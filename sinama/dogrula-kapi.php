<?php
/* =====================================================================
   MAKALE SAYFASINDA "DOĞRULA": kapı ölçümü. Depoya girmez.

   Ölçülen söz şudur: okur, sayfada yazan parmak izinin metnin gerçekten
   özeti olduğunu KENDİ makinesinde denetleyebilir. Sunucunun kendi
   yazdığı bir değeri yine kendisinin onaylaması hiçbir şey göstermez;
   bu yüzden araç sunucuya "doğru mu" diye sormaz, özeti alınan ham
   kaynağı çeker ve hesabı tarayıcıya yaptırır.

   Sözün dört ayağı vardır ve biri olmadan öteki üçü yanlıştır:

     1. Değer tutar. Yayımlanan, hesaplanan ve arşiv dökümündeki sayı
        birdir; üçü ayrı düşerse okur hangisine bakacağını bilemez.
     2. Metin değişince değer değişir. Değişmiyorsa araç bir şey
        ölçmüyordur; sessiz değişikliği yakalamak varlık sebebidir.
     3. Doğrulama koşulsuzdur. Kimlik, kayıt, oturum ya da izin
        istenmez (tüzük taslağı Ek A madde 2.5). Hız sınırı geciktirir,
        reddetmez.
     4. Basılan değer okurun OKUDUĞU metni kapsar. Kapsamadığı bir
        metnin yanında "tutuyor" demek, üç ayağı da boşa çıkarır.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php dogrula-kapi.php

   Betik VERİYİ DEĞİŞTİRİR (2. ayak ölçülemeden kurulamaz). Başta yedek
   alınır, register_shutdown_function ile hata hâlinde bile geri yüklenir.
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

require_once $KOD . '/k/dokum.php';   /* ortak.php ve k/veri.php'yi de yükler */

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function not_(string $s): void { echo "  NOT    $s\n"; }

/* ---------------------------------------------------------------------
   HTTP. Uçlar IP başına sayar; her istek ayrı adresten gelmiş gibi
   gönderilir, yoksa bölümün ortasında 429 başlar. 8. bölüm sınırı
   bilerek doldurur ve orada bu davranış kapatılır.
   --------------------------------------------------------------------- */
function ist(string $yol, array $bas = [], bool $ayriIp = true): array {
    global $PORT;
    /* ÖLÇÜM TUZAĞI: dk_iz() HTTP_CF_CONNECTING_IP ya da REMOTE_ADDR okur;
       X-Forwarded-For'a HİÇ bakmaz. Başlık yanlış seçilirse bütün istekler
       tek bir kovaya düşer, 2. bölümün ortasında 429 başlar ve sonraki
       bölümler ölçmediği hâlde KALDI verir. */
    if ($ayriIp) $bas[] = 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250);
    $ctx = stream_context_create(['http' => [
        'method' => 'GET', 'header' => implode("\r\n", $bas),
        'ignore_errors' => true, 'timeout' => 40,
    ]]);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, $ctx);
    $h = $http_response_header ?? [];
    $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g];
}
function bas_(array $h, string $ad): string {
    foreach ($h as $s) if (stripos($s, $ad . ':') === 0) return trim(substr($s, strlen($ad) + 1));
    return '';
}
/* Sayfa 500 verirse gövde BOŞ değil KIRPILMIŞ gelir; kapanış ayrıca ölçülür. */
function sayfa(string $yol): array {
    $r = ist($yol);
    $r['tam'] = ($r['kod'] === 200) && (strpos(substr($r['govde'], -400), '</html>') !== false);
    return $r;
}
function json_uc(string $yol): array {
    $r = ist($yol);
    $r['j'] = json_decode($r['govde'], true);
    if (!is_array($r['j'])) $r['j'] = null;
    return $r;
}
/* Sayfadaki yayımlanan değer: okurun gördüğü yerden okunur. */
function sayfa_degeri(string $html): string {
    return preg_match('#id="piYayin"[^>]*>([0-9a-f]{8,})<#i', $html, $m) ? strtolower($m[1]) : '';
}
function nitelik(string $html, string $ad): string {
    return preg_match('#id="piArac"[^>]*?\b' . preg_quote($ad, '#') . '="([^"]*)"#is', $html, $m) ? $m[1] : '';
}

/* ---------------------------------------------------------------------
   Yedek. Kapı 2. ve 5. bölümde metni değiştirir.
   --------------------------------------------------------------------- */
$YZ = $VERI . '/yazilar.json';
$yedek = @file_get_contents($YZ);
if ($yedek === false) { fwrite(STDERR, "yazilar.json okunamadı.\n"); exit(2); }
/* Sayaç dosyası dk_yol('hiz.json'), yani DÖKÜM dizinindedir; api/index.php'nin
   ayrı hiz-sinir.json'u değil. Yanlış dosyayı sıfırlamak "süre dolunca aynı
   kayıt verilir" sözünü ölçmüş gibi gösterip ölçmez. */
$HS = $VERI . '/dokum/hiz.json';
$hsVardi = is_file($HS);
$hsYedek = $hsVardi ? (string)@file_get_contents($HS) : '';
register_shutdown_function(function () use ($YZ, $yedek, $HS, $hsVardi, $hsYedek): void {
    @file_put_contents($YZ, $yedek);
    if ($hsVardi) @file_put_contents($HS, $hsYedek); else @unlink($HS);
});
function yazilari_oku(): array { global $YZ; $y = json_decode((string)@file_get_contents($YZ), true); return is_array($y) ? $y : []; }
function yazilari_yaz(array $y): void { global $YZ; @file_put_contents($YZ, json_encode($y, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); }

$yazilar = yazilari_oku();
if (!$yazilar) { fwrite(STDERR, "yazilar.json boş.\n"); exit(2); }

/* Ölçüm öznesi: tamgası olan ilk çalışma. */
$D = null; $Di = -1;
foreach ($yazilar as $i => $w) {
    if (is_array($w) && trim((string)($w['bcid'] ?? '')) !== '') { $D = $w; $Di = $i; break; }
}
if ($D === null) { fwrite(STDERR, "tamgalı çalışma yok.\n"); exit(2); }
$TAMGA = (string)$D['bcid'];
$YOL   = '/tamga/' . rawurlencode($TAMGA);

echo "== 0. Ölçüm öznesi ==\n";
not_("tamga: $TAMGA  ·  kayıt: " . (string)($D['id'] ?? '?') . "  ·  dil: " . (string)($D['dil'] ?? '?'));

/* =====================================================================
   1. UÇ VAR VE KOŞULSUZ AÇIK
   Değişmez ilke: arşive erişim kimlik, kayıt, üyelik, onay ya da bedel
   şartına bağlanamaz. Doğrulama arşivin parçasıdır; oturum istemesi
   bu ilkeyi deler.
   ===================================================================== */
echo "\n== 1. Uç var ve koşulsuz açık ==\n";
$u = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
den('/api/parmak 200 veriyor', $u['kod'] === 200, (string)$u['kod']);
den('yanıt JSON ve ok=true', ($u['j']['ok'] ?? null) === true);
den('  tamga geri yazılıyor', (string)($u['j']['tamga'] ?? '') === $TAMGA);
den('  parmak_izi alanı var', preg_match('/^[0-9a-f]{32}$/', (string)($u['j']['parmak_izi'] ?? '')) === 1, (string)($u['j']['parmak_izi'] ?? ''));
den('  yontem alanı var', trim((string)($u['j']['yontem'] ?? '')) !== '');
den('  kaynak dört alanın dördünü de veriyor',
    is_array($u['j']['kaynak'] ?? null)
    && array_keys($u['j']['kaynak']) === ['baslik', 'ozet', 'metin', 'kaynakca'],
    implode(',', array_keys((array)($u['j']['kaynak'] ?? []))));
den('  arşiv dökümüne yol gösteriyor', trim((string)($u['j']['dokum'] ?? '')) !== '');
den('oturum çerezi göndermeyen istek de 200 alıyor',
    ist('/api/parmak?tamga=' . rawurlencode($TAMGA), ['Cookie: '])['kod'] === 200);
den('başka kökenden okunabilir (CORS açık)',
    bas_($u['basliklar'], 'Access-Control-Allow-Origin') === '*',
    bas_($u['basliklar'], 'Access-Control-Allow-Origin'));
den('yanıt JSON içerik türüyle geliyor',
    stripos(bas_($u['basliklar'], 'Content-Type'), 'json') !== false,
    bas_($u['basliklar'], 'Content-Type'));

/* Paylaşılmış hiçbir kimlik doğrulama dışında kalmasın. */
$eskiKodlu = null;
foreach ($yazilar as $w) {
    foreach ((array)($w['eski_kod'] ?? []) as $e) {
        if (trim((string)$e) !== '') { $eskiKodlu = [$w, (string)$e]; break 2; }
    }
}
if ($eskiKodlu === null) { not_('eski kodlu kayıt yok, eski kimlik ölçülemedi'); }
else {
    $e = json_uc('/api/parmak?tamga=' . rawurlencode($eskiKodlu[1]));
    den('eski kodla da bulunuyor', ($e['j']['ok'] ?? null) === true, $eskiKodlu[1]);
    den('  ve bugünkü tamgayı döndürüyor',
        (string)($e['j']['tamga'] ?? '') === (string)$eskiKodlu[0]['bcid']);
}
$yok = json_uc('/api/parmak?tamga=YOK-0000-00000-0');
den('bilinmeyen kimlik 404 ve iki dilde gerekçe',
    $yok['kod'] === 404 && ($yok['j']['ok'] ?? null) === false
    && trim((string)($yok['j']['hata'] ?? '')) !== '' && trim((string)($yok['j']['hata_en'] ?? '')) !== '');
den('boş kimlik de 404, sessiz kalınmıyor', json_uc('/api/parmak?tamga=')['kod'] === 404);

/* =====================================================================
   2. DEĞER TUTUYOR: DÖRT BAĞIMSIZ YOL
   Sayı dört ayrı yerde üretiliyor. Kapı, dördünü de kendi yolundan
   okur ve karşılaştırır; birini ötekinden türetirse hiçbir şey ölçmez.
   ===================================================================== */
echo "\n== 2. Değer tutuyor: dört bağımsız yol ==\n";
$ucDeger = strtolower((string)($u['j']['parmak_izi'] ?? ''));

/* (a) Uçtan gelen ham kaynaktan, tg_metin_ozeti() ÇAĞRILMADAN. Okurun
       yaptığı hesabın ta kendisi; işlevi çağırmak kendini doğrulamak
       olurdu. */
$k = (array)($u['j']['kaynak'] ?? []);
$kaynakDizgesi = (string)($k['baslik'] ?? '') . "\n" . (string)($k['ozet'] ?? '') . "\n"
               . (string)($k['metin'] ?? '') . "\n" . (string)($k['kaynakca'] ?? '');
$elde = substr(hash('sha256', $kaynakDizgesi), 0, 32);
den('(a) uçtaki kaynaktan elde hesaplanan değer uçtaki değere eşit', $elde === $ucDeger, "$elde vs $ucDeger");

/* (b) Sayfanın okura gösterdiği değer. */
$sTr = sayfa($YOL . '?lang=tr');
den('makale sayfası tam geliyor (tr)', $sTr['tam'], (string)$sTr['kod']);
$sayfaDeger = sayfa_degeri($sTr['govde']);
den('(b) sayfada basılan değer uçtaki değere eşit', $sayfaDeger === $ucDeger, "$sayfaDeger vs $ucDeger");

/* (c) Arşiv dökümündeki liste: başkalarının elinde bulunabilen dosya.
       Asıl güvence budur; sunucu tek başına kalmasın diye vardır. */
$liste = ist('/dokum.php?d=kutadgu-parmak-izleri.txt');
den('parmak izi listesi yayımlanıyor', $liste['kod'] === 200, (string)$liste['kod']);
$listeDeger = '';
foreach (explode("\n", $liste['govde']) as $satir) {
    if ($satir === '' || $satir[0] === '#') continue;
    $p = preg_split('/\s+/', trim($satir));
    if (isset($p[1]) && strcasecmp($p[1], $TAMGA) === 0) { $listeDeger = strtolower($p[0]); break; }
}
den('(c) listede bu çalışmanın satırı var', $listeDeger !== '');
den('(c) listedeki değer uçtaki değere eşit', $listeDeger === $ucDeger, "$listeDeger vs $ucDeger");

/* (d) Arşiv dökümünün JSON katmanı. */
$dokumDeger = '';
foreach (['kutadgu-tam-tumu.json', 'kutadgu-kayit.json'] as $ad) {
    $f = $VERI . '/dokum/' . $ad;
    if (!is_file($f)) continue;
    $j = json_decode((string)@file_get_contents($f), true);
    $bulundu = false;
    foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator((array)$j), RecursiveIteratorIterator::SELF_FIRST) as $dugum) {
        if (is_array($dugum) && (string)($dugum['tamga'] ?? $dugum['bcid'] ?? '') === $TAMGA
            && isset($dugum['parmak_izi'])) { $dokumDeger = strtolower((string)$dugum['parmak_izi']); $bulundu = true; break; }
    }
    if ($bulundu) break;
}
if ($dokumDeger === '') not_('döküm JSON katmanında parmak izi bulunamadı (döküm üretilmemiş olabilir)');
else den('(d) döküm JSON katmanındaki değer de eşit', $dokumDeger === $ucDeger, "$dokumDeger vs $ucDeger");

/* Bağımsız uygulama: aynı hesap başka bir dilde. Aynı kodun iki kez
   koşması bir doğrulama değildir. */
$nd = trim((string)@shell_exec('command -v node 2>/dev/null'));
if ($nd === '') not_('node yok, bağımsız uygulama ölçülemedi');
else {
    $tmp = sys_get_temp_dir() . '/dogrula-capraz-' . getmypid() . '.js';
    file_put_contents($tmp, <<<'JS'
const c = require('crypto');
let g = '';
process.stdin.on('data', d => g += d).on('end', () => {
  const j = JSON.parse(g), k = j.kaynak || {};
  const s = String(k.baslik ?? '') + '\n' + String(k.ozet ?? '') + '\n'
          + String(k.metin ?? '') + '\n' + String(k.kaynakca ?? '');
  process.stdout.write(c.createHash('sha256').update(Buffer.from(s, 'utf8')).digest('hex').slice(0, 32));
});
JS);
    $ph = proc_open('node ' . escapeshellarg($tmp), [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $bp);
    fwrite($bp[0], $u['govde']); fclose($bp[0]);
    $nodeDeger = strtolower(trim((string)stream_get_contents($bp[1])));
    fclose($bp[1]); fclose($bp[2]); proc_close($ph); @unlink($tmp);
    den('(e) bağımsız uygulama (node) aynı değeri veriyor', $nodeDeger === $ucDeger, "$nodeDeger vs $ucDeger");
}

/* =====================================================================
   3. YÖNTEM CÜMLESİ YAPILAN İŞİN TARİFİDİR
   Cümle ile kod ayrı düşerse okur yanlış hesap yapar ve sistemin
   yalan söylediğini sanır. Cümle burada ayrıştırılıp sınanır.
   ===================================================================== */
echo "\n== 3. Yöntem cümlesi yapılan işin tarifi ==\n";
$yontem = (string)($u['j']['yontem'] ?? '');
den('cümle sha256 diyor ve hesap sha256', stripos($yontem, 'sha256') !== false);
den('  cümle UTF-8 diyor ve kaynak UTF-8',
    stripos($yontem, 'utf-8') !== false && mb_check_encoding($kaynakDizgesi, 'UTF-8'));
den('  cümledeki hane sayısı basılan değerin uzunluğu',
    preg_match('/(\d+)\s*hane/u', $yontem, $m) === 1 && (int)$m[1] === strlen($ucDeger),
    $yontem);
/* Alanların cümlede geçtiği sıra, dizgede birleştirildikleri sıradır.
   Sıra kayarsa aynı metin başka bir sayı verir. */
$sira = [];
foreach (['baslik', 'ozet', 'metin', 'kaynakca'] as $a) {
    $p = stripos($yontem, $a);
    if ($p !== false) $sira[$a] = $p;
}
asort($sira);
den('  cümledeki alan sırası dizgedeki sıra', array_keys($sira) === ['baslik', 'ozet', 'metin', 'kaynakca'], implode(',', array_keys($sira)));
den('  cümle ayracı LF diyor ve ayraç LF',
    stripos($yontem, 'lf') !== false && substr_count($kaynakDizgesi, "\n") >= 3);
/* Cümlenin tarif ettiği hesap, alanlar boşken de aynı sonucu verir mi:
   ayraçların sayısı doğru mu (üç LF, sona LF eklenmiyor). */
$bosDizge = "" . "\n" . "" . "\n" . "" . "\n" . "";
den('  boş dört alan üç LF verir, sona LF eklenmez', $bosDizge === "\n\n\n" && substr($kaynakDizgesi, -1) !== "\n" || substr((string)($k['kaynakca'] ?? ''), -1) === "\n",
    'kaynakça kendi satır sonuyla bitiyorsa bu ölçüm kaynakçanın kendisini görür');

/* =====================================================================
   4. SAYFA: OKUR NE GÖRÜYOR
   ===================================================================== */
echo "\n== 4. Sayfa: okur ne görüyor ==\n";
$sEn = sayfa($YOL . '?lang=en');
den('makale sayfası tam geliyor (en)', $sEn['tam'], (string)$sEn['kod']);
foreach (['tr' => $sTr, 'en' => $sEn] as $dil => $s) {
    den("[$dil] parmak izi değeri basılıyor", sayfa_degeri($s['govde']) !== '');
    den("[$dil] Doğrula düğmesi basılıyor", preg_match('#id="piDugme"#', $s['govde']) === 1);
    den("[$dil] çıktı kabı basılı ve başlangıçta gizli",
        preg_match('#id="piCikti"[^>]*\bhidden\b#i', $s['govde']) === 1);
    den("[$dil] düğme ile değer TEK kabın içinde",
        preg_match('#id="piArac".*?id="piYayin".*?id="piDugme"#s', $s['govde']) === 1);
    den("[$dil] uç adresi kaptan okunuyor",
        strpos(nitelik($s['govde'], 'data-uc'), '/api/parmak') === 0, nitelik($s['govde'], 'data-uc'));
    den("[$dil] indirilecek dosyanın adı tamgayı taşıyor",
        strpos(nitelik($s['govde'], 'data-dosya'), preg_replace('/[^A-Za-z0-9.\-]/', '', $TAMGA)) !== false,
        nitelik($s['govde'], 'data-dosya'));
    den("[$dil] dar ekran yuvası var (araç makale akışına inebilsin)",
        preg_match('#id="piYuva"#', $s['govde']) === 1);
}
/* Düğmenin ve etiketin kendi dilinde olması. Bir dilde eksik kalan
   metin, o dildeki okuru aracın olmadığına inandırır. */
den('[tr] düğme Türkçe', preg_match('#id="piDugme"[^>]*>\s*Doğrula\s*<#u', $sTr['govde']) === 1);
den('[en] düğme İngilizce', preg_match('#id="piDugme"[^>]*>\s*Verify\s*<#', $sEn['govde']) === 1);
den('[tr] araç metinleri Türkçe sözlükten geliyor', strpos($sTr['govde'], 'Hesaplanan') !== false || strpos($sTr['govde'], 'hesaplanan') !== false);
den('[en] araç metinlerinde Türkçe sızıntı yok',
    preg_match('#var M = (\{.*?\});#s', $sEn['govde'], $m) === 1
    && preg_match('/[çğışöüÇĞİŞÖÜ]/u', $m[1]) === 0,
    'sözlük: ' . mb_substr((string)($m[1] ?? '(bulunamadı)'), 0, 120));
/* Araç sayfada tek olmalı: ikinci bir kap, taşınan düğmenin
   karşılaştırdığı değeri arkasında bırakması demektir. */
den('sayfada tek #piArac var', substr_count($sTr['govde'], 'id="piArac"') === 1, (string)substr_count($sTr['govde'], 'id="piArac"'));
den('sayfada tek #piYayin var', substr_count($sTr['govde'], 'id="piYayin"') === 1);
/* Değer basılırken kaçırılıyor mu: ham HTML olarak basılsaydı kayıt
   üzerinden sayfaya kod sokulabilirdi. */
den('değer <code> içinde ve yalnız onaltılık', preg_match('#id="piYayin"><code|<code id="piYayin"#', $sTr['govde']) === 1
    || preg_match('#id="piYayin"[^>]*>[0-9a-f]+<#', $sTr['govde']) === 1);
/* Basılı belgede düğme olmamalı; kâğıtta çalışmayan bir düğme okura
   sistemin bozuk olduğunu düşündürür. */
den('araç baskıda gizli (no-print)', preg_match('#class="pi-arac no-print"#', $sTr['govde']) === 1);

/* Tamgası olmayan kayıt: düğme basılmaz ama değer basılır. */
$tamgasiz = null;
foreach ($yazilar as $w) if (is_array($w) && trim((string)($w['bcid'] ?? '')) === '') { $tamgasiz = $w; break; }
if ($tamgasiz === null) not_('tamgasız kayıt yok; "düğmesiz ama değerli" hâli veriyle ölçülemedi');
else {
    $t = sayfa('/yazi.php?y=' . rawurlencode((string)($tamgasiz['slug'] ?? '')) . '&lang=tr');
    den('tamgasız kayıtta değer yine basılıyor', sayfa_degeri($t['govde']) !== '');
    den('tamgasız kayıtta düğme basılmıyor', strpos($t['govde'], 'id="piDugme"') === false);
}

/* =====================================================================
   5. METİN DEĞİŞİNCE DEĞER DEĞİŞİR
   Aracın varlık sebebi. Değişmiyorsa 1-4 arasındaki her şey süstür.
   ===================================================================== */
echo "\n== 5. Metin değişince değer değişir ==\n";
$onceki = $ucDeger;
foreach ([
    'baslik'   => 'başlığa eklenen tek karakter',
    'ozet'     => 'özete eklenen tek karakter',
    'metin'    => 'gövdeye eklenen tek karakter',
    'kaynakca' => 'kaynakçaya eklenen tek karakter',
] as $alan => $ad) {
    $y = yazilari_oku();
    $y[$Di][$alan] = (string)($D[$alan] ?? '') . '.';
    yazilari_yaz($y);
    $r = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
    $yeni = strtolower((string)($r['j']['parmak_izi'] ?? ''));
    den("$ad değeri değiştiriyor", $yeni !== '' && $yeni !== $onceki, "$onceki -> $yeni");
    $s = sayfa($YOL . '?lang=tr');
    den("  ve sayfa yeni değeri basıyor", sayfa_degeri($s['govde']) === $yeni, sayfa_degeri($s['govde']) . " vs $yeni");
    yazilari_yaz($yazilar);
}
/* Geri alınınca eski değer geri gelir: hesap kayıttan başka bir şeye
   (tarihe, sayaca, oturuma) bakmıyor. */
$r = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
den('metin geri alınınca değer de geri geliyor', strtolower((string)($r['j']['parmak_izi'] ?? '')) === $onceki);
/* Kayıttaki başka bir alanın değişmesi değeri DEĞİŞTİRMEMELİ: parmak
   izi metnin özetidir, kaydın değil. Okunma sayacı arttıkça değer
   değişseydi her ziyaret sahte bir "metin değişti" üretirdi. */
$y = yazilari_oku();
$y[$Di]['anahtar'] = 'sinama, degisiklik';
yazilari_yaz($y);
$r = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
den('metin dışı alan (anahtar) değeri değiştirmiyor', strtolower((string)($r['j']['parmak_izi'] ?? '')) === $onceki);
yazilari_yaz($yazilar);

/* =====================================================================
   6. KAYNAK BİREBİR VERİLİYOR
   Uçtan gelen kaynak kayıttaki alanların ta kendisidir. Tek bir
   karakteri "düzeltmek" (kırpmak, boşluk sadeleştirmek, HTML kaçırmak)
   doğru bir metni okurun gözünde yanlış gösterir.
   ===================================================================== */
echo "\n== 6. Kaynak birebir veriliyor ==\n";
$y = yazilari_oku();
$tuzak = "  başta ve sonda boşluk  \n\ttab\r\nCRLF & <b>etiket</b> \"tırnak\" ";
$y[$Di]['ozet'] = $tuzak;
yazilari_yaz($y);
$r = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
$gelen = (string)($r['j']['kaynak']['ozet'] ?? '');
den('baştaki ve sondaki boşluk kırpılmıyor', $gelen === $tuzak, json_encode($gelen, JSON_UNESCAPED_UNICODE));
den('  ve o kaynaktan hesaplanan değer uçtakine eşit',
    substr(hash('sha256',
        (string)($r['j']['kaynak']['baslik'] ?? '') . "\n" . $gelen . "\n"
        . (string)($r['j']['kaynak']['metin'] ?? '') . "\n" . (string)($r['j']['kaynak']['kaynakca'] ?? '')), 0, 32)
    === strtolower((string)($r['j']['parmak_izi'] ?? '')));
yazilari_yaz($yazilar);

/* =====================================================================
   7. GERİ ÇEKİLMİŞ ÇALIŞMA DA DOĞRULANABİLİR
   Kayıt silinmez. Geri çekilmiş bir metnin ne olduğunun
   denetlenebilmesi, yayındayken denetlenebilmesinden az önemli değildir.
   ===================================================================== */
echo "\n== 7. Geri çekilmiş çalışma da doğrulanabilir ==\n";
$y = yazilari_oku();
$y[$Di]['geri_cekildi'] = ['tarih' => '2026-01-01', 'gerekce' => 'sınama'];
yazilari_yaz($y);
$r = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
den('geri çekilmiş kayıt uçta 200 veriyor', $r['kod'] === 200 && ($r['j']['ok'] ?? null) === true, (string)$r['kod']);
den('  ve değeri değişmemiş', strtolower((string)($r['j']['parmak_izi'] ?? '')) === $onceki);
$s = sayfa($YOL . '?lang=tr');
den('  ve sayfasında araç yine duruyor', strpos($s['govde'], 'id="piDugme"') !== false);
yazilari_yaz($yazilar);

/* =====================================================================
   8. HIZ SINIRI BİR REDDETME DEĞİLDİR
   Teknik önlem erişimi geciktirebilir, hiçbir durumda reddedemez.
   ===================================================================== */
echo "\n== 8. Hız sınırı bir reddetme değildir ==\n";
$ip = ['CF-Connecting-IP: 10.9.9.9'];
$son = null; $vurdu = false;
for ($i = 0; $i < 200; $i++) {
    $son = ist('/api/parmak?tamga=' . rawurlencode($TAMGA), $ip, false);
    if ($son['kod'] === 429) { $vurdu = true; break; }
}
if (!$vurdu) { not_('hız sınırına 200 istekte varılamadı; 8. bölüm ölçülemedi'); }
else {
    $j = json_decode($son['govde'], true);
    den('sınır 429 ile karşılıyor', $son['kod'] === 429);
    den('  Retry-After başlığı var ve sayı', (int)bas_($son['basliklar'], 'Retry-After') > 0, bas_($son['basliklar'], 'Retry-After'));
    den('  gövdede bekleme süresi yazılı', (int)($j['bekle'] ?? 0) > 0, (string)($j['bekle'] ?? ''));
    den('  bekleme on dakikayı aşmıyor', (int)($j['bekle'] ?? 99999) <= 600, (string)($j['bekle'] ?? ''));
    den('  gerekçe iki dilde', trim((string)($j['hata'] ?? '')) !== '' && trim((string)($j['hata_en'] ?? '')) !== '');
    den('  metin bunun bir reddetme OLMADIĞINI söylüyor',
        stripos((string)($j['hata'] ?? ''), 'reddetme değildir') !== false
        && stripos((string)($j['hata_en'] ?? ''), 'not a refusal') !== false,
        (string)($j['hata'] ?? ''));
    den('  başka bir adres aynı anda etkilenmiyor',
        ist('/api/parmak?tamga=' . rawurlencode($TAMGA), ['CF-Connecting-IP: 10.8.8.8'], false)['kod'] === 200);
    /* Beklendiğinde aynı kayıt koşulsuz verilir: sayaç sıfırlanınca
       aynı adres aynı yanıtı alır. Bekleme süresince beklemek yerine
       sayaç sıfırlanır; ölçülen söz "sonra verilir"dir. */
    @file_put_contents($HS, '{}');
    $g = json_uc('/api/parmak?tamga=' . rawurlencode($TAMGA));
    den('  süre dolunca AYNI kayıt koşulsuz veriliyor',
        $g['kod'] === 200 && strtolower((string)($g['j']['parmak_izi'] ?? '')) === $onceki);
}

/* =====================================================================
   9. BASILAN DEĞER OKURUN OKUDUĞU METNİ KAPSIYOR MU

   tg_metin_ozeti() yalnızca baslik/ozet/metin/kaynakca alanlarını
   özetler. Sayfa ise ?lang=en verildiğinde baslik_en/ozet_en/metin_en
   alanlarını basar. İkisi ayrı düştüğünde okur, OKUMADIĞI bir metnin
   özetini doğrulamış olur ve "tutuyor" yanıtı yanlış bir güven verir.

   Bölüm iki yönü birden ölçer, çünkü yalnız birincisi ölçülseydi kapı,
   arşivde yayımlanmış değeri geriye dönük değiştirerek de geçilirdi.
   Elinde eski sayıyı tutan okur o gün olmayan bir değişiklik görürdü;
   bir güven aracının verebileceği en kötü yanıt budur.
     A. Sayfada basılan değer O SAYFADAKİ metni kapsar.
     B. Arşivde yayımlanan değer DEĞİŞMEZ ve okura ulaşmayı sürdürür.
   ===================================================================== */
echo "\n== 9. Basılan değer okurun okuduğu metni kapsıyor mu ==\n";
$cevirili = null; $ci = -1;
foreach ($yazilar as $i => $w) {
    if (!is_array($w) || trim((string)($w['bcid'] ?? '')) === '') continue;
    if (trim(tg_metin($w['metin_en'] ?? '')) !== '' && trim(tg_metin($w['baslik_en'] ?? '')) !== '') { $cevirili = $w; $ci = $i; break; }
}
if ($cevirili === null) { not_('çevirisi olan tamgalı kayıt yok; 9. bölüm ölçülemedi'); }
else {
    $ct   = (string)$cevirili['bcid'];
    $cYol = '/tamga/' . rawurlencode($ct);
    $cUc  = '/api/parmak?tamga=' . rawurlencode($ct);

    $j0     = json_uc($cUc);
    $taban0 = strtolower((string)($j0['j']['parmak_izi'] ?? ''));
    $trS0   = sayfa($cYol . '?lang=tr');
    $enS0   = sayfa($cYol . '?lang=en');
    $tr0    = sayfa_degeri($trS0['govde']);
    $en0    = sayfa_degeri($enS0['govde']);

    den('Türkçe sayfanın değeri arşivde yayımlanan değerdir', $tr0 === $taban0 && $tr0 !== '', "$tr0 vs $taban0");
    den('çevirisi olan çalışmada uç ayrı bir çeviri özeti veriyor',
        is_array($j0['j']['ceviri'] ?? null)
        && preg_match('/^[0-9a-f]{32}$/', (string)($j0['j']['ceviri']['parmak_izi'] ?? '')) === 1,
        json_encode($j0['j']['ceviri'] ?? null) === 'null' ? 'ceviri: null' : '');
    den('  ve İngilizce sayfa o değeri basıyor', $en0 !== '' && $en0 === strtolower((string)($j0['j']['ceviri']['parmak_izi'] ?? '-')), "$en0");
    den('  iki değer birbirinden ayrı', $en0 !== $tr0, "$en0 vs $tr0");
    /* Okurun eline verilen kaynak, basılan değerin ta kendisini
       vermeli; araç o kaynağı çekip tarayıcıda özetliyor. */
    $ck = (array)($j0['j']['ceviri']['kaynak'] ?? []);
    den('  çeviri kaynağı dört alanı da veriyor', array_keys($ck) === ['baslik', 'ozet', 'metin', 'kaynakca'], implode(',', array_keys($ck)));
    den('  o kaynaktan elde hesaplanan değer İngilizce sayfadakine eşit',
        substr(hash('sha256', (string)($ck['baslik'] ?? '') . "\n" . (string)($ck['ozet'] ?? '') . "\n"
                             . (string)($ck['metin'] ?? '') . "\n" . (string)($ck['kaynakca'] ?? '')), 0, 32) === $en0);

    /* ---- A. Çeviri sessizce değişince İngilizce sayfanın değeri değişir ---- */
    $y = yazilari_oku();
    $y[$ci]['baslik_en'] = 'Silently changed English title';
    $y[$ci]['metin_en']  = tg_metin($cevirili['metin_en'] ?? '') . '<p>Silently inserted paragraph.</p>';
    yazilari_yaz($y);
    $enS1 = sayfa($cYol . '?lang=en');
    $trS1 = sayfa($cYol . '?lang=tr');
    den('İngilizce sayfa değişen çeviriyi basıyor', strpos($enS1['govde'], 'Silently changed English title') !== false);
    den('  ve o sayfadaki parmak izi DEĞİŞİYOR', sayfa_degeri($enS1['govde']) !== $en0 && sayfa_degeri($enS1['govde']) !== '',
        $en0 . ' -> ' . sayfa_degeri($enS1['govde']));
    den('  Türkçe sayfanın değeri ise DEĞİŞMİYOR (çeviri onun metni değil)', sayfa_degeri($trS1['govde']) === $tr0);
    yazilari_yaz($yazilar);

    /* Ters yön: taban metin değişince İngilizce sayfanın değeri de
       değişmeli, çünkü çevirisi olmayan alanlar oradan gelir. */
    $y = yazilari_oku();
    $y[$ci]['kaynakca'] = (string)($cevirili['kaynakca'] ?? '') . "\nSessiz kaynak.";
    yazilari_yaz($y);
    den('taban metin değişince İngilizce sayfanın değeri de değişiyor',
        sayfa_degeri(sayfa($cYol . '?lang=en')['govde']) !== $en0);
    yazilari_yaz($yazilar);

    /* ---- B. Arşivde yayımlanan değer okura ulaşmayı sürdürüyor ---- */
    den('İngilizce sayfa arşivdeki değeri de basıyor (başka ellerdeki listeyle bağ)',
        preg_match('#id="piArsiv"[^>]*>([0-9a-f]{32})<#i', $enS0['govde'], $mm) === 1 && strtolower($mm[1]) === $taban0,
        (string)($mm[1] ?? 'basılmıyor'));
    den('  ve hangi metnin doğrulandığını yazıyor',
        strpos($enS0['govde'], 'ceviriAck') !== false || stripos($enS0['govde'], 'the English translation') !== false);
    den('Türkçe sayfada ikinci satır basılmıyor (eşit iki sayı gürültüdür)',
        strpos($trS0['govde'], 'id="piArsiv"') === false);
    /* Yayımlanmış değer geriye dönük değişmedi: dökümdeki liste taban
       değeri veriyor, çeviri özeti onun yanına yazıldı. */
    $l = ist('/dokum.php?d=kutadgu-parmak-izleri.txt');
    $ld = '';
    foreach (explode("\n", $l['govde']) as $satir) {
        if ($satir === '' || $satir[0] === '#') continue;
        $pp = preg_split('/\s+/', trim($satir));
        if (isset($pp[1]) && strcasecmp($pp[1], $ct) === 0) { $ld = strtolower($pp[0]); break; }
    }
    den('dökümdeki liste değeri hâlâ TABAN değer (geriye dönük değişmedi)', $ld === $taban0, "$ld vs $taban0");

    /* Çevirisi olmayan çalışmada ikinci değer hiç üretilmez. */
    /* Sınama verisinde her kaydın çevirisi var; hâl kurulmadan
       ölçülemez. Çeviri alanları geçici olarak boşaltılır ve sonunda
       geri yüklenir (yedek zaten en başta alındı). */
    $y = yazilari_oku();
    foreach (['baslik_en', 'ozet_en', 'metin_en', 'kaynakca_en'] as $a) $y[$ci][$a] = '';
    yazilari_yaz($y);
    {
        $z  = $ct;
        $zj = json_uc('/api/parmak?tamga=' . rawurlencode($z));
        $zs = sayfa('/tamga/' . rawurlencode($z) . '?lang=en');
        den('çevirisiz çalışmada uç ceviri alanını boş bırakıyor', ($zj['j']['ceviri'] ?? null) === null);
        den('  ve İngilizce sayfasında ikinci satır basılmıyor', strpos($zs['govde'], 'id="piArsiv"') === false);
        den('  ve değeri taban değere eşit',
            sayfa_degeri($zs['govde']) === strtolower((string)($zj['j']['parmak_izi'] ?? '-')));
    }
    yazilari_yaz($yazilar);
}

/* =====================================================================
   10. SUNUCU KÜTÜĞÜ
   ===================================================================== */
echo "\n== 10. Sunucu kütüğü ==\n";
if (!is_file($KUTUK)) { not_('kütük dosyası yok: ' . $KUTUK); }
else {
    $son = (string)@shell_exec('tail -n 400 ' . escapeshellarg($KUTUK) . ' 2>/dev/null');
    $u2 = 0;
    foreach (explode("\n", $son) as $s) if (preg_match('/warning|deprecated|notice|fatal/i', $s)) $u2++;
    den('son 400 satırda PHP uyarısı yok', $u2 === 0, (string)$u2 . ' satır');
}

echo "\n" . str_repeat('-', 40) . "\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
