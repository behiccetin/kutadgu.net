<?php
/* =====================================================================
   KUTADGU - Uygulama / The app
   Kutadgu, mağazadan indirilen bir uygulama değil, cihaza kurulan bir
   web uygulamasıdır. Bu sayfa her platform için kurulumu anlatır ve
   çevrimdışı okuma, sesle yazma gibi uygulama özelliklerini gösterir.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en = k_en();

$ekBas = <<<CSS
<style>
.uy h2{margin:2.2em 0 .5em;scroll-margin-top:calc(var(--ust) + var(--b-5))}

/* Kurulum çağrısı ve durum satırı. Kutu ve düğme dizgeden gelir. */
.uy-kur{margin-block:1.4em}
.uy-kur p{margin:0;flex:1 1 240px;font-size:var(--y-4)}
.uy-durum{font-size:var(--y-3);color:var(--metin-2);margin:0}

/* Cihaz yönergeleri. Kart dizgeden gelir; burada yalnızca başlığın
   yanındaki im ve adımların ölçüsü var. */
.uy-plat,.uy-ozel{margin-block:1.4em}
.uy-p h3{display:flex;align-items:center;gap:var(--b-2);margin:0 0 var(--b-2);
  font-family:var(--ui);font-size:var(--y-5);font-weight:700}
.uy-p h3 svg{width:20px;height:20px;flex:none;color:var(--kut)}
.uy-p ol{margin:0;padding-left:1.25em;display:grid;gap:var(--b-2)}
.uy-p li{font-size:var(--y-3)}

/* Uygulamanın yaptıkları. */
.uy-o b{display:block;margin-bottom:var(--b-1);font-size:var(--y-4)}
.uy-o span{display:block;font-size:var(--y-3);color:var(--metin-2)}

/* Sesle yazma denemesi. Metin alanının görünüşü dizgeden gelir;
   burada yalnızca düğmeyle arasındaki boşluk ve kayıt sırasındaki
   durum rengi var. */
.uy-deneme{margin-block:1.2em}
.uy-deneme textarea{margin-top:var(--b-3)}
.uy-ses-dg[aria-pressed="true"]{background:var(--kirmizi);border-color:var(--kirmizi);
  color:var(--dolu-metin)}

.uy-not{font-size:var(--y-3);color:var(--metin-2);border-top:1px solid var(--cizgi);
  padding-top:var(--b-5);margin-top:2.4em}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Uygulama', 'The app'),
    'yol'    => '/uygulama.php',
    'aciklama' => k_c(
        'Kutadgu\'yu Android, iPhone, Huawei ve masaüstü cihazlara uygulama olarak kurma yönergeleri; çevrimdışı okuma ve sesle yazma.',
        'How to install Kutadgu as an app on Android, iPhone, Huawei and desktop; offline reading and dictation.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Uygulama', 'The app') ?></span>
      <h1><?= k_c('Kutadgu\'yu cihazınıza kurun', 'Install Kutadgu on your device') ?></h1>
      <p><?= k_c(
      'Kutadgu, bir mağazadan indirilmez. Adresi açtığınızda cihazınıza kurulabilen bir uygulamadır: Android, iPhone, iPad, Huawei, Windows, macOS ve Linux üzerinde aynı şekilde çalışır. Bunun nedeni ilkesel: mağazalar bir aracıdır, aracı olan yerde ücret ve izin vardır. Kutadgu\'nun kimseden izin almadan, ücretsiz ve her cihazda çalışması gerekiyor.',
      'Kutadgu is not downloaded from a store. It is an application that installs on your device when you open its address, and it works the same way on Android, iPhone, iPad, Huawei, Windows, macOS and Linux. The reason is a matter of principle: stores are intermediaries, and where there is an intermediary there are fees and permissions. Kutadgu must run on every device, free of charge, without anyone\'s permission.'
    ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="uy blg-ic">

    <div class="kutu kutu-kut satir uy-kur">
      <p id="uyMetin"><?= k_c(
        'Tarayıcınız kurulumu destekliyorsa aşağıdaki düğme etkinleşir. Etkinleşmezse, cihazınıza uygun adımlar aşağıda yazılıdır.',
        'If your browser supports installation the button below becomes active. If it does not, the steps for your device are written below.'
      ) ?></p>
      <button class="d d-vurgu" type="button" id="uyKur" disabled><?= k_c('Uygulama olarak kur', 'Install as an app') ?></button>
    </div>
    <p class="uy-durum" id="uyDurum"></p>

    <h2 id="kurulum"><?= k_c('Cihaza göre kurulum', 'Installation by device') ?></h2>
    <div class="dizi dizi-2 uy-plat">

      <div class="kart uy-p">
        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="2"/><path d="M11 18h2"/></svg><?= k_c('Android (Chrome, Samsung Internet, Firefox)', 'Android (Chrome, Samsung Internet, Firefox)') ?></h3>
        <ol>
          <li><?= k_c('kutadgu.net adresini tarayıcıda açın.', 'Open kutadgu.net in your browser.') ?></li>
          <li><?= k_c('Sağ üstteki üç noktaya dokunun.', 'Tap the three dots at the top right.') ?></li>
          <li><?= k_c('<b>Uygulamayı yükle</b> ya da <b>Ana ekrana ekle</b> seçeneğine dokunun.', 'Tap <b>Install app</b> or <b>Add to Home screen</b>.') ?></li>
          <li><?= k_c('Onaylayın. Simge ana ekranınıza gelir ve uygulama adres çubuğu olmadan açılır.', 'Confirm. The icon appears on your home screen and the app opens without an address bar.') ?></li>
        </ol>
        <p class="ipucu"><?= k_c('Bazı sürümlerde tarayıcı, siz bir şey yapmadan alt tarafta bir kurulum çubuğu gösterir.', 'On some versions the browser shows an install bar at the bottom without your doing anything.') ?></p>
      </div>

      <div class="kart uy-p">
        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="6" y="2" width="12" height="20" rx="3"/><path d="M10 5.5h4"/></svg><?= k_c('iPhone ve iPad (Safari)', 'iPhone and iPad (Safari)') ?></h3>
        <ol>
          <li><?= k_c('kutadgu.net adresini <b>Safari</b> ile açın. Chrome ile kurulum yapılamaz.', 'Open kutadgu.net in <b>Safari</b>. Installation is not possible from Chrome.') ?></li>
          <li><?= k_c('Alttaki <b>Paylaş</b> simgesine (yukarı oklu kare) dokunun.', 'Tap the <b>Share</b> icon at the bottom (a square with an upward arrow).') ?></li>
          <li><?= k_c('Listede aşağı inip <b>Ana Ekrana Ekle</b> seçeneğine dokunun.', 'Scroll down the list and tap <b>Add to Home Screen</b>.') ?></li>
          <li><?= k_c('Sağ üstten <b>Ekle</b> deyin.', 'Tap <b>Add</b> at the top right.') ?></li>
        </ol>
        <p class="ipucu"><?= k_c('iOS\'ta sesle yazma, işletim sisteminin klavye mikrofonuyla da yapılabilir.', 'On iOS dictation can also be done with the microphone on the system keyboard.') ?></p>
      </div>

      <div class="kart uy-p">
        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="5" y="2" width="14" height="20" rx="2.5"/><path d="M9 6h6"/></svg><?= k_c('Huawei (Petal Browser, HarmonyOS)', 'Huawei (Petal Browser, HarmonyOS)') ?></h3>
        <ol>
          <li><?= k_c('kutadgu.net adresini Petal Browser ya da Huawei Tarayıcı ile açın.', 'Open kutadgu.net in Petal Browser or Huawei Browser.') ?></li>
          <li><?= k_c('Alt çubuktaki menü simgesine dokunun.', 'Tap the menu icon in the bottom bar.') ?></li>
          <li><?= k_c('<b>Ana ekrana ekle</b> ya da <b>Masaüstüne ekle</b> seçeneğine dokunun.', 'Tap <b>Add to home screen</b> or <b>Add to desktop</b>.') ?></li>
          <li><?= k_c('Onaylayın. Google hizmetleri gerekmez; uygulama tümüyle bağımsız çalışır.', 'Confirm. No Google services are needed; the app runs entirely independently.') ?></li>
        </ol>
        <p class="ipucu"><?= k_c('AppGallery\'de bir Kutadgu uygulaması aramayın; yoktur ve olmayacaktır.', 'Do not look for a Kutadgu app in AppGallery; there is none and there will be none.') ?></p>
      </div>

      <div class="kart uy-p">
        <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><rect x="2" y="4" width="20" height="13" rx="2"/><path d="M8 21h8M12 17v4"/></svg><?= k_c('Windows, macOS ve Linux', 'Windows, macOS and Linux') ?></h3>
        <ol>
          <li><?= k_c('kutadgu.net adresini Chrome, Edge ya da Brave ile açın.', 'Open kutadgu.net in Chrome, Edge or Brave.') ?></li>
          <li><?= k_c('Adres çubuğunun sağındaki <b>kurulum</b> simgesine tıklayın (ekran ve aşağı ok).', 'Click the <b>install</b> icon at the right of the address bar (a screen with a downward arrow).') ?></li>
          <li><?= k_c('Ya da menüden <b>Kaydet ve paylaş &rarr; Bu sayfayı uygulama olarak yükle</b> yolunu izleyin.', 'Or follow <b>Save and share &rarr; Install this page as an app</b> from the menu.') ?></li>
          <li><?= k_c('Uygulama kendi penceresinde açılır; görev çubuğuna ya da Dock\'a sabitlenebilir.', 'The app opens in its own window and can be pinned to the taskbar or the Dock.') ?></li>
        </ol>
        <p class="ipucu"><?= k_c('Safari (macOS 14 ve üzeri): Dosya menüsünden <b>Dock\'a Ekle</b>.', 'Safari (macOS 14 and later): <b>Add to Dock</b> from the File menu.') ?></p>
      </div>

    </div>

    <h2 id="neler"><?= k_c('Uygulama olarak neler yapabilir', 'What it can do as an app') ?></h2>
    <div class="dizi dizi-2 uy-ozel">
      <div class="kart kart-duz uy-o">
        <b><?= k_c('Çevrimdışı okuma', 'Reading offline') ?></b>
        <span><?= k_c(
          'Açtığınız her çalışma cihazınızda saklanır. Uçakta, metroda ya da bağlantının olmadığı bir yerde daha önce açtığınız çalışmaları okumayı sürdürebilirsiniz. Bağlantı geldiğinde metin kendiliğinden güncel sürümle değişir.',
          'Every work you open is stored on your device. On a plane, in the metro or anywhere without a connection you can go on reading the works you opened before. When the connection returns the text is replaced by the current version on its own.'
        ) ?></span>
      </div>
      <div class="kart kart-duz uy-o">
        <b><?= k_c('Eşzamanlı ve eşzamansız çalışma', 'Working online and offline') ?></b>
        <span><?= k_c(
          'Bağlantı varken her şey doğrudan sunucuyla çalışır. Bağlantı kesildiğinde yazdıklarınız tarayıcıda tutulur ve bağlantı gelince gönderilir; bir taslak kaybolmaz.',
          'While there is a connection everything works directly with the server. When the connection drops what you have written is held in the browser and sent once it returns; a draft is not lost.'
        ) ?></span>
      </div>
      <div class="kart kart-duz uy-o">
        <b><?= k_c('Sesle yazma', 'Writing by voice') ?></b>
        <span><?= k_c(
          'Yazar panelindeki her metin alanının yanında bir mikrofon düğmesi vardır. Konuştuğunuz metne dönüşür. Ses kaydı hiçbir yere gönderilmez; tanıma cihazınızda ya da tarayıcınızın kendi hizmetinde yapılır.',
          'Beside every text area in the author panel there is a microphone button. What you say becomes text. No audio is sent anywhere; recognition happens on your device or in your browser\'s own service.'
        ) ?></span>
      </div>
      <div class="kart kart-duz uy-o">
        <b><?= k_c('Ana ekran kısayolları', 'Home screen shortcuts') ?></b>
        <span><?= k_c(
          'Simgeye basılı tuttuğunuzda Çalışmalar, Çalışma gönder ve Yazar paneli doğrudan açılır. Uygulama kendi penceresinde, adres çubuğu olmadan çalışır.',
          'Holding down the icon opens Works, Submit a work and the Author panel directly. The app runs in its own window without an address bar.'
        ) ?></span>
      </div>
    </div>

    <h2 id="ses"><?= k_c('Sesle yazmayı burada deneyin', 'Try dictation here') ?></h2>
    <div class="kart uy-deneme">
      <div class="satir">
        <button class="d d-ikinci d-kucuk uy-ses-dg" type="button" id="sesDg" aria-pressed="false" disabled>
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
            <rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 11a7 7 0 0 0 14 0M12 18v4"/>
          </svg>
          <span id="sesEtiket"><?= k_c('Konuşmaya başla', 'Start speaking') ?></span>
        </button>
        <span class="uy-durum" id="sesDurum"></span>
      </div>
      <textarea id="sesAlan" placeholder="<?= k_c('Konuştuklarınız buraya yazılır...', 'What you say appears here...') ?>"></textarea>
    </div>

    <p class="uy-not"><?= k_c(
      'Neden mağaza uygulaması yok: bir uygulamayı mağazaya koymak yıllık ücret, kurumsal hesap ve her güncellemede inceleme demektir. Bu üçü de Kutadgu\'nun kendi bildirisine aykırıdır; sistem hiçbir kuruma bağımlı olmamalı ve hiçbir aşamada ücret doğurmamalıdır. Web uygulaması aynı işi görür, hiçbir izne bağlı değildir ve mağazadan kaldırılamaz.',
      'Why there is no store app: putting an application in a store means an annual fee, a corporate account and a review at every update. All three run against Kutadgu\'s own declaration; the system must depend on no institution and must give rise to no charge at any stage. A web application does the same work, depends on no permission, and cannot be removed from a store.'
    ) ?></p>

   </div>
   <?= k_belge_yan([
     ['k' => 'kurulum', 'tr' => 'Cihaza göre kurulum', 'en' => 'Installation by device'],
     ['k' => 'neler',   'tr' => 'Neler yapabilir',     'en' => 'What it can do'],
     ['k' => 'ses',     'tr' => 'Sesle yazma',         'en' => 'Dictation'],
   ], [
     ['tr' => 'Mağaza yok', 'en' => 'No app store',
      'ic' => k_c('Kutadgu bir mağazadan indirilmez. Adresi açtığınızda kurulur; Android, iPhone, Windows, macOS ve Linux\'ta aynı biçimde çalışır.',
                  'Kutadgu is not downloaded from a store. It installs when you open its address and works the same on Android, iPhone, Windows, macOS and Linux.')],
   ]) ?>
  </div>
</section>
<?php
$S = json_encode([
  'kurulabilir' => k_c('Tarayıcınız kurulumu destekliyor. Düğmeye basmanız yeterli.', 'Your browser can install it. Press the button.'),
  'kuruldu'     => k_c('Kuruldu. Kutadgu\'yu artık ana ekranınızdan açabilirsiniz.', 'Installed. You can now open Kutadgu from your home screen.'),
  'zatenApp'    => k_c('Kutadgu\'yu şu an zaten uygulama olarak kullanıyorsunuz.', 'You are already using Kutadgu as an app.'),
  'elle'        => k_c('Tarayıcınız tek tıkla kurulum sunmuyor. Aşağıdaki adımları izleyin.', 'Your browser does not offer one-click installation. Follow the steps for your device below.'),
  'vazgecti'    => k_c('Kurulumdan vazgeçildi. İstediğiniz zaman yeniden deneyebilirsiniz.', 'Installation was cancelled. You can try again any time.'),
  'sesYok'      => k_c('Bu tarayıcı sesle yazmayı desteklemiyor. iPhone\'da Safari, Android ve masaüstünde Chrome destekler.', 'This browser does not support dictation. Safari on iPhone and Chrome on Android and desktop do.'),
  'sesBasla'    => k_c('Konuşmaya başla', 'Start speaking'),
  'sesDur'      => k_c('Durdur', 'Stop'),
  'sesDinliyor' => k_c('Dinleniyor. Ses cihazınızdan çıkmıyor.', 'Listening. No audio leaves your device.'),
  'sesIzin'     => k_c('Mikrofon izni verilmedi.', 'Microphone permission was not granted.'),
  'sesHata'     => k_c('Sesle yazma durdu.', 'Dictation stopped.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$betik = <<<JS
<script>
/* k/kutadgu.js "defer" ile yüklendiği için K nesnesi DOM hazır olunca vardır */
document.addEventListener('DOMContentLoaded', function(){
  var S = {$S};
  function \$(id){return document.getElementById(id);}
  var dg=\$('uyKur'), durum=\$('uyDurum');

  function tazele(){
    if (K.uygulamaMi()) { dg.disabled=true; durum.textContent=S.zatenApp; return; }
    if (K.kurulabilir()) { dg.disabled=false; durum.textContent=S.kurulabilir; }
    else { dg.disabled=true; durum.textContent=S.elle; }
  }
  window.addEventListener('kutadgu-kurulabilir', tazele);
  window.addEventListener('kutadgu-kuruldu', function(){ dg.disabled=true; durum.textContent=S.kuruldu; });
  setTimeout(tazele, 700);
  tazele();

  dg.addEventListener('click', function(){
    K.kur().then(function(s){
      if (s && s.outcome === 'accepted') durum.textContent=S.kuruldu;
      else durum.textContent=S.vazgecti;
      dg.disabled=true;
    }).catch(function(){ durum.textContent=S.elle; });
  });

  /* Sesle yazma denemesi */
  var sDg=\$('sesDg'), sEt=\$('sesEtiket'), sDu=\$('sesDurum'), sAl=\$('sesAlan');
  if (!K.sesDestek()) { sDu.textContent=S.sesYok; return; }
  sDg.disabled=false;
  var yz = K.sesYazici(sAl, {
    basladi: function(){ sDu.textContent=S.sesDinliyor; },
    hata: function(k){ sDu.textContent = (k==='not-allowed'||k==='service-not-allowed') ? S.sesIzin : S.sesHata; },
    bitti: function(){ sDg.setAttribute('aria-pressed','false'); sEt.textContent=S.sesBasla; }
  });
  sDg.addEventListener('click', function(){
    if (yz.calisiyor()) { yz.dur(); sDg.setAttribute('aria-pressed','false'); sEt.textContent=S.sesBasla; sDu.textContent=''; }
    else { yz.basla(); sDg.setAttribute('aria-pressed','true'); sEt.textContent=S.sesDur; }
  });
});
</script>
JS;
k_son($betik);
