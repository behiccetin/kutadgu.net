<?php
/* =====================================================================
   İLETİŞİM: kapı ölçümü. Depoya girmez.

   NEDEN VAR
   ---------
   İletişim sayfası ziyaretçiye şunu yazıyor:

     "Yanıt geldiğinde burada belirir ve e-posta ile de haber verir."

   Ölçüldüğünde yanıt verecek HİÇBİR YOL olmadığı görüldü. Panelde bölüm
   yoktu, uç yoktu; Telegram'dan yanıtlamayı sağlayacak webhook ise
   yalnız bir YORUM olarak duruyordu, kodu hiç yazılmamıştı. Yani sistem
   tutamayacağı bir söz veriyordu ve bu sözü en pahalı yerde veriyordu:
   bir kurum destek için yazdığında.

   İkinci kusur ondan da ağırdı ve DEVİRLE ilgiliydi: bir iletinin
   varacağı tek yer kurucunun telefonundaki Telegram botuydu. Sistem bir
   kuruma devredildiğinde iletiler devredenin telefonuna düşmeyi
   sürdürecek, devralan kurum kendisine yazılanları hiç görmeyecekti.

   Bu kapı üç şeyi ayrı ayrı ölçer:

     1. VARIŞ     ileti bir insana ulaşıyor mu, ve ayarda yazılı olan
                  kişiye mi (kişiye çakılı değil mi)
     2. YANIT     yanıt verilebiliyor mu, yanıt kişinin konuşmasına ve
                  e-postasına düşüyor mu
     3. YETKİ     bu uçlar dışarıya kapalı mı, ve okuma ile yazma
                  yetkisi ayrı mı

   ÖLÇÜM AYARA BAĞLI DEĞİLDİR: kapı alıcı listesini kendisi yazar ve
   iletiyi kendisi gönderir. Sınama verisinde bir alıcı listesi
   olmadığı için "geçti" demek hiçbir şey söylemezdi.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php iletisim-kapi.php
   ===================================================================== */
declare(strict_types=1);

const KOK = 'http://127.0.0.1:';
define('KPORT', getenv('KPORT') ?: '8941');
define('DATA', getenv('KUTADGU_DATA') ?: '');
if (DATA === '' || !is_dir(DATA)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* ---- Ölçümün bozduğu dosyalar geri konur ---- */
$YEDEK = [];
foreach (['yonetim-ayar.json', 'iletiler.json', 'auth.json', 'hesaplar.json', 'eposta.log', 'telegram.log'] as $d) {
    $y = DATA . '/' . $d;
    $YEDEK[$d] = is_file($y) ? (string)file_get_contents($y) : null;
}
register_shutdown_function(function () use ($YEDEK) {
    foreach ($YEDEK as $d => $icerik) {
        $y = DATA . '/' . $d;
        if ($icerik === null) { @unlink($y); continue; }
        @file_put_contents($y, $icerik);
    }
});

/* ---- Kurulum ---- */
$YON_PAROLA = 'iletisimOlcumu-2026-xQ';
file_put_contents(DATA . '/auth.json', json_encode(
    ['kullanici' => 'iletiolcum', 'hash' => password_hash($YON_PAROLA, PASSWORD_DEFAULT)],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* ALICI LİSTESİ KAPININ KENDİSİ TARAFINDAN YAZILIR.
   İki alıcı: biri yalnız 'kurum' ve 'destek' türlerini alır, öteki
   hepsini. Yönlendirmenin gerçekten türe göre yapıldığı ancak iki
   ayrı alıcıyla ölçülebilir; tek alıcıyla her yönlendirme "doğru"
   görünürdü. */
$ayar = is_file(DATA . '/yonetim-ayar.json')
    ? (array)json_decode((string)file_get_contents(DATA . '/yonetim-ayar.json'), true) : [];
$ayar['iletisim'] = ['alicilar' => [
    ['ad' => 'Kurum Sorumlusu', 'eposta' => 'olcum-kurum@example.org', 'turler' => ['kurum', 'destek']],
    ['ad' => 'Genel',           'eposta' => 'olcum-genel@example.org', 'turler' => ['*']],
]];
file_put_contents(DATA . '/yonetim-ayar.json', json_encode($ayar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
@unlink(DATA . '/hiz-sinir.json');
@unlink(DATA . '/eposta.log');

$KAVANOZ = tempnam('/tmp', 'ileti-cerez');
function ist(string $yol, ?array $govde = null, string $metod = ''): array {
    global $KAVANOZ;
    $ch = curl_init(KOK . KPORT . $yol);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $KAVANOZ, CURLOPT_COOKIEFILE => $KAVANOZ, CURLOPT_TIMEOUT => 15]);
    if ($govde !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($govde, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    if ($metod !== '') curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metod);
    $g = (string)curl_exec($ch);
    $k = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['kod' => $k, 'govde' => $g, 'json' => json_decode($g, true)];
}
function postaKutugu(): array {
    $y = DATA . '/eposta.log';
    return is_file($y) ? (array)file($y, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
}
function postayaGitti(string $adres): bool {
    foreach (postaKutugu() as $s) if (strpos($s, "\t" . $adres . "\t") !== false) return true;
    return false;
}

echo "== İletişim kapısı ==\n";

/* =====================================================================
   1. YETKİ: UÇLAR DIŞARIYA KAPALI MI
   ===================================================================== */
echo "\n== 1. Yetki (giriş yapılmadan) ==\n";
foreach ([['GET', '/api/yonetim/ileti-liste', null],
          ['GET', '/api/yonetim/ileti-oku?kod=abc', null],
          ['POST', '/api/yonetim/ileti-yanit', ['kod' => 'abc', 'metin' => 'olmaz']],
          ['POST', '/api/yonetim/ileti-kapat', ['kod' => 'abc']]] as $u) {
    $a = $u[0] === 'GET' ? ist($u[1]) : ist($u[1], $u[2]);
    den('girişsiz ' . $u[1] . ' reddediliyor', $a['kod'] === 403, 'kod ' . $a['kod']);
}

/* =====================================================================
   2. VARIŞ: İLETİ BİR İNSANA ULAŞIYOR MU
   ===================================================================== */
echo "\n== 2. Varış ==\n";
$a = ist('/api/iletisim', ['ad' => 'Bir Üniversite', 'eposta' => 'olcum-yazan@example.org',
    'tur' => 'kurum', 'konu' => 'Himaye görüşmesi',
    'metin' => 'Kurumumuz bu sistemi desteklemek istiyor; görüşebilir miyiz acaba bu konuda.']);
den('kurum iletisi kabul edildi', !empty($a['json']['ok']), substr($a['govde'], 0, 140));
$anahtar = (string)($a['json']['anahtar'] ?? '');
den('  kişiye kendi konuşma anahtarı verildi', strlen($anahtar) >= 24);

/* Türe göre yönlendirme: iki alıcı da haber almalı, çünkü biri
   'kurum' türünü, öteki hepsini alıyor. */
den('  türe bağlı alıcı haber aldı', postayaGitti('olcum-kurum@example.org'), implode(' | ', postaKutugu()));
den('  genel alıcı da haber aldı', postayaGitti('olcum-genel@example.org'));
den('  yazana da bir kopya gitti', postayaGitti('olcum-yazan@example.org'));

/* AYARDAKİ LİSTEYE GERÇEKTEN BAKILIYOR MU: yalnız 'genel' türünü
   almayan bir alıcı, 'oneri' türünde haber ALMAMALI. Bu deneme
   olmadan, "hepsine gönderiyorum" da geçerdi. */
@unlink(DATA . '/eposta.log');
$a = ist('/api/iletisim', ['ad' => 'Bir Okur', 'eposta' => 'olcum-okur@example.org',
    'tur' => 'oneri', 'konu' => 'Küçük bir öneri',
    'metin' => 'Arama sayfasında bir kolaylık önerim var, kısaca yazıyorum burada.']);
den('öneri iletisi kabul edildi', !empty($a['json']['ok']));
den('  yalnız kurum türünü alan kişiye GİTMEDİ', !postayaGitti('olcum-kurum@example.org'),
    implode(' | ', postaKutugu()));
den('  hepsini alan kişiye gitti', postayaGitti('olcum-genel@example.org'));

/* =====================================================================
   3. YANIT: VERİLEBİLİYOR MU VE İKİ YERE BİRDEN DÜŞÜYOR MU
   ===================================================================== */
echo "\n== 3. Yanıt ==\n";
$a = ist('/api/login', ['kullanici' => 'iletiolcum', 'parola' => $YON_PAROLA]);
den('yönetici girişi (ölçümün önkoşulu)', !empty($a['json']['ok']), substr($a['govde'], 0, 120));

$a = ist('/api/yonetim/ileti-liste');
den('iletiler listeleniyor', !empty($a['json']['ok']) && is_array($a['json']['ileti'] ?? null),
    substr($a['govde'], 0, 140));
$liste = (array)($a['json']['ileti'] ?? []);
den('  iki ileti de listede', count($liste) >= 2, (string)count($liste));
den('  bekleyen sayısı bildiriliyor', ($a['json']['bekleyen'] ?? -1) >= 2, (string)($a['json']['bekleyen'] ?? -1));

$kurumKod = '';
foreach ($liste as $x) { if (($x['tur'] ?? '') === 'kurum') { $kurumKod = (string)$x['kod']; break; } }
den('  kurum iletisi türüyle birlikte listede', $kurumKod !== '');

$a = ist('/api/yonetim/ileti-oku?kod=' . rawurlencode($kurumKod));
den('ileti açılıyor', !empty($a['json']['ok']), substr($a['govde'], 0, 140));
den('  gönderenin metni okunuyor',
    strpos((string)$a['govde'], 'desteklemek istiyor') !== false);

@unlink(DATA . '/eposta.log');
$a = ist('/api/yonetim/ileti-yanit', ['kod' => $kurumKod,
    'metin' => 'Teşekkür ederiz, görüşmek isteriz. Önümüzdeki hafta uygun musunuz acaba.']);
den('YANIT VERİLEBİLİYOR', !empty($a['json']['ok']), substr($a['govde'], 0, 160));
den('  yanıt kişinin e-postasına gitti', postayaGitti('olcum-yazan@example.org'), implode(' | ', postaKutugu()));

/* Yanıt kişinin KENDİ konuşma sayfasında da görünmeli: e-posta
   gitmeseydi bile kişi bağlantısını açtığında yanıtı bulmalı. */
$a = ist('/api/iletisim-konusma?k=' . rawurlencode($anahtar));
den('  yanıt konuşma sayfasında görünüyor',
    strpos((string)$a['govde'], 'görüşmek isteriz') !== false, substr($a['govde'], 0, 200));
den('  yanıtın kimden geldiği yazılı ("kurul")',
    strpos((string)$a['govde'], '"kurul"') !== false);

/* Yanıttan sonra ileti artık BEKLEMİYOR olmalı; yoksa bekleyen
   listesi zamanla anlamını yitirir. */
$a = ist('/api/yonetim/ileti-liste?durum=bekleyen');
$bekleyenKod = array_column((array)($a['json']['ileti'] ?? []), 'kod');
den('  yanıtlanan ileti bekleyenlerden düştü', !in_array($kurumKod, $bekleyenKod, true),
    implode(',', $bekleyenKod));

/* =====================================================================
   4. KAPATMA SİLMEK DEĞİLDİR
   ===================================================================== */
echo "\n== 4. Kapatma ==\n";
$a = ist('/api/yonetim/ileti-kapat', ['kod' => $kurumKod]);
den('ileti kapatılıyor', !empty($a['json']['ok']) && !empty($a['json']['kapali']), substr($a['govde'], 0, 140));
$a = ist('/api/iletisim-konusma?k=' . rawurlencode($anahtar));
den('  kapatılan konuşma kişinin bağlantısında DURUYOR', !empty($a['json']['ok']),
    substr($a['govde'], 0, 140));
$a = ist('/api/yonetim/ileti-kapat', ['kod' => $kurumKod, 'ac' => true]);
den('  yeniden açılabiliyor', !empty($a['json']['ok']) && empty($a['json']['kapali']));

/* =====================================================================
   5. DEVREDİLEBİLİRLİK: ALICI KİŞİYE ÇAKILI DEĞİL
   ===================================================================== */
echo "\n== 5. Devredilebilirlik ==\n";
$ayar2 = (array)json_decode((string)file_get_contents(DATA . '/yonetim-ayar.json'), true);
$ayar2['iletisim']['alicilar'] = [['ad' => 'Devralan Kurum', 'eposta' => 'olcum-devralan@example.org', 'turler' => ['*']]];
file_put_contents(DATA . '/yonetim-ayar.json', json_encode($ayar2, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
@unlink(DATA . '/eposta.log');
@unlink(DATA . '/hiz-sinir.json');
$a = ist('/api/iletisim', ['ad' => 'Yeni Yazan', 'eposta' => 'olcum-yeni@example.org',
    'tur' => 'genel', 'konu' => 'Devir sonrası',
    'metin' => 'Devirden sonra iletinin nereye gittiği ölçülüyor; bu bir sınama iletisidir.']);
den('devirden sonra ileti kabul ediliyor', !empty($a['json']['ok']), substr($a['govde'], 0, 140));
den('  YENİ alıcıya gidiyor', postayaGitti('olcum-devralan@example.org'), implode(' | ', postaKutugu()));
den('  ESKİ alıcıya artık gitmiyor', !postayaGitti('olcum-genel@example.org'));
olc('alıcı listesi tek dosyada: yonetim-ayar.json -> iletisim.alicilar');

/* =====================================================================
   6. İSTENMEYEN İŞARETİ VE SİLME
   ---------------------------------------------------------------------
   Tahmin eden bir süzgeç YOKTUR ve olmamalıdır: bu formdan geçen en
   değerli şey gerçek bir kurum başvurusudur ve yanlış pozitif tam
   olarak onu çöpe atar. Karar insanda kalır; ölçülen şey, verilen
   kararın SONUCUNUN her yere birden taşınıp taşınmadığıdır.
   ===================================================================== */
echo "\n== 6. İstenmeyen ve silme ==\n";
@unlink(DATA . '/hiz-sinir.json');
$a = ist('/api/iletisim', ['ad' => 'Reklamci', 'eposta' => 'olcum-reklam@example.org',
    'tur' => 'kurum', 'konu' => 'Teklif',
    'metin' => 'Size daha fazla kurum bulmanizda yardimci olabiliriz; kisa bir gorusme ayarlayalim mi.']);
den('istenmeyen olacak ileti kabul edildi', !empty($a['json']['ok']));
$a = ist('/api/yonetim/ileti-liste?durum=bekleyen');
$bek1 = (int)count((array)($a['json']['ileti'] ?? []));
$spamKod = '';
foreach ((array)($a['json']['ileti'] ?? []) as $x) if (($x['konu'] ?? '') === 'Teklif') $spamKod = (string)$x['kod'];
den('  önce bekleyenler arasında', $spamKod !== '', 'bekleyen ' . $bek1);

$a = ist('/api/yonetim/ileti-istenmeyen', ['kod' => $spamKod]);
den('istenmeyen işaretlenebiliyor', !empty($a['json']['ok']), substr($a['govde'], 0, 140));
$a = ist('/api/yonetim/ileti-liste?durum=bekleyen');
$bekKod = array_column((array)($a['json']['ileti'] ?? []), 'kod');
den('  BEKLEYENLERDEN düştü', !in_array($spamKod, $bekKod, true));
$a = ist('/api/yonetim/ileti-liste');
den('  genel listede de görünmüyor', !in_array($spamKod, array_column((array)($a['json']['ileti'] ?? []), 'kod'), true));
$a = ist('/api/yonetim/ileti-liste?durum=istenmeyen');
den('  ama kendi süzgecinde duruyor (silinmedi)',
    in_array($spamKod, array_column((array)($a['json']['ileti'] ?? []), 'kod'), true));

/* Yayımlanan ölçüme girmemeli: sistemin kendi vaadini bir reklamla
   ölçmesi, ölçüyü anlamsız kılar. */
require_once (getenv('KTEST_DIR') ?: '/home/claude/kg/ktest') . '/ortak.php';
$s1 = tg_ileti_sureleri();
$a = ist('/api/yonetim/ileti-istenmeyen', ['kod' => $spamKod, 'geri' => true]);
den('işaret geri alınabiliyor', !empty($a['json']['ok']));
$s2 = tg_ileti_sureleri();
den('  ölçüm işaretle DEĞİŞİYOR (yani işaretli olan sayılmıyor)',
    $s2['toplam'] > $s1['toplam'], $s1['toplam'] . ' -> ' . $s2['toplam']);

$a = ist('/api/yonetim/ileti-sil', ['kod' => $spamKod]);
den('elle silinebiliyor', !empty($a['json']['ok']) && (int)($a['json']['silinen'] ?? 0) === 1,
    substr($a['govde'], 0, 140));
$a = ist('/api/yonetim/ileti-liste?durum=istenmeyen');
den('  silinen ileti hiçbir listede yok',
    !in_array($spamKod, array_column((array)($a['json']['ileti'] ?? []), 'kod'), true));
/* Silme GERÇEK olmalı: yerine "silindi" taşı konsaydı, taşın kendisi
   kişiyle ilgili bir veri olurdu ve saklama süresinin amacı boşa
   çıkardı. */
$ham = (string)@file_get_contents(DATA . '/iletiler.json');
den('  kayıtta izi kalmadı (mezar taşı yok)', strpos($ham, 'olcum-reklam@example.org') === false);

/* Küme silme: deneme iletilerini tek tek tıklamadan temizlemek için. */
$a = ist('/api/yonetim/ileti-sil', ['kume' => 'kapali']);
den('küme silme çalışıyor (kapalılar)', !empty($a['json']['ok']), substr($a['govde'], 0, 120));
$a = ist('/api/yonetim/ileti-sil', ['kume' => 'hepsi']);
den('  tanımsız küme REDDEDİLİYOR (kazayla hepsi silinmez)',
    empty($a['json']['ok']) && $a['kod'] === 400, 'kod ' . $a['kod']);

/* Yetki: silme dışarıya kapalı olmalı. */
$K2 = tempnam('/tmp', 'ilt-yabanci');
$ch = curl_init(KOK . KPORT . '/api/yonetim/ileti-sil');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['kume' => 'kapali']), CURLOPT_COOKIEJAR => $K2,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'], CURLOPT_TIMEOUT => 10]);
curl_exec($ch); $kod2 = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
den('girişsiz silme reddediliyor', $kod2 === 403, 'kod ' . $kod2);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
