/* =====================================================================
   KUTADGU - Ortak arayüz betiği / Shared UI script
   Tema, dil, gezinme, uygulama kabuğu. Dış bağımlılık yoktur.
   ===================================================================== */
(function () {
  'use strict';

  var D = document, W = window;

  /* ---------- Tema ---------- */
  var TEMA_ANAHTAR = 'kutadgu-tema';
  function temaOku() {
    try { var t = localStorage.getItem(TEMA_ANAHTAR); if (t === 'koyu' || t === 'acik') return t; } catch (e) {}
    return W.matchMedia && W.matchMedia('(prefers-color-scheme: dark)').matches ? 'koyu' : 'acik';
  }
  function temaYaz(t) {
    D.documentElement.setAttribute('data-tema', t);
    try { localStorage.setItem(TEMA_ANAHTAR, t); } catch (e) {}
    var m = D.querySelector('meta[name="theme-color"]');
    if (m) m.setAttribute('content', t === 'koyu' ? '#111826' : '#1b2a4a');
    Array.prototype.forEach.call(D.querySelectorAll('[data-tema-dg]'), function (b) {
      b.setAttribute('aria-pressed', t === 'koyu' ? 'true' : 'false');
      var s = b.querySelector('[data-tema-sim]');
      if (s) s.textContent = t === 'koyu' ? '☀' : '☽';
    });
  }
  temaYaz(temaOku());

  /* ---------- Dil ---------- */
  /* Sayfalar sunucuda üretilir; dil ?lang= ile taşınır, çerezle hatırlanır. */
  function dilKur(d) {
    /* Dil kodu sunucudan gelen listeye göre serbesttir; yeni bir dil
       eklendiğinde bu betiği değiştirmek gerekmez. */
    d = String(d || '').toLowerCase().slice(0, 2);
    if (!/^[a-z]{2}$/.test(d)) return;
    try { localStorage.setItem('kutadgu-dil', d); } catch (e) {}
    D.cookie = 'kdil=' + d + ';path=/;max-age=31536000;samesite=lax';
    var u = new URL(W.location.href);
    u.searchParams.set('lang', d);
    W.location.href = u.toString();
  }

  /* ---------- Bağlama ---------- */
  function bagla() {
    /* tema düğmesi */
    Array.prototype.forEach.call(D.querySelectorAll('[data-tema-dg]'), function (b) {
      b.addEventListener('click', function () {
        temaYaz(D.documentElement.getAttribute('data-tema') === 'koyu' ? 'acik' : 'koyu');
      });
    });

    /* Dil değiştirici artık bir BAĞLANTIDIR: betik yokken de çalışır.
       Betik varken tıklamayı yakalar, seçimi çereze yazar ve sayfayı
       öyle yeniler; böylece seçim sonraki sayfalarda da sürer. Yeni
       sekmede açmak (Ctrl/Cmd, orta düğme) engellenmez: bağlantının
       bağlantı gibi davranması beklenir. */
    Array.prototype.forEach.call(D.querySelectorAll('[data-dil]'), function (b) {
      b.addEventListener('click', function (e) {
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.button === 1) return;
        e.preventDefault();
        dilKur(b.getAttribute('data-dil'));
      });
    });

    /* Dil penceresi <details> ile açılır: açmak tarayıcının işidir ve
       betiksiz de çalışır. Betiğin eklediği tek şey KAPANMAdır — Esc
       ve dışarı tıklama. Açılan bir pencerenin kapanmaması bir kusur
       değil, bir rahatsızlıktır; bu yüzden burada durur, kabukta
       değil. */
    var dSec = D.querySelector('[data-dil-sec]');
    if (dSec) {
      D.addEventListener('click', function (e) {
        if (dSec.open && !dSec.contains(e.target)) dSec.open = false;
      });
      D.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && dSec.open) {
          dSec.open = false;
          var oz = dSec.querySelector('summary'); if (oz) oz.focus();
        }
      });
    }

    /* Dar ekranda sol sütun bir çekmece gibi açılır */
    var dg = D.querySelector('[data-gez-dg]'),
        yan = D.getElementById('yan'),
        perde = D.querySelector('[data-gez-kapat]');
    function yanAyar(ac) {
      if (!yan) return;
      yan.setAttribute('data-acik', ac ? '1' : '0');
      if (dg) dg.setAttribute('aria-expanded', ac ? 'true' : 'false');
      if (perde) { if (ac) perde.removeAttribute('hidden'); else perde.setAttribute('hidden', ''); }
      D.body.style.overflow = ac ? 'hidden' : '';
    }
    if (dg && yan) {
      dg.addEventListener('click', function () { yanAyar(yan.getAttribute('data-acik') !== '1'); });
      yan.addEventListener('click', function (e) { if (e.target.closest('a')) yanAyar(false); });
      if (perde) perde.addEventListener('click', function () { yanAyar(false); });
      D.addEventListener('keydown', function (e) { if (e.key === 'Escape') yanAyar(false); });
      W.addEventListener('resize', function () { if (W.innerWidth > 1040) yanAyar(false); });
    }

    /* geçerli sayfayı işaretle */
    var yol = W.location.pathname.replace(/\/$/, '') || '/';
    Array.prototype.forEach.call(D.querySelectorAll('.yan .gez a[href]'), function (a) {
      var h = a.getAttribute('href') || '';
      if (h.charAt(0) !== '#' && h.replace(/\?.*$/, '').replace(/\/$/, '') === yol) a.setAttribute('aria-current', 'page');
    });

    profilBagla();
    if (!belgeSayfala()) icindekiler();
    sekmeler();
  }

  /* ---------- Sekmeler ----------
     Bölmeler sunucudan açık gelir; betik burada yalnızca birini bırakıp
     ötekileri kapatır. Böylece betik çalışmazsa sayfa eskisi gibi alt
     alta okunur, hiçbir içerik kaybolmaz. Ok tuşlarıyla da gezilir. */
  function sekmeler() {
    Array.prototype.forEach.call(D.querySelectorAll('[data-sek]'), function (kok) {
      var dgler = Array.prototype.slice.call(kok.querySelectorAll('[role="tab"]'));
      if (dgler.length < 2) return;
      var pnl = function (d) { return D.getElementById(d.getAttribute('aria-controls')); };
      function ac(i, odak) {
        dgler.forEach(function (d, j) {
          var s = i === j;
          d.setAttribute('aria-selected', s ? 'true' : 'false');
          d.setAttribute('tabindex', s ? '0' : '-1');
          var p = pnl(d);
          if (p) { if (s) p.removeAttribute('hidden'); else p.setAttribute('hidden', ''); }
        });
        if (odak) dgler[i].focus();
      }
      dgler.forEach(function (d, i) {
        d.addEventListener('click', function () { ac(i); });
        d.addEventListener('keydown', function (e) {
          var n = -1;
          if (e.key === 'ArrowRight') n = (i + 1) % dgler.length;
          if (e.key === 'ArrowLeft')  n = (i - 1 + dgler.length) % dgler.length;
          if (e.key === 'Home') n = 0;
          if (e.key === 'End')  n = dgler.length - 1;
          if (n >= 0) { e.preventDefault(); ac(n, true); }
        });
      });
      var bas = dgler.findIndex ? dgler.findIndex(function (d) { return d.getAttribute('aria-selected') === 'true'; }) : 0;
      ac(bas < 0 ? 0 : bas);
    });
  }

  /* ---------- İçindekiler listesi ----------
     .blg-nav içindeki bağlantılar sayfa kaydırıldıkça kendiliğinden
     işaretlenir. Gözlemci desteklenmiyorsa liste yine çalışır, yalnızca
     hangi bölümde olduğumuz vurgulanmaz. */
  function icindekiler() {
    var nav = D.querySelector('.blg-nav');
    if (!nav || !W.IntersectionObserver) return;
    var baglar = {}, hedefler = [];
    Array.prototype.forEach.call(nav.querySelectorAll('a[href^="#"]'), function (a) {
      var k = a.getAttribute('href').slice(1), h = k && D.getElementById(k);
      if (h) { baglar[k] = a; hedefler.push(h); }
    });
    if (!hedefler.length) return;
    var etkin = null;
    var g = new IntersectionObserver(function (girisler) {
      girisler.forEach(function (gi) {
        if (!gi.isIntersecting) return;
        var k = gi.target.id;
        if (etkin === k) return;
        if (etkin && baglar[etkin]) baglar[etkin].removeAttribute('aria-current');
        etkin = k;
        if (baglar[k]) baglar[k].setAttribute('aria-current', 'true');
      });
    }, { rootMargin: '-15% 0px -70% 0px', threshold: 0 });
    hedefler.forEach(function (h) { g.observe(h); });
  }


  /* ---------- Uzun belgeleri bölüm bölüm gösterir ----------
     Yayın ilkeleri gibi on altı bölümlük bir belge tek bir kaydırmaya
     sığmaz: okuyucu nerede olduğunu kaybeder, sayfa boyuna uzar ve
     içindekiler listesi yalnızca aşağı atlatır. Burada belge, bir
     bölümü bir ekran olacak biçimde gösterilir; içindekiler bir liste
     değil bir anahtar olur ve altta önceki/sonraki düğmeleri durur.

     SUNUCU BÜTÜN BÖLÜMLERİ AÇIK GÖNDERİR. Betik yalnızca birini
     bırakır. Böylece arama motoru, yazdırma, tarayıcı içi arama ve
     betiği kapalı bir tarayıcı bugünkü davranışı aynen görür; hiçbiri
     bu iyileştirme yüzünden bozulmaz. "Hepsini göster" düğmesi de
     istendiğinde eski görünüşe döndürür.

     Dönen: sayfalama kuruldu mu. Kurulduysa içindekiler vurgulayıcısı
     çalıştırılmaz, çünkü ekranda tek bölüm vardır. */
  function belgeSayfala() {
    var kok = D.querySelector('[data-belge]');
    if (!kok) return false;
    var govde = kok.querySelector('[data-belge-govde]');
    var nav   = kok.querySelector('[data-belge-nav]');
    if (!govde || !nav) return false;

    var bolumler = Array.prototype.filter.call(govde.children, function (e) {
      return e.tagName === 'SECTION' && e.id;
    });
    if (bolumler.length < 3) return false;

    var baglar = {};
    Array.prototype.forEach.call(nav.querySelectorAll('a[href^="#"]'), function (a) {
      baglar[a.getAttribute('href').slice(1)] = a;
    });

    var EN = (D.documentElement.getAttribute('lang') || 'tr').indexOf('en') === 0;
    var S = {
      hepsi:   EN ? 'Show all sections' : 'Bütün bölümleri göster',
      tek:     EN ? 'Show one section at a time' : 'Tek bölüm göster',
      onceki:  EN ? 'Previous' : 'Önceki bölüm',
      sonraki: EN ? 'Next' : 'Sonraki bölüm'
    };

    /* Hepsini göster anahtarı */
    /* =================================================================
       DÜĞME YARATILDIĞI YERDE GİYDİRİLİR
       -----------------------------------------------------------------
       ÖLÇÜLDÜ (wcag-kapi, 1.4.3): ilkeler sayfası koyu temada
       "Bütün bölümleri göster" düğmesini 4.17 karşıtlıkla basıyordu;
       AA eşiği 4.5. Sebep renk seçimi değildi: DÜĞMENİN HİÇ BİÇİMİ
       YOKTU. Burada yalnız 'blg-hepsi' yazılıyor, düğme biçimini veren
       'd d-ikinci' sınıflarını ise ilkeler.php kendi betiğiyle SONRADAN
       ekliyordu. O ana kadar düğme tarayıcının kendi düğmesidir ve koyu
       renk şemasında zemini gri (#6b6b6b) olur; üstündeki açık metinle
       birlikte AA'nın altına düşer.

       İki kusur birden vardı:
         1. Zamanlama. Sınıflar sonradan eklendiği için düğme bir süre
            biçimsiz duruyor, .d'nin 0.18 saniyelik zemin geçişi de o
            tarayıcı gri'sinden başlıyordu. Ölçüm tam o aralığa denk
            geldi; yani kusur ekranda da vardı, ölçüm uydurmadı.
         2. Tek kaynak. Düğmeyi BURASI üretiyor ama biçimini bir SAYFA
            veriyordu. Bugün 'data-belge' görünümünü kullanan tek sayfa
            ilkeler.php; ikincisi eklendiği gün düğmeleri sessizce
            biçimsiz çıkacaktı ve kimse aramayacaktı.

       Sınıflar artık üretildikleri satırda veriliyor; ilkeler.php'deki
       sonradan giydirme kaldırıldı. */
    var hepsiDg = D.createElement('button');
    hepsiDg.type = 'button';
    hepsiDg.className = 'blg-hepsi d d-ikinci';
    hepsiDg.textContent = S.hepsi;
    nav.appendChild(hepsiDg);

    /* Alt gezinme: her bölümün sonuna bir kez eklenir */
    var altlar = bolumler.map(function (b, i) {
      var alt = D.createElement('div');
      alt.className = 'blg-gec';
      if (i > 0) {
        var o = D.createElement('button');
        o.type = 'button'; o.className = 'blg-gec-dg d d-ikinci d-kucuk';
        o.textContent = '\u2190 ' + S.onceki;
        o.addEventListener('click', function () { git(i - 1, true); });
        alt.appendChild(o);
      }
      if (i < bolumler.length - 1) {
        var n = D.createElement('button');
        n.type = 'button'; n.className = 'blg-gec-dg blg-gec-ileri d d-ikinci d-kucuk';
        n.textContent = S.sonraki + ' \u2192';
        n.addEventListener('click', function () { git(i + 1, true); });
        alt.appendChild(n);
      }
      b.appendChild(alt);
      return alt;
    });

    var acik = 0, hepsiMi = false;

    function ciz() {
      bolumler.forEach(function (b, i) {
        var g = hepsiMi || i === acik;
        b.hidden = !g;
        if (altlar[i]) altlar[i].hidden = hepsiMi;
        var a = baglar[b.id];
        if (a) {
          if (!hepsiMi && i === acik) a.setAttribute('aria-current', 'true');
          else a.removeAttribute('aria-current');
        }
      });
      hepsiDg.textContent = hepsiMi ? S.tek : S.hepsi;
      hepsiDg.setAttribute('aria-pressed', hepsiMi ? 'true' : 'false');
    }

    function git(i, kaydir) {
      if (i < 0 || i >= bolumler.length) return;
      acik = i; hepsiMi = false; ciz();
      if (kaydir) {
        var ust = kok.getBoundingClientRect().top + W.pageYOffset - 90;
        W.scrollTo({ top: ust < 0 ? 0 : ust, behavior: 'smooth' });
      }
      if (W.history && W.history.replaceState) {
        W.history.replaceState(null, '', '#' + bolumler[i].id);
      }
    }

    Object.keys(baglar).forEach(function (id) {
      var i = bolumler.findIndex ? bolumler.findIndex(function (b) { return b.id === id; }) : -1;
      if (i < 0) return;
      baglar[id].addEventListener('click', function (e) { e.preventDefault(); git(i, true); });
    });

    hepsiDg.addEventListener('click', function () { hepsiMi = !hepsiMi; ciz(); });

    /* Adresteki çapa: hem ilk açılışta hem sonradan değişirse */
    function capa() {
      var h = (W.location.hash || '').slice(1);
      if (!h) return false;
      for (var i = 0; i < bolumler.length; i++) {
        if (bolumler[i].id === h) { acik = i; hepsiMi = false; ciz(); return true; }
      }
      return false;
    }
    W.addEventListener('hashchange', function () { if (capa()) { } });

    /* Yazdırırken ve tarayıcı içi aramada bütün belge görünür olmalı */
    if (W.matchMedia) {
      var yz = W.matchMedia('print');
      var yzDinle = function (e) { if (e.matches) { bolumler.forEach(function (b) { b.hidden = false; }); } else { ciz(); } };
      if (yz.addEventListener) yz.addEventListener('change', yzDinle);
    }
    W.addEventListener('beforeprint', function () { bolumler.forEach(function (b) { b.hidden = false; }); });
    W.addEventListener('afterprint', function () { ciz(); });

    kok.classList.add('blg-sayfali');
    if (!capa()) ciz();
    return true;
  }

  /* ---------- Sağ üstteki profil dairesi ----------
     Genel sayfalarda sunucu oturumu açmaz; hesabı burada, sayfa
     yerleştikten sonra soruyoruz. Cevap oturum süresince saklanır ki
     her sayfa geçişinde yeniden sorulmasın. */
  var HS_ANAHTAR = 'kutadgu-hesap-durum';
  function profilCiz(d) {
    var kok = D.querySelector('[data-hs]');
    if (!kok || !d) return;
    var bas = kok.querySelector('[data-hs-bas]'),
        roz = kok.querySelector('[data-hs-roz]'),
        say = kok.querySelector('[data-hs-say]'),
        kim = kok.querySelector('[data-hs-kim]'),
        cik = kok.querySelector('[data-hs-cikis]'),
        ozel = kok.querySelector('[data-hs-ozel]'),
        bag = kok.querySelector('[data-hs-bag]'),
        dg = kok.querySelector('[data-hs-dg]'),
        gir = kok.querySelector('[data-hs-giris]');
    if (!d.girisli) {
      /* Girmemiş ziyaretçi: bağlantı kalır, menü düğmesi hiç görünmez.
         Açılacak bir menü yok; tek satırlık menü, uzatılmış bir
         tıklamadan başka bir şey değildir. */
      if (cik) cik.setAttribute('hidden', '');
      if (gir) gir.removeAttribute('hidden');
      if (ozel) ozel.setAttribute('hidden', '');
      if (bag) bag.removeAttribute('hidden');
      if (dg) dg.setAttribute('hidden', '');
      return;
    }
    /* Girmiş kişi: bağlantı kalkar, yerine menü düğmesi gelir. */
    if (bag) bag.setAttribute('hidden', '');
    if (dg) dg.removeAttribute('hidden');
    if (gir) gir.setAttribute('hidden', '');
    if (ozel) ozel.removeAttribute('hidden');
    /* Profil resmi varsa daireyi o kaplar; yoksa baş harfler kalır. */
    if (bas) {
      if (d.resim) { bas.innerHTML = ''; var im = new Image(); im.className = 'yuz';
                     im.src = d.resim; im.alt = ''; im.width = 38; im.height = 38; bas.appendChild(im); }
      else if (d.bas_harf) bas.textContent = d.bas_harf;
    }
    if (kim) {
      kim.removeAttribute('hidden');
      var a = kim.querySelector('[data-hs-kim-bas]'),
          b = kim.querySelector('[data-hs-kim-ad]'),
          c = kim.querySelector('[data-hs-kim-rol]');
      if (a) {
        if (d.resim) { a.innerHTML = ''; var im2 = new Image(); im2.className = 'yuz';
                       im2.src = d.resim; im2.alt = ''; im2.width = 38; im2.height = 38; a.appendChild(im2); }
        else a.textContent = d.bas_harf || '';
      }
      if (b) b.textContent = d.ad || '';
      if (c) c.textContent = d.rol || '';
    }
    if (cik) cik.removeAttribute('hidden');
    /* Bekleyen iş ile listenizdeki bir çalışmada olan değişiklik aynı
       şey değildir; ikisi de rozette sayılır ama menüde ayrı yazılır. */
    var bek = parseInt(d.bekleyen, 10) || 0;
    var hab = parseInt(d.haber, 10) || 0;
    var n = bek + hab;
    var hb = kok.querySelector('[data-hs-haber]');
    if (hb) {
      if (hab > 0) {
        hb.textContent = hab > 9 ? '9+' : String(hab);
        hb.removeAttribute('hidden');
      } else { hb.setAttribute('hidden', ''); }
    }
    if (n > 0) {
      if (roz) { roz.textContent = n > 9 ? '9+' : String(n); roz.removeAttribute('hidden'); }
      if (say) { say.textContent = String(bek); if (bek > 0) say.removeAttribute('hidden'); }
      var dg = kok.querySelector('[data-hs-dg]');
      if (dg) dg.setAttribute('aria-label', (D.documentElement.lang === 'en'
        ? 'My account, ' + n + ' item(s) awaiting you'
        : 'Hesabım, sizi bekleyen ' + n + ' iş var'));
    }
  }
  function profilBagla() {
    var kok = D.querySelector('[data-hs]');
    if (!kok) return;
    var dg = kok.querySelector('[data-hs-dg]'), menu = kok.querySelector('[data-hs-menu]');
    if (dg && menu) {
      dg.addEventListener('click', function (e) {
        e.stopPropagation();
        var k = menu.hasAttribute('hidden');
        if (k) menu.removeAttribute('hidden'); else menu.setAttribute('hidden', '');
        dg.setAttribute('aria-expanded', k ? 'true' : 'false');
      });
      D.addEventListener('click', function (e) {
        if (kok.contains(e.target)) return;
        menu.setAttribute('hidden', ''); dg.setAttribute('aria-expanded', 'false');
      });
      D.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { menu.setAttribute('hidden', ''); dg.setAttribute('aria-expanded', 'false'); }
      });
    }
    var cik = kok.querySelector('[data-hs-cikis]');
    if (cik) cik.addEventListener('click', function () {
      try { sessionStorage.removeItem(HS_ANAHTAR); } catch (e) {}
      fetch('/api/hesap/cikis', { method: 'POST', credentials: 'same-origin' })
        .then(function () { W.location.href = '/'; })
        .catch(function () { W.location.href = '/'; });
    });

    /* Önce saklanan cevabı çiz, sonra sessizce tazele */
    var eski = null;
    try { eski = JSON.parse(sessionStorage.getItem(HS_ANAHTAR) || 'null'); } catch (e) {}
    if (eski) profilCiz(eski);
    var iste = function () {
      fetch('/api/hesap/durum', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || !d.ok) return;
          try { sessionStorage.setItem(HS_ANAHTAR, JSON.stringify(d)); } catch (e) {}
          profilCiz(d);
        })
        .catch(function () {});
    };
    if (W.requestIdleCallback) W.requestIdleCallback(iste, { timeout: 2500 }); else setTimeout(iste, 700);

    /* İŞ BİTİNCE BİLDİRİM DÜŞER.
       Cevap oturum boyunca saklanıyor ve sayfa yeniden yüklenmedikçe
       tazelenmiyordu. Panelde bir iş bitirildiğinde (belge onaylandı,
       oy verildi, rapor gönderildi) kart listesi hemen güncelleniyor
       ama sağ üstteki daire eski sayıyı göstermeyi sürdürüyordu: kişi
       işi yapıyor, bildirim duruyor. Panel bunu iş biter bitmez çağırır;
       saklanan cevap atılır ve sayı yeniden sorulur. */
    W.kutadguHesapTazele = function () {
      try { sessionStorage.removeItem(HS_ANAHTAR); } catch (e) {}
      iste();
    };
  }


  /* ---------- İlk ziyaret: bildiri ---------- */
  /* Tam sayfa okunur, "Devam et" denir ve bir daha gösterilmez.
     Arama motorlarını etkilemez: adres değişmez, içerik sayfanın üstüne
     bir katman olarak eklenir ve yalnızca gerçek ziyaretçiye gösterilir. */
  var BLD = 'kutadgu-bildiri';
  function bildiriGoster() {
    try { if (localStorage.getItem(BLD)) return; } catch (e) { return; }
    if (/bildiri\.php/.test(location.pathname)) { try { localStorage.setItem(BLD, '1'); } catch (e) {} return; }
    if (navigator.webdriver) return;
    var dil = (D.documentElement.lang || 'tr').slice(0, 2);
    fetch('/bildiri.php?parca=1&lang=' + dil, { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.text() : null; })
      .then(function (h) {
        if (!h) return;
        var k = D.createElement('div');
        k.className = 'bld-kat';
        k.setAttribute('role', 'dialog');
        k.setAttribute('aria-modal', 'true');
        k.innerHTML = '<div class="bld-kat-ic">' + h + '</div>';

        var s = D.createElement('div');
        s.className = 'bld-serit';
        s.innerHTML =
          '<div class="bld-serit-ic">' +
          '<img class="bld-mark" src="/k/tamga.svg" alt="" width="34" height="34">' +
          '<p>' + (dil !== 'tr'
            ? 'You are seeing this once. It stays available in the footer whenever you want to read it again.'
            : 'Bunu bir kez görüyorsunuz. Yeniden okumak isterseniz alt bilgide durmayı sürdürecek.') + '</p>' +
          '<button class="d d-vurgu" type="button" data-bld-kapat>' +
          (dil !== 'tr' ? 'Continue' : 'Devam et') + '</button></div>';

        D.body.appendChild(k);
        D.body.appendChild(s);
        D.body.style.overflow = 'hidden';

        function kapat() {
          try { localStorage.setItem(BLD, '1'); } catch (e) {}
          k.remove(); s.remove();
          D.body.style.overflow = '';
        }
        s.querySelector('[data-bld-kapat]').addEventListener('click', kapat);
        D.addEventListener('keydown', function esc(e) {
          if (e.key === 'Escape') { kapat(); D.removeEventListener('keydown', esc); }
        });
        k.focus();
      })
      .catch(function () {});
  }

  if (D.readyState === 'loading') D.addEventListener('DOMContentLoaded', function(){bagla();bildiriGoster();});
  else { bagla(); bildiriGoster(); }

  /* ---------- Uygulama kabuğu (PWA) ---------- */
  if ('serviceWorker' in navigator && location.protocol === 'https:') {
    W.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }

  /* Kurulum istemi: tarayıcı hazır olduğunda yakalanır, sayfa isteyince açılır */
  var kurulumIstem = null;
  W.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    kurulumIstem = e;
    D.documentElement.setAttribute('data-kurulabilir', '1');
    W.dispatchEvent(new CustomEvent('kutadgu-kurulabilir'));
  });
  W.addEventListener('appinstalled', function () {
    kurulumIstem = null;
    D.documentElement.setAttribute('data-kurulabilir', '0');
    W.dispatchEvent(new CustomEvent('kutadgu-kuruldu'));
  });

  /* Uygulama olarak mı açıldı? */
  function uygulamaMi() {
    return (W.matchMedia && W.matchMedia('(display-mode: standalone)').matches) ||
           W.navigator.standalone === true;
  }

  /* Çevrimdışı okumak üzere bir adresi sakla */
  function sakla(adres) {
    return new Promise(function (coz, at) {
      if (!('serviceWorker' in navigator) || !navigator.serviceWorker.controller) return at(new Error('yok'));
      function dinle(e) {
        var d = e.data || {};
        if (d.adres !== adres) return;
        navigator.serviceWorker.removeEventListener('message', dinle);
        d.tur === 'saklandi' ? coz() : at(new Error('saklanamadi'));
      }
      navigator.serviceWorker.addEventListener('message', dinle);
      navigator.serviceWorker.controller.postMessage({ tur: 'sakla', adres: adres });
      setTimeout(function () { navigator.serviceWorker.removeEventListener('message', dinle); at(new Error('zaman')); }, 12000);
    });
  }

  /* ---------- Sesle yazma ----------
     Tarayıcının konuşma tanıma arayüzü. Desteklenmiyorsa sessizce yok sayılır;
     hiçbir ses kaydı sunucuya gönderilmez, tanıma cihazda/tarayıcıda yapılır. */
  function sesDestek() {
    return !!(W.SpeechRecognition || W.webkitSpeechRecognition);
  }
  function sesYazici(hedef, secenek) {
    secenek = secenek || {};
    var SR = W.SpeechRecognition || W.webkitSpeechRecognition;
    if (!SR) return null;
    var t = new SR();
    t.lang = secenek.dil || (D.documentElement.lang === 'en' ? 'en-US' : 'tr-TR');
    t.continuous = true;
    t.interimResults = true;
    var taban = '';
    var calisiyor = false;

    t.onresult = function (e) {
      var kesin = '', gecici = '';
      for (var i = e.resultIndex; i < e.results.length; i++) {
        var m = e.results[i][0].transcript;
        if (e.results[i].isFinal) kesin += m; else gecici += m;
      }
      if (kesin) taban = (taban ? taban.replace(/\s*$/, ' ') : '') + kesin.replace(/^\s+/, '');
      hedef.value = taban + (gecici ? (taban ? ' ' : '') + gecici : '');
      hedef.dispatchEvent(new Event('input', { bubbles: true }));
    };
    t.onerror = function (e) { if (secenek.hata) secenek.hata(e.error); };
    t.onend = function () {
      if (calisiyor) { try { t.start(); } catch (x) { calisiyor = false; } }
      if (!calisiyor && secenek.bitti) secenek.bitti();
    };
    return {
      basla: function () {
        taban = (hedef.value || '').replace(/\s*$/, '');
        calisiyor = true;
        try { t.start(); } catch (x) {}
        if (secenek.basladi) secenek.basladi();
      },
      dur: function () { calisiyor = false; try { t.stop(); } catch (x) {} },
      calisiyor: function () { return calisiyor; }
    };
  }

  /* ---------- Küçük yardımcılar ---------- */
  W.K = {
    tema: temaYaz,
    dil: dilKur,
    /* PWA */
    kurulabilir: function () { return !!kurulumIstem; },
    kur: function () {
      if (!kurulumIstem) return Promise.reject(new Error('hazir-degil'));
      var p = kurulumIstem; kurulumIstem = null;
      p.prompt();
      return p.userChoice;
    },
    uygulamaMi: uygulamaMi,
    sakla: sakla,
    /* Sesle yazma */
    sesDestek: sesDestek,
    sesYazici: sesYazici,
    /* Basit istek yardımcısı */
    iste: function (yol, gonderi) {
      var s = { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' };
      if (gonderi !== undefined) {
        s.method = 'POST';
        s.headers['Content-Type'] = 'application/json';
        s.body = JSON.stringify(gonderi);
      }
      return fetch(yol, s).then(function (r) { return r.json(); });
    },
    /* Metni panoya kopyala */
    kopyala: function (metin) {
      if (navigator.clipboard && W.isSecureContext) return navigator.clipboard.writeText(metin);
      return new Promise(function (coz, at) {
        var t = D.createElement('textarea');
        t.value = metin; t.style.position = 'fixed'; t.style.opacity = '0';
        D.body.appendChild(t); t.select();
        try { D.execCommand('copy'); coz(); } catch (e) { at(e); }
        D.body.removeChild(t);
      });
    },
    /* Kısa bildirim */
    bildir: function (metin, tur) {
      var b = D.getElementById('k-bildirim');
      if (!b) {
        b = D.createElement('div'); b.id = 'k-bildirim';
        b.setAttribute('role', 'status'); b.setAttribute('aria-live', 'polite');
        b.style.cssText = 'position:fixed;left:50%;bottom:24px;transform:translateX(-50%);z-index:999;' +
          'padding:12px 20px;border-radius:99px;font-size:.9rem;font-weight:600;box-shadow:var(--g-3);' +
          'background:var(--yuzey);border:1px solid var(--cizgi);color:var(--metin);opacity:0;transition:opacity .2s';
        D.body.appendChild(b);
      }
      b.textContent = metin;
      b.style.borderColor = tur === 'hata' ? 'var(--kirmizi)' : tur === 'iyi' ? 'var(--yesil)' : 'var(--cizgi)';
      b.style.opacity = '1';
      clearTimeout(b._z);
      b._z = setTimeout(function () { b.style.opacity = '0'; }, 3200);
    }
  };
})();

/* =====================================================================
   KİŞİ KARTI ÖNİZLEMESİ - KALDIRILDI
   ---------------------------------------------------------------------
   Burada, bir kişi bağlantısının üzerine gelindiğinde açılan küçük bir
   önizleme kartı vardı: ad, unvan, kurum, roller ve sayımlar.
   Kaldırıldı.

   Sebebi: bir profil kartına tıklayan kişi profili açmak ister, profilin
   küçültülmüş bir kopyasını görmek değil. Önizleme, gitmek istenen yerin
   önüne bir adım daha koyuyordu; üstelik veriyi ayrı bir istekle
   getirdiği için çoğu zaman yarım görünüyordu, kart açılıyor ama içi
   henüz gelmemiş oluyordu. Yarım bir kart, hiç kart olmamasından kötüdür.

   Kart zaten bir bağlantıdır ve tıklandığında kişinin sayfasına gider.
   Aradaki adım kalktı, yol kısaldı.

   /api/kisi-kart ucu yerinde duruyor: başka bir yerde işe yarayabilir ve
   bir ucu kaldırmak, onu çağıran kalmadığını doğrulamayı gerektirir.
   ===================================================================== */


/* =====================================================================
   İÇİNDEKİLER LİSTESİ DAR EKRANDA KATLANIR

   Sağ ray 1320 pikselden sonra açılır. Bunun altında ray metnin üstüne
   iner ve içindekiler listesi orada açık dururken, on dört başlıklı bir
   liste telefonda metinden önce bütün ekranı kaplıyordu: okumaya
   başlamadan önce bir liste geçmek gerekiyordu.

   Liste HTML'de "open" yazılı gelir. Betik çalışmazsa açık kalır ve
   sayfa yine kullanılır; kapalı gelseydi ve betik çalışmasaydı liste
   hiç açılamazdı. Bu yüzden kapatmayı betik yapar, açmayı işaretleme.
   Okur elle açıp kapattıysa kararına dokunulmaz.
   ===================================================================== */
(function () {
  var ESIK = 1320;
  var elle = false;

  function listeler() {
    return document.querySelectorAll('details.blg-nav');
  }
  function uygula() {
    if (elle) return;
    var dar = window.innerWidth < ESIK;
    listeler().forEach(function (d) {
      if (dar && d.open) d.open = false;
      else if (!dar && !d.open) d.open = true;
    });
  }

  listeler().forEach(function (d) {
    var s = d.querySelector('summary');
    if (s) s.addEventListener('click', function () { elle = true; });
  });

  uygula();
  var bekle = null;
  window.addEventListener('resize', function () {
    if (bekle) clearTimeout(bekle);
    bekle = setTimeout(uygula, 150);
  }, { passive: true });
})();

/* =====================================================================
   E-POSTA ADRESİNİ GÖSTERME

   Adres sayfaya düz metin olarak basılmaz; ters çevrilmiş ve base64 ile
   kodlanmış bir dize olarak durur. Okur düğmeye bastığında adres kurulur
   ve bir mailto bağlantısına dönüşür.

   Bunun bir şifreleme OLMADIĞINI açıkça söylemek gerekir: betik
   çalıştıran bir toplayıcı adresi yine çözer. Amaç, adresin sayfa
   kaynağını okuyan basit tarayıcıların eline kolayca geçmemesidir.
   Adresinin hiç görünmesini istemeyen kişide bu veri sayfaya konmaz;
   orada saklanacak bir şey de yoktur.
   ===================================================================== */
(function () {
  function coz(k) {
    try { return decodeURIComponent(escape(atob(k))).split('').reverse().join(''); }
    catch (e) { return ''; }
  }
  document.addEventListener('click', function (e) {
    var dg = e.target.closest ? e.target.closest('[data-eposta-ac]') : null;
    if (!dg) return;
    var adres = coz(dg.getAttribute('data-e') || '');
    if (!adres) return;
    var a = document.createElement('a');
    a.href = 'mailto:' + adres;
    a.textContent = adres;
    a.className = 'ks-eposta';
    a.setAttribute('rel', 'nofollow');
    dg.parentNode.replaceChild(a, dg);
  });
})();
