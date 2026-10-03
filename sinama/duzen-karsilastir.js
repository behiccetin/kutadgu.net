/* Düzen gerilemesi karşılaştırması.

   Ne işe yarar: düzene dokunmadan ÖNCE bir anlık görüntü alınır, değişiklik
   yapılır, SONRA ikinci bir görüntü alınır ve ikisi karşılaştırılır. Çıktı
   bayrak sayısıdır. Amaç "bu sayfa güzel mi" demek değil, "dün ne idiyse
   bugün de o mu" demektir. Bu yüzden ölçülen değerlerin mutlak doğruluğu
   değil, kararlılığı önemlidir: aynı veriyle aynı tarayıcıda aynı sayı
   çıkmalıdır.

   Kullanım:
     node duzen-karsilastir.js al <ad>              anlık görüntü alır, saklar
     node duzen-karsilastir.js karsilastir <once> <sonra>
     node duzen-karsilastir.js <ad>                 <ad> ile karşılaştırır;
                                                    yoksa alıp taban yapar

   Anlık görüntüler: sinama/.duzen/<ad>.json

   ---------------------------------------------------------------------
   ÖLÇÜM TUZAKLARI (hepsi geçen oturumda vurdu; aşağıda ilgili yerlerde
   tekrar anılır)

   1) el.focus() :focus-visible tetiklemez. Odak halkası ölçülecekse
      klavyeden Tab basılır. Bu betikte odak ekseni Tab ile ölçülür.
   2) prefers-reduced-motion geçiş süresini 0s yapmaz, .001ms yapar.
      Eşik parseFloat(d) > 0.01 olmalıdır; "> 0" diyen ölçüm her ögeyi
      ihlal sanır.
   3) 11.52px ve 12.8px belirtecin KENDİSİdir (--y-1=.72rem, --y-2=.8rem).
      Kesirli olmak kural ihlali değildir. Bu yüzden belirteç listesi elle
      yazılmaz, tarayıcıdan okunur (belirtecleriOku).
   4) clamp() değeri ölçek ihlali değil, o clamp'in tavanıdır. Akan ile
      sabiti ayırmak için aynı öge İKİ genişlikte ölçülür: 1440 ve 820.
      1180 çok yakındır; clamp iki yerde de tavanda kalır, ayrım çıkmaz.
   5) Yarı saydam zemin opak değildir. rgba(255,255,255,.08) laciverdin
      üstünde beyaz değildir. Zemin çözülürken alfası 0.9'un altındaki
      katman atlanır, ağaçta yukarı devam edilir (zeminCoz).

   Genel kural: ölçüm yanlış çıktığında önce ölçümden şüphelen. */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const KOK = process.env.KURL || 'http://127.0.0.1:8941';
const DIZIN = path.join(__dirname, '.duzen');
const SURUM = 1;                       /* biçim değişirse artır: eski dosyayla karşılaştırma reddedilir */

/* Genişlikler bilerek bu ikisi. Bkz. tuzak 4: 1440 clamp tavanı, 820 ise
   clamp'in aktığı yer. Aradaki fark akan ile sabiti ayırır. */
const GENISLIKLER = [1440, 820];

/* Sayfalar. /arsiv adresi HTML değil, arşivin JSON dökümüdür (arsiv.php);
   düzeni olan arşiv listesi /yazilar.php'dir, ölçülen odur. Makale sayfası
   veriye bağlı olduğu için sabit yazılmaz, arşiv listesinden bulunur. */
const SAYFALAR = [
  { yol: '/', ad: 'anasayfa' },
  { yol: '/yazilar.php', ad: 'arsiv' },
  { yol: '/ilkeler.php', ad: 'ilkeler' },
  { yol: '/nasil-isler.php', ad: 'nasil-isler' },
  { yol: '/acikliklar.php', ad: 'acikliklar' },
  { yol: '/hakemlik.php', ad: 'hakemlik' },
  { yol: '/basvuru.php', ad: 'basvuru' },
];

/* GÜRÜLTÜ EŞİKLERİ
   Aynı tarayıcı, aynı görüntü alanı ve aynı veriyle ölçüm kural olarak
   birebir aynı çıkar; oynama ancak yazıtipinin geç yüklenmesinden ve
   alt piksel yuvarlamasından gelir, o da 1 pikseli geçmez. Yine de 2
   pikselelik dalgalanmayı bayrak saymamak için genişlik eşiği 3 px
   tutuldu: gerçek bir düzen gerilemesi (sütun daralması, dolgu değişimi,
   ızgara kayması) her zaman bundan büyüktür. Taşma daha ucuz bir sinyal
   olduğu için 2 px'te bayrak yanar: 3 piksellik taşma bile yatay kaydırma
   çubuğu demektir. Sayım eksenlerinde (kart, satır, sütun, hedef) eşik
   yoktur; tamsayıdır, 1 fark bile gerçektir. */
const ESIK_PX = 3;
const ESIK_TASMA = 2;

/* Eksenler. tur: 'tam' tamsayı/birebir, 'px' eşikli sayı, 'metin' birebir
   dizgi. kap: hangi kapsamda ölçülür (her genişlik / yalnız ilk genişlik). */
const EKSENLER = [
  { anahtar: 'kart',       ad: 'kart sayısı',            tur: 'tam' },
  { anahtar: 'satir',      ad: 'satır sayısı',           tur: 'tam' },
  { anahtar: 'pGenislik',  ad: 'paragraf genişliği',     tur: 'px' },
  { anahtar: 'kucukHedef', ad: 'küçük dokunma hedefi',   tur: 'tam' },
  { anahtar: 'tasma',      ad: 'yatay taşma',            tur: 'px', esik: ESIK_TASMA },
  { anahtar: 'tasanSayi',  ad: 'taşan öge sayısı',       tur: 'tam' },
  { anahtar: 'sutun',      ad: 'ızgara sütun sayısı',    tur: 'metin' },
  { anahtar: 'yigilma',    ad: 'yığılma sırası',         tur: 'metin' },
  { anahtar: 'basOlcek',   ad: 'başlık ölçek adımları',  tur: 'metin' },
  { anahtar: 'odak',       ad: 'odak halkası',           tur: 'metin', tek: true },
  { anahtar: 'yuzey',      ad: 'yüzey rengi',            tur: 'metin', tek: true },
  { anahtar: 'devinim',    ad: 'kısık devinim',          tur: 'tam',   tek: true },
];

/* ------------------------------------------------------------------ */
/* Tarayıcı içinde çalışan ölçüm. Tek bir evaluate içinde toplanır ki
   ölçümler arasında düzen değişmesin. */

function sayfayiOlc(hedefBelirtec) {
  /* --- yardımcılar --------------------------------------------------- */
  const de = document.documentElement;
  const ekran = de.clientWidth;
  const gorunur = el => {
    const st = getComputedStyle(el);
    if (st.display === 'none' || st.visibility === 'hidden') return false;
    const k = el.getBoundingClientRect();
    return k.width > 0 && k.height > 0;
  };
  const im = el => {
    const c = String(el.className || '').split(/\s+/).filter(Boolean)[0];
    return el.tagName.toLowerCase() + (c ? '.' + c : '');
  };

  /* Kart sayısı: görünür .kart ögeleri. Kartın kendi içinde kart varsa
     ikisi de sayılır; sayının kararlılığı önemli, mutlak doğruluğu değil. */
  const kartlar = [...document.querySelectorAll('.kart')].filter(gorunur);

  /* Satır sayısı: kartların üst kenarları kaç ayrı hizada duruyor. Izgara
     sarınca satır sayısı değişir, gerilemenin en görünür işareti budur.
     4 piksellik kutuya yuvarlanır ki alt piksel farkı yeni satır uydurmasın. */
  const satirlar = new Set(kartlar.map(k => Math.round(k.getBoundingClientRect().top / 4)));

  /* Paragraf genişliği: gerçek düzyazı paragrafları (80 karakterden uzun).
     Ortanca alınır; tek bir dar paragraf ortalamayı bozmasın. */
  const pler = [...document.querySelectorAll('main p, .blg p, article p')]
    .filter(p => gorunur(p) && (p.textContent || '').trim().length > 80)
    .map(p => p.getBoundingClientRect().width)
    .sort((a, b) => a - b);
  const pGenislik = pler.length ? +pler[Math.floor(pler.length / 2)].toFixed(1) : 0;

  /* Küçük dokunma hedefi. Eşik elle yazılmaz, --hedef belirteci tarayıcıdan
     okunup geçilir (tuzak 3: sayıyı ben uydurursam belirteç değiştiğinde
     ölçüm yalan söyler). Düzyazı içindeki satır içi bağlantılar hedef
     sayılmaz; sayılan, düğme gibi davranan denetimlerdir. */
  const hedef = hedefBelirtec;
  const denetimler = [...document.querySelectorAll(
    'a.d, button, summary, input[type=submit], input[type=button], [role="button"]')]
    .filter(gorunur);
  const kucukHedef = denetimler.filter(el => {
    const k = el.getBoundingClientRect();
    return k.height + 0.5 < hedef || k.width + 0.5 < hedef;
  }).length;

  /* Yatay taşma. Yatay kaydırılabilir bir kabın içi taşma değildir:
     kaydıraklar ve geniş çizelgeler bilerek öyle çalışır. */
  let tasanSayi = 0;
  document.querySelectorAll('body *').forEach(el => {
    const k = el.getBoundingClientRect();
    if (!(k.width > 0 && k.right > ekran + 1.5)) return;
    if (getComputedStyle(el).position === 'fixed') return;
    let a = el, kaydirilir = false;
    while (a && a !== document.body) {
      const s = getComputedStyle(a);
      if (s.overflowX === 'auto' || s.overflowX === 'scroll') { kaydirilir = true; break; }
      a = a.parentElement;
    }
    if (!kaydirilir) tasanSayi++;
  });
  const tasma = de.scrollWidth - ekran;

  /* Izgara sütun sayısı. Sütun sayısı düşerse (4 -> 3) kart dizilişi
     değişmiştir; kart sayısı aynı kalsa bile bu bir düzen gerilemesidir.
     gridTemplateColumns tarayıcıdan çözülmüş halde gelir, parça sayısı
     sütun sayısıdır. */
  const izgara = [];
  document.querySelectorAll('body *').forEach(el => {
    const st = getComputedStyle(el);
    if (st.display !== 'grid' && st.display !== 'inline-grid') return;
    if (!gorunur(el)) return;
    if (el.children.length < 2) return;
    const t = st.gridTemplateColumns;
    if (!t || t === 'none') return;
    izgara.push(im(el) + '=' + t.trim().split(/\s+/).length);
  });
  const sutun = [...new Set(izgara)].sort().join(',');

  /* Yığılma sırası: ana sütunun doğrudan çocukları görsel sırayla.
     Tek sütuna inen bir düzende sıra değişirse burada görünür. */
  const kap = document.querySelector('main') || document.body;
  const yigilma = [...kap.children].filter(gorunur)
    .map(el => ({ el, k: el.getBoundingClientRect() }))
    .sort((a, b) => (Math.round(a.k.top / 4) - Math.round(b.k.top / 4)) || (a.k.left - b.k.left))
    .map(x => im(x.el)).join('>');

  /* Başlık ölçek adımları. clamp'li başlıklar 1440'ta tavanda, 820'de
     akıyor olacaktır (tuzak 4); ikisini de saklamak akan/sabit ayrımını
     kendiliğinden kayda geçirir. */
  const basOlcek = ['h1', 'h2', 'h3'].map(t => {
    const el = [...document.querySelectorAll(t)].find(gorunur);
    return t + '=' + (el ? parseFloat(getComputedStyle(el).fontSize).toFixed(2) : '-');
  }).join(' ');

  return { kart: kartlar.length, satir: satirlar.size, pGenislik, kucukHedef,
           tasma, tasanSayi, sutun, yigilma, basOlcek };
}

/* Zemin çözme (tuzak 5). Alfası 0.9'un altındaki katman opak sayılmaz;
   ağaçta yukarı çıkılır. rgba(255,255,255,.08) laciverdin üstünde beyaz
   değildir; bunu beyaz sayan ölçüm dokuz sahte "WCAG'den kaldı" üretmişti. */
function yuzeyleriOlc() {
  const ayristir = c => {
    const m = String(c).match(/rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/);
    return m ? [+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4]] : null;
  };
  const zeminCoz = el => {
    let e = el;
    while (e) {
      const c = ayristir(getComputedStyle(e).backgroundColor);
      if (c && c[3] >= 0.9) return 'rgb(' + c.slice(0, 3).map(Math.round).join(',') + ')';
      e = e.parentElement;
    }
    return 'rgb(255,255,255)';
  };
  const sec = s => document.querySelector(s);
  return ['body', '.kart', 'main', '.marka, header, .gez']
    .map(s => { const el = sec(s); return s.split(',')[0] + ':' + (el ? zeminCoz(el) : '-'); })
    .join(' ');
}

/* Belirteçler tarayıcıdan okunur (tuzak 3). Elle liste yazmak, .72rem'in
   11.52px'e çözülmesini "kesirli, demek ki hata" sanmaya götürür. */
function belirtecleriOku() {
  const st = getComputedStyle(document.documentElement);
  const cikti = {};
  for (const sayfa of document.styleSheets) {
    let kurallar;
    try { kurallar = sayfa.cssRules; } catch (e) { continue; }   /* başka kökenli sayfa okunamaz */
    for (const k of kurallar) {
      if (!k.style || !k.selectorText || !/(^|,)\s*:root\b/.test(k.selectorText)) continue;
      for (const ad of k.style) if (ad.startsWith('--')) cikti[ad] = st.getPropertyValue(ad).trim();
    }
  }
  return cikti;
}

/* ------------------------------------------------------------------ */

async function makaleyiBul(p) {
  if (process.env.KMAKALE) return process.env.KMAKALE;
  await p.goto(KOK + '/yazilar.php?lang=tr', { waitUntil: 'domcontentloaded' });
  const yol = await p.evaluate(() => {
    const a = [...document.querySelectorAll('a[href^="/tamga/"]')]
      .map(x => x.getAttribute('href')).sort();
    return a[0] || null;
  });
  return yol;
}

async function anlikGoruntuAl(ad) {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const goruntu = { surum: SURUM, ad, tarih: new Date().toISOString(), kok: KOK,
                    genislikler: GENISLIKLER, belirtec: {}, sayfa: {} };
  try {
    const kesif = await (await b.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
    const makale = await makaleyiBul(kesif);
    await kesif.close();
    const sayfalar = SAYFALAR.concat(makale ? [{ yol: makale, ad: 'makale' }] : []);
    if (!makale) console.error('uyarı: makale sayfası bulunamadı, o sayfa ölçülmedi');

    for (const g of GENISLIKLER) {
      const ctx = await b.newContext({ viewport: { width: g, height: 900 }, serviceWorkers: 'block' });
      const p = await ctx.newPage();
      /* Bildiri şeridi düzeni aşağı iter; kapatılmazsa ölçüm gürültülenir. */
      await p.goto(KOK + '/', { waitUntil: 'domcontentloaded' });
      await p.evaluate(() => { try { localStorage.setItem('kutadgu-bildiri', '1'); } catch (e) {} });

      for (const s of sayfalar) {
        const adres = KOK + s.yol + (s.yol.includes('?') ? '&' : '?') + 'lang=tr';
        await p.goto(adres, { waitUntil: 'networkidle' });
        await p.evaluate(() => document.fonts && document.fonts.ready).catch(() => {});
        await p.waitForTimeout(150);

        const hedef = await p.evaluate(() =>
          parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--hedef')) || 40);
        const olcum = await p.evaluate(sayfayiOlc, hedef);

        if (g === GENISLIKLER[0]) {
          /* Odak halkası: .focus() :focus-visible tetiklemez (tuzak 1),
             o yüzden klavyeden Tab basılır. İlk Tab genelde "içeriğe atla"
             bağlantısına gider; hangi ögeye gittiği de kayda girer. */
          await p.evaluate(() => document.body.focus());
          await p.keyboard.press('Tab');
          await p.waitForTimeout(60);
          olcum.odak = await p.evaluate(() => {
            const el = document.activeElement;
            if (!el || el === document.body) return 'yok';
            const st = getComputedStyle(el);
            const c = String(el.className || '').split(/\s+/).filter(Boolean)[0];
            return (el.tagName.toLowerCase() + (c ? '.' + c : '')) +
                   ' outline=' + st.outlineWidth + '/' + st.outlineStyle + '/' + st.outlineOffset;
          });
          olcum.yuzey = await p.evaluate(yuzeyleriOlc);
        }
        goruntu.sayfa[s.yol] = goruntu.sayfa[s.yol] || { ad: s.ad };
        goruntu.sayfa[s.yol][g] = olcum;
      }

      goruntu.belirtec[g] = await p.evaluate(belirtecleriOku);
      await ctx.close();
    }

    /* Kısık devinim ayrı bir bağlamda ölçülür; prefers-reduced-motion
       bağlam düzeyinde bir ayardır. Eşik 0.01 saniye: kısık devinimde
       süre 0s olmaz, .001ms olur (tuzak 2). "> 0" diyen ölçüm her ögeyi
       ihlal sanar. */
    const kctx = await b.newContext({ viewport: { width: GENISLIKLER[0], height: 900 },
                                      reducedMotion: 'reduce', serviceWorkers: 'block' });
    const kp = await kctx.newPage();
    for (const yol of Object.keys(goruntu.sayfa)) {
      await kp.goto(KOK + yol + (yol.includes('?') ? '&' : '?') + 'lang=tr', { waitUntil: 'networkidle' });
      goruntu.sayfa[yol][GENISLIKLER[0]].devinim = await kp.evaluate(() => {
        let n = 0;
        document.querySelectorAll('body *').forEach(el => {
          const st = getComputedStyle(el);
          const sure = [st.transitionDuration, st.animationDuration]
            .join(',').split(',').map(x => parseFloat(x) || 0);
          if (Math.max(...sure) > 0.01) n++;      /* .001ms eşiğin altında kalır */
        });
        return n;
      });
    }
    await kctx.close();
  } finally {
    await b.close();
  }
  return goruntu;
}

/* ------------------------------------------------------------------ */

function yaz(ad, g) {
  fs.mkdirSync(DIZIN, { recursive: true });
  fs.writeFileSync(path.join(DIZIN, ad + '.json'), JSON.stringify(g, null, 1));
  return path.join(DIZIN, ad + '.json');
}
function oku(ad) {
  const y = path.join(DIZIN, ad + '.json');
  if (!fs.existsSync(y)) return null;
  return JSON.parse(fs.readFileSync(y, 'utf8'));
}

function karsilastir(once, sonra) {
  const bayrak = [];
  let toplam = 0;

  if (once.surum !== sonra.surum) {
    bayrak.push(`biçim sürümü değişmiş (${once.surum} -> ${sonra.surum}); yeniden taban al`);
    return { bayrak, toplam: 1 };
  }

  const yollar = [...new Set([...Object.keys(once.sayfa), ...Object.keys(sonra.sayfa)])].sort();
  for (const yol of yollar) {
    const a = once.sayfa[yol], b = sonra.sayfa[yol];
    toplam++;
    if (!a || !b) { bayrak.push(`${yol}: sayfa yalnız ${a ? 'öncede' : 'sonrada'} var`); continue; }
    for (const g of once.genislikler) {
      const ao = a[g], bo = b[g];
      if (!ao || !bo) { toplam++; bayrak.push(`${yol} ${g}px: ölçüm yalnız ${ao ? 'öncede' : 'sonrada'} var`); continue; }
      for (const e of EKSENLER) {
        if (e.tek && g !== once.genislikler[0]) continue;
        if (!(e.anahtar in ao) && !(e.anahtar in bo)) continue;
        toplam++;
        const x = ao[e.anahtar], y = bo[e.anahtar];
        let bozuk = false;
        if (e.tur === 'px') bozuk = Math.abs((+x || 0) - (+y || 0)) >= (e.esik || ESIK_PX);
        else bozuk = String(x) !== String(y);
        if (bozuk) bayrak.push(`${yol} ${g}px ${e.ad}: ${e.tur === 'metin' ? farkOzeti(x, y) : kisalt(x) + ' -> ' + kisalt(y)}`);
      }
    }
  }

  /* Belirteç sapması: genişlik başına tek bayrak, değişenler listelenir.
     Belirteç listesi tarayıcıdan okunduğu için (tuzak 3) burada elle
     yazılmış bir doğru değer yoktur; yalnız önce/sonra farkı vardır. */
  for (const g of once.genislikler) {
    toplam++;
    const a = (once.belirtec || {})[g] || {}, b = (sonra.belirtec || {})[g] || {};
    const adlar = [...new Set([...Object.keys(a), ...Object.keys(b)])].sort();
    const degisen = adlar.filter(n => (a[n] || '(yok)') !== (b[n] || '(yok)'));
    if (degisen.length)
      bayrak.push(`belirteç ${g}px: ${degisen.length} değişti - ` +
        degisen.slice(0, 6).map(n => `${n} ${a[n] || '(yok)'} -> ${b[n] || '(yok)'}`).join('; '));
  }
  return { bayrak, toplam };
}

function kisalt(v) {
  const s = String(v);
  return s.length > 90 ? s.slice(0, 87) + '...' : s;
}

/* Uzun dizgi eksenlerinde (ızgara sütunları, yığılma sırası) baştan
   kesmek işe yaramaz: fark çoğu zaman ortadadır ve kesilen yerde kalır.
   Onun yerine yalnız değişen parçalar yazılır. */
function farkOzeti(x, y) {
  const ayir = s => String(s).split(/[,>]/).filter(Boolean);
  const a = ayir(x), b = ayir(y);
  const gitti = a.filter(t => !b.includes(t));
  const geldi = b.filter(t => !a.includes(t));
  if (!gitti.length && !geldi.length) return kisalt(x) + ' -> ' + kisalt(y);   /* yalnız sıra değişmiş */
  return `- ${kisalt(gitti.join(',')) || '(yok)'}  +${kisalt(geldi.join(',')) || '(yok)'}`;
}

function ozet(g) {
  /* Anlık görüntünün dikkat çeken sayıları; taban alındığında yazdırılır. */
  const sat = [];
  for (const yol of Object.keys(g.sayfa)) {
    const o = g.sayfa[yol][g.genislikler[0]];
    if (!o) continue;
    sat.push(`  ${yol.padEnd(26)} kart=${String(o.kart).padStart(3)} satır=${String(o.satir).padStart(3)}` +
      ` p=${String(o.pGenislik).padStart(6)}px küçükHedef=${String(o.kucukHedef).padStart(3)} taşma=${o.tasma}`);
  }
  return sat.join('\n');
}

/* ------------------------------------------------------------------ */

(async () => {
  const [emir, a1, a2] = process.argv.slice(2);

  if (emir === 'al') {
    if (!a1) { console.error('kullanım: node duzen-karsilastir.js al <ad>'); process.exit(2); }
    const g = await anlikGoruntuAl(a1);
    console.log('yazıldı: ' + yaz(a1, g));
    console.log(ozet(g));
    console.log('0/0 bayrak');
    return;
  }

  if (emir === 'karsilastir') {
    if (!a1 || !a2) { console.error('kullanım: node duzen-karsilastir.js karsilastir <once> <sonra>'); process.exit(2); }
    const o = oku(a1), s = oku(a2);
    if (!o) { console.error('yok: ' + a1); process.exit(2); }
    if (!s) { console.error('yok: ' + a2); process.exit(2); }
    const r = karsilastir(o, s);
    r.bayrak.forEach(b => console.log('BAYRAK  ' + b));
    console.log(`${r.bayrak.length}/${r.toplam} bayrak`);
    process.exit(r.bayrak.length ? 1 : 0);
  }

  if (emir && emir[0] !== '-') {
    /* Eski kullanım: node duzen-karsilastir.js <ad>
       <ad> varsa şimdiki durumla karşılaştırılır; yoksa alınıp taban olur. */
    const eski = oku(emir);
    const yeni = await anlikGoruntuAl(emir);
    if (!eski) {
      console.log('taban yok, alındı: ' + yaz(emir, yeni));
      console.log(ozet(yeni));
      console.log('0/0 bayrak');
      return;
    }
    const r = karsilastir(eski, yeni);
    yaz(emir + '-son', yeni);
    r.bayrak.forEach(b => console.log('BAYRAK  ' + b));
    console.log(`${r.bayrak.length}/${r.toplam} bayrak`);
    process.exit(r.bayrak.length ? 1 : 0);
  }

  console.error('kullanım:\n  node duzen-karsilastir.js al <ad>\n' +
    '  node duzen-karsilastir.js karsilastir <once> <sonra>\n' +
    '  node duzen-karsilastir.js <ad>');
  process.exit(2);
})();
