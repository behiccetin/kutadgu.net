<?php
/* =====================================================================
   KUTADGU - İstatistikler / Statistics
   ---------------------------------------------------------------------
   Sayıların hepsi arşivin kendisinden, sayfa açıldığı anda hesaplanır;
   elle tutulan bir tablo yoktur, dolayısıyla süslenemez. Hiçbir kişisel
   veri gösterilmez: yalnızca zaten çalışmaların sayfalarında görünen
   adlar ve sayılar kullanılır.

   Bu sayfa bir övünme tablosu değildir. Sistem küçükse sayılar küçük
   görünecektir; şeffaflığın anlamı da budur.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/parca.php';

$en      = k_en();
$yazilar = k_yazilar();
$say     = k_sayaclar();

/* ---------- Sayım ---------- */
$t = [
    'calisma' => 0, 'hakemli' => 0, 'hakemsiz' => 0,
    'aranan' => 0, 'suruyor' => 0, 'onayli' => 0, 'reddedildi' => 0,
    'rapor' => 0, 'serh' => 0, 'oylama_acik' => 0, 'oylama_kapali' => 0,
    'okuma' => 0, 'yz_beyan' => 0, 'veri_acik' => 0, 'etik' => 0,
];
$yillar = []; $yilIz = []; $alanlar = []; $alanliCalisma = 0; $kararlar = []; $hakemler = []; $yazarlar = []; $anahtarlar = [];
$enCok = [];
$bekGun = []; $ilkGun = []; $gonderimliKayit = 0;

foreach ($yazilar as $y) {
    if (!is_array($y)) continue;
    $t['calisma']++;
    /* Hakemlik basamağı.

       'tur' alanı çalışmanın hangi YOLDA olduğunu söyler, o yolun
       neresinde olduğunu değil. Sayfa bugüne kadar iki sayı gösteriyordu:
       "hakemli" ve "hakemsiz". Hakemliğe yeni açılmış, tek bir raporu
       bile gelmemiş bir çalışma "hakemli" sütununda duruyor ve okur
       bunu değerlendirmeden geçmiş sanıyordu. Bu yüzden dört basamak
       ayrı ayrı sayılır; hiçbiri gizlenmez, hiçbiri ötekinin yerine
       geçmez.

       Geri çekilmiş çalışma da kendi basamağında sayılır: geri çekilme,
       çalışmanın hangi değerlendirmeden geçtiğini silmez. Aşama işlevi
       böyle bir kayda 'cekildi' der; sayım için basamak kaydın kendi
       alanlarından çıkarılır ki dört sayının toplamı çalışma sayısını
       versin. Geri çekilenler aşağıda ayrıca sayılır. */
    $as = tg_hakem_asamasi($y);
    if ($as === 'cekildi') {
        /* Ölçüt 'tur' değil YOL GEÇMİŞİDİR: iki ret alan çalışmanın turu
           'yazi'ye döner ve bu satır onu 'hakemsiz' sütununa yazıyordu.
           tg_hakem_asamasi() ile aynı kaynağı kullanır. */
        $as = !tg_hakem_yolu_gormus($y) ? 'yok'
            : (tg_rapor_sayisi($y) === 0 ? 'aranan'
              : (tg_ret_esigi_doldu($y) ? 'reddedildi'
                : (tg_onay_durumu($y)['onayli'] ? 'onayli' : 'suruyor')));
    }
    /* BEŞ basamak. 'reddedildi' eklenmeden önce, iki hakemin
       reddettiği çalışma 'suruyor' sütununda duruyordu; sayfa onu
       "değerlendirmede" diye sayıyordu. Eşleşmeyen bir aşamanın
       'hakemsiz'e düşmesi de sessiz bir hatadır: yeni bir aşama
       eklenip buraya yazılmazsa sayı yanlış sütuna gider. */
    $basamak = ['yok' => 'hakemsiz', 'aranan' => 'aranan', 'suruyor' => 'suruyor',
                'onayli' => 'onayli', 'reddedildi' => 'reddedildi'];
    $t[$basamak[$as] ?? 'hakemsiz']++;
    /* "Hakemli" toplamı yalnızca en az bir raporu yayımlanmış
       çalışmaları sayar: hakem aranan çalışma bu toplamın dışındadır. */
    if ($as === 'suruyor' || $as === 'onayli') $t['hakemli']++;

    /* BEKLEME SÜRESİ. Bir yayın sisteminin en çok gizlemek isteyeceği
       sayı budur: çalışmalar burada ne kadar bekliyor. Sayıyı yayımlamak
       bir söz vermek değildir -- bu sistem hiçbir çalışmaya "şu kadar
       günde bitiririm" demez, çünkü hakemler gönüllüdür ve söz verilen
       bir sürenin tutulmaması, hiç söz vermemekten kötüdür. Sayı yalnızca
       olanı söyler. Ölçüm iki ayrı şeyi ayrı tutar:
         bekleyen -> hâlâ süren bekleme (aranan + değerlendirmede)
         ilkRapor -> gelmiş ilk raporun kaç günde geldiği (tamamlanmış
                     bekleme). İkisi karıştırılırsa "ortalama süre"
                     denen ve hiçbir şey anlatmayan tek bir sayı çıkar. */
    $gk = tg_gecikme($y);
    if ($gk['bekleme_gun'] !== null) $bekGun[] = ['g' => (int)$gk['bekleme_gun'], 'y' => $y];
    if ($gk['ilk_rapor_gun'] !== null) $ilkGun[] = (int)$gk['ilk_rapor_gun'];
    if (($gk['kaynak'] ?? '') === 'gonderim') $gonderimliKayit++;

    $oku = k_okuma_sayisi((string)($y['id'] ?? ''));
    $t['okuma'] += $oku;
    $enCok[] = ['y' => $y, 'oku' => $oku];

    if (preg_match('/^(\d{4})/', (string)($y['tarih'] ?? ''), $m)) {
        $yillar[$m[1]] = ($yillar[$m[1]] ?? 0) + 1;
        /* Yıl çizelgesi yalnızca "kaç çalışma" demiyor, "hangi yolda kaç
           çalışma" diyor. Üç iz, arşiv listesindeki üç süzgeçle AYNI
           ölçütten gelir (k_suz_tur); ayrı düşselerdi okur burada başka,
           listede başka bir sayı görürdü. */
        $iz = ($as === 'onayli' || $as === 'suruyor') ? 'hakemli'
            : (($as === 'aranan') ? 'aranan' : 'hakemsiz');
        if (!isset($yilIz[$m[1]])) $yilIz[$m[1]] = ['hakemli' => 0, 'aranan' => 0, 'hakemsiz' => 0];
        $yilIz[$m[1]][$iz]++;
    }

    foreach (tg_yazar_anahtarlari($y) as $ak) { if ($ak !== '') $yazarlar[$ak] = true; }

    /* Ana bilim alanı (FORD birinci basamak). Bir çalışma birden çok
       alana yazılmış olabilir; o zaman her alanda sayılır ve toplam
       çalışma sayısını AŞAR. Çizelgenin altında bu ayrıca yazılıdır,
       yoksa okur toplamı kendi başına çalışma sayısı sanar. */
    $anaGor = [];
    if (function_exists('al_kayit_kodlari')) {
        foreach (al_kayit_kodlari($y) as $ak) {
            $ilk = explode('.', (string)$ak)[0];
            if ($ilk !== '') $anaGor[$ilk] = true;
        }
    }
    /* Kayıtta FORD kodu yoksa 'alan' alanının kendi yazısı kullanılır.
       Arşivin ilk döneminde alanlar düz sözcükle yazıldı ve o kayıtlar
       değiştirilmedi. Kodu olmayanı hiç saymamak, çizelgeyi arşivin
       bir bölümüne kör bırakırdı; kodu varmış gibi göstermek ise
       olmayan bir kesinlik uydurmak olurdu. İkisi de yapılmıyor:
       ne varsa o sayılıyor. */
    if (!$anaGor) {
        foreach (preg_split('/[,;]+/u', (string)($y['alan'] ?? '')) as $ax) {
            $ax = trim((string)$ax);
            if ($ax !== '') $anaGor['~' . mb_strtolower($ax, 'UTF-8')] = true;
        }
    }
    foreach (array_keys($anaGor) as $ilk) $alanlar[$ilk] = ($alanlar[$ilk] ?? 0) + 1;
    if ($anaGor) $alanliCalisma++;

    foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
        if (!is_array($h)) continue;
        $ad = trim((string)($h['ad'] ?? ''));
        if ($ad !== '') $hakemler[tg_ad_anahtar($ad)] = true;
        if (trim((string)($h['rapor'] ?? '')) === '') continue;
        $t['rapor']++;
        $k = (string)($h['karar'] ?? '');
        if ($k !== '') $kararlar[$k] = ($kararlar[$k] ?? 0) + 1;
    }

    $t['serh'] += tg_serh_sayi($y);
    foreach (tg_oylamalar($y) as $ov) {
        $s = tg_oylama_sonuc($ov);
        $t[$s['kapali'] ? 'oylama_kapali' : 'oylama_acik']++;
    }

    foreach (preg_split('/[,;]+/u', k_alan($y, 'anahtar')) ?: [] as $a) {
        $a = trim($a); if ($a !== '') $anahtarlar[mb_strtolower($a, 'UTF-8')] = ($anahtarlar[mb_strtolower($a, 'UTF-8')] ?? 0) + 1;
    }

    /* Beyanlar: çalışmanın sayfasında zaten görünen alanlar */
    if (trim((string)($y['yz_beyan'] ?? '')) !== '' && (string)($y['yz_beyan'] ?? '') !== 'yok') $t['yz_beyan']++;
    if (in_array((string)($y['veri_durum'] ?? ''), ['acik', 'istek'], true)) $t['veri_acik']++;
    if ((string)($y['etik_durum'] ?? '') === 'var') $t['etik']++;
}

krsort($yillar);
arsort($anahtarlar);
usort($enCok, fn($a, $b) => $b['oku'] <=> $a['oku']);
$enCok = array_slice($enCok, 0, 5);
$enYil = $yillar ? max($yillar) : 1;
$hakemSay = count($hakemler);
$yazarSay = count($yazarlar);
$ortHakem = $t['hakemli'] > 0 ? round($t['rapor'] / $t['hakemli'], 1) : 0;
$ortOkuma = $t['calisma'] > 0 ? (int)round($t['okuma'] / $t['calisma']) : 0;

$ekBas = <<<CSS
<style>
/* Kart, rozet ve ızgara dizgeden gelir; burada yalnızca sayı
   bloklarına ve çubuk çizelgesine özgü ölçüler durur. */
.is-blk{margin-bottom:var(--b-6)}
.is-blk > p{color:var(--metin-2)}
/* Sayı kutusu: im solda, sayı ile etiketi sağda. */
.is-s{display:flex;align-items:center;gap:var(--b-3);min-width:0}
/* İm karesinin ölçüsü içindeki imden çıkar: sabit bir piksel yazmak,
   im büyüdüğü gün kareyi taşırırdı. */
.is-s .im{flex:none;display:grid;place-items:center;padding:var(--b-2);border-radius:var(--r-2);
  background:var(--lacivert-zemin);color:var(--lacivert)}
.is-s b{display:block;font-family:var(--baslik);font-size:var(--y-7);line-height:1.05;font-weight:700}
.is-s span{display:block;font-size:var(--y-1);font-weight:700;letter-spacing:.07em;text-transform:uppercase;
  color:var(--metin-2);margin-top:2px;line-height:var(--sh-orta)}

/* Çubuk çizelgesi. Üç sütun yalnızca burada anlamlıdır: solda etiket,
   ortada çubuk, sağda sayı. Izgara satırın değil çizelgenin kendisinde
   kurulur ve satırlar display:contents ile onun içine akar; böylece
   etiket sütunu en uzun etikete göre kendini ayarlar ve bütün
   satırlarda aynı hizada durur. Sabit bir sütun genişliği yazıldığında
   "Büyük revizyon" gibi uzun bir ad sütunun içinde kırılıyordu. */
.is-cub{display:grid;grid-template-columns:minmax(0,max-content) minmax(0,1fr) minmax(0,max-content);
  gap:var(--b-2) var(--b-3);align-items:center;margin-top:var(--b-4)}
.is-c{display:contents}
.is-c i{display:block;height:var(--b-3);min-width:var(--b-1);border-radius:var(--r-tam);background:var(--kut)}
.is-c em{font-style:normal;color:var(--metin-2);font-family:var(--mono);font-size:var(--y-2);text-align:right}
.is-c b{font-weight:600;font-size:var(--y-3)}
.is-c .sar{background:var(--yuzey-2);border-radius:var(--r-tam);overflow:hidden}

.is-liste{display:grid;gap:var(--b-2);margin-top:var(--b-4)}
.is-l a{color:var(--metin);font-weight:600}
.is-l a:hover{color:var(--kut);text-decoration:none}
.is-l .sayi{margin-left:auto;font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2);white-space:nowrap}

/* Anahtar sözcükler rozet biçimli bağlantılardır: dizgeden .rz gelir,
   burada yalnızca üzerine gelme rengi ve sayının puntosu durur. */
.is-etiket{margin-top:var(--b-4)}
.is-etiket a:hover{border-color:var(--kut);color:var(--kut);text-decoration:none}
.is-etiket em{font-style:normal;font-size:var(--y-1)}
/* Sayfanın dibindeki açıklama bir kutu değil, metnin altına çekilmiş
   bir dipnottur. */
.is-not{font-size:var(--y-3);color:var(--metin-2);border-top:1px solid var(--cizgi);
  padding-top:var(--b-4);margin-top:var(--b-2)}

/* =====================================================================
   ÇİZELGE PALETİ

   Sayfanın metin renkleri çizelgede KULLANILAMAZ. Denendi ve ölçüldü:
   --kut, --lacivert ve --mor üçlüsü kategorik palet olarak beş
   denetimin dördünden kaldı (aydınlık bandı, doygunluk tabanı, renk
   körlüğü ayrımı, normal görü tabanı). Sebebi basit: onlar METİN
   renkleri, yani karanlık ve az doygun; yan yana duran iki dolgu
   olarak birbirinden ayrılmıyorlar.

   Bu yüzden çizelgeye kendi rampaları verildi. Aynı ailelerden
   (lacivert, altın, mor) ama dolgu olarak çalışan basamaklardan.
   Hepsi ölçüldü ve iki temada da beş denetimden beşini geçiyor.

   SIRA RASTGELE DEĞİL. Mavi ile mor yan yana konduğunda kırmızı-yeşil
   renk körlüğünde ayırt edilemiyor (ΔE 2). Araya altın konunca komşu
   çiftler mavi-altın ve altın-mor oluyor ve en kötü çift ΔE 22'ye
   çıkıyor. Bu yüzden diziliş mavi, altın, mor'dur; değiştirilmemeli.

   Sıralı rampa (dört basamak) tek renktir ve açıktan koyuya gider:
   basamaklar sıralı olduğu için okurun sırayı RENKTEN de okuması
   gerekir. Kategorik palet burada yanlış olurdu; kimlik değil,
   sıra anlatılıyor. */
:root{
  --gk-1:#3b7fd4;   /* hakemli   · mavi  */
  --gk-2:#b8860b;   /* aranan    · altın */
  --gk-3:#a04ba0;   /* hakemsiz  · mor   */
  --gr-1:#cbab5e; --gr-2:#b08a2a; --gr-3:#87670f; --gr-4:#4d3908;
  --g-izgara:var(--cizgi);
}
html[data-tema="koyu"]{
  /* Koyu tema kendi basamaklarını alır; açık temanın renklerinin
     çevrilmişi DEĞİLDİR. Koyu yüzeyde aydınlık bandı 0.48-0.67'ye
     daralıyor ve açık temanın renkleri o bandın dışında kalıyor. */
  --gk-1:#5a95e6; --gk-2:#b8892c; --gk-3:#bb6fd6;
  --gr-1:#6b5620; --gr-2:#96742a; --gr-3:#c2953a; --gr-4:#e8c66a;
}

/* Çizelge gövdesi. SVG sunucuda çizilir: betik çalışmasa da,
   yavaş bağlantıda da, yazdırıldığında da aynı çizelge görünür. */
.gz{margin-top:var(--b-4)}
.gz svg{display:block;width:100%;height:auto;overflow:visible}
.gz-izgara{stroke:var(--g-izgara);stroke-width:1;shape-rendering:crispEdges}
/* Metin çizelge rengini GİYMEZ. Renk, yanındaki dolgunun işidir. */
.gz-eksen{fill:var(--metin-3);font-size:var(--y-1);font-family:var(--ui)}
.gz-deger{fill:var(--metin-2);font-size:var(--y-1);font-weight:700;font-family:var(--mono)}
.gz-ad{fill:var(--metin);font-size:var(--y-2);font-weight:600;font-family:var(--ui)}
/* Gösterge her zaman var: iki ve daha çok dizide kimlik hiçbir zaman
   yalnız renge bırakılmaz. */
.gz-gos{display:flex;flex-wrap:wrap;gap:var(--b-2) var(--b-4);margin-top:var(--b-3)}
.gz-gos span{display:inline-flex;align-items:center;gap:6px;font-size:var(--y-2);color:var(--metin-2)}
.gz-gos i{width:11px;height:11px;border-radius:3px;flex:none}
/* Çizelgenin sayıları çizelgeye bakamayan için de durur. */
.gz-tablo{margin-top:var(--b-3);font-size:var(--y-2)}
.gz-tablo summary{cursor:pointer;color:var(--metin-2);padding:var(--b-2) 0}
.gz-tablo table{width:100%;border-collapse:collapse;margin-top:var(--b-2)}
.gz-tablo th,.gz-tablo td{text-align:left;padding:6px 10px;border-bottom:1px solid var(--cizgi)}
.gz-tablo td:not(:first-child),.gz-tablo th:not(:first-child){text-align:right;font-family:var(--mono)}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('İstatistikler', 'Statistics'),
    'yol'    => '/istatistik.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu arşivinin sayıları: çalışma, hakem raporu, karar dağılımı, okuma ve şerh sayıları. Herkese açık.',
        'The numbers of the Kutadgu archive: works, referee reports, decision distribution, reads and commentary counts. Open to everyone.'
    ),
]);

$sim = fn(string $d) => '<span class="im">' . kim_ikon($d, 19) . '</span>';

/* =====================================================================
   ÇİZELGE ÇİZİCİLERİ

   İkisi de SUNUCUDA çizer ve düz SVG döndürür. Betik yok: sayfa
   betiksiz açıldığında da, yazdırıldığında da, arama motoru
   okuduğunda da aynı çizelge vardır. Renkler CSS değişkenlerinden
   gelir, böylece tema anahtarı çevrildiğinde çizelge de döner;
   renkler SVG'ye gömülseydi koyu temada açık temanın renkleri
   kalırdı.

   Ölçüler kılavuzdan: sütun en çok 24 piksel kalın, veri ucu 4 piksel
   yuvarlak ve taban köşeli, yığın parçaları arasında 2 piksellik yüzey
   boşluğu, ızgara tek piksel ve düz.
   ===================================================================== */

/* Yığılmış sütun: yıl x iz. */
function gz_yigin(array $satirlar, array $diziler, int $enUst): string {
    $n = count($satirlar);
    if ($n === 0) return '';
    $G = 760; $Y = 210;                    /* çizim kutusu (viewBox) */
    $solPay = 34; $altPay = 26; $ustPay = 14;
    $alan = $G - $solPay; $yuk = $Y - $altPay - $ustPay;
    $bant = $alan / $n;
    $kalin = (int)min(24, max(8, $bant * 0.42));  /* slotu doldurma: kalanı hava bırak */
    /* Tavan yuvarlanır: eksende 15 / 8 / 0 gibi bir orta değer yerine
       15 / 10 / 5 / 0 okunur. Yuvarlanmamış eksen, okuru olmayan bir
       kesinliğe inandırır. */
    $tavan = max(1, $enUst);
    foreach ([1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000, 2000, 5000] as $adim) {
        if ($tavan <= $adim * 4) { $tavan = (int)(ceil($enUst / $adim) * $adim); break; }
    }
    $o = '<svg viewBox="0 0 ' . $G . ' ' . $Y . '" role="img" aria-label="'
       . k_esc(k_c('Yıllara göre yayın çizelgesi', 'Chart of publications by year')) . '">';
    /* Izgara üç çizgi. Bölen, tavanı TAM bölen bir sayı seçilir:
       15'i ikiye bölmek eksende 7,5'i "8" diye yazdırıyordu ve okur
       çizginin gerçekte nerede olduğunu bilemiyordu. */
    $bolen = 2;
    foreach ([4, 3, 2] as $bb) { if ($tavan % $bb === 0) { $bolen = $bb; break; } }
    for ($i = 0; $i <= $bolen; $i++) {
        $v  = (int)round($tavan * $i / $bolen);
        $yy = $ustPay + $yuk - ($yuk * $i / $bolen);
        $o .= '<line class="gz-izgara" x1="' . $solPay . '" y1="' . round($yy, 1)
            . '" x2="' . $G . '" y2="' . round($yy, 1) . '"/>';
        $o .= '<text class="gz-eksen" x="' . ($solPay - 8) . '" y="' . round($yy + 4, 1)
            . '" text-anchor="end">' . $v . '</text>';
    }
    $ix = 0;
    foreach ($satirlar as $ad => $degerler) {
        $x = $solPay + $bant * $ix + ($bant - $kalin) / 2;
        $taban = $ustPay + $yuk;
        $ustY = $taban;
        $toplam = array_sum($degerler);
        /* Yığın TABANDAN yukarı kurulur; parçalar arasında iki piksel
           yüzey boşluğu bırakılır, aralarına çizgi çekilmez. */
        $k = 0;
        foreach ($diziler as $anahtar => $dizi) {
            $d = (int)($degerler[$anahtar] ?? 0); $k++;
            if ($d <= 0) continue;
            $h = $yuk * $d / $tavan;
            $ustY -= $h;
            $ilkUst = ($ustY <= $ustPay + 0.5) || ($d === $toplam) || ($k === count($diziler));
            $bosluk = ($ustY > $ustPay + 2) ? 2 : 0;
            $o .= '<rect x="' . round($x, 1) . '" y="' . round($ustY + $bosluk, 1)
                . '" width="' . $kalin . '" height="' . round(max(1, $h - $bosluk), 1) . '"'
                . ' fill="var(--gk-' . $k . ')"'
                /* Yalnız yığının EN ÜST parçası yuvarlanır: veri ucu
                   yuvarlak, taban köşeli. */
                . ($ilkUst ? ' rx="4"' : '') . '>'
                . '<title>' . k_esc((string)$ad . ' · ' . $dizi . ': ' . $d) . '</title></rect>';
        }
        /* Doğrudan etiket seçici: her parçaya değil, sütunun TOPLAMINA. */
        if ($toplam > 0) {
            $o .= '<text class="gz-deger" x="' . round($x + $kalin / 2, 1) . '" y="'
                . round($ustY - 6, 1) . '" text-anchor="middle">' . (int)$toplam . '</text>';
        }
        $o .= '<text class="gz-ad" x="' . round($x + $kalin / 2, 1) . '" y="'
            . ($ustPay + $yuk + 17) . '" text-anchor="middle">' . k_esc((string)$ad) . '</text>';
        $ix++;
    }
    return $o . '</svg>';
}

/* Yatay çubuk: sıralı basamaklar ya da karar dağılımı.

   DEĞER ÇUBUĞUN UCUNDA durur, sağ kenarda değil. Sağ kenara
   hizalandığında kısa bir çubuğun sayısı çubuktan kopuyor ve okur
   hangi sayının hangi çubuğa ait olduğunu satırı takip ederek
   çıkarmak zorunda kalıyordu. Uca sığmazsa (çubuk çok uzunsa)
   içeri, dolgunun üstüne alınır. */
function gz_cubuk(array $satirlar, int $rampaAdet = 4, bool $sirali = true): string {
    $n = count($satirlar);
    if ($n === 0) return '';
    $G = 760; $sat = 46;                    /* etiket + çubuk bir satır */
    $Y = $n * $sat;
    $sagPay = 52;                            /* uç etiketine ayrılan yer */
    $enB = max(1, max(array_map(fn($r) => (int)$r['deger'], $satirlar)));
    $alan = $G - $sagPay;
    $o = '<svg viewBox="0 0 ' . $G . ' ' . $Y . '" role="img" aria-label="'
       . k_esc(k_c('Çubuk çizelgesi', 'Bar chart')) . '">';
    $i = 0;
    foreach ($satirlar as $r) {
        $y = $i * $sat;
        $d = (int)$r['deger'];
        $w = $d > 0 ? max(4, $alan * $d / $enB) : 0;
        /* SIRALI ölçekte basamak numarası rampadaki yerini verir:
           kabul -> ret gibi bir sırayı okur renkten de okur.

           SIRASIZ ölçekte (bilim alanları, kurumlar, anahtar sözcükler)
           bütün çubuklar TEK renk alır. Değere göre koyulaştırmak
           kılavuzun adıyla yasakladığı hatadır: çubuğun uzunluğu zaten
           değeri söylüyor, rengi de aynı şeyi söyleyince tek serbest
           kanal boşa harcanır ve okur renkte olmayan bir sıra arar.
           İlk yazımda bu hata yapıldı: beş alan da 12'ydi ama her biri
           bir öncekinden koyu çıkıyordu. */
        $adim = $sirali ? min($rampaAdet, $i + 1) : 2;
        $o .= '<text class="gz-ad" x="0" y="' . ($y + 13) . '">' . k_esc($r['ad']) . '</text>';
        $o .= '<rect x="0" y="' . ($y + 20) . '" width="' . round($w, 1) . '" height="18" rx="4"'
            . ' fill="var(--gr-' . $adim . ')">'
            . '<title>' . k_esc($r['ad'] . ': ' . $d) . '</title></rect>';
        $o .= '<text class="gz-deger" x="' . round($w + 8, 1) . '" y="' . ($y + 33) . '">' . $d . '</text>';
        $i++;
    }
    return $o . '</svg>';
}

/* Tek satırlık pay çizelgesi: bütünün parçaları. */
function gz_pay(array $parcalar, int $toplam): string {
    if ($toplam <= 0) return '';
    $G = 760; $Y = 26;
    $o = '<svg viewBox="0 0 ' . $G . ' ' . $Y . '" role="img" aria-label="'
       . k_esc(k_c('Basamakların payı', 'Share of stages')) . '">';
    $x = 0; $k = 0; $say = count($parcalar);
    foreach ($parcalar as $p) {
        $k++;
        $d = (int)$p['deger'];
        if ($d <= 0) continue;
        $w = $G * $d / $toplam;
        $bosluk = ($k < $say && $w > 4) ? 2 : 0;
        $o .= '<rect x="' . round($x, 1) . '" y="0" width="' . round(max(2, $w - $bosluk), 1)
            . '" height="' . $Y . '" rx="4" fill="var(--gr-' . min(4, $k) . ')">'
            . '<title>' . k_esc($p['ad'] . ': ' . $d) . '</title></rect>';
        $x += $w;
    }
    return $o . '</svg>';
}

/* Gösterge: iki ve daha çok dizide her zaman basılır. */
function gz_gosterge(array $ogeler, string $onek): string {
    $o = '<div class="gz-gos">';
    $k = 0;
    foreach ($ogeler as $ad => $deger) {
        $k++;
        $o .= '<span><i style="background:var(--' . $onek . '-' . min(4, $k) . ')"></i>'
            . k_esc((string)$ad) . ($deger !== null ? ' <b>' . (int)$deger . '</b>' : '') . '</span>';
    }
    return $o . '</div>';
}

/* Çizelgenin sayı hâli. Çizelgeye bakamayan, renk ayıramayan ya da
   ekran okuyucu kullanan için; ayrıca kopyalanabilir. */
function gz_tablo(array $basliklar, array $satirlar): string {
    $o = '<details class="gz-tablo"><summary>' . k_esc(k_c('Sayıları tablo olarak göster', 'Show the numbers as a table'))
       . '</summary><table><thead><tr>';
    foreach ($basliklar as $b) $o .= '<th>' . k_esc((string)$b) . '</th>';
    $o .= '</tr></thead><tbody>';
    foreach ($satirlar as $r) {
        $o .= '<tr>';
        foreach ($r as $h) $o .= '<td>' . k_esc((string)$h) . '</td>';
        $o .= '</tr>';
    }
    return $o . '</tbody></table></details>';
}
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Şeffaflık', 'Transparency') ?></span>
      <h1><?= k_c('Arşivin sayıları', 'The numbers of the archive') ?></h1>
      <p><?= k_c(
        'Aşağıdaki sayıların hepsi, siz bu sayfayı açtığınız anda arşivin kendisinden hesaplanır. Elle tutulan bir tablo yoktur; dolayısıyla süslenemez. Sistem küçükse sayılar küçük görünecektir, şeffaflığın anlamı da budur.',
        'Every number below is computed from the archive itself at the moment you open this page. There is no table kept by hand, and so nothing can be dressed up. If the system is small the numbers will look small; that is what transparency means.'
      ) ?></p>
    </div>
    <div class="sayfa-olcu">
      <div><b><?= k_esc(k_sayi($t['calisma'])) ?></b><span><?= k_c('çalışma', 'works') ?></span></div>
      <div><b><?= k_esc(k_sayi($t['rapor'])) ?></b><span><?= k_c('hakem raporu', 'reports') ?></span></div>
      <div title="<?= k_esc(k_sayi($t['okuma'])) ?>"><b><?= k_esc(k_sayi_kisa($t['okuma'])) ?></b><span><?= k_c('okuma', 'reads') ?></span></div>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <div class="is-blk" id="genel">
      <h2><?= k_c('Genel', 'Overall') ?></h2>
      <div class="dizi dizi-4">
        <div class="kart is-s"><?= $sim('bilgi') ?><div><b><?= k_esc(k_sayi($t['calisma'])) ?></b><span><?= k_c('çalışma', 'works') ?></span></div></div>
        <?php /* Bu sayı yalnızca en az bir hakem raporu yayımlanmış
                 çalışmaları sayar; basamakların dökümü aşağıdadır. */ ?>
        <div class="kart is-s"><?= $sim('damga') ?><div><b><?= k_esc(k_sayi($t['hakemli'])) ?></b><span><?= k_c('hakemli', 'peer reviewed') ?></span></div></div>
        <div class="kart is-s"><?= $sim('insan') ?><div><b><?= k_esc(k_sayi($yazarSay)) ?></b><span><?= k_c('yazar', 'authors') ?></span></div></div>
        <div class="kart is-s"><?= $sim('terazi') ?><div><b><?= k_esc(k_sayi($hakemSay)) ?></b><span><?= k_c('hakem', 'reviewers') ?></span></div></div>
        <div class="kart is-s"><?= $sim('kilavuz') ?><div><b><?= k_esc(k_sayi($t['rapor'])) ?></b><span><?= k_c('yayımlanmış rapor', 'published reports') ?></span></div></div>
        <div class="kart is-s" title="<?= k_esc(k_sayi($t['okuma'])) ?>"><?= $sim('goz') ?><div><b><?= k_esc(k_sayi_kisa($t['okuma'])) ?></b><span><?= k_c('tekil okuma', 'unique reads') ?></span></div></div>
      </div>
      <p class="is-not"><?= k_c(
        'Okuma sayısı tekildir: aynı kişinin aynı çalışmayı birden çok kez açması bir sayılır. Çalışma başına ortalama okuma ' . k_sayi($ortOkuma) . '. Hakemli çalışma başına ortalama yayımlanmış rapor ' . $ortHakem . '.',
        'Read counts are unique: the same person opening the same work more than once counts as one. The average number of reads per work is ' . k_sayi($ortOkuma) . '. The average number of published reports per peer reviewed work is ' . $ortHakem . '.'
      ) ?></p>
    </div>

    <?php /* Hakemlik basamakları.
             Bu blok, sayfadaki en yanıltıcı iki sayının yerine geçer.
             Eskiden yalnızca "hakemli" ve "hakemsiz" vardı ve hakemliğe
             yeni açılmış, tek raporu bile gelmemiş bir çalışma "hakemli"
             sayılıyordu. Dördü birden gösterilir; okur bir sayının hangi
             işin karşılığı olduğunu görmeden okumak zorunda kalmasın. */ ?>
    <div class="is-blk" id="basamaklar">
      <h2><?= k_c('Hakemlik basamakları', 'Stages of review') ?></h2>
      <p><?= k_c(
        'Hakemliğe açılmış olmak, hakemden geçmiş olmak demek değildir. Bir çalışma hakem sürecine girdiği anda değil, raporlar geldikçe ilerler. Aşağıdaki beş sayı bütün arşivi böler; toplamları çalışma sayısına eşittir.',
        'Being opened to review is not the same as having been reviewed. A work advances not when it enters the process but as reports arrive. The five numbers below partition the whole archive; they add up to the number of works.'
      ) ?></p>
      <div class="dizi dizi-4">
        <div class="kart is-s"><?= $sim('yayin') ?><div><b><?= k_esc(k_sayi($t['hakemsiz'])) ?></b><span><?= k_c('hakemsiz yazı', 'non reviewed') ?></span></div></div>
        <div class="kart is-s"><?= $sim('ara') ?><div><b><?= k_esc(k_sayi($t['aranan'])) ?></b><span><?= k_c('hakem aranıyor', 'seeking reviewers') ?></span></div></div>
        <div class="kart is-s"><?= $sim('saat') ?><div><b><?= k_esc(k_sayi($t['suruyor'])) ?></b><span><?= k_c('değerlendirmede', 'under review') ?></span></div></div>
        <div class="kart is-s"><?= $sim('kalkan') ?><div><b><?= k_esc(k_sayi($t['onayli'])) ?></b><span><?= k_c('hakem onaylı', 'reviewer approved') ?></span></div></div>
        <?php /* Reddedilmiş çalışma da arşivin bir parçasıdır ve sayılır.
                 Ret oranını gizleyen bir yayın sistemi, en çok
                 saklanacak sayısını saklamış olur. */ ?>
        <div class="kart is-s"><?= $sim('ara') ?><div><b><?= k_esc(k_sayi($t['reddedildi'])) ?></b><span><?= k_esc(mb_strtolower(tg_asama_metni('reddedildi', (bool)$en), 'UTF-8')) ?></span></div></div>
      </div>
      <?php
      /* Dört sayı bütün arşivi böler; kutular sayıyı tam olarak verir
         ama PAYI vermez. "45 değerlendirmede" ile "45 / 60" ayrı iki
         bilgidir ve ikincisi tek bakışta ancak böyle okunur. Tek
         satırlık pay çizelgesi kutuların yerine geçmez, yanına durur.

         Renk sıralı rampadan gelir: basamaklar sıralıdır ve sıra
         renkten de okunmalıdır. */
      $bsm = [
        ['ad' => tg_asama_metni('yok', $en),     'deger' => (int)$t['hakemsiz']],
        ['ad' => tg_asama_metni('aranan', $en),  'deger' => (int)$t['aranan']],
        ['ad' => tg_asama_metni('suruyor', $en), 'deger' => (int)$t['suruyor']],
        ['ad' => tg_asama_metni('onayli', $en),  'deger' => (int)$t['onayli']],
        ['ad' => tg_asama_metni('reddedildi', $en), 'deger' => (int)$t['reddedildi']],
      ];
      ?>
      <?php if ($t['calisma'] > 0): ?>
      <div class="gz">
        <?= gz_pay($bsm, (int)$t['calisma']) ?>
        <?= gz_gosterge(array_combine(array_column($bsm, 'ad'), array_column($bsm, 'deger')), 'gr') ?>
      </div>
      <?php endif; ?>
      <?php /* Aşağıdaki cümlede geçen "Değerlendirmede" bir rozet değil,
               cümlenin öznesidir; tek kaynaktan çekilip araya
               yapıştırılırsa cümle bozulur ("Değerlendirmede olan
               çalışmanın" tamlaması çekimli hâl ister). Basamak
               etiketlerinin kendisi zaten yukarıda ayrı duruyor. Metin
               değişirse bu cümle elle gözden geçirilmeli: bilinen ve
               kabul edilmiş bir istisnadır. */ ?>
      <p class="is-not"><?= k_c(
        'Hakem aranan çalışmanın metni okunabilir, ancak henüz hiçbir hakem raporu yayımlanmamıştır; dizinlere de ön baskı olarak bildirilir. Değerlendirmede olan çalışmanın en az bir raporu vardır, onay için gereken sayıya ulaşılmamıştır. Geri çekilmiş bir çalışma, geri çekilmeden önce ulaştığı basamakta sayılır: geri çekilme, yapılmış değerlendirmeyi yok saymaz.',
        'A work seeking reviewers can be read, but no referee report has been published for it yet; it is reported to indexing services as a preprint. A work under review has at least one report but has not reached the number required for approval. A retracted work is counted at the stage it had reached: retraction does not undo the review that was done.'
      ) ?></p>
    </div>

    <?php
    /* RET: İKİ AYRI ŞEY, İKİ AYRI SAYI

       "Ret oranı" diye tek bir sayı vermek buradaki en kolay yalan
       olurdu. Bu sistemde ret iki bambaşka olayı anlatıyor ve ikisi
       ayrı ayrı sayılmadıkça hiçbiri doğru anlaşılmaz. Masa reddi
       başvuru aşamasında olur ve ortada çalışma yoktur; hakem reddi
       yayımlanmış bir çalışmanın başına gelir ve o çalışma yayımda
       kalmayı sürdürür.

       Masa reddi SAYIYLA yayımlanır, ADLA değil: reddedilmiş bir
       başvurunun yazarını duyurmak, yayımlanmamış bir çalışmayı
       sahibinin rızası olmadan duyurmak olurdu ve başvurmayı
       caydırırdı. tg_masa_reddi() yalnız sayar, hiçbir kimlik
       döndürmez. */
    $mr = tg_masa_reddi();
    $mrKarar = (int)$mr['ret'] + (int)$mr['kabul'];
    ?>
    <div class="is-blk" id="ret">
      <h2><?= k_c('Ret: iki ayrı şey', 'Rejection: two different things') ?></h2>
      <p><?= k_esc(tg_ret_metni('masa', (bool)$en)) ?></p>
      <p><?= k_esc(tg_ret_metni('hakem', (bool)$en)) ?></p>
      <div class="dizi dizi-3">
        <div class="kart is-s"><?= $sim('ara') ?><div><b><?= k_esc(k_sayi((int)$mr['ret'])) ?></b><span><?= k_c('masa reddi', 'desk rejections') ?></span></div></div>
        <div class="kart is-s"><?= $sim('kalkan') ?><div><b><?= k_esc(k_sayi((int)$t['reddedildi'])) ?></b><span><?= k_c('hakem reddi', 'reviewer rejections') ?></span></div></div>
        <div class="kart is-s"><?= $sim('saat') ?><div><b><?= k_esc(k_sayi((int)$mr['bekleyen'])) ?></b><span><?= k_c('karar bekleyen başvuru', 'submissions awaiting decision') ?></span></div></div>
      </div>
      <?php if ($mrKarar > 0): ?>
      <p class="is-not"><?= k_c(
        'Karara bağlanmış ' . $mrKarar . ' başvurunun ' . (int)$mr['ret'] . ' tanesi masadan dönmüştür; oran yüzde ' . round(100 * (int)$mr['ret'] / $mrKarar) . '. Bu oran bir hedef değildir ve hedef olarak da kullanılmaz: yüksek ret oranını başarı sayan bir yayın sistemi, seçiciliği bilimin yerine koymuş olur.',
        'Of the ' . $mrKarar . ' submissions decided so far, ' . (int)$mr['ret'] . ' were turned down at the desk, a rate of ' . round(100 * (int)$mr['ret'] / $mrKarar) . ' per cent. This rate is not a target and is not used as one: a publishing system that treats a high rejection rate as an achievement has put selectivity in the place of science.'
      ) ?></p>
      <?php else: ?>
      <p class="is-not"><?= k_c(
        'Henüz karara bağlanmış başvuru yok; oran hesaplanmıyor. Olmayan bir oranı sıfır diye yazmak, ölçülmemiş bir şeyi ölçülmüş göstermek olurdu.',
        'No submission has been decided yet, so no rate is computed. Writing a missing rate as zero would present something unmeasured as measured.'
      ) ?></p>
      <?php endif; ?>
      <p class="is-not"><?= k_c(
        'Masa reddi yalnızca sayıyla yayımlanır. Reddedilen başvurunun yazarı, başlığı ve gerekçesi burada da başka hiçbir açık sayfada da görünmez: yayımlanmamış bir çalışma, sahibinin rızası olmadan duyurulmaz. Hakem reddinde durum tersidir; orada çalışma da raporlar da gerekçeleriyle açıktadır, çünkü yayımlanmış bir metin hakkında verilmiş bir karardır.',
        'Desk rejections are published as a count only. The author, title and reasons of a rejected submission appear neither here nor on any other public page: an unpublished work is not announced without its owner\'s consent. Reviewer rejection is the opposite: there the work and the reports stand in the open with their reasons, because that is a decision about an already published text.'
      ) ?></p>
    </div>

    <?php
    /* BEKLEME SÜRESİ

       ORTANCA, ORTALAMA DEĞİL. Bekleme süreleri çarpık dağılır: bir
       yıldır bekleyen tek bir çalışma, ortalamayı bütün arşivi yanlış
       anlatacak kadar yukarı çeker ve "ortalama 180 gün" cümlesi
       kimsenin yaşamadığı bir süreyi anlatır. Ortanca, çalışmaların
       yarısının altında kaldığı süredir; okurun sorduğu soru da odur.
       Bu yüzden sayfada ortalama HİÇ verilmez, ortanca ile birlikte
       EN UZUN bekleyen verilir: ortanca tipik olanı, en uzun ise en
       kötü hâli söyler. İkisi bir arada, tek bir ortalamanın
       gizleyeceği şeyi gizlemez.

       Sayı sayfa her açıldığında yeniden hesaplanır; günlük özet
       tutulmaz. Ölçüldü: bu sayfanın tamamı 5-6 ms'de üretiliyor,
       yani önbellek erken bir çözüm olurdu ve sayfanın "gördüğünüz
       her sayı şu anda sayıldı" sözünü karşılıksız bırakırdı. */
    $ortanca = function (array $d): int {
        if (!$d) return 0;
        sort($d); $n = count($d); $o = intdiv($n, 2);
        return $n % 2 ? (int)$d[$o] : (int)round(($d[$o - 1] + $d[$o]) / 2);
    };
    $bekDeger = array_column($bekGun, 'g');
    $enUzunKayit = null;
    foreach ($bekGun as $bg) { if ($enUzunKayit === null || $bg['g'] > $enUzunKayit['g']) $enUzunKayit = $bg; }
    ?>
    <?php if ($bekDeger): ?>
    <div class="is-blk" id="bekleme">
      <h2><?= k_c('Bekleme süresi', 'Waiting times') ?></h2>
      <p><?= k_c(
        'Açık hakemlik yalnızca sonucun değil, sürenin de görünmesidir. Aşağıdaki sayılar hâlâ hakem bekleyen çalışmaların ne kadardır beklediğini söyler. Kutadgu hiçbir çalışma için süre sözü vermez; hakemler gönüllüdür ve tutulmayacak bir söz, hiç söz vermemekten kötüdür. Burada yazan, verilmiş bir söz değil, ölçülmüş bir durumdur.',
        'Open review means making the waiting visible, not only the outcome. The numbers below say how long the works still awaiting review have been waiting. Kutadgu promises no turnaround time for any work; reviewers are volunteers, and a promise that will not be kept is worse than no promise. What is written here is not a promise but a measured state.'
      ) ?></p>
      <div class="dizi dizi-3">
        <div class="kart is-s"><?= $sim('saat') ?><div><b><?= k_esc(k_sayi($ortanca($bekDeger))) ?></b><span><?= k_c('gün: ortanca bekleme', 'days: median wait') ?></span></div></div>
        <div class="kart is-s"><?= $sim('ara') ?><div><b><?= k_esc(k_sayi($enUzunKayit ? (int)$enUzunKayit['g'] : 0)) ?></b><span><?= k_c('gün: en uzun bekleyen', 'days: longest wait') ?></span></div></div>
        <div class="kart is-s"><?= $sim('kalkan') ?><div><b><?= k_esc($ilkGun ? k_sayi($ortanca($ilkGun)) : '·') ?></b><span><?= k_c('gün: ilk rapor gelene kadar (ortanca)', 'days to the first report (median)') ?></span></div></div>
      </div>
      <?php
      /* Dağılım, tek bir ortancanın söyleyemeyeceğini söyler: bekleme
         birkaç ayda toplanmış mı, yoksa kuyruk uzun mu. Basamaklar
         SIRALI olduğu için renk de sıralı rampadan gelir. */
      /* Dört kova, çünkü sıralı rampa dört basamaklıdır (--gr-1..4).
         Beşinci kovayı eklediğimde çubuk var(--gr-5) istedi, o değişken
         tanımlı değil ve çubuk RENKSİZ çizildi: sayfada boş bir şerit
         göründü. Kovaların sayısı rampanın uzunluğuna bağlıdır. */
      $kova = [
        ['ad' => k_c('0-30 gün', '0-30 days'),        'alt' => 0,   'ust' => 30],
        ['ad' => k_c('31-90 gün', '31-90 days'),      'alt' => 31,  'ust' => 90],
        ['ad' => k_c('91-365 gün', '91-365 days'),    'alt' => 91,  'ust' => 365],
        ['ad' => k_c('1 yıldan uzun', 'over a year'), 'alt' => 366, 'ust' => PHP_INT_MAX],
      ];
      $kSatirB = [];
      foreach ($kova as $kv) {
          $n = 0;
          foreach ($bekDeger as $d) if ($d >= $kv['alt'] && $d <= $kv['ust']) $n++;
          $kSatirB[] = ['ad' => $kv['ad'], 'deger' => $n];
      }
      ?>
      <div class="gz">
        <?= gz_cubuk($kSatirB, 4, true) ?>
        <?= gz_tablo([k_c('Bekleme', 'Wait'), k_c('Çalışma', 'Works')],
                     array_map(fn($r) => [$r['ad'], $r['deger']], $kSatirB)) ?>
      </div>
      <?php if ($enUzunKayit): ?>
      <p class="is-not"><?= k_c('En uzun bekleyen çalışma: ', 'The longest waiting work: ') ?><a href="<?= k_esc(k_bag(k_yazi_yolu($enUzunKayit['y']))) ?>"><?= k_esc(k_alan($enUzunKayit['y'], 'baslik')) ?></a><?= k_c(' · ', ' · ') ?><?= k_esc(tg_gun_metni((int)$enUzunKayit['g'], (bool)$en)) ?>.</p>
      <?php endif; ?>
      <?php
      /* Kaynağın kaç kayıtta gerçekten gönderim tarihi olduğu SAYIYLA
         söylenir. Eski kayıtlarda gönderim günü yoktur ve geriye dönük
         uydurulamaz (dördüncü değişmez ilke); o kayıtlarda sayı yayın
         gününden başlar ve bu, sayının kendisinden ayrılamaz bir
         bilgidir. Gizlenirse sayı olduğundan küçük görünür. */
      $bekSay = count($bekDeger);
      ?>
      <p class="is-not"><?= k_c(
        'Ölçülen çalışma sayısı: ' . $bekSay . '. Bunların ' . $gonderimliKayit . ' tanesinde gönderim günü kayıtlıdır ve süre o günden sayılır; kalanında gönderim günü kayıtlı olmadığı için süre yayına alınma gününden başlatılır, yani gerçek bekleme bu sayıdan UZUNDUR. Eksik gönderim tarihleri geriye dönük doldurulmaz: yayımlanmış bir kayda sonradan tarih yazmak, kaydın değişmezliği ilkesine aykırıdır.',
        'Works measured: ' . $bekSay . '. Of these, ' . $gonderimliKayit . ' have a recorded submission date and the count starts there; for the rest no submission date is recorded, so the count starts from publication, which means the real wait is LONGER than the number shown. Missing submission dates are not filled in retroactively: writing a date into a published record afterwards would violate the immutability of the record.'
      ) ?></p>
    </div>
    <?php endif; ?>

    <div class="is-blk" id="yillar">
      <?php /* =================================================================
               İLETİYE YANIT SÜRESİ
               -----------------------------------------------------------------
               Hakemlik için verilmeyen söz, iletişim için VERİLİR ve bu bir
               tutarsızlık değildir: hakemler gönüllüdür ve süreleri sistemin
               elinde değildir, iletiye yanıt vermek ise doğrudan sistemin
               kendi işidir. Elindeki iş için söz vermemek, gönüllünün
               süresine söz vermek kadar yanlış olurdu.

               Söz sayfada yazılı, tutulup tutulmadığı burada. Yayımlanmayan
               bir hedef, hedef değil temennidir. */
      $iltS = tg_ileti_sureleri();
      if ($iltS['toplam'] > 0): ?>
      <h2 id="ileti"><?= k_c('İletiye yanıt süresi', 'Time to reply to a message') ?></h2>
      <p><?= k_c(
        'Bu sayılar, iletişim sayfasından yazan birinin ilk yanıtı ne kadar sürede aldığını söyler. Hakemlik için süre sözü verilmez, çünkü hakemler gönüllüdür ve süreleri sistemin elinde değildir; iletiye yanıt vermek ise doğrudan bu sistemin kendi işidir ve onun için söz verilir. Hedef türe göre değişir: kurumsal destek ve himaye başvurularında bir gün, ötekilerde en çok üç gün. Yalnızca yanıtlanmış iletiler sayılır; yanıt bekleyenler ayrıca yazılıdır.',
        'These numbers say how long someone who wrote from the contact page waited for a first reply. No turnaround is promised for peer review, because reviewers are volunteers and their time is not the system\'s to give; replying to a message, however, is this system\'s own work, and for that a promise is made. The target depends on the kind: one day for institutional support and patronage enquiries, at most three days for the rest. Only answered messages are counted; those still waiting are stated separately.'
      ) ?></p>
      <div class="dizi dizi-4">
        <div class="kart is-s"><?= $sim('saat') ?><div><b><?= k_esc(k_sayi($iltS['ortanca'])) ?></b><span><?= k_c('saat: ortanca yanıt süresi', 'hours: median time to reply') ?></span></div></div>
        <div class="kart is-s"><?= $sim('ara') ?><div><b><?= k_esc(k_sayi($iltS['en_uzun'])) ?></b><span><?= k_c('saat: en uzun süren yanıt', 'hours: longest time to reply') ?></span></div></div>
        <div class="kart is-s"><?= $sim('kalkan') ?><div><b><?= k_esc(k_sayi($iltS['hedefte'])) ?>%</b><span><?= k_c('hedefin içinde kalan yanıt', 'replies within the target') ?></span></div></div>
        <div class="kart is-s"><?= $sim('grafik') ?><div><b><?= k_esc(k_sayi($iltS['bekleyen'])) ?></b><span><?= k_c('yanıt bekleyen ileti', 'messages awaiting a reply') ?></span></div></div>
      </div>
      <?php endif; ?>

      <h2><?= k_c('Yıllara göre yayın', 'Publications by year') ?></h2>
      <?php if (!$yillar): ?>
        <p><?= k_c('Henüz yayımlanmış çalışma yok.', 'No works have been published yet.') ?></p>
      <?php else: ?>
      <?php
      /* Çizelge yalnızca "kaç çalışma" değil "hangi yolda kaç çalışma"
         diyor. Tek renkli bir çubuk yığını yıl sayısını verirdi ama
         bu sayfanın asıl söylediği şeyi, yolların birbirine oranını
         hiç göstermezdi. Üç iz, arşiv listesindeki üç süzgeçle aynı. */
      /* Etiketler ELLE YAZILMAZ, tg_asama_metni()'den gelir. İlk
         yazımda buraya elle yazılmışlardı ve asama-kapi.php bunu
         hemen yakaladı (160 -> 159): aşama metninin tek kaynağı
         ortak.php'dir. Bir gün "Hakem aranıyor" başka bir söze
         çevrilirse bu sayfa kendiliğinden döner; elle yazılsaydı
         listede başka, burada başka bir söz kalırdı.
         'hakemli' iki basamağı (onaylı + değerlendirmede) toplar ve
         arşiv süzgecindeki üçlüyle aynı ölçüttendir; karşılığı
         onaylı basamağının sözüdür. */
      $izAd = [
        'hakemli'  => tg_asama_metni('onayli', $en),
        'aranan'   => tg_asama_metni('aranan', $en),
        'hakemsiz' => tg_asama_metni('yok', $en),
      ];
      ksort($yilIz);
      $yToplam = array_map(fn($r) => array_sum($r), $yilIz);
      $izTop = ['hakemli' => 0, 'aranan' => 0, 'hakemsiz' => 0];
      foreach ($yilIz as $r) foreach ($izTop as $k => $v) $izTop[$k] += (int)($r[$k] ?? 0);
      ?>
      <div class="gz">
        <?= gz_yigin($yilIz, $izAd, $yToplam ? max($yToplam) : 1) ?>
        <?= gz_gosterge($izAd ? array_combine(array_values($izAd), array_values($izTop)) : [], 'gk') ?>
        <?= gz_tablo(
              array_merge([k_c('Yıl', 'Year')], array_values($izAd), [k_c('Toplam', 'Total')]),
              array_map(fn($yl, $r) => [$yl, (int)$r['hakemli'], (int)$r['aranan'], (int)$r['hakemsiz'], array_sum($r)],
                        array_keys($yilIz), array_values($yilIz))
            ) ?>
      </div>
      <?php endif; ?>
    </div>

    <?php
    /* ---- BİLİM ALANI DAĞILIMI ----
       Arşiv "alan sınırı yok" diyor; bu çizelge o sözün karşılığını
       gösteren tek yerdir. Bugüne kadar sayfada hiç yoktu.

       Neden çubuk, neden radar değil: alanlar SIRASIZ. Radarda
       eksenlerin dizilişi keyfîdir ve dizilişi değiştirmek şeklin
       kendisini değiştirir; okur o şekli bir bilgi sanır. Çubukta
       böyle bir şey yok, uzunluk uzunluktur. Radar aynı veriyi daha
       gösterişli ama daha az doğru anlatırdı. */
    ?>
    <?php if ($alanlar): ?>
    <div class="is-blk" id="alanlar">
      <h2><?= k_c('Bilim alanına göre', 'By field of science') ?></h2>
      <p><?= k_c(
        'Kutadgu herhangi bir bilim dalıyla sınırlı değildir. Aşağıdaki dağılım, arşivin bugün fiilen hangi alanlardan oluştuğunu gösterir; bir hedef değil, bir durumdur.',
        'Kutadgu is not limited to any one discipline. The distribution below shows which fields the archive is in fact made of today; it is a state, not a target.'
      ) ?></p>
      <?php
      arsort($alanlar);
      $alanSatir = [];
      foreach ($alanlar as $kod => $n) {
          $kod = (string)$kod;
          /* '~' öneki: FORD kodu değil, kaydın kendi yazdığı ad. */
          $ad = ($kod !== '' && $kod[0] === '~')
              ? mb_convert_case(mb_substr($kod, 1), MB_CASE_TITLE, 'UTF-8')
              : al_ad($kod, (bool)$en);
          if (trim($ad) === '') continue;
          $alanSatir[] = ['ad' => $ad, 'deger' => (int)$n];
      }
      $alanToplam = array_sum($alanlar);
      ?>
      <div class="gz">
        <?= gz_cubuk($alanSatir, 4, false) ?>
        <?= gz_tablo([k_c('Alan', 'Field'), k_c('Çalışma', 'Works')],
                     array_map(fn($r) => [$r['ad'], $r['deger']], $alanSatir)) ?>
      </div>
      <?php if ($alanToplam > $alanliCalisma): ?>
      <p class="is-not"><?= k_c(
        'Bir çalışma birden çok alana yazılmış olabilir ve o zaman her alanda ayrı ayrı sayılır. Bu yüzden yukarıdaki sayıların toplamı (' . $alanToplam . ') alan yazılmış çalışma sayısından (' . $alanliCalisma . ') büyüktür.',
        'A work may be recorded under more than one field, and is then counted in each. The numbers above therefore add up to more (' . $alanToplam . ') than the number of works carrying a field (' . $alanliCalisma . ').'
      ) ?></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="is-blk" id="kararlar">
      <h2><?= k_c('Hakem kararlarının dağılımı', 'Distribution of reviewer decisions') ?></h2>
      <p><?= k_c(
        'Bir hakemin bütün kararlarına evet demesi de, her şeye hayır demesi de kendi başına bir bilgidir. Bu yüzden karar dağılımı gizlenmez; sistemin genelinde neye ne kadar karar verildiği burada durur.',
        'A reviewer who says yes to everything, and one who says no to everything, are each telling us something. The distribution of decisions is therefore not hidden; how often each decision has been given across the system stands here.'
      ) ?></p>
      <?php if (!$kararlar): ?>
        <p><?= k_c('Henüz yayımlanmış hakem raporu yok.', 'No referee report has been published yet.') ?></p>
      <?php else: ?>
      <?php
      /* Kararlar SIRALI bir ölçektir: kabulden redde. Bu yüzden her
         karara ayrı bir kimlik rengi değil, tek rengin basamakları
         verilir; okur sırayı renkten de okur. Kategorik palet burada
         yanlış olurdu, çünkü anlatılan kimlik değil sıradır. */
      $kSira = ['kabul', 'kucuk', 'buyuk', 'ret'];
      $kSatir = [];
      foreach ($kSira as $kk) {
          if (!isset($kararlar[$kk])) continue;
          $kSatir[] = ['ad' => k_karar_ad($kk), 'deger' => (int)$kararlar[$kk]];
      }
      foreach ($kararlar as $kk => $n) {
          if (in_array($kk, $kSira, true)) continue;
          $kSatir[] = ['ad' => k_karar_ad((string)$kk), 'deger' => (int)$n];
      }
      ?>
      <div class="gz">
        <?= gz_cubuk($kSatir) ?>
        <?= gz_tablo([k_c('Karar', 'Decision'), k_c('Rapor', 'Reports')],
                     array_map(fn($r) => [$r['ad'], $r['deger']], $kSatir)) ?>
      </div>
      <?php endif; ?>
    </div>

    <div class="is-blk" id="surec">
      <h2><?= k_c('Süreç ve açıklık', 'Process and openness') ?></h2>
      <div class="dizi dizi-4">
        <div class="kart is-s"><?= $sim('bildirim') ?><div><b><?= k_esc(k_sayi($t['serh'])) ?></b><span><?= k_c('yayın sonrası şerh', 'post publication notes') ?></span></div></div>
        <div class="kart is-s"><?= $sim('terazi') ?><div><b><?= k_esc(k_sayi($t['oylama_acik'])) ?></b><span><?= k_c('açık oylama', 'open votes') ?></span></div></div>
        <div class="kart is-s"><?= $sim('damga') ?><div><b><?= k_esc(k_sayi($t['oylama_kapali'])) ?></b><span><?= k_c('sonuçlanmış oylama', 'concluded votes') ?></span></div></div>
        <div class="kart is-s"><?= $sim('kure') ?><div><b><?= k_esc(k_sayi($t['veri_acik'])) ?></b><span><?= k_c('verisi paylaşılan', 'data shared') ?></span></div></div>
      </div>
      <p class="is-not"><?= k_c(
        'Bu sayılar sistemin kendi işleyişinin ölçüsüdür. Şerh, yayımlanmış bir çalışmaya sonradan düşülen açık nottur; kurul oylaması ise yazar ile hakemin anlaşamadığı yerde üç bağımsız kişinin verdiği karardır.',
        'These numbers measure how the system itself works. A note is an open comment added to a published work after the fact; a panel vote is the decision of three independent people where an author and a reviewer disagree.'
      ) ?></p>
    </div>

    <?php if ($enCok && $enCok[0]['oku'] > 0): ?>
    <div class="is-blk" id="okunan">
      <h2><?= k_c('En çok okunanlar', 'Most read') ?></h2>
      <div class="is-liste">
        <?php foreach ($enCok as $e): if ($e['oku'] <= 0) continue; ?>
        <div class="kart satir is-l">
          <a href="<?= k_esc(k_bag(k_yazi_yolu($e['y']))) ?>"><?= k_esc(k_alan($e['y'], 'baslik')) ?></a>
          <span class="sayi"><?= k_esc(k_sayi($e['oku'])) ?> <?= k_c('okuma', 'reads') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($anahtarlar): ?>
    <div class="is-blk" id="konular">
      <h2><?= k_c('En çok geçen anahtar sözcükler', 'Most frequent keywords') ?></h2>
      <div class="satir is-etiket">
        <?php foreach (array_slice($anahtarlar, 0, 24, true) as $a => $n): ?>
          <a class="rz rz-cizgi" href="<?= k_esc(k_bag('/yazilar.php?anahtar=' . rawurlencode((string)$a))) ?>"><?= k_esc($a) ?><em><?= (int)$n ?></em></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <p class="is-not"><?= k_c(
      'Bu sayfada hiçbir kişisel veri gösterilmez. Yazar ve hakem sayıları benzersiz ad üzerinden sayılır; e-posta, kurum ya da kimlik bilgisi hiçbir yerde yer almaz. Okuma sayısı çerezle değil, kısa ömürlü ve geri döndürülemez bir özetle tekilleştirilir. Bütün ham veri arşiv dışa aktarımında zaten herkese açıktır.',
      'No personal data is shown on this page. Author and reviewer counts are made over unique names; no e mail address, institution or identity information appears anywhere. Read counts are made unique not by cookies but by a short lived, irreversible digest. All of the raw data is in any case open to everyone in the archive export.'
    ) ?> <a href="/dokum.php"><?= k_c('Arşivin tamamını indir', 'Download the whole archive') ?></a></p>

   </div>
   <?= k_belge_yan([
     ['k' => 'genel',    'tr' => 'Genel',                    'en' => 'Overall'],
     ['k' => 'yillar',   'tr' => 'Yıllara göre yayın',       'en' => 'By year'],
     ['k' => 'kararlar', 'tr' => 'Karar dağılımı',           'en' => 'Decisions'],
     ['k' => 'surec',    'tr' => 'Süreç ve açıklık',         'en' => 'Process'],
     ['k' => 'okunan',   'tr' => 'En çok okunanlar',         'en' => 'Most read'],
     ['k' => 'konular',  'tr' => 'Anahtar sözcükler',        'en' => 'Keywords'],
   ], [
     ['tr' => 'Sayılar nereden geliyor', 'en' => 'Where the numbers come from',
      'ic' => k_c('Hiçbiri elle girilmez. Sayfa her açıldığında arşiv dosyalarından yeniden hesaplanır; yanlışsa arşivin kendisi yanlıştır ve bunu herkes denetleyebilir.',
                  'None of them is entered by hand. They are recomputed from the archive files each time the page is opened; if a number is wrong then the archive itself is wrong, and anyone may check it.')],
     ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
      'ic' => '<a href="/dokum.php">' . k_c('Arşivin tamamını indir', 'Download the whole archive') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/acikliklar.php')) . '">' . k_c('Açık sözlülük', 'Plain speaking') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/kurul.php')) . '">' . k_c('Yayın kurulu', 'Editorial board') . '</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
