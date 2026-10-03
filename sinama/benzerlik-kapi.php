<?php
/* =====================================================================
   BENZERLİK (İNTİHAL) RAPORU ŞARTI: kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL KARARI, 13 Ağustos 2026: yazardan benzerlik raporu istenmez.
   Gerekçe ayar.php'de yazılıdır; üç maddesi şudur:

     1. Sistem yüzde on beşlik eşiği ÖLÇEMİYORDU. Yazar bir sayı
        yazıyor, sistem o sayıyı denetlemeden kayda geçiriyordu.
        Denetlenmeyen bir eşik bir güvence değil bir görüntüdür.
     2. Rapor paralıdır (iThenticate, Turnitin kurumsal aboneliktir) ve
        kapıyı paraya bağlar. Açık erişimli bir sistemde kapının bedeli
        parasal olamaz.
     3. Asıl denetim zaten açıklıkta: metin herkese açık, hakem adıyla
        imzalıyor, rapor yayımlanıyor.

   Bu kapının ölçtüğü kusur, bu depoda beş kez yakalanmış olanla aynı
   cinstendir: SİSTEM, UYGULAMADIĞI BİR KURALI DUYURUYOR — ya da
   tersine, duyurduğu kuralı uygulamıyor. Bir kural kaldırılırken üç
   yerin birden değişmesi gerekir: ayar, uç ve cümle. Biri unutulursa
   sistem yalan söyler.

   BU KAPI İKİ DÜNYAYI DA ÖLÇER. Şart kapalıyken de açıkken de doğru
   davranış sınanır; yalnız bugünkü dünyayı ölçen bir kapı, kurul
   kararı değiştiği gün sessizce yanlış olur (bkz. OKUBENI 83).

   Kullanım:
     KUTADGU_DATA=<veri> KPORT=<kapı> php benzerlik-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
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

/* Yorumsuz kaynak: gerekçe yazmak yasaklanamaz, aranan şey koddur. */
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

/* =====================================================================
   1. TEK KAYNAK
   ===================================================================== */
echo "== 1. Kural tek yerden okunuyor ==\n";
den('tg_benzerlik_sarti() var', function_exists('tg_benzerlik_sarti'));
den('tg_benzerlik_metni() var',  function_exists('tg_benzerlik_metni'));
den('tg_benzerlik_kisa() var',   function_exists('tg_benzerlik_kisa'));
$sart = tg_benzerlik_sarti();
olc('yürürlükteki kural: benzerlik raporu ' . ($sart ? 'İSTENİYOR' : 'İSTENMİYOR'));

/* Ayar yazılmamışsa ESKİ davranış sürmeli: bir kuralı sessizce kaldıran
   varsayılan, kaldırıldığını kimseye söylemez. */
$ayarHam = (string)@file_get_contents($KAYNAK . '/ayar.php');
den('ayarda bayrak yazılı', str_contains($ayarHam, "'benzerlik_raporu_sarti'"));
den('  ve gerekçesi de yazılı',
    (bool)preg_match('/BENZERLİK \(İNTİHAL\) RAPORU ŞARTI.{0,4000}KURUL KARARI/su', $ayarHam));
$ortakKod = kodsuz($KAYNAK . '/ortak.php');
den('bayrak yokken eski davranış sürer (varsayılan true)',
    (bool)preg_match('/function tg_benzerlik_sarti\(\).{0,220}if \(\$a === null\) return true;/su', $ortakKod));

/* Eşik sayıları ELLE yazılmamalı. Uç eskiden 15 ve 5 sayılarını kendi
   içine yazıyordu; ayar değiştiği gün sayfa bir eşiği, uç başkasını
   uygulardı. */
$apiKod = kodsuz($KAYNAK . '/api/index.php');
den('uç eşikleri ayardan okuyor',
    str_contains($apiKod, "tg_ayar('benzerlik_ust'") && str_contains($apiKod, "tg_ayar('benzerlik_tek_ust'"));
den('  ve eşik sayıları uca elle yazılmamış',
    !preg_match('/\$intOran > 15|\$intTekKaynak > 5/', $apiKod));

/* =====================================================================
   2. CÜMLE SAYFALARA ELLE YAZILMAMIŞ
   ===================================================================== */
echo "\n== 2. Cümle elle çoğaltılmamış ==\n";
/* Aranan, kuralı MUTLAK biçimde anlatan elle yazılmış cümlelerdir. */
$mutlak = [
    '/rapor eklenmesi zorunludur/iu',
    '/[Bb]enzerlik raporu zorunlu/u',
    '/must be accompanied by a report/i',
    /* "No similarity report is required" cümlesi bu deseni İÇERİR.
       Yani kapı, kuralın kaldırıldığını söyleyen cümleyi kuralın
       kendisi sandı ve doğru sayfayı kusurlu bildirdi. Desen artık
       başındaki olumsuzlamayı dışarıda bırakıyor. */
    '/(?<!No )(?<!no )similarity report is required/i',
];
$elle = [];
$yiner = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($KAYNAK, FilesystemIterator::SKIP_DOTS));
foreach ($yiner as $d) {
    $y = (string)$d;
    if (!preg_match('/\.php$/', $y)) continue;
    if ($y === $KAYNAK . '/ortak.php') continue;   /* cümlenin tek meşru kaynağı */
    if ($y === $KAYNAK . '/ayar.php')  continue;   /* kuralın tanımı ve gerekçesi */
    foreach (explode("\n", kodsuz($y)) as $n => $satir) {
        foreach ($mutlak as $m) if (preg_match($m, $satir)) { $elle[] = str_replace($KAYNAK . '/', '', $y) . ':' . ($n + 1); break; }
    }
}
olc('elle yazılmış mutlak cümle: ' . (count($elle) ?: 'yok'));
den('kuralı mutlak anlatan elle yazılmış cümle yok', $elle === [], implode(' | ', $elle));

/* =====================================================================
   3. SAYFALAR YÜRÜRLÜKTEKİ KURALI SÖYLÜYOR
   ===================================================================== */
echo "\n== 3. Sayfalar ne diyor ==\n";
function sayfa(string $yol, string $dil): string {
    $port = getenv('KPORT') ?: '8941';
    $b = stream_context_create(['http' => ['timeout' => 12, 'ignore_errors' => true]]);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol . '?lang=' . $dil, false, $b);
    return $c === false ? '' : $c;
}
$sayfalar = ['/ilkeler.php', '/basvuru.php', '/nasil-isler.php', '/index.php'];
foreach ($sayfalar as $y) {
    foreach (['tr', 'en'] as $dil) {
        $g = sayfa($y, $dil);
        if ($g === '') { den("$y ($dil) yanıt verdi", false, 'sunucuya ulaşılamadı'); continue; }
        /* Şart kapalıyken hiçbir sayfa "zorunludur" dememeli. */
        $iddia = [];
        foreach ($mutlak as $m) if (preg_match($m, $g, $mm)) $iddia[] = $mm[0];
        if ($sart) {
            den("$y ($dil) 200 dönüyor ve şart yürürlükte", true);
        } else {
            den("$y ($dil) raporu zorunlu göstermiyor", $iddia === [], implode(', ', $iddia));
        }
    }
}
/* İlkeler sayfası kuralı TEK KAYNAKTAN basmalı. */
$ilk = sayfa('/ilkeler.php', 'tr');
den('ilkeler sayfası cümleyi işlevden basıyor',
    $ilk !== '' && str_contains($ilk, mb_substr(tg_benzerlik_metni(false), 0, 60)));
$ilkEn = sayfa('/ilkeler.php', 'en');
den('  İngilizcesi de öyle',
    $ilkEn !== '' && str_contains($ilkEn, mb_substr(tg_benzerlik_metni(true), 0, 60)));

/* Şart kapalıyken ölçülemeyen eşik büyük puntoyla basılmamalı: bu
   sayfanın kendi kuralı, bir sayının büyük puntoyla yazılmasının onu
   bir söz hâline getirdiğini söylüyor. */
if (!$sart) {
    den('ölçülmeyen eşik kutusu basılmıyor',
        $ilk !== '' && !preg_match('/olcut.{0,400}genel benzerlik üst sınırı/su', $ilk));
}

/* Sihirbaz adımının ADI da kuralı söylemeli. */
$adimlar = function_exists('tg_basvuru_adimlari') ? tg_basvuru_adimlari() : [];
$bzAdim = '';
foreach ((array)$adimlar as $a) if (is_array($a) && ($a['k'] ?? '') === 'benzerlik') $bzAdim = (string)($a['tr'] ?? '');
if ($bzAdim === '') {
    olc('sihirbaz adım listesi okunamadı; adım adı denenemedi');
} else {
    olc('sihirbaz adımı: ' . $bzAdim);
    den('adım adı kuralı söylüyor',
        $sart ? !str_contains($bzAdim, 'isteğe') : str_contains($bzAdim, 'isteğe'), $bzAdim);
}

/* =====================================================================
   4. UÇ: KURALI GERÇEKTEN UYGULUYOR MU
   ---------------------------------------------------------------------
   Asıl ölçüm budur. Cümlenin düzelmesi kuralın kalktığı anlamına
   gelmez; bir kapı ancak GERÇEKTEN AÇILDIĞINDA açılmıştır.
   ===================================================================== */
echo "\n== 4. Başvuru ucu ==\n";
/* GÖNDERİM HESAP İSTER (kurul kararı, 15 Ağustos 2026): kimliksiz
   istek 401 döner ve kapı ölçmek istediği benzerlik kuralına hiç
   ulaşamaz. Oturum bir kez açılır, çerez her isteğe eklenir. */
$KEREZ_BZ = '';
function bz_ist(string $yol, array $govde): array {
    global $KEREZ_BZ;
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nCF-Connecting-IP: 10." . random_int(1, 250) . '.' . random_int(1, 250) . ".7\r\n";
    if ($KEREZ_BZ !== '') $bas .= 'Cookie: ' . $KEREZ_BZ . "\r\n";
    $b = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 20, 'ignore_errors' => true,
        'header' => $bas,
        'content' => json_encode($govde, JSON_UNESCAPED_UNICODE),
    ]]);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, $b);
    $h = $http_response_header ?? [];
    if ($KEREZ_BZ === '') {
        foreach ($h as $x) if (stripos($x, 'Set-Cookie:') === 0) { $KEREZ_BZ = explode(';', trim(substr($x, 11)))[0]; break; }
    }
    $d = json_decode((string)$c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr((string)$c, 0, 120)];
}
bz_ist('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
function gonder(array $govde): array { return bz_ist('/api/yazar-basvuru', $govde); }
/* Gövde, yazarlik-kapi.php'dekiyle aynı biçimde kurulur; buradaki tek
   değişken benzerlik alanlarıdır. */
$temelGovde = function (array $ek = []) use (&$sayac): array {
    static $n = 0; $n++;
    return array_merge([
        'unvan' => 'Dr.', 'ad' => 'Benzerlik Olcumu ' . $n,
        'eposta' => 'benzerlik' . $n . '@ornek.org', 'kurum' => 'Örnek Üniversitesi',
        /* ORCID'in son basamağı bir DENETİM basamağıdır; uydurulmuş bir
           numara reddedilir ve reddedilmesi doğrudur. İlk yazımda
           sıradan üretilmiş numaralar kullandım ve kapı "başvuru
           geçmiyor" diye okudu — oysa geçmeyen şey benzerlik kuralı
           değil, uydurduğum numaraydı. Sınamada kullanılan numaralar
           yazarlik-kapi.php'nin kullandıklarıyla aynıdır. */
        'orcid' => ['0000-0001-2345-6789', '0000-0003-1234-5674', '0000-0002-1111-1115',
                    '0000-0002-2222-2224', '0000-0002-3333-3333'][$n % 5],
        'makale_dil' => 'tr',
        'makale_baslik' => 'Benzerlik kapisi olcumu ' . $n,
        'makale_genis_ozet_en' => str_repeat('measurement word ', 320),
        'makale_ozet' => str_repeat('Bu bir olcum ozetidir ve yeterince uzundur. ', 6),
        'alan' => 'sos', 'alanlar' => ['5.2'],
        /* 15 Ağustos 2026: künye zorunlu, tam metin ve kaynakça
           gönderim anında isteniyor. Ölçülen şey benzerlik kuralıdır;
           bu alanlar hep geçerli verilir. */
        'makale_baslik_en' => 'Similarity gate measurement ' . $n,
        'makale_ozet_en' => 'A record submitted only for gate measurement.',
        'makale_metin' => '<h2>Giris</h2><p>' . str_repeat('olcum metni ', 500) . '</p>',
        'makale_kaynakca' => '<p>Olcum, K. (2026). Benzerlik kapisi. Sinama.</p>',
        'telif_kabul' => true, 'kosullar_okundu' => true, 'kosul_surum' => '1',
        /* 15 Ağustos 2026: beyan adımı genişledi. Ölçülen şey
           benzerlik kuralıdır; bunlar hep geçerli verilir. */
        'cikar_catismasi' => 'yok', 'tek_gonderim' => true, 'fon_durum' => 'yok',
        'yazar_tam' => 'tek',
        'etik_durum' => 'gerekmiyor',
        'veri_beyan' => 'yok',
        /* ALAN ADI 'yz_etik' DEĞİL 'yz_etik_kabul'. İlk yazımda yanlış
           yazılmıştı ve uç doğru davranarak reddetti; kapı bunu
           "başvuru geçmiyor" diye okudu ve benzerlik kuralını kusurlu
           bildirdi. Ölçüm yanlış çıktığında önce ölçümden şüphelenin. */
        'yz_kullanim' => 'yok', 'yz_etik_kabul' => true,
    ], $ek);
};

if (!$sart) {
    /* a) HİÇ RAPOR VERMEDEN geçebilmeli. */
    $r1 = gonder($temelGovde());
    den('rapor verilmeden başvuru geçiyor', !empty($r1['ok']), (string)($r1['hata'] ?? ''));
    /* b) Rapor VERİLEBİLMELİ de: istenmeyen ile kabul edilmeyen aynı
       şey değildir. */
    $r2 = gonder($temelGovde(['intihal_arac' => 'iThenticate', 'intihal_oran' => 8.5,
                              'intihal_tek_kaynak' => 2.1, 'intihal_link' => 'https://ornek.org/rapor.pdf']));
    den('yazar isterse raporunu ekleyebiliyor', !empty($r2['ok']), (string)($r2['hata'] ?? ''));
    /* c) Eski eşik artık ENGEL DEĞİL: %40 benzerlik bildiren bir yazar
       da geçmeli, yoksa kural kalkmamış demektir. */
    $r3 = gonder($temelGovde(['intihal_arac' => 'Turnitin', 'intihal_oran' => 40,
                              'intihal_tek_kaynak' => 30, 'intihal_link' => 'https://ornek.org/r.pdf']));
    den('eski %15 eşiği artık engel değil', !empty($r3['ok']), (string)($r3['hata'] ?? ''));
    /* d) Ama TUTARSIZ veri yine reddedilir: sayfada gösterilecek bir
       sayı, kimsenin bakmadığı bir sayı değildir. */
    $r4 = gonder($temelGovde(['intihal_oran' => 10, 'intihal_tek_kaynak' => 90]));
    den('tek kaynak payı genel orandan büyük olamaz', empty($r4['ok']), (string)($r4['hata'] ?? 'kabul edildi'));
    $r5 = gonder($temelGovde(['intihal_oran' => 300]));
    den('yüzde 300 benzerlik reddediliyor', empty($r5['ok']), (string)($r5['hata'] ?? 'kabul edildi'));
} else {
    $r1 = gonder($temelGovde());
    den('şart açıkken rapor verilmeden başvuru REDDEDİLİYOR', empty($r1['ok']), (string)($r1['hata'] ?? 'kabul edildi'));
    $r2 = gonder($temelGovde(['intihal_arac' => 'iThenticate', 'intihal_oran' => 8.5,
                              'intihal_tek_kaynak' => 2.1, 'intihal_link' => 'https://ornek.org/rapor.pdf']));
    den('  eşiğin altındaki rapor geçiyor', !empty($r2['ok']), (string)($r2['hata'] ?? ''));
    $r3 = gonder($temelGovde(['intihal_arac' => 'Turnitin', 'intihal_oran' => 40,
                              'intihal_tek_kaynak' => 3, 'intihal_link' => 'https://ornek.org/r.pdf']));
    den('  eşiğin üstündeki rapor reddediliyor', empty($r3['ok']), (string)($r3['hata'] ?? 'kabul edildi'));
}

/* =====================================================================
   5. AŞIRMA YASAĞI KALKMADI
   ---------------------------------------------------------------------
   Kaldırılan şey rapor İSTEMEKti, aşırmayı serbest bırakmak değil. Bu
   ayrımın metinde durması gerekir; durmazsa kural kaldırılırken yanına
   başka bir şey de kaldırılmış olur.
   ===================================================================== */
echo "\n== 5. Aşırma yasağı yerinde ==\n";
$metinTr = tg_benzerlik_metni(false);
$metinEn = tg_benzerlik_metni(true);
den('cümle iki dilde de dolu', $metinTr !== '' && $metinEn !== '');
den('  ve iki dil aynı dize değil', $metinTr !== $metinEn);
den('  Türkçesinde İngilizce kalıntı yok', !preg_match('/\b(the|and|report|similarity)\b/i', $metinTr), $metinTr);
den('  İngilizcesinde Türkçe kalıntı yok', !preg_match('/(benzerlik|rapor|yazar|istenmez)/iu', $metinEn), $metinEn);
if (!$sart) {
    den('cümle aşırma yasağının sürdüğünü söylüyor',
        mb_stripos($metinTr, 'Aşırma yasağı sürer') !== false, $metinTr);
    den('  İngilizcesi de öyle',
        mb_stripos($metinEn, 'prohibition on plagiarism stands') !== false, $metinEn);
    den('cümle NEDEN kaldırıldığını söylüyor (ölçülemiyordu)',
        mb_stripos($metinTr, 'ölçemez') !== false);
}
/* Yayın ilkelerindeki "kabul edilmeyen davranışlar" listesi duruyor mu:
   aşırma yasağı oradadır ve koşula bağlanmamalıdır. */
den('ilkelerde aşırma yasağı koşulsuz duruyor',
    $ilk !== '' && mb_stripos($ilk, 'kaynak göstermeden kullanmak') !== false);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
