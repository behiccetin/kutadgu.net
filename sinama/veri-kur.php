<?php
/* Sınama verisi üretici. Depoya girmez.
   Kullanım: php veri-kur.php <veri-dizini> <calisma-sayisi> <webroot> */
declare(strict_types=1);

$dizin = $argv[1] ?? '/home/claude/ktest-data';
$adet  = (int)($argv[2] ?? 60);
$kok   = $argv[3] ?? '/home/claude/kg/ktest';

@mkdir($dizin, 0755, true);
@mkdir($kok . '/photo/yazi', 0755, true);
@mkdir($kok . '/dosya/hakem', 0755, true);

mt_srand(20260808);

$yillar = ['2023', '2024', '2025', '2026'];
$alanlar = ['muhendislik', 'sosyal', 'saglik', 'temel', 'beseri'];

/* ---------------------------------------------------------------------
   FORD KODLARI (çoğul 'alanlar').

   ÖLÇÜLEN KUSUR — 14 Ağustos 2026. Bu üretici yalnızca TEKİL 'alan'
   yazıyordu; o alan eski kısa koddur (sag, fen, sos...). Oysa sistemin
   sınıflandırması ÇOĞUL 'alanlar' dizisindedir. Sonuç: sınama verisinde
   hiçbir çalışmanın FORD kodu yoktu, ana sayfadaki alan satırı boş
   çıkıyordu ve alan-arama-kapi.php 13 ölçümü sessizce ATLIYORDU —
   92 yerine 79 ölçüyor, ama yine de "KALDI: 0" diyordu.

   Bu, sistemde defalarca yakalanan kusur sınıfının sınama tarafındaki
   eşidir: ölçmediği şeyi ölçmüş sayan bir kapı. Kodlar burada
   al_dallar()'dan OKUNUR, elle yazılmaz: tek kaynak sınıflandırmanın
   kendisidir, listesi büyüdüğünde bu dosyanın değişmesi gerekmez.

   Dağılım bilerek üç durumu birden kurar:
     - aynı ana alanda İKİ dal (çalışma bir kez sayılmalı),
     - İKİ FARKLI ana alan (çalışma iki satırda da görünmeli),
     - tek dal (olağan hâl).
   --------------------------------------------------------------------- */
require_once $kok . '/ortak.php';
require_once $kok . '/k/alanlar.php';
$fordAna = [];
foreach (array_keys(al_dallar(true)) as $__kod) {
    foreach (al_anaLar((string)$__kod) as $__a) $fordAna[$__a][] = (string)$__kod;
}
ksort($fordAna);
$fordAnaKod = array_keys($fordAna);
if ($fordAnaKod === []) { fwrite(STDERR, "al_dallar() bos dondu; siniflandirma okunamadi\n"); exit(1); }
$ford = function (int $i) use ($fordAna, $fordAnaKod): array {
    $a  = $fordAnaKod[$i % count($fordAnaKod)];
    $ks = $fordAna[$a];
    $out = [$ks[$i % count($ks)]];
    if ($i % 3 === 1) $out[] = $ks[($i * 7 + 3) % count($ks)];          /* aynı ana alanda ikinci dal */
    if ($i % 4 === 2) {                                                  /* ikinci bir ana alan */
        $b  = $fordAnaKod[($i + 1) % count($fordAnaKod)];
        $out[] = $fordAna[$b][($i * 5) % count($fordAna[$b])];
    }
    return array_values(array_unique($out));
};
$adlar = ['Behiç Çetin', 'Zeynep Aydın', 'Murat Kayalar', 'Ayşe Demir', 'Ibrahim Al-Rashid',
          'Gökhan Kalağan', 'Çiğdem Öztürk', 'Elif Yalçın', 'Kenan Ergün', 'Selma Tunç'];

/* Küçük ama gerçek bir PNG ve bir PDF: paket ölçümü boş dosyayla yapılmasın. */
function png_yaz(string $yol, int $tohum): void {
    /* Gurultulu goruntu: gercek bir fotograf gibi sikismasin, boylece
       paket olcumu iyimser cikmasin. */
    $g = 700; $y = 500;
    $im = imagecreatetruecolor($g, $y);
    mt_srand($tohum * 7919);
    for ($x = 0; $x < $g; $x++) {
        for ($v = 0; $v < $y; $v++) {
            imagesetpixel($im, $x, $v, imagecolorallocate($im, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
        }
    }
    imagepng($im, $yol, 6);
    imagedestroy($im);
}
function pdf_yaz(string $yol, int $tohum, int $sayfa = 6): void {
    /* Elle kurulmuş en yalın PDF: sınama için boyut ve biçim yeterli. */
    $govde = "%PDF-1.4\n";
    $nesne = [];
    $metin = str_repeat("Kutadgu sinama belgesi " . $tohum . ". ", 40);
    $nesne[] = "<< /Type /Catalog /Pages 2 0 R >>";
    $kids = [];
    for ($i = 0; $i < $sayfa; $i++) $kids[] = (4 + $i * 2) . ' 0 R';
    $nesne[] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count $sayfa >>";
    $nesne[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
    for ($i = 0; $i < $sayfa; $i++) {
        $ic = "BT /F1 11 Tf 40 760 Td (" . $metin . " sayfa " . ($i + 1) . ") Tj ET";
        $nesne[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R >> >> /Contents " . (5 + $i * 2) . " 0 R >>";
        $nesne[] = "<< /Length " . strlen($ic) . " >>\nstream\n" . $ic . "\nendstream";
    }
    $konum = [];
    foreach ($nesne as $i => $n) {
        $konum[] = strlen($govde);
        $govde .= ($i + 1) . " 0 obj\n" . $n . "\nendobj\n";
    }
    $xref = strlen($govde);
    $govde .= "xref\n0 " . (count($nesne) + 1) . "\n0000000000 65535 f \n";
    foreach ($konum as $k) $govde .= sprintf("%010d 00000 n \n", $k);
    $govde .= "trailer\n<< /Size " . (count($nesne) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
    file_put_contents($yol, $govde);
}

$yazilar = [];
for ($i = 1; $i <= $adet; $i++) {
    $yil = $yillar[$i % count($yillar)];
    $no  = str_pad((string)$i, 5, '0', STR_PAD_LEFT);
    $tamga = 'KTG-' . $yil . '-' . $no . '-' . ($i % 10);
    $yazar = $adlar[$i % count($adlar)];
    $hakemli = ($i % 4) !== 0;

    /* Gövde: gerçekçi bir uzunluk, içinde iki görsel */
    $g1 = substr(hash('sha256', 'g1' . $i), 0, 14) . '.png';
    $g2 = substr(hash('sha256', 'g2' . $i), 0, 14) . '.png';
    png_yaz($kok . '/photo/yazi/' . $g1, $i);
    if ($i % 3 === 0) png_yaz($kok . '/photo/yazi/' . $g2, $i * 3);

    $paragraf = '';
    for ($p = 0; $p < 14; $p++) {
        $paragraf .= '<p>' . str_repeat('Bu bir sınama metnidir ve arşiv dökümünün ağırlığını gerçeğe yakın ölçmek için yazılmıştır. ', 6)
                   . 'Bölüm ' . ($p + 1) . '.</p>' . "\n";
        if ($p === 3) $paragraf .= '<figure><img src="/photo/yazi/' . $g1 . '" alt="Çizim ' . $i . '"></figure>' . "\n";
        if ($p === 9 && $i % 3 === 0) $paragraf .= '<figure><img src="/photo/yazi/' . $g2 . '" alt="Çizelge ' . $i . '"></figure>' . "\n";
    }

    $hakemler = [];
    if ($hakemli) {
        $n = 2 + ($i % 3);
        for ($h = 0; $h < $n; $h++) {
            $hAd = $adlar[($i + $h + 3) % count($adlar)];
            $dosya = '';
            if ($h === 0 && $i % 2 === 0) {
                $fad = substr(hash('sha256', 'd' . $i), 0, 18) . '.pdf';
                pdf_yaz($kok . '/dosya/hakem/' . $fad, $i, 40);
                $dosya = '/dosya/hakem/' . $fad;
            }
            $rapor = str_repeat('Hakem raporu metni; bulgular, yöntem ve sınırlılıklar üzerine ayrıntılı değerlendirme. ', 18);
            $hakemler[] = [
                'ad' => $hAd, 'karar' => ($h === 1 && $i % 7 === 0) ? 'ret' : 'kabul',
                'rapor' => $rapor, 'tarih' => $yil . '-0' . (($i % 8) + 1) . '-12T10:00:00+03:00',
                'dosya' => $dosya,
                'sifat' => ['alan'], 'yetkinlik' => 'Alanında doktora ve yayın.',
                'profil' => ['unvan' => 'Dr.', 'kurum' => 'Örnek Üniversitesi',
                             'orcid' => '0000-0002-0000-000' . ($h % 10), 'eposta' => 'gizli' . $h . '@ornek.org'],
                'raporlar' => [
                    ['karar' => 'buyuk', 'rapor' => substr($rapor, 0, 400), 'tarih' => $yil . '-0' . (($i % 8) + 1) . '-01T10:00:00+03:00', 'dosya' => $dosya],
                    ['karar' => 'kabul', 'rapor' => $rapor, 'tarih' => $yil . '-0' . (($i % 8) + 1) . '-12T10:00:00+03:00', 'dosya' => ''],
                ],
                'endeks' => ['sci'], 'endeks_anket' => ['q' => 'q1'],
                'atayan' => ['tur' => 'editor', 'ad' => 'Behiç Çetin', 'tarih' => $yil . '-01-05T09:00:00+03:00'],
            ];
        }
    }

    $y = [
        'id' => 'y' . $no,
        'bcid' => $tamga,
        'eski_kod' => $i <= 2 ? ['bc.00000' . $i] : [],
        'slug' => 'sinama-calismasi-' . $no,
        'tur' => $hakemli ? 'hakemli' : 'yazi',
        'tarih' => $yil . '-0' . (($i % 9) + 1) . '-15T12:00:00+03:00',
        'alan' => $alanlar[$i % count($alanlar)],
        'alanlar' => $ford($i),
        'dil' => $i % 5 === 0 ? 'en' : 'tr',
        'baslik' => 'Sınama çalışması ' . $i . ': ölçüm, yöntem ve sınırlılıklar',
        'baslik_en' => 'Test work ' . $i . ': measurement, method and limitations',
        'ozet' => str_repeat('Bu çalışmanın özeti sınama için üretilmiştir. ', 8),
        'ozet_en' => str_repeat('This abstract was produced for testing. ', 8),
        'anahtar' => 'ölçüm, yöntem, arşiv',
        'anahtar_en' => 'measurement, method, archive',
        'metin' => $paragraf,
        'metin_en' => $i % 5 === 0 ? $paragraf : '',
        'kaynakca' => str_repeat("Yazar, A. (2024). Bir kaynak. Dergi, 1(1), 1-10.\n", 25),
        'yazar' => $yazar,
        'yazar_bilgi' => ['unvan' => 'Dr.', 'ad' => $yazar, 'kurum' => 'Örnek Üniversitesi',
                          'orcid' => '0000-0001-0000-000' . ($i % 10), 'eposta' => 'gizli@ornek.org'],
        'yazar_liste' => $i % 3 === 0 ? [['unvan' => '', 'ad' => $adlar[($i + 1) % count($adlar)], 'kurum' => 'İkinci Kurum', 'orcid' => '']] : [],
        'hakemler' => $hakemler,
        'kayitlar' => $i % 11 === 0 ? [['tur' => 'duzeltme', 'tarih' => $yil . '-10-01T10:00:00+03:00',
                                        'metin' => 'Çizelge 2 düzeltildi.', 'karar_veren' => 'Behiç Çetin']] : [],
        'oylamalar' => $i % 9 === 0 ? [[
            'kod' => 'OY-' . $no, 'tur' => 'rapor', 'hedef_ad' => 'Rapor 1', 'acan_ad' => 'Behiç Çetin',
            'gerekce' => 'Raporun niteliği tartışıldı.', 'tarih' => $yil . '-11-01T10:00:00+03:00',
            'oylar' => [
                ['ad' => 'Behiç Çetin', 'karar' => 'evet', 'gerekce' => 'Gerekçe.', 'tarih' => $yil . '-11-02T10:00:00+03:00'],
                ['ad' => 'Zeynep Aydın', 'karar' => 'evet', 'gerekce' => 'Gerekçe.', 'tarih' => $yil . '-11-03T10:00:00+03:00'],
                ['ad' => 'Murat Kayalar', 'karar' => 'hayır', 'gerekce' => 'Gerekçe.', 'tarih' => $yil . '-11-04T10:00:00+03:00'],
            ],
        ]] : [],
        'serhler' => $i % 6 === 0 ? [['ad' => 'Okur Bir', 'kurum' => 'Kurum', 'ilgi' => 'yok',
                                      'tarih' => $yil . '-12-01T10:00:00+03:00',
                                      'metin' => str_repeat('Şerh metni. ', 30)]] : [],
    ];
    $yazilar[] = $y;
}

file_put_contents($dizin . '/yazilar.json', json_encode($yazilar, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "yazilar.json: " . count($yazilar) . " calisma, "
   . round(filesize($dizin . '/yazilar.json') / 1024) . " KB\n";
echo "gorsel: " . count(glob($kok . '/photo/yazi/*.png')) . "\n";
echo "belge:  " . count(glob($kok . '/dosya/hakem/*.pdf')) . "\n";
