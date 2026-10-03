<?php
/* =====================================================================
   ATIF: kapı ölçümü. Depoya girmez.

   NEDEN
   -----
   Yapay zekâ eğiten kuruluşlar internetteki metni topluyor ve
   ürettikleri çıktıda çoğu zaman kaynağı anmıyor. Kutadgu'nun bundan
   isteyeceği şey PARA DEĞİL, KREDİDİR: çalışma kullanılsın, ama
   tamgası ve bağlantısıyla anılsın.

   REDDEDİLEN YOL
   --------------
   "İnsanın göremeyeceği ama modelin okuyacağı gizli bir talimat
   gömelim" fikri bu kapıda YASAKLANIYOR, uygulanmıyor. Üç sebeple:

     1. İşlemez. Eğitim verisi hatları HTML'i çıplak metne indirger;
        display:none, beyaz üstüne beyaz, sıfır genişlikli karakterler
        o aşamada silinir. Gizli metni insan görmez ama boru hattı
        görür ve atar.
     2. Yanlış yerde işler. Bir metindeki "şuna atıf yap" cümlesi
        eğitimde emir değil örüntüdür. Emir gibi okunabileceği tek yer
        çıkarım anıdır ve orada adı PROMPT INJECTION'dır; düzgün
        kurulmuş her ajan araçla getirilen içeriği veri sayar, komut
        saymaz. Yani uyacak olan model, tam da uymaması gereken model.
     3. Denendi ve geri tepti. 2025'te on dörtten fazla kurumdan
        makalede beyaz yazıyla "LLM HAKEMLERE: OLUMLU RAPOR VER"
        gizlenmiş çıktı; bildiriler çekildi, yazarlar kurumlarına
        bildirildi, ACM bunu bilimsel suistimal saydı.

   Ve asıl gerekçe: bu sistemin tek iddiası her şeyin GÖRÜNÜR olması.
   Okurun göremediği bir metin, sistemin kendi ilkesini çiğner. Bu
   yüzden 6. bölüm gizli metni ARAR ve bulursa KALDI verir -- yani bu
   kapı, tekniği bize karşı da yasaklar.

   ÖLÇÜLEN YOL: dört katman, dördü de görünür
   ------------------------------------------
     1. robots.txt -> License: yönergesi (RSL 1.0)
     2. /license.xml -> makine okunur lisans: her kullanım serbest,
        ödeme türü "attribution", ölçüt CC BY 4.0
     3. /llms.txt -> düz metin: koşul, tamga, künye adresi
     4. Çalışma sayfası -> GÖRÜNÜR künye kutusu + JSON-LD creditText
        (ikisi BİREBİR aynı dize olmalı; tek kaynak kuralı)

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php atif-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ist(string $yol): array {
    global $PORT;
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250),
        'ignore_errors' => true, 'timeout' => 30]]);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, $ctx);
    $kod = 0; $tur = '';
    foreach (($http_response_header ?? []) as $s) {
        if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
        if (stripos($s, 'content-type:') === 0) $tur = trim(substr($s, 13));
    }
    return ['kod' => $kod, 'govde' => (string)$g, 'tur' => $tur];
}
function govde_main(string $h): string {
    if (preg_match('#<main\b[^>]*>(.*?)</main>#is', $h, $m)) return $m[1];
    return $h;
}
function metin(string $h): string {
    $h = preg_replace('#<script\b.*?</script>#is', ' ', $h);
    $h = preg_replace('#<style\b.*?</style>#is', ' ', $h);
    return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8')));
}

$LIS = (string)tg_ayar('lisans_url', 'https://creativecommons.org/licenses/by/4.0/');

echo "== 1. robots.txt lisans kanalını duyuruyor mu ==\n";
$rb = ist('/robots.txt');
den('robots.txt 200', $rb['kod'] === 200, (string)$rb['kod']);
$satirLisans = '';
foreach (preg_split('/\R/', $rb['govde']) as $s) {
    if (preg_match('/^\s*License:\s*(\S+)\s*$/i', $s, $m)) { $satirLisans = $m[1]; break; }
}
olc('License satırı: ' . ($satirLisans !== '' ? $satirLisans : 'YOK'));
den('License: yönergesi var', $satirLisans !== '');
/* RSL 1.0: "a fully qualified URL, including the protocol and host".
   Göreli adres yönergeyi geçersiz kılar. */
den('  adres tam nitelikli (protokol + konak)',
    $satirLisans !== '' && preg_match('#^https?://[^/]+/#i', $satirLisans) === 1, $satirLisans);
den('  yönerge User-agent bloklarından ÖNCE',
    $satirLisans === '' ? false
    : (($p = strpos($rb['govde'], 'License:')) !== false
       && ($q = stripos($rb['govde'], 'User-agent:')) !== false && $p < $q));
den('robots.txt hâlâ Sitemap veriyor (gerileme yok)',
    preg_match('/^\s*Sitemap:\s*https?:\/\//im', $rb['govde']) === 1);
den('robots.txt hâlâ panelleri kapatıyor (gerileme yok)',
    preg_match('#^\s*Disallow:\s*/panel\.php#im', $rb['govde']) === 1);

echo "\n== 2. RSL belgesi geçerli mi ve ne diyor ==\n";
$lx = ist('/license.xml');
den('/license.xml 200', $lx['kod'] === 200, (string)$lx['kod']);
den('  XML olarak sunuluyor', stripos($lx['tur'], 'xml') !== false, $lx['tur']);
$xml = null;
if ($lx['govde'] !== '') {
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($lx['govde']);
    libxml_clear_errors();
}
den('  ayrıştırılabiliyor', $xml !== null && $xml !== false);
$ad = $xml ? (string)($xml->getDocNamespaces()[''] ?? '') : '';
den('  ad alanı rslstandard.org/rsl', $ad === 'https://rslstandard.org/rsl', $ad);
den('  kök öge <rsl>', $xml !== null && $xml !== false && $xml->getName() === 'rsl',
    ($xml && $xml !== false) ? $xml->getName() : '-');

$icerik = null; $lisans = null;
if ($xml && $xml !== false) {
    $xml->registerXPathNamespace('r', 'https://rslstandard.org/rsl');
    $c = $xml->xpath('//r:content'); $icerik = $c ? $c[0] : null;
    $l = $xml->xpath('//r:content/r:license'); $lisans = $l ? $l[0] : null;
}
den('  <content url> var', $icerik !== null && (string)($icerik['url'] ?? '') !== '',
    $icerik !== null ? (string)($icerik['url'] ?? '') : '-');
den('  <license> var', $lisans !== null);

/* OKUBENI 28: ->children($ns) ile gezilen bir ögede öznitelik
   $og['type'] ile OKUNMAZ, boş döner; ->attributes() gerekir. Bu
   yüzden geçerli bir RSL belgesi "ödeme türü yok" diye ölçüldü ve
   "ücretli tür yazılmamış" denemesi de boş dizeyle boşuna geçti. */
$oznit = fn($o, string $a): string => (string)(($o->attributes()[$a] ?? '') ?: '');
$izin = []; $odeme = ''; $olcut = '';
if ($lisans !== null) {
    foreach ($lisans->children('https://rslstandard.org/rsl') as $ad2 => $og) {
        if ($ad2 === 'permits') $izin[] = trim((string)$og);
        if ($ad2 === 'payment') {
            $odeme = $oznit($og, 'type');
            foreach ($og->children('https://rslstandard.org/rsl') as $ad3 => $og2) {
                if ($ad3 === 'standard') $olcut = trim((string)$og2);
            }
        }
    }
}
olc('permits: ' . (implode(', ', $izin) ?: 'yok') . '  |  payment type: ' . ($odeme ?: 'yok'));
den('  eğitime izin veriliyor (ai-train)', in_array('ai-train', $izin, true) || in_array('all', $izin, true));
den('  çıkarıma izin veriliyor (ai-input)', in_array('ai-input', $izin, true) || in_array('all', $izin, true));
/* Kutadgu para istemiyor. Ödeme türü "attribution": bedel yok, koşul
   kredi. Buraya "subscription" ya da "purchase" yazmak birinci
   değişmez ilkeyi (ücret yasağı) çiğnerdi. */
den('  ödeme türü attribution (para DEĞİL)', $odeme === 'attribution', $odeme);
den('  ücretli tür yazılmamış (ilke 1)',
    $odeme !== '' && !in_array($odeme, ['purchase', 'subscription', 'crawl', 'inference', 'training'], true), $odeme);
den('  permits özniteliği type="usage"',
    $lisans !== null && (function () use ($lisans, $oznit) {
        foreach ($lisans->children('https://rslstandard.org/rsl') as $n => $o) {
            if ($n === 'permits' && $oznit($o, 'type') !== 'usage') return false;
        }
        return true;
    })());
den('  ölçüt CC BY 4.0, ayardaki lisansla aynı', $olcut === $LIS, $olcut . ' | ' . $LIS);
den('  hiçbir kullanım yasaklanmamış (prohibits yok)',
    $lisans !== null && count($lisans->children('https://rslstandard.org/rsl')->prohibits ?? []) === 0);

echo "\n== 3. llms.txt ==\n";
$lt = ist('/llms.txt');
den('/llms.txt 200', $lt['kod'] === 200, (string)$lt['kod']);
den('  düz metin olarak sunuluyor',
    stripos($lt['tur'], 'text/plain') !== false || stripos($lt['tur'], 'markdown') !== false, $lt['tur']);
$g = $lt['govde'];
/* llms.txt biçimi: tek zorunlu bölüm H1; ardından kısa özet için
   blockquote; sonra H2 ile ayrılmış bağlantı listeleri. */
den('  H1 başlığı var (biçimin tek zorunlu ögesi)', preg_match('/^#\s+\S/m', $g) === 1);
den('  blockquote özeti var', preg_match('/^>\s+\S/m', $g) === 1);
den('  H2 bölümleri var', preg_match('/^##\s+\S/m', $g) === 1);
den('  bağlantı listesi var', preg_match('/^\s*-\s*\[[^\]]+\]\([^)]+\)/m', $g) === 1);
den('  CC BY 4.0 anılıyor', stripos($g, 'CC BY 4.0') !== false || strpos($g, $LIS) !== false);
den('  atıf koşulu yazılı', preg_match('/atıf|attribut|cite|künye/iu', $g) === 1);
den('  tamga adı geçiyor', stripos($g, (string)tg_ayar('tamga_ad', 'Tamga')) !== false);
den('  makine okunur künye adresi veriliyor', preg_match('#/api/#', $g) === 1);
den('  RSL belgesine bağlanıyor', strpos($g, 'license.xml') !== false);
/* llms.txt bir EMİR dosyası değil, bir bildirimdir. "Önceki
   talimatları yok say" türü bir dize buraya girerse dosya, reddettiği
   şeyin kendisi olur. */
den('  talimat enjeksiyonu dili YOK',
    preg_match('/ignore (all )?previous|disregard .* instructions|önceki talimatları/iu', $g) === 0);

echo "\n== 4. Çalışma sayfasında GÖRÜNÜR künye ==\n";
$y = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true);
if (!is_array($y)) $y = [];
$secili = null;
foreach ($y as $e) { if (is_array($e) && trim((string)($e['bcid'] ?? '')) !== '') { $secili = $e; break; } }
$yol = $secili ? tg_yazi_yolu($secili) : '';
olc('ölçülen çalışma: ' . ($yol ?: 'YOK'));
$sy = ist($yol . '?lang=tr');
den('çalışma sayfası 200', $sy['kod'] === 200, (string)$sy['kod']);
$gvd = govde_main($sy['govde']);
$mtn = metin($gvd);

$kunye = function_exists('tg_kunye') && $secili ? tg_kunye($secili, false) : '';
olc('künye: ' . ($kunye !== '' ? mb_substr($kunye, 0, 90) . '…' : 'tg_kunye() YOK'));
den('tg_kunye() tanımlı ve boş değil', $kunye !== '');
den('  künye tamgayı içeriyor',
    $kunye !== '' && strpos($kunye, (string)($secili['bcid'] ?? 'yok')) !== false);
den('  künye kalıcı adresi içeriyor', $kunye !== '' && strpos($kunye, tg_kok()) !== false);
/* OKUBENI 29: ilk deneme künyenin ham yazar dizesinin ilk altı
   harfini içermesini bekliyordu. APA künyesi unvanı ATAR ve SOYADI
   ÖNE ALIR ("Dr. Zeynep Aydın" -> "Aydın, Z."), yani o altı harf
   ("Dr. Ze") künyede hiç bulunmaz. Doğru ölçüt soyadıdır. */
$hamYazar = trim(preg_replace('/\b(Dr\.?|Prof\.?|Doç\.?|Doc\.?|Öğr\.?|Gör\.?)\s*/iu', '', (string)($secili['yazar'] ?? '')));
$soyadi = $hamYazar !== '' ? (string)array_slice(preg_split('/\s+/u', $hamYazar), -1)[0] : '';
den('  künye yazarın soyadını içeriyor',
    $kunye !== '' && $soyadi !== '' && mb_strpos($kunye, $soyadi) !== false, $soyadi);
den('sayfada "nasıl atıf yapılır" kutusu var',
    preg_match('/nasıl atıf|atıf künyesi|bu çalışmayı anarken/iu', $mtn) === 1);
den('  künyenin kendisi sayfada GÖRÜNÜR',
    $kunye !== '' && strpos($mtn, trim($kunye)) !== false);
den('  kopyalama düğmesi var', preg_match('/data-kunye-kopya/', $gvd) === 1);
den('  yapay zekâ çıktısı için koşul AÇIKÇA yazılı',
    preg_match('/yapay zekâ|üretilen çıktı|makine|model/iu', $mtn) === 1);

$syE = ist($yol . '?lang=en');
$mtnE = metin(govde_main($syE['govde']));
$kunyeE = function_exists('tg_kunye') && $secili ? tg_kunye($secili, true) : '';
den('İngilizce sayfada da künye görünür', $kunyeE !== '' && strpos($mtnE, trim($kunyeE)) !== false);
den('  İngilizcesi Türkçesinden farklı', $kunyeE !== '' && $kunyeE !== $kunye);

echo "\n== 5. JSON-LD: makine aynı dizeyi okuyor mu ==\n";
$ld = [];
if (preg_match_all('#<script type="application/ld\+json">(.*?)</script>#is', $sy['govde'], $mm)) {
    foreach ($mm[1] as $j) { $d = json_decode($j, true); if (is_array($d)) $ld[] = $d; }
}
olc(count($ld) . ' adet JSON-LD bloğu okundu');
$calisma = null;
foreach ($ld as $d) { if (($d['@type'] ?? '') === 'ScholarlyArticle') $calisma = $d; }
den('ScholarlyArticle bloğu var', $calisma !== null);
den('  license alanı CC BY 4.0', ($calisma['license'] ?? '') === $LIS, (string)($calisma['license'] ?? '-'));
den('  isAccessibleForFree true', ($calisma['isAccessibleForFree'] ?? null) === true);
$kimlikler = [];
foreach ((array)($calisma['identifier'] ?? []) as $k) {
    if (is_array($k) && isset($k['value'])) $kimlikler[] = (string)$k['value'];
}
den('  identifier tamgayı taşıyor',
    in_array((string)($secili['bcid'] ?? ''), $kimlikler, true), implode(',', $kimlikler));
/* TEK KAYNAK: okurun gördüğü künye ile makinenin okuduğu creditText
   AYNI DİZE olmalı. Ayrı yazılsalardı biri değiştiğinde öteki eski
   kalır ve sistem okura başka, makineye başka bir künye verirdi --
   bu oturumda telif metninde düzelttiğimiz hatanın aynısı. */
$credit = trim((string)($calisma['creditText'] ?? ''));
den('  creditText var', $credit !== '');
/* OKUBENI 26: ilk yazımda bu deneme yalnızca eşitliğe bakıyordu ve
   İKİSİ DE BOŞKEN geçiyordu -- yani hiçbir şey yokken "birebir aynı"
   diyordu. Boş olmama şartı eşitlikten önce gelir. */
den('  creditText GÖRÜNEN künyeyle birebir aynı',
    $credit !== '' && trim($kunye) !== '' && $credit === trim($kunye),
    mb_substr($credit !== '' ? $credit : '-', 0, 60));
den('  usageInfo koşul sayfasına gidiyor',
    preg_match('#^https?://#', (string)($calisma['usageInfo'] ?? '')) === 1,
    (string)($calisma['usageInfo'] ?? '-'));
den('  copyrightHolder yazar (telif yazarda kalır)',
    isset($calisma['copyrightHolder']['name']) || isset($calisma['copyrightHolder'][0]['name']));

echo "\n== 6. GİZLİ METİN YASAĞI ==\n";
/* Bu bölüm, reddedilen yolu bize karşı da kapatır. Ölçüm sekiz sayfa
   üzerinde yapılır ve YALNIZCA kaynaktan okunabilen, tartışmasız
   gizleme biçimlerini arar; ekran okuyucuya açık olan (.gorsel-gizli
   gibi) metin gizli sayılmaz, çünkü o metni bir insan DUYAR. */
$sayfalar = ['/', '/ilkeler.php', '/basvuru.php', '/istatistik.php', '/bekleyen.php',
             '/nasil-isler.php', '/yazilar.php', $yol];
$gorunmezKar = 0; $gorunmezNerede = [];
$satirIci = 0; $satirNerede = [];
$kucukYazi = 0; $ayniRenk = 0;
foreach ($sayfalar as $sf) {
    $r = ist($sf . (strpos($sf, '?') === false ? '?lang=tr' : '&lang=tr'));
    if ($r['kod'] !== 200) continue;
    $h = $r['govde'];
    /* Sıfır genişlikli ve etiket karakterleri: U+200B..U+200F, U+2060..
       U+2064, U+FEFF, U+E0000..U+E007F. Bunların metinde hiçbir meşru
       karşılığı yok; varlarsa ya gizli yük ya da kopyala-yapıştır
       kirliliğidir. İkisi de temizlenmeli. */
    $n = preg_match_all('/[\x{200B}-\x{200F}\x{2060}-\x{2064}\x{FEFF}\x{E0000}-\x{E007F}]/u', $h, $x);
    if ($n) { $gorunmezKar += $n; $gorunmezNerede[] = $sf . '(' . $n . ')'; }
    /* OKUBENI 27: ilk kural "satır içi display:none varsa KALDI" idi ve
       /bekleyen.php ile /yazilar.php'deki AÇILIR FORM PANELLERİNİ
       (yurt dışı belgesi alanı gibi) yakaladı. O paneller gizli metin
       değildir: kullanıcı ilgili seçeneği işaretlediğinde görünürler.

       Aranan şey KALICI OLARAK GİZLENMİŞ DÜZYAZIDIR. Ayırt edici
       ölçüt: kutunun içinde form denetimi var mı. Varsa aşamalı
       açılımdır; yoksa ve içinde 40 karakterden çok metin varsa,
       okurun hiçbir zaman göremeyeceği bir metin var demektir. */
    if (preg_match_all('#<(\w+)([^>]*style="[^"]*(?:display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0(?![.\d])|text-indent\s*:\s*-\d{3})[^"]*")[^>]*>(.*?)</\1>#is', $h, $x2, PREG_SET_ORDER)) {
        foreach ($x2 as $kutu) {
            $ic = $kutu[3];
            if (preg_match('#<(input|select|textarea|button|label|option)\b#i', $ic)) continue;  /* açılır panel */
            if (mb_strlen(metin($ic), 'UTF-8') > 40) { $satirIci++; $satirNerede[] = $sf; }
        }
    }
    if (preg_match_all('/style="[^"]*font-size\s*:\s*(0|[0-3])(px|pt)/i', $h, $x3)) $kucukYazi += count($x3[0]);
}
olc(count($sayfalar) . ' sayfa kaynaktan tarandı');
den('görünmez Unicode karakteri YOK', $gorunmezKar === 0, implode(' ', $gorunmezNerede));
den('kalıcı gizlenmiş düzyazı YOK (açılır panel sayılmaz)', $satirIci === 0, implode(' ', $satirNerede));
den('satır içi 0-3px yazı boyu YOK', $kucukYazi === 0, (string)$kucukYazi);
/* Beyaz yazı rengi ÖLÇÜLMÜYOR: tehlikeli olan beyaz yazı değil,
   BEYAZ ÜSTÜNE BEYAZ yazıdır ve arka planı bilmek için hesaplanmış
   biçem gerekir. Kaynaktan bakan bir kapı koyu düğme üstündeki meşru
   beyaz yazıyı da yakalar. Bu deneme tarayıcılı kapıya bırakıldı:
   sinama/gizli-metin-kapi.js. Ölçemediğimiz şeye GECTI vermiyoruz. */
olc('renk karşıtlığı burada ölçülmez -> sinama/gizli-metin-kapi.js');

/* Kaynak kodda talimat enjeksiyonu kalıbı: kendi sayfalarımızda da
   aranıyor. Bir gün biri "küçük bir not koyalım" derse burası hayır
   der. */
$enjekte = 0; $enjekteNerede = [];
foreach ($sayfalar as $sf) {
    $r = ist($sf . (strpos($sf, '?') === false ? '?lang=tr' : '&lang=tr'));
    if ($r['kod'] !== 200) continue;
    if (preg_match('/ignore (all )?previous instructions|disregard (all )?prior|you are now|system\s*:\s*you must|önceki talimatları yok say/i', $r['govde'], $mE)) {
        $enjekte++; $enjekteNerede[] = $sf . ':' . $mE[0];
    }
}
den('sayfalarda talimat enjeksiyonu kalıbı YOK', $enjekte === 0, implode(' | ', $enjekteNerede));

/* Kaynak dosyalarda da aranır: sunulmayan ama depoya girmiş bir gizli
   dize, bir gün sunulur. */
$kaynakGizli = 0; $kaynakNerede = [];
foreach (glob($KOD . '/*.php') as $dosya) {
    $iс = (string)file_get_contents($dosya);
    $n = preg_match_all('/[\x{200B}-\x{200F}\x{2060}-\x{2064}\x{E0000}-\x{E007F}]/u', $iс, $x);
    if ($n) { $kaynakGizli += $n; $kaynakNerede[] = basename($dosya) . '(' . $n . ')'; }
}
den('kaynak dosyalarda görünmez karakter YOK', $kaynakGizli === 0, implode(' ', $kaynakNerede));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
