<?php
/* =====================================================================
   HAKEM ÇAKIŞMASI: KAPI ÖLÇÜMÜ. Depoya girmez.

   Ölçülen tek şey var: ortak.php içindeki tg_hakem_cakisma(). O işlev
   üç atama yolunun (editör ataması, gönüllü onayı, yazar önerisi)
   önüne konulan tek kapıdır. Kapının kendisi bozuksa üç yolun da
   denetimi boşa düşer; bu yüzden burada HTTP'ye hiç çıkılmaz, işlev
   doğrudan yüklenip çağrılır. Uçtan uca uygulanıp uygulanmadığı
   hakem-akis.php içinde ayrıca ölçülür.

   Kullanım: php hakem-kapi.php
   ===================================================================== */
declare(strict_types=1);
require_once '/home/claude/kg/ktest/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* Pencere ayardan okunur, betiğe yazılmaz: ayar değiştiğinde sınama
   yanlış yerden kalmasın. */
$AY    = (int)tg_ayar('karsilikli_hakem_ay', 12);
$ICERI = date('c', time() - (int)($AY * 30 * 0.5) * 86400);   /* pencerenin içi */
$DISI  = date('c', time() - ($AY * 30 + 30) * 86400);         /* pencerenin dışı */

/* ---------------------------------------------------------------------
   Veri üreticileri. Denetim yalnızca yazar ve hakem alanlarını okur;
   bu yüzden kayıtlara başka alan konmadı. Sınama, ölçtüğü işlevin
   gerçekten okuduğu alanlarla sınırlı kalmalıdır, yoksa geçmesi de
   kalması da başka bir şeyin sonucu olur.
   --------------------------------------------------------------------- */
/* Yazar dizisinin ögesi: [ad, orcid, eposta]. Üçüncüsü isteğe bağlı
   bırakıldı ki e-posta eklenmeden önce yazılmış kurulumlar aynen
   çalışsın; ölçülen davranış değişmediği yerde sınama da değişmemeli. */
function calisma(array $yazarlar, array $hakemler = [], string $erisimEposta = ''): array {
    $liste = [];
    foreach ($yazarlar as $y) {
        $k = ['ad' => (string)$y[0], 'orcid' => (string)($y[1] ?? '')];
        if (trim((string)($y[2] ?? '')) !== '') $k['eposta'] = (string)$y[2];
        $liste[] = $k;
    }
    $adlar = [];
    foreach ($liste as $l) $adlar[] = $l['ad'];
    $c = [
        'yazar'       => implode(' ve ', $adlar),
        'yazar_bilgi' => $liste[0] ?? [],
        'yazar_liste' => $liste,
        'hakemler'    => $hakemler,
    ];
    /* Yazarın erişim kaydındaki düz adres, adresin durabileceği üçüncü
       yerdir ve ayrıca ölçülmelidir: kayıtta yazar_bilgi boş kalmış
       olabilir, adres yalnızca orada duruyor olabilir. */
    if ($erisimEposta !== '') $c['yazar_erisim'] = ['eposta_acik' => $erisimEposta];
    return $c;
}
/* Bir hakem kaydı. $tarih boş bırakılırsa kayıtta hiç tarih yoktur:
   bu, atanmış ama henüz hiçbir şey yazılmamış hakemi anlatır.
   $eposta profil adresidir, $acikEposta ise davetin gittiği adres:
   ikisi ayrı alanlardır ve denetim ikisine de bakmak zorundadır. */
function hakem(string $ad, string $tarih = '', string $orcid = '', string $davet = 'kabul',
                string $eposta = '', string $acikEposta = ''): array {
    $profil = [];
    if ($orcid !== '')  $profil['orcid']  = $orcid;
    if ($eposta !== '') $profil['eposta'] = $eposta;
    $h = ['ad' => $ad, 'tarih' => $tarih, 'davet_durum' => $davet, 'profil' => $profil];
    if ($acikEposta !== '') $h['eposta_acik'] = $acikEposta;
    return $h;
}

/* Sınamada kullanılan kişiler. ORCID'ler on altı haneli olmak
   zorunda; kısa yazılan kimlik işlev tarafından bilerek yok sayılır. */
const O_A = '0000-0002-1825-0097';   /* Ayşe Kaya */
const O_B = '0000-0001-5109-3700';   /* Berk Demir */
const O_C = '0000-0003-1415-9269';   /* Cem Aydın */
/* Aynı kişilerin adresleri. Ölçülen açık tam olarak şuydu: ad ve ORCID
   değiştirilebiliyor, adres değiştirilemiyor, çünkü onay yazısının
   gideceği yer orası. */
const E_A = 'ayse.kaya@ornek-sinama.org';
const E_B = 'berk.demir@ornek-sinama.org';
const E_C = 'cem.aydin@ornek-sinama.org';
/* Denetimde başka bir ORCID: kimliğin ORCID'den değil adresten
   kurulduğunu göstermek için gerekiyor. */
const O_X = '0000-0002-7183-2803';

echo "== 1. Karsilikli eslestirme yasagi ==\n";
/* A, B'nin çalışmasına hakem. Şimdi B, A'nın çalışmasına aday.
   Yazar kümeleri ayrık tutuldu ki yakalanan şey ortak yazarlık değil
   karşılıklılık olsun. */
$k1 = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]], [hakem('Ayşe Kaya', $ICERI)]),
];
$c = tg_hakem_cakisma($k1, 0, 'Berk Demir', O_B);
den('B, A nin calismasina hakem olamiyor', ($c['kod'] ?? '') === 'karsilikli', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Aynı ilişkinin öteki yönü: sıra değişince kural değişmemeli. */
$c = tg_hakem_cakisma([$k1[1], $k1[0]], 1, 'Berk Demir', O_B);
den('  yon degistirince de yakaliyor', ($c['kod'] ?? '') === 'karsilikli', json_encode($c, JSON_UNESCAPED_UNICODE));

/* Adlar iki yerde farklı yazılmışsa ad anahtarı tutmaz; kimliği
   tutan tek şey ORCID kalır. Denetim buradan da geçmeli. */
$k2 = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['B. Demir', O_B]], [hakem('A. Kaya', $ICERI, O_A)]),
];
$c = tg_hakem_cakisma($k2, 0, 'Berk Demir', O_B);
den('adlar farkli yazilmis, ORCID tutuyor', ($c['kod'] ?? '') === 'karsilikli', json_encode($c, JSON_UNESCAPED_UNICODE));

/* Unvanlar ve Türkçe harfler ad eşleşmesini bozmamalı. */
$k3 = [
    calisma([['Prof. Dr. Şükrü Öztürk', '']]),
    calisma([['Doç. Dr. Işıl Çelik', '']], [hakem('sukru ozturk', $ICERI)]),
];
$c = tg_hakem_cakisma($k3, 0, 'Dr. Işıl Çelik');
den('unvan ve Turkce harf eslesmeyi bozmuyor', ($c['kod'] ?? '') === 'karsilikli', json_encode($c, JSON_UNESCAPED_UNICODE));

echo "\n== 2. Kendi calismasina hakem olamaz ==\n";
$k4 = [calisma([['Ayşe Kaya', O_A], ['Cem Aydın', O_C]])];
$c = tg_hakem_cakisma($k4, 0, 'Ayşe Kaya', O_A);
den('ilk yazar kendi calismasina hakem olamiyor', ($c['kod'] ?? '') === 'kendisi', json_encode($c, JSON_UNESCAPED_UNICODE));
$c = tg_hakem_cakisma($k4, 0, 'Cem Aydın');
den('ikinci yazar da olamiyor', ($c['kod'] ?? '') === 'kendisi', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Adını başka türlü yazan yazar: ORCID kimliği tutar. */
$c = tg_hakem_cakisma($k4, 0, 'A. Kaya', O_A);
den('  adini farkli yazsa da ORCID yakaliyor', ($c['kod'] ?? '') === 'kendisi', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Kendi çalışması, karşılıklılıktan önce gelir: iki engel birden
   varsa yazara söylenen sebep en yakın olanı olmalı. */
$k5 = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Ayşe Kaya', O_A]], [hakem('Berk Demir', $ICERI)]),
];
$c = tg_hakem_cakisma($k5, 0, 'Ayşe Kaya', O_A);
den('kendisi sebebi ustte geliyor', ($c['kod'] ?? '') === 'kendisi', json_encode($c, JSON_UNESCAPED_UNICODE));

echo "\n== 3. Ortak yazarlik cakisma sayiliyor ==\n";
/* Aday, çalışmanın yazarıyla başka bir çalışmada ortak yazar.
   Hiçbir hakemlik ilişkisi yok; engel yalnızca ortak yayından
   gelmeli ve sebep de onu söylemeli. */
$k6 = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Ayşe Kaya', O_A], ['Cem Aydın', O_C]]),
];
$c = tg_hakem_cakisma($k6, 0, 'Cem Aydın', O_C);
den('ortak yayin yapmis kisi hakem olamiyor', ($c['kod'] ?? '') === 'ortak', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Ortak yazarlık ORCID üzerinden de görülmeli. */
$k7 = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['A. Kaya', O_A], ['C. Aydın', O_C]]),
];
$c = tg_hakem_cakisma($k7, 0, 'Cem Aydın', O_C);
den('  adlar farkli yazilmis, ORCID tutuyor', ($c['kod'] ?? '') === 'ortak', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Üçüncü bir çalışmada ortak yazar olmak yetmez: ortaklık bu
   çalışmanın yazarlarıyla kurulmuş olmalı. */
$k8 = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B], ['Cem Aydın', O_C]]),
];
$c = tg_hakem_cakisma($k8, 0, 'Cem Aydın', O_C);
den('ilgisiz ortaklik engel degil', $c === [], json_encode($c, JSON_UNESCAPED_UNICODE));

echo "\n== 4. Zaman penceresi: gecmis hakemlikler ==\n";
/* Koddaki niyet açıkça yazılı: pencere ZAMANA bakar, raporun yazılmış
   olmasına değil. Üç durum ayrı ayrı ölçülüyor. */
$icerde = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]], [hakem('Ayşe Kaya', $ICERI)]),
];
$disarda = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]], [hakem('Ayşe Kaya', $DISI)]),
];
den('pencere icindeki hakemlik sayiliyor',
    (tg_hakem_cakisma($icerde, 0, 'Berk Demir', O_B)['kod'] ?? '') === 'karsilikli');
den('pencere disindaki (eski) hakemlik sayilmiyor',
    tg_hakem_cakisma($disarda, 0, 'Berk Demir', O_B) === [], $AY . ' ay + 30 gun once');

/* Rapor yazılmamış, yalnızca davet gönderilmiş atama da sırayı
   kapatır: karşılıklılık atama anında kurulur. */
$bekleyen = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]],
        [['ad' => 'Ayşe Kaya', 'davet_durum' => 'bekliyor', 'davet_tarih' => $ICERI,
          'karar' => '', 'rapor' => '', 'tarih' => '']]),
];
den('raporsuz bekleyen atama da sayiliyor',
    (tg_hakem_cakisma($bekleyen, 0, 'Berk Demir', O_B)['kod'] ?? '') === 'karsilikli');

/* Reddedilmiş ya da geri çekilmiş davet sırayı kapatmaz: olmamış bir
   hakemlik yüzünden kimse elenmemeli. */
foreach (['ret', 'geri_cekildi'] as $dd) {
    $d = [
        calisma([['Ayşe Kaya', O_A]]),
        calisma([['Berk Demir', O_B]], [hakem('Ayşe Kaya', $ICERI, '', $dd)]),
    ];
    den("davet durumu '$dd' olan hakemlik sayilmiyor", tg_hakem_cakisma($d, 0, 'Berk Demir', O_B) === [],
        json_encode(tg_hakem_cakisma($d, 0, 'Berk Demir', O_B), JSON_UNESCAPED_UNICODE));
}
/* Hiç tarihi olmayan kayıt: eski veride tarih alanı boş kalabiliyor.
   Böyle bir kaydı pencerenin dışına atmak, denetimi sessizce kapatmak
   olurdu; kod bunu bilerek güncel sayar. */
$tarihsiz = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]], [hakem('Ayşe Kaya', '')]),
];
den('tarihsiz kayit pencereden dusurulmuyor',
    (tg_hakem_cakisma($tarihsiz, 0, 'Berk Demir', O_B)['kod'] ?? '') === 'karsilikli');
/* Okunamayan tarih de aynı yere düşmeli. Tersi, "tarihi bozarak
   denetimi atlatmak" demek olurdu. */
$bozukTarih = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]], [hakem('Ayşe Kaya', 'dun bir ara')]),
];
den('okunamayan tarih de guncel sayiliyor',
    (tg_hakem_cakisma($bozukTarih, 0, 'Berk Demir', O_B)['kod'] ?? '') === 'karsilikli');

echo "\n== 5. Cakisma yokken acik doner ==\n";
$temiz = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', O_B]], [hakem('Cem Aydın', $ICERI, O_C)]),
];
den('ilgisiz aday icin engel yok', tg_hakem_cakisma($temiz, 0, 'Deniz Yalçın') === [],
    json_encode(tg_hakem_cakisma($temiz, 0, 'Deniz Yalçın'), JSON_UNESCAPED_UNICODE));

/* Tek yönlü hakemlik karşılıklılık değildir: C, A'nın bir başka
   çalışmasını değerlendirmiş olabilir. Bu tekrar eden eşleşmedir ve
   kod bunu bilerek engellemez, yalnızca sayar. */
$tekrar = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Ayşe Kaya', O_A]], [hakem('Cem Aydın', $ICERI, O_C)]),
];
den('ayni hakemin ayni yazari yeniden degerlendirmesi engel degil',
    tg_hakem_cakisma($tekrar, 0, 'Cem Aydın', O_C) === [],
    json_encode(tg_hakem_cakisma($tekrar, 0, 'Cem Aydın', O_C), JSON_UNESCAPED_UNICODE));
den('  ama sessiz de degil: tekrar sayaci goruyor',
    tg_hakem_tekrar($tekrar, 0, 'Cem Aydın', O_C) === 1,
    (string)tg_hakem_tekrar($tekrar, 0, 'Cem Aydın', O_C));

/* Yarım yazılmış ORCID yok sayılır. Sayılsaydı, önekleri tutan iki
   ayrı kişi aynı sayılır ve denetim yanlış yerde kapanırdı. */
$yarim = [
    calisma([['Ayşe Kaya', O_A]]),
    calisma([['Berk Demir', '0000-0002']], [hakem('Ayşe Kaya', $ICERI)]),
];
den('yarim ORCID ile yanlis eslesme uretilmiyor',
    tg_hakem_cakisma($yarim, 0, 'Deniz Yalçın', '0000-0002') === [],
    json_encode(tg_hakem_cakisma($yarim, 0, 'Deniz Yalçın', '0000-0002'), JSON_UNESCAPED_UNICODE));

/* Aday olarak yalnızca ORCID verilmişse ve o ORCID hiçbir yerde
   geçmiyorsa engel çıkmamalı. */
den('tanimsiz ORCID engel uretmiyor', tg_hakem_cakisma($temiz, 0, '', '0000-0009-9999-9999') === []);

echo "\n== 6. Bos ve eksik veriyle cokmuyor ==\n";
/* Bozuk veri denetimi düşürmemeli: düşen bir denetim, açık bir kapıdır.
   Her çağrı Throwable ile sarmalanıyor ki ölümcül hata da yakalansın. */
function guvenli(callable $f): array {
    /* Uyarılar da toplanıyor. Uyarı çökme değildir, bu yüzden sınamayı
       düşürmez; ama sessizce yutulursa bozuk veriyle çalışan kodun ne
       yaptığı görünmez kalır. Ekrana ayrı bir satır olarak basılır. */
    $uyari = [];
    set_error_handler(function ($n, $m) use (&$uyari) { $uyari[] = $m; return true; });
    try { $s = $f(); $r = ['ok' => true, 'd' => $s]; }
    catch (Throwable $e) { $r = ['ok' => false, 'd' => [], 'hata' => get_class($e) . ': ' . $e->getMessage()]; }
    restore_error_handler();
    $r['uyari'] = $uyari;
    return $r;
}
$bozuk = [
    ['bos dizi',            fn() => tg_hakem_cakisma([], 0, 'Ayşe Kaya')],
    ['olmayan indeks',      fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]])], 7, 'Berk Demir')],
    ['negatif indeks',      fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]])], -1, 'Berk Demir')],
    ['bos aday adi',        fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]])], 0, '')],
    ['yalniz bosluk adi',   fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]])], 0, '   ')],
    ['yazarsiz calisma',    fn() => tg_hakem_cakisma([[]], 0, 'Ayşe Kaya')],
    ['dizi olmayan kayit',  fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]]), 'bozuk', null, 42], 0, 'Berk Demir')],
    ['dizi olmayan hakem',  fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]]),
                                        ['yazar' => 'Berk Demir', 'hakemler' => ['metin', null, []]]], 0, 'Berk Demir')],
    ['hakemler dizi degil', fn() => tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]]),
                                        ['yazar' => 'Berk Demir', 'hakemler' => 'yok']], 0, 'Berk Demir')],
];
foreach ($bozuk as [$ad, $f]) {
    $s = guvenli($f);
    den("cokmuyor: $ad", $s['ok'] === true && is_array($s['d']), (string)($s['hata'] ?? ''));
    /* Engel dönmemeli de: bozuk bir kayıt yüzünden temiz bir aday
       elenirse denetim yanlış tarafa düşmüş olur. */
    if ($s['ok']) den("  ve engel uretmiyor: $ad", $s['d'] === [], json_encode($s['d'], JSON_UNESCAPED_UNICODE));
    foreach ($s['uyari'] as $u) echo "  NOT    uyari ($ad): $u\n";
}
/* Boş adla ve boş ORCID'le çağrı, engel değil AÇIK dönmeli: kimliği
   bilinmeyen aday yanlışlıkla elenmemeli. */
den('bos kimlikle acik donuyor', tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A]])], 0, '', '') === []);
/* Adres de boşken hiçbir ölçüt kalmaz; denetim yine açık dönmeli. */
den('bos ad, ORCID ve adresle acik donuyor',
    tg_hakem_cakisma([calisma([['Ayşe Kaya', O_A, E_A]])], 0, '', '', '') === []);

/* =====================================================================
   7. E-POSTA: ÜÇÜNCÜ TANIMLAYICI

   Ölçülen açık şuydu (11 Ağustos 2026, canlıda): denetim yalnızca ad ve
   ORCID'e bakıyordu. İkisi de adayın forma kendi yazdığı alanlar. Kendi
   çalışmasına gönüllü olmak isteyen biri adını başka yazıp ikinci bir
   ORCID verince kapıdan geçiyor, ama başvuruyu kendi adresiyle yapıyordu
   çünkü onay yazısı oraya gelecek. Bu bölüm o üçüncü ayağı ölçer.

   Dördü de ayrı ayrı sorulur: adresle yakalanıyor mu, adres tutmuyorken
   eski ölçütler hâlâ çalışıyor mu, ve hiçbiri tutmadığında denetim AÇIK
   dönüyor mu. Sonuncusu en önemlisi: yanlış pozitif üreten bir denetim,
   dar bir alanda gerçekten uygun hakemleri eler.
   ===================================================================== */
echo "\n== 7. E-posta ucuncu tanimlayici olarak calisiyor ==\n";

/* ---- 7a. tg_yazar_epostalari(): adresin durabilecegi butun yerler ---- */
$eKayit = [
    'yazar'       => 'Ayşe Kaya ve Berk Demir',
    'yazar_bilgi' => ['ad' => 'Ayşe Kaya', 'orcid' => O_A, 'eposta' => '  AYSE.Kaya@Ornek-Sinama.ORG '],
    'yazar_liste' => [['ad' => 'Berk Demir', 'orcid' => O_B, 'eposta' => E_B]],
    'yazar_erisim' => ['eposta_acik' => E_C],
];
$el = tg_yazar_epostalari($eKayit);
den('yazar_bilgi.eposta okunuyor', in_array(E_A, $el, true), implode(',', $el));
den('yazar_liste[].eposta okunuyor', in_array(E_B, $el, true), implode(',', $el));
den('yazar_erisim.eposta_acik okunuyor', in_array(E_C, $el, true), implode(',', $el));
den('  buyuk harf ve bosluk normallestiriliyor', !in_array('  AYSE.Kaya@Ornek-Sinama.ORG ', $el, true));
den('  ve liste tekrarsiz', count($el) === count(array_unique($el)), implode(',', $el));
/* Adres olmayan bir değer listeye girmemeli: elle düzenlenmiş bir
   kayıtta 'yok' ya da '-' yazıyor olabilir ve aynı şeyi yazan iki ayrı
   kişi aynı kişi sayılırdı. */
$eBos = ['yazar_bilgi' => ['ad' => 'X', 'eposta' => 'yok'],
         'yazar_liste' => [['ad' => 'Y', 'eposta' => '  ']],
         'yazar_erisim' => ['eposta_acik' => '-']];
den('adres olmayan deger listeye girmiyor', tg_yazar_epostalari($eBos) === [],
    json_encode(tg_yazar_epostalari($eBos), JSON_UNESCAPED_UNICODE));
den('yazarsiz kayit bos liste veriyor', tg_yazar_epostalari([]) === []);
/* Bozuk alan çökertmemeli: yazar_liste dizge gelirse tg_dizi() eler. */
den('bozuk yazar_liste cokertmiyor', tg_yazar_epostalari(['yazar_liste' => 'bozuk']) === []);

/* ---- 7b. ACIGIN KENDISI: yazarin adresi, baska ad, baska ORCID ---- */
$e1 = [calisma([['Behiç Çetin', O_A, E_A]])];
$c = tg_hakem_cakisma($e1, 0, 'Behiç Çetin', O_A, E_A);
den('ad ve ORCID tutuyor: engel (eski davranis korunuyor)', ($c['kod'] ?? '') === 'kendisi',
    json_encode($c, JSON_UNESCAPED_UNICODE));
/* Bugün canlıda GEÇEN deneme. Ad değiştirilmiş, ORCID başka, adres
   yazarın kendi adresi. */
$c = tg_hakem_cakisma($e1, 0, 'Cahit B. Cetin', O_X, E_A);
den('YAZARIN ADRESI + BASKA AD + BASKA ORCID: engel', ($c['kod'] ?? '') === 'kendisi',
    json_encode($c, JSON_UNESCAPED_UNICODE));
/* Aynı deneme adsız da yapılabilir: ad alanı boş bırakılırsa denetim
   yalnızca adresten kurulmalı. */
$c = tg_hakem_cakisma($e1, 0, '', '', E_A);
den('  yalniz adresle de yakaliyor', ($c['kod'] ?? '') === 'kendisi', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Adres büyük harfle ya da boşluklu yazılırsa da aynı kişidir. */
$c = tg_hakem_cakisma($e1, 0, 'Cahit B. Cetin', O_X, '  Ayse.KAYA@Ornek-Sinama.org ');
den('  buyuk harf ve bosluk atlatmiyor', ($c['kod'] ?? '') === 'kendisi', json_encode($c, JSON_UNESCAPED_UNICODE));
/* Adres yalnızca yazar_erisim'de duruyorsa da yakalanmalı. */
$e2 = [calisma([['Behiç Çetin', O_A]], [], E_A)];
$c = tg_hakem_cakisma($e2, 0, 'Cahit B. Cetin', O_X, E_A);
den('  adres yalniz yazar_erisim de olsa yakaliyor', ($c['kod'] ?? '') === 'kendisi',
    json_encode($c, JSON_UNESCAPED_UNICODE));
/* Çok yazarlı çalışmada ikinci yazarın adresi de sayılır. */
$e3 = [calisma([['Ayşe Kaya', O_A, E_A], ['Berk Demir', O_B, E_B]])];
$c = tg_hakem_cakisma($e3, 0, 'Baska Ad', O_X, E_B);
den('  ikinci yazarin adresi de sayiliyor', ($c['kod'] ?? '') === 'kendisi',
    json_encode($c, JSON_UNESCAPED_UNICODE));

/* ---- 7c. Adres tutmuyorken eski olcutler yerinde ---- */
$c = tg_hakem_cakisma($e1, 0, 'Behiç Çetin', '', 'bambaska@ornek-sinama.org');
den('e-posta farkli ama ad ayni: yine engel', ($c['kod'] ?? '') === 'kendisi',
    json_encode($c, JSON_UNESCAPED_UNICODE));
$c = tg_hakem_cakisma($e1, 0, 'Bambaska Biri', O_A, 'bambaska@ornek-sinama.org');
den('e-posta farkli ama ORCID ayni: yine engel', ($c['kod'] ?? '') === 'kendisi',
    json_encode($c, JSON_UNESCAPED_UNICODE));

/* ---- 7d. YANLIS POZITIF YOK: hicbiri tutmuyorsa acik ---- */
$c = tg_hakem_cakisma($e1, 0, 'Deniz Yalçın', O_X, 'deniz.yalcin@ornek-sinama.org');
den('hicbiri tutmuyor: ACIK donuyor', $c === [], json_encode($c, JSON_UNESCAPED_UNICODE));
/* Aynı sunucudaki başka bir adres, adresin bir parçasını paylaşsa bile
   ayrı kişidir: eşleşme tam olmalı, içerme değil. */
$c = tg_hakem_cakisma($e1, 0, 'Deniz Yalçın', O_X, 'kaya@ornek-sinama.org');
den('  benzer ama ayni olmayan adres eslesmiyor', $c === [], json_encode($c, JSON_UNESCAPED_UNICODE));
/* Yazarın adresi hiç yazılmamışsa, adres veren temiz bir aday
   elenmemeli: boş alan herkesle eşleşen bir alan olamaz. */
$e4 = [calisma([['Ayşe Kaya', O_A]])];
$c = tg_hakem_cakisma($e4, 0, 'Deniz Yalçın', O_X, 'deniz.yalcin@ornek-sinama.org');
den('  yazarin adresi bosken temiz aday elenmiyor', $c === [], json_encode($c, JSON_UNESCAPED_UNICODE));

/* ---- 7e. Ikinci kural: ortak yazarlik adresten de kuruluyor ---- */
$e5 = [
    calisma([['Ayşe Kaya', O_A, E_A]]),
    calisma([['A. Kaya', '', E_A], ['Cahit B. Cetin', '', E_C]]),
];
$c = tg_hakem_cakisma($e5, 0, 'Bambaska Ad', O_X, E_C);
den('ortak yazarlik adresten kuruluyor', ($c['kod'] ?? '') === 'ortak', json_encode($c, JSON_UNESCAPED_UNICODE));

/* ---- 7f. Ucuncu kural: karsiliklilik adresten de kuruluyor ---- */
/* A, B'nin çalışmasına hakem (kayıtta yalnızca adres var, ad başka
   yazılmış). Şimdi B, A'nın çalışmasına aday oluyor ve o da adını
   değiştirmiş. İki tarafın da adı tutmuyor; tutan tek şey adres. */
$e6 = [
    calisma([['Ayşe Kaya', O_A, E_A]]),
    calisma([['B. Demir', '', E_B]], [hakem('Baska Yazim', $ICERI, '', 'kabul', E_A)]),
];
$c = tg_hakem_cakisma($e6, 0, 'Bambaska Ad', O_X, E_B);
den('karsiliklilik hakem profil adresinden kuruluyor', ($c['kod'] ?? '') === 'karsilikli',
    json_encode($c, JSON_UNESCAPED_UNICODE));
/* Aynı ilişki, adres hakem kaydının 'eposta_acik' alanındayken de
   görülmeli: davetin gittiği adres de o kişinin adresidir. */
$e7 = [
    calisma([['Ayşe Kaya', O_A, E_A]]),
    calisma([['B. Demir', '', E_B]], [hakem('Baska Yazim', $ICERI, '', 'kabul', '', E_A)]),
];
$c = tg_hakem_cakisma($e7, 0, 'Bambaska Ad', O_X, E_B);
den('  eposta_acik alanindan da kuruluyor', ($c['kod'] ?? '') === 'karsilikli',
    json_encode($c, JSON_UNESCAPED_UNICODE));
/* Pencere dışındaki hakemlik adresten de sayılmamalı: adres yeni bir
   ölçüt, yeni bir kural değil. */
$e8 = [
    calisma([['Ayşe Kaya', O_A, E_A]]),
    calisma([['B. Demir', '', E_B]], [hakem('Baska Yazim', $DISI, '', 'kabul', E_A)]),
];
den('  pencere disindaki hakemlik adresten de sayilmiyor',
    tg_hakem_cakisma($e8, 0, 'Bambaska Ad', O_X, E_B) === [],
    json_encode(tg_hakem_cakisma($e8, 0, 'Bambaska Ad', O_X, E_B), JSON_UNESCAPED_UNICODE));
/* Reddedilmiş davet de adresten sayılmamalı. */
$e9 = [
    calisma([['Ayşe Kaya', O_A, E_A]]),
    calisma([['B. Demir', '', E_B]], [hakem('Baska Yazim', $ICERI, '', 'ret', E_A)]),
];
den('  reddedilmis davet adresten de sayilmiyor',
    tg_hakem_cakisma($e9, 0, 'Bambaska Ad', O_X, E_B) === []);

/* ---- 7g. Dordunc parametre ISTEGE BAGLI: eski cagrilar kirilmadi ---- */
den('adres verilmeden cagri eskisi gibi calisiyor',
    (tg_hakem_cakisma($e1, 0, 'Behiç Çetin', O_A)['kod'] ?? '') === 'kendisi');
den('  ve adressiz temiz aday yine acik', tg_hakem_cakisma($e1, 0, 'Deniz Yalçın', O_X) === []);
/* Bozuk adres verilmesi çökertmemeli. */
$bozukAdres = ['', '   ', 'yok', '@', 'a@', str_repeat('x', 500) . '@y.org'];
$adresHata = 0;
foreach ($bozukAdres as $ba) {
    try { $r = tg_hakem_cakisma($e1, 0, 'Deniz Yalçın', O_X, $ba); if (!is_array($r)) $adresHata++; }
    catch (Throwable $t) { $adresHata++; }
}
den('bozuk adresle cokmuyor ve engel uretmiyor', $adresHata === 0, (string)$adresHata);

echo "\n== 8. Sebep metni yakalanan cakismayi soyluyor ==\n";
$c = tg_hakem_cakisma($k4, 0, 'Ayşe Kaya', O_A);
den('kendisi: kod ve metin ortusuyor',
    ($c['kod'] ?? '') === 'kendisi' && mb_stripos((string)($c['mesaj'] ?? ''), 'kendi çalışmasının hakemi') !== false,
    (string)($c['mesaj'] ?? ''));
$c = tg_hakem_cakisma($k6, 0, 'Cem Aydın', O_C);
den('ortak: kod ve metin ortusuyor',
    ($c['kod'] ?? '') === 'ortak' && mb_stripos((string)($c['mesaj'] ?? ''), 'ortak yayın') !== false,
    (string)($c['mesaj'] ?? ''));
$c = tg_hakem_cakisma($k1, 0, 'Berk Demir', O_B);
den('karsilikli: kod ve metin ortusuyor',
    ($c['kod'] ?? '') === 'karsilikli' && mb_stripos((string)($c['mesaj'] ?? ''), 'karşılıklı hakemlik') !== false,
    (string)($c['mesaj'] ?? ''));
den('  ve metin pencere suresini yaziyor',
    strpos((string)($c['mesaj'] ?? ''), (string)$AY . ' ay') !== false, (string)($c['mesaj'] ?? ''));
/* Her engelin bir sebebi olmalı: kodsuz ya da mesajsız dönüş,
   arayüzde "engellendiniz ama neden bilinmiyor" demektir. */
$hepsi = [
    tg_hakem_cakisma($k4, 0, 'Ayşe Kaya', O_A),
    tg_hakem_cakisma($k6, 0, 'Cem Aydın', O_C),
    tg_hakem_cakisma($k1, 0, 'Berk Demir', O_B),
];
$eksik = 0;
foreach ($hepsi as $h) {
    if (!in_array((string)($h['kod'] ?? ''), ['kendisi', 'ortak', 'karsilikli'], true)) $eksik++;
    if (trim((string)($h['mesaj'] ?? '')) === '') $eksik++;
}
den('her engelde kod ve mesaj birlikte var', $eksik === 0, (string)$eksik . ' eksik');
/* Ayrık üç sebep, aynı metni vermemeli. */
$metinler = array_map(fn($h) => (string)($h['mesaj'] ?? ''), $hepsi);
den('uc sebep uc ayri metin', count(array_unique($metinler)) === 3);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
