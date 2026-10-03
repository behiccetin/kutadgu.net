/* =====================================================================
   SAĞDA KALAN BOŞLUK: kapı ölçümü. Depoya girmez.

   BULUNAN KUSURUN İMZASI
   ----------------------
   "Bu yazı neden sıkışık, sağında boşluk var." Bildirilen şey şudur:
   bir metin bloğu kabının soluna yaslanmış, birkaç satıra sarmış, ve
   sağında kabının içinde bomboş bir sütun kalmıştır. Yazı dar olduğu
   için değil, KABI GENİŞ OLDUĞU HÂLDE DAR TUTULDUĞU için sıkışıktır.

   Ölçülen örnek (başvuru sayfası, açılmış koşul satırı):
     kap 692 px · metin 499 px · sağda boş 181 px  (%26)

   Sebep her seferinde aynı sınıf hatadır: bir yere okunur satır
   uzunluğu diye bir sınır yazılır (max-width: 56ch), ama o sınır
   kabın genişliğiyle birlikte düşünülmez. Okunur satır bir üst
   sınırdır; kabı geniş bırakıp metni dar tutmak, boşluğu kabın içine
   hapsetmektir. Doğrusu ikisinden biridir:
     - kabı metnin ölçüsüne indirmek, ya da
     - kalan yeri BİR İŞE yaramaya çevirmek (iki sütun).

   ÖLÇÜT
   -----
   Bir metin bloğu için üçü birden aranır:
     1. En az iki satıra sarmış olmak (tek satırlık bir etiketin
        sağında yer kalması olağandır),
     2. Kabının içinde sağda BOŞ kalan yerin, kabın genişliğinin
        %18'inden ve 110 pikselden çok olması,
     3. İçinde en az kırk harflik metin bulunması.
   Üçü birden aranmalıdır: kısa bir rozetin, bir düğmenin ya da tek
   satırlık bir başlığın sağında yer kalması kusur değildir.

   ÖLÇÜM TUZAKLARI
     - "Kap" en yakın blok atadır; satır içi (inline) bir ata ölçü
       vermez. Kabın DOLGUSU düşülür: sağdaki dolgu boşluk değildir.
     - Sarma sayısı kutu yüksekliğinden hesaplanmaz; Range ile gerçek
       satır kutuları sayılır (OKUBENI 48).
     - Ölçüm iki genişlikte yapılır ama bayrak yalnız GENİŞ ekranda
       yanar: dar ekranda zaten boşluk kalmaz, kusur geniş ekrandadır.
     - Ortalanmış metin (text-align:center) ve ızgara/esnek kutu
       içinde kendi hizasını seçmiş öge bu ölçünün dışındadır: orada
       sağdaki yer bir karardır, artık değil.
     - Sayfa dili açıkça istenir (?lang=tr) — OKUBENI 6/25.
     - Katlanır bölümler (details) ölçümden önce açılır: kapalı bir
       metnin genişliği ölçülemez.

   Kullanım:
     KPORT=8941 node bosluk-kapi.js [--ayrinti]
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK = 'http://127.0.0.1:' + PORT;
const AYRINTI = process.argv.includes('--ayrinti');

const SAYFALAR = [
  '/', '/nasil-isler.php', '/yazilar.php', '/ara.php', '/istatistik.php',
  /* '/oylama.php' LİSTEDEN ÇIKARILDI (19 Ağustos 2026): sayfa kaldırıldı
     ve 410 (Gone) dönüyor. Bu liste SİTENİN SAYFALARINI sayar; kaldırılmış
     bir adresi burada tutmak kapının her koşumda 'ulaşılmadı' demesine yol
     açar ve gerçek bir erişim kusurunu gizlerdi. */
  '/hakemlik.php', '/bekleyen.php', '/hakemler.php', 
  '/ilkeler.php', '/kurul.php', '/yz.php', '/acikliklar.php',
  '/basvuru.php', '/destek.php', '/iletisim.php', '/harita.php',
  '/bildiri.php', '/dokum.php', '/kefil.php', '/hesap-kur.php',
  '/_olcum-panel.html',
];
/* TEK GENİŞLİKTE ÖLÇMEK, ÖLÇMEMEKTİR.
   İlk yazımda yalnız 1440 ve 820 vardı ve kapı yeşil verdi. Kusur
   1835 pikselde duruyordu: kap 1054'e çıkıyor, metin 92 karakterlik
   sınırda 819'da kalıyor ve sağda 223 piksel boşluk açılıyordu.
   Genişliğe bağlı bir kusuru tek genişlikte aramak, aramamaktır.
   Bayrak GENİŞ ekranlarda yanar; 820 yalnız ölçüm olarak yazılır. */
const GENISLIKLER = [1920, 1680, 1440, 820];
const BAYRAK_GENISLIKLER = [1920, 1680, 1440];
const OLCU_GENISLIK = 1440;   /* satır uzunluğu ölçüsünün uygulandığı genişlik */

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

function bosluklariOlc() {
  const cikti = [];
  const uzunlar = [];
  let sarmis = 0;
  const SEC = 'p,li,dd,blockquote,figcaption,h1,h2,h3,h4,h5';
  const yol = el => {
    const p = [];
    for (let e = el; e && e.tagName && p.length < 3; e = e.parentElement) {
      p.unshift(e.tagName.toLowerCase() + (e.className && typeof e.className === 'string'
        ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    }
    return p.join('>');
  };
  /* Gerçek satır sayısı: kutu yüksekliği bölü satır yüksekliği DEĞİL.
     Range, metnin çizildiği satır kutularını verir (OKUBENI 48). */
  const satirSayisi = el => {
    const r = document.createRange();
    r.selectNodeContents(el);
    const k = [...r.getClientRects()].filter(x => x.width > 1 && x.height > 1);
    const ust = [];
    k.forEach(x => { if (!ust.some(u => Math.abs(u - x.top) < 3)) ust.push(x.top); });
    return ust.length;
  };
  /* Dizgenin en geniş okuma ölçüsü, ögenin KENDİ yüzüyle piksele
     çevrilir: 'ch' rakam genişliğidir ve yazı tipine göre değişir. */
  const olcuGenis = el => {
    const d = document.createElement('div');
    d.style.cssText = 'position:absolute;visibility:hidden;left:-9999px;width:var(--olcu-metin-genis)';
    d.style.font = getComputedStyle(el).font;
    el.parentElement.appendChild(d);
    const w = d.getBoundingClientRect().width;
    d.remove();
    return w;
  };
  document.querySelectorAll(SEC).forEach(el => {
    const metin = (el.textContent || '').trim();
    if (metin.length < 40) return;
    const r = el.getBoundingClientRect();
    if (r.width < 40 || r.height < 4) return;
    const c = getComputedStyle(el);
    if (c.display === 'none' || c.visibility === 'hidden') return;
    if (c.textAlign === 'center' || c.textAlign === 'right') return;
    if (c.position === 'absolute' || c.position === 'fixed') return;
    /* Kap: en yakın blok ata. Satır içi ata ölçü vermez. */
    let kap = el.parentElement;
    while (kap && kap !== document.body) {
      const kc = getComputedStyle(kap);
      if (kc.display !== 'inline' && kap.getBoundingClientRect().width > 0) break;
      kap = kap.parentElement;
    }
    if (!kap) return;
    const kk = kap.getBoundingClientRect();
    const kc = getComputedStyle(kap);
    /* Kabın sağ DOLGUSU boşluk değildir: içerik kutusuna göre ölçülür. */
    const icSag = kk.right - (parseFloat(kc.paddingRight) || 0) - (parseFloat(kc.borderRightWidth) || 0);
    const icGen = kk.width - (parseFloat(kc.paddingLeft) || 0) - (parseFloat(kc.paddingRight) || 0);
    if (icGen < 200) return;
    const bos = Math.round(icSag - r.right);
    const satir = satirSayisi(el);
    if (satir < 2) return;
    /* ÇOK GENİŞ SATIR DA BİR KUSURDUR. Boşluğu kapatmak uğruna metni
       kabın tamamına yaymak, sarkacı öteki uca vurmaktır. Bu yüzden
       her ölçülen blok iki yandan da bakılır. */
    sarmis++;
    const olcu = olcuGenis(el);
    if (r.width > olcu + 8) uzunlar.push({ yol: yol(el), gen: Math.round(r.width),
      enCok: Math.round(olcu), metin: metin.slice(0, 34).replace(/\s+/g, ' ') });
    if (bos <= 0) return;
    /* KABIN GENİŞLİĞİNİ BAŞKA BİR ŞEY HAKLI ÇIKARIYOR MU?
       Bir bölüm girişinin 66 karakterde bitmesi kusur değildir: onun
       kabı bir bölümü tutar ve altındaki kart ızgarası kabın tamamını
       kullanır. Kusur, kabın YALNIZCA bu metin için var olduğu ve yine
       de metinden çok geniş olduğu hâldir. Ölçüt: kaptaki en geniş
       kardeş, kabın %90'ına ulaşıyor mu. Ulaşıyorsa genişlik bir
       karardır; ulaşmıyorsa kap boşuna geniştir. */
    let enGenis = 0;
    [...kap.children].forEach(k2 => {
      const kr = k2.getBoundingClientRect();
      if (kr.height > 2 && kr.width > enGenis) enGenis = kr.width;
    });
    if (enGenis >= icGen * 0.9) return;
    cikti.push({
      yol: yol(el), bos, kap: Math.round(icGen), gen: Math.round(r.width),
      enCok: Math.round(olcuGenis(el)),
      oran: Math.round((bos / icGen) * 100), satir,
      metin: metin.slice(0, 34).replace(/\s+/g, ' '),
    });
  });
  return { blok: cikti, uzun: uzunlar, sarmis };
}

(async () => {
  const tarayici = await chromium.launch();
  const bulgu = [];
  const uzun = [];
  const ulasilmayan = new Set();
  let olculen = 0, ogeSay = 0, bosluklu = 0;

  for (const g of GENISLIKLER) {
    const baglam = await tarayici.newContext({ viewport: { width: g, height: 1200 } });
    const s = await baglam.newPage();
    for (const yol of SAYFALAR) {
      const adres = KOK + yol + (yol.endsWith('.html') ? '' : '?lang=tr');
      const c = await s.goto(adres, { waitUntil: 'networkidle' }).catch(() => null);
      if (!c || c.status() >= 400) { ulasilmayan.add(yol); console.log('  ->     ' + yol + ' ULAŞILMADI'); continue; }
      /* Kapalı bir metnin genişliği ölçülemez: katlar açılır, adımlar
         gösterilir. Ölçülen şey biçimdir, açılış durumu değil. */
      await s.evaluate(() => {
        document.querySelectorAll('details').forEach(d => { d.open = true; });
        document.querySelectorAll('[data-adim]').forEach(e => { e.hidden = false; });
        const ic = document.getElementById('pnIc'); if (ic) ic.classList.remove('gizli');
      });
      await s.waitForTimeout(150);
      const r = await s.evaluate(bosluklariOlc);
      olculen++; ogeSay += r.sarmis; bosluklu += r.blok.length;
      if (!BAYRAK_GENISLIKLER.includes(g)) continue;
      r.blok.forEach(x => { if (x.bos > 110 && x.oran > 18) bulgu.push({ sayfa: yol, g, ...x }); });
      /* SATIR UZUNLUĞU ÖLÇÜSÜ YALNIZ EN DAR "GENİŞ" EKRANDA UYGULANIR.
         Daha geniş ekranlarda metin sütununun genişliği bir kusur değil,
         OKURUN KARARIDIR: dizge okuma/geniş/pano/tam ölçülerini sunar ve
         seçilen ölçü sütunu belirler. 1920'de her paragrafı "çok geniş"
         diye bayraklamak, okurun seçimini kusur saymak olurdu (453 kural
         yandı ve hiçbiri kusur değildi). Kabın boşuna geniş kalması ise
         her genişlikte kusurdur; o ölçü yukarıda, hepsinde çalışır. */
      if (g === OLCU_GENISLIK) r.uzun.forEach(x => uzun.push({ sayfa: yol, g, ...x }));
    }
    await baglam.close();
  }
  await tarayici.close();

  console.log('== Sağda kalan boşluk ==');
  olc(olculen + ' sayfa görünümü, ' + ogeSay + ' sarmış metin bloğu ölçüldü; '
      + bosluklu + ' tanesinin sağında yer kalıyor ('
      + GENISLIKLER.join('/') + ' px; bayrak: ' + BAYRAK_GENISLIKLER.join('/') + ' px)');

  /* Kusur ÖGENİN değil KURALIN kusurudur: aynı yol + aynı oran teklenir. */
  const m = new Map();
  bulgu.forEach(b => {
    const a = b.g + 'px ' + b.yol + ' %' + b.oran;
    if (!m.has(a)) m.set(a, { ...b, sayfalar: new Set() });
    m.get(a).sayfalar.add(b.sayfa);
  });
  const tek = [...m.entries()].sort((a, b) => b[1].bos - a[1].bos)
    .map(([a, o]) => a + '  ' + o.bos + 'px boş (kap ' + o.kap + ', metin ' + o.gen + ')  '
      + [...o.sayfalar].slice(0, 3).join(',') + '  «' + o.metin + '»');
  (AYRINTI ? tek : tek.slice(0, 30)).forEach(x => console.log('  ->     ' + x));

  den('sarmış hiçbir metnin sağında kabının %18\'inden çok boşluk kalmıyor',
      bulgu.length === 0, bulgu.length + ' öge / ' + tek.length + ' kural');

  const uTek = [...new Set(uzun.map(u => u.g + 'px ' + u.yol + ' ' + u.gen + '>' + u.enCok + 'px «' + u.metin + '»'))];
  (AYRINTI ? uTek : uTek.slice(0, 12)).forEach(x => console.log('  ->     çok geniş satır: ' + x));
  den('  hiçbir satır dizgenin en geniş okuma ölçüsünü aşmıyor',
      uzun.length === 0, uzun.length + ' öge / ' + uTek.length + ' kural');

  /* Ölçülemeyen sayfa sessizce atlanmaz: eksik ölçüm, ölçüm değildir. */
  den('  listedeki bütün sayfalar ölçüldü', ulasilmayan.size === 0,
      [...ulasilmayan].join(','));

  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
