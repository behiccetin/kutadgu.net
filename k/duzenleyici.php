<?php
/* =====================================================================
   KUTADGU - Metin düzenleyici / Rich text editor
   ---------------------------------------------------------------------
   Bir hakem raporu, bir editör notu ve bir makale metni; üçü de düz bir
   kutuya sığmaz. Başlık, madde, çizelge, alıntı, dipnot ve formül
   gerekir. Bunları elle HTML yazarak istemek, yazmayı bilen ama HTML
   bilmeyen bir araştırmacıyı kapıda bırakır.

   Neden hazır bir düzenleyici
     Kendi düzenleyicisini yazmak kulağa tutarlı gelir ama contenteditable
     üzerine kurulu bir düzenleyicinin geri alma, Word'den yapıştırma ve
     erişilebilirlik davranışını doğru yapmak yıllar süren bir iştir.
     Yarım yapılmışı, hiç yapılmamışından kötüdür: yazarın metnini bozar.

   Neden Jodit
     MIT lisanslı, hiçbir dış bağımlılığı yok, saf JavaScript (derleme
     adımı istemez, bu sistemde derleme adımı yoktur) ve doğrudan HTML
     üretir. Sistem metinleri zaten HTML olarak saklıyor; ara bir biçim
     kullanan düzenleyiciler burada gereksiz bir çeviri katmanı doğurur.

   Neden CDN'den değil
     Dosyalar depoda durur. Bir dış sunucu kapandığında ya da ziyaretçiyi
     izlemeye başladığında, bu sistemin yazma ekranı ne çalışmaz hâle
     gelir ne de başkasının izlemesine aracı olur.

   Ağırlık
     Yaklaşık 880 KB. Bu yük YALNIZCA yazma ekranlarında iner: okur
     hiçbir zaman indirmez. Arşiv sayfaları eskisi gibi hafif kalır.

   GÜVENLİK
     Düzenleyicinin ürettiği HTML'e asla güvenilmez. Gelen içerik
     sunucuda guvenli_html() ile süzülür; izinli olmayan her etiket,
     her olay özniteliği ve her javascript: adresi elenir. Düzenleyici
     bir kolaylıktır, bir güvenlik sınırı değildir.
   ===================================================================== */

if (!function_exists('kd_bas')) {

    /* Başlığa konacak stil bağı. Yalnızca düzenleyici kullanan sayfalar çağırır. */
    function kd_bas(): string {
        /* Durum çubuğundaki "powered by" damgası kaldırılır: lisans bunu
           istemiyor ve akademik bir sayfada bir ürün damgası yeri yok.
           Kaynak ve lisans bilgisi, ait olduğu yerde, uygulama sayfasında
           ve k/duzen/LICENSE-jodit.txt dosyasında açıkça yazılıdır. */
        return '<link rel="stylesheet" href="/k/duzen/jodit.min.css?v=' . K_SURUM . '">'
             . '<style>'
             . '.jodit-status-bar__item-right a[href*="xdsoft"],.jodit-status-bar-link{display:none!important}'
             . '.jodit-container{border-radius:var(--r-2);border-color:var(--cizgi)!important}'
             . '.jodit-container:not(.jodit_inline) .jodit-wysiwyg{font-family:var(--serif);font-size:var(--y-5);line-height:var(--sh-genis)}'
             . '.jodit-toolbar__box{border-radius:var(--r-2) var(--r-2) 0 0}'
             /* Araç ipucu kutusu, kullanılmadan önce sayfanın sol kenarının
                on iki piksel dışına park ediyor. Görünmez olduğu için göze
                çarpmaz ama ölçümde yatay taşma sayılıyordu; kutu kendi
                içini kırpar ve sayfanın dışına bir şey taşmaz. */
             . '.jodit-ui-tooltip{overflow:hidden}'
             . '</style>';
    }

    /* Toolbar takımları.
       'tam'   makale metni: her şey
       'rapor' hakem raporu ve editör notu: biçim var, görsel yok
       'kisa'  şerh ve kısa notlar: en az                             */
    function kd_araclar(string $takim = 'rapor'): array {
        $temel = ['bold', 'italic', 'underline', '|', 'ul', 'ol', '|', 'link'];
        if ($takim === 'kisa') {
            return array_merge($temel, ['|', 'undo', 'redo']);
        }
        if ($takim === 'rapor') {
            return array_merge(
                ['paragraph', '|'], $temel,
                ['|', 'table', 'hr', 'superscript', 'subscript', '|', 'symbols', '|', 'undo', 'redo', 'source']
            );
        }
        return array_merge(
            ['paragraph', 'fontsize', '|'], $temel,
            ['|', 'image', 'table', 'hr', '|', 'superscript', 'subscript', 'symbols', '|',
             'align', 'indent', 'outdent', '|', 'eraser', 'undo', 'redo', '|', 'fullsize', 'source']
        );
    }

    /* =================================================================
       YAPI PANELİ (yalnız makale metni · takım 'tam')
       -----------------------------------------------------------------
       Kurul isteği: "içindekiler kısmı otomatik oluşsun; oluşmazsa ne
       yapacağız, onu da söylesin."

       Panel üç şeyi gösterir ve üçü de ÖLÇÜLMÜŞ olgudur, öğüt değil:
         1. Bu metinden şu anda hangi içindekiler çıkıyor,
         2. Hiç başlık yoksa bunun sebebi ve çözümü,
         3. Kaynakça başlığı var mı.

       İÇİNDEKİLER YAZARDAN İSTENMEZ. Başlıklardan üretilir; yazar ayrıca
       yazsaydı iki liste olurdu ve biri bir gün ötekinden ayrılırdı.
       Aynı sebeple şablona da Word'ün TOC alanı konmadı.

       BOŞ LİSTE ÇİZİLMEZ. Başlık yoksa panel boş bir kutu göstermek
       yerine NE YAPILACAĞINI söyler: "başlıklarınızı Başlık 1/2/3
       stiliyle işaretleyin". Boş bir liste, kuralı uygulamadığı hâlde
       uygulamış gibi duran bir ekrandır.
       ================================================================= */
    function kd_yapi(string $id): string {
        $sablon = '/dosya/Kutadgu-calisma-sablonu.docx';
        return '<div class="kd-yapi" id="' . htmlspecialchars($id, ENT_QUOTES) . '-yapi" data-kd-yapi="'
             . htmlspecialchars($id, ENT_QUOTES) . '">'
             . '<div class="kd-yapi-bas">'
             . '<b>' . k_c('Metnin yapısı', 'The structure of the text') . '</b>'
             . '<a class="d d-ikinci d-kucuk" href="' . $sablon . '" download>'
             . k_c('Word şablonunu indir', 'Download the Word template') . '</a>'
             . '</div>'
             . '<div class="kd-yapi-ic"></div>'
             . '</div>';
    }

    /* Panelin biçimi. Sayfaya bir kez konur; kd_bas() ile birlikte. */
    function kd_yapi_stil(): string {
        return '<style>'
             . '.kd-yapi{margin-top:var(--b-3);border:1px solid var(--cizgi);border-radius:var(--r-2);'
             . 'background:var(--yuzey);padding:var(--b-3) var(--b-4)}'
             . '.kd-yapi-bas{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;'
             . 'justify-content:space-between;margin-bottom:var(--b-2)}'
             . '.kd-yapi-bas b{font-size:var(--y-3)}'
             . '.kd-yapi-ic{font-size:var(--y-2);color:var(--metin-2);line-height:var(--sh-orta)}'
             . '.kd-ic-liste{list-style:none;margin:0;padding:0}'
             . '.kd-ic-liste li{padding:2px 0}'
             . '.kd-ic-liste li[data-d="3"]{padding-inline-start:var(--b-4)}'
             . '.kd-ic-liste li[data-d="4"]{padding-inline-start:calc(var(--b-4) * 2)}'
             . '.kd-ic-liste li[data-d="5"]{padding-inline-start:calc(var(--b-4) * 3)}'
             . '.kd-yapi-uyar{color:var(--kirmizi)}'
             . '.kd-yapi-iyi{color:var(--yesil)}'
             . '</style>';
    }

    /**
     * Bir textarea'yı düzenleyiciye çevirir.
     * @param string $id     textarea'nın id'si
     * @param string $takim  tam | rapor | kisa
     * @param int    $enAz   en az yükseklik (piksel)
     */
    function kd_betik(string $id, string $takim = 'rapor', int $enAz = 300): string
    {
        $en = function_exists('k_en') ? k_en() : false;
        $ayar = json_encode([
            'id'    => $id,
            'takim' => kd_araclar($takim),
            'enAz'  => $enAz,
            'dil'   => k_dil(),
            /* Makale metni mi? Word yapıştırması ve yapı paneli YALNIZCA
               makale metninde çalışır: bir hakem raporunda içindekiler
               aramak, olmayan bir şeyi eksik bildirmek olurdu. */
            'tam'   => ($takim === 'tam'),
            /* GÖRSEL YÜKLEME UCU. Adres PHP'den gelir (k_bag): dil eki
               ve alt dizin buradadır. Yalnız makale metninde verilir;
               hakem raporunda görsel istenmiyor. */
            'yukle' => ($takim === 'tam' && function_exists('k_bag')) ? k_bag('/api/yazar-gorsel') : '',
            'sz'    => [
                'yok'     => k_c('Henüz başlık yok. İçindekiler başlıklardan üretilir; bölümlerinizi Başlık 1, Başlık 2, Başlık 3 stiliyle işaretleyin. Word şablonunda bu stiller hazırdır.',
                                 'No headings yet. The contents list is produced from headings; mark your sections with the Heading 1, Heading 2 and Heading 3 styles. Those styles are ready in the Word template.'),
                'kaynak'  => k_c('Kaynakça başlığı bulunamadı. En sonda "Kaynakça" başlıklı bir bölüm olmalı.',
                                 'No bibliography heading found. There must be a section headed "References" at the end.'),
                'kaynakOk'=> k_c('Kaynakça başlığı yerinde.', 'The bibliography heading is in place.'),
                'yerel'   => k_c('%1 görsel atıldı: adresleri yalnız sizin bilgisayarınızda geçerliydi ve sayfada kırık görünürdü. Görselleri yeniden ekleyin ya da yüksek çözünürlüklü dosyaları Zenodo\'ya yükleyip DOI\'sini forma girin.',
                                 '%1 image(s) were dropped: their addresses were valid only on your own computer and would appear broken on the page. Add them again, or upload the high resolution files to Zenodo and enter the DOI in the form.'),
                'basSay'  => k_c('%1 başlık bulundu.', '%1 headings found.'),
                'gorYuk'  => k_c('Görsel yükleniyor…', 'Uploading the image…'),
                'gorOk'   => k_c('%1 görsel sunucuya yüklendi.', '%1 image(s) uploaded to the server.'),
                'gorHata' => k_c('%1 görsel yüklenemedi ve metinden çıkarıldı: yükleme yapabilmek için hesabınıza girmiş olmanız gerekir. Giriş yaptıktan sonra görselleri yeniden ekleyin.',
                                 '%1 image(s) could not be uploaded and were removed from the text: uploading requires you to be signed in to your account. Add the images again after signing in.'),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $srm = K_SURUM;
        return <<<JS
<script src="/k/duzen/jodit.min.js?v={$srm}" defer></script>
<script src="/k/yapistir.js?v={$srm}" defer></script>
<script>
(function(){
  var A = {$ayar};
  function kur(){
    var el = document.getElementById(A.id);
    if (!el || !window.Jodit) return;
    if (el.dataset.kuruldu) return;
    el.dataset.kuruldu = '1';
    var koyu = document.documentElement.getAttribute('data-tema') === 'koyu';
    var j = Jodit.make(el, {
      theme: koyu ? 'dark' : 'default',
      language: A.dil,
      minHeight: A.enAz,
      toolbarAdaptive: false,
      buttons: A.takim,
      buttonsMD: A.takim,
      buttonsSM: A.takim,
      buttonsXS: A.takim,
      statusbar: true,
      showCharsCounter: true,
      showWordsCounter: true,
      showXPathInStatusbar: false,
      askBeforePasteHTML: !A.tam,
      askBeforePasteFromWord: !A.tam,
      defaultActionOnPaste: 'insert_clear_html',
      /* Güvenlik: düzenleyici de temizler, sunucu da temizler. İki kat. */
      cleanHTML: {
        removeEmptyElements: true,
        fillEmptyParagraph: false,
        denyTags: { script: true, iframe: true, object: true, embed: true, style: true, form: true },
        allowTags: false
      },
      /* ---- KAYNAK GÖRÜNÜMÜ CDN'DEN BESLENMEZ ----
         ÖLÇÜLEN KUSUR — 15 Ağustos 2026. Bu dosyanın başında "Neden
         CDN'den değil" diye bir bölüm var ve şöyle diyor: dosyalar
         depoda durur, bir dış sunucu ziyaretçiyi izlemeye başladığında
         bu sistem ona aracı olmaz. Oysa kaynak görünümü düğmesine
         basıldığında düzenleyici cdnjs.cloudflare.com'dan ace.js ve
         beautify.min.js çekiyordu — ölçüldü, sayfa iki dış istek
         yapıyordu. Yani sistem, uygulamadığı bir kuralı duyuruyordu.

         sourceEditor:'area' bu ikisini de gereksiz kılar: kaynak
         görünümü düz bir metin alanı olur. Söz dizimi renklendirmesi
         gider; karşılığında hiçbir dış sunucu yazarın ne zaman ne
         yazdığını öğrenmez. Bu değiş tokuş bilerek yapıldı. */
      sourceEditor: 'area',
      beautifyHTML: false,
      disablePlugins: ['about', 'speech-recognize', 'ai-assistant']
    });
    /* Yazılan metin gizli textarea'ya geri yazılır: sayfanın kendi
       gönderim mantığı hiç değişmez, textarea'yı okumayı sürdürür. */
    j.events.on('change', function(v){
      el.value = v;
      /* Sayfanın kendi göstergeleri (nitelik eşiği gibi) haber alsın */
      document.dispatchEvent(new CustomEvent('kutadgu-duzenleyici', {detail:{id:A.id}}));
    });
    el.addEventListener('kutadgu-oku', function(){ el.value = j.value; });
    el.joditKur = j;

    /* ---- WORD'DEN YAPIŞTIRMA ----
       Kanca DOĞRUDAN yapıştırma olayına takılır, düzenleyicinin kendi
       eklentisine değil: düzenleyicinin sürümü değiştiğinde eklenti adı
       da değişir ve kanca sessizce kopardı. Yapıştırma olayı ise
       tarayıcının kendi olayıdır, değişmez.

       Düzenleyicinin "biçimi koruyayım mı?" penceresi makale metninde
       KAPATILDI: cevap her hâlde aynı olduğunda soru sormak, kullanıcıyı
       hiçbir şeyi değiştirmeyen bir düğmeye bastırmaktır. */
    if (A.tam && window.kutYapistir) {
      var alan = j.editor;   /* contenteditable gövde */
      alan.addEventListener('paste', function(ev){
        var pano = ev.clipboardData; if (!pano) return;
        var ham = pano.getData('text/html');
        if (!ham) return;                       /* düz metin: dokunma */
        ev.preventDefault(); ev.stopPropagation();
        var s = window.kutYapistir.temizle(ham);
        j.s.insertHTML(s.html);
        el.value = j.value;
        yapiCiz(s.yerelGorsel);
        gorselleriYukle();
      }, true);
    }

    /* =================================================================
       GÖMÜLÜ GÖRSELLER DOSYAYA ÇEVRİLİR

       ÖLÇÜLEN KUSUR: düzenleyicinin görsel düğmesi de, Word
       yapıştırması da görseli data: adresiyle metnin İÇİNE gömüyordu.
       Sunucudaki süzgeç (guvenli_html) ise img/src'de yalnız http(s),
       / ve # kabul eder; data: eleniyordu. Yani yazar görseli
       ekliyor, kaydediyor ve görsel SESSİZCE kayboluyordu.

       İki çözüm vardı: süzgeci gevşetmek ya da görseli dosyaya
       çevirmek. Süzgeci gevşetmek yanlış olurdu — data: adresi bir
       adres değil, gövdenin içine gömülmüş bir yüktür; kaydı şişirir,
       önbelleğe alınamaz, ayrı ayrı sunulamaz ve süzgeçte içeriği
       denetlenemez. Bu yüzden görsel YÜKLENİR ve metne /photo/...
       adresiyle girer.

       DÜZENLEYİCİNİN KENDİ YÜKLEYİCİSİ KULLANILMADI: onun protokolüne
       bağlanmak, sürüm değiştiğinde sessizce kopan bir bağ olurdu.
       Burada yapılan iş düzenleyiciden bağımsızdır: metinde data:
       adresli bir görsel varsa yüklenir ve adresi değiştirilir. Aynı
       düzenek hem düğmeyi hem yapıştırmayı kapsar.

       BAŞARISIZ YÜKLEME SESSİZ KALMAZ: görsel metinden çıkarılır ve
       kaç tanesinin neden çıkarıldığı yapı panelinde yazar. Kırık bir
       görsel bırakmak, kaybolan görselden daha kötüdür: yazar onun
       durduğunu sanır.
       ================================================================= */
    var yuklemeSuruyor = false;
    function veriyiBloba(u){
      var v = u.split(','); if (v.length < 2) return null;
      var tur = (v[0].match(/data:([^;]+)/) || [])[1] || 'image/png';
      try {
        var ham = atob(v[1]), n = ham.length, dizi = new Uint8Array(n);
        while (n--) dizi[n] = ham.charCodeAt(n);
        return new Blob([dizi], {type: tur});
      } catch (e) { return null; }
    }
    function gorselNot(metin, sinif){
      var kutu = document.querySelector('[data-kd-yapi="' + A.id + '"] .kd-yapi-ic');
      if (!kutu) return;
      var p = kutu.querySelector('[data-gorsel-not]');
      if (!p) { p = document.createElement('p'); p.setAttribute('data-gorsel-not', '1'); kutu.insertBefore(p, kutu.firstChild); }
      p.className = sinif || '';
      p.textContent = metin;
    }
    /* DEĞER ÜZERİNDE ÇALIŞIR, DOM ÜZERİNDE DEĞİL.
       İlk yazımda düzenleyicinin gövdesindeki <img> ögeleri taranıyordu
       ve ölçüldü: adım GİZLİYKEN kurulan düzenleyici değeri henüz
       gövdeye yazmıyor, dolayısıyla tarama boş dönüyordu. Sonuç, aynı
       işin iki farklı sonuç vermesiydi — kullanıcı birinci adımdan
       yürüyünce görsel data: adresiyle kalıyor, doğrudan o adıma açılan
       sayfada ise yükleniyordu. Değer bir dizedir ve her zaman
       oradadır; görünürlükten etkilenmez. */
    function gorselleriYukle(){
      if (!A.yukle || yuklemeSuruyor) return;
      var deger = (j.value || '');
      var desen = /<img\b[^>]*?src\s*=\s*"(data:[^"]+)"[^>]*>/gi;
      var bulunan = [], m;
      while ((m = desen.exec(deger)) !== null) if (bulunan.indexOf(m[1]) === -1) bulunan.push(m[1]);
      if (!bulunan.length) return;
      yuklemeSuruyor = true;
      gorselNot(A.sz.gorYuk, '');
      var oldu = 0, olmadi = 0, kalan = bulunan.length, esles = {};
      function bitti(){
        if (--kalan > 0) return;
        var yeni = (j.value || '').replace(desen, function(tam, adres){
          if (esles[adres]) return tam.replace(adres, esles[adres]);
          return '';                       /* yüklenemeyen görsel metinden çıkar */
        });
        yuklemeSuruyor = false;
        j.value = yeni; el.value = yeni;
        try { j.setEditorValue(yeni); } catch (x) {}
        if (olmadi) gorselNot(A.sz.gorHata.replace('%1', olmadi), 'kd-yapi-uyar');
        else gorselNot(A.sz.gorOk.replace('%1', oldu), 'kd-yapi-iyi');
      }
      bulunan.forEach(function(adres){
        var blob = veriyiBloba(adres);
        if (!blob) { olmadi++; bitti(); return; }
        var fd = new FormData();
        fd.append('dosya', blob, 'gorsel');
        fetch(A.yukle, {method: 'POST', body: fd, credentials: 'same-origin'})
          .then(function(r){ return r.json(); })
          .then(function(d){
            if (d && d.ok && d.yol) { esles[adres] = d.yol; oldu++; } else olmadi++;
            bitti();
          })
          .catch(function(){ olmadi++; bitti(); });
      });
    }

    /* ---- YAPI PANELİ ----
       Ölçülen olguyu yazar: kaç başlık var, içindekiler nasıl çıkıyor,
       kaynakça yerinde mi. Öğüt vermez; hiç başlık yoksa NE YAPILACAĞINI
       söyler, boş bir liste çizmez. */
    function yapiCiz(yerel){
      var kutu = document.querySelector('[data-kd-yapi="' + A.id + '"] .kd-yapi-ic');
      if (!kutu || !window.kutYapistir) return;
      var deger = j.value || '';
      var bas = window.kutYapistir.icindekiler(deger);
      var h = '';
      if (yerel) h += '<p class="kd-yapi-uyar">' + A.sz.yerel.replace('%1', yerel) + '</p>';
      if (!bas.length) {
        h += '<p class="kd-yapi-uyar">' + A.sz.yok + '</p>';
      } else {
        h += '<p>' + A.sz.basSay.replace('%1', bas.length) + '</p><ul class="kd-ic-liste">';
        for (var i = 0; i < bas.length; i++) {
          h += '<li data-d="' + bas[i].d + '">' + bas[i].ad.replace(/[<>&]/g, function(k){
            return {'<':'&lt;','>':'&gt;','&':'&amp;'}[k]; }) + '</li>';
        }
        h += '</ul>';
        h += window.kutYapistir.kaynakcaVar(deger)
           ? '<p class="kd-yapi-iyi">' + A.sz.kaynakOk + '</p>'
           : '<p class="kd-yapi-uyar">' + A.sz.kaynak + '</p>';
      }
      kutu.innerHTML = h;
    }
    if (A.tam) {
      j.events.on('change', function(){ yapiCiz(0); });
      yapiCiz(0);
      /* ---- GÖMÜLÜ GÖRSEL NEREDEN GELİRSE GELSİN YAKALANIR ----
         İlk yazımda yalnız 'change' olayına ve yapıştırmaya
         bağlanmıştı; ölçüldü ve yetmedi: değer betikle atandığında
         (kdAyarla) düzenleyici change üretmiyor ve görsel data:
         adresiyle kalıyordu. Gözlemci DOM'un kendisine bakar, olaya
         değil: düğmeyle eklenen, yapıştırılan ve betikle konan görsel
         aynı yoldan geçer. Geciktirilir; art arda gelen değişikliklerde
         tarama bir kez yapılır. */
      var gecikme = null;
      var gozle = function(){
        if (gecikme) clearTimeout(gecikme);
        gecikme = setTimeout(gorselleriYukle, 500);
      };
      try {
        new MutationObserver(gozle).observe(j.editor, {childList: true, subtree: true, attributes: true, attributeFilter: ['src']});
      } catch (e) { /* gözlemci kurulamazsa aşağıdaki iki yol yeter */ }
      /* GÖZLEMCİ TEK BAŞINA YETMEDİ, ÖLÇÜLDÜ. Adım gizliyken kurulan
         düzenleyicide gözlemci ateşlemiyor: kullanıcı birinci adımdan
         başlayıp tam metin adımına yürüdüğünde görsel data: adresiyle
         kalıyor, doğrudan o adıma açılan sayfada ise yükleniyordu. Aynı
         işin iki farklı sonuç vermesi, ölçümün değil kurulumun
         kusurudur. Bu yüzden üç yol birden bağlanır: gözlemci, değişim
         olayı ve ögenin üstünde duran doğrudan çağrı (kdAyarla onu
         çağırır). Üçü de aynı işlevi çağırır; ilk çalışan işi bitirir,
         ötekiler boş listede erken döner. */
      j.events.on('change', gozle);
      el.kdGorsel = gozle;
      gozle();
    }
    /* Sayfa metni sonradan yüklüyorsa (panelde olduğu gibi) textarea'ya
       doğrudan yazmak düzenleyiciyi güncellemez. Bu yüzden tek bir yol
       bırakılır: kdAyarla(id, deger). */
    if (el.dataset.bekleyen) { j.value = el.dataset.bekleyen; el.value = el.dataset.bekleyen; delete el.dataset.bekleyen; }
  }
  /* DEĞER ATANDIĞINDA DÜZENLEYİCİYE HABER VERİLİR.
     ÖLÇÜLEN KUSUR: kdAyarla yalnız değeri yazıyordu ve düzenleyici
     bunu bir DEĞİŞİKLİK saymıyordu. Sonuç ekranda görülüyordu:
     "Bir şeyler yaz" yer tutucusu yazının üstünde duruyor, sayaçlar
     sıfır gösteriyor, yapı paneli dolu bir metne "henüz başlık yok"
     diyordu. Üçü de aynı sebepten: 'change' hiç doğmuyordu.
     Olay elle doğurulur; olayın kendisi zaten sistemin dilidir. */
  window.kdAyarla = window.kdAyarla || function(id, deger){
    var e = document.getElementById(id); if (!e) return;
    deger = deger == null ? '' : String(deger);
    e.value = deger;
    if (!e.joditKur) { e.dataset.bekleyen = deger; return; }
    var j = e.joditKur;
    j.value = deger;
    try { j.events.fire('change', deger); } catch (x) {}
    try { j.setEditorValue(deger); } catch (x) {}
    /* Gömülü görsel taraması doğrudan tetiklenir: gizli bir adımda
       kurulmuş düzenleyicide gözlemci ateşlemiyor (yukarıda yazılı). */
    if (typeof e.kdGorsel === 'function') { try { e.kdGorsel(); } catch (x) {} }
  };
  window.kdOku = window.kdOku || function(id){
    var e = document.getElementById(id); if (!e) return '';
    return e.joditKur ? e.joditKur.value : e.value;
  };
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', kur);
  else kur();
  window.addEventListener('load', kur);
})();
</script>
JS;
    }
}
