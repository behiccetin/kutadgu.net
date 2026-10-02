/* =====================================================================
   KUTADGU · WORD'DEN YAPIŞTIRMA VE İÇİNDEKİLER
   ---------------------------------------------------------------------
   Kurul isteği: "makalesini oraya yapıştırsın, Word'den yapıştırırsa
   hemen biçimlendirmeyi al; içindekiler kısmı otomatik oluşsun; keza
   şekiller, tablolar, grafikler de öyle."

   NE ATILIR, NE KALIR — ayrım tek cümleyle: BİÇİM atılır, YAPI kalır.
   Word'ün yapıştırdığı HTML'de ikisi iç içedir. Punto, yazı tipi, renk,
   satır aralığı, girinti ve mso- ile başlayan her şey BİÇİMdir; bu
   sistemin kendi dizgesi vardır ve dışarıdan gelen bir punto onu bozar.
   Başlık düzeyi, madde listesi, çizelge, üst/alt simge ve bağlantı ise
   YAPIdır; onlar metnin anlamıdır ve atılırsa geri getirilemez.

   BAŞLIK EŞLEMESİ (şablonun sözleşmesi):
     h1 -> h2   h2 -> h3   h3 -> h4   h4/h5/h6 -> h5
   Bir basamak aşağı iner, çünkü sayfadaki <h1> ÇALIŞMANIN BAŞLIĞIdır ve
   künyeden gelir. Metnin içinden ikinci bir h1 gelseydi belge iki
   başlıklı olurdu; ekran okuyucu için bu, iki ayrı belge demektir.

   GÖRSELLER. Word'den gelen görselin adresi çoğu zaman "file:///C:/..."
   olur: o adres yalnız yazarın kendi bilgisayarında vardır. Böyle bir
   görseli kaydetmek, sayfada kırık bir resim bırakmaktır. Bu yüzden
   yerel adresli görsel ATILIR ve yerine yazara ne yapacağını söyleyen
   bir not konur. data: ile gömülü görsel KALIR — o gerçekten oradadır.

   İÇİNDEKİLER YAZARDAN İSTENMEZ. Başlıklardan üretilir. Word'ün kendi
   içindekiler alanı yapıştırıldığında donmuş bir liste hâline gelir ve
   yazar başlığını değiştirdiğinde sessizce yanlış kalır; bu yüzden
   böyle bir liste geldiyse ATILIR.
   ===================================================================== */
(function (kok) {
  'use strict';

  /* Yapı taşıyan etiketler. Listede olmayan her etiket AÇILIR: içeriği
     kalır, kabı gider. Silmek yerine açmak önemlidir — bir <span> silmek
     içindeki cümleyi de götürürdü. */
  var KALSIN = {
    P:1, BR:1, H1:1, H2:1, H3:1, H4:1, H5:1, H6:1,
    STRONG:1, EM:1, U:1, SUP:1, SUB:1, S:1,
    UL:1, OL:1, LI:1, BLOCKQUOTE:1, HR:1,
    TABLE:1, THEAD:1, TBODY:1, TFOOT:1, TR:1, TH:1, TD:1,
    A:1, IMG:1, FIGURE:1, FIGCAPTION:1, CAPTION:1, CODE:1, PRE:1
  };
  /* Tümüyle silinecekler: içeriği de gitmeli. */
  var SIL = { SCRIPT:1, STYLE:1, META:1, LINK:1, TITLE:1, HEAD:1, OBJECT:1, EMBED:1, IFRAME:1, FORM:1, INPUT:1, BUTTON:1, SELECT:1, TEXTAREA:1, COLGROUP:1, COL:1 };
  /* Eşdeğerleri: aynı anlamın iki yazılışı tek yazılışa iner. */
  var ESLE = { B:'STRONG', I:'EM', STRIKE:'S', DEL:'S', BIG:'STRONG', FONT:null, SPAN:null, DIV:'P', SECTION:'P', ARTICLE:'P', CENTER:'P' };
  /* Başlık bir basamak iner; gerekçe dosyanın başında. */
  var BASLIK = { H1:'H2', H2:'H3', H3:'H4', H4:'H5', H5:'H5', H6:'H5' };
  var IZIN = { A:['href'], IMG:['src','alt'], TD:['colspan','rowspan'], TH:['colspan','rowspan','scope'] };

  function bosMu(e) {
    if (e.nodeName === 'IMG' || e.nodeName === 'HR' || e.nodeName === 'BR') return false;
    if (e.querySelector && e.querySelector('img,table,hr')) return false;
    return !(e.textContent || '').replace(/\u00a0/g, ' ').trim();
  }

  /* Word'ün "Başlık 1" stilini <p class=MsoHeading1> ya da
     <p class="Baslik1"> diye yapıştırdığı hâller var. Gerçek başlık
     etiketi gelmediğinde sınıf adından okunur; okunamıyorsa paragraf
     kalır — uydurmak, yanlış bir içindekiler üretmekten kötüdür. */
  function sinifBaslik(e) {
    var c = (e.getAttribute && e.getAttribute('class')) || '';
    var m = /(?:mso)?(?:heading|baslik|başlık|title)\s*([1-6])/i.exec(c.replace(/[-_]/g, ''));
    return m ? ('H' + m[1]) : '';
  }

  function nitelikTemizle(e) {
    var izin = IZIN[e.nodeName] || [];
    for (var i = e.attributes.length - 1; i >= 0; i--) {
      var ad = e.attributes[i].name;
      if (izin.indexOf(ad) === -1) e.removeAttribute(ad);
    }
    if (e.nodeName === 'A') {
      var h = (e.getAttribute('href') || '').trim();
      /* javascript: ve veri adresleri bağlantı olamaz. Sunucu da süzer;
         bu ikinci kat, birinci kat değil. */
      if (!h || /^\s*(javascript|vbscript|data):/i.test(h)) { e.removeAttribute('href'); }
    }
    if (e.nodeName === 'IMG') {
      var s = (e.getAttribute('src') || '').trim();
      if (!/^(https?:|data:image\/)/i.test(s)) e.setAttribute('data-yerel', '1');
    }
  }

  function gez(dugum, cikti) {
    var c = dugum.firstChild;
    while (c) {
      var sonraki = c.nextSibling;
      if (c.nodeType === 8) { dugum.removeChild(c); c = sonraki; continue; }   /* yorum */
      if (c.nodeType === 1) {
        var ad = c.nodeName;
        if (SIL[ad]) { dugum.removeChild(c); c = sonraki; continue; }
        gez(c, cikti);
        var hedef = BASLIK[ad] || (ESLE.hasOwnProperty(ad) ? ESLE[ad] : (KALSIN[ad] ? ad : null));
        if (!BASLIK[ad] && (ad === 'P' || ad === 'DIV')) {
          var sb = sinifBaslik(c);
          if (sb) hedef = BASLIK[sb];
        }
        if (hedef === null) {                       /* kabı aç, içi kalsın */
          while (c.firstChild) dugum.insertBefore(c.firstChild, c);
          dugum.removeChild(c);
        } else if (hedef !== ad) {
          var y = c.ownerDocument.createElement(hedef);
          while (c.firstChild) y.appendChild(c.firstChild);
          dugum.replaceChild(y, c);
          nitelikTemizle(y);
          if (bosMu(y)) dugum.removeChild(y); else if (y.nodeName === 'IMG' || y.getAttribute('data-yerel')) cikti.yerel++;
        } else {
          nitelikTemizle(c);
          if (c.nodeName === 'IMG' && c.getAttribute('data-yerel')) { cikti.yerel++; dugum.removeChild(c); }
          else if (bosMu(c) && c.nodeName !== 'IMG' && c.nodeName !== 'HR' && c.nodeName !== 'BR' && c.nodeName !== 'TD' && c.nodeName !== 'TH') dugum.removeChild(c);
        }
      }
      c = sonraki;
    }
  }

  /* Word'ün yapıştırdığı içindekiler listesi: ardışık bağlantılardan
     oluşan, hepsi #_Toc ile başlayan bir yığın. Donmuş olduğu için
     atılır; yerine sistem kendi listesini üretir. */
  function tocAt(kap) {
    var a = kap.querySelectorAll('a[href^="#_Toc"], a[name^="_Toc"]');
    for (var i = 0; i < a.length; i++) {
      var p = a[i].closest ? a[i].closest('p,li') : null;
      if (p && p.parentNode) p.parentNode.removeChild(p); else if (a[i].parentNode) a[i].parentNode.removeChild(a[i]);
    }
  }

  function temizle(html) {
    var d = document.implementation.createHTMLDocument('');
    d.body.innerHTML = String(html == null ? '' : html);
    var say = { yerel: 0 };
    tocAt(d.body);
    gez(d.body, say);
    /* Art arda gelen boş paragraflar: Word bunlardan bolca üretir. */
    var p = d.body.querySelectorAll('p');
    for (var i = 0; i < p.length; i++) if (bosMu(p[i])) p[i].parentNode.removeChild(p[i]);
    return { html: d.body.innerHTML.trim(), yerelGorsel: say.yerel };
  }

  /* İçindekiler: h2..h5'ten üretilir. Boş liste dönerse ÇAĞIRAN karar
     verir; bu işlev sessizce bir şey uydurmaz. */
  function icindekiler(html) {
    var d = document.implementation.createHTMLDocument('');
    d.body.innerHTML = String(html == null ? '' : html);
    var b = d.body.querySelectorAll('h2,h3,h4,h5'), out = [];
    for (var i = 0; i < b.length; i++) {
      var t = (b[i].textContent || '').trim();
      if (t) out.push({ d: parseInt(b[i].nodeName.slice(1), 10), ad: t });
    }
    return out;
  }

  /* Kaynakça başlığı var mı? Sekiz dilde aranır; başlık metni
     yazarındır, biçimi bizim değil. */
  var KAYNAK = /^(kaynak(ça|lar|ca)?|references?|bibliograph(y|ie)|literatur|bibliografía|bibliographie|источники|литература|المراجع)\s*$/i;
  function kaynakcaVar(html) {
    var b = icindekiler(html);
    for (var i = 0; i < b.length; i++) if (KAYNAK.test(b[i].ad.replace(/^\d+[.\s]*/, '').trim())) return true;
    return false;
  }

  kok.kutYapistir = { temizle: temizle, icindekiler: icindekiler, kaynakcaVar: kaynakcaVar };
})(window);
