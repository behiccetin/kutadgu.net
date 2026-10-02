<?php
/* =====================================================================
   KURUL OYLAMALARI · KALDIRILDI (19 Ağustos 2026)
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ: "burası öyle duruyor, bu hangi kurulun oylamaları o
   da belli değil; burayı kaldıralım, göstermeye gerek yok. Bir hakeme
   itirazda süren durumda editörlerin ya da diğer hakemlerin oylarıyla
   belli olacak ya, onu makalede göstermek daha mantıklı."

   Doğruydu ve iki ayrı kusuru birden söylüyordu:

   1. SAYFA BAĞLAMINDAN KOPUKTU. Bütün çalışmaların oylamalarını bir
      arada listeliyordu; oysa bir oylama her zaman BİR ÇALIŞMA
      hakkındadır. Karara konu olan metinden ayrı bir yerde duran bir
      karar listesi, okura "bu hangi kurul, ne oyluyor" sorusunu
      sorduruyordu ve yanıtı sayfada yoktu.

   2. ZATEN İKİ YERDE VARDI. Oylamanın kendisi çalışmanın sayfasında
      çiziliyor ve oy ORADA kullanılıyor (yazi.php, #oylama). Kişinin
      oyunu bekleyen işler ise panelin "Sizi bekleyen işler" kartında
      duruyor. Bu sayfa üçüncü bir kopyaydı ve hiçbir şey eklemiyordu.

   DOSYA SİLİNMEDİ, KAPANDIĞINI SÖYLÜYOR. Bir adres bir kez
   yayımlandıysa, ona gelen kişiye ne olduğunu söylemek gerekir; sessiz
   bir 404, adresi paylaşmış olan herkesin bağlantısını anlamsız kılar.
   Sayfa 410 (Gone) döner: arama motorlarına adresin kalıcı olarak
   kaldırıldığını söyleyen yanıt budur, 404 "belki döner" demektir.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

http_response_code(410);

$en = k_en();

k_bas([
    'olcu'     => 'okuma',
    'tur'      => 'belge',
    'baslik'   => k_c('Kurul oylamaları', 'Panel votes'),
    'yol'      => '/oylama.php',
    'aciklama' => k_c(
        'Bu sayfa kaldırıldı. Kurul oylamaları, hakkında açıldıkları çalışmanın kendi sayfasında görülür.',
        'This page has been removed. Panel votes are seen on the page of the work they concern.'
    ),
    'robots'   => 'noindex,nofollow',
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Kaldırıldı', 'Removed') ?></span>
      <h1><?= k_c('Kurul oylamaları', 'Panel votes') ?></h1>
      <p><?= k_c(
        'Bu sayfa bütün çalışmaların oylamalarını bir arada listeliyordu. Bir oylama her zaman bir çalışma hakkındadır; karara konu olan metinden ayrı duran bir karar listesi, okura hangi kurulun neyi oyladığını söyleyemiyordu. Oylama artık yalnızca hakkında açıldığı çalışmanın sayfasında görünür ve oy orada kullanılır.',
        'This page listed the votes on every work together. A vote always concerns one work; a list of decisions standing apart from the text they are about could not tell the reader which panel was voting on what. A vote now appears only on the page of the work it concerns, and the vote is cast there.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="kap">
  <div class="kutu">
    <p><?= k_c(
      '<b>Oyunuz bekleniyorsa panelinizde görürsünüz.</b> Panelin özet sekmesindeki “Sizi bekleyen işler” kartı, yazmanız gereken raporu, beklenen oyunuzu ve yarım kalmış doğrulamaları bir arada gösterir; her satır kendi çalışmasına götürür.',
      '<b>If your vote is awaited, you will see it in your panel.</b> The “Work waiting on you” card on the summary tab shows the report you owe, the vote awaited from you and any unfinished verification together; each row leads to its own work.'
    ) ?></p>
    <div class="d-kume" style="margin-top:var(--b-4)">
      <a class="d d-vurgu" href="<?= k_esc(k_bag('/panel.php')) ?>"><?= k_c('Panelime git', 'Go to my panel') ?></a>
      <a class="d d-ikinci" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Çalışmalar', 'Works') ?></a>
      <a class="d d-sessiz" href="<?= k_esc(k_bag('/hakemlik.php')) ?>"><?= k_c('Hakemlik süreci', 'Peer review process') ?></a>
    </div>
  </div>
</section>
<?php
k_son();
