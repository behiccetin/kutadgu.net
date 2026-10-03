<?php
/* =====================================================================
   İSTEM PENCERELERİ · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi: "anahtar kelime
   olsun tam metin olsun bunları yz ile de yapabilir dedik ya, hatta
   istemde önerdik."

   Doğruydu ve eksiğin yeri belliydi. Gönderim formu künye dilindeki
   metni yapay zekâyla üretmeyi AÇIKÇA öneriyor ve hazır istemler
   veriyordu. Ama o alanların çoğu gönderim anında değil KABULDEN SONRA,
   yazar panelinde doldurulur: tam metin, anahtar kelimeler, kaynakça.
   Panelde ne öneri vardı ne istem. Sistem, bir yerde verdiği yardımı,
   o yardımın asıl gerektiği yerde vermiyordu.

   İkinci eksik: istem takımı başlığı, özü ve tam metni kapsıyordu ama
   ANAHTAR KELİMEYİ kapsamıyordu — oysa kurul bildiriminde adıyla
   geçen alan oydu.

   BU KAPININ ASIL İŞİ TEK KAYNAĞI KORUMAKTIR. İstemler iki yüz satır
   metindir; panele kopyalansaydı aynı metin iki yerde durur ve biri bir
   gün ötekinden habersiz değişirdi. Kapı, iki sayfanın da AYNI
   kaynaktan bastığını ölçer.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php istem-kapi.php
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

$mod = (string)@file_get_contents($KOD . '/k/istem.php');
$bsv = (string)@file_get_contents($KOD . '/basvuru.php');
$yzr = (string)@file_get_contents($KOD . '/yazar.php');
den('kaynaklar okunabildi', $mod !== '' && $bsv !== '' && $yzr !== '');

/* ------------------------------------------------------------------ */
echo "\n== 1. TEK KAYNAK: istemler bir yerde yazılı ==\n";
den('k/istem.php var', $mod !== '', (string)strlen($mod));
foreach (['k_istem_stil', 'k_istem_ceviri', 'k_istem_betik'] as $f) {
    den('  ' . $f . '() tanımlı', str_contains($mod, 'function ' . $f . '('));
}
foreach ([['basvuru.php', $bsv], ['yazar.php', $yzr]] as [$ad, $k]) {
    den('  ' . $ad . ' modülü çağırıyor', str_contains($k, "require_once __DIR__ . '/k/istem.php';"));
    den('    pencereyi kendi yazmıyor', !str_contains($k, '<div class="mod-ov" id="cvOv">'));
    den('    biçimi de kendi yazmıyor', !str_contains($k, '.pr-metin{padding:'));
}
/* İstem METİNLERİ yalnız modülde durmalı; bir sayfaya kopyalandığı gün
   ikisi ayrılır. Ölçüt istemin ayırt edici bir cümlesidir. */
$iz = 'KAYNAKÇAYA VE ATIFLARA DOKUNMA';
olc('istem metninin izi: "' . $iz . '"');
den('istem metni yalnız modülde', str_contains($mod, $iz));
den('  basvuru.php\'de kopyası yok', !str_contains($bsv, $iz));
den('  yazar.php\'de kopyası yok', !str_contains($yzr, $iz));

/* ------------------------------------------------------------------ */
echo "\n== 2. Bileşen tek başına geçerli döner ==\n";
/* ÖLÇÜLEN KUSUR: k_istem_betik() çıplak JS döndürüyordu ve çağıran
   sayfanın kendi betiği zaten </script> ile bittiği için düzenek METİN
   OLARAK sayfaya düşüyordu. Düğme görünüyor, tıklanıyor, pencere
   açılmıyordu. Bir bileşenin döndürdüğü şey tek başına geçerli
   olmalıdır. */
den('betik kendi <script> kabuğunu taşıyor',
    str_contains($mod, "return <<<JS\n<script>") && str_contains($mod, "</script>\nJS;"));
den('  betik kendi dizelerini taşıyor (çağıranın sözlüğüne bağlı değil)',
    !str_contains($mod, 'S.kopyala') && str_contains($mod, 'KOPYALANDI'));
/* ÖLÇÜLEN KUSUR (ikinci): biçim, basvuru.php'de bir HEREDOC'un içine
   "<?= k_istem_stil() ?>" diye yazılmıştı. Heredoc işlev çağırmaz; o
   satır METİN olarak sayfaya düştü ve pencerenin biçimi büsbütün
   kayboldu (kutu 820 yerine 1175 piksel açıldı). bosluk-kapi bunu
   yakaladı — ama biçimin yokluğunu değil, yokluğunun SONUCUNU gördü.
   Bu ölçüm nedeni doğrudan arar. */
foreach ([['/basvuru.php', 'gönderim formu'], ['/yazar.php', 'yazar paneli']] as [$yol, $ad]) {
    $s2 = (string)@file_get_contents('http://127.0.0.1:' . $PORT . $yol . '?lang=tr');
    den('  ' . $ad . ' biçimi GERÇEKTEN basıyor', str_contains($s2, '.mod-ov{position:fixed'));
    den('    ve sayfada çiğ işlev çağrısı yok', !str_contains($s2, 'k_istem_stil()'));
}

/* ------------------------------------------------------------------ */
echo "\n== 3. Anahtar kelime istemi var ==\n";
den('beşinci istem anahtar kelime', str_contains($mod, "k_c('Anahtar kelime önerisi', 'Suggesting keywords')"));
den('  UYDURMAYI yasaklıyor', str_contains($mod, 'HER TERİMİN METİNDE KARŞILIĞI OLMALI'));
den('  ve İngilizcesi de yasaklıyor', str_contains($mod, 'EVERY TERM MUST HAVE A BASIS IN THE TEXT'));
den('  kaynağını göstermesini istiyor', str_contains($mod, 'hangi bölümünden geldiğini'));

/* ------------------------------------------------------------------ */
echo "\n== 4. İki sayfa da AYNI pencereyi basıyor ==\n";
$say = [];
foreach ([['/basvuru.php', 'gönderim formu'], ['/yazar.php', 'yazar paneli']] as [$yol, $ad]) {
    $s = (string)@file_get_contents('http://127.0.0.1:' . $PORT . $yol . '?lang=tr');
    den($ad . ' açıldı', $s !== '', $yol);
    if ($s === '') continue;
    den('  ' . $ad . ' çeviri penceresini basıyor', str_contains($s, 'id="cvOv"'));
    den('  ' . $ad . ' istem açma düğmesi taşıyor', str_contains($s, 'id="cvAc"'));
    den('  ' . $ad . ' düzeneği basıyor', str_contains($s, 'var ESLES ='));
    $say[$ad] = substr_count($s, 'class="pr-bas"');
    /* Düzenek <script> içinde olmalı; çıplak düşerse sayfada METİN
       olarak görünür ve okur onu okur. */
    den('  ' . $ad . ' düzeneği <script> içinde',
        (bool)preg_match('#<script>\s*\(function\(\)\{\s*var ESLES#', $s));
}
olc('çeviri penceresindeki istem sayısı: ' . json_encode($say, JSON_UNESCAPED_UNICODE));
den('yazar panelinde beş çeviri istemi var', ($say['yazar paneli'] ?? 0) === 5, (string)($say['yazar paneli'] ?? 0));
den('  gönderim formunda beşi de var (artı denetim istemleri)',
    ($say['gönderim formu'] ?? 0) >= 5, (string)($say['gönderim formu'] ?? 0));

/* ------------------------------------------------------------------ */
echo "\n== 5. Yardım DUYURULUYOR ve beyan isteniyor ==\n";
$sy = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/yazar.php?lang=tr');
den('panel yapay zekâdan yardım alınabileceğini söylüyor',
    str_contains($sy, 'yapay zekâdan yardım alarak da doldurabilirsiniz'));
/* Yardımı önerip beyanı istememek, "kullanım yasak değil ama beyan
   edilmemesi kabul edilemez" kuralını duyurup uygulamamak olurdu. */
den('  aynı yerde BEYAN da isteniyor', str_contains($sy, 'beyan edilmeyen kullanım kabul edilemez'));
den('  beyanın yeri gösteriliyor (çeviri künyesi)', str_contains($sy, 'çeviri künyesinde'));
den('  çeviri künyesi gerçekten sayfada', str_contains($sy, 'id="ceviri_kaynak"'));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
