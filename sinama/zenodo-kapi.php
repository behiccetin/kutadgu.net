<?php
/* =====================================================================
   ZENODO · KALICI KİMLİK (DOI) · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL SORUSU — 20 Ağustos 2026: "Zenodo'dan her eklenen yazı için DOI
   alınabilir mi?"

   ALINABİLİR, AMA GERİ ALINAMAZ BİR İŞTİR. Zenodo'nun kendi kuralı
   şudur: "yayımlanmış bir kayıt silinemez" ve DOI kalıcıdır. Bu yüzden
   bu sistemde iş ÜÇE bölündü ve kapı üçünü ayrı ayrı ölçer:

     önizleme  ağa hiç çıkmaz; ne gönderileceğini gönderilmeden gösterir
     taslak    geri alınabilir (Zenodo'da silinebilir)
     yayımlama GERİ ALINAMAZ; açık onay ister, kendiliğinden çağrılmaz

   BU KAPI ZENODO'YA BAĞLANMAZ. Bağlansaydı, ölçüm için gerçek kayıtlar
   açması gerekirdi; gerçek kayıt açan bir kapı, her çalıştığında kalıcı
   çöp üretirdi. Ölçülen şey üstveri eşlemesi, kapılar ve sırdır — üçü
   de ağdan bağımsızdır.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php zenodo-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = 'kutadgu.net';
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
function ist(string $yol, ?array $g = null, string $metod = ''): array {
    global $CK, $IP;
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nX-Forwarded-For: $IP\r\n";
    if ($CK !== '') $bas .= 'Cookie: ' . $CK . "\r\n";
    $se = ['method' => $metod !== '' ? $metod : ($g === null ? 'GET' : 'POST'),
           'header' => $bas, 'timeout' => 25, 'ignore_errors' => true];
    if ($g !== null) $se['content'] = json_encode($g, JSON_UNESCAPED_UNICODE);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => $se]));
    foreach (($http_response_header ?? []) as $x) {
        if (stripos($x, 'Set-Cookie: PHPSESSID') === 0) $CK = explode(';', trim(substr($x, 11)))[0];
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}

$api = (string)@file_get_contents($KOD . '/api/index.php');
$pnl = (string)@file_get_contents($KOD . '/panel.php');
den('kaynaklar okunabildi', $api !== '' && $pnl !== '');
@unlink($VERI . '/hiz-sinir.json');
@unlink($VERI . '/giris-deneme.json');

/* =====================================================================
   1. ÜSTVERİ EŞLEMESİ · AĞSIZ
   ===================================================================== */
echo "\n== 1. Üstveri eşlemesi ==\n";
$yaz = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [];
$y = null;
foreach ($yaz as $e) { if (is_array($e) && trim((string)($e['bcid'] ?? '')) !== '' && empty($e['deneme'])) { $y = $e; break; } }
den('ölçülecek çalışma bulundu', is_array($y));
if (is_array($y)) {
    $u = tg_zenodo_ustveri($y)['metadata'];
    /* Zenodo'nun ZORUNLU alanları (developers.zenodo.org): title,
       creators, description, publication_date, publication_type,
       access_right, license. Biri eksikse kayıt yayımlanamaz ve bunu
       ancak yayımlamaya basınca öğrenirdiniz. */
    foreach (['title', 'creators', 'description', 'publication_date', 'publication_type', 'access_right', 'license'] as $z) {
        den('  zorunlu alan var: ' . $z, isset($u[$z]) && $u[$z] !== '' && $u[$z] !== []);
    }
    den('  tür yayın/makale', ($u['upload_type'] ?? '') === 'publication' && ($u['publication_type'] ?? '') === 'article');
    den('  erişim açık ve lisans CC BY', ($u['access_right'] ?? '') === 'open' && ($u['license'] ?? '') === 'cc-by-4.0');
    den('  tarih ISO 8601 (YYYY-AA-GG)', (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($u['publication_date'] ?? '')));
    den('  dil ISO 639 kodu', (bool)preg_match('/^[a-z]{3}$/', (string)($u['language'] ?? '')));
    /* Yazar adı Zenodo'nun istediği biçimde: "Soyad, Ad". Unvan kimliğin
       parçası değildir; "Dr." ile kaydedilen bir ad, dizinlerde başka
       bir kişi gibi görünür. */
    $ilk = (string)($u['creators'][0]['name'] ?? '');
    olc('yazar: ' . $ilk);
    den('  yazar adı "Soyad, Ad" biçiminde', strpos($ilk, ',') !== false, $ilk);
    den('  unvan ada karışmamış', !preg_match('/^(Dr|Prof|Doç|Doc|Öğr)\.?/iu', $ilk), $ilk);
    /* Kutadgu sayfası kayda BAĞLANIR: iki yerde duran aynı çalışma,
       birbirine bağlı olmazsa iki ayrı çalışma gibi görünür. */
    $bag = '';
    foreach ((array)($u['related_identifiers'] ?? []) as $b) if (($b['relation'] ?? '') === 'isIdenticalTo') $bag = (string)$b['identifier'];
    den('  Kutadgu adresi "aynıdır" bağıyla yazılı', strpos($bag, tg_kok()) === 0, $bag);
    /* Aynı adres iki kez yazılmaz. */
    $adresler = array_column((array)($u['related_identifiers'] ?? []), 'identifier');
    den('  bağlı kimliklerde yineleme yok', count($adresler) === count(array_unique($adresler)));
    /* Not alanı dürüstlük alanıdır: dizin yalnız üstveriyi okur. */
    den('  notta değerlendirme aşaması yazılı', strpos((string)($u['notes'] ?? ''), 'Değerlendirme') !== false);
    den('  notta parmak izi yazılı', strpos((string)($u['notes'] ?? ''), 'Parmak izi') !== false);

    /* Zenodo en az bir dosya ister (kendi SSS'i). */
    $dos = tg_zenodo_dosyalar($y);
    olc('dosyalar: ' . implode(' · ', array_map(fn($k, $v) => $k . ' (' . strlen($v) . 'B)', array_keys($dos), $dos)));
    den('  en az bir dosya üretiliyor', count($dos) >= 1);
    den('  dosyaların hiçbiri boş değil', count(array_filter($dos, fn($v) => strlen($v) > 200)) === count($dos));
    $htmlAd = array_values(array_filter(array_keys($dos), fn($k) => substr($k, -5) === '.html'));
    den('  metin tek dosyalık HTML olarak konuyor', count($htmlAd) === 1);
    if ($htmlAd) {
        $h = $dos[$htmlAd[0]];
        den('    HTML çalışmanın başlığını taşıyor', strpos($h, trim(strip_tags((string)($y['baslik'] ?? '')))) !== false);
        den('    HTML Kutadgu adresini taşıyor', strpos($h, tg_kok()) !== false);
        den('    HTML dışarıdan hiçbir şey çekmiyor',
            !preg_match('/<(script|link|img)\b[^>]*(src|href)=["\']https?:/i', $h));
    }
}

/* =====================================================================
   2. DENEME EVRENİ VE DUYURULAN KURAL
   ===================================================================== */
echo "\n== 2. Duyurulan kural, uygulanan kural ==\n";
$z = tg_zenodo_ayar();
olc('açık: ' . var_export(tg_zenodo_acik(), true) . ' · deneme evreni: ' . var_export($z['sandbox'], true)
    . ' · taban: ' . tg_zenodo_taban());
den('deneme evreninde taban sandbox.zenodo.org',
    !$z['sandbox'] || strpos(tg_zenodo_taban(), 'sandbox.zenodo.org') !== false);
den('deneme evreninin öneki 10.5072', !$z['sandbox'] || tg_zenodo_onek() === '10.5072');
/* JETONSUZ AÇIK OLMAZ: ayarda "açık" yazması, işin yapılabildiği
   anlamına gelmez. Sistem uygulayamadığı kuralı duyurmaz. */
den('jeton yoksa düzen KAPALI sayılıyor', str_contains($api, "\$a['acik'] && \$a['jeton'] !== ''")
    || str_contains((string)@file_get_contents($KOD . '/ortak.php'), "return \$a['acik'] && \$a['jeton'] !== '';"));
/* Cümle ancak uygulanıyorsa görünür; deneme evreninde "verilir" demez. */
$cum = tg_zenodo_cumlesi(false);
if (tg_zenodo_acik() && $z['sandbox']) {
    den('deneme evreninde cümle "sınanıyor" diyor', str_contains($cum, 'DENEME') || str_contains($cum, 'deneme'), $cum);
    den('  deneme evreninde "verilir" diye SÖZ VERMİYOR', !str_contains($cum, 'verilir'), $cum);
}
if (!tg_zenodo_acik()) den('düzen kapalıyken cümle BOŞ', $cum === '', $cum);
/* Güven işareti: deneme evreninde hiçbir şey iddia edilmez. */
$g = tg_guven_isaretleri();
if ($z['sandbox']) den('deneme evreninde DOI işareti "alındı" DEMİYOR', ($g['doi']['durum'] ?? '') !== 'alindi',
    (string)($g['doi']['durum'] ?? ''));
/* Sayfa da aynı şeyi söylüyor mu (nasil-isler.php cümleyi tek kaynaktan alır) */
$ni = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/nasil-isler.php?lang=tr');
den('sayfa cümleyi tek kaynaktan basıyor', $cum === '' ? !str_contains($ni, 'Zenodo (CERN) üzerinden kalıcı')
                                                       : str_contains($ni, $cum), '');

/* =====================================================================
   3. KAPILAR · YETKİ, ONAY, SIR
   ===================================================================== */
echo "\n== 3. Kapılar ==\n";
$r = ist('/api/yonetim/zenodo-durum');
den('kimliksiz durum SORULAMIYOR', empty($r['ok']), (string)($r['hata'] ?? ''));
$r = ist('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
den('ölçüm hesabıyla girildi', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* Ayarı ölçüm için kur, sonunda geri al. */
$ayarYolu = $VERI . '/yonetim-ayar.json';
$ayarYedek = (string)@file_get_contents($ayarYolu);
ist('/api/yonetim/zenodo-ayar', ['jeton' => 'OLCUM-JETON-9876', 'sandbox' => true, 'acik' => true]);

$d = ist('/api/yonetim/zenodo-durum');
den('durum okunuyor', !empty($d['ok']));
/* SIR EKRANA GERİ DÖNMEZ. Bir jetonun ekrana gelmesi, onu tarayıcı
   belleğine ve ekran görüntüsüne taşımaktır. */
olc('jeton alanı: ' . (string)($d['jeton'] ?? ''));
den('  jeton olduğu gibi DÖNMÜYOR', ($d['jeton'] ?? '') !== 'OLCUM-JETON-9876');
den('  yalnız son dört hane görünüyor', (string)($d['jeton'] ?? '') === '****9876');
den('  kurulu olduğu bildiriliyor', !empty($d['kurulu']));
$ham = json_encode($d, JSON_UNESCAPED_UNICODE);
den('  yanıtın hiçbir yerinde jeton geçmiyor', strpos((string)$ham, 'OLCUM-JETON-9876') === false);

/* Maskeli değer geri gönderilirse jeton SİLİNMEZ. */
ist('/api/yonetim/zenodo-ayar', ['jeton' => '****9876', 'sandbox' => true, 'acik' => true]);
$d2 = ist('/api/yonetim/zenodo-durum');
den('maskeli değer kaydedilince jeton silinmiyor', !empty($d2['kurulu']) && ($d2['jeton'] ?? '') === '****9876');

$anah = (string)($y['slug'] ?? ($y['id'] ?? ''));
/* ÖNİZLEME AĞA ÇIKMAZ: jeton olmasa da çalışır ve hiçbir şey yazmaz. */
$o = ist('/api/yonetim/zenodo-onizleme?y=' . rawurlencode($anah));
den('önizleme çalışıyor', !empty($o['ok']), (string)($o['hata'] ?? ''));
den('  önizleme üstveriyi olduğu gibi gösteriyor',
    (string)($o['ustveri']['title'] ?? '') === trim(strip_tags((string)($y['baslik'] ?? ''))));
den('  önizleme dosyaları sayıyor', count((array)($o['dosyalar'] ?? [])) >= 1);

/* YAYIMLAMA GERİ ALINAMAZ: açık onay olmadan çalışmaz. */
$p1 = ist('/api/yonetim/zenodo-yayimla', ['y' => $anah]);
den('ONAYSIZ yayımlanmıyor', empty($p1['ok']), (string)($p1['hata'] ?? ''));
den('  gerekçe geri alınamazlığı söylüyor', str_contains((string)($p1['hata'] ?? ''), 'geri alınamaz'));
/* Onay verilse bile taslaksız yayımlanmaz. */
$p2 = ist('/api/yonetim/zenodo-yayimla', ['y' => $anah, 'onay' => '1']);
den('taslaksız yayımlanmıyor', empty($p2['ok']), (string)($p2['hata'] ?? ''));
/* Deneme kaydına kimlik verilmez. */
$dn = null;
foreach ($yaz as $e) if (is_array($e) && !empty($e['deneme'])) $dn = (string)($e['slug'] ?? ($e['id'] ?? ''));
if ($dn !== null) {
    $p3 = ist('/api/yonetim/zenodo-taslak', ['y' => $dn]);
    den('deneme kaydına taslak açılmıyor', empty($p3['ok']), (string)($p3['hata'] ?? ''));
}
/* Düzen kapalıyken taslak da açılmaz. */
ist('/api/yonetim/zenodo-ayar', ['acik' => false]);
$p4 = ist('/api/yonetim/zenodo-taslak', ['y' => $anah]);
den('düzen kapalıyken taslak açılmıyor', empty($p4['ok']), (string)($p4['hata'] ?? ''));

/* =====================================================================
   4. KENDİLİĞİNDEN YAYIMLAMA YOK
   ===================================================================== */
echo "\n== 4. Kendiliğinden yayımlama yok ==\n";
/* Yayımlama ucu YALNIZCA kendi bloğunda geçer: başka bir uç ya da
   görev onu çağırıyorsa, geri alınamaz bir iş kendiliğinden olur. */
$sayi = substr_count($api, 'actions/publish');
olc("kaynakta 'actions/publish' geçiş sayısı: " . $sayi);
den('yayımlama çağrısı tek yerde', $sayi === 1, (string)$sayi);
den('  yayımlama ucu açık onay arıyor', str_contains($api, "(string)(\$g['onay'] ?? '') !== '1'"));
den('  kim yayımladığı kayda yazılıyor', str_contains($api, "'kim' => hs_gorunen_ad(hs_gerek())"));
/* Deneme evreninin kimliği çalışmanın kalıcı DOI alanına YAZILMAZ:
   o alan dışarıya verilen üstveride kalıcı kimlik olarak görünür. */
den('  deneme kimliği çalışmanın doi alanına yazılmıyor',
    str_contains($api, "if (\$gercek && \$doi !== '') \$y[\$i]['doi'] = \$doi;"));
/* Panel de aynı şeyi söylüyor: yayımlama ayrı düğme ve ayrı onay. */
den('panelde yayımlama ayrı düğme', str_contains($pnl, 'zn-yayimla'));
den('  panel geri alınamazlığı yazıyor', str_contains($pnl, 'SİLİNEMEZ'));
den('  panel jetonu parola alanında istiyor', str_contains($pnl, 'type="password" id="znJeton"'));

/* =====================================================================
   5. DÜZEN GERİ ALINIYOR
   ===================================================================== */
echo "\n== 5. Düzen geri alınıyor ==\n";
if ($ayarYedek !== '') file_put_contents($ayarYolu, $ayarYedek); else @unlink($ayarYolu);
$son = ist('/api/yonetim/zenodo-durum');
den('ölçüm jetonu kaldırıldı', empty($son['kurulu']) || ($son['jeton'] ?? '') !== '****9876');

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
