<?php
/* =====================================================================
   ARŞİV BOŞLUKLARI RAPORU · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 18 Ağustos 2026. İki eksik biliniyordu ama hiçbir
   yerde SAYILMIYORDU:
     (a) etik beyanı hiç istenmemiş arşiv çalışmaları,
     (b) ortak yazarı adres olmadan kaydedilmiş çalışmalar — o kişilere
         yazarlıkları hiç bildirilmedi.
   Ölçülmeyen eksik ya abartılır ya unutulur; ikisi de karar vermeyi
   engeller.

   BU KAPI ÜÇ ŞEYİ ÖLÇER:
     1. rapor doğru sayıyor mu,
     2. rapor ADRES SIZDIRMIYOR mu (ihtiyacı olmayan veriyi toplayan bir
        rapor, sızıntının en sıradan biçimidir),
     3. raporda GERİ ALINAMAZ bir eylem YOK mu — posta gönderen hiçbir
        düğme ne uçta ne ekranda bulunmamalı.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php arsiv-bosluk-kapi.php
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

$KEREZ = '';
function ist(string $yol, ?array $govde = null): array {
    global $KEREZ;
    static $ip = null;
    if ($ip === null) $ip = '203.0.113.' . random_int(1, 254);
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nX-Forwarded-For: $ip\r\n";
    if ($KEREZ !== '') $bas .= 'Cookie: ' . $KEREZ . "\r\n";
    $se = ['method' => $govde === null ? 'GET' : 'POST', 'header' => $bas,
           'timeout' => 15, 'ignore_errors' => true];
    if ($govde !== null) $se['content'] = json_encode($govde, JSON_UNESCAPED_UNICODE);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => $se]));
    foreach (($http_response_header ?? []) as $x) {
        if (stripos($x, 'Set-Cookie: PHPSESSID') === 0) $KEREZ = explode(';', trim(substr($x, 11)))[0];
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}

$api = (string)@file_get_contents($KOD . '/api/index.php');
$pnl = (string)@file_get_contents($KOD . '/panel.php');
den('kaynaklar okunabildi', $api !== '' && $pnl !== '');

/* ------------------------------------------------------------------ */
echo "\n== 1. Rapor editör kimliği istiyor ==\n";
$r = ist('/api/editor/arsiv-bosluk');
den('kimliksiz istek reddediliyor', empty($r['ok']), json_encode(array_slice($r, 0, 2)));
ist('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
$r = ist('/api/editor/arsiv-bosluk');
den('editör kimliğiyle açılıyor', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 2. Sayılar veriyle birebir ==\n";
$yz = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [];
$eBek = 0; $oBek = 0; $kBek = 0;
foreach ($yz as $e) {
    if (!is_array($e)) continue;
    if (in_array(tg_etik_hal($e), ['sorulmadi', 'eksik', 'askida'], true)) $eBek++;
    $l = tg_dizi($e['yazar_liste'] ?? null); $a = 0;
    foreach ($l as $ix => $ya) {
        if ($ix === 0 || !is_array($ya)) continue;
        if (trim((string)($ya['eposta'] ?? '')) === '') $a++;
    }
    if ($a) { $oBek++; $kBek += $a; }
}
olc('veride: etik ' . $eBek . ' · adressiz ortak yazarlı çalışma ' . $oBek . ' (' . $kBek . ' kişi)');
den('toplam çalışma sayısı doğru', (int)($r['toplam'] ?? -1) === count($yz), (string)($r['toplam'] ?? ''));
den('  etik boşluğu sayısı doğru', (int)($r['etik']['sayi'] ?? -1) === $eBek, (string)($r['etik']['sayi'] ?? ''));
den('  ortak yazar boşluğu doğru', (int)($r['ortak']['sayi'] ?? -1) === $oBek, (string)($r['ortak']['sayi'] ?? ''));
den('  ulaşılamayan kişi sayısı doğru', (int)($r['ortak']['kisi'] ?? -1) === $kBek, (string)($r['ortak']['kisi'] ?? ''));
den('  kuralın başlangıç tarihi de raporda', (string)($r['baslangic'] ?? '') === tg_etik_baslangic());

/* ------------------------------------------------------------------ */
echo "\n== 3. Tek yazarlı çalışma boşluk SAYILMIYOR ==\n";
/* Tek yazarlı bir çalışmada "ortak yazara haber verilmedi" diye bir
   eksik yoktur; sayıya katmak eksiği olduğundan büyük gösterirdi. */
$t = bin2hex(random_bytes(8));
$tek = 'bosluk-tek-' . $t; $cok = 'bosluk-cok-' . $t;
$yeni = $yz;
$yeni[] = ['id' => $tek, 'bcid' => 'bc.900101', 'slug' => $tek, 'tur' => 'yazi',
           'tarih' => '2024-01-01T12:00:00+03:00', 'dil' => 'tr', 'baslik' => 'Tek yazarlı ölçüm',
           'ozet' => 'o', 'yazar' => 'Dr. Tek Yazar',
           'yazar_liste' => [['unvan' => 'Dr.', 'ad' => 'Tek Yazar', 'orcid' => '0000-0002-1825-0097']]];
$yeni[] = ['id' => $cok, 'bcid' => 'bc.900102', 'slug' => $cok, 'tur' => 'yazi',
           'tarih' => '2024-01-02T12:00:00+03:00', 'dil' => 'tr', 'baslik' => 'Çok yazarlı ölçüm',
           'ozet' => 'o', 'yazar' => 'Dr. Bir Yazar',
           'yazar_liste' => [
               ['unvan' => 'Dr.', 'ad' => 'Bir Yazar', 'orcid' => '0000-0002-1825-0097', 'eposta' => 'bir@ornek.org'],
               ['unvan' => 'Dr.', 'ad' => 'Adressiz Ortak', 'orcid' => '0000-0001-5109-3700'],
               ['unvan' => 'Dr.', 'ad' => 'Adresli Ortak', 'orcid' => '0000-0003-1415-9269', 'eposta' => 'uc@ornek.org'],
           ]];
file_put_contents($VERI . '/yazilar.json', json_encode($yeni, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$r2 = ist('/api/editor/arsiv-bosluk');
den('rapor yeniden okunabildi', !empty($r2['ok']));
den('  çok yazarlı çalışma boşluk olarak sayıldı',
    (int)($r2['ortak']['sayi'] ?? -1) === $oBek + 1, (string)($r2['ortak']['sayi'] ?? ''));
den('  ama YALNIZCA adressiz ortak sayıldı (adresli sayılmadı)',
    (int)($r2['ortak']['kisi'] ?? -1) === $kBek + 1, (string)($r2['ortak']['kisi'] ?? ''));
$adlar = [];
foreach (($r2['ortak']['liste'] ?? []) as $w) {
    if (($w['baslik'] ?? '') === 'Çok yazarlı ölçüm') $adlar = (array)($w['kisiler'] ?? []);
}
olc('listelenen kişiler: ' . implode(', ', $adlar));
den('  listede adressiz ortak var', in_array('Adressiz Ortak', $adlar, true));
den('  iletişim yazarı listede YOK (o gönderendir)', !in_array('Bir Yazar', $adlar, true));
den('  adresi olan ortak da listede YOK', !in_array('Adresli Ortak', $adlar, true));
$tekVar = false;
foreach (($r2['ortak']['liste'] ?? []) as $w) if (($w['baslik'] ?? '') === 'Tek yazarlı ölçüm') $tekVar = true;
den('  tek yazarlı çalışma boşluk sayılmadı', !$tekVar);

/* ------------------------------------------------------------------ */
echo "\n== 4. Rapor ADRES SIZDIRMIYOR ==\n";
$ham = json_encode($r2, JSON_UNESCAPED_UNICODE);
den('yanıtta hiçbir e-posta adresi yok', !preg_match('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/i', $ham),
    (string)(preg_match('/[\w.+-]+@[\w.-]+\.[a-z]{2,}/i', $ham, $mm) ? $mm[0] : ''));
den('  "adresi var mı" bilgisi de dönmüyor (gerekmiyor)', !str_contains($ham, 'adres_var'));

/* ------------------------------------------------------------------ */
echo "\n== 5. Raporda GERİ ALINAMAZ eylem YOK ==\n";
/* Bu kapının asıl nedeni budur. Eksiği kapatmanın yolu gerçek kişilere
   posta yazmaktan geçer ve posta geri alınamaz. Geri alınamaz bir işi
   bir raporun yanına iliştirmek, onu kazayla yapılabilir kılar. */
$b = strpos($api, "\$yol === '/editor/arsiv-bosluk'");
$son = $b !== false ? strpos($api, "\$yol === '/editor/calismalar'", $b) : false;
$blok = ($b !== false && $son !== false) ? substr($api, $b, $son - $b) : '';
den('uç bölümü bulundu', $blok !== '', (string)strlen($blok));
den('  uç yalnız GET (yazan bir yolu yok)', str_contains($blok, "\$metod === 'GET'"));
den('  uçta posta gönderimi yok', !preg_match('/posta_gonder|mail\(|lettermint/i', $blok));
den('  uçta diske yazma yok', !str_contains($blok, 'yaz_json('));
$pb = strpos($pnl, 'id="kartBosluk"');
$ps = $pb !== false ? strpos($pnl, '</details>', $pb) : false;
$pkart = ($pb !== false && $ps !== false) ? substr($pnl, $pb, $ps - $pb) : '';
den('panel kartı bulundu', $pkart !== '', (string)strlen($pkart));
den('  kartta hiçbir düğme yok', !preg_match('/<button|class="d d-/i', $pkart));
den('  kart KAPALI doğuyor (details, open değil)',
    str_contains($pnl, '<details class="pn-kart pn-tam pn-katla" id="kartBosluk">'));
/* ÖLÇÜM DÜZELTİLDİ — 19 Ağustos 2026. Burada kartın 3000 karakter
   ÖNCESİNDE 'if ($edYetki):' aranıyordu. Araya yeni bir kart girdiği
   gün (kurul kararları) o pencere kaydı ve kapı "kart yalnız editöre
   basılmıyor" dedi — oysa basılıyordu. Mesafeye dayanan bir ölçüm,
   komşusu değiştiğinde yanılır.

   Ölçüt artık mesafe değil YAPI: kartın bulunduğu noktadan GERİYE doğru
   en yakın koşul hangisiyse odur. */
den('  kart yalnız editöre basılıyor', (function() use ($pnl) {
    $i = strpos($pnl, 'id="kartBosluk"');
    if ($i === false) return false;
    $onc = substr($pnl, 0, $i);
    $ed  = strrpos($onc, 'if ($edYetki):');
    $bas = strrpos($onc, 'if ($basYetki):');
    if ($ed === false) return false;
    /* Kart bir baş editör koşulunun içindeyse o da kabul: baş editör
       yetkisi editör yetkisini kapsar, tersi değil. */
    return $bas === false || $ed > $bas;
})());

/* ------------------------------------------------------------------ */
/* ------------------------------------------------------------------ */
echo "\n== 6. Gidecek iletinin ÖNİZLEMESİ (hiçbir şey gönderilmez) ==\n";
/* Bu iki eksiği kapatmak gerçek kişilere posta yazmayı gerektiriyor ve
   posta geri alınamaz. İşi bekleten şey teknik bir eksik değil bir
   KARARdı; karar için görülmesi gereken üç şey vardı: ileti ne diyor,
   kaç kişiye gidiyor, gidemeyen var mı. Bu kapı üçünün de gösterildiğini
   VE hiçbir şeyin gönderilmediğini ölçer. */
$r = ist('/api/yonetim/arsiv-bildirim-onizleme');
den('önizleme açılıyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  uç "gönderilmedi" diyor', !empty($r['gonderilmedi']));
foreach (['etik', 'ortak'] as $k) {
    den('  ' . $k . ': konu var', trim((string)($r[$k]['konu'] ?? '')) !== '');
    den('  ' . $k . ': ileti metni var', mb_strlen((string)($r[$k]['govde'] ?? '')) > 300);
    den('  ' . $k . ': alıcı sayısı bildiriliyor', array_key_exists('sayi', (array)($r[$k] ?? [])));
    den('  ' . $k . ': adresi olmayan da sayılıyor', array_key_exists('adressiz', (array)($r[$k] ?? [])));
}
/* İLETİ SUÇLAMAZ: beyan bu yazarlardan hiç istenmedi. Suçlayan bir
   ileti cevap alamaz. */
den('etik iletisi "hiç istenmedi" diyor',
    str_contains((string)($r['etik']['govde'] ?? ''), 'HİÇ İSTENMEDİ'));
den('  ve bir yükümlülük olmadığını söylüyor',
    str_contains((string)($r['etik']['govde'] ?? ''), 'bir yükümlülük değildir'));
den('ortak yazar iletisi gecikmeyi ÜSTLENİYOR',
    str_contains((string)($r['ortak']['govde'] ?? ''), 'Gecikme bizdendir'));
/* ADRESLER MASKELİ: editör kaç kişiye gideceğini bilmeli, adreslerin
   tamamını okumasına gerek yok. */
$acikAdres = [];
foreach ([['etik', 'liste'], ['etik', 'adressiz_liste'], ['ortak', 'liste'], ['ortak', 'adressiz_liste']] as [$k, $l]) {
    foreach ((array)($r[$k][$l] ?? []) as $x) {
        $ad = (string)($x['adres'] ?? '');
        if ($ad !== '' && !str_contains($ad, '*')) $acikAdres[] = $ad;
    }
}
den('hiçbir adres açık dönmüyor', !$acikAdres, implode(',', array_slice($acikAdres, 0, 3)));
/* GÖNDERME EYLEMİ YAZILMADI: bir kararın önünü açmakla o kararı vermek
   aynı şey değildir. */
den('gönderen bir uç YOK', !preg_match("#'/yonetim/arsiv-bildirim-gonder'#", $api));
$pb2 = strpos($pnl, 'id="bsOnizleme"');
$ps2 = $pb2 !== false ? strpos($pnl, '</details>', $pb2) : false;
$onizKart = ($pb2 !== false && $ps2 !== false) ? substr($pnl, $pb2, $ps2 - $pb2) : '';
den('  panelde önizleme kapağı var', $onizKart !== '', (string)strlen($onizKart));
den('  kapakta gönderme düğmesi yok', !preg_match('/<button/i', $onizKart));

/* ------------------------------------------------------------------ */
echo "\n== 7. Ölçüm kayıtları geri alındı ==\n";
$son2 = [];
foreach (json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [] as $e) {
    if (is_array($e) && in_array((string)($e['slug'] ?? ''), [$tek, $cok], true)) continue;
    $son2[] = $e;
}
file_put_contents($VERI . '/yazilar.json', json_encode($son2, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('ölçüm kayıtları silindi', count($son2) === count($yz));


echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
