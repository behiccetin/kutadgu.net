<?php
/* =====================================================================
   YAPILANDIRILMIŞ ÖZ · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   NEREDEN GELDİ. ScholarOne/IJPDLM karşılaştırmasının 7. maddesi: öz
   dört başlıkla isteniyor (Purpose / Design / Findings / Originality).
   "Büyük iş" diye ertelenmişti; büyük olmasının nedeni her tüketiciye
   (PDF, OAI, döküm, site haritası, atıf künyesi) ayrı yapı bilgisi
   taşımak sanılmasıydı.

   KARAR: 'ozet' KANONİK KALIR ve yapı verildiğinde ondan TÜRETİLİR.
   Böylece hiçbir tüketici değişmedi. Bu kapının birinci işi, o kararın
   BOZULMADIĞINI ölçmektir: bir gün biri 'ozet'i yapıdan bağımsız
   yazarsa, kayıt iki ayrı özet taşımaya başlar.

   İKİNCİ KARAR: YAPI ZORUNLU DEĞİL. Kutadgu bütün FORD alanlarına
   açıktır; bir felsefe denemesine "araştırma tasarımı" sormak sorunun
   kendisini anlamsız kılar. Zorunlu olsaydı yazar olmayan bir yöntem
   uydururdu — uydurulan bir alan boş bir alandan kötüdür. Kapı, yapının
   zorunlu OLMADIĞINI da ölçer.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php ozet-yapi-kapi.php
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

$api = (string)@file_get_contents($KOD . '/api/index.php');
$bsv = (string)@file_get_contents($KOD . '/basvuru.php');
$yzr = (string)@file_get_contents($KOD . '/yazar.php');
$yzi = (string)@file_get_contents($KOD . '/yazi.php');
den('kaynaklar okunabildi', $api !== '' && $bsv !== '' && $yzr !== '' && $yzi !== '');

/* ------------------------------------------------------------------ */
echo "\n== 1. Bölümler tek kaynaktan ==\n";
den('tg_ozet_bolumleri() var', function_exists('tg_ozet_bolumleri'));
$bol = tg_ozet_bolumleri();
olc('bölümler: ' . implode(' · ', array_map(fn($b) => $b['tr'], $bol)));
den('  dört bölüm', count($bol) === 4, (string)count($bol));
$eksik = [];
foreach ($bol as $b) if (($b['tr'] ?? '') === '' || ($b['en'] ?? '') === '' || ($b['k'] ?? '') === '') $eksik[] = json_encode($b);
den('  hepsinin anahtarı ve iki dilde adı var', !$eksik, implode(',', $eksik));
/* Bölüm adları sayfalarda ELLE yazılmamalı: yazılsaydı liste
   değiştiğinde biri ötekinden habersiz kalırdı. */
foreach ([['basvuru.php', $bsv], ['yazar.php', $yzr]] as [$ad, $kaynak]) {
    den('  ' . $ad . ' bölümleri listeden basıyor', str_contains($kaynak, 'tg_ozet_bolumleri()'));
}
den('  yazi.php bölüm adını listeden okuyor', str_contains($yzi, 'tg_ozet_bolum_ad('));

/* ------------------------------------------------------------------ */
echo "\n== 2. Temizleme ve türetme ==\n";
$ham = ['bulgu' => "  Üç \n bulgu.", 'amac' => 'Amacı denemek.', 'uydurma' => 'x', 'ozgun' => '   '];
$y1 = tg_ozet_yapi_temizle($ham);
den('tanınmayan anahtar atılıyor', count($y1) === 2, (string)count($y1));
den('  boş bölüm atılıyor', !in_array('ozgun', array_column($y1, 'k'), true));
/* SIRA GÖNDERENİN DEĞİL, LİSTENİN SIRASIDIR: yoksa aynı öz iki kayıtta
   iki ayrı sırayla okunurdu. */
den('  sıra bölüm listesinin sırası', array_column($y1, 'k') === ['amac', 'bulgu'],
    implode(',', array_column($y1, 'k')));
den('  boşluklar tekleniyor', ($y1[1]['m'] ?? '') === 'Üç bulgu.', (string)($y1[1]['m'] ?? ''));
den('yarım yapı hiç yapı değil (hepsi boşsa boş döner)',
    tg_ozet_yapi_temizle(['amac' => '  ', 'bulgu' => '']) === []);
den('dizi olmayan girdi çökmüyor', tg_ozet_yapi_temizle('metin') === []);
$oz = tg_ozet_yapidan($y1);
olc('türetilen özet: ' . $oz);
den('türetilen özet bölüm adlarını taşıyor', str_contains($oz, 'Amaç:') && str_contains($oz, 'Bulgular:'));
den('  İngilizcesi de türetilebiliyor', str_contains(tg_ozet_yapidan($y1, true), 'Findings:'));

/* ------------------------------------------------------------------ */
echo "\n== 3. Çelişki bırakılmıyor ==\n";
den('yapı özeti anlatıyorsa döner', count(tg_ozet_yapisi(['ozet' => $oz, 'ozet_yapi' => $y1])) === 2);
den('  anlatmıyorsa YAPI YOK SAYILIR',
    tg_ozet_yapisi(['ozet' => 'bambaşka bir özet', 'ozet_yapi' => $y1]) === []);
den('  yapısı olmayan kayıt boş döner', tg_ozet_yapisi(['ozet' => 'serbest metin']) === []);

/* ------------------------------------------------------------------ */
echo "\n== 4. 'ozet' KANONİK KALDI (tüketiciler değişmedi) ==\n";
/* Bu bölüm kararın kendisini korur. Bir tüketici bir gün 'ozet_yapi'
   okumaya başlarsa, aynı olgu iki yerde yazılmış olur. */
foreach (['oai.php', 'dokum.php', 'kart.php', 'sitemap.php', 'k/seo.php'] as $t) {
    $iz = (string)@file_get_contents($KOD . '/' . $t);
    den('  ' . $t . ' hâlâ yalnız \'ozet\' okuyor', $iz !== '' && !str_contains($iz, 'ozet_yapi'), $t);
}
den('yapı sunucuda türetiliyor (tarayıcıya güvenilmiyor)',
    str_contains($api, '$mOzetYapi = tg_ozet_yapi_temizle(') && str_contains($api, '$mOzet = mb_substr(tg_ozet_yapidan($mOzetYapi)'));

/* ------------------------------------------------------------------ */
echo "\n== 5. Yapı ZORUNLU DEĞİL ==\n";
$KEREZ = '';
$IP = '203.0.113.' . random_int(1, 254);
function bist(string $yol, array $g): array {
    global $KEREZ, $IP;
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nX-Forwarded-For: $IP\r\n";
    if ($KEREZ !== '') $bas .= 'Cookie: ' . $KEREZ . "\r\n";
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => [
        'method' => 'POST', 'header' => $bas, 'content' => json_encode($g, JSON_UNESCAPED_UNICODE),
        'timeout' => 15, 'ignore_errors' => true]]));
    foreach (($http_response_header ?? []) as $x) {
        if (stripos($x, 'Set-Cookie: PHPSESSID') === 0) $KEREZ = explode(';', trim(substr($x, 11)))[0];
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 160)];
}
bist('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
$metin = str_repeat('Ölçüm için yazılmış bir tam metin cümlesi. ', 200);
$temel = [
    'makale_baslik' => 'Yapılandırılmış öz ölçümü', 'makale_dil' => 'tr',
    'makale_baslik_en' => 'Structured abstract measurement',
    'makale_ozet_en' => 'A measurement record for the structured abstract.',
    /* GENİŞLETİLMİŞ ÖZET DE VERİLİR. Çalışmanın dili künye dilinden
       başka olduğunda zorunludur (ayar.php). Verilmeseydi bu kapı,
       ölçmek istediği yapı kuralına hiç ulaşamadan o kurala takılırdı —
       ve "yapı reddedildi" diye yanlış bir sonuç verirdi. */
    'makale_genis_ozet_en' => str_repeat('This is a measurement sentence for the extended abstract. ', 90),
    'makale_metin' => $metin, 'makale_kaynakca' => 'Kaynak, 2026.',
    'alan' => 'sos', 'alanlar' => ['5.2'],
    'unvan' => 'Dr.', 'ad' => 'Ölçüm Yazar', 'eposta' => 'cbehic@gmail.com',
    'orcid' => '0000-0002-1825-0097', 'kurum' => 'Ölçüm',
    'yazar_tam' => 'tek', 'telif_kabul' => true, 'kosullar_okundu' => true,
    'yz_kullanim' => 'yok', 'etik_durum' => 'gereksiz', 'veri_beyan' => 'yok',
    'cikar_catismasi' => 'yok', 'tek_gonderim' => true, 'fon_durum' => 'yok',
];
$r = bist('/api/yazar-basvuru', $temel + ['makale_ozet' => 'Yapısız serbest bir özet metni.']);
den('yapısız gönderim kabul ediliyor', !empty($r['ok']), (string)($r['hata'] ?? ''));
$r2 = bist('/api/yazar-basvuru', $temel + [
    'makale_ozet' => 'TARAYICIDAN GELEN VE UYDURULMUŞ ÖZET',
    'ozet_yapi' => ['amac' => 'Ölçmek.', 'bulgu' => 'Ölçüldü.'],
]);
den('yapılı gönderim de kabul ediliyor', !empty($r2['ok']), (string)($r2['hata'] ?? ''));

$bv = json_decode((string)@file_get_contents($VERI . '/basvurular.json'), true) ?: [];
$son = null;
foreach ($bv as $b) {
    if (is_array($b) && ($b['makale_baslik'] ?? '') === 'Yapılandırılmış öz ölçümü'
        && is_array($b['makale_ozet_yapi'] ?? null) && $b['makale_ozet_yapi']) $son = $b;
}
den('yapılı başvuru diske yazıldı', $son !== null);
if ($son) {
    olc('kayıttaki özet: ' . (string)$son['makale_ozet']);
    den('  TARAYICININ ÖZETİ KULLANILMADI',
        !str_contains((string)$son['makale_ozet'], 'UYDURULMUŞ'), (string)$son['makale_ozet']);
    den('  özet yapıdan türetildi',
        (string)$son['makale_ozet'] === tg_ozet_yapidan($son['makale_ozet_yapi']));
    den('  yapı kayıtta duruyor', count($son['makale_ozet_yapi']) === 2);
}

/* ------------------------------------------------------------------ */
echo "\n== 6. Çalışma sayfası yapıyı başlıklarıyla çiziyor ==\n";
$t = bin2hex(random_bytes(8));
$slug = 'ozyapi-' . $t;
$yapi = tg_ozet_yapi_temizle(['amac' => 'Ölçmek.', 'yontem' => 'Ölçerek.', 'bulgu' => 'Ölçüldü.']);
$yzl = json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [];
$sayi = count($yzl);
$yzl[] = ['id' => $slug, 'bcid' => 'bc.900401', 'slug' => $slug, 'tur' => 'yazi',
          'tarih' => '2026-08-18T12:00:00+03:00', 'dil' => 'tr',
          'baslik' => 'Yapılı öz sayfası', 'ozet' => tg_ozet_yapidan($yapi),
          'ozet_yapi' => $yapi, 'yazar' => 'Dr. Ölçüm',
          'etik' => ['durum' => 'gereksiz']];
file_put_contents($VERI . '/yazilar.json', json_encode($yzl, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$s = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/tamga/bc.900401?lang=tr');
den('sayfa açıldı', $s !== '');
den('  yapı bölümlere ayrılmış çiziliyor', str_contains($s, 'class="oz-yapi"'));
den('  bölüm adları görünüyor',
    str_contains($s, '<b>Amaç</b>') && str_contains($s, '<b>Yöntem ya da yaklaşım</b>'));
den('  yazılmayan bölüm hiç çizilmiyor', !str_contains($s, '<b>Özgünlük</b>'));
/* Çeviriyle açılan sayfada özgün dilin yapısı GÖSTERİLMEZ: çevrilmiş
   metnin bölümlere ayrıldığını söylemek olurdu. */
den('  çeviri kaydı yoksa yapı yine de doğru', str_contains($s, 'Ölçüldü.'));
den('  koşul yazi.php\'de yazılı (çeviride yapı gösterilmez)',
    str_contains($yzi, "trim(tg_metin(\$yazi['ozet'] ?? '')) !== trim(\$ozet)"));

/* Tutarsız yapı taşıyan kayıt: sayfa yapıyı GÖSTERMEMELİ. */
foreach ($yzl as $ix => $e) if (is_array($e) && ($e['slug'] ?? '') === $slug) $yzl[$ix]['ozet'] = 'Elle değiştirilmiş, yapıyla ilgisiz özet.';
file_put_contents($VERI . '/yazilar.json', json_encode($yzl, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$s2 = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/tamga/bc.900401?lang=tr');
den('  özet elle değişince yapı GÖSTERİLMİYOR', !str_contains($s2, 'class="oz-yapi"'));
den('    ve özetin kendisi görünüyor', str_contains($s2, 'Elle değiştirilmiş'));

/* ------------------------------------------------------------------ */
echo "\n== 7. Betiksiz tarayıcı ==\n";
$bs = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/basvuru.php?lang=tr');
den('başvuru formunda bölüm kutuları AÇIK doğuyor',
    str_contains($bs, '<div id="mOzetYapi">') && !str_contains($bs, '<div id="mOzetYapi" hidden'));
den('  gizleyen betiktir', str_contains($bsv, "kutu.hidden=!acik"));
den('  yapı onay kutusu var ve işaretsiz doğuyor',
    str_contains($bs, 'id="mOzetYapili"') && !preg_match('#id="mOzetYapili"[^>]*checked#', $bs));
den('yazar sayfasında da bölüm kutuları var', str_contains($yzr, 'data-yzozbolum='));
/* ÖNİZLEME: OKURUN GÖRECEĞİ HÂL. Yazar bölümleri doldururken özet
   kutusunda birleştirilmiş dizeyi görüyordu; çalışmanın sayfasında ise
   bölümler başlıklarıyla çiziliyor ve o görünüm panelde hiç yoktu.
   Yazdığının nasıl yayımlanacağını görmeden yazan kişi, yayımlandıktan
   sonra düzeltir — düzeltmesi bir sürüm kaydı bırakır. */
den('  yazar panelinde önizleme var', str_contains($yzr, 'id="ozYapiOnizle"'));
den('    önizleme çalışma sayfasının biçimini kullanıyor',
    str_contains($yzr, 'class="oz-yapi" id="ozYapiOnizle"') && str_contains($yzr, '.oz-yapi p{margin:0 0 var(--b-2)}'));
den('    bölüm yazıldıkça yenileniyor', str_contains($yzr, 'ozYzOnizle();'));
/* Gözden geçir ve gönder özeti, aynı içeriği iki kez yazmamalı:
   birleştirilmiş özet zaten yayımlanacak dizedir. */
den('gözden geçirme özeti bölümleri ayrı ayrı YAZMIYOR',
    str_contains($bsv, "if(el.closest('#mOzetYapi')) return;"));
/* Taslak: kutuları taslaktan doldurmak 'input' üretmez; yeniden
   çizilmezse taslağı süren kişi kutuları dolu, özeti boş bulur. */
den('  taslak yüklendikten sonra yapı yeniden çiziliyor',
    str_contains($bsv, "if(typeof ozYapiCiz==='function')ozYapiCiz();"));

/* ------------------------------------------------------------------ */
echo "\n== 8. Sunulan şey DUYURULUYOR ==\n";
/* Bir kolaylık, kılavuzda yazmadığı sürece yalnızca onu kazayla
   bulanların kolaylığıdır. Bu sistemde en sık çıkan kusur sınıfının
   (uygulanmayan kuralın duyurulması) tersidir: uygulanan ama
   duyurulmayan kolaylık. */
$kil = (string)@file_get_contents($KOD . '/kilavuz.php');
den('kılavuz yapılandırılmış özden söz ediyor', str_contains($kil, 'bölümlere ayırabilir'));
den('  dört bölümü de sayıyor',
    str_contains($kil, 'amaç, yöntem ya da yaklaşım, bulgular ve özgünlük'));
den('  zorunlu OLMADIĞINI söylüyor', str_contains($kil, 'zorunlu değildir'));
den('  her alana uymadığını da söylüyor', str_contains($kil, 'Her alana uymaz'));

/* ------------------------------------------------------------------ */
echo "\n== 9. Ölçüm kayıtları geri alındı ==\n";
$kalan = [];
foreach (json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [] as $e) {
    if (is_array($e) && ($e['slug'] ?? '') === $slug) continue;
    $kalan[] = $e;
}
file_put_contents($VERI . '/yazilar.json', json_encode($kalan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('çalışma kaydı silindi', count($kalan) === $sayi);
$bvK = [];
foreach (json_decode((string)@file_get_contents($VERI . '/basvurular.json'), true) ?: [] as $b) {
    if (is_array($b) && ($b['makale_baslik'] ?? '') === 'Yapılandırılmış öz ölçümü') continue;
    $bvK[] = $b;
}
file_put_contents($VERI . '/basvurular.json', json_encode($bvK, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
den('  ölçüm başvuruları silindi', true);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
