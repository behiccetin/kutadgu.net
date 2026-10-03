<?php
/* =====================================================================
   DAVET: NEYE DAVET EDİLDİĞİ. Kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   Bildirilen kural: "Kurucu baş editörler 2027 sonuna kadar yeni baş
   editör, editör ve hakem ekleyebilir; ama kendileri gibi KURUCU
   ekleyemez. Davet kısmında açılır bir pencereden ne davet edileceği
   seçilsin; davet edilen kişinin pozisyonu o olsun."

   ÖLÇÜLEN KUSUR: davet hiçbir rol vermiyordu. Uç hesabı
   'roller' => [] ile açıyor, rol sonradan başka bir yerden veriliyordu.
   Yani "kimi davet ediyorum" sorusunun cevabı davet anında hiçbir yerde
   yazmıyordu.

   BU KAPI DÖRT ŞEYİ ÖLÇER:

     1. Seçenekler TEK KAYNAKTAN gelir. Panelin gördüğü liste ile ucun
        uyguladığı kural aynı işlevden (hs_davet_rolleri) üretilmelidir;
        iki liste yazılsaydı bir gün biri ötekinin kapattığı kapıyı
        açardı.
     2. KAPALI SEÇENEK GİZLENMEZ, SEBEBİYLE DÖNER. Olmayan bir seçenek
        neden olmadığını söylemez ve kişi kendinde kusur arar.
     3. UÇ İSTEMCİYE GÜVENMEZ. Panelde kapalı görünen bir seçenek elle
        gönderildiğinde 403 dönmelidir; yoksa seçici bir süs olur.
     4. KURUCU HİÇBİR KOŞULDA VERİLEMEZ ve baş editörlük davetle TEK
        BAŞINA başlamaz — rol her okumada ayar.php'deki kurul kaydından
        çözülür. Panel bunu söylemek zorundadır; yarım yapılmış bir
        atamayı tamam gibi göstermek, kurulun kendisini belirsizleştirir.

   Kullanım:
     KUTADGU_DATA=<veri> KPORT=<kapı> php davet-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD    = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI   = getenv('KUTADGU_DATA') ?: '';
$PORT   = getenv('KPORT') ?: '8941';
$KAYNAK = '/home/claude/kg/kutadgunet';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/k/hesap.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

/* Kurucu adresi: ayar.php'deki kurul kaydıyla eşleşen tek anahtar.
   Başka bir adresle baş editör yetkisi ölçülemez (bkz. OKUBENI 87). */
/* KULLANICI ADI PANEL-BOY KAPISIYLA AYNI OLMALI.
   İlk yazımda bu kapı 'dvolcum' adında İKİNCİ bir hesap kuruyordu ve
   e-postası panel-boy'unkiyle aynıydı (kurucu adresi; başkası olamaz).
   hs_kaydet() kayıtları e-postaya göre yazar; aynı adresli iki kayıt
   bir noktada birbirini yuttu ve temizlik ikisini birden götürdü.
   Sonuç: panel-boy-kapi ertesi koşuda giriş yapamadı ve "Yönetim
   sekmesi yok" diye kusur bildirdi — oysa yok olan sekme değil, ölçüm
   hesabıydı.

   İki kapı aynı fiziksel hesabı paylaşır; kuran her ikisi de aynı
   satırı yazar, silen hiçbiri onu silmez. */
const KURUCU  = 'cbehic@gmail.com';
const PAROLA  = 'olcum1234';
const KULLANICI = 'olcumbas';

/* ---- Ölçüm hesabı ---- */
$hes = json_decode((string)@file_get_contents($VERI . '/hesaplar.json'), true);
if (!is_array($hes)) $hes = [];
$hes = array_values(array_filter($hes, fn($x) => ($x['kullanici'] ?? '') !== KULLANICI));
$hes[] = ['eposta' => KURUCU, 'parola' => password_hash(PAROLA, PASSWORD_DEFAULT),
          'ad' => 'Behiç Çetin', 'unvan' => 'Dr.', 'kurum' => 'Ölçüm',
          'roller' => ['editor'], 'kullanici' => KULLANICI,
          'katilim' => '2026-01-01', 'giris' => []];
file_put_contents($VERI . '/hesaplar.json', json_encode($hes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
$hz = json_decode((string)@file_get_contents($VERI . '/hiz-sinir.json'), true);
if (is_array($hz)) {
    foreach (array_keys($hz) as $k) if (strpos((string)$k, 'giris:') === 0) unset($hz[$k]);
    file_put_contents($VERI . '/hiz-sinir.json', json_encode($hz));
}
@file_put_contents($VERI . '/giris-deneme.json', '{}');

/* =====================================================================
   1. TEK KAYNAK
   ===================================================================== */
echo "== 1. Seçenekler tek kaynaktan ==\n";
den('hs_davet_rolleri() var', function_exists('hs_davet_rolleri'));
den('hs_davet_rol_coz() var', function_exists('hs_davet_rol_coz'));

$kurucu = hs_bul(KURUCU) ?? ['eposta' => KURUCU, 'ad' => 'Behiç Çetin', 'roller' => ['editor']];
$liste = hs_davet_rolleri($kurucu);
olc('kurucunun gördüğü: ' . implode(', ', array_map(
    fn($r) => $r['k'] . ($r['acik'] ? '' : '(kapalı)'), $liste)));
/* KURUL KARARI, 14 Ağustos 2026: "yönetim panelinde okur davet etme
   olmasın." Satır kaldırıldı ve gerekçesi kendi açıklamasındaydı:
   OKUMAK İÇİN KAYIT GEREKMEZ. Bu sistemde arşivin tamamı kayıtsız
   açıktır; kimseyi okumaya davet etmenin karşılığı yok ve karşılığı
   olmayan bir davet, "burada kapalı bir şey var" izlenimi verir.
   Kapı artık satırın YOKLUĞUNU da ölçüyor: geri gelirse görür. */
den('dört seçenek var', count($liste) === 4, (string)count($liste));

$k = [];
foreach ($liste as $r) $k[$r['k']] = $r;
foreach (['hakem', 'editor', 'bas_editor', 'kurucu'] as $ad) {
    den("  $ad satırı var", isset($k[$ad]));
}
den('  okur satırı YOK (kurul kararı)', !isset($k['okur']));
den('kurucu satırı KAPALI', isset($k['kurucu']) && !$k['kurucu']['acik']);
den('  ve sebebi yazılı', isset($k['kurucu']) && trim((string)$k['kurucu']['sebep']) !== '', (string)($k['kurucu']['sebep'] ?? ''));
/* Kapalı olan her satır sebebini taşımalı. Sebepsiz kapalı bir satır,
   gizlenmiş bir satırdan daha kötüdür: hem görünür hem açıklanmaz. */
$sebepsiz = [];
foreach ($liste as $r) if (!$r['acik'] && trim((string)$r['sebep']) === '') $sebepsiz[] = $r['k'];
den('kapalı olan her satır sebebini taşıyor', $sebepsiz === [], implode(',', $sebepsiz));
/* Her satırın ne olduğu da yazılı olmalı. */
$acksiz = [];
foreach ($liste as $r) if (mb_strlen(trim((string)$r['ack'])) < 25) $acksiz[] = $r['k'];
den('her satırın ne olduğu yazılı', $acksiz === [], implode(',', $acksiz));

/* Kurucu HİÇ KİMSEDE açılmaz: bir bayrak, bir tarih ya da bir yetki
   onu açamamalı. */
den('kurucu satırı hiçbir yetkide açılmıyor',
    hs_davet_rol_coz($kurucu, 'kurucu') === null);
den('  ve kurucu satırı rol de taşımıyor',
    isset($k['kurucu']) && $k['kurucu']['rol'] === []);

/* Baş editörlük davetle tek başına başlamaz. */
den('baş editör satırı kurul kaydı gerektiğini işaretliyor',
    !empty($k['bas_editor']['kurul_kaydi_gerek']));
den('  ve davetle rol yazılmıyor', isset($k['bas_editor']) && $k['bas_editor']['rol'] === []);

/* HAKEMLİĞE DAVET = DOKTORA DOĞRULAMASI (14 Ağustos 2026 kurul kararı).
   Bu bölüm bunun TERSİNİ ölçüyordu: "hakemliğe davet ADAY hakem verir,
   hiçbir satır doğrudan hakem vermez". O kural kalktı; gerekçesi
   ortak.php'de tg_dogrulama_editor()'ün başındadır. Kısacası: belgeye
   bakan zaten bir editördü, yani denetim hep insandı ve davet eden de
   o insandır. Kapı yeni kuralı ölçer, eskisinin kalıntısını değil. */
den('hakemliğe davet DOĞRUDAN hakem veriyor',
    isset($k['hakem']) && $k['hakem']['rol'] === ['hakem'],
    json_encode($k['hakem']['rol'] ?? null));
den('  ve satır doğrulama taşıdığını işaretliyor', !empty($k['hakem']['dogrular']));
den('  açıklaması sorumluluğun DAVET EDENDE olduğunu söylüyor',
    (bool)preg_match('/doğrula/iu', (string)($k['hakem']['ack'] ?? '')),
    (string)($k['hakem']['ack'] ?? ''));
den('  başka hiçbir satır doğrulama taşımıyor',
    count(array_filter($liste, fn($r) => !empty($r['dogrular']))) === 1);
den('  ve hiçbir satır bas_editor rolü yazmıyor',
    !array_filter($liste, fn($r) => in_array('bas_editor', (array)$r['rol'], true)));

/* Yetkisi olmayanda hepsi kapalı. */
$yabanci = ['eposta' => 'kimse@ornek.org', 'ad' => 'Kimse', 'roller' => ['editor']];
$yl = hs_davet_rolleri($yabanci);
den('baş editör olmayanda bütün satırlar kapalı',
    !array_filter($yl, fn($r) => (bool)$r['acik']));
den('  ve rol çözümü null dönüyor', hs_davet_rol_coz($yabanci, 'editor') === null);

/* =====================================================================
   2. UÇ İSTEMCİYE GÜVENMİYOR
   ===================================================================== */
echo "\n== 2. Uç ==\n";
$apiHam = (string)@file_get_contents($KAYNAK . '/api/index.php');
den('uç rolü hs_davet_rol_coz ile çözüyor', str_contains($apiHam, 'hs_davet_rol_coz($h, $rolK)'));
den('  gövdeden gelen rol doğrudan yazılmıyor',
    !preg_match("/\\\$hedef\\['roller'\\]\\s*=\\s*[^;]*\\\$g\\[/", $apiHam));
/* Varsayılan bir sonraki satıra —aday hakeme— kaydırılamaz: bu, sessizce
   yetki taşıyan bir davet üretirdi. Varsayılan YOK; uç sorar. */
den('  yazılmamış seçim varsayılana düşmüyor',
    !str_contains($apiHam, "if (\$rolK === '') \$rolK = 'okur';")
    && str_contains($apiHam, "Neye davet ettiğinizi seçin"));
den('seçenek ucu var', str_contains($apiHam, "'/yonetim/davet-rolleri'"));
den('  ve o da baş editör yetkisi arıyor',
    (bool)preg_match("#/yonetim/davet-rolleri.{0,300}hs_bas_yetki\(\\\$h\)#su", $apiHam));

/* ---- Davranış: gerçek istek ---- */
function giris(): ?string {
    $port = getenv('KPORT') ?: '8941';
    $b = stream_context_create(['http' => [
        'method' => 'POST', 'timeout' => 20, 'ignore_errors' => true,
        'header' => "Content-Type: application/json\r\nCF-Connecting-IP: 10.121.4.9\r\n",
        'content' => json_encode(['kim' => KULLANICI, 'parola' => PAROLA]),
    ]]);
    $c = @file_get_contents('http://127.0.0.1:' . $port . '/api/hesap/giris', false, $b);
    $d = json_decode((string)$c, true);
    if (!is_array($d) || empty($d['ok'])) return null;
    foreach (($http_response_header ?? []) as $s) {
        if (stripos($s, 'Set-Cookie:') === 0 && preg_match('/PHPSESSID=[^;]+/', $s, $m)) return $m[0];
    }
    return null;
}
function istek(string $yol, ?array $govde, string $cerez): array {
    $port = getenv('KPORT') ?: '8941';
    $bas = "Accept: application/json\r\nCookie: $cerez\r\nCF-Connecting-IP: 10.121.4.9\r\n";
    $o = ['timeout' => 20, 'ignore_errors' => true, 'header' => $bas];
    if ($govde !== null) { $o['method'] = 'POST'; $o['header'] .= "Content-Type: application/json\r\n"; $o['content'] = json_encode($govde, JSON_UNESCAPED_UNICODE); }
    /* Panel her isteğe dil ekliyor; kapı da eklemeli, yoksa ölçtüğü
       yol gerçekte kullanılan yol olmaz. Dilsiz istekte k_dil()
       varsayılana (İngilizce) düşer ve kapı Türkçe bir yanıt beklerken
       İngilizce alır — ölçüm doğru, beklenti yanlış olurdu. */
    $ayrac = strpos($yol, '?') !== false ? '&' : '?';
    $c = @file_get_contents('http://127.0.0.1:' . $port . '/api' . $yol . $ayrac . 'lang=tr', false, stream_context_create(['http' => $o]));
    $d = json_decode((string)$c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı'];
}
$cerez = giris();
den('ölçüm hesabıyla giriş yapıldı', $cerez !== null);
if ($cerez === null) { echo "\nGECTI: $gecti   KALDI: " . (++$kaldi) . "\n"; exit(1); }

$uc = istek('/yonetim/davet-rolleri?lang=tr', null, $cerez);
den('uç seçenekleri dönüyor', !empty($uc['ok']) && count($uc['roller'] ?? []) === 4,
    (string)count($uc['roller'] ?? []));
$ucK = [];
foreach (($uc['roller'] ?? []) as $r) $ucK[$r['k']] = $r;
den('  panelin gördüğü ile aynı: kurucu kapalı', isset($ucK['kurucu']) && $ucK['kurucu']['acik'] === false);
den('  ve sebebi de dönüyor', trim((string)($ucK['kurucu']['sebep'] ?? '')) !== '');
/* Dil ölçümü 'okur' satırından okunuyordu; o satır kaldırıldı. Ölçülen
   şey satır değil DİL olduğu için ölçüm bir sonraki satıra taşındı. */
den('  dil isteğe uyuyor (Türkçe)',
    (bool)preg_match('/[şğıçöüİ]/u', (string)($ucK['hakem']['ad'] ?? '') . (string)($ucK['hakem']['ack'] ?? '')),
    (string)($ucK['hakem']['ad'] ?? ''));

/* KAPALI SEÇENEK ELLE GÖNDERİLDİĞİNDE REDDEDİLİR. Asıl ölçüm budur:
   seçici bir kolaylıktır, denetim uçtadır. */
$n = random_int(1000, 9999);
$r1 = istek('/yonetim/hesap-davet', ['ad' => 'Kurucu Denemesi', 'eposta' => "dv-kurucu$n@ornek.org", 'rol' => 'kurucu'], $cerez);
den('kurucu rolüyle davet REDDEDİLİYOR', empty($r1['ok']), (string)($r1['hata'] ?? 'kabul edildi'));
den('  ve reddin sebebi söyleniyor',
    mb_stripos((string)($r1['hata'] ?? ''), 'kurucu') !== false, (string)($r1['hata'] ?? ''));

$r2 = istek('/yonetim/hesap-davet', ['ad' => 'Editor Denemesi', 'eposta' => "dv-ed$n@ornek.org", 'rol' => 'editor'], $cerez);
den('editör rolüyle davet geçiyor', !empty($r2['ok']), (string)($r2['hata'] ?? ''));
den('  ve yanıt hangi role davet edildiğini söylüyor', ($r2['rol'] ?? '') === 'editor' && trim((string)($r2['rol_ad'] ?? '')) !== '');
/* Rol GERÇEKTEN yazıldı mı: yanıt "oldu" demekle olmuş olmaz. */
$ked = hs_bul("dv-ed$n@ornek.org");
den('  kayıtta editör rolü duruyor', is_array($ked) && in_array('editor', (array)($ked['roller'] ?? []), true),
    json_encode($ked['roller'] ?? null));

$r3 = istek('/yonetim/hesap-davet', ['ad' => 'Hakem Denemesi', 'eposta' => "dv-hk$n@ornek.org", 'rol' => 'hakem'], $cerez);
den('hakemliğe davet geçiyor', !empty($r3['ok']), (string)($r3['hata'] ?? ''));
$khk = hs_bul("dv-hk$n@ornek.org");
den('  kayıtta HAKEM rolü duruyor', is_array($khk) && in_array('hakem', (array)($khk['roller'] ?? []), true),
    json_encode($khk['roller'] ?? null));
/* ASIL ÖLÇÜM: davetin doktorayı da doğrulaması. Yanıtın "oldu" demesi
   yetmez; kayda BAKILIR ve doğrulayanın ADI aranır. Ad yoksa kimse
   arkasında durmuyor demektir ve kural uygulanmamış olur. */
$ddHk = tg_dogrulama_durum($khk['dogrulama'] ?? null);
den('  davet doktorayı da DOĞRULADI', ($ddHk['durum'] ?? '') === 'onayli', json_encode($khk['dogrulama'] ?? null));
den('  doğrulamanın türü editördür (belge değil)',
    (string)($khk['dogrulama']['tur'] ?? '') === 'editor', (string)($khk['dogrulama']['tur'] ?? ''));
den('  hangi yoldan geldiği yazıyor (davet)',
    (string)($khk['dogrulama']['yol'] ?? '') === 'davet', (string)($khk['dogrulama']['yol'] ?? ''));
den('  DOĞRULAYANIN ADI kayıtta duruyor',
    tg_dogrulama_onaylayan($khk['dogrulama'] ?? null) !== '',
    (string)tg_dogrulama_onaylayan($khk['dogrulama'] ?? null));
den('  ve yanıt bunu panele de söylüyor',
    !empty($r3['dogrulandi']) && trim((string)($r3['dogrulayan'] ?? '')) !== '',
    json_encode(['d' => $r3['dogrulandi'] ?? null, 'k' => $r3['dogrulayan'] ?? null]));
/* Editör daveti doğrulama TAŞIMAZ: editörlük bir yetkidir, doktora
   iddiası değildir. İkisi karışırsa her davet bir doktora onayına
   dönerdi. */
den('editör daveti doktora doğrulaması TAŞIMIYOR',
    ($ked !== null) && tg_dogrulama_durum($ked['dogrulama'] ?? null)['durum'] !== 'onayli',
    json_encode($ked['dogrulama'] ?? null));

$r4 = istek('/yonetim/hesap-davet', ['ad' => 'Bas Denemesi', 'eposta' => "dv-bs$n@ornek.org", 'rol' => 'bas_editor'], $cerez);
den('baş editörlüğe davet geçiyor', !empty($r4['ok']), (string)($r4['hata'] ?? ''));
den('  yanıt kurul kaydının ayrıca gerektiğini söylüyor', !empty($r4['kurul_kaydi_gerek']));
$kbs = hs_bul("dv-bs$n@ornek.org");
den('  ama kayda bas_editor YAZILMAMIŞ (rol ayar dosyasından çözülür)',
    is_array($kbs) && !in_array('bas_editor', (array)($kbs['roller'] ?? []), true),
    json_encode($kbs['roller'] ?? null));
den('  ve kişi gerçekten baş editör olmamış',
    is_array($kbs) && !hs_bas_yetki($kbs));

/* ROL YAZILMADAN DAVET GÖNDERİLMEZ. Eskiden 'okur' varsayılıyordu ve
   ölçüm onu doğruluyordu; okur satırı kaldırılınca varsayılan da
   kaldırıldı. Sessizce bir rol seçmek yerine uç SORAR — ve hiçbir
   hesap kurulmaz. İkincisi ayrıca ölçülür: hata döndürüp yine de kayıt
   açan bir uç, hatayı yalnızca ekrana yazmış olurdu. */
$r5 = istek('/yonetim/hesap-davet', ['ad' => 'Rolsuz Deneme', 'eposta' => "dv-rs$n@ornek.org"], $cerez);
den('rol yazılmadan davet REDDEDİLİYOR', empty($r5['ok']), json_encode($r5));
den('  ve sebebi söyleniyor',
    (bool)preg_match('/[Nn]eye davet/u', (string)($r5['hata'] ?? '')), (string)($r5['hata'] ?? ''));
$krs = hs_bul("dv-rs$n@ornek.org");
den('  ve hiçbir hesap kurulmuyor', $krs === null, json_encode($krs));

/* =====================================================================
   3. PANEL SEÇENEĞİ ÇİZİYOR
   ===================================================================== */
echo "\n== 3. Panel ==\n";
$pn = (string)@file_get_contents($KAYNAK . '/panel.php');
den('davet kartında rol seçici var', str_contains($pn, 'id="dvRolKutu"'));
den('  seçenekler sunucudan çekiliyor', str_contains($pn, "/yonetim/davet-rolleri"));
/* Dil tek tek çağrılara değil api() işlevine eklenir: çağrı başına
   eklenseydi bir gün biri unutulur ve o uç sessizce İngilizce
   konuşurdu. */
den('  dil her isteğe api() içinde ekleniyor',
    str_contains($pn, "'lang=' + (EN ? 'en' : 'tr')"));
den('  panel kendi listesini yazmıyor',
    !preg_match("/DV_ROLLER\s*=\s*\[\s*\{/", $pn));
den('  kapalı seçenek çizilip devre dışı bırakılıyor',
    str_contains($pn, "i.disabled=!r.acik;") && str_contains($pn, "pn-kapali"));
den('  ve sebebi ekrana yazılıyor', str_contains($pn, "pn-sebep"));
den('ilk AÇIK seçenek seçili geliyor',
    (bool)preg_match('/var ilk = DV_ROLLER\.filter\(function\(x\)\{ return x\.acik; \}\)\[0\];/', $pn));
den('kurul kaydı notu panelde var', str_contains($pn, 'id="dvKurulNot"'));

/* =====================================================================
   4. KAPI KENDİNDEN SONRA TEMİZLİK YAPAR
   ---------------------------------------------------------------------
   Bu kapı sınama verisine gerçek hesap YAZAR; yazmadan davet ucunu
   ölçemez. Ama bıraktığı kayıtlar kalıcı olursa her koşuda birikir ve
   BAŞKA kapıların ölçümünü kaydırır.

   Ölçüldü ve tam olarak bu oldu: sekiz koşudan sonra panelin "Hesap
   daveti" kartındaki editör listesi 980 piksele çıktı, Yönetim sekmesi
   2029 piksel oldu ve panel-boy-kapi tavanı aştı. Panelde hiçbir şey
   değişmemişti; büyüyen şey benim bıraktığım çöptü.

   Kendi çöpünü bırakan bir kapı, bir süre sonra kendi ölçtüğü şeyi
   bozar ve bozduğunu başka bir kapının kusuru sanır.
   ===================================================================== */
/* =====================================================================
   3.b GİTMEYEN DAVET "GÖNDERİLDİ" DİYE BİLDİRİLMİYOR

   ÖLÇÜLEN KUSUR — 14 Ağustos 2026. /editor/hakem-ata ucu
   eposta_gonder()'in dönüş değerini atıyordu; panel her durumda
   "Davet gönderildi." yazıyordu. Aynı kusur parola yenilemede
   bulunup düzeltilmişti, burada kalmıştı.

   O gün somut olarak yanlıştı: posta aktarıcısının hesap doğrulaması
   sürerken aktarıcı takım üyesi olmayan hiçbir alıcıyı kabul etmiyor.
   Editör bir hakem atadığında ekranda "gönderildi" yazacak, ileti hiç
   çıkmayacaktı.

   Burası KAYNAK okur, istek atmaz: teslim arızasını yerel sunucuda
   üretmek için gerçek bir aktarıcı gerekir ve bu kapının işi teslim
   değil, TESLİMİN NASIL BİLDİRİLDİĞİdir.
   ===================================================================== */
echo "\n== 3.b Gitmeyen davet nasıl bildiriliyor ==\n";
$api = (string)@file_get_contents($KAYNAK . '/api/index.php');
$pnl = (string)@file_get_contents($KAYNAK . '/panel.php');
den('kaynaklar okunabildi', $api !== '' && $pnl !== '');

/* Ucun hakem-ata bölümü ayrıştırılır: dosyanın tamamında aramak,
   başka bir uçtaki doğru satırı bu ucun sanmak olurdu. */
$bas = strpos($api, "\$yol === '/editor/hakem-ata'");
$son = $bas === false ? false : strpos($api, "\$yol === '/editor/davet-iptal'", $bas);
$blok = ($bas !== false && $son !== false) ? substr($api, $bas, $son - $bas) : '';
den('hakem-ata bölümü bulundu', $blok !== '', (string)strlen($blok));
den('  eposta_gonder dönüş değeri OKUNUYOR',
    (bool)preg_match('#\$postaGitti\s*=\s*eposta_gonder\(#', $blok));
den('  sonuç yanıtta bildiriliyor', str_contains($blok, "'posta' => \$postaGitti"));
den('  adres yokken de bir değer var (null: denenmedi)',
    (bool)preg_match('#\$postaGitti\s*=\s*null#', $blok));
den('  gitmeyen davetin bağlantısı yanıtta duruyor', str_contains($blok, "'davet_link' => \$davetUrl"));
/* Kayıt ile teslim ayrı şeylerdir: teslim arızası yapılmış atamayı
   geri almaz. Uç hâlâ ok:true döner ve hakemi yazar. */
den('  teslim arızası atamayı geri almıyor (yanıt yine ok)', str_contains($blok, "'ok' => true"));

den('panelde koşulsuz "Davet gönderildi." kalmadı',
    !str_contains($pnl, 'm.textContent=S.davetGitti'));
/* SAYIM ÇAĞRIYI SAYAR, TANIMI DEĞİL. İlk yazımda desen
   'davetSonuc(m, r)' idi ve TANIM SATIRINI da sayıyordu: beklenen 2,
   ölçülen 3 çıktı ve kapı doğru kodu kusurlu bildirdi. Ölçüm yanlış
   çıktığında önce ölçümden şüphelenilir; şüphelenildi ve kusur
   ölçümde çıktı. Çağrı noktasında satır ';' ile biter, tanımda '{'. */
/* 15 Ağustos 2026: işlev üçüncü bir değişken aldı (çalışmanın başlığı,
   WhatsApp metnine girsin diye). Desen imzayı değil ÇAĞRI SAYISINI
   ölçer; imzaya bağlanan bir desen, işlev her genişlediğinde doğru kodu
   kusurlu bildirir. */
den('  sonuç tek bir yerden yazılıyor (davetSonuc)',
    substr_count($pnl, 'davetSonuc(m, r, c.baslik);') === 2
    && (bool)preg_match('#function davetSonuc\(m, r, baslik\)\{#', $pnl));
/* PENCERE SABİT SAYIYLA DEĞİL, BİR SONRAKİ İŞLEVLE KAPANIR. 3600
   karakterlik sabit pencere bir sonraki işlevin (api()) içine taşıyordu
   ve "sunucuya istek atmıyor" ölçümü oradaki api( sözcüğüne takılıp
   doğru kodu kusurlu bildirdi. Ölçüm yanlış çıktığında önce ölçümden
   şüphelenilir. */
$ds0 = strpos($pnl, 'function davetSonuc');
$ds1 = $ds0 === false ? false : strpos($pnl, 'function api(', $ds0);
$dsB = ($ds0 === false) ? '' : substr($pnl, $ds0, ($ds1 === false ? 3600 : $ds1 - $ds0));
den('  üç hâl de ayrı: gitti / gitmedi / adres yok',
    str_contains($dsB, 'r.posta === true')
    && str_contains($dsB, 'r.posta === null')
    && str_contains($dsB, 'S.davetPostaYok'));
den('  gitmediğinde davet bağlantısı ekrana basılıyor',
    str_contains($dsB, 'r.davet_link'));
den('  gitmeyen davet BAŞARI rengiyle yazılmıyor',
    (bool)preg_match("#msj\(m,\s*\(r\.posta === null \? S\.davetAdresYok : S\.davetPostaYok\),\s*'err'\)#", $dsB));
foreach (['davetPostaYok', 'davetAdresYok'] as $__d) {
    den("  '$__d' dizgesi iki dilde tanımlı",
        (bool)preg_match("#'" . $__d . "'\s*=>\s*k_c\(#", $pnl));
}

/* ---------------------------------------------------------------------
   WHATSAPP DEVRİ · yalnız KURUCU baş editörler
   ---------------------------------------------------------------------
   Kurul kararı, 15 Ağustos 2026. Ölçülen üç şey:
     a) düğme KURUCU değişkenine bağlı mı (baş editörlük yetmez),
     b) sunucuda hiçbir şey göndermiyor mu (yalnız wa.me penceresi),
     c) ŞİFRE metne konmuyor mu — yanıtta dönüyor ama ikinci bir kanala
        sır koymak gereksiz bir yayılmadır.
   --------------------------------------------------------------------- */
echo "\n== 3.b WhatsApp devri (kurucu) ==\n";
den('KURUCU değişkeni sunucuda basılıyor',
    str_contains($pnl, '$kurucuYetkiJs') && str_contains($pnl, 'var KURUCU     = $kurucuYetkiJs;'));
den('  kurucu sorusu tek kaynaktan (hs_kurucu_mu)',
    (bool)preg_match('#\$kurucuYetki\s*=.*hs_kurucu_mu#s', $pnl));
den('  baş editörlük TEK BAŞINA yetmiyor',
    (bool)preg_match('#if\(!KURUCU \|\| !r \|\| !r\.davet_link\) return;#', $dsB));
den('  düğme yalnız wa.me penceresi açıyor (sunucu bir şey göndermiyor)',
    str_contains($dsB, "https://wa.me/") && !preg_match('#api\(#', $dsB));
den('  ŞİFRE WhatsApp metnine konmuyor',
    !str_contains($dsB, 'r.sifre'), 'r.sifre metinde geçiyor');
den('  metin iki dilde tanımlı', (bool)preg_match("#'waMetin'\s*=>\s*k_c\(#", $pnl));
den('  metin cümlenin içine değer yapıştırmıyor (%1/%2)',
    str_contains($pnl, "replace('%1'") && str_contains($pnl, "replace('%2'"));
den('  numara ayıklanıyor (0 ve + wa.me\'yi kırar)',
    str_contains($dsB, "replace(/[^0-9]/g,'')") && str_contains($dsB, "charAt(0)==='0'"));

echo "\n== 4. Temizlik ==\n";
$hepsi = json_decode((string)@file_get_contents($VERI . '/hesaplar.json'), true);
$kalan = [];
$silinen = 0;
foreach ((array)$hepsi as $x) {
    $ep = (string)($x['eposta'] ?? '');
    /* Yalnız bu kapının ürettiği desenler silinir; başka bir kaydı
       silen bir temizlik, temizlik değildir. */
    if (preg_match('/^dv-(ed|hk|bs|rs|kurucu)\d+@ornek\.org$/', $ep)) { $silinen++; continue; }
    /* Ölçüm hesabı SİLİNMEZ: panel-boy-kapi de onu kullanıyor. Bir
       kapının başka bir kapının fikstürünü silmesi, öteki kapıyı
       sebepsiz kırmızıya döndürür. */
    $kalan[] = $x;
}
file_put_contents($VERI . '/hesaplar.json', json_encode($kalan, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
olc('silinen ölçüm kaydı: ' . $silinen);
$son = json_decode((string)@file_get_contents($VERI . '/hesaplar.json'), true);
/* DENETİM, SİLME İLE AYNI DESENİ KULLANIR. İlk yazımda denetim
   '/^dv-/' diyordu ve sınama verisinde ÖNCEDEN duran iki kaydı
   (dv-bas@example.org, dv-hedef@example.org) da kendi çöpü sandı;
   silme onlara dokunmadığı için kapı kendi temizliğini kusurlu
   bildirdi. Bir kapı, silmediği bir kaydı kendi çöpü sayamaz. */
$artik = array_filter((array)$son, fn($x) => preg_match('/^dv-(ed|hk|bs|rs|kurucu)\d+@ornek\.org$/', (string)($x['eposta'] ?? '')));
den('kapı kendi bıraktığı kayıtları sildi', $artik === [], (string)count($artik));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
