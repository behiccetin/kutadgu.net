/* =====================================================================
   OKUMA ÖLÇÜSÜ VE KENAR SÜTUN SAYISI · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ — 19 Ağustos 2026, ekran görüntüsüyle: "altta sidebar
   sağda da iki tane sidebar var, çok kullanışsız."

   ÖLÇÜLDÜ VE DOĞRUYDU. 1900 pikselde çalışma sayfasında aynı anda ÜÇ
   kenar sütunu duruyordu: site menüsü 250, içindekiler rayı 200, sağ ray
   320 — toplam 770 piksel; metin sütunu 876. İçindekiler rayı ayrıca en
   zayıfıydı: beş başlıklı bir çalışmada içeriği 129 piksel, ayırdığı
   sütun 200 piksel.

   İKİNCİ KUSUR ONU ARARKEN ÇIKTI: metin sütununun üst sınırı
   '--en-metin' idi, yani min(980px,100%). O sayı 14-16 punto ARAYÜZ
   metni için seçilmiştir; çalışma gövdesi 19 punto serifle dizilir ve
   980 pikselde satır 109 KARAKTER oluyordu. Sol ray dururken bile 97'ydi.
   Yani sayfa hem kalabalıktı hem de satırı okunmaz uzunluktaydı.

   BU KAPI İKİSİNİ BİRDEN KİLİTLER:
     1. Çalışma sayfasında metnin yanında EN ÇOK BİR ray olacak.
     2. Satır uzunluğu okunur bantta kalacak (bant aşağıda tanımlı).

   Karakter genişliği tahmin edilmez, ÖLÇÜLÜR: sayfanın kendi yazı tipi
   ve puntosuyla canvas'ta bir örnek dize ölçülür. "Yaklaşık yarım em"
   gibi bir kestirim, serif bir yüzde otuz yanılır.

   Kullanım: KPORT=8941 node okuma-olcusu-kapi.js
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK = 'http://127.0.0.1:' + PORT;
/* =====================================================================
   OKUNUR SATIR BANDI · EKRAN İÇİN
   ---------------------------------------------------------------------
   BANT 20 AĞUSTOS 2026'DA DEĞİŞTİ. Eski değer 45-75'ti ve kaynağı
   Bringhurst'ün 'The Elements of Typographic Style' kitabıdır — BASILI
   SAYFA için yazılmış bir ölçü. Ekran okuma araştırması başka bir
   aralık verir: Dyson & Haselgrove (2001) satır uzunluğu ile okuma
   hızını ölçtüğünde en hızlı okumayı 100 karakter dolayında bulur;
   55-100 hem hızlı hem kabul edilebilirdir.

   KURUL ÜÇ AYRI BİLDİRİMDE "metin dar" dedi ve üçünde de haklı çıktı.
   Ölçüm: 1891 piksellik ekranda metin pencerenin %36'sı, boş alan
   %26'sı. Yani basılı kitap ölçüsü, 1891 piksellik bir ekranın dörtte
   birini boş bırakıyordu. Bir kural, uygulandığı ortamla birlikte
   sınanmadıkça kural değil alışkanlıktır.

   ÜST SINIR HER EKRANDA AYNI (95): satırın uzaması okuma güçlüğüdür ve
   ekranın genişliği onu mazur göstermez. ALT SINIR ekrana göre
   değişir: 390 piksellik bir ekranda 65 karakter, puntoyu okunmaz hâle
   getirmeden mümkün değildir — orada satırın kısalması bir tercih
   değil, ekranın kendisidir. */
const EN_AZ_KARAKTER = 65;      /* >= 900 piksel */
const DAR_EN_AZ = 35;           /* <  900 piksel: ekranın izin verdiği */
const EN_COK_KARAKTER = 95;     /* her ekranda */

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? ('  (' + ek + ')') : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

(async () => {
  const tarayici = await chromium.launch();

  /* Ölçülecek çalışma: sınama verisindeki ilk kalıcı adres. */
  const YOL = '/tamga/KTG-2024-00001-1?lang=tr';

  console.log('\n== 1. Metnin yanında kaç ray var ==');
  for (const g of [1920, 1600, 1440]) {
    const s = await tarayici.newPage({ viewport: { width: g, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      /* GÖRÜNÜRLÜK offsetParent İLE ÖLÇÜLMEZ: position:fixed bir ögede
         offsetParent null döner ve site menüsü tam olarak öyledir.
         İlk ölçümde menü "yok" sanıldı. */
      const gor = (e) => {
        const st = getComputedStyle(e), b = e.getBoundingClientRect();
        return st.display !== 'none' && st.visibility !== 'hidden' && b.width > 80;
      };
      const raylar = [];
      document.querySelectorAll('aside, nav').forEach(e => {
        if (!gor(e)) return;
        /* İç içe geçmiş ögeler bir kez sayılır: <aside> içindeki <nav>
           ayrı bir ray değildir. */
        if (e.parentElement && e.parentElement.closest('aside, nav')) return;
        raylar.push({ s: (e.className || e.tagName).toString().slice(0, 24),
                      w: Math.round(e.getBoundingClientRect().width) });
      });
      const d = document.querySelector('.duzen3');
      const ic = d ? [...d.children].filter(e => gor(e)).length : 0;
      return { raylar, sutun: ic, toc: !!document.querySelector('.yan-sag #tocNav') };
    });
    olc(g + 'px · raylar: ' + r.raylar.map(x => x.s + ' ' + x.w).join(' | ') + ' · ızgara sütunu: ' + r.sutun);
    den('  ' + g + ': metnin yanında en çok BİR ray var', r.sutun <= 2, 'ızgara sütunu ' + r.sutun);
    den('  ' + g + ': içindekiler sağ rayın içinde', r.toc);
    await s.close();
  }

  console.log('\n== 2. Satır uzunluğu okunur bantta ==');
  /* 45-75 karakter. Bu bant bu dosyada tek yerde yazılıdır; sayfa da
     bir sayı yinelemez, sınırını kendi değişkeninden alır. */
  /* GENİŞLİK LİSTESİ 20 AĞUSTOS 2026'DA UZATILDI ve uzatılır uzatılmaz
     bir kusur buldu. Eskiden yalnız 1920-1024 ölçülüyordu; ölçü ise
     ızgaranın sütun tanımında yazılıydı ve ızgara 1024'te kuruluyor.
     Arada kalan bant hiçbir şeyle sınırlı değildi:

         1000 piksel ->  905 piksel satır,  91 KARAKTER
          900 piksel ->  813 piksel satır,  81 KARAKTER
          820 piksel ->  739 piksel satır,  77 KARAKTER

     Kapı ölçmediği genişlikte kusur göremez. */
  for (const g of [1920, 1680, 1440, 1280, 1100, 1024, 1000, 900, 820, 600, 390]) {
    const s = await tarayici.newPage({ viewport: { width: g, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      /* ---- KARAKTER SAYISI KESTİRİLMEZ, SAYILIR ----
         ÖLÇÜM KUSURU — 20 Ağustos 2026. Burada canvas'ta bir örnek
         dize ('abcdefghijklmnopqrstuvwxyz ') ölçülüp ortalama karakter
         genişliği çıkarılıyor, satır genişliği ona bölünüyordu. O
         dizede m ve w gibi geniş harfler Türkçe metinde olduğundan çok
         daha ağır basar: ortalama karakter geniş hesaplanır ve satırda
         OLDUĞUNDAN AZ karakter varmış gibi görünür. Yanılma küçük
         değildi ve hep aynı yöneydi:

             665 px / 19 punto -> kestirim 74, GERÇEK 79 karakter
             803 px / 23 punto -> kestirim 75, GERÇEK 81 karakter

         Yani kapı, sayfayı bandın içinde sanıyordu; sayfa dışındaydı.
         Kestirim atıldı: satırlar Range ile gerçekten sayılıyor. Ölçüm
         yanlış çıktığında önce ölçümden şüphelenilir — bu kez şüphe
         doğru yere düştü. */
      const govde = document.querySelector('.govde') || document.querySelector('.sar');
      if (!govde) return null;
      const ps = [...govde.querySelectorAll('p')].filter(e => e.textContent.trim().length > 300);
      if (!ps.length) return null;
      const el = ps[0], st = getComputedStyle(el);
      const dugum = el.firstChild;
      const metin = dugum ? dugum.textContent : '';
      const rng = document.createRange();
      let onceki = null, say = 0;
      const satirlar = [];
      for (let i = 0; i < metin.length && satirlar.length < 8; i++) {
        rng.setStart(dugum, i); rng.setEnd(dugum, i + 1);
        const ust = Math.round(rng.getBoundingClientRect().top);
        if (onceki === null) { onceki = ust; say = 1; }
        else if (ust !== onceki) { satirlar.push(say); onceki = ust; say = 1; }
        else say++;
      }
      /* İlk ve son satır eksik kalabilir; ortalama TAM satırlardan
         alınır. Tek satırlık bir paragraf ölçülemez, o zaman ölçüm
         kestirime düşmez — hiç yapılmaz. */
      if (satirlar.length < 3) return null;
      const kar = Math.round(satirlar.reduce((a, x) => a + x, 0) / satirlar.length);
      return { gen: Math.round(el.getBoundingClientRect().width), punto: st.fontSize,
               kar, satirlar };
    });
    if (!r) { den(g + ': gövde paragrafı bulunamadı', false); await s.close(); continue; }
    /* ALT SINIR TELEFONDA UYGULANAMAZ — ARİTMETİK BÖYLE DİYOR.
       390 piksellik bir ekranda sayfanın kenar boşluklarından sonra 343
       piksel kalıyor ve o genişlik 17 puntoda 41 karakter ediyor. Alt
       sınıra çıkmanın tek yolu puntoyu okunmaz hâle getirmektir; yani
       ölçüyü tutturmak için OKUNURLUĞU bozmak gerekirdi. Eşik 900
       pikseldir: sütun kendi sınırına (39em) ancak orada ulaşabiliyor,
       altında genişliği ekran belirliyor. */
    const dar = g < 900;
    const alt = dar ? DAR_EN_AZ : EN_AZ_KARAKTER;
    olc(g + 'px · metin ' + r.gen + 'px · ' + r.punto + ' · ' + r.kar + ' karakter'
        + (dar ? ' (dar ekran bandı ' + alt + '-' + EN_COK_KARAKTER + ')' : ''));
    den('  ' + g + ': satır ' + alt + '-' + EN_COK_KARAKTER + ' karakter bandında',
        r.kar >= alt && r.kar <= EN_COK_KARAKTER, r.kar + ' karakter');
    await s.close();
  }

  /* ---------------------------------------------------------------
     PUNTO BÜYÜDÜĞÜNDE SÜTUN DA BÜYÜR
     Ölçü em ile yazıldığı için okuyucu A+ ile puntoyu büyüttüğünde
     sütun ve kâğıt birlikte genişler, karakter sayısı sabit kalır.
     Sabit piksel sınırında ise tersi olurdu: punto büyüdükçe satıra
     daha AZ karakter sığar, yani okuyucunun ayarı ölçüyü bozardı.
     --------------------------------------------------------------- */
  console.log('\n== 2b. Okuyucu puntoyu büyütünce ölçü korunuyor ==');
  {
    const s = await tarayici.newPage({ viewport: { width: 1920, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const olcum = [];
    for (const f of ['19px', '21px', '23px']) {
      const r = await s.evaluate((f) => {
        document.documentElement.style.setProperty('--okuf', f);
        const p = [...document.querySelectorAll('.govde > p')].filter(e => e.textContent.trim().length > 300)[0];
        const cs = getComputedStyle(p);
        const dugum = p.firstChild, metin = dugum.textContent, rng = document.createRange();
        let onceki = null, say = 0; const satirlar = [];
        for (let i = 0; i < metin.length && satirlar.length < 8; i++) {
          rng.setStart(dugum, i); rng.setEnd(dugum, i + 1);
          const ust = Math.round(rng.getBoundingClientRect().top);
          if (onceki === null) { onceki = ust; say = 1; }
          else if (ust !== onceki) { satirlar.push(say); onceki = ust; say = 1; }
          else say++;
        }
        const kar = Math.round(satirlar.reduce((a, x) => a + x, 0) / (satirlar.length || 1));
        return { punto: cs.fontSize, gen: Math.round(p.getBoundingClientRect().width), kar,
                 kagit: Math.round(document.querySelector('.duzen3').getBoundingClientRect().width) };
      }, f);
      olcum.push(r);
      olc(r.punto + ' · sütun ' + r.gen + 'px · kâğıt ' + r.kagit + 'px · ' + r.kar + ' karakter');
    }
    den('  punto büyüyünce sütun da büyüyor', olcum[2].gen > olcum[0].gen + 60,
        olcum[0].gen + ' -> ' + olcum[2].gen);
    den('  kâğıt sütunla birlikte büyüyor', olcum[2].kagit > olcum[0].kagit + 60,
        olcum[0].kagit + ' -> ' + olcum[2].kagit);
    den('  karakter sayısı sabit kalıyor (en çok 2 fark)',
        Math.abs(olcum[2].kar - olcum[0].kar) <= 2, olcum.map(x => x.kar).join(' / '));
    await s.close();
  }

  console.log('\n== 3. Telefonda içindekiler ==');
  /* ÖLÇÜLEN KUSUR — 19 Ağustos 2026. İçindekiler sağ rayın içine
     alındı; o ray 1024'ün altında bütünüyle gizli. Sonuç: telefonda
     içindekiler HİÇ görünmüyordu. Liste artık, ray çizilmediğinde
     metnin başına KAPALI bir kapak olarak taşınır — kopyalanmaz,
     taşınır: iki kopya bir gün ikiye ayrılır. */
  for (const g of [1440, 1024, 820, 390]) {
    const s = await tarayici.newPage({ viewport: { width: g, height: 900 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(400);
    const r = await s.evaluate(() => {
      const m = document.querySelector('.toc-mobil');
      const n = document.getElementById('tocNav');
      const gor = (e) => { if (!e) return false; const st = getComputedStyle(e);
        return st.display !== 'none' && e.getBoundingClientRect().width > 1; };
      const ray = document.querySelector('.yan-sag');
      return { rayVar: gor(ray), mobilVar: gor(m), acik: m ? m.open : null,
               nerede: n ? (n.closest('.yan-sag') ? 'ray' : (n.closest('.toc-mobil') ? 'metin' : 'başka')) : 'yok',
               kopya: document.querySelectorAll('#tocNav').length,
               bag: n ? n.querySelectorAll('a[data-hid]').length : 0 };
    });
    olc(g + 'px · ray: ' + (r.rayVar ? 'var' : 'yok') + ' · içindekiler: ' + r.nerede
        + ' · bağ: ' + r.bag);
    den('  ' + g + ': içindekiler HER durumda bir yerde var', r.nerede === 'ray' || r.nerede === 'metin', r.nerede);
    den('  ' + g + ': tek kopya', r.kopya === 1, String(r.kopya));
    if (!r.rayVar) {
      den('  ' + g + ': metnin başına taşındı', r.nerede === 'metin');
      /* Telefonda ekranın tamamını kaplayan bir liste, okumaya
         başlamayı geciktirir. */
      den('  ' + g + ': kapalı doğuyor', r.acik === false, String(r.acik));
    }
    await s.close();
  }

  console.log('\n== 4. Ray tek bir kutudur ==');
  {
    const s = await tarayici.newPage({ viewport: { width: 1600, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      const sag = document.querySelector('.yan-sag');
      if (!sag) return null;
      const st = getComputedStyle(sag);
      /* SAYIM DOĞRUDAN ÇOCUKLARDA DEĞİL, RAYIN İÇİNDE. 20 Ağustos'ta
         rayın içine yapışkanlık için bir sarmal (.ys-ic) kondu ve
         ':scope >' ile yazılmış sayım sıfıra düştü: kapı, bölümler
         kayboldu sandı. Kural "ray tek bir bütündür ve içinde ayrı
         kutu yoktur"; sayım da onu sormalı, DOM'un kaç katman
         olduğunu değil. */
      const kutu = sag.querySelectorAll('.kutu').length;
      return { cerceve: st.borderTopWidth !== '0px',
               solCizgi: st.borderLeftWidth !== '0px', icKutu: kutu,
               bolum: sag.querySelectorAll('.ys-blok').length };
    });
    den('sağ ray bulundu', r !== null);
    if (r) {
      olc('bölüm sayısı: ' + r.bolum + ' · içeride ayrı kutu: ' + r.icKutu);
      /* Dört ayrı '.kutu', aralarında boşlukla, "iki tane sidebar"
         duygusunun kaynağıydı. Kutu artık dıştadır. */
      /* ÖLÇÜT 20 AĞUSTOS 2026'DA GENİŞLETİLDİ. Eskiden yalnız ÜST
         çerçeveye bakıyordu; kural ise "ray tek bir görsel bütündür"
         idi. Ray kâğıdın üstüne alınınca çerçevesini bıraktı ve yerini
         kâğıdı bölen bir çizgiye verdi: kutu içinde kutu, zaten
         kaldırılmak istenen şeydi. Ölçüt artık kuralın kendisidir —
         rayı çevresinden ayıran bir çizgi var mı. */
      den('  ray çevresinden bir çizgiyle ayrılıyor',
          r.cerceve || r.solCizgi, 'üst:' + r.cerceve + ' sol:' + r.solCizgi);
      den('  içeride ayrı kutu KALMADI', r.icKutu === 0, String(r.icKutu));
      den('  bölümler çizgiyle ayrılmış', r.bolum >= 3, String(r.bolum));
    }
    await s.close();
  }

  /* =====================================================================
     5. ÇALIŞMA SAYFASI BİR BELGEDİR: KÂĞIT
     ---------------------------------------------------------------------
     KURUL BİLDİRİMİ — 20 Ağustos 2026: "buradaki alan çok daralmış."
     Ölçüm doğruladı: 1920 pikselde metin 670, iki yanında 315 ve 329
     piksel HİÇBİR ŞEY vardı; okunan alan ekranın %35'iydi. Satır
     uzunluğu doğruydu, ama sayfa iki boşluk arasına sıkışmış bir şerit
     gibi duruyordu. Boşluk satıra verilemez (satır 130 karaktere
     çıkardı); boşluk SAHİPLENİLDİ: metin ile ray tek bir kâğıdın
     üstüne alındı ve kâğıt ortalandı. Boşluk artık kenar boşluğudur.
     ===================================================================== */
  console.log('\n== 5. Kâğıt: metin ile ray tek yüzeyde ==');
  for (const g of [1920, 1600, 1440]) {
    const s = await tarayici.newPage({ viewport: { width: g, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      const d = document.querySelector('.duzen3');
      const ana = d.closest('main');
      const st = getComputedStyle(d), sta = getComputedStyle(ana);
      const kd = d.getBoundingClientRect(), ka = ana.getBoundingClientRect();
      const ic = parseFloat(getComputedStyle(ana).paddingLeft) || 0;
      const p = [...d.querySelectorAll('.govde > p')].filter(e => e.textContent.trim().length > 300)[0];
      const ray = document.querySelector('.yan-sag');
      return {
        zemin: st.backgroundColor, sayfaZemin: getComputedStyle(document.body).backgroundColor,
        cerceve: st.borderTopWidth !== '0px',
        sol: Math.round(kd.left - (ka.left + ic)),
        sag: Math.round((ka.right - ic) - kd.right),
        metin: p ? Math.round(p.getBoundingClientRect().width) : 0,
        rayIcinde: !!(ray && d.contains(ray)),
        ustuste: !!(ray && p && p.getBoundingClientRect().right > ray.getBoundingClientRect().left)
      };
    });
    olc(g + 'px · kâğıt kenarları sol ' + r.sol + ' / sağ ' + r.sag + ' · metin ' + r.metin + 'px');
    den('  ' + g + ': metin ile ray AYNI yüzeyin üstünde', r.rayIcinde);
    den('  ' + g + ': kâğıdın kendi zemini var', r.zemin !== r.sayfaZemin, r.zemin + ' / ' + r.sayfaZemin);
    den('  ' + g + ': kâğıt çerçeveli', r.cerceve);
    /* Kenar boşluğu DENGELİ olmalı: bir yanı öteki yanının iki katıysa
       sayfa yine kaymış görünür, kâğıt da onu düzeltmez. */
    den('  ' + g + ': iki kenar boşluğu dengeli (fark <= 24px)',
        Math.abs(r.sol - r.sag) <= 24, 'sol ' + r.sol + ' sağ ' + r.sag);
    /* KÂĞIT METİNDEN ÇALMAZ. İlk yazımda iç boşluk sabit clamp'tı ve
       1440'ta satırı 670'ten 646 piksele düşürdü — yani düzeltmeye
       çalıştığı şeyi bozdu. İç boşluk artık ARTAN yerden hesaplanır. */
    den('  ' + g + ': kâğıt metin sütunundan çalmıyor', r.metin >= 640, r.metin + 'px');
    den('  ' + g + ': metin rayın altına girmiyor', !r.ustuste);
    await s.close();
  }
  {
    /* Yer yoksa kâğıt da yoktur: 1280'de kâğıdın iç boşluğu metinden
       çalardı, bu yüzden orada açılmaz. */
    const s = await tarayici.newPage({ viewport: { width: 1280, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      const d = document.querySelector('.duzen3');
      return { zemin: getComputedStyle(d).backgroundColor,
               sayfa: getComputedStyle(document.body).backgroundColor };
    });
    den('1280: yer yokken kâğıt AÇILMIYOR', r.zemin === r.sayfa || r.zemin === 'rgba(0, 0, 0, 0)',
        r.zemin);
  }
  {
    /* Okuma kipinde kâğıt zaten .sar'a veriliyor; ikisi üst üste
       binmemeli, yoksa kâğıt üstünde kâğıt olur. */
    const s = await tarayici.newPage({ viewport: { width: 1920, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.evaluate(() => document.body.classList.add('oku'));
    await s.waitForTimeout(250);
    const r = await s.evaluate(() => {
      const d = document.querySelector('.duzen3');
      return { zemin: getComputedStyle(d).backgroundColor, sar: getComputedStyle(document.querySelector('.sar')).backgroundColor };
    });
    den('okuma kipinde kâğıt İKİ KEZ çizilmiyor',
        r.zemin === 'rgba(0, 0, 0, 0)' || r.zemin !== r.sar, 'duzen3 ' + r.zemin + ' / sar ' + r.sar);
    await s.close();
  }

  /* ---------------------------------------------------------------
     OKUNAN ALAN, EKRANIN NE KADARI
     Kurulun üç kez bildirdiği şey bir orandı: "metin dar". Ölçüldü —
     1891 pikselde metin pencerenin %36'sı, kâğıdın dışındaki boş alan
     %26'sıydı. Kapı artık bu oranı da tutuyor: sayı bir daha sessizce
     aşağı kayarsa burada görünür.
     --------------------------------------------------------------- */
  console.log('\n== 5b. Okunan alanın ekrandaki payı ==');
  for (const g of [1891, 1600]) {
    const s = await tarayici.newPage({ viewport: { width: g, height: 900 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      const k = e => { const b = e.getBoundingClientRect(); return { x: Math.round(b.x), sag: Math.round(b.right), g: Math.round(b.width) }; };
      const yan = document.querySelector('.yan');
      const kagit = k(document.querySelector('.duzen3'));
      const par = [...document.querySelectorAll('.govde > p')].filter(e => e.textContent.trim().length > 300)[0];
      const ky = yan ? k(yan) : { sag: 0 };
      return { pencere: innerWidth, metin: k(par).g,
               bos: (kagit.x - Math.max(0, ky.sag)) + (innerWidth - kagit.sag) };
    });
    const pay = Math.round(r.metin / r.pencere * 100);
    const bosPay = Math.round(r.bos / r.pencere * 100);
    olc(g + 'px · metin ' + r.metin + 'px (%' + pay + ') · kâğıdın dışındaki boşluk ' + r.bos + 'px (%' + bosPay + ')');
    den('  ' + g + ': okunan sütun pencerenin en az %40\'ı', pay >= 40, '%' + pay);
    den('  ' + g + ': kâğıdın dışındaki boşluk en çok %22', bosPay <= 22, '%' + bosPay);
    await s.close();
  }

  /* =====================================================================
     6. YAZARIN BÖLÜM BAŞLIĞI · BELGE BAŞLIĞIDIR, ETİKET DEĞİL
     ---------------------------------------------------------------------
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Sayfadaki tek h2 kuralı etiketti:
     11,5 piksel, VERSAL, altın, altı çizgili. Sayfanın kendi bölümleri
     ("ÖZET", "KAYNAKÇA") için doğru; ama aynı kural yazarın kendi
     "Giriş", "Yöntem", "Bulgular" başlıklarına da uygulanıyordu ve o
     başlıklar KENDİ METNİNDEN küçük görünüyordu (11,5 punto başlık,
     19 punto gövde). Bir makalede bölüm başlığı metnin yapısını
     gösterir; ondan küçük olamaz.
     ===================================================================== */
  console.log('\n== 6. Yazarın bölüm başlığı ==');
  {
    const s = await tarayici.newPage({ viewport: { width: 1920, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      const g = document.querySelector('.govde');
      const ps = [...g.querySelectorAll(':scope > p')];
      const h = document.createElement('h2'); h.textContent = 'Yöntem'; ps[1].after(h);
      const h3 = document.createElement('h3'); h3.textContent = 'Örneklem'; ps[2].after(h3);
      const cs = getComputedStyle(h), cp = getComputedStyle(ps[1]), c3 = getComputedStyle(h3);
      /* Sayfanın KENDİ etiketi: gövdenin dışındaki bir h2. */
      const etiket = [...document.querySelectorAll('h2')].filter(e => !e.closest('.govde'))[0];
      const ce = etiket ? getComputedStyle(etiket) : null;
      const o = {
        h2: parseFloat(cs.fontSize), h3: parseFloat(c3.fontSize), p: parseFloat(cp.fontSize),
        h2Aile: cs.fontFamily.split(',')[0].replace(/"/g, ''),
        pAile: cp.fontFamily.split(',')[0].replace(/"/g, ''),
        versal: cs.textTransform, ust: parseFloat(cs.marginTop), alt: parseFloat(cs.marginBottom),
        etiketBoy: ce ? parseFloat(ce.fontSize) : null,
        etiketVersal: ce ? ce.textTransform : null
      };
      h.remove(); h3.remove(); return o;
    });
    olc('gövde ' + r.p + 'px · h2 ' + r.h2 + 'px · h3 ' + r.h3 + 'px · sayfa etiketi ' + r.etiketBoy + 'px');
    den('  bölüm başlığı gövdeden BÜYÜK', r.h2 > r.p, r.h2 + ' vs ' + r.p);
    den('  alt başlık gövdeden büyük ve h2\'den küçük', r.h3 > r.p && r.h3 < r.h2, String(r.h3));
    den('  başlık gövdenin yazı tipiyle', r.h2Aile === r.pAile, r.h2Aile + ' / ' + r.pAile);
    den('  başlık VERSAL değil', r.versal === 'none', r.versal);
    /* Başlık kendinden SONRAKİNE bağlanır: üst boşluk alt boşluktan
       belirgin biçimde büyük olmalı, yoksa okuyucu başlığı bir önceki
       bölümün sonu sanır. */
    den('  üst boşluk alt boşluğun en az iki katı', r.ust >= r.alt * 2, r.ust + ' / ' + r.alt);
    /* Sayfanın kendi etiketi DEĞİŞMEDİ: iki rol ayrıldı, biri ötekinin
       yerine geçmedi. */
    den('  sayfanın kendi etiketi hâlâ küçük ve versal',
        r.etiketBoy !== null && r.etiketBoy < r.p && r.etiketVersal === 'uppercase',
        r.etiketBoy + 'px ' + r.etiketVersal);
    await s.close();
  }

  /* =====================================================================
     7. ÇİZELGE SÜTUNU DOLDURUR
     ---------------------------------------------------------------------
     'display:block' bir çizelgenin içindeki asıl çizelge kutusu
     içeriğine göre daralır: dört sütunlu bir çizelge 670 piksellik
     gövdenin ortasında 495 pikselde kalıyor, sağında 175 piksel boşluk
     bırakıyordu. Betik çizelgeyi kaydırma kabına alır, çizelge gerçek
     çizelge olur ve sütunu doldurur; sığmazsa kap kaydırır.
     ===================================================================== */
  console.log('\n== 7. Çizelge sütunu dolduruyor ==');
  {
    const s = await tarayici.newPage({ viewport: { width: 1920, height: 1000 } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(300);
    const r = await s.evaluate(() => {
      const g = document.querySelector('.govde');
      const p = [...g.querySelectorAll(':scope > p')].filter(e => e.textContent.trim().length > 300)[0];
      const t = document.createElement('table');
      t.innerHTML = '<thead><tr><th>A</th><th>B</th><th>C</th><th>D</th></tr></thead>'
                  + '<tbody><tr><td>1</td><td>2</td><td>3</td><td>4</td></tr></tbody>';
      p.after(t);
      const k = document.createElement('div'); k.className = 'tablo-kaydir';
      t.parentNode.insertBefore(k, t); k.appendChild(t);
      const o = { sutun: Math.round(p.getBoundingClientRect().width),
                  tablo: Math.round(t.getBoundingClientRect().width),
                  tasma: document.documentElement.scrollWidth > document.documentElement.clientWidth };
      k.remove(); return o;
    });
    olc('sütun ' + r.sutun + 'px · çizelge ' + r.tablo + 'px');
    den('  çizelge sütunu dolduruyor', Math.abs(r.tablo - r.sutun) <= 2, r.tablo + ' / ' + r.sutun);
    den('  sayfa yatayda taşmıyor', !r.tasma);
    /* BETİKSİZ TARAYICI HİÇBİR ŞEY KAYBETMEZ. Bu ölçüm 'true' yazılmış
       bir satırdı ve hiçbir şey ölçmüyordu; kaynağa bakan bir ölçüme
       çevrildi: öntanımlı kural (blok + kendi içinde kaydırma) yerinde
       duruyor mu. */
    const kaynak = require('fs').readFileSync(
      (process.env.KTEST_DIR || '/home/claude/kg/ktest') + '/yazi.php', 'utf8');
    den('  betiksiz öntanımlı kural yerinde',
        /\.govde table\{display:block;[^}]*overflow-x:auto/.test(kaynak));
    den('  kaydırma kabı yalnız betikle kuruluyor', kaynak.includes("className='tablo-kaydir'"));
    await s.close();
  }

  /* =====================================================================
     8. RAYIN KENDİ KAYDIRMA ÇUBUĞU YOK
     ---------------------------------------------------------------------
     KURUL BİLDİRİMİ — 20 Ağustos 2026: "aşağıda kaydırma çubuğu var,
     burayı normal görmeyecek miyiz." Ray yapışkandı ve içeriği ekrandan
     uzun olduğunda kendi içinde kayıyordu; bir belgenin ortasında ikinci
     bir kaydırma alanı, okuyucuya sayfanın parçası değil pencere gibi
     görünüyor. Yeni kural: ray sayfayla kayar; yapışkanlık yalnızca
     SIĞDIĞI zaman uygulanır (o zaman zaten çubuk çıkmaz).
     ===================================================================== */
  console.log('\n== 8. Rayın kendi kaydırma çubuğu yok ==');
  for (const [g, y] of [[1920, 900], [1920, 1600], [1440, 900]]) {
    const s = await tarayici.newPage({ viewport: { width: g, height: y } });
    await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
    await s.waitForTimeout(450);
    const r = await s.evaluate(() => {
      const ray = document.querySelector('.yan-sag');
      const st = getComputedStyle(ray);
      const ust = parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ust')) || 58;
      return { boy: Math.round(ray.getBoundingClientRect().height),
               yer: Math.round(window.innerHeight - ust - 24),
               kaydirma: ray.scrollHeight > ray.clientHeight + 1,
               tasma: st.overflowY, maxh: st.maxHeight, pos: st.position };
    });
    olc(g + 'x' + y + ' · ray ' + r.boy + 'px · ekranda yer ' + r.yer + 'px · konum ' + r.pos);
    den('  ' + g + 'x' + y + ': rayın kendi kaydırma çubuğu YOK', !r.kaydirma);
    den('  ' + g + 'x' + y + ': ray yüksekliği sınırlanmıyor', r.maxh === 'none', r.maxh);
    /* Yapışkanlık koşullu: sığıyorsa yapışır, sığmıyorsa sayfayla kayar.
       Sığmadığı hâlde yapışan bir rayın alt ucu HİÇ okunamaz. */
    den('  ' + g + 'x' + y + ': yapışkanlık sığdığı zaman uygulanıyor',
        (r.boy <= r.yer) === (r.pos === 'sticky'), r.boy + ' <= ' + r.yer + ' ? ' + r.pos);
    await s.close();
  }

  await tarayici.close();
  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
