/* =====================================================================
   GÖNDERİM SİHİRBAZI: tarayıcı ölçümü. Depoya girmez.

   sihirbaz-kapi.php kaynaktan ve sunucudan bilinebilenleri ölçer.
   Burada ölçülenler yalnız TARAYICIDA bilinebilir:

     - Adımlar teker teker mi açılıyor, ötekiler gerçekten kapalı mı.
     - Sekme sırası kapalı bir adımın içine düşüyor mu (sihirbazların
       en bilinen kusuru: sınıfla gizlenmiş ama odaklanabilir alan).
     - Adım adım doğrulama: eksik alan, o adımdan çıkmayı engelliyor mu.
     - Yarıda bırakılan taslak sayfa yenilendiğinde geri geliyor mu.
     - Taslak SUNUCUYA gitmiyor mu (yazarken hiçbir istek çıkmamalı).
     - Odak, yeni adımın başına gidiyor mu.

   Neden ayrı dosya: bu ölçümler bir tarayıcı gerektirir ve PHP kapısı
   onları ölçemez. Ölçemediğimize GECTI vermiyoruz (OKUBENI, genel kural).

   ÖLÇÜM TUZAKLARI
     - OKUBENI 6/25: sayfanın dili varsayılan İngilizcedir; yerel istek
       zincirin sonuna düşer. Türkçe metin ölçülecekse ?lang=tr açıkça
       verilmeli.
     - OKUBENI 18: odak halkası geçişli çizilir. Burada halka değil
       ODAĞIN KENDİSİ ölçülüyor (document.activeElement), o anında
       değişir; yine de smooth kaydırma bittikten sonra bakılıyor.
     - OKUBENI 30: gizlilik ögenin kendisinden okunmaz; hidden
       özniteliği taşıyan ÜSTÖGE aranır.

   Kullanım:
     KPORT=8941 node sihirbaz-tarayici.js
   ===================================================================== */
const { chromium } = require('playwright');

const PORT = process.env.KPORT || '8941';
const KOK  = 'http://127.0.0.1:' + PORT;
const YOL  = '/basvuru.php?lang=tr';

let gecti = 0, kaldi = 0;
function den(ad, sonuc, ek) {
  if (sonuc) { gecti++; console.log('  GECTI  ' + ad); }
  else { kaldi++; console.log('  KALDI  ' + ad + (ek ? '  (' + ek + ')' : '')); }
}
function olc(s) { console.log('  ÖLÇÜM  ' + s); }

/* Görünür adımların anahtarları. Gizlilik ağaçta YUKARI çıkarak
   belirlenir (OKUBENI 30). */
const GORUNUR = () => [...document.querySelectorAll('[data-adim]')]
  .filter(e => {
    for (let a = e; a && a !== document.documentElement; a = a.parentElement) {
      const s = getComputedStyle(a);
      if (a.hasAttribute('hidden') || s.display === 'none' || s.visibility === 'hidden') return false;
    }
    return true;
  })
  .map(e => e.getAttribute('data-adim'));

/* ---------------------------------------------------------------------
   TAM METİN ADIMINI DOLDUR.

   15 Ağustos 2026 kurul kararıyla gönderim TAM METNİ de alıyor. Alan
   bir <textarea> değil, düzenleyicidir: fill() gizli bir ögede
   çalışmaz ve çalışmaması bir kusur değil, alanın türüdür. Değer tek
   kapıdan yazılır — kdAyarla(), sayfanın kendi arayüzü.
   --------------------------------------------------------------------- */
async function metinDoldur(s) {
  await s.evaluate(() => {
    const g = '<h2>Giris</h2><p>' + 'olcum metni '.repeat(500) + '</p>';
    const k = '<p>Olcum, K. (2026). Sihirbaz olcumu. Sinama Yayinlari.</p>';
    if (window.kdAyarla) { window.kdAyarla('bvMetin', g); window.kdAyarla('bvKaynakca', k); }
    else {
      const a = document.getElementById('bvMetin'), b = document.getElementById('bvKaynakca');
      if (a) a.value = g; if (b) b.value = k;
    }
  });
  await s.waitForTimeout(120);
}

/* Ölçüm hesabıyla giriş: gönderim artık hesap ister (kurul kararı,
   15 Ağustos 2026). Sayfa yeniden yüklenir ve alanlar dolu gelir. */
async function olcumGirisi(s, KOK) {
  await s.goto(KOK + '/panel.php?lang=tr', { waitUntil: 'networkidle' });
  await s.fill('#gKim', 'olcumbas');
  await s.fill('#gParola', 'olcum1234');
  await s.click('#dgGiris');
  await s.waitForTimeout(1500);
}

/* ---------------------------------------------------------------------
   KÜNYE DİLİ ALANLARINI DOLDUR.

   14 Ağustos 2026: 1. adım artık künye dilinde de başlık + öz istiyor,
   diller farklıysa ayrıca genişletilmiş özet arıyor. Bu alanlar KAPALI
   bir sekmenin içindedir; kapı sekmeye TIKLAR, çünkü kullanıcı da
   tıklar. Alanları sekmeyi açmadan doldurmak, kullanıcının hiç
   yürümediği bir yoldan ölçüm yapmak olurdu.

   Eşik (kaç kelime) SAYFADAN okunur, buraya elle yazılmaz: yazılsaydı
   ayar.php'deki sayı değiştiği gün kapı yanlış sayıyı arardı ve
   arızayı ayarın değil kodun üstüne yıkardı.

   Sekme yalnız künye dili çalışmanın dilinden FARKLIYKEN vardır;
   aynıysa doldurulacak bir şey yoktur ve false döner.
   --------------------------------------------------------------------- */
async function kunyeDoldur(s, baslikEn, olcYaz) {
  if (!(await s.isVisible('#mDilSkKunye'))) return false;
  await s.click('#mDilSkKunye');
  await s.waitForTimeout(200);
  await s.fill('#mBaslikEn', baslikEn);
  await s.fill('#mOzetEn', 'This abstract was written in the citation language for the wizard test. ');
  if (await s.isVisible('#mGenisOzet')) {
    const az = await s.evaluate(() => (typeof GENIS_AZ !== 'undefined' ? GENIS_AZ : 500));
    if (olcYaz) olc('genişletilmiş özet eşiği: ' + az + ' kelime');
    await s.fill('#mGenisOzet', 'measurement word '.repeat(az + 40));
  }
  await s.click('#mDilSkAna');
  await s.waitForTimeout(150);
  return true;
}

(async () => {
  const tarayici = await chromium.launch();
  const baglam = await tarayici.newContext({ viewport: { width: 1440, height: 900 } });
  const s = await baglam.newPage();

  /* Sunucuya giden yazma isteklerini say: taslak sunucuya GİTMEMELİ. */
  let yazmaIstegi = [];
  s.on('request', r => { if (r.method() !== 'GET' && r.method() !== 'HEAD') yazmaIstegi.push(r.method() + ' ' + r.url()); });

  const y = await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
  den('sayfa 200 dönüyor', y && y.status() === 200, String(y && y.status()));

  /* -----------------------------------------------------------------
     1. ADIMLAR TEKER TEKER
     ----------------------------------------------------------------- */
  console.log('== 1. Adımlar teker teker açılıyor mu ==');
  const toplam = await s.evaluate(() => document.querySelectorAll('[data-adim]').length);
  olc('sayfada ' + toplam + ' adım var');
  den('adım sayısı yediden çok', toplam >= 7, String(toplam));

  let gor = await s.evaluate(GORUNUR);
  den('yalnız bir adım görünür', gor.length === 1, gor.join(','));
  /* BEKLENTİ BİLEREK DEĞİŞTİ: birinci adım artık GÖNDERİM KOŞULLARI.
     Koşullar sayfanın önsözüyken formun ilk alanı 2186. pikseldeydi;
     adım hâline gelince şerit 400. piksele, form ilk ekrana geldi. */
  den('  görünen adım GÖNDERİM KOŞULLARI', gor[0] === 'kosul', String(gor[0]));

  /* Koşul adımı bir OKUMA adımıdır: geçmek için kutu aranmaz, çünkü
     kutu artık son adımdadır (beyan gönderirken verilir). Buradaki
     ölçü, adımın okunup geçilebilmesidir. */
  await s.click('#shIleri'); await s.waitForTimeout(250);
  let gorK = await s.evaluate(GORUNUR);
  den('  koşul adımı okunup geçilebiliyor', gorK[0] === 'calisma', gorK.join(','));
  /* KOŞULU DAHA ÖNCE OKUYAN ADIMI GÖRMEZ. Bildirilen istek: "kayıtlı
     kullanıcı her çalışma göndereceğinde koşulları baştan görmese."
     Girişsiz okuyucu için bunu tarayıcının kendi kaydı bilir. Ölçülen
     üç hâl: hiç okumamış, okumuş, ve metin okuduğundan beri değişmiş. */
  await s.evaluate(() => localStorage.setItem('kutadgu-kosul-surum',
    document.getElementById('bvForm').dataset.kosulSurum));
  await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
  let gorA = await s.evaluate(GORUNUR);
  den('  koşulu okumuş olan doğrudan ÇALIŞMA adımından başlıyor', gorA[0] === 'calisma', gorA.join(','));
  den('    atlanan adım silinmedi: notuyla birlikte duruyor',
      await s.evaluate(() => {
        const n = document.querySelector('#f-kosul .kosul-not');
        return !!n && !n.hidden && /koşul/i.test(n.textContent);
      }));
  await s.evaluate(() => localStorage.setItem('kutadgu-kosul-surum', 'eski-surum'));
  await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
  gorA = await s.evaluate(GORUNUR);
  den('  KOŞULLAR DEĞİŞTİYSE adım yeniden gösteriliyor', gorA[0] === 'kosul', gorA.join(','));
  await s.evaluate(() => localStorage.removeItem('kutadgu-kosul-surum'));
  await s.goto(KOK + YOL, { waitUntil: 'networkidle' });

  /* Koşulların tam metni sayfada duruyor: katlanmış olması gizlenmiş
     olması değildir (gizli metin yasağı, gizli-metin-kapi.js). */
  const kosulMetin = await s.evaluate(() =>
    (document.querySelector('#f-kosul') || {}).textContent || '');
  /* İŞARET SEÇİMİ KURALA BAĞLIDIR. 15 Ağustos 2026'da "Yazar niteliği"
     kartı, doktora şartı kapalıyken kaldırıldı; ORCID zorunluluğu artık
     Başvuran adımında alanın yanında yazılı. Eski ölçüm ORCID sözcüğünü
     koşul kutusunda arıyordu ve kart kalkınca KALDI verdi — ama ölçülen
     şey "ORCID burada yazıyor mu" değil, "koşulların tam metni katlı da
     olsa DOM'da duruyor mu"dur. İşaret olarak her koşulda bulunan CC BY
     alınır; ORCID yalnızca kart varken aranır. Var olmayan bir kartın
     metnini eksik saymak, olmayan bir şeyi kusur bildirmek olurdu. */
  const nitelikVar = await s.evaluate(() =>
    [...document.querySelectorAll('#f-kosul .ks summary h3')]
      .some(h => /Yazar niteli|Author standing/i.test(h.textContent)));
  den('  koşulların tam metni sayfada duruyor (katlı, gizli değil)',
      /CC BY/.test(kosulMetin) && (!nitelikVar || /ORCID/.test(kosulMetin)),
      String(kosulMetin.length) + ', nitelikKarti=' + nitelikVar);

  /* Kapatma "hidden" ÖZNİTELİĞİYLE mi yapılıyor? Sınıfla gizlenmiş bir
     adım ekrandan kalkar ama sekme sırasında ve erişilebilirlik
     ağacında durur. */
  const hiddenSay = await s.evaluate(() =>
    [...document.querySelectorAll('[data-adim]')].filter(e => e.hasAttribute('hidden')).length);
  den('kapalı adımlar hidden ÖZNİTELİĞİ taşıyor (sınıfla gizlenmiş değil)',
      hiddenSay === toplam - 1, hiddenSay + '/' + (toplam - 1));

  /* -----------------------------------------------------------------
     1.b FORM İLK EKRANDA MI
     -----------------------------------------------------------------
     Bildirilen kusur: "kullanıcı her çalışma göndereceğinde kurallar
     başta yazan sayfaya geliyor, altındaki formla gönderiyor."
     Ölçüldü ve doğrulandı: adım şeridi 1802. pikselde, formun ilk
     alanı 2186. pikseldeydi; yazar formu görmeden önce iki ekran boyu
     metin kaydırıyordu. Koşullar silinmedi, sihirbazın birinci adımı
     oldu. Ölçü şudur: ŞERİT İLK EKRANDA BAŞLAMALI.
     ----------------------------------------------------------------- */
  console.log('\n== 1.b Form ilk ekranda mı ==');
  const yer = await s.evaluate(() => {
    const ust = e => e ? Math.round(e.getBoundingClientRect().top + scrollY) : -1;
    const gorunurAdim = [...document.querySelectorAll('[data-adim]')].filter(e => !e.hidden)[0];
    const alan = gorunurAdim
      ? [...gorunurAdim.querySelectorAll('input,select,textarea,summary')]
          .filter(e => e.getClientRects().length)[0] : null;
    return { serit: ust(document.querySelector('.sh-serit')),
             ilkAlan: ust(alan),
             sayfa: Math.round(document.documentElement.scrollHeight) };
  });
  olc('şerit ' + yer.serit + 'px · ilk alan ' + yer.ilkAlan + 'px · sayfa ' + yer.sayfa + 'px');
  den('adım şeridi ilk ekranda (<= 700px)', yer.serit > 0 && yer.serit <= 700, String(yer.serit));
  den('  birinci adımın ilk alanı ilk ekranda (<= 900px)',
      yer.ilkAlan > 0 && yer.ilkAlan <= 900, String(yer.ilkAlan));

  /* -----------------------------------------------------------------
     2. SEKME SIRASI KAPALI ADIMA DÜŞMÜYOR
     ----------------------------------------------------------------- */
  console.log('\n== 2. Sekme sırası ==');
  await s.evaluate(() => { const e = document.getElementById('mDil'); if (e) e.focus(); });
  let kacak = 0, gezilen = 0;
  for (let i = 0; i < 40; i++) {
    await s.keyboard.press('Tab');
    gezilen++;
    const nerede = await s.evaluate(() => {
      const a = document.activeElement;
      if (!a) return null;
      const k = a.closest('[data-adim]');
      const gizliKap = a.closest('[hidden]');
      return { adim: k ? k.getAttribute('data-adim') : null, gizli: !!gizliKap };
    });
    if (nerede && nerede.gizli) kacak++;
  }
  olc(gezilen + ' kez Tab basıldı');
  den('sekme sırası hiç kapalı adımın içine düşmedi', kacak === 0, String(kacak));

  /* -----------------------------------------------------------------
     3. ADIM ADIM DOĞRULAMA
     ----------------------------------------------------------------- */
  console.log('\n== 3. Adım adım doğrulama ==');
  /* Koşul adımı okuma adımıdır ve boş geçilir; doğrulama ondan sonra
     başlar. Bu satır olmadan ilk tıklama koşulu geçiyor, ölçüm ise
     "başlık boşken durdurdu" sanıyordu — geçen şey doğrulama değil,
     okuma adımıydı. */
  if ((await s.evaluate(GORUNUR))[0] === 'kosul') { await s.click('#shIleri'); await s.waitForTimeout(200); }
  await s.click('#shIleri');
  await s.waitForTimeout(200);
  gor = await s.evaluate(GORUNUR);
  const mesaj1 = (await s.textContent('#mesaj') || '').trim();
  den('başlık boşken İleri ilerletmiyor', gor[0] === 'calisma', gor.join(','));
  den('  eksik olanın ne olduğu söyleniyor', mesaj1.length > 5, mesaj1.slice(0, 60));

  /* HATA, ALANIN YANINDA MI. Bildirilen kusur (15 Ağustos 2026):
     "formu doldururken nerede hata olduğunu anlayamıyorum." Formun
     altındaki tek satır hangi alanın yanlış olduğunu söylemez. Üç şey
     birden ölçülür: alan aria-invalid alıyor mu, metin o alanın hemen
     yanında duruyor mu, ve ikisi aria-describedby ile bağlı mı — biri
     eksikse ya göz ya ekran okuyucu hatayı bulamaz. */
  const yanHata = await s.evaluate(() => {
    const el = document.querySelector('[aria-invalid="true"]');
    if (!el) return { yok: true };
    const id = el.getAttribute('aria-describedby') || '';
    const p  = id ? document.getElementById(id) : null;
    const kap = el.closest('.alan') || el;
    return {
      alan: el.id || el.className,
      metin: p ? p.textContent.trim() : '',
      bagli: !!p && p.classList.contains('alan-hata'),
      komsu: !!p && (kap.nextSibling === p),
      gorunur: !!(p && p.offsetParent),
    };
  });
  olc('alan hatası: ' + JSON.stringify(yanHata));
  den('  hatalı alan aria-invalid alıyor', !yanHata.yok, JSON.stringify(yanHata));
  den('  hata metni o alanın yanında duruyor', !!yanHata.komsu && !!yanHata.gorunur);
  den('  metin alana aria-describedby ile bağlı', !!yanHata.bagli && yanHata.metin.length > 5,
      yanHata.metin.slice(0, 60));
  den('  aynı cümle formun altındaki iletiyle aynı', yanHata.metin === mesaj1,
      yanHata.metin.slice(0, 40) + ' | ' + mesaj1.slice(0, 40));

  await s.fill('#mBaslik', 'Sihirbaz sınaması için bir başlık');
  await s.click('#shIleri');
  await s.waitForTimeout(200);
  gor = await s.evaluate(GORUNUR);
  den('alan seçilmeden de ilerlemiyor (bilim alanı zorunlu)',
      gor[0] === 'calisma', gor.join(','));

  /* Bilim alanı seç: kutunun kendi arayüzünden. */
  await s.evaluate(() => { if (window.alSecYaz) window.alSecYaz('alanlar', ['5.2']); });

  /* ---- KÜNYE DİLİ ADIMI ----
     14 Ağustos 2026'da bu bölüm KIRILDI ve kırılması doğruydu: 1. adım
     artık künye dilinde de başlık + öz istiyor ve diller farklıysa
     genişletilmiş özet arıyor (kurul kararı, eşiği ayar.php'den gelir).
     Kapı bunları doldurmuyordu, dolayısıyla ilerleyemiyordu.

     Alanlar KAPALI bir sekmenin içindedir. Kapı sekmeye TIKLAR — çünkü
     kullanıcı da tıklar — ve tıkladıktan sonra alanın görünür olduğunu
     ayrıca ölçer. Alanları sekmeyi açmadan JavaScript ile doldurmak,
     kullanıcının hiç yürümediği bir yoldan ölçüm yapmak olurdu.

     Sekme yalnız künye dili çalışmanın dilinden FARKLIYKEN vardır;
     aynıysa gizlenir ve o hâlde doldurulacak bir şey de yoktur. */
  const kunyeVar = await s.isVisible('#mDilSkKunye');
  olc('künye dili sekmesi: ' + (kunyeVar ? 'var' : 'yok (diller aynı)'));
  await kunyeDoldur(s, 'A title in English for the wizard test', true);
  if (kunyeVar) {
    await s.click('#mDilSkKunye'); await s.waitForTimeout(150);
    den('künye sekmesi ikinci dil alanlarını açıyor', await s.isVisible('#mBaslikEn'));
    await s.click('#mDilSkAna'); await s.waitForTimeout(120);
  }

  await s.click('#shIleri');
  await s.waitForTimeout(300);
  gor = await s.evaluate(GORUNUR);
  /* 15 Ağustos 2026: çalışmadan sonra TAM METİN adımı geliyor. */
  den('gerekenler dolunca tam metin adımına geçiyor', gor[0] === 'metin', gor.join(','));
  den('  önceki adım artık kapalı',
      await s.evaluate(() => document.querySelector('[data-adim="calisma"]').hasAttribute('hidden')));
  den('  tam metin adımında düzenleyici kuruldu (araç çubuğu var)',
      await s.evaluate(() => !!document.querySelector('#f-metin .jodit-toolbar__box')));
  den('  şekil eklemeyi anlatan kutu var',
      await s.evaluate(() => /şekil|figure/i.test(
        (document.querySelector('#f-metin .kutu') || {}).textContent || '')));
  /* Metin boşken ilerlemiyor: gönderim artık tam metin istiyor. */
  await s.click('#shIleri'); await s.waitForTimeout(250);
  den('  metin boşken ilerlemiyor', (await s.evaluate(GORUNUR))[0] === 'metin');
  await metinDoldur(s);
  await s.click('#shIleri');
  await s.waitForTimeout(300);
  gor = await s.evaluate(GORUNUR);
  den('metin girilince başvuran adımına geçiyor', gor[0] === 'basvuran', gor.join(','));

  /* Odak yeni adımın başına gitmeli: gitmezse ekran okuyucu kullanıcısı
     sayfanın neresinde olduğunu bilemez. */
  const odak = await s.evaluate(() => {
    const a = document.activeElement;
    const k = a && a.closest ? a.closest('[data-adim]') : null;
    return { adim: k ? k.getAttribute('data-adim') : null, etiket: a ? a.tagName.toLowerCase() : null };
  });
  olc('odak: ' + JSON.stringify(odak));
  den('odak yeni adımın içine taşındı', odak.adim === 'basvuran', String(odak.adim));

  /* Geri: doğrulama yapmadan geri dönülebilmeli. Kullanıcı yazdığını
     görmek için geri döner; geri dönmeyi zorlaştırmak cezalandırmaktır. */
  /* Bir adım geri: BAŞVURAN'dan TAM METİN'e; iki adım geri: ÇALIŞMA.
     Sayı elle yazılmaz, adım adına bakılır — araya bir adım daha
     girdiğinde sabit bir sayı sessizce yanlış yeri gösterir. */
  await s.click('#shGeri');
  await s.waitForTimeout(200);
  den('geri dönmek serbest', (await s.evaluate(GORUNUR))[0] === 'metin',
      (await s.evaluate(GORUNUR)).join(','));
  den('  yazılan metin duruyor',
      await s.evaluate(() => ((window.kdOku ? window.kdOku('bvMetin') : '') || '').length > 200));
  await s.click('#shGeri');
  await s.waitForTimeout(200);
  gor = await s.evaluate(GORUNUR);
  den('  ikinci geri çalışma adımına dönüyor', gor[0] === 'calisma', gor.join(','));
  den('  yazılan başlık duruyor',
      (await s.inputValue('#mBaslik')) === 'Sihirbaz sınaması için bir başlık');

  /* -----------------------------------------------------------------
     4. TASLAK: TARAYICIDA DURUYOR, SUNUCUYA GİTMİYOR
     ----------------------------------------------------------------- */
  console.log('\n== 4. Taslak ==');
  /* Künye alanları 3. bölümde zaten dolduruldu; taslak ölçümü onların
     da saklandığını görmelidir. */
  /* Geri dönülen adım 'calisma'; başvurana ulaşmak için TAM METİN
     adımı da geçilir (15 Ağustos 2026 kurul kararı). */
  await s.click('#shIleri'); await s.waitForTimeout(200);
  if ((await s.evaluate(GORUNUR))[0] === 'metin') {
    await metinDoldur(s);
    await s.click('#shIleri'); await s.waitForTimeout(250);
  }
  await s.fill('#ad', 'Dr. Deneme Yazar');
  await s.fill('#eposta', 'deneme@ornek.edu.tr');
  await s.waitForTimeout(900);            /* taslak yazımı geciktirilmiş */

  const taslak = await s.evaluate(() => localStorage.getItem('kutadgu-basvuru-taslak'));
  den('taslak tarayıcıda saklandı', !!taslak && taslak.length > 20, String(taslak && taslak.length));
  den('  taslakta girilen başlık var',
      !!taslak && taslak.indexOf('Sihirbaz sınaması') >= 0);
  den('  taslak SUNUCUYA gitmedi (yazarken hiçbir yazma isteği çıkmadı)',
      yazmaIstegi.length === 0, yazmaIstegi.join(' | ').slice(0, 120));

  /* Sayfa yenilendiğinde taslak geri gelmeli. */
  await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
  const surDugme = await s.$('#shTaslak button');
  den('yarım kalmış başvuru olduğu söyleniyor', !!surDugme);
  const taslakMetni = (await s.textContent('#shTaslak') || '').trim();
  olc('taslak kutusu: ' + taslakMetni.slice(0, 80));
  if (surDugme) {
    await surDugme.click();
    await s.waitForTimeout(400);
    den('  "kaldığım yerden sür" alanları geri getirdi',
        (await s.inputValue('#mBaslik')) === 'Sihirbaz sınaması için bir başlık');
    den('  İngilizce başlık da geri geldi',
        (await s.inputValue('#mBaslikEn')) === 'A title in English for the wizard test');
    den('  başvuran alanları da geri geldi',
        (await s.inputValue('#eposta')) === 'deneme@ornek.edu.tr');
    gor = await s.evaluate(GORUNUR);
    den('  kalınan adıma dönüldü', gor[0] === 'basvuran', gor.join(','));
  } else {
    den('  "kaldığım yerden sür" alanları geri getirdi', false, 'düğme yok');
    den('  İngilizce başlık da geri geldi', false, 'düğme yok');
    den('  başvuran alanları da geri geldi', false, 'düğme yok');
    den('  kalınan adıma dönüldü', false, 'düğme yok');
  }

  /* Taslak silinebilmeli: kullanıcının kendi tarayıcısındaki veriyi
     silmesi bir hak, gizli bir ayar değil. */
  await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
  const dugmeler = await s.$$('#shTaslak button');
  if (dugmeler.length >= 2) {
    await dugmeler[1].click();
    await s.waitForTimeout(200);
    den('taslak silinebiliyor',
        (await s.evaluate(() => localStorage.getItem('kutadgu-basvuru-taslak'))) === null);
  } else den('taslak silinebiliyor', false, 'silme düğmesi yok');

  /* -----------------------------------------------------------------
     5. UÇTAN UCA GÖNDERİM VE TASLAĞIN SİLİNMESİ
     -----------------------------------------------------------------
     Burada bulunan gerçek kusur: kullanıcı telif kutusunu işaretleyip
     HEMEN gönder'e basınca, kutunun tetiklediği 600 ms gecikmeli
     taslak yazımı gönderimden SONRA çalışıyor ve silinen taslağı geri
     yazıyordu. Başvurusu alınmış birinin tarayıcısında "yarım kalmış
     başvurunuz var" diye bir hayalet kalıyordu. Bu yüzden ölçüm
     bilerek EN KÖTÜ ZAMANLAMAYLA yapılır: son kutu işaretlenir
     işaretlenmez gönderilir.
     ----------------------------------------------------------------- */
  console.log('\n== 5. Uçtan uca gönderim ==');
  /* GÖNDERİM ARTIK HESAP İSTER (kurul kararı, 15 Ağustos 2026): kaydın
     bir sahibi olmalı. Uçtan uca ölçüm bu yüzden önce giriş yapar —
     kullanıcı da yapıyor. Giriş yapılmazsa uç 401 döner ve kapı,
     ölçmek istediği akışın sonuna hiç ulaşamaz. */
  await olcumGirisi(s, KOK);
  await s.goto(KOK + YOL, { waitUntil: 'networkidle' });
  await s.evaluate(() => localStorage.removeItem('kutadgu-basvuru-taslak'));
  await s.reload({ waitUntil: 'networkidle' });
  den('girişliyken hesap kapısı kutusu çizilmiyor',
      await s.evaluate(() => !document.getElementById('bvKapi')));
  /* Birinci adım koşullardır: okunup geçilir (beyan son adımda). */
  await s.click('#shIleri'); await s.waitForTimeout(150);
  await s.fill('#mBaslik', 'Uçtan uca sihirbaz denemesi');
  await kunyeDoldur(s, 'Wizard end to end test', false);
  await s.evaluate(() => window.alSecYaz && window.alSecYaz('alanlar', ['5.2']));
  await s.click('#shIleri'); await s.waitForTimeout(200);   /* metin */
  await metinDoldur(s);
  await s.click('#shIleri'); await s.waitForTimeout(200);   /* basvuran */
  /* Unvan artık DÜZ METİN değil, SEÇİM kutusudur: aynı unvanın altı
     ayrı yazımını üretmemek için değiştirildi. Ölçüm de öyle davranır;
     fill() bir <select> üzerinde çalışmaz ve bu bir kusur değil, alanın
     türünün değişmesidir. Değer görünen addır ("Dr."), anahtar değil —
     künyede okunacak olan şey addır. */
  await s.selectOption('#unvan', 'Dr.'); await s.fill('#ad', 'Dr. Uçtan Uca');
  await s.fill('#eposta', 'uctan@ornek.edu.tr'); await s.fill('#orcid', '0000-0002-1825-0097');
  await s.click('#shIleri'); await s.waitForTimeout(150);   /* ortak */
  /* YAZAR LİSTESİNİN TAM OLDUĞU BEYANI (kurul kararı, 15 Ağustos
     2026): eksik bırakılan yazar, kendisine gidecek bildirimi hiç
     almaz. Ortak yazar eklenmediği için "tek yazar benim". */
  await s.check('input[name=yazar_tam][value=tek]');
  /* BENZERLİK ADIMI ARTIK KOŞULLU. 15 Ağustos 2026 kurul kararı:
     "benzerlik kısmını kaldır." Şart kapalıyken adım hiç üretilmiyor;
     alanlarını doldurmaya çalışmak zaman aşımıyla düşer ve bu bir kusur
     değil, kaldırılmış bir adımın yokluğudur. Adım varsa doldurulur,
     yoksa atlanır — akışın geri kalanı değişmez. */
  if (await s.$('#intArac')) {
    await s.click('#shIleri'); await s.waitForTimeout(120);   /* benzerlik */
    await s.selectOption('#intArac', 'iThenticate'); await s.fill('#intOran', '8.2');
    await s.fill('#intTek', '2.1'); await s.fill('#intLink', 'https://ornek.edu.tr/r.pdf');
  }
  await s.click('#shIleri'); await s.waitForTimeout(120);   /* etik */
  /* ETİK BEYANI: ÜÇ METİN, DOSYA DEĞİL (kurul kararı, 15 Ağustos 2026).
     "Gereklidir" seçen kişiden kurul adı, karar tarihi ve karar
     numarası istenir. Önce istendiği ölçülür — istenmezse sistem
     yayımladığı beyanı boş yayımlıyor demektir. */
  await s.check('input[name=etik_durum][value=gerekli]');
  await s.waitForTimeout(150);
  den('"gereklidir" seçilince kurul/tarih/numara alanları açılıyor',
      await s.isVisible('#etikKurul'));
  await s.click('#shIleri'); await s.waitForTimeout(200);
  const etikGor = await s.evaluate(GORUNUR);
  den('  üçü boşken ilerletmiyor', etikGor[0] === 'etik', etikGor.join(','));
  den('  hata etik kurul alanının yanında',
      await s.evaluate(() => {
        const e = document.getElementById('etikKurul');
        return !!e && e.getAttribute('aria-invalid') === 'true';
      }));
  den('  belge YÜKLEME istenmiyor (dosya alanı yok)',
      await s.evaluate(() => !document.querySelector('#etikAlan input[type=file]')));
  await s.check('input[name=etik_durum][value=gereksiz]');
  await s.click('#shIleri'); await s.waitForTimeout(120);   /* veri */
  await s.check('input[name=veri_beyan][value=yok]');
  await s.click('#shIleri'); await s.waitForTimeout(120);   /* yz */
  /* YAPAY ZEKÂ BEYANI. 14 Ağustos 2026: "Kullanılmadı" seçilince kapsam
     bölmesi (#yzKapsam) ve içindeki etik onayı GİZLENİYOR; yerine
     #yzYokNot notu çıkıyor. Bildirilen kusur tam buydu: "kullanılmadı"
     diyen kişi, görmediği bir kutuyu işaretlemediği için ilerleyemiyordu.
     Kapı artık kutuyu YALNIZCA görünürse işaretler ve gizlenmiş
     olmasının kendisini de ölçer — gizlenmezse kusur geri gelmiş
     demektir. */
  await s.check('input[name=yz_kullanim][value=yok]');
  /* Beyan adımı genişledi: çıkar çatışması, başka yerde
     değerlendirilmeme ve fon da burada (kurul kararı, 15 Ağustos). */
  await s.check('input[name=cikar_catismasi][value=yok]');
  await s.check('#tekGonderim');
  await s.check('input[name=fon_durum][value=yok]');
  await s.waitForTimeout(150);
  den('"kullanılmadı" seçilince etik onayı kutusu ortadan kalkıyor',
      !(await s.isVisible('#yzEtik')));
  den('  yerine neden istenmediğini söyleyen not çıkıyor',
      await s.isVisible('#yzYokNot'));
  await s.click('#shIleri'); await s.waitForTimeout(120);   /* sekil */
  await s.click('#shIleri'); await s.waitForTimeout(120);   /* hakem */
  await s.click('#shIleri'); await s.waitForTimeout(120);   /* telif */
  /* GÖZDEN GEÇİR VE GÖNDER: özet BURADA ölçülür, 7.b'de değil.
     7.b'de ölçmek olanaksızdı — son adıma varmak ya formu baştan
     doldurmayı ya da taslak kutusunu yeniden açan bir sayfa isteğini
     gerektiriyordu; ölçüm oraya takılıyordu. Bu bölüm zaten sihirbazın
     tamamını yürüdüğü için özet doğal olarak burada okunur.

     Ölçülen kusur sınıfı: "sistem, uygulamadığı bir kuralı duyuruyor"un
     tersi — sistem, ALDIĞI bilgiyi göndermeden önce kişiye göstermiyor
     olabilir. ScholarOne'da bu adım var; burada da olmalı. Özet metnin
     kendisini değil ÖLÇÜSÜNÜ yazar (bir makaleyi özete dökmek özet
     olmaz). */
  const ozet = await s.evaluate(() => {
    const bl = [...document.querySelectorAll('.bv-oz-blok')];
    const bul = (re) => {
      const b = bl.find(x => x.querySelector('b') && re.test(x.querySelector('b').textContent));
      return b ? b.textContent.replace(/\s+/g, ' ') : '';
    };
    const eks = document.getElementById('shEksik');
    return {
      say: bl.length,
      eksik: !!eks && !eks.hidden,
      metin: bul(/Tam metin|full text/i),
      calisma: bul(/Çalışma|The work/i),
      beyan: bul(/Beyan|Declaration/i)
    };
  });
  olc('özet bloğu sayısı: ' + ozet.say);
  olc('özetteki tam metin satırı: ' + ozet.metin.slice(0, 90));
  den('son adımda gözden geçirme özeti çiziliyor', ozet.say >= 6, String(ozet.say));
  den('  her adım tamamken eksik listesi kapalı', !ozet.eksik);
  den('  özet, girilen metni ÖLÇÜSÜYLE gösteriyor',
      /kelime|words/i.test(ozet.metin), ozet.metin.slice(0, 70));
  den('  başlık özette görünüyor',
      /Uçtan uca/i.test(ozet.calisma), ozet.calisma.slice(0, 70));
  den('  yeni beyanlar özette görünüyor',
      /Çıkar|Conflict/i.test(ozet.beyan) && /[Ff]on|[Ff]unding/i.test(ozet.beyan),
      ozet.beyan.slice(0, 90));
  /* Son adımda İKİ beyan var: koşullar okundu ve lisans izni. */
  await s.check('#kosulOk');
  await s.check('#telif');
  await s.click('#gonder');                                  /* beklemeden: en kötü zamanlama */
  await s.waitForTimeout(1500);
  den('gönderim tamamlandı ve "alındı" kutusu açıldı',
      await s.evaluate(() => !document.getElementById('bitti').classList.contains('gizli')));
  den('  şerit ve gezinme kapandı (form bitti)',
      await s.evaluate(() => document.getElementById('shSerit').hidden && document.getElementById('shGez').hidden));
  /* Gecikmeli yazımın geri gelmesi için gereğinden uzun beklenir. */
  await s.waitForTimeout(1200);
  den('  TASLAK SİLİNDİ ve geri yazılmadı',
      (await s.evaluate(() => localStorage.getItem('kutadgu-basvuru-taslak'))) === null,
      String(await s.evaluate(() => localStorage.getItem('kutadgu-basvuru-taslak'))).slice(0, 40));

  /* -----------------------------------------------------------------
     6. DOĞRUDAN ADIMA GİTME
     ----------------------------------------------------------------- */
  console.log('\n== 6. Doğrudan adıma gitme ==');
  /* ADIM ADLARI SAYFADAN OKUNUR, KAPIYA YAZILMAZ. Bu bölüm önce
     "beşinci adım etik olmalı" diye yazılmıştı; listenin başına bir
     adım eklenince beşi de kaydı ve dördü birden KALDI verdi — oysa
     ölçülen şey adımın ADI değil, "n numaralı bağ n numaralı adımı
     açıyor mu" sorusudur. Sıra tek kaynaktan (tg_basvuru_adimlari)
     gelir; kapı da onu sayfadan okur. */
  const SIRA = await s.evaluate(() =>
    [...document.querySelectorAll('[data-adim]')].map(e => e.getAttribute('data-adim')));
  olc('adım sırası: ' + SIRA.join(' > '));
  await s.goto(KOK + '/basvuru.php?lang=tr&adim=5', { waitUntil: 'networkidle' });
  gor = await s.evaluate(GORUNUR);
  den('?adim=5 doğrudan beşinci adımı açıyor', gor[0] === SIRA[4], gor.join(',') + ' / ' + SIRA[4]);
  await s.goto(KOK + '/basvuru.php?lang=tr&adim=99', { waitUntil: 'networkidle' });
  gor = await s.evaluate(GORUNUR);
  den('sınır dışı adım birinciye düşüyor', gor[0] === SIRA[0], gor.join(','));

  /* Şeritten geri dönmek serbest, ileri atlamak doğrulamaya bağlı. */
  await s.goto(KOK + '/basvuru.php?lang=tr&adim=3', { waitUntil: 'networkidle' });
  await s.click('#shSerit li[data-serit="1"] a');
  await s.waitForTimeout(200);
  gor = await s.evaluate(GORUNUR);
  den('şeritten geriye tıklamak çalışıyor', gor[0] === SIRA[0], gor.join(','));
  await s.click('#shSerit li[data-serit="6"] a');
  await s.waitForTimeout(200);
  gor = await s.evaluate(GORUNUR);
  /* İleriye atlarken kapı, GEÇEMEDİĞİ İLK adımda durur. Koşul adımı
     artık okuma adımı olduğu için geçilir ve duraklama bir sonrakinde,
     başlığı boş olan ÇALIŞMA adımında olur. */
  den('şeritten ileriye atlamak eksik alanda durduruluyor',
      gor[0] === SIRA[1], gor.join(',') + ' / ' + SIRA[1]);

  /* -----------------------------------------------------------------
     6. BETİK YOKKEN SAYFA TEK UZUN FORM
     ----------------------------------------------------------------- */
  console.log('\n== 7. Betik kapalıyken ==');
  const bBaglam = await tarayici.newContext({ viewport: { width: 1440, height: 900 }, javaScriptEnabled: false });
  const bs = await bBaglam.newPage();
  await bs.goto(KOK + YOL, { waitUntil: 'domcontentloaded' });
  const gorB = await bs.evaluate(GORUNUR);
  den('betik yokken BÜTÜN adımlar açık', gorB.length === toplam, gorB.length + '/' + toplam);
  den('  gönder düğmesi görünür',
      await bs.evaluate(() => { const g = document.getElementById('gonder'); return !!g && !g.hidden; }));
  den('  ileri/geri düğmeleri gizli (betiksiz iş görmezler)',
      await bs.evaluate(() => { const g = document.getElementById('shGez'); return !!g && g.hidden; }));
  await bBaglam.close();

  /* -----------------------------------------------------------------
     7.a GÖZDEN GEÇİR VE GÖNDER
     -----------------------------------------------------------------
     ScholarOne'ın altıncı adımından alınan fikir (15 Ağustos 2026).
     Ölçülen iki şey:
       1. EKSİKLERİN TAMAMI bir kerede listeleniyor mu. Kutadgu ilk
          eksikte durup yalnız onu söylüyordu; on bir adımlık bir formda
          bu, kişiyi adım adım geri gönderir.
       2. Özet FORMDAN okunuyor mu — yani girilen değer özette görünüyor
          mu. Görünmüyorsa "gözden geçir" diye bir şey yoktur; kişi
          göremediği bir şeyi onaylamış olur.
     ----------------------------------------------------------------- */
  console.log('\n== 7.a Gözden geçir ve gönder ==');
  {
    const ob = await tarayici.newContext({ viewport: { width: 1280, height: 900 } });
    const op = await ob.newPage();
    /* BOŞ FORMLA: bütün eksikler görünmeli. */
    await op.goto(KOK + '/basvuru.php?lang=tr&adim=11', { waitUntil: 'networkidle' });
    await op.waitForTimeout(900);
    const bos = await op.evaluate(() => ({
      acik: !document.getElementById('shEksik').hidden,
      say: document.querySelectorAll('#shEksik li').length,
      adimlar: [...document.querySelectorAll('#shEksik li button')].map(b => b.getAttribute('data-oz-git')),
    }));
    olc('boş formda eksik sayısı: ' + bos.say + ' (' + bos.adimlar.join(',') + ')');
    den('boş formda eksik listesi açılıyor', bos.acik);
    den('  eksik BİR TANE değil, hepsi sayılıyor', bos.say >= 4, String(bos.say));
    den('  her eksik kendi adımına götüren düğme taşıyor',
        bos.adimlar.length === bos.say && bos.adimlar.every(x => !!x), bos.adimlar.join(','));
    /* Düğme gerçekten o adımı açıyor mu. */
    if (bos.say) {
      const hedef = bos.adimlar[0];
      await op.click('#shEksik li button');
      await op.waitForTimeout(400);
      den('  düğme eksiğin adımını açıyor',
          (await op.evaluate(GORUNUR))[0] === hedef, hedef);
    }
    await ob.close();
  }

  /* -----------------------------------------------------------------
     7.b ŞEKİL: GÖMÜLÜ GÖRSEL DOSYAYA ÇEVRİLİYOR MU
     -----------------------------------------------------------------
     Kurul kararı, 15 Ağustos 2026: "yazdığı metni şekillendirebilmesi
     lazım; şekillerin ekleneceği yer yok."

     ÖLÇÜLEN KUSUR: düzenleyici görseli data: adresiyle metnin içine
     gömüyordu; sunucudaki süzgeç (guvenli_html) img/src'de data:
     kabul etmez ve görsel SESSİZCE kayboluyordu. Artık görsel
     yükleniyor ve metne /photo/... adresiyle giriyor.

     Bu ölçüm data: adresli bir görseli metne koyar ve adresin
     DEĞİŞTİĞİNİ bekler. Değişmezse kusur geri gelmiş demektir:
     yazar şekli ekler, kaydeder ve şekil yok olur.
     ----------------------------------------------------------------- */
  console.log('\n== 7.b Şekil: gömülü görsel dosyaya çevriliyor ==');
  {
    const PNG = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAHElEQVQoz2NgYGD4z0AswK4KWQXJqggaR7oiAF+VBAWZ8xNvAAAAAElFTkSuQmCC';
    const gb = await tarayici.newContext({ viewport: { width: 1280, height: 900 } });
    const gp = await gb.newPage();
    await gp.goto(KOK + '/panel.php?lang=tr', { waitUntil: 'networkidle' });
    await gp.fill('#gKim', 'olcumbas'); await gp.fill('#gParola', 'olcum1234');
    await gp.click('#dgGiris'); await gp.waitForTimeout(1500);
    await gp.goto(KOK + '/basvuru.php?lang=tr&adim=3', { waitUntil: 'networkidle' });
    await gp.waitForTimeout(600);
    den('tam metin adımı düzenleyiciyle açılıyor',
        await gp.evaluate(() => !!document.querySelector('#f-metin .jodit-toolbar__box')));
    await gp.evaluate((png) => {
      window.kdAyarla('bvMetin', '<h2>Giris</h2><p>metin</p><p><img src="' + png + '" alt="sekil"></p>');
    }, PNG);
    await gp.waitForTimeout(2500);
    const gs = await gp.evaluate(() => {
      const v = window.kdOku('bvMetin') || '';
      const m = v.match(/<img[^>]*src="([^"]+)"/);
      const not = document.querySelector('[data-kd-yapi="bvMetin"] [data-gorsel-not]');
      return { src: m ? m[1] : null, not: not ? not.textContent : '' };
    });
    olc('görselin son adresi: ' + String(gs.src).slice(0, 50));
    den('  gömülü görsel SUNUCUYA yüklendi (data: değil)',
        !!gs.src && gs.src.indexOf('data:') !== 0, String(gs.src).slice(0, 40));
    den('  adres süzgecin kabul ettiği biçimde (/photo/...)',
        !!gs.src && gs.src.indexOf('/photo/') === 0, String(gs.src).slice(0, 40));
    den('  yazara ne olduğu söyleniyor', /y\u00fcklendi|uploaded/i.test(gs.not), gs.not);
    /* Görsel gerçekten sunulabiliyor mu: kırık bir adres, kaybolan
       görselden daha kötüdür — yazar onun durduğunu sanır. */
    const yanit = gs.src ? await gp.evaluate(async (u) => {
      const r = await fetch(u); return { k: r.status, t: r.headers.get('content-type') || '' };
    }, gs.src) : { k: 0, t: '' };
    den('  yüklenen görsel adresinden açılıyor', yanit.k === 200 && /image\//.test(yanit.t),
        yanit.k + ' ' + yanit.t);
    /* TASLAK SINAMASI DA BURADA: sayfa yenilenip taslak sürdürülüyor.
       Ölçülen kusur — taslak yüklenirken düzenleyici alanına doğrudan
       e.value yazılıyordu; gizli textarea doluyor, ekrandaki
       düzenleyici boş kalıyor ve ilk değişiklikte boş değer metnin
       üstüne yazılıyordu. Yani uzun bir metin yazıp sayfayı yenileyen
       kişi metnini kaybederdi.

       EŞİK SABİT SAYI DEĞİL, YENİLEMEDEN ÖNCEKİ UZUNLUKTUR: sabit bir
       sayı, fixture kısaldığında doğru kodu kusurlu bildirir. */
    await gp.waitForTimeout(1400);                 /* taslak yazımı geciktirilmiş */
    const oncekiBoy = await gp.evaluate(() => ((window.kdOku ? window.kdOku('bvMetin') : '') || '').length);
    await gp.reload({ waitUntil: 'networkidle' });
    await gp.waitForTimeout(500);
    den('  yenilemeden sonra taslak öneriliyor', await gp.isVisible('#shTaslak'));
    await gp.click('#shTaslak button');            /* kaldığım yerden sür */
    await gp.waitForTimeout(1200);
    const sonrakiBoy = await gp.evaluate(() => ((window.kdOku ? window.kdOku('bvMetin') : '') || '').length);
    den('  TAM METİN taslaktan olduğu gibi geri geldi',
        oncekiBoy > 0 && sonrakiBoy === oncekiBoy, oncekiBoy + ' -> ' + sonrakiBoy);
    await gb.close();
  }

  /* -----------------------------------------------------------------
     8. GİRİŞLİ KİŞİDE BAŞVURAN ALANLARI KENDİLİĞİNDEN DOLU

     Kurul kararı, 15 Ağustos 2026: "kullanıcı olarak girdiğime göre
     unvanım adım soyadım orcidim ve diğer bilgiler otomatik gelmeli ama
     hiç kaydı olmayan kişi de sisteme kayıt olup yazısını gönderebilsin;
     yoksa ikilik çıkıyor."

     Üç şey ölçülür:
       - girişli kişide alanlar DOLU geliyor mu,
       - doldurma SUNUCUDA mı yapılıyor (betiksiz tarayıcıda da dolu
         olmalı; betikle doldurmak, aynı kişinin betiksiz tarayıcıda
         boş form bulması demekti),
       - alanlar KİLİTLİ DEĞİL mi (hesaptaki bilgi bir başlangıçtır) ve
         girişsiz kişiye hesapsız da gönderebileceği söyleniyor mu.

     Ölçüm hesabı sinama/veri-kur.php'den gelir.
     ----------------------------------------------------------------- */
  console.log('\n== 8. Girişli kişide başvuran alanları ==');
  const gBaglam = await tarayici.newContext({ viewport: { width: 1440, height: 900 } });
  const g = await gBaglam.newPage();
  await g.goto(KOK + '/panel.php?lang=tr', { waitUntil: 'networkidle' });
  await g.fill('#gKim', 'olcumbas');
  await g.fill('#gParola', 'olcum1234');
  await g.click('#dgGiris');
  await g.waitForTimeout(1500);
  const girdi = await g.evaluate(() => {
    const e = document.getElementById('gKim');
    return !e || !e.offsetParent;
  });
  den('ölçüm hesabıyla giriş yapıldı', girdi);
  if (girdi) {
    const durum = await gBaglam.storageState();
    const nBaglam = await tarayici.newContext({ storageState: durum, javaScriptEnabled: false });
    const np = await nBaglam.newPage();
    await np.goto(KOK + YOL, { waitUntil: 'domcontentloaded' });
    const dolu = await np.evaluate(() => ({
      ad:     (document.getElementById('ad')     || {}).value,
      eposta: (document.getElementById('eposta') || {}).value,
      kurum:  (document.getElementById('kurum')  || {}).value,
      unvan:  (document.getElementById('unvan')  || {}).value,
      kilit:  ['ad', 'eposta', 'kurum', 'orcid'].filter(i => {
                const e = document.getElementById(i);
                return e && (e.readOnly || e.disabled);
              }),
      not:    [...document.querySelectorAll('#f-basvuran .ipucu')].map(e => e.textContent).join(' '),
    }));
    olc('dolu gelen: ' + JSON.stringify({ ad: dolu.ad, eposta: dolu.eposta, kurum: dolu.kurum, unvan: dolu.unvan }));
    den('  ad kendiliğinden geldi', String(dolu.ad || '').length > 2, String(dolu.ad));
    den('  e-posta kendiliğinden geldi', /@/.test(String(dolu.eposta || '')), String(dolu.eposta));
    den('  kurum kendiliğinden geldi', String(dolu.kurum || '').length > 0, String(dolu.kurum));
    den('  unvan seçili geldi', String(dolu.unvan || '').length > 0, String(dolu.unvan));
    den('  BETİKSİZ de dolu (doldurma sunucuda yapılıyor)', String(dolu.ad || '').length > 2);
    den('  alanlar kilitli değil (düzeltilebilir)', dolu.kilit.length === 0, dolu.kilit.join(','));
    den('  bilginin nereden geldiği yazıyor', /hesab/i.test(dolu.not), dolu.not.slice(0, 80));
    await nBaglam.close();
  }
  await gBaglam.close();

  /* Girişsiz kişi: form boş gelir ve hesapsız gönderebileceği yazar. */
  const aBaglam = await tarayici.newContext({ javaScriptEnabled: false });
  const ap = await aBaglam.newPage();
  await ap.goto(KOK + YOL, { waitUntil: 'domcontentloaded' });
  const anot = await ap.evaluate(() =>
    [...document.querySelectorAll('#f-basvuran .ipucu')].map(e => e.textContent).join(' '));
  /* KURAL DEĞİŞTİ (15 Ağustos 2026): gönderim hesap ister. Girişsiz
     kişiye artık "hesapsız da gönderebilirsin" denmiyor; hesabını
     açması ve yazdıklarının kaybolmayacağı söyleniyor. */
  den('girişsiz kişiye hesap gerektiği söyleniyor',
      await ap.evaluate(() => {
        const k = document.getElementById('bvKapi');
        return !!k && /hesap|account/i.test(k.textContent);
      }));
  den('  yazdıklarının kaybolmayacağı da söyleniyor',
      await ap.evaluate(() => {
        const k = document.getElementById('bvKapi');
        return !!k && /(tarayıcıda saklanır|kept in this browser)/i.test(k.textContent);
      }));
  den('  hesap sayfasına bağ var ve forma dönüşü taşıyor',
      await ap.evaluate(() => {
        const a = document.querySelector('#bvKapi a[href*="panel.php"]');
        return !!a && /donus=/.test(a.getAttribute('href') || '');
      }));
  den('  boş form gerçekten boş geliyor',
      (await ap.inputValue('#ad')) === '', await ap.inputValue('#ad'));
  await aBaglam.close();

  await tarayici.close();
  console.log('\n----------------------------------------');
  console.log('GECTI: ' + gecti + '   KALDI: ' + kaldi);
  process.exit(kaldi > 0 ? 1 : 0);
})();
