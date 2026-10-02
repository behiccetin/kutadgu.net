<?php
/* =====================================================================
   KUTADGU - Tamga mührü / The tamga seal
   ---------------------------------------------------------------------
   Bir çalışmanın tamga kodundan, o koda özgü küçük bir mühür çizer.
   Kod aynıysa mühür her zaman aynıdır: rastgelelik yoktur, çizim
   doğrudan kodun özetinden okunur. Bu sistemin tamamı "yayımlanmış bir
   kayıt geriye dönük değiştirilmez" ilkesi üzerine kuruludur; mühür de
   o kaydın bir parçasıysa değişmemelidir.

   BİRİCİKLİK İDDİASI YOKTUR VE OLMAMALIDIR. Mühür bir kimlik değil,
   kimliğin görünen yüzüdür. Kimlik, mührün yanında yazan tamga kodudur;
   denetim hanesi taşır, yanlış yazılırsa anlaşılır. İki ayrı çalışmanın
   mührü birbirine benzeyebilir, tıpkı iki insanın gölgesinin
   benzeyebileceği gibi. Çakışma oranı tahmin edilmez, dosyanın sonunda
   çizimden ölçülür (mh_cakisma_olasiligi, mh_cakisma_beklentisi);
   sinama/muhur-kapi.php aynı sayıyı bağımsız olarak da ölçer. Okurun
   gördüğü metinde "koddan üretilir" denir, "biriciktir" denmez.

   NEDEN ÜRETİLİYOR. Sayfada tamganın kimliği yalnızca logoda ve makale
   sayfasında görünüyordu; ölçümde iç sayfalarda tamga simgesi sıfır
   çıktı. Mühür, kimliği kayda bağlar: her çalışmanın kendi işareti olur
   ve o işaret koddan gelir, tasarımcının keyfinden değil.

   ÇİZİM DİLİ. Öğeler Türk-Orta Asya damga geleneğinden alınmış basit
   çizgilerdir: çubuk, çatal, hilal, üçgen, halka, nokta. Hepsi aynı
   kalınlıkta, aynı ızgarada ve yalnızca dik açılarda durur. Rastgele
   görünmemesinin nedeni budur: seçim rastgele, dil değil.

   Renk taşımaz: currentColor kullanır, bulunduğu yerin rengini alır.
   Böylece açık ve koyu temada ayrı bir iş gerekmez.
   ===================================================================== */

if (!function_exists('mh_muhur')) {

    /* Koddan sayı dizisi: sha256'nın baytları. Aynı kod, aynı dizi. */
    function mh_tohum(string $kod): array {
        $h = hash('sha256', 'kutadgu-muhur|' . strtoupper(trim($kod)), true);
        $b = [];
        for ($i = 0, $n = strlen($h); $i < $n; $i++) $b[] = ord($h[$i]);
        return $b;
    }

    /* Gövde: mührün ortasında duran ana işaret. Sekiz seçenek. */
    function mh_govde(int $i): string {
        $g = [
            /* çubuk ve kol */
            'M32 19V45 M22 29H42',
            /* çatal */
            'M32 45V32 M32 32L22 21 M32 32L42 21',
            /* hilal ve sap */
            'M32 45V26 M22 26a10 10 0 0 0 20 0',
            /* baklava */
            'M32 19L43 32L32 45L21 32Z',
            /* sap üstünde üçgen */
            'M32 45V35 M21 35L32 21L43 35Z',
            /* çifte ok */
            'M21 27L32 37L43 27 M21 36L32 46L43 36',
            /* halka ve eksen */
            'M32 19V45 M42 32a10 10 0 1 0-20 0a10 10 0 1 0 20 0',
            /* zikzak */
            'M21 22L32 32L21 42 M32 22L43 32L32 42',
        ];
        return $g[$i % count($g)];
    }

    /* Üst ve alt süs. Yedi seçenek; ilki boş, çünkü sadelik de bir seçim. */
    function mh_sus(int $i, bool $alt): string {
        /* Süs iç halkanın (r=20.5) içinde kalmalı. En uzun süs olan haç
           merkezden dört piksel taşar; 17 seçilirse en dış nokta r=19'da
           kalır ve halkayı kesmez. */
        $y = $alt ? 47 : 17;      /* süsün oturduğu yükseklik */
        $d = $alt ? -1 : 1;       /* aşağıdaysa ters çevrilir */
        $s = [
            '',
            "M32 {$y}m0 0",                                   /* tek nokta, aşağıda çizilir */
            'M26 ' . $y . 'H38',                              /* kısa çubuk */
            'M25 ' . $y . 'h2 M31 ' . $y . 'h2 M37 ' . $y . 'h2',   /* üç kertik */
            'M26 ' . ($y + 2 * $d) . 'L32 ' . ($y - 4 * $d) . 'L38 ' . ($y + 2 * $d),  /* çatı */
            'M32 ' . ($y - 4 * $d) . 'V' . ($y + 4 * $d) . ' M28 ' . $y . 'H36',       /* haç */
            'M26 ' . $y . 'a6 6 0 0 ' . ($alt ? '0' : '1') . ' 12 0',                  /* kavis */
        ];
        return $s[$i % count($s)];
    }

    /* Süs "tek nokta" ise daire olarak çizilir, çizgi olarak değil. */
    function mh_sus_nokta(int $i): bool { return ($i % 7) === 1; }

    /* Dış halkanın kertikleri. Düzenli aralıklarla dizilir: rastgele
       serpiştirilmiş kertikler gürültü gibi görünür, düzenli olanlar
       işaret gibi. Adım on ikinin bölenlerinden seçilir. */
    function mh_kertik(int $adim, int $kayma, int $uzun): string {
        $adimlar = [1, 2, 3, 4, 6];
        $a = $adimlar[$adim % count($adimlar)];
        /* Yarıçaplar kutuya sığacak biçimde seçilir: kertiğin dış ucu
           çizgi kalınlığıyla birlikte 64'lük kutunun kenarına değmemeli,
           yoksa mühür kırpılır. En dış nokta 29.5 + yarım çizgi = 30.3. */
        $u = $uzun % 2 ? 5.5 : 3.5;
        $ic = 24.0; $dis = $ic + $u;
        $d = '';
        for ($k = 0; $k < 12; $k += $a) {
            $t = deg2rad(($k + ($kayma % 12)) * 30.0);
            $x1 = 32 + $ic * sin($t);  $y1 = 32 - $ic * cos($t);
            $x2 = 32 + $dis * sin($t); $y2 = 32 - $dis * cos($t);
            $d .= sprintf('M%.1f %.1fL%.1f %.1f ', $x1, $y1, $x2, $y2);
        }
        return trim($d);
    }

    /* Mühür iki bölümden kurulur. DIŞ bölüm (halkalar ve kertikler) hiç
       dönmez; İÇ bölüm (gövde ve iki süs) bir bütün olarak döner. İkisi
       de tek bir yerden üretilir, çünkü aşağıdaki sayım işlevleri aynı
       yeri çağırır: çizim ile sayım ayrı yerlerde yazılırsa biri
       değişince öteki sessizce yalan söylemeye başlar. */
    function mh_ic_grup(int $govdeNo, int $ustNo, int $altNo, int $donme): string {
        $ust = mh_sus($ustNo, false);
        $alt = mh_sus($altNo, true);
        $ic  = '<path d="' . mh_govde($govdeNo) . '" />';
        if ($ust !== '') {
            $ic .= mh_sus_nokta($ustNo)
                ? '<circle cx="32" cy="17" r="2" fill="currentColor" stroke="none" />'
                : '<path d="' . $ust . '" stroke-width="1.8" />';
        }
        if ($alt !== '') {
            $ic .= mh_sus_nokta($altNo)
                ? '<circle cx="32" cy="47" r="2" fill="currentColor" stroke="none" />'
                : '<path d="' . $alt . '" stroke-width="1.8" />';
        }
        /* Dönme yalnızca ortadaki işareti çevirir; halka ve kertikler
           yerinde kalır, yoksa mühür eğrilmiş gibi görünür. */
        return '<g transform="rotate(' . $donme . ' 32 32)">' . $ic . '</g>';
    }

    function mh_dis_grup(bool $icHalka, string $kertik): string {
        $p = '<circle cx="32" cy="32" r="24" />';
        if ($icHalka) $p .= '<circle cx="32" cy="32" r="20.5" stroke-width="1" />';
        if ($kertik !== '') $p .= '<path d="' . $kertik . '" stroke-width="1.6" />';
        return $p;
    }

    /* Mührün kendisi. $boy piksel cinsinden kenar uzunluğudur. */
    function mh_muhur(string $kod, int $boy = 64, string $sinif = ''): string {
        $kod = trim($kod);
        if ($kod === '') return '';
        $t = mh_tohum($kod);

        $govde = mh_dis_grup(($t[6] % 2) === 1, mh_kertik($t[3], $t[4], $t[5]))
               . mh_ic_grup($t[0] % 8, $t[1] % 7, $t[2] % 7, ($t[7] % 4) * 90);

        $sn = $sinif !== '' ? ' class="' . htmlspecialchars($sinif, ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<svg' . $sn . ' width="' . $boy . '" height="' . $boy . '" viewBox="0 0 64 64" '
             . 'fill="none" stroke="currentColor" stroke-width="2.2" '
             . 'stroke-linecap="round" stroke-linejoin="round" '
             . 'aria-hidden="true" focusable="false">' . $govde . '</svg>';
    }

    /* =================================================================
       SAYIM. Buradan aşağısı bir sayfa çizilirken hiç çalışmaz; yalnızca
       "kaç ayrı mühür var" ve "iki mühür ne sıklıkla aynı çıkar" diye
       soran sınamalar ve metinler çağırır.

       ÜÇ AYRI SAYI VARDIR VE BİRBİRİNİN YERİNE GEÇMEZLER:

       1) mh_dizge_sayisi()          : üretilebilecek ayrı SVG DİZGESİ.
          Seçim uzayının boyu. Kaç ayrı mühür GÖRÜNDÜĞÜNÜ söylemez.
       2) mh_gorunus_sayisi()        : GÖZLE ayrı mühür. Okurun gördüğü
          budur; bir mührün kaç ayrı hâli olduğu sorulduğunda verilecek
          sayı budur.
       3) mh_etkin_gorunus_sayisi()  : ÇAKIŞMA bakımından etkin sayı.
          Görünüşler eşit sıklıkta gelmediği için çakışma, gözle ayrı
          sayının verdiğinden daha sıktır. Bir çakışma oranı ya da
          "şu kadar çalışmada iki mühür aynı çıkar mı" hesabı YALNIZCA
          bu sayıya dayanabilir.

       DİZGE SAYISI NEDEN GÖRÜNÜŞ SAYISI DEĞİL. Bu işlev bir zamanlar
       seçeneklerin çarpımını (376.320) döndürüyordu ve adı "görünüş
       sayısı" idi. Çarpım yanlış değil, sorusu yanlıştı: ayrı seçim
       her zaman ayrı resim vermiyor. İki yerde birleşiyor:

       a) KERTİK ADIMI. Kertikler on iki yuvaya adım adım diziliyor ve
          adım hep on ikinin bir böleni (1, 2, 3, 4, 6). Böyle olunca
          kayma ancak adım kadar ayrı sonuç verebiliyor: adım 1 ise on
          iki kertiğin hepsi çizildiği için on iki kaymanın hepsi aynı
          resmi veriyor. 5 x 12 = 60 seçim, 16 ayrı kertik dizilişi.
       b) BAKIŞIMLI GÖVDE. Baklava ile halka-eksen gövdeleri 180 derece
          döndürülünce kendilerine eşit. Üst süs 180 derece dönünce alt
          süsün ta kendisi olduğu için, bu iki gövdede
          (üst=i, alt=j, dönme=d) ile (üst=j, alt=i, dönme=d+180) aynı
          resmi veriyor. 1.568 iç bölüm seçimi, 1.372 ayrı iç bölüm.

       Sayılar elle yazılmaz, çizimin kendisinden ölçülür: her seçim
       gerçekten çizilir, çizim kanonik bir "resim anahtarına" çevrilir
       ve ayrı anahtarlar sayılır. Geometri değişirse sayı da değişir.
       Elle yazılmış olsaydı, bir gövde eklendiği gün sessizce yalan
       olurdu.

       BUGÜNKÜ DEĞERLER (bilgi içindir, hesap yukarıdaki işlevlerden
       gelir; bu satırlara bakıp sayı yazma, işlevi çağır):
         dizge 376.320, gözle ayrı 87.808, etkin 55.196.
         İki rastgele kodda gözle aynı mühür: %0,0018 (yaklaşık 1/55.200).
         Altmış kodluk bir arşivde en az bir çakışma: %3,2.
       Yüz bin sentetik kodla ölçülen çakışma 1/55.242 çıktı; sayım ile
       ölçüm binde bir içinde tutuyor.
       ================================================================= */

    /* Bir SVG gövdesinin kanonik resim anahtarı: çizgi ve yay listesi.
       Sıra, yön, parçalanma ve kayan noktalı artık silinir; kalan şey
       kâğıtta duran mürekkeptir. İki mühür aynı çizgilerden ve aynı
       yaylardan kuruluysa gözle de aynıdır.

       Uçlar tam sayıya yuvarlanır: kertik uçları %.1f ile yazıldığı için
       aynı yer kimi hesapta 17.2, kimi hesapta 17.3 çıkıyor. Okur bunu
       görmez, anahtar da görmemeli. Yuvarlama ayrı kertikleri
       birleştirmez, çünkü komşu kertikler otuz derece, yani birkaç
       piksel uzaktadır.

       Bu anahtar tam bir ölçüt değil, sağlam bir alt sınırdır: üst üste
       binen çizgiler gibi başka birleşmeler kuramda kalabilir. Bu çizim
       dilinde öyle bir durum yok. */
    function mh_resim_anahtari(string $svg): string {
        $cik = [];
        $isle = function (string $par, int $donme) use (&$cik): void {
            preg_match_all('#<(circle|path)\b([^>]*)/>#', $par, $m, PREG_SET_ORDER);
            foreach ($m as $e) {
                $oz  = $e[2];
                $kal = preg_match('#stroke-width="([\d.]+)"#', $oz, $q) ? (float)$q[1] : 2.2;
                $dolu = strpos($oz, 'fill="currentColor"') !== false;
                if ($e[1] === 'circle') {
                    preg_match('#cx="([\d.-]+)"#', $oz, $a);
                    preg_match('#cy="([\d.-]+)"#', $oz, $b);
                    preg_match('#\sr="([\d.-]+)"#', $oz, $c);
                    mh_resim_yay_yaz((float)$a[1], (float)$b[1], (float)$c[1],
                        0.0, 360.0, $kal, $donme, $dolu, $cik);
                } else {
                    preg_match('#d="([^"]*)"#', $oz, $a);
                    mh_resim_yol($a[1], $kal, $donme, $cik);
                }
            }
        };
        /* Dönen bölüm önce ayrılır: dönme, içindeki her şeye uygulanır. */
        $kalan = preg_replace_callback(
            '#<g transform="rotate\((\d+) 32 32\)">(.*?)</g>#s',
            function (array $m) use ($isle): string {
                $isle($m[2], ((int)$m[1] % 360 + 360) % 360);
                return '';
            }, $svg);
        $isle((string)$kalan, 0);
        $cik = array_values(array_unique($cik));
        sort($cik);
        return implode('|', $cik);
    }

    /* Dörtte bir dönüşler tam sayıları tam sayıya taşır: yuvarlama
       artığı doğmaz, bu yüzden dönme kanonik biçimde uygulanabilir. */
    function mh_resim_don(float $x, float $y, int $donme): array {
        if ($donme === 90)  return [64.0 - $y, $x];
        if ($donme === 180) return [64.0 - $x, 64.0 - $y];
        if ($donme === 270) return [$y, 64.0 - $x];
        return [$x, $y];
    }

    /* Doğru parçası: uçlar sıralanır, böylece hangi uçtan çizildiği
       anahtarı değiştirmez. */
    function mh_resim_cizgi(float $x0, float $y0, float $x1, float $y1,
                            float $kal, int $donme, array &$cik): void {
        [$ax, $ay] = mh_resim_don(round($x0), round($y0), $donme);
        [$bx, $by] = mh_resim_don(round($x1), round($y1), $donme);
        if ($ax === $bx && $ay === $by) return;
        $u = sprintf('%d,%d', $ax, $ay);
        $v = sprintf('%d,%d', $bx, $by);
        if (strcmp($u, $v) > 0) { $w = $u; $u = $v; $v = $w; }
        $cik[] = sprintf('C %s %s %.1f', $u, $v, $kal);
    }

    /* Yay: merkez, yarıçap, başlangıç açısı ve süpürme. Yay hep artı
       yönde yazılır, yani ters çizilmiş aynı yay aynı anahtarı verir. */
    function mh_resim_yay(float $x0, float $y0, float $r, int $genis, int $yon,
                          float $x1, float $y1, float $kal, int $donme, array &$cik): void {
        $ax = ($x0 - $x1) / 2; $ay = ($y0 - $y1) / 2;
        $kk = $ax * $ax + $ay * $ay;
        if ($kk <= 0.0) return;
        if ($kk > $r * $r) $r = sqrt($kk);
        $f = ($r * $r - $kk) / $kk;
        $f = $f < 0 ? 0.0 : sqrt($f);
        if ($genis === $yon) $f = -$f;
        $mx = $f * $ay + ($x0 + $x1) / 2;
        $my = -$f * $ax + ($y0 + $y1) / 2;
        $t0 = rad2deg(atan2($y0 - $my, $x0 - $mx));
        $t1 = rad2deg(atan2($y1 - $my, $x1 - $mx));
        $dt = $t1 - $t0;
        if ($yon === 0 && $dt > 0) $dt -= 360.0;
        if ($yon === 1 && $dt < 0) $dt += 360.0;
        if ($dt < 0) { $t0 = $t1; $dt = -$dt; }
        mh_resim_yay_yaz($mx, $my, $r, $t0, $dt, $kal, $donme, false, $cik);
    }

    function mh_resim_yay_yaz(float $mx, float $my, float $r, float $t0, float $dt,
                              float $kal, int $donme, bool $dolu, array &$cik): void {
        [$px, $py] = mh_resim_don(round($mx), round($my), $donme);
        $bas = (int)round($t0) + $donme;
        $bas = (($bas % 360) + 360) % 360;
        /* Tam çemberin başlangıç açısı yoktur; hepsi sıfırdan yazılır. */
        if ((int)round($dt) >= 360) $bas = 0;
        $cik[] = sprintf('%s %d,%d %.1f %d %d %.1f', $dolu ? 'D' : 'Y',
            $px, $py, round($r, 1), $bas, (int)round($dt), $kal);
    }

    /* Yol verisini parçalara ayırır. Yalnızca mührün kullandığı komutlar
       tanınır: M/m, L/l, H/h, V/v, A/a, Z/z. */
    function mh_resim_yol(string $d, float $kal, int $donme, array &$cik): void {
        preg_match_all('#[a-zA-Z]|-?\d*\.?\d+#', $d, $m);
        $t = $m[0]; $n = count($t); $i = 0;
        $cx = 0.0; $cy = 0.0; $bx = 0.0; $by = 0.0; $ko = '';
        while ($i < $n || $ko === 'Z' || $ko === 'z') {
            if ($i < $n && ctype_alpha($t[$i])) { $ko = $t[$i]; $i++; }
            elseif ($ko === 'M') $ko = 'L';   /* M'den sonraki sayı çifti çizgidir */
            elseif ($ko === 'm') $ko = 'l';
            if ($ko === 'Z' || $ko === 'z') {
                mh_resim_cizgi($cx, $cy, $bx, $by, $kal, $donme, $cik);
                $cx = $bx; $cy = $by; $ko = '';
                continue;
            }
            if ($i >= $n) break;
            switch ($ko) {
                case 'M': $cx = (float)$t[$i]; $cy = (float)$t[$i + 1];
                          $bx = $cx; $by = $cy; $i += 2; break;
                case 'm': $cx += (float)$t[$i]; $cy += (float)$t[$i + 1];
                          $bx = $cx; $by = $cy; $i += 2; break;
                case 'L': $x = (float)$t[$i]; $y = (float)$t[$i + 1];
                          mh_resim_cizgi($cx, $cy, $x, $y, $kal, $donme, $cik);
                          $cx = $x; $cy = $y; $i += 2; break;
                case 'l': $x = $cx + (float)$t[$i]; $y = $cy + (float)$t[$i + 1];
                          mh_resim_cizgi($cx, $cy, $x, $y, $kal, $donme, $cik);
                          $cx = $x; $cy = $y; $i += 2; break;
                case 'H': $x = (float)$t[$i];
                          mh_resim_cizgi($cx, $cy, $x, $cy, $kal, $donme, $cik);
                          $cx = $x; $i++; break;
                case 'h': $x = $cx + (float)$t[$i];
                          mh_resim_cizgi($cx, $cy, $x, $cy, $kal, $donme, $cik);
                          $cx = $x; $i++; break;
                case 'V': $y = (float)$t[$i];
                          mh_resim_cizgi($cx, $cy, $cx, $y, $kal, $donme, $cik);
                          $cy = $y; $i++; break;
                case 'v': $y = $cy + (float)$t[$i];
                          mh_resim_cizgi($cx, $cy, $cx, $y, $kal, $donme, $cik);
                          $cy = $y; $i++; break;
                case 'A': case 'a':
                    $r = (float)$t[$i]; $genis = (int)$t[$i + 3]; $yon = (int)$t[$i + 4];
                    $x = (float)$t[$i + 5]; $y = (float)$t[$i + 6];
                    if ($ko === 'a') { $x += $cx; $y += $cy; }
                    mh_resim_yay($cx, $cy, $r, $genis, $yon, $x, $y, $kal, $donme, $cik);
                    $cx = $x; $cy = $y; $i += 7; break;
                default: $i++; break;
            }
        }
    }

    /* Bir bayt kalanının ne sıklıkla hangi seçeneği verdiği. Seçimler
       sha256'nın baytlarından okunuyor; 256 bölene tam bölünmüyorsa
       (7, 5, 12) kimi seçenek ötekinden biraz daha sık geliyor ve bu,
       çakışmayı düzgün dağılıma göre yukarı çekiyor. */
    function mh_agirlik(int $bolen): array {
        $w = array_fill(0, $bolen, 0);
        for ($b = 0; $b < 256; $b++) $w[$b % $bolen]++;
        return array_map(fn($c) => $c / 256, $w);
    }

    /* Bütün seçim uzayını çizerek dolaşır; her sınıfın olasılığını da
       toplar. Mühür üç bağımsız bölgeden kuruludur ve bölgeler üst üste
       binmez (iç işaret r<=19'da kalır, iç halka r=20.5, kertikler
       r>=24'ten dışarı), bu yüzden bölüm bölüm sayıp çarpmak bütünü
       saymakla birebir aynı sonucu verir. Bütünü tek tek dolaşmak
       376.320 çizim demek olurdu; bölüm bölüm 1.688 çizim yetiyor.

       Sonuç bir kez hesaplanıp saklanır: aynı süreçte ikinci çağrı
       bedavadır. */
    function mh_sayim(): array {
        static $bellek = null;
        if ($bellek !== null) return $bellek;

        $wGovde = mh_agirlik(8);  $wSus  = mh_agirlik(7);
        $wAdim  = mh_agirlik(5);  $wKay  = mh_agirlik(12);
        $wIki   = mh_agirlik(2);  $wDort = mh_agirlik(4);

        $icGoz = []; $icHam = [];
        for ($a = 0; $a < 8; $a++) {
            for ($b = 0; $b < 7; $b++) {
                for ($c = 0; $c < 7; $c++) {
                    for ($h = 0; $h < 4; $h++) {
                        $on = $wGovde[$a] * $wSus[$b] * $wSus[$c] * $wDort[$h];
                        $s  = mh_ic_grup($a, $b, $c, $h * 90);
                        $k  = mh_resim_anahtari($s);
                        $icGoz[$k] = ($icGoz[$k] ?? 0) + $on;
                        $icHam[$s] = ($icHam[$s] ?? 0) + $on;
                    }
                }
            }
        }
        $kerGoz = []; $kerHam = [];
        for ($d = 0; $d < 5; $d++) {
            for ($e = 0; $e < 12; $e++) {
                for ($f = 0; $f < 2; $f++) {
                    $on = $wAdim[$d] * $wKay[$e] * $wIki[$f];
                    $s  = mh_kertik($d, $e, $f);
                    $k  = mh_resim_anahtari('<path d="' . $s . '" stroke-width="1.6" />');
                    $kerGoz[$k] = ($kerGoz[$k] ?? 0) + $on;
                    $kerHam[$s] = ($kerHam[$s] ?? 0) + $on;
                }
            }
        }
        $halka = [$wIki[0], $wIki[1]];
        $kare  = fn(array $p) => array_sum(array_map(fn($x) => $x * $x, $p));

        $bellek = [
            'dizge'     => count($icHam) * count($kerHam) * count($halka),
            'gorunus'   => count($icGoz) * count($kerGoz) * count($halka),
            /* İki rastgele kodun aynı şeyi vermesi olasılığı: olasılık
               karelerinin toplamı. Bağımsız bölümlerde çarpılır. */
            'cakismaDizge' => $kare($icHam) * $kare($kerHam) * $kare($halka),
            'cakismaGoz'   => $kare($icGoz) * $kare($kerGoz) * $kare($halka),
        ];
        return $bellek;
    }

    /* Üretilebilecek ayrı SVG dizgesi. Bu sayı bir görünüş sayısı
       DEĞİLDİR; yalnızca seçim uzayının boyunu merak eden yer kullanır. */
    function mh_dizge_sayisi(): int {
        return mh_sayim()['dizge'];
    }

    /* Gözle ayrı mühür sayısı. "Mührün kaç ayrı hâli var" sorusunun
       cevabı budur. Çakışma hesabı için bu sayıyı KULLANMA; görünüşler
       eşit sıklıkta gelmiyor, aşağıdaki etkin sayıyı kullan. */
    function mh_gorunus_sayisi(): int {
        return mh_sayim()['gorunus'];
    }

    /* Çakışma bakımından etkin görünüş sayısı: eşit sıklıkta gelen kaç
       görünüş aynı çakışma oranını verirdi. Gözle ayrı sayıdan küçüktür
       ve çakışmayla ilgili her cümle bunun üzerine kurulur. */
    function mh_etkin_gorunus_sayisi(): int {
        return (int)round(1 / mh_sayim()['cakismaGoz']);
    }

    /* İki rastgele kodun gözle aynı mührü taşıma olasılığı. */
    function mh_cakisma_olasiligi(): float {
        return mh_sayim()['cakismaGoz'];
    }

    /* $adet kodluk bir kümede en az bir çift mührün gözle aynı çıkma
       olasılığı. Okura gösterilecek "şu kadar çalışmada..." cümlesi bu
       işlevden üretilmeli, elle yazılmamalı: arşiv büyüdükçe sayı
       kendiliğinden büyür. */
    function mh_cakisma_beklentisi(int $adet): float {
        if ($adet < 2) return 0.0;
        return 1 - exp(-($adet * ($adet - 1) / 2) * mh_sayim()['cakismaGoz']);
    }
}
