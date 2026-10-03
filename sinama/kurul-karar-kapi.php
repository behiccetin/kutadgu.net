<?php
/* =====================================================================
   KURUL KARARLARI · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ — 19 Ağustos 2026: "kurucu danışma kurulu bir karara
   yazdı, sisteme onu nerede yazacak? Orası yok. Diğerleri nasıl
   oylayacak? O da yok."

   KUSURUN AĞIRLIĞI. Bu sistemin kuralları onlarca yerde kurulun kendi
   kararına dayanır: baş editörlüğün "kurucuların çoğunluk kararıyla"
   sona ermesi, bir metnin ilkelere uygunluğunun "yayın kurulunca"
   karara bağlanması, hakemlik yetkisinin askıya alınması. Yani sistem,
   KENDİSİNİ YÖNETEN yordamı ilan ediyor ama o yordamı yürütecek hiçbir
   yer taşımıyordu. Bu, "uygulanmayan kuralı duyurmak" sınıfının en ağır
   hâlidir; duyurulan şey, kuralların kendisini değiştiren kuraldır.

   BU KAPI DÖRT KURALI KİLİTLER:
     1. Karar YAZIYLA açılır (başlıksız/gerekçesiz karar açılamaz).
     2. Oy GEREKÇESİYLE verilir.
     3. Karar KALICIDIR: oy geri alınmaz, kapanmış oylamaya oy eklenmez.
     4. Sayım AÇIKTIR: kurul sayfasında ad ve gerekçeyle görünür.

   Ayrıca çoğunluğun "oy verenlerin" değil "oy VEREBİLECEKLERİN"
   yarısından fazlası olduğunu ölçer: aksi hâlde iki kişinin oyuyla beş
   kişilik bir kurul karar almış olurdu.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php kurul-karar-kapi.php
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

$KZ = ['bas' => '', 'ed' => ''];
$IP = '203.0.113.' . random_int(1, 254);
function kist(string $kim, string $yol, ?array $g = null): array {
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
$krl = (string)@file_get_contents($KOD . '/kurul.php');
den('kaynaklar okunabildi', $api !== '' && $krl !== '');
@unlink($VERI . '/hiz-sinir.json');
@unlink($VERI . '/giris-deneme.json');

/* ------------------------------------------------------------------ */
echo "\n== 1. Sayım TEK KAYNAKTA ==\n";
den('tg_kk_sonuc() var', function_exists('tg_kk_sonuc'));
/* PENCERE DAR TUTULUR. İlk yazımda bu ölçüm DOSYANIN TAMAMINDA
   '$kabul++' arıyordu ve iki yerde buldu: biri hakemin ret kararlarını
   sayan eski kod, öteki deneme akışının aşama hesabı. İkisinin de kurul
   kararlarıyla ilgisi yok. Bir kapı, ölçtüğü şeyin sınırını bilmiyorsa
   komşusunun işini kusur sayar. */
$kkB = strpos($api, "\$yol === '/yonetim/kurul-karar-ac'");
$kkS = $kkB !== false ? strpos($api, "\$yol === '/yonetim/deneme-giris'", $kkB) : false;
if ($kkB !== false && $kkS === false) $kkS = strlen($api);
$kkBlok = $kkB !== false ? substr($api, $kkB, $kkS - $kkB) : '';
den('  kurul karar bölümü bulundu', $kkBlok !== '', (string)strlen($kkBlok));
den('  uç kendi sayımını yapmıyor',
    substr_count($kkBlok, "tg_kk_sonuc(") >= 3 && !preg_match('/\$kabul\+\+|\$ret\+\+/', $kkBlok),
    (string)substr_count($kkBlok, 'tg_kk_sonuc('));
/* Çoğunluk oy VERENLERİN değil, oy VEREBİLECEKLERİN yarısından fazlası. */
$sahte = ['tur' => 'kurul', 'oylar' => [['karar' => 'kabul'], ['karar' => 'kabul']]];
$s = tg_kk_sonuc($sahte);
olc('oy verebilecek: ' . $s['kisi'] . ' · yeter sayı: ' . $s['yeter'] . ' · iki kabulle hâl: ' . $s['hal']);
den('yeter sayı = oy verebileceklerin yarısı + 1',
    $s['yeter'] === (int)floor($s['kisi'] / 2) + 1, (string)$s['yeter']);
den('  iki oyla beş kişilik kurul karar ALMIYOR',
    $s['kisi'] < 3 ? true : $s['hal'] === 'suruyor', $s['hal']);
/* Çekimser sayıya girer ama hiçbir yöne yazılmaz. */
$c = tg_kk_sonuc(['tur' => 'kurul', 'oylar' => [['karar' => 'cekimser']]]);
den('  çekimser hiçbir yöne yazılmıyor', $c['say']['kabul'] === 0 && $c['say']['ret'] === 0);
den('  ama verilen oy sayısına giriyor', $c['verilen'] === 1);

/* ------------------------------------------------------------------ */
echo "\n== 2. Kimlik ==\n";
$r = kist('bas', '/api/yonetim/kurul-karar-ac', ['baslik' => 'Kimliksiz deneme', 'metin' => str_repeat('x', 250)]);
den('kimliksiz karar açılamıyor', empty($r['ok']), (string)($r['hata'] ?? ''));
kist('bas', '/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
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
kist('ed', '/api/hesap/giris', ['kim' => 'sadeeditor', 'parola' => 'sade1234']);
$r = kist('ed', '/api/yonetim/kurul-karar-ac', ['baslik' => 'Editör denemesi', 'metin' => str_repeat('x', 250)]);
den('sıradan editör kurul kararı AÇAMIYOR', empty($r['ok']), (string)($r['hata'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 3. Karar YAZIYLA açılır ==\n";
$metin = 'Kurul, hakemlik yetkisinin askıya alınması yordamını görüşmüş ve yazılı hâle getirilmesine karar vermiştir. '
       . 'Yordam yazılı olmadıkça uygulanması denetlenemez; denetlenemeyen bir yaptırım, yaptırım değil keyfîliktir. ';
$r = kist('bas', '/api/yonetim/kurul-karar-ac', ['baslik' => 'Kısa', 'metin' => $metin]);
den('başlıksız/kısa başlıklı karar açılamıyor', empty($r['ok']), (string)($r['hata'] ?? ''));
$r = kist('bas', '/api/yonetim/kurul-karar-ac', ['baslik' => 'Hakemlik askı yordamı ölçümü', 'metin' => 'çok kısa']);
den('  gerekçesiz karar açılamıyor', empty($r['ok']), (string)($r['hata'] ?? ''));
den('    gerekçe "önce yazılmış olmalı" diyor',
    str_contains((string)($r['hata'] ?? ''), 'oy verilmeden önce'), (string)($r['hata'] ?? ''));
$baslik = 'Ölçüm kararı ' . bin2hex(random_bytes(4));
$r = kist('bas', '/api/yonetim/kurul-karar-ac', ['baslik' => $baslik, 'metin' => $metin]);
den('geçerli karar açıldı', !empty($r['ok']), (string)($r['hata'] ?? ''));
$kod = (string)($r['kod'] ?? '');
den('  kararın kodu var', $kod !== '');
den('  açılışta oylama SÜRÜYOR', (($r['sonuc']['hal'] ?? '') === 'suruyor'), (string)($r['sonuc']['hal'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 4. Oy gerekçesiyle verilir, geri alınmaz ==\n";
$r = kist('bas', '/api/yonetim/kurul-karar-oy', ['kod' => $kod, 'karar' => 'kabul', 'gerekce' => 'olur']);
den('gerekçesiz oy reddediliyor', empty($r['ok']), (string)($r['hata'] ?? ''));
$r = kist('bas', '/api/yonetim/kurul-karar-oy', ['kod' => $kod, 'karar' => 'belki',
    'gerekce' => str_repeat('Gerekçe cümlesi. ', 5)]);
den('  tanınmayan oy reddediliyor', empty($r['ok']), (string)($r['hata'] ?? ''));
$ger = 'Yordamın yazılı olması denetlenebilirlik için gereklidir; bu yüzden kabul ediyorum.';
$r = kist('bas', '/api/yonetim/kurul-karar-oy', ['kod' => $kod, 'karar' => 'kabul', 'gerekce' => $ger]);
den('  gerekçeli oy kabul ediliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
den('    sayım güncellendi', (int)($r['sonuc']['say']['kabul'] ?? 0) === 1, json_encode($r['sonuc']['say'] ?? []));
$r = kist('bas', '/api/yonetim/kurul-karar-oy', ['kod' => $kod, 'karar' => 'ret', 'gerekce' => $ger]);
den('  aynı kişi ikinci kez oy VEREMİYOR', empty($r['ok']), (string)($r['hata'] ?? ''));
den('    gerekçe "geri alınmaz" diyor',
    str_contains((string)($r['hata'] ?? ''), 'geri alınmaz'), (string)($r['hata'] ?? ''));
$r = kist('ed', '/api/yonetim/kurul-karar-oy', ['kod' => $kod, 'karar' => 'kabul', 'gerekce' => $ger]);
den('  sıradan editör oy VEREMİYOR', empty($r['ok']), (string)($r['hata'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 5. Kurucu kararını yalnız kurucu oylar ==\n";
$r = kist('bas', '/api/yonetim/kurul-karar-ac',
    ['tur' => 'kurucu', 'baslik' => 'Kurucu ölçüm kararı ' . bin2hex(random_bytes(3)), 'metin' => $metin]);
$kurucuAcabildi = !empty($r['ok']);
olc('ölçüm hesabı kurucu mu: ' . ($kurucuAcabildi ? 'evet' : 'hayır'));
if ($kurucuAcabildi) {
    $kod2 = (string)$r['kod'];
    $d2 = kist('bas', '/api/kurul-kararlari');
    $bulundu = null;
    foreach (($d2['kararlar'] ?? []) as $k) if (($k['kod'] ?? '') === $kod2) $bulundu = $k;
    den('kurucu kararı listede', $bulundu !== null);
    den('  oy verebilecek sayısı kurucularla sınırlı',
        (int)($bulundu['sonuc']['kisi'] ?? 0) <= (int)($s['kisi']), json_encode($bulundu['sonuc'] ?? []));
} else {
    den('kurucu olmayan baş editör kurucu kararı AÇAMIYOR', true);
    den('  gerekçe kurucuyu söylüyor', str_contains((string)($r['hata'] ?? ''), 'kurucu'), (string)($r['hata'] ?? ''));
}

/* ------------------------------------------------------------------ */
echo "\n== 5.b Oyu bekleyen karar SAHİBİNE HABER VERİLİYOR ==\n";
/* ÖLÇÜLEN KUSUR — 19 Ağustos 2026: kurul kararları açıldığı gün
   "Sizi bekleyen işler" listesi onları saymıyordu. Kartın kendi sözü
   "yalnızca sizden bir şey bekleyen işler burada görünür"dür; oy
   bekleyen bir karar tam olarak odur. Kimsenin bilmediği bir oylama
   hiç yapılmaz. */
/* ÖLÇÜM MUTLAK DEĞİL, FARK ÜZERİNDEN YAPILIR. İlk yazımda "hiç satır
   olmamalı" deniyordu; oysa bu kapı yukarıda bir KURUCU kararı açıp
   oylamadan bırakıyor, yani sayaç zaten bir. Kapı kendi bıraktığı işi
   kusur saydı. Sayılan şey bir sayı değil, DEĞİŞİMdir. */
$kkSay = function (array $ozet): int {
    $n = 0;
    foreach (($ozet['bekleyen'] ?? []) as $bk) {
        if (($bk['tur'] ?? '') !== 'kurul') continue;
        if (preg_match('/(\d+)/', (string)($bk['baslik'] ?? ''), $m)) $n += (int)$m[1];
    }
    return $n;
};
$once = $kkSay(kist('bas', '/api/panel/ozet'));
olc('şu an oyu bekleyen karar: ' . $once);
$b2 = 'Bekleyen ölçüm kararı ' . bin2hex(random_bytes(4));
$r = kist('bas', '/api/yonetim/kurul-karar-ac', ['baslik' => $b2, 'metin' => $metin]);
den('  ikinci karar açıldı', !empty($r['ok']), (string)($r['hata'] ?? ''));
$oz = kist('bas', '/api/panel/ozet');
$kurulSatiri = null;
foreach (($oz['bekleyen'] ?? []) as $bk) if (($bk['tur'] ?? '') === 'kurul') $kurulSatiri = $bk;
$sonra = $kkSay($oz);
den('  oy verilmemiş karar BEKLEYEN İŞLERE eklendi', $sonra === $once + 1, $once . ' -> ' . $sonra);
den('    satır çiziliyor', $kurulSatiri !== null);
if ($kurulSatiri) {
    olc('satır: ' . (string)$kurulSatiri['baslik']);
    /* HEDEF, İŞİN YAPILDIĞI YERDİR. Bu ölçüm 19 Ağustos'ta '/kurul.php'
       arıyordu ve geçiyordu; oysa /kurul.php kararların OKUNDUĞU kamusal
       sayfadır, oyun VERİLDİĞİ yer değil. Kapı, bildirimin bir adresi
       olmasını ölçüyor ama adresin DOĞRU yer olup olmadığını
       ölçmüyordu. Ölçüt artık şudur: hedef, oyun verildiği panel
       kartını gösterir ve o kart panelde gerçekten vardır. */
    $kkYol = (string)($kurulSatiri['yol'] ?? '');
    den('    satır kararın OYLANDIĞI yeri gösteriyor',
        str_contains($kkYol, '/panel.php#') && str_contains($kkYol, 'kartKarar'), $kkYol);
    den('    gösterdiği kart panelde var',
        str_contains((string)@file_get_contents($KOD . '/panel.php'), 'id="kartKarar"'));
}
/* Oy verilince satır düşer: iş bittiğinde listeden çıkmayan bir kart,
   bir süre sonra hiç okunmaz. */
kist('bas', '/api/yonetim/kurul-karar-oy', ['kod' => (string)($r['kod'] ?? ''), 'karar' => 'kabul', 'gerekce' => $ger]);
den('  oy verilince sayaç düşüyor', $kkSay(kist('bas', '/api/panel/ozet')) === $once,
    (string)$kkSay(kist('bas', '/api/panel/ozet')));
/* Sıradan editörün listesinde ASLA görünmez: kapatamayacağı bir işi
   listesinde taşımak, listeyi anlamsız kılar. */
$ozE = kist('ed', '/api/panel/ozet');
$edKurul = false;
foreach (($ozE['bekleyen'] ?? []) as $bk) if (($bk['tur'] ?? '') === 'kurul') $edKurul = true;
den('  sıradan editörün listesinde YOK', !$edKurul);

/* ------------------------------------------------------------------ */
echo "\n== 6. Sayım AÇIK: kurul sayfasında ad ve gerekçeyle ==\n";
$sf = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/kurul.php?lang=tr');
den('kurul sayfası açıldı', $sf !== '');
den('  kararlar bölümü var', str_contains($sf, 'id="kararlar"'));
den('  kararın başlığı görünüyor', str_contains($sf, $baslik), $baslik);
den('  oyu veren ADIYLA görünüyor', str_contains($sf, 'kk-oy'));
den('  gerekçe de görünüyor', str_contains($sf, 'denetlenebilirlik için gereklidir'));
den('  yeter sayı yazılı', str_contains($sf, 'yeter sayı'));
/* Adres sızmaz: kim oy verdi adıyla görünür, adresiyle değil. */
den('  sayfada oy verenin ADRESİ yok', !str_contains($sf, 'cbehic@gmail.com'));
$kam = kist('ed', '/api/kurul-kararlari');
den('okuma ucu kamusal (giriş gerektirmiyor)', !empty($kam['ok']));
den('  uç yanıtında e-posta alanı yok',
    !preg_match('/"eposta"\s*:/', json_encode($kam, JSON_UNESCAPED_UNICODE)));

/* ------------------------------------------------------------------ */
echo "\n== 7. Çalışma oylamasıyla karışmıyor ==\n";
/* Kaldırılan oylama.php'nin kusuru ikisini bir listede toplamaktı. */
den('kurul kararları ayrı bir kaynakta', str_contains($api, "'kurul-kararlari.json'"));
den('  çalışma oylaması hâlâ çalışmanın kendi kaydında', function_exists('tg_oylamalar'));
den('  kurul sayfası ayrımı yazıyor',
    str_contains($sf, 'o çalışmanın kendi sayfasında'));
/* ADI GEÇEN AMA YERİ GÖSTERİLMEYEN BİR KAYIT, OLMAYAN BİR KAYITTAN
   YALNIZ BİRAZ DAHA İYİDİR. */
$ilk = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/ilkeler.php?lang=tr');
den('ilkeler sayfası kararlara bağ veriyor', str_contains($ilk, '/kurul.php#kararlar')
    || str_contains($ilk, 'kurul.php?lang=tr#kararlar'), 'bağ yok');

/* ------------------------------------------------------------------ */
echo "\n== 8. Ölçüm izleri geri alındı ==\n";
$p = $VERI . '/kurul-kararlari.json';
$kal = [];
foreach (json_decode((string)@file_get_contents($p), true) ?: [] as $k) {
    if (is_array($k) && str_starts_with((string)($k['baslik'] ?? ''), 'Ölçüm kararı ')) continue;
    if (is_array($k) && str_starts_with((string)($k['baslik'] ?? ''), 'Bekleyen ölçüm kararı ')) continue;
    if (is_array($k) && str_starts_with((string)($k['baslik'] ?? ''), 'Kurucu ölçüm kararı ')) continue;
    $kal[] = $k;
}
file_put_contents($p, json_encode($kal, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('ölçüm kararları silindi', true);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
