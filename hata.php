<?php
/* =====================================================================
   KUTADGU - Hata sayfası / Error page
   403, 404 ve 500 için tek sayfa. İki dilli.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$k = isset($_GET['k']) ? (int)$_GET['k'] : 404;
if (!in_array($k, [403, 404, 500], true)) $k = 404;
http_response_code($k);

$metin = [
    403 => [
        'bas' => ['Bu sayfaya erişilemiyor', 'This page cannot be reached'],
        'ack' => [
            'İstediğiniz adres korumalı ya da doğrudan açılamaz. Yanlış bir bağlantıya tıkladıysanız aşağıdan arşive dönebilirsiniz.',
            'The address you asked for is protected or cannot be opened directly. If you followed a broken link, you can return to the archive below.',
        ],
    ],
    404 => [
        'bas' => ['Böyle bir sayfa yok', 'No such page'],
        'ack' => [
            'Adres değişmiş ya da çalışma kaldırılmış olabilir. Aradığınız çalışmayı arşivde arayabilirsiniz.',
            'The address may have changed or the work may have been removed. You can search for the work in the archive.',
        ],
    ],
    500 => [
        'bas' => ['Beklenmedik bir sorun oluştu', 'Something went wrong'],
        'ack' => [
            'Sunucu isteği tamamlayamadı. Kısa bir süre sonra yeniden deneyin; sorun sürerse bize bildirin.',
            'The server could not complete the request. Please try again shortly; if it persists, let us know.',
        ],
    ],
];
$m = $metin[$k];

k_bas([
    'olcu'   => 'okuma',
    'baslik' => (string)$k,
    /* Durum kodu tek başına duran büyük bir sayıdır; başlık değildir,
       bu yüzden h1 ile değil kendi kuralıyla yazılır. Düğmeler sayfanın
       tek eylemi olduğu için ortalanır. */
    'ek_bas' => '<meta name="robots" content="noindex">'
              . '<style>'
              . '.ht-kod{font-family:var(--mono);font-size:var(--y-8);font-weight:700;'
              . 'color:var(--kut);line-height:1;margin-bottom:var(--b-4)}'
              . '.ht-dg{justify-content:center;margin-top:var(--b-5)}'
              . '</style>',
]);
?>
<section class="bolum">
  <div class="kap kap-dar metin-orta">
    <div class="ht-kod"><?= $k ?></div>
    <h1><?= k_esc(k_c($m['bas'][0], $m['bas'][1])) ?></h1>
    <p class="metin-sonuk"><?= k_esc(k_c($m['ack'][0], $m['ack'][1])) ?></p>
    <div class="d-kume ht-dg">
      <a class="d d-vurgu" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Çalışmalar', 'Works') ?></a>
      <a class="d d-ikinci" href="<?= k_esc(k_bag('/')) ?>"><?= k_c('Ana sayfa', 'Home') ?></a>
    </div>
  </div>
</section>
<?php k_son(); ?>
