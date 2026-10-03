<?php
/* =====================================================================
   ÇEVİRİ SÖZLÜĞÜNÜ KAYNAKTAN ÇIKARIR. Depoya girmez.

   Arayüzdeki her k_c('Türkçe', 'English') çağrısı bir dizedir ve
   üçüncü bir dil açıldığında bir insanın karşılığını yazması gereken
   şey tam olarak bu dizelerdir. Bu betik onları sayar ve boş bir
   sözlük dosyası üretir.

   ANAHTAR İNGİLİZCE DİZEDİR. Bir özet (hash) olabilirdi; olmadı,
   çünkü çevirmenin gördüğü ilk şey anahtardır ve anlaşılmaz bir
   anahtar çevirmeni kör bırakır. Yalnız 120 harften uzun metinler
   kısaltılır (ortak.php, tg_ceviri_anahtar).

   DİNAMİK ÇAĞRILAR ayrıca sayılır: k_c('şu ' . $n . ' kadar', ...)
   biçimindeki çağrıların metni kaynakta bir bütün olarak durmaz, bu
   yüzden statik olarak çıkarılamaz. Sayıları gizlenmez: çıkarılamayan
   şeye "çıkarıldı" demek, bu projede en çok tekrarlanan ölçüm
   hatasıdır.

   Kullanım:
     php ceviri-cikar.php [kod dizini] [çıktı dosyası]
   ===================================================================== */
declare(strict_types=1);

$KOD    = $argv[1] ?? (getenv('KTEST_DIR') ?: '/home/claude/kg/ktest');
$CIKTI  = $argv[2] ?? '';

$dosyalar = [];
$yig = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($KOD, FilesystemIterator::SKIP_DOTS));
foreach ($yig as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') continue;
    if (strpos($f->getPathname(), '/.git/') !== false) continue;
    $dosyalar[] = $f->getPathname();
}
sort($dosyalar);

$sozluk = []; $dinamik = 0; $cagri = 0; $ktCagri = 0; $takmaCagri = 0; $takmaDize = 0;
$dosyaSay = [];

/* Anahtar kuralı ortak.php'deki tg_ceviri_anahtar ile AYNI olmalıdır;
   ayrı yazılırsa bir gün ayrı davranır ve sözlük sessizce ıskalar. */
function tg_cikar_anahtar(string $en): string {
    $t = trim(preg_replace('/\s+/u', ' ', $en));
    return mb_strlen($t, 'UTF-8') <= 120 ? $t : ('#' . substr(sha1($t), 0, 16));
}

/* k_t(['tr' => '…', 'en' => '…']) çağrısının iki değerini çıkarır.
   Dönen üçüncü değer: ikisi de düz dize miydi. */
function tg_dizi_ciftini_al(array $t, int $j, int $n): array {
    $deger = ['tr' => '', 'en' => ''];
    $duz   = ['tr' => false, 'en' => false];
    $suan = '';                  /* şu an hangi anahtarın değerini topluyoruz */
    $bekle = false;              /* '=>' görüldü mü */
    $derinlik = 0;
    for ($p = $j; $p < $n; $p++) {
        $x = $t[$p];
        if ($x === '(') { $derinlik++; if ($derinlik === 1) continue; }
        if ($x === ')') { $derinlik--; if ($derinlik === 0) break; }
        if (is_array($x) && ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT || $x[0] === T_DOC_COMMENT)) continue;
        if (is_array($x) && $x[0] === T_DOUBLE_ARROW) { $bekle = true; continue; }
        if ($x === ',') { $suan = ''; $bekle = false; continue; }
        if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
            $s = stripcslashes(substr($x[1], 1, -1));
            if (!$bekle) {                       /* anahtar tarafı */
                if ($s === 'tr' || $s === 'en') { $suan = $s; $duz[$s] = true; }
                else $suan = '';
                continue;
            }
            if ($suan !== '') $deger[$suan] .= $s;
            continue;
        }
        if ($x === '.') continue;                /* birleştirme */
        if ($bekle && $suan !== '') $duz[$suan] = false;  /* değişken karıştı */
    }
    return [$deger['tr'], $deger['en'], $duz['tr'] && $duz['en']];
}

foreach ($dosyalar as $d) {
    $t = token_get_all((string)file_get_contents($d));
    $n = count($t);
    for ($i = 0; $i < $n; $i++) {
        $ad = is_array($t[$i]) ? ($t[$i][0] === T_STRING ? $t[$i][1] : '') : '';
        /* tg_c / tg_t de sayılır: ortak.php ve k/ katmanı k_c'yi
           kullanamıyor (kabuk onları kendinden önce yükler) ve aynı işi
           tg_c ile yapıyor. Çevirmenin çalışacağı liste eksik kalırsa,
           o dizelerin karşılığı hiçbir zaman yazılmaz. */
        /* TAKMA ADLA ÇAĞRI DA SAYILIR: parça dosyaları k_c'yi yerel bir
           değişkene alıp $c('…','…') diye çağırıyor. Belirteç çözümlemesi
           T_STRING aradığı için bunların hiçbiri görülmüyordu; k/ altındaki
           bütün parçalar sözlüğün dışında kalmıştı. İki düz dize argümanı
           olan her değişken çağrısı aday sayılır: yanlışlıkla fazladan bir
           dize almak, eksik almaktan ucuzdur. */
        $takma = false;
        if ($ad === '' && is_array($t[$i]) && $t[$i][0] === T_VARIABLE) { $ad = 'k_c'; $takma = true; }
        /* k_cd / tg_cd DE SAYILIR. Bunlar k_c'nin değişken taşıyan
           biçimidir ("... %1 tarihinde kapanır") ve ilk iki argümanları
           yine iki düz dizedir. Listeye alınmasaydı, tam da dile göre
           değişmesin diye %1'e çevrilen cümleler sözlükten düşerdi;
           yani kusuru düzeltirken sözlüğü kırardık. */
        if ($ad !== 'k_c' && $ad !== 'k_t' && $ad !== 'tg_c' && $ad !== 'tg_t'
            && $ad !== 'k_cd' && $ad !== 'tg_cd') continue;
        /* Ardından '(' gelmeli; gelmiyorsa bu bir tanım ya da başka bir
           kullanımdır. Ayrıca önünde '->' ya da '::' varsa yöntemdir. */
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n || $t[$j] !== '(') continue;
        $onc = $i - 1;
        while ($onc >= 0 && is_array($t[$onc]) && $t[$onc][0] === T_WHITESPACE) $onc--;
        if ($onc >= 0 && is_array($t[$onc]) && ($t[$onc][0] === T_OBJECT_OPERATOR || $t[$onc][0] === T_DOUBLE_COLON
             || $t[$onc][0] === T_FUNCTION)) continue;

        /* ---------------------------------------------------------------
           k_t / tg_t DE ÇIKARILIR — ÖNCEDEN ÇIKARILMIYORDU

           İlk yazımda bu iki çağrı yalnız SAYILIYOR, içindeki dizeler
           sözlüğe girmiyordu. Ölçüm "1822 dize, %100 çevrildi" diyordu
           ve doğru görünüyordu; ama Almanca sayfa açıldığında 371 dize
           İngilizce kalıyordu. Sebep şuydu: k_t(['tr'=>…, 'en'=>…])
           çağrısının İngilizce değeri de çalışma anında tam olarak aynı
           çeviri katmanına gider (k_t içinde tg_ceviri_bul). Yani bu
           dizeler çevrilmesi GEREKEN dizelerdi; yalnızca sayılmıyorlardı.

           Ölçümün kendisi yanlıştı, ölçülen şey değil. Bir dizinin
           çevrilmiş sayılabilmesi için önce SAYILMASI gerekir; sayılmayan
           dize hiçbir zaman eksik görünmez.

           Nasıl çıkarılır: argüman dizisinin içinde 'en' => "…" çiftini
           ararız. Değer birleştirilmiş dize parçalarından oluşabilir
           (uzun paragraflar kaynakta satırlara bölünmüştür); değişken
           içeriyorsa statik değildir ve dinamik sayılır.
           --------------------------------------------------------------- */
        if ($ad === 'k_t' || $ad === 'tg_t') {
            $ktCagri++;
            [$tr, $en, $duz] = tg_dizi_ciftini_al($t, $j, $n);
            if (!$duz || trim($en) === '') { $dinamik++; continue; }
            $anahtar = tg_cikar_anahtar($en);
            $kisa = str_replace($KOD . '/', '', $d);
            if (!isset($sozluk[$anahtar])) $sozluk[$anahtar] = ['en' => $en, 'tr' => $tr, 'dosya' => $kisa];
            $dosyaSay[$kisa] = ($dosyaSay[$kisa] ?? 0) + 1;
            continue;
        }
        if ($takma) $takmaCagri++; else $cagri++;

        /* İki argümanı topla: yalnız düz dizeler. Birleştirilmiş
           (concat) ya da değişken içeren argüman statik değildir. */
        $derinlik = 0; $arg = [['duz' => true, 'metin' => '']]; $k = 0;
        for ($p = $j; $p < $n; $p++) {
            $x = $t[$p];
            if ($x === '(') { $derinlik++; if ($derinlik === 1) continue; }
            if ($x === ')') { $derinlik--; if ($derinlik === 0) break; }
            if ($derinlik === 1 && $x === ',') { $k++; $arg[$k] = ['duz' => true, 'metin' => '']; continue; }
            if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
                $arg[$k]['metin'] .= stripcslashes(substr($x[1], 1, -1));
            } elseif (is_array($x) && ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT || $x[0] === T_DOC_COMMENT)) {
                continue;
            } elseif ($x === '.') {
                continue;                      /* birleştirme: dize parçaları yan yana */
            } else {
                $arg[$k]['duz'] = false;       /* değişken, işlev, sabit ... */
            }
        }
        $tr = $arg[0]['metin'] ?? ''; $en = $arg[1]['metin'] ?? '';
        $duz = ($arg[0]['duz'] ?? false) && ($arg[1]['duz'] ?? false);
        /* Takma adlı çağrı k_c olmayabilir (herhangi bir $x(...) çağrısı
           aday sayılıyor); düz iki dize değilse SESSİZCE atlanır, dinamik
           sayılmaz. Yoksa "statik çıkarılamayan" ölçüsü, çeviriyle hiç
           ilgisi olmayan yüzlerce çağrıyla şişer ve bir şey söylemez. */
        if (!$duz || trim($en) === '') { if (!$takma) $dinamik++; continue; }
        if ($takma) $takmaDize++;
        $anahtar = mb_strlen(trim(preg_replace('/\s+/u', ' ', $en)), 'UTF-8') <= 120
            ? trim(preg_replace('/\s+/u', ' ', $en))
            : ('#' . substr(sha1(trim(preg_replace('/\s+/u', ' ', $en))), 0, 16));
        $kisa = str_replace($KOD . '/', '', $d);
        /* Dizenin İLK görüldüğü dosya da yazılır. Çeviri paketi
           parçaları buna göre sıralar: kabuk ve ana sayfa önce, derin
           sayfalar sonra. Sıra yalnız uzunluğa göre yapılınca birinci
           parça ".", "K", "OR" gibi bağlamsız kırıntılarla doluyordu ve
           çevirmen ilk gördüğü şeyi çeviremiyordu. */
        if (!isset($sozluk[$anahtar])) $sozluk[$anahtar] = ['en' => $en, 'tr' => $tr, 'dosya' => $kisa];
        $dosyaSay[$kisa] = ($dosyaSay[$kisa] ?? 0) + 1;
    }
}

/* =====================================================================
   ÜÇÜNCÜ GEÇİŞ: VERİ TABLOLARINDAKİ 'tr'/'en' ÇİFTLERİ

   İkinci geçişten sonra bile Almanca sayfada 371 dize İngilizce
   kalıyordu. Sebep, çağrının kendisinde değil, çağrılan şeydeydi:

       $madde = ['tr' => '…', 'en' => '…'];   // ortak.php'de bir tablo
       …
       echo tg_t($madde);                      // burada çevrilir

   tg_t'nin argümanı bir DEĞİŞKENdir; metin çağrı yerinde durmaz. Token
   çözümlemesi ne kadar dikkatli yazılırsa yazılsın bunu izleyemez,
   çünkü izlenecek şey artık bir dize değil bir program akışıdır.

   Bu yüzden kural değiştirildi: çağrıyı değil, ÇİFTİ arıyoruz. Kaynakta
   yan yana duran bir 'tr' => "…" ve 'en' => "…" çifti, tanımı gereği
   arayüz metnidir; başka hiçbir şey iki dilde birden yazılmaz.

   FAZLA TOPLAMAK UCUZ, EKSİK TOPLAMAK PAHALI. Bu kural yanlışlıkla
   birkaç dizeyi fazladan alabilir (bir tablo yalnız kayıt için iki
   dilde yazılmış olabilir). Fazla alınan dize sözlükte durur, hiçbir
   zaman aranmaz ve kimseye zarar vermez. Eksik alınan dize ise sayfada
   İngilizce görünür ve bunu kimse ölçmez — ilk yazımdaki kusur tam
   olarak buydu.
   ===================================================================== */
$ciftEk = 0;
foreach ($dosyalar as $d) {
    $t = token_get_all((string)file_get_contents($d));
    $n = count($t);
    /* Dizeyi topla: 'anahtar' => "parça" . "parça" biçiminde olabilir. */
    $degerAl = function (int $p) use ($t, $n): array {
        $metin = ''; $duz = true; $son = $p;
        for ($q = $p; $q < $n; $q++) {
            $x = $t[$q];
            if (is_array($x) && ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT || $x[0] === T_DOC_COMMENT)) continue;
            if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
                $metin .= stripcslashes(substr($x[1], 1, -1)); $son = $q;
                /* Ardından '.' gelmiyorsa dize bitti. */
                $r = $q + 1;
                while ($r < $n && is_array($t[$r]) && $t[$r][0] === T_WHITESPACE) $r++;
                if ($r < $n && $t[$r] === '.') { $q = $r; continue; }
                break;
            }
            $duz = false; $son = $q; break;
        }
        return [$metin, $duz, $son];
    };

    /* ---- SIRAYA DAYALI ÇİFT: ['Türkçe metin', 'English text'] ----
       Değişmez ilkeler, aşama adları, alan başlıkları gibi tablolar
       çifti ANAHTARSIZ yazıyor: birinci öge Türkçe, ikinci İngilizce.
       Bu biçim de arayüz metnidir ve çalışma anında aynı katmandan
       geçer. Ayırt edici imza dar tutuldu: köşeli parantezin hemen
       ardından iki düz dize ve kapanış — arada başka hiçbir şey yok.
       Bu kalıp sıradan iki elemanlı dizileri de yakalar; onlar sözlüğe
       girer, hiç aranmaz ve kimseye zarar vermez. Eksik toplamak
       pahalı, fazla toplamak ucuzdur. */
    for ($i = 0; $i < $n - 1; $i++) {
        $ac = false;
        if ($t[$i] === '[') { $ac = true; $p = $i + 1; }
        elseif (is_array($t[$i]) && $t[$i][0] === T_ARRAY) {
            $p = $i + 1;
            while ($p < $n && is_array($t[$p]) && $t[$p][0] === T_WHITESPACE) $p++;
            if ($p < $n && $t[$p] === '(') { $ac = true; $p++; }
        }
        if (!$ac) continue;
        $par = []; $iyi = true;
        for ($q = $p; $q < $n && count($par) < 3; $q++) {
            $x = $t[$q];
            if (is_array($x) && ($x[0] === T_WHITESPACE || $x[0] === T_COMMENT || $x[0] === T_DOC_COMMENT)) continue;
            if ($x === ',') continue;
            if ($x === ']' || $x === ')') break;
            if (is_array($x) && $x[0] === T_CONSTANT_ENCAPSED_STRING) {
                $s = stripcslashes(substr($x[1], 1, -1));
                $r = $q + 1;
                while ($r < $n && is_array($t[$r]) && $t[$r][0] === T_WHITESPACE) $r++;
                while ($r < $n && $t[$r] === '.') {
                    $r++;
                    while ($r < $n && is_array($t[$r]) && $t[$r][0] === T_WHITESPACE) $r++;
                    if ($r < $n && is_array($t[$r]) && $t[$r][0] === T_CONSTANT_ENCAPSED_STRING) {
                        $s .= stripcslashes(substr($t[$r][1], 1, -1)); $q = $r; $r++;
                        while ($r < $n && is_array($t[$r]) && $t[$r][0] === T_WHITESPACE) $r++;
                    } else { $iyi = false; break; }
                }
                if (!$iyi) break;
                $par[] = $s;
                continue;
            }
            $iyi = false; break;
        }
        if (!$iyi || count($par) !== 2) continue;
        $en2 = trim($par[1]);
        if ($en2 === '' || mb_strlen($en2, 'UTF-8') < 3 || $par[0] === $par[1]) continue;
        $anahtar = tg_cikar_anahtar($par[1]);
        $kisa = str_replace($KOD . '/', '', $d);
        if (!isset($sozluk[$anahtar])) { $sozluk[$anahtar] = ['en' => $par[1], 'tr' => $par[0], 'dosya' => $kisa]; $ciftEk++; }
    }

    $sonTr = '';        /* en son görülen 'tr' değeri */
    $sonTrYer = -99;    /* ve kaçıncı belirteçteydi */
    for ($i = 0; $i < $n; $i++) {
        $x = $t[$i];
        if (!is_array($x) || $x[0] !== T_CONSTANT_ENCAPSED_STRING) continue;
        $anah = stripcslashes(substr($x[1], 1, -1));
        if ($anah !== 'tr' && $anah !== 'en') continue;
        $j2 = $i + 1;
        while ($j2 < $n && is_array($t[$j2]) && $t[$j2][0] === T_WHITESPACE) $j2++;
        if ($j2 >= $n || !is_array($t[$j2]) || $t[$j2][0] !== T_DOUBLE_ARROW) continue;
        [$deger, $duz, $son] = $degerAl($j2 + 1);
        if (!$duz || trim($deger) === '') { $i = $son; continue; }
        if ($anah === 'tr') { $sonTr = $deger; $sonTrYer = $i; $i = $son; continue; }
        /* 'en' bulundu. Eşi olan 'tr' aynı dizide, hemen öncesinde
           olmalı; arada 40 belirteçten çok varsa başka bir dizidir ve
           çift sayılmaz. Yine de dize alınır: eşsiz olsa da çevrilir. */
        $tr = ($i - $sonTrYer) <= 40 ? $sonTr : '';
        $anahtar = tg_cikar_anahtar($deger);
        $kisa = str_replace($KOD . '/', '', $d);
        if (!isset($sozluk[$anahtar])) { $sozluk[$anahtar] = ['en' => $deger, 'tr' => $tr, 'dosya' => $kisa]; $ciftEk++; }
        $dosyaSay[$kisa] = ($dosyaSay[$kisa] ?? 0) + 1;
        $i = $son;
    }
}

arsort($dosyaSay);
echo "== Arayüz dizeleri ==\n";
echo "  taranan dosya      : " . count($dosyalar) . "\n";
echo "  k_c çağrısı        : " . $cagri . "\n";
echo "  k_t çağrısı        : " . $ktCagri . "\n";
echo "  takma adla çağrı   : " . $takmaCagri . " (bunun " . $takmaDize . " tanesi iki düz dize taşıyor)\n";
echo "  BİRİCİK DİZE       : " . count($sozluk) . "   <- bir insanın çevireceği sayı\n";
echo "  statik çıkarılamayan: " . $dinamik . "   (metni değişken içeriyor; kaynakta bütün durmuyor)\n";
echo "  veri tablosundan ek  : " . $ciftEk . "   (tr/en çifti; çağrı yerinde durmayan metinler)\n";
echo "\n  en çok dize taşıyan dosyalar:\n";
$i = 0;
foreach ($dosyaSay as $f => $s) { echo sprintf("    %-24s %5d\n", $f, $s); if (++$i >= 10) break; }

if ($CIKTI !== '') {
    /* Boş sözlük: anahtar => '' . Çevirmen yalnız sağ tarafı doldurur.
       Yanına, okunabilirlik için Türkçe ve İngilizce kaynak metinleri
       taşıyan ayrı bir dosya bırakılır. */
    $bos = []; $kaynak = [];
    foreach ($sozluk as $a => $v) { $bos[$a] = ''; $kaynak[$a] = $v; }
    @mkdir(dirname($CIKTI), 0775, true);
    file_put_contents($CIKTI, json_encode($bos, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    file_put_contents(preg_replace('/\.json$/', '-kaynak.json', $CIKTI),
        json_encode($kaynak, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    echo "\n  yazıldı: " . $CIKTI . "\n";
    echo "  yazıldı: " . preg_replace('/\.json$/', '-kaynak.json', $CIKTI) . "\n";
}
