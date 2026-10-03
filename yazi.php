<?php
/* Makale / yazı sayfası - sunucu tarafında render (Google Scholar & sosyal önizleme uyumlu) */
require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/k/seo.php';
require_once __DIR__ . '/k/muhur.php';   /* tamga mührü */
/* Oturum, hiçbir çıktı verilmeden ÖNCE açılmalıdır; yoksa başlıklar
   gönderilmiş olur ve giriş yapmış kullanıcı sayfada görünmez. */
$hsO = null;
if (is_file(__DIR__ . '/k/hesap.php')) { require_once __DIR__ . '/k/hesap.php'; $hsO = hs_oturum(); }
$DATA = function_exists('tg_veri_dizini') ? tg_veri_dizini() : (getenv('KUTADGU_DATA') ?: realpath(__DIR__ . '/..') . '/kutadgu-data');
$KOK  = tg_kok();                                   /* isteğin geldiği alan adı */
$MARKA = (string)tg_ayar('marka', 'Kutadgu');
$TAMGA_AD  = (string)tg_ayar('tamga_ad', 'Tamga');
$TAMGA_YOL = (string)tg_ayar('tamga_yol', 'tamga');
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
/* Kaynakça biçimlendirici.
   Yazar kaynakçayı paragraf paragraf yazmamışsa (tek blok metin ya da
   yalnızca satır sonlarıyla ayrılmış) her kaynak kendi paragrafına
   alınır. Bunu yapmazsak kaynakça tek bir yığın hâlinde görünüyor,
   asılı girinti çalışmıyor ve DOI bağlama da devreye girmiyordu;
   çünkü bağlayıcı <p> blokları üzerinde çalışır.

   Var olan paragraflara dokunulmaz: iki ya da daha çok <p> varsa metin
   olduğu gibi bırakılır. */
function kaynakca_paragrafla(string $ham): string {
    $t = trim($ham);
    if ($t === '') return $t;
    if (preg_match_all('#<p[\s>]#i', $t) >= 2) return $t;

    $duz = str_ireplace(['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>'], "\n", $t);
    $duz = trim(strip_tags($duz));
    if ($duz === '') return $t;

    $satir = preg_split('/\R+/u', $duz) ?: [];
    $satir = array_values(array_filter(array_map('trim', $satir), fn($x) => $x !== ''));

    /* Tek satırda birden çok kaynak olabilir: bir kaynağın sonu
       ")." ya da "." ile biter ve yenisi büyük harfle başlar.
       Bölme yalnızca yıl parantezi görülen metinlerde denenir,
       yoksa düz bir paragraf yanlışlıkla parçalanır. */
    if (count($satir) === 1 && preg_match('/\((?:19|20)\d{2}[a-z]?\)/u', $satir[0])) {
        $parca = preg_split('/(?<=[.)])\s+(?=[A-ZÇĞİÖŞÜ][^\s]*,)/u', $satir[0]) ?: [];
        $parca = array_values(array_filter(array_map('trim', $parca), fn($x) => $x !== ''));
        if (count($parca) > 1) $satir = $parca;
    }
    if (count($satir) < 2) return $t;

    $out = '';
    foreach ($satir as $x) $out .= '<p>' . htmlspecialchars($x, ENT_QUOTES, 'UTF-8') . '</p>';
    return $out;
}

/* Kaynakçayı tıklanabilir yap: DOI varsa doi.org linkine çevir; DOI yoksa referansın sonuna kaynağa götüren bir bağlantı ekle */
function kaynakca_linkle(string $html, bool $L): string {
    if (trim($html) === '') return $html;
    $html = kaynakca_paragrafla($html);
    // 1) Metindeki doi.org URL'lerini tıklanabilir yap
    $html = preg_replace_callback('#https?://(?:dx\.)?doi\.org/(10\.[^\s<)"\']+)#i', function($m){
        $doi = rtrim($m[1], '.');
        return '<a href="https://doi.org/' . $doi . '" target="_blank" rel="noopener">https://doi.org/' . $doi . '</a>';
    }, $html);
    // 2) Her <p> referansını ele al
    $html = preg_replace_callback('#<p([^>]*)>(.*?)</p>#is', function($m) use ($L){
        $ic = $m[2];
        $duz = trim(preg_replace('/\s+/', ' ', strip_tags($ic)));
        if ($duz === '') return $m[0];
        if (stripos($ic, 'doi.org') !== false) return $m[0]; // DOI zaten tıklanabilir
        // çıplak DOI (10.xxxx/...) var mı?
        if (preg_match('#\b(10\.\d{4,9}/[^\s<)"\']+)#', $duz, $dm)) {
            $doi = rtrim($dm[1], '.');
            $lnk = ' <a href="https://doi.org/' . $doi . '" target="_blank" rel="noopener">https://doi.org/' . $doi . ' ↗</a>';
            return '<p' . $m[1] . '>' . $ic . $lnk . '</p>';
        }
        // DOI yok: kaynağa götüren arama bağlantısı
        $q = rawurlencode(mb_substr($duz, 0, 260, 'UTF-8'));
        $lbl = $L ? 'go to source ↗' : 'kaynağa git ↗';
        $lnk = ' <a class="kref rz rz-cizgi" href="https://scholar.google.com/scholar?q=' . $q . '" target="_blank" rel="noopener">' . $lbl . '</a>';
        return '<p' . $m[1] . '>' . $ic . $lnk . '</p>';
    }, $html);
    return $html;
}

$slug = isset($_GET['y']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$_GET['y'])) : '';
$doiAra = isset($_GET['doi']) ? preg_replace('/[^a-z0-9.\-]/', '', strtolower((string)$_GET['doi'])) : '';
/* Dil, sitenin geri kalanıyla aynı kurala göre belirlenir:
   adres > çerez > ülke (Türkiye'den Türkçe, dışından İngilizce) > tarayıcı */
require_once __DIR__ . '/k/kabuk.php';
require_once __DIR__ . '/k/duzenleyici.php';
$lang = k_dil();

$yazilar = [];
$dosya = $DATA . '/yazilar.json';
if (is_file($dosya)) { $j = json_decode((string)file_get_contents($dosya), true); if (is_array($j)) $yazilar = $j; }
$yazi = null;
foreach ($yazilar as $yy) {
    if ($doiAra !== '' && tg_kod_esles($yy, $doiAra)) { $yazi = $yy; break; }
    if ($doiAra === '' && (($yy['slug'] ?? '') === $slug || ($yy['id'] ?? '') === $slug)) { $yazi = $yy; break; }
}

/* TAMGA HER ZAMAN ÖZGÜN DİLDEKİ KAYDA AÇAR.
   Tamga bu çalışmanın kalıcı kimliğidir ve kimlik, çevirinin değil
   kaydın kimliğidir. Okur tamgayla gelmiş ve bir dil belirtmemişse,
   sayfa metnin yazıldığı dildeki hâlini gösterir. Dil elle seçilmişse
   ona dokunulmaz: kimse okuduğu dile zorlanmaz, yalnızca varsayılan
   değişir.

   Arayüzün kırk iki dili yoktur; olan iki dili vardır. Bu yüzden kural
   şudur: çalışma İngilizce yazıldıysa arayüz İngilizce açılır, başka
   her dilde Türkçe açılır, çünkü İngilizce arayüz çalışmanın İngilizce
   çevirisi varsa onu öne alır ve o zaman okur kaydı değil çeviriyi
   okumuş olur. */
if ($yazi && $doiAra !== '' && !isset($_GET['lang'])) {
    $ozgunKod = tg_yazi_dili($yazi);
    if ($ozgunKod !== '') $lang = ($ozgunKod === 'en') ? 'en' : 'tr';
}

/* Kalıcı adrese topla: /yazi.php?y=... ile gelen istek, çalışmanın Tamga
   adresine kalıcı olarak (301) yönlendirilir. Böylece paylaşılan eski
   bağlantılar çalışmayı sürdürür, arama motorları tek adreste toplanır ve
   başlık değişse bile adres bozulmaz. /tamga/... ile gelen istekte
   yönlendirme yapılmaz; sayfa doğrudan oradan üretilir. */
if ($yazi && $doiAra === '' && trim((string)($yazi['bcid'] ?? '')) !== '') {
    $hedef = tg_yazi_adres($yazi);
    if (isset($_GET['lang'])) {
        $hedef .= '?lang=' . rawurlencode($lang);
    } else {
        /* TAMGA HER ZAMAN ÖZGÜN DİLDEKİ KAYDA GÖTÜRÜR.
           Tamga bu çalışmanın kalıcı kimliğidir ve kimlik, çevirinin
           değil kaydın kimliğidir. Okur bir dil belirtmemişse, metnin
           yazıldığı dildeki hâline indirilir. Çeviri okunabilir, ama
           kimliğin işaret ettiği şey odur diye değil, ona ek olarak. */
        $ozgun = tg_yazi_dili($yazi);
        $hedef .= '?lang=' . ($ozgun === 'en' ? 'en' : 'tr');
    }
    foreach (['ham', 'surec'] as $ek) {
        if (isset($_GET[$ek]) && (string)$_GET[$ek] === '1') {
            $hedef .= (strpos($hedef, '?') === false ? '?' : '&') . $ek . '=1';
        }
    }
    header('Location: ' . $hedef, true, 301);
    exit;
}

/* Kimlikle gelen istek kalıcı adresten gelmiyorsa (örneğin sistemin ilk
   döneminde kullanılan, DOI'ye benzeyen eski çözümleyici adresi) yine
   kalıcı adrese 301 ile toplanır. Eski bağlantı kırılmaz, ama arama
   motorlarında tek bir adres kalır ve DOI'ye benzeyen adres yayılmaz.
   /tamga/... ile gelen istekte yönlendirme yapılmaz; döngü oluşmaz. */
if ($yazi && $doiAra !== '' && trim((string)($yazi['bcid'] ?? '')) !== '') {
    $simdiki  = strtolower(rtrim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH), '/'));
    $kanonik  = strtolower(rtrim(tg_yazi_yolu($yazi), '/'));
    if ($simdiki !== '' && $kanonik !== '' && $simdiki !== $kanonik) {
        $hedef = tg_yazi_adres($yazi);
        if (isset($_GET['lang'])) $hedef .= '?lang=' . rawurlencode($lang);
        foreach (['ham', 'surec'] as $ek) {
            if (isset($_GET[$ek]) && (string)$_GET[$ek] === '1') {
                $hedef .= (strpos($hedef, '?') === false ? '?' : '&') . $ek . '=1';
            }
        }
        header('Location: ' . $hedef, true, 301);
        header('Cache-Control: public, max-age=86400');
        exit;
    }
}

if (!$yazi) {
    http_response_code(404);
    $L4 = (strpos(strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')), 'tr') === false);
    echo '<!DOCTYPE html><html lang="' . ($L4 ? 'en' : 'tr') . '"><head><meta charset="utf-8">'
       . '<title>' . ($L4 ? 'Not found' : 'Bulunamadı') . '</title>'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<link rel="stylesheet" href="/k/kutadgu.css?v=' . TG_SURUM . '"></head><body>'
       . '<div class="kap bolum metin-orta">'
       . '<h1>' . ($L4 ? 'This work was not found' : 'Çalışma bulunamadı') . '</h1>'
       . '<p class="metin-sonuk">' . ($L4 ? 'The address may have changed or the work may have been removed.' : 'Adres değişmiş ya da çalışma kaldırılmış olabilir.') . '</p>'
       . '<p style="margin-top:var(--b-5)"><a class="d d-vurgu" href="/yazilar.php">' . ($L4 ? 'All works' : 'Bütün çalışmalar') . '</a></p>'
       . '</div><script src="/k/kutadgu.js?v=' . TG_SURUM . '" defer></script></body></html>';
    exit;
}

/* Çeviri alanları TEK KAYNAKTAN okunur (ortak.php, tg_yazi_ceviri).
   Eskiden burada doğrudan $y['baslik_en'] okunuyordu; kayıt yeni
   biçimde ('ceviriler') tutulduğunda bu satır çeviriyi göremez ve
   sayfa sessizce özgün metne düşerdi. */
function alan($y, $k, $lang){
    if ($lang !== '' && $lang !== tg_yazi_dili($y)) {
        $c = tg_yazi_ceviri($y, $lang);
        if ($c) {
            /* 'anahtar' ve 'kaynakca' çeviri kaydında tutulmaz; onlar
               için özgün alan doğrudur. */
            $v = trim(tg_metin($c[$k] ?? ''));
            if ($v !== '') return $v;
        }
    }
    return tg_metin($y[$k] ?? '');
}

/* Okunma sayacı: botları atla, kilitli yaz, IP hash'i ile tekil say; toplamı döndür */
$okuSay = (function() use ($yazi, $DATA) {
    $id = (string)($yazi['id'] ?? ($yazi['slug'] ?? ''));
    $ua = strtolower((string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    $bot = ($ua === '' || preg_match('/bot|crawl|spider|slurp|bing|google|yandex|duckduck|baidu|facebookexternal|preview|monitor|curl|wget|python|headless|lighthouse|pingdom|uptime/', $ua));
    $f = $DATA . '/yazi-oku.json';
    if ($id === '') return ['toplam' => 0, 'tekil' => 0];
    $fp = @fopen($f, 'c+');
    if (!$fp) return ['toplam' => 0, 'tekil' => 0];
    $son = ['toplam' => 0, 'tekil' => 0];
    if (flock($fp, LOCK_EX)) {
        $raw = stream_get_contents($fp);
        $d = json_decode($raw ?: '[]', true); if (!is_array($d)) $d = [];
        if (!isset($d[$id]) || !is_array($d[$id])) $d[$id] = ['toplam' => 0, 'tekil' => []];
        if (!$bot) {
            $d[$id]['toplam'] = (int)($d[$id]['toplam'] ?? 0) + 1;
            $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
            $iph = substr(hash('sha256', $ip . '|oku'), 0, 16);
            if (!is_array($d[$id]['tekil'] ?? null)) $d[$id]['tekil'] = [];
            if (!in_array($iph, $d[$id]['tekil'], true)) {
                $d[$id]['tekil'][] = $iph;
                if (count($d[$id]['tekil']) > 8000) array_shift($d[$id]['tekil']);
            }
            ftruncate($fp, 0); rewind($fp);
            fwrite($fp, json_encode($d, JSON_UNESCAPED_UNICODE));
            fflush($fp);
        }
        $son = ['toplam' => (int)($d[$id]['toplam'] ?? 0), 'tekil' => count($d[$id]['tekil'] ?? [])];
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $son;
})();
/* Görüntülenen dildeki çeviri kaydı ve onun YAYIN olup olmadığı.
   Bu iki değer sayfanın üç ayrı yerinde kullanılıyor: uyarı şeridi,
   canonical ve hreflang. Üçü de aynı yerden okunsun diye burada bir
   kez çözülür. */
$ceviriler = tg_yazi_ceviriler($yazi);
$cGoster   = ($lang !== '' && $lang !== tg_yazi_dili($yazi)) ? ($ceviriler[$lang] ?? []) : [];
$cYayin    = tg_ceviri_yayin_mi($cGoster);
$enVar     = isset($ceviriler['en']);
$enYayin   = $enVar && tg_ceviri_yayin_mi($ceviriler['en']);
$baslik  = alan($yazi, 'baslik', $lang);
$ozet    = alan($yazi, 'ozet', $lang);
$anahtar = alan($yazi, 'anahtar', $lang);
$metin   = alan($yazi, 'metin', $lang);
$kaynakca= alan($yazi, 'kaynakca', $lang);
$yazar   = (string)($yazi['yazar'] ?? tg_ayar('varsayilan_yazar', ''));
$tarih   = (string)($yazi['tarih'] ?? '');
$yil     = substr($tarih, 0, 4);
$tur     = (string)($yazi['tur'] ?? 'yazi');
$yayin   = (string)($yazi['yayin'] ?? '');
$doi     = (string)($yazi['doi'] ?? '');
$hakemler= is_array($yazi['hakemler'] ?? null) ? $yazi['hakemler'] : [];
/* Etik kurul onayı: gerekli olduğu beyan edilmiş ama onaylanmamışsa çalışma askıya alınır.
   Erişim kapatılmaz; okuyucu eksiği görerek okur. Belge sunulup onaylanınca uyarı kalkar. */
$etik = is_array($yazi['etik'] ?? null) ? $yazi['etik'] : [];
/* ETİK BEYANI: ÜÇ METİN.
   15 Ağustos 2026 kurul kararı, etik kurul iznini DOSYA yerine BEYAN
   olarak istiyor: kurul adı + karar tarihi + karar numarası. Gerekçe
   basvuru.php'de yazılı.

   ASKI KURALI DA BUNA GÖRE DEĞİŞTİ. Eski kural "gerekli dendi ama onay
   yok" idi ve 'onay' bayrağını kaldıracak hiçbir yol sistemde YOKTU:
   izin gerektiren her çalışma sonsuza kadar askıda kalıyordu ve sayfa
   "yazar belgeyi yüklediğinde uyarı kaldırılır" diyerek olmayan bir
   yolu duyuruyordu. Yeni kural ölçülebilir: beyan verilmişse askı
   yoktur, verilmemişse vardır. Beyanın DOĞRULUĞU sistemin değil, veren
   kurulun bileceği şeydir; sistem beyanı yayımlar ki sorulabilsin. */
/* HÂL ARTIK TEK KAYNAKTAN: tg_etik_hal() (ortak.php). Buradaki üç
   satır, sayfanın kendi kuralını yazmasıydı; kural iki yerde
   yazıldığında biri ötekinden habersiz değişir. Beş hâl ve renkleri
   orada tanımlıdır; bu sayfa yalnız basar. */
$etikBeyan  = tg_etik_beyanli($etik);
$etikHal    = tg_etik_hal($yazi);
$etikAskida = ($etikHal === 'askida') && empty($etik['onay']);
/* Beyan yayımdan sonra eklendiyse bu da yazılır: geç yapılmış bir
   beyanı zamanında yapılmış gibi göstermek, beyanın kendisini
   değersizleştirir. */
$etikSonra  = trim((string)($etik['beyan_tarihi'] ?? ''));
$slugU   = (string)($yazi['slug'] ?? $yazi['id']);
/* Bu çalışmanın kalıcı adresi: bütün bağlantılar ve atıflar bunu kullanır */
$benYol  = tg_yazi_yolu($yazi);
$benSep  = strpos($benYol, '?') === false ? '?' : '&';
$url     = $KOK . $benYol;

/* Yazar adını parçala: unvanları at, soyadı ayır */
$temizAd = trim(preg_replace('/\b(Dr\.?|Prof\.?|Doç\.?|Doc\.?|Öğr\.?|Gör\.?)\s*/iu', '', $yazar));
$parcalar = preg_split('/\s+/', $temizAd);
$soyad = array_pop($parcalar);
$adlar = $parcalar;
$adTam = trim(implode(' ', $adlar));
$basHarf = trim(implode(' ', array_map(function($a){ return mb_substr($a, 0, 1, 'UTF-8') . '.'; }, $adlar)));
$baslikDuz = trim(strip_tags($baslik));
$L = ($lang === 'en');
$metinHam = alan($yazi, 'metin_ham', $lang);
$hamVar = trim(strip_tags($metinHam)) !== '';
$ham = (isset($_GET['ham']) && (string)$_GET['ham'] === '1' && $hamVar);
if ($ham) $metin = $metinHam;
$surec = (isset($_GET['surec']) && (string)$_GET['surec'] === '1');
$kararAd = ['kabul'=>['Kabul','Accepted'],'kucuk'=>['Küçük revizyonla kabul','Minor revision'],'buyuk'=>['Büyük revizyon','Major revision'],'ret'=>['Ret','Rejected']];
/* Karar rengi bir veri değil biçimdir: dizgenin belirteçlerinden gelir. */
$kararRenk = ['kabul'=>'var(--yesil)','kucuk'=>'var(--kut)','buyuk'=>'var(--kut)','ret'=>'var(--kirmizi)'];
$hakemDolu = array_values(array_filter($hakemler, function($h){ return is_array($h) && trim((string)($h['rapor'] ?? '')) !== ''; }));
/* Onay kuralı, düzeltme/geri çekme kayıtları ve veri beyanı tek yerden okunur */
$onay       = tg_onay_durumu($yazi);
$kayitlar   = tg_kayitlar($yazi);
$geriCekildi= tg_geri_cekildi($yazi);
/* Hakemlik aşaması. $tur çalışmanın hangi YOLDA olduğunu söyler, o yolun
   neresinde olduğunu değil: yazar hakemliğe açtığı anda tur='hakemli'
   olur, tek rapor gelmemiştir. Sayfadaki her rozet, bant ve paylaşım
   metni bu tek değerden beslenir ki hepsi aynı şeyi söylesin. */
$asama      = tg_hakem_asamasi($yazi);
/* YOLDA MI, YOLU GÖRDÜ MÜ. İki ret alan çalışmanın 'tur' alanı 'yazi'
   olur (ret_donusum) ve bu sayfa, iki hakemin adıyla reddettiği bir
   metnin başında "hakem değerlendirmesinden geçmemiştir" yazıp
   raporları hiç basmıyordu. Kural tek kaynaktan okunur: süreçle ilgili
   olan yerler yolda mı diye, kayıtla ilgili olan yerler yolu gördü mü
   diye sorar. Gerekçe ortak.php'de yazılı. */
$hakemYolda   = tg_hakem_yolunda($yazi);
$hakemGormus  = tg_hakem_yolu_gormus($yazi);
$hakemDustu   = tg_hakemlikten_dusmus($yazi);
$retDoldu     = tg_ret_esigi_doldu($yazi);
$veri       = is_array($yazi['veri'] ?? null) ? $yazi['veri'] : [];
/* Hakemin bütün sistemdeki karar dağılımı: her şeye onay veren hakem görünür olsun */
$hakemDagilim = [];
foreach ($yazilar as $wz) {
    foreach (tg_dizi($wz['hakemler'] ?? null) as $wh) {
        if (!is_array($wh) || trim((string)($wh['rapor'] ?? '')) === '') continue;
        $ak = tg_ad_anahtar((string)($wh['ad'] ?? '')); if ($ak === '') continue;
        $kk = (string)($wh['karar'] ?? ''); if ($kk === '') continue;
        if (!isset($hakemDagilim[$ak])) $hakemDagilim[$ak] = ['kabul'=>0,'kucuk'=>0,'buyuk'=>0,'ret'=>0,'toplam'=>0];
        if (isset($hakemDagilim[$ak][$kk])) $hakemDagilim[$ak][$kk]++;
        $hakemDagilim[$ak]['toplam']++;
    }
}
$retVar = false; $kabulSay = 0; $retSay = 0;
foreach ($hakemDolu as $h) { $kk = (string)($h['karar'] ?? ''); if ($kk === 'ret') { $retVar = true; $retSay++; } if ($kk === 'kabul') $kabulSay++; }
/* Popup için hakem rapor sürümleri (en yeni önce; en yeni = güncel/yayında) */
$hakemModal = [];
foreach ($hakemDolu as $h) {
    $vs = (is_array($h['raporlar'] ?? null) && $h['raporlar']) ? $h['raporlar']
        : [['karar' => $h['karar'] ?? '', 'rapor' => $h['rapor'] ?? '', 'tarih' => $h['tarih'] ?? '', 'dosya' => $h['dosya'] ?? '']];
    $vlist = [];
    foreach ($vs as $v) {
        if (!is_array($v) || trim((string)($v['rapor'] ?? '')) === '') continue;
        $kk = (string)($v['karar'] ?? '');
        $ts = (string)($v['tarih'] ?? '');
        $vlist[] = [
            'karar' => $L ? ($kararAd[$kk][1] ?? $kk) : ($kararAd[$kk][0] ?? $kk),
            'renk'  => $kararRenk[$kk] ?? 'var(--yesil)',
            'rapor' => tg_zengin((string)($v['rapor'] ?? '')),
            'tarih' => $ts !== '' ? date('Y-m-d H:i', strtotime($ts) ?: time()) : '',
            'dosya' => (string)($v['dosya'] ?? ''),
            'endeks' => is_array($v['endeks'] ?? null) ? array_values(array_map('strval', $v['endeks'])) : [],
            'notlar' => is_array($v['notlar'] ?? null) ? array_values(array_filter(array_map(fn($n) => is_array($n) ? ['alinti' => (string)($n['alinti'] ?? ''), 'not' => (string)($n['not'] ?? '')] : null, $v['notlar']))) : [],
        ];
    }
    $pf = is_array($h['profil'] ?? null) ? $h['profil'] : [];
    $nk = tg_rapor_nitelik($h);
    $dg = $hakemDagilim[tg_ad_anahtar((string)($h['ad'] ?? ''))] ?? null;
    $hakemModal[] = ['ad' => (string)($h['ad'] ?? ''), 'surumler' => array_reverse($vlist), 'atayan' => tg_atayan_metin($h['atayan'] ?? null, $L),
      'nitelik' => $nk['yeterli'], 'eksik' => array_map(fn($e) => tg_nitelik_metin($e, $L), $nk['eksik']),
      'dagilim' => $dg && $dg['toplam'] > 1 ? ($L
          ? ($dg['toplam'] . ' reports in the system: ' . $dg['kabul'] . ' accept, ' . $dg['kucuk'] . ' minor, ' . $dg['buyuk'] . ' major, ' . $dg['ret'] . ' reject')
          : ($dg['toplam'] . ' rapor: ' . $dg['kabul'] . ' kabul, ' . $dg['kucuk'] . ' küçük, ' . $dg['buyuk'] . ' büyük, ' . $dg['ret'] . ' ret')) : '',
      'profil' => [
        'unvan' => bh_titrle((string)($pf['unvan'] ?? '')),
        'kurum' => bh_titrle((string)($pf['kurum'] ?? '')),
        'orcid' => (string)($pf['orcid'] ?? ''),
        'eposta' => (string)($pf['eposta'] ?? ''),
        'web' => (string)($pf['web'] ?? ''),
    ]];
}

/* DOI benzeri kimlik (kutadgu.net/10.00001/bc.000001) + yazar bağlantısı */
$bcid  = (string)($yazi['bcid'] ?? '');
$nekiTam = $bcid !== '' ? $TAMGA_YOL . '/' . $bcid : '';
$nekiUrl = $bcid !== '' ? $KOK . '/' . $TAMGA_YOL . '/' . $bcid : '';
/* Atıf kimliği. DOI alanına DOI olmayan bir şey yazılmaz: "doi" alanı
   atıf yazılımlarında doi.org üzerinden çözümlenmeye çalışılır ve tamga
   orada çözümlenmez. Gerçek bir DOI varsa doi alanına o yazılır; yoksa
   alan hiç yazılmaz, tamga kendi adıyla nota geçer. */
$doiTam = $doi;
$doiUrl = $doi !== '' ? 'https://doi.org/' . $doi : $nekiUrl;
$atifUrl = $doiUrl !== '' ? $doiUrl : $url;
$yazarUrl = $KOK . '/yazilar.php?yazar=' . rawurlencode($yazar);

/* Türkçe uyumlu ilk-harf büyütme (yazar küçük harfle yazsa da düzeltir) */
function bh_titrle($s) {
    $s = trim((string)$s); if ($s === '') return '';
    $up = ['i' => 'İ', 'ı' => 'I', 'ş' => 'Ş', 'ğ' => 'Ğ', 'ü' => 'Ü', 'ö' => 'Ö', 'ç' => 'Ç'];
    $out = [];
    foreach (preg_split('/\s+/u', $s) as $w) {
        if ($w === '') continue;
        $f = mb_substr($w, 0, 1, 'UTF-8'); $r = mb_substr($w, 1, null, 'UTF-8');
        $fl = mb_strtolower($f, 'UTF-8');
        $out[] = ($up[$fl] ?? mb_strtoupper($f, 'UTF-8')) . $r;
    }
    return implode(' ', $out);
}
/* Unvan + ad birleştir (ad zaten unvan içeriyorsa tekrarlama) */
function bh_unvanli($unvan, $ad) {
    /* Unvan anahtarla saklanır; sayfanın dilinde karşılığı yazılır. */
    $unvan = tg_unvan_ad(trim((string)$unvan)); $ad = trim((string)$ad);
    if ($unvan === '') return $ad;
    if (preg_match('/^\s*(prof|do[çc]|dr|öğr|ogr)\.?/iu', $ad)) return $ad;
    return $unvan . ' ' . $ad;
}
/* Yazar profil bilgisi (yazar doldurur; ScienceDirect tarzı popup) */
$yb = is_array($yazi['yazar_bilgi'] ?? null) ? $yazi['yazar_bilgi'] : [];
$yazarBilgi = [
    'ad'     => bh_titrle(($yb['ad'] ?? '') !== '' ? $yb['ad'] : $yazar),
    'unvan'  => trim((string)($yb['unvan'] ?? '')),
    'kurum'  => bh_titrle($yb['kurum'] ?? ''),
    'eposta' => trim((string)($yb['eposta'] ?? '')),
    'orcid'  => trim((string)($yb['orcid'] ?? '')),
    'scopus' => trim((string)($yb['scopus'] ?? '')),
    'web'    => trim((string)($yb['web'] ?? '')),
    'digerUrl' => $yazarUrl,
];
/* Çok yazarlı gösterim: ilk yazar iletişim yazarıdır (tam profil), diğerleri ad/unvan/kurum */
$ylRaw = is_array($yazi['yazar_liste'] ?? null) ? $yazi['yazar_liste'] : [];
$yazarListe = [];
/* Liste birinci yazarı içeriyor mu? İçermiyorsa iletişim yazarı başa eklenir. */
$ilkAdi = mb_strtolower(trim((string)(($yb['ad'] ?? '') !== '' ? $yb['ad'] : $temizAd)), 'UTF-8');
$listedeVar = false;
foreach ($ylRaw as $ya) {
    if (is_array($ya) && mb_strtolower(trim((string)($ya['ad'] ?? '')), 'UTF-8') === $ilkAdi) { $listedeVar = true; break; }
}
if ($ylRaw && !$listedeVar) $yazarListe[] = $yazarBilgi;
foreach ($ylRaw as $ix => $ya) {
    if (!is_array($ya)) continue;
    $adx = bh_titrle((string)($ya['ad'] ?? ''));
    if ($adx === '') continue;
    if (!$yazarListe && ($yb['ad'] ?? '') !== '') {
        // ilk (iletişim) yazar: başvuru profilini kullan, listedeki unvan/kurumla tamamla
        $yazarListe[] = array_merge($yazarBilgi, [
            'unvan' => trim((string)($ya['unvan'] ?? '')) !== '' ? trim((string)$ya['unvan']) : $yazarBilgi['unvan'],
            'kurum' => bh_titrle((string)($ya['kurum'] ?? '')) !== '' ? bh_titrle((string)$ya['kurum']) : $yazarBilgi['kurum'],
            'kisiUrl' => tg_kisi_yolu($adx),
        ]);
    } else {
        $yazarListe[] = ['ad' => $adx, 'unvan' => trim((string)($ya['unvan'] ?? '')), 'kurum' => bh_titrle((string)($ya['kurum'] ?? '')),
            'eposta' => '', 'orcid' => trim((string)($ya['orcid'] ?? '')), 'scopus' => trim((string)($ya['scopus'] ?? '')), 'web' => '',
            'digerUrl' => $KOK . '/yazilar.php?yazar=' . rawurlencode($adx),
            'kisiUrl'  => tg_kisi_yolu($adx)];
    }
}
if (!$yazarListe) $yazarListe = [$yazarBilgi];
$cokYazar = count($yazarListe) > 1;

/* Atıf biçimleri.

   APA künyesi artık BURADA KURULMUYOR: tg_kunye() üretiyor. Sebebi,
   aynı dizenin yapılandırılmış veride (schema.org creditText) de
   geçmesi ve ikisinin ayrı yerlerde yazılırsa zamanla ayrışması.
   Öteki üç biçim sayfaya özgü kaldı; onların makinede karşılığı yok. */
$apa = tg_kunye($yazi, $L);
$mla = $soyad . ', ' . $adTam . '. "' . $baslikDuz . '." ' . ($yayin !== '' ? $yayin . ', ' : $MARKA . ', ') . ($yil ?: '') . ', ' . $atifUrl . '.';
$chicago = $soyad . ', ' . $adTam . '. "' . $baslikDuz . '." ' . ($yayin !== '' ? $yayin . '. ' : $MARKA . '. ') . ($yil ?: '') . '. ' . $atifUrl . '.';
$bibKey = strtolower(preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($soyad, 'UTF-8'), ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u']))) . ($yil ?: '');
/* Geri çekilmiş çalışma: durum yalnızca sayfada değil, makine tarafından okunabilen
   üst verilerde de yer alır; arama motorları sayfadaki uyarı bandını her zaman okumaz. */
$basMeta = $geriCekildi ? (($L ? '[RETRACTED] ' : '[GERİ ÇEKİLDİ] ') . $baslikDuz) : $baslikDuz;
/* Sosyal mecra kartı: her ölçü aynı kaynaktan üretilir */
$kartAnah = $bcid !== '' ? ('k=' . rawurlencode($bcid)) : ('y=' . rawurlencode($slugU));
$kartUrl  = $KOK . '/kart.php?' . $kartAnah . '&olcu=yatay' . ($L ? '&lang=en' : '');
$kartKare = $KOK . '/kart.php?' . $kartAnah . '&olcu=kare'  . ($L ? '&lang=en' : '');
$kartHik  = $KOK . '/kart.php?' . $kartAnah . '&olcu=hikaye' . ($L ? '&lang=en' : '');
/* Sayfada GÖRÜNEN önizleme aynı alan adından istenir. Mutlak adres,
   sistem başka bir alan adından açıldığında (ya da 'kok' boşken)
   görseli hâlâ kutadgu.net'ten ister; bu hem gereksiz bir dış bağlantı
   açar hem de çevrimdışı önbelleği atlar. Paylaşım ve indirme
   adresleri mutlak kalır, çünkü onlar sayfanın dışına çıkar. */
$kartYerel = '/kart.php?' . $kartAnah . '&olcu=yatay' . ($L ? '&lang=en' : '');
/* Paylaşım metinleri için yazar dizesi (JS'e gömülür) */
$payYazarAd = [];
foreach ($yazarListe as $pa) { $n = trim((string)($pa['ad'] ?? '')); if ($n !== '') $payYazarAd[] = $n; }
$payYazarJs = json_encode(implode(', ', $payYazarAd), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
$bibNot = ($yayin !== '' ? $yayin : $MARKA) . ($bcid !== '' ? '. ' . $TAMGA_AD . ': ' . $bcid : '');
$bibtex = "@misc{" . $bibKey . ",\n"
        . "  author = {" . $soyad . ", " . $adTam . "},\n"
        . "  title  = {" . $baslikDuz . "},\n"
        . "  year   = {" . ($yil ?: '') . "},\n"
        . ($doiTam !== '' ? "  doi    = {" . $doiTam . "},\n" : '')
        . "  url    = {" . $atifUrl . "},\n"
        . "  note   = {" . $bibNot . "}\n}";
?><!DOCTYPE html>
<html lang="<?= $lang ?>" data-olcu="okuma">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($basMeta) ?> · <?= esc($MARKA) ?></title>
<meta name="description" content="<?= esc(mb_substr(trim(strip_tags($ozet)), 0, 180)) ?>">
<link rel="icon" type="image/svg+xml" href="/k/tamga-kucuk.svg">
<?php /* =================================================================
   ÇEVİRİ YAYIN MI, OKUMA YARDIMI MI: CANONICAL BUNA GÖRE KURULUR
   -----------------------------------------------------------------
   Eskiden her dil kendi canonical'ini alıyordu ve $enVar varsa tr/en
   hreflang yazılıyordu. Bu, çevirinin NASIL üretildiğini hiç
   sormuyordu: yazarın kendi yazdığı İngilizce metinle onaysız bir
   makine çevirisi arama motoruna aynı şeyi söylüyordu.

   Kural artık kayıttan gelir (ortak.php, tg_ceviri_yayin_mi):
     yayın        -> kendi canonical'i, hreflang kümesine girer
     okuma yardımı-> canonical kayıt diline gider, kümeye girmez

   Gerekçe, arayüz dilleri için dün konan kuralın aynısıdır, bir kat
   aşağıda: denetlenmemiş bir makine çevirisini yayın diye bildirmek
   hem doğru değil hem de arşivin tek sermayesi olan güveni harcar. */
$ozgunDil = tg_yazi_dili($yazi); if ($ozgunDil === '') $ozgunDil = 'tr';
$kanonik  = ($lang === $ozgunDil || $cYayin) ? ($url . '&lang=' . $lang) : ($url . '&lang=' . $ozgunDil);
?>
<link rel="canonical" href="<?= esc($kanonik) ?>">
<?php /* GERİ ÇEKİLEN ÇALIŞMA DİZİNLENMEZ.
         ÖLÇÜLEN KUSUR: kurul toplantısında "yazar yayınını çekemez ama
         çalışma bütün arama motorlarından silinir" denmişti; oysa bu
         satır koşulsuzdu ve geri çekilmiş bir çalışma da "index, follow"
         diye bildiriliyordu. Yani sistem, uygulamadığı bir kuralı
         duyuruyordu — bu oturumda beşinci kez aynı kalıp.

         'follow' KORUNUR, 'index' düşer: bağlantılar çözülmeyi
         sürdürmeli, çünkü çekilen kayıt silinmez ve Tamga'sı kalıcıdır;
         yalnızca aranan sonuçlar arasında görünmemesi istenir. Kaydın
         kendisi yerinde durur ve doğrudan adresinden okunabilir. */ ?>
<meta name="robots" content="<?= $geriCekildi ? 'noindex, follow' : 'index, follow' ?>">
<?php /* hreflang kümesine yalnız YAYIN sayılan diller girer ve her
         üyesi kendi canonical'idir; biri kendini başkasına devrederse
         arama motoru kümenin tamamını düşürür. */
$kume = [$ozgunDil];
foreach ($ceviriler as $dk => $c) { if (tg_ceviri_yayin_mi($c)) $kume[] = $dk; }
$kume = array_values(array_unique($kume));
if (count($kume) > 1): foreach ($kume as $dk): ?>
<link rel="alternate" hreflang="<?= esc($dk) ?>" href="<?= esc($url . '&lang=' . $dk) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= esc($url . '&lang=' . $ozgunDil) ?>">
<?php endif; ?>
<?php if ($geriCekildi): ?>
<meta name="kutadgu_durum" content="geri_cekildi">
<meta name="dc.description.status" content="retracted">
<?php endif; ?>
<!-- Google Scholar (Highwire Press) ve Dublin Core: tek kaynaktan üretilir -->
<?= sq_scholar($yazi, $url, $L, $geriCekildi) ?>

<!-- Open Graph ve Twitter -->
<meta property="og:type" content="article">
<meta property="og:site_name" content="<?= esc($MARKA) ?>">
<meta property="og:title" content="<?= esc($basMeta) ?>">
<meta property="og:description" content="<?= esc(mb_substr(trim(strip_tags($ozet)), 0, 180)) ?>">
<meta property="og:url" content="<?= esc($url) ?>">
<meta property="og:locale" content="<?= $L ? 'en_US' : 'tr_TR' ?>">
<meta property="og:image" content="<?= esc($kartUrl) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= esc($baslikDuz) ?>">
<meta name="twitter:image" content="<?= esc($kartUrl) ?>">
<meta property="article:published_time" content="<?= esc(substr($tarih, 0, 10)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= esc($basMeta) ?>">
<meta name="twitter:description" content="<?= esc(mb_substr(trim(strip_tags($ozet)), 0, 180)) ?>">

<!-- Yapılandırılmış veri -->
<?= sq_yazi($yazi, $url, $L, $geriCekildi) ?>

<link rel="apple-touch-icon" href="/k/tamga-180.png">
<link rel="preload" href="/k/yazitipi/kutadgu-serif-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="/k/kutadgu.css?v=<?= TG_SURUM ?>">
<link rel="manifest" href="/manifest.webmanifest">
<meta name="theme-color" content="#1b2a4a">
<meta name="color-scheme" content="light dark">
<script>(function(){try{var t=localStorage.getItem('kutadgu-tema');
if(!t)t=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'koyu':'acik';
document.documentElement.setAttribute('data-tema',t);}catch(e){}})();</script>
<style>
  /* Bu sayfa okuma sayfasıdır. Renk, düğme, form, kart, rozet ve
     çizelge dizgeden gelir (k/kutadgu.css); burada yalnızca okuma
     düzenine ve bu sayfaya özgü bölümlere ait kurallar durur.

     Sayfanın ölçüsü bilerek dizgenin tek düzeninden ayrıdır: solda
     içindekiler, ortada metin, sağda künye. Metin sütununun genişliği
     ve okuma puntosu (--okuf) bu sayfanın kendi kararıdır. */

  /* ---- Okuma kabı ----
     Gövde yüzü serif ve punto okurun ayarına bağlıdır. Kural body'ye
     değil kaba yazılır: üst çubuk ve altbilgi her sayfada olduğu gibi
     arayüz yüzünde kalsın, yalnızca okunan metin serif olsun. */
  .sar{position:relative;font-family:var(--serif);font-size:var(--okuf,18px);
    line-height:1.72;padding-block:clamp(24px,3vw,40px) var(--b-8)}
  /* ---- ARKADAKİ SİLÜET KALDIRILDI ----
     Bildirilen kusur (15 Ağustos 2026): "sayfa kayıyor, sabit değil" ve
     "arkadaki silüeti çıkarmak mı lazım bilemedim".

     İkisi AYNI ŞEYDİ. Buradaki tamga filigranı position:fixed idi:
     ekranda duruyor, metin üstünden akıyordu. Sabit bir zeminin üstünde
     kayan metin, gözde "sayfa kayıyor" izlenimi üretir — kayan şey
     metindir ama arkadaki şey kaymadığı için hareket yanlış yere
     atfedilir.

     İkinci gerekçe kontrasttır: %3,5 saydamlıkta bile gövde metninin
     ALTINDA duran bir desen, okuma sütununun kontrast payını düşürür.
     Bu sistem her renk kararını WCAG'a göre ölçüyor; ölçtüğü şeyin
     altına ölçmediği bir desen koymak tutarsızlıktır.

     Tamga kaybolmadı: sağ rayda mühür olarak, künyede kod olarak ve
     PDF kapağında duruyor. Kalkan şey, okunan metnin arkasındaki
     kopyasıdır. */
  @media(min-width:900px){ .sar{font-size:var(--okuf,19px)} }
  @media(max-width:560px){ .sar{font-size:var(--okuf,17px)} .sar::before{background-size:74vw} }
  /* ---- GENİŞ EKRANDA PUNTO BÜYÜR, SÜTUN ONUNLA BİRLİKTE ----
     KURUL BİLDİRİMİ — 20 Ağustos 2026: "makale hâlâ dar... daracık
     olmuş yine metin", örnek olarak Emerald makale sayfası verildi.

     ÖLÇÜM, ŞUNU SÖYLÜYOR: sütunun dar görünmesinin sebebi YER DEĞİL.
     1920'de kâğıdın iki yanında zaten 174'er piksel boşluk var; sütunu
     oradan genişletmek satırı bandın dışına çıkarır (130 karaktere
     kadar; bant aşağıda, ölçüsüyle birlikte yazılı). Bandı bozmadan sütunu büyütmenin tek yolu
     PUNTOYU büyütmektir: ölçü artık em ile yazıldığı için punto
     büyüdüğünde sütun da, kâğıt da birlikte büyür ve satırdaki
     karakter sayısı değişmez.

     19 punto, 1200 piksellik bir ekran için seçilmişti; 1920'de aynı
     punto hem küçük kalıyor hem de sütunu 665 pikselde tutuyordu.
     Merdiven ölçümle kuruldu (karakter sayısı her basamakta 74-75):

         1200 px -> 20 punto -> sütun 700 px
         1440 px -> 21 punto -> sütun 735 px
         1680 px -> 22 punto -> sütun 770 px
         1920 px -> 23 punto -> sütun 805 px

     Bu bir ÖNTANIMLIDIR, dayatma değil: A- ve A+ düğmeleri --okuf'u
     kendisi yazar ve bu merdivenin yerine geçer. */
  @media(min-width:1200px){ .sar{font-size:var(--okuf,20px)} }
  @media(min-width:1600px){ .sar{font-size:var(--okuf,21px)} }

  /* ---- Üç sütun: sol içindekiler, orta metin, sağ künye ----
     Izgara ortalanır; metin sütunu okunur genişlikte kalır, artan yer
     iki yana dağılır. İkinci eşik 1600'dedir: 1500'de üç sütun
     kurulunca metne 606 piksel kalıyordu. */
  /* ---- OKUMA ÖLÇÜSÜ PİKSELLE DEĞİL EM İLE ----
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Sınır 670 PİKSELDİ ve gövde
     puntosu ekran genişliğiyle değişiyor (19 / 18 / 17). Sabit piksel,
     punto küçüldükçe daha ÇOK karakter demektir:

         19 punto · 670px -> 70 karakter
         18 punto · 670px -> 78 KARAKTER   (bandın dışı)

     Yani ölçü, ölçtüğü şeyi (karakter sayısını) değil pikseli
     sabitliyordu. Em ile yazıldığında karakter sayısı puntodan
     bağımsız sabit kalır; zaten korunmak istenen odur.

     BANDIN KENDİSİ DEĞİŞTİ — 20 Ağustos 2026, üçüncü bildirimden
     sonra. Kurul üç kez "metin dar" dedi ve üçünde de haklıydı; ben
     üçünde de bandı savundum. Sonunda ölçtüğüm şey bandın KENDİSİ
     oldu ve nereden geldiğine baktım:

       45-75 karakter, Bringhurst'ün 'The Elements of Typographic
       Style' kitabından gelir ve BASILI SAYFA için yazılmıştır. Ekran
       okuma araştırması başka bir aralık verir: Dyson & Haselgrove
       (2001), satır uzunluğu ile okuma hızını ölçtüğünde en hızlı
       okumayı 100 karakter civarında bulur; 55-100 aralığı hem hızlı
       hem kabul edilebilirdir. Akademik yayın platformları da bu üst
       yarıda çalışır.

     ÖLÇÜLEN SONUÇ. Kurulun ekranında (1891 piksel) eski kural şunu
     veriyordu: metin pencerenin %36'sı, boş alan %26. Yani kural,
     basılı bir kitabın ölçüsünü 1891 piksellik bir ekrana uyguluyor ve
     ekranın dörtte birini boş bırakıyordu. Yeni bant EKRAN içindir:

         >= 900 piksel : 65-95 karakter (hedef ~85)
         <  900 piksel : 35-65 karakter (ekranın izin verdiği kadar)

     Yeni ölçü 41em: 21 puntoda 859 piksel, ölçülen 93 karakter. Metin
     payı %36'dan %45'e çıktı, boş alan %26'dan %17'ye indi. 42em de
     denendi; 900-1024 bandında satır 95 karaktere dayanıyor, yani üst
     sınırın tam ucunda. 41em o payı bırakır.

     SAYI EM İLE YAZILI KALIR, ÇÜNKÜ ÖLÇÜM ARACI YANILMIŞTI.
     Karakter sayısı bugüne kadar canvas'ta bir örnek dizeyle
     ('abcdefghijklmnopqrstuvwxyz ') kestiriliyordu. O dizede m ve w
     gibi geniş harfler, Türkçe bir metinde olduğundan çok daha ağır
     basar; ortalama karakter geniş hesaplanır ve satırdaki karakter
     sayısı OLDUĞUNDAN AZ görünür. Gerçek sayım (Range ile satır satır)
     şunu verdi:

         665 px / 19 punto -> kestirim 74, GERÇEK 79 karakter
         803 px / 23 punto -> kestirim 75, GERÇEK 81 karakter

     Yani sayfa kendi yazdığı bandın DIŞINDAYDI ve kapı bunu
     göremiyordu; ölçüm aracı kusuru gizliyordu. Bu depoda kural
     bellidir: ölçüm yanlış çıktığında önce ölçümden şüphelenilir.
     Kapı artık gerçek satırları sayar; sınır da ona göre seçildi. */
  /* ---- BAŞLIK DA OKUMA PUNTOSUNA BAĞLI ----
     Başlık --y-8 ile yazılıydı: rem tabanlı, en çok 32,8 piksel.
     Gövde 23 puntoya çıkınca oran 1,43'e düştü ve başlık, altındaki
     metnin yanında küçük kaldı; okuyucu A+ ile puntoyu büyüttüğünde
     de başlık yerinde duruyordu. Bir belgenin başlığı gövdesiyle
     birlikte ölçeklenir: oran sabit, sayı değil. 1,72em — 19 puntoda
     bugünkü 32,7 pikselin aynısı, 23 puntoda 39,6. */
  .sar > .duzen3 h1{font-size:1.72em;line-height:1.16}

  .duzen3{display:block;--en-okuma:min(41em,100%)}
  /* ---- OKUMA ÖLÇÜSÜ HER GENİŞLİKTE GEÇERLİDİR ----
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Ölçü yalnızca ızgaranın sütun
     tanımında yazılıydı; ızgara 1024'te kuruluyor. Arada kalan bant
     hiçbir şeyle sınırlı değildi ve ölçüldü:

         1000 piksel ->  905 piksel satır,  91 KARAKTER
          900 piksel ->  813 piksel satır,  81 KARAKTER
          820 piksel ->  739 piksel satır,  77 KARAKTER

     Sayfanın kendi yazdığı kural "okunur satır 45-75 karakterdir"
     diyor; tablet genişliğinde uygulanmıyordu. Kural artık sütunun
     kendisinde durur, ızgarada değil: ızgara olmadığında da geçerli.
     Izgara kurulduğunda (1024+) sütun genişliği zaten ızgaradan gelir,
     bu yüzden sınır oraya karışmaz. */
  /* =================================================================
     RAYIN EŞİĞİ 1024'TEN 1280'E ÇIKTI
     -----------------------------------------------------------------
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Kapı 1100 pikseli ölçmeye
     başlayınca çıktı: o genişlikte yan gezinme (250) + kâğıdın iç
     boşluğu + ray (258) metne 463 piksel bırakıyordu, yani satır
     54 KARAKTER. Ray, sığmadığı bir yerde açılıyor ve okunan sütunu
     yiyordu.

     Eşik hesapla değil ölçümle seçildi. Metne kalan genişlik
     min(1600, W-250) - 128 - ray - boşluk; bu değerin 65 karakteri
     taşıyabildiği ilk basamak 1280'dir (613 piksel, 69 karakter).
     1280'in altında ray hiç çizilmez ve künye, dar ekrandaki yerini
     alır (.mobil-bilgi + katlanır içindekiler) — orada zaten sınanmış,
     çalışan bir düzen var.

     Eşik üç yerde geçiyor (ızgara, rayın kendisi, katlanır
     içindekiler) ve üçü de AYNI sayıyı taşımak zorunda; betik ise
     sayıyı yinelemez, rayın çizilip çizilmediğine bakar. */
  @media(max-width:1279px){
    .duzen3 > .icerik{max-width:var(--en-okuma);margin-inline:auto}
  }
  @media(min-width:1280px){
    /* ---- METİN SÜTUNUNUN ÜST SINIRI OKUMA ÖLÇÜSÜDÜR ----
       ÖLÇÜLEN KUSUR — 19 Ağustos 2026, sol ray kaldırılırken çıktı.
       Sütun '--en-metin' ile sınırlanıyordu: min(980px,100%). O sayı
       14-16 punto ARAYÜZ metni için seçilmiştir. Çalışmanın gövdesi ise
       19 punto serifle dizilir ve 980 pikselde satır 109 KARAKTER
       oluyordu (sol ray dururken de 97'ydi). O gün seçilen 670 piksel,
       BASILI SAYFA bandına (45-75) göreydi.

       SINIR 20 AĞUSTOS'TA İKİ KEZ DEĞİŞTİ ve ikisi de ölçümle:
       önce piksel yerine em oldu (punto değişince karakter sayısı
       sabit kalsın diye), sonra bandın kendisi ekran bandına döndü.
       Bugünkü değer 39em ve gerekçesi yukarıda, bandın yanında
       yazılıdır. Buradaki 109 ve 97 sayıları tarihî kayıttır: o gün
       ölçülen buydu ve o gün bu sütun hiçbir sınır tanımıyordu. */
    .duzen3{display:grid;grid-template-columns:minmax(0,var(--en-okuma)) var(--en-ray);
      gap:clamp(20px,2.4vw,36px);align-items:start;justify-content:center}
    /* HATA DÜZELTİLDİ (11 Ağustos 2026). Bu satır 1600 piksellik
       kuralın içindeydi; oysa sağ sütun 1024'te açılıyor. Arada kalan
       her ekranda "PDF İndir" ve "Kaynak Göster" düğmeleri sayfada İKİ
       KEZ çiziliyordu: biri gövdede, biri yan sütunda, aralarında
       yaklaşık yüz piksel. Dar ekranda blok yine görünür, çünkü orada
       yan sütun yoktur ve künyenin yerini o tutar. */
    .mobil-bilgi{display:none}
  }
  /* ---- SOL RAYIN EŞİĞİ 1600'DEN 1400'E İNDİ ----
     Bildirilen kusur (15 Ağustos 2026): "sol taraftaki boşluğu da
     değerlendirelim."

     Ölçüldü: 1440 piksel — en yaygın dizüstü genişliği — 1600 eşiğinin
     ALTINDA kalıyordu; o ekranda içindekiler HİÇ çizilmiyordu ve solda
     yalnız boşluk duruyordu. Yeni eşik hesapla seçildi, gözle değil:
     yan gezinme 250 + sol ray 200 + iki boşluk ~40 + sağ ray 258 = 748;
     1400'de metne 652, 1440'ta 692 piksel kalır. 18 punto serifte 692
     piksel yaklaşık 68 karakterdir ve okunur satır aralığının
     (45-75 karakter) içindedir. 1400'ün altında sol ray yine
     kapanır, çünkü orada metin sütunu 45 karakterin altına inerdi. */
  /* 1400-1599 ARASI DAR BANT: iki ray da incelir ve boşluk daralır.
     Ölçüm, aritmetiği yalanladı — hesapla 692 piksel bekleniyordu, gerçek
     503 çıktı; aradaki fark kabın kendi iç boşluklarıydı. Bu yüzden
     sayılar hesapla değil ÖLÇÜMLE seçildi (aşağıdaki değerlerle metin
     sütunu 1440'ta 62-64 karakter, 1400'te 60 karakter oluyor; okunur
     satır aralığı 45-75 karakterdir). 1600'den sonra bant biter ve
     raylar tam ölçüsüne döner. */
  /* ---- SOL RAY KALDIRILDI (19 Ağustos 2026) ----
     Burada 1400 ve 1600 için iki ayrı eşik ve bir ara bant vardı;
     hepsi tek bir soruyu çözmek içindi: içindekiler sütunu hangi
     genişlikten sonra sığar. Sütun kalkınca soru da kalktı. Eşiği
     olmayan kural, yanlış eşik seçemez.

     RAY 1024'TEN SONRA YAPIŞIK VE KENDİ İÇİNDE KAYAR: içeriği ekrandan
     uzunsa sayfayı değil kendini kaydırır, yoksa okuyucu metni
     kaydırmak için rayın bitmesini beklerdi. */
  /* =================================================================
     RAYIN KENDİ KAYDIRMA ÇUBUĞU YOK
     -----------------------------------------------------------------
     KURUL BİLDİRİMİ — 20 Ağustos 2026: "aşağıda kaydırma çubuğu var,
     burayı normal görmeyecek miyiz."

     Ray yapışkandı ve içeriği ekrandan uzun olduğunda KENDİ İÇİNDE
     kayıyordu (max-height + overflow-y:auto). Gerekçesi vardı: yapışık
     bir ray ekrandan uzunsa alt ucu okunamaz. Ama sonucu, bir belgenin
     ortasında ikinci bir kaydırma alanıydı; okuyucu onu sayfanın bir
     parçası değil, bir pencere gibi görüyor.

     YENİ KURAL: ray sayfanın kendisiyle kayar. Yapışkanlık büsbütün
     atılmadı — SIĞDIĞI ZAMAN uygulanır ve o zaman zaten çubuk çıkmaz.
     Ölçüt betiktedir, çünkü içeriğin boyu ancak çalışma zamanında
     bilinir; betiksiz tarayıcıda ray sıradan bir sütundur ve hiçbir
     şey kaybolmaz.

     RAY SÜTUN BOYUNCA UZATILMADI — DENENDİ VE BIRAKILDI. Uzatılınca
     kâğıdı bölen çizgi baştan sona iniyordu (basılı bir sütun çizgisi
     gibi) ama ölçüldü: rayın içeriği 1210 piksel, çalışma metni 8700;
     yani sayfanın yedi bölü altısında çizginin sağı BOŞ kalıyor.
     İçeriği olmayan bir sütun çizgisi, sayfayı bölmüş gibi görünüp
     hiçbir şey ayırmaz. Çizgi rayın kendi boyunca iner; altında kâğıt
     tek sütuna döner ve o genişlik metnin kenar boşluğu olur. */
  @media(min-width:1280px){
    .yan-sag{align-self:start;font-family:var(--ui);font-size:var(--y-3)}
    .yan-sag.ys-yapisik{position:sticky;top:calc(var(--ust) + var(--b-1))}
    /* Ray ekrana sığmıyorsa bütünü yapışmaz; bunun yerine İÇİNDEKİLER
       rayın sonuna alınır ve tek başına yapışır. Metin okundukça sağ
       sütun boş kalmaz, içindekiler okurla birlikte iner. Kendi
       kaydırması vardır ama çubuğu görünmez; etkin başlık görünür tutulur. */
    .yan-sag.ys-toc-yapisik{align-self:stretch;display:flex;flex-direction:column}
    .yan-sag.ys-toc-yapisik > .ys-toc{order:99;position:sticky;top:calc(var(--ust) + var(--b-1));
      max-height:calc(100vh - var(--ust) - 32px);overflow-y:auto;scrollbar-width:none;
      margin-bottom:0;padding-bottom:0;border-bottom:0}
    .yan-sag.ys-toc-yapisik > .ys-toc::-webkit-scrollbar{display:none}
  }
  /* Dar ekranda iki kenar sütunu da çizilmez. Parmak izi doğrulama aracı
     bu yüzden orada bırakılmaz: sayfanın altındaki betik, sütun
     çizilmediğini görünce aracı makale akışındaki .pi-yuva'ya taşır.
     Eşik BURADA tek yerde yazılıdır; betik sayıyı yinelemez, ögenin
     çizilip çizilmediğine bakar. */
  @media(max-width:1279px){ .yan-sag{display:none} }
  /* Dar ekranda künyenin yerini tutan satır */
  .mobil-bilgi{margin:0 0 var(--b-5)}
  .mb-oku{color:var(--metin-2);font-size:var(--y-3);margin-bottom:var(--b-2)}

  /* ---- Sol sütun: içindekiler ---- */
  .toc-bas{font-size:var(--y-1);letter-spacing:.08em;text-transform:uppercase;
    color:var(--kut);margin:0 0 var(--b-2)}
  /* Satır 44'ten 32'ye indi. WCAG 2.5.8 AA en az 24 ister; 32 onun
     üstünde. Otuz üç satırlık bir listede kazanç 396 piksel. */
  .toc-liste a,.toc-sekil a{display:flex;align-items:center;min-height:32px;
    padding:var(--b-1) 0 var(--b-1) var(--b-3);color:var(--metin-2);
    border-left:2px solid transparent;line-height:var(--sh-orta)}
  .toc-liste a:hover,.toc-sekil a:hover{color:var(--metin);text-decoration:none}
  .toc-liste a.alt{padding-left:var(--b-5)}
  .toc-liste a.alt,.toc-sekil a{font-size:var(--y-2)}
  .toc-liste a.etkin{color:var(--kut);border-left-color:var(--kut)}
  .toc-sekil{margin-top:var(--b-4)}
  /* Kaynakça, içindekilerin son maddesidir ama ne bir bölüm başlığı ne
     de bir şekildir: kendi kümesi olarak, üstünde bir çizgiyle durur.
     Çizgi olmadan "Şekiller ve Tablolar" başlığının altına düşüyor ve
     bir şekil sanılıyordu. */
  .toc-knk{margin-top:var(--b-3);padding-top:var(--b-2);border-top:1px solid var(--cizgi)}

  /* ---- Katlanan alt başlıklar ----
     Ana başlık ile aç/kapa düğmesi tek satırda durur; düğme kendi
     dokunma hedefini taşır (32x32) ve okun yönü durumu söyler.
     Kapalı küme düzenden de çıkar (display:none): ekranda olmayan bir
     bağlantıya klavyeyle düşmek, listeyi kısaltmanın anlamını
     büsbütün ortadan kaldırırdı. */
  .toc-grup-bas{display:flex;align-items:center;gap:2px}
  .toc-grup-bas > a{flex:1 1 auto;min-width:0}
  .toc-ac{flex:none;width:32px;height:32px;padding:0;border:0;background:transparent;
    color:var(--metin-2);cursor:pointer;border-radius:var(--r-2);
    display:grid;place-items:center}
  .toc-ac::before{content:"";width:6px;height:6px;border-right:1.6px solid currentColor;
    border-bottom:1.6px solid currentColor;transform:rotate(-45deg);
    transition:transform var(--gecis)}
  .toc-grup.acik > .toc-grup-bas > .toc-ac::before{transform:rotate(45deg)}
  .toc-ac:hover{color:var(--kut);background:var(--yuzey-2)}
  .toc-alt{display:none}
  .toc-grup.acik > .toc-alt{display:block}

  /* ---- Sağ sütun: künye ---- */
  /* ---- RAY TEK BİR KUTUDUR, BÖLÜMLERİ ÇİZGİYLE AYRILIR ----
     19 Ağustos 2026. Burada dört ayrı '.kutu' vardı ve aralarında
     boşluk duruyordu; okura bu, yan yana duran birkaç ayrı bölme gibi
     görünüyordu ("sağda da iki tane sidebar var"). Kutu artık dıştadır
     ve içerideki bölümler bir çizgiyle ayrılır: aynı bilgi, tek bir
     yapı. Sınıf adları değişmedi, çünkü betik ve yazdırma kuralları
     onlara bakıyor. */
  .yan-sag{border:1px solid var(--cizgi);border-radius:var(--r-3);
    background:var(--yuzey);padding:var(--b-4)}

  /* =================================================================
     ÇALIŞMA SAYFASI BİR BELGEDİR: KÂĞIT
     -----------------------------------------------------------------
     KURUL BİLDİRİMİ — 20 Ağustos 2026: "buradaki alan çok daralmış,
     bu tasarımı özellikle makale için daha uygun hâle getir."

     ÖLÇÜM ONU DOĞRULADI. 1920 pikselde sayfa şöyleydi:

       yan gezinme   0..250
       BOŞLUK      250..565   (315 piksel)
       metin       565..1235  (670 piksel, 67 karakter)
       BOŞLUK     1235..1271  (36)
       ray        1271..1591  (320)
       BOŞLUK     1591..1920  (329 piksel)

     Yani okunan alan ekranın %35'i, iki yanında 644 piksel HİÇBİR ŞEY.
     Satır uzunluğu doğruydu (67 karakter; okunur aralık 45-75) ama
     sayfa, iki boşluk arasına sıkışmış bir şerit gibi duruyordu.

     İKİ YANLIŞ ÇÖZÜM VE NİÇİN SEÇİLMEDİKLERİ:
       (a) Satırı uzatmak. 644 pikseli satıra vermek satırı 130 karaktere
           çıkarırdı; okunurluk ölçülmüş bir sınırdır, boşluk doldurmak
           için bozulmaz.
       (b) Rayı şişirmek. Yan bilgi, okunan şeyin yerini alamaz.

     SEÇİLEN: boşluğu SAHİPLENMEK. Metin ile ray tek bir kâğıdın üstüne
     alınır; kâğıt içeriğine göre daralır (fit-content) ve ortalanır.
     Böylece iki yandaki boşluk "kullanılmamış yer" olmaktan çıkıp
     BELGENİN KENAR BOŞLUĞU olur — basılı bir makalede olduğu gibi.
     Ölçüm de bunu söylüyor: aynı ekranda kâğıt 1142 piksel, iki yanında
     eşit kenar boşluğu kalır ve okunan şey bir sayfa gibi durur.

     Eşik 1200'dür ve hesapla değil ölçümle seçildi: altında kâğıdın
     kendi iç boşluğu metin sütunundan çalıyor, yani kâğıt okunurluğu
     iyileştirmek yerine bozuyordu. Yer yoksa kâğıt da yoktur.

     Okuma kipinde (body.oku) kâğıt ZATEN vardır (.sar'a verilir);
     ikisi üst üste binmesin diye orada bu kural kapanır. */
  @media(min-width:1440px){
    body:not(.oku) .duzen3{
      /* KÂĞIDIN İÇ BOŞLUĞU METİNDEN ÇALMAZ.
         Önce clamp(22px,2.6vw,52px) yazıldı ve ölçüm onu yalanladı:
         1280'de satır 613'ten 545 piksele, 1440'ta 670'ten 646'ya
         indi — yani kâğıt, düzeltmeye çalıştığı şeyi bozuyordu. İç
         boşluk artık ARTAN YERDEN hesaplanır: sütunlar yerini alır,
         KALAN ikiye bölünür. Kalan yoksa boşluk da yoktur.
         Eşik de bu hesaptan çıktı: 1440'ın altında kalan 20 pikselin
         altına düşüyor, yani kâğıdın açılacağı yer yok. */
      --kagit-ara:clamp(20px,2.4vw,36px);
      --kagit-ic:clamp(18px,calc((100% - (var(--en-okuma) + var(--en-ray) + var(--kagit-ara))) / 2),52px);
      /* GENİŞLİK 'fit-content' DEĞİL, HESAPLANMIŞ.
         Önce fit-content yazıldı ve ölçüm onu yalanladı: kâğıt
         daralmadı, kabın tamamını (1472 piksel) kapladı. Sebep
         ızgaranın kendi sütun tanımı — minmax(0,min(35em,100%)) —
         içindeki yüzdedir: içerik genişliğine göre ölçüm yapılırken
         yüzde belirsiz kalır, sütun 'auto' gibi davranır ve bir
         paragrafın tek satırlık max-content genişliğine kadar açılır.
         Genişlik artık sütunların TANIMINDAN toplanır; yüzde burada
         belirlidir, çünkü kabın genişliği bellidir. */
      width:min(100%, calc(var(--en-okuma) + var(--kagit-ara) + var(--en-ray) + 2 * var(--kagit-ic)));
      margin-inline:auto;max-width:100%;
      background:var(--yuzey);border:1px solid var(--cizgi);
      border-radius:var(--r-4);box-shadow:var(--g-1);
      padding:var(--kagit-ic);
    }
    /* Kâğıdın üstünde ikinci bir çerçeve olmaz: ray, çizgiyle ayrılır.
       Kutu içinde kutu, "iki ayrı bölme" duygusunun kaynağıydı ve 19
       Ağustos'ta rayın içinde bir kez zaten kaldırılmıştı. */
    body:not(.oku) .duzen3 > .yan-sag{
      border:0;border-radius:0;background:transparent;
      border-left:1px solid var(--cizgi);
      padding:0 0 0 var(--kagit-ic);
    }

    /* ŞEKİL VE ÇİZELGE KENAR BOŞLUĞUNA TAŞMAZ — DENENDİ VE BIRAKILDI.
       Önce taşırıldı (basılı makalede gövde dar, şekil geniştir). Ama
       burada metin sütunu kâğıdın SOLUNDA, ray sağında: sağa taşan bir
       şekil rayı ayıran çizgiye dayanıyor, sola taşan ise gövdenin sol
       kenarını — okuyucunun gözünün her satır başında döndüğü tek
       çizgiyi — bozuyordu. Kazanç yoktu, iki kenar da düzensizleşti.
       Şekil ve çizelge sütunun kendi genişliğini doldurur. */
  }
  /* ---- PARAGRAF ARALIĞI ----
     Satır aralığı 1,72; paragraf arası 1em (19 piksel) idi, yani bir
     satırın YARISINDAN az. Uzun bir metinde paragraf sınırı böyle
     silinir ve sayfa tek bir blok gibi görünür — "daralmış" duygusunun
     ikinci kaynağı budur. Aralık bir satırın üçte ikisine çıkarıldı;
     okuma kipindeki değerle (1,25em) aynı ailedendir. */
  .govde > p{margin-block:0 1.15em}
  .ys-blok{padding-bottom:var(--b-4);margin-bottom:var(--b-4);
    border-bottom:1px solid var(--cizgi)}
  .ys-blok:last-child{padding-bottom:0;margin-bottom:0;border-bottom:0}
  .ys-blok .d + .d{margin-top:var(--b-2)}
  /* İçindekiler rayın ilk bölümüdür; kendi başlığını taşır ki
     altındaki mühürle karışmasın. */
  .ys-toc:empty{display:none}
  /* Telefonda içindekiler metnin başında, kapalı bir kapak arkasında.
     Rayla aynı çerçeveyi kullanır: aynı şey iki ekranda iki ayrı şey
     gibi görünmesin. */
  .toc-mobil{border:1px solid var(--cizgi);border-radius:var(--r-3);
    background:var(--yuzey);padding:var(--b-3) var(--b-4);margin:0 0 var(--b-5)}
  .toc-mobil > summary{cursor:pointer;font-family:var(--ui);font-weight:600;
    font-size:var(--y-3);min-height:var(--hedef);display:flex;align-items:center}
  .toc-mobil[open] > summary{margin-bottom:var(--b-3);
    border-bottom:1px solid var(--cizgi);padding-bottom:var(--b-2)}
  .toc-mobil #tocNav{font-family:var(--ui);font-size:var(--y-3)}
  @media(min-width:1280px){ .toc-mobil{display:none} }
  .ys-metrik{text-align:center}
  /* ---- Tamga mührü ----
     Çalışmanın kodundan üretilen işaret. Ortada durur, altında kodun
     kendisi yazar: göz önce işareti tanır, sonra kimliği okur. Mühür
     currentColor kullandığı için ayrıca renk verilmez; koyu temada da
     bulunduğu yerin rengini alır. */
  .ys-muhur{text-align:center;padding-block:var(--b-4)}
  .ys-muhur svg{margin-inline:auto;color:var(--kut)}
  .ys-muhur-kod{display:block;font-family:var(--mono);font-size:var(--y-2);
    letter-spacing:.04em;color:var(--metin-2);margin-top:var(--b-2);word-break:break-all}
  .ys-muhur-ack{display:block;font-size:var(--y-1);color:var(--metin-3);margin-top:var(--b-1)}
  /* Dar ekranda künye satırının yanında küçük durur. */
  .mb-muhur{display:flex;align-items:center;gap:var(--b-3);margin-bottom:var(--b-3)}
  .mb-muhur svg{flex:none;color:var(--kut)}
  .mb-muhur-kod{font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2);
    letter-spacing:.04em;word-break:break-all}
  .ys-say{font-family:var(--ui);font-size:var(--y-7);font-weight:600;color:var(--kut);line-height:var(--sh-sik)}
  .ys-say-alt{color:var(--metin-2);font-size:var(--y-2);margin-top:var(--b-1)}
  .ys-bilgi h4{margin:0 0 var(--b-2);color:var(--kut);font-family:var(--ui);
    font-size:var(--y-1);font-weight:700;letter-spacing:.13em;text-transform:uppercase}
  /* SATIR SARILIR, KIRPILMAZ. 1400-1599 bandında sağ ray 236 piksele
     iner ve künyedeki uzun bir aşama değeri satıra sığmıyordu.
     (Değer burada ÖRNEKLE yazılmaz: aşama metinleri tek kaynaktan,
     ortak.php'deki tg_asama_metni'nden gelir ve elle yazılmış her
     kopyası asama-kapi tarafından kusur sayılır — haklı olarak.)
     flex ögesi kendi en küçük içerik genişliğinin altına inmeyi
     reddettiği için değer kutunun DIŞINA taşıyor, kutu overflow:hidden
     olduğu için son harfi kırpılıyordu. Kırpılan bir değer, yanlış bir
     değerdir. min-width:0 taşmayı bitirir, overflow-wrap uzun tek
     sözcükleri böler ve satır gerekirse iki satıra iner. */
  .ys-sat{display:flex;justify-content:space-between;gap:var(--b-2);font-size:var(--y-2);
    padding:var(--b-1) 0;border-bottom:1px solid var(--cizgi);flex-wrap:wrap}
  .ys-sat:last-of-type{border-bottom:0}
  .ys-sat span{color:var(--metin-2);min-width:0}
  .ys-sat b{text-align:right;font-weight:600;min-width:0;overflow-wrap:anywhere}
  /* AÇIKLAMA SATIRI. İki sütunlu düzen bir DEĞER içindir; bir cümle
     sağa yaslanıp kalın yazıldığında değer sanılır. Bu satır tek
     sütuna düşer, sola yaslanır ve normal ağırlıkta yazılır: söylediği
     şey bir ölçüm değil, bir açıklamadır. */
  .ys-sat.ys-not{display:block}
  .ys-sat.ys-not span{display:none}
  .ys-sat.ys-not b{display:block;text-align:left;font-weight:400;color:var(--metin-2);
    font-size:var(--y-3);line-height:var(--sh-orta)}
  /* Yapılandırılmış özde bölüm adı bir ETİKETtir, bir başlık değil:
     kendi satırını almaz, cümlenin başında durur. Kendi satırını
     alsaydı dört kelimelik dört başlık, özetin kendisinden uzun bir
     iskelet kurardı. */
  .oz-yapi p{margin:0 0 var(--b-2)}
  .oz-yapi p:last-child{margin-bottom:0}
  .oz-yapi b{color:var(--metin-2)}
  /* ---- Parmak izi satırı ----
     Satır iki sütunludur ve sağdaki değer otuz iki haneli tek bir
     sözcüktür (word-break:break-all). Esneklikte sağ sütun bütün payı
     aldığı için sol sütun "Parmak / izi" diye ikiye kırılıyordu: sözcük
     değil etiket bölünmüş oluyordu ve satır iki katına çıkıyordu.
     Etiket bölünmez yapılır ve daralmaya kapatılır; kırılacak yer,
     kırılmak üzere biçimlenmiş olan değerdir.
     1440'ta ölçüldü: etiket iki satırdan bire indi. 1023 pikselin
     altında sağ sütunun tamamı gizlidir (.yan-sag), yani 820'de bu
     satır hiç basılmaz; ölçüm oradan da doğrulandı. */
  .ys-ozet{align-items:baseline}
  .ys-ozet > span{flex:0 0 auto;white-space:nowrap}
  .ys-ozet b{font-weight:500;min-width:0}
  .ys-ozet code{font-size:var(--y-1);color:var(--metin-2);word-break:break-all;line-height:var(--sh-orta)}
  .ys-anah{display:flex;flex-wrap:wrap;gap:var(--b-1);margin-top:var(--b-3)}

  /* ---- Parmak izi doğrulama aracı ----
     Bu araç BİLEREK sayfa içindedir: ne k/kutadgu.css'e ne de
     k/kutadgu.js'e girer. Yalnızca bu sayfada işe yarar, ortak
     dosyalara girseydi tek bir düğme için TG_SURUM artırmak ve bütün
     sitenin önbelleğini tazelemek gerekirdi. Bedeli birkaç satırın
     burada durmasıdır; kazancı, aracın değişmesinin başka hiçbir
     sayfayı ilgilendirmemesidir.
     Basılı kopyada yeri yoktur (no-print): kâğıtta tıklanacak bir
     düğme yoktur, ama yöntem ve komut yine metnin içinde yazılıdır. */
  .pi-arac{margin-top:var(--b-3)}
  .pi-cikti{margin-top:var(--b-3);font-size:var(--y-1);line-height:var(--sh-orta);
    color:var(--metin-2);border-top:1px solid var(--cizgi);padding-top:var(--b-3)}
  .pi-cikti p{margin:0 0 var(--b-2)}
  .pi-cikti > :last-child{margin-bottom:0}
  .pi-et{display:block;color:var(--metin-3)}
  .pi-deger{display:block;font-family:var(--mono);font-size:var(--y-1);
    color:var(--metin);word-break:break-all;line-height:var(--sh-orta)}
  .pi-hukum{font-weight:600}
  .pi-tutuyor{color:var(--yesil)}
  .pi-tutmuyor{color:var(--kirmizi)}
  .pi-uyari{color:var(--kirmizi)}
  .pi-kod{display:block;font-family:var(--mono);font-size:var(--y-1);
    background:var(--yuzey-2);color:var(--metin);border-radius:var(--r-2);
    padding:var(--b-2);margin:var(--b-1) 0 var(--b-2);
    white-space:pre-wrap;word-break:break-all;overflow-wrap:anywhere}
  .pi-cikti a{color:var(--kut)}
  .pi-cikti .d{margin-top:var(--b-2)}
  .pi-ac{margin-bottom:var(--b-2)}
  .pi-ac summary{cursor:pointer;color:var(--kut);min-height:var(--hedef);
    display:flex;align-items:center}
  /* Ham kaynak dar sütuna sığmaz; kendi içinde kaydırılır. Kırpılmaz:
     kırpılmış bir kaynağın özeti tutmaz ve okur doğru bir metni yanlış
     sanır. Gösterilen dizge, indirilen dosyanın birebir aynısıdır. */
  .pi-ham{max-height:16em;overflow:auto}

  /* ---- Aracın dar ekrandaki yuvası ----
     Yuva boşken hiç yer kaplamaz; sayfada tek bir araç vardır ve o araç
     ya yan sütundadır ya buradadır. Dolduğu anda kendini bir kesitmiş
     gibi ayırır: metin bitti, doğrulama başlıyor.
     Punto burada büyütülür. Yan sütunda yer dar olduğu için en küçük
     basamak kullanılır; telefonda aynı puntoyu bırakmak, aracın "ne
     gösterir, ne göstermez" cümlesini okunmaz kılardı. Cümle katlanmaz,
     kısaltılmaz: aracın dürüstlüğü tam olarak o cümlededir. */
  .pi-yuva:empty{display:none}
  .pi-yuva{margin-top:var(--b-6);padding-top:var(--b-4);
    border-top:2px solid var(--cizgi);font-family:var(--ui)}
  .pi-yuva .ys-sat{font-size:var(--y-3)}
  .pi-yuva .pi-cikti{font-size:var(--y-3)}
  .pi-yuva .pi-et{font-size:var(--y-2)}
  .pi-yuva .pi-deger,.pi-yuva .pi-kod,.pi-yuva .ys-ozet code{font-size:var(--y-2)}

  /* ---- Okuma araçları ----
     Üst çubuğun içinde dururlar; lacivert üstündeki görünüşleri
     dizgeden gelir (.ust .d). Burada yalnızca yerleşimleri var. */
  .arac-oku{display:flex;gap:var(--b-1)}
  @media(max-width:1100px){ .arac-oku #okuModu{display:none} }
  /* Yaslama okurun seçimidir ve varsayılan KAPALIDIR: tarayıcılar Türkçe
     sözcükleri bölmediği için dar sütunda kelime aralıkları açılır.
     Seçim tarayıcıda saklanır; geniş sütunda ve isteyende işe yarar. */
  body.yasli .icerik p, body.yasli .icerik li, body.yasli .ozet p{text-align:justify;hyphens:auto;-webkit-hyphens:auto}
  @media(max-width:640px){ .arac-oku{display:none} }

  /* ---- Başlık ve künye satırı ---- */
  .bs-dil{font-size:var(--y-1);vertical-align:middle;font-family:var(--ui)}
  .bs-ozgun{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);
    margin:calc(-1 * var(--b-2)) 0 var(--b-3);line-height:var(--sh-orta)}
  .bs-ozgun span{color:var(--metin-3)}
  .meta{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);margin-bottom:var(--b-4)}
  /* Yazar adı bir bağlantı değil, künye kutusunu açan bir düğmedir;
     metnin içinde durur ama dokunulacak yüzeyi tam yüksekliktedir. */
  .yazar-ac{display:inline-flex;align-items:center;min-height:var(--hedef);padding:0;
    background:transparent;border:0;color:var(--kut);font:inherit;cursor:pointer;text-align:left}
  .yazar-ac:hover{text-decoration:underline}
  .yazar-l{color:var(--kut)}
  abbr.neki{text-decoration:none;cursor:help;border-bottom:1px dotted var(--metin-2)}
  .rozet{display:flex;flex-wrap:wrap;gap:var(--b-2);margin-bottom:var(--b-4)}

  /* Geri çekme ve ret bandı: sayfada en yüksek sesle konuşan yer. */
  .ret-buyuk{background:var(--kirmizi);color:var(--dolu-metin);border-radius:var(--r-3);
    padding:var(--b-4) var(--b-5);margin:var(--b-1) 0 var(--b-5);font-family:var(--ui)}
  .ret-buyuk .ret-b{display:block;font-size:var(--y-7);font-weight:800;letter-spacing:.04em}
  .ret-buyuk .ret-a{display:block;font-size:var(--y-3);margin-top:var(--b-1)}
  .ret-buyuk .ret-a a{color:var(--dolu-metin);text-decoration:underline}

  /* Bölüm başlıkları. Bu sayfada h2 bir bölümün adı değil, okunan
     belgenin içindeki ayraçtır: gövdeden ayrılsın diye arayüz yüzünde
     ve küçük punto ile yazılır. */
  h2{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.13em;text-transform:uppercase;
    color:var(--kut);border-bottom:1px solid var(--cizgi);padding-bottom:var(--b-1);margin:var(--b-6) 0 var(--b-3)}

  .ozet{font-size:var(--y-5)}
  .ozet h2{margin-top:0;border:0;padding:0}
  .anahtar{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);margin:var(--b-3) 0 0}

  /* METİN İKİ YANA YASLANMAZ.
     Burada "text-align:justify" ile birlikte "hyphens:auto" yazılıydı.
     Niyet doğruydu: yaslama ancak tireleme varsa işe yarar, çünkü satırı
     doldurmanın öteki yolu kelimeleri birbirinden uzaklaştırmaktır. Ama
     tarayıcılar Türkçe kelimeleri bölmüyor; "hyphens:auto" yazılı olsa
     da hiçbir şey yapmıyor. Sınandı: tireli ve tiresiz iki paragrafın
     yüksekliği birebir aynı çıktı.

     Sonucu ölçtük. 756 piksellik makale sütununda yaslanmış metinde
     ortalama kelime aralığı 9,6 piksel, en büyüğü 44 piksel; 120
     aralığın 42'si gerilmiş. 390 pikselde ortalama 11,7, en büyük 86
     piksel, 120 aralığın 50'si gerilmiş. Sola dayalı metinde ortalama
     3,8 ve 2,9 piksel, 8 pikseli aşan tek aralık yok.

     Yani yaslama burada düzgün bir sağ kenar karşılığında satır içinde
     beyaz nehirler açıyordu. Basılı kitapta yaslama iyi durur çünkü
     orada tireleme vardır; ekranda, hele dar sütunda, yoktur.

     Bu, arşiv çok dilli olduğu için ayrıca önemlidir: kırk iki dil için
     ayrı tireleme kuralı taşımak, yanlış bölünmüş bir bilimsel terimi
     göze almak demektir. Düzensiz bir sağ kenar kusur değildir; yanlış
     bölünmüş bir kelime kusurdur. */
  .govde,.ozet div{text-align:left}
  /* =================================================================
     YAZARIN KENDİ BÖLÜM BAŞLIKLARI · BELGE BAŞLIĞIDIR, ETİKET DEĞİL
     -----------------------------------------------------------------
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Sayfadaki h2 kuralı tek ve
     etiketti: arayüz yüzü, --y-1 (11,5 piksel), VERSAL, harf aralığı
     .13em, altın renk, altında çizgi. O biçim sayfanın KENDİ
     bölümleri için doğrudur — "ÖZET", "HAKEM DEĞERLENDİRMESİ",
     "KAYNAKÇA" okunan metnin parçası değil, sayfanın mobilyasıdır.

     Ama aynı kural yazarın kendi metnine de uygulanıyordu. Ölçüm:
     gövde 19 piksel serif, bölüm başlığı 11,5 piksel versal — yani
     "GİRİŞ", "YÖNTEM", "BULGULAR" başlıkları kendi metninden KÜÇÜK
     görünüyor, başlık gibi değil resim altyazısı gibi duruyordu. Bir
     makalede bölüm başlığı, metnin yapısını gösteren ana işarettir;
     ondan küçük olamaz.

     İkinci kusur aralıktaydı: h2'nin üst boşluğu 32, alt boşluğu 12
     pikseldi ve bu doğru yöndeydi, ama gövdenin kendi paragraf
     aralığıyla birlikte başlık, ait olduğu bölümden çok BİR ÖNCEKİ
     bölüme yakın duruyordu. Başlık kendinden SONRAKİNE bağlanır.

     İki rol ayrıldı: sayfanın etiketleri olduğu gibi kaldı, yazarın
     başlıkları belge başlığı oldu. Ölçüler gövdeye göre (em) verilir
     ki okuyucu punto büyüttüğünde başlıklar da birlikte büyüsün. */
  .govde h2,.govde h3,.govde h4{font-family:var(--serif);text-transform:none;
    letter-spacing:normal;color:var(--metin);border:0;padding:0;text-align:left}
  .govde h2{font-size:1.34em;font-weight:700;line-height:1.28;margin:2.1em 0 .5em}
  .govde h3{font-size:1.12em;font-weight:700;line-height:1.34;margin:1.9em 0 .45em}
  .govde h4{font-size:1em;font-weight:700;margin:1.6em 0 .35em}
  /* Metnin ilk ögesi başlıksa üstünde ikinci bir boşluk açılmaz:
     kâğıdın kendi iç boşluğu zaten oradadır. */
  .govde > :is(h2,h3,h4):first-child{margin-top:0}
  .govde img{border-radius:var(--r-2);margin:var(--b-4) auto}
  /* ---- ÇİZELGE SÜTUNU DOLDURUR ----
     ÖLÇÜLEN KUSUR — 20 Ağustos 2026. Çizelge 'display:block' idi
     (dar ekranda kendi içinde kaysın diye) ve blok bir çizelgenin
     içindeki asıl çizelge kutusu içeriğine göre daralır: dört sütunlu
     bir çizelge 670 piksellik gövdenin ortasında 495 pikselde kalıyor,
     sağında 175 piksel boşluk bırakıyordu. Bir makalede veri çizelgesi
     metin sütununu doldurur; daralmış bir çizelge yarım kalmış görünür.

     Çözüm iki katmanlı ve BETİKSİZ DE ÇALIŞIR: öntanımlı kural
     bugünküdür (blok + kendi içinde kaydırma), yani betiği kapalı
     okuyucu hiçbir şey kaybetmez. Betik çizelgeyi bir kaydırma kabına
     aldığında çizelge gerçek çizelgeye döner ve sütunu doldurur;
     sığmadığında kap kaydırır, sayfa taşmaz. */
  .govde table{display:block;max-width:100%;overflow-x:auto;border-collapse:collapse;
    margin:var(--b-4) 0;font-family:var(--ui);font-size:var(--y-4);-webkit-overflow-scrolling:touch}
  .tablo-kaydir{overflow-x:auto;margin:var(--b-4) 0;-webkit-overflow-scrolling:touch}
  .tablo-kaydir > table{display:table;width:100%;margin:0}
  .govde th,.govde td{border:1px solid var(--cizgi);padding:var(--b-2) var(--b-3);text-align:left}
  .govde figure{margin:var(--b-4) 0;text-align:center}
  .govde figcaption{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);margin-top:var(--b-1)}
  .govde a,.kaynakca a{color:var(--kut)}

  .kaynakca{font-size:var(--y-4)}
  .kaynakca p{margin:.5em 0;padding-left:1.6em;text-indent:-1.6em}
  /* Kaynağa götüren im bir cümlenin sonuna iliştirilmiştir: dokunma
     hedefi ölçütünün dışındadır ve satır aralığını bozmasın diye
     rozetin en küçük yüksekliği burada geri alınır. */
  .kaynakca a.kref{min-height:0;padding-inline:var(--b-2);text-indent:0;margin-left:var(--b-1)}

  /* ---- Vurgu kutusu ----
     Kenarından altın bir çizgi geçen küçük kutu. Editör notu, şerh
     yanıtı, perdeleme kaydı ve rapor niteliği aynı biçimi kullanır. */
  .vurgu-kutu{border-left:3px solid var(--kut);background:var(--kut-zemin);
    border-radius:0 var(--r-2) var(--r-2) 0;padding:var(--b-2) var(--b-3);
    font-family:var(--ui);font-size:var(--y-3);line-height:var(--sh-genis)}
  .vurgu-kutu a{color:var(--kut)}

  /* ---- Editöryal notlar ---- */
  .ed-not{font-family:var(--ui);margin:var(--b-4) 0}
  .ed-not h2{font-family:var(--ui);font-size:var(--y-1);font-weight:700;margin:0 0 var(--b-3);color:var(--metin-2);
    letter-spacing:.05em;text-transform:uppercase}
  .ed-n{margin-bottom:var(--b-3)}
  /* Not gövdeleri: kayıt metni, oy gerekçesi, editör notu. */
  .ed-n p,.kayit p,.oyl-o p{margin:0;font-size:var(--y-3);line-height:var(--sh-genis)}
  .ed-n-bas{display:flex;flex-wrap:wrap;gap:var(--b-3);align-items:center;
    font-size:var(--y-3);margin-bottom:var(--b-1)}
  .ed-n-bas span{color:var(--metin-2);font-size:var(--y-2)}

  /* ---- Hakem raporları ---- */
  .hakemler ul{font-family:var(--ui);font-size:var(--y-4);padding-left:1.2em}
  .hakem-acik{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);margin-top:calc(-1 * var(--b-1))}
  /* ---- HAKEM RAPORU KARTI: TEK STANDART ----
     Kurul isteği: "hakem raporları, yazışmalar bir standarda ve
     görüntüye bağlansın."

     Kartın solunda ince bir şerit durur ve şeridin rengi KARARIN
     rengidir; okur listeye baktığında hangi raporun ne dediğini
     okumadan önce görür. Renkler yeni değil, kararın zaten kullandığı
     renklerdir (aynı değerler rozetin arka planında da duruyor) —
     palete tek bir renk eklenmedi.

     Renk TEK BAŞINA bilgi taşımaz: karar ayrıca rozette yazıyla da
     durur (WCAG 1.4.1). Şerit bir kolaylıktır, bir gösterge değil. */
  .hakem-kutu{margin:var(--b-3) 0;font-family:var(--ui);
    border-inline-start:3px solid var(--cizgi-2)}
  .hakem-kutu[data-karar="kabul"]{border-inline-start-color:var(--yesil)}
  .hakem-kutu[data-karar="kucuk"]{border-inline-start-color:var(--kut)}
  .hakem-kutu[data-karar="buyuk"]{border-inline-start-color:var(--kut)}
  .hakem-kutu[data-karar="ret"]{border-inline-start-color:var(--kirmizi)}
  .hakem-bas{display:flex;justify-content:space-between;align-items:center;gap:var(--b-2);
    flex-wrap:wrap;font-size:var(--y-4)}
  .hakem-ad-ac{display:inline-flex;align-items:center;gap:var(--b-2);min-height:var(--hedef);
    padding:0;background:transparent;border:0;color:var(--kut);font:inherit;font-size:var(--y-4);
    cursor:pointer;text-align:left}
  .hakem-ad-ac:hover{text-decoration:underline}
  .hakem-ad-ac .ac-ok{font-size:var(--y-2);color:var(--metin-2)}
  .hakem-kisi:hover{color:var(--kut);border-color:var(--kut)}
  /* Karar rozetinin zemini karara göre değişir ve satır içinde verilir;
     üstündeki yazı her iki temada da ölçülmüş dolu yüzey rengidir. */
  .karar,.hkarar{color:var(--dolu-metin)}
  .hakem-rozet{display:flex;flex-wrap:wrap;gap:var(--b-1) var(--b-2);align-items:center;margin-top:var(--b-2)}
  /* Atama kaydı bir etiket değil bir cümledir: rozete sokulunca dar
     ekranda satırı taşırıyordu. */
  .hakem-atayan{font-size:var(--y-2);color:var(--metin-2);line-height:var(--sh-orta)}
  .hakem-rozet:empty{display:none}
  .hakem-yetkin{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);
    line-height:var(--sh-genis);margin-top:var(--b-2)}
  .hakem-yetkin b{color:var(--metin);font-weight:600}
  .hakem-nitelik,.hmodal-nitelik{margin-top:var(--b-2)}
  .hakem-surum{font-family:var(--ui);font-size:var(--y-2);color:var(--metin-2);margin-top:var(--b-1)}
  .hakem-rapor{margin-top:var(--b-2);font-size:var(--y-4);line-height:var(--sh-genis)}
  .ham-indir{font-family:var(--ui);font-size:var(--y-3);margin-top:var(--b-3)}
  /* Ok bağlantısı yerine ortak düğme bileşeni kullanılıyor; renk kuralı
     onun üstüne binmesin diye yalnızca düğme olmayan bağlantılara. */
  .ham-indir a:not(.d){color:var(--kut)}
  .ham-band{font-family:var(--ui);margin:var(--b-3) 0}
  .ham-band a{color:var(--kut)}
  .ret-tek b{color:var(--kirmizi)}

  /* ---- Hakem penceresi ---- */
  .hsurum{border-top:1px solid var(--cizgi);margin-top:var(--b-3);padding-top:var(--b-3)}
  .hsurum:first-of-type{border-top:0;margin-top:var(--b-1)}
  .hsurum-bas{display:flex;align-items:center;gap:var(--b-2);flex-wrap:wrap;font-size:var(--y-3)}
  .hsurum-rapor{margin-top:var(--b-2);font-size:var(--y-4);line-height:var(--sh-genis);white-space:pre-wrap}
  .hend,.hnotb{margin-top:var(--b-3);font-size:var(--y-3);color:var(--metin-2)}
  .hend .rz{margin:var(--b-1) var(--b-1) 0 0}
  .hnot{border:1px solid var(--kirmizi-cizgi);background:var(--kirmizi-zemin);
    border-radius:var(--r-2);padding:var(--b-2) var(--b-3);margin-top:var(--b-2)}
  .hnot-al{border-left:3px solid var(--kirmizi);padding-left:var(--b-2);color:var(--kirmizi);
    font-size:var(--y-3);white-space:pre-wrap}
  .hnot-nt{color:var(--metin);font-size:var(--y-3);margin-top:var(--b-1);white-space:pre-wrap}
  .hp-kart{margin:0 0 var(--b-3);padding:var(--b-2) var(--b-3);background:var(--yuzey-2);
    border:1px solid var(--cizgi);border-radius:var(--r-2);font-family:var(--ui)}
  .hp-sat{font-size:var(--y-3);color:var(--metin-2);margin:var(--b-1) 0}
  /* Yazar ve hakem yazışması. İki taraf sol kenar rengiyle ayrılır;
     renk süs değil, kimin konuştuğunu bir bakışta söylemek içindir. */
  .hakem-diyalog{margin-top:var(--b-4);padding-top:var(--b-3);border-top:1px solid var(--cizgi)}
  .hakem-diyalog h4{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.13em;
    text-transform:uppercase;color:var(--metin-2);margin:0 0 var(--b-3)}
  .hd-sat{border-left:3px solid var(--cizgi-2);padding-left:var(--b-3);margin-bottom:var(--b-3)}
  .hd-yazar{border-left-color:var(--lacivert)}
  .hd-hakem{border-left-color:var(--kut)}
  .hd-kim{display:block;font-family:var(--ui);font-size:var(--y-2);font-weight:700;
    color:var(--metin-2);margin-bottom:var(--b-1)}
  .hd-metin{font-size:var(--y-4);line-height:var(--sh-genis)}
  .hp-sat a{color:var(--kut)}
  .htarih{color:var(--metin-2);font-size:var(--y-2)}

  /* ---- Hakem çağrısı ---- */
  .hakem-cagri{display:flex;flex-wrap:wrap;gap:var(--b-4);align-items:center;
    font-family:var(--ui);margin:var(--b-4) 0}
  .hakem-cagri > div{flex:1 1 260px}
  .hakem-cagri b{display:block;font-size:var(--y-4);margin-bottom:var(--b-1)}
  .hakem-cagri span{display:block;font-size:var(--y-3);color:var(--metin-2);
    line-height:var(--sh-genis);max-width:56ch}
  /* Düğmenin yerine geçen cümle. Kutunun içinde durur ve düğme gibi
     değil, not gibi görünür. */
  .hakem-cagri .hakem-cagri-benim{flex:0 0 auto;font-size:var(--y-3);
    color:var(--metin-2);max-width:none}

  /* ---- Kefil kaydı ---- */
  .kfl{margin:var(--b-6) 0;font-family:var(--ui)}
  .kfl h2{font-family:var(--serif)}
  .kfl-ack{font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-genis);margin:0 0 var(--b-4)}
  .kfl-kume{margin:0 0 var(--b-4)}
  .kfl-kim{font-size:var(--y-3);font-weight:700;color:var(--metin-2);letter-spacing:.03em;margin:0 0 var(--b-2)}
  .kfl-k{margin:0 0 var(--b-2)}
  .kfl-bas{display:flex;flex-wrap:wrap;gap:var(--b-1) var(--b-2);align-items:baseline}
  .kfl-bas b{font-size:var(--y-4)}
  .kfl-kurum{font-size:var(--y-2);color:var(--metin-2);margin-top:var(--b-1);line-height:var(--sh-orta)}
  .kfl-ger{font-size:var(--y-3);line-height:var(--sh-genis);margin-top:var(--b-2);
    padding-top:var(--b-2);border-top:1px solid var(--cizgi)}

  /* ---- Listeye alma ---- */
  /* Yer imi bloğu sağ raydadır: dar sütunda düğme tam genişlik,
     açıklama altında. Eski yatay dizilim geniş metin sütunu içindi. */
  .bgn{display:flex;flex-direction:column;align-items:stretch;gap:var(--b-2);
    margin:var(--b-3) 0 0;font-family:var(--ui)}
  .bgn .d[aria-pressed="true"] svg{fill:currentColor}
  .bgn-say{font-size:var(--y-1);font-weight:700;line-height:1;
    padding-left:var(--b-2);border-left:1px solid currentColor}
  .bgn-say[hidden]{display:none}
  .bgn-ack{font-size:var(--y-2);color:var(--metin-2);line-height:var(--sh-orta)}

  /* ---- Kayıtlar ve uyarılar ---- */
  .kayit{font-family:var(--ui);margin:var(--b-3) 0}
  .kayit-bas{display:flex;flex-wrap:wrap;gap:var(--b-3);align-items:center;
    font-size:var(--y-3);margin-bottom:var(--b-1)}
  .kayit-bas span{color:var(--metin-2);font-size:var(--y-2)}
  .kayit-geri_cekme .kayit-bas b{color:var(--kirmizi)}
  .kayit-endise .kayit-bas b{color:var(--kut)}
  .askida{font-family:var(--ui);font-size:var(--y-4);line-height:var(--sh-genis);margin:0 0 var(--b-4)}
  .askida b{display:block;color:var(--kirmizi);font-size:var(--y-5);margin-bottom:var(--b-2)}
  .askida-not{font-size:var(--y-3);color:var(--metin-2)}
  /* Kuruluş dönemi notu: tek satır, isteyene açılan gerekçe. */
  .kurulus-not{padding:0;margin:0 0 var(--b-4);font-family:var(--ui)}
  .kurulus-not summary{display:flex;flex-wrap:wrap;align-items:baseline;gap:var(--b-1) var(--b-3);
    min-height:var(--hedef);padding:var(--b-3) var(--b-4);cursor:pointer;list-style:none;
    font-size:var(--y-3);line-height:var(--sh-orta)}
  .kurulus-not summary::-webkit-details-marker{display:none}
  .kurulus-not summary::after{content:"+";margin-left:auto;flex:none;
    color:var(--kut);font-family:var(--mono);font-weight:700}
  .kurulus-not[open] summary::after{content:"-"}
  .ku-et{font-weight:700;color:var(--kut);white-space:nowrap}
  .ku-oz{color:var(--metin-2)}
  .kurulus-not p{margin:0;padding:0 var(--b-4) var(--b-3);
    font-size:var(--y-3);line-height:var(--sh-genis);color:var(--metin-2)}

  /* ---- Metnin altındaki bölümler ----
     Kurul oylamaları, şerhler, sistem içi atıflar ve paylaşım: dördü de
     çalışmanın kendisi değil, çevresindeki kayıttır. Aynı ayraçla
     başlar ve aynı başlık ölçüsünü kullanırlar. */
  .oyl,.serh,.atifbag,.pay{font-family:var(--ui);margin-top:var(--b-6);
    border-top:1px solid var(--cizgi);padding-top:var(--b-5)}
  .oyl h2,.serh h2,.atifbag h2,.pay h2{font-size:var(--y-6);margin:0 0 var(--b-1)}
  .oyl-ack,.serh-ack,.atifbag-ack,.pay-ack{font-size:var(--y-3);color:var(--metin-2);
    line-height:var(--sh-genis);margin:0 0 var(--b-4);max-width:66ch}

  /* ---- Kurul oylamaları ---- */
  .oyl-k{margin-bottom:var(--b-3)}
  .oyl-k > summary{display:flex;align-items:center;min-height:var(--hedef);
    cursor:pointer;font-size:var(--y-4);font-weight:600}
  .oyl-acik{border-left:3px solid var(--kut)}
  .oyl-kapali{border-left:3px solid var(--yesil)}
  .oyl-bas{display:flex;flex-wrap:wrap;gap:var(--b-3);align-items:baseline;margin-bottom:var(--b-1)}
  .oyl-bas b{font-size:var(--y-4)}
  .oyl-hedef{font-size:var(--y-3);color:var(--metin-2)}
  .oyl-rz{margin-left:auto}
  .oyl-acan{font-size:var(--y-2);color:var(--metin-2);margin-bottom:var(--b-2)}
  .oyl-ger{margin:0 0 var(--b-3);font-size:var(--y-4);line-height:var(--sh-genis);
    white-space:pre-wrap;word-break:break-word}
  .oyl-gizli{border-style:dashed}
  .oyl-sonuc{font-size:var(--y-4);line-height:var(--sh-genis)}
  .oyl-sonuc b{color:var(--yesil)}
  .oyl-oylar{margin-top:var(--b-3);display:grid;gap:var(--b-2)}
  .oyl-o{border-left:2px solid var(--cizgi);padding-left:var(--b-3)}
  .oyl-o-bas{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:baseline;
    font-size:var(--y-3);margin-bottom:var(--b-1)}
  .oyl-o-bas span:first-of-type{color:var(--kut);font-weight:600}
  .oyl-o-bas .ara-oto{color:var(--metin-2);font-size:var(--y-2)}
  .oyl-o p{white-space:pre-wrap}
  .oyl-form{margin-top:var(--b-3);border-top:1px solid var(--cizgi);padding-top:var(--b-3)}
  .oyl-alt,.serh-alt{display:flex;flex-wrap:wrap;gap:var(--b-3);align-items:center;margin-top:var(--b-3)}
  .oyl-say,.serh-say{font-size:var(--y-2);color:var(--metin-2)}
  .oyl-msj,.serh-sonuc{font-size:var(--y-3);line-height:var(--sh-genis);margin-top:var(--b-2)}
  .oyl-msj.iyi,.serh-sonuc.iyi{color:var(--yesil)}
  .oyl-msj.kotu,.serh-sonuc.kotu{color:var(--kirmizi)}
  .oyl-engel{margin-top:var(--b-3);font-size:var(--y-3);color:var(--metin-2)}
  .oyl-engel a{color:var(--kut)}
  .itiraz-ac{margin-top:var(--b-3)}
  @media(max-width:560px){ .oyl-rz{margin-left:0} }

  /* ---- Yayın sonrası şerh ---- */
  .serh-k{margin-bottom:var(--b-3)}
  .serh-bas{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:baseline;margin-bottom:var(--b-2)}
  .serh-bas b{font-size:var(--y-4)}
  .serh-ilgi.i-yazar{background:var(--kut-zemin);color:var(--kut);border-color:var(--kut-cizgi)}
  .serh-ilgi.i-hakem{background:var(--lacivert-zemin);color:var(--lacivert);border-color:var(--lacivert-cizgi)}
  .serh-bas .serh-t{margin-left:auto;font-size:var(--y-2);color:var(--metin-2)}
  .serh-kurum{display:block;font-size:var(--y-2);color:var(--metin-2);margin:0 0 var(--b-2)}
  .serh-m{margin:0;font-size:var(--y-4);line-height:var(--sh-genis);word-break:break-word}
  .serh-m p{margin:0 0 .6em}
  .serh-m p:last-child{margin-bottom:0}
  .serh-m ul,.serh-m ol{margin:.4em 0;padding-left:1.3em}
  .serh-yanit{margin-top:var(--b-3)}
  .serh-yanit .serh-bas{margin-bottom:var(--b-1)}
  .serh-perde b{color:var(--kut)}
  .serh-yok{font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-genis);margin:0 0 var(--b-4)}
  .serh-form{margin-top:var(--b-4)}
  .serh-uyari{margin:var(--b-2) 0 0;font-size:var(--y-2);color:var(--metin-2);
    line-height:var(--sh-orta);max-width:64ch}
  .serh-kapi{border-style:dashed;margin-top:var(--b-4)}
  .serh-kapi a{color:var(--kut);font-weight:600}
  .serh-islem{display:flex;flex-wrap:wrap;gap:var(--b-2);margin-top:var(--b-3)}
  .serh-kutu{margin-top:var(--b-3)}
  @media(max-width:560px){ .serh-bas .serh-t{margin-left:0;width:100%} }

  /* ---- Sistem içi atıflar ---- */
  .atifbag-b{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.08em;
    text-transform:uppercase;color:var(--metin-2);margin:var(--b-4) 0 var(--b-2)}
  .atifbag-l{list-style:none;margin:0;padding:0;display:grid;gap:var(--b-2)}
  .atifbag-l a{display:block;font-family:var(--serif);font-size:var(--y-5);
    line-height:var(--sh-orta);color:var(--metin)}
  .atifbag-l a:hover{color:var(--kut)}
  .atifbag-l span{display:block;font-size:var(--y-2);color:var(--metin-2);
    margin-top:var(--b-1);line-height:var(--sh-orta)}
  .atifbag-l code{font-size:var(--y-1)}

  /* ---- Paylaşım ----
     Önce her mecra kendi markasının rengiyle duruyordu; sekiz ayrı
     kurumsal renk yan yana gelince bölüm bir reklam şeridine
     benziyordu. Şimdi hepsi aynı düğme: işaret, kurumsal kimlikten
     gelen bir levhanın içinde durur; üzerine gelindiğinde levha altın
     rengine döner. Mecraların işaretleri kendi çizimleridir, yalnızca
     durdukları levha bizimdir. */
  /* ---- SİMGE DÜĞMELER ----
     BİLDİRİLEN KUSUR: "sosyal medya ya da diğerlerinin isminin
     gözükmesine gerek yok, çok yer kaplıyor; onun yerine simgeleri
     gözükse."

     Ölçüldü: on bir düğme, adlarıyla birlikte 1440 pikselde iki satır,
     820 pikselde dört satır kaplıyordu. Ad, düğmenin ne olduğunu
     söylemiyordu — X'in, WhatsApp'ın, Telegram'ın işareti zaten
     adından daha hızlı okunur.

     AD SİLİNMEDİ, GÖRSEL OLARAK GİZLENDİ. Ekran okuyucu yine "X'te
     paylaş" diyor, fare üzerine gelince ipucu çıkıyor, klavye odağı
     yine adı okuyor. Bir düğmeyi adsız bırakmak onu erişilemez yapardı;
     burada gizlenen ad değil, adın kapladığı yerdir.

     Dokunma hedefi küçülmedi: düğme 44x44'ün altına inmiyor (WCAG
     2.5.8), bunu wcag-kapi ölçüyor. */
  .pay-dg{display:flex;flex-wrap:wrap;gap:var(--b-2)}
  .pay-b{padding:0;min-width:var(--hedef);min-height:var(--hedef);
    display:inline-grid;place-items:center;border-radius:var(--r-3);
    transition:transform var(--gecis),box-shadow var(--gecis),border-color var(--gecis)}
  .pay-b > span{position:absolute;width:1px;height:1px;padding:0;margin:-1px;
    overflow:hidden;clip:rect(0 0 0 0);clip-path:inset(50%);white-space:nowrap;border:0}
  .pay-b svg{width:30px;height:30px;flex:none;padding:6px;border-radius:var(--r-2);
    background:var(--kut-zemin);color:var(--kut);fill:none;stroke:currentColor;stroke-width:1.8;
    stroke-linecap:round;stroke-linejoin:round;transition:background var(--gecis),color var(--gecis)}
  .pay-b:hover svg,.pay-b:focus-visible svg{background:var(--kut);color:var(--kut-dolu-metin)}
  /* Üzerine gelindiğinde düğme bir basamak yükselir. Hareket küçük ve
     tek yönlü; hareketi kapatmış olanda hiç olmaz (aşağıdaki kural). */
  .pay-b:hover{transform:translateY(-2px);box-shadow:var(--g-2)}
  /* Kendi işaretimizle çizilmiş düğmeler (paylaş, kopyala) dolu durur */
  .pay-yerli svg,.pay-yerli:hover svg{background:var(--marka-yuzey-2);color:var(--kut-dolu-metin)}

  /* ---- PAYLAŞIM BÖLÜMÜ KAPALI AÇILIR ----
     Bölüm sayfanın altında on bir düğmeyle duruyordu; okurun çoğu
     paylaşmıyor ve o alan her okurdan yer alıyordu. Şimdi tek bir
     satır: "Bu çalışmayı paylaş". Açılması bir tıklamadır.

     <details> seçildi, açılır pencere değil: pencere odağı hapseder,
     Esc'i, geri tuşunu ve yazdırmayı ayrı ayrı ele almayı gerektirir;
     burada gösterilecek şey bir düğme sırasıdır, bir görev değil.
     Betiksiz tarayıcıda da açılır. */
  .pay-kut{border:1px solid var(--cizgi);border-radius:var(--r-4);background:var(--yuzey);
    overflow:hidden}
  .pay-kut > summary{display:flex;align-items:center;gap:var(--b-3);cursor:pointer;
    list-style:none;min-height:var(--hedef);padding:var(--b-3) var(--b-4);
    font-size:var(--y-4);font-weight:700}
  .pay-kut > summary::-webkit-details-marker{display:none}
  .pay-kut > summary::after{content:"+";margin-left:auto;font-family:var(--mono);
    color:var(--kut);font-weight:700;font-size:var(--y-5);line-height:1}
  .pay-kut[open] > summary::after{content:"\2212"}
  .pay-kut > summary:hover{background:var(--yuzey-2)}
  .pay-kut-ic{padding:0 var(--b-4) var(--b-4)}
  .pay-ac{overflow:hidden;margin-top:var(--b-4);padding:0 var(--b-4) var(--b-4)}
  .pay-ac:not([open]){padding-bottom:0}
  .pay-ac summary{margin-inline:calc(-1 * var(--b-4));padding:var(--b-3) var(--b-4);
    min-height:var(--hedef);cursor:pointer;list-style:none;font-size:var(--y-3);font-weight:600}
  .pay-ac summary::-webkit-details-marker{display:none}
  .pay-ac summary::before{content:"+ ";color:var(--kut);font-family:var(--mono)}
  .pay-ac[open] summary::before{content:"- "}
  .pay-ac p{margin:0 0 var(--b-3);font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-genis)}
  .pay-onizle{margin-top:var(--b-5)}
  .pay-onizle span{display:block;font-size:var(--y-2);color:var(--metin-2);margin-bottom:var(--b-2)}
  .pay-onizle img{width:100%;max-width:520px;border:1px solid var(--cizgi);border-radius:var(--r-3)}

  /* ---- Atıf kutuları ---- */
  .atif{margin-top:var(--b-7)}
  /* Atıf metni olduğu gibi seçilebilsin diye satır sonları korunur;
     sağdaki boşluk kopyalama düğmesine ayrılmıştır. */
  .cite{position:relative;margin:var(--b-3) 0;padding-right:calc(var(--b-8) + var(--b-5));
    font-family:var(--ui);font-size:var(--y-2);white-space:pre-wrap;word-break:break-word}
  .cite b{display:block;color:var(--kut);font-size:var(--y-1);letter-spacing:.08em;
    text-transform:uppercase;margin-bottom:var(--b-1)}
  .kopya{position:absolute;top:var(--b-2);right:var(--b-2)}
  .arac{font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2);margin-top:var(--b-2)}

  .lisans{margin-top:var(--b-7);padding-top:var(--b-5);border-top:1px solid var(--cizgi);
    font-family:var(--ui);font-size:var(--y-3);color:var(--metin-2)}
  .lisans a{color:var(--kut)}
  .lisans .cc{font-weight:600;color:var(--metin)}

  /* ---- Künye pencereleri ---- */
  .ymodal-ov,.hmodal-ov{position:fixed;inset:0;z-index:400;display:none;overflow-y:auto;
    align-items:flex-start;justify-content:center;padding:var(--b-6) var(--b-3);
    background:color-mix(in srgb,var(--marka-koyu) 78%,transparent)}
  .ymodal-ov.acik,.hmodal-ov.acik{display:flex}
  .ymodal,.hmodal{width:100%;font-family:var(--ui)}
  .ymodal{max-width:460px}
  .hmodal{max-width:680px}
  .kapa{float:right;margin-left:var(--b-3)}
  .ymodal h3,.hmodal h3{margin:0 0 var(--b-1)}
  .hmodal h3{color:var(--kut)}
  .ymodal .unv{color:var(--metin-2);font-size:var(--y-3);margin-bottom:var(--b-3)}
  .ymodal .sat{margin:var(--b-2) 0;font-size:var(--y-3);word-break:break-word}
  .ymodal .sat a{color:var(--kut)}
  .ymodal .diger{margin-top:var(--b-4);padding-top:var(--b-3);border-top:1px solid var(--cizgi)}

  /* =====================================================================
     OKUMA MODU
     ---------------------------------------------------------------------
     Odak düğmesi kenar sütunlarını kaldırır ve sayfayı sıcak, koyu bir
     kâğıda çevirir. Biçim kuralları çoğaltılmaz: yalnızca belirteçler
     ezilir, sayfanın geri kalanı hiçbir şey bilmeden yeni değerlerle
     boyanır. Ton sıcaktır (uzun okumada mavi ışık yorar) ama kimliğin
     içindedir: vurgu yine altındır, lacivert çerçeve olduğu gibi kalır,
     çünkü marka belirteçleri ezilmez.

     Ölçüldü. Okunan yüzey --yuzey'dir (kabın zemini): metin 14,7:1,
     ikincil metin 9,1:1, altın vurgu 10,4:1. Sayfanın kendi zemininde
     metin 15,8:1. Kurallar yalnızca ekrana yazılır; kâğıda basarken
     sayfanın koyu kalması PDF'i kullanılamaz kılardı.
     ===================================================================== */
  @media screen{
    body.oku{
      --zemin:#17130e;    --yuzey:#211a12;   --yuzey-2:#1c1610;  --yuzey-3:#2b2318;
      --cizgi:#3a3022;    --cizgi-2:#4e4230;--metin:#f3ece0;    --metin-2:#c8bba6; --metin-3:#b7a992;
      --kut:#e9c579;      --kut-ac:#f3d79a;  --kut-zemin:#2b2213;
      --kut-cizgi:#5d4a20;--kut-dolu:#e9c579;--kut-dolu-metin:#171208;
      --lacivert:#a3c3f0; --lacivert-zemin:#182231; --lacivert-cizgi:#31496f;
      --yesil:#74d1a7;    --yesil-zemin:#14261c;    --yesil-cizgi:#275c41;
      --kirmizi:#f5a49c;  --kirmizi-zemin:#2e1a16;  --kirmizi-cizgi:#653430;
      --mor:#c4aef0;      --mor-zemin:#231b34;      --mor-cizgi:#443666;--dolu-metin:#171208;
      --g-1:0 1px 2px rgba(0,0,0,.5);--g-2:0 4px 14px rgba(0,0,0,.55);--g-3:0 14px 38px rgba(0,0,0,.65);
      /* Okuma sütunu olağandan dardır: kenar sütunları kalkınca satır
         uzunluğu okumaya göre yeniden kurulur. */
      --okuma-en:840px;line-height:2;color-scheme:dark;
    }
    body.oku .yan-sag{display:none}
    body.oku .mobil-bilgi{display:block}
    body.oku .sar::before{display:none}
    body.oku .sar{background:var(--yuzey);box-shadow:var(--g-3)}
    body.oku .govde{font-size:1.08em}
    body.oku .govde p{margin:1.25em 0;letter-spacing:.003em}
    body.oku .ozet{font-size:1.02em}
    @media(min-width:1024px){
      body.oku .duzen3{display:block;max-width:var(--okuma-en);margin-inline:auto}
    }
  }

  /* Ekranda görünmez, yalnızca baskıda çıkar */
  .baski{display:none}

  /* =====================================================================
     YAZDIR / PDF
     ---------------------------------------------------------------------
     Bu çıktı sistemin dışarıya verdiği tek basılı belgedir: bir okuyucu
     onu indirir, kütüphaneye koyar, bir başkasına yollar. Bu yüzden
     ekranın küçültülmüş hâli değil, kendi başına duran bir künyeli
     belge olarak kurulur.

     Üstte tamga ve künye levhası; her sayfanın altında kalıcı kimlik
     ve adres; sonda belgenin nereden, ne zaman alındığı. Sabit
     konumlu alt şerit her sayfada yinelenir.
     ===================================================================== */
  @page{ size:A4; margin:17mm 15mm 20mm; }
  @media print{
    .ust,.yan,.no-print,.arac-oku,.hmodal-ov,.ham-band,.kopya,.eylem,
    .yan-sag,.pay,.serh-d,.atla,#k-bildirim{display:none !important}
    html,body{background:#fff !important;color:#111 !important}
    body{font-size:10.5pt;line-height:1.55}
    .sahne{margin-left:0 !important;padding-top:0 !important}
    .sar{max-width:100%;margin:0;padding:0;background:#fff;border:0;box-shadow:none;
      font-size:10.5pt;line-height:1.55}
    .sar::before{display:none}
    .duzen3{display:block;background:none;border:0;box-shadow:none;padding:0;
      width:auto;max-width:100%}
    .icerik{max-width:100%}
    .baski{display:block}

    /* --- TAM SAYFA KAPAK ---
       Kapak bir süs değil, belgenin künyesidir. Basılı arşivde bir
       çalışmanın ilk sayfası tek başına dolaşır: fotokopi çekilir,
       bir dosyaya konur, birine verilir. O yaprağa bakan kişi bunun ne
       olduğunu, kimin yazdığını, ne zaman gönderildiğini, kimlerin
       kabul verdiğini ve nereden doğrulanacağını görebilmelidir.
       Metin ikinci sayfadan başlar. */
    .baski-kapak{page-break-after:always;break-after:page;
      min-height:calc(297mm - 37mm);display:flex;flex-direction:column}
    .baski-kapak .kpk-orta{flex:1 1 auto;display:flex;flex-direction:column;justify-content:center;
      padding:3mm 0}
    .baski-kapak .kpk-tamga{width:26mm;height:26mm;margin:0 0 6mm}
    .baski-kapak h1{font-size:21pt;line-height:1.22;margin:0 0 4mm;max-width:150mm}
    .kpk-baslik-ozgun{font-family:var(--ui);font-size:10pt;color:#5c6675;margin:0 0 5mm;line-height:1.45}
    .kpk-yazar{font-family:var(--ui);font-size:11pt;color:#111;line-height:1.6;margin:0 0 2mm}
    .kpk-kurum{font-family:var(--ui);font-size:8.8pt;color:#5c6675;line-height:1.55;margin:0 0 6mm}
    .kpk-ozet{font-size:9.6pt;line-height:1.6;color:#222;max-width:155mm;margin:0 0 6mm;
      border-left:1.5pt solid #9a6b12;padding-left:5mm}
    .kpk-ozet b{display:block;font-family:var(--ui);font-size:7.6pt;letter-spacing:.12em;
      text-transform:uppercase;color:#5c6675;margin-bottom:2mm}
    /* Atıf uyarısı: kapağın dili ne olursa olsun atıf özgün dile yapılır. */
    .kpk-atif{border:.6pt solid #cfd4dc;border-radius:2mm;padding:4mm 5mm;margin:0 0 5mm;
      font-family:var(--ui);font-size:8.4pt;color:#111;line-height:1.6;max-width:155mm}
    .kpk-atif b{display:block;font-size:7.6pt;letter-spacing:.12em;text-transform:uppercase;
      color:#5c6675;margin-bottom:2mm;font-weight:600}
    .kpk-atif code{font-family:var(--mono);font-size:8.2pt;word-break:break-all}
    .kpk-hakem{font-family:var(--ui);font-size:8.6pt;color:#111;line-height:1.7;margin:0 0 5mm}
    .kpk-hakem b{display:block;font-size:7.6pt;letter-spacing:.12em;text-transform:uppercase;
      color:#5c6675;margin-bottom:2mm;font-weight:600}
    .kpk-hakem span{display:block;color:#5c6675;font-size:8pt;word-break:break-all}
    .kpk-bilgi{max-width:155mm;margin:0 0 5mm;font-family:var(--ui)}
    .kpk-bilgi > b{display:block;font-size:7.6pt;letter-spacing:.12em;text-transform:uppercase;
      color:#5c6675;margin-bottom:2mm;font-weight:600}
    .kpk-bilgi dl{display:grid;grid-template-columns:1fr 1fr;gap:0 8mm;margin:0;
      border-top:.6pt solid #cfd4dc}
    .kpk-bilgi dl > div{display:flex;gap:3mm;justify-content:space-between;align-items:baseline;
      padding:.9mm 0;border-bottom:.6pt solid #e3e7ec;font-size:8.2pt;line-height:1.45;break-inside:avoid}
    .kpk-bilgi dl > .kpk-genis{grid-column:1/-1}
    .kpk-bilgi dt{color:#5c6675;flex:none}
    .kpk-bilgi dd{margin:0;color:#111;font-weight:600;text-align:right;word-break:break-word}
    .kpk-mini{width:4mm;height:4mm;vertical-align:-1mm;margin-right:1.5mm}
    /* Metin iki yana yaslanır: A4 sütunu geniş olduğu için kelime aralıkları
       ekrandaki dar sütundaki gibi açılmaz. */
    .icerik p,.icerik li,.kpk-ozet,.ozet p{text-align:justify;hyphens:auto;-webkit-hyphens:auto}
    .kpk-alt{flex:none;border-top:.6pt solid #cfd4dc;padding-top:3mm;
      font-family:var(--ui);font-size:7.8pt;color:#5c6675;line-height:1.6}

    /* --- Künye levhası --- */
    .baski-bas{display:flex;gap:10mm;align-items:flex-start;border-bottom:1.2pt solid #1b2a4a;
      padding-bottom:4mm;margin-bottom:6mm}
    .baski-bas img{width:16mm;height:16mm;flex:none}
    .baski-marka{font-family:var(--ui);line-height:1.35}
    .baski-marka b{display:block;font-size:13pt;letter-spacing:.02em;color:#1b2a4a}
    .baski-marka span{display:block;font-size:7.4pt;letter-spacing:.1em;text-transform:uppercase;color:#5c6675}
    .baski-rozet{margin-left:auto;text-align:right;font-family:var(--ui);font-size:8pt;color:#5c6675}
    .baski-rozet b{display:block;font-size:8.6pt;color:#9a6b12;letter-spacing:.06em;text-transform:uppercase}

    /* --- Künye çizelgesi --- */
    .baski-kunye{width:100%;border-collapse:collapse;font-family:var(--ui);font-size:8.6pt;
      margin:5mm 0 7mm;border-top:.5pt solid #cfd4dc;border-bottom:.5pt solid #cfd4dc}
    .baski-kunye th,.baski-kunye td{text-align:left;vertical-align:top;padding:1.5mm 3mm 1.5mm 0;
      border-bottom:.4pt solid #e6e8ec}
    .baski-kunye tr:last-child th,.baski-kunye tr:last-child td{border-bottom:0}
    .baski-kunye th{width:34mm;font-weight:600;color:#5c6675;white-space:nowrap}
    .baski-kunye td{color:#111;word-break:break-word}
    .baski-kunye .bk-bek{color:#8a7433;font-style:italic}

    /* --- Şerit ---
       Bir süre burada sabit konumlu bir alt şerit vardı; her sayfanın
       altında yinelensin diye. Tarayıcılar sabit ögeyi baskıda sayfa
       kutusuna göre değil içerik akışına göre yerleştirdiği için şerit
       ikinci sayfadan sonra metnin üstüne biniyordu. Kimlik zaten
       belgenin başındaki künyede ve sonundaki kayıtta yazılı; yanlış
       yere basılmış bir şeritten iyidir. */
    .baski-serit{display:none !important}

    /* --- Belgenin sonu --- */
    .baski-son{margin-top:8mm;border-top:.5pt solid #cfd4dc;padding-top:3mm;
      font-family:var(--ui);font-size:8.2pt;color:#5c6675;line-height:1.6}
    .baski-son b{color:#111}

    /* --- Metin --- */
    h1{color:#111;font-size:18pt;line-height:1.25;margin:0 0 3mm}
    h2{color:#1b2a4a;border-color:#cfd4dc;font-size:12pt;margin-top:7mm;page-break-after:avoid}
    /* Kâğıtta da yazarın başlığı belge başlığıdır; sayfanın etiketi
       değil. Ekranla aynı ayrım, aynı gerekçe. */
    .govde h2{font-family:var(--serif);font-size:13pt;text-transform:none;
      letter-spacing:normal;border:0;margin:8mm 0 2mm;page-break-after:avoid}
    .govde h3{font-family:var(--serif);font-size:11.5pt;text-transform:none;
      letter-spacing:normal;margin:6mm 0 1.5mm;page-break-after:avoid}
    h3{page-break-after:avoid}
    p,li{orphans:3;widows:3}
    a{color:#111;text-decoration:none;border-bottom:.4pt dotted #999}
    .rozet{display:none}
    /* Kâğıtta bütün kutular beyaz zemine oturur: okuma modu açıkken
       yazdırılan bir belge yoksa koyu zeminle çıkardı. */
    .ozet,.hakem-kutu,.cite,.serh,.kart,.kutu,.vurgu-kutu{
      border-color:#cfd4dc;background:#fff;color:#111;page-break-inside:avoid}
    .meta,.anahtar,.kaynakca .kref{color:#444}
    .kaynakca a.kref{display:none}
    /* Ekranda işe yarayan ama kâğıtta anlamı olmayan küçük ögeler */
    .hakem-kisi,.serh-d,.serh-d-uyari,.yazar-ac[aria-expanded],.ys-blok,
    .hakem-surum-dg,.kutu-ac,.tik-dg{display:none !important}
    details.pay-ac,summary{display:none !important}
    .kaynakca p{page-break-inside:avoid}
    /* Basılı kaynakçada bağlantı adresi görünmelidir: kâğıtta bir
       bağlantıya tıklanamaz, adres yazılı değilse kaynak izlenemez. */
    .kaynakca a[href^="https://doi.org/"]{border:0}
    .govde th,.govde td{border-color:#999}
    .govde figure,.govde table,.hakem-kutu{page-break-inside:avoid}
    .atif{page-break-inside:avoid}
  }
</style>
</head>
<body>
<a class="atla" href="#ana"><?= $L ? 'Skip to content' : 'İçeriğe geç' ?></a>
<?php
/* Kabuk artık ortaktır: sol lacivert gezinme sütunu ve üst çubuk
   bütün sayfalarda aynı yerden gelir. Çalışma sayfasına özgü olan tek
   şey okuma araçlarıdır (punto ve odak); onlar çubuğa ek araç olarak
   verilir. Böylece iki ayrı gezinme bakımı yapılmaz. */
ob_start(); ?>
<span class="arac-oku">
  <button class="d d-kucuk" type="button" id="yaziKucult" title="<?= $L ? 'Smaller text' : 'Yazıyı küçült' ?>" aria-label="<?= $L ? 'Smaller text' : 'Yazıyı küçült' ?>">A-</button>
  <button class="d d-kucuk" type="button" id="yaziBuyut" title="<?= $L ? 'Larger text' : 'Yazıyı büyüt' ?>" aria-label="<?= $L ? 'Larger text' : 'Yazıyı büyüt' ?>">A+</button>
  <button class="d d-kucuk" type="button" id="yaslaModu" aria-pressed="false" title="<?= $L ? 'Justify text' : 'Metni iki yana yasla' ?>" aria-label="<?= $L ? 'Justify text' : 'Metni iki yana yasla' ?>"><?= $L ? 'Justify' : 'Yasla' ?></button>
  <button class="d d-kucuk" type="button" id="okuModu" aria-pressed="false" title="<?= $L ? 'Reading mode' : 'Okuma modu' ?>"><?= $L ? 'Focus' : 'Odak' ?></button>
</span>
<?php
$okuArac = (string)ob_get_clean();
k_kabuk_yan('/yazilar.php', $MARKA, tg_marka_alt((bool)$L), (bool)$L, $L ? 'en' : 'tr', $okuArac, $baslik);
?>
<div class="sahne">
<main class="sar kap" id="ana">
<?php /* ---------------------------------------------------------------
     İKİ RAY TEKE İNDİ — 19 Ağustos 2026, kurul bildirimi:
     "altta sidebar sağda da iki tane sidebar var, çok kullanışsız."

     ÖLÇÜLDÜ VE DOĞRUYDU. 1900 pikselde sayfada aynı anda üç kenar
     sütunu duruyordu: site menüsü 250, içindekiler rayı 200, sağ ray
     320 — toplam 770 piksel; metin sütunu 876. Yani okuma sayfasının
     neredeyse yarısı, okunacak şeyin dışındaki şeylere ayrılmıştı.
     İçindekiler rayı ayrıca en zayıf olanıydı: beş başlıklı bir
     çalışmada içeriği 129 piksel, ayırdığı sütun 200 piksel.

     İçindekiler artık sağ rayın İLK BÖLÜMÜ. Kazanılan şey yalnız
     genişlik değil, SAYIdır: metnin yanında iki değil bir sütun kalır
     ve göz metinden çıkıp bir yere bakar, iki yere değil.

     Rayın kendisi de tek bir kutu oldu. Dört ayrı kutu, üstelik
     aralarında boşlukla, "iki tane sidebar" duygusunun kaynağıydı:
     ayrı kutular ayrı bölmeler gibi okunuyordu. Şimdi tek çerçeve,
     içinde çizgiyle ayrılmış bölümler.
     --------------------------------------------------------------- */ ?>
<div class="duzen3">
  <div class="icerik">
  <?php
  /* ---- Basılı belgenin künyesi ----
     Yalnızca yazdırmada görünür. Ekranda aynı bilgiler zaten üstbilgi
     ve künye satırında var; kâğıtta ise belge kendi başına kalır ve
     nereden geldiğini kendi üstünde taşımak zorundadır. */
  $bsHakem = 0; $bsKarar = [];
  foreach ($hakemler as $bh) {
      if (!is_array($bh) || trim((string)($bh['rapor'] ?? '')) === '') continue;
      $bsHakem++;
      $bk = (string)($bh['karar'] ?? '');
      if ($bk !== '') $bsKarar[$bk] = ($bsKarar[$bk] ?? 0) + 1;
  }
  $bsKod = function_exists('al_kayit_kodlari') ? al_kayit_kodlari($yazi) : [];
  ?>
  <?php
  /* ================= TAM SAYFA KAPAK (yalnızca baskıda) =================
     Kapağın dili, indirilen belgenin dilidir. Ama tamga ve atıf her
     zaman ÖZGÜN dile gider: kayıt yazarın yazdığı dildeki metindir ve
     çeviri onun yerine geçmez. İngilizce bir çıktı indiren kişi de
     kaynakçasına çalışmanın kendi dilindeki kaydını yazar.
     ===================================================================== */
  $bsTarih   = tg_yazi_tarihleri($yazi);
  $bsKabulEd = tg_kabul_edenler($yazi);
  $bsOzgunKod = tg_yazi_dili($yazi);
  $bsOzgunAd  = $bsOzgunKod !== '' ? tg_dil_adi($bsOzgunKod) : '';
  /* Özgün dildeki kaydın adresi. Arayüz dili ne olursa olsun atıf
     buraya yapılır. */
  $bsOzgunUrl = $atifUrl;
  $bsGorunen  = tg_gorunen_dil($yazi, (bool)$L);
  $bsCeviriMi = $bsOzgunKod !== '' && $bsGorunen !== $bsOzgunKod;
  $bsGun = function (string $t) use ($L): string {
      return $t === '' ? '' : (function_exists('hs_tarih_yaz') ? hs_tarih_yaz($t, (bool)$L) : $t);
  };
  ?>
  <div class="baski baski-kapak">
    <div class="baski-bas" style="border-bottom:0;margin-bottom:0;padding-bottom:2mm">
      <img src="/k/tamga-512.png" alt="" width="64" height="64">
      <span class="baski-marka">
        <b><?= esc($MARKA) ?></b>
        <span><?= esc(tg_marka_alt((bool)$L)) ?></span>
      </span>
      <span class="baski-rozet">
        <?php /* Kâğıda geçen etiket en uzun yaşayanıdır: çıktı elden ele
                 dolaşırken sayfadaki düzeltme onu bulmaz. Bu yüzden burada
                 da yol değil aşama yazılır. */ ?>
        <b><?= esc(tg_asama_metni($asama, (bool)$L)) ?></b>
        <?= $L ? 'Open access · CC BY 4.0' : 'Açık erişim · CC BY 4.0' ?>
      </span>
    </div>

    <div class="kpk-orta">
      <h1><?= esc($baslik) ?></h1>
      <?php if ($bsCeviriMi && trim((string)($yazi['baslik'] ?? '')) !== '' && trim((string)($yazi['baslik'] ?? '')) !== $baslik): ?>
      <p class="kpk-baslik-ozgun"><?= $L ? 'Original title' : 'Özgün başlık' ?><?= $bsOzgunAd !== '' ? ' (' . esc($bsOzgunAd) . ')' : '' ?>:
        <?= esc((string)$yazi['baslik']) ?></p>
      <?php endif; ?>

      <?php $bsYazarlar = tg_yazar_kayitlari($yazi); if ($bsYazarlar): ?>
      <p class="kpk-yazar"><?= esc(implode(', ', array_values(array_filter(array_map(
          fn($ya) => trim(tg_metin(($ya['unvan'] ?? '') . ' ' . ($ya['ad'] ?? ''))), $bsYazarlar))))) ?></p>
      <?php
        $bsKurum = [];
        foreach ($bsYazarlar as $ya) {
            $kk = trim(tg_metin($ya['kurum'] ?? ''));
            if ($kk !== '' && !in_array($kk, $bsKurum, true)) $bsKurum[] = $kk;
        }
        if ($bsKurum): ?>
      <p class="kpk-kurum"><?= esc(implode(' · ', $bsKurum)) ?></p>
      <?php endif; endif; ?>

      <?php if (trim(strip_tags($ozet)) !== ''): ?>
      <div class="kpk-ozet"><b><?= $L ? 'Abstract' : 'Özet' ?></b><?= esc(mb_substr(trim(strip_tags($ozet)), 0, 1200, 'UTF-8')) ?></div>
      <?php endif; ?>

      <?php /* MAKALE BİLGİSİ. Sayfadaki "Makale bilgisi" bloğunun kâğıttaki
               karşılığı: aşama, erişim, lisans, tarih ve tamga aynı
               satırlarla durur. Tamga burada büyük bir resim değil,
               künyenin bir satırıdır; küçük işareti satırın başındadır. */ ?>
      <div class="kpk-bilgi">
        <b><?= $L ? 'Article info' : 'Makale bilgisi' ?></b>
        <dl>
          <div><dt><?= $L ? 'Review' : 'Değerlendirme' ?></dt><dd><?= esc(tg_asama_metni($asama, (bool)$L)) ?></dd></div>
          <div><dt><?= $L ? 'Access' : 'Erişim' ?></dt><dd><?= $L ? 'Open Access' : 'Açık Erişim' ?></dd></div>
          <div><dt><?= $L ? 'License' : 'Lisans' ?></dt><dd>CC BY 4.0</dd></div>
          <div><dt><?= $L ? 'Published' : 'Yayın tarihi' ?></dt><dd><?= esc($bsGun($bsTarih['yayin'])) ?></dd></div>
          <div><dt><?= esc($TAMGA_AD) ?></dt><dd><img class="kpk-mini" src="/k/tamga-512.png" alt="" width="16" height="16"><?= $bcid !== '' ? esc($bcid) : ($L ? 'not assigned' : 'verilmemiş') ?></dd></div>
          <?php if ($doi !== ''): ?><div><dt>DOI</dt><dd><?= esc($doi) ?></dd></div><?php endif; ?>
          <?php if ($bsOzgunAd !== ''): ?><div><dt><?= $L ? 'Language' : 'Kayıt dili' ?></dt><dd><?= esc($bsOzgunAd) ?></dd></div><?php endif; ?>
          <?php if ($bsKod): ?><div class="kpk-genis"><dt><?= $L ? 'Field' : 'Bilim alanı' ?></dt><dd><?= esc(implode(' · ', array_map(fn($c) => al_yol($c, (bool)$L), $bsKod))) ?></dd></div><?php endif; ?>
          <div><dt><?= $L ? 'Ethics approval' : 'Etik kurul' ?></dt><dd><?= esc(tg_etik_hal_ad($etikHal, (bool)$L)) ?></dd></div>
          <?php if (($veri['beyan'] ?? '') !== ''): ?><div><dt><?= $L ? 'Data and code' : 'Veri ve kod' ?></dt><dd><?= esc(tg_veri_beyan_ad((string)$veri['beyan'], $L)) ?></dd></div><?php endif; ?>
          <div><dt><?= $L ? 'Reviewers' : 'Hakem' ?></dt><dd><?= (int)$bsHakem ?></dd></div>
        </dl>
      </div>

      <div class="kpk-atif">
        <b><?= $L ? 'How to cite' : 'Atıf' ?></b>
        <?= $L
          ? 'Cite the record in the language the work was written in. A translation does not take the place of the record; the permanent identifier below always resolves to the original.'
          : 'Atıf, çalışmanın yazıldığı dildeki kayda yapılır. Çeviri kaydın yerine geçmez; aşağıdaki kalıcı kimlik her zaman özgün dildeki kayda götürür.' ?>
        <?php if ($bsOzgunAd !== ''): ?><br><?= $L ? 'Language of record' : 'Kayıt dili' ?>: <?= esc($bsOzgunAd) ?><?php endif; ?>
        <?php if ($bsCeviriMi): ?><br><?= $L
          ? 'This copy is a translation. It is not the record.'
          : 'Elinizdeki çıktı bir çeviridir. Kayıt bu değildir.' ?><?php endif; ?>
        <br><code><?= esc($bsOzgunUrl) ?></code>
      </div>

      <?php if ($bsKabulEd): ?>
      <div class="kpk-hakem">
        <b><?= $L ? 'Reviewers who accepted' : 'Kabul veren hakemler' ?></b>
        <?php foreach ($bsKabulEd as $kh): ?>
          <?= esc($kh['ad']) ?><?= $kh['tarih'] !== '' ? ' · ' . esc($bsGun($kh['tarih'])) : '' ?><?php
            ?><?= $kh['bagimsiz'] ? ' · ' . ($L ? 'independent' : 'bağımsız') : '' ?><br>
        <?php endforeach; ?>
        <span><?= $L ? 'All reports, in full: ' : 'Raporların tamamı: ' ?><?= esc($KOK . '/rapor.php?y=' . rawurlencode($slugU)) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <div class="kpk-alt">
      <?php if ($bsTarih['gonderim'] !== ''): ?><?= $L ? 'Submitted' : 'Gönderim' ?>: <?= esc($bsGun($bsTarih['gonderim'])) ?> &nbsp;·&nbsp; <?php endif; ?>
      <?php if ($bsTarih['ilk'] !== ''): ?><?= $L ? 'First sent for review' : 'İlk değerlendirmeye alınış' ?>: <?= esc($bsGun($bsTarih['ilk'])) ?> &nbsp;·&nbsp; <?php endif; ?>
      <?php if ($bsTarih['kabul'] !== ''): ?><?= $L ? 'Accepted' : 'Kabul' ?>: <?= esc($bsGun($bsTarih['kabul'])) ?> &nbsp;·&nbsp; <?php endif; ?>
      <?= $L ? 'Published' : 'Yayım' ?>: <?= esc($bsGun($bsTarih['yayin'])) ?>
      <br><?= esc($TAMGA_AD) ?>: <?= $bcid !== '' ? esc($bcid) : ($L ? 'not assigned' : 'verilmemiş') ?>
      <?php if ($doi !== ''): ?> &nbsp;·&nbsp; DOI: <?= esc($doi) ?><?php endif; ?>
      &nbsp;·&nbsp; <?= $L ? 'Licence' : 'Lisans' ?>: CC BY 4.0
      &nbsp;·&nbsp; <?= $L ? 'Downloaded' : 'İndirilme' ?>: <span data-indirme><?= esc($bsGun(date('Y-m-d'))) ?></span>
    </div>
    <?php /* İNDİRİLME TARİHİ yazdırma anında, okurun kendi saatiyle yazılır;
             sunucudaki tarih yalnızca betik çalışmazsa kalan yedektir. */ ?>
    <script>
    (function(){
      var L = <?= $L ? 'true' : 'false' ?>;
      function yaz(){
        var d = new Date(), el = document.querySelectorAll('[data-indirme]');
        var s; try { s = d.toLocaleString(L ? 'en-GB' : 'tr-TR', {day:'numeric', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit'}); }
               catch (e) { s = d.toISOString().slice(0, 16).replace('T', ' '); }
        for (var i = 0; i < el.length; i++) el[i].textContent = s;
      }
      window.addEventListener('beforeprint', yaz);
      yaz();
    })();
    </script>
  </div>

  <div class="baski baski-bas">
    <img src="/k/tamga-512.png" alt="" width="64" height="64">
    <span class="baski-marka">
      <b><?= esc($MARKA) ?></b>
      <span><?= esc(tg_marka_alt((bool)$L)) ?></span>
    </span>
    <span class="baski-rozet">
      <b><?= esc(tg_asama_metni($asama, (bool)$L)) ?></b>
      <?= $L ? 'Open access · CC BY 4.0' : 'Açık erişim · CC BY 4.0' ?>
    </span>
  </div>

  <table class="baski baski-kunye">
    <tr><th><?= esc($TAMGA_AD) ?></th>
        <td><?= $bcid !== '' ? esc($bcid) . '<br>' . esc($nekiUrl) : '<span class="bk-bek">' . ($L ? 'not assigned' : 'verilmemiş') . '</span>' ?></td></tr>
    <tr><th>DOI</th>
        <td><?php if ($doi !== ''): ?>https://doi.org/<?= esc($doi) ?><?php else: ?>
          <span class="bk-bek"><?= $L
            ? 'To be assigned. Until then the identifier above is the permanent reference for this work.'
            : 'Verilecek. O güne kadar yukarıdaki kalıcı kimlik bu çalışmanın değişmez göndermesidir.' ?></span>
        <?php endif; ?></td></tr>
    <tr><th><?= $L ? 'Published' : 'Yayım tarihi' ?></th><td><?= esc($tarih) ?></td></tr>
    <?php /* Çalışmanın dili künyeye girer: kayıt yazarın yazdığı dildeki
             metindir ve bunun belgede yazılı olması gerekir. */
    $yaziDil = tg_yazi_dili($yazi); if ($yaziDil !== ''): ?>
    <tr><th><?= $L ? 'Language of record' : 'Kayıt dili' ?></th><td><?= esc(tg_dil_adi($yaziDil)) ?></td></tr>
    <?php endif; ?>
    <?php if ($yayin !== ''): ?>
    <tr><th><?= $L ? 'Published in' : 'Yayın' ?></th><td><?= esc($yayin) ?></td></tr>
    <?php endif; ?>
    <?php if ($bsKod): ?>
    <tr><th><?= $L ? 'Field' : 'Bilim alanı' ?></th>
        <td><?= esc(implode(' · ', array_map(fn($c) => al_yol($c, (bool)$L), $bsKod))) ?></td></tr>
    <?php endif; ?>
    <?php if ($anahtar !== ''): ?>
    <tr><th><?= $L ? 'Keywords' : 'Anahtar kelimeler' ?></th><td><?= esc($anahtar) ?></td></tr>
    <?php endif; ?>
    <?php if ($hakemGormus): ?>
    <tr><th><?= $L ? 'Review' : 'Değerlendirme' ?></th>
        <td><?php
          if ($bsHakem === 0) echo $L ? 'No report yet' : 'Henüz rapor yok';
          else {
              $par = [];
              foreach ($bsKarar as $bk => $bn) $par[] = $bn . ' ' . tg_karar_ad($bk);
              echo esc($bsHakem . ' ' . ($L ? 'reports' : 'rapor') . ($par ? ' (' . implode(', ', $par) . ')' : ''));
          }
        ?></td></tr>
    <?php endif; ?>
    <tr><th><?= $L ? 'Licence' : 'Lisans' ?></th>
        <td>Creative Commons <?= $L ? 'Attribution' : 'Atıf' ?> 4.0 (CC BY 4.0)</td></tr>
    <tr><th><?= $L ? 'Permanent address' : 'Kalıcı adres' ?></th><td><?= esc($atifUrl) ?></td></tr>
  </table>

  <div class="baski baski-serit">
    <span><?= esc($MARKA) ?><?= $bcid !== '' ? ' · ' . esc($bcid) : '' ?></span>
    <span><?= esc(preg_replace('#^https?://#', '', $atifUrl)) ?></span>
  </div>

  <div class="rozet">
    <?php /* Başlığın hemen üstündeki ilk rozet, okurun çalışma hakkında
             gördüğü ilk yargıdır. "Hakemli Makale" yazan bir rozet,
             raporsuz bir çalışmada okura tutulmamış bir söz verir; onun
             yerine aşamanın kendisi yazılır ve rengi de aşamadan gelir. */ ?>
    <span class="rz <?= esc(tg_asama_rz($asama)) ?>"><?= esc(tg_asama_metni($asama, (bool)$L)) ?></span>
    <span class="rz rz-kut">Open Access</span>
    <span class="rz rz-cizgi">CC BY 4.0</span>
    <?php /* Onaylı aşamada etiketi ikinci kez yazmak yerine yalnızca dayanağı
             verilir: rozet "kaç olumlu raporla" sorusunu da yanıtlasın. */ ?>
    <?php if ($asama === 'onayli'): ?><span class="rz rz-yes"><?= (int)$onay['kabul'] ?> <?= $L ? 'positive reports' : 'olumlu rapor' ?></span><?php endif; ?>
  </div>
  <?php if ($geriCekildi): ?>
  <div class="ret-buyuk" role="alert">
    <span class="ret-b"><?= $L ? 'THIS WORK HAS BEEN RETRACTED' : 'BU ÇALIŞMA GERİ ÇEKİLMİŞTİR' ?></span>
    <span class="ret-a"><?= $L
      ? 'The text remains accessible so that the record stays complete, but its findings should not be relied upon. The reason and the date are given below.'
      : 'Kayıt eksilmesin diye metne erişim açık tutulmaktadır; ancak bulgularına dayanılmamalıdır. Gerekçe ve tarih aşağıda verilmiştir.' ?></span>
  </div>
  <?php endif; ?>
  <?php /* BAŞLIK VE DİLİ. Çeviri gösteriliyorsa özgün başlık da durur;
           başlığın dili sayfanın dilinden başkaysa yazılır. Karar
           ortak.php'de tek yerde (tg_baslik_bilgi). */
     $bbY = tg_baslik_bilgi($yazi, (bool)$L); ?>
  <h1 lang="<?= esc($bbY['dil'] !== '' ? $bbY['dil'] : 'tr') ?>"><?= esc($baslik) ?><?php
    if ($bbY['im'] && $bbY['dil_ad'] !== ''): ?> <span class="rz rz-cizgi bs-dil" lang="<?= esc($bbY['dil']) ?>"><?= esc($bbY['dil_ad']) ?></span><?php endif; ?></h1>
  <?php if ($bbY['ozgun'] !== '' && $bbY['ozgun'] !== $baslik): ?>
  <p class="bs-ozgun" lang="<?= esc($bbY['ozgun_dil']) ?>"><span><?= esc($L ? 'Original title' : 'Özgün başlık') ?><?= $bbY['ozgun_ad'] !== '' ? ' (' . esc($bbY['ozgun_ad']) . ')' : '' ?>:</span> <?= esc($bbY['ozgun']) ?></p>
  <?php endif; ?>
  <?php /* ÇEVİRİNİN KAYNAĞI OKURA YAZILIR.
           İlkeler sayfası "çeviren kendi adıyla kayda geçer" diyordu ama
           sayfa hiçbir şey söylemiyordu: yazarın kendi yazdığı metin, bir
           insanın çevirdiği metin ve bir makinenin ürettiği metin aynı
           görünüyordu. Cümle burada yazılmaz, KAYITTAN üretilir
           (ortak.php, tg_ceviri_kaynak_metni); aynı cümle PDF kapağında
           da geçer ve iki yerde ayrı yazılmadığı için ayrışamaz.
           Onaysız makine çevirisi ayrıca uyarı rengini alır: bu, kaydın
           kendisi değildir ve okurun bunu fark etmeden geçmesi
           istenmez. */
     $cMetin = $cGoster ? tg_ceviri_kaynak_metni($cGoster, (bool)$L) : '';
     if ($cMetin !== ''): ?>
  <p class="cv-kaynak<?= $cYayin ? '' : ' cv-uyari' ?>"><?= esc($cMetin) ?>
    <a href="<?= esc($url . '&lang=' . esc($ozgunDil)) ?>"><?= $L ? 'Go to the record' : 'Kayda git' ?></a></p>
  <?php endif; ?>
  <div class="meta"><?php foreach ($yazarListe as $yi => $ya): ?><?php if ($yi): ?><span class="yzayr">, </span><?php endif; ?><button class="yazar-ac" type="button" data-yazarpop data-yi="<?= $yi ?>"><?= esc(bh_unvanli($ya['unvan'] ?? '', $ya['ad'] ?? '')) ?></button><?php endforeach; ?> · <?= esc($tarih) ?><?php if ($yayin): ?> · <?= esc($yayin) ?><?php endif; ?><?php if ($nekiTam): ?> · <abbr class="neki" title="<?= esc($L ? 'Permanent identifier' : 'Kalıcı kimlik') ?>"><?= esc($TAMGA_AD) ?></abbr>: <a class="yazar-l" href="<?= esc($nekiUrl) ?>"><?= esc($nekiTam) ?></a><?php endif; ?><?php if ($doi !== ''): ?> · DOI: <a class="yazar-l" href="https://doi.org/<?= esc($doi) ?>" target="_blank" rel="noopener"><?= esc($doi) ?></a><?php endif; ?></div>
  <?php /* Listeye alma. Beğeni burada bir onay oyu değil bir yer imidir:
           "bunu listeme alıyorum, başına bir şey gelirse haberim olsun".
           Hiçbir sıralamayı ve hiçbir onay sayımını etkilemez. Kimin
           beğendiği açık edilmez; yalnızca toplam görünür. */ ?>
  <?php /* "LİSTEME EKLE" BURADAN KALKTI, SAĞ RAYA GİTTİ.
           Bildirilen kusur: "listemde kısmının orada olmaması gerek."
           Doğru: bu düğme başlık ile metnin ARASINDA duruyordu ve
           okurun ilk işi metne girmektir. Yer imi koymak, okuduktan
           sonra ya da okurken verilen bir karardır; okumanın önüne
           konacak bir şey değil. Sağ rayda, PDF ve atıf düğmelerinin
           yanında duruyor: hepsi "bu çalışmayla ne yapacağım"
           sorusunun yanıtları. Dar ekranda ray metnin altına düştüğü
           için orada da erişilebilir kalır. */ ?>

  <div class="mobil-bilgi no-print">
    <?php if ($bcid !== ''): ?>
    <div class="mb-muhur">
      <?= mh_muhur($bcid, 44) ?>
      <span class="mb-muhur-kod"><?= esc($bcid) ?></span>
    </div>
    <?php endif; ?>
    <?php if (($okuSay['tekil'] ?? 0) > 0): ?><div class="mb-oku">👁 <?= (int)$okuSay['tekil'] ?> <?= $L ? 'readers' : 'okuyucu' ?></div><?php endif; ?>
    <div class="eylem d-kume">
      <button type="button" class="d d-ikinci" onclick="window.print()"><?= $L ? 'Download PDF' : 'PDF İndir' ?></button>
      <a class="d d-ikinci" href="#atif"><?= $L ? 'Cite' : 'Kaynak Göster' ?></a>
    </div>
  </div>

  <?php /* Kayıtlar ve uyarılar başlıktan SONRA gelir: okuyucu önce hangi
           çalışmaya baktığını görmeli, sonra o çalışmaya ilişkin uyarıyı
           okumalıdır. Tek istisna geri çekmedir; o, başlığın üstündedir. */ ?>
  <?php if (tg_kurulus_istisnasi($yazi)): ?>
  <?php /* Kayıt açık kalır ama sayfayı kaplamaz: tek satırlık bir not,
           gerekçesi isteyene açılan bir katlamada. Bilgi eksilmiyor,
           yalnızca çalışmanın önüne geçmiyor. */ ?>
  <details class="kurulus-not kutu kutu-kut">
    <summary>
      <span class="ku-et"><?= $L ? 'Founding period record' : 'Kuruluş dönemi kaydı' ?></span>
      <span class="ku-oz"><?= $L
        ? 'Published while the system was being tested; it does not meet every rule in force today.'
        : 'Sistem denenirken yayımlandı; bugünkü kuralların tamamını karşılamaz.' ?></span>
    </summary>
    <p><?= $L
      ? 'This work was published while the system was being built, in order to test whether its rules actually worked; the review process was carried out under conditions that were still being tried out. The exception is stated here rather than corrected after the fact, because amending a published record retrospectively, or removing it quietly, would damage the record itself. The work remains in the archive together with this note, and it is not offered as an example of the process described in the editorial policies.'
      : 'Bu çalışma, sistem kurulurken kuralların gerçekten işleyip işlemediğini görmek için deneme sırasında yayımlanmıştır; değerlendirme süreci, henüz denenmekte olan koşullar altında yürütülmüştür. İstisna sonradan düzeltilmek yerine burada yazılmaktadır; çünkü yayımlanmış bir kaydı geriye dönük değiştirmek ya da sessizce kaldırmak, kaydın kendisini bozar. Çalışma bu notla birlikte arşivde kalır ve yayın ilkelerinde anlatılan sürecin örneği olarak alınmamalıdır.' ?></p>
  </details>
  <?php endif; ?>
  <?php foreach ($kayitlar as $ky): $kt = (string)($ky['tur'] ?? ''); ?>
  <div class="kayit kutu <?= $kt === 'geri_cekme' ? 'kutu-kir' : 'kutu-kut' ?> kayit-<?= esc($kt) ?>" role="note">
    <div class="kayit-bas">
      <b><?= esc(tg_kayit_ad($kt, $L)) ?></b>
      <?php if (!empty($ky['tarih'])): ?><span><?= esc(tg_zaman((string)$ky['tarih'])) ?></span><?php endif; ?>
      <?php if (!empty($ky['karar_veren'])): ?><span><?= esc($ky['karar_veren']) ?></span><?php endif; ?>
    </div>
    <p><?= nl2br(esc((string)($ky[$L && !empty($ky['metin_en']) ? 'metin_en' : 'metin'] ?? ''))) ?></p>
  </div>
  <?php endforeach; ?>
  <?php if ($hakemYolda && !$onay['onayli'] && $onay['neden'] === 'bagimsiz'): ?>
  <div class="ham-band kutu"><?= esc(tg_onay_neden_metin('bagimsiz', $L)) ?></div>
  <?php endif; ?>
  <?php if ($etikAskida): ?>
  <div class="askida kutu kutu-kir" role="note">
    <b><?= $L ? 'This work is suspended.' : 'Bu çalışma askıya alınmıştır.' ?></b>
    <p><?= $L
      ? 'The author declared that ethics committee approval is required for this study, but did not state which committee gave it, on what date or under what number. Access to the text remains open; however, until that declaration is made, the findings should be read with this deficiency in mind.'
      : 'Yazar, bu çalışma için etik kurul izninin gerekli olduğunu beyan etmiş; ancak izni hangi kurulun, hangi tarihte ve hangi kararla verdiğini bildirmemiştir. Metne erişim açık tutulmaktadır; bununla birlikte bu beyan yapılana kadar bulgular bu eksiklik göz önünde bulundurularak okunmalıdır.' ?></p>
    <p class="askida-not"><?= $L
      ? 'Studies involving human or animal subjects, and experimental designs for which scientific authorities require approval, cannot be exempted from this requirement. The document itself is not uploaded: the committee, the date and the number are published so that anyone may ask the issuing committee directly. This notice is removed as soon as the author supplies them: the declaration can still be added today, from the author panel, with the access code.'
      : 'İnsan ya da hayvan denekle yürütülen çalışmalar ile bilim otoritelerince izin alınması zorunlu tutulan deneysel tasarımlar bu yükümlülükten muaf tutulamaz. Belgenin kendisi yüklenmez: kurul adı, tarih ve numara yayımlanır ki isteyen doğrudan veren kurula sorabilsin. Yazar bunları bildirdiğinde bu uyarı kalkar: beyan, yazar panelindeki erişim koduyla bugün de eklenebilir.' ?></p>
  </div>
  <?php endif; ?>

  <?php /* KOŞUL 'tur' DEĞİL: iki ret alan çalışmanın turu 'yazi'ye döner ve
           bu bant, iki hakemin reddettiği metnin başında "hakem
           değerlendirmesinden geçmemiştir" diyordu. Cümle yalnızca
           hakemli yolu HİÇ görmemiş çalışma için doğrudur. */ ?>
  <?php if (!$hakemGormus): ?>
  <div class="ham-band kutu"><?= $L ? 'This is an independent piece and has not undergone peer review.' : 'Bu, bağımsız bir yazıdır ve hakem değerlendirmesinden geçmemiştir.' ?></div>
  <?php /* Hakemli yolda olmak değerlendirilmiş olmak değildir. Rozet bunu
           kısaca söyler, ama kısa bir etiket "hakem aranıyor"u "hakemlendi"
           diye okumaya elverir; metnin başında bir cümleyle söylenmesi
           gerekir. Cümle tek kaynaktan alınır ki rozetle çelişmesin. */ ?>
  <?php elseif ($asama === 'aranan' || $asama === 'suruyor'): ?>
  <?php /* Aşama cümlesinin yanında BEKLEME SÜRESİ durur. Açık hakemlik
           yalnızca sonucun değil, sürenin de görünmesidir: bir çalışma
           iki yıldır hakem bekliyorsa okur bunu sayfanın kendisinden
           öğrenmeli, kötü haber sayfadan çıkarılmamalı. Cümle tek
           kaynaktan (tg_gecikme_cumlesi) gelir ki panel, istatistik ve
           makale sayfası aynı sayıyı aynı sözle söylesin. */ ?>
  <?php $gcm = tg_gecikme_cumlesi($yazi, (bool)$L); ?>
  <div class="ham-band kutu"><?= esc(tg_asama_aciklama($asama, (bool)$L)) ?><?php if ($gcm !== ''): ?> <span class="gcm"><?= $gcm ?></span><?php endif; ?></div>
  <?php endif; ?>
  <?php /* Eşik burada da SAYIYLA yazılıydı (>= 2); ayarı değiştiren biri
           bandı unuturdu. Tek kaynak: tg_ret_esigi_doldu(). */ ?>
  <?php if ($hakemGormus && $retDoldu): ?>
  <div class="ret-buyuk"><span class="ret-b"><?= $L ? 'ARTICLE REJECTED' : 'MAKALE RET EDİLMİŞTİR' ?></span><span class="ret-a"><?= $L ? 'Rejected by two reviewers. It is not removed: it stays in the archive as a non reviewed piece, together with the reviewers\' reasons.' : 'İki hakem tarafından reddedilmiştir. Metin kaldırılmaz: hakem gerekçeleriyle birlikte, hakemsiz yazı olarak arşivde kalır.' ?> <a href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>&surec=1"><?= $L ? 'See reasons' : 'Gerekçeleri gör' ?></a></span></div>
  <?php elseif ($hakemGormus && $retSay >= 1): ?>
  <div class="ham-band kutu kutu-kir ret-tek"><b>RET</b> · <?= $L ? 'One reviewer rejected this article; it is published with the reviewer\'s reasons.' : 'Bir hakem bu makaleyi reddetmiştir; hakem gerekçesiyle birlikte yayımlanmaktadır.' ?> <a href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>&surec=1"><?= $L ? 'See reasons' : 'Gerekçeleri gör' ?></a></div>
  <?php endif; ?>

  <?php $edNot = is_array($yazi['editor_notlari'] ?? null) ? $yazi['editor_notlari'] : []; if ($edNot): ?>
  <section class="ed-not">
    <h2><?= $L ? 'Editorial notes' : 'Editöryal notlar' ?></h2>
    <?php foreach (array_reverse($edNot) as $n): if (!is_array($n)) continue; ?>
      <div class="ed-n vurgu-kutu">
        <div class="ed-n-bas">
          <b><?= esc((string)($n['kim'] ?? '')) ?></b>
          <span><?= esc(($n['tur'] ?? '') === 'bas_editor' ? ($L ? 'Chief editor' : 'Baş editör') : (($n['tur'] ?? '') === 'yonetim' ? ($L ? 'System administration' : 'Sistem yönetimi') : ($L ? 'Editor' : 'Editör'))) ?></span>
          <span class="ara-oto"><?= esc(tg_zaman((string)($n['tarih'] ?? ''))) ?></span>
        </div>
        <div><?= tg_zengin((string)($n['metin'] ?? '')) ?></div>
      </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if (tg_hakem_araniyor($yazi)):
    /* Çağrı kutusu bekleyen.php ile aynı denetimden geçer: girişli kişi
       bu çalışmanın yazarıysa çağrı ona yapılmaz. Çağrının kendisi
       kalkmaz, yalnızca ona yöneltilen eylem kalkar; okur, çalışmanın
       hâlâ hakem aradığını görmeyi sürdürür. */
    $benimCalismam = ($hsO !== null && tg_yazar_mi($yazi, $hsO)); ?>
  <div class="hakem-cagri kutu kutu-kut no-print">
    <div>
      <b><?= $L ? 'This work is seeking reviewers' : 'Bu çalışmaya hakem aranıyor' ?></b>
      <span><?= $L
        ? 'Anyone holding a doctorate may volunteer to assess it. Volunteering is not the author\'s choice, so the assessment counts as independent.'
        : 'Doktora derecesine sahip herkes değerlendirmeye gönüllü olabilir. Gönüllülük yazarın seçimi olmadığı için değerlendirme bağımsız sayılır.' ?></span>
    </div>
    <?php if ($benimCalismam): ?>
      <span class="hakem-cagri-benim"><?= $L ? 'This is your own work.' : 'Bu sizin çalışmanız.' ?></span>
    <?php else: ?>
      <a class="d d-vurgu d-kucuk" href="/bekleyen.php?lang=<?= $lang ?>"><?= $L ? 'Volunteer to review' : 'Hakemliğe gönüllü ol' ?></a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php /* YAPILANDIRILMIŞ ÖZ, VARSA, BAŞLIKLARIYLA ÇİZİLİR.
           Kayıttaki kanonik dize hep 'ozet'tir ve PDF, OAI, döküm, site
           haritası ve atıf künyesi onu okumayı sürdürür; yapı yalnız
           BİLEN yerde, yani burada, başlıklara ayrılır.

           İKİ KOŞUL BİRDEN ARANIR:
             - yapı özeti gerçekten anlatıyor mu (tg_ozet_yapisi
               tutarsız yapıyı hiç döndürmez),
             - ekrandaki özet kaydın KENDİ özeti mi. Sayfa çeviriyle
               açıldığında $ozet çeviri kaydından gelir; özgün dildeki
               yapıyı çevrilmiş metnin üstüne koymak, okura o çevirinin
               bölümlere ayrıldığını söylemek olurdu. Ayrılmadı. */
  $ozetYapi = tg_ozet_yapisi($yazi);
  if ($ozetYapi && trim(tg_metin($yazi['ozet'] ?? '')) !== trim($ozet)) $ozetYapi = []; ?>
  <?php if (trim(strip_tags($ozet)) !== ''): ?>
  <section class="ozet kutu"><h2><?= $L ? 'Abstract' : 'Özet' ?></h2>
    <?php if ($ozetYapi): ?>
    <div class="oz-yapi"><?php foreach ($ozetYapi as $ob): ?><p><b><?= esc(tg_ozet_bolum_ad((string)$ob['k'], (bool)$L)) ?></b> <?= esc((string)$ob['m']) ?></p><?php endforeach; ?></div>
    <?php else: ?><div><?= $ozet ?></div><?php endif; ?>
    <?php if ($anahtar !== ''): ?><div class="anahtar"><b><?= $L ? 'Keywords' : 'Anahtar Kelimeler' ?>:</b> <?= esc($anahtar) ?></div><?php endif; ?>
  </section>
  <?php endif; ?>

  <?php /* Kefillik kaydı. Doktora derecesi henüz olmayan bir araştırmacı,
           iki doktoralı kişinin adıyla sorumluluk üstlenmesiyle yazar
           olabilir. Kim sorumluluk aldı ve neden aldı: okuyucunun
           görmesi gereken bir bilgidir ve kalıcıdır. */
  $kefilliler = [];
  /* Kefil kayıtları çalışmanın kendi yazar listesinden okunur; gösterim
     için kurulan $yazarListe yalnızca ad, unvan ve kurum taşır. */
  foreach (tg_yazar_kayitlari($yazi) as $ya) {
      if (!tg_yazar_unvansiz($ya)) continue;
      $kf = tg_kefiller($ya);
      if ($kf) $kefilliler[] = ['ad' => (string)($ya['ad'] ?? ''), 'kefiller' => $kf];
  }
  if ($kefilliler): ?>
  <section class="kfl" id="kefil">
    <h2><?= k_esc(tg_destek_ad_bas((bool)$L, true)) ?></h2>
    <p class="kfl-ack"><?= $L
      ? 'A researcher who does not yet hold a doctorate may appear as an author when two researchers holding doctorates take responsibility under their own names. One of them is a co author of this work; the other has no connection to it. Their statement concerns the reality of the contribution, not the scientific quality of the work: that is what the referee reports are for.'
      : 'Doktora derecesi henüz olmayan bir araştırmacı, iki doktoralı kişinin adıyla sorumluluk üstlenmesiyle yazar olarak yer alabilir. Bunlardan biri bu çalışmanın ortak yazarıdır, diğerinin çalışmayla hiçbir bağı yoktur. Yazarlık desteği, katkının gerçekliğine ilişkindir; çalışmanın bilimsel niteliğine değil, onu hakem raporları değerlendirir.' ?></p>
    <?php foreach ($kefilliler as $kg): ?>
    <div class="kfl-kume">
      <div class="kfl-kim"><?= esc($kg['ad']) ?></div>
      <?php foreach ($kg['kefiller'] as $kk): $kd = (string)($kk['durum'] ?? 'bekliyor'); ?>
      <div class="kfl-k kart">
        <div class="kfl-bas">
          <b><?= esc(bh_unvanli((string)($kk['unvan'] ?? ''), (string)($kk['ad'] ?? ''))) ?></b>
          <span class="rz rz-cizgi"><?= esc(tg_kefil_ilgi_ad((string)($kk['ilgi'] ?? ''), (bool)$L)) ?></span>
          <?php if ($kd === 'ret'): ?><span class="rz rz-kir"><?= $L ? 'declined' : 'desteklemedi' ?></span>
          <?php elseif ($kd !== 'onayli'): ?><span class="rz rz-kut"><?= $L ? 'awaiting confirmation' : 'onay bekleniyor' ?></span><?php endif; ?>
        </div>
        <?php if (trim((string)($kk['kurum'] ?? '')) !== ''): ?><div class="kfl-kurum"><?= esc($kk['kurum']) ?></div><?php endif; ?>
        <?php if (trim((string)($kk['orcid'] ?? '')) !== ''): ?><div class="kfl-kurum">ORCID: <a class="yazar-l" href="https://orcid.org/<?= esc($kk['orcid']) ?>" target="_blank" rel="noopener"><?= esc($kk['orcid']) ?></a></div><?php endif; ?>
        <?php if (trim((string)($kk['gerekce'] ?? '')) !== ''): ?><div class="kfl-ger"><?= nl2br(esc((string)$kk['gerekce'])) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php if ($ham): ?><div class="ham-band kutu kutu-kut"><?= $L ? 'This is the PRE-REVIEW, uncorrected version.' : 'Bu, hakem değerlendirmesinden ÖNCEki düzeltilmemiş sürümdür.' ?> · <a href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>"><?= $L ? 'Final version' : 'Final sürüm' ?></a></div><?php endif; ?>

  <div class="govde"><?= $metin ?></div>

  <?php if (trim(strip_tags($kaynakca)) !== ''): ?>
  <?php /* Başlığın KİMLİĞİ var: içindekiler ona bağlanabilsin diye.
           Kimlik betikle üretilseydi, betiksiz tarayıcıda kaynakçaya
           götüren hiçbir bağlantı olmazdı. */ ?>
  <section class="kaynakca"><h2 id="kaynakca"><?= $L ? 'References' : 'Kaynakça' ?></h2><div><?= kaynakca_linkle($kaynakca, $L) ?></div></section>
  <?php endif; ?>

  <?php /* ---- Doğrulama aracının dar ekrandaki yuvası ----
           Boş durur; içini aşağıdaki betik, yan sütun çizilmediğinde
           aracı buraya TAŞIYARAK doldurur. Burada ikinci bir araç
           basılmaz; sayfada tek bir #piArac vardır.
           Yeri bilerek burasıdır. Parmak izi başlık, özet, metin ve
           kaynakçadan üretilir; okur o dördünü de bitirdiği yerde
           doğrulamayı bulur. Sayfanın başına konsaydı okunmamış bir
           metin doğrulanmış olurdu ki bunun bir anlamı yoktur. */ ?>
  <div class="pi-yuva no-print" id="piYuva"></div>

  <?php
  /* ---- Sistem içi atıflar ----
     Bir çalışmanın kaynakçasında bu sistemdeki başka bir çalışmanın
     tamgası ya da adresi geçiyorsa bağ buradadır ve hemen kurulabilir.
     Dış dizinler bu bağı yıllar sonra ve eksik kurar. İki yön de
     gösterilir: bu çalışmanın atıf yaptıkları ve ona atıf yapanlar.
     Eşleşme yalnızca kesin kimlikle kurulur; başlık benzerliğine
     bakılmaz, çünkü yanlış bağ hiç bağ olmamasından kötüdür. */
  $atifVerilen = tg_atif_verilen($yazi, $yazilar);
  $atifAlan    = tg_atif_alan($yazi, $yazilar);
  /* Yazar dizesi: bu sayfa k/veri.php'yi yüklemediği için k_yazarlar
     burada yoktur; kaydın kendi alanlarından kurulur. */
  $atifYazar = function (array $e): string {
      $ad = [];
      $ilk = trim((string)($e['yazar'] ?? ''));
      if ($ilk !== '') $ad[] = $ilk;
      foreach (tg_dizi($e['yazar_liste'] ?? null) as $ya) {
          if (!is_array($ya)) continue;
          $n = trim((string)($ya['ad'] ?? ''));
          if ($n !== '' && !in_array($n, $ad, true)) $ad[] = $n;
      }
      return implode(', ', array_slice($ad, 0, 4));
  };
  ?>
  <?php if ($atifVerilen || $atifAlan): ?>
  <section class="atifbag" id="atifbag">
    <h2><?= $L ? 'Citations within this system' : 'Sistem içi atıflar' ?></h2>
    <p class="atifbag-ack"><?= $L
      ? 'These links are drawn from permanent identifiers, not from title similarity, so they are exact. A work newly published here appears in this list the moment it is published; no external index has to notice it first.'
      : 'Bu bağlar başlık benzerliğinden değil kalıcı kimlikten kurulur; bu yüzden kesindir. Buraya yeni yayımlanan bir çalışma, yayımlandığı anda bu listede görünür; bir dış dizinin fark etmesi beklenmez.' ?></p>

    <?php if ($atifVerilen): ?>
    <h3 class="atifbag-b"><?= $L ? 'This work cites' : 'Bu çalışmanın atıf yaptıkları' ?> (<?= count($atifVerilen) ?>)</h3>
    <ul class="atifbag-l">
      <?php foreach ($atifVerilen as $av): ?>
      <li class="kart">
        <a href="<?= esc(k_bag(tg_yazi_yolu($av))) ?>"><?= esc((string)($av['baslik'] ?? '')) ?></a>
        <span><?= esc($atifYazar($av)) ?> · <?= esc(substr((string)($av['tarih'] ?? ''), 0, 4)) ?>
        <?php if (trim((string)($av['bcid'] ?? '')) !== ''): ?> · <code><?= esc((string)$av['bcid']) ?></code><?php endif; ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($atifAlan): ?>
    <h3 class="atifbag-b"><?= $L ? 'Cited by, in this system' : 'Bu çalışmaya atıf yapanlar' ?> (<?= count($atifAlan) ?>)</h3>
    <ul class="atifbag-l">
      <?php foreach ($atifAlan as $aa): ?>
      <li class="kart">
        <a href="<?= esc(k_bag(tg_yazi_yolu($aa))) ?>"><?= esc((string)($aa['baslik'] ?? '')) ?></a>
        <span><?= esc($atifYazar($aa)) ?> · <?= esc(substr((string)($aa['tarih'] ?? ''), 0, 4)) ?>
        <?php if (trim((string)($aa['bcid'] ?? '')) !== ''): ?> · <code><?= esc((string)$aa['bcid']) ?></code><?php endif; ?></span>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($hakemGormus && $hakemDolu): ?>
  <section class="hakemler"><h2><?= $L ? 'Peer Review' : 'Hakem Değerlendirmesi' ?></h2>
    <p class="hakem-acik"><?= $L ? 'Open (non-blind) review.' : 'Açık hakemlik uygulanır (kör değildir).' ?></p>
    <?php foreach ($hakemDolu as $hi => $h): $k = (string)($h['karar'] ?? ''); $vn = is_array($h['raporlar'] ?? null) ? count($h['raporlar']) : 1; ?>
      <div class="hakem-kutu kart" data-karar="<?= esc($k) ?>">
        <div class="hakem-bas">
          <button type="button" class="hakem-ad-ac" data-h="<?= $hi ?>" title="<?= $L ? 'View report' : 'Raporu görüntüle' ?>"><b><?= esc($h['ad']) ?></b> <span class="ac-ok">▾ <?= $L ? 'report' : 'rapor' ?></span></button>
          <?php /* Hakemin kendi sayfası: bütün raporları ve karar dağılımı */ ?>
          <a class="hakem-kisi rz rz-cizgi" href="<?= esc(k_bag(tg_kisi_yolu((string)$h['ad']))) ?>" title="<?= $L ? 'This reviewer\'s record' : 'Bu hakemin kaydı' ?>"><?= $L ? 'record' : 'kaydı' ?></a>
          <?php if ($k && isset($kararAd[$k])): ?><span class="rz karar" style="background:<?= $kararRenk[$k] ?>"><?= esc($L ? $kararAd[$k][1] : $kararAd[$k][0]) ?></span><?php endif; ?>
        </div>
        <?php $sfm = tg_sifat_metin($h, $L); ?>
        <div class="hakem-rozet">
          <?php if ($sfm !== ''): ?><span class="rz rz-kut"><?= esc($sfm) ?></span><?php endif; ?>
          <?php $atm = tg_atayan_metin($h['atayan'] ?? null, $L); if ($atm !== ''): ?><span class="hakem-atayan"><?= esc($atm) ?></span><?php endif; ?>
        </div>
        <?php if (trim((string)($h['yetkinlik'] ?? '')) !== ''): ?>
          <div class="hakem-yetkin"><b><?= $L ? 'Basis of competence' : 'Yetkinlik gerekçesi' ?>:</b> <?= esc($h['yetkinlik']) ?></div>
        <?php endif; ?>
        <?php if (tg_rapor_oylama_saymaz($yazi, $h)): ?>
          <div class="hakem-nitelik vurgu-kutu"><?= $L
            ? 'A panel of three independent people set this report aside; it stays published but no longer counts towards approval.'
            : 'Üç bağımsız kişiden oluşan bir kurul bu raporu geçersiz saydı; rapor yayında kalır ancak onay sayımına katılmaz.' ?>
            <a href="#oylama"><?= $L ? 'See the vote' : 'Oylamayı gör' ?></a></div>
        <?php endif; ?>
        <?php $nk = tg_rapor_nitelik($h); if (!$nk['yeterli']): ?>
          <div class="hakem-nitelik vurgu-kutu"><?= $L ? 'This report does not meet the minimum assessment criteria and is not counted towards approval' : 'Bu rapor asgari değerlendirme ölçütlerini karşılamıyor ve onay sayımına katılmıyor' ?>: <?= esc(implode(', ', array_map(fn($e) => tg_nitelik_metin($e, $L), $nk['eksik']))) ?>.</div>
        <?php endif; ?>
        <?php if ($vn > 1): ?><div class="hakem-surum"><?= $vn ?> <?= $L ? 'revisions · latest published' : 'sürüm (revizyon) · en son sürüm yayında' ?></div><?php endif; ?>
        <?php if ($surec): ?><div class="hakem-rapor"><?= tg_zengin((string)($h['rapor'] ?? '')) ?></div><?php endif; ?>
        <?php if ($surec && !empty($h['dosya'])): ?><div class="ham-indir"><a href="<?= esc($h['dosya']) ?>" download><?= $L ? 'Download reviewer\'s file' : 'Hakemin eklediği dosyayı indir' ?> ↓</a></div><?php endif; ?>
        <?php
        /* ---- YAZAR VE HAKEM YAZIŞMASI ----
           Rapordan sonra, tarih sırasıyla ve kimin yazdığı belli olarak
           görünür. Silinmez. Bu yazışmanın kapalı kalması, kapalı
           hakemliğe yöneltilen eleştirinin aynısını bu sisteme
           taşırdı. */
        $diy = tg_diyalog(is_array($h) ? $h : []);
        if ($surec && $diy): ?>
        <div class="hakem-diyalog">
          <h4><?= $L ? 'Correspondence between the author and the reviewer' : 'Yazar ile hakem yazışması' ?></h4>
          <?php foreach ($diy as $dg): ?>
          <div class="hd-sat hd-<?= esc($dg['yon']) ?>">
            <span class="hd-kim"><?= $dg['yon'] === 'yazar'
              ? ($L ? 'Author' : 'Yazar')
              : ($L ? 'Reviewer' : 'Hakem') ?><?php if ($dg['tarih'] !== ''): ?> · <?= esc(substr($dg['tarih'], 0, 10)) ?><?php endif; ?></span>
            <div class="hd-metin"><?= nl2br(esc($dg['metin'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
    <p class="ham-indir"><a class="d d-ikinci d-git" href="/rapor.php?y=<?= esc($slugU) ?>&lang=<?= $lang ?>"><?= $L ? 'All reviewer reports as a document' : 'Bütün hakem raporları, belge olarak' ?></a></p>
    <?php if ($surec && $hamVar): ?><p class="ham-indir"><a class="d d-ikinci d-git" href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>&ham=1"><?= $L ? 'The pre-review (uncorrected) version' : 'Düzeltilmemiş (hakem öncesi) sürüm' ?></a></p><?php endif; ?>
    <p class="ham-indir">
      <?php if (!$surec): ?><a href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>&surec=1"><?= $L ? 'View the full peer-review process (reports + uncorrected version)' : 'Tüm hakemlik sürecini görüntüle/indir (raporlar + düzeltilmemiş sürüm)' ?></a>
      <?php else: ?><a href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>"><?= $L ? '← Back to article' : '← Makaleye dön' ?></a> · <button class="d d-ikinci d-kucuk" type="button" onclick="window.print()"><?= $L ? 'Print / Save PDF' : 'Yazdır / PDF indir' ?></button><?php endif; ?>
    </p>
    <div id="hmodal-ov" class="hmodal-ov"><div id="hmodal-box" class="hmodal kart"></div></div>
    <script>
    (function(){
      var HM = <?= json_encode($hakemModal, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      var ov=document.getElementById('hmodal-ov'), box=document.getElementById('hmodal-box');
      function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
      var GUNCEL=<?= $L ? "'Current version (published)'" : "'Güncel sürüm (yayında)'" ?>, ESKI=<?= $L ? "'Earlier version'" : "'Önceki sürüm'" ?>, INDIR=<?= $L ? "'Download attached file'" : "'Ekli dosyayı indir'" ?>;
      var ENDBAS=<?= $L ? "'Suggested indexes / journals'" : "'Önerilen endeks / dergiler'" ?>, ISBAS=<?= $L ? "'Marked passages'" : "'İşaretlenen yerler'" ?>;
      function horcidUrl(o){o=(o||'').trim();if(!o)return '';return /^https?:/i.test(o)?o:'https://orcid.org/'+o.replace(/^orcid\.org\//i,'');}
      function hwebUrl(w){w=(w||'').trim();if(!w)return '';return /^https?:/i.test(w)?w:'https://'+w;}
      function ac(i){
        var h=HM[i]; if(!h) return;
        var p=h.profil||{};
        var titVar=function(s){return /^\s*(prof|do[çc]|dr|öğr|ogr)\.?/i.test(s||'');};
        var basAd=(p.unvan&&!titVar(h.ad)?esc(p.unvan)+' ':'')+esc(h.ad);
        var html='<button class="kapa d d-ikinci d-im" type="button" data-kapat="1">✕</button><h3>'+basAd+'</h3>';
        var pf='';
        if(p.kurum) pf+='<div class="hp-sat">'+esc(p.kurum)+'</div>';
        if(p.eposta) pf+='<div class="hp-sat"><a href="mailto:'+esc(p.eposta)+'">'+esc(p.eposta)+'</a></div>';
        if(p.orcid) pf+='<div class="hp-sat"><a href="'+esc(horcidUrl(p.orcid))+'" target="_blank" rel="noopener">ORCID ↗</a></div>';
        if(p.web) pf+='<div class="hp-sat"><a href="'+esc(hwebUrl(p.web))+'" target="_blank" rel="noopener">'+<?= $L ? "'Profile ↗'" : "'Profil ↗'" ?>+'</a></div>';
        if(h.atayan) pf+='<div class="hp-sat">'+<?= $L ? "'Assignment'" : "'Atama'" ?>+': '+esc(h.atayan)+'</div>';
        if(h.dagilim) pf+='<div class="hp-sat">'+<?= $L ? "'Decision record'" : "'Karar dağılımı'" ?>+': '+esc(h.dagilim)+'</div>';
        if(pf) html+='<div class="hp-kart">'+pf+'</div>';
        if(h.nitelik===false) html+='<div class="hmodal-nitelik vurgu-kutu">'+<?= $L ? "'Does not meet the minimum assessment criteria, not counted towards approval'" : "'Asgari değerlendirme ölçütlerini karşılamıyor, onay sayımına katılmıyor'" ?>+': '+esc((h.eksik||[]).join(', '))+'</div>';
        (h.surumler||[]).forEach(function(v,idx){
          html+='<div class="hsurum"><div class="hsurum-bas">';
          html+=(idx===0?'<span class="rz rz-yes">'+GUNCEL+'</span>':'<span class="rz">'+ESKI+'</span>');
          if(v.karar) html+='<span class="rz hkarar" style="background:'+v.renk+'">'+esc(v.karar)+'</span>';
          if(v.tarih) html+='<span class="htarih">'+esc(v.tarih)+'</span>';
          /* v.rapor sunucuda tg_zengin() ile süzülmüş güvenli HTML'dir;
             burada yeniden kaçırmak etiketleri metin olarak gösterirdi. */
          html+='</div><div class="hsurum-rapor">'+v.rapor+'</div>';
          if(v.endeks&&v.endeks.length){ html+='<div class="hend"><b>'+ENDBAS+':</b> '; v.endeks.forEach(function(e){html+='<span class="rz rz-cizgi">'+esc(e)+'</span>';}); html+='</div>'; }
          if(v.notlar&&v.notlar.length){ html+='<div class="hnotb"><b>'+ISBAS+':</b>'; v.notlar.forEach(function(n){ html+='<div class="hnot">'+(n.alinti?'<div class="hnot-al">'+esc(n.alinti)+'</div>':'')+(n['not']?'<div class="hnot-nt">'+esc(n['not'])+'</div>':'')+'</div>'; }); html+='</div>'; }
          if(v.dosya) html+='<div style="margin-top:var(--b-2)"><a href="'+esc(v.dosya)+'" download>'+INDIR+' ↓</a></div>';
          html+='</div>';
        });
        box.innerHTML=html; ov.classList.add('acik');
      }
      document.querySelectorAll('.hakem-ad-ac').forEach(function(b){b.addEventListener('click',function(){ac(parseInt(b.dataset.h,10));});});
      ov.addEventListener('click',function(e){if(e.target===ov||e.target.getAttribute('data-kapat'))ov.classList.remove('acik');});
      document.addEventListener('keydown',function(e){if(e.key==='Escape')ov.classList.remove('acik');});
    })();
    </script>
  </section>
  <?php elseif ($hakemGormus && $hakemler): ?>
  <section class="hakemler"><h2><?= $L ? 'Reviewers' : 'Hakemler' ?></h2><ul>
    <?php foreach ($hakemler as $hk): $ad = is_array($hk) ? ($hk['ad'] ?? '') : $hk; ?><li><?= esc($ad) ?></li><?php endforeach; ?>
  </ul></section>
  <?php endif; ?>
  <?php if ($hamVar && !$ham && !$surec && !$hakemDolu): ?>
  <p class="ham-indir"><a class="d d-ikinci d-git" href="<?= esc($benYol . $benSep) ?>lang=<?= $lang ?>&ham=1"><?= $L ? 'The pre-review (uncorrected) version' : 'Hakem öncesi (düzeltilmemiş) sürüm' ?></a></p>
  <?php endif; ?>

  <?php
  /* ---------------------------------------------------------------
     OTURUMDAKİ KİŞİ
     Şerh alanı da kurul oylaması da aynı bilgileri kullanır; bir kez
     hesaplanır. Oturum sayfanın en başında, hiçbir çıktı verilmeden
     açılmıştır.
     --------------------------------------------------------------- */
  $hsVar = false; $hsYazabilir = false; $hsNeden = ''; $hsAd = '';
  $hsEditor = false; $hsYazarMi = false; $oyVerebilirMi = false;
  if ($hsO !== null) {
      $hsVar = true;
      $hsAd  = hs_gorunen_ad($hsO);
      $hsDD  = tg_dogrulama_durum($hsO['dogrulama'] ?? null);
      $hsEditor = hs_editor_mu($hsO);
      $hsYazarMi = tg_yazar_mi($yazi, $hsO);
      if (trim($hsAd) === '')                { $hsNeden = 'ad'; }
      elseif ($hsDD['yeterli'] || $hsEditor) { $hsYazabilir = true; }
      else                                   { $hsNeden = 'belge'; }
      /* Oy vermek daha ağır bir yetkidir: geçiş dönemi kolaylığı geçmez,
         belgenin gerçekten doğrulanmış olması aranır. */
      $oyVerebilirMi = ($hsDD['durum'] === 'onayli' || $hsEditor)
                    && trim($hsAd) !== '' && empty($hsO['hakemlik_askida']);
  }

  /* ---------------------------------------------------------------
     KURUL OYLAMALARI
     Yazar ile hakemin anlaşamadığı yerde karar tek kişiye bırakılmaz.
     Oylar üçüncü oy düşene kadar gizlidir; sonra hepsi açılır.
     --------------------------------------------------------------- */
  $oylamalar = tg_oylamalar($yazi);
  ?>
  <?php if ($oylamalar): ?>
  <section class="oyl" id="oylama">
    <h2><?= $L ? 'Panel votes' : 'Kurul oylamaları' ?></h2>
    <p class="oyl-ack"><?= $L
      ? 'Where an author and a reviewer disagree, the decision is not left to one person. Three independent people vote. While the vote is open no one can see anyone else\'s vote, not even the count, so that the first vote does not drag the rest. The moment the third vote is cast everything opens: who voted how, and why. Secrecy belongs to the moment of decision, not to what comes after.'
      : 'Yazar ile hakemin anlaşamadığı yerde karar tek kişiye bırakılmaz. Üç bağımsız kişi oy verir. Oylama açıkken kimse kimsenin oyunu, hatta sayımı bile göremez; böylece ilk oy sonrakileri sürüklemez. Üçüncü oy düştüğü anda her şey açılır: kimin nasıl oy verdiği ve neden. Gizlilik karar anına aittir, karardan sonrasına değil.' ?></p>

    <?php foreach ($oylamalar as $ov):
      $os = tg_oylama_sonuc($ov);
      $otur = (string)($ov['tur'] ?? 'itiraz');
      $izin = ($hsO !== null) ? tg_oy_verebilir($yazi, $ov, $hsO) : ['olur' => false, 'neden' => 'giris'];
      if ($hsO !== null && !$oyVerebilirMi) $izin = ['olur' => false, 'neden' => 'belge'];
    ?>
    <div class="oyl-k kart <?= $os['kapali'] ? 'oyl-kapali' : 'oyl-acik' ?>" id="o-<?= esc((string)($ov['kod'] ?? '')) ?>">
      <div class="oyl-bas">
        <b><?= esc(tg_oylama_tur_ad($otur, $L)) ?></b>
        <span class="oyl-hedef"><?= $L ? 'Report by' : 'Hakkında' ?>: <?= esc((string)($ov['hedef_ad'] ?? '')) ?></span>
        <span class="oyl-rz rz <?= $os['kapali'] ? 'rz-yes' : 'rz-kut' ?>">
          <?= $os['kapali']
            ? ($L ? 'Concluded' : 'Sonuçlandı')
            : ($L ? 'Open' : 'Açık') . ' · ' . (int)$os['oy_sayisi'] . '/' . (int)$os['gerek'] ?>
        </span>
      </div>
      <div class="oyl-acan"><?= $L ? 'Opened by' : 'Açan' ?>: <?= esc((string)($ov['acan_ad'] ?? '')) ?> · <?= esc(tg_zaman((string)($ov['tarih'] ?? ''))) ?></div>
      <p class="oyl-ger"><?= nl2br(esc((string)($ov['gerekce'] ?? ''))) ?></p>

      <?php if ($os['kapali']): ?>
        <div class="oyl-sonuc kutu kutu-yes">
          <b><?= $L ? 'Result' : 'Sonuç' ?>:</b> <?= esc(tg_oy_metin($otur, (string)$os['karar'], $L)) ?>
        </div>
        <div class="oyl-oylar">
          <?php foreach (tg_dizi($ov['oylar'] ?? null) as $o): if (!is_array($o)) continue; ?>
          <div class="oyl-o">
            <div class="oyl-o-bas">
              <b><?= esc((string)($o['ad'] ?? '')) ?></b>
              <span><?= esc(tg_oy_metin($otur, (string)($o['karar'] ?? ''), $L)) ?></span>
              <span class="ara-oto"><?= esc(tg_zaman((string)($o['tarih'] ?? ''))) ?></span>
            </div>
            <p><?= nl2br(esc((string)($o['gerekce'] ?? ''))) ?></p>
          </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="oyl-gizli kutu"><?= $L
          ? 'Votes cast so far are sealed. Neither their number by option nor who voted is shown until the vote concludes.'
          : 'Şu ana kadar kullanılan oylar kapalıdır. Oylama sonuçlanana kadar ne hangi seçeneğe kaç oy gittiği ne de kimin oy verdiği gösterilir.' ?></div>
        <?php if ($izin['olur']): ?>
        <div class="oyl-form no-print" data-oy-kod="<?= esc((string)($ov['kod'] ?? '')) ?>" data-oy-tur="<?= esc($otur) ?>">
          <span class="etiket"><?= $L ? 'Your vote' : 'Oyunuz' ?></span>
          <div class="onay-dizi">
          <?php foreach (tg_oy_secenekleri($otur, $L) as $sk => $sv): ?>
          <label class="onay-kart">
            <input type="radio" name="oy-<?= esc((string)($ov['kod'] ?? '')) ?>" value="<?= esc($sk) ?>">
            <span><?= esc($sv) ?></span>
          </label>
          <?php endforeach; ?>
          </div>
          <textarea class="oyl-ta" placeholder="<?= $L ? 'Why? Gerekçesiz oy, kurulu bir sayaca çevirir.' : 'Neden? Gerekçesiz oy, kurulu bir sayaca çevirir.' ?>"></textarea>
          <div class="oyl-alt">
            <button type="button" class="d d-vurgu d-kucuk oyl-gonder"><?= $L ? 'Cast the vote' : 'Oyu ver' ?></button>
            <span class="oyl-say">0 / <?= (int)tg_ayar('oy_asgari_karakter', 80) ?></span>
          </div>
          <div class="oyl-msj" hidden></div>
        </div>
        <?php elseif ($izin['neden'] !== ''): ?>
        <div class="oyl-engel no-print"><?= esc(tg_oy_engel_metin($izin['neden'], $L)) ?><?= $izin['neden'] === 'giris' ? ': ' : '.' ?><?php if ($izin['neden'] === 'giris' || $izin['neden'] === 'belge'): ?><a href="/panel.php?lang=<?= $lang ?>"><?= $L ? 'go to your panel' : 'panelinize gidin' ?></a>.<?php endif; ?></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endif; ?>

  <?php
  /* Rapor yazmış hakemlerden hangileri hakkında henüz oylama açılmamış? */
  /* İtiraz kendi hakkını kullanmaktır: normal eşik yeter.
     Şikâyet başkasının raporunu hedef alır: belge doğrulanmış olmalı.
     Buradaki koşul, sunucudaki koşulla birebir aynı olmalıdır. */
  $itirazAcabilir = $hsYazarMi ? $hsYazabilir : $oyVerebilirMi;
  $itirazEdilebilir = [];
  if ($itirazAcabilir && $hakemDolu) {
      foreach ($hakemDolu as $hh) {
          if (!is_array($hh) || trim((string)($hh['rapor'] ?? '')) === '') continue;
          $hhAd = (string)($hh['ad'] ?? '');
          if ($hhAd === '' || tg_ad_anahtar($hhAd) === tg_ad_anahtar($hsAd)) continue;
          $zaten = false;
          foreach ($oylamalar as $ov) {
              if (tg_ad_anahtar((string)($ov['hedef_ad'] ?? '')) !== tg_ad_anahtar($hhAd)) continue;
              $ovTur = (string)($ov['tur'] ?? 'itiraz');
              if (!tg_oylama_sonuc($ov)['kapali']) { $zaten = true; break; }
              if ($ovTur === ($hsYazarMi ? 'itiraz' : 'sikayet')) { $zaten = true; break; }
          }
          if (!$zaten) $itirazEdilebilir[] = $hhAd;
      }
  }
  $itirazAsgari = (int)tg_ayar('itiraz_asgari_karakter', 200);
  ?>
  <?php if ($itirazEdilebilir): ?>
  <section class="oyl itiraz-ac no-print" id="itiraz"<?= $oylamalar ? ' style="border-top:0;padding-top:0;margin-top:var(--b-1)"' : '' ?>>
    <?php if (!$oylamalar): ?><h2><?= $L ? 'Panel votes' : 'Kurul oylamaları' ?></h2><?php endif; ?>
    <details class="oyl-k kart">
      <summary>
        <?= $hsYazarMi
          ? ($L ? 'Object to a report on this work' : 'Bu çalışmadaki bir rapora itiraz et')
          : ($L ? 'Complain about a report on this work' : 'Bu çalışmadaki bir rapordan şikâyetçi ol') ?>
      </summary>
      <p class="oyl-ack" style="margin-top:var(--b-3)"><?= $hsYazarMi
        ? ($L
          ? 'Your objection does not go to an editor; it goes to three independent people who are neither you nor the reviewer. Their votes stay sealed until the third is cast, then all three are published with their names and reasoning. The outcome is permanent and cannot be reversed by anyone, including you.'
          : 'İtirazınız bir editöre değil, ne siz ne de hakem olan üç bağımsız kişiye gider. Oyları üçüncü oy düşene kadar kapalı kalır, sonra üçü de adları ve gerekçeleriyle yayımlanır. Sonuç kalıcıdır ve siz dahil kimse tarafından geri alınamaz.')
        : ($L
          ? 'A complaint does not go to an editor; it goes to three independent people. Their votes stay sealed until the third is cast, then all three are published with their names and reasoning. Use this where a report is careless or in bad faith, not where you simply disagree with it.'
          : 'Şikâyet bir editöre değil, üç bağımsız kişiye gider. Oyları üçüncü oy düşene kadar kapalı kalır, sonra üçü de adları ve gerekçeleriyle yayımlanır. Bunu, katılmadığınız değil savruk ya da kötü niyetli bulduğunuz raporlar için kullanın.') ?></p>
      <div class="oyl-form" data-itiraz-tur="<?= $hsYazarMi ? 'itiraz' : 'sikayet' ?>">
        <span class="etiket"><?= $L ? 'Which report' : 'Hangi rapor' ?></span>
        <div class="onay-dizi">
        <?php foreach ($itirazEdilebilir as $ie): ?>
        <label class="onay-kart">
          <input type="radio" name="itiraz-hedef" value="<?= esc($ie) ?>">
          <span><?= esc($ie) ?></span>
        </label>
        <?php endforeach; ?>
        </div>
        <textarea class="oyl-ta itiraz-ta" placeholder="<?= $L ? 'Set out clearly what in the report is wrong or unfair, pointing to the passage.' : 'Raporun neresinin yanlış ya da haksız olduğunu, yerini göstererek açıkça yazın.' ?>"></textarea>
        <div class="oyl-alt">
          <button type="button" class="d d-vurgu d-kucuk itiraz-gonder"><?= $L ? 'Open the panel vote' : 'Kurul oylamasını aç' ?></button>
          <span class="oyl-say itiraz-say">0 / <?= $itirazAsgari ?></span>
        </div>
        <div class="oyl-msj itiraz-msj" hidden></div>
      </div>
    </details>
  </section>
  <?php endif; ?>

  <?php if ($oylamalar || $itirazEdilebilir): ?>
  <script>
  (function(){
    var EN=<?= $L ? 'true' : 'false' ?>,
        ASG=<?= (int)tg_ayar('oy_asgari_karakter', 80) ?>,
        IASG=<?= $itirazAsgari ?>,
        OID=<?= json_encode((string)($yazi['id'] ?? ($yazi['slug'] ?? '')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    /* --- Oy verme --- */
    document.querySelectorAll('.oyl-form[data-oy-kod]').forEach(function(f){
      var ta=f.querySelector('.oyl-ta'), say=f.querySelector('.oyl-say'),
          dg=f.querySelector('.oyl-gonder'), msj=f.querySelector('.oyl-msj');
      function guncelle(){ var n=ta.value.trim().length; say.textContent=n+' / '+ASG;
        say.style.color=(n>=ASG?'var(--yesil)':'var(--metin-2)'); }
      ta.addEventListener('input',guncelle); guncelle();
      dg.addEventListener('click',function(){
        var sec=f.querySelector('input[type=radio]:checked');
        msj.hidden=false;
        if(!sec){ msj.className='oyl-msj kotu'; msj.textContent=EN?'Choose one of the options.':'Seçeneklerden birini işaretleyin.'; return; }
        if(ta.value.trim().length<ASG){ msj.className='oyl-msj kotu';
          msj.textContent=EN?('At least '+ASG+' characters of reasoning are needed.'):('Gerekçe en az '+ASG+' karakter olmalıdır.'); return; }
        if(!confirm(EN?'Your vote is sealed until the third vote, then published with your name and reasoning. It cannot be changed. Cast it?':'Oyunuz üçüncü oya kadar kapalı kalır, sonra adınız ve gerekçenizle yayımlanır. Değiştirilemez. Verilsin mi?')) return;
        dg.disabled=true; dg.textContent=EN?'Sending...':'Gönderiliyor...';
        fetch('/api/oy-ver',{method:'POST',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({id:OID,kod:f.dataset.oyKod,karar:sec.value,gerekce:ta.value.trim()})})
          .then(function(r){return r.json();})
          .then(function(d){
            if(d&&d.ok){ msj.className='oyl-msj iyi';
              msj.textContent=(d.mesaj||'')+(d.yazarlik?(EN?' You have now earned the right to submit as an author.':' Bu oyla birlikte yazarlık hakkını kazandınız.'):'');
              setTimeout(function(){location.reload();},d.kapandi?1400:2200); }
            else { msj.className='oyl-msj kotu'; msj.textContent=(d&&d.hata)?d.hata:(EN?'It could not be sent.':'Gönderilemedi.');
              dg.disabled=false; dg.textContent=EN?'Cast the vote':'Oyu ver'; }
          })
          .catch(function(){ msj.className='oyl-msj kotu'; msj.textContent=EN?'Connection error.':'Bağlantı hatası.';
            dg.disabled=false; dg.textContent=EN?'Cast the vote':'Oyu ver'; });
      });
    });

    /* --- İtiraz ya da şikâyet açma --- */
    var itf=document.querySelector('.oyl-form[data-itiraz-tur]');
    if(itf){
      var ita=itf.querySelector('.itiraz-ta'), itsay=itf.querySelector('.itiraz-say'),
          itdg=itf.querySelector('.itiraz-gonder'), itmsj=itf.querySelector('.itiraz-msj'),
          itEt=itdg.textContent;
      function itGuncelle(){ var n=ita.value.trim().length; itsay.textContent=n+' / '+IASG;
        itsay.style.color=(n>=IASG?'var(--yesil)':'var(--metin-2)'); }
      ita.addEventListener('input',itGuncelle); itGuncelle();
      itdg.addEventListener('click',function(){
        var sec=itf.querySelector('input[name=itiraz-hedef]:checked');
        itmsj.hidden=false;
        if(!sec){ itmsj.className='oyl-msj itiraz-msj kotu';
          itmsj.textContent=EN?'Choose which report this concerns.':'Hangi rapor hakkında olduğunu işaretleyin.'; return; }
        if(ita.value.trim().length<IASG){ itmsj.className='oyl-msj itiraz-msj kotu';
          itmsj.textContent=EN?('At least '+IASG+' characters are needed.'):('Gerekçe en az '+IASG+' karakter olmalıdır.'); return; }
        if(!confirm(EN?'This opens a panel vote that three independent people will decide. It cannot be withdrawn. Open it?':'Bu, üç bağımsız kişinin karara bağlayacağı bir kurul oylaması açar. Geri alınamaz. Açılsın mı?')) return;
        itdg.disabled=true; itdg.textContent=EN?'Opening...':'Açılıyor...';
        fetch('/api/oylama-ac',{method:'POST',headers:{'Content-Type':'application/json'},
          body:JSON.stringify({id:OID,tur:itf.dataset.itirazTur,hedef:sec.value,gerekce:ita.value.trim(),website:''})})
          .then(function(r){return r.json();})
          .then(function(d){
            if(d&&d.ok){ itmsj.className='oyl-msj itiraz-msj iyi'; itmsj.textContent=d.mesaj||'';
              setTimeout(function(){location.reload();},1400); }
            else { itmsj.className='oyl-msj itiraz-msj kotu'; itmsj.textContent=(d&&d.hata)?d.hata:(EN?'It could not be opened.':'Açılamadı.');
              itdg.disabled=false; itdg.textContent=itEt; }
          })
          .catch(function(){ itmsj.className='oyl-msj itiraz-msj kotu'; itmsj.textContent=EN?'Connection error.':'Bağlantı hatası.';
            itdg.disabled=false; itdg.textContent=itEt; });
      });
    }
  })();
  </script>
  <?php endif; ?>

  <?php
  /* ---------------------------------------------------------------
     YAYIN SONRASI ŞERH
     Bir çalışma yayımlandıktan sonra tartışma bitmez. Şerhler adla
     yazılır, kalıcıdır ve silinmez; ancak gerekçesi görünür kalmak
     şartıyla editörce perdelenebilir.
     --------------------------------------------------------------- */
  $serhler = tg_serhler($yazi);
  $serhAsgari = (int)tg_ayar('serh_asgari_karakter', 120);
  ?>
  <section class="serh" id="serh">
    <h2><?= $L ? 'Post publication commentary' : 'Yayın sonrası şerh' ?><?= $serhler ? ' (' . count($serhler) . ')' : '' ?></h2>
    <p class="serh-ack"><?= $L
      ? 'In a journal the assessment ends on the day of publication. Here it does not. Any reader with a verified account may leave a permanent note under this work: an objection, a correction, a replication attempt, a piece of evidence. Notes are signed with the writer\'s own name and are never deleted, not by the writer, not by the author, not by an editor. A note that carries a personal attack or unlawful content may be screened by an editor, but the fact of the screening, the editor\'s name and the reason remain visible, so that censorship is recorded too.'
      : 'Bir dergide değerlendirme yayım günü biter. Burada bitmez. Doğrulanmış hesabı olan her okuyucu bu çalışmanın altına kalıcı bir not bırakabilir: bir itiraz, bir düzeltme, bir yineleme denemesi, bir kanıt. Şerhler yazanın kendi adıyla yazılır ve hiçbir zaman silinmez; ne yazan siler, ne çalışmanın yazarı, ne editör. Kişisel saldırı ya da hukuka aykırı içerik taşıyan bir şerh editörce perdelenebilir; ancak perdelemenin yapıldığı, kimin yaptığı ve gerekçesi görünür kalır, böylece sansür de kayda geçer.' ?></p>

    <?php if (!$serhler): ?>
      <p class="serh-yok"><?= $L
        ? 'No note has been left on this work yet. The first one may be yours.'
        : 'Bu çalışmaya henüz şerh düşülmemiş. İlkini siz yazabilirsiniz.' ?></p>
    <?php else: foreach ($serhler as $s): $perde = is_array($s['perde'] ?? null) ? $s['perde'] : null; ?>
      <div class="serh-k kart" id="s-<?= esc((string)($s['kod'] ?? '')) ?>">
        <div class="serh-bas">
          <b><a class="kisi-bag" href="<?= esc(k_bag(tg_kisi_yolu((string)($s['ad'] ?? '')))) ?>"><?= esc((string)($s['ad'] ?? '')) ?></a></b>
          <?php $ilgi = (string)($s['ilgi'] ?? 'okur'); $ilgiAd = tg_serh_ilgi_ad($ilgi, $L); ?>
          <?php if ($ilgiAd !== '' && $ilgi !== 'okur'): ?><span class="rz rz-cizgi serh-ilgi i-<?= esc($ilgi) ?>"><?= esc($ilgiAd) ?></span><?php endif; ?>
          <span class="serh-t"><?= esc(tg_zaman((string)($s['tarih'] ?? ''))) ?></span>
        </div>
        <?php $ku = trim((string)($s['kurum'] ?? '')); if ($ku !== ''): ?><span class="serh-kurum"><?= esc($ku) ?></span><?php endif; ?>
        <?php if ($perde): ?>
          <div class="serh-perde vurgu-kutu">
            <b><?= $L ? 'This note has been screened by an editor.' : 'Bu şerh bir editör tarafından perdelenmiştir.' ?></b><br>
            <?= $L ? 'Reason' : 'Gerekçe' ?>: <?= esc((string)($perde['neden'] ?? '')) ?><br>
            <?= $L ? 'Screened by' : 'Perdeleyen' ?>: <?= esc((string)($perde['kim'] ?? '')) ?> · <?= esc(tg_zaman((string)($perde['tarih'] ?? ''))) ?><br>
            <?= $L ? 'The text is closed, but the record of the note remains.' : 'Metin kapatılmıştır; şerhin kaydı yerinde durur.' ?>
          </div>
        <?php else: ?>
          <div class="serh-m"><?= tg_zengin((string)($s['metin'] ?? '')) ?></div>
        <?php endif; ?>
        <?php $yn = is_array($s['yanit'] ?? null) ? $s['yanit'] : null; if ($yn && trim((string)($yn['metin'] ?? '')) !== ''): ?>
          <div class="serh-yanit vurgu-kutu">
            <div class="serh-bas">
              <b><?= esc((string)($yn['ad'] ?? '')) ?></b>
              <span class="rz rz-cizgi serh-ilgi i-yazar"><?= esc(tg_serh_ilgi_ad((string)($yn['ilgi'] ?? 'yazar'), $L)) ?></span>
              <span class="serh-t"><?= esc(tg_zaman((string)($yn['tarih'] ?? ''))) ?></span>
            </div>
            <div class="serh-m"><?= tg_zengin((string)($yn['metin'] ?? '')) ?></div>
          </div>
        <?php endif; ?>
        <?php if (!$perde && ($hsEditor || ($hsYazarMi && !$yn))): ?>
          <div class="serh-islem no-print">
            <?php if ($hsYazarMi && !$yn): ?>
              <button type="button" class="serh-d d d-ikinci d-kucuk" data-serh-yanit="<?= esc((string)($s['kod'] ?? '')) ?>"><?= $L ? 'Reply once, permanently' : 'Bir kez, kalıcı olarak yanıtla' ?></button>
            <?php endif; ?>
            <?php if ($hsEditor): ?>
              <button type="button" class="serh-d serh-d-uyari d d-tehlike d-kucuk" data-serh-perde="<?= esc((string)($s['kod'] ?? '')) ?>"><?= $L ? 'Screen this note (not delete)' : 'Perdele (silmek değildir)' ?></button>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; endif; ?>

    <?php if ($hsYazabilir): ?>
      <form class="serh-form kart no-print" id="serhForm" autocomplete="off">
        <label for="serhMetin"><?= $L ? 'Your note, under your own name' : 'Kendi adınızla şerhiniz' ?>: <?= esc($hsAd) ?></label>
        <textarea id="serhMetin" name="metin" minlength="<?= $serhAsgari ?>" maxlength="6000"
          placeholder="<?= $L ? 'Write the reasoning, not only the verdict. Point to the passage, the figure or the source you are responding to.' : 'Yalnızca hükmü değil gerekçeyi yazın. Karşılık verdiğiniz yeri, sayıyı ya da kaynağı gösterin.' ?>"></textarea>
        <input class="serh-balkabi gizle" type="text" name="website" tabindex="-1" aria-hidden="true" autocomplete="off">
        <div class="serh-alt">
          <button class="d d-vurgu" type="submit" id="serhGonder"><?= $L ? 'Leave a permanent note' : 'Kalıcı şerh düş' ?></button>
          <span class="serh-say" id="serhSay">0 / <?= $serhAsgari ?></span>
        </div>
        <p class="serh-uyari"><?= $L
          ? 'Once sent, this note cannot be deleted or edited, by you or by anyone else. Please read it once more before sending.'
          : 'Gönderdikten sonra bu şerh ne sizin ne bir başkası tarafından silinebilir ya da düzeltilebilir. Göndermeden önce bir kez daha okuyun.' ?></p>
        <div class="serh-sonuc" id="serhSonuc" hidden></div>
      </form>
    <?php elseif ($hsVar && $hsNeden === 'belge'): ?>
      <div class="serh-kapi kutu no-print"><?= $L
        ? 'You are signed in, but a note is published under your name and stays permanently, so the account it comes from must be verified. You may submit your doctoral credential from '
        : 'Girişiniz var; ancak şerh adınızla yayımlanır ve kalıcı olarak durur, bu yüzden geldiği hesabın doğrulanmış olması gerekir. Doktora belgenizi ' ?><a href="/panel.php?lang=<?= $lang ?>"><?= $L ? 'your panel' : 'panelinizden' ?></a><?= $L ? '.' : ' sunabilirsiniz.' ?></div>
    <?php elseif ($hsVar && $hsNeden === 'ad'): ?>
      <div class="serh-kapi kutu no-print"><?= $L ? 'Please complete your name and title in ' : 'Şerh yazmadan önce adınızı ve unvanınızı ' ?><a href="/panel.php?lang=<?= $lang ?>"><?= $L ? 'your panel' : 'panelinizden' ?></a><?= $L ? ' before leaving a note.' : ' tamamlayın.' ?></div>
    <?php else: ?>
      <div class="serh-kapi kutu no-print"><?= $L
        ? 'Reading is open to everyone and always will be. Leaving a note requires an account, because a note carries a name and stays permanently. '
        : 'Okumak herkese açıktır ve öyle kalacaktır. Şerh düşmek için hesap gerekir; çünkü şerh bir ad taşır ve kalıcı olarak durur. ' ?><a href="/panel.php?lang=<?= $lang ?>"><?= $L ? 'Sign in or open an account' : 'Giriş yapın ya da hesap açın' ?></a>.</div>
    <?php endif; ?>
  </section>

  <?php if ($hsYazabilir): ?>
  <script>
  (function(){
    var f=document.getElementById('serhForm'); if(!f) return;
    var t=document.getElementById('serhMetin'), s=document.getElementById('serhSay'),
        b=document.getElementById('serhGonder'), c=document.getElementById('serhSonuc'),
        ASG=<?= $serhAsgari ?>, EN=<?= $L ? 'true' : 'false' ?>,
        SID=<?= json_encode((string)($yazi['id'] ?? ($yazi['slug'] ?? '')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function duz(){ var v=t.value||'';
      if(!/<[a-z][^>]*>/i.test(v)) return v.trim();
      var d=document.createElement('div'); d.innerHTML=v; return (d.textContent||'').replace(/\s+/g,' ').trim(); }
    function say(){ var n=duz().length; s.textContent=n+' / '+ASG; s.style.color=(n>=ASG?'var(--yesil)':'var(--metin-2)'); }
    t.addEventListener('input',say);
    document.addEventListener('kutadgu-duzenleyici',say);
    say();
    f.addEventListener('submit',function(e){
      e.preventDefault();
      var m=(window.kdOku?kdOku('serhMetin'):t.value).trim();
      if(duz().length<ASG){ c.hidden=false; c.className='serh-sonuc kotu'; c.textContent=EN?('At least '+ASG+' characters are needed.'):('En az '+ASG+' karakter gerekir.'); return; }
      if(!confirm(EN?'This note is permanent and cannot be deleted afterwards. Send it?':'Bu şerh kalıcıdır ve sonradan silinemez. Gönderilsin mi?')) return;
      b.disabled=true; b.textContent=EN?'Sending...':'Gönderiliyor...';
      fetch('/api/serh-ekle',{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({id:SID,metin:m,website:f.website.value})})
        .then(function(r){return r.json();})
        .then(function(d){
          c.hidden=false;
          if(d&&d.ok){ c.className='serh-sonuc iyi'; c.textContent=EN?'Your note has been recorded. Reloading...':'Şerhiniz kaydedildi. Sayfa yenileniyor...'; setTimeout(function(){location.reload();},900); }
          else { c.className='serh-sonuc kotu'; c.textContent=(d&&d.hata)?d.hata:(EN?'It could not be sent.':'Gönderilemedi.'); b.disabled=false; b.textContent=EN?'Leave a permanent note':'Kalıcı şerh düş'; }
        })
        .catch(function(){ c.hidden=false; c.className='serh-sonuc kotu'; c.textContent=EN?'Connection error.':'Bağlantı hatası.'; b.disabled=false; b.textContent=EN?'Leave a permanent note':'Kalıcı şerh düş'; });
    });
  })();
  </script>
  <?php endif; ?>

  <?php if ($serhler && ($hsEditor || $hsYazarMi)): ?>
  <script>
  (function(){
    var EN=<?= $L ? 'true' : 'false' ?>,
        SID=<?= json_encode((string)($yazi['id'] ?? ($yazi['slug'] ?? '')), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    function kutu(btn, baslik, ipucu, asgari, gonder){
      if(btn.dataset.acik) return;
      btn.dataset.acik='1';
      var w=document.createElement('div'); w.className='serh-kutu';
      var ta=document.createElement('textarea'); ta.placeholder=ipucu;
      var alt=document.createElement('div'); alt.className='serh-islem';
      var ok=document.createElement('button'); ok.type='button'; ok.className='d d-ikinci d-kucuk'; ok.textContent=baslik;
      var vaz=document.createElement('button'); vaz.type='button'; vaz.className='d d-sessiz d-kucuk';
      vaz.textContent=EN?'Cancel':'Vazgeç';
      var msg=document.createElement('div'); msg.className='serh-sonuc';
      alt.appendChild(ok); alt.appendChild(vaz);
      w.appendChild(ta); w.appendChild(alt); w.appendChild(msg);
      btn.parentNode.appendChild(w);
      vaz.addEventListener('click',function(){ w.remove(); delete btn.dataset.acik; });
      ok.addEventListener('click',function(){
        var m=ta.value.trim();
        if(m.length<asgari){ msg.className='serh-sonuc kotu';
          msg.textContent=EN?('At least '+asgari+' characters are needed.'):('En az '+asgari+' karakter gerekir.'); return; }
        ok.disabled=true; ok.textContent=EN?'Sending...':'Gönderiliyor...';
        gonder(m).then(function(d){
          if(d&&d.ok){ msg.className='serh-sonuc iyi'; msg.textContent=d.mesaj||'';
            setTimeout(function(){location.reload();},800); }
          else { msg.className='serh-sonuc kotu'; msg.textContent=(d&&d.hata)?d.hata:(EN?'It could not be sent.':'Gönderilemedi.');
            ok.disabled=false; ok.textContent=baslik; }
        }).catch(function(){ msg.className='serh-sonuc kotu';
          msg.textContent=EN?'Connection error.':'Bağlantı hatası.'; ok.disabled=false; ok.textContent=baslik; });
      });
      ta.focus();
    }
    function yolla(yol, govde){
      return fetch(yol,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(govde)})
        .then(function(r){return r.json();});
    }
    document.querySelectorAll('[data-serh-yanit]').forEach(function(b){
      b.addEventListener('click',function(){
        var kod=b.dataset.serhYanit;
        kutu(b, EN?'Send the reply':'Yanıtı gönder',
             EN?'You may reply to a note once. The reply is permanent and cannot be edited afterwards.'
               :'Bir şerhe bir kez yanıt verebilirsiniz. Yanıt kalıcıdır ve sonradan düzeltilemez.',
             40, function(m){ return yolla('/api/serh-yanit',{id:SID,kod:kod,metin:m}); });
      });
    });
    document.querySelectorAll('[data-serh-perde]').forEach(function(b){
      b.addEventListener('click',function(){
        var kod=b.dataset.serhPerde;
        kutu(b, EN?'Screen it':'Perdele',
             EN?'Write the reason. The note is not deleted: its existence, your name and this reason stay visible to every reader.'
               :'Gerekçeyi yazın. Şerh silinmez: varlığı, adınız ve bu gerekçe her okuyucuya görünür kalır.',
             15, function(m){ return yolla('/api/serh-perde',{id:SID,kod:kod,neden:m}); });
      });
    });
  })();
  </script>
  <?php endif; ?>

  <section class="pay no-print" id="paylas">
    <?php /* Bölüm KAPALI açılır: on bir düğme her okurdan yer alıyordu,
             oysa okurun çoğu paylaşmıyor. Açıklama da içeri alındı;
             kapalı bir bölümün üstünde duran açıklama, açılmamış bir
             şeyi anlatıyordu. */ ?>
    <details class="pay-kut" id="payKut">
      <summary><?= $L ? 'Share this work' : 'Bu çalışmayı paylaş' ?></summary>
      <div class="pay-kut-ic">
    <p class="pay-ack"><?= $L
      ? 'Open access means nothing if the work is not seen. Each button below prepares a message shaped for that platform; you can change it before posting.'
      : 'Açık erişim, çalışma görünmezse bir anlam taşımaz. Aşağıdaki her düğme o mecraya göre biçimlenmiş bir metin hazırlar; göndermeden önce değiştirebilirsiniz.' ?></p>

    <div class="pay-dg">
      <button class="d d-vurgu pay-b pay-yerli" type="button" id="payYerli" hidden title="<?= $L ? 'Share' : 'Paylaş' ?>" aria-label="<?= $L ? 'Share' : 'Paylaş' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M8 7l4-4 4 4M5 14v5a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-5"/></svg>
        <span><?= $L ? 'Share' : 'Paylaş' ?></span>
      </button>
      <a class="d d-ikinci pay-b pay-x" href="#" data-pay="x" title="X" aria-label="X" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" stroke="none"><path d="M18.9 2H22l-7.1 8.1L23.2 22h-6.6l-5.2-6.8L5.5 22H2.4l7.6-8.7L1.2 2h6.8l4.7 6.2L18.9 2Zm-1.1 18.1h1.7L7.3 3.8H5.5l12.3 16.3Z"/></svg>
        <span>X</span>
      </a>
      <?php /* KURUL GERİ BİLDİRİMİ (M. Z. Tunca, 13 Ağustos 2026):
               "insta ve linkedin için buton var, altta ayrıca paylaşım
               üreten kısımlar var; ya buton ekle ya diğerini."

               Gözlem doğruydu ama sebebi teknikti: LinkedIn'in paylaşım
               adresi ARTIK METİN TAŞIMIYOR (share-offsite yalnızca url
               alır), Instagram'ın ise web paylaşım adresi hiç yok. Bu
               yüzden ikisinde de metin elle kopyalanmak zorunda ve alttaki
               kutular o yüzden var.

               Kusur ikisinin AYRI AYRI durmasıydı: Instagram düğmesi
               kutusunu açıyordu, LinkedIn düğmesi ise doğrudan LinkedIn'e
               gidiyor, altındaki kutu görülmüyordu. İki ayrı yol gibi
               duran tek bir yol, kullanıcıya "hangisi" dedirtir.
               Çözüm: LinkedIn de Instagram gibi kendi kutusunu açsın.
               Kutu, metni ve LinkedIn'e gitme düğmesini birlikte taşır. */ ?>
      <button class="d d-ikinci pay-b pay-in" type="button" data-pay="linkedin" title="LinkedIn" aria-label="LinkedIn">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" stroke="none"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-.95 1.83-1.95 3.75-1.95C21.4 8.75 22 11 22 14.1V21h-4v-6.1c0-1.45-.03-3.3-2-3.3-2.01 0-2.32 1.57-2.32 3.2V21h-4V9Z"/></svg>
        <span>LinkedIn</span>
      </button>
      <button class="d d-ikinci pay-b pay-ig" type="button" data-pay="instagram" title="Instagram" aria-label="Instagram">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
        <span>Instagram</span>
      </button>
      <a class="d d-ikinci pay-b pay-wa" href="#" data-pay="whatsapp" title="WhatsApp" aria-label="WhatsApp" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" stroke="none"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2Zm0 18.2c-1.6 0-3.1-.4-4.4-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2Zm4.6-6.1c-.3-.1-1.6-.8-1.8-.9-.2-.1-.4-.1-.6.1l-.8 1c-.2.2-.3.2-.5.1a6.6 6.6 0 0 1-3.3-2.9c-.2-.4.2-.4.6-1.2.1-.1 0-.3 0-.4l-.8-1.9c-.2-.5-.4-.4-.6-.4h-.5c-.2 0-.5.1-.7.3-.7.7-1 1.6-.9 2.5.3 1.5 1.1 2.8 2.2 3.9a10 10 0 0 0 4.4 2.6c1.1.3 2 .2 2.6-.2.4-.3.8-.9.9-1.4.1-.4.1-.7 0-.8l-.2-.1Z"/></svg>
        <span>WhatsApp</span>
      </a>
      <a class="d d-ikinci pay-b pay-tg" href="#" data-pay="telegram" title="Telegram" aria-label="Telegram" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" stroke="none"><path d="M21.8 4.2 2.9 11.5c-1 .4-1 1.2-.2 1.5l4.8 1.5 1.8 5.6c.2.6.4.8 1 .8.5 0 .7-.2 1-.5l2.4-2.3 4.9 3.6c.9.5 1.5.2 1.7-.8l3.1-14.6c.3-1.2-.5-1.8-1.6-1.4ZM8.4 14.2l10.2-6.4c.5-.3.9-.1.6.2l-8.7 7.9-.3 3.6-1.8-5.3Z"/></svg>
        <span>Telegram</span>
      </a>
      <a class="d d-ikinci pay-b pay-bs" href="#" data-pay="bluesky" title="Bluesky" aria-label="Bluesky" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" stroke="none"><path d="M12 10.8C10.9 8.6 7.9 4.5 5.2 3 3.6 2.1 2 2.6 2 4.9c0 2.2.9 7.5 1.5 8.4.9 1.5 2.6 1.8 4.4 1.5-3 .5-4.1 2.4-2.4 4.5 1.9 2.4 4.6.4 5.6-2 .5-1.1.8-1.9.9-2.3.1.4.4 1.2.9 2.3 1 2.4 3.7 4.4 5.6 2 1.7-2.1.6-4-2.4-4.5 1.8.3 3.5 0 4.4-1.5.6-.9 1.5-6.2 1.5-8.4 0-2.3-1.6-2.8-3.2-1.9-2.7 1.5-5.7 5.6-6.8 7.8Z"/></svg>
        <span>Bluesky</span>
      </a>
      <a class="d d-ikinci pay-b pay-fb" href="#" data-pay="facebook" title="Facebook" aria-label="Facebook" target="_blank" rel="noopener">
        <svg viewBox="0 0 24 24" aria-hidden="true" fill="currentColor" stroke="none"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.7-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.7l-.4 2.9h-2.3v7A10 10 0 0 0 22 12Z"/></svg>
        <span>Facebook</span>
      </a>
      <a class="d d-ikinci pay-b pay-mail" href="#" data-pay="eposta" title="<?= $L ? 'E mail' : 'E-posta' ?>" aria-label="<?= $L ? 'E mail' : 'E-posta' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="m3 6 9 6.5L21 6"/></svg>
        <span><?= $L ? 'E mail' : 'E-posta' ?></span>
      </a>
      <button class="d d-ikinci pay-b pay-kop" type="button" data-pay="kopya" title="<?= $L ? 'Copy link' : 'Bağlantıyı kopyala' ?>" aria-label="<?= $L ? 'Copy link' : 'Bağlantıyı kopyala' ?>">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 13a4 4 0 0 0 5.7.3l3-3a4 4 0 0 0-5.7-5.7L11.5 6"/><path d="M14 11a4 4 0 0 0-5.7-.3l-3 3A4 4 0 0 0 11 19.4l1.4-1.4"/></svg>
        <span><?= $L ? 'Copy link' : 'Bağlantıyı kopyala' ?></span>
      </button>
    </div>

    <details class="pay-ac kart" id="payIg">
      <summary><?= $L ? 'Sharing on Instagram' : 'Instagram\'da paylaşmak' ?></summary>
      <p><?= $L
        ? 'Instagram does not accept links from other sites, so a ready made image is prepared for you. Download it, post it, and paste the caption below. The permanent address is written on the image itself.'
        : 'Instagram başka sitelerden bağlantı kabul etmez; bu yüzden sizin için hazır bir görsel üretilir. Görseli indirin, paylaşın ve altına aşağıdaki metni yapıştırın. Kalıcı adres görselin üzerinde yazılıdır.' ?></p>
      <div class="satir">
        <a class="d d-vurgu d-kucuk" href="<?= esc($kartKare) ?>" download><?= $L ? 'Download square image (1080x1080)' : 'Kare görseli indir (1080x1080)' ?></a>
        <a class="d d-ikinci d-kucuk" href="<?= esc($kartHik) ?>" download><?= $L ? 'Download story image (1080x1920)' : 'Hikâye görselini indir (1080x1920)' ?></a>
      </div>
      <label for="igMetin"><?= $L ? 'Caption' : 'Gönderi metni' ?></label>
      <textarea id="igMetin" rows="6" readonly></textarea>
      <button class="d d-sessiz d-kucuk" type="button" data-kopya-alan="igMetin"><?= $L ? 'Copy caption' : 'Metni kopyala' ?></button>
    </details>

    <details class="pay-ac kart" id="payIn">
      <summary><?= $L ? 'A ready text for LinkedIn' : 'LinkedIn için hazır metin' ?></summary>
      <p><?= $L
        ? 'LinkedIn shows only the link. A longer, professional text works better there; copy the one below and paste it above the link.'
        : 'LinkedIn yalnızca bağlantıyı gösterir. Orada daha uzun ve mesleki bir metin daha iyi karşılık bulur; aşağıdakini kopyalayıp bağlantının üstüne yapıştırabilirsiniz.' ?></p>
      <label for="inMetin"><?= $L ? 'Post text' : 'Gönderi metni' ?></label>
      <textarea id="inMetin" rows="8" readonly></textarea>
      <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center">
        <button class="d d-sessiz d-kucuk" type="button" data-kopya-alan="inMetin"><?= $L ? 'Copy text' : 'Metni kopyala' ?></button>
        <?php /* Metni kopyaladıktan SONRA gidilir; sıra bilerek böyledir.
                 LinkedIn'in paylaşım penceresi metni almaz, yalnızca
                 bağlantıyı alır: metin panoda hazır olmazsa kullanıcı
                 boş bir kutunun karşısına düşer. */ ?>
        <a class="d d-sessiz d-kucuk" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= rawurlencode($url) ?>" target="_blank" rel="noopener"><?= $L ? 'Open LinkedIn' : 'LinkedIn\'i aç' ?></a>
      </div>
    </details>

    <div class="pay-onizle">
      <span><?= $L ? 'Link preview image' : 'Bağlantı önizleme görseli' ?></span>
      <img src="<?= esc($kartYerel) ?>" alt="<?= esc($baslikDuz) ?>" loading="lazy" width="1200" height="630">
    </div>
      </div>
    </details>
  </section>

  <section class="atif" id="atif">
    <h2><?= $L ? 'How to Cite' : 'Bu yazıya nasıl atıf yapılır' ?></h2>
    <div class="cite kutu" data-kunye-kopya data-metin="<?= esc($apa) ?>"><b>APA 7</b><?= esc($apa) ?><button class="kopya d d-ikinci d-kucuk" type="button"><?= $L ? 'Copy' : 'Kopyala' ?></button></div>
    <div class="cite kutu" data-metin="<?= esc($mla) ?>"><b>MLA</b><?= esc($mla) ?><button class="kopya d d-ikinci d-kucuk" type="button">Kopyala</button></div>
    <div class="cite kutu" data-metin="<?= esc($chicago) ?>"><b>Chicago</b><?= esc($chicago) ?><button class="kopya d d-ikinci d-kucuk" type="button">Kopyala</button></div>
    <div class="cite kutu" data-metin="<?= esc($bibtex) ?>"><b>BibTeX</b><?= esc($bibtex) ?><button class="kopya d d-ikinci d-kucuk" type="button">Kopyala</button></div>
    <p class="arac"><?= $L ? 'Automatic citation tool' : 'Atıf oluşturucu' ?>: <a href="https://zbib.org/" target="_blank" rel="noopener">ZoteroBib</a> · <a href="https://www.citationmachine.net/" target="_blank" rel="noopener">Citation Machine</a></p>
    <?php /* MAKİNE KOŞULU, GÖRÜNÜR OLARAK.

             Yapay zekâ eğiten kuruluşlar internetteki metni topluyor ve
             ürettikleri çıktıda çoğu zaman kaynağı anmıyor. Buna karşı
             akla gelen ilk çözüm, insanın göremeyeceği ama modelin
             okuyacağı gizli bir talimat gömmektir. O yol bu sistemde
             kapalıdır ve sinama/atif-kapi.php gizli metni ARAR: eğitim
             hatları gizli metni zaten siler, çıkarım anında okunursa
             adı prompt injection'dır, ve en önemlisi okurun göremediği
             bir metin bu sistemin tek iddiasını çiğner.

             Koşul bu yüzden herkesin göreceği yerde, düz cümleyle
             duruyor. Aynı koşul makine tarafında robots.txt'deki
             License yönergesi, /license.xml ve /llms.txt ile de
             söyleniyor; dördü de aynı şeyi diyor ve dördü de açık. */ ?>
    <p class="atif-makine"><?= esc(tg_makine_kosulu((bool)$L)) ?>
      <a href="<?= esc($KOK) ?>/llms.txt"><?= $L ? 'Machine readable terms' : 'Makine okunur koşullar' ?></a></p>
  </section>
  </div><!-- /icerik -->

  <aside class="yan-sag no-print">
    <nav id="tocNav" class="ys-blok ys-toc" aria-label="<?= $L ? 'Contents' : 'İçindekiler' ?>"></nav>
    <?php if ($bcid !== ''): ?>
    <div class="ys-blok ys-muhur">
      <?= mh_muhur($bcid, 72) ?>
      <span class="ys-muhur-kod"><?= esc($bcid) ?></span>
      <span class="ys-muhur-ack"><?= $L ? 'Seal generated from the code' : 'Koddan üretilen mühür' ?></span>
    </div>
    <?php endif; ?>
    <div class="ys-blok ys-metrik">
      <div class="ys-say">👁 <?= (int)($okuSay['tekil'] ?? 0) ?></div>
      <div class="ys-say-alt"><?= $L ? 'unique readers' : 'tekil okuyucu' ?></div>
    </div>
    <div class="ys-blok">
      <button type="button" class="d d-ikinci d-genis" onclick="window.print()"><?= $L ? 'Download PDF' : 'PDF İndir' ?></button>
      <a class="d d-ikinci d-genis" href="#atif"><?= $L ? 'Cite this article' : 'Kaynak Göster' ?></a>
      <?php /* Yer imi. Beğeni burada bir onay oyu değil bir yer imidir:
               "bunu listeme alıyorum, başına bir şey gelirse haberim
               olsun". Hiçbir sıralamayı ve hiçbir onay sayımını
               etkilemez; kimin beğendiği açık edilmez, yalnız toplam
               görünür. Betik bu düğmeyi data-bgn ile bulur, yerinden
               değil — taşınması betiği bozmaz. */ ?>
      <div class="bgn no-print">
        <button class="d d-ikinci d-genis" type="button" data-bgn="<?= esc((string)($yazi['id'] ?? '')) ?>"
                aria-pressed="<?= tg_begendi_mi($hsO, (string)($yazi['id'] ?? '')) ? 'true' : 'false' ?>">
          <?= kim_ikon('imi', 17) ?>
          <span data-bgn-yazi><?= tg_begendi_mi($hsO, (string)($yazi['id'] ?? ''))
            ? ($L ? 'In your list' : 'Listemde')
            : ($L ? 'Add to my list' : 'Listeme ekle') ?></span>
          <span class="bgn-say" data-bgn-say<?= (int)($yazi['begeni'] ?? 0) > 0 ? '' : ' hidden' ?>><?= (int)($yazi['begeni'] ?? 0) ?></span>
        </button>
        <span class="bgn-ack" data-bgn-ack><?= $L
          ? 'You are notified when a reviewer report, a panel decision or a note arrives.'
          : 'Hakem raporu, kurul kararı ya da şerh geldiğinde haberdar olursunuz.' ?></span>
      </div>
    </div>
    <div class="ys-blok ys-bilgi">
      <h4><?= $L ? 'Article info' : 'Makale bilgisi' ?></h4>
      <?php /* İki satır, çünkü burada iki ayrı olgu var ve biri ötekinin
               yerine geçemez: çalışmanın hangi yolda yürüdüğü (Tür) ile o
               yolun neresinde olduğu (Değerlendirme). Tek satıra indirilirse
               ya yol söylenip aşama gizlenir (bugünkü kusur: raporsuz
               çalışma "Hakemli" görünür), ya da aşama söylenip yol kaybolur
               ve okur çalışmanın hakemliğe hiç açılıp açılmadığını bilemez.
               "Tür" satırı bu yüzden bitmiş bir iş gibi okunmayan bir sözle
               yolu adlandırır; iddiayı yalnızca "Değerlendirme" satırı taşır.
               Geri çekilmişte değerlendirme satırı basılmaz: hemen altındaki
               "Kayıt durumu" satırı zaten aynı şeyi söyler. */ ?>
      <?php /* Üç değer: yolda / düşmüş / hiç girmemiş. Düşmüş çalışmaya
               yalnızca "Yazı" demek, geçmişini künyeden siler. */ ?>
      <div class="ys-sat"><span><?= $L ? 'Type' : 'Tür' ?></span><b><?= $hakemYolda
        ? ($L ? 'Peer-review track' : 'Hakemli süreç')
        : ($hakemDustu ? ($L ? 'Article (rejected in review)' : 'Yazı (hakem reddi)') : ($L ? 'Article' : 'Yazı')) ?></b></div>
      <?php if ($hakemGormus && !$geriCekildi): ?><div class="ys-sat"><span><?= $L ? 'Review' : 'Değerlendirme' ?></span><b><?= esc(tg_asama_metni($asama, (bool)$L)) ?></b></div><?php endif; ?>
      <div class="ys-sat"><span><?= $L ? 'Access' : 'Erişim' ?></span><b><?= $L ? 'Open Access' : 'Açık Erişim' ?></b></div>
      <div class="ys-sat"><span><?= $L ? 'License' : 'Lisans' ?></span><b>CC BY 4.0</b></div>
      <div class="ys-sat"><span><?= $L ? 'Published' : 'Yayın tarihi' ?></span><b><?= esc($tarih) ?></b></div>
      <div class="ys-sat"><span><?= $cokYazar ? ($L ? 'Authors' : 'Yazarlar') : ($L ? 'Author' : 'Yazar') ?></span><b><?php foreach ($yazarListe as $yi => $ya): ?><?php if ($yi): ?>, <?php endif; ?><button class="yazar-ac" type="button" data-yazarpop data-yi="<?= $yi ?>"><?= esc($ya['ad']) ?></button><?php endforeach; ?></b></div>
      <?php /* "DOĞRULANDI" DEMİYORUZ ARTIK. Sistem etik kurul iznini
               doğrulamıyordu; bu satır doğrulanmamış bir şeyi
               doğrulanmış gösteriyordu. Yazan şey ne yapıldıysa odur:
               yazar BEYAN ETTİ. Beyanın kendisi de burada yazılı —
               kurul adı, tarih ve numara— ki isteyen veren kurula
               sorabilsin. Doğrulanabilirlik, rozette değil, sorulacak
               adrestedir. */ ?>
      <?php /* SATIR ARTIK HER ZAMAN BASILIR. Eskiden yalnız durum boş
               değilse basılırdı; arşiv kayıtlarında hiçbir şey
               görünmüyordu ve okur "gerekmiyor" ile "sorulmadı"yı
               ayırt edemiyordu. Susmak da bir yanıttır. */ ?>
      <div class="ys-sat"><span><?= $L ? 'Ethics approval' : 'Etik kurul' ?></span><b style="color:<?= esc(tg_etik_hal_renk($etikHal)) ?>"><?= esc(tg_etik_hal_ad($etikHal, (bool)$L)) ?></b></div>
      <?php if ($etikHal === 'sorulmadi' && tg_etik_arsiv_notu((bool)$L) !== ''): ?>
      <div class="ys-sat ys-not"><span></span><b><?= esc(tg_etik_arsiv_notu((bool)$L)) ?></b></div>
      <?php endif; ?>
      <?php if ($etikHal === 'beyanli'): ?>
      <div class="ys-sat"><span><?= $L ? 'Committee' : 'Kurul' ?></span><b><?= esc((string)$etik['kurul']) ?></b></div>
      <div class="ys-sat"><span><?= $L ? 'Decision' : 'Karar' ?></span><b><?= esc(trim((string)$etik['tarih'] . ' / ' . (string)$etik['no'], ' /')) ?><?php $eb = trim((string)($etik['belge'] ?? '')); if ($eb !== '' && preg_match('~^https?://~i', $eb)): ?> <a href="<?= esc($eb) ?>" rel="noopener nofollow" target="_blank"><?= $L ? 'document' : 'belge' ?></a><?php endif; ?></b></div>
      <?php if ($etikSonra !== ''): ?><div class="ys-sat"><span><?= $L ? 'Declared on' : 'Beyan tarihi' ?></span><b><?= esc(substr($etikSonra, 0, 10)) ?> <small><?= $L ? '(added after publication)' : '(yayımdan sonra eklendi)' ?></small></b></div><?php endif; ?>
      <?php endif; ?>
      <?php if (($veri['beyan'] ?? '') !== ''): ?><div class="ys-sat"><span><?= $L ? 'Data and code' : 'Veri ve kod' ?></span><b><?php
        $vu = trim((string)($veri['url'] ?? ''));
        $vm = tg_veri_beyan_ad((string)$veri['beyan'], $L);
        if ($vu !== '' && preg_match('#^https?://#i', $vu)) {
            echo '<a class="yazar-l" href="' . esc($vu) . '" target="_blank" rel="noopener">' . esc($vm) . ' &#8599;</a>';
        } else { echo esc($vm); }
      ?></b></div><?php endif; ?>
      <?php /* ---- YAZARIN BEYANLARI (kurul kararı, 15 Ağustos 2026) ----
               Çıkar çatışması ve fon, gönderim anında beyan ediliyor ve
               burada YAYIMLANIYOR. Beyan alınıp gösterilmeseydi,
               sorulmasının bir anlamı kalmazdı.

               NİÇİN BU SİSTEMDE AYRICA ÖNEMLİ: burada hakemlik açıktır,
               hakemin adı ve raporu yayımlanır. Okur hakemin bağını
               görüp yazarın bağını göremiyordu; tek yönlü açıklık,
               açıklık değildir.

               "YOK" DA BASILIR: boş bırakmak ile "yoktur" demek aynı
               şey değildir ve okurun ikisini ayırt edebilmesi gerekir.
               Beyan hiç alınmamışsa (eski kayıtlar) satır çizilmez —
               olmayan bir beyanı "yok" diye göstermek, verilmemiş bir
               sözü verilmiş saymak olurdu. */ ?>
      <?php $byn = is_array($yazi['beyan'] ?? null) ? $yazi['beyan'] : []; ?>
      <?php if (($byn['cikar'] ?? '') !== ''): ?><div class="ys-sat"><span><?= $L ? 'Conflict of interest' : 'Çıkar çatışması' ?></span><b><?= ($byn['cikar'] === 'var')
        ? esc(trim((string)($byn['cikar_ack'] ?? '')) !== '' ? (string)$byn['cikar_ack'] : ($L ? 'Declared' : 'Var, beyan edildi'))
        : ($L ? 'None declared' : 'Yok olarak beyan edildi') ?></b></div><?php endif; ?>
      <?php if (($byn['fon'] ?? '') !== ''): ?><div class="ys-sat"><span><?= $L ? 'Funding' : 'Fon' ?></span><b><?= ($byn['fon'] === 'var')
        ? esc(trim((string)($byn['fon_kaynak'] ?? '')) !== '' ? (string)$byn['fon_kaynak'] : ($L ? 'Funded' : 'Fon aldı'))
        : ($L ? 'No funding' : 'Fon almadı') ?></b></div><?php endif; ?>
      <?php /* ---- ÖLÇÜLER ----
               Yazara sorulmadı, metinden sayıldı. Okur için künyenin
               parçasıdır: bir çalışmanın uzunluğu ve kaç şekil taşıdığı,
               okumaya ayıracağı zamanı belirler. */ ?>
      <?php $olc = is_array($yazi['olcu'] ?? null) ? $yazi['olcu'] : []; ?>
      <?php if ((int)($olc['kelime'] ?? 0) > 0): ?><div class="ys-sat"><span><?= $L ? 'Length' : 'Uzunluk' ?></span><b><?php /* k_sayi() k/veri.php'dedir ve bu sayfa onu yüklemiyor:
                 çağırmak ölümcül hataya düşürdü (ölçüldü, sayfa <b>'nin
                 ortasında kesildi). Biçimlendirme burada yapılır; bir
                 modülü tek bir sayı için yüklemek, bağımlılığı sayının
                 kendisinden büyük yapardı. */
        echo number_format((int)$olc['kelime'], 0, ',', $L ? ',' : '.'); ?> <?= $L ? 'words' : 'kelime' ?><?php
        $sk = (int)($olc['sekil'] ?? 0); $cz = (int)($olc['cizelge'] ?? 0);
        if ($sk) echo ' · ' . (int)$sk . ' ' . ($L ? 'figure(s)' : 'şekil');
        if ($cz) echo ' · ' . (int)$cz . ' ' . ($L ? 'table(s)' : 'çizelge');
      ?></b></div><?php endif; ?>
      <?php if ($geriCekildi): ?><div class="ys-sat"><span><?= $L ? 'Record status' : 'Kayıt durumu' ?></span><b style="color:var(--kirmizi)"><?= $L ? 'Retracted' : 'Geri çekildi' ?></b></div><?php endif; ?>
      <?php if ($nekiTam): ?><div class="ys-sat"><span><abbr class="neki" title="<?= esc($L ? 'Permanent identifier' : 'Kalıcı kimlik') ?>"><?= esc($TAMGA_AD) ?></abbr></span><b><a class="yazar-l" href="<?= esc($nekiUrl) ?>"><?= esc($nekiTam) ?></a></b></div><?php endif; ?>
      <?php if ($doi !== ''): ?><div class="ys-sat"><span>DOI</span><b><a class="yazar-l" href="https://doi.org/<?= esc($doi) ?>" target="_blank" rel="noopener"><?= esc($doi) ?></a></b></div><?php endif; ?>
      <?php if ($hakemGormus && $hakemDolu): ?><div class="ys-sat"><span><?= $L ? 'Reviewers' : 'Hakem' ?></span><b><?= count($hakemDolu) ?></b></div><?php endif; ?>
      <?php /* Metnin parmak izi. Yayımlanması, metnin sonradan sessizce
               değiştirilip değiştirilmediğinin herkesçe denetlenebilmesi
               içindir: değer tutmuyorsa metin değişmiş demektir.

               Değer ile onu sınayan düğme TEK bir kabın (#piArac) içinde
               durur; ikisi ayrı ayrı yazılmaz. Sebebi aşağıdaki taşıma:
               dar ekranda bu kap olduğu gibi makalenin akışına iner. Ayrı
               dursalardı taşınan düğme, karşılaştırdığı değeri arkasında
               bırakırdı ve okur neyle kıyasladığını göremezdi. Kap
               biçimsizdir (kendi kuralı yoktur), bu yüzden geniş ekranda
               satır da düğme de bugün nasılsa öyle çizilir. */ ?>
      <?php
      /* Basılan değer, okurun BU SAYFADA okuduğu metnin özetidir.
         Eskiden her iki dilde de taban alanların (baslik/ozet/metin/
         kaynakca) özeti basılıyordu; İngilizce sayfa ise çeviriyi
         basar. Çeviri sessizce değiştirildiğinde değer değişmiyor ve
         araç yine "tutuyor" diyordu. Okumadığı bir metin için okura
         verilen bu güvence, aracın var oluş sebebini tersine
         çeviriyordu. Ölçüldü (dogrula-kapi.php 9. bölüm) ve kapandı.

         Arşiv dökümünde yayımlanan değer taban alanların özetidir ve
         DEĞİŞMEDİ; ikisi ayrıştığında ikincisi de ayrıca basılır ki
         okurun başka ellerdeki listeyle karşılaştırma yolu kapanmasın. */
      $piBu     = tg_metin_ozeti_dil($yazi, (bool)$L);
      $piArsiv  = tg_metin_ozeti($yazi);
      $piAyrik  = ($piBu !== $piArsiv);
      ?>
      <div class="pi-blok" id="piArac"<?php if ($bcid !== ''): ?>
           data-uc="/api/parmak?tamga=<?= esc(rawurlencode($bcid)) ?>"
           data-dosya="kutadgu-kaynak-<?= esc(preg_replace('/[^A-Za-z0-9.\-]/', '', $bcid)) ?><?= $piAyrik ? '-en' : '' ?>.txt"<?php endif; ?>>
        <div class="ys-sat ys-ozet"><span><abbr title="<?= esc($L ? 'A fingerprint of the text on this page. If that text is altered, this value changes.' : 'Bu sayfadaki metnin parmak izi. O metin değişirse bu değer de değişir.') ?>"><?= $L ? 'Fingerprint' : 'Parmak izi' ?></abbr></span><b><code id="piYayin"><?= esc($piBu) ?></code></b></div>
        <?php if ($piAyrik): ?>
        <?php /* İki değer ayrıştığında ikisi de yazılır. Yalnız birini
                 basmak, ya okurun okuduğu metni kapsamayan bir sayı
                 göstermek ya da dökümdeki listeyle bağı koparmak olurdu. */ ?>
        <div class="ys-sat ys-ozet"><span><abbr title="<?= esc($L ? 'The value published in the archive dump. It is the fingerprint of the base text, not of this translation.' : 'Arşiv dökümünde yayımlanan değer. Bu çevirinin değil, taban metnin parmak izidir.') ?>"><?= $L ? 'Archive record' : 'Arşiv kaydı' ?></abbr></span><b><code id="piArsiv"><?= esc($piArsiv) ?></code></b></div>
        <?php endif; ?>
        <?php /* ---- DOĞRULA ----
                 Yukarıdaki değeri sunucu yazar. Sunucunun kendi yazdığı bir
                 değeri yine kendisinin "doğrudur" diye onaylaması hiçbir şey
                 göstermez. Bu yüzden düğme sunucuya "doğru mu" diye sormaz:
                 özeti alınan HAM KAYNAĞI çeker ve hesabı okurun tarayıcısına
                 yaptırır. Karşılaştırma okurun kendi makinesinde olur.
                 Kaynak yalnızca tamgayla istenir; tamgası olmayan bir kayıtta
                 düğme basılmaz, çünkü doğrulanacak kalıcı bir kimlik yoktur.
                 Böyle bir kayıtta kap yine taşınır: düğme olmasa da parmak
                 izinin kendisi dar ekranda da okunabilmelidir; okunamayan
                 bir değer başka ellerdeki listeyle karşılaştırılamaz. */ ?>
        <?php if ($bcid !== ''): ?>
        <div class="pi-arac no-print">
          <button type="button" class="d d-ikinci d-genis" id="piDugme"><?= $L ? 'Verify' : 'Doğrula' ?></button>
          <div class="pi-cikti" id="piCikti" hidden></div>
        </div>
        <?php endif; ?>
      </div>
      <?php if (trim((string)$anahtar) !== ''): ?>
      <div class="ys-anah"><?php foreach (array_slice(array_filter(array_map('trim', preg_split('/[,;]+/', (string)$anahtar))), 0, 8) as $a): ?><span class="rz rz-cizgi"><?= esc($a) ?></span><?php endforeach; ?></div>
      <?php endif; ?>
    </div>
  </aside>
</div><!-- /duzen3 -->

  <?php /* Basılı belgenin sonu: kâğıda geçen bir metnin nereden ve ne
           zaman alındığı üstünde yazılı olmalıdır; yoksa sonradan hangi
           sürüm olduğu anlaşılamaz. */ ?>
  <div class="baski baski-son">
    <?= $L ? 'This document was taken from' : 'Bu belge' ?>
    <b><?= esc($atifUrl) ?></b>
    <?= $L ? 'on' : 'adresinden' ?> <b><?= esc(date('d.m.Y')) ?></b><?= $L ? '.' : ' tarihinde alınmıştır.' ?>
    <?= $L
      ? 'The version on that address is the authoritative one; if the work is corrected or retracted, it is recorded there.'
      : 'O adresteki hâli geçerli olandır; çalışma düzeltilir ya da geri çekilirse kaydı orada tutulur.' ?>
    <?= $L
      ? 'Reviewer reports, decisions and the whole editorial record are published openly on the same page.'
      : 'Hakem raporları, kararlar ve bütün editoryal kayıt aynı sayfada açıkça yayımlanır.' ?>
  </div>

  <footer class="lisans">
    <p><span class="cc">CC BY 4.0</span> · <?= $L ? 'This work is open access. You may share and adapt it, provided you give appropriate credit to' : 'Bu çalışma açık erişimdir. Kaynak göstererek paylaşabilir ve uyarlayabilirsiniz; atıf' ?> <?= esc($yazar) ?><?= $L ? '.' : ' şeklinde verilmelidir.' ?> <a href="https://creativecommons.org/licenses/by/4.0/deed.<?= $lang ?>" target="_blank" rel="noopener"><?= $L ? 'License details' : 'Lisans ayrıntıları' ?></a></p>
    <p>© <?= esc($yil ?: date('Y')) ?> <?= esc($yazar) ?>. <?= $L ? 'All rights of the work belong to the author.' : 'Eserin telif hakları yazara aittir.' ?></p>
  </footer>
</main>

<footer class="alt no-print">
  <div class="kap alt-ic">
    <div>
      <h4><?= esc($MARKA) ?></h4>
      <p><?= $L
        ? 'Named after the Kutadgu Bilig: knowledge that brings fortune and wellbeing. An independent, open access academic publishing system.'
        : 'Adını Kutadgu Bilig\'den alır: kut veren, insanı mutluluğa eriştiren bilgi. Açık erişimli, bağımsız akademik yayın sistemi.' ?></p>
    </div>
    <div>
      <h4><?= $L ? 'Publishing' : 'Yayın' ?></h4>
      <ul>
        <li><a href="/yazilar.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'All works' : 'Bütün çalışmalar' ?></a></li>
        <li><a href="/basvuru.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Submit a work' : 'Çalışma gönder' ?></a></li>
        <li><a href="/hakemlik.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Peer review process' : 'Hakemlik süreci' ?></a></li>
        <li><a href="/ilkeler.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Editorial policies' : 'Yayın ilkeleri' ?></a></li>
        <li><a href="/yz.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Use of artificial intelligence' : 'Yapay zekâ kullanımı' ?></a></li>
        <li><a href="/bildiri.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Declaration' : 'Bildiri' ?></a></li>
        <li><a href="/iletisim.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Contact' : 'İletişim' ?></a></li>
      </ul>
    </div>
    <div>
      <h4><?= $L ? 'Identity' : 'Kimlik' ?></h4>
      <ul>
        <li><a href="/ilkeler.php<?= $L ? '?lang=en' : '' ?>#tamga"><?= esc($TAMGA_AD) ?> <?= $L ? 'explained' : 'nedir' ?></a></li>
        <li><a href="https://creativecommons.org/licenses/by/4.0/" rel="license noopener" target="_blank">CC BY 4.0</a></li>
        <?php if ((string)tg_ayar('ana_site', '') !== ''): ?>
        <li><a href="<?= esc((string)tg_ayar('ana_site', '')) ?>" rel="noopener"><?= esc((string)tg_ayar('ana_site_ad', '')) ?></a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
  <div class="kap alt-son">
    <span>&copy; <?= date('Y') ?> <?= esc($MARKA) ?></span>
    <span><?= $L ? 'Open access, free of charge, ad free.' : 'Açık erişim, ücretsiz, reklamsız.' ?></span>
    <span class="ara-oto"><a href="/kurul.php<?= $L ? '?lang=en' : '' ?>"><?= $L ? 'Founding board' : 'Kurucu kurul' ?></a></span>
    <span class="ara-oto"><?= $L ? 'Software' : 'Yazılım' ?>: <a href="<?= esc(tg_kaynak_adres()) ?>" rel="noopener">Kutadgu, AGPL-3.0</a></span>
  </div>
</footer>
</div><?php /* .sahne */ ?>

<div id="ymodal-ov" class="ymodal-ov"><div id="ymodal-box" class="ymodal kart"></div></div>
<script>
/* Yazar popup (ScienceDirect tarzı) */
(function(){
  var YLIST = <?= json_encode(array_values($yazarListe), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var ov=document.getElementById('ymodal-ov'), box=document.getElementById('ymodal-box');
  function esc(s){var d=document.createElement('div');d.textContent=s==null?'':s;return d.innerHTML;}
  function orcidUrl(o){o=(o||'').trim();if(!o)return '';return /^https?:/i.test(o)?o:'https://orcid.org/'+o.replace(/^orcid\.org\//i,'');}
  function scopusUrl(s){s=String(s||'').trim();if(!s)return '';if(/^https?:/i.test(s))return s;
    return 'https://www.scopus.com/authid/detail.uri?authorId='+s.replace(/\D/g,'');}
  function titVar(s){return /^\s*(prof|do[çc]|dr|öğr|ogr)\.?/i.test(s||'');}
  function ac(i){
    var YB=YLIST[i]||YLIST[0]||{};
    var ad=YB.ad||'<?= $L ? "Author" : "Yazar" ?>';
    var h='<button class="kapa d d-ikinci d-im" type="button" data-kapat="1">✕</button>';
    h+='<h3>'+esc((YB.unvan&&!titVar(ad)?YB.unvan+' ':'')+ad)+'</h3>';
    if(YB.kurum) h+='<div class="sat">'+esc(YB.kurum)+'</div>';
    if(YB.eposta) h+='<div class="sat">✉ <a href="mailto:'+esc(YB.eposta)+'">'+esc(YB.eposta)+'</a></div>';
    if(YB.orcid) h+='<div class="sat"><a href="'+esc(orcidUrl(YB.orcid))+'" target="_blank" rel="noopener">ORCID '+esc(YB.orcid.replace(/^https?:\/\/(www\.)?orcid\.org\//i,''))+' ↗</a></div>';
    if(YB.scopus) h+='<div class="sat"><a href="'+esc(scopusUrl(YB.scopus))+'" target="_blank" rel="noopener"><?= $L ? "Scopus Author ID" : "Scopus Author ID" ?> '+esc(String(YB.scopus).replace(/^.*authorId=/i,''))+' ↗</a></div>';
    if(YB.web) h+='<div class="sat"><a href="'+esc(YB.web)+'" target="_blank" rel="noopener"><?= $L ? "Website" : "Web sayfası" ?> ↗</a></div>';
    if(YB.kisiUrl) h+='<div class="diger"><a href="'+esc(YB.kisiUrl)+'"><?= $L ? "This person\'s record in the system" : "Bu kişinin sistemdeki kaydı" ?></a></div>';
    if(YB.digerUrl) h+='<div class="diger"><a href="'+esc(YB.digerUrl)+'"><?= $L ? "More documents by this author" : "Bu yazarın diğer yazıları" ?></a></div>';
    box.innerHTML=h; ov.classList.add('acik');
  }
  document.querySelectorAll('[data-yazarpop]').forEach(function(b){b.addEventListener('click',function(){ac(parseInt(b.dataset.yi||'0',10));});});
  ov.addEventListener('click',function(e){if(e.target===ov||e.target.getAttribute('data-kapat'))ov.classList.remove('acik');});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')ov.classList.remove('acik');});
})();
document.querySelectorAll('.kopya').forEach(function(b){
  b.addEventListener('click',function(){
    var metin=b.parentNode.getAttribute('data-metin')||'';
    navigator.clipboard.writeText(metin).then(function(){var o=b.textContent;b.textContent='Kopyalandı ✓';setTimeout(function(){b.textContent=o;},1400);});
  });
});
/* Sol içindekiler: başlıklar + şekiller/tablolar */
(function(){
  var nav=document.getElementById('tocNav'); if(!nav) return;
  var govde=document.querySelector('.govde');
  /* 'duzen-iki' sınıfı kalktı: sütun zaten iki (19 Ağustos 2026, sol
     ray kaldırıldı). Liste kurulamıyorsa yalnız içindekiler bölümü
     düşer; ray yerinde kalır, çünkü içinde başka bölümler var. */
  if(!govde){ nav.remove(); return; }
  function kaydir(el){ if(el) el.scrollIntoView({behavior:'smooth',block:'start'}); }
  /* ---------- İÇİNDEKİLER: ALT BAŞLIKLAR KATLANIR ----------
     BİLDİRİLEN KUSUR: "içindekiler kısmı liste şeklinde aşağı kadar
     uzuyor."

     Ölçüldü ve doğruydu: on bölümlük bir çalışmada h2+h3 toplamı otuz
     üç satır, her satır 44 piksel; liste 1452 piksel. Ekran 900. Yani
     içindekilerin kendisi kaydırılmadan okunamıyordu ve bir gezinme
     aracı, gezinmek için gezinme gerektiriyordu.

     İKİ DEĞİŞİKLİK:
       1. Alt başlıklar (h3) kendi ana başlığının altına KATLANIR.
          Açık duran tek küme, okurun o an içinde bulunduğu bölümdür;
          okur başka bir bölüme geçtiğinde o küme kapanır, yenisi açılır.
          Böylece liste bölüm sayısı kadar satırdır, başlık sayısı
          kadar değil.
       2. Satır yüksekliği 44'ten 32'ye iner. 32, WCAG 2.5.8 AA'nın
          istediği 24'ün üstündedir; hedef küçülmedi, fazlalık alındı.
          Aynı ölçüm .blg-nav'da da yapılmıştı.

     Katlama <details> ile yapılıyor: betik zaten burada, ama <details>
     klavyeyle ve ekran okuyucuyla kendiliğinden çalışır; elle yazılmış
     bir aç/kapa düğmesi o davranışı yeniden üretmek zorunda kalırdı. */
  var basliklar=[].slice.call(govde.querySelectorAll('h2,h3'));
  var html='';
  if(basliklar.length){
    basliklar.forEach(function(h,i){ if(!h.id) h.id='b'+i; });
    html+='<div class="toc-bas"><?= $L ? "Contents" : "İçindekiler" ?></div><div class="toc-liste">';
    var i=0;
    while(i<basliklar.length){
      var h=basliklar[i];
      var ust=(h.textContent||'').replace(/</g,'&lt;');
      if(h.tagName.toLowerCase()==='h3'){
        /* Ana başlığı olmayan bir alt başlık: tek başına durur. */
        html+='<a href="#'+h.id+'" class="tocl alt" data-hid="'+h.id+'">'+ust+'</a>';
        i++; continue;
      }
      var alt=[];
      var j=i+1;
      while(j<basliklar.length && basliklar[j].tagName.toLowerCase()==='h3'){ alt.push(basliklar[j]); j++; }
      if(!alt.length){
        html+='<a href="#'+h.id+'" class="tocl" data-hid="'+h.id+'">'+ust+'</a>';
      }else{
        html+='<div class="toc-grup" data-grup="'+h.id+'">'
             +'<div class="toc-grup-bas">'
             +'<a href="#'+h.id+'" class="tocl" data-hid="'+h.id+'">'+ust+'</a>'
             +'<button type="button" class="toc-ac" aria-expanded="false" aria-controls="tg-'+h.id+'"'
             +' aria-label="<?= $L ? "Show subsections" : "Alt başlıkları göster" ?>"></button>'
             +'</div>'
             +'<div class="toc-alt" id="tg-'+h.id+'">';
        alt.forEach(function(a){
          html+='<a href="#'+a.id+'" class="tocl alt" data-hid="'+a.id+'">'+(a.textContent||'').replace(/</g,'&lt;')+'</a>';
        });
        html+='</div></div>';
      }
      i=j;
    }
    html+='</div>';
  }
  /* ---- ÇİZELGELER KENDİ KAYDIRMA KABINA ALINIR ----
     Gerekçe biçem bölümünde yazılı: blok çizelge içeriğine göre daralıp
     sütunun ortasında yarım kalıyordu. Kap taşmayı üstlenince çizelge
     gerçek çizelge olabilir ve sütunu doldurur. Betiksiz tarayıcıda bu
     satır hiç çalışmaz ve öntanımlı kural yürür — kaybedilen bir şey
     yoktur. */
  [].slice.call(govde.querySelectorAll('table')).forEach(function(t){
    if(t.parentElement && t.parentElement.classList.contains('tablo-kaydir')) return;
    var k=document.createElement('div'); k.className='tablo-kaydir';
    t.parentNode.insertBefore(k,t); k.appendChild(t);
  });
  var sekiller=[].slice.call(govde.querySelectorAll('figure'));
  if(sekiller.length){
    html+='<div class="toc-sekil"><div class="toc-bas"><?= $L ? "Figures & Tables" : "Şekiller ve Tablolar" ?></div>';
    sekiller.forEach(function(f,i){ if(!f.id) f.id='s'+i;
      var cap=f.querySelector('figcaption'); var et=cap?(cap.textContent||'').trim():('#'+(i+1));
      et=et.replace(/\s+/g,' '); if(et.length>46) et=et.slice(0,46)+'...';
      html+='<a href="#'+f.id+'" data-hid="'+f.id+'">'+et.replace(/</g,'&lt;')+'</a>';
    });
    html+='</div>';
  }
  /* ---------- KAYNAKÇA İÇİNDEKİLERE GİRER ----------
     Bildirilen kusur (15 Ağustos 2026): "içindekiler kısmı güzel ama
     kaynakça başlığı da olması gerek orada."

     Doğru: kaynakça .govde'nin İÇİNDE değil, KARDEŞİDİR — ayrı bir
     <section>. İçindekiler yalnız gövdeyi tarıyordu, dolayısıyla
     okuduğu metnin en çok geri dönülen bölümü listede hiç yoktu.
     Gövdeyi taramak yerine "sayfadaki bütün h2'leri" taramak da
     yanlış olurdu: sayfada künye, şerh ve atıf bölümlerinin de
     başlıkları var ve onlar okunan METNİN parçası değil.
     Bu yüzden kaynakça TEK TEK, adıyla eklenir. */
  /* Şekil listesinden SONRA eklenir: gövdesinde h2 olmayan ama şekli
     olan bir çalışmada da kaynakça satırı çıksın diye. Tek başına
     "Kaynakça" yazan bir içindekiler üretilmez — o, içindekiler değil
     tek bir bağlantıdır. */
  var knk=document.getElementById('kaynakca');
  if(knk && html){
    html+='<div class="toc-liste toc-knk"><a href="#kaynakca" class="tocl" data-hid="kaynakca">'
         +(knk.textContent||'').replace(/</g,'&lt;')+'</a></div>';
  }
  nav.innerHTML=html;
  /* Liste boşsa bölüm hiç çizilmesin. */
  if(!html){ nav.remove(); }
  /* ---------- TELEFONDA İÇİNDEKİLER ----------
     ÖLÇÜLEN KUSUR — 19 Ağustos 2026. İçindekiler 19 Ağustos'ta sağ
     rayın içine alındı; o ray 1024'ün altında bütünüyle gizli. Sonuç:
     telefonda içindekiler HİÇ görünmüyordu. (Daha önce de 1400'ün
     altında yoktu, yani bu bir gerileme değil; ama kurulun okuduğu
     ekran telefon ve uzun bir çalışmada en çok orada gerekiyor.)

     RAY ÇİZİLMEDİYSE LİSTE METNİN BAŞINA TAŞINIR. Ölçüt piksel değil,
     ögenin GERÇEKTEN çizilip çizilmediğidir: eşik CSS'te tek yerde
     yazılıdır, betik onu yinelemez. Aynı yol parmak izi aracında da
     kullanılıyor (.pi-yuva).

     KAPALI AÇILIR: telefonda ekranın tamamını kaplayan bir liste,
     okumaya başlamayı geciktirir. Başlık kaç bölüm olduğunu söyler ki
     açmaya değip değmeyeceği açılmadan bilinsin. */
  if(html) (function(){
    var ray=document.querySelector('.yan-sag');
    if(ray && ray.offsetParent!==null) return;          /* ray çizildi: yerinde kalsın */
    var kap=document.querySelector('.govde');
    if(!kap || !kap.parentNode) return;
    var d=document.createElement('details');
    d.className='toc-mobil no-print';
    var oz=document.createElement('summary');
    var say=nav.querySelectorAll('a[data-hid]').length;
    oz.textContent=<?= $L ? "'Contents'" : "'İçindekiler'" ?>+(say?(' ('+say+')'):'');
    d.appendChild(oz);
    d.appendChild(nav);                                  /* aynı öge taşınır, kopyalanmaz */
    kap.parentNode.insertBefore(d, kap);
  })();
  /* ---------- RAY: SIĞIYORSA YAPIŞIR, SIĞMIYORSA SAYFAYLA KAYAR ----------
     Bildirilen kusur — 20 Ağustos 2026: "aşağıda kaydırma çubuğu var,
     burayı normal görmeyecek miyiz."

     Rayın kendi kaydırma alanı kaldırıldı. Yapışkanlık ise koşullu:
     içerik ekrana SIĞIYORSA yapışır (ve o zaman zaten çubuk çıkmaz),
     sığmıyorsa sayfayla birlikte kayar. Ölçüt çalışma zamanında
     bilinebilir, bu yüzden buradadır; betiksiz tarayıcıda ray sıradan
     bir sütundur ve hiçbir şey kaybolmaz.

     Yapışkanlık rayın KENDİSİNE değil içine konan sarmala verilir:
     ray, sütun boyunca uzar (kâğıdı bölen çizgi baştan sona insin
     diye) ve uzayan bir öge yapışamaz. */
  (function(){
    var ray=document.querySelector('.yan-sag'); if(!ray) return;
    var zaman=null;
    function tart(){
      /* Üst şeridin altında kalan yükseklik: yapışan ray oraya sığmalı.
         Sığmıyorsa yapışkanlık kaldırılır — alt ucu hiçbir zaman
         okunamayan yapışık bir ray, kaydırma çubuğunun kendisinden
         kötüdür. */
      var ust=parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--ust'))||58;
      var yer=window.innerHeight-ust-24;
      /* ÖLÇÜT PİKSEL DEĞİL: ray çizildi mi. Eşik biçemde tek yerde
         yazılıdır; betik onu yinelerse ikisi bir gün ayrışır. */
      var cizildi = ray.offsetParent !== null;
      ray.classList.remove('ys-yapisik','ys-toc-yapisik');
      var sigar = cizildi && ray.offsetHeight<=yer;
      ray.classList.toggle('ys-yapisik', sigar);
      /* Sığmıyorsa yalnız içindekiler yapışır. */
      ray.classList.toggle('ys-toc-yapisik', cizildi && !sigar && !!ray.querySelector('.ys-toc'));
    }
    tart();
    window.addEventListener('resize',function(){ clearTimeout(zaman); zaman=setTimeout(tart,150); });
  })();

  nav.addEventListener('click',function(e){
    /* Aç/kapa düğmesi kendi işini görür; bağlantı gibi kaydırmaz. */
    var dg=e.target.closest?e.target.closest('.toc-ac'):null;
    if(dg){ e.preventDefault(); grupAc(dg.closest('.toc-grup'), dg.getAttribute('aria-expanded')!=='true', true); return; }
    var a=e.target.closest('a[data-hid]');if(!a)return;e.preventDefault();
    var t=document.getElementById(a.getAttribute('data-hid'));kaydir(t);
    history.replaceState(null,'','#'+a.getAttribute('data-hid'));
  });
  /* $elle: okur kendi eliyle açtıysa, okuma ilerledikçe kapatılmaz.
     Kendiliğinden kapanan bir küme, açan kişiye "seni dinlemiyorum"
     der; açıklığı okurun elinden almak bu sistemin işi değil. */
  function grupAc(g,ac,elle){
    if(!g)return;
    g.classList.toggle('acik',ac);
    if(elle)g.setAttribute('data-elle',ac?'1':'0');
    var dg=g.querySelector('.toc-ac');
    if(dg)dg.setAttribute('aria-expanded',ac?'true':'false');
  }
  // aktif başlık vurgusu
  var linkler=[].slice.call(nav.querySelectorAll('.tocl'));
  if(basliklar.length && 'IntersectionObserver' in window){
    var gorunen={};
    var io=new IntersectionObserver(function(girisler){
      girisler.forEach(function(g){ gorunen[g.target.id]=g.isIntersecting; });
      var akt=null;
      basliklar.forEach(function(h){ if(gorunen[h.id]&&!akt) akt=h.id; });
      linkler.forEach(function(l){ l.classList.toggle('etkin', l.getAttribute('data-hid')===akt); });
      var ek = nav.querySelector('.tocl.etkin');
      if(ek && nav.scrollHeight > nav.clientHeight){
        var nt = ek.getBoundingClientRect().top - nav.getBoundingClientRect().top + nav.scrollTop;
        if(nt < nav.scrollTop || nt > nav.scrollTop + nav.clientHeight - 40) nav.scrollTop = Math.max(0, nt - 60);
      }
      /* İçinde bulunulan bölümün kümesi açılır, ötekiler kapanır —
         okur elle açtıysa dokunulmaz. */
      if(akt){
        Array.prototype.forEach.call(nav.querySelectorAll('.toc-grup'),function(g){
          if(g.getAttribute('data-elle')==='1')return;
          var icinde=!!g.querySelector('a[data-hid="'+akt+'"]');
          grupAc(g,icinde,false);
        });
      }
    },{rootMargin:'-70px 0px -70% 0px'});
    basliklar.forEach(function(h){ io.observe(h); });
  }
})();
/* Listeye alma. Hesabı olmayan kişi panele yönlendirilir; sayı yalnızca
   sunucunun döndürdüğü değerle güncellenir, sayfada tahmin edilmez. */
(function(){
  var b=document.querySelector('[data-bgn]'); if(!b) return;
  var EN=document.documentElement.lang==='en';
  var yazi=b.querySelector('[data-bgn-yazi]'), say=b.querySelector('[data-bgn-say]'),
      ack=document.querySelector('[data-bgn-ack]');
  var S={ekle:EN?'Add to my list':'Listeme ekle', var:EN?'In your list':'Listemde',
          giris:EN?'Sign in to keep a list of works.':'Çalışma listesi tutmak için hesabınıza girin.',
          hata:EN?'Could not be saved, please try again.':'Kaydedilemedi, tekrar deneyin.'};
  b.addEventListener('click',function(){
    var acik=b.getAttribute('aria-pressed')==='true';
    b.disabled=true;
    fetch('/api/begeni',{method:'POST',credentials:'same-origin',
      headers:{'Content-Type':'application/json'},
      body:JSON.stringify({id:b.getAttribute('data-bgn'),durum:!acik})})
      .then(function(r){ if(r.status===401){ throw new Error('giris'); } return r.json(); })
      .then(function(d){
        b.disabled=false;
        if(!d||!d.ok){ if(ack) ack.textContent=(d&&d.hata)||S.hata; return; }
        b.setAttribute('aria-pressed', d.begendi?'true':'false');
        if(yazi) yazi.textContent = d.begendi?S.var:S.ekle;
        if(say){ var n=parseInt(d.sayi,10)||0; say.textContent=String(n);
                 if(n>0) say.removeAttribute('hidden'); else say.setAttribute('hidden',''); }
        if(ack&&d.mesaj) ack.textContent=d.mesaj;
        try{ sessionStorage.removeItem('kutadgu-hesap-durum'); }catch(e){}
      })
      .catch(function(e){
        b.disabled=false;
        if(e&&e.message==='giris'){ if(ack) ack.textContent=S.giris;
          window.location.href='/panel.php?donus='+encodeURIComponent(window.location.pathname); return; }
        if(ack) ack.textContent=S.hata;
      });
  });
})();
/* Okuma araçları: yazı boyutu + okuma modu (tercihler tarayıcıda hatırlanır) */
(function(){
  var LS=window.localStorage, kok=document.documentElement, govde=document.body;
  var MIN=16,MAX=28,ADIM=2;
  function oku(k,d){try{var v=LS.getItem(k);return v==null?d:v;}catch(e){return d;}}
  function yaz(k,v){try{LS.setItem(k,v);}catch(e){}}
  /* TABAN PUNTO KOD İÇİNDE YAZILI DEĞİL, SAYFADAN OKUNUR.
     Burada "masaüstünde 19, mobilde 17" diye iki sayı yazılıydı ve
     aynı merdiven CSS'te de duruyordu. 20 Ağustos'ta merdiven dört
     basamak daha kazandı (20/21/22/23); iki kopyadan biri güncellenip
     öteki unutulsaydı, A+ düğmesi bir yerden başlayıp başka bir yere
     atlardı. Sayı tek yerdedir: sayfanın kendi biçemi. */
  function tabanBoy(){
    var e=document.querySelector('.sar');
    var v=e?parseFloat(getComputedStyle(e).fontSize):0;
    return (v>=MIN&&v<=MAX)?v:(window.matchMedia('(min-width:900px)').matches?19:17);
  }
  var kayitli=parseFloat(oku('yaziBoyut',''))||0;
  if(kayitli>=MIN&&kayitli<=MAX) kok.style.setProperty('--okuf',kayitli+'px');
  function suanki(){ var v=parseFloat((kok.style.getPropertyValue('--okuf')||''));return (v>=MIN&&v<=MAX)?v:tabanBoy(); }
  function boyAyar(delta){
    var yeni=Math.min(MAX,Math.max(MIN, suanki()+delta));
    kok.style.setProperty('--okuf',yeni+'px'); yaz('yaziBoyut',yeni);
    return yeni;
  }
  function parla(btn){ if(!btn)return; btn.style.transition='none'; btn.style.background='var(--altin)'; btn.style.color='var(--altin-metin)';
    setTimeout(function(){ btn.style.background=''; btn.style.color=''; btn.style.transition=''; },160); }
  var bK=document.getElementById('yaziKucult'),bB=document.getElementById('yaziBuyut'),bO=document.getElementById('okuModu');
  if(bK)bK.addEventListener('click',function(){boyAyar(-ADIM);parla(bK);});
  if(bB)bB.addEventListener('click',function(){boyAyar(ADIM);parla(bB);});
  function modUygula(on){govde.classList.toggle('oku',on);if(bO)bO.setAttribute('aria-pressed',on?'true':'false');yaz('okuModu',on?'1':'0');}
  if(bO)bO.addEventListener('click',function(){modUygula(!govde.classList.contains('oku'));});
  if(oku('okuModu','0')==='1') modUygula(true);
  var bY=document.getElementById('yaslaModu');
  function yasUygula(on){govde.classList.toggle('yasli',on);if(bY)bY.setAttribute('aria-pressed',on?'true':'false');yaz('yaslaModu',on?'1':'0');}
  if(bY)bY.addEventListener('click',function(){yasUygula(!govde.classList.contains('yasli'));});
  if(oku('yaslaModu','0')==='1') yasUygula(true);
})();
</script>
<script>
/* Okuma süresi + okuma derinliği ölçümü (yönetim raporları için) */
(function(){
  var MID=<?= json_encode((string)($yazi['id'] ?? ''), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var MBAS=<?= json_encode(mb_substr((string)$baslikDuz, 0, 200), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  if(!MID) return;
  var otr='';
  try{ otr=sessionStorage.getItem('bh-otr')||''; if(!otr){ otr=Math.random().toString(36).slice(2)+Date.now().toString(36); sessionStorage.setItem('bh-otr',otr); } }catch(e){ otr='x'+Date.now().toString(36); }
  var baslangic=Date.now(), gecen=0, aktif=true, enDerin=0, gonderildi=0;
  function derinlik(){
    var h=document.documentElement.scrollHeight-window.innerHeight;
    if(h<=0) return 100;
    return Math.max(0,Math.min(100,Math.round((window.scrollY/h)*100)));
  }
  function saydir(){ if(aktif){ gecen+=Date.now()-baslangic; } baslangic=Date.now(); }
  window.addEventListener('scroll',function(){ var d=derinlik(); if(d>enDerin) enDerin=d; },{passive:true});
  document.addEventListener('visibilitychange',function(){
    saydir();
    aktif=!document.hidden;
    baslangic=Date.now();
    if(document.hidden) gonder();
  });
  function gonder(){
    saydir();
    var sn=Math.round(gecen/1000);
    if(sn<5) return;                 /* 5 saniyeden kısa ziyaret sayılmaz */
    if(sn<=gonderildi+4) return;     /* aynı veriyi tekrar tekrar yollama */
    gonderildi=sn;
    var veri={id:MID,baslik:MBAS,sure:sn,oran:Math.max(enDerin,derinlik()),
              lang:document.documentElement.lang||'tr',ref:document.referrer||'',oturum:otr};
    try{
      var b=new Blob([JSON.stringify(veri)],{type:'application/json'});
      if(navigator.sendBeacon&&navigator.sendBeacon('/api/oku-kayit',b)) return;
    }catch(e){}
    try{ fetch('/api/oku-kayit',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(veri),keepalive:true}); }catch(e){}
  }
  window.addEventListener('pagehide',gonder);
  window.addEventListener('beforeunload',gonder);
  setInterval(gonder,60000);   /* uzun okumalarda dakikada bir güncelle */
})();
</script>
<script>
/* ---- Sosyal paylaşım: her mecra için ayrı biçim ---- */
(function(){
  var L = <?= $L ? 'true' : 'false' ?>;
  var BAS   = <?= json_encode($baslikDuz, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var YZR   = <?= $payYazarJs ?>;
  var OZET  = <?= json_encode(mb_substr(trim(preg_replace('/\s+/u',' ', strip_tags($ozet))), 0, 600), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var ADRES = <?= json_encode($url, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var ANAH  = <?= json_encode(array_values(array_filter(array_map('trim', preg_split('/[;,]/u', (string)$anahtar)))), JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  <?php /* Paylaşım metni siteden koparak dolaşır; oradaki "Hakemli" sözünü
           sonradan geri almanın yolu yoktur. Bu yüzden burada da yol değil
           aşama taşınır ve aşama sayfadakiyle aynı yerden gelir. */ ?>
  var TUR   = <?= json_encode($asama) ?>;
  <?php /* Aşama adları JS'in içine elle yazılmaz. Buradaki dallar bugün
           doğru söylüyordu, ama tek kaynaktaki metin değiştiğinde onlar
           yerinde kalır ve aynı çalışma paylaşımda başka, sayfada başka
           bir ad taşır; bu kayma bekleyen.php'de bir kez yaşandı. Beş
           aşamanın iki dildeki karşılığı PHP'de üretilip diziyle
           geçiriliyor, JS yalnızca diziden okuyor. */
        $asamaSozluk = [];
        foreach (['cekildi', 'yok', 'aranan', 'suruyor', 'onayli'] as $__as) {
            $asamaSozluk[$__as] = ['tr' => tg_asama_metni($__as, false), 'en' => tg_asama_metni($__as, true)];
        } ?>
  var ASAMA = <?= json_encode($asamaSozluk, JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
  var KARE  = <?= json_encode($kartKare, JSON_UNESCAPED_SLASHES) ?>;

  /* Anahtar kelimelerden etiket üret: Türkçe harfler sadeleşir, boşluk kalkar */
  function etiket(s){
    var m = {'ç':'c','Ç':'C','ğ':'g','Ğ':'G','ı':'i','İ':'I','ö':'o','Ö':'O','ş':'s','Ş':'S','ü':'u','Ü':'U','â':'a','î':'i','û':'u'};
    s = String(s||'').replace(/[çÇğĞıİöÖşŞüÜâîû]/g, function(c){ return m[c]||c; });
    s = s.replace(/[^A-Za-z0-9 ]/g,' ').trim().split(/\s+/)
         .map(function(w,i){ return w.charAt(0).toUpperCase()+w.slice(1); }).join('');
    return s ? '#'+s : '';
  }
  var etiketler = ANAH.slice(0,3).map(etiket).filter(Boolean);
  /* Etiket de bir iddiadır: "#PeerReview" raporsuz bir çalışmanın altında
     durduğunda okur onu çalışmanın hakemlendiğine dair bir söz sayar. Bu
     yüzden yalnızca gerçekten rapor gelmiş aşamalarda eklenir. (Bu satırın
     üstünde aynı işi yapan, hemen ardından ezilen ve "#Hakemli" diyen ölü
     bir satır duruyordu; kalan satır elden çıkarsa o söz geri dönerdi.) */
  var gecti = (TUR==='onayli' || TUR==='suruyor');
  var ortak = L ? (gecti ? ['#OpenAccess','#PeerReview'] : ['#OpenAccess'])
                : (gecti ? ['#AcikErisim','#Hakemli'] : ['#AcikErisim','#Bilim']);

  /* Bilinmeyen bir aşama gelirse hakemsiz sayılır: paylaşımda boş bir
     durum satırı bırakmaktansa en az iddialı sözü söylemek doğrudur. */
  var etiketiTr = (ASAMA[TUR] || ASAMA['yok'])[L ? 'en' : 'tr'];

  /* --- X: kısa, iki satır, en çok 3 etiket --- */
  var xMetin = BAS + (YZR ? ('\n' + YZR) : '')
    + '\n' + (L ? 'Open access, free to read.' : 'Açık erişim, ücretsiz okunur.')
    + (etiketler.length ? ('\n' + etiketler.concat(ortak.slice(0,1)).join(' ')) : '');
  if (xMetin.length > 250) xMetin = BAS + '\n' + (L?'Open access.':'Açık erişim.');

  /* --- LinkedIn: mesleki, özetli, uzun --- */
  var inMetin = BAS + '\n\n'
    + (YZR ? ((L ? 'Author(s): ' : 'Yazar: ') + YZR + '\n') : '')
    + (L ? 'Status: ' : 'Durum: ') + etiketiTr + '\n\n'
    + (OZET ? (OZET.slice(0,420) + (OZET.length>420?'...':'') + '\n\n') : '')
    + (L
       ? 'The work is open access: no fee for the author, none for the reader. In this system peer review is not blind, and the reviewers’ reports are published together with the work, so the assessment itself can be examined.'
       : 'Çalışma açık erişimdir: ne yazardan ne okurdan ücret alınır. Bu sistemde hakemlik kör değildir; hakem raporları çalışmayla birlikte yayımlanır, böylece değerlendirmenin kendisi de denetlenebilir.')
    + '\n\n' + ADRES
    + (etiketler.length ? ('\n\n' + etiketler.concat(ortak).join(' ')) : '');

  /* --- Instagram: bağlantı tıklanamaz, adres yazıyla verilir --- */
  var igMetin = BAS + '\n\n'
    + (YZR ? (YZR + '\n\n') : '')
    + (OZET ? (OZET.slice(0,330) + (OZET.length>330?'...':'') + '\n\n') : '')
    + (L ? 'Read it free: ' : 'Ücretsiz okuyun: ') + ADRES.replace(/^https?:\/\//,'') + '\n'
    + (L ? '(the address is written on the image)' : '(adres görselin üzerinde yazılıdır)') + '\n\n'
    + etiketler.concat(ortak).concat(L?['#Research','#Science']:['#Akademi','#Arastirma']).join(' ');

  /* --- WhatsApp ve Telegram: kişisel, kısa --- */
  var waMetin = BAS + (YZR ? ('\n' + YZR) : '') + '\n\n'
    + (L ? 'Open access, free to read:' : 'Açık erişim, ücretsiz okunabiliyor:') + '\n' + ADRES;

  var epostaKonu = (L ? 'Reading suggestion: ' : 'Okuma önerisi: ') + BAS;
  var epostaGovde = (L ? 'Hello,\n\nI thought this work might interest you.\n\n' : 'Merhaba,\n\nBu çalışmanın ilginizi çekebileceğini düşündüm.\n\n')
    + BAS + '\n' + (YZR ? (YZR + '\n') : '')
    + (OZET ? ('\n' + OZET.slice(0,400) + (OZET.length>400?'...':'') + '\n') : '')
    + '\n' + ADRES + '\n\n'
    + (L ? 'It is open access; no membership or fee is required.' : 'Açık erişimdir; üyelik ya da ücret gerekmez.');

  var e = encodeURIComponent;
  var adresler = {
    x:        'https://x.com/intent/post?text=' + e(xMetin) + '&url=' + e(ADRES),
    whatsapp: 'https://wa.me/?text=' + e(waMetin),
    telegram: 'https://t.me/share/url?url=' + e(ADRES) + '&text=' + e(BAS + (YZR ? (' | ' + YZR) : '')),
    bluesky:  'https://bsky.app/intent/compose?text=' + e(xMetin + '\n' + ADRES),
    facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + e(ADRES),
    eposta:   'mailto:?subject=' + e(epostaKonu) + '&body=' + e(epostaGovde)
  };

  Array.prototype.forEach.call(document.querySelectorAll('[data-pay]'), function(el){
    var t = el.getAttribute('data-pay');
    if (adresler[t]) { el.setAttribute('href', adresler[t]); return; }
    /* Metin taşıyamayan iki mecra aynı biçimde davranır: düğme kendi
       kutusunu açar, kutu metni ve mecraya gitme bağlantısını taşır. */
    var kutular = { instagram: 'payIg', linkedin: 'payIn' };
    if (kutular[t]) {
      el.addEventListener('click', function(){
        var d = document.getElementById(kutular[t]); if(d){ d.open = true; d.scrollIntoView({block:'center',behavior:'smooth'}); }
      });
    }
    if (t === 'kopya') {
      el.addEventListener('click', function(){
        (navigator.clipboard ? navigator.clipboard.writeText(ADRES) : Promise.reject())
          .then(function(){ el.querySelector('span').textContent = L ? 'Copied' : 'Kopyalandı';
            setTimeout(function(){ el.querySelector('span').textContent = L ? 'Copy link' : 'Bağlantıyı kopyala'; }, 1800); })
          .catch(function(){ prompt(L?'Copy the link:':'Bağlantıyı kopyalayın:', ADRES); });
      });
    }
  });

  var ig = document.getElementById('igMetin'); if (ig) ig.value = igMetin;
  var inn = document.getElementById('inMetin'); if (inn) inn.value = inMetin;
  Array.prototype.forEach.call(document.querySelectorAll('[data-kopya-alan]'), function(b){
    b.addEventListener('click', function(){
      var a = document.getElementById(b.getAttribute('data-kopya-alan')); if(!a) return;
      a.select();
      (navigator.clipboard ? navigator.clipboard.writeText(a.value) : Promise.reject())
        .then(function(){ var o=b.textContent; b.textContent = L?'Copied':'Kopyalandı'; setTimeout(function(){b.textContent=o;},1800); })
        .catch(function(){ try{ document.execCommand('copy'); }catch(x){} });
    });
  });

  /* Telefonlarda işletim sisteminin kendi paylaşım penceresi */
  var yerli = document.getElementById('payYerli');
  if (yerli && navigator.share) {
    yerli.hidden = false;
    yerli.addEventListener('click', function(){
      navigator.share({ title: BAS, text: BAS + (YZR ? (' | ' + YZR) : ''), url: ADRES }).catch(function(){});
    });
  }
})();
</script>

<script>
/* =====================================================================
   ARACIN DAR EKRANDA YER DEĞİŞTİRMESİ
   ---------------------------------------------------------------------
   Sorun: yan sütun 1024 pikselin altında hiç çizilmez, araç da orada
   durur. Telefondan okuyan kimse metni doğrulayamıyordu. Oysa aracın
   bütün gerekçesi okurun sunucunun sözüne değil kendi hesabına
   güvenmesidir; yalnızca geniş ekranda çalışan bir doğrulama, okurların
   çoğunu dışarıda bırakır ve verdiği sözü yarım bırakır.

   Neden ikinci bir kopya değil: aynı işi yapan iki blok bakımı ikiye
   katlar ve biri ötekinden sessizce ayrışır; biri düzeltilir, öteki
   unutulur ve iki ekranda iki farklı şey söylerler. Bu yüzden kopya
   basılmaz, AYNI DÜĞÜM taşınır. Taşınan düğüm aynı düğüm olduğu için
   olay dinleyicileri, açılmış katlamalar ve varsa hesaplanmış çıktı da
   onunla gider: okur pencereyi daraltınca sonucunu kaybetmez.

   Neden ölçüye değil görünürlüğe bakılıyor: kırılma noktası biçim
   dosyasında yazılıdır. Buraya ikinci kez yazılsaydı, o eşik değiştiği
   gün betik sessizce yanlış yere taşırdı. Bunun yerine "aracın evi
   çiziliyor mu" diye sorulur. Bu ölçü okuma kipini de kendiliğinden
   kapsar: orada da yan sütunlar gizlenir ve araç akışa iner.

   Betiğe bağlı olması eksiklik değildir: araç zaten betiksiz çalışmaz,
   özeti tarayıcıda hesaplayan da bu betiktir. Betik yoksa doğrulanacak
   bir şey de yoktur.
   ===================================================================== */
(function(){
  var arac = document.getElementById('piArac');
  var yuva = document.getElementById('piYuva');
  if (!arac || !yuva) return;
  /* Evin kendisi hep yerinde kalır; taşınan yalnızca araçtır. Dönüş
     için geldiği yerdeki komşusu da saklanır, yoksa araç eve dönerken
     anahtar sözcüklerin altına düşerdi. */
  var evi = arac.parentNode, ardil = arac.nextSibling;

  function yerlestir(){
    var evCiziliyor = evi.getClientRects().length > 0;
    if (!evCiziliyor) { if (arac.parentNode !== yuva) yuva.appendChild(arac); }
    else if (arac.parentNode !== evi) { evi.insertBefore(arac, ardil); }
  }
  yerlestir();

  /* Pencere döndürülünce ya da okuma kipi açılınca yeniden bakılır.
     Ölçüm ucuzdur ama her çerçevede yapılmaz; kısa bir beklemeye alınır. */
  var zaman;
  function tazele(){ clearTimeout(zaman); zaman = setTimeout(yerlestir, 120); }
  window.addEventListener('resize', tazele);
  if (window.MutationObserver) {
    new MutationObserver(tazele).observe(document.body, { attributes: true, attributeFilter: ['class'] });
  }
})();
</script>

<?php
/* =====================================================================
   PARMAK İZİ DOĞRULAMA ARACI
   ---------------------------------------------------------------------
   Neden burada: araç yalnızca bu sayfada işe yarar. k/kutadgu.js'e
   girseydi bir düğme uğruna TG_SURUM artırmak ve bütün sitenin
   önbelleğini tazelemek gerekirdi. Sayfa içinde durduğu sürece
   değişmesi başka hiçbir sayfayı ilgilendirmez. Bilinçli bir seçim.

   Neden tarayıcıda hesaplanıyor: sunucunun "bu metin değişmedi" demesi
   hiçbir şey göstermez, aynı cümleyi metni değiştirmiş bir sunucu da
   kurar. Bu yüzden uçtan yalnızca özeti alınan HAM KAYNAK istenir;
   SHA-256 okurun kendi tarayıcısında hesaplanır ve sonuç, sayfada
   yazılı olan değerle karşılaştırılır. Karşılaştırmayı sunucu yapmaz.

   Neden tutmadığı da yazılır: bu aracın işe yaraması, tam olarak
   tutmadığı durumda da doğruyu söylemesine bağlıdır. Sessizce
   başarısız olmak ya da olumsuz sonucu yumuşatmak, aracı işe yaramaz
   değil, yanıltıcı yapardı.

   Metinler iki dilde. Sayfanın dili hangisiyse araç o dilde konuşur;
   dizgeler burada, PHP tarafında kurulur ki sitenin geri kalanıyla
   aynı yerde dursunlar.
   ===================================================================== */
$piM = [
    'en'          => $L ? 1 : 0,
    'calisiyor'   => $L ? 'Fetching the source and computing…' : 'Kaynak alınıyor, hesaplanıyor…',
    'alinamadi'   => $L ? 'The source could not be fetched.' : 'Kaynak alınamadı.',
    'yayinlanan'  => $L ? 'Published on this page' : 'Sayfada yayımlanan',
    'hesaplanan'  => $L ? 'Computed by your browser' : 'Tarayıcınızın hesapladığı',
    'yontem'      => $L ? 'Method' : 'Yöntem',
    /* Yöntem dizgesi uçtan olduğu gibi alınır ve sözleşmesi sabittir;
       çevirmek, okurun başka bir yerde gördüğü dizgeyle ayrışmasına yol
       açardı. İngilizce sayfada bu yüzden çeviri değil, karşılığı
       yazılır: dizge aynı kalır, anlamı ayrıca söylenir. */
    'yontemAck'   => $L
        ? 'That is: the first 32 hexadecimal characters of the SHA-256 of the four fields joined with a line feed (LF), encoded as UTF-8.'
        : '',
    'tutuyor'     => $L
        ? 'It matches. The value your browser computed is the same as the value published on this page.'
        : 'Tutuyor. Tarayıcınızın hesapladığı değer, sayfada yayımlanan değerle aynı.',
    'tutmuyor'    => $L
        ? 'IT DOES NOT MATCH. The value your browser computed differs from the value published on this page. That means either the text or the published fingerprint has changed since publication. Please report the result exactly as it appears here.'
        : 'TUTMUYOR. Tarayıcınızın hesapladığı değer, sayfada yayımlanan değerden farklı. Bu, yayımdan sonra ya metnin ya da yayımlanan parmak izinin değiştiği anlamına gelir. Sonucu burada göründüğü gibi bildirin.',
    /* Uçtan gelen değer ile sayfada yazan değer ayrı ayrı okunur. İkisi
       ayrışırsa bu, okurun bilmesi gereken bir şeydir; gizlenmez. */
    'ucfark'      => $L
        ? 'Note: the value returned by the endpoint differs from the value printed on this page. The two are produced from the same record, so this difference is itself worth reporting.'
        : 'Not: uçtan gelen değer, sayfada basılı olan değerden farklı. İkisi aynı kayıttan üretilir; bu fark başlı başına bildirilmeye değer.',
    'hesapYok'    => $L
        ? 'Your browser cannot perform this computation (crypto.subtle is unavailable: the page may have been opened over an insecure connection, or the browser may be old). Nothing was computed here. You can download the source below and do the same computation on your own machine.'
        : 'Tarayıcınız bu hesabı yapamıyor (crypto.subtle yok: sayfa güvenli olmayan bir bağlantıdan açılmış ya da tarayıcı eski olabilir). Burada hiçbir hesap yapılmadı. Aşağıdan kaynağı indirip aynı hesabı kendi bilgisayarınızda yapabilirsiniz.',
    'kaynakAc'    => $L ? 'Show the source that was hashed' : 'Özeti alınan kaynağı göster',
    'kaynakAck'   => $L
        ? 'Four fields joined with a line feed (LF): title, abstract, text, references. The source is shown raw; the page above is its formatted form. You can compare it with your own eyes.'
        : 'Dört alan, aralarına satır sonu (LF) konarak birleştirilir: başlık, özet, metin, kaynakça. Kaynak ham hâliyle gösterilir; yukarıdaki sayfa onun biçimlenmiş hâlidir. Gözünüzle karşılaştırabilirsiniz.',
    'indir'       => $L ? 'Download the source (.txt)' : 'Kaynağı indir (.txt)',
    'komutBas'    => $L ? 'The same computation on your own machine' : 'Aynı hesabı kendi bilgisayarınızda',
    'komutAck'    => $L
        ? 'With another tool: take the first 32 characters of the 64-character result, in lower case.'
        : 'Başka bir araç kullanıyorsanız: çıkan 64 hanenin ilk 32\'sini, küçük harfle alın.',
    /* Söz verilemeyecek şey söylenmez. Araç ne gösterdiğini ve ne
       göstermediğini aynı yerde yazar; ikincisi olmadan ilki bir
       güvence gibi okunur ve okuru yanıltır. */
    'kanit'       => $L
        ? 'What this shows and what it does not: it shows that the text you are reading matches the fingerprint published for it. It does not show that the server did not change the text and the fingerprint together. The assurance against that is the fingerprint being held in other hands as well: the list in the archive dump is open to anyone, without registration or approval, and a copy of it may have been downloaded months ago by someone else.'
        : 'Ne gösterir, ne göstermez: okuduğunuz metnin, kendisi için yayımlanmış parmak iziyle tuttuğunu gösterir. Sunucunun metni ve parmak izini birlikte değiştirmediğini göstermez. Buna karşı güvence, parmak izinin başka ellerde de bulunmasıdır: arşiv dökümündeki liste kayıt ya da onay istenmeden herkese açıktır ve bir kopyası aylar önce başkalarınca indirilmiş olabilir.',
    'listeBag'    => $L ? 'The fingerprint list in the archive dump' : 'Arşiv dökümündeki parmak izi listesi',
    /* Bu sayfada okunan metin ile arşivde yayımlanan değer ayrı
       düştüğünde susmak, okurun yanlış bir sayıyı doğru sanmasına yol
       açardı. Hangi metnin doğrulandığı açıkça yazılır. */
    'ceviriAck'   => $L
        ? 'What was verified is the text on this page, that is, the English translation. The value published in the archive dump is the fingerprint of the base text and is a different value; it is printed above as well, so that you can still compare it with the list held in other hands.'
        : 'Doğrulanan, bu sayfadaki metindir; yani çeviridir. Arşiv dökümünde yayımlanan değer taban metnin parmak izidir ve başka bir değerdir; başka ellerdeki listeyle karşılaştırma yolunuz kapanmasın diye o da yukarıda basılıdır.',
];
?>
<?php if ($bcid !== ''): ?>
<script>
(function(){
  var arac = document.getElementById('piArac');
  if (!arac) return;
  var dugme = document.getElementById('piDugme');
  var cikti = document.getElementById('piCikti');
  var yayinEl = document.getElementById('piYayin');
  if (!dugme || !cikti || !yayinEl) return;

  var M = <?= json_encode($piM, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  /* Yayımlanan değer sayfanın kendisinden okunur, ayrı bir veri
     alanından değil: okurun gördüğü değer neyse karşılaştırılan da o
     olsun. İki yerde ayrı yazılsalardı araç, okurun görmediği bir
     değeri doğrulamış olurdu. */
  var YAYIN = (yayinEl.textContent || '').trim().toLowerCase();
  var DOSYA = arac.getAttribute('data-dosya') || 'kutadgu-kaynak.txt';
  var UC = arac.getAttribute('data-uc') || '';
  var indirmeAdres = null;

  function kacir(s){ var d = document.createElement('div'); d.textContent = String(s == null ? '' : s); return d.innerHTML; }
  function et(baslik, deger){ return '<p><span class="pi-et">' + kacir(baslik) + '</span><span class="pi-deger">' + kacir(deger) + '</span></p>'; }

  function onaltilik(tampon){
    var b = new Uint8Array(tampon), s = '', i;
    for (i = 0; i < b.length; i++) { var h = b[i].toString(16); s += (h.length < 2 ? '0' : '') + h; }
    return s;
  }

  /* Kaynak, tg_metin_ozeti()'nin kardığı sırayla ve aynı ayraçla
     kurulur. Burada hiçbir kırpma, boşluk temizliği ya da HTML kaçırma
     yapılmaz: tek bir karakteri "düzeltmek", doğru bir metni yanlış
     gösterir. */
  function kaynakDizgesi(k){
    k = k || {};
    return String(k.baslik == null ? '' : k.baslik) + '\n'
         + String(k.ozet == null ? '' : k.ozet) + '\n'
         + String(k.metin == null ? '' : k.metin) + '\n'
         + String(k.kaynakca == null ? '' : k.kaynakca);
  }

  /* İndirilen dosya, hesabı yapılan dizgenin birebir kendisidir. Sonuna
     satır sonu eklenmez: eklenseydi dosyanın sha256sum çıktısı sayfadaki
     değeri vermez ve okur haklı olarak sistemden şüphelenirdi. */
  function indirmeAdresi(kaynak){
    if (indirmeAdres) { try { URL.revokeObjectURL(indirmeAdres); } catch (e) {} }
    indirmeAdres = URL.createObjectURL(new Blob([kaynak], { type: 'text/plain;charset=utf-8' }));
    return indirmeAdres;
  }

  function yontemBloku(j, kaynak){
    var h = '';
    h += et(M.yontem, String(j.yontem || ''));
    if (M.yontemAck) h += '<p>' + kacir(M.yontemAck) + '</p>';
    h += '<details class="pi-ac"><summary>' + kacir(M.kaynakAc) + '</summary>'
       + '<p>' + kacir(M.kaynakAck) + '</p>'
       + '<pre class="pi-kod pi-ham">' + kacir(kaynak) + '</pre></details>';
    h += '<p><a class="d d-ikinci d-genis" download="' + kacir(DOSYA) + '" href="' + kacir(indirmeAdresi(kaynak)) + '">' + kacir(M.indir) + '</a></p>';
    h += '<p><span class="pi-et">' + kacir(M.komutBas) + '</span>'
       + '<span class="pi-kod">' + kacir('sha256sum ' + DOSYA + ' | cut -c1-32') + '</span>'
       + kacir(M.komutAck) + '</p>';
    h += '<p>' + kacir(M.kanit) + '</p>';
    h += '<p><a href="/dokum.php?d=kutadgu-parmak-izleri.txt">' + kacir(M.listeBag) + '</a></p>';
    return h;
  }

  function yaz(hesap, j, kaynak, hesapUyari){
    var h = '';
    if (hesapUyari) {
      /* Hesap yapılamadıysa bir sonuç cümlesi kurulmaz. "Doğrulanamadı"
         ile "tutmuyor" ayrı şeylerdir; ikisini aynı cümleye koymak
         okuru yanıltır. */
      h += '<p class="pi-hukum pi-uyari">' + kacir(hesapUyari) + '</p>';
      h += et(M.yayinlanan, YAYIN);
    } else {
      var tutuyor = (hesap === YAYIN);
      h += '<p class="pi-hukum ' + (tutuyor ? 'pi-tutuyor' : 'pi-tutmuyor') + '">'
         + kacir(tutuyor ? M.tutuyor : M.tutmuyor) + '</p>';
      h += et(M.yayinlanan, YAYIN);
      h += et(M.hesaplanan, hesap);
    }
    /* Uçtan gelen değer, sayfanın bastığı değerle AYNI metnin özeti
       olmalı; karşılaştırma bu yüzden seçilen kaynağın değeriyle
       yapılır. j.parmak_izi ile karşılaştırılsaydı çeviri sayfasında
       her seferinde sahte bir "uç farkı" uyarısı basılırdı. */
    var ucDeger = secilen(j).deger;
    if (ucDeger && ucDeger !== YAYIN) h += '<p class="pi-uyari">' + kacir(M.ucfark) + '</p>';
    /* Doğrulanan metnin hangisi olduğu, sonuç cümlesinin hemen
       ardından yazılır: "tutuyor" tek başına neyin tuttuğunu söylemez. */
    if (secilen(j).ceviri && M.ceviriAck) h += '<p>' + kacir(M.ceviriAck) + '</p>';
    h += yontemBloku(j, kaynak);
    cikti.innerHTML = h;
  }

  /* Hangi kaynağın özeti alınacak: sayfa hangi metni bastıysa o.
     Uç iki kaynağı da verir; seçimi sayfanın dili yapar. Seçim uca
     bırakılsaydı uç, sayfanın ne bastığını bilemeden tahmin ederdi. */
  function secilen(j){
    var c = j && j.ceviri;
    if (M.en && c && c.parmak_izi && c.kaynak) return { kaynak: c.kaynak, deger: String(c.parmak_izi).toLowerCase(), ceviri: true };
    return { kaynak: j ? j.kaynak : null, deger: String((j && j.parmak_izi) || '').toLowerCase(), ceviri: false };
  }

  function hesapla(j){
    var sec = secilen(j);
    var kaynak = kaynakDizgesi(sec.kaynak);
    var altyapi = window.crypto && window.crypto.subtle && window.crypto.subtle.digest && window.TextEncoder;
    if (!altyapi) { yaz(null, j, kaynak, M.hesapYok); return Promise.resolve(); }
    return window.crypto.subtle.digest('SHA-256', new TextEncoder().encode(kaynak))
      .then(function(oz){ yaz(onaltilik(oz).slice(0, 32), j, kaynak, null); })
      /* Hesap yarıda kalırsa da sessiz kalınmaz: ne olduğu yazılır ve
         okura kendi makinesinde ölçmenin yolu verilir. */
      .catch(function(){ yaz(null, j, kaynak, M.hesapYok); });
  }

  dugme.addEventListener('click', function(){
    var etiket = dugme.textContent;
    dugme.disabled = true;
    dugme.textContent = M.calisiyor;
    cikti.hidden = false;
    cikti.innerHTML = '<p>' + kacir(M.calisiyor) + '</p>';
    /* Uç oturum istemez; çerez de göndermeyiz. Doğrulamanın kim olduğunuza
       bağlı olmadığı, isteğin kendisinde de görünsün. */
    fetch(UC, { headers: { 'Accept': 'application/json' }, credentials: 'omit' })
      .then(function(c){ return c.json().then(function(j){ return { kod: c.status, j: j }; },
                                             function(){ return { kod: c.status, j: null }; }); })
      .then(function(y){
        if (!y.j || y.j.ok !== true) {
          /* 429 bir reddetme değildir ve uç ne zaman gelineceğini yazar;
             o cümle olduğu gibi okura aktarılır. */
          var m = y.j ? (M.en ? (y.j.hata_en || y.j.hata) : (y.j.hata || y.j.hata_en)) : '';
          cikti.innerHTML = '<p class="pi-hukum pi-uyari">'
            + kacir(M.alinamadi + ' (HTTP ' + y.kod + ')' + (m ? ' ' + m : '')) + '</p>';
          return;
        }
        return hesapla(y.j);
      })
      .catch(function(e){
        cikti.innerHTML = '<p class="pi-hukum pi-uyari">' + kacir(M.alinamadi + ' ' + (e && e.message ? e.message : '')) + '</p>';
      })
      .then(function(){ dugme.disabled = false; dugme.textContent = etiket; });
  });
})();
</script>
<?php endif; ?>

<script src="/k/kutadgu.js?v=<?= TG_SURUM ?>" defer></script>
</body>
</html>