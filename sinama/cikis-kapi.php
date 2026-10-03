<?php
/* =====================================================================
   ÇIKIŞ KAPISI. Depoya girmez.

   ÖLÇTÜĞÜ ŞEY TEK BİR CÜMLEDİR: "Çıkış yap" düğmesine basan kişi
   sistemden GERÇEKTEN çıkmış olmalıdır.

   NEDEN AYRI BİR KAPI GEREKTİ
   ---------------------------
   Sistemde iki ayrı kimlik vardır ve ikisi aynı oturumun içinde yan
   yana durabilir:

     $_SESSION['kutadgu_hesap']  hesap kimliği (yazar, hakem, editör)
     $_SESSION['behic_giris']    yönetici kimliği (tek parolalı kapı)

   Arayüzdeki bütün çıkış düğmeleri /api/hesap/cikis ucuna gidiyordu ve
   o uç yalnız BİRİNCİSİNİ siliyordu. Yönetici kimliği oturumda kalıyor,
   kişi çıkmış olduğunu sanıyor, ardından yeniden girmeye çalışınca
   parola yanlış olsa bile "içeride" kalmayı sürdürüyordu: ekranda hata
   görünüyor, yetki duruyordu.

   girisli() küçük bir bayrak değildir: yonetim_yazma_mi() içinde tek
   başına yeter ve bütün /yonetim/* uçlarını açar. Yani bu, bir görüntü
   kusuru değil, kapanmayan bir kapıdır.

   ÖLÇÜM YOLU: gerçek uçlara gerçek istek. Kural koda yazılmış olabilir;
   uç onu çağırmıyorsa kural yoktur.

   Kullanım:
     KUTADGU_DATA=/.../veri KPORT=8941 php cikis-kapi.php
   ===================================================================== */
declare(strict_types=1);

const KOK = 'http://127.0.0.1:';
define('KPORT', getenv('KPORT') ?: '8941');
define('DATA', rtrim((string)(getenv('KUTADGU_DATA') ?: ''), '/'));

if (DATA === '' || !is_dir(DATA)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* ---------------------------------------------------------------------
   YEDEK: bu betik auth.json ve hesaplar.json yazar. Yedek en başta
   alınır, geri yükleme kapanışa bağlanır; ölümcül hatada da çalışır.
   --------------------------------------------------------------------- */
const YEDEKLENEN = ['auth.json', 'hesaplar.json', 'hiz-sinir.json'];
$YEDEK = [];
foreach (YEDEKLENEN as $d) {
    $k = DATA . '/' . $d; $h = '/tmp/cikis-yedek-' . $d;
    if (is_file($k)) { copy($k, $h); $YEDEK[$d] = $h; } else { $YEDEK[$d] = null; @unlink($h); }
}
register_shutdown_function(function () use ($YEDEK): void {
    foreach ($YEDEK as $d => $h) {
        $k = DATA . '/' . $d;
        if ($h === null) { @unlink($k); continue; }
        @copy($h, $k); @unlink($h);
    }
});

/* ---- Kurulum: bir yönetici kimliği ve bir hesap ---- */
$YON_PAROLA = 'kapiOlcumu-2026-xQ';   /* yalnız bu ölçüm için; diske karması yazılır */
$HES_EPOSTA = 'cikis-olcum@example.org';
$HES_PAROLA = 'kapiOlcumu-2026-hesap';

file_put_contents(DATA . '/auth.json', json_encode(
    ['kullanici' => 'olcumcu', 'hash' => password_hash($YON_PAROLA, PASSWORD_DEFAULT)],
    JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$hesaplar = is_file(DATA . '/hesaplar.json')
    ? (array)json_decode((string)file_get_contents(DATA . '/hesaplar.json'), true) : [];
$hesaplar = array_values(array_filter($hesaplar, fn($h) => is_array($h)
    && strtolower((string)($h['eposta'] ?? '')) !== $HES_EPOSTA));
$hesaplar[] = ['eposta' => $HES_EPOSTA, 'parola' => password_hash($HES_PAROLA, PASSWORD_DEFAULT),
               'ad' => 'Ölçüm Kişisi', 'unvan' => '', 'kurum' => '', 'roller' => [],
               'kullanici' => '', 'katilim' => date('c'), 'giris' => []];
file_put_contents(DATA . '/hesaplar.json', json_encode($hesaplar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
@unlink(DATA . '/hiz-sinir.json');   /* önceki ölçümlerin sayacı bu ölçümü kilitlemesin */

/* ---- Tek bir çerez kavanozu: tarayıcı gibi davranırız ---- */
$KAVANOZ = tempnam('/tmp', 'cikis-cerez');
function ist(string $yol, ?array $govde = null): array {
    global $KAVANOZ;
    $ch = curl_init(KOK . KPORT . $yol);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => false,
        CURLOPT_COOKIEJAR => $KAVANOZ, CURLOPT_COOKIEFILE => $KAVANOZ,
        CURLOPT_TIMEOUT => 15,
    ]);
    if ($govde !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($govde, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $g = (string)curl_exec($ch);
    $k = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['kod' => $k, 'govde' => $g, 'json' => json_decode($g, true)];
}

echo "== Çıkış kapısı ==\n";

/* ---------------------------------------------------------------------
   1. İKİ KİMLİKLE DE GİRİLİYOR MU (ölçümün önkoşulu)
   --------------------------------------------------------------------- */
echo "\n== 1. Giriş (önkoşul) ==\n";
$a = ist('/api/login', ['kullanici' => 'olcumcu', 'parola' => $YON_PAROLA]);
den('yönetici girişi kabul edildi', !empty($a['json']['ok']), 'kod ' . $a['kod'] . ' ' . substr($a['govde'], 0, 120));
$a = ist('/api/durum');
den('  /durum yönetici oturumunu görüyor', !empty($a['json']['girisli']), $a['govde']);

$a = ist('/api/hesap/giris', ['kim' => $HES_EPOSTA, 'parola' => $HES_PAROLA]);
den('hesap girişi kabul edildi', !empty($a['json']['ok']), 'kod ' . $a['kod'] . ' ' . substr($a['govde'], 0, 120));
$a = ist('/api/hesap/durum');
den('  /hesap/durum hesabı görüyor', !empty($a['json']['girisli']), substr($a['govde'], 0, 120));

/* ---------------------------------------------------------------------
   2. ASIL ÖLÇÜM: "ÇIKIŞ YAP" NE KADARINI KAPATIYOR

   Arayüzdeki bütün çıkış düğmeleri bu uca gider (k/kutadgu.js ve
   panel.php). Bu yüzden ölçüm de tam olarak bu ucu çağırır: kişinin
   bastığı düğme neyi çağırıyorsa o ölçülmelidir.
   --------------------------------------------------------------------- */
echo "\n== 2. Çıkış ==\n";
$a = ist('/api/hesap/cikis', []);
den('çıkış ucu yanıt verdi', !empty($a['json']['ok']), $a['govde']);

$a = ist('/api/hesap/durum');
den('çıkıştan sonra HESAP kimliği düştü', empty($a['json']['girisli']), substr($a['govde'], 0, 120));

$a = ist('/api/durum');
den('çıkıştan sonra YÖNETİCİ kimliği de düştü', empty($a['json']['girisli']), $a['govde']);

/* Bayrağın düşmesi yetmez: yetki gerçekten kapanmalı. girisli() tek
   başına /yonetim/* uçlarını açar; kapının kapandığını ancak kapıyı
   iterek anlarız. */
$a = ist('/api/guven-liste');
den('çıkıştan sonra yönetim ucu 401 veriyor', $a['kod'] === 401, 'kod ' . $a['kod'] . ' ' . substr($a['govde'], 0, 80));

/* ---------------------------------------------------------------------
   3. YANLIŞ PAROLA İLE GİRİŞ, İÇERİ ALMAMALI

   Kusurun kişiye görünen yüzü buydu: çıkış yapılıyor, yeniden
   giriliyor, ekranda hata beliriyor ama panel yine açılıyordu. Hata
   ile yetkinin aynı anda durması, ölçülmesi gereken şeydir.
   --------------------------------------------------------------------- */
echo "\n== 3. Yanlış parola ==\n";
$a = ist('/api/login', ['kullanici' => 'olcumcu', 'parola' => 'bu-parola-yanlis-000']);
den('yanlış parola reddedildi', empty($a['json']['ok']), $a['govde']);
$a = ist('/api/durum');
den('  ve reddedilen deneme oturum açmadı', empty($a['json']['girisli']), $a['govde']);
$a = ist('/api/guven-liste');
den('  yönetim ucu hâlâ kapalı', $a['kod'] === 401, 'kod ' . $a['kod']);

/* ---------------------------------------------------------------------
   4. TEK KAYNAK: ARAYÜZDEKİ ÇIKIŞ DÜĞMELERİ AYNI UCA GİDİYOR MU

   İki ayrı çıkış ucu olsaydı biri düzeltilir öteki unutulurdu. Kaynak
   okunarak sayılır: kaç ayrı çıkış adresi çağrılıyor.
   --------------------------------------------------------------------- */
echo "\n== 4. Tek çıkış yolu ==\n";
$KOD = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$js  = (string)@file_get_contents($KOD . '/k/kutadgu.js');
$pnl = (string)@file_get_contents($KOD . '/panel.php');
$hepsi = $js . "\n" . $pnl;
preg_match_all('#[\'"]/?(?:api/)?(hesap/cikis|cikis)[\'"]#', $hepsi, $m);
$adresler = array_unique($m[1] ?? []);
olc('arayüzün çağırdığı çıkış adresleri: ' . (implode(', ', $adresler) ?: 'yok'));
den('arayüzde tek bir çıkış adresi var', count($adresler) === 1, implode(', ', $adresler));

$api = (string)@file_get_contents($KOD . '/api/index.php');
/* Gövde, işlev başlığından dosyanın ilk sütundaki kapanış ayracına
   kadardır. Önceki yazımda "en çok 400 harf" deniyordu; işlev uzayınca
   ölçüm, davranış düzelmiş olduğu hâlde KALDI demeye başladı. Ölçüm
   metnin uzunluğuna değil, sınırına bakmalıdır. */
$gov = '';
if (preg_match('#\nfunction hs_cikis\(\)[^{]*\{(.*?)\n\}#s', $api, $mg)) $gov = $mg[1];
den('çıkış ucu oturumun tamamını yıkıyor (session_destroy)',
    $gov !== '' && strpos($gov, 'session_destroy') !== false,
    $gov === '' ? 'hs_cikis gövdesi okunamadı' : 'gövdede session_destroy yok');
den('  ve oturum dizisini de boşaltıyor', strpos($gov, '$_SESSION = []') !== false);
den('  ölü oturum çerezi de düşürülüyor', strpos($gov, 'setcookie(session_name()') !== false);

@unlink($KAVANOZ);
echo "\n== Sonuç: $gecti geçti, $kaldi kaldı ==\n";
exit($kaldi > 0 ? 1 : 0);
