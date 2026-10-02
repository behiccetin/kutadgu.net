<?php
/* =====================================================================
   KUTADGU - Nasıl işler / How it works
   ---------------------------------------------------------------------
   Sistemin tamamı tek bir basamak dizisidir: okurdan hakeme, hakemden
   yazara, yazardan editöre. Bu sayfa o diziyi sırasıyla gösterir.
   Roller ayrı ayrı konular değil, aynı merdivenin basamaklarıdır; bu
   yüzden dört ayrı sayfa değil, tek bir yol olarak anlatılır.

   Basamak sekmeleri sağ rayda durur ve her zaman görünür: hangi
   basamakta olduğunuz ve öteki basamakların neler olduğu aynı anda
   okunur. Betik çalışmazsa bütün basamaklar alt alta açık kalır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';
require_once __DIR__ . '/k/muhur.php';   /* tamga mührü: yayım adımının yanında */

$en   = k_en();
$kabS = (int)tg_ayar('kabul_gecerli', 2);
$retS = (int)tg_ayar('ret_donusum', 2);
$oyE  = (int)tg_ayar('oy_yazarlik_esigi', 4);
$kY   = (int)tg_ayar('kurul_yayin', 20);
$kO   = (int)tg_ayar('kurul_onayli', 10);

/* Basamaklar. Sıra anlamlıdır: her basamak bir öncekinin üstüne biner. */
$basamaklar = [
    ['k' => 'okur',   'im' => 'bilgi',   'tr' => 'Okur',    'en' => 'Reader',
     'ozet_tr' => 'Hiçbir kayıt gerekmez', 'ozet_en' => 'No registration at all'],
    ['k' => 'hakem',  'im' => 'terazi',  'tr' => 'Hakem',   'en' => 'Reviewer',
     'ozet_tr' => 'Doktora derecesi ve bir rapor', 'ozet_en' => 'A doctorate and one report'],
    /* Yazar basamağının özeti koşulun o anki hâline bakar: kuruluş
       döneminde önceden hakemlik aranmıyor, sekmede de aranıyormuş
       gibi yazmamalı. */
    /* DOKTORA ŞARTI KALKTIYSA ÖNCE O SÖYLENİR (kurul kararı,
       13 Ağustos 2026). Sıra önemlidir: alttaki iki karşılık da
       doktoradan söz eder ve biri yazılırsa sekme, sistemin artık
       aramadığı bir koşulu duyurur. */
    ['k' => 'yazar',  'im' => 'yayin',   'tr' => 'Yazar',   'en' => 'Author',
     'ozet_tr' => !tg_yazarlik_doktora_sarti() ? 'Herkes gönderebilir, editör karar verir'
                  : (tg_yazarlik_hakemlik_sarti() ? 'Bir hakemlik tamamlandıktan sonra' : 'Doktora derecesi yeter'),
     'ozet_en' => !tg_yazarlik_doktora_sarti() ? 'Anyone may submit, an editor decides'
                  : (tg_yazarlik_hakemlik_sarti() ? 'After one completed review' : 'A doctorate is enough')],
    ['k' => 'editor', 'im' => 'kilavuz', 'tr' => 'Editör',  'en' => 'Editor',
     'ozet_tr' => 'Kayıtla kazanılır, davetle değil', 'ozet_en' => 'Earned by record, not by invitation'],
];

$ekBas = <<<CSS
<style>
/* ---- Tamga örneği ----
   Mühür süstür ve rengi vurgudan gelir; kod ise okunacak olandır ve
   metin renginde, tek aralıklı yazıyla durur. İkisi aynı ağırlıkta
   çizilseydi okur mühürde bir bilgi arardı. */
.ni-tamga{display:flex;align-items:flex-start;gap:var(--b-3);margin:var(--b-4) 0;
  padding:var(--b-3);background:var(--yuzey-2);border:1px solid var(--cizgi);
  border-radius:var(--r-1)}
.ni-tamga > svg{flex:none;color:var(--vurgu)}
.ni-tamga-kod{display:block;font-family:var(--mono);font-size:var(--y-3);
  font-weight:600;letter-spacing:.02em}
.ni-tamga-ack{display:block;margin-top:var(--b-1);font-size:var(--y-1);
  line-height:var(--sh-genis);color:var(--metin-sonuk)}
@media (max-width:420px){.ni-tamga{flex-direction:column}}
/* Sağ raydaki basamak sekmeleri. Kutunun kendisi dizgenin .blg-nav
   bileşenidir; dizgede o kutunun içi bağlantı listesi olduğu için
   burada yalnızca düğmeler tanımlanır.

   Sekmeler ne kadar yer kaplayacağına eşikle değil kendi genişlikleriyle
   karar verir: rayda tek sütuna, ray metnin üstüne indiğinde yan yana
   dizilir. Böylece sayfa ikinci bir düzen eşiği kurmaz. */
.ni-sek{display:flex;flex-wrap:wrap;gap:var(--b-1);padding:var(--b-2)}
.ni-sek b{flex:1 0 100%;font-size:var(--y-1);font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:var(--metin-2);margin:0 var(--b-2) var(--b-1)}
.ni-sek button{flex:1 1 210px;display:flex;align-items:center;gap:var(--b-3);text-align:left;border:0;
  background:transparent;border-radius:var(--r-2);padding:var(--b-2) var(--b-3);cursor:pointer;
  min-height:var(--hedef);color:var(--metin-2);font:inherit;font-size:var(--y-3);font-weight:600;
  line-height:var(--sh-orta);border-left:2px solid transparent;transition:var(--gecis)}
.ni-sek button:hover{background:var(--yuzey-2);color:var(--metin)}
.ni-sek button[aria-selected="true"]{background:var(--kut-zemin);color:var(--kut);border-left-color:var(--kut)}
.ni-sek button svg{flex:none}
.ni-sek button > span{display:block;min-width:0}
.ni-sek button > span > span{display:block;font-size:var(--y-2);font-weight:400;
  color:var(--metin-2);line-height:var(--sh-orta);margin-top:var(--b-1)}
.ni-sek button[aria-selected="true"] > span > span{color:var(--kut)}

/* Merdiven şeridi: hangi basamakta olunduğu bölümün üstünde görünür.
   Sütun sayısını basamak genişliği belirler, ayrı bir eşik değil. */
.ni-mer{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1px;
  background:var(--cizgi);border:1px solid var(--cizgi);border-radius:var(--r-3);
  overflow:hidden;margin-bottom:var(--b-5)}
.ni-mer div{background:var(--yuzey);padding:var(--b-3);display:flex;flex-direction:column;gap:var(--b-1)}
.ni-mer div.simdi{background:var(--kut-zemin)}
.ni-mer em{font-style:normal;font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2)}
.ni-mer b{font-size:var(--y-3)}
.ni-mer div.simdi b,.ni-mer div.simdi em{color:var(--kut)}

.ni-pnl h2{margin-bottom:.35em;scroll-margin-top:calc(var(--ust) + var(--b-5))}
.ni-pnl > p{color:var(--metin-2)}

/* Basamağın adımları. Numara metnin parçası değildir, sayaçla üretilir. */
.ni-adim{counter-reset:na;list-style:none;padding:0;margin:var(--b-5) 0;display:grid;gap:var(--b-3)}
.ni-adim li{counter-increment:na;position:relative;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:var(--r-3);padding:var(--b-4) var(--b-4) var(--b-4) var(--b-7);font-size:var(--y-4)}
.ni-adim li::before{content:counter(na);position:absolute;left:var(--b-4);top:var(--b-4);
  width:var(--b-5);height:var(--b-5);border-radius:50%;background:var(--kut-zemin);color:var(--kut);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700;display:grid;place-items:center}
.ni-adim b{display:block;margin-bottom:var(--b-1)}

/* Gereken ve kazanılan. Kartın kendisi dizgeden gelir; burada yalnızca
   iki kartı ayıran başlık rengi ve listenin ölçüsü var. */
.ni-ikili{margin-block:var(--b-5)}
.ni-kt h3{margin:0 0 var(--b-2);font-family:var(--ui);font-size:var(--y-1);letter-spacing:.13em;
  text-transform:uppercase;font-weight:700}
.ni-kt.ger h3{color:var(--kut)}
.ni-kt.kaz h3{color:var(--yesil)}
.ni-kt ul{margin:0;padding-left:var(--b-5);display:grid;gap:var(--b-2);
  font-size:var(--y-3);color:var(--metin-2)}

/* Bölümün son sözü ve bölümden çıkan yollar. */
.ni-son,.ni-git{margin-top:var(--b-5)}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Nasıl işler', 'How it works'),
    'yol'    => '/nasil-isler.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu\'da okurdan hakeme, hakemden yazara, yazardan editöre uzanan yol; her basamakta ne gerekir, ne kazanılır.',
        'The path in Kutadgu from reader to reviewer, reviewer to author, author to editor; what each step requires and what it earns.'
    ),
]);

/* Merdiven şeridi: hangi basamağa bakıldığını gösterir */
function ni_merdiven(array $basamaklar, string $simdi, bool $en): string {
    $c = '<div class="ni-mer" aria-hidden="true">';
    foreach ($basamaklar as $i => $b) {
        $c .= '<div class="' . ($b['k'] === $simdi ? 'simdi' : '') . '">'
            . '<em>' . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . '</em>'
            . '<b>' . k_esc(k_t($b)) . '</b>'
            . '</div>';
    }
    return $c . '</div>';
}
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Yol', 'The path') ?></span>
      <h1><?= k_c('Bu sistem nasıl işler', 'How this system works') ?></h1>
      <p><?= k_c(
        'Burada roller satın alınmaz, sırayla kazanılır ve sıra atlanmaz. Okumakla başlar, hakemlikle sürer, yazarlıkla açılır, editörlükle sorumluluğa döner. Aşağıdaki dört basamak ayrı konular değil, aynı merdivenin basamaklarıdır; her birinde ne gerektiği ve ne kazanıldığı yazılıdır.',
        'Roles here are not bought but earned in sequence, and no step is skipped. It begins with reading, continues with reviewing, opens out into authorship and returns as responsibility in editorship. The four steps below are not separate topics but rungs of one ladder; what each requires and what it earns is set out.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg" data-sek>
   <div class="blg-ray">
   <nav class="blg-nav ni-sek" role="tablist" aria-label="<?= k_c('Basamaklar', 'Steps') ?>">
     <b><?= k_c('Basamaklar', 'Steps') ?></b>
     <?php foreach ($basamaklar as $i => $bs): ?>
     <button type="button" role="tab" id="nas-d<?= $i ?>" aria-controls="nas-p<?= $i ?>"
             aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? '0' : '-1' ?>">
       <?= kim_ikon((string)$bs['im'], 18) ?>
       <span><?= k_esc(k_t($bs)) ?>
         <span><?= k_esc(k_t(['tr' => $bs['ozet_tr'], 'en' => $bs['ozet_en']])) ?></span>
       </span>
     </button>
     <?php endforeach; ?>
   </nav>

   <?php /* Ray TEK bir kutudur ve yapışkanlık ona verilir. Bu sayfa
            rayını kendi yazdığı için not kutuları da buraya girer:
            ikinci bir .blg-ray açılırsa ızgarada aynı gözü paylaşır ve
            sekmelerin üstüne biner. Ölçüldü, biniyordu. */ ?>
   <aside class="blg-ek" aria-label="<?= k_c('Sayfa notları', 'Page notes') ?>">
     <div class="blg-kutu">
       <b><?= k_c('Sıra atlanmaz', 'No step is skipped') ?></b>
       <?= k_c('Okur, hakem, yazar, editör. Bir basamak, bir öncekini tamamlamadan açılmaz. Bunun tek istisnası kuruluş dönemidir ve o istisna ilgili çalışmanın sayfasında açıkça yazılıdır.',
               'Reader, reviewer, author, editor. No rung opens before the one below it is complete. The only exception is the founding period, and that exception is written plainly on the page of the work concerned.') ?>
     </div>
     <div class="blg-kutu">
       <b><?= k_c('Hesabınızın durumu', 'Where your account stands') ?></b>
       <?= k_c('Hangi basamakta olduğunuzu ve sıradaki adımı panelinizde görürsünüz.',
               'You can see which rung you are on and what comes next in your panel.') ?>
       <br><a href="<?= k_esc(k_bag('/panel.php')) ?>"><?= k_c('Panelime git', 'Go to my panel') ?></a>
     </div>
     <div class="blg-kutu">
       <b><?= k_c('Bütün kurallar', 'Every rule') ?></b>
       <a href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></a><br>
       <a href="<?= k_esc(k_bag('/yz.php')) ?>"><?= k_c('Yapay zekâ kullanımı', 'Use of artificial intelligence') ?></a><br>
       <a href="<?= k_esc(k_bag('/bildiri.php')) ?>"><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></a>
     </div>
   </aside>
   </div><!-- /blg-ray -->

   <div class="blg-ic">

    <!-- ---------- 01 OKUR ---------- -->
    <div class="sek-pnl ni-pnl" role="tabpanel" id="nas-p0" aria-labelledby="nas-d0" tabindex="0">
      <?= ni_merdiven($basamaklar, 'okur', $en) ?>
      <h2 id="okur"><?= k_c('Okur', 'Reader') ?></h2>
      <p><?= k_c(
        'Bu sistemde okumak için hiçbir şey gerekmez: hesap yok, abonelik yok, ücret yok, kayıt yok. Ölçülü bir cümleyle söylersek, okurluk bir basamak değil, sistemin varsayılan hâlidir.',
        'Reading here requires nothing at all: no account, no subscription, no fee, no registration. Put precisely, being a reader is not a rung but the default state of the system.'
      ) ?></p>
      <ol class="ni-adim">
        <li><b><?= k_c('Tam metinleri okursunuz', 'You read the full texts') ?></b><?= k_c('Hiçbir çalışmanın arkasında ödeme duvarı yoktur; hepsi CC BY 4.0 ile açıktır.', 'No work sits behind a paywall; all are open under CC BY 4.0.') ?></li>
        <li><b><?= k_c('Hakem raporlarını da okursunuz', 'You read the referee reports too') ?></b><?= k_c('Kör hakemlik yoktur. Her rapor, yazanın adıyla birlikte çalışmanın sayfasında durur.', 'There is no blind review. Every report stands on the work\'s page together with the name of the person who wrote it.') ?></li>
        <li><b><?= k_c('Süreci görürsünüz', 'You see the process') ?></b><?= tg_benzerlik_sarti()
              ? k_c('Benzerlik oranı, yapay zekâ beyanı, veri paylaşımı, etik kurul durumu ve hakem atamalarının kimin yaptığı hep açıktır.', 'The similarity ratio, the AI declaration, data sharing, ethics approval status and who assigned each reviewer are all in the open.')
              : k_c('Yapay zekâ beyanı, veri paylaşımı, etik kurul durumu ve hakem atamalarının kimin yaptığı hep açıktır.', 'The AI declaration, data sharing, ethics approval status and who assigned each reviewer are all in the open.') ?></li>
        <li><b><?= k_c('Kendi listenizi tutarsınız', 'You keep your own list') ?></b><?= k_c('Bir hesapla, izlemek istediğiniz çalışmaları listenize alırsınız. O çalışmaya yeni bir hakem raporu geldiğinde, hakem onayı değiştiğinde, bir kurul oylaması açıldığında ya da sonuçlandığında, altına şerh düşüldüğünde panelinizde haber alırsınız. Kimin neyi listesine aldığı açık edilmez.', 'With an account you add the works you want to follow to your list. You are told in your panel when a new referee report arrives, when the approval status changes, when a panel vote opens or concludes, or when a note is left beneath it. Who added what is never disclosed.') ?></li>
        <li><b><?= k_c('Arşivin tamamını indirirsiniz', 'You download the whole archive') ?></b><?= k_c('Tek dosyada, kişisel veri olmadan. Sistem kapansa bile arşiv elinizde kalır.', 'In a single file, with no personal data. Even were the system to close, the archive remains in your hands.') ?></li>
      </ol>
      <div class="kutu kutu-kut ni-son"><?= k_c(
        '<b>Sıradaki basamak.</b> Doktora ya da eşdeğeri bir dereceniz varsa, okurken gördüğünüz bir çalışmayı değerlendirmeye gönüllü olabilirsiniz. ' . k_esc(tg_hakemlik_yazarlik_cumlesi(false)),
        '<b>The next step.</b> If you hold a doctorate or its equivalent, you may volunteer to assess a work you come across while reading. In this system reviewing is also the door to authorship.'
      ) ?></div>
      <div class="d-kume ni-git">
        <a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Çalışmalara göz at', 'Browse the works') ?></a>
        <a class="d d-sessiz d-kucuk" href="/dokum.php"><?= k_c('Arşivin tamamını indir', 'Download the whole archive') ?></a>
      </div>
    </div>

    <!-- ---------- 02 HAKEM ---------- -->
    <div class="sek-pnl ni-pnl" role="tabpanel" id="nas-p1" aria-labelledby="nas-d1" tabindex="0" hidden>
      <?= ni_merdiven($basamaklar, 'hakem', $en) ?>
      <h2 id="hakem"><?= k_c('Hakem', 'Reviewer') ?></h2>
      <p><?= k_c(
        'Klasik dergide hakemi yalnızca editör çağırır; burada kapı açıktır. Ölçütü karşılayan herkes, hakem aranan bir çalışmaya kendiliğinden gönüllü olabilir. Gönüllü olunan bir değerlendirme yazarın önerisi sayılmaz; bu yüzden bağımsız hakemlik olarak kaydedilir ve onayda geçerli sayılır.',
        'In a conventional journal only an editor calls a reviewer; here the door is open. Anyone meeting the criterion may volunteer for a work seeking reviewers. An assessment you volunteer for does not count as the author\'s proposal, and is therefore recorded as an independent review and counts towards approval.'
      ) ?></p>
      <ol class="ni-adim">
        <li><b><?= k_c('Hesap açarsınız', 'You open an account') ?></b><?= k_c('Hesap açmak sizi hakem yapmaz; yalnızca sürecin başlangıcıdır.', 'Opening an account does not make you a reviewer; it is only the beginning of the process.') ?></li>
        <?php /* ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi:
                 "burada da e-Devlet diyor, kaldırmadık mı biz e-Devlet
                 olayını?"

                 Kaldırılmıştı: 14 Ağustos 2026 kurul kararı belge
                 yolunu bütünüyle kapattı ve doktora doğrulamasını tek
                 standarda indirdi — ADIYLA SORUMLULUK ÜSTLENEN BİR
                 EDİTÖR (gerekçesi ortak.php'de tg_dogrulama_editor()'ün
                 başındadır). Ama bu sayfa hâlâ e-Devlet kodunu, hatta
                 üç yollu eski düzeni anlatıyordu. Sistem, kaldırdığı bir
                 kuralı duyurmayı sürdürüyordu: bu oturumların en sık
                 yakalanan kusur sınıfının yirmi beşinci örneği.

                 SORUMLULUK KİMDE: ataması ya da onayı yapan editörde.
                 Bir editör birini hakem olarak atadıysa, o kişinin en az
                 doktoralı olduğunu bilerek atamıştır; ayrıca belge
                 istemek editörün kendi kararına güvenmemek olurdu.
                 Kimin doğruladığı kayda geçer ve ekranda görünür. */ ?>
        <li><b><?= k_c('Doktoranız bir editörün adıyla doğrulanır', 'Your doctorate is verified in an editor\'s name') ?></b><?= k_c('Belge istenmez ve saklanmaz. Bir editör ya da baş editör sizi hakem olarak atadıysa doktoranız o atamayla doğrulanmış sayılır ve sorumluluğu atayan kişiye aittir; kendiniz gönüllü olduysanız çalışmanın editörü ya da bir baş editör adıyla onaylar. ORCID kaydınızdaki eğitim bilgisi ile kurumunuzun kamusal sayfası artık bir kapı değil, editöre kararında yardımcı olan destektir. Kimin doğruladığı kayda geçer ve görünür.', 'No document is asked for and none is stored. If an editor or a chief editor assigned you as a reviewer, your doctorate counts as verified by that assignment and the responsibility rests with the person who assigned you; if you volunteered, the editor of the work or a chief editor confirms it under their own name. The education record in your ORCID profile and your institution\'s public page are no longer a gate but support that helps the editor decide. Who verified it is recorded and visible.') ?></li>
        <li><b><?= k_c('Bir çalışmaya gönüllü olursunuz', 'You volunteer for a work') ?></b><?= k_c('Hakem aranan çalışmalar sayfasından seçersiniz ya da bir editörün davetini kabul edersiniz. Alanınızın çalışmanın alanıyla birebir örtüşmesi gerekmez; yöntem ya da veri hakemi olarak da katkı verebilirsiniz.', 'You choose from the works seeking reviewers, or accept an editor\'s invitation. Your field need not match the work\'s exactly; you may contribute as a methods or data reviewer.') ?></li>
        <li><b><?= k_c('Raporunuzu adınızla yazarsınız', 'You write your report under your name') ?></b><?= k_c('Rapor bir nitelik eşiğinden geçer: gerekçe taşımayan, metinde hiçbir yeri işaretlemeyen ya da ölçüt değerlendirmesi doldurulmamış bir rapor yayımlanır ve görünür kalır, ancak onay sayımına katılmaz.', 'The report passes a quality threshold: one carrying no reasoning, marking no place in the text or leaving the criteria assessment unfilled is published and remains visible, but does not enter the approval count.') ?></li>
      </ol>

      <?php
      /* ---- Tamganın kendisi ----
         Yukarıdaki dördüncü adım "tamgasını alır" diyor ama tamganın
         neye benzediğini göstermiyordu. Kimlik ilk kez, yazarın onu
         alacağı adımın hemen yanında görünür.

         Kod elle yazılmaz: denetim hanesi tg_denetim()'den gelir.
         Elle yazılsaydı okur onu deneyip geçersiz bulurdu. */
      $niYil = date('Y');
      $niKod = tg_ayar('tamga_on', 'KTG') . '-' . $niYil . '-00001-' . tg_denetim($niYil . '00001');
      ?>
      <div class="ni-tamga">
        <?= mh_muhur($niKod, 56, 'tmg-im') ?>
        <div>
          <span class="ni-tamga-kod"><?= k_esc($niKod) ?></span>
          <span class="ni-tamga-ack"><?= k_c(
            'Her çalışmanın kendi kimliği ve o kimlikten üretilen mührü. Mühür bir süstür, bilgi taşımaz; okunacak olan koddur. Kimliğin parçalarının ne anlama geldiği ilkeler sayfasında adım adım yazılıdır.',
            'Every work has its own identifier and a seal generated from it. The seal is an ornament and carries no information; what is to be read is the code. What the parts of the identifier mean is set out step by step on the policies page.'
          ) ?> <a href="/ilkeler.php#tamga"><?= k_c('Çözümleyici şeması', 'The decoder diagram') ?></a></span>
          <?php /* DOI CÜMLESİ TEK KAYNAKTAN VE ANCAK UYGULANIYORSA.
                   tg_zenodo_cumlesi() düzen kapalıyken BOŞ döner; bu
                   depoda en sık görülen kusur, uygulanmayan bir kuralın
                   duyurulmasıdır. Deneme evreninde cümle "sınanıyor"
                   der, "verilir" demez. */
          $niDoi = tg_zenodo_cumlesi(k_en()); if ($niDoi !== ''): ?>
          <span class="ni-tamga-ack"><?= k_esc($niDoi) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <div class="dizi dizi-2 ni-ikili">
        <div class="kart ni-kt ger">
          <h3><?= k_c('Gerekenler', 'What is required') ?></h3>
          <ul>
            <li><?= k_c('Doktora ya da eşdeğeri bir derece; belgeyle değil, bir editörün adıyla doğrulanmış.', 'A doctorate or its equivalent, verified not by a document but in an editor\'s name.') ?></li>
            <li><?= k_c('Kendi çalışmanıza hakem olamazsınız.', 'You may not review your own work.') ?></li>
            <li><?= k_c('Yakın zamanda sizi değerlendiren birinin çalışmasına hakem olamazsınız; karşılıklı hakemlik kapalıdır.', 'You may not review the work of someone who recently assessed you; reciprocal reviewing is closed.') ?></li>
          </ul>
        </div>
        <div class="kart ni-kt kaz">
          <h3><?= k_c('Kazandıklarınız', 'What you gain') ?></h3>
          <ul>
            <li><?= k_c('Raporunuz adınızla ve kalıcı olarak yayımlanır; emeğiniz görünür olur.', 'Your report is published under your name and permanently; your labour becomes visible.') ?></li>
            <li><?= k_c('Bir hakemlik tamamlamak yazarlık hakkını kazandırır.', 'Completing one review earns the right to submit as an author.') ?></li>
            <li><?= k_c('Kurul oylamalarında oy kullanabilirsiniz.', 'You may cast votes in panel decisions.') ?></li>
          </ul>
        </div>
      </div>
      <div class="kutu kutu-kut ni-son"><?= k_c(
        '<b>Anlaşmazlık olursa.</b> Yazar raporunuza itiraz ederse karar ne size ne yazara bırakılır: üç bağımsız kişinin oyuna gider. Oylama açıkken kimse başkasının oyunu göremez; üçüncü oyla oylama kapanır ve bütün oylar gerekçeleriyle açılır. ' . $oyE . ' geçerli oy kullanan kişi de yazarlık hakkını kazanır.',
        '<b>If there is disagreement.</b> Should the author object to your report, the decision is left to neither of you: it goes to the vote of three independent people. While the vote is open no one can see another\'s vote; the third vote closes it and every vote opens with its reasoning. Casting ' . $oyE . ' valid votes also earns the right to submit as an author.'
      ) ?></div>
      <div class="d-kume ni-git">
        <a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag('/bekleyen.php')) ?>"><?= k_c('Hakem aranan çalışmalar', 'Works seeking reviewers') ?></a>
        <a class="d d-sessiz d-kucuk" href="<?= k_esc(k_bag('/hakemlik.php')) ?>"><?= k_c('Hakemlik sürecinin tamamı', 'The full review process') ?></a>
      </div>
    </div>

    <!-- ---------- 03 YAZAR ---------- -->
    <div class="sek-pnl ni-pnl" role="tabpanel" id="nas-p2" aria-labelledby="nas-d2" tabindex="0" hidden>
      <?= ni_merdiven($basamaklar, 'yazar', $en) ?>
      <h2 id="yazar"><?= k_c('Yazar', 'Author') ?></h2>
      <p><?= k_c(
        'Yazarlık bu sistemde ilk basamak değil üçüncü basamaktır. Bir metni değerlendirmiş olan kişi, kendi metninin nasıl değerlendirileceğini de bilir; sıra bilinçli olarak böyle kuruldu.',
        'Authorship is not the first rung here but the third. Someone who has assessed another\'s text also understands how their own will be assessed; the sequence is deliberate.'
      ) ?></p>
      <?php /* Sıranın kendisi kalıcıdır, uygulanması kuruluş döneminde
               ertelenmiştir. İkisini ayırmak gerekir: erteleme yazılı ve
               tarihlidir, sessizce sürüp giden bir gevşeme değildir. */ ?>
      <?php if (tg_kurulus_donemi() && !tg_yazarlik_hakemlik_sarti()): ?>
      <div class="kutu kutu-lac"><?= k_esc(tg_yazarlik_kosulu_metni($en)) ?></div>
      <?php endif; ?>
      <ol class="ni-adim">
        <li><b><?= k_c('Başvurunuzu gönderirsiniz', 'You send your application') ?></b><?= tg_benzerlik_sarti()
              ? k_c('Yazar bilgileri, ORCID, benzerlik raporu, etik kurul durumu, veri ve kod beyanı, yapay zekâ beyanı. Hepsi çalışmayla birlikte yayımlanır.', 'Author details, ORCID, similarity report, ethics approval status, a data and code declaration and an AI declaration. All are published together with the work.')
              : k_c('Yazar bilgileri, ORCID, alan kodları, etik kurul durumu, veri ve kod beyanı, yapay zekâ beyanı. Hepsi çalışmayla birlikte yayımlanır.', 'Author details, ORCID, field codes, ethics approval status, a data and code declaration and an AI declaration. All are published together with the work.') ?></li>
        <li><b><?= k_c('Ön denetimden geçer', 'It passes a desk check') ?></b><?= k_c('Kapsam, biçim ve yayın ilkelerine uygunluk aranır. Kabul edilirse yalnızca o çalışma için düzenleme erişimi açılır.', 'Scope, format and compliance with the editorial policies are checked. If accepted, editing access is opened for that one work only.') ?></li>
        <li><b><?= k_c('Yolunuzu seçersiniz', 'You choose your track') ?></b><?= k_c('Hakemli yol ya da hakemsiz yazı. İkisi de aynı arşivdedir ama hiçbir zaman aynı etiketi taşımaz.', 'The peer reviewed track or the non reviewed track. Both live in the same archive but never carry the same label.') ?></li>
        <li><b><?= k_c('Yayımlanır ve tamgasını alır', 'It is published and receives its tamga') ?></b><?= k_c($kabS . ' olumlu rapor çalışmayı hakem onaylı yapar; ' . $retS . ' ret onu hakemsiz yazıya döndürür ama silmez. Süreç ne olursa olsun açıkta kalır.', $kabS . ' positive reports make the work reviewer approved; ' . $retS . ' rejections move it to the non reviewed track but do not delete it. Whatever the process, it remains in the open.') ?></li>
      </ol>
      <div class="dizi dizi-2 ni-ikili">
        <div class="kart ni-kt ger">
          <h3><?= k_c('Gerekenler', 'What is required') ?></h3>
          <ul>
            <?php /* KOŞULUN İKİ HÂLİ BURADA DEĞİL İŞLEVDE AYRILIR.
                     Eskiden bu satır kendi koşulunu kuruyor
                     (tg_kurulus_donemi() && !tg_yazarlik_hakemlik_sarti())
                     ve dönem dışı cümleyi ELLE yazıyordu. Yani işlevin
                     içindeki dallanma burada bir kez daha kuruluyordu:
                     iki kopya, bir gün ikiye ayrılır. İşlev bütün
                     dalları zaten biliyor; burada yalnız çağrılır. */ ?>
            <li><?= k_esc(tg_yazarlik_kosulu_metni($en)) ?></li>
            <?php /* Doktora şartı kalktığında bu madde bütünüyle değişir:
                     eski metin destekleyen araştırmacı düzenini anlatıyordu
                     ve o düzen artık yazarlık yolunda işlemiyor. Silinmedi,
                     koşula bağlandı; şart geri açılırsa aynen döner. */ ?>
            <li><?= !tg_yazarlik_doktora_sarti()
                     ? k_c('Doktora derecesi aranmaz; çalışmayı herkes gönderebilir. Her yazar için ORCID zorunludur. Gönderilen metni editör okur ve sisteme girip girmeyeceğine karar verir; istenmeyen posta, reklam, siyasi propaganda ve kişiyi küçük düşüren metin kabul edilmez.', 'No doctorate is required; anyone may submit a work. An ORCID is required for every author. An editor reads what is sent and decides whether it enters the system; spam, advertising, political propaganda and text that demeans a person are not accepted.')
                     : k_c('Yazarlarda en az doktora unvanı ve her yazar için ORCID. Doktora derecesi henüz olmayan bir araştırmacı, iki doktoralı ' . tg_destek_ad() . 'yla yazar olabilir: biri bu çalışmanın ortak yazarı, biri çalışmayla bağı olmayan bağımsız bir araştırmacı. Destekleyenler gerekçesiyle onaylar; onay gelmeden çalışma yayına alınmaz.', 'At least a doctoral title for authors, and an ORCID for each. A researcher who does not yet hold a doctorate may appear as an author with two ' . tg_destek_ad(true, true) . ' holding doctorates: one a co author of the work, one an independent researcher with no connection to it. They confirm with their reasoning; the work is not published until they do.') ?></li>
            <li><?= tg_benzerlik_sarti()
                  ? k_c('Benzerlik raporu ve gerekiyorsa etik kurul onayı.', 'A similarity report and, where required, ethics approval.')
                  : k_c('Gerekiyorsa etik kurul onayı. Benzerlik raporu istenmez.', 'Where required, ethics approval. No similarity report is asked for.') ?></li>
          </ul>
        </div>
        <div class="kart ni-kt kaz">
          <h3><?= k_c('Kazandıklarınız', 'What you gain') ?></h3>
          <ul>
            <li><?= k_c('Kalıcı adres ve devredilemez bir tamga.', 'A permanent address and a tamga of its own.') ?></li>
            <li><?= k_c('Hiçbir ücret ödemeden, gecikmesiz açık erişim.', 'Open access with no fee and no delay.') ?></li>
            <li><?= k_c('Okuma sayısı ve erişim verisi; metnin parmak izi.', 'Read counts and access data; a fingerprint of the text.') ?></li>
          </ul>
        </div>
      </div>
      <div class="kutu kutu-kut ni-son"><?= k_c(
        '<b>Açık sözlü olalım.</b> Burada yayımlanan bir çalışma bugün itibarıyla hiçbir dizinde taranmamaktadır ve akademik yükseltmede dizin puanı getirmez. Bunu gizlemiyoruz. Buraya çalışma göndermek, karşılık beklemeden vermektir.',
        '<b>Let us be plain.</b> Work published here is not, as of today, indexed anywhere and brings no index score in academic promotion. We do not conceal this. To send work here is to give without expecting return.'
      ) ?></div>
      <div class="d-kume ni-git">
        <a class="d d-vurgu d-kucuk" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Başvuru formunu aç', 'Open the application form') ?></a>
        <a class="d d-sessiz d-kucuk" href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></a>
      </div>
    </div>

    <!-- ---------- 04 EDİTÖR ---------- -->
    <div class="sek-pnl ni-pnl" role="tabpanel" id="nas-p3" aria-labelledby="nas-d3" tabindex="0" hidden>
      <?= ni_merdiven($basamaklar, 'editor', $en) ?>
      <h2 id="editor"><?= k_c('Editör', 'Editor') ?></h2>
      <p><?= k_c(
        'Editörlük bir unvan değil bir sorumluluktur: hakem atamak, editöryal not düşmek ve bunların hepsinin kaydının okuyucuya görünmesini kabul etmek anlamına gelir. Bu yüzden davetle değil, kayıtla kazanılır.',
        'Editorship is not a title but a duty: it means assigning reviewers, adding editorial notes, and accepting that the record of all of it is visible to the reader. It is therefore earned by record, not by invitation.'
      ) ?></p>
      <ol class="ni-adim">
        <li><b><?= k_c('Ölçüt tektir ve herkes için aynıdır', 'There is one criterion and it is the same for everyone') ?></b><?= k_c('Bu sistemde en az ' . $kY . ' yayın, bunlardan en az ' . $kO . ' tanesi hakem onaylı. Sayım kurul sayfasında, kayıtların üzerinden anlık yapılır ve isteyen doğrulayabilir.', 'At least ' . $kY . ' publications in this system, at least ' . $kO . ' of them reviewer approved. The count is made on the board page directly from the records, and anyone may verify it.') ?></li>
        <li><b><?= k_c('Ya da baş editörlerce eklenirsiniz', 'Or you are added by the chief editors') ?></b><?= k_c('Sistem yeni olduğu için başlangıçta atamalar baş editörlerce yapılır. Eklenen her editör kurul sayfasında adıyla görünür.', 'Because the system is new, assignments are made at the outset by the chief editors. Every editor added appears on the board page by name.') ?></li>
        <li><b><?= k_c('Hakem atar ve not düşersiniz', 'You assign reviewers and add notes') ?></b><?= k_c('Yapılan her atama, atayanın adı ve saatiyle birlikte çalışmanın sayfasında görünür. Bir editörün kim olduğu okurdan gizlenmez.', 'Every assignment appears on the work\'s page together with the name of the person who made it and the time. Who an editor is is not hidden from the reader.') ?></li>
        <li><b><?= k_c('Ayrıcalığınız olmaz', 'You gain no privilege') ?></b><?= k_c('Kurul üyesinin çalışması da aynı hakemlikten geçer, aynı ölçütlerle değerlendirilir. Bir editör kendi çalışmasına hakem atayamaz.', 'A board member\'s own work goes through the same review and is assessed by the same criteria. An editor may not assign reviewers to their own work.') ?></li>
      </ol>
      <div class="kutu kutu-kut ni-son"><?= k_c(
        '<b>Baş editörler.</b> Baş editörler kurucu editörlerdir ve değişmezler. Kurul sayfasındaki adların sırası her açılışta yeniden düzenlenir; böylece sıralama bir derece anlamı taşımaz. Kurul kararı kalıcıdır: sistemin kurucusu dâhil kimse bir kurul kararını geri alamaz.',
        '<b>The chief editors.</b> The chief editors are the founding editors and do not change. The order of the names on the board page is set anew each time it is opened, so that the ordering carries no sense of degree. A panel decision is permanent: no one, the founder of this system included, can reverse it.'
      ) ?></div>
      <div class="d-kume ni-git">
        <a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag('/kurul.php')) ?>"><?= k_c('Yayın kurulu', 'Editorial board') ?></a>
      </div>
    </div>

   </div>
  </div>
</section>
<?php k_son(); ?>
