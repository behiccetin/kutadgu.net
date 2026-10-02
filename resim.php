<?php
/* =====================================================================
   KUTADGU - Profil resmi sunucusu / Profile image server
   ---------------------------------------------------------------------
   Resimler veri dizininde durur, depoda değil. Sebebi şudur: depo her
   gönderimde yerine konur; oraya yazılan bir dosya bir sonraki
   gönderimde silinirdi. Veri dizini ise sistemin dışındadır ve kalır.

   Dosya adı, resmin kendi içeriğinin özetidir. Bu yüzden bir resim
   değiştiğinde adresi de değişir ve tarayıcı eski resmi göstermez;
   değişmediği sürece de bir yıl boyunca yeniden indirilmez.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/ortak.php';

$r = (string)($_GET['r'] ?? '');
/* Dosya adı yalnızca özet ve uzantı olabilir: dizin gezinmesi imkânsızdır. */
if (!preg_match('/^[a-f0-9]{16,64}\.(webp|jpg|png)$/', $r, $m)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Bulunamadı\n");
}
$yol = tg_resim_dizini() . '/' . $r;
if (!is_file($yol)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit("Bulunamadı\n");
}
$tur = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'][$m[1]] ?? 'application/octet-stream';

header('Content-Type: ' . $tur);
header('Content-Length: ' . (string)filesize($yol));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($yol);
