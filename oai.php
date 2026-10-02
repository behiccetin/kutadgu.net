<?php
/* =====================================================================
   KUTADGU - OAI-PMH 2.0 toplayıcı arayüzü / Harvesting interface
   Akademik dizinler (DOAJ, BASE, OpenAIRE, CORE, OpenDOAR ve benzeri)
   içerik toplarken bu adresi kullanır. Google Scholar sayfadaki
   Highwire etiketlerini okur; dizinler ise buradan toplar.
   Adres:  /oai?verb=Identify
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/seo.php';

header('Content-Type: text/xml; charset=UTF-8');
header('Cache-Control: public, max-age=1800');

$kok   = tg_kok();
$taban = $kok . '/oai';
$simdi = gmdate('Y-m-d\TH:i:s\Z');
$onek  = 'oai:' . preg_replace('#^https?://#', '', $kok) . ':';

function x($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8'); }

$verb   = (string)($_GET['verb'] ?? '');
$kimlik = (string)($_GET['identifier'] ?? '');
$onekF  = (string)($_GET['metadataPrefix'] ?? '');
$from   = (string)($_GET['from'] ?? '');
$until  = (string)($_GET['until'] ?? '');

/* İstek satırı: her yanıtta yinelenir */
$istekOz = [];
foreach (['verb', 'identifier', 'metadataPrefix', 'from', 'until', 'set'] as $k) {
    if (isset($_GET[$k]) && $_GET[$k] !== '') $istekOz[] = $k . '="' . x((string)$_GET[$k]) . '"';
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<OAI-PMH xmlns="http://www.openarchives.org/OAI/2.0/"' . "\n";
echo '         xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
echo '         xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd">' . "\n";
echo '  <responseDate>' . $simdi . '</responseDate>' . "\n";
echo '  <request' . ($istekOz ? ' ' . implode(' ', $istekOz) : '') . '>' . x($taban) . '</request>' . "\n";

/* HATA METNİ İKİ DİLDE YAZILIR.

   Bu metin bir okura değil, bir TOPLAYICI RAPORUNA düşer: OpenAIRE'in
   ya da BASE'in doğrulayıcısı takıldığında raporun içine bu cümleyi
   koyar ve o raporu okuyan kişi çoğu zaman burada oturan biri değil,
   o kurumda çalışan biridir. "Yalnızca oai_dc desteklenir." cümlesi
   ona hiçbir şey söylemez. Protokol bir dil şartı koymuyor; şart
   koymaması, yalnız Türkçe yazmayı doğru yapmıyor.

   Küme adları (setName, setDescription) zaten iki dilliydi; hata
   metinleri ile depo adı tek dilde kalmıştı, yani aynı yanıtın içinde
   iki ayrı ölçü vardı. */
function hata(string $kod, string $mesaj, string $mesajEn = ''): void {
    $m = $mesajEn !== '' ? ($mesaj . ' / ' . $mesajEn) : $mesaj;
    echo '  <error code="' . x($kod) . '">' . x($m) . '</error>' . "\n";
    echo '</OAI-PMH>' . "\n";
    exit;
}

/* Yayımlanabilir kayıtlar: slug'ı olan her çalışma */
$yazilar = [];
foreach (k_yazilar() as $y) {
    $slug = trim((string)($y['slug'] ?? ''));
    if ($slug === '') continue;
    $yazilar[$slug] = $y;
}

/* DAMGA, BİLDİRİLEN İNCELİKLE AYNI OLMALI.

   Identify yanıtı <granularity>YYYY-MM-DD</granularity> diyordu ama
   bütün damgalar YYYY-MM-DDT00:00:00Z biçimindeydi. Protokol bunu
   açıkça yasaklar: bildirilen incelik neyse damgalar da o biçimde
   olmalıdır ve doğrulayıcılar ilk baktıkları yerlerden biri burasıdır;
   yani OpenAIRE ya da BASE kaydı bu satırda takılırdı.

   Hangisi düzeltilecekti: incelik mi damga mı? Kaydın elinde YALNIZCA
   GÜN var ('tarih' bir gündür, saati yoktur). Saniye inceliği bildirip
   hepsine 00:00:00 yazmak, olmayan bir kesinliği bildirmek olurdu.
   Bu yüzden damga güne indirildi, bildirim değil. */
function oai_tarih(array $y): string {
    $t = substr((string)($y['tarih'] ?? ''), 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? $t : gmdate('Y-m-d');
}

/* ---------------------------------------------------------------------
   Bir çalışmanın düştüğü setler.

   'tur' alanı çalışmanın hangi YOLDA olduğunu söyler, o yolun neresinde
   olduğunu değil: yazar çalışmasını hakemliğe açtığı anda tur='hakemli'
   yazılır ve tek bir rapor bile gelmemiş olabilir. Böyle bir çalışmayı
   "hakem değerlendirmesinden geçmiş" setine koymak, dizinlere ve DOAJ'a
   verilmiş yanlış bir beyandır. Bu yüzden set üyeliği rapor gelmiş
   olmasına bağlıdır ve ölçüt tek yerden, ortak.php'den okunur.

   Bu işlev hem kayıt başlığını hem ListIdentifiers başlığını besler; set
   listesi ile içeriğin çelişmemesi böyle güvence altına alınır.
   --------------------------------------------------------------------- */
function oai_setler(array $y): array {
    /* 'openaire' seti OpenAIRE'in ve DRIVER geleneğinden gelen
       toplayıcıların aradığı addır: doğrulayıcı "hangi seti
       toplayalım" diye sorar ve bu ada bakar. Set BÜTÜN kayıtları
       taşır, çünkü buradaki her kayıt OpenAIRE'in istediği alanları
       zaten taşıyor: eu-repo tür ve sürüm sözlüğü, openAccess hakkı,
       ISO 639-3 dil kodu, kalıcı kimlik ve iniş adresi. Ayrı bir
       süzgeç konsaydı, süzülen kayıtlar için "neden dışarıda" sorusunun
       yazılı bir cevabı olması gerekirdi; yok, çünkü hepsi uyuyor. */
    $s = ['kutadgu', 'openaire'];
    if (tg_hakemden_gecti($y)) $s[] = 'hakemli';
    elseif (tg_hakem_asamasi($y) === 'aranan') $s[] = 'aranan';
    return $s;
}

/* Toplayıcının sorabileceği setler. Süzme de, ListSets de buradan
   beslenir; birine eklenip ötekine eklenmeyen bir set olamaz. */
function oai_set_var(string $set): bool {
    return in_array($set, ['kutadgu', 'openaire', 'hakemli', 'aranan'], true);
}

function oai_kayit(array $y, string $slug, string $onek, string $kok): void {
    $url   = $kok . tg_yazi_yolu($y);
    $silik = tg_geri_cekildi($y);
    echo '  <record>' . "\n";
    echo '    <header' . ($silik ? ' status="deleted"' : '') . '>' . "\n";
    echo '      <identifier>' . x($onek . $slug) . '</identifier>' . "\n";
    echo '      <datestamp>' . x(oai_tarih($y)) . '</datestamp>' . "\n";
    foreach (oai_setler($y) as $s) echo '      <setSpec>' . x($s) . '</setSpec>' . "\n";
    echo '    </header>' . "\n";
    if ($silik) { echo '  </record>' . "\n"; return; }

    echo '    <metadata>' . "\n";
    echo '      <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"' . "\n";
    echo '                 xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n";
    echo '                 xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
    echo '                 xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . "\n";

    echo '        <dc:title>' . x(trim(strip_tags((string)($y['baslik'] ?? '')))) . '</dc:title>' . "\n";
    /* =================================================================
       DİZİNE YALNIZ YAYIN BİLDİRİLİR
       -----------------------------------------------------------------
       Bu blok dışarıya verilen beyandır ve GERİ ALINAMAZ: bir kez
       toplanan üstveri başka kataloglarda yaşamayı sürdürür. Onaysız
       bir makine çevirisini "bu çalışmanın İngilizce sürümü vardır"
       diye bildirmek, yapılmamış bir işi yapılmış göstermektir; aynı
       gerekçeyle 'preprint' ile 'article' de burada ayrılıyor.
       Ölçüt tek yerdedir: ortak.php, tg_ceviri_yayin_mi. Okuma yardımı
       sayılan çeviri sayfada durur, dizine gitmez. */
    $cevYayin = [];
    foreach (tg_yazi_ceviriler($y) as $dk => $c) { if (tg_ceviri_yayin_mi($c)) $cevYayin[$dk] = $c; }
    foreach ($cevYayin as $dk => $c) {
        $cb = trim(strip_tags((string)($c['baslik'] ?? '')));
        if ($cb !== '') echo '        <dc:title xml:lang="' . x($dk) . '">' . x($cb) . '</dc:title>' . "\n";
    }
    $basEn = isset($cevYayin['en']) ? trim(strip_tags((string)($cevYayin['en']['baslik'] ?? ''))) : '';

    foreach (sq_yazarlar($y) as $a) echo '        <dc:creator>' . x(sq_yalin($a['ad'])) . '</dc:creator>' . "\n";

    $anah = trim(tg_metin($y['anahtar'] ?? ''));
    if ($anah !== '') {
        foreach (preg_split('/[;,]/u', $anah) as $k) {
            $k = trim($k); if ($k !== '') echo '        <dc:subject>' . x($k) . '</dc:subject>' . "\n";
        }
    }
    $ozet = trim(strip_tags((string)($y['ozet'] ?? '')));
    if ($ozet !== '') echo '        <dc:description>' . x(mb_substr($ozet, 0, 4000, 'UTF-8')) . '</dc:description>' . "\n";
    foreach ($cevYayin as $dk => $c) {
        $co = trim(strip_tags((string)($c['ozet'] ?? '')));
        if ($co !== '') echo '        <dc:description xml:lang="' . x($dk) . '">' . x(mb_substr($co, 0, 4000, 'UTF-8')) . '</dc:description>' . "\n";
    }

    echo '        <dc:publisher>' . x((string)tg_ayar('marka', 'Kutadgu')) . '</dc:publisher>' . "\n";
    echo '        <dc:date>' . x(substr((string)($y['tarih'] ?? ''), 0, 10)) . '</dc:date>' . "\n";
    /* Tür ve sürüm beyanı.

       Bu iki satır dışarıya, dizinlere ve DOAJ'a verilen beyandır ve
       geri alınamaz: bir kez toplanan üstveri başka kataloglarda yaşar.
       Ölçüt 'tur' olamaz, çünkü 'tur' yalnızca yolun adıdır. Hakemliğe
       açılmış ama tek bir raporu bile gelmemiş bir çalışmayı 'article'
       ve 'publishedVersion' diye bildirmek, yapılmamış bir işi yapılmış
       göstermektir. Rapor gelene kadar doğru olan 'preprint' ve
       'submittedVersion'dır; OpenAIRE'in bu durumda beklediği de budur.
       Söz verilemeyecek şey söylenmez. */
    $gecti = tg_hakemden_gecti($y);
    echo '        <dc:type>info:eu-repo/semantics/' . ($gecti ? 'article' : 'preprint') . '</dc:type>' . "\n";
    echo '        <dc:type>info:eu-repo/semantics/' . ($gecti ? 'publishedVersion' : 'submittedVersion') . '</dc:type>' . "\n";
    echo '        <dc:type>Text</dc:type>' . "\n";
    echo '        <dc:format>text/html</dc:format>' . "\n";
    echo '        <dc:identifier>' . x($url) . '</dc:identifier>' . "\n";
    $doi = trim((string)($y['doi'] ?? ''));
    if ($doi !== '') echo '        <dc:identifier>https://doi.org/' . x($doi) . '</dc:identifier>' . "\n";
    $kod = trim((string)($y['bcid'] ?? ''));
    if ($kod !== '') echo '        <dc:identifier>' . x($kok . '/' . trim((string)tg_ayar('tamga_yol', 'tamga'), '/') . '/' . $kod) . '</dc:identifier>' . "\n";
    /* Kayıt dili. Çalışmanın kendi dili esastır; İngilizce bir sürüm de
       varsa ikinci bir dil olarak bildirilir. Eski kayıtlarda dil alanı
       yoktur; onlar Türkçe yazılmıştı ve öyle bildirilir. */
    $dilKod = tg_dil_iso3(tg_yazi_dili($y));
    if ($dilKod === '') $dilKod = 'tur';
    $dilListe = [$dilKod];
    foreach (array_keys($cevYayin) as $dk) {
        $k3 = tg_dil_iso3($dk);
        if ($k3 !== '' && !in_array($k3, $dilListe, true)) $dilListe[] = $k3;
    }
    foreach ($dilListe as $dk) echo '        <dc:language>' . x($dk) . '</dc:language>' . "\n";
    echo '        <dc:rights>info:eu-repo/semantics/openAccess</dc:rights>' . "\n";
    echo '        <dc:rights>' . x((string)tg_ayar('lisans_url', '')) . '</dc:rights>' . "\n";
    echo '      </oai_dc:dc>' . "\n";
    echo '    </metadata>' . "\n";
    echo '  </record>' . "\n";
}

switch ($verb) {

    case 'Identify':
        $ilk = '2026-01-01';
        foreach ($yazilar as $y) { $t = oai_tarih($y); if ($t < $ilk) $ilk = $t; }
        echo '  <Identify>' . "\n";
        /* DEPO ADI İKİ DİLDE.

           Bu dize, toplayıcının her kaydınızın yanında KAYNAK ETİKETİ
           olarak gösterdiği şeydir: BASE'de, OpenAIRE'de, OpenDOAR'da
           ve CORE'da arama yapan biri çalışmayı bu adla görür. Yalnız
           Türkçe bırakıldığında, Lizbon'da ya da Delhi'de arama yapan
           birinin gördüğü etiket okuyamadığı bir dizedir.

           ŞİMDİ DEĞİŞTİRİLMESİNİN SEBEBİ ZAMANLAMADIR. Depo adı bir
           kez toplandıktan sonra kataloglarda yaşamayı sürdürür ve
           sonradan değiştirmek her katalogda ayrı bir düzeltme demektir.
           Kayıt başvuruları henüz yapılmadı; değiştirilecekse tam
           şimdi değiştirilir. */
        $depoAd = (string)tg_ayar('marka', 'Kutadgu');
        $altTr  = (string)tg_ayar('marka_alt', '');
        $altEn  = (string)tg_ayar('marka_alt_en', '');
        $ad = $depoAd;
        if ($altTr !== '') $ad .= ' · ' . $altTr;
        if ($altEn !== '' && $altEn !== $altTr) $ad .= ' / ' . $altEn;
        echo '    <repositoryName>' . x($ad) . '</repositoryName>' . "\n";
        echo '    <baseURL>' . x($taban) . '</baseURL>' . "\n";
        echo '    <protocolVersion>2.0</protocolVersion>' . "\n";
        /* Adres tek yerden gelir: ayar.php'deki 'iletisim_eposta'. Buraya
           yazılı bir yedek konmaz; yedek konsaydı bu sistemi kuran
           herkes, farkında olmadan başkasının adresini yayımlardı.
           Ayar boşsa alan hiç basılmaz: eksik bir yanıt, yanlış bir
           adres yayımlamaktan iyidir. */
        $yonetimEposta = trim((string)tg_ayar('iletisim_eposta', ''));
        if ($yonetimEposta !== '') {
            echo '    <adminEmail>' . x($yonetimEposta) . '</adminEmail>' . "\n";
        }
        echo '    <earliestDatestamp>' . x($ilk) . '</earliestDatestamp>' . "\n";
        echo '    <deletedRecord>persistent</deletedRecord>' . "\n";
        echo '    <granularity>YYYY-MM-DD</granularity>' . "\n";
        echo '    <description>' . "\n";
        echo '      <oai-identifier xmlns="http://www.openarchives.org/OAI/2.0/oai-identifier"' . "\n";
        echo '                      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        echo '                      xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai-identifier http://www.openarchives.org/OAI/2.0/oai-identifier.xsd">' . "\n";
        echo '        <scheme>oai</scheme>' . "\n";
        echo '        <repositoryIdentifier>' . x(preg_replace('#^https?://#', '', $kok)) . '</repositoryIdentifier>' . "\n";
        echo '        <delimiter>:</delimiter>' . "\n";
        echo '        <sampleIdentifier>' . x($onek . (array_key_first($yazilar) ?: 'ornek')) . '</sampleIdentifier>' . "\n";
        echo '      </oai-identifier>' . "\n";
        echo '    </description>' . "\n";
        echo '  </Identify>' . "\n";
        break;

    case 'ListMetadataFormats':
        echo '  <ListMetadataFormats>' . "\n";
        echo '    <metadataFormat>' . "\n";
        echo '      <metadataPrefix>oai_dc</metadataPrefix>' . "\n";
        echo '      <schema>http://www.openarchives.org/OAI/2.0/oai_dc.xsd</schema>' . "\n";
        echo '      <metadataNamespace>http://www.openarchives.org/OAI/2.0/oai_dc/</metadataNamespace>' . "\n";
        echo '    </metadataFormat>' . "\n";
        echo '  </ListMetadataFormats>' . "\n";
        break;

    case 'ListSets':
        /* setName metni olduğu gibi kalıyor: "Hakem değerlendirmesinden
           geçmiş çalışmalar" bugüne kadar yanlıştı, çünkü sete rapor
           gelmemiş çalışmalar da düşüyordu. Ölçüt düzeltildiği için ad
           artık setin içindekini doğru anlatıyor; değiştirilmesi gereken
           ad değil, ölçüttü. Toplayıcılar setSpec'e bakar, ad okur
           içindir; bu yüzden ada İngilizcesi de eklendi ve ölçüt
           setDescription'da açıkça yazıldı: dizin, neyin nasıl sayıldığını
           tahmin etmek zorunda kalmasın. */
        $setAciklama = function (string $spec, string $ad, string $tr, string $en): void {
            echo '    <set>' . "\n";
            echo '      <setSpec>' . x($spec) . '</setSpec>' . "\n";
            echo '      <setName>' . x($ad) . '</setName>' . "\n";
            echo '      <setDescription>' . "\n";
            echo '        <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"' . "\n";
            echo '                   xmlns:dc="http://purl.org/dc/elements/1.1/"' . "\n";
            echo '                   xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
            echo '                   xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">' . "\n";
            echo '          <dc:description>' . x($tr) . '</dc:description>' . "\n";
            echo '          <dc:description xml:lang="en">' . x($en) . '</dc:description>' . "\n";
            echo '        </oai_dc:dc>' . "\n";
            echo '      </setDescription>' . "\n";
            echo '    </set>' . "\n";
        };
        echo '  <ListSets>' . "\n";
        $setAciklama('kutadgu', 'Bütün çalışmalar / All works',
            'Arşivdeki bütün çalışmalar.',
            'All works in the archive.');
        $setAciklama('openaire', 'OpenAIRE uyumlu kayıtlar / OpenAIRE compliant records',
            'Arşivin tamamı. Kayıtlar OpenAIRE yönergesinin istediği alanları taşır: '
            . 'eu-repo tür ve sürüm sözlüğü, açık erişim hakkı, üç harfli dil kodu, kalıcı kimlik ve iniş adresi. '
            . 'Bu set "kutadgu" setiyle aynı kayıtları taşır; ayrı adla durmasının sebebi toplayıcıların bu adı aramasıdır.',
            'The whole archive. Records carry the fields the OpenAIRE guidelines ask for: '
            . 'the eu-repo type and version vocabulary, open access rights, a three letter language code, a permanent identifier and a landing page. '
            . 'This set holds the same records as "kutadgu"; it exists under this name because harvesters look for this name.');
        $setAciklama('hakemli', 'Hakem değerlendirmesinden geçmiş çalışmalar / Peer reviewed works',
            'Hakemlik yolundaki ve en az bir tamamlanmış hakem raporu yayımlanmış çalışmalar. Hakemliğe açılmış ama henüz raporu gelmemiş çalışmalar bu sete girmez.',
            'Works on the peer review track for which at least one completed referee report has been published. Works opened to review but with no report yet are not in this set.');
        $setAciklama('aranan', 'Hakem aranan çalışmalar / Works seeking reviewers',
            'Hakemlik yoluna açılmış, ancak henüz tamamlanmış bir hakem raporu bulunmayan çalışmalar. Bunlar ön baskı (preprint) olarak bildirilir.',
            'Works opened to peer review for which no completed report exists yet. These are declared as preprints.');
        echo '  </ListSets>' . "\n";
        break;

    case 'ListIdentifiers':
    case 'ListRecords':
        if ($onekF !== '' && $onekF !== 'oai_dc') hata('cannotDisseminateFormat', 'Yalnızca oai_dc desteklenir.', 'Only oai_dc is supported.');
        if ($onekF === '') hata('badArgument', 'metadataPrefix gereklidir.', 'metadataPrefix is required.');
        $set = (string)($_GET['set'] ?? '');
        $secili = [];
        foreach ($yazilar as $slug => $y) {
            $t = substr(oai_tarih($y), 0, 10);
            if ($from !== '' && $t < substr($from, 0, 10)) continue;
            if ($until !== '' && $t > substr($until, 0, 10)) continue;
            /* Süzme, kayıt başlığındaki setSpec ile aynı ölçütten
               beslenir. Ayrı yazılsalardı biri düzeltilip öteki unutulur
               ve set listesi ile içerik çelişirdi. */
            if ($set !== '' && !oai_set_var($set)) continue;
            if ($set !== '' && $set !== 'kutadgu' && !in_array($set, oai_setler($y), true)) continue;
            $secili[$slug] = $y;
        }
        if (!$secili) hata('noRecordsMatch', 'Ölçütlere uyan kayıt yok.', 'No records match the criteria.');
        if ($verb === 'ListIdentifiers') {
            echo '  <ListIdentifiers>' . "\n";
            foreach ($secili as $slug => $y) {
                echo '    <header' . (tg_geri_cekildi($y) ? ' status="deleted"' : '') . '>' . "\n";
                echo '      <identifier>' . x($onek . $slug) . '</identifier>' . "\n";
                echo '      <datestamp>' . x(oai_tarih($y)) . '</datestamp>' . "\n";
                /* Başlık, kaydın kendisiyle aynı setleri saysın: burada
                   yalnızca "kutadgu" yazmak, hakemli seti sorulduğunda
                   toplayıcıya eksik bilgi vermek olurdu. */
                foreach (oai_setler($y) as $s) echo '      <setSpec>' . x($s) . '</setSpec>' . "\n";
                echo '    </header>' . "\n";
            }
            echo '  </ListIdentifiers>' . "\n";
        } else {
            echo '  <ListRecords>' . "\n";
            foreach ($secili as $slug => $y) oai_kayit($y, $slug, $onek, $kok);
            echo '  </ListRecords>' . "\n";
        }
        break;

    case 'GetRecord':
        if ($onekF !== '' && $onekF !== 'oai_dc') hata('cannotDisseminateFormat', 'Yalnızca oai_dc desteklenir.', 'Only oai_dc is supported.');
        if ($kimlik === '') hata('badArgument', 'identifier gereklidir.', 'identifier is required.');
        $slug = str_starts_with($kimlik, $onek) ? substr($kimlik, strlen($onek)) : $kimlik;
        if (!isset($yazilar[$slug])) hata('idDoesNotExist', 'Böyle bir kayıt yok.', 'No such record exists.');
        echo '  <GetRecord>' . "\n";
        oai_kayit($yazilar[$slug], $slug, $onek, $kok);
        echo '  </GetRecord>' . "\n";
        break;

    case '':
        hata('badVerb', 'verb parametresi gereklidir. Örnek: /oai?verb=Identify', 'The verb parameter is required. Example: /oai?verb=Identify');
        break;

    default:
        hata('badVerb', 'Bilinmeyen verb: ' . $verb, 'Unknown verb: ' . $verb);
}

echo '</OAI-PMH>' . "\n";
