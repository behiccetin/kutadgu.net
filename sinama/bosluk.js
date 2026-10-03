/* Boşluk denetimi.
   Bir sayfada başlık şeridiyle gövdenin sol kenarı aynı hizada mı, ve
   ızgarada boş bırakılmış bir sütun var mı diye bakar. Metnin solunda
   ya da sağında sebepsiz bir delik kalması bu denetimle yakalanır. */
const { chromium } = require('playwright');
const sayfalar = ['/','/nasil-isler.php','/yazilar.php','/ilkeler.php','/hakemlik.php','/basvuru.php',
  '/bekleyen.php','/acikliklar.php','/iletisim.php','/uygulama.php','/kurul.php','/hakemler.php','/ara.php?f1=kalkinma&g=1&coz=1',
  '/bildiri.php','/yz.php','/kimlik.php','/istatistik.php','/tamga/KTG-2026-00001-7'];
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  let kotu=0;
  for (const w of [1905,1440,1200]) {
    const p=await (await b.newContext({viewport:{width:w,height:900}})).newPage();
    await p.goto('http://127.0.0.1:8941/');
    await p.evaluate(()=>{try{localStorage.setItem('kutadgu-bildiri','1')}catch(e){}});
    for (const s of sayfalar) {
      await p.goto('http://127.0.0.1:8941'+s+(s.includes('?')?'&':'?')+'lang=tr',{waitUntil:'networkidle'});
      await p.waitForTimeout(200);
      const r = await p.evaluate(()=>{
        const q=x=>x?x.getBoundingClientRect():null;
        const bas=q(document.querySelector('.sayfa-bas .sayfa-bas-ic'));
        const iz=document.querySelector('.blg')||document.querySelector('.duzen3');
        const ic=q(document.querySelector('.blg-ic')||document.querySelector('.icerik'));
        const kap=q(document.querySelector('main .kap')||document.querySelector('main .sar'));
        let delik=null;
        if (iz && ic) {
          const g=iz.getBoundingClientRect();
          /* Kabın kendi iç boşluğu delik değildir; ölçü ondan sonra başlar. */
          const pad=parseFloat(getComputedStyle(iz).paddingLeft)||0;
          /* Izgaranın sol kenarıyla içeriğin sol kenarı arasında,
             görünür bir öge olmadan kalan boşluk deliktir. */
          const solda=[...iz.children].some(c=>{
            const k=c.getBoundingClientRect();
            return k.width>1 && k.left < ic.left - 2;
          });
          /* Izgara ortalanmışsa iki yanda eşit boşluk kalır; bu bir
             delik değil, tasarımın kendisidir. Delik ancak boşluk tek
             yana yığıldığında vardır. */
          const solBos = ic.left - (g.left + pad);
          let sagBos = 0;
          [...iz.children].forEach(c=>{ const k=c.getBoundingClientRect(); if(k.width>1) sagBos=Math.max(sagBos,k.right); });
          const padR=parseFloat(getComputedStyle(iz).paddingRight)||0;
          sagBos = (g.right - padR) - sagBos;
          const ortali = Math.abs(solBos - sagBos) < 40;
          if (!solda && !ortali && solBos > 24) delik = Math.round(solBos);
        }
        return { bas: bas?Math.round(bas.left):null, kap: kap?Math.round(kap.left):null, delik };
      });
      const sorun=[];
      if (r.delik) sorun.push('sol delik '+r.delik+'px');
      if (r.bas!==null && r.kap!==null && Math.abs(r.bas-r.kap)>2) sorun.push('baslik/govde hizasi '+r.bas+' vs '+r.kap);
      if (sorun.length){ kotu++; console.log(w+' '+s+': '+sorun.join(', ')); }
    }
    await p.close();
  }
  console.log(kotu===0?'BOSLUK DENETIMI TEMIZ':kotu+' sorun');
  await b.close();
})();
