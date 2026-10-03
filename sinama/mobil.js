const { chromium } = require('/home/claude/.npm-global/lib/node_modules/playwright');
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const sayfalar = ['/','/yazilar.php','/ilkeler.php','/basvuru.php','/hakemlik.php','/bildiri.php','/yz.php','/kurul.php','/hakemler.php','/ara.php?f1=kalkinma&g=1&coz=1','/uygulama.php','/bekleyen.php','/tamga/KTG-2026-00001-7','/davet.php','/panel.php','/iletisim.php','/acikliklar.php','/oylama.php'];
  const olcu = [{w:360,h:740,ad:'telefon'},{w:768,h:1024,ad:'tablet'},{w:1366,h:900,ad:'masaustu'},{w:1920,h:1080,ad:'genis'}];
  let hata = 0;
  for (const o of olcu) {
    const ctx = await b.newContext({ viewport:{width:o.w,height:o.h}, isMobile:o.w<700, hasTouch:o.w<700 });
    for (const s of sayfalar) {
      const p = await ctx.newPage(); const konsol = [];
      p.on('pageerror', e => konsol.push('PE: '+e.message));
      p.on('console', m => { if (m.type()==='error' && !/ERR_|Failed to load/.test(m.text())) konsol.push(m.text()); });
      await p.goto('http://127.0.0.1:8941'+s, { waitUntil:'networkidle' }).catch(()=>{});
      const t = await p.evaluate(() => {
        const g = document.documentElement.clientWidth, kotu = [];
        document.querySelectorAll('body *').forEach(el => {
          const r = el.getBoundingClientRect(); if (r.width<=0) return;
          const cs = getComputedStyle(el);
          if (cs.position==='fixed' || r.left < -1000 || cs.overflowX==='auto' || cs.overflowX==='scroll') return;
          if (r.right > g+2 || r.left < -2) kotu.push(el.tagName.toLowerCase()+'.'+String(el.className).slice(0,40));
        });
        return { yatay: document.documentElement.scrollWidth > g+2, kotu: kotu.slice(0,3) };
      });
      if (t.yatay || konsol.length) { hata++; console.log('X '+o.ad+' '+s+(t.kotu.length?' tasan:'+t.kotu.join(','):'')+(konsol.length?' konsol:'+konsol[0]:'')); }
      await p.close();
    }
    await ctx.close();
  }
  console.log(hata ? ('SORUNLU: '+hata) : 'TUM OLCULERDE TEMIZ');
  await b.close();
})();
