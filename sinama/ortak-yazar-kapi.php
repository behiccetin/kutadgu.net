<?php
/* =====================================================================
   ORTAK YAZAR: ARAMA VE YAZARLIK BİLDİRİMİ · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KURUL KARARI — 15 Ağustos 2026:
     "Çalışma gönderen yazar, ekleyeceği kişiler sistemde kayıtlıysa
      oradan seçebilir, ORCID'ini girerek arayabilir. Eklediği yazar
      sistemde yoksa o kişinin e-postasını girer ve ona ilgili makalede
      yer aldığını söyleyen davet bağlantısı gider."

   Bu kapı üç şeyi ayrı ayrı ölçer:

     1. ARAMA YALNIZCA ORCID İLE YAPILIYOR MU. E-postayla arayan bir uç,
        "bu adres burada kayıtlı mı" sorusuna herkese yanıt veren bir
        makinedir; üyelik bilgisini kamuya açar. Kapı bunun KAPALI
        olduğunu ölçer — açılırsa kusur sessizce geri gelir.
     2. ARAMA E-POSTA SIZDIRIYOR MU. ORCID herkese açıktır, e-posta
        değildir; yanıtta adres bulunmamalıdır.
     3. YAZARLIK GERÇEKTEN BİLDİRİLİYOR MU. "Davet gider" demek yetmez;
        posta kütüğünde mektup, hesaplar dosyasında davet jetonu ve
        başvuru kaydında bildirim izi olmalıdır. Bu oturumda on beş kez
        görülen kusur tam buydu: sistemin duyurduğu şeyi yapmaması.

   BU BETİK VERİYİ DEĞİŞTİRİR: gerçek uca gerçek başvuru gönderir.
   Dokunduğu dosyaların yedeğini alır ve her çıkışta geri yükler.

   Kullanım:
     KUTADGU_DATA=/.../ktest-data KPORT=8941 php ortak-yazar-kapi.php
   ===================================================================== */
declare(strict_types=1);

const KOK = 'http://127.0.0.1:';
define('KPORT', getenv('KPORT') ?: '8941');
define('DATA', rtrim((string)(getenv('KUTADGU_DATA') ?: ''), '/'));
$KOD = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
if (DATA === '' || !is_dir(DATA)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* ---- yedek: betik gerçek arşivin üstüne yazar ---- */
const YEDEKLENEN = ['basvurular.json', 'hesaplar.json', 'hiz-sinir.json', 'eposta.log'];
$YEDEK = [];
foreach (YEDEKLENEN as $d) {
    $k = DATA . '/' . $d; $h = '/tmp/oy-yedek-' . $d;
    if (is_file($k)) { copy($k, $h); $YEDEK[$d] = $h; } else { $YEDEK[$d] = null; @unlink($h); }
}
register_shutdown_function(function () use ($YEDEK): void {
    foreach ($YEDEK as $d => $h) {
        $k = DATA . '/' . $d;
        if ($h === null) { @unlink($k); continue; }
        @copy($h, $k);
    }
});
echo "  (yedek: /tmp/oy-yedek-*; cikista geri yuklenir)\n";
file_put_contents(DATA . '/hiz-sinir.json', '{}');

/* GÖNDERİM HESAP İSTER (kurul kararı, 15 Ağustos 2026). Bu kapı
   /yazar-basvuru'ya gerçek istek atıyor, dolayısıyla gerçek bir oturum
   taşımak zorunda: kimliksiz istek 401 döner ve kapı, ölçmek istediği
   şeye hiç ulaşamaz. Çerez ilk girişte alınır ve sonraki her isteğe
   eklenir. */
$KEREZ = '';
function ist(string $yol, $govde = null, string $metod = 'POST'): array {
    global $KEREZ;
    $b = ['Content-Type: application/json', 'Accept: application/json'];
    if ($KEREZ !== '') $b[] = 'Cookie: ' . $KEREZ;
    $ctx = stream_context_create(['http' => [
        'method' => $metod, 'header' => implode("\r\n", $b),
        'content' => $govde === null ? '' : (string)json_encode($govde, JSON_UNESCAPED_UNICODE),
        'ignore_errors' => true, 'timeout' => 60]]);
    $g = @file_get_contents(KOK . KPORT . '/api' . $yol, false, $ctx);
    $h = $http_response_header ?? []; $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    $j = json_decode((string)$g, true);
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g, 'j' => is_array($j) ? $j : []];
}
function bas_oku(array $h, string $ad): string {
    foreach ($h as $x) if (stripos($x, $ad . ':') === 0) return trim(substr($x, strlen($ad) + 1));
    return '';
}
function gonderen_oturumu(): void {
    global $KEREZ;
    $r = ist('/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
    $sc = bas_oku($r['basliklar'] ?? [], 'Set-Cookie');
    if ($sc !== '') $KEREZ = explode(';', $sc)[0];
}
function hesaplar(): array {
    $d = json_decode((string)@file_get_contents(DATA . '/hesaplar.json'), true);
    return is_array($d) ? $d : [];
}
function hesap_bul(string $e): ?array {
    foreach (hesaplar() as $h) {
        if (is_array($h) && strcasecmp(trim((string)($h['eposta'] ?? '')), $e) === 0) return $h;
    }
    return null;
}
function basvurular(): array {
    $d = json_decode((string)@file_get_contents(DATA . '/basvurular.json'), true);
    return is_array($d) ? $d : [];
}
function postalar(): string { return (string)@file_get_contents(DATA . '/eposta.log'); }

gonderen_oturumu();
$api = (string)@file_get_contents($KOD . '/api/index.php');
$bsv = (string)@file_get_contents($KOD . '/basvuru.php');
den('kaynaklar okunabildi', $api !== '' && $bsv !== '');

/* =====================================================================
   1. ARAMA UCU
   ===================================================================== */
echo "\n== 1. ORCID ile arama ==\n";
$olcum = hesap_bul('cbehic@gmail.com');
den('ölçüm hesabı duruyor', $olcum !== null);

/* Ölçüm hesabına ORCID yazılır: aramanın bulacağı bir hedef gerekir.
   Yedekten geri yükleneceği için kalıcı bir değişiklik değildir. */
$hs = hesaplar();
$ORC = '0000-0002-1825-0097';
foreach ($hs as $i => $h) {
    if (is_array($h) && strcasecmp((string)($h['eposta'] ?? ''), 'cbehic@gmail.com') === 0) {
        $hs[$i]['orcid'] = $ORC; unset($hs[$i]['dizin_gizli']);
    }
}
file_put_contents(DATA . '/hesaplar.json', (string)json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$r = ist('/yazar-ara', ['orcid' => $ORC]);
den('kayıtlı ORCID bulunuyor', $r['kod'] === 200 && !empty($r['j']['bulundu']), $r['kod'] . ' ' . $r['govde']);
olc('yanıt: ' . mb_substr($r['govde'], 0, 160));
den('  ad dönüyor', trim((string)($r['j']['ad'] ?? '')) !== '');
/* EN ÖNEMLİ ÖLÇÜM: ORCID herkese açıktır, e-posta değildir. */
den('  E-POSTA DÖNMÜYOR (adres sızıntısı yok)',
    stripos($r['govde'], '@') === false, $r['govde']);

$r = ist('/yazar-ara', ['orcid' => '0000-0001-5109-3700']);
den('bilinmeyen ORCID: 200 ve bulundu=false (hata değil)',
    $r['kod'] === 200 && isset($r['j']['bulundu']) && $r['j']['bulundu'] === false,
    $r['kod'] . ' ' . $r['govde']);

$r = ist('/yazar-ara', ['orcid' => 'abc']);
den('geçersiz ORCID reddediliyor', $r['kod'] === 400, (string)$r['kod']);

/* E-POSTAYLA ARAMA OLMAMALI: adres verilip ORCID verilmezse uç,
   "kayıtlı mı" sorusuna yanıt vermek yerine geçersiz ORCID der. */
$r = ist('/yazar-ara', ['eposta' => 'cbehic@gmail.com']);
den('E-POSTAYLA ARAMA KAPALI (üyelik sorulamaz)', $r['kod'] === 400, $r['kod'] . ' ' . $r['govde']);
den('  kaynakta da e-posta ile arayan bir dal yok',
    !preg_match("#yazar-ara.{0,1200}\\\$g\['eposta'\]#s", $api));

/* Kendini dizinden çıkarmış kişi bulunmaz. */
$hs = hesaplar();
foreach ($hs as $i => $h) {
    if (is_array($h) && strcasecmp((string)($h['eposta'] ?? ''), 'cbehic@gmail.com') === 0) $hs[$i]['dizin_gizli'] = true;
}
file_put_contents(DATA . '/hesaplar.json', (string)json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$r = ist('/yazar-ara', ['orcid' => $ORC]);
den('kendini dizinden çıkaran kişi ORCID ile de bulunmuyor',
    $r['kod'] === 200 && ($r['j']['bulundu'] ?? true) === false, $r['govde']);
/* geri al */
$hs = hesaplar();
foreach ($hs as $i => $h) {
    if (is_array($h) && strcasecmp((string)($h['eposta'] ?? ''), 'cbehic@gmail.com') === 0) unset($hs[$i]['dizin_gizli']);
}
file_put_contents(DATA . '/hesaplar.json', (string)json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* =====================================================================
   2. ORTAK YAZARIN E-POSTASI ZORUNLU
   ===================================================================== */
echo "\n== 2. Ortak yazarın e-postası zorunlu ==\n";
function basvuru_govde(array $ortaklar): array {
    return [
        'unvan' => 'Dr.', 'ad' => 'Dr. Ölçüm Gönderen', 'eposta' => 'gonderen@ornek.edu.tr',
        'kurum' => 'Ölçüm Üniversitesi', 'orcid' => '0000-0002-1694-233X',
        'makale_dil' => 'tr', 'makale_baslik' => 'Ortak yazar bildirimi ölçümü',
        'makale_ozet' => str_repeat('Ölçüm özeti. ', 20),
        'makale_baslik_en' => 'Co author notification measurement',
        'makale_ozet_en' => str_repeat('Measurement abstract. ', 20),
        /* Çalışma İngilizce değil: genişletilmiş özet eşiği (ayar.php)
           kadar sözcük gerekir. Eşiği kapı YAZMAZ, fazlasıyla geçer. */
        'makale_genis_ozet_en' => str_repeat('This is an extended abstract sentence for the measurement. ', 120),
        'alanlar' => ['5.2'],
        /* 15 Ağustos 2026: tam metin ve kaynakça gönderim anında
           isteniyor; künye dilindeki başlık ve özet de zorunlu. */
        'makale_metin' => '<h2>Giriş</h2><p>' . str_repeat('ölçüm metni ', 500) . '</p>',
        'makale_kaynakca' => '<p>Ölçüm, K. (2026). Ortak yazar ölçümü. Sınama Yayınları.</p>',
        'telif_kabul' => true, 'kosullar_okundu' => true,
        'yazarlar' => $ortaklar,
        'yz_kullanim' => 'yok', 'yz_aciklama' => '', 'yz_etik_kabul' => false,
        'cikar_catismasi' => 'yok', 'tek_gonderim' => true, 'fon_durum' => 'yok',
        'yazar_tam' => 'hepsi',
        'etik_durum' => 'gereksiz', 'etik_kurul' => '', 'etik_tarih' => '', 'etik_no' => '', 'etik_belge' => '',
        'veri_beyan' => 'yok', 'veri_url' => '', 'veri_gerekce' => '',
        'sekil_link' => '', 'veri_link' => '', 'analiz_arac' => '', 'analiz_kod' => '',
        'hakem_oneri' => [],
    ];
}
$r = ist('/yazar-basvuru', basvuru_govde([
    ['unvan' => 'Dr.', 'ad' => 'Dr. Adressiz Ortak', 'kurum' => 'X', 'orcid' => '0000-0003-1613-5981'],
]));
den('e-postasız ortak yazar REDDEDİLİYOR', $r['kod'] === 400, $r['kod'] . ' ' . mb_substr($r['govde'], 0, 120));
/* Uç, dil verilmediğinde İngilizce konuşur; kapı iki dili de kabul
   eder — ölçülen şey gerekçenin SÖYLENMESİ, hangi dilde söylendiği
   değil. */
den('  gerekçesi söyleniyor',
    (bool)preg_match('/e-posta|e mail/iu', $r['govde']), $r['govde']);
den('  sayfada da aynı denetim var (betik ile uç ayrışmıyor)',
    str_contains($bsv, 'eYEposta') && str_contains($bsv, '.y-eposta'));

/* =====================================================================
   3. KAYITSIZ ORTAK YAZARA DAVET GİDİYOR
   ===================================================================== */
echo "\n== 3. Kayıtsız ortak yazara davet ==\n";
$YENI = 'yeniortak@ornek.edu.tr';
$hs = array_values(array_filter(hesaplar(), fn($h) => is_array($h) && strcasecmp((string)($h['eposta'] ?? ''), $YENI) !== 0));
file_put_contents(DATA . '/hesaplar.json', (string)json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
@unlink(DATA . '/eposta.log');

$r = ist('/yazar-basvuru', basvuru_govde([
    ['unvan' => 'Dr.', 'ad' => 'Dr. Yeni Ortak', 'kurum' => 'Y Üniversitesi',
     'orcid' => '0000-0003-1613-5981', 'eposta' => $YENI],
]));
den('başvuru alındı', $r['kod'] === 200 && !empty($r['j']['ok']), $r['kod'] . ' ' . mb_substr($r['govde'], 0, 200));

$yh = hesap_bul($YENI);
den('  kayıtsız yazar için bir kayıt açıldı', $yh !== null);
/* Kayıt yoksa aşağıdakiler ÖLÇÜLEMEZ. Ölçülemeyene GECTI verilmez
   (OKUBENI genel kuralı): boş diziye bakıp "rol taşımıyor" demek,
   olmayan bir şeyi doğru saymaktır. */
$yh = is_array($yh) ? $yh : [];
den('  kaydın DAVET jetonu var', is_array($yh['davet'] ?? null) && ($yh['davet']['ozet'] ?? '') !== '');
/* EN ÖNEMLİ ÖLÇÜM: yazarlık daveti HİÇBİR ROL TAŞIMAZ. */
den('  davet HİÇBİR ROL taşımıyor (yetki dağıtmıyor)',
    $yh !== [] && empty($yh['roller']), json_encode($yh['roller'] ?? null));
den('  parola kurulmamış (hesap değil, davet)',
    $yh !== [] && trim((string)($yh['parola'] ?? '')) === '');
$pl = postalar();
den("  kişiye mektup çıkarıldı (kütükte kaydı var)", stripos($pl, $YENI) !== false);
/* MEKTUBUN GÖVDESİ KÜTÜĞE YAZILMAZ (yazılsaydı davet jetonu kütükte
   dururdu ve kütüğü okuyan herkes o hesabı kurabilirdi). Bu yüzden
   gövde KAYNAKTAN ölçülür: davet dalı hesap-kur bağlantısını ve
   "haberiniz yoksa" yolunu gerçekten yazıyor mu. */
$dal = '';
$dp = strpos($api, 'ORTAK YAZARA YAZARLIĞI BİLDİRİLİR');
if ($dp !== false) $dal = substr($api, $dp, 6000);
den('  bildirim bölümü kaynakta bulundu', $dal !== '');
den('  davet dalı hesap-kur bağlantısı yazıyor', str_contains($dal, '/hesap-kur.php?k='));
den('  "haberiniz yoksa" yolu yazılı', str_contains($dal, 'iletisim.php'));
den('  davet otuz gün geçerli', str_contains($dal, '30 * 86400'));
$bs = basvurular(); $son = $bs ? $bs[count($bs) - 1] : [];
$ort = null;
foreach ((array)($son['yazarlar'] ?? []) as $ya) {
    if (is_array($ya) && strcasecmp((string)($ya['eposta'] ?? ''), $YENI) === 0) $ort = $ya;
}
/* KARAR İLE TESLİM AYRI KAYITLARDIR. Sınama ortamında posta yazılımı
   yoktur ve mektup gitmez; bu bir kusur değil, ortamın özelliğidir.
   Ölçülen şey KARARIN kaydedilmesi — 'davet' mi 'bildirim' mi — çünkü
   posta yeniden denenecekse hangi mektubun deneneceği o kayıttan
   okunur. */
den('  kayıtta KARAR duruyor (davet)', is_array($ort) && (string)($ort['bildirim'] ?? '') === 'davet',
    json_encode($ort['bildirim'] ?? null));
den('  teslim durumu ayrı alanda', is_array($ort) && array_key_exists('bildirim_gitti', $ort),
    json_encode($ort['bildirim_gitti'] ?? null));

/* =====================================================================
   4. KAYITLI ORTAK YAZARA BİLDİRİM (DAVET DEĞİL)
   ===================================================================== */
echo "\n== 4. Kayıtlı ortak yazara bildirim ==\n";
@unlink(DATA . '/eposta.log');
$r = ist('/yazar-basvuru', basvuru_govde([
    ['unvan' => 'Dr.', 'ad' => 'Behiç Çetin', 'kurum' => 'Ölçüm',
     'orcid' => '0000-0002-1825-0097', 'eposta' => 'cbehic@gmail.com'],
]));
den('başvuru alındı', $r['kod'] === 200 && !empty($r['j']['ok']), $r['kod'] . ' ' . mb_substr($r['govde'], 0, 200));
$pl = postalar();
den('  kayıtlı yazara da mektup gitti', stripos($pl, 'cbehic@gmail.com') !== false);
/* PAROLALI HESABA DAVET ÇIKARILMAZ: davet, parolalı bir hesabı ele
   geçirmenin en kısa yoludur (aynı gerekçe hesap-davet ucunda yazılı). */
den('  PAROLALI hesaba davet bağlantısı GÖNDERİLMİYOR',
    !str_contains($pl, '/hesap-kur.php?k='), mb_substr($pl, 0, 200));
$oh = hesap_bul('cbehic@gmail.com');
den('  var olan hesaba davet jetonu yazılmadı', empty($oh['davet']));
$bs = basvurular(); $son = $bs ? $bs[count($bs) - 1] : [];
$ort = null;
foreach ((array)($son['yazarlar'] ?? []) as $ya) {
    if (is_array($ya) && strcasecmp((string)($ya['eposta'] ?? ''), 'cbehic@gmail.com') === 0) $ort = $ya;
}
den('  kayıtta KARAR duruyor (bildirildi)', is_array($ort) && (string)($ort['bildirim'] ?? '') === 'bildirildi',
    json_encode($ort['bildirim'] ?? null));

/* =====================================================================
   5. BAŞVURANIN KENDİSİNE İKİ KEZ YAZILMIYOR
   ===================================================================== */
echo "\n== 5. Başvuranın kendisi ==\n";
@unlink(DATA . '/eposta.log');
$r = ist('/yazar-basvuru', basvuru_govde([
    ['unvan' => 'Dr.', 'ad' => 'Dr. Ölçüm Gönderen', 'kurum' => 'Ölçüm Üniversitesi',
     'orcid' => '0000-0002-1694-233X', 'eposta' => 'gonderen@ornek.edu.tr'],
]));
den('başvuru alındı', $r['kod'] === 200, (string)$r['kod']);
$bs = basvurular(); $son = $bs ? $bs[count($bs) - 1] : [];
$kendi = null;
foreach ((array)($son['yazarlar'] ?? []) as $ya) {
    if (is_array($ya) && strcasecmp((string)($ya['eposta'] ?? ''), 'gonderen@ornek.edu.tr') === 0) $kendi = $ya;
}
den('  gönderenin kendisine "sizi eklediler" yazılmıyor',
    is_array($kendi) && (string)($kendi['bildirim'] ?? '') === 'basvuran',
    json_encode($kendi['bildirim'] ?? null));

/* =====================================================================
   6. DAVET BAĞLANTISI KARŞI TARAFTA ÇALIŞIYOR MU
   ---------------------------------------------------------------------
   Mektuptaki bağlantı kütüğe yazılmaz (yazılsaydı jeton kütüğü okuyan
   herkeste olurdu), bu yüzden kapı AYNI BİÇİMDE bir jeton üretip
   karşılama ucunu çalıştırır. Ölçülen şey: ROLSÜZ bir davetin —yazar
   bildirimi böyle bir davettir— karşı tarafta kabul edilmesi. Kabul
   edilmezse mektup gider, kişi tıklar ve "geçersiz bağlantı" görür;
   bu, gönderilen ama işlemeyen bir davettir.
   ===================================================================== */
echo "\n== 6. Davet bağlantısı karşı tarafta ==\n";
$JET = bin2hex(random_bytes(32));
$hs = hesaplar();
$bulundu = false;
foreach ($hs as $i => $h) {
    if (!is_array($h) || strcasecmp((string)($h['eposta'] ?? ''), $YENI) !== 0) continue;
    $hs[$i]['davet'] = ['ozet' => hash('sha256', $JET), 'olusma' => date('c'),
                        'bitis' => date('c', time() + 30 * 86400), 'kuran' => 'ölçüm'];
    $bulundu = true;
}
den('davet kaydı yerleştirildi', $bulundu);
file_put_contents(DATA . '/hesaplar.json', (string)json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents(DATA . '/hiz-sinir.json', '{}');
$r = ist('/davet-bilgi', ['k' => $JET]);
den('  rolsüz davet karşılama ucunda kabul ediliyor', $r['kod'] === 200 && !empty($r['j']['ok']),
    $r['kod'] . ' ' . mb_substr($r['govde'], 0, 140));
den('  kişiye editörlük gösterilmiyor', ($r['j']['editor'] ?? true) === false,
    json_encode($r['j']['editor'] ?? null));
den('  baş editör yetkisi gösterilmiyor', ($r['j']['bas_yetki'] ?? true) === false,
    json_encode($r['j']['bas_yetki'] ?? null));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
