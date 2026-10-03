<?php
/* =====================================================================
   BİLİM ALANINA GÖRE ARAMA: kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL İSTEĞİ (M. Z. Tunca, iki kez): "alanlar doğrultusunda arama".
   BİLDİRİLEN KUSUR: "ana sayfada arama da alanlar tam gözükmüyor,
   işlevsiz duruyor gibi; birisi gelip herhangi bir bilim dalında arama
   yapabilmeli."

   ÖLÇÜLDÜ, ÜÇ AYRI KUSUR ÇIKTI:

   1. SÜZGEÇ YANLIŞ SORUYU SORUYORDU. ar_suz() ve yazilar.php
      al_yakinlik(...) >= 2 istiyordu. Oysa al_yakinlik() hakem
      eşleştirmesi için yazılmıştır, "bu kayıt seçilenin altında mı"
      sorusunu yanıtlamaz:
          al_yakinlik('5',       '5.2.001') = 1  -> temel alan seçen
                                                    SIFIR sonuç görüyordu
          al_yakinlik('5.2.001', '5.2.007') = 2  -> "Makro iktisat" seçen
                                                    "Mikro iktisat"ı da alıyordu
      Süzgeç aynı anda hem dar hem gevşekti. Doğru soru KAPSAMADIR ve
      tek yönlüdür: al_kapsar().

   2. SEÇİCİ TEMEL ALANI HİÇ BASMIYORDU. Yedi temel alan yalnızca
      optgroup ETİKETİ olarak geçiyordu; seçenek olarak yoktu. Ana
      sayfadaki satır /yazilar.php?alan=5 adresine gidiyor, sayfa
      süzüyor, ama seçicide seçili görünen satır olmadığı için okur
      "süzgeç çalışmadı" diye okuyordu. Dahası yazilar.php'nin kendi
      açıklaması "yedi temel alan listelenir" DİYORDU. Bu deponun en sık
      kusuru yine buydu: sistem, uygulamadığı bir kuralı duyuruyor.

   3. BASAMAK ADLARI KARIŞMIŞTI. Merdiven üç basamaklıdır ve YÖK/FORD
      adları şudur: temel alan (7) > bilim alanı (42) > bilim dalı (189).
      Ana sayfa satırının adı "Bilim dallarına göre"ydi ama satırda dal
      yoktu. Adların tek kaynağı artık al_duzey_ad().

   BU KAPI DAVRANIŞI ÖLÇER, ETİKETİ DEĞİL. Seçicinin dolu olması yetmez;
   seçilen kodun gerçekten doğru kümeyi getirmesi ve getirmemesi gereken
   kardeşi GETİRMEMESİ ayrı ayrı sınanır.

   Kullanım:
     KTEST_DIR=<kod> KUTADGU_DATA=<veri> KPORT=<kapı> php alan-arama-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD    = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI   = getenv('KUTADGU_DATA') ?: '';
$PORT   = getenv('KPORT') ?: '8941';
$KAYNAK = '/home/claude/kg/kutadgunet';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';
require_once $KOD . '/k/alanlar.php';
require_once $KOD . '/k/veri.php';   /* k_yazilar(): sayfaların okuduğu liste */

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }
function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }

function ist(string $yol): array {
    global $PORT;
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, stream_context_create(['http' => [
        'method' => 'GET', 'header' => 'CF-Connecting-IP: ' . ip(), 'ignore_errors' => true, 'timeout' => 30]]));
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
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

/* Bir seçicinin içindeki value'lar. Seçicinin adı id ile bulunur;
   "kaç seçenek var" sorusu ancak DOĞRU seçicide sorulursa anlamlıdır —
   sayfada birden çok select vardır (sırala, anahtar sözcük, tür). */
function secenekler(string $govde, string $id): array {
    if (!preg_match('#<select[^>]*id="' . preg_quote($id, '#') . '"[^>]*>(.*?)</select>#su', $govde, $m)) return [];
    preg_match_all('#<option value="([^"]*)"#', $m[1], $o);
    return $o[1];
}
function gruplar(string $govde, string $id): int {
    if (!preg_match('#<select[^>]*id="' . preg_quote($id, '#') . '"[^>]*>(.*?)</select>#su', $govde, $m)) return -1;
    return substr_count($m[1], '<optgroup');
}
function secili(string $govde, string $id): string {
    if (!preg_match('#<select[^>]*id="' . preg_quote($id, '#') . '"[^>]*>(.*?)</select>#su', $govde, $m)) return '#SEÇİCİ YOK#';
    return preg_match('#<option value="([^"]*)"[^>]*\bselected#', $m[1], $s) ? $s[1] : '';
}
/* Sonuç kartı sayısı. Sayfanın kendi bildirdiği sayıya değil, çizilen
   karta bakılır: "30 sonuç" yazıp sıfır kart çizen bir sayfa da
   kusurludur ve o kusur yalnız kart sayılırsa görünür.

   ÖLÇÜM DÜZELTMESİ: ilk yazımda 'class="kart' sayılıyordu ve sayı bir
   fazla çıkıyordu — sayfadaki bilgi kutusu (ar-kutu) da bir karttır.
   Sonuç kartının sınıfı ara.php'de 'ar-k', yazilar.php'de 'yk'dir;
   sayılan yalnız bu ikisidir. */
function kart(string $govde): int {
    return substr_count($govde, 'class="kart ar-k"') + substr_count($govde, 'class="kart kart-t yk"');
}

/* =====================================================================
   1. KAPSAMA — SÜZGECİN SORDUĞU SORU
   ===================================================================== */
echo "== 1. Kapsama kuralı ==\n";
den('al_kapsar() var', function_exists('al_kapsar'));
den('al_kayit_kapsam() var', function_exists('al_kayit_kapsam'));

den('temel alan kendi dalını kapsar (5 > 5.2.001)', al_kapsar('5', '5.2.001'));
den('temel alan kendi bilim alanını kapsar (5 > 5.2)', al_kapsar('5', '5.2'));
den('bilim alanı kendi dalını kapsar (5.2 > 5.2.001)', al_kapsar('5.2', '5.2.001'));
den('kod kendini kapsar (5.2 > 5.2)', al_kapsar('5.2', '5.2'));

/* Kapsama TEK YÖNLÜDÜR. Bu üçü, eski al_yakinlik() süzgecinin
   geçirdiği ve geçirmemesi gereken durumlardır. */
den('kardeş kardeşi KAPSAMAZ (5.2.001 > 5.2.007 değil)', !al_kapsar('5.2.001', '5.2.007'));
den('alt üstü KAPSAMAZ (5.2.001 > 5 değil)', !al_kapsar('5.2.001', '5'));
den('bilim alanı temel alanı KAPSAMAZ (5.2 > 5 değil)', !al_kapsar('5.2', '5'));
den('yabancı temel alan kapsamaz (1 > 5.2.001 değil)', !al_kapsar('1', '5.2.001'));
den('boş kod kimseyi kapsamaz', !al_kapsar('', '5.2.001') && !al_kapsar('5', ''));

/* Aradisipliner dal: birden çok alt alana bağlıdır ve HER BİRİNİN
   altında bulunmalıdır. Tek üst üzerinden yürüyen bir kapsama bunu
   kaçırır ve o dalı arayanlardan gizler. */
$aradal = ''; $aradalUstler = [];
foreach (array_keys(al_dallar(false)) as $dk) {
    $u = al_ustler((string)$dk);
    if (count($u) > 1) { $aradal = (string)$dk; $aradalUstler = $u; break; }
}
if ($aradal !== '') {
    olc('aradisipliner dal: ' . $aradal . ' -> ' . implode(', ', $aradalUstler));
    $hepsi = true;
    foreach ($aradalUstler as $u) if (!al_kapsar($u, $aradal)) $hepsi = false;
    den('aradisipliner dal her üstünün altında bulunur', $hepsi);
    $anaHepsi = true;
    foreach ($aradalUstler as $u) {
        $ana = explode('.', $u)[0];
        if (!al_kapsar($ana, $aradal)) $anaHepsi = false;
    }
    den('  ve her üstünün TEMEL alanının da altında', $anaHepsi);
} else {
    /* ÖLÇÜM DÜZELTMESİ: "aradisipliner dal yok, atlandı" demek burada
       yetmez. Aradisipliner dallar VERİ DİZİNİNDEN gelir (alanlar.json)
       ve üretimde bugün yok diye kural ölçülmeden kalırsa, ilk öneri
       onaylandığı gün kimse bakmayacak. Kapı kendi düzeneğini kurar:
       geçici bir veri dizini, içinde iki üstlü bir dal, ayrı bir süreç.
       Düzenek ölçüm biter bitmez silinir. */
    $tmp = sys_get_temp_dir() . '/kalan-' . getmypid();
    @mkdir($tmp, 0700, true);
    file_put_contents($tmp . '/alanlar.json', json_encode([
        '5.1.900' => ['tr' => 'Ölçüm dalı', 'en' => 'Measurement branch',
                      'ust' => ['6.4', '5.8'], 'durum' => 'onayli',
                      'ekleyen' => 'kapı', 'tarih' => '2026-08-14'],
    ], JSON_UNESCAPED_UNICODE));
    $betik = 'require ' . var_export($KOD . '/ortak.php', true) . ';'
           . 'require ' . var_export($KOD . '/k/alanlar.php', true) . ';'
           . '$k="5.1.900";'
           . 'echo json_encode(["ustler"=>al_ustler($k),'
           . '"kendi"=>al_kapsar("5.1",$k),"ek1"=>al_kapsar("6.4",$k),"ek2"=>al_kapsar("5.8",$k),'
           . '"ana1"=>al_kapsar("6",$k),"ana2"=>al_kapsar("5",$k),"yabanci"=>al_kapsar("3",$k)]);';
    $cikti = (string)shell_exec('KUTADGU_DATA=' . escapeshellarg($tmp) . ' php -r ' . escapeshellarg($betik) . ' 2>/dev/null');
    @unlink($tmp . '/alanlar.json'); @rmdir($tmp);
    $s = json_decode($cikti, true);
    olc('düzenek: 5.1.900, ust=[6.4, 5.8] -> ' . trim($cikti));
    den('düzenek kuruldu ve dal okundu', is_array($s) && !empty($s['ustler']), $cikti);
    if (is_array($s)) {
        den('aradisipliner dal kendi üstünün altında', !empty($s['kendi']));
        den('  ve eklenen her üstünün altında', !empty($s['ek1']) && !empty($s['ek2']));
        den('  ve o üstlerin TEMEL alanlarının altında', !empty($s['ana1']) && !empty($s['ana2']));
        den('  ama yabancı bir temel alanın altında değil', empty($s['yabanci']));
    }
}

/* Kapsama, ESKİ kaba alan kodu taşıyan kayıtlarda da çalışmalı:
   hiçbir eski kayıt süzgeçten düşmez. */
den('eski kod taşıyan kayıt yeni süzgeçle bulunur',
    al_kayit_kapsam(['alan' => 'ikt'], '5') && al_kayit_kapsam(['alan' => 'ikt'], '5.2'));
den('  ve yanlış temel alana düşmez', !al_kayit_kapsam(['alan' => 'ikt'], '1'));

/* Süzgeç artık al_yakinlik() ÇAĞIRMIYOR: eski soru geri gelirse kapı
   görsün. al_yakinlik() yaşamayı sürdürür, ama yeri hakem havuzudur. */
$aramaKod  = kodsuz($KAYNAK . '/k/arama.php');
$yazilarKod = kodsuz($KAYNAK . '/yazilar.php');
den('ar_suz() kapsamayı kullanıyor', str_contains($aramaKod, 'al_kayit_kapsam'));
den('  ve al_yakinlik ile süzmüyor', !preg_match('/al_yakinlik\([^)]*\)\s*>=\s*2/', $aramaKod));
den('yazilar.php kapsamayı kullanıyor', str_contains($yazilarKod, 'al_kayit_kapsam'));
den('  ve al_yakinlik ile süzmüyor', !preg_match('/al_yakinlik\([^)]*\)\s*>=\s*2/', $yazilarKod));

/* =====================================================================
   2. BASAMAK ADLARI TEK KAYNAKTAN
   ===================================================================== */
echo "\n== 2. Basamak adları ==\n";
den('al_duzey_ad() var', function_exists('al_duzey_ad'));
den('al_duzey() var', function_exists('al_duzey'));
den('al_secici_etiket() var', function_exists('al_secici_etiket'));

den('1. basamak: temel alan', al_duzey_ad(1, false) === 'temel alan', al_duzey_ad(1, false));
den('2. basamak: bilim alanı', al_duzey_ad(2, false) === 'bilim alanı', al_duzey_ad(2, false));
den('3. basamak: bilim dalı',  al_duzey_ad(3, false) === 'bilim dalı',  al_duzey_ad(3, false));
den('adlar İngilizcede başka', al_duzey_ad(2, true) !== al_duzey_ad(2, false));
den('  ve İngilizcesinde Türkçe kalıntı yok',
    !preg_match('/(alan|dal)/iu', al_duzey_ad(1, true) . al_duzey_ad(2, true) . al_duzey_ad(3, true)));

den('kodun basamağı doğru okunuyor (5)',       al_duzey('5') === 1);
den('  (5.2)',                                  al_duzey('5.2') === 2);
den('  (5.2.001)',                              al_duzey('5.2.001') === 3);

/* Merdivenin basamak sayıları: adlar doğru olsa da sayılar kaymışsa
   "yedi temel alan" cümlesi yine yalan olur. */
olc('temel alan: ' . count(al_ana()) . ' / bilim alanı: ' . count(al_alt()) . ' / bilim dalı: ' . count(al_dallar()));
den('yedi temel alan', count(al_ana()) === 7, (string)count(al_ana()));
den('kırk iki bilim alanı', count(al_alt()) === 42, (string)count(al_alt()));
den('bilim dalı sayısı yüzden çok', count(al_dallar()) > 100, (string)count(al_dallar()));

/* =====================================================================
   3. SEÇİCİ TEK KAYNAKTAN BASILIYOR
   ===================================================================== */
echo "\n== 3. Seçici tek kaynaktan ==\n";
den('al_secenek_html() var', function_exists('al_secenek_html'));
den('al_sayac() var', function_exists('al_sayac'));

$araKod = kodsuz($KAYNAK . '/ara.php');
den('ara.php seçiciyi tek kaynaktan basıyor', substr_count($araKod, 'al_secenek_html') >= 2);
den('yazilar.php seçiciyi tek kaynaktan basıyor', str_contains($yazilarKod, 'al_secenek_html'));
/* Elle yazılmış optgroup döngüsü kalmamalı: kalırsa üç seçici yeniden
   ayrışır ve biri temel alanı yine atlar. */
den('ara.php elle optgroup döngüsü yazmıyor', !preg_match('/foreach \(al_secim\(/', $araKod));
den('yazilar.php elle optgroup döngüsü yazmıyor', !preg_match('/foreach \(al_secim\(/', $yazilarKod));

$bekOpt = count(al_ana()) + count(al_alt()) + count(al_dallar());
$bekGrup = 1 + count(al_alt());
olc('beklenen seçenek (boş satır hariç): ' . $bekOpt . ' / küme: ' . $bekGrup);

/* =====================================================================
   4. SAYFALAR: SEÇİCİ GERÇEKTEN ÜÇ BASAMAĞI DA TAŞIYOR MU
   ===================================================================== */
echo "\n== 4. Sayfalardaki seçici ==\n";
foreach ([
    ['/yazilar.php', 'alanSec', 'arşiv'],
    ['/ara.php',     'tAlan',   'temel arama'],
    ['/ara.php?g=1', 'sAlan',   'gelişmiş arama'],
] as [$yol, $id, $ad]) {
    $y = ist($yol . (strpos($yol, '?') === false ? '?' : '&') . 'lang=tr');
    den("$ad sayfası 200", $y['kod'] === 200, (string)$y['kod']);
    $ops = secenekler($y['govde'], $id);
    $grp = gruplar($y['govde'], $id);
    olc("  $ad: " . count($ops) . ' seçenek, ' . $grp . ' küme');
    den("  $ad seçicisi var", $ops !== []);
    den("  $ad: bütün seçenekler basılmış", count($ops) === $bekOpt + 1, count($ops) . ' / ' . ($bekOpt + 1));
    den("  $ad: küme sayısı doğru", $grp === $bekGrup, $grp . ' / ' . $bekGrup);
    /* Asıl kusur buydu: temel alanlar seçenek olarak yoktu. */
    $eksik = [];
    foreach (array_keys(al_ana()) as $k) if (!in_array((string)$k, $ops, true)) $eksik[] = (string)$k;
    den("  $ad: yedi temel alanın hepsi SEÇENEK", $eksik === [], implode(',', $eksik));
    den("  $ad: ilk seçenek boş (hepsi)", ($ops[0] ?? '#') === '');
}

/* =====================================================================
   5. DAVRANIŞ: SEÇİLEN KOD DOĞRU KÜMEYİ GETİRİYOR MU
   ===================================================================== */
echo "\n== 5. Süzgecin davranışı ==\n";
/* Ölçüm, arşivde fiilen ne varsa onun üzerinden yapılır: kapının kendi
   uydurduğu bir koda bakması, arşiv değiştiği gün sessizce anlamsız
   olurdu. En çok çalışma taşıyan dolu dal seçilir. */
/* ÖLÇÜM DÜZELTMESİ: ilk yazımda tg_yazilar() çağrılıyordu; öyle bir
   işlev yok, dönen boş diziydi ve kapı bütün davranış ölçümlerini
   "arşivde çalışma yok" diyerek ATLIYORDU — yani sessizce hiçbir şey
   ölçmeden yeşil veriyordu. Sayfaların okuduğu işlev k_yazilar()'dır;
   kapı da onu okur. (OKUBENI 92: bir kapının atladığı ölçüm, geçtiği
   ölçümden daha tehlikelidir.) */
$hepsiYazi = array_values(array_filter(k_yazilar(), 'is_array'));
$yayimli = $hepsiYazi;
olc('okunan kayıt: ' . count($hepsiYazi));
den('arşivde ölçülecek çalışma var', $hepsiYazi !== [], '0 kayıt');

$dalSay = [];
foreach ($yayimli as $y) foreach (al_kayit_kodlari($y) as $k) {
    if (al_duzey((string)$k) === 3) $dalSay[(string)$k] = ($dalSay[(string)$k] ?? 0) + 1;
}
arsort($dalSay);
/* ÖLÇÜM DÜZELTMESİ: ilk yazımda yalnız "en dolu dal" seçiliyordu ve o
   dalın (6.2.001) boş bir kardeşi olmadığı için KARDEŞ SIZMASI ölçümü
   atlanıyordu — oysa bütün düzeltmenin sebebi o sızmaydı. Artık önce
   boş kardeşi OLAN dolu dallar aranır; yalnız hiçbiri yoksa en dolu
   dala düşülür. Atlanan bir ölçüm, kırmızı bir ölçümden sinsidir. */
$kardesBul = static function (string $d, array $dalSay): string {
    $ust = al_ust($d);
    foreach (array_keys(al_dallar(false)) as $k) {
        $k = (string)$k;
        if ($k !== $d && al_ust($k) === $ust && ($dalSay[$k] ?? 0) === 0) return $k;
    }
    return '';
};
$dal = '';
foreach (array_keys($dalSay) as $aday) {
    if ($kardesBul((string)$aday, $dalSay) !== '') { $dal = (string)$aday; break; }
}
if ($dal === '') $dal = (string)(array_key_first($dalSay) ?? '');

if ($dal === '') {
    olc('arşivde dal kodu taşıyan yayımlı çalışma yok; davranış ölçümü atlandı');
} else {
    $bilimAlani = al_ust($dal);
    $temelAlan  = explode('.', $dal)[0];
    olc("ölçülen merdiven: $temelAlan > $bilimAlani > $dal");

    $sayac = al_sayac($yayimli);
    $nDal = $sayac($dal); $nAlan = $sayac($bilimAlani); $nTemel = $sayac($temelAlan);
    olc("kapsayan sayım: dal $nDal, bilim alanı $nAlan, temel alan $nTemel");

    /* Merdiven yukarı gittikçe sayı KÜÇÜLEMEZ. Eski süzgeçte tam tersi
       oluyordu: dal 30, temel alan 0. */
    den('temel alan >= bilim alanı >= dal', $nTemel >= $nAlan && $nAlan >= $nDal,
        "$nTemel / $nAlan / $nDal");
    den('temel alan sıfır değil', $nTemel > 0, (string)$nTemel);

    /* Ve bu HTTP üzerinden de öyle olmalı: işlev doğru sayıp sayfa
       yanlış çizerse okurun gördüğü yine sıfırdır. */
    $sDal   = ist('/ara.php?alan=' . rawurlencode($dal) . '&lang=tr');
    $sAlan  = ist('/ara.php?alan=' . rawurlencode($bilimAlani) . '&lang=tr');
    $sTemel = ist('/ara.php?alan=' . rawurlencode($temelAlan) . '&lang=tr');
    den('ara.php: dal seçimi 200', $sDal['kod'] === 200, (string)$sDal['kod']);
    den('ara.php: temel alan seçimi 200', $sTemel['kod'] === 200, (string)$sTemel['kod']);
    $kDal = kart($sDal['govde']); $kAlan = kart($sAlan['govde']); $kTemel = kart($sTemel['govde']);
    olc("ara.php kart: dal $kDal, bilim alanı $kAlan, temel alan $kTemel");
    den('ara.php: temel alan seçimi sonuç veriyor', $kTemel > 0, (string)$kTemel);
    den('ara.php: temel alan >= bilim alanı >= dal', $kTemel >= $kAlan && $kAlan >= $kDal,
        "$kTemel / $kAlan / $kDal");

    /* Seçilen kod seçicide SEÇİLİ görünmeli. Süzgeç doğru süzüp
       seçiciyi boş gösterirse okur "çalışmadı" diye okur; bildirilen
       cümlenin bir parçası tam olarak buydu. */
    den('ara.php: seçilen temel alan seçicide seçili', secili($sTemel['govde'], 'tAlan') === $temelAlan,
        secili($sTemel['govde'], 'tAlan'));
    $yTemel = ist('/yazilar.php?alan=' . rawurlencode($temelAlan) . '&lang=tr');
    den('yazilar.php: seçilen temel alan seçicide seçili', secili($yTemel['govde'], 'alanSec') === $temelAlan,
        secili($yTemel['govde'], 'alanSec'));
    den('yazilar.php: temel alan seçimi kart çiziyor', kart($yTemel['govde']) > 0);

    /* KARDEŞ SIZMASI. Aynı bilim alanının başka bir dalı seçildiğinde
       ölçülen dalın çalışmaları GELMEMELİ. */
    $kardes = $kardesBul($dal, $dalSay);
    if ($kardes !== '') {
        olc('boş kardeş dal: ' . $kardes);
        den('kardeş dal seçimi sızdırmıyor', $sayac($kardes) === 0, (string)$sayac($kardes));
        $sK = ist('/ara.php?alan=' . rawurlencode($kardes) . '&lang=tr');
        den('  ve sayfa da sızdırmıyor', $sK['kod'] === 200 && kart($sK['govde']) === 0, (string)kart($sK['govde']));
        /* Boş bir dal seçmek HATA değildir: dürüst bir "yok" cevabı verir. */
        den('  boş dal seçimi 200 ve dürüst cevap veriyor',
            $sK['kod'] === 200 && $sK['govde'] !== '' && str_contains($sK['govde'], '</html>'));
        den('  boş dal da seçicide seçili görünüyor', secili($sK['govde'], 'tAlan') === $kardes,
            secili($sK['govde'], 'tAlan'));
    } else {
        olc('boş kardeş dal bulunamadı; sızma ölçümü atlandı');
    }
}

/* GEÇERSİZ KOD. İlk yazımda buraya "hiçbir şey getirmemeli" yazmıştım;
   ölçünce altmış kart çıktı ve önce sayfadan şüphelendim. Yanlış olan
   ölçümdü: ara.php geçersiz kodu süzgeç saymaz (al_gecerli() düşürür)
   ve süzgeçsiz liste doğru olarak her şeyi gösterir.

   ASIL ŞART ŞU: sayfa süzdüğünü SÖYLEMEDEN süzmemeli. Geçersiz kodda
   seçici "bütün bilim alanları"na dönmeli ve daraltma etiketi
   basılmamalı; okur o zaman süzgecin uygulanmadığını görür. Sessizce
   süzgeci kaldırıp seçicide o kodu seçili göstermek, süzdüğünü sanan
   okuru yanıltırdı. */
$sYok = ist('/ara.php?alan=' . rawurlencode('99.99.999') . '&lang=tr');
den('geçersiz alan kodu sayfayı düşürmüyor', $sYok['kod'] === 200, (string)$sYok['kod']);
den('  seçici "hepsi"ne dönüyor', secili($sYok['govde'], 'tAlan') === '', secili($sYok['govde'], 'tAlan'));
den('  ve süzüldüğünü söyleyen etiket basmıyor',
    !preg_match('#>\s*Alan:\s*#u', $sYok['govde']));

/* =====================================================================
   6. ANA SAYFA: SINIFLANDIRMANIN TAMAMINA GİDEN YOL
   ===================================================================== */
echo "\n== 6. Ana sayfadaki alan satırı ==\n";
$ana = ist('/index.php?lang=tr');
den('ana sayfa 200', $ana['kod'] === 200, (string)$ana['kod']);
den('alan satırı var', str_contains($ana['govde'], 'class="alan-satir"'));
/* Bildirilen kusur: satır dolu alanları gösteriyor ama tamamına giden
   yol yok. O yol bir bağdır ve seçiciye götürmelidir. */
den('satırın sonunda "bütün alanlar" kapısı var', str_contains($ana['govde'], 'alan-et-hepsi'));
den('  ve seçiciye götürüyor', (bool)preg_match('#alan-et-hepsi"[^>]*href="[^"]*yazilar\.php\#alanSec#', $ana['govde'])
    || (bool)preg_match('#href="[^"]*yazilar\.php\#alanSec"[^>]*class="[^"]*alan-et-hepsi#', $ana['govde']));
den('satırın adı DAL demiyor (satırda dal yok)',
    !preg_match('#<nav class="alan-satir"[^>]*aria-label="[^"]*dallar#iu', $ana['govde']));
den('  temel alan diyor',
    (bool)preg_match('#<nav class="alan-satir"[^>]*aria-label="[^"]*[Tt]emel alan#u', $ana['govde']));
$anaEn = ist('/index.php?lang=en');
den('İngilizcesinde de kapı var', str_contains($anaEn['govde'], 'alan-et-hepsi'));
den('  ve etiketi Türkçe değil',
    !preg_match('#alan-et-hepsi[^>]*>\s*<span>[^<]*(alanlar|dalları)#u', $anaEn['govde']));

/* Ana sayfadaki her etiket gerçekten sonuç vermeli: rakam yazıp boş
   liste açan bir etiket, arşivi olduğundan büyük gösterir. */
preg_match_all('#<a class="alan-et" href="[^"]*alan=([^"&]+)"#', $ana['govde'], $et);
$etKod = array_map('rawurldecode', $et[1]);
olc('ana sayfada dolu temel alan: ' . (count($etKod) ?: 'yok') . ' (' . implode(',', $etKod) . ')');
$bos = [];
foreach ($etKod as $k) {
    $y = ist('/yazilar.php?alan=' . rawurlencode($k) . '&lang=tr');
    if ($y['kod'] !== 200 || kart($y['govde']) === 0) $bos[] = $k;
}
den('ana sayfadaki her alan etiketi dolu bir liste açıyor', $bos === [], implode(',', $bos));

/* =====================================================================
   7. İKİ DİL
   ===================================================================== */
echo "\n== 7. İki dilde de aynı liste ==\n";
$yTr = ist('/yazilar.php?lang=tr'); $yEn = ist('/yazilar.php?lang=en');
$oTr = secenekler($yTr['govde'], 'alanSec'); $oEn = secenekler($yEn['govde'], 'alanSec');
den('İngilizce sayfa 200', $yEn['kod'] === 200, (string)$yEn['kod']);
den('iki dilde seçenek sayısı aynı', count($oTr) === count($oEn), count($oTr) . ' / ' . count($oEn));
den('iki dilde kodlar aynı', $oTr === $oEn);
/* Aynı kodlar ama aynı ADLAR değil: çeviri düşmüşse liste İngilizce
   sayfada Türkçe kalır ve kimse fark etmez. */
/* PCRE'nin {n,m} sınırı 65535'tir; seçici ondan uzundur ve desen
   derlenmeden "geçti" veriyordu. Seçici önce kesilip sonra aranır. */
$secEn = preg_match('#<select[^>]*id="alanSec"[^>]*>(.*?)</select>#su', $yEn['govde'], $mEn) ? $mEn[1] : '';
den('İngilizce seçici okunabildi', $secEn !== '');
den('İngilizce seçicide Türkçe ad kalmamış',
    !preg_match('#(Sosyal bilimler|Beşerî bilimler|Mühendislik ve teknoloji|Doğa bilimleri)#u', $secEn));
$secTr = preg_match('#<select[^>]*id="alanSec"[^>]*>(.*?)</select>#su', $yTr['govde'], $mTr) ? $mTr[1] : '';
den('Türkçe seçicide de İngilizce ad kalmamış',
    !preg_match('#(Social sciences|Natural sciences|Engineering and technology)#u', $secTr));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
