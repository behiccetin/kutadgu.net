<?php
/* =====================================================================
   /license.xml  --  RSL 1.0 (Really Simple Licensing)

   NE İŞE YARAR
   ------------
   Yapay zekâ eğiten kuruluşlar internetteki metni topluyor ve
   ürettikleri çıktıda çoğu zaman kaynağı anmıyor. Bu dosya, Kutadgu'nun
   koşulunu MAKİNE OKUNUR biçimde, tek ve standart bir yerden söyler:

     kullan, eğit, çıkarımda kullan -- ama ANDIĞINDA tamgayı ve
     bağlantıyı ver.

   NEDEN "ATTRIBUTION", NEDEN PARA DEĞİL
   -------------------------------------
   RSL'de ödeme türleri arasında purchase, subscription, crawl,
   inference gibi ücretli seçenekler de var. Kutadgu bunların hiçbirini
   kullanamaz: BİRİNCİ DEĞİŞMEZ İLKE ücret yasağıdır ve oylanarak
   değiştirilemez. Buraya bir ücret yazmak, sistemin okurdan almadığı
   parayı makineden almaya kalkması olurdu; erişimin koşulsuzluğu
   yalnız insan için geçerli bir söz değildir.

   Geriye kalan tek koşul kredidir ve RSL'de karşılığı aynen
   payment type="attribution"tır. Ölçütü de uydurmuyoruz: CC BY 4.0'ın
   kendisini gösteriyoruz, çünkü atıf yükümlülüğü zaten o lisanstan
   doğuyor. Bu dosya yeni bir hak yaratmaz, var olan hakkı makinenin
   okuyabileceği yere yazar.

   NE YAPMIYORUZ
   -------------
   Hiçbir kullanımı yasaklamıyoruz (prohibits yok). Kutadgu'nun
   metinleri açık erişimlidir ve eğitimi yasaklamak, "herkes okuyabilir
   ama makine okuyamaz" demek olurdu; bu da ikinci ilkenin daralması
   olurdu. İstenen engel değil, addır.

   Yönerge robots.txt içinde durur:  License: <?= '' ?>
   ===================================================================== */
declare(strict_types=1);
require_once __DIR__ . '/ortak.php';

$kok   = rtrim(tg_kok(), '/');
$lis   = (string)tg_ayar('lisans_url', 'https://creativecommons.org/licenses/by/4.0/');
$marka = (string)tg_ayar('marka', 'Kutadgu');

header('Content-Type: application/rsl+xml; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rsl xmlns="https://rslstandard.org/rsl">
  <!-- <?= htmlspecialchars($marka, ENT_QUOTES, 'UTF-8') ?> - açık erişimli akademik yayın sistemi.
       Koşul tek: kullan, ama an. Ücret yoktur ve istenemez. -->
  <content url="<?= htmlspecialchars($kok . '/', ENT_QUOTES, 'UTF-8') ?>">
    <license>
      <permits type="usage">all</permits>
      <permits type="usage">ai-train</permits>
      <permits type="usage">ai-input</permits>
      <permits type="usage">search</permits>
      <payment type="attribution">
        <standard><?= htmlspecialchars($lis, ENT_QUOTES, 'UTF-8') ?></standard>
      </payment>
    </license>
  </content>
</rsl>
