<?php
/* =====================================================================
   UZUN ÇİZGİ (—) KAPISI · depoya girmez.
   ---------------------------------------------------------------------
   KURUL KARARI, 15 Ağustos 2026: "sistemde hiç uzun çizgi olmasın."

   NEDEN BİR KAPI GEREKİR. Bir kez taranıp temizlenen bir işaret, bir
   sonraki cümlede geri gelir; kimse fark etmez, çünkü uzun çizgi
   yazarken doğal görünür. Kural ancak ölçülürse kuraldır.

   NE ÖLÇÜLÜR: SAYFANIN GÖSTERDİĞİ METİN. Kaynak dosyadaki açıklama
   satırları taranmaz — onlar kullanıcıya gösterilmez ve kararın konusu
   değildir ("sistemde" denen şey ekranda görünen şeydir). Ölçüm bu
   yüzden HTTP üzerinden yapılır: sayfa neyi basıyorsa o okunur, betik
   ve biçim etiketleri atılır, varlık kaçışları çözülür.

   İKİ DİL AYRI ÖLÇÜLÜR: bir dilde temizlenen cümlenin öteki dilde
   kalması bu sistemde defalarca görüldü.

   Kullanım:
     KUTADGU_DATA=/.../ktest-data KPORT=8941 php uzun-cizgi-kapi.php
   ===================================================================== */
declare(strict_types=1);

const KOKA = 'http://127.0.0.1:';
define('KPORT', getenv('KPORT') ?: '8941');

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "\n         $ek" : '') . "\n"; }
}

/* Sayfa listesi yazi-tipi-kapi.js ile aynı mantıkla tutulur: herkese
   açık ve kişi girişi gerektirmeyen sayfalar. Giriş isteyen sayfalar
   (panel gibi) kapıya kapalı hâliyle girer; kapalı hâli de metindir. */
const SAYFALAR = [
    '/', '/nasil-isler.php', '/yazilar.php', '/ara.php', '/istatistik.php',
    '/hakemlik.php', '/bekleyen.php', '/hakemler.php', 
    '/ilkeler.php', '/kurul.php', '/yz.php', '/acikliklar.php',
    '/basvuru.php', '/kilavuz.php', '/destek.php', '/iletisim.php', '/harita.php',
    '/bildiri.php', '/dokum.php', '/kefil.php', '/lisans.php',
    '/hesap-kur.php', '/panel.php', '/arsiv.php', '/kimlik.php',
    /* Bir ÇALIŞMA sayfası ve bir KİŞİ sayfası da taranır: bu iki
       şablonun kendi metni (rozetler, künye satırları, uyarı kutuları)
       başka hiçbir sayfada geçmez. Sınama arşivindeki kayıtlarda yazar
       metni yoktur, dolayısıyla bulunan her çizgi ŞABLONUNDUR.
       Yazarın kendi metnindeki çizgi bu kuralın konusu değildir:
       yayımlanan kayıt yazarın yazdığı gibi durur. */
    '/tamga/KTG-2024-00001-1', '/kisi/behic-cetin',
];
const DILLER = ['tr', 'en'];

function govde(string $yol, string $dil): ?string {
    $ayrac = strpos($yol, '?') === false ? '?' : '&';
    $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'timeout' => 30]]);
    $h = @file_get_contents(KOKA . KPORT . $yol . $ayrac . 'lang=' . $dil, false, $ctx);
    return $h === false ? null : (string)$h;
}

/* Görünen metin: betik, biçim ve HTML açıklamaları çıkarılır. */
function gorunen(string $html): string {
    $h = preg_replace('#<script\b.*?</script>#is', ' ', $html);
    $h = preg_replace('#<style\b.*?</style>#is', ' ', (string)$h);
    $h = preg_replace('#<!--.*?-->#s', ' ', (string)$h);
    $h = preg_replace('#<[^>]+>#s', ' ', (string)$h);
    return html_entity_decode((string)$h, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

echo "== Görünen metinde uzun çizgi ==\n";
$toplamSayfa = 0; $bulgu = [];
foreach (SAYFALAR as $y) {
    foreach (DILLER as $d) {
        $h = govde($y, $d);
        if ($h === null) { den("sayfa okunabildi: $y ($d)", false, 'istek başarısız'); continue; }
        $toplamSayfa++;
        $t = preg_replace('/\s+/u', ' ', gorunen($h));
        if (mb_strpos((string)$t, '—', 0, 'UTF-8') === false) continue;
        /* Bulgunun bağlamı yazılır: hangi cümlede olduğu görülmeden
           düzeltilemez. */
        preg_match_all('/.{0,55}—.{0,55}/u', (string)$t, $mm);
        foreach (array_slice($mm[0], 0, 3) as $orn) $bulgu[] = "$y ($d): …" . trim($orn) . "…";
    }
}
echo "  ÖLÇÜM  taranan sayfa: $toplamSayfa\n";
den('görünen metinde hiç uzun çizgi yok', $bulgu === [], implode("\n         ", $bulgu));

/* -----------------------------------------------------------------
   ÇEVİRİ SÖZLÜKLERİ
   -----------------------------------------------------------------
   Altı makine çevirisi dilinin karşılıkları ayrı dosyalardadır ve
   yukarıdaki tarama onları görmez (o dillerde sayfa açılmıyorsa
   basılmazlar). Değerlerde uzun çizgi kalmışsa o dilde okuyan kişi
   onu görür. Anahtarlar İngilizce özgün metindir; onlar da taranır.
   ----------------------------------------------------------------- */
echo "\n== Çeviri sözlükleri ==\n";
$KOD = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$cev = glob($KOD . '/k/ceviri/*.json') ?: [];
den('çeviri dosyaları bulundu', $cev !== [], (string)count($cev));
$cBulgu = [];
foreach ($cev as $dosya) {
    $j = json_decode((string)@file_get_contents($dosya), true);
    if (!is_array($j)) continue;
    foreach ($j as $k => $v) {
        if (!is_string($v)) continue;
        if (mb_strpos($v, '—', 0, 'UTF-8') !== false) $cBulgu[] = basename($dosya) . ': ' . mb_substr($v, 0, 80, 'UTF-8');
    }
}
den('çeviri karşılıklarında uzun çizgi yok', $cBulgu === [],
    implode("\n         ", array_slice($cBulgu, 0, 8)) . (count($cBulgu) > 8 ? "\n         (+" . (count($cBulgu) - 8) . ')' : ''));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
