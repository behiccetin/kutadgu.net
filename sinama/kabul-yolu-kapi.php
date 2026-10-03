<?php
/* =====================================================================
   KABUL EDİLEN ÇALIŞMA HANGİ YOLA GİRER · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 15 Ağustos 2026. Kural iki yerde yazılıydı ve ikisi de
   "kabul edilen çalışma ÖNCE HAKEMSİZ yayımlanır" diyordu; kod ise
   'tur' => 'hakemli' yazıyordu. Kabul edilen her çalışma hakemli yola
   giriyor, sayfasında "Hakem aranıyor" rozeti beliriyordu.

   Bu kapı METİN İLE DAVRANIŞIN AYNI ŞEYİ SÖYLEDİĞİNİ ölçer. İkisinden
   birini değiştiren, ötekini de değiştirmek zorunda kalsın diye vardır.

   Kullanım: KUTADGU_DATA=<veri> php kabul-yolu-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
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

$api = (string)@file_get_contents($KOD . '/api/index.php');
$bsv = (string)@file_get_contents($KOD . '/basvuru.php');
$ort = (string)@file_get_contents($KOD . '/ortak.php');
den('kaynaklar okunabildi', $api !== '' && $bsv !== '' && $ort !== '');

echo "\n== 1. Tek kaynak ==\n";
den('tg_kabul_yolu() var', function_exists('tg_kabul_yolu'));
$yol = function_exists('tg_kabul_yolu') ? tg_kabul_yolu() : '';
olc('bugünkü yol: ' . $yol . ' (doktora şartı: ' . (tg_yazarlik_doktora_sarti() ? 'açık' : 'kapalı') . ')');
den('  yalnız iki değerden biri', in_array($yol, ['yazi', 'hakemli'], true), $yol);
den('  doktora şartıyla tutarlı',
    $yol === (tg_yazarlik_doktora_sarti() ? 'hakemli' : 'yazi'));

echo "\n== 2. Uç kuralı UYGULUYOR mu ==\n";
$b = strpos($api, "\$yol === '/yonetim/basvuru-karar'");
$son = $b === false ? false : strpos($api, "\$yol === '/yonetim/basvuru-sil'", $b);
if ($son === false && $b !== false) $son = $b + 6000;
$blok = ($b !== false) ? substr($api, $b, $son - $b) : '';
den('basvuru-karar bölümü bulundu', $blok !== '', (string)strlen($blok));
den('  yol tek kaynaktan okunuyor', str_contains($blok, "'tur' => tg_kabul_yolu()"));
den('  ELLE yazılmış hakemli kalmadı', !preg_match("#'tur'\s*=>\s*'hakemli'#", $blok));

echo "\n== 3. Kapalı düzen editörü durdurmuyor ==\n";
/* Destekleyen araştırmacı düzeni kapalıyken, eski başvurulardan kalmış
   yarım kayıtlar yüzünden çalışma kabul edilememeliydi — edilemiyordu. */
den('kefil denetimi düzenin açık olmasına bağlı',
    (bool)preg_match('#if \(tg_destek_duzeni_acik\(\)\) \{#', $blok));
den('  düzen bugün kapalı', tg_destek_duzeni_acik() === false);

echo "\n== 4. Metin ile davranış aynı şeyi söylüyor ==\n";
/* Duyurulan cümle: gönderim koşullarında ve yazarlık koşulu metninde. */
$kosul = function_exists('tg_yazarlik_kosulu_metni') ? tg_yazarlik_kosulu_metni(false) : '';
olc('koşul metni: ' . mb_substr($kosul, 0, 90) . '...');
$hakemsizDiyor = (bool)preg_match('/hakemsiz olarak yayımlanır|hakemsiz yayımlanır/u', $kosul);
$formDiyor     = (bool)preg_match('/önce hakemsiz olarak yayımlanır/u', $bsv);
if ($yol === 'yazi') {
    den('koşul metni "hakemsiz yayımlanır" diyor ve uç da öyle yapıyor', $hakemsizDiyor);
    den('  başvuru formundaki cümle de aynı', $formDiyor);
} else {
    den('koşul metni hakemsiz yayımdan SÖZ ETMİYOR (yol hakemli)', !$hakemsizDiyor);
    den('  başvuru formu da etmiyor', !$formDiyor);
}

echo "\n== 5. Yayımlandıktan sonra hakeme açılabiliyor ==\n";
/* Kuralın ikinci yarısı: "yazar dilerse hakem aranmasını ister." */
den('/yazar-hakemlige-ac ucu var', str_contains($api, "\$yol === '/yazar-hakemlige-ac'"));
den('  geri çekilmiş çalışma açılamıyor', str_contains($api, 'Geri çekilmiş bir çalışma hakemliğe açılamaz'));
den('  hakem kaydı düşmüşse geri kapatılamıyor',
    str_contains($api, 'artık hakemlikten geri çekilemez'));
/* Aşama sözlüğü hakemsiz yolu tanıyor mu: tanımasaydı yayımlanan
   çalışma rozetsiz kalırdı. */
$sahte = ['tur' => 'yazi', 'hakemler' => []];
den('hakemsiz çalışmanın aşaması "yok"', tg_hakem_asamasi($sahte) === 'yok', tg_hakem_asamasi($sahte));
$sahte2 = ['tur' => 'hakemli', 'hakemler' => []];
den('  hakemliğe açılınca "aranan" oluyor', tg_hakem_asamasi($sahte2) === 'aranan', tg_hakem_asamasi($sahte2));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
