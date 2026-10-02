<?php
/* =====================================================================
   KUTADGU - Hakemlik süreci / Peer review process
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

$kabul = (int)tg_ayar('kabul_gecerli', 2);
$ret   = (int)tg_ayar('ret_donusum', 2);

/* Hakem dizini ve rapor sayıları */
$hakemler = [];
foreach (k_yazilar() as $y) {
    foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
        if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
        $ad = trim((string)($h['ad'] ?? '')); if ($ad === '') continue;
        if (!isset($hakemler[$ad])) $hakemler[$ad] = ['say' => 0, 'kurum' => '', 'unvan' => ''];
        $hakemler[$ad]['say']++;
        $p = $h['profil'] ?? null;
        if (is_array($p)) {
            if ($hakemler[$ad]['kurum'] === '') $hakemler[$ad]['kurum'] = trim((string)($p['kurum'] ?? ''));
            if ($hakemler[$ad]['unvan'] === '') $hakemler[$ad]['unvan'] = trim((string)($p['unvan'] ?? ''));
        }
    }
}
uasort($hakemler, fn($a, $b) => $b['say'] <=> $a['say']);

$ekBas = <<<CSS
<style>
/* Sayfada iki numaralı kart dizisi var: roller ve akış. Dizinin
   kendisi dizgenin .dizi ızgarasıdır; burada yalnızca kartın üstündeki
   sıra numarası tanımlanır. Numara metnin bir parçası değildir, bu
   yüzden işaretlemede değil sayaçla üretilir. */
.adim{counter-reset:a}
.adim article{position:relative;padding-top:var(--b-6)}
/* Numara kartın sol dolgusuyla hizalanır: clamp değeri dizgedeki
   .kart dolgusunun ta kendisidir. */
.adim article::before{counter-increment:a;content:"0" counter(a);position:absolute;
  top:var(--b-3);left:clamp(16px,2vw,22px);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700;color:var(--kut);letter-spacing:.08em}
.adim h3{margin:0 0 var(--b-1);font-size:var(--y-5)}
.adim p{margin:0;font-size:var(--y-3);color:var(--metin-2)}

/* Dört karar yan yana. Aralarındaki tek piksellik boşluk ızgaranın
   zemininden gelir: dört ayrı kenarlık yerine tek bir çerçeve. */
.karar{display:grid;gap:1px;background:var(--cizgi);border:1px solid var(--cizgi);
  border-radius:var(--r-3);overflow:hidden;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));
  margin-top:var(--b-5)}
.karar div{background:var(--yuzey);padding:var(--b-4)}
.karar h4{margin:var(--b-2) 0 var(--b-1)}
.karar p{margin:0;font-size:var(--y-2);color:var(--metin-2)}

/* Kartın içindeki başlık sayfa başlığı ölçüsünde değil kartın
   ölçüsünde durur. */
.kart > h2{margin:0 0 .3em}
.kart ul{margin:var(--b-2) 0 0;padding-left:var(--b-5);display:grid;gap:var(--b-2);
  font-size:var(--y-3);color:var(--metin-2)}
/* Metnin arasına giren kutular ve düğme kümeleri paragraf ritmini
   sürdürür; boşluk satır içi öznitelikle değil buradan gelir. */
.kap > .kutu,.kart .satir{margin-top:var(--b-4)}
.kap > .kutu + .kutu{margin-top:var(--b-3)}

/* Zebra şeridi: art arda gelen bölümler aynı kâğıt üstünde birbirine
   karışmasın diye biri ikincil yüzeye alınır. */
.serit{background:var(--yuzey-2);border-block:1px solid var(--cizgi)}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Hakemlik süreci', 'Peer review process'),
    'yol' => '/hakemlik.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu\'da hakemlik açıktır: hakem adıyla imzalar, rapor herkese açık yayımlanır.',
        'Peer review at Kutadgu is open: reviewers sign their names and reports are published for everyone to read.'
    ),
]);
?>

<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
   <div>
    <span class="bas-ust"><?= k_c('Süreç', 'Process') ?></span>
    <h1><?= k_c('Hakem, raporunun arkasında adıyla durur', 'The reviewer stands behind the report by name') ?></h1>
    <p><?= k_c(
      'Kör hakemlik, hakemi korur ama okuru karanlıkta bırakır. Bu sistemde hakem yazarı bilir, yazar hakemi bilir ve rapor çalışmanın sayfasında imzasıyla yayımlanır. Sonuç olarak değerlendirme de en az çalışmanın kendisi kadar denetlenebilir bir belge hâline gelir.',
      'Blind review protects the reviewer but leaves the reader in the dark. In this system the reviewer knows the author, the author knows the reviewer, and the report is published on the work\'s page under the reviewer\'s signature. The assessment thereby becomes a document at least as verifiable as the work itself.'
    ) ?></p>
   </div>
  </div>
</section>

<!-- ================= ROLLER ================= -->
<section class="bolum" id="roller">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Roller', 'Roles') ?></span>
    <?php /* BAŞLIK VE GİRİŞ DE KOŞULA BAĞLI.
             "Okurdan hakeme, hakemden yazara" ve "roller sırayla
             kazanılır" cümleleri, yazarlığın hakemliğin üstünde bir
             basamak olduğunu söyler. Doktora şartı kalktıktan sonra bu
             doğru değil: yazarlık bir basamak değil, ayrı bir yoldur.
             Kartların içindeki cümleleri düzeltip başlığı eski hâlinde
             bırakmak, düzeltmenin yarısını yapmak olurdu — okur önce
             başlığı okur. */ ?>
    <?php if (tg_yazarlik_hakemlik_sarti() || tg_yazarlik_doktora_sarti()): ?>
    <h2><?= k_c('Okurdan hakeme, hakemden yazara', 'From reader to reviewer, from reviewer to author') ?></h2>
    <p class="metin-sonuk"><?= k_c(
      'Bu sistemde roller satın alınmaz, sırayla kazanılır. Sıra bilinçli olarak böyle kuruldu: bir metni değerlendirmiş olan kişi, kendi metninin nasıl değerlendirileceğini de bilir.',
      'Roles here are not bought; they are earned in sequence. The sequence is deliberate: someone who has assessed another\'s text also understands how their own will be assessed.'
    ) ?></p>
    <?php else: ?>
    <h2><?= k_c('Okurdan hakeme; yazarlık ayrı bir yol', 'From reader to reviewer; authorship is a separate road') ?></h2>
    <p class="metin-sonuk"><?= k_c(
      'Hakemlikte roller satın alınmaz, sırayla kazanılır: hakem olmak için doktora belgesi aranır ve belge doğrulanır. Yazarlık bu sıranın son basamağı değildir; çalışmayı herkes gönderebilir, kararı editör verir.',
      'In reviewing, roles are not bought; they are earned in sequence: becoming a reviewer calls for a doctoral credential, and the credential is verified. Authorship is not the last rung of that ladder: anyone may submit a work, and an editor decides.'
    ) ?></p>
    <?php endif; ?>

    <div class="dizi dizi-3 adim">
      <article class="kart">
        <h3><?= k_c('Okur', 'Reader') ?></h3>
        <p><?= k_c('Hiçbir kayıt gerekmez. Bütün çalışmalar, hakem raporlarıyla birlikte herkese açıktır.', 'No registration is needed. Every work, together with its reviewer reports, is open to all.') ?></p>
      </article>
      <article class="kart">
        <h3><?= k_c('Aday hakem', 'Prospective reviewer') ?></h3>
        <p><?= k_c('Doktora ya da eşdeğeri unvanını belgeleyen okur, hakemliğe aday olur. Aday olmadan önce sistemde en az iki çalışmayı okumanız önerilir; sistemin nasıl işlediğini en iyi böyle görürsünüz.', 'A reader who documents a doctorate or an equivalent may stand as a reviewer. Reading at least two works here before you do so is recommended; it is the clearest way to see how the system works.') ?></p>
      </article>
      <article class="kart">
        <h3><?= k_c('Hakem', 'Reviewer') ?></h3>
        <p><?= k_c('Unvanı doğrulanan aday hakem olur. Kendisine gelen daveti değerlendirir, raporunu adıyla yazar ve kararını verir.', 'Once the title is verified the candidate becomes a reviewer, assesses the invitation received, writes the report under their own name and gives a decision.') ?></p>
      </article>
      <?php /* YAZAR BASAMAĞI: CÜMLE KURALDAN OKUNUR, ELLE YAZILMAZ.
               Burada "En az bir hakemlik sürecini tamamlayan kişi yazar
               olur" yazıyordu. 13 Ağustos 2026 kurul kararından sonra bu
               cümle YANLIŞtı: çalışmayı herkes gönderebilir, kararı
               editör verir. Sistem uygulamadığı bir kuralı duyuruyordu.

               Cümle artık tg_yazarlik_kosulu_kisa()'dan gelir; koşul
               geri açılırsa cümle de kendiliğinden döner ve buraya bir
               şey yazmak gerekmez. Yazarın hakem DAVET etme yetkisi
               koşuldan bağımsızdır, o yüzden ayrı cümlede durur. */ ?>
      <article class="kart">
        <h3><?= k_c('Yazar', 'Author') ?></h3>
        <?php $hkKisa = tg_yazarlik_kosulu_kisa(k_en()); ?>
        <?php if ($hkKisa !== ''): ?><p><?= k_esc($hkKisa) ?></p><?php endif; ?>
        <p><?= k_c('Çalışması sisteme giren kişi, dilerse ona hakem aranmasını ister; hakemsiz bırakmayı da seçebilir.', 'Once a work enters the system its author may ask for reviewers to be sought, or may choose to leave it unreviewed.') ?></p>
      </article>
    </div>

    <?php /* İKİ KUTU DA KOŞULA BAĞLANDI.

             Birincisi "Hakemlik yapmadan yazar olunamaz" diyordu.
             İkincisi "31 Aralık 2027 tarihine kadar bu sıra
             esnetilebilir" diyordu ve tarihi ELLE yazılmıştı, yani ayar
             değiştiği gün cümle eski tarihte donacaktı.

             Kural bugün aranmıyor. Aranmayan bir kuralı "olmazsa olmaz"
             diye anlatmak, insanları var olmayan bir engelle caydırmak
             olur; bu sistemde bunun bir adı var: uygulanmayan kuralı
             duyurmak. Metin silinmedi, koşula bağlandı; kurul kararı
             değişirse eskisi aynen döner ve tarihi ayardan okur. */ ?>
    <?php if (tg_yazarlik_hakemlik_sarti()): ?>
    <div class="kutu kutu-lac"><?= k_c(
      '<b>Neden bu sıra?</b> Hakemlik yapmadan yazar olunamaz. Bunun nedeni bir engel koymak değil, bir denge kurmaktır: sistemde okunan her çalışmanın arkasında, bir başkasının çalışmasını okumuş bir emek vardır. Hakemlik gönüllüdür, ücretlendirilmez ve bir kez yapılması yeterlidir.',
      '<b>Why this order?</b> One cannot become an author without having reviewed. This is not a barrier but a balance: behind every work read here stands the labour of someone who read another\'s work. Reviewing is voluntary, unpaid, and doing it once is enough.'
    ) ?></div>
    <?php else: ?>
    <div class="kutu kutu-lac"><?= k_c(
      '<b>Hakemlik bir kapı değil, bir emektir.</b> Çalışma göndermek için hakemlik yapmış olmak aranmaz. Buna karşılık burada okunan her çalışmanın arkasında, bir başkasının metnini okumuş birinin emeği vardır. Hakemlik gönüllüdür, ücretlendirilmez ve sistemi ayakta tutan şey odur.',
      '<b>Reviewing is labour, not a gate.</b> Having reviewed is not required in order to submit a work. Even so, behind every work read here stands someone who read another person\'s text. Reviewing is voluntary, unpaid, and it is what holds the system up.'
    ) ?></div>
    <?php endif; ?>

    <?php /* Geçiş dönemi kutusu ancak KAPANACAK bir sıra varsa
             anlamlıdır: aranmayan bir koşulun geçici olarak
             esnetilmesinden söz etmek, olmayan bir kapıyı aralamaktır.
             Tarih artık tg_kurulus_bitis_ad()'dan okunur. */ ?>
    <?php $hkBitis = tg_kurulus_bitis_ad(k_en());
          if (tg_kurulus_donemi() && !tg_yazarlik_hakemlik_sarti() && tg_yazarlik_doktora_sarti()): ?>
    <?php /* k_esc YOK: cümle <b> taşıyor ve öteki kutular da ham
             basılıyor; burada kaçırmak etiketi ekrana yazdırırdı. */ ?>
    <div class="kutu kutu-kut"><?= $hkBitis !== ''
      ? tg_cd(
          '<b>Geçiş dönemi.</b> Sistem yeni olduğu için %1 tarihine kadar bu sıra esnetilir; henüz hakemlik yapmamış araştırmacılar da çalışma gönderebilir. Amaç, kapıyı daraltmak değil, alanına hâkim araştırmacıları sisteme kazandırmaktır.',
          '<b>Transitional period.</b> Because the system is new, this sequence is relaxed until %1; researchers who have not yet reviewed may also submit work. The aim is not to narrow the door but to bring in researchers who know their field.',
          k_en(), $hkBitis)
      : k_c(
          '<b>Geçiş dönemi.</b> Sistem yeni olduğu için bu sıra esnetilir; henüz hakemlik yapmamış araştırmacılar da çalışma gönderebilir.',
          '<b>Transitional period.</b> Because the system is new, this sequence is relaxed; researchers who have not yet reviewed may also submit work.') ?></div>
    <?php endif; ?>
  </div>
</section>

<section class="bolum serit">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Akış', 'Flow') ?></span>
    <h2><?= k_c('Bir çalışma hakemden nasıl geçer', 'How a work passes through review') ?></h2>
    <div class="dizi dizi-3 adim">
      <article class="kart">
        <h3><?= k_c('Davet', 'Invitation') ?></h3>
        <p><?= k_c('Çalışma ön denetimi geçince konuya yakın hakemlere davet gönderilir. Davet, çalışmanın başlığını ve özetini içerir.', 'Once a work passes the desk check, an invitation is sent to reviewers close to the subject. The invitation includes the title and abstract.') ?></p>
      </article>
      <article class="kart">
        <h3><?= k_c('İnceleme', 'Assessment') ?></h3>
        <p><?= k_c('Hakem tam metne kendi bağlantısıyla erişir; savı, yöntemi ve kaynak kullanımını ayrı ayrı değerlendirir.', 'The reviewer reaches the full text through a link of their own and assesses the argument, the method and the use of sources separately.') ?></p>
      </article>
      <article class="kart">
        <h3><?= k_c('Rapor', 'Report') ?></h3>
        <p><?= k_c('Rapor yazılır ve karar verilir. Rapor, hakemin adıyla birlikte yayımlanacağı bilinerek hazırlanır.', 'The report is written and a decision is taken. It is prepared in the knowledge that it will be published under the reviewer\'s name.') ?></p>
      </article>
      <article class="kart">
        <h3><?= k_c('Sonuç', 'Outcome') ?></h3>
        <p><?= k_c('Yazar raporu görür, gerekirse düzeltip yeniden yükler. Yayımlanan çalışmanın sayfasında rapor kalıcı olarak yer alır.', 'The author sees the report and, if needed, revises and resubmits. The report remains permanently on the published work\'s page.') ?></p>
      </article>
    </div>
  </div>
</section>

<section class="bolum serit">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Kararlar', 'Decisions') ?></span>
    <h2><?= k_c('Dört karardan biri', 'One of four decisions') ?></h2>
    <div class="karar">
      <div>
        <span class="rz rz-yes"><span class="nokta"></span><?= k_c('Kabul', 'Accept') ?></span>
        <h3><?= k_c('Değişiklik gerekmez', 'No change required') ?></h3>
        <p><?= k_c('Metin bulunduğu hâliyle yayımlanabilir.', 'The text may be published as it stands.') ?></p>
      </div>
      <div>
        <span class="rz rz-lac"><span class="nokta"></span><?= k_c('Küçük revizyon', 'Minor revision') ?></span>
        <h3><?= k_c('Sınırlı düzeltme', 'Limited correction') ?></h3>
        <p><?= k_c('Anlatım, biçim ya da kaynak düzeyinde düzeltme istenir.', 'Correction is requested at the level of expression, format or sources.') ?></p>
      </div>
      <div>
        <span class="rz rz-kut"><span class="nokta"></span><?= k_c('Büyük revizyon', 'Major revision') ?></span>
        <h3><?= k_c('Yöntem ya da sav gözden geçirilmeli', 'Method or argument must be revisited') ?></h3>
        <p><?= k_c('Çalışma yeniden değerlendirmeye alınır.', 'The work is returned for a further assessment.') ?></p>
      </div>
      <div>
        <span class="rz rz-kir"><span class="nokta"></span><?= k_c('Ret', 'Reject') ?></span>
        <h3><?= k_c('Hakemli yolda yayımlanamaz', 'Cannot be published on the reviewed track') ?></h3>
        <p><?= k_c('Metin silinmez; hakemsiz yazı olarak açık kalır.', 'The text is not deleted; it remains open as a non reviewed piece.') ?></p>
      </div>
    </div>
    <div class="kutu kutu-kut"><?= k_c(
      '<b>' . $kabul . ' olumlu rapor</b> alan bir çalışma bu sistemde <b>hakem onaylı</b> sayılır. Bu, çalışmanın iki bağımsız uzmanın adıyla imzaladığı olumlu değerlendirmeden geçtiği anlamına gelir; bir dizin taraması ya da kurumsal denklik anlamına gelmez. <b>' . $ret . ' ret</b> alan çalışma silinmez; hakemsiz yazıya döner ve aldığı raporlarla birlikte açık kalır.',
      'A work receiving <b>' . $kabul . ' positive reports</b> counts as <b>reviewer approved</b> within this system. That means it has passed a favourable assessment signed by two independent experts; it does not mean indexing or institutional equivalence. A work receiving <b>' . $ret . ' rejections</b> is not deleted; it moves to the non reviewed track and remains open together with the reports it received.'
    ) ?></div>
  </div>
</section>

<section class="bolum">
  <div class="kap dizi dizi-2">
    <div class="kart">
      <span class="bas-ust"><?= k_c('Hakem olmak', 'Becoming a reviewer') ?></span>
      <h2><?= k_c('Aranan nitelikler', 'What is looked for') ?></h2>
      <ul>
        <li><?= k_c('En az doktora derecesi.', 'At least a doctoral degree.') ?></li>
        <li><?= k_c('Değerlendirilecek konuda yayımlanmış çalışma.', 'Published work in the subject to be assessed.') ?></li>
        <li><?= k_c('Geçerli bir ORCID kimliği.', 'A valid ORCID identifier.') ?></li>
        <li><?= k_c('Raporun adla yayımlanmasını kabul.', 'Acceptance that the report will be published under one\'s name.') ?></li>
      </ul>
      <p class="ipucu"><?= k_c(
        'Hakemlik gönüllüdür ve ücretlendirilmez. Başvuru için, yayımlanmış çalışmalarınıza ulaşılabilecek bir bağlantıyla birlikte gönderim formundaki iletişim bölümünü kullanabilirsiniz.',
        'Reviewing is voluntary and unpaid. To apply, use the contact section of the submission form together with a link through which your published work can be reached.'
      ) ?></p>
      <div class="satir">
        <a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag('/ilkeler.php#hakem')) ?>"><?= k_c('Hakem yükümlülükleri', 'Reviewer obligations') ?></a>
      </div>
    </div>

    <div class="kart">
      <span class="bas-ust"><?= k_c('Hakem girişi', 'Reviewer sign in') ?></span>
      <h2><?= k_c('Davet aldıysanız', 'If you received an invitation') ?></h2>
      <p class="metin-sonuk"><?= k_c(
        'Davet e postasındaki bağlantı sizi doğrudan değerlendirme sayfasına götürür. Bağlantıyı kaybettiyseniz, e posta adresinizle birlikte yeniden gönderilmesini isteyebilirsiniz.',
        'The link in the invitation e mail takes you straight to the assessment page. If you have lost the link you may ask for it to be sent again along with your e mail address.'
      ) ?></p>
      <div class="satir">
        <a class="d d-vurgu d-kucuk" href="<?= k_esc(k_bag('/hakem.html')) ?>"><?= k_c('Değerlendirme sayfası', 'Assessment page') ?></a>
      </div>
      <p class="ipucu"><?= k_c(
        'Değerlendirme sayfasına yalnızca davet bağlantısıyla girilebilir; genel bir kullanıcı hesabı yoktur.',
        'The assessment page can only be entered through the invitation link; there is no general user account.'
      ) ?></p>
    </div>
  </div>
</section>

<?php if ($hakemler): ?>
<section class="bolum serit">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Dizin', 'Index') ?></span>
    <h2><?= k_c('Rapor yazan hakemler', 'Reviewers who have written reports') ?></h2>
    <p class="metin-sonuk"><?= k_c(
      'Aşağıdaki araştırmacılar bu sistemde en az bir çalışmayı adlarıyla değerlendirmiştir. Raporların tamamı ilgili çalışmanın sayfasından okunabilir.',
      'The researchers below have assessed at least one work in this system under their own name. All reports can be read from the relevant work\'s page.'
    ) ?></p>
    <div class="dizi dizi-2">
      <?php foreach ($hakemler as $ad => $h): ?>
        <div class="kk">
          <?= k_yuz($ad, '', 40, 'kk-yuz') ?>
          <span class="kk-ic">
            <b><?= k_esc($ad) ?></b>
            <span class="kk-kurum"><?= $h['kurum'] !== '' ? k_esc($h['kurum']) . ' · ' : '' ?><?= (int)$h['say'] ?> <?= k_c('rapor', $h['say'] === 1 ? 'report' : 'reports') ?></span>
          </span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php k_son(); ?>
