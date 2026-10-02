<?php
/* =====================================================================
   KUTADGU - Hakemlik daveti / Reviewer invitation
   Davet edilen kişi burada sistemi tanır, kabul ya da ret eder.
   Hakemlik gönüllüdür; reddetmek en doğal haktır ve hiçbir kaydı
   olumsuz etkilemez.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en = k_en();

$ekBas = <<<CSS
<meta name="robots" content="noindex, nofollow">
<style>
/* Bu sayfada yalnızca davetin kendi metnine ait üç şey kalır:
   çalışmanın künyesi, özeti ve işleyişi anlatan noktalı liste. Kart,
   düğme, form ve ileti kuralları dizgeden gelir. */
/* Kartın içindeki düz paragraflar da okuma genişliğinde kalır;
   dizgenin bu sınırı yalnızca kartın dışındaki paragraflara uygular. */
.dv-kart{margin-bottom:var(--b-4)}
.dv-kart > p{max-width:74ch}
.dv-baslik{font-size:var(--y-6);font-weight:600;line-height:var(--sh-orta);margin:0 0 var(--b-2)}
.dv-yazar{color:var(--metin-2);font-size:var(--y-3);margin:0 0 var(--b-3)}
.dv-ozet{font-size:var(--y-4);line-height:var(--sh-genis);color:var(--metin)}
/* Noktalar liste iminin yerine geçer: madde sayısı azdır ve her madde
   bir cümleden uzundur, o yüzden imle metnin arası açık tutulur. */
.dv-nokta{list-style:none;padding:0;margin:var(--b-3) 0 0;display:grid;gap:var(--b-3)}
.dv-nokta li{position:relative;padding-left:var(--b-5);font-size:var(--y-3);
  line-height:var(--sh-genis);color:var(--metin-2)}
.dv-nokta li::before{content:"";position:absolute;left:5px;top:.62em;width:7px;height:7px;
  border-radius:50%;background:var(--kut)}
.dv-neden{margin-top:var(--b-4)}
.dv-dg{margin-top:var(--b-5)}
</style>
CSS;

k_bas([
    'olcu'   => 'okuma',
    'baslik' => k_c('Hakemlik daveti', 'Invitation to review'),
    'ek_bas' => $ekBas,
]);

$S = json_encode([
    'yok'      => k_c('Bu davet bulunamadı. Bağlantı hatalı olabilir ya da davet geri alınmış olabilir.', 'This invitation was not found. The link may be incorrect or may have been withdrawn.'),
    'baglanti' => k_c('Bağlantı kurulamadı. Kısa bir süre sonra tekrar deneyin.', 'Could not connect. Please try again shortly.'),
    'gonder'   => k_c('Gönderiliyor...', 'Sending...'),
    'zaten'    => k_c('Bu davet daha önce yanıtlanmış.', 'This invitation has already been answered.'),
    'kabulOk'  => k_c('Teşekkür ederiz. Değerlendirme sayfanız hazır.', 'Thank you. Your assessment page is ready.'),
    'retOk'    => k_c('Bildirdiğiniz için teşekkür ederiz. Sizi rahatsız ettiysek özür dileriz; bu çalışmayla ilgili size başka bir ileti gönderilmeyecektir.', 'Thank you for letting us know. We are sorry to have troubled you; no further message will be sent about this work.'),
    /* Süresi dolmuş davet: reddedilmiş sayılmaz. Kimsenin kaydına
       olumsuz bir şey yazılmaz; yalnızca davet düşmüştür. */
    'sureOk'   => k_c('Bu davetin süresi doldu. Davetler, bir çalışma belirsiz süre beklemesin diye sınırlı süre açık kalır; bu hiç kimsenin kaydına olumsuz olarak geçmez. Yine de değerlendirmek isterseniz editörlere yazmanız daveti yenilemek için yeterlidir.', 'This invitation has lapsed. Invitations stay open for a limited time so that a work does not wait indefinitely; this is not recorded against anyone. If you would still like to assess this work, writing to the editors is enough for the invitation to be renewed.'),
    'ac'       => k_c('Değerlendirme sayfasını aç', 'Open the assessment page'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<section class="bolum">
  <div class="kap blg">
    <div class="blg-ic">

    <span class="bas-ust"><?= k_c('Hakemlik daveti', 'Invitation to review') ?></span>
    <h1><?= k_c('Bir çalışmayı değerlendirmeniz rica ediliyor', 'You are asked to assess a work') ?></h1>

    <div id="yukleniyor" class="yukleniyor"><?= k_c('Davet aranıyor...', 'Looking up the invitation...') ?></div>
    <div id="hata" class="gizli form-msj err"></div>

    <div id="icerik" class="gizli">
      <div class="kart dv-kart">
        <h2><?= k_c('Değerlendirilmesi istenen çalışma', 'The work you are asked to assess') ?></h2>
        <p class="dv-baslik" id="dBaslik"></p>
        <p class="dv-yazar" id="dYazar"></p>
        <div class="dv-ozet" id="dOzet"></div>
      </div>

      <div class="kart dv-kart">
        <h2><?= k_c('Bu sistemde hakemlik nasıl işler', 'How review works here') ?></h2>
        <p class="metin-sonuk"><?= k_c(
          'Kutadgu, akademik çalışmaların ücretsiz, engelsiz ve gecikmesiz yayımlandığı bağımsız bir yayın sistemidir. Ne yazardan ne okurdan ücret alınır, reklam yoktur.',
          'Kutadgu is an independent publishing system in which academic work is published free of charge, without barriers and without delay. Neither authors nor readers are charged, and there is no advertising.'
        ) ?></p>
        <ul class="dv-nokta">
          <li><?= k_c(
            '<b>Kör hakemlik uygulanmaz.</b> Adınız, kararınız ve raporunuz çalışmayla birlikte açıkça yayımlanır. Amaç, hakemin emeğini görünür kılmak ve değerlendirmenin de denetlenebilir bir belgeye dönüşmesini sağlamaktır.',
            '<b>Review is not blind.</b> Your name, your decision and your report are published openly together with the work. The aim is to make the reviewer\'s labour visible and to turn the assessment itself into a checkable record.'
          ) ?></li>
          <li><?= k_c(
            '<b>Hangi sıfatla değerlendirdiğinizi siz seçersiniz:</b> konu, yöntem, veri ve istatistik ya da dil. Alanınızın çalışmanın alanıyla birebir örtüşmesi gerekmez; farklı bir alandan gelen bakış çoğu zaman en değerli katkıdır.',
            '<b>You choose the capacity in which you assess:</b> subject, method, data and statistics, or language. Your field does not have to match the field of the work; a view from another discipline is often the most valuable contribution.'
          ) ?></li>
          <li><?= k_c(
            '<b>Raporunuz gönderildikten sonra değiştirilemez.</b> Kararınızı ve ölçüt değerlendirmenizi gönderdiğiniz an, bu çalışmayla ilgili süreciniz tamamlanır.',
            '<b>Your report cannot be changed once sent.</b> The moment you submit your decision and your criteria assessment, your part in this work is complete.'
          ) ?></li>
          <li><?= k_c(
            '<b>Hiçbir yükümlülüğünüz yoktur.</b> Hakemlik tümüyle gönüllüdür. Reddetmeniz son derece olağandır, hiçbir kaydı olumsuz etkilemez ve bir daha bu çalışma için rahatsız edilmezsiniz.',
            '<b>You are under no obligation.</b> Reviewing is entirely voluntary. Declining is perfectly ordinary, affects no record adversely, and you will not be troubled about this work again.'
          ) ?></li>
        </ul>
      </div>

      <div class="kart dv-kart" id="karar">
        <h2><?= k_c('Yanıtınız', 'Your answer') ?></h2>
        <p class="metin-sonuk"><?= k_c(
          'Kabul ederseniz değerlendirme sayfanız hemen açılır. Uygun değilseniz aşağıdan bildirmeniz yeterlidir; isterseniz kısa bir gerekçe yazabilirsiniz, zorunlu değildir.',
          'If you accept, your assessment page opens straight away. If it is not suitable for you, simply say so below; you may add a short reason if you wish, but it is not required.'
        ) ?></p>
        <div class="dv-neden">
          <label for="neden"><?= k_c('Gerekçe (isteğe bağlı, yalnızca yazara iletilir)', 'Reason (optional, shared only with the author)') ?></label>
          <textarea id="neden" placeholder="<?= k_c('Örn. Bu dönem yoğunluğum nedeniyle vakit ayıramıyorum.', 'e.g. I am unable to find the time this term.') ?>"></textarea>
        </div>
        <div class="d-kume dv-dg">
          <button id="kabul" type="button" class="d d-vurgu"><?= k_c('Kabul ediyorum, değerlendireyim', 'I accept, let me assess it') ?></button>
          <button id="ret" type="button" class="d d-ikinci"><?= k_c('Bu kez katılamıyorum', 'I cannot take part this time') ?></button>
        </div>
        <div id="mesaj" class="form-msj"></div>
      </div>

      <div class="kutu kutu-kut gizli" id="son"></div>
    </div>

    </div>
  </div>
</section>
<?php
$betik = <<<JS
<script>
(function(){
  var S = {$S};
  function \$(id){return document.getElementById(id);}
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  var d = new URLSearchParams(location.search).get('d') || '';
  var link = '';

  function goster(el){ el.classList.remove('gizli'); }
  function bitir(html){ \$('karar').classList.add('gizli'); \$('son').innerHTML=html; goster(\$('son')); }

  if(!d){ \$('yukleniyor').classList.add('gizli'); \$('hata').textContent=S.yok; goster(\$('hata')); return; }

  fetch('/api/hakem-davet?d='+encodeURIComponent(d))
    .then(function(r){return r.json();})
    .then(function(x){
      \$('yukleniyor').classList.add('gizli');
      if(!x||!x.ok){ \$('hata').textContent=(x&&x.hata)||S.yok; goster(\$('hata')); return; }
      link = x.link || '';
      \$('dBaslik').textContent = x.baslik || '';
      \$('dYazar').textContent = x.yazar || '';
      \$('dOzet').textContent = x.ozet || '';
      goster(\$('icerik'));
      if (x.durum && x.durum !== 'bekliyor') {
        if (x.durum === 'kabul' && link) bitir(esc(S.kabulOk)+'<br><br><a class="d d-vurgu" href="'+esc(link)+'">'+esc(S.ac)+'</a>');
        else if (x.durum === 'suresi_doldu') bitir(esc(S.sureOk));
        else bitir(esc(x.durum === 'kabul' ? S.kabulOk : S.retOk));
      }
    })
    .catch(function(){ \$('yukleniyor').classList.add('gizli'); \$('hata').textContent=S.baglanti; goster(\$('hata')); });

  function yanitla(yanit){
    var m=\$('mesaj');
    \$('kabul').disabled=true; \$('ret').disabled=true;
    m.textContent=S.gonder; m.className='form-msj';
    fetch('/api/hakem-davet-yanit',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({d:d,yanit:yanit,neden:\$('neden').value.trim()})})
      .then(function(r){return r.json();}).then(function(x){
        if(x&&x.ok){
          if(yanit==='kabul'){
            var l = (x.link||link||'');
            bitir(esc(S.kabulOk)+(l?('<br><br><a class="d d-vurgu" href="'+esc(l)+'">'+esc(S.ac)+'</a>'):''));
          } else { bitir(esc(S.retOk)); }
        } else {
          m.textContent=(x&&x.hata)||S.baglanti; m.className='form-msj err';
          \$('kabul').disabled=false; \$('ret').disabled=false;
        }
      }).catch(function(){
        m.textContent=S.baglanti; m.className='form-msj err';
        \$('kabul').disabled=false; \$('ret').disabled=false;
      });
  }
  \$('kabul').addEventListener('click',function(){yanitla('kabul');});
  \$('ret').addEventListener('click',function(){yanitla('ret');});
})();
</script>
JS;

k_son($betik);
