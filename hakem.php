<?php
/* =====================================================================
   KUTADGU - Hakem değerlendirme formu / Reviewer assessment form
   Belirteçle (t=) açılır. API sözleşmesi eski hakem.html ile aynıdır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';
require_once __DIR__ . '/k/duzenleyici.php';
require_once __DIR__ . '/k/endeks.php';

$ekBas = <<<CSS
<meta name="robots" content="noindex, nofollow">
<style>
/* Bu bir form sayfasıdır: kartlar geniş ekranda yan yana durur, sığmaz
   olunca kendiliğinden alt alta iner. Ölçü ızgaranın kendi ölçüsüdür,
   bir düzen eşiği değildir. */
.hk-dizi{display:grid;gap:var(--b-4);align-items:start;
  grid-template-columns:repeat(auto-fit,minmax(min(430px,100%),1fr))}
.hk-genis{grid-column:1 / -1}
/* Değerlendirilen çalışmanın metni. Sayfanın içinde duran bir okuma
   penceresidir: kendi yüksekliği vardır ve kendi içinde kaydırılır,
   yoksa form yüz ekran aşağı iner. */
.mkmetin{background:var(--zemin);border:1px solid var(--cizgi);border-radius:var(--r-2);
  padding:var(--b-4) var(--b-4);max-height:460px;overflow-y:auto;
  line-height:var(--sh-genis);font-family:var(--serif);font-size:var(--y-5)}
.mkmetin h2,.mkmetin h3{font-size:var(--y-6);margin:1.2em 0 .4em}
.mkmetin mark.hmark{background:var(--kirmizi-zemin);color:var(--kirmizi);
  border-radius:var(--r-1);padding:0 2px}
/* Seçilen yerin üstünde beliren düğme. Sayfayla birlikte kaymaz,
   çünkü işaretlenecek yer ekranda nerede duruyorsa oraya konur. */
.isaret-btn{position:fixed;z-index:150;box-shadow:var(--g-3)}
.isaret-sat{margin-top:var(--b-3)}
.isaret-sat .al{color:var(--kirmizi);font-size:var(--y-3);border-left:3px solid var(--kirmizi);
  padding-left:var(--b-2);margin-bottom:var(--b-2);white-space:pre-wrap}
/* Dizin ve çeyreklik değerlendirmesi */
.ez-kart{border-left:3px solid var(--kut);margin-top:var(--b-3)}
.ez-secim{margin-bottom:var(--b-3)}
.ez-soru{padding:var(--b-3) 0;border-top:1px solid var(--cizgi)}
.ez-soru:first-of-type{border-top:0;padding-top:0}
.ez-soru p{margin:0 0 var(--b-3);font-size:var(--y-3);line-height:var(--sh-orta)}
/* Beşli ölçek. Seçenekler yan yana dizilir ama ölçü sabit değildir:
   sığmadıkları anda alt alta inerler, böylece dar ekranda sözcükleri
   gizlemek gerekmez. */
.ez-olcek{display:grid;gap:var(--b-2);grid-template-columns:repeat(auto-fit,minmax(min(168px,100%),1fr))}
/* Gönderilmiş değerlendirmede verilen not: seçilecek bir şey değil,
   okunacak bir sayıdır. */
.ez-yanit{font-size:var(--y-3);color:var(--kut);font-weight:700}
/* Değerlendirilen çalışmanın başlığı: bir h başlığı değil, forma
   konu olan şeyin adıdır. */
.hk-eser{font-weight:600;font-size:var(--y-6)}
.hk-giris{margin:0 0 var(--b-3);font-size:var(--y-4);line-height:var(--sh-genis)}
.hk-giris-son{margin-bottom:0}
.nitelik-kutu:empty{display:none}
.nitelik-kutu b{display:block;font-size:var(--y-1);letter-spacing:.1em;text-transform:uppercase;
  margin-bottom:var(--b-1)}
.nitelik-kutu ul{margin:var(--b-2) 0 0;padding-left:1.15em}
.nitelik-kutu li{margin:3px 0}
.nitelik-kutu.kutu-yes b{color:var(--yesil)}
.nitelik-kutu.kutu-kut b{color:var(--kut)}
/* Yazar ile hakem yazışması. Aynı sınıf adları makale sayfasında da
   kullanılır; iki yerde aynı şey aynı görünsün. */
.hd-sat{border-left:3px solid var(--cizgi-2);padding-left:var(--b-3);margin-bottom:var(--b-3)}
.hd-yazar{border-left-color:var(--lacivert)}
.hd-hakem{border-left-color:var(--kut)}
.hd-kim{display:block;font-family:var(--ui);font-size:var(--y-2);font-weight:700;
  color:var(--metin-2);margin-bottom:var(--b-1)}
.hd-metin{font-size:var(--y-4);line-height:var(--sh-genis);white-space:pre-wrap}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'baslik' => k_c('Hakem değerlendirme formu', 'Reviewer assessment form'),
    'yol' => '/hakemlik.php',
    'ek_bas' => $ekBas . kd_bas(),
]);
?>

<section class="bolum">
<div class="kap blg">
<div class="blg-ic">
  <span class="bas-ust"><?= k_c('Açık hakemlik', 'Open peer review') ?></span>
  <h1><?= k_c('Hakem değerlendirme formu', 'Reviewer assessment form') ?></h1>

  <div id="yukleniyor" class="yukleniyor"><?= k_c('Yükleniyor...', 'Loading...') ?></div>

  <!-- E-POSTA KİLİDİ -->
  <div id="kilit" class="gizli">
    <div class="kart">
      <h2><?= k_c('Güvenlik doğrulaması', 'Security check') ?></h2>
      <p class="ipucu"><?= k_c(
        'Bu değerlendirme bağlantısı yalnızca size özeldir. Devam etmek için davetin <b>gönderildiği e-posta adresini</b> ve e-postada size iletilen <b>erişim şifresini</b> girin.',
        'This assessment link belongs to you alone. To continue, enter the <b>e mail address the invitation was sent to</b> and the <b>access code</b> given in that e mail.'
      ) ?></p>
      <label for="mail"><?= k_c('E-posta adresiniz', 'Your e mail address') ?></label>
      <input type="email" id="mail" placeholder="ornek@eposta.com" autocomplete="email">
      <label for="sifre"><?= k_c('Erişim şifresi', 'Access code') ?></label>
      <input type="text" id="sifre" placeholder="K7P-3RM" autocomplete="off" autocapitalize="characters">
      <button id="ac" type="button" class="d d-vurgu"><?= k_c('Devam', 'Continue') ?></button>
      <div id="kilitMsj" class="form-msj"></div>
    </div>
  </div>

  <!-- FORM -->
  <div id="form" class="gizli hk-dizi">
    <div class="kart hk-genis">
      <h2><?= k_c('Bu değerlendirme hakkında', 'About this assessment') ?></h2>
      <p class="hk-giris"><?= k_c(
        'Bu çalışmayı değerlendirmeyi kabul ettiğiniz için teşekkür ederiz. Bu sistemde <b style="color:var(--kut)">kör hakemlik uygulanmaz</b>: adınız, kararınız ve raporunuz yayımlanan çalışmayla birlikte açıkça görünür.',
        'Thank you for agreeing to assess this work. <b style="color:var(--kut)">Blind review is not practised</b> in this system: your name, your decision and your report appear openly alongside the published work.'
      ) ?></p>
      <p class="hk-giris hk-giris-son"><?= k_c(
        'Çalışmayı yetersiz bulur ya da reddetmek isterseniz, gerekçelerinizi açıkça yazınız. Çalışma reddedilse dahi sistemde <b>"reddedildi"</b> bilgisi ve gerekçeleriniz gösterilir; çünkü bir çalışma reddedilse bile sizin bakış açınız okuyucuya bir şey kazandırabilir.',
        'If you find the work inadequate or wish to reject it, set out your grounds plainly. Even where a work is rejected, the <b>"rejected"</b> mark and your grounds remain visible; a rejected work read together with its criticism can still teach the reader something.'
      ) ?></p>
    </div>

    <div class="kart">
      <h2><?= k_c('Değerlendirilen çalışma', 'The work under assessment') ?></h2>
      <div id="baslik" class="hk-eser"></div>
      <div id="ozet" class="ipucu"></div>
    </div>

    <div class="kart">
      <h2><?= k_c('Hakem', 'Reviewer') ?></h2>
      <div id="hakemAd"></div>
    </div>

    <div class="kart">
      <h2><?= k_c('Hakem bilgileriniz', 'Your reviewer details') ?></h2>
      <p class="ipucu"><?= k_c(
        'Açık hakemlik gereği bu bilgiler, değerlendirmenizle birlikte çalışmanın sayfasında adınıza tıklandığında herkese açık gösterilir. Doldurmak isteğe bağlıdır; boş bıraktığınız alanlar gösterilmez.',
        'Because review is open, these details are shown publicly when your name is clicked on the work\'s page. Completing them is optional; fields you leave empty are not displayed.'
      ) ?></p>
      <label for="hUnvan"><?= k_c('Unvan', 'Title') ?></label>
      <input type="text" id="hUnvan" placeholder="Prof. Dr. / Doç. Dr. / Dr.">
      <label for="hKurum"><?= k_c('Kurum', 'Institution') ?></label>
      <input type="text" id="hKurum" placeholder="<?= k_c('Üniversite, Bölüm', 'University, Department') ?>">
      <label for="hEposta"><?= k_c('Görünür e-posta (isteğe bağlı)', 'Public e mail (optional)') ?></label>
      <input type="email" id="hEposta" placeholder="ornek@universite.edu.tr" autocomplete="off">
      <label for="hOrcid">ORCID</label>
      <input type="text" id="hOrcid" placeholder="0000-0000-0000-0000">
      <label for="hWeb"><?= k_c('Web / akademik profil (isteğe bağlı)', 'Web or academic profile (optional)') ?></label>
      <input type="text" id="hWeb" placeholder="https://...">
    </div>

    <div class="kart hk-genis">
      <h2><?= k_c('Çalışmanın metni', 'The text of the work') ?></h2>
      <p class="ipucu"><?= k_c(
        'Metinde değişmesini istediğiniz yeri fareyle seçin, çıkan <b style="color:var(--kirmizi)">İşaretle</b> düğmesine basın; seçtiğiniz yer kırmızı işaretlenir ve aşağıda notunuzu yazabilirsiniz. İşaretledikleriniz çalışmanın hakem bölümünde görünür.',
        'Select a passage you want changed, then press the <b style="color:var(--kirmizi)">Mark</b> button that appears; the passage is marked in red and you can write your note below. Your marks appear in the review section of the work.'
      ) ?></p>
      <div id="makaleMetin" class="mkmetin"></div>
      <div id="isaretListe"></div>
    </div>

    <div class="kart hk-genis gizli" id="endeksKart">
      <h2><?= k_c('Yayımlanabilirlik ve dizin değerlendirmesi', 'Publishability and index assessment') ?></h2>
      <p class="ipucu"><?= k_c(
        'Bu çalışmayı kabul ediyorsanız, hangi dizinde ya da hangi çeyreklikteki bir dergide yayımlanabilir nitelikte olduğunu işaretleyin. Her seçim için o dizinin ölçütlerini yoklayan kısa bir değerlendirme açılır. Yalnızca çalışmanın bilim alanına uygun dizinler listelenir.',
        'If you accept this work, mark the index, or the journal quartile, in which it could be published. Each selection opens a short assessment against that index\'s criteria. Only indexes appropriate to the field of the work are listed.'
      ) ?></p>
      <div id="endeksList" class="d-kume"></div>
      <div id="endeksFormlar"></div>
      <div class="kutu kutu-kut"><?= k_c(
        '<b>Dikkat:</b> Bu değerlendirme raporunuzla birlikte bir kez gönderilir ve <b>sonradan değiştirilemez</b>. Gönderdikten sonra bu çalışmayla ilgili süreciniz tamamlanmış olur; yazdığınız bütün notlar ve düzeltmeler rapor olarak çalışmanın sayfasında görünür.',
        '<b>Please note:</b> this assessment is submitted once together with your report and <b>cannot be changed afterwards</b>. Once you submit, your process for this work is complete; all your notes and corrections appear as a report on the work\'s page.'
      ) ?></div>
    </div>

    <div class="kart hk-genis">
      <h2><?= k_c('Hangi sıfatla değerlendiriyorsunuz?', 'In what capacity are you assessing this?') ?></h2>
      <p class="ipucu"><?= k_c(
        'Uzmanlık, diplomanın alanıyla değil çalışmanın konusuyla ilgilidir. Bir fizikçi bir iktisat çalışmasının yöntemine, iktisatçının göremeyeceği bir katkı yapabilir. Bu yüzden alanınızın çalışmanın alanıyla birebir örtüşmesi gerekmez. Hangi sıfatla katkı verdiğinizi seçin; bu bilgi raporunuzla birlikte okuyucuya gösterilir.',
        'Expertise is a matter of the topic, not of the discipline on your diploma. A physicist may contribute to the method of an economics paper in a way an economist could not. Your field therefore does not have to match the field of the work. Choose the capacity in which you are contributing; this is shown to the reader together with your report.'
      ) ?></p>
      <div id="sifatKutu" class="onay-dizi-2">
        <label class="onay-kart"><input type="checkbox" name="sifat" value="konu"><span><b><?= k_c('Konu hakemi', 'Subject reviewer') ?></b><small><?= k_c('Çalışmanın konusunu ve alanyazınını değerlendiriyorum.', 'I am assessing the subject matter and the literature.') ?></small></span></label>
        <label class="onay-kart"><input type="checkbox" name="sifat" value="yontem"><span><b><?= k_c('Yöntem hakemi', 'Method reviewer') ?></b><small><?= k_c('Kurgunun ve yöntemin sağlamlığını değerlendiriyorum.', 'I am assessing the soundness of the design and the method.') ?></small></span></label>
        <label class="onay-kart"><input type="checkbox" name="sifat" value="veri"><span><b><?= k_c('Veri ve istatistik hakemi', 'Data and statistics reviewer') ?></b><small><?= k_c('Veriyi, çözümlemeyi ve sayısal sonuçları değerlendiriyorum.', 'I am assessing the data, the analysis and the numerical results.') ?></small></span></label>
        <label class="onay-kart"><input type="checkbox" name="sifat" value="dil"><span><b><?= k_c('Dil ve kurgu hakemi', 'Language and form reviewer') ?></b><small><?= k_c('Anlatımı, düzeni ve kaynak gösterimini değerlendiriyorum.', 'I am assessing the writing, the structure and the referencing.') ?></small></span></label>
      </div>
      <label for="yetkinlik"><?= k_c('Bu çalışmayı değerlendirmekte kendinizi neden yetkin görüyorsunuz? (Bir iki cümle; raporunuzla birlikte yayımlanır.)', 'Why do you consider yourself competent to assess this work? (A sentence or two; published together with your report.)') ?></label>
      <textarea id="yetkinlik" rows="3" placeholder="<?= k_c('Örn. Karmaşık sistemler üzerine çalışıyorum; bu makalenin kullandığı ağ çözümlemesi yöntemi doğrudan alanıma giriyor.', 'e.g. I work on complex systems; the network analysis method used in this paper falls directly within my area.') ?>"></textarea>
    </div>

    <div class="kart hk-genis">
      <h2><?= k_c('Tekrarlanabilirlik', 'Reproducibility') ?></h2>
      <p class="ipucu"><?= k_c(
        'Bir sonucun doğruluğu, ancak başkası tarafından tekrarlanabildiği ölçüde denetlenebilir. Bu bölüm bütün çalışmalar için doldurulur.',
        'A finding can only be checked to the extent that someone else can reproduce it. This section is completed for every work.'
      ) ?></p>
      <div id="tekrarForm"></div>
    </div>

    <?php /* Yazarın yanıtı rapor kutusunun ÜSTÜNDE durur: hakem kararını
             vermeden önce okusun diye. Altında dursaydı çoğu kişi
             raporunu yazdıktan sonra görürdü ve kanalın anlamı kalmazdı. */ ?>
    <div class="kart hk-genis gizli" id="diyalogKart">
      <h2><?= k_c('Yazarın yanıtı', 'The author\'s response') ?></h2>
      <p class="ipucu"><?= k_c(
        'Yazar raporunuza yazılı bir yanıt verdi. Kararınızı vermeden önce okumanız beklenir. Karşılık yazmak zorunda değilsiniz ve kararınızı değiştirmek zorunda hiç değilsiniz; bu kanal ikna etmek için değil, yanlış anlaşılmayı düzeltmek içindir. Yazışmanın tamamı çalışmayla birlikte kalıcı olarak yayımlanır.',
        'The author has written a response to your report. You are expected to read it before deciding. You need not write back, and you certainly need not change your decision; this channel exists to correct misunderstanding, not to persuade. The whole exchange is published permanently with the work.'
      ) ?></p>
      <div id="diyalogListe"></div>
      <div id="diyalogYaz" class="gizli">
        <label for="diyalogMetin"><?= k_c('Karşılığınız (isteğe bağlı):', 'Your reply (optional):') ?></label>
        <textarea id="diyalogMetin" rows="5" placeholder="<?= k_c('Yazarın açıklamasına ilişkin görüşünüz.', 'Your view on the author\'s explanation.') ?>"></textarea>
        <div class="d-kume">
          <button type="button" class="d d-ikinci" id="diyalogGonder"><?= k_c('Karşılığımı gönder', 'Send my reply') ?></button>
        </div>
        <div id="diyalogMsj" class="form-msj"></div>
      </div>
    </div>

    <div class="kart hk-genis">
      <h2><?= k_c('Değerlendirme raporu', 'Assessment report') ?></h2>
      <label for="rapor"><?= k_c('Görüş, gerekçe ve düzeltme önerilerinizi yazınız:', 'Set out your view, your grounds and your suggested corrections:') ?></label>
      <textarea id="rapor" placeholder="<?= k_c('Çalışmanın özgünlüğü, yöntemi, bulguları, dili ve düzeltilmesini istediğiniz yerler...', 'The originality, method, findings and language of the work, and the places you want corrected...') ?>"></textarea>
      <label for="dosya"><?= k_c('İsterseniz düzeltmeleri işaretlediğiniz bir dosya da ekleyebilirsiniz (Word / PDF, isteğe bağlı):', 'You may also attach a file with your corrections marked (Word / PDF, optional):') ?></label>
      <label class="dosya-sec d d-ikinci"><input type="file" id="dosya" accept=".doc,.docx,.pdf,.odt"><span><?= k_c('Word / PDF', 'Word / PDF') ?></span></label>
      <label><?= k_c('Kararınız:', 'Your decision:') ?></label>
      <div class="onay-dizi">
        <label class="onay-kart"><input type="radio" name="karar" value="kabul"><span><?= k_c('Kabul (yayımlanabilir)', 'Accept (publishable)') ?></span></label>
        <label class="onay-kart"><input type="radio" name="karar" value="kucuk"><span><?= k_c('Küçük revizyonla kabul', 'Accept with minor revision') ?></span></label>
        <label class="onay-kart"><input type="radio" name="karar" value="buyuk"><span><?= k_c('Büyük revizyon gerekli', 'Major revision required') ?></span></label>
        <label class="onay-kart"><input type="radio" name="karar" value="ret"><span><?= k_c('Ret (yayımlanamaz)', 'Reject (not publishable)') ?></span></label>
      </div>
      <div id="nitelik" class="nitelik-kutu"></div>
      <button id="gonder" type="button" class="d d-vurgu"><?= k_c('Raporu gönder', 'Send the report') ?></button>
      <div id="mesaj" class="form-msj"></div>
    </div>
  </div>

  <div id="hata" class="gizli form-msj err"></div>
</div>
</div>
</section>
<button id="isaretBtn" class="d d-tehlike d-kucuk isaret-btn gizli" type="button"><?= k_c('İşaretle', 'Mark') ?></button>

<?php
$en = k_en();
$KAT = kt_katalog_json('', $en);   /* alan sunucudan gelince tarayıcıda süzülür */
$S = json_encode([
  'gecersiz'  => k_c('Geçersiz bağlantı.', 'Invalid link.'),
  'ePosta'    => k_c('Geçerli bir e-posta girin.', 'Enter a valid e mail address.'),
  'eSifre'    => k_c('E-postadaki erişim şifresini girin.', 'Enter the access code from the e mail.'),
  'kontrol'   => k_c('Kontrol ediliyor...', 'Checking...'),
  'bulunamadi'=> k_c('Bağlantı bulunamadı.', 'Link not found.'),
  'baglanti'  => k_c('Bağlantı kurulamadı.', 'Could not connect.'),
  'oncekiRapor'=> k_c('Daha önce gönderdiğiniz rapor yüklendi; güncelleyip tekrar gönderebilirsiniz.', 'Your earlier report has been loaded; you may update it and send it again.'),
  'metinYok'  => k_c('Metin bulunamadı.', 'No text found.'),
  'genelNot'  => k_c('(genel not)', '(general note)'),
  'notYer'    => k_c('Bu yer için notunuz veya önerdiğiniz değişiklik', 'Your note or suggested change for this passage'),
  'kaldir'    => k_c('Kaldır', 'Remove'),
  'kisaRapor' => k_c('Lütfen raporu biraz daha ayrıntılı yazın.', 'Please write the report in a little more detail.'),
  'kararSec'  => k_c('Lütfen bir karar seçin.', 'Please choose a decision.'),
  'gonderiliyor' => k_c('Gönderiliyor...', 'Sending...'),
  'alindi'    => k_c('Raporunuz alındı, çok teşekkürler. Bu sayfayı kapatabilirsiniz.', 'Your report has been received, thank you. You may close this page.'),
  'gonderilemedi' => k_c('Gönderilemedi.', 'Could not be sent.'),
  'hataTekrar'=> k_c('Bağlantı hatası, tekrar deneyin.', 'Connection error, please try again.'),
  'anketEksik'=> k_c('Açtığınız dizin değerlendirmesindeki bütün önermeleri yanıtlayın.', 'Please answer every statement in the index assessment you opened.'),
  'secimEksik'=> k_c('Bir çeyreklik seçin.', 'Choose a quartile.'),
  'kapali'    => k_c('Bu değerlendirme tamamlandı ve değiştirilemez. Teşekkür ederiz.', 'This assessment has been completed and cannot be changed. Thank you.'),
  /* Yazar ile yazışma */
  'diyYazar'  => k_c('Yazar', 'Author'),
  'diyHakem'  => k_c('Hakem', 'Reviewer'),
  'diyKisa'   => k_c('Karşılığınız en az %d karakter olmalıdır.', 'Your reply must be at least %d characters.'),
  'diyGonder' => k_c('Gönderiliyor...', 'Sending...'),
  'diyOk'     => k_c('Karşılığınız kayda geçti.', 'Your reply has been recorded.'),
  'diyOlmadi' => k_c('Karşılık gönderilemedi.', 'The reply could not be sent.'),
  'alanYok'   => k_c('Çalışmanın bilim alanı kayıtlı değil; bu nedenle bütün dizinler listeleniyor.', 'The field of the work is not recorded, so every index is listed.'),
  'tekrarEksik' => k_c('Tekrarlanabilirlik bölümündeki bütün önermeleri yanıtlayın.', 'Please answer every statement in the reproducibility section.'),
  'sifatEksik' => k_c('Bu çalışmayı hangi sıfatla değerlendirdiğinizi en az bir seçenekle belirtin.', 'Please choose at least one capacity in which you are assessing this work.'),
  'yetkinlikEksik' => k_c('Kendinizi neden yetkin gördüğünüzü bir iki cümleyle yazın.', 'Please write a sentence or two on why you consider yourself competent here.'),
  'tekrarYok' => k_c('Bu çalışma veri ya da kod üretmiyor', 'This work produces no data or code'),
  'nitBas'    => k_c('Rapor niteliği', 'Report quality'),
  'nitTamam'  => k_c('Bu rapor asgari değerlendirme ölçütlerini karşılıyor.', 'This report meets the minimum assessment criteria.'),
  'nitUyari'  => k_c('Raporunuz her hâlükârda yayımlanır; ancak şu ölçütleri karşılamadıkça onay sayımına ve hakemlik kaydınıza katılmaz:', 'Your report will be published either way, but it will not be counted towards approval or towards your reviewing record unless it meets these criteria:'),
  'nitGerekce'=> k_c('en az %d karakter gerekçe (şu an %c)', 'at least %d characters of reasoning (currently %c)'),
  'nitIsaret' => k_c('metinde notlandırılmış en az %d yer (şu an %c)', 'at least %d passages marked in the text with a note (currently %c)'),
  'nitSonrasiTamam' => k_c('Raporunuz kaydedildi ve asgari değerlendirme ölçütlerini karşılıyor. Onay sayımına ve hakemlik kaydınıza katılıyor.', 'Your report has been recorded and meets the minimum assessment criteria. It is counted towards approval and towards your reviewing record.'),
  'nitSonrasiEksik' => k_c('Raporunuz kaydedildi ve adınızla yayımlanıyor; ancak asgari değerlendirme ölçütlerini karşılamadığı için onay sayımına ve hakemlik kaydınıza katılmıyor. Gerekçe çalışmanın sayfasında yazılıdır.', 'Your report has been recorded and is published under your name, but it does not meet the minimum assessment criteria: it is not counted towards approval or towards your reviewing record. The reason is stated on the page of the work.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$TEKRAR = json_encode([
  'olcut' => $en ? [
    'The method is described in enough detail for another researcher to repeat the same procedure.',
    'The source of the data, how it was gathered and, where applicable, how it can be reached are stated clearly.',
    'The analytical steps leading from the findings to the conclusions can be followed; the figures and tables agree with the text.',
  ] : [
    'Çalışmanın yöntemi, başka bir araştırmacının aynı işlemi yeniden yapabilmesine yetecek ayrıntıda anlatılmış.',
    'Verinin kaynağı, nasıl toplandığı ve varsa nasıl erişilebileceği açıkça belirtilmiş.',
    'Bulgulardan sonuca giden çözümleme adımları izlenebiliyor; sayılar ve tablolar metinle tutarlı.',
  ],
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$ESIK = json_encode([
  'karakter' => (int)tg_ayar('rapor_asgari_karakter', 400),
  'isaret'   => (int)tg_ayar('rapor_asgari_isaret', 2),
]);

$betik = <<<JS
<script>
(function(){
  var S = {$S};
  var KAT = {$KAT};
  var TEKRAR = {$TEKRAR};
  var ESIK = {$ESIK};
  var ANKET = {};        /* endeks anahtarı -> {secim, yanit[]} */
  var TYANIT = [];       /* tekrarlanabilirlik yanıtları */
  var TYOK = false;      /* çalışma veri ya da kod üretmiyor */
  function \$(id){return document.getElementById(id);}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  var token=new URLSearchParams(location.search).get('t')||'';
  var mail='';var sifre='';
  var notlar=[]; var sonRange=null; var sonMetinSec='';

  function kurMetin(html){ \$('makaleMetin').innerHTML=html||'<i class="metin-sonuk">'+S.metinYok+'</i>'; }
  /* ---- Dizin ve çeyreklik değerlendirmesi ---- */
  function kurEndeks(alan){
    var box=\$('endeksList'), form=\$('endeksFormlar');
    box.innerHTML=''; form.innerHTML='';
    Object.keys(KAT.endeks).forEach(function(k){
      var e=KAT.endeks[k];
      if (alan && e.alan && e.alan !== '*' && e.alan.indexOf(alan) === -1) return;
      var b=document.createElement('button');
      b.type='button'; b.className='d d-ikinci d-kucuk'; b.setAttribute('aria-pressed','false');
      b.setAttribute('data-ez',k); b.textContent=e.ad;
      b.addEventListener('click',function(){ ezDegistir(k,b); });
      box.appendChild(b);
    });
  }

  function ezDegistir(k,b){
    var acik = b.getAttribute('aria-pressed')==='true';
    if (acik) { b.setAttribute('aria-pressed','false'); delete ANKET[k];
      var v=document.getElementById('ez-'+k); if(v)v.remove(); return; }
    b.setAttribute('aria-pressed','true');
    ANKET[k]={secim:'',yanit:[]};
    var e=KAT.endeks[k];
    var d=document.createElement('div'); d.className='kart ez-kart'; d.id='ez-'+k;
    var h='<h3>'+esc(e.ad)+'</h3><p class="ipucu">'+esc(e.ack)+'</p>';
    if (e.secim){
      h+='<div class="d-kume ez-secim" data-ez-secim="'+k+'">';
      e.secim.forEach(function(sc){ h+='<button type="button" class="d d-ikinci d-kucuk" aria-pressed="false" data-sc="'+esc(sc)+'">'+esc(sc)+'</button>'; });
      h+='</div>';
    }
    e.olcut.forEach(function(o,ix){
      h+='<div class="ez-soru"><p>'+(ix+1)+'. '+esc(o)+'</p><div class="ez-olcek">';
      for (var n=1;n<=5;n++){
        h+='<label class="onay-kart"><input type="radio" name="ez-'+k+'-'+ix+'" value="'+n+'"><span><b>'+n+'</b> '+esc(KAT.likert[n])+'</span></label>';
      }
      h+='</div></div>';
    });
    d.innerHTML=h;
    \$('endeksFormlar').appendChild(d);
    var sb=d.querySelector('[data-ez-secim]');
    if (sb) sb.addEventListener('click',function(ev){
      var t=ev.target.closest('button[data-sc]'); if(!t)return;
      Array.prototype.forEach.call(sb.querySelectorAll('button'),function(x){x.setAttribute('aria-pressed', x===t?'true':'false');});
      ANKET[k].secim=t.getAttribute('data-sc');
    });
    d.addEventListener('change',function(ev){
      var r=ev.target; if(r.type!=='radio')return;
      var ix=parseInt(r.name.split('-').pop(),10);
      ANKET[k].yanit[ix]=parseInt(r.value,10);
    });
    d.scrollIntoView({block:'nearest',behavior:'smooth'});
  }

  /* Gönderilmiş ve kilitlenmiş değerlendirmeyi salt okunur göster */
  function kurEndeksKilitli(anket){
    var box=\$('endeksList'), form=\$('endeksFormlar');
    box.innerHTML=''; form.innerHTML='';
    (anket||[]).forEach(function(a){
      var e=KAT.endeks[a.endeks]; if(!e)return;
      var d=document.createElement('div'); d.className='kart ez-kart';
      var h='<h3>'+esc(e.ad)+(a.secim?(' · '+esc(a.secim)):'')+'</h3>'+
            '<p class="ipucu">'+esc(e.ack)+'</p>';
      e.olcut.forEach(function(o,ix){
        var v=a.yanit[ix]||0;
        h+='<div class="ez-soru"><p>'+(ix+1)+'. '+esc(o)+'</p>'+
           '<div class="ez-yanit">'+
           (v?(v+' · '+esc(KAT.likert[v])):'-')+'</div></div>';
      });
      d.innerHTML=h; form.appendChild(d);
    });
  }

  /* ---- Tekrarlanabilirlik bölümü ---- */
  /* ---- Yazar ile yazışma ----
     Liste her zaman çizilir; yazma kutusu yalnızca sıra hakemdeyse
     açılır. Sıra kuralı sunucuda (tg_diyalog_sira), burada yalnızca
     gösterilir; iki yerde iki ayrı kural olsaydı biri yanılırdı. */
  var diyalogAsgari = 80;
  function cizDiyalog(d){
    var k=\$('diyalogKart'), l=\$('diyalogListe'), yz=\$('diyalogYaz');
    var dz=(d&&d.diyalog)||[];
    diyalogAsgari=(d&&d.diyalog_asgari)||80;
    if(!dz.length){ k.classList.add('gizli'); return; }
    k.classList.remove('gizli');
    l.innerHTML='';
    dz.forEach(function(e){
      var x=document.createElement('div');
      x.className='hd-sat hd-'+(e.yon==='yazar'?'yazar':'hakem');
      var kim=document.createElement('span'); kim.className='hd-kim';
      kim.textContent=(e.yon==='yazar'?S.diyYazar:S.diyHakem)+(e.tarih?(' · '+e.tarih.slice(0,10)):'');
      var mt=document.createElement('div'); mt.className='hd-metin'; mt.textContent=e.metin;
      x.appendChild(kim); x.appendChild(mt); l.appendChild(x);
    });
    if(d.diyalog_sira==='hakem') yz.classList.remove('gizli');
    else yz.classList.add('gizli');
  }
  if(\$('diyalogGonder')) \$('diyalogGonder').addEventListener('click',function(){
    var m=\$('diyalogMsj'), t=\$('diyalogMetin').value.trim();
    if(t.length<diyalogAsgari){
      m.textContent=S.diyKisa.replace('%d',diyalogAsgari); m.className='form-msj err'; return;
    }
    \$('diyalogGonder').disabled=true; m.textContent=S.diyGonder; m.className='form-msj';
    fetch('/api/hakem-yazar-yanit',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({t:token,mail:mail,sifre:sifre,metin:t})})
      .then(function(r){return r.json();}).then(function(d){
        \$('diyalogGonder').disabled=false;
        if(d&&d.ok){ m.textContent=d.mesaj||S.diyOk; m.className='form-msj ok';
          \$('diyalogMetin').value=''; \$('diyalogYaz').classList.add('gizli'); }
        else { m.textContent=(d&&d.hata)||S.diyOlmadi; m.className='form-msj err'; }
      }).catch(function(){ \$('diyalogGonder').disabled=false;
        m.textContent=S.diyOlmadi; m.className='form-msj err'; });
  });

  function kurTekrar(kilitli, kayit){
    var f=\$('tekrarForm'); if(!f)return; f.innerHTML='';
    if (kilitli){
      var kv=(kayit&&kayit.yanit)||[];
      if (kayit&&kayit.yok){ f.innerHTML='<p class="ipucu">'+S.tekrarYok+'</p>'; return; }
      var hk='';
      TEKRAR.olcut.forEach(function(o,ix){
        var v=kv[ix]||0;
        hk+='<div class="ez-soru"><p>'+(ix+1)+'. '+esc(o)+'</p>'+
            '<div class="ez-yanit">'+(v?(v+' · '+esc(KAT.likert[v])):'-')+'</div></div>';
      });
      f.innerHTML=hk; return;
    }
    var h='<label class="onay"><input type="checkbox" id="tekrarYok"><span>'+esc(S.tekrarYok)+'</span></label><div id="tekrarSorular">';
    TEKRAR.olcut.forEach(function(o,ix){
      h+='<div class="ez-soru"><p>'+(ix+1)+'. '+esc(o)+'</p><div class="ez-olcek">';
      for (var n=1;n<=5;n++){
        h+='<label class="onay-kart"><input type="radio" name="tk-'+ix+'" value="'+n+'"><span><b>'+n+'</b> '+esc(KAT.likert[n])+'</span></label>';
      }
      h+='</div></div>';
    });
    f.innerHTML=h+'</div>';
    f.addEventListener('change',function(ev){
      var r=ev.target;
      if(r.id==='tekrarYok'){ TYOK=r.checked; \$('tekrarSorular').style.display=r.checked?'none':''; niteligiGuncelle(); return; }
      if(r.type!=='radio'||r.name.indexOf('tk-')!==0)return;
      TYANIT[parseInt(r.name.split('-')[1],10)]=parseInt(r.value,10);
    });
  }
  function tekrarTopla(){
    if (TYOK) return {yok:true, yanit:[], eksik:false};
    for (var i=0;i<TEKRAR.olcut.length;i++){ if(!TYANIT[i]) return {yok:false, yanit:TYANIT, eksik:true}; }
    return {yok:false, yanit:TEKRAR.olcut.map(function(_,i){return TYANIT[i]||0;}), eksik:false};
  }

  /* Raporun görünür metni. Düzenleyici HTML üretir; nitelik eşiği
     etiketleri değil metnin kendisini saymalıdır, yoksa biçimlendirmek
     eşiği aşmanın yolu olurdu. */
  function raporMetni(){
    var v=\$('rapor').value||'';
    if(!/<[a-z][^>]*>/i.test(v)) return v.trim();
    var d=document.createElement('div'); d.innerHTML=v;
    return (d.textContent||'').replace(/\s+/g,' ').trim();
  }

  /* ---- Rapor nitelik göstergesi: hakem gönderMEDEN önce görür ---- */
  function niteligiGuncelle(){
    var kutu=\$('nitelik'); if(!kutu)return;
    var uzun=raporMetni().length;
    var isaret=notlar.filter(function(n){return n.not&&n.not.trim();}).length;
    var eksik=[];
    if (uzun<ESIK.karakter) eksik.push(S.nitGerekce.replace('%d',ESIK.karakter).replace('%c',uzun));
    if (isaret<ESIK.isaret) eksik.push(S.nitIsaret.replace('%d',ESIK.isaret).replace('%c',isaret));
    if (!eksik.length){ kutu.className='nitelik-kutu kutu kutu-yes'; kutu.innerHTML='<b>'+esc(S.nitBas)+'</b> '+esc(S.nitTamam); return; }
    kutu.className='nitelik-kutu kutu kutu-kut';
    var h='<b>'+esc(S.nitBas)+'</b> '+esc(S.nitUyari)+'<ul>';
    eksik.forEach(function(e){ h+='<li>'+esc(e)+'</li>'; });
    kutu.innerHTML=h+'</ul>';
  }

  function anketTopla(){
    var out=[], eksik=null, secimEksik=null;
    Object.keys(ANKET).forEach(function(k){
      var e=KAT.endeks[k], a=ANKET[k];
      for (var i=0;i<e.olcut.length;i++){ if(!a.yanit[i]) eksik=k; }
      if (e.secim && !a.secim) secimEksik=k;
      out.push({endeks:k, secim:a.secim||'', yanit:e.olcut.map(function(_,i){return a.yanit[i]||0;})});
    });
    return {liste:out, eksik:eksik, secimEksik:secimEksik};
  }
  function cizNotlar(){
    var box=\$('isaretListe'); box.innerHTML='';
    notlar.forEach(function(n,i){
      var d=document.createElement('div'); d.className='kutu kutu-kir isaret-sat';
      d.innerHTML='<div class="al">'+esc(n.alinti||S.genelNot)+'</div><textarea placeholder="'+S.notYer+'"></textarea><button type="button" class="d d-ikinci d-kucuk sil">'+S.kaldir+'</button>';
      var ta=d.querySelector('textarea'); ta.value=n.not||'';
      ta.addEventListener('input',function(){notlar[i].not=ta.value;niteligiGuncelle();});
      d.querySelector('.sil').addEventListener('click',function(){notlar.splice(i,1);cizNotlar();});
      box.appendChild(d);
    });
    niteligiGuncelle();
  }

  \$('yukleniyor').classList.add('gizli');
  if(!token){\$('hata').textContent=S.gecersiz;\$('hata').classList.remove('gizli');return;}
  \$('kilit').classList.remove('gizli');

  function acmayiDene(){
    var m=\$('mail').value.trim();
    var s=\$('sifre').value.trim();
    var km=\$('kilitMsj');
    if(!/.+@.+\\..+/.test(m)){km.textContent=S.ePosta;km.className='form-msj err';return;}
    if(!s){km.textContent=S.eSifre;km.className='form-msj err';return;}
    \$('ac').disabled=true;km.textContent=S.kontrol;km.className='form-msj';
    fetch('/api/hakem-form',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({t:token,mail:m,sifre:s})})
      .then(function(r){return r.json();}).then(function(d){
        \$('ac').disabled=false;
        if(!d||!d.ok){km.textContent=(d&&d.hata)||S.bulunamadi;km.className='form-msj err';return;}
        mail=m;sifre=s;
        \$('baslik').innerHTML=esc(d.baslik);
        \$('ozet').innerHTML=esc(d.ozet||'');
        \$('hakemAd').innerHTML=esc(d.hakem);
        var pf=d.profil||{};
        \$('hUnvan').value=pf.unvan||'';\$('hKurum').value=pf.kurum||'';
        \$('hEposta').value=pf.eposta||'';\$('hOrcid').value=pf.orcid||'';\$('hWeb').value=pf.web||'';
        kurMetin(d.metin||'');
        notlar=(d.notlar||[]).map(function(n){return {alinti:n.alinti||'',not:n.not||''};});
        cizNotlar();
        if(d.yetkinlik)\$('yetkinlik').value=d.yetkinlik;
        (d.sifat||[]).forEach(function(s){
          var c=document.querySelector('input[name=sifat][value="'+s+'"]'); if(c)c.checked=true;});
        if(d.kapali){
          \$('yetkinlik').readOnly=true;
          Array.prototype.forEach.call(document.querySelectorAll('input[name=sifat]'),function(c){c.disabled=true;});
        }
        cizDiyalog(d);
        kurTekrar(!!d.kapali, d.tekrar||null);
        if (d.kapali) {
          kurEndeksKilitli(d.endeks_anket||[]);
          \$('endeksKart').classList.remove('gizli');
          var ku=\$('endeksKart').querySelector('.ez-kilit'); if(ku)ku.remove();
          \$('gonder').disabled=true;
          \$('rapor').readOnly=true;
          Array.prototype.forEach.call(document.querySelectorAll('input[name=karar]'),function(r){r.disabled=true;});
          \$('mesaj').textContent=S.kapali; \$('mesaj').className='form-msj ok';
        } else {
          kurEndeks(d.alan||'');
          if(!d.alan) \$('endeksKart').querySelector('.ipucu').textContent += ' ' + S.alanYok;
        }
        if(d.rapor)\$('rapor').value=d.rapor;
        niteligiGuncelle();
        if(d.karar){var el=document.querySelector('input[name=karar][value="'+d.karar+'"]');
          if(el)el.checked=true;
          if(!d.kapali){endeksGoster();\$('mesaj').textContent=S.oncekiRapor;\$('mesaj').className='form-msj';}}
        \$('kilit').classList.add('gizli');
        \$('form').classList.remove('gizli');
      }).catch(function(){\$('ac').disabled=false;km.textContent=S.baglanti;km.className='form-msj err';});
  }
  \$('rapor').addEventListener('input',niteligiGuncelle);
  /* Düzenleyici kurulunca textarea'ya "input" düşmez; kendi olayını dinleriz. */
  document.addEventListener('kutadgu-duzenleyici', niteligiGuncelle);
  \$('ac').addEventListener('click',acmayiDene);
  \$('mail').addEventListener('keydown',function(e){if(e.key==='Enter')acmayiDene();});
  \$('sifre').addEventListener('keydown',function(e){if(e.key==='Enter')acmayiDene();});

  /* Metinde yer seçip işaretleme */
  document.addEventListener('mouseup',function(e){
    if(e.target&&e.target.id==='isaretBtn')return;
    var sel=window.getSelection(), mk=\$('makaleMetin');
    if(!sel||sel.isCollapsed||!mk){\$('isaretBtn').classList.add('gizli');return;}
    var txt=(sel.toString()||'').trim(), r=sel.rangeCount?sel.getRangeAt(0):null;
    if(!txt||!r||!mk.contains(r.commonAncestorContainer)){\$('isaretBtn').classList.add('gizli');return;}
    sonRange=r; sonMetinSec=txt;
    var rect=r.getBoundingClientRect(), b=\$('isaretBtn');
    b.classList.remove('gizli');
    b.style.top=Math.max(6,rect.top-42)+'px';
    b.style.left=Math.max(8,Math.min(rect.left,window.innerWidth-130))+'px';
  });
  \$('isaretBtn').addEventListener('click',function(){
    if(!sonMetinSec)return;
    try{ if(sonRange){var m=document.createElement('mark');m.className='hmark';sonRange.surroundContents(m);} }catch(e){}
    notlar.push({alinti:sonMetinSec,not:''}); cizNotlar();
    window.getSelection().removeAllRanges(); this.classList.add('gizli'); sonMetinSec='';
    var son=\$('isaretListe').lastChild; if(son){var ta=son.querySelector('textarea');if(ta)ta.focus();}
  });

  function endeksGoster(){ var v=document.querySelector('input[name=karar]:checked');
    \$('endeksKart').classList.toggle('gizli', !(v&&(v.value==='kabul'||v.value==='kucuk'))); }
  Array.prototype.forEach.call(document.querySelectorAll('input[name=karar]'),function(r){
    r.addEventListener('change',endeksGoster);});

  \$('gonder').addEventListener('click',function(){
    var rapor=\$('rapor').value.trim();
    var raporDuz=raporMetni();
    var kEl=document.querySelector('input[name=karar]:checked');
    var m=\$('mesaj');
    if(raporDuz.length<10){m.textContent=S.kisaRapor;m.className='form-msj err';return;}
    if(!kEl){m.textContent=S.kararSec;m.className='form-msj err';return;}
    var sf=[];
    Array.prototype.forEach.call(document.querySelectorAll('input[name=sifat]:checked'),function(c){sf.push(c.value);});
    if(!sf.length){m.textContent=S.sifatEksik;m.className='form-msj err';
      var sk2=\$('sifatKutu'); if(sk2)sk2.scrollIntoView({block:'center',behavior:'smooth'}); return;}
    var ytk=(\$('yetkinlik').value||'').trim();
    if(ytk.length<20){m.textContent=S.yetkinlikEksik;m.className='form-msj err';\$('yetkinlik').focus();return;}
    var tk=tekrarTopla();
    if (tk.eksik){m.textContent=S.tekrarEksik;m.className='form-msj err';
      var tf=\$('tekrarForm'); if(tf)tf.scrollIntoView({block:'center',behavior:'smooth'}); return;}
    var ak={liste:[],eksik:null,secimEksik:null};
    if (!\$('endeksKart').classList.contains('gizli')) {
      ak=anketTopla();
      if (ak.secimEksik){m.textContent=S.secimEksik;m.className='form-msj err';
        var sk=document.getElementById('ez-'+ak.secimEksik); if(sk)sk.scrollIntoView({block:'center',behavior:'smooth'});return;}
      if (ak.eksik){m.textContent=S.anketEksik;m.className='form-msj err';
        var ek=document.getElementById('ez-'+ak.eksik); if(ek)ek.scrollIntoView({block:'center',behavior:'smooth'});return;}
    }
    \$('gonder').disabled=true;m.textContent=S.gonderiliyor;m.className='form-msj';
    var endeks=ak.liste.map(function(a){
      var e=KAT.endeks[a.endeks]; return e ? (e.ad + (a.secim?(' '+a.secim):'')) : a.endeks;
    });
    var nl=notlar.filter(function(n){return (n.alinti&&n.alinti.trim())||(n.not&&n.not.trim());});
    var fd=new FormData();
    fd.append('t',token);fd.append('mail',mail);fd.append('sifre',sifre);fd.append('karar',kEl.value);fd.append('rapor',rapor);
    fd.append('endeks',JSON.stringify(endeks));
    fd.append('endeks_anket',JSON.stringify(ak.liste));
    fd.append('notlar',JSON.stringify(nl));
    fd.append('tekrar',JSON.stringify(tk));
    fd.append('sifat',JSON.stringify(sf));
    fd.append('yetkinlik',ytk);
    fd.append('h_unvan',\$('hUnvan').value.trim());fd.append('h_kurum',\$('hKurum').value.trim());
    fd.append('h_eposta',\$('hEposta').value.trim());fd.append('h_orcid',\$('hOrcid').value.trim());
    fd.append('h_web',\$('hWeb').value.trim());
    var f=\$('dosya');if(f&&f.files&&f.files[0])fd.append('dosya',f.files[0]);
    fetch('/api/hakem-gonder',{method:'POST',body:fd})
      .then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok){
          m.textContent=S.alindi;m.className='form-msj ok';
          var nk=\$('nitelik');
          if(nk){
            if(d.nitelik===false){
              nk.className='nitelik-kutu kutu kutu-kut';
              nk.innerHTML='<b>'+esc(S.nitBas)+'</b> '+esc(S.nitSonrasiEksik);
            } else {
              nk.className='nitelik-kutu kutu kutu-yes';
              nk.innerHTML='<b>'+esc(S.nitBas)+'</b> '+esc(S.nitSonrasiTamam);
            }
          }
          \$('rapor').readOnly=true;
          Array.prototype.forEach.call(document.querySelectorAll('input[name=karar]'),function(r){r.disabled=true;});
          if(ak.liste.length){kurEndeksKilitli(ak.liste.map(function(a){return a;}));
            var kk=\$('endeksKart').querySelector('.ez-kilit'); if(kk)kk.remove();}
        }
        else{m.textContent=(d&&d.hata)||S.gonderilemedi;m.className='form-msj err';\$('gonder').disabled=false;}
      }).catch(function(){m.textContent=S.hataTekrar;m.className='form-msj err';\$('gonder').disabled=false;});
  });
})();
</script>
JS;

k_son($betik . kd_betik('rapor', 'rapor', 220));
