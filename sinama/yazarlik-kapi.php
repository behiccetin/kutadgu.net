<?php
/* =====================================================================
   Yazarlık kapısı: mantık ölçümü. Depoya girmez.

   Ölçülen kapı şudur: bir çalışma göndermek için önce hakemlik yapmış
   olmak aranıyor mu? Kuruluş döneminde aranmaz; çünkü arşiv boşken
   değerlendirilecek çalışma yoktur ve koşul sisteme ilk çalışmanın
   girmesini engeller. Dönem bitince koşul kendiliğinden geri gelir.
   Doktora ya da iki destekleyen şartı bundan hiç etkilenmez; o kapı
   her iki dönemde de aynıdır ve burada ayrıca ölçülür.

   Her senaryo KENDİ SÜRECİNDE koşar, çünkü tg_ayar() dosyayı bir kez
   okuyup bellekte tutar; aynı süreçte ikinci bir ayarı denemek,
   birincinin sonucunu ölçmek olurdu.

   Kullanım:
     php yazarlik-kapi.php            bütün bölümler
     php yazarlik-kapi.php <senaryo>  tek bölüm (alt süreç bunu çağırır)

   10. bölüm ayakta bir sunucu ister:
     cp -a <veri> <kopya-veri>
     KUTADGU_DATA=<kopya-veri> KTEST_DIR=<ktest> \
       php -S 127.0.0.1:8941 -t <ktest> krouter.php
   Sunucu başka bir kapıdaysa KPORT ile bildirilir.
   Yine de 429 gelirse: echo '{}' > <kopya-veri>/hiz-sinir.json
   ===================================================================== */
declare(strict_types=1);

const KAYNAK = '/home/claude/kg/kutadgunet';
/* KOPYA DİZİNİ AYRI VE HER KOŞUDA TAZELENİR.
   Eskiden burada da '/tmp/ky' yazıyordu — yazarlik-metin.php ile AYNI
   dizin — ve iki kapı da 'if (!is_dir(KOPYA))' ile bir kez kurup bir
   daha hiç tazelemiyordu. Bedeli ölçüldü: 12 Ağustos'ta kurulan kopya,
   13 Ağustos'ta doktora şartı kaldırıldıktan sonra da eski kodu
   taşımayı sürdürdü ve bu kapı 44/0 vererek YEŞİL göründü. Yeşildi
   çünkü DÜNKÜ KODU ölçüyordu.

   Bir kapının verdiği yeşil, ölçtüğü kodun bugünkü kod olmasına
   bağlıdır. İki kapı bir dizini paylaşırsa hangisinin yazdığı da
   belirsizleşir; dizin ayrıldı. */
const KOPYA  = '/tmp/ky-kapi';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* Geçerli ORCID'ler: sağlama basamağı doğru olanlar. Başvuru kapısı
   ORCID'i biçimden değil sağlamadan geçirir; uydurma bir numara
   ölçümü kapıya değil, ORCID denetimine takardı. */
const ORCID_A = '0000-0001-2345-6789';   /* koşulu sağlamayan yazar   */
const ORCID_B = '0000-0003-1234-5674';   /* hakemlik yapmış yazar     */
const ORCID_C = '0000-0002-1111-1115';   /* unvansız araştırmacı      */
const ORCID_D = '0000-0002-2222-2224';   /* doktoralı ortak yazar     */
const ORCID_E = '0000-0002-3333-3333';   /* bağımsız destekleyen      */

/* ---- Senaryolar: gerçek ayar dosyasını alır, gereken satırı değiştirir ---- */
function ayar_kur(string $senaryo): array {
    $a = include KAYNAK . '/ayar.php';
    $gecmis = '2020-01-01';

    switch ($senaryo) {

        case 'bugun':
        case 'uygulama':
        case 'http':
            break;

        case 'sinir-gunu':
            /* Bitiş tarihi tam bugün: sınırın hangi tarafında sayıldığı. */
            $a['kurulus_donemi']['bitis'] = date('Y-m-d');
            break;

        case 'sinir-ertesi':
            $a['kurulus_donemi']['bitis'] = date('Y-m-d', strtotime('-1 day'));
            break;

        case 'donem-bitti':
            $a['kurulus_donemi']['bitis'] = $gecmis;
            break;

        case 'gevsetme-kapali':
            /* Dönem sürüyor ama gevşetme elle kapatılmış. */
            $a['kurulus_donemi']['yazarlik_hakemlik_sarti'] = true;
            break;

        case 'anahtar-yok':
            /* Dönem sürüyor, gevşetme satırı hiç yazılmamış. */
            unset($a['kurulus_donemi']['yazarlik_hakemlik_sarti']);
            break;

        case 'blok-yok':
            /* Kuruluş dönemi bloğunun tamamı silinmiş. */
            unset($a['kurulus_donemi']);
            break;

        case 'bozuk-tarih':
            /* Tarih Türkçe biçimle yazılmış: okunamaz. */
            $a['kurulus_donemi']['bitis'] = '31.12.2027';
            break;
    }
    return $a;
}

/* ---- HTTP: sunucudaki gerçek başvuru kapısı ---- */
/* Başvuru ucu aynı IP'den on ikinci denemede 429 döner ve sayaç her
   istekte artar; ölçüm ikinci koşumda kendi izini bulup takılırdı.
   Her koşum kendine ayrılmış bir sınama adresinden (TEST-NET-3)
   konuşur, böylece betik veri dizinine dokunmadan yinelenebilir. */
/* GÖNDERİM HESAP İSTER (kurul kararı, 15 Ağustos 2026): kaydın bir
   sahibi olmalı. Kimliksiz istek 401 döner ve kapı ölçmek istediği
   yazarlık kuralına hiç ulaşamaz; bu yüzden önce bir oturum açılır ve
   çerez her isteğe eklenir. */
$KEREZ_YZ = '';
function gonder(string $yol, array $govde): array {
    global $KEREZ_YZ;
    static $ip = null;
    if ($ip === null) $ip = '203.0.113.' . random_int(1, 254);
    $port = getenv('KPORT') ?: '8941';
    $ham  = json_encode($govde, JSON_UNESCAPED_UNICODE);
    $bas  = "Content-Type: application/json\r\nX-Forwarded-For: $ip\r\n";
    if ($KEREZ_YZ !== '') $bas .= 'Cookie: ' . $KEREZ_YZ . "\r\n";
    $baglam = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => $bas,
        'content'       => $ham,
        'timeout'       => 10,
        'ignore_errors' => true,   /* 400/409 gövdesi de okunsun */
    ]]);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, $baglam);
    $h = $http_response_header ?? [];
    if ($KEREZ_YZ === '') {
        foreach ($h as $x) if (stripos($x, 'Set-Cookie:') === 0) { $KEREZ_YZ = explode(';', trim(substr($x, 11)))[0]; break; }
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı (KPORT=' . $port . ')'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 120)];
}
gonder('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);

/* Başvuru gövdesinin değişmeyen kısmı: ölçülen şey yazarlık kapısıdır,
   benzerlik ya da beyan alanları değil; onlar hep geçerli verilir. */
function basvuru_govde(array $ek): array {
    return array_merge([
        'makale_baslik'        => 'Yazarlik kapisi olcumu',
        'makale_ozet'          => 'Olcum icin gonderilmis kayittir.',
        'alan'                 => 'sos',
        'telif_kabul'          => true,
        /* 12 Ağustos: gönderim koşulları sihirbazın birinci adımı
           oldu ve beyanı sunucuda da aranıyor. Ölçülen şey yazarlık
           kapısıdır; koşul beyanı hep geçerli verilir. */
        'kosullar_okundu'      => true,
        'intihal_arac'         => 'iThenticate',
        'intihal_oran'         => 5,
        'intihal_tek_kaynak'   => 2,
        'intihal_link'         => 'https://ornek.org/rapor',
        'yz_kullanim'          => 'yok',
        'yz_etik_kabul'        => true,
        /* 15 Ağustos 2026: yeni beyanlar. Ölçülen şey yazarlık
           kapısıdır; bunlar hep geçerli verilir. */
        'cikar_catismasi'      => 'yok',
        'tek_gonderim'         => true,
        'fon_durum'            => 'yok',
        'yazar_tam'            => 'hepsi',
        'veri_beyan'           => 'yok',
        /* 15 Ağustos 2026: tam metin ve kaynakça gönderim anında
           isteniyor; künye dilindeki başlık ve özet de zorunlu.
           Ölçülen şey yazarlık kapısıdır, bu alanlar hep geçerli
           verilir. */
        'makale_baslik_en'     => 'Authorship gate measurement',
        'makale_ozet_en'       => 'A record submitted only for gate measurement.',
        'makale_metin'         => '<h2>Giris</h2><p>' . str_repeat('olcum metni ', 500) . '</p>',
        'makale_kaynakca'      => '<p>Olcum, K. (2026). Yazarlik kapisi. Sinama.</p>',
    ], $ek);
}

/* ---- Bölümlerin denetimleri ---- */
function kos(string $senaryo): void {
    switch ($senaryo) {

    case 'bugun':
        den('kuruluş dönemi bitişi ayardan okunuyor', tg_kurulus_bitis() === '2027-12-31',
            tg_kurulus_bitis());
        den('bugün dönem içindeyiz', tg_kurulus_donemi());
        den('hakemlik koşulu ARANMIYOR', !tg_yazarlik_hakemlik_sarti());
        den('koşul cümlesi gevşemiş hâli anlatıyor',
            mb_strpos(tg_yazarlik_kosulu_metni(), 'aranmaz') !== false, tg_yazarlik_kosulu_metni());
        den('kenar notu doludur', tg_yazarlik_kosulu_kisa() !== '');
        /* DESTEKLEYEN DÜZENİ KAPATILDI — kurul kararı, 14 Ağustos 2026.
           Bu iki ölçüm düzenin AÇIK olduğunu arıyordu; o kural artık
           yok. Kapı yeni durumu ölçer, eskisinin kalıntısını değil.

           Anahtar SİLİNMEMİŞ olmalı: silinirse tg_ayar() varsayılana
           düşer ve varsayılan true'dur — düzen sessizce geri açılırdı.
           Kapatmanın doğru yolu, kapalı olduğunu YAZMAKtır. */
        den('destekleyen düzeni KAPALI', tg_ayar('kefil_acik') === false,
            var_export(tg_ayar('kefil_acik'), true));
        den('  anahtar silinmemiş (varsayılana düşüp geri açılmıyor)',
            tg_ayar('kefil_acik', null) !== null);
        den('  tek kapı da kapalı diyor', tg_destek_duzeni_acik() === false);
        /* Sayı KALIR: kapanan şey yeni kayıt üretilmesidir. Eski
           kayıtlar okunurken bu sayı hâlâ gerekir. */
        den('destekleyen sayısı yine iki (eski kayıtlar okunabilsin)',
            (int)tg_ayar('kefil_sayisi') === 2, (string)tg_ayar('kefil_sayisi'));
        den('unvan ölçütü yerinde', tg_unvan_yeterli('Dr.') && !tg_unvan_yeterli('Arş. Gör.'));
        break;

    case 'sinir-gunu':
        /* Sınır tarihi dâhildir: 'bitiş' o günün sonu demektir, o günün
           başı değil. Tersi olsaydı ilan edilen tarihte kapı bir gün
           erken kapanırdı. */
        den('sınır günü dönem İÇİNDE sayılır', tg_kurulus_donemi());
        den('  ve koşul o gün de aranmaz', !tg_yazarlik_hakemlik_sarti());
        /* CÜMLEDEKİ TARİH ANCAK GEVŞETİLECEK BİR KOŞUL VARSA ANLAMLI.
           Doktora şartı kalktıysa cümle dönemden söz etmez ve içinde
           hiçbir tarih bulunmaz; ölçüm o zaman tersine döner. Bu dal
           silinmedi, çünkü kurul kararı geri dönebilir. */
        if (tg_yazarlik_doktora_sarti()) {
            den('metindeki tarih o günkü ayarla aynı',
                mb_strpos(tg_yazarlik_kosulu_metni(), tg_kurulus_bitis_ad()) !== false,
                tg_kurulus_bitis_ad());
        } else {
            den('doktora şartı yokken cümlede tarih de yok',
                preg_match('/\\b(19|20)\\d{2}\\b/', tg_yazarlik_kosulu_metni()) === 0,
                tg_yazarlik_kosulu_metni());
        }
        break;

    case 'sinir-ertesi':
        den('sınırın ertesi günü dönem KAPALI', !tg_kurulus_donemi());
        den('  ve koşul geri gelir', tg_yazarlik_hakemlik_sarti());
        break;

    case 'donem-bitti':
        den('dönem kapandı', !tg_kurulus_donemi());
        den('koşul yeniden aranır', tg_yazarlik_hakemlik_sarti());
        /* Dönem kapanınca HAKEMLİK koşulu geri gelir; ama doktora
           şartı kalkmışsa cümle yine de doktoradan söz etmez, çünkü
           tg_yazarlik_kosulu_metni() doktora dalını en başta döndürür
           ve dönem dalına hiç varmaz. Sıra bilerek böyledir: aşağıdaki
           dalların hepsi doktoradan söz eder ve biri okunursa sistem
           uygulamadığı bir koşulu duyurmuş olur. */
        if (tg_yazarlik_doktora_sarti()) {
            den('koşul cümlesi katı hâline döner',
                mb_strpos(tg_yazarlik_kosulu_metni(), 'Tamamlanmış en az bir hakemlik') === 0,
                tg_yazarlik_kosulu_metni());
            den('kenar notu susar', tg_yazarlik_kosulu_kisa() === '', tg_yazarlik_kosulu_kisa());
        } else {
            den('doktora şartı yokken cümle dönem kapanınca da aynı kalır',
                mb_strpos(tg_yazarlik_kosulu_metni(), 'Doktora derecesi aranmaz') === 0,
                tg_yazarlik_kosulu_metni());
            den('  kenar notu susmaz: söyleyecek bir şeyi var',
                tg_yazarlik_kosulu_kisa() !== '');
        }
        /* Dönemden bağımsız aynı kalmalı: bir dönemin bitmesi kapalı
           bir düzeni geri açamaz. */
        den('destekleyen düzeni dönem bitince de KAPALI', tg_ayar('kefil_acik') === false);
        den('destekleyen sayısı yine iki', (int)tg_ayar('kefil_sayisi') === 2);
        den('unvan ölçütü değişmedi', tg_unvan_yeterli('Dr.') && !tg_unvan_yeterli('Arş. Gör.'));
        break;

    case 'gevsetme-kapali':
        den('dönem hâlâ açık', tg_kurulus_donemi());
        den('ayar kapalıyken koşul aranır', tg_yazarlik_hakemlik_sarti());
        if (tg_yazarlik_doktora_sarti()) {
            den('metin dönem içinde bile katı hâlde',
                mb_strpos(tg_yazarlik_kosulu_metni(), 'Tamamlanmış en az bir hakemlik') === 0,
                tg_yazarlik_kosulu_metni());
            den('kenar notu susar', tg_yazarlik_kosulu_kisa() === '');
        } else {
            den('doktora şartı yokken gevşetme ayarı cümleyi değiştirmez',
                mb_strpos(tg_yazarlik_kosulu_metni(), 'Doktora derecesi aranmaz') === 0,
                tg_yazarlik_kosulu_metni());
            den('  kenar notu yine konuşur', tg_yazarlik_kosulu_kisa() !== '');
        }
        break;

    case 'anahtar-yok':
        /* Yazılmamış bir gevşetme uygulanmaz: array_key_exists ile
           bakılmasının nedeni budur. Varsayılan, gevşek değil katı
           taraf olmalıdır. */
        den('dönem açık', tg_kurulus_donemi());
        den('yazılmamış gevşetme uygulanmaz', tg_yazarlik_hakemlik_sarti());
        break;

    case 'blok-yok':
        den('bitiş tarihi boş okunur', tg_kurulus_bitis() === '', tg_kurulus_bitis());
        den('dönem kapalı sayılır', !tg_kurulus_donemi());
        den('koşul aranır', tg_yazarlik_hakemlik_sarti());
        den('tarih adı boş kalır', tg_kurulus_bitis_ad() === '', tg_kurulus_bitis_ad());
        /* Tarih okunamadığında dönem de kapalı sayıldığı için metin katı
           dala düşer; gevşek daldaki "tarih boşsa cümleyi kısalt"
           koruması bu yüzden hiç çalışmaz. Zararsız bir fazlalıktır,
           ölçüm onu koşul saymaz; ölçülen, cümlenin tam kalmasıdır. */
        den('metin tam bir cümledir',
            mb_substr(rtrim(tg_yazarlik_kosulu_metni()), -1) === '.',
            tg_yazarlik_kosulu_metni());
        break;

    case 'bozuk-tarih':
        /* Okunamayan bir tarih, dönemi süresiz açık bırakmamalı:
           gevşetmenin unutulup kalıcılaşması bu sistemin en kolay
           bozulma yoludur. */
        den('tanınmayan tarih biçimi okunmaz', tg_kurulus_bitis() === '', tg_kurulus_bitis());
        den('bozuk tarih dönemi süresiz açmaz', !tg_kurulus_donemi());
        den('koşul aranır', tg_yazarlik_hakemlik_sarti());
        break;

    case 'uygulama':
        /* Bugün metin ile kod aynı şeyi söylüyor: koşul duyurulmuyor ve
           aranmıyor. Ölçülen şey, dönem bittiğinde bu uyumun sürüp
           sürmeyeceğidir. Cümle o gün "en az bir hakemlik" demeye
           başlayacak; onu arayan bir kod var mı? */
        $api   = (string)file_get_contents(KAYNAK . '/api/index.php');
        $hesap = (string)file_get_contents(KAYNAK . '/k/hesap.php');
        den('bugün duyurulan ile uygulanan aynı: koşul yok, kapı yok',
            !tg_yazarlik_hakemlik_sarti());
        den('gevşetme yalnızca metni etkiliyor, gönderim kapısını değil',
            mb_strpos($api, 'kurulus_donemi') === false);
        /* BU ÖLÇÜM 13 AĞUSTOS 2026'DA DEĞİŞTİ ve gerekçesi yazılıdır.
           Eskiden aranan şey şuydu: "hakemlik koşulunu UYGULAYAN bir
           satır var mı, ki dönem bittiğinde duyuru boşa düşmesin."
           Kurul o gün doktora şartını kaldırdı ve yazarlık kapısını
           editöre taşıdı; artık gönderim ucunda aranacak bir yazarlık
           koşulu yok, olması da gerekmiyor.

           Ölçüm silinmedi, YERİ DEĞİŞTİ: koşulun uygulanıp
           uygulanmadığı değil, DUYURU İLE UYGULAMANIN AYNI KAYNAKTAN
           okunup okunmadığı ölçülüyor. Kapının nerede olduğu değişebilir;
           değişmemesi gereken şey, sistemin söylediği ile yaptığının
           tek yerden gelmesidir. */
        den('yazarlık koşulu kodda tek kaynaktan okunuyor',
            mb_strpos($api, 'tg_yazarlik_doktora_sarti') !== false,
            'gönderim ucu koşulu ayar yerine kendi içinde tanımlıyor olabilir');
        den('koşul ayar dosyasında yazılı',
            array_key_exists('yazarlik_doktora_sarti', (array)require KAYNAK . '/ayar.php'));
        break;

    case 'http':
        /* Kapının kendisi: gerçek başvuru ucu. Sunucu, ktest'teki
           gerçek ayarla koşar; yani bu bölüm kuruluş dönemi içindeki
           davranışı ölçer. */
        $y1 = gonder('/api/yazar-basvuru', basvuru_govde([
            'unvan' => 'Dr.', 'ad' => 'Hakemlik Yapmamis Yazar',
            'eposta' => 'kapi-a@ornek.org', 'kurum' => 'Örnek Üniversitesi',
            'orcid' => ORCID_A,
        ]));
        den('koşulu sağlamayan biri dönem içinde çalışma gönderebiliyor',
            !empty($y1['ok']), (string)($y1['hata'] ?? ''));

        /* Koşulu zaten sağlayan kişi: sınama verisinde tamamlanmış
           hakemliği olan araştırmacı. Kapı ikisini de aynı şekilde
           karşılamalı; hakemlik geçmişi gönderimi ne açar ne kapatır. */
        $y2 = gonder('/api/yazar-basvuru', basvuru_govde([
            'unvan' => 'Dr.', 'ad' => 'Ibrahim Al-Rashid',
            'eposta' => 'gizli0@ornek.org', 'kurum' => 'Örnek Üniversitesi',
            'orcid' => ORCID_B,
        ]));
        den('koşulu zaten sağlayan biri de geçiyor', !empty($y2['ok']), (string)($y2['hata'] ?? ''));

        /* KURUL KARARI, 13 AĞUSTOS 2026: doktora şartı kaldırıldı.
           Bu ölçüm TERSİNE ÇEVRİLDİ ve çevrilmesi kaydın kendisidir:
           eskiden "unvansız kişi tek başına gönderemiyor" doğrulanıyordu,
           şimdi GÖNDEREBİLDİĞİ doğrulanıyor. Bir kapının kaldırıldığını
           söylemek yetmez; kaldırıldığı ölçülür. */
        $y3 = gonder('/api/yazar-basvuru', basvuru_govde([
            'unvan' => '', 'ad' => 'Unvansiz Arastirmaci',
            'eposta' => 'kapi-c@ornek.org', 'kurum' => 'Örnek Üniversitesi',
            'orcid' => ORCID_C,
        ]));
        /* Ölçüm yürürlükteki karara göre dallanır. Şart geri açılırsa
           beklenen davranış TERSİNE döner ve kapı da onu ölçer; tek bir
           dünyayı ölçen kapı, öteki dünyada sessizce yanlış olur. */
        if (tg_yazarlik_doktora_sarti()) {
            den('şart açıkken unvansız kişi tek başına GÖNDEREMİYOR',
                empty($y3['ok']), (string)($y3['hata'] ?? ''));
        } else {
            den('unvansız kişi tek başına gönderebiliyor', !empty($y3['ok']), (string)($y3['hata'] ?? ''));
        }

        /* ORCID KALKMADI. Doktora bir yeterlik iddiasıdır, ORCID bir
           kimlik; açık hakemlikte adın doğru kişiye bağlanması
           vazgeçilemez. Bir şartı kaldırırken yanındakini de düşürmek,
           en sık yapılan gevşetme hatasıdır. */
        $y3b = gonder('/api/yazar-basvuru', basvuru_govde([
            'unvan' => '', 'ad' => 'Orcidsiz Arastirmaci',
            'eposta' => 'kapi-f@ornek.org', 'kurum' => 'Örnek Üniversitesi',
            'orcid' => '',
        ]));
        den('ORCID hâlâ zorunlu', empty($y3b['ok']), (string)($y3b['hata'] ?? ''));

        /* Aynı kişi, iki destekleyenle geçer: gevşemeyen şartın kapalı
           bir kapı değil, yazılı bir yol olduğu buradan görülür. */
        $y4 = gonder('/api/yazar-basvuru', basvuru_govde([
            'unvan' => '', 'ad' => 'Unvansiz Arastirmaci',
            'eposta' => 'kapi-c@ornek.org', 'kurum' => 'Örnek Üniversitesi',
            'orcid' => ORCID_C,
            'kefiller' => [
                ['ad' => 'Ayse Yilmaz', 'unvan' => 'Dr.', 'kurum' => 'Örnek Üniversitesi',
                 'orcid' => ORCID_D, 'eposta' => 'ortak@ornek.org', 'ilgi' => 'ortak_yazar'],
                ['ad' => 'Kemal Deniz', 'unvan' => 'Prof. Dr.', 'kurum' => 'Başka Üniversite',
                 'orcid' => ORCID_E, 'eposta' => 'bagimsiz@ornek.org', 'ilgi' => 'bagimsiz'],
            ],
            /* Ortak yazarın E-POSTASI 15 Ağustos 2026'dan beri zorunlu:
               eklenen her yazara yazarlığı bildiriliyor ve adresi
               olmayana bildirilemez (ortak-yazar-kapi.php). */
            'yazarlar' => [
                ['ad' => 'Ayse Yilmaz', 'unvan' => 'Dr.', 'kurum' => 'Örnek Üniversitesi',
                 'orcid' => ORCID_D, 'eposta' => 'ortak@ornek.org'],
            ],
        ]));
        den('iki destekleyenle de geçiyor', !empty($y4['ok']), (string)($y4['hata'] ?? ''));
        /* DESTEKLEYEN ARAŞTIRMACI ARTIK İSTENMEZ, dolayısıyla onay
           bağlantısı da gitmez. Eskiden "iki bağlantı gitti" ölçülüyordu;
           şimdi ölçülen şey, İSTENMEYEN bir onay için kimseye posta
           gönderilmediğidir. Kaldırılmış bir düzenin sessizce posta
           atmaya devam etmesi, kaldırılmamış olması demektir. */
        if (tg_yazarlik_doktora_sarti()) {
            /* Şart açıkken destekleyen düzeni gerçekten çalışır ve iki
               kişiye onay bağlantısı gider. */
            den('  şart açıkken iki destekleyene onay bağlantısı gidiyor',
                (int)($y4['kefil'] ?? 0) === 2, (string)($y4['kefil'] ?? 0));
        } else {
            den('  ve gereksiz onay bağlantısı gönderilmiyor',
                (int)($y4['kefil'] ?? 0) === 0, (string)($y4['kefil'] ?? 0));
        }
        break;
    }
}

/* ---- Koşum ---- */
$bolumler = [
    'bugun'           => '1. Kuruluş dönemi içinde: kapı gevşek',
    'sinir-gunu'      => '2. Sınır tarihi tam gününde',
    'sinir-ertesi'    => '3. Sınırın ertesi günü',
    'donem-bitti'     => '4. Dönem bittikten sonra',
    'gevsetme-kapali' => '5. Dönem açık, gevşetme ayarı kapalı',
    'anahtar-yok'     => '6. Gevşetme satırı hiç yazılmamış',
    'blok-yok'        => '7. Kuruluş dönemi bloğu yok',
    'bozuk-tarih'     => '8. Bitiş tarihi okunamıyor',
    'uygulama'        => '9. Duyurulan koşulu uygulayan kod',
    'http'            => '10. Gerçek başvuru kapısı (sunucu)',
];

$arg = $argv[1] ?? '';

if ($arg === '') {
    /* Kopya dizin: senaryolar gerçek kaynağa değil buna yazar. */
    {
        exec('rm -rf ' . escapeshellarg(KOPYA) . ' && cp -a ' . escapeshellarg(KAYNAK)
            . ' ' . escapeshellarg(KOPYA) . ' && rm -rf ' . escapeshellarg(KOPYA . '/.git'), $c, $k);
        if ($k !== 0 || !is_dir(KOPYA)) { echo "KOPYA kurulamadi: " . KOPYA . "\n"; exit(2); }
    }
    $tg = 0; $tk = 0;
    foreach ($bolumler as $s => $ad) {
        echo "== $ad ==\n";
        $cikti = [];
        exec('php ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($s) . ' 2>&1', $cikti);
        foreach ($cikti as $satir) {
            echo $satir . "\n";
            if (strpos($satir, 'GECTI') !== false) $tg++;
            if (strpos($satir, 'KALDI') !== false) $tk++;
        }
        echo "\n";
    }
    echo "----------------------------------------\n";
    echo "GECTI: $tg   KALDI: $tk\n";
    exit($tk > 0 ? 1 : 0);
}

if (!isset($bolumler[$arg])) { echo "bilinmeyen senaryo: $arg\n"; exit(2); }
file_put_contents(KOPYA . '/ayar.php', "<?php\nreturn " . var_export(ayar_kur($arg), true) . ";\n");
require_once KOPYA . '/k/hesap.php';
/* Alt süreç özet satırı basmaz: üst süreç satırlardaki GECTI/KALDI
   sözcüklerini sayar, özet satırı iki kez sayılmaya yol açardı. */
kos($arg);
