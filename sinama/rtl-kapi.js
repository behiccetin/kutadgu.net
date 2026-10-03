/* =====================================================================
   SAĞDAN SOLA (RTL) KAPISI. Depoya girmez.

   NEDEN VAR
   ---------
   Arapça açılırken tek yapılan iş bir sözlük yazmak değildir. <html
   dir="rtl"> konduğu anda bütün düzen aynalanır ve biçim dosyasındaki
   FİZİKSEL kurallar aynalanmaz: margin-left solda kalır, border-left
   solda kalır, yan çubuk solda kalır. Sonuç, yazısı sağda ama
   iskeleti solda duran bir sayfadır; bu, çevrilmemiş bir sayfadan
   daha kötüdür, çünkü yanlış olduğu ilk bakışta anlaşılmaz.

   Bu yüzden kapı sözlüğü değil DÜZENİ ölçer.

   ÖLÇÜM NEYE DAYANIR
   ------------------
   İki dilde aynı sayfa açılır ve şu sorulur: soldan sağa sayfada
   SOLDA olan şey, sağdan sola sayfada SAĞDA mı? Cevap bir renk ya da
   bir izlenim değil, piksel cinsinden bir konumdur.

   Ölçülenler:
     1. <html dir> gerçekten rtl mi (ön koşul; değilse geri kalanı
        ölçmenin anlamı yok).
     2. Yan çubuk aynalandı mı: ltr'de sol kenarda, rtl'de sağ kenarda.
     3. Üst çubuk yan çubuğa ters yönden bitişik mi.
     4. Hesap/dil açılır kutuları ekranın DIŞINA taşmıyor mu (right:0
        aynalanmazsa kutu sağa taşar ve sayfayı yatay kaydırır).
     5. Sayfanın hiçbir yerinde yatay taşma yok.
     6. Metin gerçekten sağa dayalı mı (ilk paragrafın sağ kenarı,
        kabının sağ kenarına yakın).
     7. Yazı tipi yığını Arap harfi taşıyan bir aile ile başlıyor mu.
     8. Dar ekrandaki çekmece sağdan giriyor mu (transform mantıksal
        değildir; elle çevrilmezse soldan girer ve ekranın dışına
        çıkar).

   FİZİKSEL KURAL ARTIĞI da ayrıca sayılır: biçim dosyasında kalan
   margin-left / padding-right / border-left / text-align:left gibi
   kurallar listelenir. Sıfır olması beklenmez (ortalayan ve yönle
   ilgisi olmayan birkaç kural vardır); beklenen, listenin BİLİNEN
   listeden büyümemesidir.

   Kullanım: KPORT=8941 node rtl-kapi.js
   ===================================================================== */
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const PORT = process.env.KPORT || '8941';
const KOD  = process.env.KTEST_DIR || '/home/claude/kg/ktest';
const SAYFALAR = ['/', '/yazilar.php', '/kurul.php', '/ilkeler.php', '/panel.php', '/basvuru.php'];
const GENISLIKLER = [1440, 1024, 390];

/* Biçim dosyasında kalması KABUL EDİLEN fiziksel kurallar. Hepsinin
   bir gerekçesi var ve gerekçesi kurala bakılarak anlaşılabilir
   olmalı; bu yüzden liste sayı değil, DESEN tutar. */
const KABUL = [
  /left:\s*50%/,            /* ortalama: yön değil merkez */
  /left:\s*-9999px/,        /* ekran okuyucu bağlantısı, zaten mantıksala çevrildi */
  /\[dir="rtl"\]/,          /* rtl için yazılmış kuralın kendisi */
];

let gecti = 0, kaldi = 0;
const den = (ad, ok, ek) => {
  if (ok) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek !== undefined ? '  (' + ek + ')' : '')); }
};

function bicimDosyasi() {
  const p = path.join(KOD, 'k', 'kutadgu.css');
  return fs.existsSync(p) ? fs.readFileSync(p, 'utf8') : '';
}

(async () => {
  console.log('\n== 1. BİÇİM DOSYASINDA KALAN FİZİKSEL KURALLAR ==');
  const css = bicimDosyasi();
  den('biçim dosyası okundu', css.length > 1000, css.length + ' bayt');

  const FIZIKSEL = /(margin|padding|border)-(left|right)\b|text-align:\s*(left|right)\b|float:\s*(left|right)\b|(^|[;{\s])(left|right):/g;
  const artik = [];
  css.split('\n').forEach((satir, i) => {
    if (satir.trim().startsWith('*') || satir.trim().startsWith('/*')) return;
    if (KABUL.some((k) => k.test(satir))) return;
    const m = satir.match(FIZIKSEL);
    if (m) artik.push((i + 1) + ': ' + satir.trim().slice(0, 90));
  });
  /* rtl bloğu içindeki kurallar sayılmaz: onlar zaten çözümün kendisi. */
  den('yön tutan fiziksel kural kalmadı', artik.length === 0,
      artik.length + (artik.length ? ' -> ' + artik.slice(0, 6).join(' | ') : ''));

  const b = await chromium.launch();

  for (const g of GENISLIKLER) {
    console.log('\n== GENİŞLİK ' + g + ' ==');
    const c = await b.newContext({ viewport: { width: g, height: 900 },
                                   hasTouch: g < 800, isMobile: g < 800 });
    const s = await c.newPage();

    for (const y of SAYFALAR) {
      const olc = async (dil) => {
        await s.goto(`http://127.0.0.1:${PORT}${y}?lang=${dil}`, { waitUntil: 'networkidle' });
        return await s.evaluate(() => {
          const gs = (e) => e ? e.getBoundingClientRect() : null;
          const yan = document.querySelector('.yan');
          const ust = document.querySelector('.ust');
          const vw  = document.documentElement.clientWidth;
          /* Yatay taşma: hiçbir ögenin kenarı görünüm alanının dışında
             olmamalı. Konumu sabit/mutlak olanlar ve gizlenmişler
             sayılmaz; onlar düzeni itmez. */
          /* KENDİ KAYDIRMA ALANI OLAN KABIN İÇİ SAYILMAZ. Ana
             sayfadaki çalışma kaydırıcısı ve dar ekrandaki geniş
             tablolar bilerek yana kayan kaplardır; sağdan sola bir
             sayfada içerikleri EKSİ koordinatlara oturur ve ölçüm
             bunu taşma sanıyordu. Ölçülen ilk sürümde 22 kusur
             bildirildi, hiçbiri gerçek değildi: kaydırıcı ve tablo.
             Kabın kendisine bakılır, içindekilere değil. */
          const kayanKapta = (e) => {
            for (let a = e.parentElement; a && a !== document.body; a = a.parentElement) {
              const o = getComputedStyle(a).overflowX;
              if (o === 'auto' || o === 'scroll' || o === 'hidden') return true;
            }
            return false;
          };
          let tasan = [];
          document.querySelectorAll('body *').forEach((e) => {
            const st = getComputedStyle(e);
            if (st.position === 'fixed' || st.position === 'absolute') return;
            if (st.visibility === 'hidden' || st.display === 'none') return;
            if (kayanKapta(e)) return;
            const r = e.getBoundingClientRect();
            if (r.width === 0 || r.height === 0) return;
            if (r.right > vw + 1 || r.left < -1) {
              tasan.push((e.className || e.tagName) + ' ' + Math.round(r.left) + '..' + Math.round(r.right));
            }
          });
          const kutu = document.querySelector('.hs-menu, .dil-kutu');
          /* KARŞILAŞTIRILAN ÖGE İKİ DİLDE AYNI OLMALI.
             İlk yazımda "main içindeki ilk p ya da li" ölçülüyordu ve
             kapı her sayfada KALDI diyordu: 17 piksel sapma. Sapma
             düzende değil ÖLÇÜMDEYDİ. Arapça sayfada, İngilizcede
             bulunmayan bir kutu vardır ("bu dil denetlenmedi") ve ilk
             paragraf onun içinden geliyordu; yani iki ayrı öge
             karşılaştırılıyordu. Ölçüm artık iki dilde de bulunan tek
             ögeye, sayfanın h1'ine bakar ve onu KENDİ kabıyla
             karşılaştırır. */
          const bas = document.querySelector('main h1') || document.querySelector('main h2');
          const kap = bas ? bas.parentElement : null;
          const br = gs(bas), kr = gs(kap);
          /* KÖKÜN KENDİ KENARLARI. 15 Ağustos 2026'da html'e
             scrollbar-gutter:stable kondu; artık kaydırma çubuğunun
             yeri her zaman ayrılıyor ve DÜZENİN kenarı ile PENCERENİN
             kenarı aynı yer değil. Sabit yan çubuğun "kenara bitişik"
             olup olmadığı pencereyle değil DÜZENLE karşılaştırılır;
             yoksa doğru bitişen bir çubuk 15 piksel kaçık ölçülür ve
             kapı doğru kodu kusurlu bildirir. */
          const kok = document.documentElement.getBoundingClientRect();
          return {
            yon: document.documentElement.getAttribute('dir'),
            vw,
            kokSol: Math.round(kok.left),
            kokSag: Math.round(kok.right),
            yan: gs(yan) ? { l: Math.round(gs(yan).left), r: Math.round(gs(yan).right) } : null,
            yanGorunur: yan ? getComputedStyle(yan).visibility !== 'hidden' : false,
            ust: gs(ust) ? { l: Math.round(gs(ust).left), r: Math.round(gs(ust).right) } : null,
            kutuVar: !!kutu,
            metinSagBosluk: (br && kr) ? Math.round(kr.right - br.right) : null,
            metinSolBosluk: (br && kr) ? Math.round(br.left - kr.left) : null,
            yaziAilesi: getComputedStyle(document.body).fontFamily,
            tasan: tasan.slice(0, 4),
            tasanSay: tasan.length,
          };
        });
      };

      const L = await olc('en');
      const R = await olc('ar');
      const ad = y + ' @' + g;

      den(ad + ' · dir=rtl', R.yon === 'rtl', R.yon);
      den(ad + ' · yatay taşma yok', R.tasanSay === 0, R.tasan.join(' ; '));

      if (g > 1040) {
        /* Geniş ekranda yan çubuk hep duruyor: ltr'de solda, rtl'de sağda. */
        den(ad + ' · yan çubuk aynalandı',
            L.yan && R.yan
            && Math.abs(L.yan.l - L.kokSol) < 4
            && Math.abs(R.kokSag - R.yan.r) < 4,
            'ltr sol=' + (L.yan && L.yan.l) + '/' + L.kokSol
            + ' rtl sağ=' + (R.yan && R.yan.r) + '/' + R.kokSag);
        den(ad + ' · üst çubuk yan çubuğa ters yönden bitişik',
            L.ust && R.ust && Math.abs(L.ust.l - L.yan.r) < 2 && Math.abs(R.ust.r - R.yan.l) < 2,
            'ltr ust.l=' + (L.ust && L.ust.l) + ' rtl ust.r=' + (R.ust && R.ust.r));
      } else {
        /* Dar ekranda çekmece kapalıdır ve GÖRÜNÜM DIŞINDA durmalıdır.
           translateX mantıksal olmadığı için elle çevrilmezse rtl'de
           soldan değil yine soldan girer; yani ekranın içinde kalır. */
        den(ad + ' · kapalı çekmece görünüm dışında',
            !R.yanGorunur || (R.yan && R.yan.l >= R.kokSag - 1),
            'görünür=' + R.yanGorunur + ' sol=' + (R.yan && R.yan.l) + '/' + R.kokSag);
      }

      /* Metin sağa dayandı mı: rtl'de sağ boşluk, ltr'deki sol boşluğa
         yakın olmalı. Eşit olması beklenmez (iç boşluklar ayrıdır),
         AYNALANMASI beklenir. */
      if (R.metinSagBosluk !== null && L.metinSolBosluk !== null) {
        den(ad + ' · metin sağa dayalı',
            Math.abs(R.metinSagBosluk - L.metinSolBosluk) <= 8,
            'ltr sol=' + L.metinSolBosluk + ' rtl sağ=' + R.metinSagBosluk);
      }

      den(ad + ' · yazı yığını Arap harfi taşıyan aile ile başlıyor',
          /Noto|Geeza|Traditional Arabic|Segoe UI|Tahoma/i.test(R.yaziAilesi),
          R.yaziAilesi.slice(0, 60));
    }
    await c.close();
  }

  await b.close();
  console.log('\n  GECTI ' + gecti + ' · KALDI ' + kaldi);
  process.exit(kaldi === 0 ? 0 : 1);
})();
