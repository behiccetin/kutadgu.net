/* Döküm sayfası: taşma, konsol hatası, dokunma hedefi. Depoya girmez. */
const { chromium } = require('playwright');
const GEN = [1920, 1600, 1400, 1320, 1200, 1024, 768, 390];
const S = 'http://127.0.0.1:8941';
(async () => {
  const b = await chromium.launch();
  let hata = 0, n = 0;
  for (const dil of ['tr', 'en']) {
    for (const g of GEN) {
      const ctx = await b.newContext({ viewport: { width: g, height: 900 }, serviceWorkers: 'block' });
      const p = await ctx.newPage();
      const konsol = [];
      p.on('console', m => { if (m.type() === 'error') konsol.push(m.text()); });
      p.on('pageerror', e => konsol.push('pageerror: ' + e.message));
      await p.goto(`${S}/dokum.php?lang=${dil}&z=${g}`, { waitUntil: 'networkidle' });
      const tasma = await p.evaluate(() => {
        const kotu = [];
        const g = document.documentElement.clientWidth;
        document.querySelectorAll('body *').forEach(el => {
          if (el.closest('.tablo-sar, .dk-kod, [style*="overflow"]')) return;
          const r = el.getBoundingClientRect();
          if (r.width === 0) return;
          if (r.right > g + 1 || r.left < -1) kotu.push(el.className || el.tagName);
        });
        return [...new Set(kotu)].slice(0, 5);
      });
      const kucukHedef = await p.evaluate(() => {
        const k = [];
        document.querySelectorAll('a.d, button.d').forEach(el => {
          const r = el.getBoundingClientRect();
          if (r.height > 0 && r.height < 32) k.push((el.textContent || '').trim().slice(0, 24) + ' h=' + Math.round(r.height));
        });
        return [...new Set(k)];
      });
      n++;
      if (tasma.length || konsol.length || kucukHedef.length) {
        hata++;
        console.log(`  ${dil} ${g}px  tasma=${JSON.stringify(tasma)} konsol=${JSON.stringify(konsol)} hedef=${JSON.stringify(kucukHedef)}`);
      }
      await ctx.close();
    }
  }
  console.log(`olculen: ${n} ekran x dil, sorunlu: ${hata}`);
  await b.close();
})();
