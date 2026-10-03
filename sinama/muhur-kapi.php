<?php
/* =====================================================================
   Tamga mührü: kapı ve ölçüm betiği. Depoya girmez.

   Mühür bir kimlik değil, kimliğin görünen yüzüdür. Bu yüzden buradaki
   kapıların çoğu "doğru mu" değil "söz verilen ile ölçülen tutuyor mu"
   diye sorar. Betik yalnızca GECTI/KALDI basmaz; ölçtüğü sayıyı da
   yazar, çünkü bir sonraki karar (arşiv listesine mühür konsun mu,
   metinde ne yazsın) o sayılara bakarak verilecek.

   Kullanım:
     KUTADGU_DATA=<veri> KPORT=<port> php muhur-kapi.php

   Sunucu ayakta değilse yalnızca 8. bölüm kalır; ötekiler sunucusuz
   ölçülür.
   ===================================================================== */
declare(strict_types=1);

const KTEST = '/home/claude/kg/ktest';
require_once KTEST . '/k/muhur.php';

$VERI  = getenv('KUTADGU_DATA') ?: '/home/claude/kg/ktest-data';
$PORT  = getenv('KPORT') ?: '8941';
$KOK   = 'http://127.0.0.1:' . $PORT;

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $ad, string $deger): void { echo "  OLCUM  $ad: $deger\n"; }

/* --------------------------------------------------------------------
   Kertik anahtarı. İki mühür bayt bayt farklı olduğu hâlde gözle aynı
   olabilir: dış kertikler ayrı sırayla yazılmış olsa da aynı yere
   düşer, üstelik kayan noktalı hesap kimi ucu 46.7, kimini 46.8 yapar.
   Okur bunu görmez. Kertik parçalarının koordinatları tam sayıya
   yuvarlanır ve parçalar sıralanır.

   Bu anahtar mührün BÜTÜNÜ için yetmez: bakışımlı bir gövdenin 180
   derece döndürülmüşünü ayrı sayar, oysa resim aynıdır. Bütün için
   k/muhur.php içindeki mh_resim_anahtari() kullanılır; o, çizimi
   çizgi ve yay listesine indirger ve dönmeyi de uygular. Burada bu
   anahtar yalnızca kertik halkasında ve mh_resim_anahtari()'nin
   sonucunu bağımsız olarak doğrulamak için duruyor.
   -------------------------------------------------------------------- */
function muh_gorsel_anahtar(string $svg): string {
    return preg_replace_callback(
        '#(?<=d=")M[\d.]+ [\d.]+L[^"]*(?=" stroke-width="1\.6")#',
        function (array $m): string {
            $s = preg_replace_callback('#-?\d+\.\d#', fn($x) => (string)round((float)$x[0]), $m[0]);
            $p = array_filter(array_map('trim', explode('M', $s)));
            sort($p);
            return 'M' . implode(' M', $p);
        },
        $svg
    );
}

/* Arşivdeki gerçek tamga kodları: ölçüm uydurma veriyle değil, sistemin
   kendi kodlarıyla da yapılmalı. */
$kodlar = [];
$yj = $VERI . '/yazilar.json';
if (is_file($yj)) {
    $d = json_decode((string)file_get_contents($yj), true);
    foreach (($d['yazilar'] ?? $d ?? []) as $y) {
        $b = trim((string)($y['bcid'] ?? ''));
        if ($b !== '') $kodlar[] = $b;
    }
}
$kodlar = array_values(array_unique($kodlar));

/* HTTP yardımcısı. Hız sınırına takılırsa sayaç dosyası boşaltılıp bir
   kez daha denenir: ölçülmek istenen şey sınır değil, mührün sayfada
   basılıp basılmadığı. */
function ist(string $yol): array {
    global $KOK, $VERI;
    for ($i = 0; $i < 2; $i++) {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'ignore_errors' => true, 'timeout' => 30]]);
        $g = @file_get_contents($KOK . $yol, false, $ctx);
        $kod = 0;
        foreach (($http_response_header ?? []) as $s) {
            if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
        }
        if ($kod !== 429) return ['kod' => $kod, 'govde' => (string)$g];
        @file_put_contents($VERI . '/hiz-sinir.json', '{}');
    }
    return ['kod' => 429, 'govde' => ''];
}

/* =====================================================================
   1. Kararlılık: aynı kod her zaman aynı mühür
   ===================================================================== */
echo "== 1. Kararlılık ==\n";

/* Aynı süreçte: bir çağrının ikincisini etkileyen bir durum (statik
   sayaç, önbellek, sıra numarası) varsa burada görünür. */
$ayni = true; $ilkler = [];
foreach ($kodlar as $k) {
    $ilkler[$k] = mh_muhur($k, 64);
    for ($i = 0; $i < 4; $i++) if (mh_muhur($k, 64) !== $ilkler[$k]) $ayni = false;
}
den('aynı süreçte ' . count($kodlar) . ' kod x 5 çağrı aynı sonucu verir', $ayni);

/* Ayrı süreçte: rastgele tohum, zaman, süreç kimliği ya da başlatma
   sırasındaki bir şey sızmışsa iki süreç ayrışır. Yayımlanmış bir kayıt
   geriye dönük değişmediği için mühür de sunucu her yeniden başladığında
   değişmemelidir. */
$ornek = array_slice($kodlar, 0, 12);
$betik = 'require ' . var_export(KTEST . '/k/muhur.php', true) . ';'
       . '$o = [];foreach (json_decode($argv[1], true) as $k) $o[] = md5(mh_muhur($k, 64));'
       . 'echo implode(",", $o);';
$cikti = [];
exec('php -r ' . escapeshellarg($betik) . ' ' . escapeshellarg(json_encode($ornek)) . ' 2>&1', $cikti);
$beklenen = implode(',', array_map(fn($k) => md5($ilkler[$k]), $ornek));
den('ayrı süreçte aynı sonucu verir', trim(implode('', $cikti)) === $beklenen,
    substr(trim(implode('', $cikti)), 0, 60));

/* Kaynakta rastgelelik ya da zaman okuma hiç olmamalı: bir kez sızarsa
   yukarıdaki iki kapı da çoğu koşuluda geçer, kusur yalnızca sunucu
   yeniden başlayınca görünür. */
$kaynak = (string)file_get_contents(KTEST . '/k/muhur.php');
$yasak = ['rand(', 'mt_rand', 'random_int', 'random_bytes', 'shuffle(', 'uniqid(',
          'time()', 'microtime', 'date(', 'array_rand', 'session_', '$_'];
$bulunan = array_values(array_filter($yasak, fn($y) => strpos($kaynak, $y) !== false));
den('kaynakta rastgelelik/zaman/oturum okuması yok', $bulunan === [], implode(' ', $bulunan));

/* Tohum kodu trim + strtoupper ediyor. Bu bir kusur değil, yazılı bir
   davranış: aynı kod farklı yazılmış olsa da aynı mührü verir. Kapı
   burada bu davranışı çiviler ki sonradan sessizce değişmesin. */
$k0 = $kodlar[0] ?? 'KTG-2024-00001-1';
den('baştaki/sondaki boşluk mührü değiştirmez', mh_muhur('  ' . $k0 . ' ') === mh_muhur($k0));
den('küçük harfle yazılmış kod aynı mührü verir',
    mh_muhur(strtolower($k0)) === mh_muhur($k0));
den('boş kod boş çıktı verir (sayfada boş kutu kalmaz)', mh_muhur('') === '' && mh_muhur('   ') === '');

/* Boy ve sınıf yalnızca kabuğu değiştirmeli; çizimin kendisi koddan
   gelir, ölçüden değil. */
$g64 = mh_muhur($k0, 64); $g44 = mh_muhur($k0, 44);
$icAl = fn(string $s) => (string)preg_replace('#^<svg[^>]*>|</svg>$#', '', $s);
den('boy değişince çizim değişmez, yalnızca kutu ölçüsü değişir', $icAl($g64) === $icAl($g44));

/* =====================================================================
   2. Ayrışma: tek karakter değişince mühür değişir
   ===================================================================== */
echo "\n== 2. Ayrışma ==\n";

/* Tamga kodunun bir hanesi yanlış yazıldığında mührün de değişmesi
   beklenir; değişmezse mühür yanlış kodu doğruluyormuş gibi görünür.
   Ölçüm hem bayt düzeyinde hem gözle görünen düzeyde yapılır, çünkü
   okur baytları değil resmi görür. */
$deneme = 0; $baytAyni = 0; $gozAyni = 0; $ornekler = [];
foreach ($kodlar as $k) {
    $u = strtoupper($k);
    for ($i = 0, $n = strlen($u); $i < $n; $i++) {
        $c = $u[$i];
        $y = ctype_digit($c) ? (string)(((int)$c + 1) % 10)
           : (ctype_alpha($c) ? chr(65 + ((ord($c) - 65 + 1) % 26)) : '_');
        $v = substr_replace($u, $y, $i, 1);
        if ($v === $u) continue;
        $deneme++;
        $a = mh_muhur($u, 64); $b = mh_muhur($v, 64);
        if ($a === $b) { $baytAyni++; $ornekler[] = "$u -> $v"; }
        if (mh_resim_anahtari($a) === mh_resim_anahtari($b)) $gozAyni++;
    }
}
olc('tek karakter değişimi denemesi', (string)$deneme);
olc('mühür bayt bayt aynı kalan', $baytAyni . ' (' . sprintf('%.3f%%', $deneme ? 100 * $baytAyni / $deneme : 0) . ')');
olc('mühür gözle aynı kalan', $gozAyni . ' (' . sprintf('%.3f%%', $deneme ? 100 * $gozAyni / $deneme : 0) . ')');
/* Sıfır beklenmiyor, beklenen sayı çakışma olasılığı kadar küçük. Eşik
   rastlantıya yer bırakacak kadar gevşek, kusuru yakalayacak kadar dar. */
den('tek karakter değişimi mührü değiştirir (bayt)', $deneme > 0 && $baytAyni <= 1,
    $baytAyni . ' aynı kaldı: ' . implode(', ', array_slice($ornekler, 0, 3)));
den('tek karakter değişimi mührü değiştirir (gözle)', $deneme > 0 && $gozAyni <= 2, (string)$gozAyni);

/* Kodun sonundaki denetim hanesi ile ondan önceki sıra numarası ayrı
   ayrı etkili olmalı; biri ötekini gölgelemesin. */
$farkli = mh_muhur('KTG-2024-00001-1') !== mh_muhur('KTG-2024-00001-2')
       && mh_muhur('KTG-2024-00001-1') !== mh_muhur('KTG-2024-00002-1')
       && mh_muhur('KTG-2024-00001-1') !== mh_muhur('KTG-2025-00001-1');
den('ardışık kodlar ayrı mühür verir', $farkli);

/* =====================================================================
   3. Çakışma oranı
   ===================================================================== */
echo "\n== 3. Çakışma oranı ==\n";

/* Önce koddan sayılabilecek olanı sayalım. Sekiz seçim var ve her biri
   sha256'nın bir baytının kalanından okunuyor; baytlar 0-255 arasında
   düzgün dağıldığı için her seçeneğin olasılığı kalan aritmetiğinden
   çıkar. 256 bölene tam bölünmüyorsa (7, 5, 12) kimi seçenek ötekinden
   biraz daha sık gelir; bu, çakışmayı düzgün dağılıma göre yukarı
   çeker. Aşağıdaki sayım bu eğrilmeyi de hesaba katar. */
$agirlik = function (int $bolen): array {
    $w = array_fill(0, $bolen, 0);
    for ($b = 0; $b < 256; $b++) $w[$b % $bolen]++;
    return array_map(fn($c) => $c / 256, $w);
};
$wGovde = $agirlik(8); $wSus = $agirlik(7); $wAdim = $agirlik(5);
$wKayma = $agirlik(12); $wIki = $agirlik(2); $wDort = $agirlik(4);

/* Kertiğin gözle ayrı kaç hâli var: adım 12'nin bir böleni olduğu için
   kayma ancak adım kadar ayrı sonuç verir (adım 1 ise on iki kertiğin
   hepsi çizilir ve kayma hiçbir şeyi değiştirmez). */
$wKertik = []; $wHamKertik = [];
foreach ([0, 1, 2, 3, 4] as $d) {
    foreach (range(0, 11) as $e) {
        foreach ([0, 1] as $f) {
            $on = $wAdim[$d] * $wKayma[$e] * $wIki[$f];
            $ham = mh_kertik($d, $e, $f);
            $goz = muh_gorsel_anahtar('<path d="' . $ham . '" stroke-width="1.6" />');
            $wKertik[$goz] = ($wKertik[$goz] ?? 0) + $on;
            $wHamKertik[$ham] = ($wHamKertik[$ham] ?? 0) + $on;
        }
    }
}

/* İç bölümün gözle ayrı kaç hâli var. Buradaki incelik kertiğinkinden
   farklı: baklava ile halka-eksen gövdeleri 180 derece döndürülünce
   kendilerine eşit, üst süs 180 derece dönünce de alt süsün ta kendisi
   oluyor. Bu iki gövdede (üst=i, alt=j, dönme=d) ile
   (üst=j, alt=i, dönme=d+180) aynı resmi veriyor. Seçim ayrı, resim
   aynı; okur ikisini ayıramaz. */
$wIc = []; $wHamIc = [];
for ($a = 0; $a < 8; $a++) {
  for ($b = 0; $b < 7; $b++) {
    for ($c = 0; $c < 7; $c++) {
      for ($h = 0; $h < 4; $h++) {
        $on = $wGovde[$a] * $wSus[$b] * $wSus[$c] * $wDort[$h];
        $ham = mh_ic_grup($a, $b, $c, $h * 90);
        $goz = mh_resim_anahtari($ham);
        $wIc[$goz] = ($wIc[$goz] ?? 0) + $on;
        $wHamIc[$ham] = ($wHamIc[$ham] ?? 0) + $on;
      }
    }
  }
}

/* mh_resim_anahtari() kaynak dosyanın kendi işlevi; onunla ölçüp yine
   ona inanmak ölçüm değil, yankıdır. Aynı sayı burada bambaşka bir
   yolla da çıkarılır: her çizim nokta nokta örneklenir ve kâğıtta kalan
   nokta kümesi karşılaştırılır. İki yöntem tutmuyorsa ikisinden biri
   yanlıştır ve aşağıdaki hiçbir sayıya güvenilmez.

   Nokta bulutu yalnızca iç bölümde kullanılır: iç bölümün bütün
   koordinatları tam sayı olduğu için orada kayan noktalı artık yoktur.
   Kertik uçları %.1f ile yazıldığından aynı yer kimi hesapta 17.2 kimi
   hesapta 17.3 çıkar; oradaki ölçüm yuvarlayan anahtarla yapılır. */
$nokta = function (string $svg): string {
    $p = [];
    $ekle = function (float $x0, float $y0, float $x1, float $y1, float $kal, int $donme) use (&$p): void {
        $uz = sqrt(($x1 - $x0) ** 2 + ($y1 - $y0) ** 2);
        $adet = max(1, (int)ceil($uz / 0.25));
        for ($j = 0; $j <= $adet; $j++) {
            $u = $j / $adet;
            $x = $x0 + ($x1 - $x0) * $u; $y = $y0 + ($y1 - $y0) * $u;
            if ($donme === 90)  { $t = $x; $x = 64 - $y; $y = $t; }
            if ($donme === 180) { $x = 64 - $x; $y = 64 - $y; }
            if ($donme === 270) { $t = $x; $x = $y; $y = 64 - $t; }
            $p[sprintf('%.1f %.1f %.1f', round($x * 2) / 2, round($y * 2) / 2, $kal)] = 1;
        }
    };
    $cember = function (float $mx, float $my, float $r, float $b, float $s,
                        float $kal, int $donme) use (&$p, $ekle): void {
        /* Uçlar da örneklenir: yay ters yönde çizilmişse ancak uçlar da
           dahilse aynı nokta kümesi çıkar. */
        $adet = max(8, (int)ceil(deg2rad(abs($s)) * $r / 0.25));
        for ($j = 0; $j <= $adet; $j++) {
            $ac = deg2rad($b + $s * $j / $adet);
            $x = $mx + $r * cos($ac); $y = $my + $r * sin($ac);
            $ekle($x, $y, $x, $y, $kal, $donme);
        }
    };
    $isle = function (string $par, int $donme) use ($ekle, $cember): void {
        preg_match_all('#<(circle|path)\b([^>]*)/>#', $par, $m, PREG_SET_ORDER);
        foreach ($m as $e) {
            $kal = preg_match('#stroke-width="([\d.]+)"#', $e[2], $q) ? (float)$q[1] : 2.2;
            if (strpos($e[2], 'fill="currentColor"') !== false) $kal += 100;
            if ($e[1] === 'circle') {
                preg_match('#cx="([\d.-]+)"#', $e[2], $u); preg_match('#cy="([\d.-]+)"#', $e[2], $v);
                preg_match('#\sr="([\d.-]+)"#', $e[2], $w);
                $cember((float)$u[1], (float)$v[1], (float)$w[1], 0, 360, $kal, $donme);
                continue;
            }
            preg_match('#d="([^"]*)"#', $e[2], $u);
            preg_match_all('#[a-zA-Z]|-?\d*\.?\d+#', $u[1], $g);
            $t = $g[0]; $n = count($t); $i = 0; $cx = 0.0; $cy = 0.0; $bx = 0.0; $by = 0.0; $ko = '';
            while ($i < $n) {
                if (ctype_alpha($t[$i])) { $ko = $t[$i]; $i++; }
                elseif ($ko === 'M') $ko = 'L'; elseif ($ko === 'm') $ko = 'l';
                /* Z sayı almaz ve yolun sonunda durur: sayı bekleyip
                   çıkılırsa kapanış kenarı hiç çizilmez ve baklava
                   gövdesi dört yerine üç kenarlı ölçülür. */
                if ($ko === 'Z' || $ko === 'z') { $ekle($cx, $cy, $bx, $by, $kal, $donme); $cx = $bx; $cy = $by; $ko = ''; continue; }
                if ($i >= $n) break;
                if ($ko === 'M' || $ko === 'm') {
                    $cx = ($ko === 'm' ? $cx : 0) + (float)$t[$i];
                    $cy = ($ko === 'm' ? $cy : 0) + (float)$t[$i + 1];
                    $bx = $cx; $by = $cy; $i += 2; continue;
                }
                if ($ko === 'L' || $ko === 'l') {
                    $x = ($ko === 'l' ? $cx : 0) + (float)$t[$i];
                    $y = ($ko === 'l' ? $cy : 0) + (float)$t[$i + 1];
                    $ekle($cx, $cy, $x, $y, $kal, $donme); $cx = $x; $cy = $y; $i += 2; continue;
                }
                if ($ko === 'H' || $ko === 'h') {
                    $x = ($ko === 'h' ? $cx : 0) + (float)$t[$i];
                    $ekle($cx, $cy, $x, $cy, $kal, $donme); $cx = $x; $i++; continue;
                }
                if ($ko === 'V' || $ko === 'v') {
                    $y = ($ko === 'v' ? $cy : 0) + (float)$t[$i];
                    $ekle($cx, $cy, $cx, $y, $kal, $donme); $cy = $y; $i++; continue;
                }
                if ($ko === 'A' || $ko === 'a') {
                    $r = (float)$t[$i]; $genis = (int)$t[$i + 3]; $yon = (int)$t[$i + 4];
                    $x = ($ko === 'a' ? $cx : 0) + (float)$t[$i + 5];
                    $y = ($ko === 'a' ? $cy : 0) + (float)$t[$i + 6];
                    /* Yayın merkezi: yarıçaplar eşit olduğu için kısa yol. */
                    $ax = ($cx - $x) / 2; $ay = ($cy - $y) / 2; $kk = $ax * $ax + $ay * $ay;
                    if ($kk > 0) {
                        if ($kk > $r * $r) $r = sqrt($kk);
                        $f = ($r * $r - $kk) / $kk; $f = $f < 0 ? 0.0 : sqrt($f);
                        if ($genis === $yon) $f = -$f;
                        $mx = $f * $ay + ($cx + $x) / 2; $my = -$f * $ax + ($cy + $y) / 2;
                        $t0 = rad2deg(atan2($cy - $my, $cx - $mx));
                        $t1 = rad2deg(atan2($y - $my, $x - $mx));
                        $dt = $t1 - $t0;
                        if ($yon === 0 && $dt > 0) $dt -= 360;
                        if ($yon === 1 && $dt < 0) $dt += 360;
                        $cember($mx, $my, $r, $t0, $dt, $kal, $donme);
                    }
                    $cx = $x; $cy = $y; $i += 7; continue;
                }
                $i++;
            }
        }
    };
    $kalan = preg_replace_callback('#<g transform="rotate\((\d+) 32 32\)">(.*?)</g>#s',
        function (array $m) use ($isle): string { $isle($m[2], ((int)$m[1] % 360 + 360) % 360); return ''; }, $svg);
    $isle((string)$kalan, 0);
    $k = array_keys($p); sort($k);
    return md5(implode('|', $k));
};
$icNokta = [];
for ($a = 0; $a < 8; $a++) for ($b = 0; $b < 7; $b++) for ($c = 0; $c < 7; $c++) for ($h = 0; $h < 4; $h++)
    $icNokta[$nokta(mh_ic_grup($a, $b, $c, $h * 90))] = 1;
den('iki bağımsız görsel ölçüm iç bölümde aynı sayıyı veriyor',
    count($icNokta) === count($wIc),
    'nokta bulutu ' . count($icNokta) . ', kanonik parça ' . count($wIc));

/* Mühür üç bağımsız bölgeden kuruludur ve bölgeler üst üste binmez (iç
   işaret r<=19'da kalır, iç halka r=20.5, kertikler r>=24'ten dışarı).
   Bu yüzden bölüm bölüm sayıp çarpmak bütünü saymakla aynı sonucu
   verir; aşağıdaki yirmi bin girdilik ölçüm de bunu doğruluyor. */
$kare = fn(array $p) => array_sum(array_map(fn($x) => $x * $x, $p));
$sayHamToplam = count($wHamIc) * count($wHamKertik) * 2;
$sayGozToplam = count($wIc) * count($wKertik) * 2;
$pHam = $kare($wHamIc) * $kare($wHamKertik) * $kare([$wIki[0], $wIki[1]]);
$pGoz = $kare($wIc) * $kare($wKertik) * $kare([$wIki[0], $wIki[1]]);
$etkinHam = (int)round(1 / $pHam);
$etkinGoz = (int)round(1 / $pGoz);

olc('ayrı SVG dizgesi sayısı', number_format($sayHamToplam));
olc('gözle ayrı mühür sayısı', number_format($sayGozToplam));
olc('  bunun kaynağı: iç bölüm', count($wHamIc) . ' seçim -> ' . count($wIc) . ' ayrı resim');
olc('  bunun kaynağı: kertik', count($wHamKertik) . ' seçim -> ' . count($wKertik) . ' ayrı resim');
olc('etkin ayrı mühür (dizge)', number_format($etkinHam));
olc('etkin ayrı mühür (gözle)', number_format($etkinGoz));
olc('kodda beyan edilen dizge sayısı (mh_dizge_sayisi)', number_format(mh_dizge_sayisi()));
olc('kodda beyan edilen görünüş sayısı (mh_gorunus_sayisi)', number_format(mh_gorunus_sayisi()));
olc('kodda beyan edilen etkin sayı (mh_etkin_gorunus_sayisi)', number_format(mh_etkin_gorunus_sayisi()));

/* Şimdi aynı şeyi ölçelim: sayım ile ölçüm tutmuyorsa önce sayımdan
   şüphelenilir. Girdiler gerçek tamga kodunun biçiminde üretilir. */
$N = 20000;
$sayHam = []; $sayGoz = [];
for ($i = 0; $i < $N; $i++) {
    $kod = sprintf('KTG-%04d-%05d-%d', 2000 + intdiv($i, 1000), $i % 100000, $i % 10);
    $s = mh_muhur($kod, 64);
    $a = substr(md5($s), 0, 16);
    $b = substr(md5(mh_resim_anahtari($s)), 0, 16);
    $sayHam[$a] = ($sayHam[$a] ?? 0) + 1;
    $sayGoz[$b] = ($sayGoz[$b] ?? 0) + 1;
}
$cift = fn(array $m) => array_sum(array_map(fn($c) => $c * ($c - 1) / 2, $m));
$toplamCift = $N * ($N - 1) / 2;
$ciftHam = $cift($sayHam); $ciftGoz = $cift($sayGoz);
$olcHam = $ciftHam / $toplamCift; $olcGoz = $ciftGoz / $toplamCift;

olc('sentetik girdi sayısı', number_format($N));
olc('ayrı mühür (dizge) / çakışan girdi',
    number_format(count($sayHam)) . ' / ' . number_format($N - count($sayHam))
    . sprintf('  (%.3f%% girdi bir başkasıyla aynı dizgeyi paylaşıyor)', 100 * ($N - count($sayHam)) / $N));
olc('ayrı mühür (gözle) / çakışan girdi',
    number_format(count($sayGoz)) . ' / ' . number_format($N - count($sayGoz))
    . sprintf('  (%.3f%% girdi gözle aynı mührü paylaşıyor)', 100 * ($N - count($sayGoz)) / $N));
olc('ÖLÇÜLEN ÇAKIŞMA ORANI (dizge)',
    sprintf('%.6f%%  = iki rastgele kodda 1/%s', 100 * $olcHam, number_format((int)round(1 / max($olcHam, 1e-12)))));
olc('ÖLÇÜLEN ÇAKIŞMA ORANI (gözle)',
    sprintf('%.6f%%  = iki rastgele kodda 1/%s', 100 * $olcGoz, number_format((int)round(1 / max($olcGoz, 1e-12)))));

/* Ölçüm ile sayım tutuyor mu: tutmuyorsa ikisinden biri yanlıştır ve
   aşağıdaki karşılaştırmaların hiçbirine güvenilmez. */
den('ölçülen çakışma sayılan ile tutuyor (dizge)',
    abs($olcHam - $pHam) <= 0.2 * $pHam,
    sprintf('ölçülen 1/%d, sayılan 1/%d', (int)round(1 / max($olcHam, 1e-12)), $etkinHam));
den('ölçülen çakışma sayılan ile tutuyor (gözle)',
    abs($olcGoz - $pGoz) <= 0.2 * $pGoz,
    sprintf('ölçülen 1/%d, sayılan 1/%d', (int)round(1 / max($olcGoz, 1e-12)), $etkinGoz));

/* Beyan denetimi. Kodda artık üç ayrı sayı var ve üçü ayrı şey söyler;
   birinin ötekinin yerine geçmesi tam da düzeltilen kusurdu. */
den('mh_dizge_sayisi() ayrı SVG dizgesi sayısını veriyor',
    mh_dizge_sayisi() === $sayHamToplam,
    mh_dizge_sayisi() . ' vs ' . $sayHamToplam);
den('mh_gorunus_sayisi() GÖZLE ayrı mühür sayısını veriyor',
    mh_gorunus_sayisi() === $sayGozToplam,
    'beyan ' . number_format(mh_gorunus_sayisi()) . ', gözle ayrı ' . number_format($sayGozToplam));
den('mh_gorunus_sayisi() dizge sayısını tekrarlamıyor',
    mh_gorunus_sayisi() < $sayHamToplam,
    'görünüş sayısı dizge sayısına eşit çıktı, demek ki birleşmeler sayılmamış');
den('mh_etkin_gorunus_sayisi() ölçülen çakışmayla tutuyor (%2)',
    abs(mh_etkin_gorunus_sayisi() - $etkinGoz) <= 0.02 * $etkinGoz,
    'beyan ' . number_format(mh_etkin_gorunus_sayisi()) . ', sayılan ' . number_format($etkinGoz));
den('etkin sayı gözle ayrı sayıdan küçük (görünüşler eşit sıklıkta değil)',
    mh_etkin_gorunus_sayisi() < mh_gorunus_sayisi(),
    sprintf('etkin %s, gözle ayrı %s', number_format(mh_etkin_gorunus_sayisi()),
        number_format(mh_gorunus_sayisi())));
olc('dizge sayısı etkin sayının kaç katı',
    sprintf('%.1f kat (bu oran kadar iyimser bir tablo çizerdi)', $sayHamToplam / max($etkinGoz, 1)));

/* Arşivin bugünkü boyunda çakışma var mı, ve olma olasılığı ne. Bu sayı
   metne yazılacak olan sayıdır: "altmış çalışmada iki mührün aynı çıkma
   olasılığı yüzde bu kadar". Metin bu sayıyı elle yazmamalı,
   mh_cakisma_beklentisi() işlevinden almalı. */
$gercek = [];
foreach ($kodlar as $k) {
    $a = mh_resim_anahtari(mh_muhur($k, 64));
    $gercek[$a][] = $k;
}
$carpisan = array_filter($gercek, fn($v) => count($v) > 1);
$m = count($kodlar);
$bekEn = $m > 1 ? 1 - exp(-($m * ($m - 1) / 2) * $pGoz) : 0.0;
olc('arşivdeki gerçek kod sayısı', (string)$m);
olc('arşivde gözle çakışan çift', (string)$cift($gercek === [] ? [] : array_map('count', $gercek)));
olc('bu boyda en az bir çakışma olasılığı', sprintf('%%%.2f', 100 * $bekEn));
olc('altmış kodluk bir arşivde en az bir çakışma olasılığı',
    sprintf('%%%.2f', 100 * mh_cakisma_beklentisi(60)));
den('mh_cakisma_beklentisi() kapının kendi hesabıyla tutuyor',
    $m < 2 || abs(mh_cakisma_beklentisi($m) - $bekEn) <= 0.02 * max($bekEn, 1e-9),
    sprintf('beyan %%%.4f, sayılan %%%.4f', 100 * mh_cakisma_beklentisi($m), 100 * $bekEn));
den('bugünkü arşivde çakışan mühür yok', $carpisan === [],
    implode(' | ', array_map(fn($v) => implode(' = ', $v), $carpisan)));

/* =====================================================================
   4. Çıktı geçerliliği
   ===================================================================== */
echo "\n== 4. Çıktı geçerliliği ==\n";

$svgler = [];
foreach (array_slice($kodlar, 0, 60) as $k) $svgler[$k] = mh_muhur($k, 64);
for ($i = 0; $i < 200; $i++) $svgler['s' . $i] = mh_muhur('KTG-2030-' . sprintf('%05d', $i) . '-3', 64);

libxml_use_internal_errors(true);
$bozuk = [];
foreach ($svgler as $k => $s) {
    $x = simplexml_load_string('<w xmlns:svg="http://www.w3.org/2000/svg">' . $s . '</w>');
    if ($x === false) $bozuk[] = $k;
}
libxml_clear_errors();
den('üretilen ' . count($svgler) . ' mühür iyi biçimli XML', $bozuk === [], implode(' ', array_slice($bozuk, 0, 3)));

$ilk = $svgler[$k0] ?? reset($svgler);
den('viewBox var', strpos($ilk, 'viewBox="0 0 64 64"') !== false);
/* width/height öznitelik olarak duruyor: sunum özniteliğinin CSS'e karşı
   önceliği yoktur, bu yüzden .ys-muhur svg{width:...} yazan bir kural
   onu ezebilir. Sabit boyut dayatan şey inline style olurdu. */
den('inline style ile boyut dayatmıyor', strpos($ilk, 'style=') === false);
den('width/height CSS ile ezilebilir öznitelik', (bool)preg_match('#<svg[^>]*\swidth="\d+"\s+height="\d+"#', $ilk));
den('kare oran (width = height)', (bool)preg_match('#width="(\d+)" height="\1"#', $ilk));
/* Aynı sayfaya altmış mühür konacaksa id çakışması olmamalı; id yoksa
   sorun da yok. Aynı gerekçeyle <defs>/<use> de yok. */
$idli = array_filter($svgler, fn($s) => strpos($s, ' id=') !== false || strpos($s, '<defs') !== false);
den('id ya da defs kullanmıyor (aynı sayfada çoğaltılabilir)', $idli === []);
/* Renk yalnızca currentColor ya da none olabilir; bir yere sabit renk
   yazılmışsa koyu temada mühür kaybolur ya da göze batar. */
den('rengi sabitlemiyor, currentColor kullanıyor',
    strpos($ilk, 'stroke="currentColor"') !== false
    && !preg_match('~(stroke|fill)="(?!currentColor"|none")~', $ilk));
$disArac = array_filter($svgler, fn($s) => preg_match('~<script|<image|xlink:href|url\(~', $s));
den('dış kaynak, script ya da image içermiyor', $disArac === []);
$sinifli = mh_muhur($k0, 64, 'ys-im "x');
den('sınıf adı kaçırılıyor (öznitelik kırılmıyor)', strpos($sinifli, 'class="ys-im &quot;x"') !== false);

/* =====================================================================
   5. Sayfa ağırlığı
   ===================================================================== */
echo "\n== 5. Sayfa ağırlığı ==\n";

$boylar = [];
foreach ($kodlar as $k) $boylar[] = strlen(mh_muhur($k, 64));
sort($boylar);
$toplam = array_sum($boylar);
$ort = $boylar ? (int)round($toplam / count($boylar)) : 0;
$ortanca = $boylar ? $boylar[intdiv(count($boylar), 2)] : 0;
olc('tek mühür (bayt)', 'en küçük ' . ($boylar[0] ?? 0) . ', ortanca ' . $ortanca
    . ', ortalama ' . $ort . ', en büyük ' . (end($boylar) ?: 0));

/* Bir sonraki iş: arşiv listesine satır içi mühür konursa. Ham bayt
   önemlidir çünkü HTML gövdesi belleğe ve DOM'a girer; gzip önemlidir
   çünkü tel üzerinde giden odur. İkisi de yazılır ki karar ikisine
   birden bakarak verilsin. */
$govde = '';
foreach ($kodlar as $k) $govde .= mh_muhur($k, 28);
$hamKB  = strlen($govde) / 1024;
$gzipKB = strlen((string)gzencode($govde, 6)) / 1024;
$dugum = count($kodlar) * 5;   /* svg + halka + kertik + g + gövde yolu */
olc('arşiv listesinde ' . count($kodlar) . ' satır içi mühür',
    sprintf('ham %.1f KB, gzip %.1f KB, yaklaşık %d DOM düğümü', $hamKB, $gzipKB, $dugum));
olc('yüz çalışmalık listede aynı hesap',
    sprintf('ham %.1f KB, gzip %.1f KB', $hamKB / max(count($kodlar), 1) * 100, $gzipKB / max(count($kodlar), 1) * 100));

den('tek mühür 1 KB altında', (end($boylar) ?: 0) < 1024, (string)(end($boylar) ?: 0));
den('altmış satır içi mühür ham 64 KB altında', $hamKB < 64, sprintf('%.1f KB', $hamKB));
den('altmış satır içi mühür gzip 8 KB altında', $gzipKB < 8, sprintf('%.1f KB', $gzipKB));

/* =====================================================================
   6. İddia denetimi
   ===================================================================== */
echo "\n== 6. İddia denetimi ==\n";

/* Kural: söz verilemeyecek şey söylenmez. Mühür biricik değildir, bu
   yüzden hiçbir yerde biricikmiş gibi anılmamalıdır. Tarama mühürden
   söz eden satırların iki satır çevresinde yapılır; yoksa "tekil
   okuyucu" gibi mühürle ilgisi olmayan sözler yanlış yere düşer.
   Olumsuzlanmış cümle (biriciklik iddiası YOKTUR) iddia değildir. */
$kucult = function (string $s): string {
    /* mb_strtolower Türkçe İ'yi noktalı i'ye çevirdiği için önce elle
       eşlenir; yoksa "BİRİCİK" hiçbir kalıba uymaz. */
    return mb_strtolower(strtr($s, ['İ' => 'i', 'I' => 'ı', 'Ş' => 'ş', 'Ğ' => 'ğ',
        'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç']), 'UTF-8');
};
$iddiaKalip  = '#(?<![\p{L}])(biricik|eşsiz|essiz|unique|garanti|kesin)#u';
$olumsuzlama = '#(yok|değil|denmez|olmaz|olmamalı|iddia edilmez|no |not |never)#u';
$muhurKalip  = '#(muhur|mühür|mh_muhur|seal|tamga mühr)#u';

$dosyalar = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(KTEST, FilesystemIterator::SKIP_DOTS));
$tarandi = 0; $ihlal = [];
foreach ($dosyalar as $f) {
    $uz = strtolower($f->getExtension());
    if (!in_array($uz, ['php', 'js', 'md'], true)) continue;
    $tarandi++;
    $satir = file($f->getPathname(), FILE_IGNORE_NEW_LINES);
    if ($satir === false) continue;
    foreach ($satir as $i => $s) {
        $l = $kucult($s);
        if (!preg_match($iddiaKalip, $l)) continue;
        /* Mühür bağlamı mı: kendi satırı ya da iki satır çevresi */
        $pencere = '';
        for ($j = max(0, $i - 2); $j <= min(count($satir) - 1, $i + 2); $j++) $pencere .= $kucult($satir[$j]) . "\n";
        if (!preg_match($muhurKalip, $pencere)) continue;
        if (preg_match($olumsuzlama, $l)) continue;   /* olumsuzlanmış: iddia değil */
        $ihlal[] = str_replace(KTEST . '/', '', $f->getPathname()) . ':' . ($i + 1) . '  ' . trim(substr($s, 0, 70));
    }
}
olc('taranan php/js/md dosyası', (string)$tarandi);
den('mühür için biricik/eşsiz/unique/garanti/kesin iddiası yok', $ihlal === [],
    implode(' || ', array_slice($ihlal, 0, 3)));

/* Okura gösterilen metin ne diyor: "koddan üretilen", "biricik" değil. */
$yazi = (string)file_get_contents(KTEST . '/yazi.php');
den('okura gösterilen metin "koddan üretilen" diyor',
    strpos($yazi, 'Koddan üretilen mühür') !== false
    && strpos($yazi, 'Seal generated from the code') !== false);
den('kaynak dosya biricik olmadığını açıkça yazıyor',
    stripos($kucult($kaynak), 'biriciklik iddiası yoktur') !== false);

/* =====================================================================
   7. Erişilebilirlik
   ===================================================================== */
echo "\n== 7. Erişilebilirlik ==\n";

/* İki geçerli yol var: ya mührün bir metin karşılığı olur (role+aria-label
   ya da <title>), ya da salt süs sayılıp gizlenir. Ortası, ekran
   okuyucuya anlamsız bir "grafik" duyurmaktır. Buradaki seçim ikincisi
   olmalı: mührün taşıdığı bilgi, hemen yanında metin olarak zaten yazan
   tamga kodudur; iki kez okutmak gürültüdür. */
$metinli = strpos($ilk, 'aria-label=') !== false || strpos($ilk, '<title') !== false
        || strpos($ilk, 'role="img"') !== false;
$gizli   = strpos($ilk, 'aria-hidden="true"') !== false;
den('ya metin karşılığı var ya da salt süs olarak gizli', $metinli xor $gizli,
    'metinli=' . ($metinli ? 'e' : 'h') . ' gizli=' . ($gizli ? 'e' : 'h'));
den('gizliyse odak sırasından da çıkarılmış', !$gizli || strpos($ilk, 'focusable="false"') !== false);
den('aria-hidden içinde odaklanabilir öğe yok', !preg_match('#<(a|button|text)[\s>]#', $ilk));
/* Süs sayılabilmesinin koşulu: taşıdığı bilgi başka yerde metin olarak
   bulunmalı. Bu koşul yazi.php'de sağlanıyor mu diye bakılır. */
den('mührün yanında kod metin olarak yazıyor (yazi.php)',
    strpos($yazi, 'ys-muhur-kod') !== false && strpos($yazi, 'mb-muhur-kod') !== false);

/* =====================================================================
   8. Sayfada görünürlük
   ===================================================================== */
echo "\n== 8. Sayfada görünürlük ==\n";

$ana = ist('/');
if ($ana['kod'] !== 200) {
    den('sunucu ayakta (' . $KOK . ')', false, 'HTTP ' . $ana['kod']);
} else {
    den('sunucu ayakta (' . $KOK . ')', true);
    /* Yazı bağlantısı ana sayfadan okunur; kod içindeki yol kuralı
       değişirse betik uydurma bir adrese değil, gerçek bağlantıya
       bakmaya devam etsin. */
    preg_match_all('~href="/tamga/([^"?\#]+)~', $ana['govde'], $m);
    $bcid = $m[1][0] ?? ($kodlar[0] ?? '');
    den('ana sayfada yazı bağlantısı bulundu', $bcid !== '', $bcid);

    $s = ist('/tamga/' . rawurlencode($bcid));
    den('makale sayfası açılıyor: /tamga/' . $bcid, $s['kod'] === 200, 'HTTP ' . $s['kod']);
    $sayfa = $s['govde'];

    /* Birebir dizge aranır: mühür sayfada gerçekten basılmış mı, yoksa
       yalnızca kutusu mu var. */
    den('sağ sütun mührü (72 piksel) sayfada basılı', strpos($sayfa, mh_muhur($bcid, 72)) !== false);
    den('dar ekran künye mührü (44 piksel) sayfada basılı', strpos($sayfa, mh_muhur($bcid, 44)) !== false);
    den('mührün altında tamga kodu metin olarak yazıyor',
        (bool)preg_match('#ys-muhur-kod[^>]*>' . preg_quote($bcid, '#') . '#', $sayfa));
    den('mühür kutusunun açıklaması basılı',
        strpos($sayfa, 'Koddan üretilen mühür') !== false || strpos($sayfa, 'Seal generated from the code') !== false);
    den('sayfada mühür için biriciklik sözü geçmiyor',
        !preg_match('#(biricik|eşsiz)#ui', (string)preg_replace('#\s+#', ' ', $sayfa)));

    $agirlik = strlen($sayfa);
    $muhurBayt = strlen(mh_muhur($bcid, 72)) + strlen(mh_muhur($bcid, 44));
    olc('makale sayfası ağırlığı', sprintf('%.1f KB, mühürler %d bayt (%%%.2f)',
        $agirlik / 1024, $muhurBayt, 100 * $muhurBayt / max($agirlik, 1)));

    /* Aynı mühür iki kez basılıyor; ikisi de aynı koddan geldiği için
       aynı çizimi taşımalı, yoksa okur iki ayrı işaret görür. */
    den('iki mühür de aynı çizimi taşıyor', $icAl(mh_muhur($bcid, 72)) === $icAl(mh_muhur($bcid, 44)));
}

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
