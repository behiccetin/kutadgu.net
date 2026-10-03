<?php
/* =====================================================================
   TELİF VE LİSANS: kapı ölçümü. Depoya girmez.

   Bu sistemin en sonuçlu metni budur. Bir yazar, gönderim sırasında
   haklarına dair bir cümle imzalar; o cümle yanlışsa geri alınması en
   zor şey odur.

   ÖLÇÜLEN SÖZ, sistemin KENDİ DEĞİŞMEZ İLKESİNDEN gelir. İlke 2
   (Açık erişim) aynen şöyle yazılıdır:

     "Bütün çalışmalar, TELİF HAKKI YAZARINDA KALMAK ÜZERE, en az
      Creative Commons Atıf düzeyinde açık bir lisansla yayımlanır."

   Bu ilke oylanamaz: tg_ilke_kapisi() lisans daraltmayı reddeder ve
   tg_ayar_ilke_suz() ayar dosyası ne yazarsa yazsın lisansı geri
   çeker. Dolayısıyla ölçüt tek yönlüdür: sayfalar ilkeye uyar, ilke
   sayfalara değil.

   Kapı üç şeyi ayrı ayrı arar:
     1. İlkenin kendisi yerinde mi.
     2. Okura ve yazara görünen hiçbir yerde DEVİR istenmiyor mu.
        (Devir, ilkenin "yazarında kalmak üzere" sözünü doğrudan
        çiğner; üstelik gereksizdir, çünkü CC BY 4.0 geri alınamaz
        bir lisanstır ve arşivin kalıcılığını zaten güvenceye alır.)
     3. Yazarın verdiği izin ne ise o yazılı mı: yayımlama, arşivleme
        ve CC BY 4.0 ile dağıtma izni.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php telif-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ist(string $yol): array {
    global $PORT;
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250),
        'ignore_errors' => true, 'timeout' => 30]]);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, $ctx);
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
}
/* Okura görünen metin: betik ve biçem çıkarılır, öznitelikler eklenir. */
function gorunur(string $html): string {
    $h = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);
    preg_match_all('#\b(title|alt|aria-label|placeholder)\s*=\s*"([^"]*)"#i', (string)$h, $m);
    $t = strip_tags((string)$h) . ' || ' . implode(' | ', $m[2]);
    return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/* Devir iddiası kalıpları. Yalnız "devir" sözcüğünü aramak yetmez:
   kurul sayfasında "görevi devretti" gibi bambaşka bir kullanım var
   ve o bir telif cümlesi değildir. Bu yüzden kalıplar TELİF ile
   birlikte aranır. */
$devirTR = '/(telif|hak)\w*\s+(hak\w*\s+)?[^.]{0,60}?devre|devret\w*\s+[^.]{0,40}(telif|hak)|bütün\s+haklar\w*\s+[^.]{0,40}devr/iu';
$devirEN = '/transfer\w*\s+[^.]{0,40}copyright|copyright\s+[^.]{0,40}transferr?ed|assign\w*\s+[^.]{0,30}copyright/i';

/* =====================================================================
   1. DEĞİŞMEZ İLKE YERİNDE Mİ
   ===================================================================== */
echo "== 1. Değişmez ilke yerinde mi ==\n";
$ilkeler = tg_degismez_ilkeler();
den('sekiz değişmez ilke duruyor', count($ilkeler) === 8, (string)count($ilkeler));
$ilke2 = (string)($ilkeler[2]['ic'][0] ?? '');
$ilke2en = (string)($ilkeler[2]['ic'][1] ?? '');
olc('İlke 2: ' . mb_substr($ilke2, 0, 120));
den('ilke 2 telif hakkının YAZARDA KALDIĞINI söylüyor',
    mb_stripos($ilke2, 'yazarında kalmak') !== false, $ilke2);
den('  aynısı İngilizcede de yazılı',
    stripos($ilke2en, 'copyright remaining with the author') !== false, $ilke2en);
den('lisans daraltma oylanamıyor', tg_ilke_kapisi('Lisansı daralt')['gecer'] === false);
den('ayar süzgeci lisansı geri çekiyor',
    (string)(tg_ayar_ilke_suz(['lisans' => 'Tüm hakları saklıdır'])['lisans'] ?? '') === 'CC BY 4.0');

/* =====================================================================
   2. HİÇBİR SAYFA DEVİR İSTEMİYOR
   Yazarın imzaladığı cümle, sistemin kendi ilkesini çiğneyemez.
   ===================================================================== */
echo "\n== 2. Hiçbir sayfa devir istemiyor ==\n";
$sayfalar = ['/basvuru.php', '/ilkeler.php', '/', '/nasil-isler.php', '/acikliklar.php', '/bildiri.php'];
foreach ($sayfalar as $yol) {
    foreach (['tr', 'en'] as $dil) {
        $r = ist($yol . '?lang=' . $dil);
        if ($r['kod'] !== 200) { den("[$dil] $yol açılıyor", false, (string)$r['kod']); continue; }
        $g = gorunur($r['govde']);
        $kalip = $dil === 'tr' ? $devirTR : $devirEN;
        $var = preg_match($kalip, $g, $m) === 1;
        den("[$dil] $yol telif DEVRİ istemiyor", !$var, $var ? '...' . mb_substr($m[0], 0, 70) . '...' : '');
    }
}

/* =====================================================================
   3. YAZARIN VERDİĞİ İZİN AÇIKÇA YAZILI
   Devir istenmiyorsa, o zaman ne isteniyor? Boş bırakılamaz: yazar
   neyi kabul ettiğini bilmelidir.
   ===================================================================== */
echo "\n== 3. Yazarın verdiği izin açıkça yazılı ==\n";
$b = ist('/basvuru.php?lang=tr'); $bg = gorunur($b['govde']);
$be = ist('/basvuru.php?lang=en'); $beg = gorunur($be['govde']);
den('[tr] başvuru sayfası telif hakkının yazarda kaldığını söylüyor',
    (bool)preg_match('/telif\s+hak\w*\s+[^.]{0,40}(yazar|siz)\w*\s*(kal|ait|durur)|hak\w*\s+size\s+ait/iu', $bg));
den('[en] aynısı İngilizcede',
    (bool)preg_match('/copyright\s+[^.]{0,40}(remains?|stays?)\s+with\s+(the\s+)?(author|you)|retain\w*\s+copyright/i', $beg));
den('[tr] verilen iznin adı yazılı (yayımlama ve arşivleme)',
    mb_stripos($bg, 'arşiv') !== false && (mb_stripos($bg, 'yayımla') !== false || mb_stripos($bg, 'yayımlama') !== false));
den('[tr] lisans adıyla yazılı', mb_stripos($bg, 'CC BY 4.0') !== false);
den('[en] lisans adıyla yazılı', stripos($beg, 'CC BY 4.0') !== false);
/* İznin GERİ ALINAMAZ olduğu da yazılmalı: arşivin kalıcılık sözü
   buna dayanır ve yazar neyi kabul ettiğini bilmelidir. */
den('[tr] iznin geri alınamazlığı yazılı',
    (bool)preg_match('/geri\s+alınamaz|geri\s+çekilemez/iu', $bg));
den('[en] aynısı İngilizcede',
    (bool)preg_match('/irrevocable|cannot be revoked/i', $beg));

/* =====================================================================
   4. YAYIMLANMIŞ ÇALIŞMANIN KENDİ SAYFASI
   Makale sayfası okura "telif hakları yazara aittir" diyor. Başvuru
   sayfası bunun tersini söylüyorsa, ikisinden biri yalan söylüyordur.
   ===================================================================== */
echo "\n== 4. Yayımlanmış çalışmanın kendi sayfası ==\n";
$yz = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true);
$tamga = '';
foreach ((array)$yz as $w) { if (is_array($w) && trim((string)($w['bcid'] ?? '')) !== '') { $tamga = (string)$w['bcid']; break; } }
if ($tamga === '') { echo "  NOT    tamgalı çalışma yok\n"; }
else {
    foreach (['tr', 'en'] as $dil) {
        $a = ist('/tamga/' . rawurlencode($tamga) . '?lang=' . $dil);
        $ag = gorunur($a['govde']);
        $kalip = $dil === 'tr'
            ? '/telif\s+hak\w*\s+yazara\s+ait/iu'
            : '/rights\s+of\s+the\s+work\s+belong\s+to\s+the\s+author/i';
        den("[$dil] makale sayfası telif hakkının yazarda olduğunu yazıyor", (bool)preg_match($kalip, $ag));
        den("[$dil] makale sayfası CC BY 4.0 diyor", stripos($ag, 'CC BY 4.0') !== false);
    }
}

/* =====================================================================
   5. İKİ METİN BİRBİRİYLE ÇELİŞMİYOR
   Asıl ölçüm bu: aynı soruya iki sayfa aynı cevabı veriyor mu.
   ===================================================================== */
echo "\n== 5. Sayfalar birbiriyle çelişmiyor ==\n";
$i = ist('/ilkeler.php?lang=tr'); $ig = gorunur($i['govde']);
den('ilkeler sayfası da telif hakkının yazarda kaldığını söylüyor',
    (bool)preg_match('/telif\s+hak\w*\s+[^.]{0,40}yazar\w*\s*(kal|ait|durur)/iu', $ig));
den('ilkeler ile başvuru aynı şeyi söylüyor',
    (preg_match($devirTR, $ig) === 0) && (preg_match($devirTR, $bg) === 0));
/* Devirden vazgeçildiğinde arşivin kalıcılık sözü boşa düşmemeli:
   sebebi yazılı olmalı. */
den('arşivin kalıcılığının neye dayandığı yazılı (lisansın geri alınamazlığı)',
    (bool)preg_match('/geri\s+alınamaz/iu', $ig) || (bool)preg_match('/geri\s+alınamaz/iu', $bg));

/* =====================================================================
   6. ÖTEKİ YEDİ İLKE: SAYFALAR ONLARLA DA ÇELİŞİYOR MU

   Telif çelişkisi tek başına bir kaza değildi: ilke bir yerde yazılı,
   sayfa başka bir şey söylüyordu ve ikisi hiç karşılaştırılmamıştı.
   Aynı hata öteki ilkelerde de olabilir; bu bölüm onları arar.

   Ölçüm KABA ve bunu saklamıyor: bir ilkenin çiğnendiğini metinden
   kesin olarak çıkarmak her zaman mümkün değil. Aranan şey, ilkenin
   AÇIKÇA tersini söyleyen kalıplardır. Bulunmaması ilke tutuluyor
   demek değildir; bulunması ise bakılacak bir yer olduğunu kesin
   olarak söyler. Yanlış alarm çıkmasın diye her kalıp dar yazıldı ve
   bilinen meşru kullanımlar ayrıca elendi (aşağıda tek tek yazılı).
   ===================================================================== */
echo "\n== 6. Öteki yedi ilke: sayfalar onlarla çelişiyor mu ==\n";

$tumSayfalar = ['/', '/nasil-isler.php', '/ilkeler.php', '/acikliklar.php', '/basvuru.php',
                '/hakemlik.php', '/kurul.php', '/destek.php', '/iletisim.php', '/bildiri.php',
                '/yz.php', '/dokum.php', '/istatistik.php'];
$metin = [];
foreach ($tumSayfalar as $yol) {
    foreach (['tr', 'en'] as $dil) {
        $r = ist($yol . '?lang=' . $dil);
        if ($r['kod'] === 200) $metin[$yol . ' [' . $dil . ']'] = gorunur($r['govde']);
    }
}
olc(count($metin) . ' sayfa metni okundu (' . count($tumSayfalar) . ' sayfa x 2 dil)');

/* Her ilke için: ilkenin AÇIKÇA tersini söyleyen kalıp, artı o kalıba
   benzeyen ama meşru olan kullanımların elemesi. */
$denetim = [
  1 => ['ad' => 'Ücret yasağı',
        /* "ücret" sözcüğü tek başına suç değil: sistem zaten "hiçbir
           ücret yok" diye yazıyor ve bağış sayfası var. Aranan şey,
           yazardan/okurdan ÜCRET İSTEYEN bir cümle. */
        'kalip' => '/(yazar|okur|hakem)[^.]{0,40}(ücret|bedel|ödeme)\s*(alınır|ödenir|talep|gerekir|zorunlu)|makale\s+işlem\s+ücreti\s*(alınır|vardır)|(submission|processing|article)\s+(fee|charge)\s+(is\s+)?(required|applies|payable)/iu',
        'ele'   => []],
  3 => ['ad' => 'Açık değerlendirme',
        /* Kör hakemliğin YAPILDIĞINI söyleyen cümle. Sistem bugün
           "kör hakemlik uygulanmaz" diyor; o cümle elenmeli. */
        'kalip' => '/(kör|çift\s*kör|anonim)\s+hakemlik\s*(uygulanır|yapılır|kullanılır)|hakem\w*\s+(adı|adları|kimliği)\s+[^.]{0,30}(gizli|saklı|açıklanmaz)|reviewers?\s+remain\s+anonymous|(double[- ])?blind\s+review\s+is\s+(used|applied)/iu',
        'ele'   => []],
  4 => ['ad' => 'Kaydın değişmezliği',
        /* Yayımlanmış kaydın SİLİNECEĞİNİ söyleyen cümle. "silinmez",
           "silinemez" olumsuzları elenir. */
        'kalip' => '/(yayımlanmış|yayınlanmış)\s+[^.]{0,30}(silinir|kaldırılır|geri\s+alınır)|(arşivden|kayıttan)\s+(silinir|çıkarılır)(?!\w*maz)|published\s+[^.]{0,30}(is\s+deleted|will\s+be\s+removed)/iu',
        'ele'   => []],
  5 => ['ad' => 'Arşivin taşınabilirliği',
        /* İndirmeyi kimliğe/kayda/bedele bağlayan cümle. */
        'kalip' => '/(indir\w*|erişim)\s*(için)?\s*[^.]{0,40}(üyelik|kayıt|hesap|giriş|onay|bedel|ücret)\s*(gerek|şart|zorunlu|ister)|(download|access)\s+[^.]{0,40}requires?\s+(registration|an account|membership|payment|approval)/iu',
        'ele'   => []],
  6 => ['ad' => 'Yazılımın açıklığı',
        'kalip' => '/(yazılım|kaynak\s*kod)\w*\s+[^.]{0,30}(kapalı|gizli|özel\s+mülk)|software\s+is\s+(closed|proprietary)/iu',
        'ele'   => []],
  7 => ['ad' => 'Bağımsızlık',
        /* Destekçinin karara etki edebileceğini söyleyen cümle. */
        'kalip' => '/(bağış|destek|sponsor|himaye)\w*\s*[^.]{0,50}(karar\w*\s+etki|önceli\w*|ayrıcalık|hızlandır)|donors?\s+[^.]{0,40}(influence|priority|expedite)/iu',
        'ele'   => []],
  8 => ['ad' => 'Ayrım gözetmeme',
        /* Belirli bir ülke/kurum/dile ayrıcalık ya da dışlama. */
        'kalip' => '/(yalnızca|sadece|only)\s+(türk|turkish|yerli)\w*\s+(yazar|araştırmacı|kurum|üniversite|authors?|institutions?)|(başvuru|gönderim)\s+[^.]{0,30}(türkiye|türk\s+vatandaş)\w*\s+(ile\s+)?sınırlı/iu',
        'ele'   => []],
];

/* ÖLÇÜM TUZAĞI (OKUBENI 17'nin aynısı, bu kapı da düştü): YASAĞI
   ANLATAN CÜMLE, YASAKLANAN SÖZCÜKLERİ TAŞIR. İlk yazımda iki bayrak
   yandı ve ikisi de sahteydi:
     ilkeler.php: "Çalışmalara erişim için üyelik, abonelik ya da giriş
                   GEREKMEZ."
     kurul.php:   "Bu erişim kimlik, kayıt, üyelik, onay ya da bedel
                   şartına BAĞLANAMAZ."
   İkisi de ilkenin kendisini söylüyor, tersini değil.

   Çözüm: eşleşen parçaya değil, İÇİNDE BULUNDUĞU CÜMLEYE bakılır ve
   cümlede olumsuzluk varsa eleme yapılır. Türkçede bu ölçülebilir bir
   şeydir: olumsuzluk ekle taşınır (-mez, -maz, -amaz, -emez) ya da
   "değil" ile kurulur. İngilizcede "not", "never", "no" aranır. */
function cumle_al(string $g, string $par): string {
    $i = mb_strpos($g, $par);
    if ($i === false) return $par;
    $bas = $i; $son = $i + mb_strlen($par);
    /* Cümle sınırı: nokta, soru, ünlem ya da metnin ucu. */
    for ($k = $i; $k > 0 && $i - $k < 260; $k--) {
        $c = mb_substr($g, $k - 1, 1);
        if ($c === '.' || $c === '!' || $c === '?') { $bas = $k; break; }
        $bas = $k - 1;
    }
    for ($k = $son; $k < mb_strlen($g) && $k - $son < 260; $k++) {
        $c = mb_substr($g, $k, 1);
        if ($c === '.' || $c === '!' || $c === '?') { $son = $k + 1; break; }
        $son = $k + 1;
    }
    return trim(mb_substr($g, $bas, $son - $bas));
}
$olumsuz = '/\b(gerekmez|gerekmiyor|bağlanamaz|reddedemez|istenmez|alınmaz|yapılmaz|uygulanmaz|kullanılmaz|silinmez|silinemez|kaldırılmaz|çıkarılmaz|değildir|değil|yoktur|asla)\b|\b(not|never|no longer|cannot|may not|must not|is not|are not)\b/iu';

$toplamBayrak = 0;
foreach ($denetim as $no => $d) {
    $bulundu = [];
    foreach ($metin as $ad => $g) {
        if (preg_match($d['kalip'], $g, $m)) {
            $par = trim(mb_substr($m[0], 0, 90));
            $cumle = cumle_al($g, $par);
            $atla = (preg_match($olumsuz, $cumle) === 1);
            foreach ($d['ele'] as $e) { if (preg_match($e, $cumle)) { $atla = true; break; } }
            if (!$atla) $bulundu[] = $ad . ': "' . mb_substr($cumle, 0, 120) . '"';
        }
    }
    $toplamBayrak += count($bulundu);
    den('İlke ' . $no . ' (' . $d['ad'] . ') ile çelişen cümle yok',
        count($bulundu) === 0, implode(' | ', array_slice($bulundu, 0, 2)));
}
olc('yedi ilke x ' . count($metin) . ' sayfa metni tarandı, ' . $toplamBayrak . ' bayrak');

/* Ölçülmeyenler adıyla yazılır: bu bölüm metin arar, davranış değil. */
echo "  NOT    Bu bölüm METİN arar, DAVRANIŞ değil. İlke 1'in gerçekten\n";
echo "  NOT    tutulduğunu ödeme ucu olmadığı gösterir (sir-kapi.php);\n";
echo "  NOT    ilke 5'i dokum-kapi.php, ilke 3'ü hakem-akis.php ölçer.\n";
echo "  NOT    Burada ölçülen tek şey: sayfalar ilkenin tersini SÖYLÜYOR mu.\n";

echo "\n" . str_repeat('-', 40) . "\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
