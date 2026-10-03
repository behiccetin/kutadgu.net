/* =====================================================================
   UZUN SÖZCÜK KAPISI. Depoya girmez.

   NEDEN VAR
   ---------
   Almanca sayfada bir rozet kartı taşırdı: "Wirtschaftswissenschaften
   und Betriebswirtschaft" tek bir sözcük gibi davranır ve rozet
   sarmadığı için kart, ızgara ve sayfa arka arkaya genişledi. 1860
   piksellik bir ekranda bile sayfa yana kaydı; yani sorun ekranın
   darlığı değil, SÖZCÜĞÜN uzunluğuydu.

   ÖLÇÜM NEDEN VERİYE DAYANMAZ
   ---------------------------
   İlk yazımda bu kapı gerçek sayfaları Almanca açıp yatay taşma
   arıyordu ve GEÇTİ dedi — çünkü sınama verisinde o uzun alan adı yok.
   Kusur canlıda vardı, ölçümde yoktu. Veriye bağlı bir ölçüm, verinin
   olmadığı yerde kusuru göremez ve "geçti" demesi hiçbir şey söylemez.

   Bu yüzden kapı uzun sözcüğü KENDİSİ üretir: sayfadaki rozetlere ve
   kart başlıklarına 44 harflik bir Almanca bileşik sözcük yazar, sonra
   sayfanın yana kayıp kaymadığını ölçer. Böylece ölçülen şey verinin
   bugünkü hâli değil, KURALIN kendisi olur.

   Kullanım: KPORT=8941 node dil-tasma-kapi.js
   ===================================================================== */
const { chromium } = require('playwright');
const PORT = process.env.KPORT || '8941';
const SAYFALAR = ['/hakemler.php', '/bekleyen.php', '/', '/kurul.php', '/yazilar.php'];
const GENISLIKLER = [1860, 1440, 1024, 820, 390];
const UZUN = 'Wirtschaftswissenschaftenbetriebswirtschaftslehre';   /* 48 harf, tek sözcük */

(async () => {
  const b = await chromium.launch();
  let gecti = 0, kaldi = 0;
  const den = (ad, ok, ek) => {
    if (ok) { gecti++; console.log('  GECTI  ' + ad); }
    else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
  };
  for (const g of GENISLIKLER) {
    const s = await b.newPage({ viewport: { width: g, height: 900 } });
    for (const y of SAYFALAR) {
      await s.goto(`http://127.0.0.1:${PORT}${y}?lang=de`, { waitUntil: 'domcontentloaded' });
      const r = await s.evaluate((uzun) => {
        /* Rozetlere ve kart başlıklarına uzun sözcüğü yaz. */
        let n = 0;
        document.querySelectorAll('.rz, .yk-bas, .kk-ic b').forEach(function (e) {
          if (!e.textContent.trim()) return;
          e.textContent = uzun; n++;
        });
        /* TAŞMA, scrollWidth İLE ÖLÇÜLMEZ.
           İlk yazımda documentElement.scrollWidth kullanılıyordu ve
           1440 pikselde 1546 diyordu — ama sayfada yana taşan TEK BİR
           öge yoktu ve tarayıcı yatay kaydırmıyordu. scrollWidth,
           kırpılmış (overflow:hidden) içeriği de sayabiliyor; yani
           ölçüm, görünmeyen bir şeyi kusur diye bildiriyordu.
           Doğru ölçü gözle görülen taşmadır: hiçbir ögenin sağ kenarı
           görünüm alanının dışına çıkmamalı. Kırpılmış ve ekran
           okuyucuya ayrılmış ögeler (konumu absolute/fixed) sayılmaz;
           onlar düzeni itmez. */
        /* ÖLÇÜLEN ŞEY: KIRPILMIŞ METİN.
           İlk yazımda sayfanın yatay taşması ölçülüyordu ve düzeltmeden
           önce de sonra da 25/0 diyordu — yani kusuru hiç görmüyordu.
           Çünkü ekrandaki kusur sayfanın kayması DEĞİLDİ: rozet kendi
           kabında kalıyor, uzun Almanca sözcük ise ortasından kesiliyordu.
           "Wirtschaftswissenschaften und" diye biten bir rozet, taşmış
           bir sayfadan daha az göze çarpar ama okunamaz olması bakımından
           daha kötüdür.
           Doğru ölçü şudur: bir ögenin içeriği kabından geniş mi ve
           kabı onu kırpıyor mu. Sarma kuralı çalışıyorsa metin alt
           satıra iner ve kırpılma olmaz. */
        const kirpik = [];
        document.querySelectorAll('.rz, .yk-bas, .kk-ic b').forEach(function (e) {
          const st = getComputedStyle(e);
          const kirpiyor = st.overflowX === 'hidden' || st.overflowX === 'clip'
                        || st.textOverflow === 'ellipsis' || st.whiteSpace === 'nowrap';
          if (kirpiyor && e.scrollWidth > e.clientWidth + 1) {
            kirpik.push(e.tagName + '.' + String(e.className || '').slice(0, 30)
              + ' (' + e.scrollWidth + '>' + e.clientWidth + ')');
          }
        });
        const gorunum = document.documentElement.clientWidth;
        let ensag = gorunum, suclu = '';
        /* KENDİ KAYDIRMA ALANI OLAN BİR KABIN İÇİ SAYILMAZ.
           Ana sayfadaki çalışma kaydırıcısı ve dar ekrandaki geniş
           tablolar, bilerek yana kayan kaplardır: içlerindeki ögeler
           görünüm alanının dışına taşar ama SAYFAYI itmez, kendi
           kabında kayar. Bunları saymak, tasarımı kusur diye
           bildirmek olurdu. Ölçüm, kabın kendisine bakar. */
        const kayanKapta = function (e) {
          for (let a = e.parentElement; a && a !== document.body; a = a.parentElement) {
            const o = getComputedStyle(a).overflowX;
            if (o === 'auto' || o === 'scroll' || o === 'hidden') return true;
          }
          return false;
        };
        document.querySelectorAll('body *').forEach(function (e) {
          const p = getComputedStyle(e).position;
          if (p === 'absolute' || p === 'fixed') return;
          if (kayanKapta(e)) return;
          const k = e.getBoundingClientRect();
          if (k.width > 0 && k.right > ensag) {
            ensag = k.right;
            suclu = e.tagName + '.' + String(e.className || '').slice(0, 40);
          }
        });
        return { yazilan: n, gorunum: gorunum, ensag: Math.round(ensag), suclu: suclu,
                 kirpik: kirpik.length, kirpikOrnek: kirpik.slice(0, 2).join(' · ') };
      }, UZUN);
      const tasma = r.ensag - r.gorunum;
      den(`${y} @${g}px  sayfa kaymıyor`, tasma <= 1, `yatay taşma ${tasma}px · ${r.suclu}`);
      den(`  ${y} @${g}px  uzun sözcük kırpılmıyor (${r.yazilan} öge)`, r.kirpik === 0,
          `${r.kirpik} öge kırpık · ${r.kirpikOrnek}`);
    }
    await s.close();
  }
  await b.close();
  console.log(`\n----------------------------------------\nGECTI: ${gecti}   KALDI: ${kaldi}`);
  process.exit(kaldi > 0 ? 1 : 0);
})();
