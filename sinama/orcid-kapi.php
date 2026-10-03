<?php
/* =====================================================================
   ORCID KAPISI. Depoya girmez.

   ORCID bir DIŞ servistir ve bu kapının ölçtüğü şeylerin çoğu, o servis
   hiç çağrılmadan ölçülebilir. Ağa çıkan bir sınama, ağ yavaşken
   "kusur" der; burada ağa çıkılmıyor.

   ÖLÇÜLENLER
   ----------
   1. KAPALI GELİYOR MU. Ayar üçlüsü (acik + istemci + gizli) dolmadan
      sistem ORCID'den hiç söz etmemeli: düğme basılmamalı, uç 404
      vermeli. Yarım açık bir kapı, çalışmayan bir düğmedir.
   2. GİZLİ ANAHTAR DEPODA MI. ayar.php'deki 'gizli' alanı boş olmalı;
      değer veri dizinindedir. Depoya giren anahtar geçmişte kalır.
   3. TEK GİRİŞ YOLU DEĞİL. Parola alanları yerinde durmalı.
   4. ORCID BİÇİMİ. 16 hane, X yalnız sonda. Biçimi tutmayan bir değer
      ORCID değildir ve kabul edilmemelidir.
   5. DURUM (state) TEK KULLANIMLIK MI. İkinci kez sorulduğunda
      düşmeli; düşmeyen bir durum değeri, CSRF'ye açık kapıdır.
   6. KİMLİK ≠ UNVAN. Kodda hiçbir yerde ORCID doğrulaması bir role ya
      da belge onayına bağlanmamalı.

   Kullanım: KTEST_DIR=... KUTADGU_DATA=... KPORT=8941 php orcid-kapi.php
   ===================================================================== */
declare(strict_types=1);

const KOK = 'http://127.0.0.1:';
define('KPORT', getenv('KPORT') ?: '8941');
$KOD = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ist(string $yol): array {
    $ch = curl_init(KOK . KPORT . $yol);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 10]);
    $g = (string)curl_exec($ch);
    $k = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['kod' => $k, 'govde' => $g];
}

echo "== ORCID kapısı ==\n";

$ayar  = (string)@file_get_contents($KOD . '/ayar.php');
$modul = (string)@file_get_contents($KOD . '/k/orcid.php');
$api   = (string)@file_get_contents($KOD . '/api/index.php');
$panel = (string)@file_get_contents($KOD . '/panel.php');

/* ---------------------------------------------------------------------
   1. KAPALI GELİYOR
   --------------------------------------------------------------------- */
echo "\n== 1. Öntanımlı olarak kapalı ==\n";
den('ayar.php ORCID bloğunu taşıyor', strpos($ayar, "'orcid' => [") !== false);
/* İSTEMCİ KİMLİĞİ ARTIK DEPODA VE BU DOĞRUDUR.
   İlk yazımda "istemci boş olmalı" deniyordu; kimliğin de bir sır
   olduğu varsayılmıştı. Değil: istemci kimliği, ORCID'e giden yetki
   adresinin içinde her ziyaretçinin tarayıcısında görünür. Gizli olan
   yalnız 'gizli' alanıdır ve o veri dizinindedir.
   Ölçü şuna çevrildi: kimlik doluysa biçimi doğru olsun, gizli alan
   ise HER KOŞULDA boş kalsın. */
$acikMi = (bool)preg_match("#'orcid'\s*=>\s*\[[^\]]*'acik'\s*=>\s*true#s", $ayar);
olc('ORCID ayarı: ' . ($acikMi ? 'açık' : 'kapalı'));
den('  istemci kimliği ya boş ya da APP- biçiminde',
    (bool)preg_match("#'orcid'\s*=>\s*\[.*?'istemci'\s*=>\s*'(APP-[A-Z0-9]{10,}|)'#s", $ayar));

/* GİZLİ ANAHTAR OLMADAN AÇIK SAYILMAZ. Sınama ortamının veri dizininde
   orcid_gizli yoktur; ayar 'acik' olsa bile tg_orcid_acik() false
   döner ve uçlar kapalı kalır. Ölçülen şey budur: üç parçadan biri
   eksikse kapı açılmaz. Bu, yarım yapılandırmanın en sık görülen
   hâlidir ve sessizce çalışan bir düğme üretmemelidir. */
$a = ist('/api/orcid/git');
den('gizli anahtar yokken /orcid/git 404 veriyor', $a['kod'] === 404, 'kod ' . $a['kod']);
$a = ist('/api/orcid/donus?code=x&state=y');
den('gizli anahtar yokken /orcid/donus 404 veriyor', $a['kod'] === 404, 'kod ' . $a['kod']);

$a = ist('/panel.php');
den('gizli anahtar yokken sayfada ORCID düğmesi YOK', strpos($a['govde'], 'orcid/git') === false);

/* ---------------------------------------------------------------------
   2. GİZLİ ANAHTAR DEPODA DEĞİL
   --------------------------------------------------------------------- */
echo "\n== 2. Gizli anahtar ==\n";
den("ayar.php'deki 'gizli' alanı boş", (bool)preg_match("#'orcid'\s*=>\s*\[[^\]]*'gizli'\s*=>\s*''#s", $ayar));
den('  gizli değer veri dizininden okunuyor (canlı ayar)',
    strpos($modul, "tg_canli_ayar('orcid_gizli'") !== false);

/* =====================================================================
   YAZAN AD İLE OKUYAN AD AYNI MI

   Bu deneme bir kusurdan doğdu. Veri dosyalarının adı değişti ve
   api/index.php içindeki veri_gecis() eski dosyaları yeni ada TAŞIDI;
   ama ortak.php'deki okuyucu eski adı aramayı sürdürdü. Taşıma yapıldığı
   an ayar dosyası "yok" sayıldı ve bütün canlı ayarlar sessizce
   varsayılana düştü. Hiçbir hata çıkmadı, çünkü "dosya yoksa varsayılan"
   zaten tasarlanmış davranıştı.

   Bir yeniden adlandırmanın en tehlikeli yanı budur: yazan taraf taşınır,
   okuyan taraf unutulur ve sistem çalışıyormuş gibi görünür. Bu yüzden
   ölçü artık ikisinin AYNI ADI söylediğini arıyor.
   ===================================================================== */
echo "\n== 2.b Taşıma ile okuma aynı adı gösteriyor mu ==\n";
$ortak = (string)@file_get_contents($KOD . '/ortak.php');
$hedefAd = '';
if (preg_match("#'ruh-ayar\.json'\s*=>\s*'([^']+)'#", $api, $mH)) $hedefAd = $mH[1];
olc('veri_gecis() ayar dosyasını şuraya taşıyor: ' . ($hedefAd ?: 'taşıma yok'));
den('taşıma hedefi tg_canli_ayar() içinde de aranıyor',
    $hedefAd === '' || strpos($ortak, "/' . \$ad") !== false || strpos($ortak, $hedefAd) !== false,
    $hedefAd . ' ortak.php icinde gecmiyor');
den('  eski ad da yedek olarak okunuyor (taşınmamış sunucu düşmesin)',
    strpos($ortak, 'ruh-ayar.json') !== false);
/* Depoda APP- ile başlayan gerçek bir istemci kimliği durmamalı. */
/* Yer tutucu (APP-XXXX...) kusur değildir; kurulum satırının ne
   yazılacağını gösterir. Aranan şey GERÇEK bir kimliktir: içinde
   X'ten başka harf ya da rakam geçen. İlk yazımda kalıp yer
   tutucuyu da yakaladı ve kapı, hiçbir sır sızmamışken yandı. */
/* Aranan şey GİZLİ ANAHTARdır. ORCID gizli anahtarı UUID biçimindedir
   (8-4-4-4-12). Depoda böyle bir dize varsa kapı yanar. İstemci kimliği
   aranmaz: o gizli değildir. */
$sir = [];
foreach ([$ayar, $modul, $api, $panel] as $d) {
    if (preg_match_all('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', $d, $mS)) {
        foreach ($mS[0] as $x) $sir[] = substr($x, 0, 8) . '…';
    }
}
den('  depoda ORCID gizli anahtarı biçiminde bir dize yok', $sir === [], implode(', ', $sir));

/* ---------------------------------------------------------------------
   3. TEK GİRİŞ YOLU DEĞİL
   --------------------------------------------------------------------- */
echo "\n== 3. Parola yolu duruyor ==\n";
den('parola alanı sayfada duruyor', strpos($a['govde'], 'id="gParola"') !== false);
den('  kullanıcı adı alanı da duruyor', strpos($a['govde'], 'id="gKim"') !== false);
den('  giriş ucu yerinde', strpos($api, "'/hesap/giris'") !== false);

/* ---------------------------------------------------------------------
   4. ORCID BİÇİMİ
   --------------------------------------------------------------------- */
echo "\n== 4. Biçim denetimi ==\n";
$oturum = '/tmp/orcid-bicim.php';
file_put_contents($oturum, "<?php\nputenv('KUTADGU_DATA=" . (getenv('KUTADGU_DATA') ?: '') . "');\n"
    . "\$_SERVER['HTTP_HOST']='127.0.0.1';\n"
    . "require '" . $KOD . "/ortak.php';\nrequire '" . $KOD . "/k/orcid.php';\n"
    . "\$d = ['0000-0002-1825-0097','0000000218250097','0000-0002-1825-009X'];\n"
    . "\$y = ['123','0000-0002-1825-00X7','abcd-efgh-ijkl-mnop',''];\n"
    . "foreach (\$d as \$x) echo (tg_orcid_bicim(\$x)!=='' ? 'E':'H');\n"
    . "echo '|';\n"
    . "foreach (\$y as \$x) echo (tg_orcid_bicim(\$x)==='' ? 'E':'H');\n");
$c = trim((string)shell_exec('php ' . escapeshellarg($oturum) . ' 2>&1'));
@unlink($oturum);
olc('geçerli/geçersiz sonucu: ' . $c);
den('geçerli üç biçim kabul, geçersiz dört biçim ret', $c === 'EEE|EEEE', $c);

/* ---------------------------------------------------------------------
   5. DURUM DEĞERİ TEK KULLANIMLIK
   --------------------------------------------------------------------- */
echo "\n== 5. Durum (state) ==\n";
den('durum oturumda saklanıyor', strpos($modul, "\$_SESSION['orcid_durum']") !== false);
den('  okunur okunmaz siliniyor (tek kullanımlık)',
    (bool)preg_match('#function tg_orcid_durum_al.*?unset\(\$_SESSION\[.orcid_durum.\]\);#s', $modul));
den('  sabit zamanlı karşılaştırma', strpos($modul, 'hash_equals') !== false);
den('  süresi var (10 dakika)', (bool)preg_match('#time\(\) - \(int\)\(\$s\[.zaman.\] \?\? 0\) > 600#', $modul));
den('  yetki adresi /authenticate yetkisiyle sınırlı',
    strpos($modul, "'scope'         => '/authenticate'") !== false
    || (bool)preg_match("#'scope'\s*=>\s*'/authenticate'#", $modul));

/* ---------------------------------------------------------------------
   6. KİMLİK UNVAN DEĞİLDİR
   --------------------------------------------------------------------- */
echo "\n== 6. Kimlik ≠ unvan ==\n";
/* ORCID doğrulaması hiçbir yerde rol ya da belge onayı yazmamalı. */
$blok = '';
/* Blok, ORCID başlığından ORCID uçlarının SONUNA kadardır. İlk
   yazımda "sonraki ===== bloğu" aranıyordu ve o çok ileride olduğu
   için araya ORCID'le ilgisi olmayan yirmi bin harf giriyordu;
   'belge' sözcüğü orada geçiyor ve kapı yanlış yanıyordu. Ölçüm,
   baktığı yerin sınırını da bilmeli. */
if (preg_match("#/\* =+\s*\n\s*ORCID İLE KİMLİK.*?/orcid/bekleyen.*?\n\}#s", $api, $mb)) $blok = $mb[0];
olc('ORCID uç bloğu: ' . strlen($blok) . ' harf');
den('uç bloğu rol yazmıyor', $blok !== '' && strpos($blok, "'roller'") === false);
den('  belge onayı vermiyor', $blok !== '' && strpos($blok, 'belge') === false);
den('  doğrulama bir hesap AÇMIYOR (kayıt ekranına gönderiyor)',
    strpos($blok, "orcid_bekleyen") !== false && strpos($blok, 'hs_kaydet($hesap)') === false);
den('bir ORCID iki hesapta duramıyor', strpos($api, 'function orcid_sahibi') !== false
    && strpos($blok, 'baskasinda') !== false);
den('yalnız DOĞRULANMIŞ kayıt giriş açıyor',
    (bool)preg_match("#function orcid_sahibi.*?empty\(\\\$h\['orcid_dogrulandi'\]\).*?continue#s", $api));
den('numara elle değişirse doğrulama düşüyor',
    (bool)preg_match("#unset\(\\\$h\['orcid_dogrulandi'\], \\\$h\['orcid_yol'\]\)#", $api));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
