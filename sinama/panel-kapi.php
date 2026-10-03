<?php
/* =====================================================================
   PANEL SEKMELERİ VE BEKLEYEN İŞ SAYACI: kapı ölçümü. Depoya girmez.

   İki kusur ölçülür.

   1. SEKME AYRIMI. "Editör" ile "Yönetim" sekmelerinin içerikleri
      birbirine karışmıştı: editörlüğün asıl işi olan yazar başvuruları
      ve hakemlik süreçleri yönetimde, yönetimin işi olan sunucu durumu
      ve okuma raporları editördeydi. Ayrım tek soruyla yapılır:
        çalışmalar ve insanlar üzerine bir karar mı -> EDİTÖR
        sitenin kendisi üzerine bir iş mi           -> YÖNETİM

   2. BEKLEYEN İŞ SAYACI KENDİ ÇALIŞMASINI SAYIYORDU. Profil
      dairesindeki rozet 1 gösteriyor, panelin "Sizi bekleyenler" kartı
      ise "şu anda sizi bekleyen bir iş yok" diyordu. Sebep: rozet,
      hakem aranan BÜTÜN çalışmaları sayıyordu; oysa editör kendi
      çalışmasına hakem atayamaz (/yonetim/hakem-ata 403 verir) ve
      panel kartı bunu zaten dışarıda bırakıyordu. Aynı soruya iki
      farklı yanıt veren sistem, ikisinde de güveni kaybeder.

   Bu kapı KAYNAK ve DAVRANIŞ ölçer: sekme yerleşimi sayfanın
   çıktısından, sayaç kuralı ise ucun kodundan ve tg_yazar_mi()
   davranışından.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php panel-kapi.php
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

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

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

/* =====================================================================
   1. SEKME AYRIMI
   ---------------------------------------------------------------------
   Yetkili bir kullanıcı olmadan bu bölümler basılmadığı için ölçüm,
   kod kopyası üzerinde yetkileri açıp sayfayı ÜRETEREK yapılır. Ölçüm
   kendi kopyasında çalışır; ana sunucuya ve veri dizinine dokunmaz.
   ===================================================================== */
echo "== 1. Sekme ayrımı ==\n";

$kopya = '/tmp/kpanel';
shell_exec('rm -rf ' . escapeshellarg($kopya) . ' && cp -a ' . escapeshellarg($KOD) . ' ' . escapeshellarg($kopya)
    . ' && rm -rf ' . escapeshellarg($kopya . '/.git'));
$pn = (string)file_get_contents($kopya . '/panel.php');
/* Yetkiler açılır: ölçülen şey YETKİ değil, YERLEŞİMdir. Yetkinin
   kendisi hs_editor_mu()/hs_bas_yetki() ile belirlenir ve bu kapı ona
   dokunmaz; yalnız iki kartın hangi sekmede durduğuna bakar. */
$pn2 = preg_replace('/^\$edYetki\s*=.*$/m',  '$edYetki  = true;', $pn, 1);
$pn2 = preg_replace('/^\$basYetki\s*=.*$/m', '$basYetki = true;', (string)$pn2, 1);
file_put_contents($kopya . '/panel.php', (string)$pn2);
den('yetkiler ölçüm kopyasında açıldı', $pn2 !== $pn);

/* OKUBENI 6/25: SAYFANIN DİLİ VARSAYILAN İNGİLİZCEDİR. k_dil()
   sırayla ?lang=, çerez, ülke, Accept-Language ve ayara bakar; komut
   satırında bunların hiçbiri yoktur ve dil varsayılana, yani
   İngilizceye düşer. İlk yazımda kapı Türkçe kart adlarını arıyordu ve
   sayfa doğru üretildiği hâlde on kartın dokuzunu "YOK" ölçtü. Dil
   açıkça istenir; sayfa panel.php'yi saran küçük bir başlatıcıdan
   çağrılır, çünkü $_GET'i dosyanın kendisine yazmak kaynağı bozardı. */
file_put_contents($kopya . '/_olcum.php',
    "<?php \$_GET['lang']='tr'; \$_SERVER['HTTP_HOST']='127.0.0.1'; require __DIR__.'/panel.php';\n");
$cikti = (string)shell_exec('cd ' . escapeshellarg($kopya) . ' && KUTADGU_DATA=' . escapeshellarg($VERI)
    . ' php -d error_reporting=E_ALL _olcum.php 2>&1');
den('panel sayfası üretiliyor', strlen($cikti) > 5000 && str_contains($cikti, '</html>'), (string)strlen($cikti));
den('  üretimde PHP uyarısı yok',
    !preg_match('/(Warning|Fatal error|Notice|Deprecated)[: ]/i', $cikti));

$pEd  = strpos($cikti, 'data-pnl="editor"');
$pYon = strpos($cikti, 'data-pnl="yonetim"');
den('iki sekme de basılıyor', $pEd !== false && $pYon !== false);
den('  editör sekmesi yönetimden önce', $pEd !== false && $pYon !== false && $pEd < $pYon);

/* Hangi kart hangi sekmede: ölçüt kartın adı değil, gövdedeki YERİdir. */
/* KART ADI, BAŞLIK İŞARETLEMESİYLE ARANIR.
   Eskiden çıplak dize aranıyordu ve bu, ölçümü bir gün yanılttı:
   panel.php'nin CSS bölümüne yazılan bir gerekçe yorumu "Hesap daveti"
   ifadesini geçiriyordu. CSS sayfaya basılır, dolayısıyla ilk eşleşme
   <style> içinde çıktı ve kapı, yönetim sekmesindeki kartı editör
   sekmesinde sandı.

   Aranan şey artık kartın BAŞLIĞIdır: '<h2>Hesap daveti'. Bir gerekçe
   yorumu <h2> açmaz. */
$nerede = function (string $iz) use ($cikti, $pEd, $pYon): string {
    $i = strpos($cikti, '<h2>' . $iz);
    if ($i === false) $i = strpos($cikti, $iz);   /* yedek: başlıksız kart */
    if ($i === false) return 'YOK';
    return $i < $pYon ? 'editor' : 'yonetim';
};
$beklenen = [
    'Yazar başvuruları'                       => 'editor',
    'Çalışmalar ve hakem atama'               => 'editor',
    'Hakemlik süreçleri'                      => 'editor',
    'Doktora belgesi bekleyenler'             => 'editor',
    'Önerilen bilim dalları'                  => 'editor',
    'Değerlendirme süreleri'                  => 'editor',
    'Hesap daveti'                            => 'yonetim',
    'Sunucu durumu'                           => 'yonetim',
    'Okuma raporları'                         => 'yonetim',
    'Son okuma kayıtları'                     => 'yonetim',
];
$yanlis = [];
foreach ($beklenen as $ad => $sek) {
    $b = $nerede($ad);
    olc(str_pad($ad, 30) . $b);
    if ($b !== $sek) $yanlis[] = $ad . ' (' . $b . ', olmalı: ' . $sek . ')';
}
den('editörlük kartları Editör sekmesinde, site yönetimi kartları Yönetim sekmesinde',
    $yanlis === [], implode(' | ', $yanlis));

/* -----------------------------------------------------------------
   BASILMADAN HİÇBİR ŞEY GÖSTERMEYEN KART ÜSTTE DURAMAZ.

   "Sunucu durumu az kullanılan bir özellik" diye bildirildi. Ölçüt
   tahmin değil, kartın kendi davranışıdır: bu kart açıldığında
   #durumIc BOŞtur; içeriği ancak "Durumu getir" düğmesine basılınca
   gelir. Yönetim sekmesindeki öteki üç kart ise açılır açılmaz kendi
   verisini gösterir. Bakılan bir kart ile ÇAĞRILAN bir araç aynı
   yerde duramaz.

   İki şey ölçülür: kart sekmenin SONUNDA mı, ve kapağı kapalı mı.
   ----------------------------------------------------------------- */
$yonBolum = substr($cikti, (int)$pYon, (strpos($cikti, '/yonetim -->') ?: strlen($cikti)) - (int)$pYon);
$sira = [];
foreach (['Hesap daveti', 'Okuma raporları', 'Son okuma kayıtları', 'Sunucu durumu'] as $ad) {
    $i = strpos($yonBolum, $ad);
    if ($i !== false) $sira[$ad] = $i;
}
asort($sira);
olc('yönetim sekmesi sırası: ' . implode(' -> ', array_keys($sira)));
den('sunucu durumu yönetim sekmesinin sonunda',
    array_key_last($sira) === 'Sunucu durumu', (string)array_key_last($sira));
den('  sunucu durumu kapaklı bir kart (details) ve kapağı kapalı',
    (bool)preg_match('/<details[^>]*id="kartDurum"[^>]*>/', $yonBolum)
    && !preg_match('/<details[^>]*id="kartDurum"[^>]*\bopen\b/', $yonBolum));
den('  kapağın altındaki düğmeler yerinde duruyor (kart silinmedi)',
    str_contains($yonBolum, 'id="durumYenile"') && str_contains($yonBolum, 'id="durumPosta"')
    && str_contains($yonBolum, 'id="durumIc"'));
/* Kapağın üstünde ne olduğu yazılı olmalı: yalnız "Sunucu durumu"
   yazan kapalı bir kutu, açmadan ne bulacağını söylemez. */
den('  kapakta kartın ne gösterdiği yazılı',
    (bool)preg_match('/id="kartDurum".{0,600}<summary.{0,400}<span>/su', $yonBolum));

/* Bir kart iki sekmede birden olamaz. */
$ikiKez = [];
foreach (array_keys($beklenen) as $ad) if (substr_count($cikti, $ad) > 2) $ikiKez[] = $ad;
olc('iki kereden çok geçen başlık: ' . ($ikiKez ? implode(',', $ikiKez) : 'yok'));

/* Etiket dengesi: taşıma sırasında bir </div> düşerse sayfa sessizce
   bozulur ve bunu yalnız düzen ölçümü fark eder. */
$bolum = substr($cikti, (int)$pEd, strpos($cikti, '/yonetim -->') - (int)$pEd);
den('taşınan bölümde <div> dengesi bozulmamış',
    substr_count($bolum, '<div') === substr_count($bolum, '</div>'),
    substr_count($bolum, '<div') . ' / ' . substr_count($bolum, '</div>'));

/* =====================================================================
   2. BEKLEYEN İŞ SAYACI
   ===================================================================== */
echo "\n== 2. Bekleyen iş sayacı ==\n";

$api = kodsuz($KOD . '/api/index.php');
den('tg_yazar_mi() var (kendi çalışması ayırt edilebiliyor)', function_exists('tg_yazar_mi'));
/* Rozeti üreten blok, hakem aranan çalışmaları sayarken kendi
   çalışmasını atlıyor mu. Desen bloğun KENDİSİNE bakar; dosyanın
   herhangi bir yerinde tg_yazar_mi geçmesi yetmez (OKUBENI 22). */
/* BU ÖLÇÜM 20 AĞUSTOS 2026'DA DEĞİŞTİ. Eskiden rozeti üreten AYRI
   bloğu arıyor ve o blokta tg_yazar_mi() geçiyor mu diye bakıyordu;
   yani "iki kopya var ama ikisi de aynı ölçütü kullanıyor" demenin
   yoluydu. İki kopya iki kez ayrıştığı için kopya kaldırıldı: liste de
   rozet de bekleyen_isler()'den geliyor. Ölçüt artık daha güçlü —
   ayrışma mümkün değil, çünkü ikinci sayım YOK. */
$apiHam = (string)@file_get_contents($KOD . '/api/index.php');
$fb = strpos($apiHam, 'function bekleyen_isler(array $h): array');
$fs = $fb !== false ? strpos($apiHam, "\nif (\$yol === '/hesap/durum'", $fb) : false;
$blok = ($fb !== false && $fs !== false) ? substr($apiHam, $fb, $fs - $fb) : '';
olc('bekleyen_isler() bloğu ' . (strlen($blok) ? strlen($blok) . ' bayt' : 'BULUNAMADI'));
den('bekleyen işler tek kaynaktan üretiliyor', $blok !== '');
den('  sayaç kendi çalışmasını atlıyor',
    $blok !== '' && str_contains($blok, 'tg_yazar_mi($e2, $h)) continue;'));
den('  rozet listeyi SAYIYOR, ikinci kez saymıyor',
    str_contains($apiHam, '$bekleyen = count(bekleyen_isler($h));'));
den('  panel kartı da aynı listeyi alıyor',
    str_contains($apiHam, '$bekleyen = bekleyen_isler($h);'));

/* Ucun kendisi de aynı şeyi söylüyor mu: editör kendi çalışmasına
   hakem atayamaz. Kural üç yerde birden tutmalı, yoksa arayüz
   sistemin yasakladığı bir işi teklif eder. */
den('atama ucu kendi çalışmasını reddediyor',
    (bool)preg_match('/hakem-ata.{0,3000}?kendi çalışmasına hakem atayamaz/su', $api));

/* Davranış: sınama verisindeki bir çalışmanın yazarı, o çalışmanın
   yazarı sayılıyor mu. Ölçülecek veri yoksa deneme ÖLÇÜM yazılır
   (OKUBENI 34). */
$y = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true);
$ornek = null;
if (is_array($y)) foreach ($y as $e) {
    if (is_array($e) && trim((string)($e['yazar'] ?? '')) !== '') { $ornek = $e; break; }
}
if ($ornek === null) {
    olc('sınama verisinde yazarı olan çalışma yok; davranış denemesi yapılamadı');
} else {
    /* SALT ADLA EŞLEŞME BİLEREK YETMEZ: ad, herkesin kendi yazdığı bir
       alandır ve tg_yazar_mi() ada dayalı eşleşmeyi ancak hesabın
       kimlik doğrulaması onaylıysa kabul eder. İlk yazımda kapı
       doğrulamasız bir hesapla deneyip "kendi yazarını tanımıyor" diye
       sahte bir KALDI verdi; oysa ölçülen şey kusur değil, kuralın
       kendisiydi. Deneme artık iki yoldan da ölçüyor. */
    $ad  = (string)($ornek['yazar_bilgi']['ad'] ?? $ornek['yazar'] ?? '');
    $orc = (string)($ornek['yazar_bilgi']['orcid'] ?? '');
    olc('örnek çalışma: ' . $ad . ($orc !== '' ? ' (ORCID var)' : ' (ORCID yok)'));
    if ($orc !== '') {
        den('tg_yazar_mi() yazarı ORCID ile tanıyor', tg_yazar_mi($ornek, ['orcid' => $orc]), $orc);
        /* KURALIN KENDİSİ: kayıtta ORCID varsa AD eşleşmesi kabul
           edilmez; kimlik, kişiye bağlı alandan kanıtlanır. İlk yazımda
           kapı bunu kusur sandı ve doğrulanmış bir hesabın adla
           tanınmasını bekledi; oysa tanınmaması kuralın ta kendisiydi.
           Ölçüm yanlış çıktığında önce ölçümden şüphelen. */
        den('  kayıtta ORCID varken salt ad yetmiyor (kimlik kişiye bağlı alandan kanıtlanır)',
            !tg_yazar_mi($ornek, ['ad' => $ad, 'dogrulama' => ['durum' => 'onayli']]));
    } else {
        den('tg_yazar_mi() ORCID\'siz kaydı doğrulanmış hesabın adıyla tanıyor',
            tg_yazar_mi($ornek, ['ad' => $ad, 'dogrulama' => ['durum' => 'onayli']]), $ad);
        den('  doğrulanmamış hesabı salt adla yazar saymıyor',
            !tg_yazar_mi($ornek, ['ad' => $ad]));
    }
    den('  ilgisiz birini yazar saymıyor',
        !tg_yazar_mi($ornek, ['ad' => 'Dr. Bambaşka Biri', 'dogrulama' => ['durum' => 'onayli']]));
}

/* =====================================================================
   4. SAYI ŞERİDİ, ROZETİN ADI VE İLETİLER SEKMESİ (13 Ağustos 2026)
   ---------------------------------------------------------------------
   Üç kusur bildirildi ve üçü de aynı cinsten: SİSTEM BİR SAYI
   GÖSTERİYOR AMA NE SAYDIĞINI SÖYLEMİYOR.

     a) "Editör 2" yazıyordu ve iki'nin ne olduğu hiçbir yerde yoktu.
        Sebebi ölçüldü: rozet İKİ ayrı işi birden sayıyordu — bekleyen
        iletiler ile editörlük işleri. Bir sayacın iki şeyi birden
        sayması, saydığı hiçbir şeyi söyleyememesidir.
     b) İletiler Editör sekmesinin içindeydi. Gerekçesi "iletiyi okumak
        editörün işidir" diye yazılmıştı; bu SORUMLUYU söyler, işin ne
        olduğunu değil. Gelen kutusu kendi başlığını alır.
     c) Kapalı kapaklı kart satırın tamamını alıyordu.

   Bu bölüm dördünü birden ölçer: iletilerin kendi sekmesinde olduğunu,
   rozetin artık iki işi birden saymadığını, şeridin TEK bir yazıcıdan
   beslendiğini ve gizli bir sekmenin adresten açılamadığını.
   ===================================================================== */
echo "\n== 4. Sayı şeridi ve iletiler sekmesi ==\n";

$pnKaynak = (string)@file_get_contents($KOD . '/panel.php');

/* a) İletiler kendi bölümünde ve Editör bölümünün DIŞINDA. */
$pIlt = strpos($cikti, 'data-pnl="iletiler"');
den('iletiler kendi bölümünde', $pIlt !== false);
$pIltSon = $pIlt !== false ? strpos($cikti, '/iletiler -->', $pIlt) : false;
$pKart = strpos($cikti, 'id="kartIleti"');
den('  İletiler kartı o bölümün içinde',
    $pIlt !== false && $pIltSon !== false && $pKart !== false && $pKart > $pIlt && $pKart < $pIltSon);
den('  İletiler kartı artık Editör bölümünde değil',
    $pKart !== false && $pEd !== false && $pKart < $pEd);
den('  sekme düğmesi var ve yetkisizde gizli başlıyor',
    (bool)preg_match('/class="pn-sk gizli"[^>]*data-sek="iletiler" id="sekIletiler"/', $cikti));
/* ÖLÇÜT DEĞİŞTİ — 19 Ağustos 2026, kurul bildirimi: "editörler siteden
   gelen mesajları görmesin, baş editörler görecek." Burada bir zaman
   "düğme EDİTÖR yetkisiyle açılıyor" ölçülüyordu ve o gün doğruydu.
   Bugün yanlış olurdu: bir ölçüm, ölçtüğü kural değiştiğinde eski
   kuralı savunan bir bekçiye dönüşür. Gerekçesi panel.php'de İletiler
   bölümünün başında ve api'de ileti-liste ucundadır. */
den('  düğme BAŞ EDİTÖR yetkisiyle açılıyor',
    str_contains($pnKaynak, "goster(\\\$('sekIletiler'), !!h.bas_yetki);"));
den('  ve bölüm sunucuda yalnız baş editöre basılıyor',
    (bool)preg_match('#<\\?php if \\(\\$basYetki\\): \\?>\\s*<section class="pn-pnl" data-pnl="iletiler">#', $pnKaynak));

/* b) Rozet artık iki işi birden saymıyor: iltBekYaz rozEditor'a
   yazmamalı. Ölçüt kodun kendisidir; sayı ekranda ancak veri varken
   görünür, kural ise her zaman geçerlidir. */
$iltBlok = '';
if (preg_match('/function iltBekYaz\(n\)\s*\{.*?\n  \}/su', $pnKaynak, $m)) $iltBlok = $m[0];
olc('iltBekYaz bloğu ' . ($iltBlok !== '' ? strlen($iltBlok) . ' bayt' : 'BULUNAMADI'));
den('ileti sayısı Editör rozetine yazılmıyor',
    $iltBlok !== '' && !str_contains($iltBlok, 'rozEditor'));
den('  ileti sayısı kendi rozetine yazılıyor',
    $iltBlok !== '' && str_contains($iltBlok, "roz('rozIletiler'"));

/* c) SAYI TEK YERDE. 14 Ağustos 2026 kurul bildirimi: "üstteki '1 iş
   sizi bekliyor' orada olmasın, başka bir formül lazım."

   Sayı ekranda ÜÇ KEZ yazıyordu: şerit kutusu, sekme rozeti ve
   Özet'teki kart. Üç yerde yazan bir sayı, üç yerde ayrı düşme riski
   taşır. Şerit kaldırıldı; sayı ait olduğu sekmenin üstünde, bir kez.

   Bu bölüm eskiden "şeride yazan tek yer var mı" diye sorardı. Artık
   şeridin GERİ GELMEDİĞİNİ ölçüyor: bir gün biri aynı sayıyı ikinci
   kez ekrana koymak isterse kapı görür. */
$serityaz = [];
foreach (explode("\n", $pnKaynak) as $n => $satir) {
    if (!preg_match('/\bSERIT\[[^\]]+\]\s*=/', $satir)) continue;
    $kirp = ltrim($satir);
    if ($kirp === '' || $kirp[0] === '*' || strpos($kirp, '//') === 0) continue;
    $serityaz[] = $n + 1;
}
olc('SERIT[...] = yazan satır: ' . (count($serityaz) ? implode(',', $serityaz) : 'yok'));
den('sayı şeridi geri gelmemiş (aynı sayı ikinci kez yazılmıyor)', $serityaz === [],
    implode(',', $serityaz));
den('  şerit kabı da sayfada yok', !str_contains($cikti, 'id="pnSerit"'));
den('  şeridi çizen işlev de yok', !str_contains($pnKaynak, 'function seritCiz'));
/* Rozet DURUYOR: kaldırılan şey sayının kopyasıydı, sayının kendisi
   değil. Bir kaldırma, kaldırmayı amaçlamadığı şeyi de götürmemeli. */
den('  sayı yine de yazılıyor (roz() duruyor)',
    (bool)preg_match('/function roz\(id,n,ad,sek\)/', $pnKaynak));

/* d) Rozetin adı DÜĞMEYE yazılır. Bir düğmenin içindeki span'a konan
   aria-label okunmaz; düğmenin kendi adı okunur. */
den('sayının adı sekme düğmesinin erişilebilir adına yazılıyor',
    (bool)preg_match('/dg\.setAttribute\(\x27aria-label\x27,\s*temel\s*\+/', $pnKaynak));
den('  ipucu da aynı çağrıdan geliyor', str_contains($pnKaynak, "dg.title=n+' '+ad;"));
den('  sayı sıfırlanınca ad da siliniyor',
    str_contains($pnKaynak, "dg.removeAttribute('aria-label')"));

/* e) Her rozet çağrısı bir AD taşır. Adsız bir çağrı, şeritte
   görünmeyen ve ipucu vermeyen bir sayı bırakır. */
$adsiz = [];
if (preg_match_all('/roz\(\x27(roz[A-Za-z]+)\x27\s*,([^;]*?)\);/s', $pnKaynak, $mm, PREG_SET_ORDER)) {
    foreach ($mm as $g) if (substr_count($g[2], ',') < 2) $adsiz[] = $g[1];
}
olc('rozet çağrısı: ' . count($mm ?? []) . ' tane');
den('her rozet çağrısı sayının adını ve sekmesini taşıyor', $adsiz === [], implode(' | ', array_unique($adsiz)));

/* f) GİZLİ SEKME ADRESTEN AÇILAMAZ. İletiler bölümü sunucuda yalnız
   editöre basılır ama düğme herkeste basılıdır; ölçüt "düğme var mı"
   olsaydı #iletiler yazan bir adres yetkisiz birinde bütün bölümleri
   kapatıp boş sayfa bırakırdı. */
den('sekAc gizli sekmeyi açmıyor',
    (bool)preg_match('/function sekAc\(ad\).{0,900}!b\.classList\.contains\(\x27gizli\x27\)/su', $pnKaynak));
/* g) Sekme değiştiren dinleyici yalnız [data-sek] taşıyan düğmelere
   bağlanır: aynı '.pn-sk' sınıfını İleti süzgeci de taşıyor ve
   eskisi "Bekleyen" süzgecine basan kişiyi Özet sekmesine atıyordu. */
den('sekme dinleyicisi süzgeç düğmelerini kapsamıyor',
    substr_count($pnKaynak, "querySelectorAll('.pn-sk')") === 0
    && substr_count($pnKaynak, "querySelectorAll('.pn-sk[data-sek]')") >= 2);

/* h) Kapalı kapak satırın tamamını almaz. Ölçüldü: 1440 pikselde iki
   kapaklı kart alt alta iki tam satır tutuyordu; kapalıyken yan yana
   geliyorlar. Kural özgüllük yüzünden iki kez yazıldı, ölçüt onu da
   kapsıyor. */
den('kapalı kapaklı kart yarım sütunda duruyor',
    str_contains($pnKaynak, '.pn-pnl.acik > .pn-kart.pn-katla:not([open]){grid-column:span 6}'));
den('  açılınca satırın tamamını alıyor',
    str_contains($pnKaynak, '.pn-pnl.acik > .pn-kart.pn-katla[open]{grid-column:1/-1}'));

/* i) SEKME, SEKME GİBİ GÖRÜNÜYOR. Kurul bildirimi: "özet, çalışmalarım
   kısımları anlaşılmıyor; sekme gibi göster." Kural TEK YERDEDİR
   (k/kutadgu.css .sek-bar) ve panel de onu kullanır; ayrı bir sekme
   çubuğu yazılsaydı "bunun gibi olan her yer" birlikte düzelmezdi. */
$css = (string)@file_get_contents($KOD . '/k/kutadgu.css');
den('sekme çubuğu tek kaynaktan geliyor (.sek-bar)',
    str_contains($cikti, 'class="sek-bar'));
den('  her sekmenin kendi yüzeyi var', (bool)preg_match('/\.sek-bar button\{[^}]*background:var\(--yuzey-2\)/s', $css));
den('  seçili sekme kâğıt beyazına çıkıyor',
    (bool)preg_match('/aria-selected="true"\][^{]*\{[^}]*background:var\(--yuzey\)/s', $css));
den('  ve üstünde altın kenar taşıyor', (bool)preg_match('/inset 0 3px 0 var\(--kut\)/', $css));

/* =====================================================================
   2b. ÇALIŞMA ROZETİ AŞAMADAN GELİYOR

   ÖLÇÜLEN KUSUR — 19 Ağustos 2026. Panel, "Çalışmalarım" ve "Listem"
   satırlarındaki rozeti KENDİSİ üretiyordu:

       w.tur === 'hakemli' ? 'Hakemli' : 'Hakemsiz'

   Bu iki şeyi birden yanlış söyler. Tek raporu bile gelmemiş bir
   çalışmaya "Hakemli" der (makale sayfasında bu kusur çoktan
   düzeltilmişti, panelde kalmıştı); iki hakemin adıyla reddettiği bir
   çalışmaya da "Hakemsiz" der, çünkü ret sonrası 'tur' alanı 'yazi'ya
   döner. Aynı çalışma iki ekranda iki başka şey oluyordu.

   Metin artık sunucudan, makale sayfasıyla AYNI kaynaktan
   (tg_asama_metni) gelir.
   ===================================================================== */
echo "\n== 2b. Çalışma rozeti aşamadan geliyor ==\n";
$pnl = (string)@file_get_contents($KOD . '/panel.php');
$uc  = (string)@file_get_contents($KOD . '/api/index.php');
den("panel rozeti 'tur' alanından ÜRETMİYOR",
    !preg_match("/tur\s*===\s*'hakemli'\s*\?\s*\(EN\?'Peer reviewed'/", $pnl));
den('  rozet sunucudan gelen aşama adını basıyor', str_contains($pnl, 'w.asama_ad'));
den('  listem etiketi de aşamadan geliyor', str_contains($pnl, 'x.asama_ad'));
den('/panel/ozet aşama adını iki dilde yolluyor',
    str_contains($uc, "'asama_ad'    => tg_asama_metni") && str_contains($uc, "'asama_ad_en' => tg_asama_metni"));
den('  sayaçta reddedildi basamağı var', str_contains($uc, "'asama_reddedildi' => 0"));
/* Basamağın adı gerçekten üretilebiliyor mu: dizge tek kaynakta
   tanımlıysa boş dönmemeli. */
den('  reddedildi basamağının kısa adı var',
    tg_asama_metni('reddedildi', false, true) === 'Ret'
    && tg_asama_metni('reddedildi', true, true) === 'Rejected');

/* =====================================================================
   3. SAYFA AYAKTA
   ===================================================================== */
echo "\n== 3. Sayfa ayakta ==\n";
$ctx = stream_context_create(['http' => ['method' => 'GET', 'ignore_errors' => true, 'timeout' => 30,
    'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.9']]);
$g = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/panel.php?lang=tr', false, $ctx);
$kod = 0;
foreach (($http_response_header ?? []) as $s2) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s2, $mm)) $kod = (int)$mm[1];
den('panel 200 dönüyor', $kod === 200, (string)$kod);
den('  gövde </html> ile bitiyor', str_ends_with(rtrim($g), '</html>'));
den('  panel pano ölçüsünde açılıyor', (bool)preg_match('#data-olcu="pano"#', $g));

$yeni = (string)@file_get_contents($LOG, false, null, $logOnce);
den('sunucu uyarısı yok', preg_match_all('/warning|deprecated|notice|fatal/i', $yeni) === 0);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
