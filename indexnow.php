<?php
/* =====================================================================
   /<anahtar>.txt  --  IndexNow anahtar dosyası

   IndexNow'un tek doğrulaması şudur: bildirimi gönderen kişi siteye
   dosya koyabiliyor mu. Bunun için sitenin kökünde, adı anahtarın
   kendisi olan ve içinde yine anahtar yazan bir .txt dosyası aranır.

   DOSYA DEPOYA KONMAZ. Anahtar bir sırdır ve depo herkese açıktır;
   oraya konan bir anahtarla, depoyu klonlayan herkes bu site adına
   arama motoruna bildirim yapabilirdi. Anahtar veri dizinindeki
   yonetim-ayar.json'da durur ve dosya istendiği anda buradan üretilir.
   Böylece sunucuya elle dosya yüklemek de gerekmez: ayara yazılan
   anahtar aynı anda hem bildirimde hem doğrulamada kullanılır, yani
   ikisi ayrışamaz.

   KARŞILAŞTIRMA hash_equals ile yapılır. Sıradan bir === , eşleşmenin
   kaçıncı harfte bozulduğunu geçen süreden okunabilir kılar; anahtar
   harf harf tahmin edilebilirdi.

   Ayar boşsa 404 döner. "Anahtar yok" bir arıza değildir: bildirim de
   yapılmaz, doğrulama da istenmez (bkz. api/index.php, indexnow_bildir).
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/ortak.php';

$istenen = (string)($_GET['k'] ?? '');
/* tg_ayar DEĞİL tg_canli_ayar: anahtar depodaki ayar.php'de değil,
   veri dizinindeki yonetim-ayar.json'da durur ve oraya yalnız
   tg_canli_ayar bakar. İlk yazımda tg_ayar kullanıldı ve anahtar
   yazıldığı hâlde dosya 404 döndü; yani doğrulama, doğru anahtarla
   bile başarısız olacaktı. */
$anahtar = trim((string)tg_canli_ayar('indexnow_anahtar', ''));

if ($anahtar === '' || $istenen === '' || !hash_equals($anahtar, $istenen)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "404\n";
    exit;
}

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
echo $anahtar;
