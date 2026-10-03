<?php
/* =====================================================================
   GECİKME TAKİBİ: kapı ölçümü. Depoya girmez.

   ÖLÇÜLEN SÖZ
   -----------
   Bu sistem hakemliğin GÖRÜNÜR olmasıyla güven kazanıyor. Görünür
   olan şimdiye kadar yalnızca sonuçtu: kaç rapor geldi, kim yazdı, ne
   dedi. Görünmeyen şey SÜREYDİ. Bir çalışma iki yıldır hakem bekliyor
   olabilir ve sayfasında bunu söyleyen tek bir sayı bulunmuyordu.
   Süreyi saklamak, kötü haberi saklamaktır; bu sistemin iddiasıyla
   bağdaşmaz.

   Bu yüzden ölçüt şudur: bir çalışmanın NE ZAMAN geldiği ve NE
   KADARDIR beklediği, o çalışmanın kendi sayfasında okura görünür.

   ÜÇ TASARIM KARARI, ÜÇÜ DE ÖLÇÜLÜYOR
   -----------------------------------
   1. GECİKME HESAPLANIR, SAKLANMAZ. Aşama (tg_hakem_asamasi) neden
      saklanmıyorsa gecikme de o yüzden saklanmaz: saklanan bir sayı
      güncellenmeyi unutabilir, hesaplanan bir sayı unutamaz. Kapı,
      hesabın veri dosyasına dokunmadığını md5 ile ölçer.

   2. KAYNAK DÜRÜSTÇE ETİKETLENİR. Yeni çalışmalarda gönderim anı
      'gonderim' alanına yazılır (başvurunun kendi tarihi). ESKİ
      çalışmalarda böyle bir alan YOKTUR ve geriye dönük uydurulamaz
      -- dördüncü değişmez ilke yayımlanmış kaydın sonradan
      değiştirilmesini yasaklar. O hâlde eski kayıtlarda 'tarih'
      alanına düşülür ve sayının yanına bunun gönderim değil YAYIN
      günü olduğu yazılır. Kapı, kaynağın etiketlendiğini ölçer.

   3. SAYI YALNIZCA ANLAMLI OLDUĞU AŞAMADA GÖSTERİLİR. "Hakem
      onaylı" bir çalışmada "412 gündür bekliyor" yazmak yalandır.
      Bekleme yalnızca 'aranan' ve 'suruyor' aşamalarında sürer.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php gecikme-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ist(string $yol): array {
    global $PORT;
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250),
        'ignore_errors' => true, 'timeout' => 30]]);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, $ctx);
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
}
/* Yalnızca <main> içi: kabuk, altbilgi ve gezinti sayılmaz. */
function govde_main(string $h): string {
    if (preg_match('#<main\b[^>]*>(.*?)</main>#is', $h, $m)) return $m[1];
    return $h;
}
function metin(string $h): string {
    $h = preg_replace('#<script\b.*?</script>#is', ' ', $h);
    $h = preg_replace('#<style\b.*?</style>#is', ' ', $h);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8')));
}
function gunOnce(int $g): string { return date('c', strtotime("-$g days")); }

$VY = $VERI . '/yazilar.json';

echo "== 1. Hesap var mı ve saf mı ==\n";
$varmi = function_exists('tg_gecikme');
den('tg_gecikme() tanımlı', $varmi);
if (!$varmi) {
    olc('hesap yok; 2-6. bölümler ölçülemez, hepsi KALDI sayılır');
}

$md5Once = md5_file($VY);
$ornek = ['tur' => 'hakemli', 'tarih' => gunOnce(100), 'hakemler' => []];
$g1 = $varmi ? tg_gecikme($ornek) : [];
clearstatcache(true, $VY);
den('hesap veri dosyasına dokunmuyor', md5_file($VY) === $md5Once);
den('dönen değer dizi', is_array($g1));
foreach (['gonderim', 'kaynak', 'bekleme_gun', 'ilk_rapor', 'ilk_rapor_gun', 'son_olay'] as $a) {
    den("  anahtar '$a' var", is_array($g1) && array_key_exists($a, $g1));
}

echo "\n== 2. Kaynak dürüstçe etiketleniyor ==\n";
$eski = ['tur' => 'hakemli', 'tarih' => gunOnce(365), 'hakemler' => []];
$ge = $varmi ? tg_gecikme($eski) : [];
den("gonderim alanı yoksa kaynak 'tarih'", ($ge['kaynak'] ?? '') === 'tarih', (string)($ge['kaynak'] ?? '-'));
$yeni = ['tur' => 'hakemli', 'tarih' => gunOnce(300), 'gonderim' => gunOnce(340), 'hakemler' => []];
$gy = $varmi ? tg_gecikme($yeni) : [];
den("gonderim alanı varsa kaynak 'gonderim'", ($gy['kaynak'] ?? '') === 'gonderim', (string)($gy['kaynak'] ?? '-'));
den('  ve sayı gonderim alanından sayılır (340)', (int)($gy['bekleme_gun'] ?? -1) === 340, (string)($gy['bekleme_gun'] ?? '-'));
den('  yani tarih alanından DEĞİL (300 değil)', (int)($gy['bekleme_gun'] ?? -1) !== 300);
den('kaynak metni tr', $varmi && tg_gecikme_kaynak_metni('tarih') !== '' && tg_gecikme_kaynak_metni('gonderim') !== '');
den('kaynak metni en', $varmi && tg_gecikme_kaynak_metni('tarih', true) !== tg_gecikme_kaynak_metni('tarih'));
den("'tarih' kaynağı okura yayın günü olduğunu söylüyor",
    $varmi && preg_match('/yayın|yayim|arşiv/iu', tg_gecikme_kaynak_metni('tarih')) === 1,
    $varmi ? tg_gecikme_kaynak_metni('tarih') : '-');

echo "\n== 3. Sayı yalnızca anlamlı aşamada ==\n";
/* OKUBENI 24: bu bölümü ilk yazışımda iki ölçüm hatası vardı, ikisi de
   denemeyi ASLA geçemez hâle getiriyordu:
     a) "(...)['bekleme_gun'] ?? 0) === null" -- ?? işleci null'ı yutar,
        yani null beklenen denemenin kendisi null'ı göremez. Değer
        artık bir yardımcıyla, ?? kullanılmadan okunuyor.
     b) 'onayli' ve 'cekildi' kurguları o aşamalara hiç düşmüyordu:
        onay için rapor 400 karakterden uzun olmalı, iki işaret notu ve
        dizin anketi bulunmalı; geri çekme ise 'kayitlar' içinde
        tur='geri_cekme' kaydıyla olur. ÖLÇÜM satırı aşamaları yazdırıp
        bunu görünür kılıyor. */
function bek(array $y) { $g = tg_gecikme($y); return $g['bekleme_gun']; }
function ilkg(array $y) { $g = tg_gecikme($y); return $g['ilk_rapor_gun']; }

$uzunRapor = str_repeat('Değerlendirme metni; yöntem, bulgular ve sınırlılıklar. ', 12);
$rapor = fn(int $gun) => [
    'ad' => 'H' . $gun, 'karar' => 'kabul', 'rapor' => $uzunRapor, 'tarih' => gunOnce($gun),
    'notlar' => [['not' => 'birinci işaret'], ['not' => 'ikinci işaret']],
    'endeks_anket' => ['dizin' => 'q1'],
];
$aranan  = ['tur' => 'hakemli', 'tarih' => gunOnce(200), 'hakemler' => []];
$suruyor = ['tur' => 'hakemli', 'tarih' => gunOnce(200), 'hakemler' => [$rapor(30)]];
$onayli  = ['tur' => 'hakemli', 'tarih' => gunOnce(200), 'hakemler' => [$rapor(30), $rapor(25), $rapor(20)]];
$yoktur  = ['tur' => 'yazi',    'tarih' => gunOnce(200), 'hakemler' => []];
$cekili  = ['tur' => 'hakemli', 'tarih' => gunOnce(200), 'hakemler' => [],
            'kayitlar' => [['tur' => 'geri_cekme', 'tarih' => gunOnce(10), 'metin' => 'gerekçe']]];

if ($varmi) {
    olc('aşamalar: ' . implode(', ', array_map(fn($e) => tg_hakem_asamasi($e), [$aranan, $suruyor, $onayli, $yoktur, $cekili]))
        . '  (beklenen: aranan, suruyor, onayli, yok, cekildi)');
}
den("aranan: bekleme sayısı var", $varmi && bek($aranan) === 200, $varmi ? var_export(bek($aranan), true) : '-');
den("suruyor: bekleme sayısı var", $varmi && bek($suruyor) === 200, $varmi ? var_export(bek($suruyor), true) : '-');
den("onayli: bekleme YOK (null)", $varmi && bek($onayli) === null, $varmi ? var_export(bek($onayli), true) : '-');
den("hakemsiz: bekleme YOK (null)", $varmi && bek($yoktur) === null, $varmi ? var_export(bek($yoktur), true) : '-');
den("çekilmiş: bekleme YOK (null)", $varmi && bek($cekili) === null, $varmi ? var_export(bek($cekili), true) : '-');
den("onayli: ilk rapora kadar geçen süre 170 gün", $varmi && ilkg($onayli) === 170,
    $varmi ? var_export(ilkg($onayli), true) : '-');
den("aranan: ilk rapor yok (null)", $varmi && ilkg($aranan) === null, $varmi ? var_export(ilkg($aranan), true) : '-');
den("suruyor: son olay ilk rapor günü", $varmi && substr((string)(tg_gecikme($suruyor)['son_olay'] ?? ''), 0, 10) === substr(gunOnce(30), 0, 10));
den("aranan: son olay gönderim günü", $varmi && substr((string)(tg_gecikme($aranan)['son_olay'] ?? ''), 0, 10) === substr(gunOnce(200), 0, 10));

echo "\n== 4. Sayının dili ==\n";
den('0 gün "bugün" der, "0 gün" demez',
    $varmi && preg_match('/^0\b/', tg_gun_metni(0)) === 0, $varmi ? tg_gun_metni(0) : '-');
den('1 gün tekil', $varmi && tg_gun_metni(1) !== '' && strpos(tg_gun_metni(1), '1 gün') !== false, $varmi ? tg_gun_metni(1) : '-');
den('45 gün gün olarak', $varmi && strpos(tg_gun_metni(45), '45 gün') !== false, $varmi ? tg_gun_metni(45) : '-');
den('400 gün ay/yıl olarak da anlatılır',
    $varmi && preg_match('/yıl|ay/u', tg_gun_metni(400)) === 1, $varmi ? tg_gun_metni(400) : '-');
den('İngilizcesi ayrı', $varmi && tg_gun_metni(45, true) !== tg_gun_metni(45) && strpos(tg_gun_metni(45, true), 'day') !== false,
    $varmi ? tg_gun_metni(45, true) : '-');
den('eksi gün üretilmez (gelecek tarih 0 sayılır)',
    $varmi && (int)(tg_gecikme(['tur' => 'hakemli', 'tarih' => date('c', strtotime('+30 days')), 'hakemler' => []])['bekleme_gun'] ?? -1) === 0);

echo "\n== 5. Okur çalışma sayfasında görüyor mu ==\n";
$y = json_decode((string)@file_get_contents($VY), true);
if (!is_array($y)) $y = [];
/* OKUBENI 21: /yazi.php?y=<slug> tamga adresine 301 verir ve hedef
   üretim konağıdır; yerel ölçüm oraya gidemez. Sayfa, sistemin kendi
   yol üreticisiyle istenir -- kapı böylece kanonik adresi de ölçmüş
   olur. */
$slugS = ''; $slugY = '';
foreach ($y as $e) {
    if (!is_array($e)) continue;
    $a = tg_hakem_asamasi($e);
    if ($a === 'suruyor' && $slugS === '') $slugS = tg_yazi_yolu($e);
    if ($a === 'yok'     && $slugY === '') $slugY = tg_yazi_yolu($e);
}
olc("ölçülen çalışmalar: suruyor=$slugS  hakemsiz=$slugY");

$s = ist($slugS . '?lang=tr');
den('değerlendirmedeki çalışma sayfası 200', $s['kod'] === 200, (string)$s['kod']);
$mS = metin(govde_main($s['govde']));
den('  gövdede gün sayısı geçiyor', preg_match('/\d+\s*gün/u', $mS) === 1, mb_substr($mS, 0, 0));
den('  makine okunur <time datetime> var',
    preg_match('#<time[^>]+datetime="\d{4}-\d{2}-\d{2}#i', govde_main($s['govde'])) === 1);
den('  sayının kaynağı yazılı (gönderim/yayın)',
    preg_match('/gönderildi|gönderim|yayına alındı|arşive/iu', $mS) === 1);

$sE = ist($slugS . '?lang=en');
den('İngilizce sayfa 200', $sE['kod'] === 200, (string)$sE['kod']);
$mE = metin(govde_main($sE['govde']));
den('  İngilizcesinde gün sayısı geçiyor', preg_match('/\d+\s*days?\b/u', $mE) === 1);
den('  İngilizcesinde Türkçe "gün" sızmamış', preg_match('/\d+\s*gün/u', $mE) === 0);

if ($slugY !== '') {
    $sY = ist($slugY . '?lang=tr');
    $mY = metin(govde_main($sY['govde']));
    den('hakemsiz yazıda bekleme cümlesi YOK',
        preg_match('/\d+\s*gündür|gündür (hakem|değerlendirme)/u', $mY) === 0);
}

echo "\n== 6. Editör ve okur toplu görüyor mu ==\n";
/* OKUBENI 25: dil, ziyaretçinin ülkesinden çözülüyor ve kapının
   gönderdiği CF-Connecting-IP başlığı sayfayı İNGİLİZCE açtırıyordu;
   Türkçe desenlerin hiçbiri tutmadı, üç sahte KALDI çıktı. Dil artık
   açıkça isteniyor. */
$ist = ist('/istatistik.php?lang=tr');
$mI = metin(govde_main($ist['govde']));
den('istatistik sayfası 200', $ist['kod'] === 200, (string)$ist['kod']);
den('  bekleme süresi bölümü var', preg_match('/bekleme|süre|gecikme/iu', $mI) === 1);
den('  ortanca (medyan) veriliyor', preg_match('/ortanca|medyan/iu', $mI) === 1);
den('  ortalama tek başına verilmiyor',
    preg_match('/ortalama/iu', $mI) === 0 || preg_match('/ortanca|medyan/iu', $mI) === 1);
den('  en uzun bekleyen sayısı var', preg_match('/en uzun|en eski/iu', $mI) === 1);
den('  kaynağı kaç kayıtta gerçek olduğu yazılı',
    preg_match('/gönderim günü kayıtlı/iu', $mI) === 1);
/* Sıralı rampa dört basamaklıdır; beşinciyi isteyen çubuk RENKSİZ
   çizilir ve sayfada boş şerit görünür. Ölçüm tanımsız değişkeni
   doğrudan arar. */
if (preg_match_all('/var\(--gr-(\d+)\)/', $ist['govde'], $mm)) {
    $enBuyuk = max(array_map('intval', $mm[1]));
    olc('kullanılan en büyük sıralı basamak: --gr-' . $enBuyuk);
    den('  tanımsız renk basamağı istenmiyor (--gr-1..4)', $enBuyuk <= 4, '--gr-' . $enBuyuk);
} else {
    den('  tanımsız renk basamağı istenmiyor (--gr-1..4)', true);
}

$bek = ist('/bekleyen.php?lang=tr');
$mB = metin(govde_main($bek['govde']));
den('bekleyen çalışmalar sayfası 200', $bek['kod'] === 200, (string)$bek['kod']);
/* Sayının görünmesi yetmez, SIRAYA da geçmelidir. Gönüllü hakem arayan
   bir sayfada en uzun bekleyen çalışma listenin dibinde duruyorsa
   görünürlük süsten ibarettir. Eski sıra ikinci ölçüt olarak "en yeni"
   diyordu; tam tersi. */
$sr = $varmi ? tg_bekleyen_sirala([
    ['tur' => 'hakemli', 'slug' => 'yeni', 'tarih' => gunOnce(10),  'hakemler' => []],
    ['tur' => 'hakemli', 'slug' => 'eski', 'tarih' => gunOnce(800), 'hakemler' => []],
    ['tur' => 'hakemli', 'slug' => 'orta', 'tarih' => gunOnce(300), 'hakemler' => []],
]) : [];
den('  eşit rapordakiler arasında EN UZUN BEKLEYEN önde',
    array_column($sr, 'slug') === ['eski', 'orta', 'yeni'],
    implode(',', array_column($sr, 'slug')));
$sr2 = $varmi ? tg_bekleyen_sirala([
    ['tur' => 'hakemli', 'slug' => 'raporlu', 'tarih' => gunOnce(900), 'hakemler' => [['rapor' => 'x', 'tarih' => gunOnce(5)]]],
    ['tur' => 'hakemli', 'slug' => 'raporsuz', 'tarih' => gunOnce(10), 'hakemler' => []],
]) : [];
den('  ama raporu hiç olmayan yine de önde', ($sr2[0]['slug'] ?? '') === 'raporsuz',
    implode(',', array_column($sr2, 'slug')));
/* OKUBENI 23: ilk desen düz "gün" arıyordu ve sayfadaki "Ergün",
   "Özgün" gibi ADLARA takılıp sahte GECTI verdi. Sayı istenmelidir:
   bekleme bir süredir, süre sayı ile yazılır. */
den('  her satırda ne kadardır beklediği yazılı', preg_match('/\d+\s*gün/u', $mB) === 1);

echo "\n== 7. Gönderim anı yeni kayıtlara yazılıyor mu ==\n";
/* OKUBENI 22: 'gonderim' alanı sistemde ZATEN vardı -- yönetim
   düzeltme yolunda (api/index.php, "Gönderim tarihi" yorumu). İlk
   yazdığım desen o satırı yakalayıp sahte GECTI verdi. Ölçüm, alanın
   var olup olmadığını değil, BAŞVURUDAN ÇALIŞMA ÜRETİLİRKEN yazılıp
   yazılmadığını sormalı; bu yüzden yalnızca $makale bloğu okunur. */
$api = file_get_contents($KOD . '/api/index.php');
$blok = '';
if (preg_match('/\$makale\s*=\s*\[(.*?)\n    \];/s', $api, $mB)) $blok = $mB[1];
olc('$makale bloğu ' . ($blok === '' ? 'BULUNAMADI' : strlen($blok) . ' bayt'));
den("başvurudan çalışma üretilirken 'gonderim' yazılıyor",
    $blok !== '' && preg_match("/'gonderim'\s*=>/", $blok) === 1);
den('  ve değeri başvurunun kendi tarihidir, date() değil',
    $blok !== '' && preg_match("/'gonderim'\s*=>[^,\n]*\\\$bs\[/", $blok) === 1
                 && preg_match("/'gonderim'\s*=>\s*date\(/", $blok) === 0);
den('yönetim düzeltme yolundaki alan duruyor (geriye uyum)',
    preg_match("/'gonderim'\s*=>\s*\\\$al\('gonderim'/", $api) === 1);
den('eski kayıtlar toplu doldurulmuyor',
    preg_match("/foreach[^\n]*\\\$y as[^\n]*\n[^\n]*\['gonderim'\]\s*=\s*/", $api) === 0);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
