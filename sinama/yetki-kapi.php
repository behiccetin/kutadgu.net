<?php
/* =====================================================================
   İLETİ GİZLİLİĞİ VE ATAMANIN DOĞRULAMASI · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   İKİ KURUL BİLDİRİMİ, 19 Ağustos 2026:

   (1) "editörler siteden gelen mesajları görmesin, baş editörler
       görecek."
       ÖLÇÜLEN KUSUR: yazma uçları (yanıt, istenmeyen, sil, kapat) baş
       editör yetkisi arıyordu ama OKUMA uçları (liste, oku) sıradan
       editöre açıktı. Bir editör yanıt veremiyor, ama herkesin yazdığı
       her şeyi okuyabiliyordu. Yetkilendirmenin en sık yapılan yanlışı
       budur: EYLEM korunur, VERİ korunmaz. Oysa iletişim kutusuna yazan
       kişi bir kurula değil sorumluya yazdığını sanır; içinde bir
       şikâyet ya da bir hakem hakkında söz olabilir.

   (2) "baş editörler de hakem atayabilir editörler de; onların atadıkları
       hakemler otomatik asgari dr kabul edilir, sorumluluğu atayan
       kişiye aittir."
       ÖLÇÜLEN KUSUR: kural 14 Ağustos'ta konmuştu ve
       tg_dogrulama_editor() 'atama' yolunu tanıyordu, ama o yol HİÇBİR
       YERDEN çağrılmıyordu. Hesap daveti doktorayı doğruluyor, çalışmaya
       hakem ATAMA doğrulamıyordu: sistem kuralın yarısını uyguluyordu.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php yetki-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* İki ayrı oturum: biri sıradan editör, biri baş editör. */
$KZ = ['ed' => '', 'bas' => ''];
$IP = '203.0.113.' . random_int(1, 254);
function yist(string $kim, string $yol, ?array $g = null): array {
    global $KZ, $IP;
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nX-Forwarded-For: $IP\r\n";
    if ($KZ[$kim] !== '') $bas .= 'Cookie: ' . $KZ[$kim] . "\r\n";
    $se = ['method' => $g === null ? 'GET' : 'POST', 'header' => $bas, 'timeout' => 20, 'ignore_errors' => true];
    if ($g !== null) $se['content'] = json_encode($g, JSON_UNESCAPED_UNICODE);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => $se]));
    foreach (($http_response_header ?? []) as $x) {
        if (stripos($x, 'Set-Cookie: PHPSESSID') === 0) $KZ[$kim] = explode(';', trim(substr($x, 11)))[0];
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}

$api = (string)@file_get_contents($KOD . '/api/index.php');
$pnl = (string)@file_get_contents($KOD . '/panel.php');
den('kaynaklar okunabildi', $api !== '' && $pnl !== '');

/* Ölçüm hesapları: bir sıradan editör kurulur (yoksa). */
$hp = $VERI . '/hesaplar.json';
$hs = json_decode((string)@file_get_contents($hp), true) ?: [];
$edVar = false;
foreach ($hs as $h) if (is_array($h) && ($h['eposta'] ?? '') === 'sade.editor@ornek-sinama.org') $edVar = true;
if (!$edVar) {
    $hs[] = ['ad' => 'Sade Editör', 'unvan' => 'Dr.', 'eposta' => 'sade.editor@ornek-sinama.org',
             'kullanici' => 'sadeeditor', 'kurum' => 'Ölçüm',
             'parola' => password_hash('sade1234', PASSWORD_DEFAULT),
             'roller' => ['editor'], 'katilma' => date('c')];
    file_put_contents($hp, json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}
@unlink($VERI . '/giris-deneme.json');
@unlink($VERI . '/hiz-sinir.json');

/* ------------------------------------------------------------------ */
echo "\n== 1. Kimlikler ==\n";
$r = yist('ed', '/api/hesap/giris', ['kim' => 'sadeeditor', 'parola' => 'sade1234']);
den('sıradan editör girdi', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  ve baş editör DEĞİL', empty($r['hesap']['bas_yetki']), json_encode($r['hesap']['roller'] ?? []));
$r = yist('bas', '/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
den('baş editör girdi', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 2. İletiler yalnızca baş editöre açık ==\n";
foreach ([['/api/yonetim/ileti-liste', 'liste'], ['/api/yonetim/ileti-oku?kod=abc', 'oku']] as [$yol, $ad]) {
    $r = yist('ed', $yol);
    den('sıradan editör ileti ' . $ad . ' ucuna ERİŞEMİYOR', empty($r['ok']), json_encode($r, JSON_UNESCAPED_UNICODE));
    den('  gerekçe baş editörü söylüyor',
        str_contains((string)($r['hata'] ?? ''), 'baş editör'), (string)($r['hata'] ?? ''));
}
$r = yist('bas', '/api/yonetim/ileti-liste');
den('baş editör ileti listesini açabiliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
/* Yazma uçları da kapalı kalmalı: okuma kapatılırken yazmanın açılması
   düzeltmenin en sık görülen yan etkisidir. */
foreach ([['/api/yonetim/ileti-yanit', ['kod' => 'x', 'metin' => 'y']],
          ['/api/yonetim/ileti-kapat', ['kod' => 'x']],
          ['/api/yonetim/ileti-sil', ['kod' => 'x']],
          ['/api/yonetim/ileti-istenmeyen', ['kod' => 'x']]] as [$yol, $g]) {
    $r = yist('ed', $yol, $g);
    den('  sıradan editör ' . basename($yol) . ' yapamıyor', empty($r['ok']), (string)($r['hata'] ?? ''));
}
/* Editörün KENDİ işi kapanmadı: yetki daraltmanın yan etkisi olarak
   editörlüğün kendisi kapansaydı, kusur düzeltmek değil taşımak olurdu. */
$r = yist('ed', '/api/editor/calismalar');
den('editör kendi işini yapmayı sürdürüyor (çalışma listesi)', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 3. Panel bölümü sunucuda basılmıyor ==\n";
/* Gizlenmiş bir sekme açılır; basılmamış bir sekme açılamaz. */
den('İletiler bölümü $basYetki ile basılıyor',
    (bool)preg_match('#<\?php if \(\$basYetki\): \?>\s*<section class="pn-pnl" data-pnl="iletiler">#', $pnl));
den('  sekme düğmesi de baş editör ölçütüne bağlı',
    str_contains($pnl, "goster(\\\$('sekIletiler'), !!h.bas_yetki);"));
den('  liste çağrısı da yalnız baş editörde koşuyor', str_contains($pnl, 'if (BAS_YETKI) iltListeCiz();'));
den('okuma uçları baş editör kapısında',
    substr_count($api, "\$yol === '/yonetim/ileti-liste' && \$metod === 'GET') {\n    yonetim_yazma_gerek();") === 1);

/* ------------------------------------------------------------------ */
echo "\n== 4. Atama doktorayı doğruluyor, sorumluluk atayanda ==\n";
den('tg_dogrulama_editor() "atama" yolunu tanıyor',
    (tg_dogrulama_editor('X', 'atama')['yol'] ?? '') === 'atama');
den('  ve uç bu yolu GERÇEKTEN çağırıyor',
    str_contains($api, "tg_dogrulama_editor(\$ed['ad'], 'atama')"));
/* Uçtan uca: hesabı olan biri hakem atanınca doktorası onaylı olur ve
   onaylayan olarak ATAYAN yazılır. */
/* AD DA HER KOŞUMDA YENİDİR. İlk yazımda ad sabitti ("Atama Ölçüm")
   ve uç ikinci koşumda haklı olarak "bu kişi zaten hakem olarak
   eklenmiş" dedi: kapı, kendi bıraktığı ize takıldı. Bir kapı
   yinelenebilir değilse, ölçtüğü şeyi yalnız bir kez ölçmüş olur. */
$ek = bin2hex(random_bytes(4));
$olcAd  = 'Atama Ölçüm ' . $ek;
$olcAd2 = 'Atama Ölçüm İkinci ' . $ek;
$posta = 'atama.olcum.' . $ek . '@ornek-sinama.org';
$hs = json_decode((string)@file_get_contents($hp), true) ?: [];
$hs[] = ['ad' => $olcAd, 'unvan' => 'Dr.', 'eposta' => $posta,
         'kullanici' => 'atamaolcum' . $ek, 'kurum' => 'Ölçüm',
         'parola' => password_hash('x', PASSWORD_DEFAULT), 'roller' => ['hakem'], 'katilma' => date('c')];
file_put_contents($hp, json_encode($hs, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$yz = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [];
$wid = '';
foreach ($yz as $e) { if (is_array($e) && ($e['tur'] ?? '') === 'hakemli') { $wid = (string)$e['id']; break; } }
den('ölçüm için hakemli bir çalışma bulundu', $wid !== '');
$r = yist('bas', '/api/editor/hakem-ata', ['id' => $wid, 'ad' => $olcAd, 'eposta' => $posta]);
den('baş editör hakem atayabiliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  atama doktorayı doğruladı', !empty($r['dogrulandi']));
den('  doğrulayan olarak ATAYAN yazıldı', trim((string)($r['dogrulayan'] ?? '')) !== '', (string)($r['dogrulayan'] ?? ''));
$hs2 = json_decode((string)@file_get_contents($hp), true) ?: [];
$kayit = null;
foreach ($hs2 as $h) if (is_array($h) && ($h['eposta'] ?? '') === $posta) $kayit = $h;
den('  hesapta doğrulama kaydı var', is_array($kayit['dogrulama'] ?? null));
if (is_array($kayit['dogrulama'] ?? null)) {
    olc('kayıt: ' . json_encode($kayit['dogrulama'], JSON_UNESCAPED_UNICODE));
    den('    durum onaylı', ($kayit['dogrulama']['durum'] ?? '') === 'onayli');
    den('    yol "atama"', ($kayit['dogrulama']['yol'] ?? '') === 'atama');
    den('    onaylayanın ADI yazılı', trim((string)($kayit['dogrulama']['onaylayan'] ?? '')) !== '');
}
/* Zaten onaylı bir kayıt EZİLMEZ: yeni bir atama eski bir sorumluluk
   kaydının üstüne yazamaz. */
$r2 = yist('ed', '/api/editor/hakem-ata', ['id' => $wid, 'ad' => $olcAd2, 'eposta' => $posta]);
$hs3 = json_decode((string)@file_get_contents($hp), true) ?: [];
$kayit2 = null;
foreach ($hs3 as $h) if (is_array($h) && ($h['eposta'] ?? '') === $posta) $kayit2 = $h;
den('  ikinci atama eski sorumluluk kaydını EZMİYOR',
    ($kayit2['dogrulama']['onaylayan'] ?? '') === ($kayit['dogrulama']['onaylayan'] ?? 'x'),
    (string)($kayit2['dogrulama']['onaylayan'] ?? ''));
den('  sıradan editör de hakem atayabiliyor', !empty($r2['ok']), (string)($r2['hata'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 5. Kaldırılmış e-Devlet yolu artık duyurulmuyor ==\n";
foreach (['nasil-isler.php', 'hakemlik.php', 'bekleyen.php', 'ilkeler.php', 'kurul.php'] as $sf) {
    $x = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/' . $sf . '?lang=tr');
    den('  ' . $sf . ' e-Devlet\'ten söz etmiyor', $x !== '' && !str_contains($x, 'e-Devlet'), $sf);
}
$ni = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/nasil-isler.php?lang=tr');
den('nasıl işler, doğrulamanın bugünkü kuralını yazıyor',
    str_contains($ni, 'Belge istenmez ve saklanmaz'));
den('  sorumluluğun atayanda olduğunu söylüyor',
    str_contains($ni, 'sorumluluğu atayan kişiye aittir'));

/* ------------------------------------------------------------------ */
echo "\n== 6. Ölçüm izleri geri alındı ==\n";
/* Bıraktığı izle bir sonraki koşumu bozan kapı, ölçüm değil tuzaktır. */
$hsS = [];
foreach (json_decode((string)@file_get_contents($hp), true) ?: [] as $h) {
    if (is_array($h) && ($h['eposta'] ?? '') === $posta) continue;
    $hsS[] = $h;
}
file_put_contents($hp, json_encode($hsS, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$yzS = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [];
$silinen = 0;
foreach ($yzS as $ix => $e) {
    if (!is_array($e) || (string)($e['id'] ?? '') !== $wid) continue;
    $kalan = [];
    foreach (tg_dizi($e['hakemler'] ?? null) as $h) {
        if (is_array($h) && in_array((string)($h['ad'] ?? ''), [$olcAd, $olcAd2], true)) { $silinen++; continue; }
        $kalan[] = $h;
    }
    $yzS[$ix]['hakemler'] = $kalan;
}
file_put_contents($VERI . '/yazilar.json', json_encode($yzS, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('ölçüm hesabı silindi', true);
den('  ölçüm hakem kayıtları silindi', $silinen >= 1, (string)$silinen);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
