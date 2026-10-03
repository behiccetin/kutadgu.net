/* =====================================================================
   WORD'DEN YAPIŞTIRMA · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   Ölçülen şey: k/yapistir.js gerçekten BİÇİMİ atıp YAPIYI koruyor mu.

   FİKSTÜR UYDURULMADI. Ölçümde kullanılan HTML, sablon/yap.js ile
   üretilen gerçek Word şablonunun bir ofis yazılımıyla HTML'e
   çevrilmiş hâlidir (sablon/Kutadgu-calisma-sablonu.html). Elle yazılmış
   bir "Word'e benzer" HTML, kendi beklentimizi ölçmek olurdu; bu dosya
   ise gerçekten bir ofis yazılımının ürettiği şeydir — <font> etiketleri,
   class="western" sınıfları ve satır içi stilleriyle birlikte.

   Kullanım: KPORT=8941 node yapistir-kapi.js
   ===================================================================== */
'use strict';
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const KOK  = path.join(__dirname, '..');
const KOD  = process.env.KTEST_DIR || path.join(KOK, 'ktest');
const FIKS = path.join(KOK, 'sablon', 'Kutadgu-calisma-sablonu.html');

let gecti = 0, kaldi = 0;
const den = (ad, ok, ek = '') => {
  if (ok) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
};
const olc = s => console.log('  ÖLÇÜM  ' + s);

(async () => {
  if (!fs.existsSync(FIKS)) {
    console.error('Fikstür yok: ' + FIKS + '\n  sablon/ içinde şablonu HTML\'e çevirin.');
    process.exit(2);
  }
  const betik = fs.readFileSync(path.join(KOD, 'k', 'yapistir.js'), 'utf8');
  const ofis  = fs.readFileSync(FIKS, 'utf8');

  const b = await chromium.launch();
  const p = await b.newPage();
  const hata = [];
  p.on('pageerror', e => hata.push(String(e)));
  await p.setContent('<!doctype html><meta charset="utf-8"><body></body>');
  await p.addScriptTag({ content: betik });

  console.log('== 1. Modül ayakta ==');
  den('sayfa hatası yok', hata.length === 0, hata.join(' | '));
  den('kutYapistir tanımlı', await p.evaluate(() => !!window.kutYapistir));
  den('  üç işlev de var', await p.evaluate(() =>
    !!(window.kutYapistir.temizle && window.kutYapistir.icindekiler && window.kutYapistir.kaynakcaVar)));

  const s = await p.evaluate(h => window.kutYapistir.temizle(h), ofis);
  olc('girdi ' + ofis.length + ' bayt -> çıktı ' + s.html.length + ' bayt');

  console.log('\n== 2. Biçim atıldı ==');
  for (const [ad, kal] of [['<font', 'font etiketi'], ['<span', 'span etiketi'],
                           ['class=', 'class özniteliği'], ['style=', 'style özniteliği'],
                           ['<style', 'style bloğu'], ['<meta', 'meta etiketi']]) {
    den('  ' + kal + ' kalmadı', !s.html.toLowerCase().includes(ad),
        s.html.toLowerCase().split(ad).length - 1 + ' tane');
  }

  console.log('\n== 3. Yapı korundu: başlık eşlemesi ==');
  /* Sözleşme: h1->h2, h2->h3, h3->h4. Gerekçe k/yapistir.js başında. */
  const say = await p.evaluate(html => {
    const d = document.implementation.createHTMLDocument('');
    d.body.innerHTML = html;
    const o = {};
    ['h1','h2','h3','h4','h5','p','ul','li','table','tr','th','td','strong','em'].forEach(t => {
      o[t] = d.body.querySelectorAll(t).length;
    });
    return o;
  }, s.html);
  olc(JSON.stringify(say));
  den('h1 HİÇ kalmadı (sayfanın h1\'i çalışmanın başlığıdır)', say.h1 === 0, String(say.h1));
  den('  şablondaki 8 Başlık 1 -> h2 oldu', say.h2 === 8, String(say.h2));
  den('  Başlık 2 -> h3 oldu', say.h3 === 1, String(say.h3));
  den('  Başlık 3 -> h4 oldu', say.h4 === 1, String(say.h4));
  den('paragraflar duruyor', say.p > 5, String(say.p));
  den('madde listesi duruyor', say.ul >= 1 && say.li >= 4, 'ul=' + say.ul + ' li=' + say.li);
  den('çizelge duruyor', say.table === 1 && say.tr >= 3, 'table=' + say.table + ' tr=' + say.tr);
  den('  çizelgenin hücreleri de duruyor', (say.td + say.th) >= 9, 'td+th=' + (say.td + say.th));

  console.log('\n== 4. İçindekiler ==');
  const ic = await p.evaluate(h => window.kutYapistir.icindekiler(h), s.html);
  olc('başlık: ' + ic.length + ' · ilk: ' + (ic[0] ? ic[0].ad : '-') + ' · son: ' + (ic.length ? ic[ic.length-1].ad : '-'));
  den('içindekiler başlıklardan üretildi', ic.length === 10, String(ic.length));
  den('  düzeyler taşınıyor', ic.some(x => x.d === 2) && ic.some(x => x.d === 3) && ic.some(x => x.d === 4));
  den('  son başlık Kaynakça', ic.length > 0 && /kaynak/i.test(ic[ic.length - 1].ad), ic.length ? ic[ic.length-1].ad : '');
  den('kaynakçaVar() doğru diyor', await p.evaluate(h => window.kutYapistir.kaynakcaVar(h), s.html));
  const yokHtml = '<h2>Giriş</h2><p>metin</p>';
  den('  kaynakça yokken YANLIŞ demiyor', !(await p.evaluate(h => window.kutYapistir.kaynakcaVar(h), yokHtml)));
  den('başlıksız metinde boş liste dönüyor (uydurmuyor)',
      (await p.evaluate(() => window.kutYapistir.icindekiler('<p>yalnız metin</p>').length)) === 0);

  console.log('\n== 5. Tehlikeli içerik ==');
  const kotu = await p.evaluate(() => window.kutYapistir.temizle(
    '<p>iyi</p><script>kotu()<\/script><p onclick="kotu()">tik</p>' +
    '<a href="javascript:kotu()">bag</a><iframe src="x"></iframe>' +
    '<img src="file:///C:/Users/x/sekil.png"><img src="data:image/png;base64,iVBOR">'));
  den('script atıldı', !/script/i.test(kotu.html));
  den('olay özniteliği atıldı', !/onclick/i.test(kotu.html));
  den('iframe atıldı', !/iframe/i.test(kotu.html));
  den('javascript: adresi bağlantı olmadı', !/javascript:/i.test(kotu.html));
  den('yerel görsel atıldı ve SAYILDI', kotu.yerelGorsel >= 1 && !/file:\/\//.test(kotu.html), 'sayı=' + kotu.yerelGorsel);
  den('  gömülü (data:) görsel KALDI', /data:image\//.test(kotu.html));

  console.log('\n== 6. Word\'ün donmuş içindekiler listesi ==');
  const toc = await p.evaluate(() => window.kutYapistir.temizle(
    '<p><a href="#_Toc123">1. Giris</a></p><p><a href="#_Toc124">2. Yontem</a></p><h1>1. Giris</h1><p>metin</p>'));
  den('yapıştırılan TOC atıldı', !/_Toc/.test(toc.html));
  den('  ama gerçek başlık kaldı', /<h2>/.test(toc.html), toc.html.slice(0, 80));

  console.log('\n== 7. Düzenleyiciye bağlandı mı ==');
  const kd = fs.readFileSync(path.join(KOD, 'k', 'duzenleyici.php'), 'utf8');
  den('yapistir.js sayfaya yükleniyor', kd.includes('/k/yapistir.js'));
  den('kanca tarayıcının kendi paste olayına takılı',
      /addEventListener\('paste'/.test(kd));
  den('  yalnız makale metninde (tam) çalışıyor', /A\.tam && window\.kutYapistir/.test(kd));
  den('yapı paneli işlevi var', /function kd_yapi\(/.test(kd));
  den('  şablon indirme bağlantısı panelde', kd.includes('Kutadgu-calisma-sablonu.docx'));
  den('  başlık yokken NE YAPILACAĞI yazılı', /Başlık 1, Başlık 2, Başlık 3 stiliyle/.test(kd));
  den('makale metninde "biçimi koruyayım mı" penceresi kapalı',
      /askBeforePasteFromWord: !A\.tam/.test(kd));

  console.log('\n== 8. Dış sunucuya gitmiyor ==');
  /* ÖLÇÜLEN KUSUR — 15 Ağustos 2026. k/duzenleyici.php'nin başında
     "Neden CDN'den değil" diye bir bölüm var, ama kaynak görünümü
     düğmesine basıldığında düzenleyici cdnjs.cloudflare.com'dan ace.js
     ve beautify.min.js çekiyordu. Sistem, uygulamadığı bir kuralı
     duyuruyordu. Kapı artık bunu ölçer: bir daha eklenirse görür. */
  den('kaynak görünümü düz metin alanı (ace CDN\'i yok)',
      /sourceEditor: 'area'/.test(kd));
  den('  biçimlendirici de kapalı (beautify CDN\'i yok)',
      /beautifyHTML: false/.test(kd));
  den('modülde hiçbir dış adres yok',
      !/https?:\/\/(?!127\.0\.0\.1)/.test(kd.replace(/\/\*[\s\S]*?\*\//g, '')),
      (kd.replace(/\/\*[\s\S]*?\*\//g, '').match(/https?:\/\/[^'"\s]+/g) || []).join(','));

  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  await b.close();
  process.exit(kaldi > 0 ? 1 : 0);
})();
