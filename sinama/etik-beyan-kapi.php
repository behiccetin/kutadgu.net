<?php
/* =====================================================================
   ETİK KURUL BEYANI · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 18 Ağustos 2026. Üç kusur bir aradaydı:

   1) ARŞİV KAYDI SUSUYORDU. Beyan 15 Ağustos 2026'da istenmeye
      başlandı; ondan önceki çalışmalarda 'etik' alanı yok ve yazi.php
      satırı ancak durum boş DEĞİLSE basılıyordu. Okur "gerekmiyor" ile
      "sorulmadı"yı ayırt edemiyordu.

   2) SUÇLAMAK DA ÇÖZÜM DEĞİLDİ. Boş alanı kırmızı "Beyan edilmedi"
      yapmak, kendisinden hiç istenmemiş bir şey için yazarı suçlamaktı.

   3) KAPI YOKTU. /yazar-kaydet 'etik' alanını hiç okumuyordu; oysa
      çalışma sayfası "Yazar bunları bildirdiğinde bu uyarı kalkar"
      diyordu. Sistem, çözümünü sunmadığı bir eksiği duyuruyordu.

   Bu kapı üçünü de ölçer ve GERİ ALINAMAZLIK sınırını da sınar:
   verilmiş bir beyan silinemez, yalnız düzeltilebilir.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php etik-beyan-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

$api  = (string)@file_get_contents($KOD . '/api/index.php');
$yzP  = (string)@file_get_contents($KOD . '/yazar.php');
$yziP = (string)@file_get_contents($KOD . '/yazi.php');
den('kaynaklar okunabildi', $api !== '' && $yzP !== '' && $yziP !== '');

/* ------------------------------------------------------------------ */
echo "\n== 1. Tek kaynak: hâl ortak.php'de tanımlı ==\n";
den('tg_etik_hal() var', function_exists('tg_etik_hal'));
den('tg_etik_hal_ad() var', function_exists('tg_etik_hal_ad'));
den('tg_etik_baslangic() var', function_exists('tg_etik_baslangic'));
$bas = tg_etik_baslangic();
olc('yürürlük tarihi: ' . ($bas === '' ? '(yok)' : $bas));
den('  tarih ayar.php\'den okunuyor ve biçimi doğru',
    (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $bas), $bas);
/* Sayfa kendi kuralını yazmamalı: hâl kararı yazi.php'de tekrar
   edilirse biri ötekinden habersiz değişir. */
den('  yazi.php hâli kendisi hesaplamıyor', str_contains($yziP, 'tg_etik_hal($yazi)'));
den('  rengi de tek kaynaktan alıyor', str_contains($yziP, 'tg_etik_hal_renk('));

/* ------------------------------------------------------------------ */
echo "\n== 2. Beş hâl birbirinden ayrılıyor ==\n";
$oncesi = ($bas !== '') ? date('Y-m-d', strtotime($bas . ' -1 day')) : '2020-01-01';
$sonrasi = ($bas !== '') ? date('Y-m-d', strtotime($bas . ' +1 day')) : '2030-01-01';
$olay = [
    ['arşiv kaydı (kuraldan önce, alan yok)',      ['tarih' => $oncesi],                                     'sorulmadi'],
    ['kuraldan sonra doğmuş ama beyansız kayıt',   ['tarih' => $sonrasi],                                    'eksik'],
    ['gerekmiyor beyanı',                          ['tarih' => $sonrasi, 'etik' => ['durum' => 'gereksiz']],  'gereksiz'],
    ['tam beyan',                                  ['tarih' => $sonrasi, 'etik' => ['durum' => 'gerekli', 'kurul' => 'X Etik Kurulu', 'tarih' => '2026-01-02', 'no' => '3']], 'beyanli'],
    ['gerekli dendi, beyanı yok',                  ['tarih' => $sonrasi, 'etik' => ['durum' => 'gerekli']],   'askida'],
    ['gerekli, numarası eksik (yarım beyan)',      ['tarih' => $sonrasi, 'etik' => ['durum' => 'gerekli', 'kurul' => 'X', 'tarih' => '2026-01-02']], 'askida'],
];
foreach ($olay as [$ad, $kayit, $beklenen]) {
    $h = tg_etik_hal($kayit);
    den($ad . ' -> ' . $beklenen, $h === $beklenen, $h);
}
/* Tarihi okunamayan kayıt SUÇLANMAZ. */
den('tarihi okunamayan kayıt suçlanmıyor (sorulmadi)',
    tg_etik_hal(['tarih' => 'bilinmiyor']) === 'sorulmadi', tg_etik_hal(['tarih' => 'bilinmiyor']));
/* Beş hâlin de bir adı var; adsız hâl ekranda boş kutu demektir. */
foreach (['gereksiz', 'beyanli', 'askida', 'sorulmadi', 'eksik'] as $h) {
    den('  "' . $h . '" hâlinin adı var (tr+en)',
        tg_etik_hal_ad($h, false) !== '' && tg_etik_hal_ad($h, true) !== '');
}
/* Renk: yalnız gerçek eksik kırmızıdır. Arşiv kaydını kırmızı yapmak
   suçlamaktır; beyanı kırmızı yapmak yanlış bilgi vermektir. */
den('arşiv kaydı KIRMIZI DEĞİL', tg_etik_hal_renk('sorulmadi') !== 'var(--kirmizi)');
den('  askıdaki kayıt kırmızı', tg_etik_hal_renk('askida') === 'var(--kirmizi)');
den('  beyanlı kayıt yeşil', tg_etik_hal_renk('beyanli') === 'var(--yesil)');

/* ------------------------------------------------------------------ */
echo "\n== 3. Çalışma sayfası: satır HER ZAMAN basılıyor ==\n";
$yz = oku_json_kapi($VERI . '/yazilar.json');
$hedef = null;
foreach ($yz as $e) { if (is_array($e) && ($e['slug'] ?? '') !== '') { $hedef = $e; break; } }
den('ölçülecek arşiv çalışması bulundu', $hedef !== null);
if ($hedef) {
    $adres = 'http://127.0.0.1:' . $PORT . '/tamga/' . rawurlencode((string)($hedef['bcid'] ?? $hedef['slug'])) . '?lang=tr';
    $s = (string)@file_get_contents($adres);
    if ($s === '') { $s = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/yazi.php?y=' . rawurlencode((string)$hedef['slug']) . '&lang=tr'); }
    den('sayfa açıldı', $s !== '', $adres);
    $hal = tg_etik_hal($hedef);
    olc('bu çalışmanın hâli: ' . $hal);
    den('  "Etik kurul" satırı sayfada VAR', str_contains($s, 'Etik kurul</span>'));
    den('  ve hâlin adını yazıyor', str_contains($s, tg_etik_hal_ad($hal, false)), $hal);
    if ($hal === 'sorulmadi') {
        den('  arşiv notu da basılı (suçlamayan cümle)', str_contains($s, 'ys-sat ys-not'));
        den('    not, kuralın başlangıç tarihini söylüyor', str_contains($s, $bas));
        den('    ve kapının açık olduğunu söylüyor',
            str_contains($s, 'bugün de ekleyebilir'));
        den('  sayfa arşiv kaydını SUÇLAMIYOR', !str_contains($s, 'Beyan edilmedi'));
    }
}

/* ------------------------------------------------------------------ */
echo "\n== 4. Kapı gerçekten var: uç 'etik' alanını okuyor ==\n";
/* PENCERE SABİT UZUNLUK DEĞİL, BİR SONRAKİ UCA KADARDIR.
   İlk yazımda 12000 karakterlik sabit bir pencere alınıyordu. Uca yeni
   bir denetim eklendiği gün (künye kuralı, 19 Ağustos) etik bloğu
   pencerenin dışına taştı ve kapı "uç 'etik' alanını okumuyor" dedi —
   oysa okuyordu. Sabit sayı, ölçtüğü şey büyüdüğünde sessizce yanlış
   ölçer. Ölçüm yanlış çıktığında önce ölçümden şüphelen. */
$b = strpos($api, "\$yol === '/yazar-kaydet'");
$sonU = $b !== false ? strpos($api, "\$yol === '/yazar-hakemlige-ac'", $b) : false;
if ($b !== false && $sonU === false) $sonU = strlen($api);
$blok = $b !== false ? substr($api, $b, $sonU - $b) : '';
den('/yazar-kaydet bölümü bulundu', $blok !== '');
den('  uç \'etik\' alanını okuyor', str_contains($blok, "array_key_exists('etik', \$g)"));
/* KİLİTTEN BAĞIMSIZ: beyan bulgu değildir. 'etik' içerik listesinde
   olsaydı, kilitli çalışmada eksik kalıcı olurdu. */
$ic = [];
if (preg_match("#foreach \(\['baslik'.*?\] as \\\$ak\)#s", $blok, $mm)) $ic[] = $mm[0];
den('  \'etik\' METİN KİLİDİ listesinde DEĞİL',
    $ic && !str_contains($ic[0], "'etik'"), $ic ? 'liste bulundu' : 'liste bulunamadı');
den('  /yazar-form beyanı geri de veriyor', str_contains($api, "'hal'   => tg_etik_hal(\$e)"));
den('yazar sayfasında beyan bölümü var', str_contains($yzP, 'id="etikKart"'));
den('  alanlar betiksiz tarayıcıda AÇIK doğuyor',
    str_contains($yzP, '<div id="etikAlan">') && !str_contains($yzP, '<div id="etikAlan" hidden'));
den('  gizleme betikle yapılıyor', str_contains($yzP, "\$('etikAlan').hidden"));

/* ------------------------------------------------------------------ */
echo "\n== 5. Uçtan uca: beyan sonradan eklenebiliyor ==\n";
/* Ölçüm kendi kaydını kurar: hazır bir kayda dokunmak, başka kapıların
   ölçtüğü veriyi bozar ve sonraki koşumda "kod bozuldu" der. */
$t = bin2hex(random_bytes(16));
$slug = 'etik-olcum-' . substr($t, 0, 8);
$yz2 = oku_json_kapi($VERI . '/yazilar.json');
$yz2[] = [
    'id' => $slug, 'bcid' => 'bc.900001', 'slug' => $slug, 'tur' => 'yazi',
    'tarih' => $oncesi . 'T12:00:00+03:00', 'dil' => 'tr',
    'baslik' => 'Etik beyanı ölçümü', 'ozet' => 'Ölçüm kaydıdır.',
    'yazar' => 'Dr. Ölçüm Yazar',
    'yazar_liste' => [['unvan' => 'Dr.', 'ad' => 'Ölçüm Yazar', 'orcid' => '0000-0002-1825-0097']],
    'yazar_erisim' => ['token' => $t, 'sifre' => 'olcumsifre'],
];
file_put_contents($VERI . '/yazilar.json', json_encode($yz2, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

function ist(string $yol, array $govde): array {
    static $ip = null;
    if ($ip === null) $ip = '203.0.113.' . random_int(1, 254);
    $port = getenv('KPORT') ?: '8941';
    $baglam = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\nX-Forwarded-For: $ip\r\n",
        'content' => json_encode($govde, JSON_UNESCAPED_UNICODE),
        'timeout' => 10, 'ignore_errors' => true,
    ]]);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, $baglam);
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}
$kim = ['t' => $t, 'sifre' => 'olcumsifre'];

$r = ist('/api/yazar-form', $kim);
den('erişim koduyla form açılıyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  beyan hâli formla birlikte geliyor', (($r['etik']['hal'] ?? '') === 'sorulmadi'),
    (string)($r['etik']['hal'] ?? ''));

/* Yarım beyan reddedilmeli: eksik beyan, beyan değildir. */
$r = ist('/api/yazar-kaydet', $kim + ['etik' => ['durum' => 'gerekli', 'kurul' => 'X Kurulu']]);
den('yarım beyan reddediliyor', empty($r['ok']));
den('  gerekçe üç alanı da sayıyor',
    str_contains((string)($r['hata'] ?? ''), 'karar numarası'), (string)($r['hata'] ?? ''));

/* Uydurma durum reddedilmeli. */
$r = ist('/api/yazar-kaydet', $kim + ['etik' => ['durum' => 'belkide']]);
den('tanınmayan durum reddediliyor', empty($r['ok']));

/* Tam beyan kabul edilmeli — kayıt KİLİTLİ olsa da. */
$r = ist('/api/yazar-kaydet', $kim + ['etik' => ['durum' => 'gerekli',
    'kurul' => 'Ölçüm Üniversitesi Etik Kurulu', 'tarih' => '2026-03-04', 'no' => '2026/77']]);
den('tam beyan kaydediliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));

$yz3 = oku_json_kapi($VERI . '/yazilar.json');
$kayit = null;
foreach ($yz3 as $e) if (is_array($e) && ($e['slug'] ?? '') === $slug) $kayit = $e;
den('  kayıt diske yazıldı', $kayit !== null);
if ($kayit) {
    den('  hâl artık "beyanli"', tg_etik_hal($kayit) === 'beyanli', tg_etik_hal($kayit));
    den('  beyan tarihi damgalandı', trim((string)($kayit['etik']['beyan_tarihi'] ?? '')) !== '');
    /* Geç yapılmış beyanı zamanında yapılmış gibi göstermek, beyanı
       değersizleştirir: sayfa "sonradan eklendi" demek zorunda. */
    /* KALICI ADRESTEN OKUNUR. /yazi.php?y=... kalıcı tamga adresine
       301 döner ve o adres CANLI ALAN ADIDIR; file_get_contents
       yönlendirmeyi izleyip kutadgu.net'i ölçerdi. Kapı, ölçtüğünü
       sandığı şeyi ölçmemiş olurdu — bu tam olarak ilk koşumda oldu
       ve iki ölçüm boşuna KALDI verdi. Ölçüm yanlış çıktığında önce
       ölçümden şüphelen. */
    $s2 = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/tamga/' . rawurlencode((string)$kayit['bcid']) . '?lang=tr');
    den('  çalışma sayfası beyanı gösteriyor', str_contains($s2, 'Ölçüm Üniversitesi Etik Kurulu'));
    den('    ve sonradan eklendiğini SÖYLÜYOR', str_contains($s2, 'yayımdan sonra eklendi'));
    den('    arşiv notu artık basılmıyor', !str_contains($s2, 'ys-sat ys-not'));
    $kayitlar = is_array($kayit['kayitlar'] ?? null) ? $kayit['kayitlar'] : [];
    $bulundu = false;
    foreach ($kayitlar as $k) if (($k['tur'] ?? '') === 'etik-beyan') $bulundu = true;
    den('  işlem kayda geçti (etik-beyan)', $bulundu);
}

/* GERİ ALINAMAZLIK: verilmiş beyan silinemez. */
$r = ist('/api/yazar-kaydet', $kim + ['etik' => ['durum' => 'gereksiz']]);
den('verilmiş beyan GERİ ALINAMIYOR', empty($r['ok']));
den('  gerekçe yolu da gösteriyor (editöre düzeltme)',
    str_contains((string)($r['hata'] ?? ''), 'düzeltme'), (string)($r['hata'] ?? ''));
/* Ama DÜZELTİLEBİLİR: yanlış numara sonsuza kadar kalmamalı. */
$r = ist('/api/yazar-kaydet', $kim + ['etik' => ['durum' => 'gerekli',
    'kurul' => 'Ölçüm Üniversitesi Etik Kurulu', 'tarih' => '2026-03-04', 'no' => '2026/78']]);
den('  düzeltmeye ise izin var', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* Ölçüm kaydı bırakılmaz: bıraksaydı her koşumda bir çalışma daha
   birikirdi ve panel-boy kapısı bir gün "panel uzadı" derdi. */
$yz4 = [];
foreach (oku_json_kapi($VERI . '/yazilar.json') as $e) {
    if (is_array($e) && ($e['slug'] ?? '') === $slug) continue;
    $yz4[] = $e;
}
file_put_contents($VERI . '/yazilar.json', json_encode($yz4, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('ölçüm kaydı geri alındı', count($yz4) === count($yz3) - 1);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);

function oku_json_kapi(string $p): array {
    $d = json_decode((string)@file_get_contents($p), true);
    return is_array($d) ? $d : [];
}
