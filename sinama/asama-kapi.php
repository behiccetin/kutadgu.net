<?php
/* =====================================================================
   HAKEMLİK AŞAMASI: kapı ölçümü. Depoya girmez.

   Ölçülen söz iki yönlüdür ve biri olmadan öteki yanlış olur:

     1. Rapor gelmemiş bir çalışmaya hiçbir yerde "hakemli" denmez.
        'tur' alanı çalışmanın hangi YOLDA olduğunu söyler, o yolun
        neresinde olduğunu değil; yazar hakemliğe açtığı anda
        tur='hakemli' yazılır ve tek bir rapor bile gelmemiştir.
     2. O çalışma gizlenmez, kısıtlanmaz, listelerden çıkarılmaz.
        Açıkta durması gönüllü hakem bulmanın yoludur.

   Yalnız birinciyi ölçen bir kapı, çalışmayı arşivden silerek de
   geçilebilirdi. Bu yüzden 3. bölüm 2. bölüm kadar bağlayıcıdır.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php asama-kapi.php

   Betik VERİYİ DEĞİŞTİRİR: sınama verisinde hiçbir çalışma 'aranan'
   aşamasında değildir, o hâl kurulmadan ölçülemez. Başta yedek alınır,
   register_shutdown_function ile hata hâlinde bile geri yüklenir ve
   döküm yeniden üretilir.
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
/* Sunucu kütüğü 11. bölümde okunur. Kapıyı koda gömmek paylaşılan bir
   sunucuya yazmak demekti; kütük yolu da aynı sebeple dışarıdan gelir. */
$KUTUK = getenv('KLOG') ?: '/home/claude/kg/sunucu.log';

if ($VERI === '' || !is_dir($VERI)) {
    fwrite(STDERR, "KUTADGU_DATA verilmedi ya da dizin yok.\n");
    exit(2);
}
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;

require_once $KOD . '/k/dokum.php';      /* ortak.php ve k/veri.php'yi de yükler */
require_once $KOD . '/k/parca.php';      /* k_suz_tur(): süzgecin tek kaynağı */

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
/* Ölçülemeyen şey KALDI değildir: eşzamanlı yürüyen bir işin henüz
   ortada olmaması gerileme sayılmaz, ama sessizce de geçilmez. */
function not_(string $s): void { echo "  NOT    $s\n"; }

/* ---------------------------------------------------------------------
   HTTP yardımcıları
   --------------------------------------------------------------------- */
function ist(string $yol, array $bas = [], string $metod = 'GET', string $govde = ''): array {
    global $PORT;
    /* Uçlar IP başına sayar; her istek ayrı adresten gelmiş gibi
       gönderilir, yoksa bölümün ortasında 429 başlar. */
    $bas[] = 'X-Forwarded-For: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250);
    $o = ['method' => $metod, 'header' => implode("\r\n", $bas), 'ignore_errors' => true, 'timeout' => 40];
    if ($govde !== '') $o['content'] = $govde;
    $ctx = stream_context_create(['http' => $o]);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, $ctx);
    $h = $http_response_header ?? [];
    $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g];
}
function bas(array $h, string $ad): string {
    foreach ($h as $s) if (stripos($s, $ad . ':') === 0) return trim(substr($s, strlen($ad) + 1));
    return '';
}
/* Sayfa çekimi tek başına yeterli değildir: yazi.php içinde bulunmayan
   bir işlev çağrılırsa sayfa 500 verir ve BOŞ değil KIRPILMIŞ gelir.
   Bu yüzden her çekimde kod ve gövdenin kapanışı ayrıca ölçülür. */
function sayfa(string $yol): array {
    $r = ist($yol);
    $r['tam'] = ($r['kod'] === 200) && (strpos(substr($r['govde'], -400), '</html>') !== false);
    return $r;
}

/* ---------------------------------------------------------------------
   Görünür metin: betik ve biçem çıkarılır, öznitelikler eklenir.
   Okur yalnızca gövdeyi görmez; ekran okuyucu aria-label'ı da okur,
   fare title'ı da gösterir. Yanlış söz oralarda da söylenebilir.
   --------------------------------------------------------------------- */
function gorunur(string $html): string {
    $h = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);
    preg_match_all('#\b(title|alt|placeholder|aria-label)\s*=\s*"([^"]*)"#i', (string)$h, $m);
    $t = strip_tags((string)$h) . ' || ' . implode(' | ', $m[2]);
    $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string)preg_replace('/\s+/u', ' ', $t));
}
/* Sözcük sınırlı arama. "hakemli" düz alt dizge olarak arandığında
   "hakemlik" ve "hakemliğe" de eşleşir; kapı o zaman ısırmadığı hâlde
   ısırıyor sanılır. Sınır YALNIZCA harf sınıfıyla verilir: sekme
   etiketinde sayı doğrudan metne yapışık basılıyor ("Hakemli4") ve
   rakamı da sınır saymak gerçek bir kullanımı gözden kaçırırdı. */
function soz_var(string $metin, string $soz): bool {
    return (bool)preg_match('/(?<![\p{L}])' . preg_quote($soz, '/') . '(?![\p{L}])/iu', $metin);
}

/* ---------------------------------------------------------------------
   Bir çalışmanın kendi bloğu.

   Sayfada başka çalışmalar da vardır ve onların biri hakemliden geçmiş
   olabilir. Bütün sayfada "Hakemli" aramak sahte KALDI üretir; ölçüm
   çalışmanın kendi kartıyla sınırlı olmalı. Blok, çalışmanın adresine
   giden bağlantıdan yukarı çıkılarak bulunur: article, li, tr ya da
   'kart' sınıflı ilk ata.
   --------------------------------------------------------------------- */
function bloklar(string $html, string $yol): array {
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!@$d->loadHTML('<?xml encoding="UTF-8">' . $html)) { libxml_clear_errors(); return []; }
    libxml_clear_errors();
    $x = new DOMXPath($d);
    foreach (iterator_to_array($x->query('//script|//style|//template')) as $n) {
        if ($n->parentNode) $n->parentNode->removeChild($n);
    }
    $out = [];
    foreach ($x->query('//a[@href]') as $a) {
        if (strpos((string)$a->getAttribute('href'), $yol) === false) continue;
        $n = $a; $blok = $a; $adim = 0;
        while ($n->parentNode && $n->parentNode->nodeType === XML_ELEMENT_NODE && $adim < 8) {
            $n = $n->parentNode; $adim++;
            $ad = strtolower($n->nodeName);
            $sn = (string)$n->getAttribute('class');
            $blok = $n;
            if (in_array($ad, ['article', 'li', 'tr'], true) || preg_match('/(^|\s)kart(\s|$|-)/u', $sn)) break;
        }
        $metin = $blok->textContent;
        foreach (['title', 'alt', 'placeholder', 'aria-label'] as $on) {
            foreach ($x->query('.//@' . $on, $blok) as $at) $metin .= ' | ' . $at->nodeValue;
            if ($blok->hasAttribute($on)) $metin .= ' | ' . $blok->getAttribute($on);
        }
        $out[] = trim((string)preg_replace('/\s+/u', ' ', $metin));
    }
    return $out;
}

/* ---------------------------------------------------------------------
   VERİ YEDEĞİ VE GERİ YÜKLEME
   --------------------------------------------------------------------- */
$YZ   = rtrim($VERI, '/') . '/yazilar.json';
$HS   = rtrim($VERI, '/') . '/hesaplar.json';
$yedek = sys_get_temp_dir() . '/asama-kapi-yazilar-' . getmypid() . '.json';
if (!is_file($YZ)) { fwrite(STDERR, "yazilar.json yok: $YZ\n"); exit(2); }
copy($YZ, $yedek);
$hesapVardi = is_file($HS);
$hesapYedek = sys_get_temp_dir() . '/asama-kapi-hesaplar-' . getmypid() . '.json';
if ($hesapVardi) copy($HS, $hesapYedek);

register_shutdown_function(function () use ($YZ, $yedek, $HS, $hesapVardi, $hesapYedek): void {
    /* Hata hâlinde de geri yüklenir: yarım kalmış bir sınama, sonraki
       sınamanın verisini bozmuş olmasın. */
    if (is_file($yedek)) { @copy($yedek, $YZ); @unlink($yedek); }
    if ($hesapVardi) { @copy($hesapYedek, $HS); @unlink($hesapYedek); }
    elseif (is_file($HS)) @unlink($HS);
    /* Döküm veriden üretilir; veri geri alındıysa döküm de eskimiştir. */
    if (function_exists('dk_kirlet')) { dk_kirlet('asama-kapi geri yukleme'); @dk_uret_hafif(true); }
});

function yazilari_oku(): array {
    global $YZ;
    $j = json_decode((string)file_get_contents($YZ), true);
    return is_array($j) ? $j : [];
}
function yazilari_yaz(array $y): void {
    global $YZ;
    file_put_contents($YZ, json_encode($y, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}

/* ---------------------------------------------------------------------
   FİKSTÜR
   Sınama verisinde 'aranan' bir çalışma yoktur; kurulur. Raporlar
   silinir, hakem kayıtları durur: hakemli yolda ama tek raporu yok.
   --------------------------------------------------------------------- */
$y = yazilari_oku();
$ARANAN = -1; $GECTI_IX = -1; $ACMA = -1; $RET = -1;
foreach ($y as $i => $e) {
    if (!is_array($e)) continue;
    $hakemVar = is_array($e['hakemler'] ?? null) && count($e['hakemler']) >= 2;
    if ($ARANAN < 0 && (string)($e['tur'] ?? '') === 'hakemli' && $hakemVar) { $ARANAN = $i; continue; }
    if ($GECTI_IX < 0 && (string)($e['tur'] ?? '') === 'hakemli' && tg_rapor_sayisi($e) > 0) { $GECTI_IX = $i; continue; }
    if ($RET < 0 && (string)($e['tur'] ?? '') === 'hakemli' && $hakemVar) { $RET = $i; continue; }
    if ($ACMA < 0 && (string)($e['tur'] ?? '') !== 'hakemli' && empty($e['hakemler'])) { $ACMA = $i; continue; }
}
if ($ARANAN < 0 || $GECTI_IX < 0) { fwrite(STDERR, "Fikstur kurulamadi: uygun calisma yok.\n"); exit(2); }

foreach ($y[$ARANAN]['hakemler'] as $j => $h) {
    if (!is_array($h)) continue;
    $y[$ARANAN]['hakemler'][$j]['rapor'] = '';
    $y[$ARANAN]['hakemler'][$j]['karar'] = '';
    unset($y[$ARANAN]['hakemler'][$j]['raporlar'], $y[$ARANAN]['hakemler'][$j]['kapali'],
          $y[$ARANAN]['hakemler'][$j]['endeks_anket'], $y[$ARANAN]['hakemler'][$j]['notlar']);
}
/* 10. bölümün iki ret denemesi için iki hakeme erişim jetonu verilir. */
$RET_JETON = [];
if ($RET >= 0) {
    $n = 0;
    foreach ($y[$RET]['hakemler'] as $j => $h) {
        if (!is_array($h) || $n >= 2) continue;
        $t = bin2hex(random_bytes(16));
        $y[$RET]['hakemler'][$j]['token'] = $t;
        $y[$RET]['hakemler'][$j]['rapor'] = '';
        $y[$RET]['hakemler'][$j]['karar'] = '';
        unset($y[$RET]['hakemler'][$j]['kapali'], $y[$RET]['hakemler'][$j]['eposta_hash'], $y[$RET]['hakemler'][$j]['sifre']);
        $RET_JETON[] = $t; $n++;
    }
}
$ACMA_JETON = '';
if ($ACMA >= 0) {
    $ACMA_JETON = bin2hex(random_bytes(16));
    $y[$ACMA]['yazar_erisim'] = ['token' => $ACMA_JETON];
}
yazilari_yaz($y);
dk_kirlet('asama-kapi fikstur');
dk_uret_hafif(true);

$y = yazilari_oku();
$A   = $y[$ARANAN];            /* rapor gelmemiş, hakemli yolda */
$B   = $y[$GECTI_IX];          /* en az bir raporu olan denetim çalışması */
$Ayol = tg_yazi_yolu($A);      /* /tamga/<bcid> */
$Byol = tg_yazi_yolu($B);
$Abas = (string)($A['baslik'] ?? '');
$Atamga = (string)($A['bcid'] ?? '');
$Aslug = '';
foreach (tg_yazar_kayitlari($A) as $ya) { $Aslug = tg_ad_slug((string)($ya['ad'] ?? '')); if ($Aslug !== '') break; }

/* Hız sınırı sıfırlanır: uçlar IP başına sayar ve önceki sınamalardan
   kalmış bir sayaç bu betiği 429'a düşürür. */
@file_put_contents(rtrim($VERI, '/') . '/hiz-sinir.json', '{}');
$kutukBaslangic = is_file($KUTUK) ? (int)filesize($KUTUK) : 0;

echo "Veri: $VERI   Kapı: $PORT\n";
echo "Ölçülen çalışma: $Atamga  (" . mb_substr($Abas, 0, 46) . ")\n";
echo "Denetim çalışması: " . (string)($B['bcid'] ?? '') . "  rapor=" . tg_rapor_sayisi($B) . "\n\n";

/* Aşama metinleri: kapının beklediği doğru sözler tek kaynaktan alınır,
   elle yazılmaz. Elle yazılsaydı kapı da ölçtüğü hatayı yapardı. */
$M_ARANAN_TR = tg_asama_metni('aranan', false);
$M_ARANAN_EN = tg_asama_metni('aranan', true);
$YANLIS = ['Hakemli', 'Peer reviewed', 'Peer-Reviewed', 'Hakemli Makale'];

/* =====================================================================
   1. AŞAMA HESABI
   ===================================================================== */
echo "== 1. Asama hesabi: bes asama ve sinir haller ==\n";

$rapor = str_repeat('Değerlendirme metni. ', 30);
$nitelikli = fn(string $karar) => [
    'ad' => 'Hakem', 'karar' => $karar, 'rapor' => $rapor,
    'notlar' => [['not' => 'ilk işaret'], ['not' => 'ikinci işaret']],
    'endeks_anket' => ['q' => 'q1'],
    'atayan' => ['tur' => 'editor', 'ad' => 'Editör'],
];
$ornek = [
    'yok'     => ['tur' => 'yazi'],
    'aranan'  => ['tur' => 'hakemli', 'hakemler' => [['ad' => 'H', 'rapor' => '']]],
    'suruyor' => ['tur' => 'hakemli', 'hakemler' => [$nitelikli('kabul')]],
    'onayli'  => ['tur' => 'hakemli', 'hakemler' => [$nitelikli('kabul'), $nitelikli('kabul')]],
    'cekildi' => ['tur' => 'hakemli', 'hakemler' => [$nitelikli('kabul')],
                  'kayitlar' => [['tur' => 'geri_cekme', 'tarih' => '2026-01-01', 'metin' => 'x']]],
];
foreach ($ornek as $bekle => $kayit) {
    den("asama '$bekle' dogru hesaplaniyor", tg_hakem_asamasi($kayit) === $bekle, tg_hakem_asamasi($kayit));
}
den("bes asamanin hepsi ayri", count(array_unique(array_map('tg_hakem_asamasi', $ornek))) === 5);

den("rapor alani bos dizge: 'aranan'",
    tg_hakem_asamasi(['tur' => 'hakemli', 'hakemler' => [['rapor' => '']]]) === 'aranan');
den("rapor alani yalniz bosluk: 'aranan'",
    tg_hakem_asamasi(['tur' => 'hakemli', 'hakemler' => [['rapor' => "  \n\t "]]]) === 'aranan');
den("'hakemler' alani hic yok: 'aranan'",
    tg_hakem_asamasi(['tur' => 'hakemli']) === 'aranan');
den("'hakemler' alani hic yok: hakemden gecmemis",
    tg_hakemden_gecti(['tur' => 'hakemli']) === false);

/* Bozuk kayıt: 'hakemler' dizi değil dizge. Sonucun doğru olması yetmez,
   yol boyunca uyarı da basmamalı; kütükte uyarı sayısı sıfır olmalı. */
$uyari = [];
set_error_handler(function (int $n, string $s) use (&$uyari): bool { $uyari[] = $s; return true; });
$bozuk = ['tur' => 'hakemli', 'hakemler' => 'bozuk-veri'];
$bozukAsama = tg_hakem_asamasi($bozuk);
$bozukGecti = tg_hakemden_gecti($bozuk);
restore_error_handler();
den("'hakemler' dizge iken asama yine 'aranan'", $bozukAsama === 'aranan', $bozukAsama);
den("'hakemler' dizge iken hakemden gecmemis", $bozukGecti === false);
den("'hakemler' dizge iken PHP uyarisi uretilmiyor", $uyari === [], implode(' / ', array_slice($uyari, 0, 1)));

/* Geri çekilme geçmişi silmez: çekilmiş ama raporlanmış çalışma
   hakemlenmiş sayılır, bu yüzden tg_hakemden_gecti() aşamaya değil
   rapor sayısına bakar. */
den("geri cekilmis ama raporlu: asama 'cekildi'", tg_hakem_asamasi($ornek['cekildi']) === 'cekildi');
den("geri cekilmis ama raporlu: hakemden gecti dogru", tg_hakemden_gecti($ornek['cekildi']) === true);
den("geri cekilmis ve raporsuz: hakemden gecmemis",
    tg_hakemden_gecti(['tur' => 'hakemli', 'hakemler' => [['rapor' => '']],
                       'kayitlar' => [['tur' => 'geri_cekme']]]) === false);
den("hakemsiz yolda rapor olsa bile hakemden gecmemis",
    tg_hakemden_gecti(['tur' => 'yazi', 'hakemler' => [$nitelikli('kabul')]]) === false);

/* İki ret alan çalışmanın YOLU 'yazi'ya döner ama GEÇMİŞİ silinmez.
   BU KAPI 19 AĞUSTOS 2026'DA DÜZELTİLDİ; eskiden şunu ölçüyordu:

       den("iki ret sonrasi (tur='yazi') asama 'yok'", ... === 'yok');
       den("iki ret sonrasi hakemden gecmis sayilmiyor", ... === false);

   Yani kapı, kodun yaptığını doğru sayıyordu. Sistemin duyurduğu kural
   ise başkaydı (hakemlik.php): "ret alan çalışma silinmez; hakemsiz
   yazıya döner ve ALDIĞI RAPORLARLA BİRLİKTE AÇIK KALIR." Aşama 'yok'
   olduğu için makale sayfası hakem bölümünü hiç basmıyor, üstüne "hakem
   değerlendirmesinden geçmemiştir" diyordu. Kapının bunu görememesinin
   sebebi ölçtüğü yerdi: VERİYİ ölçüyordu (tg_rapor_sayisi hâlâ 2) ve
   sayfanın 200 dönmesine bakıyordu; SAYFANIN NE YAZDIĞINA bakmıyordu.
   Aşağıya o ölçüm de eklendi.

   Doğru olan: 'reddedildi' basamağı. Bu basamak ve "Hakemler reddetti"
   rozeti kodda zaten yazılıydı ama ERİŞİLEMEZDİ, çünkü eşik dolduğu
   anda tur değişiyordu. */
$ikiRet = ['tur' => 'yazi', 'ret_donusum' => ['ret_sayisi' => 2],
           'hakemler' => [$nitelikli('ret'), $nitelikli('ret')]];
den("iki ret sonrasi asama 'reddedildi'", tg_hakem_asamasi($ikiRet) === 'reddedildi',
    tg_hakem_asamasi($ikiRet));
den("iki ret sonrasi rozet 'Hakemler reddetti' diyor",
    tg_asama_metni(tg_hakem_asamasi($ikiRet)) === 'Hakemler reddetti');
den("iki ret sonrasi hakemli yolda DEGIL", tg_hakem_yolunda($ikiRet) === false);
den("iki ret sonrasi hakemli yolu GORMUS", tg_hakem_yolu_gormus($ikiRet) === true);
den("iki ret sonrasi ret turu 'hakem'", tg_ret_turu($ikiRet) === 'hakem', tg_ret_turu($ikiRet));
/* Düşme kaydı olmayan eski/elle yazılmış kayıt da geçmişini korur. */
den("ret_donusum kaydi olmasa da gecmis korunuyor",
    tg_hakem_asamasi(['tur' => 'yazi', 'hakemler' => [$nitelikli('ret'), $nitelikli('ret')]]) === 'reddedildi');
/* Hakemsiz yolda TEK ret raporu olan kayıt düşmüş sayılmaz: eşik
   dolmadan yolu değiştiren şey başka bir sebeptir (yazar kapattı). */
den("tek ret ile hakemsiz yol dusmus SAYILMIYOR",
    tg_hakemlikten_dusmus(['tur' => 'yazi', 'hakemler' => [$nitelikli('ret')]]) === false);
den("iki ret sonrasi rapor sayisi korunuyor", tg_rapor_sayisi($ikiRet) === 2);
den("iki ret sonrasi hakem aranmiyor", tg_hakem_araniyor($ikiRet) === false);

den("rapor sayisi bosluklu raporu saymiyor",
    tg_rapor_sayisi(['hakemler' => [['rapor' => ' '], ['rapor' => 'x'], ['rapor' => '']]]) === 1);

/* Metinler: her aşamanın iki dilde bir karşılığı var ve hiçbiri boş
   değil. Boş dönen bir aşama rozeti sessizce yok eder. */
$eksik = [];
foreach (['cekildi', 'yok', 'aranan', 'suruyor', 'onayli'] as $as) {
    foreach ([false, true] as $en) foreach ([false, true] as $kisa) {
        if (trim(tg_asama_metni($as, $en, $kisa)) === '') $eksik[] = "$as/$en/$kisa";
    }
    if (trim(tg_asama_rz($as)) === '') $eksik[] = "rz:$as";
}
den("her asamanin iki dilde metni ve rozet rengi var", $eksik === [], implode(',', $eksik));
den("bilinmeyen asama metni bos doner", tg_asama_metni('uydurma') === '');
den("'aranan' aciklamasi 'gecmemistir' diyor",
    mb_stripos(tg_asama_aciklama('aranan', false), 'geçmemiştir') !== false
    && mb_stripos(tg_asama_aciklama('aranan', true), 'has not undergone peer review') !== false);
den("'aranan' aciklamasi gonullu cagrisi da yapiyor",
    mb_stripos(tg_asama_aciklama('aranan', false), 'gönüllü') !== false);

/* =====================================================================
   2. HİÇBİR SAYFA RAPORSUZ ÇALIŞMAYA "HAKEMLİ" DEMİYOR
   ===================================================================== */
echo "\n== 2. Raporsuz calismaya hicbir sayfa 'hakemli' demiyor (iki dil) ==\n";

$aramaQ = rawurlencode('"' . $Abas . '"');
$sayfalar = [
    'ana sayfa'        => '/',
    'arsiv'            => '/yazilar',
    'calisma sayfasi'  => $Ayol,
    'calisma (surec)'  => $Ayol . '?surec=1',
    'kisi sayfasi'     => $Aslug !== '' ? '/kisi/' . rawurlencode($Aslug) : '',
    'arama'            => '/ara?f1=' . $aramaQ,
    'istatistik'       => '/istatistik',
];
foreach (['tr', 'en'] as $dil) {
    $en = $dil === 'en';
    $dogruRozet = tg_asama_metni('aranan', $en);
    foreach ($sayfalar as $ad => $yol) {
        if ($yol === '') { not_("$ad [$dil]: adres cozulemedi, olculmedi"); continue; }
        $u = $yol . (strpos($yol, '?') === false ? '?' : '&') . 'lang=' . $dil;
        $r = sayfa($u);
        den("$ad [$dil] 200 ve tam basildi", $r['tam'], 'kod=' . $r['kod']);
        if (!$r['tam']) continue;

        /* Çalışmanın kendi sayfasında bütün sayfa o çalışmanındır;
           listelerde yalnızca kendi kartı ölçülür. */
        $parcalar = (strpos($yol, $Ayol) === 0) ? [gorunur($r['govde'])] : bloklar($r['govde'], $Ayol);
        if (!$parcalar) { not_("$ad [$dil]: calisma bu sayfada gorunmuyor, 2. bolum olculmedi"); continue; }
        $kotu = [];
        foreach ($parcalar as $p) foreach ($YANLIS as $s) if (soz_var($p, $s)) $kotu[] = $s;
        den("$ad [$dil] calismanin blogunda yanlis soz yok", $kotu === [], implode(',', array_unique($kotu)));
        $rozetli = false;
        foreach ($parcalar as $p) if (mb_strpos($p, $dogruRozet) !== false) $rozetli = true;
        den("$ad [$dil] dogru asama metni gorunuyor ('$dogruRozet')", $rozetli);
    }
}

/* Ana sayfadaki "Hakemli" sekmesi ve arşivdeki 'hakemli' süzgeci, okurun
   açıkça "hakemden geçmiş" diye istediği listelerdir; raporsuz bir
   çalışma oraya düşerse rozet doğru olsa da liste yalan söyler. */
$r = sayfa('/yazilar?tur=hakemli&lang=tr');
den("arsiv 'hakemli' suzgeci raporsuz calismayi listelemiyor",
    $r['tam'] && !bloklar($r['govde'], $Ayol));
/* Ana sayfadaki sekmeler: "Hakemli" başlıklı bölmede ne varsa okur onu
   hakemden geçmiş sayar. Bölme kimliğiyle bulunur, çünkü sayfada başka
   listeler de vardır ve hepsini birden ölçmek sahte KALDI verirdi. */
$r = sayfa('/?lang=tr');
$sekmeDurum = ['bulundu' => false, 'kirli' => []];
$araninanSekme = false;
if ($r['tam']) {
    $d = new DOMDocument(); libxml_use_internal_errors(true);
    @$d->loadHTML('<?xml encoding="UTF-8">' . $r['govde']); libxml_clear_errors();
    $x = new DOMXPath($d);
    foreach ($x->query('//*[starts-with(@id,"ars-p")]') as $pnl) {
        $no = substr((string)$pnl->getAttribute('id'), 5);
        /* getElementById(), DTD'siz yüklenen HTML'de güvenilmez; sekme
           düğmesi XPath ile aranır. */
        $dg = $x->query('//*[@id="ars-d' . $no . '"]')->item(0);
        $etiket = $dg ? trim((string)preg_replace('/\s+/u', ' ', $dg->textContent)) : '';
        $ic = $d->saveHTML($pnl);
        preg_match_all('#data-tur="([a-z]+)"#', (string)$ic, $mm);
        if (soz_var($etiket, 'Hakemli')) {
            $sekmeDurum['bulundu'] = true;
            foreach (array_unique($mm[1]) as $v) if ($v !== 'hakemli') $sekmeDurum['kirli'][] = $etiket . ':' . $v;
        }
        if (mb_strpos($etiket, $M_ARANAN_TR) !== false) $araninanSekme = true;
    }
}
den("ana sayfa 'Hakemli' sekmesinde yalniz raporlu calismalar var",
    $sekmeDurum['bulundu'] && $sekmeDurum['kirli'] === [], implode(',', $sekmeDurum['kirli']));
if ($araninanSekme) den("ana sayfada 'Hakem aranıyor' sekmesi acilmis", true);
else not_("ana sayfada 'Hakem aranıyor' sekmesi yok: eszamanli is henuz girmemis olabilir");

/* Aramanın tür süzgeci: okur "Peer reviewed" diye seçtiğinde ne alıyor? */
$r = sayfa('/ara?tur=hakemli&lang=en');
$araSonuc = $r['tam'] ? bloklar($r['govde'], $Ayol) : [];
den("arama 'Peer reviewed' suzgeci raporsuz calismayi vermiyor", $araSonuc === [],
    $araSonuc ? 'raporsuz calisma "Peer reviewed" suzgecinde cikiyor' : '');

/* =====================================================================
   3. ÇALIŞMA GİZLENMİYOR
   ===================================================================== */
echo "\n== 3. Raporsuz calisma gizlenmiyor, kisitlanmiyor ==\n";

$r = sayfa('/yazilar?lang=tr');
den("arsiv listesinde gorunuyor", $r['tam'] && (bool)bloklar($r['govde'], $Ayol));
$r = sayfa($Ayol . '?lang=tr');
den("kendi sayfasi 200 ve tam", $r['tam'], 'kod=' . $r['kod']);
$govdeMetin = $r['tam'] ? gorunur($r['govde']) : '';
$tamMetin = tg_duz((string)($A['metin'] ?? ''));
$ornekMetin = trim(mb_substr($tamMetin, 0, 60, 'UTF-8'));
den("tam metni basiliyor (kirpilmamis)", $ornekMetin !== '' && mb_strpos($govdeMetin, $ornekMetin) !== false);
den("ozeti basiliyor", trim((string)($A['ozet'] ?? '')) === ''
    || mb_strpos($govdeMetin, trim(mb_substr(tg_duz((string)$A['ozet']), 0, 40, 'UTF-8'))) !== false);
den("indirme/atif bolumu kisitlanmamis (sayfa uzunlugu denetim ile ayni duzeyde)",
    strlen($r['govde']) > 0.5 * strlen(sayfa($Byol . '?lang=tr')['govde']));

$r = ist('/api/yazilar');
$j = json_decode($r['govde'], true);
$apiKayit = null;
foreach ((array)($j['yazilar'] ?? []) as $e) if ((string)($e['bcid'] ?? '') === $Atamga) $apiKayit = $e;
den("/api/yazilar ciktisinda var", $apiKayit !== null);
den("/api/yazilar 'asama' alanini veriyor", (string)($apiKayit['asama'] ?? '') === 'aranan');
den("/api/yazilar 'hakemden_gecti' yanlis soylemiyor", ($apiKayit['hakemden_gecti'] ?? null) === false);
den("/api/yazilar tam metni veriyor", trim((string)($apiKayit['metin'] ?? '')) !== '');

$r = ist('/sitemap.xml');
den("sitemap'te var", strpos($r['govde'], $Ayol) !== false);
$r = ist('/oai?verb=ListRecords&metadataPrefix=oai_dc');
den("OAI ListRecords'ta var", strpos($r['govde'], $Atamga) !== false);

$katA = json_decode((string)@file_get_contents(dk_yol('kutadgu-ustveri.json')), true);
$varA = false;
foreach ((array)($katA['calismalar'] ?? []) as $e) if ((string)($e['tamga'] ?? '') === $Atamga) $varA = true;
den("arsiv dokumu katman a'da var", $varA);
$katB = json_decode((string)@file_get_contents(dk_yol('kutadgu-tam-tumu.json')), true);
$varB = false;
foreach ((array)($katB['calismalar'] ?? []) as $e) if ((string)($e['tamga'] ?? '') === $Atamga) $varB = true;
den("arsiv dokumu katman b'de var", $varB);

/* =====================================================================
   4. GÖNÜLLÜ ÇAĞRISI GÖRÜNÜYOR
   ===================================================================== */
echo "\n== 4. Gonullu cagrisi gorunuyor ==\n";
foreach (['tr', 'en'] as $dil) {
    $r = sayfa($Ayol . '?lang=' . $dil);
    $g = $r['tam'] ? $r['govde'] : '';
    den("calisma sayfasinda hakem cagrisi kutusu var [$dil]", strpos($g, 'hakem-cagri') !== false);
    den("cagri /bekleyen.php'ye baglaniyor [$dil]", (bool)preg_match('#href="[^"]*bekleyen\.php#', $g));
    $gr = gorunur($g);
    den("sayfa 'degerlendirmeden gecmemistir' cumlesini basiyor [$dil]",
        mb_strpos($gr, mb_substr(tg_asama_aciklama('aranan', $dil === 'en'), 0, 40, 'UTF-8')) !== false);
}
$r = sayfa('/bekleyen.php?lang=tr');
den("bekleyen.php calismayi listeliyor", $r['tam'] && (bool)bloklar($r['govde'], $Ayol));
$r2 = sayfa('/bekleyen.php?lang=en');
$bekEn = $r2['tam'] ? bloklar($r2['govde'], $Ayol) : [];
$bekRozet = false;
foreach ($bekEn as $p) if (mb_strpos($p, $M_ARANAN_EN) !== false) $bekRozet = true;
den("bekleyen.php rozeti tek kaynakla ayni ('" . $M_ARANAN_EN . "')", $bekRozet,
    $bekEn ? 'sayfa baska bir metin yaziyor' : 'calisma listede yok');
/* Raporlu çalışma bu listede olmamalı: çağrı, hakemi olmayan işe yapılır. */
den("bekleyen.php denetim calismasini (raporlu) listelemiyor",
    !tg_hakem_araniyor($B) ? !bloklar($r['govde'], $Byol) : true,
    'denetim calismasi hedefe ulasmadiysa listede olmasi dogrudur');

/* =====================================================================
   5. OAI-PMH ÜSTVERİSİ
   ===================================================================== */
echo "\n== 5. OAI-PMH ustverisi ==\n";
function oai_kayit_al(string $xml, string $tamga): string {
    if (preg_match('#<record>(?:(?!</record>).)*' . preg_quote($tamga, '#') . '(?:(?!</record>).)*</record>#s', $xml, $m)) return $m[0];
    return '';
}
$liste = ist('/oai?verb=ListRecords&metadataPrefix=oai_dc')['govde'];
$kA = oai_kayit_al($liste, $Atamga);
$kB = oai_kayit_al($liste, (string)($B['bcid'] ?? ''));
den("raporsuz calisma kaydi OAI'de bulunuyor", $kA !== '');
den("raporsuz calisma 'preprint' bildiriliyor", strpos($kA, 'semantics/preprint') !== false);
den("raporsuz calisma 'submittedVersion' bildiriliyor", strpos($kA, 'semantics/submittedVersion') !== false);
den("raporsuz calisma 'article' bildirilmiyor", strpos($kA, 'semantics/article') === false);
den("raporsuz calisma 'publishedVersion' bildirilmiyor", strpos($kA, 'semantics/publishedVersion') === false);
den("raporsuz calisma 'hakemli' setinde degil", strpos($kA, '<setSpec>hakemli</setSpec>') === false);
den("raporsuz calisma 'aranan' setinde", strpos($kA, '<setSpec>aranan</setSpec>') !== false);
den("raporlu calisma 'article' bildiriliyor", strpos($kB, 'semantics/article') !== false);
den("raporlu calisma 'publishedVersion' bildiriliyor", strpos($kB, 'semantics/publishedVersion') !== false);
den("raporlu calisma 'hakemli' setinde", strpos($kB, '<setSpec>hakemli</setSpec>') !== false);

$setler = [];
if (preg_match_all('#<setSpec>([^<]+)</setSpec>#', ist('/oai?verb=ListSets')['govde'], $m)) $setler = array_values(array_unique($m[1]));
den("ListSets 'aranan' setini de duyuruyor", in_array('aranan', $setler, true), implode(',', $setler));
/* 'openaire' seti OpenAIRE ve BASE doğrulayıcılarının aradığı addır ve
   'kutadgu' ile AYNI kayıtları taşır: buradaki her kayıt yönergenin
   istediği alanları zaten taşıyor. İkisi ayrı sayılsaydı, bir gün
   biri süzülüp öteki süzülmediğinde kimse fark etmezdi. */
$beklenen = ['kutadgu' => 0, 'openaire' => 0, 'hakemli' => 0, 'aranan' => 0];
foreach (yazilari_oku() as $e) {
    if (!is_array($e)) continue;
    $beklenen['kutadgu']++;
    $beklenen['openaire']++;
    if (tg_hakemden_gecti($e)) $beklenen['hakemli']++;
    elseif (tg_hakem_asamasi($e) === 'aranan') $beklenen['aranan']++;
}
foreach ($setler as $s) {
    $g = ist('/oai?verb=ListIdentifiers&metadataPrefix=oai_dc&set=' . rawurlencode($s))['govde'];
    $n = substr_count($g, '<identifier>');
    den("ListSets ile ListRecords suzmesi cakismiyor: set '$s'",
        isset($beklenen[$s]) && $n === $beklenen[$s], $n . ' / bekleniyordu ' . ($beklenen[$s] ?? '?'));
}
foreach (['ListRecords&metadataPrefix=oai_dc', 'ListIdentifiers&metadataPrefix=oai_dc', 'ListSets', 'Identify'] as $v) {
    $g = ist('/oai?verb=' . $v)['govde'];
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $ok = (bool)@$d->loadXML($g);
    libxml_clear_errors();
    den("OAI '$v' gecerli XML", $ok);
}

/* =====================================================================
   6. SAYAÇLAR
   ===================================================================== */
echo "\n== 6. Sayaclar: raporsuz calisma hakemli sayilmiyor ==\n";
$tumu = yazilari_oku();
$bGecti = 0; $bAranan = 0; $bAsama = ['cekildi' => 0, 'yok' => 0, 'aranan' => 0, 'suruyor' => 0, 'onayli' => 0];
foreach ($tumu as $e) {
    if (!is_array($e)) continue;
    if (tg_hakemden_gecti($e)) $bGecti++;
    $as = tg_hakem_asamasi($e);
    $bAsama[$as] = ($bAsama[$as] ?? 0) + 1;
    if ($as === 'aranan') $bAranan++;
}
$say = k_sayaclar();
den("k_sayaclar(): hakemli sayisi rapor gelmemisleri saymiyor", (int)$say['hakemli'] === $bGecti,
    $say['hakemli'] . ' / ' . $bGecti);
den("k_sayaclar(): 'aranan' ayri sayiliyor", (int)($say['aranan'] ?? -1) === $bAranan,
    ($say['aranan'] ?? '-') . ' / ' . $bAranan);
den("k_sayaclar(): hakemli + aranan toplami calisma sayisini asmiyor",
    (int)$say['hakemli'] + (int)($say['aranan'] ?? 0) <= (int)$say['toplam']);

/* istatistik.php: basamakların toplamı çalışma sayısını vermeli.
   Hiçbir çalışma sayılmadan kalmamalı, hiçbiri iki kez sayılmamalı.

   OKUBENI 35: bu deneme "count($basamak) === 4" diye YAZILIYDI ve
   beşinci basamak ('reddedildi') eklenince gerileme gibi göründü --
   oysa gerileyen kod değil, kapının kendi varsayımıydı. Beklenen sayı
   artık AŞAMA LİSTESİNDEN türetiliyor; yarın altıncı basamak eklenirse
   kapı yine kendiliğinden doğru sayıyı bekler. 'cekildi' listede yok,
   çünkü geri çekilmiş çalışma kendi ulaştığı basamakta sayılır. */
$ARSIV_BASAMAKLARI = ['yok', 'aranan', 'suruyor', 'onayli', 'reddedildi'];
$r = sayfa('/istatistik?lang=tr');
den("/istatistik 200 ve tam", $r['tam'], 'kod=' . $r['kod']);
$basamak = [];
if ($r['tam']) {
    $d = new DOMDocument(); libxml_use_internal_errors(true);
    @$d->loadHTML('<?xml encoding="UTF-8">' . $r['govde']); libxml_clear_errors();
    $x = new DOMXPath($d);
    foreach ($x->query('//*[@id="basamaklar"]//*[contains(@class,"is-s")]') as $k) {
        /* Kartın ilk span'i simgedir ve metni boştur; sayının etiketi
           <b>'nin hemen ardındaki span'dir. İlk span alınırsa dört
           basamak da boş anahtara düşer ve kapı sahte KALDI verir. */
        $b = $x->query('.//b', $k)->item(0); $s = $x->query('.//b/following-sibling::span[1]', $k)->item(0);
        if ($b && $s) $basamak[trim($s->textContent)] = (int)preg_replace('/\D/', '', $b->textContent);
    }
}
$beklenenAd = [];
foreach ($ARSIV_BASAMAKLARI as $__as) $beklenenAd[] = mb_strtolower(tg_asama_metni($__as, false), 'UTF-8');
$bulunanAd = array_map(fn($k) => mb_strtolower(trim($k), 'UTF-8'), array_keys($basamak));
sort($beklenenAd); sort($bulunanAd);
den("/istatistik butun basamaklari yaziyor (" . count($ARSIV_BASAMAKLARI) . ")",
    $beklenenAd === $bulunanAd, implode(',', $bulunanAd) . '  <>  ' . implode(',', $beklenenAd));
den("/istatistik basamak toplami calisma sayisina esit",
    array_sum($basamak) === count($tumu), array_sum($basamak) . ' / ' . count($tumu));
den("/istatistik 'hakem aranıyor' sayisi canli hesapla tutuyor",
    ($basamak['hakem aranıyor'] ?? -1) === $bAranan, ($basamak['hakem aranıyor'] ?? '-') . ' / ' . $bAranan);
den("/istatistik 'değerlendirmede' sayisi canli hesapla tutuyor",
    ($basamak['değerlendirmede'] ?? -1) === ($bAsama['suruyor'] ?? 0));
den("/istatistik 'hakem onaylı' sayisi canli hesapla tutuyor",
    ($basamak['hakem onaylı'] ?? -1) === ($bAsama['onayli'] ?? 0));

/* /panel/ozet: hesap gerektirir. Sayaç ölçülebilsin diye bu betik kendi
   hesabını açar ve sonunda siler. */
$eposta = 'asama-kapi-' . bin2hex(random_bytes(4)) . '@ornek.org';
$kayit = ist('/api/hesap/kayit', ['Content-Type: application/json'], 'POST',
    json_encode(['eposta' => $eposta, 'parola' => 'sinama-parola-123', 'ad' => 'Sınama Ölçer']));
$cerez = '';
foreach ($kayit['basliklar'] as $h) {
    if (stripos($h, 'Set-Cookie:') === 0 && preg_match('#Set-Cookie:\s*([^;]+)#i', $h, $m)) $cerez = trim($m[1]);
}
if ($kayit['kod'] !== 200 || $cerez === '') {
    not_('/panel/ozet olculemedi: hesap acilamadi (kod=' . $kayit['kod'] . ')');
} else {
    $oz = ist('/api/panel/ozet', ['Cookie: ' . $cerez]);
    $oj = json_decode($oz['govde'], true);
    $sc = (array)($oj['sayac'] ?? []);
    den("/panel/ozet erisilebildi", $oz['kod'] === 200 && $sc !== [], 'kod=' . $oz['kod']);
    if ($sc) {
        den("/panel/ozet 'hakemli' sayaci rapor gelmemisleri saymiyor", (int)($sc['hakemli'] ?? -1) === $bGecti,
            ($sc['hakemli'] ?? '-') . ' / ' . $bGecti);
        den("/panel/ozet 'asama_aranan' canli hesapla tutuyor", (int)($sc['asama_aranan'] ?? -1) === $bAranan,
            ($sc['asama_aranan'] ?? '-') . ' / ' . $bAranan);
        $toplamAsama = (int)($sc['asama_aranan'] ?? 0) + (int)($sc['asama_suruyor'] ?? 0) + (int)($sc['asama_onayli'] ?? 0);
        den("/panel/ozet asama sayaclari calisma sayisini asmiyor", $toplamAsama <= (int)($sc['calisma'] ?? 0));
    }
}

/* =====================================================================
   7. SÜZGEÇ BÜTÜNLÜĞÜ
   ===================================================================== */
echo "\n== 7. Suzgec butunlugu: sunucu ile kartlar ayni listeyi veriyor ==\n";
$bekSuz = ['hakemli' => 0, 'aranan' => 0, 'yazi' => 0];
foreach ($tumu as $e) { if (is_array($e)) $bekSuz[k_suz_tur($e)] = ($bekSuz[k_suz_tur($e)] ?? 0) + 1; }
$toplamKart = 0;
foreach (['hakemli', 'aranan', 'yazi'] as $t) {
    $r = sayfa('/yazilar?tur=' . $t . '&lang=tr');
    den("/yazilar?tur=$t 200 ve tam", $r['tam'], 'kod=' . $r['kod']);
    $sayim = [];
    preg_match_all('#data-tur="([a-z]+)"#', $r['govde'], $m);
    foreach ($m[1] as $v) $sayim[$v] = ($sayim[$v] ?? 0) + 1;
    $toplamKart += (int)($sayim[$t] ?? 0);
    den("/yazilar?tur=$t yalniz o turden kart basiyor", array_keys($sayim) === [$t] || $sayim === [],
        json_encode($sayim));
    den("/yazilar?tur=$t kart sayisi sunucu suzmesiyle ayni",
        (int)($sayim[$t] ?? 0) === $bekSuz[$t], ($sayim[$t] ?? 0) . ' / ' . $bekSuz[$t]);
}
den("uc suzgecin toplami butunu veriyor", $toplamKart === count($tumu), $toplamKart . ' / ' . count($tumu));
$r = sayfa('/yazilar?lang=tr');
preg_match_all('#data-tur="([a-z]+)"#', $r['govde'], $m);
den("suzgecsiz arsiv butun calismalari basiyor", count($m[1]) === count($tumu), count($m[1]) . ' / ' . count($tumu));
den("kartin data-tur degerleri uc degerle sinirli",
    array_diff(array_unique($m[1]), ['hakemli', 'aranan', 'yazi']) === []);
/* Çip sayıları da aynı kaynaktan gelmeli; JS'siz okur önce onları görür. */
$gr = gorunur($r['govde']);
den("arsiv basligindaki 'hakemli' sayisi rapor gelmemisleri saymiyor",
    (int)$say['hakemli'] === $bekSuz['hakemli'], $say['hakemli'] . ' / ' . $bekSuz['hakemli']);

/* =====================================================================
   8. DÖKÜM
   ===================================================================== */
echo "\n== 8. Dokum: asama ve rapor sayisi kalici kayitta ==\n";
foreach (['a' => 'kutadgu-ustveri.json', 'b' => 'kutadgu-tam-tumu.json'] as $katman => $dosya) {
    $j = json_decode((string)@file_get_contents(dk_yol($dosya)), true);
    $liste = (array)($j['calismalar'] ?? []);
    den("katman $katman okunabildi", $liste !== []);
    if (!$liste) continue;
    $alanEksik = 0; $tutmayan = 0;
    $ham = [];
    foreach ($tumu as $e) { if (is_array($e) && (string)($e['bcid'] ?? '') !== '') $ham[(string)$e['bcid']] = $e; }
    foreach ($liste as $k) {
        if (!array_key_exists('asama', $k) || !array_key_exists('rapor_sayisi', $k)) { $alanEksik++; continue; }
        $t = (string)($k['tamga'] ?? '');
        if (!isset($ham[$t])) continue;
        if ((string)$k['asama'] !== tg_hakem_asamasi($ham[$t])) $tutmayan++;
        elseif ((int)$k['rapor_sayisi'] !== tg_rapor_sayisi($ham[$t])) $tutmayan++;
    }
    den("katman $katman: her kayitta 'asama' ve 'rapor_sayisi' var", $alanEksik === 0, (string)$alanEksik);
    den("katman $katman: degerler canli hesapla tutuyor", $tutmayan === 0, (string)$tutmayan);
    $hedef = null;
    foreach ($liste as $k) if ((string)($k['tamga'] ?? '') === $Atamga) $hedef = $k;
    den("katman $katman: raporsuz calisma 'aranan' yaziyor", (string)($hedef['asama'] ?? '') === 'aranan');
    den("katman $katman: raporsuz calismanin 'tur' alani hala yolu yaziyor",
        (string)($hedef['tur'] ?? '') === 'hakemli');
}

/* =====================================================================
   9. TEK KAYNAK
   ===================================================================== */
echo "\n== 9. Asama metni tek kaynaktan uretiliyor ==\n";
/* Ham grep yorumları da yakalar ve kapı sahte GECTI verir; metin
   token_get_all ile ayrıştırılır ve yorumlar dışarıda bırakılır. */
$sozler = ['Hakem aranıyor', 'Seeking reviewers', 'Değerlendirmede', 'Under review'];
/* Aşama metniyle SES BENZERLİĞİ olan ama rozet olmayan kullanımlar.
   Her istisna gerekçesiyle durur; gerekçesiz istisna kapıyı boşaltır. */
$istisna = [
    'k/seo.php|Under review'      => "schema.org creativeWorkStatus değeri; okura basılan rozet değil",
    'istatistik.php|Değerlendirmede' => "basamakları anlatan düz cümle; rozet değil",
];
$dosyalar = array_merge(glob($KOD . '/*.php') ?: [], glob($KOD . '/k/*.php') ?: [], glob($KOD . '/api/*.php') ?: []);
$bulgu = [];
foreach ($dosyalar as $f) {
    $kisa = ltrim(str_replace($KOD, '', $f), '/');
    if ($kisa === 'ortak.php') continue;               /* tek kaynağın kendisi */
    $tk = @token_get_all((string)file_get_contents($f));
    if (!$tk) continue;
    foreach ($tk as $t) {
        if (!is_array($t)) continue;
        if (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) continue;
        $tur = $t[0] === T_INLINE_HTML ? 'html'
             : (in_array($t[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true) ? 'dizge' : '');
        if ($tur === '') continue;
        foreach ($sozler as $s) {
            if (mb_strpos($t[1], $s) === false) continue;
            if (isset($istisna[$kisa . '|' . $s])) continue;
            $bulgu[] = $kisa . ':' . $t[2] . ' [' . $tur . '] "' . $s . '"';
        }
    }
}
foreach ($bulgu as $b) not_('elle yazilmis asama metni: ' . $b);
den("asama metni ortak.php disinda elle yazilmamis", $bulgu === [], count($bulgu) . ' yer');

/* Davranış ölçümü: aynı çalışma için iki ayrı sayfanın bastığı aşama
   metni birbirinden farklıysa tek kaynak fiilen kırılmış demektir. */
foreach (['tr', 'en'] as $dil) {
    $en = $dil === 'en';
    $dogru = tg_asama_metni('aranan', $en, true);
    $uzun  = tg_asama_metni('aranan', $en, false);
    $ayri = [];
    foreach (['/yazilar' => 'arsiv', '/bekleyen.php' => 'bekleyen', '/' => 'ana'] as $yol => $ad) {
        $r = sayfa($yol . '?lang=' . $dil);
        foreach (bloklar($r['tam'] ? $r['govde'] : '', $Ayol) as $p) {
            if (mb_strpos($p, $dogru) === false && mb_strpos($p, $uzun) === false) $ayri[] = $ad;
        }
    }
    den("ayni calismanin asama metni butun listelerde ayni [$dil]", $ayri === [], implode(',', array_unique($ayri)));
}

/* =====================================================================
   10. YOL DENETİMLERİ BOZULMAMIŞ
   ===================================================================== */
echo "\n== 10. 'tur' hala yolu yaziyor: acma, kapatma, iki ret ==\n";
if ($ACMA < 0 || $ACMA_JETON === '') {
    not_('acma/kapatma olculemedi: hakemsiz ve hakemsiz kayitli calisma bulunamadi');
} else {
    $ac = fn(array $g) => ist('/api/yazar-hakemlige-ac', ['Content-Type: application/json'], 'POST', json_encode($g));
    $r1 = $ac(['t' => $ACMA_JETON]);
    $j1 = json_decode($r1['govde'], true);
    den("/yazar-hakemlige-ac calismayi hakemli yola aliyor",
        ($j1['ok'] ?? false) === true && (string)($j1['tur'] ?? '') === 'hakemli', (string)($j1['hata'] ?? $r1['kod']));
    $son = yazilari_oku();
    den("acildiktan sonra tur='hakemli', asama 'aranan'",
        (string)($son[$ACMA]['tur'] ?? '') === 'hakemli' && tg_hakem_asamasi($son[$ACMA]) === 'aranan');
    den("acilan calisma hakemden gecmis SAYILMIYOR", tg_hakemden_gecti($son[$ACMA]) === false);
    $r2 = $ac(['t' => $ACMA_JETON]);
    den("ikinci kez acma reddediliyor (yol denetimi 'tur'a bakiyor)", $r2['kod'] === 409, 'kod=' . $r2['kod']);
    $r3 = $ac(['t' => $ACMA_JETON, 'kapat' => 1]);
    $j3 = json_decode($r3['govde'], true);
    den("/yazar-hakemlige-ac kapatma hala calisiyor",
        ($j3['ok'] ?? false) === true && (string)($j3['tur'] ?? '') === 'yazi', (string)($j3['hata'] ?? $r3['kod']));
    $son = yazilari_oku();
    den("kapatildiktan sonra asama 'yok'", tg_hakem_asamasi($son[$ACMA]) === 'yok');
}

if ($RET < 0 || count($RET_JETON) < 2) {
    not_('iki ret donusumu olculemedi: iki hakemli uygun calisma bulunamadi');
} else {
    $raporMetin = str_repeat('Ret gerekçesi: yöntem bölümü sonucu taşımıyor. ', 12);
    foreach ($RET_JETON as $n => $t) {
        $g = http_build_query(['t' => $t, 'karar' => 'ret', 'rapor' => $raporMetin,
                               'endeks' => '[]', 'notlar' => '[]', 'sifat' => '[]']);
        $r = ist('/api/hakem-gonder', ['Content-Type: application/x-www-form-urlencoded'], 'POST', $g);
        $jr = json_decode($r['govde'], true);
        den("ret raporu " . ($n + 1) . " gonderildi", ($jr['ok'] ?? false) === true, (string)($jr['hata'] ?? $r['kod']));
    }
    $son = yazilari_oku();
    $e = $son[$RET];
    den("iki ret sonrasi tur='yazi' donusumu hala calisiyor", (string)($e['tur'] ?? '') === 'yazi',
        (string)($e['tur'] ?? ''));
    den("iki ret sonrasi ret_donusum kaydi dusuyor", is_array($e['ret_donusum'] ?? null));
    den("iki ret sonrasi raporlar yerinde duruyor", tg_rapor_sayisi($e) >= 2, (string)tg_rapor_sayisi($e));
    den("iki ret sonrasi calisma yayinda kaliyor", sayfa(tg_yazi_yolu($e) . '?lang=tr')['tam']);
    /* "Geçti mi" sorusunun cevabı EVET'tir: olumsuz geçmiştir. Sorunun
       "onaylandı mı" hâli ayrı bir işlevdir (tg_onay_durumu). */
    den("iki ret sonrasi hakemden gecmis SAYILIYOR", tg_hakemden_gecti($e) === true);
    den("iki ret sonrasi asama 'reddedildi'", tg_hakem_asamasi($e) === 'reddedildi', tg_hakem_asamasi($e));
    /* ASIL ÖLÇÜM SAYFANIN KENDİSİ. Kapı eskiden yalnızca 200 dönmesine
       bakıyordu; sayfa raporları hiç basmadan da 200 döner. */
    $sy = sayfa(tg_yazi_yolu($e) . '?lang=tr');
    $sg = (string)($sy['govde'] ?? '');
    den("sayfa 'hakem degerlendirmesinden gecmemistir' DEMIYOR",
        $sg !== '' && !str_contains($sg, 'bağımsız bir yazıdır'));
    den("sayfa ret bandini basiyor", str_contains($sg, 'MAKALE RET EDİLMİŞTİR'));
    den("sayfa hakem bolumunu basiyor", str_contains($sg, 'Hakem Değerlendirmesi'));
    den("sayfa ret gerekcesini tasiyor", str_contains($sg, 'yöntem bölümü sonucu taşımıyor'));
}

/* =====================================================================
   11. SUNUCU KÜTÜĞÜ
   ===================================================================== */
echo "\n== 11. Sunucu kutugu temiz ==\n";
if (!is_file($KUTUK)) {
    not_("sunucu kutugu bulunamadi: $KUTUK (KLOG ile verilebilir)");
} else {
    $f = fopen($KUTUK, 'r');
    fseek($f, $kutukBaslangic);
    $yeni = (string)stream_get_contents($f);
    fclose($f);
    $kotu = [];
    foreach (preg_split('/\R/', $yeni) ?: [] as $s) {
        if (preg_match('/\b(Fatal|Parse error|Uncaught|Warning|Notice|Deprecated)\b/i', $s)) $kotu[] = trim($s);
        if (preg_match('#" [5]\d\d\b#', $s)) $kotu[] = trim($s);
    }
    foreach (array_slice($kotu, 0, 6) as $s) not_('kutuk: ' . mb_substr($s, 0, 160));
    den("kutukte hata/uyari satiri yok", $kotu === [], count($kotu) . ' satir');
}

echo "\nGECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
