<?php
/* =====================================================================
   ÇEVİRİ PAKETİ ÜRETİCİSİ. Depoya girmez.

   NE İŞE YARAR
   ------------
   Arayüzün bütün dizelerini, bir dil modeline (Gemini, ChatGPT…) parça
   parça verilebilecek dosyalara böler ve her parçanın yanına o parçayı
   çevirmek için gereken İSTEMİ yazar. Dönen dosyalar `ceviri-al.php`
   ile denetlenip birleştirilir.

   NEDEN PARÇA PARÇA
   -----------------
   1821 dize tek bir istemde verilirse model ya ortasını atlar ya da
   yanıtı kesilir; ikisi de sessizce olur. Sessiz eksik, bu sistemde en
   pahalı kusurdur: eksik çeviri "çevrilmiş" sanılır. Parçalar 120
   dizeyi geçmez ve her parça KENDİ İÇİNDE tamdır: modelin bir parçayı
   çevirmek için ötekini görmesi gerekmez.

   NEDEN ANAHTAR İNGİLİZCE DİZENİN KENDİSİ
   ---------------------------------------
   Sözlük dosyasının anahtarı İngilizce dizedir (120 harfi geçenlerde
   kısa bir özet). Çevirmen anahtara baktığında ne çevirdiğini görür.
   Model de öyle: bağlamsız bir kod (str_412) yerine cümlenin kendisini
   görür ve daha doğru çevirir.

   ÜÇ ŞEY DEĞİŞMEZ VE İSTEMDE AÇIKÇA YAZILIDIR
   -------------------------------------------
   1. HTML etiketleri (<b>, <a href="...">) olduğu gibi kalır.
   2. Yer tutucular (%1, %2, {sayi}) olduğu gibi kalır.
   3. Özel adlar çevrilmez: Kutadgu, tamga, ORCID, DOI, CC BY 4.0.

   Kullanım:
     php ceviri-paket.php <dil-kodu> [<dil-adı>] [<çıktı-dizini>]
     php ceviri-paket.php de Deutsch /tmp/ceviri-paket
   ===================================================================== */
declare(strict_types=1);

$dil = strtolower(trim((string)($argv[1] ?? '')));
$dilAd = trim((string)($argv[2] ?? ''));
$cikti = rtrim((string)($argv[3] ?? '/tmp/ceviri-paket'), '/');
$kaynakDosya = __DIR__ . '/ceviri-sozluk-kaynak.json';

$DILLER = [
    'de' => ['Deutsch', 'German'],
    'fr' => ['Français', 'French'],
    'es' => ['Español', 'Spanish'],
    'ru' => ['Русский', 'Russian'],
    'ar' => ['العربية', 'Arabic'],
    'az' => ['Azərbaycan dili', 'Azerbaijani'],
    'uz' => ['Oʻzbekcha', 'Uzbek'],
    'kk' => ['Қазақша', 'Kazakh'],
    'fa' => ['فارسی', 'Persian'],
    'zh' => ['中文', 'Chinese'],
];

if ($dil === '' || !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $dil)) {
    fwrite(STDERR, "Kullanım: php ceviri-paket.php <dil-kodu> [<dil-adı>] [<çıktı>]\n"
        . "Bilinen kodlar: " . implode(', ', array_keys($DILLER)) . "\n");
    exit(2);
}
if ($dilAd === '') $dilAd = $DILLER[$dil][0] ?? strtoupper($dil);
$dilAdEn = $DILLER[$dil][1] ?? $dilAd;

if (!is_file($kaynakDosya)) {
    fwrite(STDERR, "Kaynak sözlük yok: $kaynakDosya\n  Önce: php ceviri-cikar.php\n");
    exit(2);
}
$kaynak = json_decode((string)file_get_contents($kaynakDosya), true);
if (!is_array($kaynak)) { fwrite(STDERR, "Kaynak sözlük okunamadı.\n"); exit(2); }

/* ---------------------------------------------------------------------
   SIRALAMA: ÖNCE GÖRÜLEN, SONRA DERİN OLAN

   Bir dilin ilk parçası bittiğinde sitenin ÜST çubuğu, menüsü ve ana
   sayfası o dile dönmüş olmalıdır. Çevirmen yarıda bırakırsa bile
   ziyaretçi "yarısı çevrilmiş" değil, "üstü çevrilmiş" bir site görür.
   Bu yüzden kısa dizeler (menü, düğme, etiket) başa alınır; uzun
   paragraflar sona.
   --------------------------------------------------------------------- */
/* Sıra iki ölçüte göre: önce DOSYA önceliği, sonra uzunluk.
   Yalnız uzunluğa göre sıralanınca birinci parça ".", "K", "OR" gibi
   bağlamsız kırıntılarla doluyordu; çevirmen ilk gördüğü dosyada ne
   çevirdiğini anlayamıyordu. Kabuk (menü, düğmeler, üst çubuk) ve ana
   sayfa başa alınır: ilk parça bittiğinde sitenin GÖRÜNEN yüzü o dile
   dönmüş olur. */
$ONCELIK = ['k/kabuk.php' => 1, 'k/parca.php' => 2, 'index.php' => 3,
            'k/veri.php' => 4, 'ortak.php' => 5, 'yazilar.php' => 6,
            'k/hesap.php' => 7, 'nasil-isler.php' => 8, 'yazi.php' => 9,
            'hakemlik.php' => 10, 'basvuru.php' => 11, 'panel.php' => 12];
uasort($kaynak, function (array $a, array $b) use ($ONCELIK): int {
    $oa = $ONCELIK[(string)($a['dosya'] ?? '')] ?? 50;
    $ob = $ONCELIK[(string)($b['dosya'] ?? '')] ?? 50;
    if ($oa !== $ob) return $oa <=> $ob;
    return mb_strlen((string)($a['en'] ?? ''), 'UTF-8') <=> mb_strlen((string)($b['en'] ?? ''), 'UTF-8');
});

$PARCA = 120;
$dizin = $cikti . '/' . $dil;
@mkdir($dizin, 0775, true);
array_map('unlink', glob($dizin . '/*.json') ?: []);
array_map('unlink', glob($dizin . '/*.txt') ?: []);

$parcalar = [];
$suan = [];
foreach ($kaynak as $anahtar => $v) {
    $suan[$anahtar] = (string)($v['en'] ?? '');
    if (count($suan) >= $PARCA) { $parcalar[] = $suan; $suan = []; }
}
if ($suan) $parcalar[] = $suan;

$istem = function (int $no, int $toplam) use ($dil, $dilAd, $dilAdEn): string {
    return <<<TXT
Sen akademik yayıncılık alanında çalışan, {$dilAdEn} diline çeviri yapan
profesyonel bir çevirmensin.

Sana bir JSON dosyası vereceğim. Bu, "Kutadgu" adlı açık erişimli akademik
yayın sisteminin arayüz metinleridir. ANAHTARLAR İngilizce özgün dizedir;
DEĞERLER şu an İngilizcedir ve senin {$dilAdEn} diline çevirmen gereken
kısımdır.

KURALLAR
1. Yalnızca DEĞERLERİ çevir. ANAHTARLARI hiç değiştirme, harfi harfine
   aynı bırak. Anahtar sayısı ne artsın ne azalsın.
2. Yanıtın SADECE geçerli JSON olsun. Açıklama, giriş cümlesi, kod
   çiti (```), yorum yazma.
3. HTML etiketleri aynen korunacak: <b>…</b>, <a href="...">…</a>,
   <br>, &mdash; gibi. Etiketin içindeki metin çevrilir, etiketin
   kendisi ve öznitelikleri çevrilmez.
4. Yer tutucular aynen korunacak: %1, %2, %s, {sayi} gibi. Yerleri
   cümlede doğru yere taşınabilir ama biçimleri değişmez.
5. Şu adlar ÇEVRİLMEZ, olduğu gibi kalır:
   Kutadgu, tamga, ORCID, DOI, CC BY 4.0, iThenticate, Turnitin,
   Scopus, ISSN, DOAJ, COPE, OAI-PMH, FORD.
6. Üslup: resmî, açık ve yalın. Bu sistem abartılı ve pazarlama dili
   kullanmaz; "en iyi", "garanti", "kusursuz" gibi sözler yoktur ve
   çeviride de olmamalıdır. Kısa cümleyi kısa çevir.
7. Terimler tutarlı olsun. Aynı İngilizce terim her yerde aynı
   {$dilAdEn} karşılığıyla çevrilsin:
   work = bir akademik çalışma (makale/metin)
   review / reviewer = hakemlik / hakem (akademik değerlendirme)
   panel = yayın kurulu
   founding period = sistemin kuruluş dönemi
   archive = arşiv
   open access = açık erişim
8. Emin olmadığın, teknik ya da kuruma özgü bir dizede İngilizceyi
   olduğu gibi bırak. Uydurma bir karşılık yazmaktansa İngilizce
   kalması iyidir: sistem çevirisi olmayan dizeyi zaten İngilizce
   gösterir.
9. Büyük harf düzenini {$dilAdEn} dilinin kuralına göre yaz (örneğin
   Almancada adlar büyük harfle başlar). İngilizcedeki başlık büyük
   harflerini körü körüne taşıma.

Bu {$toplam} parçadan {$no}. parçadır. Yalnızca bu parçayı çevir.

JSON:
TXT;
};

$toplam = count($parcalar);
$yazilan = [];
foreach ($parcalar as $i => $p) {
    $no = $i + 1;
    $ad = sprintf('%s-%02d', $dil, $no);
    file_put_contents($dizin . '/' . $ad . '.json',
        json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    file_put_contents($dizin . '/' . $ad . '-istem.txt',
        $istem($no, $toplam) . "\n\n" . json_encode($p, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n");
    $yazilan[] = $ad;
}

/* Bir de okunacak kısa bir yönerge: dosyaların ne olduğu ve sıra. */
$yonerge = <<<TXT
KUTADGU — {$dilAd} ({$dil}) ÇEVİRİ PAKETİ

Bu klasörde {$toplam} parça var. Her parça için iki dosya:

  {$dil}-01.json        çevrilecek dizeler (anahtar = İngilizce özgün)
  {$dil}-01-istem.txt   modele verilecek metnin tamamı (istem + JSON)

NASIL YAPILIR
1. "{$dil}-01-istem.txt" dosyasını aç, tamamını kopyala, modele yapıştır.
2. Modelin verdiği JSON'u "{$dil}-01-cevrilmis.json" adıyla aynı klasöre
   kaydet. (Dosya adının sonu MUTLAKA "-cevrilmis.json" olsun.)
3. Öteki parçalar için aynısını yap. Sıra önemlidir: birinci parça
   menüyü ve düğmeleri taşır, sonuncular uzun metinleri.
4. Hepsi bitince (ya da yarısı bitince — yarısı da çalışır) klasörü
   bana gönder. Denetleyip yerine koyacağım:

     php ceviri-al.php {$dil} <klasör>

DENETİM NE ARAR
  - anahtar kaybolmuş mu, uydurulmuş anahtar var mı
  - HTML etiketi bozulmuş mu (<b> açılıp kapanmamış gibi)
  - yer tutucu (%1, %2) kaybolmuş mu
  - çeviri İngilizceyle birebir aynı mı (çevrilmemiş demektir)
  - boş bırakılmış mı

Eksik bir dize sorun değildir: sistem karşılığı olmayan dizeyi
İngilizce gösterir ve sayfanın başında "bu dil henüz bir insan
tarafından gözden geçirilmedi" yazar. O uyarı, bir insan diller
ayarında 'gozden_gecirildi' bayrağını açana kadar durur.
TXT;
file_put_contents($dizin . '/OKU.txt', $yonerge . "\n");

echo "== Çeviri paketi ==\n";
echo "  dil          : $dil ($dilAd)\n";
echo "  dize         : " . count($kaynak) . "\n";
echo "  parça        : $toplam (en çok $PARCA dize)\n";
echo "  klasör       : $dizin\n";
echo "  dosyalar     : " . implode(', ', array_slice($yazilan, 0, 3)) . " …\n";
