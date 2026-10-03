<?php
/* Kutadgu API (PHP 8.x)
 * Tüm /api/* istekleri buraya yönlenir (.htaccess ile).
 * Veri, public_html DIŞINDA saklanır; yeri KUTADGU_DATA ortam değişkeninden
 * gelir. Depoda hiçbir kişiye ya da makineye özgü yol yazılı değildir: yazılı
 * bir yol, taşınan bir sistemde sessizce yanlış dizini işaret eder.
 * Yönetim uçlarının ön eki /yonetim/ ... Anahtarlar SADECE sunucuda,
 * veri dizininde durur; depoya hiçbir zaman girmez.
 *
 * Dosyada yalnızca Kutadgu'nun kendi uçları bulunur. Daha eski bir
 * arkayüzden kalan uçlar 8 Ağustos 2026'da büsbütün çıkarıldı.
 */
declare(strict_types=1);
date_default_timezone_set('Europe/Istanbul');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
session_start();

/* ---- Merkezi yapılandırma (alan adından bağımsızlık) ---- */
$__ortak = __DIR__ . '/../ortak.php';
if (is_file($__ortak)) require_once $__ortak;

/* Bilim alanı sınıflandırması: hem hesap hem çalışma uçları kullanır */
$__alanlar = __DIR__ . '/../k/alanlar.php';
if (is_file($__alanlar)) require_once $__alanlar;

/* Arşiv dökümü: arşiv her değiştiğinde döküm yeniden üretilir. Üretim
   yanıt gönderildikten sonra çalışır; API'nin hızını etkilemez. */
$__dokum = __DIR__ . '/../k/dokum.php';
if (is_file($__dokum)) require_once $__dokum;

/* Posta yolu: dışarıdaki aktarıcıya SMTP ile teslim. Sunucunun kendi
   mail() işlevi yalnızca aktarıcı ayarlanmamışsa kullanılır; gerekçesi
   k/posta.php'nin başındadır. */
$__posta = __DIR__ . '/../k/posta.php';
if (is_file($__posta)) require_once $__posta;

/* =====================================================================
   VERİ DİZİNİ / DATA DIRECTORY
   ---------------------------------------------------------------------
   Sıra: ayar.php'deki veri_dizini > KUTADGU_DATA ortam değişkeni > son
   çare olarak webroot'un bir üstü. Son çare yolda kişi adı yoktur; bir
   depo herkese açıldığında oradaki kişi adı hem gereksiz bir bilgidir
   hem de yanlış makinede sessizce "doğru" görünür.

   Dizin yoksa bir kez kurulmaya çalışılır; kurulamıyor ya da yazılamıyorsa
   istek burada durur. Eski davranış sessizdi: dizin şaşınca sistem bomboş
   bir dizinle çalışmayı sürdürüyor, kendini kurulmamış sanıyor ve bütün
   kurulum yollarını yeniden işletiyordu. Bu sistemdeki asıl tehlike hatanın
   kendisi değil, hatanın duyulmamasıydı.

   Order: the veri_dizini setting, then the KUTADGU_DATA environment
   variable, then a last resort path beside the web root. If the directory
   is missing or unwritable the request stops here with an error instead of
   quietly writing somewhere else, because a silently relocated data
   directory makes the system believe it has never been set up.
   ===================================================================== */
$DATA = function_exists('tg_veri_dizini')
    ? tg_veri_dizini()
    : (getenv('KUTADGU_DATA') ?: dirname(__DIR__, 2) . '/kutadgu-data');
$DATA = rtrim((string)$DATA, '/');
if ($DATA === '' || (!is_dir($DATA) && !@mkdir($DATA, 0750, true) && !is_dir($DATA)) || !is_writable($DATA)) {
    error_log('kutadgu/veri: veri dizini kullanilamiyor, istek durduruldu (KUTADGU_DATA ayarini denetle)');
    http_response_code(500);
    echo json_encode(['ok' => false,
        'hata' => 'Veri dizinine ulaşılamıyor; sistem isteği güvenle karşılayamaz. Sunucu yöneticisine bildirin.',
        'hata_en' => 'The data directory is unreachable; the request cannot be served safely. Please notify the server administrator.'],
        JSON_UNESCAPED_UNICODE);
    exit;
}

function veri_yolu(string $ad): string { global $DATA; return $DATA . '/' . $ad; }

/* Eski "ruh-" adlı veri dosyalarını yeni adlarına taşır. Adlar eski bir
   arkayüzden geliyordu; Kutadgu'nun dosyaları Kutadgu'nun diliyle anılmalı. Taşıma bir
   kez olur ve kendiliğinden olur: bir ad değiştiğinde içindeki veriyi
   kaybetmek, adı hiç düzeltmemekten kötüdür. Taşıma başarısız olursa eski
   dosya yerinde durmayı sürdürür ve okuma ona düşer. */
function veri_gecis(): void {
    static $yapildi = false;
    if ($yapildi) return;
    $yapildi = true;
    $harita = [
        'ruh-ayar.json'  => 'yonetim-ayar.json',
        'ruh-spam.json'  => 'hiz-sinir.json',
        'ruh-guven.json' => 'guvenli-tarayici.json',
        'ruh-geo.json'   => 'geo-onbellek.json',
    ];
    foreach ($harita as $eski => $yeni) {
        $e = veri_yolu($eski); $y = veri_yolu($yeni);
        if (is_file($e) && !is_file($y)) @rename($e, $y);
    }
}

function oku_json(string $ad, $vars = []) {
    /* Yeni ad yoksa ama eski ad varsa, önce taşı. */
    static $eskiAdlar = ['yonetim-ayar.json' => 1, 'hiz-sinir.json' => 1,
                         'guvenli-tarayici.json' => 1, 'geo-onbellek.json' => 1];
    if (isset($eskiAdlar[$ad]) && !is_file(veri_yolu($ad))) veri_gecis();
    return oku_json_ham($ad, $vars);
}
function oku_json_ham(string $ad, $vars = []) {
    $p = veri_yolu($ad);
    if (!is_file($p)) return $vars;
    $d = json_decode(file_get_contents($p) ?: 'null', true);
    return $d === null ? $vars : $d;
}
function yaz_json(string $ad, $veri): bool {
    $p = veri_yolu($ad); $tmp = $p . '.tmp';
    if (file_put_contents($tmp, json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    $ok = rename($tmp, $p);
    /* ---- ARŞİV DÖKÜMÜNÜN TETİĞİ ----
       Döküm istek anında üretilmez; arşiv değiştiği anda üretilir.
       Tetik buraya konuldu, tek tek yayımlama uçlarına değil: arşivi
       değiştiren her yol sonunda bu satırdan geçer, dolayısıyla
       unutulacak bir uç kalmaz. Üretim yanıt gönderildikten SONRA
       çalışır ve kilitlidir; bu çağrı isteği bekletmez. */
    if ($ok && $ad === 'yazilar.json' && function_exists('dk_tetikle')) dk_tetikle('yazilar.json');
    return $ok;
}
/* =====================================================================
   FORM BİÇİMİ: BETİKSİZ KULLANICI HAM JSON GÖRMEZ
   ---------------------------------------------------------------------
   Uçlar JSON konuşur; betik çalışırken bu doğrudur. Ama başvuru formu
   betik çalışmasa da gönderilebilmelidir (devir belgesi 7.1: "sihirbaz
   bir kabuk olmalı, tek yol değil"). O durumda tarayıcı formu doğrudan
   POST eder ve yanıtı SAYFA olarak gösterir; ekranda `{"ok":false,...}`
   görmek bir yanıt değil, bir kazadır.

   Bu yüzden form gövdesinde "bicim=form" varsa cikti() JSON basmaz:
   303 ile geldiği sayfaya döner ve sonucu tek kullanımlık bir çerezle
   taşır. Çerez yalnızca gösterilecek metni taşır; kimlik, oturum ya da
   girilen alanların kendisi ORADA DURMAZ.

   303 bilerek: POST'tan sonra 302 kimi tarayıcıda POST'u yineler,
   303 yenilemeyi GET'e çevirir ve aynı başvurunun iki kez düşmesini
   önler.

   Yönlendirme adresi dışarıdan geldiği için AÇIK YÖNLENDİRME
   tehlikesi taşır: yalnızca bu sitenin kendi yolları kabul edilir,
   başka konağa giden bir "geri" değeri sessizce başvuru sayfasına
   düşer.
   ===================================================================== */
function form_bicimi(): bool {
    return isset($_POST['bicim']) && (string)$_POST['bicim'] === 'form';
}
function form_geri_yol(): string {
    $ham = (string)($_POST['geri'] ?? '');
    $yol = (string)parse_url($ham, PHP_URL_PATH);
    /* Yalnız kendi yolumuz: "//baska.site" ve "http://..." elenir. */
    if ($yol === '' || $yol[0] !== '/' || str_starts_with($yol, '//')) $yol = '/basvuru.php';
    if (!preg_match('#^/[A-Za-z0-9_./-]*$#', $yol)) $yol = '/basvuru.php';
    return $yol;
}
function cikti($veri, int $kod = 200): void {
    if (form_bicimi() && is_array($veri) && array_key_exists('ok', $veri)) {
        $iyi  = !empty($veri['ok']);
        $ileti = (string)($iyi ? ($veri['mesaj'] ?? '') : ($veri['hata'] ?? ''));
        /* Dosyanın başında Content-Type json verilmişti; bu yanıt JSON
           değil, bir yönlendirmedir. Başlık düzeltilmezse kimi vekil
           sunucu yanıtı JSON sanıp gövdeyi indirmeye kalkar. */
        header('Content-Type: text/html; charset=utf-8');
        setcookie('kbv_sonuc', ($iyi ? '1' : '0') . '|' . $ileti, [
            'expires' => time() + 300, 'path' => '/', 'httponly' => false, 'samesite' => 'Lax',
        ]);
        header('Location: ' . form_geri_yol() . ($iyi ? '?gonderildi=1' : '?hata=1') . '#form', true, 303);
        exit;
    }
    http_response_code($kod);
    echo json_encode($veri, JSON_UNESCAPED_UNICODE);
    exit;
}
function govde_json() {
    $d = json_decode(file_get_contents('php://input') ?: 'null', true);
    return is_array($d) ? $d : [];
}
/* ---- YÖNETİCİ OTURUMUNUN SÜRÜMÜ ----
   Oturum yalnızca doğru parolayla değil, GEÇERLİ BİR SÜRÜMLE de
   açılmış olmalıdır. Sebep: sızmış parolanın kara listeye alınmasından
   ÖNCE açılmış bir oturum, sunucuda öylece duruyor olabilir ve o
   oturum tam da kapatmak istediğimiz parolayla açılmış olabilir.
   Parolanın kendisini oturumdan geri okumak mümkün değildir, yani o
   oturumu ayırt etmenin başka bir yolu yok. Bu yüzden numara
   büyütüldüğünde eski oturumların hepsi birden düşer.

   Bedeli, sistem sahibinin bir kez yeniden giriş yapmasıdır; kazancı,
   kara listenin yalnızca yeni girişleri değil sürmekte olan oturumu da
   kapatmasıdır. Parola sızmışsa zaten yeniden giremeyecektir ve
   yönetimi hesabıyla sürdürür. */
const YONETIM_OTURUM_SURUM = 2;

function girisli(): bool {
    return !empty($_SESSION['kutadgu_yonetim'])
        && (int)($_SESSION['kutadgu_yonetim_surum'] ?? 0) >= YONETIM_OTURUM_SURUM;
}
function yetki_gerek(): void { if (!girisli()) cikti(['ok' => false, 'hata' => 'yetkisiz'], 401); }

/* =====================================================================
   YÖNETİM YETKİSİ / MANAGEMENT AUTHORITY
   ---------------------------------------------------------------------
   Yönetim ekranı artık ayrı bir sayfa değildir; panelin bir sekmesidir
   ve panele hesapla girilir. Bu yüzden yetki iki yerden gelebilir: eski
   tek parolalı yönetici oturumu ya da hesabıyla girmiş bir baş editör.

   Yazma ile okuma ayrı denetlenir. Bir sayıyı görmek onu değiştirmek
   değildir: okuma raporları bütün editörlere, kayıt ve karar işlemleri
   yalnızca baş editörlere açıktır.

   Bu denetim arayüzdeki gizlemenin yerine geçmez, altına konur. Sekme
   hiç basılmamış olsa bile uç kendini korur.

   The management screen is no longer a separate page but a tab inside
   the panel, and the panel is entered with an account. Authority may
   therefore come from either the old single password administrator
   session or from a chief editor signed in with an account. Reading and
   writing are checked separately: seeing a number is not changing it.
   ===================================================================== */
function yonetim_hesap(): ?array {
    require_once __DIR__ . '/../k/hesap.php';
    return hs_oturum();
}
function yonetim_yazma_mi(): bool {
    if (girisli()) return true;
    $h = yonetim_hesap();
    return $h !== null && hs_bas_yetki($h);
}
function yonetim_okuma_mi(): bool {
    if (girisli()) return true;
    $h = yonetim_hesap();
    return $h !== null && hs_editor_mu($h);
}
function yonetim_yazma_gerek(): void {
    if (!yonetim_yazma_mi())
        cikti(['ok' => false, 'hata' => 'Bu işlem yalnızca baş editörlere açıktır.'], 403);
}
function yonetim_okuma_gerek(): void {
    if (!yonetim_okuma_mi())
        cikti(['ok' => false, 'hata' => 'Bu bölüm yalnızca editörlere ve baş editörlere açıktır.'], 403);
}

/* Ziyaretçi IP'si (Cloudflare arkasında gerçek IP başlıkta) */
function ip_al(): string {
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', (string)$_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
    }
    return '0.0.0.0';
}

/* ---------------------------------------------------------------------
   ZİYARETÇİNİN ÜLKESİ

   Bu işlev eskiden ziyaretçinin HAM ADRESİNİ üçüncü bir tarafa,
   üstelik DÜZ HTTP ile gönderiyordu:
   http://ip-api.com/json/<IP>. Şifresiz olduğu için aradaki her
   aktarıcı hangi adresin sorulduğunu görebiliyordu; ip-api.com ise
   adresi doğrudan alıyordu. Ziyaretçinin bu istekten haberi yoktu.

   Daha kötüsü, sonucu ANAHTARI HAM IP OLAN bir dosyada
   (geo-onbellek.json) otuz gün saklıyordu. Oysa hemen yanındaki okuma
   kaydının içinde şu yazılı:

     "Ham adres YAZILMAZ ... Toplanmayan veri sızdırılamaz."

   Yani kayıt dosyası adresi özetliyor, yanındaki önbellek ham hâlde
   saklıyordu. Sistem okura bir şey söyleyip başka bir şey yapıyordu.

   Ve bütün bunlar GEREKSİZDİ: site Cloudflare arkasında ve ülke
   bilgisi CF-IPCOUNTRY başlığıyla, hiçbir yere hiçbir şey
   gönderilmeden zaten geliyor. Dışarıya sorulan şey elde olan şeydi.

   ŞEHİR VE KONUM DÜŞÜRÜLDÜ. Açık erişimli bir arşivin, ziyaretçisinin
   hangi şehirde olduğunu bilmeye ihtiyacı yoktur; o kesinliği elde
   etmenin tek yolu adresi dışarıya vermekti ve bedeli buna değmezdi.
   Eski kayıtlardaki şehir bilgisi SİLİNMEZ (dördüncü ilke: yayımlanmış
   kayıt geriye dönük değiştirilmez), ama yenisi yazılmaz.

   İŞLETME ADIMI: sunucudaki geo-onbellek.json ham adres taşır ve ELLE
   SİLİNMELİDİR. Kod artık onu ne okur ne yazar; var olan dosyayı silmek
   ise kodun işi değildir.

   $ip parametresi, çağrı yerleri kırılmasın diye duruyor ve
   KULLANILMIYOR -- bu işlev artık adrese hiç bakmaz.
   --------------------------------------------------------------------- */
function geo_al(string $ip = ''): array {
    /* ÜLKE TEK KAYNAKTAN OKUNUR.
       Burada bir zamanlar başlık kendi başına çözülüyordu:
       strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY'])). Aynı başlığı
       k/kabuk.php içindeki k_ulke() de çözüyor ve sayfanın DİLİNİ
       ondan belirliyor. Aynı bilgi iki yerde çözülünce zamanla ayrışır:
       biri yedek başlıklara bakar öteki bakmaz, biri bir biçimi kabul
       eder öteki etmez ve sistem okura bir ülke, kaydına başka bir ülke
       yazar. Bu projede aynı sınıf hata dört kez bulundu; kural şudur:
       aynı bilgi tek yerden üretilir.

       Çözen işlev ortak.php'dedir (tg_ulke); hem sayfa katmanı hem API
       aynı dosyayı yükler, bu yüzden yedek bir yola gerek yoktur. Yedek
       yol yazmak, tek kaynağı ikiye bölmenin sessiz biçimidir. */
    $kod = tg_ulke();
    /* Cloudflare bilinmeyen için 'XX', Tor çıkışları için 'T1' verir;
       ikisi de ülke değildir ve ülke diye yazılmaz. */
    if ($kod === 'XX' || $kod === 'T1') $kod = '';
    return ['sehir' => '', 'ulke' => $kod, 'lat' => null, 'lon' => null];
}

/* =====================================================================
   YÖNETİCİ KİMLİĞİ / ADMINISTRATOR CREDENTIAL
   ---------------------------------------------------------------------
   Burada bir zamanlar bir varsayılan parola vardı ve kimlik dosyası
   eksikse kendiliğinden kuruluyordu. İki ayrı sebeple kaldırıldı:
   depoya giren bir parola parola değildir, ve daha sinsisi, kimlik
   dosyası bir taşımada ya da yanlış veri dizininde görünmez olduğunda
   sistem o parolayı kendiliğinden geri kuruyor, üstelik hiç ses
   çıkarmıyordu. Kimliğini kaybeden bir sistemin yapması gereken şey
   kapıyı kapatmaktır, tahminde bulunmak değil.

   Kimlik yoksa bu işlev null döner ve giriş reddedilir. Kurulumun tek
   yolu KUTADGU_ILK_PAROLA ortam değişkenidir: bir kez okunur, karması
   dosyaya yazılır ve dosyada karma bulunduğu sürece bir daha bakılmaz.
   Böylece değişken sunucuda unutulsa bile parolayı ikinci kez kuramaz.
   Kullanıcı adı KUTADGU_ILK_KULLANICI ile verilebilir; verilmezse genel
   bir addır, çünkü bir kişinin adı koda gömülecek bir veri değildir.

   Var olan auth.json dosyaları olduğu gibi çalışmayı sürdürür: içinde
   karma bulunan bir dosya hiçbir koşulda yeniden yazılmaz.

   The default password that used to live here is gone. It leaked through
   the repository, and worse, it was silently reinstalled whenever the
   credential file went missing (for instance after a move to a different
   data directory). A system that has lost its credential must close the
   door rather than guess. Initial setup now happens once through the
   KUTADGU_ILK_PAROLA environment variable; afterwards the stored hash
   wins and the variable is never consulted again.
   ===================================================================== */
function auth_al(): ?array {
    $a = oku_json('auth.json', null);
    if (is_array($a) && is_string($a['hash'] ?? null) && $a['hash'] !== '') return $a;

    $ilk = (string)(getenv('KUTADGU_ILK_PAROLA') ?: '');
    if ($ilk === '') {
        /* Sessiz kalmasın: kütükteki tek satır, "giriş neden olmuyor"
           sorusunun cevabını aramaya gidecek yolu kısaltır. Sözcükler
           bilerek yalındır; kütük sınamaları belirli anahtar sözcükleri
           arıza sayar, bu satır bir arıza değil bir durum bildirimidir. */
        error_log('kutadgu/kimlik: yonetici parolasi kurulu degil, /login kapali (kurulum icin KUTADGU_ILK_PAROLA)');
        return null;
    }
    /* Uzunluk denetimi kurulumun kendisinde durur: zayıf bir parolayı
       kurup sonra uyarmak, hiç kurmamaktan daha kötü bir yanılsamadır. */
    if (mb_strlen($ilk) < 12) {
        error_log('kutadgu/kimlik: KUTADGU_ILK_PAROLA cok kisa (en az 12 karakter), kurulum yapilmadi');
        return null;
    }
    /* Kara listedeki bir değer yeniden KURULAMAZ da. Yalnızca girişte
       reddetmek yetmezdi: sızmış parolayı bu değişkene yazan bir
       kurulum, dosyaya onun karmasını koyar ve kapı bir daha hiç
       açılmayan bir kapıya dönerdi. Burada durdurmak, o kurulumu hiç
       yapmamak demektir. Liste ve gerekçesi aşağıdadır. */
    if (kara_listede_mi($ilk)) {
        error_log('kutadgu/kimlik: KUTADGU_ILK_PAROLA kara listedeki bir kimlikle ayni, kurulum yapilmadi');
        return null;
    }
    $yeni = is_array($a) ? $a : [];
    $kadi = trim((string)(getenv('KUTADGU_ILK_KULLANICI') ?: ''));
    if (($yeni['kullanici'] ?? '') === '') $yeni['kullanici'] = $kadi !== '' ? $kadi : 'yonetici';
    $yeni['hash'] = password_hash($ilk, PASSWORD_DEFAULT);
    if (!yaz_json('auth.json', $yeni)) {
        error_log('kutadgu/kimlik: ilk kurulum yazilamadi, veri dizini yazilabilir mi');
        return null;
    }
    error_log('kutadgu/kimlik: ilk yonetici parolasi kuruldu; KUTADGU_ILK_PAROLA degiskenini kaldirin');
    return $yeni;
}

/* Kimlik kurulmamışken ne söylenmeli: yöneticiye yapacağı işi anlatan,
   saldırgana ise hiçbir şey vermeyen bir yanıt. Dosyanın adı, yeri ve
   var olan kullanıcı adı bilerek yazılmaz; bunları bilen zaten sunucuda
   olan kişidir. Onun ihtiyacı olan tek şey kurulumun hangi kapıdan
   yapıldığıdır. */
function auth_yok_cikti(): void {
    cikti(['ok' => false,
        'hata' => 'Yönetici parolası kurulu değil; giriş kapalı. Kurulum sunucuda, KUTADGU_ILK_PAROLA ortam değişkeniyle bir kez yapılır (en az 12 karakter).',
        'hata_en' => 'The administrator password is not configured; sign-in is closed. Set it up once on the server through the KUTADGU_ILK_PAROLA environment variable (at least 12 characters).'], 403);
}
/* Oturum açmış uçlar için: kimliği ver ya da isteği burada bitir. */
function auth_gerek(): array {
    $a = auth_al();
    if ($a === null) auth_yok_cikti();
    return $a;
}

/* =====================================================================
   SIZMIŞ KİMLİKLERİN KARA LİSTESİ / BLOCKED LEAKED CREDENTIALS
   ---------------------------------------------------------------------
   Buradaki her satır, bir zamanlar bu sistemin yönetim kapısını açmış
   ve sonra herkesin eline geçmiş bir kimlik bilgisinin sha256 özetidir.
   Özetle eşleşen bir giriş denemesi, auth.json ne derse desin, HER
   KOŞULDA reddedilir.

   NEDEN ÖZET: değerin kendisini buraya yazmak, kapatmaya çalıştığımız
   şeyi bir kez daha depoya koymak olurdu. Özet karşılaştırma için
   yeter; geri dönüşü yoktur.

   NEDEN GEREKLİ: kimlik koddan silinmek yetmiyor. Sunucudaki auth.json
   hâlâ o değerin karmasını taşıyabilir ve taşıdığı sürece kapı, geçmişi
   okuyan herkese açıktır. Sistemin sahibi sunucuda elle iş yapamıyor;
   yapamadığı için de kapının kendi kendini kapatması gerekiyor.

   BU KAPI NE KADAR AÇIYOR: girisli() oturumu küçük bir şey değildir.
   yonetim_yazma_mi() içinde tek başına yeter, yani bütün /yonetim/*
   uçlarını (yazı silme, ayar kaydetme, davet, başvuru kararı) açar.
   Onun için burada bir uyarı değil, bir ret vardır.

   YENİ PAROLA NASIL KURULUR: sunucuda KUTADGU_ILK_PAROLA ortam
   değişkeniyle, bir kez. Bu yol bilerek açık bırakıldı; kurulan değer
   de bu listeden geçer (bkz. auth_al()), yani listedeki bir değer
   yeniden kurulamaz.

   Liste TEK bir dizidir: yarın başka bir kimlik sızarsa özeti buraya
   eklenir ve kapı hiçbir yeri değiştirmeden kapanır.
   ===================================================================== */
const GIRIS_KARA_LISTE = [
    /* Yönetim panelinin ilk kimliği. Kodda gömülü duruyordu, kırktan
       çok commit ile depoya girdi ve depo herkese açıldı. */
    'cd4deefb12bbf1b377df3d4be29fcfbe4c10fce2a575ebd13c40c72738b02339',
];

/* Karşılaştırma sabit zamanlıdır. Özet gizli bir değer değildir ama
   sürenin de bir şey söylememesi iyidir. */
function kara_listede_mi(string $deger): bool {
    if ($deger === '') return false;
    $oz = hash('sha256', $deger);
    foreach (GIRIS_KARA_LISTE as $k) {
        if (hash_equals((string)$k, $oz)) return true;
    }
    return false;
}

/* Reddin yanıtı. Kullanıcıya NE OLDUĞUNU ve NE YAPACAĞINI söyler;
   saldırgana hiçbir şey söylemez: hangi değerin listede olduğu, listede
   kaç madde bulunduğu, kullanıcı adının doğru olup olmadığı ve
   auth.json'da ne yazdığı bu yanıttan okunamaz. Kütüğe düşen satır da
   aynı ölçüdedir.

   Kod 403'tür, 401 değil: bu bir "yeniden dene" durumu değildir,
   kapı bu kimliğe kalıcı olarak kapalıdır. */
function kara_liste_cikti(): void {
    error_log('kutadgu/giris: kara listedeki bir kimlikle giris denendi ve reddedildi; yeni parola KUTADGU_ILK_PAROLA ile kurulur');
    cikti(['ok' => false, 'kara_liste' => true,
        'hata' => 'Bu giriş bilgisi kalıcı olarak kapatıldı: sistemin eski yönetici parolası herkese açık depoya girmiş durumda ve artık hiçbir koşulda kabul edilmiyor. Yeni bir parola sunucuda bir kez kurulur: KUTADGU_ILK_PAROLA ortam değişkenine en az 12 karakterlik yeni bir değer verilir, bir kez giriş denenir ve değişken kaldırılır. Yönetim işleri bu arada baş editör hesabıyla panelden sürdürülebilir.',
        'hata_en' => 'This credential has been permanently disabled: the old administrator password reached a public repository and is no longer accepted under any circumstances. A new password is set once on the server through the KUTADGU_ILK_PAROLA environment variable (at least 12 characters); sign in once, then remove the variable. Management work can continue meanwhile through a chief editor account.'], 403);
}

/* =====================================================================
   GİRİŞ DENEMESİ SAYACI / SIGN-IN ATTEMPT THROTTLE
   ---------------------------------------------------------------------
   Eskiden /login yalnızca sabit bir çeyrek saniye bekliyordu; bu, otomatik
   deneme yapan birini yavaşlatmaz, sadece insanı bekletir. Karşılığında
   sert bir kilit de doğru değil: burayı tek bir kişi kullanıyor, kendi
   kapısında kalmak da bir arıza, üstelik dışarıdan biri onun kullanıcı
   adını deneyerek onu kilitleyebilir.

   Seçilen orta yol: gecikme başarısızlıkla birlikte artar (0,3 sn'den
   2 sn'ye), ARDA ARDA 8 başarısızlıktan sonra 5 dakika kilit gelir ve
   kilit kendiliğinden açılır. 15 dakika sessizlikten ya da tek bir başarılı
   girişten sonra sayaç sıfırlanır. Yani parola deneyen biri saatte ancak
   birkaç düzine deneme yapabilir, parolasını yanlış yazan kişi ise en kötü
   ihtimalle beş dakika bekler. Kalıcı kilit, elle açılması gereken kilit ve
   IP yasağı bilerek yazılmadı: hepsi tek kişilik bir sistemde onarılması
   arızadan pahalı çözümlerdir.

   Sayaç IP ile kullanıcı adının birlikte karmasıyla tutulur; kütükte ne
   IP ne kullanıcı adı düz durur.
   ===================================================================== */
const GIRIS_PENCERE = 900;   /* sayaç bu kadar sessizlikten sonra sıfırlanır */
const GIRIS_ESIK    = 8;     /* bu kadar art arda başarısızlıktan sonra kilit */
const GIRIS_KILIT   = 300;   /* kilit süresi (saniye), kendiliğinden açılır */

function giris_anahtar(string $kullanici): string {
    return substr(hash('sha256', ip_al() . '|' . mb_strtolower(trim($kullanici))), 0, 32);
}
/* Süresi dolmuş bir kayıt sayılmaz. Kilidini çekmiş bir kayıt da sayılmaz:
   kilit süresi bittiğinde sayaç sıfırlanır, yoksa bir kez kilitlenen kişi
   her yanlış denemede anında yeniden kilitlenir ve pratikte kapının dışında
   kalır. Cezanın bitmesi cezanın bitmesi demektir. */
function giris_kayit_gecerli(?array $e, int $simdi): ?array {
    if (!is_array($e)) return null;
    if ($simdi - (int)($e['t'] ?? 0) > GIRIS_PENCERE) return null;
    $kilit = (int)($e['kilit'] ?? 0);
    if ($kilit > 0 && $kilit <= $simdi) return null;
    return $e;
}
/* Kilit sürüyorsa kalan saniye, sürmüyorsa 0; ayrıca o ana dek biriken
   başarısızlık sayısını da döndürür ki gecikme ona göre ayarlansın. */
function giris_durum(string $anahtar): array {
    $d = oku_json('giris-deneme.json', []);
    $simdi = time();
    $e = giris_kayit_gecerli($d[$anahtar] ?? null, $simdi);
    if ($e === null) return [0, 0];
    $kalan = (int)($e['kilit'] ?? 0) - $simdi;
    return [$kalan > 0 ? $kalan : 0, (int)($e['n'] ?? 0)];
}
function giris_basarisiz(string $anahtar): int {
    $d = oku_json('giris-deneme.json', []);
    $simdi = time();
    /* Eskimiş kayıtlar burada temizlenir: dosyanın süresiz büyümesi,
       tuttuğu bilgiden daha büyük bir sorundur. */
    foreach ($d as $k => $v) if ($simdi - (int)($v['t'] ?? 0) > GIRIS_PENCERE) unset($d[$k]);
    $onceki = giris_kayit_gecerli($d[$anahtar] ?? null, $simdi);
    $n = (int)($onceki['n'] ?? 0) + 1;
    $kayit = ['n' => $n, 't' => $simdi];
    if ($n >= GIRIS_ESIK) {
        $kayit['kilit'] = $simdi + GIRIS_KILIT;
        error_log('kutadgu/giris: art arda ' . $n . ' basarisiz deneme, giris ' . GIRIS_KILIT . ' saniye kilitlendi');
    }
    $d[$anahtar] = $kayit;
    if (count($d) > 500) $d = array_slice($d, -300, null, true);
    yaz_json('giris-deneme.json', $d);
    return $n;
}
function giris_basarili(string $anahtar): void {
    $d = oku_json('giris-deneme.json', []);
    if (isset($d[$anahtar])) { unset($d[$anahtar]); yaz_json('giris-deneme.json', $d); }
}

/* ---- TOTP (Google Authenticator) ---- */
function b32_coz(string $s): string {
    $harita = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $s = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $s) ?? '');
    $bit = ''; $out = '';
    for ($i = 0; $i < strlen($s); $i++) {
        $v = strpos($harita, $s[$i]);
        if ($v === false) continue;
        $bit .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    for ($i = 0; $i + 8 <= strlen($bit); $i += 8) $out .= chr((int)bindec(substr($bit, $i, 8)));
    return $out;
}
function b32_yap(string $ham): string {
    $harita = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bit = ''; $out = '';
    for ($i = 0; $i < strlen($ham); $i++) $bit .= str_pad(decbin(ord($ham[$i])), 8, '0', STR_PAD_LEFT);
    for ($i = 0; $i < strlen($bit); $i += 5) $out .= $harita[(int)bindec(str_pad(substr($bit, $i, 5), 5, '0'))];
    return $out;
}
function totp_kod(string $secretB32, int $zaman): string {
    $key = b32_coz($secretB32);
    $sayac = pack('N*', 0) . pack('N*', (int)floor($zaman / 30));
    $h = hash_hmac('sha1', $sayac, $key, true);
    $of = ord($h[19]) & 0xf;
    $kod = ((ord($h[$of]) & 0x7f) << 24 | (ord($h[$of + 1]) & 0xff) << 16 | (ord($h[$of + 2]) & 0xff) << 8 | (ord($h[$of + 3]) & 0xff)) % 1000000;
    return str_pad((string)$kod, 6, '0', STR_PAD_LEFT);
}
function totp_dogru(string $secretB32, string $kod): bool {
    $kod = preg_replace('/\D/', '', $kod) ?? '';
    if (strlen($kod) !== 6) return false;
    $t = time();
    foreach ([-1, 0, 1] as $p) if (hash_equals(totp_kod($secretB32, $t + $p * 30), $kod)) return true;
    return false;
}

/* ---- Güvenilir tarayıcı (TOTP atlama) ---- */
function guven_kontrol(): bool {
    /* Çerez adı 'kutadgu_guven'. Eski ad ('behic_guven') ile verilmiş
       çerezler süreleri dolana kadar okunmayı sürdürür; böylece ad
       değişikliği hiçbir güvenilir tarayıcıyı habersiz düşürmez. */
    $c = (string)($_COOKIE['kutadgu_guven'] ?? ($_COOKIE['behic_guven'] ?? ''));
    if ($c === '') return false;
    $hash = hash('sha256', $c);
    $liste = oku_json('guvenli-tarayici.json', []);
    foreach ($liste as &$e) {
        if (hash_equals((string)($e['hash'] ?? ''), $hash)) {
            if ((time() - (int)($e['t'] ?? 0)) > 180 * 86400) return false; // 180 gün
            $e['son'] = date('c');
            yaz_json('guvenli-tarayici.json', $liste);
            return true;
        }
    }
    return false;
}
function guven_ver(): void {
    $token = bin2hex(random_bytes(32));
    $liste = oku_json('guvenli-tarayici.json', []);
    $liste[] = ['hash' => hash('sha256', $token), 'ua' => mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
        'ip' => ip_al(), 'ad' => '', 't' => time(), 'ilk' => date('c'), 'son' => date('c')];
    if (count($liste) > 20) $liste = array_slice($liste, -15);
    yaz_json('guvenli-tarayici.json', $liste);
    setcookie('kutadgu_guven', $token, ['expires' => time() + 180 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
}

/* ---- Yönetim ayarları (panelden düzenlenir, veri dizininde durur) ----
   Bu dosya bir zamanlar daha eski bir arkayüzün ayarlarını da taşıyordu.
   Hiçbiri Kutadgu'ya ait değildi ve hiçbiri kullanılmıyordu; bir anahtarın
   gereksiz yere bir sunucuda durması kendi başına bir risktir. Geriye
   Kutadgu'nun gerçekten kullandığı iki küme kaldı: Telegram bildirimi ve
   hız sınırı. Eski anahtarlar dosyada duruyorsa okunmaz, ama kendiliğinden
   de silinmez; silmek sistem yöneticisinin elle yapacağı iştir, çünkü
   veriyi habersiz silmek bu sistemde yapılmayacak şeylerden biridir.        */
function yonetim_ayar(): array {
    $vars = [
        /* Telegram bildirimi: destek başvurusu, kefil onayı ve benzeri
           olaylar sistem yöneticisinin Telegram hesabına düşer. Yalnızca
           giden bildirimdir; kurulun e-posta bildirimlerinin yerini tutmaz. */
        'tg'  => ['token' => '', 'chat' => '', 'anahtar' => '', 'son' => 0],
        /* Hız sınırı: IP başına pencere (saniye), pencere içi adet, günlük adet */
        'hiz' => ['pencere' => 300, 'adet' => 18, 'gunluk' => 150],
        /* Posta aktarıcısı. Parola BURADA durur, depoda değil. */
        'smtp' => ['sunucu' => '', 'port' => 587, 'kullanici' => '', 'parola' => '',
                   'guvenlik' => 'tls', 'gonderen' => '', 'gonderen_ad' => '', 'yanit' => ''],
        /* Zenodo jetonu. Parola gibidir ve depoya girmez; sunucudaki bu
           dosyada durur, panelden yazılır, ekrana maskeli döner. */
        'zenodo' => ['jeton' => '', 'acik' => false, 'sandbox' => true, 'topluluk' => ''],
    ];
    $a = oku_json('yonetim-ayar.json', null);
    if (!is_array($a)) { yaz_json('yonetim-ayar.json', $vars); return $vars; }
    foreach ($vars as $k => $v) if (!isset($a[$k])) $a[$k] = $v;
    return $a;
}









/* Kötüye kullanım sayacı (oturum/IP başına, 2 saat pencere) */
function kotu_say(string $anahtar): int {
    $s = oku_json('hiz-sinir.json', []);
    $simdi = time();
    foreach ($s as $k => $v) if ($simdi - (int)($v['t'] ?? 0) > 7200) unset($s[$k]);
    $c = (int)($s[$anahtar]['c'] ?? 0) + 1;
    $s[$anahtar] = ['c' => $c, 't' => $simdi];
    if (count($s) > 2000) $s = array_slice($s, -1000, null, true);
    yaz_json('hiz-sinir.json', $s);
    return $c;
}




/* E-posta gerçek mi: biçim + geçici servis + alan adının posta/DNS kaydı */
function eposta_gecerli(string $e): array {
    $e = trim($e);
    if ($e === '' || mb_strlen($e) > 160) return [false, 'boş ya da çok uzun'];
    if (!filter_var($e, FILTER_VALIDATE_EMAIL)) return [false, 'biçim hatalı (ör. ad@site.com)'];
    $parca = explode('@', $e);
    $domain = strtolower((string)end($parca));
    $tekkullan = ['mailinator.com','guerrillamail.com','guerrillamail.info','10minutemail.com','tempmail.com','temp-mail.org','yopmail.com','throwaway.email','sharklasers.com','getnada.com','trashmail.com','fakeinbox.com','maildrop.cc','dispostable.com','mailnesia.com','mintemail.com','mohmal.com','tempr.email','emailondeck.com','getairmail.com','spam4.me','tmpmail.org','1secmail.com','1secmail.net','moakt.com','tempmailo.com','mailcatch.com','dropmail.me'];
    if (in_array($domain, $tekkullan, true)) return [false, 'geçici/tek kullanımlık e-posta kabul etmiyorum'];
    if (function_exists('checkdnsrr')) {
        $var = @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A');
        if (!$var) return [false, 'bu alan adına ait posta sunucusu bulunamadı'];
    }
    return [true, ''];
}













/* ---- Rota çöz ---- */
$yol = $_SERVER['PATH_INFO'] ?? '';
if ($yol === '' && isset($_SERVER['REQUEST_URI'])) {
    $u = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '';
    $yol = preg_replace('#^.*/api#', '', $u);
}
$yol = '/' . trim($yol, '/');
/* Yönetim uçlarının eski öneki "/ruh" idi ve o ad başka bir siteden,
   eski arkayüzden geliyordu. Eski ön ek BİLEREK yaşatılmadı: iki ayrı
   sistemin aynı adresleri paylaşması, birinde açılan bir gediğin
   ötekinde de açılması demektir. /ruh/... artık yanıt vermez.

   Aynı devirden gelen otuz sekiz uç (sohbet, bilgi tabanı, eğitim
   kayıtları, ziyaret sayacı, eski ana sayfa içeriği, tema görselleri,
   yapay zekâ sağlayıcı ayarları ve eski iletişim formu) önce tek
   kapıda durduruldu, sonra 8 Ağustos 2026'da kodlarıyla birlikte
   çıkarıldı. Erişilemeyen kod bir açık değildi ama devralacak kişiyi
   yanıltıyordu. Kutadgu'nun kendi /begeni, /serh-*, /iletisim ve
   /iletisim-ekle uçlarına dokunulmadı. */

$metod = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ================= ESKİ UÇLAR (panel + klasik sayfa + iletişim) ================= */

if ($yol === '/login' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $k = trim((string)($g['kullanici'] ?? '')); $p = (string)($g['parola'] ?? '');

    /* Kilit denetimi parolaya bakmadan önce gelir: kilitliyken parola
       doğrulamak, kilidin ölçmek istediği şeyi ölçtürmeye devam eder. */
    $dAnahtar = giris_anahtar($k);
    [$kilit, $sayi] = giris_durum($dAnahtar);
    if ($kilit > 0) {
        $dk = (int)ceil($kilit / 60);
        cikti(['ok' => false,
            'hata' => 'Art arda çok sayıda başarısız deneme oldu; giriş geçici olarak kapalı. Yaklaşık ' . $dk . ' dakika sonra yeniden deneyin.',
            'hata_en' => 'Too many failed attempts in a row; sign-in is temporarily closed. Try again in about ' . $dk . ' minute(s).'], 429);
    }

    /* ---- KARA LİSTE, HER ŞEYDEN ÖNCE ----
       Denetim auth.json okunmadan yapılır: dosyada ne yazdığının önemi
       yok, sızmış bir kimlik hiçbir koşulda kabul edilmez. Böylece
       dosya o karmayı taşısa da, taşımasa da sonuç aynıdır.

       SAYAÇ ARTMAZ. Bu bilerek böyledir: sistemin sahibinin kendi eski
       parolasını denemesi çok olası bir şeydir ve onu kendi kapısında
       beş dakika kilitli bırakmak, hiçbir şeyi korumadan yalnızca
       zarar verirdi. Denemenin karşılığı zaten kesin bir rettir;
       tahmin edilecek bir şey kalmadığı için sayılacak bir deneme de
       yoktur. */
    if (kara_listede_mi($p)) kara_liste_cikti();

    $auth = auth_al();
    /* Kimlik kurulmadan gecikme uygulanmaz: bekletilecek bir doğrulama yok. */
    if ($auth === null) auth_yok_cikti();

    /* Sabit gecikme yerine biriken gecikme; üst sınır insanı da düşünerek 2 sn. */
    usleep((int)min(300000 + $sayi * 300000, 2000000));

    /* Kullanıcı adı dosyada yoksa genel yönetici adı varsayılır. Koda bir
       kişinin adı yazılmaz; eski dosyalarda ad zaten kayıtlıdır. */
    $kBek = trim((string)($auth['kullanici'] ?? ''));
    if ($kBek === '') $kBek = 'yonetici';
    if (hash_equals($kBek, $k) && password_verify($p, (string)$auth['hash'])) {
        /* TOTP açıksa ve tarayıcı güvenilir değilse kod iste. Kod istemek
           bir başarısızlık değildir; sayaca yalnızca YANLIŞ kod işlenir,
           yoksa iki adımlı giriş kişinin kendini kilitlemesine dönüşür. */
        if (!empty($auth['totp']) && !guven_kontrol()) {
            $kod = (string)($g['kod'] ?? '');
            if ($kod === '') cikti(['ok' => false, 'totp' => true,
                'hata' => 'Doğrulama kodu gerekli (Google Authenticator).',
                'hata_en' => 'A verification code is required (Google Authenticator).'], 401);
            if (!totp_dogru((string)$auth['totp'], $kod)) {
                giris_basarisiz($dAnahtar);
                cikti(['ok' => false, 'totp' => true,
                    'hata' => 'Kod hatalı ya da süresi geçti.',
                    'hata_en' => 'The code is wrong or has expired.'], 401);
            }
            if (!empty($g['guven'])) guven_ver(); // "bu tarayıcıya güven"
        }
        /* Sayaç yalnızca giriş bütünüyle tamamlandığında sıfırlanır. */
        giris_basarili($dAnahtar);
        session_regenerate_id(true);
        $_SESSION['kutadgu_yonetim'] = true;
        /* Oturumun sürümü: girisli() bunu arar. Bu satır olmadan açılan
           bir oturum yönetim yetkisi taşımaz; gerekçesi girisli()
           işlevinin üstündedir. */
        $_SESSION['kutadgu_yonetim_surum'] = YONETIM_OTURUM_SURUM;
        /* son giriş eşiği: bir önceki girişin zamanı (yeni yazışmaları göstermek için) */
        $gj = oku_json('panel_giris.json', []);
        $_SESSION['esik'] = (int)($gj['son'] ?? 0);
        yaz_json('panel_giris.json', ['son' => time()]);
        cikti(['ok' => true]);
    }
    /* Kullanıcı adı mı parola mı yanlış, ayrımı bilerek verilmez. */
    giris_basarisiz($dAnahtar);
    cikti(['ok' => false,
        'hata' => 'Kullanıcı adı veya parola hatalı.',
        'hata_en' => 'The user name or password is incorrect.'], 401);
}

/* TOTP kurulum/kapatma + güvenilir tarayıcılar (yetki) */
if ($yol === '/totp-kur' && $metod === 'POST') {
    yetki_gerek();
    $secret = b32_yap(random_bytes(20));
    $_SESSION['totp_aday'] = $secret;
    cikti(['ok' => true, 'secret' => $secret,
        'uri' => 'otpauth://totp/Kutadgu:yonetim?secret=' . $secret . '&issuer=Kutadgu&digits=6&period=30']);
}
if ($yol === '/totp-onayla' && $metod === 'POST') {
    yetki_gerek();
    $g = govde_json();
    $aday = (string)($_SESSION['totp_aday'] ?? '');
    if ($aday === '') cikti(['ok' => false, 'hata' => 'Önce kurulum başlat.'], 400);
    if (!totp_dogru($aday, (string)($g['kod'] ?? ''))) cikti(['ok' => false, 'hata' => 'Kod hatalı; uygulamadaki güncel kodu gir.', 'hata_en' => 'Wrong code; enter the current code from the application.'], 400);
    $auth = auth_gerek();
    $auth['totp'] = $aday;
    yaz_json('auth.json', $auth);
    unset($_SESSION['totp_aday']);
    if (!empty($g['guven'])) guven_ver();
    cikti(['ok' => true]);
}
if ($yol === '/totp-kapat' && $metod === 'POST') {
    yetki_gerek();
    $g = govde_json();
    $auth = auth_gerek();
    if (!password_verify((string)($g['parola'] ?? ''), (string)$auth['hash'])) cikti(['ok' => false, 'hata' => 'Parola yanlış.', 'hata_en' => 'Wrong password.'], 400);
    unset($auth['totp']);
    yaz_json('auth.json', $auth);
    cikti(['ok' => true]);
}
if ($yol === '/guven-liste' && $metod === 'GET') {
    yetki_gerek();
    $liste = array_map(function ($e) { unset($e['hash']); return $e; }, oku_json('guvenli-tarayici.json', []));
    /* Kimlik kurulu değilse liste boş bir kimlikle okunmaya çalışılmaz. */
    $au = auth_al();
    cikti(['ok' => true, 'liste' => $liste, 'totp_acik' => $au !== null && !empty($au['totp'])]);
}
if ($yol === '/guven-sil' && $metod === 'POST') {
    yetki_gerek();
    $g = govde_json();
    $ilk = (string)($g['ilk'] ?? '');
    yaz_json('guvenli-tarayici.json', array_values(array_filter(oku_json('guvenli-tarayici.json', []), fn($e) => ($e['ilk'] ?? '') !== $ilk)));
    cikti(['ok' => true]);
}

/* Yönetici çıkışı ile hesap çıkışı AYNI işi yapar ve aynı yerden yapar.
   İki ayrı yıkım kodu olsaydı biri düzeltilir öteki unutulurdu; kapının
   yarısı kapanırdı. (Tanım aşağıda, hs_cikis; PHP üst düzey işlevleri
   dosyanın başında tanır.) */
if ($yol === '/cikis' && $metod === 'POST') { hs_cikis(); cikti(['ok' => true]); }

if ($yol === '/durum') { cikti(['ok' => true, 'girisli' => girisli()]); }

if ($yol === '/parola' && $metod === 'POST') {
    yetki_gerek();
    $g = govde_json();
    $eski = (string)($g['eski'] ?? ''); $yeni = (string)($g['yeni'] ?? '');
    $auth = auth_gerek();
    if (!password_verify($eski, (string)$auth['hash'])) cikti(['ok' => false, 'hata' => 'Mevcut parola yanlış.', 'hata_en' => 'The current password is wrong.'], 400);
    /* Alt sınır 8'de bırakıldı: burası parolasını bilen kişinin kendi
       parolasını değiştirdiği yerdir, ilk kurulum değil. İlk kurulumun
       sınırı (12) daha yüksektir, çünkü orada henüz kimse doğrulanmamıştır. */
    if (mb_strlen($yeni) < 8) cikti(['ok' => false, 'hata' => 'Yeni parola en az 8 karakter olmalı.', 'hata_en' => 'The new password must be at least 8 characters.'], 400);
    /* Kara listedeki bir değer buradan da geri kurulamaz. Aksi hâlde
       kapının kapanması tek bir işleme, üstelik yanlışlıkla yapılabilen
       bir işleme bağlı kalırdı. */
    if (kara_listede_mi($yeni)) cikti(['ok' => false,
        'hata' => 'Bu parola sızmış kimlikler listesinde; kurulamaz. Başka bir parola seçin.',
        'hata_en' => 'This password is on the list of leaked credentials and cannot be set. Choose a different one.'], 400);
    $auth['hash'] = password_hash($yeni, PASSWORD_DEFAULT);
    yaz_json('auth.json', $auth);
    cikti(['ok' => true]);
}

/* E-posta doğrulama (herkese açık; sohbet iletişim akışı kullanır) */
if ($yol === '/eposta-dogrula' && $metod === 'POST') {
    $g = govde_json();
    [$ok, $hata] = eposta_gecerli((string)($g['eposta'] ?? ''));
    cikti(['ok' => $ok, 'hata' => $hata]);
}








/* IndexNow: içerik değişince Bing/Yandex/DuckDuckGo/Seznam'a anında bildir.
   Tek endpoint'e ping tüm katılımcı motorlara dağıtılır. Anahtar: /<key>.txt */
/* Anahtar yalnızca ayardan gelir. Koda gömülü yedek kaldırıldı: o anahtar
   başka bir siteden devralınmıştı ve depoya girdiği anda o sitenin adına
   bildirim yapılabilir hale geliyordu. Ayar boşsa bildirim yapılmaz; arama
   motoruna haber verememek bir arıza değil, geciken bir dizinlemedir. */
function indexnow_anahtar(): string {
    /* Veri dizinindeki yonetim-ayar.json'dan okunur; ayar.php'deki
       yuva boştur ve boş kalmalıdır (depo herkese açık). indexnow.php
       de aynı işlevden okur: bildirimle doğrulama tek kaynaktan gelir,
       biri yazılıp öteki unutulamaz. */
    return trim((string)tg_canli_ayar('indexnow_anahtar', ''));
}
/* Arama motorlarına anında bildirim: IndexNow (Bing, Yandex, Naver, Seznam) */
function indexnow_bildir(array $urls): void {
    $urls = array_values(array_unique(array_filter($urls)));
    if (!$urls || !function_exists('curl_init')) return;
    $kok  = function_exists('tg_kok') ? tg_kok() : '';
    $host = parse_url($kok, PHP_URL_HOST);
    if (!$host) return;
    $key  = indexnow_anahtar();
    if ($key === '') return;   /* ayar boşsa sessizce atla; hata değil */
    $govde = json_encode([
        'host' => $host,
        'key' => $key,
        'keyLocation' => rtrim($kok, '/') . '/' . $key . '.txt',
        'urlList' => array_slice($urls, 0, 100),
    ]);
    $ch = curl_init('https://api.indexnow.org/indexnow');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $govde,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 4,
    ]);
    curl_exec($ch); curl_close($ch);
}
/* Site haritasının yenilendiğini arama motorlarına duyur */
function sitemap_bildir(): void {
    if (!function_exists('curl_init')) return;
    $kok = function_exists('tg_kok') ? tg_kok() : '';
    if ($kok === '') return;
    $sm = rawurlencode(rtrim($kok, '/') . '/sitemap.xml');
    foreach (['https://www.bing.com/ping?sitemap=' . $sm, 'https://webmaster.yandex.com/ping?sitemap=' . $sm] as $u) {
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_NOBODY => true]);
        curl_exec($ch); curl_close($ch);
    }
}
/* Belirli bir çalışma yayımlandı ya da değişti: adresiyle birlikte bildir */
function indexnow_yazi(string $slug, string $bcid = ''): void {
    $kok = function_exists('tg_kok') ? rtrim(tg_kok(), '/') : '';
    if ($kok === '') return;
    $u = [$kok . '/', $kok . '/yazilar.php'];
    if ($slug !== '') {
        $u[] = $kok . '/yazi.php?y=' . rawurlencode($slug);
        $u[] = $kok . '/yazi.php?y=' . rawurlencode($slug) . '&lang=en';
    }
    if ($bcid !== '') $u[] = $kok . '/' . trim((string)tg_ayar('tamga_yol', 'tamga'), '/') . '/' . $bcid;
    indexnow_bildir($u);
    sitemap_bildir();
}



if ($yol === '/foto-yukle' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $izin = ['hero1.jpg','hero2.jpg','hero3.jpg','about-side.jpg','skills-side.jpg','book.jpg','work-derspro.jpg','work-probina.jpg','work-kasis.jpg','work-pubs.jpg','work-consult.jpg'];
    $hedef = (string)($_POST['hedef'] ?? '');
    if (!in_array($hedef, $izin, true)) cikti(['ok'=>false,'hata'=>'Gecersiz hedef.'],400);
    if (empty($_FILES['dosya']) || ($_FILES['dosya']['error'] ?? 1) !== 0) cikti(['ok'=>false,'hata'=>'Dosya alinamadi.'],400);
    if ((int)($_FILES['dosya']['size'] ?? 0) > 8*1024*1024) cikti(['ok'=>false,'hata'=>'En fazla 8 MB.'],400);
    $tmp = $_FILES['dosya']['tmp_name'];
    $bilgi = @getimagesize($tmp);
    if (!$bilgi || !in_array($bilgi[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) cikti(['ok'=>false,'hata'=>'Sadece JPG, PNG veya WebP.'],400);
    $klasor = __DIR__ . '/../photo/site';
    if (!is_dir($klasor)) cikti(['ok'=>false,'hata'=>'Foto klasoru yok.'],500);
    $yeni = $klasor . '/' . $hedef;
    if (is_file($yeni)) @copy($yeni, $yeni . '.bak');
    if (!@move_uploaded_file($tmp, $yeni)) { if (!@copy($tmp, $yeni)) cikti(['ok'=>false,'hata'=>'Kaydedilemedi.'],500); }
    @chmod($yeni, 0644);
    cikti(['ok'=>true,'hedef'=>$hedef]);
}


/* YAZILARIM (herkese açık liste) */
if ($yol === '/yazilar' && $metod === 'GET') {
    $y = oku_json('yazilar.json', []);
    if (!is_array($y)) $y = [];
    usort($y, fn($a, $b) => strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? '')));
    /* Okunma sayıları */
    $oku = oku_json('yazi-oku.json', []); if (!is_array($oku)) $oku = [];
    /* Genel listede hakem token/e-posta hash'i sızmasın: sadece ad + karar + rapor kalsın */
    foreach ($y as $i => $e) {
        if (isset($e['hakemler']) && is_array($e['hakemler'])) {
            $y[$i]['hakemler'] = array_map(fn($h) => is_array($h) ? [
                'ad' => (string)($h['ad'] ?? ''), 'karar' => (string)($h['karar'] ?? ''),
                'rapor' => (string)($h['rapor'] ?? ''), 'tarih' => (string)($h['tarih'] ?? ''),
                'dosya' => (string)($h['dosya'] ?? ''),
                'profil' => is_array($h['profil'] ?? null) ? [
                    'unvan' => (string)($h['profil']['unvan'] ?? ''), 'kurum' => (string)($h['profil']['kurum'] ?? ''),
                    'orcid' => (string)($h['profil']['orcid'] ?? ''), 'eposta' => (string)($h['profil']['eposta'] ?? ''),
                    'web' => (string)($h['profil']['web'] ?? ''),
                ] : [],
                'endeks' => is_array($h['endeks'] ?? null) ? array_values($h['endeks']) : [],
                'notlar' => is_array($h['notlar'] ?? null) ? array_values($h['notlar']) : [],
                /* Diyalog herkese açıktır. Kapalı bir kanal, kapalı
                   hakemliğe yöneltilen eleştirinin aynısını bu sisteme
                   taşırdı; yazışma da raporun kendisi gibi kayıttır. */
                'diyalog' => tg_diyalog($h),
                'raporlar' => is_array($h['raporlar'] ?? null) ? array_map(fn($r) => is_array($r) ? [
                    'karar' => (string)($r['karar'] ?? ''), 'rapor' => (string)($r['rapor'] ?? ''),
                    'tarih' => (string)($r['tarih'] ?? ''), 'dosya' => (string)($r['dosya'] ?? ''),
                    'endeks' => is_array($r['endeks'] ?? null) ? array_values($r['endeks']) : [],
                    'notlar' => is_array($r['notlar'] ?? null) ? array_values($r['notlar']) : [],
                ] : [], $h['raporlar']) : [],
            ] : $h, $e['hakemler']);
        }
        /* Yazar erişim jetonu/şifresi kesinlikle sızmasın */
        unset($y[$i]['yazar_erisim']);
        /* Önerilen hakemler ve intihal raporunun özel bağlantısı yalnızca editörde kalır.
           Benzerlik oranı, yapay zekâ beyanı ve ek kaynaklar şeffaflık gereği açıktır. */
        unset($y[$i]['hakem_oneri']);
        if (isset($y[$i]['intihal']) && is_array($y[$i]['intihal'])) unset($y[$i]['intihal']['link']);
        $o = $oku[(string)($e['id'] ?? '')] ?? null;
        $y[$i]['oku_toplam'] = is_array($o) ? (int)($o['toplam'] ?? 0) : 0;
        $y[$i]['oku_tekil'] = is_array($o) ? count($o['tekil'] ?? []) : 0;
        /* HAKEMLİK AŞAMASI
           'tur' çalışmanın hangi YOLDA olduğunu söyler, o yolun neresinde
           olduğunu değil: yazar hakemliğe açtığı anda tur='hakemli' olur ve
           tek bir rapor bile gelmemiştir. Bu ucu tüketen taraf bugüne kadar
           "hakemli mi" sorusunu 'tur'dan yanıtlıyordu, yani hakem aranan bir
           çalışmayı da hakem değerlendirmesinden geçmiş gibi gösteriyordu.
           Aşama artık kayıtla birlikte dışarı verilir. 'tur' geriye uyum
           için yerinde kalır; kaldırılmaz, yalnızca yanına doğrusu konur. */
        $y[$i]['asama'] = tg_hakem_asamasi($e);
        $y[$i]['rapor_sayisi'] = tg_rapor_sayisi($e);
        $y[$i]['hakemden_gecti'] = tg_hakemden_gecti($e);
    }
    cikti(['ok' => true, 'yazilar' => tire_temizle($y)]);
}

/* =====================================================================
   PARMAK İZİ DOĞRULAMA UCU  (herkese açık)
   GET /api/parmak?tamga=KTG-2026-00001-4
   ---------------------------------------------------------------------
   Sunucunun "bu metin değişmedi" demesi hiçbir şey göstermez: aynı
   cümleyi metni değiştirmiş bir sunucu da kurar. Bu uç o cümleyi hiç
   kurmaz. Yaptığı tek iş, özeti alınan HAM KAYNAĞI vermektir; hesabı
   okurun tarayıcısı yapar. Okur böylece üç şeyi birden görebilir:
     - kaynağın içeriğini gözüyle sayfadaki metinle karşılaştırabilir,
     - SHA-256'yı kendisi hesaplayıp yayımlanan değerle eşleyebilir,
     - aynı değeri arşiv dökümündeki listede arayabilir. O liste bir
       başkasının elinde aylar önce indirilmiş olabilir; bu ucun tek
       başına veremeyeceği güvence oradan gelir.

   'kaynak' alanları HAM verilir: kırpma, boşluk temizliği, HTML kaçırma
   yoktur. Özeti alınan dizge neyse okura giden de odur. Burada yapılacak
   en küçük "temizlik" hesabı bozar ve doğru bir metni yanlış gösterir.

   Karma yöntemi yanıtın içinde yazılıdır ('yontem'). Sebep: okur aynı
   hesabı bizim yazılımımızdan bağımsız olarak, kendi bilgisayarında
   tekrarlayabilsin. Yöntemi yalnızca kodun içinde bırakmak, okurdan
   koda güvenmesini istemek olurdu.

   Oturum istenmez ve istenemez: arşive onay kapısı konulamaz (tüzük
   taslağı Ek A Madde 2.5), doğrulama da arşivin bir parçasıdır. Hız
   sınırı vardır ama bir reddetme değildir; 429 yanıtı ne zaman
   gelineceğini yazar ve o an gelindiğinde aynı kayıt koşulsuz verilir.

   Geri çekilmiş çalışma da doğrulanabilir. Kayıt silinmez: geri çekilmiş
   bir metnin ne olduğunun denetlenebilmesi, yayındayken
   denetlenebilmesinden daha az önemli değildir.
   ===================================================================== */
if ($yol === '/parmak' && $metod === 'GET') {
    /* Sınır yalnızca geciktirir. Pencere bilerek kısa tutuldu: en uzun
       bekleme on dakikadır. Aynı tavan bir saatlik pencereye yazılsaydı
       bekleyen kişi için reddedilmekten ayırt edilemezdi. */
    if (function_exists('dk_hiz_sinir')) {
        $ps = dk_hiz_sinir('parmak', 120, 600);
        if (!$ps['gecer']) {
            header('Retry-After: ' . (int)$ps['bekle']);
            cikti(['ok' => false,
                'hata' => 'Çok sık istek. Bu bir reddetme değildir: ' . (int)$ps['bekle'] . ' saniye sonra aynı kayıt koşulsuz verilir.',
                'hata_en' => 'Too many requests. This is not a refusal: the same record is served without any condition in ' . (int)$ps['bekle'] . ' seconds.',
                'bekle' => (int)$ps['bekle']], 429);
        }
    }
    /* Okuma başka kökenlere de açıktır: doğrulamayı bizim sayfamızdan
       değil, kendi aracından yapmak isteyen biri de aynı kaydı
       alabilmelidir. Uç yalnızca zaten yayımlanmış olanı verir ve
       oturuma hiç bakmaz; açmanın bir bedeli yoktur. */
    header('Access-Control-Allow-Origin: *');

    $pKod = trim((string)($_GET['tamga'] ?? ''));
    $py = oku_json('yazilar.json', []); if (!is_array($py)) $py = [];
    $pBul = null;
    foreach ($py as $pe) {
        /* Eski kodlarla (bc.000001 gibi) gelen istek de bulunur:
           paylaşılmış hiçbir kimlik doğrulama dışında kalmasın. */
        if (is_array($pe) && $pKod !== '' && tg_kod_esles($pe, $pKod)) { $pBul = $pe; break; }
    }
    if ($pBul === null) {
        cikti(['ok' => false, 'tamga' => $pKod,
            'hata' => 'Bu kimlikle bir çalışma bulunamadı.',
            'hata_en' => 'No work was found with this identifier.'], 404);
    }
    cikti([
        'ok' => true,
        'tamga' => (string)($pBul['bcid'] ?? $pKod),
        'parmak_izi' => tg_metin_ozeti($pBul),
        /* Yöntem cümlesi tg_metin_ozeti()'nin yaptığı işin birebir
           tarifidir. İkisi ayrı düşerse okur yanlış hesap yapar; bu
           satır değiştirilecekse ortak.php'deki karma da değişmelidir. */
        'yontem' => 'sha256(baslik + LF + ozet + LF + metin + LF + kaynakca), UTF-8, ilk 32 hane',
        'kaynak' => [
            'baslik'   => (string)($pBul['baslik'] ?? ''),
            'ozet'     => (string)($pBul['ozet'] ?? ''),
            'metin'    => (string)($pBul['metin'] ?? ''),
            'kaynakca' => (string)($pBul['kaynakca'] ?? ''),
        ],
        /* Asıl güvence burada: aynı parmak izi arşiv dökümünde de
           yayımlanır ve o dosya başkalarının elinde bulunabilir. */
        'dokum' => (function_exists('tg_url') ? tg_url('/dokum.php') : '/dokum.php'),
        /* Yukarıdaki değer TABAN alanların özetidir. İngilizce sayfa
           çeviriyi basar; o metnin özeti başkadır ve ayrıca verilir,
           yoksa okur okumadığı bir metni doğrulamış olur. Çevirisi
           olmayan çalışmada alan hiç basılmaz: eşit iki sayı okura
           seçmesi gereken bir şey varmış gibi görünürdü. */
        'ceviri' => (function () use ($pBul) {
            $co = tg_ceviri_ozeti($pBul);
            if ($co === '') return null;
            return [
                'dil' => 'en',
                'parmak_izi' => $co,
                'kaynak' => [
                    'baslik'   => tg_dil_alani($pBul, 'baslik', true),
                    'ozet'     => tg_dil_alani($pBul, 'ozet', true),
                    'metin'    => tg_dil_alani($pBul, 'metin', true),
                    'kaynakca' => tg_dil_alani($pBul, 'kaynakca', true),
                ],
            ];
        })(),
    ]);
}

/* Basit slug (Türkçe -> ascii) */
function yazi_slug(string $s): string {
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['ç'=>'c','ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','â'=>'a','î'=>'i','û'=>'u']);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim((string)$s, '-');
    return $s !== '' ? mb_substr($s, 0, 80) : 'yazi';
}
/* Hakem erişim şifresi: okunaklı, karışabilen karakter yok (ör. K7P-3RM) */
function uret_sifre(): string {
    $a = 'ABCDEFGHJKLMNPRSTUVYZ23456789';
    $s = '';
    for ($i = 0; $i < 6; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return substr($s, 0, 3) . '-' . substr($s, 3, 3);
}
/* YAZI kaydet/güncelle (yönetici) - dergi/makale şeması */
if ($yol === '/yonetim/yazi-kaydet' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $id = (string)($g['id'] ?? '');
    $temizle = fn($h) => trim(preg_replace('#<script\b[^>]*>.*?</script>#is', '', (string)$h));
    $baslik = mb_substr(trim((string)($g['baslik'] ?? '')), 0, 220);
    $slug = yazi_slug(((string)($g['slug'] ?? '')) !== '' ? (string)$g['slug'] : $baslik);
    /* Mevcut kaydın hakem değerlendirmelerini (token/rapor/karar) koru */
    $eskiHak = [];
    foreach ($y as $e) { if (($e['id'] ?? '') === $id && is_array($e['hakemler'] ?? null)) { foreach ($e['hakemler'] as $eh) { if (is_array($eh) && ($eh['ad'] ?? '') !== '') $eskiHak[mb_strtolower($eh['ad'], 'UTF-8')] = $eh; } } }
    /* Atamayı yapan: panelden gelirse editörün adı, gelmezse sistem yönetimi.
       Bir hakemin atama kaydı bir kez yazılır, sonra korunur. */
    $atayanTurG = (string)($g['atayan_tur'] ?? '');
    $atayanTur  = in_array($atayanTurG, ['editor', 'bas_editor', 'yonetim'], true) ? $atayanTurG : 'yonetim';
    $atayanAd   = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['atayan_ad'] ?? ''))), 0, 120);
    if ($atayanAd === '') $atayanAd = (string)tg_ayar('yonetici_ad', 'Yayın Yönetimi');
    $hakemler = [];
    if (isset($g['hakemler']) && is_array($g['hakemler'])) {
        foreach ($g['hakemler'] as $hk) {
            $ad = is_array($hk) ? (string)($hk['ad'] ?? '') : (string)$hk;
            $ad = mb_substr(trim($ad), 0, 120); if ($ad === '') continue;
            $eposta = is_array($hk) ? mb_strtolower(trim((string)($hk['eposta'] ?? '')), 'UTF-8') : '';
            $m = $eskiHak[mb_strtolower($ad, 'UTF-8')] ?? [];
            /* ---- ESKİ KAYIT OLDUĞU GİBİ KORUNUR ----
               Burası bir zamanlar sayılı alanı adıyla taşıyan bir liste
               yazıyordu. Listede olmayan her alan, listeyi yazan kişinin
               aklına gelmediği için değil, o alan sonradan eklendiği
               için düşüyordu: davet_tarih, davet_durum, davet_token,
               endeks, sifat, yetkinlik, anket. Bir hakem daveti
               yapıldığında aynı çalışmadaki ÖTEKİ hakemlerin bu
               kayıtları sessizce siliniyordu; rapor duruyor, davetin ne
               zaman yapıldığı gidiyordu.

               Bu yüzden artık kaydın tamamı alınır ve yalnızca değişen
               alanlar üstüne yazılır. Yarın hakem kaydına yeni bir alan
               eklendiğinde burayı kimsenin güncellemesi gerekmez; bir
               kayıt, onu tanımayan bir kodun elinde eksilmemelidir.

               The old record is preserved whole and only the changed
               fields are written over it. A record must not shrink in
               the hands of code that does not know all of its fields. */
            $yeni = $m;
            $yeni['ad'] = $ad;
            if ((string)($yeni['token'] ?? '') === '')
                $yeni['token'] = substr(hash('sha256', $ad . microtime() . mt_rand() . random_int(0, PHP_INT_MAX)), 0, 32);
            if ($eposta !== '') $yeni['eposta_hash'] = hash('sha256', $eposta . '|hakem');
            elseif (!isset($yeni['eposta_hash'])) $yeni['eposta_hash'] = '';
            if ((string)($yeni['sifre'] ?? '') === '') $yeni['sifre'] = uret_sifre();
            foreach (['karar', 'rapor', 'tarih', 'dosya'] as $bos)
                if (!isset($yeni[$bos])) $yeni[$bos] = '';
            if (!is_array($yeni['profil'] ?? null)) $yeni['profil'] = [];
            if (!is_array($yeni['raporlar'] ?? null)) $yeni['raporlar'] = [];
            /* Atamayı kim yaptı: okuyucuya gösterilir, sonradan değişmez */
            if (!(is_array($m['atayan'] ?? null) && ($m['atayan']['tur'] ?? '') !== ''))
                $yeni['atayan'] = ['tur' => $atayanTur, 'ad' => $atayanAd, 'tarih' => date('c')];
            $hakemler[] = $yeni;
        }
    } else {
        $hakemler = array_values($eskiHak); // editör hakem göndermediyse mevcutları koru
    }
    /* Mevcut kayıt (varsa) */
    $mevcut = null;
    foreach ($y as $e) { if (($e['id'] ?? '') === $id) { $mevcut = $e; break; } }
    /* DOI benzeri kimlik (bc.000001): bir kez atanır, korunur; yenide sıradaki numara */
    $bcid = (string)($mevcut['bcid'] ?? '');
    if ($bcid === '') {
        $bcid = function_exists('tg_sonraki_kod') ? tg_sonraki_kod($y) : ('bc.' . str_pad((string)(count($y) + 1), 6, '0', STR_PAD_LEFT));
    }
    /* Yama modu: yalnızca gönderilen alanlar güncellenir, gerisi korunur (ör. yalnız İngilizce ekleme) */
    $yama = !empty($g['yama']) && $mevcut !== null;
    /* Alan seçici: yama modunda alan JSON'da yoksa mevcut değeri koru */
    $al = function($k, $yeni, $vars = '') use ($g, $yama, $mevcut) {
        if ($yama && !array_key_exists($k, $g)) return $mevcut[$k] ?? $vars;
        return $yeni;
    };
    $kayit = [
        'id' => $id !== '' ? $id : substr(hash('sha256', microtime() . mt_rand()), 0, 10),
        'slug' => $yama ? (string)($mevcut['slug'] ?? $slug) : $slug,
        /* KARAR: olduğu gibi bırakıldı. Burada YOL yazılıyor: yöneticinin
           gönderdiği değer iki geçerli yoldan birine indirgeniyor. Aşama
           hesaplanan bir değerdir, kayda yazılmaz; yazılsaydı rapor geldiğinde
           güncellenmeyi unutabilirdi. */
        'tur' => $al('tur', (($g['tur'] ?? '') === 'hakemli') ? 'hakemli' : 'yazi', 'yazi'),
        /* Çalışmanın dili: yönetici kaydında da tutulur ki eski kayıtlar
           elle işaretlenebilsin. Geçersiz kod boşa düşer. */
        'dil' => $al('dil', tg_dil_kodu($g['dil'] ?? ''), ''),
        /* Gönderim tarihi. Kayıtta ayrı durur çünkü hesaplanamaz: bir
           çalışmanın sisteme ne zaman verildiği, hakem atamasından da
           yayından da önceki bir olaydır. Bilinmiyorsa boş kalır ve
           hiçbir yerde yazılmaz; uydurulmuş bir tarih, olmayan bir
           tarihten kötüdür. */
        'gonderim' => $al('gonderim', preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($g['gonderim'] ?? '')) ? (string)$g['gonderim'] : '', ''),
        'baslik' => $al('baslik', $baslik),
        'baslik_en' => $al('baslik_en', mb_substr(trim((string)($g['baslik_en'] ?? '')), 0, 220)),
        'yazar' => $al('yazar', mb_substr(trim((string)($g['yazar'] ?? '')), 0, 160) ?: (string)tg_ayar('varsayilan_yazar', ''), (string)tg_ayar('varsayilan_yazar', '')),
        'yazar_bilgi' => (function() use ($g, $yama, $mevcut) {
            if ($yama && !array_key_exists('yazar_bilgi', $g)) return $mevcut['yazar_bilgi'] ?? [];
            $b = $g['yazar_bilgi'] ?? null;
            if (!is_array($b)) return $mevcut['yazar_bilgi'] ?? [];
            $k = fn($x, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($b[$x] ?? ''))), 0, $n);
            $o = orcid_temiz($k('orcid', 60)); $sc = scopus_temiz($k('scopus', 200));
            return ['unvan' => $k('unvan', 40), 'ad' => $k('ad', 120), 'kurum' => $k('kurum', 220), 'eposta' => $k('eposta', 120), 'orcid' => $o, 'scopus' => $sc, 'web' => $k('web', 200)];
        })(),
        /* Yazar listesi (ikinci, üçüncü... yazarlar). ORCID/Scopus biçimlenir; yönetici için engelleyici değildir. */
        'yazar_liste' => (function() use ($g, $yama, $mevcut) {
            $anahtar = array_key_exists('yazarlar', $g) ? 'yazarlar' : (array_key_exists('yazar_liste', $g) ? 'yazar_liste' : '');
            if ($anahtar === '') return $mevcut['yazar_liste'] ?? [];
            $yl = $g[$anahtar];
            if (is_string($yl)) $yl = json_decode($yl, true);
            if (!is_array($yl)) return $mevcut['yazar_liste'] ?? [];
            $liste = [];
            foreach ($yl as $ya) {
                if (!is_array($ya)) continue;
                $t = fn($x, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($ya[$x] ?? ''))), 0, $n);
                $ad = $t('ad', 120); if ($ad === '') continue;
                $liste[] = ['unvan' => $t('unvan', 60), 'ad' => $ad, 'kurum' => $t('kurum', 220),
                            'orcid' => orcid_temiz($t('orcid', 60)), 'scopus' => scopus_temiz($t('scopus', 200))];
                if (count($liste) >= 15) break;
            }
            return $liste;
        })(),
        'etik' => (function() use ($g, $yama, $mevcut) {
            $esk = is_array($mevcut['etik'] ?? null) ? $mevcut['etik'] : ['durum'=>'','belge'=>'','no'=>'','onay'=>false,'onay_tarih'=>''];
            if ($yama && !array_key_exists('etik', $g)) return $esk;
            $e = is_array($g['etik'] ?? null) ? $g['etik'] : [];
            $t = fn($x,$n) => mb_substr(trim(preg_replace('#<[^>]*>#','',(string)($e[$x] ?? ''))), 0, $n);
            $d = preg_replace('/[^a-z]/','', strtolower((string)($e['durum'] ?? '')));
            if (!in_array($d, ['gerekli','gereksiz'], true)) $d = (string)($esk['durum'] ?? '');
            return ['durum' => $d,
                    'belge' => $t('belge', 500) !== '' ? $t('belge', 500) : (string)($esk['belge'] ?? ''),
                    'no'    => $t('no', 120)    !== '' ? $t('no', 120)    : (string)($esk['no'] ?? ''),
                    /* Onay yalnızca yönetici tarafından verilir; yazar kendi kendine onaylayamaz. */
                    'onay'  => (bool)($esk['onay'] ?? false),
                    'onay_tarih' => (string)($esk['onay_tarih'] ?? '')];
        })(),
        'veri' => (function() use ($g, $yama, $mevcut) {
            $esk = is_array($mevcut['veri'] ?? null) ? $mevcut['veri'] : ['beyan'=>'','url'=>'','gerekce'=>''];
            if ($yama && !array_key_exists('veri', $g)) return $esk;
            $v = is_array($g['veri'] ?? null) ? $g['veri'] : [];
            $t = fn($x,$n) => mb_substr(trim(preg_replace('#<[^>]*>#','',(string)($v[$x] ?? ''))), 0, $n);
            $d = preg_replace('/[^a-z]/','', strtolower((string)($v['beyan'] ?? '')));
            if (!in_array($d, ['acik','istek','kisitli','yok'], true)) $d = (string)($esk['beyan'] ?? '');
            return ['beyan' => $d,
                    'url'     => $t('url', 500)     !== '' ? $t('url', 500)     : (string)($esk['url'] ?? ''),
                    'gerekce' => $t('gerekce', 800) !== '' ? $t('gerekce', 800) : (string)($esk['gerekce'] ?? '')];
        })(),
        'kayitlar' => is_array($mevcut['kayitlar'] ?? null) ? $mevcut['kayitlar'] : [],
        'alan' => $al('alan', (function() use ($g) {
            $a = preg_replace('/[^a-z]/', '', strtolower((string)($g['alan'] ?? '')));
            return in_array($a, ['sag','fen','sos','ikt','egt','hkk','san'], true) ? $a : '';
        })(), ''),
        /* Bilim alanı kodları (FORD tabanlı). Eski 'alan' alanı yerinde
           bırakılır: dizin önerileri hâlâ onu kullanır ve yayımlanmış
           hiçbir kaydın alanı elden geçirilmeden değişmemelidir. */
        'alanlar' => $al('alanlar', array_slice(al_kodlar(is_array($g['alanlar'] ?? null) ? $g['alanlar'] : [], true), 0, 6), []),
        'tarih' => $al('tarih', preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($g['tarih'] ?? '')) ? (string)$g['tarih'] : date('Y-m-d')),
        'ozet' => $al('ozet', mb_substr($temizle($g['ozet'] ?? ''), 0, 4000)),
        'ozet_en' => $al('ozet_en', mb_substr($temizle($g['ozet_en'] ?? ''), 0, 4000)),
        'anahtar' => $al('anahtar', mb_substr(trim(tg_metin($g['anahtar'] ?? '')), 0, 400)),
        'anahtar_en' => $al('anahtar_en', mb_substr(trim(tg_metin($g['anahtar_en'] ?? '')), 0, 400)),
        'metin' => $al('metin', mb_substr($temizle($g['metin'] ?? ''), 0, 120000)),
        'metin_en' => $al('metin_en', mb_substr($temizle($g['metin_en'] ?? ''), 0, 120000)),
        'metin_ham' => $al('metin_ham', mb_substr($temizle($g['metin_ham'] ?? ''), 0, 120000)),
        'metin_ham_en' => $al('metin_ham_en', mb_substr($temizle($g['metin_ham_en'] ?? ''), 0, 120000)),
        'kaynakca' => $al('kaynakca', mb_substr($temizle($g['kaynakca'] ?? ''), 0, 20000)),
        'kaynakca_en' => $al('kaynakca_en', mb_substr($temizle($g['kaynakca_en'] ?? ''), 0, 20000)),
        'yayin' => $al('yayin', mb_substr(trim((string)($g['yayin'] ?? '')), 0, 200)),
        'doi' => $al('doi', mb_substr(trim((string)($g['doi'] ?? '')), 0, 120)),
        'bcid' => $bcid,
        'hakemler' => ($yama && !array_key_exists('hakemler', $g)) ? ($mevcut['hakemler'] ?? []) : $hakemler,
        'link' => $al('link', mb_substr(trim((string)($g['link'] ?? '')), 0, 300)),
    ];
    if ($kayit['baslik'] === '') cikti(['ok' => false, 'hata' => 'Başlık gerekli.'], 400);
    /* slug benzersizliği */
    foreach ($y as $e) { if (($e['id'] ?? '') !== $kayit['id'] && ($e['slug'] ?? '') === $kayit['slug']) { $kayit['slug'] .= '-' . substr($kayit['id'], 0, 4); break; } }
    $bulundu = false;
    foreach ($y as $i => $e) { if (($e['id'] ?? '') === $kayit['id']) { $y[$i] = array_merge($e, $kayit); $bulundu = true; break; } }
    if (!$bulundu) $y[] = $kayit;
    yaz_json('yazilar.json', $y);
    indexnow_yazi((string)$kayit['slug'], (string)($kayit['bcid'] ?? ''));
    cikti(['ok' => true, 'id' => $kayit['id'], 'slug' => $kayit['slug'], 'hakemler' => $kayit['hakemler']]);
}
/* YAZI sil (yönetici) */
if ($yol === '/yonetim/yazi-sil' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); $id = (string)($g['id'] ?? '');
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $y = array_values(array_filter($y, fn($e) => ($e['id'] ?? '') !== $id));
    yaz_json('yazilar.json', $y);
    cikti(['ok' => true]);
}
/* =====================================================================
   HESAP UÇLARI: kayıt, giriş, profil
   Roller bu sistemde satın alınmaz, verilmez, kazanılır. Kayıt olmak
   kimseyi hakem ya da yazar yapmaz; yalnızca kapıyı açar.
   ===================================================================== */
require_once __DIR__ . '/../k/hesap.php';

/* Oturum yardımcıları */
function hs_giris_yap(string $eposta): void { $_SESSION['kutadgu_hesap'] = hs_eposta_anahtar($eposta); }
/* =====================================================================
   ÇIKIŞ, OTURUMUN TAMAMINI YIKAR
   ---------------------------------------------------------------------
   Burada bir zamanlar tek satır vardı: unset($_SESSION['kutadgu_hesap']).
   Yalnız hesap kimliğini siliyordu. Oysa aynı oturumda İKİNCİ bir kimlik
   daha durabilir:

       $_SESSION['kutadgu_hesap']  hesap (yazar, hakem, editör)
       $_SESSION['kutadgu_yonetim'] yönetici (tek parolalı kapı)

   Arayüzdeki bütün çıkış düğmeleri bu uca gelir. Yani "Çıkış yap"
   diyen kişi hesabından çıkıyor, yönetici kimliği ise oturumda kalmaya
   devam ediyordu. Görünen sonucu şuydu: çıkış yapılıyor, yeniden
   girilmeye çalışılıyor, parola yanlış olduğu için ekranda hata
   beliriyor — ama panel yine de açılıyordu. Kişi haklı olarak
   "çıkamıyorum" diyordu.

   Bu bir görüntü kusuru değildir. girisli() tek başına
   yonetim_yazma_mi() içinde yeter ve bütün /yonetim/* uçlarını açar:
   yazı silme, ayar kaydetme, davet, başvuru kararı. Kapanmayan kapı,
   kapı değildir.

   NEDEN "HESABINDAN ÇIK AMA YÖNETİCİ KAL" DİYE BİR SEÇENEK YOK: böyle
   bir istek yok. Çıkış düğmesine basan kişinin beklediği tek şey, o
   tarayıcıda hiçbir yetkisinin kalmamasıdır. İki kimliği ayrı ayrı
   kapatan bir arayüz, hangisinin açık kaldığını kullanıcının aklında
   tutmasını isterdi; bu, güvenliği kullanıcının hafızasına bırakmaktır.

   Çerez de düşürülür: sunucudaki oturum yıkıldıktan sonra tarayıcıda
   ölü bir oturum kimliği kalmasın.
   ===================================================================== */
function hs_cikis(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        /* ÇEREZ İKİ KAPSAMDA BİRDEN DÜŞÜRÜLÜR.
           Bir tarayıcıda aynı adı taşıyan iki çerez durabilir: biri
           yalnız kutadgu.net'e ait (host-only), öteki .kutadgu.net
           altındaki her şeye. İkisi de aynı adla gönderilir ve sunucu
           hangisinin geldiğini seçemez. Yalnız birini silmek, ötekini
           gölge gibi bırakır: giriş yeni bir oturum açar, tarayıcı eski
           kimliği göndermeyi sürdürür ve kişi "girdim ama girmemişim"
           durumunda kalır. Bu, oturum kusurlarının en sinsi biçimidir,
           çünkü temiz bir tarayıcıda hiç görünmez.
           Alan adının başına nokta koyarak ikinci kapsam da silinir. */
        $alanlar = [$p['domain']];
        $ana = (string)($_SERVER['HTTP_HOST'] ?? '');
        $ana = preg_replace('/:\d+$/', '', $ana);
        if ($ana !== '' && filter_var($ana, FILTER_VALIDATE_IP) === false) {
            $alanlar[] = $ana;
            $alanlar[] = '.' . $ana;
        }
        foreach (array_unique($alanlar) as $alan) {
            @setcookie(session_name(), '', time() - 42000,
                $p['path'] ?: '/', (string)$alan, (bool)$p['secure'], (bool)$p['httponly']);
        }
    }
    session_destroy();
}
function hs_gerek(): array {
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => false, 'hata' => 'Bu işlem için giriş yapmalısınız.'], 401);
    return $h;
}
/* hs_kaydet() BURADAN k/hesap.php'ye TAŞINDI.
   Sebebi ölçüldü: hs_yenileme_uret() k/hesap.php'de duruyor ve içeride
   hs_kaydet() çağırıyordu; ama hs_kaydet yalnızca bu uç dosyasında
   tanımlıydı. Yani hesap modülü, tek başına yüklendiğinde ÇALIŞMAYAN
   bir işlev taşıyordu — komut satırından çağrılan ilk araç fatal
   hatayla düştü. Bir modülün işlevi, o modül yüklendiğinde çalışmalıdır.
   Uç dosyası k/hesap.php'yi zaten yüklüyor; davranış değişmedi. */
/* Dışarıya verilecek hesap görünümü: parola özeti asla çıkmaz */
function hs_gorunum(array $h): array {
    $dd = tg_dogrulama_durum($h['dogrulama'] ?? null);
    return [
        'eposta'    => (string)($h['eposta'] ?? ''),
        'kullanici' => (string)($h['kullanici'] ?? ''),
        'ad'        => (string)($h['ad'] ?? ''),
        'unvan'     => hs_unvan_anahtar((string)($h['unvan'] ?? '')) ?: (string)($h['unvan'] ?? ''),
        'unvan_ad'  => hs_unvan_ad((string)($h['unvan'] ?? '')),
        'gorunen'   => hs_gorunen_ad($h),
        'kurum'     => (string)($h['kurum'] ?? ''),
        'orcid'     => (string)($h['orcid'] ?? ''),
        /* Doğrulanmış mı: panel elle yazılmış bir numarayla doğrulanmış
           olanı aynı görünüşte gösterirse, doğrulamanın hiçbir anlamı
           kalmaz. Tarih değil, yalnız evet/hayır verilir. */
        'orcid_dogru' => !empty($h['orcid_dogrulandi']),
        'scopus'    => (string)($h['scopus'] ?? ''),
        /* Bağlantılar tek kapıdan okunur: eski tek 'web' alanı da bu
           listenin içinde gelir, böylece arayüz iki ayrı yer görmez. */
        'baglantilar' => tg_hesap_baglantilar($h),
        'uyelikler'   => tg_uyelikler($h['uyelikler'] ?? []),
        'tanitim'     => tg_tanitim($h['tanitim'] ?? ''),
        'telefon'     => (string)($h['telefon'] ?? ''),
        /* Görünürlük düzeyleri panele olduğu gibi verilir; eksik olanlar
           varsayılanla doldurulur ki panelde boş bir seçim görünmesin. */
        'gorunurluk'  => (function() use ($h) {
            $o = [];
            foreach (array_keys(tg_gorunurluk_alanlari()) as $alan) $o[$alan] = tg_gorunurluk($h, $alan);
            return $o;
        })(),
        /* Alan kodları: yeni kodlar 'alanlar' alanında durur, eski kaba
           alanlar da buraya çevrilerek katılır. Arayüz tek liste görür. */
        'alanlar'   => al_kayit_kodlari($h),
        'alan'      => is_array($h['alan'] ?? null) ? array_values($h['alan']) : [],
        'roller'    => hs_rolleri($h),
        'editor'    => hs_editor_mu($h),
        'bas_yetki' => hs_bas_yetki($h),
        /* Editör atama ayrı bir yetkidir ve süreli olabilir; arayüz
           süresi dolmuş bir düğmeyi göstermemelidir. */
        'editor_atama' => hs_editor_atama_yetkisi($h),
        'yetki_bitis'  => hs_editor_yetki_bitis((string)($h['eposta'] ?? '')),
        'dogrulama' => ['durum' => $dd['durum'], 'yeterli' => $dd['yeterli'], 'tur' => (string)(($h['dogrulama']['tur'] ?? ''))],
        'kullanici_secildi' => trim((string)($h['kullanici'] ?? '')) !== '',
        'dizin_gizli' => !empty($h['dizin_gizli']),
        'katilim'   => (string)($h['katilim'] ?? ''),
        'resim'     => tg_resim_yolu($h),
        'kisi_yolu' => tg_kisi_yolu((string)($h['ad'] ?? '')),
    ];
}

/* ---- KAYIT ---- */
if ($yol === '/hesap/kayit' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'hesap:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 15) cikti(['ok' => false, 'hata' => 'Çok fazla deneme. Lütfen sonra tekrar deneyin.'], 429);
    $t = fn($k, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g[$k] ?? ''))), 0, $n);

    $eposta = hs_eposta_anahtar((string)($g['eposta'] ?? ''));
    $parola = (string)($g['parola'] ?? '');
    $ad     = $t('ad', 120);
    $unvan  = $t('unvan', 60);

    if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'Geçerli bir e-posta adresi girin.'], 400);
    if (mb_strlen($parola, 'UTF-8') < 10) cikti(['ok' => false, 'hata' => 'Parola en az 10 karakter olmalıdır.'], 400);
    if ($ad === '') cikti(['ok' => false, 'hata' => 'Adınızı ve soyadınızı yazın.'], 400);
    if (hs_bul($eposta) !== null) cikti(['ok' => false, 'hata' => 'Bu e-posta adresiyle bir hesap zaten var. Giriş yapmayı deneyin.'], 409);

    $hesap = [
        'eposta'  => $eposta,
        'parola'  => password_hash($parola, PASSWORD_DEFAULT),
        'ad'      => $ad,
        'unvan'   => hs_unvan_anahtar($unvan),
        'kurum'   => $t('kurum', 220),
        'orcid'   => orcid_temiz((string)($g['orcid'] ?? '')),
        'scopus'  => scopus_temiz((string)($g['scopus'] ?? '')),
        /* Kayıt ekranı tek bir adres soruyorsa o da listeye yazılır;
           artık ayrı bir 'web' alanı tutulmaz. */
        'baglantilar' => tg_baglantilar(is_array($g['baglantilar'] ?? null) ? $g['baglantilar'] : [$t('web', 200)]),
        /* Kayıt sırasında da alan seçilebilir; seçilmezse boş kalır ve
           kişi panelinden ekler. Kimse alan seçmeye zorlanmaz. */
        'alanlar' => array_slice(al_kodlar(is_array($g['alanlar'] ?? null) ? $g['alanlar'] : [], true), 0, 10),
        'roller'  => [],
        'kullanici' => '',
        'katilim' => date('c'),
        'giris'   => [],
    ];
    /* ORCID ile gelinip burada hesap kuruluyorsa, o ORCID artık bir
       iddia değil doğrulanmış bir kimliktir; kaydı öyle düşer. Formdan
       gelen değere BAKILMAZ: doğrulanan neyse o yazılır, yoksa kişi
       kutuya başkasının numarasını yazıp doğrulanmış gösterebilirdi.
       Bekleyen kayıt burada tükenir. */
    $bek = $_SESSION['orcid_bekleyen'] ?? null;
    if (is_array($bek) && time() - (int)($bek['zaman'] ?? 0) <= 1800) {
        $bid = (string)($bek['orcid'] ?? '');
        if ($bid !== '' && orcid_sahibi($bid) === null) {
            $hesap['orcid'] = $bid;
            $hesap['orcid_dogrulandi'] = date('c');
            $hesap['orcid_yol'] = 'oauth';
        }
    }
    unset($_SESSION['orcid_bekleyen']);
    if (hs_bas_editor_mu($eposta)) {
        /* Rol KAYDA YAZILMAZ: baş editörlük ayar.php'deki listeden
           çözülür ve hs_rolleri() her okumada oradan verir. Kayda
           yazılsaydı, kişi listeden çıkarıldıktan sonra da yetkiyi
           taşırdı ve "geri alınabilir" sözü yalan olurdu. Buradan
           yalnızca boş kalmış unvan ve ad tamamlanır. */
        $b = hs_bas_editor_bilgi($eposta);
        if ($hesap['unvan'] === '' && trim((string)($b['unvan'] ?? '')) !== '') $hesap['unvan'] = (string)$b['unvan'];
        if ($hesap['ad'] === '' && trim((string)($b['ad'] ?? '')) !== '') $hesap['ad'] = (string)$b['ad'];
    }
    hs_kaydet($hesap);
    hs_giris_yap($eposta);
    cikti(['ok' => true, 'hesap' => hs_gorunum($hesap),
           'mesaj' => hs_bas_editor_mu($eposta)
             ? 'Hoş geldiniz. Baş editör olarak tanımlısınız; giriş için bir kullanıcı adı seçmeniz gerekiyor.'
             : 'Hesabınız açıldı. Hakemlik yapabilmek için doktoranızın bir editör tarafından doğrulanması gerekir.']);
}

/* ---- GİRİŞ ---- */
if ($yol === '/hesap/giris' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'giris:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 25) cikti(['ok' => false, 'hata' => 'Çok fazla deneme. Bir süre sonra tekrar deneyin.'], 429);
    /* Alan adı "kim"dir; e-posta ya da kullanıcı adı olabilir. Eski
       istemciler "eposta" gönderiyor olabilir, o da kabul edilir. */
    $kim = trim((string)($g['kim'] ?? ($g['eposta'] ?? '')));
    $parola = (string)($g['parola'] ?? '');
    if ($kim === '' || $parola === '') {
        cikti(['ok' => false, 'hata' => 'E-posta ya da kullanıcı adı ile parolanızı girin.'], 400);
    }
    $h = filter_var($kim, FILTER_VALIDATE_EMAIL) ? hs_bul($kim) : hs_bul_kullanici($kim);

    /* Baş editör ilk kez giriyorsa hesabı burada açılır */
    if ($h === null && filter_var($kim, FILTER_VALIDATE_EMAIL) && hs_bas_editor_mu($kim)) {
        cikti(['ok' => false, 'ilk_giris' => true,
               'hata' => 'Baş editör olarak tanımlısınız ama henüz bir parolanız yok. Hesabınızı açmak için kayıt bölümünü kullanın; e-posta adresiniz tanınacaktır.'], 404);
    }
    /* Sabit zamanlı davranış: hesap yoksa da doğrulama maliyeti ödenir */
    $ozet = is_array($h) ? (string)($h['parola'] ?? '') : password_hash('bos', PASSWORD_DEFAULT);
    if (!password_verify($parola, $ozet) || $h === null) {
        cikti(['ok' => false, 'hata' => 'E-posta ya da parola hatalı.'], 403);
    }
    if (password_needs_rehash($ozet, PASSWORD_DEFAULT)) {
        $h['parola'] = password_hash($parola, PASSWORD_DEFAULT);
    }
    $h['giris'] = array_slice(array_merge((array)($h['giris'] ?? []), [['tarih' => date('c'), 'ip' => substr(hash('sha256', ip_al()), 0, 12)]]), -20);
    hs_kaydet($h);
    session_regenerate_id(true);
    hs_giris_yap((string)$h['eposta']);
    cikti(['ok' => true, 'hesap' => hs_gorunum($h)]);
}

/* ---- ÇIKIŞ ---- */
if ($yol === '/hesap/cikis' && $metod === 'POST') { hs_cikis(); cikti(['ok' => true]); }

/* =====================================================================
   ORCID İLE KİMLİK
   ---------------------------------------------------------------------
   İki uç: birine gidilir, ötekinden dönülür. İkisi de tarayıcı
   yönlendirmesidir, JSON değil — bu yüzden betiksiz tarayıcıda da
   çalışır: sayfadaki şey bir bağlantıdır, bir düğme değil.

   ÜÇ AYRI SONUÇ, ÜÇÜ DE BİLEREK
   -----------------------------
   1. Oturumu açık bir kişi geldiyse    -> ORCID'i o hesaba BAĞLANIR.
   2. Bu ORCID doğrulanmış bir hesapta  -> o hesapla GİRİŞ yapılır.
   3. Hiçbiri                            -> hesap AÇILMAZ. Kişi kayıt
      ekranına, ORCID'i ve adı doldurulmuş olarak gönderilir.

   Üçüncüsü niye böyle: bir kimlik doğrulaması hesap açma kararı
   değildir. Sistem, kişiye e-postasını sormadan ve koşulları
   göstermeden hesap açarsa, kişi ne kabul ettiğini bilmeden içeri
   girmiş olur. Kimlik doğrulanır, hesap ise kurulur.

   BİR ORCID İKİ HESAPTA DURAMAZ. Gelen ORCID başka bir hesapta
   doğrulanmışsa bağlama reddedilir. Aksi hâlde bir kişi, başkasının
   ORCID'ini kendi hesabına yazıp o kimliği paylaşmış olurdu.
   ===================================================================== */
require_once __DIR__ . '/../k/orcid.php';

/* Kimin ORCID'i kim: yalnız DOĞRULANMIŞ kayıtlar sayılır. Elle yazılmış
   bir ORCID bir iddiadır, kimlik değildir ve giriş açmaz. */
function orcid_sahibi(string $id): ?array {
    foreach (hs_oku() as $h) {
        if (!is_array($h)) continue;
        if (empty($h['orcid_dogrulandi'])) continue;
        if (strcasecmp(trim((string)($h['orcid'] ?? '')), $id) === 0) return $h;
    }
    return null;
}

/* Dönüşün adresi: panel.php?orcid=<durum>#<bolum>
   SORGU, ÇAPADAN ÖNCE GELİR. İlk yazımda '#kapi?orcid=durum' diye
   kuruluyordu; o adreste "?orcid=durum" sorgu değil ÇAPANIN PARÇASIdır
   ve sunucuya hiç gitmez, betik de $_GET diye okuyamaz. Sonuç, sessizce
   kaybolan bir sonuç bildirimiydi: ORCID'den dönen kişi giriş ekranına
   düşüyor ve neden düştüğünü hiçbir yerde göremiyordu.
   Bir başarısızlık, başarısız olduğunu söylemelidir. */
function orcid_don(string $bolum, string $durum): void {
    header('Location: ' . rtrim(tg_kok(), '/') . '/panel.php?orcid=' . rawurlencode($durum)
        . '#' . $bolum, true, 302);
    exit;
}

if ($yol === '/orcid/git' && $metod === 'GET') {
    if (!tg_orcid_acik()) cikti(['ok' => false, 'hata' => 'ORCID girişi kapalı.'], 404);
    $niyet = ($_GET['niyet'] ?? '') === 'bagla' ? 'bagla' : 'giris';
    $durum = tg_orcid_durum_uret($niyet);
    header('Location: ' . tg_orcid_yetki_adresi($durum), true, 302);
    exit;
}

if ($yol === '/orcid/donus' && $metod === 'GET') {
    if (!tg_orcid_acik()) cikti(['ok' => false, 'hata' => 'ORCID girişi kapalı.'], 404);

    /* Kişi ORCID ekranında "izin verme" derse buraya hata ile döner.
       Bu bir arıza değildir; sessizce ana ekrana götürülür. */
    if (isset($_GET['error'])) orcid_don('kapi', 'iptal');

    $durum = (string)($_GET['state'] ?? '');
    $niyet = $durum === '' ? null : tg_orcid_durum_al($durum);
    if ($niyet === null) orcid_don('kapi', 'durum');

    $kod = (string)($_GET['code'] ?? '');
    if ($kod === '') orcid_don('kapi', 'kod');

    $k = tg_orcid_jeton($kod);
    if ($k === null) orcid_don('kapi', 'jeton');

    $id  = $k['orcid'];
    $ben = hs_oturum();
    $var = orcid_sahibi($id);

    /* 1. Oturum açıksa: bağla. */
    if ($ben !== null) {
        if ($var !== null && hs_eposta_anahtar((string)$var['eposta']) !== hs_eposta_anahtar((string)$ben['eposta'])) {
            orcid_don('hesap', 'baskasinda');
        }
        $ben['orcid'] = $id;
        $ben['orcid_dogrulandi'] = date('c');
        $ben['orcid_yol'] = 'oauth';
        hs_kaydet($ben);
        orcid_don('hesap', 'baglandi');
    }

    /* 2. Bu ORCID doğrulanmış bir hesapta: giriş. */
    if ($var !== null) {
        session_regenerate_id(true);
        hs_giris_yap((string)$var['eposta']);
        orcid_don('ozet', 'giris');
    }

    /* 3. Hesap yok: açmıyoruz, kayıt ekranına taşıyoruz. */
    $_SESSION['orcid_bekleyen'] = ['orcid' => $id, 'ad' => $k['ad'], 'zaman' => time()];
    orcid_don('kapi', 'kayit');
}

/* Kayıt ekranının doldurulmuş alanları buradan okunur. Oturumdaki
   bekleyen kayıt yarım saatte düşer: kayıt ekranını açıp bırakan biri,
   yarın gelen başkasına kendi ORCID'ini bırakmamalıdır. */
if ($yol === '/orcid/bekleyen' && $metod === 'GET') {
    $b = $_SESSION['orcid_bekleyen'] ?? null;
    if (!is_array($b) || time() - (int)($b['zaman'] ?? 0) > 1800) {
        unset($_SESSION['orcid_bekleyen']);
        cikti(['ok' => true, 'var' => false]);
    }
    cikti(['ok' => true, 'var' => true, 'orcid' => (string)$b['orcid'], 'ad' => (string)$b['ad']]);
}

/* ---- DURUM ----
   Sağ üstteki profil dairesinin ihtiyacı olan en az bilgi: baş harf,
   görünen ad, rol ve sizi bekleyen iş sayısı. /panel/ozet bütün arşivi
   tarar ve panelin tamamını kurar; her genel sayfada onu çağırmak
   gereksiz iş olur. Burada yalnızca sayılır, liste kurulmaz. */
/* =====================================================================
   SİZİ BEKLEYEN İŞLER · TEK KAYNAK
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Bu liste İKİ YERDE ayrı ayrı
   yazılıydı: /panel/ozet kartın listesini kuruyor, /hesap/durum ise
   sağ üstteki dairenin sayısını kendi başına sayıyordu. Bir kez
   uzlaştırılmışlardı (11 Ağustos, editörün kendi çalışması), ama iki
   kopya olmayı sürdürdükleri için 19 Ağustos'ta yeniden ayrıştılar:

     - kurul kararı oyu LİSTEDE vardı, ROZETTE yoktu;
     - kişinin kendi tamamlanmamış doğrulaması ROZETTE vardı, LİSTEDE
       yoktu.

   Üstelik kartın kendi sözü şudur: "Yalnızca sizden bir şey bekleyen
   işler burada görünür: yazmanız gereken bir rapor, oyunuzu bekleyen
   bir oylama ya da TAMAMLANMAMIŞ BİR DOĞRULAMA. Sağ üstteki daire
   üzerindeki sayı da bunları sayar." İki cümle de yanlıştı. Sistem,
   uygulamadığı bir kuralı duyuruyordu — bu depoda otuz üçüncü kez.

   ÇÖZÜM SAYIYI DÜZELTMEK DEĞİL, İKİNCİ KOPYAYI KALDIRMAKTIR. Liste
   burada bir kez kurulur; rozet onu SAYAR. Böylece rozetle listenin
   ayrışması mümkün olmaktan çıkar: sayı, listenin uzunluğudur.

   HER İŞİN BİR HEDEFİ VARDIR ('yol'). Hedefsiz bir bildirim, kişiye
   bir iş olduğunu söyleyip nerede yapılacağını söylememektir; panel
   hedefleri "#sekme/kart" biçiminde okur, kartı açar ve vurgular.
   ===================================================================== */
function bekleyen_isler(array $h): array {
    $benAd  = tg_ad_anahtar(hs_gorunen_ad($h));
    $editor = hs_editor_mu($h);
    $dd     = tg_dogrulama_durum($h['dogrulama'] ?? null);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $bek = [];

    foreach ($y as $e) {
        if (!is_array($e)) continue;
        /* Raporu bekleyen hakemlikler */
        foreach (tg_dizi($e['hakemler'] ?? null) as $hk) {
            if (!is_array($hk)) continue;
            if ($benAd !== '' && tg_ad_anahtar((string)($hk['ad'] ?? '')) === $benAd
                && trim((string)($hk['rapor'] ?? '')) === '') {
                $bek[] = ['tur' => 'rapor', 'baslik' => (string)($e['baslik'] ?? ''),
                          'yol' => tg_yazi_yolu($e),
                          'aciklama' => 'Hakem olarak atandınız, raporunuz bekleniyor.'];
            }
        }
        /* Oyunuzu bekleyen çalışma oylamaları */
        if ($dd['durum'] === 'onayli' || $editor) {
            foreach (tg_oylamalar($e) as $ov) {
                $s = tg_oylama_sonuc($ov);
                if ($s['kapali']) continue;
                if (!tg_oy_verebilir($e, $ov, $h)['olur']) continue;
                $bek[] = ['tur' => 'oy', 'baslik' => (string)($e['baslik'] ?? ''),
                          'yol' => tg_yazi_yolu($e) . '#oylama',
                          'aciklama' => 'Kurul oylaması oyunuzu bekliyor (' . $s['oy_sayisi'] . '/' . $s['gerek'] . ').'];
            }
        }
        /* Yazar olarak: revizyon istenmiş çalışma. Hedef, düzeltmenin
           YAPILDIĞI ekrandır; kamusal sayfa isteği gösterir ama orada
           yazılacak bir yer yoktur. */
        if (tg_yazar_mi($e, $h) && tg_kilit_durum($e) === 'revizyon') {
            $bek[] = ['tur' => 'revizyon', 'baslik' => (string)($e['baslik'] ?? ''),
                      'yol' => '/yazar.php?y=' . rawurlencode((string)($e['slug'] ?? ($e['id'] ?? ''))),
                      'aciklama' => 'Hakem revizyon istedi; düzeltilmiş metni yükleyebilirsiniz.'];
        }
    }

    /* KENDİ DOĞRULAMANIZ. Kart bunu sayacağını yazıyordu ve saymıyordu;
       rozet sayıyordu. Artık ikisi de aynı satırı görüyor. */
    if ($dd['durum'] !== 'onayli') {
        $bek[] = ['tur' => 'dogrulama', 'baslik' => 'Doktora doğrulamanız tamamlanmadı',
                  'yol' => '/panel.php#hesap/kartBelge',
                  'aciklama' => 'Hakemlik için doktora doğrulaması aranır; belge istenmez, bir editör adıyla doğrular.'];
    }

    /* Oyunuzu bekleyen KURUL kararı. Yalnız oy verebileceği kararlar
       sayılır: kurucu kararı, kurucu olmayan baş editörün önüne
       konsaydı o kişi hiç kapatamayacağı bir işi listesinde taşırdı. */
    if (hs_bas_yetki($h)) {
        $kkBek = 0;
        $benKk = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
        $kurucuMu = hs_kurucu_mu((string)($h['eposta'] ?? ''));
        foreach (tg_kk_oku() as $kk) {
            if (tg_kk_sonuc($kk)['kapali']) continue;
            if ((string)($kk['tur'] ?? '') === 'kurucu' && !$kurucuMu) continue;
            $verdi = false;
            foreach (tg_dizi($kk['oylar'] ?? null) as $o) {
                if (is_array($o) && hs_eposta_anahtar((string)($o['eposta'] ?? '')) === $benKk) { $verdi = true; break; }
            }
            if (!$verdi) $kkBek++;
        }
        if ($kkBek > 0) {
            $bek[] = ['tur' => 'kurul', 'baslik' => $kkBek . ' kurul kararı oyunuzu bekliyor',
                      'yol' => '/panel.php#editor/kartKarar',
                      'aciklama' => 'Oyunuzu Kurul kararları kartından verirsiniz; gerekçesiyle birlikte kurul sayfasında görünür.'];
        }
    }

    /* Editöre özel işler */
    if ($editor) {
        $belgeBekleyen = 0;
        foreach (hs_oku() as $x) {
            if (!is_array($x)) continue;
            $xd = $x['dogrulama'] ?? null;
            if (is_array($xd) && ($xd['tur'] ?? '') !== '' && empty($xd['onay'])) $belgeBekleyen++;
        }
        if ($belgeBekleyen > 0) {
            $bek[] = ['tur' => 'belge', 'baslik' => $belgeBekleyen . ' hesap doğrulama bekliyor',
                      'yol' => '/panel.php#editor/kartEditor',
                      'aciklama' => 'Sunulan doktora belgeleri editör onayı bekliyor.'];
        }
        $gonulluBekleyen = 0;
        foreach ($y as $e) { if (is_array($e)) $gonulluBekleyen += tg_gonullu_bekleyen($e); }
        if ($gonulluBekleyen > 0) {
            $bek[] = ['tur' => 'gonullu', 'baslik' => $gonulluBekleyen . ' hakemlik başvurusu bekliyor',
                      'yol' => '/panel.php#editor/kartCalisma',
                      'aciklama' => 'Gönüllü hakem başvuruları kararınızı bekliyor; kararı çalışmanın satırından verirsiniz.'];
        }
        /* KENDİ ÇALIŞMASI SAYILMAZ. /yonetim/hakem-ata ucu, editör o
           çalışmanın yazarıysa 403 veriyor; arayüz, sistemin yasakladığı
           bir işi teklif etmemeli. Kendi çalışması gizlenmiyor: kendi
           satırı "Çalışmalarım" sekmesinde zaten "Hakem aranıyor" diyor. */
        $atanabilir = 0;
        foreach ($y as $e2) {
            if (!is_array($e2) || !tg_hakem_araniyor($e2)) continue;
            if (tg_yazar_mi($e2, $h)) continue;
            $atanabilir++;
        }
        if ($atanabilir > 0) {
            $bek[] = ['tur' => 'atama', 'baslik' => $atanabilir . ' çalışmaya hakem aranıyor',
                      'yol' => '/panel.php#editor/kartCalisma',
                      'aciklama' => 'Bu çalışmalara hakem atayabilirsiniz.'];
        }
    }
    return $bek;
}

if ($yol === '/hesap/durum' && $metod === 'GET') {
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => true, 'girisli' => false]);

    $ad    = hs_gorunen_ad($h);
    $benAd = tg_ad_anahtar($ad);
    $roller = hs_rolleri($h);
    $editor = hs_editor_mu($h);
    $dd     = tg_dogrulama_durum($h['dogrulama'] ?? null);

    /* Baş harf: adın ve soyadın ilk harfleri. Ünvan sayılmaz. */
    $sade = trim(preg_replace('/^((Prof|Doç|Dr|Öğr|Arş|Gör)\.?\s*)+/ui', '', $ad));
    $parca = preg_split('/\s+/u', $sade, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $bh = '';
    if ($parca) {
        $bh = mb_strtoupper(mb_substr($parca[0], 0, 1, 'UTF-8'), 'UTF-8');
        if (count($parca) > 1) $bh .= mb_strtoupper(mb_substr($parca[count($parca) - 1], 0, 1, 'UTF-8'), 'UTF-8');
    }
    if ($bh === '') $bh = 'K';

    /* SAYI, LİSTENİN UZUNLUĞUDUR. Burada ayrı bir sayım vardı ve
       /panel/ozet'teki listeyle iki kez ayrıştı. Tek kaynak:
       bekleyen_isler(). Rozet ile kart aynı satırları görür. */
    $bekleyen = count(bekleyen_isler($h));
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];

    /* Listeye alınmış çalışmalardan, en son görüldüğünden bu yana
       değişenler. Bunlar "yapılacak iş" değil "okunacak haber"dir;
       bu yüzden ayrı sayılır ve rozette ayrı gösterilir. */
    $haber = 0;
    $takip = tg_takip_listesi($h);
    if ($takip) {
        foreach ($y as $e) {
            if (!is_array($e)) continue;
            $eid = (string)($e['id'] ?? '');
            if ($eid === '' || !array_key_exists($eid, $takip)) continue;
            $kayit = is_array($takip[$eid]) ? $takip[$eid] : [];
            if (tg_ozet_fark($kayit['ozet'] ?? null, tg_yazi_ozet($e))) $haber++;
        }
    }

    $enDil = (isset($_GET['lang']) && strtolower((string)$_GET['lang']) === 'en')
           || (isset($_COOKIE['kdil']) && strtolower((string)$_COOKIE['kdil']) === 'en');
    $rolAd = $roller ? hs_rol_ad((string)end($roller), $enDil) : '';
    cikti([
        'ok' => true, 'girisli' => true,
        'ad' => $ad, 'bas_harf' => $bh, 'rol' => $rolAd,
        'bekleyen' => $bekleyen, 'haber' => $haber,
        'begeni' => count($takip),
        'resim' => tg_resim_yolu($h),
        'kisi_yolu' => tg_kisi_yolu($ad),
    ]);
}

/* ---- PROFİL RESMİ ----
   Resim kareye kırpılır, 320 piksele indirilir ve WebP olarak yeniden
   yazılır. Yeniden yazmanın üç sebebi var: dosya küçülür, EXIF içindeki
   konum ve cihaz bilgisi tamamen düşer, ve dosyanın içine gizlenmiş
   bir betik varsa hiçbir izi kalmaz.

   Dosya adı içeriğin özetidir; resim değişince adres de değişir. */
if ($yol === '/hesap/resim' && $metod === 'POST') {
    $h = hs_gerek();
    $ec = $_FILES['dosya']['error'] ?? 4;
    if (empty($_FILES['dosya']) || $ec !== 0) cikti(['ok' => false, 'hata' => 'Resim alınamadı; dosya çok büyük olabilir.'], 400);
    if ((int)($_FILES['dosya']['size'] ?? 0) > 6 * 1024 * 1024) cikti(['ok' => false, 'hata' => 'En fazla 6 MB.'], 400);
    if (!function_exists('imagecreatetruecolor')) cikti(['ok' => false, 'hata' => 'Sunucuda resim işleme desteği yok.'], 500);

    $tmp = (string)$_FILES['dosya']['tmp_name'];
    $bilgi = @getimagesize($tmp);
    if (!$bilgi) cikti(['ok' => false, 'hata' => 'Bu dosya bir resim değil.'], 400);
    $kaynak = null;
    switch ($bilgi[2]) {
        case IMAGETYPE_JPEG: $kaynak = @imagecreatefromjpeg($tmp); break;
        case IMAGETYPE_PNG:  $kaynak = @imagecreatefrompng($tmp); break;
        case IMAGETYPE_WEBP: $kaynak = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($tmp) : null; break;
        case IMAGETYPE_GIF:  $kaynak = @imagecreatefromgif($tmp); break;
    }
    if (!$kaynak) cikti(['ok' => false, 'hata' => 'Sadece JPG, PNG, WebP veya GIF.'], 400);

    /* Ortadan kare kırp, sonra 320 piksele indir */
    $g = imagesx($kaynak); $y = imagesy($kaynak);
    $kenar = min($g, $y);
    $sx = (int)(($g - $kenar) / 2); $sy = (int)(($y - $kenar) / 2);
    $olcu = 320;
    $hedef = imagecreatetruecolor($olcu, $olcu);
    imagecopyresampled($hedef, $kaynak, 0, 0, $sx, $sy, $olcu, $olcu, $kenar, $kenar);
    imagedestroy($kaynak);

    ob_start();
    $webp = function_exists('imagewebp');
    if ($webp) imagewebp($hedef, null, 82); else imagejpeg($hedef, null, 86);
    $veri = (string)ob_get_clean();
    imagedestroy($hedef);
    if ($veri === '') cikti(['ok' => false, 'hata' => 'Resim işlenemedi.'], 500);

    $dizin = tg_resim_dizini();
    if (!is_dir($dizin) && !@mkdir($dizin, 0755, true)) cikti(['ok' => false, 'hata' => 'Resim klasörü açılamadı.'], 500);
    $ad = substr(hash('sha256', $veri), 0, 32) . ($webp ? '.webp' : '.jpg');
    if (@file_put_contents($dizin . '/' . $ad, $veri) === false) cikti(['ok' => false, 'hata' => 'Kaydedilemedi.'], 500);
    @chmod($dizin . '/' . $ad, 0644);

    $mail = hs_eposta_anahtar((string)$h['eposta']);
    $hs = hs_oku(); $eski = '';
    foreach ($hs as $i => $hh) {
        if (!is_array($hh) || hs_eposta_anahtar((string)($hh['eposta'] ?? '')) !== $mail) continue;
        $eski = (string)($hh['resim'] ?? '');
        $hs[$i]['resim'] = $ad;
        break;
    }
    hs_yaz($hs);
    /* Eskisi başka bir hesapta kullanılmıyorsa silinir */
    if ($eski !== '' && $eski !== $ad && preg_match('/^[a-f0-9]{16,64}\.(webp|jpg|png)$/', $eski)) {
        $kullanan = 0;
        foreach ($hs as $hh) { if (is_array($hh) && (string)($hh['resim'] ?? '') === $eski) $kullanan++; }
        if ($kullanan === 0) @unlink($dizin . '/' . $eski);
    }
    cikti(['ok' => true, 'resim' => '/resim.php?r=' . rawurlencode($ad),
           'mesaj' => 'Profil resminiz kaydedildi. Adınızın göründüğü yerlerde baş harflerinizin yerine bu resim görünür.']);
}

if ($yol === '/hesap/resim-sil' && $metod === 'POST') {
    $h = hs_gerek();
    $mail = hs_eposta_anahtar((string)$h['eposta']);
    $hs = hs_oku(); $eski = '';
    foreach ($hs as $i => $hh) {
        if (!is_array($hh) || hs_eposta_anahtar((string)($hh['eposta'] ?? '')) !== $mail) continue;
        $eski = (string)($hh['resim'] ?? '');
        unset($hs[$i]['resim']);
        break;
    }
    hs_yaz($hs);
    if ($eski !== '' && preg_match('/^[a-f0-9]{16,64}\.(webp|jpg|png)$/', $eski)) {
        $kullanan = 0;
        foreach ($hs as $hh) { if (is_array($hh) && (string)($hh['resim'] ?? '') === $eski) $kullanan++; }
        if ($kullanan === 0) @unlink(tg_resim_dizini() . '/' . $eski);
    }
    cikti(['ok' => true, 'mesaj' => 'Profil resminiz kaldırıldı. Yeniden baş harfleriniz görünecek.']);
}

/* ---- BEN ---- */
if ($yol === '/hesap/ben' && $metod === 'GET') {
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => true, 'girisli' => false]);
    $enU = (isset($_GET['lang']) && strtolower((string)$_GET['lang']) === 'en')
        || (isset($_COOKIE['kdil']) && strtolower((string)$_COOKIE['kdil']) === 'en');
    cikti(['ok' => true, 'girisli' => true, 'hesap' => hs_gorunum($h), 'unvanlar' => hs_unvanlar($enU)]);
}

/* ---- KULLANICI ADI SEÇİMİ (ilk girişte) ----
   Kullanıcı adı yalnızca girişte kullanılır. Çalışmalarda ve raporlarda
   her zaman "Ünvan Ad SOYAD" yazılır; kullanıcı adı hiçbir yerde görünmez. */
if ($yol === '/hesap/kullanici-sec' && $metod === 'POST') {
    $h = hs_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $k = mb_strtolower(trim((string)($g['kullanici'] ?? '')), 'UTF-8');
    if (!hs_kullanici_gecerli($k)) {
        cikti(['ok' => false, 'hata' => 'Kullanıcı adı 3 ile 24 karakter arası olmalı; küçük harf, rakam, nokta ve alt çizgi kullanılabilir.'], 400);
    }
    $var = hs_bul_kullanici($k);
    if ($var !== null && hs_eposta_anahtar((string)$var['eposta']) !== hs_eposta_anahtar((string)$h['eposta'])) {
        cikti(['ok' => false, 'hata' => 'Bu kullanıcı adı alınmış. Başka bir ad deneyin.'], 409);
    }
    if (trim((string)($h['kullanici'] ?? '')) !== '') {
        cikti(['ok' => false, 'hata' => 'Kullanıcı adınız zaten belirlenmiş.'], 409);
    }
    $h['kullanici'] = $k;
    hs_kaydet($h);
    cikti(['ok' => true, 'hesap' => hs_gorunum($h)]);
}

/* ---- PROFİL GÜNCELLE ----
   Kullanıcı kendi bilgilerini her zaman düzenleyebilir. Unvan da buradan
   değişir; bir kişinin unvanı zamanla yükselir, sistem buna kapalı olamaz. */
if ($yol === '/hesap/guncelle' && $metod === 'POST') {
    $h = hs_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = fn($k, $n, $v) => array_key_exists($k, $g)
        ? mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)$g[$k])), 0, $n) : $v;

    $ad = $t('ad', 120, (string)($h['ad'] ?? ''));
    if ($ad === '') cikti(['ok' => false, 'hata' => 'Ad boş bırakılamaz.'], 400);
    /* Unvan bir anahtar olarak saklanır. Eski kayıtlardan ya da başka
       bir dilden düz metin gelirse anahtara çevrilir; çevrilemiyorsa
       kabul edilmez, çünkü elle yazılan unvan doğrulanamaz. */
    $unvan = $t('unvan', 60, (string)($h['unvan'] ?? ''));
    if ($unvan !== '') {
        $ua = hs_unvan_anahtar($unvan);
        if ($ua === '') cikti(['ok' => false, 'hata' => 'Unvan listeden seçilmelidir.'], 400);
        $unvan = $ua;
    }
    if (array_key_exists('orcid', $g)) {
        $o = orcid_temiz((string)$g['orcid']);
        if (trim((string)$g['orcid']) !== '' && $o === '') cikti(['ok' => false, 'hata' => 'ORCID geçersiz (0000-0000-0000-0000 biçiminde olmalı).'], 400);
        /* DOĞRULAMA, DEĞİŞTİRİLEN NUMARAYLA BİRLİKTE DÜŞER.
           Bir kez ORCID ile giriş yapıp sonra kutuya başka bir numara
           yazan kişi, o numarayı da doğrulanmış göstermiş olurdu.
           Doğrulama numaranın kendisine bağlıdır, hesaba değil. */
        if (!empty($h['orcid_dogrulandi']) && strcasecmp($o, (string)($h['orcid'] ?? '')) !== 0) {
            unset($h['orcid_dogrulandi'], $h['orcid_yol']);
        }
        $h['orcid'] = $o;
    }
    if (array_key_exists('scopus', $g)) {
        $scH = trim((string)$g['scopus']);
        $sc = scopus_temiz($scH);
        if ($scH !== '' && $sc === '') cikti(['ok' => false, 'hata' => 'Scopus Author ID girildiyse geçerli olmalıdır.'], 400);
        $h['scopus'] = $sc;
    }
    /* Çalışma alanları. Artık FORD tabanlı kodlarla tutulur; eski yedi
       kaba alan gönderilirse karşılığına çevrilir, kimse elle taşımak
       zorunda kalmaz. En çok on kod: liste bir özgeçmiş değil, hakem
       eşleştirmesi için bir imdir; uzadıkça anlamını yitirir. */
    if (array_key_exists('alanlar', $g) || array_key_exists('alan', $g)) {
        $ham = [];
        foreach ((array)($g['alanlar'] ?? []) as $k) $ham[] = (string)$k;
        $es = $g['alan'] ?? [];
        foreach (is_array($es) ? $es : [$es] as $k) $ham[] = (string)$k;
        $h['alanlar'] = array_slice(al_kodlar($ham, true), 0, 10);
        unset($h['alan']);   /* eski alan artık yenisinin içinde */
    }
    /* ---- Telefon ve iletişim görünürlüğü ----
       Telefon isteğe bağlıdır ve varsayılanı gizlidir. Görünürlük
       düzeyleri kişinin kendi kararıdır; sistem hiçbirini onun yerine
       seçmez ve varsayılanı geriye dönük değiştirmez. */
    if (array_key_exists('telefon', $g)) {
        $tel = preg_replace('/[^0-9+ ()\-]/', '', (string)$g['telefon']);
        $h['telefon'] = mb_substr(trim((string)$tel), 0, 40);
    }
    if (array_key_exists('gorunurluk', $g) && is_array($g['gorunurluk'])) {
        $izin = ['acik', 'uyeler', 'gizli'];
        $mevcut = is_array($h['gorunurluk'] ?? null) ? $h['gorunurluk'] : [];
        foreach (array_keys(tg_gorunurluk_alanlari()) as $alan) {
            if (!array_key_exists($alan, $g['gorunurluk'])) continue;
            $d = trim((string)$g['gorunurluk'][$alan]);
            if (in_array($d, $izin, true)) $mevcut[$alan] = $d;
        }
        $h['gorunurluk'] = $mevcut;
    }

    /* ---- Akademik bağlantılar, üyelikler ve kısa tanıtım ----
       Bir araştırmacının kaydı yalnızca bu sistemdeki işlerinden ibaret
       değildir; kendi seçtiği dış profillerini de yazabilir. Hiçbiri
       zorunlu değildir ve hiçbiri bir yetki getirmez.

       Gönderilmeyen alan değişmez: panelin bir bölümünü kullanmayan bir
       istemci ötekini silmiş olmaz. Boş dizi gönderilirse liste bilerek
       boşaltılmış demektir ve boşaltılır. */
    if (array_key_exists('baglantilar', $g)) {
        $h['baglantilar'] = tg_baglantilar($g['baglantilar']);
    }
    if (array_key_exists('uyelikler', $g)) {
        $h['uyelikler'] = tg_uyelikler($g['uyelikler']);
    }
    if (array_key_exists('tanitim', $g)) {
        $h['tanitim'] = tg_tanitim($g['tanitim']);
    }

    $h['ad']    = $ad;
    $h['unvan'] = $unvan;
    $h['kurum'] = $t('kurum', 220, (string)($h['kurum'] ?? ''));

    /* Eski tek 'web' alanının göçü. Bir kez çalışması yeter: adres
       listeye taşınır ve alan kayıttan düşer.

       Bağlantı listesi bu istekte gönderildiyse taşıma yapılmaz, çünkü
       panel listeyi zaten eski adres içinde olacak biçimde gösterir;
       kişi onu oradan sildiyse silinmiş sayılır. Geri koymak, kişinin
       kararını görmezden gelmek olurdu. */
    if (trim((string)($h['web'] ?? '')) !== '') {
        if (!array_key_exists('baglantilar', $g)) $h['baglantilar'] = tg_hesap_baglantilar($h);
        unset($h['web'], $h['web_ad']);
    }

    hs_kaydet($h);
    cikti(['ok' => true, 'hesap' => hs_gorunum($h)]);
}

/* ---- PAROLA DEĞİŞTİR ---- */
if ($yol === '/hesap/parola' && $metod === 'POST') {
    $h = hs_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $eski = (string)($g['eski'] ?? '');
    $yeni = (string)($g['yeni'] ?? '');
    if (!password_verify($eski, (string)($h['parola'] ?? ''))) cikti(['ok' => false, 'hata' => 'Mevcut parolanız hatalı.'], 403);
    if (mb_strlen($yeni, 'UTF-8') < 10) cikti(['ok' => false, 'hata' => 'Yeni parola en az 10 karakter olmalıdır.'], 400);
    $h['parola'] = password_hash($yeni, PASSWORD_DEFAULT);
    $h['parola_tarih'] = date('c');
    hs_kaydet($h);
    session_regenerate_id(true);
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    eposta_gonder((string)$h['eposta'], $marka . ' | Parolanız değiştirildi',
        "Sayın " . hs_gorunen_ad($h) . ",\n\n" .
        "Hesabınızın parolası " . date('d.m.Y H:i') . " tarihinde değiştirildi.\n\n" .
        "Bu değişikliği siz yapmadıysanız lütfen hemen bize yazın.\n\n" . $marka . "\n" . tg_kok() . "\n");
    cikti(['ok' => true]);
}

/* ---- DOKTORA BELGESİ SUN ----
   Hakemlik koşulu belgeyle kanıtlanır; kimlik beyanı yeterli değildir. */
/* =====================================================================
   DOKTORA BELGESİNİN DOĞRULANMASI
   ---------------------------------------------------------------------
   Burada bilerek verilmeyen bir karar var: BELGE DOSYASI YÜKLENMİYOR.

   Bir Türk mezuniyet belgesinin üzerinde T.C. kimlik numarası, anne ve
   baba adı, doğum tarihi ve diploma numarası yazılıdır. Sistemin bu
   bilgilere ihtiyacı yoktur; ihtiyaç duyduğu tek şey kişinin doktora
   derecesi olup olmadığıdır. Toplanmayan veri sızdırılamaz. Bu yüzden
   belgenin kendisi değil, YALNIZCA doğrulama anahtarı saklanır.

   Üç yol vardır:

     edevlet : e-Devlet'ten alınmış barkodlu belgenin doğrulama kodu.
               Kod, turkiye.gov.tr/belge-dogrulama adresinde sorgulanır.
               Bu sorgu OTOMATİK YAPILAMAZ: sayfa robot denetimi ister ve
               bir kamu hizmetinin bu denetimini aşmaya çalışmak doğru
               olmaz. Bu yüzden sorguyu bir editör kendi eliyle yapar;
               panelde kodun yanındaki bağlantı doğrulama sayfasını açar.
               Kod tek kullanımlık değildir, doğrulanan bilgi sayfada
               görünür ve editör yalnızca "doktora mezuniyeti var mı"
               sorusuna bakar.

     orcid   : ORCID'in herkese açık arayüzü üzerinden OTOMATİK denetim.
               Kişi eğitim kayıtlarını ORCID profilinde açık bırakmışsa,
               doktora düzeyinde bir kaydın varlığı sistemce sorgulanır.
               Bu bir kanıt değil güçlü bir belirtidir; editör onayı yine
               aranır, ama editörün işi tek bakışa iner.

     yabanci : Yurt dışı belgeler için, belgenin sorgulanabileceği
               kamusal kurum adresi. Alan adının kamusal olduğu sistemce
               denetlenir; sayfanın kendisine editör bakar.
   ===================================================================== */

/* ORCID herkese açık arayüzünden eğitim kayıtlarını sorgula.
   Dönen: ['ok'=>bool,'doktora'=>bool,'kayitlar'=>[...],'hata'=>string] */
function orcid_egitim_sorgula(string $orcid): array {
    $o = orcid_temiz($orcid);
    if ($o === '') return ['ok' => false, 'doktora' => false, 'kayitlar' => [], 'hata' => 'ORCID geçersiz.'];

    $url = 'https://pub.orcid.org/v3.0/' . rawurlencode($o) . '/educations';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'User-Agent: Kutadgu/1.0 (+' . tg_kok() . ')'],
    ]);
    $govde = curl_exec($ch);
    $kod   = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hataM = curl_error($ch);
    curl_close($ch);

    if ($govde === false || $kod !== 200) {
        return ['ok' => false, 'doktora' => false, 'kayitlar' => [],
                'hata' => 'ORCID sorgusu yanıt vermedi' . ($kod ? ' (HTTP ' . $kod . ')' : ($hataM !== '' ? ': ' . $hataM : '')) . '.'];
    }
    $d = json_decode((string)$govde, true);
    if (!is_array($d)) return ['ok' => false, 'doktora' => false, 'kayitlar' => [], 'hata' => 'ORCID yanıtı okunamadı.'];
    return orcid_egitim_coz($d);
}

/* ORCID yanıtını çözümle. Ağdan ayrı tutuldu ki tek başına sınanabilsin. */
function orcid_egitim_coz(array $d): array {
    /* Doktora düzeyini gösteren unvanlar; dil ayrımı gözetilmez.
       "dr" tek başına aranmaz: "drama", "hydrology" gibi sözcüklerde geçer. */
    $doktoraIz = ['phd', 'ph.d', 'ph d', 'doctor', 'doctora', 'doctorate', 'doktora', 'doctoral',
                  'dphil', 'd.phil', 'doctorat', 'doktorat', 'promotion',
                  'dsc', 'd.sc', 'scd', 'sc.d', 'edd', 'ed.d', 'jsd', 'j.s.d', 'thd', 'th.d', 'dba', 'd.b.a'];
    $kayitlar = []; $doktora = false;

    $ozetler = [];
    foreach (($d['affiliation-group'] ?? []) as $grup) {
        foreach (($grup['summaries'] ?? []) as $o) { if (is_array($o)) $ozetler[] = $o; }
    }
    /* Bazı yanıtlar doğrudan education-summary listesi döner */
    foreach (($d['education-summary'] ?? []) as $o) { if (is_array($o)) $ozetler[] = ['education-summary' => $o]; }

    foreach ($ozetler as $ozet) {
        $e = $ozet['education-summary'] ?? ($ozet['qualification-summary'] ?? null);
        if (!is_array($e)) continue;
        $unvan = trim((string)($e['role-title'] ?? ''));
        $bolum = trim((string)($e['department-name'] ?? ''));
        $kurum = trim((string)(($e['organization']['name'] ?? '')));
        $bitis = (string)(($e['end-date']['year']['value'] ?? ''));
        $kayit = ['unvan' => $unvan, 'bolum' => $bolum, 'kurum' => $kurum, 'yil' => $bitis, 'doktora' => false];
        $kucuk = mb_strtolower($unvan . ' ' . $bolum, 'UTF-8');
        foreach ($doktoraIz as $iz) {
            if ($kucuk !== '' && mb_strpos($kucuk, $iz) !== false) { $kayit['doktora'] = true; $doktora = true; break; }
        }
        $kayitlar[] = $kayit;
    }
    return ['ok' => true, 'doktora' => $doktora, 'kayitlar' => $kayitlar, 'hata' => ''];
}

/* ORCID DENETİMİ: kişinin kendisi ya da bir editör çalıştırabilir */
/* =====================================================================
   PAROLAMI UNUTTUM
   ---------------------------------------------------------------------
   ERİŞİM İLE KİMLİK AYRI ŞEYLERDİR ve kurtarma erişime dayanmalıdır.
   ORCID bir KİMLİK savıdır ("bu, aynı araştırmacı"); e-posta kutusuna
   erişim bir ERİŞİM savıdır ("bu, aynı kutunun sahibi"). Bir hesabı
   geri almanın yolu ikincisidir.

   ORCID GİRİŞİ BUNUN YERİNE GEÇMEZ, ikinci bir kapıdır. Üç sebeple:
   herkesin ORCID'i doğrulanmış değil (Gökhan Hoca'nın kaydında yoktu),
   ORCID bir ÜÇÜNCÜ TARAFTIR ve kapanabilir, ve istemci gizli anahtarı
   yenilendiği anda o kapı da kapanır. Tek kapısı üçüncü tarafta olan
   bir sistem, o tarafın çalışma saatlerine bağlıdır.

   HESABIN VARLIĞI AÇIK EDİLMEZ. Yanıt her zaman aynıdır: "kayıtlıysa
   gönderildi". Yoksa bu uç, hangi adreslerin sistemde olduğunu
   sorabileceğiniz bir listeye dönerdi.

   YENİLEME İSTEĞİ PAROLAYI DÜŞÜRMEZ. Kişi isteği yanlışlıkla yaptıysa
   ya da istek başkasından geldiyse, eski parolası çalışmayı sürdürür.
   Parola ancak yeni parola KURULDUĞU anda değişir.
   ===================================================================== */
if ($yol === '/hesap/parola-unuttum' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    /* Bal kabı ve hız sınırı: bu uç, adres denemek için de kullanılabilir. */
    if (trim((string)($g['website'] ?? '')) !== '') cikti(['ok' => true]);
    $ipk = 'pyenile:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 8) cikti(['ok' => false, 'hata' => 'Kısa sürede çok fazla istek. Lütfen sonra tekrar deneyin.'], 429);

    $eposta = hs_eposta_anahtar((string)($g['eposta'] ?? ''));

    /* ---- AYNI YANIT HER DURUMDA, AMA DOĞRU YANIT ----
       Adres geçersizse bile aynı cümle döner: biçim denetiminin farklı
       yanıt vermesi de bir sızıntıdır. Bu kural DURUYOR.

       DEĞİŞEN ŞU: cümle "gönderildi" diyordu ve gönderilmemiş olabilirdi.
       Bildirilen kusur buydu — "mesaj gönderildi dedi ama gelmedi."
       Ölçüldü: uç eposta_gonder()'in DÖNÜŞ DEĞERİNİ HİÇ OKUMUYORDU.
       Posta yolu kapalıyken bile "gönderildi" yazıyordu. Bu depoda
       on birinci kez aynı kusur: sistem yapmadığı bir şeyi duyuruyor.

       SIZINTI OLMADAN NASIL DÜRÜST OLUNUR: posta yolunun açık olup
       olmadığı SİSTEMİN durumudur, hesabın değil. Bu yüzden hesap
       aranmadan ÖNCE sorulur ve iki durumda da aynı cümle döner.
       Hesabı olan da olmayan da aynı şeyi duyar; duyduğu şey artık
       doğrudur. */
    $postaVar = !function_exists('pst_yol_var') || pst_yol_var();
    $yanit = $postaVar
        ? ['ok' => true, 'mesaj' => 'Bu adreste bir hesap varsa, parola yenileme bağlantısı gönderildi. Bağlantı bir saat geçerlidir.']
        : ['ok' => true, 'posta_yok' => true,
           'mesaj' => 'Bu adreste bir hesap varsa bağlantı hazırlandı; ancak sistemin posta yolu şu anda kurulu değil ve ileti GÖNDERİLEMEDİ. Yöneticiye ulaşın: bağlantı sunucudan üretilebilir.'];
    if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) cikti($yanit);
    $h = hs_bul($eposta);
    if ($h === null || trim((string)($h['parola'] ?? '')) === '') cikti($yanit);

    $jeton = hs_yenileme_uret($h, 1);
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    $bag = rtrim(tg_kok(), '/') . '/hesap-kur.php?y=' . $jeton;
    /* DÖNÜŞ DEĞERİ OKUNUR. Okunmadığı için bu uç yıllardır gönderemediği
       iletiyi "gönderildi" diye bildiriyordu. Yanıt yine hesabın var
       olup olmadığını söylemez; yalnızca gönderimin başarısız olduğunu
       söyler ve o bilgi hesaba değil sisteme aittir. */
    /* METNİN NEDEN BÖYLE OLDUĞU — 14 Ağustos 2026.
       Bu ileti Gmail'de spam klasörüne düştü. Kimlik doğrulaması
       ölçüldü ve üçü de geçiyordu (SPF, DKIM, DMARC: PASS); sebep
       iletinin KENDİSİYDİ. Bağlamı olmayan, doğrudan "parolanızı
       yenileyin" diyen ve gövdesinde tek başına duran uzun bir
       bağlantı taşıyan bir metin, oltalama iletisinin ta kendisidir —
       hem süzgeç için hem de onu okuyan insan için.

       Bu yüzden ileti önce KENDİNİ TANITIR, sonra isteği söyler; ve
       bir oltalama iletisinin asla yazmayacağı cümleyi yazar: bu
       sistem sizden parolanızı hiçbir zaman istemez. */
    $gitti = eposta_gonder($eposta, $marka . ' | Parola yenileme',
        "Sayın " . hs_gorunen_ad($h) . ",\n\n"
        . "Bu ileti, açık erişimli akademik yayın sistemi " . $marka . " tarafından gönderilmektedir (" . tg_kok() . "). "
        . "Bu adrese kayıtlı hesap için bir parola yenileme isteği alındı.\n\n"
        . "Yeni parolanızı şu bağlantıdan kurabilirsiniz:\n\n"
        . $bag . "\n\n"
        . "Bağlantı BİR SAAT geçerlidir ve bir kez kullanılır. Kimseyle paylaşmayın: "
        . $marka . " sizden parolanızı hiçbir zaman istemez ve hiçbir iletide parola sormaz.\n\n"
        . "Bu isteği siz yapmadıysanız yapmanız gereken bir şey yok: eski parolanız çalışmayı sürdürüyor "
        . "ve bu bağlantı bir saat sonra kendiliğinden geçersiz olur.\n\n"
        . $marka . "\n" . tg_kok() . "\n");
    if (!$gitti) {
        /* Kütüğe zaten eposta_gonder() yazıyor; burada yalnız yanıt
           düzeltilir. Sebep yanıta KONMAZ: sunucunun iç hatası okura
           söylenmez, yöneticiye kütükte söylenir. */
        $yanit['posta_yok'] = true;
        $yanit['mesaj'] = 'Bu adreste bir hesap varsa bağlantı hazırlandı; ancak ileti GÖNDERİLEMEDİ. Yöneticiye ulaşın: bağlantı sunucudan üretilebilir.';
    }
    cikti($yanit);
}

/* Yenileme bağlantısını çözer: kimin için olduğunu söyler, adresi
   söylemez. Davet çözücüsüyle aynı ölçü. */
if ($yol === '/hesap/yenileme-bilgi' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'pybilgi:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 40) cikti(['ok' => false, 'hata' => 'Çok fazla deneme. Bir süre sonra tekrar deneyin.'], 429);
    $h = hs_yenileme_coz((string)($g['y'] ?? ''));
    if ($h === null) cikti(['ok' => false, 'hata' => 'Bu bağlantı geçersiz ya da süresi dolmuş.'], 404);
    cikti(['ok' => true, 'ad' => hs_gorunen_ad($h),
           'bitis' => (string)($h['parola_yenile']['bitis'] ?? '')]);
}

if ($yol === '/hesap/parola-yenile' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'pykur:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 25) cikti(['ok' => false, 'hata' => 'Çok fazla deneme. Bir süre sonra tekrar deneyin.'], 429);
    $h = hs_yenileme_coz((string)($g['y'] ?? ''));
    if ($h === null) cikti(['ok' => false, 'hata' => 'Bu bağlantı geçersiz ya da süresi dolmuş.'], 404);
    $parola = (string)($g['parola'] ?? '');
    if (mb_strlen($parola, 'UTF-8') < 10) cikti(['ok' => false, 'hata' => 'Parola en az 10 karakter olmalıdır.'], 400);
    $h['parola'] = password_hash($parola, PASSWORD_DEFAULT);
    $h['parola_tarih'] = date('c');
    /* JETON BURADA TÜKENİR. Kalsaydı, aynı bağlantı bir saat boyunca
       parolayı tekrar tekrar değiştirebilirdi. */
    unset($h['parola_yenile']);
    hs_kaydet($h);
    session_regenerate_id(true);
    hs_giris_yap((string)$h['eposta']);
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    /* Değişiklik kişiye HABER VERİLİR: parolası başkası tarafından
       değiştirildiyse, bunu öğrenmesinin tek yolu budur. */
    eposta_gonder((string)$h['eposta'], $marka . ' | Parolanız yenilendi',
        "Sayın " . hs_gorunen_ad($h) . ",\n\n"
        . "Parolanız az önce yenilendi. Bunu siz yapmadıysanız hemen bize yazın:\n"
        . rtrim(tg_kok(), '/') . "/iletisim.php\n\n" . $marka . "\n");
    cikti(['ok' => true, 'hesap' => hs_gorunum($h)]);
}

if ($yol === '/hesap/orcid-denetle' && $metod === 'POST') {
    $h = hs_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $hedefMail = mb_strtolower(trim((string)($g['eposta'] ?? '')), 'UTF-8');

    $hedef = $h;
    if ($hedefMail !== '' && hs_eposta_anahtar($hedefMail) !== hs_eposta_anahtar((string)$h['eposta'])) {
        if (!hs_editor_mu($h)) cikti(['ok' => false, 'hata' => 'Başkasının hesabını yalnızca editörler denetleyebilir.'], 403);
        $bul = hs_bul($hedefMail);
        if ($bul === null) cikti(['ok' => false, 'hata' => 'Hesap bulunamadı.'], 404);
        $hedef = $bul;
    }

    $o = orcid_temiz((string)($hedef['orcid'] ?? ''));
    if ($o === '') cikti(['ok' => false, 'hata' => 'Bu hesapta geçerli bir ORCID kayıtlı değil. Önce profilinize ORCID kimliğinizi yazın.'], 400);

    $s = orcid_egitim_sorgula($o);
    if (!$s['ok']) cikti(['ok' => false, 'hata' => $s['hata']], 502);

    /* Sonuç kaydedilir; kendi başına onay değildir, editöre bilgi verir. */
    $d = is_array($hedef['dogrulama'] ?? null) ? $hedef['dogrulama'] : [];
    $d['orcid_denetim'] = [
        'tarih'    => date('c'),
        'doktora'  => (bool)$s['doktora'],
        'kayitlar' => array_slice($s['kayitlar'], 0, 8),
    ];
    if (($d['tur'] ?? '') === '') { $d['tur'] = 'orcid'; $d['onay'] = false; $d['durum'] = 'bekliyor'; $d['tarih'] = date('c'); }
    $hedef['dogrulama'] = $d;
    hs_kaydet($hedef);

    cikti(['ok' => true, 'doktora' => (bool)$s['doktora'], 'kayitlar' => $s['kayitlar'],
           'mesaj' => $s['doktora']
             ? 'ORCID kayıtlarında doktora düzeyinde bir eğitim kaydı bulundu. Bu güçlü bir belirtidir; onayı yine bir editör verir.'
             : ($s['kayitlar']
                ? 'ORCID kayıtlarına ulaşıldı ancak doktora düzeyinde bir kayıt görünmedi. Kayıtlarınız ORCID profilinizde herkese açık mı, kontrol edin.'
                : 'ORCID profilinde herkese açık eğitim kaydı bulunmuyor. Kayıtları açık yaparsanız denetim kendiliğinden yapılabilir.')]);
}

if ($yol === '/hesap/belge' && $metod === 'POST') {
    $h = hs_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $tur = preg_replace('/[^a-z]/', '', strtolower((string)($g['belge_tur'] ?? '')));
    /* e-DEVLET YOLU KAPATILDI — 14 Ağustos 2026.
       Tek bir ülkenin kimlik altyapısına bağlı bir kapıydı; bu sistemin
       hakemleri tek bir ülkeden gelmiyor. Gerekçenin tamamı ortak.php'de
       tg_dogrulama_editor()'ün başındadır. Uç eski istekleri de reddeder
       ki iki kural aynı anda yürümesin; DAHA ÖNCE e-Devlet yoluyla
       onaylanmış kayıtlar ise DOKUNULMADAN geçerli kalır — verilmiş bir
       onayı geri almak, kişinin hakkını almak olurdu. */
    if ($tur === 'edevlet') {
        cikti(['ok' => false, 'hata' => 'Bu yol kapatıldı: doktora artık belgeyle değil, bir editörün adıyla doğrulanır. Bir çalışmaya hakem olarak başvurabilir ya da bir editörün sizi davet etmesini bekleyebilirsiniz.'], 410);
    }
    if (!in_array($tur, ['orcid', 'yabanci'], true)) cikti(['ok' => false, 'hata' => 'Doğrulama türünü seçin.'], 400);
    $d = ['tur' => $tur, 'onay' => false, 'durum' => 'bekliyor', 'tarih' => date('c')];
    if ($tur === 'orcid') {
        /* ORCID yolu: belge yok, kimlik var. Denetim kendiliğinden yapılır. */
        $o = orcid_temiz((string)($g['belge_orcid'] ?? ($h['orcid'] ?? '')));
        if ($o === '') cikti(['ok' => false, 'hata' => 'Geçerli bir ORCID kimliği gerekir (0000-0000-0000-0000 biçiminde).'], 400);
        $d['orcid']  = $o;
        $d['kaynak'] = 'https://orcid.org/' . $o;
        $s = orcid_egitim_sorgula($o);
        if ($s['ok']) {
            $d['orcid_denetim'] = ['tarih' => date('c'), 'doktora' => (bool)$s['doktora'],
                                   'kayitlar' => array_slice($s['kayitlar'], 0, 8)];
        }
        if (trim((string)($h['orcid'] ?? '')) === '') $h['orcid'] = $o;
    } else {
        $u = mb_substr(trim((string)($g['belge_url'] ?? '')), 0, 500);
        if ($u === '') cikti(['ok' => false, 'hata' => 'Derecenizin göründüğü bir adres yazın.'], 400);
        /* ALAN ADI ARTIK REDDETMEZ, YALNIZCA İŞARETLER.
           Eskiden kamusal olmayan bir alan adı 400 ile geri çevriliyordu.
           İki kusuru vardı: (1) alan adı doktorayı göstermez — sayfaya
           bakıp karar veren yine editördü, yani liste denetim değil
           engeldi; (2) listede olmayan ülkelerdeki araştırmacılar
           sebepsiz dışarıda kalıyordu. Kanıt olarak değeri sürüyor, o
           yüzden ölçülür ve editöre gösterilir; kapı olmaktan çıkar. */
        $d['kamusal'] = tg_kamu_alan($u);
        $d['url'] = $u;
        $d['kurum'] = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['belge_kurum'] ?? ''))), 0, 220);
        $d['kaynak'] = $u;
    }
    $h['dogrulama'] = $d;
    $r = is_array($h['roller'] ?? null) ? $h['roller'] : [];
    if (!in_array('aday_hakem', $r, true) && !in_array('hakem', $r, true)) { $r[] = 'aday_hakem'; $h['roller'] = $r; }
    hs_kaydet($h);
    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'],
            "\xF0\x9F\x93\x84 Doktora belgesi sunuldu\n" . hs_gorunen_ad($h) . "\n" . (string)$h['eposta'] . "\nTur: " . $tur, 4);
    }
    $mesajlar = [
        /* e-Devlet satırı kaldırıldı: o yol yukarıda 410 ile kapanıyor,
           buraya hiç düşmüyor. Ulaşılamaz bir metin, bir gün kuralın
           hâlâ yürürlükte olduğunu sanan birine yol gösterir. */
        'orcid'   => 'ORCID kimliğiniz alındı ve eğitim kayıtlarınız kendiliğinden sorgulandı. Bu bir kanıttır, karar değil: onayı bir editör adıyla verir.',
        'yabanci' => 'Bildirdiğiniz adres alındı. Bir editör sayfayı açıp derecenizi görecek ve sonucu adıyla bildirecektir. Belgenin kendisi istenmez ve saklanmaz.',
    ];
    cikti(['ok' => true, 'hesap' => hs_gorunum($h), 'mesaj' => $mesajlar[$tur] ?? 'Alındı.']);
}

/* =====================================================================
   PANEL ÖZETİ
   Panelin karşıladığı dört soru:
     1. Bende bekleyen ne var?      (yapılacak işler)
     2. Kendi çalışmalarım nerede?  (durum, rapor, okunma, şerh)
     3. Sistemin nabzı nedir?       (toplam sayılar)
     4. Ben ne yaptım?              (hakemlik ve oy geçmişi, yazarlığa kalan)
   Her sayı kayıtlardan anlık hesaplanır; hiçbir yerde ayrıca tutulmaz,
   böylece gösterilen sayı ile gerçek arasında fark oluşamaz.
   ===================================================================== */
if ($yol === '/panel/ozet' && $metod === 'GET') {
    $h = hs_gerek();
    $ben   = hs_eposta_anahtar((string)$h['eposta']);
    $benAd = tg_ad_anahtar(hs_gorunen_ad($h));
    $editor = hs_editor_mu($h);
    $dd = tg_dogrulama_durum($h['dogrulama'] ?? null);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $oku = oku_json('yazi-oku.json', []); if (!is_array($oku)) $oku = [];

    $benim = []; $bekleyen = []; $hakemligim = []; $oylarim = 0;
    /* Aşama sayaçları ('asama_*') hakemliğin neresinde kaç çalışma
       olduğunu ayrı ayrı verir. Eski 'hakemli' ve 'onayli' anahtarları
       geriye uyum için yerinde kalır. */
    $sayac = ['calisma' => 0, 'hakemli' => 0, 'onayli' => 0, 'hakem' => 0, 'okuma' => 0,
              'acik_oylama' => 0, 'hakem_aranan' => 0, 'serh' => 0,
              'asama_aranan' => 0, 'asama_suruyor' => 0, 'asama_onayli' => 0,
              /* 'reddedildi' basamağı buraya 19 Ağustos 2026'da eklendi.
                 Basamak koda çok önce yazılmıştı ama ERİŞİLEMEZDİ: iki ret
                 gelince tur 'yazi'ya döndüğü için aşama 'yok' çıkıyordu.
                 Anahtar eksik kaldığında sayaç isset() denetimine takılıp
                 sessizce düşer; sayı yanlış değil, YOK olur. */
              'asama_reddedildi' => 0];
    $hakemAdlari = [];

    foreach ($y as $e) {
        if (!is_array($e)) continue;
        $sayac['calisma']++;
        /* KARAR: değiştirildi. Bu bir SAYAÇ, süreç kapısı değil. Panelde
           "hakemli" diye gösterilen sayı, okura hakem değerlendirmesinden
           geçmiş çalışma sayısı olarak okunur. tur='hakemli' ise yazarın
           çalışmayı hakemliğe açtığını söyler, tek rapor gelmemiş olabilir;
           bu yüzden sayaç rapor görmüş çalışmaları sayar. */
        if (tg_hakemden_gecti($e)) $sayac['hakemli']++;
        $as = tg_hakem_asamasi($e);
        if (isset($sayac['asama_' . $as])) $sayac['asama_' . $as]++;
        $onay = tg_onay_durumu($e);
        if ($onay['onayli']) $sayac['onayli']++;
        $o = $oku[(string)($e['id'] ?? '')] ?? null;
        $okumaTekil = is_array($o) ? count($o['tekil'] ?? []) : 0;
        $sayac['okuma'] += $okumaTekil;
        $sayac['serh']  += tg_serh_sayi($e);
        if (tg_hakem_araniyor($e)) $sayac['hakem_aranan']++;

        foreach (tg_dizi($e['hakemler'] ?? null) as $hk) {
            if (!is_array($hk)) continue;
            $ad = trim((string)($hk['ad'] ?? ''));
            if ($ad !== '') $hakemAdlari[tg_ad_anahtar($ad)] = true;
            /* Benim hakemliklerim */
            if ($benAd !== '' && tg_ad_anahtar($ad) === $benAd) {
                $hakemligim[] = [
                    'calisma' => (string)($e['baslik'] ?? ''), 'yol' => tg_yazi_yolu($e),
                    'karar'   => (string)($hk['karar'] ?? ''),
                    'rapor_var' => trim((string)($hk['rapor'] ?? '')) !== '',
                    'nitelik' => tg_rapor_nitelik($hk)['yeterli'],
                    'tarih'   => (string)($hk['tarih'] ?? ''),
                ];
            }
        }

        /* Açık oylamalar: SAYAÇ burada, yapılacak iş satırı
           bekleyen_isler() içinde. */
        foreach (tg_oylamalar($e) as $ov) {
            if (!tg_oylama_sonuc($ov)['kapali']) $sayac['acik_oylama']++;
            foreach (tg_dizi($ov['oylar'] ?? null) as $o2) {
                if (is_array($o2) && hs_eposta_anahtar((string)($o2['eposta'] ?? '')) === $ben) $oylarim++;
            }
        }

        /* Kendi çalışmalarım */
        if (tg_yazar_mi($e, $h)) {
            $tamRapor = 0;
            foreach (tg_dizi($e['hakemler'] ?? null) as $hk) {
                if (is_array($hk) && trim((string)($hk['rapor'] ?? '')) !== '') $tamRapor++;
            }
            $benim[] = [
                'baslik' => (string)($e['baslik'] ?? ''), 'yol' => tg_yazi_yolu($e),
                /* Yazarın kendi düzenleme ekranı: bağlantı beklemesine
                   gerek yok, hesabıyla girdiği için yetkisi var. */
                'duzenle' => '/yazar.php?y=' . rawurlencode((string)($e['slug'] ?? ($e['id'] ?? ''))),
                'tur' => (string)($e['tur'] ?? ''), 'tarih' => (string)($e['tarih'] ?? ''),
                /* Yazar kendi çalışmasının yolunu ('tur') zaten biliyor;
                   bilmediği, o yolun neresinde olduğudur. İkisi yan yana
                   durur ki ekran "hakemli" yazıp da rapor beklediğini
                   söylemeyen bir satır üretmesin. */
                'asama' => tg_hakem_asamasi($e),
                /* Rozetin YAZISI da buradan gider. Panel bunu kendisi
                   üretiyordu ("tur==='hakemli' ? Hakemli : Hakemsiz") ve
                   iki şeyi birden yanlış söylüyordu: raporsuz çalışmaya
                   "Hakemli", iki ret almış çalışmaya "Hakemsiz". Metin
                   tek kaynaktan (tg_asama_metni) gelir; panelin dili
                   sunucuda bilinmediği için iki dil de yollanır. */
                'asama_ad'    => tg_asama_metni(tg_hakem_asamasi($e), false, true),
                'asama_ad_en' => tg_asama_metni(tg_hakem_asamasi($e), true,  true),
                'onayli' => $onay['onayli'], 'kabul' => $onay['kabul'], 'esik' => $onay['esik'],
                'rapor' => $tamRapor, 'okuma' => $okumaTekil, 'serh' => tg_serh_sayi($e),
                'kilit' => tg_kilit_durum($e),
                'araniyor' => tg_hakem_araniyor($e),
                /* Hakemliğe açma eylemi için gereken iki bilgi: hangi
                   çalışma, ve bugün açılabilir mi. İki ret almış ya da
                   geri çekilmiş çalışma açılamaz. */
                'anahtar' => (string)($e['slug'] ?? ($e['id'] ?? '')),
                /* KARAR: iki alan da olduğu gibi bırakıldı. Bunlar yol
                   denetimidir, aşama değil: "bu çalışma hakemliğe açık mı"
                   sorusunu sorarlar. Hakemliğe açık ama rapor almamış bir
                   çalışma yeniden açılamaz, kapatılabilir; tg_hakemden_gecti()
                   konsaydı yazar aynı çalışmayı ikinci kez açabilirdi. */
                'acilabilir' => (string)($e['tur'] ?? '') !== 'hakemli'
                                && !yazar_kilitli($e)
                                && !(function_exists('tg_geri_cekildi') && tg_geri_cekildi($e)),
                'kapatilabilir' => (string)($e['tur'] ?? '') === 'hakemli'
                                && !(bool)array_filter((array)($e['hakemler'] ?? []), 'is_array'),
                'acik_oylama' => tg_acik_oylama_sayisi($e),
                'kurulus' => tg_kurulus_istisnasi($e),
            ];
        }
    }
    $sayac['hakem'] = count($hakemAdlari);

    /* ---- SİZİ BEKLEYEN İŞLER ----
       Liste burada KURULMAZ, tek kaynaktan alınır (bekleyen_isler).
       Eskiden rapor, oy, revizyon, kurul, belge, gönüllü ve atama
       satırları bu ucun içinde yazılıydı ve sağ üstteki rozet aynı
       işleri /hesap/durum içinde İKİNCİ KEZ sayıyordu. İki kopya iki
       kez ayrıştı; gerekçe bekleyen_isler()'in başında yazılı. */
    $bekleyen = bekleyen_isler($h);

    usort($benim, fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
    usort($hakemligim, fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));

    $esikOy = (int)tg_ayar('oy_yazarlik_esigi', 4);
    cikti([
        'ok' => true,
        'bekleyen'   => $bekleyen,
        'calismalar' => $benim,
        'sayac'      => $sayac,
        'gecmis'     => [
            'hakemlik'   => $hakemligim,
            'oy'         => $oylarim,
            'oy_esik'    => $esikOy,
            'oy_kalan'   => max(0, $esikOy - $oylarim),
            'yazar'      => hs_rol_var($h, 'yazar'),
        ],
        'dogrulama' => ['durum' => $dd['durum'], 'yeterli' => $dd['yeterli']],
        'editor' => $editor,
    ]);
}

/* =====================================================================
   HESAP DAVETİ
   ---------------------------------------------------------------------
   Bir kişiye hesap açmanın iki yolu vardır ve buradaki bilerek
   seçilmiştir: PAROLA HİÇBİR ZAMAN ÜRETİLMEZ VE İLETİLMEZ.

   Baş editör bir kişiyi davet ettiğinde sistem parolasız bir hesap
   iskeleti kurar ve tek kullanımlık bir anahtar üretir. Anahtarın
   kendisi hiçbir yerde saklanmaz; kayda yalnızca özeti düşer. Davet
   eden kişi bağlantıyı bir kez görür ve iletir; davet edilen kişi
   bağlantıyı açıp kendi parolasını kurar. Böylece parola ne bir
   e-postada, ne bir sohbette, ne de bir kayıtta durur.

   Bağlantı on dört günde düşer ve bir kez kullanılır. Parolasız hesap
   giriş yapamaz: password_verify boş bir özetle her zaman yanlış döner.
   ===================================================================== */
if ($yol === '/yonetim/hesap-davet' && $metod === 'POST') {
    $h = hs_gerek();
    if (!hs_bas_yetki($h)) cikti(['ok' => false, 'hata' => 'Bu işlem yalnızca baş editörlere açıktır.'], 403);
    $g = govde_json(); if (!$g) $g = $_POST;
    $tmz = fn($k, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g[$k] ?? ''))), 0, $n);

    $eposta = hs_eposta_anahtar((string)($g['eposta'] ?? ''));
    if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'Geçerli bir e-posta adresi girin.'], 400);
    $ad = $tmz('ad', 120);
    if ($ad === '') cikti(['ok' => false, 'hata' => 'Davet edilecek kişinin adını yazın.'], 400);

    $var = hs_bul($eposta);
    /* Parolası olan bir hesabı davet etmek, o hesabı ele geçirmenin bir
       yolu olurdu. Kişi parolasını unuttuysa parola yenileme yolundan
       gider; davet yalnızca hesabı olmayana açılır. */
    if ($var !== null && trim((string)($var['parola'] ?? '')) !== '') {
        cikti(['ok' => false, 'hata' => 'Bu adreste parolası kurulmuş bir hesap zaten var. Davet yalnızca hesabı olmayan bir kişiye gönderilebilir.'], 409);
    }

    $hedef = $var ?? [
        'eposta'  => $eposta,
        'parola'  => '',
        'ad'      => $ad,
        'unvan'   => '',
        'kurum'   => '',
        'roller'  => [],
        'kullanici' => '',
        'katilim' => date('c'),
        'giris'   => [],
    ];
    $hedef['ad']    = $ad;
    $unvan = hs_unvan_anahtar($tmz('unvan', 60));
    if ($unvan !== '') $hedef['unvan'] = $unvan;
    $kurum = $tmz('kurum', 220);
    if ($kurum !== '') $hedef['kurum'] = $kurum;

    /* ---- DAVETİN NEYE DAVET OLDUĞU ----
       Panel bir seçim gönderir; uç o seçime GÜVENMEZ, aynı işleve
       yeniden sorar. hs_davet_rol_coz() kapalı bir seçenekte null döner
       ve burada 403 olur. İstemcinin gönderdiği rolü olduğu gibi
       yazmak, davet düğmesini bir yetki dağıtıcısına çevirirdi.

       SEÇİM YAZILMAMIŞSA DAVET GÖNDERİLMEZ. Eskiden 'okur' varsayılıyordu
       ve o satır kurul kararıyla kaldırıldı (14 Ağustos 2026): okumak
       için kayıt gerekmediğinden okur daveti bir karşılık taşımıyordu.
       Varsayılanı bir sonraki satıra —aday hakeme— kaydırmak, sessizce
       yetki taşıyan bir davet üretirdi; en kötüsü o olurdu. Bu yüzden
       varsayılan yok: neye davet edildiği yazılmamışsa uç sorar. */
    $rolK = $tmz('rol', 30);
    if ($rolK === '') {
        cikti(['ok' => false, 'hata' => 'Neye davet ettiğinizi seçin: davetin hangi rolü taşıdığı yazılmadan gönderilemez.'], 400);
    }
    $secim = hs_davet_rol_coz($h, $rolK);
    if ($secim === null) {
        /* Sebebi de söylenir: "yetkiniz yok" diyen bir yanıt, hangi
           kuralın kapattığını söylemezse kişi kendinde kusur arar. */
        $sebep = '';
        foreach (hs_davet_rolleri($h) as $r) if ($r['k'] === $rolK) $sebep = (string)$r['sebep'];
        cikti(['ok' => false, 'hata' => $sebep !== '' ? $sebep : 'Bu rolle davet gönderemezsiniz.'], 403);
    }
    /* Roller davetin kendisinde durur, hesabın içinde değil: davet
       kullanılana kadar kişi hiçbir şey değildir. Kullanıldığı an
       hesap-kur ucu bunları yazar. */
    $hedef['roller'] = array_values(array_unique(array_merge(
        array_values(array_filter((array)($hedef['roller'] ?? []), 'is_string')),
        (array)$secim['rol'])));

    /* ---- DAVET, DOKTORAYI DA DOĞRULAR ----
       14 Ağustos 2026 kurul kararı. Bir editör birini hakem olarak
       davet ediyorsa, o kişinin doktoralı olduğunu bilerek davet
       ediyordur; ayrıca belge istemek, editörün kendi kararına
       güvenmemek olurdu. Gerekçenin tamamı ortak.php'de
       tg_dogrulama_editor()'ün başındadır.

       BAYRAK LİSTEDEN GELİR, BURADA YAZILMAZ. Hangi davetin doğrulama
       taşıdığı hs_davet_rolleri() içindeki 'dogrular' alanındadır;
       panel de o listeden çizer. İki yere yazılsaydı bir gün ayrışır
       ve ekranda yazan ile kaydedilen ayrı şeyler olurdu.

       ZATEN DOĞRULANMIŞ BİR KAYIT EZİLMEZ: daha önce bir editör adıyla
       arkasında durduysa o ad kalır. Yeni bir davet eski bir sorumluluk
       kaydını silemez. */
    if (!empty($secim['dogrular'])) {
        $ddVar = tg_dogrulama_durum($hedef['dogrulama'] ?? null);
        if ($ddVar['durum'] !== 'onayli') {
            $hedef['dogrulama'] = tg_dogrulama_editor(hs_gorunen_ad($h), 'davet');
        }
    }

    $jeton = bin2hex(random_bytes(32));
    $hedef['davet'] = [
        'ozet'   => hash('sha256', $jeton),
        'olusma' => date('c'),
        'bitis'  => date('c', time() + 14 * 86400),
        'kuran'  => hs_gorunen_ad($h),
    ];
    hs_kaydet($hedef);

    cikti(['ok' => true, 'ad' => $ad, 'eposta' => $eposta,
           'bitis' => $hedef['davet']['bitis'],
           'rol'      => $rolK,
           'rol_ad'   => (string)$secim['ad'],
           /* Panel, kimin neyi doğruladığını yazabilsin diye. Yanıtta
              yoksa ekran "davet gönderildi" der ve doğrulamanın olduğunu
              hiç söylemez; sessizce yetki taşıyan bir davet olurdu. */
           'dogrulandi'   => !empty($secim['dogrular']),
           'dogrulayan'   => !empty($secim['dogrular']) ? tg_dogrulama_onaylayan($hedef['dogrulama'] ?? null) : '',
           /* Baş editörlükte davet TEK BAŞINA YETMEZ ve panel bunu
              söylemek zorundadır: rol her okumada ayar.php'deki kurul
              kaydından çözülür. Yarım yapılmış bir atamayı tamam gibi
              göstermek, kurulun kendisini belirsizleştirir. */
           'kurul_kaydi_gerek' => !empty($secim['kurul_kaydi_gerek']),
           /* Bağlantı yalnızca bu yanıtta görünür ve bir daha üretilemez;
              kaybolursa yeni bir davet çıkarılır, eskisi düşer. */
           'bag'   => rtrim(tg_kok(), '/') . '/hesap-kur.php?k=' . $jeton]);
}

/* Davet edilebilecek roller. Panel bu listeden çizer; kapalı olanlar
   da sebebiyle döner, çünkü olmayan bir seçenek neden olmadığını
   söylemez. Liste hs_davet_rolleri() ile üretilir — panelin gördüğü ile
   ucun uyguladığı aynı işlevden gelir ve ayrışamaz. */
if ($yol === '/yonetim/davet-rolleri' && $metod === 'GET') {
    $h = hs_gerek();
    if (!hs_bas_yetki($h)) cikti(['ok' => false, 'hata' => 'Bu işlem yalnızca baş editörlere açıktır.'], 403);
    $out = [];
    foreach (hs_davet_rolleri($h) as $r) {
        $out[] = ['k' => $r['k'], 'ad' => $r['ad'], 'ack' => $r['ack'],
                  'acik' => (bool)$r['acik'], 'sebep' => (string)$r['sebep'],
                  'kurul_kaydi_gerek' => !empty($r['kurul_kaydi_gerek'])];
    }
    cikti(['ok' => true, 'roller' => $out]);
}

/* Davet anahtarını çözer: kimin için olduğunu söyler, başka bir şey
   söylemez. E-posta adresi burada da gösterilmez. */
if ($yol === '/davet-bilgi' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'davet:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 40) cikti(['ok' => false, 'hata' => 'Çok fazla deneme. Bir süre sonra tekrar deneyin.'], 429);
    $d = hs_davet_coz((string)($g['k'] ?? ''));
    if ($d === null) cikti(['ok' => false, 'hata' => 'Bu bağlantı geçersiz ya da süresi dolmuş.'], 404);
    /* Karşılama sayfası, kişiye burada NE YAPABİLECEĞİNİ anlatır; bunun
       için rolünü bilmesi gerekir. Yalnızca iki kutucuk döner, rollerin
       tamamı değil: davet ekranının başka bir şeye ihtiyacı yok. */
    cikti(['ok' => true, 'ad' => hs_gorunen_ad($d), 'kurum' => (string)($d['kurum'] ?? ''),
           'editor' => hs_editor_mu($d),
           'bas_yetki' => hs_editor_atama_yetkisi($d),
           'bitis' => (string)($d['davet']['bitis'] ?? '')]);
}

/* Parolayı kişinin kendisi kurar. Anahtar burada tükenir. */
if ($yol === '/davet-parola' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'davetp:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 25) cikti(['ok' => false, 'hata' => 'Çok fazla deneme. Bir süre sonra tekrar deneyin.'], 429);
    $d = hs_davet_coz((string)($g['k'] ?? ''));
    if ($d === null) cikti(['ok' => false, 'hata' => 'Bu bağlantı geçersiz ya da süresi dolmuş.'], 404);
    $parola = (string)($g['parola'] ?? '');
    if (mb_strlen($parola, 'UTF-8') < 10) cikti(['ok' => false, 'hata' => 'Parola en az 10 karakter olmalıdır.'], 400);
    $d['parola'] = password_hash($parola, PASSWORD_DEFAULT);
    $d['parola_tarih'] = date('c');
    unset($d['davet']);
    hs_kaydet($d);
    session_regenerate_id(true);
    hs_giris_yap((string)$d['eposta']);
    cikti(['ok' => true, 'hesap' => hs_gorunum($d)]);
}

/* ---- HESAP LİSTESİ (editör ve üstü) ---- */
if ($yol === '/hesap/liste' && $metod === 'GET') {
    $h = hs_gerek();
    if (!hs_editor_mu($h)) cikti(['ok' => false, 'hata' => 'Bu liste yalnızca editörlere açıktır.'], 403);
    $out = [];
    foreach (hs_oku() as $x) { if (is_array($x)) $out[] = hs_gorunum($x); }
    usort($out, fn($a, $b) => strcmp((string)$b['katilim'], (string)$a['katilim']));
    cikti(['ok' => true, 'hesaplar' => $out]);
}

/* ---- ROL VER / AL (yalnızca baş editör ve yönetici) ----
   Editör listesine ekleme ve çıkarma buradan yapılır; her işlem kaydedilir. */
if ($yol === '/hesap/rol' && $metod === 'POST') {
    $h = hs_gerek();
    if (!hs_bas_yetki($h)) cikti(['ok' => false, 'hata' => 'Bu işlem yalnızca baş editörlere açıktır.'], 403);
    /* Yetki süreli olabilir: baş editörlük kaydı durur ama editör
       listesini değiştirme yetkisi verilen tarihte kapanır. */
    if (!hs_editor_atama_yetkisi($h)) {
        $bt = hs_editor_yetki_bitis((string)($h['eposta'] ?? ''));
        cikti(['ok' => false, 'hata' => 'Editör atama yetkiniz '
            . hs_tarih_yaz($bt) . ' tarihinde sona erdi. Baş editörlük kaydınız ve öteki yetkileriniz yerinde durur.'], 403);
    }
    $g = govde_json(); if (!$g) $g = $_POST;
    $hedef = hs_bul((string)($g['eposta'] ?? ''));
    if ($hedef === null) cikti(['ok' => false, 'hata' => 'Hesap bulunamadı.'], 404);
    $rol = (string)($g['rol'] ?? '');
    /* ---- KAPI: baş editörlük buradan verilemez ----
       Liste bilerek üç rolle sınırlıdır. 'bas_editor' buraya
       eklenemez, çünkü baş editörlüğün tek kaynağı ayar.php'dir:
       kurucu kaydı kapalıdır ve sonradan yazılamaz, görevdeki baş
       editörlük ise yazılı süresiyle birlikte 'gorevdeki_bas_editorler'
       listesine geçer ve tg_atama_gecerli() kapısından geçer. Bir
       hesabın içine yazılan yetki geri alınamaz hâle gelirdi; bildiride
       verilen "görevdeki baş editörlük devredilebilir ve geri
       alınabilir" sözü ancak böyle doğru kalır. */
    if (!in_array($rol, ['editor', 'hakem', 'yazar'], true)) cikti(['ok' => false, 'hata' => 'Bu rol buradan verilemez.'], 400);
    $ver = !empty($g['ver']);
    $r = is_array($hedef['roller'] ?? null) ? $hedef['roller'] : [];
    if ($ver && !in_array($rol, $r, true)) $r[] = $rol;
    if (!$ver) $r = array_values(array_diff($r, [$rol]));
    $hedef['roller'] = array_values(array_unique($r));
    $hedef['rol_kayit'][] = ['rol' => $rol, 'ver' => $ver, 'tarih' => date('c'), 'yapan' => hs_gorunen_ad($h)];
    if (count($hedef['rol_kayit']) > 60) array_shift($hedef['rol_kayit']);
    hs_kaydet($hedef);

    /* Editör listesi herkese açık sayfada gösterilir */
    $ed = [];
    foreach (hs_oku() as $x) {
        if (!is_array($x)) continue;
        if (in_array('editor', (array)($x['roller'] ?? []), true)) {
            $ed[] = ['ad' => (string)($x['ad'] ?? ''), 'unvan' => (string)($x['unvan'] ?? ''), 'kurum' => (string)($x['kurum'] ?? '')];
        }
    }
    yaz_json('editorler.json', $ed);
    cikti(['ok' => true, 'hesap' => hs_gorunum($hedef)]);
}

/* ---- BELGE ONAYI (editör ve üstü) ---- */
if ($yol === '/hesap/belge-onay' && $metod === 'POST') {
    $h = hs_gerek();
    if (!hs_editor_mu($h)) cikti(['ok' => false, 'hata' => 'Bu işlem yalnızca editörlere açıktır.'], 403);
    $g = govde_json(); if (!$g) $g = $_POST;
    $hedef = hs_bul((string)($g['eposta'] ?? ''));
    if ($hedef === null) cikti(['ok' => false, 'hata' => 'Hesap bulunamadı.'], 404);
    $onay = !empty($g['onay']);
    if (!is_array($hedef['dogrulama'] ?? null)) cikti(['ok' => false, 'hata' => 'Bu hesap henüz belge sunmamış.'], 400);
    $hedef['dogrulama']['onay'] = $onay;
    $hedef['dogrulama']['durum'] = $onay ? 'onayli' : 'eksik';
    $hedef['dogrulama']['onay_tarih'] = date('c');
    $hedef['dogrulama']['onaylayan'] = hs_gorunen_ad($h);
    $r = is_array($hedef['roller'] ?? null) ? $hedef['roller'] : [];
    if ($onay) {
        $r = array_values(array_diff($r, ['aday_hakem']));
        if (!in_array('hakem', $r, true)) $r[] = 'hakem';
    }
    $hedef['roller'] = array_values(array_unique($r));
    hs_kaydet($hedef);
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    eposta_gonder((string)$hedef['eposta'], $marka . ($onay ? ' | Doktoranız doğrulandı' : ' | Doktoranız doğrulanamadı'),
        "Sayın " . hs_gorunen_ad($hedef) . ",\n\n" .
        ($onay
          ? "Sunduğunuz belge doğrulandı. Artık hakemlik yapabilirsiniz. Hakem aranan çalışmaları şu adreste görebilirsiniz:\n" . tg_kok() . "/bekleyen.php\n\nHakemlik yaptıktan sonra kendi çalışmanızı gönderme hakkını da kazanırsınız."
          : "Sunduğunuz belge doğrulanamadı. Bu, niteliğinize ilişkin bir değerlendirme değildir; çoğu zaman kod ya da adres hatasından kaynaklanır. Panelinizden yeniden sunabilirsiniz.") .
        "\n\n" . $marka . "\n" . tg_kok() . "\n");
    cikti(['ok' => true, 'hesap' => hs_gorunum($hedef)]);
}

/* =====================================================================
   EDİTÖR İŞLEMLERİ
   Editörler ve baş editörler, istedikleri çalışmaya hakem atayabilir ve
   editöryal not düşebilir. Yapılan her işlem, yapanın adı ve saatiyle
   birlikte kaydedilir ve okuyucuya gösterilir. Kimse kendi çalışmasına
   hakem atayamaz.
   ===================================================================== */

/* Editör kimliği: oturumdaki hesap ya da yönetici oturumu */
function ed_kimlik(): array {
    $h = hs_oturum();
    if ($h !== null && hs_editor_mu($h)) {
        return ['ad' => hs_gorunen_ad($h), 'tur' => hs_rol_var($h, 'bas_editor') ? 'bas_editor' : 'editor',
                'eposta' => (string)$h['eposta'], 'hesap' => $h];
    }
    if (girisli()) {
        return ['ad' => (string)tg_ayar('yonetici_ad', 'Sistem yönetimi'), 'tur' => 'yonetim', 'eposta' => '', 'hesap' => null];
    }
    cikti(['ok' => false, 'hata' => 'Bu işlem yalnızca editörlere açıktır.'], 403);
}

/* =====================================================================
   ARŞİV BİLDİRİMİ · YALNIZCA ÖNİZLEME, HİÇBİR ŞEY GÖNDERİLMEZ
   ---------------------------------------------------------------------
   İki eksik biliniyor ve ikisi de gerçek kişilere posta yazmayı
   gerektiriyor:
     (11) Etik beyanı hiç istenmemiş arşiv çalışmalarının yazarları,
     (12) Yazarlıkları hiç bildirilmemiş ortak yazarlar.

   POSTA GERİ ALINAMAZ, BU YÜZDEN BURADA GÖNDERİLMEZ. Bu uç, gönderilecek
   iletinin TAM METNİNİ ve kimlere gideceğini gösterir; gönderme eylemi
   ayrıdır ve bilerek yazılmamıştır. Bir kararın önünü açmakla o kararı
   vermek aynı şey değildir: hazırlık geri alınabilir, gönderim değil.

   NİÇİN ÖNİZLEME YETMİYOR SANILIR AMA YETER: bu işi bekleten şey teknik
   bir eksik değil, bir karardı. Karar verilebilmesi için görülmesi
   gereken üç şey vardı — ileti ne diyor, kaç kişiye gidiyor, gidemeyen
   var mı. Üçü de burada.

   ADRESLER MASKELENİR. Editör kaç kişiye gideceğini ve kime
   gidemeyeceğini bilmeli; adreslerin tamamını okumasına gerek yok.
   İhtiyacı olmayan veriyi gösteren bir ekran, sızıntının en sıradan
   biçimidir (aynı gerekçe /editor/arsiv-bosluk ucunda da yazılı).
   ===================================================================== */
function ab_maske(string $e): string {
    $e = trim($e);
    $p = strpos($e, '@');
    if ($p === false || $p < 1) return $e === '' ? '' : '***';
    $ad = substr($e, 0, $p); $alan = substr($e, $p);
    $g = mb_substr($ad, 0, 1, 'UTF-8');
    return $g . str_repeat('*', max(2, mb_strlen($ad, 'UTF-8') - 1)) . $alan;
}

if ($yol === '/yonetim/arsiv-bildirim-onizleme' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    $kok   = tg_kok();
    $bas   = tg_etik_baslangic();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];

    /* ---- (11) Etik beyanı istenmemiş çalışmaların yazarları ---- */
    $etik = ['alici' => [], 'adressiz' => [], 'konu' => '', 'govde' => ''];
    $etik['konu'] = $marka . ' | Çalışmanız için etik kurul beyanı';
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        if (!in_array(tg_etik_hal($e), ['sorulmadi', 'eksik', 'askida'], true)) continue;
        $ad   = trim((string)($e['yazar'] ?? ''));
        $adres = trim((string)(($e['yazar_erisim']['eposta_acik'] ?? '')));
        if ($adres === '') $adres = trim((string)((($e['yazar_bilgi'] ?? [])['eposta'] ?? '')));
        $satir = ['ad' => $ad, 'baslik' => (string)($e['baslik'] ?? ''),
                  'yol' => $kok . tg_yazi_yolu($e), 'adres' => ab_maske($adres)];
        if ($adres === '') $etik['adressiz'][] = $satir; else $etik['alici'][] = $satir;
    }
    /* İLETİ, YAPILMAMIŞ BİR ŞEYİ SUÇLAMAZ. Beyan bu yazarlardan hiç
       istenmedi; ileti bunu açıkça söyler ve bir yükümlülük değil bir
       olanak sunar. Suçlayan bir ileti, cevap alamaz. */
    $etik['govde'] =
        "Sayın [YAZAR],\n\n"
      . "\"[ÇALIŞMA]\" başlıklı çalışmanız " . $marka . " arşivinde açık erişimle yayımlanıyor.\n\n"
      . "Sistem " . ($bas !== '' ? $bas . ' tarihinden' : 'bir süredir') . " bu yana her gönderimde etik kurul beyanı istiyor: "
      . "izin gerekiyorsa kurul adı, karar tarihi ve karar numarası; gerekmiyorsa bunun beyanı. "
      . "Çalışmanız o tarihten önce yayımlandığı için bu beyan sizden HİÇ İSTENMEDİ ve bugün de bir yükümlülük değildir.\n\n"
      . "Yine de eklemek isterseniz kapı açık: çalışmanızın sayfasındaki erişim koduyla yazar panelinize girip "
      . "\"Etik kurul beyanı\" bölümünü doldurmanız yeterli. Belge istenmiyor ve saklanmıyor; yalnızca kurul adı, "
      . "tarih ve numara yayımlanıyor ki isteyen doğrudan veren kurula sorabilsin.\n\n"
      . "Çalışmanızın sayfası:\n[ADRES]\n\n"
      . "Bu ileti bir uyarı değil, bir bilgilendirmedir. Bir şey yapmazsanız çalışmanızda hiçbir şey değişmez; "
      . "sayfada \"Sorulmadı (arşiv kaydı)\" yazmayı sürdürür.\n\n"
      . $marka . "\n" . $kok . "\n";

    /* ---- (12) Yazarlığı bildirilmemiş ortak yazarlar ---- */
    $ortak = ['alici' => [], 'adressiz' => [], 'konu' => '', 'govde' => ''];
    $ortak['konu'] = $marka . ' | Bir çalışmada yazar olarak görünüyorsunuz';
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        foreach (tg_dizi($e['yazar_liste'] ?? null) as $ix => $ya) {
            if ($ix === 0 || !is_array($ya)) continue;         /* gönderen/iletişim yazarı */
            $adres = trim((string)($ya['eposta'] ?? ''));
            $satir = ['ad' => (string)($ya['ad'] ?? ''), 'baslik' => (string)($e['baslik'] ?? ''),
                      'yol' => $kok . tg_yazi_yolu($e), 'adres' => ab_maske($adres)];
            if ($adres === '') $ortak['adressiz'][] = $satir; else $ortak['alici'][] = $satir;
        }
    }
    $ortak['govde'] =
        "Sayın [YAZAR],\n\n"
      . "\"[ÇALIŞMA]\" başlıklı çalışmada yazar olarak görünüyorsunuz ve bu " . $marka . " arşivinde "
      . "açık erişimle yayımlanıyor.\n\n"
      . "Bu bildirim size daha önce gitmedi. Sistem bugün her gönderimde eklenen her yazara bildirim gönderiyor "
      . "-- adının kullanıldığını bilmeyen bir yazarlık, yazarlık değildir -- ama bu çalışma o düzenden önce kayda girdi. "
      . "Gecikme bizdendir.\n\n"
      . "Çalışmanın sayfası:\n[ADRES]\n\n"
      . "Yapmanız gereken bir şey yok. Adınızın orada yer almasını istemiyorsanız ya da bilginiz dışında eklendiyse "
      . "bize yazın; kayıt gerekçesiyle birlikte düzeltilir. Hesap açmak zorunda değilsiniz, ama açarsanız "
      . "çalışma kendi panelinizde görünür ve başına bir şey geldiğinde haberiniz olur.\n\n"
      . $marka . "\n" . $kok . "\n";

    cikti(['ok' => true, 'gonderilmedi' => true,
        'not' => 'Bu uç hiçbir ileti göndermez. Gönderme eylemi ayrıdır ve bilerek yazılmamıştır.',
        'etik'  => ['konu' => $etik['konu'], 'govde' => $etik['govde'],
                    'sayi' => count($etik['alici']), 'adressiz' => count($etik['adressiz']),
                    'liste' => array_slice($etik['alici'], 0, 200),
                    'adressiz_liste' => array_slice($etik['adressiz'], 0, 200)],
        'ortak' => ['konu' => $ortak['konu'], 'govde' => $ortak['govde'],
                    'sayi' => count($ortak['alici']), 'adressiz' => count($ortak['adressiz']),
                    'liste' => array_slice($ortak['alici'], 0, 200),
                    'adressiz_liste' => array_slice($ortak['adressiz'], 0, 200)]]);
}

/* =====================================================================
   ARŞİV BOŞLUKLARI · SALT OKUNUR

   ÖLÇÜLEN KUSUR — 18 Ağustos 2026. İki eksik biliniyordu ama HİÇBİR
   YERDE SAYILMIYORDU: (a) etik beyanı hiç istenmemiş arşiv çalışmaları,
   (b) ortak yazarı e-posta adresi olmadan kaydedilmiş çalışmalar — o
   yazarlara yazarlıkları hiç bildirilmedi.

   Bir eksiği bilmek ile onu ölçmek aynı şey değildir. Ölçülmeyen eksik,
   büyüklüğü bilinmediği için ya abartılır ya unutulur; ikisi de karar
   vermeyi engeller. Bu uç, editöre yalnızca SAYIYI ve LİSTEYİ verir.

   BURADA HİÇBİR ŞEY GÖNDERİLMEZ. Eksiği kapatmanın yolu gerçek kişilere
   posta yazmaktır ve posta geri alınamaz; geri alınamaz bir işi bir
   raporun yan etkisi yapmak, onu kazayla yapılabilir kılar. Uç yalnız
   okur. Gönderme kararı insanındır ve ayrı bir eylemdir.

   ADRESLERİN KENDİSİ DÖNMEZ: yalnız "adresi var mı" bilgisi döner.
   Bir raporun, ihtiyacı olmadığı hâlde adres toplaması sızıntının en
   sıradan biçimidir.
   ===================================================================== */
if ($yol === '/editor/arsiv-bosluk' && $metod === 'GET') {
    ed_kimlik();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $etikL = []; $ortakL = [];
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        $hal = tg_etik_hal($e);
        $satir = [
            'baslik' => (string)($e['baslik'] ?? ''),
            'yol'    => tg_yazi_yolu($e),
            'tarih'  => substr((string)($e['tarih'] ?? ''), 0, 10),
        ];
        if ($hal === 'sorulmadi' || $hal === 'eksik' || $hal === 'askida') {
            $etikL[] = $satir + ['hal' => $hal, 'hal_ad' => tg_etik_hal_ad($hal, false),
                                 'hal_ad_en' => tg_etik_hal_ad($hal, true)];
        }
        /* Ortak yazar: gönderen dışındaki yazarlar. Tek yazarlı bir
           çalışmada "ortak yazara haber verilmedi" diye bir eksik
           yoktur; sayıya katmak eksiği olduğundan büyük gösterirdi. */
        $adressiz = [];
        $liste = tg_dizi($e['yazar_liste'] ?? null);
        foreach ($liste as $ix => $ya) {
            if (!is_array($ya)) continue;
            if ($ix === 0) continue;                 /* gönderen/iletişim yazarı */
            if (trim((string)($ya['eposta'] ?? '')) === '') $adressiz[] = (string)($ya['ad'] ?? '');
        }
        if ($adressiz) $ortakL[] = $satir + ['kisiler' => $adressiz, 'sayi' => count($adressiz)];
    }
    usort($etikL,  fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
    usort($ortakL, fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
    $kisiSay = 0; foreach ($ortakL as $o) $kisiSay += (int)$o['sayi'];
    cikti(['ok' => true,
        'baslangic'  => tg_etik_baslangic(),
        'etik'       => ['sayi' => count($etikL),  'liste' => array_slice($etikL, 0, 200)],
        'ortak'      => ['sayi' => count($ortakL), 'kisi' => $kisiSay,
                         'liste' => array_slice($ortakL, 0, 200)],
        'toplam'     => count($y)]);
}

/* EDİTÖRÜN GÖRDÜĞÜ ÇALIŞMA LİSTESİ */
if ($yol === '/editor/calismalar' && $metod === 'GET') {
    $ed = ed_kimlik();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $out = [];
    foreach ($y as $ei2 => $e) {
        if (!is_array($e)) continue;
        $hk = [];
        foreach (tg_dizi($e['hakemler'] ?? null) as $h) {
            if (!is_array($h)) continue;
            $hk[] = [
                'ad'     => (string)($h['ad'] ?? ''),
                'karar'  => (string)($h['karar'] ?? ''),
                'rapor_var' => trim((string)($h['rapor'] ?? '')) !== '',
                'davet'  => davet_suresi_doldu($h) ? 'suresi_doldu' : (string)($h['davet_durum'] ?? ''),
                'davet_tarih' => (string)($h['davet_tarih'] ?? ''),
                /* Raporun tarihi. Davet ile rapor arasındaki gün sayısı
                   değerlendirme süresidir; editörün kendi ekranında bu
                   iki tarihten hesaplanır, ayrı bir uç açılmaz.
                   The date of the report. The number of days between the
                   invitation and the report is the assessment duration. */
                'rapor_tarih' => (string)($h['tarih'] ?? ''),
                'atayan' => tg_atayan_metin($h['atayan'] ?? null, false),
            ];
        }
        $gn = [];
        foreach (tg_dizi($e['gonulluler'] ?? null) as $g2) {
            if (!is_array($g2) || (string)($g2['durum'] ?? '') !== 'bekliyor') continue;
            $gn[] = ['kod' => (string)($g2['kod'] ?? ''), 'ad' => (string)($g2['ad'] ?? ''),
                     'unvan' => (string)($g2['unvan'] ?? ''), 'kurum' => (string)($g2['kurum'] ?? ''),
                     'orcid' => (string)($g2['orcid'] ?? ''), 'yetkinlik' => (string)($g2['yetkinlik'] ?? ''),
                     'sifat' => tg_sifat_metin($g2, false),
                     'belge' => (string)(($g2['dogrulama']['tur'] ?? '')),
                     'belge_kod' => (string)(($g2['dogrulama']['kod'] ?? '')),
                     'belge_url' => (string)(($g2['dogrulama']['url'] ?? ''))];
        }
        /* Yazarın önerdiği ve karar bekleyen hakemler. Editör panelinde
           gönüllülerin yanında durur: ikisi de "bir insan var, karar
           bekliyor" demektir. Adres gösterilmez; daveti sistem gönderir. */
        $on = [];
        foreach (($e['hakem_onerileri'] ?? []) as $o2) {
            if (!is_array($o2) || (string)($o2['durum'] ?? '') !== 'bekliyor') continue;
            $on[] = ['kod' => (string)($o2['kod'] ?? ''), 'ad' => (string)($o2['ad'] ?? ''),
                     'kurum' => (string)($o2['kurum'] ?? ''), 'orcid' => (string)($o2['orcid'] ?? ''),
                     'gerekce' => (string)($o2['gerekce'] ?? ''),
                     'oneren' => (string)($o2['oneren'] ?? ''),
                     'adres_var' => trim((string)($o2['eposta'] ?? '')) !== '',
                     /* Tekrar eden eşleşme sayısı: engel değil, uyarı.
                        Editör bu kişinin aynı yazarın kaç çalışmasına
                        daha hakem olduğunu görerek karar verir. */
                     'tekrar' => tg_hakem_tekrar($y, $ei2, (string)($o2['ad'] ?? ''), (string)($o2['orcid'] ?? '')),
                     'tarih' => (string)($o2['tarih'] ?? '')];
        }
        $onay = tg_onay_durumu($e);
        $out[] = [
            'id' => (string)($e['id'] ?? ''), 'slug' => (string)($e['slug'] ?? ''),
            'baslik' => (string)($e['baslik'] ?? ''), 'yazar' => (string)($e['yazar'] ?? ''),
            'tur' => (string)($e['tur'] ?? ''), 'tarih' => (string)($e['tarih'] ?? ''),
            /* Editör listesi de aşamayı 'tur'dan çıkarmasın: hakemliğe açılmış
               ama tek rapor almamış çalışma ile eşiği doldurmuş çalışma aynı
               'tur' değerini taşır. */
            'asama' => tg_hakem_asamasi($e),
            'rapor_sayisi' => tg_rapor_sayisi($e),
            'alan' => (string)($e['alan'] ?? ''), 'bcid' => (string)($e['bcid'] ?? ''),
            'hakemler' => $hk, 'gonulluler' => $gn, 'oneriler' => $on,
            'onayli' => $onay['onayli'], 'kabul' => $onay['kabul'], 'bagimsiz' => $onay['bagimsiz_kabul'],
            'araniyor' => tg_hakem_araniyor($e),
            'notlar' => array_values(array_filter(array_map(function($n){
                return is_array($n) ? ['metin' => (string)($n['metin'] ?? ''), 'kim' => (string)($n['kim'] ?? ''), 'tarih' => (string)($n['tarih'] ?? '')] : null;
            }, (array)($e['editor_notlari'] ?? [])))),
        ];
    }
    usort($out, fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
    cikti(['ok' => true, 'editor' => ['ad' => $ed['ad'], 'tur' => $ed['tur']], 'calismalar' => $out]);
}

/* EDİTÖR HAKEM ATAR
   Yazarın önerisi değildir; bu yüzden bağımsız hakemlik sayılır. */
if ($yol === '/editor/hakem-ata' && $metod === 'POST') {
    $ed = ed_kimlik();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id  = (string)($g['id'] ?? '');
    $hAd = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['ad'] ?? ''))), 0, 120);
    $hMail = mb_strtolower(trim((string)($g['eposta'] ?? '')), 'UTF-8');
    /* Dizinden atama: editör adres görmez, dizin kimliğini gönderir ve
       adresi sunucu çözer. Hakemin adresi editöre de açılmaz; sistemde
       adres yalnızca iletiyi göndermek için kullanılır. */
    /* ORCID çıkar çatışması denetimi için gerekir: ad yazılışı değişse
       de ORCID değişmez. Dizinden atamada dizindeki kayıttan okunur. */
    $hOrcid = (string)($g['orcid'] ?? '');
    $dk = preg_replace('/[^a-f0-9]/', '', (string)($g['dk'] ?? ''));
    if ($dk !== '') {
        $hd = dizin_hesap($dk);
        if ($hd === null) cikti(['ok' => false, 'hata' => 'Dizinde böyle bir hakem yok.'], 404);
        if (!empty($hd['dizin_gizli'])) cikti(['ok' => false, 'hata' => 'Bu kişi hakem dizininde görünmemeyi seçmiş.'], 403);
        $hAd   = trim((string)($hd['ad'] ?? ''));
        $hMail = mb_strtolower(trim((string)($hd['eposta'] ?? '')), 'UTF-8');
        $hOrcid = (string)($hd['orcid'] ?? '');
    }
    if ($hAd === '') cikti(['ok' => false, 'hata' => 'Hakem adı gerekli.'], 400);
    if ($hMail !== '' && !filter_var($hMail, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'E-posta geçersiz.'], 400);
    /* Kurul kararıyla askıya alınmış biri hakem olarak atanamaz.
       Bu, editörün de aşamayacağı bir sınırdır; kararı kurul verdi. */
    if (hakemlik_askida($hMail, $hAd)) {
        cikti(['ok' => false, 'hata' => 'Bu kişinin hakemlik yetkisi bir kurul oylamasıyla askıya alınmıştır. Kararı kurul verdiği için editör kararıyla aşılamaz.'], 403);
    }

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = null;
    foreach ($y as $ix => $e) { if ((string)($e['id'] ?? '') === $id) { $i = $ix; break; } }
    if ($i === null) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);

    /* Editör kendi çalışmasına hakem atayamaz */
    $anahtar = tg_ad_anahtar($ed['ad']);
    if ($anahtar !== '' && in_array($anahtar, tg_yazar_anahtarlari($y[$i]), true)) {
        cikti(['ok' => false, 'hata' => 'Bir editör kendi çalışmasına hakem atayamaz.'], 403);
    }
    /* Çıkar çatışması: kendi işi, ortak yazarlık ve karşılıklılık.
       Denetim ortak.php'deki tek kapıdan geçer; üç atama yolunun
       üçünde de aynı kural işler. Editörün kararı bu kapıyı açmaz:
       karşılıklı hakemlik bir yetki sorunu değil, bağımsızlık
       sorunudur.
       Adres de ölçüte girer: ad ve ORCID adayın kendi yazdığı
       alanlardır, adres ise iletinin gerçekten gitmesi gereken yerdir
       ve bu yüzden değiştirilmesi en zor tanımlayıcıdır. */
    $cak = tg_hakem_cakisma($y, $i, $hAd, $hOrcid, $hMail);
    if ($cak) cikti(['ok' => false, 'hata' => $cak['mesaj'], 'cakisma' => $cak['kod']], 409);
    if (!is_array($y[$i]['hakemler'] ?? null)) $y[$i]['hakemler'] = [];
    foreach ($y[$i]['hakemler'] as $h) {
        if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === tg_ad_anahtar($hAd)) {
            cikti(['ok' => false, 'hata' => 'Bu kişi zaten hakem olarak eklenmiş.'], 409);
        }
    }
    if (count($y[$i]['hakemler']) >= 12) cikti(['ok' => false, 'hata' => 'En fazla 12 hakem eklenebilir.'], 400);

    $hToken = substr(hash('sha256', $hAd . microtime() . random_int(0, PHP_INT_MAX)), 0, 32);
    $hSifre = uret_sifre();
    $davetT = substr(hash('sha256', 'davet' . $hToken . microtime() . random_int(0, PHP_INT_MAX)), 0, 32);
    $y[$i]['hakemler'][] = [
        'ad' => $hAd, 'token' => $hToken, 'davet_token' => $davetT,
        'eposta_hash' => $hMail !== '' ? hash('sha256', $hMail . '|hakem') : '',
        'eposta_acik' => $hMail, 'sifre' => $hSifre,
        'davet_durum' => 'bekliyor', 'davet_tarih' => date('c'),
        'karar' => '', 'rapor' => '', 'tarih' => '', 'dosya' => '',
        'profil' => [], 'raporlar' => [],
        'atayan' => ['tur' => $ed['tur'] === 'yonetim' ? 'yonetim' : $ed['tur'], 'ad' => $ed['ad'], 'tarih' => date('c')],
    ];
    yaz_json('yazilar.json', $y);

    /* ---- ATAMA DA DOKTORAYI DOĞRULAR ----
       KURUL BİLDİRİMİ — 19 Ağustos 2026: "baş editörler de hakem
       atayabilir editörler de; onların atadıkları hakemler otomatik
       asgari dr kabul edilir, bunun sorumluluğu atayan kişiye aittir."

       KURAL YENİ DEĞİL, UYGULANMASI EKSİKTİ. 14 Ağustos kararı zaten
       "editör ya da baş editör birini hakem olarak çağırdıysa doktoralı
       olduğunu bilerek çağırmıştır" diyordu ve tg_dogrulama_editor()
       üç yoldan birini 'atama' diye tanıyordu. Ama o yol HİÇBİR YERDEN
       çağrılmıyordu: hesap daveti doğruluyor, çalışmaya hakem ATAMA
       doğrulamıyordu. Yani sistem, kuralın yarısını uyguluyordu.

       SORUMLULUK ATAYANDA VE ADIYLA YAZILIR. Kayda kimin doğruladığı
       geçer; bu, sistemin başka her yerindeki kuralın aynısıdır —
       raporun arkasında hakem adıyla durur, kararın arkasında editör
       adıyla durur, doğrulamanın arkasında da atayan durur.

       ZATEN ONAYLI KAYIT EZİLMEZ: daha önce biri adıyla arkasında
       durduysa o ad kalır. Yeni bir atama, eski bir sorumluluk kaydının
       üstüne yazamaz.

       HESABI OLMAYAN KİŞİ İÇİN BİR ŞEY YAPILMAZ ve yapılamaz: doğrulama
       bir HESABIN alanıdır. O kişi hesabını açtığında atamayı yapan
       editörün adı çalışmanın sayfasındaki 'atayan' kaydında durmayı
       sürdürür; sorumluluk kaybolmaz, yalnız hesaba yazılacağı anı
       bekler. */
    $atamaDogrulandi = false;
    if ($hMail !== '' && function_exists('hs_bul')) {
        $hs = hs_bul($hMail);
        if (is_array($hs)) {
            $dd = tg_dogrulama_durum($hs['dogrulama'] ?? null);
            if (($dd['durum'] ?? '') !== 'onayli') {
                $hs['dogrulama'] = tg_dogrulama_editor($ed['ad'], 'atama');
                hs_kaydet($hs);
                $atamaDogrulandi = true;
            }
        }
    }

    $marka = (string)tg_ayar('marka', 'Kutadgu');
    $kokAdr = tg_kok();
    $davetUrl = $kokAdr . '/davet.php?d=' . $davetT;
    /* DÖNÜŞ DEĞERİ OKUNUR.
       ÖLÇÜLEN KUSUR — 14 Ağustos 2026. Bu uç eposta_gonder()'in
       sonucunu atıyordu ve panel her durumda "Davet gönderildi"
       yazıyordu. Aynı kusur parola yenilemede de vardı ve orada
       düzeltilmişti; burada kalmıştı.

       Bugün somut olarak yanlış: posta aktarıcısının hesap doğrulaması
       sürerken aktarıcı, takım üyesi olmayan HİÇBİR alıcıyı kabul
       etmiyor. Yani editör Mustafa hocayı hakem atadığında ekranda
       "gönderildi" yazacak, ileti ise hiç çıkmayacaktı.

       Bu, bu sistemde on bir kez yakalanan kusur sınıfının aynısıdır:
       sistem, uygulamadığı bir kuralı duyuruyor. Hakem kaydı yine
       kurulur — kayıt ile teslim ayrı şeylerdir ve bir teslim arızası
       yapılmış atamayı geri almaz; değişen yalnızca EKRANIN NE
       SÖYLEDİĞİdir. Gitmediyse davet bağlantısı zaten yanıtta
       dönüyor: editör onu elden iletebilir. */
    $postaGitti = null;                       /* null = adres yok, denenmedi */
    if ($hMail !== '') {
        $postaGitti = eposta_gonder($hMail, $marka . ' | Hakemlik daveti',
            "Sayın " . $hAd . ",\n\n" .
            "Bu ileti " . $marka . " adlı açık erişimli akademik yayın sisteminden gönderilmektedir (" . $kokAdr . ").\n\n" .
            $ed['ad'] . ", aşağıdaki çalışmayı değerlendirmeniz için sizi hakem olarak önerdi:\n\n" .
            "    \"" . (string)($y[$i]['baslik'] ?? '') . "\"\n\n" .
            "Daveti kabul edip etmeyeceğinizi şu sayfadan bildirebilirsiniz:\n" . $davetUrl . "\n\n" .
            "Bu sistemde kör hakemlik uygulanmaz: adınız, kararınız ve raporunuz çalışmayla birlikte " .
            "açıkça yayımlanır. Hakemlik tümüyle gönüllüdür; reddetmek en doğal hakkınızdır ve hiçbir " .
            "kaydı olumsuz etkilemez.\n\n" .
            $marka . "\n" . $kokAdr . "\n");
    }
    cikti(['ok' => true, 'davet_link' => $davetUrl, 'hakem_link' => $kokAdr . '/hakem.php?t=' . $hToken,
           'sifre' => $hSifre,
           /* Panel bunu yazar: atayan kişi ne üstlendiğini o anda
              görmeli, sonradan bir kayıttan öğrenmemeli. */
           'dogrulandi' => $atamaDogrulandi,
           'dogrulayan' => $atamaDogrulandi ? $ed['ad'] : '',
           /* 'posta' üç değerlidir ve üçü ayrı şeydir: true gitti,
              false denendi ve gitmedi, null adres yok (dizinden gizli
              adresle atama). Boole yapılsaydı "adres yok" ile
              "gönderilemedi" aynı ekrana düşerdi. */
           'posta' => $postaGitti]);
}

/* DAVET GERİ ÇEKME
   Süresi dolmuş ya da yanıtsız kalmış bir daveti editör kapatabilir.
   Rapor yazılmışsa kapatılamaz: yazılmış bir rapor sistemden çıkmaz.
   Kapatma kayda geçer; kimin, ne zaman kapattığı görünür. */
if ($yol === '/editor/davet-iptal' && $metod === 'POST') {
    $ed = ed_kimlik();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id  = (string)($g['id'] ?? '');
    $hAd = trim((string)($g['ad'] ?? ''));
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = null;
    foreach ($y as $ix => $e) { if ((string)($e['id'] ?? '') === $id) { $i = $ix; break; } }
    if ($i === null) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    $j = null;
    foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $jx => $h) {
        if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === tg_ad_anahtar($hAd)) { $j = $jx; break; }
    }
    if ($j === null) cikti(['ok' => false, 'hata' => 'Hakem bulunamadı.'], 404);
    $h = $y[$i]['hakemler'][$j];
    if (trim((string)($h['rapor'] ?? '')) !== '') {
        cikti(['ok' => false, 'hata' => 'Bu hakem raporunu yazmış; yazılmış bir rapor sistemden çıkarılamaz.'], 409);
    }
    if ((string)($h['davet_durum'] ?? '') === 'kabul' && !davet_suresi_doldu($h)) {
        cikti(['ok' => false, 'hata' => 'Bu hakem daveti kabul etmiş. Kabul edilmiş bir davet geri çekilemez.'], 409);
    }
    $y[$i]['hakemler'][$j]['davet_durum'] = 'geri_cekildi';
    $y[$i]['hakemler'][$j]['davet_kapatan'] = ['ad' => $ed['ad'], 'tarih' => date('c')];
    yaz_json('yazilar.json', $y);
    cikti(['ok' => true]);
}

/* EDİTÖRYAL NOT
   Not, çalışmanın sayfasında editörün adı ve saatiyle görünür. */
if ($yol === '/editor/not' && $metod === 'POST') {
    $ed = ed_kimlik();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id = (string)($g['id'] ?? '');
    $metin = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? ''))), 0, 3000);
    if (mb_strlen($metin, 'UTF-8') < 10) cikti(['ok' => false, 'hata' => 'Not çok kısa.'], 400);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    foreach ($y as $ix => $e) {
        if ((string)($e['id'] ?? '') !== $id) continue;
        if (!is_array($y[$ix]['editor_notlari'] ?? null)) $y[$ix]['editor_notlari'] = [];
        $y[$ix]['editor_notlari'][] = ['metin' => $metin, 'kim' => $ed['ad'], 'tur' => $ed['tur'], 'tarih' => date('c')];
        if (count($y[$ix]['editor_notlari']) > 40) array_shift($y[$ix]['editor_notlari']);
        yaz_json('yazilar.json', $y);
        cikti(['ok' => true, 'notlar' => $y[$ix]['editor_notlari']]);
    }
    cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
}

/* =====================================================================
   YAYIN SONRASI ŞERH
   Bir çalışma yayımlandıktan sonra tartışmanın bittiği yerde bilim de
   biter. Bu yüzden her çalışmanın altında kalıcı bir şerh alanı vardır.

   Kurallar:
     1. Şerh yazmak için doğrulanmış bir hesapla giriş yapmak gerekir.
        Anonim ya da takma adlı şerh kabul edilmez.
     2. Şerh silinmez. Ne yazan siler, ne çalışmanın yazarı, ne editör.
     3. Kişisel saldırı ya da hukuka aykırı içerik taşıyan bir şerh
        editör tarafından PERDELENİR: metin kapatılır, ama şerhin
        varlığı, perdeleyenin adı ve gerekçesi görünür kalır. Sansür de
        kayda geçer.
     4. Yazar her şerhe bir kez yanıt verebilir; yanıt da kalıcıdır.
   ===================================================================== */

/* Şerh yazacak kişinin kimliği ve bu çalışmayla ilişkisi */
function serh_kimlik(array $yazi): array {
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => false, 'hata' => 'Şerh yazmak için hesabınıza girmeniz gerekir.'], 401);
    $ad = hs_gorunen_ad($h);
    if (trim($ad) === '') cikti(['ok' => false, 'hata' => 'Şerh yazmadan önce panelinizden adınızı ve unvanınızı tamamlayın.'], 400);

    $dd = tg_dogrulama_durum($h['dogrulama'] ?? null);
    if (!$dd['yeterli'] && !hs_editor_mu($h)) {
        cikti(['ok' => false, 'hata' => 'Şerh yazmak için hesabınızın doğrulanmış olması gerekir. Panelinizden doktora belgenizi sunabilirsiniz.'], 403);
    }

    /* Bu çalışmayla ilişkisi okuyucuya gösterilir */
    $ilgi = 'okur';
    if (tg_yazar_mi($yazi, $h)) $ilgi = 'yazar';
    foreach (tg_dizi($yazi['hakemler'] ?? null) as $hk) {
        if (!is_array($hk)) continue;
        if (tg_ad_anahtar((string)($hk['ad'] ?? '')) === tg_ad_anahtar((string)($h['ad'] ?? ''))) { $ilgi = 'hakem'; break; }
    }
    if ($ilgi === 'okur' && hs_editor_mu($h)) $ilgi = 'editor';

    return [
        'hesap'  => $h,
        'ad'     => $ad,
        'eposta' => (string)($h['eposta'] ?? ''),
        'kurum'  => (string)($h['kurum'] ?? ''),
        'orcid'  => (string)($h['orcid'] ?? ''),
        'ilgi'   => $ilgi,
        'editor' => hs_editor_mu($h),
    ];
}

function serh_yazi_bul(array &$y, string $id): int {
    foreach ($y as $ix => $e) {
        if (!is_array($e)) continue;
        if ((string)($e['id'] ?? '') === $id && $id !== '') return $ix;
        if ((string)($e['bcid'] ?? '') === $id && $id !== '') return $ix;
        if ((string)($e['slug'] ?? '') === $id && $id !== '') return $ix;
    }
    cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
}

/* =====================================================================
   BEĞENİ / OKUMA LİSTESİ
   ---------------------------------------------------------------------
   Beğeni burada bir onay oyu değil, bir yer imidir: "bunu listeme
   alıyorum, başına bir şey gelirse haberim olsun". Bu yüzden hiçbir
   sıralamayı, onayı ya da hakemlik sayımını etkilemez; yalnızca
   okuyucunun kendi listesini ve bildirimini kurar.

   Kimin beğendiği açık edilmez. Sayfada yalnızca toplam görünür.
   Beğeni yalnızca hesabı olan kişiye açıktır; aksi hâlde sayı,
   sayfayı kaç kez yenilediğinizin ölçüsü olurdu.
   ===================================================================== */

/* Bir çalışmayı kaç hesap listesine almış? Hesap dosyasından sayılır;
   böylece çalışmadaki sayaç sürüklenirse kendi kendini düzeltir. */
function bg_say(string $id): int {
    if ($id === '') return 0;
    $n = 0;
    foreach (hs_oku() as $h) {
        if (!is_array($h)) continue;
        $b = $h['begeni'] ?? null;
        if (is_array($b) && array_key_exists($id, $b)) $n++;
    }
    return $n;
}

if ($yol === '/begeni' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => false, 'hata' => 'Bir çalışmayı listenize almak için hesabınıza girin.'], 401);

    $y  = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ix = serh_yazi_bul($y, (string)($g['id'] ?? ''));
    $id = (string)($y[$ix]['id'] ?? '');
    if ($id === '') cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);

    $mail = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
    $hs = hs_oku(); $bulundu = false; $begendi = false;
    foreach ($hs as $i => $hh) {
        if (!is_array($hh) || hs_eposta_anahtar((string)($hh['eposta'] ?? '')) !== $mail) continue;
        $bulundu = true;
        $liste = is_array($hh['begeni'] ?? null) ? $hh['begeni'] : [];
        /* İstek açıkça bir durum söylüyorsa ona uyulur; söylemiyorsa çevrilir.
           Böylece aynı düğmeye iki kez basmak sayıyı bozmaz. */
        $istek = array_key_exists('durum', $g) ? (bool)$g['durum'] : !array_key_exists($id, $liste);
        if ($istek) {
            $liste[$id] = ['t' => date('c'), 'ozet' => tg_yazi_ozet($y[$ix])];
        } else {
            unset($liste[$id]);
        }
        $hs[$i]['begeni'] = $liste;
        $begendi = $istek;
        break;
    }
    if (!$bulundu) cikti(['ok' => false, 'hata' => 'Hesap bulunamadı.'], 404);
    hs_yaz($hs);

    /* Sayaç çalışmada da tutulur: her okuyucu için bütün hesapları
       taramamak için. Değer, hesaplardan yeniden sayılarak yazılır. */
    $sayi = bg_say($id);
    if ((int)($y[$ix]['begeni'] ?? -1) !== $sayi) {
        $y[$ix]['begeni'] = $sayi;
        yaz_json('yazilar.json', $y);
    }

    cikti(['ok' => true, 'begendi' => $begendi, 'sayi' => $sayi,
           'mesaj' => $begendi
               ? 'Çalışma listenize eklendi. Hakem raporu, kurul kararı ya da şerh gibi bir değişiklik olduğunda panelinizde görürsünüz.'
               : 'Çalışma listenizden çıkarıldı.']);
}

/* LİSTEM: beğenilen çalışmalar ve son görüldüğünden bu yana değişenler */
if ($yol === '/begenilerim' && $metod === 'GET') {
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => false, 'hata' => 'Giriş gerekli.'], 401);
    $en = (isset($_GET['lang']) && $_GET['lang'] === 'en');

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $liste = tg_takip_listesi($h);
    $out = []; $yeni = 0;
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        $id = (string)($e['id'] ?? '');
        if ($id === '' || !array_key_exists($id, $liste)) continue;
        $kayit = is_array($liste[$id]) ? $liste[$id] : [];
        $fark = tg_ozet_fark($kayit['ozet'] ?? null, tg_yazi_ozet($e), $en);
        if ($fark) $yeni++;
        $onay = tg_onay_durumu($e);
        $out[] = [
            'id' => $id,
            'baslik' => (string)($e['baslik'] ?? ''),
            'yazar' => (string)($e['yazar'] ?? ''),
            'yol' => tg_yazi_yolu($e),
            'tur' => (string)($e['tur'] ?? ''),
            /* Listedeki etiket de aşamadan gelir; 'tur' yolu söyler, o
               yolun neresinde olunduğunu değil. */
            'asama_ad' => tg_asama_metni(tg_hakem_asamasi($e), $en, true),
            'tarih' => (string)($e['tarih'] ?? ''),
            'eklendi' => (string)($kayit['t'] ?? ''),
            'onayli' => $onay['onayli'],
            'degisiklik' => $fark,
        ];
    }
    usort($out, function ($a, $b) {
        $af = $a['degisiklik'] ? 1 : 0; $bf = $b['degisiklik'] ? 1 : 0;
        if ($af !== $bf) return $bf - $af;
        return strcmp((string)$b['eklendi'], (string)$a['eklendi']);
    });
    cikti(['ok' => true, 'liste' => $out, 'yeni' => $yeni]);
}

/* GÖRÜLDÜ: değişiklikleri okudum. Bir id verilirse yalnızca o, verilmezse hepsi. */
if ($yol === '/begeni-gorundu' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => false, 'hata' => 'Giriş gerekli.'], 401);
    $hedef = (string)($g['id'] ?? '');

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ozetler = [];
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        $id = (string)($e['id'] ?? ''); if ($id === '') continue;
        $ozetler[$id] = tg_yazi_ozet($e);
    }

    $mail = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
    $hs = hs_oku();
    foreach ($hs as $i => $hh) {
        if (!is_array($hh) || hs_eposta_anahtar((string)($hh['eposta'] ?? '')) !== $mail) continue;
        $liste = is_array($hh['begeni'] ?? null) ? $hh['begeni'] : [];
        foreach ($liste as $id => $kayit) {
            if ($hedef !== '' && $id !== $hedef) continue;
            if (!isset($ozetler[$id])) continue;
            if (!is_array($kayit)) $kayit = [];
            $kayit['ozet'] = $ozetler[$id];
            $liste[$id] = $kayit;
        }
        $hs[$i]['begeni'] = $liste;
        break;
    }
    hs_yaz($hs);
    cikti(['ok' => true]);
}

/* ŞERH EKLE */
if ($yol === '/serh-ekle' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    if (trim((string)($g['website'] ?? '')) !== '') cikti(['ok' => true, 'mesaj' => 'Alındı.']);

    $id = (string)($g['id'] ?? '');
    $metin = trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? '')));
    $metin = mb_substr($metin, 0, 6000, 'UTF-8');
    $asgari = (int)tg_ayar('serh_asgari_karakter', 120);
    if (mb_strlen($metin, 'UTF-8') < $asgari) {
        cikti(['ok' => false, 'hata' => 'Şerh en az ' . $asgari . ' karakter olmalıdır. Gerekçesini yazmadan bırakılan bir not tartışmayı ilerletmez.'], 400);
    }

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ix = serh_yazi_bul($y, $id);
    $kim = serh_kimlik($y[$ix]);

    if (!is_array($y[$ix]['serhler'] ?? null)) $y[$ix]['serhler'] = [];

    /* Aynı kişi aynı çalışmaya arka arkaya yığmasın */
    $bugun = date('Y-m-d'); $sayac = 0;
    foreach ($y[$ix]['serhler'] as $s) {
        if (!is_array($s)) continue;
        if (hs_eposta_anahtar((string)($s['eposta'] ?? '')) !== hs_eposta_anahtar($kim['eposta'])) continue;
        if (strpos((string)($s['tarih'] ?? ''), $bugun) === 0) $sayac++;
        if (trim((string)($s['metin'] ?? '')) === $metin) {
            cikti(['ok' => false, 'hata' => 'Aynı şerhi daha önce yazmışsınız.'], 409);
        }
    }
    if ($sayac >= 3) cikti(['ok' => false, 'hata' => 'Bir çalışmaya günde en çok üç şerh yazılabilir.'], 429);

    $kod = 'S' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $kayit = [
        'kod'    => $kod,
        'metin'  => $metin,
        'ad'     => $kim['ad'],
        'eposta' => $kim['eposta'],
        'kurum'  => $kim['kurum'],
        'orcid'  => $kim['orcid'],
        'ilgi'   => $kim['ilgi'],
        'tarih'  => date('c'),
    ];
    $y[$ix]['serhler'][] = $kayit;
    yaz_json('yazilar.json', $y);

    $ayarR = yonetim_ayar(); $tgR = (array)($ayarR['tg'] ?? []);
    if (!empty($tgR['token']) && !empty($tgR['chat'])) {
        tg_gonder((string)$tgR['token'], (string)$tgR['chat'],
            "\xF0\x9F\x96\x8B Yeni serh\n" . $kim['ad'] . "\n" . (string)($y[$ix]['baslik'] ?? '')
            . "\n\n" . mb_substr($metin, 0, 500, 'UTF-8'), 4);
    }

    cikti(['ok' => true, 'kod' => $kod, 'mesaj' => 'Şerhiniz kaydedildi. Kalıcıdır ve silinemez.']);
}

/* YAZARIN ŞERHE YANITI: her şerhe bir kez */
if ($yol === '/serh-yanit' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $id  = (string)($g['id'] ?? '');
    $kod = (string)($g['kod'] ?? '');
    $metin = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? ''))), 0, 6000, 'UTF-8');
    if (mb_strlen($metin, 'UTF-8') < 40) cikti(['ok' => false, 'hata' => 'Yanıt en az 40 karakter olmalıdır.'], 400);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ix = serh_yazi_bul($y, $id);
    $kim = serh_kimlik($y[$ix]);
    if ($kim['ilgi'] !== 'yazar' && !$kim['editor']) {
        cikti(['ok' => false, 'hata' => 'Bir şerhe yalnızca çalışmanın yazarı ya da bir editör yanıt verebilir.'], 403);
    }

    foreach (($y[$ix]['serhler'] ?? []) as $si => $s) {
        if (!is_array($s) || (string)($s['kod'] ?? '') !== $kod) continue;
        if (is_array($s['yanit'] ?? null) && trim((string)($s['yanit']['metin'] ?? '')) !== '') {
            cikti(['ok' => false, 'hata' => 'Bu şerhe zaten bir yanıt verilmiş. Yanıtlar da kalıcıdır ve değiştirilemez.'], 409);
        }
        $y[$ix]['serhler'][$si]['yanit'] = [
            'metin' => $metin, 'ad' => $kim['ad'],
            'ilgi'  => $kim['ilgi'], 'tarih' => date('c'),
        ];
        yaz_json('yazilar.json', $y);
        cikti(['ok' => true, 'mesaj' => 'Yanıtınız kaydedildi.']);
    }
    cikti(['ok' => false, 'hata' => 'Şerh bulunamadı.'], 404);
}

/* ŞERHİ PERDELE: silmek değil, kapatmak. Gerekçe herkese görünür. */
if ($yol === '/serh-perde' && $metod === 'POST') {
    $ed = ed_kimlik();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id  = (string)($g['id'] ?? '');
    $kod = (string)($g['kod'] ?? '');
    $neden = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['neden'] ?? ''))), 0, 500, 'UTF-8');
    if (mb_strlen($neden, 'UTF-8') < 15) {
        cikti(['ok' => false, 'hata' => 'Perdeleme gerekçesi yazılmadan bir şerh kapatılamaz. Gerekçe okuyucuya gösterilir.'], 400);
    }
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ix = serh_yazi_bul($y, $id);
    foreach (($y[$ix]['serhler'] ?? []) as $si => $s) {
        if (!is_array($s) || (string)($s['kod'] ?? '') !== $kod) continue;
        $y[$ix]['serhler'][$si]['perde'] = [
            'neden' => $neden, 'kim' => $ed['ad'], 'tur' => $ed['tur'], 'tarih' => date('c'),
        ];
        yaz_json('yazilar.json', $y);
        cikti(['ok' => true, 'mesaj' => 'Şerh perdelendi. Metni kapatıldı, varlığı ve gerekçesi görünür kalıyor.']);
    }
    cikti(['ok' => false, 'hata' => 'Şerh bulunamadı.'], 404);
}

/* =====================================================================
   OYLAMA VE İTİRAZ DÜZENİ
   Yazar ile hakemin anlaşamadığı yerde karar tek kişiye bırakılmaz.
   Üç bağımsız kişi oy verir; oy verenler birbirinin oyunu göremez.
   Üçüncü oy düştüğü anda oylama kapanır ve her şey açılır: kimin nasıl
   oy verdiği, gerekçesiyle birlikte kalıcı olarak yayımlanır.

   Gizlilik karar anına aittir, karardan sonrasına değil.
   ===================================================================== */

/* Bu kişinin hakemlik yetkisi bir kurul kararıyla askıya alınmış mı?
   E-posta ya da ad üzerinden bakılır; hesabı olmayan biri için false. */
function hakemlik_askida(string $eposta, string $ad = ''): bool {
    $mail = hs_eposta_anahtar($eposta);
    $adk  = tg_ad_anahtar($ad);
    foreach (hs_oku() as $h) {
        if (!is_array($h) || empty($h['hakemlik_askida'])) continue;
        $he = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
        if ($mail !== '' && $he !== '' && $he === $mail) return true;
        if ($adk !== '' && tg_ad_anahtar((string)($h['ad'] ?? '')) === $adk) return true;
    }
    return false;
}

/* Kurul işlemleri için kimlik.
   $sikiDogrulama true ise geçiş dönemi kolaylığı uygulanmaz.

   Ayrım bilinçlidir:
     - Kendi çalışmasına gelen bir rapora itiraz etmek, kişinin kendi
       hakkını kullanmasıdır; normal eşik yeter.
     - Bir başkasının raporunu şikâyet etmek ve oy vermek, başkasının
       emeğini geçersiz kılabilecek işlemlerdir; bunlar için belgenin
       gerçekten doğrulanmış olması aranır. Böylece üç sahte hesabın
       bir araya gelip bir raporu düşürmesi engellenir. */
function oy_kimlik(bool $sikiDogrulama = true): array {
    $h = hs_oturum();
    if ($h === null) cikti(['ok' => false, 'hata' => 'Bu işlem için hesabınıza girmeniz gerekir.'], 401);
    $ad = hs_gorunen_ad($h);
    if (trim($ad) === '') cikti(['ok' => false, 'hata' => 'Önce panelinizden adınızı ve unvanınızı tamamlayın.'], 400);

    $dd = tg_dogrulama_durum($h['dogrulama'] ?? null);
    $editor = hs_editor_mu($h);
    if ($sikiDogrulama) {
        if ($dd['durum'] !== 'onayli' && !$editor) {
            cikti(['ok' => false, 'hata' => 'Bu işlem için doktora belgenizin doğrulanmış olması gerekir. Başkasının raporunu geçersiz kılabilecek bir işlem olduğu için burada geçiş dönemi kolaylığı uygulanmaz. Belgenizi panelinizden sunabilirsiniz.'], 403);
        }
    } elseif (!$dd['yeterli'] && !$editor) {
        cikti(['ok' => false, 'hata' => 'Bu işlem için hesabınızın doğrulanmış olması gerekir. Panelinizden doktora belgenizi sunabilirsiniz.'], 403);
    }
    if (!empty($h['hakemlik_askida'])) {
        cikti(['ok' => false, 'hata' => 'Hakemlik yetkiniz bir kurul oylamasıyla askıya alınmıştır.'], 403);
    }
    return ['hesap' => $h, 'ad' => $ad, 'eposta' => (string)($h['eposta'] ?? ''),
            'kurum' => (string)($h['kurum'] ?? ''), 'editor' => $editor];
}

/* Oylama kapandıysa sonucunu uygula: rapor geçersiz sayma, askıya alma */
function oy_sonucu_isle(array &$yazi, array $ov): void {
    $s = tg_oylama_sonuc($ov);
    if (!$s['kapali'] || $s['karar'] === '') return;

    /* Hakemlik yetkisinin askıya alınması hesapta işaretlenir */
    if ($s['karar'] === 'askiya_al') {
        $hedefMail = mb_strtolower(trim((string)($ov['hedef_eposta'] ?? '')), 'UTF-8');
        $hedefAd   = tg_ad_anahtar((string)($ov['hedef_ad'] ?? ''));
        $hs = hs_oku(); $degisti = false;
        foreach ($hs as $i => $hh) {
            if (!is_array($hh)) continue;
            $eslesti = ($hedefMail !== '' && hs_eposta_anahtar((string)($hh['eposta'] ?? '')) === $hedefMail)
                    || ($hedefMail === '' && $hedefAd !== '' && tg_ad_anahtar((string)($hh['ad'] ?? '')) === $hedefAd);
            if (!$eslesti) continue;
            $hs[$i]['hakemlik_askida'] = [
                'tarih' => date('c'),
                'oylama' => (string)($ov['kod'] ?? ''),
                'calisma' => (string)($yazi['baslik'] ?? ''),
            ];
            $degisti = true;
        }
        if ($degisti) hs_yaz($hs);
    }
}

/* Dört geçerli oy kullanan kişi yazarlık hakkını kazanır */
function oy_yazarlik_kontrol(string $eposta): bool {
    $esik = (int)tg_ayar('oy_yazarlik_esigi', 4);
    $mail = hs_eposta_anahtar($eposta);
    if ($mail === '') return false;
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $say = 0;
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        foreach (tg_oylamalar($e) as $ov) {
            foreach (tg_dizi($ov['oylar'] ?? null) as $o) {
                if (is_array($o) && hs_eposta_anahtar((string)($o['eposta'] ?? '')) === $mail) $say++;
            }
        }
    }
    if ($say < $esik) return false;
    $h = hs_bul($eposta);
    if ($h === null) return false;
    $r = is_array($h['roller'] ?? null) ? $h['roller'] : [];
    if (in_array('yazar', $r, true)) return false;
    $r[] = 'yazar';
    $h['roller'] = array_values(array_unique($r));
    $h['yazarlik_kaynak'] = ['tur' => 'oylama', 'sayi' => $say, 'tarih' => date('c')];
    hs_kaydet($h);
    return true;
}

/* İTİRAZ YA DA ŞİKÂYET AÇ */
if ($yol === '/oylama-ac' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    if (trim((string)($g['website'] ?? '')) !== '') cikti(['ok' => true, 'mesaj' => 'Alındı.']);

    $id  = (string)($g['id'] ?? '');
    $tur = (string)($g['tur'] ?? 'itiraz');
    if (!in_array($tur, ['itiraz', 'sikayet'], true)) $tur = 'itiraz';
    $hedefAd = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['hedef'] ?? ''))), 0, 120, 'UTF-8');
    $gerekce = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['gerekce'] ?? ''))), 0, 6000, 'UTF-8');

    $asgari = (int)tg_ayar('itiraz_asgari_karakter', 200);
    if (mb_strlen($gerekce, 'UTF-8') < $asgari) {
        cikti(['ok' => false, 'hata' => 'Gerekçe en az ' . $asgari . ' karakter olmalıdır. Bir raporu kurul önüne getirmek ciddi bir adımdır; neye itiraz ettiğinizi açıkça yazın.'], 400);
    }

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ix = serh_yazi_bul($y, $id);
    /* İtiraz kendi hakkını kullanmaktır: normal eşik yeter.
       Şikâyet başkasının raporunu hedef alır: belge doğrulanmış olmalı. */
    $kim = oy_kimlik($tur === 'sikayet');

    /* İtirazı yalnızca yazar açabilir; şikâyeti doğrulanmış herkes. */
    $yazarMi = tg_yazar_mi($y[$ix], $kim['hesap']);
    if ($tur === 'itiraz' && !$yazarMi) {
        cikti(['ok' => false, 'hata' => 'İtirazı yalnızca çalışmanın yazarı açabilir. Rapordan siz de rahatsızsanız şikâyet yolunu kullanabilirsiniz.'], 403);
    }
    if ($tur === 'sikayet' && $yazarMi) {
        cikti(['ok' => false, 'hata' => 'Kendi çalışmanız için şikâyet değil itiraz yolunu kullanın.'], 400);
    }

    /* Hedef hakem çalışmada gerçekten var mı ve raporunu yazmış mı? */
    $hedefKey = tg_ad_anahtar($hedefAd);
    $hedefEposta = ''; $bulundu = false;
    foreach (tg_dizi($y[$ix]['hakemler'] ?? null) as $hk) {
        if (!is_array($hk)) continue;
        if (tg_ad_anahtar((string)($hk['ad'] ?? '')) !== $hedefKey) continue;
        if (trim((string)($hk['rapor'] ?? '')) === '') {
            cikti(['ok' => false, 'hata' => 'Bu hakem henüz rapor yazmamış; olmayan bir rapora itiraz edilemez.'], 400);
        }
        $hedefAd = (string)($hk['ad'] ?? $hedefAd);
        $hedefEposta = (string)(($hk['profil']['eposta'] ?? '') ?: ($hk['eposta'] ?? ''));
        $bulundu = true; break;
    }
    if (!$bulundu) cikti(['ok' => false, 'hata' => 'Bu çalışmada böyle bir hakem yok.'], 404);

    /* Kendi raporu hakkında oylama açılamaz */
    if (tg_ad_anahtar($kim['ad']) === $hedefKey) {
        cikti(['ok' => false, 'hata' => 'Kendi raporunuz hakkında oylama açamazsınız.'], 400);
    }

    /* Aynı rapor için açık bir oylama varsa ikincisi açılmaz */
    foreach (tg_oylamalar($y[$ix]) as $ov) {
        if (tg_ad_anahtar((string)($ov['hedef_ad'] ?? '')) !== $hedefKey) continue;
        if (!tg_oylama_sonuc($ov)['kapali']) {
            cikti(['ok' => false, 'hata' => 'Bu rapor hakkında açık bir oylama zaten var. Sonucunu bekleyin.'], 409);
        }
        if ((string)($ov['tur'] ?? '') === $tur) {
            cikti(['ok' => false, 'hata' => 'Bu rapor hakkında daha önce bir kurul oylaması yapılmış ve sonuçlanmış. Aynı gerekçeyle ikinci kez açılamaz.'], 409);
        }
    }

    if (!is_array($y[$ix]['oylamalar'] ?? null)) $y[$ix]['oylamalar'] = [];
    $kod = 'O' . strtoupper(substr(bin2hex(random_bytes(6)), 0, 10));
    $y[$ix]['oylamalar'][] = [
        'kod' => $kod, 'tur' => $tur,
        'hedef_ad' => $hedefAd, 'hedef_eposta' => $hedefEposta,
        'acan_ad' => $kim['ad'], 'acan_eposta' => $kim['eposta'],
        'acan_ilgi' => $yazarMi ? 'yazar' : ($kim['editor'] ? 'editor' : 'okur'),
        'gerekce' => $gerekce, 'tarih' => date('c'),
        'oylar' => [],
    ];
    yaz_json('yazilar.json', $y);

    $ayarR = yonetim_ayar(); $tgR = (array)($ayarR['tg'] ?? []);
    if (!empty($tgR['token']) && !empty($tgR['chat'])) {
        tg_gonder((string)$tgR['token'], (string)$tgR['chat'],
            "\xE2\x9A\x96 Kurul oylamasi acildi (" . $tur . ")\n" . $kim['ad'] . " -> " . $hedefAd
            . "\n" . (string)($y[$ix]['baslik'] ?? '') . "\n\n" . mb_substr($gerekce, 0, 400, 'UTF-8'), 4);
    }

    cikti(['ok' => true, 'kod' => $kod,
           'mesaj' => 'Oylama açıldı. Üç bağımsız oy toplandığında sonuç ve bütün oylar gerekçeleriyle birlikte yayımlanacak.']);
}

/* OY VER */
if ($yol === '/oy-ver' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $id    = (string)($g['id'] ?? '');
    $kod   = (string)($g['kod'] ?? '');
    $karar = (string)($g['karar'] ?? '');
    $gerekce = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['gerekce'] ?? ''))), 0, 4000, 'UTF-8');

    $asgari = (int)tg_ayar('oy_asgari_karakter', 80);
    if (mb_strlen($gerekce, 'UTF-8') < $asgari) {
        cikti(['ok' => false, 'hata' => 'Oy gerekçesi en az ' . $asgari . ' karakter olmalıdır. Gerekçesiz oy, kurulu bir sayaç hâline getirir.'], 400);
    }

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $ix = serh_yazi_bul($y, $id);
    $kim = oy_kimlik();

    foreach (($y[$ix]['oylamalar'] ?? []) as $oi => $ov) {
        if (!is_array($ov) || (string)($ov['kod'] ?? '') !== $kod) continue;

        $tur = (string)($ov['tur'] ?? 'itiraz');
        if (!array_key_exists($karar, tg_oy_secenekleri($tur))) {
            cikti(['ok' => false, 'hata' => 'Geçersiz oy.'], 400);
        }
        $izin = tg_oy_verebilir($y[$ix], $ov, $kim['hesap']);
        if (!$izin['olur']) {
            cikti(['ok' => false, 'hata' => tg_oy_engel_metin($izin['neden'], false) . '.'], 403);
        }

        $y[$ix]['oylamalar'][$oi]['oylar'][] = [
            'ad' => $kim['ad'], 'eposta' => $kim['eposta'], 'kurum' => $kim['kurum'],
            'karar' => $karar, 'gerekce' => $gerekce, 'tarih' => date('c'),
        ];
        $son = tg_oylama_sonuc($y[$ix]['oylamalar'][$oi]);
        if ($son['kapali']) {
            $y[$ix]['oylamalar'][$oi]['sonuc'] = [
                'karar' => $son['karar'], 'sayim' => $son['sayim'], 'tarih' => date('c'),
            ];
        }
        yaz_json('yazilar.json', $y);

        if ($son['kapali']) {
            oy_sonucu_isle($y[$ix], $y[$ix]['oylamalar'][$oi]);
        }
        $yeniYazar = oy_yazarlik_kontrol($kim['eposta']);

        cikti([
            'ok' => true,
            'kapandi' => $son['kapali'],
            'sonuc' => $son['kapali'] ? tg_oy_metin($tur, $son['karar'], false) : '',
            'yazarlik' => $yeniYazar,
            'mesaj' => $son['kapali']
                ? 'Oyunuz alındı ve oylama sonuçlandı. Bütün oylar gerekçeleriyle birlikte açıldı.'
                : ('Oyunuz alındı. Diğer oyları, oylama sonuçlanana kadar ne siz ne başkası görebilir. '
                   . 'Toplanan oy: ' . $son['oy_sayisi'] . '/' . $son['gerek'] . '.'),
        ]);
    }
    cikti(['ok' => false, 'hata' => 'Oylama bulunamadı.'], 404);
}

/* AÇIK OYLAMALARIN LİSTESİ (oylama.php sayfası için) */
if ($yol === '/oylamalar' && $metod === 'GET') {
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $h = hs_oturum();
    $out = [];
    foreach ($y as $e) {
        if (!is_array($e)) continue;
        foreach (tg_oylamalar($e) as $ov) {
            $s = tg_oylama_sonuc($ov);
            $kayit = [
                'kod' => (string)($ov['kod'] ?? ''), 'tur' => (string)($ov['tur'] ?? ''),
                'calisma_id' => (string)($e['id'] ?? ''), 'calisma' => (string)($e['baslik'] ?? ''),
                'yol' => tg_yazi_yolu($e),
                'hedef' => (string)($ov['hedef_ad'] ?? ''), 'acan' => (string)($ov['acan_ad'] ?? ''),
                'gerekce' => (string)($ov['gerekce'] ?? ''), 'tarih' => (string)($ov['tarih'] ?? ''),
                'kapali' => $s['kapali'], 'oy_sayisi' => $s['oy_sayisi'], 'gerek' => $s['gerek'],
                'karar' => $s['kapali'] ? $s['karar'] : '',
            ];
            /* Oy verebilir mi bilgisi yalnızca giriş yapmış kişiye */
            if ($h !== null) {
                $iz = tg_oy_verebilir($e, $ov, $h);
                $kayit['verebilir'] = $iz['olur'];
                $kayit['engel'] = $iz['neden'];
            }
            $out[] = $kayit;
        }
    }
    usort($out, function ($a, $b) {
        if ($a['kapali'] !== $b['kapali']) return $a['kapali'] ? 1 : -1;
        return strcmp((string)$b['tarih'], (string)$a['tarih']);
    });
    cikti(['ok' => true, 'oylamalar' => $out, 'girisli' => $h !== null]);
}

/* =====================================================================
   İLETİŞİM VE DESTEK
   Sisteme katkı sunmak ya da bir şey iletmek isteyen kişiler buradan
   yazar. İleti, sistemin genel bildirim botundan AYRI bir Telegram
   botuna düşer; kurucu oradan yanıtladığında yanıt, yazan kişinin
   kendi konuşma sayfasında görünür.

   GÜVENLİK NOTU (bilerek burada yazılıdır):
     1. Telegram'ın webhook'u herkese açık bir adrestir. Bu yüzden her
        istek, Telegram'a kurulum sırasında bildirilen gizli anahtarla
        (X-Telegram-Bot-Api-Secret-Token) doğrulanır. Anahtarı bilmeyen
        bir istek hiçbir şey yapamaz.
     2. Gelen iletinin sohbet kimliği, yapılandırmada yazılı kimlikle
        karşılaştırılır. Başka bir sohbetten gelen ileti yok sayılır.
     3. Yanıt metni HTML olarak değil düz metin olarak saklanır ve
        sayfada kaçırılarak basılır; betik çalıştırılamaz.
     4. Konuşma sayfası tahmin edilemez bir anahtarla açılır ve arama
        motorlarına kapalıdır; konuşmalar herkese açık bir sayfada
        listelenmez.
     5. Form bir e-posta aktarıcısına dönüşmesin diye: bal kabı alanı,
        adres başına sınır ve ileti uzunluğu sınırı vardır.
   ===================================================================== */

function ilt_ayar(): array {
    $a = yonetim_ayar();
    $i = is_array($a['iletisim'] ?? null) ? $a['iletisim'] : [];
    return [
        'token'  => (string)($i['token'] ?? ''),   /* AYRI bot */
        'chat'   => (string)($i['chat'] ?? ''),
        'gizli'  => (string)($i['gizli'] ?? ''),   /* webhook gizli anahtarı */
    ];
}

/* =====================================================================
   İLETİYİ KİM ALIR — DEVREDİLEBİLİR OLMASININ ŞARTI
   ---------------------------------------------------------------------
   Bugüne kadar bir iletinin varacağı tek yer kurucunun telefonundaki
   Telegram botuydu. Bu, çalıştığı sürece bile YANLIŞTI: sistem bir
   kuruma devredildiğinde iletiler devredenin telefonuna düşmeyi
   sürdürürdü ve devralan kurum, kendisine yazılanları hiç görmezdi.
   Devredilebilir bir sistemde "kime ulaşılır" sorusunun cevabı bir
   kişide değil, AYARDA durmalıdır.

   Alıcılar veri dizinindeki yonetim-ayar.json'a yazılır:

     "iletisim": {
       "alicilar": [
         {"ad":"...", "eposta":"...", "turler":["kurum","destek"]},
         {"ad":"...", "eposta":"...", "turler":["*"]}
       ]
     }

   'turler' hangi türdeki iletilerin o kişiye gideceğini söyler; "*"
   hepsi demektir. Böylece kurumsal destek başvurusu doğrudan onunla
   ilgilenen kişiye gider, genel sorular başkasına; bir başvuru,
   ötekilerin arasında beklemez.

   LİSTE BOŞSA SİSTEM SESSİZ KALMAZ: ayardaki yönetim adresine düşer
   (ayar.php, iletisim_eposta). O da boşsa hiçbir yere gitmez ve BU DA
   KÜTÜĞE YAZILIR; "kimse yazmadı" ile "yazıldı ama kimseye gitmedi"
   ayrı şeylerdir. */
/* =====================================================================
   BİR İLETİYİ KİM ÜSTLENDİ
   ---------------------------------------------------------------------
   BİLDİRİLEN SORUN (14 Ağustos 2026, kurul): "mesaj geldiğinde tüm baş
   editörler görüyor; hepsi birden yanıtlamaya kalkarsa ne olacak?"

   Gerçek bir sorundur ve bu sistemde sık görülen bir sorunun bir
   örneğidir: bir işi HERKES görüyorsa, o işi KİMSE üstlenmemiş olur.
   İkisi de olabilir — iki editör aynı kişiye iki ayrı yanıt yazar
   (yazan kişi kime inanacağını bilemez), ya da her ikisi de ötekinin
   yazdığını sanıp kimse yazmaz.

   ÜÇ ÇÖZÜM DÜŞÜNÜLDÜ, İKİSİ ELENDİ:

     1. Kilit: ileti bir editöre kilitlenir, öteki yanıtlayamaz.
        ELENDİ. Kilit tutan kişi hastalanır, unutur ya da tatile
        çıkar; ileti kimsenin açamadığı bir kutuda kalır. Bir yayın
        sisteminde okuru bekleten şey, onu koruyan şeyden kötüdür.
     2. Sıra/atama: ileti bir editöre atanır ve yalnız o sorumludur.
        ELENDİ. Kim atayacak? Atayan bir kişi ya da bir kural gerekir;
        ikisi de yeni bir yönetim katmanıdır ve bu sistem katman
        eklemeye değil azaltmaya çalışıyor.
     3. ÜSTLENME (seçildi). Bir iletiyi AÇAN kişi onu üstlenmiş sayılır
        ve bu görünür olur. Kimse kimseyi engellemez; herkes ötekinin
        orada olduğunu bilir.

   ÜSTLENME NEDEN ENGELLEMEZ. Engelleyen bir düzen, sistemin başka
   hiçbir yerinde yok: hakem ataması engellemez, oylama engellemez,
   şerh engellemez. Hepsi aynı ilkeyi kullanır — yapılan iş görünür
   olur ve adıyla durur. Burada da öyle: ikinci editör yine
   yanıtlayabilir, ama yazmadan önce birincinin orada olduğunu görür.
   Görmek, engellemekten daha iyidir; çünkü engelleme arızalanır,
   görmek arızalanmaz.

   ÜSTLENME SÜRELİDİR. Süresiz olsaydı, bir kez açıp kapatan kişi o
   iletiyi sonsuza dek "üstlenmiş" görünürdü ve bir süre sonra hiç
   kimse o etikete bakmazdı. Süre dolduğunda üstlenme düşer, ileti
   yine herkesin olur. Varsayılan 45 dakikadır: bir yanıt yazmaya
   yeter, bir günü tutmaya yetmez.

   KİMLER BAKTI AYRICA TUTULUR ve üstlenmeden ayrı bir şeydir:
   üstlenme "şu an kim ilgileniyor", bakanlar "bu iletiyi kimler
   gördü". İkincisi düşmez, çünkü görmüş olmak geri alınmaz.
   ===================================================================== */
function ilt_ustlenme_dk(): int {
    $d = (int)tg_ayar('ileti_ustlenme_dk', 45);
    return ($d >= 5 && $d <= 1440) ? $d : 45;
}

/* Kaydın üstlenme durumu. 'canli' false ise süre dolmuştur ve ad
   yalnızca geçmiş bilgisidir; ekranda uyarı olarak gösterilmez. */
function ilt_ustlenme(array $kk): array {
    $u = is_array($kk['ustlenen'] ?? null) ? $kk['ustlenen'] : [];
    $ad = trim((string)($u['ad'] ?? ''));
    $tr = (string)($u['tarih'] ?? '');
    if ($ad === '' || $tr === '') return ['ad' => '', 'tarih' => '', 'canli' => false];
    $t = strtotime($tr);
    $canli = $t !== false && (time() - $t) < (ilt_ustlenme_dk() * 60);
    return ['ad' => $ad, 'tarih' => $tr, 'canli' => $canli];
}

function ilt_alicilar(string $tur = ''): array {
    $a = yonetim_ayar();
    $i = is_array($a['iletisim'] ?? null) ? $a['iletisim'] : [];
    $ham = is_array($i['alicilar'] ?? null) ? $i['alicilar'] : [];
    $out = [];
    foreach ($ham as $x) {
        if (!is_array($x)) continue;
        $mail = mb_strtolower(trim((string)($x['eposta'] ?? '')), 'UTF-8');
        if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) continue;
        $turler = is_array($x['turler'] ?? null) ? array_map('strval', $x['turler']) : ['*'];
        if ($tur !== '' && !in_array('*', $turler, true) && !in_array($tur, $turler, true)) continue;
        $out[$mail] = ['ad' => trim((string)($x['ad'] ?? '')), 'eposta' => $mail];
    }
    if (!$out) {
        $yedek = mb_strtolower(trim((string)tg_ayar('iletisim_eposta', '')), 'UTF-8');
        if (filter_var($yedek, FILTER_VALIDATE_EMAIL)) {
            $out[$yedek] = ['ad' => (string)tg_ayar('yonetici_ad', ''), 'eposta' => $yedek];
        }
    }
    return array_values($out);
}

/* =====================================================================
   SAKLAMA SÜRESİ — YAZIŞMA SÜRESİZ TUTULMAZ
   ---------------------------------------------------------------------
   Gerekçesi ayar.php'de yazılıdır: yayımlanmış kayıt hiç silinmez,
   yazışma ise bir insanın adı ve adresidir ve süresiz saklanması bir
   erdem değil bir yüktür.

   SİLME GERÇEK SİLMEDİR. Kaydın yerine bir "silindi" taşı konmaz:
   taşın kendisi de kişiyle ilgili bir veridir ("bu kişi şu tarihte
   yazmıştı") ve saklama süresinin amacını boşa çıkarır. Silinenin
   yalnızca SAYISI kütüğe düşer; sayıda kişisel bir şey yoktur.

   YANITLANMAMIŞ İLETİ SİLİNMEZ. Süreyle silinseydi, biriken iş
   kendiliğinden süpürülmüş olurdu.

   Süpürme her yeni iletide bir kez çalışır: ayrı bir zamanlayıcıya
   bağlanmadı, çünkü çalışmayan bir zamanlayıcı, olmayan bir kuraldan
   daha kötüdür (çalıştığı sanılır). */
function ilt_saklama_uygula(array $k): array {
    $a = (array)tg_ayar('iletisim_saklama', []);
    $gun = function (string $d) use ($a): int { return max(0, (int)($a[$d] ?? 0)); };
    $simdi = time();
    $kalan = []; $silinen = 0;
    foreach ($k as $kk) {
        if (!is_array($kk)) continue;
        $durum = !empty($kk['istenmeyen']) ? 'istenmeyen' : (!empty($kk['kapali']) ? 'kapali' : 'acik');
        $sinir = $gun($durum);
        if ($sinir === 0) { $kalan[] = $kk; continue; }
        /* Ölçüt SON HAREKET tarihidir, ilk yazılma tarihi değil: altı ay
           önce açılıp dün yazılan bir konuşmayı silmek, konuşmanın
           ortasında kapıyı kapatmaktır. */
        $son = strtotime((string)($kk['tarih'] ?? '')) ?: $simdi;
        foreach ((array)($kk['mesajlar'] ?? []) as $m) {
            if (!is_array($m)) continue;
            $t = strtotime((string)($m['tarih'] ?? ''));
            if ($t && $t > $son) $son = $t;
        }
        if (($simdi - $son) > $sinir * 86400) { $silinen++; continue; }
        $kalan[] = $kk;
    }
    if ($silinen > 0) tg_kutuk_ileti('saklama suresi doldu, silinen: ' . $silinen);
    return array_values($kalan);
}

/* Yazışmaya ilişkin işlemlerin kütüğü. KİŞİSEL VERİ YAZILMAZ: ne ad ne
   adres ne metin; yalnız ne olduğu ve kaç tane olduğu. Bir silme
   kaydının kendisi kişiyi ele veriyorsa, silme silme değildir. */
function tg_kutuk_ileti(string $not): void {
    $satir = date('c') . "\t" . $not . "\n";
    $yol = veri_yolu('iletisim.log');
    if (@filesize($yol) > 1024 * 1024) @unlink($yol);
    @file_put_contents($yol, $satir, FILE_APPEND | LOCK_EX);
}

/* İletiyi alıcılara e-posta ile de duyurur.
   TELEGRAM TEK BAŞINA YETMEZ: tek bir kişinin telefonuna bağlıdır,
   sessizce başarısız olabilir ve devirde taşınmaz. E-posta ikisini de
   çözer; ikisi birlikte çalışır, biri yoksa öteki taşır. */
function ilt_alicilara_bildir(string $tur, string $konu, string $govde): int {
    $n = 0;
    foreach (ilt_alicilar($tur) as $al) {
        if (eposta_gonder($al['eposta'], $konu, $govde)) $n++;
    }
    if (!$n) tg_kutuk('iletisim-eposta', '', '', false, 'alici yok ya da posta gonderilemedi, tur=' . $tur);
    return $n;
}

/* İLETİ GÖNDER */
if ($yol === '/iletisim' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    /* Bal kabı: insanlar bu alanı görmez, dolduran robottur */
    if (trim((string)($g['website'] ?? '')) !== '') cikti(['ok' => true, 'mesaj' => 'Alındı.']);

    $ipk = 'iletisim:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 6) cikti(['ok' => false, 'hata' => 'Kısa sürede çok fazla ileti gönderildi. Lütfen sonra tekrar deneyin.'], 429);

    $t = fn($k, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g[$k] ?? ''))), 0, $n);
    $ad     = $t('ad', 120);
    $eposta = mb_strtolower(trim((string)($g['eposta'] ?? '')), 'UTF-8');
    $konu   = $t('konu', 160);
    $metin  = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? ''))), 0, 4000);
    $tur    = preg_replace('/[^a-z]/', '', strtolower((string)($g['tur'] ?? 'genel')));
    /* 'kurum': bir üniversite, vakıf ya da kuruluşun destek ya da
       himaye başvurusu. Ayrı bir tür olması, bu iletilerin ötekilerin
       arasında kaybolmamasını sağlar. */
    if (!in_array($tur, ['genel', 'destek', 'hata', 'oneri', 'isbirligi', 'kurum'], true)) $tur = 'genel';

    if ($ad === '') cikti(['ok' => false, 'hata' => 'Adınızı yazın.'], 400);
    if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'Geçerli bir e-posta adresi girin.'], 400);
    if (mb_strlen($metin, 'UTF-8') < 20) cikti(['ok' => false, 'hata' => 'İletinizi biraz daha açık yazar mısınız?'], 400);

    $k = oku_json('iletiler.json', []); if (!is_array($k)) $k = [];
    $anahtar = bin2hex(random_bytes(16));
    $kayit = [
        'kod'    => substr(hash('sha256', $anahtar), 0, 12),
        'anahtar'=> $anahtar,
        'ad'     => $ad, 'eposta' => $eposta, 'konu' => $konu, 'tur' => $tur,
        'tarih'  => date('c'),
        'ip'     => substr(hash('sha256', ip_al()), 0, 12),
        'mesajlar' => [['kim' => 'ziyaretci', 'metin' => $metin, 'tarih' => date('c')]],
        'okundu' => false,
    ];
    $k[] = $kayit;
    /* SESSİZ KIRPMA KALDIRILDI. Burada "4000'i geçerse en eski 1000'i
       at" yazıyordu: yazılı olmayan, haber verilmeyen ve ne zaman
       çalıştığı bilinmeyen bir silme. Bir sistemin veriyi ne kadar
       sakladığı bir ayrıntı değil, kişiye verilen bir sözdür ve
       yazılmalıdır. Silme artık kurala bağlı (ayar.php,
       iletisim_saklama) ve kütüğe düşer. */
    $k = ilt_saklama_uygula($k);
    yaz_json('iletiler.json', $k);

    $marka = (string)tg_ayar('marka', 'Kutadgu');
    $kokAdr = tg_kok();
    $baglanti = $kokAdr . '/iletisim.php?k=' . $anahtar;

    /* Ayrı bottan bildir; ayrı bot tanımlı değilse genel bota düşer */
    $it = ilt_ayar();
    $turAd = ['genel'=>'Genel','destek'=>'DESTEK','hata'=>'Hata bildirimi','oneri'=>'Oneri',
              'isbirligi'=>'Isbirligi','kurum'=>'KURUM BASVURUSU'][$tur] ?? 'Genel';
    $govde = "\xF0\x9F\x93\xAC " . $turAd . " | " . $marka . "\n"
           . $ad . " <" . $eposta . ">\n"
           . ($konu !== '' ? ($konu . "\n") : '')
           . "\n" . mb_substr($metin, 0, 2500) . "\n\n"
           . "Yanitlamak icin bu iletiyi YANITLAYIN (reply). Yanitiniz kisinin sayfasinda gorunur.\n"
           . "Konusma: " . $baglanti;
    if ($it['token'] !== '' && $it['chat'] !== '') {
        $s = tg_gonder($it['token'], $it['chat'], $govde, 6, 'iletisim');
        if (!empty($s['msgid'])) {
            foreach ($k as $ix => $kk) {
                if (($kk['kod'] ?? '') === $kayit['kod']) { $k[$ix]['tg_msgid'] = (int)$s['msgid']; break; }
            }
            yaz_json('iletiler.json', $k);
        }
    } else {
        $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
        if (!empty($tg['token']) && !empty($tg['chat'])) {
            tg_gonder((string)$tg['token'], (string)$tg['chat'], $govde, 6, 'iletisim>genel');
        } else {
            /* İKİ BOTUN DA TANIMSIZ OLDUĞU DURUM DA YAZILIR.
               Eskiden burada hiçbir şey olmuyordu ve kütükte de hiçbir
               şey yoktu; bildirim bekleyen kişi "gönderildi ama gelmedi"
               ile "hiç denenmedi"yi ayırt edemiyordu. */
            tg_kutuk('iletisim', '', '', false, 'ayar eksik: ne iletisim botu ne genel bot tanimli');
        }
    }

    /* ALICILARA E-POSTA. Telegram'ın yanına değil, ONUN YERİNE
       GEÇEBİLECEK biçimde: bot tanımsızsa, bozuksa ya da devirde
       taşınmadıysa ileti yine de bir insana ulaşır. Alıcılar ayardan
       gelir (ilt_alicilar), kişiden değil. */
    ilt_alicilara_bildir($tur,
        $marka . ' | ' . $turAd . ': ' . ($konu !== '' ? $konu : $ad),
        $turAd . " turunde yeni bir ileti var.\n\n"
        . "Gonderen: " . $ad . " <" . $eposta . ">\n"
        . ($konu !== '' ? ("Konu: " . $konu . "\n") : '')
        . "\n" . mb_substr($metin, 0, 3000) . "\n\n"
        . "Yanitlamak icin panelden Iletiler bolumune gidin:\n"
        . $kokAdr . "/panel.php#iletiler\n\n"
        . "Kisinin kendi konusma sayfasi:\n" . $baglanti . "\n");

    /* Yazana da bir kopya: konuşma bağlantısı kaybolmasın */
    eposta_gonder($eposta, $marka . ' | İletiniz alındı',
        "Sayın " . $ad . ",\n\n" .
        "İletiniz ulaştı, teşekkür ederiz. Yanıt verildiğinde şu sayfada görünecektir:\n" .
        $baglanti . "\n\n" .
        "Bu bağlantı yalnızca sizde olduğu için konuşmanız kimseye açık değildir.\n\n" .
        $marka . "\n" . $kokAdr . "\n");

    cikti(['ok' => true, 'anahtar' => $anahtar, 'baglanti' => $baglanti,
           'mesaj' => 'İletiniz alındı. Yanıt verildiğinde bu sayfada görünecek; bağlantıyı e-posta ile de gönderdik.']);
}

/* KONUŞMAYI OKU (yalnızca anahtarı bilen) */
if ($yol === '/iletisim-konusma' && $metod === 'GET') {
    $a = preg_replace('/[^a-f0-9]/', '', (string)($_GET['k'] ?? ''));
    if (strlen($a) < 24) cikti(['ok' => false, 'hata' => 'Konuşma bulunamadı.'], 404);
    foreach (oku_json('iletiler.json', []) as $kk) {
        if (!is_array($kk) || !hash_equals((string)($kk['anahtar'] ?? ''), $a)) continue;
        $m = [];
        foreach (($kk['mesajlar'] ?? []) as $x) {
            if (!is_array($x)) continue;
            $m[] = ['kim' => (string)($x['kim'] ?? ''), 'metin' => (string)($x['metin'] ?? ''), 'tarih' => (string)($x['tarih'] ?? '')];
        }
        cikti(['ok' => true, 'konusma' => ['ad' => (string)($kk['ad'] ?? ''), 'konu' => (string)($kk['konu'] ?? ''),
               'tarih' => (string)($kk['tarih'] ?? ''), 'mesajlar' => $m]]);
    }
    cikti(['ok' => false, 'hata' => 'Konuşma bulunamadı.'], 404);
}

/* ZİYARETÇİNİN KONUŞMAYA EKLEME YAPMASI */
if ($yol === '/iletisim-ekle' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $a = preg_replace('/[^a-f0-9]/', '', (string)($g['k'] ?? ''));
    $metin = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? ''))), 0, 4000);
    if (strlen($a) < 24) cikti(['ok' => false, 'hata' => 'Konuşma bulunamadı.'], 404);
    if (mb_strlen($metin, 'UTF-8') < 2) cikti(['ok' => false, 'hata' => 'Bir şeyler yazın.'], 400);
    $ipk = 'iletisimek:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 30) cikti(['ok' => false, 'hata' => 'Çok fazla ileti.'], 429);
    $k = oku_json('iletiler.json', []); if (!is_array($k)) $k = [];
    foreach ($k as $ix => $kk) {
        if (!is_array($kk) || !hash_equals((string)($kk['anahtar'] ?? ''), $a)) continue;
        if (count((array)($kk['mesajlar'] ?? [])) > 60) cikti(['ok' => false, 'hata' => 'Bu konuşma çok uzadı.'], 429);
        $k[$ix]['mesajlar'][] = ['kim' => 'ziyaretci', 'metin' => $metin, 'tarih' => date('c')];
        $k[$ix]['okundu'] = false;
        yaz_json('iletiler.json', $k);
        $it = ilt_ayar();
        $govde = "\xF0\x9F\x92\xAC " . (string)($kk['ad'] ?? '') . " yazdi:\n\n" . mb_substr($metin, 0, 2500)
               . "\n\nPanel: " . tg_kok() . '/panel.php#iletiler'
               . "\nKonusma: " . tg_kok() . '/iletisim.php?k=' . $a;
        if ($it['token'] !== '' && $it['chat'] !== '') tg_gonder($it['token'], $it['chat'], $govde, 6, 'iletisim-ek');
        /* Devam eden konuşma da alıcılara düşer; ilk ileti görülüp
           ikincisi görülmeseydi konuşma yarıda kalırdı. */
        ilt_alicilara_bildir((string)($kk['tur'] ?? 'genel'),
            (string)tg_ayar('marka', 'Kutadgu') . ' | Konusmaya ek: ' . (string)($kk['ad'] ?? ''),
            (string)($kk['ad'] ?? '') . " konusmasina ekleme yapti.\n\n"
            . mb_substr($metin, 0, 3000) . "\n\nPanel: " . tg_kok() . "/panel.php#iletiler\n");
        cikti(['ok' => true]);
    }
    cikti(['ok' => false, 'hata' => 'Konuşma bulunamadı.'], 404);
}

/* =====================================================================
   İLETİLERİ YÖNETME: LİSTELE, YANITLA, KAPAT
   ---------------------------------------------------------------------
   BURASI BOŞTU VE BU, SİSTEMİN EN AĞIR AÇIĞIYDI.

   İletişim sayfası ziyaretçiye şunu yazıyor: "Yanıt geldiğinde burada
   belirir ve e-posta ile de haber verir." Oysa yanıt verecek hiçbir yol
   yoktu. Panelde bölüm yok, uç yok; Telegram'dan yanıtlamayı sağlayacak
   webhook ise yalnız bir YORUM olarak duruyordu, kodu hiç yazılmamıştı.
   Yani sistem, tutamayacağı bir söz veriyordu ve bu söz en pahalı
   yerde veriliyordu: bir kurum destek için yazdığında.

   Üç uç yazıldı ve üçü de EDİTÖR YETKİSİNE bağlıdır, tek bir kişiye
   değil. Devirde değişen tek şey kimin editör olduğudur; yanıt yolu
   yerinde kalır.

   YANIT İKİ YERE BİRDEN DÜŞER: konuşma sayfasına (kişinin elindeki
   bağlantı) ve kişinin e-postasına. Yalnız sayfaya düşseydi, kimse o
   sayfayı yeniden açmayacağı için yanıt görülmezdi.
   ===================================================================== */
/* ---- İLETİLER YALNIZCA BAŞ EDİTÖRLERE AÇIK ----
   KURUL BİLDİRİMİ — 19 Ağustos 2026: "editörler siteden gelen mesajları
   görmesin, baş editörler görecek."

   ÖLÇÜLEN KUSUR: yazma uçları (yanıt, istenmeyen, sil, kapat) zaten baş
   editör yetkisi arıyordu, ama OKUMA uçları (liste ve oku) sıradan
   editöre açıktı. Yani bir editör yanıt veremiyor, ama herkesin yazdığı
   her şeyi okuyabiliyordu. Bu, yetkilendirmenin en sık yapılan
   yanlışıdır: eylem korunur, VERİ korunmaz — oysa burada korunması
   gereken şey eylem değil, insanların yazdıklarıdır.

   NİÇİN OKUMA DAHA AĞIR BASAR: iletişim kutusuna yazan kişi bir yayın
   kuruluna değil, sistemin sorumlusuna yazdığını düşünür. İçinde bir
   şikâyet, bir ihbar ya da bir hakem hakkında söz olabilir; o sözün
   hakkında yazılan kişiye açık olması, kutuyu işlevsiz kılar.

   Dört yazma ucu zaten yonetim_yazma_gerek() ile korunuyordu; iki okuma
   ucu da aynı kapıya alındı. Kapı tek: baş editör. */
if ($yol === '/yonetim/ileti-liste' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $suz = preg_replace('/[^a-z]/', '', strtolower((string)($_GET['durum'] ?? '')));
    $out = [];
    foreach (oku_json('iletiler.json', []) as $kk) {
        if (!is_array($kk)) continue;
        $msj = is_array($kk['mesajlar'] ?? null) ? $kk['mesajlar'] : [];
        $son = $msj ? $msj[count($msj) - 1] : [];
        /* İSTENMEYEN İŞARETLİ İLETİ BEKLEMEZ. Bekleyen sayısı bir iş
           listesidir; içine reklam karıştığında sayı işi değil gürültüyü
           ölçer ve bir süre sonra kimse ona bakmaz. */
        $bekliyor = ((string)($son['kim'] ?? '') === 'ziyaretci')
                    && empty($kk['kapali']) && empty($kk['istenmeyen']);
        if ($suz === 'bekleyen' && !$bekliyor) continue;
        if ($suz === 'kapali' && empty($kk['kapali'])) continue;
        if ($suz === 'istenmeyen' && empty($kk['istenmeyen'])) continue;
        /* İşaretlenmiş ileti, açıkça istenmedikçe listede görünmez:
           görülmemesi için işaretlendi. */
        if ($suz === '' && !empty($kk['istenmeyen'])) continue;
        $out[] = [
            'kod'   => (string)($kk['kod'] ?? ''),
            'ad'    => (string)($kk['ad'] ?? ''),
            'eposta'=> (string)($kk['eposta'] ?? ''),
            'konu'  => (string)($kk['konu'] ?? ''),
            'tur'   => (string)($kk['tur'] ?? 'genel'),
            'tarih' => (string)($kk['tarih'] ?? ''),
            'sonTarih' => (string)($son['tarih'] ?? ($kk['tarih'] ?? '')),
            'adet'  => count($msj),
            'bekliyor' => $bekliyor,
            'kapali'   => !empty($kk['kapali']),
            'istenmeyen' => !empty($kk['istenmeyen']),
            /* Listede de görünür: bir editör iletiyi AÇMADAN önce
               başkasının onunla ilgilendiğini bilsin. Açtıktan sonra
               öğrenmek, iki kişinin aynı anda açmasını engellemez. */
            'ustlenen' => ilt_ustlenme($kk)['canli'] ? ilt_ustlenme($kk)['ad'] : '',
            'ozet'  => mb_substr((string)($son['metin'] ?? ''), 0, 160, 'UTF-8'),
        ];
    }
    /* En son yazılan en üstte: bekleyen bir iletiyi aramak zorunda
       kalmak, onu geciktirmenin en sessiz yoludur. */
    usort($out, fn($a2, $b2) => strcmp((string)$b2['sonTarih'], (string)$a2['sonTarih']));
    cikti(['ok' => true, 'ileti' => array_slice($out, 0, 300),
           'bekleyen' => count(array_filter($out, fn($x) => $x['bekliyor']))]);
}

if ($yol === '/yonetim/ileti-oku' && $metod === 'GET') {
    /* Liste gibi bu da baş editöre kapalıdır (gerekçe ileti-liste'de).
       İkisi ayrı kapıya bağlansaydı, listeyi göremeyen biri tek tek
       kimlikleri deneyerek iletileri yine okuyabilirdi. */
    yonetim_yazma_gerek();
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($_GET['kod'] ?? ''));
    $k = oku_json('iletiler.json', []);
    foreach ($k as $ix => $kk) {
        if (!is_array($kk) || (string)($kk['kod'] ?? '') !== $kod) continue;
        /* Okundu işareti yalnız YAZMA yetkisi olanın açmasıyla düşer;
           okuyan bir editörün geçmesi "ilgilenildi" demek değildir. */
        $yaz = false;
        if (yonetim_yazma_mi() && empty($kk['okundu'])) { $k[$ix]['okundu'] = true; $kk['okundu'] = true; $yaz = true; }

        /* ---- ÜSTLENME ----
           Gerekçesi ilt_ustlenme()'nin başındadır. Açan kişi üstlenmiş
           sayılır, AMA yalnız kimse canlı olarak tutmuyorsa: ikinci
           açan, birincinin etiketini sessizce üstünden almaz. Kapalı
           ya da istenmeyen iletide üstlenme yazılmaz; orada üstlenilecek
           bir iş kalmamıştır. */
        /* Kimlik yönetim oturumundan gelir. hs_gerek() BURADA YANLIŞ
           OLURDU: eski tek parolalı yönetici oturumunun bir hesabı
           yoktur ve hs_gerek() onu 401 ile dışarı atardı — okuma
           yetkisi olan biri, yalnız adı yok diye iletiyi göremezdi. */
        $benH  = yonetim_hesap();
        $benAd = is_array($benH) ? hs_gorunen_ad($benH) : 'Yönetici';
        $mevcut = ilt_ustlenme($kk);
        if (yonetim_yazma_mi() && empty($kk['kapali']) && empty($kk['istenmeyen'])
            && (!$mevcut['canli'] || $mevcut['ad'] === $benAd)) {
            $k[$ix]['ustlenen'] = ['ad' => $benAd, 'tarih' => date('c')];
            $kk['ustlenen'] = $k[$ix]['ustlenen'];
            $mevcut = ilt_ustlenme($kk);
            $yaz = true;
        }

        /* ---- KİMLER BAKTI ----
           Üstlenmeden ayrıdır ve DÜŞMEZ: görmüş olmak geri alınmaz.
           Aynı kişi ikinci kez baktığında yalnız saati güncellenir;
           liste kişi başına tek satırdır, yoksa bir editörün on kez
           açması ekranı doldururdu. */
        if ($benAd !== '') {
            $bak = is_array($kk['bakanlar'] ?? null) ? $kk['bakanlar'] : [];
            $yeni = [];
            foreach ($bak as $bb) {
                if (!is_array($bb)) continue;
                if ((string)($bb['ad'] ?? '') === $benAd) continue;
                $yeni[] = ['ad' => (string)$bb['ad'], 'tarih' => (string)($bb['tarih'] ?? '')];
            }
            $yeni[] = ['ad' => $benAd, 'tarih' => date('c')];
            if (count($yeni) > 20) $yeni = array_slice($yeni, -20);
            $k[$ix]['bakanlar'] = $yeni;
            $kk['bakanlar'] = $yeni;
            $yaz = true;
        }
        if ($yaz) yaz_json('iletiler.json', $k);

        cikti(['ok' => true, 'ileti' => [
            'kod' => $kod, 'ad' => (string)($kk['ad'] ?? ''), 'eposta' => (string)($kk['eposta'] ?? ''),
            'konu' => (string)($kk['konu'] ?? ''), 'tur' => (string)($kk['tur'] ?? 'genel'),
            'tarih' => (string)($kk['tarih'] ?? ''), 'kapali' => !empty($kk['kapali']),
            'istenmeyen' => !empty($kk['istenmeyen']),
            'ustlenen' => $mevcut['canli'] ? $mevcut['ad'] : '',
            'ustlenen_tarih' => $mevcut['canli'] ? $mevcut['tarih'] : '',
            'ustlenen_ben' => $mevcut['canli'] && $mevcut['ad'] === $benAd,
            'ustlenme_dk' => ilt_ustlenme_dk(),
            'bakanlar' => array_map(fn($x) => ['ad' => (string)($x['ad'] ?? ''), 'tarih' => (string)($x['tarih'] ?? '')],
                                    is_array($kk['bakanlar'] ?? null) ? $kk['bakanlar'] : []),
            'baglanti' => tg_kok() . '/iletisim.php?k=' . (string)($kk['anahtar'] ?? ''),
            'mesajlar' => array_map(fn($x) => [
                'kim' => (string)($x['kim'] ?? ''), 'metin' => (string)($x['metin'] ?? ''),
                'tarih' => (string)($x['tarih'] ?? ''), 'yazan' => (string)($x['yazan'] ?? ''),
            ], is_array($kk['mesajlar'] ?? null) ? $kk['mesajlar'] : []),
        ]]);
    }
    cikti(['ok' => false, 'hata' => 'İleti bulunamadı.'], 404);
}

if ($yol === '/yonetim/ileti-yanit' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod   = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    $metin = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? ''))), 0, 4000);
    $kapat = !empty($g['kapat']);
    if (mb_strlen($metin, 'UTF-8') < 2) cikti(['ok' => false, 'hata' => 'Yanıt boş olamaz.'], 400);
    $k = oku_json('iletiler.json', []); if (!is_array($k)) $k = [];
    foreach ($k as $ix => $kk) {
        if (!is_array($kk) || (string)($kk['kod'] ?? '') !== $kod) continue;
        /* YANITI KİMİN YAZDIĞI KAYDA GEÇER. Bu sistemde hakem raporu da
           kurul oyu da adıyla durur; bir kuruma verilen cevabın adsız
           kalması onlarla çelişirdi. Ad ziyaretçiye gösterilmek zorunda
           değil, ama kayıtta bulunmak zorunda. */
        $ben = function_exists('hs_oturum') ? (array)hs_oturum() : [];
        $k[$ix]['mesajlar'][] = ['kim' => 'kurul', 'metin' => $metin, 'tarih' => date('c'),
                                 'yazan' => trim((string)($ben['ad'] ?? ''))];
        $k[$ix]['okundu'] = true;
        $k[$ix]['yanit_tarih'] = $k[$ix]['yanit_tarih'] ?? date('c');
        /* ÜSTLENME DÜŞER: iş yapıldı. Yanıt yazıldıktan sonra "şu an
           X ilgileniyor" demek yanlış olurdu; ilgilenilmiş ve bitmiş.
           Kimin yazdığı zaten iletinin kendisinde adıyla duruyor. */
        unset($k[$ix]['ustlenen']);
        if ($kapat) $k[$ix]['kapali'] = true;
        yaz_json('iletiler.json', $k);

        $marka = (string)tg_ayar('marka', 'Kutadgu');
        $bag = tg_kok() . '/iletisim.php?k=' . (string)($kk['anahtar'] ?? '');
        eposta_gonder((string)($kk['eposta'] ?? ''), $marka . ' | İletinize yanıt verildi',
            "Sayın " . (string)($kk['ad'] ?? '') . ",\n\n"
            . "İletinize yanıt verildi:\n\n" . $metin . "\n\n"
            . "Konuşmanın tamamı ve yanıt yazma yeri:\n" . $bag . "\n\n"
            . $marka . "\n" . tg_kok() . "\n");
        cikti(['ok' => true]);
    }
    cikti(['ok' => false, 'hata' => 'İleti bulunamadı.'], 404);
}

/* İSTENMEYEN İŞARETİ — TAHMİN EDEN SÜZGEÇ YERİNE İNSAN KARARI.
   Bu formdan geçen en değerli şey gerçek bir kurum başvurusudur ve
   tahmin eden bir süzgecin yanlış pozitifi tam olarak onu sessizce
   çöpe atar. Karar bu yüzden insanda kalır; sistemin yaptığı tek şey,
   verilen kararın SONUCUNU her yere birden taşımaktır: işaretlenen
   ileti bekleyenlerden düşer, yayımlanan yanıt süresi ölçümüne
   girmez ve kendi saklama süresine tabi olur.
   İşaret geri alınabilir: yanlış işaretlenen bir başvuru kaybolmaz. */
if ($yol === '/yonetim/ileti-istenmeyen' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    $geri = !empty($g['geri']);
    $k = oku_json('iletiler.json', []); if (!is_array($k)) $k = [];
    foreach ($k as $ix => $kk) {
        if (!is_array($kk) || (string)($kk['kod'] ?? '') !== $kod) continue;
        if ($geri) unset($k[$ix]['istenmeyen']);
        else       $k[$ix]['istenmeyen'] = true;
        yaz_json('iletiler.json', array_values($k));
        tg_kutuk_ileti($geri ? 'istenmeyen isareti kaldirildi' : 'istenmeyen isaretlendi');
        cikti(['ok' => true, 'istenmeyen' => !$geri]);
    }
    cikti(['ok' => false, 'hata' => 'İleti bulunamadı.'], 404);
}

/* ELLE SİLME — DENEME İLETİLERİ VE KİŞİNİN İSTEĞİ İÇİN.
   Saklama süresi kendiliğinden işler ama iki durumda beklemek yanlış
   olur: sistemi kurarken atılan deneme iletileri ve bir kişinin
   "kaydımı silin" demesi. İkincisi KVKK'nın verdiği bir haktır ve
   bir hakkın altı ay beklemesi olmaz.

   GERÇEKTEN SİLİNİR. Yerine bir iz bırakılmaz: izin kendisi de o
   kişiyle ilgili bir veridir. Kişinin elindeki bağlantı bundan sonra
   "konuşma bulunamadı" der; silinen bir şeyin silinmiş görünmesi
   doğrudur. */
if ($yol === '/yonetim/ileti-sil' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    /* Toplu silme için: yalnız işaretlenmişleri ya da yalnız kapalıları
       silmek, tek tek tıklamaktan hem hızlı hem daha az hatalıdır. */
    $kume = preg_replace('/[^a-z]/', '', strtolower((string)($g['kume'] ?? '')));
    $k = oku_json('iletiler.json', []); if (!is_array($k)) $k = [];
    if ($kume !== '') {
        if (!in_array($kume, ['istenmeyen', 'kapali'], true)) {
            cikti(['ok' => false, 'hata' => 'Silinebilecek küme yalnızca "istenmeyen" ya da "kapali" olabilir.'], 400);
        }
        $once = count($k);
        $k = array_values(array_filter($k, fn($x) => !(is_array($x) && !empty($x[$kume]))));
        $n = $once - count($k);
        yaz_json('iletiler.json', $k);
        tg_kutuk_ileti('elle toplu silme, kume=' . $kume . ', silinen: ' . $n);
        cikti(['ok' => true, 'silinen' => $n]);
    }
    foreach ($k as $ix => $kk) {
        if (!is_array($kk) || (string)($kk['kod'] ?? '') !== $kod) continue;
        unset($k[$ix]);
        yaz_json('iletiler.json', array_values($k));
        tg_kutuk_ileti('elle silindi (tek)');
        cikti(['ok' => true, 'silinen' => 1]);
    }
    cikti(['ok' => false, 'hata' => 'İleti bulunamadı.'], 404);
}

if ($yol === '/yonetim/ileti-kapat' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    $ac  = !empty($g['ac']);
    $k = oku_json('iletiler.json', []); if (!is_array($k)) $k = [];
    foreach ($k as $ix => $kk) {
        if (!is_array($kk) || (string)($kk['kod'] ?? '') !== $kod) continue;
        /* Kapatmak SİLMEK DEĞİLDİR: konuşma kişinin bağlantısında
           durmayı sürdürür ve yeniden açılabilir. */
        $k[$ix]['kapali'] = !$ac;
        yaz_json('iletiler.json', $k);
        cikti(['ok' => true, 'kapali' => !$ac]);
    }
    cikti(['ok' => false, 'hata' => 'İleti bulunamadı.'], 404);
}


/* ---------------------------------------------------------------------
   HAKEMLİĞE GÖNÜLLÜ OLMA
   Yazar çalışmasını yükledikten sonra, isteyen herkes o çalışmayı
   değerlendirmeye gönüllü olabilir. Başvuru doğrudan hakemlik açmaz;
   editör onayından geçer. Böylece hem kapı herkese açık kalır hem de
   kötü niyetli bir başvuru süreci ele geçiremez.
   Gönüllü hakem yazarın seçimi olmadığı için BAĞIMSIZ sayılır.
   --------------------------------------------------------------------- */
if ($yol === '/hakem-gonullu' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'gonullu:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 12) cikti(['ok' => false, 'hata' => 'Çok fazla başvuru. Lütfen sonra deneyin.'], 429);
    $t = fn($k, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g[$k] ?? ''))), 0, $n);

    $slug    = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($g['slug'] ?? '')));
    $ad      = $t('ad', 120);
    $unvan   = $t('unvan', 60);
    $kurum   = $t('kurum', 220);
    $mail    = mb_strtolower(trim((string)($g['eposta'] ?? '')), 'UTF-8');
    $orcid   = orcid_temiz((string)($g['orcid'] ?? ''));
    $gerekce = $t('yetkinlik', 900);
    $sifatH  = $g['sifat'] ?? [];
    if (is_string($sifatH)) $sifatH = json_decode($sifatH, true);
    $sifat = [];
    if (is_array($sifatH)) { foreach ($sifatH as $s) { $s = (string)$s; if (isset(tg_sifatlar()[$s]) && !in_array($s, $sifat, true)) $sifat[] = $s; } }

    /* ---- OTURUM VARSA KİMLİK OTURUMDAN GELİR ----
       Bu uç bilerek oturum İSTEMEZ ve istemeye de devam eder: doktoralı
       herkes, hesabı olmasa da bir çalışmayı değerlendirmeye gönüllü
       olabilmelidir. Değişen şey kapının kime açıldığı değil, kapıdan
       geçenin kim olduğunun sorulup sorulmadığıdır.

       Girişli bir hesap varsa kimliği artık formdaki metin değil,
       hesabın kendi kaydı belirler. Formdaki ad ve adres yok sayılır;
       çünkü kişi kendi kimliğini forma yanlış yazarak çıkar çatışması
       denetimini atlatabiliyordu ve bu ölçüldü (11 Ağustos 2026).

       ORCID'DE FARK VAR VE BİLEREK VAR: ad ile adres hesapta her zaman
       vardır (hesap açmanın koşuludur), ORCID ise boş kalabilir. Boş
       bir hesap ORCID'i formdakini de ezseydi, ORCID'ini hesabına
       yazmamış bir kişi hiç gönüllü olamazdı. Bu yüzden yalnızca hesapta
       ORCID YAZILIYSA o kullanılır; yazmıyorsa formdaki kabul edilir.
       Formdaki ORCID'in denetimi zayıflatması diye bir durum yok: ad ve
       adres zaten hesaptan geldiği için kimlik oradan da kuruluyor. */
    $hsG = function_exists('hs_oturum') ? hs_oturum() : null;
    if (is_array($hsG)) {
        $hsAd    = trim((string)($hsG['ad'] ?? ''));
        $hsMail  = tg_eposta_anahtar((string)($hsG['eposta'] ?? ''));
        $hsOrcid = orcid_temiz((string)($hsG['orcid'] ?? ''));
        if ($hsAd   !== '') $ad    = mb_substr($hsAd, 0, 120);
        if ($hsMail !== '') $mail  = $hsMail;
        if ($hsOrcid !== '') $orcid = $hsOrcid;
    }

    if ($ad === '') cikti(['ok' => false, 'hata' => 'Adınızı yazın.'], 400);
    if (!unvan_yeterli($unvan)) cikti(['ok' => false, 'hata' => 'Hakemlik için en az "Dr." unvanı aranır.'], 400);
    if (hakemlik_askida($mail, $ad)) {
        cikti(['ok' => false, 'hata' => 'Hakemlik yetkiniz bir kurul oylamasıyla askıya alınmıştır; hakemliğe gönüllü olamazsınız. Karar ve gerekçesi kurul oylamaları sayfasında yazılıdır.'], 403);
    }
    if (!filter_var($mail, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'Geçerli bir e-posta adresi girin.'], 400);
    if ($orcid === '') cikti(['ok' => false, 'hata' => 'Geçerli bir ORCID gerekir (0000-0000-0000-0000 biçiminde).'], 400);
    if (!$sifat) cikti(['ok' => false, 'hata' => 'Hangi sıfatla değerlendireceğinizi seçin.'], 400);
    if (mb_strlen($gerekce, 'UTF-8') < 40) cikti(['ok' => false, 'hata' => 'Kendinizi neden yetkin gördüğünüzü birkaç cümleyle yazın.'], 400);

    /* ---- DOKTORA: KANIT İSTEĞE BAĞLI, KARAR EDİTÖRÜN ----
       14 Ağustos 2026 kurul kararı. Burada iki tür vardı ve ikisi de
       ZORUNLUYDU: Türkiye için e-Devlet barkod kodu, yurt dışı için
       kamusal alan adı. İkisi de 400 ile geri çeviriyordu.

       Kaldırıldı, çünkü ikisi de gerçekte aynı denetime çıkıyordu:
       bir editörün bakıp karar vermesi. Alan adı bir dereceyi
       göstermez; e-Devlet kodu ise yalnız tek bir ülkenin
       araştırmacısında vardır. Gerekçenin tamamı ortak.php'de
       tg_dogrulama_editor()'ün başındadır.

       Şimdi: başvuran isterse derecesinin göründüğü bir adres yazar,
       yazmazsa başvurusu yine alınır. Doktorayı, başvuruyu KABUL EDEN
       editör ya da bir baş editör adıyla doğrular; o ad kayda geçer ve
       görünür (bkz. /editor/gonullu-karar, 'belge_onay').

       tg_kamu_alan() SİLİNMEDİ: adres kamusal bir alan adındaysa bu
       editöre gösterilecek bir kanıttır. Ölçülür, işaretlenir, ama
       kimseyi geri çevirmez. */
    $dogrulama = ['tur' => 'yabanci', 'onay' => false, 'durum' => 'bekliyor', 'tarih' => date('c')];
    $bUrl = mb_substr(trim((string)($g['belge_url'] ?? '')), 0, 500);
    if ($bUrl !== '') {
        $dogrulama['url']     = $bUrl;
        $dogrulama['kaynak']  = $bUrl;
        $dogrulama['kamusal'] = tg_kamu_alan($bUrl);
    }
    $dogrulama['kurum'] = $t('belge_kurum', 220);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = null;
    foreach ($y as $ix => $e) { if ((string)($e['slug'] ?? '') === $slug) { $i = $ix; break; } }
    if ($i === null) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    if (!tg_hakem_araniyor($y[$i])) cikti(['ok' => false, 'hata' => 'Bu çalışma için şu an hakem aranmıyor.'], 409);

    /* Girişli kişi bu çalışmanın yazarıysa uç açıkça söyler. Aşağıdaki
       genel denetim de bunu yakalar ('kendisi'), ama kişi kendi
       çalışmasına gönüllü olmaya çalıştığında gördüğü cümlenin
       anlaşılır olması gerekir; genel metin "bir çalışmanın yazarı"
       diye konuşur, bu ise doğrudan ona söylenir. tg_yazar_mi() hesabı
       ORCID, adres ve (kimliği doğrulanmışsa) adla eşleştirir. */
    if (is_array($hsG) && tg_yazar_mi($y[$i], $hsG)) {
        cikti(['ok' => false, 'cakisma' => 'kendisi',
               'hata' => 'Bu sizin kendi çalışmanız; kendi çalışmanızın hakemi olamazsınız. '
                       . 'This is your own work; you cannot be a reviewer of it.'], 409);
    }

    $anahtar = tg_ad_anahtar($ad);
    /* Çıkar çatışması aynı kapıdan geçer. Gönüllülük denetimi
       gevşetmez: aksine, kişinin kendi seçtiği bir çalışmaya
       yazılabildiği tek yol burasıdır ve karşılıklılığın en kolay
       kurulacağı yer de burasıdır.
       Adres de geçirilir: adını ve ORCID'ini değiştiren biri, başvuru
       onaylandığında bağlantının geleceği adresi değiştiremez. */
    $cak = tg_hakem_cakisma($y, $i, $ad, $orcid, $mail);
    if ($cak) cikti(['ok' => false, 'hata' => $cak['mesaj'], 'cakisma' => $cak['kod']], 409);
    foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $h) {
        if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === $anahtar) {
            cikti(['ok' => false, 'hata' => 'Bu çalışmanın hakemleri arasında zaten yer alıyorsunuz.'], 409);
        }
    }
    if (!is_array($y[$i]['gonulluler'] ?? null)) $y[$i]['gonulluler'] = [];
    foreach ($y[$i]['gonulluler'] as $gg) {
        if (is_array($gg) && tg_ad_anahtar((string)($gg['ad'] ?? '')) === $anahtar) {
            cikti(['ok' => false, 'hata' => 'Bu çalışma için başvurunuz zaten alınmış.'], 409);
        }
    }
    if (count($y[$i]['gonulluler']) >= 40) cikti(['ok' => false, 'hata' => 'Bu çalışma için başvuru sayısı doldu.'], 409);

    $kod = substr(hash('sha256', $slug . $anahtar . microtime() . random_int(0, PHP_INT_MAX)), 0, 12);
    /* Kayda hesabın bilgisi girer, formdaki metin değil: $ad, $mail ve
       (hesapta varsa) $orcid yukarıda oturumdan alındı. Kimliğin nereden
       geldiği de yazılır; editör, önündeki adın doğrulanmış bir hesaptan
       mı yoksa serbest bir formdan mı geldiğini görebilmelidir. */
    $y[$i]['gonulluler'][] = [
        'kod' => $kod, 'ad' => $ad, 'unvan' => $unvan, 'kurum' => $kurum,
        'eposta' => $mail, 'orcid' => $orcid, 'sifat' => $sifat, 'yetkinlik' => $gerekce,
        'dogrulama' => $dogrulama,
        'kimlik' => is_array($hsG) ? 'oturum' : 'form',
        'durum' => 'bekliyor', 'tarih' => date('c'),
    ];
    yaz_json('yazilar.json', $y);

    $marka  = (string)tg_ayar('marka', 'Kutadgu');
    $kokAdr = tg_kok();
    $yMail  = (string)($y[$i]['yazar_erisim']['eposta_acik'] ?? '');
    if ($yMail !== '') {
        eposta_gonder($yMail, $marka . ' | Çalışmanız için bir hakem adayı başvurdu',
            "Sayın " . (string)($y[$i]['yazar'] ?? '') . ",\n\n" .
            "\"" . (string)($y[$i]['baslik'] ?? '') . "\" başlıklı çalışmanızı değerlendirmek üzere bir meslektaşınız kendiliğinden başvurdu:\n\n" .
            "    " . trim($unvan . ' ' . $ad) . ($kurum !== '' ? (', ' . $kurum) : '') . "\n\n" .
            "Bu başvuru sizin öneriniz olmadığı için değerlendirmesi bağımsız sayılacaktır. Kararı editör verir; " .
            "onaylanırsa hakem olarak atanır ve süreç olağan biçimde işler.\n\n" .
            $marka . "\n" . $kokAdr . "\n");
    }
    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'],
            "\xF0\x9F\x99\x8B Hakemlige gonullu basvuru\n" . trim($unvan . ' ' . $ad) . "\nCalisma: " . (string)($y[$i]['baslik'] ?? '') . "\nORCID: " . $orcid, 4);
    }
    cikti(['ok' => true, 'mesaj' => 'Başvurunuz alındı. Editör değerlendirmesinden sonra e-posta ile bilgilendirileceksiniz.']);
}

/* GÖNÜLLÜ HAKEM KARARI (editör / yönetici) */
if ($yol === '/yonetim/gonullu-karar' && $metod === 'POST') {
    /* Editör hesabı ya da yönetici oturumu: ikisi de karar verebilir */
    $edK = ed_kimlik();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id    = (string)($g['id'] ?? '');
    $kod   = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    $karar = (string)($g['karar'] ?? '');
    if (!in_array($karar, ['kabul', 'ret'], true)) cikti(['ok' => false, 'hata' => 'Karar geçersiz.'], 400);
    /* Kararı veren, oturumdaki editörün kendisidir; dışarıdan ad kabul edilmez */
    $atayanAd  = $edK['ad'];
    $atayanTur = $edK['tur'] === 'yonetim' ? 'yonetim' : $edK['tur'];

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = null; $j = null;
    foreach ($y as $ix => $e) {
        if (($e['id'] ?? '') !== $id) continue;
        foreach (tg_dizi($e['gonulluler'] ?? null) as $jx => $gg) {
            if (is_array($gg) && (string)($gg['kod'] ?? '') === $kod) { $i = $ix; $j = $jx; break 2; }
        }
    }
    if ($i === null) cikti(['ok' => false, 'hata' => 'Başvuru bulunamadı.'], 404);
    if ((string)($y[$i]['gonulluler'][$j]['durum'] ?? '') !== 'bekliyor') cikti(['ok' => false, 'hata' => 'Bu başvuru zaten karara bağlanmış.'], 409);

    $ga = $y[$i]['gonulluler'][$j];
    /* Kabul için doktora belgesi koşulu: geçiş dönemi bittiğinde belge zorunludur.
       Editör belgeyi doğruladıysa 'belge_onay' ile birlikte gönderir. */
    if ($karar === 'kabul') {
        if (!empty($g['belge_onay'])) {
            $y[$i]['gonulluler'][$j]['dogrulama']['onay'] = true;
            $y[$i]['gonulluler'][$j]['dogrulama']['durum'] = 'onayli';
            $y[$i]['gonulluler'][$j]['dogrulama']['onay_tarih'] = date('c');
            $y[$i]['gonulluler'][$j]['dogrulama']['onaylayan'] = $atayanAd;
            $ga = $y[$i]['gonulluler'][$j];
        }
        $dd = tg_dogrulama_durum($ga['dogrulama'] ?? null);
        if (!$dd['yeterli']) {
            cikti(['ok' => false, 'hata' => 'Bu adayın doktora belgesi doğrulanmadan hakem olarak atanamaz. Geçiş dönemi sona ermiştir.'], 409);
        }
        /* Başvuru ile onay arasında zaman geçer; o arada aday bu
           yazarın başka bir çalışmasına hakem olmuş olabilir. Bu yüzden
           denetim onay anında yeniden çalıştırılır. Başvurudaki adres de
           geçirilir: başvuru anında yakalanan şeyin onay anında da
           yakalanması gerekir, yoksa kapı yalnızca girişte durur. */
        $cak = tg_hakem_cakisma($y, $i, (string)($ga['ad'] ?? ''), (string)($ga['orcid'] ?? ''),
                                (string)($ga['eposta'] ?? ''));
        if ($cak) cikti(['ok' => false, 'hata' => $cak['mesaj'], 'cakisma' => $cak['kod']], 409);
    }
    $y[$i]['gonulluler'][$j]['durum'] = $karar;
    $y[$i]['gonulluler'][$j]['karar_tarih'] = date('c');
    $y[$i]['gonulluler'][$j]['karar_veren'] = $atayanAd;

    $marka  = (string)tg_ayar('marka', 'Kutadgu');
    $kokAdr = tg_kok();
    $hMail  = (string)($ga['eposta'] ?? '');

    if ($karar === 'ret') {
        yaz_json('yazilar.json', $y);
        if ($hMail !== '') {
            eposta_gonder($hMail, $marka . ' | Başvurunuz için teşekkür ederiz',
                "Sayın " . trim((string)($ga['unvan'] ?? '') . ' ' . (string)($ga['ad'] ?? '')) . ",\n\n" .
                "Bir çalışmayı değerlendirmeye gönüllü olduğunuz için teşekkür ederiz. Bu çalışmanın hakem " .
                "kadrosu tamamlandığından başvurunuzu bu kez değerlendiremedik.\n\n" .
                "Bu, uzmanlığınıza ilişkin bir değerlendirme değildir. Hakem aranan çalışmaları şu adreste " .
                "izleyebilir ve dilediğiniz zaman yeniden başvurabilirsiniz:\n" . $kokAdr . "/bekleyen.php\n\n" .
                $marka . "\n" . $kokAdr . "\n");
        }
        cikti(['ok' => true, 'durum' => 'ret']);
    }

    if (!is_array($y[$i]['hakemler'] ?? null)) $y[$i]['hakemler'] = [];
    $hAd    = trim((string)($ga['ad'] ?? ''));
    $hToken = substr(hash('sha256', $hAd . microtime() . random_int(0, PHP_INT_MAX)), 0, 32);
    $hSifre = uret_sifre();
    $y[$i]['hakemler'][] = [
        'ad' => $hAd,
        'token' => $hToken,
        'eposta_hash' => $hMail !== '' ? hash('sha256', $hMail . '|hakem') : '',
        'eposta_acik' => $hMail,
        'sifre' => $hSifre,
        'davet_durum' => 'kabul',
        'davet_tarih' => (string)($ga['tarih'] ?? date('c')),
        'karar' => '', 'rapor' => '', 'tarih' => '', 'dosya' => '',
        'profil' => ['unvan' => (string)($ga['unvan'] ?? ''), 'kurum' => (string)($ga['kurum'] ?? ''),
                     'orcid' => (string)($ga['orcid'] ?? ''), 'eposta' => $hMail, 'web' => ''],
        'raporlar' => [],
        'sifat' => is_array($ga['sifat'] ?? null) ? $ga['sifat'] : [],
        'yetkinlik' => (string)($ga['yetkinlik'] ?? ''),
        'dogrulama' => is_array($ga['dogrulama'] ?? null) ? $ga['dogrulama'] : [],
        'atayan' => ['tur' => 'gonullu', 'ad' => $atayanAd, 'tarih' => date('c'), 'onay_turu' => $atayanTur],
    ];
    $y[$i]['gonulluler'][$j]['hakem_token'] = $hToken;
    yaz_json('yazilar.json', $y);

    $hLink = tg_url('/hakem.php?t=' . $hToken);
    if ($hMail !== '') {
        eposta_gonder($hMail, $marka . ' | Hakemlik başvurunuz kabul edildi',
            "Sayın " . trim((string)($ga['unvan'] ?? '') . ' ' . $hAd) . ",\n\n" .
            "\"" . (string)($y[$i]['baslik'] ?? '') . "\" başlıklı çalışmayı değerlendirme başvurunuz kabul edildi.\n\n" .
            "Değerlendirme sayfanız:\n" . $hLink . "\n" .
            "Erişim şifreniz: " . $hSifre . "\n\n" .
            "Bu sistemde kör hakemlik uygulanmaz: adınız, kararınız ve raporunuz çalışmayla birlikte açıkça " .
            "yayımlanır. Raporunuzu gönderdiğinizde bu çalışmayla ilgili süreciniz tamamlanır ve rapor " .
            "değiştirilemez.\n\n" .
            "Değerlendirmeyi kendiniz istediğiniz için bu hakemlik yazarın önerisi sayılmaz; bağımsız " .
            "değerlendirme olarak kaydedilir.\n\n" .
            $marka . "\n" . $kokAdr . "\n");
    }
    cikti(['ok' => true, 'durum' => 'kabul', 'token' => $hToken, 'sifre' => $hSifre, 'link' => $hLink]);
}

/* HAKEM ARANAN ÇALIŞMALAR (herkese açık liste) */
if ($yol === '/bekleyen' && $metod === 'GET') {
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $out = [];
    foreach ($y as $e) {
        if (!is_array($e) || !tg_hakem_araniyor($e)) continue;
        $tam = 0;
        foreach (tg_dizi($e['hakemler'] ?? null) as $h) { if (is_array($h) && trim((string)($h['rapor'] ?? '')) !== '') $tam++; }
        $out[] = [
            'slug'    => (string)($e['slug'] ?? ''),
            'baslik'  => (string)($e['baslik'] ?? ''),
            'baslik_en' => (string)($e['baslik_en'] ?? ''),
            'ozet'    => mb_substr(strip_tags((string)($e['ozet'] ?? '')), 0, 400),
            'ozet_en' => mb_substr(strip_tags((string)($e['ozet_en'] ?? '')), 0, 400),
            'yazar'   => (string)($e['yazar'] ?? ''),
            'alan'    => (string)($e['alan'] ?? ''),
            'anahtar' => tg_metin($e['anahtar'] ?? ''),
            'tarih'   => (string)($e['tarih'] ?? ''),
            'hakem_tamam' => $tam,
            'hedef'   => (int)tg_ayar('hakem_hedef', 3),
            /* Bu listedeki her çalışma hakem arıyor, ama hepsi aynı yerde
               değil: kimi tek rapor bile almamıştır ('aranan'), kimi ilk
               raporunu almış ama eşiği doldurmamıştır ('suruyor'). Listeyi
               gösteren taraf bu ayrımı 'tur'dan çıkaramaz, çünkü ikisinde de
               tur='hakemli' yazar. */
            'asama'   => tg_hakem_asamasi($e),
        ];
    }
    usort($out, fn($a, $b) => ($a['hakem_tamam'] <=> $b['hakem_tamam']) ?: strcmp((string)$b['tarih'], (string)$a['tarih']));
    cikti(['ok' => true, 'calismalar' => $out]);
}

/* ---------------------------------------------------------------------
   DÜZELTME / GERİ ÇEKME / ENDİŞE KAYDI
   Yayımdan sonra hata bulunduğunda bilimsel kaydın izleyeceği yol budur.
   Özgün metin hiçbir zaman silinmez; kayıt metnin üstüne eklenir, tarihiyle
   ve kararı verenin adıyla birlikte okuyucuya gösterilir.

/* Hakem token + e-posta doğrula; [makale_index, hakem_index] ya da null. Sabit zamanlı. */
function hakem_dogrula(array $y, string $t, string $mail, string $sifre = ''): ?array {
    if ($t === '') return null;
    foreach ($y as $i => $e) {
        foreach (tg_dizi($e['hakemler'] ?? null) as $j => $h) {
            if (is_array($h) && ($h['token'] ?? '') !== '' && hash_equals((string)$h['token'], $t)) {
                $eh = (string)($h['eposta_hash'] ?? '');
                if ($eh !== '' && !hash_equals($eh, hash('sha256', $mail . '|hakem'))) return ['eposta_yanlis' => true];
                $sf = (string)($h['sifre'] ?? '');
                if ($sf !== '' && !hash_equals($sf, trim($sifre))) return ['sifre_yanlis' => true];
                return [$i, $j];
            }
        }
    }
    return null;
}
/* HAKEM: token+e-posta ile form verisini getir */
if ($yol === '/hakem-form' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    $ipk = 'hakem:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 30) cikti(['ok' => false, 'hata' => 'Çok fazla deneme, biraz sonra tekrar deneyin.'], 429);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = hakem_dogrula($y, $t, $mail, $sifre);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Bağlantı bulunamadı.'], 404);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'Bu e-posta, bağlantının gönderildiği adresle eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı. Davet e-postasındaki şifreyi girin.'], 403);
    [$i, $j] = $d; $e = $y[$i]; $h = $e['hakemler'][$j];
    cikti(['ok' => true, 'baslik' => (string)($e['baslik'] ?? ''), 'hakem' => (string)($h['ad'] ?? ''),
        'karar' => (string)($h['karar'] ?? ''), 'rapor' => (string)($h['rapor'] ?? ''),
        'surum_sayisi' => is_array($h['raporlar'] ?? null) ? count($h['raporlar']) : 0,
        'endeks' => is_array($h['endeks'] ?? null) ? array_values($h['endeks']) : [],
        'alan' => (string)($e['alan'] ?? ''),
        'endeks_anket' => is_array($h['endeks_anket'] ?? null) ? array_values($h['endeks_anket']) : [],
        'kapali' => !empty($h['kapali']),
        'etik_gorus' => (string)($h['etik_gorus'] ?? ''),
        'tekrar' => is_array($h['tekrar'] ?? null) ? $h['tekrar'] : null,
        'sifat' => is_array($h['sifat'] ?? null) ? array_values($h['sifat']) : [],
        'yetkinlik' => (string)($h['yetkinlik'] ?? ''),
        'notlar' => is_array($h['notlar'] ?? null) ? array_values($h['notlar']) : [],
        /* Yazarın yanıtı hakemin KARARINDAN ÖNCE görünür. Bu diyaloğun
           bütün anlamı budur: hakem, yazarın açıklamasını okuyarak
           karar versin. */
        'diyalog' => tg_diyalog($h),
        'diyalog_sira' => tg_diyalog_sira($h),
        'diyalog_tur' => tg_diyalog_tur($h),
        'diyalog_ust' => (int)tg_ayar('diyalog_tur_ust', 2),
        'diyalog_asgari' => (int)tg_ayar('diyalog_asgari', 80),
        'profil' => is_array($h['profil'] ?? null) ? [
            'unvan' => (string)($h['profil']['unvan'] ?? ''), 'kurum' => (string)($h['profil']['kurum'] ?? ''),
            'orcid' => (string)($h['profil']['orcid'] ?? ''), 'eposta' => (string)($h['profil']['eposta'] ?? ''),
            'web' => (string)($h['profil']['web'] ?? ''),
        ] : [],
        'metin' => (string)($e['metin_ham'] ?? '') !== '' ? (string)$e['metin_ham'] : (string)($e['metin'] ?? ''),
        'ozet' => strip_tags((string)($e['ozet'] ?? ''))]);
}
/* Hakemin dizin/çeyreklik değerlendirmesini temizler.
   Yapı: [{ 'endeks'=>'trdizin', 'secim'=>'Q1'|'', 'yanit'=>[1..5,...] }, ...]
   Bir kez gönderilir; sonrasında değiştirilemez. */
function endeks_anket_temiz($ham): array {
    if (is_string($ham)) $ham = json_decode($ham, true);
    if (!is_array($ham)) return [];
    $gecerli = ['trdizin','sobiad','esci','ssci','scie','ahci','scopus','pubmed','econlit','eric','doaj','q'];
    $out = [];
    foreach ($ham as $a) {
        if (!is_array($a)) continue;
        $k = preg_replace('/[^a-z]/', '', strtolower((string)($a['endeks'] ?? '')));
        if (!in_array($k, $gecerli, true)) continue;
        $sec = preg_replace('/[^A-Za-z0-9]/', '', (string)($a['secim'] ?? ''));
        $sec = mb_substr($sec, 0, 8);
        $yn = [];
        foreach ((array)($a['yanit'] ?? []) as $v) {
            $v = (int)$v;
            $yn[] = ($v >= 1 && $v <= 5) ? $v : 0;
            if (count($yn) >= 12) break;
        }
        if (!$yn) continue;
        $dolu = array_values(array_filter($yn, fn($x) => $x > 0));
        $out[] = [
            'endeks' => $k,
            'secim'  => $sec,
            'yanit'  => $yn,
            'ortalama' => $dolu ? round(array_sum($dolu) / count($dolu), 2) : 0,
        ];
        if (count($out) >= 8) break;
    }
    return $out;
}

/* HAKEM: rapor + karar gönder (token+e-posta doğrulamalı) */
if ($yol === '/hakem-gonder' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    $karar = (string)($g['karar'] ?? '');
    $ipk = 'hakem:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 30) cikti(['ok' => false, 'hata' => 'Çok fazla deneme.'], 429);
    if (!in_array($karar, ['kabul', 'kucuk', 'buyuk', 'ret'], true)) cikti(['ok' => false, 'hata' => 'Karar seçilmeli.'], 400);
    /* Rapor artık biçimli yazılabiliyor: başlık, madde, çizelge. Gelen
       HTML süzülür; izinli olmayan hiçbir etiket ve öznitelik geçmez.
       Uzunluk denetimi etiketleri değil metnin kendisini sayar. */
    $rapor = mb_substr(guvenli_html((string)($g['rapor'] ?? '')), 0, 40000);
    if (mb_strlen(tg_duz($rapor), 'UTF-8') < 10) cikti(['ok' => false, 'hata' => 'Rapor çok kısa.'], 400);
    /* Endeks/dergi önerileri (kabul için) + metin içi işaretli notlar */
    $endeks = [];
    $eh = json_decode((string)($g['endeks'] ?? '[]'), true);
    if (is_array($eh)) { foreach ($eh as $e2) { $e2 = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)$e2)), 0, 40); if ($e2 !== '') $endeks[] = $e2; } $endeks = array_slice(array_values(array_unique($endeks)), 0, 24); }
    $notlar = [];
    $nh = json_decode((string)($g['notlar'] ?? '[]'), true);
    if (is_array($nh)) { foreach ($nh as $n) { if (!is_array($n)) continue; $al = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($n['alinti'] ?? ''))), 0, 600); $nt = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($n['not'] ?? ''))), 0, 1200); if ($al !== '' || $nt !== '') $notlar[] = ['alinti' => $al, 'not' => $nt]; } $notlar = array_slice($notlar, 0, 80); }
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = hakem_dogrula($y, $t, $mail, $sifre);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Bağlantı bulunamadı.'], 404);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'E-posta eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i, $j] = $d;
    /* ---- İKİ AYRI ŞEY, İKİ AYRI ALAN ----
       Burada tek bir 'kapali' alanı İKİ İŞİ birden yapıyordu:
         a) hakem raporunu bir daha gönderemez  (değerlendirme bitti)
         b) yazar bu hakeme not yazamaz          (diyalog kanalı kapalı)

       İkisi aynı şey değildir ve karışmaları bir kusur doğurdu: küçük
       revizyon kararında (a) doğru, (b) YANLIŞtır. Küçük revizyon bir
       son değil, yazara yöneltilmiş bir istektir; yazarın yanıt
       veremediği bir revizyon isteği istek değil hükümdür.

       Bu karışıklık iki kapının BİRBİRİNİN TERSİNİ söylemesine yol
       açmıştı: diyalog-kapi "küçük revizyonda kanal açık kalmalı" diyip
       kırmızı duruyordu, hakem-akis "küçük revizyondan sonra kapandı"
       diyip yeşil duruyordu. İkisi de aynı alana bakıyordu.

       Artık:
         degerlendirme_bitti  hakem yeniden gönderemez (kabul/kucuk/ret)
         kapali               diyalog kanalı kapalı    (kabul/ret)

       Eski kayıtlarda 'degerlendirme_bitti' yoktur; bu yüzden ikisine
       de bakılır — göç ettirmeye gerek kalmaz. */
    if (!empty($y[$i]['hakemler'][$j]['kapali'])) {
        cikti(['ok' => false, 'hata' => 'Bu değerlendirme tamamlandı ve değiştirilemez.'], 409);
    }
    /* Hakem profil bilgileri (açık hakemlik: hakem adıyla durur; alanlar herkese açık gösterilir) */
    $pAl = fn($k, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g[$k] ?? ''))), 0, $n);
    $profilYeni = ['unvan' => $pAl('h_unvan', 60), 'kurum' => $pAl('h_kurum', 220), 'orcid' => $pAl('h_orcid', 60), 'eposta' => $pAl('h_eposta', 120), 'web' => $pAl('h_web', 200)];
    $profilEski = is_array($y[$i]['hakemler'][$j]['profil'] ?? null) ? $y[$i]['hakemler'][$j]['profil'] : [];
    $profil = [];
    foreach (['unvan', 'kurum', 'orcid', 'eposta', 'web'] as $pk) { $profil[$pk] = $profilYeni[$pk] !== '' ? $profilYeni[$pk] : (string)($profilEski[$pk] ?? ''); }
    $y[$i]['hakemler'][$j]['profil'] = $profil;
    $tarih = date('c');
    $dosyaYol = '';
    /* İsteğe bağlı hakem dosyası (Word/PDF) - katı uzantı denetimi */
    if (!empty($_FILES['dosya']) && ((int)($_FILES['dosya']['error'] ?? 4)) === 0 && (int)($_FILES['dosya']['size'] ?? 0) <= 15 * 1024 * 1024) {
        $uz = strtolower(pathinfo((string)($_FILES['dosya']['name'] ?? ''), PATHINFO_EXTENSION));
        if (in_array($uz, ['doc', 'docx', 'pdf', 'odt'], true)) {
            $kls = __DIR__ . '/../dosya/hakem';
            if (!is_dir($kls)) @mkdir($kls, 0755, true);
            $fad = substr(hash('sha256', microtime() . mt_rand()), 0, 18) . '.' . $uz;
            if (@move_uploaded_file($_FILES['dosya']['tmp_name'], $kls . '/' . $fad) || @copy($_FILES['dosya']['tmp_name'], $kls . '/' . $fad)) {
                @chmod($kls . '/' . $fad, 0644);
                $dosyaYol = '/dosya/hakem/' . $fad;
            }
        }
    }
    /* Raporu SÜRÜM olarak ekle; en son sürüm 'güncel/yayında' sayılır */
    if (!is_array($y[$i]['hakemler'][$j]['raporlar'] ?? null)) $y[$i]['hakemler'][$j]['raporlar'] = [];
    $y[$i]['hakemler'][$j]['raporlar'][] = ['karar' => $karar, 'rapor' => $rapor, 'tarih' => $tarih, 'dosya' => $dosyaYol, 'endeks' => $endeks, 'notlar' => $notlar];
    if (count($y[$i]['hakemler'][$j]['raporlar']) > 20) array_shift($y[$i]['hakemler'][$j]['raporlar']);
    $y[$i]['hakemler'][$j]['karar'] = $karar;
    $y[$i]['hakemler'][$j]['rapor'] = $rapor;
    $y[$i]['hakemler'][$j]['tarih'] = $tarih;
    $y[$i]['hakemler'][$j]['dosya'] = $dosyaYol;
    $y[$i]['hakemler'][$j]['endeks'] = $endeks;
    $y[$i]['hakemler'][$j]['notlar'] = $notlar;
    /* Dizin ve çeyreklik değerlendirmesi: bir kez yazılır, bir daha değişmez */
    $anket = endeks_anket_temiz($g['endeks_anket'] ?? '[]');
    if ($anket && empty($y[$i]['hakemler'][$j]['endeks_anket'])) {
        $y[$i]['hakemler'][$j]['endeks_anket'] = $anket;
        $y[$i]['hakemler'][$j]['anket_tarih'] = $tarih;
    }
    /* Hakemin sıfatı ve yetkinlik gerekçesi: alan uyuşmazlığı engel değil,
       okuyucuya gösterilen bir bilgidir. */
    $sfHam = json_decode((string)($g['sifat'] ?? '[]'), true);
    $sifat = [];
    if (is_array($sfHam)) {
        foreach ($sfHam as $s) { $s = (string)$s; if (isset(tg_sifatlar()[$s]) && !in_array($s, $sifat, true)) $sifat[] = $s; }
    }
    if ($sifat) $y[$i]['hakemler'][$j]['sifat'] = $sifat;
    $yetkin = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['yetkinlik'] ?? ''))), 0, 900);
    if ($yetkin !== '') $y[$i]['hakemler'][$j]['yetkinlik'] = $yetkin;

    /* Tekrarlanabilirlik değerlendirmesi: bir kez yazılır, değişmez. */
    $tkHam = json_decode((string)($g['tekrar'] ?? '{}'), true);
    if (is_array($tkHam) && empty($y[$i]['hakemler'][$j]['tekrar'])) {
        $tkYok = !empty($tkHam['yok']);
        $tkYanit = [];
        if (!$tkYok && is_array($tkHam['yanit'] ?? null)) {
            foreach (array_slice($tkHam['yanit'], 0, 12) as $v) {
                $v = (int)$v; $tkYanit[] = ($v >= 1 && $v <= 5) ? $v : 0;
            }
        }
        $ort = 0.0;
        if ($tkYanit) { $ort = round(array_sum($tkYanit) / max(1, count($tkYanit)), 2); }
        if ($tkYok || $tkYanit) {
            $y[$i]['hakemler'][$j]['tekrar'] = ['yok' => $tkYok, 'yanit' => $tkYanit, 'ortalama' => $ort, 'tarih' => $tarih];
        }
    }

    /* Etik kurul görüşü: bu belirleme hakemlere aittir. */
    $etikGorus = preg_replace('/[^a-z]/', '', strtolower((string)($g['etik_gorus'] ?? '')));
    if (!in_array($etikGorus, ['gerekli', 'gereksiz', 'emin_degilim'], true)) $etikGorus = '';
    $etikGorus = str_replace('emin_degilim', 'emin', $etikGorus);
    if ($etikGorus !== '') $y[$i]['hakemler'][$j]['etik_gorus'] = $etikGorus;

    /* İki hakem "gerekli" dediyse ve onaylı belge yoksa çalışma askıya alınır. */
    $gerekliSay = 0;
    foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $hh) {
        if (is_array($hh) && (string)($hh['etik_gorus'] ?? '') === 'gerekli') $gerekliSay++;
    }
    if (!is_array($y[$i]['etik'] ?? null)) $y[$i]['etik'] = ['durum' => '', 'belge' => '', 'no' => '', 'onay' => false, 'onay_tarih' => ''];
    $y[$i]['etik']['hakem_gerekli'] = $gerekliSay;
    if ($gerekliSay >= 2 && empty($y[$i]['etik']['onay'])) {
        $y[$i]['etik']['durum'] = 'gerekli';
        $y[$i]['etik']['kaynak'] = 'hakem';
        if (empty($y[$i]['etik']['yazar_bildirildi'])) {
            $y[$i]['etik']['yazar_bildirildi'] = date('c');
            $marka2 = function_exists('tg_ayar') ? (string)tg_ayar('marka', 'Kutadgu') : 'Kutadgu';
            $kok2   = function_exists('tg_kok') ? tg_kok() : '';
            $yMail2 = (string)($y[$i]['yazar_erisim']['eposta_acik'] ?? '');
            if ($yMail2 !== '') {
                eposta_gonder($yMail2, $marka2 . ' | Etik kurul belgesi isteniyor',
                    "Sayın " . (string)($y[$i]['yazar'] ?? '') . ",\n\n" .
                    "\"" . (string)($y[$i]['baslik'] ?? '') . "\" başlıklı çalışmanızı değerlendiren iki hakem, " .
                    "bu çalışma için etik kurul izninin gerekli olduğu görüşünü bildirdi.\n\n" .
                    "Onaylı etik kurul belgesini yazar panelinizden sisteme eklemenizi rica ederiz. " .
                    "Belge sunulup doğrulanana kadar çalışmanızın sayfasında, izin belgesinin bulunmadığını " .
                    "bildiren bir açıklama görünecektir. Metne erişim kapatılmaz.\n\n" .
                    "Hakemler bu belirlemede yanılmış olabileceğini düşünüyorsanız, gerekçenizi yazar panelinden iletebilirsiniz.\n\n" .
                    $marka2 . "\n" . $kok2 . "\n");
            }
        }
    }

    /* ---- SÜRECİ HANGİ KARAR BİTİRİR ----
       BURADA BİR KUSUR VARDI VE KAYDA GEÇİYOR. Eskiden bu satır
       'kabul', 'kucuk' ve 'ret' kararlarında kanalı kapatıyordu; üstündeki
       yorum da "yalnızca büyük revizyon süreci açık bırakır" diyordu.

       Oysa kanalın açık olup olmadığına karar veren TEK KAYNAK
       tg_diyalog_sira()'dır ve o şöyle der:

           if ($karar !== 'buyuk' && $karar !== 'kucuk') return '';

       Yani işlev KÜÇÜK revizyonda kanalı AÇIK sayar, uç ise kapatıyordu.
       İki yer, iki ayrı kural. Sonuç, bu depoda beş kez yakalanmış olanın
       aynısıydı: sayfa yazara "hakeme not yazabilirsiniz" diyor, uç 409
       ile "bu hakemle diyalog turlarınız doldu" diyordu.

       Hangisinin doğru olduğu kuralın kendisinden okunur: küçük revizyon
       bir SON değil, YAZARA YÖNELTİLMİŞ BİR İSTEKtir. Yazarın yanıt
       veremediği bir revizyon isteği, istek değil hükümdür. Kapatan uç
       düzeltildi.

       Süreci bitiren iki karar kaldı: kabul ve ret. İkisi de yazara
       sorulacak bir şey bırakmaz; itiraz yolu ayrıdır ve kurul
       oylamasından geçer. */
    /* İKİNCİ BİR ALAN GEREKMEDİ ve neden gerekmediği önemlidir.
       Bir ara 'degerlendirme_bitti' diye ayrı bir alan yazdım: hakem
       raporunu bir daha gönderemesin ama yazar not yazabilsin diye.
       Ölçüm bunun yanlış olduğunu gösterdi — diyalog kapısı, BÜYÜK
       revizyondan sonra hakemin geri gelip kararını 'ret' olarak
       kesinleştirebildiğini ölçüyor ve bu doğru: revizyon isteği bir
       TUR açar, turun sonunda hakem yeniden karar verir.

       Küçük revizyon da bir revizyon isteğidir. İkisini ayırmak,
       "az düzeltme isteyen hakem geri dönemez, çok düzeltme isteyen
       dönebilir" demek olurdu; tutarsızdır. İki karar da turu açık
       bırakır, iki karar da hakemi geri bekler.

       Süreci bitiren iki karar: kabul ve ret. İkisi de yazara
       sorulacak bir şey bırakmaz; itiraz yolu ayrıdır ve kurul
       oylamasından geçer. */
    if (in_array($karar, ['kabul', 'ret'], true)) {
        $y[$i]['hakemler'][$j]['kapali'] = true;
        $y[$i]['hakemler'][$j]['kapanis'] = $tarih;
    }
    /* İki ret kararı: çalışma hakemli makale niteliğini yitirir, YAZI olarak sistemde kalır.
       Silinmez; ret kararı ve gerekçeleri şeffaflık gereği gösterilmeye devam eder.
       KARAR: aşağıdaki tur karşılaştırması olduğu gibi bırakıldı. Burada
       çalışmanın YOLU değiştiriliyor (hakemli yoldan yazıya iniyor); soru
       "hakemden geçti mi" değil, "bu çalışma hâlâ hakemli yolda mı"dır. */
    $retSay = 0;
    foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $hh) { if (is_array($hh) && (string)($hh['karar'] ?? '') === 'ret') $retSay++; }
    if ($retSay >= 2 && (string)($y[$i]['tur'] ?? '') === 'hakemli') {
        $y[$i]['tur'] = 'yazi';
        $y[$i]['ret_donusum'] = ['tarih' => date('c'), 'onceki_tur' => 'hakemli', 'ret_sayisi' => $retSay];
    }
    /* Rapor nitelik eşiği: karşılamayan rapor yayımlanır ama sayılmaz. */
    $nit = tg_rapor_nitelik($y[$i]['hakemler'][$j]);
    $y[$i]['hakemler'][$j]['nitelik'] = $nit['yeterli'];
    $y[$i]['hakemler'][$j]['nitelik_eksik'] = $nit['eksik'];

    yaz_json('yazilar.json', $y);
    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        $kararAd = ['kabul'=>'Kabul','kucuk'=>'Kucuk revizyon','buyuk'=>'Buyuk revizyon','ret'=>'RET'][$karar];
        $hakemAd = (string)($y[$i]['hakemler'][$j]['ad'] ?? '');
        tg_gonder((string)$tg['token'], (string)$tg['chat'], "\xF0\x9F\x93\x9D Hakem raporu geldi (" . $hakemAd . ")\nKarar: " . $kararAd . "\n\n" . mb_substr($rapor, 0, 500) . "\n\nPanel: https://kutadgu.net/panel.php#yonetim", 4);
    }
    /* Hakem, raporunun sayıma katılıp katılmadığını gönderdikten sonra da
       görsün: gönderim ekranındaki gösterge kaybolduğunda sonuç belirsiz
       kalmasın. */
    cikti(['ok' => true, 'nitelik' => $nit['yeterli'], 'eksik' => $nit['eksik']]);
}

/* ================= YAZAR BAŞVURU + PORTAL ================= */
/* Ünvan Dr. ve üzeri mi? (min. Dr. kuralı; doç./prof. doktora içerir)
   Ölçüt ortak.php'de tek bir yerde durur; kamusal sayfalar da onu
   kullanır. Buradaki ad, eski çağrı yerleri kırılmasın diye kalıyor. */
function unvan_yeterli(string $u): bool { return tg_unvan_yeterli($u); }
/* Yazar içeriği için güvenli HTML: yalnızca izinli etiket/öznitelikler kalır.
   Olay öznitelikleri (onclick vb.), script/iframe/style, javascript: adresleri tamamen elenir. */
/* guvenli_html() ortak.php icine tasindi: kamusal sayfalarin da
   hakem raporlarini ve editor notlarini bicimli basabilmesi icin. */
/* ORCID doğrula ve biçimle. Geçersizse '' döner. (ISO 7064 MOD 11-2 sağlama basamağı) */
function orcid_temiz($s): string {
    $s = strtoupper(trim((string)$s));
    $s = preg_replace('#^HTTPS?://(WWW\.)?ORCID\.ORG/#i', '', $s) ?? $s;
    $d = preg_replace('/[^0-9X]/', '', $s) ?? '';
    if (strlen($d) !== 16) return '';
    if (strpos(substr($d, 0, 15), 'X') !== false) return '';  /* X yalnızca son basamakta olabilir */
    $t = 0;
    for ($i = 0; $i < 15; $i++) $t = ($t + (int)$d[$i]) * 2;
    $son = (12 - ($t % 11)) % 11;
    $bek = ($son === 10) ? 'X' : (string)$son;
    if ($d[15] !== $bek) return '';
    return substr($d, 0, 4) . '-' . substr($d, 4, 4) . '-' . substr($d, 8, 4) . '-' . substr($d, 12, 4);
}
/* Scopus Author ID: yalnızca rakam (8-16). Profil bağlantısı verilirse authorId ayıklanır. */
function scopus_temiz($s): string {
    $s = trim((string)$s);
    if (preg_match('/authorId=(\d{6,})/i', $s, $m)) $s = $m[1];
    $d = preg_replace('/\D/', '', $s) ?? '';
    if ($d === '' || strlen($d) < 8 || strlen($d) > 16) return '';
    return $d;
}
/* Yazar erişimi doğrula (token + e-posta + şifre) */
/* Yazarın kendi çalışmasına erişimi iki yoldan olur:
     1. Kabul e-postasıyla giden bağlantı (token + posta + şifre)
     2. Hesabına girmiş olmak
   İkincisi sonradan eklendi. Hesap düzeni kurulmadan önce tek yol
   bağlantıydı; giriş yapmış bir yazarın kendi çalışmasını
   düzenleyememesi bir kural değil, bir eksiklikti.

   $g isteğin gövdesidir; içinde 'y' (slug) ya da 'id' bulunur. */
function yazar_yetki(array $y, array $g): ?array {
    $t     = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail  = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    if ($t !== '') return yazar_dogrula($y, $t, $mail, $sifre);

    /* Bağlantı yoksa: oturum. Yalnızca kendi çalışması açılır. */
    $h = function_exists('hs_oturum') ? hs_oturum() : null;
    if ($h === null) return null;
    $anah = trim((string)($g['y'] ?? ($g['id'] ?? '')));
    if ($anah === '') return null;
    foreach ($y as $i => $e) {
        if (!is_array($e)) continue;
        $eslesir = (string)($e['slug'] ?? '') === $anah
                || (string)($e['id'] ?? '') === $anah
                || tg_kod_esles($e, $anah);
        if (!$eslesir) continue;
        if (!tg_yazar_mi($e, $h)) return ['yetkisiz' => true];
        return [$i];
    }
    return null;
}

function yazar_dogrula(array $y, string $t, string $mail, string $sifre = ''): ?array {
    if ($t === '') return null;
    foreach ($y as $i => $e) {
        $ye = $e['yazar_erisim'] ?? null;
        if (is_array($ye) && ($ye['token'] ?? '') !== '' && hash_equals((string)$ye['token'], $t)) {
            $eh = (string)($ye['eposta_hash'] ?? '');
            if ($eh !== '' && !hash_equals($eh, hash('sha256', $mail . '|yazar'))) return ['eposta_yanlis' => true];
            $sf = (string)($ye['sifre'] ?? '');
            if ($sf !== '' && !hash_equals($sf, trim($sifre))) return ['sifre_yanlis' => true];
            return [$i];
        }
    }
    return null;
}
/* 2 RET -> yazar artık düzenleyemez (makale yayında kalır) */
function yazar_kilitli(array $e): bool {
    $ret = 0;
    foreach (tg_dizi($e['hakemler'] ?? null) as $h) { if (is_array($h) && (string)($h['karar'] ?? '') === 'ret') $ret++; }
    return $ret >= 2;
}
/* Sıradaki bc.###### kimliğini üret */
function sonraki_bcid(array $y): string {
    if (function_exists('tg_sonraki_kod')) return tg_sonraki_kod($y);
    $mx = 0;
    foreach ($y as $e) { if (preg_match('/[a-z0-9]+\.(\d+)/i', (string)($e['bcid'] ?? ''), $mm)) $mx = max($mx, (int)$mm[1]); }
    return 'bc.' . str_pad((string)($mx + 1), 6, '0', STR_PAD_LEFT);
}
/* Yazar dizesi: "Dr. A, Dr. B ve Doç. Dr. C" */
function yazar_dizesi(array $yazarlar): string {
    $ad = [];
    foreach ($yazarlar as $ya) {
        if (!is_array($ya)) continue;
        $u = trim((string)($ya['unvan'] ?? '')); $a = trim((string)($ya['ad'] ?? ''));
        if ($a === '') continue;
        $ad[] = ($u !== '' ? $u . ' ' : '') . $a;
    }
    if (!$ad) return (string)tg_ayar('varsayilan_yazar', '');
    if (count($ad) === 1) return $ad[0];
    $son = array_pop($ad);
    return implode(', ', $ad) . ' ve ' . $son;
}

/* YAZAR BAŞVURUSU (herkese açık) - makale gönderme başvurusu */
if ($yol === '/yazar-basvuru' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    /* =================================================================
       GÖNDERİM HESAP İSTER

       Kurul kararı, 15 Ağustos 2026: "çalışma gönder kısmı kişiyi kayıt
       sayfasına yöneltmeli ki yazar kısımları boş olmamış olur."

       KURAL YALNIZ SAYFADA UYGULANAMAZ. basvuru.php "önce hesabınızı
       açın" diyorsa ve uç kimliksiz gönderimi kabul ediyorsa, sistem
       uygulamadığı bir kuralı duyuruyor demektir — bu oturumda on altı
       kez görülen kusur. Denetim burada.

       YENİ BİR ENGEL DEĞİL, ADIMIN YER DEĞİŞTİRMESİDİR: gönderimden
       sonra kişiye zaten hesap açılıyordu. Artık form TAM METNİ de
       alıyor ve tam metin sahibi olan bir kayıttır: yazarın geri dönüp
       düzeltmesi, kararın ona ulaşması ve ortak yazar davetlerinin
       onun adına çıkması sahiplik ister.

       Betiksiz yolda da doğru davranır: cikti() 'bicim=form' gördüğünde
       303 ile sayfaya döner ve iletiyi çerezle taşır, yani kişi ham
       JSON değil bir cümle görür. */
    $bvBen = hs_oturum();
    if ($bvBen === null) {
        cikti(['ok' => false, 'hata' => tg_c(
            'Çalışma göndermek için önce hesabınızı açmanız gerekiyor. Yazdıklarınız tarayıcınızda duruyor; hesabınızı açıp bu sayfaya döndüğünüzde olduğu gibi geri gelecek.',
            'You need to open an account before submitting a work. What you have written is kept in your browser and will come back unchanged when you return to this page with an account.')], 401);
    }
    /* HIZ SINIRI HESABA GÖRE TUTULUR, ORTAK İNTERNET ADRESİNE GÖRE DEĞİL.
       ÖLÇÜLEN KUSUR — 3 Ekim 2026. Sayaç yalnız IP'ye bağlıydı, eşik 12'ydi,
       pencere iki saatti ve hatalı denemeler de sayılıyordu. Aynı kurum
       ağından (ortak IP) birkaç hoca denediğinde HEPSİ iki saat kilitleniyordu;
       gönderim artık hesap istediği için sınırın hesaba bağlanması yeterli
       ve adildir. Hesap + IP çifti anahtar olur: bir kişinin hatası
       başkasını kilitlemez, kimliksiz istekler zaten yukarıda 401 alır.
       Eşik 12'den 30'a çıktı: form çok adımlıdır ve düzeltme
       denemeleri de sayılır. */
    $ipk = 'basvuru:' . substr(hash('sha256', mb_strtolower((string)($bvBen['eposta'] ?? ''), 'UTF-8') . '|' . ip_al()), 0, 16);
    if (kotu_say($ipk) > 30) cikti(['ok' => false, 'hata' => 'Çok fazla başvuru denemesi. Lütfen biraz sonra tekrar deneyin.'], 429);
    $temiz = fn($s, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)$s)), 0, $n);
    $bUnvan = $temiz($g['unvan'] ?? '', 60);
    $bAd = $temiz($g['ad'] ?? '', 120);
    $bEposta = mb_strtolower(trim((string)($g['eposta'] ?? '')), 'UTF-8');
    $bKurum = $temiz($g['kurum'] ?? '', 220);
    $bOrcid = $temiz($g['orcid'] ?? '', 60);
    $bScopus = $temiz($g['scopus'] ?? '', 200);
    $bWeb = $temiz($g['web'] ?? '', 200);
    /* Çalışmanın dili: listeye karşı denetlenir, tanınmayan kod boşa
       düşer. Boş kalırsa arayüzün dili varsayılır; uydurma bir kod
       kayda yazılmaz. */
    $mDil = tg_dil_kodu($g['makale_dil'] ?? '');
    if ($mDil === '') $mDil = tg_dil_kodu(k_dil()) ?: 'tr';
    $mBaslik = $temiz($g['makale_baslik'] ?? '', 220);
    /* KÜNYE DİLİNDEKİ BAŞLIK. 15 Ağustos 2026 kurul kararıyla ZORUNLU
       oldu (denetimi aşağıda, tg_kunye_zorunlu ile). Sistemin sözü
       değişmedi: istenen şey başlık ve özettir, TAM METİN değil; kayıt
       hâlâ yazarın kendi dilindeki metindir. Zorunluluğun gerekçesi
       ayar.php'de: künyesiz çalışma dizinlerde ve başka dilden okurun
       atıf listesinde yok sayılıyordu. */
    $mBaslikEn = $temiz($g['makale_baslik_en'] ?? '', 220);
    $mOzet = $temiz($g['makale_ozet'] ?? '', 4000);
    /* ---- YAPILANDIRILMIŞ ÖZ (isteğe bağlı) ----
       ÖZET SUNUCUDA TÜRETİLİR. Tarayıcının birleştirdiği dizeye
       güvenilseydi, yapı ile özet birbirini tutmayan bir kayıt doğar ve
       hangisinin doğru olduğu sorusunun yanıtı olmazdı. Yapı verildiyse
       'ozet' ONDAN kurulur; verilmediyse gelen serbest metin geçerlidir.

       'ozet' KANONİK KALIR: PDF, OAI, döküm, site haritası ve atıf
       künyesi eskisi gibi yalnız onu okur. Yapıyı her tüketiciye ayrı
       ayrı taşımak, aynı olguyu altı yerde yazmak olurdu. */
    $mOzetYapi = tg_ozet_yapi_temizle($g['ozet_yapi'] ?? null);
    if ($mOzetYapi) $mOzet = mb_substr(tg_ozet_yapidan($mOzetYapi), 0, 4000);
    /* İKİNCİ DİLDEKİ ÖZET. Başlığın ikinci dildeki karşılığı baştan
       alınıyordu ama özetinki alınmıyordu: sayfa iki dilde künye
       vaat ediyor, kayıt tek dilde tutuyordu. Dizinler künyeyi
       başlıkla değil başlık+özetle alır. 15 Ağustos 2026'dan beri
       ZORUNLUdur; denetimi aşağıdadır. */
    $mOzetEn = $temiz($g['makale_ozet_en'] ?? '', 4000);
    /* GENİŞLETİLMİŞ ÖZET. Çalışmanın dili künye dilinden başkaysa
       istenir; gerekçesi ayar.php'de yazılıdır. Sınır kısa özetinkinden
       yüksek: yedi yüz elli kelimelik bir metin dört bin karaktere
       sığmaz ve sığmadığı yerden kesilirse yazarın son paragrafı
       sessizce düşerdi. */
    $mGenisEn = $temiz($g['makale_genis_ozet_en'] ?? '', 20000);
    /* ---- TAM METİN ----
       Kurul kararı, 15 Ağustos 2026: metin gönderim anında istenir.
       Gerekçesi ortak.php'deki adım listesinde yazılı.

       $temiz() BURADA KULLANILMAZ: o işlev bütün etiketleri söker ve
       metnin yapısını (başlıklar, çizelgeler, listeler) yok ederdi.
       Metin guvenli_html()'den geçer — izinli etiketler kalır, betik ve
       olay öznitelikleri elenir. Düzenleyici de temizliyor; ikisi de
       temizler, çünkü düzenleyici bir kolaylıktır, güvenlik sınırı
       değil.

       Üst sınır cömerttir: bir makale, gömülü çizelgelerle birlikte
       kolayca yüz binlerce karakter tutar. Sınırın işi taşmayı
       durdurmaktır, yazarı kısaltmak değil. */
    $mMetin    = guvenli_html(mb_substr((string)($g['makale_metin'] ?? ''), 0, 900000));
    $mKaynakca = guvenli_html(mb_substr((string)($g['makale_kaynakca'] ?? ''), 0, 120000));
    /* Gövdeye yapıştırılan özet ve kaynakça, ayrı kutularla aynıysa bir kez saklanır. */
    $mMetin = tg_metin_tekrar_ayikla($mMetin, [(string)($g['makale_ozet'] ?? ''), (string)($g['makale_ozet_en'] ?? '')], $mKaynakca);
    /* Bilim alanı: hakemin göreceği dizin listesi buna göre süzülür */
    $mAlan = preg_replace('/[^a-z]/', '', strtolower((string)($g['alan'] ?? '')));
    if (!in_array($mAlan, ['sag','fen','sos','ikt','egt','hkk','san'], true)) $mAlan = '';
    /* FORD tabanlı alan kodları: hakem eşleştirmesi bunlarla yapılır.
       En çok altı kod; bir çalışma altı alandan fazlasına aitse
       seçim yapılmamış demektir. */
    $mAlanlar = array_slice(al_kodlar(is_array($g['alanlar'] ?? null) ? $g['alanlar'] : [], true), 0, 6);
    $telif = !empty($g['telif_kabul']);
    /* Koşulların okunduğu beyanı da kayda girer: sihirbazın birinci
       adımında işaretlenir, betiksiz yolda tarayıcı required ile
       durdurur. Beyan saklanmazsa sorulmasının bir anlamı kalmaz. */
    $kosulOkundu = !empty($g['kosullar_okundu']);
    /* Hangi SÜRÜMÜ okuduğu da yazılır: koşullar değişince "zaten
       okumuştu" diyemeyelim diye. Sürüm sayfadan gelir ve metnin
       kendisinden üretilir (basvuru.php, $kosulSurum). */
    $kosulSurum = $temiz($g['kosul_surum'] ?? '', 40);
    /* Bilimsel dürüstlük dosyaları ve beyanları */
    $intOran = (float)($g['intihal_oran'] ?? -1);
    $intArac = $temiz($g['intihal_arac'] ?? '', 60);
    $intTekKaynak = (float)($g['intihal_tek_kaynak'] ?? -1);
    $intLink = $temiz($g['intihal_link'] ?? '', 400);
    $yzKullanim = $temiz($g['yz_kullanim'] ?? '', 40);      /* yok | duzenleme | icerik | analiz */
    $yzAciklama = $temiz($g['yz_aciklama'] ?? '', 2000);
    $yzEtik = !empty($g['yz_etik_kabul']);
    /* ---- BEYANLAR (kurul kararı, 15 Ağustos 2026) ----
       Çıkar çatışması, başka yerde değerlendirilmeme ve fon; üçü de
       Kutadgu'da HİÇ YOKTU. Gerekçeleri basvuru.php'de yazılı. Hepsi
       çalışmayla birlikte yayımlanır — editör notu hariç, o yalnız
       kararı verecek editöre gider ve hiçbir yerde basılmaz. */
    $ccDurum = preg_replace('/[^a-z]/', '', strtolower((string)($g['cikar_catismasi'] ?? '')));
    if (!in_array($ccDurum, ['yok', 'var'], true)) $ccDurum = '';
    $ccAciklama = $temiz($g['cikar_aciklama'] ?? '', 2000);
    $tekGonderim = !empty($g['tek_gonderim']);
    $fonDurum = preg_replace('/[^a-z]/', '', strtolower((string)($g['fon_durum'] ?? '')));
    if (!in_array($fonDurum, ['yok', 'var'], true)) $fonDurum = '';
    $fonKaynak = $temiz($g['fon_kaynak'] ?? '', 400);
    $editorNotu = $temiz($g['editor_notu'] ?? '', 4000);
    /* YAZAR LİSTESİ TAM MI. Gerekçesi basvuru.php'de; özeti: eksik
       bırakılan yazar, kendisine gidecek bildirimi hiç almaz ve hayalet
       yazarlık tam orada doğar. Beyan kayda geçer. */
    $yazarTam = preg_replace('/[^a-z]/', '', strtolower((string)($g['yazar_tam'] ?? '')));
    if (!in_array($yazarTam, ['hepsi', 'tek'], true)) $yazarTam = '';
    /* Etik kurul: gerekli mi, belge bağlantısı var mı */
    $etikDurum = preg_replace('/[^a-z]/', '', strtolower((string)($g['etik_durum'] ?? '')));
    if (!in_array($etikDurum, ['gerekli', 'gereksiz'], true)) $etikDurum = '';
    /* ETİK KURUL BEYANI ÜÇ METİNDİR, BİR DOSYA DEĞİL (kurul kararı,
       15 Ağustos 2026). Gerekçe basvuru.php'de yazılı; özeti: sistem
       taranmış bir belgeyi doğrulayamaz, kurul adı + tarih + numara ise
       veren kurula sorularak doğrudan denetlenebilir ve karar metinleri
       kişisel veri taşır. Belgenin herkese açık bir adresi varsa yine
       kabul edilir; zorunlu olan üç metindir. */
    $etikBelge = $temiz($g['etik_belge'] ?? '', 500);
    $etikNo    = $temiz($g['etik_no'] ?? '', 120);
    $etikKurul = $temiz($g['etik_kurul'] ?? '', 220);
    $etikTarih = $temiz($g['etik_tarih'] ?? '', 30);
    /* SAYFADAKİ KURAL UÇTA DA UYGULANIR. Yalnız sayfada uygulanan bir
       kural, betiği kapalı olan için hiç yoktur; duyurulan ama
       uygulanmayan kural bu oturumda on beş kez görüldü. */
    if ($etikDurum === 'gerekli' && ($etikKurul === '' || $etikTarih === '' || $etikNo === '')) {
        cikti(['ok' => false, 'hata' => tg_c(
            'Etik kurul izni gerekli dendiğinde kurulun adı, karar tarihi ve karar numarası zorunludur.',
            'When ethics approval is stated to be required, the committee name, the decision date and the decision number are required.')], 400);
    }
    /* Veri ve kod erişilebilirliği: tekrarlanabilirliğin ön koşulu */
    $veriBeyan = preg_replace('/[^a-z]/', '', strtolower((string)($g['veri_beyan'] ?? '')));
    if (!in_array($veriBeyan, ['acik', 'istek', 'kisitli', 'yok'], true)) $veriBeyan = '';
    $veriUrl     = $temiz($g['veri_url'] ?? '', 500);
    $veriGerekce = $temiz($g['veri_gerekce'] ?? '', 800);
    $sekilLink = $temiz($g['sekil_link'] ?? '', 500);
    $veriLink = $temiz($g['veri_link'] ?? '', 500);
    $analizArac = $temiz($g['analiz_arac'] ?? '', 200);
    $analizKod = $temiz($g['analiz_kod'] ?? '', 500);
    $hakemOneri = [];
    $ho = $g['hakem_oneri'] ?? [];
    if (is_string($ho)) $ho = json_decode($ho, true);
    if (is_array($ho)) {
        foreach ($ho as $hh) {
            if (!is_array($hh)) continue;
            $had = $temiz($hh['ad'] ?? '', 120); if ($had === '') continue;
            $hakemOneri[] = ['ad' => $had, 'kurum' => $temiz($hh['kurum'] ?? '', 220),
                             'eposta' => mb_strtolower(trim((string)($hh['eposta'] ?? '')), 'UTF-8'),
                             'alan' => $temiz($hh['alan'] ?? '', 160)];
            if (count($hakemOneri) >= 6) break;
        }
    }
    $yazarlar = [];
    $yl = $g['yazarlar'] ?? [];
    if (is_string($yl)) $yl = json_decode($yl, true);
    if (is_array($yl)) {
        foreach ($yl as $ya) {
            if (!is_array($ya)) continue;
            $ad = $temiz($ya['ad'] ?? '', 120); if ($ad === '') continue;
            /* Kefiller: unvanı yeterli olmayan yazar için iki doktoralı.
               Burada yalnızca okunur; denetimi aşağıda yapılır. */
            $kf = $ya['kefiller'] ?? [];
            if (is_string($kf)) $kf = json_decode($kf, true);
            $kefiller = [];
            if (is_array($kf)) {
                foreach ($kf as $kk) {
                    if (!is_array($kk)) continue;
                    $kad = $temiz($kk['ad'] ?? '', 120); if ($kad === '') continue;
                    $kefiller[] = [
                        'ad'     => $kad,
                        'unvan'  => $temiz($kk['unvan'] ?? '', 60),
                        'kurum'  => $temiz($kk['kurum'] ?? '', 220),
                        'orcid'  => orcid_temiz((string)($kk['orcid'] ?? '')),
                        'eposta' => mb_strtolower(trim((string)($kk['eposta'] ?? '')), 'UTF-8'),
                        'ilgi'   => ((string)($kk['ilgi'] ?? '') === 'bagimsiz') ? 'bagimsiz' : 'ortak_yazar',
                        'durum'  => 'bekliyor',
                    ];
                    if (count($kefiller) >= 4) break;
                }
            }
            /* ORTAK YAZARIN E-POSTASI. Kurul kararı, 15 Ağustos 2026:
               eklenen her yazara yazarlığı BİLDİRİLİR; kayıtlı değilse
               davet bağlantısıyla. Adresi olmadan bildirilemez.
               Geçersiz adres sessizce boş bırakılmaz, reddedilir:
               sessizce düşen bir adres, hiç gönderilmemiş bir bildirim
               demektir ve kimse bunu fark etmez. */
            $yEposta = hs_eposta_anahtar((string)($ya['eposta'] ?? ''));
            if ($yEposta === '' || !filter_var($yEposta, FILTER_VALIDATE_EMAIL)) {
                cikti(['ok' => false, 'hata' => tg_c(
                    $ad . ' için geçerli bir e-posta adresi gerekir: yazarlığını ona bildirebilmemiz için.',
                    $ad . ' needs a valid e mail address, so that we can tell them of their authorship.')], 400);
            }
            $yazarlar[] = ['unvan' => $temiz($ya['unvan'] ?? '', 60), 'ad' => $ad, 'kurum' => $temiz($ya['kurum'] ?? '', 220),
                           'orcid' => (string)($ya['orcid'] ?? ''), 'scopus' => (string)($ya['scopus'] ?? ''),
                           'eposta' => $yEposta, 'bildirim' => '',
                           'kefiller' => $kefiller];
            if (count($yazarlar) >= 14) break;
        }
    }
    /* Başvuranın kendi kefilleri (unvanı yoksa) */
    $bKefiller = [];
    $bk = $g['kefiller'] ?? [];
    if (is_string($bk)) $bk = json_decode($bk, true);
    if (is_array($bk)) {
        foreach ($bk as $kk) {
            if (!is_array($kk)) continue;
            $kad = $temiz($kk['ad'] ?? '', 120); if ($kad === '') continue;
            $bKefiller[] = [
                'ad'     => $kad,
                'unvan'  => $temiz($kk['unvan'] ?? '', 60),
                'kurum'  => $temiz($kk['kurum'] ?? '', 220),
                'orcid'  => orcid_temiz((string)($kk['orcid'] ?? '')),
                'eposta' => mb_strtolower(trim((string)($kk['eposta'] ?? '')), 'UTF-8'),
                'ilgi'   => ((string)($kk['ilgi'] ?? '') === 'bagimsiz') ? 'bagimsiz' : 'ortak_yazar',
                'durum'  => 'bekliyor',
            ];
            if (count($bKefiller) >= 4) break;
        }
    }
    /* Başvuran her zaman ilk yazar */
    /* ÇELİŞKİ DENETİMİ: "tek yazar benim" deyip ortak yazar eklemiş
       olmak bir çelişkidir. Sayfa da söylüyor; uç da söylemeli, yoksa
       kural betiği kapalı olan için hiç yoktur. Denetim başvuran
       listenin başına eklenmeden ÖNCE yapılır: o eklendikten sonra
       liste hiçbir zaman boş olmaz ve ölçüm anlamını yitirir. */
    if ($yazarTam === 'tek' && count($yazarlar) > 0) {
        cikti(['ok' => false, 'hata' => tg_c(
            '"Tek yazar benim" dediniz ama ortak yazar eklediniz. İkisinden biri doğru olabilir.',
            'You said you are the only author but you have added co authors. Only one of the two can be true.')], 400);
    }
    array_unshift($yazarlar, ['unvan' => $bUnvan, 'ad' => $bAd, 'kurum' => $bKurum, 'orcid' => $bOrcid, 'scopus' => $bScopus, 'kefiller' => $bKefiller]);
    if ($bAd === '' || !filter_var($bEposta, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'Adınızı ve geçerli bir e-posta adresi girin.'], 400);
    if ($mBaslik === '') cikti(['ok' => false, 'hata' => 'Makale başlığı gerekli.'], 400);
    /* İLETİ SAYFANIN SÖYLEDİĞİYLE AYNI ŞEYİ SÖYLEMELİ. Burada hâlâ
       "telif haklarının DEVRİNİ kabul etmelisiniz" yazıyordu; oysa
       ikinci değişmez ilke telif hakkının yazarda KALDIĞINI söyler ve
       sayfadaki kutunun metni de öyle. Alan adı (telif_kabul) eski
       kayıtlar için korunur, ileti düzeltildi. */
    if (!$telif) cikti(['ok' => false, 'hata' => 'Yayımlama ve arşivleme iznini vermelisiniz.'], 400);
    /* KOŞULLARIN OKUNDUĞU BEYANI SUNUCUDA DA ARANIR. Tarayıcıda kutu
       required'dır, ama tarayıcıya güvenmek kapı değildir: beyan
       gelmeden kayıt düşerse kutu bir süstür. */
    if (!$kosulOkundu) cikti(['ok' => false, 'hata' => 'Gönderim koşullarını okuduğunuzu onaylayın.'], 400);
    /* ---- BENZERLİK (İNTİHAL) RAPORU ----
       KURUL KARARI, 13 Ağustos 2026: rapor İSTENMEZ. Gerekçe
       ayar.php'de yazılıdır; özeti şudur: sistem bu eşiği ölçemiyordu,
       yazarın yazdığı sayıyı denetlemeden kayda geçiriyordu, ve rapor
       paralı olduğu için kapıyı paraya bağlıyordu.

       DENETİM SİLİNMEDİ, KOŞULA BAĞLANDI. Şart geri açılırsa aşağıdaki
       beş denetim aynen işler.

       ŞART KAPALIYKEN DE VERİ KABUL EDİLİR: yazar dilerse raporunu
       ekler. Ama eklediği şey de denetlenir — çünkü SAYFADA GÖSTERİLEN
       bir sayı, kimsenin bakmadığı bir sayı değildir. Yüzde 300
       benzerlik yazan bir kayıt, kaydın kendisini güvenilmez yapar.

       ESKİDEN 15 ve 5 SAYILARI BURAYA ELLE YAZILIYDI; ayar.php'de de
       yazılıydı. İkisi ayrıldığı gün sayfa bir eşiği, uç başka bir
       eşiği uygular ve hangisinin doğru olduğu belirsizleşirdi. */
    $benzUst    = (float)tg_ayar('benzerlik_ust', 15);
    $benzTekUst = (float)tg_ayar('benzerlik_tek_ust', 5);
    $yuzde = fn(float $x): string => rtrim(rtrim(number_format($x, 1, ',', ''), '0'), ',');
    if (tg_benzerlik_sarti()) {
        if ($intArac === '') cikti(['ok' => false, 'hata' => 'İntihal raporunun hangi araçla alındığını belirtin (iThenticate, Turnitin, intihal.net vb.).'], 400);
        if ($intOran < 0 || $intOran > 100) cikti(['ok' => false, 'hata' => 'Genel benzerlik oranını (%) girin.'], 400);
        if ($intOran > $benzUst) cikti(['ok' => false, 'hata' => 'Genel benzerlik oranı en çok %' . $yuzde($benzUst) . ' olabilir. Raporunuzdaki oran: %' . $yuzde($intOran) . '.'], 400);
        if ($intTekKaynak < 0 || $intTekKaynak > 100) cikti(['ok' => false, 'hata' => 'Tek bir kaynaktan gelen en yüksek benzerlik oranını (%) girin.'], 400);
        if ($intTekKaynak > $benzTekUst) cikti(['ok' => false, 'hata' => 'Benzerliğin tamamı tek bir kaynaktan olamaz; tek kaynaktan gelen pay en çok %' . $yuzde($benzTekUst) . ' olabilir. Bildirdiğiniz pay: %' . $yuzde($intTekKaynak) . '.'], 400);
        if ($intTekKaynak > $intOran) cikti(['ok' => false, 'hata' => 'Tek kaynaktan gelen pay, genel benzerlik oranından büyük olamaz.'], 400);
        if ($intLink === '') cikti(['ok' => false, 'hata' => 'İntihal raporunun erişilebilir bağlantısını (paylaşım linki) ekleyin.'], 400);
    } else {
        /* İsteğe bağlı ama tutarlı olmalı. Verilmeyen alan -1 gelir ve
           sessizce düşürülür; verilen alan ölçüsüz olamaz. */
        if ($intOran > 100) cikti(['ok' => false, 'hata' => 'Genel benzerlik oranı yüzde olarak, 0 ile 100 arasında yazılır.'], 400);
        if ($intTekKaynak > 100) cikti(['ok' => false, 'hata' => 'Tek kaynaktan gelen pay yüzde olarak, 0 ile 100 arasında yazılır.'], 400);
        if ($intTekKaynak >= 0 && $intOran >= 0 && $intTekKaynak > $intOran)
            cikti(['ok' => false, 'hata' => 'Tek kaynaktan gelen pay, genel benzerlik oranından büyük olamaz.'], 400);
    }
    /* Yapay zekâ beyanı */
    if (!in_array($yzKullanim, ['yok', 'duzenleme', 'icerik', 'analiz'], true)) cikti(['ok' => false, 'hata' => 'Yapay zekâ kullanımına ilişkin beyanı seçin.'], 400);
    if ($yzKullanim !== 'yok' && mb_strlen($yzAciklama, 'UTF-8') < 20) cikti(['ok' => false, 'hata' => 'Yapay zekâ kullandıysanız kapsamını en az bir cümleyle açıklayın.'], 400);
    /* ETİK ONAYI YALNIZCA KULLANIM BEYAN EDİLDİĞİNDE ARANIR.
       Onayın cümlesi "yapay zekâ KULLANIMININ ilkeler çerçevesinde
       kaldığını beyan ederim" der; kullanmadığını söyleyen birinden,
       olmamış bir kullanımın niteliğini beyan etmesi istenemez.

       Bu satır ayrıca hiç işlemiyordu: sayfa 'yz_etik_kabul' alanını
       kutuya bakmadan her zaman true gönderiyordu. Yani kural
       duyuruluyor ama korunmuyordu — bu depoda onuncu kez aynı kusur.
       Sayfa artık gerçek değeri gönderiyor ve bu denetim gerçekten
       işliyor: kullanım beyan edildiyse onay ZORUNLUDUR. */
    if ($yzKullanim !== 'yok' && !$yzEtik) cikti(['ok' => false, 'hata' => 'YÖK Yapay Zekâ Kullanımına Dair Etik Rehber ilkelerine uyduğunuzu onaylamalısınız.'], 400);
    if ($yzKullanim === 'yok') { $yzEtik = false; $yzAciklama = ''; }
    /* ---- GENİŞLETİLMİŞ ÖZET (kurul kararı, 14 Ağustos 2026) ----
       Kural SUNUCUDA da işler. Sayfadaki sayaç bir kolaylıktır; betiği
       kapalı bir tarayıcıdan ya da doğrudan uca gönderilen bir istekten
       geçen kayıt, sayfanın hiç görmediği kayıttır. Bu sistemde
       "sayfada duruyor ama uçta yok" kusuru on kez ölçüldü.

       İSTENMİYORSA TEMİZLENİR: çalışması künye dilinde olan birinden
       gelen bir genişletilmiş özet, istenmemiş bir alandır ve kayda
       yazılmaz — yazılsaydı, sayfada hiç görünmeyen bir metin kayıtta
       durur ve kimse onu bir daha okumazdı. */
    /* KISA KÜNYE ZORUNLUYSA UÇ DA ARAR.
       Kurul kararı, 15 Ağustos 2026: başlık ve özet künye dilinde de
       zorunlu. Kural yalnız sayfada uygulansaydı, betiği kapalı olan
       için hiç var olmazdı; ayrıca uç, sayfanın uyguladığı kuralı
       tanımayan bir kayıt üretirdi. Çalışmanın dili zaten künye
       diliyse istenmez — aynı metni iki kez istemek olurdu. */
    /* TAM METİN VE KAYNAKÇA ARANIR.
       Eşik sayfayla AYNI kaynaktan gelir (ayar.php); iki yerde iki
       ayrı sayı olsaydı tarayıcı geçirir, sunucu reddeder ve kişi
       nedenini hiçbir yerde göremezdi. Kelime sayısı etiketler
       atıldıktan sonra sayılır: <p> sayısı bir ölçü değildir. */
    $mMetinAz  = (int)tg_ayar('metin_en_az_kelime', 800);
    $mMetinKel = tg_kelime_say(trim(preg_replace('/\s+/u', ' ',
                     html_entity_decode(strip_tags($mMetin), ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    if ($mMetinKel < $mMetinAz) {
        cikti(['ok' => false, 'hata' => tg_cd(
            'Çalışmanın tam metni gerekir: en az %1 kelime, şu an %2. Word belgenizin tamamını yapıştırabilirsiniz.',
            'The full text of the work is required: at least %1 words, currently %2. You may paste the whole of your Word document.',
            null, (string)$mMetinAz, (string)$mMetinKel)], 400);
    }
    if (trim(strip_tags($mKaynakca)) === '') {
        cikti(['ok' => false, 'hata' => tg_c(
            'Kaynakça gerekir. Kaynağı olmayan bir çalışma yayımlanmaz.',
            'A reference list is required. A work without sources is not published.')], 400);
    }
    if (tg_kunye_zorunlu() && tg_kunye_dili() !== '' && tg_dil_kodu($mDil) !== tg_kunye_dili()) {
        if ($mBaslikEn === '') {
            cikti(['ok' => false, 'hata' => tg_cd(
                'Başlığın %1 dilindeki karşılığı zorunludur: dizinler ve başka dilden okurun atıf künyesi onu okur.',
                'The title in %1 is required: indexes and the citation entry seen by readers in other languages read it.',
                null, tg_kunye_dil_adi())], 400);
        }
        if ($mOzetEn === '') {
            cikti(['ok' => false, 'hata' => tg_cd(
                'Özetin %1 dilindeki karşılığı zorunludur: dizinler künyeyi başlıkla değil, başlık ve özetle alır.',
                'The abstract in %1 is required: indexes take the citation entry from the title together with the abstract, not from the title alone.',
                null, tg_kunye_dil_adi())], 400);
        }
    }
    if (tg_genis_ozet_gerek($mDil)) {
        $gAyar = tg_genis_ozet_ayar();
        $gKel  = tg_kelime_say($mGenisEn);
        if ($gKel < $gAyar['en_az_kelime']) {
            cikti(['ok' => false, 'hata' => tg_cd(
                'Çalışmanız %1 dilinde olmadığı için %1 dilinde genişletilmiş bir özet gerekir: en az %2 kelime, şu an %3.',
                'Because your work is not in %1, an extended abstract in %1 is required: at least %2 words, currently %3.',
                null, tg_kunye_dil_adi(), (string)$gAyar['en_az_kelime'], (string)$gKel)], 400);
        }
    } else {
        $mGenisEn = '';
    }
    if ($veriBeyan === '') cikti(['ok' => false, 'hata' => 'Veri ve kodun erişilebilirliğini belirtmelisiniz. Paylaşılamıyor olması bir kusur değildir; belirtilmemesi kusurdur.'], 400);
    /* Sayfadaki kural uçta da uygulanır; yalnız sayfada uygulanan bir
       kural, betiği kapalı olan için hiç yoktur. */
    if ($ccDurum === '') cikti(['ok' => false, 'hata' => tg_c(
        'Çıkar çatışması olup olmadığını belirtmelisiniz. "Yok" da bir beyandır ve o da yayımlanır.',
        'You must state whether there is a conflict of interest. "None" is also a declaration, and it too is published.')], 400);
    if ($ccDurum === 'var' && $ccAciklama === '') cikti(['ok' => false, 'hata' => tg_c(
        'Çıkar çatışması var dediniz; neyin, kiminle ve nasıl bir bağı olduğunu yazmalısınız.',
        'You stated there is a conflict of interest; you must write what the tie is, with whom, and of what kind.')], 400);
    if (!$tekGonderim) cikti(['ok' => false, 'hata' => tg_c(
        'Çalışmanın başka bir yerde değerlendirilmediğini beyan etmelisiniz.',
        'You must declare that the work is not under assessment elsewhere.')], 400);
    if ($fonDurum === '') cikti(['ok' => false, 'hata' => tg_c(
        'Fon alınıp alınmadığını belirtmelisiniz.',
        'You must state whether funding was received.')], 400);
    if ($fonDurum === 'var' && $fonKaynak === '') cikti(['ok' => false, 'hata' => tg_c(
        'Fon aldınız dediniz; fon veren kuruluşu ve proje numarasını yazmalısınız.',
        'You stated that funding was received; you must write the funder and the project number.')], 400);
    if ($yazarTam === '') cikti(['ok' => false, 'hata' => tg_c(
        'Bütün yazarları ekleyip eklemediğinizi belirtmelisiniz. Eksik bırakılan bir yazar, yazarlığından hiç haberdar olmaz.',
        'You must state whether all authors have been added. An author left out never learns of their authorship.')], 400);
    if ($veriBeyan === 'acik' && $veriUrl === '') cikti(['ok' => false, 'hata' => 'Veri ya da kodun bulunduğu adresi girin.'], 400);
    if ($veriBeyan === 'kisitli' && mb_strlen($veriGerekce, 'UTF-8') < 15) cikti(['ok' => false, 'hata' => 'Verinin neden paylaşılamadığını yazın.'], 400);
    /* Her yazar için: en az Dr. unvanı + geçerli ORCID.
       Scopus Author ID isteğe bağlıdır: Scopus ticari bir veri tabanıdır ve
       birçok alanda araştırmacıların kimliği orada bulunmaz. Girilirse
       biçimi denetlenir, girilmezse başvuru yine de kabul edilir. */
    /* =================================================================
       DOKTORA ŞARTI (kurul kararı, 13 Ağustos 2026: KALDIRILDI)
       -----------------------------------------------------------------
       Kural artık unvana bakmaz. Aşağıdaki bütün denetim — unvan
       yeterliliği, destekleyen araştırmacı düzeni, ortak yazar/bağımsız
       ayrımı — YALNIZCA şart açıkken çalışır.

       KAPI KAPANMADI, YER DEĞİŞTİRDİ: çalışma gönderilir, editör
       /yonetim/basvuru-karar ucundan okur ve sisteme girip girmeyeceğine
       karar verir. Nitelik denetimi metne bakan birine geçti.

       ORCID DENETİMİ BU BLOĞUN DIŞINDADIR ve aşağıda her koşulda
       çalışmayı sürdürür: doktora bir yeterlik iddiası, ORCID ise bir
       kimliktir; açık hakemlikte adın doğru kişiye bağlanması
       vazgeçilemez.

       Şart geri açılırsa (ayar.php 'yazarlik_doktora_sarti' => true)
       eski düzenin tamamı olduğu gibi yeniden işler; hiçbir satır
       silinmedi.
       ================================================================= */
    $doktoraSarti = function_exists('tg_yazarlik_doktora_sarti') ? tg_yazarlik_doktora_sarti() : true;
    $kefilAcik = $doktoraSarti && (bool)tg_ayar('kefil_acik', true);
    $kefilSay  = (int)tg_ayar('kefil_sayisi', 2);
    /* Unvanı yeterli olan yazarların adları: kefilin "ortak yazar" olup
       olmadığını denetlerken kullanılır. */
    $doktoraliYazar = []; $tumYazarAnahtar = [];
    foreach ($yazarlar as $ya) {
        $a = tg_ad_anahtar((string)($ya['ad'] ?? ''));
        if ($a === '') continue;
        $tumYazarAnahtar[$a] = true;
        if (unvan_yeterli((string)($ya['unvan'] ?? ''))) $doktoraliYazar[$a] = true;
    }
    $unvansizSay = 0;
    foreach ($yazarlar as $ya) { if (!unvan_yeterli((string)($ya['unvan'] ?? ''))) $unvansizSay++; }
    /* Kefil düzeni ancak yanında doktoralı biri varken anlamlıdır:
       unvansız bir kişi tek başına çalışma gönderemez. */
    if ($kefilAcik && $unvansizSay > 0 && count($doktoraliYazar) === 0) {
        cikti(['ok' => false, 'hata' => 'Unvanı olmayan bir araştırmacı tek başına çalışma gönderemez. Çalışmada en az bir doktoralı ortak yazar bulunmalıdır.'], 400);
    }

    foreach ($yazarlar as $i => $ya) {
        $kim = (($ya['ad'] ?? '') !== '') ? (string)$ya['ad'] : ($i === 0 ? 'başvuran' : ($i + 1) . '. yazar');
        /* ÖLÇÜLEN KUSUR VE DÜZELTMESİ. Buraya önce "şart kalktıysa
           döngüyü atla" (continue) yazıldı; yazarlik-kapi §ORCID onu
           aynı koşuda yakaladı: ORCID denetimi de bu döngünün İÇİNDE,
           unvan bloğunun ALTINDA duruyor ve atlanan tur onu da atlıyordu.
           Yani doktora şartını kaldırırken ORCID zorunluluğu sessizce
           düşüyordu — bir şartı kaldırırken yanındakini de düşürmek,
           gevşetmelerin en sık yaptığı hatadır.

           Doğrusu: atlanacak olan TUR değil, yalnızca unvan ve
           destekleyen araştırmacı BLOĞUdur. ORCID her koşulda aranır. */
        if ($doktoraSarti && !unvan_yeterli((string)$ya['unvan'])) {
            if (!$kefilAcik) cikti(['ok' => false, 'hata' => 'Tüm yazarlar en az "Dr." unvanına sahip olmalıdır. Unvanı eksik ya da yetersiz: ' . $kim], 400);
            /* ---- Kefil denetimi ----
               Biri metni bilen ortak yazar, biri çalışmayla bağı olmayan
               bağımsız bir doktoralı. İkisi de adıyla ve ORCID'iyle
               yazılır; onay bağlantısı e-postalarına gider. */
            $kefiller = is_array($ya['kefiller'] ?? null) ? $ya['kefiller'] : [];
            if (count($kefiller) !== $kefilSay) {
                cikti(['ok' => false, 'hata' => $kim . ' için ' . $kefilSay . ' ' . tg_destek_ad() . ' gerekir: biri bu çalışmanın doktoralı ortak yazarı, biri çalışmayla bağı olmayan doktoralı bir araştırmacı.'], 400);
            }
            $ortakVar = false; $bagimsizVar = false; $kefilAnahtar = [];
            $yazarAnahtar = tg_ad_anahtar((string)$ya['ad']);
            foreach ($kefiller as $kj => $kk) {
                $kad = (string)($kk['ad'] ?? '');
                $ka  = tg_ad_anahtar($kad);
                if (!unvan_yeterli((string)($kk['unvan'] ?? ''))) {
                    cikti(['ok' => false, 'hata' => tg_destek_ad_bas() . ' en az "Dr." unvanına sahip olmalıdır. Unvanı yetersiz: ' . $kad], 400);
                }
                if ((string)($kk['orcid'] ?? '') === '') {
                    cikti(['ok' => false, 'hata' => tg_destek_ad_bas(false, true) . 'ın her biri için geçerli bir ORCID zorunludur. Eksik ya da hatalı: ' . $kad], 400);
                }
                if (!filter_var((string)($kk['eposta'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                    cikti(['ok' => false, 'hata' => tg_destek_ad_bas(false, true) . 'ın her biri için geçerli bir e-posta adresi gerekir; onay bağlantısı oraya gönderilir. Eksik ya da hatalı: ' . $kad], 400);
                }
                if ($ka !== '' && $ka === $yazarAnahtar) {
                    cikti(['ok' => false, 'hata' => 'Bir araştırmacı kendi çalışmasının destekleyeni olamaz: ' . $kad], 400);
                }
                if (isset($kefilAnahtar[$ka])) {
                    cikti(['ok' => false, 'hata' => tg_c('Aynı kişi iki kez ', 'The same person cannot be shown twice as a ') . tg_destek_ad() . tg_c(' gösterilemez: ', ': ') . $kad], 400);
                }
                $kefilAnahtar[$ka] = true;
                if ((string)($kk['ilgi'] ?? '') === 'ortak_yazar') {
                    if (!isset($doktoraliYazar[$ka])) {
                        cikti(['ok' => false, 'hata' => tg_destek_ad_bas() . ' olarak gösterilen ortak yazar, bu çalışmanın doktoralı yazarlarından biri olmalıdır. Yazar listesinde bulunamadı: ' . $kad], 400);
                    }
                    $ortakVar = true;
                } else {
                    if (isset($tumYazarAnahtar[$ka])) {
                        cikti(['ok' => false, 'hata' => 'Bağımsız ' . tg_destek_ad() . ', bu çalışmanın yazarlarından biri olamaz: ' . $kad], 400);
                    }
                    $bagimsizVar = true;
                }
                /* Onay anahtarı: her kefile kendi bağlantısı gider */
                $kefiller[$kj]['kod']   = bin2hex(random_bytes(16));
                $kefiller[$kj]['durum'] = 'bekliyor';
                $kefiller[$kj]['tarih'] = date('c');
            }
            if (!$ortakVar || !$bagimsizVar) {
                cikti(['ok' => false, 'hata' => $kim . ' için ' . tg_destek_ad(false, true) . 'dan biri bu çalışmanın doktoralı ortak yazarı, diğeri çalışmayla bağı olmayan bir doktoralı olmalıdır. İkisi de aynı türden olamaz.'], 400);
            }
            $yazarlar[$i]['kefiller'] = $kefiller;
            $yazarlar[$i]['unvansiz'] = true;
        } else {
            /* Unvanı yeterli olan yazarda destek kaydı taşınmaz; şart
               büsbütün kalkmışsa da taşınmaz, çünkü istenmemiştir. */
            unset($yazarlar[$i]['kefiller']);
        }
        $o = orcid_temiz((string)($ya['orcid'] ?? ''));
        if ($o === '') cikti(['ok' => false, 'hata' => 'Her yazar için geçerli bir ORCID zorunludur (0000-0000-0000-0000 biçiminde). Eksik ya da hatalı: ' . $kim], 400);
        $scHam = trim((string)($ya['scopus'] ?? ''));
        $sc = scopus_temiz($scHam);
        if ($scHam !== '' && $sc === '') cikti(['ok' => false, 'hata' => 'Scopus Author ID girildiyse geçerli olmalıdır (yalnızca rakam). Hatalı: ' . $kim], 400);
        $yazarlar[$i]['orcid'] = $o;
        $yazarlar[$i]['scopus'] = $sc;
    }
    $bOrcid = (string)$yazarlar[0]['orcid'];
    $bScopus = (string)$yazarlar[0]['scopus'];
    $b = oku_json('basvurular.json', []); if (!is_array($b)) $b = [];
    $kayit = [
        'id' => substr(hash('sha256', microtime() . mt_rand() . random_int(0, PHP_INT_MAX)), 0, 12),
        'durum' => 'bekliyor',
        'tarih' => date('c'),
        'basvuran' => ['unvan' => $bUnvan, 'ad' => $bAd, 'eposta' => $bEposta, 'kurum' => $bKurum, 'orcid' => $bOrcid, 'scopus' => $bScopus, 'web' => $bWeb],
        /* KAYDIN SAHİBİ. Başvuran alanları kişinin YAZDIĞIdır ve
           değiştirilebilir (kurumunu düzeltebilmeli); sahiplik ise
           OTURUMDAN gelir ve yazılamaz. İkisi ayrı tutulur: biri
           künyedir, öteki "bu kayda kim geri dönebilir" sorusunun
           yanıtı. */
        'hesap' => (string)($bvBen['eposta'] ?? ''),
        'yazarlar' => $yazarlar,
        'makale_dil' => $mDil,
        'makale_baslik' => $mBaslik,
        'makale_baslik_en' => $mBaslikEn,
        'makale_ozet_en' => $mOzetEn,
        'makale_genis_ozet_en' => $mGenisEn,
        'makale_ozet' => $mOzet,
        'makale_ozet_yapi' => $mOzetYapi,
        /* Tam metin başvurunun İÇİNDE durur; kabul edildiğinde
           çalışmaya olduğu gibi geçer (basvuru-karar). Reddedilirse de
           kayıtta kalır: neyin reddedildiği, ret gerekçesinin
           denetlenebilmesi için görülebilmelidir. */
        'makale_metin' => $mMetin,
        'makale_kaynakca' => $mKaynakca,
        'alan' => $mAlan,
        'alanlar' => $mAlanlar,
        'telif_kabul' => true,
        'kosullar_okundu' => $kosulOkundu,
        'kosul_surum' => $kosulSurum,
        'intihal' => ['arac' => $intArac, 'oran' => $intOran, 'tek_kaynak' => $intTekKaynak, 'link' => $intLink],
        /* Kayda GERÇEK değer yazılır. Burada da sabit 'true' vardı:
           kullanmadığını söyleyen bir yazarın kaydında bile "etik
           onayı verildi" yazıyordu. Bu beyan çalışmayla birlikte
           yayımlanıyor; yayımlanan bir kayıt, olmamış bir onayı
           taşıyamaz. */
        'yz' => ['kullanim' => $yzKullanim, 'aciklama' => $yzAciklama, 'etik_kabul' => $yzEtik],
        /* Yayımlanan beyanlar. */
        'beyan' => [
            'cikar'        => $ccDurum,
            'cikar_ack'    => $ccAciklama,
            'tek_gonderim' => $tekGonderim,
            'yazar_tam'    => $yazarTam,
            'fon'          => $fonDurum,
            'fon_kaynak'   => $fonKaynak,
        ],
        /* EDİTÖR NOTU AYRI DURUR ve 'beyan' içine konmaz: beyanlar
           yayımlanır, bu not yayımlanmaz. Aynı kabın içinde dursaydı
           bir gün beyanları basan bir döngü onu da basardı. */
        'editor_notu' => $editorNotu,
        /* ---- SAYILAR SORULMAZ, HESAPLANIR ----
           ScholarOne yazara "kaç şekil, kaç çizelge, kaç kelime" diye
           SORUYOR. Kutadgu metni elinde tuttuğu için sayabilir; sormak,
           bilinen bir şeyi kişiye iş olarak yüklemek ve yanlış
           yazılabilen bir sayıyı kayda geçirmek olurdu. */
        'olcu' => [
            'kelime'  => $mMetinKel,
            'sekil'   => preg_match_all('/<img\b/i', $mMetin),
            'cizelge' => preg_match_all('/<table\b/i', $mMetin),
        ],
        'ek' => ['sekil_link' => $sekilLink, 'veri_link' => $veriLink, 'analiz_arac' => $analizArac, 'analiz_kod' => $analizKod],
        'etik' => ['durum' => $etikDurum, 'kurul' => $etikKurul, 'tarih' => $etikTarih,
                   'belge' => $etikBelge, 'no' => $etikNo, 'onay' => false, 'onay_tarih' => ''],
        'veri' => ['beyan' => $veriBeyan, 'url' => $veriUrl, 'gerekce' => $veriGerekce],
        'hakem_oneri' => $hakemOneri,
        'not' => '',
        'yazi_id' => '',
    ];
    $b[] = $kayit;
    if (count($b) > 3000) $b = array_slice($b, -2000);
    yaz_json('basvurular.json', $b);

    /* Kefillere kendi onay bağlantıları gider. Kimsenin adı, haberi
       olmadan bir sorumluluğun altına yazılmaz; onay gelmeden çalışma
       yayına alınmaz. */
    $kefilSayisi = 0;
    $markaK = (string)tg_ayar('marka', 'Kutadgu');
    $kokK   = rtrim(tg_kok(), '/');
    foreach ($yazarlar as $ya) {
        foreach ((is_array($ya['kefiller'] ?? null) ? $ya['kefiller'] : []) as $kk) {
            if (!is_array($kk) || (string)($kk['kod'] ?? '') === '') continue;
            $kefilSayisi++;
            eposta_gonder((string)$kk['eposta'], $markaK . ' | Yazarlık desteği isteniyor',
                "Sayın " . (string)$kk['ad'] . ",\n\n"
                . (string)$ya['ad'] . " adlı araştırmacı, henüz doktora derecesine sahip olmadığı için "
                . "aşağıdaki çalışmada ancak iki doktoralı araştırmacının sorumluluk üstlenmesiyle yazar olarak yer alabilir. "
                . "Sizi " . tg_destek_ad() . " olarak gösterdi.\n\n"
                . "Çalışma: " . $mBaslik . "\n"
                . "Sizin sıfatınız: " . tg_kefil_ilgi_ad((string)$kk['ilgi']) . "\n\n"
                . tg_destek_ad_bas() . " olmak, bu araştırmacının çalışmaya gerçekten katkı verdiğini ve adının orada bulunmayı "
                . "hak ettiğini bildiğinizi söylemektir. Adınız, gerekçenizle birlikte çalışmanın sayfasında "
                . "kalıcı olarak görünür.\n\n"
                . "Onaylamak ya da reddetmek için:\n" . $kokK . "/kefil.php?k=" . (string)$kk['kod'] . "\n\n"
                . "Onayınız gelmeden çalışma yayına alınmaz. Bu istekten haberiniz yoksa, bağlantıdan "
                . "reddedebilirsiniz; reddiniz de gerekçesiyle kayda geçer.\n\n"
                . $markaK . "\n" . $kokK . "\n");
        }
    }

    /* =================================================================
       ORTAK YAZARA YAZARLIĞI BİLDİRİLİR

       Kurul kararı, 15 Ağustos 2026: "eklediği yazar sistemde yoksa o
       kişinin epostasını sisteme girer ve ona ilgili makalede yer
       aldığını söyleyen davet bağlantısı gider; ORCID ile girebilir
       sisteme ya da eposta ile."

       NEDEN HERKESE, YALNIZCA KAYITSIZA DEĞİL: adının bir çalışmada
       yazar olarak kullanıldığını öğrenmek, hesabı olanın da hakkıdır.
       Hayalet yazarlık ve armağan yazarlık, tam olarak "kişinin
       haberi olmaması" ile yaşar. İki durum arasındaki fark, mektubun
       İÇERİĞİDİR, gönderilip gönderilmemesi değil:
         - hesabı olan: bildirim + panel bağlantısı,
         - hesabı olmayan: aynı bildirim + hesap kurma daveti.

       DAVET HİÇBİR ROL TAŞIMAZ. hesap-davet ucundakinin aksine burada
       'roller' boş bırakılır: yazar olarak gösterilmek hakemlik ya da
       editörlük getirmez. Rol taşıyan bir davet, gönderim formunu bir
       yetki dağıtıcısına çevirirdi.

       PAROLASI OLAN HESABA DAVET ÇIKARILMAZ (aynı gerekçe orada
       yazılı: davet, parolalı bir hesabı ele geçirmenin en kısa yolu
       olurdu). O kişiye yalnız bildirim gider.

       Gönderim sonucu kayda yazılır: gitmeyen bir bildirim, sessizce
       gitmemiş sayılmaz — editör kimin haberdar edildiğini görür. */
    $bildirimSay = 0; $davetSay = 0;
    foreach ($yazarlar as $yi => $ya) {
        $yAd = (string)($ya['ad'] ?? '');
        $ye  = hs_eposta_anahtar((string)($ya['eposta'] ?? ''));
        if ($ye === '' || !filter_var($ye, FILTER_VALIDATE_EMAIL)) continue;
        /* Başvuranın kendisi ortak yazar satırında da geçiyorsa iki kez
           yazılmaz: aynı kişiye "sizi eklediler" demek anlamsızdır. */
        if (strcasecmp($ye, $bEposta) === 0) { $yazarlar[$yi]['bildirim'] = 'basvuran'; continue; }
        $vh = hs_bul($ye);
        $kayitli = ($vh !== null && trim((string)($vh['parola'] ?? '')) !== '');
        $govde = "Sayın " . $yAd . ",

"
               . $bAd . ", " . $markaK . " yayın sistemine bir çalışma gönderdi ve sizi bu çalışmanın "
               . "yazarlarından biri olarak gösterdi.

"
               . "Çalışma: " . $mBaslik . "
"
               . "Gönderen (sorumlu yazar): " . $bAd . "

";
        if ($kayitli) {
            $govde .= "Bu adreste zaten bir hesabınız var; çalışmayı hesabınızdan görebilirsiniz:
"
                    . $kokK . "/panel.php

";
        } else {
            $jetonY = bin2hex(random_bytes(32));
            $hedefY = $vh ?? ['eposta' => $ye, 'parola' => '', 'ad' => $yAd, 'unvan' => '', 'kurum' => '',
                              'roller' => [], 'kullanici' => '', 'katilim' => date('c'), 'giris' => []];
            if (trim((string)($hedefY['ad'] ?? '')) === '') $hedefY['ad'] = $yAd;
            if (trim((string)($hedefY['kurum'] ?? '')) === '') $hedefY['kurum'] = (string)($ya['kurum'] ?? '');
            if (trim((string)($hedefY['orcid'] ?? '')) === '') $hedefY['orcid'] = (string)($ya['orcid'] ?? '');
            $hedefY['davet'] = ['ozet' => hash('sha256', $jetonY), 'olusma' => date('c'),
                                'bitis' => date('c', time() + 30 * 86400),
                                'kuran' => $bAd . ' (yazar bildirimi)'];
            hs_kaydet($hedefY);
            $davetSay++;
            $govde .= "Bu adreste bir hesabınız yok. Aşağıdaki bağlantıdan hesabınızı kurabilirsiniz; "
                    . "ORCID'inizle ya da bu e-posta adresinizle girebilirsiniz:
"
                    . $kokK . "/hesap-kur.php?k=" . $jetonY . "
"
                    . "(Bağlantı otuz gün geçerlidir.)

"
                    . "Hesap açmak zorunda değilsiniz: açmasanız da çalışmadaki yazarlığınız durur. "
                    . "Hesap, çalışmanın durumunu görmenizi ve kendi sayfanızı düzenlemenizi sağlar.

";
        }
        $govde .= "BU ÇALIŞMADAN HABERİNİZ YOKSA, lütfen bize yazın: " . $kokK . "/iletisim.php
"
                . "Adının kullanıldığını bilmeyen bir yazarlık, yazarlık değildir; bildirdiğinizde "
                . "çalışma incelemeye alınır.

"
                . $markaK . "
" . $kokK . "
";
        /* İKİ AYRI ŞEY, İKİ AYRI ALAN: NE KARARLAŞTIRILDI ve GİTTİ Mİ.
           Önce tek alana yazılıyordu ve mektup gönderilemediğinde
           'gitmedi' yazıp kararı siliyordu; editör "bu kişiye davet mi
           bildirim mi çıkarılmıştı" sorusunu bir daha yanıtlayamıyordu.
           Posta yeniden denenecekse hangi mektubun deneneceği de
           bilinmez olurdu. */
        $gitti = eposta_gonder($ye, $markaK . ' | Bir çalışmada yazar olarak gösterildiniz', $govde);
        $bildirimSay += $gitti ? 1 : 0;
        $yazarlar[$yi]['bildirim']       = $kayitli ? 'bildirildi' : 'davet';
        $yazarlar[$yi]['bildirim_gitti'] = (bool)$gitti;
        $yazarlar[$yi]['bildirim_tarih'] = date('c');
    }
    /* Kayıt, mektuplar gönderildikten SONRA güncellenir: hangi yazara
       ne gittiği kayda geçmezse editör bunu hiçbir yerden göremez. */
    if ($yazarlar) {
        $b[count($b) - 1]['yazarlar'] = $yazarlar;
        yaz_json('basvurular.json', $b);
    }

    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'], "\xF0\x9F\x93\xA5 Yeni yazar basvurusu\n" . $bAd . "\nMakale: " . $mBaslik
            . ($kefilSayisi ? "\nYazarlik destegi bekleniyor: " . $kefilSayisi : '')
            . "\n\nInceleme: https://kutadgu.net/panel.php#yonetim", 4);
    }
    cikti(['ok' => true, 'kefil' => $kefilSayisi,
           'mesaj' => $kefilSayisi > 0
        ? 'Başvurunuz alındı. Unvanı olmayan yazar için gösterdiğiniz ' . $kefilSayisi . ' ' . tg_destek_ad() . 'ya onay bağlantısı gönderildi; onayları gelmeden çalışma yayına alınmaz. Sonuç e-posta ile bildirilecektir.'
        : 'Başvurunuz alındı. İncelendikten sonra e-posta ile bilgilendirileceksiniz.']);
}

/* =====================================================================
   ORTAK YAZAR ARAMA: YALNIZCA ORCID İLE
   ---------------------------------------------------------------------
   Kurul kararı, 15 Ağustos 2026: çalışmaya yazar eklerken kişi sistemde
   kayıtlıysa bilgileri gelsin; ORCID'iyle aranabilsin.

   NEDEN E-POSTAYLA ARAMA YOK. E-posta ile arayan bir uç, "bu adres
   burada kayıtlı mı" sorusunu herkese yanıtlayan bir makinedir; üyelik
   bilgisini kamuya açar ve adres listesi olan biri bütün kullanıcıları
   sayabilir. ORCID böyle değildir: on altı haneli, herkese açık ve
   tahmin edilemez bir numaradır; birinin ORCID'ini bilen kişi zaten
   onun kim olduğunu biliyordur. E-posta yine de İSTENİR, ama aramak
   için değil — yazarlığı kişiye bildirmek için.

   NE DÖNER: yalnızca zaten herkese açık olan alanlar (ad, unvan,
   kurum). E-POSTA DÖNMEZ; dönseydi, ORCID bilen herkes o kişinin
   adresini de almış olurdu.

   KENDİNİ DİZİNDEN ÇIKARMIŞ KİŞİ BULUNMAZ. 'dizin_gizli' işaretleyen
   biri "beni listelemeyin" demiştir; onu ORCID'le bulunabilir
   bırakmak, o isteği yalnızca bir ekranda uygulamak olurdu.

   HIZ SINIRI VAR: numara tahmin edilemez ama denenebilir.
   ===================================================================== */
if ($yol === '/yazar-ara' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'yazarara:' . substr(hash('sha256', ip_al()), 0, 16);
    /* kotu_say() sayacı kendisi artırır; ayrıca bir "ekle" çağrısı
       yoktur. İki kez çağırmak sınırı yarıya indirirdi. */
    if (kotu_say($ipk) > 60) cikti(['ok' => false, 'hata' => 'Çok fazla arama yapıldı. Lütfen sonra tekrar deneyin.'], 429);
    $id = orcid_temiz((string)($g['orcid'] ?? ''));
    if ($id === '') cikti(['ok' => false, 'hata' => 'Geçerli bir ORCID girin (0000-0000-0000-0000).'], 400);
    foreach (hs_oku() as $h) {
        if (!is_array($h) || !empty($h['dizin_gizli'])) continue;
        if (strcasecmp(trim((string)($h['orcid'] ?? '')), $id) !== 0) continue;
        $ad = trim((string)($h['ad'] ?? ''));
        if ($ad === '') continue;
        cikti(['ok' => true, 'bulundu' => true,
               'ad'    => $ad,
               'unvan' => hs_unvan_ad((string)($h['unvan'] ?? '')),
               'kurum' => (string)($h['kurum'] ?? ''),
               'yol'   => tg_kisi_yolu($ad)]);
    }
    /* Bulunamamak bir hata değildir: kişi sistemde yoktur ve gönderimden
       sonra ona davet gider. 404 dönmek, sayfayı hata gibi konuşturur. */
    cikti(['ok' => true, 'bulundu' => false]);
}

/* =====================================================================
   KEFİLLİK ONAYI
   ---------------------------------------------------------------------
   Kefil, kendisine gönderilen bağlantıdan onaylar ya da reddeder.
   Gerekçe her iki durumda da istenir ve çalışmanın sayfasında kalıcı
   olarak görünür: bir kişinin adının bir başkasının çalışmasında
   sorumluluk taşıması, gerekçesi yazılmadan geçilecek bir şey değildir.

   Bağlantı anahtarı tek başına yeterlidir; kefilin hesap açması
   istenmez. Anahtar 32 hanelik rastgele bir dizidir ve yalnızca
   e-postasına gider.
   ===================================================================== */
function kefil_bul(array &$b, string $kod): array {
    foreach ($b as $bi => $bs) {
        if (!is_array($bs)) continue;
        foreach ((is_array($bs['yazarlar'] ?? null) ? $bs['yazarlar'] : []) as $yi => $ya) {
            if (!is_array($ya)) continue;
            foreach ((is_array($ya['kefiller'] ?? null) ? $ya['kefiller'] : []) as $ki => $kk) {
                if (is_array($kk) && hash_equals((string)($kk['kod'] ?? ''), $kod)) {
                    return ['b' => $bi, 'y' => $yi, 'k' => $ki];
                }
            }
        }
    }
    cikti(['ok' => false, 'hata' => 'Bu bağlantı geçersiz ya da artık kullanılmıyor.'], 404);
}

if ($yol === '/kefil-bilgi' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($g['k'] ?? ''));
    if (strlen($kod) < 16) cikti(['ok' => false, 'hata' => 'Bağlantı eksik.'], 400);
    $b = oku_json('basvurular.json', []); if (!is_array($b)) $b = [];
    $p = kefil_bul($b, $kod);
    $bs = $b[$p['b']]; $ya = $bs['yazarlar'][$p['y']]; $kk = $ya['kefiller'][$p['k']];
    cikti(['ok' => true,
        'kefil'   => ['ad' => (string)($kk['ad'] ?? ''), 'unvan' => (string)($kk['unvan'] ?? ''),
                      'ilgi' => (string)($kk['ilgi'] ?? ''), 'durum' => (string)($kk['durum'] ?? 'bekliyor'),
                      'gerekce' => (string)($kk['gerekce'] ?? '')],
        'yazar'   => ['ad' => (string)($ya['ad'] ?? ''), 'kurum' => (string)($ya['kurum'] ?? ''),
                      'orcid' => (string)($ya['orcid'] ?? '')],
        'calisma' => ['baslik' => (string)($bs['makale_baslik'] ?? ''), 'ozet' => (string)($bs['makale_ozet'] ?? ''),
                      'tarih' => (string)($bs['tarih'] ?? ''), 'yazarlar' => yazar_dizesi((array)($bs['yazarlar'] ?? []))],
        'asgari'  => (int)tg_ayar('kefil_gerekce_asgari', 120),
    ]);
}

if ($yol === '/kefil-onay' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($g['k'] ?? ''));
    $karar = (string)($g['karar'] ?? '');
    $gerekce = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['gerekce'] ?? ''))), 0, 3000, 'UTF-8');
    if (strlen($kod) < 16) cikti(['ok' => false, 'hata' => 'Bağlantı eksik.'], 400);
    if (!in_array($karar, ['onayli', 'ret'], true)) cikti(['ok' => false, 'hata' => 'Kararınızı belirtin.'], 400);
    $asgari = (int)tg_ayar('kefil_gerekce_asgari', 120);
    if (mb_strlen($gerekce, 'UTF-8') < $asgari) {
        cikti(['ok' => false, 'hata' => 'Gerekçeniz en az ' . $asgari . ' karakter olmalıdır. Bir araştırmacının adının bir çalışmada bulunmasına açıkça sahip çıkmak, gerekçesi yazılmadan geçilecek bir adım değildir.'], 400);
    }

    $b = oku_json('basvurular.json', []); if (!is_array($b)) $b = [];
    $p = kefil_bul($b, $kod);
    $mevcut = (string)($b[$p['b']]['yazarlar'][$p['y']]['kefiller'][$p['k']]['durum'] ?? 'bekliyor');
    if ($mevcut !== 'bekliyor') {
        cikti(['ok' => false, 'hata' => 'Bu yazarlık desteği için kararınızı daha önce verdiniz. Karar geri alınmaz; bir yanlışlık olduğunu düşünüyorsanız iletişim sayfasından yazın.'], 409);
    }
    $b[$p['b']]['yazarlar'][$p['y']]['kefiller'][$p['k']]['durum']       = $karar;
    $b[$p['b']]['yazarlar'][$p['y']]['kefiller'][$p['k']]['gerekce']     = $gerekce;
    $b[$p['b']]['yazarlar'][$p['y']]['kefiller'][$p['k']]['onay_tarihi'] = date('c');
    yaz_json('basvurular.json', $b);

    $ayarK = yonetim_ayar(); $tgK = (array)($ayarK['tg'] ?? []);
    if (!empty($tgK['token']) && !empty($tgK['chat'])) {
        tg_gonder((string)$tgK['token'], (string)$tgK['chat'],
            ($karar === 'onayli' ? "\xE2\x9C\x94 Yazarlik destegi onaylandi" : "\xE2\x9C\x96 Yazarlik destegi reddedildi") . "\n"
            . (string)($b[$p['b']]['yazarlar'][$p['y']]['kefiller'][$p['k']]['ad'] ?? '') . " -> "
            . (string)($b[$p['b']]['yazarlar'][$p['y']]['ad'] ?? '') . "\n"
            . (string)($b[$p['b']]['makale_baslik'] ?? ''), 4);
    }
    cikti(['ok' => true, 'durum' => $karar, 'mesaj' => $karar === 'onayli'
        ? 'Onayınız alındı. Adınız ve gerekçeniz, çalışma yayımlandığında sayfasında görünecektir.'
        : 'Reddiniz alındı ve gerekçesiyle kayda geçti. Bu çalışma, adı geçen araştırmacı için ikinci bir ' . tg_destek_ad() . ' bulunmadıkça yayına alınmaz.']);
}

/* BAŞVURULARI LİSTELE (yönetici) */
if ($yol === '/yonetim/basvurular' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $b = oku_json('basvurular.json', []); if (!is_array($b)) $b = [];
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $slugMap = [];
    foreach ($y as $e) { $slugMap[(string)($e['id'] ?? '')] = ['slug' => (string)($e['slug'] ?? ''), 'token' => (string)(($e['yazar_erisim']['token'] ?? '')), 'sifre' => (string)(($e['yazar_erisim']['sifre'] ?? ''))]; }
    usort($b, fn($x, $z) => strcmp((string)($z['tarih'] ?? ''), (string)($x['tarih'] ?? '')));
    foreach ($b as &$bb) {
        if (($bb['durum'] ?? '') === 'kabul' && ($bb['yazi_id'] ?? '') !== '' && isset($slugMap[$bb['yazi_id']])) {
            $bb['yazar_token'] = $slugMap[$bb['yazi_id']]['token'];
            $bb['yazar_sifre'] = $slugMap[$bb['yazi_id']]['sifre'];
            $bb['yazi_slug'] = $slugMap[$bb['yazi_id']]['slug'];
        }
    }
    unset($bb);
    cikti(['ok' => true, 'basvurular' => $b]);
}

/* BAŞVURUYU ARŞİVLE / GERİ AL (yönetici). SİLMEZ: kayıt dosyada durur,
   yalnızca panelde varsayılan listeden gizlenir ve "arşivdekileri göster"
   ile geri getirilir. Karar bekleyen başvuru arşivlenemez; bekleyen bir
   başvuru bir iştir ve gizlenirse unutulur. */
if ($yol === '/yonetim/basvuru-arsivle' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id = (string)($g['id'] ?? '');
    $ars = !empty($g['arsiv']);
    $b = oku_json('basvurular.json', []); if (!is_array($b)) $b = [];
    $bi = -1;
    foreach ($b as $i => $e) { if ((string)($e['id'] ?? '') === $id) { $bi = $i; break; } }
    if ($bi < 0) cikti(['ok' => false, 'hata' => 'Başvuru bulunamadı.'], 404);
    if ($ars && (string)($b[$bi]['durum'] ?? 'bekliyor') === 'bekliyor') {
        cikti(['ok' => false, 'hata' => 'Karar bekleyen başvuru arşivlenemez. Önce karara bağlayın.'], 409);
    }
    if ($ars) { $b[$bi]['arsiv'] = true; $b[$bi]['arsiv_tarih'] = date('c'); }
    else { unset($b[$bi]['arsiv'], $b[$bi]['arsiv_tarih']); }
    yaz_json('basvurular.json', $b);
    cikti(['ok' => true, 'arsiv' => $ars]);
}

/* BAŞVURU KARARI (yönetici): kabul -> makale + yazar erişimi aç; ret -> gerekçe */
if ($yol === '/yonetim/basvuru-karar' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json();
    $id = (string)($g['id'] ?? '');
    $karar = (string)($g['karar'] ?? '');
    $not = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['not'] ?? ''))), 0, 2000);
    if (!in_array($karar, ['kabul', 'ret'], true)) cikti(['ok' => false, 'hata' => 'Karar geçersiz.'], 400);
    $b = oku_json('basvurular.json', []); if (!is_array($b)) $b = [];
    $bi = -1;
    foreach ($b as $i => $e) { if ((string)($e['id'] ?? '') === $id) { $bi = $i; break; } }
    if ($bi < 0) cikti(['ok' => false, 'hata' => 'Başvuru bulunamadı.'], 404);
    if ($karar === 'ret') {
        $b[$bi]['durum'] = 'ret';
        $b[$bi]['not'] = $not;
        yaz_json('basvurular.json', $b);
        cikti(['ok' => true, 'durum' => 'ret']);
    }
    /* KABUL: zaten makale açılmışsa tekrar açma */
    if (($b[$bi]['durum'] ?? '') === 'kabul' && ($b[$bi]['yazi_id'] ?? '') !== '') {
        cikti(['ok' => true, 'durum' => 'kabul', 'zaten' => true, 'yazi_id' => $b[$bi]['yazi_id']]);
    }
    /* Kefil onayı gelmeden çalışma açılmaz. Editörün bu kuralı
       atlayabilmesi, kuralı kuralsız kılardı.

       DÜZEN KAPALIYSA DENETİM DE YOK — 15 Ağustos 2026. Destekleyen
       araştırmacı düzeni kurul kararıyla kapatıldı; kapalı bir düzenin
       onayını beklemek, editörü hiç gelmeyecek bir onay için
       durdurmaktı. Eski başvurularda kalmış yarım kayıtlar yüzünden
       kabul edilemeyen çalışmalar doğardı. */
    $kefilBekleyen = []; $kefilRet = [];
    if (tg_destek_duzeni_acik()) {
    foreach ((is_array($b[$bi]['yazarlar'] ?? null) ? $b[$bi]['yazarlar'] : []) as $ya) {
        if (!is_array($ya)) continue;
        foreach ((is_array($ya['kefiller'] ?? null) ? $ya['kefiller'] : []) as $kk) {
            if (!is_array($kk)) continue;
            $d = (string)($kk['durum'] ?? 'bekliyor');
            if ($d === 'ret')      $kefilRet[]      = (string)($kk['ad'] ?? '');
            elseif ($d !== 'onayli') $kefilBekleyen[] = (string)($kk['ad'] ?? '');
        }
    }
    }
    if ($kefilRet) {
        cikti(['ok' => false, 'hata' => tg_destek_ad_bas(false, true) . 'dan biri desteğini reddetti (' . implode(', ', $kefilRet)
            . '). Bu çalışma, unvanı olmayan yazar için yeni bir ' . tg_destek_ad() . ' gösterilmeden yayına alınamaz.'], 409);
    }
    if ($kefilBekleyen) {
        cikti(['ok' => false, 'hata' => 'Yazarlık desteği bekleniyor: ' . implode(', ', $kefilBekleyen)
            . '. Onaylar tamamlanmadan çalışma açılamaz.'], 409);
    }
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $bs = $b[$bi];
    $bv = is_array($bs['basvuran'] ?? null) ? $bs['basvuran'] : [];
    $eposta = mb_strtolower(trim((string)($bv['eposta'] ?? '')), 'UTF-8');
    $token = substr(hash('sha256', $id . microtime() . mt_rand() . random_int(0, PHP_INT_MAX)), 0, 32);
    $sifre = uret_sifre();
    $yid = substr(hash('sha256', microtime() . mt_rand()), 0, 10);
    $slug = yazi_slug((string)($bs['makale_baslik'] ?? '') ?: 'makale');
    foreach ($y as $e) { if (($e['slug'] ?? '') === $slug) { $slug .= '-' . substr($yid, 0, 4); break; } }
    $yazarlar = is_array($bs['yazarlar'] ?? null) ? $bs['yazarlar'] : [];
    $makale = [
        'id' => $yid,
        'slug' => $slug,
        /* YOL TEK KAYNAKTAN. Burada 'hakemli' ELLE yazılıydı ve
           gönderim koşullarında yazana ("kabul edilen çalışma önce
           hakemsiz yayımlanır") aykırıydı: kabul edilen her çalışma
           hakemli yola giriyor, sayfasında "Hakem aranıyor" rozeti
           beliriyordu. Gerekçe ortak.php'de tg_kabul_yolu()'nun
           başındadır. Deneme çalışması (aşağıda) bilerek 'hakemli'
           kalır: o zaten hakem akışını sınamak için vardır. */
        'tur' => tg_kabul_yolu(),
        'dil' => tg_dil_kodu($bs['makale_dil'] ?? '') ?: 'tr',
        'baslik' => (string)($bs['makale_baslik'] ?? ''),
        /* Yazarın başvuruda verdiği İngilizce başlık çalışmaya geçer.
           Eskiden burada boş dize vardı: yazar başlığı yazıyor, sistem
           onu alıp bir daha hiç kullanmıyordu. */
        'baslik_en' => (string)($bs['makale_baslik_en'] ?? ''),
        'yazar' => yazar_dizesi($yazarlar),
        'yazar_bilgi' => ['unvan' => (string)($bv['unvan'] ?? ''), 'ad' => (string)($bv['ad'] ?? ''), 'kurum' => (string)($bv['kurum'] ?? ''), 'eposta' => $eposta, 'orcid' => (string)($bv['orcid'] ?? ''), 'scopus' => (string)($bv['scopus'] ?? ''), 'web' => (string)($bv['web'] ?? '')],
        'yazar_liste' => $yazarlar,
        'tarih' => date('Y-m-d'),
        /* Gönderim anı, başvurunun KENDİ tarihinden gelir; date() ile
           yeniden üretilemez, çünkü başvuru ile kabul arasında günler,
           kimi zaman aylar vardır ve okura gösterilecek bekleme süresi
           tam olarak o aralıktır. Buraya date() yazmak, sistemin kendi
           gecikmesini sıfırlaması olurdu. */
        'gonderim' => substr((string)($bs['tarih'] ?? ''), 0, 10),
        'ozet' => (string)($bs['makale_ozet'] ?? ''),
        /* Yapı da çalışmaya geçer. Geçmeseydi yazar özetini bölümlere
           ayırır, kabul anında bölümler sessizce düşer ve yayımlanan
           sayfada yalnız birleştirilmiş dize kalırdı. */
        'ozet_yapi' => is_array($bs['makale_ozet_yapi'] ?? null) ? $bs['makale_ozet_yapi'] : [],
        /* Başvuruda ikinci dilde özet verilmişse çalışmaya o geçer.
           Eskiden burada sabit '' vardı: yazarın yazdığı özet
           kabul anında sessizce düşüyordu. */
        'ozet_en' => (string)($bs['makale_ozet_en'] ?? ''),
        /* Genişletilmiş özet çalışmaya geçer: künye onunla tamamlanır
           ve okurun başka dilden gördüğü tek uzun metin odur. */
        'genis_ozet_en' => (string)($bs['makale_genis_ozet_en'] ?? ''),
        /* Yazarın başvuruda verdiği alan kodları çalışmaya geçer */
        'alan' => (string)($bs['alan'] ?? ''),
        'alanlar' => is_array($bs['alanlar'] ?? null) ? $bs['alanlar'] : [],
        'anahtar' => '', 'anahtar_en' => '',
        /* TAM METİN BAŞVURUDAN GELİR (kurul kararı, 15 Ağustos 2026).
           Burada boş dize vardı: yazarın gönderdiği metin kabul anında
           düşüyor, yazardan aynı metni yazma ekranında BİR KEZ DAHA
           yazması isteniyordu. İkinci dildeki metin hâlâ boştur ve
           öyle kalır — çeviri istenmez, kayıt yazarın yazdığı dildeki
           metindir. */
        'metin' => (string)($bs['makale_metin'] ?? ''),
        'metin_en' => '', 'metin_ham' => '', 'metin_ham_en' => '',
        'kaynakca' => (string)($bs['makale_kaynakca'] ?? ''), 'kaynakca_en' => '',
        'yayin' => '', 'doi' => '',
        'bcid' => sonraki_bcid($y),
        'hakemler' => [],
        'yazar_erisim' => ['token' => $token, 'sifre' => $sifre, 'eposta_hash' => $eposta !== '' ? hash('sha256', $eposta . '|yazar') : ''],
        'basvuru_id' => $id,
        /* Bilimsel dürüstlük kaydı (makaleyle birlikte saklanır ve şeffaflık gereği gösterilir) */
        'intihal' => is_array($bs['intihal'] ?? null) ? $bs['intihal'] : [],
        'veri' => is_array($bs['veri'] ?? null) ? $bs['veri'] : [],
        'yz' => is_array($bs['yz'] ?? null) ? $bs['yz'] : [],
        /* Beyanlar ve ölçüler çalışmaya geçer: ikisi de yayımlanır.
           Editör notu GEÇMEZ — yayımlanmayacak bir metni yayımlanan
           kaydın içine koymak, bir gün basılması demektir. */
        'beyan' => is_array($bs['beyan'] ?? null) ? $bs['beyan'] : [],
        'olcu'  => is_array($bs['olcu'] ?? null) ? $bs['olcu'] : [],
        'ek' => is_array($bs['ek'] ?? null) ? $bs['ek'] : [],
        'hakem_oneri' => is_array($bs['hakem_oneri'] ?? null) ? $bs['hakem_oneri'] : [],
        'link' => '',
    ];
    $y[] = $makale;
    yaz_json('yazilar.json', $y);
    $b[$bi]['durum'] = 'kabul';
    $b[$bi]['not'] = $not;
    $b[$bi]['yazi_id'] = $yid;
    yaz_json('basvurular.json', $b);
    indexnow_yazi($slug, (string)($makale['bcid'] ?? ''));
    cikti(['ok' => true, 'durum' => 'kabul', 'yazi_id' => $yid, 'slug' => $slug, 'token' => $token, 'sifre' => $sifre, 'eposta' => $eposta,
        'link' => (function_exists('tg_url') ? tg_url('/yazar.php?t=' . $token) : 'https://kutadgu.net/yazar.php?t=' . $token)]);
}

/* YAZAR FORM: token+e-posta+şifre ile makalesini ve hakem geri bildirimlerini getir */
if ($yol === '/yazar-form' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    $ipk = 'yazar:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 40) cikti(['ok' => false, 'hata' => 'Çok fazla deneme, biraz sonra tekrar deneyin.'], 429);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = yazar_yetki($y, $g);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Erişim bağlantısı bulunamadı. Hesabınıza girdiyseniz bu çalışmanın yazarları arasında görünmüyor olabilirsiniz.'], 404);
    if (isset($d['yetkisiz'])) cikti(['ok' => false, 'hata' => 'Bu çalışmanın yazarı olarak görünmüyorsunuz. Adınız ya da ORCID\'iniz çalışmada yazılıysa hesabınızdakiyle aynı olmalıdır.'], 403);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'Bu e-posta, erişimin tanımlandığı adresle eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı. Size iletilen erişim şifresini girin.'], 403);
    [$i] = $d; $e = $y[$i];
    $kararAd = ['kabul' => 'Kabul', 'kucuk' => 'Küçük revizyonla kabul', 'buyuk' => 'Büyük revizyon', 'ret' => 'Ret'];
    $hakemGeri = [];
    foreach (tg_dizi($e['hakemler'] ?? null) as $h) {
        if (!is_array($h)) continue;
        $kk = (string)($h['karar'] ?? '');
        /* ---- ERİŞİM BİLGİSİ YALNIZCA YAZARIN KENDİ ÖNERDİĞİ HAKEM İÇİN ----
           Burası bir güvenlik açığıydı ve kapatıldı (11 Ağustos 2026).
           Döngü bütün hakemleri kapsıyordu; yani editörün atadığı ya da
           kurulun gönüllüler arasından onayladığı hakemin giriş
           bağlantısı ve şifresi de yazara gidiyordu. Yazar o bağlantıyla
           hakemin değerlendirme sayfasını açabilir, raporunu yazılmadan
           görebilir, hatta yazabilirdi. Bu, bağımsız hakemlik iddiasını
           doğrudan deler.

           Yazarın kendi önerdiği hakem için bilgi bilerek gösterilir:
           daveti ona yazar iletir. Bunun dışında hiçbir hakemin erişim
           bilgisi yazara açılmaz. Ölçüt kaydın kendisidir
           (atayan.tur === 'yazar'), sayfanın değil. */
        $kendiOnerisi = (string)((($h['atayan'] ?? [])['tur'] ?? '')) === 'yazar';
        $hakemGeri[] = [
            'ad' => (string)($h['ad'] ?? ''),
            'link' => $kendiOnerisi
                ? (function_exists('tg_url') ? tg_url('/hakem.php?t=' . (string)($h['token'] ?? '')) : 'https://kutadgu.net/hakem.php?t=' . (string)($h['token'] ?? ''))
                : '',
            'sifre' => $kendiOnerisi ? (string)($h['sifre'] ?? '') : '',
            'atayan' => (string)((($h['atayan'] ?? [])['tur'] ?? '')),
            'diyalog' => tg_diyalog($h),
            'diyalog_sira' => tg_diyalog_sira($h),
            'diyalog_tur' => tg_diyalog_tur($h),
            'karar' => $kk, 'kararAd' => $kararAd[$kk] ?? '',
            'rapor' => (string)($h['rapor'] ?? ''),
            'endeks' => is_array($h['endeks'] ?? null) ? array_values($h['endeks']) : [],
            'notlar' => is_array($h['notlar'] ?? null) ? array_values($h['notlar']) : [],
            'dosya' => (string)($h['dosya'] ?? ''),
        ];
    }
    $kilitD = tg_kilit_durum($e);
    cikti(['ok' => true, 'kilitli' => yazar_kilitli($e),
        'kilit' => $kilitD,
        'kilit_metin' => tg_kilit_metin($kilitD, false),
        'kilit_metin_en' => tg_kilit_metin($kilitD, true),
        'metin_ozeti' => tg_metin_ozeti($e),
        'surumler' => array_slice(array_map(function($v){
            return ['tarih' => (string)($v['tarih'] ?? ''), 'ozet' => (string)($v['ozet'] ?? ''), 'kaynak' => (string)($v['kaynak'] ?? '')];
        }, is_array($e['surumler'] ?? null) ? $e['surumler'] : []), -10),
        'yazi' => [
            'baslik' => (string)($e['baslik'] ?? ''), 'baslik_en' => (string)($e['baslik_en'] ?? ''),
            'ozet' => (string)($e['ozet'] ?? ''), 'ozet_en' => (string)($e['ozet_en'] ?? ''),
            /* Yapı geri de verilir; verilmeseydi panel her açılışta
               kutuları boş gösterir ve ilk kayıtta yazar kendi
               bölümlerini silmiş olurdu. Okuma ile yazma aynı alanı
               görmeli. Tutarsız yapı hiç dönmez (tg_ozet_yapisi). */
            'ozet_yapi' => tg_ozet_yapisi($e),
            'anahtar' => tg_metin($e['anahtar'] ?? ''), 'anahtar_en' => tg_metin($e['anahtar_en'] ?? ''),
            'metin' => (string)($e['metin'] ?? ''), 'metin_en' => (string)($e['metin_en'] ?? ''),
            'kaynakca' => (string)($e['kaynakca'] ?? ''), 'kaynakca_en' => (string)($e['kaynakca_en'] ?? ''),
            'doi' => (string)($e['doi'] ?? ''), 'slug' => (string)($e['slug'] ?? ''),
            /* ÇALIŞMANIN DİLİ DE DÖNER. Sayfa sunucuda basılırken hangi
               çalışmanın açılacağı bilinmiyor (erişim sonra doğrulanır);
               künye bölümünün gerekip gerekmediğini ancak bu bilgiyle
               söyleyebilir. Dönmeseydi, zaten künye dilinde yazılmış bir
               çalışmanın yazarından aynı metin ikinci kez istenirdi. */
            'dil' => tg_yazi_dili($e),
            'bcid' => (string)($e['bcid'] ?? ''),
            /* Çevirinin künyesi de geri verilir; yoksa panel her
               açılışta kutuyu boş gösterir ve yazar kaydettiği şeyi
               göremez. Okuma ile yazma aynı alanı görmeli. */
            'ceviri_en' => is_array($e['ceviri_en'] ?? null)
                ? ['kaynak'  => (string)($e['ceviri_en']['kaynak'] ?? ''),
                   'ceviren' => (string)($e['ceviri_en']['ceviren'] ?? ''),
                   'onay'    => !empty($e['ceviri_en']['onay'])]
                : null,
            'yazar_bilgi' => is_array($e['yazar_bilgi'] ?? null) ? $e['yazar_bilgi'] : [],
            'yazar_liste' => is_array($e['yazar_liste'] ?? null) ? $e['yazar_liste'] : [],
        ],
        /* ETİK BEYANI GERİ DE VERİLİR. Yazma açılıp okuma açılmasaydı
           panel her açılışta kutuyu boş gösterir, yazar kaydettiğini
           göremez ve ikinci kaydında kendi beyanını silmiş sanırdı.
           'hal' de gönderilir ki panel, arşiv kaydını suçlamadan
           anlatabilsin — hâlin adı tek kaynaktan gelir. */
        /* KÜNYE KURALI TEK KAYNAKTAN GELİR. Panel kuralı kendi yazsaydı,
           ayar değiştiği gün sayfa bir şey söyler, uç başka bir şey
           uygulardı — kaldırılmış bir kuralı duyuran metinlerin doğuş
           biçimi budur. */
        'kunye' => [
            'kod'     => tg_kunye_dili(),
            'ad'      => tg_kunye_dil_adi(),
            'zorunlu' => tg_kunye_zorunlu(),
        ],
        'etik' => [
            'durum' => (string)((is_array($e['etik'] ?? null) ? $e['etik'] : [])['durum'] ?? ''),
            'kurul' => (string)((is_array($e['etik'] ?? null) ? $e['etik'] : [])['kurul'] ?? ''),
            'tarih' => (string)((is_array($e['etik'] ?? null) ? $e['etik'] : [])['tarih'] ?? ''),
            'no'    => (string)((is_array($e['etik'] ?? null) ? $e['etik'] : [])['no'] ?? ''),
            'hal'   => tg_etik_hal($e),
            'hal_ad'    => tg_etik_hal_ad(tg_etik_hal($e), false),
            'hal_ad_en' => tg_etik_hal_ad(tg_etik_hal($e), true),
            'kilitli'   => tg_etik_beyanli(is_array($e['etik'] ?? null) ? $e['etik'] : []),
        ],
        'hakemler' => $hakemGeri]);
}

/* YAZAR KAYDET: makalesini günceller (2 RET sonrası kilitli) */
if ($yol === '/yazar-kaydet' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    $ipk = 'yazar:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 60) cikti(['ok' => false, 'hata' => 'Çok fazla istek.'], 429);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = yazar_yetki($y, $g);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Erişim bulunamadı.'], 404);
    if (isset($d['yetkisiz'])) cikti(['ok' => false, 'hata' => 'Bu çalışmanın yazarı olarak görünmüyorsunuz.'], 403);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'E-posta eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i] = $d;
    if (yazar_kilitli($y[$i])) cikti(['ok' => false, 'hata' => 'Bu makale iki ret kararı aldığı için artık düzenlenemez. Makale, ret gerekçeleriyle yayında kalır.'], 403);

    /* ---- METİN KİLİDİ ----
       Değerlendirmesi tamamlanmış bir çalışmanın metni yazar tarafından
       değiştirilemez. Bu kural yalnızca bilimsel kaydı değil, yazarın
       kendisini de korur: erişim şifresi ele geçirilse bile yayımlanmış
       bir çalışmanın içeriği değiştirilemez. Kilitliyken yalnızca
       yazarın kendi profil bilgileri güncellenebilir. */
    $kilit = tg_kilit_durum($y[$i]);
    $icerikIstendi = false;
    foreach (['baslik','baslik_en','ozet','ozet_en','anahtar','anahtar_en','metin','metin_en','kaynakca','kaynakca_en','doi','yazarlar','ceviri_en'] as $ak) {
        if (array_key_exists($ak, $g)) { $icerikIstendi = true; break; }
    }
    if ($kilit === 'kilitli' && $icerikIstendi) {
        $y[$i]['kilit_denemesi'][] = ['tarih' => date('c'), 'ip' => substr(hash('sha256', ip_al()), 0, 12)];
        if (count($y[$i]['kilit_denemesi']) > 40) array_shift($y[$i]['kilit_denemesi']);
        yaz_json('yazilar.json', $y);
        cikti(['ok' => false, 'kilit' => 'kilitli',
               'hata' => 'Bu çalışmanın değerlendirmesi tamamlandığı için metni artık değiştirilemez. Bir hata bulduysanız yazar panelinden düzeltme isteyebilirsiniz; düzeltme, tarihi ve gerekçesiyle birlikte çalışmanın sayfasında görünür.'], 403);
    }

    /* Yazar içeriği güvenli HTML süzgecinden geçer (kalıcı XSS koruması) */
    $al = fn($k, $n) => guvenli_html(mb_substr((string)($g[$k] ?? ''), 0, $n));
    $oncekiOzet = tg_metin_ozeti($y[$i]);
    if ($icerikIstendi) {
        $baslik = mb_substr(trim(strip_tags((string)($g['baslik'] ?? ''))), 0, 220);
        if ($baslik === '') cikti(['ok' => false, 'hata' => 'Başlık boş olamaz.'], 400);
        /* ---- KÜNYE KURALI PENCEREDE DE UYGULANIR ----
           ÖLÇÜLEN KUSUR — 19 Ağustos 2026. Künye (künye dilindeki başlık
           ve öz) 15 Ağustos'ta ZORUNLU oldu ve kural yalnız KAPIDA,
           /yazar-basvuru'da uygulanıyordu. Yazar paneli aynı alanları
           hiç denetlemiyordu: gönderirken zorunlu olan bir alan,
           yayımdan sonra tek tıkla boşaltılabiliyordu. Kapıda uygulanıp
           pencerede uygulanmayan kural, kural değildir.

           AMA ARŞİV KAYDI SUÇLANMAZ. Kural 15 Ağustos'ta başladı; ondan
           önce yayımlanmış çalışmalarda künye hiç istenmedi ve boş
           olması bir eksiklik değil, o günün kuralıdır. Boş bir künyeyi
           şimdi zorunlu kılmak, yazarı bir yazım yanlışını düzeltmekten
           bile alıkoyardı — etik beyanında da aynı tuzağa düşülmüştü.

           Uygulanan kural bu yüzden DAR ve tam olarak zararı önler:
           **dolu bir künye alanı boşaltılamaz.** Boşsa istenir,
           dayatılmaz. */
        if (tg_kunye_zorunlu() && tg_kunye_dili() !== ''
            && tg_yazi_dili($y[$i]) !== '' && tg_yazi_dili($y[$i]) !== tg_kunye_dili()) {
            foreach ([['baslik_en', 'Başlık', 'The title'], ['ozet_en', 'Öz', 'The abstract']] as [$ak, $adTr, $adEn]) {
                $eski = trim(strip_tags((string)($y[$i][$ak] ?? '')));
                $yeni = trim(strip_tags((string)($g[$ak] ?? '')));
                if ($eski !== '' && $yeni === '') {
                    cikti(['ok' => false, 'hata' => tg_cd(
                        '%2 (%1) boş bırakılamaz: dizinler ve başka dilden okurun atıf künyesi onu okur. Değiştirebilirsiniz, ama silemezsiniz.',
                        '%2 (%1) cannot be left empty: indexes and the citation entry seen by readers in other languages read it. You may change it, but you cannot delete it.',
                        null, tg_kunye_dil_adi(), tg_t(['tr' => $adTr, 'en' => $adEn], k_en()))], 400);
                }
            }
        }
        $y[$i]['baslik'] = $baslik;
        $y[$i]['baslik_en'] = mb_substr(trim(strip_tags((string)($g['baslik_en'] ?? ''))), 0, 220);
        $y[$i]['ozet'] = $al('ozet', 4000);
        $y[$i]['ozet_en'] = $al('ozet_en', 4000);
        /* ---- YAPILANDIRILMIŞ ÖZ: ÇELİŞKİ BIRAKILMAZ ----
           Yazar özetini yayımdan sonra düzenleyebiliyor. Yapı burada
           hiç ele alınmasaydı, serbest metni değiştiren yazarın kaydında
           artık özeti ANLATMAYAN bir yapı kalırdı; sayfa iki ayrı özet
           gösterir ve hangisinin doğru olduğu sorusunun yanıtı olmazdı.

           İki yol var ve ikisi de tutarlı bırakır:
             - yapı gönderildiyse özet ondan TÜRETİLİR,
             - gönderilmediyse yapı DÜŞER (yazar serbest metne döndü).
           Yapıyı sessizce korumak, sessizce yanlış göstermek olurdu. */
        if (array_key_exists('ozet_yapi', $g)) {
            $yy = tg_ozet_yapi_temizle($g['ozet_yapi']);
            if ($yy) { $y[$i]['ozet_yapi'] = $yy; $y[$i]['ozet'] = mb_substr(tg_ozet_yapidan($yy), 0, 4000); }
            else unset($y[$i]['ozet_yapi']);
        } elseif (isset($y[$i]['ozet_yapi'])) {
            unset($y[$i]['ozet_yapi']);
        }
        $y[$i]['anahtar'] = mb_substr(trim(tg_metin($g['anahtar'] ?? '')), 0, 400);
        $y[$i]['anahtar_en'] = mb_substr(trim(tg_metin($g['anahtar_en'] ?? '')), 0, 400);
        $y[$i]['metin'] = $al('metin', 120000);
        $y[$i]['metin_en'] = $al('metin_en', 120000);
        $y[$i]['kaynakca'] = $al('kaynakca', 20000);
        $y[$i]['kaynakca_en'] = $al('kaynakca_en', 20000);
        $y[$i]['doi'] = mb_substr(trim((string)($g['doi'] ?? '')), 0, 120);

        /* =============================================================
           ÇEVİRİNİN KÜNYESİ
           -------------------------------------------------------------
           İlkeler sayfası "çevirenin adı kayda geçer" diye yazıyordu ve
           kayıtta öyle bir alan yoktu. Alan burada yazılır; metnin
           kendisi yerinde kalır (bkz. ortak.php, tg_yazi_ceviriler).

           ONAY YAZARA AİTTİR VE BURADA VERİLİR: bu uç noktaya yalnız
           çalışmanın yazarı erişebiliyor, yani kutuyu işaretleyen kişi
           onayı verebilecek kişidir. Onayı başka bir yerden almak,
           onayı sahiplerinden başkasına yazdırmak olurdu.

           'yazar' seçildiğinde onay sorusu düşer: yazan zaten odur.
           Bilinmeyen bir değer gelirse 'yazar' değil, en temkinli
           varsayım olan 'makine' + onaysız seçilmez; çünkü o da bir
           uydurmadır. Bilinmeyen değer reddedilir. */
        if (array_key_exists('ceviri_en', $g)) {
            $ck = is_array($g['ceviri_en']) ? $g['ceviri_en'] : [];
            $kaynak = (string)($ck['kaynak'] ?? '');
            if ($kaynak !== '' && !in_array($kaynak, tg_ceviri_kaynaklari(), true)) {
                cikti(['ok' => false, 'hata' => 'Çeviri kaynağı yalnızca "yazar", "insan" ya da "makine" olabilir.'], 400);
            }
            if ($kaynak === '') {
                unset($y[$i]['ceviri_en']);
            } else {
                $y[$i]['ceviri_en'] = [
                    'kaynak'  => $kaynak,
                    'ceviren' => mb_substr(trim(strip_tags((string)($ck['ceviren'] ?? ''))), 0, 120),
                    'onay'    => $kaynak === 'yazar' ? true : !empty($ck['onay']),
                    'tarih'   => date('Y-m-d'),
                ];
            }
        }
    }
    /* Yazar bilgisi (başvuran/iletişim) güncellenebilir; ORCID ve Scopus zorunlu */
    if (is_array($g['yazar_bilgi'] ?? null)) {
        $bb = $g['yazar_bilgi'];
        $k = fn($x, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($bb[$x] ?? ''))), 0, $n);
        $o = orcid_temiz($k('orcid', 60));
        if ($o === '') cikti(['ok' => false, 'hata' => 'İletişim yazarı için geçerli bir ORCID zorunludur (0000-0000-0000-0000 biçiminde).'], 400);
        $scH = trim($k('scopus', 200));
        $sc  = scopus_temiz($scH);
        if ($scH !== '' && $sc === '') cikti(['ok' => false, 'hata' => 'Scopus Author ID girildiyse geçerli olmalıdır (yalnızca rakam).'], 400);
        $y[$i]['yazar_bilgi'] = ['unvan' => $k('unvan', 60), 'ad' => $k('ad', 120), 'kurum' => $k('kurum', 220), 'eposta' => $k('eposta', 120), 'orcid' => $o, 'scopus' => $sc, 'web' => $k('web', 200)];
    }
    /* Yazar listesi güncellenebilir (hepsi Dr+ ve ORCID zorunlu, Scopus isteğe bağlı) */
    $yl = $g['yazarlar'] ?? null;
    if (is_string($yl)) $yl = json_decode($yl, true);
    if (is_array($yl)) {
        $liste = [];
        foreach ($yl as $ya) {
            if (!is_array($ya)) continue;
            $ad = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($ya['ad'] ?? ''))), 0, 120); if ($ad === '') continue;
            $un = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($ya['unvan'] ?? ''))), 0, 60);
            /* ÖLÇÜLEN KUSUR: bu uç doktora şartını /yazar-basvuru'dan
               DAHA SIKI arıyordu — destekleyen araştırmacı yolu burada
               hiç yoktu. Yani iki destekleyenle usulünce kabul edilmiş
               bir yazar, kendi yazar listesini bir daha kaydedemiyordu.
               Şart kalktığı için sorun kendiliğinden bitti; ama koşul
               tek kaynaktan okunuyor ki şart geri açılırsa iki uç aynı
               kuralı uygulasın. */
            if (tg_yazarlik_doktora_sarti() && !unvan_yeterli($un)) {
                cikti(['ok' => false, 'hata' => 'Tüm yazarlar en az "Dr." unvanına sahip olmalı: ' . $ad], 400);
            }
            $o = orcid_temiz((string)($ya['orcid'] ?? ''));
            if ($o === '') cikti(['ok' => false, 'hata' => 'Her yazar için geçerli bir ORCID zorunludur (0000-0000-0000-0000). Eksik ya da hatalı: ' . $ad], 400);
            $scH = trim((string)($ya['scopus'] ?? ''));
            $sc  = scopus_temiz($scH);
            if ($scH !== '' && $sc === '') cikti(['ok' => false, 'hata' => 'Scopus Author ID girildiyse geçerli olmalıdır (yalnızca rakam): ' . $ad], 400);
            $liste[] = ['unvan' => $un, 'ad' => $ad, 'kurum' => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($ya['kurum'] ?? ''))), 0, 220),
                        'orcid' => $o, 'scopus' => $sc];
            if (count($liste) >= 15) break;
        }
        if ($liste) { $y[$i]['yazar_liste'] = $liste; $y[$i]['yazar'] = yazar_dizesi($liste); }
    }

    /* ---- ETİK KURUL BEYANI: KİLİTTEN BAĞIMSIZ ----
       ÖLÇÜLEN KUSUR — 18 Ağustos 2026. Bu uç 'etik' alanını hiç
       okumuyordu. Sonuç: yazi.php "Yazar bunları bildirdiğinde bu uyarı
       kalkar" diyordu ve bildirmenin HİÇBİR YOLU yoktu. Askıya alınmış
       her çalışma sonsuza kadar askıda kalırdı; 15 Ağustos'ta kaldırılan
       kusurun aynısı, bu kez uç noktada.

       BEYAN, METİN KİLİDİNİN DIŞINDADIR ve bu bilerek böyledir. Kilit,
       değerlendirmesi biten bir çalışmanın BULGULARININ değişmesini
       önler. Etik beyanı bulgu değildir; kayıt dışı bırakılmış bir
       olgunun kayda geçirilmesidir. Kilitli çalışmada beyanı yasaklamak,
       eksiği kalıcı kılmaktan başka bir işe yaramazdı. Bu yüzden 'etik'
       $icerikIstendi listesinde YOKTUR.

       GERİ ALINAMAZ YÖNÜ SINIRLANDIRILDI: verilmiş bir beyan
       SİLİNEMEZ, yalnız düzeltilebilir. 'gerekli' demiş ve kurul/tarih/
       numarayı yazmış bir yazar sonradan 'gereksiz'e dönemez; dönebilse
       beyan, unutulabilir bir şey olurdu. Değişiklik her durumda
       tarihiyle kayda geçer. */
    if (array_key_exists('etik', $g) && is_array($g['etik'])) {
        $ge  = $g['etik'];
        $esk = is_array($y[$i]['etik'] ?? null) ? $y[$i]['etik'] : [];
        $eskiBeyanli = tg_etik_beyanli($esk);
        $dur = trim((string)($ge['durum'] ?? ''));
        if ($dur !== '' && $dur !== 'gerekli' && $dur !== 'gereksiz') {
            cikti(['ok' => false, 'hata' => 'Etik beyanı için geçersiz durum.'], 400);
        }
        if ($eskiBeyanli && $dur === 'gereksiz') {
            cikti(['ok' => false, 'hata' => 'Bu çalışma için etik kurul izni zaten beyan edilmiş. Beyan geri alınamaz; yanlış girildiyse editöre düzeltme isteyin.'], 403);
        }
        if ($dur === 'gerekli') {
            $eKurul = mb_substr(trim(strip_tags((string)($ge['kurul'] ?? ''))), 0, 220);
            $eTarih = mb_substr(trim(strip_tags((string)($ge['tarih'] ?? ''))), 0, 40);
            $eNo    = mb_substr(trim(strip_tags((string)($ge['no'] ?? ''))), 0, 80);
            if ($eKurul === '' || $eTarih === '' || $eNo === '') {
                cikti(['ok' => false, 'hata' => 'Etik kurul izni gerekliyse kurul adı, karar tarihi ve karar numarasının üçü de yazılmalıdır.'], 400);
            }
            $yeniEtik = ['durum' => 'gerekli', 'kurul' => $eKurul, 'tarih' => $eTarih, 'no' => $eNo];
            $eBelge = trim((string)($ge['belge'] ?? ''));
            if ($eBelge !== '' && preg_match('~^https?://~i', $eBelge)) $yeniEtik['belge'] = mb_substr($eBelge, 0, 300);
        } elseif ($dur === 'gereksiz') {
            $yeniEtik = ['durum' => 'gereksiz'];
        } else {
            $yeniEtik = null;                       /* boş gönderim: dokunma */
        }
        if ($yeniEtik !== null) {
            /* Beyan yayımdan SONRA yapıldıysa tarihi kaydedilir ve
               çalışmanın sayfasında da yazılır. Geç yapılmış bir beyanı
               zamanında yapılmış gibi göstermek, beyanı değersizleştirir. */
            if (trim((string)($esk['durum'] ?? '')) === '') $yeniEtik['beyan_tarihi'] = date('c');
            elseif (isset($esk['beyan_tarihi'])) $yeniEtik['beyan_tarihi'] = (string)$esk['beyan_tarihi'];
            if (isset($esk['onay'])) $yeniEtik['onay'] = $esk['onay'];
            $y[$i]['etik'] = $yeniEtik;
            if (!is_array($y[$i]['kayitlar'] ?? null)) $y[$i]['kayitlar'] = [];
            $y[$i]['kayitlar'][] = [
                'tur'   => 'etik-beyan',
                'tarih' => date('c'),
                'kim'   => 'yazar',
                'not'   => $yeniEtik['durum'] === 'gereksiz'
                           ? 'Yazar, etik kurul izninin gerekmediğini beyan etti.'
                           : 'Yazar etik kurul iznini beyan etti: ' . $yeniEtik['kurul']
                             . ' / ' . $yeniEtik['tarih'] . ' / ' . $yeniEtik['no'],
            ];
        }
    }

    /* ---- Değişiklik kaydı ve bildirim ----
       Metin değiştiyse parmak izi ve zamanı kaydedilir, yazara e-posta
       gider. Şifresi çalınan bir yazar, izinsiz bir değişikliği böylece
       hemen fark eder ve düzeltilmesini isteyebilir. */
    $yeniOzet = tg_metin_ozeti($y[$i]);
    if ($icerikIstendi && $yeniOzet !== $oncekiOzet) {
        if (!is_array($y[$i]['surumler'] ?? null)) $y[$i]['surumler'] = [];
        $y[$i]['surumler'][] = [
            'ozet'  => $yeniOzet,
            'onceki'=> $oncekiOzet,
            'tarih' => date('c'),
            'kaynak'=> 'yazar',
            'ip'    => substr(hash('sha256', ip_al()), 0, 12),
        ];
        if (count($y[$i]['surumler']) > 60) array_shift($y[$i]['surumler']);
        $bildirimGerek = true;
    } else { $bildirimGerek = false; }

    yaz_json('yazilar.json', $y);

    if ($bildirimGerek) {
        $marka2 = (string)tg_ayar('marka', 'Kutadgu');
        $kok2   = tg_kok();
        $adresler = [];
        $ym = (string)($y[$i]['yazar_erisim']['eposta_acik'] ?? '');
        if ($ym !== '') $adresler[] = $ym;
        $yb2 = is_array($y[$i]['yazar_bilgi'] ?? null) ? $y[$i]['yazar_bilgi'] : [];
        $ym2 = mb_strtolower(trim((string)($yb2['eposta'] ?? '')), 'UTF-8');
        if ($ym2 !== '' && !in_array($ym2, $adresler, true)) $adresler[] = $ym2;
        foreach ($adresler as $adr) {
            eposta_gonder($adr, $marka2 . ' | Çalışmanızın metni değiştirildi',
                "Sayın " . (string)($y[$i]['yazar'] ?? '') . ",\n\n" .
                "\"" . (string)($y[$i]['baslik'] ?? '') . "\" başlıklı çalışmanızın metni az önce yazar panelinden güncellendi.\n\n" .
                "Tarih: " . date('d.m.Y H:i') . "\n" .
                "Metin parmak izi: " . $yeniOzet . "\n\n" .
                "Bu değişikliği siz yaptıysanız yapmanız gereken bir şey yok.\n\n" .
                "SİZ YAPMADIYSANIZ erişim şifreniz başkasının eline geçmiş olabilir. Bu durumda hemen " .
                "bize yazın; erişim şifreniz yenilenir ve metnin önceki hâli geri getirilir. Her sürümün " .
                "parmak izi saklandığı için neyin değiştiği kanıtlanabilir.\n\n" .
                $marka2 . "\n" . $kok2 . "\n");
        }

        /* ---- HAKEME DE HABER VERİLİR ----
           Bu bildirim eksikti (11 Ağustos 2026 ölçümü). Yazar metnini
           düzeltiyor, yeni sürüm kayda giriyor, ama değerlendirmeyi
           bekleyen hakem bundan hiç haberdar olmuyordu; kararını eski
           metne bakarak veriyordu. Revizyon döngüsünün sessiz olması
           tek başına bir kusurdur.

           Yalnızca süreci açık olan hakemlere gider: daveti kabul
           etmiş, kararı henüz kapanmamış olanlara. Reddetmiş ya da
           kararını vermiş birini rahatsız etmez. */
        $marka3 = $marka2;
        foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $hh) {
            if (!is_array($hh)) continue;
            $dd = (string)($hh['davet_durum'] ?? '');
            if ($dd === 'ret' || $dd === 'geri_cekildi') continue;
            if (!empty($hh['kapali'])) continue;
            $kk = (string)($hh['karar'] ?? '');
            if ($kk === 'kabul' || $kk === 'ret') continue;
            $hm3 = (string)($hh['eposta_acik'] ?? '');
            if ($hm3 === '') continue;
            eposta_gonder($hm3, $marka3 . ' | Değerlendirdiğiniz çalışmanın yeni sürümü var',
                "Sayın " . (string)($hh['ad'] ?? '') . ",\n\n" .
                "Değerlendirmekte olduğunuz çalışmanın yazarı metni güncelledi:\n\n" .
                "    \"" . (string)($y[$i]['baslik'] ?? '') . "\"\n\n" .
                "Tarih: " . date('d.m.Y H:i') . "\n" .
                "Yeni metin parmak izi: " . $yeniOzet . "\n\n" .
                "Değerlendirme sayfanızdan yeni sürümü okuyabilirsiniz. Bir yanıt yazmanız " .
                "gerekmiyor; bu ileti yalnızca metnin değiştiğini bildirmek içindir.\n\n" .
                tg_url('/hakem.php?t=' . (string)($hh['token'] ?? '')) . "\n\n" .
                $marka3 . "\n" . $kok2 . "\n");
        }
    }

    indexnow_yazi((string)($y[$i]['slug'] ?? ''), (string)($y[$i]['bcid'] ?? ''));
    cikti(['ok' => true, 'slug' => (string)$y[$i]['slug'], 'kilit' => $kilit, 'ozet' => $yeniOzet]);
}

/* YAZARIN DÜZELTME İSTEĞİ
   Metin kilitliyken yazar doğrudan değiştiremez; gerekçesini yazıp
   düzeltme ister. İstek editöre gider, kabul edilirse düzeltme kaydı
   olarak çalışmanın sayfasında tarihiyle görünür. */
/* =====================================================================
   YAZAR ÇALIŞMASINI HAKEMLİĞE AÇAR
   ---------------------------------------------------------------------
   Bir çalışmanın hakemli mi hakemsiz mi olduğu bugüne kadar YALNIZCA
   gönderim anında belirleniyordu. Sonradan tek değiştiği yer otomatik
   düşüştü: iki ret kararı alan hakemli çalışma yazıya iner. Yani yazar
   fikrini değiştirip "bunu hakeme açayım" diyemiyordu; sistemde böyle
   bir yol yoktu.

   Bu, kuruluş dönemi için gerçek bir engel. Hakem havuzu gönüllülükle
   büyüyor: bir çalışma hakemli olarak işaretlenmedikçe bekleyen.php
   listesine düşmez, listeye düşmedikçe kimse gönüllü olamaz. Yazar
   kendi çalışmasını o listeye koyamıyorsa hiç kimse hakem olamaz ve
   havuz hiç büyümez.

   Kapılar:
     - Yalnızca çalışmanın yazarı açabilir (yazar_yetki).
     - İki ret almış çalışma açılamaz: o süreç kapandı ve kararı geri
       almak, verilmiş bir kararı yok saymak olurdu.
     - Geri çekilmiş çalışma açılamaz.
     - Zaten hakemliyse bir şey yapılmaz.

   Kapatma da vardır ama dardır: hiç hakem kaydı yokken yazar fikrini
   geri alabilir. Bir tek rapor yazıldıktan sonra kapatmak, o raporu
   yazan kişinin emeğini görünmez kılardı.

   Her iki yön de kayda yazılır: ne zaman, kimin eliyle. Bir çalışmanın
   hangi yolda olduğu, o yola nasıl girdiğiyle birlikte okunabilmelidir.
   ===================================================================== */
if ($yol === '/yazar-hakemlige-ac' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'yazar:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 30) cikti(['ok' => false, 'hata' => 'Çok fazla istek.'], 429);
    $kapat = !empty($g['kapat']);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = yazar_yetki($y, $g);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Erişim bulunamadı.'], 404);
    if (isset($d['yetkisiz'])) cikti(['ok' => false, 'hata' => 'Bu çalışmanın yazarı olarak görünmüyorsunuz.'], 403);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'E-posta eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i] = $d;
    $e = $y[$i];

    if (function_exists('tg_geri_cekildi') && tg_geri_cekildi($e)) {
        cikti(['ok' => false, 'hata' => 'Geri çekilmiş bir çalışma hakemliğe açılamaz.'], 409);
    }
    if (yazar_kilitli($e)) {
        cikti(['ok' => false, 'hata' => 'Bu çalışma iki ret kararı aldı; değerlendirme süreci kapandı ve yeniden açılamaz. Çalışma, ret gerekçeleriyle birlikte yayında kalır.'], 409);
    }

    $simdi = date('c');
    $kim = '';
    $h = function_exists('hs_oturum') ? hs_oturum() : null;
    if (is_array($h)) $kim = (string)(function_exists('hs_gorunen_ad') ? hs_gorunen_ad($h) : ($h['ad'] ?? ''));
    if ($kim === '') $kim = trim((string)($e['yazar'] ?? ''));

    /* KARAR: bu uçtaki bütün tur karşılaştırmaları olduğu gibi bırakıldı.
       Hepsi YOL denetimidir: "bu çalışma hakemliğe açık mı" diye sorarlar,
       "hakemden geçti mi" diye değil. tg_hakemden_gecti() konsaydı rapor
       almamış ama zaten açık bir çalışma ikinci kez açılabilir, kapatma
       denetimi de "zaten açık değil" diyerek yanlış yere düşerdi. */
    if ($kapat) {
        if ((string)($e['tur'] ?? '') !== 'hakemli') {
            cikti(['ok' => false, 'hata' => 'Bu çalışma zaten hakemliğe açık değil.'], 409);
        }
        $hakemVar = false;
        foreach (tg_dizi($e['hakemler'] ?? null) as $hh) { if (is_array($hh)) { $hakemVar = true; break; } }
        if ($hakemVar) {
            cikti(['ok' => false, 'hata' => 'Bu çalışmaya hakem kaydı düşmüş; artık hakemlikten geri çekilemez. Hakemin emeği görünmez kılınamaz.'], 409);
        }
        $y[$i]['tur'] = 'yazi';
        $y[$i]['hakemlik_kaydi'][] = ['yon' => 'kapatildi', 'tarih' => $simdi, 'kim' => $kim];
        yaz_json('yazilar.json', $y);
        cikti(['ok' => true, 'tur' => 'yazi',
               'mesaj' => 'Çalışma hakemsiz yola alındı. Dilediğiniz zaman yeniden açabilirsiniz.']);
    }

    if ((string)($e['tur'] ?? '') === 'hakemli') {
        cikti(['ok' => false, 'hata' => 'Bu çalışma zaten hakemliğe açık.'], 409);
    }
    $y[$i]['tur'] = 'hakemli';
    $y[$i]['hakemlik_kaydi'][] = ['yon' => 'acildi', 'tarih' => $simdi, 'kim' => $kim];
    yaz_json('yazilar.json', $y);

    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'],
            "\xF0\x9F\x94\x8D Calisma hakemlige acildi\n" . (string)($y[$i]['baslik'] ?? '') . "\n" . $kim, 4);
    }
    cikti(['ok' => true, 'tur' => 'hakemli',
           'mesaj' => 'Çalışmanız hakemliğe açıldı. Artık hakem aranan çalışmalar listesinde görünüyor; gönüllü olan herkes okuyup rapor yazabilir.']);
}

if ($yol === '/yazar-duzeltme-iste' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    $ipk = 'yazar:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 30) cikti(['ok' => false, 'hata' => 'Çok fazla istek.'], 429);
    $metin = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['gerekce'] ?? ''))), 0, 3000);
    if (mb_strlen($metin, 'UTF-8') < 20) cikti(['ok' => false, 'hata' => 'Neyin, neden düzeltilmesi gerektiğini yazın.'], 400);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = yazar_yetki($y, $g);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Erişim bulunamadı.'], 404);
    if (isset($d['yetkisiz'])) cikti(['ok' => false, 'hata' => 'Bu çalışmanın yazarı olarak görünmüyorsunuz.'], 403);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'E-posta eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i] = $d;
    if (!is_array($y[$i]['duzeltme_istek'] ?? null)) $y[$i]['duzeltme_istek'] = [];
    if (count($y[$i]['duzeltme_istek']) >= 20) cikti(['ok' => false, 'hata' => 'Çok fazla istek gönderildi.'], 429);
    $y[$i]['duzeltme_istek'][] = [
        'kod' => substr(hash('sha256', microtime() . random_int(0, PHP_INT_MAX)), 0, 12),
        'gerekce' => $metin, 'durum' => 'bekliyor', 'tarih' => date('c'),
    ];
    yaz_json('yazilar.json', $y);
    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'],
            "\xE2\x9C\x8F\xEF\xB8\x8F Duzeltme istegi\n" . (string)($y[$i]['baslik'] ?? '') . "\n\n" . mb_substr($metin, 0, 400), 4);
    }
    cikti(['ok' => true, 'mesaj' => 'Düzeltme isteğiniz alındı. Editör değerlendirdikten sonra, kabul edilirse düzeltme kaydı olarak çalışmanın sayfasında görünecektir.']);
}

/* YAZAR HAKEM DAVETİ: yazar KENDİ makalesine hakem ekler, davet linkini alır */
if ($yol === '/yazar-hakem-davet' && $metod === 'POST') {
    /* =================================================================
       YAZAR HAKEM ÖNERİSİ
       -----------------------------------------------------------------
       BU UÇ ESKİDEN DAVET GÖNDERİYORDU, ARTIK ÖNERİ YAZIYOR
       (11 Ağustos 2026). Eskiden yazar adı girer girmez hakem kaydı
       oluşuyor ve davet iletisi doğrudan gidiyordu; araya kimse
       girmiyordu.

       Neden değişti. Hakemin bağımsızlığı, onu kimin çağırdığına
       bağlıdır. Yazarın kendi seçtiği ve kendi çağırdığı bir kişi,
       raporunu ne kadar dürüst yazarsa yazsın, dışarıdan bakan için
       bağımsız değildir. Bu sistemin tamamı "süreç görünür olsun" diye
       kuruldu; görünürlük, bağımsızlığın yerini tutmaz.

       Ne değişmedi. Yazarın söz hakkı duruyor: alanı en iyi bilen
       çoğu zaman yazarın kendisidir ve kimi önerdiği kayda geçer.
       Değişen yalnızca son sözün kimde olduğudur. Öneri editöre gider,
       editör kimliği bağımsız doğrular ve daveti kendisi gönderir.

       ADRES DEĞİŞMEDİ. Uç yolu aynı kaldı ki tarayıcıda önbelleğe
       alınmış eski betikler kırılmasın; yalnızca davranışı ve dönen
       ileti değişti.
       ================================================================= */
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'yhoner:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 60) cikti(['ok' => false, 'hata' => 'Çok fazla istek. Biraz sonra tekrar deneyin.'], 429);

    $temiz = fn($v, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)$v)), 0, $n);
    /* Eski betikler alanları hakem_ad / hakem_eposta diye gönderiyordu.
       Tarayıcıda bir yıl saklanan bir betiğin kırılmaması için iki ad da
       okunur; yeni betik sade adları kullanır. */
    $hAd    = $temiz(($g['ad'] ?? '') !== '' ? $g['ad'] : ($g['hakem_ad'] ?? ''), 120);
    $hMail  = mb_strtolower(trim((string)(($g['eposta'] ?? '') !== '' ? $g['eposta'] : ($g['hakem_eposta'] ?? ''))), 'UTF-8');
    $hKurum = $temiz($g['kurum'] ?? '', 220);
    $hOrcid = orcid_temiz((string)($g['orcid'] ?? ''));
    $hNeden = $temiz($g['gerekce'] ?? '', 1200);
    if ($hAd === '') cikti(['ok' => false, 'hata' => 'Hakem adı gerekli.'], 400);
    if ($hMail !== '' && !filter_var($hMail, FILTER_VALIDATE_EMAIL)) cikti(['ok' => false, 'hata' => 'E-posta geçersiz.'], 400);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = yazar_yetki($y, $g);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Erişim bağlantısı bulunamadı.'], 404);
    if (isset($d['yetkisiz'])) cikti(['ok' => false, 'hata' => 'Bu çalışmanın yazarı olarak görünmüyorsunuz.'], 403);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'Bu e-posta, erişimin tanımlandığı adresle eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i] = $d;

    if (yazar_kilitli($y[$i])) {
        cikti(['ok' => false, 'hata' => 'Makale iki ret kararı aldığı için yeni hakem önerisi gönderilemez.'], 403);
    }
    if (hakemlik_askida($hMail, $hAd)) {
        cikti(['ok' => false, 'hata' => 'Bu kişinin hakemlik yetkisi bir kurul oylamasıyla askıya alınmıştır.'], 403);
    }

    /* Zaten hakem mi */
    foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $h) {
        if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === tg_ad_anahtar($hAd)) {
            cikti(['ok' => false, 'hata' => 'Bu kişi zaten bu çalışmanın hakemleri arasında.'], 409);
        }
    }
    /* Zaten önerilmiş mi */
    if (!is_array($y[$i]['hakem_onerileri'] ?? null)) $y[$i]['hakem_onerileri'] = [];
    foreach ($y[$i]['hakem_onerileri'] as $o) {
        if (is_array($o) && tg_ad_anahtar((string)($o['ad'] ?? '')) === tg_ad_anahtar($hAd)
            && (string)($o['durum'] ?? '') === 'bekliyor') {
            cikti(['ok' => false, 'hata' => 'Bu kişiyi zaten önerdiniz; öneriniz editör kararını bekliyor.'], 409);
        }
    }
    $ust = (int)tg_ayar('yazar_oneri_ust', 6);
    $bekleyen = 0;
    foreach ($y[$i]['hakem_onerileri'] as $o) {
        if (is_array($o) && (string)($o['durum'] ?? '') === 'bekliyor') $bekleyen++;
    }
    if ($bekleyen >= $ust) {
        cikti(['ok' => false, 'hata' => 'Aynı anda en çok ' . $ust . ' öneri bekleyebilir. Editör mevcut önerileri karara bağlayınca yenisini ekleyebilirsiniz.'], 409);
    }

    /* Çıkar çatışması öneri anında da bakılır: editörün önüne baştan
       elenmiş bir ad gitmesin, yazar da neden olmadığını hemen öğrensin.
       Önerilen kişinin adresi de ölçüte girer: yazar, kendi adresini
       başka bir adla yazarak kendini önermeye kalkarsa buradan
       yakalanır. */
    $cak = tg_hakem_cakisma($y, $i, $hAd, $hOrcid, $hMail);
    if ($cak) cikti(['ok' => false, 'hata' => $cak['mesaj'], 'cakisma' => $cak['kod']], 409);

    $kod = substr(hash('sha256', $hAd . microtime() . random_int(0, PHP_INT_MAX)), 0, 12);
    $y[$i]['hakem_onerileri'][] = [
        'kod'     => $kod,
        'ad'      => $hAd,
        'kurum'   => $hKurum,
        'orcid'   => $hOrcid,
        /* Adres kayda girer ama yazarın önerisi olduğu için editöre
           gösterilmez; daveti sistem gönderir. */
        'eposta'  => $hMail,
        'gerekce' => $hNeden,
        'durum'   => 'bekliyor',          /* bekliyor | kabul | ret */
        'tarih'   => date('c'),
        'oneren'  => (string)($y[$i]['yazar'] ?? ''),
    ];
    yaz_json('yazilar.json', $y);

    /* Editörlere haber: öneri bekliyor. Telegram varsa oraya da düşer. */
    $ayarT = yonetim_ayar(); $tgT = (array)($ayarT['tg'] ?? []);
    if (!empty($tgT['token']) && !empty($tgT['chat'])) {
        tg_gonder((string)$tgT['token'], (string)$tgT['chat'],
            "\xF0\x9F\x91\xA4 Yazardan hakem onerisi\n" . $hAd . "\nCalisma: " . (string)($y[$i]['baslik'] ?? '')
            . "\n\nPanel: " . tg_url('/panel.php#yonetim'), 4);
    }

    cikti(['ok' => true, 'oneri' => ['ad' => $hAd, 'kod' => $kod, 'durum' => 'bekliyor'],
        'mesaj' => 'Öneriniz alındı ve editöre iletildi. Daveti editör gönderir; kararı size bildirilir. '
                 . 'Önerdiğiniz kişi kayda geçti ve çalışmanız yayımlandığında kimin önerdiği görünür kalır.']);
}

/* =====================================================================
   YAZAR HAKEME YANIT YAZAR
   ---------------------------------------------------------------------
   Rapor geldi, karar "revizyon" çıktı ve yazarın söyleyecek bir şeyi
   var. Bugüne kadar bunun gideceği bir yer yoktu. Artık var, ve
   kayıtlıdır: yayımlandığında raporun altında görünür.

   Sıra kuralı ortak.php'de (tg_diyalog_sira). Yazar üst üste iki not
   yazamaz; hakem yanıtlamadan yeni tur açılmaz.
   ===================================================================== */
if ($yol === '/yazar-hakem-yanit' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'ydiy:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 40) cikti(['ok' => false, 'hata' => 'Çok fazla istek. Biraz sonra tekrar deneyin.'], 429);

    $metin = trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? '')));
    $metin = mb_substr($metin, 0, 6000);
    $anahtar = mb_substr(trim((string)($g['hakem'] ?? '')), 0, 120);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = yazar_yetki($y, $g);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Erişim bağlantısı bulunamadı.'], 404);
    if (isset($d['yetkisiz'])) cikti(['ok' => false, 'hata' => 'Bu çalışmanın yazarı olarak görünmüyorsunuz.'], 403);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'Bu e-posta, erişimin tanımlandığı adresle eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i] = $d;

    $j = null;
    foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $jx => $h) {
        if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === tg_ad_anahtar($anahtar)) { $j = $jx; break; }
    }
    if ($j === null) cikti(['ok' => false, 'hata' => 'Bu çalışmada böyle bir hakem yok.'], 404);
    $h = $y[$i]['hakemler'][$j];

    $sira = tg_diyalog_sira($h);
    if ($sira === '') {
        $karar = (string)($h['karar'] ?? '');
        $neden = trim((string)($h['rapor'] ?? '')) === ''
            ? 'Bu hakem henüz rapor yazmadı; olmayan bir rapora yanıt yazılamaz.'
            : (($karar === 'kabul' || $karar === 'ret')
                ? 'Bu hakemin kararı verilmiş ve süreç kapanmıştır. Karara itirazınız varsa kurul oylaması yolu açıktır.'
                : 'Bu hakemle diyalog turlarınız doldu.');
        cikti(['ok' => false, 'hata' => $neden], 409);
    }
    if ($sira !== 'yazar') {
        cikti(['ok' => false, 'hata' => 'Sıra sizde değil: son notunuza hakem henüz yanıt vermedi.'], 409);
    }
    $asgari = (int)tg_ayar('diyalog_asgari', 80);
    if (mb_strlen($metin, 'UTF-8') < $asgari) {
        cikti(['ok' => false, 'hata' => 'Yanıtınız en az ' . $asgari . ' karakter olmalıdır. Hakemin okuyup değerlendirebileceği bir açıklama yazın.'], 400);
    }

    if (!is_array($y[$i]['hakemler'][$j]['diyalog'] ?? null)) $y[$i]['hakemler'][$j]['diyalog'] = [];
    $y[$i]['hakemler'][$j]['diyalog'][] = [
        'yon' => 'yazar', 'metin' => $metin, 'tarih' => date('c'),
        /* Hangi sürüme ilişkin yazıldığı: metin sonradan değişirse
           yanıtın neye ilişkin olduğu yine de okunabilsin. */
        'surum' => tg_metin_ozeti($y[$i]),
    ];
    yaz_json('yazilar.json', $y);

    /* Hakeme haber: kararını vermeden önce görsün. */
    $hm = (string)($h['eposta_acik'] ?? '');
    if ($hm !== '') {
        $marka = (string)tg_ayar('marka', 'Kutadgu');
        eposta_gonder($hm, $marka . ' | Yazardan yanıt geldi',
            "Sayın " . (string)($h['ad'] ?? '') . ",\n\n" .
            "Değerlendirdiğiniz çalışmanın yazarı raporunuza yazılı bir yanıt verdi:\n\n" .
            "    \"" . (string)($y[$i]['baslik'] ?? '') . "\"\n\n" .
            "Yanıtı değerlendirme sayfanızda görebilir, dilerseniz kendi karşılığınızı yazabilirsiniz. " .
            "Yazışmanın tamamı çalışmayla birlikte kalıcı olarak yayımlanır.\n\n" .
            tg_url('/hakem.php?t=' . (string)($h['token'] ?? '')) . "\n\n" .
            $marka . "\n" . tg_kok() . "\n");
    }

    cikti(['ok' => true, 'sira' => 'hakem',
        'mesaj' => 'Yanıtınız kayda geçti ve hakeme iletildi. Hakem kararını vermeden önce bunu görecek; yazışma çalışmanızla birlikte kalıcı olarak yayımlanır.']);
}

/* =====================================================================
   HAKEM YAZARIN YANITINA KARŞILIK YAZAR
   ---------------------------------------------------------------------
   Hakem, kararını değiştirmek zorunda değildir. Bu kanal ikna etmek
   için değil, yanlış anlaşılmayı düzeltmek içindir; karşılık yazmak da
   zorunlu değildir.
   ===================================================================== */
if ($yol === '/hakem-yazar-yanit' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $t = preg_replace('/[^a-f0-9]/', '', (string)($g['t'] ?? ''));
    $mail = mb_strtolower(trim((string)($g['mail'] ?? '')), 'UTF-8');
    $sifre = (string)($g['sifre'] ?? '');
    $ipk = 'hdiy:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 40) cikti(['ok' => false, 'hata' => 'Çok fazla istek.'], 429);
    $metin = trim(preg_replace('#<[^>]*>#', '', (string)($g['metin'] ?? '')));
    $metin = mb_substr($metin, 0, 6000);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $d = hakem_dogrula($y, $t, $mail, $sifre);
    if ($d === null) cikti(['ok' => false, 'hata' => 'Bağlantı bulunamadı.'], 404);
    if (isset($d['eposta_yanlis'])) cikti(['ok' => false, 'hata' => 'Bu e-posta, bağlantının gönderildiği adresle eşleşmiyor.'], 403);
    if (isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Şifre hatalı.'], 403);
    [$i, $j] = $d;
    $h = $y[$i]['hakemler'][$j];

    $sira = tg_diyalog_sira($h);
    if ($sira !== 'hakem') {
        cikti(['ok' => false, 'hata' => $sira === 'yazar'
            ? 'Yanıtlanacak bir yazar notu yok.'
            : 'Bu çalışmada diyalog kanalı kapalı.'], 409);
    }
    $asgari = (int)tg_ayar('diyalog_asgari', 80);
    if (mb_strlen($metin, 'UTF-8') < $asgari) {
        cikti(['ok' => false, 'hata' => 'Karşılığınız en az ' . $asgari . ' karakter olmalıdır.'], 400);
    }

    $y[$i]['hakemler'][$j]['diyalog'][] = [
        'yon' => 'hakem', 'metin' => $metin, 'tarih' => date('c'),
        'surum' => tg_metin_ozeti($y[$i]),
    ];
    yaz_json('yazilar.json', $y);

    /* Yazara haber. */
    $adresler = [];
    $ym = (string)($y[$i]['yazar_erisim']['eposta_acik'] ?? '');
    if ($ym !== '') $adresler[] = $ym;
    $yb = is_array($y[$i]['yazar_bilgi'] ?? null) ? $y[$i]['yazar_bilgi'] : [];
    $ym2 = mb_strtolower(trim((string)($yb['eposta'] ?? '')), 'UTF-8');
    if ($ym2 !== '' && !in_array($ym2, $adresler, true)) $adresler[] = $ym2;
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    foreach ($adresler as $adr) {
        eposta_gonder($adr, $marka . ' | Hakemden karşılık geldi',
            "Sayın " . (string)($y[$i]['yazar'] ?? '') . ",\n\n" .
            "\"" . (string)($y[$i]['baslik'] ?? '') . "\" başlıklı çalışmanızın hakemlerinden biri, " .
            "yazdığınız yanıta karşılık verdi. Yazar panelinizden okuyabilirsiniz.\n\n" .
            $marka . "\n" . tg_kok() . "\n");
    }

    cikti(['ok' => true, 'sira' => tg_diyalog_sira($y[$i]['hakemler'][$j]),
        'mesaj' => 'Karşılığınız kayda geçti ve yazara iletildi.']);
}

/* =====================================================================
   EDİTÖR: YAZAR ÖNERİSİNİ KARARA BAĞLAR
   ---------------------------------------------------------------------
   Kabul edilirse hakem kaydı burada oluşur ve davet sistemden gider;
   yazarın eline hiçbir zaman erişim bilgisi geçmez. Kayıtta hem öneren
   hem karar veren yazılıdır: okur, hakemi kimin önerdiğini ve kimin
   onayladığını ayrı ayrı görür.
   ===================================================================== */
if ($yol === '/editor/oneri-karar' && $metod === 'POST') {
    $ed = ed_kimlik();
    $g = govde_json(); if (!$g) $g = $_POST;
    $id    = (string)($g['id'] ?? '');
    $kod   = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    $karar = (string)($g['karar'] ?? '');
    $neden = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['neden'] ?? ''))), 0, 800);
    if (!in_array($karar, ['kabul', 'ret'], true)) cikti(['ok' => false, 'hata' => 'Kararı belirtin.'], 400);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = null;
    foreach ($y as $ix => $e) { if ((string)($e['id'] ?? '') === $id) { $i = $ix; break; } }
    if ($i === null) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);

    /* Editör kendi çalışmasının önerisini karara bağlayamaz. */
    $edKey = tg_ad_anahtar($ed['ad']);
    if ($edKey !== '' && in_array($edKey, tg_yazar_anahtarlari($y[$i]), true)) {
        cikti(['ok' => false, 'hata' => 'Bir editör kendi çalışmasının hakem önerisini karara bağlayamaz.'], 403);
    }

    $j = null;
    foreach (($y[$i]['hakem_onerileri'] ?? []) as $jx => $o) {
        if (is_array($o) && (string)($o['kod'] ?? '') === $kod) { $j = $jx; break; }
    }
    if ($j === null) cikti(['ok' => false, 'hata' => 'Öneri bulunamadı.'], 404);
    if ((string)($y[$i]['hakem_onerileri'][$j]['durum'] ?? '') !== 'bekliyor') {
        cikti(['ok' => false, 'hata' => 'Bu öneri daha önce karara bağlanmış.'], 409);
    }
    $one = $y[$i]['hakem_onerileri'][$j];

    if ($karar === 'kabul') {
        $hAd   = (string)($one['ad'] ?? '');
        $hMail = (string)($one['eposta'] ?? '');
        if (hakemlik_askida($hMail, $hAd)) {
            cikti(['ok' => false, 'hata' => 'Bu kişinin hakemlik yetkisi askıya alınmıştır.'], 403);
        }
        foreach (tg_dizi($y[$i]['hakemler'] ?? null) as $h) {
            if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === tg_ad_anahtar($hAd)) {
                cikti(['ok' => false, 'hata' => 'Bu kişi zaten hakem olarak eklenmiş.'], 409);
            }
        }
        /* count() dizge alırsa PHP 8'de uyarı değil ölümcül hata verir;
           alan bozuk gelmişse sayı sıfır sayılır ve akış sürer. */
        if (count(tg_dizi($y[$i]['hakemler'] ?? null)) >= 12) cikti(['ok' => false, 'hata' => 'En fazla 12 hakem eklenebilir.'], 400);
        /* Öneri ile karar arasında zaman geçer: çakışma yeniden bakılır.
           Öneride yazılı adres de ölçüte girer; $hMail yukarıda zaten
           öneri kaydından okundu. */
        $cak = tg_hakem_cakisma($y, $i, $hAd, (string)($one['orcid'] ?? ''), $hMail);
        if ($cak) cikti(['ok' => false, 'hata' => $cak['mesaj'], 'cakisma' => $cak['kod']], 409);

        $hToken = substr(hash('sha256', $hAd . microtime() . random_int(0, PHP_INT_MAX)), 0, 32);
        $hSifre = uret_sifre();
        $davetT = substr(hash('sha256', 'davet' . $hToken . microtime() . random_int(0, PHP_INT_MAX)), 0, 32);
        if (!is_array($y[$i]['hakemler'] ?? null)) $y[$i]['hakemler'] = [];
        $y[$i]['hakemler'][] = [
            'ad' => $hAd, 'token' => $hToken, 'davet_token' => $davetT,
            'eposta_hash' => $hMail !== '' ? hash('sha256', $hMail . '|hakem') : '',
            'eposta_acik' => $hMail, 'sifre' => $hSifre,
            'davet_durum' => 'bekliyor', 'davet_tarih' => date('c'),
            'karar' => '', 'rapor' => '', 'tarih' => '', 'dosya' => '',
            'profil' => ['kurum' => (string)($one['kurum'] ?? ''), 'orcid' => (string)($one['orcid'] ?? '')],
            'raporlar' => [],
            /* Öneren ve onaylayan ayrı ayrı yazılır. Hakem "bağımsız"
               sayılmaz: adı yazarın önerisiyle gelmiştir ve okur bunu
               görür. Değişen, davetin kimden gittiğidir. */
            'atayan' => [
                'tur'    => 'editor',
                'ad'     => (string)$ed['ad'],
                'tarih'  => date('c'),
                'kaynak' => 'yazar_onerisi',
                'oneren' => (string)($one['oneren'] ?? ''),
            ],
        ];
        $y[$i]['hakem_onerileri'][$j]['hakem_token'] = $hToken;
    }

    $y[$i]['hakem_onerileri'][$j]['durum']        = $karar;
    $y[$i]['hakem_onerileri'][$j]['karar_tarih']  = date('c');
    $y[$i]['hakem_onerileri'][$j]['karar_veren']  = (string)$ed['ad'];
    $y[$i]['hakem_onerileri'][$j]['karar_neden']  = $neden;
    yaz_json('yazilar.json', $y);

    /* Kabulde davet iletisi sistemden gider. */
    if ($karar === 'kabul' && (string)($one['eposta'] ?? '') !== '') {
        $marka   = (string)tg_ayar('marka', 'Kutadgu');
        $kokAdr  = tg_kok();
        $davetUrl = tg_url('/davet.php?d=' . $davetT);
        $hakemUrl = tg_url('/hakem.php?t=' . $hToken);
        $mBaslik  = (string)($y[$i]['baslik'] ?? '');
        $govde =
            "Sayın " . (string)($one['ad'] ?? '') . ",\n\n" .
            "Bu ileti " . $marka . " adlı açık erişimli akademik yayın sisteminden gönderilmektedir (" . $kokAdr . ").\n\n" .
            "Aşağıdaki çalışmayı değerlendirmeniz için editörlüğümüzce hakem olarak davet edildiniz:\n\n" .
            "    \"" . $mBaslik . "\"\n\n" .
            "Adınız çalışmanın yazarı tarafından önerilmiş, daveti editör kararıyla tarafımızdan gönderilmiştir. " .
            "Bu ayrım kayda geçer ve çalışma yayımlandığında görünür kalır.\n\n" .
            "Bu sistemde kör hakemlik uygulanmaz. Raporunuz, kararınız ve adınız çalışmayla birlikte açıkça yayımlanır.\n\n" .
            "Hakemlik gönüllüdür ve ücretlendirilmez. Kabul etmek zorunda değilsiniz.\n\n" .
            "Daveti görüntülemek ve kararınızı bildirmek için:\n" . $davetUrl . "\n\n" .
            "Kabul etmeniz hâlinde değerlendirme sayfanıza bu adresten ulaşırsınız:\n" . $hakemUrl . "\n" .
            "Erişim şifreniz: " . $hSifre . "\n\n" .
            "İlginiz için teşekkür ederiz.\n" . $marka . "\n" . $kokAdr . "\n";
        eposta_gonder((string)$one['eposta'], $marka . ' hakem daveti: ' . mb_substr($mBaslik, 0, 90), $govde);
    }

    cikti(['ok' => true, 'durum' => $karar, 'mesaj' => $karar === 'kabul'
        ? 'Öneri kabul edildi; davet gönderildi.'
        : 'Öneri reddedildi ve gerekçesiyle kayda geçti.']);
}

/* =====================================================================
   HAKEM DAVETİ: görüntüle ve yanıtla
   Hakemlik zorunlu değildir. Ret hâlinde hakeme özür iletisi, yazara
   bildirim gider; davet kapanır ve yerine başka hakem çağrılabilir.
   ===================================================================== */
/* Davetin süresi doldu mu?
   Bir davet sonsuza kadar açık kalamaz: cevapsız bir davet, yazarın
   çalışmasını sessizce bekletir. Süre ayarlıdır (hakem_davet_gun,
   öntanımlı 10 gün) ve baş editörler değiştirebilir. Süresi dolan
   davet reddedilmiş sayılmaz; yalnızca düşer, kimsenin kaydına
   olumsuz bir şey yazılmaz. */
function davet_suresi_doldu(array $h): bool {
    if ((string)($h['davet_durum'] ?? '') !== 'bekliyor') return false;
    $t = strtotime((string)($h['davet_tarih'] ?? ''));
    if (!$t) return false;
    $gun = function_exists('tg_davet_gun') ? tg_davet_gun() : 10;
    return (time() - $t) > ($gun * 86400);
}

function davet_bul(array $y, string $d): ?array {
    if ($d === '') return null;
    foreach ($y as $i => $e) {
        foreach (tg_dizi($e['hakemler'] ?? null) as $j => $h) {
            if (is_array($h) && hash_equals((string)($h['davet_token'] ?? ''), $d)) return [$i, $j];
        }
    }
    return null;
}

if ($yol === '/hakem-davet' && $metod === 'GET') {
    $d = preg_replace('/[^a-f0-9]/', '', (string)($_GET['d'] ?? ''));
    $ipk = 'davet:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 60) cikti(['ok' => false, 'hata' => 'Çok fazla istek.'], 429);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $b = davet_bul($y, $d);
    if ($b === null) cikti(['ok' => false, 'hata' => 'Davet bulunamadı.'], 404);
    [$i, $j] = $b; $e = $y[$i]; $h = $e['hakemler'][$j];
    $doldu = davet_suresi_doldu($h);
    cikti(['ok' => true,
        'hakem'  => (string)($h['ad'] ?? ''),
        'durum'  => $doldu ? 'suresi_doldu' : (string)($h['davet_durum'] ?? 'bekliyor'),
        'gun'    => function_exists('tg_davet_gun') ? tg_davet_gun() : 10,
        'tarih'  => (string)($h['davet_tarih'] ?? ''),
        'baslik' => (string)($e['baslik'] ?? ''),
        'ozet'   => mb_substr(strip_tags((string)($e['ozet'] ?? '')), 0, 1200),
        'yazar'  => (string)($e['yazar'] ?? ''),
        'alan'   => (string)($e['alan'] ?? ''),
        'link'   => (function_exists('tg_url') ? tg_url('/hakem.php?t=' . (string)($h['token'] ?? '')) : ''),
    ]);
}

if ($yol === '/hakem-davet-yanit' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $d = preg_replace('/[^a-f0-9]/', '', (string)($g['d'] ?? ''));
    $yanit = (string)($g['yanit'] ?? '');
    $neden = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['neden'] ?? ''))), 0, 600);
    if (!in_array($yanit, ['kabul', 'ret'], true)) cikti(['ok' => false, 'hata' => 'Geçersiz yanıt.'], 400);
    $ipk = 'davet:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 60) cikti(['ok' => false, 'hata' => 'Çok fazla istek.'], 429);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $b = davet_bul($y, $d);
    if ($b === null) cikti(['ok' => false, 'hata' => 'Davet bulunamadı.'], 404);
    [$i, $j] = $b;
    if ((string)($y[$i]['hakemler'][$j]['davet_durum'] ?? '') !== 'bekliyor') {
        cikti(['ok' => false, 'hata' => 'Bu davet zaten yanıtlandı.'], 409);
    }
    if (davet_suresi_doldu($y[$i]['hakemler'][$j])) {
        cikti(['ok' => false, 'hata' => 'Bu davetin süresi doldu. İlgilenmeniz bizi sevindirir; editöre yazarsanız davet yenilenebilir.'], 410);
    }
    $y[$i]['hakemler'][$j]['davet_durum'] = $yanit;
    $y[$i]['hakemler'][$j]['davet_yanit_tarih'] = date('c');
    if ($yanit === 'ret') $y[$i]['hakemler'][$j]['davet_neden'] = $neden;
    yaz_json('yazilar.json', $y);

    $marka   = function_exists('tg_ayar') ? (string)tg_ayar('marka', 'Kutadgu') : 'Kutadgu';
    $kokAdr  = function_exists('tg_kok') ? tg_kok() : '';
    $hAd     = (string)($y[$i]['hakemler'][$j]['ad'] ?? '');
    $hMail   = (string)($y[$i]['hakemler'][$j]['eposta_acik'] ?? '');
    $mBaslik = (string)($y[$i]['baslik'] ?? '');
    $yMail   = (string)($y[$i]['yazar_erisim']['eposta_acik'] ?? '');
    $yazarAd = (string)($y[$i]['yazar'] ?? '');
    $hLink   = function_exists('tg_url') ? tg_url('/hakem.php?t=' . (string)($y[$i]['hakemler'][$j]['token'] ?? '')) : '';

    if ($yanit === 'ret') {
        if ($hMail !== '') {
            eposta_gonder($hMail, $marka . ' | Anlayışınız için teşekkür ederiz',
                "Sayın " . $hAd . ",\n\n" .
                "Hakemlik davetimize verdiğiniz yanıt için teşekkür ederiz. Sizi rahatsız ettiysek özür dileriz; " .
                "hakemlik tümüyle gönüllüdür ve reddetmek en doğal hakkınızdır.\n\n" .
                "Bu davet kapatılmıştır; bundan sonra bu çalışmayla ilgili size başka bir ileti gönderilmeyecektir.\n\n" .
                "İyi çalışmalar dileriz.\n" . $marka . "\n" . $kokAdr . "\n");
        }
        if ($yMail !== '') {
            eposta_gonder($yMail, $marka . ' | Hakem daveti yanıtlandı',
                "Sayın " . $yazarAd . ",\n\n" .
                "\"" . $mBaslik . "\" başlıklı çalışmanız için davet ettiğiniz " . $hAd . ", " .
                "şu an için değerlendirme yapamayacağını bildirdi.\n\n" .
                ($neden !== '' ? ("Belirttiği gerekçe:\n" . $neden . "\n\n") : '') .
                "Hakemlik gönüllü olduğu için bu olağan bir yanıttır. Yazar panelinizden başka bir hakem davet edebilir, " .
                "ya da hakem havuzundan uzmanlık alanına göre arama yapabilirsiniz.\n\n" .
                $marka . "\n" . $kokAdr . "\n");
        }
    } else {
        if ($hMail !== '') {
            eposta_gonder($hMail, $marka . ' | Hakemlik davetini kabul ettiniz',
                "Sayın " . $hAd . ",\n\n" .
                "Daveti kabul ettiğiniz için teşekkür ederiz.\n\n" .
                "Değerlendirme sayfanız:\n" . $hLink . "\n" .
                "Erişim şifreniz davet iletisinde yer almaktadır.\n\n" .
                "Raporunuz ve kararınız, adınızla birlikte çalışmanın sayfasında yayımlanacaktır.\n\n" .
                $marka . "\n" . $kokAdr . "\n");
        }
        if ($yMail !== '') {
            eposta_gonder($yMail, $marka . ' | Hakem davetiniz kabul edildi',
                "Sayın " . $yazarAd . ",\n\n" .
                "\"" . $mBaslik . "\" başlıklı çalışmanız için davet ettiğiniz " . $hAd . " daveti kabul etti " .
                "ve değerlendirme sürecine başladı.\n\n" . $marka . "\n" . $kokAdr . "\n");
        }
    }

    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'],
            "Hakem daveti " . ($yanit === 'kabul' ? 'KABUL' : 'RET') . ": " . $hAd . "\n" . mb_substr($mBaslik, 0, 160), 4);
    }
    cikti(['ok' => true, 'durum' => $yanit, 'link' => $yanit === 'kabul' ? $hLink : '']);
}

/* =====================================================================
   HAKEM HAVUZU: sistemde rapor yazmış hakemler, uzmanlık ve anahtar
   kelimelerine göre aranabilir. İletişim bilgileri gösterilmez.
   ===================================================================== */
if ($yol === '/hakem-havuz' && $metod === 'GET') {
    $q = mb_strtolower(trim((string)($_GET['q'] ?? '')), 'UTF-8');
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $havuz = [];
    foreach ($y as $e) {
        $anah = array_filter(array_map('trim', preg_split('/[,;]+/u', tg_metin($e['anahtar'] ?? '') . ',' . tg_metin($e['anahtar_en'] ?? ''))));
        foreach (tg_dizi($e['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $ad = trim((string)($h['ad'] ?? '')); if ($ad === '') continue;
            $k = mb_strtolower($ad, 'UTF-8');
            if (!isset($havuz[$k])) {
                $pf = is_array($h['profil'] ?? null) ? $h['profil'] : [];
                $havuz[$k] = ['ad' => $ad, 'unvan' => (string)($pf['unvan'] ?? ''), 'kurum' => (string)($pf['kurum'] ?? ''),
                              'orcid' => (string)($pf['orcid'] ?? ''), 'rapor' => 0, 'anahtar' => [], 'alan' => []];
            }
            $havuz[$k]['rapor']++;
            foreach ($anah as $a) { if ($a !== '' && !in_array($a, $havuz[$k]['anahtar'], true)) $havuz[$k]['anahtar'][] = $a; }
            $al = (string)($e['alan'] ?? '');
            if ($al !== '' && !in_array($al, $havuz[$k]['alan'], true)) $havuz[$k]['alan'][] = $al;
        }
    }
    foreach ($havuz as $k => $v) { $havuz[$k]['anahtar'] = array_slice($v['anahtar'], 0, 18); }
    $liste = array_values($havuz);
    if ($q !== '') {
        $liste = array_values(array_filter($liste, function ($v) use ($q) {
            $metin = mb_strtolower($v['ad'] . ' ' . $v['kurum'] . ' ' . implode(' ', $v['anahtar']), 'UTF-8');
            return mb_strpos($metin, $q) !== false;
        }));
    }
    usort($liste, fn($a, $b) => $b['rapor'] <=> $a['rapor']);
    cikti(['ok' => true, 'hakemler' => array_slice($liste, 0, 60)]);
}

/* =====================================================================
   KİŞİ KARTI
   ---------------------------------------------------------------------
   Bir ada tıklamadan önce o kişinin kim olduğunu görmek, sayfayı terk
   etmeden okumayı sürdürmeyi sağlar. Kart yalnızca zaten herkese açık
   olan bilgiyi taşır: ad, unvan, kurum, roller ve sayımlar. E-posta
   hiçbir koşulda yer almaz.
   ===================================================================== */
if ($yol === '/kisi-kart' && $metod === 'GET') {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($_GET['y'] ?? '')));
    if ($slug === '') cikti(['ok' => false, 'hata' => 'Kişi belirtilmedi.'], 400);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $anahtar = tg_slug_anahtar($slug);
    if ($anahtar === '') cikti(['ok' => false, 'hata' => 'Kişi bulunamadı.'], 404);

    $k = tg_kisi_kaydi($y, $anahtar);

    /* Hesap varsa fotoğraf, unvan ve kurum oradan tamamlanır: kişinin
       kendi yazdığı bilgi, kayıtlardan çıkarılana yeğlenir. */
    $hesap = null;
    foreach (hs_oku() as $h) {
        if (is_array($h) && tg_ad_anahtar((string)($h['ad'] ?? '')) === $anahtar) { $hesap = $h; break; }
    }
    $unvan = $hesap !== null && trim((string)($hesap['unvan'] ?? '')) !== ''
        ? hs_unvan_ad((string)$hesap['unvan']) : tg_unvan_ad((string)($k['unvan'] ?? ''));
    $roller = [];
    if ($k['yazarlik']) $roller[] = 'yazar';
    if ($k['hakemlik']) $roller[] = 'hakem';
    if ($hesap !== null) {
        foreach (hs_rolleri($hesap) as $r) { if (in_array($r, ['editor', 'bas_editor'], true)) $roller[] = $r; }
    }
    $kodlar = $hesap !== null ? al_kayit_kodlari($hesap) : [];

    cikti(['ok' => true, 'kisi' => [
        'ad'      => (string)($k['ad'] ?? ''),
        'unvan'   => $unvan,
        'kurum'   => (string)($k['kurum'] ?? ($hesap['kurum'] ?? '')),
        'orcid'   => (string)($k['orcid'] ?? ''),
        'resim'   => $hesap !== null ? tg_resim_yolu($hesap) : '',
        'yol'     => tg_kisi_yolu((string)($k['ad'] ?? '')),
        'roller'  => array_values(array_unique($roller)),
        'yazarlik'=> count($k['yazarlik']),
        'hakemlik'=> count($k['hakemlik']),
        'serh'    => count($k['serhler']),
        'alanlar' => array_map(fn($c) => al_ad($c), array_slice($kodlar, 0, 3)),
        'ilk'     => substr((string)($k['ilk'] ?? ''), 0, 4),
    ]]);
}

/* =====================================================================
   HAKEM DİZİNİ
   ---------------------------------------------------------------------
   Bu sistemde kör hakemlik yoktur; hakemin adı zaten çalışmayla birlikte
   yayımlanır. O hâlde hakemlerin kim olduğu da açık olmalıdır: dizin
   herkese açıktır. Görünen tek şey kişinin kendi verdiği bilgidir; adres
   hiçbir koşulda gösterilmez. İsteyen kendini dizinden çıkarabilir
   (hesap ayarlarındaki tek onay kutusu), çıkması hakemliğini etkilemez.

   Dizindeki sıra, sorulan alana yakınlığa göredir:
     3 - tam kod eşleşmesi
     2 - aynı alt alan (aradisipliner dallarda üst kümeleri kesişiyorsa)
     1 - aynı ana alan
   Eşitlik hâlinde yazdığı rapor sayısına bakılır.
   ===================================================================== */

/* Bir hesabın dizin kimliği: adres yerine kullanılan, adresten
   türetilmiş ama adresi ele vermeyen sabit bir anahtar. Editör hakem
   atarken bu anahtarı gönderir; adresi sunucu çözer. */
function dizin_kimlik(string $eposta): string {
    return substr(hash('sha256', mb_strtolower(trim($eposta), 'UTF-8') . '|dizin'), 0, 16);
}
function dizin_hesap(string $dk): ?array {
    foreach (hs_oku() as $h) {
        if (!is_array($h)) continue;
        if (hash_equals(dizin_kimlik((string)($h['eposta'] ?? '')), $dk)) return $h;
    }
    return null;
}

/* Rapor sayıları: kim kaç rapor yazmış (ada göre) */
function dizin_rapor_sayilari(array $y): array {
    $s = [];
    foreach ($y as $e) {
        foreach (tg_dizi($e['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $k = tg_ad_anahtar((string)($h['ad'] ?? ''));
            if ($k === '') continue;
            $s[$k] = ($s[$k] ?? 0) + 1;
        }
    }
    return $s;
}

/* Dizinde görünecek hesaplar */
function dizin_liste(): array {
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $rapor = dizin_rapor_sayilari($y);
    $out = [];
    foreach (hs_oku() as $h) {
        if (!is_array($h)) continue;
        if (!empty($h['dizin_gizli'])) continue;                 /* kişi kendini çıkarmış */
        $ad = trim((string)($h['ad'] ?? ''));
        if ($ad === '') continue;
        $roller = hs_rolleri($h);
        $dd = tg_dogrulama_durum($h['dogrulama'] ?? null);
        /* Dizinde yalnızca hakemlik yapabilecek durumda olanlar durur:
           belgesi onaylanmış olanlar ile kurulun kendisi. Aday hakem
           listede görünmez; henüz hakem değildir. */
        $hakem = $dd['durum'] === 'onayli'
              || in_array('hakem', $roller, true)
              || in_array('editor', $roller, true)
              || in_array('bas_editor', $roller, true);
        if (!$hakem) continue;
        $kodlar = al_kayit_kodlari($h);
        $out[] = [
            'dk'       => dizin_kimlik((string)($h['eposta'] ?? '')),
            'ad'       => $ad,
            'gorunen'  => hs_gorunen_ad($h),
            'unvan'    => hs_unvan_ad((string)($h['unvan'] ?? '')),
            'kurum'    => (string)($h['kurum'] ?? ''),
            'orcid'    => (string)($h['orcid'] ?? ''),
            'resim'    => tg_resim_yolu($h),
            'kisi_yolu'=> tg_kisi_yolu($ad),
            'alanlar'  => $kodlar,
            'alan_ad'  => array_map(fn($k) => al_ad($k), $kodlar),
            'rapor'    => (int)($rapor[tg_ad_anahtar($ad)] ?? 0),
            'roller'   => $roller,
        ];
    }
    return $out;
}

/* Bir listeyi verilen alan kodlarına yakınlığa göre sırala */
function dizin_sirala(array $liste, array $kodlar): array {
    foreach ($liste as $i => $k) {
        $en = 0;
        foreach ($kodlar as $a) {
            foreach ($k['alanlar'] as $b) { $y = al_yakinlik($a, $b); if ($y > $en) $en = $y; }
        }
        $liste[$i]['yakinlik'] = $en;
    }
    usort($liste, function ($a, $b) {
        return ($b['yakinlik'] <=> $a['yakinlik'])
            ?: ($b['rapor'] <=> $a['rapor'])
            ?: strcmp(tg_ad_anahtar($a['ad']), tg_ad_anahtar($b['ad']));
    });
    return $liste;
}

/* Herkese açık dizin */
if ($yol === '/hakem-dizin' && $metod === 'GET') {
    $q     = mb_strtolower(trim((string)($_GET['q'] ?? '')), 'UTF-8');
    $kodlar = al_kodlar(array_filter(array_map('trim', explode(',', (string)($_GET['alan'] ?? '')))));
    $liste = dizin_liste();
    if ($q !== '') {
        $liste = array_values(array_filter($liste, function ($k) use ($q) {
            $m = mb_strtolower($k['ad'] . ' ' . $k['kurum'] . ' ' . implode(' ', $k['alan_ad']), 'UTF-8');
            return mb_strpos($m, $q) !== false;
        }));
    }
    if ($kodlar) {
        $liste = dizin_sirala($liste, $kodlar);
        /* Alan sorulduysa hiç ilgisi olmayanlar listelenmez: sorulan
           soru "bu alanda kim var" sorusudur. */
        $liste = array_values(array_filter($liste, fn($k) => (int)$k['yakinlik'] > 0));
    } else {
        usort($liste, fn($a, $b) => ($b['rapor'] <=> $a['rapor']) ?: strcmp(tg_ad_anahtar($a['ad']), tg_ad_anahtar($b['ad'])));
    }
    /* Dizin kimliği yalnızca editöre gerekir; herkese açık yanıtta yer almaz. */
    $acik = array_map(function ($k) { unset($k['dk']); return $k; }, $liste);
    cikti(['ok' => true, 'toplam' => count($acik), 'hakemler' => array_slice($acik, 0, 200)]);
}

/* Editöre öneri: bir çalışmaya uygun hakemler, yakınlığa göre sıralı */
if ($yol === '/editor/hakem-oner' && $metod === 'GET') {
    $ed = ed_kimlik();
    $id = (string)($_GET['id'] ?? '');
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $is = null;
    foreach ($y as $e) { if ((string)($e['id'] ?? '') === $id) { $is = $e; break; } }
    if ($is === null) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);

    $kodlar = al_kayit_kodlari($is);
    $liste  = dizin_sirala(dizin_liste(), $kodlar);

    /* ---- ARAMA: ad, ORCID ya da e-posta ----
       Alan yakınlığına göre sıralı liste çoğu zaman yeter, ama editör
       aklındaki kişiyi arıyorsa altmış kaydı gözle taramak zorunda
       kalmamalı. Üç yolla aranır ve hangisi olduğu sorgudan anlaşılır:
       ORCID biçimindeyse ORCID, içinde @ varsa e-posta, değilse ad ve
       kurum içinde geçme.

       E-POSTA ARAMASI BİR ADRES DÖNDÜRMEZ. Yalnızca editörün yazdığı
       adresin karşılığı olan kişiyi bulur; eşleşme özet üzerinden
       yapılır ve sonuçta yine adres değil dizin kimliği döner. Böylece
       bu uç, adres toplamanın bir yolu hâline gelmez. Zaten yalnızca
       editör yetkisi olanlara açıktır. */
    $ara = trim((string)($_GET['ara'] ?? ''));
    if ($ara !== '') {
        $orcidAra = orcid_temiz($ara);
        $epostaAra = (strpos($ara, '@') !== false) ? hs_eposta_anahtar($ara) : '';
        $adAra = tg_ad_anahtar($ara);
        $duz = mb_strtolower($ara, 'UTF-8');

        /* E-posta aramasında kişiyi hesap kayıtlarından buluruz; dizin
           listesi adres taşımaz ve taşımamalıdır. */
        $epostaDk = '';
        if ($epostaAra !== '') {
            foreach (hs_oku() as $hh) {
                if (!is_array($hh)) continue;
                if (hs_eposta_anahtar((string)($hh['eposta'] ?? '')) !== $epostaAra) continue;
                $epostaDk = dizin_kimlik((string)($hh['eposta'] ?? ''));
                break;
            }
        }

        $liste = array_values(array_filter($liste, function ($k) use ($orcidAra, $epostaDk, $adAra, $duz) {
            if ($epostaDk !== '') return (string)($k['dk'] ?? '') === $epostaDk;
            if ($orcidAra !== '') return orcid_temiz((string)($k['orcid'] ?? '')) === $orcidAra;
            if ($adAra !== '' && strpos(tg_ad_anahtar((string)$k['ad']), $adAra) !== false) return true;
            return mb_strpos(mb_strtolower((string)($k['kurum'] ?? ''), 'UTF-8'), $duz) !== false;
        }));
    }

    /* Elenecekler: çalışmanın yazarları, hâlihazırda davet edilmiş
       hakemler ve hakemliği askıya alınmış kişiler. Bunlar bir öneri
       listesinde görünmemelidir; editörün eli boşuna gitmesin. */
    $yazarlar = tg_yazar_anahtarlari($is);
    $davetli  = [];
    foreach (tg_dizi($is['hakemler'] ?? null) as $h) {
        if (is_array($h)) $davetli[] = tg_ad_anahtar((string)($h['ad'] ?? ''));
    }
    $liste = array_values(array_filter($liste, function ($k) use ($yazarlar, $davetli) {
        $a = tg_ad_anahtar($k['ad']);
        if (in_array($a, $yazarlar, true)) return false;
        if (in_array($a, $davetli, true)) return false;
        if (function_exists('hakemlik_askida') && hakemlik_askida('', $k['ad'])) return false;
        return true;
    }));

    cikti(['ok' => true,
           'calisma' => ['id' => $id, 'baslik' => (string)($is['baslik'] ?? ''),
                         'alanlar' => $kodlar,
                         'alan_ad' => array_map(fn($k) => al_yol($k), $kodlar)],
           /* Arama terimi yankılanmaz: istemci ne yazdığını zaten bilir
              ve e-posta ile arandığında o adresin yanıtta durmasına
              gerek yoktur. */
           'toplam' => count($liste),
           'hakemler' => array_slice($liste, 0, 60)]);
}

/* Dizinde görünmeme tercihi */
if ($yol === '/hesap/dizin' && $metod === 'POST') {
    $h = hs_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $h['dizin_gizli'] = !empty($g['gizli']);
    hs_kaydet($h);
    cikti(['ok' => true, 'gizli' => (bool)$h['dizin_gizli']]);
}

/* =====================================================================
   BİLİM ALANI ÖNERİLERİ
   ---------------------------------------------------------------------
   Sınıflandırmanın ilk iki basamağı uluslararası ölçüte (FORD) bağlıdır
   ve değişmez. Üçüncü basamak bu sisteme aittir ve herkese açıktır:
   listede olmayan bir dalı kim isterse önerebilir. Öneri, önerenin adı
   ve tarihiyle birlikte kayda geçer; editör onaylarsa listeye girer.
   Reddedilen öneri de silinmez, kaydı kalır. Numara bir kez verilir ve
   geri alınmaz; reddedilen öneri numarasını da götürür.
   ===================================================================== */

/* Editör ya da yönetici mi? Öneri kararı için gereken yetki. */
function alan_karar_yetkisi(): array {
    if (girisli()) return ['ad' => 'Sistem yöneticisi', 'eposta' => ''];
    $h = hs_oturum();
    if ($h !== null && hs_editor_mu($h)) {
        return ['ad' => trim((string)($h['ad'] ?? '')), 'eposta' => (string)($h['eposta'] ?? '')];
    }
    cikti(['ok' => false, 'hata' => 'Bu işlem için editör yetkisi gerekir.'], 403);
}

if ($yol === '/alan-oner' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $ipk = 'alanoner:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 8) cikti(['ok' => false, 'hata' => 'Çok fazla öneri gönderildi. Lütfen sonra tekrar deneyin.'], 429);

    $t   = fn($k, $n) => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g[$k] ?? ''))), 0, $n);
    $ust = trim((string)($g['ust'] ?? ''));
    $tr  = $t('tr', 90);
    $enA = $t('en', 90);
    $ger = $t('gerekce', 900);
    $ad  = $t('ad', 120);

    $oturum = hs_oturum();
    if ($oturum !== null) {
        if ($ad === '') $ad = trim((string)($oturum['ad'] ?? ''));
    }

    if ($tr === '') cikti(['ok' => false, 'hata' => 'Dalın adını yazın.'], 400);
    if ($ad === '') cikti(['ok' => false, 'hata' => 'Adınızı yazın: öneri adıyla kayda geçer.'], 400);
    if (mb_strlen($ger, 'UTF-8') < 60) cikti(['ok' => false, 'hata' => 'Gerekçe en az 60 karakter olmalıdır.'], 400);
    if ($ust !== '9' && !isset(al_alt()[$ust])) cikti(['ok' => false, 'hata' => 'Üst alan listeden seçilmelidir.'], 400);

    /* Aynı ad daha önce önerilmiş mi? Aynı dalın iki numara alması,
       aramada aynı işi iki yerde gösterir. */
    $anahtar = mb_strtolower(preg_replace('/\s+/u', ' ', $tr), 'UTF-8');
    foreach (al_dallar(false) as $k => $v) {
        $var = mb_strtolower(preg_replace('/\s+/u', ' ', (string)($v['tr'] ?? '')), 'UTF-8');
        if ($var === $anahtar) {
            cikti(['ok' => false, 'hata' => 'Bu adla bir dal listede zaten var: ' . $k . ' ' . (string)$v['tr']], 409);
        }
    }
    foreach (al_alt() as $k => $v) {
        if (mb_strtolower((string)$v['tr'], 'UTF-8') === $anahtar) {
            cikti(['ok' => false, 'hata' => 'Bu ad uluslararası listede bir alt alanın adıdır: ' . $k . ' ' . (string)$v['tr']], 409);
        }
    }

    $kod = al_yeni_kod($ust);
    if ($kod === '') cikti(['ok' => false, 'hata' => 'Üst alan çözümlenemedi.'], 400);

    $liste = oku_json('alanlar.json', []); if (!is_array($liste)) $liste = [];
    $liste[$kod] = [
        'tr'      => $tr,
        'en'      => $enA !== '' ? $enA : $tr,
        'gerekce' => $ger,
        'ekleyen' => $ad,
        'eposta'  => $oturum !== null ? (string)($oturum['eposta'] ?? '') : '',
        'tarih'   => date('c'),
        'durum'   => 'oneri',
    ];
    yaz_json('alanlar.json', $liste);

    $ayar = yonetim_ayar(); $tg = (array)($ayar['tg'] ?? []);
    if (!empty($tg['token']) && !empty($tg['chat'])) {
        tg_gonder((string)$tg['token'], (string)$tg['chat'],
            "ALAN ONERISI: " . $kod . " " . $tr . "\nOneren: " . $ad . "\n" . mb_substr($ger, 0, 400), 4);
    }

    cikti(['ok' => true, 'kod' => $kod,
           'mesaj' => 'Öneriniz ' . $kod . ' numarasıyla kayda geçti. Editör onayladığında listede görünecek.']);
}

/* Bekleyen ve karara bağlanmış öneriler (editör) */
if ($yol === '/yonetim/alan-oneriler' && $metod === 'GET') {
    alan_karar_yetkisi();
    $liste = oku_json('alanlar.json', []); if (!is_array($liste)) $liste = [];
    $out = [];
    foreach ($liste as $kod => $v) {
        if (!is_array($v)) continue;
        $out[] = [
            'kod'     => (string)$kod,
            'tr'      => (string)($v['tr'] ?? ''),
            'en'      => (string)($v['en'] ?? ''),
            'gerekce' => (string)($v['gerekce'] ?? ''),
            'ekleyen' => (string)($v['ekleyen'] ?? ''),
            'tarih'   => (string)($v['tarih'] ?? ''),
            'durum'   => (string)($v['durum'] ?? 'oneri'),
            'karar'   => is_array($v['karar'] ?? null) ? $v['karar'] : null,
            'yol'     => al_yol((string)$kod),
        ];
    }
    usort($out, function ($a, $b) {
        $ai = $a['durum'] === 'oneri' ? 0 : 1;
        $bi = $b['durum'] === 'oneri' ? 0 : 1;
        return ($ai <=> $bi) ?: strcmp((string)$b['tarih'], (string)$a['tarih']);
    });
    cikti(['ok' => true, 'oneriler' => $out]);
}

/* Öneri kararı: onayla ya da reddet. Karar kimin verdiği kayda geçer. */
if ($yol === '/yonetim/alan-karar' && $metod === 'POST') {
    $kim = alan_karar_yetkisi();
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod   = trim((string)($g['kod'] ?? ''));
    $karar = (string)($g['karar'] ?? '');
    $not   = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['not'] ?? ''))), 0, 600);
    if (!in_array($karar, ['onayli', 'red'], true)) cikti(['ok' => false, 'hata' => 'Karar geçersiz.'], 400);

    $liste = oku_json('alanlar.json', []); if (!is_array($liste)) $liste = [];
    if (!isset($liste[$kod]) || !is_array($liste[$kod])) cikti(['ok' => false, 'hata' => 'Öneri bulunamadı.'], 404);
    if ((string)($liste[$kod]['durum'] ?? '') !== 'oneri') cikti(['ok' => false, 'hata' => 'Bu öneri zaten karara bağlanmış.'], 409);

    $liste[$kod]['durum'] = $karar;
    $liste[$kod]['karar'] = ['veren' => (string)$kim['ad'], 'tarih' => date('c'), 'not' => $not];
    yaz_json('alanlar.json', $liste);

    /* Önerene haber ver. Sistemde önerinin ne olduğu değil, ne olduğunun
       söylenmiş olması önemlidir: kayda geçen bir öneri sessiz kalmaz. */
    $ep = (string)($liste[$kod]['eposta'] ?? '');
    if ($ep !== '' && function_exists('eposta_gonder')) {
        $marka  = (string)tg_ayar('marka', 'Kutadgu');
        $kokAdr = (string)tg_ayar('kok', '');
        $ad     = (string)($liste[$kod]['tr'] ?? '');
        eposta_gonder($ep, $marka . ' | Alan öneriniz karara bağlandı',
            "Sayın " . (string)($liste[$kod]['ekleyen'] ?? '') . ",\n\n" .
            "\"" . $ad . "\" başlıklı alan öneriniz " .
            ($karar === 'onayli' ? "onaylandı ve " . $kod . " koduyla listeye girdi." : "onaylanmadı.") . "\n" .
            ($not !== '' ? "\nEditör notu: " . $not . "\n" : '') .
            "\nÖneriniz her hâlükârda kayıtta durur; silinmez.\n\n" . $marka . "\n" . $kokAdr . "\n");
    }

    cikti(['ok' => true, 'kod' => $kod, 'durum' => $karar]);
}

/* Son hata kayıtları (yönetici ve baş editör).
   Bir sistemin kendi arızasını görebilmesi, o arızayı gizlememesi
   kadar önemlidir. Kayıt veri dizininde durur, web kökünde değil. */
if ($yol === '/yonetim/hatalar' && $metod === 'GET') {
    alan_karar_yetkisi();
    $p = veri_yolu('hata-gunluk.jsonl');
    if (!is_file($p)) cikti(['ok' => true, 'hatalar' => []]);
    $satirlar = array_slice(array_filter(explode("\n", (string)file_get_contents($p))), -60);
    $out = [];
    foreach ($satirlar as $s2) {
        $d = json_decode($s2, true);
        if (is_array($d)) $out[] = $d;
    }
    cikti(['ok' => true, 'hatalar' => array_reverse($out)]);
}

/* =====================================================================
   KURUL KARARLARI · aç, oyla, oku
   ---------------------------------------------------------------------
   Gerekçenin tamamı ortak.php'de tg_kk_turleri()'nin başındadır. Kısaca:
   bu sistemin kuralları onlarca yerde "kurulun kararına" dayanıyordu ve
   o kararın yazılacağı, oylanacağı hiçbir yer yoktu.

   ÜÇ UÇ, TEK KAYNAK: sayım tg_kk_sonuc()'tadır; hiçbir uç kendi sayımını
   yapmaz. İki yerde iki ayrı sayım, bir gün iki ayrı sonuç demektir.

   GERİ ALINAMAZ YÖN SINIRLANDI: karar açıldıktan sonra metni
   değiştirilemez, oy geri alınamaz, kayıt silinemez. Bunlar sistemin
   kurul sayfasında zaten ilan ettiği hükümlerdir; burada uygulanır.
   ===================================================================== */
function kk_yaz(array $liste): void { yaz_json('kurul-kararlari.json', array_values($liste)); }

/* Oy verebilir mi? Tür kurucuysa yalnız görevdeki kurucu baş editör. */
function kk_oy_yetkisi(array $karar, ?array $h): array {
    if ($h === null) return ['olur' => false, 'neden' => 'Bu işlem için baş editör yetkisi gerekir.'];
    if (!hs_bas_yetki($h)) return ['olur' => false, 'neden' => 'Kurul kararlarını yalnız baş editörler oylayabilir.'];
    if ((string)($karar['tur'] ?? '') === 'kurucu' && !hs_kurucu_mu((string)($h['eposta'] ?? ''))) {
        return ['olur' => false, 'neden' => 'Bu bir kurucu kararıdır; yalnız kurucu baş editörler oy verebilir.'];
    }
    return ['olur' => true, 'neden' => ''];
}

if ($yol === '/yonetim/kurul-karar-ac' && $metod === 'POST') {
    yonetim_yazma_gerek();
    require_once __DIR__ . '/../k/hesap.php';
    $h = hs_oturum();
    $g = govde_json(); if (!$g) $g = $_POST;

    $baslik = mb_substr(trim(strip_tags((string)($g['baslik'] ?? ''))), 0, 200);
    $metin  = mb_substr(trim(strip_tags((string)($g['metin'] ?? ''))), 0, 12000);
    $tur    = (string)($g['tur'] ?? 'kurul');
    if (!isset(tg_kk_turleri()[$tur])) $tur = 'kurul';
    /* BAŞLIKSIZ YA DA GEREKÇESİZ KARAR AÇILAMAZ: oylanacak şeyin ne
       olduğu, oy verilmeden ÖNCE yazılmış olmalıdır. Sayı keyfî değil:
       iki yüz karakterin altında bir metin, bir kararın gerekçesi değil
       başlığının tekrarıdır. */
    if (mb_strlen($baslik) < 8) {
        cikti(['ok' => false, 'hata' => 'Kararın başlığı yazılmalıdır (en az 8 karakter).'], 400);
    }
    if (mb_strlen($metin) < 200) {
        cikti(['ok' => false, 'hata' => 'Kararın metni en az 200 karakter olmalıdır: oylanacak şeyin ne olduğu, oy verilmeden önce yazılmış olmalıdır.'], 400);
    }
    if ($tur === 'kurucu' && !hs_kurucu_mu((string)($h['eposta'] ?? ''))) {
        cikti(['ok' => false, 'hata' => 'Kurucu kararını yalnız bir kurucu baş editör açabilir.'], 403);
    }

    $liste = tg_kk_oku();
    $kayit = [
        'kod'    => substr(hash('sha256', $baslik . microtime() . random_int(0, PHP_INT_MAX)), 0, 16),
        'tur'    => $tur,
        'baslik' => $baslik,
        'metin'  => $metin,
        'acan'   => ['ad' => hs_gorunen_ad($h), 'eposta' => (string)($h['eposta'] ?? '')],
        'tarih'  => date('c'),
        'oylar'  => [],
    ];
    $liste[] = $kayit;
    kk_yaz($liste);
    error_log('kutadgu/kurul-karar: ' . hs_gorunen_ad($h) . ' -> ' . $baslik);
    cikti(['ok' => true, 'kod' => $kayit['kod'], 'sonuc' => tg_kk_sonuc($kayit)]);
}

if ($yol === '/yonetim/kurul-karar-oy' && $metod === 'POST') {
    yonetim_yazma_gerek();
    require_once __DIR__ . '/../k/hesap.php';
    $h = hs_oturum();
    $g = govde_json(); if (!$g) $g = $_POST;
    $kod = preg_replace('/[^a-f0-9]/', '', (string)($g['kod'] ?? ''));
    $oy  = (string)($g['karar'] ?? '');
    $ger = mb_substr(trim(strip_tags((string)($g['gerekce'] ?? ''))), 0, 4000);

    $liste = tg_kk_oku();
    $ix = null;
    foreach ($liste as $i2 => $k) if ((string)($k['kod'] ?? '') === $kod) $ix = $i2;
    if ($ix === null) cikti(['ok' => false, 'hata' => 'Karar bulunamadı.'], 404);

    $yet = kk_oy_yetkisi($liste[$ix], $h);
    if (!$yet['olur']) cikti(['ok' => false, 'hata' => $yet['neden']], 403);
    if (!isset(tg_kk_secenekler()[$oy])) cikti(['ok' => false, 'hata' => 'Geçersiz oy.'], 400);
    /* OY GEREKÇESİYLE VERİLİR. Bu sistemin her yerinde geçerli olan
       kural budur: hakem adıyla durur, editör adıyla durur, oy da
       adıyla ve gerekçesiyle durur. */
    if (mb_strlen($ger) < 40) {
        cikti(['ok' => false, 'hata' => 'Oyunuzun gerekçesi en az 40 karakter olmalıdır; gerekçesiz oy kayda geçmez.'], 400);
    }
    $sonucOnce = tg_kk_sonuc($liste[$ix]);
    if ($sonucOnce['kapali']) {
        cikti(['ok' => false, 'hata' => 'Bu oylama kapandı: ' . tg_kk_hal_ad($sonucOnce['hal'], false)], 409);
    }
    /* OY GERİ ALINMAZ VE DEĞİŞTİRİLMEZ. Bir kez verilmiş oy, kararın
       kendisi kadar kalıcıdır; aksi hâlde sayım son ana kadar
       pazarlığa açık kalırdı. */
    $ben = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
    foreach (tg_dizi($liste[$ix]['oylar'] ?? null) as $o) {
        if (is_array($o) && hs_eposta_anahtar((string)($o['eposta'] ?? '')) === $ben) {
            cikti(['ok' => false, 'hata' => 'Bu karara zaten oy verdiniz. Oy geri alınmaz.'], 409);
        }
    }
    if (!is_array($liste[$ix]['oylar'] ?? null)) $liste[$ix]['oylar'] = [];
    $liste[$ix]['oylar'][] = [
        'ad' => hs_gorunen_ad($h), 'eposta' => (string)($h['eposta'] ?? ''),
        'karar' => $oy, 'gerekce' => $ger, 'tarih' => date('c'),
        'kurucu' => hs_kurucu_mu((string)($h['eposta'] ?? '')),
    ];
    $sonuc = tg_kk_sonuc($liste[$ix]);
    if ($sonuc['kapali'] && trim((string)($liste[$ix]['kapanma'] ?? '')) === '') {
        $liste[$ix]['kapanma'] = date('c');
        $liste[$ix]['hal'] = $sonuc['hal'];
    }
    kk_yaz($liste);
    cikti(['ok' => true, 'sonuc' => $sonuc]);
}

/* OKUMA UCU KAMUSALDIR VE BİLEREK ÖYLEDİR. Kurul sayfası "sayım herkese
   açıktır" diyor; kapalı bir sayım, denetlenemeyen bir kurul demektir.
   Adres dönmez: kim oy verdi adıyla görünür, adresiyle değil. */
if ($yol === '/kurul-kararlari' && $metod === 'GET') {
    $en = function_exists('k_en') ? k_en() : false;
    $out = [];
    foreach (tg_kk_oku() as $k) {
        $s = tg_kk_sonuc($k);
        $oylar = [];
        foreach (tg_dizi($k['oylar'] ?? null) as $o) {
            if (!is_array($o)) continue;
            $oylar[] = ['ad' => (string)($o['ad'] ?? ''), 'karar' => (string)($o['karar'] ?? ''),
                        'karar_ad' => tg_kk_secenek_ad((string)($o['karar'] ?? ''), $en),
                        'gerekce' => (string)($o['gerekce'] ?? ''),
                        'kurucu' => !empty($o['kurucu']), 'tarih' => (string)($o['tarih'] ?? '')];
        }
        $out[] = [
            'kod' => (string)$k['kod'], 'tur' => (string)($k['tur'] ?? 'kurul'),
            'tur_ad' => tg_kk_tur_ad((string)($k['tur'] ?? 'kurul'), $en),
            'baslik' => (string)($k['baslik'] ?? ''), 'metin' => (string)($k['metin'] ?? ''),
            'acan' => (string)((($k['acan'] ?? [])['ad'] ?? '')),
            'tarih' => (string)($k['tarih'] ?? ''), 'kapanma' => (string)($k['kapanma'] ?? ''),
            'oylar' => $oylar, 'sonuc' => $s, 'hal_ad' => tg_kk_hal_ad($s['hal'], $en),
        ];
    }
    cikti(['ok' => true, 'kararlar' => $out]);
}

/* =====================================================================
   DENEME DÜZENİ (yönetici ve baş editör)
   ---------------------------------------------------------------------
   KURUL İSTEĞİ (M. Z. Tunca): "bir sürecin tam olarak işlediğini
   denemek için demo hakem, yazar, editör ile deneme yapamıyoruz."
   Yönetim de aynı şeyi bildirdi: "hakem atamasını test bile edemedim."

   Bir yayın sisteminin en tehlikeli yeri, hiç denenmemiş bir akıştır:
   hakem ataması ilk kez gerçek bir çalışmayla, gerçek bir hakemin
   gözü önünde denenirse, çıkan her kusur bir kişinin emeğinin üstüne
   düşer.

   ÜÇ KURAL:

   1. DENEME KAYITLARI ARŞİVE GİRMEZ. Hepsi 'deneme' => true taşır ve
      k_yazilar() onları kamusal her sayfadan süzer (anasayfa, liste,
      arama, istatistik, OAI, site haritası, döküm). Süzgeç tek yerde
      durur; her sayfaya ayrı yazılsaydı biri unutulurdu.
   2. DENEME KAYITLARI GERİ ALINABİLİR. /yonetim/deneme-sil hepsini
      birden siler ve yalnızca 'deneme' işaretlileri siler. Denenebilir
      olmanın koşulu, denemenin izinin kalmamasıdır.
   3. GERÇEK POSTA GİTMEZ. Deneme hesaplarının adresleri
      @deneme.gecersiz alan adındadır; bu alan adı kayıtlıdır ve
      hiçbir posta sunucusu ona teslim yapamaz (RFC 6761 'invalid').
      Deneme kurmak, kimsenin gelen kutusuna bir şey düşürmemelidir.
   ===================================================================== */
/* =====================================================================
   DENEME HESABIYLA GİRİŞ
   ---------------------------------------------------------------------
   İSTENEN: "deneme hakemi açıldığında üzerine tıklayınca direkt o
   kullanıcı gibi giriş yapsa olmaz mı?"

   OLUR, AMA DAR BİR KAPIDAN. Bu uç, bir kimliğe parolasız girmeyi
   sağlar; yani yanlış yazılırsa sistemin en tehlikeli ucudur. Bu
   yüzden dört kapı birden konuldu ve dördü de burada, tek yerde:

     1. ÇAĞIRAN YÖNETİM YETKİSİ TAŞIMALI. Deneme düzenini kuran kapı
        neyse, giren kapı da odur (yonetim_yazma_gerek).
     2. HEDEF HESAP 'deneme' => true OLMALI. Bu alan yalnızca
        /yonetim/deneme-kur tarafından yazılır; hiçbir kayıt ucu bu
        alanı kabul etmez. Gerçek bir hesaba bu yoldan girilemez.
     3. ADRES DENEME ALAN ADINDA OLMALI. İkinci bir ölçüt bilerek
        kondu: bir gün bir içe aktarma 'deneme' bayrağını yanlışlıkla
        gerçek bir kayda yazarsa, adres onu yine durdurur.
        @deneme.gecersiz teslim edilemeyen bir alan adıdır (RFC 6761).
     4. KİM, KİME GİRDİĞİ YAZILIR. Hedef hesabın giriş kaydına
        "vekâleten" damgası düşer ve kimin girdiği yazılır. Denemede
        bile iz bırakmayan bir yetki, denemenin dışında da bırakmaz.

   DÖNÜŞ YOLU AÇIK BIRAKILIR: çıkış yapıp kendi hesabıyla girmek her
   zaman mümkündür; oturum değiştirmek bir yetki devri değildir. */
if ($yol === '/yonetim/deneme-giris' && $metod === 'POST') {
    yonetim_yazma_gerek();
    require_once __DIR__ . '/../k/hesap.php';
    $g = govde_json(); if (!$g) $g = $_POST;
    $eposta = trim((string)($g['eposta'] ?? ''));
    if ($eposta === '') cikti(['ok' => false, 'hata' => 'Hangi deneme hesabı olduğu bildirilmedi.'], 400);

    /* Kimin girdiği, oturum değişmeden önce okunur. */
    $suanki = hs_oturum();
    $girenAd = is_array($suanki) ? trim((string)($suanki['ad'] ?? '')) : '';

    $h = hs_bul($eposta);
    if (!is_array($h)) cikti(['ok' => false, 'hata' => 'Böyle bir deneme hesabı yok.'], 404);
    /* İKİ ÖLÇÜT BİRDEN. Biri yeterli görünür; ikisi bilerek istenir. */
    if (empty($h['deneme'])) {
        cikti(['ok' => false, 'hata' => 'Bu hesap bir deneme hesabı değil. Bu yoldan yalnızca deneme hesaplarına girilebilir.'], 403);
    }
    if (!preg_match('/@deneme\.gecersiz$/i', (string)($h['eposta'] ?? ''))) {
        cikti(['ok' => false, 'hata' => 'Bu adres deneme alan adında değil; giriş açılmadı.'], 403);
    }

    $h['giris'] = array_slice(array_merge((array)($h['giris'] ?? []), [[
        'tarih'      => date('c'),
        'ip'         => substr(hash('sha256', ip_al()), 0, 12),
        'vekaleten'  => $girenAd !== '' ? $girenAd : 'yonetim',
    ]]), -20);
    hs_kaydet($h);
    session_regenerate_id(true);
    hs_giris_yap((string)$h['eposta']);
    error_log('kutadgu/deneme-giris: ' . ($girenAd !== '' ? $girenAd : 'yonetim')
              . ' -> ' . (string)$h['eposta']);
    cikti(['ok' => true, 'hesap' => hs_gorunum($h)]);
}

if ($yol === '/yonetim/deneme-kur' && $metod === 'POST') {
    yonetim_yazma_gerek();
    require_once __DIR__ . '/../k/hesap.php';

    $simdi = date('c');
    $kisiler = [
        ['ad' => 'Deneme Yazar',  'rol' => 'yazar',  'unvan' => 'Dr.'],
        ['ad' => 'Deneme Hakem',  'rol' => 'hakem',  'unvan' => 'Prof. Dr.'],
        ['ad' => 'Deneme Editör', 'rol' => 'editor', 'unvan' => 'Doç. Dr.'],
    ];
    $hesaplar = hs_oku();
    $kurulan = [];
    foreach ($kisiler as $k) {
        $eposta = str_replace(' ', '.', mb_strtolower($k['ad'], 'UTF-8')) . '@deneme.gecersiz';
        $eposta = str_replace(['ç','ğ','ı','ö','ş','ü'], ['c','g','i','o','s','u'], $eposta);
        $var = false;
        foreach ($hesaplar as $h) {
            if (is_array($h) && hs_eposta_anahtar((string)($h['eposta'] ?? '')) === hs_eposta_anahtar($eposta)) { $var = true; break; }
        }
        /* Parola üretilir ve BİR KEZ döndürülür; kayda yalnızca özeti
           girer. Deneme hesabı da olsa parola saklanmaz — deneme
           düzeninin gerçek düzenden farklı davranması, denemeyi
           değersizleştirir. */
        $sifre = uret_sifre();
        if (!$var) {
            $hesaplar[] = [
                'ad' => $k['ad'], 'unvan' => $k['unvan'], 'eposta' => $eposta,
                'kurum' => 'Deneme Üniversitesi',
                'parola' => password_hash($sifre, PASSWORD_DEFAULT),
                'roller' => [$k['rol']],
                'katilma' => $simdi,
                'deneme' => true,
            ];
        }
        $kurulan[] = ['ad' => $k['ad'], 'rol' => $k['rol'], 'eposta' => $eposta,
                      'parola' => $var ? '' : $sifre, 'vardi' => $var];
    }
    hs_yaz($hesaplar);

    /* Hakem araması denenebilsin diye hakem bekleyen bir çalışma. */
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $denemeVar = false;
    foreach ($y as $e) { if (is_array($e) && !empty($e['deneme'])) { $denemeVar = true; break; } }
    if (!$denemeVar) {
        $yid = 'deneme' . substr(hash('sha256', microtime()), 0, 6);
        $y[] = [
            'id' => $yid, 'slug' => 'deneme-calismasi-' . substr($yid, -4),
            'deneme' => true,
            'tur' => 'hakemli',
            'dil' => 'tr',
            'baslik' => 'Deneme çalışması: hakem atama akışının sınanması',
            'baslik_en' => 'Test work: exercising the reviewer assignment flow',
            'yazar' => 'Dr. Deneme Yazar',
            'yazar_liste' => [['unvan' => 'Dr.', 'ad' => 'Deneme Yazar',
                               'kurum' => 'Deneme Üniversitesi', 'orcid' => '']],
            'tarih' => date('Y-m-d'),
            'ozet' => 'Bu kayıt bir deneme kaydıdır. Arşivde, istatistikte, OAI çıktısında, '
                    . 'site haritasında ve dökümde görünmez. Yönetim panelinden tek düğmeyle silinir.',
            'alanlar' => ['5.2.016'],
            'hakemler' => [],
            'raporlar' => [],
            /* ---- DENEME ÇALIŞMASI GERÇEK BİR ÇALIŞMA GİBİ DOĞAR ----
               KURUL BİLDİRİMİ (19 Ağustos 2026): "deneme editör hakem
               yazar falan her seferinde tekrar açmak gerekiyor ve
               kullanışsız... hakeme gönderince hakem nasıl bir arayüz
               görecek, revizyon istedi yazar yaptı, yayımlanabilir
               dedikleri zaman nasıl görünüyor, aradaki iletişim nasıl
               gözüküyor, revize edilmiş yeni metin nasıl gözüküyor,
               bunları deneyemiyorum."

               Deneyememesinin sebebi buydu: kayıt yarımdı. Yazar
               erişimi yoktu, yani yazar gözünden bakılamıyor ve metin
               revize edilemiyordu; tam metin yoktu, yani revizyonun
               ÖNCE/SONRA farkı görülemiyordu; künye ve beyanlar yoktu,
               yani kabul edilse bile yayımlanmış bir sayfa gibi
               görünmüyordu.

               Artık eksiksiz doğar. Erişim anahtarı da kayda girer:
               deneme akışını yürüten panel, yazar gözünden yapılan
               adımları o anahtarla ve GERÇEK uçlardan geçerek yapar.
               Deneme düzeninin gerçek düzenden farklı davranması,
               denemeyi değersizleştirir. */
            'yazar_bilgi' => [
                'unvan' => 'Dr.', 'ad' => 'Deneme Yazar',
                'eposta' => 'deneme.yazar@deneme.gecersiz',
                'orcid' => '0000-0002-1825-0097', 'kurum' => 'Deneme Üniversitesi',
            ],
            'yazar_erisim' => [
                'token' => substr(hash('sha256', 'deneme' . $yid . microtime()), 0, 32),
                'sifre' => uret_sifre(),
            ],
            'baslik_en' => 'Test work: exercising the reviewer assignment flow',
            'ozet_en' => 'This is a test record used to exercise the review flow from end to end.',
            'genis_ozet_en' => '',
            'anahtar' => 'deneme, hakemlik akışı, sınama',
            'anahtar_en' => 'test, review flow, measurement',
            'metin' => '<h2>Giriş</h2><p>Bu bölüm, deneme akışında revizyondan ÖNCEKİ metindir. '
                     . 'Yazar revizyon adımını işlettiğinde bu paragrafın yerini düzeltilmiş sürüm alır ve '
                     . 'çalışmanın sayfasındaki sürüm kaydında ikisinin parmak izi yan yana görünür.</p>'
                     . '<h2>Yöntem</h2><p>Ölçüm aracının geçerlik katsayıları bu sürümde bilerek verilmemiştir; '
                     . 'birinci hakemin isteyeceği düzeltme budur.</p>'
                     . '<h2>Bulgular</h2><p>Deneme verisiyle üretilmiş üç bulgu.</p>'
                     . '<h2>Sonuç</h2><p>Bu kayıt bir denemedir.</p>',
            'kaynakca' => '<p>Deneme, A. (2026). Sınama kaydı. <i>Deneme Dergisi</i>, 1(1), 1-10.</p>',
            'etik' => ['durum' => 'gereksiz'],
            'veri' => ['beyan' => 'yok'],
            'beyan' => ['cikar' => 'yok', 'tek_gonderim' => true, 'yazar_tam' => 'tek', 'fon' => 'yok'],
        ];
        yaz_json('yazilar.json', $y);
    }

    /* ---- İKİNCİ DENEME ÇALIŞMASI: RET YOLU ----
       Kurul listesindeki D2 maddesi: "deneme akışına ret kolu — bugün
       yalnız kabul yolu yürüyor; iki ret alan çalışmanın kilitlenmesi
       denenemiyor."

       Ret, kabulün aynası değildir; kendi kuralları vardır ve en
       önemlisi geri alınamaz: iki ret alan çalışma KİLİTLENİR, yazar
       metnini bir daha değiştiremez ve çalışma ret gerekçeleriyle
       birlikte YAYINDA KALIR. Bir yayın sisteminde en pahalı kural
       budur ve hiç denenmemişti.

       AYRI BİR KAYIT, ÇÜNKÜ AYNI ÇALIŞMADA DENENEMEZ: bir çalışma ya
       kabul yolunu yürür ya ret yolunu. İkisini tek kayıtta denemek,
       ikisini de yarım denemek olurdu. */
    $retVar = false;
    foreach ($y as $e) { if (is_array($e) && !empty($e['deneme']) && !empty($e['deneme_ret'])) { $retVar = true; break; } }
    if (!$retVar) {
        $rid = 'denemeret' . substr(hash('sha256', microtime() . 'ret'), 0, 6);
        $y[] = [
            'id' => $rid, 'slug' => 'deneme-ret-' . substr($rid, -4),
            'deneme' => true, 'deneme_ret' => true,
            'tur' => 'hakemli', 'dil' => 'tr',
            'baslik' => 'Deneme çalışması: ret yolunun sınanması',
            'baslik_en' => 'Test work: exercising the rejection path',
            'ozet' => 'Bu kayıt bir deneme kaydıdır ve ret yolunu sınamak için vardır. Arşivde görünmez.',
            'ozet_en' => 'A test record used to exercise the rejection path. It does not appear in the archive.',
            'anahtar' => 'deneme, ret yolu, sınama', 'anahtar_en' => 'test, rejection path, measurement',
            'yazar' => 'Dr. Deneme Yazar',
            'yazar_bilgi' => ['unvan' => 'Dr.', 'ad' => 'Deneme Yazar',
                              'eposta' => 'deneme.yazar@deneme.gecersiz',
                              'orcid' => '0000-0002-1825-0097', 'kurum' => 'Deneme Üniversitesi'],
            'yazar_liste' => [['unvan' => 'Dr.', 'ad' => 'Deneme Yazar',
                               'kurum' => 'Deneme Üniversitesi', 'orcid' => '0000-0002-1825-0097']],
            'yazar_erisim' => ['token' => substr(hash('sha256', 'ret' . $rid . microtime()), 0, 32),
                               'sifre' => uret_sifre()],
            'tarih' => date('Y-m-d'),
            'alanlar' => ['5.2.016'],
            'metin' => '<h2>Giriş</h2><p>Bu kayıt ret yolunu denemek için vardır.</p>'
                     . '<h2>Yöntem</h2><p>Yöntem bilerek eksik bırakılmıştır; iki hakemin ret gerekçesi budur.</p>'
                     . '<h2>Sonuç</h2><p>Bu bir deneme kaydıdır.</p>',
            'kaynakca' => '<p>Deneme, A. (2026). Sınama kaydı. <i>Deneme Dergisi</i>, 1(1), 1-10.</p>',
            'etik' => ['durum' => 'gereksiz'], 'veri' => ['beyan' => 'yok'],
            'hakemler' => [], 'raporlar' => [],
        ];
        yaz_json('yazilar.json', $y);
    }

    cikti(['ok' => true, 'hesaplar' => $kurulan, 'calisma' => !$denemeVar,
           'not' => 'Deneme kayıtları arşivde görünmez. Parolalar bir kez gösterilir; '
                  . 'kaybolursa deneme düzenini silip yeniden kurun.']);
}

/* =====================================================================
   DENEME AKIŞININ DURUMU
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ (19 Ağustos 2026): "her seferinde tekrar açmak
   gerekiyor ve kullanışsız... bunları deneyemiyorum."

   İKİ AYRI KUSUR VARDI:

   1. DENEME DÜZENİ SÜRDÜRÜLEBİLİR DEĞİLDİ. /yonetim/deneme-kur hesap ve
      çalışma kuruyordu, ama hakem anahtarları yalnızca o yanıtta bir kez
      dönüyordu. Sayfa yenilendiğinde eldeki her şey kayboluyor ve
      denemeye baştan başlamak gerekiyordu. Bu uç, durumu KAYITTAN
      okuyup her açılışta yeniden verir; deneme kaldığı yerden sürer.

   2. AKIŞIN NERESİNDE OLDUĞU HİÇBİR YERDE YAZMIYORDU. Aşama burada
      HESAPLANIR, saklanmaz: saklanan bir aşama, kayıt elle değiştiği
      gün yalan söyler. Kayıt ne diyorsa aşama odur.

   BU UÇ YALNIZCA OKUR. Adımları işleten şey panelin kendisidir ve
   GERÇEK uçlardan geçer (/editor/hakem-ata, /hakem-davet-yanit,
   /hakem-gonder, /yazar-hakem-yanit, /yazar-kaydet). Deneme için ayrı
   bir yol yazılsaydı, denenen şey sistemin kendisi olmazdı.

   ANAHTARLAR NEDEN DÖNÜYOR: hakem anahtarı ve yazar erişim şifresi
   normalde kimseye gösterilmez. Burada dönüyorlar, çünkü kayıtların
   hepsi 'deneme' işaretlidir ve uç yönetim yetkisi ister. Gerçek bir
   kaydın anahtarı bu uçtan ASLA dönmez: döngü yalnız deneme
   işaretlilerin üstünde yürür.
   ===================================================================== */
if ($yol === '/yonetim/deneme-durum' && $metod === 'GET') {
    yonetim_yazma_gerek();
    require_once __DIR__ . '/../k/hesap.php';

    $hesaplar = [];
    foreach (hs_oku() as $h) {
        if (!is_array($h) || empty($h['deneme'])) continue;
        $hesaplar[] = ['ad' => (string)($h['ad'] ?? ''), 'eposta' => (string)($h['eposta'] ?? ''),
                       'roller' => array_values((array)($h['roller'] ?? []))];
    }

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $w = null;
    foreach ($y as $e) { if (is_array($e) && !empty($e['deneme'])) { $w = $e; break; } }

    if ($w === null) {
        cikti(['ok' => true, 'kurulu' => false, 'adim' => 'yok', 'hesaplar' => $hesaplar]);
    }

    $kok = tg_kok();
    $hakemler = [];
    foreach (tg_dizi($w['hakemler'] ?? null) as $h) {
        if (!is_array($h)) continue;
        $hakemler[] = [
            'ad'          => (string)($h['ad'] ?? ''),
            'token'       => (string)($h['token'] ?? ''),
            'sifre'       => (string)($h['sifre'] ?? ''),
            'davet'       => (string)($h['davet_token'] ?? ($h['davet'] ?? '')),
            'davet_durum' => (string)($h['davet_durum'] ?? ''),
            'karar'       => (string)($h['karar'] ?? ''),
            'rapor_var'   => trim((string)($h['rapor'] ?? '')) !== '',
            'diyalog'     => count(tg_dizi($h['diyalog'] ?? null)),
            'bak'         => $kok . '/hakem.php?t=' . (string)($h['token'] ?? ''),
        ];
    }

    /* ---- RET YOLU KAYDI ---- */
    $wr = null;
    foreach ($y as $e) { if (is_array($e) && !empty($e['deneme']) && !empty($e['deneme_ret'])) { $wr = $e; break; } }
    $retHakem = []; $retAdim = 'yok';
    if ($wr !== null) {
        $retSay = 0;
        foreach (tg_dizi($wr['hakemler'] ?? null) as $h) {
            if (!is_array($h)) continue;
            $retHakem[] = [
                'ad' => (string)($h['ad'] ?? ''), 'token' => (string)($h['token'] ?? ''),
                'sifre' => (string)($h['sifre'] ?? ''),
                'davet' => (string)($h['davet_token'] ?? ''),
                'davet_durum' => (string)($h['davet_durum'] ?? ''),
                'karar' => (string)($h['karar'] ?? ''),
                'rapor_var' => trim((string)($h['rapor'] ?? '')) !== '',
                'bak' => $kok . '/hakem.php?t=' . (string)($h['token'] ?? ''),
            ];
            if ((string)($h['karar'] ?? '') === 'ret') $retSay++;
        }
        $retEsik = max(1, (int)tg_ayar('ret_donusum', 2));
        if (!$retHakem)                                        $retAdim = 'kuruldu';
        elseif (($retHakem[0]['davet_durum'] ?? '') !== 'kabul') $retAdim = 'hakem-atandi';
        elseif (!$retHakem[0]['rapor_var'])                    $retAdim = 'davet-kabul';
        elseif (count($retHakem) < 2)                          $retAdim = 'ret-1';
        elseif (!$retHakem[1]['rapor_var'])                    $retAdim = 'hakem-2';
        elseif ($retSay < $retEsik)                            $retAdim = 'ret-2';
        else                                                   $retAdim = 'kilitli';
    }

    /* ---- AŞAMA KAYITTAN HESAPLANIR ---- */
    $surum = count(tg_dizi($w['surumler'] ?? null));
    $raporlu = 0; $kucuk = 0; $kabul = 0;
    foreach ($hakemler as $h) {
        if ($h['rapor_var']) $raporlu++;
        if ($h['karar'] === 'kucuk' || $h['karar'] === 'buyuk') $kucuk++;
        if ($h['karar'] === 'kabul') $kabul++;
    }
    /* ---- AKIŞ HAKEM HEDEFİNE KADAR YÜRÜR ----
       ÖLÇÜLEN KUSUR — 19 Ağustos 2026. Akış iki kabulle "bitti"
       diyordu, ama çalışmanın sayfası hâlâ "Hakem aranıyor" yazıyordu
       ve ölçüm bunu gösterdi: onay eşiği iki (kabul_gecerli), hakem
       HEDEFİ ise üç (hakem_hedef). Yani sistem, iki olumlu raporla
       çalışmayı onaylı sayar ama üçüncüyü aramayı sürdürür.

       Kurulun sorusu "yayımlanabilir dedikleri zaman nasıl görünüyor"
       idi; iki kabulde duran bir deneme, o soruya YARIM yanıt veriyordu.
       Akış artık hedefe kadar yürür ve sayfanın oturmuş hâlini gösterir.

       HEDEF ELLE YAZILMAZ, AYARDAN OKUNUR: sayı bir gün değişirse akış
       da onunla değişir. Elle yazılsaydı, ayarı değiştiren kişi
       denemenin yanlış yerde durduğunu ancak kazayla fark ederdi. */
    $hedef = max(1, (int)tg_ayar('hakem_hedef', 3));
    $adim = 'kuruldu';
    if (!$hakemler)                                   $adim = 'kuruldu';
    elseif (($hakemler[0]['davet_durum'] ?? '') !== 'kabul') $adim = 'hakem-atandi';
    elseif (!$hakemler[0]['rapor_var'])               $adim = 'davet-kabul';
    elseif ($surum === 0)                             $adim = 'rapor-1';
    elseif (count($hakemler) < 2)                     $adim = 'revizyon';
    elseif (!$hakemler[1]['rapor_var'])               $adim = 'hakem-2';
    elseif ($kucuk > 0)                               $adim = 'rapor-2';
    elseif ($raporlu < $hedef)                        $adim = 'hakem-3';
    else                                              $adim = 'bitti';

    cikti(['ok' => true, 'kurulu' => true, 'adim' => $adim,
        'hesaplar' => $hesaplar,
        'calisma' => [
            'id' => (string)($w['id'] ?? ''), 'slug' => (string)($w['slug'] ?? ''),
            'baslik' => (string)($w['baslik'] ?? ''),
            'tur' => (string)($w['tur'] ?? ''),
            'asama' => tg_hakem_asamasi($w),
            'kilit' => tg_kilit_durum($w),
            'surum' => $surum,
            'yol' => $kok . tg_yazi_yolu($w),
            'yazar_bak' => $kok . '/yazar.php?t=' . (string)(($w['yazar_erisim']['token'] ?? '')),
            /* ---- REVİZYON ADIMI İÇİN MEVCUT İÇERİK ----
               ÖLÇÜLDÜ (ilk koşumda): revizyon adımı yalnız 'metin'
               gönderiyordu ve /yazar-kaydet haklı olarak reddetti —
               künye alanları dolu bir kayıtta boş gönderilen künye,
               künyenin SİLİNMESİ demektir ve o kural 19 Ağustos'ta
               kondu. Deneme akışı gerçek uçtan geçtiği için gerçek
               kurala çarptı; olması gereken de buydu. İçerik olduğu
               gibi geri verilir, adım yalnız değiştirdiği alanı
               değiştirir — gerçek bir yazar panelinin yaptığı da
               tam olarak budur. */
            'icerik' => [
                'baslik'     => (string)($w['baslik'] ?? ''),
                'baslik_en'  => (string)($w['baslik_en'] ?? ''),
                'ozet'       => (string)($w['ozet'] ?? ''),
                'ozet_en'    => (string)($w['ozet_en'] ?? ''),
                'anahtar'    => tg_metin($w['anahtar'] ?? ''),
                'anahtar_en' => tg_metin($w['anahtar_en'] ?? ''),
                'kaynakca'   => (string)($w['kaynakca'] ?? ''),
                'metin'      => (string)($w['metin'] ?? ''),
            ],
        ],
        'yazar' => [
            'token' => (string)(($w['yazar_erisim']['token'] ?? '')),
            'sifre' => (string)(($w['yazar_erisim']['sifre'] ?? '')),
            'eposta' => (string)(($w['yazar_bilgi']['eposta'] ?? '')),
        ],
        /* Çalışmanın oturmuş hâli: akışın sonunda sayfanın ne dediğini
           panel de yazsın ki "bitti" dendiği yerde gerçekten bittiği
           görülsün. */
        'onay' => tg_onay_durumu($w),
        'araniyor' => tg_hakem_araniyor($w),
        'hedef' => $hedef,
        'hakemler' => $hakemler,
        /* ---- RET YOLU AYRI BİR KAYITTA ----
           Ayrı, çünkü bir çalışma ya kabul yolunu yürür ya ret yolunu;
           ikisini tek kayıtta denemek ikisini de yarım denemek olurdu.
           Aşama yine SAKLANMAZ, kayıttan hesaplanır. */
        'ret' => $wr === null ? null : [
            'id' => (string)($wr['id'] ?? ''), 'slug' => (string)($wr['slug'] ?? ''),
            'baslik' => (string)($wr['baslik'] ?? ''),
            'yol' => $kok . tg_yazi_yolu($wr),
            'yazar_bak' => $kok . '/yazar.php?t=' . (string)(($wr['yazar_erisim']['token'] ?? '')),
            'yazar' => ['token' => (string)(($wr['yazar_erisim']['token'] ?? '')),
                        'sifre' => (string)(($wr['yazar_erisim']['sifre'] ?? ''))],
            'hakemler' => $retHakem,
            'adim' => $retAdim,
            'kilitli' => yazar_kilitli($wr),
            'esik' => (int)tg_ayar('ret_donusum', 2),
        ]]);
}

/* =====================================================================
   BİR ÇALIŞMAYI DENEME KAYDI OLARAK İŞARETLEMEK
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ — 20 Ağustos 2026: "ben deneme gibi bir makale
   gönderdim ret ettim ama orada kalmasa; denemeyi düzgün yapamamıştık
   ya o zaman, onu siler misin."

   İSTEK YERİNDE: deneme düzeni 19 Ağustos'ta açıldı; ondan önce sistemi
   denemenin tek yolu GERÇEK bir çalışma göndermekti. O kayıt bugün
   arşivde duruyor ve arşivi kirletiyor — üstelik dünkü düzeltmeden
   sonra sayfasında "MAKALE RET EDİLMİŞTİR" bandıyla duruyor.

   SİLME UCU YAZILMADI, İŞARET UCU YAZILDI. Sebebi ölçülebilir: silme
   geri alınamaz, işaret alınabilir. İşaretlenen kayıt k_yazilar()
   süzgecine takılır ve kamusal her sayfadan (anasayfa, liste, arama,
   istatistik, OAI, site haritası, döküm) tek yerden düşer; kayıt
   yerinde durur, yanlışlık olduysa aynı uçtan geri alınır. Kalıcı
   olarak silmek isteyen, zaten var olan /yonetim/deneme-sil ucunu
   kullanır — iki ayrı ve bilinçli adım.

   BEŞ KAPI. Bir kaydı arşivden görünmez kılmak, bu sistemde yapılabilecek
   en ağır işlerden biridir; kapılar dar olmalı ve ÖLÇÜLEBİLİR olmalıdır:

     1. Baş editör yetkisi (yonetim_yazma_gerek).
     2. YALNIZ KENDİ ÇALIŞMASI. Başkasının çalışmasını görünmez kılmak
        bu uçtan mümkün değildir. Bu kapı olmasaydı, "deneme" sözcüğü
        beğenilmeyen bir çalışmayı sessizce kaldırmanın adı olurdu.
     3. ÜÇÜNCÜ KİŞİNİN EMEĞİ VARSA OLMAZ. Başka birinin hakem raporu,
        şerhi ya da ortak yazarlığı varsa kayıt işaretlenemez: o emek de
        birlikte görünmez olurdu. Kendi kendine yazdığı deneme raporu
        engel değildir.
     4. DOI VERİLMİŞSE OLMAZ. DOI kalıcı bir sözdür; verildikten sonra
        adresin boşa düşmesi dışarıya verilmiş bir sözü bozar.
     5. GEREKÇE ZORUNLU ve kayda geçer. Kim, ne zaman, niçin — üçü de
        kaydın içinde durur ki işaretin kendisi de denetlenebilsin.
   ===================================================================== */
if ($yol === '/yonetim/deneme-isaretle' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $h = hs_gerek();
    /* Panel JSON gövdesiyle konuşur; form gönderimi de kabul edilir. */
    $g = govde_json(); if (!$g) $g = $_POST;
    $anah = trim((string)($g['y'] ?? ''));
    $kaldir = !empty($g['kaldir']);
    $gerekce = trim((string)($g['gerekce'] ?? ''));
    if ($anah === '') cikti(['ok' => false, 'hata' => 'Hangi çalışma olduğu bildirilmedi.'], 400);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = -1;
    foreach ($y as $k => $e) {
        if (!is_array($e)) continue;
        if ((string)($e['slug'] ?? '') === $anah || (string)($e['id'] ?? '') === $anah) { $i = $k; break; }
    }
    if ($i < 0) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    $e = $y[$i];

    /* GERİ ALMA: yalnızca bu uçla konmuş işaret kaldırılır. Deneme
       düzeninin kurduğu kayıtlar buradan geri alınamaz; onların yeri
       /yonetim/deneme-sil'dir ve karışması işe yaramaz. */
    if ($kaldir) {
        if (empty($e['deneme'])) cikti(['ok' => false, 'hata' => 'Bu çalışma zaten deneme kaydı değil.'], 409);
        if (!is_array($e['deneme_isaret'] ?? null))
            cikti(['ok' => false, 'hata' => 'Bu kayıt deneme düzeninin kendi kaydıdır; buradan geri alınamaz.'], 409);
        unset($y[$i]['deneme']);
        $y[$i]['deneme_isaret']['geri_alindi'] = ['kim' => hs_gorunen_ad($h), 'tarih' => date('c')];
        yaz_json('yazilar.json', array_values($y));
        cikti(['ok' => true, 'deneme' => false]);
    }

    if (!empty($e['deneme'])) cikti(['ok' => false, 'hata' => 'Bu çalışma zaten deneme kaydı.'], 409);
    if (mb_strlen($gerekce, 'UTF-8') < 20)
        cikti(['ok' => false, 'hata' => 'Gerekçe yazın (en az 20 karakter). Gerekçe kayda geçer.'], 400);
    if (!tg_yazar_mi($e, $h))
        cikti(['ok' => false, 'hata' => 'Yalnızca kendi çalışmanızı deneme kaydı olarak işaretleyebilirsiniz.'], 403);
    if (trim((string)($e['doi'] ?? '')) !== '')
        cikti(['ok' => false, 'hata' => 'Bu çalışmaya DOI verilmiş. DOI kalıcı bir sözdür; kayıt arşivden çıkarılamaz.'], 409);

    /* ÜÇÜNCÜ KİŞİNİN EMEĞİ. Ad karşılaştırması tg_ad_anahtar() ile
       yapılır ki "Dr. Ayşe Demir" ile "ayse demir" aynı kişi sayılsın. */
    $ben = tg_ad_anahtar(hs_gorunen_ad($h));
    $baskasi = [];
    foreach (tg_dizi($e['hakemler'] ?? null) as $hk) {
        if (!is_array($hk) || trim((string)($hk['rapor'] ?? '')) === '') continue;
        $ad = trim((string)($hk['ad'] ?? ''));
        if ($ad !== '' && tg_ad_anahtar($ad) !== $ben) $baskasi[] = $ad;
    }
    foreach (tg_serhler($e) as $sh) {
        if (!is_array($sh)) continue;
        $ad = trim((string)($sh['ad'] ?? ''));
        if ($ad !== '' && tg_ad_anahtar($ad) !== $ben) $baskasi[] = $ad;
    }
    foreach (tg_yazar_anahtarlari($e) as $ak) {
        if ($ak !== '' && $ak !== $ben) $baskasi[] = (string)$ak;
    }
    $baskasi = array_values(array_unique($baskasi));
    if ($baskasi)
        cikti(['ok' => false, 'kisiler' => array_slice($baskasi, 0, 6),
               'hata' => 'Bu çalışmaya başkaları emek vermiş (' . implode(', ', array_slice($baskasi, 0, 3))
                       . '). Onların raporu, şerhi ya da yazarlığı da görünmez olurdu; kayıt işaretlenemez.'], 409);

    $y[$i]['deneme'] = true;
    $y[$i]['deneme_isaret'] = ['kim' => hs_gorunen_ad($h), 'tarih' => date('c'),
                               'gerekce' => mb_substr($gerekce, 0, 600, 'UTF-8'),
                               'onceki_tur' => (string)($e['tur'] ?? '')];
    yaz_json('yazilar.json', array_values($y));
    cikti(['ok' => true, 'deneme' => true, 'baslik' => (string)($e['baslik'] ?? '')]);
}

/* İŞARETLENMİŞ KAYITLAR: görünmez olan şeyin bir yerde görünmesi gerekir.
   Arşivden düşen bir kayıt panelden de düşerse, kimse onun durduğunu
   bilmez ve geri alınamaz. Liste yalnız bu uçla işaretlenenleri verir;
   deneme düzeninin kendi kayıtları oraya karışmaz. */
if ($yol === '/yonetim/deneme-isaretliler' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $out = [];
    foreach ($y as $e) {
        if (!is_array($e) || empty($e['deneme'])) continue;
        $im = $e['deneme_isaret'] ?? null;
        if (!is_array($im) || !empty($im['geri_alindi'])) continue;
        $out[] = ['baslik' => (string)($e['baslik'] ?? ''), 'anahtar' => (string)($e['slug'] ?? ($e['id'] ?? '')),
                  'yazar' => (string)($e['yazar'] ?? ''), 'tarih' => (string)($e['tarih'] ?? ''),
                  'kim' => (string)($im['kim'] ?? ''), 'ne_zaman' => (string)($im['tarih'] ?? ''),
                  'gerekce' => (string)($im['gerekce'] ?? '')];
    }
    cikti(['ok' => true, 'liste' => $out]);
}

if ($yol === '/yonetim/deneme-sil' && $metod === 'POST') {
    yonetim_yazma_gerek();
    require_once __DIR__ . '/../k/hesap.php';

    /* YALNIZCA 'deneme' İŞARETLİ KAYITLAR SİLİNİR. İşaret taşımayan bir
       kaydın buradan silinmesi mümkün olmamalıdır: bu uç bir temizlik
       aracıdır, bir silme aracı değil. */
    $hesaplar = hs_oku();
    $kalan = []; $hSil = 0;
    foreach ($hesaplar as $h) {
        if (is_array($h) && !empty($h['deneme'])) { $hSil++; continue; }
        $kalan[] = $h;
    }
    if ($hSil) hs_yaz($kalan);

    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $yKalan = []; $ySil = 0;
    foreach ($y as $e) {
        if (is_array($e) && !empty($e['deneme'])) { $ySil++; continue; }
        $yKalan[] = $e;
    }
    if ($ySil) yaz_json('yazilar.json', array_values($yKalan));

    cikti(['ok' => true, 'hesap' => $hSil, 'calisma' => $ySil]);
}

/* =====================================================================
   İZLEME (yönetici ve baş editör).
   ---------------------------------------------------------------------
   İSTENEN: "sistem yöneticisi için ayrı bir panel; hataları, kayıtları,
   çevrimiçi kullanıcıları izleyebilmek."

   AYRI BİR PANEL AÇILMADI ve gerekçesi ölçülebilir bir olaydır: bu
   sistem 8 Ağustos'a kadar /yonetim/index.html adresinde AYRI bir
   parolayla açılan ikinci bir panele sahipti ve o ayrılık kaldırıldı.
   İkinci bir yönetim kapısı, ikinci bir parola, ikinci bir oturum ve
   ikinci bir saldırı yüzeyi demektir. Üstelik bu ay yaşanan asıl arıza
   tam olarak buydu: kurulun beş üyesi de sisteme giremedi. Bir giriş
   sorununu, ikinci bir giriş ekleyerek çözemezsiniz.

   Bunun yerine izleme, zaten var olan panelin Yönetim sekmesine, zaten
   var olan "Sunucu durumu" kartının içine girer ve baş editör yetkisiyle
   açılır. İçerik ayrı, kapı aynı.

   YALNIZCA OKUR. Hiçbir şey değiştirmez, hiçbir ileti göndermez.
   Kayıtlarda sır bulunmaz: jetonlar, parolalar ve tam e-posta adresleri
   bu çıktının dışındadır.
   ===================================================================== */
if ($yol === '/yonetim/izleme' && $metod === 'GET') {
    alan_karar_yetkisi();
    $simdi = time();

    /* --- Son görülenler --- */
    $gorulen = [];
    $sp = veri_yolu('son-gorulme.json');
    if (is_file($sp)) {
        $d = json_decode((string)@file_get_contents($sp), true);
        if (is_array($d)) {
            foreach ($d as $v) {
                if (!is_array($v)) continue;
                $gorulen[] = ['ad' => (string)($v['ad'] ?? ''), 'zaman' => date('c', (int)($v['t'] ?? 0)),
                              'dakika' => (int)floor(($simdi - (int)($v['t'] ?? 0)) / 60),
                              'roller' => array_values((array)($v['roller'] ?? []))];
            }
            usort($gorulen, fn($a, $b) => $a['dakika'] <=> $b['dakika']);
            $gorulen = array_slice($gorulen, 0, 40);
        }
    }

    /* --- Hatalar --- */
    $hatalar = [];
    $hp = veri_yolu('hata-gunluk.jsonl');
    if (is_file($hp)) {
        foreach (array_slice(array_filter(explode("\n", (string)file_get_contents($hp))), -40) as $s2) {
            $x = json_decode($s2, true);
            if (is_array($x)) $hatalar[] = $x;
        }
        $hatalar = array_reverse($hatalar);
    }

    /* --- Telegram kütüğü --- */
    $tg = [];
    $tp = veri_yolu('telegram.log');
    if (is_file($tp)) {
        foreach (array_slice(array_values(array_filter(array_map('trim', explode("\n", (string)file_get_contents($tp))))), -20) as $s2) {
            $p2 = explode("\t", $s2);
            $tg[] = ['zaman' => (string)($p2[0] ?? ''), 'durum' => (string)($p2[1] ?? ''),
                     'etiket' => (string)($p2[2] ?? ''), 'not' => (string)($p2[5] ?? '')];
        }
        $tg = array_reverse($tg);
    }

    cikti(['ok' => true, 'izleme' => [
        'gorulen' => $gorulen,
        'hatalar' => $hatalar,
        'telegram' => $tg,
        /* Son yirmi dört saatte kaç ayrı hesap görüldü: tek sayı, uzun
           listeye bakmadan "sistem yaşıyor mu" sorusunu yanıtlar. */
        'gun_sayisi' => count(array_filter($gorulen, fn($g) => $g['dakika'] <= 1440)),
        'zaman' => date('c'),
    ]]);
}

/* =====================================================================
   Sunucu durumu (yönetici ve baş editör).
   Bir yayın sistemi, kendi altyapısının çalışıp çalışmadığını
   kullanıcıya bir arıza yaşatmadan görebilmelidir. Burada yalnızca
   okuma yapılır; hiçbir şey değiştirilmez, hiçbir ileti gönderilmez.
   Gizli bilgi dönmez: anahtarlar, parolalar ve jetonlar bu çıktıda yok.
   ===================================================================== */
if ($yol === '/yonetim/sunucu-durum' && $metod === 'GET') {
    alan_karar_yetkisi();

    /* Bir dizinin gerçekten yazılabilir olup olmadığını deneyerek ölçer.
       is_writable() bazı sunucularda yanıltıcıdır; deneme dosyası kesin sonuç verir. */
    $yazilabilir = function (string $dizin): array {
        /* Dizin henüz açılmamış olabilir; bu bir arıza değildir, çünkü ilk
           kullanımda kendiliğinden açılır. Bu durumda üst dizinin yazılabilir
           olup olmadığına bakılır: asıl soru odur. */
        $var = is_dir($dizin);
        $hedef = $var ? $dizin : dirname($dizin);
        if (!is_dir($hedef)) return ['var' => false, 'yazilabilir' => false, 'not' => 'Üst dizin de yok.'];
        $deneme = $hedef . '/.yazma-denemesi-' . bin2hex(random_bytes(4));
        $ok = @file_put_contents($deneme, 'x') !== false;
        if ($ok) @unlink($deneme);
        return ['var' => $var, 'yazilabilir' => $ok,
                'not' => $ok ? ($var ? '' : 'Dizin ilk kullanımda açılacak.') : 'Yazma izni yok.'];
    };

    $dataDizin = veri_yolu('');
    $dataDizin = rtrim($dataDizin, '/');

    /* E-posta kaydının son satırları: gerçekten ileti gitmiş mi, gitmemiş mi */
    $epostaLog = [];
    $gonderildi = 0; $basarisiz = 0;
    $lp = veri_yolu('eposta.log');
    if (is_file($lp)) {
        $tum = array_values(array_filter(array_map('trim', explode("\n", (string)file_get_contents($lp)))));
        foreach ($tum as $s2) {
            if (strpos($s2, "\tGONDERILDI\t") !== false) $gonderildi++;
            elseif (strpos($s2, "\tBASARISIZ\t") !== false) $basarisiz++;
        }
        foreach (array_slice($tum, -25) as $s2) {
            $p2 = explode("\t", $s2);
            /* Alıcı adresi tam gösterilmez; sızıntı olmaması için maskelenir. */
            $ad2 = (string)($p2[2] ?? '');
            if (strpos($ad2, '@') !== false) {
                [$sol, $sag] = explode('@', $ad2, 2);
                $ad2 = mb_substr($sol, 0, 2) . str_repeat('*', max(1, mb_strlen($sol) - 2)) . '@' . $sag;
            }
            /* 5. ve 6. sütun sonradan eklendi (yol ve gerekçe). Eski
               satırlarda yoktur; boş dönerler, kayıt bozulmaz. */
            $epostaLog[] = ['zaman' => (string)($p2[0] ?? ''), 'durum' => (string)($p2[1] ?? ''),
                            'alici' => $ad2, 'konu' => (string)($p2[3] ?? ''),
                            'yol' => (string)($p2[4] ?? ''), 'neden' => (string)($p2[5] ?? '')];
        }
        $epostaLog = array_reverse($epostaLog);
    }

    $serbest = @disk_free_space($dataDizin);
    $toplam  = @disk_total_space($dataDizin);

    cikti(['ok' => true, 'durum' => [
        'php' => [
            'surum'        => PHP_VERSION,
            'mail_var'     => function_exists('mail'),
            'gd_var'       => function_exists('imagecreatetruecolor'),
            'webp_var'     => function_exists('imagewebp'),
            'curl_var'     => function_exists('curl_init'),
            'intl_var'     => class_exists('IntlDateFormatter'),
            'bellek'       => (string)ini_get('memory_limit'),
            'yukleme_boyu' => (string)ini_get('upload_max_filesize'),
            'gonderi_boyu' => (string)ini_get('post_max_size'),
        ],
        'dizinler' => [
            'veri'  => $yazilabilir($dataDizin),
            'resim' => $yazilabilir(function_exists('tg_resim_dizini') ? tg_resim_dizini() : $dataDizin . '/resim'),
            'site_resim' => $yazilabilir(__DIR__ . '/../photo/site'),
        ],
        'disk' => [
            'serbest_mb' => is_float($serbest) ? (int)round($serbest / 1048576) : null,
            'toplam_mb'  => is_float($toplam) ? (int)round($toplam / 1048576) : null,
        ],
        'eposta' => [
            'gonderildi' => $gonderildi,
            'basarisiz'  => $basarisiz,
            'son'        => $epostaLog,
            /* Hangi yolun açık olduğu, kaç iletinin gittiğinden önce
               gelen sorudur: aktarıcı yoksa sayılar zaten yanıltıcıdır. */
            'aktarici'   => function_exists('pst_kurulu') && pst_kurulu(),
            'sunucu'     => function_exists('pst_ayar') ? (string)pst_ayar()['sunucu'] : '',
        ],
        'zaman' => date('c'),
    ]]);
}

/* E-posta yolunun sınanması (yönetici ve baş editör).
   İleti yalnızca isteği yapan kişinin kendi kayıtlı adresine gider.
   Alıcı dışarıdan verilemez; böylece bu uç bir yönlendirme aracına
   dönüşemez. Amaç, bir kefil daveti gerçekten gerekmeden önce
   sunucunun posta yolunun çalıştığını bilmektir. */
if ($yol === '/yonetim/eposta-sina' && $metod === 'POST') {
    $kim = alan_karar_yetkisi();
    $hedef = trim((string)($kim['eposta'] ?? ''));
    if ($hedef === '') {
        /* Sistem yöneticisi hesapsız girmiş olabilir: kurucu baş editörün adresi kullanılır. */
        foreach ((array)tg_ayar('kurul', []) as $k) {
            if (is_array($k) && !empty($k['kurucu']) && !empty($k['eposta'])) { $hedef = (string)$k['eposta']; break; }
        }
    }
    if ($hedef === '') cikti(['ok' => false, 'hata' => 'Sınama için bir adres bulunamadı.'], 400);
    if (kotu_say('epostasina:' . substr(hash('sha256', $hedef), 0, 16)) > 5) {
        cikti(['ok' => false, 'hata' => 'Çok fazla sınama yapıldı. Lütfen sonra tekrar deneyin.'], 429);
    }
    $marka = (string)tg_ayar('marka', 'Kutadgu');
    $ok = eposta_gonder($hedef, $marka . ' | Posta yolu sınaması',
        "Bu ileti, sunucunun posta yolunun çalıştığını doğrulamak için gönderildi.\n" .
        "Başka bir anlamı yoktur; yanıtlamanız gerekmez.\n\n" .
        "Zaman: " . date('c') . "\n" .
        "İsteyen: " . (string)($kim['ad'] ?? '') . "\n\n" .
        "---\n" .
        "This message was sent to verify that the server's mail path works.\n" .
        "It means nothing else and needs no reply.\n\n" .
        $marka . "\n" . tg_kok() . "\n");
    /* Sınamanın değeri, başarısız olduğunda NEDEN başarısız olduğunu
       söylemesindedir. "Gönderilemedi" bir yanıt değildir; "535 Username
       and Password not accepted" ise doğrudan çözümü gösterir. */
    $aktarici = function_exists('pst_kurulu') && pst_kurulu();
    $neden = function_exists('pst_son_hata') ? pst_son_hata() : '';
    cikti(['ok' => true, 'gonderildi' => $ok, 'aktarici' => $aktarici, 'neden' => $neden,
           'alici' => preg_replace('/^(..).*(@.*)$/u', '$1***$2', $hedef),
           'mesaj' => $ok
               ? ($aktarici
                   ? 'Aktarıcı iletiyi kabul etti. Gelen kutunuzu ve gereksiz posta klasörünü denetleyin.'
                   : 'Yerel posta yazılımı iletiyi aldı; bu bir teslim güvencesi DEĞİLDİR. Bu sunucunun posta kimliği (SPF/DKIM/PTR) yok, ileti büyük olasılıkla düşürülecek. Aktarıcı ayarlayın.')
               : ($aktarici ? ('Aktarıcı iletiyi kabul etmedi: ' . $neden)
                            : 'Posta aktarıcısı ayarlanmamış ve sunucunun kendi posta yolu çalışmıyor.')]);
}

/* Çalışma dilleri. Herkese açıktır: gönderim ekranı, yönetim ekranı ve
   diller sayfası aynı listeyi okur, böylece liste tek yerde durur.
   Her dilin adı kendi dilindedir; bir dilin adını başka bir dilde
   okumak zorunda kalmak, bu sistemin karşı olduğu şeyin küçük bir
   örneğidir. */
if ($yol === '/diller' && $metod === 'GET') {
    $out = [];
    foreach ((array)tg_ayar('calisma_dilleri', []) as $dk => $dad) {
        $out[] = ['kod' => (string)$dk, 'ad' => (string)$dad];
    }
    cikti(['ok' => true, 'diller' => $out]);
}

/* Sınıflandırmanın tamamı (arama ve süzme ekranları için) */
if ($yol === '/alanlar' && $metod === 'GET') {
    $en = ((string)($_GET['lang'] ?? '') === 'en');
    $out = [];
    foreach (al_secim($en) as $ak => $av) {
        $out[] = ['kod' => $ak, 'ana' => $av['ana'], 'ad' => $av['ad'],
                  'dallar' => $av['dallar'], 'ortak' => array_keys($av['ortak'] ?? [])];
    }
    cikti(['ok' => true, 'alanlar' => $out]);
}

/* Tam yazı listesi (yönetici): hakem jetonları ve yazar erişimi dahil */
if ($yol === '/yonetim/yazilar-tam' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    usort($y, fn($a, $b) => strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? '')));
    $oku = oku_json('yazi-oku.json', []); if (!is_array($oku)) $oku = [];
    foreach ($y as $i => $e) {
        $o = $oku[(string)($e['id'] ?? '')] ?? null;
        $y[$i]['oku_toplam'] = is_array($o) ? (int)($o['toplam'] ?? 0) : 0;
        $y[$i]['oku_tekil'] = is_array($o) ? count($o['tekil'] ?? []) : 0;
        /* Ham kaydı dışarı veren bu uç da aşamayı yanına koyar: kaydı alıp
           kendi ekranını kuran taraf "hakemli mi" sorusunu 'tur'dan
           yanıtlamak zorunda kalmasın. */
        $y[$i]['asama'] = tg_hakem_asamasi($e);
        $y[$i]['rapor_sayisi'] = tg_rapor_sayisi($e);
    }
    cikti(['ok' => true, 'yazilar' => $y]);
}

/* ================= MAKALE OKUMA ANALİTİĞİ ================= */
/* Okuma kaydı (herkese açık; yazi.php sayfadan ayrılırken gönderir) */
if ($yol === '/oku-kayit' && $metod === 'POST') {
    $g = govde_json(); if (!$g) $g = $_POST;
    $id = mb_substr(trim((string)($g['id'] ?? '')), 0, 40);
    if ($id === '') cikti(['ok' => true]);
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    if ($ua === '' || preg_match('/bot|crawl|spider|slurp|bing|google|yandex|duckduck|baidu|facebookexternal|preview|monitor|curl|wget|python|headless|lighthouse|pingdom|uptime/', $ua)) cikti(['ok' => true]);
    $sure = (int)($g['sure'] ?? 0);
    if ($sure < 0) $sure = 0;
    if ($sure > 10800) $sure = 10800; /* 3 saatten fazlası sayılmaz (sekme açık unutulmuş) */
    $ip = ip_al();
    /* Kötüye kullanım koruması: aynı IP'den 2 saatte en çok 200 kayıt (disk şişirme saldırısı) */
    if (kotu_say('oku:' . substr(hash('sha256', $ip), 0, 16)) > 200) cikti(['ok' => true]);
    /* Günlük dosyası aşırı büyümesin (50 MB üstünde yeni kayıt alınmaz) */
    $logYol = veri_yolu('okuma-' . date('Y-m') . '.jsonl');
    if (is_file($logYol) && filesize($logYol) > 50 * 1024 * 1024) cikti(['ok' => true]);
    $geo = geo_al($ip);
    $satir = [
        't' => date('c'),
        'id' => $id,
        'baslik' => mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($g['baslik'] ?? ''))), 0, 200),
        'sure' => $sure,
        'oran' => max(0, min(100, (int)($g['oran'] ?? 0))),
        'lang' => ((string)($g['lang'] ?? '') === 'en') ? 'en' : 'tr',
        /* Ham adres YAZILMAZ. Yayın ilkeleri, ziyaretçi adresinin geri
           çevrilemeyecek biçimde özetlenerek saklandığını söyler; kayıt
           da onu söylemelidir. Aşağıdaki özet aynı okuyucunun tekrar
           tekrar açmasını tek okuma saymaya yeter ve adresi geri
           vermez. Toplanmayan veri sızdırılamaz.
           The raw address is not written. The editorial policies say the
           visitor's address is stored in an irreversible digest, and the
           record must say the same. */
        'iph' => substr(hash('sha256', $ip . '|oku'), 0, 16),
        'sehir' => (string)($geo['sehir'] ?? ''),
        'ulke' => (string)($geo['ulke'] ?? ''),
        'cihaz' => (strpos($ua, 'mobi') !== false ? 'mobil' : 'masaustu'),
        'ref' => mb_substr((string)($g['ref'] ?? ''), 0, 200),
        'oturum' => mb_substr((string)($g['oturum'] ?? ''), 0, 40),
    ];
    @file_put_contents($logYol, json_encode($satir, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    cikti(['ok' => true]);
}

/* Okuma raporu (yönetici): hangi makale, nereden, ne kadar süre */
if ($yol === '/yonetim/dergi-rapor' && $metod === 'GET') {
    yonetim_okuma_gerek();
    $ay = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['ay'] ?? '')) ? (string)$_GET['ay'] : date('Y-m');
    /* Mevcut ay dosyaları */
    $aylar = [];
    foreach (glob(dirname(veri_yolu('x')) . '/okuma-*.jsonl') ?: [] as $p) {
        if (preg_match('/okuma-(\d{4}-\d{2})\.jsonl$/', $p, $m)) $aylar[] = $m[1];
    }
    rsort($aylar);
    /* Oturum+makale bazında tekilleştir, en uzun süreyi al */
    $f = veri_yolu('okuma-' . $ay . '.jsonl');
    $kayit = [];
    if (is_file($f)) {
        foreach (file($f, FILE_IGNORE_NEW_LINES) ?: [] as $l) {
            $r = json_decode((string)$l, true); if (!is_array($r)) continue;
            $k = (string)($r['oturum'] ?? '') . '|' . (string)($r['id'] ?? '');
            if (!isset($kayit[$k]) || (int)($r['sure'] ?? 0) > (int)($kayit[$k]['sure'] ?? 0)) $kayit[$k] = $r;
        }
    }
    $kayit = array_values($kayit);
    /* Makale başlıklarını yazilar.json'dan tamamla */
    $yz = oku_json('yazilar.json', []); if (!is_array($yz)) $yz = [];
    $baslikMap = [];
    foreach ($yz as $e) { if (is_array($e)) $baslikMap[(string)($e['id'] ?? '')] = (string)($e['baslik'] ?? ''); }

    $makale = []; $sehirler = []; $ulkeler = []; $cihazlar = ['mobil' => 0, 'masaustu' => 0];
    $gunluk = []; $toplamSure = 0; $tekilSet = [];
    foreach ($kayit as $r) {
        $mid = (string)($r['id'] ?? ''); if ($mid === '') continue;
        $s = (int)($r['sure'] ?? 0);
        if (!isset($makale[$mid])) $makale[$mid] = ['id' => $mid, 'baslik' => $baslikMap[$mid] ?? (string)($r['baslik'] ?? ''), 'okuma' => 0, 'tekil' => [], 'sure' => 0, 'oran' => 0, 'oranN' => 0];
        $makale[$mid]['okuma']++;
        $makale[$mid]['sure'] += $s;
        $makale[$mid]['tekil'][(string)($r['iph'] ?? '')] = true;
        if ((int)($r['oran'] ?? 0) > 0) { $makale[$mid]['oran'] += (int)$r['oran']; $makale[$mid]['oranN']++; }
        $toplamSure += $s;
        $tekilSet[(string)($r['iph'] ?? '')] = true;
        $sh = trim((string)($r['sehir'] ?? '')); if ($sh !== '') $sehirler[$sh] = ($sehirler[$sh] ?? 0) + 1;
        $ul = trim((string)($r['ulke'] ?? '')); if ($ul !== '') $ulkeler[$ul] = ($ulkeler[$ul] ?? 0) + 1;
        $cz = ((string)($r['cihaz'] ?? '') === 'mobil') ? 'mobil' : 'masaustu'; $cihazlar[$cz]++;
        $gun = substr((string)($r['t'] ?? ''), 0, 10); if ($gun !== '') $gunluk[$gun] = ($gunluk[$gun] ?? 0) + 1;
    }
    foreach ($makale as $k => $m) {
        $makale[$k]['tekil'] = count($m['tekil']);
        $makale[$k]['ort_sure'] = $m['okuma'] > 0 ? (int)round($m['sure'] / $m['okuma']) : 0;
        $makale[$k]['ort_oran'] = $m['oranN'] > 0 ? (int)round($m['oran'] / $m['oranN']) : 0;
        unset($makale[$k]['oran'], $makale[$k]['oranN']);
    }
    usort($makale, fn($a, $b) => $b['okuma'] <=> $a['okuma']);
    arsort($sehirler); arsort($ulkeler); ksort($gunluk);
    /* Son okumalar (en yeni 60).
       Toplu sayılar bütün editörlere açıktır; tek tek okuma kayıtları
       değildir. Bir şehir dökümü kimseyi göstermez, altmış satırlık bir
       kayıt listesi gösterebilir. Bu yüzden liste yalnızca baş editöre
       konur ve editöre giden yanıta HİÇ girmez: gizlemek yetmez, veri
       gitmemelidir. Ham adres artık kayıtta da yoktur.
       Aggregate counts are open to every editor; individual reading
       records are not. The list is therefore added only for a chief
       editor and never enters the response sent to an editor. */
    $sonGoster = yonetim_yazma_mi();
    usort($kayit, fn($a, $b) => strcmp((string)($b['t'] ?? ''), (string)($a['t'] ?? '')));
    $son = [];
    foreach ($sonGoster ? array_slice($kayit, 0, 60) : [] as $r) {
        $son[] = [
            't' => (string)($r['t'] ?? ''),
            'baslik' => $baslikMap[(string)($r['id'] ?? '')] ?? (string)($r['baslik'] ?? ''),
            'sure' => (int)($r['sure'] ?? 0), 'oran' => (int)($r['oran'] ?? 0),
            'sehir' => (string)($r['sehir'] ?? ''), 'ulke' => (string)($r['ulke'] ?? ''),
            'cihaz' => (string)($r['cihaz'] ?? ''),
            'lang' => (string)($r['lang'] ?? ''), 'ref' => (string)($r['ref'] ?? ''),
        ];
    }
    cikti(['ok' => true, 'ay' => $ay, 'aylar' => $aylar,
        'ozet' => ['okuma' => count($kayit), 'tekil' => count($tekilSet),
                   'ort_sure' => count($kayit) > 0 ? (int)round($toplamSure / count($kayit)) : 0,
                   'toplam_sure' => $toplamSure],
        'makale' => array_values($makale),
        'sehir' => array_slice($sehirler, 0, 25, true),
        'ulke' => array_slice($ulkeler, 0, 15, true),
        'cihaz' => $cihazlar, 'gunluk' => $gunluk, 'son' => $son, 'tekil_kayit' => $sonGoster]);
}

/* ================= WORD (.docx) -> SİSTEM BİÇİMİ ================= */
/* Word paragraf/başlık/tablo/liste yapısını sitenin HTML şemasına çevirir. */
function docx_metin(DOMElement $p, DOMXPath $xp): string {
    $par = '';
    foreach ($xp->query('.//w:r', $p) as $r) {
        $t = '';
        foreach ($xp->query('.//w:t', $r) as $tn) $t .= $tn->textContent;
        foreach ($xp->query('.//w:tab', $r) as $x) $t .= ' ';
        foreach ($xp->query('.//w:br', $r) as $x) $t .= "\n";
        if ($t === '') continue;
        $t = htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $kalin = $xp->query('.//w:rPr/w:b', $r)->length > 0;
        $egik  = $xp->query('.//w:rPr/w:i', $r)->length > 0;
        $ust   = $xp->query('.//w:rPr/w:vertAlign[@w:val="superscript"]', $r)->length > 0;
        if ($ust) $t = '<sup>' . $t . '</sup>';
        if ($egik) $t = '<em>' . $t . '</em>';
        if ($kalin) $t = '<strong>' . $t . '</strong>';
        $par .= $t;
    }
    return trim(preg_replace('/\s+/u', ' ', str_replace("\n", '<br>', $par)) ?? '');
}
function docx_baslik_duzeyi(DOMElement $p, DOMXPath $xp, string $duzMetin): int {
    /* 1) Word stil adı  2) outline seviyesi  3) biçimsel sezgi */
    foreach ($xp->query('./w:pPr/w:pStyle', $p) as $st) {
        $v = strtolower((string)$st->getAttribute('w:val'));
        if (preg_match('/^(heading|ba[sş]l[iı]k|balk|titre|berschrift)\s*([1-6])/u', $v, $m)) return (int)$m[2];
        if (preg_match('/^(title|ba[sş]l[iı]k)$/u', $v)) return 1;
    }
    foreach ($xp->query('./w:pPr/w:outlineLvl', $p) as $o) {
        $v = (int)$o->getAttribute('w:val');
        if ($v >= 0 && $v <= 5) return $v + 1;
    }
    /* Sezgi: kısa, nokta ile bitmeyen, tamamı kalın paragraf = başlık */
    $uz = mb_strlen($duzMetin, 'UTF-8');
    if ($uz > 0 && $uz <= 90 && !preg_match('/[.:;،]$/u', $duzMetin)) {
        $tumKalin = $xp->query('.//w:r', $p)->length > 0 && $xp->query('.//w:r[not(.//w:rPr/w:b)][normalize-space(.//w:t)!=""]', $p)->length === 0;
        if ($tumKalin) return 2;
        if (preg_match('/^\d+(\.\d+)*[\.\)]?\s+\S/u', $duzMetin)) return substr_count(explode(' ', $duzMetin)[0], '.') >= 2 ? 3 : 2;
    }
    return 0;
}
function docx_coz(string $dosya): array {
    if (!class_exists('ZipArchive')) return ['ok' => false, 'hata' => 'Sunucuda ZipArchive eklentisi yok; Word dönüşümü yapılamıyor.'];
    $z = new ZipArchive();
    if ($z->open($dosya) !== true) return ['ok' => false, 'hata' => 'Word dosyası açılamadı. Dosya bozuk olabilir.'];
    /* Zip bombası koruması: açılmış boyut ve dosya sayısı sınırı */
    if ($z->numFiles > 3000) { $z->close(); return ['ok' => false, 'hata' => 'Belge beklenenden çok fazla parça içeriyor.']; }
    $ist = $z->statName('word/document.xml');
    if ($ist === false) { $z->close(); return ['ok' => false, 'hata' => 'Belge içeriği bulunamadı. Dosyanın .docx (Word 2007 ve üzeri) olduğundan emin olun; eski .doc biçimi desteklenmez.']; }
    if ((int)($ist['size'] ?? 0) > 80 * 1024 * 1024) { $z->close(); return ['ok' => false, 'hata' => 'Belge içeriği çok büyük.']; }
    $xml = $z->getFromName('word/document.xml', 80 * 1024 * 1024);
    $z->close();
    if ($xml === false || $xml === '') return ['ok' => false, 'hata' => 'Belge içeriği okunamadı. Dosyanın .docx (Word 2007 ve üzeri) olduğundan emin olun; eski .doc biçimi desteklenmez.'];
    /* XXE koruması: dış varlık çözümlemesi ve ağ erişimi kapalı (LIBXML_NOENT KULLANILMAZ) */
    $dom = new DOMDocument();
    $eskiHata = libxml_use_internal_errors(true);
    $yuklendi = $dom->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOCDATA);
    libxml_clear_errors();
    libxml_use_internal_errors($eskiHata);
    if (!$yuklendi) return ['ok' => false, 'hata' => 'Word içeriği çözümlenemedi.'];
    /* DTD / dış varlık tanımı içeren belgeler reddedilir */
    if ($dom->doctype !== null) return ['ok' => false, 'hata' => 'Güvenlik gereği DTD içeren belgeler işlenmez.'];
    $xp = new DOMXPath($dom);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $govde = $xp->query('//w:body')->item(0);
    if (!$govde) return ['ok' => false, 'hata' => 'Word gövdesi bulunamadı.'];

    $bloklar = [];   /* ['tip'=>'h'|'p'|'li'|'tablo', 'duzey'=>, 'html'=>, 'duz'=>] */
    foreach ($govde->childNodes as $dugum) {
        if (!($dugum instanceof DOMElement)) continue;
        $ad = $dugum->localName;
        if ($ad === 'p') {
            $html = docx_metin($dugum, $xp);
            $duz = trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
            if ($duz === '') continue;
            /* Liste: doğrudan numaralandırma ya da "List Bullet / List Number / Liste" stili */
            $liste = $xp->query('./w:pPr/w:numPr', $dugum)->length > 0;
            $siraliListe = false;
            foreach ($xp->query('./w:pPr/w:pStyle', $dugum) as $st) {
                $sv = strtolower((string)$st->getAttribute('w:val'));
                if (preg_match('/list|liste|madde/', $sv)) {
                    $liste = true;
                    if (preg_match('/number|numara|sayı|sirali|siral/', $sv)) $siraliListe = true;
                }
            }
            $dz = docx_baslik_duzeyi($dugum, $xp, $duz);
            if ($liste && $dz === 0) { $bloklar[] = ['tip' => 'li', 'sirali' => $siraliListe, 'html' => $html, 'duz' => $duz]; }
            elseif ($dz > 0) { $bloklar[] = ['tip' => 'h', 'duzey' => $dz, 'html' => $html, 'duz' => $duz]; }
            else { $bloklar[] = ['tip' => 'p', 'html' => $html, 'duz' => $duz]; }
        } elseif ($ad === 'tbl') {
            $satirlar = [];
            foreach ($xp->query('./w:tr', $dugum) as $tr) {
                $hucre = [];
                foreach ($xp->query('./w:tc', $tr) as $tc) {
                    $ic = [];
                    foreach ($xp->query('./w:p', $tc) as $tp) { $m = docx_metin($tp, $xp); if ($m !== '') $ic[] = $m; }
                    $hucre[] = implode('<br>', $ic);
                }
                if ($hucre) $satirlar[] = $hucre;
            }
            if ($satirlar) {
                $t = "<table>\n";
                foreach ($satirlar as $i => $sr) {
                    $et = ($i === 0) ? 'th' : 'td';
                    $t .= "  <tr>" . implode('', array_map(fn($c) => "<$et>$c</$et>", $sr)) . "</tr>\n";
                }
                $t .= "</table>";
                $bloklar[] = ['tip' => 'tablo', 'html' => $t, 'duz' => ''];
            }
        }
    }
    if (!$bloklar) return ['ok' => false, 'hata' => 'Belgede metin bulunamadı.'];

    /* Bölüm etiketlerini tanı: başlık / özet / anahtar kelimeler / kaynakça */
    $etiket = function(string $s): string {
        $n = mb_strtolower(trim($s), 'UTF-8');
        $n = strtr($n, ['ç'=>'c','ğ'=>'g','ı'=>'i','İ'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','â'=>'a']);
        $n = trim(preg_replace('/[^a-z ]+/', ' ', $n) ?? '');
        $n = trim(preg_replace('/\s+/', ' ', $n) ?? '');
        if (preg_match('/^(ozet|oz)$/', $n)) return 'ozet';
        if (preg_match('/^(abstract|summary)$/', $n)) return 'ozet_en';
        if (preg_match('/^(anahtar kelimeler|anahtar sozcukler|anahtar kavramlar)$/', $n)) return 'anahtar';
        if (preg_match('/^(keywords|key words)$/', $n)) return 'anahtar_en';
        if (preg_match('/^(kaynakca|kaynaklar|referanslar)$/', $n)) return 'kaynakca';
        if (preg_match('/^(references|bibliography|works cited)$/', $n)) return 'kaynakca_en';
        return '';
    };
    /* Satır içi "Özet: ..." biçimini de yakala */
    $satirIci = function(string $s) use ($etiket): array {
        if (preg_match('/^([^:：]{3,40})\s*[:：]\s*(.+)$/us', $s, $m)) {
            $e = $etiket($m[1]);
            if ($e !== '') return [$e, trim($m[2])];
        }
        return ['', ''];
    };

    $sonuc = ['baslik' => '', 'ozet' => '', 'ozet_en' => '', 'anahtar' => '', 'anahtar_en' => '',
              'kaynakca' => '', 'kaynakca_en' => '', 'metin' => ''];
    $bolum = 'bas';     /* bas -> govde; ozet/anahtar/kaynakca */
    $metinP = []; $ozetP = []; $ozetEnP = []; $kaynP = []; $kaynEnP = [];
    $listeAcik = false;  /* false ya da 'ul'/'ol' */

    /* İlk anlamlı blok başlık kabul edilir (h1 ya da ilk paragraf) */
    foreach ($bloklar as $i => $b) {
        if ($b['duz'] === '' && $b['tip'] !== 'tablo') continue;
        if ($b['tip'] === 'h' || $b['tip'] === 'p') { $sonuc['baslik'] = $b['duz']; unset($bloklar[$i]); break; }
    }
    $bloklar = array_values($bloklar);

    $ekle = function(array &$hedef, string $html, bool $liOl = false, bool $sirali = false) use (&$listeAcik) {
        if ($liOl) {
            $et = $sirali ? 'ol' : 'ul';
            if ($listeAcik !== false && $listeAcik !== $et) { $hedef[] = '</' . $listeAcik . '>'; $listeAcik = false; }
            if ($listeAcik === false) { $hedef[] = '<' . $et . '>'; $listeAcik = $et; }
            $hedef[] = '  <li>' . $html . '</li>';
        } else {
            if ($listeAcik !== false) { $hedef[] = '</' . $listeAcik . '>'; $listeAcik = false; }
            $hedef[] = $html;
        }
    };

    foreach ($bloklar as $b) {
        /* Bölüm başlığı mı? */
        if ($b['tip'] === 'h' || $b['tip'] === 'p') {
            $e = $etiket($b['duz']);
            if ($e !== '') { if ($listeAcik !== false) { $metinP[] = '</' . $listeAcik . '>'; $listeAcik = false; } $bolum = $e; continue; }
            /* Özet/anahtar/kaynakça bölümü, etiket olmayan bir BAŞLIK görülünce biter (gövdeye dönülür) */
            if ($b['tip'] === 'h' && $bolum !== 'bas') $bolum = 'bas';
            [$e2, $kalan] = $satirIci($b['duz']);
            if ($e2 !== '' && $kalan !== '') {
                if ($e2 === 'ozet') $ozetP[] = '<p>' . htmlspecialchars($kalan, ENT_QUOTES, 'UTF-8') . '</p>';
                elseif ($e2 === 'ozet_en') $ozetEnP[] = '<p>' . htmlspecialchars($kalan, ENT_QUOTES, 'UTF-8') . '</p>';
                elseif ($e2 === 'anahtar') $sonuc['anahtar'] = $kalan;
                elseif ($e2 === 'anahtar_en') $sonuc['anahtar_en'] = $kalan;
                elseif ($e2 === 'kaynakca') $bolum = 'kaynakca';
                elseif ($e2 === 'kaynakca_en') $bolum = 'kaynakca_en';
                continue;
            }
        }
        $html = (string)$b['html'];
        if ($bolum === 'ozet')      { if ($b['tip'] !== 'tablo') $ozetP[] = '<p>' . $html . '</p>'; continue; }
        if ($bolum === 'ozet_en')   { if ($b['tip'] !== 'tablo') $ozetEnP[] = '<p>' . $html . '</p>'; continue; }
        if ($bolum === 'anahtar')   { if ($sonuc['anahtar'] === '') $sonuc['anahtar'] = $b['duz']; continue; }
        if ($bolum === 'anahtar_en'){ if ($sonuc['anahtar_en'] === '') $sonuc['anahtar_en'] = $b['duz']; continue; }
        if ($bolum === 'kaynakca')  { if ($b['tip'] !== 'tablo') $kaynP[] = '<p>' . $html . '</p>'; continue; }
        if ($bolum === 'kaynakca_en'){ if ($b['tip'] !== 'tablo') $kaynEnP[] = '<p>' . $html . '</p>'; continue; }
        /* Gövde */
        if ($b['tip'] === 'h') {
            $dz = max(2, min(4, (int)($b['duzey'] ?? 1) + 1));
            $ekle($metinP, '<h' . $dz . '>' . $html . '</h' . $dz . '>');
        } elseif ($b['tip'] === 'li') {
            $ekle($metinP, $html, true, !empty($b['sirali']));
        } elseif ($b['tip'] === 'tablo') {
            $ekle($metinP, $html);
        } else {
            $ekle($metinP, '<p>' . $html . '</p>');
        }
    }
    if ($listeAcik !== false) $metinP[] = '</' . $listeAcik . '>';

    $sonuc['metin']       = trim(implode("\n", $metinP));
    $sonuc['ozet']        = trim(implode("\n", $ozetP));
    $sonuc['ozet_en']     = trim(implode("\n", $ozetEnP));
    $sonuc['kaynakca']    = trim(implode("\n", $kaynP));
    $sonuc['kaynakca_en'] = trim(implode("\n", $kaynEnP));
    $sonuc['anahtar']     = trim(preg_replace('/\s*[;،]\s*/u', ', ', $sonuc['anahtar']) ?? '');
    $sonuc['anahtar_en']  = trim(preg_replace('/\s*[;،]\s*/u', ', ', $sonuc['anahtar_en']) ?? '');
    $sonuc['ok'] = true;
    $sonuc['istatistik'] = [
        'blok' => count($bloklar),
        'kelime' => preg_match_all('/\p{L}[\p{L}\p{M}\'’-]*/u', strip_tags($sonuc['metin'])) ?: 0,
        'baslik_sayisi' => substr_count($sonuc['metin'], '<h'),
        'tablo' => substr_count($sonuc['metin'], '<table'),
    ];
    return $sonuc;
}
/* Word yükle + çevir. Yönetici oturumu VEYA yazar (token+e-posta+şifre) kullanabilir. */
if ($yol === '/word-cevir' && $metod === 'POST') {
    /* Belgeyi aktaran, çalışmayı yazandır. Yönetim ekranında bir zamanlar
       ayrı bir aktarma vardı; kaldırıldı, çünkü aynı işi yazar.php zaten
       yazarın kendi ekranında yapıyor. Bu yüzden baş editöre burada bir
       yetki verilmez: kullanılmayan yetki, verilmemesi gereken yetkidir.
       Whoever imports a document is the one who wrote the work. */
    $yetkili = girisli();
    if (!$yetkili) {
        $t = preg_replace('/[^a-f0-9]/', '', (string)($_POST['t'] ?? ''));
        $mail = mb_strtolower(trim((string)($_POST['mail'] ?? '')), 'UTF-8');
        $sifre = (string)($_POST['sifre'] ?? '');
        $ipk = 'word:' . substr(hash('sha256', ip_al()), 0, 16);
        if (kotu_say($ipk) > 40) cikti(['ok' => false, 'hata' => 'Çok fazla deneme.'], 429);
        $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
        $d = yazar_yetki($y, $_POST);
        if ($d === null || isset($d['yetkisiz']) || isset($d['eposta_yanlis']) || isset($d['sifre_yanlis'])) cikti(['ok' => false, 'hata' => 'Yetkisiz.'], 403);
        $yetkili = true;
    }
    if (empty($_FILES['dosya']) || ((int)($_FILES['dosya']['error'] ?? 4)) !== 0) cikti(['ok' => false, 'hata' => 'Dosya alınamadı.'], 400);
    if ((int)($_FILES['dosya']['size'] ?? 0) > 25 * 1024 * 1024) cikti(['ok' => false, 'hata' => 'Dosya çok büyük (en fazla 25 MB).'], 400);
    $uz = strtolower(pathinfo((string)($_FILES['dosya']['name'] ?? ''), PATHINFO_EXTENSION));
    if ($uz !== 'docx') cikti(['ok' => false, 'hata' => 'Yalnızca .docx desteklenir. Word\'de "Farklı Kaydet" ile .docx seçip tekrar yükleyin.'], 400);
    $gecici = (string)($_FILES['dosya']['tmp_name'] ?? '');
    if ($gecici === '' || !is_readable($gecici)) cikti(['ok' => false, 'hata' => 'Geçici dosya okunamadı.'], 400);
    $r = docx_coz($gecici);
    if (empty($r['ok'])) cikti(['ok' => false, 'hata' => (string)($r['hata'] ?? 'Dönüştürülemedi.')], 400);
    cikti($r);
}

/* ================= DİJİTAL RUH ================= */







/* ---- RUH PANELİ (yetki) ---- */






/* =====================================================================
   ZENODO · KALICI KİMLİK (DOI)
   ---------------------------------------------------------------------
   KURUL SORUSU — 20 Ağustos 2026: "Zenodo'dan her eklenen yazı için DOI
   alınabilir mi?" Alınabilir. Gerekçe ve sınırlar ayar.php'de, üstveri
   eşlemesi ortak.php'de (tg_zenodo_ustveri) yazılı. Burada YALNIZCA ağ
   işi var.

   ÜÇ UÇ, ÜÇ AYRI AĞIRLIK:

     /yonetim/zenodo-onizleme   AĞA ÇIKMAZ. Gönderilecek üstverinin ve
                                dosyaların tamamını gösterir. Bir işi
                                yapmadan önce ne yapılacağını görmek,
                                bu sistemin kuralıdır.
     /yonetim/zenodo-taslak     GERİ ALINABİLİR. Zenodo'da taslak kayıt
                                açar, DOI'yi REZERVE eder, dosyaları
                                yükler. Taslak silinebilir; DOI, kayıt
                                yayımlanmadıkça çözülmez.
     /yonetim/zenodo-yayimla    GERİ ALINAMAZ. Zenodo'nun kendi kuralı:
                                "yayımlanmış bir kayıt silinemez." Bu
                                yüzden uç, açık onay ('onay' => 1)
                                olmadan çalışmaz ve hiçbir yerden
                                kendiliğinden çağrılmaz. Kimin
                                yayımladığı kayda yazılır.

   OTOMATİK OLAN TEK ŞEY TASLAKTIR. "Her eklenen yazı için DOI" isteği
   böyle karşılanır: hazırlığı sistem yapar, kalıcı olan adımı bir insan
   atar. Geri alınamaz bir işi kendiliğinden yapan bir sistem, hatasını
   da kendiliğinden kalıcı yapar.
   ===================================================================== */
function zenodo_ayar_oku(): array {
    $a = yonetim_ayar();
    $z = is_array($a['zenodo'] ?? null) ? $a['zenodo'] : [];
    $t = (array)tg_ayar('zenodo', []);
    return [
        'jeton'    => trim((string)($z['jeton'] ?? '')),
        'acik'     => array_key_exists('acik', $z) ? !empty($z['acik']) : !empty($t['acik']),
        'sandbox'  => array_key_exists('sandbox', $z) ? !empty($z['sandbox']) : (!array_key_exists('sandbox', $t) || !empty($t['sandbox'])),
        'topluluk' => trim((string)($z['topluluk'] ?? ($t['topluluk'] ?? ''))),
    ];
}
function zenodo_taban(array $z): string {
    return !empty($z['sandbox']) ? 'https://sandbox.zenodo.org/api' : 'https://zenodo.org/api';
}
/* Tek istek noktası: her çağrı buradan geçer ki zaman aşımı, hata
   biçimi ve jetonun nereye konduğu tek yerde yazılı olsun. Jeton
   BAŞLIKTA gider, adres satırında değil: adres sunucu kütüklerine ve
   vekil kayıtlarına yazılır, başlık yazılmaz. */
function zenodo_istek(array $z, string $metod, string $yol, $govde = null, array $ek = []): array {
    if (!function_exists('curl_init')) return ['ok' => false, 'kod' => 0, 'hata' => 'Sunucuda curl yok.'];
    $url = (strpos($yol, 'http') === 0) ? $yol : (zenodo_taban($z) . $yol);
    $bas = ['Authorization: Bearer ' . $z['jeton'], 'Accept: application/json'];
    $ch = curl_init($url);
    $se = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 10,
           CURLOPT_CUSTOMREQUEST => $metod];
    if (is_array($govde)) { $se[CURLOPT_POSTFIELDS] = json_encode($govde, JSON_UNESCAPED_UNICODE); $bas[] = 'Content-Type: application/json'; }
    elseif (is_string($govde)) { $se[CURLOPT_POSTFIELDS] = $govde; $bas[] = 'Content-Type: ' . ($ek['tur'] ?? 'application/octet-stream'); }
    $se[CURLOPT_HTTPHEADER] = $bas;
    curl_setopt_array($ch, $se);
    $c = curl_exec($ch);
    $kod = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ha = curl_error($ch);
    curl_close($ch);
    if ($c === false) return ['ok' => false, 'kod' => $kod, 'hata' => 'Zenodo yanıt vermedi' . ($ha !== '' ? ': ' . $ha : '.')];
    $d = json_decode((string)$c, true);
    $iyi = $kod >= 200 && $kod < 300;
    return ['ok' => $iyi, 'kod' => $kod, 'veri' => is_array($d) ? $d : [],
            'hata' => $iyi ? '' : zenodo_hata_metni($kod, is_array($d) ? $d : [])];
}
/* Zenodo'nun hata gövdesi alan alan gelir; okura tek cümle gerekir. */
function zenodo_hata_metni(int $kod, array $d): string {
    $m = trim((string)($d['message'] ?? ''));
    $par = [];
    foreach ((array)($d['errors'] ?? []) as $e) {
        if (!is_array($e)) continue;
        $par[] = trim((string)($e['field'] ?? '')) . ': ' . trim((string)($e['message'] ?? ''));
    }
    if ($kod === 401 || $kod === 403) return 'Zenodo jetonu geçersiz ya da yetkisi yok (HTTP ' . $kod . ').';
    if ($kod === 429) return 'Zenodo istek sınırına takıldı; biraz sonra yeniden deneyin.';
    return 'Zenodo hatası (HTTP ' . $kod . ')' . ($m !== '' ? ': ' . $m : '') . ($par ? ' — ' . implode(' · ', array_slice($par, 0, 3)) : '');
}
/* Kayıttaki çalışmayı slug ya da kimlikle bul. */
function zenodo_yazi_bul(array $y, string $anah): int {
    foreach ($y as $i => $e) {
        if (!is_array($e)) continue;
        if ((string)($e['slug'] ?? '') === $anah || (string)($e['id'] ?? '') === $anah) return (int)$i;
    }
    return -1;
}

if ($yol === '/yonetim/zenodo-durum' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $z = zenodo_ayar_oku();
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $liste = []; $doili = 0; $taslakli = 0;
    foreach ($y as $e) {
        if (!is_array($e) || !empty($e['deneme'])) continue;
        $zk = tg_zenodo_kayit($e);
        $doi = trim((string)($e['doi'] ?? ''));
        if ($doi !== '') $doili++;
        if ($zk['taslak'] !== '' && $doi === '') $taslakli++;
        $liste[] = ['anahtar' => (string)($e['slug'] ?? ($e['id'] ?? '')),
                    'baslik' => (string)($e['baslik'] ?? ''),
                    'tarih' => substr((string)($e['tarih'] ?? ''), 0, 10),
                    'doi' => $doi, 'taslak' => $zk['taslak'], 'kayit' => $zk['kayit'],
                    'sandbox' => $zk['sandbox'], 'kim' => $zk['kim'], 'zaman' => $zk['tarih']];
    }
    usort($liste, fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
    /* JETON DÖNMEZ. Yalnız kurulu olup olmadığı ve son dört hanesi. */
    cikti(['ok' => true,
           'jeton'   => $z['jeton'] === '' ? '' : ('****' . substr($z['jeton'], -4)),
           'kurulu'  => $z['jeton'] !== '',
           'acik'    => !empty($z['acik']) && $z['jeton'] !== '',
           'sandbox' => !empty($z['sandbox']),
           'topluluk'=> $z['topluluk'],
           'taban'   => zenodo_taban($z),
           'onek'    => !empty($z['sandbox']) ? '10.5072' : '10.5281',
           'sayi'    => ['toplam' => count($liste), 'doili' => $doili, 'taslakli' => $taslakli],
           'liste'   => array_slice($liste, 0, 300)]);
}

if ($yol === '/yonetim/zenodo-ayar' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); if (!$g) $g = $_POST;
    $a = yonetim_ayar();
    $z = is_array($a['zenodo'] ?? null) ? $a['zenodo'] : [];
    /* Maskeli değer geri gönderildiyse jetona DOKUNULMAZ: ekranı açıp
       kaydete basmak bir sırrı silmemelidir. Telegram ve SMTP'de aynı
       kural var; üçü de aynı kusurdan korunuyor. */
    $yeni = trim((string)($g['jeton'] ?? ''));
    if ($yeni !== '' && strpos($yeni, '****') !== 0) $z['jeton'] = $yeni;
    if (array_key_exists('sandbox', $g))  $z['sandbox'] = !empty($g['sandbox']);
    if (array_key_exists('acik', $g))     $z['acik'] = !empty($g['acik']);
    if (array_key_exists('topluluk', $g)) $z['topluluk'] = trim((string)$g['topluluk']);
    $a['zenodo'] = $z;
    yaz_json('yonetim-ayar.json', $a);
    cikti(['ok' => true]);
}

/* Jeton gerçekten çalışıyor mu: Zenodo'ya YAZMAYAN tek istek. */
if ($yol === '/yonetim/zenodo-sina' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $z = zenodo_ayar_oku();
    if ($z['jeton'] === '') cikti(['ok' => false, 'hata' => 'Önce jetonu yazın.'], 400);
    $r = zenodo_istek($z, 'GET', '/deposit/depositions?size=1');
    cikti(['ok' => $r['ok'], 'kod' => $r['kod'], 'hata' => $r['hata'],
           'evren' => !empty($z['sandbox']) ? 'deneme (sandbox)' : 'gerçek']);
}

/* ÖNİZLEME: ağa çıkmaz. Ne gönderileceği, gönderilmeden görülür. */
if ($yol === '/yonetim/zenodo-onizleme' && $metod === 'GET') {
    yonetim_yazma_gerek();
    $anah = trim((string)($_GET['y'] ?? ''));
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = zenodo_yazi_bul($y, $anah);
    if ($i < 0) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    $dos = [];
    foreach (tg_zenodo_dosyalar($y[$i]) as $ad => $ic) $dos[] = ['ad' => $ad, 'boyut' => strlen($ic)];
    cikti(['ok' => true, 'ustveri' => tg_zenodo_ustveri($y[$i])['metadata'], 'dosyalar' => $dos,
           'kayit' => tg_zenodo_kayit($y[$i])]);
}

/* TASLAK: geri alınabilir. Kayıt açılır, DOI rezerve edilir, dosyalar
   yüklenir. Yayımlanmadıkça DOI çözülmez ve taslak silinebilir. */
if ($yol === '/yonetim/zenodo-taslak' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $z = zenodo_ayar_oku();
    if ($z['jeton'] === '') cikti(['ok' => false, 'hata' => 'Zenodo jetonu yazılmamış.'], 400);
    if (empty($z['acik'])) cikti(['ok' => false, 'hata' => 'Zenodo düzeni kapalı. Panelden açın.'], 409);
    $g = govde_json(); if (!$g) $g = $_POST;
    $anah = trim((string)($g['y'] ?? ''));
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = zenodo_yazi_bul($y, $anah);
    if ($i < 0) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    if (!empty($y[$i]['deneme'])) cikti(['ok' => false, 'hata' => 'Deneme kaydına kimlik verilmez.'], 409);
    if (trim((string)($y[$i]['doi'] ?? '')) !== '') cikti(['ok' => false, 'hata' => 'Bu çalışmanın DOI\'si zaten var.'], 409);
    $zk = tg_zenodo_kayit($y[$i]);
    if ($zk['taslak'] !== '') cikti(['ok' => false, 'hata' => 'Taslak zaten var (' . $zk['taslak'] . ').'], 409);

    $r = zenodo_istek($z, 'POST', '/deposit/depositions', ['metadata' => new stdClass()]);
    if (!$r['ok']) cikti(['ok' => false, 'hata' => $r['hata']], 502);
    $kid = (string)($r['veri']['id'] ?? '');
    $bucket = (string)($r['veri']['links']['bucket'] ?? '');
    if ($kid === '') cikti(['ok' => false, 'hata' => 'Zenodo taslak kimliği dönmedi.'], 502);

    /* DOSYALAR ÖNCE. Zenodo en az bir dosya ister; üstveri doğru ama
       dosyasız bir taslak yayımlanamaz ve kişi bunu ancak yayımlamaya
       basınca öğrenirdi. */
    foreach (tg_zenodo_dosyalar($y[$i]) as $ad => $ic) {
        $tur = substr($ad, -5) === '.json' ? 'application/json' : 'text/html; charset=UTF-8';
        $ry = $bucket !== ''
            ? zenodo_istek($z, 'PUT', $bucket . '/' . rawurlencode($ad), $ic, ['tur' => $tur])
            : ['ok' => false, 'hata' => 'Zenodo dosya kovası dönmedi.'];
        if (!$ry['ok']) cikti(['ok' => false, 'hata' => 'Dosya yüklenemedi (' . $ad . '): ' . $ry['hata'], 'taslak' => $kid], 502);
    }

    /* ÜSTVERİ ve DOI REZERVİ. prereserve_doi, kayıt yayımlanmadan
       kimliğin ne olacağını söyler; kimliği metnin içine koymak
       gerektiğinde tek yol budur. */
    $u = tg_zenodo_ustveri($y[$i]);
    $u['metadata']['prereserve_doi'] = true;
    $r2 = zenodo_istek($z, 'PUT', '/deposit/depositions/' . rawurlencode($kid), $u);
    if (!$r2['ok']) cikti(['ok' => false, 'hata' => $r2['hata'], 'taslak' => $kid], 502);

    $rez = (string)(($r2['veri']['metadata']['prereserve_doi']['doi'] ?? '') ?: '');
    $y[$i]['zenodo'] = ['taslak' => $kid, 'kayit' => '', 'doi' => '', 'rezerv' => $rez,
                        'sandbox' => !empty($z['sandbox']),
                        'tarih' => date('c'), 'kim' => hs_gorunen_ad(hs_gerek())];
    yaz_json('yazilar.json', array_values($y));
    cikti(['ok' => true, 'taslak' => $kid, 'rezerv' => $rez,
           'adres' => (string)($r2['veri']['links']['html'] ?? '')]);
}

/* Taslağı sil: yayımlanmamış kayıt silinebilir, kaydın izi kalkar. */
if ($yol === '/yonetim/zenodo-taslak-sil' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $z = zenodo_ayar_oku();
    if ($z['jeton'] === '') cikti(['ok' => false, 'hata' => 'Zenodo jetonu yazılmamış.'], 400);
    $g = govde_json(); if (!$g) $g = $_POST;
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = zenodo_yazi_bul($y, trim((string)($g['y'] ?? '')));
    if ($i < 0) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    $zk = tg_zenodo_kayit($y[$i]);
    if ($zk['taslak'] === '') cikti(['ok' => false, 'hata' => 'Taslak yok.'], 409);
    if (trim((string)($y[$i]['doi'] ?? '')) !== '')
        cikti(['ok' => false, 'hata' => 'Yayımlanmış kayıt silinemez; Zenodo\'nun kuralı budur.'], 409);
    $r = zenodo_istek($z, 'DELETE', '/deposit/depositions/' . rawurlencode($zk['taslak']));
    if (!$r['ok'] && $r['kod'] !== 404) cikti(['ok' => false, 'hata' => $r['hata']], 502);
    unset($y[$i]['zenodo']);
    yaz_json('yazilar.json', array_values($y));
    cikti(['ok' => true]);
}

/* YAYIMLA · GERİ ALINAMAZ.
   Zenodo: "yayımlanmış bir kayıt silinemez." Bu yüzden açık onay
   olmadan çalışmaz, hiçbir yerden kendiliğinden çağrılmaz ve kimin
   yayımladığı kayda yazılır. */
if ($yol === '/yonetim/zenodo-yayimla' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $z = zenodo_ayar_oku();
    if ($z['jeton'] === '') cikti(['ok' => false, 'hata' => 'Zenodo jetonu yazılmamış.'], 400);
    if (empty($z['acik'])) cikti(['ok' => false, 'hata' => 'Zenodo düzeni kapalı.'], 409);
    $g = govde_json(); if (!$g) $g = $_POST;
    if ((string)($g['onay'] ?? '') !== '1')
        cikti(['ok' => false, 'hata' => 'Bu iş geri alınamaz: Zenodo kaydı yayımlandıktan sonra silinemez ve DOI kalıcıdır. Açık onay gerekir.'], 400);
    $y = oku_json('yazilar.json', []); if (!is_array($y)) $y = [];
    $i = zenodo_yazi_bul($y, trim((string)($g['y'] ?? '')));
    if ($i < 0) cikti(['ok' => false, 'hata' => 'Çalışma bulunamadı.'], 404);
    if (!empty($y[$i]['deneme'])) cikti(['ok' => false, 'hata' => 'Deneme kaydına kimlik verilmez.'], 409);
    if (trim((string)($y[$i]['doi'] ?? '')) !== '') cikti(['ok' => false, 'hata' => 'Bu çalışmanın DOI\'si zaten var.'], 409);
    $zk = tg_zenodo_kayit($y[$i]);
    if ($zk['taslak'] === '') cikti(['ok' => false, 'hata' => 'Önce taslak hazırlayın.'], 409);

    $r = zenodo_istek($z, 'POST', '/deposit/depositions/' . rawurlencode($zk['taslak']) . '/actions/publish');
    if (!$r['ok']) cikti(['ok' => false, 'hata' => $r['hata']], 502);
    $doi = trim((string)($r['veri']['doi'] ?? ''));
    $kayit = (string)($r['veri']['id'] ?? $zk['taslak']);
    $kavram = trim((string)($r['veri']['conceptdoi'] ?? ''));
    /* DENEME EVRENİNİN KİMLİĞİ GERÇEK DEĞİLDİR ve çalışmanın 'doi'
       alanına yazılmaz: o alan sayfada ve dışarıya verilen üstveride
       kalıcı kimlik olarak görünür. Deneme kimliği oraya yazılsaydı,
       sistem dizinlere çözülmeyen bir kimlik beyan ederdi. */
    $gercek = empty($z['sandbox']);
    $y[$i]['zenodo'] = ['taslak' => '', 'kayit' => $kayit, 'doi' => $doi, 'kavram' => $kavram,
                        'sandbox' => !$gercek, 'tarih' => date('c'), 'kim' => hs_gorunen_ad(hs_gerek())];
    if ($gercek && $doi !== '') $y[$i]['doi'] = $doi;
    yaz_json('yazilar.json', array_values($y));
    cikti(['ok' => true, 'doi' => $doi, 'kavram' => $kavram, 'kayit' => $kayit,
           'gercek' => $gercek, 'adres' => (string)($r['veri']['links']['record_html'] ?? '')]);
}

if ($yol === '/yonetim/ayar' && $metod === 'GET') {
    yonetim_okuma_gerek();
    $a = yonetim_ayar();
    /* Jeton hiçbir zaman olduğu gibi dönmez; yalnızca kurulu olup olmadığı
       ve son dört hanesi görünür. Ekranda "kayıtlı mı" sorusunun yanıtı
       yeterlidir, değerin kendisi değil. */
    $tg = (array)($a['tg'] ?? []);
    $sm = (array)($a['smtp'] ?? []);
    $maske = fn(string $k): string => $k === '' ? '' : ('****' . substr($k, -4));
    cikti(['ok' => true, 'ayar' => [
        'tg'  => ['token' => $maske((string)($tg['token'] ?? '')),
                  'chat'  => (string)($tg['chat'] ?? ''),
                  'kurulu' => ($tg['token'] ?? '') !== '' && ($tg['chat'] ?? '') !== ''],
        'hiz' => (array)($a['hiz'] ?? []),
        /* Parola dönmez; yalnızca yazılı olup olmadığı görünür. Bir sırrın
           ekrana geri gelmesi, onu tarayıcı belleğine ve ekran görüntüsüne
           taşımaktır. */
        'smtp' => ['sunucu' => (string)($sm['sunucu'] ?? ''), 'port' => (int)($sm['port'] ?? 587),
                   'kullanici' => (string)($sm['kullanici'] ?? ''),
                   'parola' => $maske((string)($sm['parola'] ?? '')),
                   'guvenlik' => (string)($sm['guvenlik'] ?? 'tls'),
                   'gonderen' => (string)($sm['gonderen'] ?? ''),
                   'gonderen_ad' => (string)($sm['gonderen_ad'] ?? ''),
                   'yanit' => (string)($sm['yanit'] ?? ''),
                   'kurulu' => trim((string)($sm['sunucu'] ?? '')) !== ''],
    ]]);
}
if ($yol === '/yonetim/ayar-kaydet' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json();
    $a = yonetim_ayar();
    if (isset($g['tg']) && is_array($g['tg'])) {
        $yeni = trim((string)($g['tg']['token'] ?? ''));
        /* Maskeli değer geri gönderildiyse dokunma: ekranı açıp kaydete
           basmak jetonu silmemelidir. */
        if ($yeni !== '' && strpos($yeni, '****') !== 0) $a['tg']['token'] = $yeni;
        if (isset($g['tg']['chat'])) $a['tg']['chat'] = preg_replace('/[^0-9-]/', '', (string)$g['tg']['chat']) ?? '';
    }
    if (isset($g['smtp']) && is_array($g['smtp'])) {
        $s = (array)($a['smtp'] ?? []);
        $s['sunucu']      = trim((string)($g['smtp']['sunucu'] ?? ($s['sunucu'] ?? '')));
        $s['port']        = max(1, (int)($g['smtp']['port'] ?? ($s['port'] ?? 587)));
        $s['kullanici']   = trim((string)($g['smtp']['kullanici'] ?? ($s['kullanici'] ?? '')));
        $s['guvenlik']    = in_array((string)($g['smtp']['guvenlik'] ?? ''), ['tls','ssl','yok'], true)
                            ? (string)$g['smtp']['guvenlik'] : (string)($s['guvenlik'] ?? 'tls');
        $s['gonderen']    = trim((string)($g['smtp']['gonderen'] ?? ($s['gonderen'] ?? '')));
        $s['gonderen_ad'] = trim((string)($g['smtp']['gonderen_ad'] ?? ($s['gonderen_ad'] ?? '')));
        $s['yanit']       = trim((string)($g['smtp']['yanit'] ?? ($s['yanit'] ?? '')));
        /* Maskeli parola geri gönderildiyse dokunma: ekranı açıp kaydete
           basmak, kayıtlı parolayı silmemelidir. Telegram jetonunda aynı
           kural vardı; ikisi de aynı kusurdan korunuyor. */
        $yp = (string)($g['smtp']['parola'] ?? '');
        if ($yp !== '' && strpos($yp, '****') !== 0) $s['parola'] = $yp;
        $a['smtp'] = $s;
    }
    if (isset($g['hiz']) && is_array($g['hiz']))
        $a['hiz'] = ['pencere' => max(30, (int)($g['hiz']['pencere'] ?? 300)),
                     'adet'    => max(3,  (int)($g['hiz']['adet'] ?? 18)),
                     'gunluk'  => max(0,  (int)($g['hiz']['gunluk'] ?? 150))];
    yaz_json('yonetim-ayar.json', $a);
    cikti(['ok' => true]);
}



/* Makale gövdesine görsel yükle (WYSIWYG) */
if ($yol === '/yonetim/yazi-gorsel' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $ec = $_FILES['dosya']['error'] ?? 4;
    if (empty($_FILES['dosya']) || $ec !== 0) cikti(['ok' => false, 'hata' => 'Görsel alınamadı (çok büyük olabilir).'], 400);
    if ((int)($_FILES['dosya']['size'] ?? 0) > 8 * 1024 * 1024) cikti(['ok' => false, 'hata' => 'En fazla 8 MB.'], 400);
    $tmp = $_FILES['dosya']['tmp_name'];
    $bilgi = @getimagesize($tmp);
    $uzanti = $bilgi ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$bilgi[2]] ?? '') : '';
    if ($uzanti === '') cikti(['ok' => false, 'hata' => 'Sadece JPG, PNG, WebP veya GIF.'], 400);
    $klasor = __DIR__ . '/../photo/yazi';
    if (!is_dir($klasor)) @mkdir($klasor, 0755, true);
    $ad = substr(hash('sha256', microtime() . mt_rand()), 0, 14);
    $yeni = $klasor . '/' . $ad . '.' . $uzanti;
    if (!@move_uploaded_file($tmp, $yeni)) { if (!@copy($tmp, $yeni)) cikti(['ok' => false, 'hata' => 'Kaydedilemedi.'], 500); }
    @chmod($yeni, 0644);
    cikti(['ok' => true, 'yol' => '/photo/yazi/' . $ad . '.' . $uzanti]);
}

/* =====================================================================
   YAZARIN GÖRSELİ: ŞEKİL, ÇİZELGE, GRAFİK
   ---------------------------------------------------------------------
   Kurul kararı, 15 Ağustos 2026: "yazdığı metni şekillendirebilmesi
   lazım; şekillerin ekleneceği yer yok."

   ÖLÇÜLEN KUSUR — bu uç açılmadan önce şekil eklemek MÜMKÜN DEĞİLDİ ve
   bunu hiçbir yer söylemiyordu:
     - Düzenleyicinin görsel düğmesi ve Word yapıştırması, görseli
       data: adresiyle metnin içine gömüyordu.
     - guvenli_html() ise img/src'de yalnız http(s), / ve # kabul eder;
       data: adresi ELENİYORDU (ortak.php'deki izin listesi).
     - Sonuç: yazar görseli ekliyor, kaydediyor, görsel sessizce
       kayboluyordu. Var olan tek yükleme ucu yönetim yetkisi
       istiyordu, yani yazara kapalıydı.

   Bu uç o boşluğu kapatır: görsel sunucuda dosya olarak durur ve
   metne /photo/... adresiyle girer — guvenli_html'in kabul ettiği
   biçim. Böylece iki katman (düzenleyici ve süzgeç) birbiriyle
   çelişmez.

   YETKİ: giriş yapmış her hesap. Yazarlık yeterlidir; çalışma henüz
   kayıt bile olmamış olabilir (gönderim formunda yazılıyor).
   HIZ SINIRI: yükleme pahalı bir iştir ve kimliği olan biri de aşırıya
   kaçabilir. TÜR: yalnız gerçek resim; uzantıya değil, getimagesize'ın
   okuduğu içeriğe bakılır.
   ===================================================================== */
if ($yol === '/yazar-gorsel' && $metod === 'POST') {
    hs_gerek();
    $ipk = 'yzgorsel:' . substr(hash('sha256', ip_al()), 0, 16);
    if (kotu_say($ipk) > 300) cikti(['ok' => false, 'hata' => tg_c(
        'Çok fazla görsel yüklendi. Lütfen biraz sonra tekrar deneyin.',
        'Too many images uploaded. Please try again a little later.')], 429);
    /* Alan adı iki biçimde gelebilir: sayfanın kendi yüklemesi 'dosya',
       düzenleyicinin toplu yüklemesi 'files[]'. İkisini de kabul etmek,
       istemcinin biçimini uca dayatmamaktır. */
    $dosya = null;
    if (!empty($_FILES['dosya']) && (int)($_FILES['dosya']['error'] ?? 4) === 0) {
        $dosya = ['tmp' => $_FILES['dosya']['tmp_name'], 'boyut' => (int)$_FILES['dosya']['size']];
    } elseif (!empty($_FILES['files']) && is_array($_FILES['files']['error'] ?? null)
              && (int)$_FILES['files']['error'][0] === 0) {
        $dosya = ['tmp' => $_FILES['files']['tmp_name'][0], 'boyut' => (int)$_FILES['files']['size'][0]];
    }
    if ($dosya === null) cikti(['ok' => false, 'hata' => tg_c(
        'Görsel alınamadı (çok büyük olabilir).', 'The image could not be received (it may be too large).')], 400);
    if ($dosya['boyut'] > 8 * 1024 * 1024) cikti(['ok' => false, 'hata' => tg_c(
        'En fazla 8 MB.', 'At most 8 MB.')], 400);
    $bilgi = @getimagesize($dosya['tmp']);
    $uzanti = $bilgi ? ([IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png',
                         IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$bilgi[2]] ?? '') : '';
    if ($uzanti === '') cikti(['ok' => false, 'hata' => tg_c(
        'Sadece JPG, PNG, WebP veya GIF.', 'Only JPG, PNG, WebP or GIF.')], 400);
    $klasor = __DIR__ . '/../photo/yazi';
    if (!is_dir($klasor)) @mkdir($klasor, 0755, true);
    $ad = substr(hash('sha256', microtime() . mt_rand() . random_int(0, PHP_INT_MAX)), 0, 14);
    $yeni = $klasor . '/' . $ad . '.' . $uzanti;
    if (!@move_uploaded_file($dosya['tmp'], $yeni)) {
        if (!@copy($dosya['tmp'], $yeni)) cikti(['ok' => false, 'hata' => tg_c(
            'Kaydedilemedi.', 'It could not be saved.')], 500);
    }
    @chmod($yeni, 0644);
    cikti(['ok' => true, 'yol' => '/photo/yazi/' . $ad . '.' . $uzanti]);
}

/* ---- Klasik sayfa (cv.html) görselleri ---- */
function site_slotlar(): array {
    return [
        'hero1' => 'Anasayfa arka plan 1', 'hero2' => 'Anasayfa arka plan 2', 'hero3' => 'Anasayfa arka plan 3',
        'about-side' => 'Hakkımda yan görsel', 'skills-side' => 'Yetenekler yan görsel',
        'book' => 'Kitap kapağı', 'work-derspro' => 'DersPro görseli', 'work-probina' => 'Probina görseli',
        'work-kasis' => 'Kasis görseli', 'work-pubs' => 'Yayınlar görseli', 'work-consult' => 'Danışmanlık görseli',
    ];
}
if ($yol === '/yonetim/site-gorseller' && $metod === 'GET') {
    yonetim_okuma_gerek();
    $klasor = __DIR__ . '/../photo/site';
    $liste = [];
    foreach (site_slotlar() as $slot => $ad) {
        $var = is_file($klasor . '/' . $slot . '.jpg');
        $liste[] = ['slot' => $slot, 'ad' => $ad, 'var' => $var,
            'yol' => $var ? ('photo/site/' . $slot . '.jpg?v=' . @filemtime($klasor . '/' . $slot . '.jpg')) : ''];
    }
    cikti(['ok' => true, 'gorseller' => $liste]);
}
if ($yol === '/yonetim/site-gorsel' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $slot = (string)($_POST['slot'] ?? '');
    if (!isset(site_slotlar()[$slot])) cikti(['ok' => false, 'hata' => 'Geçersiz görsel alanı.'], 400);
    if (empty($_FILES['dosya']) || ($_FILES['dosya']['error'] ?? 1) !== 0) cikti(['ok' => false, 'hata' => 'Dosya alınamadı.'], 400);
    if ((int)($_FILES['dosya']['size'] ?? 0) > 8 * 1024 * 1024) cikti(['ok' => false, 'hata' => 'En fazla 8 MB.'], 400);
    $tmp = $_FILES['dosya']['tmp_name'];
    $bilgi = @getimagesize($tmp);
    if (!$bilgi) cikti(['ok' => false, 'hata' => 'Geçerli bir görsel değil.'], 400);
    $klasor = __DIR__ . '/../photo/site';
    if (!is_dir($klasor)) @mkdir($klasor, 0755, true);
    $hedef = $klasor . '/' . $slot . '.jpg';
    /* GD ile JPEG'e çevir (isim sabit kalsın, cv.html değişmesin); yoksa doğrudan kopyala */
    $ok = false;
    if (function_exists('imagecreatefromstring')) {
        $ham = @file_get_contents($tmp);
        $im = $ham ? @imagecreatefromstring($ham) : false;
        if ($im) {
            $g = imagesx($im); $y = imagesy($im); $maxG = 1600;
            if ($g > $maxG) {
                $ny = (int)round($y * $maxG / $g);
                $im2 = imagecreatetruecolor($maxG, $ny);
                imagecopyresampled($im2, $im, 0, 0, 0, 0, $maxG, $ny, $g, $y);
                imagedestroy($im); $im = $im2;
            }
            $ok = @imagejpeg($im, $hedef, 85);
            imagedestroy($im);
        }
    }
    if (!$ok) { // GD yoksa: sadece jpg/jpeg kabul et, kopyala
        if (!in_array($bilgi[2], [IMAGETYPE_JPEG], true)) cikti(['ok' => false, 'hata' => 'Sunucuda GD yok; lütfen JPG yükle.'], 400);
        if (!@move_uploaded_file($tmp, $hedef) && !@copy($tmp, $hedef)) cikti(['ok' => false, 'hata' => 'Kaydedilemedi.'], 500);
    }
    @chmod($hedef, 0644);
    cikti(['ok' => true, 'yol' => 'photo/site/' . $slot . '.jpg?v=' . time()]);
}

/* ---- Telegram bildirimi (yalnızca yönetici) ---- */
/* =====================================================================
   E-POSTA GÖNDERİMİ · TEK KAPI
   ---------------------------------------------------------------------
   İki yol vardır ve sırası değişmez:

     1. AKTARICI (SMTP). Panelde bir sunucu yazılıysa ileti oraya teslim
        edilir. SPF ve DKIM aktarıcıya aittir; bizde bakımı olan hiçbir
        şey yoktur.
     2. mail(). Yalnızca aktarıcı yazılmamışsa denenir ve bu sunucuda
        ÇALIŞMADIĞI ölçüldü (MX/SPF/DKIM/PTR yok). Silinmedi: kurulacak
        her sunucu böyle olmak zorunda değildir ve bir yedek yolun
        varlığı, ayar kaybolduğunda sistemin büsbütün susmasını önler.

   ÖNCEKİ KUSUR: bu işlev "BASARISIZ" yazıyordu ama NEDEN başarısız
   olduğunu yazmıyordu; üstelik mail() gövdeyi yerel posta yazılımına
   teslim edip true döndürdüğü için çoğu zaman "GONDERILDI" yazıp
   hiçbir şey göndermiyordu. Artık her satırda yol ve gerekçe var.
   ===================================================================== */
function eposta_gonder(string $alici, string $konu, string $govde): bool {
    $alici = trim($alici);
    if ($alici === '' || !filter_var($alici, FILTER_VALIDATE_EMAIL)) {
        if (function_exists('pst_son_hata')) pst_son_hata('adres gecersiz');
        return false;
    }
    $marka = function_exists('tg_ayar') ? (string)tg_ayar('marka', 'Kutadgu') : 'Kutadgu';
    $kok   = function_exists('tg_kok') ? tg_kok() : '';
    $alan  = (string)preg_replace('#^https?://#', '', $kok);
    if ($alan === '') $alan = 'kutadgu.net';
    $gonderen = 'bildirim@' . $alan;

    /* Başlık satırlarına yeni satır sızmasını engelle */
    $konu  = trim(preg_replace('/[\r\n]+/', ' ', $konu));
    $alici = preg_replace('/[\r\n]+/', '', $alici);

    $ok = false; $yol2 = 'mail'; $neden = '';

    if (function_exists('pst_kurulu') && pst_kurulu()) {
        $s = pst_smtp_gonder($alici, $konu, $govde);
        $ok = !empty($s['ok']); $yol2 = 'smtp'; $neden = (string)($s['hata'] ?? '');
    } elseif (function_exists('mail')) {
        $bas  = 'From: ' . mb_encode_mimeheader($marka, 'UTF-8') . ' <' . $gonderen . ">\r\n";
        $bas .= 'Reply-To: ' . $gonderen . "\r\n";
        $bas .= "MIME-Version: 1.0\r\n";
        $bas .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $bas .= "Content-Transfer-Encoding: 8bit\r\n";
        $bas .= 'X-Mailer: ' . $marka . "\r\n";
        $ok = @mail($alici, mb_encode_mimeheader($konu, 'UTF-8'), $govde, $bas);
        /* mail()'in true'su bir teslim güvencesi DEĞİLDİR ve kayıtta
           öyleymiş gibi durmamalıdır. */
        $neden = $ok ? 'yerel posta yazilimina verildi (teslim guvencesi yok)' : 'yerel posta yazilimi kabul etmedi';
    } else {
        $neden = 'ne aktarici ayarli ne de mail() var';
    }

    if (function_exists('pst_son_hata')) pst_son_hata($ok ? '' : $neden);

    /* Kayıt: gönderilsin ya da gönderilmesin izlenebilir kalsın */
    $kayit = date('c') . "\t" . ($ok ? 'GONDERILDI' : 'BASARISIZ') . "\t" . $alici . "\t" . $konu
           . "\t" . $yol2 . "\t" . $neden . "\n";
    $yolL = veri_yolu('eposta.log');
    if (@filesize($yolL) > 5 * 1024 * 1024) @unlink($yolL);
    @file_put_contents($yolL, $kayit, FILE_APPEND | LOCK_EX);
    return $ok;
}

/* =====================================================================
   TELEGRAM BİLDİRİMİ VE KÜTÜĞÜ
   ---------------------------------------------------------------------
   BİLDİRİLEN KUSUR: iletişim formundan bir ileti gönderildi, Telegram'a
   düşmedi ve NEDEN düşmediğini söyleyen hiçbir kayıt yoktu.

   Sebebi bu işlevin kendisiydi. On beş çağıran var ve HİÇBİRİ dönen
   değere bakmıyor: gönderilemediğinde dizi sessizce atılıyor. Token
   yanlışsa, sohbet kimliği yanlışsa, kullanıcı bota hiç yazmadıysa
   (Telegram, bota ilk yazan taraf olmadan bildirim kabul etmez),
   sunucudan dışarı çıkış engelliyse ya da ayar hiç yazılmamışsa
   sonuç aynı: hiçbir şey olmuyor ve hiçbir yerde yazmıyor.

   E-posta tarafında bu çözülmüştü: eposta_gonder her denemeyi
   eposta.log'a GONDERILDI/BASARISIZ diye yazıyor. Telegram'ın
   karşılığı yoktu. Artık var ve TEK NOKTADAN yazılıyor: on beş çağıranın
   hiçbiri değişmedi, hepsi kütüğe düşüyor.

   TOKEN KÜTÜĞE YAZILMAZ. Bir sır, arıza ararken bakılan bir dosyaya
   giremez; yalnız ilk altı hanesi yazılır, hangi botun kullanıldığı
   anlaşılsın diye yeter.

   AYAR HİÇ YOKKEN DE YAZILIR. "Ayar boş" bir arıza değildir ama
   SESSİZ kalması arızadır: bildirim beklerken hiç denenmediğini
   bilmek, denenip başarısız olduğunu bilmek kadar önemlidir.
   ===================================================================== */
function tg_kutuk(string $etiket, string $token, string $chat, bool $ok, string $not): void {
    $on = $token !== '' ? substr($token, 0, 6) : '-';
    $satir = date('c') . "\t" . ($ok ? 'GONDERILDI' : 'BASARISIZ') . "\t" . $etiket
           . "\tbot=" . $on . "\tchat=" . ($chat !== '' ? $chat : '-') . "\t" . $not . "\n";
    $yol = veri_yolu('telegram.log');
    if (@filesize($yol) > 2 * 1024 * 1024) @unlink($yol);
    @file_put_contents($yol, $satir, FILE_APPEND | LOCK_EX);
}

function tg_gonder(string $token, string $chat, string $metin, int $zaman = 12, string $etiket = 'genel'): array {
    if ($token === '' || $chat === '') {
        tg_kutuk($etiket, $token, $chat, false, 'ayar eksik: token ya da sohbet kimligi bos');
        return ['ok' => false, 'hata' => 'Token veya sohbet ID boş.'];
    }
    $url = 'https://api.telegram.org/bot' . $token . '/sendMessage';
    $veri = ['chat_id' => $chat, 'text' => $metin, 'disable_web_page_preview' => true];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($veri), CURLOPT_TIMEOUT => $zaman, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_NOSIGNAL => 1]);
    $c = curl_exec($ch); $hata = curl_error($ch); $kod = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
    $j = json_decode((string)$c, true);
    if (is_array($j) && !empty($j['ok'])) {
        tg_kutuk($etiket, $token, $chat, true, 'msgid=' . (int)($j['result']['message_id'] ?? 0));
        return ['ok' => true, 'msgid' => (int)($j['result']['message_id'] ?? 0)];
    }
    /* Telegram'ın KENDİ açıklaması yazılır. "Gönderilemedi" demek,
       hiçbir şey dememektir; "chat not found" ise doğrudan çözümü
       gösterir. */
    $ac = $hata !== '' ? ('curl: ' . $hata)
        : ((is_array($j) && !empty($j['description'])) ? ('telegram: ' . $j['description'])
        : ('yanit yok, HTTP ' . $kod));
    tg_kutuk($etiket, $token, $chat, false, $ac);
    return ['ok' => false, 'hata' => $ac];
}



/* Cevabın sonundaki kalıp "başka bir şey sormak ister misin?" türü soruyu kırp */
/* Uzun tire, orta tire, yatay çubuk ve benzerleri düz tireye (-) çevrilir.
   Sitenin hiçbir yerinde uzun çizgi kullanılmaz; bu kural yazar metinleri,
   hakem raporları ve yapay zekâ çıktıları için de geçerlidir. */
function tire_temizle($v) {
    /* U+2010..U+2015, U+2212 (eksi), U+2500 (kutu çizgisi), U+FE58, U+FE63, U+FF0D */
    static $liste = ["\xE2\x80\x90", "\xE2\x80\x91", "\xE2\x80\x92", "\xE2\x80\x93", "\xE2\x80\x94",
                     "\xE2\x80\x95", "\xE2\x88\x92", "\xE2\x94\x80", "\xEF\xB9\x98", "\xEF\xB9\xA3", "\xEF\xBC\x8D"];
    if (is_string($v)) return str_replace($liste, '-', $v);
    if (is_array($v)) { foreach ($v as $k => $x) $v[$k] = tire_temizle($x); return $v; }
    return $v;
}





/* Telegram ayarını getir (token maskeli) */
if ($yol === '/yonetim/tg-al' && $metod === 'GET') {
    yonetim_okuma_gerek();
    $a = yonetim_ayar(); $tg = (array)($a['tg'] ?? []);
    $tok = (string)($tg['token'] ?? '');
    cikti(['ok' => true, 'token_var' => $tok !== '', 'token_ipucu' => $tok !== '' ? ('...' . substr($tok, -6)) : '',
        'chat' => (string)($tg['chat'] ?? ''), 'anahtar' => (string)($tg['anahtar'] ?? ''),
        'son' => (int)($tg['son'] ?? 0), 'aktar' => !empty($tg['aktar']),
        'kok' => (isset($_SERVER['HTTP_HOST']) ? ((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']) : 'https://kutadgu.net')]);
}

/* Telegram ayarını kaydet + cron anahtarı üret */
if ($yol === '/yonetim/tg-kaydet' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $g = govde_json(); $a = yonetim_ayar();
    $tg = (array)($a['tg'] ?? ['token' => '', 'chat' => '', 'anahtar' => '', 'son' => 0]);
    $ytok = trim((string)($g['token'] ?? ''));
    if ($ytok !== '' && strpos($ytok, '...') !== 0) $tg['token'] = $ytok; // maskeli değeri geri yazma
    if (isset($g['chat'])) $tg['chat'] = trim((string)$g['chat']);
    if (isset($g['aktar'])) $tg['aktar'] = !empty($g['aktar']);
    if (empty($tg['anahtar'])) $tg['anahtar'] = substr(hash('sha256', $tg['token'] . microtime() . mt_rand()), 0, 24);
    $a['tg'] = $tg;
    yaz_json('yonetim-ayar.json', $a);
    cikti(['ok' => true, 'anahtar' => $tg['anahtar']]);
}

/* Telegram: sohbet ID'sini otomatik bul (kullanıcı bota mesaj attıktan sonra) */
if ($yol === '/yonetim/tg-id' && $metod === 'GET') {
    yonetim_okuma_gerek();
    $a = yonetim_ayar(); $tok = (string)($a['tg']['token'] ?? '');
    if ($tok === '') cikti(['ok' => false, 'hata' => 'Önce bot token gir ve kaydet.']);
    $c = @file_get_contents('https://api.telegram.org/bot' . $tok . '/getUpdates');
    $j = json_decode((string)$c, true);
    $idler = [];
    if (is_array($j) && !empty($j['result'])) {
        foreach ($j['result'] as $u) {
            $ch = $u['message']['chat'] ?? $u['edited_message']['chat'] ?? null;
            if ($ch && isset($ch['id'])) {
                $ad = trim(($ch['first_name'] ?? '') . ' ' . ($ch['last_name'] ?? '')) ?: ($ch['username'] ?? $ch['title'] ?? '');
                $idler[(string)$ch['id']] = $ad;
            }
        }
    }
    cikti(['ok' => true, 'idler' => $idler]);
}

/* Telegram test bildirimi gönder */
if ($yol === '/yonetim/tg-test' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $a = yonetim_ayar();
    $r = tg_gonder((string)($a['tg']['token'] ?? ''), (string)($a['tg']['chat'] ?? ''),
        "Kutadgu bildirimi calisiyor. Destek basvurusu, yazarlik destegi ve benzeri olaylar bundan sonra buraya dusecek. - kutadgu.net",
        12, 'deneme-genel');
    cikti($r);
}

/* İLETİŞİM BOTU AYRI BİR BOTTUR VE AYRICA DENENEBİLMELİDİR.
   Deneme ucu bugüne kadar yalnız GENEL botu deniyordu; oysa iletişim
   formunun bildirimi 'iletisim' bloğundaki ayrı bottan gidiyor. Genel
   bot çalışırken iletişim botu bozuk olabilir ve deneme bunu hiç
   göstermezdi: yeşil bir deneme, çalışmayan bir bildirim. */
if ($yol === '/yonetim/tg-test-iletisim' && $metod === 'POST') {
    yonetim_yazma_gerek();
    $it = ilt_ayar();
    if ($it['token'] === '' || $it['chat'] === '') {
        $a = yonetim_ayar(); $tg = (array)($a['tg'] ?? []);
        $r = tg_gonder((string)($tg['token'] ?? ''), (string)($tg['chat'] ?? ''),
            "Kutadgu: iletisim bildirimi denemesi. Ayri bir iletisim botu TANIMLI DEGIL; bu ileti genel bottan gitti. - kutadgu.net",
            12, 'deneme-iletisim>genel');
        $r['bot'] = 'genel (iletisim botu tanimli degil)';
    } else {
        $r = tg_gonder($it['token'], $it['chat'],
            "Kutadgu: iletisim bildirimi calisiyor. Bize yazin formundan gelen iletiler buraya dusecek. - kutadgu.net",
            12, 'deneme-iletisim');
        $r['bot'] = 'iletisim';
    }
    cikti($r);
}

/* SON DENEMELERİN KÜTÜĞÜ.
   "Gelmedi" diyen birine sorulacak ilk soru "denendi mi" ve ikincisi
   "Telegram ne dedi"dir. İkisinin de cevabı burada, sunucuya girmeden
   okunabilsin. Kütükte gizli bir şey yoktur: belirtecin yalnız ilk altı
   hanesi yazılır. */
if ($yol === '/yonetim/tg-kutuk' && $metod === 'GET') {
    yonetim_okuma_gerek();
    $yol2 = veri_yolu('telegram.log');
    $satirlar = [];
    if (is_file($yol2)) {
        $ham = @file($yol2, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $satirlar = array_slice($ham, -60);
    }
    cikti(['ok' => true, 'satir' => $satirlar, 'toplam' => count($satirlar)]);
}





/* bilinmeyen */
cikti(['ok' => false, 'hata' => 'Bilinmeyen uç: ' . $yol], 404);
