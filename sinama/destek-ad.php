<?php
/* =====================================================================
   "Destekleyen araştırmacı" adı: kapı ölçümü. Depoya girmez.

   Bu düzenin görünen adı "kefil"den "destekleyen araştırmacı"ya geçti,
   ama kod anahtarları bilerek olduğu gibi bırakıldı; gerekçesi ortak.php
   ve ayar.php içindeki "ad değişti, anahtar değişmedi" başlıklı
   yorumlarda yazılı: anahtarlar yayımlanmış kayıtların içindedir ve
   gönderilmiş onay bağlantıları o adrese gider. Kapı iki yönlüdür:
   eski ad kullanıcıya görünmeyecek, kod anahtarı da kaybolmayacak.

   Kullanım:
     KUTADGU_DATA=<veri> KPORT=<kapi> php destek-ad.php
   ===================================================================== */
declare(strict_types=1);

const KTEST = '/home/claude/kg/ktest';
$VERI = (string)(getenv('KUTADGU_DATA') ?: '');
$PORT = (string)(getenv('KPORT') ?: '8941');
$KOK  = 'http://127.0.0.1:' . $PORT;

/* Ölçüm, ölçtüğü kodun kendi yardımcılarını da çağırır: adı üreten
   işlevin dönüşünü sayfadan tahmin etmek yerine doğrudan sormak için. */
require_once KTEST . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* ---- HTTP ----------------------------------------------------------
   Sunucu hız sınırı uygular; bu betik tek oturumda otuza yakın istek
   atar ve sınıra takılırsa boş sayfa ölçer, yani yanlış GECTI verir.
   429 görüldüğünde sayaç dosyası sıfırlanıp istek bir kez yinelenir. */
function ist(string $yol): array {
    global $KOK, $VERI;
    for ($deneme = 0; $deneme < 2; $deneme++) {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET', 'ignore_errors' => true, 'timeout' => 30,
            /* Dil çerezi ya da ülke tahmini araya girmesin diye sade istek */
            'header' => "Accept-Language: tr,en\r\n"]]);
        $g = @file_get_contents($KOK . $yol, false, $ctx);
        $h = $http_response_header ?? [];
        $kod = 0;
        foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
        if ($kod !== 429) return ['kod' => $kod, 'govde' => (string)$g];
        if ($VERI !== '' && is_dir($VERI)) @file_put_contents($VERI . '/hiz-sinir.json', '{}');
    }
    return ['kod' => 429, 'govde' => ''];
}

/* Kullanıcıya görünen metin: betik ve stil blokları çıkarılır, etiketler
   soyulur, varlıklar çözülür. Ham gövdede aramak ölçümü bozar; çünkü
   sınıf adı "kefil-k", çapa "id=kefil" ve "/kefil.php" bağlantısı ham
   gövdede vardır ama hiçbiri kullanıcının okuduğu metin değildir. */
function gorunen_metin(string $html): string {
    $h = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    return html_entity_decode(strip_tags((string)$h), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/* Etiketin içinde kalan ama yine de okunan yerler: başlık niteliği,
   yer tutucu, erişilebilirlik etiketi, resim alt metni. */
function gorunen_oznitelik(string $html): array {
    $h = (string)preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);
    $out = [];
    if (preg_match_all('/\b(title|alt|placeholder|aria-label)\s*=\s*"([^"]*)"/i', $h, $m)) {
        foreach ($m[2] as $i => $v) $out[] = $m[1][$i] . '="' . html_entity_decode($v, ENT_QUOTES, 'UTF-8') . '"';
    }
    return $out;
}

/* Betikteki dizeleri düzenli ifadeyle çekmek burada işe yaramaz: sayfa
   içi betiklerin yorumları Türkçedir ve "ORCID'i" gibi tek tırnak taşır;
   tırnaklar eşleşmediği için ifade satırları birbirine bağlar ve kod
   parçasını "cümle" sanır. Bu yüzden küçük bir tarayıcı yazıldı: yorum
   atlanır, dize sınırı satır sonunu geçmez. */
function betik_dizeleri(string $js): array {
    $out = []; $n = strlen($js); $i = 0;
    while ($i < $n) {
        $c = $js[$i];
        if ($c === '/' && $i + 1 < $n && $js[$i + 1] === '/') { while ($i < $n && $js[$i] !== "\n") $i++; continue; }
        if ($c === '/' && $i + 1 < $n && $js[$i + 1] === '*') { $k = strpos($js, '*/', $i + 2); $i = $k === false ? $n : $k + 2; continue; }
        if ($c === '"' || $c === "'" || $c === '`') {
            $j = $i + 1; $s = '';
            while ($j < $n) {
                if ($js[$j] === '\\') { $s .= substr($js, $j, 2); $j += 2; continue; }
                if ($js[$j] === $c) break;
                /* Şablon dizesi dışında satır sonu dizeyi kapatır; kapanmayan
                   tırnak burada biter, sonraki satıra taşmaz. */
                if ($js[$j] === "\n" && $c !== '`') break;
                $s .= $js[$j]; $j++;
            }
            if ($j < $n && $js[$j] === $c) { $out[] = $s; $i = $j + 1; } else { $i++; }
            continue;
        }
        $i++;
    }
    return $out;
}

/* Sayfa içi betiklerdeki insan metni. Değişken, sınıf ve seçici adları
   burada da "kefil" taşır (kefilTopla, .kefil-alan, [data-kefil-alan]);
   cümle sayılması için dizenin boşluk taşıması ve işaretleme parçası
   olmaması aranır. */
function betik_cumleleri(string $html): array {
    $out = [];
    if (!preg_match_all('#<script\b[^>]*>(.*?)</script>#is', $html, $bl)) return $out;
    foreach ($bl[1] as $js) {
        foreach (betik_dizeleri($js) as $s) {
            if (strlen($s) < 8 || strpos($s, ' ') === false) continue;
            if (strpos($s, '<') !== false || strpos($s, '[') !== false) continue;
            $out[] = $s;
        }
    }
    return $out;
}

function eski_ad(string $s): bool { return (bool)preg_match('/kefil|kefal/iu', $s); }

/* Ölçülecek sayfalar. Her biri iki dilde çekilir: dil seçimi yapılmazsa
   k_dil() varsayılan olarak İngilizceye düşer, yani ?lang=tr yazılmadan
   Türkçe metin hiç ölçülmemiş olur. */
$sayfalar = [
    '/'                  => 'Kutadgu',
    '/basvuru'           => 'ORCID',
    '/ilkeler'           => '<h1',
    '/nasil-isler'       => '<h1',
    '/acikliklar'        => '<h1',
    '/hakemlik'          => '<h1',
    '/destek'            => '<h1',
    '/kefil'             => '<h1',
    '/yazar'             => '<h1',
    '/kisi/zeynep-aydin' => '<h1',
];

/* Destek kaydı taşıyan bir çalışma varsa onun sayfası da ölçülür: eski
   ad en çok orada, kaydı basan bölümde kalabilir. Veri tohumunda böyle
   bir kayıt yoksa bölüm hiç basılmaz ve o sayfa boşuna "temiz" görünür,
   bu yüzden varlığı ayrıca bildirilir. */
$kayitliYol = '';
$vy = $VERI . '/yazilar.json';
if (is_file($vy)) {
    $yz = json_decode((string)file_get_contents($vy), true);
    foreach (is_array($yz) ? $yz : [] as $b) {
        if (!is_array($b)) continue;
        foreach (tg_yazar_kayitlari($b) as $ya) {
            if (!is_array($ya) || empty($ya['kefiller'])) continue;
            $bcid = (string)($b['bcid'] ?? '');
            if ($bcid !== '') { $kayitliYol = '/tamga/' . rawurlencode($bcid); }
            break 2;
        }
    }
}
if ($kayitliYol !== '') $sayfalar[$kayitliYol] = '<h1';

echo "== 1. Kullaniciya gorunen metinde eski ad yok ==\n";
$cekilen = [];
foreach ($sayfalar as $yol => $imza) {
    foreach (['tr', 'en'] as $d) {
        $ayr = strpos($yol, '?') === false ? '?' : '&';
        $c = ist($yol . $ayr . 'lang=' . $d);
        $cekilen[$yol . '|' . $d] = $c;
        /* Hata sayfası da "kefil" içermez; sayfanın gerçekten dolu geldiği
           doğrulanmazsa bu kapı kendiliğinden geçer ve hiçbir şey ölçmez. */
        $dolu = $c['kod'] === 200 && strlen($c['govde']) > 2000 && strpos($c['govde'], $imza) !== false;
        den("sayfa geldi $yol [$d]", $dolu, 'kod=' . $c['kod'] . ' uzunluk=' . strlen($c['govde']));
        if (!$dolu) continue;

        $m = gorunen_metin($c['govde']);
        preg_match_all('/.{0,40}(kefil|kefal).{0,40}/iu', $m, $bul);
        den("gorunen metinde eski ad yok $yol [$d]", count($bul[0]) === 0,
            count($bul[0]) . ' yer: ' . preg_replace('/\s+/u', ' ', implode(' | ', array_slice($bul[0], 0, 3))));

        $kotu = array_values(array_filter(gorunen_oznitelik($c['govde']), 'eski_ad'));
        den("okunan ozniteliklerde eski ad yok $yol [$d]", count($kotu) === 0, implode(' | ', array_slice($kotu, 0, 3)));

        $kc = array_values(array_filter(betik_cumleleri($c['govde']), 'eski_ad'));
        den("sayfa ici betik cumlelerinde eski ad yok $yol [$d]", count($kc) === 0, implode(' | ', array_slice($kc, 0, 3)));
    }
}
/* Kapı değil, kapsam bildirimi: kayıt bölümünün basılıp basılmadığı
   koda değil veriye bağlıdır. KALDI sayılırsa temiz veri tohumunda
   kod kusuru yokken kapı kalır; bu yüzden yalnızca yazılır. */
echo '  ---    kayit bolumu ' . ($kayitliYol !== ''
    ? "olculdu ($kayitliYol)"
    : 'OLCULMEDI: veride kefil kaydi tasiyan calisma yok, yazi.php kayit bolumu hic basilmadi') . "\n";

/* Kullanıcıya metin basan iki ortak dosya: kabuk her sayfanın çerçevesini,
   kutadgu.js tarayıcıdaki iletileri yazar. İkisinde de eski ad hiç
   geçmemeli; burada kod anahtarı da yok, o yüzden ölçüt katıdır. */
foreach (['k/kabuk.php', 'k/kutadgu.js'] as $d) {
    $ic = (string)@file_get_contents(KTEST . '/' . $d);
    preg_match_all('/.{0,40}(kefil|kefal).{0,40}/iu', $ic, $bl);
    den("$d icinde eski ad hic gecmiyor", $ic !== '' && count($bl[0]) === 0,
        $ic === '' ? 'dosya okunamadi' : (count($bl[0]) . ' yer'));
}

echo "\n== 2. Kod anahtarlari yerinde ==\n";
/* Anahtarlar yayımlanmış kaydın ve gönderilmiş bağlantının içindedir;
   ad değişikliği bunlara dokunmamalıydı. Sahibi olan dosyalarda aranır. */
$anahtar = [
    "'kefiller'"   => ['ortak.php', 'api/index.php'],
    'kefil_acik'   => ['ortak.php', 'api/index.php'],
    'kefil_sayisi' => ['ortak.php', 'api/index.php'],
    'kefil-onay'   => ['api/index.php'],
    'kefil.php'    => ['api/index.php'],
];
$icerik = [];
foreach (['ortak.php', 'api/index.php', 'panel.php'] as $d) $icerik[$d] = (string)@file_get_contents(KTEST . '/' . $d);
foreach ($anahtar as $a => $nerede) {
    foreach ($nerede as $d) {
        den("$d icinde $a duruyor", substr_count($icerik[$d], $a) > 0);
    }
}
/* panel.php bu anahtarların hiçbirini kullanmıyor; oradaki ölçüt bunun
   yerine eski adın panelin metnine geri sızmamış olmasıdır. */
den('panel.php metninde eski ad yok', !eski_ad($icerik['panel.php']));

foreach (['tg_kefiller', 'tg_kefil_durum', 'tg_kefil_eksik', 'tg_kefil_ilgi_ad'] as $f) {
    den("$f() duruyor", function_exists($f));
}
den('tg_ayar(kefil_acik) okunuyor', tg_ayar('kefil_acik', null) !== null);
den('tg_ayar(kefil_sayisi) sayi donuyor', is_int(tg_ayar('kefil_sayisi', null)));

/* Gönderilmiş bağlantıların gittiği uçlar canlıda da yaşıyor mu: 404
   dönerse eski bağlantılar kırılmış demektir. Boş gövdeye 400 beklenir. */
$ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'timeout' => 30,
    'header' => "Content-Type: application/json\r\n", 'content' => '{}']]);
foreach (['/api/kefil-bilgi', '/api/kefil-onay'] as $uc) {
    @file_get_contents($KOK . $uc, false, $ctx);
    $kod = 0;
    foreach ($http_response_header ?? [] as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    den("$uc ucu yasiyor", $kod !== 0 && $kod !== 404, 'kod=' . $kod);
}
$kf = ist('/kefil.php?k=' . str_repeat('0', 32));
den('/kefil.php adresi yasiyor', $kf['kod'] === 200, 'kod=' . $kf['kod']);

echo "\n== 3. Adi ureten tek yer ==\n";
den('tg_destek_ad() var', function_exists('tg_destek_ad'));
den('tg_destek_ad() tekil dogru', tg_destek_ad() === 'destekleyen araştırmacı', tg_destek_ad());
den('tg_destek_ad() cogul dogru', tg_destek_ad(false, true) === 'destekleyen araştırmacılar', tg_destek_ad(false, true));
den('tg_destek_ad_bas() buyuk harfle basliyor', tg_destek_ad_bas() === 'Destekleyen araştırmacı', tg_destek_ad_bas());
den('tg_destek_duzen_ad() soyut adi veriyor', tg_destek_duzen_ad() === 'yazarlık desteği', tg_destek_duzen_ad());

/* Elle yazımı saymak için metin araması yetmez: aynı tamlama yorumların
   içinde de geçiyor ve yorum kullanıcıya gitmez. Bu yüzden dosyalar
   ayrıştırılıp yalnızca dize belirteçleri sayılır. */
function php_dosyalari(string $kok): array {
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile() && strtolower($f->getExtension()) === 'php') $out[] = $f->getPathname();
    sort($out);
    return $out;
}
/* Adı üreten ailenin kendi gövdesi ne elle yazım ne de çağrı sayılır.
   Satır numarası yazmak yerine işlevin kapsadığı aralık çıkarılır;
   dosya bir satır kaydığında ölçüm sessizce yanlışa dönmesin diye. */
function islev_araliklari(string $src): array {
    $t = token_get_all($src); $n = count($t); $out = [];
    for ($i = 0; $i < $n; $i++) {
        if (!is_array($t[$i]) || $t[$i][0] !== T_FUNCTION) continue;
        $j = $i + 1;
        while ($j < $n && is_array($t[$j]) && $t[$j][0] === T_WHITESPACE) $j++;
        if ($j >= $n || !is_array($t[$j]) || $t[$j][0] !== T_STRING) continue;
        $ad = $t[$j][1]; $bas = $t[$j][2]; $son = $bas; $d = 0; $acildi = false;
        for ($k = $j; $k < $n; $k++) {
            if (is_array($t[$k])) { $son = $t[$k][2]; continue; }
            if ($t[$k] === '{') { $d++; $acildi = true; }
            elseif ($t[$k] === '}') { $d--; if ($acildi && $d === 0) break; }
        }
        $out[] = ['ad' => $ad, 'bas' => $bas, 'son' => $son];
    }
    return $out;
}
$aile = ['tg_destek_ad', 'tg_destek_ad_bas', 'tg_destek_duzen_ad'];
function aile_ici(array $araliklar, int $satir, array $aile): bool {
    foreach ($araliklar as $a) {
        if ($satir >= $a['bas'] && $satir <= $a['son'] && in_array($a['ad'], $aile, true)) return true;
    }
    return false;
}

$desen = '/destekleyen\s+ara[sş]t[iı]rmac/iu';
$elle = []; $yorumda = 0; $cagiran = [];
foreach (php_dosyalari(KTEST) as $f) {
    $src = (string)file_get_contents($f);
    $kisa = str_replace(KTEST . '/', '', $f);
    $ar = islev_araliklari($src);
    $tk = token_get_all($src);
    foreach ($tk as $sira => $t) {
        if (!is_array($t)) continue;
        [$id, $txt, $ln] = $t;

        /* Elle yazım: yalnızca dize belirteçleri sayılır. Aynı tamlama
           yorumların içinde de geçiyor ama yorum kullanıcıya gitmez. */
        $n = preg_match_all($desen, $txt);
        if ($n > 0) {
            if ($id === T_COMMENT || $id === T_DOC_COMMENT) $yorumda += $n;
            elseif (!aile_ici($ar, $ln, $aile)) {
                for ($i = 0; $i < $n; $i++) $elle[] = $kisa . ':' . $ln;
            }
        }

        /* Çağrı: metin araması burada yanılır, çünkü ayar.php yorumunda
           "tg_destek_ad() işlevinden gelir" cümlesi geçiyor ve düzenli
           ifade onu çağrı sanıp kapıyı sahte GECTI ile geçiriyor. */
        if ($id !== T_STRING || !in_array($txt, $aile, true)) continue;
        $on = $sira - 1;
        while ($on >= 0 && is_array($tk[$on]) && $tk[$on][0] === T_WHITESPACE) $on--;
        if ($on >= 0 && is_array($tk[$on]) && $tk[$on][0] === T_FUNCTION) continue;  /* tanım */
        $sn = $sira + 1;
        while ($sn < count($tk) && is_array($tk[$sn]) && $tk[$sn][0] === T_WHITESPACE) $sn++;
        if ($sn >= count($tk) || $tk[$sn] !== '(') continue;
        if (aile_ici($ar, $ln, $aile)) continue;   /* ailenin kendi içi */
        $cagiran[] = $kisa . ':' . $ln;
    }
}
den('yeni ad isleve disinda elle yazilmamis', count($elle) === 0,
    count($elle) . ' elle yazim, ornek: ' . implode(', ', array_slice($elle, 0, 4)));
/* Asıl kapı bu: işlev varsa ama kimse çağırmıyorsa tek kaynak yoktur,
   ad ikinci kez değiştiğinde yine bütün cümleler taranacak demektir. */
den('adi ureten islevin en az bir cagirani var', count($cagiran) > 0,
    'cagri ' . count($cagiran) . ' / elle ' . count($elle) . ' dize + ' . $yorumda . ' yorum');
echo "  ---    dagilim: " . implode(', ', array_map(
    fn($d, $s) => $d . ' x' . $s,
    array_keys(array_count_values(array_map(fn($x) => explode(':', $x)[0], $elle))),
    array_values(array_count_values(array_map(fn($x) => explode(':', $x)[0], $elle))))) . "\n";

echo "\n== 4. Ingilizce karsilik ==\n";
den('tg_destek_ad(en) tekil dogru', tg_destek_ad(true) === 'supporting researcher', tg_destek_ad(true));
den('tg_destek_ad(en) cogul dogru', tg_destek_ad(true, true) === 'supporting researchers', tg_destek_ad(true, true));
den('tg_destek_ad_bas(en) buyuk harfle basliyor', tg_destek_ad_bas(true) === 'Supporting researcher', tg_destek_ad_bas(true));
den('tg_destek_duzen_ad(en) destek anlamini tasiyor',
    stripos(tg_destek_duzen_ad(true), 'support') !== false, tg_destek_duzen_ad(true));
den('tg_kefil_ilgi_ad() iki dilde ayri karsilik veriyor',
    tg_kefil_ilgi_ad('ortak_yazar') !== tg_kefil_ilgi_ad('ortak_yazar', true)
    && stripos(tg_kefil_ilgi_ad('bagimsiz', true), 'doctorate') !== false);

/* Eski adın hukuk çağrışımlı İngilizce karşılıkları hiç kalmamalı. */
$eskiEn = ['guarantor', 'endorser', 'endorsement', 'surety', 'vouch'];
$kalinti = [];
foreach (php_dosyalari(KTEST) as $f) {
    $src = (string)file_get_contents($f);
    foreach ($eskiEn as $e) if (stripos($src, $e) !== false) $kalinti[] = str_replace(KTEST . '/', '', $f) . ':' . $e;
}
$js = (string)@file_get_contents(KTEST . '/k/kutadgu.js');
foreach ($eskiEn as $e) if (stripos($js, $e) !== false) $kalinti[] = 'k/kutadgu.js:' . $e;
den('eski Ingilizce karsilik kalmamis', count($kalinti) === 0, implode(', ', array_slice($kalinti, 0, 5)));

/* Sayfada da tutarlı mı: Türkçesinde ad geçen sayfanın İngilizcesinde
   karşılığı geçmeli, yoksa çeviri düşmüş demektir. */
foreach (['/basvuru', '/ilkeler', '/kefil'] as $y) {
    $tr = $cekilen[$y . '|tr']['govde'] ?? '';
    $en = $cekilen[$y . '|en']['govde'] ?? '';
    if ($tr === '' || $en === '') { den("$y iki dilde de cekildi", false); continue; }
    $trVar = (bool)preg_match($desen, gorunen_metin($tr));
    $enVar = stripos(gorunen_metin($en), 'supporting researcher') !== false
          || stripos(gorunen_metin($en), 'authorship support') !== false;
    den("$y Ingilizcesinde karsilik var", !$trVar || $enVar, 'tr=' . (int)$trVar . ' en=' . (int)$enVar);
}

echo "\nGECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
