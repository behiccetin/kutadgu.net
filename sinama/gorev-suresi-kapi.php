<?php
/* =====================================================================
   BAŞ EDİTÖRLÜK: GÖREV SÜRESİ, ETKİNLİK ÖLÇÜTÜ VE ATAMA OYU
   Kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL KARARLARI, 14 Ağustos 2026.

   F1 — "Baş editörler hakem atama, sisteme giriş gibi işlemleri sık
        tekrarlayıp sistemin çalışmasına katkı vermiyorsa en fazla 1 yıl
        baş editör olur; akabinde onursal listede yer alır. Baş editörler
        sistemde yazar ve hakem olarak çalışabilirler. Tekrar baş editör
        olmak için, baş editörlüğü bittikten sonra baş editörlük
        kurallarını yerine getirirse tekrar otomatik baş editör olur.
        Hangi yıllar baş editörlük yaptığı, hangi çalışmalarda yer aldığı
        profil kartında gösterilir. Kurucu baş editörler hepsinden
        farklı."

   F2 — "Kurucuların olduğu zamanda 2027 sonuna kadar kurucu baş editörler
        atar; kurucu kurul üyelerinin beşte üçü kabul ederse o kişi baş
        editör olur, ama kurucu baş editör değil. 2027 sonrasında
        kurallar hâlâ geçerli."

   BU KAPININ ÖLÇTÜĞÜ KUSUR SINIFI, BU DEPODA SEKİZ KEZ YAKALANANDIR:
   SİSTEM, UYGULAMADIĞI BİR KURALI DUYURUYOR. Bir kural konurken üç yer
   birden değişmelidir: ayar, kod ve cümle. Bu kapı üçünü de ayrı ayrı
   yoklar; ayrıca kuralın TERSİNİ de ölçer (ölçütü karşılayan görevde
   KALMALI), çünkü yalnız cezayı ölçen bir kapı, herkesi görevden alan
   bir kusuru yeşil geçirir.

   ÖLÇÜM DÜZENEĞİ KENDİ VERİ DİZİNİNİ KURAR ve bitince siler. Ana veri
   dizinine dokunulmaz (OKUBENI: iki ölçüm aynı dizine yazarsa ikisi de
   yalan söyler).

   Kullanım:
     KTEST_DIR=<kod> KUTADGU_DATA=<veri> KPORT=<kapı> php gorev-suresi-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD    = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI   = getenv('KUTADGU_DATA') ?: '';
$PORT   = getenv('KPORT') ?: '8941';
$KAYNAK = '/home/claude/kg/kutadgunet';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function kodsuz(string $dosya): string {
    $ham = (string)@file_get_contents($dosya);
    if ($ham === '') return '';
    $c = '';
    foreach (token_get_all($ham) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { $c .= ' '; continue; }
            $c .= $t[1];
        } else $c .= $t;
    }
    return $c;
}

function ist(string $yol): array {
    global $PORT;
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, stream_context_create(['http' => [
        'method' => 'GET', 'ignore_errors' => true, 'timeout' => 30,
        'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250)]]));
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
}

/* Kendi veri dizininde, kendi alt sürecinde bir senaryo koşturur.
   Alt süreç şart: tg_ayar ve tg_yazilar_oku dosyayı bir kez okuyup
   bellekte tutar (OKUBENI 9); aynı süreçte iki ayrı arşiv ölçülemez. */
function senaryo(array $yazilar, string $govde): array {
    global $KOD;
    $tmp = sys_get_temp_dir() . '/kgorev-' . getmypid() . '-' . random_int(1000, 9999);
    @mkdir($tmp, 0700, true);
    file_put_contents($tmp . '/yazilar.json', json_encode($yazilar, JSON_UNESCAPED_UNICODE));
    $bet = tempnam(sys_get_temp_dir(), 'kg') . '.php';
    file_put_contents($bet, "<?php\n\$_SERVER['HTTP_HOST']='127.0.0.1';\nrequire " . var_export($KOD . '/ortak.php', true) . ";\n" . $govde . "\n");
    $cikti = (string)shell_exec('KUTADGU_DATA=' . escapeshellarg($tmp) . ' php ' . escapeshellarg($bet) . ' 2>&1');
    @unlink($bet); @unlink($tmp . '/yazilar.json'); @rmdir($tmp);
    $j = json_decode(trim($cikti), true);
    return is_array($j) ? $j : ['#ham' => $cikti];
}

/* Bir dönemde N hakem ataması üreten arşiv. Atayan adı verilir; rapor
   alanı doldurulur ki ölçütün "tamamlanmış atama" sayan yüzü de
   ölçülebilsin. */
function arsiv(string $atayan, int $n, string $ilkGun, int $araGun = 7, int $kisiSay = 1): array {
    $y = [];
    for ($i = 0; $i < $n; $i++) {
        $g = date('Y-m-d', strtotime($ilkGun . ' +' . ($i * $araGun) . ' days'));
        $hak = [];
        for ($j = 0; $j < $kisiSay; $j++) {
            $hak[] = ['ad' => 'Hakem ' . $i . '-' . $j, 'rapor' => 'rapor metni',
                      'atayan' => ['tur' => 'yonetim', 'ad' => $atayan, 'tarih' => $g . 'T10:00:00+03:00']];
        }
        $y[] = ['id' => 'ol' . $i, 'baslik' => 'Ölçüm çalışması ' . $i, 'tarih' => $g,
                'durum' => 'yayimda', 'hakemler' => $hak];
    }
    return $y;
}

/* =====================================================================
   1. KURAL AYARDA YAZILI VE GEREKÇESİYLE BİRLİKTE
   ===================================================================== */
echo "== 1. Kural ayarda ==\n";
$ayarHam = (string)@file_get_contents($KAYNAK . '/ayar.php');
den("'bas_editor_gorev' bloğu ayarda", str_contains($ayarHam, "'bas_editor_gorev'"));
den('  ve gerekçesi yazılı (kurul kararı anılıyor)',
    (bool)preg_match('/GÖREV SÜRESİ VE ETKİNLİK ÖLÇÜTÜ.{0,4000}KURUL KARARI/su', $ayarHam));
den('  ve neden süre değil ölçüt olduğu yazılı',
    (bool)preg_match('/NEDEN SÜRE DEĞİL ÖLÇÜT/u', $ayarHam));
den('  ve neden yalnız giriş sayılmadığı yazılı',
    (bool)preg_match('/NEDEN GİRİŞ TEK BAŞINA YETMEZ/u', $ayarHam));

den('tg_gorev_olcut() var', function_exists('tg_gorev_olcut'));
den('tg_donem_olcumu() var', function_exists('tg_donem_olcumu'));
den('tg_gorev_donemleri() var', function_exists('tg_gorev_donemleri'));
den('tg_editoryal_islemler() var', function_exists('tg_editoryal_islemler'));
den('tg_gorev_gecmisi() var', function_exists('tg_gorev_gecmisi'));

$o = tg_gorev_olcut();
olc('ölçüt: ' . $o['sure_ay'] . ' ay · ' . $o['atama'] . ' atama · ' . $o['islem_gunu'] . ' işlem günü'
    . ' · kurucu ' . ($o['kurucu'] ? 'DAHİL' : 'muaf'));
den('dönem uzunluğu bir yıl', $o['sure_ay'] === 12, (string)$o['sure_ay']);
den('kurucular ölçüme bağlı değil', $o['kurucu'] === false);
den('atama hedefi dönem başına makul (0 < n <= 52)', $o['atama'] > 0 && $o['atama'] <= 52, (string)$o['atama']);

/* Ölçüt sayıları koda ELLE yazılmamalı: ayar değiştiği gün kod eski
   sayıyı uygulasa kimse fark etmez. */
$ortakKod = kodsuz($KAYNAK . '/ortak.php');
den('kod ölçütü ayardan okuyor', str_contains($ortakKod, "tg_ayar('bas_editor_gorev'"));
den('  ve sayıları elle yazmıyor',
    !preg_match('/\$atama\s*>=\s*12\b|\$gun\s*>=\s*24\b/', $ortakKod));

/* =====================================================================
   2. DÖNEM ARİTMETİĞİ VE ÖLÇÜMÜN İKİ YÜZÜ
   ===================================================================== */
echo "\n== 2. Dönem ölçümü ==\n";
$gecenYil = date('Y-m-d', strtotime('-30 months'));

/* (a) İş VARDI ve kişi yapmadı -> görev biter. */
$s = senaryo(
    arsiv('Başka Kişi', 300, $gecenYil, 2),
    '$k=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . 'echo json_encode(["donem"=>tg_gorev_donemleri($k),"durum"=>tg_gorev_durumu($k)]);'
);
olc('(a) iş vardı, kişi hiç atama yapmadı');
den('  görev etkinlik ölçütüyle bitiyor',
    ($s['durum']['bitti'] ?? false) === true && ($s['durum']['yol'] ?? '') === 'etkinlik',
    json_encode($s['durum'] ?? $s));
den('  bitiş günü ilk dönemin sonu',
    ($s['durum']['tarih'] ?? '') === date('Y-m-d', strtotime($gecenYil . ' +12 months')),
    (string)($s['durum']['tarih'] ?? ''));

/* (b) İş vardı ve kişi YAPTI -> görev sürer. Kuralın tersi de
       ölçülmezse, herkesi görevden alan bir kusur yeşil geçerdi. */
$s = senaryo(
    array_merge(arsiv('Ölçüm Editör', 60, $gecenYil, 7), arsiv('Başka Kişi', 60, $gecenYil, 7)),
    '$k=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . 'echo json_encode(["donem"=>tg_gorev_donemleri($k),"durum"=>tg_gorev_durumu($k)]);'
);
olc('(b) iş vardı, kişi yaptı');
den('  görev sürüyor', ($s['durum']['bitti'] ?? true) === false, json_encode($s['durum'] ?? $s));
den('  ölçülen dönem sayısı ikiden az değil', count($s['donem']['donemler'] ?? []) >= 2,
    (string)count($s['donem']['donemler'] ?? []));
den('  süren dönemin bitişi gelecekte',
    ($s['donem']['suren_bitis'] ?? '') > date('Y-m-d'), (string)($s['donem']['suren_bitis'] ?? ''));

/* (c) HİÇ İŞ YOKTU -> kimse görevden düşmez. Yapılacak iş yokken
       "iş yapmadın" demek ölçmek değil cezalandırmaktır. */
$s = senaryo(
    [],
    '$k=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . 'echo json_encode(["durum"=>tg_gorev_durumu($k),"donem"=>tg_gorev_donemleri($k)]);'
);
olc('(c) arşivde hiç iş yok');
den('  görev sürüyor (boş dönem kimseyi düşürmez)', ($s['durum']['bitti'] ?? true) === false,
    json_encode($s['durum'] ?? $s));
den('  ve sebebi "iş yok" olarak yazılı',
    ($s['donem']['donemler'][0]['sebep'] ?? '') === 'is-yok',
    (string)($s['donem']['donemler'][0]['sebep'] ?? ''));

/* (d) AZ İŞ VARDI. Hedef tavandır: sistem beş kişilik kurulda on atama
       ürettiyse kimseden on iki beklenmez, İKİ beklenir.

       ÖLÇÜM DÜZELTMESİ: buraya önce "hedef indi, görev sürer" yazmıştım
       ve kapı kırmızı verdi. Önce ölçümden şüphelendim ve haklıydım:
       hedef ikiye inmişti, kişi ikisinden HİÇBİRİNİ yapmamıştı. Az iş
       olması, hiç iş yapmamayı aklamaz. Doğru ölçüm ikisini de sınar:
       payına düşeni yapan kalır, hiç yapmayan düşer. */
$s = senaryo(
    arsiv('Başka Kişi', 10, $gecenYil, 20),
    '$k=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . 'echo json_encode(["durum"=>tg_gorev_durumu($k),"donem"=>tg_gorev_donemleri($k)]);'
);
olc('(d1) sistemde on atama, beş koltuk, kişinin payı sıfır');
den('  hedef ikiye indi', (int)($s['donem']['donemler'][0]['hedef'] ?? -1) === 2,
    (string)($s['donem']['donemler'][0]['hedef'] ?? -1));
den('  ve hiç iş yapmayanın görevi bitti', ($s['durum']['bitti'] ?? false) === true,
    json_encode($s['donem']['donemler'][0] ?? $s));

$s = senaryo(
    array_merge(arsiv('Başka Kişi', 8, $gecenYil, 20), arsiv('Ölçüm Editör', 2, $gecenYil, 30)),
    '$k=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . 'echo json_encode(["durum"=>tg_gorev_durumu($k),"donem"=>tg_gorev_donemleri($k)]);'
);
olc('(d2) aynı arşiv, kişi payına düşen iki atamayı yaptı');
den('  payını yapanın görevi sürüyor', ($s['durum']['bitti'] ?? true) === false,
    json_encode($s['donem']['donemler'][0] ?? $s));

/* (e) DÖNEM DOLMADI -> hiçbir ölçüm görevi bitiremez. */
$yeni = date('Y-m-d', strtotime('-3 months'));
$s = senaryo(
    arsiv('Başka Kişi', 300, $yeni, 1),
    '$k=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($yeni, true) . '];'
    . 'echo json_encode(["durum"=>tg_gorev_durumu($k),"donem"=>tg_gorev_donemleri($k)]);'
);
olc('(e) atamanın üzerinden üç ay geçti');
den('  dönem dolmadan görev bitmiyor', ($s['durum']['bitti'] ?? true) === false,
    json_encode($s['durum'] ?? $s));
den('  ve ölçülmüş dönem yok', ($s['donem']['donemler'] ?? []) === []);

/* =====================================================================
   3. KURUCULAR AYRIDIR
   ===================================================================== */
echo "\n== 3. Kurucular ölçümün dışında ==\n";
$s = senaryo(
    arsiv('Başka Kişi', 300, $gecenYil, 2),
    '$k=["ad"=>"Ölçüm Kurucu","kurucu"=>true,"atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . '$a=["ad"=>"Ölçüm Editör","atama_tarih"=>' . var_export($gecenYil, true) . '];'
    . 'echo json_encode(["kurucu"=>tg_gorev_durumu($k),"atanmis"=>tg_gorev_durumu($a),'
    . '"kurucuDonem"=>tg_gorev_donemleri($k)]);'
);
den('kurucunun görevi etkinlikle BİTMEZ', ($s['kurucu']['bitti'] ?? true) === false,
    json_encode($s['kurucu'] ?? $s));
den('  ve kurucuda ölçüm hiç yapılmaz', ($s['kurucuDonem']['olculdu'] ?? true) === false);
den('aynı arşivde atanmışın görevi biter', ($s['atanmis']['bitti'] ?? false) === true,
    json_encode($s['atanmis'] ?? []));

/* Bugünkü kurulda kurucular hâlâ görevde: kural konurken beş kurucuyu
   birden düşüren bir kusur, en pahalı kusur olurdu. */
olc('bugün görevdeki kurucu: ' . count(array_filter(tg_kurucu_bas_editorler(), fn($k) => !empty($k['gorevde']))));
den('kurucuların hiçbiri bu kuralla düşmedi',
    count(array_filter(tg_kurucu_bas_editorler(), fn($k) => !empty($k['gorevde']))) === count(tg_kurucu_bas_editorler()));

/* =====================================================================
   4. ATAMA TARİHİ YOKSA ÖLÇÜT SESSİZCE KAPANMAZ
   ===================================================================== */
echo "\n== 4. Atama tarihi yoksa ==\n";
$s = senaryo(
    arsiv('Başka Kişi', 300, $gecenYil, 2),
    '$k=["ad"=>"Tarihsiz Editör"];'
    . 'echo json_encode(["durum"=>tg_gorev_durumu($k),"donem"=>tg_gorev_donemleri($k)]);'
);
/* HATA DEĞİL UYARI. İlk yazımda burası 'hata' bekliyordu ve kod da
   'hata' döndürüyordu; ölçüldü, sonucu ağırdı: hata kaydı DÜŞÜRÜR,
   yani atama tarihi yazılmamış her baş editör kuruldan siliniyordu
   (kapi.php on yedi kusur bildirdi). Eksik bir alan yüzünden bir kişiyi
   kuruldan silmek, o alanı istemekten çok daha ağırdır. Doğru şart
   şudur: kişi görevde KALIR, ölçüm yapılmaz, eksiklik GÖRÜNÜR. */
den('atama tarihi yoksa uyarı bildiriliyor', trim((string)($s['durum']['uyari'] ?? '')) !== '',
    json_encode($s['durum'] ?? $s));
den('  uyarı hata olarak sayılmıyor (kayıt düşmez)',
    trim((string)($s['durum']['hata'] ?? '')) === '');
den('  kişi görevde kalıyor', ($s['durum']['bitti'] ?? true) === false);
den('  ve eksikliğin ne olduğu yazılı',
    str_contains((string)($s['durum']['uyari'] ?? ''), 'Atama tarihi'));
den('tg_gorev_kayit_uyarilari() var', function_exists('tg_gorev_kayit_uyarilari'));
$ku = ist('/kurul.php?lang=tr');
den('kurul sayfası uyarı kümesini tanıyor',
    mb_stripos($ku['govde'], 'Görev süresi ölçülemeyen') !== false
    || tg_gorev_kayit_uyarilari() === [],
    'uyarı sayısı: ' . count(tg_gorev_kayit_uyarilari()));

/* =====================================================================
   5. GERİ DÖNÜŞ: ÖLÇÜT BİTİŞTEN SONRASINI SAYAR
   ===================================================================== */
echo "\n== 5. Yeniden göreve gelme ==\n";
den('tg_gorev_bitis_haritasi() var', function_exists('tg_gorev_bitis_haritasi'));
den('ölçüt sayacı kesim alıyor',
    (bool)preg_match('/function tg_atama_sayimi\(array \$yazilar, array \$kesim/', $ortakKod));
den('  ve saglayanlar kesimi kullanıyor',
    (bool)preg_match('/tg_atama_sayimi\(\$y, tg_gorev_bitis_haritasi\(\)\)/', $ortakKod));

$s = senaryo(
    /* Yarısı bitişten önce, yarısı sonra. */
    array_merge(arsiv('Ölçüm Editör', 20, '2024-01-05', 7), arsiv('Ölçüm Editör', 20, '2026-01-05', 7)),
    '$y=tg_yazilar_oku();'
    . 'echo json_encode(["hepsi"=>tg_atama_sayimi($y),'
    . '"kesimli"=>tg_atama_sayimi($y, [tg_ad_anahtar("Ölçüm Editör")=>"2025-06-30"])]);'
);
$an = tg_ad_anahtar('Ölçüm Editör');
olc('bitiş öncesi+sonrası: ' . (int)($s['hepsi'][$an] ?? 0) . ' · yalnız sonrası: ' . (int)($s['kesimli'][$an] ?? 0));
den('kesimsiz sayım hepsini sayıyor', (int)($s['hepsi'][$an] ?? 0) === 40, (string)($s['hepsi'][$an] ?? 0));
den('kesimli sayım yalnız bitişten sonrasını sayıyor', (int)($s['kesimli'][$an] ?? 0) === 20,
    (string)($s['kesimli'][$an] ?? 0));
den('  yani görevi biten kişi eski işiyle geri dönemiyor',
    (int)($s['kesimli'][$an] ?? 0) < (int)($s['hepsi'][$an] ?? 0));

/* =====================================================================
   6. F2: ATAMA OYUNU KİM VERİR
   ===================================================================== */
echo "\n== 6. Atama oyu (F2) ==\n";
den('tg_kurulus_atama_donemi() var', function_exists('tg_kurulus_atama_donemi'));
$donem = tg_kurulus_atama_donemi();
olc('kuruluş atama dönemi bugün ' . ($donem ? 'AÇIK' : 'kapalı')
    . ' (yetki bitiş: ' . tg_bas_atama()['yetki_bitis'] . ')');
$kurul = tg_atama_oy_kurulu();
olc('oy kurulu: ' . count($kurul) . ' kişi · yeter sayı: ' . tg_atama_yeter_sayisi());
if ($donem) {
    $hepsiKurucu = true;
    foreach ($kurul as $k) if (empty($k['kurucu'])) $hepsiKurucu = false;
    den('kuruluş döneminde oyu YALNIZ kurucular verir', $hepsiKurucu);
    den('  beş kurucu', count($kurul) === 5, (string)count($kurul));
    den('  ve beşte üçü yeter', tg_atama_yeter_sayisi() === 3, (string)tg_atama_yeter_sayisi());
}
den('yetki bitişi 2027 sonu', tg_bas_atama()['yetki_bitis'] === '2027-12-31', tg_bas_atama()['yetki_bitis']);

/* Atanan kişi KURUCU OLMAZ. Bu kapı zaten vardı; kural değiştiği için
   yeniden ölçülür: bir kuralı değiştirirken yanındakini bozmak, bu
   depodaki en sık gerileme yoludur. */
$adlar = array_map(fn($k) => (string)$k['ad'], array_slice($kurul, 0, 3));
$sonuc = tg_atama_gecerli(['ad' => 'Ölçüm Aday', 'kurucu' => true, 'atama_tarih' => date('Y-m-d')], $adlar);
if ($sonuc['ok']) {
    den('atanan kayıtta kurucu sıfatı düşürülüyor', empty($sonuc['kayit']['kurucu']));
    den('  ve atayanlar kayda yazılıyor', !empty($sonuc['kayit']['atayan']));
} else {
    /* Kurulda boş koltuk yoksa atama zaten yazılamaz; o da doğrudur. */
    olc('atama yazılamadı: ' . implode(' | ', $sonuc['hata']));
    den('atama reddi gerekçeli', $sonuc['hata'] !== []);
}
$az = tg_atama_gecerli(['ad' => 'Ölçüm Aday 2', 'atama_tarih' => date('Y-m-d')], array_slice($adlar, 0, 1));
den('tek oyla atama olmuyor', $az['ok'] === false);
den('  ve gerekçesi oy sayısını söylüyor',
    (bool)preg_match('/oyu gerekir/u', implode(' ', $az['hata'])), implode(' | ', $az['hata']));

/* =====================================================================
   7. CÜMLE İLE KURAL AYNI ŞEYİ SÖYLÜYOR MU
   ===================================================================== */
echo "\n== 7. Sayfalar kuralı doğru anlatıyor ==\n";
/* Kural değiştiğinde eski cümle sistemde kalırsa, sistem uygulamadığı
   bir kuralı duyurur. Aranan, ESKİ mutlak cümlelerdir. */
$eski = [
    '/Görev süresizdir/u',
    '/Dört yol vardır/u',
    '/dört yoldan biriyle sona erer/u',
    '/office has no fixed term/i',
    '/There are four routes/i',
    '/ends by one of four routes/i',
];
$kalan = [];
$yig = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($KAYNAK, FilesystemIterator::SKIP_DOTS));
foreach ($yig as $d) {
    $y = (string)$d;
    if (!preg_match('/\.php$/', $y)) continue;
    foreach (explode("\n", kodsuz($y)) as $n => $satir) {
        foreach ($eski as $m) if (preg_match($m, $satir)) { $kalan[] = str_replace($KAYNAK . '/', '', $y) . ':' . ($n + 1); break; }
    }
}
olc('eski cümle kalan yer: ' . (count($kalan) ?: 'yok'));
den('hiçbir sayfa "görev süresizdir" demiyor', $kalan === [], implode(' | ', $kalan));

foreach ([['/kurul.php', 'kurul'], ['/ilkeler.php', 'ilkeler'], ['/bildiri.php', 'bildiri']] as [$yol, $ad]) {
    foreach (['tr', 'en'] as $dil) {
        $y = ist($yol . '?lang=' . $dil);
        den("$ad ($dil) 200", $y['kod'] === 200, (string)$y['kod']);
        $var = $dil === 'tr'
            ? (mb_stripos($y['govde'], 'bir yıllık dönem') !== false)
            : (stripos($y['govde'], 'one-year term') !== false);
        den("  $ad ($dil) dönem kuralını anlatıyor", $var);
    }
}
$k = ist('/kurul.php?lang=tr');
den('kurul sayfası ölçümün bir görevden alma OLMADIĞINI söylüyor',
    mb_stripos($k['govde'], 'görevden alma değildir') !== false);
den('  ve kurucuların dışında olduğunu söylüyor',
    mb_stripos($k['govde'], 'Kuruculara işlemez') !== false);
den('  ve geri dönüşün açık olduğunu söylüyor',
    mb_stripos($k['govde'], 'yeniden göreve gelir') !== false);

/* =====================================================================
   8. PROFİL KARTI: GÖREV VE EDİTÖRLÜK
   ===================================================================== */
echo "\n== 8. Profil kartı ==\n";
$kurucuAd = (string)((tg_kurucu_bas_editorler()[0] ?? ['ad' => ''])['ad']);
/* ÖLÇÜM DÜZELTMESİ: tg_slug() diye bir işlev yok; boş dizeyle
   istenen sayfa 404 döndü ve kapı dokuz kusur bildirdi, dokuzunun da
   sebebi tekti. Kişi yolunun tek kaynağı tg_kisi_yolu()'dur. */
$yolKisi = tg_kisi_yolu($kurucuAd);
olc('ölçülen kişi: ' . $kurucuAd . ' (' . $yolKisi . ')');
$p = ist($yolKisi . '?lang=tr');
den('kişi sayfası 200', $p['kod'] === 200, (string)$p['kod']);
den('kurucu sıfatı kartta yazılı', mb_stripos($p['govde'], 'Kurucu baş editör') !== false);
den('görev ve editörlük bölümü var', mb_stripos($p['govde'], 'Görev ve editörlük') !== false);
den('  baş editörlük süresi yazılı', mb_stripos($p['govde'], 'Baş editörlük') !== false);
den('  hakem ataması sayısı yazılı', mb_stripos($p['govde'], 'hakem ataması') !== false);
den('  editöryal işlem günü yazılı', mb_stripos($p['govde'], 'editöryal işlem günü') !== false);
/* Kurucuda uydurma bir başlangıç yılı YAZILMAMALI. */
den('kurucuda uydurma başlangıç yılı yok', mb_stripos($p['govde'], 'kuruluştan bu yana') !== false);
$pEn = ist($yolKisi . '?lang=en');
den('İngilizcesi 200', $pEn['kod'] === 200, (string)$pEn['kod']);
den('  ve İngilizce başlık basılıyor', stripos($pEn['govde'], 'Office and editorial work') !== false);

/* Editörlük listesi gerçekten dolduruluyor mu: sayı yazıp liste
   çizmeyen bir bölüm, ölçülmeden yeşil geçen bir bölümdür. */
$s = senaryo(
    arsiv('Ölçüm Editör', 5, '2026-01-05', 7),
    'echo json_encode(tg_gorev_gecmisi("Ölçüm Editör"));'
);
olc('düzenekte: ' . (int)($s['atama'] ?? 0) . ' atama, ' . count($s['isler'] ?? []) . ' çalışma');
den('geçmiş atamaları sayıyor', (int)($s['atama'] ?? 0) === 5, (string)($s['atama'] ?? 0));
den('geçmiş çalışmaları topluyor', count($s['isler'] ?? []) === 5, (string)count($s['isler'] ?? []));
den('  ve çalışmaların başlığı dolu', ($s['isler'][0]['baslik'] ?? '') !== '');
den('yazarın kendi hakem önerisi editörlük SAYILMIYOR',
    (int)(senaryo(
        [['id' => 'x', 'baslik' => 'y', 'tarih' => '2026-02-02', 'hakemler' => [
            ['ad' => 'H', 'rapor' => 'r', 'atayan' => ['tur' => 'yazar', 'ad' => 'Ölçüm Editör', 'tarih' => '2026-02-02T09:00:00+03:00']]]]],
        'echo json_encode(tg_gorev_gecmisi("Ölçüm Editör"));'
    )['atama'] ?? -1) === 0);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
