<?php
/* =====================================================================
   GÖNDERİM SİHİRBAZI: kapı ölçümü. Depoya girmez.

   Behiç'in tarifi: "çalışma gönder deyip ard arda sihirbaz gibi bir şey
   olsa; önce makale dilini seçecek, sonra başlık, sonra İngilizce
   başlık." Yani sıra şudur: DİL -> BAŞLIK -> İNGİLİZCE BAŞLIK.

   Bu kapı dört şeyi ölçer; dördü de devir belgesinin 7.1 maddesinde
   adıyla isteniyor:

     1. Adım yapısı tek kaynaktan mı geliyor, sıra doğru mu.
     2. Her adım TEK BAŞINA açılıyor mu (/basvuru.php?adim=N 200 mü,
        gövde </html> ile bitiyor mu, sunucu kütüğüne uyarı düşüyor mu).
     3. Betik çalışmazsa form YİNE DE gönderilebiliyor mu. Bu ölçümün
        yolu tek: gerçekten form-kodlu bir POST atmak ve kaydın
        basvurular.json'a düştüğünü görmek.
     4. Telif metni hâlâ doğru mu (sihirbaz onu bir adımın içine
        soktu; sokarken bozulmuş olabilir).

   ÖLÇÜLMEYEN, ve bilerek ölçülmeyen:
     - Taslağın geri gelmesi ve adım adım doğrulama TARAYICIDA olur;
       onları `sihirbaz-tarayici.js` ölçer. Ölçemediğimize GECTI
       vermiyoruz (OKUBENI, genel kural).

   TASLAK NEREDE DURUYOR — VERİLEN KARAR
     Taslak TARAYICIDA durur (localStorage), sunucuda değil. Gerekçe
     ikilidir ve ikisi de sistemin kendi sözünden gelir:
       a) Sunucudaki bir taslak, kullanıcının HENÜZ GÖNDERMEDİĞİ adını,
          e-postasını ve ORCID'ini toplamak olurdu. Devir belgesi
          "kişisel veri toplamadan" diyor; toplanmayan veri sızdırılamaz.
       b) Sayfanın kendi yan rayında şu söz yazılı: "hiçbir alan
          gönderilmeden sisteme yazılmaz." Sunucuda taslak tutmak bu
          sözü yalan yapardı. Söz değişmedi; taslak sözü çiğnemeyen
          yere kondu.
     Bu kapı (b) sözünün hâlâ doğru olduğunu ölçer: sunucuda taslak
     ucu YOKTUR.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php sihirbaz-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
$LOG  = getenv('KLOG') ?: '/home/claude/kg/sunucu.log';

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

/* Her istek ayrı bir adresten gelir: hız sınırı ölçümü bozmasın.
   (OKUBENI 12: kimlik CF-Connecting-IP ya da REMOTE_ADDR'den okunur,
   X-Forwarded-For bu yola hiç girmez.) */
function ip(): string { return '10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250); }

/* GÖNDERİM HESAP İSTER (kurul kararı, 15 Ağustos 2026). Betiksiz yol
   da kimlik ister: kimliksiz gönderim 401 döner ve bu doğrudur. Kapı
   bu yüzden bir oturum taşır; oturumun kendisi de ayrıca ölçülür
   (aşağıda, "hesapsız gönderim reddediliyor"). */
$KEREZ_SH = '';
function ist(string $yol, array $secenek = []): array {
    global $PORT, $KEREZ_SH;
    $bas = ['CF-Connecting-IP: ' . ip()];
    if ($KEREZ_SH !== '' && empty($secenek['kimliksiz'])) $bas[] = 'Cookie: ' . $KEREZ_SH;
    $http = ['method' => $secenek['metod'] ?? 'GET', 'ignore_errors' => true, 'timeout' => 30,
             'follow_location' => 0];
    if (isset($secenek['govde'])) {
        $bas[] = 'Content-Type: ' . ($secenek['tur'] ?? 'application/x-www-form-urlencoded');
        $http['content'] = $secenek['govde'];
    }
    $http['header'] = implode("\r\n", $bas);
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, stream_context_create(['http' => $http]));
    $kod = 0; $konum = ''; $tip = '';
    foreach (($http_response_header ?? []) as $s) {
        if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
        if (stripos($s, 'Location:') === 0) $konum = trim(substr($s, 9));
        if (stripos($s, 'Content-Type:') === 0) $tip = trim(substr($s, 13));
    }
    /* ÇEREZ HER YENİLENMEDE GÜNCELLENİR. İlk yazımda yalnız boşken
       alınıyordu; oysa /hesap/giris session_regenerate_id çağırır ve
       oturum yeni bir ada geçer. Eski adı taşıyan kapı, girişten sonra
       kimliksiz konuşuyordu ve kendi açtığı oturumu bulamıyordu. */
    if (empty($secenek['kimliksiz'])) {
        /* YALNIZ OTURUM ÇEREZİ ALINIR. İlk yazımda her Set-Cookie
           satırı alınıyordu ve form gönderiminden sonra uç, sonucu
           taşıyan 'kbv_sonuc' çerezini de gönderiyor; sonuncusu
           kazandığı için kapı oturum çerezinin yerine onu taşımaya
           başlıyor ve bir sonraki istekte kimliksiz kalıyordu. */
        foreach (($http_response_header ?? []) as $s2)
            if (stripos($s2, 'Set-Cookie:') === 0 && stripos($s2, 'PHPSESSID=') !== false
                && stripos($s2, 'deleted') === false) {
                $KEREZ_SH = explode(';', trim(substr($s2, 11)))[0];
            }
    }
    return ['kod' => $kod, 'govde' => (string)$g, 'konum' => $konum, 'tip' => $tip];
}
function sh_giris(): void {
    global $KEREZ_SH;
    $r = ist('/api/hesap/giris', ['metod' => 'POST', 'tur' => 'application/json',
        'govde' => json_encode(['kim' => 'olcumbas', 'parola' => 'olcum1234'])]);
    if ($r['kod'] !== 200) fwrite(STDERR, "  (uyari: olcum hesabina girilemedi: " . $r['kod'] . ")\n");
}

/* Okura görünen metin: betik ve biçem çıkarılır. */
function gorunur(string $html): string {
    $h = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);
    return trim((string)preg_replace('/\s+/u', ' ',
        html_entity_decode(strip_tags((string)$h), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/* OKUBENI 37: kaynakta dize ararken YORUMLARI ÇIKAR. Bu projede her
   düzeltmenin yanına neyin neden yapıldığı yazılıyor; o yazı kapıya
   takılmamalı. Ölçülen çağrıdır, anlatı değil. */
function kodsuz(string $dosya): string {
    $ham = (string)@file_get_contents($dosya);
    if ($ham === '') return '';
    $cikti = '';
    foreach (token_get_all($ham) as $t) {
        if (is_array($t)) {
            if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) { $cikti .= ' '; continue; }
            $cikti .= $t[1];
        } else $cikti .= $t;
    }
    /* JS yorumları da düşsün: sihirbazın betiği PHP dizgesi içinde. */
    $cikti = preg_replace('#/\*.*?\*/#s', ' ', $cikti);
    return (string)preg_replace('#(^|[\s;{}])//[^\n]*#', '$1 ', (string)$cikti);
}

function sayac(string $dosya): int {
    global $VERI;
    $d = json_decode((string)@file_get_contents($VERI . '/' . $dosya), true);
    return is_array($d) ? count($d) : -1;
}

$logOnce = (int)@filesize($LOG);

/* =====================================================================
   1. ADIM YAPISI TEK KAYNAKTAN GELİYOR MU, SIRA DOĞRU MU
   ===================================================================== */
echo "== 1. Adım yapısı ==\n";

den('tg_basvuru_adimlari() var', function_exists('tg_basvuru_adimlari'));
$adimlar = function_exists('tg_basvuru_adimlari') ? tg_basvuru_adimlari() : [];
$anahtarlar = array_column($adimlar, 'k');
olc('adımlar: ' . implode(' > ', $anahtarlar));
den('en az yedi adım var', count($adimlar) >= 7, (string)count($adimlar));
/* BEKLENTİ BİLEREK DEĞİŞTİ (12 Ağustos akşamı).
   Önce "ilk adım ÇALIŞMA olmalı" yazıyordu. Bildirilen kusur şuydu:
   yazar başvuru sayfasına geldiğinde önce iki ekran boyu koşul metni
   görüyor, formu aşağıda arıyordu (ölçüldü: ilk alan 2186. piksel).
   Koşullar kaldırılmadı; sihirbazın BİRİNCİ ADIMI oldu. Bu yüzden ilk
   adım artık 'kosul', ikinci adım 'calisma'dır. Kapı bunu sessizce
   kabul etmez: iki adımın da yerini ayrı ayrı ölçer. */
den('ilk adım GÖNDERİM KOŞULLARI (okunmadan geçilen bir önsöz değil, adım)',
    ($anahtarlar[0] ?? '') === 'kosul', (string)($anahtarlar[0] ?? '-'));
den('  ikinci adım ÇALIŞMA (dil/başlık burada başlar)',
    ($anahtarlar[1] ?? '') === 'calisma', (string)($anahtarlar[1] ?? '-'));
den('son adım TELİF (imza en sonda)', (string)end($anahtarlar) === 'telif', (string)end($anahtarlar));
/* OKUBENI 26/34: BOŞ ile BOŞU karşılaştıran deneme hep geçer. Liste
   hiç yokken "hepsinin adı var" ve "hepsi biricik" kendiliğinden
   doğrudur ve sayıyı şişirir. Liste boşsa deneme geçemez. */
den('her adımın Türkçe ve İngilizce adı var',
    $adimlar !== [] && count(array_filter($adimlar, fn($a) => trim((string)($a['tr'] ?? '')) !== '' && trim((string)($a['en'] ?? '')) !== '')) === count($adimlar));
den('adım anahtarları biricik',
    $anahtarlar !== [] && count(array_unique($anahtarlar)) === count($anahtarlar));

/* Adım listesi TEK KAYNAK olmalı: sayfa hem bölümleri hem yan rayı
   hem de sihirbaz şeridini bu listeden üretmeli. Elle yazılmış ikinci
   bir liste zamanla ayrışır (devir belgesi, "tek kaynak kuralı"). */
$bvKod = kodsuz($KOD . '/basvuru.php');
den('basvuru.php adım listesini tg_basvuru_adimlari() ile okuyor',
    strpos($bvKod, 'tg_basvuru_adimlari(') !== false);
/* OKUBENI 36: deseni koda değil, kodun YAPTIĞI ŞEYE göre yaz. İlk
   yazımda burada "=> 'f-" dizesi aranıyordu; oysa ray listeyi tek
   kaynaktan üretirken de o dizeyi kuruyor ('f-' . $a['k']). Aranan
   şey biçim değil, rayın adım listesini OKUYUP okumadığıdır.
   Ayrıca sayfada iki listenin sırası ayrışmamalı: ikisi de aynı
   diziden geldiğinde bu kendiliğinden sağlanır, ama ölçülür. */
/* SAĞ RAYDAKİ ADIM LİSTESİ KALDIRILDI, ÇÜNKÜ ŞERİT ZATEN ORADA.
   Aynı liste iki yerde duruyordu: formun üstündeki adım şeridi
   (tıklanınca adımı açar) ve sağ ray (yalnız kaydırır). Ölçülen şey
   artık "ikinci liste var mı" değil, İKİNCİ LİSTE YOK MU: elle
   yazılmış bir adım listesi de, üretilmiş ikinci bir liste de
   olmamalı. Şerit tek kaynaktan (tg_basvuru_adimlari) üretilir. */
den('  sağ rayda ikinci bir adım listesi yok',
    (bool)preg_match('#k_belge_yan\(\s*\[\s*\]#s', $bvKod));
den('  adım şeridi tek kaynaktan üretiliyor',
    (bool)preg_match('#sh-serit.{0,400}foreach\s*\(\s*\$shAdimlar#s', $bvKod));

/* =====================================================================
   2. SAYFA VE ADIMLAR: HER ADIM TEK BAŞINA AÇILIYOR MU
   ===================================================================== */
echo "\n== 2. Adımlar tek başına açılıyor mu ==\n";

$s = ist('/basvuru.php?lang=tr');
den('/basvuru.php 200', $s['kod'] === 200, (string)$s['kod']);
den('  gövde </html> ile bitiyor (OKUBENI 8: kırpılmış sayfa)',
    str_ends_with(rtrim($s['govde']), '</html>'));
$sayfa = $s['govde'];

$rayS = []; $formS = [];
if (preg_match('#<details class="blg-nav".*?</details>#s', $sayfa, $mm)) {
    preg_match_all('/href="#f-([a-z]+)"/', $mm[0], $r1);
    $rayS = $r1[1];
}
preg_match_all('/data-adim="([a-z]+)"/', $sayfa, $r2); $formS = $r2[1];
den('  sayfada ikinci bir adım listesi basılmıyor', $rayS === [], implode(',', $rayS));
/* Şeridin sırası ile bölümlerin sırası aynı olmalı: ikisi de aynı
   diziden gelir, ama ölçülür. */
$seritS = [];
if (preg_match('#<ol class="sh-serit".*?</ol>#s', $sayfa, $mm2)) {
    preg_match_all('/href="[^"]*#f-([a-z]+)"/', $mm2[0], $r3);
    $seritS = $r3[1];
}
den('  şeridin sırası form bölümlerinin sırasıyla aynı',
    $seritS !== [] && $seritS === $formS, implode(',', $seritS) . ' | ' . implode(',', $formS));
den('  şerit koşul adımıyla başlıyor', ($seritS[0] ?? '') === 'kosul', (string)($seritS[0] ?? '-'));

$hepsi200 = true; $hepsiAdim = true; $kirpik = 0;
for ($i = 1; $i <= max(1, count($adimlar)); $i++) {
    $r = ist('/basvuru.php?lang=tr&adim=' . $i);
    if ($r['kod'] !== 200) { $hepsi200 = false; olc('adım ' . $i . ' kod ' . $r['kod']); }
    if (!str_ends_with(rtrim($r['govde']), '</html>')) $kirpik++;
    /* Sunucu, istenen adımı sayfaya YAZMALI ki betik onu açabilsin ve
       betik yokken de kullanıcı doğru yere düşsün. */
    if (strpos($r['govde'], 'data-adim-baslangic="' . $i . '"') === false) {
        $hepsiAdim = false; olc('adım ' . $i . ' sayfada işaretlenmemiş');
    }
}
den('her adım tek başına 200 dönüyor', $hepsi200);
den('  hiçbiri kırpılmıyor', $kirpik === 0, (string)$kirpik);
den('  istenen adım sunucu tarafından sayfaya yazılıyor', $hepsiAdim);

$r = ist('/basvuru.php?lang=tr&adim=999');
den('sınır dışı adım sayfayı kırmıyor (1. adıma düşer)',
    $r['kod'] === 200 && strpos($r['govde'], 'data-adim-baslangic="1"') !== false, (string)$r['kod']);
$r = ist('/basvuru.php?lang=tr&adim=' . urlencode('<script>'));
den('adım değeri sayıya zorlanıyor (uydurma değer sayfaya basılmıyor)',
    $r['kod'] === 200 && strpos($r['govde'], '<script>"') === false
    && strpos($r['govde'], 'data-adim-baslangic="1"') !== false);

/* =====================================================================
   3. SIRA: DİL -> BAŞLIK -> İNGİLİZCE BAŞLIK
   ===================================================================== */
echo "\n== 3. İlk adımın içindeki sıra ==\n";

$pDil = strpos($sayfa, 'id="mDil"');
$pBas = strpos($sayfa, 'id="mBaslik"');
$pBEn = strpos($sayfa, 'id="mBaslikEn"');
den('çalışmanın dili alanı var', $pDil !== false);
den('başlık alanı var', $pBas !== false);
den('İNGİLİZCE BAŞLIK alanı var', $pBEn !== false);
den('sıra dil -> başlık -> İngilizce başlık',
    $pDil !== false && $pBas !== false && $pBEn !== false && $pDil < $pBas && $pBas < $pBEn,
    $pDil . ' / ' . $pBas . ' / ' . $pBEn);
/* Üçü de İLK adımda olmalı; ikinci adıma taşan bir başlık alanı
   "önce dil, sonra başlık" sözünü tutmaz. */
$ilkAdimBas = strpos($sayfa, 'data-adim="calisma"');
$sonraki = array_values(array_filter($anahtarlar, fn($k) => $k !== 'kosul' && $k !== 'calisma'));
$ikinciAdim = strpos($sayfa, 'data-adim="' . ($sonraki[0] ?? 'basvuran') . '"');
den('üçü de ÇALIŞMA adımının içinde',
    $ilkAdimBas !== false && $ikinciAdim !== false && $pDil > $ilkAdimBas && $pBEn < $ikinciAdim);

/* İngilizce başlık zorunlu DEĞİLDİR. Sistem "kayıt her zaman sizin
   yazdığınız dildeki metindir" diyor; İngilizce başlığı zorunlu
   tutmak, o sözü sessizce geri almak olurdu. Ama istenmesinin bir
   gerekçesi vardır ve o gerekçe sayfada yazılı olmalı. */
$g = gorunur($sayfa);
/* ÖLÇÜM DÜZELTİLDİ — KURAL AYNI, SÖZCÜK DEĞİŞTİ.
   Alan artık "İngilizce başlık" değil "Başlık (English)" diye
   imleniyor: ikinci dil ayar dosyasından geliyor ve dil adları listede
   KENDİ dillerinde yazılı ('English', 'Deutsch'). Türkçede sıfat gibi
   kullanılınca "English başlık" gibi bozuk bir tamlama çıkıyordu.
   Ölçülen şart değişmedi ve değişmemeli: alan İSTEĞE BAĞLI olarak
   imlenmiş olmalı ve neden istendiği sayfada yazmalı. Kapı artık
   sözcüğü değil, alanın kendisini ve künye sekmesini okuyor. */
$kunyeAdi = function_exists('tg_kunye_dil_adi') ? tg_kunye_dil_adi() : 'English';
den('künye başlığı alanı var', str_contains($g, 'Başlık (' . $kunyeAdi . ')')
    || str_contains($g, 'Title (' . $kunyeAdi . ')'), $kunyeAdi);
/* ETİKET KURALA BAĞLIDIR, DONDURULMAZ. 15 Ağustos 2026 kurul kararıyla
   kısa künye ZORUNLU oldu; "isteğe bağlı" arayan bir ölçüm, kurul
   kararını kusur diye bildirirdi. Ölçülen şey kuralın SAYFAYA
   GEÇMESİDİR: zorunluysa "zorunlu", değilse "isteğe bağlı" yazmalı. */
$kunyeZor = function_exists('tg_kunye_zorunlu') && tg_kunye_zorunlu();
olc('kısa künye: ' . ($kunyeZor ? 'ZORUNLU' : 'isteğe bağlı'));
den('künye alanları kurala göre imlenmiş',
    $kunyeZor
      ? ((bool)preg_match('/Başlık \(' . preg_quote($kunyeAdi, '/') . '\)\s*zorunlu/iu', $g)
         || (bool)preg_match('/Title \(' . preg_quote($kunyeAdi, '/') . '\)\s*required/iu', $g))
      : ((bool)preg_match('/Başlık \(' . preg_quote($kunyeAdi, '/') . '\)\s*(isteğe bağlı|zorunlu değil)/iu', $g)
         || (bool)preg_match('/Title \(' . preg_quote($kunyeAdi, '/') . '\)\s*(optional|not required)/iu', $g)));
den('  neden istendiği yazılı (dizin/arama/atıf)',
    (bool)preg_match('/(dizin|arama|atıf|uluslararası)/iu',
        (string)(preg_match('/KÜNYESİDİR.{0,600}/su', $g, $m) ? $m[0] : '')));

/* =====================================================================
   4. BETİKSİZ FORM: YAPISI
   ===================================================================== */
echo "\n== 4. Betiksiz form: yapı ==\n";

den('gerçek bir <form> var ve POST ediyor',
    (bool)preg_match('#<form[^>]+method="post"#i', $sayfa));
den('  eylemi başvuru ucuna gidiyor',
    (bool)preg_match('#<form[^>]+action="[^"]*yazar-basvuru#i', $sayfa));
den('  biçim=form imi var (uç, yanıtı JSON değil sayfa olarak verecek)',
    (bool)preg_match('#name="bicim"[^>]*value="form"#i', $sayfa)
    || (bool)preg_match('#value="form"[^>]*name="bicim"#i', $sayfa));

/* Betik yokken gönderilecek her alanın name'i olmalı; id yeterli
   değildir, tarayıcı name'i olmayan alanı GÖNDERMEZ. */
$adliAlanlar = ['makale_dil', 'makale_baslik', 'makale_baslik_en', 'makale_ozet',
                'ad', 'eposta', 'orcid', 'yz_kullanim', 'yz_aciklama',
                'yz_etik_kabul', 'etik_durum', 'veri_beyan', 'telif_kabul'];
/* Benzerlik alanları YALNIZCA şart açıkken beklenir. 15 Ağustos 2026
   kurul kararıyla adım tümüyle kaldırıldı; kapalı bir adımın alanlarını
   aramak, olmayan bir şeyi eksik bildirmek olurdu. Şart geri açılırsa
   alanlar da geri gelir ve bu satır onları yeniden arar. */
if (tg_benzerlik_sarti()) {
    array_push($adliAlanlar, 'intihal_arac', 'intihal_oran', 'intihal_tek_kaynak', 'intihal_link');
}
$eksik = [];
foreach ($adliAlanlar as $a) {
    if (!preg_match('#name="' . preg_quote($a, '#') . '"#', $sayfa)) $eksik[] = $a;
}
den('gönderilecek alanların hepsinde name var', $eksik === [], implode(', ', $eksik));

/* Betiksiz kullanıcı gönder düğmesine basabilmeli: type="button" olan
   bir düğme formu göndermez. */
den('gönder düğmesi betiksiz de çalışır (type=submit)',
    (bool)preg_match('#<button[^>]+type="submit"[^>]*id="gonder"#i', $sayfa)
    || (bool)preg_match('#<button[^>]+id="gonder"[^>]*type="submit"#i', $sayfa));

den('betiksiz kullanıcıya durum <noscript> ile anlatılıyor',
    stripos($sayfa, '<noscript') !== false);

/* =====================================================================
   5. BETİKSİZ FORM: GERÇEKTEN GÖNDERİLİYOR MU
   ---------------------------------------------------------------------
   Ölçümün tek dürüst yolu: form-kodlu POST at, kaydın düştüğünü gör.
   ===================================================================== */
echo "\n== 5. Betiksiz form: gerçek gönderim ==\n";

$once = sayac('basvurular.json');
olc('gönderimden önce basvurular.json: ' . $once . ' kayıt');

$govde = http_build_query([
    'bicim' => 'form',
    'unvan' => 'Dr.', 'ad' => 'Betiksiz Deneme', 'eposta' => 'betiksiz@ornek.edu.tr',
    'kurum' => 'Deneme Üniversitesi', 'orcid' => '0000-0002-1825-0097',
    'makale_dil' => 'tr',
    'makale_baslik' => 'Betiksiz gönderim denemesi',
    'makale_baslik_en' => 'A submission test without scripting',
    'makale_genis_ozet_en' => str_repeat('measurement word ', 320),
    'makale_ozet' => 'Betik kapalıyken formun gönderilebildiğini ölçen deneme kaydı.',
    'makale_ozet_en' => 'A record measuring that the form can be sent with scripting off.',
    /* 15 Ağustos 2026: tam metin ve kaynakça gönderim anında isteniyor. */
    'makale_metin' => '<h2>Giris</h2><p>' . str_repeat('olcum metni ', 500) . '</p>',
    'makale_kaynakca' => '<p>Olcum, K. (2026). Betiksiz gonderim. Sinama.</p>',
    'alanlar' => ['1.2'],
    'intihal_arac' => 'iThenticate', 'intihal_oran' => '9.4', 'intihal_tek_kaynak' => '3.1',
    'intihal_link' => 'https://ornek.edu.tr/rapor.pdf',
    'yz_kullanim' => 'yok', 'yz_aciklama' => '', 'yz_etik_kabul' => '1',
    /* 15 Ağustos 2026: yeni beyanlar (çıkar çatışması, başka yerde
       değerlendirilmeme, fon). Betiksiz gönderim de bunları taşır. */
    'cikar_catismasi' => 'yok', 'tek_gonderim' => '1', 'fon_durum' => 'yok',
    'yazar_tam' => 'tek',
    'etik_durum' => 'gereksiz',
    'veri_beyan' => 'yok',
    'telif_kabul' => '1',
    'kosullar_okundu' => '1',
]);
/* ÖNCE KİMLİKSİZ: gönderim hesap ister ve bu KURALIN KENDİSİ ölçülür.
   Sayfa "önce hesabınızı açın" diyorsa, uç da öyle davranmalı.
   Ölçüm JSON ile yapılır: form-kodlu istekte uç 303 ile sayfaya döner
   ve durum kodu iletiyi değil taşıma biçimini anlatır. */
$kimliksiz = ist('/api/yazar-basvuru', ['metod' => 'POST', 'kimliksiz' => true,
    'tur' => 'application/json', 'govde' => json_encode(['ad' => 'Kimliksiz'])]);
den('hesapsız gönderim reddediliyor (401)', $kimliksiz['kod'] === 401,
    $kimliksiz['kod'] . ' ' . mb_substr($kimliksiz['govde'], 0, 90));
den('  ve neden reddedildiği söyleniyor',
    (bool)preg_match('/(hesab|account)/iu', $kimliksiz['govde']), mb_substr($kimliksiz['govde'], 0, 90));

sh_giris();
$p = ist('/api/yazar-basvuru', ['metod' => 'POST', 'govde' => $govde]);
olc('yanıt kodu ' . $p['kod'] . ', tür: ' . ($p['tip'] ?: '-') . ', konum: ' . ($p['konum'] ?: '-'));
den('form gönderimi JSON DEĞİL, sayfaya yönlendirme dönüyor',
    $p['kod'] === 303 && stripos($p['tip'], 'json') === false, (string)$p['kod']);
den('  yönlendirme başvuru sayfasına gidiyor',
    stripos($p['konum'], 'basvuru.php') !== false, $p['konum']);
$sonra = sayac('basvurular.json');
den('  kayıt gerçekten düştü', $sonra === $once + 1, $once . ' -> ' . $sonra);

/* Kayda giden ne ise o yazılmalı: İngilizce başlık kaybolmamalı. */
$kayitlar = json_decode((string)@file_get_contents($VERI . '/basvurular.json'), true);
$sonKayit = is_array($kayitlar) ? (array)end($kayitlar) : [];
den('  İngilizce başlık kayda geçti',
    (string)($sonKayit['makale_baslik_en'] ?? '') === 'A submission test without scripting',
    (string)($sonKayit['makale_baslik_en'] ?? '-'));
den('  çalışmanın dili kayda geçti', (string)($sonKayit['makale_dil'] ?? '') === 'tr');
den('  başvuran adı kayda geçti',
    (string)(($sonKayit['basvuran']['ad'] ?? '')) === 'Betiksiz Deneme');

/* Eksik alanla gönderim: kullanıcı ham JSON görmemeli, sayfaya dönüp
   hangi alanın eksik olduğunu okumalı. Ve kayıt DÜŞMEMELİ. */
$oncekiSay = sayac('basvurular.json');
$eksikGovde = http_build_query([
    'bicim' => 'form', 'ad' => 'Eksik Deneme', 'eposta' => 'eksik@ornek.edu.tr',
    'orcid' => '0000-0002-1825-0097', 'makale_dil' => 'tr',
    'makale_baslik' => '',                       /* eksik olan bu */
    'intihal_arac' => 'iThenticate', 'intihal_oran' => '9.4', 'intihal_tek_kaynak' => '3.1',
    'intihal_link' => 'https://ornek.edu.tr/rapor.pdf',
    'yz_kullanim' => 'yok', 'yz_etik_kabul' => '1', 'etik_durum' => 'gereksiz',
    'veri_beyan' => 'yok', 'telif_kabul' => '1',
]);
$pe = ist('/api/yazar-basvuru', ['metod' => 'POST', 'govde' => $eksikGovde]);
den('eksik alanlı gönderim de sayfaya dönüyor (ham JSON değil)',
    $pe['kod'] === 303 && stripos($pe['konum'], 'basvuru.php') !== false,
    $pe['kod'] . ' ' . $pe['konum']);
den('  hata sayfaya taşınıyor (adres ya da çerezle)',
    stripos($pe['konum'], 'hata') !== false || stripos(implode(' ', $http_response_header ?? []), 'set-cookie') !== false);
den('  eksik gönderimde kayıt DÜŞMÜYOR', sayac('basvurular.json') === $oncekiSay,
    $oncekiSay . ' -> ' . sayac('basvurular.json'));

/* KOŞUL BEYANI OLMADAN KAYIT DÜŞMEMELİ. Tarayıcıda kutu required'dır;
   ama kapı tarayıcıda değil sunucuda olmalıdır, yoksa doğrudan bir
   POST beyanı atlar ve kayıtta "koşullar okundu: hayır" yazan bir
   başvuru birikir. */
$oncekiSay2 = sayac('basvurular.json');
$kosulsuz = http_build_query([
    'bicim' => 'form', 'unvan' => 'Dr.', 'ad' => 'Koşulsuz Deneme',
    'eposta' => 'kosulsuz@ornek.edu.tr', 'orcid' => '0000-0002-1825-0097',
    'makale_dil' => 'tr', 'makale_baslik' => 'Koşul beyanı olmayan gönderim',
    'intihal_arac' => 'iThenticate', 'intihal_oran' => '9.4', 'intihal_tek_kaynak' => '3.1',
    'intihal_link' => 'https://ornek.edu.tr/rapor.pdf',
    'yz_kullanim' => 'yok', 'yz_etik_kabul' => '1', 'etik_durum' => 'gereksiz',
    'veri_beyan' => 'yok', 'telif_kabul' => '1',
    /* kosullar_okundu bilerek YOK */
]);
$pk = ist('/api/yazar-basvuru', ['metod' => 'POST', 'govde' => $kosulsuz]);
den('koşul beyanı olmayan gönderimde kayıt DÜŞMÜYOR',
    sayac('basvurular.json') === $oncekiSay2, $oncekiSay2 . ' -> ' . sayac('basvurular.json'));
$pkj = ist('/api/yazar-basvuru', ['metod' => 'POST', 'tur' => 'application/json',
    'govde' => json_encode(['ad' => 'Dr. Koşulsuz', 'eposta' => 'k@ornek.edu.tr',
        'orcid' => '0000-0002-1825-0097', 'makale_dil' => 'tr',
        'makale_baslik' => 'Koşul beyanı olmayan gönderim',
        'intihal_arac' => 'iThenticate', 'intihal_oran' => 9.4, 'intihal_tek_kaynak' => 3.1,
        'intihal_link' => 'https://ornek.edu.tr/rapor.pdf',
        'yz_kullanim' => 'yok', 'yz_etik_kabul' => true, 'etik_durum' => 'gereksiz',
        'veri_beyan' => 'yok', 'telif_kabul' => true], JSON_UNESCAPED_UNICODE)]);
den('  neden söyleniyor (400 ve koşul iletisi)',
    $pkj['kod'] === 400 && str_contains(mb_strtolower($pkj['govde']), 'koşul'),
    $pkj['kod'] . ' ' . mb_substr($pkj['govde'], 0, 90));

/* Sayfadaki onay kutusu da yerinde mi: sunucu isteyip sayfa sormazsa
   yazar neyi eksik bıraktığını anlamaz. */
den('  sayfada koşul onay kutusu var ve required',
    (bool)preg_match('#name="kosullar_okundu"[^>]*required|required[^>]*name="kosullar_okundu"#', $sayfa));
/* BEYAN SON ADIMDA, METİN BİRİNCİ ADIMDA.
   Kutu önce koşul adımının içindeydi. Sonra "kayıtlı kullanıcı her
   gönderimde koşulları baştan görmesin" istendi; adım atlanabilir
   olunca kutu da atlanıyordu. İkisi ayrıldı: METİN atlanabilir (daha
   önce okunmuşsa), BEYAN atlanamaz. */
den('  koşul beyanı SON adımda (telif ile birlikte)',
    strpos($sayfa, 'name="kosullar_okundu"') > strpos($sayfa, 'data-adim="telif"'));
den('  koşul metni hâlâ birinci adımda',
    strpos($sayfa, 'data-adim="kosul"') < strpos($sayfa, 'data-adim="calisma"'));
den('  beyanın yanında koşulları yeniden açan bağ var',
    (bool)preg_match('#data-adim-git="kosul"#', $sayfa));

/* =====================================================================
   5.b KOŞULU DAHA ÖNCE OKUYAN ADIMI GÖRMEZ — AMA METİN DEĞİŞMEDİYSE
   ===================================================================== */
echo "\n== 5.b Koşul adımının atlanması ==\n";
den('tg_kosul_okundu_mu() var', function_exists('tg_kosul_okundu_mu'));
$surum = '';
if (preg_match('#data-kosul-surum="([a-f0-9]+)"#', $sayfa, $ms)) $surum = $ms[1];
olc('koşul metninin sürümü: ' . ($surum !== '' ? $surum : 'YOK'));
den('sayfa koşul sürümünü basıyor (metinden üretilmiş)', strlen($surum) >= 8, $surum);
den('  sürüm elle yazılmış bir sayı değil, metinden üretiliyor',
    (bool)preg_match('#\$kosulSurum\s*=.*sha1\(#s', $bvKod));

$byol = $VERI . '/basvurular.json';
$yedek = (string)@file_get_contents($byol);
$bl = json_decode($yedek, true); if (!is_array($bl)) $bl = [];
$bl[] = ['tarih' => date('c'), 'basvuran' => ['eposta' => 'okumus@ornek.edu.tr'],
         'kosullar_okundu' => true, 'kosul_surum' => $surum];
@file_put_contents($byol, json_encode($bl, JSON_UNESCAPED_UNICODE));
$r1 = tg_kosul_okundu_mu('okumus@ornek.edu.tr', $surum);
$r2 = tg_kosul_okundu_mu('okumus@ornek.edu.tr', 'baskasurum');
$r3 = tg_kosul_okundu_mu('baskasi@ornek.edu.tr', $surum);
@file_put_contents($byol, $yedek);   /* ölçüm verisi geri alınır */
den('aynı sürümü okumuş olan "okudu" sayılıyor', $r1['okundu'] === true, json_encode($r1));
den('  okuduğu tarih de dönüyor', preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$r1['tarih']) === 1, (string)$r1['tarih']);
den('KOŞULLAR DEĞİŞTİYSE OKUMUŞ SAYILMIYOR', $r2['okundu'] === false, json_encode($r2));
den('başkasının okuması kimseyi okumuş yapmıyor', $r3['okundu'] === false, json_encode($r3));
den('  sınama verisi geri yüklendi',
    (string)@file_get_contents($byol) === $yedek);

/* Betikli yol bozulmadı: JSON gönderimi hâlâ JSON döndürüyor. */
$pj = ist('/api/yazar-basvuru', ['metod' => 'POST', 'tur' => 'application/json',
    'govde' => json_encode(['ad' => '', 'eposta' => ''], JSON_UNESCAPED_UNICODE)]);
den('betikli yol bozulmadı: JSON isteğe JSON yanıt', $pj['kod'] === 400
    && stripos($pj['tip'], 'json') !== false, $pj['kod'] . ' ' . $pj['tip']);

/* =====================================================================
   6. TELİF METNİ HÂLÂ DOĞRU MU
   ---------------------------------------------------------------------
   Sihirbaz telif kutusunu bir adımın içine soktu. Sokarken bozulmuş
   olabilir: ikinci değişmez ilke "telif hakkı YAZARINDA KALMAK ÜZERE"
   der ve bu cümle sayfadan düşerse kimse fark etmez.
   ===================================================================== */
echo "\n== 6. Telif metni ==\n";

$devirTR = '/(telif|hak)\w*\s+(hak\w*\s+)?[^.]{0,60}?devre|devret\w*\s+[^.]{0,40}(telif|hak)|bütün\s+haklar\w*\s+[^.]{0,40}devr/iu';
den('devir iddiası YOK (Türkçe)', !preg_match($devirTR, $g));
den('telif hakkının yazarda kaldığı yazılı',
    (bool)preg_match('/telif hakkı bende/iu', $g) || (bool)preg_match('/telif hakkı[^.]{0,40}yazar/iu', $g));
den('verilen iznin kapsamı yazılı (yayımlama, arşivleme, CC BY 4.0)',
    stripos($g, 'CC BY 4.0') !== false && stripos($g, 'arşiv') !== false);
den('telif onayı formun bir alanı (name var)',
    (bool)preg_match('#name="telif_kabul"#', $sayfa));

$sEn = ist('/basvuru.php?lang=en');
$gEn = gorunur($sEn['govde']);
den('İngilizce sayfada da devir iddiası yok',
    !preg_match('/transfer\w*\s+[^.]{0,40}copyright|assign\w*\s+[^.]{0,30}copyright/i', $gEn));
den('  İngilizce sayfada da hakkın yazarda kaldığı yazılı',
    stripos($gEn, 'copyright in this work remains with me') !== false
    || stripos($gEn, 'copyright remains with') !== false);

/* =====================================================================
   7. TASLAK SUNUCUDA DURMUYOR
   ===================================================================== */
echo "\n== 7. Taslak nerede duruyor ==\n";

$t = ist('/api/basvuru-taslak', ['metod' => 'POST', 'tur' => 'application/json', 'govde' => '{"ad":"x"}']);
den('sunucuda taslak ucu YOK (kişisel veri gönderilmeden toplanmıyor)',
    $t['kod'] === 404 || $t['kod'] === 400 || $t['kod'] === 405, (string)$t['kod']);
den('sayfa "gönderilmeden sisteme yazılmaz" sözünü hâlâ veriyor',
    stripos($g, 'gönderilmeden sisteme yazılmaz') !== false
    || stripos($g, 'gönderilmeden sisteme') !== false);
den('taslak tarayıcıda saklanıyor (localStorage)',
    stripos($bvKod, 'localStorage') !== false);
den('  taslakta parola/oturum benzeri bir şey saklanmıyor',
    !preg_match('/localStorage[^;]{0,120}(sifre|parola|password|token|oturum)/i', $bvKod));

/* =====================================================================
   8. ERİŞİLEBİLİRLİK: ADIMLAR KLAVYEYLE GEZİLEBİLİR OLMALI
   (tarayıcı ölçümü sihirbaz-tarayici.js'de; burada yalnız kaynakta
    bilinebilenler)
   ===================================================================== */
echo "\n== 8. Adımların erişilebilirlik iskeleti ==\n";

/* Yine OKUBENI 26: adım listesi boşken ">= 0" her sayfada doğrudur.
   Beklenen sayı listeden gelir, ama liste boşsa deneme geçemez. */
$bekAdim = count($adimlar);
den('adım bölümleri fieldset/legend ile yazılmış (ekran okuyucu grubu görsün)',
    $bekAdim > 0 && substr_count($sayfa, '<fieldset') >= $bekAdim
    && substr_count($sayfa, '<legend') >= $bekAdim,
    substr_count($sayfa, '<fieldset') . ' fieldset / ' . substr_count($sayfa, '<legend') . ' legend');
den('kapalı adımlar hidden ÖZNİTELİĞİYLE kapanıyor (sınıfla değil)',
    $bekAdim > 0 && substr_count($sayfa, 'data-adim=') >= $bekAdim
    && substr_count($sayfa, ' hidden') >= $bekAdim - 1,
    substr_count($sayfa, 'data-adim=') . ' bölüm / ' . substr_count($sayfa, ' hidden') . ' hidden');
den('ilerleme durumu ekran okuyucuya söyleniyor (aria-live ya da rol)',
    stripos($sayfa, 'aria-live') !== false);
den('adım şeridi <ol> (sıra bir listedir)',
    (bool)preg_match('#<ol[^>]*class="[^"]*sh-serit#i', $sayfa));

/* =====================================================================
   9. SUNUCU KÜTÜĞÜ
   ===================================================================== */
echo "\n== 9. Sunucu kütüğü ==\n";
$yeni = (string)@file_get_contents($LOG, false, null, $logOnce);
$uyari = preg_match_all('/warning|deprecated|notice|fatal/i', $yeni);
den('bu ölçüm sırasında sunucu uyarısı yok', $uyari === 0, (string)$uyari);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
