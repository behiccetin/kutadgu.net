const { chromium } = require('playwright');
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  for (const s of ['/','/yazilar.php','/yz.php','/tamga/KTG-2026-00001-7','/panel.php']) {
    const ctx=await b.newContext({viewport:{width:1366,height:900}});
    const p=await ctx.newPage();
    let n=0, bayt=0; const tur={};
    p.on('response', async r=>{ n++; try{const h=r.headers(); const l=+(h['content-length']||0);
      bayt+=l; const t=(h['content-type']||'').split(';')[0]; tur[t]=(tur[t]||0)+1;}catch(e){} });
    const t0=Date.now();
    await p.goto('http://127.0.0.1:8941'+s+'?lang=tr',{waitUntil:'load'});
    const m=await p.evaluate(()=>{const t=performance.getEntriesByType('navigation')[0];
      const fp=performance.getEntriesByType('paint').find(x=>x.name==='first-contentful-paint');
      return {dom:Math.round(t.domContentLoadedEventEnd), yuk:Math.round(t.loadEventEnd), fcp:fp?Math.round(fp.startTime):null};});
    console.log(s.padEnd(20), 'istek='+n, 'bayt='+Math.round(bayt/1024)+'KB',
      'FCP='+m.fcp+'ms', 'DOM='+m.dom+'ms', 'yuk='+m.yuk+'ms');
    await ctx.close();
  }
  await b.close();
})();
