<?php
/* =====================================================================
   KUTADGU - Tamga kimliklerini düzene sokma / Tamga migration
   ---------------------------------------------------------------------
   Kuruluş döneminde bazı çalışmalara "bc.000001" gibi, DOI'ye benzeyen
   geçici kodlar verilmişti. Bu betik onları asıl tamga biçimine geçirir:

       bc.000001  ->  KTG-2026-00001-4

   Eski kod SİLİNMEZ; çalışmanın "eski_kod" listesine yazılır. Böylece
   paylaşılmış "/tamga/bc.000001" ve "/10.00001/bc.000001" adresleri
   çalışmayı sürdürür ve kalıcı adrese 301 ile toplanır. Hiçbir bağlantı
   kırılmaz.

   Yalnızca komut satırından çalışır. Çalıştırmadan önce yazilar.json'un
   yedeğini kendisi alır.

   Kullanım (sunucuda). Aşağıdaki <KURULUM_DIZINI>, Kutadgu'nun kurulu
   olduğu klasörün yerine geçen bir yer tutucudur; kopyalayıp
   yapıştıran kendi yoluyla değiştirir. Gerçek sunucu yolu buraya
   yazılmaz: bu satır yalnızca belgedir, betiğin işleyişine girmez
   (veri dizinini ayar ya da KUTADGU_DATA belirler, aşağıya bakınız)
   ve gerçek yol depoda durursa yalnızca sunucuyu tanımak isteyene
   yol gösterir.

       cd <KURULUM_DIZINI>
       php kur-tamga.php            (ne yapacağını gösterir, dokunmaz)
       php kur-tamga.php uygula     (uygular)

   Bittiğinde bu dosya depodan kaldırılabilir; işi bir kezliktir.
   ===================================================================== */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Bu betik yalnızca komut satırından çalışır.\n");
}

require_once __DIR__ . '/ortak.php';

$uygula = in_array('uygula', array_slice($argv, 1), true);
$dizin  = tg_veri_dizini();
$dosya  = $dizin . '/yazilar.json';

if (!is_file($dosya)) exit("Bulunamadı: $dosya\n");

$ham = (string)file_get_contents($dosya);
$yazilar = json_decode($ham, true);
if (!is_array($yazilar)) exit("yazilar.json okunamadı ya da bozuk.\n");

echo "Kutadgu tamga düzenlemesi\n";
echo "Veri dosyası : $dosya\n";
echo "Çalışma      : " . count($yazilar) . "\n";
echo $uygula ? "Kip          : UYGULA\n\n" : "Kip          : deneme (hiçbir şey yazılmaz)\n\n";

$degisen = 0;

foreach ($yazilar as $i => $y) {
    if (!is_array($y)) continue;
    $kod = trim((string)($y['bcid'] ?? ''));
    $bas = trim((string)($y['baslik'] ?? ($y['baslik_tr'] ?? '')));
    if ($bas === '') $bas = (string)($y['id'] ?? '?');

    if ($kod !== '' && tg_tamga_gecerli($kod)) {
        echo "  = " . str_pad($kod, 20) . mb_substr($bas, 0, 48) . "\n";
        continue;
    }

    /* Yıl, çalışmanın kendi yayın tarihinden gelir; sıra o yıl içinde
       verilmiş en büyük numaranın bir fazlasıdır. Böylece numaralar
       yayın yılına göre anlamlı kalır. */
    $yil = preg_match('/^(\d{4})/', (string)($y['tarih'] ?? ''), $m) ? (int)$m[1] : (int)date('Y');
    $yeni = tg_sonraki_kod($yazilar, $yil);

    echo "  + " . str_pad(($kod !== '' ? $kod : '(kodsuz)'), 20) . mb_substr($bas, 0, 40) . "\n";
    echo "    -> " . $yeni . "\n";

    if ($kod !== '') {
        $eski = (array)($y['eski_kod'] ?? []);
        if (!in_array($kod, $eski, true)) $eski[] = $kod;
        $yazilar[$i]['eski_kod'] = array_values($eski);
    }
    $yazilar[$i]['bcid'] = $yeni;
    $degisen++;
}

echo "\nDeğişecek çalışma: $degisen\n";

if ($degisen === 0) { echo "Yapılacak bir şey yok.\n"; exit(0); }

if (!$uygula) {
    echo "\nUygulamak için: php kur-tamga.php uygula\n";
    exit(0);
}

/* Yedek: aynı dizine, saat damgasıyla */
$yedek = $dizin . '/yazilar-' . date('Ymd-His') . '.yedek.json';
if (file_put_contents($yedek, $ham) === false) exit("Yedek yazılamadı: $yedek\n");
echo "Yedek alındı: $yedek\n";

$yeniHam = json_encode($yazilar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($yeniHam === false) exit("JSON üretilemedi; hiçbir şey yazılmadı.\n");

/* Önce geçici dosyaya yaz, sonra yerine koy: yarım yazma olmaz */
$gecici = $dosya . '.tmp';
if (file_put_contents($gecici, $yeniHam) === false) exit("Yazılamadı: $gecici\n");
if (!rename($gecici, $dosya)) { @unlink($gecici); exit("Yerine konulamadı: $dosya\n"); }

echo "Tamam. $degisen çalışmanın tamgası düzenlendi.\n";
echo "Eski kodlar 'eski_kod' altında duruyor; eski adresler kalıcı adrese yönlenmeyi sürdürür.\n";
