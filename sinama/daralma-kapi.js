/* =====================================================================
   DARALAN HÜCRE / DİKEY UZAMA TARAMASI. Depoya girmez.

   BULUNAN KUSURUN İMZASI
   ----------------------
   Bir metin kutusu genişliğini kaybettiğinde satıra bir sözcük düşer
   ve kutu aşağı doğru uzar. Ekranı büyütmek işe yaramaz, çünkü daralan
   ekran değil KUTUdur: altı sütunlu bir çizelgede başlık hücresine
   90 piksel kalır ve otuz harflik bir makale adı on beş satır olur.

   Bu kusur iki kez ayrı ayrı bildirildi ve ikisinde de "burayı
   düzelttim" denip yanındaki aynı kusur görülmedi. Sebep şuydu:
   düzeltme SÜTUNUN YERİNE bağlanmıştı (td:first-child), oysa kusur
   içeriğe bağlıdır. Bu kapı, o kusuru artık yerine göre değil
   ÖLÇEREK arar ve bütün sayfalara bakar.

   ÖLÇÜT
   -----
   Bir ögenin "daralmış" sayılması için üçü birden:
     1. En az DORT satıra sarmış olmak (satır sayısı = yükseklik /
        satır yüksekliği),
     2. Satır başına ortalama on iki harften az düşmesi,
     3. İçinde en az on beş harflik metin bulunması.
   Üçü birden aranmalıdır: kısa bir etiketin iki satıra düşmesi kusur
   değildir, dar bir rozet de öyle.

   ÖLÇÜM TUZAKLARI
     - Sayfa dili açıkça istenir (?lang=tr); yerel istek İngilizceye
       düşer ve Türkçe metin hiç ölçülmemiş olur (OKUBENI 6/25).
     - Gizli öge ölçülmez: yüksekliği sıfır olan ya da [hidden]
       kabındaki metin ekranda daralmış olamaz.
     - Ölçüm İKİ genişlikte yapılır. Dar ekranda her şey dardır;
       kusur, GENİŞ ekranda bile daralan kutudur. Bayrak yalnız geniş
       ekranda yanar; 820 piksel yalnız ölçüm olarak yazılır.
     - `white-space:pre` ve tek sözcüklük uzun dizeler (kimlik, adres)
       sarmaz; onlar bu ölçünün dışındadır.

   Kullanım:
     KPORT=8941 node daralma-kapi.js [--ayrinti]
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK  = 'http://127.0.0.1:' + PORT;
const AYRINTI = process.argv.includes('--ayrinti');

/* Ölçülen sayfalar: menüdeki her genel sayfa, artı çalışma sayfası.
   Panel giriş ister; onun çizelgeleri panel-kapi.php ile ölçülür. */
const SAYFALAR = [
  '/', '/nasil-isler.php', '/yazilar.php', '/ara.php', '/istatistik.php',
  /* '/oylama.php' çıkarıldı: sayfa kaldırıldı, 410 dönüyor (19 Ağu 2026). */
  '/hakemlik.php', '/bekleyen.php', '/hakemler.php', 
  '/ilkeler.php', '/kurul.php', '/yz.php', '/acikliklar.php',
  '/basvuru.php', '/destek.php', '/iletisim.php', '/harita.php',
  '/bildiri.php', '/dokum.php',
];
const GENISLIKLER = [1440, 820];

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

/* Tarayıcı içinde çalışır.

   SATIR SAYISI METNİN KENDİSİNDEN ÖLÇÜLÜR.
   İlk iki yazımda satır sayısı "kutu yüksekliği / satır yüksekliği"
   ile hesaplanıyordu ve iki ayrı yönden yanlıştı:
     - Kutunun içinde iki ayrı punto varsa (başlık + açıklama)
       yükseklik tek bir satır yüksekliğine bölünmez; anasayfadaki
       kartlar böyle sahte bulgu verdi.
     - Bunu düzeltmek için "çocuğu olmayan öge" şartı konunca bu kez
       GERÇEK çizelge hücreleri elendi: bir hücrenin içinde <span> ve
       <code> olması onun sarmadığı anlamına gelmez. Yanlışlama bunu
       yakaladı: hücreler 60 piksele daraltıldığı hâlde kapı hiçbir şey
       görmedi. Isırmayan bir kapı, kapı değildir.

   Doğru ölçüm Range ile yapılır: bir METİN DÜĞÜMÜNÜN kaç satıra
   sardığı, o düğümün istemci dikdörtgenlerinin sayısıdır. Bu sayı
   kardeş ögelerin puntosundan, ızgaradan ve satır yüksekliğinden
   bağımsızdır; ölçülen şey doğrudan sarmanın kendisidir. */
function daralanlariBul() {
  const bulgu = [];
  const yurutec = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let d;
  while ((d = yurutec.nextNode())) {
    const t = (d.nodeValue || '').replace(/\s+/g, ' ').trim();
    if (t.length < 15) continue;
    if (t.indexOf(' ') < 0) continue;          /* tek sözcük sarmaz */
    const e = d.parentElement;
    if (!e) continue;
    const ad = e.tagName.toLowerCase();
    if (ad === 'script' || ad === 'style' || ad === 'noscript' || ad === 'template' || ad === 'title') continue;

    /* Gizli mi: ağaçta yukarı çıkılır (OKUBENI 30). */
    let gizli = false;
    for (let a = e; a && a !== document.documentElement; a = a.parentElement) {
      const s = getComputedStyle(a);
      if (s.display === 'none' || s.visibility === 'hidden' || a.hasAttribute('hidden')) { gizli = true; break; }
    }
    if (gizli) continue;
    const b = getComputedStyle(e);
    if (b.whiteSpace === 'pre' || b.whiteSpace === 'nowrap') continue;

    const r = document.createRange();
    r.selectNodeContents(d);
    const kutular = [...r.getClientRects()].filter(k => k.width > 0 && k.height > 0);
    if (kutular.length < 4) continue;           /* dörtten az satır kusur değil */
    const harfSatir = t.length / kutular.length;
    if (harfSatir >= 12) continue;
    const enGenis = Math.round(Math.max(...kutular.map(k => k.width)));

    const yer = ad + (e.id ? '#' + e.id : '')
      + (e.className && typeof e.className === 'string'
         ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '');
    bulgu.push({ yer, satir: kutular.length, harfSatir: Math.round(harfSatir * 10) / 10,
                 en: enGenis, ornek: t.slice(0, 45) });
  }
  return bulgu;
}

(async () => {
  const tarayici = await chromium.launch();
  const toplam = {};
  let acilan = 0, bayrak = 0;
  const liste = [];

  for (const g of GENISLIKLER) {
    const s = await (await tarayici.newContext({ viewport: { width: g, height: 950 } })).newPage();
    for (const yol of SAYFALAR) {
      let y;
      try { y = await s.goto(KOK + yol + (yol.includes('?') ? '&' : '?') + 'lang=tr',
                             { waitUntil: 'networkidle', timeout: 25000 }); }
      catch (e) { continue; }
      if (!y || y.status() !== 200) { console.log('  NOT    ' + yol + ' kod ' + y?.status()); continue; }
      if (g === GENISLIKLER[0]) acilan++;
      const b = await s.evaluate(daralanlariBul);
      if (!b.length) continue;
      toplam[g] = (toplam[g] || 0) + b.length;
      if (g === 1440) {                       /* bayrak yalnız geniş ekranda */
        bayrak += b.length;
        b.forEach(x => liste.push(yol + '  ' + x.yer + '  ' + x.satir + ' satır, '
          + x.harfSatir + ' harf/satır, ' + x.en + 'px  "' + x.ornek + '"'));
      }
    }
    await s.context().close();
  }

  console.log('== Daralan hücre taraması ==');
  olc(acilan + '/' + SAYFALAR.length + ' sayfa açıldı');
  olc('1440px: ' + (toplam[1440] || 0) + ' daralmış öge · 820px: ' + (toplam[820] || 0));
  if (liste.length && AYRINTI) liste.forEach(x => console.log('  ->     ' + x));
  else if (liste.length) liste.slice(0, 12).forEach(x => console.log('  ->     ' + x));

  /* ==================================================================
     PANEL ÇİZELGELERİ: KURALIN KENDİSİ ÖLÇÜLÜR
     ------------------------------------------------------------------
     Panelin okuma çizelgeleri giriş ister ve içleri yetkili bir API
     yanıtıyla dolar; bu kapı oraya giremez. Ama düzelten şey bir
     KURALDIR (.tb-ad) ve kural, sayfaya gerçek bir çizelge çizilerek
     ölçülebilir. Çizilen şey uydurma değil, panelin kendi biçimidir:
     aynı sınıflar, aynı kap, aynı sütun sayısı.

     Bu, "ölçemediğine GECTI verme" kuralının kaçamağı değil tersidir:
     ölçülemeyen veri yerine, ölçülebilen kural ölçülür ve neyin
     ölçülmediği burada yazılıdır — çizelgenin İÇERİĞİ ölçülmüyor.
     ================================================================== */
  console.log('\n== Panel çizelge kuralı ==');
  const pp = await (await tarayici.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  await pp.goto(KOK + '/panel.php?lang=tr', { waitUntil: 'networkidle' });
  const pr = await pp.evaluate(() => {
    const UZUN = 'Yapay Zekâ ve Otonom Sistemler Çağında Yükseköğretimin Yeniden '
               + 'Yapılanması: Mimari, Müfredat ve Kurumsal Dönüşüm Üzerine Kavramsal Bir Çerçeve';
    const kart = document.createElement('div');
    kart.className = 'pn-kart pn-tam';
    kart.innerHTML = '<div class="tablo-sar"><table class="tb"><tr>'
      + '<th>Zaman</th><th>Çalışma</th><th>Konum</th><th class="sayi">Süre</th>'
      + '<th class="sayi">Derinlik</th><th>Cihaz</th></tr><tr>'
      + '<td>12.08 04:11</td>'
      + '<td class="tb-ad"><span class="tb-bas" title="x">' + UZUN + '</span></td>'
      + '<td>Türkiye</td><td class="sayi">4 dk</td><td class="sayi">93%</td><td>masaüstü</td>'
      + '</tr></table></div>';
    /* Panelin kendi ızgarasının içine konur: dar sütun etkisi ancak
       orada doğar. */
    const yer = document.querySelector('.pn') || document.body;
    yer.appendChild(kart);
    const hucre = kart.querySelector('td.tb-ad');
    const kapsam = kart.querySelector('span.tb-bas');
    const k = hucre.getBoundingClientRect();
    const r = document.createRange(); r.selectNodeContents(kapsam.firstChild);
    const satir = [...r.getClientRects()].filter(x => x.width > 0).length;
    const sonuc = { en: Math.round(k.width), satir: satir,
                    kirpik: getComputedStyle(kapsam).webkitLineClamp,
                    tam: Math.round(kart.getBoundingClientRect().width) };
    kart.remove();
    return sonuc;
  });
  olc('deneme çizelgesi: başlık hücresi ' + pr.en + 'px, metin ' + pr.satir
      + ' satır, kırpma ' + pr.kirpik);
  den('panel çizelgesinde başlık hücresi daralmıyor', pr.en >= 180, pr.en + 'px');
  den('  uzun başlık üç satırda kırpılıyor (aşağı uzamıyor)',
      pr.kirpik === '3', String(pr.kirpik));
  den('  kural sütunun YERİNE değil içeriğe bağlı (.tb-ad)',
      await pp.evaluate(() => [...document.styleSheets]
        .flatMap(s => { try { return [...s.cssRules]; } catch (e) { return []; } })
        .some(r => r.selectorText && r.selectorText.indexOf('td.tb-ad') >= 0)
        || [...document.querySelectorAll('style')].some(s => s.textContent.indexOf('td.tb-ad') >= 0)));
  await pp.context().close();

  den('bütün sayfalar açıldı', acilan === SAYFALAR.length, acilan + '/' + SAYFALAR.length);
  den('geniş ekranda satıra bir sözcük düşen kutu YOK', bayrak === 0, String(bayrak));
  /* Ölçüm boşlukta geçmesin: tarama gerçekten metin gördü mü. */
  const s2 = await (await tarayici.newContext({ viewport: { width: 1440, height: 950 } })).newPage();
  await s2.goto(KOK + '/ilkeler.php?lang=tr', { waitUntil: 'networkidle' });
  const metinSay = await s2.evaluate(() =>
    [...document.querySelectorAll('p,li,td')].filter(e => (e.textContent || '').trim().length > 15).length);
  den('tarama gerçekten metin ölçtü (boşlukta geçen deneme değil)', metinSay > 50, String(metinSay));

  await tarayici.close();
  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
