<?php
/* =====================================================================
   KUTADGU - Paylaşım kartı / Share card
   Bir çalışmanın sosyal mecralarda görünecek görselini üretir.
   Ölçüler:
     yatay  1200x630   Open Graph, LinkedIn, X, WhatsApp, Telegram
     kare   1080x1080  Instagram gönderisi
     hikaye 1080x1920  Instagram ve WhatsApp hikâyesi
   Adres:  /kart.php?y=<slug>&olcu=kare
   Dış istek yoktur; yazı tipleri depoyla birlikte gelir.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

if (!function_exists('imagecreatetruecolor')) { http_response_code(501); exit('GD yok'); }

$slug  = isset($_GET['y'])   ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$_GET['y'])) : '';
$kodAr = isset($_GET['k'])   ? preg_replace('/[^A-Za-z0-9.\-]/', '', (string)$_GET['k']) : '';
$olcu  = isset($_GET['olcu']) ? preg_replace('/[^a-z]/', '', (string)$_GET['olcu']) : 'yatay';
if (!in_array($olcu, ['yatay', 'kare', 'hikaye'], true)) $olcu = 'yatay';

$yazi = null;
foreach (k_yazilar() as $e) {
    if ($kodAr !== '' && strcasecmp((string)($e['bcid'] ?? ''), $kodAr) === 0) { $yazi = $e; break; }
    if ($kodAr === '' && ((string)($e['slug'] ?? '') === $slug || (string)($e['id'] ?? '') === $slug)) { $yazi = $e; break; }
}
if (!$yazi) { http_response_code(404); exit('yok'); }

$en = k_en();
$OL = ['yatay' => [1200, 630], 'kare' => [1080, 1080], 'hikaye' => [1080, 1920]][$olcu];
[$G, $Y] = $OL;

$FS  = __DIR__ . '/k/yazitipi/DejaVuSerif-Bold.ttf';   /* başlık */
$FSN = __DIR__ . '/k/yazitipi/DejaVuSerif.ttf';        /* yazar */
$FU  = __DIR__ . '/k/yazitipi/DejaVuSans.ttf';         /* arayüz */
$FUB = __DIR__ . '/k/yazitipi/DejaVuSans-Bold.ttf';
foreach ([$FS, $FSN, $FU, $FUB] as $f) { if (!is_file($f)) { http_response_code(500); exit('yazi tipi yok'); } }

$im = imagecreatetruecolor($G, $Y);
imageantialias($im, true);

/* ---- Renkler: sitenin açık teması ---- */
$kagit   = imagecolorallocate($im, 250, 248, 244);
$murekkep= imagecolorallocate($im, 21, 24, 29);
$sonuk   = imagecolorallocate($im, 92, 102, 117);
$kut     = imagecolorallocate($im, 154, 107, 18);
$kutAcik = imagecolorallocate($im, 192, 141, 40);
$cizgi   = imagecolorallocate($im, 229, 224, 214);
$beyaz   = imagecolorallocate($im, 255, 255, 255);
$yesil   = imagecolorallocate($im, 28, 107, 71);
$kirmizi = imagecolorallocate($im, 163, 36, 43);

imagefilledrectangle($im, 0, 0, $G, $Y, $kagit);

/* Üstte ince altın şerit */
$serit = max(8, (int)round($Y * 0.012));
imagefilledrectangle($im, 0, 0, $G, $serit, $kut);

imagealphablending($im, true);

/* ---- Yardımcılar ---- */
function kt_yaz($im, string $font, float $boy, int $x, int $y, int $renk, string $metin): array {
    $k = imagettftext($im, $boy, 0, $x, $y, $renk, $font, $metin);
    return is_array($k) ? $k : [];
}
function kt_genislik(string $font, float $boy, string $metin): int {
    $k = imagettfbbox($boy, 0, $font, $metin);
    return is_array($k) ? (int)abs($k[2] - $k[0]) : 0;
}
/* Metni verilen genişliğe göre satırlara böl */
function kt_sar(string $font, float $boy, string $metin, int $enFazla, int $satirUst = 99): array {
    $kelime = preg_split('/\s+/u', trim($metin));
    $satir = []; $s = '';
    foreach ($kelime as $w) {
        $d = $s === '' ? $w : $s . ' ' . $w;
        if (kt_genislik($font, $boy, $d) <= $enFazla) { $s = $d; continue; }
        if ($s !== '') $satir[] = $s;
        $s = $w;
        if (count($satir) >= $satirUst) break;
    }
    if ($s !== '' && count($satir) < $satirUst) $satir[] = $s;
    if (count($satir) > $satirUst) $satir = array_slice($satir, 0, $satirUst);
    return $satir;
}

$kenar = (int)round($G * 0.075);
$ic    = $G - 2 * $kenar;
$olcek = $G / 1200;

/* ---- Üst satır: marka ----
   İşaret elle çizilmiyor; kurumsal kimlikte duran gerçek tamga
   görseli kullanılıyor. Önceden dört çizgiyle kabaca taklit
   ediliyordu ve paylaşılan görselde işaret kendisine benzemiyordu.
   Bir kimliğin en çok görüldüğü yer paylaşım görselidir; orada
   yaklaşık bir çizim, kimliğin kendisini aşındırır. */
$mBoy = 20 * $olcek * ($olcu === 'yatay' ? 1 : 1.35);
$ust  = $serit + (int)round(52 * $olcek * ($olcu === 'hikaye' ? 1.8 : 1));
$s = (int)round($mBoy * 2.1);
$tamgaYol = __DIR__ . '/k/tamga-512.png';
$tamgaIm = is_file($tamgaYol) ? @imagecreatefrompng($tamgaYol) : null;
if ($tamgaIm) {
    imagealphablending($im, true);
    imagecopyresampled($im, $tamgaIm, $kenar, $ust - $s + (int)($s * 0.18), 0, 0,
        $s, $s, imagesx($tamgaIm), imagesy($tamgaIm));
} else {
    imagefilledrectangle($im, $kenar, $ust - $s + (int)($s*0.18), $kenar + $s, $ust + (int)($s*0.18), $kut);
}
kt_yaz($im, $FUB, $mBoy, $kenar + $s + (int)(16 * $olcek), $ust, $murekkep, (string)tg_ayar('marka', 'Kutadgu'));
$altAd = mb_strtoupper(tg_marka_alt($en), 'UTF-8');
kt_yaz($im, $FU, $mBoy * 0.42, $kenar + $s + (int)(16 * $olcek), $ust + (int)($mBoy * 0.85), $sonuk, $altAd);

/* ---- Rozetler ---- */
$rozetY = $ust + (int)round(($olcu === 'hikaye' ? 150 : 88) * $olcek);
$rx = $kenar;
$rBoy = 15 * $olcek * ($olcu === 'yatay' ? 1 : 1.3);
/* Rozet 'tur' alanından değil aşamadan üretilir: 'tur' yalnızca hangi
   yolda olunduğunu söyler. Bir rapor bile gelmemiş çalışmanın kartında
   "HAKEMLİ" yazması, paylaşılan görselde geri alınamayacak bir söz olur;
   görsel siteden koparak dolaşır, düzeltmesi de yayılmaz.
   Rapor beklenen aşamalar sönük renkle verilir; renk de bir iddiadır,
   kurumsal altın "bitmiş iş" gibi okunur. */
$asama = tg_hakem_asamasi($yazi);
$asamaRenk = ['cekildi' => $kirmizi, 'onayli' => $yesil, 'suruyor' => $kut,
              'aranan' => $sonuk, 'yok' => $sonuk];
$rozetler = [];
$rozetler[] = [mb_strtoupper(tg_asama_metni($asama, $en, true), 'UTF-8'), $asamaRenk[$asama] ?? $sonuk];
$rozetler[] = [k_c('AÇIK ERİŞİM', 'OPEN ACCESS'), $sonuk];
$rozetler[] = ['CC BY 4.0', $sonuk];
foreach ($rozetler as $r) {
    $w = kt_genislik($FUB, $rBoy, $r[0]) + (int)(26 * $olcek);
    $h = (int)($rBoy * 2.3);
    imagefilledrectangle($im, $rx, $rozetY - $h, $rx + $w, $rozetY, $r[1]);
    kt_yaz($im, $FUB, $rBoy, $rx + (int)(13 * $olcek), $rozetY - (int)($h * 0.32), $beyaz, $r[0]);
    $rx += $w + (int)(10 * $olcek);
}

/* ---- Başlık ---- */
$baslik = trim(strip_tags((string)($en && trim((string)($yazi['baslik_en'] ?? '')) !== '' ? $yazi['baslik_en'] : ($yazi['baslik'] ?? ''))));
$bBoy = ($olcu === 'yatay' ? 46 : ($olcu === 'kare' ? 54 : 62)) * $olcek;
$enFazlaSatir = $olcu === 'yatay' ? 4 : ($olcu === 'kare' ? 6 : 8);
$satir = kt_sar($FS, $bBoy, $baslik, $ic, $enFazlaSatir + 1);
while (count($satir) > $enFazlaSatir && $bBoy > 20 * $olcek) {
    $bBoy *= 0.9;
    $satir = kt_sar($FS, $bBoy, $baslik, $ic, $enFazlaSatir + 1);
}
if (count($satir) > $enFazlaSatir) { $satir = array_slice($satir, 0, $enFazlaSatir); $satir[$enFazlaSatir - 1] .= '...'; }
$by = $rozetY + (int)($bBoy * 1.6);
foreach ($satir as $s2) { kt_yaz($im, $FS, $bBoy, $kenar, $by, $murekkep, $s2); $by += (int)($bBoy * 1.34); }

/* ---- Yazarlar ---- */
$yzr = k_yazarlar($yazi);
if ($yzr !== '') {
    $yBoy = ($olcu === 'yatay' ? 24 : 30) * $olcek;
    $ysat = kt_sar($FSN, $yBoy, $yzr, $ic, 2);
    $by += (int)($yBoy * 0.7);
    foreach ($ysat as $s3) { kt_yaz($im, $FSN, $yBoy, $kenar, $by, $sonuk, $s3); $by += (int)($yBoy * 1.35); }
}

/* ---- Özet ----
   Yatay kartta da gösterilir: başlıkla adres arasındaki boşluk,
   paylaşılan görselde okunacak bir şey olmadığı izlenimi veriyordu.
   Sığdığı kadarı yazılır, sonrası kesilir. */
{
    $ozet = trim(strip_tags((string)($en && trim((string)($yazi['ozet_en'] ?? '')) !== '' ? $yazi['ozet_en'] : ($yazi['ozet'] ?? ''))));
    if ($ozet !== '') {
        $oBoy = ($olcu === 'yatay' ? 22 : ($olcu === 'kare' ? 26 : 27)) * $olcek;
        $satirSay = $olcu === 'yatay' ? 3 : ($olcu === 'kare' ? 9 : 8);
        $by += (int)($oBoy * 1.1);
        /* ince ayraç */
        imagefilledrectangle($im, $kenar, $by, $kenar + (int)(58 * $olcek), $by + max(2, (int)(3 * $olcek)), $kutAcik);
        $by += (int)($oBoy * 1.9);
        foreach (kt_sar($FU, $oBoy, $ozet, $ic, $satirSay) as $s4) { kt_yaz($im, $FU, $oBoy, $kenar, $by, $sonuk, $s4); $by += (int)($oBoy * 1.55); }
    }
}

/* ---- Alt bilgi: kalıcı adres ---- */
$altY = $Y - (int)round(($olcu === 'hikaye' ? 120 : 54) * $olcek);
imagefilledrectangle($im, $kenar, $altY - (int)(46 * $olcek), $kenar + (int)(58 * $olcek), $altY - (int)(43 * $olcek), $kutAcik);
$aBoy = ($olcu === 'yatay' ? 21 : 26) * $olcek;
$kod = trim((string)($yazi['bcid'] ?? ''));
$adres = preg_replace('#^https?://#', '', tg_kok()) . ($kod !== '' ? ('/' . trim((string)tg_ayar('tamga_yol', 'tamga'), '/') . '/' . $kod) : '');
kt_yaz($im, $FU, $aBoy, $kenar, $altY, $sonuk, $adres);
/* Sağ alt köşe işarete ayrıldı; bu satır adresin altına iner. */
$sagMetin = k_c('Ücretsiz, serbestçe paylaşılır', 'Free to read and share');
kt_yaz($im, $FU, $aBoy * 0.86, $kenar, $altY + (int)($aBoy * 1.5), $sonuk, $sagMetin);

/* ---- Alt köşe işareti ----
   Bir süre burada işaretin devasa ve soluk bir kopyası duruyordu;
   arkasındaki lacivert levha gri bir kutu gibi görünüyor ve kartın
   sağ yarısını kaplıyordu. Bunun yerine işaret, kartın sağ alt
   köşesinde küçük ve tam renkli duruyor: bir filigran değil, bir
   mühür. Kimlik, silikleştirilerek değil yerinde durarak taşınır. */
if ($tamgaIm) {
    $mBoy2 = (int)round(min($G, $Y) * ($olcu === 'yatay' ? 0.115 : 0.10));
    $mx = $G - $kenar - $mBoy2;
    $my = $Y - (int)round(($olcu === 'hikaye' ? 120 : 54) * $olcek) - (int)($mBoy2 * 0.78);
    imagealphablending($im, true);
    imagecopyresampled($im, $tamgaIm, $mx, $my, 0, 0, $mBoy2, $mBoy2, imagesx($tamgaIm), imagesy($tamgaIm));
    imagedestroy($tamgaIm);
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Content-Disposition: inline; filename="kutadgu-' . ($kod !== '' ? $kod : $slug) . '-' . $olcu . '.png"');
imagepng($im, null, 6);
imagedestroy($im);
