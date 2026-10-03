const { chromium } = require('playwright');
(async()=>{
  const b=await chromium.launch({executablePath:'/opt/pw-browsers/chromium'});
  let hata=0;
  // 1) Mobilde cekmece
  {
    const p=await (await b.newContext({viewport:{width:390,height:844}})).newPage();
    await p.goto('http://127.0.0.1:8941/?lang=tr');
    await p.evaluate(()=>{try{localStorage.setItem('kutadgu-bildiri','1')}catch(e){}});
    await p.reload({waitUntil:'networkidle'});
    const kapali=await p.evaluate(()=>getComputedStyle(document.getElementById('yan')).visibility);
    await p.click('[data-gez-dg]'); await p.waitForTimeout(400);
    const acik=await p.evaluate(()=>getComputedStyle(document.getElementById('yan')).visibility);
    console.log('cekmece kapali/acik:',kapali,acik); if(kapali!=='hidden'||acik!=='visible')hata++;
    await p.keyboard.press('Escape'); await p.waitForTimeout(400);
    // arama dugmesi
    await p.click('[data-ara-ac]'); await p.waitForTimeout(250);
    const ara=await p.evaluate(()=>getComputedStyle(document.querySelector('.ust-ara')).display);
    console.log('arama kutusu acildi:',ara); if(ara==='none')hata++;
    await p.screenshot({path:'ss/n-tel-cekmece.png'});
  }
  // 2) Masaustunde profil menusu
  {
    const p=await (await b.newContext({viewport:{width:1440,height:900}})).newPage();
    await p.goto('http://127.0.0.1:8941/?lang=tr');
    await p.evaluate(()=>{try{localStorage.setItem('kutadgu-bildiri','1')}catch(e){}});
    await p.reload({waitUntil:'networkidle'});
    await p.waitForTimeout(900);
    await p.click('[data-hs-dg]'); await p.waitForTimeout(300);
    const g=await p.evaluate(()=>!document.querySelector('[data-hs-menu]').hasAttribute('hidden'));
    console.log('profil menusu acildi:',g); if(!g)hata++;
    await p.screenshot({path:'ss/n-profil.png'});
  }
  // 3) Arsivde arama ve siralama
  {
    const p=await (await b.newContext({viewport:{width:1440,height:900}})).newPage();
    await p.goto('http://127.0.0.1:8941/yazilar.php?lang=tr',{waitUntil:'networkidle'});
    await p.evaluate(()=>{try{localStorage.setItem('kutadgu-bildiri','1')}catch(e){}});
    await p.reload({waitUntil:'networkidle'});
    await p.fill('#ara','kentlesme'); await p.waitForTimeout(400);
    const n=await p.evaluate(()=>Array.from(document.querySelectorAll('[data-ara]')).filter(x=>x.style.display!=='none').length);
    console.log('turkce harfsiz arama sonucu:',n); if(n!==1)hata++;
    await p.fill('#ara',''); await p.waitForTimeout(300);
    await p.selectOption('#siraSec','ad'); await p.waitForTimeout(400);
    const ilk=await p.evaluate(()=>document.querySelector('#dizi [data-ara]').getAttribute('data-bas'));
    await p.selectOption('#siraSec','eski'); await p.waitForTimeout(400);
    const ilk2=await p.evaluate(()=>document.querySelector('#dizi [data-ara]').getAttribute('data-tarih'));
    console.log('ada gore ilk:',ilk,'| eskiye gore ilk:',ilk2);
    await p.click('[data-suz="hakemli"]'); await p.waitForTimeout(300);
    const h=await p.evaluate(()=>Array.from(document.querySelectorAll('[data-ara]')).filter(x=>x.style.display!=='none').length);
    const bekle=await p.evaluate(()=>Array.from(document.querySelectorAll('[data-ara]')).filter(x=>(x.getAttribute('data-tur')||'')==='hakemli').length);
    console.log('hakemli suzgeci:',h,'(beklenen',bekle+')'); if(h!==bekle)hata++;
    await p.screenshot({path:'ss/n-arsiv-suz.png'});
  }
  console.log(hata===0?'ETKILESIM TEMIZ':hata+' sorun');
  await b.close();
})();
