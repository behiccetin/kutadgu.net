<?php
/* =====================================================================
   ÇEVİRİNİN KAYNAĞI: kapı ölçümü. Depoya girmez.

   NEDEN VAR
   ---------
   İlkeler sayfası şunu YAZILI olarak söz veriyordu:

     "Bir çevirinin buraya girmesi için yazarın onayı ve çevirenin adı
      gerekir; çeviren kendi adıyla kayda geçer."

   Kayıtta çevirenin adını tutan bir alan yoktu. Yazarın kendi yazdığı
   İngilizce metin, bir insanın çevirdiği metin ve bir makinenin
   ürettiği metin sayfada BİRBİRİNİN AYNI görünüyordu; üstelik üçü de
   dizinlere aynı biçimde, "bu çalışmanın İngilizce sürümü" diye
   bildiriliyordu. Sistem, uygulamadığı bir kuralı duyuruyordu.

   Bu kapı o kuralın uygulandığını ölçer. Üç ayrı yerde sonucu var ve
   üçü de ayrı ayrı ölçülür, çünkü biri düzelip ötekiler unutulursa
   sayfa "makine çevirisi" derken üstveri "İngilizce sürüm vardır"
   demeyi sürdürür:

     1. KAYIT      künye okunuyor mu, eski biçim bozulmadan
     2. SAYFA      okura hangi cümle yazılıyor
     3. ÜSTVERİ    dizine ne bildiriliyor (canonical, hreflang, OAI,
                   Scholar etiketleri)

   ÖLÇÜM VERİYE BAĞLI DEĞİLDİR. Sınama verisinde onaysız bir makine
   çevirisi taşıyan bir kayıt yok; olmadığı için "geçti" demek hiçbir
   şey söylemezdi. Kapı kaydı KENDİSİ kurar: aynı çalışmanın dört ayrı
   künyeyle (eski biçim / yazar / insan+onay / makine+onaysız) nasıl
   davrandığını ölçer.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php ceviri-kaynak-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';

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

/* Dört künye, tek çalışma iskeleti. */
function kayit(array $ek = []): array {
    return array_merge([
        'id' => 'sinama-ceviri', 'slug' => 'sinama-ceviri',
        'dil' => 'tr', 'tarih' => '2026-01-15',
        'baslik' => 'Ölçüm, yöntem ve sınırlılıklar',
        'ozet' => 'Türkçe özet.',
        'metin' => '<p>Türkçe gövde.</p>',
        'baslik_en' => 'Measurement, method and limitations',
        'ozet_en' => 'English abstract.',
        'metin_en' => '<p>English body.</p>',
    ], $ek);
}

echo "\n== 1. KAYIT: KÜNYE OKUNUYOR MU ==\n";

$eski = kayit();                                   /* künye yok: eski biçim */
$cEski = tg_yazi_ceviri($eski, 'en');
den('eski biçim (yalnız _en alanları) okunuyor', $cEski !== [], 'boş döndü');
den('  metni yerinde okunuyor', ($cEski['baslik'] ?? '') === 'Measurement, method and limitations');
/* BU DENEME ÖNEMLİ. Bugüne kadarki İngilizce metinler başvuru
   formundaki kutudan geldi; onlara geriye dönük "makine çevirisi"
   demek, bilinmeyen bir şeyi biliyormuş gibi kaydetmek olurdu. */
den('  künyesi yoksa YAZAR sayılıyor (geriye dönük uydurma yok)',
    ($cEski['kaynak'] ?? '') === 'yazar', (string)($cEski['kaynak'] ?? ''));
den('  ve yayın sayılıyor', tg_ceviri_yayin_mi($cEski));

$yeni = kayit(['ceviri_en' => ['kaynak' => 'insan', 'ceviren' => 'Ayşe Demir', 'onay' => true]]);
$cYeni = tg_yazi_ceviri($yeni, 'en');
den('künye yazılmışsa o okunuyor', ($cYeni['kaynak'] ?? '') === 'insan');
den('  çevirenin adı kayda geçmiş', ($cYeni['ceviren'] ?? '') === 'Ayşe Demir');
den('  ve metin yine yerinden okunuyor', ($cYeni['metin'] ?? '') === '<p>English body.</p>');

$mak = kayit(['ceviri_en' => ['kaynak' => 'makine', 'ceviren' => 'bir çeviri aracı', 'onay' => false]]);
$cMak = tg_yazi_ceviri($mak, 'en');
den('onaysız makine çevirisi YAYIN SAYILMIYOR', !tg_ceviri_yayin_mi($cMak));
$makO = kayit(['ceviri_en' => ['kaynak' => 'makine', 'ceviren' => 'bir çeviri aracı', 'onay' => true]]);
den('onaylanmış makine çevirisi yayın sayılıyor', tg_ceviri_yayin_mi(tg_yazi_ceviri($makO, 'en')));
den('insan çevirisi onaysızsa yayın sayılmıyor',
    !tg_ceviri_yayin_mi(tg_yazi_ceviri(kayit(['ceviri_en' => ['kaynak' => 'insan', 'onay' => false]]), 'en')));
/* Yazan zaten yazarsa onay sorusu anlamsızdır ve sorulmaz. */
den('yazarın kendi metninde onay sorulmuyor (her hâlde yayın)',
    tg_ceviri_yayin_mi(tg_yazi_ceviri(kayit(['ceviri_en' => ['kaynak' => 'yazar', 'onay' => false]]), 'en')));

/* Bilinmeyen bir değer sessizce "makine" sayılmamalı: o da uydurmadır. */
den('bilinmeyen kaynak değeri yazar sayılıyor, makine değil',
    (tg_yazi_ceviri(kayit(['ceviri_en' => ['kaynak' => 'zort']]), 'en')['kaynak'] ?? '') === 'yazar');

/* KAYDIN KENDİ DİLİ, ÇEVİRİ OLARAK SUNULMAZ. Ama listeden de
   düşürülmez: arşivde 'dil' => 'en' yazıp İngilizce metnini yine
   baslik_en yuvasında tutan kayıtlar var ve düşürme kuralı o metni
   görünmez yapıyordu. Koruma gösterim yerindedir; ölçüsü de 4.
   bölümdeki "kayıt dilinde çeviri künyesi yazılmıyor" denemesidir. */
$kendi = kayit(['dil' => 'en']);
den('kaydın kendi dilindeki metin okunabiliyor (görünmez olmuyor)',
    (tg_yazi_ceviri($kendi, 'en')['baslik'] ?? '') === 'Measurement, method and limitations');

/* Yeni biçim (ceviriler) ile eski biçim çakışırsa yeni kazanır. */
$cakis = kayit(['ceviriler' => ['en' => ['baslik' => 'Yeni biçim', 'kaynak' => 'makine', 'onay' => false]]]);
den('yeni biçim eski biçimi eziyor (iki kaynak sessizce karışmıyor)',
    (tg_yazi_ceviri($cakis, 'en')['baslik'] ?? '') === 'Yeni biçim');

/* Üçüncü dil: modelin İngilizceye çakılı olmadığı ölçülür. */
$arapca = kayit(['ceviriler' => ['ar' => ['baslik' => 'قياس', 'kaynak' => 'makine', 'onay' => false]]]);
den('üçüncü bir dil de çeviri olarak tutulabiliyor',
    (tg_yazi_ceviri($arapca, 'ar')['baslik'] ?? '') === 'قياس');

echo "\n== 2. SAYFA: OKURA HANGİ CÜMLE YAZILIYOR ==\n";

$_GET['lang'] = 'tr';
$mYazar = tg_ceviri_kaynak_metni($cEski, false);
$mInsan = tg_ceviri_kaynak_metni($cYeni, false);
$mMakin = tg_ceviri_kaynak_metni($cMak, false);
$mMakOn = tg_ceviri_kaynak_metni(tg_yazi_ceviri($makO, 'en'), false);

den('yazar sürümünün cümlesi var', $mYazar !== '');
den('çevirenin adı cümlede geçiyor', strpos($mInsan, 'Ayşe Demir') !== false, $mInsan);
den('onaysız makine cümlesi "kayıt bu değildir" diyor',
    mb_strpos($mMakin, 'kayıt bu değildir') !== false, $mMakin);
den('onaylı makine cümlesi onayı da söylüyor',
    mb_strpos($mMakOn, 'onayladı') !== false, $mMakOn);
den('dört durumun cümlesi birbirinden AYRI',
    count(array_unique([$mYazar, $mInsan, $mMakin, $mMakOn])) === 4);
/* Uzun çizge yasağı bu projede geneldir; üretilen metin de uyar. */
den('cümlelerde uzun çizgi yok',
    strpos($mYazar . $mInsan . $mMakin . $mMakOn, '—') === false);

$_GET['lang'] = 'en';
$mEn = tg_ceviri_kaynak_metni($cMak, true);
den('İngilizce karşılığı da var ve ayrı', $mEn !== '' && $mEn !== $mMakin, $mEn);
$_GET['lang'] = 'tr';

echo "\n== 3. ÜSTVERİ: DİZİNE NE BİLDİRİLİYOR ==\n";

/* seo.php içindeki JSON-LD üreticisi k_c() kullanır; o da kabukta
   tanımlıdır. Kabuk yüklenmezse ölçüm "çalışmıyor" der ama ölçtüğü şey
   çalışıyordur: yanlış bir kusur bildirmek, kusuru kaçırmak kadar
   pahalıdır. */
require_once $KOD . '/k/kabuk.php';
require_once $KOD . '/k/seo.php';

/* Scholar etiketleri: onaysız makine çevirisi başlığı üstveride
   KAYDIN başlığının yerine geçmemeli. */
$shMak = sq_scholar($mak, 'https://ornek/x', true, false);
den('onaysız makine başlığı Scholar üstverisine GİRMİYOR',
    strpos($shMak, 'Measurement, method and limitations') === false);
den('  onun yerine kaydın kendi başlığı bildiriliyor',
    strpos($shMak, 'Ölçüm, yöntem ve sınırlılıklar') !== false);

$shOn = sq_scholar($makO, 'https://ornek/x', true, false);
den('onaylanmış makine başlığı üstveriye giriyor',
    strpos($shOn, 'Measurement, method and limitations') !== false);

$shEski = sq_scholar($eski, 'https://ornek/x', true, false);
den('eski kayıtların İngilizce başlığı bildirilmeyi sürdürüyor (geri gitme yok)',
    strpos($shEski, 'Measurement, method and limitations') !== false);

/* JSON-LD de aynı ölçütten geçmeli; iki ayrı yol olsaydı biri
   ötekinden ayrılırdı. */
$jsMak = sq_yazi($mak, 'https://ornek/x', true, false);
den('JSON-LD de onaysız makine başlığını kullanmıyor',
    strpos($jsMak, 'Measurement, method and limitations') === false);

echo "\n== 4. SAYFANIN KENDİSİ (HTTP) ==\n";

$kok = 'http://127.0.0.1:' . $PORT;
$al = function (string $yol) use ($kok): string {
    $c = curl_init($kok . $yol);
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20]);
    $g = (string)curl_exec($c); curl_close($c); return $g;
};
$liste = tg_yazilar_oku();
$ilk = null;
foreach ($liste as $y) { if (trim((string)($y['slug'] ?? '')) !== '') { $ilk = $y; break; } }
if ($ilk === null) { olc('arşivde çalışma yok; HTTP bölümü atlandı'); }
else {
    /* Adres elle kurulmaz: tg_yazi_yolu() kalıcı kimliği olan çalışmayı
       /tamga/<kod> altında verir ve ?y=<slug> orada 404 döner. Ölçüm,
       sistemin kendi ürettiği adresi kullanmalı; yoksa ölçtüğü şey
       sayfa değil, kendi tahmini olur. */
    $yol = tg_yazi_yolu($ilk);
    $s = $al($yol . (strpos($yol, '?') === false ? '?' : '&') . 'lang=tr');
    den('çalışma sayfası açılıyor', strpos($s, '</html>') !== false);
    /* Kaydın kendi dilinde çeviri künyesi YAZILMAMALI: okuduğu şey
       zaten kaydın kendisi. */
    den('kayıt dilinde çeviri künyesi yazılmıyor', strpos($s, 'cv-kaynak') === false);
    den('canonical kendi dilini gösteriyor', strpos($s, 'lang=tr"') !== false);
}

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
