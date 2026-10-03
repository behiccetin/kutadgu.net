<?php
/* =====================================================================
   SİSTEM DİLİNİN ZİYARETÇİYE GÖRE ÇÖZÜLMESİ: kapı ölçümü. Depoya girmez.

   Devir belgesi 7.2: "Zincir zaten var: ?lang= -> kdil çerezi ->
   CF-IPCOUNTRY -> Accept-Language -> varsayılan. Yapılacak: zinciri
   ÖLÇEN bir kapı yaz (şu an ölçülmüyor) ve makale dili açılır
   menüsünün sistemde bulunan dilleri gösterdiğini doğrula."

   Bu zincir sistemin en sessiz parçasıdır: yanlış çözülürse hiçbir
   hata çıkmaz, yalnız okur kendi dilinde olmayan bir sayfa görür ve
   bunu sisteme bildirmez. Ölçülmemiş kod, üstünde durulmamış koddur.

   ÖLÇÜLEN
     1. Zincirin BASAMAK SIRASI: her basamak bir üstündekine yeniliyor mu.
     2. Her basamağın kendi başına doğru çalışması.
     3. Bozuk/uydurma değerlerin zinciri kırmaması.
     4. Ülke bilgisinin TEK KAYNAKTAN okunması: k_ulke() ile geo_al()
        aynı başlığı iki ayrı yerde çözmemeli. (Devir belgesi 7.2'nin
        "Dikkat" notu tam olarak budur.)
     5. Çalışma dilleri menüsünün sistemdeki listeyi göstermesi ve
        arayüz dillerinin o listenin içinde bulunması.
     6. Sayfanın <html lang> ve dir'inin çözülen dille aynı olması —
        yanlış lang, ekran okuyucuya metni yanlış seslendirtir.

   ÖLÇÜM TUZAKLARI (yaşanmış)
     - OKUBENI 6/25: yerel curl zincirin SONUNA düşer ve o 'en'dir.
       Türkçe metin ölçülecekse dil açıkça istenmeli.
     - Çerez ile ?lang= aynı istekte gönderilirse ?lang= kazanmalı;
       ölçüm ikisini birden göndermeden "sıra doğru" diyemez.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php dil-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
$LOG  = getenv('KLOG') ?: '/home/claude/kg/sunucu.log';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';
/* k_dil/k_ulke k/kabuk.php'de; ölçüm ikisini de doğrudan çağırır. */
require_once $KOD . '/k/kabuk.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }

/* Bir istek; başlıklar dışarıdan verilir ki zincirin her basamağı ayrı
   ayrı ve birlikte sınanabilsin. */
function ist(string $yol, array $baslik = []): array {
    global $PORT;
    $b = ['CF-Connecting-IP: ' . ip()];
    foreach ($baslik as $ad => $deger) $b[] = $ad . ': ' . $deger;
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, stream_context_create(['http' => [
        'method' => 'GET', 'header' => implode("\r\n", $b), 'ignore_errors' => true, 'timeout' => 30]]));
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
}

/* Sayfanın kendi bildirdiği dil. Metinden tahmin etmiyoruz: sayfanın
   <html lang> özniteliği, sistemin "ben bu dildeyim" dediği yerdir ve
   ekran okuyucunun okuduğu da odur. */
function dil(array $y): string {
    return preg_match('#<html[^>]*\blang="([a-zA-Z-]+)"#i', $y['govde'], $m) ? strtolower($m[1]) : '';
}
function yon(array $y): string {
    return preg_match('#<html[^>]*\bdir="([a-z]+)"#i', $y['govde'], $m) ? strtolower($m[1]) : '';
}

function kodsuz(string $dosya): string {
    $ham = (string)@file_get_contents($dosya);
    if ($ham === '') return '';
    $c = '';
    foreach (token_get_all($ham) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { $c .= ' '; continue; }
            $c .= $t[1];
        } else $c .= $t;
    }
    return $c;
}

$logOnce = (int)@filesize($LOG);
$YOL = '/nasil-isler.php';       /* iki dilde de var olan, sabit bir sayfa */

/* =====================================================================
   1. ZİNCİRİN BASAMAKLARI TEK TEK
   ===================================================================== */
echo "== 1. Basamaklar tek tek ==\n";

$a = ist($YOL . '?lang=tr');
den('1. basamak: ?lang=tr Türkçe veriyor', dil($a) === 'tr', dil($a));
$a = ist($YOL . '?lang=en');
den('1. basamak: ?lang=en İngilizce veriyor', dil($a) === 'en', dil($a));

$a = ist($YOL, ['Cookie' => 'kdil=tr']);
den('2. basamak: kdil çerezi Türkçe veriyor', dil($a) === 'tr', dil($a));
$a = ist($YOL, ['Cookie' => 'kdil=en']);
den('2. basamak: kdil çerezi İngilizce veriyor', dil($a) === 'en', dil($a));

$a = ist($YOL, ['CF-IPCountry' => 'TR']);
den('3. basamak: ülke TR Türkçe veriyor', dil($a) === 'tr', dil($a));
$a = ist($YOL, ['CF-IPCountry' => 'DE']);
den('3. basamak: ülke DE varsayılana (İngilizce) düşüyor', dil($a) === 'en', dil($a));
/* Ayar dosyasında 'tr' ülkeleri ['TR','CY']; ikinci ülke de işlemeli,
   yoksa liste var gibi görünüp tek ülke çalışıyor demektir. */
$trUlke = (array)((tg_ayar('diller', [])['tr']['ulke']) ?? []);
olc('tr için tanımlı ülkeler: ' . implode(',', $trUlke));
if (count($trUlke) > 1) {
    $a = ist($YOL, ['CF-IPCountry' => (string)$trUlke[1]]);
    den('  listedeki İKİNCİ ülke de Türkçe veriyor (' . $trUlke[1] . ')', dil($a) === 'tr', dil($a));
} else {
    /* Ölçülecek veri yoksa deneme ÖLÇÜM olarak yazılır, GECTI olarak
       değil (OKUBENI 34). */
    olc('tr için tek ülke tanımlı; ikinci ülke denemesi yapılamadı');
}

$a = ist($YOL, ['Accept-Language' => 'tr-TR,tr;q=0.9,en;q=0.8']);
den('4. basamak: tarayıcı dili Türkçe veriyor', dil($a) === 'tr', dil($a));
/* Bu deneme eskiden "de" örneğiyle yazılmıştı ve "bilinmeyen tarayıcı
   dili varsayılana düşüyor" diyordu. Almanca artık TANIMLI bir dildir
   (ayar.php 'diller'), yani örnek geçersiz kaldı: ölçüm değişmedi,
   ölçülen dünya değişti. İki deneme birden yazılır, çünkü ikisi ayrı
   şeyi söyler ve biri ötekinin yerini tutmaz:
     - tanımlı bir tarayıcı dili o dile gidiyor mu,
     - GERÇEKTEN tanımsız olan bir dil varsayılana düşüyor mu.
   Tanımsız örnek olarak Japonca seçildi; tanımlı bir dile dönerse bu
   satır da yeniden yazılır. */
$a = ist($YOL, ['Accept-Language' => 'de-DE,de;q=0.9,en;q=0.8']);
den('4. basamak: tanımlı tarayıcı dili (de) Almanca veriyor', dil($a) === 'de', dil($a));
$a = ist($YOL, ['Accept-Language' => 'ja-JP,ja;q=0.9']);
den('4. basamak: tanımsız tarayıcı dili varsayılana düşüyor', dil($a) === 'en', dil($a));

$a = ist($YOL);
$vars = (string)tg_ayar('dil_varsayilan', 'en');
den('5. basamak: hiçbir ipucu yokken varsayılan (' . $vars . ')', dil($a) === $vars, dil($a));

/* =====================================================================
   2. SIRA: HANGİ BASAMAK HANGİSİNİ YENİYOR
   ---------------------------------------------------------------------
   Basamakların tek tek çalışması yetmez; asıl mesele sıradır. Bir
   ziyaretçi dili elle seçtiyse ülkesi onu geri alamamalıdır: seçim,
   tahminin üstündedir.
   ===================================================================== */
echo "\n== 2. Basamak sırası ==\n";

$a = ist($YOL . '?lang=en', ['Cookie' => 'kdil=tr']);
den('?lang= çerezi yeniyor', dil($a) === 'en', dil($a));
$a = ist($YOL . '?lang=tr', ['CF-IPCountry' => 'DE', 'Accept-Language' => 'de-DE']);
den('?lang= ülkeyi ve tarayıcı dilini yeniyor', dil($a) === 'tr', dil($a));
$a = ist($YOL, ['Cookie' => 'kdil=tr', 'CF-IPCountry' => 'DE']);
den('çerez ülkeyi yeniyor (elle seçim tahmine üstün)', dil($a) === 'tr', dil($a));
$a = ist($YOL, ['Cookie' => 'kdil=en', 'CF-IPCountry' => 'TR']);
den('  tersi de doğru: çerez en, ülke TR iken İngilizce', dil($a) === 'en', dil($a));
$a = ist($YOL, ['CF-IPCountry' => 'TR', 'Accept-Language' => 'de-DE,de;q=0.9']);
den('ülke tarayıcı dilini yeniyor', dil($a) === 'tr', dil($a));

/* =====================================================================
   3. BOZUK DEĞERLER ZİNCİRİ KIRMIYOR
   ===================================================================== */
echo "\n== 3. Bozuk değerler ==\n";

$a = ist($YOL . '?lang=zz', ['CF-IPCountry' => 'TR']);
den('tanınmayan ?lang= sessizce bir alt basamağa düşüyor', dil($a) === 'tr', dil($a));
$a = ist($YOL . '?lang=' . urlencode('"><script>'), ['CF-IPCountry' => 'TR']);
den('uydurma ?lang= sayfaya basılmıyor',
    dil($a) === 'tr' && strpos($a['govde'], '<script>"') === false, dil($a));
$a = ist($YOL, ['Cookie' => 'kdil=zz', 'CF-IPCountry' => 'TR']);
den('tanınmayan çerez sessizce ülkeye düşüyor', dil($a) === 'tr', dil($a));
$a = ist($YOL, ['CF-IPCountry' => 'TÜRKİYE']);
den('iki harfli olmayan ülke yok sayılıyor', dil($a) === $vars, dil($a));
$a = ist($YOL, ['CF-IPCountry' => 'tr']);
den('küçük harfli ülke kodu da tanınıyor', dil($a) === 'tr', dil($a));

/* =====================================================================
   4. ÜLKE BİLGİSİ TEK KAYNAKTAN OKUNUYOR MU
   ---------------------------------------------------------------------
   Devir belgesi 7.2, "Dikkat": CF-IPCOUNTRY artık geo_al() içinde de
   kullanılıyor; ikisi tek kaynaktan okumalı. Aynı bilgi iki yerde
   çözülürse zamanla ayrışır: biri küçük harfi kabul eder öteki etmez,
   biri yedek başlığa bakar öteki bakmaz ve sistem okura bir şey,
   kaydına başka bir şey yazar.
   ===================================================================== */
echo "\n== 4. Ülke: tek kaynak ==\n";

$apiKod = kodsuz($KOD . '/api/index.php');
$kabukKod = kodsuz($KOD . '/k/kabuk.php');
$ortakKod = kodsuz($KOD . '/ortak.php');
den('tg_ulke() var (ülkeyi çözen tek işlev)', function_exists('tg_ulke'));
den('  k_ulke() onun takma adı, ikinci bir çözüm değil',
    function_exists('k_ulke') && substr_count($kabukKod, 'HTTP_CF_IPCOUNTRY') === 0,
    (string)substr_count($kabukKod, 'HTTP_CF_IPCOUNTRY'));
den('  başlığı okuyan tek yer ortak.php',
    substr_count($ortakKod, 'HTTP_CF_IPCOUNTRY') === 1, (string)substr_count($ortakKod, 'HTTP_CF_IPCOUNTRY'));
den('geo_al() başlığı KENDİ BAŞINA çözmüyor, tek kaynağı çağırıyor',
    substr_count($apiKod, 'HTTP_CF_IPCOUNTRY') === 0
    && (bool)preg_match('#function geo_al\([^)]*\)[^{]*\{[^}]{0,600}tg_ulke\(\)#s', $apiKod),
    'api içinde HTTP_CF_IPCOUNTRY: ' . substr_count($apiKod, 'HTTP_CF_IPCOUNTRY'));
/* Aynı girdiye iki yol aynı yanıtı vermeli; ölçülen şey adların
   aynılığı değil, DAVRANIŞIN aynılığı. */
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'de';
den('  sayfa katmanı ile API aynı ülkeyi görüyor', k_ulke() === tg_ulke() && tg_ulke() === 'DE', k_ulke() . '/' . tg_ulke());
/* İkisi aynı girdiye aynı yanıtı vermeli. */
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'tr';
den('  k_ulke() küçük harfi büyütüyor', k_ulke() === 'TR', k_ulke());
$_SERVER['HTTP_CF_IPCOUNTRY'] = 'TURKIYE';
den('  k_ulke() iki harfli olmayanı yok sayıyor', k_ulke() === '', k_ulke());
unset($_SERVER['HTTP_CF_IPCOUNTRY']);

/* =====================================================================
   5. ÇALIŞMA DİLLERİ MENÜSÜ
   ---------------------------------------------------------------------
   Arayüzün dili iki tane; çalışmanın dili onlarca. Menü sistemdeki
   listeyi göstermeli, elle yazılmış kısa bir liste değil.
   ===================================================================== */
echo "\n== 5. Çalışma dilleri menüsü ==\n";

$cd = tg_calisma_dilleri();
olc('sistemde ' . count($cd) . ' çalışma dili tanımlı');
den('çalışma dili listesi boş değil', count($cd) >= 2, (string)count($cd));

$bv = ist('/basvuru.php?lang=tr');
den('başvuru sayfası 200', $bv['kod'] === 200, (string)$bv['kod']);
$secenek = [];
if (preg_match('#<select[^>]+id="mDil".*?</select>#s', $bv['govde'], $m)) {
    preg_match_all('#<option value="([^"]*)"#', $m[0], $mm);
    $secenek = $mm[1];
}
olc('menüde ' . count($secenek) . ' seçenek var');
den('menü sistemdeki BÜTÜN çalışma dillerini gösteriyor',
    $secenek !== [] && array_values(array_diff(array_keys($cd), $secenek)) === [],
    implode(',', array_slice(array_diff(array_keys($cd), $secenek), 0, 8)));
den('  menüde listede olmayan uydurma bir dil yok',
    $secenek !== [] && array_values(array_diff($secenek, array_keys($cd))) === [],
    implode(',', array_diff($secenek, array_keys($cd))));
den('  her dil KENDİ ADIYLA yazılı (Türkçe sayfada da)',
    strpos($bv['govde'], 'Deutsch') !== false && strpos($bv['govde'], 'Français') !== false);

/* Arayüz dilleri çalışma dilleri listesinin İÇİNDE olmalı: sistem
   kendi arayüz dilinde yazılmış bir çalışmayı kabul edemiyorsa,
   listeler ayrışmış demektir. */
$eksikArayuz = array_diff(array_keys(k_diller()), array_keys($cd));
den('arayüz dillerinin hepsi çalışma dili olarak da tanımlı',
    $eksikArayuz === [], implode(',', $eksikArayuz));

/* Menü, ziyaretçinin diline göre önceden seçili gelmeli: en sık
   karşılaşılan durum, insanın kendi arayüz dilinde yazmasıdır. */
den('menüde sayfanın dili önceden seçili',
    (bool)preg_match('#<option value="tr"[^>]*selected#', $m[0] ?? ''),
    substr((string)($m[0] ?? ''), 0, 80));
$bvEn = ist('/basvuru.php?lang=en');
preg_match('#<select[^>]+id="mDil".*?</select>#s', $bvEn['govde'], $m2);
den('  İngilizce sayfada İngilizce seçili',
    (bool)preg_match('#<option value="en"[^>]*selected#', $m2[0] ?? ''));

/* =====================================================================
   6. SAYFANIN KENDİ BİLDİRDİĞİ DİL VE YÖN
   ===================================================================== */
echo "\n== 6. lang ve dir ==\n";

$a = ist($YOL . '?lang=tr');
den('<html lang> çözülen dille aynı (tr)', dil($a) === 'tr', dil($a));
den('  yön ltr', yon($a) === 'ltr', yon($a));
$a = ist($YOL . '?lang=en');
den('<html lang> çözülen dille aynı (en)', dil($a) === 'en', dil($a));

/* Dil değiştirme bağlantısı sayfada olmalı: tahmin yanlışsa okurun
   düzeltebileceği bir yer bulunmalı, yoksa tahmin bir hüküm olur. */
/* OKUBENI 20: ögeyi METNİYLE değil DAVRANIŞIYLA ara. Dil değiştirici
   bir bağlantı metni değil, öteki dile götüren bir denetimdir; kimi
   sayfada iki dilli bir düğme, kimi sayfada açılır liste olur. */
$a = ist($YOL . '?lang=tr');
den('Türkçe sayfada İngilizceye götüren bir denetim var',
    (bool)preg_match('#data-dil="en"#', $a['govde']));
den('  betiksiz de çalışıyor (adresi olan bir bağlantı)',
    (bool)preg_match('#<a[^>]+data-dil="en"[^>]*href="[^"]*lang=en#', $a['govde'])
    || (bool)preg_match('#<a[^>]+href="[^"]*lang=en"[^>]*data-dil="en"#', $a['govde']));
$a = ist($YOL . '?lang=en');
den('İngilizce sayfada Türkçeye götüren bir denetim var',
    (bool)preg_match('#data-dil="tr"#', $a['govde']));
den('  betiksiz de çalışıyor',
    (bool)preg_match('#<a[^>]+data-dil="tr"[^>]*href="[^"]*lang=tr#', $a['govde'])
    || (bool)preg_match('#<a[^>]+href="[^"]*lang=tr"[^>]*data-dil="tr"#', $a['govde']));
den('arama motorlarına öteki dil hreflang ile bildiriliyor',
    (bool)preg_match('#hreflang="tr"#', $a['govde']) && (bool)preg_match('#hreflang="en"#', $a['govde']));

/* =====================================================================
   7. SUNUCU KÜTÜĞÜ
   ===================================================================== */
echo "\n== 7. Sunucu kütüğü ==\n";
$yeni = (string)@file_get_contents($LOG, false, null, $logOnce);
$uyari = preg_match_all('/warning|deprecated|notice|fatal/i', $yeni);
den('bu ölçüm sırasında sunucu uyarısı yok', $uyari === 0, (string)$uyari);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
