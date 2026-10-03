<?php
/* =====================================================================
   KAYNAK KODUN ADRESİ · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 15 Ağustos 2026 akşamı. Depo özel (GitHub hesabında
   yalnız 'stata' ve 'host' herkese açık); kutadgunet adresi herkese
   404 dönüyor. Site bunu biliyordu: ayar.php'de 'depo_acik' => false
   duruyor ve sayfalar bağı hiç basmıyor.

   AMA İKİ DOSYA HÂLÂ O ADRESİ DUYURUYORDU:
     CITATION.cff  repository-code:  → Zenodo, GitHub atıf kutusu,
                                        Zotero ve cffconvert bunu okur
     .zenodo.json  related_identifiers → arşiv kaydının kendisi
   Yani sistem, ulaşılamayan bir adresi "kaynağın yeri" diye ÜÇÜNCÜ
   TARAFLARA bildiriyordu. İnsana gösterilmeyen bir yanlış, makineye
   gösterilince yanlış olmaktan çıkmaz.

   BU KAPI NE ÖLÇER: anahtarın (depo_acik) ve onu duyuran her yerin
   AYNI ŞEYİ söylediğini. Depo açıldığı gün anahtar true olur; kapı o
   zaman adresin geri konmasını ister. Kapalıyken ise hiçbir yerde
   canlı bir depo adresi bulunmamalıdır.

   NEDEN GEREKLİ: adres beş dosyada geçiyor ve üçü kurucunun kendi
   araçları (KUR.bat, CONTRIBUTING.md) — onlar için özel depo adresi
   DOĞRUdur, kurucunun erişimi var. Ayrım elle takip edilemez.

   Kullanım: KUTADGU_DATA=<veri> php depo-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

$k = tg_kaynak();
$acik = (bool)$k['depo_acik'];
olc('depo bugün: ' . ($acik ? 'AÇIK' : 'KAPALI') . ' · adres: ' . ($k['depo'] ?: '-'));

echo "\n== 1. Tek kaynak ==\n";
den('tg_kaynak() var', function_exists('tg_kaynak'));
den('tg_kaynak_depo() var', function_exists('tg_kaynak_depo'));
den('tg_kaynak_adres() var', function_exists('tg_kaynak_adres'));
den('tg_kaynak_adi() var', function_exists('tg_kaynak_adi'));
/* Kapalıyken depo adresi BOŞ döner: "varsa bas" diyen bir sayfa,
   olmayan bir bağı hiç basmasın diye. */
den('kapalıyken tg_kaynak_depo() boş döner', $acik || tg_kaynak_depo() === '', tg_kaynak_depo());
den('tg_kaynak_adres() her hâlde bir adres veriyor', tg_kaynak_adres() !== '', tg_kaynak_adres());
den('  ve adı ne olduğunu söylüyor', tg_kaynak_adi(false) !== '', tg_kaynak_adi(false));
/* Kapalıyken adres Zenodo kaydına gitmeli: kaynak zipi oradan iniyor.
   AGPL §13 kaynağın SUNULMASINI ister; 404 sunulmuş sayılmaz. */
if (!$acik) {
    den('  kapalıyken adres Zenodo kaydına gidiyor',
        str_contains(tg_kaynak_adres(), 'zenodo') || str_contains(tg_kaynak_adres(), 'doi.org'),
        tg_kaynak_adres());
}

echo "\n== 2. İşlevin çağıranı var mı ==\n";
/* Tek kaynak yazılıp kullanılmazsa tek kaynak değildir: 'destek-ad'
   borcu tam olarak buydu. */
$cagiran = [];
foreach (['ortak.php', 'llms.php', 'k/kabuk.php', 'kimlik.php', 'index.php', 'acikliklar.php', 'ilkeler.php'] as $d) {
    $y = $KOD . '/' . $d;
    if (!is_file($y)) continue;
    $src = (string)file_get_contents($y);
    if ($d === 'ortak.php') continue;                 /* tanımın kendisi */
    if (preg_match('/tg_kaynak_(adres|depo|adi)\s*\(/', $src)) $cagiran[] = $d;
}
olc('çağıran dosyalar: ' . ($cagiran ? implode(', ', $cagiran) : 'YOK'));
den('adresi üreten işlevin en az bir çağıranı var', $cagiran !== [], 'hiç çağrılmıyor');

echo "\n== 3. Elle yazılmış canlı depo adresi ==\n";
/* KURUCUNUN KENDİ ARAÇLARI AYRIDIR. KUR.bat ve CONTRIBUTING.md depoyu
   klonlamak içindir ve kurucunun erişimi vardır; orada özel adres
   doğrudur. Ölçülen yer, ÜÇÜNCÜ TARAFA giden dosyalardır. */
$disari = ['CITATION.cff', '.zenodo.json'];
$bulgu = [];
foreach ($disari as $d) {
    $y = $KOD . '/' . $d;
    if (!is_file($y)) { $bulgu[] = $d . ': dosya yok'; continue; }
    foreach (explode("\n", (string)file_get_contents($y)) as $no => $sat) {
        if (!preg_match('#github\.com/[A-Za-z0-9._-]+/[A-Za-z0-9._-]+#', $sat)) continue;
        /* Yorum satırı sayılmaz: yorumda duran adres duyurulmuş değil,
           açıklanmıştır. CFF yorumu '#', JSON'da yorum yoktur. */
        if (preg_match('/^\s*#/', $sat)) continue;
        $bulgu[] = $d . ':' . ($no + 1) . '  ' . trim(mb_substr($sat, 0, 90));
    }
}
if ($acik) {
    den('depo AÇIKKEN üçüncü taraf dosyalarında adres YAZILI', $bulgu !== [],
        'depo açık ama CITATION.cff/.zenodo.json adresi duyurmuyor');
} else {
    den('depo KAPALIYKEN üçüncü taraf dosyalarında canlı adres yok', $bulgu === [],
        implode(' | ', $bulgu));
}

echo "\n== 4. Sayfalarda kırık bağ yok ==\n";
/* Sayfalar bağı yalnız açıkken basmalı. Kapalıyken bir sayfada
   github.com adresi geçiyorsa okur 404 görür. */
$sayfa = [];
foreach (glob($KOD . '/*.php') ?: [] as $y) {
    /* AYAR DOSYASI TARANMAZ: oradaki adres basılan bir bağ değil,
       açıldığı gün kullanılacak olan DEĞERdir. Anahtar (depo_acik)
       zaten kapalı ve sayfalar o anahtara bakıyor. Ayarı da kusur
       saymak, kapalı bir düğmenin varlığını kusur saymak olurdu. */
    if (basename($y) === 'ayar.php') continue;
    $src = (string)file_get_contents($y);
    /* Yorumlar elenir: token_get_all ile ayrıştırmak en dürüst yol. */
    $tk = @token_get_all($src);
    if (!$tk) continue;
    foreach ($tk as $t) {
        if (!is_array($t)) continue;
        if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        if (!preg_match('#github\.com/behiccetin/kutadgunet#', $t[1])) continue;
        $sayfa[] = basename($y) . ':' . $t[2];
    }
}
if ($acik) {
    olc('depo açık; sayfalarda adres bulunması normal: ' . count($sayfa));
    den('depo açıkken sayfa taraması bir sonuç veriyor', true);
} else {
    den('depo kapalıyken hiçbir sayfa depo adresini basmıyor', $sayfa === [],
        implode(' | ', $sayfa));
}
/* ayar.php'deki adres bir AYARDIR, basılan bir bağ değil: orada
   durması doğrudur ve açıldığı gün kullanılacak olan odur. */
den('adres ayar dosyasında saklı duruyor (açıldığı gün kullanılacak)',
    $k['depo'] !== '', 'ayar.php kaynak.depo boş');

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
