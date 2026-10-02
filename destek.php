<?php
/* =====================================================================
   KUTADGU - Destek ve himaye / Support and custody
   ---------------------------------------------------------------------
   Bir çağrının karşılık bulması için üç şeyin aynı sayfada yazılı olması
   gerekir: ne isteniyor, karşılığında ne garanti ediliyor ve nasıl
   başvurulur. Bu sayfa yalnızca bunu yapar.

   Çağrı Türkiye ile sınırlı değildir. Dünyanın herhangi bir yerindeki
   bir üniversite, kütüphane, vakıf ya da araştırma kuruluşu başvurabilir;
   sistemin kendisi de zaten hiçbir ülkenin malı değildir.

   Başvurular iletişim düzeninden geçer ve 'kurum' türüyle işaretlenir;
   böylece öteki iletilerin arasında kaybolmaz.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

$en  = k_en();
$say = k_sayaclar();

$ekBas = <<<CSS
<style>
/* Kart, kutu, form ve düğme dizgeden gelir. Burada yalnızca çağrı
   metnine özgü biçim durur: giriş paragrafı, numaralı madde listesi ve
   değişmeyen sınırı yazan kutu. */
.ds{min-width:0}
/* Başlıklar sağ raydaki içindekiler listesinden atlanır; yapışkan üst
   çubuğun altında kalmasınlar. */
.ds h2{scroll-margin-top:calc(var(--ust) + var(--b-5));margin:var(--b-6) 0 var(--b-2)}
.ds-giris{font-size:var(--y-5);border-left:3px solid var(--kut);padding-left:var(--b-5);margin:0 0 var(--b-6)}
.ds-say{margin:0 0 var(--b-6)}
.ds-say b{display:block;font-family:var(--baslik);font-size:var(--y-7);line-height:1.1}
.ds-say span{display:block;font-size:var(--y-1);color:var(--metin-2);margin-top:2px;
  letter-spacing:.05em;text-transform:uppercase}
/* Numaralı maddeler. Sayı listenin kendi sayacından gelir: maddeler
   yer değiştirdiğinde numaralar da kendiliğinden düzelir. */
.ds-liste{display:grid;gap:var(--b-3);margin:var(--b-5) 0;counter-reset:dsn;list-style:none;padding:0}
.ds-liste li{counter-increment:dsn;position:relative;padding-left:var(--b-7);font-size:var(--y-4)}
.ds-liste li::before{content:counter(dsn);position:absolute;left:var(--b-4);top:var(--b-4);
  width:var(--b-5);height:var(--b-5);border-radius:50%;
  background:var(--kut-zemin);color:var(--kut);font-family:var(--mono);
  font-size:var(--y-2);font-weight:700;display:grid;place-items:center}
.ds-liste b{display:block;margin-bottom:2px}
.ds-soz{margin:var(--b-5) 0}
.ds-soz b{color:var(--yesil)}
.ds-form{margin-top:var(--b-5)}
/* Bal kabağı: form alanı gibi görünen ama kimsenin görmediği bir tuzak.
   Ekran dışında durur; ekran okuyucudan da aria ile gizlenmiştir. */
input.ds-balkabi{position:absolute;left:-9999px;width:1px;height:1px;opacity:0}
</style>
CSS;

k_bas([
    'tur'      => 'belge',
    'baslik'   => k_c('Destek ve himaye', 'Support and custody'),
    'yol'      => '/destek.php',
    'aciklama' => k_c(
        'Kutadgu\'ya destek olmak ya da sistemin himayesini üstlenmek isteyen üniversite, kütüphane, vakıf ve araştırma kuruluşları için açık çağrı.',
        'An open call to universities, libraries, foundations and research organisations willing to support Kutadgu or to take on custody of the system.'
    ),
    'ek_bas'   => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Açık çağrı', 'Open call') ?></span>
      <h1><?= k_c('Destek ve himaye', 'Support and custody') ?></h1>
      <p><?= k_c(
        'Kutadgu, ücretsiz, reklamsız ve ödeme duvarsız işleyen; hakem raporlarından kurul oylamalarına kadar bütün süreçleri kamuya açık olan bir akademik yayın altyapısıdır. Bugün tek bir sunucu üzerinde, kendi imkânlarıyla duruyor. Bu sayfa, onu ayakta tutacak desteği açıkça istemek içindir.',
        'Kutadgu is an academic publishing infrastructure that runs free of charge, free of advertising and free of paywalls, with every process from referee reports to panel votes open to the public. Today it stands on a single server, by its own means. This page is here to ask plainly for the support that will keep it standing.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
    <div class="ds blg-ic">

      <p class="ds-giris"><?= k_c(
        'Bu çağrı Türkiye ile sınırlı değildir. Dünyanın herhangi bir yerindeki bir üniversite, kütüphane, vakıf ya da araştırma kuruluşu başvurabilir. Sistem hiçbir ülkenin, hiçbir kurumun ve hiçbir kişinin malı değildir; bütün hakları bütün insanlığındır. Bu yüzden destek de, himaye de bir mülkiyet devri değil bir sorumluluğun paylaşılmasıdır.',
        'This call is not limited to Türkiye. A university, library, foundation or research organisation anywhere in the world may apply. The system is the property of no country, no institution and no person; all rights in it belong to all humanity. Support, and custody, are therefore not a transfer of ownership but the sharing of a responsibility.'
      ) ?></p>

      <div class="dizi dizi-4 ds-say">
        <div class="kart"><b><?= k_esc(k_sayi_kisa($say['toplam'])) ?></b><span><?= k_c('çalışma', 'works') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa($say['hakemli'])) ?></b><span><?= k_c('hakemli', 'peer reviewed') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa($say['yazar'])) ?></b><span><?= k_c('yazar', 'authors') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa($say['okuma'])) ?></b><span><?= k_c('okuma', 'reads') ?></span></div>
      </div>

      <h2 id="ne"><?= k_c('Ne isteniyor', 'What is asked for') ?></h2>
      <ol class="ds-liste">
        <li class="kart"><b><?= k_c('Ayna kopyalar ve uzun süreli saklama', 'Mirror copies and long term preservation') ?></b><?= k_c(
          'Bir arşiv ancak birden çok yerde durduğunda kalıcıdır. Arşivin tamamı, kişisel veri içermeyen tek bir dosyadır; onu düzenli olarak alıp saklayacak her kurum bu sistemin sürekliliğine katkı vermiş olur. Teknik iş bize aittir.',
          'An archive is permanent only when it stands in more than one place. The whole archive is a single file containing no personal data; any institution that takes and preserves it regularly contributes to this system\'s continuity. The technical work is ours to do.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Sunucu ve altyapı', 'Servers and infrastructure') ?></b><?= k_c(
          'Dayanıklı bir sunucu, düzenli yedekleme ve alan adı giderlerinin yıldan yıla belirsizliğe bırakılmadan bir düzene bağlanması. Barındırmayı doğrudan üstlenmek de bir destek biçimidir.',
          'A durable server, regular backups, and putting domain costs on a settled footing rather than leaving them to year to year uncertainty. Taking on the hosting directly is itself a form of support.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Çeviri', 'Translation') ?></b><?= k_c(
          'Sistem şu an iki dilde yazılıyor ve makine çevirisi bilerek kapalı tutuluyor: ücretli bir hizmete bağlanmak, "her zaman ücretsiz" sözüyle çelişir. Desteklenen bir çeviri katmanı, bir çalışmanın dünyanın her yerinde okunabilmesi demektir. Açık erişimin asıl vaadi budur.',
          'The system is at present written in two languages and machine translation is deliberately switched off: binding it to a paid service would contradict the promise that it is always free. A supported translation layer means a work can be read anywhere in the world. That is the real promise of open access.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Yürütücü kurum', 'A host institution') ?></b><?= k_c(
          'Kamusal araştırma fonlarına başvurabilmek için tüzel bir muhatap gerekir. Bir üniversitenin ya da araştırma kuruluşunun yürütücülüğü üstlenmesi, bu kapıyı tek başına açar.',
          'Applying to public research funds requires a legal counterparty. A university or research organisation taking on the host role opens that door by itself.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Bakımın sürekliliği', 'The continuity of maintenance') ?></b><?= k_c(
          'Bir sistem yazıldıktan sonra kendi kendine durmaz: sunucu yenilenir, güvenlik açığı kapatılır, kurallar işledikçe eksikleri görünür ve düzeltilir, iletiler yanıtlanır. Bu süregiden bir emektir ve desteğin bunu da karşılaması olağandır.',
          'A system does not stand by itself once written: servers are renewed, vulnerabilities are closed, gaps in the rules become visible as they run and are corrected, messages are answered. This is continuing labour, and it is ordinary for support to cover it.'
        ) ?></li>
      </ol>

      <h2 id="soz"><?= k_c('Karşılığında ne garanti ediliyor', 'What is guaranteed in return') ?></h2>
      <div class="kutu kutu-yes ds-soz"><?= k_c(
        '<b>Değişmeyen sınır:</b> yazardan, hakemden ve okurdan hiçbir koşulda ücret alınmaz; hiçbir gelir erişimi kısıtlamaktan doğamaz. Bu sistemin geliri varsa erişimi genişletmekten doğar, daraltmaktan değil.',
        '<b>The boundary that does not move:</b> no fee is ever charged to an author, a reviewer or a reader, and no revenue may arise from restricting access. If this system has an income, it arises from widening access, not from narrowing it.'
      ) ?></div>
      <ol class="ds-liste">
        <li class="kart"><b><?= k_c('Destek yayın kararlarına dokunmaz', 'Support does not touch editorial decisions') ?></b><?= k_c(
          'Destek veren hiçbir kurum bir çalışmanın kabulüne, reddine ya da bir hakem raporuna etki edemez. Kurul kararları kalıcıdır ve sistemin kurucuları dâhil kimse geri alamaz. Bu bir nezaket değil, sistemin işleyişine yazılı bir kuraldır.',
          'No supporting institution may influence the acceptance or rejection of a work, or a referee report. Panel decisions are permanent and no one, the founders included, can reverse them. This is not a courtesy but a rule written into how the system runs.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Her kuruş yazılır', 'Every payment is recorded') ?></b><?= k_c(
          'Alınan her desteğin kimden geldiği ve ne için harcandığı istatistik sayfasında açıkça yazılır. Bu sistem kendi kusurlarını yazan bir sayfa tutuyor; kendi gelirini yazmaması düşünülemez.',
          'The source of every support received and the purpose it was spent on are written openly on the statistics page. This system keeps a page on which it writes its own faults; it could hardly fail to write its own income.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Yazılım açık, arşiv açık', 'The software is open, the archive is open') ?></b><?= k_c(
          'Yazılımın tamamı AGPL-3.0 ile yayımlanır: alan herkes kurabilir, değiştirebilir; ancak değiştirilmiş bir sürümle ağ hizmeti veriyorsa kaynağını da açmak zorundadır. Böylece Kutadgu\'dan türeyen hiçbir sistem kapalı olamaz. Bütün çalışmalar CC BY 4.0\'dır ve arşivin tamamı her an indirilebilir.',
          'The whole of the software is published under AGPL-3.0: anyone may install and modify it, but a modified version offered as a network service must also make its source available. No system derived from Kutadgu can therefore be closed. All works are CC BY 4.0 and the entire archive can be downloaded at any time.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Süreklilik kişiye bağlı değil', 'Continuity does not rest on a person') ?></b><?= k_c(
          'Arşiv indirilebilir olduğu için bu sistem yarın kapansa bile kayıt kaybolmaz ve kimse, kurucuları dâhil, onu kilitleyemez. Bakım altı ay kesilirse emanet kendiliğinden kurucu baş editörler kuruluna, o da üstlenemezse arşivi kabul eden bir saklama hizmetine geçer.',
          'Because the archive is downloadable, the record would not be lost even if this system closed tomorrow, and no one, its founders included, can lock it. Should maintenance lapse for six months, the trust passes of itself to the board of founding chief editors, and if they cannot take it on, to a preservation service willing to accept the archive.'
        ) ?></li>
        <li class="kart"><b><?= k_c('Destek görünür olur', 'Support is visible') ?></b><?= k_c(
          'Destek veren kurumun adı, dilerse, sistemin destekçiler bölümünde ve ilgili sayfalarda yazılı kalır. İstemezse yazılmaz; bu da kurumun kendi kararıdır.',
          'The name of a supporting institution, if it wishes, remains written in the supporters section and on the relevant pages. If it prefers not, it is not written; that too is the institution\'s own decision.'
        ) ?></li>
      </ol>

      <h2 id="basvuru"><?= k_c('Başvuru', 'Get in touch') ?></h2>
      <p><?= k_c(
        'Aşağıdaki form doğrudan yayın yönetimine ulaşır ve genellikle aynı gün yanıtlanır. Bir taahhüt değildir: yalnızca konuşmayı başlatır. Neyi üstlenebileceğinizi ya da neyi merak ettiğinizi yazmanız yeter.',
        'The form below reaches the publication\'s management directly and is usually answered the same day. It is not a commitment: it only starts the conversation. Write what you might be able to take on, or what you would like to know.'
      ) ?></p>

      <form class="kart ds-form" id="dsForm" autocomplete="off">
        <label for="dsAd"><?= k_c('Adınız ve göreviniz', 'Your name and role') ?></label>
        <input type="text" id="dsAd" placeholder="<?= k_esc(k_c('Prof. Dr. ... , Kütüphane Daire Başkanı', 'Prof. ... , Director of Libraries')) ?>">

        <label for="dsKurum"><?= k_c('Kurumunuz', 'Your institution') ?></label>
        <input type="text" id="dsKurum" placeholder="<?= k_esc(k_c('Üniversite / kütüphane / vakıf / kuruluş', 'University / library / foundation / organisation')) ?>">

        <label for="dsPosta"><?= k_c('E-posta', 'E mail') ?></label>
        <input type="email" id="dsPosta" placeholder="ornek@universite.edu">

        <label for="dsTur"><?= k_c('Ne konuşmak istersiniz', 'What would you like to discuss') ?></label>
        <select id="dsTur">
          <option value="ayna"><?= k_c('Ayna kopya ve uzun süreli saklama', 'Mirror copy and long term preservation') ?></option>
          <option value="sunucu"><?= k_c('Sunucu ve barındırma', 'Servers and hosting') ?></option>
          <option value="ceviri"><?= k_c('Çeviri desteği', 'Translation support') ?></option>
          <option value="yurutucu"><?= k_c('Yürütücü kurum olmak', 'Becoming the host institution') ?></option>
          <option value="himaye"><?= k_c('Sistemin himayesini üstlenmek', 'Taking on custody of the system') ?></option>
          <option value="baska"><?= k_c('Başka bir konu', 'Something else') ?></option>
        </select>

        <label for="dsMetin"><?= k_c('İletiniz', 'Your message') ?></label>
        <textarea id="dsMetin" placeholder="<?= k_esc(k_c('Neyi üstlenebilirsiniz, neyi merak ediyorsunuz?', 'What might you take on, and what would you like to know?')) ?>"></textarea>
        <p class="ipucu"><?= k_c(
          'İletiniz size özel bir bağlantıyla açılan bir konuşma sayfasında saklanır; yanıt oraya düşer. Adresiniz hiçbir sayfada görünmez ve hiçbir listeye eklenmez.',
          'Your message is kept on a conversation page opened by a link unique to you; the reply appears there. Your address is shown on no page and added to no list.'
        ) ?></p>

        <input class="ds-balkabi" type="text" id="dsBal" name="website" tabindex="-1" aria-hidden="true" autocomplete="off">
        <button class="d d-vurgu" type="submit" id="dsDg"><?= k_c('Gönder', 'Send') ?></button>
        <p class="form-msj" id="dsMsj"></p>
      </form>

    </div>
    <?= k_belge_yan(
      [
        ['k' => 'ne',      'tr' => 'Ne isteniyor',   'en' => 'What is asked for'],
        ['k' => 'soz',     'tr' => 'Ne garanti ediliyor', 'en' => 'What is guaranteed'],
        ['k' => 'basvuru', 'tr' => 'Başvuru',        'en' => 'Get in touch'],
      ],
      [
        ['tr' => 'Neden destek isteniyor', 'en' => 'Why support is asked for',
         'ic' => k_c(
           'Kalıcı kimlik vermek kalıcılığı taahhüt etmektir. Bu taahhüt tek bir makineye, tek bir faturaya ve tek bir kişinin ömrüne dayandığı sürece eksik kalır.',
           'To issue a permanent identifier is to promise permanence. That promise stays incomplete for as long as it rests on one machine, one invoice and the lifetime of one person.'
         )],
        ['tr' => 'Bir bedel istenmiyor', 'en' => 'Nothing is asked in return',
         'ic' => k_c(
           'Himaye devri bir satış değildir; hiçbir bedel istenmez. Devralan kurum yayın yönetimine ilişkin bütün yetkileri kullanır. Değişmeyen tek şey amaçtır.',
           'A handover of custody is not a sale; nothing is asked in return. The receiving institution exercises every authority pertaining to the running of the publication. The one thing that does not change is the purpose.'
         )],
        ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
         'ic' => '<a href="' . k_esc(k_bag('/bildiri.php#b-devir')) . '">' . k_c('Bildiri: destek ve devir', 'Manifesto: support and succession') . '</a><br>'
               . '<a href="' . k_esc(k_bag('/istatistik.php')) . '">' . k_c('İstatistikler', 'Statistics') . '</a><br>'
               . '<a href="' . k_esc(k_bag('/acikliklar.php')) . '">' . k_c('Açık sözlülük', 'Plain speaking') . '</a><br>'
               . '<a href="' . k_esc(k_bag('/dokum.php')) . '">' . k_c('Arşivin tamamını indir', 'Download the whole archive') . '</a>'],
      ]
    ) ?>
  </div>
</section>
<?php

$S = json_encode([
  'ad'      => k_c('Adınızı yazın.', 'Please write your name.'),
  'posta'   => k_c('Geçerli bir e-posta adresi girin.', 'Please enter a valid e mail address.'),
  'metin'   => k_c('İletinizi biraz daha açık yazar mısınız?', 'Could you write your message a little more fully?'),
  'gonder'  => k_c('Gönderiliyor...', 'Sending...'),
  'dugme'   => k_c('Gönder', 'Send'),
  'baglanti'=> k_c('Bağlantı kurulamadı, tekrar deneyin.', 'Could not connect, please try again.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$TURAD = json_encode([
  'ayna'     => k_c('Ayna kopya ve saklama', 'Mirror copy and preservation'),
  'sunucu'   => k_c('Sunucu ve barındırma', 'Servers and hosting'),
  'ceviri'   => k_c('Çeviri desteği', 'Translation support'),
  'yurutucu' => k_c('Yürütücü kurum', 'Host institution'),
  'himaye'   => k_c('Himaye', 'Custody'),
  'baska'    => k_c('Başka', 'Other'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$betik = <<<JS
<script>
(function(){
  var S={$S}, TURAD={$TURAD};
  function \$(i){return document.getElementById(i);}
  var f=\$('dsForm'); if(!f) return;
  f.addEventListener('submit',function(e){
    e.preventDefault();
    var m=\$('dsMsj');
    var ad=\$('dsAd').value.trim(), kurum=\$('dsKurum').value.trim(),
        posta=\$('dsPosta').value.trim(), tur=\$('dsTur').value, metin=\$('dsMetin').value.trim();
    if(!ad){ m.textContent=S.ad; m.className='form-msj err'; \$('dsAd').focus(); return; }
    if(!/.+@.+\\..+/.test(posta)){ m.textContent=S.posta; m.className='form-msj err'; \$('dsPosta').focus(); return; }
    if(metin.length<20){ m.textContent=S.metin; m.className='form-msj err'; \$('dsMetin').focus(); return; }
    \$('dsDg').disabled=true; m.textContent=S.gonder; m.className='form-msj';
    /* Kurum adı ve konuşma başlığı, konu satırında birlikte gider:
       kurucunun telefonuna düşen bildirimde kimin yazdığı ilk satırda
       görünsün diye. */
    var konu=(TURAD[tur]||tur)+(kurum?(' | '+kurum):'');
    fetch('/api/iletisim',{method:'POST',headers:{'Content-Type':'application/json'},
      body:JSON.stringify({ad:ad,eposta:posta,konu:konu,metin:metin,tur:'kurum',website:\$('dsBal').value})})
      .then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok){ m.innerHTML=(d.mesaj||'')+(d.baglanti?(' <a href="'+d.baglanti+'">'+d.baglanti+'</a>'):'');
          m.className='form-msj ok'; f.reset(); }
        else { m.textContent=(d&&d.hata)||S.baglanti; m.className='form-msj err'; }
        \$('dsDg').disabled=false;
      }).catch(function(){ m.textContent=S.baglanti; m.className='form-msj err'; \$('dsDg').disabled=false; });
  });
})();
</script>
JS;

k_son($betik);
