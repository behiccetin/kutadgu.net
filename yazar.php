<?php
/* =====================================================================
   KUTADGU - Yazar paneli / Author panel
   Belirteçle (t=) açılır. API sözleşmesi eski yazar.html ile aynıdır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';
require_once __DIR__ . '/k/duzenleyici.php';
require_once __DIR__ . '/k/istem.php';

$retS = (int)tg_ayar('ret_donusum', 2);

$ekBas = <<<CSS
<meta name="robots" content="noindex, nofollow">
<style>
/* Bu bir form sayfasıdır, okuma sayfası değil: kartlar geniş ekranda
   yan yana durur, uzun olanlar (gövde metni ve kaynakça) satırın
   tamamını alır. Ölçü ızgaranın kendi ölçüsüdür, bir düzen eşiği
   değildir: sütun sığmadığı anda kendiliğinden alt alta iner. */
.yz-dizi{display:grid;gap:var(--b-4);align-items:start;
  grid-template-columns:repeat(auto-fit,minmax(min(430px,100%),1fr))}
.yz-genis{grid-column:1 / -1}
/* Gövde ve kaynakça alanları uzun metin taşır; düzenleyici açılınca da
   bu yükseklikten başlar. */
textarea.buyuk{min-height:320px;font-family:var(--mono);font-size:var(--y-3)}
/* ZORUNLU / İSTEĞE BAĞLI İMİ. Gönderim formundakiyle aynı biçim:
   aynı şeyi iki sayfada iki ayrı görünüşle söylemek, kişiye iki ayrı
   kural olduğunu düşündürür. Kırmızı olan yalnız 'zorunlu'dur; isteğe
   bağlı olan sessiz kalır, çünkü bir uyarı değil bir bilgidir. */
/* Önizleme, çalışma sayfasındaki .oz-yapi ile aynı biçimi taşır:
   bölüm adı bir ETİKETtir, kendi satırını almaz. İki yerde iki ayrı
   görünüm, önizlemeyi yalancı kılardı. */
.oz-yapi{border:1px solid var(--cizgi);border-radius:var(--r-2);padding:var(--b-3);
  background:var(--yuzey-2);margin-top:var(--b-2)}
.oz-yapi p{margin:0 0 var(--b-2)}
.oz-yapi p:last-child{margin-bottom:0}
.oz-yapi b{color:var(--metin-2)}
.zor,.ist{font-size:var(--y-1);font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.ist{color:var(--metin-2);font-weight:600}
/* Yazar satırı: listeye sonradan eklenen, kendi başına silinebilen bir
   öbek. Kesik çizgi onu kalıcı bir karttan ayırır. */
.yazar-sat{border:1px dashed var(--cizgi-2);border-radius:var(--r-3);padding:var(--b-4);
  margin-top:var(--b-3);position:relative}
.ysira{font-size:var(--y-1);letter-spacing:.1em;text-transform:uppercase;color:var(--kut);
  font-weight:700;margin-bottom:var(--b-2)}
.yazar-sat .sil{position:absolute;top:var(--b-3);right:var(--b-3)}
/* Hakem geri bildirimi: rapor metni ve işaretlenen yerler. */
.hk-bas{display:flex;gap:var(--b-3);align-items:center;flex-wrap:wrap;margin-bottom:var(--b-2)}
/* Yazar ile hakem yazışması. Sınıf adları makale sayfası ve hakem
   sayfasıyla aynı; aynı şey üç yerde aynı görünsün. */
.hakem-diyalog{margin-top:var(--b-4);padding-top:var(--b-3);border-top:1px solid var(--cizgi)}
.hakem-diyalog h4{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.13em;
  text-transform:uppercase;color:var(--metin-2);margin:0 0 var(--b-3)}
.hd-sat{border-left:3px solid var(--cizgi-2);padding-left:var(--b-3);margin-bottom:var(--b-3)}
.hd-yazar{border-left-color:var(--lacivert)}
.hd-hakem{border-left-color:var(--kut)}
.hd-kim{display:block;font-family:var(--ui);font-size:var(--y-2);font-weight:700;
  color:var(--metin-2);margin-bottom:var(--b-1)}
.hd-metin{font-size:var(--y-4);line-height:var(--sh-genis);white-space:pre-wrap}
.hk-diy-yaz{margin-top:var(--b-3)}
.hk-rapor{white-space:pre-wrap;font-size:var(--y-3);line-height:var(--sh-genis);color:var(--metin-2);
  border-left:2px solid var(--cizgi);padding-left:var(--b-3);margin-top:var(--b-2)}
.hk-end{margin-top:var(--b-3);font-size:var(--y-3);color:var(--metin-2)}
.hk-dosya{margin-top:var(--b-3)}
.hk-not{margin-top:var(--b-2)}
.hk-not-al{color:var(--kirmizi);font-size:var(--y-3);border-left:3px solid var(--kirmizi);
  padding-left:var(--b-2);white-space:pre-wrap}
.hk-not-nt{font-size:var(--y-3);margin-top:var(--b-2)}
/* Davet bağlantısı ve şifresi: kopyalanacak bir dize olduğu için
   sarmalama noktası harf düzeyine indirilir, yoksa kutudan taşar. */
.link-kutu{margin-top:var(--b-3);word-break:break-all}
.kopyala{margin-top:var(--b-2)}
</style>
CSS;
/* İstem penceresinin biçimi ortak kaynaktan gelir (k/istem.php): aynı
   pencere gönderim formunda da açılıyor ve iki yerde iki ayrı biçim,
   bir gün iki ayrı pencere demekti. */
$ekBas .= '<style>' . k_istem_stil() . '</style>';

k_bas([
    'olcu'   => 'genis',
    'baslik' => k_c('Yazar paneli', 'Author panel'),
    'yol' => '',
    'ek_bas' => $ekBas . kd_bas() . kd_yapi_stil(),
]);
?>

<section class="bolum">
<div class="kap blg">
<div class="blg-ic">
  <span class="bas-ust"><?= k_c('Yazar', 'Author') ?></span>
  <h1><?= k_c('Yazar paneli', 'Author panel') ?></h1>

  <div id="yukleniyor" class="yukleniyor"><?= k_c('Yükleniyor...', 'Loading...') ?></div>

  <!-- GİRİŞ -->
  <div id="kilit" class="gizli">
    <div class="kart">
      <h2><?= k_c('Erişim doğrulaması', 'Access check') ?></h2>
      <p class="ipucu"><?= k_c(
        'Bu panel yalnızca size özeldir. Başvurunuz kabul edildiğinde iletilen <b>e-posta adresini</b> ve <b>erişim şifresini</b> girin.',
        'This panel belongs to you alone. Enter the <b>e mail address</b> and <b>access code</b> sent to you when your application was accepted.'
      ) ?></p>
      <label for="mail"><?= k_c('E-posta adresiniz', 'Your e mail address') ?></label>
      <input type="email" id="mail" placeholder="ornek@universite.edu.tr" autocomplete="email">
      <label for="sifre"><?= k_c('Erişim şifresi', 'Access code') ?></label>
      <input type="text" id="sifre" placeholder="K7P-3RM" autocomplete="off" autocapitalize="characters">
      <button id="ac" type="button" class="d d-vurgu"><?= k_c('Devam', 'Continue') ?></button>
      <div id="kilitMsj" class="form-msj"></div>
    </div>
  </div>

  <!-- PANEL -->
  <div id="panel" class="gizli">
    <div id="kilitliUyari" class="kutu kutu-kir gizli">
      <?= k_c(
        '<b>Bu çalışma ' . $retS . ' ret kararı aldı.</b> Artık düzenleme yapamazsınız. Çalışmanız silinmez; hakemlerin ret gerekçeleriyle birlikte yayında kalır.',
        '<b>This work received ' . $retS . ' rejections.</b> You can no longer edit it. Your work is not deleted; it stays published together with the reviewers\' grounds for rejection.'
      ) ?>
    </div>

    <div class="sek-bar">
      <button type="button" data-sekme="makale" class="acik"><?= k_c('Çalışma', 'The work') ?></button>
      <button type="button" data-sekme="hakem"><?= k_c('Hakemler ve geri bildirim', 'Reviewers and feedback') ?></button>
    </div>

    <!-- SEKME: ÇALIŞMA -->
    <div id="s-makale" class="yz-dizi">
      <div class="kart yz-genis" id="wordKart">
        <h2><?= k_c('Word belgesinden aktar', 'Import from a Word file') ?></h2>
        <p class="ipucu"><?= k_c(
          'Çalışmanızı <b>.docx</b> olarak yükleyin; sistem başlık, özet, anahtar kelimeler, gövde (başlıklar, paragraflar, listeler, tablolar) ve kaynakça yapısını ayırıp aşağıdaki alanlara kendi biçiminde yerleştirir. Yerleşen içeriği kaydetmeden önce gözden geçirebilirsiniz.',
          'Upload your work as a <b>.docx</b> file; the system separates the title, abstract, keywords, body (headings, paragraphs, lists, tables) and bibliography, then places them into the fields below in its own format. You can review the result before saving.'
        ) ?></p>
        <label class="dosya-sec d d-ikinci"><input type="file" id="wordDosya" accept=".docx"><span><?= k_c('Word dosyası (.docx)', 'Word file (.docx)') ?></span></label>
        <label class="onay">
          <input type="checkbox" id="wordIng">
          <?php /* Aynı kusurun üçüncü yeri. Word aktarımı da künye
                   dilinden söz ediyor; adı ayardan alır. */ ?>
          <span><?= k_cd('Bu belge %1 sürüm; %1 alanlarına aktar.', 'This file is the %1 version; import into the %1 fields.', tg_kunye_dil_adi()) ?></span>
        </label>
        <button id="wordAktar" type="button" class="d d-vurgu"><?= k_c('Belgeyi aktar', 'Import the file') ?></button>
        <div id="wordMsj" class="form-msj"></div>
        <div id="wordOzet"></div>
      </div>

      <div class="kart yz-genis">
        <h2><?= k_c('Çalışma (Türkçe)', 'The work (Turkish)') ?></h2>
        <label for="baslik"><?= k_c('Başlık', 'Title') ?></label>
        <input type="text" id="baslik">
        <label for="ozet"><?= k_c('Özet', 'Abstract') ?></label>
        <textarea id="ozet"></textarea>
        <?php /* YAPILANDIRILMIŞ ÖZ — BURADA DA DÜZENLENEBİLİR.
                 Gönderim formunda açılabilen yapı burada da açılabilir
                 olmak zorundadır: yazar özetini bölümlere ayırıp
                 yayımlandıktan sonra bir bölümü düzeltmek istediğinde,
                 yalnız birleştirilmiş dizeyi bulsaydı ya yapıyı bozar ya
                 da düzeltmekten vazgeçerdi. Kurduğu şeyi sürdürebilmeli.

                 Kutular AÇIK doğar, gizleyen betiktir (betiksiz tarayıcı
                 kuralı). Bölümler tek kaynaktan basılır. */ ?>
        <label class="onay" id="yzYapiAc">
          <input type="checkbox" id="ozetYapili">
          <span><?= k_c('Özeti bölümlere ayır', 'Divide the abstract into sections') ?>
            <small><?= k_c('İsteğe bağlı. Bölümleri doldurduğunuzda özet onlardan kurulur; kapatırsanız yazılan özet olduğu gibi kalır.', 'Optional. When you fill in the sections the abstract is built from them; if you switch it off, the abstract as written stays.') ?></small></span>
        </label>
        <div id="ozetYapi">
          <?php foreach (tg_ozet_bolumleri() as $bo): ?>
          <label for="yzoz_<?= k_esc($bo['k']) ?>"><?= k_esc(k_en() ? $bo['en'] : $bo['tr']) ?></label>
          <textarea id="yzoz_<?= k_esc($bo['k']) ?>" rows="2" data-yzozbolum="<?= k_esc($bo['k']) ?>"></textarea>
          <?php endforeach; ?>
          <?php /* ÖNİZLEME: OKURUN GÖRECEĞİ HÂL.
                   Yazar bölümleri doldururken yukarıdaki özet kutusunda
                   birleştirilmiş dizeyi görüyor; ama çalışmanın
                   sayfasında bölümler başlıklarıyla ÇİZİLİYOR ve o
                   görünüm burada hiç yoktu. Yazdığının nasıl
                   yayımlanacağını görmeden yazan kişi, yayımlandıktan
                   sonra düzeltir — düzeltmesi bir sürüm kaydı bırakır.

                   Önizleme çalışma sayfasının kendi biçimini kullanır
                   (.oz-yapi): iki yerde iki ayrı görünüm, önizlemeyi
                   yalancı kılardı. */ ?>
          <p class="ipucu" style="margin-top:var(--b-3)"><b><?= k_c('Okurun göreceği hâl', 'How the reader will see it') ?></b></p>
          <div class="oz-yapi" id="ozYapiOnizle" aria-live="polite"></div>
        </div>
        <label for="anahtar"><?= k_c('Anahtar kelimeler (virgülle)', 'Keywords (comma separated)') ?></label>
        <input type="text" id="anahtar">
        <label for="metin"><?= k_c('Tam metin', 'Full text') ?></label>
        <p class="ipucu"><?= k_c('Biçimlendirme araç çubuğundan yapılır: başlık, madde, çizelge, alıntı, üst ve alt simge. HTML bilmeniz gerekmez; bilenler için son düğme kaynak görünümünü açar. Word\'den yapıştırdığınızda biçim temizlenerek alınır.', 'Formatting is done from the toolbar: headings, lists, tables, quotations, superscript and subscript. You do not need to know HTML; for those who do, the last button opens the source view. Pasting from Word brings the text in with its formatting cleaned.') ?></p>
        <textarea id="metin" class="buyuk"></textarea>
        <?php /* YAPI PANELİ. Metnin altında durur, çünkü söylediği şey
                 metnin bir ÖLÇÜMÜdür: kaç başlık çıktı, içindekiler
                 nasıl görünüyor, kaynakça yerinde mi. Yazar yazdıkça
                 kendiliğinden yenilenir. Gerekçesi k/duzenleyici.php'de
                 kd_yapi()'nin başındadır. */ ?>
        <?= kd_yapi('metin') ?>
        <label for="kaynakca"><?= k_c('Kaynakça', 'Bibliography') ?></label>
        <textarea id="kaynakca"></textarea>
      </div>

      <div class="kart yz-genis">
        <?php $kyKod = tg_kunye_dili(); $kyAd = tg_kunye_dil_adi(); $kyZor = tg_kunye_zorunlu(); ?>
        <?php /* ---------------------------------------------------------
             ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi:
             "isteğe bağlı diyor."

             Doğruydu. Başlık ve öz künye dilinde 15 Ağustos 2026'da
             ZORUNLU oldu (ayar.php: kunye_dili.zorunlu). Gönderim formu
             o gün güncellendi; yazar paneli güncellenmedi ve o günden
             beri kaldırılmış bir kuralı duyuruyordu. Bu, bu sistemde en
             sık yakalanan kusur sınıfının yirminci örneğidir: sistem,
             uygulamadığı bir kuralı duyuruyor. Burada ters yönden —
             UYGULADIĞI bir kuralı "isteğe bağlı" diye duyuruyordu.

             ÜÇ ŞEY BİRDEN DÜZELDİ:
               - "isteğe bağlı" gitti; zorunluluk tg_kunye_zorunlu()'dan
                 SORULUYOR, elle yazılmıyor. Kural değişirse metin de
                 değişir.
               - "İngilizce" elle yazılmıştı. Künye dili bir ayardır;
                 kurul başka bir dil seçtiği gün bu kart yalan söylerdi.
                 Ad artık tg_kunye_dil_adi()'nden geliyor.
               - KÜNYE İLE GERİSİ AYRILDI. Zorunlu olan yalnız BAŞLIK ve
                 ÖZdür. Tam metin, anahtar kelimeler ve kaynakça bu
                 dilde istenmiyor ve istenmeyecek: kayıt her zaman
                 yazarın kendi dilindeki metindir. Hepsini tek bir
                 "zorunlu" başlığın altına koymak, verilmeyen bir sözü
                 vermek olurdu.

             Çalışmanın kendi dili zaten künye diliyse bu kart gereksizdir
             ve betik onu kapatır (aynı metni iki kez istemek olurdu);
             kapatmayı betik yapar, çünkü hangi çalışmanın açıldığı ancak
             erişim doğrulandıktan sonra bilinir. */ ?>
        <h2><?= $kyAd !== '' ? k_cd('Künye ve %1 sürüm', 'Citation entry and the %1 version', $kyAd) : k_c('İkinci dildeki sürüm', 'The version in the second language') ?></h2>
        <p class="ipucu" id="kyAck"><?= $kyZor ? k_cd(
          '<b>Başlık ve öz (%1) zorunludur:</b> dizinler, arama motorları ve başka dilden okurun atıf künyesi bunları okur; bulunamayan bir çalışmada açık erişimin anlamı kalmaz. Alttaki tam metin, anahtar kelimeler ve kaynakça <b>isteğe bağlıdır</b> ve öyle kalacaktır: kayıt her zaman sizin yazdığınız dildeki metindir.',
          '<b>The title and abstract (%1) are required:</b> indexes, search engines and the citation entry seen by readers in other languages read them; open access means little for a work that cannot be found. The full text, keywords and references below are <b>optional</b> and will stay so: the record is always the text in the language you wrote it in.',
          $kyAd
        ) : k_cd(
          'Başlık ve öz (%1) isteğe bağlıdır; yalnızca dizinler ve başka dilden okurun atıf künyesi için istenir.',
          'The title and abstract (%1) are optional; they are asked for only so that indexes and readers in other languages can cite your work.',
          $kyAd
        ) ?></p>
        <p class="ipucu gizli" id="kyAyniDil"><?= k_cd(
          'Çalışmanız zaten %1 dilinde yazılmış; bu bölüm sizden istenmiyor. Aynı metni ikinci kez istemek olurdu.',
          'Your work is already written in %1; this section is not asked of you. It would mean asking for the same text twice.',
          $kyAd
        ) ?></p>
        <?php /* İSTEMLER BURADA DA AÇILIR. Aynı düğme gönderim
                 formunda da var; ikisi de aynı pencereyi açar. */ ?>
        <p style="margin:var(--b-3) 0 0">
          <button type="button" class="d d-ikinci d-kucuk" id="cvAc"><?= k_c('Çeviri ve anahtar kelime istemlerini açın', 'Open the translation and keyword prompts') ?></button>
        </p>
        <p class="ipucu"><?= k_c(
          'Bu alanları yapay zekâdan yardım alarak da doldurabilirsiniz; istemler üslubunuzu koruyacak ve metinde olmayan bir şey eklemeyecek biçimde yazıldı. Kullanırsanız aşağıdaki <b>çeviri künyesinde</b> beyan edin: beyan edilmeyen kullanım kabul edilemez.',
          'You may fill these fields with help from an AI; the prompts are written to preserve your voice and to add nothing that is not in the text. If you use one, declare it in the <b>translation record</b> below: undeclared use is not acceptable.'
        ) ?></p>
        <label for="baslik_en"><?= k_cd('Başlık (%1)', 'Title (%1)', $kyAd) ?>
          <small class="<?= $kyZor ? 'zor' : 'ist' ?>" data-kyzor><?= $kyZor ? k_c('zorunlu', 'required') : k_c('isteğe bağlı', 'optional') ?></small></label>
        <input type="text" id="baslik_en">
        <label for="ozet_en"><?= k_cd('Öz (%1)', 'Abstract (%1)', $kyAd) ?>
          <small class="<?= $kyZor ? 'zor' : 'ist' ?>" data-kyzor><?= $kyZor ? k_c('zorunlu', 'required') : k_c('isteğe bağlı', 'optional') ?></small></label>
        <textarea id="ozet_en"></textarea>
        <label for="anahtar_en"><?= k_cd('Anahtar kelimeler (%1)', 'Keywords (%1)', $kyAd) ?>
          <small class="ist"><?= k_c('isteğe bağlı', 'optional') ?></small></label>
        <input type="text" id="anahtar_en">
        <label for="metin_en"><?= k_cd('Tam metin (%1)', 'Full text (%1)', $kyAd) ?>
          <small class="ist"><?= k_c('isteğe bağlı, hiçbir zaman zorunlu olmayacak', 'optional, and will never be required') ?></small></label>
        <textarea id="metin_en" class="buyuk"></textarea>
        <?= kd_yapi('metin_en') ?>
        <label for="kaynakca_en"><?= k_cd('Kaynakça (%1)', 'References (%1)', $kyAd) ?>
          <small class="ist"><?= k_c('isteğe bağlı', 'optional') ?></small></label>
        <textarea id="kaynakca_en"></textarea>

        <?php /* ÇEVİRİNİN KÜNYESİ.
                 İlkeler sayfası "yazarın onayı ve çevirenin adı gerekir;
                 çeviren kendi adıyla kayda geçer" diyor. Bu üç alan o
                 cümlenin kod karşılığıdır. Boş bırakılırsa eski davranış
                 sürer: metin sizin yazdığınız sayılır.

                 Neden okurun da işine yarıyor: onaysız bir makine
                 çevirisi sayfada uyarı olarak yazılır, dizinlere ve arama
                 motorlarına "bu çalışmanın İngilizce sürümü" diye
                 bildirilmez. Onayladığınız gün bildirilir; yani onay bir
                 tören değil, bir anahtardır.

                 <select> ve <input> gerçek form alanlarıdır; betik
                 çalışmasa da görünür ve okunur. */ ?>
        <fieldset class="cv-kunye">
          <?php /* DİL ADI BURADA DA ELLE YAZILMAZ. Aynı kusurun ikinci
                   yeri: "Bu İngilizce sürüm nasıl üretildi?" Künye dili
                   bir ayardır ve kurul başka bir dil seçtiği gün bu
                   soru, sorulmayan bir dili sorardı. */ ?>
          <legend><?= k_cd('Bu %1 sürüm nasıl üretildi?', 'How was this %1 version produced?', $kyAd) ?></legend>
          <label for="ceviri_kaynak"><?= k_c('Kaynağı', 'Source') ?></label>
          <select id="ceviri_kaynak">
            <option value="yazar"><?= k_c('Yazarın kendisi yazdı', 'Written by the author') ?></option>
            <option value="insan"><?= k_c('Bir çevirmen çevirdi', 'Translated by a person') ?></option>
            <option value="makine"><?= k_c('Makine çevirisi', 'Machine translation') ?></option>
          </select>
          <div id="ceviriEk" hidden>
            <label for="ceviri_ceviren"><?= k_c('Çevirenin adı ya da kullanılan aracın adı', 'Name of the translator, or of the tool used') ?></label>
            <input type="text" id="ceviri_ceviren" maxlength="120"
                   placeholder="<?= k_c('Örnek: Ayşe Demir · ya da kullandığınız çeviri aracı', 'Example: Jane Doe · or the translation tool you used') ?>">
            <label class="onay" for="ceviri_onay">
              <input type="checkbox" id="ceviri_onay">
              <span><?= k_c('Bu çeviriyi baştan sona okudum ve onaylıyorum.', 'I have read this translation in full and I approve it.') ?></span>
            </label>
            <p class="ipucu"><?= k_cd(
              'Onaylamazsanız çeviri sayfada durur ve okura "yazar görmedi, kayıt bu değildir" diye yazılır; dizinlere bildirilmez. Onaylarsanız çalışmanızın %1 sürümü olarak bildirilir.',
              'If you do not approve it, the translation stays on the page and the reader is told that the author has not seen it and that it is not the record; it is not reported to indexes. If you approve it, it is reported as the %1 version of your work.',
              $kyAd
            ) ?></p>
          </div>
        </fieldset>
      </div>

      <div class="kart">
        <h2><?= k_c('İletişim yazarı bilgileri', 'Corresponding author details') ?></h2>
        <div class="alan-ikili">
          <div><label for="yb_unvan"><?= k_c('Unvan', 'Title') ?></label><?php /* Seçim, düz metin değil; liste tek kaynaktan. */ ?><select id="yb_unvan"><?= tg_unvan_secenek() ?></select></div>
          <div><label for="yb_ad"><?= k_c('Ad Soyad', 'Full name') ?></label><input type="text" id="yb_ad"></div>
        </div>
        <div class="alan-ikili">
          <div><label for="yb_eposta"><?= k_c('E-posta', 'E mail') ?></label><input type="text" id="yb_eposta"></div>
          <div><label for="yb_orcid">ORCID <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label><input type="text" id="yb_orcid" placeholder="0000-0000-0000-0000"></div>
        </div>
        <label for="yb_kurum"><?= k_c('Kurum', 'Institution') ?></label>
        <input type="text" id="yb_kurum">
        <div class="alan-ikili">
          <div><label for="yb_scopus">Scopus Author ID <small><?= k_c('isteğe bağlı', 'optional') ?></small></label><input type="text" id="yb_scopus" placeholder="7004212771"></div>
          <div><label for="yb_web"><?= k_c('Web / profil', 'Web or profile') ?></label><input type="text" id="yb_web"></div>
        </div>
      </div>

      <div class="kart">
        <h2><?= k_c('Yazar listesi', 'Author list') ?></h2>
        <p class="ipucu"><?= k_c(
          'Bütün yazarlar en az "Dr." unvanına sahip olmalı; <b>ORCID her yazar için zorunludur</b>, Scopus Author ID isteğe bağlıdır.',
          'Every author must hold at least a doctoral title; <b>an ORCID is required for each</b>, a Scopus Author ID is optional.'
        ) ?></p>
        <div id="yazarlar"></div>
        <button type="button" class="d d-ikinci" id="yazarEkle">+ <?= k_c('Yazar ekle', 'Add an author') ?></button>
      </div>

      <?php /* ---------------------------------------------------------
           ETİK KURUL BEYANI — YAYIMDAN SONRA DA AÇIK

           ÖLÇÜLEN KUSUR — 18 Ağustos 2026. Çalışma sayfası askı
           kutusunda "Yazar bunları bildirdiğinde bu uyarı kalkar"
           diyordu; bildirmenin hiçbir yolu yoktu. Yol burasıdır.

           BU BÖLÜM METİN KİLİDİNDEN ETKİLENMEZ. Kilit, değerlendirmesi
           biten bir çalışmanın bulgularının değişmesini önler; etik
           beyanı bulgu değildir, kayıt dışı kalmış bir olgunun kayda
           geçirilmesidir. Kilitliyken de açıktır (bkz. api /yazar-kaydet).

           Alanlar betiksiz tarayıcıda da AÇIK gelir; gizleme betikle
           yapılır. Gizli doğan zorunlu alan, betiği çalışmayan kişiye
           doldurulamaz bir zorunluluk bırakır — bu kusur bu sistemde
           daha önce üç kez ölçüldü.
           --------------------------------------------------------- */ ?>
      <div class="kart" id="etikKart">
        <h2><?= k_c('Etik kurul beyanı', 'Ethics committee declaration') ?></h2>
        <p class="ipucu" id="etikDurumNot"></p>
        <p class="ipucu"><?= k_c(
          'Belgenin kendisi yüklenmez: kurul adı, karar tarihi ve karar numarası yayımlanır ki isteyen doğrudan veren kurula sorabilsin. Doğrulanabilirlik rozette değil, sorulacak adrestedir.',
          'The document itself is not uploaded: the committee name, decision date and decision number are published so that anyone may ask the issuing committee directly. Verifiability lies in an address one can ask, not in a badge.'
        ) ?></p>
        <div class="onay-dizi-2">
          <label class="onay"><input type="radio" name="etik_durum" value="gereksiz" id="etikYok"><span><?= k_c('Bu çalışma için etik kurul izni gerekmiyor.', 'This work does not require ethics committee approval.') ?></span></label>
          <label class="onay"><input type="radio" name="etik_durum" value="gerekli" id="etikVar"><span><?= k_c('Etik kurul izni gerekli ve alındı; beyanı aşağıda.', 'Ethics committee approval is required and was obtained; the declaration is below.') ?></span></label>
        </div>
        <div id="etikAlan">
          <label for="etikKurul"><?= k_c('İzni veren kurul', 'Committee that gave the approval') ?></label>
          <input type="text" id="etikKurul" placeholder="<?= k_c('Üniversite ... Etik Kurulu', 'University ... Ethics Committee') ?>">
          <div class="alan-ikili">
            <div><label for="etikTarih"><?= k_c('Karar tarihi', 'Decision date') ?></label><input type="text" id="etikTarih" placeholder="2025-04-17"></div>
            <div><label for="etikNo"><?= k_c('Karar numarası', 'Decision number') ?></label><input type="text" id="etikNo" placeholder="2025/14"></div>
          </div>
        </div>
        <p class="ipucu" id="etikKilitNot" hidden><?= k_c(
          'Verilmiş bir beyan geri alınamaz; yalnızca düzeltilebilir. Yanlış girdiyseniz editöre düzeltme isteyin.',
          'A declaration once given cannot be withdrawn, only corrected. If you entered it wrongly, ask an editor for a correction.'
        ) ?></p>
        <div class="d-kume">
          <button id="etikKaydet" type="button" class="d d-vurgu"><?= k_c('Beyanı kaydet', 'Save the declaration') ?></button>
        </div>
        <div id="etikMsj" class="form-msj"></div>
      </div>

      <div class="kart">
        <label for="doi"><?= k_c('DOI (varsa)', 'DOI (if any)') ?></label>
        <input type="text" id="doi" placeholder="10.xxxx/xxxxx">
        <div class="d-kume">
          <button id="kaydet" type="button" class="d d-vurgu"><?= k_c('Değişiklikleri kaydet', 'Save changes') ?></button>
          <a id="gorLink" href="#" target="_blank" class="d d-ikinci"><?= k_c('Çalışmayı görüntüle', 'View the work') ?></a>
        </div>
        <div id="kaydetMsj" class="form-msj"></div>
      </div>
    </div>

    <!-- SEKME: HAKEM -->
    <div id="s-hakem" class="gizli yz-dizi">
      <div class="kart">
        <h2><?= k_c('Hakem önerin', 'Suggest a reviewer') ?></h2>
        <p class="ipucu"><?= k_c(
          'Alanı en iyi bilen çoğu zaman yazarın kendisidir; bu yüzden kimi önereceğinize siz karar verirsiniz ve öneriniz kayda geçer. Daveti ise editör gönderir: bir hakemin bağımsız sayılabilmesi, onu kimin çağırdığına bağlıdır. Öneriniz editöre iletilir, editör kimliği doğrular ve kararını size bildirir.',
          'The person who knows the field best is often the author, so you decide whom to suggest and your suggestion is recorded. The invitation, however, is sent by an editor: whether a reviewer counts as independent depends on who called them. Your suggestion goes to an editor, who verifies the person and tells you the decision.'
        ) ?></p>
        <p class="ipucu"><?= k_c(
          'Açık hakemlik uygulanır: hakemin adı, kararı ve raporu çalışmanızla birlikte görünür. Kimin önerdiği de görünür kalır.',
          'Review is open: the reviewer\'s name, decision and report appear alongside your work. Who suggested them stays visible too.'
        ) ?></p>
        <label for="hAd"><?= k_c('Hakem adı (unvanıyla)', 'Reviewer name with title') ?></label>
        <input type="text" id="hAd" placeholder="Prof. Dr. ...">
        <label for="hKurum"><?= k_c('Kurumu (isteğe bağlı)', 'Institution (optional)') ?></label>
        <input type="text" id="hKurum" placeholder="<?= k_c('Üniversite, bölüm', 'University, department') ?>">
        <label for="hOrcid"><?= k_c('ORCID (isteğe bağlı, kimliği doğrulamayı kolaylaştırır)', 'ORCID (optional, makes verification easier)') ?></label>
        <input type="text" id="hOrcid" placeholder="0000-0000-0000-0000">
        <label for="hEposta"><?= k_c('Hakem e-postası (isteğe bağlı)', 'Reviewer e mail (optional)') ?></label>
        <input type="email" id="hEposta" placeholder="hakem@universite.edu.tr">
        <label for="hGerekce"><?= k_c('Neden bu kişi? (editöre yardımcı olur)', 'Why this person? (helps the editor)') ?></label>
        <textarea id="hGerekce" rows="3" placeholder="<?= k_c('Alanı, yöntemi ya da veriyi bilmesiyle ilgili kısa bir gerekçe.', 'A short reason relating to their field, method or data.') ?>"></textarea>
        <button id="davet" type="button" class="d d-vurgu"><?= k_c('Öneriyi editöre gönder', 'Send the suggestion to an editor') ?></button>
        <div id="davetMsj" class="form-msj"></div>
        <div id="davetSonuc"></div>
      </div>

      <div class="kart yz-genis">
        <h2><?= k_c('Hakemler ve geri bildirimleri', 'Reviewers and their feedback') ?></h2>
        <p class="ipucu"><?= k_c(
          'Hakemlerin raporlarını ve işaretledikleri yerleri buradan görüp çalışmanızı düzeltebilirsiniz. Düzeltmeden sonra hakem yeni sürümü yeniden değerlendirir.',
          'You can see the reviewers\' reports and marked passages here and revise your work. After you revise, the reviewer assesses the new version again.'
        ) ?></p>
        <div id="hakemListe"></div>
      </div>
    </div>
  </div>

  <div id="hata" class="gizli form-msj err"></div>
</div>
</div>
</section>

<?php /* ÇEVİRİ VE ANAHTAR KELİME İSTEMLERİ (k/istem.php).
         ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi: "anahtar
         kelime olsun tam metin olsun bunları yz ile de yapabilir dedik
         ya, hatta istemde önerdik."

         Doğruydu. Gönderim formu künye dilindeki metni yapay zekâyla
         üretmeyi açıkça öneriyor ve hazır istemler veriyordu. Ama o
         alanların çoğu gönderim anında değil, KABULDEN SONRA burada
         doldurulur: tam metin, anahtar kelimeler, kaynakça. Yardımın
         asıl gerektiği yer burasıydı ve burada yoktu. */ ?>
<?= k_istem_ceviri() ?>

<?php
$en = k_en();
$S = json_encode([
  'gecersiz'   => k_c('Geçersiz bağlantı.', 'Invalid link.'),
  'ePosta'     => k_c('Geçerli bir e-posta girin.', 'Enter a valid e mail address.'),
  'eSifre'     => k_c('Erişim şifresini girin.', 'Enter the access code.'),
  'kontrol'    => k_c('Kontrol ediliyor...', 'Checking...'),
  'bulunamadi' => k_c('Bağlantı bulunamadı.', 'Link not found.'),
  'baglanti'   => k_c('Bağlantı kurulamadı.', 'Could not connect.'),
  'yazarNo'    => k_c('. yazar', '. author'),
  'unvanEt'    => k_c('Unvan (en az Dr.)', 'Title (at least Dr.)'),
  'unvanSec'   => tg_unvan_secenek(),
  'adEt'       => k_c('Ad Soyad', 'Full name'),
  'kurumEt'    => k_c('Kurum', 'Institution'),
  'zorunlu'    => $en ? 'required' : 'zorunlu',
  'istege'     => k_c('isteğe bağlı', 'optional'),
  'kaldir'     => k_c('Kaldır', 'Remove'),
  'hakemYok'   => k_c('Henüz hakem davet etmediniz.', 'You have not invited a reviewer yet.'),
  'bekliyor'   => k_c('değerlendirme bekliyor', 'awaiting assessment'),
  'onerilen'   => k_c('Önerilen dizin ve dergiler:', 'Suggested indexes or journals:'),
  'isaretli'   => k_c('İşaretlenen yerler:', 'Marked passages:'),
  'hakemDosya' => k_c('Hakemin eklediği dosya', 'File attached by the reviewer'),
  'davetBag'   => k_c('Davet bağlantısı:', 'Invitation link:'),
  'sifreEt'    => k_c('Şifre:', 'Code:'),
  'kopyaDg'    => k_c('Bağlantı ve şifreyi kopyala', 'Copy link and code'),
  'kopyalandi' => k_c('Kopyalandı', 'Copied'),
  'kopyaMetin' => k_c('Hakem değerlendirme bağlantınız: ', 'Your reviewer assessment link: '),
  'kopyaSifre' => k_c('Erişim şifresi: ', 'Access code: '),
  'wordSec'    => k_c('Önce bir .docx dosyası seçin.', 'Select a .docx file first.'),
  'wordTur'    => k_c('Yalnızca .docx desteklenir. Word\'de "Farklı Kaydet" ile .docx seçin.', 'Only .docx is supported. In Word choose "Save As" and select .docx.'),
  'wordCoz'    => k_c('Belge çözümleniyor...', 'Reading the file...'),
  'wordOlmadi' => k_c('Dönüştürülemedi.', 'Could not be converted.'),
  'wordOk'     => k_c('Belge aktarıldı. Aşağıdaki alanları gözden geçirin.', 'The file was imported. Please review the fields below.'),
  'wordAktarildi' => k_c('Aktarıldı.', 'Imported.'),
  'wordKelime' => k_c(' kelime', ' words'),
  'wordBaslik' => k_c(' başlık', ' headings'),
  'wordTablo'  => k_c(' tablo', ' tables'),
  'wordUnut'   => k_c('Alanları kontrol edip <b>Değişiklikleri Kaydet</b> demeyi unutmayın.', 'Check the fields and remember to press <b>Save changes</b>.'),
  'baslikBos'  => k_c('Başlık boş olamaz.', 'The title cannot be empty.'),
  'eYUnvan'    => k_c(' için unvan en az "Dr." olmalıdır.', ' must hold at least a "Dr." title.'),
  'eYOrcid'    => k_c(' için geçerli bir ORCID zorunludur.', ' needs a valid ORCID.'),
  'eYScopus'   => k_c(' için girilen Scopus Author ID geçerli değil.', ' has an invalid Scopus Author ID.'),
  'eYbOrcid'   => k_c('İletişim yazarı için geçerli bir ORCID zorunludur.', 'A valid ORCID is required for the corresponding author.'),
  'eYbScopus'  => k_c('Scopus Author ID girecekseniz geçerli olmalıdır.', 'If you enter a Scopus Author ID it must be valid.'),
  'kaydediliyor' => k_c('Kaydediliyor...', 'Saving...'),
  'kaydedildi' => k_c('Kaydedildi.', 'Saved.'),
  'kaydedilemedi' => k_c('Kaydedilemedi.', 'Could not be saved.'),
  'ozBos'      => k_c('Bölümleri doldurdukça okurun göreceği hâl burada belirir.', 'As you fill in the sections, what the reader will see appears here.'),
  'kyBaslik'   => k_c('başlık', 'the title'),
  'kyOzet'     => k_c('öz', 'the abstract'),
  'kyBos'      => k_cd('Künye dilindeki %1 boş bırakılamaz: dizinler ve başka dilden okurun atıf künyesi onu okur. Değiştirebilirsiniz, ama silemezsiniz.', 'The %1 in the metadata language cannot be left empty: indexes and the citation entry seen by readers in other languages read it. You may change it, but you cannot delete it.', '%1'),
  'etikSimdi'  => k_c('Şu anki durum:', 'Current state:'),
  'etikSec'    => k_c('Etik kurul izninin gerekli olup olmadığını seçin.', 'Choose whether ethics committee approval is required.'),
  'etikEksik'  => k_c('İzin gerekliyse kurul adı, karar tarihi ve karar numarasının üçü de yazılmalıdır.', 'If approval is required, the committee name, decision date and decision number are all needed.'),
  'etikOk'     => k_c('Beyan kaydedildi ve çalışmanızın sayfasında görünür.', 'The declaration was saved and appears on your work\'s page.'),
  'hataBag'    => k_c('Bağlantı hatası.', 'Connection error.'),
  'hakemAdGir' => k_c('Hakem adını girin.', 'Enter the reviewer\'s name.'),
  'davetOlus'  => k_c('Öneri gönderiliyor...', 'Sending the suggestion...'),
  'davetOk'    => k_c('Öneriniz editöre iletildi.', 'Your suggestion has been sent to an editor.'),
  'davetOlmadi'=> k_c('Öneri gönderilemedi.', 'The suggestion could not be sent.'),
  /* Erişim bilgisi yalnızca yazarın önerdiği hakem için gösterilir;
     editörün atadığı hakemin sayfasına yazar giremez. */
  'erisimYok'  => k_c('Bu hakemi editör davet etti. Erişim bilgisi yazarla paylaşılmaz.', 'This reviewer was invited by an editor. Their access details are not shared with the author.'),
  /* Yazar ile hakem yazışması */
  'diyBas'     => k_c('Hakemle yazışma', 'Correspondence with the reviewer'),
  'diyYazar'   => k_c('Siz', 'You'),
  'diyHakem'   => k_c('Hakem', 'Reviewer'),
  'diyEtiket'  => k_c('Bu rapora yazılı yanıt yazın:', 'Write a response to this report:'),
  'diyYer'     => k_c('Yanlış okunduğunu düşündüğünüz noktayı ve metnin neresinde yanıtlandığını açıklayın. Hakem kararını vermeden önce bunu görür.', 'Explain the point you believe was misread, and where in the text it is answered. The reviewer sees this before deciding.'),
  'diyDugme'   => k_c('Yanıtımı gönder', 'Send my response'),
  'diyKisa'    => k_c('Yanıtınız en az 80 karakter olmalıdır.', 'Your response must be at least 80 characters.'),
  'diyGonder'  => k_c('Gönderiliyor...', 'Sending...'),
  'diyOk'      => k_c('Yanıtınız kayda geçti ve hakeme iletildi.', 'Your response has been recorded and sent to the reviewer.'),
  'diyOlmadi'  => k_c('Yanıt gönderilemedi.', 'The response could not be sent.'),
  'baglantiEt' => k_c('Bağlantı:', 'Link:'),
  'kopyalaKisa'=> k_c('Kopyala', 'Copy'),
  'dil'        => k_dil(),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$kararAd = json_encode([
  'kabul' => k_c('Kabul', 'Accepted'),
  'kucuk' => k_c('Küçük revizyon', 'Minor revision'),
  'buyuk' => k_c('Büyük revizyon', 'Major revision'),
  'ret'   => k_c('Ret', 'Rejected'),
], JSON_UNESCAPED_UNICODE);

$betik = <<<JS
<script>
(function(){
  var S = {$S};
  var KARARAD = {$kararAd};
  /* Karar rengi bir renk kodu değil, rozet ailesinin bir üyesidir. */
  var KARARSNF={kabul:'rz-yes',kucuk:'rz-lac',buyuk:'rz-kut',ret:'rz-kir'};
  function \$(id){return document.getElementById(id);}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  var SP=new URLSearchParams(location.search);
  var token=SP.get('t')||'';
  /* Erişim iki yoldan olur: e-postayla gelen bağlantı (t=) ya da hesaba
     girmiş olmak. İkincisinde adreste çalışmanın kısa adı bulunur
     (y=) ve kilit ekranı hiç gösterilmez. */
  var slug=SP.get('y')||SP.get('id')||'';
  var mail='',sifre='',kilitli=false;
  var yazarKutu=null;

  function orcidTemiz(s){
    s=String(s||'').toUpperCase().trim().replace(/^HTTPS?:\\/\\/(WWW\\.)?ORCID\\.ORG\\//,'');
    var d=s.replace(/[^0-9X]/g,'');
    if(d.length!==16) return '';
    if(d.slice(0,15).indexOf('X')!==-1) return '';
    var t=0; for(var i=0;i<15;i++) t=(t+parseInt(d.charAt(i),10))*2;
    var son=(12-(t%11))%11, bek=(son===10)?'X':String(son);
    if(d.charAt(15)!==bek) return '';
    return d.slice(0,4)+'-'+d.slice(4,8)+'-'+d.slice(8,12)+'-'+d.slice(12,16);
  }
  function scopusTemiz(s){
    s=String(s||'').trim();
    var mm=s.match(/authorId=(\\d{6,})/i); if(mm) s=mm[1];
    var d=s.replace(/\\D/g,'');
    if(!d||d.length<8||d.length>16) return '';
    return d;
  }
  function unvanYeterli(u){ return /(^|\\s)(dr|prof|do[çc])\\b|dr\\./i.test(String(u||'').trim()); }

  function yazarSatir(v){
    v=v||{};
    var d=document.createElement('div'); d.className='yazar-sat';
    var no=yazarKutu.querySelectorAll('.yazar-sat').length+1;
    d.innerHTML='<div class="ysira">'+no+S.yazarNo+'</div>'+
      '<div class="alan-ikili"><div><label>'+S.unvanEt+'</label><select class="y-unvan">'+S.unvanSec+'</select></div>'+
      '<div><label>'+S.adEt+'</label><input type="text" class="y-ad"></div></div>'+
      '<label>'+S.kurumEt+'</label><input type="text" class="y-kurum">'+
      '<div class="alan-ikili"><div><label>ORCID <b class="zor">'+S.zorunlu+'</b></label><input type="text" class="y-orcid" placeholder="0000-0000-0000-0000"></div>'+
      '<div><label>Scopus Author ID <small>'+S.istege+'</small></label><input type="text" class="y-scopus" placeholder="7004212771"></div></div>'+
      '<button type="button" class="d d-ikinci d-kucuk sil">'+S.kaldir+'</button>';
    d.querySelector('.y-unvan').value=v.unvan||'';d.querySelector('.y-ad').value=v.ad||'';
    d.querySelector('.y-kurum').value=v.kurum||'';
    d.querySelector('.y-orcid').value=v.orcid||'';d.querySelector('.y-scopus').value=v.scopus||'';
    d.querySelector('.sil').addEventListener('click',function(){d.remove();});
    yazarKutu.appendChild(d);
  }

  \$('yukleniyor').classList.add('gizli');
  if(!token && !slug){\$('hata').textContent=S.gecersiz;\$('hata').classList.remove('gizli');return;}
  /* Oturumla açılış: kilit ekranı yok, doğrudan yüklenir.
     ÖNEMLİ: bu iş, betiğin geri kalanı (yazar kutusu, olay bağlamaları)
     kurulduktan SONRA yapılmalıdır. Daha önce burada erken bir çıkış
     vardı ve sayfanın kurulumu hiç çalışmıyordu. setTimeout(0), bu
     betik sonuna kadar okunduktan sonra çalışmayı güvenceye alır. */
  if(!token && slug){ setTimeout(function(){
    \$('yukleniyor').classList.remove('gizli');
    fetch('/api/yazar-form',{method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json'},body:JSON.stringify({y:slug})})
      .then(function(r){return r.json();}).then(function(d){
        \$('yukleniyor').classList.add('gizli');
        if(!d||!d.ok){ \$('hata').textContent=(d&&d.hata)||S.gecersiz; \$('hata').classList.remove('gizli'); return; }
        try { doldur(d); } catch(err){
          /* Yükleme hatasını "bağlantı kurulamadı" diye göstermek yanlış
             yere baktırır: sunucu yanıt vermişti, hata buradaydı. */
          \$('hata').textContent='Sayfa yüklenirken hata: '+(err&&err.message?err.message:err);
          \$('hata').classList.remove('gizli');
          if(window.console) console.error('yazar paneli:', err);
          return;
        }
        \$('panel').classList.remove('gizli');
      }).catch(function(err){ \$('yukleniyor').classList.add('gizli');
        \$('hata').textContent=S.baglanti+(err&&err.message?(' ('+err.message+')'):'');
        \$('hata').classList.remove('gizli');
        if(window.console) console.error('yazar paneli:', err); });
  }, 0); }
  \$('kilit').classList.remove('gizli');
  yazarKutu=\$('yazarlar');
  \$('yazarEkle').addEventListener('click',function(){yazarSatir();});

  /* Ad ve onay kutusu yalnız çeviri söz konusuysa anlamlıdır: yazarın
     kendi yazdığı metin için "çeviren kim" diye sormak, sorulmaması
     gereken bir soruyu sormaktır. Betik çalışmazsa kutu açık kalır ve
     yine de doldurulabilir; gizleme bir kolaylıktır, kural değil. */
  function ceviriEkGoster(){
    var k=\$('ceviri_kaynak'); var e=\$('ceviriEk');
    if(!k||!e) return;
    e.hidden = (k.value==='yazar');
  }
  if(\$('ceviri_kaynak')) \$('ceviri_kaynak').addEventListener('change',ceviriEkGoster);

  function doldur(d){
    var y=d.yazi||{};
    /* Düzenleyiciye bağlı alanlar kdAyarla ile yazılır: textarea'ya
       doğrudan yazmak düzenleyiciyi güncellemez, metin görünmez olurdu. */
    \$('baslik').value=y.baslik||''; \$('anahtar').value=y.anahtar||'';
    kdAyarla('ozet',y.ozet); kdAyarla('metin',y.metin); kdAyarla('kaynakca',y.kaynakca);
    \$('baslik_en').value=y.baslik_en||''; \$('anahtar_en').value=y.anahtar_en||'';
    kdAyarla('ozet_en',y.ozet_en); kdAyarla('metin_en',y.metin_en); kdAyarla('kaynakca_en',y.kaynakca_en);
    /* Çevirinin künyesi. Kayıtta yoksa 'yazar' seçili kalır: bugüne
       kadarki İngilizce metinler başvuru formundaki kutudan, yani
       yazarın kendi elinden geldi ve onlara başka bir şey demek
       bilinmeyen bir şeyi biliyormuş gibi kaydetmek olurdu. */
    var cv=y.ceviri_en||{};
    \$('ceviri_kaynak').value=cv.kaynak||'yazar';
    \$('ceviri_ceviren').value=cv.ceviren||'';
    \$('ceviri_onay').checked=!!cv.onay;
    ceviriEkGoster();
    \$('doi').value=y.doi||'';
    var yb=y.yazar_bilgi||{};
    \$('yb_unvan').value=yb.unvan||'';\$('yb_ad').value=yb.ad||'';\$('yb_eposta').value=yb.eposta||'';
    \$('yb_orcid').value=yb.orcid||'';\$('yb_kurum').value=yb.kurum||'';\$('yb_scopus').value=yb.scopus||'';
    \$('yb_web').value=yb.web||'';
    yazarKutu.innerHTML='';(y.yazar_liste||[]).forEach(function(ya){yazarSatir(ya);});
    if(y.slug)\$('gorLink').href='/yazi.php?y='+encodeURIComponent(y.slug)+'&lang='+S.dil;
    kilitli=!!d.kilitli;
    if(kilitli){
      \$('kilitliUyari').classList.remove('gizli');
      \$('kaydet').disabled=true;\$('davet').disabled=true;
      ['baslik','ozet','anahtar','metin','kaynakca','baslik_en','ozet_en','anahtar_en','metin_en',
       'ceviri_kaynak','ceviri_ceviren','ceviri_onay',
       'kaynakca_en','doi','yb_unvan','yb_ad','yb_eposta','yb_orcid','yb_kurum','yb_scopus','yb_web',
       'hAd','hEposta','wordDosya','wordIng','wordAktar'].forEach(function(id){
        var el=\$(id); if(!el) return; el.disabled=true;
        /* Düzenleyiciye bağlı alan da salt okunur olmalı; textarea'yı
           kapatmak düzenleyiciyi kapatmaz. */
        if(el.joditKur){ try{ el.joditKur.setReadOnly(true); }catch(e){} }
      });
      \$('yazarEkle').disabled=true;
    }
    etikDoldur(d.etik||{});
    kunyeCiz(d.kunye||{}, y.dil||'');
    ozYapiDoldur(y.ozet_yapi||[]);
    cizHakemler(d.hakemler||[]);
  }

  /* ---- KÜNYE BÖLÜMÜ ----
     Kural sunucudan gelir, burada yeniden yazılmaz. İki durum var:
       - çalışmanın dili zaten künye diliyse bölüm gereksizdir ve
         kapanır (aynı metni ikinci kez istemek olurdu),
       - değilse zorunluluk imi olduğu gibi durur.
     Zorunluluğu burada da yazsaydık, ayar değiştiği gün sayfa bir şey
     söyler uç başka bir şey uygulardı. */
  var KUNYE_ZOR = false;
  /* AÇILIŞTA DOLU OLAN KÜNYE ALANLARI. Kural "boş olan doldurulsun"
     değil, "DOLU OLAN BOŞALTILMASIN"dır: künye 15 Ağustos 2026'da
     zorunlu oldu ve ondan önce yayımlanmış çalışmalarda hiç istenmedi.
     Boş bir künyeyi şimdi zorunlu kılmak, arşiv yazarını bir yazım
     yanlışını düzeltmekten bile alıkoyardı. */
  var kyDolu = {};
  function kunyeCiz(k, dil){
    var ayni = !!(k && k.kod && dil && dil === k.kod);
    KUNYE_ZOR = !!(k && k.zorunlu) && !ayni;
    var ack=\$('kyAck'), ay=\$('kyAyniDil');
    if(ack) ack.classList.toggle('gizli', ayni);
    if(ay)  ay.classList.toggle('gizli', !ayni);
    /* Gereksizse imler de kalkar: gerekmeyen bir alanın yanında duran
       "zorunlu" imi, kişiye doldurulacak bir şey kaldığını söyler. */
    Array.prototype.forEach.call(document.querySelectorAll('[data-kyzor]'),function(e){
      e.hidden = ayni;
    });
    ['baslik_en','ozet_en'].forEach(function(id){
      var el=\$(id);
      var v=(el&&el.joditKur&&window.kdOku)?window.kdOku(id):(el?el.value:'');
      kyDolu[id] = String(v||'').replace(/<[^>]*>/g,'').trim() !== '';
    });
  }

  /* ---- YAPILANDIRILMIŞ ÖZ ----
     Özet, yapı açıkken bölümlerden kurulur ve kutusu salt okunur olur:
     iki yerde birbirinden habersiz yazılabilen tek bir olgu er geç
     ikiye ayrılır. Yapı kapatılınca yazılan özet OLDUĞU GİBİ KALIR —
     kapatmak yazdığını silmek değildir; sunucu da yapı gelmediğinde
     onu düşürür, yani kayıt da ekranla aynı şeyi söyler. */
  function ozYzAlan(){ return [].slice.call(document.querySelectorAll('[data-yzozbolum]')); }
  function ozYzAcikMi(){ var c=\$('ozetYapili'); return !!(c&&c.checked); }
  function ozYzOzet(){
    var p=[];
    ozYzAlan().forEach(function(e){
      var v=e.value.replace(/\s+/g,' ').trim();
      if(v) p.push((e.previousElementSibling?e.previousElementSibling.textContent.trim():'')+': '+v);
    });
    return p.join(' ');
  }
  /* Önizleme, çalışma sayfasının kendi biçimiyle çizilir; bölüm adları
     etikettir, başlık değil (aynı kural yazi.php'de yazılı). */
  function ozYzOnizle(){
    var kap=\$('ozYapiOnizle'); if(!kap) return;
    var h='';
    ozYzAlan().forEach(function(e){
      var v=e.value.replace(/\s+/g,' ').trim();
      if(!v) return;
      var ad=e.previousElementSibling?e.previousElementSibling.textContent.trim():'';
      h+='<p><b>'+esc(ad)+'</b> '+esc(v)+'</p>';
    });
    kap.innerHTML = h || '<p class="ipucu">'+esc(S.ozBos)+'</p>';
  }
  function ozYzCiz(){
    var acik=ozYzAcikMi(), kutu=\$('ozetYapi'), oz=\$('ozet');
    if(kutu) kutu.hidden=!acik;
    ozYzOnizle();
    if(!oz) return;
    if(acik && window.kdAyarla) window.kdAyarla('ozet', ozYzOzet());
    else if(acik) oz.value=ozYzOzet();
    if(oz.joditKur){ try{ oz.joditKur.setReadOnly(acik); }catch(e){} }
    else oz.readOnly=acik;
  }
  function ozYapiDoldur(list){
    var v={};
    (list||[]).forEach(function(b){ if(b&&b.k) v[b.k]=b.m||''; });
    ozYzAlan().forEach(function(e){ e.value=v[e.getAttribute('data-yzozbolum')]||''; });
    var c=\$('ozetYapili'); if(c) c.checked=(list||[]).length>0;
    ozYzCiz();
  }
  function ozYapiTopla(){
    if(!ozYzAcikMi()) return null;
    var o={};
    ozYzAlan().forEach(function(e){
      var t=e.value.replace(/\s+/g,' ').trim();
      if(t) o[e.getAttribute('data-yzozbolum')]=t;
    });
    return o;
  }
  if(\$('ozetYapili')) \$('ozetYapili').addEventListener('change',ozYzCiz);
  ozYzAlan().forEach(function(e){ e.addEventListener('input',ozYzCiz); });
  ozYzCiz();

  /* ---- ETİK BEYANI ----
     Kilit bu bölümü KAPATMAZ (bkz. api /yazar-kaydet gerekçesi), bu
     yüzden yukarıdaki kilit döngüsünde etik alanları yoktur. */
  function etikGoster(){
    var v=document.querySelector('input[name=etik_durum]:checked');
    \$('etikAlan').hidden = !(v && v.value==='gerekli');
  }
  function etikDoldur(e){
    var d=e.durum||'';
    if(d==='gerekli'||d==='gereksiz'){
      var r=document.querySelector('input[name=etik_durum][value="'+d+'"]');
      if(r)r.checked=true;
    }
    \$('etikKurul').value=e.kurul||'';
    \$('etikTarih').value=e.tarih||'';
    \$('etikNo').value=e.no||'';
    var not=\$('etikDurumNot');
    var ad=(S.dil==='en'?e.hal_ad_en:e.hal_ad)||'';
    not.textContent = ad ? (S.etikSimdi+' '+ad) : '';
    /* Beyanı verilmiş çalışmada "gerekmiyor" seçeneği kapatılır:
       verilmiş bir beyan geri alınamaz. Uç nokta da aynı kuralı
       uygular; burada kapatmak, reddedilecek bir düğmeyi kişiye
       bastırmamak içindir. */
    if(e.kilitli){
      var g=\$('etikYok'); if(g)g.disabled=true;
      \$('etikKilitNot').hidden=false;
    }
    etikGoster();
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name=etik_durum]'),
    function(r){r.addEventListener('change',etikGoster);});
  etikGoster();
  \$('etikKaydet').addEventListener('click',function(){
    var m=\$('etikMsj');
    var v=document.querySelector('input[name=etik_durum]:checked');
    if(!v){m.textContent=S.etikSec;m.className='form-msj err';return;}
    var g={durum:v.value};
    if(v.value==='gerekli'){
      g.kurul=\$('etikKurul').value.trim();
      g.tarih=\$('etikTarih').value.trim();
      g.no=\$('etikNo').value.trim();
      if(!g.kurul||!g.tarih||!g.no){m.textContent=S.etikEksik;m.className='form-msj err';return;}
    }
    \$('etikKaydet').disabled=true;m.textContent=S.kaydediliyor;m.className='form-msj';
    fetch('/api/yazar-kaydet',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({t:token,mail:mail,sifre:sifre,y:slug,etik:g})})
      .then(function(r){return r.json();}).then(function(d){
        \$('etikKaydet').disabled=false;
        if(d&&d.ok){m.textContent=S.etikOk;m.className='form-msj ok';}
        else{m.textContent=(d&&d.hata)||S.kaydedilemedi;m.className='form-msj err';}
      }).catch(function(){\$('etikKaydet').disabled=false;m.textContent=S.hataBag;m.className='form-msj err';});
  });

  function cizHakemler(hs){
    var box=\$('hakemListe'); box.innerHTML='';
    if(!hs.length){box.innerHTML='<p class="bos-durum">'+S.hakemYok+'</p>';return;}
    hs.forEach(function(h){
      var d=document.createElement('div'); d.className='kart';
      var html='<div class="hk-bas"><b>'+esc(h.ad)+'</b>';
      if(h.karar)html+='<span class="rz '+(KARARSNF[h.karar]||'rz-kut')+'">'+esc(KARARAD[h.karar]||h.kararAd||h.karar)+'</span>';
      else html+='<span class="rz rz-cizgi">'+S.bekliyor+'</span>';
      html+='</div>';
      if(h.rapor)html+='<div class="hk-rapor">'+esc(h.rapor)+'</div>';
      if(h.endeks&&h.endeks.length){html+='<div class="hk-end"><b>'+S.onerilen+'</b> <span class="d-kume">';
        h.endeks.forEach(function(e){html+='<span class="rz rz-cizgi">'+esc(e)+'</span>';});html+='</span></div>';}
      if(h.notlar&&h.notlar.length){html+='<div class="hk-end"><b>'+S.isaretli+'</b></div>';
        h.notlar.forEach(function(n){html+='<div class="kutu hk-not">'+(n.alinti?'<div class="hk-not-al">'+esc(n.alinti)+'</div>':'')+
          (n['not']?'<div class="hk-not-nt">'+esc(n['not'])+'</div>':'')+'</div>';});}
      if(h.dosya)html+='<div class="hk-dosya"><a class="d d-ikinci d-kucuk" href="'+esc(h.dosya)+'" download>'+S.hakemDosya+'</a></div>';
      /* ---- Yazışma ----
         Rapordan sonra gelir. Yazma kutusu yalnızca sıra yazardaysa
         açılır; sıra kuralı sunucudadır (tg_diyalog_sira) ve burada
         yalnızca gösterilir. */
      if(h.diyalog&&h.diyalog.length){
        html+='<div class="hakem-diyalog"><h4>'+S.diyBas+'</h4>';
        h.diyalog.forEach(function(e){
          html+='<div class="hd-sat hd-'+(e.yon==='yazar'?'yazar':'hakem')+'">'
             +'<span class="hd-kim">'+(e.yon==='yazar'?S.diyYazar:S.diyHakem)
             +(e.tarih?(' · '+esc(e.tarih.slice(0,10))):'')+'</span>'
             +'<div class="hd-metin">'+esc(e.metin)+'</div></div>';
        });
        html+='</div>';
      }
      if(!kilitli&&h.diyalog_sira==='yazar'){
        html+='<div class="hk-diy-yaz"><label>'+S.diyEtiket+'</label>'
           +'<textarea class="diy-metin" rows="4" placeholder="'+S.diyYer+'"></textarea>'
           +'<div class="d-kume"><button type="button" class="d d-ikinci d-kucuk diy-gonder" data-h="'+esc(h.ad)+'">'
           +S.diyDugme+'</button></div><div class="form-msj diy-msj"></div></div>';
      }
      /* Erişim bilgisi yalnızca yazarın kendi önerdiği ve eski düzende
         doğrudan davet ettiği hakem için gelir; sunucu başka hiçbir
         hakem için bu alanları doldurmaz. Burada da boşsa çizilmez. */
      if(!kilitli&&h.link&&h.sifre){
        html+='<div class="kutu kutu-kut link-kutu"><b>'+S.davetBag+'</b> '+esc(h.link)+'<br><b>'+S.sifreEt+'</b> '+esc(h.sifre)+
              '<br><button type="button" class="d d-ikinci d-kucuk kopyala">'+S.kopyaDg+'</button></div>';
      }else if(!kilitli){
        html+='<div class="kutu"><span class="metin-sonuk">'+S.erisimYok+'</span></div>';
      }
      d.innerHTML=html;
      var dg=d.querySelector('.diy-gonder');
      if(dg)dg.addEventListener('click',function(){
        var m=d.querySelector('.diy-msj'), tx=d.querySelector('.diy-metin');
        var v=tx.value.trim();
        if(v.length<80){ m.textContent=S.diyKisa; m.className='form-msj diy-msj err'; return; }
        dg.disabled=true; m.textContent=S.diyGonder; m.className='form-msj diy-msj';
        fetch('/api/yazar-hakem-yanit',{method:'POST',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({t:token,mail:mail,sifre:sifre,y:slug,hakem:dg.dataset.h,metin:v})})
          .then(function(r){return r.json();}).then(function(j){
            dg.disabled=false;
            if(j&&j.ok){ m.textContent=j.mesaj||S.diyOk; m.className='form-msj diy-msj ok'; yenile(); }
            else { m.textContent=(j&&j.hata)||S.diyOlmadi; m.className='form-msj diy-msj err'; }
          }).catch(function(){ dg.disabled=false; m.textContent=S.diyOlmadi; m.className='form-msj diy-msj err'; });
      });
      var kp=d.querySelector('.kopyala');
      if(kp)kp.addEventListener('click',function(){
        var t=S.kopyaMetin+h.link+'\\n'+S.kopyaSifre+h.sifre;
        if(navigator.clipboard)navigator.clipboard.writeText(t);
        kp.textContent=S.kopyalandi;setTimeout(function(){kp.textContent=S.kopyaDg;},1500);});
      box.appendChild(d);
    });
  }

  function acmayiDene(){
    var m=\$('mail').value.trim(),s=\$('sifre').value.trim(),km=\$('kilitMsj');
    if(!/.+@.+\\..+/.test(m)){km.textContent=S.ePosta;km.className='form-msj err';return;}
    if(!s){km.textContent=S.eSifre;km.className='form-msj err';return;}
    \$('ac').disabled=true;km.textContent=S.kontrol;km.className='form-msj';
    fetch('/api/yazar-form',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({t:token,mail:m,sifre:s,y:slug})})
      .then(function(r){return r.json();}).then(function(d){
        \$('ac').disabled=false;
        if(!d||!d.ok){km.textContent=(d&&d.hata)||S.bulunamadi;km.className='form-msj err';return;}
        mail=m;sifre=s;doldur(d);
        \$('kilit').classList.add('gizli');\$('panel').classList.remove('gizli');
      }).catch(function(){\$('ac').disabled=false;km.textContent=S.baglanti;km.className='form-msj err';});
  }
  \$('ac').addEventListener('click',acmayiDene);
  \$('mail').addEventListener('keydown',function(e){if(e.key==='Enter')acmayiDene();});
  \$('sifre').addEventListener('keydown',function(e){if(e.key==='Enter')acmayiDene();});

  /* Sekmeler */
  Array.prototype.forEach.call(document.querySelectorAll('.sek-bar button'),function(b){
    b.addEventListener('click',function(){
      Array.prototype.forEach.call(document.querySelectorAll('.sek-bar button'),function(x){x.classList.remove('acik');});
      b.classList.add('acik');
      \$('s-makale').classList.toggle('gizli',b.getAttribute('data-sekme')!=='makale');
      \$('s-hakem').classList.toggle('gizli',b.getAttribute('data-sekme')!=='hakem');
    });
  });

  /* Word belgesinden aktar */
  \$('wordAktar').addEventListener('click',function(){
    if(kilitli)return;
    var m=\$('wordMsj'), f=\$('wordDosya');
    if(!f.files||!f.files[0]){m.textContent=S.wordSec;m.className='form-msj err';return;}
    var ad=(f.files[0].name||'').toLowerCase();
    if(ad.slice(-5)!=='.docx'){m.textContent=S.wordTur;m.className='form-msj err';return;}
    var ing=\$('wordIng').checked;
    \$('wordAktar').disabled=true;m.textContent=S.wordCoz;m.className='form-msj';
    var fd=new FormData();
    fd.append('dosya',f.files[0]);fd.append('t',token);fd.append('mail',mail);fd.append('sifre',sifre);fd.append('y',slug);
    fetch('/api/word-cevir',{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
      \$('wordAktar').disabled=false;
      if(!d||!d.ok){m.textContent=(d&&d.hata)||S.wordOlmadi;m.className='form-msj err';return;}
      var e=ing?'_en':'';
      function koy(id,deger){ if(deger&&String(deger).trim()!=='') \$(id).value=deger; }
      koy('baslik'+e, d.baslik);
      koy('ozet'+e,   ing?(d.ozet_en||d.ozet):d.ozet);
      koy('anahtar'+e,ing?(d.anahtar_en||d.anahtar):d.anahtar);
      koy('metin'+e,  d.metin);
      koy('kaynakca'+e, ing?(d.kaynakca_en||d.kaynakca):d.kaynakca);
      if(!ing){ koy('ozet_en',d.ozet_en); koy('anahtar_en',d.anahtar_en); koy('kaynakca_en',d.kaynakca_en); }
      var i=d.istatistik||{};
      \$('wordOzet').innerHTML='<div class="kutu kutu-kut link-kutu"><b>'+S.wordAktarildi+'</b><br>'+
        (i.kelime?(i.kelime+S.wordKelime+' · '):'')+
        (i.baslik_sayisi?(i.baslik_sayisi+S.wordBaslik+' · '):'')+
        (i.tablo?(i.tablo+S.wordTablo+' · '):'')+
        S.wordUnut+'</div>';
      m.textContent=S.wordOk;m.className='form-msj ok';
    }).catch(function(){\$('wordAktar').disabled=false;m.textContent=S.hataBag;m.className='form-msj err';});
  });

  /* Kaydet */
  \$('kaydet').addEventListener('click',function(){
    if(kilitli)return;
    var m=\$('kaydetMsj');
    if(!\$('baslik').value.trim()){m.textContent=S.baslikBos;m.className='form-msj err';return;}
    var yazarlar=[],ykHata=null;
    Array.prototype.forEach.call(yazarKutu.querySelectorAll('.yazar-sat'),function(s){
      var a=s.querySelector('.y-ad').value.trim();
      if(!a)return;
      var u=s.querySelector('.y-unvan').value.trim();
      var o=orcidTemiz(s.querySelector('.y-orcid').value),sc=scopusTemiz(s.querySelector('.y-scopus').value);
      if(!ykHata&&!unvanYeterli(u))ykHata=a+S.eYUnvan;
      else if(!ykHata&&!o)ykHata=a+S.eYOrcid;
      else if(!ykHata&&s.querySelector('.y-scopus').value.trim()&&!sc)ykHata=a+S.eYScopus;
      if(o)s.querySelector('.y-orcid').value=o;
      if(sc)s.querySelector('.y-scopus').value=sc;
      yazarlar.push({unvan:u,ad:a,kurum:s.querySelector('.y-kurum').value.trim(),orcid:o,scopus:sc});
    });
    if(ykHata){m.textContent=ykHata;m.className='form-msj err';return;}
    /* KÜNYE: DOLU OLAN BOŞALTILAMAZ. Aynı denetim sunucuda da var
       (uç, tarayıcıya güvenmez); buradaki, reddedilecek bir kaydı
       kişiye göndertmemek içindir. */
    if(KUNYE_ZOR){
      var kyE=[['baslik_en',S.kyBaslik],['ozet_en',S.kyOzet]];
      for(var ki=0;ki<kyE.length;ki++){
        var el=\$(kyE[ki][0]);
        var deg=(el&&el.joditKur&&window.kdOku)?window.kdOku(kyE[ki][0]):(el?el.value:'');
        var duz=String(deg||'').replace(/<[^>]*>/g,'').trim();
        if(kyDolu[kyE[ki][0]] && !duz){
          m.textContent=S.kyBos.replace('%1',kyE[ki][1]); m.className='form-msj err';
          if(el&&el.focus)el.focus();
          return;
        }
      }
    }
    var ybO=orcidTemiz(\$('yb_orcid').value);
    if(!ybO){m.textContent=S.eYbOrcid;m.className='form-msj err';\$('yb_orcid').focus();return;}
    \$('yb_orcid').value=ybO;
    var ybSHam=\$('yb_scopus').value.trim();
    var ybS=scopusTemiz(ybSHam);
    if(ybSHam&&!ybS){m.textContent=S.eYbScopus;m.className='form-msj err';\$('yb_scopus').focus();return;}
    if(ybS)\$('yb_scopus').value=ybS;
    \$('kaydet').disabled=true;m.textContent=S.kaydediliyor;m.className='form-msj';
    fetch('/api/yazar-kaydet',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      t:token,mail:mail,sifre:sifre,y:slug,
      baslik:\$('baslik').value,baslik_en:\$('baslik_en').value,ozet:kdOku('ozet'),ozet_en:kdOku('ozet_en'),
      anahtar:\$('anahtar').value,anahtar_en:\$('anahtar_en').value,metin:kdOku('metin'),metin_en:kdOku('metin_en'),
      kaynakca:kdOku('kaynakca'),kaynakca_en:kdOku('kaynakca_en'),doi:\$('doi').value,
      /* Yapı HER KAYITTA gönderilir; kapalıysa null gider ve sunucu
         kayıttaki yapıyı düşürür. Yalnızca açıkken göndermek, yapıyı
         kapatan yazarın kaydında onu bırakırdı — özeti anlatmayan bir
         yapı, olmayan bir yapıdan kötüdür. */
      ozet_yapi:ozYapiTopla(),
      ceviri_en:{kaynak:\$('ceviri_kaynak').value,ceviren:\$('ceviri_ceviren').value,
                 onay:\$('ceviri_onay').checked},
      yazar_bilgi:{unvan:\$('yb_unvan').value,ad:\$('yb_ad').value,eposta:\$('yb_eposta').value,orcid:\$('yb_orcid').value,
                   kurum:\$('yb_kurum').value,scopus:\$('yb_scopus').value,web:\$('yb_web').value},
      yazarlar:yazarlar
    })}).then(function(r){return r.json();}).then(function(d){
      \$('kaydet').disabled=false;
      if(d&&d.ok){m.textContent=S.kaydedildi;m.className='form-msj ok';
        if(d.slug)\$('gorLink').href='/yazi.php?y='+encodeURIComponent(d.slug)+'&lang='+S.dil;}
      else{m.textContent=(d&&d.hata)||S.kaydedilemedi;m.className='form-msj err';}
    }).catch(function(){\$('kaydet').disabled=false;m.textContent=S.hataBag;m.className='form-msj err';});
  });

  /* Hakem önerisi. Artık davet gitmiyor: öneri editöre düşüyor ve
     yazarın eline hiçbir erişim bilgisi geçmiyor. */
  \$('davet').addEventListener('click',function(){
    if(kilitli)return;
    var m=\$('davetMsj');
    var ad=\$('hAd').value.trim();
    if(!ad){m.textContent=S.hakemAdGir;m.className='form-msj err';return;}
    \$('davet').disabled=true;m.textContent=S.davetOlus;m.className='form-msj';
    fetch('/api/yazar-hakem-davet',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({t:token,mail:mail,sifre:sifre,y:slug,ad:ad,
        eposta:\$('hEposta').value.trim(),kurum:\$('hKurum')?\$('hKurum').value.trim():'',
        orcid:\$('hOrcid')?\$('hOrcid').value.trim():'',gerekce:\$('hGerekce')?\$('hGerekce').value.trim():''})})
      .then(function(r){return r.json();}).then(function(d){
        \$('davet').disabled=false;
        if(d&&d.ok&&d.oneri){
          m.textContent=S.davetOk;m.className='form-msj ok';
          \$('hAd').value='';\$('hEposta').value='';
          if(\$('hKurum'))\$('hKurum').value='';
          if(\$('hOrcid'))\$('hOrcid').value='';
          if(\$('hGerekce'))\$('hGerekce').value='';
          \$('davetSonuc').innerHTML='<div class="kutu kutu-lac"><b>'+esc(d.oneri.ad)+'</b><br>'+esc(d.mesaj||'')+'</div>';
          yenile();
        }else{m.textContent=(d&&d.hata)||S.davetOlmadi;m.className='form-msj err';}
      }).catch(function(){\$('davet').disabled=false;m.textContent=S.hataBag;m.className='form-msj err';});
  });

  function yenile(){
    fetch('/api/yazar-form',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({t:token,mail:mail,sifre:sifre})})
      .then(function(r){return r.json();}).then(function(d){if(d&&d.ok)cizHakemler(d.hakemler||[]);}).catch(function(){});
  }
})();
</script>
JS;

k_son($betik
    . k_istem_betik(['cvAc' => 'cvOv'])
    . kd_betik('metin', 'tam', 420) . kd_betik('ozet', 'kisa', 130) . kd_betik('kaynakca', 'rapor', 200)
    . kd_betik('metin_en', 'tam', 420) . kd_betik('ozet_en', 'kisa', 130) . kd_betik('kaynakca_en', 'rapor', 200));
