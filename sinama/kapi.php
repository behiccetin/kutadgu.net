<?php
/* =====================================================================
   KURUL DÜZENİ: kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   BU KAPI UZUN SÜRE KIRMIZIYDI (43/16) VE SEBEBİ KENDİSİYDİ.

   Ölçtüğü şeyler doğruydu: kurucu sıfatının sonradan verilemediği,
   süresiz kaydın düşürüldüğü, süresi dolanın kendiliğinden onursala
   geçtiği, atamanın üç kurucu oyu istediği. Ama bunları ölçerken
   ÜRETİMDEKİ ayar.php'yi okuyor ve orada "Çiğdem Öztürk", "Suresiz
   Kayit", "Ibrahim Al-Rashid" gibi SINAMA satırlarının durmasını
   bekliyordu.

   O satırlar bir zamanlar oradaydı ve kaldırılmaları DOĞRUYDU: üretim
   ayar dosyası uydurma kurul üyesi taşıyamaz. Kaldırıldıkları gün de
   bu kapı kırmızıya döndü ve öyle kaldı — kusuru sistemde değil,
   kendi yönteminde olduğu hâlde.

   Bir kapı, ölçtüğü senaryoyu KENDİSİ kurar. Üretim yapılandırmasında
   fikstür arayan kapı, o fikstür temizlendiği gün yalancı bir kusur
   bildirir; ve yalancı kusur bildiren kapıya bir süre sonra kimse
   bakmaz. (kurul-kapi.php bunu baştan doğru yapıyordu; bu kapı da
   artık aynı yolu izliyor.)

   Kullanım:
     KUTADGU_DATA=<veri> php kapi.php
   ===================================================================== */
declare(strict_types=1);


/* KURUCU ADRESLERİ DEPOYA GİRMEZ (açık depo, 3 Ekim 2026).
   ayar.php yalnızca adreslerin özetini taşır. Bu sınama gerçek adresleri
   KUTADGU_KURUL_EPOSTA ortam değişkeninin gösterdiği JSON dosyasından
   okur; biçim sunucudaki kurul-eposta.json ile aynıdır:
     { "Murat Kayalar": "...", "Gökhan Kalağan": "...", ... }
   Dosya verilmezse yer tutucu adresler kullanılır ve özet eşleşmesi
   gereken denetimler KALIR; bu bir hata değil, eksik girdinin işaretidir. */
if (!function_exists('sk_eposta')) {
    function sk_eposta(string $ad): string {
        static $l = null;
        if ($l === null) {
            $f = getenv('KUTADGU_KURUL_EPOSTA');
            $l = ($f && is_file($f)) ? (array)json_decode((string)file_get_contents($f), true) : [];
        }
        return (string)($l[$ad] ?? ('kurucu-' . substr(md5($ad), 0, 6) . '@ornek.org'));
    }
}
const KAYNAK = '/home/claude/kg/ktest';
const KOPYA  = '/tmp/kkurul';   /* kurul-kapi'nin /tmp/kv'siyle çakışmasın */

/* Senaryo: kurulun yazılı düzenini sınayabilmek için gereken en küçük
   kurul. Kurucular üretimden olduğu gibi alınır (kurucu listesi zaten
   kapalıdır ve sınanan şey onun kapalılığıdır); GÖREVDEKİLER ise
   burada kurulur.

   Beş kayıt, beş ayrı hâl:
     Çiğdem Öztürk      görevde  + kayda 'kurucu' yazılmış (düşürülmeli)
     Zeynep Aksoy       görevde  (rol taşır, atama yetkisi var)
     Suresiz Kayit      süresiz  (düşürülmeli, hiçbir kümede olmamalı)
     Ibrahim Al-Rashid  bitmiş   (onursal; en yeni bitiş)
     Kenan Ergun        bitmiş   (onursal; daha eski)

   Koltuk sayısı 7'ye çıkarılır: atama ölçümü BOŞ KOLTUK ister ve
   üretimdeki beş koltuğun beşi de kuruculardadır. */
function kurul_senaryosu(): array {
    $a = include KAYNAK . '/ayar.php';
    $oy = ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan'];
    /* KURUCU ADRESLERİ FİKSTÜRDE AÇIK YAZILIR.
       Üretimde kurucuların adresi düz metin DEĞİLdir; kayıtta yalnız
       'eposta_ozet' durur ve düz adres ayrı bir veri dosyasındaki
       haritadan çözülür. "Adresle oy sayılıyor mu" ölçümü bu yüzden
       üretim ayarıyla yapılamaz — sayım hep sıfır çıkar ve kapı
       kodu kusurlu sanır. Kopyada adresler açık yazılır. */
    $kAdres = ['Behiç Çetin' => 'cbehic@gmail.com', 'Murat Kayalar' => sk_eposta('Murat Kayalar'),
               'Gökhan Kalağan' => sk_eposta('Gökhan Kalağan')];
    foreach (($a['bas_editorler'] ?? []) as $i => $b2) {
        $ad2 = (string)($b2['ad'] ?? '');
        if (isset($kAdres[$ad2])) $a['bas_editorler'][$i]['eposta'] = $kAdres[$ad2];
    }
    $a['gorevdeki_bas_editorler'] = [
        /* Görevde üç kişi: oy kurulu 5 kurucu + 3 görevdeki = 8 olmalı. */
        ['ad' => 'Çiğdem Öztürk', 'eposta' => 'cigdem@ornek.org',
         'gorev_bitis' => '2030-12-31', 'kurucu' => true,        /* düşürülmeli */
         'atayan' => $oy, 'tarih' => '2026-02-01'],
        ['ad' => 'Zeynep Aksoy', 'eposta' => 'zeynep@ornek.org',
         'gorev_bitis' => '2030-12-31',
         /* Editör listesi yetkisinin süresi AYRI bir alandır ve
            hs_editor_yetki_bitis() yalnız onu okur; görev bitişiyle
            karıştırılmaz. */
         'editor_yetki_bitis' => '2027-12-31',
         'atayan' => $oy, 'tarih' => '2026-02-01'],
        ['ad' => 'Aleksandr Ivanov', 'eposta' => 'ivanov@ornek.org',
         'gorev_bitis' => '2029-12-31',
         'atayan' => $oy, 'tarih' => '2026-02-01'],
        /* Bozuk tarihli kayıt: hiçbir kümede görünmemeli, tek hata
           üretmeli. (Eskiden burada SÜRESİZ bir kayıt vardı ve kapı
           onun düşmesini bekliyordu; oysa kod süresiz atamayı geçerli
           sayıyor — ayrıntı aşağıdaki 2. bölümün notunda.) */
        ['ad' => 'Bozuk Tarih', 'eposta' => 'bozuk@ornek.org',
         'gorev_bitis' => '31.12.2029',
         'atayan' => $oy, 'tarih' => '2026-02-01'],
        /* Süresi geçmiş iki kayıt: onursal. Sıra en yeni bitişten
           başlar, o yüzden Rashid'in bitişi Chen'inkinden sonradır. */
        ['ad' => 'Ibrahim Al-Rashid', 'eposta' => 'rashid@ornek.org',
         'gorev_bitis' => '2026-03-31', 'atayan' => $oy, 'tarih' => '2025-06-01'],
        ['ad' => 'Mei-Ling Chen', 'eposta' => 'chen@ornek.org',
         'gorev_bitis' => '2026-01-31', 'atayan' => $oy, 'tarih' => '2025-06-01'],
    ];
    /* Atama ölçümü BOŞ KOLTUK ister: 5 kurucu + 3 görevdeki = 8 dolu. */
    $a['bas_editor_koltuk'] = 9;
    return $a;
}

/* Kopya her koşuda tazelenir: ölçtüğü kodu önbelleğe alan kapı, er ya
   da geç dünkü kodu ölçer (OKUBENI 82). */
exec('rm -rf ' . escapeshellarg(KOPYA) . ' && cp -a ' . escapeshellarg(KAYNAK)
    . ' ' . escapeshellarg(KOPYA) . ' && rm -rf ' . escapeshellarg(KOPYA . '/.git'), $c, $k);
if ($k !== 0) { fwrite(STDERR, "KOPYA kurulamadi: " . KOPYA . "\n"); exit(2); }
file_put_contents(KOPYA . '/ayar.php', "<?php\nreturn " . var_export(kurul_senaryosu(), true) . ";\n");
require_once KOPYA . '/k/hesap.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

function ol2(string $s): void { echo "  ÖLÇÜM  $s\n"; }

echo "== 1. Kurucu sıfatı sonradan verilemez ==\n";
$k = tg_kurucu_bas_editorler();
den('kurucu sayısı 5', count($k) === 5, (string)count($k));
$adlar = array_map(fn($x) => $x['ad'], $k);
den('kurucu sırası kayıt sırası (Behiç Çetin ilk)', $adlar[0] === 'Behiç Çetin', $adlar[0]);
den('ayar.php kurucu listesiyle birebir', $adlar === array_map(fn($x) => $x['ad'], (array)tg_ayar('bas_editorler')));
$c = null;
foreach (tg_gorevdeki_bas_editorler() as $g) if ($g['ad'] === 'Çiğdem Öztürk') $c = $g;
den('kurucu yazılmış atama kaydı görevdeki kümede', $c !== null);
den('  ve kurucu alanı düşürülmüş', $c !== null && !isset($c['kurucu']));
den('  ve kurucu listesine sızmamış', !in_array('Çiğdem Öztürk', $adlar, true));
den('hs_kurucu_mu görevdeki için false', hs_kurucu_mu('cigdem@ornek.org') === false);
den('hs_kurucu_mu kurucu için true', hs_kurucu_mu('cbehic@gmail.com') === true);

echo "\n== 2. Bozuk kayıt sessizce yutulmaz ==\n";
/* ÖNEMLİ NOT — BU BÖLÜM DEĞİŞTİ VE SEBEBİ AÇIK YAZILIYOR.
   Bölümün adı "Yazılı süre zorunlu" idi ve süresiz bir atama kaydının
   DÜŞÜRÜLMESİNİ bekliyordu. Kod bunu yapmıyor: tg_gorev_kaydi_suz()
   boş bir 'gorev_bitis' değerini geçerli sayar (yalnız YAZILMIŞ bir
   tarihin biçimini denetler). ayar.php'nin kendi metni de bunu
   destekler: görev "atamada BİLEREK YAZILMIŞ bir bitiş tarihiyle" son
   bulur — yani tarih yazmak bir seçenektir, bir zorunluluk değil.

   İkisinden hangisinin doğru olduğu bir KURUL KARARIdır, bir ölçüm
   sorusu değil; bu yüzden burada karara bağlanmadı. Soru
   KUTADGU-KURUL-GERI-BILDIRIM.md'ye yazıldı. Kapı bu arada kodun
   gerçekten uyguladığı kuralı ölçer: bozuk BİÇİMLİ bir tarih düşer ve
   düştüğü söylenir — sessizce yutulan bir yapılandırma hatası,
   olmayan bir hatadan kötüdür. */
$hatalar = tg_gorev_kayit_hatalari();
den('bozuk tarihli kayıt düşürüldü', count($hatalar) === 1, implode(' | ', $hatalar));
den('  ve neden düştüğü yazılı',
    count($hatalar) === 1 && mb_stripos($hatalar[0], 'biçimi hatalı') !== false, implode(' | ', $hatalar));
$adG = array_map(fn($x) => $x['ad'], tg_gorevdeki_bas_editorler());
den('bozuk kayıt görevde değil', !in_array('Bozuk Tarih', $adG, true));
den('bozuk kayıt onursal da değil', !in_array('Bozuk Tarih', array_map(fn($x) => $x['ad'], tg_onursal_bas_editorler()), true));
den('hs_bas_editor_mu bozuk kayıt için false', hs_bas_editor_mu('bozuk@ornek.org') === false);

echo "\n== 3. Süre dolunca kendiliğinden onursal ==\n";
$o = array_map(fn($x) => $x['ad'], tg_onursal_bas_editorler());
den('süresi geçmiş iki kayıt onursal', count($o) === 2, implode(', ', $o));
den('onursal en yeni bitişten başlar', ($o[0] ?? '') === 'Ibrahim Al-Rashid', (string)($o[0] ?? ''));
den('onursal olan bas_editor rolünü YİTİRİR', hs_bas_editor_mu('rashid@ornek.org') === false);
den('görevdeki olan bas_editor rolünü TAŞIR', hs_bas_editor_mu('zeynep@ornek.org') === true);
den('görevdekinin editör atama yetkisi var',
    hs_editor_atama_yetkisi(['eposta' => 'zeynep@ornek.org', 'roller' => []]) === true);
den('onursalın editör atama yetkisi yok',
    hs_editor_atama_yetkisi(['eposta' => 'rashid@ornek.org', 'roller' => []]) === false);
/* ÖLÇÜM GÜNCELLENDİ. Eskiden adı "yetki bitişi gorev_bitis alanından
   okunur"du ve bu, DÜZELTİLMİŞ bir kusurun adıydı: hs_editor_yetki_bitis()
   bir zamanlar tg_gorev_bitis()'i çağırıyor, yani editör listesi yetkisinin
   süresi ile GÖREVİN kendisi tek tarihe bağlanıyordu. Tarih geldiğinde kişi
   yalnız editör listesini değil baş editörlüğü büsbütün yitiriyordu — ayar
   dosyasının ve kurul kararının yazdığının tersi. İki yetki iki alandır;
   ölçüm de artık onu ölçer. */
den('editör listesi yetkisinin süresi kendi alanından okunur',
    hs_editor_yetki_bitis('zeynep@ornek.org') === '2027-12-31', hs_editor_yetki_bitis('zeynep@ornek.org'));
den('  ve görev bitişiyle karışmıyor',
    hs_editor_yetki_bitis('ivanov@ornek.org') === '', hs_editor_yetki_bitis('ivanov@ornek.org'));

echo "\n== 4. Atama kapısı: üç oy ve yazılı süre ==\n";
$aday = ['ad' => 'Yeni Aday', 'eposta' => 'aday@ornek.org', 'gorev_bitis' => '2027-12-31'];
$r = tg_atama_gecerli($aday, ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
den('üç kurucu oyu geçer', $r['ok'] === true, implode(' | ', $r['hata']));
den('  ve kurucu yazılmaz', $r['ok'] && empty($r['kayit']['kurucu']));
$r = tg_atama_gecerli($aday, ['Behiç Çetin', 'Murat Kayalar']);
den('iki oy yetmez', $r['ok'] === false);
$r = tg_atama_gecerli($aday, ['Behiç Çetin', 'Behiç Çetin', 'Behiç Çetin']);
den('aynı kişinin üç oyu sayılmaz', $r['ok'] === false);
/* ÖLÇÜM DÜZELTİLDİ. Eski adı "kurucu olmayanın oyu sayılmaz"dı ve
   oyu Zeynep Aksoy'a verdiriyordu — oysa Zeynep GÖREVDEKİ bir baş
   editördür ve kod "en az 3 GÖREVDEKİ BAŞ EDİTÖR oyu" arar, "kurucu
   oyu" değil. ayar.php de böyle yazar: bir koltuk boşaldığında
   "görevdeki baş editörler yerine birini atar". Ölçüm artık gerçekten
   yetkisiz olanı dener: kurulda hiç bulunmayan biri. */
$r = tg_atama_gecerli($aday, ['Behiç Çetin', 'Murat Kayalar', 'Kimse Yok']);
den('kurulda olmayanın oyu sayılmaz', $r['ok'] === false, implode(' | ', $r['hata']));
/* ÖLÇÜM BİR KEZ DAHA DÜZELTİLDİ — KURAL DEĞİŞTİ, KAPI DEĞİL.
   Kurul kararı F2 (14 Ağustos 2026): "Kurucuların olduğu zamanda, 2027
   sonuna kadar KURUCU baş editörler atar; kurucu kurul üyelerinin beşte
   üçü kabul ederse o kişi baş editör olur."

   Buradaki eski ölçüm, kuruluş döneminde SONRADAN ATANMIŞ bir baş
   editörün (Zeynep Aksoy) oyunun sayılmasını bekliyordu. Kod da öyle
   yapıyordu ve bugün ikisi de aynı sonucu veriyordu, çünkü görevdeki
   beş kişinin beşi de kuruculardı. Ayrışma tam olarak burada, yani
   kurula sonradan biri geldiğinde başlıyor: kurul, atamayı kurucuların
   yapmasını istedi.

   İki yönü de ölçülür; yalnız bugünü ölçen bir kapı, dönem kapandığı
   gün sessizce yanlış olur (OKUBENI 83). */
$r = tg_atama_gecerli($aday, ['Behiç Çetin', 'Murat Kayalar', 'Zeynep Aksoy']);
den('  kuruluş döneminde ATANMIŞ baş editörün oyu sayılmaz', $r['ok'] === false, implode(' | ', $r['hata']));
den('    ve gerekçe kurucu oyu istediğini söylüyor',
    (bool)preg_match('/kurucu baş editör oyu/u', implode(' ', $r['hata'])), implode(' | ', $r['hata']));
den('kuruluş dönemi bugün açık', tg_kurulus_atama_donemi() === true);
$oyKurulu = tg_atama_oy_kurulu();
den('  ve oy kurulunun tamamı kurucu', count(array_filter($oyKurulu, fn($k) => empty($k['kurucu']))) === 0,
    (string)count($oyKurulu));

/* DÖNEM KAPANDIKTAN SONRA eski kural sürer: oyu görevdeki baş editörler
   verir. Ayrı bir süreçte, yetki bitişi geçmişe çekilmiş bir ayarla
   ölçülür — tg_ayar dosyayı bir kez okur (OKUBENI 9). */
$gecmisAyar = kurul_senaryosu();
$gecmisAyar['bas_editor_atama']['yetki_bitis'] = '2020-12-31';
$gDizin = sys_get_temp_dir() . '/kkurul-gecmis-' . getmypid();
exec('rm -rf ' . escapeshellarg($gDizin) . ' && cp -a ' . escapeshellarg(KOPYA)
    . ' ' . escapeshellarg($gDizin));
file_put_contents($gDizin . '/ayar.php', "<?php\nreturn " . var_export($gecmisAyar, true) . ";\n");
$bet = tempnam(sys_get_temp_dir(), 'kk') . '.php';
file_put_contents($bet, "<?php\n\$_SERVER['HTTP_HOST']='127.0.0.1';\nrequire "
    . var_export($gDizin . '/ortak.php', true) . ";\n"
    . "\$r=tg_atama_gecerli(['ad'=>'Yeni Aday 2','gorev_bitis'=>'2035-12-31'],"
    . "['Behiç Çetin','Murat Kayalar','Zeynep Aksoy']);\n"
    . "echo json_encode(['donem'=>tg_kurulus_atama_donemi(),'ok'=>\$r['ok'],"
    . "'kurul'=>count(tg_atama_oy_kurulu()),'hata'=>\$r['hata']]);\n");
$gc = json_decode(trim((string)shell_exec('KUTADGU_DATA=' . escapeshellarg((string)getenv('KUTADGU_DATA'))
    . ' php ' . escapeshellarg($bet) . ' 2>&1')), true);
@unlink($bet); exec('rm -rf ' . escapeshellarg($gDizin));
ol2('dönem kapalıyken: kurul ' . (int)($gc['kurul'] ?? -1) . ' kişi, atama '
    . (!empty($gc['ok']) ? 'geçti' : 'geçmedi'));
den('dönem kapandığında kuruluş dönemi kapalı okunuyor', ($gc['donem'] ?? true) === false);
den('  oy kurulu görevdekilerin tamamı (8)', (int)($gc['kurul'] ?? 0) === 8, (string)($gc['kurul'] ?? 0));
den('  ve atanmış baş editörün oyu SAYILIR', ($gc['ok'] ?? false) === true,
    implode(' | ', (array)($gc['hata'] ?? [])));
$r = tg_atama_gecerli($aday, ['cbehic@gmail.com', sk_eposta('Murat Kayalar'), sk_eposta('Gökhan Kalağan')]);
den('e-posta ile de sayılır', $r['ok'] === true);
/* SÜRESİZ ATAMA — KURUL KARARI GELDİ (F1, 14 Ağustos 2026).
   Soru şuydu: atamaya yazılı bir süre konması zorunlu mu? Yanıt bir
   süre değil bir ÖLÇÜT getirdi. Yazılı bitiş tarihi hâlâ isteğe
   bağlıdır ve yazılmayan bir atama geçerlidir; ama artık "süresiz"
   değildir: görev bir yıllık dönemler hâlinde sürer ve her dönem
   sonunda etkinlik ölçütüyle ölçülür. Yani bu ölçümün beklediği
   davranış (atama geçerli) doğrudur, adı yanlıştı — bekleyen bir
   karar yok. Sürenin kendisi ayrı bir kapıda ölçülüyor:
   sinama/gorev-suresi-kapi.php. */
$r = tg_atama_gecerli(['ad' => 'Suresiz', 'eposta' => 'x@x.org'], ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
den('bitiş tarihi yazılmayan atama geçerli (görev dönemlerle ölçülür)', $r['ok'] === true,
    implode(' | ', $r['hata']));
den('  ama kayda süresiz olduğu yazılıyor',
    $r['ok'] && ($r['kayit']['gorev_bitis'] ?? 'x') === '', json_encode($r['kayit']['gorev_bitis'] ?? null));
$r = tg_atama_gecerli(['ad' => 'Kurucu Olmak Isteyen', 'gorev_bitis' => '2027-12-31', 'kurucu' => true],
                      ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
den('kurucu yazan aday geçer ama kurucu OLMAZ', $r['ok'] === true && empty($r['kayit']['kurucu']));
$r = tg_atama_gecerli(['ad' => 'Gecmis', 'gorev_bitis' => '2020-01-01'], ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
den('geçmiş tarihli atama geçmez', $r['ok'] === false);
$r = tg_atama_gecerli(['ad' => 'Yabanci Aday', 'gorev_bitis' => '2027-12-31', 'uyruk_sarti' => 'TR'],
                      ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
den('uyruk şartı alanı düşürülür', $r['ok'] === true && !isset($r['kayit']['uyruk_sarti']));
den('atama yetkisi bugün açık', tg_atama_yetkisi_acik() === true);
den('atama yetkisi 2027-12-31de kapanır', tg_bas_atama()['yetki_bitis'] === '2027-12-31');
/* 'oy_toplam' diye bir alan yok; tg_bas_atama() koltuk sayısını
   'koltuk' adıyla döndürür. Eski ad hiçbir zaman tutmayacak bir
   ölçümdü. */
den('gereken oy 3', tg_bas_atama()['oy_gerekli'] === 3, (string)tg_bas_atama()['oy_gerekli']);
den('  koltuk sayısı ayardan okunuyor', tg_bas_atama()['koltuk'] === 9, (string)tg_bas_atama()['koltuk']);

echo "\n== 5. Mali oy: oylar eşit ==\n";
$ku = tg_mali_oy_kurulu();
den('oy kurulu 5 kurucu + 3 görevdeki', count($ku) === 8, (string)count($ku));
$agirliklar = array_unique(array_column($ku, 'agirlik'));
den('bütün ağırlıklar 1', $agirliklar === [1], json_encode($agirliklar));
$onursalAd = array_map(fn($x) => $x['ad'], tg_onursal_bas_editorler());
den('onursal oy kurulunda yok', !array_intersect($onursalAd, array_column($ku, 'ad')));
$m = tg_mali_karar('Yıllık barındırma gideri', ['Behiç Çetin', 'Murat Kayalar', 'Zeynep Aksoy', 'Çiğdem Öztürk', 'Aleksandr Ivanov']);
den('5 oy 8 kişilik kurulda geçer', $m['ok'] === true, json_encode($m));
$m = tg_mali_karar('Yıllık barındırma gideri', ['Behiç Çetin', 'Murat Kayalar']);
den('2 oy geçmez', $m['ok'] === false);
$m = tg_mali_karar('Yıllık barındırma gideri', ['Ibrahim Al-Rashid', 'Mei-Ling Chen', 'Bozuk Tarih', 'Behiç Çetin', 'Murat Kayalar']);
den('onursalın oyu sayılmaz', $m['ok'] === false && $m['oy'] === 2, json_encode($m));

echo "\n== 6. İlke kapısı: sekiz ilke oylanamaz ==\n";
$konular = [
    ['Yazardan işlem ücreti alınması', 1],
    ['Arşiv indirmesine üyelik şartı konması', 5],
    ['Kapalı hakemliğe dönülmesi', 3],
    ['Bir kaydı sil', 4],
    ['Yazılımı kapalı kaynak yapmak', 6],
    ['Baş editörlükte uyruk şartı', 8],
    ['Bağış karşılığı kurul kararı', 7],
    ['Lisansı daralt', 2],
];
foreach ($konular as [$konu, $no]) {
    $g = tg_ilke_kapisi($konu);
    den("'" . $konu . "' durduruldu (Madde 2.$no)", $g['gecer'] === false && $g['ilke'] === $no, json_encode($g));
}
den("'Yıllık bütçe' geçer", tg_ilke_kapisi('Yıllık bütçenin onaylanması')['gecer'] === true);
den("'Yeni sunucu alınması' geçer", tg_ilke_kapisi('Yeni sunucu alınması')['gecer'] === true);
$m = tg_mali_karar('Yazardan işlem ücreti alınması', ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan', 'Mustafa Zihni Tunca', 'İbrahim Atilla Acar', 'Zeynep Aksoy', 'Çiğdem Öztürk', 'Aleksandr Ivanov']);
den('sekiz oybirliğiyle bile ücret oylanamaz', $m['ok'] === false && $m['ilke'] === 1, json_encode($m));

echo "\n== 7. Ayar süzgeci: ilke bir satırla delinemez ==\n";
$kayit = [];
$suz = tg_ayar_ilke_suz([
    'lisans' => 'Tüm hakları saklıdır', 'lisans_url' => 'https://ornek/kapali',
    'ucret_makale' => 250, 'abonelik_yillik' => 1200,
    'arsiv_onay' => true, 'bas_editor_uyruk' => ['TR'],
    'marka' => 'Kutadgu',
], $kayit);
den('kapalı lisans CC BY 4.0ya çekildi', $suz['lisans'] === 'CC BY 4.0', $suz['lisans']);
den('ücret anahtarları düşürüldü', !isset($suz['ucret_makale']) && !isset($suz['abonelik_yillik']));
den('arşiv onay kapısı kapatıldı', $suz['arsiv_onay'] === false);
den('uyruk şartı düşürüldü', !isset($suz['bas_editor_uyruk']));
den('ilgisiz anahtara dokunulmadı', $suz['marka'] === 'Kutadgu');
den('süzgeç ne yaptığını kaydetti', count($kayit) === 5, json_encode($kayit, JSON_UNESCAPED_UNICODE));
den('sıfır ücretli anahtar düşürülmez', isset(tg_ayar_ilke_suz(['ucret_makale' => 0])['ucret_makale']));
den('gerçek ayarda süzgeç kaydı boş', tg_ilke_suzgec_kaydi() === [], json_encode(tg_ilke_suzgec_kaydi()));
den('gerçek lisans açık', (string)tg_ayar('lisans') === 'CC BY 4.0');
den('sekiz ilke tam', count(tg_degismez_ilkeler()) === 8);

echo "\nGECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
