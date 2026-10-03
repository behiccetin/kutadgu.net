<?php
/* Arşiv dökümü: kapı ve davranış ölçümü. Depoya girmez.
   Kullanım: KUTADGU_DATA=... php dokum-kapi.php */
declare(strict_types=1);
require_once '/home/claude/kg/ktest/k/dokum.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function ist(string $yol, array $bas = [], string $metod = 'GET'): array {
    $ctx = stream_context_create(['http' => [
        'method' => $metod, 'header' => implode("\r\n", $bas), 'ignore_errors' => true, 'timeout' => 30]]);
    $g = @file_get_contents('http://127.0.0.1:' . (getenv('KPORT') ?: '8941') . $yol, false, $ctx);
    $h = $http_response_header ?? [];
    $kod = 0;
    foreach ($h as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'basliklar' => $h, 'govde' => (string)$g];
}
function bas(array $h, string $ad): string {
    foreach ($h as $s) if (stripos($s, $ad . ':') === 0) return trim(substr($s, strlen($ad) + 1));
    return '';
}

echo "== 1. Uc katman uretildi ve belirtede yazili ==\n";
$b = dk_belirte();
$k = ['a' => 0, 'b' => 0, 'c' => 0];
foreach ($b['dosyalar'] as $d) $k[(string)$d['katman']] = ($k[(string)$d['katman']] ?? 0) + 1;
den('katman a dosyasi var', $k['a'] >= 2, (string)$k['a']);
den('katman b yillara bolunmus', $k['b'] >= 3, (string)$k['b']);
den('katman c paketi var', $k['c'] === 1, (string)$k['c']);
den('her dosyanin sha256 ozeti var', !array_filter($b['dosyalar'], fn($d) => strlen((string)($d['ozet'] ?? '')) !== 64));
den('her dosyanin uretim tarihi var', !array_filter($b['dosyalar'], fn($d) => strtotime((string)($d['uretim'] ?? '')) === false));
den('SHA256SUMS.txt yazildi', is_file(dk_yol('SHA256SUMS.txt')));

echo "\n== 2. Ozetler dosyalarla tutuyor ==\n";
foreach ($b['dosyalar'] as $d) {
    $y = dk_yol((string)$d['ad']);
    den('ozet dogru: ' . $d['ad'], is_file($y) && hash_file('sha256', $y) === (string)$d['ozet']);
}

echo "\n== 3. Katman a gercekten hafif: tam metin tasimiyor ==\n";
$a = json_decode((string)file_get_contents(dk_yol('kutadgu-ustveri.json')), true);
$ilk = $a['calismalar'][0] ?? [];
den('metin alani yok', !isset($ilk['metin']));
den('kaynakca alani yok', !isset($ilk['kaynakca']));
den('hakem rapor govdesi yok', !isset($ilk['hakemler'][0]['rapor']));
den('kunye alanlari duruyor', isset($ilk['tamga'], $ilk['baslik'], $ilk['yazarlar'], $ilk['parmak_izi']));
$ab = filesize(dk_yol('kutadgu-ustveri.json'));
$bb = filesize(dk_yol('kutadgu-tam-tumu.json'));
den('katman a katman b den kucuk', $ab < $bb / 5, round($ab / 1024) . ' KB / ' . round($bb / 1024) . ' KB');

echo "\n== 4. Kisisel veri dokumde yok ==\n";
$hepsi = '';
foreach ($b['dosyalar'] as $d) if (substr((string)$d['ad'], -5) === '.json') $hepsi .= file_get_contents(dk_yol((string)$d['ad']));
den('e-posta adresi gecmiyor', strpos($hepsi, '@ornek.org') === false);
den('"eposta" anahtari gecmiyor', strpos($hepsi, '"eposta"') === false);

echo "\n== 5. Yillara bolme dogru ==\n";
$toplam = 0;
foreach ($b['dosyalar'] as $d) {
    if (($d['katman'] ?? '') !== 'b' || !isset($d['yil'])) continue;
    $j = json_decode((string)file_get_contents(dk_yol((string)$d['ad'])), true);
    $toplam += count($j['calismalar']);
    $yanlis = 0;
    foreach ($j['calismalar'] as $c) if (substr((string)$c['tarih'], 0, 4) !== (string)$d['yil']) $yanlis++;
    den('yil parcasi ' . $d['yil'] . ' yalniz o yili tasiyor', $yanlis === 0, (string)$yanlis);
}
$tumu = json_decode((string)file_get_contents(dk_yol('kutadgu-tam-tumu.json')), true);
den('yil parcalarinin toplami tek parcaya esit', $toplam === count($tumu['calismalar']),
    $toplam . ' / ' . count($tumu['calismalar']));

echo "\n== 6. Onay kapisi yok ==\n";
$r = ist('/dokum.php?d=kutadgu-ustveri.json');
den('anonim istek 200 aliyor', $r['kod'] === 200, (string)$r['kod']);
den('X-Kosul basligi "yok"', bas($r['basliklar'], 'X-Kosul') === 'yok');
den('Set-Cookie ile isaretlenmiyor', bas($r['basliklar'], 'Set-Cookie') === '');
$src = file_get_contents('/home/claude/kg/ktest/dokum.php') . file_get_contents('/home/claude/kg/ktest/k/dokum.php')
     . file_get_contents('/home/claude/kg/ktest/arsiv.php');
den('kodda yetki/giris/onay kapisi cagrisi yok',
    !preg_match('/yetki_gerek|girisli\(\)|hs_giris|oturum_gerek|bas_yetki_gerek/', $src));
den('ayar suzgeci arsiv_onay ve dokum_onay anahtarlarini kapatiyor',
    (function () { $x = tg_ayar_ilke_suz(['arsiv_onay' => true, 'dokum_onay' => true, 'arsiv_kayit_gerek' => true], $kayit);
                   return empty($x['arsiv_onay']) && empty($x['dokum_onay']) && empty($x['arsiv_kayit_gerek']); })());
$r429 = null;
for ($i = 0; $i < 8; $i++) $r429 = ist('/dokum.php?d=kutadgu-paket.zip', ['CF-Connecting-IP: 203.0.113.55', 'Range: bytes=0-0']);
den('tavan asilinca 429 donuyor', $r429['kod'] === 429, (string)$r429['kod']);
den('  ve Retry-After yazili', (int)bas($r429['basliklar'], 'Retry-After') > 0, bas($r429['basliklar'], 'Retry-After'));
den('  ve bekleme en cok on dakika', (int)bas($r429['basliklar'], 'Retry-After') <= 600, bas($r429['basliklar'], 'Retry-After'));
den('  ve govdede "reddetme degildir" yaziyor', strpos($r429['govde'], 'reddetme degildir') !== false);
den('  ve ayni anda katman a acik kaliyor',
    ist('/dokum.php?d=kutadgu-ustveri.json', ['CF-Connecting-IP: 203.0.113.55'])['kod'] === 200);

echo "\n== 7. Onbellek ve kismi indirme ==\n";
$r = ist('/dokum.php?d=kutadgu-ustveri.json');
$etag = bas($r['basliklar'], 'ETag');
den('ETag gonderiliyor', $etag !== '');
den('Last-Modified gonderiliyor', bas($r['basliklar'], 'Last-Modified') !== '');
den('Cache-Control durgun dosyaya gore', strpos(bas($r['basliklar'], 'Cache-Control'), 'max-age=3600') !== false,
    bas($r['basliklar'], 'Cache-Control'));
den('Accept-Ranges: bytes', bas($r['basliklar'], 'Accept-Ranges') === 'bytes');
den('If-None-Match 304 donuyor', ist('/dokum.php?d=kutadgu-ustveri.json', ['If-None-Match: ' . $etag])['kod'] === 304);
$p = ist('/dokum.php?d=kutadgu-ustveri.json', ['Range: bytes=10-19']);
den('Range 206 ve 10 bayt donuyor', $p['kod'] === 206 && strlen($p['govde']) === 10,
    $p['kod'] . '/' . strlen($p['govde']));
den('  ve Content-Range dogru', strpos(bas($p['basliklar'], 'Content-Range'), 'bytes 10-19/') === 0,
    bas($p['basliklar'], 'Content-Range'));
den('gecersiz Range 416 donuyor', ist('/dokum.php?d=kutadgu-ustveri.json', ['Range: bytes=99999999-'])['kod'] === 416);

echo "\n== 8. Dizin gezinmesi kapali ==\n";
foreach (['../../ayar.php', '..%2Fayar.php', 'kutadgu-ustveri.json/../../yazilar.json', 'yazilar.json', 'belirte.json'] as $kotu) {
    den('reddedildi: ' . $kotu, ist('/dokum.php?d=' . rawurlencode($kotu))['kod'] === 404);
}

echo "\n== 9. Eski adres kirilmadi ==\n";
$e1 = ist('/arsiv.php');
den('/arsiv.php 200', $e1['kod'] === 200, (string)$e1['kod']);
den('  ve tek parca tam metin dosyasini veriyor',
    hash('sha256', $e1['govde']) === hash_file('sha256', dk_yol('kutadgu-tam-tumu.json')));
den('  ve varsayilan olarak indirme basligi yok', bas($e1['basliklar'], 'Content-Disposition') === '');
den('/arsiv.php?indir=1 indirme basligi koyuyor',
    strpos(bas(ist('/arsiv.php?indir=1')['basliklar'], 'Content-Disposition'), 'attachment') === 0);
$e2 = ist('/arsiv.php?b=ozet');
den('/arsiv.php?b=ozet parmak izlerini veriyor',
    $e2['kod'] === 200 && strpos($e2['govde'], 'Her satir: <parmak izi>') !== false);

echo "\n== 10. Kuyruk ==\n";
/* (a) Guncel bir paket varken sira diye bir sey yoktur. */
$g = dk_sira_al('sinama-iz-0');
den('guncel paket varken sıraya girilmiyor', ($g['durum'] ?? '') === 'hazir', (string)($g['durum'] ?? ''));
den('  ve bekleyen is acilmiyor', dk_kuyruk_bekleyen() === 0, (string)dk_kuyruk_bekleyen());

/* (b) Paket eskiyince sira isler. Paketi elle yaslandiriyoruz. */
$bel = dk_belirte();
$bel['paket']['t'] = time() - dk_paket_arasi() - 60;
dk_belirte_yaz($bel);

$k1 = dk_sira_al('sinama-iz-1');
den('paket eskiyince ilk istek fis aliyor', ($k1['fis'] ?? '') !== '' && ($k1['durum'] ?? '') === 'sirada',
    (string)($k1['durum'] ?? ''));
$k1b = dk_sira_al('sinama-iz-1');
den('ayni ziyaretcinin ikinci istegi YENI is acmiyor', ($k1b['fis'] ?? '') === $k1['fis']);
$k2 = dk_sira_al('sinama-iz-2');
den('baska ziyaretci ayri fis aliyor', ($k2['fis'] ?? '') !== ($k1['fis'] ?? ''));
den('  ve ikisi ayni isi bekliyor', dk_kuyruk_bekleyen() === 2, (string)dk_kuyruk_bekleyen());
den('  ikinci istek sirada 2. sirada', (int)($k2['sira'] ?? 0) === 2, (string)($k2['sira'] ?? 0));

$u = dk_kuyruk_isle();
den('tek uretim iki istegi de karsiliyor', !empty($u['ok']) && empty($u['atlandi']));
den('  birinci fis hazir', (dk_fis_durum((string)$k1['fis'])['durum'] ?? '') === 'hazir');
den('  ikinci fis hazir', (dk_fis_durum((string)$k2['fis'])['durum'] ?? '') === 'hazir');
den('  ikisi de ayni dosyayi gosteriyor',
    (dk_fis_durum((string)$k1['fis'])['paket']['ozet'] ?? 'x') === (dk_fis_durum((string)$k2['fis'])['paket']['ozet'] ?? 'y'));
den('  kuyrukta bekleyen kalmadi', dk_kuyruk_bekleyen() === 0, (string)dk_kuyruk_bekleyen());
den('fissiz ucuncu kisi paketi dogrudan indirebiliyor',
    ist('/dokum.php?d=kutadgu-paket.zip', ['CF-Connecting-IP: 198.51.100.240', 'Range: bytes=0-9'])['kod'] === 206);

echo "\n== 11. Gunluk sinir ==\n";
$once = dk_belirte()['paket']['t'] ?? 0;
$s = dk_uret_paket(false);
den('gun dolmadan yeniden uretilmiyor', !empty($s['atlandi']) && ($s['sebep'] ?? '') === 'gunluk-sinir',
    (string)($s['sebep'] ?? ''));
den('  ve paket yerinde duruyor', (int)(dk_belirte()['paket']['t'] ?? 0) === (int)$once);

echo "\n== 12. Kirli iz ve kilit ==\n";
dk_kirlet('sinama');
den('kirli iz birakildi', dk_kirli() !== []);
$s = dk_uret_hafif();
den('kirli izden sonra uretim yapildi', !empty($s['ok']) && empty($s['atlandi']));
den('  ve iz temizlendi', dk_kirli() === []);
$s2 = dk_uret_hafif();
den('degisiklik yokken uretim atlaniyor', !empty($s2['atlandi']) && ($s2['sebep'] ?? '') === 'degisiklik-yok');
$kl = dk_kilit_al('uretim.kilit');
den('kilit alindi', $kl !== null);
dk_kirlet('kilit sinamasi');
$s3 = dk_uret_hafif();
den('kilit mesgulken ikinci uretim baslamiyor', ($s3['sebep'] ?? '') === 'kilit-mesgul');
dk_kilit_birak($kl);
den('  ve is kaybolmuyor: iz yerinde duruyor', dk_kirli() !== []);
dk_uret_hafif();

echo "\n== 13. Belirte ucu ==\n";
$d = ist('/dokum.php?durum=1');
$j = json_decode($d['govde'], true);
den('durum ucu JSON donuyor', is_array($j) && isset($j['dosyalar']));
den('  ve kural metni iki dilde yazili', isset($j['kural']['tr'], $j['kural']['en']));
den('  ve bekleyen istek sayisi var', array_key_exists('bekleyen_istek', $j));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
