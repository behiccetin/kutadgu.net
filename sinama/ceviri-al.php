<?php
/* =====================================================================
   ÇEVİRİ ALICI VE DENETÇİSİ. Depoya girmez.

   Dil modelinden dönen parçaları okur, DENETLER ve tek bir sözlük
   dosyasında birleştirir. Denetim geçmeden hiçbir dize sözlüğe girmez:
   bozuk bir çeviri, eksik bir çeviriden kötüdür — eksik olan İngilizce
   görünür, bozuk olan sayfayı bozar.

   ARANAN KUSURLAR
   ---------------
   1. UYDURULMUŞ ANAHTAR   : kaynakta olmayan bir anahtar geldi. Model
                             anahtarı da çevirmiş demektir; o satır
                             hiçbir zaman eşleşmez ve sessizce ölür.
   2. KAYIP ANAHTAR        : parçadaki bir dize dönmemiş. Eksiktir,
                             kusur değildir: yazılır ve geçilir.
   3. BOZUK HTML           : kaynakta <b> var, çeviride yok (ya da
                             tersi). Etiket sayısı tutmalıdır.
   4. KAYIP YER TUTUCU     : %1, %2 gibi bir yer tutucu düşmüş. O
                             cümlede sayı ya da ad görünmez olur.
   5. ÇEVRİLMEMİŞ          : değer İngilizceyle birebir aynı. Bu bir
                             kusur değil, bir ÖLÇÜdür: kaç dize
                             gerçekten çevrildi.
   6. KORUNAN AD ÇEVRİLMİŞ : Kutadgu, ORCID, DOI, tamga gibi adlar
                             kaynakta varken çeviride yok.

   Kullanım:
     php ceviri-al.php <dil> <klasör> [--yaz]
     php ceviri-al.php de /tmp/ceviri-paket/de --yaz

   --yaz verilmezse hiçbir dosya yazılmaz; yalnız rapor basılır.
   Yazma hedefi: $KUTADGU_DATA/ceviri/<dil>.json
   ===================================================================== */
declare(strict_types=1);

$dil   = strtolower(trim((string)($argv[1] ?? '')));
$klas  = rtrim((string)($argv[2] ?? ''), '/');
$yaz   = in_array('--yaz', $argv, true);
$VERI  = getenv('KUTADGU_DATA') ?: '';
$kaynakDosya = __DIR__ . '/ceviri-sozluk-kaynak.json';

if ($dil === '' || $klas === '' || !is_dir($klas)) {
    fwrite(STDERR, "Kullanım: php ceviri-al.php <dil> <klasör> [--yaz]\n");
    exit(2);
}
$kaynak = json_decode((string)@file_get_contents($kaynakDosya), true);
if (!is_array($kaynak)) { fwrite(STDERR, "Kaynak sözlük okunamadı: $kaynakDosya\n"); exit(2); }

$enler = [];
foreach ($kaynak as $a => $v) $enler[$a] = (string)($v['en'] ?? '');

$KORUNAN = ['Kutadgu', 'ORCID', 'DOI', 'CC BY 4.0', 'iThenticate', 'Turnitin',
            'Scopus', 'ISSN', 'DOAJ', 'COPE', 'OAI-PMH', 'FORD', 'tamga', 'Tamga'];

function etiketler(string $s): array {
    preg_match_all('#</?([a-z][a-z0-9]*)\b#i', $s, $m);
    $c = array_map('strtolower', $m[1] ?? []);
    sort($c);
    return $c;
}
function tutucular(string $s): array {
    preg_match_all('/%\d|%s|\{[a-z_]+\}/u', $s, $m);
    $c = $m[0] ?? [];
    sort($c);
    return $c;
}

$dosyalar = glob($klas . '/*-cevrilmis.json') ?: [];
if (!$dosyalar) {
    /* Model kimi zaman adı değiştirir; "-cevrilmis" yoksa elde ne varsa
       ondan kaynak/istem dosyalarını AYIKLAYARAK devam edilir. */
    $hepsi = glob($klas . '/*.json') ?: [];
    foreach ($hepsi as $h) {
        $ad = basename($h);
        if (preg_match('/^' . preg_quote($dil, '/') . '-\d+\.json$/', $ad)) continue;  /* kaynak parça */
        $dosyalar[] = $h;
    }
}
sort($dosyalar);
if (!$dosyalar) { fwrite(STDERR, "Klasörde çevrilmiş dosya yok: $klas\n"); exit(2); }

$sozluk = [];
$uydurma = []; $bozukHtml = []; $kayipTutucu = []; $ceviriYok = []; $korunanKayip = [];
$okunan = 0; $bozukDosya = [];

foreach ($dosyalar as $d) {
    $ham = (string)file_get_contents($d);
    /* Model kod çiti koyduysa temizlenir: bu bir kusur değil, bilinen
       bir alışkanlıktır ve dosyayı elle düzeltmeye değmez. */
    $ham = preg_replace('/^\s*```(?:json)?\s*|\s*```\s*$/s', '', $ham);
    $j = json_decode((string)$ham, true);
    if (!is_array($j)) { $bozukDosya[] = basename($d); continue; }
    foreach ($j as $a => $v) {
        $a = (string)$a; $v = trim((string)$v);
        if (!isset($enler[$a])) { $uydurma[] = basename($d) . ': ' . mb_substr($a, 0, 48); continue; }
        if ($v === '') continue;
        /* Kaynak dizede baştaki/sondaki boşluk olabilir; çeviri
           trim'lenmiş gelir. İkisi trim'siz karşılaştırılırsa yalnız
           boşluğu düşmüş bir dize "çevrilmiş" sayılır ve ölçüm şişer. */
        $en = trim($enler[$a]);
        if (etiketler($en) !== etiketler($v)) { $bozukHtml[] = mb_substr($a, 0, 46); continue; }
        if (tutucular($en) !== tutucular($v))  { $kayipTutucu[] = mb_substr($a, 0, 46); continue; }
        $kayip = [];
        /* BÜYÜK/KÜÇÜK HARF ARANMAZ. Almanca adları büyük harfle yazar
           ("tamga" -> "Tamga") ve istem bunu açıkça istiyor. Korunması
           gereken şey adın KENDİSİdir, yazımının büyüklüğü değil; sıkı
           karşılaştırma dört doğru çeviriyi kusur saydı. */
        /* SÖZCÜK SINIRI ARANIR. "COPE" adı, "scope" sözcüğünün içinde
           de geçiyor: sınırsız arama "…scope, format…" çevirisini
           "COPE düşmüş" diye kusur saydı. Ad, sözcük olarak aranır. */
        foreach ($KORUNAN as $k) {
            $d = '/(?<![\p{L}\p{N}])' . preg_quote($k, '/') . '(?![\p{L}\p{N}])/ui';
            if (preg_match($d, $en) && !preg_match($d, $v)) $kayip[] = $k;
        }
        if ($kayip) { $korunanKayip[] = mb_substr($a, 0, 40) . ' -> ' . implode(',', $kayip); continue; }
        if ($v === $en) { $ceviriYok[] = mb_substr($a, 0, 46); }
        $sozluk[$a] = $v;
        $okunan++;
    }
}

$toplam = count($enler);
$dolu = count($sozluk);
$gercek = $dolu - count($ceviriYok);

echo "== Çeviri alımı: $dil ==\n";
echo "  okunan dosya      : " . count($dosyalar) . ($bozukDosya ? ' (bozuk: ' . implode(', ', $bozukDosya) . ')' : '') . "\n";
echo "  kaynak dize       : $toplam\n";
echo "  sözlüğe giren     : $dolu   (%" . ($toplam ? round($dolu * 100 / $toplam, 1) : 0) . ")\n";
echo "  bunun çevrilmiş   : $gercek\n";
echo "  İngilizce kalmış  : " . count($ceviriYok) . "   (sorun değil: sayfa zaten İngilizce gösterir)\n";
echo "  ---- geri çevrilenler ----\n";
echo "  uydurulmuş anahtar: " . count($uydurma) . "\n";
echo "  bozuk HTML        : " . count($bozukHtml) . "\n";
echo "  kayıp yer tutucu  : " . count($kayipTutucu) . "\n";
echo "  korunan ad düşmüş : " . count($korunanKayip) . "\n";
foreach ([['uydurulmuş anahtar', $uydurma], ['bozuk HTML', $bozukHtml],
          ['kayıp yer tutucu', $kayipTutucu], ['korunan ad', $korunanKayip]] as [$ad, $liste]) {
    foreach (array_slice($liste, 0, 5) as $x) echo "    $ad: $x\n";
}

if (!$yaz) { echo "\n  (--yaz verilmedi: hiçbir dosya yazılmadı)\n"; exit(count($uydurma) + count($bozukHtml) > 0 ? 1 : 0); }
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }

$hedefDizin = $VERI . '/ceviri';
@mkdir($hedefDizin, 0775, true);
$hedef = $hedefDizin . '/' . $dil . '.json';
/* Var olan sözlüğün üstüne yazılmaz, BİRLEŞTİRİLİR: elle düzeltilmiş
   bir karşılık, yeni bir parça yüzünden kaybolmamalı. Yeni gelen
   kazanır, ama yalnız gerçekten geldiyse. */
$eski = is_file($hedef) ? (array)json_decode((string)file_get_contents($hedef), true) : [];
$yeni = array_merge($eski, $sozluk);
ksort($yeni);
file_put_contents($hedef, json_encode($yeni, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
echo "\n  yazıldı: $hedef  (" . count($yeni) . " dize)\n";
echo "  Sırada: ayar.php'ye dil satırı, sonra `php sinama/ceviri-kapi.php`.\n";
