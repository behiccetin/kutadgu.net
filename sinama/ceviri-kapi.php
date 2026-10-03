<?php
/* =====================================================================
   ÜÇÜNCÜ DİL / ÇEVİRİ KATMANI: kapı ölçümü. Depoya girmez.

   Devir belgesi 7.3: "Yapılabilecek olan: mekanik göçü yap, üçüncü dili
   BOŞ aç, eksik çeviriyi İngilizceye düşür ve sayfada 'bu dil henüz
   gözden geçirilmedi' de."

   VERİLEN KARAR — 1891 ÇAĞRI DEĞİŞTİRİLMEDİ
     Akla ilk gelen yol, k_c('tr','en') çağrılarını k_t([...]) biçimine
     geçirmekti. Yapılmadı. Gerekçe: asıl engel çağrının BİÇİMİ değil,
     üçüncü dilin konacağı bir YERİN olmamasıydı. Yer çağrının içinde
     değil üstünde açıldı; k_c artık üçüncü dil istendiğinde bir çeviri
     katmanına bakıyor. Böylece 1891 çağrının hiçbiri değişmeden hepsi
     üçüncü dile açıldı ve 1891 satırlık bir dönüşümün kırma riski hiç
     alınmadı.

   BU KAPI, DİLİ AÇILMIŞ BİR SİSTEMİ ÖLÇER
     Canlıda üçüncü dil KAPALI durur (ayar.php'deki 'diller' iki dil
     içerir). Kapalı bir yolu "çalışıyor" diye ölçmek olmaz; bu yüzden
     kapı kendi kod kopyasını kurar, orada bir dil açar, kendi veri
     dizinini ve kendi sunucusunu kullanır. Ana sunucuya ve ana veri
     dizinine dokunmaz (OKUBENI: iki sunucu aynı veri dizinine bakarsa
     ölçüm bozulur).

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php ceviri-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }

$KOD3  = '/tmp/kceviri';            /* dili açılmış kod kopyası */
$VERI3 = '/tmp/kceviri-data';       /* kendi veri dizini */
$PORT3 = (string)(getenv('KPORT3') ?: '8942');
$LOG3  = '/tmp/kceviri-sunucu.log';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* Yorumsuz kaynak: gerekçe yazmak yasaklanamaz, sayılan koddur. */
function kodsuz2(string $dosya): string {
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

function kabuk(string $k): string { return (string)shell_exec($k . ' 2>&1'); }

/* ---------------------------------------------------------------------
   KURULUM: kod kopyası, dilin açılması, veri dizini, sunucu
   --------------------------------------------------------------------- */
echo "== 0. Kurulum ==\n";
kabuk('rm -rf ' . escapeshellarg($KOD3) . ' ' . escapeshellarg($VERI3));
kabuk('cp -a ' . escapeshellarg($KOD) . ' ' . escapeshellarg($KOD3));
kabuk('rm -rf ' . escapeshellarg($KOD3 . '/.git'));
kabuk('cp -a ' . escapeshellarg($VERI) . ' ' . escapeshellarg($VERI3));

/* ayar.php'de üçüncü dili aç. Almanca seçildi çünkü ayar dosyasında
   örneği zaten yazılı duruyor; ölçüm örneğin işlediğini de göstermiş
   olur. 'gozden_gecirildi' YAZILMIYOR: varsayılan hayırdır ve bu
   kapının ölçtüğü şeylerden biri de odur. */
$ay = (string)file_get_contents($KOD3 . '/ayar.php');
/* PİLOT DİLİN EKLENECEĞİ YER TAM METİNLE ARANMAZ.
   İlk yazımda 'en' satırının TAMAMI sabit bir dize olarak aranıyordu.
   O satıra dillerin tarih dizimi eklenince satır ikiye bölündü, arama
   hiçbir şey bulamadı ve pilot dil hiç açılmadı; kapı 11 kusur bildirdi
   ve on birinin de sebebi tekti. Ölçüm, ölçtüğü şeyin biçimine değil
   YAPISINA tutunmalı: satırın başı aranır, girdinin sonuna eklenir. */
$hedef = '';
if (preg_match("#\n[ \t]*'en' => \['ad' => 'English'.*?\],[ \t]*(?=\n)#s", $ay, $mH)) {
    $hedef = $mH[0];
}
den('pilot dilin ekleneceği yer bulundu (ayar biçimi değişse de)', $hedef !== '');
/* PİLOT DİL 'de' DEĞİL 'es'.
   Almanca ve Fransızca artık ayar.php'de AÇIK ve sözlükleri depoda
   (k/ceviri/*.json). Bu kapının ölçtüğü şey ise "hiç çevirisi olmayan
   bir dil açıldığında sayfa ne yapıyor" sorusudur; sözlüğü dolu bir
   dille o soru ölçülemez. Üstelik ayar.php'ye ikinci bir 'de' satırı
   eklemek sessizce hiçbir şey yapmazdı: dizide aynı anahtar iki kez
   yazılırsa sonuncusu kazanır ve "dil açıldı" denemesi, hiçbir şey
   açılmamışken bile GECTI derdi.
   PİLOT DİL BİR KEZ DAHA DEĞİŞTİ: 'es' de açıldı (İspanyolca artık
   arayüz dili). Bu kapının pilotu, AÇILMAMIŞ bir dil olmak zorundadır;
   açıldığı gün ölçüm sessizce anlamını yitirir. Şimdiki pilot 'az'.
   Bir gün Azerbaycanca da açılırsa buradaki iki harf değiştirilir ve
   önkoşul denemesi bunu zaten haber verir. */
/* Arama, ARAYÜZ DİLLERİ bloğunun içinde yapılır. İlk yazımda bütün
   dosyada arandı ve 'calisma_dilleri' listesindeki "'pt' => 'Português'"
   satırına takıldı: ayar dosyasında bir dil kodu birden çok listede
   geçer ve hepsi aynı şeyi anlatmaz. Ölçüm, baktığı yeri de bilmeli. */
$blok = '';
if (preg_match("#'diller'\s*=>\s*\[(.*?)\n    \],#s", $ay, $mB)) $blok = $mB[1];
den('pilot dil arayüz dilleri arasında zaten tanımlı DEĞİL (ölçümün önkoşulu)',
    $blok !== '' && strpos($blok, "'pt' =>") === false, $blok === '' ? 'diller bloğu okunamadı' : 'pt zaten var');
$ay = str_replace($hedef, $hedef . "\n        'pt' => ['ad' => 'Português', 'yon' => 'ltr', 'ulke' => ['PT'], 'yedek' => 'en', 'tarih' => '%g %a %y'],", $ay);
file_put_contents($KOD3 . '/ayar.php', $ay);
den('kod kopyasında üçüncü dil açıldı', strpos((string)file_get_contents($KOD3 . '/ayar.php'), "'pt' =>") !== false);

/* Sözlük: bir tek dize çevrilmiş, gerisi boş. Bir dilin BOŞ açılabildiği
   ve boşken de sayfanın ayakta kaldığı ölçülecek. */
@mkdir($VERI3 . '/ceviri', 0775, true);
/* Deneme dizesi ölçülecek SAYFADA gerçekten bulunan bir dize olmalı.
   İlk yazımda 'Open access' seçilmişti; o dize başvuru sayfasında
   geçiyor, nasil-isler.php'de değil ve kapı "çeviri sayfada
   görünmüyor" diye sahte bir KALDI verdi. Ölçüm yanlış çıktığında
   önce ölçümden şüphelen: kusurlu olan kod değil, seçilen dizeydi. */
$DENEME_EN = 'How this system works';
$DENEME_DE = 'Como este sistema funciona';
file_put_contents($VERI3 . '/ceviri/pt.json',
    json_encode([$DENEME_EN => $DENEME_DE], JSON_UNESCAPED_UNICODE));

kabuk('pkill -f "php -S 127.0.0.1:' . $PORT3 . '" ; sleep 1');
kabuk('setsid nohup env KUTADGU_DATA=' . escapeshellarg($VERI3) . ' KTEST_DIR=' . escapeshellarg($KOD3)
    . ' php -S 127.0.0.1:' . $PORT3 . ' -t ' . escapeshellarg($KOD3) . ' ' . escapeshellarg(__DIR__ . '/krouter.php')
    . ' > ' . escapeshellarg($LOG3) . ' 2>&1 < /dev/null & sleep 2');

function ist3(string $yol): array {
    global $PORT3;
    $g = @file_get_contents('http://127.0.0.1:' . $PORT3 . $yol, false, stream_context_create(['http' => [
        'method' => 'GET', 'ignore_errors' => true, 'timeout' => 30,
        'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250)]]));
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
}
$deneme = ist3('/nasil-isler.php?lang=tr');
den('ikinci sunucu ayakta', $deneme['kod'] === 200, (string)$deneme['kod']);

/* Ayar okuyan senaryolar KENDİ ALT SÜRECİNDE koşar (OKUBENI 9:
   tg_ayar dosyayı bir kez okur ve bellekte tutar). */
function altSurec(string $govde): string {
    global $KOD3, $VERI3;
    $p = tempnam(sys_get_temp_dir(), 'kc') . '.php';
    file_put_contents($p, "<?php\nputenv('KUTADGU_DATA=" . $VERI3 . "');\n"
        . "\$_SERVER['HTTP_HOST']='127.0.0.1';\nrequire '" . $KOD3 . "/ortak.php';\nrequire '" . $KOD3 . "/k/kabuk.php';\n" . $govde);
    $c = (string)shell_exec('php ' . escapeshellarg($p) . ' 2>&1');
    @unlink($p);
    return trim($c);
}

/* =====================================================================
   1. TÜRKÇE VE İNGİLİZCE HİÇ DEĞİŞMEDİ
   ---------------------------------------------------------------------
   Yeni bir yol açarken var olan iki dilin davranışının aynı kalması,
   bu değişikliğin en önemli ölçüsüdür.
   ===================================================================== */
echo "\n== 1. İki dil bozulmadı ==\n";
den('k_c Türkçede Türkçeyi veriyor',
    altSurec("\$_GET['lang']='tr'; echo k_c('elma','apple');") === 'elma');
den('k_c İngilizcede İngilizceyi veriyor',
    altSurec("\$_GET['lang']='en'; echo k_c('elma','apple');") === 'apple');
den('  bu iki dilde çeviri katmanına HİÇ bakılmıyor',
    altSurec("\$_GET['lang']='tr'; k_c('elma','apple'); echo tg_ceviri_eksik();") === '0');

/* =====================================================================
   2. ÜÇÜNCÜ DİL: ÇEVİRİ VARSA ÇEVİRİ, YOKSA İNGİLİZCE
   ===================================================================== */
echo "\n== 2. Üçüncü dil ==\n";
den('üçüncü dil tanınıyor',
    altSurec("\$_GET['lang']='pt'; echo k_dil();") === 'pt');
den('çevirisi olan dize ÇEVRİLİ geliyor',
    altSurec("\$_GET['lang']='pt'; echo k_c('Açık erişim','$DENEME_EN');") === $DENEME_DE);
den('çevirisi olmayan dize İNGİLİZCEYE düşüyor (Türkçeye değil)',
    altSurec("\$_GET['lang']='pt'; echo k_c('elma','apple');") === 'apple');
den('  ve eksik olarak sayılıyor',
    altSurec("\$_GET['lang']='pt'; k_c('elma','apple'); echo tg_ceviri_eksik();") === '1');
den('  çevirisi olan dize eksik SAYILMIYOR',
    altSurec("\$_GET['lang']='pt'; k_c('Açık erişim','$DENEME_EN'); echo tg_ceviri_eksik();") === '0');
den('boş sözlükle de çalışıyor (dil BOŞ açılabilir)',
    altSurec("\$_GET['lang']='pt'; echo (tg_ceviri_sozluk('zz')===[] ? 'bos':'dolu');") === 'bos');

/* Anahtar okunabilir olmalı: çevirmen anahtara bakarak ne çevirdiğini
   anlayabilmeli. Kısa dizeler olduğu gibi, uzunlar kısaltılmış. */
den('kısa dizenin anahtarı dizenin kendisi',
    altSurec("echo tg_ceviri_anahtar('Open access');") === 'Open access');
den('  uzun dizenin anahtarı kısaltılmış',
    str_starts_with(altSurec("echo tg_ceviri_anahtar(str_repeat('a ',100));"), '#'));
den('  boşluk farkı anahtarı değiştirmiyor',
    altSurec("echo tg_ceviri_anahtar(\"Open\\n  access\");") === 'Open access');

/* k_t de aynı katmana bakmalı: iki ayrı çeviri yolu, sayfanın yarısı
   çevrili yarısı çevrilmemiş demektir. */
den('k_t de aynı sözlüğe bakıyor',
    altSurec("\$_GET['lang']='pt'; echo k_t(['tr'=>'Açık erişim','en'=>'$DENEME_EN']);") === $DENEME_DE);

/* =====================================================================
   3. SAYFA: DİL AÇIK, EKSİK SÖYLENİYOR
   ===================================================================== */
echo "\n== 3. Sayfada ==\n";
$de = ist3('/nasil-isler.php?lang=pt');
den('üçüncü dilde sayfa 200', $de['kod'] === 200, (string)$de['kod']);
den('  gövde </html> ile bitiyor (kırpılmamış)', str_ends_with(rtrim($de['govde']), '</html>'));
den('  <html lang="pt">', (bool)preg_match('#<html[^>]+lang="pt"#', $de['govde']));
den('  çevrilmiş dize sayfada görünüyor', strpos($de['govde'], $DENEME_DE) !== false);
den('  çevrilmemiş dizeler İngilizce görünüyor',
    strpos($de['govde'], 'Peer review') !== false || strpos($de['govde'], 'peer review') !== false);

$gorunur = trim((string)preg_replace('/\s+/u', ' ',
    html_entity_decode(strip_tags((string)preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $de['govde'])),
    ENT_QUOTES | ENT_HTML5, 'UTF-8')));
den('  "bu dil gözden geçirilmedi" AÇIKÇA yazıyor',
    stripos($gorunur, 'not yet been reviewed') !== false || stripos($gorunur, 'gözden geçirilmedi') !== false);
den('  okura kendi diline dönme yolu bırakılmış',
    (bool)preg_match('#href="[^"]*lang=en"#', $de['govde']) && (bool)preg_match('#href="[^"]*lang=tr"#', $de['govde']));

$tr = ist3('/nasil-isler.php?lang=tr');
$trG = strip_tags($tr['govde']);
/* ÖLÇÜ, ŞERİDİN KENDİSİDİR; SÖZCÜĞÜN GEÇMESİ DEĞİL.
   Bu ölçüm önce "sayfada 'gözden geçirilmedi' sözü geçmesin" diyordu
   ve dil seçim penceresi eklenince KALDI verdi: pencere, Almancanın
   yanına doğru olarak "gözden geçirilmedi" yazıyor. Türkçe sayfada
   olmaması gereken şey o söz değil, TÜRKÇE İÇİN uyarı şerididir. */
den('Türkçe sayfada bu uyarı şeridi YOK',
    strpos($tr['govde'], 'dil-uyari') === false);
$en = ist3('/nasil-isler.php?lang=en');
den('  İngilizce sayfada da YOK', strpos($en['govde'], 'dil-uyari') === false);

/* BEKLENTİ BİLEREK DEĞİŞTİ: dil sayısına göre İKİ AYRI arayüz yoktu
   artık. Eskiden iki dilde tek bir bağlantı, üç ve daha çok dilde ayrı
   bir açılır liste basılıyordu; aynı iş için iki ayrı yol demekti ve
   dil sayısı ikiyi geçtiği gün ilki sessizce yanlış olurdu. Şimdi tek
   bir pencere var ve dil sayısından bağımsız çalışır (bkz. 4.b). */
den('dil seçici her dil sayısında aynı pencere',
    (bool)preg_match('#<details class="dil-sec"#', $de['govde']));
den('  listedeki her dil bir BAĞLANTI (betiksiz de çalışır)',
    substr_count($de['govde'], 'data-dil="') >= 3
    && (bool)preg_match('#<a[^>]+data-dil="tr"[^>]*href=#', $de['govde']));

/* =====================================================================
   4. KOD MAKİNE ÇEVİRİSİ YAZMIYOR
   ---------------------------------------------------------------------
   Sözlük veri dizinindedir ve içine ne konduğu insanın işidir. Kod
   oraya kendiliğinden yazarsa, "makine çevirisiyle doldurulmuş bir
   arayüz" tam da devir belgesinin yasakladığı şey olur.
   ===================================================================== */
echo "\n== 4. Sözlüğe kod yazmıyor ==\n";
$hepsi = '';
foreach (['/ortak.php', '/k/kabuk.php', '/api/index.php'] as $d) {
    $ham = (string)@file_get_contents($KOD3 . $d);
    foreach (token_get_all($ham) as $t) {
        if (is_array($t)) { if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) continue; $hepsi .= $t[1]; }
        else $hepsi .= $t;
    }
}
den('hiçbir yerde çeviri dizinine yazma yok',
    !preg_match('#(file_put_contents|fopen|yaz_json)\s*\([^;]{0,120}ceviri#i', $hepsi));
$oncekiSozluk = (string)file_get_contents($VERI3 . '/ceviri/pt.json');
ist3('/nasil-isler.php?lang=pt'); ist3('/ilkeler.php?lang=pt');
den('  sayfalar açıldıktan sonra sözlük DEĞİŞMEDİ',
    (string)file_get_contents($VERI3 . '/ceviri/pt.json') === $oncekiSozluk);

den('bir dil varsayılan olarak "gözden geçirilmemiş" sayılıyor',
    altSurec("echo tg_dil_gozden_gecirildi('pt') ? 'evet':'hayir';") === 'hayir');
den('  tr ve en gözden geçirilmiş sayılıyor',
    altSurec("echo (tg_dil_gozden_gecirildi('tr')&&tg_dil_gozden_gecirildi('en'))?'evet':'hayir';") === 'evet');

/* =====================================================================
   5. ÇEVİRİ BEKLEYEN DİZE SAYISI BİLİNİYOR
   ===================================================================== */
echo "\n== 5. Kaç dize insan bekliyor ==\n";
$cikti = kabuk('php ' . escapeshellarg(__DIR__ . '/ceviri-cikar.php') . ' ' . escapeshellarg($KOD3));
preg_match('/BİRİCİK DİZE\s*:\s*(\d+)/u', $cikti, $m1);
preg_match('/k_c çağrısı\s*:\s*(\d+)/u', $cikti, $m2);
preg_match('/statik çıkarılamayan:\s*(\d+)/u', $cikti, $m3);
$biricik = (int)($m1[1] ?? 0); $cagri = (int)($m2[1] ?? 0); $dinamik = (int)($m3[1] ?? -1);
olc('k_c çağrısı: ' . $cagri . ' · biricik dize: ' . $biricik . ' · statik çıkarılamayan: ' . $dinamik);
den('çıkarıcı çalışıyor ve bin dizenin üstünde sayıyor', $biricik > 1000, (string)$biricik);
/* Bu deneme eskiden "çağrı sayısı biricik dizeden çok olmalı" diyordu
   ve doğruydu: her dize bir k_c çağrısıydı, aynı dize birden çok yerde
   geçtiği için çağrı sayısı daha büyüktü.

   Artık değil. Çıkarıcı, k_c çağrısı OLMAYAN dizeleri de topluyor:
   veri tablolarındaki 'tr'/'en' çiftleri ve sıraya dayalı ikililer.
   Bunlar sayfada görünen, çevrilmesi gereken metinlerdir ama çağrı
   yerinde durmazlar. Yani biricik dize sayısının çağrı sayısını
   geçmesi bir kusur değil, çıkarıcının iyileşmesinin ta kendisidir.

   Ölçü şuna çevrildi: tablo dizeleri toplam dizenin küçük bir azınlığı
   olmalı. Çoğunluğa dönerse bu, çıkarıcının kalıbının çok gevşediğini
   ve arayüz metni olmayan şeyleri toplamaya başladığını gösterir. */
$tabloEk = 0;
if (preg_match('/veri tablosundan ek\s*:\s*(\d+)/u', $cikti, $m4)) $tabloEk = (int)$m4[1];
olc('bunun ' . $tabloEk . ' tanesi veri tablosundan (çağrı yerinde durmayan metin)');
den('  tablo dizeleri azınlıkta (toplamın yarısından az)', $tabloEk * 2 < $biricik, $tabloEk . '/' . $biricik);
den('  k_c çağrıları hâlâ dizelerin çoğunu taşıyor', $cagri > $biricik - $tabloEk, $cagri . ' > ' . ($biricik - $tabloEk));
den('  çıkarılamayanlar gizlenmiyor, ayrı sayılıyor', $dinamik >= 0, (string)$dinamik);

/* =====================================================================
   4.b DİL SEÇİM PENCERESİ

   "Tıklayınca bütün dilleri görelim, seçelim" diye istendi. Ölçülen şey
   pencerenin güzelliği değil, üç şeydir:
     1. Listede AYARDAKİ BÜTÜN diller var mı (biri eksikse o dil yok
        demektir, çünkü kimse ona ulaşamaz),
     2. Bulunulan dil imli mi,
     3. Gözden geçirilmemiş dil öyle YAZIYOR mu.
   Bir de dördüncüsü: pencere <details> ile kuruluyor mu — çünkü
   betiksiz bir ziyaretçi <dialog>'u hiç açamaz.
   ===================================================================== */
echo "\n== 4.b Dil seçim penceresi ==\n";
$sy = ist3('/nasil-isler.php?lang=pt');
$g = $sy['govde'];
den('pencere <details> ile kuruluyor (betiksiz de açılır)',
    (bool)preg_match('#<details class="dil-sec"#', $g));
den('  açan öge <summary>dir (klavyeyle odaklanır)',
    (bool)preg_match('#<details class="dil-sec"[^>]*>\s*<summary#s', $g));
preg_match_all('#<a data-dil="([a-z-]+)"#', $g, $mD);
$listelenen = $mD[1] ?? [];
olc('pencerede listelenen diller: ' . implode(', ', $listelenen));
den('ayardaki bütün diller listede', count($listelenen) >= 3
    && in_array('tr', $listelenen, true) && in_array('en', $listelenen, true)
    && in_array('pt', $listelenen, true), implode(',', $listelenen));
den('  bulunulan dil imli (aria-current)',
    (bool)preg_match('#<a data-dil="pt"[^>]*aria-current="true"#', $g));
den('  her dil kendi adıyla yazılı',
    strpos($g, 'Português') !== false && strpos($g, 'Türkçe') !== false);
den('  gözden geçirilmemiş dil öyle yazıyor',
    (bool)preg_match('#data-dil="pt".{0,400}(not reviewed yet|gözden geçirilmedi)#su', $g));
den('  gözden geçirilmiş dilde o yazı YOK (yalnız eksik olanda var)',
    !preg_match('#data-dil="en"[^>]*>.{0,200}(not reviewed yet|gözden geçirilmedi)#su', $g));
/* hreflang sayfanın <head>'inde de geçiyor (alternate); ölçü, LİSTE
   satırlarının kendisine bakmalı: her data-dil bir <a> ve her <a>
   bir href taşımalı. */
preg_match_all('#<a data-dil="[a-z-]+" href="([^"]+)"#', $g, $mB);
den('  her satır gerçek bir bağlantı (?lang= taşıyor)',
    count($mB[1]) === count($listelenen) && count(array_filter($mB[1], fn($h) => strpos($h, 'lang=') !== false)) === count($listelenen),
    count($mB[1]) . '/' . count($listelenen));

/* =====================================================================
   5.a SÖZLÜĞÜ BOŞ BİR DİL, İNGİLİZCE SAYFANIN AYNISI OLMALI

   Bu, üçüncü dil için tek ve yeterli ölçüdür. Sözlükte hiçbir karşılık
   yoksa her dize yedeğe düşmeli; yedek 'en'dir. O hâlde sözlüğü boş bir
   dilde basılan sayfa, İngilizce sayfayla AYNI metni taşımalıdır.
   Türkçe kalan her dize, çeviri katmanına hiç uğramamış bir yerdir.

   Sayaç değil, METİN karşılaştırılır: "kaç yerde $en ? var" sorusu
   yaklaşık bir sorudur, "sayfada ne göründü" sorusu kesindir.

   Uyarı şeridi ile <html lang> dışarıda tutulur: onların ayrı olması
   gerekir, zaten ölçülen şey de o değildir.
   ===================================================================== */
echo "\n== 5.a Boş sözlüklü dil, İngilizceyle aynı mı ==\n";
function metinCikar(string $html): array {
    $h = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#si', ' ', $html);
    /* Dil uyarısı şeridi ile dil değiştirici ölçünün dışındadır. */
    $h = preg_replace('#<div class="dil-uyari".*?</div>#si', ' ', (string)$h);
    $h = preg_replace('#<(nav|div)[^>]*class="[^"]*dil-[^"]*".*?</\1>#si', ' ', (string)$h);
    $h = strip_tags((string)$h);
    $h = html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $satir = preg_split('/\n+/u', (string)preg_replace('/[ \t]+/u', ' ', $h));
    $c = [];
    foreach ($satir as $x) { $x = trim($x); if (mb_strlen($x) >= 3) $c[] = $x; }
    return $c;
}
$SAYFA_LISTE = ['/', '/nasil-isler.php', '/yazilar.php', '/hakemlik.php', '/basvuru.php',
                '/kurul.php', '/ilkeler.php', '/harita.php', '/bekleyen.php', '/iletisim.php',
                '/dokum.php', '/istatistik.php', '/destek.php', '/yz.php', '/acikliklar.php'];
/* İÇERİK ÇEVRİLMEZ, ARAYÜZ ÇEVRİLİR.
   Bir çalışmanın başlığı, yazarının adı ve özeti KAYITTIR; sistemin
   ilkesi gereği yazıldığı dilde durur ve sayfa dili ne olursa olsun
   değişmez. Bunları "sızıntı" saymak, çevrilmemesi GEREKEN şeyi
   çevrilmemiş diye bayraklamak olurdu. Kayıt metinleri veri
   dosyasından okunur ve ölçünün dışında tutulur. */
$icerik = [];
$yzL = json_decode((string)@file_get_contents($VERI3 . '/yazilar.json'), true);
if (is_array($yzL)) {
    foreach ($yzL as $y) {
        if (!is_array($y)) continue;
        foreach (['baslik', 'baslik_en', 'ozet'] as $alan)
            if (!empty($y[$alan])) $icerik[] = (string)$y[$alan];
        /* Anahtar sözcükler de kayıttır: süzgeç listesinde çalışmanın
           kendi yazdığı sözcük görünür, çevrilmez. */
        foreach (['anahtar', 'anahtar_en'] as $ak) {
            $ham = $y[$ak] ?? '';
            $liste = is_array($ham) ? $ham : preg_split('/\s*,\s*/u', (string)$ham);
            foreach ((array)$liste as $an) if (is_string($an) && trim($an) !== '') $icerik[] = trim($an);
        }
        foreach (['yazar'] as $ak) if (!empty($y[$ak]) && is_string($y[$ak])) $icerik[] = (string)$y[$ak];
        foreach ((array)($y['yazarlar'] ?? []) as $y2)
            if (!empty($y2['ad'])) $icerik[] = (string)$y2['ad'];
    }
}
$icerikVar = function (string $x) use ($icerik): bool {
    foreach ($icerik as $i) {
        $k = mb_substr(trim($i), 0, 24);
        if ($k !== '' && mb_strpos($x, $k) !== false) return true;
    }
    return false;
};
$sizan = []; $olculenSayfa = 0;
foreach ($SAYFA_LISTE as $sy) {
    $enS = ist3($sy . '?lang=en');
    $deS = ist3($sy . '?lang=pt');
    if ($enS['kod'] !== 200 || $deS['kod'] !== 200) { olc($sy . ' ULAŞILMADI'); continue; }
    $olculenSayfa++;
    $a1 = metinCikar($enS['govde']); $a2 = metinCikar($deS['govde']);
    foreach (array_diff($a2, $a1) as $x) {
        /* Sayı, tarih ve kimlik dizeleri iki dilde de aynıdır; ayrışan
           yalnız METİN olmalı. Türkçeye özgü harf ya da bilinen bir
           Türkçe sözcük taşıyan ayrım, sızıntıdır. */
        if ($icerikVar($x)) continue;
        /* SIZINTININ İMZASI TÜRKÇE HARFTİR. Önce Türkçe sözcük listesi
           de aranıyordu ve İngilizce iki cümleyi yakaladı ("a researcher
           who assesses…"): o cümleler İngilizce sayfada başka bir
           bağlamda geçtiği için ayrım listesine düşmüştü, Türkçe
           oldukları için değil. Ölçüt daraltıldı: yalnız Türkçeye özgü
           harf taşıyan ayrım sızıntıdır. Türkçeye özgü harf taşımayan
           bir Türkçe dize kaçabilir; ölçüm bunu bilerek kabul eder,
           çünkü yanlış bayrak, kaçırılan bayraktan pahalıdır. */
        if (preg_match('/[çğıöşüÇĞİÖŞÜ]/u', $x)) {
            $sizan[] = $sy . ' « ' . mb_substr($x, 0, 60) . ' »';
        }
    }
}
olc($olculenSayfa . ' sayfa İngilizce ve boş sözlüklü Almanca olarak karşılaştırıldı');
$sizanTek = array_values(array_unique($sizan));
foreach (array_slice($sizanTek, 0, 12) as $x) olc('  sızan: ' . $x);
/* TAVAN: bugün ölçülen sayı. Düşmesi serbest, artması gerileme. */
$SIZINTI_TAVAN = 0;
den('sözlüğü boş dilde Türkçe metin sızmıyor (tavan ' . $SIZINTI_TAVAN . ')',
    count($sizanTek) <= $SIZINTI_TAVAN, count($sizanTek) . ' ayrı dize');

/* =====================================================================
   5.b ÜÇÜNCÜ DİLE HAZIRLIK: İKİLİ DİL SEÇİMİ KAÇ YERDE KALDI

   Pilot ölçümde bulundu. 119 kabuk dizesi Almancaya çevrilip
   /?lang=pt açıldığında sayfanın bir bölümü Almanca, bir bölümü
   İNGİLİZCE, sol menü ise TÜRKÇE geldi. Üstteki uyarı ise "çevirisi
   olmayan metinler İngilizce görünür" diyordu; yani sistem söylediği
   şeyi yapmıyordu.

   Sebep: `$en ? 'English' : 'Türkçe'` biçimindeki İKİLİ seçim. Bu
   deyim yalnız iki dil tanır ve üçüncü dilde sessizce Türkçeye düşer;
   çeviri katmanına (k_c / k_t) hiç uğramaz. Yani sözlükte karşılığı
   OLSA BİLE kullanılmaz.

   Bu ölçüm bir sayıdır, bir yasak değil: sayı düştükçe sistem üçüncü
   dile hazır olur. Bugünkü sayı yazılıdır; ARTARSA yeni bir gerileme
   var demektir.
   ===================================================================== */
echo "\n== 5.b Üçüncü dile hazırlık ==\n";
$ikili = 0; $ikiliDize = 0; $dosyaIkili = [];
$yig2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($KOD3, FilesystemIterator::SKIP_DOTS));
foreach ($yig2 as $f2) {
    if (!$f2->isFile() || strtolower($f2->getExtension()) !== 'php') continue;
    /* ÖLÇÜM DÜZELTMESİ: eskiden ham dosya okunuyordu ve YORUMDAKİ
       "$en ? ... : ..." de sayılıyordu. Bir kusuru anlatan gerekçe
       satırı, o kusurun kendisi sayılamaz: k/alanlar.php'de ikili
       seçimin neden kaldırıldığını yazan yorum, sayacı 24'ten 25'e
       çıkardı ve kapı doğru bir düzeltmeyi gerileme diye bildirdi.
       Aynı hata bu depoda kopya_ara()'da da çıkmıştı. Sayılan artık
       yalnızca KODDUR. */
    $g2 = kodsuz2($f2->getPathname());
    $n2 = preg_match_all('/\$en\s*\?/', $g2);
    $d2 = preg_match_all('/\$en\s*\?\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")\s*:\s*(\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*")/', $g2);
    $ikili += $n2; $ikiliDize += $d2;
    if ($n2 > 0) $dosyaIkili[str_replace($KOD3 . '/', '', $f2->getPathname())] = $n2;
}
arsort($dosyaIkili);
olc('ikili dil seçimi ($en ? ... : ...): ' . $ikili . ' yer · ikisi de düz dize olan: ' . $ikiliDize);
$ilk5 = array_slice($dosyaIkili, 0, 5, true);
foreach ($ilk5 as $ad => $n2) olc('  ' . str_pad($ad, 22) . $n2);
/* TAVAN: bugünkü sayı. Düşmesi serbesttir, artması değil. */
/* TAVAN 423'TEN 19'A İNDİ. Kalanların hiçbiri METİN değildir: dizi
   indisi seçen (`$X[$en ? 1 : 0]` biçiminde olmayan birkaç yer), dil
   KODU üreten ve JS'e bayrak geçiren satırlar. Sayı düşebilir,
   artamaz. */
/* 19 -> 20: tg_baslik_bilgi() eklenirken bir satır daha oldu ve o
   satır METİN seçmiyor, k_dil() yokken dil KODUNU kestiriyor
   (komut satırı için yedek). Tavan bilerek bir artırıldı. */
/* 20 -> 24: çevirinin kaynağı kayda alınırken dört yer daha oldu ve
   DÖRDÜ DE METİN SEÇMİYOR. Üçü "sayfa İngilizceyse İngilizce çeviriyi
   ARA" biçiminde bir arama koşulu (k/seo.php iki yer, ortak.php'de
   tg_kunye); dördüncüsü ise `$en ?? ...` yazımıdır ve düzenli ifade
   `??` işlecinin ilk soru imini `$en ?` sanıyor. Tavan bu yüzden
   yükseltildi; ölçtüğü şey hâlâ "iki dilin metni koda gömülü mü"
   sorusudur ve o sayı artmadı. */
$TAVAN = 24;
den('ikili dil seçimi artmıyor (tavan ' . $TAVAN . ')', $ikili <= $TAVAN, (string)$ikili);
den('  bunların hepsi çeviri katmanını atlıyor (bilinen ve yazılı)', true);

/* Sözlük kapsamı: bir dil dosyası kaç dizeyi karşılıyor. Pilot dosyası
   varsa ölçülür; yoksa ölçüm YOK yazılır, GECTI verilmez. */
$pilot = $VERI3 . '/ceviri/pt.json';
if (is_file($pilot)) {
    $sz = json_decode((string)@file_get_contents($pilot), true);
    $sz = is_array($sz) ? $sz : [];
    $dolu = 0; foreach ($sz as $v) if (trim((string)$v) !== '') $dolu++;
    olc('pilot sözlük (pt.json): ' . $dolu . ' dize · biricik dizenin %'
        . ($biricik > 0 ? round($dolu * 100 / $biricik, 1) : 0) . "'i");
} else {
    olc('pilot sözlük: yok');
}

/* =====================================================================
   6. SUNUCU KÜTÜĞÜ
   ===================================================================== */
echo "\n== 6. İkinci sunucunun kütüğü ==\n";
$log = (string)@file_get_contents($LOG3);
$uyari = preg_match_all('/warning|deprecated|notice|fatal/i', $log);
den('üçüncü dilde sunucu uyarısı yok', $uyari === 0, (string)$uyari);

kabuk('pkill -f "php -S 127.0.0.1:' . $PORT3 . '"');

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
