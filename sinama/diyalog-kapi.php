<?php
/* =====================================================================
   Yazar ile hakem arasındaki diyalog: kapı ölçümü. Depoya girmez.

   Ölçülen söz şudur: kanal ancak rapor yazılmışken ve karar revizyon
   iken açıktır, sıralıdır, sayılıdır, kayıtlıdır ve silinmez. Bu betik
   sözün her parçasını ayrı ayrı yoklar; ezberden değil, koda ve ağdan
   dönen yanıta bakarak.

   Kullanım:
     KUTADGU_DATA=/.../veri KPORT=8941 php diyalog-kapi.php

   Çevre değişkenleri:
     KUTADGU_DATA  veri dizini (zorunlu)
     KPORT         sınama sunucusunun kapısı (varsayılan 8941)
     KWEB          site kökü (varsayılan /home/claude/kg/ktest)
     KLOG          sunucu kütüğü (varsayılan /home/claude/kg/sunucu.log)

   Betik veriyi DEĞİŞTİRİR: kendi sınama çalışmalarını ekler, ileti
   yazar, metin günceller. Başta yedek alınır ve kapanışta (hata ya da
   ölümcül hata hâlinde de) geri yüklenir.
   ===================================================================== */
declare(strict_types=1);

$VERI = (string)(getenv('KUTADGU_DATA') ?: '');
$PORT = (string)(getenv('KPORT') ?: '8941');
$KOK  = (string)(getenv('KWEB') ?: '/home/claude/kg/ktest');
$KLOG = (string)(getenv('KLOG') ?: '/home/claude/kg/sunucu.log');
if ($VERI === '' || !is_dir($VERI)) { echo "KUTADGU_DATA yok ya da dizin degil\n"; exit(2); }
if (!is_file($KOK . '/ortak.php')) { echo "KWEB yanlis: " . $KOK . "\n"; exit(2); }

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* ---- Ağ yardımcıları ----
   dokum-kapi.php'deki ist()/bas() ikilisinin aynısı; tek farkı gövde
   gönderebilmesi ve ikinci bir kapıya (ayar sunucusu) sorabilmesi. */
function ist(string $yol, string $metod = 'GET', ?array $veri = null, int $kapi = 0): array {
    global $PORT;
    $k = $kapi > 0 ? (string)$kapi : $PORT;
    $bas = [];
    $govde = null;
    if ($veri !== null) {
        $govde = (string)json_encode($veri, JSON_UNESCAPED_UNICODE);
        $bas[] = 'Content-Type: application/json';
        $bas[] = 'Content-Length: ' . strlen($govde);
    }
    $ctx = stream_context_create(['http' => [
        'method' => $metod, 'header' => implode("\r\n", $bas), 'content' => $govde,
        'ignore_errors' => true, 'timeout' => 30, 'follow_location' => 0]]);
    $g = @file_get_contents('http://127.0.0.1:' . $k . $yol, false, $ctx);
    $h = $http_response_header ?? [];
    $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g];
}
function bas(array $h, string $ad): string {
    foreach ($h as $s) if (stripos($s, $ad . ':') === 0) return trim(substr($s, strlen($ad) + 1));
    return '';
}
/* JSON ucuna POST: hem kodu hem çözülmüş yanıtı verir. */
function gonder(string $yol, array $veri, int $kapi = 0): array {
    $r = ist($yol, 'POST', $veri, $kapi);
    $j = json_decode($r['govde'], true);
    return ['kod' => $r['kod'], 'j' => is_array($j) ? $j : [], 'govde' => $r['govde']];
}
/* Uç, isteği kabul etti mi. */
function olur(array $r): bool { return $r['kod'] === 200 && !empty($r['j']['ok']); }
function hata_metni(array $r): string { return (string)($r['kod'] . ' ' . ($r['j']['hata'] ?? mb_substr($r['govde'], 0, 60))); }

/* ---- Veri okuma ---- */
function veri(): array {
    global $VERI;
    $y = json_decode((string)file_get_contents($VERI . '/yazilar.json'), true);
    return is_array($y) ? $y : [];
}
function calisma(string $id): array {
    foreach (veri() as $e) if (is_array($e) && (string)($e['id'] ?? '') === $id) return $e;
    return [];
}
function hakemi(string $id, string $ad): array {
    foreach ((calisma($id)['hakemler'] ?? []) as $h) {
        if (is_array($h) && (string)($h['ad'] ?? '') === $ad) return $h;
    }
    return [];
}
function diy_say(string $id, string $ad): int { return count(hakemi($id, $ad)['diyalog'] ?? []); }

/* ---- Sınama iletisi: tam olarak n karakter uzunluğunda ----
   Asgari uzunluk sınırı tam sınırda ölçülecek. Uç önce etiketleri atıp
   trim ettiği için son karakter boşluk olmamalı; olursa noktaya
   çevrilir, uzunluk yine n kalır. */
function ileti(int $n, string $tohum = 'Sinama iletisi olarak yazilmis bir aciklama metni. '): string {
    $s = mb_substr(str_repeat($tohum, (int)ceil($n / max(1, mb_strlen($tohum))) + 2), 0, $n, 'UTF-8');
    if (mb_substr($s, -1, 1, 'UTF-8') === ' ') $s = mb_substr($s, 0, $n - 1, 'UTF-8') . '.';
    return $s;
}

/* =====================================================================
   YEDEK VE GERİ YÜKLEME
   Ölçüm veriyi değiştirir. Yedek en başta alınır ve kapanışta her
   durumda (olağan çıkış, özel durum, ölümcül hata) geri yüklenir;
   yoksa bir sonraki ölçüm bu ölçümün artıklarıyla koşardı.
   ===================================================================== */
$YEDEK = [];
foreach (['yazilar.json', 'hiz-sinir.json', 'eposta.log'] as $ad) {
    $YEDEK[$ad] = is_file($VERI . '/' . $ad) ? (string)file_get_contents($VERI . '/' . $ad) : null;
}
$GECICI = [];          /* kapanışta silinecek dizinler */
$AYAR_PID = 0;         /* kapanışta öldürülecek ikinci sunucu */

register_shutdown_function(function () use ($VERI, &$YEDEK, &$GECICI, &$AYAR_PID) {
    foreach ($YEDEK as $ad => $ic) {
        if ($ic === null) { @unlink($VERI . '/' . $ad); continue; }
        @file_put_contents($VERI . '/' . $ad, $ic);
    }
    if ($AYAR_PID > 0) { @exec('kill ' . (int)$AYAR_PID . ' 2>/dev/null'); }
    foreach ($GECICI as $d) {
        if (is_dir($d)) @exec('rm -rf ' . escapeshellarg($d));
        elseif (is_file($d)) @unlink($d);
    }
});

/* Kütüğün ölçüm öncesi uzunluğu: sonunda yalnızca bizim istekelerimizin
   ürettiği satırlara bakılsın. */
$LOG_BAS = is_file($KLOG) ? (int)filesize($KLOG) : 0;

/* =====================================================================
   SINAMA ÇALIŞMALARI
   Hazır veride ne hakem erişim anahtarı ne de revizyon kararı var;
   diyalog kanalı hiç açılmıyor. Bu yüzden ölçüm kendi çalışmalarını
   kurar: A çalışmasında kanal açık olabilecek hakemler, B çalışmasında
   kanalı kapalı olanlar ve başka bir çalışmaya yazma denemesinin hedefi.
   ===================================================================== */
const YT = 'aaaaaaaaaaaaaaaaaaaaaaaa';   /* A çalışmasının yazar anahtarı */
const YT_B = 'ffffffffffffffffffffffff';
const HT = [                              /* hakem anahtarları */
    'Adem Bir'    => 'bbbbbbbbbbbbbbbbbbbbbbbb',
    'Bade Iki'    => 'cccccccccccccccccccccccc',
    'Cem Uc'      => 'dddddddddddddddddddddddd',
    'Dilek Dort'  => 'eeeeeeeeeeeeeeeeeeeeeeee',
    'Emre Bes'    => '1111111111111111111111a1',
    'Fatma Alti'  => '2222222222222222222222a2',
    'Gonca Yedi'  => '3333333333333333333333a3',
    'Emel Sekiz'  => '4444444444444444444444a4',
    'Ferit Dokuz' => '5555555555555555555555a5',
    'Hakan Sekiz' => '6666666666666666666666a6',
];
/* Hakemin adresi ve şifresi anahtarından TÜREMEZ: açık hakemlikte profil
   e-postası yayımlanır, adres anahtarı içerseydi "anahtar sızmıyor"
   ölçümü kendi kurduğu veriye takılır ve yanlış alarm verirdi. */
function h_anahtar(string $ad): string { return strtolower((string)preg_replace('/[^a-z]/i', '', $ad)); }
function h_posta(string $ad): string { return 'hakem' . h_anahtar($ad) . '@ornek.org'; }
function h_sifre(string $ad): string { return 'sifre' . h_anahtar($ad); }

function hakem_kaydi(string $ad, string $karar, string $rapor, bool $kapali = false): array {
    $tok = HT[$ad];
    return ['ad' => $ad, 'karar' => $karar, 'rapor' => $rapor,
        'tarih' => '2026-01-01T10:00:00+03:00', 'dosya' => '',
        'token' => $tok, 'eposta_hash' => hash('sha256', h_posta($ad) . '|hakem'),
        'sifre' => h_sifre($ad), 'eposta_acik' => h_posta($ad),
        'kapali' => $kapali, 'davet_durum' => 'kabul',
        'sifat' => ['konu'], 'yetkinlik' => 'Alanında doktora ve yayın.',
        'profil' => ['unvan' => 'Dr.', 'kurum' => 'Örnek Üniversitesi',
                     'orcid' => '0000-0002-0000-0009', 'eposta' => h_posta($ad)],
        'raporlar' => $rapor === '' ? [] : [['karar' => $karar, 'rapor' => $rapor,
                     'tarih' => '2026-01-01T10:00:00+03:00', 'dosya' => '']],
        'atayan' => ['tur' => 'editor', 'ad' => 'Behiç Çetin', 'tarih' => '2025-12-01T09:00:00+03:00']];
}

function sinama_verisi(string $temel): array {
    $y = json_decode($temel, true); if (!is_array($y)) $y = [];
    $rapor = str_repeat('Hakem raporu; yöntem ve bulgular üzerine değerlendirme. ', 6);
    $A = ['id' => 'dtestA', 'bcid' => 'KTG-2026-90001-1', 'slug' => 'diyalog-sinama-a',
        'tur' => 'hakemli', 'tarih' => '2026-01-05T12:00:00+03:00', 'alan' => 'sosyal', 'dil' => 'tr',
        'baslik' => 'Diyalog sınaması A', 'ozet' => 'Sınama özeti.',
        'metin' => '<p>Sınama metni, birinci sürüm.</p>', 'kaynakca' => 'Kaynak listesi.',
        'anahtar' => 'sınama', 'yazar' => 'Dr. Sınama Yazar',
        'yazar_liste' => [['unvan' => 'Dr.', 'ad' => 'Sınama Yazar', 'kurum' => 'Örnek Üniversitesi',
                           'orcid' => '0000-0001-0000-0009', 'scopus' => '']],
        'yazar_bilgi' => ['unvan' => 'Dr.', 'ad' => 'Sınama Yazar', 'kurum' => 'Örnek Üniversitesi',
                          'orcid' => '0000-0001-0000-0009', 'eposta' => 'yazara@ornek.org'],
        'yazar_erisim' => ['token' => YT, 'eposta_hash' => hash('sha256', 'yazara@ornek.org|yazar'),
                           'sifre' => 'sifreA', 'eposta_acik' => 'yazara@ornek.org'],
        'hakemler' => [
            hakem_kaydi('Adem Bir', 'buyuk', $rapor),      /* sıra ve tur ölçümü */
            hakem_kaydi('Bade Iki', 'kucuk', $rapor),      /* asgari uzunluk ölçümü */
            hakem_kaydi('Cem Uc', '', ''),                 /* rapor yok: kanal kapalı */
            hakem_kaydi('Dilek Dort', 'buyuk', $rapor, true), /* kapalı: kanal kapalı */
            hakem_kaydi('Emre Bes', 'buyuk', $rapor),      /* kayıt biçimi ve sürüm */
            hakem_kaydi('Fatma Alti', 'buyuk', $rapor),    /* kalıcılık */
            hakem_kaydi('Gonca Yedi', 'kucuk', $rapor),    /* yetki ölçümleri */
            hakem_kaydi('Hakan Sekiz', 'buyuk', $rapor),   /* kararın uçtan verilmesi */
        ],
        'kayitlar' => [], 'oylamalar' => [], 'serhler' => []];
    $B = ['id' => 'dtestB', 'bcid' => 'KTG-2026-90002-1', 'slug' => 'diyalog-sinama-b',
        'tur' => 'hakemli', 'tarih' => '2026-01-06T12:00:00+03:00', 'alan' => 'sosyal', 'dil' => 'tr',
        'baslik' => 'Diyalog sınaması B', 'ozet' => 'Sınama özeti.',
        'metin' => '<p>Sınama metni B.</p>', 'kaynakca' => 'Kaynak listesi.',
        'anahtar' => 'sınama', 'yazar' => 'Dr. Başka Yazar', 'yazar_liste' => [],
        'yazar_bilgi' => ['unvan' => 'Dr.', 'ad' => 'Başka Yazar', 'kurum' => 'Örnek Üniversitesi',
                          'orcid' => '0000-0001-0000-0008', 'eposta' => 'yazarb@ornek.org'],
        'yazar_erisim' => ['token' => YT_B, 'eposta_hash' => hash('sha256', 'yazarb@ornek.org|yazar'),
                           'sifre' => 'sifreB', 'eposta_acik' => 'yazarb@ornek.org'],
        'hakemler' => [
            hakem_kaydi('Emel Sekiz', 'kabul', $rapor),    /* karar kabul: kanal kapalı */
            hakem_kaydi('Ferit Dokuz', 'ret', $rapor),     /* karar ret: kanal kapalı */
        ],
        'kayitlar' => [], 'oylamalar' => [], 'serhler' => []];
    $y[] = $A; $y[] = $B;
    return $y;
}

/* Hız sınırı ölçümün kendisini kesmesin: uçlar bir IP için 40 istek
   sayıyor, ölçüm bundan çok istek yolluyor. Sayaç veri dosyasında
   tutulduğu için bölüm başlarında sıfırlanır; yedeği alındı. */
function sayac_sifirla(): void { global $VERI; @file_put_contents($VERI . '/hiz-sinir.json', '{}'); }

$temelYazilar = (string)$YEDEK['yazilar.json'];
file_put_contents($VERI . '/yazilar.json',
    (string)json_encode(sinama_verisi($temelYazilar), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
sayac_sifirla();
@file_put_contents($VERI . '/eposta.log', '');

/* Sunucu ayakta mı: değilse ölçümün tamamı yanıltıcı olur. */
$sag = ist('/api/yazilar');
if ($sag['kod'] !== 200) {
    echo "Sunucu 127.0.0.1:$PORT yanit vermiyor (kod " . $sag['kod'] . "). Once sunucuyu baslatin.\n";
    exit(2);
}

require_once $KOK . '/ortak.php';

echo "== 1. Kanal ne zaman acik: tg_diyalog_sira() ==\n";
/* Kanalın kapısı tek bir işlevde: rapor yazılmamışsa, karar revizyon
   değilse ya da hakem süreci kapanmışsa kanal hiç açılmaz. */
$r = str_repeat('rapor ', 5);
den('rapor yokken kanal kapali', tg_diyalog_sira(['rapor' => '', 'karar' => 'buyuk']) === '',
    tg_diyalog_sira(['rapor' => '', 'karar' => 'buyuk']));
den('rapor bosluktan ibaretken kanal kapali', tg_diyalog_sira(['rapor' => "  \n ", 'karar' => 'buyuk']) === '');
den('rapor var + karar yokken kanal kapali', tg_diyalog_sira(['rapor' => $r, 'karar' => '']) === '',
    tg_diyalog_sira(['rapor' => $r, 'karar' => '']));
den('rapor var + buyuk revizyon: kanal ACIK, sira yazarda',
    tg_diyalog_sira(['rapor' => $r, 'karar' => 'buyuk']) === 'yazar');
den('rapor var + kucuk revizyon: kanal ACIK, sira yazarda',
    tg_diyalog_sira(['rapor' => $r, 'karar' => 'kucuk']) === 'yazar');
den('karar kabul: kanal kapali', tg_diyalog_sira(['rapor' => $r, 'karar' => 'kabul']) === '',
    tg_diyalog_sira(['rapor' => $r, 'karar' => 'kabul']));
den('karar ret: kanal kapali', tg_diyalog_sira(['rapor' => $r, 'karar' => 'ret']) === '',
    tg_diyalog_sira(['rapor' => $r, 'karar' => 'ret']));
den('hakem sureci kapaliysa kanal kapali',
    tg_diyalog_sira(['rapor' => $r, 'karar' => 'buyuk', 'kapali' => true]) === '');
/* Sıranın kimde olduğu son iletinin yönünden okunur. */
$bir = ['rapor' => $r, 'karar' => 'buyuk', 'diyalog' => [['yon' => 'yazar', 'metin' => 'a']]];
den('son ileti yazarinsa sira hakemde', tg_diyalog_sira($bir) === 'hakem', tg_diyalog_sira($bir));
$iki = ['rapor' => $r, 'karar' => 'buyuk', 'diyalog' => [
    ['yon' => 'yazar', 'metin' => 'a'], ['yon' => 'hakem', 'metin' => 'b']]];
den('son ileti hakeminse sira yine yazarda (tur bitmemisse)', tg_diyalog_sira($iki) === 'yazar');
/* Bozuk kayıt sayılmaz: yönü tanınmayan ya da metni boş satır düşer. */
$bozuk = ['rapor' => $r, 'karar' => 'buyuk', 'diyalog' => [
    ['yon' => 'editor', 'metin' => 'x'], ['yon' => 'yazar', 'metin' => '   '],
    ['yon' => 'yazar', 'metin' => 'gecerli'], 'dizi degil']];
den('tg_diyalog bozuk kaydi suzuyor', count(tg_diyalog($bozuk)) === 1, (string)count(tg_diyalog($bozuk)));
den('tg_diyalog_tur yalnizca yazar iletisini sayiyor', tg_diyalog_tur($iki) === 1, (string)tg_diyalog_tur($iki));
den('diyalog alani hic yokken tur sifir', tg_diyalog_tur(['rapor' => $r]) === 0);

echo "\n== 2. Sira kurali: yazar iki kere ust uste yazamaz ==\n";
sayac_sifirla();
$yz = ['t' => YT, 'mail' => 'yazara@ornek.org', 'sifre' => 'sifreA'];
$hk = fn(string $ad) => ['t' => HT[$ad], 'mail' => h_posta($ad), 'sifre' => h_sifre($ad)];

$a1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Adem Bir', 'metin' => ileti(200)]);
den('yazarin ilk notu kabul edildi', olur($a1), hata_metni($a1));
den('  ve sira hakeme gecti', ($a1['j']['sira'] ?? '') === 'hakem', (string)($a1['j']['sira'] ?? ''));
$sayi = diy_say('dtestA', 'Adem Bir');
$a2 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Adem Bir', 'metin' => ileti(200, 'Ikinci not denemesi. ')]);
den('yazarin ust uste ikinci notu REDDEDILDI', !olur($a2) && $a2['kod'] === 409, hata_metni($a2));
den('  ve gerekcesi sira kuralidir',
    mb_strpos((string)($a2['j']['hata'] ?? ''), 'Sıra sizde değil') !== false, (string)($a2['j']['hata'] ?? ''));
den('  ve kayda hicbir sey eklenmedi', diy_say('dtestA', 'Adem Bir') === $sayi,
    diy_say('dtestA', 'Adem Bir') . ' / ' . $sayi);

$b1 = gonder('/api/hakem-yazar-yanit', $hk('Adem Bir') + ['metin' => ileti(200, 'Hakemin karsiligi. ')]);
den('hakemin karsiligi kabul edildi', olur($b1), hata_metni($b1));
den('  ve sira yazara dondu', ($b1['j']['sira'] ?? '') === 'yazar', (string)($b1['j']['sira'] ?? ''));
$sayi = diy_say('dtestA', 'Adem Bir');
$b2 = gonder('/api/hakem-yazar-yanit', $hk('Adem Bir') + ['metin' => ileti(200, 'Hakemin ikinci karsiligi. ')]);
den('hakemin ust uste ikinci karsiligi REDDEDILDI', !olur($b2) && $b2['kod'] === 409, hata_metni($b2));
den('  ve kayda hicbir sey eklenmedi', diy_say('dtestA', 'Adem Bir') === $sayi);

/* Kanalın hiç açılmadığı hâller uçtan da ölçülür: yalnızca işlev değil,
   uç da reddetmelidir. */
$c1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Cem Uc', 'metin' => ileti(200)]);
den('rapor yazmamis hakeme not yazilamaz', !olur($c1) && $c1['kod'] === 409, hata_metni($c1));
den('  ve gerekcesi raporun yoklugudur',
    mb_strpos((string)($c1['j']['hata'] ?? ''), 'rapor yazmadı') !== false, (string)($c1['j']['hata'] ?? ''));
$d1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Dilek Dort', 'metin' => ileti(200)]);
den('sureci kapanmis hakeme not yazilamaz', !olur($d1) && $d1['kod'] === 409, hata_metni($d1));
$yzB = ['t' => YT_B, 'mail' => 'yazarb@ornek.org', 'sifre' => 'sifreB'];
$e1 = gonder('/api/yazar-hakem-yanit', $yzB + ['hakem' => 'Emel Sekiz', 'metin' => ileti(200)]);
den('karari kabul olan hakeme not yazilamaz', !olur($e1) && $e1['kod'] === 409, hata_metni($e1));
den('  ve yazar kurul oylamasina yonlendiriliyor',
    mb_strpos((string)($e1['j']['hata'] ?? ''), 'kurul oylaması') !== false, (string)($e1['j']['hata'] ?? ''));
$f1 = gonder('/api/yazar-hakem-yanit', $yzB + ['hakem' => 'Ferit Dokuz', 'metin' => ileti(200)]);
den('karari ret olan hakeme not yazilamaz', !olur($f1) && $f1['kod'] === 409, hata_metni($f1));
$g1 = gonder('/api/hakem-yazar-yanit', $hk('Emel Sekiz') + ['metin' => ileti(200)]);
den('kanal kapaliyken hakem de yazamaz', !olur($g1) && $g1['kod'] === 409, hata_metni($g1));
$h1 = gonder('/api/hakem-yazar-yanit', $hk('Gonca Yedi') + ['metin' => ileti(200)]);
den('yanitlanacak yazar notu yokken hakem yazamaz', !olur($h1) && $h1['kod'] === 409, hata_metni($h1));

/* Kararın kendisi elle değil uçtan verilirse ne oluyor: kural "buyuk ya
   da kucuk revizyonda kanal açık" diyor. Kucuk kararını /hakem-gonder
   ile veren bir hakemde kanal gerçekten açık kalıyor mu? */
$kk = gonder('/api/hakem-gonder', $hk('Hakan Sekiz') + ['karar' => 'kucuk',
    'rapor' => str_repeat('Kucuk revizyon isteyen rapor metni. ', 8)]);
den('hakem kucuk revizyon karari verebildi', olur($kk), hata_metni($kk));
$hkS = hakemi('dtestA', 'Hakan Sekiz');
den('  karar kayda kucuk olarak gecti', (string)($hkS['karar'] ?? '') === 'kucuk', (string)($hkS['karar'] ?? ''));
den('  KUCUK REVIZYONDA KANAL ACIK KALIYOR', tg_diyalog_sira($hkS) === 'yazar',
    'sira "' . tg_diyalog_sira($hkS) . '", kapali=' . var_export(!empty($hkS['kapali']), true));
$kk2 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Hakan Sekiz', 'metin' => ileti(200, 'Kucuk revizyon notu. ')]);
den('  ve yazar bu hakeme not yazabiliyor', olur($kk2), hata_metni($kk2));

echo "\n== 3. Tur ust siniri: diyalog_tur_ust ==\n";
sayac_sifirla();
$ust = (int)tg_ayar('diyalog_tur_ust', 2);
den('ayar okunuyor ve varsayilan iki', $ust === 2, (string)$ust);
/* Adem Bir'de bir tur tamamlandı. Varsayılan sınır iki olduğu için
   ikinci tur açılabilmeli, üçüncüsü reddedilmeli. */
$t2 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Adem Bir', 'metin' => ileti(200, 'Ikinci tur notu. ')]);
den('ikinci tur acilabiliyor (sinir iki)', olur($t2), hata_metni($t2));
$t2h = gonder('/api/hakem-yazar-yanit', $hk('Adem Bir') + ['metin' => ileti(200, 'Ikinci tur karsiligi. ')]);
den('ikinci turun karsiligi yazilabiliyor', olur($t2h), hata_metni($t2h));
den('  ve sira artik kimsede degil (kanal kapandi)', ($t2h['j']['sira'] ?? 'x') === '',
    (string)($t2h['j']['sira'] ?? 'x'));
$sayi = diy_say('dtestA', 'Adem Bir');
$t3 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Adem Bir', 'metin' => ileti(200, 'Ucuncu tur notu. ')]);
den('UCUNCU tur reddedildi', !olur($t3) && $t3['kod'] === 409, hata_metni($t3));
den('  ve gerekcesi tur sinirdir',
    mb_strpos((string)($t3['j']['hata'] ?? ''), 'turlarınız doldu') !== false, (string)($t3['j']['hata'] ?? ''));
den('  ve kayit dort iletide kaldi', $sayi === 4 && diy_say('dtestA', 'Adem Bir') === 4, (string)$sayi);
den('tg_diyalog_tur iki turu sayiyor', tg_diyalog_tur(hakemi('dtestA', 'Adem Bir')) === 2,
    (string)tg_diyalog_tur(hakemi('dtestA', 'Adem Bir')));

echo "\n== 4. Asgari uzunluk: diyalog_asgari ==\n";
sayac_sifirla();
$asg = (int)tg_ayar('diyalog_asgari', 80);
den('ayar okunuyor ve varsayilan seksen', $asg === 80, (string)$asg);
$sayi = diy_say('dtestA', 'Bade Iki');
$k1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Bade Iki', 'metin' => ileti($asg - 1)]);
den('sinirin bir altindaki ileti reddedildi', !olur($k1) && $k1['kod'] === 400, hata_metni($k1));
den('  ve kayda eklenmedi', diy_say('dtestA', 'Bade Iki') === $sayi);
$k2 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Bade Iki', 'metin' => ileti($asg)]);
den('sinirin tam ustundeki ileti kabul edildi', olur($k2), hata_metni($k2));
/* Uzunluk karakterle ölçülmeli, baytla değil: Türkçe bir metinde bayt
   sayısı karakter sayısının iki katına yaklaşır. */
$cokBayt = ileti($asg, 'Çığırışığşüöç açıklaması. ');
den('  olcum karakter uzerinden (bayt degil)', mb_strlen($cokBayt, 'UTF-8') === $asg && strlen($cokBayt) > $asg,
    mb_strlen($cokBayt, 'UTF-8') . ' karakter / ' . strlen($cokBayt) . ' bayt');
/* Sıra hakemde: önce kısa ileti reddedilmeli, sonra tam sınırdaki
   ileti geçmeli. Ters sırada denenirse ikincisi sıra kuralına takılır
   ve uzunluk ölçümü hiç yapılmamış olur. */
$k4 = gonder('/api/hakem-yazar-yanit', $hk('Bade Iki') + ['metin' => ileti(5)]);
den('hakem tarafinda da kisa ileti reddedildi', !olur($k4) && $k4['kod'] === 400, hata_metni($k4));
$k3 = gonder('/api/hakem-yazar-yanit', $hk('Bade Iki') + ['metin' => $cokBayt]);
den('  cok baytli tam sinir ileti kabul edildi', olur($k3), hata_metni($k3));
$k5 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Bade Iki', 'metin' => '<b>' . ileti($asg - 1) . '</b>']);
den('etiketle sisirilmis kisa ileti de reddedildi', !olur($k5) && $k5['kod'] === 400, hata_metni($k5));

echo "\n== 5. Ayar gercekten baglayici mi: ikinci sunucu ==\n";
/* tg_ayar dosyayı bir kez okuyup bellekte tutar ve yolu site kökünde
   sabittir; ayarı değiştirmenin tek dürüst yolu, ayarı değiştirilmiş
   bir kök üzerinde ikinci bir sunucu koşturmaktır. Kök sabit bağla
   çoğaltılır (yer kaplamaz), yalnızca ayar.php yeniden yazılır. */
$aKok  = '/tmp/kdiyalog-kok-' . getmypid();
$aVeri = '/tmp/kdiyalog-veri-' . getmypid();
$aLog  = '/tmp/kdiyalog-sunucu-' . getmypid() . '.log';
$aKapi = (int)$PORT + 1;
$GECICI[] = $aKok; $GECICI[] = $aVeri; $GECICI[] = $aLog;
@exec('cp -al ' . escapeshellarg($KOK) . ' ' . escapeshellarg($aKok) . ' 2>/dev/null');
$ayarDizi = include $KOK . '/ayar.php';
$ayarDizi['diyalog_tur_ust'] = 1;
$ayarDizi['diyalog_asgari']  = 200;
@unlink($aKok . '/ayar.php');   /* sabit bağ kopsun: aslı hic degismesin */
file_put_contents($aKok . '/ayar.php', "<?php\nreturn " . var_export($ayarDizi, true) . ";\n");
/* İkinci sunucunun verisi: bozulmamış temel + sınama çalışmaları, üstüne
   Adem Bir icin bir tur onceden islenmis. Boylece sinir bir olunca
   ikinci turun acilamadigi tek istekle olculur. */
$aY = sinama_verisi($temelYazilar);
foreach ($aY as $ix => $e) {
    if ((string)($e['id'] ?? '') !== 'dtestA') continue;
    $aY[$ix]['hakemler'][0]['diyalog'] = [
        ['yon' => 'yazar', 'metin' => ileti(300), 'tarih' => '2026-02-01T10:00:00+03:00', 'surum' => 'x'],
        ['yon' => 'hakem', 'metin' => ileti(300), 'tarih' => '2026-02-02T10:00:00+03:00', 'surum' => 'x'],
    ];
}
@mkdir($aVeri, 0755, true);
file_put_contents($aVeri . '/yazilar.json',
    (string)json_encode($aY, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
file_put_contents($aVeri . '/hiz-sinir.json', '{}');
$komut = 'nohup env ' . 'KUTADGU_DATA=' . escapeshellarg($aVeri) . ' KTEST_DIR=' . escapeshellarg($aKok)
       . ' php -S 127.0.0.1:' . $aKapi . ' -t ' . escapeshellarg($aKok) . ' ' . escapeshellarg(__DIR__ . '/krouter.php')
       . ' > ' . escapeshellarg($aLog) . ' 2>&1 < /dev/null & echo $!';
$AYAR_PID = (int)shell_exec($komut);
$ayakta = false;
for ($i = 0; $i < 50; $i++) {
    usleep(200000);
    $s = @fsockopen('127.0.0.1', $aKapi, $en, $es, 1);
    if ($s) { fclose($s); $ayakta = true; break; }
}
den('ayar sunucusu ayakta', $ayakta, 'kapi ' . $aKapi);
if ($ayakta) {
    $s1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Adem Bir', 'metin' => ileti(300)], $aKapi);
    den('tur_ust=1 iken IKINCI tur acilamiyor', !olur($s1) && $s1['kod'] === 409, hata_metni($s1));
    den('  gerekcesi tur sinirdir',
        mb_strpos((string)($s1['j']['hata'] ?? ''), 'turlarınız doldu') !== false, (string)($s1['j']['hata'] ?? ''));
    $s2 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Bade Iki', 'metin' => ileti(100)], $aKapi);
    den('asgari=200 iken 100 karakterlik ileti reddediliyor', !olur($s2) && $s2['kod'] === 400, hata_metni($s2));
    den('  ve mesaj yeni siniri soyluyor',
        mb_strpos((string)($s2['j']['hata'] ?? ''), '200 karakter') !== false, (string)($s2['j']['hata'] ?? ''));
    $s3 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Bade Iki', 'metin' => ileti(200)], $aKapi);
    den('  ve tam 200 karakterlik ileti geciyor', olur($s3), hata_metni($s3));
    /* Sınır yalnızca uçta değil, hakem sayfasının da bildirdiği bir
       sayı olmalı; yoksa tarayıcı eski sınırla ölçer. */
    $s4 = gonder('/api/hakem-form', ['t' => HT['Bade Iki'], 'mail' => h_posta('Bade Iki'),
                                     'sifre' => h_sifre('Bade Iki')], $aKapi);
    den('hakem formu yeni asgariyi bildiriyor', (int)($s4['j']['diyalog_asgari'] ?? 0) === 200,
        (string)($s4['j']['diyalog_asgari'] ?? '-'));
    den('hakem formu yeni tur ustunu bildiriyor', (int)($s4['j']['diyalog_ust'] ?? 0) === 1,
        (string)($s4['j']['diyalog_ust'] ?? '-'));
}

echo "\n== 6. Uclarin yetki kapisi ==\n";
sayac_sifirla();
$u1 = gonder('/api/yazar-hakem-yanit', ['hakem' => 'Emre Bes', 'metin' => ileti(200)]);
den('anahtarsiz yazar cagrisi reddedildi', !olur($u1) && $u1['kod'] === 404, hata_metni($u1));
$u2 = gonder('/api/yazar-hakem-yanit',
    ['t' => YT, 'mail' => 'baskasi@ornek.org', 'sifre' => 'sifreA', 'hakem' => 'Emre Bes', 'metin' => ileti(200)]);
den('yanlis e-postali yazar cagrisi reddedildi', !olur($u2) && $u2['kod'] === 403, hata_metni($u2));
$u3 = gonder('/api/yazar-hakem-yanit',
    ['t' => YT, 'mail' => 'yazara@ornek.org', 'sifre' => 'yanlis', 'hakem' => 'Emre Bes', 'metin' => ileti(200)]);
den('yanlis sifreli yazar cagrisi reddedildi', !olur($u3) && $u3['kod'] === 403, hata_metni($u3));
/* Rol karışması: hakem anahtarı yazar ucunu, yazar anahtarı hakem ucunu
   açmamalı. Ikisi ayri defterde arandigi icin bulunamaz. */
$u4 = gonder('/api/yazar-hakem-yanit',
    ['t' => HT['Emre Bes'], 'mail' => h_posta('Emre Bes'), 'sifre' => h_sifre('Emre Bes'),
     'hakem' => 'Emre Bes', 'metin' => ileti(200)]);
den('hakem anahtariyla YAZAR ucu acilmiyor', !olur($u4) && $u4['kod'] === 404, hata_metni($u4));
$u5 = gonder('/api/hakem-yazar-yanit', ['t' => YT, 'mail' => 'yazara@ornek.org', 'sifre' => 'sifreA',
                                        'metin' => ileti(200)]);
den('yazar anahtariyla HAKEM ucu acilmiyor', !olur($u5) && $u5['kod'] === 404, hata_metni($u5));
$u6 = gonder('/api/hakem-yazar-yanit',
    ['t' => HT['Emre Bes'], 'mail' => 'baskasi@ornek.org', 'sifre' => h_sifre('Emre Bes'),
     'metin' => ileti(200)]);
den('yanlis e-postali hakem cagrisi reddedildi', !olur($u6) && $u6['kod'] === 403, hata_metni($u6));
$u7 = ist('/api/yazar-hakem-yanit', 'GET');
den('uc yalnizca POST kabul ediyor', $u7['kod'] !== 200 || strpos($u7['govde'], '"ok":true') === false,
    (string)$u7['kod']);
den('  ve yanit JSON olarak donuyor (sayfa degil)',
    stripos(bas($u7['basliklar'], 'Content-Type'), 'application/json') === 0,
    bas($u7['basliklar'], 'Content-Type'));

/* Baska bir calismanin diyalogu: A'nin yazari B'nin hakemine yazamaz. */
$oncekiB = diy_say('dtestB', 'Emel Sekiz') + diy_say('dtestB', 'Ferit Dokuz');
$u8 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Emel Sekiz', 'metin' => ileti(200)]);
den('A yazari B hakemine yazamiyor', !olur($u8) && $u8['kod'] === 404, hata_metni($u8));
den('  gerekcesi "bu calismada boyle bir hakem yok"',
    mb_strpos((string)($u8['j']['hata'] ?? ''), 'böyle bir hakem yok') !== false, (string)($u8['j']['hata'] ?? ''));
$u9 = gonder('/api/yazar-hakem-yanit', $yzB + ['hakem' => 'Emre Bes', 'metin' => ileti(200)]);
den('B yazari A hakemine yazamiyor', !olur($u9) && $u9['kod'] === 404, hata_metni($u9));
den('  ve B calismasinin kaydi hic buyumedi',
    diy_say('dtestB', 'Emel Sekiz') + diy_say('dtestB', 'Ferit Dokuz') === $oncekiB);
/* Hakem ucunda hedef, gonderilen kimlikten degil anahtardan cikarilir:
   govdeye baska bir calismanin kimligini koymak hicbir sey degistirmez. */
$w1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Gonca Yedi', 'metin' => ileti(200)]);
den('hazirlik: Gonca Yedi kanalinda sira hakemde', olur($w1), hata_metni($w1));
$oncekiA = diy_say('dtestA', 'Gonca Yedi');
$w2 = gonder('/api/hakem-yazar-yanit', $hk('Gonca Yedi') + ['metin' => ileti(200, 'Yonlendirme denemesi. '),
    'id' => 'dtestB', 'y' => 'diyalog-sinama-b', 'hakem' => 'Emel Sekiz']);
den('hakem ucu govdedeki yabanci kimlige aldirmadi', olur($w2), hata_metni($w2));
den('  yazi kendi kaydina dustu', diy_say('dtestA', 'Gonca Yedi') === $oncekiA + 1);
den('  B calismasi yine degismedi',
    diy_say('dtestB', 'Emel Sekiz') + diy_say('dtestB', 'Ferit Dokuz') === $oncekiB);

echo "\n== 7. Kayit bicimi ve surum ==\n";
sayac_sifirla();
$m1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Emre Bes', 'metin' => ileti(200, 'Birinci surum notu. ')]);
den('kayit yazildi', olur($m1), hata_metni($m1));
$kayitlar = hakemi('dtestA', 'Emre Bes')['diyalog'] ?? [];
$son = $kayitlar ? $kayitlar[count($kayitlar) - 1] : [];
den('kayitta yon var', isset($son['yon']) && in_array($son['yon'], ['yazar', 'hakem'], true));
den('kayitta metin var', isset($son['metin']) && trim((string)$son['metin']) !== '');
den('kayitta tarih var ve cozulebiliyor', isset($son['tarih']) && strtotime((string)$son['tarih']) !== false,
    (string)($son['tarih'] ?? ''));
den('kayitta surum var', isset($son['surum']) && (string)$son['surum'] !== '');
/* Sürüm alanı yazının o andaki parmak izini göstermeli: metin sonradan
   değişirse yanıtın neye ilişkin yazıldığı kayıttan okunabilsin. */
$ozetIlk = tg_metin_ozeti(calisma('dtestA'));
den('surum, yazinin o anki parmak iziyle ayni', (string)$son['surum'] === $ozetIlk,
    (string)$son['surum'] . ' / ' . $ozetIlk);
/* Kişisel iletişim bilgisi kanalda hiç taşınmıyor: kayıtta dört alan var. */
den('kayitta bu dort alandan baskasi yok', array_keys($son) === ['yon', 'metin', 'tarih', 'surum'],
    implode(',', array_keys($son)));
den('kayitta e-posta, ip ya da anahtar alani yok',
    !array_intersect(array_keys($son), ['eposta', 'ip', 'token', 'mail', 'sifre']));

/* Yazar metnini düzeltip yeni bir not yazınca sürüm değişmeli, eskisi
   olduğu yerde kalmalı. */
$kay = gonder('/api/yazar-kaydet', $yz + ['baslik' => 'Diyalog sınaması A', 'ozet' => 'Sınama özeti.',
    'metin' => '<p>Sınama metni, ikinci sürüm.</p>', 'kaynakca' => 'Kaynak listesi.']);
den('yazar yeni surum yazabildi (revizyon acikken)', olur($kay), hata_metni($kay));
$ozetIki = tg_metin_ozeti(calisma('dtestA'));
den('  yazinin parmak izi degisti', $ozetIki !== $ozetIlk);
$m2 = gonder('/api/hakem-yazar-yanit', $hk('Emre Bes') + ['metin' => ileti(200, 'Ikinci surum karsiligi. ')]);
den('yeni surumden sonra yazilan kayit gecti', olur($m2), hata_metni($m2));
$kayitlar = hakemi('dtestA', 'Emre Bes')['diyalog'] ?? [];
den('  yeni kaydin surumu yeni parmak izi',
    (string)($kayitlar[count($kayitlar) - 1]['surum'] ?? '') === $ozetIki);
den('  eski kaydin surumu DEGISMEDI (kayit gecmisi bozulmuyor)',
    (string)($kayitlar[0]['surum'] ?? '') === $ozetIlk);

echo "\n== 8. Kalicilik: diyalog silinmiyor ==\n";
sayac_sifirla();
$p1 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Fatma Alti', 'metin' => ileti(200, 'Kalicilik notu. ')]);
den('hazirlik: yazar notu yazildi', olur($p1), hata_metni($p1));
$p2 = gonder('/api/hakem-yazar-yanit', $hk('Fatma Alti') + ['metin' => ileti(200, 'Kalicilik karsiligi. ')]);
den('hazirlik: hakem karsiligi yazildi', olur($p2), hata_metni($p2));
$oncekiF = diy_say('dtestA', 'Fatma Alti');
/* Hakem raporunu yeniden gönderdiğinde hakem kaydı yeniden yazılır;
   diyalog bu sirada silinmemeli. */
$p3 = gonder('/api/hakem-gonder', $hk('Fatma Alti') + ['karar' => 'buyuk',
    'rapor' => str_repeat('Gozden gecirilmis rapor metni. ', 8)]);
den('hakem raporunu yeniden gonderebildi', olur($p3), hata_metni($p3));
den('  diyalog rapor guncellemesinden sag cikti', diy_say('dtestA', 'Fatma Alti') === $oncekiF,
    diy_say('dtestA', 'Fatma Alti') . ' / ' . $oncekiF);
/* Karar sürecin sonuna varınca kanal kapanır ama kayıt durur. */
$p4 = gonder('/api/hakem-gonder', $hk('Fatma Alti') + ['karar' => 'ret',
    'rapor' => str_repeat('Ret karari raporu. ', 10)]);
den('hakem karari ret olarak kesinlestirdi', olur($p4), hata_metni($p4));
den('  kanal kapandi', tg_diyalog_sira(hakemi('dtestA', 'Fatma Alti')) === '',
    tg_diyalog_sira(hakemi('dtestA', 'Fatma Alti')));
den('  ama yazisma kayitta duruyor', diy_say('dtestA', 'Fatma Alti') === $oncekiF);
$p5 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Fatma Alti', 'metin' => ileti(200)]);
den('  kapanan kanala yeni ileti yazilamiyor', !olur($p5) && $p5['kod'] === 409, hata_metni($p5));
/* Yazar metnini yeniden kaydettiginde de diyalog yerinde kalmali. */
$p6 = gonder('/api/yazar-kaydet', $yz + ['baslik' => 'Diyalog sınaması A', 'ozet' => 'Sınama özeti, uçuncu.',
    'metin' => '<p>Sınama metni, ucuncu sürüm.</p>', 'kaynakca' => 'Kaynak listesi.']);
den('yazar metni yeniden kaydetti', olur($p6), hata_metni($p6));
den('  diyalog metin guncellemesinden de sag cikti', diy_say('dtestA', 'Fatma Alti') === $oncekiF);

/* Herkese açık listede yazışma görünüyor; hakem anahtarı görünmüyor. */
$liste = ist('/api/yazilar');
$lj = json_decode($liste['govde'], true);
$acikA = [];
foreach (($lj['yazilar'] ?? $lj['liste'] ?? (is_array($lj) ? $lj : [])) as $e) {
    if (is_array($e) && (string)($e['id'] ?? '') === 'dtestA') $acikA = $e;
}
$acikDiy = 0;
foreach (($acikA['hakemler'] ?? []) as $h) $acikDiy += count($h['diyalog'] ?? []);
den('herkese acik listede yazisma yer aliyor', $acikDiy > 0, (string)$acikDiy);
$sizAnahtar = strpos($liste['govde'], HT['Emre Bes']);
den('  ama hakem anahtari ve sifresi sizmiyor',
    $sizAnahtar === false && strpos($liste['govde'], h_sifre('Emre Bes')) === false,
    $sizAnahtar === false ? 'sifre sizdi' : ('anahtar @' . $sizAnahtar . ': '
        . mb_substr(substr($liste['govde'], max(0, (int)$sizAnahtar - 90), 180), 0, 180)));

/* Kalıcı arşiv dökümü: sözün "kalıcı kayıt" parçası burada sınanır. */
require_once $KOK . '/k/dokum.php';
$dkH = function_exists('dk_hakem') ? dk_hakem(hakemi('dtestA', 'Emre Bes')) : [];
den('dokum katman b hakem kaydinda diyalog var', isset($dkH['diyalog']) && $dkH['diyalog'] !== [],
    'anahtarlar: ' . implode(',', array_keys($dkH)));

echo "\n== 9. Yayimlanan sayfada yazisma raporun ALTINDA ==\n";
/* Tuzak: yazi.php k_tarih() gibi yuklemedigi bir islevi cagirirsa sayfa
   yarida kesilir ve gorunuste kirpilmis bir sayfa doner. Bu yuzden hem
   HTTP kodu hem gövdenin sonu hem de sunucu kutugu ayrica olculur. */
$sayfa = ist('/tamga/KTG-2026-90001-1?lang=tr&surec=1');
den('yayin sayfasi 200 donuyor', $sayfa['kod'] === 200, (string)$sayfa['kod']);
$gov = $sayfa['govde'];
den('sayfa yarida kesilmemis (</html> ile bitiyor)', substr(rtrim($gov), -7) === '</html>',
    mb_substr(rtrim($gov), -40));
den('sayfa makul uzunlukta', strlen($gov) > 20000, (string)strlen($gov));
$kutu = strpos($gov, '<section class="hakemler">');
den('hakem bolumu var', $kutu !== false);
if ($kutu !== false) {
    $dilim = substr($gov, $kutu);
    preg_match_all('#<div class="hakem-(kutu kart|rapor|diyalog)"#', $dilim, $mm, PREG_OFFSET_CAPTURE);
    $ilkRapor = null; $ilkDiy = null; $kutuArasi = false;
    foreach ($mm[1] as $ix => $x) {
        if ($x[0] === 'rapor' && $ilkRapor === null) $ilkRapor = $mm[0][$ix][1];
        if ($x[0] === 'diyalog' && $ilkDiy === null) $ilkDiy = $mm[0][$ix][1];
    }
    den('rapor govdesi sayfada', $ilkRapor !== null);
    den('yazisma sayfada', $ilkDiy !== null);
    den('yazisma raporun ALTINDA', $ilkRapor !== null && $ilkDiy !== null && $ilkDiy > $ilkRapor,
        'rapor@' . var_export($ilkRapor, true) . ' diyalog@' . var_export($ilkDiy, true));
    if ($ilkRapor !== null && $ilkDiy !== null && $ilkDiy > $ilkRapor) {
        /* Ayni hakem kutusunun icinde olmali: arada yeni bir kutu acilmamali. */
        $ara = substr($dilim, $ilkRapor, $ilkDiy - $ilkRapor);
        den('  ve ayni hakem kutusunun icinde', strpos($ara, 'class="hakem-kutu') === false);
    }
    den('yazarin notu sayfada okunuyor', mb_strpos($dilim, 'Birinci surum notu') !== false);
    den('hakemin karsiligi sayfada okunuyor', mb_strpos($dilim, 'Ikinci surum karsiligi') !== false);
    den('kimin yazdigi belli', mb_strpos($dilim, 'hd-yazar') !== false && mb_strpos($dilim, 'hd-hakem') !== false);
    $sizSayfa = strpos($gov, HT['Emre Bes']);
    den('sayfada hakem anahtari sizmiyor', $sizSayfa === false,
        $sizSayfa === false ? '' : ('@' . $sizSayfa . ': '
            . mb_substr(substr($gov, max(0, (int)$sizSayfa - 90), 180), 0, 180)));
}

echo "\n== 10. hakem.php yerlesimi: yazarin yaniti raporun USTUNDE ==\n";
$hs = ist('/hakem.php?t=' . HT['Emre Bes']);
den('hakem sayfasi 200 donuyor', $hs['kod'] === 200, (string)$hs['kod']);
den('hakem sayfasi yarida kesilmemis', substr(rtrim($hs['govde']), -7) === '</html>',
    mb_substr(rtrim($hs['govde']), -40));
$pD = strpos($hs['govde'], 'id="diyalogKart"');
$pR = strpos($hs['govde'], 'id="rapor"');
den('diyalog karti sayfada', $pD !== false);
den('rapor kutusu sayfada', $pR !== false);
den('yazarin yaniti rapor kutusunun USTUNDE', $pD !== false && $pR !== false && $pD < $pR,
    'diyalog@' . var_export($pD, true) . ' rapor@' . var_export($pR, true));
den('  karar secenekleri de diyalogdan sonra geliyor',
    $pD !== false && strpos($hs['govde'], 'name="karar"') > $pD);
/* Sayfanin veriyi aldigi uc: yazisma, sira ve sinirlar birlikte gelmeli;
   yoksa tarayici kanali dogru cizemez. */
$hf = gonder('/api/hakem-form', ['t' => HT['Emre Bes'], 'mail' => h_posta('Emre Bes'),
                                 'sifre' => h_sifre('Emre Bes')]);
den('hakem formu yazismayi donduruyor', olur($hf) && count($hf['j']['diyalog'] ?? []) > 0,
    (string)count($hf['j']['diyalog'] ?? []));
den('  sirayi da donduruyor', array_key_exists('diyalog_sira', $hf['j']));
den('  tur ve sinirlari da donduruyor',
    (int)($hf['j']['diyalog_ust'] ?? 0) === 2 && (int)($hf['j']['diyalog_asgari'] ?? 0) === 80,
    (string)($hf['j']['diyalog_ust'] ?? '-') . '/' . (string)($hf['j']['diyalog_asgari'] ?? '-'));

echo "\n== 11. Bildirimler ==\n";
sayac_sifirla();
function posta_kutugu(): string { global $VERI; return is_file($VERI . '/eposta.log') ? (string)file_get_contents($VERI . '/eposta.log') : ''; }
@file_put_contents($VERI . '/eposta.log', '');
/* Yazar yeni surum yazdiginda hakeme de haber gitmeli: eskiden yalnizca
   yazara gidiyordu ve hakem karari eski metne bakarak veriyordu. */
$n1 = gonder('/api/yazar-kaydet', $yz + ['baslik' => 'Diyalog sınaması A', 'ozet' => 'Sınama özeti, dorduncu.',
    'metin' => '<p>Sınama metni, dorduncu sürüm.</p>', 'kaynakca' => 'Kaynak listesi.']);
den('yeni surum yazildi', olur($n1), hata_metni($n1));
$log = posta_kutugu();
den('yazara bildirim gitti', strpos($log, 'yazara@ornek.org') !== false);
den('HAKEME de bildirim gitti', strpos($log, h_posta('Adem Bir')) !== false, mb_substr($log, 0, 200));
den('  bildirimin konusu yeni surum', strpos($log, 'yeni sürümü var') !== false);
den('  sureci kapali hakeme bildirim GITMEDI', strpos($log, h_posta('Dilek Dort')) === false);
den('  karari kesinlesmis hakeme bildirim GITMEDI', strpos($log, h_posta('Fatma Alti')) === false);
@file_put_contents($VERI . '/eposta.log', '');
$n2 = gonder('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Bade Iki', 'metin' => ileti(200, 'Bildirim notu. ')]);
den('yazar notu yazildi', olur($n2), hata_metni($n2));
$log = posta_kutugu();
den('yazar notu hakeme bildirildi', strpos($log, h_posta('Bade Iki')) !== false, mb_substr($log, 0, 200));
@file_put_contents($VERI . '/eposta.log', '');
$n3 = gonder('/api/hakem-yazar-yanit', $hk('Bade Iki') + ['metin' => ileti(200, 'Bildirim karsiligi. ')]);
den('hakem karsiligi yazildi', olur($n3), hata_metni($n3));
$log = posta_kutugu();
den('hakem karsiligi yazara bildirildi', strpos($log, 'yazara@ornek.org') !== false, mb_substr($log, 0, 200));

echo "\n== 12. Sunucu kutugu temiz mi ==\n";
/* Yarida kesilen bir sayfa gorunuste kirpilmis bir sayfadir; kutuge
   bakmadan "sayfa geldi" demek olcumu yaniltir. */
$yeni = '';
if (is_file($KLOG)) {
    $fp = @fopen($KLOG, 'rb');
    if ($fp) { @fseek($fp, $LOG_BAS); $yeni = (string)stream_get_contents($fp); fclose($fp); }
}
den('sunucu kutugu okunabildi', is_file($KLOG), $KLOG);
$agir = [];
foreach (preg_split('/\R/', $yeni) ?: [] as $satir) {
    if (preg_match('/(Fatal error|Parse error|Uncaught|Warning|Notice|Deprecated)/i', $satir)) $agir[] = trim($satir);
}
den('olcum boyunca Fatal/Warning/Notice/Deprecated yok', $agir === [],
    count($agir) . ' satir: ' . mb_substr(implode(' | ', array_slice($agir, 0, 3)), 0, 240));
$sunucuHata = 0;
foreach (preg_split('/\R/', $yeni) ?: [] as $satir) {
    if (preg_match('#\] 127\.0\.0\.1:\d+ \[(5\d\d)\]#', $satir, $m)) $sunucuHata++;
}
den('hicbir istek 5xx donmedi', $sunucuHata === 0, (string)$sunucuHata . ' istek');

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
