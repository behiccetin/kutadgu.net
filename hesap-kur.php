<?php
/* =====================================================================
   KUTADGU - Davetle hesap kurma / Setting up an invited account
   ---------------------------------------------------------------------
   Bir baş editör birini davet ettiğinde sistem parolasız bir hesap
   iskeleti kurar ve tek kullanımlık bir bağlantı üretir. Bu sayfa o
   bağlantının açıldığı yerdir.

   PAROLAYI KİŞİNİN KENDİSİ KURAR. Sistem hiçbir zaman bir parola
   üretmez ve hiç kimse bir başkasının parolasını görmez, iletmez ya da
   bir yerde saklar. Bir parolayı e-postayla göndermek, onu o e-posta
   kutusunda ve yolda geçtiği her sunucuda bırakmak demektir; o yüzden
   yapılmaz.

   Bağlantı on dört günde düşer ve bir kez kullanılır. Anahtarın kendisi
   kayıtta durmaz, yalnızca özeti durur; kayıt ele geçse bile anahtar
   geri üretilemez.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en  = k_en();
/* Anahtar yalnızca onaltılık olabilir; başka bir şey gelirse sayfa
   sunucuya hiç sormaz. */
$kod = preg_replace('/[^a-f0-9]/', '', (string)($_GET['k'] ?? ''));
/* AYNI SAYFA İKİ İŞ GÖRÜR: davet (?k=) ve parola yenileme (?y=).
   İkisi de aynı şeyi yaptırır (kişi kendi parolasını kurar) ve aynı
   iskeleti kullanır; ayrı bir sayfa açmak, aynı formu iki yerde
   tutmak olurdu ve ikisi zamanla ayrışırdı. Değişen yalnızca hangi
   ucun sorulduğu ve başlıktaki cümledir. */
$yen = preg_replace('/[^a-f0-9]/', '', (string)($_GET['y'] ?? ''));
$yenMi = ($yen !== '' && $kod === '');

$ekBas = <<<CSS
<style>
/* Sayfada kalan tek şey tanıtım bölümünün kendi ritmidir: kartın
   içinde, karşılama ile parola kutusu arasında duran bir metin
   adasıdır ve iki yanından çizgiyle ayrılır. Kart, form, düğme, ileti
   ve not kutusu kuralları dizgeden gelir. */
.hk-kim{font-size:var(--y-6);line-height:var(--sh-orta);margin:0 0 var(--b-1)}
.hk-kim b{font-family:var(--serif)}
.hk-kurum{color:var(--metin-2);font-size:var(--y-3);line-height:var(--sh-orta);margin:0 0 var(--b-4)}
/* Tanıtım bölümü. Parola kutusundan çizgiyle ayrılır: okur önce ne
   olduğunu okur, sonra karar verir. */
.hk-tanit{margin:var(--b-4) 0 var(--b-1);padding:var(--b-4) 0 var(--b-1);
  border-top:1px solid var(--cizgi)}
.hk-tanit h2{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.13em;
  text-transform:uppercase;color:var(--metin-2);margin:0 0 var(--b-2)}
.hk-tanit h2 + p{margin-top:0}
.hk-tanit p{font-size:var(--y-5);line-height:var(--sh-genis);margin:0 0 var(--b-4);max-width:68ch}
.hk-tanit ul{list-style:none;margin:0 0 var(--b-4);padding:0;display:grid;gap:var(--b-3);max-width:68ch}
.hk-tanit li{position:relative;padding-left:var(--b-5);font-size:var(--y-4);line-height:var(--sh-genis)}
.hk-tanit li::before{content:"";position:absolute;left:2px;top:.65em;width:6px;height:6px;
  border-radius:50%;background:var(--kut)}
.hk-tsk{color:var(--metin-2)}
.hk-bag{margin:0 0 var(--b-1)}
.hk-parola-bas{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.12em;
  text-transform:uppercase;color:var(--metin-2);margin:var(--b-5) 0 var(--b-3);padding-top:var(--b-4);
  border-top:1px solid var(--cizgi)}
.hk-dg{margin-top:var(--b-5)}
.hk-not{margin-top:var(--b-4)}
</style>
CSS;

k_bas([
    'olcu'   => 'okuma',
    'tur'    => 'belge',
    'baslik' => k_c('Hesabınızı kurun', 'Set up your account'),
    'yol'    => '/hesap-kur.php',
    'robots' => 'noindex,nofollow',
    'aciklama' => k_c(
        'Davet bağlantısıyla gelen kişinin kendi parolasını kurduğu sayfa.',
        'The page where an invited person sets their own password.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Davet', 'Invitation') ?></span>
      <h1><?= k_c('Hesabınızı kurun', 'Set up your account') ?></h1>
      <p><?= k_c(
        'Bu sistemde hiç kimse bir başkasının parolasını görmez, iletmez ya da bir yerde saklamaz. Parolanızı burada siz kurarsınız ve yalnızca siz bilirsiniz.',
        'In this system no one sees, sends or stores anyone else\'s password. You set your own password here, and only you know it.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
    <div class="hk blg-ic">

      <p class="yukleniyor" id="hkYuk"><?= k_c('Bağlantı okunuyor...', 'Reading the link...') ?></p>

      <div class="kutu kutu-kut gizli" id="hkHata"></div>

      <?php /* ---- ÖNCE SİSTEM, SONRA KİŞİ, SONRA PAROLA ----
               Davetle gelen kişi çoğu zaman bu sistemi hiç duymamıştır.
               Karşısına ilk çıkan şey bir parola kutusu olursa, neye
               kaydolduğunu bilmeden kaydolur. Bu yüzden sıra şudur:
               sistemin ne olduğu, kişinin burada ne yapabileceği, sonra
               parola. Metin kısa tutulur; uzun bir tanıtım okunmaz. */ ?>
      <div class="kart gizli" id="hkKart">
        <p class="hk-kim"><?= k_c('Hoş geldiniz,', 'Welcome,') ?> <b id="hkAd"></b></p>
        <p class="hk-kurum" id="hkKurum"></p>

        <div class="hk-tanit">
          <h2><?= k_c('Kutadgu nedir', 'What Kutadgu is') ?></h2>
          <p><?= k_c(
            'Kutadgu, bilimsel çalışmaların ücretsiz yayımlandığı bağımsız bir yayın sistemidir. Yazardan işlem ücreti, okurdan abonelik, kurumdan erişim bedeli alınmaz ve alınamaz. Hakemlik kapalı değil açıktır: raporlar, kararlar ve gerekçeler çalışmanın sayfasında adlarıyla birlikte durur. Bir çalışmanın ölçüsü yazarının unvanı ya da kurumu değil, yönteminin sağlamlığı ve kaynaklarının denetlenebilirliğidir.',
            'Kutadgu is an independent publishing system in which scholarly work is published free of charge. No processing fee from authors, no subscription from readers, no access fee from institutions, and none may ever be charged. Review is not closed but open: reports, decisions and their reasoning stand on the work\'s own page together with the names. The measure of a work is not its author\'s title or institution but the soundness of its method and the verifiability of its sources.'
          ) ?></p>

          <h2><?= k_c('Burada ne yapabilirsiniz', 'What you can do here') ?></h2>
          <ul>
            <li><?= k_c(
              '<b>Çalışma gönderebilirsiniz.</b> Kendi ana dilinizde yazabilirsiniz; kayıt, sizin yazdığınız dildeki metindir ve çeviri onun yerine geçmez.',
              '<b>You may submit work.</b> You may write in your own first language; the record is the text in the language you wrote it in, and a translation does not take its place.'
            ) ?></li>
            <li><?= k_c(
              '<b>Hakemlik yapabilirsiniz.</b> Raporunuz adınızla yayımlanır ve kalıcı bir kayıt olur. Bu sistemde hakemlik görünmeyen bir emek değildir.',
              '<b>You may review.</b> Your report is published under your name and becomes a permanent record. In this system reviewing is not invisible labour.'
            ) ?></li>
            <li id="hkYetkiEditor" class="gizli"><?= k_c(
              '<b>Editörlük yapabilirsiniz.</b> İstediğiniz çalışmaya hakem atayabilir ve editöryal not düşebilirsiniz. Yaptığınız her işlem adınız ve saatiyle çalışmanın sayfasında görünür.',
              '<b>You may act as an editor.</b> You may assign reviewers to any work and add editorial notes. Everything you do appears on the work\'s page with your name and the time.'
            ) ?></li>
            <li id="hkYetkiBas" class="gizli"><?= k_c(
              '<b>Editör atayabilirsiniz.</b> Bu yetki sürelidir ve süresi kurul sayfasında açıkça yazılıdır.',
              '<b>You may appoint editors.</b> This authority is time limited and its end date is written openly on the board page.'
            ) ?></li>
            <li><?= k_c(
              '<b>Hiçbir şey yapmak zorunda değilsiniz.</b> Hesabınız açık kalır, ne zaman isterseniz kullanırsınız.',
              '<b>You are not obliged to do anything.</b> Your account stays open and you use it whenever you wish.'
            ) ?></li>
          </ul>

          <p class="hk-tsk"><?= k_c(
            'Bilime ayıracağınız zaman için şimdiden teşekkür ederiz. Bu sistemin işleyebilmesi, çalışmayı okuyup gerekçesini yazmaya vakit ayıran insanlara bağlıdır; başka bir dayanağı yoktur.',
            'Thank you in advance for the time you will give to scholarship. Whether this system works at all depends on people who take the time to read a work and write down their reasoning; it rests on nothing else.'
          ) ?></p>

          <p class="d-kume hk-bag">
            <a class="d d-ikinci d-git" href="<?= k_esc(k_bag('/bildiri.php')) ?>" target="_blank" rel="noopener"><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></a>
            <a class="d d-ikinci d-git" href="<?= k_esc(k_bag('/ilkeler.php')) ?>" target="_blank" rel="noopener"><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></a>
            <a class="d d-ikinci d-git" href="<?= k_esc(k_bag('/nasil-isler.php')) ?>" target="_blank" rel="noopener"><?= k_c('Sistem nasıl işler', 'How the system works') ?></a>
          </p>
        </div>

        <h2 class="hk-parola-bas"><?= k_c('Parolanızı kurun', 'Set your password') ?></h2>

        <label for="hkP1"><?= k_c('Parolanız', 'Your password') ?></label>
        <input type="password" id="hkP1" autocomplete="new-password" minlength="10">
        <p class="ipucu"><?= k_c(
          'En az 10 karakter. Uzun ve size özgü bir cümle, kısa ve karışık bir dizeden daha güvenlidir ve akılda kalır.',
          'At least 10 characters. A long sentence of your own is both safer and easier to remember than a short jumble.'
        ) ?></p>

        <label for="hkP2"><?= k_c('Parolanız (yeniden)', 'Your password (again)') ?></label>
        <input type="password" id="hkP2" autocomplete="new-password" minlength="10">

        <button class="d d-vurgu hk-dg" type="button" id="hkDg"><?= k_c('Parolayı kur ve gir', 'Set the password and sign in') ?></button>
        <p class="form-msj" id="hkMsj"></p>

        <div class="kutu hk-not"><?= k_c(
          'Parolanızı kurduğunuz anda oturumunuz açılır ve panelinize gidersiniz. Bu bağlantı o anda tükenir; bir daha çalışmaz. Parolanızı sonradan panelden değiştirebilirsiniz.',
          'The moment you set your password you are signed in and taken to your panel. This link is used up at that moment and will not work again. You can change your password later from the panel.'
        ) ?></div>
      </div>

    </div>
  </div>
</section>
<?php
$L = $en ? 1 : 0;
$JS = <<<JS
<script>
(function(){
  var KOD = <?kod?>;
  var YEN = <?yen?>;
  var EN  = {$L};
  var S = {
    gecersiz: EN ? 'This link is invalid or has expired. Ask the person who invited you for a new one.'
                 : 'Bu bağlantı geçersiz ya da süresi dolmuş. Sizi davet eden kişiden yenisini isteyin.',
    kisa:     EN ? 'The password must be at least 10 characters.' : 'Parola en az 10 karakter olmalıdır.',
    esitDegil:EN ? 'The two passwords do not match.' : 'İki parola birbirini tutmuyor.',
    bekle:    EN ? 'Working...' : 'Bekleyin...',
    baglanti: EN ? 'Could not reach the server.' : 'Sunucuya ulaşılamadı.',
    tamam:    EN ? 'Done. Taking you to your panel...' : 'Tamam. Panelinize gidiliyor...'
  };
  function \$(i){ return document.getElementById(i); }
  function goster(e,v){ e.classList.toggle('gizli', !v); }
  function msj(e,t,s){ e.textContent=t; e.className='form-msj'+(s?' '+s:''); }
  function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':s; return d.innerHTML; }

  function hataGoster(t){
    goster(\$('hkYuk'), false);
    goster(\$('hkKart'), false);
    \$('hkHata').textContent = t || S.gecersiz;
    goster(\$('hkHata'), true);
  }

  if (!KOD) { hataGoster(); return; }

  fetch(YEN ? '/api/hesap/yenileme-bilgi' : '/api/davet-bilgi',
    {method:'POST', headers:{'Content-Type':'application/json'},
     body: JSON.stringify(YEN ? {y: KOD} : {k: KOD})})
   .then(function(r){ return r.json(); })
   .then(function(d){
     if (!d || !d.ok) { hataGoster(d && d.hata); return; }
     \$('hkAd').textContent = d.ad || '';
     \$('hkKurum').textContent = d.kurum || '';
     /* Kişiye yalnızca kendisinde olan yetkiler anlatılır. Olmayan bir
        yetkiyi anlatmak, olduğunu sandırır. */
     if (d.editor) goster(\$('hkYetkiEditor'), true);
     if (d.bas_yetki) goster(\$('hkYetkiBas'), true);
     goster(\$('hkYuk'), false);
     goster(\$('hkKart'), true);
     \$('hkP1').focus();
   })
   .catch(function(){ hataGoster(S.baglanti); });

  \$('hkDg').addEventListener('click', function(){
    var p1 = \$('hkP1').value, p2 = \$('hkP2').value;
    if (p1.length < 10) { msj(\$('hkMsj'), S.kisa, 'err'); return; }
    if (p1 !== p2)      { msj(\$('hkMsj'), S.esitDegil, 'err'); return; }
    \$('hkDg').disabled = true;
    msj(\$('hkMsj'), S.bekle);
    fetch(YEN ? '/api/hesap/parola-yenile' : '/api/davet-parola',
      {method:'POST', headers:{'Content-Type':'application/json'},
       body: JSON.stringify(YEN ? {y: KOD, parola: p1} : {k: KOD, parola: p1})})
     .then(function(r){ return r.json(); })
     .then(function(d){
       if (d && d.ok) {
         msj(\$('hkMsj'), S.tamam, 'ok');
         setTimeout(function(){ location.href = '/panel.php'; }, 900);
       } else {
         \$('hkDg').disabled = false;
         msj(\$('hkMsj'), (d && d.hata) || S.baglanti, 'err');
       }
     })
     .catch(function(){ \$('hkDg').disabled = false; msj(\$('hkMsj'), S.baglanti, 'err'); });
  });

  \$('hkP2').addEventListener('keydown', function(e){ if (e.key === 'Enter') \$('hkDg').click(); });
})();
</script>
JS;
/* Anahtar betiğe JSON olarak konur; dizge birleştirmek, içine tırnak
   sıkıştıran bir adresle betiği kırmanın yoludur. */
echo str_replace(['<?kod?>', '<?yen?>'],
    [json_encode($yenMi ? $yen : $kod, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
     $yenMi ? 'true' : 'false'], $JS);
k_son();
