<?php
/* =====================================================================
   Yazarlık koşulunun METNİ: kapı ölçümü. Depoya girmez.

   Koşulun kendisi yazarlik-kapi.php ile ölçülür. Burada ölçülen şey
   cümledir: sistem, uygulamadığı bir kuralı duyuruyor mu, duyurduğu
   kuralı tek bir yerden mi üretiyor, tarihi ayardan mı okuyor.

   Cümleyi üreten tek yer ortak.php'deki tg_yazarlik_kosulu_metni() ve
   kardeşi tg_yazarlik_kosulu_kisa()'dır. Elle yazılmış her kopya, ayar
   değiştiğinde yerinde donar; bu betik o kopyaları arar.

   Dönem dışındaki cümleyi ölçmek ayar değiştirmeyi gerektirir; o yüzden
   metin alt süreçte üretilir: tg_ayar() dosyayı bir kez okuyup bellekte
   tutar, aynı süreçte ikinci bir ayar denenemez.

   Kullanım:
     php yazarlik-metin.php                bütün bölümler
     php yazarlik-metin.php metin:<senaryo> tek metin (alt süreç çağırır)

   6. bölüm ayakta bir sunucu ister:
     KUTADGU_DATA=<kopya-veri> KTEST_DIR=<ktest> \
       php -S 127.0.0.1:8941 -t <ktest> krouter.php
   ===================================================================== */
declare(strict_types=1);

const KAYNAK = '/home/claude/kg/kutadgunet';
const KOPYA  = '/tmp/ky';   /* kurul-kapi.php'nin /tmp/kv'siyle çakışmasın */

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* ---- Alt süreç: verilen ayarla cümleleri üretip JSON basar ---- */
function ayar_kur(string $senaryo): array {
    $a = include KAYNAK . '/ayar.php';
    switch ($senaryo) {
        case 'donem-ici':                                        break;
        case 'donem-disi':   $a['kurulus_donemi']['bitis'] = '2020-01-01'; break;
        /* Ayardaki tarih değişince cümledeki tarih de değişmeli;
           değişmiyorsa cümlede elle yazılmış bir tarih vardır. */
        case 'baska-tarih':  $a['kurulus_donemi']['bitis'] = '2030-06-15'; break;
    }
    return $a;
}

function metin_al(string $senaryo): array {
    $c = [];
    exec('php ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg('metin:' . $senaryo) . ' 2>&1', $c);
    $d = json_decode(implode('', $c), true);
    return is_array($d) ? $d : ['hata' => implode(' ', $c)];
}

/* ---- HTTP: sayfanın kullanıcıya gösterdiği gövde ---- */
function sayfa(string $yol, string $dil): string {
    $port = getenv('KPORT') ?: '8941';
    $baglam = stream_context_create(['http' => ['timeout' => 10, 'ignore_errors' => true]]);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol . '?lang=' . $dil, false, $baglam);
    return $c === false ? '' : $c;
}

/* ---- Kaynak taraması: elle yazılmış kopyalar ---- */
/* Aranan, koşulu MUTLAK biçimde anlatan cümlelerdir. "Bir hakemlik
   yazarlık hakkını kazandırır" gibi cümleler aranmaz: onlar kazanılan
   hakkı anlatır ve kuruluş döneminde de doğrudur. Yanlış olan, koşulu
   aranan bir şart gibi yazmak ya da işlevin cümlesini elle
   çoğaltmaktır. */
function kaynak_dosyalari(): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(KAYNAK, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $y = (string)$f;
        if (!preg_match('/\.(php|js)$/', $y)) continue;
        if ($y === KAYNAK . '/ortak.php') continue;   /* cümlenin tek meşru kaynağı */
        if ($y === KAYNAK . '/ayar.php')  continue;   /* koşulun tanımı ve gerekçesi */
        $out[] = $y;
    }
    sort($out);
    return $out;
}

/* YORUMSUZ SATIRLAR.
   Dosya PHP ayrıştırıcısıyla okunur ve yorum belirteçleri satır
   sayısını bozmadan boşaltılır: 40. satır yine 40. satırdır, ama
   içindeki gerekçe metni kalmaz. */
function kodsuz_satirlar(string $dosya): array {
    $ham = (string)@file_get_contents($dosya);
    if ($ham === '') return [];
    $c = '';
    foreach (token_get_all($ham) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
                $c .= str_repeat("\n", substr_count($t[1], "\n"));
                continue;
            }
            $c .= $t[1];
        } else $c .= $t;
    }
    return explode("\n", $c);
}

/* KORUMALI SATIRLAR.
   Bir cümlenin kaynakta durması, okura GÖSTERİLDİĞİ anlamına gelmez.
   "Hakemlik yapmadan yazar olunamaz" cümlesi
   'if (tg_yazarlik_hakemlik_sarti()):' bloğunun içindeyse, ancak koşul
   yürürlükteyken basılır — yani sistem uygulamadığı bir kuralı
   duyurmuş olmaz, tersine uyguladığı kuralı doğru duyurur.

   Kuralı silmek yerine koşula bağlamak DOĞRU davranıştır: kurul kararı
   geri döndüğünde metnin de dönmesi gerekir ve dönmesi için yazılı
   durması gerekir. Kapı bu iki durumu ayırmazsa, doğru davranışı kusur
   sayar ve insanı metni SİLMEYE zorlar.

   Blok sınırı bu kod tabanının biçiminden okunur:
     <?php if (tg_yazarlik_..._sarti()): ?>  ...  <?php endif; ?>
   Arada başka if/endif çiftleri olabilir; sayaçla izlenir. */
function korumali_araliklar(array $satirlar): array {
    $aralik = []; $acik = null; $derin = 0;
    foreach ($satirlar as $i => $s) {
        if ($acik === null) {
            if (preg_match('/if\s*\(\s*tg_yazarlik_(hakemlik|doktora)_sarti\(\)\s*\)\s*:/', $s)) {
                $acik = $i; $derin = 1;
            }
            continue;
        }
        /* Aynı satırda hem açılış hem kapanış olabilir; ikisi de sayılır. */
        $derin += preg_match_all('/\bif\s*\(.*?\)\s*:/', $s);
        $derin -= preg_match_all('/\bendif\s*;/', $s);
        if ($derin <= 0) { $aralik[] = [$acik, $i]; $acik = null; }
    }
    if ($acik !== null) $aralik[] = [$acik, count($satirlar) - 1];
    return $aralik;
}

function kopya_ara(array $desenler): array {
    $bulgu = [];
    foreach (kaynak_dosyalari() as $y) {
        $hamSatir = file($y, FILE_IGNORE_NEW_LINES);
        if ($hamSatir === false) continue;
        $korumali = korumali_araliklar($hamSatir);
        /* Blok sınırları HAM metinden, cümle araması ise YORUMSUZ
           metinden yapılır: bir cümlenin neden kaldırıldığını yazan
           gerekçe, o cümlenin kendisi sayılmaz. İlk yazımda yorum
           yalnız "satır * ile mi başlıyor" diye ayıklanıyordu; bu kod
           tabanında yorumlar girintili düz metinle sürüyor, o yüzden
           gerekçe satırlarının ikincisi ve sonrası kusur sayıldı.
           Yorum artık ayrıştırıcıyla ayıklanır, göze bakılarak değil. */
        $satirlar = kodsuz_satirlar($y);
        foreach ($satirlar as $i => $s) {
            if (trim($s) === '') continue;
            $ic = false;
            foreach ($korumali as $a) if ($i >= $a[0] && $i <= $a[1]) { $ic = true; break; }
            if ($ic) continue;
            foreach ($desenler as $d) {
                if (preg_match($d, $s)) {
                    $bulgu[] = str_replace(KAYNAK . '/', '', $y) . ':' . ($i + 1);
                    break;
                }
            }
        }
    }
    return $bulgu;
}

/* ================== ALT SÜREÇ ================== */
$arg = $argv[1] ?? '';
if (strpos($arg, 'metin:') === 0) {
    $s = substr($arg, 6);
    /* KOPYA HER KOŞUDA YENİLENİR.
       Eskiden burada 'if (!is_dir(KOPYA))' yazıyordu, yani kopya bir
       kez kurulup bir daha hiç tazelenmiyordu. Bunun bedeli ödendi:
       12 Ağustos'ta kurulan /tmp/ky, 13 Ağustos'ta doktora şartı
       kaldırıldıktan sonra da eski ortak.php'yi taşımayı sürdürdü.
       Alt süreç eski cümleleri üretti, kapı o eski cümleleri
       sayfalarda aradı, bulamadı ve DOĞRU ÇALIŞAN beş sayfayı kusurlu
       bildirdi.

       Ölçtüğü kodu önbelleğe alan bir kapı, er ya da geç dünkü kodu
       ölçer ve bugünkü kodu kusurlu gösterir. */
    exec('rm -rf ' . escapeshellarg(KOPYA) . ' && cp -a ' . escapeshellarg(KAYNAK)
        . ' ' . escapeshellarg(KOPYA) . ' && rm -rf ' . escapeshellarg(KOPYA . '/.git'), $c, $k);
    if ($k !== 0) { echo '{}'; exit(2); }
    file_put_contents(KOPYA . '/ayar.php', "<?php\nreturn " . var_export(ayar_kur($s), true) . ";\n");
    require_once KOPYA . '/ortak.php';
    echo json_encode([
        'sart'     => tg_yazarlik_hakemlik_sarti(),
        /* Kapının 2. ve 4. bölümü buna göre dallanır: kalkmış bir
           koşulun gevşetilmesini ölçmenin anlamı yoktur. */
        'doktora_sarti' => tg_yazarlik_doktora_sarti(),
        'donem'    => tg_kurulus_donemi(),
        'bitis'    => tg_kurulus_bitis(),
        'ad_tr'    => tg_kurulus_bitis_ad(false),
        'ad_en'    => tg_kurulus_bitis_ad(true),
        'metni_tr' => tg_yazarlik_kosulu_metni(false),
        'metni_en' => tg_yazarlik_kosulu_metni(true),
        'kisa_tr'  => tg_yazarlik_kosulu_kisa(false),
        'kisa_en'  => tg_yazarlik_kosulu_kisa(true),
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

/* ================== ÖLÇÜM ================== */

echo "== 1. Cümlenin tek kaynağı: ortak.php ==\n";
/* Mutlak iddialar: koşulu "olmazsa olmaz" gibi yazan cümleler. */
$mutlak = [
    '/hakemlik yapmadan yazar olunamaz/iu',
    '/yazarlık ancak hakemlik/iu',
    '/hakemlik sürecini tamamlayan kişi yazar olur/iu',
    '/kendi çalışmanızı yayımlayamazsınız/iu',
    '/cannot become an author without/i',
    '/authorship comes only after/i',
    '/review process becomes an author/i',
    '/cannot publish your own work without/i',
    /* ---- ON YEDİNCİ KUSUR, 15 Ağustos 2026 ----
       Kayıt sayfasında (panel.php) şu duruyordu: "yazarlık için de en
       az bir değerlendirme yapmanız gerekir". Kural 13 Ağustos'ta
       kaldırılmıştı; cümle kaldırılmayı unuttu ve sistemi ilk gören
       ekran, kaldırılmış bir kuralı duyurmayı sürdürdü.

       Desen ARANAN ŞEYE bakar, cümlenin tam yazımına değil: aynı iddia
       "bir hakemlik yapmadan", "en az bir rapor yazmadan" diye de
       yazılabilir. Bu yüzden "yazarlık" ile "değerlendirme/hakemlik
       gereği"nin aynı cümlede buluşması aranıyor. */
    '/yazarlık için.{0,60}(değerlendirme|hakemlik|rapor).{0,30}gerek/iu',
    '/authorship requires you to have (completed|made)/i',
    '/authorship requires.{0,40}(assessment|review)/i',
];
/* İşlevin kendi cümlesinin elle çoğaltılmış hâli. */
$cogaltma = [
    '/Tamamlanmış en az bir hakemlik/u',
    '/At least one completed review/i',
];
$b1 = kopya_ara($mutlak);
$b2 = kopya_ara($cogaltma);
echo "  koşulu mutlak anlatan satır: " . count($b1) . "\n";
echo "  işlevin cümlesini çoğaltan satır: " . count($b2) . "\n";
den('koşulu mutlak anlatan elle yazılmış cümle yok', $b1 === [], implode(' | ', $b1));
den('işlevin cümlesinin elle yazılmış kopyası yok', $b2 === [], implode(' | ', $b2));

/* Sabit tarih: gevşetmeyi anlatan satırda tarih elle yazılmışsa, ayar
   değiştiğinde metin eski tarihte kalır. */
$sabitTarih = [];
foreach (kaynak_dosyalari() as $y) {
    foreach ((array)file($y, FILE_IGNORE_NEW_LINES) as $i => $s) {
        if (!preg_match('/(hakemlik yapmam|not yet reviewed)/iu', $s)) continue;
        if (!preg_match('/(31 Aralık 2027|31 December 2027|2027-12-31)/u', $s)) continue;
        $sabitTarih[] = str_replace(KAYNAK . '/', '', $y) . ':' . ($i + 1);
    }
}
echo "  gevşetmeyi elle tarihleyen satır: " . count($sabitTarih) . "\n";
den('gevşetme cümlesinde elle yazılmış tarih yok', $sabitTarih === [], implode(' | ', $sabitTarih));

/* İşlevin kendisi gerçekten tek yerde mi tanımlı? */
$tanim = 0;
foreach (array_merge(kaynak_dosyalari(), [KAYNAK . '/ortak.php']) as $y) {
    $tanim += preg_match_all('/function\s+tg_yazarlik_kosulu_(metni|kisa)\s*\(/', (string)file_get_contents($y));
}
den('cümleyi üreten iki işlev, tek dosyada bir kez tanımlı', $tanim === 2, (string)$tanim);

echo "\n== 2. Dönem içinde ve dışında farklı cümle ==\n";
$ici  = metin_al('donem-ici');
$disi = metin_al('donem-disi');
den('dönem içi metin alındı', !isset($ici['hata']), (string)($ici['hata'] ?? ''));
den('dönem dışı metin alındı', !isset($disi['hata']), (string)($disi['hata'] ?? ''));
den('dönem içinde koşul aranmıyor', ($ici['sart'] ?? true) === false);
den('dönem dışında koşul aranıyor', ($disi['sart'] ?? false) === true);
/* BURADAN AŞAĞISI YÜRÜRLÜKTEKİ KURALA GÖRE DALLANIR.

   Kuruluş dönemi bir GEVŞETMEdir: aranan bir koşulu geçici olarak
   askıya alır. 13 Ağustos 2026 kurul kararıyla yazarlıkta doktora
   şartı bütünüyle kaldırıldı; askıya alınacak bir koşul kalmayınca
   dönemin cümleye etkisi de kalmadı. tg_yazarlik_kosulu_metni() bunu
   zaten böyle kuruyor: doktora dalı EN BAŞTA döner, dönem dalına hiç
   varılmaz.

   Kapı bu yüzden ikiye ayrıldı. Eski ölçümler silinmedi; şart geri
   açıldığı gün aynen koşacaklar. Bir kapının, yürürlükten kalkmış bir
   kuralı ölçmeye devam etmesi, sistemin uygulamadığı bir kuralı
   duyurmasıyla aynı kusurdur — yalnız aynası. */
$doktoraSarti = (bool)($ici['doktora_sarti'] ?? true);
echo '  ÖLÇÜM  yazarlıkta doktora şartı: ' . ($doktoraSarti ? 'ARANIYOR' : 'ARANMIYOR') . "\n";

if ($doktoraSarti) {
    den('iki dönemin Türkçe cümlesi farklı', ($ici['metni_tr'] ?? '') !== ($disi['metni_tr'] ?? ''));
    den('iki dönemin İngilizce cümlesi farklı', ($ici['metni_en'] ?? '') !== ($disi['metni_en'] ?? ''));
    den('dönem içi cümle gevşemeyi söylüyor',
        mb_strpos((string)($ici['metni_tr'] ?? ''), 'aranmaz') !== false, (string)($ici['metni_tr'] ?? ''));
    den('dönem dışı cümle koşulu söylüyor',
        mb_strpos((string)($disi['metni_tr'] ?? ''), 'Tamamlanmış en az bir hakemlik') === 0,
        (string)($disi['metni_tr'] ?? ''));
    /* Kenar notu kazanılan hakkı anlatır; koşul geri geldiğinde
       söyleyecek bir şeyi kalmaz ve susar. Susmazsa yanlış yerde. */
    den('kenar notu dönem içinde konuşur', ($ici['kisa_tr'] ?? '') !== '');
    den('kenar notu dönem dışında susar', ($disi['kisa_tr'] ?? 'x') === '', (string)($disi['kisa_tr'] ?? ''));
    den('  İngilizcesi de susar', ($disi['kisa_en'] ?? 'x') === '', (string)($disi['kisa_en'] ?? ''));
} else {
    /* Şart kalktığında ölçülecek şey TERSİNE DÖNER: cümle dönemden
       BAĞIMSIZ olmalıdır. Dönem içinde ve dışında ayrı şeyler söyleyen
       bir cümle, kalkmış bir koşulun hâlâ askıya alındığını ima eder. */
    den('cümle dönemden bağımsız (askıya alınacak koşul yok)',
        ($ici['metni_tr'] ?? '') === ($disi['metni_tr'] ?? 'x'), (string)($disi['metni_tr'] ?? ''));
    den('  İngilizcesi de öyle', ($ici['metni_en'] ?? '') === ($disi['metni_en'] ?? 'x'));
    den('cümle doktoranın aranmadığını söylüyor',
        mb_strpos((string)($ici['metni_tr'] ?? ''), 'Doktora derecesi aranmaz') === 0,
        (string)($ici['metni_tr'] ?? ''));
    den('  İngilizcesi de öyle',
        mb_stripos((string)($ici['metni_en'] ?? ''), 'No doctorate is required') === 0,
        (string)($ici['metni_en'] ?? ''));
    /* Kararı kimin verdiği söylenmeli: "herkes gönderebilir" tek
       başına, hiçbir süzgeç yokmuş gibi okunur. */
    den('kararın editörde olduğu da söyleniyor',
        mb_strpos((string)($ici['metni_tr'] ?? ''), 'editör') !== false);
    den('kenar notu iki dönemde de konuşur',
        ($ici['kisa_tr'] ?? '') !== '' && ($disi['kisa_tr'] ?? '') !== '');
    den('  ve ikisi aynı şeyi söylüyor', ($ici['kisa_tr'] ?? '') === ($disi['kisa_tr'] ?? 'x'));
}

echo "\n== 3. İki dil ==\n";
foreach (['metni' => 'koşul cümlesi', 'kisa' => 'kenar notu'] as $anah => $ad) {
    $tr = (string)($ici[$anah . '_tr'] ?? '');
    $en = (string)($ici[$anah . '_en'] ?? '');
    den("$ad iki dilde de dolu", $tr !== '' && $en !== '');
    den("  ve iki dil aynı dize değil", $tr !== $en);
    /* Dil karışması: Türkçe cümlede İngilizce kalıntı, tersi de. */
    den("  Türkçesinde İngilizce kalıntı yok",
        !preg_match('/\b(the|and|is|are|of|review|doctorate)\b/i', $tr), $tr);
    den("  İngilizcesinde Türkçe kalıntı yok",
        !preg_match('/(hakemlik|doktora|kuruluş|aranmaz|çalışma)/iu', $en), $en);
}
den('Türkçe cümle Türkçe harfler taşıyor',
    preg_match('/[şğıçöüİŞĞÇÖÜ]/u', (string)($ici['metni_tr'] ?? '')) === 1);
den('İngilizce cümlede Türkçe harf yok',
    preg_match('/[şğıİŞĞ]/u', (string)($ici['metni_en'] ?? '')) === 0, (string)($ici['metni_en'] ?? ''));

echo "\n== 4. Tarih ayardan gelir, elle yazılmaz ==\n";
$bsk = metin_al('baska-tarih');
den('başka tarihli metin alındı', !isset($bsk['hata']), (string)($bsk['hata'] ?? ''));
den('ayardaki tarih okundu', ($bsk['bitis'] ?? '') === '2030-06-15', (string)($bsk['bitis'] ?? ''));
den('Türkçe tarih adı ayardan üretildi', ($bsk['ad_tr'] ?? '') === '15 Haziran 2030', (string)($bsk['ad_tr'] ?? ''));
den('İngilizce tarih adı ayardan üretildi', ($bsk['ad_en'] ?? '') === 'June 15, 2030', (string)($bsk['ad_en'] ?? ''));
foreach (['metni_tr', 'metni_en', 'kisa_tr', 'kisa_en'] as $k) {
    $s = (string)($bsk[$k] ?? '');
    if ($doktoraSarti) {
        den("$k yeni tarihi taşıyor",
            mb_strpos($s, (string)($bsk[strpos($k, '_en') !== false ? 'ad_en' : 'ad_tr'] ?? 'yok')) !== false, $s);
        den("  ve eski tarih izi kalmadı", mb_strpos($s, '2027') === false, $s);
    } else {
        /* Şart kalktığında cümlede tarih HİÇ olmamalı. Elle yazılmış
           bir tarihin eskimesinden korunmanın en kesin yolu, cümlede
           tarih bulunmamasıdır; kapı bunu da ölçer, yoksa bir gün
           birisi "2027'ye kadar" diye bir kenar notu ekler ve o not
           ayar değiştiğinde kimseye haber vermeden yanlış olur. */
        den("$k hiçbir tarih taşımıyor", preg_match('/\b(19|20)\d{2}\b/', $s) === 0, $s);
    }
}

echo "\n== 5. Söz verilemeyecek bir şey yok ==\n";
/* Bu projenin kuralı: iddia edilemeyecek şey yazılmaz. Cümle bir
   kolaylığı anlatır; bir güvence, bir üstünlük ya da bir kesinlik
   vaat edemez. */
$yasak = [
    '/\bbiricik\b/iu', '/\bkesin(likle)?\b/iu', '/\bgaranti/iu', '/\bmutlaka\b/iu',
    '/\basla\b/iu', '/\bher zaman\b/iu', '/\ben iyi\b/iu', '/\btek\s+(gerçek|doğru)\b/iu',
    '/\bguarantee/i', '/\bunique\b/i', '/\bcertainly\b/i', '/\babsolutely\b/i',
    '/\balways\b/i', '/\bnever\b/i', '/\bbest\b/i', '/\bensures?\b/i', '/\bpromise/i',
];
foreach ([['dönem içi', $ici], ['dönem dışı', $disi]] as [$ad, $veri]) {
    foreach (['metni_tr', 'metni_en', 'kisa_tr', 'kisa_en'] as $k) {
        $s = (string)($veri[$k] ?? '');
        if ($s === '') continue;
        $carpan = [];
        foreach ($yasak as $d) if (preg_match($d, $s, $m)) $carpan[] = $m[0];
        den("$ad $k iddia taşımıyor", $carpan === [], implode(', ', $carpan));
    }
}

echo "\n== 6. Sayfalarda gerçekten görünüyor mu ==\n";
/* Beklenti sayfanın işine göredir:
     metni  gönderim koşullarını anlatan sayfa, tam cümleyi basar
     kisa   hakemliği anlatan sayfa, kenar notunu basar
     yok    koşulu hiç anlatmayan sayfa; elle yazılmış kopya da olmamalı */
$sayfalar = [
    '/basvuru'     => 'metni',
    '/nasil-isler' => 'metni',
    '/hakemlik'    => 'kisa',
    '/ilkeler'     => 'kisa',
    '/bekleyen'    => 'kisa',
    '/hesap-kur'   => 'yok',
];
$govdeler = [];
foreach ($sayfalar as $yol => $beklenti) {
    $var = true; $bos = false;
    foreach (['tr' => '_tr', 'en' => '_en'] as $dil => $ek) {
        $g = sayfa($yol, $dil);
        $govdeler[$yol . ':' . $dil] = $g;
        if ($g === '') { $bos = true; continue; }
        $aranan = $beklenti === 'yok'
            ? ''
            : (string)($ici[($beklenti === 'metni' ? 'metni' : 'kisa') . $ek] ?? '');
        /* Sayfa ya tam cümleyi ya kenar notunu basabilir; ikisi de
           aynı kaynaktan geldiği için hangisi olduğu yeter. */
        $oteki = (string)($ici[($beklenti === 'metni' ? 'kisa' : 'metni') . $ek] ?? '');
        if ($beklenti === 'yok') continue;
        if (mb_strpos($g, $aranan) === false && ($oteki === '' || mb_strpos($g, $oteki) === false)) $var = false;
    }
    if ($bos) { den("$yol yanıt verdi", false, 'sunucuya ulaşılamadı (KPORT)'); continue; }
    if ($beklenti === 'yok') {
        den("$yol koşulu anlatmıyor, elle kopya da yok",
            mb_strpos($govdeler[$yol . ':tr'], 'hakemlik yapmadan') === false
            && mb_strpos($govdeler[$yol . ':en'], 'cannot become an author') === false);
        continue;
    }
    den("$yol iki dilde de koşul cümlesini basıyor", $var);
}

/* Okurun gördüğü gövdede mutlak iddia kalmamalı: kaynakta duran bir
   kopya sayfaya çıkmıyorsa zararı azdır, çıkıyorsa okur yanlış
   bilgilendirilir. */
$govdeIddia = [];
foreach ($govdeler as $ad => $g) {
    if ($g === '') continue;
    foreach ($mutlak as $d) if (preg_match($d, $g)) { $govdeIddia[] = $ad; break; }
}
den('okurun gördüğü gövdede mutlak iddia yok', $govdeIddia === [], implode(' | ', $govdeIddia));

echo "\nGECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
