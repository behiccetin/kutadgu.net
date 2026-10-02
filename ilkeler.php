<?php
/* =====================================================================
   KUTADGU - Yayın ilkeleri / Editorial policies
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/muhur.php';   /* tamga mührü: çözümleyici şemasında */

$benz    = (int)tg_ayar('benzerlik_ust', 15);
$benzTek = (int)tg_ayar('benzerlik_tek_ust', 5);
$kabul   = (int)tg_ayar('kabul_gecerli', 2);
$ret     = (int)tg_ayar('ret_donusum', 2);
$unvan   = (string)tg_ayar('asgari_unvan', 'Dr.');
$lis     = (string)tg_ayar('lisans', 'CC BY 4.0');
$lisU    = (string)tg_ayar('lisans_url', '');
$tamAd   = (string)tg_ayar('tamga_ad', 'Tamga');
$tamYol  = (string)tg_ayar('tamga_yol', 'tamga');
$tamOn   = (string)tg_ayar('tamga_on', 'bc');
$tamEski = (string)tg_ayar('tamga_eski', '10.00001');
$tamOnEski = (string)tg_ayar('tamga_on_eski', 'bc');
$kokAd   = (string)preg_replace('#^https?://#', '', tg_kok());

$ekBas = <<<CSS
<style>
/* Bu sayfanın kendine ait bir ızgarası YOKTUR. Sistemdeki tek düzeni
   kullanır: .blg (metin) + .blg-nav (içindekiler) + .blg-ek (ray
   kutuları). Daha önce burada dördüncü bir ızgara duruyordu ve 1200
   pikselden sonra içindekileri sola alıp sağı boş bırakıyordu; sayfanın
   ötekilerden başka görünmesinin sebebi buydu. Kendi ızgarasını yazan
   her sayfa, sistemin bütünlüğünden bir parça götürür.

   Aşağıda yalnızca bu belgeye özgü BİÇİM kuralları kalır: bölüm
   numaraları, ölçüt çizelgesi, bölüm ayraçları. Düzenle ilgili tek bir
   kural yoktur. */
/* Bölüm ayracı. On altı bölüm art arda okunduğu için nerede bitip
   nerede başladıkları çizgiyle ayrılır. Son bölüm ayraç almaz; ondan
   sonra gelen kutu ve düğmeler bölüm değildir, bu yüzden ölçüt
   :last-of-type olur. */
.ilk-gov section{scroll-margin-top:calc(var(--ust) + var(--b-5));padding-bottom:var(--b-6);
  border-bottom:1px solid var(--cizgi);margin-bottom:var(--b-6)}
.ilk-gov section:last-of-type{border-bottom:0}
/* Başlığın önündeki bölüm numarası: metnin kendisi değil, kaydıdır. */
.ilk-gov h2{display:flex;gap:var(--b-3);align-items:baseline}
.ilk-gov h2 .no{font-family:var(--mono);font-size:var(--y-2);color:var(--kut);font-weight:700;flex:none}
/* Ara başlıklar bölüm başlığından ayrılsın diye arayüz yüzüyle yazılır. */
.ilk-gov h3{font-size:var(--y-5);margin-top:1.5em}
.ilk-gov ul,.ilk-gov ol.md{padding-left:var(--b-5);display:grid;gap:var(--b-2);margin:0 0 1em}
.ilk-gov li{line-height:var(--sh-genis)}
/* Bölüm sonu notları gövdeden bir punto küçük okunur. */
.ilk-gov .metin-sonuk{font-size:var(--y-3)}
/* Metnin arasına giren düğme kümeleri paragraf ritmini sürdürür. */
.ilk-gov .satir{margin-block:1em}

/* Sayısal ölçüt şeridi: birkaç kısa değeri yan yana gösterir. */
.olcut{display:grid;gap:1px;background:var(--cizgi);border:1px solid var(--cizgi);border-radius:var(--r-3);
  overflow:hidden;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));margin:var(--b-4) 0}
.olcut div{background:var(--yuzey);padding:var(--b-3) var(--b-4)}
.olcut b{display:block;font-family:var(--serif);font-size:var(--y-7);line-height:var(--sh-sik)}
.olcut span{font-size:var(--y-2);color:var(--metin-2)}

/* Kabul edilmeyen ve edilen içerik listeleri. İm ve zemin, maddeyi
   okumadan önce hangi listede olduğunu söyler. */
ul.yasak,ul.serbest{padding-left:0;margin:var(--b-4) 0}
ul.yasak li,ul.serbest li{list-style:none;display:flex;gap:var(--b-3);align-items:flex-start;
  padding:var(--b-3) var(--b-4);border-radius:var(--r-2);font-size:var(--y-4);
  line-height:var(--sh-genis);background:var(--kirmizi-zemin)}
ul.yasak li::before{content:"\\00D7";color:var(--kirmizi);font-weight:800;font-size:var(--y-5);
  line-height:var(--sh-genis);flex:none}
ul.serbest li{background:var(--yesil-zemin)}
ul.serbest li::before{content:"\\2713";color:var(--yesil);font-weight:800;flex:none}

/* Kalıcı kimlik örneği: satır içinde ayrı bir yüzey olarak durur. */
.kod{font-family:var(--mono);font-size:var(--y-3);background:var(--yuzey-2);border:1px solid var(--cizgi);
  border-radius:var(--r-1);padding:var(--b-1) var(--b-2);white-space:nowrap}

/* ---- Tamga çözümleyici şeması ----
   Kimliğin dört parçası ayrı ayrı işaretlenir. Düz bir cümle
   ("dört parçadan oluşur") okura hangi hanenin hangi parça olduğunu
   göstermez; şemanın işi tam olarak budur.

   Sarma bilerek açıktır: 375 pikselte dört parça alt alta iner ve
   ayraçlar kaybolur. Tek satıra zorlansaydı ya yazı okunmayacak kadar
   küçülürdü ya da satır yatay kayardı; ikisi de dar ekranda şemayı
   şema olmaktan çıkarır. */
.tmg-sema{display:flex;flex-wrap:wrap;align-items:flex-start;gap:var(--b-1);
  margin:var(--b-3) 0;padding:var(--b-3);background:var(--yuzey-2);
  border:1px solid var(--cizgi);border-radius:var(--r-1)}
.tmg-p{display:flex;flex-direction:column;gap:2px;min-width:0}
.tmg-p b{font-family:var(--mono);font-size:var(--y-4);letter-spacing:.02em;
  color:var(--metin);font-weight:600}
.tmg-p i{font-style:normal;font-size:var(--y-1);line-height:var(--sh-dar);
  color:var(--metin-sonuk);max-width:11ch}
/* Ayraç şemanın parçası değil kimliğin parçasıdır; okunması gerekir,
   bu yüzden aria-hidden konmaz, yalnız soluklaştırılır. */
.tmg-ay{font-family:var(--mono);font-size:var(--y-4);color:var(--metin-sonuk);
  line-height:1.1;flex:none}
/* Mühür şemanın sağında durur ve dar ekranda alta iner. Süstür:
   bir şey söylemez, bu yüzden ekran okuyucudan gizlidir ve yanında
   her zaman kodun kendisi yazılıdır. */
.tmg-muhur{margin-left:auto;display:flex;align-items:center;gap:var(--b-2);
  color:var(--vurgu)}
.tmg-muhur span{font-size:var(--y-1);color:var(--metin-sonuk);max-width:16ch}
@media (max-width:520px){.tmg-muhur{margin-left:0;width:100%;
  padding-top:var(--b-2);border-top:1px solid var(--cizgi)}}
/* Denetim hanesinin hesabı: adımlar okunabilir kalsın diye ayrı satır. */
.tmg-hesap{font-family:var(--mono);font-size:var(--y-2);line-height:var(--sh-genis);
  overflow-x:auto;padding:var(--b-2) 0}
</style>
CSS;

$bolumler = [
    ['kapsam', 'Kapsam ve amaç',            'Scope and purpose'],
    ['yazar',  'Yazar ölçütleri',           'Author criteria'],
    ['icerik', 'İçerik ilkeleri',           'Content principles'],
    ['surec',  'Değerlendirme süreci',      'Evaluation process'],
    ['hakem',  'Hakemlerin yükümlülükleri', 'Reviewer obligations'],
    ['etik',   'Yayın etiği ve benzerlik',  'Publication ethics and similarity'],
    ['yz',     'Yapay zekâ kullanımı',      'Use of artificial intelligence'],
    ['telif',  'Telif ve lisans',           'Copyright and licence'],
    ['tamga',  $tamAd . ' kimliği',         $tamAd . ' identifier'],
    ['duzelt', 'Düzeltme ve geri çekme',    'Correction and retraction'],
    ['veri',   'Veri, gizlilik ve erişim',  'Data, privacy and access'],
    ['kurul',  'İtiraz ve kurul oylaması',  'Objection and the panel vote'],
    ['serh',   'Yayın sonrası şerh',        'Post publication commentary'],
    ['hesap',  'Hesaplar, roller ve metin güvenliği', 'Accounts, roles and text security'],
    ['ceviri', 'Çeviri ve diller',           'Translation and languages'],
    ['acik',   'Bu ilkelerin bilinen açıkları', 'Known weaknesses of these policies'],
];

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Yayın ilkeleri', 'Editorial policies'),
    'yol' => '/ilkeler.php',
    'ek_bas' => $ekBas,
]);
?>

<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
   <div>
    <span class="bas-ust"><?= k_c('Belge', 'Document') ?></span>
    <h1><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></h1>
    <p><?= k_c(
      'Bu belge, Kutadgu\'ya gönderilen çalışmaların hangi ölçütlerle değerlendirildiğini, hangi içeriklerin kabul edilmediğini ve süreçlerin nasıl yürütüldüğünü düzenler. Çalışma göndermek, burada yazılanların tamamının kabul edildiği anlamına gelir.',
      'This document sets out the criteria by which work submitted to Kutadgu is assessed, which content is not accepted, and how the processes are conducted. Submitting a work constitutes acceptance of everything set out here.'
    ) ?></p>
   </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg" data-belge>

    <!-- ---------- İÇİNDEKİLER ---------- -->
    <div class="blg-ray">
    <?php /* Sistemdeki öteki belgelerle aynı bileşen: geniş ekranda açık
             durur, dar ekranda katlanır. "open" yazılı olduğu için betik
             çalışmasa da liste açık gelir. On dört başlıklı bir listeyi
             telefonda metnin önüne dikmek, okumayı okumadan bitirir. */ ?>
    <details class="blg-nav" data-belge-nav open>
      <summary><?= k_c('İçindekiler', 'Contents') ?></summary>
      <ol id="icindekiler">
        <?php foreach ($bolumler as $i => $b): ?>
          <li><a href="#<?= k_esc($b[0]) ?>"><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?>. <?= k_esc(k_t(['tr' => $b[1], 'en' => $b[2]])) ?></a></li>
        <?php endforeach; ?>
      </ol>
    </details>

    <?php /* Rayın alt kutusu. Sağ ray hiçbir sayfada boş kalmaz: içindekiler
             listesi bittiği yerde okurun oradan gitmek isteyeceği yerler
             durur. Boş bir sütun, dolu bir sütundan daha çok göze batar. */ ?>
    <aside class="blg-ek" aria-label="<?= k_c('İlgili sayfalar', 'Related pages') ?>">
      <div class="blg-kutu">
        <b><?= k_c('Bu belge neyi bağlar', 'What this document binds') ?></b>
        <?= k_c(
          'Çalışma göndermek, burada yazılanların tamamını kabul etmek demektir. Kurallar değişirse değişiklik tarihiyle birlikte yazılır; geriye dönük uygulanmaz.',
          'Submitting a work means accepting everything set out here. If the rules change, the change is recorded with its date and is not applied retrospectively.'
        ) ?>
      </div>
      <div class="blg-kutu">
        <b><?= k_c('İlgili sayfalar', 'Related pages') ?></b>
        <a href="/bildiri.php"><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></a><br>
        <a href="/hakemlik.php"><?= k_c('Hakemlik süreci', 'The review process') ?></a><br>
        <a href="/yz.php"><?= k_c('Yapay zekâ ilkesi', 'Policy on AI') ?></a><br>
        <a href="/kurul.php"><?= k_c('Yayın kurulu', 'Editorial board') ?></a><br>
        <a href="/basvuru.php"><?= k_c('Çalışma gönderin', 'Submit a work') ?></a>
      </div>
    </aside>
    </div><!-- /blg-ray -->

    <!-- ---------- GÖVDE ---------- -->
    <div class="blg-ic ilk-gov" data-belge-govde>

      <!-- 01 KAPSAM -->
      <section id="kapsam">
        <h2><span class="no">01</span><?= k_c('Kapsam ve amaç', 'Scope and purpose') ?></h2>
        <p><?= k_c(
          'Kutadgu, özgün bilimsel çalışmaların açık erişimle yayımlanması amacıyla kurulmuş bağımsız bir yayın sistemidir. Herhangi bir bilim dalıyla sınırlı değildir; sosyal bilimlerden mühendisliğe, sağlık bilimlerinden beşerî bilimlere kadar bütün alanlardan çalışma kabul edilir. Ölçüt alanın kendisi değil, çalışmanın yöntemsel sağlamlığı, savının izlenebilirliği ve kaynaklarının denetlenebilirliğidir.',
          'Kutadgu is an independent publishing system established for the open access publication of original scholarly work. It is not confined to any single discipline; submissions are accepted from every field, from the social sciences to engineering, from health sciences to the humanities. The criterion is not the field itself but the methodological soundness of the work, the traceability of its argument and the verifiability of its sources.'
        ) ?></p>
        <p><?= k_c(
          'Sistem iki ayrı yayın yolu işletir. Birincisi, açık hakemlikten geçen çalışmaların yolu; ikincisi, hakem değerlendirmesi olmaksızın yayımlanan yazıların yolu. Her iki yoldaki çalışmalar aynı arşivde yer alır ancak birbirine karıştırılmayacak biçimde ve her sayfada açıkça etiketlenir.',
          'The system operates two separate publication tracks. The first is for work that undergoes open peer review; the second is for pieces published without review. Work on both tracks resides in the same archive but is labelled explicitly on every page so that the two are never conflated.'
        ) ?></p>
        <h3><?= k_c('Dizinlerde taranma durumu', 'Standing with regard to indexes') ?></h3>
        <p><?= k_c(
          'Burada hakem değerlendirmesinden geçerek yayımlanan çalışmalar, bugün itibarıyla TR Dizin, ESCI, Scopus ya da benzeri hiçbir dizinde taranmamaktadır. Bu, çalışma göndermeden önce bilinmesi gereken bir gerçektir ve gizlenmemektedir: burada yayımlanan bir çalışma akademik yükseltme ölçütlerinde bir dizin puanı getirmez. İleride dizinlerin ölçütleri karşılanabilir ve sistem taranmaya başlayabilir; bu bir taahhüt değil, yalnızca bir ihtimaldir.',
          'Work published here after peer review is not, as of today, indexed in TR Dizin, ESCI, Scopus or any comparable index. This is a fact that should be known before submitting and it is not concealed: work published here brings no index score in academic promotion criteria. The criteria of the indexes may in future be met and the system may come to be indexed; that is a possibility, not an undertaking.'
        ) ?></p>
        <p><?= k_c(
          'Yazarın buradan bekleyeceği şey bir sıralama ya da bir rozet değildir. Beklemesi gereken şudur: çalışmasının hiçbir engel olmaksızın okunması, aldığı hakem raporlarıyla birlikte açıkta durması, kalıcı bir adresle korunması ve ulaşabildiği herkese ücretsiz açık kalması. Bu sistemin amacı bir yükseltme aracı olmak değil, bütün insanlık için kimsenin izni ya da ödemesi gerekmeden erişilebilen bir bilimsel arşiv oluşturmaktır.',
          'What an author should expect from this place is not a ranking or a badge. What they should expect is this: that their work is read without any barrier, that it stands in the open together with the referee reports it received, that it is preserved at a permanent address, and that it remains free of charge to everyone it can reach. The purpose of this system is not to serve as an instrument of promotion but to build a scholarly archive for all humanity, reachable without anyone\'s permission or payment.'
        ) ?></p>

        <h3><?= k_c('Kabul edilen çalışma türleri', 'Accepted types of work') ?></h3>
        <ul>
          <li><?= k_c('Özgün araştırma makalesi', 'Original research article') ?></li>
          <li><?= k_c('Derleme ve alanyazın taraması', 'Review article and literature survey') ?></li>
          <li><?= k_c('Kuramsal tartışma yazısı', 'Theoretical discussion paper') ?></li>
          <li><?= k_c('Yöntem notu ve veri betimlemesi', 'Methods note and data description') ?></li>
          <li><?= k_c('Alan notu, saha gözlemi ve olgu sunumu', 'Field note, field observation and case report') ?></li>
          <li><?= k_c('Kitap değerlendirmesi ve çeviri', 'Book review and translation') ?></li>
        </ul>
      </section>

      <!-- 02 YAZAR ÖLÇÜTLERİ -->
      <section id="yazar">
        <h2><span class="no">02</span><?= k_c('Yazar ölçütleri', 'Author criteria') ?></h2>
        <?php /* DOKTORA ŞARTI 13 AĞUSTOS 2026'DA KURUL KARARIYLA KALDIRILDI.
                 Metin silinmedi, koşula bağlandı: ayar.php'deki
                 'yazarlik_doktora_sarti' true yapılırsa eski kural ve eski
                 cümle birlikte geri gelir. Bir ilkeler sayfasının en kötü
                 hâli, kaldırılmış bir kuralı duyurmaya devam etmesidir. */ ?>
        <?php if (!tg_yazarlik_doktora_sarti()): ?>
        <p><?= k_c(
          'Yazarlık için doktora derecesi aranmaz. Çalışmayı herkes gönderebilir. Bu, 13 Ağustos 2026 tarihli kurul kararıdır ve gerekçesi açıktır: doktorası olmayan çok değerli araştırmacılar vardır, bir çalışmanın ölçüsü yazarının unvanı değil yönteminin sağlamlığı ve kaynaklarının denetlenebilirliğidir.',
          'No doctoral degree is required for authorship. Anyone may submit a work. This is a board decision of 13 August 2026, and its reasoning is plain: there are researchers of great value who hold no doctorate, and the measure of a work is not its author\'s title but the soundness of its method and the verifiability of its sources.'
        ) ?></p>
        <p><?= k_c(
          'Denetim kaldırılmadı, yeri değiştirildi. Eskiden kapı unvandaydı ve metne bakılmadan yazarın derecesine bakılıyordu; artık kapı <b>editördedir</b>. Gönderilen her metni bir editör okur ve sisteme girip girmeyeceğine karar verir. Kabul edilen çalışma önce hakemsiz olarak yayımlanır; yazar dilerse hakem aranmasını ister, dilerse hakemsiz bırakır. Hakemsiz olmak bir eksiklik değil bir durumdur ve çalışmanın sayfasında öyle yazar.',
          'The check was not removed but moved. The gate used to be the title: the author\'s degree was examined instead of the text. The gate is now <b>the editor</b>. An editor reads every text that is sent and decides whether it enters the system. An accepted work is first published without review; the author may then ask for reviewers to be sought, or leave it unreviewed. Being unreviewed is a state, not a shortcoming, and the work\'s page says so.'
        ) ?></p>
        <p><?= k_c(
          'Editörün reddedebileceği şeyler kurul kararında sayılmıştır ve bu sayfanın 03. bölümünde zaten yazılıdır: istenmeyen posta ve reklam, siyasi propaganda, kişiyi küçük düşüren metin. Doktora şartının kalkması bu kuralların hiçbirini gevşetmez.',
          'What an editor may refuse is set out in the board decision and is already written in section 03 of this page: spam and advertising, political propaganda, and text that demeans a person. The removal of the doctoral requirement relaxes none of these.'
        ) ?></p>
        <p><?= k_c(
          '<b>Hakemlik için doktora aranmaya devam eder</b> ve bu koşul gevşemez. Bir metni değerlendirmek, onu yazmaktan başka bir sorumluluktur: rapor adla yayımlanır ve bir çalışmanın kaderini etkiler.',
          '<b>A doctorate is still required in order to review</b>, and that condition does not relax. Assessing a text is a different responsibility from writing one: the report is published under a name and affects the fate of a work.'
        ) ?></p>
        <?php else: ?>
        <p><?= k_c(
          'Kural olarak, birinci yazarın ve bütün ortak yazarların en az doktora derecesine sahip olması aranır. Bu koşul tek bir yazar için değil, yazar listesindeki herkes için ayrı ayrı geçerlidir; ikinci, üçüncü ve sonraki yazarlar da bu ölçütü karşılar.',
          'As a rule, the first author and all co authors are required to hold at least a doctoral degree. This condition applies to every person on the author list individually, not to one author alone; second, third and subsequent authors meet it too.'
        ) ?></p>
        <?php endif; ?>
        <?php /* BURADA BİR SAYI KUTUSU YOKTU, OLMAMALIYDI DA.
                 .olcut kutusu SAYI göstermek için var: "2 olumlu rapor",
                 "%15 benzerlik". Bir sayı büyük puntoyla yazıldığında
                 okurun aklında kalır, çünkü ölçülmüş bir eşiktir.
                 Burada ise "Dr.", "ORCID" ve "Scopus" sözcükleri aynı
                 kutuya konmuştu; üç kelime, üç santimlik serif harflerle
                 sayfanın ortasında duruyor ama hiçbir şey ölçmüyordu.
                 Biçim, söylediği şeyden büyük olduğunda süse döner.

                 Scopus satırı büsbütün kalktı: Scopus ticari bir veri
                 tabanıdır, birçok alanda karşılığı yoktur ve zaten
                 isteğe bağlıdır. İsteğe bağlı bir alanı zorunlu ORCID
                 ile yan yana, aynı ağırlıkta göstermek onu koşul gibi
                 okutuyordu. Bilgi kaybolmadı: aşağıdaki cümlede, hak
                 ettiği ağırlıkla duruyor. */ ?>
        <p><?= k_c(
          (tg_yazarlik_doktora_sarti() ? 'Aranan asgari unvan <b>' . k_esc($unvan) . '</b>, ve her' : 'Her')
            . ' yazar için <b>ORCID zorunludur</b>: ORCID, kimliği doğrulanabilir kılan ve bu sistemde adın kime ait olduğunu belirleyen tek numaradır. Scopus Author ID girilebilir ama istenmez; Scopus ticari bir veri tabanıdır ve birçok alanda araştırmacıların karşılığı orada bulunmaz.',
          (tg_yazarlik_doktora_sarti() ? 'The minimum title required is <b>' . k_esc($unvan) . '</b>, and an' : 'An')
            . ' <b>ORCID is mandatory</b> for every author: the ORCID is the one number that makes an identity verifiable and settles whom a name belongs to in this system. A Scopus Author ID may be entered but is not asked for; Scopus is a commercial database and does not cover researchers in every field.'
        ) ?></p>

        <?php /* DESTEKLEYEN ARAŞTIRMACI YOLU, DOKTORA ŞARTININ YAN
                 KAPISIYDI. Şart kalkınca kapının kendisi kalktı; yan
                 kapıyı açık bırakmak, olmayan bir engelin etrafından
                 dolaşma yolunu anlatmak olurdu. Bölüm silinmedi, koşula
                 bağlandı: düzenek kodda duruyor ve şart geri açılırsa
                 hem kural hem bu anlatım aynen döner. Yayımlanmış
                 çalışmaların sayfalarındaki destek kayıtları ise yerinde
                 kalır; onlar gerçekten olmuş şeylerdir. */ ?>
        <?php if (tg_yazarlik_doktora_sarti()): ?>
        <h3><?= k_c('Doktora derecesi olmayan araştırmacı: yazarlık desteği', 'A researcher without a doctorate: the supporting researcher rule') ?></h3>
        <p><?= k_c(
          'Doktora şartı, niteliği korumak için kondu. Ama olduğu gibi bırakıldığında korunmak istenen şeyi değil yalnızca kapıyı koruyor; henüz derecesini almamış bir araştırmacının, kendi emeğiyle ürettiği bir çalışmada adının bulunmasını da engelliyordu. Bir çalışmanın verisini toplayan, çözümlemesini yapan, yöntemini kuran kişinin adının o çalışmada bulunmaması, yayın etiğine aykırıdır. Bu yüzden şartın yanına bir yol açıldı.',
          'The doctoral requirement was set in order to protect quality. Left as it stood, however, it protected the gate rather than the thing the gate was for: it also kept a researcher who has not yet taken that degree from appearing on work they themselves produced. For the person who gathered a study\'s data, ran its analysis and built its method not to appear on it is contrary to publication ethics. So a path was opened alongside the requirement.'
        ) ?></p>
        <p><?= k_c(
          'Doktora derecesi olmayan bir araştırmacı, <b>iki doktoralı araştırmacının adıyla sorumluluk üstlenmesi</b> durumunda yazar olarak yer alabilir. Bu iki kişiden biri aynı çalışmanın doktoralı ortak yazarı olmak zorundadır: metni bilen kişidir. Diğeri çalışmayla hiçbir bağı olmayan bir doktoralı olmak zorundadır: dışarıdan bir gözdür. Böylece bir danışman kendi öğrencisini tek başına içeri alamaz; her durumda çalışmayla ilgisi olmayan biri de adını ortaya koyar.',
          'A researcher without a doctorate may appear as an author where <b>two researchers holding doctorates take responsibility under their own names</b>. One of the two must be a co author of the same work holding a doctorate: someone who knows the text. The other must hold a doctorate and have no connection with the work: an outside eye. In this way a supervisor cannot admit their own student alone; in every case someone unconnected with the work also puts their name forward.'
        ) ?></p>
        <p><?= k_c(
          'Yazarlık desteği bir imza işidir. ' . tg_destek_ad_bas(false, true) . 'ın her birine kendi bağlantısı gönderilir; kararını kendisi verir ve gerekçesini kendisi yazar. Onay gelmeden çalışma yayına alınmaz. Destekleyenin adı, sıfatı ve gerekçesi çalışmanın sayfasında kalıcı olarak görünür; destek reddedilirse ret de gerekçesiyle kayda geçer. Kimsenin adı, haberi olmadan bir sorumluluğun altına yazılmaz.',
          'Authorship support is a matter of signature. Each supporting researcher is sent their own link; each decides for themselves and writes their own reasoning. The work is not published until the confirmation arrives. Their name, capacity and reasoning appear permanently on the work\'s page; where support is declined, the refusal is recorded with its reasoning too. No one\'s name is placed under a responsibility without their knowledge.'
        ) ?></p>
        <p><?= k_c(
          tg_destek_ad_bas() . ', çalışmanın bilimsel niteliğine sahip çıkmaz; onu hakem raporları değerlendirir. Yazarlık desteği, <b>katkının gerçekliğine</b> ilişkindir: adı geçen araştırmacının o çalışmaya gerçekten emek verdiğini ve adının orada bulunmayı hak ettiğini bildirir.',
          'A supporting researcher does not attest to the scientific quality of the work; the referee reports assess that. The support concerns <b>the reality of the contribution</b>: it states that the named researcher genuinely worked on the study and that their name deserves to be on it.'
        ) ?></p>
        <p><?= k_c(
          'Bu yolla yazar olan araştırmacının yapamayacağı üç şey vardır: <b>hakem raporu yazamaz</b>, <b>kurul oylamasında oy kullanamaz</b> ve <b>tek yazar olarak çalışma gönderemez</b>. Bunların üçü de doktora derecesinin belgeyle doğrulanmasına bağlıdır. Araştırmacı doktora belgesini sunup doğrulattığı gün bu sınırların tamamı kendiliğinden kalkar; o günden sonra hem yazar hem hakem olur. Destek kaydı ise çalışmanın sayfasında kalmayı sürdürür, çünkü o gün gerçekten olmuş bir şeydir ve yayımlanmış bir kaydı geriye dönük düzeltmek kaydın kendisini bozar.',
          'There are three things a researcher admitted by this path may not do: <b>write a referee report</b>, <b>cast a vote in a panel vote</b>, and <b>submit a work as sole author</b>. All three depend on a doctoral degree verified by document. On the day the researcher submits and verifies that document, every one of these limits falls away of itself; from then on they are both author and reviewer. The support record, however, stays on the work\'s page, because it is something that actually happened on that day, and amending a published record after the fact damages the record itself.'
        ) ?></p>
        <?php endif; ?>
        <h3><?= k_c('Kimlik numaraları', 'Identifier numbers') ?></h3>
        <p><?= k_c(
          'Her yazar için geçerli bir ORCID kimliği bildirilmesi zorunludur; ORCID numarası biçim ve denetim hanesi bakımından sistem tarafından doğrulanır, geçersiz numaralar kabul edilmez. Bunun amacı, aynı adı taşıyan araştırmacıların ayırt edilmesini ve yayının yazarına kalıcı biçimde bağlanmasını sağlamaktır. Scopus yazar kimliği isteğe bağlıdır: Scopus ticari bir veri tabanıdır, her bilim alanını aynı ölçüde kapsamaz ve orada kimliği bulunmayan bir araştırmacının önü bu yüzden kesilmez.',
          'A valid ORCID identifier must be supplied for every author; the number is validated by the system for format and check digit, and invalid numbers are not accepted. The purpose is to distinguish researchers who share a name and to bind the publication permanently to its author. A Scopus author identifier is optional: Scopus is a commercial database, it does not cover every field equally, and a researcher who has no entry there is not shut out for that reason.'
        ) ?></p>
        <h3><?= k_c('Yazarlık sorumluluğu', 'Responsibility for authorship') ?></h3>
        <p><?= k_c(
          'Yazar listesinde yer alan herkesin çalışmaya fiilen katkı vermiş olması beklenir. Katkısı bulunmayan kişilerin listeye eklenmesi ve katkı vermiş kişilerin listeden çıkarılması yayın etiğine aykırıdır. Yazar sırası ve katkı paylaşımı yazarlar arasında çözülmesi gereken bir konudur; sistem bu konuda taraf olmaz.',
          'Everyone named on the author list is expected to have made an actual contribution to the work. Adding people who did not contribute, or omitting people who did, is contrary to publication ethics. The order of authors and the division of credit are matters to be settled among the authors; the system does not take sides.'
        ) ?></p>
      </section>

      <!-- 03 İÇERİK İLKELERİ -->
      <section id="icerik">
        <h2><span class="no">03</span><?= k_c('İçerik ilkeleri', 'Content principles') ?></h2>
        <p><?= k_c(
          'Kutadgu bilimsel bir yayın alanıdır; kamuoyu oluşturma, kanaat yayma ya da tanıtım aracı değildir. Aşağıdaki içerikler, bilimsel biçime bürünmüş olsalar dahi kabul edilmez ve yayımlandıktan sonra saptanmaları hâlinde geri çekilir.',
          'Kutadgu is a scholarly venue; it is not an instrument for shaping public opinion, spreading conviction or promotion. The following content is not accepted even when cast in scholarly form, and is retracted if identified after publication.'
        ) ?></p>
        <ul class="yasak">
          <li><?= k_c(
            'Siyasi görüş bildiren, bir siyasi hareketi, partiyi ya da yönetimi destekleyen veya hedef alan yazılar.',
            'Writing that expresses a political position, or that supports or targets a political movement, party or administration.'
          ) ?></li>
          <li><?= k_c(
            'Reklam amacı güden, belirli bir ürünü, hizmeti, kurumu ya da kişiyi tanıtan içerikler.',
            'Content with a promotional purpose that advertises a particular product, service, institution or person.'
          ) ?></li>
          <li><?= k_c(
            'Belirli bir topluluğu, etnik grubu, inanç grubunu, cinsiyeti ya da mesleği öven veya yeren; üstünlük veya aşağılık atfeden anlatımlar.',
            'Accounts that praise or condemn a particular community, ethnic group, faith group, gender or profession, or that ascribe superiority or inferiority to them.'
          ) ?></li>
          <li><?= k_c(
            'Adı anılan kurum ya da kişileri hedef gösteren, itibar zedeleyici ya da kişisel husumet taşıyan metinler.',
            'Texts that single out named institutions or individuals, damage reputation, or carry personal animosity.'
          ) ?></li>
          <li><?= k_c(
            'Bilimsel dayanağı olmayan iddiaları kanıtlanmış gerçek gibi sunan, sağlık ya da güvenlik bakımından zarar doğurabilecek metinler.',
            'Texts that present claims without scientific basis as established fact, and that may cause harm to health or safety.'
          ) ?></li>
        </ul>
        <p><?= k_c(
          'Buna karşılık, kurumların ve kişilerin adı anılmadan yürütülen çözümlemeler yayımlanabilir. Bir kamu politikasının sonuçlarını ölçen, bir uygulamanın etkisini sınayan ya da bir sektörün yapısını inceleyen çalışmalar, bulgularını veriye dayandırdıkları ve belirli bir tarafı üstün ya da suçlu göstermedikleri sürece bu sistemde yerini bulur.',
          'Analyses conducted without naming institutions or individuals may, by contrast, be published. Work that measures the outcomes of a public policy, tests the effect of a practice or examines the structure of a sector has a place in this system, provided its findings rest on data and it does not cast any party as superior or culpable.'
        ) ?></p>
        <ul class="serbest">
          <li><?= k_c(
            'Bir politikanın ya da uygulamanın sonuçlarını veriyle ölçen çözümlemeler; kurum adı anılmaksızın.',
            'Analyses that measure the outcomes of a policy or practice with data, without naming the institution.'
          ) ?></li>
          <li><?= k_c(
            'Bir alandaki eğilimleri, dağılımları ve yapısal sorunları betimleyen incelemeler.',
            'Studies describing trends, distributions and structural problems in a field.'
          ) ?></li>
          <li><?= k_c(
            'Yerleşik bir kurama ya da yönteme yöneltilen, gerekçesi kaynaklarla desteklenen eleştiriler.',
            'Criticism directed at an established theory or method, supported by referenced grounds.'
          ) ?></li>
        </ul>
        <p class="metin-sonuk"><?= k_c(
          'Bir metnin bu ilkelere uyup uymadığı tartışmalıysa, karar yayın kurulunca verilir ve gerekçesi yazara yazılı olarak bildirilir.',
          'Where it is disputable whether a text complies with these principles, the decision is taken by the editorial board and its grounds are communicated to the author in writing.'
        ) ?></p>
      </section>

      <!-- 04 DEĞERLENDİRME SÜRECİ -->
      <section id="surec">
        <h2><span class="no">04</span><?= k_c('Değerlendirme süreci', 'Evaluation process') ?></h2>
        <h3><?= k_c('Ön denetim', 'Desk check') ?></h3>
        <p><?= tg_benzerlik_sarti()
          ? k_c(
            'Gelen her başvuru önce biçimsel ve ilkesel denetimden geçirilir. Yazar ölçütlerinin karşılanıp karşılanmadığı, kimlik numaralarının geçerliliği, benzerlik raporunun eşikleri aşıp aşmadığı ve içeriğin yayın ilkelerine uygunluğu bu aşamada incelenir. Ön denetimi geçemeyen başvurular gerekçesiyle birlikte yazara döner.',
            'Every submission first undergoes a check of form and principle. Whether the author criteria are met, whether the identifiers are valid, whether the similarity report exceeds the thresholds and whether the content complies with the editorial policies are all examined at this stage. Submissions that do not pass are returned to the author with reasons.')
          : k_c(
            'Gelen her başvuru önce biçimsel ve ilkesel denetimden geçirilir. Kimlik numaralarının geçerliliği, alan kodlarının yerindeliği ve içeriğin yayın ilkelerine uygunluğu bu aşamada incelenir. Ön denetimi geçemeyen başvurular gerekçesiyle birlikte yazara döner.',
            'Every submission first undergoes a check of form and principle. Whether the identifiers are valid, whether the field codes are apt and whether the content complies with the editorial policies are all examined at this stage. Submissions that do not pass are returned to the author with reasons.') ?></p>
        <h3><?= k_c('Açık hakemlik', 'Open peer review') ?></h3>
        <p><?= k_c(
          'Hakemli yolu seçen çalışmalar en az iki hakeme gönderilir. Bu sistemde kör hakemlik uygulanmaz: hakem yazarın kim olduğunu bilir, yazar da hakemin kim olduğunu bilir ve rapor, hakemin adıyla birlikte çalışmanın sayfasında yayımlanır. Böylece değerlendirmenin kendisi de denetlenebilir bir belgeye dönüşür.',
          'Work on the reviewed track is sent to at least two reviewers. Blind review is not practised here: the reviewer knows who the author is, the author knows who the reviewer is, and the report is published on the work\'s page together with the reviewer\'s name. The assessment thereby becomes a verifiable document in its own right.'
        ) ?></p>
        <div class="olcut">
          <div><b><?= (int)$kabul ?></b><span><?= k_c('olumlu rapor: hakem onaylı sayılır', 'positive reports: counts as reviewer approved') ?></span></div>
          <div><b><?= (int)$ret ?></b><span><?= k_c('ret: çalışma hakemsiz yazıya döner', 'rejections: the work moves to the non reviewed track') ?></span></div>
        </div>
        <p><?= k_c(
          'Hakemler dört karardan birini verir: kabul, küçük revizyon, büyük revizyon, ret. Revizyon istenen çalışmalarda yazar düzeltilmiş metni yükler ve aynı hakem yeniden değerlendirir. İki ret alan çalışma sistemden silinmez; hakemsiz yazı olarak, aldığı raporlarla birlikte açık kalır. Bunun nedeni, olumsuz değerlendirmenin de bilimsel kaydın parçası olmasıdır.',
          'Reviewers issue one of four decisions: accept, minor revision, major revision, reject. Where revision is requested the author uploads the corrected text and the same reviewer assesses it again. A work receiving two rejections is not deleted from the system; it remains open as a non reviewed piece together with the reports it received. The reason is that a negative assessment is also part of the scholarly record.'
        ) ?></p>
        <h3><?= k_c('Hakemi kim seçer', 'Who chooses the reviewer') ?></h3>
        <p><?= k_c(
          'Yazarın kendi hakemini önermesi bu tür sistemlerin en bilinen açığıdır: birbirini onaylayan kapalı bir halka doğurabilir. Bu yüzden burada üç kural birlikte işler. Birincisi, bir çalışmanın <b>hakem onaylı</b> sayılabilmesi için olumlu raporlardan en az birinin yazarın önermediği bir hakemden gelmesi gerekir. İkincisi, karşılıklı hakemlik engellenir: bir kişinin çalışmasını yakın zamanda değerlendiren yazar, o kişiyi kendi çalışmasına hakem olarak öneremez. Üçüncüsü, ortak yayın yapmış kişiler birbirinin hakemi olamaz.',
          'An author proposing their own reviewer is the best known weakness of systems like this: it can produce a closed circle of mutual approval. Three rules therefore work together here. First, for a work to count as <b>reviewer approved</b>, at least one of the positive reports must come from a reviewer the author did not propose. Second, reciprocal reviewing is blocked: an author who recently assessed someone\'s work may not propose that person as a reviewer of their own. Third, people who have published together may not review one another.'
        ) ?></p>
        <p><?= k_c(
          'Her hakemin nasıl atandığı okuyucuya gösterilir: yazarın önerisiyle mi, editör atamasıyla mı, baş editör atamasıyla mı, yoksa kişinin kendi isteğiyle mi. Atamayı yapanın adı ve atamanın günü ile saati kayda geçer ve sonradan değiştirilemez.',
          'How each reviewer was assigned is shown to the reader: at the author\'s proposal, by an editor, by a chief editor, or at the person\'s own offer. The name of whoever made the assignment, and its day and hour, are recorded and cannot afterwards be altered.'
        ) ?></p>

        <h3><?= k_c('Gönüllü hakemlik', 'Volunteering to review') ?></h3>
        <p><?= k_c(
          'Bir yazar çalışmasını yükledikten sonra hakem beklemek zorunda değildir. Hakemlik koşullarını taşıyan herkes, hakem aranan çalışmalar sayfasından o çalışmayı değerlendirmeye gönüllü olabilir. Başvuru yazara değil editöre gider; onaylanırsa hakem olarak atanır. Gönüllülük yazarın seçimi olmadığı için bu değerlendirme <b>bağımsız</b> sayılır.',
          'An author who has uploaded a work does not have to wait for a reviewer. Anyone who meets the reviewer conditions may volunteer, from the page of works seeking reviewers, to assess it. The application goes to an editor rather than to the author; if approved, the volunteer is assigned as a reviewer. Because volunteering is not the author\'s choice, such an assessment counts as <b>independent</b>.'
        ) ?></p>

        <h3><?= k_c('Raporun nitelik eşiği', 'The quality threshold for a report') ?></h3>
        <p><?= k_c(
          'Bu sistemde hakemlik yapmak yazarlığın da kapısı olduğu için, hızlı ve içeriksiz rapor yazma eğilimi doğabilir. Buna karşı bir eşik konmuştur: yeterli gerekçe taşımayan, metinde hiçbir yeri işaretlemeyen ya da ölçüt değerlendirmesi doldurulmamış bir rapor yayımlanır ve görünür kalır, ancak onay sayımına ve hakemlik kaydına katılmaz. Eşiğin karşılanıp karşılanmadığı hakeme, raporu göndermeden önce gösterilir.',
          'Because reviewing here is also the door to authorship, a tendency to write quick and empty reports can arise. A threshold stands against it: a report that carries insufficient reasoning, marks no passage in the text, or leaves the criteria assessment unfilled is published and remains visible, but does not count towards approval or towards the reviewing record. Whether the threshold is met is shown to the reviewer before the report is sent.'
        ) ?></p>

        <h3><?= k_c('Kuruluş dönemi istisnası', 'The founding period exception') ?></h3>
        <p><?= k_c(
          'Sistem kurulurken, kuralların gerçekten işleyip işlemediğini görmenin tek yolu onları çalıştırmaktı. Bu yüzden ilk çalışmalar deneme sırasında yayımlandı ve bugün yürürlükte olan kuralların tamamını karşılamaz. Bu çalışmalar arşivden çıkarılmadı ve kayıtları geriye dönük düzeltilmedi: yayımlanmış bir kaydı sonradan değiştirmek ya da sessizce kaldırmak, kaydın kendisini bozar. Bunun yerine istisna açıkça tanındı. Her birinin sayfasında, hangi koşulda yayımlandığını anlatan kalıcı bir kuruluş dönemi kaydı görünür. Bu istisna yalnızca adı ayar dosyasında yazılı olan ilk çalışmalar için geçerlidir ve genişletilmeyecektir; kuruluş dönemi bir kez yaşanır.',
          'While the system was being built, the only way to see whether its rules actually worked was to run them. The first works were therefore published during testing and do not satisfy every rule now in force. They were not removed from the archive, and their records were not corrected retrospectively: to alter a published record after the fact, or to remove it quietly, damages the record itself. The exception was acknowledged openly instead. On each of their pages a permanent founding period record appears, setting out the conditions under which the work was published. The exception applies only to those first works named in the configuration file and will not be extended; a founding period happens once.'
        ) ?></p>

        <h3><?= k_c('Hakemsiz yayın', 'Publication without review') ?></h3>
        <p><?= k_c(
          'Yazar, çalışmasının hakem sürecine girmesini istemeyebilir. Bu durumda metin ön denetimden geçtikten sonra "hakemsiz yazı" etiketiyle yayımlanır. Etiket, çalışmanın listelerde, sayfasında ve paylaşım bağlantılarında görünür durumda kalır. Yazar sonradan aynı metnin hakem sürecine alınmasını isteyebilir.',
          'An author may prefer not to enter the review process. In that case the text is published with a "non reviewed" label once it has passed the desk check. The label remains visible in listings, on the work\'s page and in sharing links. The author may later ask for the same text to enter the review process.'
        ) ?></p>
      </section>

      <!-- 05 HAKEM YÜKÜMLÜLÜKLERİ -->
      <section id="hakem">
        <h2><span class="no">05</span><?= k_c('Hakemlerin yükümlülükleri', 'Reviewer obligations') ?></h2>
        <p><?= k_c(
          'Hakemlik gönüllü bir görevdir ve ücretlendirilmez. Hakem olarak görev alan kişilerden en az doktora derecesi ve değerlendirdikleri konuda yayımlanmış çalışma beklenir. Raporun adıyla yayımlanacak olması, hakemin görüşünü yumuşatmasının değil, gerekçelendirmesinin sebebi olmalıdır.',
          'Reviewing is a voluntary duty and is not remunerated. Reviewers are expected to hold at least a doctoral degree and to have published work in the subject they assess. That the report will be published under their name should be a reason to justify an opinion, not to soften it.'
        ) ?></p>
        <ul>
          <li><?= k_c('Rapor, metnin savını, yöntemini ve kaynak kullanımını ayrı ayrı ele almalıdır.', 'The report should address the argument, the method and the use of sources separately.') ?></li>
          <li><?= k_c('Her eleştiri, metindeki yerine işaret ederek somutlaştırılmalıdır.', 'Each criticism should be made concrete by pointing to its place in the text.') ?></li>
          <li><?= k_c('Yazarın kişiliğine, kurumuna ya da geçmişine ilişkin değerlendirme yapılmaz.', 'No assessment is made of the author\'s person, institution or past.') ?></li>
          <li><?= k_c('Hakem, kendi çalışmasının atıf almasını isteme amacıyla kaynak dayatamaz.', 'A reviewer may not impose references with a view to securing citations to their own work.') ?></li>
          <li><?= k_c('Çıkar çatışması bulunan hakem görevi kabul etmez ve durumu bildirir.', 'A reviewer with a conflict of interest declines the task and reports the situation.') ?></li>
          <li><?= k_c('Değerlendirme süresi içinde metin üçüncü kişilerle paylaşılmaz.', 'The text is not shared with third parties during the review period.') ?></li>
        </ul>

        <h3><?= k_c('Hakem olmanın koşulu', 'The condition for becoming a reviewer') ?></h3>
        <p><?= k_c(
          'Aranan tek nitelik, doktora ya da eşdeğeri bir dereceye sahip olmaktır; bilim dalı ayrımı gözetilmez. Bu nitelik <b>belgeyle değil, adıyla sorumluluk üstlenen bir editörle</b> doğrulanır ve üç yol vardır. <b>Bir:</b> bir editör ya da baş editör kişiyi hakem olarak davet ettiyse, doğrulamayı daveti gönderen yapmış olur. <b>İki:</b> kişi bir çalışmaya kendisi hakem olmak için başvurduysa, başvuruyu kabul eden editör ya da bir baş editör doğrular. <b>Üç:</b> kimse adıyla arkasında durmadıysa kişi aday hakem olarak kalır ve rapor yazamaz. Her üç yolda da <b>kimin doğruladığı kayda geçer ve görünür</b>. Belge istenmez, yüklenmez ve saklanmaz; ORCID eğitim kaydı ile derecenin göründüğü bir kurum adresi yazılabilir, bunlar editöre yardımcı olan kanıtlardır, kimseyi geri çeviren koşullar değil. Bu kural <b>her ülke için aynıdır</b>: tek bir ülkenin kimlik altyapısına bağlı bir yol tutulmaz.',
          'The only quality sought is a doctorate or an equivalent degree; no distinction is made between disciplines. It is confirmed <b>not by a document but by an editor who takes responsibility by name</b>, and there are three routes. <b>One:</b> if an editor or a chief editor invited the person as a reviewer, the one who sent the invitation has made the confirmation. <b>Two:</b> if the person applied to review a particular work, the editor who accepts the application, or a chief editor, confirms it. <b>Three:</b> if no one stands behind it by name, the person remains a prospective reviewer and cannot write a report. On all three routes <b>who confirmed it is recorded and shown</b>. No document is requested, uploaded or stored; an ORCID education record and an institutional address showing the degree may be given, and these are evidence that helps the editor, not conditions that turn anyone away. This rule is <b>the same for every country</b>: no route is tied to the identity infrastructure of a single state.'
        ) ?></p>
        <p><?= k_c(
          'Geçiş dönemi <b>31 Aralık 2027</b> tarihinde sona erer. Bu tarihe kadar belge sonradan da tamamlanabilir; sonrasında belgesi doğrulanmamış hiç kimse hakem olarak atanamaz.',
          'The transitional period ends on <b>31 December 2027</b>. Until then the document may be completed later; after that date no one whose document has not been verified may be assigned as a reviewer.'
        ) ?></p>

        <h3><?= k_c('Hangi sıfatla değerlendirilir', 'In what capacity the assessment is made') ?></h3>
        <p><?= k_c(
          'Uzmanlık, diplomanın alanıyla değil çalışmanın konusuyla ilgilidir. Bir fizikçi, bir iktisat çalışmasının yöntemine iktisatçının göremeyeceği bir katkı yapabilir; bilimin en verimli yeri çoğu zaman alanların kesiştiği yerdir. Bu yüzden alan uyuşmazlığı bir engel sayılmaz. Hakem, hangi sıfatla değerlendirdiğini kendisi seçer: <b>konu</b>, <b>yöntem</b>, <b>veri ve istatistik</b> ya da <b>dil ve kurgu</b>. Ayrıca kendisini o çalışmada neden yetkin gördüğünü birkaç cümleyle yazar; bu cümleler raporla birlikte yayımlanır ve okuyucu hakemin neye dayanarak konuştuğunu görür.',
          'Expertise is a matter of the topic, not of the discipline on the diploma. A physicist may contribute to the method of an economics paper in a way an economist could not; the most fertile ground in scholarship is often where fields meet. A mismatch of field is therefore not treated as an obstacle. The reviewer chooses the capacity in which they assess: <b>subject</b>, <b>method</b>, <b>data and statistics</b>, or <b>language and form</b>. They also write a few sentences on why they consider themselves competent for that work; these sentences are published with the report so the reader can see on what basis the reviewer speaks.'
        ) ?></p>

        <h3><?= k_c('Tekrarlanabilirlik değerlendirmesi', 'The reproducibility assessment') ?></h3>
        <p><?= k_c(
          'Her hakem, raporunun yanında çalışmanın tekrarlanabilirliğini de değerlendirir: yöntem başkasının aynı işlemi yeniden yapmasına yetecek ayrıntıda anlatılmış mı, verinin kaynağı ve erişim yolu belirtilmiş mi, bulgudan sonuca giden adımlar izlenebiliyor mu. Kuramsal ya da kavramsal çalışmalarda bu bölüm geçilebilir. Değerlendirme, raporla birlikte yayımlanır.',
          'Alongside the report every reviewer also assesses the reproducibility of the work: whether the method is described in enough detail for another to repeat the same procedure, whether the source of the data and the way to reach it are stated, and whether the steps from finding to conclusion can be followed. For theoretical or conceptual work this section may be skipped. The assessment is published with the report.'
        ) ?></p>

        <?php /* CÜMLE TEK KAYNAKTAN (ortak.php). Burada iki dilde elle
                 yazılmıştı ve ikisi de 13 Ağustos'ta kaldırılmış bir
                 kuralı anlatıyordu. İngilizcesini de düzeltmek gerekti:
                 bir kural iki dilde iki kez yazıldığında, kaldırıldığı
                 gün ikisinin de bulunması gerekir. */ ?>
        <p class="metin-sonuk"><?= k_esc(tg_hakemlik_yazarlik_cumlesi(k_en())) ?> <?= k_c(
          'Hakemlik hiçbir aşamada zorunlu değildir; bir daveti reddetmek en doğal haktır ve hiçbir kaydı olumsuz etkilemez.',
          'Reviewing is never compulsory; declining an invitation is an entirely ordinary right and affects no record adversely.'
        ) ?><?php $kn = tg_yazarlik_kosulu_kisa(k_en()); if ($kn !== ''): ?> <?= k_esc($kn) ?><?php endif; ?></p>
      </section>

      <!-- 06 ETİK VE BENZERLİK -->
      <section id="etik">
        <h2><span class="no">06</span><?= k_c('Yayın etiği ve benzerlik', 'Publication ethics and similarity') ?></h2>
        <?php /* KURAL 13 AĞUSTOS 2026'DA KALDIRILDI; cümle tek kaynaktan
                 okunur ve gerekçesi ayar.php'de durur. Eşik kutuları da
                 koşullu: ölçülmeyen bir eşiği büyük puntoyla basmak,
                 bu sayfanın kendi kuralına aykırıdır (bkz. 260. satır:
                 "bir sayı büyük puntoyla yazıldığında..."). */ ?>
        <p><?= k_esc(tg_benzerlik_metni(k_en())) ?></p>
        <?php if (tg_benzerlik_sarti()): ?>
        <div class="olcut">
          <div><b>%<?= (int)$benz ?></b><span><?= k_c('genel benzerlik üst sınırı', 'overall similarity ceiling') ?></span></div>
          <div><b>%<?= (int)$benzTek ?></b><span><?= k_c('tek bir kaynaktan üst sınır', 'ceiling from a single source') ?></span></div>
        </div>
        <p><?= k_c(
          'Eşiklerin altında kalmak tek başına yeterli değildir: kaynak gösterilmeden aktarılmış bir tek paragraf bile, oran ne olursa olsun başvurunun reddi için yeterlidir. Benzerlik oranı, çalışma yayımlandığında sayfasında açıkça gösterilir; denetim raporunun kendisi ise gizli tutulur.',
          'Remaining below the thresholds is not sufficient on its own: a single paragraph carried over without attribution is enough for rejection whatever the ratio. The similarity ratio is displayed openly on the work\'s page once published; the detection report itself is kept confidential.'
        ) ?></p>
        <?php endif; ?>
        <h3><?= k_c('Kabul edilmeyen davranışlar', 'Conduct that is not accepted') ?></h3>
        <ul>
          <li><?= k_c('Başkasının metnini, verisini ya da görselini kaynak göstermeden kullanmak.', 'Using another person\'s text, data or figures without attribution.') ?></li>
          <li><?= k_c('Aynı çalışmayı eşzamanlı olarak başka bir yayın organına göndermek.', 'Submitting the same work simultaneously to another publication venue.') ?></li>
          <li><?= k_c('Bir çalışmayı bölerek yapay biçimde çoğaltmak.', 'Splitting one study to multiply it artificially.') ?></li>
          <li><?= k_c('Veriyi seçerek, düzelterek ya da uydurarak sonucu yönlendirmek.', 'Steering the result by selecting, adjusting or fabricating data.') ?></li>
          <li><?= k_c('Etik kurul izni gereken bir çalışmayı izinsiz yürütmek ya da izni gizlemek.', 'Conducting work requiring ethics committee approval without it, or concealing the approval.') ?></li>
        </ul>
        <p><?= k_c(
          'İnsan ya da hayvan denekle yürütülen çalışmalarda etik kurul onayının tarihi ve numarası yöntem bölümünde belirtilmelidir. Bu bilgi eksikse başvuru ön denetimi geçemez.',
          'For work involving human or animal subjects, the date and number of the ethics committee approval must be stated in the methods section. A submission lacking this information does not pass the desk check.'
        ) ?></p>
      </section>

      <!-- 07 YAPAY ZEKÂ -->
      <section id="yz">
        <h2><span class="no">07</span><?= k_c('Yapay zekâ kullanımı', 'Use of artificial intelligence') ?></h2>
        <p><?= k_c(
          'Yapay zekâ araçlarının kullanımı yasak değildir, ancak beyan edilmesi zorunludur. Başvuru sırasında yazardan, hangi aracı hangi aşamada ve ne amaçla kullandığını yazması istenir. Bu beyan çalışmanın sayfasında yayımlanır.',
          'The use of artificial intelligence tools is not prohibited, but it must be declared. At submission the author is asked to state which tool was used, at which stage and for what purpose. This declaration is published on the work\'s page.'
        ) ?></p>
        <ul>
          <li><?= k_c('Yapay zekâ araçları yazar olarak gösterilemez; yazarlık sorumluluk gerektirir.', 'AI tools cannot be listed as authors; authorship entails responsibility.') ?></li>
          <li><?= k_c('Dil düzeltmesi, biçimlendirme ve çeviri amaçlı kullanım olağan kabul edilir.', 'Use for language editing, formatting and translation is regarded as ordinary.') ?></li>
          <li><?= k_c('Bulguların üretilmesinde ya da yorumlanmasında kullanım ayrıntılı olarak açıklanmalıdır.', 'Use in producing or interpreting findings must be explained in detail.') ?></li>
          <li><?= k_c('Aracın ürettiği kaynakların gerçekten var olduğu yazar tarafından doğrulanmalıdır.', 'The author must verify that references produced by the tool actually exist.') ?></li>
        </ul>
        <p class="metin-sonuk"><?= k_c(
          'Beyan edilmemiş kullanım saptandığında çalışma geri çekilir ve geri çekme gerekçesi sayfasında kalıcı olarak yer alır.',
          'Where undeclared use is identified the work is retracted and the grounds for retraction remain permanently on its page.'
        ) ?></p>
      </section>

      <!-- 08 TELİF -->
      <section id="telif">
        <h2><span class="no">08</span><?= k_c('Telif ve lisans', 'Copyright and licence') ?></h2>
        <p><?= k_c(
          '<b>Telif hakkı yazarda kalır.</b> Yazar, çalışmasını gönderirken yayın sistemine <b>münhasır olmayan, geri alınamaz ve süresiz</b> bir yayımlama, arşivleme ve dağıtma izni verir; hakkın devri yoktur. İzin münhasır olmadığı için yazar aynı çalışmayı başka yerlerde de yayımlamayı sürdürebilir.',
          '<b>Copyright remains with the author.</b> On submitting, the author grants the publishing system a <b>non exclusive, irrevocable and perpetual</b> permission to publish, archive and distribute the work; no right changes hands. Because the permission is non exclusive the author may continue to publish the same work elsewhere.'
        ) ?></p>
        <p><?= k_c(
          'İzin, çalışmanın kesintisiz biçimde açık erişimde tutulabilmesi ve arşivlenebilmesi içindir. <b>Bunun için hakkın el değiştirmesi gerekmez:</b> ' . $lis . ' lisansı geri alınamaz olduğundan, çalışma bir kez bu lisansla yayımlandığında arşivde kalma güvencesi lisansın kendisinden gelir. Bu düzen, sistemin ikinci değişmez ilkesinin gereğidir; o ilke "telif hakkı yazarında kalmak üzere" der ve oylanarak değiştirilemez.',
          'The permission exists so that the work can be kept in open access and archived without interruption. <b>No right needs to change hands for that:</b> because the ' . $lis . ' licence is irrevocable, once a work is published under it the guarantee that it stays in the archive comes from the licence itself. This arrangement follows from the second immutable principle of the system, which says "with copyright remaining with the author" and cannot be changed by a vote.'
        ) ?></p>
        <p><?= k_c(
          'Lisans, herkesin çalışmayı kaynak göstermek kaydıyla çoğaltmasına, dağıtmasına, çevirmesine ve üzerine yeni çalışma kurmasına izin verir.',
          'The licence permits anyone to copy, distribute, translate and build upon the work provided attribution is given.'
        ) ?></p>
        <p class="metin-sonuk"><?= k_c('Lisans metni: ', 'Licence text: ') ?><a href="<?= k_esc($lisU) ?>" rel="license noopener" target="_blank"><?= k_esc($lisU) ?></a></p>
      </section>

      <!-- 09 TAMGA -->
      <section id="tamga">
        <h2><span class="no">09</span><?= k_esc($tamAd) ?> <?= k_c('kimliği', 'identifier') ?></h2>
        <p><?= k_c(
          'Yayımlanan her çalışmaya, kendisine özgü ve devredilemez bir kimlik verilir. Bu kimliğe ' . $tamAd . ' adı verilmiştir; sözcük, Türk boylarının kendilerine ait olanı işaretlemek için kullandığı damgayı karşılar. Kimlik, çalışmanın başlığı ya da adresi değişse bile aynı kalır.',
          'Every published work receives an identifier that is unique to it and cannot be transferred. This identifier is called the ' . $tamAd . '; the word denotes the mark that Turkic clans used to sign what belonged to them. The identifier remains the same even if the title or address of the work changes.'
        ) ?></p>
        <p><span class="kod"><?= k_esc($kokAd . '/' . $tamYol . '/' . $tamOn . '-' . date('Y') . '-00001-' . tg_denetim(date('Y') . '00001')) ?></span></p>
        <p class="metin-sonuk"><?= k_c(
          'Kimlik dört parçadan oluşur: sistem öneki, yayın yılı, o yıl içindeki sıra ve bir denetim hanesi. Yıl dört haneli olduğu için biçim yüzyıllarca yeterlidir; sıra her yıl yeniden başladığı için taşma olmaz. Denetim hanesi, yanlış yazılmış ya da eksik aktarılmış bir kimliği anında açığa çıkarır.',
          'The identifier has four parts: the system prefix, the year of publication, the sequence within that year and a check character. Because the year has four digits the form suffices for centuries, and because the sequence restarts each year it cannot overflow. The check character reveals at once an identifier that has been mistyped or transcribed incompletely.'
        ) ?></p>

        <?php
        /* ---- ÇÖZÜMLEYİCİ ŞEMASI ----
           Yukarıdaki paragraf kimliğin dört parçadan oluştuğunu söylüyor
           ama hangi hanenin hangi parça olduğunu göstermiyor. Şemanın
           işi budur.

           ÖRNEK KOD ELLE YAZILMAZ. Denetim hanesi tg_denetim()'den
           gelir; elle yazılsaydı okur onu deneyip geçersiz bulur ve
           haklı olarak sistemden şüphelenirdi. Aynı sebeple aşağıdaki
           hesap adımları da tek tek çalıştırılarak basılır: metin ile
           kod ayrı düşemesin. */
        $semaYil  = date('Y');
        $semaSira = '00001';
        $semaGov  = $semaYil . $semaSira;
        $semaDen  = tg_denetim($semaGov);
        ?>
        <div class="tmg-sema">
          <span class="tmg-p"><b><?= k_esc($tamOn) ?></b><i><?= k_c('sistem öneki', 'system prefix') ?></i></span>
          <span class="tmg-ay">&#8211;</span>
          <span class="tmg-p"><b><?= k_esc($semaYil) ?></b><i><?= k_c('yayın yılı', 'year of publication') ?></i></span>
          <span class="tmg-ay">&#8211;</span>
          <span class="tmg-p"><b><?= k_esc($semaSira) ?></b><i><?= k_c('o yıl içindeki sıra', 'sequence within that year') ?></i></span>
          <span class="tmg-ay">&#8211;</span>
          <span class="tmg-p"><b><?= k_esc($semaDen) ?></b><i><?= k_c('denetim hanesi', 'check character') ?></i></span>
          <span class="tmg-muhur">
            <?= mh_muhur($tamOn . '-' . $semaYil . '-' . $semaSira . '-' . $semaDen, 48, 'tmg-im') ?>
            <span><?= k_c('bu koddan üretilen mühür', 'the seal generated from this code') ?></span>
          </span>
        </div>

        <h3><?= k_c('Denetim hanesi nasıl bulunur', 'How the check character is found') ?></h3>
        <p><?= k_c(
          'Yöntem ISO 7064 MOD 11-2\'dir; ISNI ve ORCID kimliklerinde de aynı yöntem kullanılır. Yıl ile sıra yan yana yazılır ve haneler soldan sağa şu işlemden geçirilir: toplama eklenen her hanenin ardından toplam ikiyle çarpılır. Sonuç 11\'e bölünür, kalan 12\'den çıkarılıp yine 11\'e bölünür; çıkan sayı denetim hanesidir. 10 çıkarsa yerine X yazılır, çünkü tek hane gerekir.',
          'The method is ISO 7064 MOD 11-2, the same one used for ISNI and ORCID identifiers. The year and the sequence are written side by side and the digits are processed from left to right: after each digit is added the running total is doubled. The result is taken modulo 11, that remainder is subtracted from 12 and taken modulo 11 again; the number that comes out is the check character. If it comes out as 10 an X is written instead, because a single character is required.'
        ) ?></p>
        <?php
        /* Adımlar hesabın kendisinden basılır. Elle yazılmış bir tablo,
           tg_denetim() bir gün değişirse sessizce yalan olurdu. */
        $t = 0; $satir = [];
        for ($i = 0; $i < strlen($semaGov); $i++) {
            $t = ($t + (int)$semaGov[$i]) * 2;
            $satir[] = $semaGov[$i] . ' &#8594; ' . $t;
        }
        ?>
        <div class="tmg-hesap"><?= k_esc($semaGov) ?><br>
          <?= implode(' &#183; ', $satir) ?><br>
          <?= k_c('toplam', 'total') ?> <?= (int)$t ?> &#183; mod 11 = <?= (int)($t % 11) ?>
          &#183; (12 &#8722; <?= (int)($t % 11) ?>) mod 11 = <?= k_esc($semaDen) ?>
        </div>
        <p class="metin-sonuk"><?= k_c(
          'Bu yöntem, tek bir hanenin yanlış yazıldığı ve yan yana iki hanenin yer değiştirdiği bütün durumları yakalar; ikisi de en sık yapılan aktarma hatalarıdır. Her hatayı yakaladığı söylenemez: örneğin üç hanenin birden değiştiği bir kimlik, düşük bir olasılıkla geçerli görünebilir. Kimliğin doğrulanması, metnin doğrulanmasının yerine geçmez; onun için her makale sayfasındaki parmak izi aracı vardır.',
          'This method catches every case in which a single digit is mistyped and every case in which two adjacent digits are transposed; both are the most common transcription errors. It cannot be said to catch every error: an identifier in which three digits have changed at once may, with low probability, still appear valid. Verifying the identifier does not stand in place of verifying the text; the fingerprint tool on every article page exists for that.'
        ) ?></p>
        <h3><?= k_c('Eski kimlikler ve eski adresler', 'Earlier identifiers and earlier addresses') ?></h3>
        <p><?= k_c(
          'Sistemin ilk döneminde verilen kimlikler ' . $tamOnEski . '.000001 biçimindeydi. Bu kimlikler değiştirilmedi ve değiştirilmeyecek: bir kalıcı kimliğin sonradan değiştirilmesi, kalıcılık sözünün kendisini bozar. O çalışmalar da bugün herkes gibi kalıcı adresten açılır.',
          'Identifiers issued in the system\'s first period took the form ' . $tamOnEski . '.000001. They have not been changed and will not be: altering a permanent identifier after the fact breaks the very promise of permanence. Those works open at the permanent address like every other.'
        ) ?></p>
        <p><span class="kod"><?= k_esc($kokAd . '/' . $tamYol . '/' . $tamOnEski . '.000001') ?></span></p>
        <p><?= k_c(
          'Daha da eski, DOI\'ye benzeyen bir adres biçimi de bir süre kullanıldı. O adresler kırılmadı; istendiğinde çalışmanın bugünkü kalıcı adresine yönlendirilir. Ancak yeni çalışmalarda kullanılmaz ve hiçbir yerde önerilmez: DOI\'ye benzeyen bir adres, DOI olmayan bir kimliği DOI sanmaya yol açar. Paylaşılacak adres her zaman yukarıdaki biçimdir.',
          'An even earlier address form resembling a DOI was also in use for a time. Those addresses are not broken; when requested they redirect to the work\'s present permanent address. They are not used for new works and are recommended nowhere: an address that looks like a DOI invites the reader to mistake a non DOI identifier for one. The address to share is always the form above.'
        ) ?></p>
        <div class="kutu kutu-lac"><?= k_c(
          '<b>Önemli:</b> ' . $tamAd . ' bir DOI değildir ve DOI yerine geçmez. İşlevi benzerdir: çalışmaya kalıcı bir adresten erişilmesini sağlar. Ancak DOI kayıt kuruluşlarınca verilen bir numara değildir ve akademik yükseltme ölçütlerinde DOI yerine sayılmaz. Bu ayrım, yazarın yanlış bir beklentiye kapılmaması için burada açıkça belirtilmiştir.',
          '<b>Important:</b> the ' . $tamAd . ' is not a DOI and does not replace one. Its function is similar: it provides a permanent address through which the work can be reached. It is not, however, a number issued by a DOI registration agency and it does not count in place of a DOI in academic promotion criteria. This distinction is stated openly here so that authors are not left with a false expectation.'
        ) ?></div>
      </section>

      <!-- 10 DÜZELTME VE GERİ ÇEKME -->
      <section id="duzelt">
        <h2><span class="no">10</span><?= k_c('Düzeltme ve geri çekme', 'Correction and retraction') ?></h2>
        <p><?= k_c(
          'Yayımlanmış bir çalışmanın metni sessizce değiştirilmez. Hata bildirimi geldiğinde yapılan işlem, hatanın ağırlığına göre üç biçimden birini alır.',
          'The text of a published work is never altered silently. When an error is reported the action taken takes one of three forms according to the gravity of the error.'
        ) ?></p>
        <h3><?= k_c('Küçük düzeltme', 'Minor correction') ?></h3>
        <p><?= k_c(
          'Yazım yanlışı, bozuk bağlantı ve biçim hataları doğrudan düzeltilir; çalışmanın sonuçlarını etkilemediği için ayrı bir kayıt tutulmaz.',
          'Typographical errors, broken links and formatting faults are corrected directly; since they do not affect the results of the work, no separate record is kept.'
        ) ?></p>
        <h3><?= k_c('Düzeltme kaydı', 'Correction notice') ?></h3>
        <p><?= k_c(
          'Bulguları, sayıları ya da yorumu etkileyen bir hata düzeltildiğinde, çalışmanın sayfasına tarihli bir düzeltme kaydı eklenir. Kayıtta neyin, neden değiştiği yazar.',
          'Where an error affecting findings, figures or interpretation is corrected, a dated correction notice is added to the work\'s page stating what changed and why.'
        ) ?></p>
        <h3><?= k_c('Endişe bildirimi', 'Expression of concern') ?></h3>
        <p><?= k_c(
          'Bir çalışma hakkında ciddi bir kuşku doğduğu, ancak inceleme henüz tamamlanmadığı durumlarda sayfaya tarihli bir endişe bildirimi eklenir. Bildirimin amacı okuyucuyu uyarmak, incelemenin sonucunu beklerken de kaydı eksiksiz tutmaktır.',
          'Where a serious doubt arises about a work but the examination is not yet complete, a dated expression of concern is added to its page. Its purpose is to warn the reader while keeping the record complete pending the outcome.'
        ) ?></p>
        <h3><?= k_c('Geri çekme', 'Retraction') ?></h3>
        <p><?= k_c(
          'İntihal, veri uydurma, beyan edilmemiş yapay zekâ kullanımı, çifte yayın ya da yayın ilkelerine aykırı içerik saptandığında çalışma geri çekilir. Geri çekilen çalışma sistemden silinmez: sayfası "geri çekildi" damgasıyla erişilebilir kalır ve gerekçe orada yazılı olur. Bilimsel kaydın silinmesi değil, düzeltilerek korunması esastır.',
          'Where plagiarism, data fabrication, undeclared use of artificial intelligence, duplicate publication or content contrary to the editorial policies is identified, the work is retracted. A retracted work is not deleted from the system: its page remains accessible bearing a "retracted" stamp, with the grounds set out there. The principle is that the scholarly record is preserved and corrected rather than erased.'
        ) ?></p>
        <p><?= k_c(
          'Düzeltme, endişe bildirimi ve geri çekme kayıtlarının her biri kalıcı kimliğe bağlanır; tarihi, gerekçesi ve kararı verenin adı görünür. Geri çekme yalnızca sayfada değil, makine tarafından okunabilen üst verilerde de yer alır: arama motorları ve dizinler sayfadaki uyarı bandını her zaman okumadığı için, çalışmanın başlığı bu kayıtlara da geri çekildiği bilgisiyle gider.',
          'Corrections, expressions of concern and retractions are each bound to the permanent identifier; the date, the grounds and the name of whoever decided are visible. A retraction appears not only on the page but also in machine readable metadata: because search engines and indexes do not always read a notice on the page, the title reaches those records carrying the fact of retraction.'
        ) ?></p>
      </section>

      <!-- 11 VERİ VE GİZLİLİK -->
      <section id="veri">
        <h2><span class="no">11</span><?= k_c('Veri, gizlilik ve erişim', 'Data, privacy and access') ?></h2>
        <p><?= k_c(
          'Sistem, çalışmaların ne ölçüde okunduğunu görebilmek için erişim kaydı tutar. Kaydedilen bilgiler, hangi çalışmanın açıldığı, sayfada geçirilen süre ve ziyaretçinin ülke ya da şehir düzeyindeki konumudur. Ziyaretçi adresi, kimliği geri çevrilemeyecek biçimde özetlenerek saklanır; aynı okuyucunun tekrar tekrar açması tek okuma sayılır.',
          'The system keeps access records so that the extent to which works are read can be observed. The information recorded is which work was opened, the time spent on the page and the visitor\'s location at country or city level. The visitor address is stored in a summarised form from which the identity cannot be recovered; repeated opening by the same reader counts as a single read.'
        ) ?></p>
        <ul>
          <li><?= k_c('Erişim verisi üçüncü kişilerle paylaşılmaz ve satılmaz.', 'Access data is not shared with third parties and is not sold.') ?></li>
          <li><?= k_c('Sistemde reklam ve izleme betiği bulunmaz.', 'The system contains no advertising and no tracking scripts.') ?></li>
          <li><?= k_c('Yazar, kendi çalışmasının erişim verisini yazar panelinden görebilir.', 'An author can view the access data for their own work from the author panel.') ?></li>
          <li><?= k_c('Hakem havuzuna ilişkin iletişim bilgileri hiçbir sayfada gösterilmez.', 'Contact details relating to the reviewer pool are not displayed on any page.') ?></li>
        </ul>
        <h3><?= k_c('Veri ve kod erişilebilirliği', 'Availability of data and code') ?></h3>
        <p><?= k_c(
          'Bir sonucun doğruluğu, ancak başkası tarafından denetlenebildiği ölçüde bilimseldir. Bu yüzden her gönderimde veri ve kodun erişilebilirliğine ilişkin bir beyan istenir: açık olarak paylaşıldı, istek üzerine paylaşılır, paylaşılamıyor ya da çalışma veri üretmiyor. Paylaşılamıyorsa gerekçesi yazılır. Veri paylaşılamıyor olması bir kusur değildir; gizlenmesi kusurdur. Beyan, çalışmayla birlikte yayımlanır ve okuyucuya görünür.',
          'A finding is scientific only to the extent that someone else can check it. A statement on the availability of data and code is therefore required with every submission: openly shared, available on request, cannot be shared, or the work produces no data. Where it cannot be shared, the reason is stated. Being unable to share data is not a fault; concealing that is. The statement is published with the work and shown to the reader.'
        ) ?></p>
        <h3><?= k_c('Arşivin tamamı indirilebilir', 'The whole archive can be downloaded') ?></h3>
        <p><?= k_c(
          'Kalıcı bir kimlik vermek, kalıcılığı taahhüt etmektir. Bu taahhüt tek bir sunucuya bağlı kalırsa boştur: sunucu giderse kayıt da gider. Bu yüzden arşivin tamamı tek bir adresten, kimseden izin almadan indirilebilir. İçinde çalışmaların tam metni, hakem raporları, kurul oylamaları ve bütün oylar, yayın sonrası şerhler, düzeltme ve geri çekme kayıtları ile her çalışmanın metin parmak izi vardır. Kişisel veri yoktur: e-posta adresleri, erişim anahtarları ve hesap kayıtları bilerek dışarıda bırakılmıştır. Kopyayı alan herkes bu arşivi çoğaltabilir ve sürdürebilir. Kopyalamayı kolaylaştırmak, bir arşivi korumanın en ucuz ve en sağlam yoludur.',
          'To issue a permanent identifier is to promise permanence. That promise is empty if it rests on a single server: if the server goes, the record goes with it. The entire archive can therefore be downloaded from a single address without anyone\'s permission. It contains the full text of the works, the referee reports, the panel votes with every vote, the post publication notes, the correction and retraction records, and the text fingerprint of each work. It contains no personal data: e mail addresses, access keys and account records are deliberately excluded. Anyone who takes a copy may reproduce and continue this archive. Making copying easy is the cheapest and the sturdiest way to preserve an archive.'
        ) ?></p>
        <h3><?= k_c('Metnin parmak izi', 'The fingerprint of the text') ?></h3>
        <p><?= k_c(
          'Her çalışmanın metninden bir özet değer hesaplanır ve çalışmanın sayfasında yayımlanır. Metin sonradan değiştirilirse bu değer de değişir; dolayısıyla bir metnin yayımlandığı hâlinden farklı olup olmadığı, sisteme güvenmek zorunda kalmadan, dışarıdan denetlenebilir. Bu değer, sistemin kendisine karşı da bir güvencedir: kurucusu dahil hiç kimse bir metni sessizce değiştiremez.',
          'A digest is computed from the text of every work and published on the work\'s page. If the text is later altered the value changes with it; whether a text differs from the form in which it was published can therefore be checked from outside, without having to trust the system. This value is a safeguard against the system itself: no one, its founder included, can alter a text in silence.'
        ) ?></p>
        <div class="satir">
          <a class="d d-ikinci" href="/dokum.php"><?= k_c('Arşivin tamamını indir', 'Download the whole archive') ?></a>
          <a class="d d-ikinci" href="/dokum.php?d=kutadgu-parmak-izleri.txt"><?= k_c('Yalnızca parmak izleri', 'Fingerprints only') ?></a>
        </div>

        <h3><?= k_c('Erişilebilirlik', 'Accessibility') ?></h3>
        <p><?= k_c(
          'Bütün sayfalar klavyeyle kullanılabilir, ekran okuyucularla uyumludur ve koyu tema seçeneği sunar. Metinler tarayıcıdan büyütüldüğünde düzen bozulmaz. Çalışmalara erişim için üyelik, abonelik ya da giriş gerekmez.',
          'All pages are operable by keyboard, compatible with screen readers and offer a dark theme. The layout does not break when text is enlarged in the browser. No membership, subscription or sign in is required to reach the works.'
        ) ?></p>
      </section>

      <!-- 12 İTİRAZ VE KURUL OYLAMASI -->
      <section id="kurul">
        <h2><span class="no">12</span><?= k_c('İtiraz ve kurul oylaması', 'Objection and the panel vote') ?></h2>
        <p><?= k_c(
          'Açık hakemlikte en kırılgan an, yazar ile hakemin anlaşamadığı andır. Kararı tek bir editöre bırakmak sistemin bütün ağırlığını tek kişiye yükler ve o kişiyi zamanla sistemin sahibi hâline getirir. Yazara bırakmak ise hakemliği baştan anlamsız kılar. Bu yüzden anlaşmazlık, tarafların hiçbirine değil üç bağımsız kişiye götürülür.',
          'The most fragile moment in open review is when an author and a reviewer disagree. Leaving the decision to a single editor puts the whole weight of the system on one person and in time makes that person its owner. Leaving it to the author makes review meaningless from the start. The disagreement therefore goes to neither party but to three independent people.'
        ) ?></p>
        <h3><?= k_c('İki yol: itiraz ve şikâyet', 'Two routes: objection and complaint') ?></h3>
        <p><?= k_c(
          'İtirazı yalnızca çalışmanın yazarı açabilir ve yalnızca kendi çalışması hakkındaki bir rapora karşı açar; bu, kişinin kendi hakkını kullanmasıdır. Şikâyeti ise doktora belgesi doğrulanmış herkes açabilir; bir raporun savruk ya da kötü niyetli olduğunu düşünen okuyucunun yolu budur. Şikâyet, rapora katılmamak için değil, raporun değerlendirme sayılamayacak kadar özensiz ya da art niyetli olduğunu düşünmek için kullanılır. Her iki yolda da gerekçe zorunludur ve gerekçe herkese açık olarak yayımlanır.',
          'An objection may be opened only by the author of the work, and only against a report on their own work; this is a person exercising their own right. A complaint may be opened by anyone whose doctoral credential has been verified; it is the route for a reader who believes a report is careless or made in bad faith. A complaint is not for disagreeing with a report but for holding that it is too slipshod or too malicious to count as an assessment. Both routes require stated grounds, and those grounds are published openly.'
        ) ?></p>
        <h3><?= k_c('Oylar karar anında kapalıdır', 'Votes are sealed at the moment of decision') ?></h3>
        <p><?= k_c(
          'Üç bağımsız kişi oy verir. Oylama açıkken kimse kimsenin oyunu göremez; hangi seçeneğe kaç oy gittiği bile gizlidir. Bunun sebebi basittir: ilk oyun görüldüğü bir düzende sonraki oylar ona doğru kayar ve üç bağımsız görüş yerine tek bir görüşün üç kopyası elde edilir. Üçüncü oy düştüğü anda oylama kapanır ve her şey açılır: kimin nasıl oy verdiği, gerekçesiyle birlikte kalıcı olarak yayımlanır. Gizlilik karar anına aittir, karardan sonrasına değil.',
          'Three independent people vote. While the vote is open no one can see anyone else\'s vote; even the count by option is sealed. The reason is simple: where the first vote is visible, later votes drift towards it, and instead of three independent judgements one obtains three copies of a single one. The moment the third vote is cast the vote closes and everything opens: who voted how, together with their reasoning, published permanently. Secrecy belongs to the moment of decision, not to what follows it.'
        ) ?></p>
        <h3><?= k_c('Kimler oy veremez', 'Who cannot vote') ?></h3>
        <p><?= k_c(
          'Taraflar kapsam dışıdır: çalışmanın yazarı ve ortak yazarları, hakkında oy verilen hakem ve oylamayı açan kişi oy kullanamaz. Oy vermek için doktora belgesinin gerçekten doğrulanmış olması aranır; şerh düşmekte ve hakemlikte tanınan geçiş dönemi kolaylığı burada uygulanmaz. Gerekçesi şudur: bir oy, başkasının yayımlanmış raporunu geçersiz kılabilir ve hakemlik yetkisini askıya alabilir. Bu ağırlıkta bir yetkinin doğrulanmamış hesaplara açık olması, üç sahte hesabın bir araya gelip bir raporu düşürmesine yol açardı.',
          'The parties are excluded: the author and co authors of the work, the reviewer whose report is at issue, and whoever opened the vote may not cast one. Voting requires a doctoral credential that has actually been verified; the transitional allowance granted for commentary and for reviewing does not apply here. The reason is that a vote can set aside someone else\'s published report and suspend their reviewing privilege. Opening a power of that weight to unverified accounts would allow three false accounts to combine and bring down a report.'
        ) ?></p>
        <h3><?= k_c('Kurul ne karar verebilir', 'What the panel may decide') ?></h3>
        <p><?= k_c(
          'Bir itirazda üç sonuçtan biri çıkar: itiraz yersizdir ve rapor yerinde durur; rapor geçersiz sayılır ve onay sayımına katılmaz; ya da rapor durur ancak çalışmaya yeni bir hakem aranır. Bir şikâyette de üç sonuç vardır: şikâyet yersizdir; rapor geçersiz sayılır; ya da rapor geçersiz sayılmakla birlikte hakemin hakemlik yetkisi askıya alınır. Oyların eşit dağıldığı durumda ağır olan değil hafif olan sonuç geçerlidir: bir kaydı geçersiz saymak için açık bir çoğunluk aranır.',
          'An objection yields one of three outcomes: the objection is unfounded and the report stands; the report is set aside and does not count towards approval; or the report stands but a further reviewer is sought for the work. A complaint likewise has three: the complaint is unfounded; the report is set aside; or the report is set aside and the reviewer\'s privilege is suspended. Where votes divide evenly the lighter outcome prevails, not the heavier one: setting a record aside requires a clear majority.'
        ) ?></p>
        <h3><?= k_c('Geçersiz sayılan rapor silinmez', 'A report set aside is not deleted') ?></h3>
        <p><?= k_c(
          'Kurul bir raporu geçersiz saydığında rapor sistemden kaldırılmaz. Çalışmanın sayfasında durmayı sürdürür ve üzerinde kurul kararıyla geçersiz sayıldığı yazar; okuyucu hem raporu hem itirazı hem de üç oyu gerekçeleriyle okuyabilir. Değişen tek şey sayımdır: geçersiz sayılan rapor ne onaya ne rette katılır. Bilimsel kaydın silinmesi değil, düzeltilerek korunması esastır.',
          'When the panel sets a report aside, the report is not removed from the system. It remains on the work\'s page bearing a note that a panel vote set it aside; the reader can read the report, the objection and all three votes with their reasoning. The only thing that changes is the count: a report set aside counts neither towards approval nor towards rejection. The principle is that the scholarly record is preserved and corrected rather than erased.'
        ) ?></p>
        <h3><?= k_c('Oy vermek de bir emektir', 'Voting too is labour') ?></h3>
        <p><?= k_c(
          'Dört geçerli oy kullanan kişi yazarlık hakkını kazanır. Bu, hakemlik yoluyla kazanılan hakla aynı mantığa dayanır: bu sistemde başkasının işine vakit ayırmadan yazar olunmaz. Oy gerekçesiz verilemez; gerekçesiz oy, kurulu bir sayaca çevirir ve kararın denetlenebilirliğini ortadan kaldırır.',
          'Whoever casts four valid votes earns the right to submit as an author. This rests on the same logic as the right earned through reviewing: in this system no one becomes an author without giving time to someone else\'s work. A vote cannot be cast without reasoning; an unreasoned vote turns the panel into a counter and destroys the auditability of the decision.'
        ) ?></p>
        <p><?= k_c(
          'Kurul kararı kalıcıdır. Sonuç, oylar ve gerekçeler silinmez ve değiştirilemez; bu sistemin kurucuları dahil hiç kimse bir kurul kararını geri alamaz. Bir çalışma hakkındaki oylama, o çalışmanın kendi sayfasında görülür ve oy orada kullanılır: karar, karara konu olan metinden ayrı bir yerde durmaz. Kurulun <b>kendisi</b> hakkındaki kararlar ise kurul sayfasında, oyları ve gerekçeleriyle birlikte durur.',
          'A panel decision is permanent. The result, the votes and the reasoning are never deleted or altered; no one, the founders of this system included, can reverse a panel decision. A vote about a work is seen on the page of that work, and the vote is cast there: a decision does not stand in a place separate from the text it is about. Decisions about the board <b>itself</b> stand on the board page, together with their votes and reasoning.'
        ) ?></p>
        <?php /* BAĞ EKLENDİ — 19 Ağustos 2026. Kurul kararları aynı gün
                 açıldı ve bu paragraf onlardan söz ediyordu, ama nerede
                 durduklarını söylemiyordu: adı geçen ama yeri
                 gösterilmeyen bir kayıt, olmayan bir kayıttan yalnız
                 biraz daha iyidir. */ ?>
        <div class="satir">
          <a class="d d-ikinci" href="<?= k_esc(k_bag('/kurul.php')) ?>#kararlar"><?= k_c('Kurul kararlarını gör', 'See the board decisions') ?></a>
        </div>
              <?php /* Onursal baş editörlük. Ayrıntısı kurul sayfasındadır;
                 buraya yalnızca bağlayıcı hüküm yazılır. */ ?>
        <h3><?= k_c('Onursal baş editörlük', 'Honorary chief editorship') ?></h3>
        <p><?= k_c(
          'Görevdeki baş editörlüğü sona eren kişi onursal baş editör olarak anılmayı sürdürür. Görev beş yoldan biriyle sona erer. Dördü yazılı bir kayıttır: kişinin kendi devri, vefatı, kurucuların çoğunluk kararı, ya da atamaya bilerek konmuş bir bitiş tarihi. Beşincisi bir sayımdır ve yalnız atanmış baş editörlere işler: görev bir yıllık dönemler hâlinde sürer ve dönem sonunda etkinlik ölçütü karşılanmamışsa o gün sona erer; kurucular bu ölçümün dışındadır. Görev takvimle bitmez; bir tarihin gelmesi kimsenin yetkisini sessizce almaz. Görevi biten kişi sistemde yazar ve hakem olarak çalışmayı sürdürür ve ölçütü yeniden karşıladığı gün kendiliğinden yeniden göreve gelir. Bu sıfat bir teşekkürdür; hakem atama, editör ekleme, editöryal not düşme ve karar geri alma dahil hiçbir yetki taşımaz. <b>Verilmez, kalır:</b> kimsenin bu sıfatı birine tanıma yetkisi yoktur, sistemi devralan kurumun da yoktur. Sayım kurul sayfasında kayıtlardan yapılır.',
          'A person whose chief editorship in office has ended continues to be recorded as an honorary chief editor. An office ends by one of five routes. Four are a written record: the person\'s own handover, their death, a majority decision of the founders, or an end date deliberately written into the appointment. The fifth is a count and applies only to appointed chief editors: the office runs in one-year terms and ends on the day a term closes with the activity criterion unmet; founders lie outside this measurement. An office does not end by the calendar; the arrival of a date takes no one\'s authority away in silence. A person whose office has ended goes on working in the system as an author and a reviewer, and returns to office of itself on the day they meet the criterion again. The title is a thank you; it carries no authority whatsoever, including assigning reviewers, adding editors, writing editorial notes and reversing decisions. <b>It is not granted, it remains:</b> no one has the power to confer it on anyone, and neither does the institution that takes the system over. The count is made from the records on the board page.'
        ) ?></p>
        <p><?= k_c(
          '<b>Bu sıfat para karşılığı verilemez.</b> Bağış, barındırma, çeviri desteği ya da başka bir katkı hiç kimseye onursal baş editörlük kazandırmaz. Destek verenlerin adı destek sayfasında ve destekledikleri dilin sayfasında kalıcı olarak yazar; bu ayrı bir teşekkürdür ve kurulla ilgisi yoktur. Dağıtılabilen bir onur sıfatı zamanla bir nezaket parasına dönüşür ve dönüştüğü gün kurulun bütün adlarını değersizleştirir.',
          '<b>This title may not be given for money.</b> A donation, hosting, translation support or any other contribution earns no one an honorary chief editorship. The names of supporters are recorded permanently on the support page and on the page of the language they supported; that is a separate acknowledgement and has nothing to do with the board. An honour that can be handed out becomes, in time, a courtesy currency, and on the day it does it devalues every name on the board.'
        ) ?></p>
</section>

      <!-- 13 YAYIN SONRASI ŞERH -->
      <section id="serh">
        <h2><span class="no">13</span><?= k_c('Yayın sonrası şerh', 'Post publication commentary') ?></h2>
        <p><?= k_c(
          'Klasik dergide değerlendirme yayım günü biter. Oysa bir bulgunun sınanması çoğu zaman yayımdan sonra başlar: biri veriyi yeniden çalıştırır, biri yöntemdeki bir varsayımı fark eder, biri aynı sonucu başka bir örneklemde bulamaz. Bu bilgi bir yere yazılmazsa kaybolur. Bu yüzden her çalışmanın altında kalıcı bir şerh alanı vardır.',
          'In a conventional journal the assessment ends on the day of publication. Yet the testing of a finding usually begins after publication: someone re runs the data, someone notices an assumption in the method, someone fails to obtain the same result in another sample. If this knowledge is not written down anywhere it is lost. For that reason there is a permanent commentary area beneath every work.'
        ) ?></p>
        <h3><?= k_c('Kim şerh düşebilir', 'Who may leave a note') ?></h3>
        <p><?= k_c(
          'Doğrulanmış hesabı olan herkes. Şerh yazanın kendi adıyla, unvanı ve kurumuyla yayımlanır; anonim ya da takma adlı şerh kabul edilmez. Şerh düşenin çalışmayla ilişkisi de gösterilir: okuyucu, çalışmanın hakemi ya da çalışmanın yazarı. Okumak için hesap gerekmez ve hiçbir zaman gerekmeyecektir; hesap yalnızca ad taşıyan ve kalıcı olan bir kayıt bırakmak için istenir.',
          'Anyone with a verified account. A note is published under the writer\'s own name, title and institution; anonymous or pseudonymous notes are not accepted. The writer\'s relation to the work is also shown: reader, reviewer of this work, or author of this work. No account is needed in order to read, and none ever will be; an account is required only in order to leave a record that carries a name and stays permanently.'
        ) ?></p>
        <h3><?= k_c('Şerh silinmez', 'A note is not deleted') ?></h3>
        <p><?= k_c(
          'Bir şerh gönderildikten sonra ne yazan, ne çalışmanın yazarı, ne de bir editör onu silebilir ya da düzeltebilir. Rahatsız edici bir eleştiriyi ortadan kaldırma yetkisi kimsede yoktur; bir sistem, kendi aleyhine yazılanı silebiliyorsa şeffaf değildir. Bu yüzden gönderim anında bir onay istenir: şerh kalıcıdır ve geri alınamaz.',
          'Once a note has been sent, neither the writer, nor the author of the work, nor an editor can delete or edit it. No one holds the power to remove an uncomfortable criticism; a system that can erase what is written against it is not transparent. A confirmation is therefore asked for at the moment of sending: the note is permanent and cannot be withdrawn.'
        ) ?></p>
        <h3><?= k_c('Perdeleme: sansür de kayda geçer', 'Screening: censorship is recorded too') ?></h3>
        <p><?= k_c(
          'Tek istisna kişisel saldırı ve hukuka aykırı içeriktir. Böyle bir şerhin metni bir editör tarafından perdelenebilir, yani kapatılabilir. Ancak silinmez: şerhin var olduğu, kimin perdelediği, ne zaman perdelediği ve gerekçesi her okuyucuya açık kalır. Gerekçe yazılmadan bir şerh perdelenemez. Böylece bir eleştiriyi susturma kararı da denetlenebilir bir kayda dönüşür.',
          'The sole exception is a personal attack or unlawful content. The text of such a note may be screened, that is closed, by an editor. It is not deleted: the fact that the note exists, who screened it, when, and on what grounds remains open to every reader. A note cannot be screened without a stated reason. A decision to silence a criticism thereby becomes an auditable record in its own right.'
        ) ?></p>
        <h3><?= k_c('Yazarın yanıt hakkı', 'The author\'s right of reply') ?></h3>
        <p><?= k_c(
          'Çalışmanın yazarı her şerhe bir kez yanıt verebilir. Yanıt şerhin altında görünür ve o da kalıcıdır. Bir kez sınırı bilerek konmuştur: amaç tartışmayı sürdürmek değil, yazara kendini açıklama hakkını tanımaktır. Sürekli karşılıklı yazışma, sayfayı kayıt olmaktan çıkarıp münakaşaya çevirir.',
          'The author of a work may reply once to each note. The reply appears beneath the note and is likewise permanent. The limit of one is deliberate: the aim is not to sustain a debate but to give the author the right to explain. Continuous exchange would turn the page from a record into a quarrel.'
        ) ?></p>
        <p><?= k_c(
          'Şerhler çalışmanın kalıcı kimliğine bağlanır ve çalışmayla birlikte arşivlenir. Bir gün bu sistem yürümezse bile, kimin neye ne zaman itiraz ettiği kayıtta durur.',
          'Notes are bound to the permanent identifier of the work and archived with it. Even if one day this system does not continue, who objected to what, and when, remains in the record.'
        ) ?></p>
      </section>

      <!-- 14 HESAPLAR VE METİN GÜVENLİĞİ -->
      <section id="hesap">
        <h2><span class="no">14</span><?= k_c('Hesaplar, roller ve metin güvenliği', 'Accounts, roles and text security') ?></h2>
        <?php /* MERDİVEN CÜMLESİ İKİYE AYRILDI.
                 Eskiden tek bir cümlede hem HAKEMLİK basamakları hem de
                 "yazarlık ancak hakemlikten sonra gelir ... kendi
                 çalışmanızı yayımlayamazsınız" yazıyordu. 13 Ağustos
                 2026 kurul kararından sonra ikinci yarısı yanlıştır:
                 çalışmayı herkes gönderebilir. Hakemlik basamakları ise
                 aynen durur — doktora belgesi HAKEM olmak için hâlâ
                 aranır; kalkan şey YAZAR olmanın doktora koşuludur ve
                 ikisi karıştırılmamalıdır.

                 Yazarlık cümlesi artık tek kaynaktan okunur. */ ?>
        <p><?= k_c(
          'Hakemlikte roller bir merdivendir ve kimse basamak atlayamaz. Okur olmak için kayıt gerekmez; bütün çalışmalar herkese açıktır. Doktora belgesini sunan kişi aday hakem olur. Belgesi doğrulanan ve en az bir değerlendirme yapan kişi hakem olur. Editörler baş editörlerce listeye eklenir; hakem atayabilir ve editöryal not düşebilirler.',
          'In reviewing, the roles form a ladder and no one skips a rung. Being a reader requires no registration; every work is open to all. Whoever submits a doctoral credential becomes a candidate reviewer. Whoever has that credential verified and completes at least one assessment becomes a reviewer. Editors are added to the list by chief editors; they may assign reviewers and add editorial notes.'
        ) ?></p>
        <?php $ilkKosul = tg_yazarlik_kosulu_metni(k_en()); if ($ilkKosul !== ''): ?>
        <p><b><?= k_c('Yazarlık ayrı bir yoldur.', 'Authorship is a separate road.') ?></b> <?= k_esc($ilkKosul) ?></p>
        <?php endif; ?>
        <h3><?= k_c('Kullanıcı adı görünmez', 'The username is never shown') ?></h3>
        <p><?= k_c(
          'Giriş için seçilen kullanıcı adı hiçbir sayfada gösterilmez ve hiçbir yerde yayımlanmaz. Sayfalarda her zaman yalnızca unvanıyla birlikte gerçek ad görünür. Kullanıcı adı bir kimlik değil, yalnızca bir anahtardır.',
          'The username chosen for signing in is not shown on any page and is published nowhere. What appears on the pages is always the real name together with its title. The username is not an identity, only a key.'
        ) ?></p>
        <h3><?= k_c('Kendi bilgilerinizi kendiniz düzenlersiniz', 'You edit your own details yourself') ?></h3>
        <p><?= k_c(
          'Her kullanıcı unvanını, kurumunu, ORCID kaydını, çalışma alanlarını ve kişisel sayfasını kendi panelinden düzenleyebilir; parolasını değiştirebilir. Bu bilgiler yayımlanmış çalışmaların künyesinde değil, kişinin profilinde tutulur; çünkü künye, çalışmanın gönderildiği andaki durumu kaydeder ve sonradan değiştirilmez.',
          'Every user may edit their title, institution, ORCID record, fields of work and personal page from their own panel, and may change their password. These details are held in the person\'s profile rather than in the imprint of published works, because the imprint records the state of things at the moment of submission and is not altered afterwards.'
        ) ?></p>
        <h3><?= k_c('Metin kilidi: parola çalınırsa', 'The text lock: if a password is stolen') ?></h3>
        <p><?= k_c(
          'Bir çalışmanın metni her zaman düzenlenebilir değildir. Üç durumdan birindedir. Henüz rapor gelmemişse metin açıktır ve yazar serbestçe düzeltir. Bir hakem revizyon istemişse yalnızca o kapı açılır ve düzeltilmiş metin yüklenebilir. Değerlendirme tamamlandıysa ya da çalışma hakem onaylı sayıldıysa metin kilitlenir: yazar dahil kimse içeriği değiştiremez.',
          'The text of a work is not always editable. It is in one of three states. Where no report has yet arrived the text is open and the author corrects it freely. Where a reviewer has asked for revision, only that door opens and the corrected text may be uploaded. Where the assessment is complete, or the work counts as reviewer approved, the text is locked: no one, the author included, can change the content.'
        ) ?></p>
        <p><?= k_c(
          'Bunun ilk gerekçesi bilimseldir: hakemin okuduğu metnin sonradan sessizce değişmesi, hem bilimsel kaydı hem de hakemin emeğini geçersiz kılar. İkinci gerekçesi güvenliktir: bir yazarın erişim parolası çalınırsa, kötü niyetli biri yayımlanmış ve onaylanmış bir çalışmanın içeriğini değiştiremez. Kilitli bir metinde düzeltme yalnızca editöre başvurularak, gerekçesiyle ve tarihiyle kayda geçerek yapılır. Ayrıca her metnin bir özet değeri hesaplanır ve her sürüm kaydedilir; bir metnin sonradan değiştirilip değiştirilmediği bu iz üzerinden gösterilebilir.',
          'The first ground for this is scholarly: if the text a reviewer read changes silently afterwards, both the scholarly record and the reviewer\'s labour are voided. The second is security: if an author\'s password is stolen, a malicious party cannot alter the content of a published and approved work. In a locked text a correction is made only through the editor, entering the record with its grounds and its date. A hash is also computed for every text and every version is kept; whether a text was altered afterwards can be shown from that trail.'
        ) ?></p>
        <h3><?= k_c('Doğrulama ve geçiş dönemi', 'Verification and the transitional period') ?></h3>
        <p><?= k_c(
          'Doğrulamayı bir editör verir ve adı kayda geçer; belge yüklenmez. Sistemin ilk döneminde, henüz kimsenin adıyla arkasında durmadığı kişilerin katılımına izin verilir; bu geçiş dönemi <b>31 Aralık 2027</b> tarihinde sona erer. O tarihten sonra doktorası bir editör tarafından doğrulanmamış hiç kimse ne hakem olarak atanabilir ne de şerh düşebilir. Verilmiş bir doğrulama geri alınmaz: kuralın kendisi değişse bile, daha önce doğrulanmış olan doğrulanmış kalır.',
          'Confirmation is given by an editor and that name is recorded; no document is uploaded. During the system\'s first period, participation is permitted to those behind whom no one yet stands by name; this transitional period ends on <b>31 December 2027</b>. After that date no one whose doctorate has not been confirmed by an editor may be assigned as a reviewer or leave a note. A confirmation once given is never withdrawn: even if the rule itself changes, whoever was confirmed stays confirmed.'
        ) ?></p>
      </section>

      <!-- 15 BİLİNEN AÇIKLAR -->
      <!-- 15 ÇEVİRİ VE DİLLER -->
      <section id="ceviri">
        <h2><span class="no">15</span><?= k_c('Çeviri ve diller', 'Translation and languages') ?></h2>
        <p><?= k_c(
          'Bir çalışmanın hakem değerlendirmesinden geçtiği dil, o çalışmanın kayıtlı dilidir. Hakemler o dildeki metni okumuş, kararlarını o metin için vermiştir. Başka bir dildeki metin, ne kadar iyi olursa olsun, hakemlerin okuduğu metin değildir. Bu ayrım bu sistemde her yerde korunur.',
          'The language in which a work passed peer review is that work\'s language of record. The reviewers read the text in that language and gave their decisions for that text. A text in another language, however good, is not the text the reviewers read. This distinction is preserved everywhere in this system.'
        ) ?></p>

        <h3><?= k_c('Çalışmanın dili', 'The language of the work') ?></h3>
        <p><?= k_c(
          'Bir araştırmacı çalışmasını kendi dilinde gönderebilir; bu bir istisna değil, olağan yoldur. Gönderim sırasında çalışmanın dili seçilir ve bu dil kayda geçer, künyede yazılır. İnsan kendi ana dilinde daha ince düşünür ve daha doğru anlatır; yabancı bir dilde yazmak zorunda kalan araştırmacı çoğu zaman söylemek istediğini değil, söyleyebildiğini yazar. Ayrıntılı gerekçe bildirinin "Dil üzerine" bölümündedir.',
          'A researcher may submit their work in their own language; this is the ordinary path, not an exception. The language of the work is chosen at submission, entered in the record and stated in the metadata. A person thinks more finely and states things more exactly in their first language; a researcher obliged to write in a foreign language often writes not what they meant to say but what they were able to say. The full reasoning is set out in the declaration, under "On language".'
        ) ?></p>
        <p><?= k_c(
          'Listede diliniz yoksa iletişim sayfasından bildirin; dil eklenir. Kimse dili listede bulunmadığı için başka bir dile geçmek zorunda bırakılmaz.',
          'If your language is not on the list, write to us from the contact page and it will be added. No one is made to switch language because theirs is missing.'
        ) ?></p>

        <?php /* KÜNYE DİLİ VE GENİŞLETİLMİŞ ÖZET.
                 ÖLÇÜLEN KUSUR — 14 Ağustos 2026. Bu kural bir BAŞVURUYU
                 400 ile REDDEDİYOR (api/index.php, /basvuru ucu) ama
                 yayın ilkelerinde tek satırı yoktu. Ana sayfa "Bütün
                 kurallar tek belgede, herkese açık" diyor; en sert
                 kurallardan biri o belgede yoksa o söz tutulmuyor
                 demektir. Yazılmayan bir kural, ancak reddedilince
                 öğrenilen bir kuraldır.

                 EŞİK BURAYA ELLE YAZILMAZ. tg_genis_ozet_ayar()'dan
                 okunur; ayar değiştiği gün bu sayfa da değişir. İki
                 yere yazılsaydı bir gün sayfa 500, sunucu 700 derdi. */ ?>
        <h3><?= k_c('Künye dili ve genişletilmiş özet', 'The citation language and the extended abstract') ?></h3>
        <?php
          $__gz  = function_exists('tg_genis_ozet_ayar') ? tg_genis_ozet_ayar() : ['zorunlu' => false, 'en_az_kelime' => 500, 'hedef_kelime' => 750];
          $__kd  = function_exists('tg_kunye_dili') ? tg_kunye_dili() : 'en';
          $__kda = function_exists('tg_kunye_dil_adi') ? tg_kunye_dil_adi() : 'İngilizce';
          $__az  = (int)($__gz['en_az_kelime'] ?? 500);
          $__hd  = (int)($__gz['hedef_kelime'] ?? 750);
        ?>
        <p><?= k_cd(
          'Kurul, çalışmanın kendi dilinin yanında bir <b>künye dili</b> belirler; bugün bu dil: <b>%1</b>. Çalışmanın dili künye dilinden başkaysa, künye dilinde <b>en az %2 kelimelik</b> bir genişletilmiş özet istenir (hedeflenen uzunluk %3 kelimedir). Bu koşul karşılanmadan başvuru alınmaz; sunucu da tarayıcı da aynı sayıyı arar.',
          'The board sets a <b>citation language</b> alongside the work\'s own language; today that language is <b>%1</b>. Where the language of the work differs from the citation language, an extended abstract of <b>at least %2 words</b> in the citation language is required (the intended length is %3 words). A submission is not accepted until this is met; the server and the browser look for the same number.',
          $__kda, k_sayi($__az), k_sayi($__hd)
        ) ?></p>
        <p><?= k_cd(
          'Bu, çalışmanın <b>%1</b> dilinde yazılmasını istemek değildir; çalışma kendi dilinde yazılır, hakemlikten kendi dilinde geçer ve kayıtlı dili odur. İstenen tek şey, o çalışmayı okuyamayacak bir araştırmacının ne yapıldığını anlayabileceği kadar metindir. Yükün eşit dağılmadığı açıktır ve kurul bunu bilerek kabul etmiştir: künye dilinde yazan bu yükü hiç taşımaz. Karşılığında, kendi dilinde yazan kişi yayının kendisinden vazgeçmek zorunda kalmaz. Yazar bu özeti yapay zekâ ile de hazırlayabilir; başvuru ekranında bunun için hazır yönergeler verilir. Beyan etmek yine zorunludur.',
          'This is not a demand that the work <b>be written in %1</b>; the work is written in its own language, reviewed in its own language, and that is its language of record. All that is asked is enough text for a researcher who cannot read the work to understand what was done. The burden plainly falls unevenly and the board accepted that knowingly: whoever writes in the citation language never carries it. In return, whoever writes in their own language is not made to give up publication itself. The author may prepare this abstract with the help of AI; ready prompts are offered on the submission screen. Declaring it remains obligatory.',
          $__kda
        ) ?></p>

        <h3><?= k_c('Yazarın kendi çevirisi', 'The author\'s own translation') ?></h3>
        <p><?= k_c(
          'Yazar, çalışmasının başka bir dildeki sürümünü sunabilir. Yazarın onayladığı çeviri aynı tamganın altında ayrı bir dil sürümü olarak yayımlanır; künyesinde hangi dilin hakemlikten geçtiği ve bu metnin yazar tarafından sunulan bir çeviri olduğu yazar. Yazar çeviriyi hazırlarken yapay zekâdan yararlanabilir. Yararlanması yasak değildir; beyan etmemesi kabul edilemez ve sorumluluk her hâlde yazarındır. Yapay zekâ sayfasındaki ölçü burada da geçerlidir: araç metnin biçimine dokunabilir, iddiasına dokunamaz.',
          'An author may supply a version of their work in another language. A translation approved by the author is published as a separate language version under the same tamga; its record states which language passed review and that this text is a translation supplied by the author. The author may use artificial intelligence in preparing it. Doing so is not prohibited; failing to declare it is not acceptable, and responsibility rests with the author in every case. The measure set out on the artificial intelligence page applies here too: the tool may touch the form of the text, not its claim.'
        ) ?></p>

        <h3><?= k_c('Makine çevirisi: yayın değil, okuma yardımı', 'Machine translation: a reading aid, not a publication') ?></h3>
        <p><?= k_c(
          'Sistem, yazarın sunmadığı dillerde makine çevirisi sunabilir. Sunduğunda bu çeviri bir yayın sayılmaz, bir okuma yardımı sayılır ve altı koşula bağlıdır:',
          'The system may offer machine translation in languages the author has not supplied. When it does, that translation does not count as a publication but as a reading aid, and is bound by six conditions:'
        ) ?></p>
        <ol>
          <li><?= k_c('Sayfanın üstünde, kapatılamayan bir şeritle <b>makine çevirisi</b> olduğu yazar.', 'A banner at the top of the page, which cannot be dismissed, states that this is a <b>machine translation</b>.') ?></li>
          <li><?= k_c('Yazara atfedilmez. Yazarın adı çevirinin altında değil, özgün metnin altında durur.', 'It is not attributed to the author. The author\'s name stands beneath the original text, not beneath the translation.') ?></li>
          <li><?= k_c('Alıntılanabilir sayılmaz; künyeye, kaynakçaya ve arşiv dışa aktarımına girmez.', 'It is not citable; it does not enter the record, the reference list or the archive export.') ?></li>
          <li><?= k_c('Arama motorlarına kalıcı sayfa olarak açılmaz. Aksi hâlde çeviri, özgün metnin önüne geçer.', 'It is not opened to search engines as a permanent page. Otherwise the translation would displace the original.') ?></li>
          <li><?= k_c('Özgün metne dönüş bağlantısı her zaman ekrandadır.', 'A link back to the original is always on screen.') ?></li>
          <li><?= k_c('Hangi çeviri motorunun hangi tarihte ürettiği yazılır.', 'The translation engine used and the date it produced the text are stated.') ?></li>
        </ol>

        <h3><?= k_c('Üçüncü kişilerin çevirisi', 'Translations by third parties') ?></h3>
        <p><?= k_c(
          'CC BY 4.0 lisansı herkese çeviri yapma hakkı verir ve bu hak kısıtlanmaz; isteyen çeviriyi yapar, kaynak göstererek istediği yerde yayımlar. Ancak bu arşiv, yazarın adını taşıyan bir metnin altına yazarın görmediği bir çeviriyi koymaz. Bir çevirinin buraya girmesi için yazarın onayı ve çevirmenin adı gerekir; çevirmen künyede kendi adıyla görünür.',
          'The CC BY 4.0 licence gives everyone the right to translate and that right is not restricted; anyone may translate and publish the result wherever they wish with attribution. This archive, however, does not place beneath a text bearing an author\'s name a translation that author has not seen. For a translation to enter here, the author\'s approval and the translator\'s name are required; the translator appears in the record under their own name.'
        ) ?></p>

        <h3><?= k_c('İki kesin sınır', 'Two firm limits') ?></h3>
        <p><?= k_c(
          '<b>Gizlilik ve yazarın onayı.</b> Yayımlanmış metinler için çeviri serbesttir; onlar zaten herkese açıktır. Hakem sürecindeki bir metin ise <b>yalnızca yazarın açık onayıyla</b> ve yalnızca o çalışma için seçilmiş motora gönderilir. Bu kural, önceki mutlak yasağın yerini almıştır: eski hüküm hakemin çalışmayı kendi dilinde okumasını da imkânsız kıldığı için tutulamıyordu ve tutulamayan bir söz, verilmemiş bir sözden kötüdür. Yazar onay vermezse çalışma yalnızca kendi dilinde ve köprü dilinde değerlendirilir; bu bir kusur sayılmaz ve değerlendirmeyi hiçbir biçimde etkilemez. Gönderilen her metin, hangi motora ve hangi tarihte gönderildiğiyle kayda geçer. Motoru sağlayan taraf, metni model eğitmek için kullanamayacağını yazılı olarak kabul etmedikçe bu yola hiç başvurulmaz.',
          '<b>Confidentiality and the author\'s consent.</b> Translation is unrestricted for published texts; they are open to everyone already. A text under review is sent <b>only with the author\'s express consent</b>, and only to the engine chosen for that work. This rule replaces the earlier absolute prohibition: the old provision also made it impossible for a reviewer to read a work in their own language, so it could not be kept, and a promise that cannot be kept is worse than one never given. If the author does not consent, the work is assessed only in its own language and in the bridge language; this is not counted against it and does not affect the assessment in any way. Every text sent is recorded with the engine it went to and the date. This route is not taken at all unless the provider has accepted in writing that the text may not be used to train a model.'
        ) ?></p>
        <p><?= k_c(
          '<b>Hakemlik köprüsü.</b> Hedef şudur: hakem çalışmayı kendi dilinde okur, raporunu kendi dilinde yazar; yazar o raporu kendi dilinde görür. Kendi dilinde sürüm yoksa hakem <b>köprü dilindeki</b> sürümü okur; köprü dili başlangıçta İngilizcedir ve destekçiler arttıkça çoğalır. Raporun kaydı hakemin yazdığı dildedir. Yayımlanırken hem özgün dili hem çevirisi yan yana ve etiketli durur; hiçbir metin bir başkasının yerine geçmez. Aynı kural editöryal notlar, kurul oyu gerekçeleri ve şerhler için de geçerlidir: bir çalışmanın sayfasında görünen her metin çevrilir, çünkü bu katman eksik bırakılırsa açık hakemlik bir dilde açık, öteki dilde kapalı olur.',
          '<b>The review bridge.</b> The aim is this: a reviewer reads the work in their own language, writes the report in their own language, and the author sees that report in theirs. Where no version exists in the reviewer\'s language, they read the version in the <b>bridge language</b>; the bridge language is English at first and grows as sponsors come. The record of the report is in the language the reviewer wrote it in. On publication the original and the translation stand side by side and labelled; no text takes the place of another. The same rule holds for editorial notes, panel vote reasons and post publication notes: every text that appears on a work\'s page is translated, because if this layer is left out, open review is open in one language and closed in another.'
        ) ?></p>
        <p><?= k_c(
          '<b>Bağımlılık.</b> Çeviri hizmeti sistemin zorunlu bir parçası değildir. Ücretli bir servise bağlı kalmak, "her zaman ücretsiz" sözüyle çelişir. Bu yüzden çeviri kapalı gelir; bir anahtar tanımlanmazsa sistem yalnızca çeviri sunmaz, başka hiçbir şeyi bozulmaz. Anahtar bir gün geçersiz olursa arşiv olduğu gibi çalışmayı sürdürür.',
          '<b>Dependence.</b> The translation service is not a required part of the system. Remaining tied to a paid service contradicts the promise that this will always be free. Translation therefore ships switched off; if no key is defined the system simply does not offer translation and nothing else breaks. Should a key one day cease to work, the archive carries on exactly as it is.'
        ) ?></p>
        <p><?= k_c(
          '<b>Dil destekçisi.</b> Bir dilin sisteme girmesi, o dile çevirecek bir motora ve onun masrafına bağlıdır; ikisini de bir kurum ya da bir kişi üstlenebilir. Destekçinin adı o dilin sayfalarında kalıcı olarak ve o dilde yazılı bir teşekkürle durur; neyin çevrildiği, kimin karşıladığı ve ne harcandığı açıkça yazılır. Sınır ötekilerle aynıdır: dil desteği hiçbir editöryal etki taşımaz. Bir dilin destekçisi, o dildeki bir çalışmanın kabulüne, hakem seçimine ya da yayın sırasına karışamaz.',
          '<b>Language sponsors.</b> A language entering the system depends on an engine that translates into it and on the cost of that engine; an institution or a person may take on both. The sponsor\'s name stands permanently on the pages of that language with a note of thanks written in that language; what was translated, who paid for it and how much was spent are stated openly. The boundary is the same as elsewhere: sponsoring a language carries no editorial influence. A sponsor cannot affect the acceptance of a work in that language, the choice of reviewers or the order of publication.'
        ) ?></p>
      </section>

      <section id="acik">
        <h2><span class="no">16</span><?= k_c('Bu ilkelerin bilinen açıkları', 'Known weaknesses of these policies') ?></h2>
        <p><?= k_c(
          'Yukarıda yazılan hiçbir kural, bu sistemin kusursuz olduğu anlamına gelmez. Bir sistemin şeffaflık iddiası varsa, ilk şeffaf olacağı şey kendi kusurlarıdır. Bu yüzden sistemin bilinen bütün zayıf noktaları, her biri için ne yapıldığı ve hangilerinin hâlâ kapatılamadığı ayrı bir sayfada, herkese açık olarak yazılıdır. Kapatılmamış olanlar o listeden çıkarılmaz.',
          'None of the rules written above means this system is without fault. If a system claims transparency, the first thing it must be transparent about is its own faults. Every known weakness of the system, what was done about each, and which remain unclosed, is therefore written out on a separate page, open to all. Those that remain are not removed from that list.'
        ) ?></p>
        <div class="satir">
          <a class="d d-ikinci" href="<?= k_esc(k_bag('/acikliklar.php')) ?>"><?= k_c('Sistemin açıklarını oku', 'Read the weaknesses of the system') ?></a>
          <a class="d d-ikinci" href="<?= k_esc(k_bag('/iletisim.php')) ?>"><?= k_c('Gördüğünüz bir açığı bildirin', 'Report a weakness you have seen') ?></a>
        </div>
      </section>

      <div class="kutu kutu-kut">
        <?= k_c(
          'Bu ilkeler zaman içinde güncellenebilir. Güncelleme, yürürlüğe girdiği tarihten sonraki başvuruları bağlar; daha önce yayımlanmış çalışmalar gönderildikleri tarihteki ilkelere tabidir.',
          'These policies may be updated over time. An update binds submissions made after the date it takes effect; work published earlier remains subject to the policies in force on the date it was submitted.'
        ) ?>
      </div>

      <div class="satir">
        <a class="d d-vurgu" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Başvuru formuna geç', 'Go to the application form') ?></a>
        <a class="d d-ikinci" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Çalışmalara göz at', 'Browse the works') ?></a>
      </div>

    </div>
  </div>
</section>

<?php
k_son(<<<JS
<script>
(function(){
  /* Bölüm bölüm okunan belgenin gezinme düğmelerini ortak betik üretir
     ve yalnızca kendi adlarını verir. Dizgede düğme ailesi tektir, bu
     yüzden üretilen düğmeler burada o aileye bağlanır. Betik
     çalışmazsa düğme de yoktur; sayfa baştan sona akar. */
  /* Düğmelere biçim sınıfları BURADAN eklenmiyor artık. Eskiden bu
     betik k/kutadgu.js'in ürettiği düğmelere 'd d-ikinci' ekliyordu ve
     eklenene kadar düğme tarayıcının kendi düğmesi olarak duruyordu:
     koyu temada gri zemin, açık metin, 4.17 karşıtlık (wcag-kapi 1.4.3
     bunu yakaladı). Sınıflar artık düğmenin üretildiği satırda
     veriliyor; düğmeyi üreten yer ile biçimini veren yer aynı olsun
     diye. */

  /* Bütün bölümler açıkken okunan yeri içindekiler listesinde
     işaretler. İşaret dizgenin kendi durumudur (aria-current); ayrı bir
     sınıf tanımlanmaz. */
  var baglar = Array.prototype.slice.call(document.querySelectorAll('#icindekiler a'));
  var bolumler = baglar.map(function(a){ return document.querySelector(a.getAttribute('href')); });
  if (!('IntersectionObserver' in window)) return;
  var g = new IntersectionObserver(function(kayit){
    kayit.forEach(function(k){
      if (!k.isIntersecting) return;
      var i = bolumler.indexOf(k.target);
      if (i < 0) return;
      baglar.forEach(function(a, j){
        if (i === j) a.setAttribute('aria-current', 'true');
        else a.removeAttribute('aria-current');
      });
    });
  }, { rootMargin: '-90px 0px -70% 0px', threshold: 0 });
  bolumler.forEach(function(b){ if (b) g.observe(b); });
})();
</script>
JS);
