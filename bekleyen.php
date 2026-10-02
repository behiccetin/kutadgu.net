<?php
/* =====================================================================
   KUTADGU - Hakem aranan çalışmalar / Works seeking reviewers
   Bir yazar çalışmasını yükledikten sonra, isteyen herkes o çalışmayı
   değerlendirmeye gönüllü olabilir. Kapı davetle değil, istekle açılır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/endeks.php';

/* ---------------------------------------------------------------------
   OTURUM, HİÇBİR ÇIKTI VERİLMEDEN ÖNCE AÇILIR.
   Bu sayfa bugüne kadar oturumu hiç tanımıyordu: girişli bir yazar kendi
   çalışmasının kartında da "Bu çalışmayı değerlendirmek istiyorum"
   düğmesini görüyor, formdaki kimliği kendisi yazıyordu. Sayfanın
   politikası değişmiyor, gönüllülük girişsiz de açık; değişen, sistemin
   kimi gördüğünü biliyorsa artık onu bilmesi.
   --------------------------------------------------------------------- */
$hsO = null;
if (is_file(__DIR__ . '/k/hesap.php')) { require_once __DIR__ . '/k/hesap.php'; $hsO = hs_oturum(); }

$en = k_en();

$bekleyen = [];
foreach (k_yazilar() as $y) {
    if (!tg_hakem_araniyor($y)) continue;
    $bekleyen[] = $y;
}
/* Sıra tek kaynaktan gelir: raporu az olan önde, eşitlikte EN UZUN
   BEKLEYEN önde. Eskiden ikinci ölçüt "en yeni" idi ve en uzun
   bekleyen çalışma listenin dibinde kalıyordu. */
$bekleyen = tg_bekleyen_sirala($bekleyen);
$hedef = (int)tg_ayar('hakem_hedef', 3);

$ekBas = <<<CSS
<style>
/* Kartlar en çok iki sütuna dizilir. Üç sütunda özet iki satıra
   iniyor ve düğmelerin metni alt alta kırılıyordu. */
/* minmax(320px,...) 320 piksellik bir ekranda kabından geniş bir
   ray ister ve kart sayfayı 16 piksel taşırır (ölçüldü). En küçük
   ölçü, kabın kendisinden büyük olamaz: min() bunu söyler. */
.bk-liste{grid-template-columns:repeat(auto-fit,minmax(min(320px,100%),1fr));margin-block:var(--b-5)}
.bk-k{display:flex;flex-direction:column;gap:var(--b-3)}
.bk-k h3{margin:0}
.bk-k h3 a{color:var(--metin)}
.bk-k h3 a:hover{color:var(--kut)}
.bk-yazar{margin:0;font-size:var(--y-3);color:var(--metin-2)}
.bk-ozet{margin:0;font-size:var(--y-3);color:var(--metin-2)}
.bk-anah{display:flex;flex-wrap:wrap;gap:var(--b-1)}
/* Eylemler kartın dibine oturur: bilgisi az olan kartta düğme
   yukarıda kalırsa satırdaki kartlar birbirini tutmuyor. */
.bk-alt{margin-top:auto}
.bk-say{font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2)}
/* Kendi çalışmasının kartında düğmenin yerini alan cümle. Uyarı rengi
   verilmedi: bu bir engel bildirimi değil, bir durum bildirimi. */
.bk-benim{font-size:var(--y-3);color:var(--metin-2)}
/* Oturumdan gelen ve değiştirilemeyen alanlar, serbest alanlardan
   ayırt edilebilsin diye sönük zeminle durur. */
.gv input[readonly]{background:var(--zemin-2,var(--yuzey));color:var(--metin-2);cursor:not-allowed}
.gv-kilit{font-size:var(--y-2);color:var(--metin-2);margin:var(--b-1) 0 0}

/* Sayılı anlatım. Ölçü karakterle verilir: bu bölüm okunan bir
   metindir ve satır uzunluğu sütun genişliğine bırakılmaz. */
.bk-nasil{max-width:74ch;margin-top:var(--b-7);border-top:1px solid var(--cizgi);padding-top:var(--b-5)}
.bk-nasil ol{counter-reset:bn;list-style:none;padding:0;margin:var(--b-5) 0;display:grid;gap:var(--b-3)}
.bk-nasil li{counter-increment:bn;position:relative;background:var(--yuzey);
  border:1px solid var(--cizgi);border-radius:var(--r-3);
  padding:var(--b-4) var(--b-4) var(--b-4) var(--b-7);font-size:var(--y-4)}
.bk-nasil li::before{content:counter(bn);position:absolute;left:var(--b-4);top:var(--b-4);
  width:var(--b-5);height:var(--b-5);border-radius:50%;background:var(--kut-zemin);color:var(--kut);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700;display:grid;place-items:center}

/* ---- Başvuru penceresi ----
   Dizgede kaplama katmanı yok; bu yüzden pencere burada tanımlı
   kalıyor. Karartma rengi de bir belirteçten türetilir, ayrı bir
   renk açılmaz. */
.gv-ov{position:fixed;inset:0;z-index:400;display:none;overflow-y:auto;padding:var(--b-kenar);
  background:color-mix(in srgb,var(--marka-koyu) 62%,transparent)}
.gv-ov.acik{display:block}
/* Pencerenin genişliği sayfanınkinden bağımsızdır: içindeki form
   iki sütuna sığsın, daha fazlasına yayılmasın diye. */
.gv{max-width:640px;margin-inline:auto;position:relative;
  background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-4);
  padding:var(--b-5);box-shadow:var(--g-3)}
.gv h2{margin:0 0 var(--b-2);padding-right:var(--b-7)}
.gv-mak{color:var(--metin-2);font-size:var(--y-3);margin:0 0 var(--b-4)}
.gv-kapa{position:absolute;top:var(--b-3);right:var(--b-3)}
.gv-sifat{margin-top:var(--b-2)}
.gv-eylem{margin-top:var(--b-5)}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Hakem aranan çalışmalar', 'Works seeking reviewers'),
    'yol'    => '/bekleyen.php',
    'aciklama' => k_c(
        'Değerlendirilmeyi bekleyen çalışmalar. Bir çalışmayı okumak ve değerlendirmek isteyen herkes gönüllü olabilir.',
        'Works awaiting assessment. Anyone willing to read and assess a work may volunteer.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Açık çağrı', 'Open call') ?></span>
      <h1><?= k_c('Hakem aranan çalışmalar', 'Works seeking reviewers') ?></h1>
      <p><?= k_c(
      'Bir yazar çalışmasını yükledikten sonra hakem beklemek zorunda değildir: isteyen herkes o çalışmayı değerlendirmeye gönüllü olabilir. Klasik dergilerde bu kapı kapalıdır, hakemi yalnızca editör çağırır. Burada açıktır. Gönüllü olduğunuz bir değerlendirme, yazarın önerisi sayılmaz; bu yüzden <b>bağımsız hakemlik</b> olarak kaydedilir ve çalışmanın hakem onayı almasında geçerli sayılır.',
      'An author who has uploaded a work does not have to wait for a reviewer: anyone willing may volunteer to assess it. In conventional journals this door is closed and only an editor calls a reviewer. Here it is open. An assessment you volunteer for does not count as the author\'s proposal, and is therefore recorded as an <b>independent review</b> and counts towards the work\'s approval.'
    ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <?php if (!$bekleyen): ?>
      <div class="bos-durum"><?= k_c(
        'Şu an hakem bekleyen bir çalışma yok. Bu iyi bir haber: gönderilen çalışmaların hepsine hakem ulaşmış durumda. Yeni bir çalışma geldiğinde bu sayfada görünecektir.',
        'No work is waiting for a reviewer at the moment. That is good news: every submitted work has found its reviewers. When a new work arrives it will appear on this page.'
      ) ?></div>
    <?php else: ?>
      <?php /* BAŞLIK SIRASI ATLAMAZ. Sayfanın h1'inden sonra kartların
               h3'ü geliyordu; arada h2 yoktu ve ekran okuyucu "bir
               düzey atlandı" der. Liste bir bölümdür ve adı vardır;
               adı ekranda başlığın altındaki cümlede zaten yazılı
               olduğu için görsel olarak tekrar edilmez, ama yapıda
               durur. */ ?>
      <h2 class="gorsel-gizli"><?= k_c('Hakem bekleyen çalışmalar', 'Works awaiting a reviewer') ?></h2>
      <p class="metin-sonuk"><?= count($bekleyen) ?> <?= k_c('çalışma değerlendirilmeyi bekliyor', 'works are awaiting assessment') ?>.</p>
      <div class="dizi bk-liste">
        <?php foreach ($bekleyen as $y):
          $slug = (string)($y['slug'] ?? '');
          $bas  = k_alan($y, 'baslik');
          $alan = (string)($y['alan'] ?? '');
          $anah = k_alan($y, 'anahtar');
        ?>
        <article class="kart bk-k">
          <div class="satir">
            <?php /* Rozet metni tek kaynaktan gelir. Burada elle yazılmış
                     olduğu için İngilizcesi zamanla kaymış ve aynı çalışma
                     arşivde "Seeking reviewers", bu sayfada "Reviewers
                     sought" adı taşır olmuştu; okur iki ayrı durum sanıyordu.
                     Sayfanın geri kalanındaki tg_hakem_araniyor() ölçütü
                     doğrudur, ona dokunulmadı: burada düzelen ad, ölçüt
                     değil. */ ?>
            <span class="rz rz-kut"><?= k_esc(tg_asama_metni('aranan', $en)) ?></span>
            <?php /* Bekleme süresi rozeti. Gönüllü hakem arayan bir
                     sayfada en önemli bilgi, çalışmanın NE KADARDIR
                     beklediğidir: iki yıldır bekleyen bir çalışma ile
                     dün gelen bir çalışma aynı aciliyette değildir. */ ?>
            <?php $bkG = (int)($y['_bek'] ?? -1); if ($bkG >= 0): ?>
              <span class="rz rz-cizgi bk-sure"><?= k_esc(tg_gun_metni($bkG, $en)) ?><?= k_c(' bekliyor', ' waiting') ?></span>
            <?php endif; ?>
            <?php if ($alan !== ''): ?><span class="rz rz-cizgi"><?= k_esc(kt_alan_ad($alan, $en)) ?></span><?php endif; ?>
          </div>
          <h3><a href="<?= k_esc(k_bag(k_yazi_yolu($y))) ?>"><?= k_esc($bas) ?></a></h3>
          <p class="bk-yazar"><?= k_esc(k_yazarlar($y)) ?> · <?= k_esc(k_tarih((string)($y['tarih'] ?? ''))) ?></p>
          <?php if (trim(strip_tags(k_alan($y, 'ozet'))) !== ''): ?>
            <p class="bk-ozet"><?= k_esc(k_ozet(k_alan($y, 'ozet'), 190)) ?></p>
          <?php endif; ?>
          <?php if ($anah !== ''): ?>
          <div class="bk-anah">
            <?php foreach (array_slice(array_filter(array_map('trim', preg_split('/[;,]/u', $anah))), 0, 5) as $a): ?>
              <span class="rz rz-cizgi"><?= k_esc($a) ?></span>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="satir bk-alt">
            <?php /* Kendi çalışması olan kişiye düğme HİÇ BASILMAZ.
                     Gizlemek yetmezdi: sayfada duran bir düğme, uçtan
                     dönen hata ne olursa olsun "belki geçer" diyen bir
                     kapıdır. Yerine konan cümle ceza değil bilgi:
                     kişinin yanlış bir şey yaptığı söylenmiyor, orada
                     yapacak bir şeyi olmadığı söyleniyor. */
                  if ($hsO !== null && tg_yazar_mi($y, $hsO)): ?>
              <span class="bk-benim"><?= k_c('Bu sizin çalışmanız.', 'This is your own work.') ?></span>
            <?php else: ?>
            <button class="d d-vurgu d-kucuk" type="button" data-gonullu="<?= k_esc($slug) ?>" data-bas="<?= k_esc($bas) ?>">
              <?= k_c('Bu çalışmayı değerlendirmek istiyorum', 'I would like to assess this work') ?>
            </button>
            <?php endif; ?>
            <a class="d d-sessiz d-kucuk" href="<?= k_esc(k_bag(k_yazi_yolu($y))) ?>"><?= k_c('Önce oku', 'Read it first') ?></a>
            <span class="bk-say ara-oto"><?= (int)$y['_tam'] ?>/<?= $hedef ?> <?= k_c('hakem', 'reviewers') ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="bk-nasil">
      <h2><?= k_c('Gönüllü hakemlik nasıl işler', 'How volunteering works') ?></h2>
      <ol>
        <li><?= k_c(
          'Değerlendirmek istediğiniz çalışmayı okursunuz. Metnin tamamı herkese açıktır; okumadan başvurmanız istenmez.',
          'You read the work you wish to assess. The full text is open to everyone; you are not asked to apply without reading it.'
        ) ?></li>
        <li><?= k_c(
          'Adınızı, unvanınızı, ORCID kimliğinizi ve hangi sıfatla değerlendireceğinizi bildirirsiniz. Alanınızın çalışmanın alanıyla birebir örtüşmesi gerekmez; yöntem ya da veri hakemi olarak da katkı verebilirsiniz.',
          'You give your name, your title, your ORCID and the capacity in which you will assess. Your field does not have to match the work\'s field; you may contribute as a method or data reviewer as well.'
        ) ?></li>
        <li><?= k_c(
          'Başvurunuz editöre gider. Karar yazarın değil editörün elindedir; böylece yazar kendi hakemini seçmiş olmaz ve değerlendirmeniz bağımsız sayılır.',
          'Your application goes to an editor. The decision rests with the editor, not the author; this way the author has not chosen their own reviewer and your assessment counts as independent.'
        ) ?></li>
        <li><?= k_c(
          'Onaylanırsa değerlendirme sayfanızın bağlantısı ve erişim şifreniz e-posta ile gelir. Raporunuz adınızla birlikte yayımlanır ve gönderdikten sonra değiştirilemez.',
          'If approved, the link to your assessment page and your access code arrive by e mail. Your report is published under your name and cannot be changed once sent.'
        ) ?></li>
        <?php /* CÜMLE TEK KAYNAKTAN. Burada elle yazılmıştı ve 13
                 Ağustos'ta kaldırılmış bir kuralı anlatıyordu; aynı
                 ekranda, birkaç santim aşağıda "çalışmayı herkes
                 gönderebilir" yazıyordu. Sayfa kendi kendisiyle
                 çelişiyordu. Gerekçenin tamamı ortak.php'de
                 tg_hakemlik_yazarlik_cumlesi()'nin başındadır. */ ?>
        <li><?= k_esc(tg_hakemlik_yazarlik_cumlesi(k_en())) ?></li>
      </ol>
    </div>

   </div>
   <?= k_belge_yan([], [
     ['tr' => 'Kim gönüllü olabilir', 'en' => 'Who may volunteer',
      'ic' => k_c('Doktora derecesi bulunan herkes. Alanın çalışmanın alanıyla birebir örtüşmesi gerekmez; yöntem ya da veri hakemi olarak da katkı verebilirsiniz.',
                  'Anyone holding a doctorate. Your field need not match the work\'s exactly; you may also contribute as a methods or data reviewer.')],
     /* Kutunun BAŞLIĞI da kurala bağlıdır: başlık iddiayı taşır, gövde
        onu açar. Başlık sabit kalsaydı kutu "kapısıdır" der, içi
        "değildir" derdi — ölçülen kusur tam olarak buydu. */
     ['tr' => tg_hakemlik_yazarlik_basligi(false), 'en' => tg_hakemlik_yazarlik_basligi(true),
      'ic' => k_esc(tg_hakemlik_yazarlik_cumlesi(k_en()))
            . (tg_yazarlik_kosulu_kisa(k_en()) !== ''
               ? '<br><span class="metin-sonuk">' . k_esc(tg_yazarlik_kosulu_kisa(k_en())) . '</span>' : '')],
     ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
      'ic' => '<a href="' . k_esc(k_bag('/hakemlik.php')) . '">' . k_c('Hakemlik süreci', 'Peer review process') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/ilkeler.php#hakem')) . '">' . k_c('Hakemlerin yükümlülükleri', 'Reviewer obligations') . '</a>'],
   ]) ?>
  </div>
</section>

<div class="gv-ov" id="gvOv" role="dialog" aria-modal="true" aria-labelledby="gvBas">
  <div class="gv">
    <button class="d d-sessiz d-im gv-kapa" type="button" data-gv-kapat aria-label="<?= k_c('Kapat', 'Close') ?>">&#10005;</button>
    <h2 id="gvBas"><?= k_c('Hakemliğe gönüllü olun', 'Volunteer to review') ?></h2>
    <p class="gv-mak" id="gvMak"></p>

    <?php
    /* ---- GİRİŞLİ KİŞİNİN KİMLİĞİ FORMDAN SORULMAZ ----
       Uç, oturum varsa ad ve adresi zaten hesaptan alıyor. Form bunları
       yine de boş ve serbest sorsaydı, kullanıcı yazdığının kullanıldığını
       sanır ve reddedildiğinde nedenini anlamazdı. Alanlar bu yüzden
       hesaptan doldurulur ve readonly olur; nedeni de yanlarında yazar.
       disabled değil readonly: disabled bir alan hiç okunmaz ve kişi
       kendi kimliğini göremez olurdu.
       ORCID hesapta boşsa serbest bırakılır; uçtaki kural da böyle. */
    $gvAd    = $hsO !== null ? trim((string)($hsO['ad'] ?? '')) : '';
    $gvMail  = $hsO !== null ? trim((string)($hsO['eposta'] ?? '')) : '';
    $gvOrcid = $hsO !== null ? trim((string)($hsO['orcid'] ?? '')) : '';
    $gvKilit = $hsO !== null;
    ?>
    <?php if ($gvKilit): ?>
    <p class="gv-kilit"><?= k_c(
      'Ad, e-posta ve ORCID alanları hesabınızdan geliyor ve değiştirilemez: başvurunun kimden geldiği formdaki yazıyla değil hesabınızla belirlenir. Bilgileriniz eksik ya da yanlışsa hesap sayfanızdan düzeltin.',
      'Your name, e mail and ORCID come from your account and cannot be changed here: who the application comes from is settled by your account, not by what is typed in the form. If anything is missing or wrong, correct it on your account page.'
    ) ?></p>
    <?php endif; ?>

    <div class="alan-ikili">
      <?php /* Unvan ve kurum da hesaptan doldurulur ama KİLİTLENMEZ:
               ikisi de kimlik değil, o başvuruya ilişkin beyandır ve
               kişi kurumunu hesabında güncellemeden başka bir kurum
               adına başvurabilir. Kimlik denetimi bu iki alana bakmaz. */ ?>
      <div class="alan"><label for="gvUnvan"><?= k_c('Unvan', 'Title') ?></label><?php /* Seçim, düz metin değil: tek kaynak tg_unvan_secenek. Kayıtlı unvan listede yoksa kendi seçeneği olarak eklenir, kaybolmaz. */ ?><select id="gvUnvan"><?= tg_unvan_secenek($en, $hsO !== null ? hs_unvan_ad((string)($hsO['unvan'] ?? ''), $en) : '') ?></select></div>
      <div class="alan"><label for="gvAd"><?= k_c('Ad ve soyad', 'Name and surname') ?></label><input type="text" id="gvAd" autocomplete="name" value="<?= k_esc($gvAd) ?>"<?= $gvKilit ? ' readonly' : '' ?>></div>
    </div>
    <div class="alan-ikili">
      <div class="alan"><label for="gvEposta"><?= k_c('E-posta', 'E mail') ?></label><input type="email" id="gvEposta" autocomplete="email" placeholder="ornek@universite.edu.tr" value="<?= k_esc($gvMail) ?>"<?= $gvKilit ? ' readonly' : '' ?>></div>
      <div class="alan"><label for="gvOrcid">ORCID</label><input type="text" id="gvOrcid" placeholder="0000-0000-0000-0000" value="<?= k_esc($gvOrcid) ?>"<?= ($gvKilit && $gvOrcid !== '') ? ' readonly' : '' ?>></div>
    </div>
    <label for="gvKurum"><?= k_c('Kurum', 'Institution') ?></label>
    <input type="text" id="gvKurum" placeholder="<?= k_c('Üniversite, Bölüm', 'University, Department') ?>" value="<?= k_esc($hsO !== null ? trim((string)($hsO['kurum'] ?? '')) : '') ?>">

    <label><?= k_c('Hangi sıfatla değerlendireceksiniz?', 'In what capacity will you assess?') ?></label>
    <div class="onay-dizi-2 gv-sifat">
      <?php foreach (tg_sifatlar($en) as $k => $ad): ?>
      <label class="onay-kart"><input type="checkbox" name="gvSifat" value="<?= k_esc($k) ?>"> <span><?= k_esc($ad) ?></span></label>
      <?php endforeach; ?>
    </div>

    <label for="gvYetkin"><?= k_c('Bu çalışmayı değerlendirmekte kendinizi neden yetkin görüyorsunuz?', 'Why do you consider yourself competent to assess this work?') ?></label>
    <textarea id="gvYetkin" placeholder="<?= k_c('Bu cümleler raporunuzla birlikte yayımlanır.', 'These sentences are published together with your report.') ?>"></textarea>

    <?php /* DOKTORA: TEK STANDART — 14 Ağustos 2026 kurul kararı.
             Burada iki seçenek vardı: Türkiye için e-Devlet barkod kodu,
             yurt dışı için kamusal alan adı. Yani sistem, hakemlerinin
             tek bir ülkeden gelmeyeceğini bilerek o ülkeye özel bir kapı
             kurmuştu ve o kapı listenin başındaydı. Gerekçenin tamamı
             ortak.php'de tg_dogrulama_editor()'ün başındadır.

             Belge artık İSTENMİYOR. Bu formu dolduran kişi bir çalışmaya
             hakem olmak için başvuruyor; doktorasını, başvuruyu kabul
             eden editör ya da bir baş editör adıyla doğrular. Kanıt
             sunmak isteyene kapı açık ama zorunlu değil: ORCID
             kimliği ve derecenin göründüğü bir adres yazılabilir,
             ikisi de editörün işini kolaylaştırır, ikisi de kimseyi
             geri çevirmez. */ ?>
    <label><?= k_c('Doktoranız', 'Your doctorate') ?></label>
    <p class="ipucu"><?= k_c(
      'Hakemlik için doktora ya da eşdeğeri bir derece aranır. Belge istemiyoruz: başvurunuzu kabul eden editör ya da bir baş editör doktoranızı adıyla doğrular ve o ad kayda geçer. Aşağıdakiler isteğe bağlıdır; yazarsanız editörün işi tek bakışa iner.',
      'Reviewing requires a doctorate or an equivalent degree. We do not ask for a document: the editor who accepts your application, or a chief editor, confirms your doctorate by name and that name is recorded. The fields below are optional; filling them reduces the editor\'s work to a single glance.'
    ) ?></p>
    <div id="gvYabanci">
      <label for="gvBelgeUrl"><?= k_c('Derecenizin göründüğü bir adres (isteğe bağlı)', 'An address where your degree is visible (optional)') ?></label>
      <input type="text" id="gvBelgeUrl" placeholder="https://...">
      <label for="gvBelgeKurum"><?= k_c('Dereceyi veren kurum (isteğe bağlı)', 'Institution that awarded it (optional)') ?></label>
      <input type="text" id="gvBelgeKurum" placeholder="<?= k_c('Üniversite ya da yetkili kurum', 'University or competent authority') ?>">
      <p class="ipucu"><?= k_c(
        'Kurumun kendi sayfası en açık kanıttır, ama şart değildir: alan adı bir dereceyi göstermez, ona bakan yine bir editördür. Hangi ülkeden olduğunuz hiçbir şeyi değiştirmez.',
        'A page on the institution\'s own site is the clearest evidence, but it is not required: a domain name does not show a degree, and it is an editor who looks at it either way. Which country you are in changes nothing.'
      ) ?></p>
    </div>

    <div class="kutu kutu-kut"><?= k_c(
      'Bu sistemde kör hakemlik uygulanmaz: adınız, kararınız ve raporunuz çalışmayla birlikte açıkça yayımlanır. Başvurunuz editör onayına gider; onaylanırsa değerlendirme bağlantınız e-posta ile gelir.',
      'Review here is not blind: your name, your decision and your report are published openly with the work. Your application goes to an editor; if approved, the link to your assessment arrives by e mail.'
    ) ?></div>

    <div class="d-kume gv-eylem">
      <button class="d d-vurgu" type="button" id="gvGonder"><?= k_c('Başvuruyu gönder', 'Send the application') ?></button>
      <button class="d d-sessiz" type="button" data-gv-kapat><?= k_c('Vazgeç', 'Cancel') ?></button>
    </div>
    <div class="form-msj" id="gvMsj"></div>
  </div>
</div>

<?php
$S = json_encode([
  'mak'     => k_c('Çalışma: ', 'Work: '),
  'eAd'     => k_c('Adınızı yazın.', 'Please write your name.'),
  'eUnvan'  => k_c('Hakemlik için en az "Dr." unvanı gerekir.', 'At least a doctoral title is required to review.'),
  'ePosta'  => k_c('Geçerli bir e-posta adresi girin.', 'Enter a valid e mail address.'),
  'eOrcid'  => k_c('Geçerli bir ORCID girin (0000-0000-0000-0000).', 'Enter a valid ORCID (0000-0000-0000-0000).'),
  'eSifat'  => k_c('En az bir sıfat seçin.', 'Choose at least one capacity.'),
  'eYetkin' => k_c('Yetkinliğinizi birkaç cümleyle yazın.', 'Please write a few sentences on your competence.'),
  'eKod'    => k_c('Barkodlu belgenizin doğrulama kodunu girin.', 'Enter the verification code of your barcoded document.'),
  'eBelgeUrl' => k_c('Belgenin sorgulanabileceği adresi girin.', 'Enter the address at which the document can be verified.'),
  'gonder'  => k_c('Gönderiliyor...', 'Sending...'),
  'baglanti'=> k_c('Bağlantı kurulamadı. Tekrar deneyin.', 'Could not connect. Please try again.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$betik = <<<JS
<script>
(function(){
  var S = {$S};
  function \$(id){return document.getElementById(id);}
  var ov=\$('gvOv'), slug='';

  function ac(s, bas){
    slug=s; \$('gvMak').textContent = S.mak + bas;
    \$('gvMsj').textContent=''; \$('gvMsj').className='form-msj';
    \$('gvGonder').disabled=false;
    ov.classList.add('acik'); document.body.style.overflow='hidden';
    setTimeout(function(){ \$('gvUnvan').focus(); }, 60);
  }
  function kapat(){ ov.classList.remove('acik'); document.body.style.overflow=''; }

  Array.prototype.forEach.call(document.querySelectorAll('[data-gonullu]'), function(b){
    b.addEventListener('click', function(){ ac(b.getAttribute('data-gonullu'), b.getAttribute('data-bas')||''); });
  });
  Array.prototype.forEach.call(document.querySelectorAll('[data-gv-kapat]'), function(b){
    b.addEventListener('click', kapat);
  });
  ov.addEventListener('click', function(e){ if(e.target===ov) kapat(); });
  document.addEventListener('keydown', function(e){ if(e.key==='Escape' && ov.classList.contains('acik')) kapat(); });

  function unvanYeterli(u){ return /(prof|do[çc]|dr)\\b/i.test((u||'').trim()); }

  /* Belge türü seçimi kalktı: tek yol var, seçilecek bir şey yok. */
  function orcidTemiz(o){
    var s=String(o||'').replace(/[^0-9Xx]/g,'').toUpperCase();
    if(s.length!==16) return '';
    return s.slice(0,4)+'-'+s.slice(4,8)+'-'+s.slice(8,12)+'-'+s.slice(12,16);
  }

  \$('gvGonder').addEventListener('click', function(){
    var m=\$('gvMsj');
    function hata(t,el){ m.textContent=t; m.className='form-msj err'; if(el) el.focus(); }
    var ad=\$('gvAd').value.trim(), unvan=\$('gvUnvan').value.trim(),
        eposta=\$('gvEposta').value.trim(), kurum=\$('gvKurum').value.trim(),
        yetkin=\$('gvYetkin').value.trim();
    if(!ad) return hata(S.eAd, \$('gvAd'));
    if(!unvanYeterli(unvan)) return hata(S.eUnvan, \$('gvUnvan'));
    if(!/.+@.+\\..+/.test(eposta)) return hata(S.ePosta, \$('gvEposta'));
    var orcid=orcidTemiz(\$('gvOrcid').value);
    if(!orcid) return hata(S.eOrcid, \$('gvOrcid'));
    \$('gvOrcid').value=orcid;
    var sifat=[];
    Array.prototype.forEach.call(document.querySelectorAll('input[name=gvSifat]:checked'), function(c){sifat.push(c.value);});
    if(!sifat.length) return hata(S.eSifat);
    if(yetkin.length<40) return hata(S.eYetkin, \$('gvYetkin'));

    /* Adres ve kurum İSTEĞE BAĞLIDIR; boş olmaları başvuruyu
       durdurmaz. Burada bir zorunluluk bırakmak, formda "isteğe
       bağlı" yazıp sunucuda reddetmek olurdu. */
    var bUrl = \$('gvBelgeUrl').value.trim(), bKurum = \$('gvBelgeKurum').value.trim();

    \$('gvGonder').disabled=true; m.textContent=S.gonder; m.className='form-msj';
    fetch('/api/hakem-gonullu',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({slug:slug,ad:ad,unvan:unvan,eposta:eposta,kurum:kurum,orcid:orcid,sifat:sifat,yetkinlik:yetkin,
        belge_tur:'yabanci',belge_url:bUrl,belge_kurum:bKurum})})
      .then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok){ m.textContent=d.mesaj||'OK'; m.className='form-msj ok'; }
        else { m.textContent=(d&&d.hata)||S.baglanti; m.className='form-msj err'; \$('gvGonder').disabled=false; }
      }).catch(function(){ m.textContent=S.baglanti; m.className='form-msj err'; \$('gvGonder').disabled=false; });
  });
})();
</script>
JS;
k_son($betik);
