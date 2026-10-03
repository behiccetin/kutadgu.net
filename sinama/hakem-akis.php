<?php
/* =====================================================================
   HAKEM AKIŞI: UÇTAN UCA KAPI ÖLÇÜMÜ. Depoya girmez.

   Üç şeyi birlikte ölçer:
     a) karşılıklı hakemlik yasağının ÜÇ atama yolunda da uygulanması,
     b) yazarın hakem ÖNERMESİ (davet etmemesi),
     c) yazar formundaki ŞİFRE SIZINTISININ kapalı kalması (4. bölüm).

   BU BETİK VERİYİ DEĞİŞTİRİR. Gerçek uçlara gerçek istek atar, çünkü
   ölçülmek istenen şey tam olarak budur: kural ortak.php'de yazılı
   olabilir ama uç onu çağırmıyorsa kural yoktur. Bu yüzden dokunduğu
   dosyaların yedeğini kendisi alır ve HER ÇIKIŞTA geri yükler; yedek
   register_shutdown_function ile bağlandığı için ölümcül hatada ve
   exit() ile çıkışta da geri yükleme çalışır.

   Kullanım:
     KUTADGU_DATA=/.../veri KPORT=8941 php hakem-akis.php
   ===================================================================== */
declare(strict_types=1);

const KOK = 'http://127.0.0.1:';
define('KPORT', getenv('KPORT') ?: '8941');
define('DATA', rtrim((string)(getenv('KUTADGU_DATA') ?: ''), '/'));

if (DATA === '' || !is_dir(DATA)) {
    fwrite(STDERR, "KUTADGU_DATA tanimli degil ya da dizin yok.\n");
    exit(2);
}

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* ---------------------------------------------------------------------
   YEDEK VE GERİ YÜKLEME
   ---------------------------------------------------------------------
   Betik gerçek arşivin üstüne yazar. Yedek en başta alınır ve geri
   yükleme kapanış işlevine bağlanır: bir sınama ortasında ölümcül hata
   çıksa bile arşiv olduğu gibi kalır. Var olmayan bir dosya "yok" diye
   yedeklenir ve geri yüklemede silinir; betiğin ürettiği dosya
   arkasında kalmaz.
   --------------------------------------------------------------------- */
/* hesaplar.json de yedeklenir: 7. bölüm gerçek bir hesap açıp giriş
   yapıyor ve hesap dosyası bu betiğin dokunduğu dosyalardan biri. */
const YEDEKLENEN = ['yazilar.json', 'basvurular.json', 'hiz-sinir.json', 'hesaplar.json'];
$YEDEK = [];
foreach (YEDEKLENEN as $d) {
    $kaynak = DATA . '/' . $d;
    /* Arşivin yedeği devir belgesindeki adla durur; onu arayan biri
       bulabilsin. Yanında değişen öteki dosyalar ekli adla yedeklenir. */
    $hedef  = $d === 'yazilar.json' ? '/tmp/hakem-yedek.json' : '/tmp/hakem-yedek-' . $d;
    if (is_file($kaynak)) { copy($kaynak, $hedef); $YEDEK[$d] = $hedef; }
    else { $YEDEK[$d] = null; @unlink($hedef); }
}
register_shutdown_function(function () use ($YEDEK): void {
    foreach ($YEDEK as $d => $hedef) {
        $kaynak = DATA . '/' . $d;
        if ($hedef === null) { @unlink($kaynak); continue; }
        @copy($hedef, $kaynak);
    }
    /* Arşiv dökümü yazilar.json her değiştiğinde kendiliğinden yeniden
       üretilir; bu betiğin ürettiği çalışmalar da döküme girer. Dosyayı
       geri yüklemek dökümü geri getirmez, bu yüzden döküm "kirli"
       işaretlenir: bir sonraki üretim onu sınama verisinden temizler.
       İşaretlenmezse sınama çalışmaları kamusal döküm paketinde kalır. */
    @file_put_contents(DATA . '/dokum/kirli.json',
        (string)json_encode(['t' => time(), 'tarih' => date('c'), 'neden' => 'hakem-akis sinamasi geri yuklendi'],
            JSON_UNESCAPED_UNICODE));
});
echo "  (yedek: /tmp/hakem-yedek.json ve /tmp/hakem-yedek-*.json; cikista geri yuklenir)\n";
/* Hız sınırı sayaçları sıfırlanır: bir önceki koşudan kalan sayaç,
   bu koşuda ölçülen şeyle ilgisi olmayan 429'lar üretir. */
file_put_contents(DATA . '/hiz-sinir.json', '{}');

/* ---------------------------------------------------------------------
   HTTP yardımcıları. Yönetim oturumu çerezle taşınır; oturum açan
   istekten sonra Set-Cookie okunup saklanır.
   --------------------------------------------------------------------- */
$KEREZ = '';
function ist(string $yol, $govde = null, string $metod = 'POST', array $ekBas = []): array {
    global $KEREZ;
    $b = ['Content-Type: application/json', 'Accept: application/json'];
    if ($KEREZ !== '') $b[] = 'Cookie: ' . $KEREZ;
    foreach ($ekBas as $e) $b[] = $e;
    $ctx = stream_context_create(['http' => [
        'method'  => $metod,
        'header'  => implode("\r\n", $b),
        'content' => $govde === null ? '' : (string)json_encode($govde, JSON_UNESCAPED_UNICODE),
        'ignore_errors' => true, 'timeout' => 60]]);
    $g = @file_get_contents(KOK . KPORT . '/api' . $yol, false, $ctx);
    $h = $http_response_header ?? [];
    $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    $j = json_decode((string)$g, true);
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g, 'j' => is_array($j) ? $j : []];
}
function bas(array $h, string $ad): string {
    foreach ($h as $s) if (stripos($s, $ad . ':') === 0) return trim(substr($s, strlen($ad) + 1));
    return '';
}

/* Arşiv dosyasına doğrudan erişim. Yalnızca ESKİ BİÇİMLİ bir kaydı
   yerleştirmek ve kaydın gerçekte ne yazdığını okumak için kullanılır;
   ölçülen kapılar her zaman HTTP üzerinden çalıştırılır. */
function veri(): array {
    $d = json_decode((string)@file_get_contents(DATA . '/yazilar.json'), true);
    return is_array($d) ? $d : [];
}
function veri_yaz(array $y): void {
    file_put_contents(DATA . '/yazilar.json', (string)json_encode($y, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
function ix(array $y, string $id): int {
    foreach ($y as $i => $e) if ((string)($e['id'] ?? '') === $id) return (int)$i;
    return -1;
}
function hakem_kaydi(string $id, string $ad): array {
    foreach (veri() as $e) {
        if ((string)($e['id'] ?? '') !== $id) continue;
        foreach (($e['hakemler'] ?? []) as $h) {
            if (is_array($h) && (string)($h['ad'] ?? '') === $ad) return $h;
        }
    }
    return [];
}

/* Sınama kişileri. ORCID'lerin sağlama basamağı geçerli olmak zorunda:
   /yazar-basvuru geçersiz kimliği baştan reddeder. */
const KISI = [
    'A' => ['Ayşe Kaya',    'Prof. Dr.', 'ayse.kaya@ornek-sinama.org', '0000-0002-1825-0097'],
    'B' => ['Berk Demir',   'Prof. Dr.', 'berk.demir@ornek-sinama.org', '0000-0001-5109-3700'],
    'C' => ['Cem Aydın',    'Doç. Dr.',  'cem.aydin@ornek-sinama.org',  '0000-0003-1415-9269'],
    'D' => ['Deniz Yalçın', 'Doç. Dr.',  'deniz.yalcin@ornek-sinama.org', '0000-0002-7183-2803'],
    'E' => ['Emre Kılıç',   'Dr.',       'emre.kilic@ornek-sinama.org', '0000-0001-2718-2818'],
    'L' => ['Leyla Arslan', 'Dr.',       'leyla.arslan@ornek-sinama.org', '0000-0003-1622-7763'],
];
function k(string $h, int $n) { return KISI[$h][$n]; }

/* Bir çalışmayı gerçek yoldan açar: başvuru + yönetim kararı.
   Dönen: ['id','slug','token','sifre','eposta'] */
function calisma_ac(string $kim, string $baslik): array {
    global $KEREZ, $GONDEREN_KEREZ;
    /* Başvuru GÖNDEREN oturumuyla atılır, yönetim oturumuyla değil:
       uç hesap ister. Çerez hemen geri alınır; sonraki çağrılar yine
       yönetim kimliğiyle konuşur. */
    $yonetimKerez = $KEREZ;
    $KEREZ = $GONDEREN_KEREZ;
    $r = ist('/yazar-basvuru', [
        'unvan' => k($kim, 1), 'ad' => k($kim, 0), 'eposta' => k($kim, 2),
        'kurum' => 'Sınama Üniversitesi', 'orcid' => k($kim, 3),
        'makale_dil' => 'tr', 'makale_baslik' => $baslik,
        'makale_genis_ozet_en' => str_repeat('measurement word ', 320),
        'makale_ozet' => 'Bu çalışma yalnızca kapı ölçümü için üretilmiştir.',
        /* 15 Ağustos 2026: künye dilindeki başlık ve özet ZORUNLU;
           tam metin ve kaynakça da gönderim anında isteniyor. */
        'makale_baslik_en' => 'Gate measurement work',
        'makale_ozet_en' => 'This work was produced only for gate measurement.',
        'makale_metin' => '<h2>Giris</h2><p>' . str_repeat('olcum metni ', 500) . '</p>',
        'makale_kaynakca' => '<p>Olcum, K. (2026). Kapi olcumu. Sinama Yayinlari.</p>',
        'alan' => 'sos', 'telif_kabul' => true, 'kosullar_okundu' => true,
        'intihal_arac' => 'iThenticate', 'intihal_oran' => 6, 'intihal_tek_kaynak' => 2,
        'intihal_link' => 'https://ornek-sinama.org/intihal',
        'yz_kullanim' => 'yok', 'yz_etik_kabul' => true,
        /* 15 Ağustos 2026: beyan adımı genişledi (çıkar çatışması,
           başka yerde değerlendirilmeme, fon). Ölçülen şey hakem
           akışıdır; bu alanlar hep geçerli verilir. */
        'cikar_catismasi' => 'yok', 'tek_gonderim' => true, 'fon_durum' => 'yok',
        'yazar_tam' => 'tek',
        'veri_beyan' => 'yok', 'yazarlar' => [],
    ]);
    $KEREZ = $yonetimKerez;
    if (empty($r['j']['ok'])) return ['hata' => $r['govde']];
    $l = ist('/yonetim/basvurular', null, 'GET');
    $bid = '';
    foreach (($l['j']['basvurular'] ?? []) as $b) {
        if ((string)($b['makale_baslik'] ?? '') === $baslik) { $bid = (string)($b['id'] ?? ''); break; }
    }
    if ($bid === '') return ['hata' => 'basvuru bulunamadi'];
    $kr = ist('/yonetim/basvuru-karar', ['id' => $bid, 'karar' => 'kabul']);
    if (empty($kr['j']['ok'])) return ['hata' => $kr['govde']];
    $c = ['id' => (string)($kr['j']['yazi_id'] ?? ''), 'slug' => (string)($kr['j']['slug'] ?? ''),
          'token' => (string)($kr['j']['token'] ?? ''), 'sifre' => (string)($kr['j']['sifre'] ?? ''),
          'eposta' => k($kim, 2)];
    /* 15 Ağustos 2026: kabul edilen çalışma artık HAKEMSİZ yayımlanıyor
       (kural iki yerde yazılıydı, kod tersini yapıyordu; bkz.
       ortak.php tg_kabul_yolu). Hakemliği YAZAR açar. Bu kapı uçtan
       uca hakem akışını ölçtüğü için o adımı da atmak zorunda —
       kullanıcı da atıyor. Adım atlanırsa akış "hakem aranmıyor"
       diyerek durur ve kapı, olmayan bir kusuru bildirir. */
    /* Bu betik ortak.php'yi YÜKLEMEZ (uçlarla HTTP üzerinden konuşur),
       bu yüzden tg_kabul_yolu() burada çağrılamaz. Ölçülen şey zaten
       kuralın kendisi değil, KULLANICININ YOLUdur: aç, açıksa dokunma.
       "Zaten hakemli" yanıtı bir kusur değildir. */
    $ac = ist('/yazar-hakemlige-ac', yz($c));
    if (empty($ac['j']['ok']) && stripos($ac['govde'], 'zaten') === false) {
        return ['hata' => 'hakemlige acilamadi: ' . $ac['govde']];
    }
    return $c;
}
/* Yazar kimliğiyle gönderilen gövdeye erişim alanlarını ekler. */
function yz(array $c, array $ek = []): array {
    return array_merge(['t' => $c['token'], 'mail' => $c['eposta'], 'sifre' => $c['sifre']], $ek);
}

/* =====================================================================  */
echo "== 1. Calisma gonderimi ve editorun hakem atamasi ==\n";

/* Yönetici parolası artık koda gömülü değil ve eski sızmış parola kara
   listede. Sınama kendi parolasını KUTADGU_ILK_PAROLA ile kurar: veri
   dizininde auth.json yoksa ilk giriş denemesi kimliği oluşturur. Böylece
   betik hiçbir gerçek parolayı taşımaz ve her ortamda kendi kurulumunu
   yapar. Var olan bir auth.json'a dokunulmaz; o durumda kurulum atlanır
   ve giriş, sınamanın kendi parolasıyla başarısız olur, bu yüzden veri
   dizini kopyasında dosya varsa önce kaldırılır. */
$SINAMA_PAROLA = 'sinama-yonetici-parolasi-2026';
$AUTH_YOL = rtrim((string)getenv('KUTADGU_DATA'), '/') . '/auth.json';
$AUTH_VARDI = is_file($AUTH_YOL) ? file_get_contents($AUTH_YOL) : null;
/* Ortam değişkeni burada işe yaramaz: onu okuyan taraf sunucu süreci,
   bu betik değil. Bu yüzden kimlik dosyası doğrudan yazılır. Betiğin
   veri dizini zaten kendi kopyasıdır. */
file_put_contents($AUTH_YOL, json_encode(
    ['kullanici' => 'yonetici', 'hash' => password_hash($SINAMA_PAROLA, PASSWORD_DEFAULT)],
    JSON_UNESCAPED_UNICODE));
register_shutdown_function(function () use ($AUTH_YOL, $AUTH_VARDI) {
    if ($AUTH_VARDI === null) @unlink($AUTH_YOL);
    else file_put_contents($AUTH_YOL, $AUTH_VARDI);
});
/* GÖNDERİM ARTIK HESAP İSTER (kurul kararı, 15 Ağustos 2026): kaydın
   bir sahibi olmalı ki yazar geri dönüp düzeltebilsin.

   İKİ OTURUM AYRI TUTULUR, BİRLEŞTİRİLMEZ. İlk yazımda gönderen hesabı
   yönetim oturumunun ÜSTÜNE kuruldu ve ölçüm bozuldu: bu kapının 5-7.
   bölümleri gönüllü hakemliği FORM KİMLİĞİYLE ölçer, ama açık bir hesap
   varsa uç kimliği hesaptan okur (doğru davranışı; 7b bunu ayrıca
   ölçüyor). Yani kapı, kendi kurduğu oturum yüzünden başka bir yolu
   ölçmeye başlamıştı. Ölçüm yanlış çıktığında önce ölçümden şüphelen.

   Şimdi gönderen hesabının çerezi ayrı bir değişkende durur ve YALNIZCA
   başvuru gönderilirken kullanılır (calisma_ac içinde). */
$KEREZ = '';
$hesapKayit = ist('/hesap/kayit', [
    'eposta' => 'gonderen-kapi@ornek-sinama.org', 'parola' => 'sinama-hesap-parolasi-2026',
    'ad' => 'Dr. Kapi Gonderen', 'unvan' => 'Dr.', 'orcid' => '0000-0002-1825-0097']);
$sc = bas($hesapKayit['basliklar'], 'Set-Cookie');
if ($sc !== '') $GONDEREN_KEREZ = explode(';', $sc)[0]; else $GONDEREN_KEREZ = '';
if ($GONDEREN_KEREZ === '') {
    $hg = ist('/hesap/giris', ['kim' => 'gonderen-kapi@ornek-sinama.org', 'parola' => 'sinama-hesap-parolasi-2026']);
    $sc = bas($hg['basliklar'], 'Set-Cookie');
    if ($sc !== '') $GONDEREN_KEREZ = explode(';', $sc)[0];
}
den('gonderen hesabi acildi (ayri oturum)', $GONDEREN_KEREZ !== '', $hesapKayit['govde']);

$KEREZ = '';
$giris = ist('/login', ['kullanici' => 'yonetici', 'parola' => $SINAMA_PAROLA]);
$sc = bas($giris['basliklar'], 'Set-Cookie');
if ($sc !== '') $KEREZ = explode(';', $sc)[0];
den('yonetim oturumu acildi', !empty($giris['j']['ok']) && $KEREZ !== '', $giris['govde']);
den('  ve oturum tasiniyor', !empty(ist('/durum', null, 'GET')['j']['girisli']));
den('  yonetim oturumunda hesap kimligi YOK (ayrik)',
    empty(ist('/hesap/durum', null, 'GET')['j']['girisli']));

$W1 = calisma_ac('A', 'Kapi Olcumu Calismasi Bir');
den('yazar basvurusu kabul edildi ve calisma acildi',
    ($W1['id'] ?? '') !== '' && ($W1['token'] ?? '') !== '', (string)($W1['hata'] ?? ''));
$e1 = veri()[ix(veri(), (string)($W1['id'] ?? ''))] ?? [];
/* Kabulden sonra yol HAKEMSİZDİR; yukarıdaki yardımcı yazar adına
   hakemliği açar, bu yüzden kayıt burada 'hakemli' görünür. Ölçülen
   şey ikisi birden: kuralın kendisi ve açma adımının işlediği. */
den('  yazar hakemliği açınca calisma hakemli yola girdi', (string)($e1['tur'] ?? '') === 'hakemli', (string)($e1['tur'] ?? ''));
den('  ve hakem kadrosu bos basliyor', ($e1['hakemler'] ?? []) === []);

/* Editör ataması: yazarın önerisi değildir, bağımsız sayılır. */
$ata = ist('/editor/hakem-ata', ['id' => $W1['id'], 'ad' => k('B', 0),
    'eposta' => k('B', 2), 'orcid' => k('B', 3)]);
den('editor hakem atadi', !empty($ata['j']['ok']), $ata['govde']);
$B_TOKEN = '';
if (preg_match('#hakem\.php\?t=([a-f0-9]+)#', (string)($ata['j']['hakem_link'] ?? ''), $m)) $B_TOKEN = $m[1];
$B_SIFRE = (string)($ata['j']['sifre'] ?? '');
$B_DAVET = '';
if (preg_match('#davet\.php\?d=([a-f0-9]+)#', (string)($ata['j']['davet_link'] ?? ''), $m)) $B_DAVET = $m[1];
den('  erisim bilgisi editore donuyor', $B_TOKEN !== '' && $B_SIFRE !== '' && $B_DAVET !== '');
$hB = hakem_kaydi((string)$W1['id'], k('B', 0));
den('  kayitta atayan yazar degil', (string)(($hB['atayan']['tur'] ?? '')) !== 'yazar',
    (string)(($hB['atayan']['tur'] ?? '')));
den('  ve hakem bagimsiz sayiliyor', in_array((string)(($hB['atayan']['tur'] ?? '')), ['editor', 'bas_editor', 'yonetim'], true));

/* Erişim bilgisinin gerçekten çalıştığı burada kanıtlanıyor. 4. bölümün
   anlamı buna dayanır: yazara sızsaydı, yazar bu kapıdan girerdi. */
$hf = ist('/hakem-form', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => $B_SIFRE]);
den('hakem kendi baglantisiyla degerlendirme sayfasini aciyor', !empty($hf['j']['ok']), $hf['govde']);
$hy = ist('/hakem-form', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => 'YANLIS-1']);
den('  yanlis sifreyle acamiyor', $hy['kod'] === 403, (string)$hy['kod']);

/* =====================================================================  */
echo "\n== 2. Kurulun gonullu hakemi onaylamasi ==\n";

$gon = ist('/hakem-gonullu', [
    'slug' => $W1['slug'], 'ad' => k('D', 0), 'unvan' => k('D', 1),
    'kurum' => 'Sınama Üniversitesi', 'eposta' => k('D', 2), 'orcid' => k('D', 3),
    'sifat' => ['konu'],
    'yetkinlik' => 'Bu konuda on yıldır çalışıyorum ve doğrudan ilgili yayınlarım var.',
    'belge_tur' => 'edevlet', 'belge_kod' => 'AB12CD34EF',
]);
den('gonullu basvurusu alindi', !empty($gon['j']['ok']), $gon['govde']);
$e1 = veri()[ix(veri(), (string)$W1['id'])] ?? [];
den('  basvuru hemen hakem yapmiyor',
    count(array_filter((array)($e1['hakemler'] ?? []), fn($h) => (string)($h['ad'] ?? '') === k('D', 0))) === 0);
$gkod = '';
foreach (($e1['gonulluler'] ?? []) as $gg) if ((string)($gg['ad'] ?? '') === k('D', 0)) $gkod = (string)($gg['kod'] ?? '');
den('  gonullu kaydi bekliyor durumunda', $gkod !== '');

$ok = ist('/yonetim/gonullu-karar', ['id' => $W1['id'], 'kod' => $gkod, 'karar' => 'kabul', 'belge_onay' => 1]);
den('kurul gonulluyu onayladi ve hakem oldu', !empty($ok['j']['ok']), $ok['govde']);
$D_TOKEN = (string)($ok['j']['token'] ?? '');
$D_SIFRE = (string)($ok['j']['sifre'] ?? '');
$hD = hakem_kaydi((string)$W1['id'], k('D', 0));
den('  atayan turu gonullu olarak yazildi', (string)(($hD['atayan']['tur'] ?? '')) === 'gonullu',
    (string)(($hD['atayan']['tur'] ?? '')));
den('  ve kayitta atayan yazar degil', (string)(($hD['atayan']['tur'] ?? '')) !== 'yazar');
den('  gonullu hakem kendi baglantisiyla giriyor',
    !empty(ist('/hakem-form', ['t' => $D_TOKEN, 'mail' => k('D', 2), 'sifre' => $D_SIFRE])['j']['ok']));

/* =====================================================================  */
echo "\n== 3. Yazar hakem ONERIR, davet etmez ==\n";

$on = ist('/yazar-hakem-davet', yz($W1, [
    'ad' => k('C', 0), 'eposta' => k('C', 2), 'orcid' => k('C', 3),
    'kurum' => 'Sınama Üniversitesi', 'gerekce' => 'Konunun yöntem tarafını en iyi bilen kişi.',
]));
den('yazarin onerisi alindi', !empty($on['j']['ok']), $on['govde']);
den('  yanit oneri diyor, davet demiyor',
    isset($on['j']['oneri']) && (string)($on['j']['oneri']['durum'] ?? '') === 'bekliyor');
/* Eski davranışın izi: uç davet bağlantısı döndürmemeli. Bir davet
   bağlantısı dönüyorsa yazar daveti yine kendisi gönderiyor demektir. */
den('  davet baglantisi DONMUYOR',
    strpos($on['govde'], 'davet.php') === false && !isset($on['j']['davet_link']) && !isset($on['j']['link']),
    $on['govde']);
$e1 = veri()[ix(veri(), (string)$W1['id'])] ?? [];
den('  oneri hakem kaydi olusturmadi',
    count(array_filter((array)($e1['hakemler'] ?? []), fn($h) => (string)($h['ad'] ?? '') === k('C', 0))) === 0);
$okod = (string)($on['j']['oneri']['kod'] ?? '');
den('  oneri ayri bir listede bekliyor',
    count(array_filter((array)($e1['hakem_onerileri'] ?? []), fn($o) => (string)($o['kod'] ?? '') === $okod)) === 1);
$tekrar = ist('/yazar-hakem-davet', yz($W1, ['ad' => k('C', 0), 'eposta' => k('C', 2), 'orcid' => k('C', 3)]));
den('  ayni kisi ikinci kez onerilemiyor', $tekrar['kod'] === 409, (string)$tekrar['kod']);

$ok2 = ist('/editor/oneri-karar', ['id' => $W1['id'], 'kod' => $okod, 'karar' => 'kabul']);
den('editor oneriyi onayladi ve daveti kendisi gonderdi', !empty($ok2['j']['ok']), $ok2['govde']);
$hC = hakem_kaydi((string)$W1['id'], k('C', 0));
den('  atayan editor olarak yazildi', (string)(($hC['atayan']['tur'] ?? '')) === 'editor',
    (string)(($hC['atayan']['tur'] ?? '')));
den('  onerenin adi da kayda gecti', trim((string)(($hC['atayan']['oneren'] ?? ''))) !== '');
den('  kaynak yazar onerisi olarak isaretli', (string)(($hC['atayan']['kaynak'] ?? '')) === 'yazar_onerisi');
/* Kritik ayrım: öneriyi editör onayladı diye kayıt "yazarın" olmaz.
   Bu alan 4. bölümdeki kapının ölçütüdür. */
den('  ama atayan turu YAZAR degil', (string)(($hC['atayan']['tur'] ?? '')) !== 'yazar');

/* =====================================================================  */
echo "\n== 4. SIFRE SIZINTISI KAPISI ==\n";
/* Kapanan açık şuydu: /yazar-form bütün hakemlerin giriş bağlantısını
   ve şifresini yazara veriyordu. Yazar o bağlantıyla hakemin
   değerlendirme sayfasını açıp raporu yazılmadan görebiliyordu.

   Kapı iki yönlü ölçülür:
     - GORUNMELI : yazarın kendi önerdiği (eski biçim: atayan.tur='yazar')
                   hakemin erişim bilgisi. Daveti yazar ilettiği için bu
                   bilerek açıktır.
     - GORUNMEMELI: editörün atadığı ve kurulun onayladığı hakemin
                   şifresi ve giriş bağlantısı.

   atayan.tur='yazar' olan kayıt bugün hiçbir uçtan üretilmiyor (yazar
   artık öneri yazıyor, davet etmiyor). Bu yüzden o kayıt ESKİ BİÇİMİYLE
   dosyaya yerleştiriliyor: açığın kapatıldığı ölçüt tam olarak bu
   kayıtta sınanır. */
$y = veri(); $i1 = ix($y, (string)$W1['id']);
$L_TOKEN = str_repeat('ab12', 8);
$L_SIFRE = 'ESK-19X';
$y[$i1]['hakemler'][] = [
    'ad' => k('L', 0), 'token' => $L_TOKEN, 'davet_token' => str_repeat('cd34', 8),
    'eposta_hash' => hash('sha256', k('L', 2) . '|hakem'), 'eposta_acik' => k('L', 2),
    'sifre' => $L_SIFRE, 'davet_durum' => 'kabul', 'davet_tarih' => date('c'),
    'karar' => '', 'rapor' => '', 'tarih' => '', 'dosya' => '', 'profil' => [], 'raporlar' => [],
    'atayan' => ['tur' => 'yazar', 'ad' => k('A', 0), 'tarih' => date('c')],
];
veri_yaz($y);

$yf = ist('/yazar-form', yz($W1));
den('yazar formu aciliyor', !empty($yf['j']['ok']), $yf['govde']);
$hak = [];
foreach (($yf['j']['hakemler'] ?? []) as $h) $hak[(string)($h['ad'] ?? '')] = $h;
den('  dort hakem de listede', count($hak) === 4, (string)count($hak));

/* --- GORUNMELI: yazarin kendi onerdigi hakem --- */
$L = $hak[k('L', 0)] ?? [];
den('yazarin kendi onerdigi hakemin baglantisi GORUNUYOR',
    strpos((string)($L['link'] ?? ''), $L_TOKEN) !== false, (string)($L['link'] ?? ''));
den('  ve sifresi GORUNUYOR', (string)($L['sifre'] ?? '') === $L_SIFRE, (string)($L['sifre'] ?? ''));

/* --- GORUNMEMELI: oteki uc yolun hakemleri --- */
$gizli = [
    'editorun atadigi hakem' => [k('B', 0), $B_TOKEN, $B_SIFRE],
    'kurulun onayladigi hakem' => [k('D', 0), $D_TOKEN, $D_SIFRE],
    'yazar onerisini editorun onayladigi hakem' => [k('C', 0), '', ''],
];
foreach ($gizli as $ad => [$kimAd, $tok, $sif]) {
    $h = $hak[$kimAd] ?? [];
    den("$ad: giris baglantisi bos", trim((string)($h['link'] ?? '')) === '', (string)($h['link'] ?? ''));
    den("$ad: sifre bos", trim((string)($h['sifre'] ?? '')) === '', (string)($h['sifre'] ?? ''));
    /* Alanı boşaltmak yetmez: gerçek anahtar yanıtın herhangi bir
       yerinde geçiyorsa sızıntı kapanmamış demektir. */
    if ($tok !== '') den("$ad: token yanitin HICBIR yerinde gecmiyor", strpos($yf['govde'], $tok) === false);
    if ($sif !== '') den("$ad: sifre yanitin HICBIR yerinde gecmiyor", strpos($yf['govde'], $sif) === false);
}
/* Kayıtta duran öteki hakemlerin anahtarları da yanıta düşmemeli. */
$sizan = [];
foreach ((veri()[$i1]['hakemler'] ?? []) as $h) {
    if (!is_array($h) || (string)(($h['atayan']['tur'] ?? '')) === 'yazar') continue;
    foreach (['token', 'davet_token', 'sifre', 'eposta_acik', 'eposta_hash'] as $alan) {
        $v = (string)($h[$alan] ?? '');
        if ($v !== '' && strpos($yf['govde'], $v) !== false) $sizan[] = (string)($h['ad'] ?? '') . '.' . $alan;
    }
}
den('bagimsiz hakemlerin hicbir gizli alani yanitta yok', $sizan === [], implode(', ', $sizan));

/* Sızıntının bedeli somut olarak gösteriliyor: sızan bir anahtar
   hakemin sayfasını gerçekten açar. Kapı bu yüzden var. */
$dene = ist('/hakem-form', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => $B_SIFRE]);
den('sizsaydi ise yarardi: anahtar hakem sayfasini gercekten aciyor', !empty($dene['j']['ok']));
/* Yazarın elindeki tek anahtar kendi erişimidir; onunla hakem sayfası açılmaz. */
$yanlis = ist('/hakem-form', ['t' => $W1['token'], 'mail' => $W1['eposta'], 'sifre' => $W1['sifre']]);
den('yazarin kendi anahtari hakem sayfasini acmiyor', $yanlis['kod'] === 404, (string)$yanlis['kod']);

/* =====================================================================  */
echo "\n== 5. Hakemin rapor yazmasi ve kararin islenmesi ==\n";

$dy = ist('/hakem-davet-yanit', ['d' => $B_DAVET, 'yanit' => 'kabul']);
den('hakem daveti kabul etti', !empty($dy['j']['ok']), $dy['govde']);
den('  kayitta davet durumu kabul', (string)(hakem_kaydi((string)$W1['id'], k('B', 0))['davet_durum'] ?? '') === 'kabul');

$kisa = ist('/hakem-gonder', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => $B_SIFRE,
    'karar' => 'kucuk', 'rapor' => 'kısa']);
den('cok kisa rapor kabul edilmiyor', $kisa['kod'] === 400, (string)$kisa['kod']);
$kararsiz = ist('/hakem-gonder', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => $B_SIFRE,
    'karar' => '', 'rapor' => str_repeat('Yöntem bölümü yeterince ayrıntılı değil. ', 12)]);
den('karar secilmeden rapor gonderilemiyor', $kararsiz['kod'] === 400, (string)$kararsiz['kod']);

$rapor = 'Çalışmanın kuramsal çerçevesi yerinde kurulmuş, örneklem yeterli. '
       . 'Yöntem bölümünde ölçüm aracının geçerlik katsayıları verilmemiş; eklenmelidir. '
       . 'Bulgular tablolarla desteklenmiş ancak tartışma bölümü alanyazınla yeterince konuşmuyor. '
       . 'Kaynakça biçimi tutarlı. Küçük düzeltmelerle yayımlanabilir.';
$gr = ist('/hakem-gonder', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => $B_SIFRE,
    'karar' => 'kucuk', 'rapor' => $rapor]);
den('hakem raporunu ve kararini gonderdi', !empty($gr['j']['ok']), $gr['govde']);
den('  raporun sayima katilip katilmadigi hakeme bildiriliyor', array_key_exists('nitelik', $gr['j']));
$hB2 = hakem_kaydi((string)$W1['id'], k('B', 0));
den('  karar kayda islendi', (string)($hB2['karar'] ?? '') === 'kucuk', (string)($hB2['karar'] ?? ''));
den('  rapor surum olarak saklandi', count((array)($hB2['raporlar'] ?? [])) === 1);
/* ---- BU İKİ ÖLÇÜM TERSİNE ÇEVRİLDİ VE SEBEBİ KAYDA GEÇİYOR ----
   Eskiden burada "küçük revizyondan sonra değerlendirme KAPANDI" ve
   "kapanmış değerlendirme yeniden yazılamıyor" ölçülüyordu. Bu kapı o
   ölçümlerle yeşildi.

   Aynı anda diyalog-kapi'de şu ölçüm KIRMIZI duruyordu:
   "KUCUK REVIZYONDA KANAL ACIK KALIYOR". İki kapı, tek bir alana
   ('kapali') bakarak birbirinin tersini söylüyordu; biri yeşil olduğu
   için kimse ötekine bakmadı.

   Karar kuralın kendisinden okundu: tg_diyalog_sira() — kanalın açık
   olup olmadığına karar veren TEK KAYNAK — küçük ve büyük revizyonda
   kanalı açık sayar. Küçük revizyon bir SON değil, yazara yöneltilmiş
   bir istektir; yazarın yanıt veremediği bir revizyon isteği istek
   değil hükümdür. Ayrıca büyük revizyondan sonra hakemin geri gelip
   kararını kesinleştirebildiği zaten ölçülüyordu: "az düzeltme isteyen
   hakem geri dönemez, çok düzeltme isteyen döner" tutarsızlıktı.

   Süreci bitiren iki karar kaldı: kabul ve ret. */
den('  degerlendirme KAPANMADI: kucuk revizyon bir tur acar', empty($hB2['kapali']),
    'kapali=' . var_export(!empty($hB2['kapali']), true));
$tekrarG = ist('/hakem-gonder', ['t' => $B_TOKEN, 'mail' => k('B', 2), 'sifre' => $B_SIFRE,
    'karar' => 'kucuk', 'rapor' => $rapor]);
den('  hakem revizyon turunda kararini yenileyebiliyor', $tekrarG['kod'] === 200, (string)$tekrarG['kod']);
/* KABUL/RET İLE KAPANMA BURADA DENENMEZ ve bunun sebebi yazılıyor:
   bu betik tek bir çalışma üzerinde uçtan uca bir AKIŞ yürütüyor.
   Buraya bir 'kabul' kararı koyduğumda çalışmanın kilidi revizyondan
   çıktı ve aşağıdaki dört ölçüm birden düştü — ölçümün kendisi akışı
   bozdu. Kapanma, kendi çalışması üzerinde diyalog-kapi ve ret-kapi
   ile ölçülüyor. */

$yf2 = ist('/yazar-form', yz($W1));
$hakB = null;
foreach (($yf2['j']['hakemler'] ?? []) as $h) if ((string)($h['ad'] ?? '') === k('B', 0)) $hakB = $h;
den('yazar karari ve raporu goruyor',
    is_array($hakB) && (string)$hakB['karar'] === 'kucuk' && strpos((string)$hakB['rapor'], 'geçerlik katsayıları') !== false);
den('  metin kilidi revizyona acildi', (string)($yf2['j']['kilit'] ?? '') === 'revizyon', (string)($yf2['j']['kilit'] ?? ''));
den('  rapor geldi ama sifre hala gorunmuyor', trim((string)($hakB['sifre'] ?? '')) === '' && trim((string)($hakB['link'] ?? '')) === '');

$genel = ist('/yazilar', null, 'GET');
den('rapor kamusal listede acik', strpos($genel['govde'], 'geçerlik katsayıları') !== false);
den('  ama hakem anahtarlari kamusal listede yok',
    strpos($genel['govde'], $B_TOKEN) === false && strpos($genel['govde'], $B_SIFRE) === false);

/* =====================================================================  */
echo "\n== 6. Cakisma yasagi uctan uca uygulaniyor ==\n";
/* W1: yazar A, hakemlerinden biri B. Demek ki B, A'yı değerlendiriyor.
   W2: yazar B. Şimdi A, W2'nin hakemi olmaya çalışırsa karşılıklılık
   kurulur. Aynı yasak üç atama yolunda da işlemeli. */
$W2 = calisma_ac('B', 'Kapi Olcumu Calismasi Iki');
den('ikinci calisma acildi (yazari, birincinin hakemi)', ($W2['id'] ?? '') !== '', (string)($W2['hata'] ?? ''));

$r = ist('/editor/hakem-ata', ['id' => $W2['id'], 'ad' => k('A', 0), 'orcid' => k('A', 3), 'eposta' => k('A', 2)]);
den('editor atamasi: karsilikli hakemlik engellendi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'karsilikli', $r['kod'] . ' ' . $r['govde']);
$r = ist('/editor/hakem-ata', ['id' => $W2['id'], 'ad' => k('B', 0), 'orcid' => k('B', 3), 'eposta' => k('B', 2)]);
den('editor atamasi: yazarin kendisi hakem yapilamadi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'kendisi', $r['kod'] . ' ' . $r['govde']);

$r = ist('/hakem-gonullu', ['slug' => $W2['slug'], 'ad' => k('A', 0), 'unvan' => k('A', 1),
    'kurum' => 'Sınama Üniversitesi', 'eposta' => k('A', 2), 'orcid' => k('A', 3), 'sifat' => ['konu'],
    'yetkinlik' => 'Bu konuda uzun süredir çalışıyorum ve ilgili yayınlarım var.',
    'belge_tur' => 'edevlet', 'belge_kod' => 'ZZ99YY88']);
den('gonullu yolu: karsilikli hakemlik engellendi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'karsilikli', $r['kod'] . ' ' . $r['govde']);

$r = ist('/yazar-hakem-davet', yz($W2, ['ad' => k('A', 0), 'eposta' => k('A', 2), 'orcid' => k('A', 3),
    'gerekce' => 'Alanı iyi bilir.']));
den('yazar onerisi yolu: karsilikli hakemlik engellendi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'karsilikli', $r['kod'] . ' ' . $r['govde']);

/* Onay ANINDA yeniden denetim. Öneri ve karar arasında dünya değişir:
   temizken önerilen bir ad, karar anında çakışıyor olabilir. Bu iki
   ölçüm, denetimin yalnızca giriş kapısına konulmadığını gösterir. */
$r = ist('/yazar-hakem-davet', yz($W2, ['ad' => k('D', 0), 'eposta' => k('D', 2), 'orcid' => k('D', 3),
    'gerekce' => 'Yöntem tarafını bilir.']));
den('temiz aday onerilebiliyor (yanlis pozitif yok)', !empty($r['j']['ok']), $r['govde']);
$okod2 = (string)($r['j']['oneri']['kod'] ?? '');
$W3 = calisma_ac('D', 'Kapi Olcumu Calismasi Uc');
den('ucuncu calisma acildi (yazari, bekleyen onerinin adayi)', ($W3['id'] ?? '') !== '', (string)($W3['hata'] ?? ''));
$r = ist('/editor/hakem-ata', ['id' => $W3['id'], 'ad' => k('B', 0), 'orcid' => k('B', 3), 'eposta' => k('B', 2)]);
den('  W2 yazari, W3 e hakem atandi (cakisma yok)', !empty($r['j']['ok']), $r['govde']);
$r = ist('/editor/oneri-karar', ['id' => $W2['id'], 'kod' => $okod2, 'karar' => 'kabul']);
den('oneri karari: sonradan olusan cakisma onay aninda yakalandi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'karsilikli', $r['kod'] . ' ' . $r['govde']);

$r = ist('/hakem-gonullu', ['slug' => $W1['slug'], 'ad' => k('E', 0), 'unvan' => k('E', 1),
    'kurum' => 'Sınama Üniversitesi', 'eposta' => k('E', 2), 'orcid' => k('E', 3), 'sifat' => ['yontem'],
    'yetkinlik' => 'Yöntem ve istatistik tarafında yeterliyim, ilgili yayınlarım var.',
    'belge_tur' => 'edevlet', 'belge_kod' => 'QQ11WW22']);
den('temiz gonullu basvurusu alinabiliyor', !empty($r['j']['ok']), $r['govde']);
$gkod2 = '';
foreach ((veri()[ix(veri(), (string)$W1['id'])]['gonulluler'] ?? []) as $gg) {
    if ((string)($gg['ad'] ?? '') === k('E', 0)) $gkod2 = (string)($gg['kod'] ?? '');
}
$W4 = calisma_ac('E', 'Kapi Olcumu Calismasi Dort');
den('dorduncu calisma acildi (yazari, bekleyen gonullu)', ($W4['id'] ?? '') !== '', (string)($W4['hata'] ?? ''));
$r = ist('/editor/hakem-ata', ['id' => $W4['id'], 'ad' => k('A', 0), 'orcid' => k('A', 3), 'eposta' => k('A', 2)]);
den('  W1 yazari, W4 e hakem atandi (cakisma yok)', !empty($r['j']['ok']), $r['govde']);
$r = ist('/yonetim/gonullu-karar', ['id' => $W1['id'], 'kod' => $gkod2, 'karar' => 'kabul', 'belge_onay' => 1]);
den('gonullu karari: sonradan olusan cakisma onay aninda yakalandi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'karsilikli', $r['kod'] . ' ' . $r['govde']);

/* Engellenen aday gerçekten kayda geçmemeli: 409 dönüp yine de
   yazmak, kuralı yalnızca ekranda uygulamak olurdu. */
$e2 = veri()[ix(veri(), (string)$W2['id'])] ?? [];
$adlar = array_map(fn($h) => (string)($h['ad'] ?? ''), (array)($e2['hakemler'] ?? []));
den('engellenen aday hakem listesine yazilmadi', !in_array(k('A', 0), $adlar, true), implode(', ', $adlar));
$bekleyen = 0;
foreach (($e2['hakem_onerileri'] ?? []) as $o) if ((string)($o['ad'] ?? '') === k('A', 0)) $bekleyen++;
den('  engellenen aday oneri listesine de yazilmadi', $bekleyen === 0);

/* =====================================================================
   7. KENDI CALISMASINA GONULLU OLMA: ADRES VE OTURUM
   ---------------------------------------------------------------------
   Ölçülen açık (11 Ağustos 2026, canlıda): /hakem-gonullu yalnızca ad ve
   ORCID'e bakıyordu. Yazar adını değiştirip ikinci bir ORCID yazınca
   kendi çalışmasına gönüllü olarak kaydediliyordu; başvuruyu ise kendi
   adresiyle yapıyordu, çünkü onay yazısı oraya gelecek.

   Bu bölüm iki şeyi uçtan uca ölçer:
     a) adres, çakışma denetiminin ölçütü mü,
     b) oturum varsa kimlik formdaki metinden mi yoksa hesaptan mı
        okunuyor.

   POLİTİKA DEĞİŞMEDİ VE DEĞİŞMEMELİ: gönüllülük oturum istemez. Bu da
   ayrıca ölçülüyor (aşağıdaki son iki ölçüm), çünkü açığı kapatırken
   kapıyı kapatmak en kolay ve en yanlış çözüm olurdu.
   ===================================================================== */
echo "\n== 7. Kendi calismasina gonullu olma: adres ve oturum ==\n";

$W5 = calisma_ac('L', 'Kapi Olcumu Calismasi Bes');
den('besinci calisma acildi (yazari L)', ($W5['id'] ?? '') !== '', (string)($W5['hata'] ?? ''));

/* Geçerli ama kimseye ait olmayan ORCID'ler. Denetim hanesi ISO 7064
   MOD 11-2 ile üretildi; uç geçersiz kimliği baştan reddediyor. */
const O_SAHTE1 = '0000-0002-0000-0014';
const O_SAHTE2 = '0000-0003-0000-002X';

$gonullu = fn(array $ek) => ist('/hakem-gonullu', array_merge([
    'slug' => $W5['slug'], 'unvan' => 'Prof. Dr.', 'kurum' => 'Sınama Üniversitesi',
    'sifat' => ['konu'],
    'yetkinlik' => 'Bu konuda uzun süredir çalışıyorum ve doğrudan ilgili yayınlarım var.',
    'belge_tur' => 'edevlet', 'belge_kod' => 'MM33NN44'], $ek));

/* ---- 7a. GIRISSIZ: yazarin adresi, degistirilmis ad, baska ORCID ---- */
$r = $gonullu(['ad' => k('L', 0), 'eposta' => k('L', 2), 'orcid' => k('L', 3)]);
den('yazar kendi adiyla gonullu olamiyor (eski davranis)',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'kendisi', $r['kod'] . ' ' . $r['govde']);

/* TAM O SENARYO: ad değiştirilmiş, ORCID başka, adres yazarın kendi
   adresi. Bugüne kadar bu deneme GEÇİYORDU ve kayıt düşüyordu. */
$r = $gonullu(['ad' => 'Leyla A. Arslanoglu', 'eposta' => k('L', 2), 'orcid' => O_SAHTE1]);
den('YAZARIN ADRESI + DEGISTIRILMIS AD + BASKA ORCID reddedildi',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'kendisi', $r['kod'] . ' ' . $r['govde']);
$e5 = veri()[ix(veri(), (string)$W5['id'])] ?? [];
den('  ve kayit DUSMEDI', count((array)($e5['gonulluler'] ?? [])) === 0,
    json_encode($e5['gonulluler'] ?? [], JSON_UNESCAPED_UNICODE));

/* ---- 7b. GIRISLI OTURUM: kimlik formdan degil hesaptan okunuyor ---- */
/* Yönetim çerezi kenara alınır: hesap girişi ayrı bir oturumda yapılır,
   yoksa hesap yönetim oturumunun üstüne biner. */
$YONETIM_KEREZ = $KEREZ;
$KEREZ = '';
$kayit = ist('/hesap/kayit', ['eposta' => k('L', 2), 'parola' => 'sinama-hesap-parolasi-2026',
    'ad' => k('L', 0), 'unvan' => 'Dr.', 'orcid' => k('L', 3)]);
$sc = bas($kayit['basliklar'], 'Set-Cookie');
if ($sc !== '') $KEREZ = explode(';', $sc)[0];
if ($KEREZ === '') {
    $gir = ist('/hesap/giris', ['kim' => k('L', 2), 'parola' => 'sinama-hesap-parolasi-2026']);
    $sc = bas($gir['basliklar'], 'Set-Cookie');
    if ($sc !== '') $KEREZ = explode(';', $sc)[0];
}
den('yazarin hesabi acildi ve oturum kuruldu', $KEREZ !== '', $kayit['govde']);
den('  oturum tasiniyor', !empty(ist('/hesap/durum', null, 'GET')['j']['girisli']));

/* Formda başka bir ad, başka bir adres, başka bir ORCID. Oturum varsa
   bunların hiçbiri sayılmaz; kimlik hesabın kendisidir. */
$r = $gonullu(['ad' => 'Bambaska Biri', 'eposta' => 'bambaska@ornek-sinama.org',
               'orcid' => O_SAHTE2]);
den('GIRISLI OTURUMLA kendi calismasina gonullu olamiyor',
    $r['kod'] === 409 && (string)($r['j']['cakisma'] ?? '') === 'kendisi', $r['kod'] . ' ' . $r['govde']);
den('  hata iki dilde de anlasilir',
    mb_stripos((string)($r['j']['hata'] ?? ''), 'kendi çalışmanız') !== false
    && mb_stripos((string)($r['j']['hata'] ?? ''), 'your own work') !== false,
    (string)($r['j']['hata'] ?? ''));
$e5 = veri()[ix(veri(), (string)$W5['id'])] ?? [];
den('  ve yine kayit DUSMEDI', count((array)($e5['gonulluler'] ?? [])) === 0,
    json_encode($e5['gonulluler'] ?? [], JSON_UNESCAPED_UNICODE));

/* Başka bir çalışmaya girişli başvuru: kayda hesabın bilgisi yazılmalı,
   formdaki metin değil. Kural yalnızca engelde değil, kabulde de
   geçerlidir; yoksa kişi başka bir adla kaydedilir ve sonraki denetim
   onu tanımaz. W3'ün yazarı D; L'nin onunla hiçbir ilişkisi yok. */
$r = ist('/hakem-gonullu', ['slug' => $W3['slug'], 'ad' => 'Bambaska Biri',
    'unvan' => 'Prof. Dr.', 'kurum' => 'Sınama Üniversitesi',
    'eposta' => 'bambaska@ornek-sinama.org', 'orcid' => O_SAHTE2, 'sifat' => ['konu'],
    'yetkinlik' => 'Bu konuda uzun süredir çalışıyorum ve doğrudan ilgili yayınlarım var.',
    'belge_tur' => 'edevlet', 'belge_kod' => 'PP55RR66']);
den('girisli kisi baska bir calismaya gonullu olabiliyor', !empty($r['j']['ok']), $r['govde']);
$e3 = veri()[ix(veri(), (string)$W3['id'])] ?? [];
$son = null;
foreach ((array)($e3['gonulluler'] ?? []) as $gg) $son = $gg;
den('  kayda HESABIN adi yazildi, formdaki degil',
    is_array($son) && (string)($son['ad'] ?? '') === k('L', 0), json_encode($son, JSON_UNESCAPED_UNICODE));
den('  kayda HESABIN adresi yazildi, formdaki degil',
    is_array($son) && (string)($son['eposta'] ?? '') === k('L', 2), json_encode($son, JSON_UNESCAPED_UNICODE));
den('  kayda HESABIN ORCID i yazildi, formdaki degil',
    is_array($son) && (string)($son['orcid'] ?? '') === k('L', 3), json_encode($son, JSON_UNESCAPED_UNICODE));
den('  kimligin oturumdan geldigi kayda gecti',
    is_array($son) && (string)($son['kimlik'] ?? '') === 'oturum', json_encode($son, JSON_UNESCAPED_UNICODE));

/* ---- 7c. POLITIKA YERINDE: girissiz gonulluluk ACIK kaldi ---- */
ist('/hesap/cikis', []);
$KEREZ = '';
den('cikis yapildi', empty(ist('/hesap/durum', null, 'GET')['j']['girisli']));
$r = $gonullu(['ad' => 'Selim Korkmaz', 'eposta' => 'selim.korkmaz@ornek-sinama.org',
               'orcid' => O_SAHTE1]);
den('GIRISSIZ temiz aday hala gonullu olabiliyor (politika degismedi)',
    !empty($r['j']['ok']), $r['kod'] . ' ' . $r['govde']);
$e5 = veri()[ix(veri(), (string)$W5['id'])] ?? [];
$var = 0;
foreach ((array)($e5['gonulluler'] ?? []) as $gg) if ((string)($gg['ad'] ?? '') === 'Selim Korkmaz') $var++;
den('  ve kaydi dustu', $var === 1, json_encode($e5['gonulluler'] ?? [], JSON_UNESCAPED_UNICODE));
$gAnon = null;
foreach ((array)($e5['gonulluler'] ?? []) as $gg) {
    if (is_array($gg) && (string)($gg['ad'] ?? '') === 'Selim Korkmaz') $gAnon = $gg;
}
den('  kimligin formdan geldigi kayda gecti',
    is_array($gAnon) && (string)($gAnon['kimlik'] ?? '') === 'form',
    json_encode($gAnon, JSON_UNESCAPED_UNICODE));
$KEREZ = $YONETIM_KEREZ;

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
