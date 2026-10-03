/* =====================================================================
   YAZI TİPİ EŞİTLİĞİ: kapı ölçümü. Depoya girmez.

   BULUNAN KUSURUN İMZASI
   ----------------------
   "Yazı tipleri neden farklı" diye bildirilen şey, iki sayfanın yan
   yana konduğunda başka bir dizgeden gelmiş gibi durmasıydı. Ölçüm
   bunu doğruladı: aynı işi gören başlık, sayfadan sayfaya başka
   boyda ve başka yüzde yazılıyordu.

     h1  /kurul.php 32.8px  ·  /panel.php 45.6px   (aynı düzeyde başlık)
     h2  /kurul.php 25.9px serif · /harita.php 16px sans ·
         /basvuru.php 15.2px sans · /acikliklar.php 19.2px serif
     h3  /kurul.php 19.2px serif · /yz.php 12.8px sans versal

   Sebep tek tek sayfalarda değil, YÖNTEMDEDİR: her sayfa kendi
   <style> bloğunda başlık ögesine doğrudan bir boy yazıyordu. Yirmi
   beş ayrı kural, yirmi beş ayrı karar. Bir dizgede boy kararı bir
   kez verilir; sayfa yalnız hangi BASAMAĞI kullandığını söyler.

   ÖLÇÜT: BASAMAK MERDİVENİ
   ------------------------
   Görünen her metnin boyu, --y-1 … --y-8 belirteçlerinden birinin
   çözülmüş değerine eşit olmalıdır. 1.06rem, .98rem, clamp(1.12rem,…)
   gibi merdiven dışı bir değer, tanımı gereği "yalnız bu sayfada
   geçerli bir karar"dır ve kapı onu kusur sayar.

   Başlık ögesinin yüzü de serbest değildir. Dizgenin kendi yazılı
   kuralı (kutadgu.css: "Başlıklar serif yüzle yazılır. VERSAL
   YAPILMAZ") iki hâl tanır:
     - başlık   : serif yüz, merdivenin --y-5 … --y-8 basamakları
     - etiket   : sans yüz, --y-1/--y-2, VERSAL ve harf arası >= .06em
   Sans yüzlü ama versal olmayan bir başlık, ikisinin arasında kalmış
   demektir; kapı onu da kusur sayar.

   ÖLÇÜM TUZAKLARI
     - Belirtecin hesaplanmış değeri metindir: getPropertyValue('--y-6')
       "clamp(…)" döndürür, piksel döndürmez. Merdiven, her genişlikte
       ekrana konan bir deneme ögesiyle ÇÖZÜLEREK okunur.
     - clamp'li basamaklar genişliğe göre değişir; merdiven her
       genişlik için yeniden çözülür.
     - Sayfa dili açıkça istenir (?lang=tr): yerel istek İngilizceye
       düşer (OKUBENI 6/25).
     - Panel giriş ister. Panelin kendisi ölçülmezse bu kapı, kusurun
       ilk bildirildiği sayfayı hiç görmemiş olur; panel, panel-kapi
       ile aynı yöntemle üretilip durağan dosya olarak ölçülür.
     - Yalnız GÖRÜNEN metin ölçülür: yüksekliği sıfır olan, [hidden]
       kabındaki ya da .gizle ile gizlenen metin ekranda durmaz.
     - Baskı (@media print) kuralları ölçülmez; onların merdiveni
       punto, piksel değil.

   Kullanım (önce panelin ölçüm kopyası üretilir, yoksa kapı KALIR):

     rm -rf /tmp/kpanel && cp -a /home/claude/kg/ktest /tmp/kpanel
     php -r '$p="/tmp/kpanel/panel.php"; $s=file_get_contents($p);
       $s=preg_replace("/^\\$edYetki\\s*=.*$/m","\\$edYetki  = true;",$s,1);
       $s=preg_replace("/^\\$basYetki\\s*=.*$/m","\\$basYetki = true;",$s,1);
       file_put_contents($p,$s);'
     printf '%s' "<?php \$_GET['lang']='tr'; \$_SERVER['HTTP_HOST']='127.0.0.1';
       require __DIR__.'/panel.php';" > /tmp/kpanel/_olcum.php
     (cd /tmp/kpanel && KUTADGU_DATA=/home/claude/kg/ktest-data php _olcum.php) \
       > /home/claude/kg/ktest/_olcum-panel.html

     KPORT=8941 node yazi-tipi-kapi.js [--ayrinti]
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK = 'http://127.0.0.1:' + PORT;
const AYRINTI = process.argv.includes('--ayrinti');

const SAYFALAR = [
  '/', '/nasil-isler.php', '/yazilar.php', '/ara.php', '/istatistik.php',
  '/hakemlik.php', '/bekleyen.php', '/hakemler.php', '/oylama.php',
  '/ilkeler.php', '/kurul.php', '/yz.php', '/acikliklar.php',
  '/basvuru.php', '/destek.php', '/iletisim.php', '/harita.php',
  '/bildiri.php', '/dokum.php', '/kefil.php',
  '/hesap-kur.php', '/panel.php', '/_olcum-panel.html',
];

/* MERDİVEN DIŞI KALMASI DOĞRU OLAN TEK ŞEY: İŞARETİN KENDİSİ.
   Marka yazısı ("KUTDGU") akan metin değil, harflerle çizilmiş bir
   işarettir; boyu okunurluk değil, işaretin oranı belirler. Bu liste
   KISA TUTULUR ve her ögesi ölçümde gerçekten bulunmalıdır: ölü bir
   istisna, ölçmediğini ölçmüş gibi gösterir. */
/* MERDİVEN DIŞINDA KALMASI MEŞRU OLANLAR.
   Liste kısa tutulur ve her satırın gerekçesi burada durur; gerekçesiz
   bir muafiyet, kuralın kendisini boşa çıkarır.

   yuz-harf: resmi olmayan kişinin baş harfleri. Boyu METNE değil
   DAİREYE bağlıdır — k_yuz() çağıranın verdiği çapın 0,38'ini alır ve
   çap yerine göre 40, 44 ya da 72 pikseldir. Merdivenin bir basamağına
   sabitlense harf ya daireden taşar ya ortasında kaybolur. Ayrıca
   kendisi aria-hidden'dır: okunacak metin değil, adın yanındaki
   işarettir. */
const DISINDA = ['kmk-ad', 'marka-ad', 'marka-alt', 'yuz-harf'];
const GENISLIKLER = [1440, 820];

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

/* Merdiven, sayfanın kendi belirteçlerinden çözülür: kapı boyları
   bilmez, dizgeye sorar. */
function merdivenOku() {
  const d = document.createElement('div');
  d.style.cssText = 'position:absolute;visibility:hidden;left:-9999px';
  document.body.appendChild(d);
  const m = {};
  for (let i = 1; i <= 9; i++) {
    d.style.fontSize = 'var(--y-' + i + ')';
    const p = parseFloat(getComputedStyle(d).fontSize);
    if (p > 0) m['y-' + i] = Math.round(p * 100) / 100;
  }
  d.remove();
  return m;
}

function metinOlc(DISINDA) {
  const cikti = [];
  const gorunur = el => {
    if (!el.getClientRects().length) return false;
    const c = getComputedStyle(el);
    return c.visibility !== 'hidden' && c.display !== 'none' && parseFloat(c.opacity) > 0.05;
  };
  const yol = el => {
    const p = [];
    for (let e = el; e && e.tagName && p.length < 3; e = e.parentElement) {
      p.unshift(e.tagName.toLowerCase() + (e.className && typeof e.className === 'string'
        ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
    }
    return p.join('>');
  };
  const kap = document.querySelector('main') || document.body;
  kap.querySelectorAll('*').forEach(el => {
    /* Kendi metni olmayan öge ölçülmez: kutunun boyu değil, YAZININ
       boyu aranıyor. */
    const kendi = [...el.childNodes]
      .filter(n => n.nodeType === 3).map(n => n.textContent.trim()).join('');
    if (kendi.length < 2) return;
    if (!gorunur(el)) return;
    const c = getComputedStyle(el);
    cikti.push({
      etiket: el.tagName.toLowerCase(),
      yol: yol(el),
      boy: Math.round(parseFloat(c.fontSize) * 100) / 100,
      yuz: c.fontFamily.split(',')[0].replace(/["']/g, '').trim(),
      kalin: c.fontWeight,
      versal: c.textTransform === 'uppercase',
      aralik: c.letterSpacing === 'normal' ? 0 : parseFloat(c.letterSpacing) || 0,
      metin: kendi.slice(0, 40),
      disinda: DISINDA.find(k => el.classList.contains(k)) || '',
    });
  });
  return cikti;
}

(async () => {
  const tarayici = await chromium.launch();
  const disari = [];      /* merdiven dışı boy */
  const disindaBulunan = new Set();
  const arada  = [];      /* sans yüzlü ama versal olmayan başlık */
  const h1Boy  = {};      /* sayfa -> h1 boyu */
  const etiketBicim = new Set();   /* versal etiket başlıklarının ölçüsü */
  const etiketNe = [];
  let olculen = 0, ogeSay = 0;
  const olculenYol = new Set();

  for (const g of GENISLIKLER) {
    const baglam = await tarayici.newContext({ viewport: { width: g, height: 1100 } });
    const s = await baglam.newPage();
    for (const yol of SAYFALAR) {
      const adres = KOK + yol + (yol.endsWith('.html') ? '' : (yol.includes('?') ? '&' : '?') + 'lang=tr');
      const c = await s.goto(adres, { waitUntil: 'networkidle' }).catch(() => null);
      if (!c || c.status() >= 400) { console.log('  ->     ' + yol + ' ULAŞILMADI ' + (c ? c.status() : 'yok')); continue; }
      /* PANELİN İÇİ JS İLE AÇILIR. Ölçüm kopyasında panel gövdesi
         .gizli sınıfıyla kapalı duruyor ve başlıkları boş; kapı bu
         hâlde panelin tek bir kuralını bile ölçemez, üstelik ölçtüğünü
         sanır (yanlışlama denemesinde panelin h1'i --y-7'ye çekildiği
         hâlde kapı "hepsi aynı" dedi). Kapak kaldırılır ve boş
         başlıklara örnek metin konur: ölçülen şey YETKİ değil, BİÇİM
         kuralıdır. */
      if (yol === '/_olcum-panel.html') {
        await s.evaluate(() => {
          const ic = document.getElementById('pnIc');
          if (ic) ic.classList.remove('gizli');
          document.querySelectorAll('h1,h2,h3,h4,h5').forEach(h => {
            if (!h.textContent.trim()) h.textContent = 'Örnek başlık';
          });
        });
        await s.waitForTimeout(120);
      }
      const merdiven = await s.evaluate(merdivenOku);
      const boylar = Object.values(merdiven);
      const ogeler = await s.evaluate(metinOlc, DISINDA);
      olculen++; ogeSay += ogeler.length; olculenYol.add(yol);

      ogeler.forEach(o => {
        if (o.disinda) { disindaBulunan.add(o.disinda); return; }
        const uyan = boylar.some(b => Math.abs(b - o.boy) < 0.25);
        if (!uyan) disari.push({ sayfa: yol, g, ...o });
        const baslikMi = /^h[1-5]$/.test(o.etiket);
        const serifMi = /serif|Kutadgu|Iowan|Palatino|Georgia|Times/i.test(o.yuz);
        if (baslikMi && !serifMi && !(o.versal && o.aralik >= g * 0 + 0.06 * o.boy)) {
          arada.push({ sayfa: yol, g, ...o });
        }
        /* SAYFADA BİRDEN ÇOK h1 OLABİLİR ve ilki aradığımız olmayabilir.
           Ölçüm kopyasında panelin üstünde giriş kutusunun h1'i de
           duruyordu; kapı ilkini ölçtüğü için panelin kendi başlığını
           hiç görmedi ve yanlışlama ısırmadı. Hepsi ölçülür. */
        if (baslikMi && o.versal && g === 1440) {
          etiketBicim.add(o.boy + 'px/' + Math.round(o.aralik * 100) / 100 + 'px');
          etiketNe.push(o.boy + 'px ' + yol + ' ' + o.yol);
        }
        if (o.etiket === 'h1' && g === 1440) {
          (h1Boy[yol] = h1Boy[yol] || []).push(o.boy);
        }
      });
    }
    await baglam.close();
  }
  await tarayici.close();

  console.log('== Yazı tipi eşitliği ==');
  olc(olculen + ' sayfa görünümü, ' + ogeSay + ' metin ögesi ölçüldü (' + GENISLIKLER.join('/') + ' px)');

  /* Aynı boyu birden çok yerde bildirmek listeyi şişirir; kusur
     ÖGENİN kendisi değil, KURALIN kendisidir: yol + boy ile teklenir. */
  const tekle = (dizi) => {
    const m = new Map();
    dizi.forEach(o => {
      const a = o.yol + ' @' + o.boy + 'px';
      if (!m.has(a)) m.set(a, { ...o, sayfalar: new Set() });
      m.get(a).sayfalar.add(o.sayfa);
    });
    return [...m.entries()].map(([a, o]) => a + '  ' + [...o.sayfalar].slice(0, 3).join(',')
      + '  «' + o.metin + '»');
  };
  const dTek = tekle(disari), aTek = tekle(arada);
  (AYRINTI ? dTek : dTek.slice(0, 25)).forEach(x => console.log('  ->     merdiven dışı: ' + x));
  (AYRINTI ? aTek : aTek.slice(0, 15)).forEach(x => console.log('  ->     arada kalmış başlık: ' + x));

  const h1Ayri = [...new Set(Object.values(h1Boy).flat())];
  olc('h1 boyları: ' + Object.entries(h1Boy).map(([k, v]) => k + '=' + [...new Set(v)].join('/')).join(' · '));

  den('görünen her metnin boyu belirteç merdiveninden geliyor', disari.length === 0,
      disari.length + ' öge / ' + dTek.length + ' kural');
  den('başlıklar ya serif ya versal etiket; arada kalan yok', arada.length === 0,
      arada.length + ' öge / ' + aTek.length + ' kural');
  den('bütün sayfaların h1 boyu aynı', h1Ayri.length === 1, h1Ayri.join(' / '));
  /* Etiketin biçimi .et sınıfında bir kez tanımlıdır. Sayfalar bugün
     onu ayrı ayrı yazıyor olabilir; ölçü aynı kaldığı sürece dizge
     tektir. Ayrışırsa bu kapı, ayrışmayı yazıldığı gün görür. */
  olc('versal etiket ölçüleri: ' + [...etiketBicim].join(' · '));
  [...new Set(etiketNe)].forEach(x => console.log('  ->     etiket: ' + x));
  den('bütün versal etiket başlıkları aynı ölçüde', etiketBicim.size === 1,
      [...etiketBicim].join(' / '));

  /* Panel ölçülmediyse bu kapı, kusurun ilk bildirildiği sayfayı hiç
     görmemiş demektir; sessizce eksik ölçmektense KALIR. */
  den('panelin ölçüm kopyası da ölçüldü', olculenYol.has('/_olcum-panel.html'),
      'üretilmemiş: başlıktaki komutu koşturun');

  /* =================================================================
     YÜZLERİN İKİSİ DE İLK BOYAMADAN ÖNCE İSTENİYOR MU
     -----------------------------------------------------------------
     "Yazı tipleri sapıtmış, g ve b farklı farklı" diye bildirilen kusur
     buydu: sayfaların yedisi Caladea'nın DÜZ (400) yüzünü istiyor ama
     yalnız KALIN (700) yüz önceden yükleniyordu. Kalın başlıklar
     Caladea ile anında çiziliyor, düz metinler ise 400 dosyası inene
     kadar yedek serifle (Georgia/Palatino) duruyordu — yani ekranda bir
     süre İKİ AYRI YÜZ bulunuyordu.

     Ölçüldü (yerel ağda, yani gerçeğin en iyimser hâlinde):
       önce : 700 → 30-57 ms'de bitiyor, 400 → 60-89 ms'de BAŞLIYOR
       sonra: ikisi de 20-28 ms'de başlıyor, 39-59 ms'de bitiyor

     Ölçüt "önden yükleme etiketi var mı" değil, İKİSİNİN DE İLK
     BOYAMADAN ÖNCE İSTENMESİdir: etiket yazılıp yanlış yola bakıyorsa
     kapı yine yeşil verirdi. */
  const sayfaFont = ['/ilkeler.php', '/panel.php', '/yazilar.php'];
  const fontOlcum = [];
  /* Kendi tarayıcısını açar: yukarıdaki örnek bu noktada kapatılmış
     oluyor ve kapalı bir tarayıcıdan sayfa istemek, ölçümü kusur
     değil ÇÖKME ile bitirir. */
  const tar2 = await chromium.launch();
  for (const yol of sayfaFont) {
    const s2 = await tar2.newPage({ viewport: { width: 1280, height: 900 },
      extraHTTPHeaders: { 'CF-Connecting-IP': '10.226.' + yol.length + '.4' } });
    const y = await s2.goto(KOK + yol + '?lang=tr', { waitUntil: 'networkidle' }).catch(() => null);
    if (!y || y.status() !== 200) { await s2.close(); continue; }
    const t = await s2.evaluate(async () => {
      await document.fonts.ready;
      return performance.getEntriesByType('resource')
        .filter(r => /kutadgu-serif-(400|700)\.woff2/.test(r.name))
        .map(r => ({ d: r.name.split('/').pop(), bas: Math.round(r.startTime) }));
    });
    await s2.close();
    if (t.length < 2) { fontOlcum.push({ yol, fark: null, adet: t.length }); continue; }
    const bas = t.map(x => x.bas);
    fontOlcum.push({ yol, fark: Math.max(...bas) - Math.min(...bas), adet: t.length });
  }
  await tar2.close();
  fontOlcum.forEach(f => olc('yüz isteği ' + f.yol + ': ' + f.adet + ' dosya'
    + (f.fark === null ? ' (tek yüz istendi)' : ', başlangıç farkı ' + f.fark + ' ms')));
  const ikiYuz = fontOlcum.filter(f => f.adet >= 2);
  den('düz ve kalın yüz aynı anda isteniyor (ikinci gidiş gelişi beklemiyor)',
      ikiYuz.length > 0 && ikiYuz.every(f => f.fark !== null && f.fark <= 20),
      ikiYuz.map(f => f.yol + ':' + f.fark + 'ms').join(' | ') || 'iki yüzlü sayfa ölçülemedi');

  den('merdiven dışı bırakılan tek şey marka yazısı, o da gerçekten var',
      disindaBulunan.size > 0 && [...disindaBulunan].every(k => DISINDA.includes(k)),
      [...disindaBulunan].join(',') || 'hiçbiri bulunamadı');

  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
