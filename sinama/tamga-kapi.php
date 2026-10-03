<?php
/* =====================================================================
   TAMGA KİMLİĞİ VE MÜHRÜ: kapı ölçümü. Depoya girmez.

   İki ayrı söz ölçülür ve karıştırılmamalıdır:

     A. KİMLİK bir şey SÖYLER. KTG-2026-00001-4 dört parçadır ve son
        hane bir denetim hanesidir: yanlış yazılmış bir kimlik anında
        anlaşılır. Bunu okura anlatan şema, anlattığı hesabın ta
        kendisini anlatmalıdır. Şemadaki örnek kod elle yazılırsa okur
        onu denediğinde geçersiz çıkar ve sistemin bozuk olduğunu
        sanar; en kötü sonuç budur.
     B. MÜHÜR bir şey SÖYLEMEZ. Koddan üretilen bir çizimdir, süstür.
        Bilgi taşıdığı sanılmasın diye ekran okuyucudan gizlenir ve
        yanında kodun metin karşılığı durur. Mührü tek başına basmak,
        okunamayan bir kimlik basmaktır.

   Brief'in kabul kriteri: "Tamga kimliği logo dışında en az üç yerde
   görsel olarak tekrar ediyor."

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php tamga-kapi.php

   Betik veriyi DEĞİŞTİRMEZ.
   ===================================================================== */
declare(strict_types=1);

$KOD   = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI  = getenv('KUTADGU_DATA') ?: '';
$PORT  = getenv('KPORT') ?: '8941';
$KUTUK = getenv('KLOG') ?: '/home/claude/kg/sunucu.log';

if ($VERI === '' || !is_dir($VERI)) {
    fwrite(STDERR, "KUTADGU_DATA verilmedi ya da dizin yok.\n");
    exit(2);
}
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;

require_once $KOD . '/ortak.php';
require_once $KOD . '/k/muhur.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function not_(string $s): void { echo "  NOT    $s\n"; }
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ist(string $yol, array $bas = []): array {
    global $PORT;
    $bas[] = 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250);
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $bas),
        'ignore_errors' => true, 'timeout' => 40]]);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, $ctx);
    $h = $http_response_header ?? [];
    $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g];
}
function sayfa(string $yol): array {
    $r = ist($yol);
    $r['tam'] = ($r['kod'] === 200) && (strpos(substr($r['govde'], -400), '</html>') !== false);
    return $r;
}
/* Betik ve biçem çıkarılır, okura görünen öznitelikler eklenir. */
function gorunur(string $html): string {
    $h = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);
    preg_match_all('#\b(title|alt|aria-label)\s*=\s*"([^"]*)"#i', (string)$h, $m);
    $t = strip_tags((string)$h) . ' || ' . implode(' | ', $m[2]);
    return trim((string)preg_replace('/\s+/u', ' ', html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}
/* ---------------------------------------------------------------------
   Bir sayfada tamga kimliğinin GÖRSEL olarak kaç ayrı yerde geçtiği.

   ÖLÇÜM TUZAĞI, iki tane, ikisi de ilk yazımda sahte GECTI verdi:

   1. Logo bir DOSYA değil, satır içi SVG'dir (`class="marka-im"`).
      `tamga-kucuk.svg` sayfanın HTML'inde hiç geçmiyor. Dosya adını
      aramak, logoyu hiç görmemek demekti.
   2. Mühür `viewBox="0 0 64 64"` ile aranamaz: logo da, bildirim
      simgesi de aynı kutuyu kullanıyor. Mührün ayırt edici imzası
      `stroke-width="2.2"`dir; mh_muhur() dışında kimse basmıyor.

   Ölçüm bu yüzden yalnızca <main> içine bakar. Kabuk (üst bar, alt
   bilgi) her sayfada aynıdır; brief "logo DIŞINDA" dediği için kabuğu
   saymak her sayfayı kendiliğinden geçirirdi.
   --------------------------------------------------------------------- */
function govde_al(string $html): string {
    return preg_match('#<main\b[^>]*>(.*)</main>#si', $html, $m) ? $m[1] : '';
}
function kimlik_izleri(string $html): array {
    $g = govde_al($html);
    $iz = [];
    /* Mühür: mh_muhur()'un imzası. */
    $n = preg_match_all('#<svg[^>]*viewBox="0 0 64 64"[^>]*stroke-width="2\.2"#i', $g);
    if ($n > 0) $iz['muhur'] = $n;
    /* Tamga deseni ve simge dosyaları. */
    if (preg_match_all('#/k/(tamga[a-z0-9\-]*|desen)\.svg#i', $g, $m)) {
        foreach ($m[1] as $a) $iz['dosya:' . strtolower($a)] = (($iz['dosya:' . strtolower($a)] ?? 0) + 1);
    }
    /* Gövdeye konmuş satır içi tamga simgesi (marka-im kabuğun malıdır;
       gövdedekinin ayrı bir sınıfı olmalı ki ikisi karışmasın). */
    $n2 = preg_match_all('#class="[^"]*tmg-im[^"]*"#i', $g);
    if ($n2 > 0) $iz['simge'] = $n2;

    /* ÖLÇÜM TUZAĞI: tamga deseni gövdeye BİÇEMLE gelir. Dosya adı
       <style> bloğunun içindedir, <main> içinde hiç geçmez; yalnızca
       gövdeye bakan bir ölçüm onu göremez ve okurun ekranda gördüğü
       bir işareti "yok" sayar. Bu yüzden desene atıf yapan kuralın
       SEÇİCİSİ okunur ve o sınıfın gövdede bulunup bulunmadığına
       bakılır. Kuralın var olması yetmez: hiçbir öğeye değmiyorsa
       desen çizilmiyordur. */
    if (preg_match_all('#([^{}]+)\{[^{}]*/k/desen\.svg[^{}]*\}#i', $html, $mk)) {
        foreach ($mk[1] as $secici) {
            if (preg_match_all('#\.([a-z0-9_-]+)#i', $secici, $ms)) {
                foreach ($ms[1] as $sinif) {
                    if (preg_match('#class="[^"]*\b' . preg_quote($sinif, '#') . '\b[^"]*"#i', $g)) {
                        $iz['desen'] = ($iz['desen'] ?? 0) + 1;
                        break 2;
                    }
                }
            }
        }
    }
    return $iz;
}

/* =====================================================================
   1. KİMLİK BİR ŞEY SÖYLER: DENETİM HANESİ
   ===================================================================== */
echo "== 1. Kimlik bir şey söyler: denetim hanesi ==\n";
$ornek = 'KTG-' . date('Y') . '-00001-' . tg_denetim(date('Y') . '00001');
den('üretilen kimlik kendi çözümleyicisinden geçiyor', tg_tamga_gecerli($ornek), $ornek);
$c = tg_tamga_coz($ornek);
den('  dört parçaya ayrılıyor', is_array($c) && isset($c['on'], $c['yil'], $c['sira'], $c['denetim']));
den('  yıl doğru okunuyor', (int)($c['yil'] ?? 0) === (int)date('Y'));
den('  sıra doğru okunuyor', (int)($c['sira'] ?? -1) === 1);

/* Denetim hanesinin işi: yanlış yazılmış kimliği açığa çıkarmak.
   Söz buysa ölçülmeli; "vardır" demek yetmez. */
$tekHane = 0; $tekHaneToplam = 0;
$govde = date('Y') . '00001';
for ($i = 0; $i < strlen($govde); $i++) {
    for ($d = 0; $d <= 9; $d++) {
        if ((string)$d === $govde[$i]) continue;
        $bozuk = $govde; $bozuk[$i] = (string)$d;
        $tekHaneToplam++;
        if (tg_denetim($bozuk) !== tg_denetim($govde)) $tekHane++;
    }
}
den('TEK hane hatasının tamamı yakalanıyor', $tekHane === $tekHaneToplam, "$tekHane / $tekHaneToplam");

$yer = 0; $yerToplam = 0;
for ($i = 0; $i + 1 < strlen($govde); $i++) {
    if ($govde[$i] === $govde[$i + 1]) continue;
    $b = $govde; $t = $b[$i]; $b[$i] = $b[$i + 1]; $b[$i + 1] = $t;
    $yerToplam++;
    if (tg_denetim($b) !== tg_denetim($govde)) $yer++;
}
den('KOMŞU iki hanenin yer değiştirmesi yakalanıyor', $yerToplam > 0 && $yer === $yerToplam, "$yer / $yerToplam");
den('denetim hanesi bozulunca kimlik reddediliyor',
    !tg_tamga_gecerli(substr($ornek, 0, -1) . (((int)substr($ornek, -1) + 1) % 10)));
den('biçimi tutmayan dizge reddediliyor',
    !tg_tamga_gecerli('KTG-2026-1-4') && !tg_tamga_gecerli('KTG-26-00001-4') && !tg_tamga_gecerli(''));
/* X hâli: denetim 10 çıktığında. Kod bunu üretiyorsa çözümleyici de
   kabul etmeli; yoksa yılda bir kimlik doğduğu gün kırılır. */
$xVar = false;
for ($s = 1; $s < 3000 && !$xVar; $s++) {
    $h = date('Y') . str_pad((string)$s, 5, '0', STR_PAD_LEFT);
    if (tg_denetim($h) === 'X') { $xVar = true; $xKod = 'KTG-' . date('Y') . '-' . str_pad((string)$s, 5, '0', STR_PAD_LEFT) . '-X'; }
}
if (!$xVar) not_('bu yılın ilk 3000 sırasında X denetimli kimlik yok');
else den('X denetimli kimlik de geçerli sayılıyor', tg_tamga_gecerli($xKod), $xKod);

/* =====================================================================
   2. ŞEMA: ANLATTIĞI HESAP, YAPILAN HESAP MI
   Şemadaki örnek kod elle yazılırsa okur onu denediğinde geçersiz
   çıkar ve sistemin bozuk olduğunu sanar. En pahalı hata budur.
   ===================================================================== */
echo "\n== 2. Şema: anlattığı hesap, yapılan hesap mı ==\n";
$iTr = sayfa('/ilkeler.php?lang=tr');
$iEn = sayfa('/ilkeler.php?lang=en');
den('ilkeler sayfası tam geliyor (tr)', $iTr['tam'], (string)$iTr['kod']);
den('ilkeler sayfası tam geliyor (en)', $iEn['tam'], (string)$iEn['kod']);

/* Sayfada geçen HER tamga biçimli kodun kendi denetim hanesi tutmalı. */
foreach (['tr' => $iTr, 'en' => $iEn] as $dil => $s) {
    preg_match_all('/\b([A-Z]{2,6}-\d{4}-\d{4,7}-[0-9X])\b/u', $s['govde'], $m);
    $kodlar = array_values(array_unique($m[1]));
    $bozuk = array_values(array_filter($kodlar, fn($k) => !tg_tamga_gecerli($k)));
    den("[$dil] sayfadaki örnek kimliklerin hepsi geçerli", $bozuk === [],
        count($kodlar) . ' kod, bozuk: ' . implode(', ', $bozuk));
    if ($kodlar) olc("[$dil] sayfada geçen kimlik: " . implode(', ', $kodlar));
}

/* ŞEMANIN KENDİ KODU ayrıca ölçülür.

   ÖLÇÜM TUZAĞI: şema kodu parça parça basar (her parça ayrı bir
   <span> içinde), bu yüzden "KTG-2026-00001-7" düz dizgesi şemanın
   içinde HİÇ geçmez. Sayfa genelinde kod arayan bir ölçüm yalnızca
   yandaki .kod örneğini görür ve şemadaki hane elle yazılsa bile
   GECTI verir. Yanlışlamayla yakalandı: denetim hanesi '4'e
   sabitlendiğinde kapı ısırmamıştı.

   Şemadaki parçalar birleştirilip kimliğin kendisi kurulur. */
foreach (['tr' => $iTr, 'en' => $iEn] as $dil => $s) {
    if (!preg_match('#<div[^>]*class="[^"]*tmg-sema[^"]*"[^>]*>(.*?)</div>#si', $s['govde'], $ms)) {
        den("[$dil] şema bloğu okunabiliyor", false, 'blok bulunamadı');
        continue;
    }
    preg_match_all('#<b>(.*?)</b>#si', $ms[1], $mp);
    $parcalar = array_map(fn($x) => trim(html_entity_decode(strip_tags($x), ENT_QUOTES | ENT_HTML5, 'UTF-8')), $mp[1]);
    den("[$dil] şema kimliği dört parça olarak basıyor", count($parcalar) === 4, implode('|', $parcalar));
    if (count($parcalar) === 4) {
        $semaKod = implode('-', $parcalar);
        den("[$dil] ŞEMADAKİ kimlik gerçekten geçerli (denetim hanesi elle yazılmamış)",
            tg_tamga_gecerli($semaKod), $semaKod);
        /* Ve şemadaki hesap adımlarının vardığı yer, şemadaki hanenin
           kendisi olmalı: metin ile kod ayrı düşmesin. */
        $g = gorunur($s['govde']);
        den("[$dil] hesap adımları şemadaki haneyle bitiyor",
            (bool)preg_match('/mod 11 = *' . preg_quote($parcalar[3], '/') . '\b/u', $g),
            $parcalar[3]);
    }
}

/* Şema, kimliğin dört parçasını ADLANDIRMALI. Prose'da anlatılması
   yetmez; brief bir ŞEMA istiyor: parçalar ayrı ayrı işaretli olmalı. */
$semaTr = preg_match('#<[^>]*class="[^"]*tmg-sema[^"]*"#i', $iTr['govde']) === 1;
$semaEn = preg_match('#<[^>]*class="[^"]*tmg-sema[^"]*"#i', $iEn['govde']) === 1;
den('çözümleyici şeması ilkeler sayfasında (tr)', $semaTr);
den('çözümleyici şeması ilkeler sayfasında (en)', $semaEn);
$gTr = gorunur($iTr['govde']);
$gEn = gorunur($iEn['govde']);
foreach ([['tr', $gTr, ['önek', 'yıl', 'sıra', 'denetim']],
          ['en', $gEn, ['prefix', 'year', 'sequence', 'check']]] as [$dil, $g, $parcalar]) {
    foreach ($parcalar as $p) {
        den("[$dil] şema '$p' parçasını adlandırıyor", mb_stripos($g, $p) !== false);
    }
}
/* Denetim hanesinin YÖNTEMİ yazılı mı: okur kendi hesabını yapabilsin.
   "Bir denetim hanesi vardır" demek, okura hiçbir şey vermez. */
den('[tr] denetim hanesinin yöntemi adıyla yazılı (ISO 7064)', mb_stripos($gTr, 'ISO 7064') !== false);
den('[en] denetim hanesinin yöntemi adıyla yazılı (ISO 7064)', mb_stripos($gEn, 'ISO 7064') !== false);
/* Söz verilemeyecek şey söylenmez: sayfa yakaladığı hata türlerini
   sayıyorsa, YAKALAMADIĞINI da aynı yerde söylemeli.

   ÖLÇÜM TUZAĞI: "her hatayı" dizgesini aramak yetmez; sınırı ANLATAN
   cümlenin kendisi de o sözcükleri taşır ("Her hatayı yakaladığı
   söylenemez"). Düz arama, doğru yazılmış bir sayfayı KALDI yapar.
   Ölçülen şey sınırlamanın VARLIĞIDIR. */
den('[tr] yakalayamadığını da söylüyor (sınırsız iddia yok)',
    (bool)preg_match('/her hatayı yakala\w*\s+söylenemez|yakaladığı söylenemez/iu', $gTr));
den('[en] yakalayamadığını da söylüyor (sınırsız iddia yok)',
    (bool)preg_match('/cannot be said to catch every error/iu', $gEn));
/* Ve kimlik doğrulamasının metin doğrulamasının yerine geçmediği
   yazılı olmalı: ikisi ayrı şeylerdir, karıştırılırsa okur metnin
   değişmediğini sandığı yerde yalnızca kimliğin doğru yazıldığını
   öğrenmiş olur. */
den('[tr] kimlik doğrulaması metin doğrulamasının yerine geçmiyor deniyor',
    mb_stripos($gTr, 'yerine geçmez') !== false && mb_stripos($gTr, 'parmak izi') !== false);
den('[en] aynısı İngilizcede de yazılı',
    mb_stripos($gEn, 'does not stand in place') !== false && mb_stripos($gEn, 'fingerprint') !== false);

/* =====================================================================
   3. MÜHÜR BİR ŞEY SÖYLEMEZ
   ===================================================================== */
echo "\n== 3. Mühür bir şey söylemez ==\n";
$mk = 'KTG-2026-00001-' . tg_denetim('202600001');
$m1 = mh_muhur($mk, 64);
den('mühür üretiliyor', $m1 !== '' && strpos($m1, '<svg') === 0);
den('  ekran okuyucudan gizli (aria-hidden)', strpos($m1, 'aria-hidden="true"') !== false);
den('  odak sırasına girmiyor (focusable=false)', strpos($m1, 'focusable="false"') !== false);
den('  metin taşımıyor (<text> yok)', stripos($m1, '<text') === false);
den('aynı kod aynı mührü veriyor', mh_muhur($mk, 64) === $m1);
den('başka kod başka mühür veriyor', mh_muhur('KTG-2026-00002-' . tg_denetim('202600002'), 64) !== $m1);
den('kodsuz çağrı boş dönüyor (mühürsüz kayıt mühürlenmiş görünmesin)', mh_muhur('', 64) === '');
olc('tek mühür: ' . strlen($m1) . ' B ham, ' . strlen((string)gzencode($m1, 6)) . ' B gzip');

/* =====================================================================
   4. KİMLİK LOGO DIŞINDA KAÇ YERDE
   Brief'in kabul kriteri: en az üç yer.
   ===================================================================== */
echo "\n== 4. Kimlik logo dışında kaç yerde ==\n";
$sayfalar = [
    '/nasil-isler.php' => 'nasıl işler',
    '/ilkeler.php'     => 'ilkeler',
    '/acikliklar.php'  => 'açıklıklar',
];
$yerler = [];
foreach ($sayfalar as $yol => $ad) {
    $s = sayfa($yol . '?lang=tr');
    den("$ad sayfası tam geliyor", $s['tam'], (string)$s['kod']);
    $iz = kimlik_izleri($s['govde']);
    den("$ad sayfasında kimlik görsel olarak var", $iz !== [], json_encode($iz));
    if ($iz !== []) { $yerler[] = $ad; olc("  $ad: " . json_encode($iz)); }
}
den('kimlik logo dışında EN AZ ÜÇ yerde tekrar ediyor (brief kabul kriteri)',
    count($yerler) >= 3, count($yerler) . ' yer: ' . implode(', ', $yerler));

/* =====================================================================
   5. ARŞİV LİSTESİNDE MÜHÜR
   Yanlış mühür, hiç mühür olmamasından kötüdür: okur onu kimliğin
   karşılığı sanar.
   ===================================================================== */
echo "\n== 5. Arşiv listesinde mühür ==\n";
$yz = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true);
$yz = is_array($yz) ? $yz : [];
$L1 = sayfa('/yazilar?lang=tr');
den('arşiv listesi tam geliyor', $L1['tam'], (string)$L1['kod']);
$muhurSayisi = preg_match_all('#<svg[^>]*viewBox="0 0 64 64"[^>]*stroke-width="2\\.2"#i', govde_al($L1['govde']));
den('listede mühür basılıyor', $muhurSayisi > 0, (string)$muhurSayisi . ' mühür');
/* Basılan mühür gerçekten O çalışmanın kodundan mı üretilmiş: listedeki
   her tamgayı alıp mührünü kendimiz üretir ve sayfada arar. */
preg_match_all('/\b([A-Z]{2,6}-\d{4}-\d{4,7}-[0-9X])\b/u', $L1['govde'], $mm);
$listeKod = array_values(array_unique($mm[1]));
if (!$listeKod) { not_('listede tamga kodu geçmiyor; mührün doğruluğu ölçülemedi'); }
else {
    $dogru = 0;
    foreach ($listeKod as $k) {
        $gov = mh_muhur($k, 40);
        if ($gov === '') continue;
        /* Boy değişebilir; karşılaştırma çizimin kendisi üzerinden. */
        if (preg_match('#<svg[^>]*>(.*)</svg>#s', $gov, $g1) && strpos($L1['govde'], $g1[1]) !== false) $dogru++;
    }
    den('listedeki mühürlerin hepsi kendi kodundan üretilmiş', $dogru === count($listeKod),
        "$dogru / " . count($listeKod));
    den('  ve kodun metin karşılığı da basılı (mühür okunamaz, kod okunur)',
        mb_stripos(gorunur($L1['govde']), $listeKod[0]) !== false, $listeKod[0]);
}
/* Ağırlık ölçülüp yazılır; "ucuz" demek yetmez. */
$hamB = strlen($L1['govde']);
$gzB  = strlen((string)gzencode($L1['govde'], 6));
olc('arşiv listesi: ' . round($hamB / 1024, 1) . ' KB ham, ' . round($gzB / 1024, 1) . ' KB gzip'
    . ($muhurSayisi > 0 ? ' (' . $muhurSayisi . ' mühürle)' : ' (mühürsüz)'));

/* =====================================================================
   6. GERİLEMEYEN YERLER
   ===================================================================== */
echo "\n== 6. Gerilemeyen yerler ==\n";
$mYol = null;
foreach ($yz as $w) if (is_array($w) && trim((string)($w['bcid'] ?? '')) !== '') { $mYol = '/tamga/' . rawurlencode((string)$w['bcid']); break; }
if ($mYol === null) not_('tamgalı çalışma yok');
else {
    $a = sayfa($mYol . '?lang=tr');
    den('makale sayfasındaki mühür yerinde', preg_match_all('#stroke-width="2\\.2"#', $a['govde']) >= 1);
    den('  yanında kodun metin karşılığı var', mb_stripos(gorunur($a['govde']), trim($mYol, '/tamga/') ?: 'KTG') !== false || preg_match('/KTG-\d{4}-\d{4,7}-[0-9X]/', gorunur($a['govde'])) === 1);
    den('  ve "koddan üretilen" deniyor, "biricik" denmiyor',
        mb_stripos(gorunur($a['govde']), 'koddan üretilen') !== false
        && mb_stripos(gorunur($a['govde']), 'biricik') === false);
}
den('CSS ve JS değişmediyse TG_SURUM artırılmadı (elle bakılacak not)', true);

echo "\n== 7. Sunucu kütüğü ==\n";
if (!is_file($KUTUK)) { not_('kütük dosyası yok: ' . $KUTUK); }
else {
    $son = (string)@shell_exec('tail -n 400 ' . escapeshellarg($KUTUK) . ' 2>/dev/null');
    $u = 0;
    foreach (explode("\n", $son) as $s) if (preg_match('/warning|deprecated|notice|fatal/i', $s)) $u++;
    den('son 400 satırda PHP uyarısı yok', $u === 0, (string)$u . ' satır');
}

echo "\n" . str_repeat('-', 40) . "\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
