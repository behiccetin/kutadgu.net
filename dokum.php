<?php
/* =====================================================================
   KUTADGU - ARŞİV DÖKÜMÜ SAYFASI VE DOSYA SUNUMU
   Archive dump page and file serving
   ---------------------------------------------------------------------
   Bu sayfa iki iş yapar:
     1. Hangi dökümlerin bulunduğunu, ne kadar yer tuttuklarını, hangi
        SHA-256 özetini taşıdıklarını ve ne zaman üretildiklerini
        gösterir.
     2. İstenen dosyayı olduğu gibi akıtır. Dosyalar burada üretilmez;
        yayım anında ve her gece bir kez üretilip durağan dosya olarak
        konur. İndirme sunucuya iş çıkarmaz.

   Onay kapısı yoktur ve konulamaz. Hiçbir dosya kimlik, kayıt, üyelik,
   onay ya da bedel şartına bağlı değildir. Sıra ve hız sınırı bir
   gecikmedir; bir reddetme değildir. Gerekçe: tüzük taslağı Ek A Madde
   2.5 ve DOAJ'ın okurdan kayıt istememe şartı.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/dokum.php';

/* ---------------------------------------------------------------------
   1. DOSYA SUNUMU
   Sayfa kabuğu yüklenmeden önce biter: indirme yolunda ne şablon
   çalışır ne de arşiv okunur.
   --------------------------------------------------------------------- */
$istenen = trim((string)($_GET['d'] ?? ''));
if ($istenen !== '') {
    if (!dk_ad_gecerli($istenen) || !is_file(dk_yol($istenen))) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=UTF-8');
        exit("Bulunamadi / Not found\n");
    }
    $bilgi = dk_belirte_dosya($istenen);
    $katman = (string)($bilgi['katman'] ?? 'b');
    /* Yol bazında tavan: katman ağırlaştıkça sıklık düşer.
       Pencere bilerek kısa (on dakika) tutuldu. Aynı tavan bir saatlik
       pencereye yazılsaydı sınırı aşan kişi bir saat beklerdi; bir saat
       beklemek pratikte reddedilmekten ayırt edilemez. On dakikalık
       pencerede en uzun bekleme on dakikadır ve bekleyen kişi aynı
       dosyayı hiçbir koşul olmadan alır. */
    $tavan = ['a' => [60, 600], 'b' => [20, 600], 'c' => [5, 600]][$katman] ?? [20, 600];
    $s = dk_hiz_sinir('indir-' . $katman, $tavan[0], $tavan[1]);
    if (!$s['gecer']) {
        /* 429 bir reddetme değildir: ne zaman geleceği yazılıdır ve o
           an gelindiğinde dosya aynı dosyadır. */
        http_response_code(429);
        header('Retry-After: ' . (int)$s['bekle']);
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store');
        exit("Cok sik istek. Bu bir reddetme degildir: " . (int)$s['bekle']
           . " saniye sonra ayni dosyayi kosulsuz indirebilirsiniz.\n"
           . "Too many requests. This is not a refusal: the same file may be downloaded\n"
           . "without any condition in " . (int)$s['bekle'] . " seconds.\n");
    }
    dk_gonder($istenen);
    exit;
}

/* ---------------------------------------------------------------------
   2. BELİRTE (makine okunur durum)
   Aynalar ve betikler için. Küçük bir dosyanın okunmasından ibarettir.
   --------------------------------------------------------------------- */
if (isset($_GET['durum'])) {
    $s = dk_hiz_sinir('durum', 60, 600);
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    if (!$s['gecer']) {
        http_response_code(429);
        header('Retry-After: ' . (int)$s['bekle']);
        echo json_encode(['ok' => false, 'bekle' => (int)$s['bekle'],
                          'not' => 'Gecikme, reddetme degil. A delay, not a refusal.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header('Cache-Control: public, max-age=300');
    $b = dk_belirte();
    $b['bekleyen_istek'] = dk_kuyruk_bekleyen();
    $b['sonraki_paket']  = dk_sonraki_uretim();
    echo json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/* ---------------------------------------------------------------------
   3. SIRAYA GİRME
   Kimlik istenmez. Karşılığında rastgele bir sıra fişi verilir; fiş
   yalnızca "hazır mı" sorusunu sormaya yarar. Fişi olmayan da hazır
   paketi indirir.
   --------------------------------------------------------------------- */
$fis = preg_replace('/[^a-f0-9]/', '', (string)($_GET['fis'] ?? ($_POST['fis'] ?? '')));
$fis = substr((string)$fis, 0, 32);
$siraUyari = '';
if (isset($_POST['sira']) || isset($_GET['sira'])) {
    $s = dk_hiz_sinir('sira', 10, 600);
    if (!$s['gecer']) {
        $siraUyari = (int)$s['bekle'];
    } else {
        $sonuc = dk_sira_al(dk_iz(), $fis);
        $yeniFis = (string)($sonuc['fis'] ?? '');
        /* Dil korunur: k_bag() Türkçede adresi olduğu gibi bırakır,
           öteki dillerde lang= ekler. Yönlendirmede dil düşerse kişi
           sıradaki yerini başka bir dilde okur. */
        $hedef = k_bag('/dokum.php' . ($yeniFis !== '' ? '?fis=' . rawurlencode($yeniFis) : ''));
        if (!headers_sent()) { header('Location: ' . $hedef, true, 303); exit; }
        $fis = $yeniFis;
    }
}

require_once __DIR__ . '/k/parca.php';

/* ---------------------------------------------------------------------
   İLK KURULUM
   Yeni bir sunucuda ya da veri dizini yeni açıldığında henüz hiçbir
   döküm üretilmemiş olur ve sayfa boş görünür. Hiç dosya yokken bir kez
   üretilir. Bu, istek anında üretime dönüş DEĞİLDİR: koşul "hiç dosya
   yok"tur, bir kez sağlanır ve bir daha sağlanmaz. Kilit beklemez, yani
   aynı anda gelen ikinci kişi üretimi ikinci kez başlatmaz.
   --------------------------------------------------------------------- */
if ((dk_belirte()['dosyalar'] ?? []) === [] && !is_file(dk_yol('kutadgu-ustveri.json'))) {
    dk_uret_hafif(true);
}

/* ---------------------------------------------------------------------
   Dosya satırları. Sayfada üç yerde aynı biçimde göründüğü için tek
   yerde yazıldı; üç kez kopyalanmış bir satır üç kez ayrışır.
   --------------------------------------------------------------------- */
function dk_satirlar(array $liste, bool $en): string {
    if (!$liste) {
        return '<div class="kutu"><p>' . (k_c('Henüz üretilmiş dosya yok.', 'No file has been built yet.')) . '</p></div>';
    }
    $c = '<div class="dk-liste">';
    foreach ($liste as $d) {
        $ad = (string)($d['ad'] ?? '');
        if ($ad === '') continue;
        $ozet = (string)($d['ozet'] ?? '');
        $t = (string)($d['uretim'] ?? '');
        $ts = $t !== '' ? strtotime($t) : false;
        $yil = (string)($d['yil'] ?? '');
        $alt = [];
        if ($yil !== '') $alt[] = (k_c('yıl ', 'year ')) . $yil;
        if (isset($d['gorsel_sayisi'])) $alt[] = (int)$d['gorsel_sayisi'] . (k_c(' görsel', ' images'));
        if (isset($d['belge_sayisi']))  $alt[] = (int)$d['belge_sayisi'] . (k_c(' belge', ' documents'));
        $alt[] = (k_c('üretim ', 'built ')) . ($ts ? date(k_c('j.m.Y H:i', 'j M Y, H:i'), $ts) : '-');
        $c .= '<div class="dk-d"><div>'
            . '<b>' . k_esc($ad) . '</b>'
            . '<div class="dk-alt">' . k_esc(implode(' · ', $alt)) . '</div>'
            . '<div class="dk-ozet">sha256 ' . k_esc($ozet) . '</div>'
            . '</div><div><a class="d d-vurgu d-kucuk" href="/dokum.php?d=' . k_esc(rawurlencode($ad)) . '">'
            . (k_c('İndir', 'Download')) . ' (' . k_esc(dk_boy((int)($d['boyut'] ?? 0))) . ')</a></div></div>';
    }
    return $c . '</div>';
}

$en = k_en();
$b  = dk_belirte();
$dosyalar = is_array($b['dosyalar'] ?? null) ? $b['dosyalar'] : [];
$katman = ['a' => [], 'b' => [], 'c' => []];
foreach ($dosyalar as $d) {
    $k = (string)($d['katman'] ?? 'b');
    if (isset($katman[$k])) $katman[$k][] = $d;
}
/* Yıl parçaları yeniden eskiye; tek parça dosya en sonda dursun. */
usort($katman['b'], function ($x, $z) {
    $xy = (string)($x['yil'] ?? ''); $zy = (string)($z['yil'] ?? '');
    if ($xy === '' && $zy !== '') return 1;
    if ($zy === '' && $xy !== '') return -1;
    return strcmp($zy, $xy);
});

$paket   = is_array($b['paket'] ?? null) ? $b['paket'] : [];
$paketVar = $paket !== [] && is_file(dk_yol((string)($paket['ad'] ?? '')));
$durum   = $fis !== '' ? dk_fis_durum($fis) : [];
$bekleyen = dk_kuyruk_bekleyen();
$uretim  = (string)($b['uretim'] ?? '');
$sureMs  = (int)($b['sure_ms'] ?? 0);
$sayi    = (int)($b['calisma_sayisi'] ?? 0);
$toplamBoy = 0;
foreach ($dosyalar as $d) $toplamBoy += (int)($d['boyut'] ?? 0);

$tarihYaz = function (string $iso) use ($en): string {
    $t = $iso !== '' ? strtotime($iso) : false;
    if ($t === false) return '-';
    return date(k_c('j.m.Y H:i', 'j M Y, H:i'), $t);
};

$ekBas = <<<CSS
<style>
/* Sayfaya özel olan yalnızca bu üç şey: katman şeridi, dosya satırı ve
   sıra kutusu. Ötekilerin hepsi dizgeden gelir. */
.dk-blk{margin-top:var(--b-7)}
.dk-blk:first-child{margin-top:0}
.dk-blk h2{scroll-margin-top:90px}
.dk-agir{display:inline-flex;align-items:center;gap:var(--b-1);max-width:var(--olcu-metin-genis);
  font-family:var(--mono);font-size:var(--y-1);color:var(--metin-2)}
.dk-liste{display:grid;gap:var(--b-3);margin-top:var(--b-4)}
.dk-d{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,max-content);
  gap:var(--b-2) var(--b-4);align-items:center;
  border:1px solid var(--cizgi);border-radius:var(--r-3);padding:var(--b-3) var(--b-4);
  background:var(--yuzey)}
.dk-d b{font-weight:600;display:block}
.dk-d .dk-ozet{font-family:var(--mono);font-size:var(--y-1);color:var(--metin-2);
  word-break:break-all;margin-top:var(--b-1)}
.dk-d .dk-alt{font-size:var(--y-2);color:var(--metin-2);margin-top:var(--b-1)}
@media (max-width:560px){.dk-d{grid-template-columns:minmax(0,1fr)}}
.dk-sira{margin-top:var(--b-4)}
.dk-sira form{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;margin-top:var(--b-3)}
.dk-not{font-size:var(--y-3);color:var(--metin-2);border-top:1px solid var(--cizgi);
  padding-top:var(--b-4);margin-top:var(--b-6)}
.dk-kod{font-family:var(--mono);font-size:var(--y-2);background:var(--yuzey-2);
  border-radius:var(--r-2);padding:var(--b-3);overflow-x:auto;white-space:pre;margin-top:var(--b-3)}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Arşiv dökümü', 'Archive dump'),
    'yol'    => '/dokum.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu arşivinin tamamı üç katman hâlinde indirilebilir: üstveri, tam metin ve görsel paketi. Koşulsuz, kayıtsız, ücretsiz.',
        'The whole Kutadgu archive may be downloaded in three layers: metadata, full text and an image package. Without condition, registration or payment.'
    ),
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Kalıcılık', 'Permanence') ?></span>
      <h1><?= k_c('Arşivin dökümü', 'The archive dump') ?></h1>
      <p><?= k_c(
        'Kalıcı bir kimlik vermek, kalıcılığı taahhüt etmektir. Tek bir sunucuda duran arşiv, o sunucu gittiğinde gider. Bu yüzden arşivin tamamı kimseden izin almadan indirilebilir. Kopyayı alan herkes arşivi sürdürebilir.',
        'To give a permanent identifier is to promise permanence. An archive kept on a single server goes when that server goes. The whole archive may therefore be downloaded without anyone\'s permission. Whoever takes a copy may continue the archive.'
      ) ?></p>
    </div>
    <div class="sayfa-olcu">
      <div><b><?= k_esc(k_sayi($sayi)) ?></b><span><?= k_c('çalışma', 'works') ?></span></div>
      <div><b><?= k_esc((string)count($dosyalar)) ?></b><span><?= k_c('durağan dosya', 'static files') ?></span></div>
      <div><b><?= k_esc(dk_boy($toplamBoy)) ?></b><span><?= k_c('toplam', 'in total') ?></span></div>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <div class="dk-blk" id="kosul">
      <h2><?= k_c('İndirmenin koşulu yoktur', 'There is no condition for downloading') ?></h2>
      <div class="kutu kutu-yes">
        <p><?= k_c(
          'Bu sayfadaki hiçbir dosya kimlik, kayıt, üyelik, onay ya da bedel şartına bağlı değildir ve bağlanamaz. Bu bir tercih değil, tüzük taslağının Ek A Madde 2.5 hükmüdür ve hiçbir çoğunlukla daraltılamaz. Aşağıdaki sıra ve hız sınırı erişimi geciktirebilir; hiçbir durumda reddedemez.',
          'No file on this page is, or may be, made conditional on identity, registration, membership, approval or payment. This is not a preference but Article 2.5 of the draft charter, and no majority may narrow it. The queue and the rate limit below may delay access; they may never refuse it.'
        ) ?></p>
        <p><?= k_c(
          'Kural yalnızca burada yazılı da değildir. Ayar dosyasına arşivi onaya bağlayan bir satır yazılırsa sistem o satırı okur okumaz etkisiz kılar; bir uyarı vermez, düzeltir.',
          'The rule is not only written here. If a line making the archive conditional on approval is put into the configuration file, the system disables that line as soon as it reads it. It does not warn; it corrects.'
        ) ?></p>
      </div>
      <p><?= k_c(
        'Döküm istek geldiğinde üretilmez. Yayım anında ve her gece bir kez üretilir, durağan dosya olarak durur. Sebebi şudur: istek anında üretilen bir döküm, arşiv büyüdükçe kötü niyet gerekmeden sistemi yavaşlatır, niyet varsa da doğrudan bir saldırı yoludur. Doğru karşılık erişimi kısmak değil, üretimi istekten ayırmaktır.',
        'The dump is not produced when it is requested. It is built at publication and once every night, and kept as a static file. The reason is this: a dump produced on request slows the system as the archive grows, with no ill will needed, and is a direct line of attack where there is ill will. The right answer is not to narrow access but to separate production from the request.'
      ) ?></p>
      <?php if ($sureMs > 0): ?>
      <p class="dk-agir"><?= k_c('Son üretim süresi', 'Last build took') ?>: <?= k_esc((string)$sureMs) ?> ms<?php if ($uretim !== ''): ?> · <?= k_esc($tarihYaz($uretim)) ?><?php endif; ?></p>
      <?php endif; ?>
    </div>

    <div class="dk-blk" id="ustveri">
      <h2><?= k_c('Katman a · Üstveri', 'Layer a · Metadata') ?></h2>
      <p><?= k_c(
        'Künye, kimlik, yazarlar, anahtar sözcükler, karar özeti ve metin parmak izi. Tam metin, hakem raporlarının gövdesi, oy gerekçeleri ve şerh metinleri bu katmanda yoktur. Küçüktür ve arayanların çoğunun aradığı budur.',
        'Bibliographic record, identifier, authors, keywords, decision summary and text fingerprint. Full text, the body of referee reports, vote justifications and commentary texts are not in this layer. It is small, and it is what most of those who ask are asking for.'
      ) ?></p>
      <?= dk_satirlar($katman['a'], $en) ?>
    </div>

    <div class="dk-blk" id="tam">
      <h2><?= k_c('Katman b · Üstveri ile tam metin', 'Layer b · Metadata with full text') ?></h2>
      <p><?= k_c(
        'Çalışmaların tam metni, hakem raporları, kararlar, atama kayıtları, düzeltme ve geri çekme kayıtları, kurul oylamaları ve yayın sonrası şerhler. Yıllara bölünmüştür: bir yılı olan kişi ötekileri indirmek zorunda değildir. En sondaki tek parça dosya, bugüne kadar arşivi tek adresten alan betikler ve aynalar kırılmasın diye durur.',
        'The full text of the works, referee reports, decisions, assignment records, correction and retraction records, board votes and post publication commentary. It is split by year: whoever has one year need not download the others. The single file at the end is kept so that scripts and mirrors that have been taking the archive from one address do not break.'
      ) ?></p>
      <?= dk_satirlar($katman['b'], $en) ?>
    </div>

    <div class="dk-blk" id="paket">
      <h2><?= k_c('Katman c · Görsel ve belge paketi', 'Layer c · Image and document package') ?></h2>
      <p><?= k_c(
        'Çalışmaların gövdesinde geçen görseller ve hakem raporlarına eklenmiş belgeler, tek bir sıkıştırılmış dosyada. En ağır olan budur ve istek anında üretilmez: kuyruğa alınır ve günde en çok bir kez üretilir. Sıraya girmek için kimlik gerekmez, karşılığında rastgele bir sıra fişi verilir. Fişi olmayan da hazır paketi indirir; fiş bir kapı değil, bir kolaylıktır.',
        'The images that appear in the works and the files attached to referee reports, in a single compressed file. This is the heaviest layer and it is not produced on request: it is queued and built at most once a day. Joining the queue asks for no identity; a random ticket is given in return. Whoever has no ticket downloads the ready package all the same. The ticket is a convenience, not a gate.'
      ) ?></p>

      <?php if ($paketVar): ?>
      <?= dk_satirlar([$paket], $en) ?>
      <?php else: ?>
      <div class="kutu"><p><?= k_c(
        'Şu anda hazır bir paket yok. Aşağıdan sıraya girerseniz bir sonraki üretimde hazırlanır.',
        'There is no ready package at the moment. If you join the queue below it will be built in the next run.'
      ) ?></p></div>
      <?php endif; ?>

      <div class="dk-sira" id="sira">
        <h3><?= k_c('Sıra', 'Queue') ?></h3>
        <?php if ($siraUyari !== ''): ?>
        <div class="kutu kutu-kut"><p><?= k_c(
          'Çok sık istek geldi. Bu bir reddetme değildir: ' . (int)$siraUyari . ' saniye sonra yeniden deneyebilirsiniz.',
          'Requests came too often. This is not a refusal: you may try again in ' . (int)$siraUyari . ' seconds.'
        ) ?></p></div>
        <?php endif; ?>

        <?php if (($durum['durum'] ?? '') === 'sirada'): ?>
        <div class="kutu kutu-lac">
          <p><b><?= k_c('Sıradasınız.', 'You are in the queue.') ?></b>
             <?= k_c('Sıra numaranız', 'Your position') ?>: <?= k_esc((string)(int)($durum['sira'] ?? 0)) ?>
             · <?= k_c('bekleyen', 'waiting') ?>: <?= k_esc((string)(int)($durum['bekleyen'] ?? 0)) ?></p>
          <p><?= k_c('En erken üretim', 'Earliest build') ?>: <?= k_esc($tarihYaz((string)($durum['tahmin'] ?? ''))) ?>.
             <?= k_c('Sıra fişiniz', 'Your ticket') ?>: <code><?= k_esc($fis) ?></code>.
             <?= k_c('Bu adresi saklayın; hazır olduğunda bağlantı burada görünür.',
                     'Keep this address; the link will appear here when it is ready.') ?></p>
          <p><a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag('/dokum.php?fis=' . rawurlencode($fis))) ?>"><?= k_c('Durumu yenile', 'Refresh status') ?></a></p>
        </div>
        <?php elseif (($durum['durum'] ?? '') === 'hazir'): ?>
        <div class="kutu kutu-yes">
          <p><b><?= k_c('Paketiniz hazır.', 'Your package is ready.') ?></b>
             <?= k_c('Sıra fişi', 'Ticket') ?>: <code><?= k_esc($fis) ?></code></p>
          <?php $pd = is_array($durum['paket'] ?? null) ? $durum['paket'] : $paket; ?>
          <?php if ($pd): ?>
          <p><a class="d d-vurgu" href="/dokum.php?d=<?= k_esc(rawurlencode((string)$pd['ad'])) ?>"><?= k_c('Paketi indir', 'Download the package') ?> (<?= k_esc(dk_boy((int)($pd['boyut'] ?? 0))) ?>)</a></p>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <form method="post" action="/dokum.php">
          <input type="hidden" name="sira" value="1">
          <?php if ($fis !== ''): ?><input type="hidden" name="fis" value="<?= k_esc($fis) ?>"><?php endif; ?>
          <button class="d d-ikinci" type="submit"><?= ($durum['durum'] ?? '') === 'sirada'
            ? k_c('Sıradaki yerimi göster', 'Show my position')
            : k_c('Paket için sıraya gir', 'Join the queue for the package') ?></button>
          <span class="dk-agir"><?= k_c('bekleyen istek', 'waiting requests') ?>: <?= k_esc((string)$bekleyen) ?>
            · <?= k_c('en erken üretim', 'earliest build') ?>: <?= k_esc($tarihYaz(dk_sonraki_uretim())) ?></span>
        </form>
        <p class="dk-agir"><?= k_c(
          'Sıraya girmek zorunlu değildir. Hazır bir paket varsa doğrudan indirilir; sıra yalnızca hazır paket yokken ya da eskimişken işe yarar.',
          'Joining the queue is not required. Where a ready package exists it is downloaded directly; the queue is only useful when there is none or it has aged.'
        ) ?></p>
      </div>
    </div>

    <div class="dk-blk" id="dogrulama">
      <h2><?= k_c('Doğrulama', 'Verification') ?></h2>
      <p><?= k_c(
        'Her dosyanın yanında SHA-256 özeti ve üretim tarihi yazılıdır. Aynı değerler makine okunur biçimde de durur. İndirdiğiniz kopyanın buradaki kopyayla aynı olduğunu kendiniz denetleyebilirsiniz; bize güvenmeniz gerekmez.',
        'A SHA-256 digest and a build date are written beside every file. The same values are also kept in machine readable form. You may check for yourself that the copy you downloaded is the same as the copy here; you do not have to take our word for it.'
      ) ?></p>
      <div class="dk-kod">curl -O <?= k_esc(tg_kok()) ?>/dokum.php?d=kutadgu-ustveri.json
sha256sum kutadgu-ustveri.json
curl -s <?= k_esc(tg_kok()) ?>/dokum.php?durum=1 | grep ozet</div>
      <p class="dk-agir"><a href="/dokum.php?durum=1"><?= k_c('Makine okunur belirte (JSON)', 'Machine readable manifest (JSON)') ?></a></p>
    </div>

    <div class="dk-blk" id="surdurme">
      <h2><?= k_c('Arşivi sürdürmek', 'Continuing the archive') ?></h2>
      <p><?= k_c(
        'Bu dosyaları alan herkes arşivi çoğaltabilir ve sürdürebilir. Bunu bir izin olarak değil, bir istek olarak yazıyoruz: kopyalamayı kolaylaştırmak, arşivi korumanın en ucuz ve en sağlam yoludur. Bir ayna kurarsanız bize haber vermek zorunda değilsiniz.',
        'Anyone who takes these files may copy and continue the archive. We write this not as a permission but as a request: making copying easy is the cheapest and most robust way of protecting an archive. If you set up a mirror you are under no obligation to tell us.'
      ) ?></p>
      <p><?= k_c(
        'Dökümde kişisel veri yoktur. E-posta adresleri, erişim anahtarları, hesap kayıtları, doğrulama kodları ve okuyucu adresleri bilerek dışarıda bırakılmıştır. Bilimsel kayıt kişisel veriye ihtiyaç duymaz.',
        'The dump contains no personal data. E mail addresses, access keys, account records, verification codes and reader addresses are deliberately left out. The scholarly record has no need of personal data.'
      ) ?></p>
    </div>

    <p class="dk-not"><?= k_c(
      'Bu sayfadaki dosyalar ' . (string)tg_ayar('lisans', 'CC BY 4.0') . ' ile verilir. Sunucu indirme sırasında hiçbir hesap yapmaz: dosya önceden üretilmiş hâliyle akıtılır, ETag ve Last-Modified başlıkları gönderilir, kısmi indirme (Range) desteklenir. Yarıda kalan bir indirme kaldığı yerden sürdürülebilir.',
      'The files on this page are given under ' . (string)tg_ayar('lisans', 'CC BY 4.0') . '. The server computes nothing while serving them: the file is streamed as it was built, ETag and Last-Modified headers are sent, and partial downloads (Range) are supported. An interrupted download may be resumed where it stopped.'
    ) ?></p>

   </div>
   <?= k_belge_yan([
     ['k' => 'kosul',      'tr' => 'İndirmenin koşulu yoktur', 'en' => 'No condition'],
     ['k' => 'ustveri',    'tr' => 'Katman a · Üstveri',       'en' => 'Layer a · Metadata'],
     ['k' => 'tam',        'tr' => 'Katman b · Tam metin',     'en' => 'Layer b · Full text'],
     ['k' => 'paket',      'tr' => 'Katman c · Görsel paketi', 'en' => 'Layer c · Images'],
     ['k' => 'dogrulama',  'tr' => 'Doğrulama',                'en' => 'Verification'],
     ['k' => 'surdurme',   'tr' => 'Arşivi sürdürmek',         'en' => 'Continuing the archive'],
   ], [
     ['tr' => 'Ne zaman üretilir', 'en' => 'When it is built',
      'ic' => k_c('Bir çalışma yayımlandığında ve her gece bir kez. Görsel paketi günde en çok bir kez. İndirme anında hiçbir şey üretilmez.',
                  'When a work is published, and once every night. The image package at most once a day. Nothing is produced at the moment of download.')],
     ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
      'ic' => '<a href="' . k_esc(k_bag('/istatistik.php')) . '">' . k_c('Arşivin sayıları', 'The numbers of the archive') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/ilkeler.php')) . '">' . k_c('İlkeler', 'Principles') . '</a><br>'
            . '<a href="/oai">' . k_c('OAI-PMH toplayıcı ucu', 'OAI-PMH endpoint') . '</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
