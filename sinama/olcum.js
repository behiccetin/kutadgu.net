const { chromium } = require('playwright');
const sayfalar = ['/','/yazilar.php','/yz.php','/ilkeler.php','/hakemlik.php','/basvuru.php',
  '/kurul.php','/hakemler.php','/ara.php?f1=kalkinma&g=1&coz=1','/bekleyen.php','/acikliklar.php','/uygulama.php','/iletisim.php',
  '/bildiri.php','/panel.php','/destek.php','/kisi/oyveren-bir','/kefil.php?k=7ccca0642024812cc3680f3df5f5ea6b','/tamga/KTG-2026-00001-7'];
const olcu = [[360,760],[768,1024],[1366,900],[1920,1080]];
(async () => {
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  let kotu = 0;
  for (const [w,h] of olcu) {
    const ctx = await b.newContext({ viewport:{width:w,height:h} });
    const p = await ctx.newPage();
    await p.goto('http://127.0.0.1:8941/');
    await p.evaluate(()=>{try{localStorage.setItem('kutadgu-bildiri','1')}catch(e){}});
    for (const s of sayfalar) {
      const errs=[]; p.removeAllListeners('console');
      p.on('console', m=>{ if(m.type()==='error') errs.push(m.text()); });
      await p.goto('http://127.0.0.1:8941'+s+(s.includes('?')?'&':'?')+'lang=tr', {waitUntil:'networkidle'});
      await p.waitForTimeout(150);
      const r = await p.evaluate(() => {
        const de = document.documentElement;
        const tasan = [];
        document.querySelectorAll('body *').forEach(el=>{
          const k = el.getBoundingClientRect();
          if (k.width>0 && k.right > de.clientWidth+1.5) {
            const st=getComputedStyle(el);
            if (st.position==='fixed') return;
            /* Yatay kaydırılabilir bir kabın içindeki ögeler taşma
               sayılmaz: kaydırak ve çizelgeler böyle çalışır. */
            let a=el, kaydirilir=false;
            while (a && a!==document.body) {
              const s2=getComputedStyle(a);
              if (s2.overflowX==='auto'||s2.overflowX==='scroll') { kaydirilir=true; break; }
              a=a.parentElement;
            }
            if (!kaydirilir)
              tasan.push(el.tagName+'.'+(el.className||'').toString().slice(0,40)+' r='+Math.round(k.right));
          }
        });
        return { yatay: de.scrollWidth > de.clientWidth + 1, genislik: de.scrollWidth, ekran: de.clientWidth,
                 tasan: tasan.slice(0,3) };
      });
      if (r.yatay || r.tasan.length || errs.length) {
        kotu++;
        console.log(`${w}x${h} ${s}: yatay=${r.yatay} (${r.genislik}/${r.ekran}) ${r.tasan.join(' | ')} ${errs.length?'KONSOL:'+errs.slice(0,2).join(' '):''}`);
      }
    }
    await ctx.close();
  }
  console.log(kotu===0 ? 'TUM OLCULERDE TEMIZ' : kotu+' sorun');
  await b.close();
})();
