<?php
/* =====================================================================
   RET TÜRLERİ: kapı ölçümü. Depoya girmez.

   İKİ AYRI ŞEYE AYNI AD VERİLİYORDU
   ---------------------------------
   Bu sistemde "ret" iki bambaşka olayı anlatıyor:

     MASA REDDİ   Başvuru, hiç yayımlanmadan editör tarafından geri
                  çevrilir. Ortada çalışma yoktur; kayıt yalnızca
                  basvurular.json içinde durur ve dışarıdan HİÇ
                  görünmez. Hakemlikle ilgisi yoktur.
     HAKEM REDDİ  Çalışma yayımlanmıştır, okunabilir, hakem raporları
                  ortadadır ve hakemler onu reddetmiştir. Metin
                  silinmez; gerekçesiyle birlikte açıkta durmayı
                  sürdürür.

   İkisini tek sözcükle anmak, ikisini de yanlış anlatır. "Kutadgu'da
   ret oranı" diye bir sayı verilecekse hangisinin sayıldığı yazılmalı.

   ÜÇÜNCÜ VE DAHA AĞIR KUSUR: REDDEDİLEN ÇALIŞMANIN AŞAMASI
   --------------------------------------------------------
   tg_hakem_asamasi() beş değer üretiyordu: cekildi, yok, aranan,
   suruyor, onayli. İki hakemin reddettiği bir çalışma bunların
   hiçbirine düşmüyor, 'suruyor' sayılıyordu -- yani rozeti
   "Değerlendirmede" diyordu. Oysa süreç KAPANMIŞTIR:
   tg_hakem_araniyor() o çalışmaya artık hakem aramaz. Sistem, kapattığı
   bir süreci okura sürüyormuş gibi gösteriyordu. Bu, handover'ın
   "'tur' yolu söyler, aşama durumu söyler" uyarısının aynısıdır.

   MAHREMİYET SINIRI
   -----------------
   Masa reddi SAYIYLA yayımlanır, ADLA değil. Reddedilen bir başvurunun
   yazarını duyurmak, yayımlanmamış bir çalışmayı sahibinin rızası
   olmadan duyurmaktır; üstelik başvurmayı caydırır. Şeffaflık burada
   sayının kendisindedir: kaç başvuru masadan döndü. Kapı, adların
   sızmadığını da ölçer.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php ret-kapi.php
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
function govde_main(string $h): string {
    if (preg_match('#<main\b[^>]*>(.*?)</main>#is', $h, $m)) return $m[1];
    return $h;
}
function metin(string $h): string {
    $h = preg_replace('#<script\b.*?</script>#is', ' ', $h);
    $h = preg_replace('#<style\b.*?</style>#is', ' ', $h);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8')));
}

/* Nitelik eşiğini geçen rapor: 400+ karakter, iki işaret, dizin anketi.
   (OKUBENI 24: eşiği geçmeyen rapor hiçbir sayıma girmez, bu yüzden
   kurgular gerçek rapor gibi kurulmalı.) */
$uzun = str_repeat('Değerlendirme metni; yöntem, bulgular ve sınırlılıklar. ', 12);
$rap = fn(string $karar, int $i = 0) => [
    'ad' => 'Hakem ' . $i, 'karar' => $karar, 'rapor' => $uzun, 'tarih' => date('c', strtotime('-30 days')),
    'notlar' => [['not' => 'bir'], ['not' => 'iki']], 'endeks_anket' => ['dizin' => 'q1'],
];
$ESIK = (int)tg_ayar('ret_donusum', 2);
olc('ret_donusum eşiği: ' . $ESIK);

echo "\n== 1. Reddedilmiş çalışmanın kendi aşaması var ==\n";
$redli  = ['tur' => 'hakemli', 'tarih' => date('c', strtotime('-200 days')),
           'hakemler' => array_map(fn($i) => $rap('ret', $i), range(1, $ESIK))];
$tekRet = ['tur' => 'hakemli', 'tarih' => date('c', strtotime('-200 days')), 'hakemler' => [$rap('ret', 1)]];
$onayli = ['tur' => 'hakemli', 'tarih' => date('c', strtotime('-200 days')),
           'hakemler' => [$rap('kabul', 1), $rap('kabul', 2)]];

olc('aşamalar: ' . implode(', ', array_map('tg_hakem_asamasi', [$redli, $tekRet, $onayli]))
    . '  (beklenen: reddedildi, suruyor, onayli)');
den('eşiği dolduran ret: aşama "reddedildi"', tg_hakem_asamasi($redli) === 'reddedildi', tg_hakem_asamasi($redli));
den('tek ret: aşama hâlâ "suruyor"', tg_hakem_asamasi($tekRet) === 'suruyor', tg_hakem_asamasi($tekRet));
den('kabuller: aşama "onayli" (gerileme yok)', tg_hakem_asamasi($onayli) === 'onayli', tg_hakem_asamasi($onayli));
den('reddedilmişe artık hakem ARANMAZ', tg_hakem_araniyor($redli) === false);
den('  ve bu zaten böyleydi: aşama onu YAKALIYOR şimdi',
    tg_hakem_araniyor($redli) === false && tg_hakem_asamasi($redli) !== 'suruyor');
den('geri çekme reddin önüne geçer',
    tg_hakem_asamasi(array_merge($redli, ['kayitlar' => [['tur' => 'geri_cekme', 'metin' => 'x']]])) === 'cekildi');

echo "\n== 2. Aşama metinleri eksiksiz ==\n";
$eksik = [];
foreach (['cekildi', 'yok', 'aranan', 'suruyor', 'onayli', 'reddedildi'] as $as) {
    foreach ([false, true] as $en) foreach ([false, true] as $kisa) {
        if (trim(tg_asama_metni($as, $en, $kisa)) === '') $eksik[] = "$as/" . ($en ? 'en' : 'tr') . ($kisa ? '/kisa' : '');
    }
}
den('altı aşamanın da dört metni var', $eksik === [], implode(' ', $eksik));
den('"reddedildi" metni "değerlendirme" demiyor',
    preg_match('/değerlendirmede|under review/iu', tg_asama_metni('reddedildi', false) . ' ' . tg_asama_metni('reddedildi', true)) === 0,
    tg_asama_metni('reddedildi', false));
den('  Türkçesi ret diyor', preg_match('/ret|redd/iu', tg_asama_metni('reddedildi', false)) === 1, tg_asama_metni('reddedildi', false));
den('  İngilizcesi reject diyor', preg_match('/reject/i', tg_asama_metni('reddedildi', true)) === 1, tg_asama_metni('reddedildi', true));
den('rozet rengi tanımlı ve kırmızı ailesinden',
    strpos(tg_asama_rz('reddedildi'), 'kir') !== false, tg_asama_rz('reddedildi'));
den('açıklama cümlesi var ve metnin durduğunu söylüyor',
    preg_match('/yayımda kal|açıkta|silinme|kaldırılma/iu', tg_asama_aciklama('reddedildi', false)) === 1,
    mb_substr(tg_asama_aciklama('reddedildi', false), 0, 70));
den('bilinmeyen aşama hâlâ boş dönüyor', tg_asama_metni('uydurma') === '');

echo "\n== 3. Ret TÜRÜ tek kaynaktan ==\n";
den('tg_ret_turu() tanımlı', function_exists('tg_ret_turu'));
$rt = fn($e) => function_exists('tg_ret_turu') ? tg_ret_turu($e) : 'YOK';
den('reddedilmiş çalışma: "hakem"', $rt($redli) === 'hakem', (string)$rt($redli));
den('tek retli çalışma: ret türü yok', $rt($tekRet) === '', (string)$rt($tekRet));
den('onaylı çalışma: ret türü yok', $rt($onayli) === '', (string)$rt($onayli));
den('tg_ret_metni() iki türü AYRI anlatıyor',
    function_exists('tg_ret_metni') && tg_ret_metni('masa') !== '' && tg_ret_metni('hakem') !== ''
    && tg_ret_metni('masa') !== tg_ret_metni('hakem'));
den('  masa reddi "yayımlanmadan" diyor',
    function_exists('tg_ret_metni') && preg_match('/yayımlanmadan|yayına alınmadan|başvuru/iu', tg_ret_metni('masa')) === 1,
    function_exists('tg_ret_metni') ? tg_ret_metni('masa') : '-');
den('  hakem reddi "yayımdadır" diyor',
    function_exists('tg_ret_metni') && preg_match('/yayımda|açıkta|okunabil/iu', tg_ret_metni('hakem')) === 1,
    function_exists('tg_ret_metni') ? tg_ret_metni('hakem') : '-');
den('  İngilizceleri de ayrı',
    function_exists('tg_ret_metni') && tg_ret_metni('masa', true) !== tg_ret_metni('hakem', true)
    && tg_ret_metni('masa', true) !== tg_ret_metni('masa'));

echo "\n== 4. Masa reddi sayıyla yayımlanıyor ==\n";
den('tg_masa_reddi() tanımlı', function_exists('tg_masa_reddi'));
$ms = function_exists('tg_masa_reddi') ? tg_masa_reddi() : [];
foreach (['ret', 'kabul', 'bekleyen', 'toplam'] as $a) {
    den("  '$a' anahtarı var", is_array($ms) && array_key_exists($a, $ms));
}
$bv = json_decode((string)@file_get_contents($VERI . '/basvurular.json'), true);
if (!is_array($bv)) $bv = [];
$sayimRet = 0; $sayimKabul = 0; $sayimBek = 0;
foreach ($bv as $e) {
    $d = (string)($e['durum'] ?? '');
    if ($d === 'ret') $sayimRet++; elseif ($d === 'kabul') $sayimKabul++; else $sayimBek++;
}
olc("basvurular.json: ret=$sayimRet kabul=$sayimKabul bekleyen=$sayimBek toplam=" . count($bv));
den('  sayı dosyayla birebir', is_array($ms) && (int)($ms['ret'] ?? -1) === $sayimRet, (string)($ms['ret'] ?? '-'));
den('  toplam dosyayla birebir', is_array($ms) && (int)($ms['toplam'] ?? -1) === count($bv));
den('  bekleyen dosyayla birebir', is_array($ms) && (int)($ms['bekleyen'] ?? -1) === $sayimBek);

$md5 = md5_file($VERI . '/basvurular.json');
if (function_exists('tg_masa_reddi')) tg_masa_reddi();
clearstatcache(true, $VERI . '/basvurular.json');
den('  gerçekten değiştirmiyor (md5)', md5_file($VERI . '/basvurular.json') === $md5);

echo "\n== 5. İstatistik sayfası ikisini AYRI gösteriyor ==\n";
$is = ist('/istatistik.php?lang=tr');
$mi = metin(govde_main($is['govde']));
den('istatistik 200', $is['kod'] === 200, (string)$is['kod']);
den('  masa reddi bölümü var', preg_match('/masa reddi|masadan/iu', $mi) === 1);
den('  hakem reddi ayrı anılıyor', preg_match('/hakem reddi|hakemler(in)? redde/iu', $mi) === 1);
den('  ikisinin farkı yazıyla anlatılıyor',
    preg_match('/yayımlanmadan/iu', $mi) === 1 && preg_match('/yayımda|açıkta/iu', $mi) === 1);
/* OKUBENI 33: tg_asama_metni('reddedildi') boşken preg_quote('') boş
   desen üretir ve BOŞ DESEN HER METNE UYAR -- deneme, aşama metni hiç
   yokken GECTI verdi. Aranan dize boşsa deneme geçemez. */
$mRed = tg_asama_metni('reddedildi', false);
den('  reddedilmiş çalışma sayısı basamaklarda',
    $mRed !== '' && preg_match('/' . preg_quote($mRed, '/') . '/iu', $mi) === 1, $mRed);
/* Basamaklar bütün arşivi bölmeyi SÜRDÜRMELİ: sayfa "toplamları
   çalışma sayısına eşittir" diyor; beşinci basamak eklenince bu söz
   tutulmazsa sayfa yalan söyler. */
if (preg_match_all('/(\d+)\s+(?:' . preg_quote(tg_asama_metni('yok', false, true), '/') . '|hakemsiz)/iu', $mi, $mm)) {
    olc('basamak sayıları sayfadan okunabildi');
}
den('  "dört sayı" ifadesi güncellenmiş (artık beş)',
    preg_match('/aşağıdaki dört sayı/iu', $mi) === 0);

echo "\n== 6. Mahremiyet: masa reddi ADLA görünmüyor ==\n";
$adlar = [];
foreach ($bv as $e) {
    if ((string)($e['durum'] ?? '') !== 'ret') continue;
    $b = is_array($e['basvuran'] ?? null) ? $e['basvuran'] : [];
    $ad = trim((string)($b['ad'] ?? ''));
    $bas = trim((string)($e['makale_baslik'] ?? ''));
    if ($ad !== '') $adlar[] = $ad;
    if ($bas !== '') $adlar[] = $bas;
}
olc(count($adlar) . ' reddedilmiş başvuru adı/başlığı denendi');
$sizan = [];
foreach (['/istatistik.php?lang=tr', '/', '/yazilar.php', '/bekleyen.php'] as $sf) {
    $r = ist($sf);
    if ($r['kod'] !== 200) continue;
    foreach ($adlar as $a) { if (mb_strlen($a) > 6 && mb_strpos($r['govde'], $a) !== false) $sizan[] = $sf . ':' . mb_substr($a, 0, 24); }
}
/* OKUBENI 34: sınama verisinde reddedilmiş başvuru YOKTUR; deneme
   boşlukta geçerdi ve sayıyı şişirirdi. Veri yoksa deneme sayılmaz,
   ÖLÇÜM olarak yazılır -- ölçmediğimiz şeye GECTI vermiyoruz. */
if ($adlar === []) {
    olc('reddedilmiş başvuru yok; sızıntı denemesi ÇALIŞTIRILMADI (boş geçiş sayılmaz)');
} else {
    den('reddedilmiş başvurunun adı/başlığı hiçbir açık sayfada YOK', $sizan === [], implode(' | ', array_slice($sizan, 0, 3)));
}
den('  basvurular.json dışarıdan okunamıyor', ist('/basvurular.json')['kod'] !== 200,
    (string)ist('/basvurular.json')['kod']);

echo "\n== 7. Reddedilmiş çalışma sayfası ==\n";
den('gecikme: reddedilmiş çalışma artık BEKLEMİYOR',
    function_exists('tg_gecikme') && tg_gecikme($redli)['bekleme_gun'] === null,
    function_exists('tg_gecikme') ? var_export(tg_gecikme($redli)['bekleme_gun'], true) : '-');
den('  ama ilk rapora kadar geçen süre duruyor',
    function_exists('tg_gecikme') && tg_gecikme($redli)['ilk_rapor_gun'] !== null);
den('reddedilmiş çalışma hakemden GEÇMİŞ sayılır (metin silinmez)',
    tg_hakemden_gecti($redli) === true);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
