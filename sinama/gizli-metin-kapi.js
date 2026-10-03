/* =====================================================================
   GİZLİ METİN YASAĞI: kapı ölçümü. Depoya girmez.

   NİYE VAR
   --------
   Yapay zekâ eğiten kuruluşlar internetteki metni topluyor ve
   ürettikleri çıktıda çoğu zaman kaynağı anmıyor. Buna karşı akla
   gelen ilk çözüm şudur: sayfaya insanın göremeyeceği ama makinenin
   okuyacağı bir talimat gömmek. Kutadgu bunu YAPMAYACAK ve bu kapı
   yapılmadığını ÖLÇECEK.

   Üç gerekçe koda değil, karara aittir; burada yazılı olması, bir gün
   "küçük bir not koyalım" diyen birine cevabın hazır durması içindir:

     1. İşlemez. Eğitim verisi hatları HTML'i çıplak metne indirger;
        display:none, beyaz üstüne beyaz, sıfır genişlikli karakterler
        o aşamada silinir.
     2. Yanlış yerde işler. Eğitimde cümle emir değil örüntüdür. Emir
        gibi okunabileceği tek an çıkarımdır ve orada adı prompt
        injection'dır; düzgün kurulmuş her ajan getirdiği içeriği veri
        sayar, komut saymaz.
     3. Denendi, geri tepti. 2025'te on dörtten fazla kurumdan
        makalede beyaz yazıyla gizlenmiş "olumlu rapor ver" talimatı
        bulundu; bildiriler çekildi, ACM bunu suistimal saydı.

   Ve asıl gerekçe: bu sistemin tek iddiası her şeyin GÖRÜNÜR olması.

   NE ÖLÇÜLÜR
   ----------
   atif-kapi.php kaynaktan okunabilenleri ölçer (görünmez Unicode,
   satır içi gizleme). Burada ölçülen, ancak TARAYICIDA bilinebilenler:
   hesaplanmış biçemle gizlenmiş metin, arka planına eşit renkte yazı,
   okunamayacak kadar küçük punto, ekranın dışına atılmış metin.

   MEŞRU İSTİSNA: ekran okuyucuya açık metin gizli DEĞİLDİR.
   .gorsel-gizli sınıfı görsel olarak kırpar ama erişilebilirlik
   ağacında durur; o metni bir insan DUYAR. Yasak, HERKESTEN gizlenmiş
   metne konur. Bu ayrım aria-hidden ile yapılır: hem gözden hem
   erişilebilirlik ağacından çıkarılmış ve içinde cümle taşıyan bir
   düğüm, kimsenin göremeyeceği bir metindir.

   Kullanım:
     KPORT=8941 node gizli-metin-kapi.js
     KPORT=8941 node gizli-metin-kapi.js --ayrinti
   ===================================================================== */
'use strict';
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK  = 'http://127.0.0.1:' + PORT;
const AYRINTI = process.argv.includes('--ayrinti');

const SAYFALAR = [
  '/', '/yazilar.php', '/ilkeler.php', '/nasil-isler.php',
  '/basvuru.php', '/istatistik.php', '/bekleyen.php', '/llms.txt',
];

let gecti = 0, kaldi = 0;
const bulgular = [];
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

/* Rengi rgb()/rgba() dizesinden sayıya çevir; alfa da döner. */
function renk(s) {
  const m = String(s || '').match(/rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?\)/);
  if (!m) return null;
  return { r: +m[1], g: +m[2], b: +m[3], a: m[4] === undefined ? 1 : +m[4] };
}
function bagilParlaklik(c) {
  const f = (v) => { v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
  return 0.2126 * f(c.r) + 0.7152 * f(c.g) + 0.0722 * f(c.b);
}
function karsitlik(a, b) {
  const l1 = bagilParlaklik(a), l2 = bagilParlaklik(b);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

(async () => {
  const tarayici = await chromium.launch();
  const sayfa = await (await tarayici.newContext({ viewport: { width: 1440, height: 900 } })).newPage();

  let gizliDugum = 0, dusukKarsit = 0, kucukPunto = 0, disari = 0;
  let taranan = 0, metinDugumu = 0;

  for (const yol of SAYFALAR) {
    let y;
    try {
      y = await sayfa.goto(KOK + yol + (yol.includes('?') ? '&' : '?') + 'lang=tr',
                           { waitUntil: 'domcontentloaded', timeout: 20000 });
    } catch (e) { continue; }
    if (!y || y.status() !== 200) continue;
    /* llms.txt düz metindir; tarayıcı onu <pre> içinde gösterir ve
       gizlenmiş bir şey barındıramaz. Yine de istenir: 200 dönmesi
       ve gövdesinin boş olmaması başlı başına bir ölçüdür. */
    if (yol.endsWith('.txt')) { taranan++; continue; }
    taranan++;

    const sonuc = await sayfa.evaluate(() => {
      const bul = { gizli: [], karsit: [], kucuk: [], disari: [], metin: 0 };
      const yurutec = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
      const gorulen = new Set();
      let d;
      while ((d = yurutec.nextNode())) {
        const t = (d.nodeValue || '').replace(/\s+/g, ' ').trim();
        if (t.length < 25) continue;                 /* tek sözcük değil, cümle ara */
        const e = d.parentElement;
        if (!e || gorulen.has(e)) continue;
        gorulen.add(e);
        const ad = e.tagName.toLowerCase();
        if (ad === 'script' || ad === 'style' || ad === 'noscript' || ad === 'template') continue;
        bul.metin++;

        const b = getComputedStyle(e);
        const kutu = e.getBoundingClientRect();
        const ornek = t.slice(0, 60);
        const yer = (e.id ? '#' + e.id : '') + (e.className && typeof e.className === 'string'
                    ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '');

        /* Erişilebilirlik ağacında duruyor mu? .gorsel-gizli gibi
           yalnız görsel olarak kırpılmış metin MEŞRUDUR: onu bir
           insan ekran okuyucuyla duyar. Yasak, hem gözden hem
           ağaçtan çıkarılmış metne. */
        /* OKUBENI 30: gizlilik ÖGENİN KENDİSİNDEN okunamaz.
           display:none olan bir ÜSTÖGENİN çocuğunda
           getComputedStyle(e).display hâlâ 'block' döner; gizleyen
           üstögedir. İlk yazımda bu yüzden gizli ögeler "görünür"
           sayıldı, sonra sıfır ölçülü kutu dalına düştüler ve anasayfa
           397 sahte bulgu verdi. Gizlilik ağaçta YUKARI çıkarak
           belirlenir; sıfır ölçü de ayrı bir suç değil, gizliliğin
           belirtisidir. */
        let gozdenGizli = false;
        for (let a = e; a && a !== document.documentElement; a = a.parentElement) {
          const s = getComputedStyle(a);
          if (s.display === 'none' || s.visibility === 'hidden' || parseFloat(s.opacity) === 0) { gozdenGizli = true; break; }
        }
        if (!gozdenGizli && (kutu.width === 0 || kutu.height === 0)) gozdenGizli = true;

        const agactaGizli = !!e.closest('[aria-hidden="true"]') || !!e.closest('[hidden]');
        /* OKUBENI 31: etkileşimli açılım listesine SEKME PANELİ de
           girer (role="tabpanel"). Anasayfadaki .sek-pnl kutuları
           kapalı sekmelerin içeriğidir; kullanıcı sekmeye basınca
           görünürler, gizli metin değildirler. */
        const acilir = !!e.closest('details, dialog, [hidden], [role="menu"], [role="dialog"], [role="tabpanel"], [aria-expanded], form');

        if (gozdenGizli) {
          /* Gizli VE erişilebilirlik ağacından da çıkarılmış VE
             açılabilir bir kabın içinde değilse: kimsenin göremeyeceği
             metindir. Yalnız görsel olarak kırpılmış olan (.gorsel-gizli)
             ekran okuyucuda durur, bu yüzden gizli sayılmaz. */
          if (agactaGizli && !acilir) bul.gizli.push([yer, ornek]);
          continue;                                  /* görünmeyende renk ölçülmez */
        }
        /* Ekranın dışına atılmış metin: -9999px klasiği. Ekran
           okuyucuya açık olan (.gorsel-gizli) 1px kırpma ile yapılır
           ve kutusu sıfır değildir; onu yukarıdaki dal ayıklar. */
        if (kutu.right < -500 || kutu.bottom < -500 || kutu.left > innerWidth + 2000) {
          bul.disari.push([yer, ornek]); continue;
        }
        const punto = parseFloat(b.fontSize);
        if (punto > 0 && punto < 5) bul.kucuk.push([yer, ornek, punto]);

        /* Arka planı ağaçta yukarı çıkarak bul; yarı saydam zemin
           opak sayılmaz (wcag-kapi.js'deki 5. tuzak). */
        let z = null, p = e;
        while (p && p !== document.documentElement) {
          const zb = getComputedStyle(p).backgroundColor;
          const m = String(zb).match(/rgba?\(([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?\)/);
          if (m && (m[4] === undefined || +m[4] >= 0.9)) { z = zb; break; }
          p = p.parentElement;
        }
        if (!z) z = getComputedStyle(document.documentElement).backgroundColor;
        bul.karsit.push([yer, ornek, b.color, z]);
      }
      return bul;
    });

    metinDugumu += sonuc.metin;
    for (const [yer, ornek] of sonuc.gizli)  { gizliDugum++; bulgular.push(['gizli', yol, yer, ornek]); }
    for (const [yer, ornek] of sonuc.disari) { disari++;     bulgular.push(['disari', yol, yer, ornek]); }
    for (const [yer, ornek, p] of sonuc.kucuk) { kucukPunto++; bulgular.push(['punto', yol, yer, ornek + ' (' + p + 'px)']); }
    for (const [yer, ornek, r1, r2] of sonuc.karsit) {
      const a = renk(r1), b2 = renk(r2);
      if (!a || !b2) continue;
      if (a.a < 0.9) continue;                       /* yarı saydam yazı: ayrı mesele */
      /* 1.5 eşiği okunabilirlik eşiği DEĞİLDİR; o wcag-kapi.js'in işi.
         Burada aranan tek şey, yazının zeminine PRATİK OLARAK EŞİT
         olması: beyaz üstüne beyaz. */
      if (karsitlik(a, b2) < 1.5) { dusukKarsit++; bulgular.push(['renk', yol, yer, ornek + ' [' + r1 + ' / ' + r2 + ']']); }
    }
  }

  await tarayici.close();

  console.log('== Gizli metin taraması ==');
  olc(taranan + ' sayfa açıldı, ' + metinDugumu + ' cümle taşıyan düğüm ölçüldü');
  den('gözden VE erişilebilirlik ağacından gizlenmiş cümle YOK', gizliDugum === 0, String(gizliDugum));
  den('zeminine eşit renkte yazı YOK (beyaz üstüne beyaz)', dusukKarsit === 0, String(dusukKarsit));
  den('5px altı punto YOK', kucukPunto === 0, String(kucukPunto));
  den('ekran dışına atılmış cümle YOK', disari === 0, String(disari));
  den('taranan sayfa sayısı beklenen kadar', taranan === SAYFALAR.length, taranan + '/' + SAYFALAR.length);

  if (AYRINTI && bulgular.length) {
    console.log('\n-- bulgular --');
    for (const [t, s, yer, ek] of bulgular) console.log('  ' + t + '  ' + s + '  ' + yer + '  ' + ek);
  } else if (bulgular.length) {
    console.log('  NOT    ayrıntı için --ayrinti');
  }

  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
