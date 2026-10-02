<?php
/* =====================================================================
   KUTADGU - Bilim alanı seçim kutusu / Field picker
   ---------------------------------------------------------------------
   k/alanlar.php'deki sınıflandırmayı ekrana getirir. Üç yerde aynı
   kutu kullanılır: hesap kaydı, profil ve çalışma gönderimi. Aynı
   kutunun kullanılması yalnızca kolaylık değil, tutarlılıktır: yazarın
   çalışmasına verdiği kod ile hakemin kendine verdiği kod aynı listeden
   gelmezse eşleştirme diye bir şey olmaz.

   Kutu üç parçadan oluşur:
     1. Seçilenler   - üstte, tek tıkla çıkarılabilir
     2. Liste        - ana alan > alt alan > dal, aranabilir
     3. Öneri kutusu - listede olmayan bir dal önerilir; öneren ve
                       tarih kayda geçer, editör onayına düşer

   Betik yalnızca bir kez basılır; sayfada birden çok kutu olabilir.
   Betiksiz de çalışır: onay kutuları düz form alanlarıdır.
   ===================================================================== */

require_once __DIR__ . '/alanlar.php';

if (!function_exists('as_kutu')) {

    /* Kutunun biçemi. Sayfa başına bir kez basılır. */
    function as_stil(): string {
        static $basildi = false;
        if ($basildi) return '';
        $basildi = true;
        return <<<'CSS'
<style>
/* Onay kutusu, rozet, kutu, düğme ve form iletisi dizgeden gelir.
   Burada yalnızca üç basamaklı listenin kendi düzeni durur. */
.alsec{display:grid;gap:var(--b-3)}
.alsec-ust{min-height:2px}
/* Seçilen alan bir rozettir; içindeki çıkarma imi ise dokunulacak bir
   hedeftir, bu yüzden rozeti kendi yüksekliğine çeker. */
.alsec-etiket{min-height:var(--hedef);padding-right:0}
.alsec-etiket code{font-family:var(--mono);font-size:var(--y-1)}
.alsec-etiket button{border:0;background:transparent;color:var(--metin-2);cursor:pointer;
  width:var(--hedef);min-width:var(--hedef);min-height:var(--hedef);
  padding:0;font-size:var(--y-5);line-height:1;border-radius:var(--r-tam)}
.alsec-etiket button:hover{color:var(--kirmizi)}
.alsec-yok{font-size:var(--y-3);color:var(--metin-2)}
.alsec-liste{border:1px solid var(--cizgi);border-radius:var(--r-3);background:var(--yuzey);
  max-height:340px;overflow-y:auto;overscroll-behavior:contain}
.alsec-ana{border-top:1px solid var(--cizgi)}
.alsec-ana:first-child{border-top:0}
.alsec-ana > summary{cursor:pointer;list-style:none;min-height:var(--hedef);
  padding:var(--b-3) var(--b-4);font-size:var(--y-3);font-weight:700;
  color:var(--metin);display:flex;align-items:center;justify-content:space-between;gap:var(--b-3)}
.alsec-ana > summary::-webkit-details-marker{display:none}
.alsec-ana > summary::after{content:"+";font-family:var(--mono);color:var(--kut);font-weight:700}
.alsec-ana[open] > summary::after{content:"\2212"}
.alsec-ana > summary:hover{background:var(--yuzey-2)}
/* Kapalı bir bölmenin içi yalnızca boyanmaz, düzenden de çıkarılır.
   Aksi hâlde klavyeyle gezinen kişi ekranda olmayan yüzlerce onay
   kutusuna düşüyor ve dokunma hedefi ölçümü de onları sayıyordu. */
.alsec-ana:not([open]) .alsec-alt{display:none}
.alsec-alt{padding:0 0 var(--b-2)}
.alsec-bas{display:flex;align-items:baseline;gap:var(--b-2);padding:var(--b-2) var(--b-4) var(--b-1);
  font-size:var(--y-1);font-weight:700;letter-spacing:.03em;color:var(--metin-2);text-transform:uppercase}
.alsec-bas code{font-family:var(--mono);color:var(--kut);text-transform:none}
/* Dal satırı bir onay kutusudur; burada yalnızca listedeki girintisi
   verilir. Alt alanın kendisi bir basamak solda durur. */
.alsec-sat{padding:var(--b-1) var(--b-4) var(--b-1) var(--b-6);font-size:var(--y-3)}
.alsec-sat:hover{background:var(--yuzey-2)}
.alsec-sat code{font-family:var(--mono);font-size:var(--y-1);color:var(--metin-2);margin-left:var(--b-2)}
.alsec-sat.alsec-genis{padding-left:var(--b-4);font-weight:600}
.alsec-ortak{margin-left:var(--b-2)}
.alsec-say{font-size:var(--y-2);color:var(--metin-2);margin:0}
/* Metin gibi görünen bir düğme de dokunulacak bir düğmedir. Yüksekliği
   22 pikseldi; dolgu ile --hedef değerine çıkarıldı, görünüşü değişmedi. */
.alsec-oner-ac{background:none;border:0;color:var(--kut);font-size:var(--y-3);cursor:pointer;
  text-align:left;padding:var(--b-2) 0;min-height:var(--hedef);
  text-decoration:underline;text-underline-offset:3px}
/* Öneri kutusu listenin bir parçası değil, ona eklenen bir istektir;
   kesik çizgili çerçeve bunu söyler. */
.alsec-oner{border-style:dashed;display:grid;gap:var(--b-2)}
.alsec-oner p{margin:0}
.alsec-gizli{display:none}
</style>
CSS;
    }

    /* Kutunun kendisi.
       $ad     : onay kutularının name değeri (form alanı adı)
       $secili : şu an seçili kodlar
       $sec    : ['coklu'=>bool, 'oneri'=>bool, 'ustler'=>bool, 'ensok'=>int] */
    function as_kutu(string $ad, array $secili = [], array $sec = []): string {
        $en     = function_exists('k_en') ? k_en() : false;
        $coklu  = (bool)($sec['coklu'] ?? true);
        $oneri  = (bool)($sec['oneri'] ?? true);
        $ustler = (bool)($sec['ustler'] ?? true);   /* alt alanın kendisi de seçilebilsin mi */
        $ensok  = (int)($sec['ensok'] ?? 8);
        $kimlik = preg_replace('/[^A-Za-z0-9_]/', '', $ad) ?: 'alan';

        $secili = al_kodlar($secili);
        $secim  = al_secim($en);

        /* Ana alanlara göre kümele */
        $anaKume = [];
        foreach ($secim as $ak => $av) {
            $ana = explode('.', $ak)[0];
            $anaKume[$ana][$ak] = $av;
        }

        $es = function ($s) { return function_exists('k_esc') ? k_esc((string)$s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
        /* İki dillik kapanış, çeviri katmanına bağlandı: üçüncü dilde
           sessizce Türkçeye düşüyordu ("Bütün alt dallar"). */
        $c  = function ($tr, $enM) use ($en) { return tg_c($tr, $enM, $en); };

        ob_start();
        echo as_stil();
        ?>
<div class="alsec" data-alsec="<?= $es($kimlik) ?>" data-ad="<?= $es($ad) ?>" data-ensok="<?= $ensok ?>" data-coklu="<?= $coklu ? '1' : '0' ?>">
  <div class="satir alsec-ust" data-alsec-ust>
    <span class="alsec-yok"><?= $es($c('Henüz alan seçilmedi.', 'No field selected yet.')) ?></span>
  </div>

  <?php /* aria-label var çünkü görünür bir etiket yok: kutunun içindeki
           yer tutucu yazı yazılmaya başlandığı anda kaybolur ve alan
           adsız kalır. Yer tutucu bir etiket değildir. Görünür bir
           etiket eklemek kutunun üstüne ikinci bir satır koyardı;
           arama kutusunun ne olduğu zaten çevresinden okunuyor. */ ?>
  <input type="search" class="alsec-ara" data-alsec-ara autocomplete="off"
         aria-label="<?= $es($c('Bilim alanı arayın', 'Search a field of science')) ?>"
         placeholder="<?= $es($c('Alan arayın: ekonometri, epidemiyoloji, 5.2 ...', 'Search a field: econometrics, epidemiology, 5.2 ...')) ?>">

  <div class="alsec-liste" data-alsec-liste>
    <?php foreach (al_ana() as $anaK => $anaV): ?>
      <?php if (empty($anaKume[$anaK])) continue; ?>
      <details class="alsec-ana" data-ana="<?= $es($anaK) ?>">
        <summary><span><?= $es(k_t($anaV)) ?></span></summary>
        <div class="alsec-alt">
          <?php foreach ($anaKume[$anaK] as $altK => $altV): ?>
            <div class="alsec-bas"><span><?= $es($altV['ad']) ?></span><code><?= $es($altK) ?></code></div>
            <?php if ($ustler): ?>
            <label class="onay alsec-sat alsec-genis">
              <input type="checkbox" name="<?= $es($ad) ?>" value="<?= $es($altK) ?>"<?= in_array($altK, $secili, true) ? ' checked' : '' ?>>
              <span><?= $es($c('Bütün alt dallar', 'The whole subfield')) ?><code><?= $es($altK) ?></code></span>
            </label>
            <?php endif; ?>
            <?php foreach ($altV['dallar'] as $dalK => $dalAd): ?>
            <label class="onay alsec-sat">
              <input type="checkbox" name="<?= $es($ad) ?>" value="<?= $es($dalK) ?>"<?= in_array($dalK, $secili, true) ? ' checked' : '' ?>>
              <span><?= $es($dalAd) ?><code><?= $es($dalK) ?></code><?php
                if (!empty($altV['ortak'][$dalK])) echo '<span class="rz rz-kut alsec-ortak">' . $es($c('alanlar arası', 'interdisciplinary')) . '</span>';
              ?></span>
            </label>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </div>

  <p class="alsec-say" data-alsec-say></p>

  <?php if ($oneri): ?>
  <button type="button" class="alsec-oner-ac" data-alsec-oner-ac><?= $es($c('Aradığınız dal listede yok mu? Öneri gönderin.', 'Is the branch you need missing? Propose it.')) ?></button>
  <div class="kutu kutu-kut alsec-oner alsec-gizli" data-alsec-oner>
    <p><?= $es($c(
      'Listenin üçüncü basamağı bu sisteme aittir ve büyür. Önerdiğiniz dal, adınız ve tarihle birlikte kayda geçer; editör onayladığında listeye girer ve size bildirilir. Onaylanmayan öneri de kayıtta kalır, silinmez.',
      'The third level of this list belongs to this system and grows. The branch you propose is recorded with your name and the date; when an editor approves it, it enters the list and you are told. A proposal that is not approved also stays on the record; it is not deleted.'
    )) ?></p>
    <div>
      <label for="<?= $es($kimlik) ?>-oust"><?= $es($c('Hangi alt alanın altında?', 'Under which subfield?')) ?></label>
      <select id="<?= $es($kimlik) ?>-oust" data-alsec-oust>
        <?php foreach ($secim as $ak => $av): ?>
        <option value="<?= $es($ak) ?>"><?= $es($av['ana'] . ' > ' . $av['ad'] . ' (' . $ak . ')') ?></option>
        <?php endforeach; ?>
        <option value="9"><?= $es($c('Hiçbirine sığmıyor: yeni bir ana alan', 'It fits none of them: a new main field')) ?></option>
      </select>
    </div>
    <div>
      <label for="<?= $es($kimlik) ?>-otr"><?= $es($c('Dalın Türkçe adı', 'Name in Turkish')) ?></label>
      <input type="text" id="<?= $es($kimlik) ?>-otr" data-alsec-otr maxlength="90">
    </div>
    <div>
      <label for="<?= $es($kimlik) ?>-oen"><?= $es($c('İngilizce adı', 'Name in English')) ?></label>
      <input type="text" id="<?= $es($kimlik) ?>-oen" data-alsec-oen maxlength="90">
    </div>
    <div>
      <label for="<?= $es($kimlik) ?>-oger"><?= $es($c('Neden ayrı bir dal olmalı? (en az 60 karakter)', 'Why should this be a separate branch? (at least 60 characters)')) ?></label>
      <textarea id="<?= $es($kimlik) ?>-oger" data-alsec-oger rows="3" maxlength="900"></textarea>
    </div>
    <div>
      <label for="<?= $es($kimlik) ?>-oad"><?= $es($c('Adınız', 'Your name')) ?></label>
      <input type="text" id="<?= $es($kimlik) ?>-oad" data-alsec-oad maxlength="120">
    </div>
    <button type="button" class="d d-ikinci d-kucuk" data-alsec-oner-gonder><?= $es($c('Öneriyi gönder', 'Send the proposal')) ?></button>
    <p class="form-msj" data-alsec-oner-msj></p>
  </div>
  <?php endif; ?>
</div>
        <?php
        return (string)ob_get_clean() . as_betik();
    }

    /* Ortak betik; sayfa başına bir kez. */
    function as_betik(): string {
        static $basildi = false;
        if ($basildi) return '';
        $basildi = true;
        $en = function_exists('k_en') ? k_en() : false;
        $S = [
            'yok'    => k_c('Henüz alan seçilmedi.', 'No field selected yet.'),
            'say'    => k_c('%s alan seçildi, en çok %d.', '%s selected, at most %d.'),
            'sinir'  => k_c('En çok %d alan seçebilirsiniz.', 'You may select at most %d fields.'),
            'bekle'  => k_c('Gönderiliyor...', 'Sending...'),
            'eksik'  => k_c('Adı, gerekçeyi ve adınızı doldurun.', 'Please fill in the name, the reasoning and your name.'),
            'kisa'   => k_c('Gerekçe en az 60 karakter olmalıdır.', 'The reasoning must be at least 60 characters.'),
            'baglan' => k_c('Bağlantı kurulamadı.', 'Could not connect.'),
            'ara'    => k_c('eşleşme yok', 'no match'),
        ];
        $js = json_encode($S, JSON_UNESCAPED_UNICODE);
        return <<<HTML
<script>
/* Alan seçim kutusu. Betik olmadan da onay kutuları çalışır; bu betik
   yalnızca seçilenleri üstte gösterir, arar ve sayar. */
(function(){
  var S = {$js};
  function kur(kok){
    if(kok.dataset.kuruldu) return; kok.dataset.kuruldu='1';
    var ust=kok.querySelector('[data-alsec-ust]');
    var ara=kok.querySelector('[data-alsec-ara]');
    var say=kok.querySelector('[data-alsec-say]');
    var ensok=parseInt(kok.dataset.ensok||'8',10);
    var coklu=kok.dataset.coklu!=='0';
    function kutular(){ return Array.prototype.slice.call(kok.querySelectorAll('[data-alsec-liste] input[type=checkbox]')); }
    function secili(){ return kutular().filter(function(c){return c.checked;}); }

    function ciz(){
      var s=secili();
      ust.innerHTML='';
      if(!s.length){ var y=document.createElement('span'); y.className='alsec-yok'; y.textContent=S.yok; ust.appendChild(y); }
      s.forEach(function(c){
        var ad=c.parentElement.querySelector('span').cloneNode(true);
        var kodE=ad.querySelector('code'); var kod=kodE?kodE.textContent:'';
        if(kodE) kodE.remove();
        var ortak=ad.querySelector('.alsec-ortak'); if(ortak) ortak.remove();
        var e=document.createElement('span'); e.className='rz rz-kut alsec-etiket';
        var t=document.createElement('span'); t.textContent=ad.textContent.trim(); e.appendChild(t);
        if(kod){ var k=document.createElement('code'); k.textContent=kod; e.appendChild(k); }
        var b=document.createElement('button'); b.type='button'; b.textContent='×';
        b.setAttribute('aria-label','çıkar');
        b.addEventListener('click',function(){ c.checked=false; ciz(); });
        e.appendChild(b); ust.appendChild(e);
      });
      if(say) say.textContent = s.length ? S.say.replace('%s',s.length).replace('%d',ensok) : '';
    }

    kok.addEventListener('change',function(ev){
      var c=ev.target; if(!c || c.type!=='checkbox') return;
      if(!coklu && c.checked){ kutular().forEach(function(o){ if(o!==c) o.checked=false; }); }
      if(c.checked && secili().length>ensok){ c.checked=false; alert(S.sinir.replace('%d',ensok)); }
      ciz();
    });

    if(ara) ara.addEventListener('input',function(){
      var q=ara.value.trim().toLocaleLowerCase('tr');
      var anaKutu=kok.querySelectorAll('.alsec-ana');
      Array.prototype.forEach.call(anaKutu,function(d){
        var gorunen=0;
        Array.prototype.forEach.call(d.querySelectorAll('.alsec-sat'),function(l){
          var m = q==='' || l.textContent.toLocaleLowerCase('tr').indexOf(q)>=0;
          l.style.display = m ? '' : 'none';
          if(m) gorunen++;
        });
        Array.prototype.forEach.call(d.querySelectorAll('.alsec-bas'),function(b){
          var n=b.nextElementSibling, gor=false;
          while(n && !n.classList.contains('alsec-bas')){ if(n.style.display!=='none') gor=true; n=n.nextElementSibling; }
          b.style.display = gor ? '' : 'none';
        });
        d.style.display = gorunen ? '' : 'none';
        if(q!=='' && gorunen) d.open=true;
        if(q==='') d.open=false;
      });
    });

    var acDg=kok.querySelector('[data-alsec-oner-ac]');
    var kutu=kok.querySelector('[data-alsec-oner]');
    if(acDg && kutu) acDg.addEventListener('click',function(){ kutu.classList.toggle('alsec-gizli'); });

    var gonder=kok.querySelector('[data-alsec-oner-gonder]');
    if(gonder) gonder.addEventListener('click',function(){
      var msj=kok.querySelector('[data-alsec-oner-msj]');
      var ust2=kok.querySelector('[data-alsec-oust]').value;
      var tr=kok.querySelector('[data-alsec-otr]').value.trim();
      var enA=kok.querySelector('[data-alsec-oen]').value.trim();
      var ger=kok.querySelector('[data-alsec-oger]').value.trim();
      var ad=kok.querySelector('[data-alsec-oad]').value.trim();
      msj.className='form-msj';
      if(!tr || !ger || !ad){ msj.className='form-msj err'; msj.textContent=S.eksik; return; }
      if(ger.length<60){ msj.className='form-msj err'; msj.textContent=S.kisa; return; }
      msj.textContent=S.bekle; gonder.disabled=true;
      fetch('/api/alan-oner',{method:'POST',credentials:'same-origin',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify({ust:ust2,tr:tr,en:enA,gerekce:ger,ad:ad})})
        .then(function(r){return r.json();})
        .then(function(d){
          gonder.disabled=false;
          if(d&&d.ok){ msj.className='form-msj ok'; msj.textContent=d.mesaj||'';
            kok.querySelector('[data-alsec-otr]').value='';
            kok.querySelector('[data-alsec-oen]').value='';
            kok.querySelector('[data-alsec-oger]').value='';
          } else { msj.className='form-msj err'; msj.textContent=(d&&d.hata)||S.baglan; }
        })
        .catch(function(){ gonder.disabled=false; msj.className='form-msj err'; msj.textContent=S.baglan; });
    });

    kok.alsecCiz = ciz;   /* dışarıdan yazıldığında yeniden çizmek için */
    ciz();
  }
  function hepsi(){ Array.prototype.forEach.call(document.querySelectorAll('[data-alsec]'),kur); }
  if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',hepsi); else hepsi();

  /* Dışarıdan okuma ve yazma: panel gibi betikle çalışan sayfalar için */
  window.alSecOku=function(kimlik){
    var kok=document.querySelector('[data-alsec="'+kimlik+'"]'); if(!kok) return [];
    return Array.prototype.slice.call(kok.querySelectorAll('input[type=checkbox]:checked')).map(function(c){return c.value;});
  };
  window.alSecYaz=function(kimlik,liste){
    var kok=document.querySelector('[data-alsec="'+kimlik+'"]'); if(!kok) return;
    liste=liste||[];
    Array.prototype.forEach.call(kok.querySelectorAll('input[type=checkbox]'),function(c){
      c.checked = liste.indexOf(c.value)>=0;
    });
    if(kok.alsecCiz) kok.alsecCiz();
  };
  window.alSecKur=hepsi;
})();
</script>
HTML;
    }
}
