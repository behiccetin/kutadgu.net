/* =====================================================================
   KUTADGU - Uygulama kabuğu ve çevrimdışı okuma / App shell and offline
   Duran varlıklar önbellekten, sayfalar ağdan gelir. Okunmuş bir çalışma
   ağ olmadığında da açılır: eşzamanlı çalışırken ağ, ağ yokken önbellek.
   API ve kişisel paneller hiçbir zaman önbelleğe alınmaz; yönetim de
   panelin içinde olduğu için aynı kuralın altındadır.
   ===================================================================== */
var SURUM   = 'kutadgu-v65';
var KABUK   = 'kutadgu-kabuk-v34';
var SAYFA   = 'kutadgu-sayfa-v17';
var SAYFA_UST = 60;                    /* çevrimdışı saklanacak en çok sayfa */

var VARLIK = ['/k/kutadgu.css', '/k/kutadgu.js', '/k/tamga.svg', '/k/desen.svg',
              '/k/yazitipi/kutadgu-serif-400.woff2', '/k/yazitipi/kutadgu-serif-700.woff2',
              '/manifest.webmanifest', '/cevrimdisi.html'];

self.addEventListener('install', function (e) {
  e.waitUntil(
    caches.open(KABUK)
      .then(function (c) { return Promise.all(VARLIK.map(function (u) { return c.add(u).catch(function () {}); })); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (e) {
  e.waitUntil(caches.keys().then(function (a) {
    return Promise.all(a.map(function (k) {
      return (k === KABUK || k === SAYFA) ? null : caches.delete(k);
    }));
  }).then(function () { return self.clients.claim(); }));
});

/* Önbellekteki sayfa sayısını sınırla: eski kayıtlar düşer */
function budaSayfa() {
  caches.open(SAYFA).then(function (c) {
    c.keys().then(function (k) {
      if (k.length <= SAYFA_UST) return;
      for (var i = 0; i < k.length - SAYFA_UST; i++) c.delete(k[i]);
    });
  });
}

/* Önbelleğe alınmaması gereken yollar: kişisel ve yönetimsel olan her şey */
function ozel(p) {
  return p.indexOf('/api/') === 0
      || p.indexOf('/error/') === 0
      || p.indexOf('/hakem.php') === 0
      || p.indexOf('/yazar.php') === 0
      || p.indexOf('/davet.php') === 0
      || p.indexOf('/panel.php') === 0
      /* Arama sonucu önbelleğe alınmaz: sorgu her seferinde arşivin o
         anki hâline sorulmalıdır. Eski bir sonuç sayfası, yeni gelmiş
         bir çalışmayı yokmuş gibi gösterirdi. */
      || p.indexOf('/ara.php') === 0
      || p.indexOf('/kefil.php') === 0
      /* Davetle hesap kurma sayfası: adresinde tek kullanımlık bir
         anahtar taşır, hiçbir koşulda önbelleğe alınmamalıdır. */
      || p.indexOf('/hesap-kur.php') === 0
      /* Arşiv dökümü: bağlantıya tıklamak da bir "navigate" isteğidir
         ve buradan yüzlerce megabaytlık bir dosya inebilir. Böyle bir
         dosyanın çevrimdışı önbelleğe kopyalanması kullanıcının diskini
         sessizce doldurur. Dosyaların kendi önbellek başlıkları
         (ETag, Last-Modified) zaten tarayıcının olağan önbelleğinde
         işini görür. */
      || p.indexOf('/dokum.php') === 0
      || p.indexOf('/arsiv.php') === 0;
}

self.addEventListener('fetch', function (e) {
  var istek = e.request;
  if (istek.method !== 'GET') return;

  var u;
  try { u = new URL(istek.url); } catch (x) { return; }
  if (u.origin !== self.location.origin) return;
  if (ozel(u.pathname)) return;

  /* Sayfalar: önce ağ, ağ yoksa en son okunan sürüm, o da yoksa çevrimdışı sayfası */
  if (istek.mode === 'navigate') {
    e.respondWith(
      fetch(istek).then(function (y) {
        if (y && y.status === 200 && y.type === 'basic') {
          var kop = y.clone();
          caches.open(SAYFA).then(function (c) { c.put(istek, kop); budaSayfa(); });
        }
        return y;
      }).catch(function () {
        return caches.match(istek).then(function (v) {
          return v || caches.match('/cevrimdisi.html');
        });
      })
    );
    return;
  }

  /* Duran varlıklar: önce önbellek, arkada tazele */
  if (/\.(css|js|mjs|svg|woff2?|png|jpg|jpeg|webp|avif|ico)$/i.test(u.pathname)) {
    e.respondWith(caches.match(istek).then(function (v) {
      var ag = fetch(istek).then(function (y) {
        if (y && y.status === 200 && y.type === 'basic') {
          var kop = y.clone();
          caches.open(KABUK).then(function (c) { c.put(istek, kop); });
        }
        return y;
      }).catch(function () { return v; });
      return v || ag;
    }));
  }
});

/* Sayfadan gelen istekle bir çalışmayı çevrimdışı okumak üzere saklama */
self.addEventListener('message', function (e) {
  var d = e.data || {};
  if (d.tur === 'sakla' && d.adres) {
    e.waitUntil(caches.open(SAYFA).then(function (c) {
      return c.add(d.adres).then(function () {
        budaSayfa();
        if (e.source) e.source.postMessage({ tur: 'saklandi', adres: d.adres });
      }).catch(function () {
        if (e.source) e.source.postMessage({ tur: 'saklanamadi', adres: d.adres });
      });
    }));
  }
  if (d.tur === 'temizle') {
    e.waitUntil(caches.delete(SAYFA).then(function () {
      if (e.source) e.source.postMessage({ tur: 'temizlendi' });
    }));
  }
});
