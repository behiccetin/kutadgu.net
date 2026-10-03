<?php
/* =====================================================================
   KÜNYE ZORUNLULUĞU · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi: "isteğe bağlı diyor."

   Doğruydu. Künye (künye dilindeki başlık ve öz) 15 Ağustos 2026'da
   ZORUNLU oldu. Gönderim formu o gün güncellendi; YAZAR PANELİ
   güncellenmedi ve o günden beri kartın başlığında "İngilizce, isteğe
   bağlı" yazıyordu. Bu, bu sistemde en sık yakalanan kusur sınıfının
   YİRMİNCİ örneğidir — bu kez ters yönden: sistem, UYGULADIĞI bir
   kuralı "isteğe bağlı" diye duyuruyordu.

   ARDINDAN İKİNCİ KUSUR ÇIKTI: kural yalnız KAPIDA (/yazar-basvuru)
   uygulanıyordu. Yazar paneli aynı alanları hiç denetlemiyordu; yani
   gönderirken zorunlu olan bir alan, yayımdan sonra tek tıkla
   boşaltılabiliyordu. Kapıda uygulanıp pencerede uygulanmayan kural,
   kural değildir.

   ÜÇÜNCÜ ŞEY BİR TUZAKTI: kuralı olduğu gibi geriye yürütmek. Arşivde
   künye hiç istenmedi; boş olması eksiklik değil, o günün kuralıdır.
   Boş künyeyi şimdi zorunlu kılmak, arşiv yazarını bir yazım yanlışını
   düzeltmekten bile alıkoyardı (etik beyanında da aynı tuzak vardı).
   Uygulanan kural bu yüzden DAR: **dolu bir künye alanı boşaltılamaz.**

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php kunye-kapi.php
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

$yzr = (string)@file_get_contents($KOD . '/yazar.php');
$bsv = (string)@file_get_contents($KOD . '/basvuru.php');
$api = (string)@file_get_contents($KOD . '/api/index.php');
den('kaynaklar okunabildi', $yzr !== '' && $bsv !== '' && $api !== '');

/* ------------------------------------------------------------------ */
echo "\n== 1. Kural tek kaynaktan okunuyor ==\n";
olc('künye dili: ' . tg_kunye_dili() . ' (' . tg_kunye_dil_adi() . ') · zorunlu: '
    . (tg_kunye_zorunlu() ? 'evet' : 'hayır'));
den('yazar paneli zorunluluğu SORUYOR (elle yazmıyor)', str_contains($yzr, 'tg_kunye_zorunlu()'));
den('  dil adını da soruyor', str_contains($yzr, 'tg_kunye_dil_adi()'));
/* "İngilizce" elle yazılmışsa, kurul başka bir dil seçtiği gün kart
   yalan söyler. Künye kartında elle yazılmış dil adı kalmamalı. */
$b1 = strpos($yzr, '$kyKod = tg_kunye_dili()');
$b2 = $b1 !== false ? strpos($yzr, '<fieldset class="cv-kunye">', $b1) : false;
$kart = ($b1 !== false && $b2 !== false) ? substr($yzr, $b1, $b2 - $b1) : '';
den('künye kartı bulundu', $kart !== '', (string)strlen($kart));
/* ÖLÇÜM YORUMLARI SAYMAZ. İlk yazımda kaynak metin olduğu gibi
   taranıyordu ve kapı, KUSURU ANLATAN YORUMUN kendisini kusur sayıyordu
   ("İngilizce elle yazılmıştı" cümlesi). Ölçülen şey OKURA GİDEN
   metindir; bu yüzden önce yorumlar atılır. Ölçüm yanlış çıktığında
   önce ölçümden şüphelen — bu kapıda da doğrulandı. */
$kartTemiz = preg_replace('#/\*.*?\*/#s', '', $kart);
den('  kartta elle yazılmış "İngilizce" yok', !str_contains($kartTemiz, 'İngilizce'), 'yorumsuz');
den('  ve elle yazılmış "English" de yok', !preg_match("/'[^']*\bEnglish\b[^']*'/", $kartTemiz));
/* Sayfanın TAMAMINDA da elle yazılmış dil adı kalmamalı: kusur üç
   yerdeydi (künye kartı, çeviri künyesi, Word aktarımı). */
$yzrTemiz = preg_replace('#/\*.*?\*/#s', '', $yzr);
$elle = [];
if (preg_match_all("/k_c\('([^']*İngilizce[^']*)'/u", $yzrTemiz, $mm)) $elle = $mm[1];
den('sayfada elle yazılmış dil adı taşıyan metin yok', !$elle, implode(' | ', $elle));

/* ------------------------------------------------------------------ */
echo "\n== 2. Kaldırılmış kural artık duyurulmuyor ==\n";
den('kartın başlığında "isteğe bağlı" YOK',
    !str_contains($kart, 'İngilizce, isteğe bağlı') && !str_contains($kart, 'English, optional'));
if (tg_kunye_zorunlu()) {
    den('  başlık ve öz için "zorunlu" imi var',
        substr_count($kart, "k_c('zorunlu', 'required')") >= 2, (string)substr_count($kart, "k_c('zorunlu', 'required')"));
}
/* KÜNYE İLE GERİSİ AYRI. Zorunlu olan yalnız başlık ve özdür; tam
   metin künye dilinde İSTENMİYOR ve istenmeyecek. Hepsini tek bir
   "zorunlu" başlığın altına koymak, verilmeyen bir sözü vermek olurdu. */
den('tam metin bu dilde İSTEĞE BAĞLI olarak imleniyor',
    str_contains($kart, 'hiçbir zaman zorunlu olmayacak'));
den('  anahtar kelimeler ve kaynakça da isteğe bağlı',
    substr_count($kart, "k_c('isteğe bağlı', 'optional')") >= 2);

/* ------------------------------------------------------------------ */
echo "\n== 3. Gönderim formu ile yazar paneli AYNI şeyi söylüyor ==\n";
/* Aynı kural iki sayfada iki ayrı cümleyle yazılırsa, biri bir gün
   ötekinden habersiz değişir. Nitekim tam olarak bu oldu. */
$sayfa = [];
foreach ([['/basvuru.php', 'gönderim formu'], ['/yazar.php', 'yazar paneli']] as [$yol, $ad]) {
    $s = (string)@file_get_contents('http://127.0.0.1:' . $PORT . $yol . '?lang=tr');
    $sayfa[$ad] = $s;
    den($ad . ' açıldı', $s !== '', $yol);
}
$bekle = tg_kunye_zorunlu() ? 'zorunlu' : 'isteğe bağlı';
foreach ($sayfa as $ad => $s) {
    if ($s === '') continue;
    /* Künye alanının yanındaki im: ikisinde de aynı olmalı. */
    den('  ' . $ad . ' künyeyi "' . $bekle . '" diyor',
        tg_kunye_zorunlu()
            ? (str_contains($s, 'ZORUNLU') || str_contains($s, 'zorunlu'))
            : true);
}
if (tg_kunye_zorunlu()) {
    den('  hiçbir sayfa künye için "isteğe bağlı" demiyor',
        !str_contains($sayfa['yazar paneli'] ?? '', 'İngilizce, isteğe bağlı'));
}

/* ------------------------------------------------------------------ */
echo "\n== 4. Uç kuralı UYGULUYOR (kapıda ve pencerede) ==\n";
den('gönderim ucu künyeyi arıyor', str_contains($api, 'if (tg_kunye_zorunlu() && tg_kunye_dili() !== \'\' && tg_dil_kodu($mDil) !== tg_kunye_dili())'));
den('yazar kaydetme ucu da arıyor',
    str_contains($api, "tg_yazi_dili(\$y[\$i]) !== tg_kunye_dili()"));
den('  /yazar-form kuralı geri veriyor', str_contains($api, "'kunye' => ["));
den('  ve çalışmanın dilini de', str_contains($api, "'dil' => tg_yazi_dili(\$e)"));

/* ------------------------------------------------------------------ */
echo "\n== 5. Uçtan uca: üç durum ==\n";
$yz = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [];
$sayi = count($yz);
$ort = ['unvan' => 'Dr.', 'ad' => 'Künye Ölçüm', 'eposta' => 'ky@ornek.org',
        'orcid' => '0000-0002-1825-0097', 'kurum' => 'Ölçüm'];
$tk = bin2hex(random_bytes(12));
$kur = function (string $slug, string $bcid, string $dil, string $tarih, string $bEn, string $oEn) use ($ort, $tk) {
    return ['id' => $slug, 'bcid' => $bcid, 'slug' => $slug, 'tur' => 'yazi', 'tarih' => $tarih,
            'dil' => $dil, 'baslik' => 'Künye ölçümü ' . $slug, 'ozet' => 'Ölçüm.',
            'baslik_en' => $bEn, 'ozet_en' => $oEn, 'yazar' => 'Dr. Künye Ölçüm',
            'yazar_bilgi' => $ort, 'yazar_liste' => [$ort],
            'yazar_erisim' => ['token' => hash('sha256', $tk . $slug), 'sifre' => 'kysifre'],
            'etik' => ['durum' => 'gereksiz']];
};
$yeni = $yz;
$yeni[] = $kur('ky-olcum-dolu', 'bc.990001', 'tr', '2026-08-18T12:00:00+03:00', 'A work', 'An abstract.');
$yeni[] = $kur('ky-olcum-ayni', 'bc.990002', 'en', '2026-08-18T12:00:00+03:00', '', '');
$yeni[] = $kur('ky-olcum-arsiv', 'bc.990003', 'tr', '2024-01-01T12:00:00+03:00', '', '');
file_put_contents($VERI . '/yazilar.json', json_encode($yeni, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

function kist(string $yol, array $g): array {
    static $ip = null;
    if ($ip === null) $ip = '203.0.113.' . random_int(1, 254);
    $port = getenv('KPORT') ?: '8941';
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/json\r\nX-Forwarded-For: $ip\r\n",
        'content' => json_encode($g, JSON_UNESCAPED_UNICODE), 'timeout' => 15, 'ignore_errors' => true]]));
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}
$kim = fn(string $slug) => ['t' => hash('sha256', $tk . $slug), 'sifre' => 'kysifre'];
$govde = ['baslik' => 'Künye ölçümü', 'yazar_bilgi' => $ort];

/* (a) Kuralın kapsadığı çalışma: dolu künye boşaltılamaz. */
$r = kist('/api/yazar-form', $kim('ky-olcum-dolu'));
den('form açıldı (kuralın kapsadığı çalışma)', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  kural yanıtta ve zorunlu', !empty($r['kunye']['zorunlu']));
den('  çalışmanın dili yanıtta', ($r['yazi']['dil'] ?? '') === 'tr', (string)($r['yazi']['dil'] ?? ''));
$r = kist('/api/yazar-kaydet', $kim('ky-olcum-dolu') + $govde + ['baslik_en' => '', 'ozet_en' => 'An abstract.']);
den('DOLU başlık boşaltılamıyor', empty($r['ok']), (string)($r['hata'] ?? ''));
den('  gerekçe silinemeyeceğini söylüyor',
    (bool)preg_match('/silemezsiniz|cannot delete it/u', (string)($r['hata'] ?? '')), (string)($r['hata'] ?? ''));
$r = kist('/api/yazar-kaydet', $kim('ky-olcum-dolu') + $govde + ['baslik_en' => 'A work', 'ozet_en' => '']);
den('  DOLU öz de boşaltılamıyor', empty($r['ok']), (string)($r['hata'] ?? ''));
$r = kist('/api/yazar-kaydet', $kim('ky-olcum-dolu') + $govde + ['baslik_en' => 'Değişmiş başlık', 'ozet_en' => 'Değişmiş öz.']);
den('  ama DEĞİŞTİRİLEBİLİYOR', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* (b) Çalışmanın dili zaten künye diliyse künye istenmez. */
$r = kist('/api/yazar-kaydet', $kim('ky-olcum-ayni') + $govde + ['baslik_en' => '', 'ozet_en' => '']);
den('künye dilinde yazılmış çalışmadan künye İSTENMİYOR', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* (c) ARŞİV KAYDI SUÇLANMIYOR: künye hiç istenmemişti. */
$r = kist('/api/yazar-kaydet', $kim('ky-olcum-arsiv') + $govde + ['baslik_en' => '', 'ozet_en' => '']);
den('arşiv kaydı boş künyeyle de kaydedilebiliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  (kural geriye yürütülmüyor)', !empty($r['ok']));

/* ------------------------------------------------------------------ */
echo "\n== 6. Ölçüm kayıtları geri alındı ==\n";
$kalan = [];
foreach (json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [] as $e) {
    if (is_array($e) && str_starts_with((string)($e['slug'] ?? ''), 'ky-olcum-')) continue;
    $kalan[] = $e;
}
file_put_contents($VERI . '/yazilar.json', json_encode($kalan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('ölçüm kayıtları silindi', count($kalan) === $sayi, count($kalan) . ' / ' . $sayi);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
