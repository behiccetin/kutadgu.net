/* Kurul sayfası: sekiz genişlikte yatay taşma, konsol hatası ve
   üç kümenin görünürlüğü. Depoya girmez. */
const { chromium } = require('playwright');
const KOK = 'http://127.0.0.1:8941';
const olcu = [1920, 1600, 1400, 1320, 1200, 1024, 768, 390];
const diller = ['tr', 'en'];

(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  let kotu = 0;
  for (const w of olcu) {
    const ctx = await b.newContext({ viewport: { width: w, height: 900 }, serviceWorkers: 'block' });
    const p = await ctx.newPage();
    for (const dil of diller) {
      const errs = [];
      p.removeAllListeners('console');
      p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
      await p.goto(KOK + '/kurul.php?lang=' + dil, { waitUntil: 'networkidle' });
      await p.waitForTimeout(120);
      const r = await p.evaluate(() => {
        const de = document.documentElement;
        const tasan = [];
        document.querySelectorAll('body *').forEach(el => {
          const k = el.getBoundingClientRect();
          if (!(k.width > 0 && k.right > de.clientWidth + 1.5)) return;
          if (getComputedStyle(el).position === 'fixed') return;
          /* Yatay kaydırılabilir bir kabın içi taşma sayılmaz. */
          let a = el, kaydirilir = false;
          while (a && a !== document.body) {
            const s = getComputedStyle(a);
            if (s.overflowX === 'auto' || s.overflowX === 'scroll') { kaydirilir = true; break; }
            a = a.parentElement;
          }
          if (kaydirilir) return;
          tasan.push(el.tagName.toLowerCase() + '.' + (el.className || '').toString().split(' ')[0]
            + ' right=' + Math.round(k.right));
        });
        const kume = id => {
          const h = document.getElementById(id);
          if (!h) return null;
          let n = h.nextElementSibling, kart = 0, satir = new Set();
          while (n && n.tagName !== 'H2') {
            n.querySelectorAll('.kk-sar').forEach(k => {
              kart++; satir.add(Math.round(k.getBoundingClientRect().top));
            });
            n = n.nextElementSibling;
          }
          return { baslik: h.textContent.trim(), kart, satir: satir.size };
        };
        return {
          govdeTasma: de.scrollWidth - de.clientWidth,
          tasan: tasan.slice(0, 6),
          kurucu: kume('kurucu'), gorevdeki: kume('gorevdeki'), onursal: kume('onursal'),
        };
      });
      const kotuMu = r.tasan.length > 0 || r.govdeTasma > 1 || errs.length > 0;
      if (kotuMu) kotu++;
      const k = x => x ? `${x.kart} kart/${x.satir} satır` : 'YOK';
      console.log(
        `${String(w).padStart(4)}px ${dil}  ${kotuMu ? 'TASMA' : ' temiz'}` +
        `  govde=${r.govdeTasma}  kurucu:${k(r.kurucu)}  gorevdeki:${k(r.gorevdeki)}  onursal:${k(r.onursal)}` +
        (errs.length ? `  KONSOL: ${errs[0]}` : '') +
        (r.tasan.length ? `  ${r.tasan.join(' | ')}` : '')
      );
    }
    await ctx.close();
  }
  await b.close();
  console.log(kotu === 0 ? '\nSONUC: sekiz genislikte iki dilde taşma ve konsol hatası yok.' : `\nSONUC: ${kotu} olcumde sorun var.`);
  process.exit(kotu ? 1 : 0);
})();
