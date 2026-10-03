<?php
/* =====================================================================
   KUTADGU - Yazı ekleme kılavuzu / Guide to submitting a work
   ---------------------------------------------------------------------
   KURUL KARARI, 15 Ağustos 2026: "bir de yazı ekleme kılavuzu
   oluşturmak lazım, yardım menüsü gibi."

   BU SAYFA NİÇİN A'DAN SONRA YAZILDI. Kılavuz, olmayan bir ekranı
   anlatamaz. Yazma ekranı, Word şablonu ve yapıştırma ayrıştırıcısı
   bitmeden yazılsaydı, sistemin yapmadığı bir şeyi duyuran on altıncı
   metin olurdu — bu oturumun en sık çıkan kusur sınıfı tam olarak
   budur. Şimdi anlatılan her adım gerçekten vardır.

   TEK KAYNAK KURALI BU SAYFADA DA GEÇERLİDİR. Kılavuzun en kolay
   bozulma biçimi, kuralı ELLE YAZMAKTIR: koşul değişir, kılavuz eski
   kuralı anlatmayı sürdürür ve kimse fark etmez. Bu yüzden sayfadaki
   her sayı ve her koşul cümlesi ortak.php'deki işlevden okunur:

     tg_basvuru_adimlari()      form adımlarının adı ve sırası
     tg_yazarlik_doktora_sarti() doktora aranıyor mu
     tg_kabul_yolu()            kabul edilen çalışma hangi yola girer
     tg_benzerlik_sarti()       benzerlik raporu isteniyor mu
     tg_yazarlik_kosulu_kisa()  yazarlık koşulunun tek cümlesi
     tg_ayar('kabul_gecerli')   onay için gereken rapor sayısı

   Word şablonunun adresi de düzenleyicinin kullandığı adresle aynıdır
   (k/duzenleyici.php, kd_yapi); iki yerde iki ayrı dosya adı yazılsaydı
   biri güncellenir öteki unutulurdu.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

$adimlar  = tg_basvuru_adimlari();
$en       = k_en();
$doktora  = tg_yazarlik_doktora_sarti();
$benz     = tg_benzerlik_sarti();
$yol      = tg_kabul_yolu();
$metinAz  = (int)tg_ayar('metin_en_az_kelime', 800);   /* basvuru.php ile aynı kaynak */
$kabulSay = (int)tg_ayar('kabul_gecerli', 2);
$SABLON   = '/dosya/Kutadgu-calisma-sablonu.docx';

$ekBas = <<<CSS
<style>
/* Kılavuz numaralı adımlardan oluşur. Numara metnin parçası değildir,
   sayaçla üretilir: bir adım araya girdiğinde numaralar elle
   düzeltilmez. Aynı desen hakemlik.php'de de var; oradan kopyalanmadı,
   ikisi de dizgenin .kart ölçüsüne oturuyor. */
.kl{counter-reset:k;display:grid;gap:var(--b-4)}
.kl > article{position:relative;padding-left:clamp(46px,5vw,64px)}
.kl > article::before{counter-increment:k;content:counter(k);position:absolute;
  left:0;top:2px;width:32px;height:32px;border-radius:var(--r-tam);
  display:flex;align-items:center;justify-content:center;
  background:var(--marka);color:var(--marka-metin);border:2px solid var(--altin);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700}
.kl h3{margin:0 0 var(--b-2);font-size:var(--y-5)}
.kl p{margin:0 0 var(--b-2)}
.kl p:last-child{margin-bottom:0}
.kl ul{margin:var(--b-2) 0 0;padding-left:var(--b-5);display:grid;gap:var(--b-2)}

/* Adım şeridi: başvuru formundaki sıranın aynısı, tek kaynaktan. */
.kl-serit{display:flex;flex-wrap:wrap;gap:var(--b-2);margin:var(--b-3) 0 0;
  padding:0;list-style:none;font-family:var(--ui);font-size:var(--y-2)}
.kl-serit li{display:flex;align-items:center;gap:var(--b-2);
  padding:var(--b-1) var(--b-3);border:1px solid var(--cizgi);
  border-radius:var(--r-tam);background:var(--yuzey);color:var(--metin-2)}
.kl-serit b{font-family:var(--mono);color:var(--kut)}

/* Soru-cevap kümesi. <details> kullanılıyor: klavyeyle ve ekran
   okuyucuyla kendiliğinden çalışır, elle yazılmış bir aç/kapa o
   davranışı yeniden üretmek zorunda kalırdı. */
.sc{display:grid;gap:var(--b-2);margin-top:var(--b-4)}
.sc details{border:1px solid var(--cizgi);border-radius:var(--r-2);background:var(--yuzey)}
.sc summary{cursor:pointer;list-style:none;padding:var(--b-3) var(--b-4);
  font-weight:600;min-height:var(--hedef);display:flex;align-items:center}
.sc summary::-webkit-details-marker{display:none}
.sc summary:hover{background:var(--kut-zemin);color:var(--kut)}
.sc summary:focus-visible{outline:2px solid var(--kut);outline-offset:-2px}
.sc > details > div{padding:0 var(--b-4) var(--b-4);color:var(--metin-2)}
.sc p{margin:0 0 var(--b-2)}
.sc p:last-child{margin-bottom:0}

.serit{background:var(--yuzey-2);border-block:1px solid var(--cizgi)}
.kap > .kutu{margin-top:var(--b-4)}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Yazı ekleme kılavuzu', 'Guide to submitting a work'),
    'yol'    => '/kilavuz.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Çalışmanızı Kutadgu\'ya nasıl göndereceğiniz, metni nasıl yazacağınız, şekil ve veriyi nereye koyacağınız adım adım anlatılır.',
        'How to send your work to Kutadgu, how to write the text, and where figures and data belong, step by step.'
    ),
]);
?>

<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
   <div>
    <span class="bas-ust"><?= k_c('Kılavuz', 'Guide') ?></span>
    <h1><?= k_c('Çalışmanızı göndermenin tamamı', 'Submitting your work, from start to end') ?></h1>
    <p><?= k_c(
      'Bu sayfa tek bir soruyu yanıtlar: elinizdeki metni bu sistemde yayımlatmak için ne yapmanız gerekir? Sıra, gerçekten karşılaşacağınız sıradır; hiçbir adım süslenmedi, hiçbir adım atlanmadı.',
      'This page answers one question: what do you have to do to get the text in your hand published in this system? The order is the order you will actually meet; no step is dressed up and none is skipped.'
    ) ?></p>
    <div class="d-kume" style="margin-top:var(--b-4)">
      <a class="d d-vurgu" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Çalışma gönderin', 'Submit a work') ?></a>
      <a class="d d-ikinci" href="<?= k_esc($SABLON) ?>" download><?= k_c('Word şablonunu indirin', 'Download the Word template') ?></a>
    </div>
   </div>
  </div>
</section>

<!-- ================= ÖNCE: NE GEREKİYOR ================= -->
<section class="bolum">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Başlamadan', 'Before you start') ?></span>
    <h2><?= k_c('Elinizde ne olmalı', 'What you need to hand') ?></h2>
    <?php /* KOŞUL CÜMLESİ ELLE YAZILMAZ. Doktora şartı bir gün geri
             açılırsa bu paragrafın da değişmesi gerekir; işlevden
             okunduğu için kendiliğinden değişir. */ ?>
    <p class="metin-sonuk"><?= k_esc(tg_yazarlik_kosulu_kisa($en)) ?></p>
    <ul class="ok-liste">
      <li><?= k_c('<b>Tam metniniz.</b> Tam metin başvuru anında istenir: en az ' . $metinAz . ' kelime. Word belgenizi açıp tamamını kopyalar ve forma yapıştırırsınız; dosya yüklenmez.', '<b>Your full text.</b> The full text is asked for at application time: at least ' . $metinAz . ' words. You open your Word document, copy all of it and paste it into the form; no file is uploaded.') ?></li>
      <li><?= k_c('<b>Kaynakçanız.</b> Metinden ayrı bir kutuya yapıştırılır ve boş bırakılamaz. Kaynakçası olmayan bir çalışma yayımlanmaz.', '<b>Your reference list.</b> It is pasted into a box of its own and cannot be left empty. A work without sources is not published.') ?></li>
      <li><?= k_c('<b>Künye dilinde başlık ve öz.</b> Çalışmanız künye dilinden başka bir dildeyse (örneğin Türkçeyse) künye dilinde başlık, kısa öz ve ayrıca genişletilmiş bir öz de gerekir. Ayrıntısı aşağıdaki sorular arasındadır.', '<b>A title and abstract in the metadata language.</b> If your work is in a language other than the metadata language (Turkish, for example), a title, a short abstract and also an extended abstract in the metadata language are needed. The details are in the questions below.') ?></li>
      <li><?= k_c('<b>Her yazar için ORCID.</b> Bu sistemde bir adın kime ait olduğunu belirleyen tek numara odur. Yoksa <a href="https://orcid.org/register" target="_blank" rel="noopener">orcid.org</a> üzerinden dakikalar içinde ücretsiz alınır.', '<b>An ORCID for every author.</b> It is the only number that ties a name to a person in this system. If you have none, one is free and takes minutes at <a href="https://orcid.org/register" target="_blank" rel="noopener">orcid.org</a>.') ?></li>
      <li><?= k_c('<b>Ortak yazarların e-posta adresleri.</b> Eklediğiniz her yazara, bu çalışmada yazar olarak gösterildiğini söyleyen bir bildirim gider. Adının kullanıldığını bilmeyen bir yazarlık, yazarlık değildir.', '<b>The e mail addresses of your co authors.</b> Every author you add receives a notice saying they have been listed as an author of this work. An authorship the person does not know about is not an authorship.') ?></li>
      <li><?= k_c('<b>Etik kurul kararınız</b> (gerekiyorsa): kurulun tam adı, karar tarihi ve karar numarası. Belge yüklenmez; bu üç bilgi yayımlanır.', '<b>Your ethics committee decision</b> (if one is required): the full name of the committee, the date and the number. The document is not uploaded; those three details are published.') ?></li>
      <?php if ($benz): ?>
      <li><?= k_c('<b>Benzerlik raporu.</b> iThenticate, Turnitin ya da intihal.net raporunun erişilebilir bir bağlantısı.', '<b>A similarity report.</b> An accessible link to an iThenticate, Turnitin or intihal.net report.') ?></li>
      <?php endif; ?>
    </ul>
    <p class="kutu kutu-kut"><?= k_c(
      '<b>Önce giriş yapın.</b> Çalışma göndermek için hesabınızla giriş yapmış olmanız gerekir. Hesabınız yoksa <a href="/panel.php">panel sayfasından</a> e-posta adresiniz ve en az 10 karakterli bir parolayla açarsınız. Giriş yapınca unvan, ad, kurum ve ORCID alanları kendiliğinden dolar. Formda yazdıklarınız tarayıcınızda taslak olarak saklanır; hesabınızı açıp döndüğünüzde olduğu gibi geri gelir.',
      '<b>Sign in first.</b> To send a work you must be signed in to your account. If you have none, you open one on <a href="/panel.php">the panel page</a> with your e mail address and a password of at least 10 characters. Once signed in, the title, name, institution and ORCID fields fill themselves. What you write in the form is kept as a draft in your browser; it comes back unchanged when you return with your account.'
    ) ?></p>
  </div>
</section>

<!-- ================= ADIMLAR ================= -->
<section class="bolum serit">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Sıra', 'The sequence') ?></span>
    <h2><?= k_c('Baştan sona', 'From beginning to end') ?></h2>

    <div class="kl">
      <article>
        <h3><?= k_c('Şablonu indirin ve metninizi onun içine yazın', 'Download the template and write inside it') ?></h3>
        <p><?= k_c(
          'Word şablonu, sistemin metninizden ne anlayacağını belirler. Başlıkları şablondaki <b>Başlık 1 / Başlık 2 / Başlık 3</b> biçemleriyle işaretleyin; kalın punto ile büyütülmüş bir satır başlık değildir, yalnızca büyük bir satırdır ve içindekiler orada oluşmaz.',
          'The Word template determines what the system will understand from your text. Mark your headings with the template\'s <b>Heading 1 / Heading 2 / Heading 3</b> styles; a line merely made bold and large is not a heading, only a large line, and no table of contents will grow from it.'
        ) ?></p>
        <p><?= k_c('Şablonda bir örnek tablo, bir şekil altyazısı ve zorunlu bir <b>Kaynakça</b> başlığı bulunur. Kaynakça başlığını silmeyin: çalışmanın sayfasındaki içindekilerde ayrı bir madde olarak görünür.', 'The template contains a sample table, a figure caption and a mandatory <b>References</b> heading. Do not delete the References heading: it appears as its own entry in the contents on the work\'s page.') ?></p>
        <p><a class="d d-ikinci d-kucuk" href="<?= k_esc($SABLON) ?>" download><?= k_c('Word şablonunu indirin', 'Download the Word template') ?></a></p>
      </article>

      <article>
        <h3><?= k_c('Başvuru formunu doldurun', 'Fill in the application form') ?></h3>
        <p><?= k_c('Form bir sihirbazdır: her adımda yalnızca o adımın alanları görünür, eksik bırakılan alan sizi orada durdurur ve eksiğin ne olduğu alanın hemen yanında yazar. Yarıda bırakırsanız taslak bu tarayıcıda saklanır; sunucuya hiçbir şey gitmez.', 'The form is a wizard: only the fields of the current step are shown, a missing field stops you there, and what is missing is written next to that very field. If you leave it half done the draft is kept in this browser; nothing goes to the server.') ?></p>
        <?php /* Adım listesi TEK KAYNAKTAN gelir: formda bir adım
                 açılıp kapandığında bu şerit de kendiliğinden değişir.
                 Elle yazılsaydı kılavuz, formda olmayan bir adımı
                 anlatırdı. */ ?>
        <ol class="kl-serit">
          <?php foreach ($adimlar as $i => $a): ?>
          <li><b><?= (int)$i + 1 ?></b> <?= k_esc($en ? $a['en'] : $a['tr']) ?></li>
          <?php endforeach; ?>
        </ol>
        <p style="margin-top:var(--b-3)"><?= k_c('Toplam ' . count($adimlar) . ' adım. Hiçbiri dosya yüklemesi istemez.', $adimlar ? count($adimlar) . ' steps in all. None of them asks you to upload a file.' : '') ?></p>
      </article>

      <article>
        <h3><?= k_c('Ortak yazarları ekleyin', 'Add your co authors') ?></h3>
        <p><?= k_c(
          'Sorumlu yazar, çalışmayı sisteme gönderen kişidir. Ortak yazarları ORCID ile arayabilirsiniz: kişi sistemde kayıtlıysa adı ve kurumu kendiliğinden gelir. Kayıtlı değilse bilgilerini elle yazarsınız.',
          'The corresponding author is the person who sends the work. You can search for co authors by ORCID: if the person is registered here, their name and institution arrive by themselves. If not, you enter the details by hand.'
        ) ?></p>
        <p><?= k_c(
          'Gönderim bittiğinde her ortak yazara bir bildirim gider. Hesabı olan panelinden görür; hesabı olmayana bildirime bir <b>davet bağlantısı</b> eklenir ve otuz gün geçerli olur. Davet hiçbir yetki taşımaz: yazar olarak gösterilmek hakemlik ya da editörlük getirmez. Hesap açmak zorunlu da değildir; açılmasa da yazarlık durur.',
          'When the submission is complete every co author receives a notice. Anyone with an account sees it in their panel; anyone without one receives an <b>invitation link</b> in the notice, valid for thirty days. The invitation carries no authority: being listed as an author brings neither reviewing nor editorship. Opening an account is not obligatory either; the authorship stands without it.'
        ) ?></p>
      </article>

      <article>
        <h3><?= k_c('Editör okur ve karar verir', 'An editor reads and decides') ?></h3>
        <p><?php if ($doktora): ?><?= k_c(
          'Gönderdiğiniz künyeyi bir editör okur. Kabul edilirse çalışma hakemli sürece girer ve hakem aranır.',
          'An editor reads what you sent. If it is accepted the work enters the peer review track and reviewers are sought.'
        ) ?><?php else: ?><?= k_c(
          'Gönderdiğiniz künyeyi bir editör okur ve sisteme girip girmeyeceğine karar verir. <b>Kabul edilen çalışma önce hakemsiz olarak yayımlanır.</b> Dilerseniz sonradan hakem aranmasını istersiniz, dilerseniz hakemsiz bırakırsınız; karar sizindir.',
          'An editor reads what you sent and decides whether it enters the system. <b>An accepted work is first published without review.</b> You may later ask for reviewers to be sought, or leave it unreviewed; the decision is yours.'
        ) ?><?php endif; ?></p>
        <p><?= k_c('Karar e-posta ile bildirilir. Ret de gerekçesiyle bildirilir ve gerekçesiz ret verilmez.', 'The decision is sent by e mail. A rejection is also given with its reasons; no rejection is issued without them.') ?></p>
      </article>

      <article>
        <h3><?= k_c('Tam metni forma yapıştırın', 'Paste the full text into the form') ?></h3>
        <p><?= k_c(
          'Başvuru formu tam metni bir yazma kutusunda ister (en az ' . $metinAz . ' kelime); kabulden sonra aynı metni panelinizdeki yazma ekranında düzeltebilirsiniz. İkisinde de Word\'deki gibi bir araç çubuğu vardır: tablo ekleme, görsel ekleme, başlık, madde, üst ve alt simge, formül işaretleri, geri alma.',
          'The application form asks for the full text in a writing box (at least ' . $metinAz . ' words); after acceptance you can correct the same text on the writing screen in your panel. Both have a toolbar like the one in Word: insert table, insert image, headings, lists, superscript and subscript, symbols, undo.'
        ) ?></p>
        <p><?= k_c(
          '<b>Word belgenizi olduğu gibi yapıştırabilirsiniz.</b> Yapıştırdığınız anda Word\'ün biçim çöpü atılır, YAPI kalır: başlık düzeyleri, listeler, tablolar, dipnotlar, kalın ve eğik. İçindekiler yapıştırdığınız anda kendiliğinden oluşur ve ekranın yanında görünür; oluşmadıysa başlıklarınız biçemle değil elle büyütülmüş demektir ve ekran bunu size söyler.',
          '<b>You can paste your Word document as it is.</b> The moment you paste, Word\'s formatting rubbish is thrown away and the STRUCTURE is kept: heading levels, lists, tables, footnotes, bold and italic. A table of contents grows by itself as you paste and appears beside the screen; if it does not, your headings were enlarged by hand rather than styled, and the screen tells you so.'
        ) ?></p>
        <p><?= k_c(
          'Word belgenizdeki görseller bilgisayarınızda duruyorsa yapıştırmada gelmez ve kaç tanesinin düştüğü size sayıyla bildirilir; onları araç çubuğundaki görsel düğmesiyle tek tek eklersiniz.',
          'If the images in your Word document live on your own computer they do not come across when pasting, and you are told how many were dropped; you then add them one by one with the image button on the toolbar.'
        ) ?></p>
      </article>

      <article>
        <h3><?= k_c('Şekil, tablo ve veriyi yerine koyun', 'Put figures, tables and data where they belong') ?></h3>
        <p><?= k_c(
          '<b>Şekil ve tablo metnin içindedir</b>, ayrı bir yere yüklenmez: yazma ekranında, ait oldukları yere konur.',
          '<b>Figures and tables live inside the text</b> and are not uploaded anywhere separately: they go on the writing screen, at the place they belong.'
        ) ?></p>
        <p><?= k_c(
          '<b>Veri, kod ve yüksek çözünürlüklü dosyalar</b> için Kutadgu dosya barındırmaz; sakladığı şey metindir. Ücretsiz ve kalıcı bir akademik arşive yükleyip DOI bağlantısını forma yapıştırın. En kolayı <a href="https://zenodo.org" target="_blank" rel="noopener">Zenodo</a>&#8217;dur: CERN işletir, kurum gerektirmez, kayıt başına 50 GB verir ve her kayda kalıcı bir DOI çıkarır. <a href="https://osf.io" target="_blank" rel="noopener">OSF</a>, <a href="https://datadryad.org" target="_blank" rel="noopener">Dryad</a>, <a href="https://figshare.com" target="_blank" rel="noopener">figshare</a> ya da kurumunuzun açık arşivi de olur; kod için GitHub yeterlidir.',
          '<b>For data, code and high resolution files</b> Kutadgu hosts nothing; what it keeps is the text. Upload to a free, permanent academic archive and paste the DOI link into the form. The easiest is <a href="https://zenodo.org" target="_blank" rel="noopener">Zenodo</a>: run by CERN, no institution required, 50 GB per record, and a permanent DOI for every record. <a href="https://osf.io" target="_blank" rel="noopener">OSF</a>, <a href="https://datadryad.org" target="_blank" rel="noopener">Dryad</a>, <a href="https://figshare.com" target="_blank" rel="noopener">figshare</a> or your institution\'s open archive will do; GitHub is enough for code.'
        ) ?></p>
      </article>

      <article>
        <h3><?= k_c('Gönderin; sonrası açıktır', 'Send it; what follows is in the open') ?></h3>
        <p><?php if ($yol === 'hakemli'): ?><?= k_c(
          'Çalışma hakemli süreçte yürür. Hakemler adlarıyla imzalar, raporlar çalışmanın sayfasında yayımlanır ve onay için ' . $kabulSay . ' olumlu rapor aranır.',
          'The work runs through peer review. Reviewers sign by name, reports are published on the work\'s page, and ' . $kabulSay . ' favourable reports are required for approval.'
        ) ?><?php else: ?><?= k_c(
          'Çalışmanız hakemsiz olarak yayımlanır ve kalıcı bir tamga alır. Dilediğiniz an panelinizden <b>hakemliğe açabilirsiniz</b>; o andan sonra hakemler adlarıyla imzalar, raporlar çalışmanın sayfasında yayımlanır ve onay için ' . $kabulSay . ' olumlu rapor aranır.',
          'Your work is published without review and receives a permanent identifier. At any time you may <b>open it to review</b> from your panel; from then on reviewers sign by name, reports are published on the work\'s page, and ' . $kabulSay . ' favourable reports are required for approval.'
        ) ?><?php endif; ?></p>
        <p><?= k_c('Yayımlandıktan sonra da düzeltme isteyebilir, çalışmayı geri çekebilirsiniz. Her iki kayıt da silinmez: ne yapıldığı, ne zaman ve niçin yapıldığı çalışmanın sayfasında kalır.', 'After publication you may still ask for a correction or withdraw the work. Neither record is deleted: what was done, when and why remains on the work\'s page.') ?></p>
      </article>
    </div>
  </div>
</section>

<!-- ================= SIK SORULANLAR ================= -->
<section class="bolum">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Takıldığınız yer', 'Where people get stuck') ?></span>
    <h2><?= k_c('Sık sorulanlar', 'Common questions') ?></h2>
    <div class="sc">
      <details>
        <summary><?= k_c('İçindekiler oluşmadı, ne yapmalıyım?', 'No table of contents appeared. What now?') ?></summary>
        <div><p><?= k_c(
          'Başlıklarınız biçemle işaretlenmemiş demektir. Word\'de başlık satırını seçip <b>Başlık 1</b> ya da <b>Başlık 2</b> biçemini uygulayın, metni yeniden kopyalayıp yapıştırın. İsterseniz yazma ekranındaki başlık düğmesiyle satırları tek tek de işaretleyebilirsiniz; içindekiler o anda oluşur.',
          'It means your headings are not marked with styles. In Word select the heading line and apply the <b>Heading 1</b> or <b>Heading 2</b> style, then copy and paste again. You can also mark the lines one by one with the heading button on the writing screen; the contents appear at once.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Etik kurul belgesini nereye yükleyeceğim?', 'Where do I upload the ethics document?') ?></summary>
        <div><p><?= k_c(
          'Hiçbir yere. Belge yüklenmez; <b>kurulun tam adı, karar tarihi ve karar numarası</b> istenir ve bu üçü çalışmanızla birlikte yayımlanır. İki sebebi var: sistem taranmış bir belgenin gerçekliğini doğrulayamaz, ama kurul adı ve numara verildiğinde okur da editör de doğrudan veren kurula sorabilir; ayrıca etik kurul kararları kişisel veri taşır ve toplanmayan veri sızdırılamaz. Beyan sizi bağlar; yanlış beyan geri çekme sebebidir.',
          'Nowhere. The document is not uploaded; the <b>full name of the committee, the date and the number of the decision</b> are asked for, and those three are published with your work. There are two reasons: the system cannot verify a scanned document, whereas given the committee and the number a reader or an editor can ask the issuing committee directly; and ethics decisions carry personal data, which cannot leak if it is not collected. The declaration binds you; a false declaration is grounds for retraction.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Yapay zekâ kullandım. Sorun olur mu?', 'I used artificial intelligence. Is that a problem?') ?></summary>
        <div><p><?= k_c(
          'Kullanım yasak değildir; beyan edilmemesi kabul edilemez. Formun yapay zekâ adımında hangi aşamada ne kadar kullandığınızı yazarsınız ve bu beyan çalışmayla birlikte yayımlanır. Kullanmadıysanız o adımda başka bir şey istenmez.',
          'Use is not prohibited; failing to declare it is not acceptable. On the AI step of the form you state at which stage and to what extent you used it, and that declaration is published with the work. If you did not use any, nothing further is asked at that step.'
        ) ?></p></div>
      </details>
      <?php /* SUNULAN ŞEY DUYURULUR. Bir kolaylık, formda görülene
               kadar yoktur: kılavuzda yazmayan bir seçenek, yalnızca onu
               kazayla bulanların seçeneğidir. Burada ayrıca NE ZAMAN
               KULLANILMAMASI gerektiği de yazar — her alana uymayan bir
               kalıbı herkese önermek, uymayan alanların yazarlarına
               olmayan bir yöntem uydurtur. */ ?>
      <details>
        <summary><?= k_c('Özeti bölümlere ayırabilir miyim?', 'Can I divide the abstract into sections?') ?></summary>
        <div><p><?= k_c(
          'Ayırabilirsiniz, ama zorunlu değildir. Özetin altındaki kutuyu işaretlediğinizde dört alan açılır: amaç, yöntem ya da yaklaşım, bulgular ve özgünlük. Doldurduklarınız özeti kurar; boş bıraktıklarınız hiç görünmez. Çalışmanızın sayfasında bu bölümler adlarıyla birlikte çizilir. <b>Her alana uymaz:</b> kuramsal bir denemeye "yöntem" sormak sorunun kendisini anlamsız kılar; uymuyorsa kutuyu kapalı bırakın, serbest bir özet eksik sayılmaz.',
          'You can, but it is not required. When you tick the box under the abstract, four fields open: purpose, method or approach, findings and originality. What you fill in makes up the abstract; what you leave empty does not appear at all. On the page of your work these sections are shown with their names. <b>It does not suit every field:</b> asking a theoretical essay for its "method" makes the question itself meaningless. If it does not suit yours, leave the box off; a free abstract is not counted as incomplete.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Çalışmam Türkçe. İngilizce bir şey gerekiyor mu?', 'My work is in Turkish. Is anything needed in English?') ?></summary>
        <div><p><?= k_c(
          'Kayıt her zaman sizin yazdığınız dildeki metindir ve tam metnin çevirisi hiçbir zaman istenmez. Yalnızca künye dilinde bir başlık ve öz istenir; bunlar dizinler, arama motorları ve başka dillerde yazanların atıf künyesi içindir. Çalışmanız künye dilinden başka bir dildeyse ayrıca genişletilmiş bir özet istenir: sorunuz, yönteminiz, bulgunuz ve sonucunuz.',
          'The record is always the text in the language you wrote it in, and a translation of the full text is never asked for. Only a title and an abstract in the metadata language are asked for; these are for indexes, search engines and for citation by people writing in other languages. If your work is in a language other than the metadata language, an extended abstract is also asked for: your question, method, finding and conclusion.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Formda takıldım, neyin eksik olduğunu göremiyorum.', 'I am stuck on the form and cannot see what is missing.') ?></summary>
        <div><p><?= k_c(
          'Eksik alan kırmızı çerçeveyle işaretlenir ve eksiğin ne olduğu <b>o alanın hemen altında</b> yazar; sayfa da oraya kaydırılır. Alanı düzeltmeye başladığınızda uyarı kendiliğinden kalkar. Yine de takılırsanız bize yazın: takıldığınız yer bizim kusurumuzdur.',
          'The missing field is marked with a red border and what is missing is written <b>directly beneath that field</b>; the page also scrolls to it. The warning clears itself as soon as you start correcting the field. If you are still stuck, write to us: where you get stuck is our fault.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Ortak yazarım sistemde kayıtlı değil.', 'My co author is not registered here.') ?></summary>
        <div><p><?= k_c(
          'Sorun değil. Bilgilerini elle yazın ve e-posta adresini girin; gönderim bittiğinde o kişiye, bu çalışmada yazar olarak gösterildiğini söyleyen bir bildirim ve otuz gün geçerli bir hesap kurma daveti gider. Hesabı ORCID\'iyle ya da e-postasıyla kurabilir; kurmasa da yazarlığı durur.',
          'That is fine. Enter the details by hand along with an e mail address; when the submission is complete that person receives a notice saying they have been listed as an author, together with an invitation to open an account, valid for thirty days. They can open it with their ORCID or with their e mail; the authorship stands even if they do not.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Form ORCID numaramı geçersiz sayıyor.', 'The form says my ORCID is invalid.') ?></summary>
        <div><p><?= k_c(
          'ORCID numarasının son hanesi bir sağlama basamağıdır; tek bir rakam yanlışsa numara kabul edilmez. Numarayı elle yazmak yerine orcid.org profilinizden kopyalayıp yapıştırın. 16 haneli numara ya da orcid.org bağlantısı kabul edilir.',
          'The last digit of an ORCID is a check digit; a single wrong digit and the number is refused. Instead of typing it, copy it from your orcid.org profile and paste it. Either the 16 digit number or the orcid.org link is accepted.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Gönderirken "çok fazla başvuru denemesi" uyarısı çıktı.', 'I got a "too many application attempts" message.') ?></summary>
        <div><p><?= k_c(
          'Aynı hesaptan kısa sürede 30\'dan fazla gönderme denemesi yapıldığında sistem iki saat bekletir; hatalı denemeler de sayılır. Aynı kurumdaki başka hesaplar bundan etkilenmez. İki saat sonra yazdıklarınız tarayıcınızda duruyorsa kaldığınız yerden devam edersiniz.',
          'After more than 30 attempts to send from one account in a short time the system makes you wait two hours; failed attempts count too. Other accounts at the same institution are not affected. After two hours, if what you wrote is still in your browser, you carry on where you left off.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Tam metin yeterli sayılmıyor.', 'The full text is not counted as enough.') ?></summary>
        <div><p><?= k_c(
          'Form tam metinde en az ' . $metinAz . ' kelime arar ve kutunun altında kelime sayınızı gösterir. Yalnızca özet ya da yarım bir metin yapıştırdıysanız sayı yetmez. Word belgesinde Ctrl+A ile tümünü seçip kopyalayın ve kutuya yapıştırın.',
          'The form looks for at least ' . $metinAz . ' words in the full text and shows your word count under the box. If you pasted only the abstract or half a text, the count falls short. In your Word document select everything with Ctrl+A, copy it and paste it into the box.'
        ) ?></p></div>
      </details>
      <details>
        <summary><?= k_c('Bir yerde hâlâ takıldım.', 'I am still stuck somewhere.') ?></summary>
        <div><p><?= k_c('Bize yazın; bir kılavuzun anlatamadığı her yer, kılavuzun değil sistemin eksiğidir.', 'Write to us; anywhere a guide cannot explain is a shortcoming of the system, not of the guide.') ?>
          <a href="<?= k_esc(k_bag('/iletisim.php')) ?>"><?= k_c('Bize yazın', 'Write to us') ?></a></p></div>
      </details>
    </div>

    <div class="d-kume" style="margin-top:var(--b-6)">
      <a class="d d-vurgu" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Çalışma gönderin', 'Submit a work') ?></a>
      <a class="d d-ikinci" href="<?= k_esc(k_bag('/hakemlik.php')) ?>"><?= k_c('Hakemlik süreci', 'The review process') ?></a>
      <a class="d d-ikinci" href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkeleri', 'Publication policy') ?></a>
    </div>
  </div>
</section>

<?php k_son(); ?>
