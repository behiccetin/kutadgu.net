<?php
/* =====================================================================
   KUTADGU - İletişim ve destek / Contact and support
   Sisteme katkı sunmak, bir hata bildirmek ya da yalnızca bir şey
   söylemek isteyen herkes buradan yazar. Yanıt, yazan kişinin kendi
   konuşma sayfasında görünür; konuşmalar herkese açık değildir.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en = k_en();
$anahtar = isset($_GET['k']) ? preg_replace('/[^a-f0-9]/', '', (string)$_GET['k']) : '';

$ekBas = <<<CSS
<meta name="robots" content="noindex, nofollow">
<style>
/* Kart, form, onay kutusu ve form iletisi dizgeden gelir. Burada
   yalnızca giriş paragrafı ile konuşma balonları durur. */
.il{min-width:0}
.il-giris{font-size:var(--y-5);border-left:3px solid var(--kut);padding-left:var(--b-5);margin:0 0 var(--b-6)}
.il-kart{margin-bottom:var(--b-4)}
/* Bal kabağı: ekran dışında duran, yalnızca kendiliğinden dolduran
   yazılımların göreceği bir alan. */
.il-bal{position:absolute!important;left:-9999px;width:1px;height:1px;overflow:hidden}
.il-not{font-size:var(--y-3);color:var(--metin-2);border-top:1px solid var(--cizgi);
  padding-top:var(--b-4);margin-top:var(--b-6)}
.il-tur{margin:var(--b-2) 0 var(--b-5)}
.il-tur b{display:block}
/* Gönderme düğmesi son alandan bir basamak ayrılır: etiket-alan
   ritmini düğmenin de sürdürmesi gerekir. */
.il-kart .d{margin-top:var(--b-4)}

/* Konuşma. İki taraf iki yana yaslanır: kimin yazdığı, adı okunmadan
   önce yerinden anlaşılsın. Balonun bir köşesi kırılır, o köşe
   konuşanın tarafını gösterir. */
.il-kon{display:grid;gap:var(--b-3);margin:var(--b-4) 0}
.il-b{max-width:88%;padding:var(--b-3) var(--b-4);border-radius:var(--r-3);font-size:var(--y-4)}
.il-b.ziyaretci{background:var(--yuzey-2);border:1px solid var(--cizgi);justify-self:start;
  border-bottom-left-radius:var(--b-1)}
.il-b.kurucu{background:var(--kut-zemin);border:1px solid var(--kut-cizgi);
  justify-self:end;border-bottom-right-radius:var(--b-1)}
.il-b .kim{display:block;font-size:var(--y-1);font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  margin-bottom:var(--b-2);color:var(--metin-2)}
.il-b.kurucu .kim{color:var(--kut)}
.il-b .zaman{display:block;font-size:var(--y-1);color:var(--metin-2);margin-top:var(--b-2)}
.il-b p{margin:0;white-space:pre-wrap}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('İletişim', 'Contact'),
    'yol'    => '/iletisim.php',
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <?php if ($anahtar !== ''): ?>
      <span class="bas-ust"><?= k_c('Konuşma', 'Conversation') ?></span>
      <h1><?= k_c('İletiniz ve yanıtı', 'Your message and the reply') ?></h1>
      <p><?= k_c(
        'Bu sayfa yalnızca sizde olan bir bağlantıyla açılır; konuşmanız hiçbir listede görünmez ve arama motorlarına kapalıdır. Yanıt geldiğinde burada belirir ve e-posta ile de haber verilir.',
        'This page opens only with a link that you alone hold; your conversation appears in no listing and is closed to search engines. When a reply arrives it appears here and you are notified by e mail as well.'
      ) ?></p>
      <?php else: ?>
      <span class="bas-ust"><?= k_c('İletişim', 'Contact') ?></span>
      <h1><?= k_c('Sistemle ilgili yazmak isterseniz', 'If you would like to write about the system') ?></h1>
      <p><?= k_c(
        'Kutadgu ücretsizdir ve öyle kalacaktır; bu sayfanın amacı bağış toplamak değildir. Yine de bir yayın sistemi zamanla barındırma, bakım ve geliştirme gideri doğurur. Bu yükü paylaşmak isteyen meslektaşlarımız olursa, bunu nazikçe konuşabileceğimiz yer burasıdır.',
        'Kutadgu is free of charge and will remain so; the purpose of this page is not to collect donations. Even so, a publishing system in time gives rise to costs of hosting, maintenance and development. If colleagues wish to share that burden, this is where we can discuss it courteously.'
      ) ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="il blg-ic">

    <?php if ($anahtar !== ''): ?>
    <div id="konYuk" class="yukleniyor"><?= k_c('Yükleniyor...', 'Loading...') ?></div>
    <div id="konHata" class="form-msj err gizli"></div>
    <div id="konIc" class="gizli">
      <div class="il-kon" id="konListe"></div>
      <div class="kart il-kart">
        <label for="ekMetin"><?= k_c('Eklemek istediğiniz bir şey var mı?', 'Is there anything you would like to add?') ?></label>
        <textarea id="ekMetin"></textarea>
        <button class="d d-vurgu" type="button" id="dgEk"><?= k_c('Gönder', 'Send') ?></button>
        <div class="form-msj" id="ekMsj"></div>
      </div>
    </div>

    <?php else: ?>
    <!-- ---------- FORM ---------- -->
    <p class="il-giris"><?= k_c(
      'Katkı hiçbir ayrıcalık getirmez: katkı sunan bir kişinin çalışması da aynı hakemlikten geçer, adı hiçbir yerde ayrıcalıklı biçimde anılmaz.',
      'A contribution brings no privilege: the work of anyone who contributes goes through the same review, and their name is mentioned nowhere in any privileged way.'
    ) ?></p>

    <div class="kart il-kart">
      <h2 style="margin-top:0"><?= k_c('Ne için yazıyorsunuz?', 'What are you writing about?') ?></h2>
      <div class="onay-dizi-2 il-tur">
        <label class="onay"><input type="radio" name="ilTur" value="genel" checked>
          <span><b><?= k_c('Genel', 'General') ?></b><small><?= k_c('Bir soru, bir görüş ya da söylemek istediğiniz bir şey.', 'A question, a view, or something you would like to say.') ?></small></span></label>
        <label class="onay"><input type="radio" name="ilTur" value="destek">
          <span><b><?= k_c('Maddi katkı', 'Material contribution') ?></b><small><?= k_c('Barındırma ve bakım giderlerine katkı sunmak istiyorsanız.', 'If you would like to contribute to hosting and maintenance costs.') ?></small></span></label>
        <label class="onay"><input type="radio" name="ilTur" value="hata">
          <span><b><?= k_c('Hata bildirimi', 'Reporting a fault') ?></b><small><?= k_c('Sistemde bozuk ya da yanlış çalışan bir yer gördüyseniz.', 'If you have seen something broken or behaving wrongly.') ?></small></span></label>
        <label class="onay"><input type="radio" name="ilTur" value="oneri">
          <span><b><?= k_c('Öneri', 'A suggestion') ?></b><small><?= k_c('Eklenmesini ya da değişmesini istediğiniz bir şey.', 'Something you would like added or changed.') ?></small></span></label>
        <label class="onay"><input type="radio" name="ilTur" value="isbirligi">
          <span><b><?= k_c('Kurumsal iş birliği', 'Institutional cooperation') ?></b><small><?= k_c('Bir kurum adına yazıyorsanız; devir koşulları Bildiri sayfasındadır.', 'If you write on behalf of an institution; the conditions of transfer are on the Declaration page.') ?></small></span></label>
      </div>

      <div class="alan-ikili">
        <div class="alan"><label for="ilAd"><?= k_c('Adınız', 'Your name') ?></label><input type="text" id="ilAd" autocomplete="name"></div>
        <div class="alan"><label for="ilEposta"><?= k_c('E-posta', 'E mail') ?></label><input type="email" id="ilEposta" autocomplete="email"></div>
      </div>
      <label for="ilKonu"><?= k_c('Konu (isteğe bağlı)', 'Subject (optional)') ?></label>
      <input type="text" id="ilKonu">
      <label for="ilMetin"><?= k_c('İletiniz', 'Your message') ?></label>
      <textarea id="ilMetin"></textarea>
      <div class="il-bal"><label for="website">Website</label><input type="text" id="website" tabindex="-1" autocomplete="off"></div>
      <button class="d d-vurgu" type="button" id="dgIleti"><?= k_c('İletiyi gönder', 'Send the message') ?></button>
      <div class="form-msj" id="ilMsj"></div>
    </div>

    <p class="il-not"><?= k_c(
      'İletiniz doğrudan yayın yönetimine ulaşır. Yanıt verildiğinde size özel bir konuşma sayfasında görünür; o sayfanın adresi yalnızca sizde olur ve arama motorlarına kapalıdır. E-posta adresiniz hiçbir sayfada gösterilmez, kimseyle paylaşılmaz ve hiçbir listeye eklenmez.',
      'Your message reaches the publication\'s management directly. When it is answered, the reply appears on a conversation page that belongs to you; its address is held only by you and it is closed to search engines. Your e mail address is shown on no page, shared with no one and added to no list.'
    ) ?></p>
    <?php endif; ?>

   </div>
   <?= k_belge_yan([], [
     /* SÖZ ARTIK ÖLÇÜLEBİLİR VE ÖLÇÜSÜ YAYIMLI.
        "Birkaç gün" tutulup tutulmadığı bilinemeyen bir sözdü ve bütün
        türlere aynı şeyi söylüyordu; oysa bir kurum destek için
        yazdığında birkaç gün beklemek, o desteğin kaybedilmesidir.
        Süre türe bağlandı (ayar.php, iletisim_hedef) ve gerçekleşen
        süreler istatistik sayfasında yayımlanıyor. Söz verip ölçüyü
        saklamak, söz vermemekten kötüdür. */
     ['tr' => 'Ne kadar sürer', 'en' => 'How long it takes',
      'ic' => k_cd('İletiler elle okunur; kimse adınıza otomatik cevap yazmaz. Hedefimiz kurumsal destek ve himaye başvurularında <b>%1 saat</b>, ötekilerde en çok <b>%2 saat</b> içinde yanıt vermektir.',
                   'Messages are read by a person; no one writes an automatic answer in our name. Our target is a reply within <b>%1 hours</b> for institutional support and patronage enquiries, and at most <b>%2 hours</b> for the rest.',
                   tg_ileti_hedef_saat('kurum'), tg_ileti_hedef_saat('genel'))
            . '<br><a href="' . k_esc(k_bag('/istatistik.php#ileti')) . '">'
            . k_c('Tutulup tutulmadığı ölçülür ve yayımlanır', 'Whether it is kept is measured and published') . '</a>'],
     /* SAKLAMA SÜRESİ SÖYLENİR. Bir kişinin adını, adresini ve
        yazdıklarını ne kadar tuttuğunuz, ona söylenmesi gereken bir
        şeydir; söylenmediğinde saklama süresi bir kural değil bir
        alışkanlık olur. */
     ['tr' => 'Yazdıklarınız ne kadar durur', 'en' => 'How long what you write is kept',
      'ic' => k_cd('Konuşma kapandıktan <b>%1 gün</b> sonra kaydı tümüyle silinir; adınız, adresiniz ve yazdıklarınız sistemde kalmaz. Yanıt bekleyen bir konuşma süreyle silinmez, çünkü o bekleyen bir iştir. Daha erken silinmesini isterseniz yazmanız yeter.',
                   'The record is deleted in full <b>%1 days</b> after the conversation is closed; your name, your address and what you wrote do not remain in the system. A conversation still awaiting a reply is not deleted on a timer, because it is work that is pending. If you would like it removed sooner, you need only ask.',
                   (int)(((array)tg_ayar('iletisim_saklama', []))['kapali'] ?? 180))],
     ['tr' => 'Bunlar için buradan yazmayın', 'en' => 'Do not write here for these',
      'ic' => k_c('Çalışma göndermek için başvuru formu, hakem olmak için hakemlik sayfası kullanılır; buradan gönderilen çalışmalar işleme alınmaz.',
                  'Use the application form to submit work and the peer review page to volunteer; works sent through this form are not processed.')
            . '<br><a href="' . k_esc(k_bag('/basvuru.php')) . '">' . k_c('Başvuru formu', 'Application form') . '</a>'
            . ' &middot; <a href="' . k_esc(k_bag('/hakemlik.php')) . '">' . k_c('Hakemlik', 'Peer review') . '</a>'],
   ]) ?>
  </div>
</section>

<?php
$S = json_encode([
  'bekle'    => k_c('Gönderiliyor...', 'Sending...'),
  'baglanti' => k_c('Bağlantı kurulamadı. Tekrar deneyin.', 'Could not connect. Please try again.'),
  'eAd'      => k_c('Adınızı yazın.', 'Please write your name.'),
  'ePosta'   => k_c('Geçerli bir e-posta adresi girin.', 'Enter a valid e mail address.'),
  'eMetin'   => k_c('İletinizi biraz daha açık yazar mısınız?', 'Please write your message a little more fully.'),
  'siz'      => k_c('Siz', 'You'),
  'kurucu'   => k_c('Kurucu', 'Founder'),
  'yok'      => k_c('Bu konuşma bulunamadı. Bağlantı hatalı olabilir.', 'This conversation was not found. The link may be incorrect.'),
  'gonderildi' => k_c('Gönderildi.', 'Sent.'),
  'bosEk'    => k_c('Önce bir şeyler yazın.', 'Write something first.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$anahtarJs = json_encode($anahtar);

$betik = <<<JS
<script>
(function(){
  var S = {$S};
  var ANAHTAR = {$anahtarJs};
  function \$(id){ return document.getElementById(id); }
  function goster(el,k){ if(el) el.classList.toggle('gizli', !k); }
  function msj(el,t,tur){ if(el){ el.textContent=t; el.className='form-msj'+(tur?(' '+tur):''); } }
  function api(yol, govde){
    var s = { headers:{'Accept':'application/json'}, credentials:'same-origin' };
    if (govde !== undefined) { s.method='POST'; s.headers['Content-Type']='application/json'; s.body=JSON.stringify(govde); }
    return fetch('/api'+yol, s).then(function(r){ return r.json(); });
  }
  function zaman(t){
    if(!t) return '';
    var d = new Date(t); if (isNaN(d.getTime())) return '';
    var i = function(n){ return (n<10?'0':'')+n; };
    return i(d.getDate())+'.'+i(d.getMonth()+1)+'.'+d.getFullYear()+' '+i(d.getHours())+':'+i(d.getMinutes());
  }

  /* ---- Konuşma sayfası ---- */
  if (ANAHTAR) {
    function ciz(k){
      var kutu = \$('konListe'); kutu.innerHTML='';
      (k.mesajlar||[]).forEach(function(m){
        var d = document.createElement('div');
        d.className = 'il-b ' + (m.kim === 'kurucu' ? 'kurucu' : 'ziyaretci');
        var kim = document.createElement('span'); kim.className='kim';
        kim.textContent = (m.kim === 'kurucu' ? S.kurucu : S.siz);
        var p = document.createElement('p'); p.textContent = m.metin || '';
        var z = document.createElement('span'); z.className='zaman'; z.textContent = zaman(m.tarih);
        d.appendChild(kim); d.appendChild(p); d.appendChild(z);
        kutu.appendChild(d);
      });
    }
    function yukle(){
      api('/iletisim-konusma?k='+encodeURIComponent(ANAHTAR)).then(function(d){
        goster(\$('konYuk'), false);
        if (d && d.ok) { ciz(d.konusma); goster(\$('konIc'), true); }
        else { \$('konHata').textContent = (d && d.hata) || S.yok; goster(\$('konHata'), true); }
      }).catch(function(){
        goster(\$('konYuk'), false); \$('konHata').textContent=S.baglanti; goster(\$('konHata'), true);
      });
    }
    yukle();
    /* Yanıt gelmiş mi diye ara sıra bakar */
    setInterval(yukle, 45000);

    \$('dgEk').addEventListener('click', function(){
      var m = \$('ekMetin').value.trim();
      if (m.length < 2) return msj(\$('ekMsj'), S.bosEk, 'err');
      msj(\$('ekMsj'), S.bekle);
      api('/iletisim-ekle', {k:ANAHTAR, metin:m}).then(function(d){
        if (d && d.ok) { msj(\$('ekMsj'), S.gonderildi, 'ok'); \$('ekMetin').value=''; yukle(); }
        else msj(\$('ekMsj'), (d && d.hata) || S.baglanti, 'err');
      }).catch(function(){ msj(\$('ekMsj'), S.baglanti, 'err'); });
    });
    return;
  }

  /* ---- Form ---- */
  \$('dgIleti').addEventListener('click', function(){
    var ad = \$('ilAd').value.trim(), ep = \$('ilEposta').value.trim(), mt = \$('ilMetin').value.trim();
    if (!ad) return msj(\$('ilMsj'), S.eAd, 'err');
    if (!/.+@.+\\..+/.test(ep)) return msj(\$('ilMsj'), S.ePosta, 'err');
    if (mt.length < 20) return msj(\$('ilMsj'), S.eMetin, 'err');
    var tur = (document.querySelector('input[name=ilTur]:checked')||{}).value || 'genel';
    \$('dgIleti').disabled = true;
    msj(\$('ilMsj'), S.bekle);
    api('/iletisim', {ad:ad, eposta:ep, konu:\$('ilKonu').value.trim(), metin:mt, tur:tur,
                      website:\$('website').value}).then(function(d){
      if (d && d.ok) {
        msj(\$('ilMsj'), d.mesaj || S.gonderildi, 'ok');
        if (d.anahtar) setTimeout(function(){ location.href = '/iletisim.php?k=' + d.anahtar; }, 1400);
      } else { msj(\$('ilMsj'), (d && d.hata) || S.baglanti, 'err'); \$('dgIleti').disabled = false; }
    }).catch(function(){ msj(\$('ilMsj'), S.baglanti, 'err'); \$('dgIleti').disabled = false; });
  });
})();
</script>
JS;
k_son($betik);
