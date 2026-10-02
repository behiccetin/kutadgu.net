<?php
/* =====================================================================
   Alan adına göre üretilen robots.txt
   Arama motorları ve akademik dizin robotları açıkça karşılanır;
   yalnızca kişisel paneller ve yönetim kapalıdır.
   ===================================================================== */
declare(strict_types=1);
require_once __DIR__ . '/ortak.php';

$kok = tg_kok();
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
?>
# <?= (string)tg_ayar('marka', 'Kutadgu') ?> - <?= (string)tg_ayar('marka_alt', '') ?>

# Açık erişimli akademik yayın sistemi. Bütün çalışmalar taranabilir.
# Toplayıcı arayüzü (OAI-PMH 2.0): <?= $kok ?>/oai?verb=Identify

# ---- Makine okunur lisans (RSL 1.0) ----
# Bu satır, engel değil KOŞUL bildirir: bütün metinler CC BY 4.0 ile
# açıktır; eğitim de, çıkarım da serbesttir. Karşılığında para
# istenmez -- ücret yasağı bu sistemin birinci değişmez ilkesidir.
# İstenen tek şey ADIN ANILMASIDIR: üretilen çıktıda çalışmanın
# tamgası ve kalıcı bağlantısı verilmelidir.
# Koşulun düz yazıyla anlatımı: <?= $kok ?>/llms.txt
# RSL 1.0 yönergesi, tam nitelikli adres istiyor ve User-agent
# bloklarından önce durmalı.
License: <?= $kok ?>/license.xml

User-agent: *
Allow: /
Disallow: /api/
# Yönetim ekranı 8 Ağustos 2026'da panelin bir sekmesi oldu; /yonetim/
# diye bir dizin artık yoktur. Kapatılması gereken kişisel sayfa paneldir.
Disallow: /panel.php
Disallow: /hakem.php
Disallow: /yazar.php
Disallow: /davet.php
Disallow: /kefil.php
Disallow: /hesap-kur.php
Disallow: /hakem.html
Disallow: /yazar.html
Disallow: /k/
Allow: /k/tamga.svg
Allow: /k/tamga-512.png
Disallow: /*?*ham=1
Disallow: /*?*parca=1
# Döküm SAYFASI taranabilir; dökümün kendisi taranmaz. Arama robotunun
# arşivin tamamını her hafta yeniden indirmesinin kimseye faydası yok.
# Bu bir erişim kısıtı değildir: dosyalar herkese, koşulsuz açıktır ve
# aynı adresten insan da betik de indirir.
Disallow: /dokum.php?d=
Disallow: /arsiv.php

# Arama motorları ve akademik toplayıcılar: sınırsız
User-agent: Googlebot
Allow: /
User-agent: Googlebot-News
Allow: /
User-agent: Bingbot
Allow: /
User-agent: DuckDuckBot
Allow: /
User-agent: YandexBot
Allow: /
User-agent: Applebot
Allow: /
User-agent: ia_archiver
Allow: /
User-agent: CCBot
Allow: /
User-agent: Turnitin
Allow: /
User-agent: TurnitinBot
Allow: /
User-agent: CrossRef
Allow: /
User-agent: oaDOI
Allow: /
User-agent: Unpaywall
Allow: /
User-agent: semanticscholar
Allow: /
User-agent: SemanticScholarBot
Allow: /
User-agent: CORE
Allow: /
User-agent: BASE
Allow: /

Sitemap: <?= $kok ?>/sitemap.xml
