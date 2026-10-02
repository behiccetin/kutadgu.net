<?php
/* =====================================================================
   KUTADGU - ARŞİV DÖKÜMÜ / ARCHIVE DUMP
   ---------------------------------------------------------------------
   Neden bu dosya var
   ---------------------------------------------------------------------
   Arşivin tamamı herkese açıktır ve öyle kalacaktır. Ancak dökümün
   istek geldiği anda üretilmesi, arşiv büyüdükçe kendi başına bir
   yavaşlama sebebidir: her indirme, bütün kayıtların okunmasını,
   süzülmesini ve yeniden kodlanmasını gerektirir. Kötü niyet gerekmez;
   yeterince ilgi de aynı sonucu verir. Kötü niyet varsa zaten en ucuz
   saldırı yolu budur: tek bir adres, tek bir istek, sunucunun bütün
   arşivi yeniden kurması.

   Çözüm erişimi kısmak değildir. Çözüm ÜRETİMİ İSTEKTEN AYIRMAKTIR.
   Döküm, yayım anında ve her gece bir kez üretilir ve durağan bir dosya
   olarak durur. İndiren kişi hazır bir dosyayı alır; sunucu o sırada
   hiçbir şey hesaplamaz.

   Üç katman vardır, çünkü herkes aynı ağırlığı taşımak zorunda değildir:
     (a) üstveri dökümü      - künye, kimlik, karar özeti. Küçüktür ve
                               isteyenlerin çoğunun istediği budur.
     (b) üstveri ile tam metin - yıllara bölünmüş parçalar, artı bütün
                               yılları taşıyan tek dosya (eski
                               bağlantılar kırılmasın diye).
     (c) görsel ve belge paketi - en ağırı. Kuyruğa alınır ve günde bir
                               kez üretilir.

   Her dosyanın yanında SHA-256 özeti ve üretim tarihi durur.

   DEĞİŞMEZ KURAL
   ---------------------------------------------------------------------
   Buraya onay kapısı konulamaz. Erişim kimlik, kayıt, üyelik, onay ya
   da bedel şartına bağlanamaz. Aşağıdaki teknik önlemler erişimi
   GECİKTİREBİLİR (sıra, hız sınırı), hiçbir durumda REDDEDEMEZ.
   Gerekçesi ikidir: tüzük taslağı Ek A Madde 2.5 bunu değiştirilemez
   bir ilke olarak yazar; ve DOAJ, okurdan kayıt ya da izin isteyen
   yayınları kabul etmez.

   Kural yalnızca burada yazılı değildir: ortak.php içindeki
   tg_ayar_ilke_suz(), 'arsiv_onay', 'arsiv_kayit_gerek',
   'arsiv_uyelik_gerek' ve 'dokum_onay' anahtarlarını ayar dosyası ne
   yazarsa yazsın kapatır. Bu dosya o kapıya yaslanır, kendi kapısını
   kurmaz.

   ---------------------------------------------------------------------
   Why this file exists
   ---------------------------------------------------------------------
   The whole archive is open to everyone and will remain so. Producing
   the dump at the moment of the request, however, becomes a cause of
   slowness by itself as the archive grows. The remedy is not to narrow
   access but to SEPARATE PRODUCTION FROM THE REQUEST: the dump is built
   at publication and once every night, and is served as a static file.

   No approval gate may be placed here. Access cannot be made
   conditional on identity, registration, membership, approval or
   payment. The technical measures below may DELAY access; they may
   never REFUSE it.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/veri.php';

/* Dökümdeki bütün tarihler tek bir saat diliminde yazılır. Üretimi
   yapan üç yer vardır (API, gecelik görev, ilk kurulum) ve ikisi saat
   dilimini kendisi kuruyordu; üçüncüsü kurmayınca aynı arşivin
   dosyaları farklı dilimlerde damgalanıyordu. Damga bir kayıttır;
   kaydın birimi değişken olamaz. API ve gecelik görev zaten aynı dilimi
   kuruyor; burada da aynısı kurulunca üçü ayrışmaz. */
date_default_timezone_set('Europe/Istanbul');

if (!function_exists('dk_dizin')) {

    /* Biçim sürümü. Dosyaların içeriği değişirse artırılır; indiren
       taraf elindekinin hangi biçimde olduğunu bilsin.
       3: her çalışma kaydına 'asama', 'rapor_sayisi' ve
       'ceviri_parmak_izi' eklendi. Alan kaldırılmadı, yalnızca eklendi;
       eski biçimi okuyan bir betik kırılmaz.
       Sürüm 3 daha yayına girmediği için çeviri özeti ayrı bir sürüm
       açmadı; ikisi aynı biçimde çıkıyor. Yayına girmiş bir sürüme
       alan eklenseydi numara artırılmak zorundaydı. */
    if (!defined('DK_SURUM')) define('DK_SURUM', 3);

    /* ---------------------------------------------------------------
       YERLER
       Döküm dosyaları veri dizinindedir, depoda değil. İki sebebi var:
       depo her gönderimde yerine konur ve oraya yazılan dosya silinir;
       ayrıca veri dizini web kökünün dışındadır, dosyalar yalnızca
       dokum.php üzerinden ve bilinen başlıklarla verilir.
       --------------------------------------------------------------- */
    function dk_dizin(): string {
        $d = k_veri_dizin() . '/dokum';
        if (!is_dir($d)) @mkdir($d, 0755, true);
        return $d;
    }
    function dk_yol(string $ad): string { return dk_dizin() . '/' . $ad; }

    /* Dosya adı dışarıdan gelir; yalnızca beklenen kalıp kabul edilir.
       Dizin gezinmesi bu yüzden imkânsızdır. */
    function dk_ad_gecerli(string $ad): bool {
        return (bool)preg_match('/^kutadgu-[a-z0-9\-]{1,60}\.(json|txt|zip)$/', $ad);
    }

    /* ---------------------------------------------------------------
       KAYIT SÜZGEÇLERİ
       Beyaz liste kullanılır: yeni bir alan eklendiğinde kendiliğinden
       dışarı sızmasın diye. Dökümde kişisel veri yoktur; e-posta
       adresleri, erişim anahtarları, hesap kayıtları ve okuyucu
       adresleri bilerek dışarıda bırakılır. Bilimsel kayıt kişisel
       veriye ihtiyaç duymaz.
       --------------------------------------------------------------- */
    function dk_kisi(array $k): array {
        return ['unvan' => (string)($k['unvan'] ?? ''), 'ad' => (string)($k['ad'] ?? ''),
                'kurum' => (string)($k['kurum'] ?? ''), 'orcid' => (string)($k['orcid'] ?? '')];
    }

    function dk_hakem(array $h): array {
        $out = [
            'ad'        => (string)($h['ad'] ?? ''),
            'karar'     => (string)($h['karar'] ?? ''),
            'rapor'     => (string)($h['rapor'] ?? ''),
            'tarih'     => (string)($h['tarih'] ?? ''),
            'sifat'     => is_array($h['sifat'] ?? null) ? array_values($h['sifat']) : [],
            'yetkinlik' => (string)($h['yetkinlik'] ?? ''),
            'atayan'    => is_array($h['atayan'] ?? null)
                            ? ['tur' => (string)($h['atayan']['tur'] ?? ''),
                               'ad'  => (string)($h['atayan']['ad'] ?? ''),
                               'tarih' => (string)($h['atayan']['tarih'] ?? '')]
                            : null,
            'endeks'    => is_array($h['endeks'] ?? null) ? array_values($h['endeks']) : [],
        ];
        $p = is_array($h['profil'] ?? null) ? $h['profil'] : [];
        $out['profil'] = ['unvan' => (string)($p['unvan'] ?? ''), 'kurum' => (string)($p['kurum'] ?? ''),
                          'orcid' => (string)($p['orcid'] ?? '')];   /* e-posta bilerek alınmaz */
        $sur = [];
        foreach (($h['raporlar'] ?? []) as $r) {
            if (!is_array($r)) continue;
            $sur[] = ['karar' => (string)($r['karar'] ?? ''), 'rapor' => (string)($r['rapor'] ?? ''),
                      'tarih' => (string)($r['tarih'] ?? '')];
        }
        if ($sur) $out['raporlar'] = $sur;

        /* ---- YAZAR İLE HAKEM ARASINDAKİ YAZIŞMA ----
           DÖKÜM, SAYFANIN GÖSTERDİĞİNDEN AZ ŞEY TAŞIYAMAZ. Yazışma
           çalışmanın kendi sayfasında ve herkese açık listede
           görünüyordu, ama kalıcı arşiv dökümüne hiç girmiyordu: yani
           okurun bugün gördüğü bir kayıt, yarının arşivinde
           bulunmayacaktı. Açık hakemliğin anlamı, değerlendirmenin
           kendisinin de denetlenebilir bir belge olmasıdır; o belgenin
           yarısını dışarıda bırakan bir döküm kalıcı kayıt değildir.

           Alanlar tg_diyalog()'dan geçirilerek alınır: yön, metin,
           tarih ve hangi metin sürümüne yazıldığı. Anahtar, şifre ve
           e-posta buraya da girmez — dökümün gizlilik ölçütü sayfanın
           ölçütüyle aynıdır. */
        if (function_exists('tg_diyalog')) {
            $diy = tg_diyalog($h);
            if ($diy) $out['diyalog'] = $diy;
        }
        return $out;
    }

    /* Yazar listesi tek yerden kurulur: hem üstveri hem tam metin
       katmanı aynı listeyi kullansın, ikisi ayrışmasın. */
    function dk_yazarlar(array $y): array {
        $b = $y['yazar_bilgi'] ?? null;
        $yz = [];
        if (is_array($b)) $yz[] = dk_kisi($b);
        elseif (trim((string)($y['yazar'] ?? '')) !== '') $yz[] = ['ad' => (string)$y['yazar']];
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) { if (is_array($ya)) $yz[] = dk_kisi($ya); }
        return $yz;
    }

    /* Çalışmanın yılı: dökümün yıllara bölünmesi buna göredir. */
    function dk_yil(array $y): string {
        $t = (string)($y['tarih'] ?? '');
        return preg_match('/^(\d{4})/', $t, $m) ? $m[1] : 'tarihsiz';
    }

    /* ---- KATMAN A: üstveri ----
       Tam metin, hakem raporlarının gövdesi, oy gerekçeleri ve şerh
       metinleri burada YOKTUR. Bu katman bir künye dizinidir: kim, ne,
       ne zaman, hangi kimlikle, hangi kararla. Toplayıcıların ve
       kaynakça araçlarının istediği budur ve küçüktür. */
    function dk_yazi_ustveri(array $y): array {
        $onay = tg_onay_durumu($y);
        $hk = 0; $hkAd = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $hk++;
            $hkAd[] = ['ad' => (string)($h['ad'] ?? ''), 'karar' => (string)($h['karar'] ?? ''),
                       'tarih' => (string)($h['tarih'] ?? '')];
        }
        $out = [
            'tamga'      => (string)($y['bcid'] ?? ''),
            'eski_tamga' => array_values(array_filter(array_map('strval', (array)($y['eski_kod'] ?? [])))),
            'adres'      => tg_kok() . tg_yazi_yolu($y),
            'slug'       => (string)($y['slug'] ?? ''),
            /* 'tur' çalışmanın hangi YOLDA olduğunu söyler: yazar hakemliğe
               açtığı anda 'hakemli' yazılır, tek bir rapor bile gelmemiştir.
               Döküm kalıcı kayıttır ve onu okuyan biri "bu çalışma hakemden
               geçti mi" sorusunu 'tur'dan yanıtlayamamalı; aşama ve rapor
               sayısı bu yüzden kaydın kendisinde durur. Eski alan geriye uyum
               için yerinde bırakıldı. */
            'tur'        => (string)($y['tur'] ?? ''),
            'asama'      => tg_hakem_asamasi($y),
            'rapor_sayisi' => tg_rapor_sayisi($y),
            'tarih'      => (string)($y['tarih'] ?? ''),
            'yil'        => dk_yil($y),
            'alan'       => (string)($y['alan'] ?? ''),
            'dil'        => (string)($y['dil'] ?? ''),
            'baslik'     => (string)($y['baslik'] ?? ''),
            'baslik_en'  => (string)($y['baslik_en'] ?? ''),
            'ozet'       => (string)($y['ozet'] ?? ''),
            'ozet_en'    => (string)($y['ozet_en'] ?? ''),
            'anahtar'    => tg_metin($y['anahtar'] ?? ''),
            'anahtar_en' => tg_metin($y['anahtar_en'] ?? ''),
            /* ÇEVİRİNİN KÜNYESİ DÖKÜME DE GİRER.
               İngilizce metin döküme giriyordu ama NASIL üretildiği
               girmiyordu; dökümü indiren biri yazarın yazdığı metinle
               onaysız bir makine çevirisini ayırt edemiyordu. Metin
               çıkarılmadı, künyesi eklendi: eksik bir kayıt yayımlamak,
               arşivin kendi sözüyle çelişirdi. Künyesi olmayan
               çalışmada alan hiç basılmaz. */
            'ceviri_en'  => (function ($y) {
                $c = tg_yazi_ceviri($y, 'en');
                if (!$c) return null;
                return ['kaynak' => $c['kaynak'], 'ceviren' => $c['ceviren'],
                        'onay' => $c['onay'], 'yayin' => tg_ceviri_yayin_mi($c)];
            })($y),
            'lisans'     => (string)tg_ayar('lisans', 'CC BY 4.0'),
            'parmak_izi' => tg_metin_ozeti($y),
            /* Çevirinin ayrı özeti. Taban değer DEĞİŞMEDİ; elinde eski
               listeyi tutan okur olmayan bir değişiklik görmesin diye
               genişletilmedi, yanına yazıldı. Çevirisi olmayan
               çalışmada alan hiç basılmaz. Bkz. ortak.php,
               tg_ceviri_ozeti(). */
            'ceviri_parmak_izi' => tg_ceviri_ozeti($y) ?: null,
            'yazarlar'   => dk_yazarlar($y),
            'hakem_sayisi' => $hk,
            'hakemler'   => $hkAd,               /* ad ve karar; rapor gövdesi katman b'de */
            'degerlendirme' => ['onayli' => $onay['onayli'], 'olumlu' => $onay['kabul'],
                                'esik' => $onay['esik'], 'ret' => $onay['ret']],
            'kayit_sayisi'   => count(tg_kayitlar($y)),
            'oylama_sayisi'  => count(tg_oylamalar($y)),
            'serh_sayisi'    => count(tg_serhler($y)),
        ];
        if (tg_kurulus_istisnasi($y)) $out['kurulus_istisnasi'] = true;
        return $out;
    }

    /* ---- KATMAN B: üstveri ile tam metin ----
       Bugüne kadar arsiv.php'nin ürettiği kaydın kendisidir; biçim
       değişmedi, yalnızca üretildiği an değişti. */
    function dk_yazi(array $y): array {
        $out = [
            'tamga'      => (string)($y['bcid'] ?? ''),
            /* Kuruluş döneminde verilmiş eski kodlar da dışa aktarılır:
               eski bir bağlantıdan gelen kişi hangi çalışma olduğunu
               arşivin kendisinden bulabilsin. */
            'eski_tamga' => array_values(array_filter(array_map('strval', (array)($y['eski_kod'] ?? [])))),
            'adres'      => tg_kok() . tg_yazi_yolu($y),
            'slug'       => (string)($y['slug'] ?? ''),
            /* Yol ve durum ayrı iki bilgidir; ikisi de kalıcı kayıtta durur.
               Bkz. dk_yazi_ustveri(): aynı ayrım orada da yapılır ki iki
               katman aynı çalışma için farklı şey söylemesin. */
            'tur'        => (string)($y['tur'] ?? ''),
            'asama'      => tg_hakem_asamasi($y),
            'rapor_sayisi' => tg_rapor_sayisi($y),
            'tarih'      => (string)($y['tarih'] ?? ''),
            'alan'       => (string)($y['alan'] ?? ''),
            'baslik'     => (string)($y['baslik'] ?? ''),
            'baslik_en'  => (string)($y['baslik_en'] ?? ''),
            'ozet'       => (string)($y['ozet'] ?? ''),
            'ozet_en'    => (string)($y['ozet_en'] ?? ''),
            'anahtar'    => tg_metin($y['anahtar'] ?? ''),
            'anahtar_en' => tg_metin($y['anahtar_en'] ?? ''),
            /* ÇEVİRİNİN KÜNYESİ DÖKÜME DE GİRER.
               İngilizce metin döküme giriyordu ama NASIL üretildiği
               girmiyordu; dökümü indiren biri yazarın yazdığı metinle
               onaysız bir makine çevirisini ayırt edemiyordu. Metin
               çıkarılmadı, künyesi eklendi: eksik bir kayıt yayımlamak,
               arşivin kendi sözüyle çelişirdi. Künyesi olmayan
               çalışmada alan hiç basılmaz. */
            'ceviri_en'  => (function ($y) {
                $c = tg_yazi_ceviri($y, 'en');
                if (!$c) return null;
                return ['kaynak' => $c['kaynak'], 'ceviren' => $c['ceviren'],
                        'onay' => $c['onay'], 'yayin' => tg_ceviri_yayin_mi($c)];
            })($y),
            'metin'      => (string)($y['metin'] ?? ''),
            'metin_en'   => (string)($y['metin_en'] ?? ''),
            'kaynakca'   => (string)($y['kaynakca'] ?? ''),
            'lisans'     => (string)tg_ayar('lisans', 'CC BY 4.0'),
            'parmak_izi' => tg_metin_ozeti($y),
            /* Çevirinin ayrı özeti. Taban değer DEĞİŞMEDİ; elinde eski
               listeyi tutan okur olmayan bir değişiklik görmesin diye
               genişletilmedi, yanına yazıldı. Çevirisi olmayan
               çalışmada alan hiç basılmaz. Bkz. ortak.php,
               tg_ceviri_ozeti(). */
            'ceviri_parmak_izi' => tg_ceviri_ozeti($y) ?: null,
        ];
        $out['yazarlar'] = dk_yazarlar($y);

        $hk = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) { if (is_array($h) && trim((string)($h['rapor'] ?? '')) !== '') $hk[] = dk_hakem($h); }
        if ($hk) $out['hakemler'] = $hk;

        $onay = tg_onay_durumu($y);
        $out['degerlendirme'] = ['onayli' => $onay['onayli'], 'olumlu' => $onay['kabul'],
                                 'esik' => $onay['esik'], 'ret' => $onay['ret']];

        $ky = tg_kayitlar($y);
        if ($ky) {
            $out['kayitlar'] = array_map(fn($k) => [
                'tur' => (string)($k['tur'] ?? ''), 'tarih' => (string)($k['tarih'] ?? ''),
                'metin' => (string)($k['metin'] ?? ''), 'karar_veren' => (string)($k['karar_veren'] ?? ''),
            ], $ky);
        }

        $ov = [];
        foreach (tg_oylamalar($y) as $o) {
            $s = tg_oylama_sonuc($o);
            $kayit = ['kod' => (string)($o['kod'] ?? ''), 'tur' => (string)($o['tur'] ?? ''),
                      'hedef' => (string)($o['hedef_ad'] ?? ''), 'acan' => (string)($o['acan_ad'] ?? ''),
                      'gerekce' => (string)($o['gerekce'] ?? ''), 'tarih' => (string)($o['tarih'] ?? ''),
                      'sonuclandi' => $s['kapali'], 'karar' => $s['kapali'] ? (string)$s['karar'] : ''];
            if ($s['kapali']) {
                $kayit['oylar'] = array_values(array_filter(array_map(function ($x) {
                    return is_array($x) ? ['ad' => (string)($x['ad'] ?? ''), 'karar' => (string)($x['karar'] ?? ''),
                                           'gerekce' => (string)($x['gerekce'] ?? ''), 'tarih' => (string)($x['tarih'] ?? '')] : null;
                }, (array)($o['oylar'] ?? []))));
            }
            $ov[] = $kayit;
        }
        if ($ov) $out['oylamalar'] = $ov;

        $sr = [];
        foreach (tg_serhler($y) as $s) {
            $k = ['ad' => (string)($s['ad'] ?? ''), 'kurum' => (string)($s['kurum'] ?? ''),
                  'ilgi' => (string)($s['ilgi'] ?? ''), 'tarih' => (string)($s['tarih'] ?? '')];
            if (is_array($s['perde'] ?? null)) {
                $k['perdelendi'] = true;
                $k['perde_neden'] = (string)($s['perde']['neden'] ?? '');
                $k['perde_kim']   = (string)($s['perde']['kim'] ?? '');
            } else {
                $k['metin'] = (string)($s['metin'] ?? '');
            }
            if (is_array($s['yanit'] ?? null)) {
                $k['yanit'] = ['ad' => (string)($s['yanit']['ad'] ?? ''), 'metin' => (string)($s['yanit']['metin'] ?? ''),
                               'tarih' => (string)($s['yanit']['tarih'] ?? '')];
            }
            $sr[] = $k;
        }
        if ($sr) $out['serhler'] = $sr;

        if (tg_kurulus_istisnasi($y)) $out['kurulus_istisnasi'] = true;
        return $out;
    }

    /* Her dökümün başında duran ortak künye. */
    function dk_bas(string $katman, string $kapsam, int $sayi): array {
        return [
            'arsiv'   => (string)tg_ayar('marka', 'Kutadgu'),
            'adres'   => tg_kok(),
            'lisans'  => ['ad' => (string)tg_ayar('lisans', 'CC BY 4.0'),
                          'url' => (string)tg_ayar('lisans_url', '')],
            'surum'   => DK_SURUM,
            'katman'  => $katman,
            'kapsam'  => $kapsam,
            'uretim'  => date('c'),
            'aciklama' => 'Kutadgu arşivinin dökümü. Kişisel veri içermez: e-posta adresleri, '
                        . 'erişim anahtarları ve hesap kayıtları bilerek dışarıda bırakılmıştır. '
                        . 'Bu dosyayı indiren herkes arşivi çoğaltabilir ve sürdürebilir; '
                        . 'kalıcılığın tek bir sunucuya bağlı kalmaması içindir. '
                        . 'İndirmek için hiçbir koşul yoktur: kayıt, üyelik, onay ya da bedel istenmez.',
            'aciklama_en' => 'A dump of the Kutadgu archive. It contains no personal data: e mail addresses, '
                        . 'access keys and account records are deliberately excluded. '
                        . 'Anyone who downloads this file may copy and continue the archive; '
                        . 'this is so that permanence does not rest on a single server. '
                        . 'There is no condition for downloading it: no registration, membership, approval or payment is asked.',
            'calisma_sayisi' => $sayi,
        ];
    }

    /* ---------------------------------------------------------------
       YAZMA
       Önce geçici dosyaya, sonra yerine taşınır. Yarım kalmış bir
       dosyanın indirilmesi böylece imkânsızdır: rename tek adımdır.
       --------------------------------------------------------------- */
    function dk_dosya_yaz(string $ad, string $icerik): array {
        $y = dk_yol($ad); $t = $y . '.tmp';
        if (@file_put_contents($t, $icerik, LOCK_EX) === false) return [];
        if (!@rename($t, $y)) { @unlink($t); return []; }
        @chmod($y, 0644);
        return ['ad' => $ad, 'boyut' => strlen($icerik), 'ozet' => hash('sha256', $icerik),
                'uretim' => date('c')];
    }

    function dk_json(array $v): string {
        $s = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return $s === false ? '{"hata":"kodlanamadi"}' : $s;
    }

    /* ---------------------------------------------------------------
       BELİRTE (manifest)
       Hangi dosya var, ne kadar, hangi özetle, ne zaman üretildi.
       Sayfa da, betikler de, aynalar da buradan okur.
       --------------------------------------------------------------- */
    function dk_belirte(): array {
        $p = dk_yol('belirte.json');
        if (!is_file($p)) return [];
        $j = json_decode((string)@file_get_contents($p), true);
        return is_array($j) ? $j : [];
    }

    function dk_belirte_yaz(array $b): void {
        $p = dk_yol('belirte.json'); $t = $p . '.tmp';
        if (@file_put_contents($t, dk_json($b), LOCK_EX) === false) return;
        @rename($t, $p);
        /* Standart araçlarla denetlenebilsin diye ayrıca düz bir özet
           listesi: sha256sum -c ile doğrudan çalışır. */
        $satir = '';
        foreach (($b['dosyalar'] ?? []) as $d) {
            $satir .= (string)($d['ozet'] ?? '') . '  ' . (string)($d['ad'] ?? '') . "\n";
        }
        @file_put_contents(dk_yol('SHA256SUMS.txt'), $satir, LOCK_EX);
    }

    function dk_belirte_dosya(string $ad): array {
        foreach ((dk_belirte()['dosyalar'] ?? []) as $d) {
            if ((string)($d['ad'] ?? '') === $ad) return $d;
        }
        return [];
    }

    /* ---------------------------------------------------------------
       KİRLİ İZ
       Arşiv değiştiğinde bir iz bırakılır. Üretim bu izi görerek
       çalışır; iz yoksa gecelik görev boşuna dosya yazmaz.
       --------------------------------------------------------------- */
    function dk_kirlet(string $neden = ''): void {
        @file_put_contents(dk_yol('kirli.json'),
            dk_json(['t' => time(), 'tarih' => date('c'), 'neden' => $neden]), LOCK_EX);
    }
    function dk_kirli(): array {
        $p = dk_yol('kirli.json');
        if (!is_file($p)) return [];
        $j = json_decode((string)@file_get_contents($p), true);
        return is_array($j) ? $j : [];
    }

    /* ---------------------------------------------------------------
       YAYIM ANI TETİKLEYİCİSİ
       Arşiv her değiştiğinde çağrılır. İki şey yapar ve ikisi de
       isteği bekletmez:
         1. Kirli izi bırakır.
         2. Üretimi, yanıt kullanıcıya gönderildikten SONRA çalışacak
            biçimde sıraya koyar (register_shutdown_function). PHP-FPM
            varsa yanıt fastcgi_finish_request ile önce kapatılır.
       Üretim kilitlidir ve kilit beklemez: aynı anda iki üretim
       başlamaz, ikincisi sessizce vazgeçer. Vazgeçilen iş kaybolmaz,
       çünkü kirli iz yerinde durur ve bir sonraki tetik ya da gecelik
       görev onu görür.
       --------------------------------------------------------------- */
    function dk_tetikle(string $neden = ''): void {
        static $kayitli = false;
        dk_kirlet($neden);
        if ($kayitli) return;
        $kayitli = true;
        if (PHP_SAPI === 'cli') return;          /* CLI'da görev betiği zaten üretir */
        register_shutdown_function(function (): void {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            @ignore_user_abort(true);
            @set_time_limit(120);
            dk_uret_hafif();
        });
    }

    /* Kilit: aynı anda tek üretim. Beklemez. */
    function dk_kilit_al(string $ad) {
        $f = @fopen(dk_yol($ad), 'c');
        if (!$f) return null;
        if (!@flock($f, LOCK_EX | LOCK_NB)) { @fclose($f); return null; }
        return $f;
    }
    function dk_kilit_birak($f): void {
        if (!$f) return;
        @flock($f, LOCK_UN); @fclose($f);
    }

    /* ---------------------------------------------------------------
       KATMAN A VE B ÜRETİMİ
       Ucuz olan iki katman birlikte üretilir: ikisi de aynı kayıtları
       okur, iki kez okumanın anlamı yoktur.
       --------------------------------------------------------------- */
    function dk_uret_hafif(bool $zorla = false): array {
        $bas = microtime(true);
        $kilit = dk_kilit_al('uretim.kilit');
        if (!$kilit) return ['ok' => false, 'sebep' => 'kilit-mesgul'];

        $iz = dk_kirli();
        $b  = dk_belirte();
        /* Biçim sürümü değiştiyse döküm eskimiş sayılır: veride değişiklik
           olmasa da yeniden üretilir. Yoksa yeni bir alan eklendiği hâlde
           arşivde hiçbir çalışma değişmediği için döküm aylarca eski biçimde
           kalabilir; indiren taraf belirtede yazan sürümle dosyanın içeriği
           arasında uyuşmazlık görürdü. */
        $surumEski = (int)($b['surum'] ?? 0) !== DK_SURUM;
        if (!$zorla && !$iz && !$surumEski && ($b['dosyalar'] ?? []) !== []) {
            dk_kilit_birak($kilit);
            return ['ok' => true, 'atlandi' => true, 'sebep' => 'degisiklik-yok'];
        }
        /* Üretime başladığımız an. Bu andan sonra gelen bir değişiklik
           izini silmeyiz; yoksa üretimin okumadığı bir değişiklik
           sessizce kaybolurdu. */
        $baslangic = time();

        $yazilar = k_yazilar();
        $ustveri = []; $tam = []; $yillar = [];
        foreach ($yazilar as $y) {
            if (!is_array($y)) continue;
            $ustveri[] = dk_yazi_ustveri($y);
            $kayit = dk_yazi($y);
            $yil = dk_yil($y);
            $kayit['yil'] = $yil;
            $tam[] = $kayit;
            $yillar[$yil][] = $kayit;
        }
        $sirala = fn(&$a) => usort($a, fn($x, $z) => strcmp((string)$x['tamga'], (string)$z['tamga']));
        $sirala($ustveri); $sirala($tam);
        krsort($yillar);

        $dosyalar = [];

        /* --- (a) üstveri --- */
        $gov = dk_bas('a', 'ustveri', count($ustveri));
        $gov['icerik'] = ['tam_metin' => false, 'hakem_raporu_govdesi' => false,
                          'oy_gerekcesi' => false, 'serh_metni' => false];
        $gov['calismalar'] = $ustveri;
        $d = dk_dosya_yaz('kutadgu-ustveri.json', dk_json($gov));
        if ($d) { $d['katman'] = 'a'; $d['tur'] = 'application/json'; $dosyalar[] = $d; }

        /* --- (a) parmak izleri: en küçük dosya, denetim için yeterli --- */
        $mar = (string)tg_ayar('marka', 'Kutadgu');
        $satirlar = "# " . $mar . " " . tg_kok() . "\n"
                  . "# Her satir: <parmak izi>  <tamga>  <baslik>\n"
                  . "# Bu degerler metnin ozetidir. Metin degisirse deger de degisir.\n"
                  . "# Uretim: " . date('c') . "\n"
                  . "# Dosyalarin kendi SHA-256 ozetleri: " . tg_kok() . "/dokum.php?durum=1\n"
                  . "# Indirmenin hicbir kosulu yoktur: kayit, uyelik, onay ya da bedel istenmez.\n\n";
        foreach ($ustveri as $u) {
            $satirlar .= $u['parmak_izi'] . "  " . str_pad((string)$u['tamga'], 14) . "  "
                       . preg_replace('/\s+/', ' ', (string)$u['baslik']) . "\n";
        }
        $d = dk_dosya_yaz('kutadgu-parmak-izleri.txt', $satirlar);
        if ($d) { $d['katman'] = 'a'; $d['tur'] = 'text/plain; charset=UTF-8'; $dosyalar[] = $d; }

        /* --- (b) tam metin, yıllara bölünmüş --- */
        foreach ($yillar as $yil => $kayitlar) {
            $g = dk_bas('b', 'tam-metin-' . $yil, count($kayitlar));
            $g['yil'] = (string)$yil;
            $g['calismalar'] = $kayitlar;
            $ad = 'kutadgu-tam-' . preg_replace('/[^a-z0-9]/', '', strtolower((string)$yil)) . '.json';
            $d = dk_dosya_yaz($ad, dk_json($g));
            if ($d) { $d['katman'] = 'b'; $d['tur'] = 'application/json'; $d['yil'] = (string)$yil; $dosyalar[] = $d; }
        }

        /* --- (b) tam metin, tek dosya ---
           Yıllara bölünmüş parçalar asıl yoldur; bu dosya, bugüne kadar
           /arsiv.php adresini kullanan betikler ve aynalar kırılmasın
           diye durur. Aynı içerik, tek parça. */
        $g = dk_bas('b', 'tam-metin-tumu', count($tam));
        $g['calismalar'] = $tam;
        $d = dk_dosya_yaz('kutadgu-tam-tumu.json', dk_json($g));
        if ($d) { $d['katman'] = 'b'; $d['tur'] = 'application/json'; $dosyalar[] = $d; }

        /* Paket (katman c) burada üretilmez; belirtede yerini korur. */
        $eskiPaket = [];
        foreach (($b['dosyalar'] ?? []) as $x) if (($x['katman'] ?? '') === 'c') $eskiPaket[] = $x;
        foreach ($eskiPaket as $x) { if (is_file(dk_yol((string)($x['ad'] ?? '')))) $dosyalar[] = $x; }

        $sure = (int)round((microtime(true) - $bas) * 1000);
        $yeni = [
            'surum'   => DK_SURUM,
            'uretim'  => date('c'),
            'sure_ms' => $sure,
            'calisma_sayisi' => count($tam),
            'yillar'  => array_map('strval', array_keys($yillar)),
            'dosyalar' => $dosyalar,
            'paket'   => $b['paket'] ?? [],
            'kural'   => [
                'tr' => 'Bu dosyaların indirilmesi hiçbir koşula bağlı değildir. Kayıt, üyelik, onay ve bedel istenmez. Sıra ve hız sınırı erişimi geciktirebilir, reddedemez.',
                'en' => 'Downloading these files is subject to no condition. No registration, membership, approval or payment is asked. A queue or a rate limit may delay access; it may not refuse it.',
            ],
        ];
        dk_belirte_yaz($yeni);

        /* Üretim sırasında yeni bir değişiklik geldiyse iz kalır. */
        $izSon = dk_kirli();
        if (!$izSon || (int)($izSon['t'] ?? 0) <= $baslangic) @unlink(dk_yol('kirli.json'));

        dk_kilit_birak($kilit);
        return ['ok' => true, 'sure_ms' => $sure, 'dosya' => count($dosyalar), 'calisma' => count($tam)];
    }

    /* ---------------------------------------------------------------
       KATMAN C: GÖRSEL VE BELGE PAKETİ
       En ağır olan budur ve istek anında üretilmesi hiçbir koşulda
       kabul edilemez. Kuyruğa alınır, günde en çok bir kez üretilir.
       İçinde ne var: çalışmaların gövdesinde geçen görseller ve hakem
       raporlarına eklenmiş belgeler. İkisi de zaten herkese açık
       adreslerde durur; paket yalnızca hepsini tek dosyada toplar.
       --------------------------------------------------------------- */
    function dk_paket_arasi(): int {
        $s = (int)tg_ayar('dokum_paket_arasi', 86400);
        return $s > 0 ? $s : 86400;
    }

    /* Bir çalışmanın gövdesinde geçen görsellerin dosya yolları. */
    function dk_gorsel_yollari(array $y): array {
        $metin = (string)($y['metin'] ?? '') . "\n" . (string)($y['metin_en'] ?? '');
        $out = [];
        if (preg_match_all('#/photo/yazi/([A-Za-z0-9]{4,40}\.(?:jpg|jpeg|png|webp|gif))#', $metin, $m)) {
            foreach ($m[1] as $ad) $out[$ad] = '/photo/yazi/' . $ad;
        }
        return $out;
    }

    /* Hakem raporlarına eklenmiş belgeler. */
    function dk_belge_yollari(array $y): array {
        $out = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h)) continue;
            $liste = [];
            if (trim((string)($h['dosya'] ?? '')) !== '') $liste[] = (string)$h['dosya'];
            foreach (($h['raporlar'] ?? []) as $r) {
                if (is_array($r) && trim((string)($r['dosya'] ?? '')) !== '') $liste[] = (string)$r['dosya'];
            }
            foreach ($liste as $d) {
                if (preg_match('#^/dosya/hakem/([A-Za-z0-9]{4,40}\.(?:pdf|doc|docx|odt))$#', $d, $m)) {
                    $out[$m[1]] = $d;
                }
            }
        }
        return $out;
    }

    function dk_uret_paket(bool $zorla = false): array {
        $bas = microtime(true);
        if (!class_exists('ZipArchive')) return ['ok' => false, 'sebep' => 'zip-yok'];
        $kilit = dk_kilit_al('paket.kilit');
        if (!$kilit) return ['ok' => false, 'sebep' => 'kilit-mesgul'];

        $b = dk_belirte();
        $paket = is_array($b['paket'] ?? null) ? $b['paket'] : [];
        $son = (int)($paket['t'] ?? 0);
        if (!$zorla && $son > 0 && (time() - $son) < dk_paket_arasi() && is_file(dk_yol((string)($paket['ad'] ?? '')))) {
            dk_kilit_birak($kilit);
            return ['ok' => true, 'atlandi' => true, 'sebep' => 'gunluk-sinir', 'paket' => $paket];
        }

        $kok = dirname(__DIR__);          /* web kökü: /photo ve /dosya buradadır */
        $yazilar = k_yazilar();
        $ad  = 'kutadgu-paket.zip';
        $gec = dk_yol($ad . '.tmp');
        @unlink($gec);

        $z = new ZipArchive();
        if ($z->open($gec, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            dk_kilit_birak($kilit);
            return ['ok' => false, 'sebep' => 'zip-acilamadi'];
        }

        $sayi = ['gorsel' => 0, 'belge' => 0, 'atlanan' => 0];
        $liste = [];
        foreach ($yazilar as $y) {
            if (!is_array($y)) continue;
            $tamga = (string)($y['bcid'] ?? ($y['id'] ?? 'bilinmeyen'));
            $klasor = preg_replace('/[^A-Za-z0-9._\-]/', '_', $tamga);
            foreach (dk_gorsel_yollari($y) as $dosyaAd => $yol) {
                $kaynak = $kok . $yol;
                if (!is_file($kaynak)) { $sayi['atlanan']++; continue; }
                $z->addFile($kaynak, $klasor . '/gorsel/' . $dosyaAd);
                $sayi['gorsel']++;
                $liste[] = ['tamga' => $tamga, 'tur' => 'gorsel', 'ad' => $dosyaAd,
                            'boyut' => (int)filesize($kaynak), 'ozet' => hash_file('sha256', $kaynak)];
            }
            foreach (dk_belge_yollari($y) as $dosyaAd => $yol) {
                $kaynak = $kok . $yol;
                if (!is_file($kaynak)) { $sayi['atlanan']++; continue; }
                $z->addFile($kaynak, $klasor . '/belge/' . $dosyaAd);
                $sayi['belge']++;
                $liste[] = ['tamga' => $tamga, 'tur' => 'belge', 'ad' => $dosyaAd,
                            'boyut' => (int)filesize($kaynak), 'ozet' => hash_file('sha256', $kaynak)];
            }
        }

        /* Paketin içine üstveri de konur: paketi alan kişi hangi
           görselin hangi çalışmaya ait olduğunu dışarıya bakmadan
           bilsin. */
        $ust = dk_yol('kutadgu-ustveri.json');
        if (is_file($ust)) $z->addFile($ust, 'kutadgu-ustveri.json');
        $z->addFromString('BELIRTE.json', dk_json([
            'arsiv' => (string)tg_ayar('marka', 'Kutadgu'), 'adres' => tg_kok(),
            'katman' => 'c', 'surum' => DK_SURUM, 'uretim' => date('c'),
            'gorsel_sayisi' => $sayi['gorsel'], 'belge_sayisi' => $sayi['belge'],
            'dosyalar' => $liste,
        ]));
        $z->addFromString('OKUBENI.txt',
            "KUTADGU - gorsel ve belge paketi\n"
          . "Uretim: " . date('c') . "\n\n"
          . "Bu paket, calismalarin govdesinde gecen gorselleri ve hakem raporlarina\n"
          . "eklenmis belgeleri tasir. Her klasorun adi calismanin Tamga kimligidir.\n"
          . "Ustveri icin kutadgu-ustveri.json, dosya ozetleri icin BELIRTE.json.\n\n"
          . "Bu paketi indirmek hicbir kosula bagli degildir: kayit, uyelik, onay ya da\n"
          . "bedel istenmez. Paket gunde bir kez uretilir; sira beklemek bir gecikmedir,\n"
          . "bir reddetme degildir.\n\n"
          . "This package carries the images that appear in the works and the files\n"
          . "attached to referee reports. Each folder is named after the work's Tamga\n"
          . "identifier. Downloading it is subject to no condition.\n");
        $z->close();

        if (!is_file($gec)) {
            dk_kilit_birak($kilit);
            return ['ok' => false, 'sebep' => 'zip-yazilamadi'];
        }
        $hedef = dk_yol($ad);
        @rename($gec, $hedef);
        @chmod($hedef, 0644);

        $sure = (int)round((microtime(true) - $bas) * 1000);
        $bilgi = ['ad' => $ad, 'katman' => 'c', 'tur' => 'application/zip',
                  'boyut' => (int)filesize($hedef), 'ozet' => hash_file('sha256', $hedef),
                  'uretim' => date('c'), 't' => time(), 'sure_ms' => $sure,
                  'gorsel_sayisi' => $sayi['gorsel'], 'belge_sayisi' => $sayi['belge']];

        /* Belirteyi güncelle: paket satırı bir kez bulunur, bir kez yazılır. */
        $b = dk_belirte();
        $dosyalar = [];
        foreach (($b['dosyalar'] ?? []) as $x) if (($x['katman'] ?? '') !== 'c') $dosyalar[] = $x;
        $dosyalar[] = $bilgi;
        $b['dosyalar'] = $dosyalar;
        $b['paket'] = $bilgi;
        dk_belirte_yaz($b);

        /* Kuyruktaki herkesin fişi artık hazır sayılır. */
        dk_kuyruk_kapat($bilgi);

        dk_kilit_birak($kilit);
        return ['ok' => true] + $bilgi + ['atlanan' => $sayi['atlanan']];
    }

    /* ---------------------------------------------------------------
       KUYRUK
       En ağır paketi isteyen kişi sıraya girer. Sıraya girmek için
       kimlik gerekmez: karşılığında rastgele bir sıra fişi verilir ve
       fiş yalnızca "hazır mı" sorusunu sormaya yarar. Fişi olmayan da
       hazır paketi indirir; fiş bir kapı değil, bir kolaylıktır.
       --------------------------------------------------------------- */
    function dk_kuyruk(): array {
        $p = dk_yol('kuyruk.json');
        if (!is_file($p)) return ['istek' => [], 'son' => 0];
        $j = json_decode((string)@file_get_contents($p), true);
        return is_array($j) ? $j + ['istek' => [], 'son' => 0] : ['istek' => [], 'son' => 0];
    }
    function dk_kuyruk_yaz(array $k): void {
        $p = dk_yol('kuyruk.json'); $t = $p . '.tmp';
        if (@file_put_contents($t, dk_json($k), LOCK_EX) === false) return;
        @rename($t, $p);
    }

    /* Sıraya gir. Aynı ziyaretçi ikinci kez isterse YENİ bir iş
       açılmaz: elindeki fiş neyse o döner. Kuyruk bir iş listesi
       değildir, tek bir işin bekleyenler listesidir. */
    function dk_sira_al(string $iz, string $fis = ''): array {
        $k = dk_kuyruk();
        $simdi = time();
        /* Yedi günden eski fişler düşer. */
        $k['istek'] = array_values(array_filter((array)$k['istek'],
            fn($x) => is_array($x) && ($simdi - (int)($x['t'] ?? 0)) < 7 * 86400));

        /* Hazır ve güncel bir paket varsa sıraya gerek yoktur. */
        $b = dk_belirte();
        $paket = is_array($b['paket'] ?? null) ? $b['paket'] : [];
        $hazir = $paket && is_file(dk_yol((string)($paket['ad'] ?? '')));
        $guncel = $hazir && (time() - (int)($paket['t'] ?? 0)) < dk_paket_arasi();
        if ($guncel) {
            dk_kuyruk_yaz($k);
            return ['durum' => 'hazir', 'sira' => 0, 'fis' => $fis, 'paket' => $paket,
                    'bekleyen' => count(array_filter($k['istek'], fn($x) => empty($x['kapandi'])))];
        }

        /* Elinde fiş varsa ve hâlâ bekliyorsa aynı fiş döner. */
        foreach ($k['istek'] as $x) {
            if ($fis !== '' && (string)($x['fis'] ?? '') === $fis && empty($x['kapandi'])) {
                return dk_fis_durum($fis);
            }
        }
        /* Aynı ziyaretçi izinden bekleyen bir istek varsa onu döndür. */
        foreach ($k['istek'] as $x) {
            if ((string)($x['iz'] ?? '') === $iz && empty($x['kapandi'])) {
                dk_kuyruk_yaz($k);
                return dk_fis_durum((string)$x['fis']);
            }
        }
        if (count($k['istek']) > 5000) $k['istek'] = array_slice($k['istek'], -2000);
        $yeni = bin2hex(random_bytes(8));
        $k['istek'][] = ['fis' => $yeni, 'iz' => $iz, 't' => $simdi, 'tarih' => date('c')];
        dk_kuyruk_yaz($k);
        return dk_fis_durum($yeni);
    }

    function dk_fis_durum(string $fis): array {
        $k = dk_kuyruk();
        $bekleyen = 0; $sira = 0; $kayit = null;
        foreach ($k['istek'] as $x) {
            if (!is_array($x)) continue;
            if (empty($x['kapandi'])) { $bekleyen++; if ((string)($x['fis'] ?? '') === $fis) $sira = $bekleyen; }
            if ((string)($x['fis'] ?? '') === $fis) $kayit = $x;
        }
        if ($kayit === null) return ['durum' => 'yok', 'fis' => $fis, 'sira' => 0, 'bekleyen' => $bekleyen];
        if (!empty($kayit['kapandi'])) {
            $b = dk_belirte();
            return ['durum' => 'hazir', 'fis' => $fis, 'sira' => 0, 'bekleyen' => $bekleyen,
                    'paket' => is_array($b['paket'] ?? null) ? $b['paket'] : []];
        }
        return ['durum' => 'sirada', 'fis' => $fis, 'sira' => $sira, 'bekleyen' => $bekleyen,
                'tahmin' => dk_sonraki_uretim()];
    }

    /* Bir sonraki paket üretiminin en erken zamanı. */
    function dk_sonraki_uretim(): string {
        $b = dk_belirte();
        $son = (int)(($b['paket']['t'] ?? 0));
        if ($son <= 0) return date('c');
        return date('c', $son + dk_paket_arasi());
    }

    function dk_kuyruk_kapat(array $paket): void {
        $k = dk_kuyruk();
        foreach ($k['istek'] as $i => $x) {
            if (is_array($x) && empty($x['kapandi'])) {
                $k['istek'][$i]['kapandi'] = date('c');
                $k['istek'][$i]['dosya'] = (string)($paket['ad'] ?? '');
            }
        }
        $k['son'] = time();
        dk_kuyruk_yaz($k);
    }

    function dk_kuyruk_bekleyen(): int {
        $s = 0;
        foreach (dk_kuyruk()['istek'] as $x) if (is_array($x) && empty($x['kapandi'])) $s++;
        return $s;
    }

    /* Kuyruğu işle: bekleyen varsa ve günlük sınır dolduysa üret. */
    function dk_kuyruk_isle(bool $zorla = false): array {
        if (dk_kuyruk_bekleyen() < 1 && !$zorla) return ['ok' => true, 'atlandi' => true, 'sebep' => 'bekleyen-yok'];
        return dk_uret_paket($zorla);
    }

    /* ---------------------------------------------------------------
       YOL BAZINDA HIZ SINIRI
       Aynı adresten gelen isteklerin sıklığına bir tavan konur. Tavan
       aşıldığında istek REDDEDİLMEZ; "biraz sonra" denir ve ne kadar
       sonra olduğu Retry-After başlığında yazılıdır. Bu bir gecikmedir.
       Erişimin kendisi hiçbir koşulda kapanmaz.
       --------------------------------------------------------------- */
    function dk_iz(): string {
        $ip = (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        return substr(hash('sha256', $ip . '|' . date('Y-m-d')), 0, 16);
    }

    function dk_hiz_sinir(string $anahtar, int $adet, int $pencere): array {
        $p = dk_yol('hiz.json');
        $j = is_file($p) ? json_decode((string)@file_get_contents($p), true) : [];
        if (!is_array($j)) $j = [];
        $simdi = time();
        foreach ($j as $k => $v) {
            if (!is_array($v) || ($simdi - (int)($v['t'] ?? 0)) > 7200) unset($j[$k]);
        }
        $k = dk_iz() . '|' . $anahtar;
        $kayit = is_array($j[$k] ?? null) ? $j[$k] : ['c' => 0, 't' => $simdi];
        if (($simdi - (int)$kayit['t']) > $pencere) $kayit = ['c' => 0, 't' => $simdi];
        $kayit['c'] = (int)$kayit['c'] + 1;
        $j[$k] = $kayit;
        if (count($j) > 5000) $j = array_slice($j, -2500, null, true);
        @file_put_contents($p, dk_json($j), LOCK_EX);
        if ($kayit['c'] <= $adet) return ['gecer' => true, 'bekle' => 0];
        $bekle = max(1, $pencere - ($simdi - (int)$kayit['t']));
        return ['gecer' => false, 'bekle' => $bekle];
    }

    /* ---------------------------------------------------------------
       DURAĞAN DOSYA SUNUMU
       ETag, Last-Modified, Range ve önbellek başlıkları burada.
       Sunucunun yaptığı iş: dosyayı olduğu gibi akıtmak. Hesap yok,
       kodlama yok, bellekte kurulan bir gövde yok.
       --------------------------------------------------------------- */
    function dk_gonder(string $ad, bool $ekOlarak = true): void {
        $yol = dk_yol($ad);
        $bilgi = dk_belirte_dosya($ad);
        $boy = (int)@filesize($yol);
        $tur = (string)($bilgi['tur'] ?? 'application/octet-stream');
        $mt  = (int)@filemtime($yol);
        $etag = '"' . substr((string)($bilgi['ozet'] ?? hash('sha256', $ad . $mt . $boy)), 0, 32) . '"';

        header('Content-Type: ' . $tur);
        header('Accept-Ranges: bytes');
        header('ETag: ' . $etag);
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mt) . ' GMT');
        header('Cache-Control: public, max-age=3600, stale-while-revalidate=86400');
        header('Access-Control-Allow-Origin: *');
        header('X-Content-Type-Options: nosniff');
        if (isset($bilgi['ozet'])) header('X-Ozet-Sha256: ' . (string)$bilgi['ozet']);
        if (isset($bilgi['uretim'])) header('X-Uretim: ' . (string)$bilgi['uretim']);
        header('X-Kosul: yok');   /* indirme hiçbir koşula bağlı değildir */
        if ($ekOlarak) header('Content-Disposition: attachment; filename="' . $ad . '"');

        /* Değişmediyse gövde gönderilmez: en ucuz indirme, yapılmayan
           indirmedir. */
        $inm = trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''));
        $ims = trim((string)($_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? ''));
        if (($inm !== '' && (strpos($inm, $etag) !== false || $inm === '*'))
            || ($inm === '' && $ims !== '' && @strtotime($ims) >= $mt)) {
            http_response_code(304);
            return;
        }

        $bas = 0; $son = $boy - 1; $kismi = false;
        $ar = trim((string)($_SERVER['HTTP_RANGE'] ?? ''));
        if ($ar !== '' && preg_match('/^bytes=(\d*)-(\d*)$/', $ar, $m)) {
            $ib = $m[1]; $is = $m[2];
            if ($ib === '' && $is === '') { /* geçersiz, tamamı gönderilir */ }
            elseif ($ib === '') { $uzunluk = (int)$is; $bas = max(0, $boy - $uzunluk); $son = $boy - 1; $kismi = true; }
            else {
                $bas = (int)$ib;
                $son = $is === '' ? $boy - 1 : min((int)$is, $boy - 1);
                $kismi = true;
            }
            if ($bas > $son || $bas >= $boy) {
                http_response_code(416);
                header('Content-Range: bytes */' . $boy);
                return;
            }
        }

        $uzun = $son - $bas + 1;
        if ($kismi) {
            http_response_code(206);
            header('Content-Range: bytes ' . $bas . '-' . $son . '/' . $boy);
        }
        header('Content-Length: ' . $uzun);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') return;

        $f = @fopen($yol, 'rb');
        if (!$f) { http_response_code(500); return; }
        if ($bas > 0) fseek($f, $bas);
        $kalan = $uzun;
        while ($kalan > 0 && !feof($f)) {
            $parca = fread($f, (int)min(524288, $kalan));
            if ($parca === false || $parca === '') break;
            echo $parca;
            $kalan -= strlen($parca);
            if (connection_aborted()) break;
        }
        fclose($f);
    }

    /* Sayfanın ve betiklerin ortak kullandığı okunur boyut. */
    function dk_boy(int $b): string {
        if ($b <= 0) return '0';
        $birim = ['B', 'KB', 'MB', 'GB'];
        $i = (int)floor(log($b, 1024));
        $i = max(0, min($i, count($birim) - 1));
        $d = $b / (1024 ** $i);
        return ($i === 0 ? (string)(int)$d : number_format($d, $d < 10 ? 1 : 0)) . ' ' . $birim[$i];
    }
}
