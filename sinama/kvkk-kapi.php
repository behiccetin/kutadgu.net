<?php
/* =====================================================================
   ZİYARETÇİ ADRESİ: kapı ölçümü. Depoya girmez.

   BULUNAN ÜÇ KUSUR
   ----------------
   1. HAM ADRES ÜÇÜNCÜ TARAFA, ÜSTELİK DÜZ HTTP İLE GİDİYORDU.
      geo_al() her okuma kaydında ziyaretçinin IP'sini
      http://ip-api.com/json/<IP> adresine gönderiyordu. Şifresiz
      olduğu için aradaki her aktarıcı hangi adresin sorulduğunu
      görebilir; ip-api.com ise adresi doğrudan alır. Ziyaretçi bu
      isteğin varlığından habersizdir.

   2. HAM ADRES DİSKE YAZILIYORDU. geo-onbellek.json'un ANAHTARI ham
      IP'ydi ve 30 gün duruyordu. Oysa okuma kaydının kendi içinde şu
      yorum yazılı:

        "Ham adres YAZILMAZ. Yayın ilkeleri, ziyaretçi adresinin geri
         çevrilemeyecek biçimde özetlenerek saklandığını söyler; kayıt
         da onu söylemelidir. Toplanmayan veri sızdırılamaz."

      Kayıt dosyası adresi özetliyor ('iph'), yanındaki önbellek ise
      ham hâlde saklıyordu. Sistem okura bir şey söyleyip başka bir şey
      yapıyordu -- bu oturumda telif metninde ve gizli metin kararında
      düzelttiğimiz kusurun aynısı.

   3. GEREKSİZDİ. Site Cloudflare arkasında ve ülke bilgisi zaten
      CF-IPCOUNTRY başlığıyla, hiçbir yere hiçbir şey gönderilmeden
      geliyor. Dışarıya sorulan şey, elde zaten olan şeydi.

   VERİLEN KARAR
   -------------
   Şehir ve konum DÜŞÜRÜLDÜ. Ülke, Cloudflare başlığından alınır.
   Açık erişimli bir arşivin ziyaretçisinin hangi şehirde olduğunu
   bilmeye ihtiyacı yoktur; o kesinliği elde etmenin tek yolu adresi
   dışarıya vermekti ve bedeli buna değmez. Eski kayıtlarda duran
   şehir bilgisi SİLİNMEZ (dördüncü ilke: yayımlanmış kayıt geriye
   dönük değiştirilmez), ama yenisi yazılmaz.

   İŞLETME ADIMI: sunucudaki geo-onbellek.json ham adres taşır ve
   ELLE SİLİNMELİDİR. Kod artık onu ne okur ne yazar; ama var olan
   dosyayı kod silemez, çünkü veri dizinini silmek kodun işi değildir.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php kvkk-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* OKUBENI 37: kaynakta bir dizeyi ararken YORUMLARI ÇIKARIN.
   "ip-api.com çağrısı yok" denemesi, kusuru ANLATAN yorumun kendisine
   takıldı ve düzeltme yapıldığı hâlde KALDI verdi. Bu projede her
   düzeltmenin yanına neyin neden kaldırıldığı yazılıyor; o yazı
   kalacak, kapı ona takılmayacak. Ölçülen şey ÇAĞRIDIR, anlatı değil. */
function kod_yalin(string $yol): string {
    $ham = (string)file_get_contents($yol);
    $cikti = '';
    foreach (token_get_all($ham) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { $cikti .= ' '; continue; }
            $cikti .= $t[1];
        } else $cikti .= $t;
    }
    return $cikti;
}
$apiHam = (string)file_get_contents($KOD . '/api/index.php');
$api = kod_yalin($KOD . '/api/index.php');
olc('api/index.php: ' . strlen($apiHam) . ' bayt ham, ' . strlen($api) . ' bayt yorumsuz');

echo "== 1. Ham adres dışarıya GİTMİYOR ==\n";
den('ip-api.com çağrısı yok', stripos($api, 'ip-api.com') === false);
den('  hiçbir dış coğrafya hizmeti çağrılmıyor',
    preg_match('/ipapi|ipinfo|ipstack|maxmind|freegeoip|geoplugin|ipwhois/i', $api) === 0);
/* Düz HTTP ile dışarıya çıkan HERHANGİ bir istek: adres taşımasa da
   araya girilebilir. Ölçüt, hedefin adı değil şemanın kendisidir. */
$duzHttp = [];
if (preg_match_all("#curl_init\(\s*'http://([^']+)'#i", $api, $m)) $duzHttp = $m[1];
if (preg_match_all("#file_get_contents\(\s*'http://([^']+)'#i", $api, $m2)) $duzHttp = array_merge($duzHttp, $m2[1]);
$duzHttp = array_values(array_filter($duzHttp, fn($h) => strpos($h, '127.0.0.1') !== 0 && strpos($h, 'localhost') !== 0));
olc('düz HTTP çıkışları: ' . ($duzHttp ? implode(', ', $duzHttp) : 'yok'));
den('  şifresiz HTTP ile dışarı istek yok', $duzHttp === []);

echo "\n== 2. Ham adres DİSKE yazılmıyor ==\n";
den('geo-onbellek.json artık yazılmıyor',
    preg_match("/yaz_json\(\s*'geo-onbellek\.json'/", $api) === 0);
den('  ve okunmuyor', preg_match("/oku_json\(\s*'geo-onbellek\.json'/", $api) === 0);
/* Okuma kaydında ham adres alanı olmamalı; yalnızca özet. */
den("okuma kaydı adresi ÖZETLE yazıyor ('iph')", preg_match("/'iph'\s*=>\s*substr\(hash\(/", $api) === 1);
den('  kayıtta ham adres alanı yok',
    preg_match("/'ip'\s*=>\s*\\\$ip\b/", $api) === 0);

echo "\n== 3. Ülke, hiçbir yere sorulmadan geliyor ==\n";
den('geo_al() hâlâ var (çağrı yerleri kırılmasın)', preg_match('/function geo_al\(/', $api) === 1);
/* OKUBENI 35: KAPILAR DA ESKİR. Bu deneme "başlık api/index.php'de
   okunuyor mu" diye soruyordu; 12 Ağustos'ta ülke çözümü tek kaynağa
   (ortak.php, tg_ulke) taşındı ve api artık onu çağırıyor. Kod
   düzeldiği hâlde kapı gerileme sandı. Aranan şey başlığın HANGİ
   DOSYADA okunduğu değil, ülkenin DIŞARIYA HİÇBİR ŞEY SORULMADAN
   gelmesidir; ölçüm de artık onu ölçüyor. */
$ortakK = (string)@file_get_contents($KOD . '/ortak.php');
den('  Cloudflare ülke başlığından okuyor (tek kaynakta)',
    preg_match('/HTTP_CF_IPCOUNTRY/', $ortakK) === 1);
den('  geo_al o tek kaynağı çağırıyor, kendi başına çözmüyor',
    preg_match('/HTTP_CF_IPCOUNTRY/', $api) === 0
    && preg_match('/function geo_al\([^)]*\)[^{]*\{[^}]{0,600}tg_ulke\(\)/s', $api) === 1);
/* OKUBENI 36: ilk desen "'sehir' => (string)($d['city']" arıyordu; kod
   ise DEĞİŞKENE atıyordu ($sehir = (string)($d['city'] ?? '')). Desen
   hiç tutmadığı için deneme, şehir hâlâ üretilirken GECTI verdi.
   Aranan şey biçim değil, dış yanıtın alanına dokunulup
   dokunulmadığıdır. */
den('  şehir alanı artık üretilmiyor', strpos($api, "['city']") === false);

echo "\n== 4. İlkelerle tutarlı mı ==\n";
$ilke = @file_get_contents($KOD . '/ilkeler.php');
den('ilkeler sayfası adresin özetlendiğini söylüyor',
    preg_match('/özetlen|geri çevrilemeyecek|geri döndürülemez/iu', (string)$ilke) === 1);
/* Söz ile yapılan aynı olmalı: sayfa "özetlenir" diyorsa kodun hiçbir
   yerinde ham adres saklanmamalı. Bu, bu oturumda telif metninde ve
   gizli metin kararında kullanılan ölçütün aynısıdır. */
$hamSaklama = preg_match("/\[\s*\\\$ip\s*\]\s*=/", $api) === 1;
den('  ve kod ham adresi hiçbir dizide anahtar yapmıyor', !$hamSaklama);

echo "\n== 5. Gerileme: sayaç ve rapor çalışmayı sürdürüyor ==\n";
den('okuma kaydı hâlâ ülke yazıyor', preg_match("/'ulke'\s*=>/", $api) === 1);
den('rapor uçları duruyor', preg_match('#/yonetim/dergi-rapor#', $api) === 1);
den('hız sınırı ve bot süzgeci duruyor',
    preg_match('/kotu_say\(/', $api) === 1 && preg_match('/bot\|crawl\|spider/', $api) === 1);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
