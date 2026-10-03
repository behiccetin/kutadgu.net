/* =====================================================================
   ALT BİLGİ KAPISI. Depoya girmez.

   NEDEN VAR
   ---------
   Alt bilgi için gelen şikâyet ikiydi: "çok kalabalık" ve "geniş
   aralıklı". İkisi de bir izlenimdir; kapı onları sayıya çevirir.

   Ölçülen üç şey:

     1. SÜTUN DENGESİ. Izgara sütunları en uzunun boyuna gerer. Yayın
        11, Sistem 8, Açıklık 7 satırken en kısa sütunun altında 124
        piksellik bir boşluk kalıyordu; alt bilgi hem uzun hem delik
        görünüyordu. Kural: bağlantı sütunlarının madde sayısı
        birbirinden en çok 1 fark etsin.

     2. YÜKSEKLİK TAVANI. 390 piksellik bir telefonda alt bilgi 1547
        piksele çıkıyordu; yani sayfanın sonu bir alt bilgi değil,
        ikinci bir sayfaydı. Her genişlik için bir tavan konur.

     3. HİÇBİR BAĞLANTI KIRPILMIYOR. Sütunlar daraltılarak yükseklik
        düşürüldü; daraltmanın bedeli metnin kesilmesi olmamalı.
        Sarmak serbesttir, KIRPILMAK değildir.

   Ayrıca alt bilgideki her bağlantının gerçekten açıldığı denetlenir:
   bir alt bilgi, çalışmayan bağlantı taşıyorsa düzeni ne olursa olsun
   yanlıştır.

   Kullanım: KPORT=8941 node altbilgi-kapi.js
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
/* Tavanlar ölçülerek konmuştur: düzeltme sonrası değerin biraz
   üstünde. Amaç bugünkü sayıyı dondurmak değil, GERİ GİDİŞİ
   yakalamaktır. */
/* 15 Ağustos 2026: html'e scrollbar-gutter:stable kondu (sayfanın
   yana sıçramasını bitirmek için). Kullanılabilir genişlik her yerde
   ~15 piksel azaldı ve 1440'ta alt bilginin bir sütunu bir satır daha
   sardı: 470 -> 493. Bu bir geri gidiş DEĞİLDİR, ölçüm ortamının
   düzelmesidir — klasik kaydırma çubuğu çizen sistemlerde (Windows'ta
   Chrome, Firefox) kullanıcı zaten bu genişliği görüyordu; ölçüm
   örtüşen çubuklu başsız tarayıcıda 15 piksel iyimserdi. Tavan
   ölçülen değerin biraz üstüne çekildi; amaç bugünkü sayıyı dondurmak
   değil, GERİ GİDİŞİ yakalamaktır. */
const TAVAN = { 1440: 510, 1024: 500, 768: 720, 390: 1200, 360: 1250 };

let gecti = 0, kaldi = 0;
const den = (ad, ok, ek) => {
  if (ok) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek !== undefined ? '  (' + ek + ')' : '')); }
};

(async () => {
  const b = await chromium.launch();

  for (const g of Object.keys(TAVAN).map(Number).sort((x, y) => y - x)) {
    const c = await b.newContext({ viewport: { width: g, height: 900 },
                                   hasTouch: g < 800, isMobile: g < 800 });
    const s = await c.newPage();
    await s.goto(`http://127.0.0.1:${PORT}/`, { waitUntil: 'networkidle' });

    const r = await s.evaluate(() => {
      const f = document.querySelector('footer.alt');
      const ic = f.querySelector('.alt-ic');
      const sutun = [...ic.children].map((d) => ({
        bas: (d.querySelector('h2') || {}).textContent,
        madde: d.querySelectorAll('li').length,
        yuk: Math.round(d.getBoundingClientRect().height),
      }));
      /* KIRPILMA: metnin kendi genişliği kabından büyük ve kap onu
         kesiyor mu. Sarma (iki satıra inme) kırpılma değildir. */
      const kirpik = [];
      f.querySelectorAll('ul a').forEach((a) => {
        if (a.scrollWidth > a.clientWidth + 1) kirpik.push(a.textContent.trim());
      });
      return {
        yuk: Math.round(f.getBoundingClientRect().height),
        sutun,
        sutunSay: getComputedStyle(ic).gridTemplateColumns.split(' ').length,
        kirpik,
        yatay: document.documentElement.scrollWidth > document.documentElement.clientWidth + 1,
        baglar: [...f.querySelectorAll('a')].map((a) => a.getAttribute('href')),
      };
    });

    const listeli = r.sutun.filter((x) => x.madde > 0).map((x) => x.madde);
    const fark = Math.max(...listeli) - Math.min(...listeli);

    console.log('\n== GENİŞLİK ' + g + ' == alt bilgi ' + r.yuk + 'px · '
      + r.sutunSay + ' sütun · maddeler ' + listeli.join('/'));
    den(g + ' · sütunlar dengeli (fark <= 1)', fark <= 1, 'fark ' + fark);
    den(g + ' · yükseklik tavanı ' + TAVAN[g], r.yuk <= TAVAN[g], r.yuk + 'px');
    den(g + ' · hiçbir bağlantı kırpılmıyor', r.kirpik.length === 0, r.kirpik.slice(0, 3).join(' | '));
    den(g + ' · sayfa yana kaymıyor', !r.yatay);
    /* Marka sütununun altındaki boşluk bir kusur değildir; asıl kusur
       LİSTE sütunları arasındaki eşitsizlikti. Burada yalnız listeler
       karşılaştırılır. */

    if (g === 1440) {
      console.log('\n== BAĞLANTILAR ==');
      const gorulen = new Set();
      for (const h of r.baglar) {
        if (!h || h.startsWith('http') || h.startsWith('#') || gorulen.has(h)) continue;
        gorulen.add(h);
        const y = await s.request.get('http://127.0.0.1:' + PORT + h);
        den('  ' + h, y.status() < 400, 'HTTP ' + y.status());
      }
    }
    await c.close();
  }

  await b.close();
  console.log('\n  GECTI ' + gecti + ' · KALDI ' + kaldi);
  process.exit(kaldi === 0 ? 0 : 1);
})();
