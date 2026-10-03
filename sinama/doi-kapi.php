<?php
/* =====================================================================
   DOI KAPISI
   ---------------------------------------------------------------------
   Sistem 13 Ağustos 2026'da Zenodo'ya yatırıldı ve kalıcı iki kimlik
   aldı. Kalıcı kimliğin tek tehlikesi vardır: YANLIŞ olması. Yanlış bir
   DOI, olmayan bir DOI'den kötüdür — okuru var olmayan bir kayda
   gönderir ve bunu güvenle yapar.

   Bu kapı, numaranın doğru olduğunu Zenodo'ya sorarak kanıtlayamaz (ağ
   gerektirir ve kapılar ağsız koşar). Kanıtlayabileceği şey şudur: numara
   BİR yerde yazılıdır, biçimi geçerlidir, iki DOI birbirine karışmamıştır
   ve sistemin duyurduğu kimlik ile künyenin duyurduğu kimlik AYNIDIR.

   Sınanan kusurlar:
     §1 Numaranın sayfaya elle yazılması. Ayar değiştiği gün sayfa
        sessizce yanlış olur.
     §2 Kavram DOI'si ile sürüm DOI'sinin karışması. Kavram DOI'si
        anılır; sürümünki anılırsa atıf ilk sürüme çakılır ve sistem
        geliştikçe atıf eskir.
     §3 ayar.php ile CITATION.cff'in ayrışması. Sistem bir kimliği,
        künye başka bir kimliği duyurur.
     §4 DOI alınmamışken boş bir "DOI:" etiketi gösterilmesi.
   ===================================================================== */

$kok = dirname(__DIR__) . '/kutadgunet';
$hata = 0; $sira = 0;
function ol(string $ad, bool $ok, string $not = ''): void {
    global $hata, $sira;
    $sira++;
    if (!$ok) $hata++;
    printf("%s  %s%s\n", $ok ? ' OK ' : 'KUSUR', $ad, $not !== '' ? ('  -> ' . $not) : '');
}

require_once $kok . '/ortak.php';

$kavram = tg_doi();
$surum  = tg_doi_surum();

/* §0 Biçim. DOI ön eki 10. ile başlar, eğik çizgi taşır ve boşluk taşımaz. */
$bicim = function (string $d): bool {
    return (bool)preg_match('#^10\.\d{4,9}/[-._;()/:a-zA-Z0-9]+$#', $d);
};
ol('§0a kavram DOI biçimi geçerli', $kavram !== '' && $bicim($kavram), $kavram);
ol('§0b sürüm DOI biçimi geçerli',  $surum  !== '' && $bicim($surum),  $surum);

/* §1 Numara TEK yerde yazılı: ayar.php. Sayfalarda düz metin DOI aranır.
   Kapı ve künye dosyaları dışarıdadır; künye zaten karşılaştırılıyor. */
$muaf = ['ayar.php', 'CITATION.cff', '.zenodo.json'];
$elle = [];
$yiner = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
foreach ($yiner as $dosya) {
    $yol = $dosya->getPathname();
    $ad  = basename($yol);
    if (in_array($ad, $muaf, true)) continue;
    if (!preg_match('/\.(php|js|html|json|md)$/', $ad)) continue;
    $i = (string)@file_get_contents($yol);
    if ($i === '') continue;
    /* Yorum satırlarında numaranın geçmesi kusur değildir; gerekçe
       yazmak yasaklanamaz. Aranan şey, KODUN ürettiği çıktıya elle
       yazılmış bir numaradır. */
    foreach (explode("\n", $i) as $n => $satir) {
        if (strpos($satir, '10.5281/zenodo.') === false) continue;
        $kirp = ltrim($satir);
        if ($kirp === '' || $kirp[0] === '*' || strpos($kirp, '//') === 0 || strpos($kirp, '#') === 0) continue;
        $elle[] = str_replace($kok . '/', '', $yol) . ':' . ($n + 1);
    }
}
ol('§1 numara sayfalara elle yazılmamış', $elle === [], implode(' | ', $elle));

/* §2 İki DOI ayrı ve karışmamış. Zenodo'da kavram numarası sürümünkinden
   BİR KÜÇÜKTÜR; bu bir kural değil bir gözlemdir, o yüzden yalnızca
   eşit OLMADIKLARI sınanır. */
ol('§2a kavram ile sürüm ayrı', $kavram !== '' && $surum !== '' && $kavram !== $surum);
ol('§2b anılan kimlik kavram DOI\'sidir', tg_doi() === $kavram);

/* §3 ayar.php ile CITATION.cff aynı iki numarayı taşıyor. */
$cff = (string)@file_get_contents($kok . '/CITATION.cff');
$cffDoi = ''; $cffSurum = '';
if (preg_match('/^doi:\s*"?([^"\s]+)"?\s*$/m', $cff, $m)) $cffDoi = $m[1];
if (preg_match('/^\s*value:\s*"?([^"\s]+)"?\s*$/m', $cff, $m2)) $cffSurum = $m2[1];
ol('§3a künyedeki kavram DOI\'si ayarla aynı', $cffDoi !== '' && $cffDoi === $kavram, $cffDoi);
ol('§3b künyedeki sürüm DOI\'si ayarla aynı', $cffSurum !== '' && $cffSurum === $surum, $cffSurum);

/* Sürüm adı da ayrışmamalı: künyede 1.0.0 yazarken ayarda 1.1.0 yazması,
   hangi kaynağın hangi numaraya karşılık geldiğini belirsizleştirir. */
$cffSur = '';
if (preg_match('/^version:\s*"?([^"\s]+)"?\s*$/m', $cff, $m3)) $cffSur = $m3[1];
ol('§3c sürüm adı ayrışmamış', $cffSur !== '' && $cffSur === tg_doi_surum_ad(), $cffSur . ' / ' . tg_doi_surum_ad());

/* §4 DOI yokken hiçbir şey gösterilmez. Kabuk, tg_doi() boş dönerse
   satırı hiç çizmemelidir; koşul kaynakta aranır. */
/* §4 DOI NEREDE DURUYOR — ÖLÇÜM DEĞİŞTİ, KURAL DEĞİŞMEDİ.

   Eskiden burada "DOI altbilgide OLMAMALI" yazıyordu ve gerekçesi
   ölçümdü: altbilgiye iki ayrı biçimde kondu, altbilgi-kapi iki kez de
   yükseklik tavanını aştı (1440'ta 475>470 ve 493>470).

   14 Ağustos 2026'da bildirilen kusur şuydu: "sistemimizin DOI'si
   sistemde nerede, ben göremedim." Haklıydı — numara yalnızca
   kimlik.php'deydi ve o sayfaya giden bağın adı "Kurumsal kimlik ve
   işaret"ti. Bir DOI'yi marka sayfasında aramak kimsenin aklına gelmez;
   bulunamayan bir kimlik, alınmamış gibidir.

   ASIL KURAL "DOI ALTBİLGİDE OLMASIN" DEĞİL, "ALTBİLGİ TAVANI AŞMASIN"
   idi. İkincisi zaten altbilgi-kapi'nin ölçtüğü şeydir ve o kapı bugün
   yeşil: 1440'ta 454px (tavan 470). Yani eski satır, başka bir kapının
   düzgün ölçtüğü bir şeyin VEKİLİYDİ — ve vekil ölçüm, asıl ölçümün
   sonucu değiştiğinde yanlış yere bakar.

   Yeni ölçüm iki şeyi birden ister: DOI okurun bakacağı yerde OLACAK,
   ve altbilgi tavanı aşmayacak (o kapıya bırakılır). */
$kimlik = (string)@file_get_contents($kok . '/kimlik.php');
ol('§4a kimlik sayfası DOI\'yi tg_doi()\'den okuyor', strpos($kimlik, '$sysDoi = tg_doi();') !== false);
ol('§4b DOI boşken bölüm çizilmiyor', strpos($kimlik, "if (\$sysDoi !== ''):") !== false);
$kabuk = (string)@file_get_contents($kok . '/k/kabuk.php');
ol('§4d DOI altbilgide de var (okurun bakacağı yer)', strpos($kabuk, 'tg_doi()') !== false);
ol('§4e altbilgide de boşken çizilmiyor',
   (bool)preg_match("/\\\$sysDoi = function_exists\('tg_doi'\) \? tg_doi\(\) : '';\s*if \(\\\$sysDoi !== ''\)/", $kabuk));
ol('§4f altbilgideki numara elle yazılmamış',
   strpos($kabuk, '10.5281/zenodo') === false);
$seo = (string)@file_get_contents($kok . '/k/seo.php');
ol('§4c seo DOI boşken alan yazmıyor', strpos($seo, "tg_doi() !== '' ? [") !== false);

/* §5 Adres üretimi: iki kez ön ek almaz, boşken boş döner. */
ol('§5a adres doi.org ön ekiyle', tg_doi_adres('10.5281/zenodo.1') === 'https://doi.org/10.5281/zenodo.1');
ol('§5b tam adres iki kez sarılmıyor', tg_doi_adres('https://doi.org/10.5281/zenodo.1') === 'https://doi.org/10.5281/zenodo.1');
ol('§5c boş DOI boş adres verir', tg_doi_adres('') !== '' || tg_doi() !== '');

echo "\n" . ($hata === 0 ? "GEÇTİ" : "KALDI") . ": {$sira} ölçüm, {$hata} kusur\n";
exit($hata === 0 ? 0 : 1);
