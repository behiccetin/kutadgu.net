<?php
/* =====================================================================
   SİTE HARİTASI — İNSAN İÇİN
   ---------------------------------------------------------------------
   Sistemde zaten iki harita var ve ikisi de MAKİNE içindir:
   /sitemap.php arama motorlarına, /oai.php dizinlere konuşur. İnsanın
   "burada başka ne var" sorusunu soracağı bir yer yoktu; menü yalnızca
   bir seçki gösteriyor, gerisi ancak bir sayfanın içindeki bağlantıdan
   bulunuyordu.

   Bu sayfa bütün genel sayfaları, ne işe yaradıklarıyla birlikte, tek
   listede toplar. Üç kural:

     1. MENÜDEN ÜRETİLİR. Sol menüdeki kümeler ve maddeler k_gez() ile
        k_gez_kume()'den okunur; elle ikinci bir liste yazmak, menü
        değiştiğinde haritayı sessizce yanlış yapardı (tek kaynak
        kuralı). Menüde bulunmayan ama herkese açık olan sayfalar
        ayrıca, kendi kümelerinde yazılır.
     2. GİRİŞ İSTEYEN YER "GİRİŞ İSTER" DİYE YAZILIR, GİZLENMEZ.
        Panelin varlığı bir sır değildir; içine kimin girebileceği
        ayrı bir sorudur.
     3. HER SATIRIN BİR CÜMLESİ VARDIR. Yalnız başlıklardan oluşan bir
        harita, aramaya gelen insana menüden fazlasını vermez.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

$en = k_en();

$ekBas = <<<CSS
<style>
/* Harita listesi. Kart değil liste: kart, her maddeyi eşit ağırlıkta
   ve büyük gösterir; oysa burada aranan şey göz gezdirmektir. */
.hr-kume{margin:var(--b-6) 0 0}
.hr-kume > h2{font-size:var(--y-6);margin:0 0 var(--b-2)}
.hr-kume > p{margin:0 0 var(--b-4);color:var(--metin-2);font-size:var(--y-3);max-width:70ch}
.hr-liste{list-style:none;margin:0;padding:0;display:grid;gap:1px;
  background:var(--cizgi);border:1px solid var(--cizgi);border-radius:var(--r-3);overflow:hidden}
.hr-liste > li{background:var(--yuzey)}
.hr-liste a{display:grid;grid-template-columns:auto 1fr;gap:var(--b-2) var(--b-3);
  align-items:baseline;padding:var(--b-3) var(--b-4);text-decoration:none;color:inherit}
.hr-liste a:hover{background:var(--yuzey-2);text-decoration:none}
.hr-ad{font-weight:600}
.hr-ack{grid-column:2;color:var(--metin-2);font-size:var(--y-3);margin:0}
.hr-yol{grid-column:2;font-family:var(--mono);font-size:var(--y-1);color:var(--metin-3,var(--metin-2))}
.hr-im{color:var(--kut);display:inline-flex;align-items:center}
.hr-not{margin-top:var(--b-2);font-size:var(--y-2);color:var(--metin-2)}
@media (max-width:560px){
  .hr-liste a{grid-template-columns:1fr}
  .hr-ack,.hr-yol{grid-column:1}
}
</style>
CSS;

/* ---------------------------------------------------------------------
   Menüden gelen kümeler. Açıklamalar burada durur: menüde yer yok,
   haritada ise asıl iş açıklamadır.
   --------------------------------------------------------------------- */
$ack = [
    '/'                => ['Sistemin ne olduğu, son çalışmalar ve sayılar.', 'What the system is, the latest works and the numbers.'],
    '/nasil-isler.php' => ['Gönderimden yayına bütün basamaklar, sırasıyla.', 'Every step from submission to publication, in order.'],
    '/yazilar.php'     => ['Yayımlanmış bütün çalışmalar; hakemli ve hakemsiz birlikte.', 'All published work; reviewed and non reviewed together.'],
    '/ara.php'         => ['Başlık, yazar, alan ve tam metinde arama.', 'Search in titles, authors, fields and full text.'],
    '/istatistik.php'  => ['Arşivin sayıları: çalışma, hakemlik, okuma, bekleme süreleri.', 'The numbers of the archive: works, reviews, readings, waiting times.'],
    '/hakemlik.php'    => ['Hakemliğin nasıl işlediği; kararlar, raporlar ve diyalog.', 'How review works here; decisions, reports and dialogue.'],
    '/bekleyen.php'    => ['Şu anda hakem arayan çalışmalar. Gönüllü olabilirsiniz.', 'Works seeking reviewers right now. You may volunteer.'],
    '/hakemler.php'    => ['Sistemde hakemlik yapanların açık dizini.', 'The open directory of those who review here.'],
    '/ilkeler.php'     => ['Bütün kurallar tek belgede, on altı bölüm.', 'Every rule in a single document, sixteen sections.'],
    '/kurul.php'       => ['Yayın kurulu: kim, hangi görevde, ne zamandan beri; ve kurulun kendisi hakkındaki kararlar, oylarıyla birlikte.', 'The editorial board: who, in which role, since when; and the decisions about the board itself, with their votes.'],
    '/yz.php'          => ['Yapay zekânın bu sistemde neye izinli olduğu.', 'What artificial intelligence is permitted to do here.'],
    '/acikliklar.php'  => ['Sistemin kendi kusurları ve sahip olmadığı güven işaretleri.', 'The system\'s own faults and the marks of standing it does not hold.'],
    '/basvuru.php'     => ['Gönderim koşulları ve adım adım başvuru formu.', 'The conditions and the step by step application form.'],
    '/kilavuz.php'     => ['Metni yazmaktan yayına kadar bütün yol; şablon, yapıştırma, şekil ve veri.', 'The whole road from writing the text to publication; template, pasting, figures and data.'],
    '/destek.php'      => ['Kurumsal destek ve himaye çağrısı.', 'The call for institutional support and patronage.'],
    '/iletisim.php'    => ['Soru, öneri ve bildirimler için.', 'For questions, suggestions and reports.'],
    '/harita.php'      => ['Bu sayfa.', 'This page.'],
];

/* Menüde olmayan ama herkese açık sayfalar. Menüde olmamalarının
   sebebi gizlilik değil, menünün bir seçki olmasıdır. */
$ekSayfalar = [
    ['k' => 'bil', 'yol' => '/bildiri.php', 'im' => 'ilkeler',
     'tr' => 'Kutadgu Bildirisi', 'en' => 'The Kutadgu Declaration',
     'ack' => ['Sistemin kuruluş metni: neye söz veriyor, neyi reddediyor.', 'The founding text: what the system promises and what it refuses.']],
    ['k' => 'bil', 'yol' => '/lisans.php', 'im' => 'kalkan',
     'tr' => 'Lisans belgesi (makine okur)', 'en' => 'Licence document (machine readable)',
     'ack' => ['Kullanım koşulunun makine okunur hâli: kullan, ama an.', 'The condition in machine readable form: use it, but cite it.']],
    ['k' => 'oku', 'yol' => '/dokum.php', 'im' => 'damga',
     'tr' => 'Arşivin tamamını indir', 'en' => 'Download the whole archive',
     'ack' => ['Bütün arşiv tek dosyada, parmak iziyle birlikte.', 'The entire archive in one file, with its fingerprint.']],
    ['k' => 'oku', 'yol' => '/oai.php?verb=Identify', 'im' => 'yz',
     'tr' => 'OAI-PMH ucu (dizinler için)', 'en' => 'OAI-PMH endpoint (for indexes)',
     'ack' => ['Dizinlerin arşivi kendiliğinden toplaması için standart uç.', 'The standard endpoint by which indexes harvest the archive.']],
    ['k' => 'katil', 'yol' => '/panel.php', 'im' => 'grafik',
     'tr' => 'Panelim', 'en' => 'My panel', 'giris' => true,
     'ack' => ['Çalışmalarınız, hakemlikleriniz ve hesabınız. Giriş ister.', 'Your works, your reviews and your account. Requires signing in.']],
    ['k' => 'katil', 'yol' => '/hesap-kur.php', 'im' => 'insan',
     'tr' => 'Hesap açın', 'en' => 'Open an account',
     'ack' => ['Okumak için hesap gerekmez; göndermek ve hakemlik için gerekir.', 'No account is needed to read; one is needed to submit and to review.']],
];

$kumeler = k_gez_kume();
$kumeAck = [
    'oku'   => ['Yayımlanmış her şey burada ve hepsi ücretsizdir. Okumak için hesap gerekmez.',
                'Everything published is here and all of it is free. No account is needed to read.'],
    'hakem' => ['Değerlendirmenin nasıl işlediği ve şu anda nerede durduğu. Raporlar adıyla yayımlanır.',
                'How assessment works and where it stands right now. Reports are published under their authors\' names.'],
    'bil'   => ['Sistemin kuralları, o kuralları yürütenler ve sistemin kendi eksikleri.',
                'The rules of the system, those who run them, and the system\'s own shortcomings.'],
    'katil' => ['Çalışma göndermek, destek olmak ya da yazmak için.',
                'To submit work, to support the system, or to write to us.'],
    'son'   => ['', ''],
];

/* Menü maddelerini kümelerine göre topla; menüde olmayanları ekle. */
$gruplar = [];
foreach (k_gez() as $g) {
    $k = (string)($g['k'] ?? '');
    if ($k === '') $k = 'giris';
    $gruplar[$k][] = $g + ['ack' => $ack[$g['yol']] ?? ['', '']];
}
foreach ($ekSayfalar as $g) $gruplar[(string)$g['k']][] = $g;

/* Başlıksız kümeler ('giris' ve 'son') kendi adlarını burada alır:
   haritada başlıksız bir liste, okuru ortada bırakır. */
$kumeler['giris'] = ['tr' => 'Başlangıç', 'en' => 'Start here'];
$kumeAck['giris'] = ['Sistemi ilk kez görüyorsanız buradan başlayın.', 'If you are seeing the system for the first time, start here.'];
$kumeler['son']   = ['tr' => 'Bu sayfa', 'en' => 'This page'];

$sira = ['giris', 'oku', 'hakem', 'bil', 'katil', 'son'];

/* Kaç sayfa var: sayfanın kendi iddiası ölçülebilir olsun. */
$toplam = 0;
foreach ($gruplar as $liste) $toplam += count($liste);

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Site haritası', 'Site map'),
    'yol'    => '/harita.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu\'daki bütün genel sayfalar, ne işe yaradıklarıyla birlikte tek listede.',
        'Every public page in Kutadgu, in a single list, with what each one is for.'
    ),
]);
?>

<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Yön bulun', 'Find your way') ?></span>
      <h1><?= k_c('Site haritası', 'Site map') ?></h1>
      <?php /* Sayfa sayısı cümlenin İÇİNE konursa çeviri anahtarı o
               sayıya bağlanır: bir sayfa eklendiği gün cümle bütün
               sözlüklerden düşer (bkz. k_cd). */ ?>
      <p><?= k_cd(
        'Bu sistemdeki <b>%1</b> genel sayfanın hepsi aşağıda, ne işe yaradıklarıyla birlikte yazılı. Menüde yalnızca bir seçki durur; burada eksik yoktur. Giriş isteyen tek yer panelinizdir ve o da gizlenmemiştir: adı listede, koşulu yanında yazılıdır.',
        'All <b>%1</b> public pages in this system are listed below, together with what each is for. The menu shows only a selection; nothing is missing here. The one place that requires signing in is your panel, and it is not concealed either: its name is on the list with its condition beside it.',
        (int)$toplam
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <?php foreach ($sira as $k):
      if (empty($gruplar[$k])) continue;
      $kad  = k_t((array)$kumeler[$k]);
      $kack = k_t(['tr' => ($kumeAck[$k][0] ?? ''), 'en' => ($kumeAck[$k][1] ?? '')]);
    ?>
    <div class="hr-kume" id="k-<?= k_esc($k) ?>">
      <h2><?= k_esc($kad) ?></h2>
      <?php if ($kack !== ''): ?><p><?= k_esc($kack) ?></p><?php endif; ?>
      <ul class="hr-liste">
        <?php foreach ($gruplar[$k] as $g):
          $sayfaAck = k_t(['tr' => (string)($g['ack'][0] ?? ''), 'en' => (string)($g['ack'][1] ?? '')]);
        ?>
        <li>
          <a href="<?= k_esc(k_bag((string)$g['yol'])) ?>">
            <span class="hr-im"><?= kim_ikon((string)$g['im'], 17) ?></span>
            <span class="hr-ad"><?= k_esc(k_t($g)) ?><?php
              if (!empty($g['giris'])) echo ' <span class="rz rz-kut">' . k_c('giriş ister', 'sign in required') . '</span>';
            ?></span>
            <?php if ($sayfaAck !== ''): ?><span class="hr-ack"><?= k_esc($sayfaAck) ?></span><?php endif; ?>
            <span class="hr-yol"><?= k_esc((string)$g['yol']) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>

    <?php /* k_bag() adrese dili yazar; adres cümlenin içindeyken
             anahtar da dile göre değişiyordu ve cümle hiçbir dilde
             bulunamıyordu. Adres artık %1'dir. */ ?>
    <p class="hr-not"><?= k_cd(
      'Makine için iki harita daha var: arama motorları için <a href="/sitemap.php">/sitemap.php</a>, dizinler için <a href="/oai.php?verb=Identify">OAI-PMH ucu</a>. Bir sayfayı burada bulamadıysanız <a href="%1">bize yazın</a>; harita eksikse eksik olan haritadır, siz değil.',
      'There are two more maps, both for machines: <a href="/sitemap.php">/sitemap.php</a> for search engines and the <a href="/oai.php?verb=Identify">OAI-PMH endpoint</a> for indexes. If you could not find a page here, <a href="%1">write to us</a>; if the map is incomplete, it is the map that is at fault, not you.',
      k_esc(k_bag('/iletisim.php'))
    ) ?></p>

   </div>
   <?= k_belge_yan(array_values(array_filter(array_map(function ($k) use ($gruplar, $kumeler, $en) {
        if (empty($gruplar[$k])) return null;
        return ['k' => 'k-' . $k,
                'tr' => (string)($kumeler[$k]['tr'] ?? ''),
                'en' => (string)($kumeler[$k]['en'] ?? '')];
   }, $sira))), [
     ['tr' => 'Neden bu sayfa var', 'en' => 'Why this page exists',
      'ic' => k_c('Menü bir seçkidir; harita bir sayımdır. Bir sistemin sakladığı sayfası olmadığını göstermenin en kısa yolu, hepsini bir yere yazmaktır.',
                  'A menu is a selection; a map is a count. The shortest way to show that a system hides no page is to write them all down in one place.')],
     ['tr' => 'Makine için', 'en' => 'For machines',
      'ic' => '<a href="/sitemap.php">/sitemap.php</a><br><a href="/robots.txt">/robots.txt</a><br><a href="/llms.txt">/llms.txt</a><br><a href="/license.xml">/license.xml</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
