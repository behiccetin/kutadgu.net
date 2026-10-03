<?php
/* =====================================================================
   POSTA KAPISI
   ---------------------------------------------------------------------
   Ne sınar: davet ve parola yenileme iletilerinin çıktığı yolu.

   Bu kapı bir sunucuya gerçekten ileti göndermez. Kendi içinde SAHTE bir
   SMTP sunucusu açar, konuşmanın tamamını kaydeder ve gönderilen iletiyi
   satır satır denetler. Böylece sınama ne ağa bağlıdır ne de bir hesap
   parolasına.

   Sınanan kusurlar gerçek kusurlardır:
     §1 Aktarıcı yazılmadığında sistem sessizce mail()'e düşüyordu ve
        mail() teslim etmediği hâlde "GONDERILDI" yazıyordu.
     §2 Başarısızlıkta gerekçe yazılmıyordu; kayıtta yalnız "BASARISIZ"
        vardı ve hangi adımda kırıldığı bilinemiyordu.
     §3 TLS doğrulaması kapatılmış bir istemci, sahte bir sunucuya da
        parolayı verirdi.
     §4 Parola yenileme bağlantısının WhatsApp'a düşmesi, kurtarma
        aracını hesap ele geçirme aracına çevirir.
     §5 İzleme ucu kimlik istemezse, sistemin hataları ve kimin ne zaman
        girdiği herkese açık olur.
   ===================================================================== */

$kok = dirname(__DIR__) . '/kutadgunet';
$hata = 0; $sira = 0;
function ol(string $ad, bool $ok, string $not = ''): void {
    global $hata, $sira;
    $sira++;
    if (!$ok) $hata++;
    printf("%s  %s%s\n", $ok ? ' OK ' : 'KUSUR', $ad, $not !== '' ? ('  -> ' . $not) : '');
}

/* ---- Sahte SMTP sunucusu (ayrı süreç) ---- */
$sahte = sys_get_temp_dir() . '/kutadgu-sahte-smtp.php';
$kayit = sys_get_temp_dir() . '/kutadgu-smtp-konusma.txt';
@unlink($kayit);
file_put_contents($sahte, '<?php
$kayit = ' . var_export($kayit, true) . ';
$s = stream_socket_server("tcp://127.0.0.1:2531", $e, $m);
if (!$s) exit(1);
$c = @stream_socket_accept($s, 20);
if (!$c) exit(1);
$yaz = function (string $x) use ($c) { fwrite($c, $x . "\r\n"); };
$log = "";
$yaz("220 sahte ESMTP");
$veri = false;
while (($satir = fgets($c, 4096)) !== false) {
    $t = rtrim($satir, "\r\n");
    $log .= $t . "\n";
    if ($veri) { if ($t === ".") { $veri = false; $yaz("250 2.0.0 Ok: queued as SINAMA"); } continue; }
    $u = strtoupper($t);
    if (strpos($u, "EHLO") === 0)          $yaz("250-sahte\r\n250-AUTH PLAIN LOGIN\r\n250 HELP");
    elseif (strpos($u, "AUTH PLAIN") === 0) $yaz("235 2.7.0 Authentication successful");
    elseif (strpos($u, "MAIL FROM") === 0)  $yaz("250 2.1.0 Ok");
    elseif (strpos($u, "RCPT TO") === 0)    $yaz("250 2.1.5 Ok");
    elseif ($u === "DATA") { $veri = true; $yaz("354 End data with <CR><LF>.<CR><LF>"); }
    elseif ($u === "QUIT") { $yaz("221 Bye"); break; }
    else $yaz("250 Ok");
}
file_put_contents($kayit, $log);
');
$sur = proc_open(PHP_BINARY . ' ' . escapeshellarg($sahte), [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $borular);
usleep(400000);

/* ---- Sınanan katman ---- */
if (!function_exists('tg_canli_ayar')) {
    function tg_canli_ayar($k, $v = null) { return $GLOBALS['__sinama_ayar'][$k] ?? $v; }
}
if (!function_exists('tg_ayar')) { function tg_ayar($k, $v = null) { return $v; } }
require_once $kok . '/k/posta.php';

/* §0 Aktarıcı yazılmamışken kurulu DEĞİLDİR */
$GLOBALS['__sinama_ayar'] = ['smtp' => []];
ol('§0 aktarıcı yazılmadan kurulu görünmüyor', pst_kurulu() === false);

/* §1 Yazıldığında teslim eder ve iletiyi doğru kurar */
$GLOBALS['__sinama_ayar'] = ['smtp' => ['sunucu' => '127.0.0.1', 'port' => 2531,
    'kullanici' => 'kadi', 'parola' => 'gizli', 'guvenlik' => 'yok',
    'gonderen' => 'bildirim@kutadgu.net', 'gonderen_ad' => 'Kutadgu', 'yanit' => '']];
ol('§1a aktarıcı yazılınca kurulu', pst_kurulu() === true);
$govde = "Sayın Gökhan,\n\nBağlantı: https://kutadgu.net/hesap-kur.php?k=abc\n";
$r = pst_smtp_gonder('gokhan@example.org', 'Kutadgu | Davet · sınama', $govde);
ol('§1b ileti teslim edildi', !empty($r['ok']), (string)($r['hata'] ?? ''));

if (is_resource($sur)) { proc_close($sur); }
$k = is_file($kayit) ? (string)file_get_contents($kayit) : '';
ol('§1c konuşma kaydedildi', $k !== '');
ol('§1d gönderen yazıldı', strpos($k, 'MAIL FROM:<bildirim@kutadgu.net>') !== false);
ol('§1e alıcı yazıldı', strpos($k, 'RCPT TO:<gokhan@example.org>') !== false);
ol('§1f kimlik doğrulaması yapıldı', stripos($k, 'AUTH PLAIN') !== false);
/* Parola konuşmada base64'tür ama DÜZ metin olarak GEÇMEMELİDİR. */
ol('§1g parola düz metin geçmiyor', strpos($k, 'gizli') === false);

/* Gövde base64'tür; çözüldüğünde Türkçe harfler bozulmadan çıkmalı.
   Ölçülen kusur değil ama ölçülmeye değer: 8bit gönderimde satır
   uzunluğu ve baştaki nokta tuzağı gövdeyi sessizce kesiyordu. */
/* Kayıt satır satır tutulur: DATA'dan sonraki boş satırı gövde izler ve
   tek nokta gövdeyi bitirir. Düzenli ifadeyle çıkarmayı denemek burada
   yanlış ölçüm verdi (başlıkların bir kısmını da gövde sandı); ayrıştırma
   satır sayarak yapılır. */
/* 14 Ağustos 2026: gövde artık multipart/alternative. Bu ayrıştırıcı
   TEK base64 yığını varsayıyordu ve sınır satırlarını da gövde sandı;
   base64_decode çöp döndü, kapı doğru kodu kusurlu bildirdi. Biçim
   gerçekten değişti — ölçüm biçime uyduruldu, biçim ölçüme değil.
   Ölçülen şey aynı kaldı: METİN bölümü bozulmadan gitti mi. */
$bolumler = []; $suan = null; $govdede = false; $bosGordu = false;
$sinirAdi = '';
foreach (explode("\n", $k) as $satir) {
    $satir = rtrim($satir, "\r");
    if (!$govdede) {
        if (strtoupper($satir) === 'DATA') $govdede = true;
        continue;
    }
    if (!$bosGordu) {
        if (preg_match('#boundary="([^"]+)"#', $satir, $m)) $sinirAdi = $m[1];
        if ($satir === '') $bosGordu = true;
        continue;
    }
    if ($satir === '.') break;
    if ($sinirAdi !== '' && strpos($satir, '--' . $sinirAdi) === 0) { $suan = null; continue; }
    if ($sinirAdi !== '' && $suan === null) {
        if (stripos($satir, 'Content-Type:') === 0) {
            $suan = (stripos($satir, 'text/plain') !== false) ? 'metin'
                  : ((stripos($satir, 'text/html') !== false) ? 'html' : 'baska');
            $bolumler[$suan] = $bolumler[$suan] ?? ['bas' => true, 'b64' => ''];
        }
        continue;
    }
    if ($sinirAdi === '') { $bolumler['metin']['b64'] = ($bolumler['metin']['b64'] ?? '') . $satir; continue; }
    if ($suan !== null && isset($bolumler[$suan])) {
        if (!empty($bolumler[$suan]['bas'])) {
            if ($satir === '') { $bolumler[$suan]['bas'] = false; }
            continue;   /* bölümün kendi başlıkları */
        }
        $bolumler[$suan]['b64'] .= $satir;
    }
}
$coz  = isset($bolumler['metin']) ? (string)base64_decode($bolumler['metin']['b64']) : '';
$cozH = isset($bolumler['html'])  ? (string)base64_decode($bolumler['html']['b64'])  : '';
ol('§1h metin bölümü bozulmadan gitti', $coz !== '' && strpos($coz, 'Sayın Gökhan') === 0 && strpos($coz, 'hesap-kur.php?k=abc') !== false);
ol('§1i HTML bölümü de gitti ve aynı bağlantıyı taşıyor',
   $cozH !== '' && strpos($cozH, '<!doctype html>') === 0 && strpos($cozH, 'hesap-kur.php?k=abc') !== false);

/* §2 Başarısızlıkta gerekçe yazılır ve gerekçe SOMUTTUR */
$GLOBALS['__sinama_ayar'] = ['smtp' => ['sunucu' => '127.0.0.1', 'port' => 2599, 'guvenlik' => 'yok',
    'kullanici' => '', 'parola' => '', 'gonderen' => 'a@kutadgu.net']];
$r2 = pst_smtp_gonder('x@example.org', 'k', 'g');
ol('§2a ulaşılamayan sunucu başarısız sayılır', empty($r2['ok']));
ol('§2b gerekçe somut', strpos((string)$r2['hata'], 'baglanti kurulamadi') === 0, (string)$r2['hata']);

/* §3 STARTTLS isteniyorsa ve sunucu bilmiyorsa ŞİFRESİZ DEVAM EDİLMEZ.
   Bu kapının en önemli maddesi budur: geri düşen bir istemci, parolayı
   dinleyen birine düz metin verir. */
$kaynak = (string)file_get_contents($kok . '/k/posta.php');
ol('§3a TLS doğrulaması açık', strpos($kaynak, "'verify_peer' => true") !== false);
ol('§3b sunucu adı doğrulanıyor', strpos($kaynak, "'verify_peer_name' => true") !== false);
ol('§3c STARTTLS yoksa şifresiz devam edilmiyor',
   strpos($kaynak, 'sunucu STARTTLS bilmiyor') !== false);
ol('§3d TLS kırılınca gönderim durur',
   strpos($kaynak, 'TLS el sikismasi basarisiz') !== false);

/* §4 Parola yenileme bağlantısı WhatsApp'a düşmez.
   WhatsApp düğmesi YALNIZCA davet metnini okur; başka hiçbir alan
   okumaz ve yenileme akışında wa.me geçmez. */
$panel = (string)file_get_contents($kok . '/panel.php');
/* Yalnızca GERÇEK adres sayılır. İlk yazımda "wa.me" düz metin olarak
   arandı ve gerekçe açıklamasındaki geçişi de kusur saydı: ölçüm yanlış
   çıktığında önce ölçümden şüphelenilir. */
$wa = [];
if (preg_match_all("#'https://wa\.me/#", $panel, $mm)) $wa = $mm[0];
/* 15 Ağustos 2026: ikinci bir devir noktası açıldı — hakem daveti,
   yalnız KURUCU baş editörler için. Ölçülen şey "kaç tane var"
   olamaz; sayı arttıkça kapı doğru kodu kusurlu bildirir. Ölçülen şey
   HER BİRİNİN NEYİ TAŞIDIĞIDIR: davet metni ya da davet bağlantısı,
   başka hiçbir şey. */
ol('§4a wa.me kullanımı sayıldı', count($wa) >= 1, (string)count($wa));
/* Hakem daveti devri: yalnız kurucuya çizilir ve şifre taşımaz. */
$ds0 = strpos($panel, 'function davetSonuc');
$ds1 = $ds0 === false ? false : strpos($panel, 'function api(', $ds0);
$dsB = ($ds0 === false) ? '' : substr($panel, $ds0, ($ds1 === false ? 3600 : $ds1 - $ds0));
ol('§4a1 hakem daveti devri yalnız kurucuya çizilir',
   strpos($dsB, 'if(!KURUCU') !== false);
ol('§4a2 hakem devri ŞİFRE taşımıyor',
   strpos($dsB, 'r.sifre') === false && strpos($dsB, 'S.waMetin') !== false);
ol('§4a3 hakem devri parola/yenileme alanı okumuyor',
   strpos($dsB, 'parola') === false && strpos($dsB, 'yenile') === false);
if (preg_match("/dgWhats'\\)\\.addEventListener\\('click', function\\(\\)\\{(.*?)\\n  \\}\\);/s", $panel, $mw)) {
    $govdeJs = $mw[1];
    ol('§4b WhatsApp yalnız davet metnini okuyor',
       strpos($govdeJs, "dvMetin") !== false && strpos($govdeJs, 'parola') === false && strpos($govdeJs, 'yenile') === false);
} else {
    ol('§4b WhatsApp yalnız davet metnini okuyor', false, 'düğme bulunamadı');
}
$kurBetik = (string)file_get_contents($kok . '/hesap-kur.php');
ol('§4c yenileme ekranında wa.me yok', strpos($kurBetik, 'wa.me') === false);

/* §5 İzleme ucu yetki ister ve sır dönmez */
$api = (string)file_get_contents($kok . '/api/index.php');
if (preg_match("#/yonetim/izleme' && \\\$metod === 'GET'\\) \\{(.*?)\ncikti#s", $api, $mi) ||
    preg_match("#/yonetim/izleme'(.*?)\n\\}#s", $api, $mi)) {
    ol('§5a izleme yetki istiyor', strpos($mi[1], 'alan_karar_yetkisi()') !== false);
} else ol('§5a izleme yetki istiyor', false, 'uç bulunamadı');
ol('§5b izleme e-posta adresi döndürmüyor',
   preg_match("#'gorulen' => \\\$gorulen#", $api) === 1 && strpos($api, "'eposta' => \$v") === false);
/* Son görülme kaydında ADRES DÜZ HÂLİYLE durmaz: anahtar özettir. */
$hs = (string)file_get_contents($kok . '/k/hesap.php');
ol('§5c son görülme anahtarı özet', strpos($hs, '$e = hs_eposta_anahtar((string)($h[\'eposta\'] ?? \'\'));') !== false);
ol('§5d son görülme IP tutmuyor',
   preg_match('/function hs_gorundu.*?\n    \}/s', $hs, $mg) === 1 && strpos($mg[0], 'REMOTE_ADDR') === false);

/* §6 eposta.log altı sütunlu: yol ve gerekçe yazılıyor */
ol('§6 kayıt yol ve gerekçeyi taşıyor',
   strpos($api, '"\t" . $yol2 . "\t" . $neden . "\n"') !== false);


/* =====================================================================
   PAROLA YENİLEME: DÜRÜST YANIT VE KURTARMA YOLU
   ---------------------------------------------------------------------
   BİLDİRİLEN KUSUR (14 Ağustos 2026): "parolamı unuttum dedim, mesaj
   gönderildi dedi ama e-postama gelmedi."

   İKİ AYRI KUSUR VARDI:
     1. Posta yolu kurulu değil — bu zaten biliniyordu ve aktarıcı
        kuruluyor.
     2. UÇ, eposta_gonder()'in DÖNÜŞ DEĞERİNİ HİÇ OKUMUYORDU ve her
        durumda "gönderildi" diyordu. Bu depoda on birinci kez aynı
        kusur: sistem yapmadığı bir şeyi duyuruyor.

   SIZINTI KURALI DURUYOR. Hesabın var olup olmadığı yine söylenmez;
   posta yolunun açık olup olmadığı SİSTEMİN durumudur, hesabın değil.
   O yüzden hesap aranmadan ÖNCE sorulur ve iki durumda da aynı cümle
   döner: hesabı olan da olmayan da aynı şeyi duyar, duyduğu şey doğru.

   KURTARMA YOLU. Jeton hiçbir yerde saklanmaz (kayda yalnız sha256
   özeti girer) ve bu doğrudur; sonucu şudur: gönderilememiş bir
   bağlantı geri getirilemez. Getirilebilseydi veri dizinini okuyan
   herkes her hesaba girerdi. Yapılabilecek tek doğru şey sunucuda YENİ
   bir bağlantı üretmektir — ve o araç YALNIZCA komut satırından
   çalışmalıdır. O satır silinirse araç bir arka kapıya döner; bu kapı
   onu her koşuda ölçer.
   ===================================================================== */
$apiHam = (string)@file_get_contents($kok . '/api/index.php');
$pstHam = (string)@file_get_contents($kok . '/k/posta.php');
$arac   = (string)@file_get_contents($kok . '/parola-baglantisi.php');

ol('§7a posta yolunun varlığı ayrı sorulabiliyor (pst_yol_var)',
   strpos($pstHam, 'function pst_yol_var()') !== false);
ol('§7b uç gönderim sonucunu okuyor',
   strpos($apiHam, '$gitti = eposta_gonder($eposta,') !== false);
ol('§7c gönderilemediğinde yanıt düzeltiliyor',
   strpos($apiHam, "if (!\$gitti) {") !== false
   && strpos($apiHam, "\$yanit['posta_yok'] = true;") !== false);
ol('§7d posta yolu kapalıyken hesap aranmadan söyleniyor',
   preg_match('/\$postaVar = .{0,80}pst_yol_var\(\);.{0,600}\$yanit = \$postaVar/su', $apiHam) === 1);
ol('§7e sızıntı kuralı duruyor (iki durumda da aynı cümle)',
   substr_count($apiHam, 'Bu adreste bir hesap varsa') >= 2);
ol('§7f gönderim hatasının SEBEBİ okura yazılmıyor',
   strpos($apiHam, "pst_son_hata()], 400") === false);

ol('§8a kurtarma aracı var', $arac !== '');
ol('§8b araç yalnızca komut satırından çalışıyor',
   strpos($arac, "PHP_SAPI !== 'cli'") !== false);
ol('§8c  ve web isteği ikinci ölçütle de eleniyor',
   strpos($arac, "isset(\$_SERVER['REQUEST_METHOD'])") !== false);
ol('§8d  web\'den çağrılırsa 404 veriyor',
   strpos($arac, 'http_response_code(404)') !== false);
ol('§8e adres verilmeden hiçbir hesap açılmıyor',
   strpos($arac, 'FILTER_VALIDATE_EMAIL') !== false && strpos($arac, 'exit(2)') !== false);
ol('§8f parolası olmayan hesapta yenileme yolu açılmıyor',
   strpos($arac, "trim((string)(\$h['parola'] ?? '')) === ''") !== false);
ol('§8g üretilen bağlantı kütüğe düşüyor',
   strpos($arac, "error_log('kutadgu/parola-baglantisi") !== false);
ol('§8h jeton dosyada saklanmıyor (özet yazılıyor)',
   strpos((string)@file_get_contents($kok . '/k/hesap.php'), "'ozet'   => hash('sha256', \$jeton)") !== false);

/* hs_kaydet() hesap modülünde olmalı: k/hesap.php içindeki
   hs_yenileme_uret() onu çağırıyor ve modül tek başına da yükleniyor. */
ol('§8i hesap modülü tek başına çalışıyor (hs_kaydet orada)',
   strpos((string)@file_get_contents($kok . '/k/hesap.php'), 'function hs_kaydet(array $hesap): void') !== false);

/* =====================================================================
   9. İLETİNİN BİÇİMİ: OLTALAMAYA BENZEMESİN

   ÖLÇÜLEN KUSUR — 14 Ağustos 2026. Parola yenileme iletisi Gmail'de
   SPAM klasörüne düştü. Önce kimlik doğrulamasından şüphelenildi;
   iletinin ASLI okundu ve üçü de geçiyordu:
     SPF PASS · DKIM PASS (d=kutadgu.net) · DMARC PASS
   Yani DNS değil, iletinin BİÇİMİ. Gmail'in kendi cümlesi: "geçmişte
   spam olarak tanımlanan iletilere benziyor."

   Bu bölüm o biçimi ölçer. Bir sonraki kişi HTML bölümünü kaldırırsa
   ya da iletiye izleme pikseli koyarsa kapı görür.
   ===================================================================== */
$kaynakPosta = (string)@file_get_contents($kok . '/k/posta.php');
ol('§9a HTML gövde üreticisi var', function_exists('pst_html_govde'));
ol('§9b ileti multipart/alternative gidiyor',
   strpos($kaynakPosta, 'multipart/alternative; boundary=') !== false);
ol('§9c düz metin bölümü DURUYOR (betiksiz okuyan da okuyabilsin)',
   strpos($kaynakPosta, "\$bolum('text/plain', \$govde)") !== false);
/* multipart/alternative'de SON bölüm tercih edilir: HTML metinden
   SONRA yazılmalı. Ters yazılırsa HTML boşuna gider. */
$sirasi = strpos($kaynakPosta, "\$bolum('text/plain'");
$sirasiH = strpos($kaynakPosta, "\$bolum('text/html'");
ol('§9d sıra doğru: önce metin, sonra HTML',
   $sirasi !== false && $sirasiH !== false && $sirasi < $sirasiH);

if (function_exists('pst_html_govde')) {
    $denemeGovde = "Sayın Kimse,\n\nBir istek alındı.\n\n"
                 . "https://ornek.org/hesap-kur.php?y=abc123\n\n"
                 . "<script>kotu()</script> & \"tırnak\"\n\nKutadgu\n";
    $h = pst_html_govde($denemeGovde, 'Kutadgu', 'https://ornek.org');
    ol('§9e gövdedeki HTML KAÇIRILIYOR (ileti bir enjeksiyon yolu değil)',
       strpos($h, '<script>kotu') === false && strpos($h, '&lt;script&gt;') !== false);
    /* İzleme pikseli, uzak resim, dış yazı tipi: hiçbiri olmamalı.
       Bir doğrulama iletisinin okunup okunmadığını ölçmek bizim işimiz
       değildir; ölçmeyen bir ileti sızdırmaz da. */
    ol('§9f dış kaynak yok (resim, izleme pikseli, uzak yazı tipi)',
       !preg_match('#<img|\ssrc=|url\(|<link|<script#i', $h));
    /* Adres GİZLENMEZ: düğmenin altında açıkça yazar. Gizlenen adres
       oltalamanın kendi yöntemidir. */
    ol('§9g bağlantı düğme olur ama adres AÇIKÇA da yazılır',
       substr_count($h, 'hesap-kur.php?y=abc123') === 2);
    ol('§9h düğmenin yazısı eylemi söylüyor, marka adını değil',
       strpos($h, '>Kutadgu</a>') === false);
    ol('§9i HTML tek parça ve kapanıyor',
       strpos($h, '<!doctype html>') === 0 && substr(trim($h), -7) === '</html>');
}

/* İletinin METNİ de bağlamsız olmamalı: "parolanızı yenileyin" deyip
   çıplak bir bağlantı koyan metin, süzgeç için de insan için de
   oltalamaya benzer. Uç metni kendini tanıtmalı ve bir oltalama
   iletisinin asla yazmayacağı cümleyi yazmalı. */
$ucKaynak = (string)@file_get_contents($kok . '/api/index.php');
$pb = strpos($ucKaynak, "' | Parola yenileme'");
$pblok = $pb === false ? '' : substr($ucKaynak, $pb, 1400);
ol('§9j parola iletisi önce KENDİNİ TANITIYOR',
   strpos($pblok, 'açık erişimli akademik yayın sistemi') !== false);
ol('§9k ve parolanın hiçbir zaman sorulmadığını yazıyor',
   strpos($pblok, 'parolanızı hiçbir zaman istemez') !== false);

echo "\n" . ($hata === 0 ? "GEÇTİ" : "KALDI") . ": {$sira} ölçüm, {$hata} kusur\n";
@unlink($sahte); @unlink($kayit);
exit($hata === 0 ? 0 : 1);
