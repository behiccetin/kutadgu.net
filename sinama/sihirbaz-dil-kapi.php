<?php
/* =====================================================================
   GÖNDERİM FORMU: KÜNYE DİLİ SEKMESİ VE YAPAY ZEKÂ BEYANI
   Kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   İKİ BİLDİRİLEN KUSUR, 14 Ağustos 2026.

   1. "Yapay zekâ kullanılmadı dedim, geçmedi; burayı tıklamam gerekti."

      Ölçüldü, iki katmanda birden yanlıştı:
        a) Onay kutusunun cümlesi "yapay zekâ KULLANIMININ ilkeler
           çerçevesinde kaldığını beyan ederim" diyor. Kullanmadığını
           söyleyen birinden, olmamış bir kullanımın niteliğini beyan
           etmesi isteniyordu.
        b) Uç 'yz_etik_kabul' istiyordu ama sayfa bu alanı kutuya
           BAKMADAN her zaman true gönderiyordu. Yani kutu kullanıcıyı
           durduruyor, kuralı hiç korumuyordu. Bu depoda onuncu kez
           aynı kusur: SİSTEM, UYGULAMADIĞI BİR KURALI DUYURUYOR.

   2. "Çalışma adımında sekme olsa: birincisi ana dil, ikincisi baş
      editör kurulunun seçtiği ikinci dil (bugün İngilizce)."

      Eksik olan yalnız sekme değildi: ikinci dildeki ÖZET hiç
      sorulmuyordu ve sorulsa da kabul anında kayda geçmiyordu
      ('ozet_en' => '' sabit yazılıydı). Sayfa iki dilde künye vaat
      ediyor, kayıt tek dilde tutuyordu.

   BU KAPI KURALIN İKİ YÜZÜNÜ DE ÖLÇER: beyan edilmediğinde onay
   İSTENMEMELİ, edildiğinde İSTENMELİ. Yalnız birini ölçen bir kapı,
   kuralı büsbütün kaldıran bir kusuru yeşil geçirir.

   Kullanım:
     KTEST_DIR=<kod> KUTADGU_DATA=<veri> KPORT=<kapı> php sihirbaz-dil-kapi.php
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
require_once $KOD . '/k/duzenleyici.php';   /* kd_araclar(): araç takımı */

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

$bvKod  = kodsuz($KAYNAK . '/basvuru.php');
$apiKod = kodsuz($KAYNAK . '/api/index.php');

/* =====================================================================
   1. YAPAY ZEKÂ BEYANI: SORULMAMASI GEREKEN ŞEY SORULMUYOR
   ===================================================================== */
echo "== 1. Yapay zekâ beyanı ==\n";
den('sayfa artık sabit true göndermiyor',
    !preg_match('/yz_etik_kabul\s*:\s*true/', $bvKod), 'basvuru.php');
den('  gerçek kutu değeri gönderiliyor',
    (bool)preg_match("/yz_etik_kabul\s*:\s*\(yzEl\.value\s*===\s*'yok'\)\s*\?\s*false\s*:/", $bvKod));
den('doğrulama kullanım yokken onay istemiyor',
    (bool)preg_match("/if\(y\.value===.yok.\)return null;/", $bvKod));
den('kullanım varken onay hâlâ isteniyor',
    (bool)preg_match("/if\(!\\\\?\\\$\('yzEtik'\)\.checked\)return \[S\.eYzEtik/", $bvKod), 'doğrulama satırı');

den('uç onayı yalnız kullanım beyan edildiğinde arıyor',
    (bool)preg_match("/\\\$yzKullanim !== 'yok' && !\\\$yzEtik/", $apiKod));
den('uç kayda sabit true yazmıyor',
    !preg_match("/'etik_kabul'\s*=>\s*true/", $apiKod));
den('  gerçek değer kayda geçiyor',
    (bool)preg_match("/'etik_kabul'\s*=>\s*\\\$yzEtik/", $apiKod));
den('kullanım yokken açıklama da temizleniyor',
    (bool)preg_match("/\\\$yzKullanim === 'yok'\)\s*\{\s*\\\$yzEtik = false;\s*\\\$yzAciklama = '';/", $apiKod));

$s = ist('/basvuru.php?adim=8&lang=tr');
den('form sayfası 200', $s['kod'] === 200, (string)$s['kod']);
den('kullanım yokken gösterilecek cümle sayfada var', str_contains($s['govde'], 'id="yzYokNot"'));
den('  ve olmamış bir kullanımın beyan edilemeyeceğini söylüyor',
    mb_stripos($s['govde'], 'olmamış bir kullanımın kapsamı yazılamaz') !== false);
den('kapsam bölmesi ayrı bir kutuda', str_contains($s['govde'], 'id="yzKapsam"'));

/* =====================================================================
   2. KÜNYE DİLİ: TEK KAYNAK
   ===================================================================== */
echo "\n== 2. Künye dili tek kaynaktan ==\n";
den('tg_kunye_dili() var', function_exists('tg_kunye_dili'));
den('tg_kunye_zorunlu() var', function_exists('tg_kunye_zorunlu'));
den('tg_kunye_dil_adi() var', function_exists('tg_kunye_dil_adi'));
$kunye = tg_kunye_dili();
olc('künye dili: ' . ($kunye === '' ? 'kapalı' : $kunye . ' (' . tg_kunye_dil_adi() . ')')
    . ' · ' . (tg_kunye_zorunlu() ? 'ZORUNLU' : 'isteğe bağlı'));
den('künye dili bugün İngilizce', $kunye === 'en', $kunye);
den('  ve çalışma dilleri listesinde var', isset(tg_calisma_dilleri()[$kunye]));
/* 15 Ağustos 2026 kurul kararı: "İngilizce başlık ana dilin yanında
   zorunlu olmalı, özet de." Kapı artık kuralın DEĞERİNİ dondurmaz —
   dondurulmuş bir değer, kurul kararını kusur diye bildirir. Ölçülen
   şey kuralın SAYFAYA GEÇMESİDİR: zorunluysa etiket "zorunlu" der ve
   doğrulama boş alanda durdurur; değilse "isteğe bağlı" der. Aşağıda
   ikisi de ölçülüyor. */
olc('kısa künye bugün: ' . (tg_kunye_zorunlu() ? 'ZORUNLU' : 'isteğe bağlı'));

$ayarHam = (string)@file_get_contents($KAYNAK . '/ayar.php');
den("ayarda 'kunye_dili' bloğu var", str_contains($ayarHam, "'kunye_dili'"));
den('  ve neyin istenip neyin istenmediği yazılı',
    (bool)preg_match('/NE İSTENİR, NE İSTENMEZ/u', $ayarHam));
/* Sayfa dil kodunu ELLE yazmamalı: ayar değiştiği gün sekme eski dili
   gösterirken uç yenisini beklerdi. */
den('sayfa künye dilini ayardan okuyor', str_contains($bvKod, 'tg_kunye_dili()'));
den('  ve dil kodunu elle yazmıyor',
    !preg_match("/mBaslikEn.{0,80}'en'/s", $bvKod));

/* =====================================================================
   3. SEKMELER SAYFADA
   ===================================================================== */
echo "\n== 3. Sekmeler ==\n";
$s = ist('/basvuru.php?adim=2&lang=tr');
den('çalışma adımı 200', $s['kod'] === 200, (string)$s['kod']);
den('sekme şeridi var', str_contains($s['govde'], 'id="mDilSekme"'));
den('  ana dil sekmesi', str_contains($s['govde'], 'id="mDilSkAna"'));
den('  künye sekmesi', str_contains($s['govde'], 'id="mDilSkKunye"'));
den('  künye dilinin adı sekmede yazıyor',
    str_contains($s['govde'], '<span>' . tg_kunye_dil_adi() . '</span>'));
den('ikinci dilde ÖZET alanı var', str_contains($s['govde'], 'id="mOzetEn"'));
den('  adı makale_ozet_en', str_contains($s['govde'], 'name="makale_ozet_en"'));
den('sekme <details> değil düğme (betiksiz iki bölme de açık)',
    str_contains($s['govde'], 'class="bv-dil-pnl acik"'));

/* ÖZNİTELİK ADI. 'data-dil' k/kutadgu.js'de SİTE DİLİ değiştiricisidir;
   sekmeye o adı vermek sayfayı başka bir dile götürüyordu ("kunye"nin
   ilk iki harfi 'ku'). Kapı bunu kalıcı olarak ölçer. */
den("sekmeler 'data-dil' kullanmıyor",
    !preg_match('/bv-dil-sk[^>]*data-dil=/', $s['govde']));
den('  kendi öznitelik adını kullanıyor', str_contains($s['govde'], 'data-bvdil='));
$jsKod = (string)@file_get_contents($KAYNAK . '/k/kutadgu.js');
den('  ve [data-dil] gerçekten dil değiştiricidir (ölçümün dayanağı)',
    str_contains($jsKod, "querySelectorAll('[data-dil]')"));

/* =====================================================================
   4. İKİNCİ DİLDEKİ ÖZET KAYDA GEÇİYOR
   ===================================================================== */
echo "\n== 4. Künye kayda geçiyor ==\n";
den('uç makale_ozet_en alıyor', str_contains($apiKod, "\$g['makale_ozet_en']"));
den('  başvuru kaydına yazılıyor', str_contains($apiKod, "'makale_ozet_en' => \$mOzetEn"));
den('  ve kabulde çalışmaya geçiyor',
    (bool)preg_match("/'ozet_en'\s*=>\s*\(string\)\(\\\$bs\['makale_ozet_en'\]/", $apiKod));
/* ÖLÇÜM DÜZELTMESİ: desen bütün dosyada aranıyordu ve Word içe
   aktarma sonucunun BOŞ BAŞLANGIÇ değerine takılıyordu — orada boş
   olması doğrudur, henüz okunmamış bir alandır. Aranan yer kabul
   bloğudur: başvurudan çalışmaya geçen künye. */
den('kabul bloğunda sabit boş dize kalmadı',
    !preg_match("/'gonderim' =>.{0,400}'ozet_en'\s*=>\s*'',/s", $apiKod));

/* =====================================================================
   5. ŞEKİL ADIMI NEREYE EKLENECEĞİNİ SÖYLÜYOR
   ===================================================================== */
echo "\n== 5. Şekil adımı ==\n";
$s = ist('/basvuru.php?adim=9&lang=tr');
den('şekil adımı 200', $s['kod'] === 200, (string)$s['kod']);
den('şeklin bu adımda yüklenmediğini söylüyor',
    mb_stripos($s['govde'], 'bu adımda yüklemezsiniz') !== false);
den('  ve nereye ekleneceğini söylüyor (yazma ekranı)',
    mb_stripos($s['govde'], 'araç çubuğu') !== false);
$sEn = ist('/basvuru.php?adim=9&lang=en');
den('İngilizcesi de söylüyor', stripos($sEn['govde'], 'do not upload figures') !== false);

/* Düzenleyicide tablo ve görsel araçları GERÇEKTEN var mı: cümlenin
   doğru olması, aracın var olmasına bağlı. */
den('makale düzenleyicisinde tablo aracı var', in_array('table', kd_araclar('tam'), true));
den('  ve görsel aracı var', in_array('image', kd_araclar('tam'), true));
den('  ve geri alma var', in_array('undo', kd_araclar('tam'), true));

/* =====================================================================
   6. PANELDEKİ BAĞ NE YAPTIĞINI SÖYLÜYOR
   ===================================================================== */
echo "\n== 6. Yazma ekranına giden bağ ==\n";
$pnKod = (string)@file_get_contents($KAYNAK . '/panel.php');
den('bağın adı tam metni yazmaktan söz ediyor',
    str_contains($pnKod, 'Tam metni yaz ya da düzenle'));
den('  İngilizcesi de', str_contains($pnKod, 'Write / edit the full text'));
den('eski belirsiz ad kalmadı', !str_contains($pnKod, "'Çalışmayı düzenle'"));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
