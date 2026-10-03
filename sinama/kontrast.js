const { chromium } = require('playwright');
const sayfalar=['/','/yazilar.php','/yz.php','/panel.php','/ilkeler.php','/basvuru.php','/kurul.php','/hakemler.php','/ara.php?f1=kalkinma&g=1&coz=1','/tamga/KTG-2026-00001-7'];
function lum(r,g,b){const c=[r,g,b].map(v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)});return .2126*c[0]+.7152*c[1]+.0722*c[2]}
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  const ctx=await b.newContext({viewport:{width:1366,height:900}});
  const p=await ctx.newPage();
  await p.goto('http://127.0.0.1:8941/');
  await p.evaluate(()=>{try{localStorage.setItem('kutadgu-bildiri','1')}catch(e){}});
  let n=0;
  for(const tema of ['acik','koyu']){
    for(const s of sayfalar){
      await p.goto('http://127.0.0.1:8941'+s+'?lang=tr',{waitUntil:'networkidle'});
      await p.evaluate(t=>{document.documentElement.setAttribute('data-tema',t);try{localStorage.setItem('kutadgu-tema',t)}catch(e){}},tema);
      await p.waitForTimeout(250);
      const kotu=await p.evaluate(()=>{
        function pars(c){const m=c.match(/rgba?\(([\d.]+),\s*([\d.]+),\s*([\d.]+)(?:,\s*([\d.]+))?\)/);return m?[+m[1],+m[2],+m[3],m[4]===undefined?1:+m[4]]:null}
        function L(r,g,bb){const c=[r,g,bb].map(v=>{v/=255;return v<=.03928?v/12.92:Math.pow((v+.055)/1.055,2.4)});return .2126*c[0]+.7152*c[1]+.0722*c[2]}
        function zemin(el){let e=el;while(e){const st=getComputedStyle(e);
          const g=st.backgroundImage||'';
          if(g&&g.indexOf('gradient')>=0){
            /* Degrade zeminlerde en aydinlik duragi al: karsitligin en
               kotu oldugu nokta odur. */
            const m=g.match(/rgba?\([^)]+\)/g)||[];
            let en=null,eniyi=-1;
            m.forEach(x=>{const c=pars(x);if(c&&c[3]>.5){const l=L(c[0],c[1],c[2]);if(l>eniyi){eniyi=l;en=c}}});
            if(en)return en;
          }
          const c=pars(st.backgroundColor);if(c&&c[3]>.5)return c;e=e.parentElement}return [255,255,255,1]}
        const out=[];
        document.querySelectorAll('p,span,a,li,td,th,b,h1,h2,h3,h4,button,label,small,em,i,div').forEach(el=>{
          if(!el.childNodes.length)return;
          let t='';el.childNodes.forEach(n=>{if(n.nodeType===3)t+=n.textContent});
          if(!t.trim())return;
          const st=getComputedStyle(el);
          if(st.visibility==='hidden'||st.display==='none'||+st.opacity<.5)return;
          const k=el.getBoundingClientRect(); if(k.width<2||k.height<2)return;
          const f=pars(st.color); if(!f)return;
          const z=zemin(el);
          const o=(Math.max(L(f[0],f[1],f[2]),L(z[0],z[1],z[2]))+.05)/(Math.min(L(f[0],f[1],f[2]),L(z[0],z[1],z[2]))+.05);
          const px=parseFloat(st.fontSize),kalin=parseInt(st.fontWeight)>=700;
          const esik=(px>=24||(px>=18.66&&kalin))?3:4.5;
          if(o<esik-0.02) out.push({m:t.trim().slice(0,32),o:o.toFixed(2),esik,r:st.color,z:'rgb('+z.slice(0,3).join(',')+')'});
        });
        return out.slice(0,6);
      });
      if(kotu.length){n+=kotu.length;console.log(tema,s,JSON.stringify(kotu));}
    }
  }
  console.log(n===0?'KARSITLIK TEMIZ':n+' dusuk karsitlik');
  await b.close();
})();
