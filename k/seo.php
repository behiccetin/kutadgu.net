<?php
/* =====================================================================
   KUTADGU - Arama motoru ve dizin görünürlüğü / Search and index visibility
   Yapılandırılmış veri (schema.org), Highwire Press ve Dublin Core
   etiketlerini tek yerden üretir. Google, Google Scholar ve akademik
   dizin toplayıcıları aynı kaynaktan beslenir.
   ===================================================================== */

require_once __DIR__ . '/../ortak.php';

if (!function_exists('sq_json')) {

    function sq_json(array $d): string {
        return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    /* Bir çalışmanın bütün yazar adları, düz metin olarak */
    function sq_yazarlar(array $y): array {
        $ad = [];
        $ilk = trim((string)($y['yazar'] ?? ''));
        $b = $y['yazar_bilgi'] ?? null;
        if (is_array($b) && trim((string)($b['ad'] ?? '')) !== '') $ilk = trim((string)$b['ad']);
        if ($ilk !== '') $ad[] = ['ad' => $ilk, 'orcid' => is_array($b) ? trim((string)($b['orcid'] ?? '')) : '', 'kurum' => is_array($b) ? trim((string)($b['kurum'] ?? '')) : ''];
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
            if (!is_array($ya)) continue;
            $n = trim((string)($ya['ad'] ?? ''));
            if ($n === '') continue;
            foreach ($ad as $v) { if (tg_ad_anahtar($v['ad']) === tg_ad_anahtar($n)) continue 2; }
            $ad[] = ['ad' => $n, 'orcid' => trim((string)($ya['orcid'] ?? '')), 'kurum' => trim((string)($ya['kurum'] ?? ''))];
        }
        return $ad;
    }

    /* Unvanları atıp yalın ad bırakır: dizinler unvanı ad sanmasın */
    function sq_yalin(string $ad): string {
        return trim((string)preg_replace('/\b(prof|doç|doc|dr|öğr|ogr|arş|ars|uzm)\b\.?\s*/iu', '', $ad));
    }

    /* Yayıncı kimliği: her sayfada aynı */
    function sq_yayinci(): array {
        return [
            '@type' => 'Organization',
            'name'  => (string)tg_ayar('marka', 'Kutadgu'),
            'url'   => tg_kok(),
            'logo'  => ['@type' => 'ImageObject', 'url' => tg_kok() . '/k/tamga-512.png'],
            'founder' => sq_kurucular(),
        ];
    }

    /* Kurucular: kurucu baş editörler kaydından okunur, elle yazılmaz.
       Kayıt okunamazsa alan boş bir dizi olur ve hiçbir ad uydurulmaz. */
    function sq_kurucular(): array {
        $l = [];
        if (function_exists('tg_kurucu_bas_editorler')) {
            foreach ((array)tg_kurucu_bas_editorler() as $k) {
                $ad = is_array($k) ? trim((string)($k['ad'] ?? '')) : trim((string)$k);
                if ($ad !== '') $l[] = ['@type' => 'Person', 'name' => $ad];
            }
        }
        return $l;
    }

    /* Sistemin kendisi: ana sayfada ve genel sayfalarda */
    function sq_site(bool $en = false): string {
        $kok = tg_kok();
        $ad  = tg_marka($en);
        $alt = tg_marka_alt($en);
        $d = ['@context' => 'https://schema.org', '@graph' => [
            [
                '@type' => 'WebSite',
                '@id'   => $kok . '/#site',
                'url'   => $kok . '/',
                'name'  => $ad,
                'alternateName' => $alt,
                'inLanguage' => k_dil(),
                'publisher' => ['@id' => $kok . '/#kurum'],
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $kok . '/yazilar.php?q={search_term_string}'],
                    'query-input' => 'required name=search_term_string',
                ],
            ],
            [
                '@type' => ['Organization', 'Periodical'],
                '@id'   => $kok . '/#kurum',
                'name'  => $ad,
                'url'   => $kok . '/',
                'description' => k_c('Hakemli ve hakemsiz akademik çalışmaların ücretsiz yayımlandığı, hakem raporlarının açıkça gösterildiği bağımsız açık erişim yayın sistemi.', 'An independent open access academic publishing system in which peer reviewed and non reviewed scholarly work is published without charge, with the reviewer reports shown openly.'),
                'inLanguage' => ['tr', 'en'],
                'logo' => ['@type' => 'ImageObject', 'url' => $kok . '/k/tamga-512.png'],
                'founder' => sq_kurucular(),
                'foundingDate' => '2026',
                'isAccessibleForFree' => true,
                'publishingPrinciples' => $kok . '/ilkeler.php',
                'ethicsPolicy' => $kok . '/ilkeler.php',
                /* Sistemin kendi kalıcı kimliği. Kavram DOI'si yazılır:
                   sürümünki bu düğümü bir yazılım sürümüne bağlardı, oysa
                   burada tanımlanan şey kurumun kendisidir. Alınmamışsa
                   alan hiç yazılmaz; boş bir identifier, arama motoruna
                   var olmayan bir kimlik bildirmektir. */
            ] + (tg_doi() !== '' ? [
                'identifier' => ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => tg_doi()],
                'sameAs' => tg_doi_adres(),
            ] : []),
        ]];
        return '<script type="application/ld+json">' . sq_json($d) . '</script>';
    }

    /* Bir çalışma: ScholarlyArticle */
    function sq_yazi(array $y, string $url, bool $en = false, bool $geriCekildi = false): string {
        $kok = tg_kok();
        $cSq = $en ? tg_yazi_ceviri($y, 'en') : [];
        /* Üstveride yalnız YAYIN sayılan çeviri kullanılır: onaysız bir
           makine çevirisini Scholar'a "bu çalışmanın başlığı" diye
           vermek, kaydı çevirinin arkasına gizler. Ölçüt tek yerdedir
           (ortak.php, tg_ceviri_yayin_mi). */
        if (!tg_ceviri_yayin_mi($cSq)) $cSq = [];
        $bas = trim(strip_tags((string)(($cSq['baslik'] ?? '') !== '' ? $cSq['baslik'] : ($y['baslik'] ?? ''))));
        $ozet = trim(strip_tags((string)(($cSq['ozet'] ?? '') !== '' ? $cSq['ozet'] : ($y['ozet'] ?? ''))));
        $anah = trim(tg_metin($en && trim(tg_metin($y['anahtar_en'] ?? '')) !== '' ? $y['anahtar_en'] : ($y['anahtar'] ?? '')));
        $tarih = substr((string)($y['tarih'] ?? ''), 0, 10);

        $yazarlar = [];
        foreach (sq_yazarlar($y) as $a) {
            $p = ['@type' => 'Person', 'name' => sq_yalin($a['ad'])];
            if ($a['orcid'] !== '') $p['identifier'] = (preg_match('#^https?://#i', $a['orcid']) ? $a['orcid'] : 'https://orcid.org/' . $a['orcid']);
            if ($a['kurum'] !== '') $p['affiliation'] = ['@type' => 'Organization', 'name' => $a['kurum']];
            $yazarlar[] = $p;
        }

        $d = [
            '@context' => 'https://schema.org',
            '@type'    => 'ScholarlyArticle',
            '@id'      => $url . '#calisma',
            'headline' => mb_substr($bas, 0, 110, 'UTF-8'),
            'name'     => $bas,
            'url'      => $url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $url],
            /* Bildirilen dil, BİLDİRİLEN BAŞLIĞIN dili olmalı. Okuma
               yardımı sayılan çeviride başlık kaydın kendi dilinden
               geliyor; inLanguage 'en' deseydi üstveri kendi içinde
               çelişirdi: Türkçe bir başlığın dili İngilizce yazılırdı. */
            'inLanguage' => $cSq ? tg_gorunen_dil($y, $en) : (tg_yazi_dili($y) ?: 'tr'),
            'isAccessibleForFree' => true,
            'license'  => (string)tg_ayar('lisans_url', 'https://creativecommons.org/licenses/by/4.0/'),
            'publisher' => sq_yayinci(),
            'copyrightHolder' => $yazarlar ? $yazarlar[0] : sq_yayinci(),
        ];
        if ($yazarlar) $d['author'] = $yazarlar;
        /* creditText: "bu çalışmayı anarken şunu yaz" alanı. Dize
           tg_kunye()'den gelir, yani OKURA GÖSTERİLEN künyenin ta
           kendisidir. Ayrı yazılsalardı biri değiştiğinde öteki eski
           kalır ve sistem okura başka, dizine başka bir künye verirdi.
           usageInfo, koşulun düz yazıyla anlatıldığı adresi gösterir;
           lisansın kendisi zaten license alanında.  */
        $d['creditText'] = tg_kunye($y, $en);
        $d['usageInfo']  = $kok . '/llms.txt';
        if ($ozet !== '') $d['abstract'] = mb_substr($ozet, 0, 1200, 'UTF-8');
        if ($tarih !== '') { $d['datePublished'] = $tarih; $d['dateModified'] = $tarih; }
        if ($anah !== '') $d['keywords'] = array_values(array_filter(array_map('trim', preg_split('/[;,]/u', $anah))));

        $kod = trim((string)($y['bcid'] ?? ''));
        $doi = trim((string)($y['doi'] ?? ''));
        $kimlik = [];
        if ($doi !== '') { $d['sameAs'] = 'https://doi.org/' . $doi; $kimlik[] = ['@type' => 'PropertyValue', 'propertyID' => 'DOI', 'value' => $doi]; }
        if ($kod !== '') $kimlik[] = ['@type' => 'PropertyValue', 'propertyID' => (string)tg_ayar('tamga_ad', 'Tamga'), 'value' => $kod];
        if ($kimlik) $d['identifier'] = $kimlik;

        /* Açık hakemlik: raporlar da yapılandırılmış veride görünür */
        $rapor = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $r = ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => sq_yalin((string)($h['ad'] ?? ''))]];
            if (!empty($h['tarih'])) $r['datePublished'] = substr((string)$h['tarih'], 0, 10);
            $kar = (string)($h['karar'] ?? '');
            if ($kar !== '') $r['reviewBody'] = (k_c('Karar: ', 'Decision: ')) . k_karar_kisa($kar, $en);
            $rapor[] = $r;
        }
        /* Hakemlik iddiası yalnızca gerçekten hakemden geçmiş çalışmada
           basılır. Ölçüt 'tur' değil tg_hakemden_gecti()'dir: 'tur'
           çalışmanın hangi yolda olduğunu söyler, o yolun neresinde
           olduğunu değil. Rapor yoksa yapılandırılmış veride "Açık
           hakemlik" diye bir satır bulunmaz; olmayan bir değerlendirme
           Google'a ve dizinlere olmuş gibi bildirilemez. */
        if ($rapor) $d['review'] = $rapor;
        if ($rapor && tg_hakemden_gecti($y)) {
            $d['peerReview'] = k_c('Açık hakemlik', 'Open peer review');
        }

        /* Çalışmanın durumu. @type her çalışmada 'ScholarlyArticle'
           kalır: bu tür metnin akademik olduğunu söyler, hakemden
           geçtiğini değil, dolayısıyla daraltılacak bir iddia değildir.
           Daraltılması gereken durum bildirimidir: schema.org'da bunun
           yeri creativeWorkStatus'tır ve rapor gelmemiş bir çalışma için
           doğru olan 'Preprint'tir, tıpkı OAI'de submittedVersion gibi.
           Hakemden geçmiş çalışmada ayrıca bir durum yazılmaz; onu
           peerReview ve review alanları zaten söyler. */
        /* Buradaki iki değer bilerek tg_asama_metni()'den alınmıyor.
           Okura basılan rozet değil, schema.org'un kendi sözlüğünden
           bir terim: karşı taraf onu dizin yazılımıyla okuyor ve
           beklediği yazımın dışına çıkmak alanı geçersiz kılar. Rozet
           metni Türkçeleşse ya da kısalsa bile bu iki dizge yerinde
           kalmalı; ikisinin ayrı durmasının sebebi budur. Aynı gerekçe
           OAI'deki semantics/* değerleri için de geçerlidir. */
        $asama = tg_hakem_asamasi($y);
        if ($asama === 'aranan')  $d['creativeWorkStatus'] = 'Preprint';
        if ($asama === 'suruyor') $d['creativeWorkStatus'] = 'Under review';
        /* Reddedilen çalışma da durumunu söyler. Sayfa bunu bandıyla
           söylüyor; yapılandırılmış veri susarsa dizin, reddedilmiş bir
           metni sıradan bir yazı sanır. */
        if ($asama === 'reddedildi') $d['creativeWorkStatus'] = 'Rejected';
        /* Yayın sonrası şerhler de yapılandırılmış veride görünür.
           Perdelenmiş şerhin metni verilmez; yalnızca varlığı bildirilir. */
        $yorum = [];
        foreach (tg_serhler($y) as $s) {
            if (!is_array($s)) continue;
            $c = ['@type' => 'Comment', 'author' => ['@type' => 'Person', 'name' => sq_yalin((string)($s['ad'] ?? ''))]];
            if (!empty($s['tarih'])) $c['datePublished'] = substr((string)$s['tarih'], 0, 10);
            $c['text'] = is_array($s['perde'] ?? null)
                ? (k_c('Bu şerh bir editör tarafından perdelenmiştir.', 'This note has been screened by an editor.'))
                : sq_yalin(mb_substr((string)($s['metin'] ?? ''), 0, 900, 'UTF-8'));
            $yorum[] = $c;
        }
        if ($yorum) { $d['comment'] = $yorum; $d['commentCount'] = count($yorum); }

        if ($geriCekildi) {
            $d['creativeWorkStatus'] = 'Retracted';
            $d['name'] = (k_c('[GERİ ÇEKİLDİ] ', '[RETRACTED] ')) . $bas;
        }

        $iz = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => k_c('Ana sayfa', 'Home'), 'item' => $kok . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => k_c('Çalışmalar', 'Works'), 'item' => $kok . '/yazilar.php'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => mb_substr($bas, 0, 80, 'UTF-8')],
        ]];

        return '<script type="application/ld+json">' . sq_json($d) . '</script>' . "\n"
             . '<script type="application/ld+json">' . sq_json($iz) . '</script>';
    }

    function k_karar_kisa(string $k, bool $en): string {
        $m = ['kabul' => ['Kabul', 'Accepted'], 'kucuk' => ['Küçük revizyon', 'Minor revision'],
              'buyuk' => ['Büyük revizyon', 'Major revision'], 'ret' => ['Ret', 'Rejected']];
        return isset($m[$k]) ? tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]], $en) : $k;
    }

    /* Highwire Press etiketleri: Google Scholar bunları okur.
       Her yazar için ayrı bir citation_author satırı gerekir. */
    function sq_scholar(array $y, string $url, bool $en = false, bool $geriCekildi = false): string {
        $out = [];
        $e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $cSq = $en ? tg_yazi_ceviri($y, 'en') : [];
        /* Üstveride yalnız YAYIN sayılan çeviri kullanılır: onaysız bir
           makine çevirisini Scholar'a "bu çalışmanın başlığı" diye
           vermek, kaydı çevirinin arkasına gizler. Ölçüt tek yerdedir
           (ortak.php, tg_ceviri_yayin_mi). */
        if (!tg_ceviri_yayin_mi($cSq)) $cSq = [];
        $bas = trim(strip_tags((string)(($cSq['baslik'] ?? '') !== '' ? $cSq['baslik'] : ($y['baslik'] ?? ''))));
        if ($geriCekildi) $bas = (k_c('[GERİ ÇEKİLDİ] ', '[RETRACTED] ')) . $bas;
        $ozet = trim(strip_tags((string)(($cSq['ozet'] ?? '') !== '' ? $cSq['ozet'] : ($y['ozet'] ?? ''))));
        $anah = trim(tg_metin($en && trim(tg_metin($y['anahtar_en'] ?? '')) !== '' ? $y['anahtar_en'] : ($y['anahtar'] ?? '')));
        $tarih = (string)($y['tarih'] ?? '');
        $yayin = trim((string)($y['yayin'] ?? ''));
        $doi   = trim((string)($y['doi'] ?? ''));

        $out[] = '<meta name="citation_title" content="' . $e($bas) . '">';
        foreach (sq_yazarlar($y) as $a) {
            $out[] = '<meta name="citation_author" content="' . $e(sq_yalin($a['ad'])) . '">';
            if ($a['kurum'] !== '') $out[] = '<meta name="citation_author_institution" content="' . $e($a['kurum']) . '">';
            if ($a['orcid'] !== '') $out[] = '<meta name="citation_author_orcid" content="' . $e($a['orcid']) . '">';
        }
        if ($tarih !== '') $out[] = '<meta name="citation_publication_date" content="' . $e(str_replace('-', '/', substr($tarih, 0, 10))) . '">';
        $out[] = '<meta name="citation_journal_title" content="' . $e($yayin !== '' ? $yayin : (string)tg_ayar('marka', 'Kutadgu')) . '">';
        $out[] = '<meta name="citation_publisher" content="' . $e((string)tg_ayar('marka', 'Kutadgu')) . '">';
        if ($doi !== '') $out[] = '<meta name="citation_doi" content="' . $e($doi) . '">';
        $out[] = '<meta name="citation_public_url" content="' . $e($url) . '">';
        $out[] = '<meta name="citation_abstract_html_url" content="' . $e($url) . '">';
        $out[] = '<meta name="citation_fulltext_html_url" content="' . $e($url) . '">';
        $gorDil = tg_gorunen_dil($y, $en);
        $out[] = '<meta name="citation_language" content="' . $e($gorDil) . '">';
        if ($ozet !== '') $out[] = '<meta name="citation_abstract" content="' . $e(mb_substr($ozet, 0, 900, 'UTF-8')) . '">';
        if ($anah !== '') $out[] = '<meta name="citation_keywords" content="' . $e(str_replace(';', ',', $anah)) . '">';

        /* Dublin Core: akademik toplayıcıların çoğu bunu da okur */
        $out[] = '<meta name="DC.title" content="' . $e($bas) . '">';
        foreach (sq_yazarlar($y) as $a) $out[] = '<meta name="DC.creator" content="' . $e(sq_yalin($a['ad'])) . '">';
        if ($tarih !== '') $out[] = '<meta name="DC.date" content="' . $e(substr($tarih, 0, 10)) . '">';
        $out[] = '<meta name="DC.publisher" content="' . $e((string)tg_ayar('marka', 'Kutadgu')) . '">';
        $out[] = '<meta name="DC.type" content="Text.Article">';
        $out[] = '<meta name="DC.format" content="text/html">';
        $out[] = '<meta name="DC.identifier" content="' . $e($url) . '">';
        $out[] = '<meta name="DC.language" content="' . $e($gorDil) . '">';
        $out[] = '<meta name="DC.rights" content="' . $e((string)tg_ayar('lisans_url', '')) . '">';
        if ($ozet !== '') $out[] = '<meta name="DC.description" content="' . $e(mb_substr($ozet, 0, 900, 'UTF-8')) . '">';

        return implode("\n", $out);
    }
}
