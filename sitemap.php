<?php
/* =====================================================================
   Alan adına göre üretilen site haritası.
   Her kurulum kendi adresini yayınlar; tek dosya yeter.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

$kok     = tg_kok();
$kutadgu = true;
$yazilar = k_yazilar();
$tamYol  = trim((string)tg_ayar('tamga_yol', 'tamga'), '/');

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=3600');

/* Her adres iki dilde bildirilir: hreflang ile eşlenince çift içerik sayılmaz */
function sm_url(string $loc, string $sik, string $onc, string $tarih = '', bool $ikiDil = true): void {
    $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
    $ayrac = strpos($loc, '?') === false ? '?' : '&';
    echo "  <url>\n    <loc>" . $e($loc) . "</loc>\n";
    if ($tarih !== '') echo "    <lastmod>" . $e($tarih) . "</lastmod>\n";
    echo "    <changefreq>$sik</changefreq>\n    <priority>$onc</priority>\n";
    if ($ikiDil) {
        echo '    <xhtml:link rel="alternate" hreflang="tr" href="' . $e($loc) . "\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="en" href="' . $e($loc . $ayrac . 'lang=en') . "\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . $e($loc) . "\"/>\n";
    }
    echo "  </url>\n";
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

if ($kutadgu) {
    sm_url($kok . '/', 'daily', '1.0');
    sm_url($kok . '/yazilar.php', 'daily', '0.9');
    sm_url($kok . '/nasil-isler.php', 'monthly', '0.8');
    sm_url($kok . '/ilkeler.php', 'monthly', '0.7');
    sm_url($kok . '/hakemlik.php', 'monthly', '0.7');
    sm_url($kok . '/basvuru.php', 'monthly', '0.7');
    sm_url($kok . '/kilavuz.php', 'monthly', '0.7');
    sm_url($kok . '/yz.php', 'monthly', '0.7');
    sm_url($kok . '/kurul.php', 'monthly', '0.6');
    sm_url($kok . "/uygulama.php", "monthly", "0.6");
    sm_url($kok . "/bekleyen.php", "weekly", "0.7");
    sm_url($kok . "/iletisim.php", "monthly", "0.6");
    sm_url($kok . '/harita.php', 'monthly', '0.5');
    sm_url($kok . '/dokum.php', 'weekly', '0.6');
    /* Eski dışa aktarım adresi: dizinde kalır, kırılmadığı görünsün. */
    sm_url($kok . "/arsiv.php", "weekly", "0.4", "", false);
    sm_url($kok . "/acikliklar.php", "monthly", "0.7");
    sm_url($kok . '/bildiri.php', 'yearly', '0.8');
    sm_url($kok . '/kimlik.php', 'yearly', '0.5');
    sm_url($kok . '/istatistik.php', 'weekly', '0.6');
    sm_url($kok . '/destek.php', 'monthly', '0.7');

    /* Kişi sayfaları: arşivde kamusal bir izi olan herkes. Sayfalar
       kayıtlardan üretildiği için liste de kayıtlardan çıkarılır;
       elle tutulan bir liste zamanla gerçeğe uymaz. */
    $kisiler = [];
    foreach ($yazilar as $y) {
        if (!is_array($y)) continue;
        foreach (tg_yazar_kayitlari($y) as $ya) {
            $s2 = tg_ad_slug((string)($ya['ad'] ?? '')); if ($s2 !== '') $kisiler[$s2] = true;
        }
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $s2 = tg_ad_slug((string)($h['ad'] ?? '')); if ($s2 !== '') $kisiler[$s2] = true;
        }
    }
    ksort($kisiler);
    foreach (array_keys($kisiler) as $s2) sm_url($kok . '/kisi/' . rawurlencode($s2), 'weekly', '0.5');
} else {
    sm_url($kok . '/', 'daily', '1.0');
    sm_url($kok . '/cv.html', 'weekly', '0.8');
    sm_url($kok . '/yazilar.php', 'daily', '0.8');
}

/* Her çalışma YALNIZCA kalıcı adresiyle bildirilir. Slug adresi kalıcı
   adrese 301 ile yönlendiği için ayrıca bildirilmez; böylece arama
   motorlarında çift kayıt oluşmaz ve bütün güç tek adreste toplanır. */
foreach ($yazilar as $y) {
    if (trim((string)($y['slug'] ?? '')) === '' && trim((string)($y['id'] ?? '')) === '') continue;
    /* GERİ ÇEKİLEN ÇALIŞMA HARİTAYA GİRMEZ. Sayfası 'noindex' bildiriyor
       (yazi.php); haritada durmayı sürdürseydi sistem arama motoruna iki
       ayrı şey söylerdi: "bunu dizine alma" ve "bunu ziyaret et". İki
       bildirim çeliştiğinde hangisinin kazanacağı motora kalır ve kural
       uygulanmamış olur. Kayıt silinmez, yalnızca haritadan düşer. */
    if (function_exists('tg_geri_cekildi') && tg_geri_cekildi($y)) continue;
    $tarih = preg_match('/^\d{4}-\d{2}-\d{2}/', (string)($y['tarih'] ?? '')) ? substr((string)$y['tarih'], 0, 10) : '';
    sm_url($kok . tg_yazi_yolu($y), 'monthly', '0.9', $tarih);
}

echo "</urlset>\n";
