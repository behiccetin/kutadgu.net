/* =====================================================================
   PANEL BOYU: kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   Bildirilen kusur, 13 Ağustos 2026: "panel hala uzun uzun".

   Ölçüldü ve doğruydu (1440 piksel genişlikte, on iki çalışmalık sınama
   verisiyle):

       Editör  sekmesi  5385px   (Çalışmalar ve hakem atama tek başına 4303)
       Hesabım sekmesi  3368px   (Profiliniz tek başına 2348)
       Özet    sekmesi  1370px   (Bulunduğunuz basamak tek başına  655)

   Sebep yerleşim değil YAKLAŞIMdı: her kart bütün ayrıntısını aynı anda
   açık tutuyordu. Oysa panelde üç ayrı cins içerik var ve üçü ayrı
   zamanlarda okunur:

       İŞ        şimdi yapılacak şey            -> hep açık
       DURUM     bir bakışta görülecek şey      -> kapağın üstünde
       AÇIKLAMA  bir kez okunacak şey           -> kapağın altında

   Kapağa alınan hiçbir şey kaybolmadı: her kapağın üstünde o kartın tek
   günlük bilgisi yazılı durur (belgenin durumu, kaç rapor ölçüldü, hangi
   basamaktasınız) ve karar bekleyen bir insan varsa kapak KENDİLİĞİNDEN
   açılır.

   BU KAPI NİYE VAR: uzunluk, eklenen her yeni kartla sessizce geri
   gelir. Bir kart eklendiğinde kimse "sekme kaç piksel oldu" diye
   bakmaz. Tavan burada yazılıdır; aşıldığında ölçüm bağırır.

   Tavanlar bugünkü ölçümün yaklaşık 1,2 katıdır: olağan içerik
   değişimine yer bırakır, iki katına çıkmaya bırakmaz.

   Kullanım:
     KUTADGU_DATA=<veri> KPORT=<kapı> node panel-boy-kapi.js
   ===================================================================== */
'use strict';
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');

const PORT = process.env.KPORT || '8941';
const VERI = process.env.KUTADGU_DATA || '';
const KOK  = 'http://127.0.0.1:' + PORT;

/* ÖLÇÜM HESABI BAŞ EDİTÖR OLMALIDIR — ve bu bir kolaylık değil, bu
   kapının ilk yazımındaki bir KUSURun düzeltmesidir.

   İlk yazımda hesap 'olcum-bas@example.org' adresiyle kuruluyor ve
   hesaplar.json'a 'roller' => ['editor','bas_editor'] yazılıyordu. Kapı
   32/0 verdi ve yeşil göründü. Oysa Yönetim sekmesini hiç ölçmemişti:
   hs_roller() 'bas_editor' rolünü hesap dosyasından SİLER ve yalnız
   adres ayar.php'deki kurul listesiyle eşleşiyorsa geri koyar (kurucu
   sıfatı sonradan verilemez). Sekme sunucuda hiç basılmadı, kapı da
   "görünen sekmeler" üzerinden döndüğü için onu atladı ve atladığını
   söylemedi.

   Ölçülmeyen bir sekme, ölçülmüş sayılmaz. Nitekim atlanan sekme
   sistemin EN UZUN sekmesiydi (2257px) ve "Deneme düzeni" kartı orada,
   sayfanın 2752 piksel aşağısında kayboluyordu.

   Adres, kurul-kapi.php'nin de kullandığı kurucu adresidir; başka bir
   adresle bu sekme ölçülemez. */
const KULLANICI = 'olcumbas';
const PAROLA    = 'olcum1234';
const EPOSTA    = 'cbehic@gmail.com';

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? ('  (' + ek + ')') : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

/* Ölçüm hesabı her koşuda garanti edilir. Kapının, elle kurulmuş bir
   hesabın varlığına güvenmesi, bir gün o hesap silindiğinde kapının
   "panel kısaldı" değil "panel yok" demesine yol açardı. */
function hesabiKur() {
  if (!VERI) { console.error('KUTADGU_DATA verilmedi.'); process.exit(2); }
  const kod = `
    $y = getenv('KUTADGU_DATA') . '/hesaplar.json';
    $h = json_decode((string)@file_get_contents($y), true); if (!is_array($h)) $h = [];
    $h = array_values(array_filter($h, fn($x) => ($x['kullanici'] ?? '') !== ${JSON.stringify(KULLANICI)}));
    $h[] = ['eposta' => ${JSON.stringify(EPOSTA)},
            'parola' => password_hash(${JSON.stringify(PAROLA)}, PASSWORD_DEFAULT),
            'ad' => 'Ölçüm Panel', 'unvan' => 'Prof. Dr.', 'kurum' => 'Ölçüm',
            'roller' => ['editor', 'bas_editor'], 'kullanici' => ${JSON.stringify(KULLANICI)},
            'katilim' => '2026-01-01', 'giris' => []];
    file_put_contents($y, json_encode($h, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    /* Giriş hız sınırı ölçümün kendisini kilitler: dokuz ekran, dokuz
       giriş. Sayaç sıfırlanır, yoksa ölçüm birdenbire her yerde sıfır
       gösterir ve kusur sanılır (bkz. OKUBENI 80). */
    $s = getenv('KUTADGU_DATA') . '/hiz-sinir.json';
    $d = json_decode((string)@file_get_contents($s), true);
    if (is_array($d)) {
      foreach (array_keys($d) as $k) if (strpos($k, 'giris:') === 0) unset($d[$k]);
      file_put_contents($s, json_encode($d));
    }
    echo 'ok';
  `;
  const c = execFileSync('php', ['-r', kod], { env: process.env, encoding: 'utf8' });
  if (c.trim() !== 'ok') { console.error('ölçüm hesabı kurulamadı: ' + c); process.exit(2); }
}

/* Sekme tavanları, 1440 piksel genişlikte. Ölçüm ve düzeltme tarihi
   13 Ağustos 2026; parantez içindeki sayı düzeltmeden ÖNCEki boydur. */
const TAVAN = {
  ozet:        950,   /* ölçüldü  720  (önce 1370) */
  calismalar:  700,   /* ölçüldü  298 */
  hakemlik:    700,   /* ölçüldü  297 */
  listem:      700,   /* ölçüldü  270 */
  hesap:      1700,   /* ölçüldü 1369  (önce 3368) */
  iletiler:   1200,   /* ölçüldü  938 */
  /* Editör sekmesi baş editöre üç ayrı iş listesi birden gösterir:
     yazar başvuruları, çalışmalar ve hakem atama, hakemlik süreçleri.
     Üçü de kapaklı ve sınırlı; tavan buna göre.

     ÖLÇÜMÜN TARİHÇESİ BURAYA YAZILIYOR, ÇÜNKÜ SAYININ KENDİSİ ANLATMIYOR:
       5385  ilk ölçüm (baş editör OLMAYAN hesapla — eksik ölçümdü)
       1979  kapaklar konduktan sonra, yine eksik ölçümle
      79723  baş editör hesabıyla ölçülünce görülen gerçek boy
       4032  başvuru ve hakemlik listeleri de sınırlandıktan sonra */
  editor:     2500,   /* ölçüldü 2040  (önce 79.723) */
  yonetim:    1450,   /* ölçüldü 1119  (önce  2257) */
};

/* Bu sekmelerin ölçülmüş olması zorunludur. Ölçüt "görünen sekmeler"
   olsaydı, bir sekme yetki yüzünden hiç basılmadığında kapı onu sessizce
   atlar ve yine yeşil verirdi — bir kez oldu, bir daha olmasın. */
const ZORUNLU = ['ozet', 'hesap', 'editor', 'iletiler', 'yonetim'];

/* Kapalı bir çalışma satırının tavanı. Asıl koruma budur: sekme boyu
   sınama verisindeki çalışma sayısına bağlıdır ve veri değişince
   değişir, ama SATIR BAŞINA boy yalnız tasarım değişince değişir. */
const SATIR_TAVAN = 120;   /* ölçüldü 86 (önce 330) */

(async () => {
  hesabiKur();
  const b = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
  const p = await b.newPage({
    viewport: { width: 1440, height: 900 },
    extraHTTPHeaders: { 'CF-Connecting-IP': '10.130.7.1' },
  });
  const hata = [];
  p.on('pageerror', e => hata.push('PAGEERROR ' + e));
  p.on('console', m => { if (m.type() === 'error') hata.push('CONSOLE ' + m.text()); });

  await p.goto(KOK + '/panel.php?lang=tr', { waitUntil: 'networkidle' });
  await p.fill('#gKim', KULLANICI);
  await p.fill('#gParola', PAROLA);
  await p.click('#dgGiris');
  await p.waitForTimeout(3000);

  const girdi = await p.evaluate(() => !!(document.getElementById('pnAd') || {}).textContent);
  den('ölçüm hesabıyla panele girildi', girdi);
  if (!girdi) { console.log('\nGECTI: ' + gecti + '   KALDI: ' + (++kaldi)); await b.close(); process.exit(1); }

  console.log('\n== 1. Sekme boyları (1440px) ==');
  const sekler = await p.evaluate(() =>
    [...document.querySelectorAll('.pn-sk[data-sek]')]
      .filter(x => !x.classList.contains('gizli')).map(x => x.dataset.sek));
  olc('görünen sekme: ' + sekler.join(', '));
  const eksik = ZORUNLU.filter(x => !sekler.includes(x));
  den('ölçülmesi zorunlu sekmelerin hepsi görünüyor', eksik.length === 0,
      'ölçülemeyen: ' + eksik.join(', '));

  for (const sk of sekler) {
    await p.click(`.pn-sk[data-sek="${sk}"]`);
    await p.waitForTimeout(600);
    const r = await p.evaluate((sk) => {
      const pnl = document.querySelector(`[data-pnl="${sk}"]`);
      if (!pnl) return null;
      const kart = [...pnl.querySelectorAll('.pn-kart')].filter(k => k.offsetHeight > 0)
        .map(k => {
          const h = k.querySelector('h2');
          return { ad: ((h && h.childNodes[0] && h.childNodes[0].nodeValue) || (h && h.textContent) || k.id || '?').trim().slice(0, 34),
                   h: Math.round(k.getBoundingClientRect().height) };
        }).sort((a, b2) => b2.h - a.h);
      return { h: Math.round(pnl.getBoundingClientRect().height),
               tasma: Math.round(document.documentElement.scrollWidth - document.documentElement.clientWidth),
               enUzun: kart[0] || null };
    }, sk);
    if (!r) { den(sk + ' bölümü sayfada', false); continue; }
    const tavan = TAVAN[sk];
    olc(sk.padEnd(11) + String(r.h).padStart(5) + 'px' + (tavan ? ('  / tavan ' + tavan) : '  / tavan yazılmamış')
        + (r.enUzun ? ('   en uzun kart: ' + r.enUzun.h + 'px ' + r.enUzun.ad) : ''));
    /* Tavanı yazılmamış bir sekme, kapıyı sessizce boşa çıkarır:
       yeni bir sekme eklendiğinde ölçülmemiş olur. */
    den(sk + ' sekmesinin tavanı yazılı', tavan !== undefined);
    if (tavan !== undefined) den('  ' + sk + ' tavanın altında', r.h <= tavan, r.h + ' > ' + tavan);
    /* ÖLÇÜLEN ŞEY TAŞMADIR, EŞİTLİK DEĞİL. 15 Ağustos 2026'da html'e
       scrollbar-gutter:stable kondu; kaydırma çubuğunun yeri her zaman
       ayrıldığı için içerik genişliği görünüm alanından 15 piksel dar
       kalabiliyor ve fark EKSİ çıkıyor. Eksi fark taşma değildir —
       içeriğin sığmasıdır. Eşitlik arayan eski ölçüm doğru düzeni
       kusurlu bildirdi. */
    den('  ' + sk + ' sayfayı yana kaydırmıyor', r.tasma <= 0, String(r.tasma));
  }

  console.log('\n== 2. Kapalı çalışma satırı ==');
  await p.click('.pn-sk[data-sek="editor"]');
  await p.waitForTimeout(900);
  /* ÇİP ŞERİDİ GELDİKTEN SONRA KART SEÇİLMEDEN ÖLÇÜLEMEZ.
     Sekme içi çipler artık kartları DEĞİŞTİRİYOR; seçili olmayan kart
     display:none. İlk koşuda ölçüm "kapalı satırların boyu: 0px" dedi
     ve deneme boşlukta GEÇTİ — yani hiçbir şey ölçmeden yeşil verdi.
     Bir ölçüm sıfır dönüyorsa önce ölçüleni GÖRÜP görmediğine bakılır. */
  const cipSecildi = await p.evaluate(() => {
    const c = [...document.querySelectorAll('[data-pnl="editor"] .pn-yol button')]
      .filter(b => /hakem atama|reviewer assignment/i.test(b.textContent))[0];
    if (!c) return false;
    c.click();
    return true;
  });
  den('çalışmalar çipi bulundu ve seçildi', cipSecildi);
  await p.waitForTimeout(600);
  const satir = await p.evaluate(() => [...document.querySelectorAll('#calListe > details')].map(d => ({
    acik: d.open,
    h: Math.round(d.getBoundingClientRect().height),
    bekleyen: /karar bekliyor|await a decision|awaits a decision/.test(d.querySelector('summary').textContent),
  })));
  olc('çalışma satırı: ' + satir.length + ' tane, kapalı olanların boyu: '
      + [...new Set(satir.filter(x => !x.acik).map(x => x.h))].join(',') + 'px');
  den('çalışma listesi çiziliyor', satir.length > 0);
  /* Sıfır boy, ölçülemeyen bir satır demektir; geçmiş sayılmaz. */
  den('  satırlar gerçekten görünüyor (boy sıfır değil)',
      satir.every(x => x.h > 0), '0 piksel ölçülen satır var');
  const kapali = satir.filter(x => !x.acik);
  den('  kapalı satırlar tavanın altında',
      kapali.every(x => x.h <= SATIR_TAVAN),
      'en uzun ' + Math.max(0, ...kapali.map(x => x.h)) + ' > ' + SATIR_TAVAN);
  /* Karar bekleyen bir insan kapağın arkasında kalamaz. Bu, kısaltmanın
     bedelidir ve ödenmemesi gerekir: kısalık uğruna bekleyen bir işi
     saklamak, kısaltmayı bir kusura çevirir.

     ÖLÇÜLECEK VERİ YOKSA GEÇTİ YAZILMAZ. Sınama verisinde karar bekleyen
     gönüllü ya da öneri bulunmayabilir; o durumda deneme boşlukta geçer
     ve "sınandı" sanılır. Boş geçen bir deneme, yapılmamış bir denemedir
     — hangisi olduğu yazılır. */
  const bekleyenler = satir.filter(x => x.bekleyen);
  if (!bekleyenler.length) {
    olc('sınama verisinde karar bekleyen çalışma yok; kendiliğinden açılma denenemedi');
  } else {
    den('  karar bekleyen çalışma kendiliğinden açık (' + bekleyenler.length + ' tane)',
        bekleyenler.every(x => x.acik),
        bekleyenler.filter(x => !x.acik).length + ' kapalı kalmış');
  }

  console.log('\n== 3. Kapaklar bilgiyi gizlemiyor ==');
  /* Bir kapağın meşruluğu, üstünde ne yazdığına bağlıdır. Boş bir
     kapak, bilgiyi sıraya koymaz; saklar. */
  const kapak = await p.evaluate(() => {
    const oku = id => {
      const e = document.getElementById(id);
      return e ? (e.textContent || '').trim() : null;
    };
    return { srOzet: oku('srOzet'), belgeOzet: oku('belgeOzet'), pnBasamak: oku('pnBasamak') };
  });
  await p.click('.pn-sk[data-sek="hesap"]'); await p.waitForTimeout(600);
  await p.click('.pn-sk[data-sek="ozet"]');  await p.waitForTimeout(600);
  const kapak2 = await p.evaluate(() => ({
    belgeOzet: (document.getElementById('belgeOzet') || {}).textContent,
    pnBasamak: (document.getElementById('pnBasamak') || {}).textContent,
    /* SEÇİCİ HESAP PANELİNE GÖRE DARALTILDI: '.pn-grup' deyimi davet
       kartındaki rol seçicisinde de kullanılıyor ve ölçüm onu profil
       grubu sandı (3 yerine 4). Ölçüt artık YERE bağlı: profil kartı
       Hesabım sekmesindedir. */
    grup: [...document.querySelectorAll('[data-pnl="hesap"] .pn-grup > summary')].map(s => ({
      ad: (s.querySelector('b') || {}).textContent,
      ack: ((s.querySelector('span') || {}).textContent || '').trim(),
    })),
  }));
  den('süre kapağında ne olduğu yazılı', !!(kapak.srOzet && kapak.srOzet.length > 5), String(kapak.srOzet));
  den('belge kapağında durum yazılı', !!(kapak2.belgeOzet && kapak2.belgeOzet.length > 5), String(kapak2.belgeOzet));
  den('basamak kapağında bulunulan basamak yazılı',
      !!(kapak2.pnBasamak && kapak2.pnBasamak.length > 3), String(kapak2.pnBasamak));
  den('profil grupları üçe ayrılmış', kapak2.grup.length === 3, String(kapak2.grup.length));
  den('  her grubun ne içerdiği yazılı',
      kapak2.grup.every(g => g.ad && g.ack && g.ack.length > 10),
      JSON.stringify(kapak2.grup));

  console.log('\n== 4. Kapalı alanlar forma dâhil ==');
  /* Kapak bir görsel kolaylıktır; kapalı bir gruptaki alanın kaydedilmemesi
     sessiz veri kaybı olurdu. */
  const dahil = await p.evaluate(() => {
    const t = document.getElementById('pUyelik');
    if (!t) return null;
    const eski = t.value;
    t.value = 'ÖLÇÜM';
    const okundu = document.getElementById('pUyelik').value;
    t.value = eski;
    const grup = t.closest('details.pn-grup');
    return { kapaliMi: grup ? !grup.open : null, okundu: okundu === 'ÖLÇÜM' };
  });
  den('üyelikler alanı kapalı grupta', dahil && dahil.kapaliMi === true);
  den('  buna rağmen betikten okunabiliyor', !!(dahil && dahil.okundu));

  den('sayfada betik hatası yok', hata.length === 0, hata.join(' | '));

  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  await b.close();
  process.exit(kaldi > 0 ? 1 : 0);
})();
