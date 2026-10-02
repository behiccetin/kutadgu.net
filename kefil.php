<?php
/* =====================================================================
   KUTADGU - Yazarlık desteği / Authorship support
   ---------------------------------------------------------------------
   Doktora derecesi henüz olmayan bir araştırmacı, iki doktoralı kişinin
   adıyla sorumluluk üstlenmesiyle bir çalışmada yazar olarak yer
   alabilir. Bu sayfa, o iki kişiden birinin kendi kararını verdiği
   yerdir.

   Sayfa bir hesap istemez: destekleyen araştırmacıya e-postasıyla giden bağlantının içinde
   kendi anahtarı vardır. Karar bir kez verilir ve geri alınmaz; gerekçe
   her iki durumda da zorunludur ve çalışmanın sayfasında kalıcı olarak
   görünür. Bir kişinin adının bir başkasının çalışmasında sorumluluk
   taşıması, gerekçesi yazılmadan geçilecek bir şey değildir.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en  = k_en();
$kod = preg_replace('/[^a-f0-9]/', '', (string)($_GET['k'] ?? ''));

$ekBas = <<<CSS
<style>
/* Sayfaya kalan iki şey: künye satırı (etiket solda, değer sağda) ve
   özet alıntısı. Kart, seçim kutusu, form alanı, düğme, ileti ve uyarı
   kutusu kuralları dizgeden gelir. */
.kf-kart{margin:0 0 var(--b-4)}
/* Etiketin en az genişliği verilir ki art arda gelen satırlarda
   değerler aynı yerden başlasın; dar ekranda etiket kendi satırına
   iner ve hizalama kendiliğinden düşer. */
.kf-sat{display:flex;flex-wrap:wrap;gap:var(--b-1) var(--b-3);font-size:var(--y-3);
  line-height:var(--sh-genis);margin:0 0 var(--b-2)}
.kf-sat b{font-weight:600;min-width:132px;color:var(--metin-2);font-family:var(--ui);
  font-size:var(--y-2);letter-spacing:.04em;text-transform:uppercase}
.kf-ozet{font-size:var(--y-4);line-height:var(--sh-genis);color:var(--metin-2);margin:var(--b-3) 0 0;
  border-left:2px solid var(--cizgi);padding-left:var(--b-3)}
.kf-ne{font-size:var(--y-5);line-height:var(--sh-genis);margin:0 0 var(--b-4)}
.kf-sec{margin:0 0 var(--b-4)}
.kf-say{font-size:var(--y-2);color:var(--metin-2);margin:var(--b-2) 0 0}
.kf-dg{margin-top:var(--b-4)}
.kf-uyari{margin:var(--b-4) 0 0}
</style>
CSS;

k_bas([
    'olcu'   => 'okuma',
    'tur'      => 'belge',
    'baslik'   => k_c('Yazarlık desteği', 'Authorship support'),
    'yol'      => '/kefil.php',
    'aciklama' => k_c(
        'Doktora derecesi henüz olmayan bir araştırmacının bir çalışmada yazar olarak yer alabilmesi için istenen yazarlık desteği.',
        'The support a supporting researcher is asked for so that a researcher without a doctorate may appear as an author on a work.'
    ),
    'robots'   => 'noindex,nofollow',
    'ek_bas'   => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Sorumluluk', 'Responsibility') ?></span>
      <h1><?= k_c('Yazarlık desteği', 'Authorship support') ?></h1>
      <p><?= k_c(
        'Doktora şartı, niteliği korumak için konmuştu. Olduğu gibi bırakıldığında henüz derecesini almamış bir araştırmacının, kendi emeğiyle ürettiği bir çalışmada adının bulunmasını da engelliyordu; bu yüzden bir yol açılmıştı: iki doktoralı araştırmacı adıyla sorumluluk üstlenirse, o araştırmacı yazar olarak yer alabilirdi. Şartın kendisi kalktığı için bu yol da kapandı. Verilmiş kararlar gerekçesiyle birlikte kalıcı olarak yayımlanmayı sürdürür.',
        'The doctoral requirement was set in order to protect quality. Left as it stood, however, it also kept a researcher who has not yet taken that degree from appearing on work they themselves produced. So a way was opened: if two researchers holding doctorates take responsibility under their own names, that researcher may appear as an author. The decision below is yours, and it is published permanently together with your reasoning.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
    <div class="kf blg-ic">

      <?php /* DÜZEN KAPANDI — kurul kararı, 14 Ağustos 2026.
               Sayfa SİLİNMEDİ ve silinmemeli: daha önce gönderilmiş
               onay bağlantıları bu adrese geliyor ve bir bağlantının
               404 vermesi, kendisinden bir şey istenen kişiye hiçbir
               şey söylemez. Sayfa artık ne olduğunu söylüyor.

               Kayıt varsa yine açılır: kapanan şey YENİ kayıt
               üretilmesidir, verilmiş bir sözün geri alınması değil.
               Bu yüzden aşağıdaki uyarı formu kaldırmaz, önüne konur. */ ?>
      <?php if (!tg_destek_duzeni_acik()): ?>
      <div class="kutu kutu-kut">
        <p><b><?= k_c('Bu düzen kapatıldı.', 'This arrangement has been closed.') ?></b></p>
        <p><?= k_c(
          'Yayın kurulu yazarlık için doktora şartını kaldırdı; onunla birlikte yazarlık desteği düzeni de kapandı. Artık çalışma göndermek için bir dereceye ya da ' . tg_destek_ad() . 'ya gerek yok. Daha önce verilmiş destek kayıtları çalışmaların sayfasında durmayı sürdürür: o gün gerçekten olmuş şeylerdir ve bu sistem olmuş bir şeyi geri almaz.',
          'The editorial board removed the doctoral requirement for authorship, and with it the authorship support arrangement was closed. A degree or a supporting researcher is no longer needed in order to submit a work. Support records given earlier remain on the pages of those works: they are things that actually happened, and this system does not undo what happened.'
        ) ?></p>
        <p><a class="d d-ikinci d-kucuk d-git" href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkeleri', 'The editorial policies') ?></a></p>
      </div>
      <?php endif; ?>

      <p id="kfYuk" class="yukleniyor"><?= k_c('Bilgiler alınıyor...', 'Loading...') ?></p>
      <div id="kfHata" class="kutu kutu-kut gizli"></div>

      <div id="kfIc" class="gizli">
        <div class="kart kf-kart">
          <h2><?= k_c('Desteğiniz istenen çalışma', 'The work your support is asked for') ?></h2>
          <p class="kf-sat"><b><?= k_c('Başlık', 'Title') ?></b><span id="kfBaslik"></span></p>
          <p class="kf-sat"><b><?= k_c('Yazarlar', 'Authors') ?></b><span id="kfYazarlar"></span></p>
          <p class="kf-sat"><b><?= k_c('Desteklediğiniz araştırmacı', 'The researcher you support') ?></b><span id="kfKimin"></span></p>
          <p class="kf-sat"><b><?= k_c('Sıfatınız', 'Your capacity') ?></b><span id="kfIlgi"></span></p>
          <div id="kfOzetKutu" class="kf-ozet gizli"><span id="kfOzet"></span></div>
        </div>

        <div class="kart kf-kart" id="kfKarar">
          <h2><?= k_c('Kararınız', 'Your decision') ?></h2>
          <p class="kf-ne"><?= k_c(
            'Desteklemek, adı geçen araştırmacının bu çalışmaya <b>gerçekten katkı verdiğini</b> ve adının orada bulunmayı hak ettiğini bildiğinizi söylemektir. Çalışmanın bilimsel niteliğini desteklemiyorsunuz; onu hakemler değerlendirecek. Beyanınız katkının gerçekliğine ilişkindir.',
            'To support is to say that you know the named researcher <b>genuinely contributed</b> to this work and that their name deserves to be on it. You are not supporting the scientific quality of the work; reviewers will assess that. Your statement concerns the reality of the contribution.'
          ) ?></p>

          <div class="onay-dizi-2 kf-sec">
            <label class="onay-kart"><input type="radio" name="kfk" value="onayli"><span><?= k_c('Destekliyorum', 'I support this') ?></span></label>
            <label class="onay-kart"><input type="radio" name="kfk" value="ret"><span><?= k_c('Desteklemiyorum', 'I do not support this') ?></span></label>
          </div>

          <label for="kfGerekce"><?= k_c('Gerekçeniz', 'Your reasoning') ?></label>
          <textarea id="kfGerekce" placeholder="<?= k_esc(k_c('Araştırmacıyı nereden tanıyorsunuz, bu çalışmadaki katkısını nasıl biliyorsunuz?', 'How do you know the researcher, and how do you know their contribution to this work?')) ?>"></textarea>
          <p class="kf-say" id="kfSay"></p>

          <button type="button" class="d d-vurgu kf-dg" id="kfDg"><?= k_c('Kararımı gönder', 'Send my decision') ?></button>
          <p class="form-msj" id="kfMsj"></p>

          <div class="kutu kutu-kut kf-uyari"><?= k_c(
            'Karar bir kez verilir ve geri alınmaz. Adınız, sıfatınız ve gerekçeniz çalışmanın sayfasında kalıcı olarak görünür. Bu istekten haberiniz yoksa reddedin; reddiniz de gerekçesiyle kayda geçer ve çalışma yeni bir ' . tg_destek_ad() . ' bulunmadıkça yayına alınmaz.',
            'The decision is made once and is not reversed. Your name, your capacity and your reasoning appear permanently on the work\'s page. If you knew nothing of this request, decline; a refusal is recorded with its reasoning too, and the work is not published unless a new supporting researcher is found.'
          ) ?></div>
        </div>
      </div>

    </div>
    <?= k_belge_yan([], [
      ['tr' => 'Neden iki kişi?', 'en' => 'Why two people?',
       'ic'  => k_c(
         tg_destek_ad_bas(false, true) . 'dan biri çalışmanın doktoralı ortak yazarıdır: metni bilen kişidir. Diğeri çalışmayla hiçbir bağı olmayan bir doktoralıdır: dışarıdan bir gözdür. Böylece bir danışman kendi öğrencisini tek başına içeri alamaz.',
         'One supporting researcher is a co author of the work holding a doctorate: someone who knows the text. The other holds a doctorate but has no connection to the work: an outside eye. In this way a supervisor cannot admit their own student alone.'
       )],
      ['tr' => 'Bu araştırmacı ne yapabilir?', 'en' => 'What may this researcher do?',
       'ic'  => k_c(
         'Yazar olarak yer alır; adı, kurumu ve ORCID\'i çalışmada tam olarak görünür. Hakem raporu yazamaz, kurul oylamasında oy kullanamaz ve tek yazar olarak çalışma gönderemez. Doktora belgesini sunup doğrulattığı gün bu sınırların hepsi kalkar.',
         'They appear as an author; their name, institution and ORCID are shown in full on the work. They may not write a referee report, may not vote in a panel vote, and may not submit a work as sole author. On the day they submit and verify a doctoral credential, all of these limits fall away.'
       )],
      ['tr' => 'Destek kaydı silinir mi?', 'en' => 'Is the record ever removed?',
       'ic'  => k_c(
         'Hayır. Araştırmacı sonradan doktorasını alsa bile destek kaydı çalışmanın sayfasında kalır; çünkü o gün gerçekten olmuş bir şeydir ve kaydı geriye dönük düzeltmek kaydın kendisini bozar.',
         'No. Even if the researcher later takes their doctorate, the record stays on the work\'s page, because it is something that actually happened on that day, and amending a record after the fact damages the record itself.'
       )],
    ]) ?>
  </div>
</section>
<?php

$KOD = json_encode($kod);
$S = json_encode([
  'yok'      => k_c('Bu bağlantı geçersiz ya da artık kullanılmıyor.', 'This link is not valid, or is no longer in use.'),
  'baglanti' => k_c('Bağlantı kurulamadı, tekrar deneyin.', 'Could not connect, please try again.'),
  'karar'    => k_c('Destekleyip desteklemediğinizi işaretleyin.', 'Please mark whether you support this.'),
  'gerekce'  => k_c('Gerekçeniz en az %d karakter olmalıdır (şu an %c).', 'Your reasoning must be at least %d characters (currently %c).'),
  'gonder'   => k_c('Gönderiliyor...', 'Sending...'),
  'verildi'  => k_c('Bu yazarlık desteği için kararınızı daha önce vermişsiniz.', 'You have already given your decision for this support.'),
  'onaylandi'=> k_c('Desteklediniz', 'You supported'),
  'reddedildi'=> k_c('Reddettiniz', 'You declined'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$ILGI = json_encode([
  'ortak_yazar' => k_c('Bu çalışmanın doktoralı ortak yazarı', 'A co author of this work holding a doctorate'),
  'bagimsiz'    => k_c('Çalışmayla bağı olmayan doktoralı', 'A doctorate holder with no connection to the work'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$betik = <<<JS
<script>
(function(){
  var S={$S}, ILGI={$ILGI}, KOD={$KOD}, ASGARI=120;
  function \$(i){return document.getElementById(i);}
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function hata(t){ \$('kfYuk').classList.add('gizli');
    \$('kfHata').textContent=t; \$('kfHata').classList.remove('gizli'); }

  if(!KOD){ hata(S.yok); return; }

  fetch('/api/kefil-bilgi',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({k:KOD})})
    .then(function(r){return r.json();}).then(function(d){
      if(!d||!d.ok){ hata((d&&d.hata)||S.yok); return; }
      ASGARI=parseInt(d.asgari,10)||120;
      \$('kfBaslik').textContent=d.calisma.baslik||'';
      \$('kfYazarlar').textContent=d.calisma.yazarlar||'';
      \$('kfKimin').textContent=(d.yazar.ad||'')+(d.yazar.kurum?(' · '+d.yazar.kurum):'')
        +(d.yazar.orcid?(' · ORCID '+d.yazar.orcid):'');
      \$('kfIlgi').textContent=ILGI[d.kefil.ilgi]||d.kefil.ilgi||'';
      if(d.calisma.ozet){ \$('kfOzet').textContent=d.calisma.ozet;
        \$('kfOzetKutu').classList.remove('gizli'); }
      \$('kfYuk').classList.add('gizli');
      \$('kfIc').classList.remove('gizli');
      if(d.kefil.durum && d.kefil.durum!=='bekliyor'){
        var kt=(d.kefil.durum==='onayli')?S.onaylandi:S.reddedildi;
        \$('kfKarar').innerHTML='<h2>'+esc(kt)+'</h2><p class="kf-ne">'+esc(S.verildi)+'</p>'
          +(d.kefil.gerekce?('<div class="kf-ozet">'+esc(d.kefil.gerekce)+'</div>'):'');
        return;
      }
      sayGuncelle();
    }).catch(function(){ hata(S.baglanti); });

  function sayGuncelle(){
    var n=(\$('kfGerekce').value||'').trim().length;
    \$('kfSay').textContent=n+' / '+ASGARI;
  }
  \$('kfGerekce').addEventListener('input',sayGuncelle);

  \$('kfDg').addEventListener('click',function(){
    var m=\$('kfMsj');
    var k=document.querySelector('input[name=kfk]:checked');
    if(!k){ m.textContent=S.karar; m.className='form-msj err'; return; }
    var g=(\$('kfGerekce').value||'').trim();
    if(g.length<ASGARI){
      m.textContent=S.gerekce.replace('%d',ASGARI).replace('%c',g.length);
      m.className='form-msj err'; \$('kfGerekce').focus(); return;
    }
    \$('kfDg').disabled=true; m.textContent=S.gonder; m.className='form-msj';
    fetch('/api/kefil-onay',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({k:KOD,karar:k.value,gerekce:g})})
      .then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok){ m.textContent=d.mesaj||''; m.className='form-msj ok';
          \$('kfGerekce').readOnly=true;
          Array.prototype.forEach.call(document.querySelectorAll('input[name=kfk]'),function(r){r.disabled=true;});
        } else { m.textContent=(d&&d.hata)||S.baglanti; m.className='form-msj err'; \$('kfDg').disabled=false; }
      }).catch(function(){ m.textContent=S.baglanti; m.className='form-msj err'; \$('kfDg').disabled=false; });
  });
})();
</script>
JS;

k_son($betik);
