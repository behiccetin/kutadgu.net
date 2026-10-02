<?php
/* =====================================================================
   ARŞİVİN TAMAMININ DIŞA AKTARIMI (ESKİ ADRES)
   ---------------------------------------------------------------------
   Bu adres eskiden dökümü istek geldiği anda üretiyordu: her indirme
   bütün kayıtların okunmasını, süzülmesini ve yeniden kodlanmasını
   gerektiriyordu. Arşiv büyüdükçe bu, kötü niyet gerekmeden sistemi
   yavaşlatır; niyet varsa doğrudan bir saldırı yoludur.

   Üretim artık istekten ayrılmıştır. Döküm yayım anında ve her gece bir
   kez üretilip durağan dosya olarak konur; buradan yapılan şey yalnızca
   o dosyanın akıtılmasıdır. Ne üretildiği ve nasıl doğrulanacağı
   /dokum.php sayfasında yazılıdır.

   Adres kırılmadı ve kırılmayacak: bugüne kadar bu adresi kullanan
   betikler, yedekleme kabuğu ve aynalar aynı içeriği almayı sürdürür.

     /arsiv.php          katman b, bütün yıllar, tek dosya
     /arsiv.php?b=ozet   parmak izleri (küçük dosya)
     /arsiv.php?indir=1  aynısı, indirme başlığıyla

   İndirmenin hiçbir koşulu yoktur ve olamaz: kimlik, kayıt, üyelik,
   onay ya da bedel istenmez (Ek A Madde 2.5).
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/dokum.php';

$bicim = isset($_GET['b']) ? strtolower(preg_replace('/[^a-z]/', '', (string)$_GET['b'])) : 'json';
if (!in_array($bicim, ['json', 'ozet'], true)) $bicim = 'json';

$ad = $bicim === 'ozet' ? 'kutadgu-parmak-izleri.txt' : 'kutadgu-tam-tumu.json';

/* İlk kurulumda ya da veri dizini yeni açıldığında dosya henüz
   üretilmemiş olabilir. O durumda bir kez üretilir; bundan sonrası
   durağan dosyadan gelir. Bu, istek anında üretime dönüş değildir:
   yalnızca hiç dosya yokken çalışan bir başlangıç adımıdır. */
if (!is_file(dk_yol($ad))) dk_uret_hafif(true);

if (!is_file(dk_yol($ad))) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Retry-After: 300');
    exit("Dokum henuz uretilmedi. Bu bir reddetme degildir; biraz sonra hazir olur.\n"
       . "The dump has not been built yet. This is not a refusal; it will be ready shortly.\n");
}

/* Yol bazında tavan: aşılırsa gecikme bildirilir, erişim kapanmaz. */
$s = dk_hiz_sinir('arsiv-' . $bicim, $bicim === 'ozet' ? 60 : 20, 600);
if (!$s['gecer']) {
    http_response_code(429);
    header('Retry-After: ' . (int)$s['bekle']);
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
    exit("Cok sik istek. Bu bir reddetme degildir: " . (int)$s['bekle']
       . " saniye sonra ayni dosyayi kosulsuz indirebilirsiniz.\n"
       . "Too many requests. This is not a refusal: the same file may be downloaded\n"
       . "without any condition in " . (int)$s['bekle'] . " seconds.\n");
}

/* Eski davranış korunuyor: indir= verilmedikçe tarayıcıda açılabilsin. */
dk_gonder($ad, isset($_GET['indir']));
