/* =====================================================================
   WCAG DENETİMİ: kapı ölçümü. Depoya girmez.

   duzen-karsilastir.js bir GERİLEME ölçer: bugünkü düzen dünküyle aynı
   mı. Bu kapı başka bir şey ölçer: düzen, dün de bugün de doğru mu.
   Gerileme ölçümü, ilk günden beri yanlış olan bir şeyi hiç görmez.

   Ölçülen ölçüt WCAG 2.2 AA'dır ve YALNIZCA makineyle kesin
   ölçülebilenler alınmıştır. Bir aracın "ihlal yok" demesi sayfanın
   erişilebilir olduğunu göstermez; gösterebileceği tek şey, sayılabilir
   olanların sayıldığıdır. Ölçülemeyenler 9. bölümde adıyla yazılıdır ki
   ölçülmedikleri gizlenmesin.

   Kullanım:
     KPORT=8941 node wcag-kapi.js
     KPORT=8941 node wcag-kapi.js --ayrinti      (her ihlali tek tek yaz)

   ÖLÇÜM TUZAKLARI (OKUBENI 1-5; hepsi bu betikte açıkça karşılanır):
     1. .focus() :focus-visible'i tetiklemez -> klavyeden Tab basılır.
     2. prefers-reduced-motion geçişi 0s yapmaz, 1e-06s ölçülür ->
        eşik parseFloat(d) > 0.01.
     3. 11.52px belirtecin KENDİSİdir (--y-1 = .72rem); kesirli olmak
        ihlal değildir.
     4. clamp() değeri ihlal değil tavandır -> 1440 ve 820'de ölçülür.
     5. Yarı saydam zemin opak sanılmaz -> alfası 0.9 altındaki zemin
        atlanır, ağaçta yukarı çıkılır.
   ===================================================================== */
'use strict';
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK  = 'http://127.0.0.1:' + PORT;
const AYRINTI = process.argv.includes('--ayrinti');

const SAYFALAR = [
  { yol: '/',                ad: 'anasayfa' },
  { yol: '/yazilar.php',     ad: 'arsiv' },
  { yol: '/ilkeler.php',     ad: 'ilkeler' },
  { yol: '/nasil-isler.php', ad: 'nasil-isler' },
  { yol: '/acikliklar.php',  ad: 'acikliklar' },
  { yol: '/hakemlik.php',    ad: 'hakemlik' },
  { yol: '/basvuru.php',     ad: 'basvuru' },
  /* 320 pikselde taşan bir sayfa, ölçülmediği için görülmemişti:
     rozet sarmayınca kart sayfayı 16 piksel taşırıyordu. */
  { yol: '/bekleyen.php',    ad: 'bekleyen' },
  { yol: '/kurul.php',       ad: 'kurul' },
];
const GENISLIKLER = [1440, 820];
const TEMALAR = ['acik', 'koyu'];
const DILLER = ['tr', 'en'];

let gecti = 0, kaldi = 0;
const bulgular = [];
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function not_(s) { console.log('  NOT    ' + s); }
function olc(s) { console.log('  ÖLÇÜM  ' + s); }
function kaydet(olcut, sayfa, kapsam, ayrinti) {
  bulgular.push({ olcut, sayfa, kapsam, ayrinti });
}

/* ===================================================================
   TARAYICI İÇİNDE ÇALIŞAN ÖLÇÜM
   Tek bir evaluate içinde toplanır ki ölçümler arasında düzen
   değişmesin.
   =================================================================== */
function sayfayiOlc() {
  const cik = { karsitlik: [], hedef: [], baslik: [], etiket: [], alt: [],
                bag: [], yerimi: [], dil: [], baslikMetni: '', h1: 0,
                atlaBag: 0, tekrarId: [], aria: [] };

  const gorunur = el => {
    const st = getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden') return false;
    if (el.closest('[hidden],[aria-hidden="true"]')) return false;
    const k = el.getBoundingClientRect();
    return k.width > 0.5 && k.height > 0.5;
  };
  const im = el => {
    const c = String(el.className || '').split(/\s+/).filter(Boolean)[0];
    return el.tagName.toLowerCase() + (c ? '.' + c : '');
  };

  /* --- renk ---------------------------------------------------------- */
  function pars(c) {
    let m = c.match(/rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/);
    if (m) return [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]];
    m = c.match(/color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?:\s*\/\s*([\d.]+))?\)/);
    if (m) return [m[1] * 255, m[2] * 255, m[3] * 255, m[4] === undefined ? 1 : +m[4]];
    return null;
  }
  function L(r, g, b) {
    const c = [r, g, b].map(v => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); });
    return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
  }
  /* TUZAK 5: yarı saydam zemin opak sanılmaz. Alfası 0.9'un altındaki
     zemin ATLANIR ve ağaçta yukarı çıkılır; yoksa dokuz sahte ihlal
     çıkar (11 Ağustos'ta çıkmıştı). */
  function zemin(el) {
    let e = el;
    while (e) {
      const st = getComputedStyle(e);
      const g = st.backgroundImage || '';
      if (g && g.indexOf('gradient') >= 0) {
        const m = g.match(/rgba?\([^)]+\)/g) || [];
        let en = null, eniyi = -1;
        m.forEach(x => { const c = pars(x); if (c && c[3] > 0.5) { const l = L(c[0], c[1], c[2]); if (l > eniyi) { eniyi = l; en = c; } } });
        if (en) return en;
      }
      const c = pars(st.backgroundColor);
      if (c && c[3] >= 0.9) return c;
      e = e.parentElement;
    }
    return [255, 255, 255, 1];
  }
  function oran(f, z) {
    const a = L(f[0], f[1], f[2]), b = L(z[0], z[1], z[2]);
    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
  }

  /* --- 1.4.3 metin karşıtlığı ---------------------------------------- */
  document.querySelectorAll('p,span,a,li,td,th,b,strong,h1,h2,h3,h4,h5,h6,button,label,small,em,i,div,summary,legend,figcaption,dt,dd,code,abbr').forEach(el => {
    if (!el.childNodes.length) return;
    let t = '';
    el.childNodes.forEach(n => { if (n.nodeType === 3) t += n.textContent; });
    if (!t.trim()) return;
    if (!gorunur(el)) return;
    const st = getComputedStyle(el);
    if (+st.opacity < 0.5) return;
    const f = pars(st.color); if (!f) return;
    const o = oran(f, zemin(el));
    const px = parseFloat(st.fontSize), kalin = parseInt(st.fontWeight) >= 700;
    const esik = (px >= 24 || (px >= 18.66 && kalin)) ? 3 : 4.5;
    /* 0.02 pay: tarayıcının renk yuvarlaması. Belirtecin tam eşikte
       tutulduğu yerlerde sahte ihlal üretmesin. */
    if (o < esik - 0.02) cik.karsitlik.push({ im: im(el), m: t.trim().slice(0, 40), o: +o.toFixed(2), esik });
  });

  /* --- 2.5.8 dokunma hedefi (AA: 24x24 CSS px) -----------------------
     Aralık kuralı da WCAG'te var ama burada ölçülmüyor; ölçülmeyen şey
     9. bölümde yazılı.

     WCAG'in SATIR İÇİ İSTİSNASI: "hedef bir cümlenin içindeyse ya da
     boyu hedef olmayan metnin satır yüksekliğiyle sınırlıysa" ölçüt
     uygulanmaz. Akan metnin içindeki bir bağlantı büyütülemez;
     büyütülseydi satır aralığı bozulur ve metin okunmaz olurdu.

     ÖLÇÜM TUZAĞI: bu istisna KAP LİSTESİYLE yazılamaz. İlk yazımda
     'p,li,dd,figcaption' listesi vardı ve ray kutularındaki
     (.blg-kutu) cümle içi bağlantılar listede olmadıkları için 60'tan
     fazla sahte ihlal ürettiler; kutu metnini doğrudan div'in içine
     yazmak bir kusur değildir. Doğru ölçüt kabın ADI değil, hedefin
     ÇEVRESİNDE hedef olmayan metin bulunup bulunmadığıdır. */
  const cumleIci = el => {
    if (getComputedStyle(el).display !== 'inline') return false;
    /* Hedefi saran salt biçim ögeleri (b, strong, em, i, span) aşılır:
       <b><a>...</a></b> yazımında hedefin doğrudan atası yalnız
       bağlantıyı taşır ve cümle bir üst katmandadır. Yalnız doğrudan
       ataya bakan bir ölçüm, cümlenin ortasındaki bir bağlantıyı
       "tek başına duran hedef" sanar. */
    let hedef = el, ata = el.parentElement;
    while (ata && /^(b|strong|em|i|span|u|mark|small)$/i.test(ata.tagName)
           && (ata.textContent || '').trim() === (hedef.textContent || '').trim()) {
      hedef = ata; ata = ata.parentElement;
    }
    if (!ata) return false;
    let dis = '';
    ata.childNodes.forEach(n => {
      if (n === hedef) return;
      if (n.nodeType === 3) dis += n.textContent;
      else if (n.nodeType === 1 && !n.matches('a[href],button')) dis += n.textContent || '';
    });
    return dis.trim().length > 0;
  };
  document.querySelectorAll('a[href],button,input,select,textarea,summary,[role="button"],[role="tab"],[role="checkbox"]').forEach(el => {
    if (!gorunur(el)) return;
    const st = getComputedStyle(el);
    if (cumleIci(el)) return;
    if (el.type === 'hidden') return;
    const k = el.getBoundingClientRect();
    if (k.width < 24 || k.height < 24) {
      cik.hedef.push({ im: im(el), m: (el.textContent || el.getAttribute('aria-label') || el.value || '').trim().slice(0, 30),
                       g: Math.round(k.width), y: Math.round(k.height) });
    }
  });

  /* --- 1.3.1 başlık sırası ------------------------------------------- */
  const basliklar = Array.from(document.querySelectorAll('h1,h2,h3,h4,h5,h6')).filter(gorunur);
  cik.h1 = basliklar.filter(h => h.tagName === 'H1').length;
  let onceki = 0;
  basliklar.forEach(h => {
    const d = +h.tagName[1];
    if (onceki && d > onceki + 1) cik.baslik.push({ atlanan: 'h' + onceki + ' -> h' + d, m: (h.textContent || '').trim().slice(0, 40) });
    onceki = d;
  });

  /* --- 1.3.1 / 3.3.2 form etiketi ------------------------------------ */
  document.querySelectorAll('input,select,textarea').forEach(el => {
    if (el.type === 'hidden' || !gorunur(el)) return;
    const id = el.getAttribute('id');
    const etiketli = (id && document.querySelector('label[for="' + CSS.escape(id) + '"]'))
                  || el.closest('label')
                  || el.getAttribute('aria-label')
                  || (el.getAttribute('aria-labelledby') && document.getElementById(el.getAttribute('aria-labelledby')))
                  || el.getAttribute('title');
    if (!etiketli) cik.etiket.push({ im: im(el), ad: el.getAttribute('name') || '', tur: el.type || el.tagName.toLowerCase() });
  });

  /* --- 1.1.1 metin karşılığı ----------------------------------------- */
  document.querySelectorAll('img').forEach(el => {
    if (!gorunur(el)) return;
    if (el.getAttribute('alt') === null) cik.alt.push({ src: (el.getAttribute('src') || '').slice(-40) });
  });
  document.querySelectorAll('svg').forEach(el => {
    if (el.getAttribute('aria-hidden') === 'true') return;
    if (!gorunur(el)) return;
    const t = el.querySelector('title');
    if (!t && !el.getAttribute('aria-label') && el.getAttribute('role') !== 'presentation') {
      cik.alt.push({ src: 'svg:' + (el.getAttribute('class') || '') });
    }
  });

  /* --- 2.4.4 bağlantı amacı ------------------------------------------ */
  const belirsiz = ['buraya', 'tıkla', 'tıklayın', 'devam', 'daha', 'click here', 'here', 'read more', 'more', 'link'];
  document.querySelectorAll('a[href]').forEach(el => {
    if (!gorunur(el)) return;
    const t = (el.textContent || '').trim().toLowerCase();
    const ad = el.getAttribute('aria-label');
    if (!t && !ad && !el.querySelector('img[alt]:not([alt=""])')) {
      cik.bag.push({ im: im(el), h: (el.getAttribute('href') || '').slice(0, 40), sebep: 'adsız' });
    } else if (!ad && belirsiz.includes(t)) {
      cik.bag.push({ im: im(el), h: (el.getAttribute('href') || '').slice(0, 40), sebep: 'belirsiz: ' + t });
    }
  });

  /* --- 1.3.6 / 2.4.1 yer imleri -------------------------------------- */
  ['header,[role="banner"]', 'main,[role="main"]', 'footer,[role="contentinfo"]', 'nav,[role="navigation"]'].forEach(s => {
    if (!document.querySelector(s)) cik.yerimi.push(s.split(',')[0]);
  });
  /* ÖLÇÜM TUZAĞI: bağlantının METNİNE bakmayın. İlk yazımda
     /atla|skip/ aranıyordu; bağlantının Türkçesi "İçeriğe GEÇ"
     olduğu için kapı, sekiz sayfada da var olan ve çalışan bir
     bağlantıyı "yok" saydı. Ölçülecek olan söz değil DAVRANIŞTIR:
     odak sırasındaki ilk bağlantı ana bölgeye gidiyor mu. */
  const ilkBag = document.querySelector('a[href]');
  const hedefId = ilkBag ? (ilkBag.getAttribute('href') || '').replace(/^#/, '') : '';
  const hedefEl = hedefId ? document.getElementById(hedefId) : null;
  cik.atlaBag = (hedefEl && (hedefEl.tagName === 'MAIN' || hedefEl.getAttribute('role') === 'main'
                 || hedefEl.closest('main'))) ? 1 : 0;

  /* --- 3.1.1 sayfa dili ---------------------------------------------- */
  cik.dilNite = document.documentElement.getAttribute('lang') || '';
  cik.baslikMetni = (document.title || '').trim();

  /* --- 4.1.1 tekrar eden id ------------------------------------------ */
  const gorulen = {};
  document.querySelectorAll('[id]').forEach(el => {
    const i = el.getAttribute('id');
    if (gorulen[i]) { if (cik.tekrarId.indexOf(i) < 0) cik.tekrarId.push(i); }
    gorulen[i] = 1;
  });

  /* --- 4.1.2 aria-labelledby kırık gönderme -------------------------- */
  document.querySelectorAll('[aria-labelledby],[aria-describedby],[aria-controls]').forEach(el => {
    ['aria-labelledby', 'aria-describedby', 'aria-controls'].forEach(n => {
      const v = el.getAttribute(n);
      if (!v) return;
      v.split(/\s+/).forEach(id => { if (id && !document.getElementById(id)) cik.aria.push({ im: im(el), n, id }); });
    });
  });

  return cik;
}

/* ===================================================================
   ÇALIŞTIR
   =================================================================== */
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const toplam = { karsitlik: 0, hedef: 0, baslik: 0, etiket: 0, alt: 0, bag: 0, yerimi: 0, tekrarId: 0, aria: 0 };
  const h1Sorun = [], dilSorun = [], baslikYok = [], atlaYok = [];

  /* --- 1-6: sayfa x tema x genişlik ---------------------------------- */
  console.log('== 1-6. Sayfa denetimi (' + SAYFALAR.length + ' sayfa x ' + TEMALAR.length + ' tema x ' + GENISLIKLER.length + ' genişlik) ==');
  for (const g of GENISLIKLER) {
    const ctx = await b.newContext({ viewport: { width: g, height: 900 } });
    const p = await ctx.newPage();
    /* Bildirim şeridi kapatılır: her sayfada aynı öğeyi ölçmenin
       anlamı yok ve açık kaldığında düzeni kaydırıyor. */
    await p.goto(KOK + '/');
    await p.evaluate(() => { try { localStorage.setItem('kutadgu-bildiri', '1'); } catch (e) {} });
    for (const tema of TEMALAR) {
      for (const s of SAYFALAR) {
        await p.goto(KOK + s.yol + '?lang=tr', { waitUntil: 'networkidle' });
        await p.evaluate(t => {
          document.documentElement.setAttribute('data-tema', t);
          try { localStorage.setItem('kutadgu-tema', t); } catch (e) {}
        }, tema);
        await p.waitForTimeout(220);
        const r = await p.evaluate(sayfayiOlc);
        const kap = s.ad + ' ' + tema + ' ' + g;
        toplam.karsitlik += r.karsitlik.length;
        toplam.hedef     += r.hedef.length;
        toplam.tekrarId  += r.tekrarId.length;
        toplam.aria      += r.aria.length;
        r.karsitlik.forEach(x => kaydet('1.4.3 karşıtlık', s.ad, kap, x.im + ' ' + x.o + '<' + x.esik + ' "' + x.m + '"'));
        r.hedef.forEach(x => kaydet('2.5.8 dokunma hedefi', s.ad, kap, x.im + ' ' + x.g + 'x' + x.y + ' "' + x.m + '"'));
        r.tekrarId.forEach(x => kaydet('4.1.1 tekrar eden id', s.ad, kap, x));
        r.aria.forEach(x => kaydet('4.1.2 kırık aria göndermesi', s.ad, kap, x.im + ' ' + x.n + '=' + x.id));
        /* Yapı ölçütleri temadan ve genişlikten bağımsızdır; bir kez
           sayılır, yoksa aynı ihlal dört kere sayılır ve sayı şişer. */
        if (tema === TEMALAR[0] && g === GENISLIKLER[0]) {
          toplam.baslik += r.baslik.length;
          toplam.etiket += r.etiket.length;
          toplam.alt    += r.alt.length;
          toplam.bag    += r.bag.length;
          toplam.yerimi += r.yerimi.length;
          r.baslik.forEach(x => kaydet('1.3.1 başlık sırası', s.ad, s.ad, x.atlanan + ' "' + x.m + '"'));
          r.etiket.forEach(x => kaydet('3.3.2 form etiketi', s.ad, s.ad, x.im + ' [' + x.ad + '] ' + x.tur));
          r.alt.forEach(x => kaydet('1.1.1 metin karşılığı', s.ad, s.ad, x.src));
          r.bag.forEach(x => kaydet('2.4.4 bağlantı amacı', s.ad, s.ad, x.sebep + ' -> ' + x.h));
          r.yerimi.forEach(x => kaydet('1.3.6 yer imi', s.ad, s.ad, 'eksik: ' + x));
          if (r.h1 !== 1) h1Sorun.push(s.ad + ': ' + r.h1);
          if (!/^tr\b/i.test(r.dilNite)) dilSorun.push(s.ad + ': lang="' + r.dilNite + '"');
          if (!r.baslikMetni) baslikYok.push(s.ad);
          if (!r.atlaBag) atlaYok.push(s.ad);
        }
      }
    }
    await ctx.close();
  }

  den('1.4.3 metin karşıtlığı AA (iki tema, iki genişlik)', toplam.karsitlik === 0, toplam.karsitlik + ' ihlal');
  den('2.5.8 dokunma hedefi 24x24', toplam.hedef === 0, toplam.hedef + ' ihlal');
  den('1.3.1 başlık sırası atlamıyor', toplam.baslik === 0, toplam.baslik + ' ihlal');
  den('  her sayfada tam bir h1 var', h1Sorun.length === 0, h1Sorun.join(' · '));
  den('3.3.2 her form alanının etiketi var', toplam.etiket === 0, toplam.etiket + ' ihlal');
  den('1.1.1 her görselin metin karşılığı var', toplam.alt === 0, toplam.alt + ' ihlal');
  den('2.4.4 bağlantı amacı adından anlaşılıyor', toplam.bag === 0, toplam.bag + ' ihlal');
  den('1.3.6 yer imleri tam (banner, main, contentinfo, navigation)', toplam.yerimi === 0, toplam.yerimi + ' eksik');
  den('4.1.1 tekrar eden id yok', toplam.tekrarId === 0, toplam.tekrarId + ' ihlal');
  den('4.1.2 aria göndermeleri kırık değil', toplam.aria === 0, toplam.aria + ' ihlal');
  den('2.4.2 her sayfanın başlığı var', baslikYok.length === 0, baslikYok.join(', '));
  den('2.4.1 her sayfada içeriğe atla bağlantısı var', atlaYok.length === 0, atlaYok.join(', '));

  /* --- 7. Klavye ------------------------------------------------------
     TUZAK 1: .focus() :focus-visible'i TETİKLEMEZ. Odak halkası
     yalnızca klavyeden Tab basıldığında çizilir; el ile odaklayıp
     ölçen bir kapı, halkası hiç olmayan bir sayfada da GECTI verir. */
  console.log('\n== 7. Klavye: odak görünür mü (Tab ile) ==');
  {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    await p.goto(KOK + '/');
    await p.evaluate(() => { try { localStorage.setItem('kutadgu-bildiri', '1'); } catch (e) {} });
    for (const s of SAYFALAR.slice(0, 4)) {
      await p.goto(KOK + s.yol + '?lang=tr', { waitUntil: 'networkidle' });
      let halkasiz = 0, bakilan = 0, tuzak = 0;
      const gorulen = new Set();
      for (let i = 0; i < 40; i++) {
        await p.keyboard.press('Tab');
        /* ÖLÇÜM TUZAĞI (bu kapının kendisi bununla beş sahte ihlal
           verdi): odak halkası GEÇİŞLİ çizilir (.kav-ic a ve .kmk-bag
           üzerinde transition var). Tab'a basıp hemen ölçen bir kapı
           outline-width'i 0px okur ve halkası olan bir ögeyi
           "halkasız" sayar. 400 ms sonra aynı öge 2px veriyor.
           Bekleme geçiş süresinden (--gecis, 0.2s) uzun tutuldu. */
        await p.waitForTimeout(300);
        const r = await p.evaluate(() => {
          const el = document.activeElement;
          if (!el || el === document.body) return null;
          const st = getComputedStyle(el);
          const k = el.getBoundingClientRect();
          const c = String(el.className || '').split(/\s+/).filter(Boolean)[0];
          return {
            im: el.tagName.toLowerCase() + (c ? '.' + c : ''),
            /* Halka outline, box-shadow ya da belirgin bir kenarlık
               olabilir; üçü de sayılır. Yalnız outline aranırsa
               box-shadow ile çizilmiş bir halka "yok" görünür. */
            outline: st.outlineStyle !== 'none' && parseFloat(st.outlineWidth) > 0,
            golge: st.boxShadow !== 'none',
            gorunur: k.width > 0 && k.height > 0,
            ekranda: k.top >= -2 && k.bottom <= innerHeight + 2,
          };
        });
        if (!r) break;
        if (gorulen.has(r.im + i)) continue;
        gorulen.add(r.im + i);
        if (!r.gorunur) { tuzak++; continue; }
        bakilan++;
        if (!r.outline && !r.golge) { halkasiz++; kaydet('2.4.7 odak görünür', s.ad, s.ad + ' tab#' + i, r.im); }
      }
      den('[' + s.ad + '] Tab ile gezilen ' + bakilan + ' ögenin hepsinde odak halkası var',
          halkasiz === 0, halkasiz + ' halkasız');
      if (tuzak > 0) not_('[' + s.ad + '] ' + tuzak + ' odak görünmeyen ögede (ölçüm dışı)');
    }
    await ctx.close();
  }

  /* --- 8. Kısık devinim ----------------------------------------------
     TUZAK 2: prefers-reduced-motion geçişi 0s YAPMAZ; canlıda 1e-06s
     ölçüldü. Eşik parseFloat(d) > 0.01 olmalı, yoksa tek sayfada 56
     sahte ihlal çıkar. */
  console.log('\n== 8. Kısık devinim (prefers-reduced-motion) ==');
  {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, reducedMotion: 'reduce' });
    const p = await ctx.newPage();
    let uzun = 0;
    for (const s of SAYFALAR) {
      await p.goto(KOK + s.yol + '?lang=tr', { waitUntil: 'networkidle' });
      uzun += await p.evaluate(() => {
        let n = 0;
        document.querySelectorAll('*').forEach(el => {
          const st = getComputedStyle(el);
          [st.transitionDuration, st.animationDuration].forEach(d => {
            String(d).split(',').forEach(x => { if (parseFloat(x) > 0.01) n++; });
          });
        });
        return n;
      });
    }
    den('kısık devinim istendiğinde süren geçiş/animasyon yok', uzun === 0, uzun + ' öge');
  }

  /* --- 9. 320 pikselde akış (1.4.10) ---------------------------------- */
  console.log('\n== 9. 320 pikselde akış (1.4.10) ==');
  {
    const ctx = await b.newContext({ viewport: { width: 320, height: 800 } });
    const p = await ctx.newPage();
    await p.goto(KOK + '/');
    await p.evaluate(() => { try { localStorage.setItem('kutadgu-bildiri', '1'); } catch (e) {} });
    let tasan = 0;
    for (const s of SAYFALAR) {
      await p.goto(KOK + s.yol + '?lang=tr', { waitUntil: 'networkidle' });
      const r = await p.evaluate(() => {
        const de = document.documentElement;
        const tasma = de.scrollWidth - de.clientWidth;
        const suclu = [];
        if (tasma > 2) {
          document.querySelectorAll('*').forEach(el => {
            const k = el.getBoundingClientRect();
            if (k.right > de.clientWidth + 2 && k.width > 0) {
              const c = String(el.className || '').split(/\s+/).filter(Boolean)[0];
              const ad = el.tagName.toLowerCase() + (c ? '.' + c : '');
              if (suclu.indexOf(ad) < 0) suclu.push(ad);
            }
          });
        }
        return { tasma, suclu: suclu.slice(0, 6) };
      });
      if (r.tasma > 2) { tasan++; kaydet('1.4.10 akış', s.ad, '320px', r.tasma + 'px: ' + r.suclu.join(', ')); }
    }
    den('320 pikselde yatay kaydırma yok', tasan === 0, tasan + ' sayfa');
    await ctx.close();
  }

  /* --- 10. Çok dillilik (3.1.1 / 3.1.2) ------------------------------- */
  console.log('\n== 10. Çok dillilik ==');
  {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    let yanlisDil = 0, eksikAlternatif = 0;
    for (const s of SAYFALAR) {
      for (const d of DILLER) {
        await p.goto(KOK + s.yol + '?lang=' + d, { waitUntil: 'domcontentloaded' });
        const r = await p.evaluate(() => ({
          lang: document.documentElement.getAttribute('lang') || '',
          alt: Array.from(document.querySelectorAll('link[rel="alternate"][hreflang]')).map(x => x.getAttribute('hreflang')),
        }));
        if (!r.lang.toLowerCase().startsWith(d)) {
          yanlisDil++;
          kaydet('3.1.1 sayfa dili', s.ad, '?lang=' + d, 'lang="' + r.lang + '"');
        }
        /* Aynı içeriğin öteki dili duyurulmalı: yoksa arama motoru da
           ekran okuyucu da öteki sürümü hiç bulmaz. */
        if (!r.alt.some(x => (x || '').toLowerCase().startsWith(d === 'tr' ? 'en' : 'tr'))) {
          eksikAlternatif++;
          kaydet('3.1.2 dil alternatifi', s.ad, '?lang=' + d, 'hreflang: ' + (r.alt.join(',') || 'yok'));
        }
      }
    }
    den('3.1.1 lang niteliği sayfanın diliyle uyuşuyor', yanlisDil === 0, yanlisDil + ' ihlal');
    den('3.1.2 her sayfa öteki dilini hreflang ile duyuruyor', eksikAlternatif === 0, eksikAlternatif + ' ihlal');
    await ctx.close();
  }

  /* --- 11. Konsol ----------------------------------------------------- */
  console.log('\n== 11. Konsol ==');
  {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    const hata = [];
    p.on('console', m => { if (m.type() === 'error') hata.push(m.text().slice(0, 80)); });
    p.on('pageerror', e => hata.push('pageerror: ' + String(e.message).slice(0, 80)));
    for (const s of SAYFALAR) await p.goto(KOK + s.yol + '?lang=tr', { waitUntil: 'networkidle' });
    den('sayfalarda konsol hatası yok', hata.length === 0, hata.slice(0, 3).join(' | '));
    await ctx.close();
  }

  await b.close();

  /* --- 12. ÖLÇÜLMEYENLER --------------------------------------------
     Bir aracın "ihlal yok" demesi sayfanın erişilebilir olduğunu
     göstermez. Ölçülmeyenler adıyla yazılır ki ölçülmedikleri
     gizlenmesin ve bir sonraki oturum nereden başlayacağını bilsin. */
  console.log('\n== 12. Bu kapının ÖLÇMEDİKLERİ ==');
  [
    '1.1.1 alt metnin DOĞRU olup olmadığı (varlığı ölçülüyor, isabeti değil)',
    '1.3.2 anlamlı okuma sırası',
    '1.4.11 arayüz ögesi ve grafik karşıtlığı (yalnız metin ölçülüyor)',
    '2.4.6 başlık ve etiketlerin açıklayıcı olması',
    '2.5.8 dokunma hedefleri arasındaki ARALIK kuralı',
    '3.3.1/3.3.3 hata bildirimi ve düzeltme önerisi (form gönderilmiyor)',
    '4.1.3 durum iletileri (canlı bölge)',
    'ekran okuyucuyla gerçek bir okuma denemesi',
  ].forEach(x => not_(x));

  /* --- Özet ------------------------------------------------------------ */
  if (AYRINTI && bulgular.length) {
    console.log('\n== Bulgular ==');
    const grup = {};
    bulgular.forEach(x => { (grup[x.olcut] = grup[x.olcut] || []).push(x); });
    Object.keys(grup).sort().forEach(k => {
      console.log('\n  ' + k + '  (' + grup[k].length + ')');
      grup[k].slice(0, 25).forEach(x => console.log('    ' + x.kapsam + '  ' + x.ayrinti));
      if (grup[k].length > 25) console.log('    ... ve ' + (grup[k].length - 25) + ' tane daha');
    });
  } else if (bulgular.length) {
    const grup = {};
    bulgular.forEach(x => { grup[x.olcut] = (grup[x.olcut] || 0) + 1; });
    console.log('\n== Bulgu dağılımı (ayrıntı için --ayrinti) ==');
    Object.keys(grup).sort().forEach(k => olc(k + ': ' + grup[k]));
  }

  console.log('\n' + '-'.repeat(40));
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
