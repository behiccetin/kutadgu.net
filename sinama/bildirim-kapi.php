<?php
/* =====================================================================
   BİLDİRİMLER VE DENEME İŞARETİ · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ — 20 Ağustos 2026, iki madde:

     1. "bildirimi görüyorum ama bildirimle ilgili sayfanın açılıp
        ilgili bölümün belli edilmesi lazım, ve sonrasında kişi onun
        üzerine tıklarsa ve işlem yaparsa bildirim gider"
     2. "ben deneme gibi bir makale gönderdim ret ettim ama orada
        kalmasa; denemeyi düzgün yapamamıştık ya o zaman"

   ÖLÇÜLEN KUSURLAR:

   A. BEKLEYEN İŞLER İKİ YERDE SAYILIYORDU. /panel/ozet kartın
      listesini kuruyor, /hesap/durum sağ üstteki dairenin sayısını
      kendi başına sayıyordu. İki kopya iki kez ayrıştı; en son 19
      Ağustos'ta: kurul kararı listede vardı rozette yoktu, kişinin
      kendi doğrulaması rozette vardı listede yoktu. Kartın kendi sözü
      ("...ya da tamamlanmamış bir doğrulama. Sağ üstteki daire
      üzerindeki sayı da bunları sayar") iki kez birden yanlıştı.
   B. BİLDİRİMİN HEDEFİ YOKTU. 'belge' ve 'gonullu' satırlarının 'yol'u
      BOŞTU; 'atama' okura açık liste sayfasına, 'kurul' ise kararların
      okunduğu kamusal sayfaya gidiyordu — ikisi de işin YAPILDIĞI yer
      değil. Adres yalnız sekme adı taşıyabildiği için de kişi 5000
      pikselden uzun bir sekmenin başına bırakılıyordu.
   C. İŞ BİTİNCE BİLDİRİM DÜŞMÜYORDU. Rozet oturum boyunca saklanan bir
      cevaptan çiziliyor, panelde iş bitince tazelenmiyordu.
   D. DENEME DÜZENİNDEN ÖNCE GÖNDERİLMİŞ GERÇEK DENEME KAYITLARI için
      hiçbir yol yoktu: /yonetim/deneme-sil yalnız 'deneme' işaretli
      kayıtları siler, eski kayıtta o işaret yoktur.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php bildirim-kapi.php
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

$CK = '';
$IP = '198.51.100.' . random_int(1, 254);
function ist(string $yol, ?array $g = null): array {
    global $CK, $IP;
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nX-Forwarded-For: $IP\r\n";
    if ($CK !== '') $bas .= 'Cookie: ' . $CK . "\r\n";
    $se = ['method' => $g === null ? 'GET' : 'POST', 'header' => $bas, 'timeout' => 25, 'ignore_errors' => true];
    if ($g !== null) $se['content'] = json_encode($g, JSON_UNESCAPED_UNICODE);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => $se]));
    foreach (($http_response_header ?? []) as $x) {
        if (stripos($x, 'Set-Cookie: PHPSESSID') === 0) $CK = explode(';', trim(substr($x, 11)))[0];
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}
function sayfa(string $yol): string {
    $port = getenv('KPORT') ?: '8941';
    return (string)@file_get_contents('http://127.0.0.1:' . $port . $yol, false,
        stream_context_create(['http' => ['timeout' => 25, 'ignore_errors' => true]]));
}

$api = (string)@file_get_contents($KOD . '/api/index.php');
$pnl = (string)@file_get_contents($KOD . '/panel.php');
$kjs = (string)@file_get_contents($KOD . '/k/kutadgu.js');
den('kaynaklar okunabildi', $api !== '' && $pnl !== '' && $kjs !== '');
@unlink($VERI . '/hiz-sinir.json');
@unlink($VERI . '/giris-deneme.json');

/* =====================================================================
   1. BEKLEYEN İŞLER TEK KAYNAKTAN
   ===================================================================== */
echo "\n== 1. Bekleyen işler tek kaynaktan ==\n";
den('bekleyen_isler() işlevi var', str_contains($api, 'function bekleyen_isler(array $h): array'));
/* İKİNCİ SAYIM KALMADI: '$bekleyen[] = [' satırlarının hepsi bu
   işlevin içinde olmalı. Dışarıda bir tane bile kalırsa, liste ile
   rozet yeniden ayrışabilir. */
$fb = strpos($api, 'function bekleyen_isler(array $h): array');
$fs = $fb !== false ? strpos($api, "\nif (\$yol === '/hesap/durum'", $fb) : false;
$icBlok = ($fb !== false && $fs !== false) ? substr($api, $fb, $fs - $fb) : '';
$toplam = substr_count($api, '$bekleyen[] = [');
$icinde = substr_count($icBlok, '$bekleyen[] = [') + substr_count($icBlok, '$bek[] = [');
olc('kaynakta "bekleyen[] =" sayısı: ' . $toplam . ' · işlevin içindeki satır: ' . substr_count($icBlok, '$bek[] = ['));
den('  liste işlevin DIŞINDA kurulmuyor', $toplam === 0, (string)$toplam);
den('  /hesap/durum sayıyı listeden alıyor', str_contains($api, '$bekleyen = count(bekleyen_isler($h));'));
den('  /panel/ozet listeyi aynı işlevden alıyor', str_contains($api, '$bekleyen = bekleyen_isler($h);'));

kist_giris:
$r = ist('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
den('ölçüm hesabıyla girildi', !empty($r['ok']), (string)($r['hata'] ?? ''));

$oz = ist('/api/panel/ozet');
$du = ist('/api/hesap/durum');
$liste = is_array($oz['bekleyen'] ?? null) ? $oz['bekleyen'] : [];
olc('liste: ' . count($liste) . ' satır · rozet: ' . (int)($du['bekleyen'] ?? -1));
den('ROZET = LİSTE UZUNLUĞU', count($liste) === (int)($du['bekleyen'] ?? -1),
    count($liste) . ' vs ' . (int)($du['bekleyen'] ?? -1));

/* =====================================================================
   2. HER BİLDİRİMİN BİR HEDEFİ VAR
   ===================================================================== */
echo "\n== 2. Her bildirimin bir hedefi var ==\n";
$hedefsiz = [];
foreach ($liste as $b) if (trim((string)($b['yol'] ?? '')) === '') $hedefsiz[] = (string)($b['tur'] ?? '?');
den('listedeki her satırın hedefi var', $hedefsiz === [], implode(', ', $hedefsiz));
/* Kaynakta da hedefsiz satır kalmamalı: ölçüm anında o türden bir iş
   olmayabilir ve kapı onu hiç görmez. */
den('  kaynakta da hedefsiz satır yok', !preg_match("/'yol' => '',/", $icBlok));

/* PANEL HEDEFLERİ GERÇEKTEN VAR MI. '#sekme/kart' biçimindeki her
   hedef için kartın panel.php'de bulunması ve DOĞRU sekmenin içinde
   olması aranır: doğru sekmede olmayan bir kart, adres onu açsa bile
   görünmez. */
preg_match_all("#'/panel\.php\#([a-z]+)/([A-Za-z]+)'#", $icBlok, $hd, PREG_SET_ORDER);
olc('panel içi hedef sayısı: ' . count($hd));
den('  en az dört panel içi hedef var', count($hd) >= 4, (string)count($hd));
foreach ($hd as $x) {
    [$tam, $sek, $kart] = $x;
    $kp = strpos($pnl, 'id="' . $kart . '"');
    den('  ' . $kart . ' kartı var', $kp !== false);
    if ($kp === false) continue;
    $sp = strpos($pnl, 'data-pnl="' . $sek . '"');
    /* Kart, o sekmenin bölümünün İÇİNDE mi: bölümün başlangıcından
       sonraki İLK bölüm başlangıcından önce durmalı. */
    $son = $sp !== false ? strpos($pnl, '<section class="pn-pnl', $sp + 10) : false;
    if ($son === false) $son = strlen($pnl);
    den('    ' . $kart . ' gerçekten "' . $sek . '" sekmesinde', $sp !== false && $kp > $sp && $kp < $son);
}

/* =====================================================================
   3. ADRES KARTA GÖTÜRÜR VE KARTI BELLİ EDER
   ===================================================================== */
echo "\n== 3. Adres karta götürür ve kartı belli eder ==\n";
den('panel iki parçalı adres okuyor (#sekme/kart)', str_contains($pnl, 'function adrestenGit('));
den('  kapaklı kart açılıyor', (bool)preg_match('/adrestenGit[\s\S]{0,900}?k\.open\s*=\s*true/', $pnl));
den('  karta kaydırılıyor', (bool)preg_match('/adrestenGit[\s\S]{0,900}?scrollIntoView/', $pnl));
den('  kart vurgulanıyor', (bool)preg_match('/adrestenGit[\s\S]{0,900}?vurgula\(/', $pnl));
den('  vurgu kendi kendine sönüyor', (bool)preg_match('/function vurgula[\s\S]{0,700}?setTimeout/', $pnl));
den('  odak da karta taşınıyor', (bool)preg_match('/function vurgula[\s\S]{0,900}?\.focus\(/', $pnl));
den('  hareket istemeyene animasyon yok',
    (bool)preg_match('/prefers-reduced-motion:reduce\)\s*\{\s*\.pn-vurgu-ac\{animation:none\}/', $pnl));
den('paneldeyken gelen bildirim de çalışıyor (hashchange)',
    str_contains($pnl, "window.addEventListener('hashchange'"));
/* Sayfa gerçekten ayakta ve vurgu biçemi basılıyor mu */
$pg = sayfa('/panel.php?lang=tr');
den('panel sayfası vurgu biçemini basıyor', str_contains($pg, '.pn-vurgu-ac'));

/* =====================================================================
   4. İŞ BİTİNCE BİLDİRİM DÜŞER
   ===================================================================== */
echo "\n== 4. İş bitince bildirim düşer ==\n";
den('durum değiştiren her istek bekleyenleri tazeliyor',
    (bool)preg_match('/if \(govde !== undefined && c && c\.ok\) bekTazele\(\);/', $pnl));
den('  tazeleme tek yerde (api içinde)', substr_count($pnl, 'function bekTazele(') === 1);
den('  sağ üstteki daire de tazeleniyor', str_contains($pnl, 'window.kutadguHesapTazele'));
den('  kabuk betiği bu kapıyı açıyor', str_contains($kjs, 'W.kutadguHesapTazele = function'));
den('  saklanan cevap atılıyor', (bool)preg_match('/kutadguHesapTazele[\s\S]{0,300}?removeItem\(HS_ANAHTAR\)/', $kjs));

/* =====================================================================
   5. DENEME İŞARETİ: KAPILAR
   ===================================================================== */
echo "\n== 5. Deneme işareti: kapılar ==\n";
$yp = $VERI . '/yazilar.json';
$yed = (string)@file_get_contents($yp);
$yz  = json_decode($yed, true) ?: [];

/* ÖLÇÜM KAYDI, HESABIN GERÇEK ADIYLA KURULUR.
   İlk yazımda ad ve adres kapıya elle yazılmıştı ('Ölçüm Panel') ve
   süpürme sırasında başka bir kapı o hesabın adını değiştirince bu
   kapı KALDI: uç, çalışmanın yazarını "başka biri" saydı ve haklıydı —
   çalışmanın üstündeki ad ile hesabın adı gerçekten farklıydı. Ad ve
   adres artık kaydın kendisinden okunur. */
$hsHam = json_decode((string)@file_get_contents($VERI . '/hesaplar.json'), true) ?: [];
$benAd = ''; $benEp = '';
foreach ($hsHam as $x) {
    if (is_array($x) && (string)($x['kullanici'] ?? '') === 'olcumbas') {
        $benAd = trim((string)($x['ad'] ?? '')); $benEp = trim((string)($x['eposta'] ?? ''));
    }
}
den('ölçüm hesabının adı ve adresi okundu', $benAd !== '' && $benEp !== '', $benAd . ' / ' . $benEp);

$rid = 'olcumisaret' . bin2hex(random_bytes(3));
$kayit = [
    'id' => $rid, 'slug' => $rid, 'baslik' => 'İşaret ölçümü çalışması',
    'yazar' => $benAd, 'tarih' => date('c'), 'tur' => 'yazi',
    /* Yazarlık e-postadan kurulur: ada dayalı eşleşme, hesabın kimlik
       doğrulaması onaylı değilse (bu ölçüm hesabında değil) bilerek
       kabul edilmiyor. */
    'yazar_bilgi' => ['ad' => $benAd, 'eposta' => $benEp],
    'ozet' => str_repeat('Ölçüm için yazılmış özet. ', 6),
    'metin' => '<p>' . str_repeat('Ölçüm gövdesi. ', 40) . '</p>',
];
/* Başkasının çalışması: aynı ölçümde ikinci kayıt. */
$rid2 = $rid . 'b';
$kayit2 = $kayit; $kayit2['id'] = $rid2; $kayit2['slug'] = $rid2;
$kayit2['yazar'] = 'Başka Biri'; $kayit2['baslik'] = 'Başkasının çalışması';
$kayit2['yazar_bilgi'] = ['ad' => 'Başka Biri', 'eposta' => 'baska@ornek-sinama.org'];
$yz[] = $kayit; $yz[] = $kayit2;
file_put_contents($yp, json_encode($yz, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

$r = ist('/api/yonetim/deneme-isaretle', ['y' => $rid2, 'gerekce' => 'Bu bir ölçüm gerekçesidir, yeterince uzun.']);
den('BAŞKASININ çalışması işaretlenemiyor', empty($r['ok']), (string)($r['hata'] ?? ''));
den('  gerekçe "kendi çalışmanız" diyor', str_contains((string)($r['hata'] ?? ''), 'kendi çalışmanızı'));

$r = ist('/api/yonetim/deneme-isaretle', ['y' => $rid, 'gerekce' => 'kısa']);
den('GEREKÇESİZ işaretlenemiyor', empty($r['ok']), (string)($r['hata'] ?? ''));

/* DOI verilmiş çalışma: kalıcı bir söz. */
$yz = json_decode((string)@file_get_contents($yp), true) ?: [];
foreach ($yz as $i => $e) if (($e['id'] ?? '') === $rid) $yz[$i]['doi'] = '10.00001/olcum';
file_put_contents($yp, json_encode($yz, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$r = ist('/api/yonetim/deneme-isaretle', ['y' => $rid, 'gerekce' => 'Bu bir ölçüm gerekçesidir, yeterince uzun.']);
den('DOI verilmiş çalışma işaretlenemiyor', empty($r['ok']), (string)($r['hata'] ?? ''));
den('  gerekçe DOI diyor', str_contains((string)($r['hata'] ?? ''), 'DOI'));

/* Üçüncü kişinin emeği: hakem raporu. */
$yz = json_decode((string)@file_get_contents($yp), true) ?: [];
foreach ($yz as $i => $e) if (($e['id'] ?? '') === $rid) {
    unset($yz[$i]['doi']);
    $yz[$i]['hakemler'] = [['ad' => 'Emek Veren Hakem', 'rapor' => str_repeat('Rapor gövdesi. ', 30), 'karar' => 'ret']];
}
file_put_contents($yp, json_encode($yz, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$r = ist('/api/yonetim/deneme-isaretle', ['y' => $rid, 'gerekce' => 'Bu bir ölçüm gerekçesidir, yeterince uzun.']);
den('BAŞKASININ EMEĞİ varsa işaretlenemiyor', empty($r['ok']), (string)($r['hata'] ?? ''));
den('  kimin emeği olduğu söyleniyor', str_contains((string)($r['hata'] ?? ''), 'Emek Veren Hakem'));

/* Kendi yazdığı deneme raporu engel DEĞİLDİR. */
$yz = json_decode((string)@file_get_contents($yp), true) ?: [];
foreach ($yz as $i => $e) if (($e['id'] ?? '') === $rid) {
    $yz[$i]['hakemler'] = [['ad' => $benAd, 'rapor' => str_repeat('Kendi deneme raporum. ', 20), 'karar' => 'ret']];
}
file_put_contents($yp, json_encode($yz, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

/* =====================================================================
   6. İŞARET: ARŞİVDEN DÜŞER, KAYIT DURUR, GERİ ALINIR
   ===================================================================== */
echo "\n== 6. İşaret: arşivden düşer, kayıt durur, geri alınır ==\n";
$oncePg = sayfa('/yazilar.php?lang=tr');
den('çalışma işaretten ÖNCE arşivde görünüyor', str_contains($oncePg, 'İşaret ölçümü çalışması'));
/* Site haritası ölçümü ADRESE bakar, kimliğe değil: haritada çalışma
   yolu (tamga ya da /yazi.php?y=) yazılıdır. Kayıt önce haritada
   GERÇEKTEN durmalı; durmuyorsa "düştü" ölçümü hiçbir şey ölçmez. */
$hrtOnce = sayfa('/sitemap.xml');
/* ARAMA DİZGESİ KAPALI OLMALI. İlk yazımda yalnız $rid aranıyordu ve
   ikinci ölçüm kaydının kimliği ($rid . 'b') onu İÇERİYOR: kapı,
   silinen kaydı hâlâ duruyor sanıyordu. Adres, kapanışıyla birlikte
   aranır. */
$hrtYol = 'y=' . $rid . '</loc>';
den('  site haritasında da var', str_contains($hrtOnce, $hrtYol), $hrtYol);

$r = ist('/api/yonetim/deneme-isaretle', ['y' => $rid, 'gerekce' => 'Deneme düzeninden önce gönderilmiş deneme kaydı.']);
den('kendi çalışması işaretlenebiliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));

$sonra = sayfa('/yazilar.php?lang=tr');
den('  arşiv listesinden düştü', !str_contains($sonra, 'İşaret ölçümü çalışması'));
den('  ana sayfada yok', !str_contains(sayfa('/?lang=tr'), 'İşaret ölçümü çalışması'));
den('  aramada yok', !str_contains(sayfa('/ara.php?q=' . rawurlencode('İşaret ölçümü')), 'İşaret ölçümü çalışması'));
den('  OAI çıktısında yok', !str_contains(sayfa('/oai?verb=ListRecords&metadataPrefix=oai_dc'), 'İşaret ölçümü çalışması'));
$hrtSonra = sayfa('/sitemap.xml');
den('  site haritasında yok', !str_contains($hrtSonra, $hrtYol));
/* Sayı da azalmalı. Kaç azaldığı kayda bağlıdır: çalışmanın kendi
   adresi düşer, o kişinin başka kamusal çalışması kalmadıysa kişi
   sayfası da düşer. Bu yüzden ölçüm sayıyı YAZAR, sabitlemez. */
olc('harita adresi: ' . substr_count($hrtOnce, '<loc>') . ' -> ' . substr_count($hrtSonra, '<loc>'));
den('  harita adres sayısı azaldı',
    substr_count($hrtSonra, '<loc>') < substr_count($hrtOnce, '<loc>'));
$kayitVar = false;
foreach (json_decode((string)@file_get_contents($yp), true) ?: [] as $e) {
    if (is_array($e) && ($e['id'] ?? '') === $rid) $kayitVar = true;
}
den('  KAYIT SİLİNMEDİ, yerinde duruyor', $kayitVar);

$im = ist('/api/yonetim/deneme-isaretliler');
$imVar = false; $imGer = '';
foreach (($im['liste'] ?? []) as $x) if (($x['anahtar'] ?? '') === $rid) { $imVar = true; $imGer = (string)($x['gerekce'] ?? ''); }
den('  işaretlenenler listesinde görünüyor', $imVar);
den('  gerekçe kayda geçmiş', str_contains($imGer, 'Deneme düzeninden önce'));

$r = ist('/api/yonetim/deneme-isaretle', ['y' => $rid, 'kaldir' => 1]);
den('işaret GERİ ALINABİLİYOR', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('  çalışma yeniden arşivde', str_contains(sayfa('/yazilar.php?lang=tr'), 'İşaret ölçümü çalışması'));

/* =====================================================================
   7. DÜZEN GERİ ALINIYOR
   ===================================================================== */
echo "\n== 7. Düzen geri alınıyor ==\n";
$yz = json_decode((string)@file_get_contents($yp), true) ?: [];
$kalan = [];
foreach ($yz as $e) {
    if (is_array($e) && in_array((string)($e['id'] ?? ''), [$rid, $rid2], true)) continue;
    $kalan[] = $e;
}
file_put_contents($yp, json_encode(array_values($kalan), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('ölçüm kayıtları silindi', count($kalan) === count($yz) - 2);
den('  arşivde iz kalmadı', !str_contains(sayfa('/yazilar.php?lang=tr'), 'İşaret ölçümü çalışması'));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
