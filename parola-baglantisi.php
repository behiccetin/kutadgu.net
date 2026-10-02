<?php
/* =====================================================================
   KUTADGU · PAROLA YENİLEME BAĞLANTISI ÜRETİCİSİ
   YALNIZCA KOMUT SATIRINDAN ÇALIŞIR.
   ---------------------------------------------------------------------
   BUNUN NEDEN VAR OLMASI GEREKTİ

   Parola yenileme bağlantısı e-postayla gider. Posta yolu kurulu
   değilse ileti hiç çıkmaz ve hesabına giremeyen kişi KİLİTLİ kalır.
   Bugün tam olarak bu oldu: yönetici kendi hesabına giremedi.

   Yenileme jetonu hiçbir yerde SAKLANMAZ; kayda yalnızca sha256 özeti
   girer (bkz. hs_yenileme_uret). Bu doğru bir tasarımdır ve
   değiştirilmedi — ama sonucu şudur: gönderilememiş bir bağlantı geri
   getirilemez. Getirilebilseydi, veri dizinini okuyan herkes her hesaba
   girebilirdi.

   Yapılabilecek tek doğru şey: sunucuda YENİ bir bağlantı üretmek.

   NEDEN BU BİR AÇIK DEĞİL

   Bu betiği çalıştırmak sunucuda kabuk erişimi ister. Kabuk erişimi
   olan biri zaten hesaplar dosyasını, ayarları ve bütün veriyi
   okuyabilir; ona yeni bir yetki verilmiyor. Buna karşılık üç kapı
   konuldu ve üçü de burada:

     1. YALNIZCA CLI. Web üzerinden çağrılırsa 404 verir ve hiçbir şey
        yapmaz. Bu satır silinirse bu dosya bir arka kapıya dönüşür;
        sinama/parola-kapi.php bunu her koşuda ölçer.
     2. ADRES ELLE VERİLİR. Argümansız çalıştırmak hiçbir hesap açmaz.
     3. İZ BIRAKIR. Üretilen her bağlantı sunucu kütüğüne düşer:
        ne zaman, hangi adres için. Sessizce kullanılamaz.

   Bağlantı BİR SAAT geçerlidir ve BİR KEZ kullanılır — e-postayla
   gidenle tamamen aynı jetondur, aynı uçtan çözülür.

   KULLANIM (sunucuda):
     php parola-baglantisi.php ad@ornek.org

   (Örnekteki adres bilerek uydurmadır: bu depo herkese açıktır ve bir
   gerçek adres, kullanım örneği kılığında da olsa kişisel veridir.
   sinama/sir-kapi.php bunu ölçüyor ve ilk yazımda YAKALADI.)

   Posta aktarıcısı kurulduktan sonra bu betiğe gerek kalmaz; yine de
   silinmez, çünkü aktarıcı bir gün yine düşebilir.
   ===================================================================== */
declare(strict_types=1);

/* ---- KAPI 1: YALNIZCA KOMUT SATIRI ----
   php_sapi_name() denetimi TEK BAŞINA yetmez: bazı kurulumlarda CGI
   yorumlayıcı 'cgi-fcgi' der ve o da web isteğidir. Bu yüzden ikinci
   ölçüt de aranır: bir web isteğinde $_SERVER['REQUEST_METHOD'] her
   zaman doludur, komut satırında hiç yoktur. */
if (PHP_SAPI !== 'cli' || isset($_SERVER['REQUEST_METHOD'])) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Bulunamadi.\n";
    exit(1);
}

require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/k/hesap.php';

$eposta = trim((string)($argv[1] ?? ''));

if ($eposta === '' || !filter_var($eposta, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR,
        "Kullanim: php parola-baglantisi.php <eposta>\n\n"
      . "Verilen adrese ait hesap icin YENI bir parola yenileme baglantisi uretir.\n"
      . "Baglanti bir saat gecerlidir ve bir kez kullanilir.\n");
    exit(2);
}

$h = hs_bul($eposta);
if (!is_array($h)) {
    /* Komut satırında sızıntı kaygısı yoktur: burayı çalıştıran kişi
       zaten hesap dosyasını okuyabilir. Sebebi açıkça söylemek,
       yöneticinin yanlış adres yazdığını anlamasını sağlar. */
    fwrite(STDERR, "Bu adreste bir hesap yok: " . $eposta . "\n");
    exit(3);
}

/* Parolası hiç kurulmamış bir hesap için bu yol doğru yol değildir:
   orada gereken şey DAVET bağlantısıdır. İkisini karıştırmak, hesabı
   olmayan birine parola yenileme yolu açmak olurdu. */
if (trim((string)($h['parola'] ?? '')) === '') {
    fwrite(STDERR,
        "Bu hesabin henuz bir parolasi yok; yenilenecek bir sey yok.\n"
      . "Gereken sey davet baglantisidir, panelden uretilir.\n");
    exit(4);
}

$jeton = hs_yenileme_uret($h, 1);
$bag   = rtrim(tg_kok(), '/') . '/hesap-kur.php?y=' . $jeton;

/* İZ: kim ürettiğini sunucu bilmez (kabuk kullanıcısıdır), ama NE ZAMAN
   ve KİMİN İÇİN üretildiği kütüğe düşer. */
error_log('kutadgu/parola-baglantisi: komut satirindan uretildi -> ' . $eposta);

echo "\n";
echo "Hesap    : " . hs_gorunen_ad($h) . "\n";
echo "Adres    : " . (string)$h['eposta'] . "\n";
echo "Gecerlik : 1 saat, tek kullanimlik\n";
echo "\n" . $bag . "\n\n";
echo "Bu baglantiyi kimseyle paylasmayin. Kullanildiginda ya da bir saat\n";
echo "sonra kendiliginden gecersiz olur.\n\n";
exit(0);
