/* =====================================================================
   ARAYÜZ DÜZELTMELERİ: tarayıcı ölçümü. Depoya girmez.

   12 Ağustos akşamı bildirilen altı görsel kusuru ölçer. Hepsi ancak
   tarayıcıda bilinebilir: bir kuralın hangi medya sorgusunun içinde
   kaldığı, bir başlığın altındaki boşluk, bir kabın gerçek genişliği
   kaynağa bakarak görülmez.

   ÖLÇÜLEN
     1. [hidden] genel kuralı: hidden verilen hiçbir öge ekranda
        durmuyor. (Bu kusur iki ayrı yerde vurdu; kural artık tek.)
     2. Yayın ilkeleri: bütün bölümler açıkken "Sonraki bölüm" şeridi
        görünmüyor, tek bölüm kipinde görünüyor.
     3. Yazar ölçütleri: sayı kutusu kalktı, bilgi cümlede duruyor.
     4. Panel: geniş ekranda 1240 piksellik şeride hapsolmuyor.
     5. Sol menü: küme başlığı altındaki bağa yapışmıyor.
     6. Site haritası: menüdeki her sayfa haritada var.

   ÖLÇÜM TUZAKLARI
     - OKUBENI 39: [hidden] bir sınıf kuralına yenilir. Bu kapı tam da
       onu ölçüyor; ölçüt "öznitelik var mı" değil, EKRANDA DURUYOR MU.
     - OKUBENI 6/25: sayfa dili açıkça istenmeli (?lang=tr).
     - Genişlik ölçümü tek ekran boyunda yapılmaz: 1240'a hapsolma
       ancak 1240'tan geniş bir ekranda görülür.

   Kullanım:
     KPORT=8941 node arayuz-kapi.js
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK  = 'http://127.0.0.1:' + PORT;

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

/* Ekranda gerçekten duruyor mu: öznitelik değil, kutu ölçülür. */
const GORUNUR_SAY = (secici) => [...document.querySelectorAll(secici)]
  .filter(e => { const k = e.getBoundingClientRect(); return k.width > 0 && k.height > 0; }).length;

(async () => {
  const tarayici = await chromium.launch();
  const yap = async (w) => (await tarayici.newContext({ viewport: { width: w, height: 950 } })).newPage();

  /* ==================================================================
     1. [hidden] KURALI HER YERDE İŞLİYOR
     ================================================================== */
  console.log('== 1. [hidden] genel kuralı ==');
  const s = await yap(1600);
  const SAYFALAR = ['/', '/ilkeler.php', '/basvuru.php', '/yazilar.php', '/panel.php', '/harita.php'];
  let toplamHidden = 0, kacak = 0, kacakYer = [];
  for (const yol of SAYFALAR) {
    const y = await s.goto(KOK + yol + '?lang=tr', { waitUntil: 'networkidle' });
    if (!y || y.status() !== 200) { den('sayfa 200: ' + yol, false, String(y && y.status())); continue; }
    const r = await s.evaluate(() => {
      const l = [...document.querySelectorAll('[hidden]')];
      const kotu = l.filter(e => getComputedStyle(e).display !== 'none')
        .map(e => e.tagName.toLowerCase() + '.' + String(e.className || '').split(/\s+/)[0]);
      return { toplam: l.length, kotu: kotu };
    });
    toplamHidden += r.toplam;
    if (r.kotu.length) { kacak += r.kotu.length; kacakYer.push(yol + ': ' + r.kotu.join(',')); }
  }
  olc(SAYFALAR.length + ' sayfada ' + toplamHidden + ' adet hidden öge ölçüldü');
  den('hidden verilen hiçbir öge ekranda durmuyor', kacak === 0, kacakYer.join(' | ').slice(0, 160));
  den('  ölçülecek hidden öge gerçekten var (boşlukta geçen deneme değil)',
      toplamHidden >= 10, String(toplamHidden));

  /* ==================================================================
     1b. '.gizle' DÜZENDEN ÇIKARIR — SAYFAYI KAYDIRMAZ
     ------------------------------------------------------------------
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026, kurulun kendi tarayıcısında.
     Bildirim: "hâlâ kayıyor". Sayfa gerçekten kayıyordu: belge 2113
     piksel, pencere 1905; yatay kaydırma çubuğu vardı ve sayfa 208
     piksel sağa kayınca içerik sabit yan gezinmenin altına giriyordu.

     Sebep '.gizle' yardımcısıydı. Tek işi ögeyi düzenden çıkarmaktır,
     ama ölçüleri !important taşımıyordu ve form kuralı daha güçlüydü:

         input[type="text"] { width:100%; min-height:44px }   (0,1,1)
         .gizle             { width:1px; height:1px }         (0,1,0)

     Şerh formundaki tuzak alanı (bal kabağı) ekranda görünmüyor ama
     düzende 1600x44 piksel yer kaplıyor, position:absolute olduğu için
     kabından taşıyor ve belgeyi genişletiyordu.

     BU ÖLÇÜM GİRİŞ YAPMIŞ OKUYUCUYU TAKLİT EDER: şerh formu yalnızca
     ona basılır, bu yüzden kapı onu göremiyordu. Aynı işaretleme
     sayfaya eklenip belgenin genişleyip genişlemediğine bakılır.
     ================================================================== */
  console.log('\n== 1b. .gizle düzenden çıkarır ==');
  {
    await s.goto(KOK + '/yazilar.php?lang=tr', { waitUntil: 'networkidle' });
    const r = await s.evaluate(() => {
      const k = document.documentElement;
      const once = k.scrollWidth;
      const f = document.createElement('form'); f.className = 'serh-form kart';
      const i = document.createElement('input'); i.type = 'text'; i.className = 'serh-balkabi gizle';
      f.appendChild(i);
      (document.querySelector('main') || document.body).appendChild(f);
      const kutu = i.getBoundingClientRect();
      const o = { once, sonra: k.scrollWidth, g: Math.round(kutu.width), y: Math.round(kutu.height) };
      f.remove();
      return o;
    });
    olc('gizli alan ' + r.g + 'x' + r.y + ' piksel · belge ' + r.once + ' -> ' + r.sonra);
    den('gizli alan düzende yer KAPLAMIYOR', r.g <= 1 && r.y <= 1, r.g + 'x' + r.y);
    den('  sayfa yatayda genişlemiyor', r.sonra <= r.once, r.once + ' -> ' + r.sonra);
  }

  /* ==================================================================
     2. YAYIN İLKELERİ: BÖLÜM GEZİNMESİ
     ================================================================== */
  console.log('\n== 2. Yayın ilkeleri: bölüm gezinmesi ==');
  await s.goto(KOK + '/ilkeler.php?lang=tr', { waitUntil: 'networkidle' });
  const tekBolum = await s.evaluate(GORUNUR_SAY, '.blg-gec');
  const tekAcik = await s.evaluate(() =>
    [...document.querySelectorAll('[data-belge-govde] > section[id]')].filter(e => !e.hidden).length);
  den('açılışta tek bölüm açık', tekAcik === 1, String(tekAcik));
  den('  o bölümün sonunda gezinme şeridi var', tekBolum === 1, String(tekBolum));
  await s.click('.blg-hepsi'); await s.waitForTimeout(300);
  const hepAcik = await s.evaluate(() =>
    [...document.querySelectorAll('[data-belge-govde] > section[id]')].filter(e => !e.hidden).length);
  const hepGec = await s.evaluate(GORUNUR_SAY, '.blg-gec');
  olc('bütün bölümler açıkken: ' + hepAcik + ' bölüm, ' + hepGec + ' gezinme şeridi');
  den('"bütün bölümleri göster" gerçekten hepsini açıyor', hepAcik >= 10, String(hepAcik));
  den('  ve hiçbir "Sonraki bölüm" şeridi kalmıyor', hepGec === 0, String(hepGec));
  await s.click('.blg-hepsi'); await s.waitForTimeout(300);
  den('  geri dönünce şerit yine tek', (await s.evaluate(GORUNUR_SAY, '.blg-gec')) === 1);

  /* ==================================================================
     3. YAZAR ÖLÇÜTLERİ: SAYI KUTUSU YOK, BİLGİ CÜMLEDE
     ================================================================== */
  console.log('\n== 3. Yazar ölçütleri ==');
  const y3 = await s.evaluate(() => {
    const b = document.getElementById('yazar');
    if (!b) return null;
    return { kutu: b.querySelectorAll('.olcut').length, metin: b.textContent.replace(/\s+/g, ' ') };
  });
  den('yazar bölümü var', !!y3);
  if (y3) {
    den('  sayı kutusu kaldırıldı (Dr./ORCID/Scopus üç kart değil)', y3.kutu === 0, String(y3.kutu));
    den('  ORCID zorunluluğu cümlede duruyor', /ORCID/.test(y3.metin) && /zorunlu/i.test(y3.metin));
    den('  Scopus koşul gibi değil, isteğe bağlı olarak anlatılıyor',
        /Scopus/.test(y3.metin) && /(istenmez|isteğe bağlı)/i.test(y3.metin));
  } else { den('  sayı kutusu kaldırıldı', false, 'bölüm yok'); den('  ORCID cümlede', false); den('  Scopus', false); }
  /* Sayı kutusu ötekil yerlerde DURUYOR olmalı: kaldırılan şey biçimin
     kendisi değil, sayı olmayan şeyi sayı gibi göstermekti.

     EŞİK 2'DEN 1'E İNDİ ve sebebi yazılıyor: 13 Ağustos 2026 kurul
     kararıyla benzerlik raporu şartı kalktı ve o bölümdeki iki sayılık
     kutu (%15 / %5) artık basılmıyor. Ölçülemeyen bir eşiği büyük
     puntoyla basmak, bu sayfanın kendi kuralına aykırıydı zaten.

     Ölçüm artık yalnız sayıyı değil ETİKETLERİ de yazıyor: kutu sayısı
     sessizce düştüğünde hangi kutunun gittiği ölçüm satırından
     görülür, kapının kırmızıya dönmesini beklemek gerekmez. */
  const olcutBilgi = await s.evaluate(() => {
    const k = [...document.querySelectorAll('.olcut')];
    return { say: k.length,
             etiket: k.flatMap(x => [...x.querySelectorAll('span')].map(y => y.textContent.trim().slice(0, 34))) };
  });
  olc('sayı kutusu: ' + olcutBilgi.say + ' tane — ' + olcutBilgi.etiket.join(' | '));
  den('sayı kutusu gerçek sayıların olduğu yerlerde duruyor', olcutBilgi.say >= 1, String(olcutBilgi.say));

  /* ==================================================================
     4. PANEL GENİŞLİĞİ
     ================================================================== */
  console.log('\n== 4. Panel genişliği ==');
  for (const w of [1600, 1920]) {
    const p = await yap(w);
    await p.goto(KOK + '/panel.php?lang=tr', { waitUntil: 'networkidle' });
    const r = await p.evaluate(() => {
      const k = document.querySelector('.kap.pn'), s = document.querySelector('.sahne');
      if (!k || !s) return null;
      return { kap: Math.round(k.getBoundingClientRect().width),
               sahne: Math.round(s.getBoundingClientRect().width) };
    });
    olc(w + 'px ekran: sahne ' + (r ? r.sahne : '?') + 'px, panel ' + (r ? r.kap : '?') + 'px');
    den('  ' + w + 'px ekranda panel 1240 piksellik şeride hapsolmuyor',
        !!r && r.kap > 1240, r ? String(r.kap) : 'ölçülemedi');
    den('  ' + w + 'px ekranda panel sahneyi taşırmıyor',
        !!r && r.kap <= r.sahne, r ? r.kap + '>' + r.sahne : '-');
    await p.context().close();
  }

  /* ==================================================================
     5. SOL MENÜ: KÜME BAŞLIĞI ALTTAKİNE AİTTİR
     ================================================================== */
  console.log('\n== 5. Sol menü ==');
  await s.goto(KOK + '/?lang=tr', { waitUntil: 'networkidle' });
  const men = await s.evaluate(() => [...document.querySelectorAll('.yan-bol')].map(e => {
    const k = e.getBoundingClientRect();
    const son = e.nextElementSibling ? e.nextElementSibling.getBoundingClientRect() : null;
    const onc = e.previousElementSibling ? e.previousElementSibling.getBoundingClientRect() : null;
    return { ad: e.textContent.trim(),
             ust: onc ? Math.round(k.top - onc.bottom) : null,
             alt: son ? Math.round(son.top - k.bottom) : null };
  }));
  men.forEach(m => olc(m.ad + ': üst ' + m.ust + 'px, alt ' + m.alt + 'px'));
  den('menüde küme başlığı var', men.length >= 3, String(men.length));
  /* Bir başlık kendinden SONRAKİNE aittir: üstündeki boşluk altındakinden
     büyük olmalı. Eskiden alt boşluk NEGATİFTİ ve başlık alttaki bağın
     üstüne biniyordu. */
  const yapisik = men.filter(m => m.ust !== null && m.alt !== null && m.ust <= m.alt);
  den('hiçbir başlık alttaki maddeye yapışmıyor (üst boşluk > alt boşluk)',
      yapisik.length === 0, yapisik.map(m => m.ad).join(','));
  const negatif = men.filter(m => m.alt !== null && m.alt < 0);
  den('  hiçbir başlık alttaki maddenin üstüne binmiyor', negatif.length === 0, String(negatif.length));
  const adlar = men.map(m => m.ad.toLocaleLowerCase('tr'));
  den('küme adları ne bulunacağını söylüyor',
      adlar.some(a => a.indexOf('arşiv') >= 0) && adlar.some(a => a.indexOf('değerlendirme') >= 0)
      && adlar.some(a => a.indexOf('kural') >= 0), adlar.join(' | '));

  /* ==================================================================
     6. SİTE HARİTASI
     ================================================================== */
  console.log('\n== 6. Site haritası ==');
  const menuYollar = await s.evaluate(() =>
    [...document.querySelectorAll('.yan .gez a')].map(a => a.getAttribute('href').split('?')[0]));
  const h = await s.goto(KOK + '/harita.php?lang=tr', { waitUntil: 'networkidle' });
  den('harita 200 dönüyor', h && h.status() === 200, String(h && h.status()));
  const haritaYollar = await s.evaluate(() =>
    [...document.querySelectorAll('.hr-liste a')].map(a => a.getAttribute('href').split('?')[0]));
  olc('menüde ' + menuYollar.length + ' bağ, haritada ' + haritaYollar.length + ' bağ');
  const eksik = menuYollar.filter(y => haritaYollar.indexOf(y) < 0);
  den('menüdeki her sayfa haritada da var', eksik.length === 0, eksik.join(','));
  den('  harita menüden fazlasını gösteriyor', haritaYollar.length > menuYollar.length,
      haritaYollar.length + ' > ' + menuYollar.length);
  const acikMi = await s.evaluate(() => {
    const t = document.body.textContent;
    return { giris: /giriş ister/i.test(t), her: document.querySelectorAll('.hr-ack').length };
  });
  den('  giriş isteyen yer gizlenmemiş, imlenmiş', acikMi.giris);
  den('  her satırın bir açıklaması var', acikMi.her === haritaYollar.length,
      acikMi.her + '/' + haritaYollar.length);

  await tarayici.close();
  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
