<?php
/* =====================================================================
   KUTADGU - Kurumsal kimlik / Brand and identity
   ---------------------------------------------------------------------
   Kamuya açık kimlik sayfası. İşaretin bütün kullanım biçimleri, altı
   kavram simgesi, renkler ve kullanım kuralları burada durur; dosyalar
   tek tek ya da topluca indirilebilir.

   Dikkat: bu dosya k/kimlik.php ile karıştırılmamalıdır. Oradaki dosya
   kimliğin kaynağıdır (renkler, işaret ve simgeleri üreten işlevler);
   buradaki ise onu gösteren sayfadır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en = k_en();
$r  = kim_renkler();

/* İndirilebilir dosyalar. Boyut sunucudan okunur: kimse indirmeden önce
   neyi indirdiğini bilmemiş olmasın. */
function km_boy(string $yol): string {
    $t = __DIR__ . '/' . $yol;
    if (!is_file($t)) return '';
    $b = (int)filesize($t);
    if ($b < 1024) return $b . ' B';
    if ($b < 1024 * 1024) return round($b / 1024) . ' KB';
    return round($b / (1024 * 1024), 1) . ' MB';
}

$surumler = [
    ['ad' => 'kutadgu-isaret-renkli',  'tr' => 'Tam renkli işaret',       'en' => 'Full colour mark',
     'ack_tr' => 'Açık zeminlerde kullanılır.', 'ack_en' => 'For use on light backgrounds.',
     'tip' => 'renkli', 'zemin' => 'acik'],
    ['ad' => 'kutadgu-isaret-ters',    'tr' => 'Koyu zemin sürümü',       'en' => 'Dark background version',
     'ack_tr' => 'Kitap ve figür fildişi renginde.', 'ack_en' => 'Book and figure in ivory.',
     'tip' => 'ters', 'zemin' => 'koyu'],
    ['ad' => 'kutadgu-isaret-zeminli', 'tr' => 'Zeminli işaret',          'en' => 'Mark with backing',
     'ack_tr' => 'Uygulama simgesi ve favicon.', 'ack_en' => 'App icon and favicon.',
     'tip' => 'tam', 'zemin' => 'acik'],
    ['ad' => 'kutadgu-isaret-daire',   'tr' => 'Daire içinde işaret',     'en' => 'Mark in a circle',
     'ack_tr' => 'Profil resmi ve yuvarlak yerler.', 'ack_en' => 'Profile pictures and round placements.',
     'tip' => 'daire', 'zemin' => 'acik'],
    ['ad' => 'kutadgu-isaret-tekrenk', 'tr' => 'Tek renk sürüm',          'en' => 'Single colour version',
     'ack_tr' => 'Bulunduğu yerin rengini alır; damga ve baskı için.', 'ack_en' => 'Takes the colour of its surroundings; for stamping and print.',
     'tip' => 'sade', 'zemin' => 'acik', 'png' => false],
    ['ad' => 'kutadgu-isaret-kucuk',   'tr' => 'Küçük ölçü sürümü',       'en' => 'Small size version',
     'ack_tr' => '16 ile 24 piksel arası için sadeleştirilmiş.', 'ack_en' => 'Simplified for 16 to 24 pixels.',
     'tip' => 'kucuk', 'zemin' => 'acik', 'png' => false],
];

$ekBas = <<<CSS
<style>
.km-bol{margin-bottom:var(--b-7)}
.km-bol > p{color:var(--metin-2)}

/* İşaret kartı. Dizgenin kartı değil: önizleme alanı kenardan kenara
   uzanır, bu yüzden kartın kendisinde dolgu yoktur. */
.km-k{background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-3);overflow:hidden;
  display:flex;flex-direction:column}
.km-on{display:grid;place-items:center;padding:var(--b-5) var(--b-4);background:var(--yuzey-2);
  min-height:150px}
/* Koyu zemin sürümü kendi zeminiyle birlikte gösterilir; tek renk
   sürüm bulunduğu yerin metin rengini alır, adı da bunu söyler. */
.km-on.koyu{background:var(--marka)}
.km-yaz{padding:var(--b-3) var(--b-4);display:flex;flex-direction:column;gap:var(--b-1);flex:1}
.km-yaz > span{font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-orta)}
.km-in{margin-top:auto;padding-top:var(--b-2)}

/* Simge ızgarası. Kaç sütun olacağına simge genişliği karar verir;
   sayfa ayrı bir eşik kurmaz. */
.km-simge{display:grid;gap:1px;grid-template-columns:repeat(auto-fit,minmax(118px,1fr));
  background:var(--cizgi);border:1px solid var(--cizgi);border-radius:var(--r-3);
  overflow:hidden;margin-top:var(--b-4)}
.km-simge a{background:var(--yuzey);display:flex;flex-direction:column;align-items:center;gap:var(--b-2);
  padding:var(--b-4) var(--b-2);color:var(--metin-2);text-align:center}
.km-simge a:hover{background:var(--kut-zemin);color:var(--kut);text-decoration:none}
.km-simge svg{color:var(--kut)}
.km-simge b{font-size:var(--y-1);font-weight:700;letter-spacing:.1em;text-transform:uppercase}

/* Renk kartı: üstte rengin kendisi, altında adı ve değeri. */
.km-renk{display:grid;gap:var(--b-3);grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  margin-top:var(--b-4)}
.km-r{border:1px solid var(--cizgi);border-radius:var(--r-3);overflow:hidden;background:var(--yuzey)}
.km-r i{display:block;height:64px}
.km-r div{padding:var(--b-2) var(--b-3)}
.km-r b{display:block;font-size:var(--y-3)}
.km-r code{font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2)}

/* Kullanım kuralları. Numara metnin parçası değildir, sayaçla üretilir. */
.km-kural{counter-reset:kk;list-style:none;padding:0;margin:var(--b-4) 0 0;display:grid;gap:var(--b-2)}
.km-kural li{counter-increment:kk;position:relative;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:var(--r-3);padding:var(--b-3) var(--b-4) var(--b-3) var(--b-7);font-size:var(--y-3)}
.km-kural li::before{content:counter(kk);position:absolute;left:var(--b-3);top:var(--b-3);
  width:var(--b-5);height:var(--b-5);border-radius:50%;background:var(--kut-zemin);color:var(--kut);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700;display:grid;place-items:center}

/* Paket çağrısı ilk bölümün başlığına yapışmasın diye altında bir
   boşluk taşır; kutunun kendisinde boşluk yoktur. */
.km-hepsi{margin-bottom:var(--b-6)}
.km-hepsi p{margin:0;font-size:var(--y-3);flex:1 1 240px}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Kurumsal kimlik', 'Brand and identity'),
    'yol'    => '/kimlik.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu işaretinin bütün kullanım biçimleri, kavram simgeleri, renkleri ve kullanım kuralları; dosyalar ücretsiz indirilebilir.',
        'Every version of the Kutadgu mark, the concept icons, the colours and the rules of use; the files may be downloaded free of charge.'
    ),
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Kurumsal kimlik', 'Brand and identity') ?></span>
      <h1><?= k_c('İşaret, simgeler ve renkler', 'The mark, the icons and the colours') ?></h1>
      <p><?= k_c(
        'Kutadgu işaretini burada bütün kullanım biçimleriyle bulur ve indirirsiniz. Kimliği gizlemenin bir anlamı yok: sistemin kendisi açık olduğuna göre işareti de açık olsun. Aşağıdaki dosyalar ücretsizdir; sisteme atıfta bulunan haber, sunum, tez ve akademik çalışmalarda serbestçe kullanılabilir.',
        'Here you will find and may download every version of the Kutadgu mark. There is no sense in keeping the identity hidden: since the system itself is open, let its mark be open too. The files below are free of charge and may be used freely in news pieces, presentations, theses and scholarly work that refer to the system.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <div class="kutu kutu-kut satir km-hepsi">
      <?= kim_ikon('damga', 24) ?>
      <p><?= k_c(
        'Bütün dosyalar tek pakette: SVG ve PNG sürümleri, altı kavram simgesi, monogram, renk değerleri ve kullanım kuralları.',
        'Every file in a single package: SVG and PNG versions, the six concept icons, the monogram, the colour values and the rules of use.'
      ) ?></p>
      <a class="d d-vurgu d-kucuk" href="/k/kutadgu-kimlik.zip" download><?= k_c('Kimlik paketini indir', 'Download the identity pack') ?><?php $bz = km_boy('k/kutadgu-kimlik.zip'); if ($bz !== ''): ?> (<?= k_esc($bz) ?>)<?php endif; ?></a>
    </div>

    <?php /* SİSTEMİN KENDİ DOI'Sİ BURADA DURUR, ALTBİLGİDE DEĞİL.
             ÖNCE ALTBİLGİYE KONDU VE ÖLÇÜM GERİ ÇEVİRDİ. İki kez
             denendi: "Açıklık" sütununda dokuzuncu madde olarak
             (altbilgi-kapi üç genişlikte tavanı aştı: 1440'ta 475>470,
             1024'te 521>500, 390'da 1203>1200), sonra alt çubukta
             altıncı öge olarak (1440'ta 493>470, 768'de 727>720).
             Altbilginin kalabalık olduğu zaten bildirilmiş bir
             şikâyetti ve sütunlar 9/8/8 dengesine göre kurulmuştu;
             ölçüm iki kez aynı şeyi söyledi.

             Doğru yer burasıdır: bu sayfa sistemin KİMLİĞİNİ anlatır ve
             DOI bir kimliktir. Makineler zaten schema.org düğümünden ve
             CITATION.cff'ten okuyor; buradaki kayıt insan içindir.

             KAVRAM DOI'Sİ yazılır, sürümünki değil: anılacak olan
             sistemdir, 1.0.0 değil. */ ?>
    <?php $sysDoi = tg_doi(); if ($sysDoi !== ''): ?>
    <div class="km-bol" id="doi">
      <h2><?= k_c('Sistemin kalıcı kimliği', 'The permanent identifier of the system') ?></h2>
      <p><?= k_c(
        'Bu sistemin kendisi de bir çalışma olarak arşivlenmiştir ve kalıcı bir kimliği vardır. Kimlik, sistemin bütün sürümlerini kapsar ve değişmez; her zaman en son sürüme götürür. Çalışmalara verilen kalıcı işaretin yerine geçmez: o her yayımlanan çalışmanın işaretidir, bu ise sistemin kendisinin kimliğidir.',
        'This system is itself archived as a work and has a permanent identifier. The identifier covers every version of the system, does not change, and always leads to the most recent one. It does not stand in place of the permanent mark given to works: that marks each published work, while this identifies the system itself.'
      ) ?></p>
      <p><a href="<?= k_esc(tg_doi_adres($sysDoi)) ?>" rel="noopener" target="_blank"><b>DOI <?= k_esc($sysDoi) ?></b></a></p>
      <?php $sur = tg_doi_surum(); $surAd = tg_doi_surum_ad();
            if ($sur !== '' && $sur !== $sysDoi): ?>
      <p class="km-ack"><?= k_cd(
        'Yalnızca %1 sürümünü göstermek gerekiyorsa: ',
        'If only version %1 is to be cited: ', null, $surAd) ?><a href="<?= k_esc(tg_doi_adres($sur)) ?>" rel="noopener" target="_blank"><?= k_esc($sur) ?></a></p>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="km-bol" id="isaret">
      <h2><?= k_c('İşaret', 'The mark') ?></h2>
      <p><?= k_c(
        'İşaret üç şeyi bir arada söyler: küre yeryüzünü ve sınırsız erişimi, açık kitap bilgiyi, kollarını açmış figür ise onu yazan ve arkasında duran insanı. Figürün gövdesi bir kalem ucudur. Küreyi çevreleyen ayrı renkteki noktalar ayrı ayrı kültürlerdir; hepsi aynı kürenin çevresinde durur.',
        'The mark says three things at once: the globe is the earth and unbounded access, the open book is knowledge, and the figure with open arms is the person who writes it and stands behind it. The figure\'s body is the nib of a pen. The differently coloured points around the globe are distinct cultures; all of them stand around the same globe.'
      ) ?></p>
      <div class="dizi dizi-3">
        <?php foreach ($surumler as $sv): ?>
        <div class="km-k">
          <div class="km-on<?= $sv['zemin'] === 'koyu' ? ' koyu' : '' ?>">
            <?= kim_isaret(96, $sv['tip']) ?>
          </div>
          <div class="km-yaz">
            <b><?= k_esc(k_t($sv)) ?></b>
            <span><?= k_esc(k_t(['tr' => $sv['ack_tr'], 'en' => $sv['ack_en']])) ?></span>
            <span class="d-kume km-in">
              <a href="/k/marka/<?= k_esc($sv['ad']) ?>.svg" class="d d-ikinci d-kucuk" download>SVG</a>
              <?php if (($sv['png'] ?? true) !== false): ?>
                <a href="/k/marka/<?= k_esc($sv['ad']) ?>-512.png" class="d d-ikinci d-kucuk" download>PNG 512</a>
                <?php if (is_file(__DIR__ . '/k/marka/' . $sv['ad'] . '-1024.png')): ?>
                <a href="/k/marka/<?= k_esc($sv['ad']) ?>-1024.png" class="d d-ikinci d-kucuk" download>PNG 1024</a>
                <?php endif; ?>
              <?php endif; ?>
            </span>
          </div>
        </div>
        <?php endforeach; ?>

        <div class="km-k">
          <div class="km-on"><?= kim_monogram(96) ?></div>
          <div class="km-yaz">
            <b><?= k_c('Monogram', 'Monogram') ?></b>
            <span><?= k_c('KUTADGU sözcüğünün ortasındaki, karnında altın bir nokta taşıyan A. Çok dar yerlerde işaretin yerine geçer.', 'The A at the centre of the word KUTADGU, carrying a gold point in its counter. It stands in for the mark where space is very tight.') ?></span>
            <span class="d-kume km-in">
              <a href="/k/marka/kutadgu-monogram.svg" class="d d-ikinci d-kucuk" download>SVG</a>
              <a href="/k/marka/kutadgu-monogram-512.png" class="d d-ikinci d-kucuk" download>PNG 512</a>
              <a href="/k/marka/kutadgu-monogram-ters.svg" class="d d-ikinci d-kucuk" download><?= k_c('Koyu zemin', 'Dark bg') ?></a>
            </span>
          </div>
        </div>
      </div>
    </div>

    <div class="km-bol" id="simgeler">
      <h2><?= k_c('Altı kavram simgesi', 'The six concept icons') ?></h2>
      <p><?= k_c(
        'Simgeler işaretin kendi parçalarından türetilmiştir: kürenin çizgileri, açık kitap, kalem ucu, figür, pusula ve ortak halkada duran noktalar. Böylece simgeler logodan ayrı bir dil kurmaz. Tıklayınca SVG dosyası iner.',
        'The icons are derived from the parts of the mark itself: the lines of the globe, the open book, the pen nib, the figure, the compass and the points standing on a shared ring. The icons therefore speak no language separate from the logo. Click to download the SVG.'
      ) ?></p>
      <div class="km-simge">
        <?php foreach (kim_kavramlar() as $k): ?>
          <a href="/k/marka/simge-<?= k_esc($k['im']) ?>.svg" download>
            <?= kim_ikon($k['im'], 30) ?>
            <b><?= k_esc(k_t($k)) ?></b>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="km-bol" id="renkler">
      <h2><?= k_c('Renkler', 'Colours') ?></h2>
      <p><?= k_c(
        'İki renk yeterlidir: lacivert ve altın. Açık zeminde metin olarak kullanılan altın, kimlik altınının tonu korunarak koyulaştırılmıştır; küçük punto etiketlerin okunabilmesi için karşıtlık eşiğini geçmesi gerekir.',
        'Two colours suffice: navy and gold. The gold used as text on light backgrounds is a darkened form of the identity gold, its hue preserved; it must clear the contrast threshold so that small labels remain legible.'
      ) ?></p>
      <div class="km-renk">
        <?php foreach ([
          ['lacivert',      'Lacivert',      'Navy'],
          ['lacivert_koyu', 'Lacivert koyu', 'Deep navy'],
          ['altin',         'Altın',         'Gold'],
          ['altin_ac',      'Altın açık',    'Light gold'],
          ['altin_koyu',    'Koyu altın',    'Dark gold'],
          ['fildisi',       'Fildişi',       'Ivory'],
        ] as $rk): ?>
        <div class="km-r">
          <i style="background:<?= k_esc($r[$rk[0]]) ?>"></i>
          <div><b><?= k_esc($en ? $rk[2] : $rk[1]) ?></b><code><?= k_esc($r[$rk[0]]) ?></code></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="km-bol" id="kurallar">
      <h2><?= k_c('Kullanım kuralları', 'Rules of use') ?></h2>
      <ol class="km-kural">
        <li><?= k_c('İşaretin oranları değiştirilmez, eğilmez; gölge, anahat ya da parlaklık eklenmez.', 'The proportions of the mark are not altered and it is not skewed; no shadow, outline or gloss is added.') ?></li>
        <li><?= k_c('Çevresinde en az kendi genişliğinin altıda biri kadar boş alan bırakılır.', 'A clear space of at least one sixth of its own width is left around it.') ?></li>
        <li><?= k_c('En küçük kullanım 16 pikseldir; bu ölçüde sadeleştirilmiş sürüm kullanılır.', 'The smallest use is 16 pixels; at that size the simplified version is used.') ?></li>
        <li><?= k_c('İşaret kimlik renkleri dışında bir renge boyanmaz. Tek renk gerektiğinde tek renk sürüm kullanılır.', 'The mark is not coloured outside the identity palette. Where a single colour is required, the single colour version is used.') ?></li>
        <li><?= k_c('İşaret, Kutadgu dışında bir yayının ya da kurumun işareti olarak kullanılamaz; sisteme atıfta bulunan çalışmalarda serbestçe kullanılabilir.', 'The mark may not be used as the mark of any publication or institution other than Kutadgu; it may be used freely in work that refers to the system.') ?></li>
        <li><?= k_c('İşaret bir marka işaretidir. Sistemin metinlerini kapsayan CC BY 4.0 lisansı, işaretin marka olarak kullanımını kapsamaz.', 'The mark is a trade mark. The CC BY 4.0 licence that covers the texts of the system does not cover use of the mark as a trade mark.') ?></li>
      </ol>
    </div>

   </div>
   <?= k_belge_yan([
     ['k' => 'isaret',   'tr' => 'İşaret',            'en' => 'The mark'],
     ['k' => 'simgeler', 'tr' => 'Altı kavram simgesi','en' => 'The six icons'],
     ['k' => 'renkler',  'tr' => 'Renkler',           'en' => 'Colours'],
     ['k' => 'kurallar', 'tr' => 'Kullanım kuralları','en' => 'Rules of use'],
   ], [
     ['tr' => 'Slogan', 'en' => 'The line',
      'ic' => k_esc(kim_slogan(k_dil()))],
     ['tr' => 'Adın anlamı', 'en' => 'What the name means',
      'ic' => k_c('Kutadgu Bilig: kut veren, insanı mutluluğa eriştiren bilgi. Tamga ise Türk boylarının kendilerine ait olanı işaretlemek için kullandığı damgadır.',
                  'Kutadgu Bilig: knowledge that brings fortune and wellbeing. A tamga is the mark Turkic clans used to sign what belonged to them.')
            . '<br><a href="' . k_esc(k_bag('/bildiri.php#b-ad')) . '">' . k_c('Bildiride ayrıntısı', 'In full in the declaration') . '</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
