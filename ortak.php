<?php
/* =====================================================================
   TAMGA · Ortak yardımcılar
   Yapılandırmayı yükler ve alan adından bağımsız bağlantılar üretir.
   Hem webroot sayfaları hem api/index.php bunu kullanır.
   ===================================================================== */

/* ---------------------------------------------------------------------
   VARLIK SÜRÜMÜ
   Stil ve betik dosyaları bir yıl boyunca "immutable" olarak önbelleğe
   alınır; bu, sayfaların hızlı açılması içindir. Bunun bedeli şudur:
   dosyanın içeriği değiştiğinde adresteki numara da DEĞİŞMEK ZORUNDADIR,
   yoksa ziyaretçiye yeni sayfa eski stille gider ve düzen dağılır.

   NUMARA ARTIK ELLE YAZILMIYOR; DOSYANIN KENDİSİNDEN OKUNUYOR.

   Neden değişti. Numara elle tutulduğu sürece iki ayrı yoldan
   bozuluyordu ve ikisi de 11-12 Ağustos gecesi gerçekten oldu:

   1. UNUTULUYOR. CSS değişiyor, satır güncellenmiyor, ziyaretçi eski
      stili bir yıl boyunca görüyor.
   2. DAHA KÖTÜSÜ: ARADAKİ ANDA ÖNBELLEĞE GİRİYOR. PHP dosyaları
      sunucuya CSS'ten önce ulaştı; o aralıkta gelen ilk istek
      `kutadgu.css?v=...f` adresini ESKİ içerikle çekti ve Cloudflare
      o adresi bir yıllığına önbelleğe aldı. Sonra doğru CSS sunucuya
      ulaştı ama kimse göremedi: adres aynı, önbellekteki cevap eski.
      Ölçüldü: `?v=...f` -> cf-cache-status HIT, 71.500 bayt, eski
      içerik; aynı dosya rastgele bir sorgu ekiyle -> MISS, 75.514
      bayt, yeni içerik. Numarayı tekrar artırmak bunu bir kereliğine
      çözer ama sebebi ortadan kaldırmaz; her gönderimde yeniden
      olabilir.

   Çözüm: numara dosyanın DEĞİŞİKLİK ZAMANINDAN üretilir. Böylece
   adres, dosyanın içeriği değiştiği anda ve yalnızca o zaman değişir.
   Zehirlenmiş bir önbellek kaydı artık imkânsızdır: yeni içeriğin
   adresi her zaman yenidir. Elle güncellenecek bir satır kalmadığı
   için unutulacak bir şey de kalmaz.

   Maliyeti bir filemtime() çağrısıdır (dosya sisteminden tek bir stat);
   ölçüldü, sayfa üretimine kattığı süre 0,1 milisaniyenin altında.
   Dosya okunamazsa TG_SURUM_YEDEK kullanılır: sürüm kaybolmaz, yalnız
   eskisi gibi elle tutulmuş olur.
   --------------------------------------------------------------------- */
if (!defined('TG_SURUM_YEDEK')) define('TG_SURUM_YEDEK', '20260812a');
if (!defined('TG_SURUM')) {
    $tgKok = __DIR__;
    $tgEnYeni = 0;
    foreach (['/k/kutadgu.css', '/k/kutadgu.js'] as $tgD) {
        $tgZ = @filemtime($tgKok . $tgD);
        if ($tgZ && $tgZ > $tgEnYeni) $tgEnYeni = $tgZ;
    }
    define('TG_SURUM', $tgEnYeni ? date('Ymd', $tgEnYeni) . '-' . substr((string)$tgEnYeni, -5) : TG_SURUM_YEDEK);
    unset($tgKok, $tgEnYeni, $tgD, $tgZ);
}

if (!function_exists('tg_ayar')) {

    /* =================================================================
       İKİ DİLLİK SEÇİM ORTAK.PHP'DE DE KALKTI

       Bu dosyanın işlevleri sayfaya metin döndürür ve eskiden bunu
       `$en ? 'English' : 'Türkçe'` diye yaparlardı. O deyim yalnız iki
       dil tanır: üçüncü bir dilde sessizce TÜRKÇEYE düşer ve çeviri
       katmanına hiç uğramaz. Ölçüldü — sözlüğü boş bir dilde sayfanın
       593 dizesi Türkçe geliyordu, oysa üstteki uyarı "çevirisi olmayan
       metinler İngilizce görünür" diyor.

       Neden doğrudan k_c() değil: k_c k/kabuk.php'de tanımlıdır ve
       kabuk BU dosyayı kendinden önce yükler. ortak.php tek başına da
       yüklenebiliyor (sınama betikleri böyle yapıyor); orada k_c
       yoktur. tg_c önce k_c'yi arar, bulamazsa eski davranışa döner:
       hiçbir yerde çökmez, kabuk yüklüyse çeviri katmanını kullanır.

       $en parametresi KORUNUR: işlevlerin imzası değişmedi, çağıran
       kod da değişmedi. O bayrak yalnızca k_c'nin bulunmadığı hâlde
       kullanılır.
       ================================================================= */
    /* Ay adları ve tarih dizimi TEK YERDE.
       Dört ayrı yerde iki dilli ay dizisi vardı ve dördü de üçüncü
       dilde Türkçeye düşüyordu ("15 Eylül 2026"). Ay adı artık çeviri
       katmanından geçer: sözlüğe "September" karşılığı yazıldığında
       tarih de o dile döner. Dizim (gün-ay-yıl / ay gün, yıl) SAYFANIN
       DİLİNE bakar, çağıranın iki dillik bayrağına değil: üçüncü bir
       dilde İngilizce dizim kullanılır, çünkü yedek dil odur. */
    function tg_ay_adi(int $ay, ?bool $enBayrak = null): string {
        $tr = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran',
               'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        $en = ['', 'January', 'February', 'March', 'April', 'May', 'June',
               'July', 'August', 'September', 'October', 'November', 'December'];
        if ($ay < 1 || $ay > 12) return '';
        return tg_c($tr[$ay], $en[$ay], $enBayrak);
    }

    /* =================================================================
       TARİHİN DİLİ — DİZİM VE AYIN TARİHTEKİ HÂLİ
       -----------------------------------------------------------------
       Arapça açılırken görüldü: sayfa "سبتمبر 15, 2026" yazıyordu.
       Yani ay adı Arapçaydı ama SIRASI İngilizceydi. Sebebi tek
       satırdı: dizim iki seçenekten ibaretti, "gün ay yıl" (Türkçe)
       ve "ay gün, yıl" (İngilizce), üçüncü diller de İngilizceye
       düşürülüyordu. Oysa Almanca, Fransızca, İspanyolca, Rusça ve
       Arapça günü öne alır; Çince ise yılı öne alır ve ayı sayıyla
       yazar. Kusur Arapçayla ortaya çıktı ama beş dilde de vardı.

       Dizim artık dilin kendi özelliğidir ve ayar.php'de yazılıdır:
         %g gün · %a ay adı · %A ay sayısı · %y yıl

       AYIN TARİHTEKİ HÂLİ AYRI BİR ŞEYDİR. Rusçada ay adı tek başına
       yalın hâldedir (Январь) ama tarihin içinde tamlanan hâle girer
       (января). Bir sözlük anahtarı iki hâli birden veremez; bu yüzden
       gereken dillerde ayar dosyasında 'ay_tarih' listesi durur.
       Listesi olmayan dil, sözlükten gelen adı kullanır. */
    function tg_tarih_dil(?bool $enBayrak = null): string {
        $dil = function_exists('k_dil') ? k_dil() : '';
        /* Üçüncü dilde katman kazanır: tg_c'nin kuralının aynısı.
           Ayrı bir kural konsaydı, ay adı bir dilden dizim başka bir
           dilden gelirdi. */
        if ($dil !== '' && $dil !== 'tr' && $dil !== 'en') return $dil;
        if ($enBayrak === null) return $dil !== '' ? $dil : 'tr';
        return $enBayrak ? 'en' : 'tr';
    }
    function tg_tarih_bicimi(string $dil): string {
        $d = (array)tg_ayar('diller', []);
        $b = (string)($d[$dil]['tarih'] ?? '');
        return $b !== '' ? $b : '%a %g, %y';
    }
    function tg_ay_tarih_adi(int $ay, string $dil, ?bool $enBayrak): string {
        $d = (array)tg_ayar('diller', []);
        $liste = $d[$dil]['ay_tarih'] ?? null;
        if (is_array($liste) && isset($liste[$ay - 1]) && (string)$liste[$ay - 1] !== '') {
            return (string)$liste[$ay - 1];
        }
        return tg_ay_adi($ay, $enBayrak);
    }
    /* BAYRAK BURADA DA TAŞINIR. İlk yazımda dizim yalnız k_dil()'e
       bakıyordu; İngilizce metni açıkça isteyen çağıran (arşiv dökümü,
       komut satırı) "No prior review … runs to 31 Aralık 2027" gibi
       yarı Türkçe bir cümle alıyordu. Ölçüm bunu dört sayfada birden
       gösterdi. Bayrak verilmişse o, verilmemişse sayfanın dili. */
    function tg_tarih_dizimi(int $gun, int $ay, int $yil, ?bool $enBayrak = null): string {
        $dil = tg_tarih_dil($enBayrak);
        $a   = tg_ay_tarih_adi($ay, $dil, $enBayrak);
        return str_replace(
            ['%g', '%A', '%a', '%y'],
            [(string)$gun, (string)$ay, $a, (string)$yil],
            tg_tarih_bicimi($dil));
    }

    /* MARKA ADI VE ALT YAZISI DA ÇEVİRİ KATMANINDAN GEÇER.
       Sekiz yerde `tg_ayar($en ? 'marka_en' : 'marka')` yazılıydı: bu
       bir AYAR ANAHTARI seçimidir ve üçüncü dilde sessizce Türkçe
       anahtarı seçer. Ayardaki iki değer okunur, hangisinin görüneceğine
       çeviri katmanı karar verir; sözlüğe karşılığı yazılırsa marka alt
       yazısı da o dile döner. Marka ADI çevrilmez ama yine buradan
       geçer: bir gün çevrilmesi istenirse tek yer değişir. */
    function tg_marka(?bool $enBayrak = null): string {
        $tr = (string)tg_ayar('marka', 'Kutadgu');
        $en = (string)tg_ayar('marka_en', $tr !== '' ? $tr : 'Kutadgu');
        if ($tr === '') $tr = 'Kutadgu';
        if ($en === '') $en = $tr;
        return tg_c($tr, $en, $enBayrak);
    }
    /* =================================================================
       SİSTEMİN KENDİ DOI'Sİ — TEK KAYNAK
       -----------------------------------------------------------------
       Numara yalnızca ayar.php'de yazılıdır ve buradan okunur. Sayfaya
       elle yazılmış bir DOI, ayar değiştiği gün sessizce yanlış olur ve
       yanlış bir kalıcı kimlik, olmayan bir kimlikten kötüdür: okuru
       var olmayan bir kayda gönderir.

       tg_doi()        kavram DOI'si (bütün sürümler). ANILACAK OLAN BU.
       tg_doi_surum()  yalnız bu sürüm.
       tg_doi_adres()  verilen DOI'nin çözümlenebilir adresi.

       Boş dönerse DOI HENÜZ ALINMAMIŞTIR ve çağıran yer hiçbir şey
       göstermemelidir. "DOI: —" yazmak, alınmamış bir kimliği varmış
       gibi göstermenin kibar biçimidir.
       ================================================================= */
    /* ---- KAYNAK KODUN ADRESİ (AGPL §13) ----
       Sisteme ağ üzerinden erişen herkese kaynağın SUNULMASI gerekir;
       404 dönen bir adres sunulmuş sayılmaz. Bu yüzden iki adres ayrı
       tutulur ve sayfalar hangisini basacağını kendileri seçmez.

       tg_kaynak_adres()  Bugün gerçekten indirilebilen adres. Depo
                          açıldıysa depo, açılmadıysa Zenodo kaydı.
       tg_kaynak_depo()   Depo adresi, yalnız AÇIKSA; kapalıysa boş.
                          Boş dönmesi bilerek: bir sayfa "varsa bas"
                          diye yazınca, olmayan bir bağ hiç basılmaz. */
    function tg_kaynak(): array {
        $k = (array)tg_ayar('kaynak', []);
        return [
            'zenodo'    => trim(tg_metin($k['zenodo'] ?? '')),
            'depo'      => trim(tg_metin($k['depo'] ?? '')),
            'depo_acik' => !empty($k['depo_acik']),
        ];
    }

    function tg_kaynak_depo(): string {
        $k = tg_kaynak();
        return $k['depo_acik'] ? $k['depo'] : '';
    }

    function tg_kaynak_adres(): string {
        $k = tg_kaynak();
        if ($k['depo_acik'] && $k['depo'] !== '') return $k['depo'];
        if ($k['zenodo'] !== '') return $k['zenodo'];
        /* İkisi de yoksa DOI'ye düşülür: kaynak zipini taşıyan kayıt
           odur ve o kayıt her zaman vardır. */
        return tg_doi_adres(tg_doi());
    }

    /* Adresin ne olduğunu okura söyleyen ad. "GitHub" ile "Zenodo
       kaydı" aynı şey değildir; bağın metni hangisine gittiğini
       söylemezse okur tıklamadan bilemez. */
    function tg_kaynak_adi(?bool $en = null): string {
        $k = tg_kaynak();
        if ($k['depo_acik'] && $k['depo'] !== '') return 'GitHub';
        return tg_c('Zenodo arşiv kaydı', 'Zenodo archive record', $en);
    }

    function tg_doi(): string {
        $d = (array)tg_ayar('doi', []);
        return trim((string)($d['kavram'] ?? ''));
    }
    function tg_doi_surum(): string {
        $d = (array)tg_ayar('doi', []);
        return trim((string)($d['surum'] ?? ''));
    }
    function tg_doi_surum_ad(): string {
        $d = (array)tg_ayar('doi', []);
        return trim((string)($d['surum_ad'] ?? ''));
    }
    function tg_doi_adres(string $doi = ''): string {
        $doi = trim($doi !== '' ? $doi : tg_doi());
        if ($doi === '') return '';
        /* Zaten tam adresse olduğu gibi bırakılır; iki kez ön ek almasın. */
        if (stripos($doi, 'http') === 0) return $doi;
        return 'https://doi.org/' . ltrim($doi, '/');
    }

    function tg_marka_alt(?bool $enBayrak = null): string {
        $tr = (string)tg_ayar('marka_alt', '');
        $en = (string)tg_ayar('marka_alt_en', '');
        if ($tr === '' && $en === '') return '';
        if ($en === '') $en = $tr;
        if ($tr === '') $tr = $en;
        return tg_c($tr, $en, $enBayrak);
    }

    function tg_c(string $tr, string $en, ?bool $enBayrak = null): string {
        /* ÇAĞIRANIN AÇIK İSTEĞİ, SAYFANIN DİLİNDEN ÖNCE GELİR.
           İlk yazımda bu işlev doğrudan k_c'ye gidiyordu ve k_c sayfanın
           dilini okur. Ama bu dosyanın işlevlerinin çoğu bir BAYRAK alır
           (tg_asama_metni($a, $en)) ve kimi çağıran o bayrağı bilerek
           verir: arşiv dökümü iki dili de üretir, komut satırı Türkçe
           metni ister. Bayrağı yok saymak, ölçümde on bir kusur olarak
           göründü: Türkçe sayfada 'Seeking reviewers' yazdı.
           Kural: sayfa ÜÇÜNCÜ bir dildeyse (tr/en değilse) çeviri
           katmanı kullanılır — çünkü bayrak orada zaten yanlış bir
           soruya yanıt verir. Değilse çağıranın dediği olur. */
        $dil = function_exists('k_dil') ? k_dil() : '';
        if ($dil !== '' && $dil !== 'tr' && $dil !== 'en') return k_c($tr, $en);
        if ($enBayrak === null) return function_exists('k_c') ? k_c($tr, $en) : $tr;
        return $enBayrak ? $en : $tr;
    }

    /* tg_c'nin değişken taşıyan biçimi. Gerekçesi kabuk.php'deki
       k_cd'nin başında yazılıdır: bir tarih ya da bir sayı metnin
       İÇİNE konursa çeviri anahtarı o tarihe ve o sayıya bağlanır,
       cümle hiçbir dilde bulunamaz. Anahtar "%1" ile sabit kalır,
       değer çeviri bulunduktan sonra yerine geçer. */
    function tg_cd(string $tr, string $en, ?bool $enBayrak, ...$deger): string {
        $m = tg_c($tr, $en, $enBayrak);
        foreach ($deger as $i => $d) {
            $m = str_replace('%' . ($i + 1), (string)$d, $m);
        }
        return $m;
    }

    /* ['tr'=>…, 'en'=>…] biçimindeki bir dizinin metni. k_t ile aynı
       iş; k_t yoksa eski davranış. */
    function tg_t(array $metin, ?bool $enBayrak = null): string {
        /* Bkz. tg_c: üçüncü dilde katman, tr/en'de çağıranın isteği. */
        $dil = function_exists('k_dil') ? k_dil() : '';
        if ($dil !== '' && $dil !== 'tr' && $dil !== 'en') return k_t($metin);
        if ($enBayrak === null && function_exists('k_t')) return k_t($metin);
        $k = $enBayrak ? 'en' : 'tr';
        return (string)($metin[$k] ?? ($metin['tr'] ?? ($metin['en'] ?? '')));
    }

    function tg_ayar(?string $anahtar = null, $vars = null) {
        static $a = null;
        if ($a === null) {
            $yol = __DIR__ . '/ayar.php';
            if (!is_file($yol)) $yol = dirname(__DIR__) . '/ayar.php';   /* api/ içinden çağrı */
            $a = is_file($yol) ? include $yol : [];
            if (!is_array($a)) $a = [];
            /* ---- DEĞİŞTİRİLEMEZ İLKELER KAPISI ----
               Yapılandırma, okunur okunmaz sekiz ilkeye karşı süzülür:
               ücret alan bir satır düşer, kapalı bir lisans açık lisansa
               çekilir, arşive onay kapısı koyan bir satır kapatılır,
               uyruk şartı düşer. Yani sistem, ilkeye aykırı bir ayar
               dosyasıyla hiç çalışmaz. Süzgecin ne yaptığı
               tg_ilke_suzgec_kaydi() ile okunur.

               Süzgeç bu dosyanın ilerisinde tanımlıdır; aynı blokta
               olduğu için çağrıldığı anda vardır, yine de denetleniyor:
               olmadığı bir durumda ayarı süzmeden geçirmek, sayfayı
               düşürmekten iyidir. */
            if (function_exists('tg_ayar_ilke_suz')) {
                $kayit = [];
                $a = tg_ayar_ilke_suz($a, $kayit);
                $GLOBALS['TG_ILKE_SUZGEC'] = $kayit;
            }
            /* ---- KURUL ADRESLERİ VERİ DİZİNİNDEN BİNDİRİLİR ----
               ayar.php depoya girer, veri dizini girmez. Kurul
               kayıtlarındaki e-posta adresleri kişisel veridir ve
               hiçbir sayfada gösterilmiyordu; depoya girmeleri, sitenin
               bilerek sakladığı bir veriyi açmak olurdu. Bu yüzden
               ayar.php'de alanlar boş durur, gerçek değerler veri
               dizinindeki kurul-eposta.json dosyasından buraya bindirilir.

               BİNDİRME ARTIK ZORUNLU DEĞİL, İSTEĞE BAĞLIDIR. Kimlik
               denetimi ayar.php'deki 'eposta_ozet' alanıyla yapılır ve
               hiçbir ek dosya istemez; dosya yoksa kurucular baş
               editör olarak tanınmayı sürdürür. Bindirme yine de
               duruyor, çünkü özetten adrese dönüş yoktur: ileride
               sisteme kurucuya posta gönderen bir özellik eklenirse
               (davet, bildirim) düz adres bu dosyadan gelecektir.

               NEDEN TAM BURASI: adresi okuyan yer çoktur (kurul.php,
               kisi.php, k/hesap.php, api/index.php) ve hepsi kaydın
               ['eposta'] alanını okur. Ayar dosyasını okuyan tek yer ise
               burasıdır; ayar.php'yi doğrudan include eden başka bir
               dosya yoktur. Birleştirmeyi buraya koymak, okuyanların
               hiçbirini değiştirmeden bütün yolları tek noktadan
               kapatır. Blok da yalnızca ilk yüklemede çalışır, çünkü
               $a bir kez doldurulur ve bellekte kalır; her tg_ayar()
               çağrısında dosya yeniden okunmaz.

               Bindirme burada, ilke süzgecinden SONRA yapılır: süzgeç
               ilkeye aykırı satırları düşürebilir, adres bindirmesi ise
               ilkelerle ilgisi olmayan bir tamamlamadır ve süzgecin
               düşürdüğü bir kaydı geri getirmemelidir. */
            $a = tg_ayar_kurul_eposta_bindir($a);
        }
        if ($anahtar === null) return $a;
        return array_key_exists($anahtar, $a) ? $a[$anahtar] : $vars;
    }

    /* ---------------------------------------------------------------
       Kurul kayıtlarının boş 'eposta' alanlarını veri dizinindeki
       kurul-eposta.json dosyasından tamamlar.

       Dosya biçimi yalındır, anahtar ad değer adrestir:
         { "Behiç Çetin": "...", "Murat Kayalar": "..." }

       Eşleştirme AD üzerindendir, sıra üzerinden değil: ayar
       dosyasındaki sıra bir kuruluş kaydıdır ve ileride araya bir kayıt
       girerse sıraya dayanan bir eşleşme sessizce yanlış adresi
       bindirirdi. Ad karşılaştırması tg_ad_anahtar() ile yapılır;
       depoda bunun için zaten tek bir işlev vardır ve unvanı atıp
       Türkçe harfleri sadeleştirir, böylece "Prof. Dr. Gökhan Kalağan"
       ile "gokhan kalagan" aynı kişi sayılır. İkinci bir karşılaştırma
       yazmak, iki ölçütün zamanla ayrışması demekti.

       Dosya yoksa, okunamıyorsa ya da bozuksa ayar olduğu gibi döner:
       eksik bir yardımcı dosya yüzünden sayfa düşmez. Ayar dosyasında
       yazılı duran bir adres EZİLMEZ; bindirme yalnızca boş alanları
       doldurur, yoksa yerel bir kurulumda elle yazılan adres sessizce
       kaybolurdu.
       --------------------------------------------------------------- */
    function tg_ayar_kurul_eposta_bindir(array $a): array {
        /* Veri dizini ayarın kendisinden okunur; bu işlev tg_ayar()
           içinden çağrıldığında $a artık doldurulmuş olduğu için
           tg_veri_dizini() geri dönüp sonsuz döngü kurmaz. */
        $dizin = function_exists('tg_veri_dizini')
            ? tg_veri_dizini()
            : (string)(getenv('KUTADGU_DATA') ?: '');
        if (trim($dizin) === '') return $a;
        $yol = rtrim($dizin, '/') . '/kurul-eposta.json';
        if (!is_file($yol) || !is_readable($yol)) return $a;
        $j = json_decode((string)@file_get_contents($yol), true);
        if (!is_array($j)) return $a;

        $harita = [];
        foreach ($j as $ad => $ep) {
            if (!is_string($ep)) continue;
            $ep = trim($ep);
            if ($ep === '') continue;
            $anahtar = tg_ad_anahtar((string)$ad);
            if ($anahtar === '') continue;
            $harita[$anahtar] = $ep;
        }
        if ($harita === []) return $a;

        /* İki liste de taranır: kuruluş kaydı ve sonradan atananlar.
           İkisi ayrı listedir ama adres bakımından aynı sorunu taşır. */
        foreach (['bas_editorler', 'gorevdeki_bas_editorler'] as $liste) {
            if (!isset($a[$liste]) || !is_array($a[$liste])) continue;
            foreach ($a[$liste] as $i => $kayit) {
                if (!is_array($kayit)) continue;
                if (trim((string)($kayit['eposta'] ?? '')) !== '') continue;
                $ad = trim((string)($kayit['ad'] ?? ''));
                if ($ad === '') continue;
                $anahtar = tg_ad_anahtar($ad);
                if ($anahtar !== '' && isset($harita[$anahtar])) {
                    $a[$liste][$i]['eposta'] = $harita[$anahtar];
                }
            }
        }
        return $a;
    }

    /* ---------------------------------------------------------------
       E-POSTA ANAHTARI VE ÖZETİ
       ---------------------------------------------------------------
       İki işlev de kimlik karşılaştırmasının tek kaynağıdır.

       tg_eposta_anahtar(): adresin karşılaştırılabilir biçimi. Kural
       tektir ve burada durur; k/hesap.php içindeki hs_eposta_anahtar()
       bu işlevi çağırır. İki ayrı normalleştirme yazılsaydı biri
       büyük/küçük harfi ötekinden başka çözdüğü gün, aynı kişi bir
       yerde tanınıp öteki yerde tanınmaz olurdu.

       tg_eposta_ozet(): aynı anahtarın sha256 özeti. Kurucu kayıtları
       ayar.php'de adresle değil bu özetle durur; gerekçesi ayar.php'de
       'bas_editorler' listesinin üstünde yazılıdır. Özet ALINACAK TEK
       YER burasıdır: ikinci bir yerde elle hash almak, bir gün
       normalleştirmenin değişmesiyle sessizce ayrışırdı.

       Boş adres boş özet döner. Boşun özeti alınsaydı, e-postası
       yazılmamış bir kayıt boş bir istekle eşleşir hâle gelirdi.
       --------------------------------------------------------------- */
    function tg_eposta_anahtar(string $e): string {
        return mb_strtolower(trim($e), 'UTF-8');
    }
    function tg_eposta_ozet(string $e): string {
        $a = tg_eposta_anahtar($e);
        return $a === '' ? '' : hash('sha256', $a);
    }

    /* ---------------------------------------------------------------
       KURUL KAYDI İLE BİR ADRESİN EŞLEŞMESİ
       ---------------------------------------------------------------
       Kural: DÜZ ADRES VARSA ONUNLA, YOKSA ÖZETLE.

       Sıra bilerek böyledir. Düz adres yalnızca yerel bir kurulumda
       ayar dosyasına elle yazıldığında ya da veri dizinindeki
       kurul-eposta.json bindirildiğinde bulunur; bulunduğunda en
       kesin ölçüt odur. Bulunmadığında özet devreye girer ve sistem
       hiçbir ek dosya olmadan kurucuyu tanımayı sürdürür. Bu ikinci
       yol olmasaydı, dosyanın sunucuda bulunmadığı bir kurulumda
       kurucular baş editör yetkisini yitirirdi.

       Kimliği e-posta ile eşleştiren HER YER bu işlevi çağırır. Altı
       ayrı karşılaştırma yazılsaydı, biri özet yolunu tanımadığı gün
       aynı kişi bir sayfada baş editör, ötekinde okur olurdu.

       Özet alanı 64 haneli onaltılık değilse yok sayılır: yarım
       yazılmış bir alanın "eşleşmedi" demesi, tanımadığı bir biçimi
       eşleştirmeye çalışmasından iyidir. Karşılaştırma hash_equals()
       ile yapılır; özet gizli bir değer değildir ama karşılaştırmanın
       süresi de bir bilgi taşımasın.
       --------------------------------------------------------------- */
    function tg_kurucu_eslesir(array $kayit, string $eposta): bool {
        $e = tg_eposta_anahtar($eposta);
        if ($e === '') return false;
        $duz = tg_eposta_anahtar((string)($kayit['eposta'] ?? ''));
        if ($duz !== '') return $duz === $e;
        $oz = strtolower(trim((string)($kayit['eposta_ozet'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $oz)) return false;
        return hash_equals($oz, tg_eposta_ozet($e));
    }

    /* Sistemin kök adresi. Yapılandırma boşsa isteğin geldiği alan adı kullanılır,
       böylece yanlış/eksik yapılandırmada bile bağlantılar kırılmaz. */
    function tg_kok(): string {
        $k = trim((string)tg_ayar('kok', ''));
        if ($k !== '') return rtrim($k, '/');
        $sema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host) ?? 'localhost';
        return $sema . '://' . $host;
    }

    /* Kök adrese göre tam bağlantı: tg_url('/basvuru.html') */
    function tg_url(string $yol = ''): string {
        if ($yol === '') return tg_kok();
        if (preg_match('#^https?://#i', $yol)) return $yol;
        return tg_kok() . '/' . ltrim($yol, '/');
    }

    /* Bir çalışmanın Tamga kimliği ve adresi.
       Dönen: ['kod'=>'bc.000001', 'tam'=>'tamga/bc.000001', 'url'=>'https://.../tamga/bc.000001'] */
    function tg_tamga(string $kod): array {
        $kod = trim($kod);
        if ($kod === '') return ['kod' => '', 'tam' => '', 'url' => ''];
        $yol = trim((string)tg_ayar('tamga_yol', 'tamga'), '/');
        return ['kod' => $kod, 'tam' => $yol . '/' . $kod, 'url' => tg_url($yol . '/' . $kod)];
    }

    /* ---------------------------------------------------------------
       TAMGA kimliği:  KTG-2026-00001-4
       Denetim hanesi ISO 7064 MOD 11-2 ile üretilir; yanlış yazılan
       ya da eksik aktarılan bir kimlik anında anlaşılır.
       Eski biçimler (bc.000001) geçerliliğini korur.
       --------------------------------------------------------------- */

    /* "202600001" gibi hane dizisinden denetim hanesi (0-9 ya da X) */
    function tg_denetim(string $haneler): string {
        $t = 0;
        $n = strlen($haneler);
        for ($i = 0; $i < $n; $i++) {
            $t = ($t + (int)$haneler[$i]) * 2;
        }
        $k = (12 - ($t % 11)) % 11;
        return $k === 10 ? 'X' : (string)$k;
    }

    /* Kimliği çözümle: ['on'=>'KTG','yil'=>2026,'sira'=>1,'denetim'=>'4'] ya da null */
    function tg_tamga_coz(string $kod): ?array {
        $kod = strtoupper(trim($kod));
        if (!preg_match('/^([A-Z]{2,6})-(\d{4})-(\d{4,7})-([0-9X])$/', $kod, $m)) return null;
        if (tg_denetim($m[2] . $m[3]) !== $m[4]) return null;
        return ['on' => $m[1], 'yil' => (int)$m[2], 'sira' => (int)$m[3], 'denetim' => $m[4]];
    }

    function tg_tamga_gecerli(string $kod): bool { return tg_tamga_coz($kod) !== null; }

    /* Sıradaki Tamga kodunu üret. Sıra her yıl yeniden başlar. */
    function tg_sonraki_kod(array $yazilar, ?int $yil = null): string {
        $on = strtoupper(preg_replace('/[^a-z0-9]/i', '', (string)tg_ayar('tamga_on', 'KTG')));
        if ($on === '') $on = 'KTG';
        if ($yil === null) $yil = (int)date('Y');
        $mx = 0;
        foreach ($yazilar as $e) {
            if (!is_array($e)) continue;
            $k = strtoupper(trim((string)($e['bcid'] ?? '')));
            if (preg_match('/^' . preg_quote($on, '/') . '-(\d{4})-(\d{4,7})-[0-9X]$/', $k, $m)) {
                if ((int)$m[1] === $yil) $mx = max($mx, (int)$m[2]);
            }
        }
        $sira = str_pad((string)($mx + 1), 5, '0', STR_PAD_LEFT);
        return $on . '-' . $yil . '-' . $sira . '-' . tg_denetim($yil . $sira);
    }

    /* Bir kod bu çalışmayı gösteriyor mu?
       Geçerli tamganın yanı sıra çalışmanın taşıdığı eski kodlara da
       bakar. Kuruluş döneminde verilmiş "bc.000001" gibi kodlar
       kırılmasın diye tutulur; o kodla gelen istek bulunur ve kalıcı
       adrese 301 ile toplanır. Kimlik değişse de paylaşılmış hiçbir
       bağlantı ölmez. */
    function tg_kod_esles(array $y, string $kod): bool {
        $kod = strtolower(trim($kod));
        if ($kod === '') return false;
        if (strtolower(trim((string)($y['bcid'] ?? ''))) === $kod) return true;
        foreach ((array)($y['eski_kod'] ?? []) as $e) {
            if (strtolower(trim((string)$e)) === $kod) return true;
        }
        return false;
    }

    /* ---------------------------------------------------------------
       Bir çalışmanın KALICI adresi.
       Bir çalışmanın adresi başlığından değil kimliğinden türer:
           /tamga/KTG-2026-00001-4
       Başlık düzeltilse, slug değişse, sistem başka bir alan adına
       taşınsa bile bu adres bozulmaz. Slug adresi çalışmayı sürdürür
       ama kalıcı adrese yönlendirilir; paylaşılan hiçbir bağlantı
       kırılmaz, arama motorları tek adreste toplanır.
       --------------------------------------------------------------- */
    function tg_yazi_yolu(array $y): string {
        $kod = trim((string)($y['bcid'] ?? ''));
        if ($kod !== '') {
            return '/' . trim((string)tg_ayar('tamga_yol', 'tamga'), '/') . '/' . rawurlencode($kod);
        }
        $s = trim((string)($y['slug'] ?? ''));
        if ($s !== '') return '/yazi.php?y=' . rawurlencode($s);
        return '/yazi.php?id=' . rawurlencode((string)($y['id'] ?? ''));
    }
    function tg_yazi_adres(array $y): string { return tg_kok() . tg_yazi_yolu($y); }

    /* ---------------------------------------------------------------
       Hakemi kim atadı?
       Okuyucunun görmesi gereken bir bilgidir: bir hakemi yazarın kendisi
       mi önerdi, yoksa bir editör mü atadı? Atama kaydı hakemle birlikte
       tutulur, çalışma sayfasında ve rapor belgesinde açıkça gösterilir.
       --------------------------------------------------------------- */
    /* Kayıt zamanını, kaydedildiği saat diliminde göster.
       Sunucunun saat dilimi değişse bile geçmiş kayıtların saati kaymaz. */
    function tg_zaman(string $iso): string {
        $iso = trim($iso);
        if ($iso === '') return '';
        try { return (new DateTime($iso))->format('d.m.Y H:i'); }
        catch (Exception $e) { $t = strtotime($iso); return $t ? date('d.m.Y H:i', $t) : ''; }
    }

    function tg_atayan_metin($a, bool $en = false): string {
        if (!is_array($a)) return '';
        $tur = (string)($a['tur'] ?? '');
        $etiket = [
            'yazar'      => ['Yazarın önerisiyle', 'At the author\'s proposal'],
            'gonullu'    => ['Kendi isteğiyle (gönüllü hakem), editör onayıyla', 'By their own offer (volunteer reviewer), approved by an editor'],
            'editor'     => ['Editör atamasıyla', 'Assigned by an editor'],
            'bas_editor' => ['Baş editör atamasıyla', 'Assigned by a chief editor'],
            'yonetim'    => ['Sistem yönetimi atamasıyla', 'Assigned by the system administration'],
        ];
        if (!isset($etiket[$tur])) return '';
        $s = tg_t(['tr' => $etiket[$tur][0], 'en' => $etiket[$tur][1]], $en);
        $ad = trim((string)($a['ad'] ?? ''));
        if ($ad !== '') $s .= ': ' . $ad;
        $z = tg_zaman((string)($a['tarih'] ?? ''));
        if ($z !== '') $s .= ' · ' . $z;
        return $s;
    }

    /* ---------------------------------------------------------------
       Ad karşılaştırma: unvanlar atılır, Türkçe harfler sadeleşir.
       "Prof. Dr. Ayşe KAYA" ile "ayse kaya" aynı kişi sayılır.
       --------------------------------------------------------------- */
    function tg_ad_anahtar(string $ad): string {
        /* Türkçe harfler önce, büyük küçük ayrımı yapılmadan sadeleştirilir.
           Bunu küçültmeden ÖNCE yapmak gerekir: "İ" harfi küçültüldüğünde
           bazı ortamlarda üstüne ayrı bir birleşen nokta düşer ve ad ikiye
           bölünür. Bu yüzden aşağıda hem büyük hem küçük biçimler yazılıdır. */
        $a = trim($ad);
        $a = strtr($a, [
            'Ç'=>'C','Ğ'=>'G','I'=>'I','İ'=>'I','Ö'=>'O','Ş'=>'S','Ü'=>'U',
            'Â'=>'A','Î'=>'I','Û'=>'U',
            'ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',
            'â'=>'a','î'=>'i','û'=>'u',
        ]);
        $a = mb_strtolower($a, 'UTF-8');
        /* Küçültme sırasında oluşabilecek birleşen noktalar temizlenir */
        $a = str_replace(["\xCC\x87", "\xCC\x88"], '', $a);
        $a = preg_replace('/\b(prof|doç|doc|dr|öğr|ogr|arş|ars|uzm|av|gör|gor)\b\.?/u', ' ', $a);
        $a = preg_replace('/[^a-z0-9]+/', ' ', $a);
        return trim(preg_replace('/\s+/', ' ', (string)$a));
    }

    /* ---------------------------------------------------------------
       Kayıt içindeki liste alanını güvenle okumak.

       'hakemler', 'yazar_liste', 'gonulluler' gibi alanların dizi
       olduğu varsayılır, ama veri elle de düzenlenebilir ve bir
       aktarım bu alanı dizge bırakabilir. foreach dizgeyi gezmeye
       kalkınca sonuç yine doğru çıkar (hiçbir öge sayılmaz) ama her
       sayfa isteğinde kütüğe "foreach() argument must be of type
       array|object" düşer. Kütüğün kirlenmesi kendi başına bir kusur:
       gerçek hatalar bu gürültünün içinde görünmez olur.

       Alanın kendisi dizi değilse boş liste döner; tek tek ögelerin
       bozukluğu ise döngünün içinde is_array() ile elenir, çünkü bir
       bozuk öge yüzünden bütün kaydı düşürmek veriyi olduğundan daha
       çok kaybetmek demektir. Aynı ölçüt tg_kayitlar() içinde de
       kullanılıyordu; burada tek bir yere alındı. */
    function tg_dizi($v): array {
        return is_array($v) ? $v : [];
    }

    /* Bir çalışmanın bütün yazarlarının ad anahtarları */
    function tg_yazar_anahtarlari(array $y): array {
        $k = [];
        $ilk = trim((string)($y['yazar'] ?? ''));
        if ($ilk !== '') $k[] = tg_ad_anahtar($ilk);
        $b = $y['yazar_bilgi'] ?? null;
        if (is_array($b) && trim((string)($b['ad'] ?? '')) !== '') $k[] = tg_ad_anahtar((string)$b['ad']);
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
            if (is_array($ya) && trim((string)($ya['ad'] ?? '')) !== '') $k[] = tg_ad_anahtar((string)$ya['ad']);
        }
        return array_values(array_filter(array_unique($k)));
    }

    /* Bir çalışmanın yazarlarının ORCID'leri. Ad eşleştirmesi tek başına
       yetmez: "Mehmet Yılmaz" ile "M. Yılmaz" farklı anahtar üretir ve
       çıkar çatışması denetimi sessizce boşa düşer. ORCID varsa kesin
       ölçüttür, adın yazılışından etkilenmez. */
    function tg_yazar_orcidleri(array $y): array {
        $o = [];
        $ek = function ($v) use (&$o) {
            $v = tg_orcid_anahtar((string)$v);
            if (strlen($v) === 16) $o[] = $v;
        };
        $b = $y['yazar_bilgi'] ?? null;
        if (is_array($b)) $ek($b['orcid'] ?? '');
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
            if (is_array($ya)) $ek($ya['orcid'] ?? '');
        }
        return array_values(array_unique($o));
    }

    /* ---------------------------------------------------------------
       Bir çalışmanın yazarlarının E-POSTA ADRESLERİ.

       ÜÇÜNCÜ TANIMLAYICI. Yukarıdaki yorum "ad eşleştirmesi tek başına
       yetmez" diyor ve bu yüzden ORCID eklenmişti. Aynı cümle ORCID
       için de geçerli: ORCID de adayın forma kendi yazdığı bir alandır
       ve ikinci bir ORCID almak kimseyi zorlamaz. Ölçüldü (11 Ağustos
       2026): kendi çalışmasına gönüllü olmak isteyen biri adını
       değiştirip başka bir ORCID yazınca denetimden geçiyor, ama
       başvuruyu kendi e-posta adresiyle yapıyordu, çünkü onaylandığında
       değerlendirme bağlantısının geleceği yer orası. Adres, kimliğin
       gerçekten kullanılmak zorunda olduğu tek alandır; bu yüzden
       denetimin üçüncü ayağıdır.

       ADRESİN DURABİLECEĞİ YERLER, koda bakılarak çıkarıldı:
         yazar_bilgi.eposta        başvurudan gelen ana kayıt
         yazar_liste[].eposta      çok yazarlı çalışmada öteki yazarlar
         yazar_erisim.eposta_acik  yazarın erişim kaydındaki düz adres

       yazar_erisim.eposta_hash BİLEREK ALINMADI: o bir adres değil,
       adresin özetidir ve düz adres her zaman yanında, yazar_bilgi
       içinde durur (api/index.php, çalışma açılırken ikisi de aynı
       değerden üretilir). Özeti burada çözmeye çalışmak, bu listeyi
       "adresler listesi" olmaktan çıkarır ve çağıranı yanıltırdı.

       İçinde '@' olmayan değer atılır: veri elle de düzenlenebilir ve
       'yok' ya da '-' gibi bir doldurma değeri adres sayılırsa, aynı
       şeyi yazan iki ayrı kayıt aynı kişi sanılırdı.
       --------------------------------------------------------------- */
    function tg_yazar_epostalari(array $y): array {
        $e = [];
        $ek = function ($v) use (&$e) {
            $a = tg_eposta_anahtar((string)$v);
            if ($a !== '' && strpos($a, '@') !== false) $e[] = $a;
        };
        $b = $y['yazar_bilgi'] ?? null;
        if (is_array($b)) $ek($b['eposta'] ?? '');
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
            if (is_array($ya)) $ek($ya['eposta'] ?? '');
        }
        $er = $y['yazar_erisim'] ?? null;
        if (is_array($er)) $ek($er['eposta_acik'] ?? '');
        return array_values(array_unique($e));
    }

    /* Bir hakem kaydındaki düz adresler. Kayıt iki ayrı yerde adres
       taşıyabilir ve ikisi her zaman aynı değildir: 'eposta_acik'
       davetin gittiği adrestir, 'profil.eposta' hakemin kendi verdiği
       adrestir. Karşılıklılık denetimi ikisine de bakmalıdır, yoksa
       adresini profilinde güncelleyen bir hakem tanınmaz olur. */
    function tg_hakem_epostalari(array $h): array {
        $e = [];
        $kaynak = [$h['eposta_acik'] ?? '', ($h['profil']['eposta'] ?? '')];
        foreach ($kaynak as $v) {
            $a = tg_eposta_anahtar((string)$v);
            if ($a !== '' && strpos($a, '@') !== false) $e[] = $a;
        }
        return array_values(array_unique($e));
    }

    /* ---------------------------------------------------------------
       HAKEM ÇAKIŞMASI: TEK KAPI
       ---------------------------------------------------------------
       Bu denetim önce yalnızca yazarın davet yolunda vardı. Editör
       ataması ve gönüllü onayı ondan geçmiyordu; yani B, A'nın
       çalışmasına gönüllü olarak yazılabiliyor ve karşılıklı hakemlik
       denetimi hiç çalışmıyordu. Ölçüldü ve doğrulandı (11 Ağustos
       2026). Kural bir yerde yazılıp üç yoldan ikisinde uygulanmazsa
       kural değildir; bu yüzden denetim tek bir işleve alındı ve üç
       yolun da önüne kondu.

       Dönüş: engel yoksa boş dizi, varsa
       ['kod' => 'kendisi|ortak|karsilikli', 'mesaj' => '...'].

       ÜÇ ŞEYE BAKAR:
         1. Aday bu çalışmanın yazarlarından biri mi (kendi işini
            değerlendiremez).
         2. Aday bu yazarlarla başka bir çalışmada ortak yazar olmuş mu.
         3. Bu çalışmanın yazarlarından biri, adayın bir çalışmasını
            yakın zamanda değerlendirmiş mi (karşılıklılık). Bu madde
            A↔B eşleşmesinin iki yönünü de kapatır: "A, B'yi
            değerlendirdi" ile "B, A'yı değerlendirdi" aynı cümledir.

       BİR ŞEYE BAKMAZ VE BİLEREK BAKMAZ: aynı hakemin aynı yazarın
       ikinci çalışmasını da değerlendirmesi. Bu karşılıklılık değildir,
       tekrar eden eşleşmedir. Yasaklamak küçük bir arşivde zarar verir:
       dar bir alanda o kişi gerçekten tek uygun hakem olabilir. Bu
       sistemin yolu yasaklamak değil görünür kılmaktır; sayım
       tg_hakem_tekrar() ile yapılır ve editöre atama anında gösterilir.

       Üçüncü maddede RAPOR YAZILMIŞ OLMASI ARANMAZ. Eski
       denetim yalnızca raporu tamamlanmış hakemliği sayıyordu; oysa
       karşılıklılık atama anında kurulur, rapor yazıldığında değil.
       Bekleyen bir atama da sırayı kapatır.

       ÜÇÜNCÜ TANIMLAYICI: E-POSTA. $adayEposta isteğe bağlıdır, çünkü
       bu işlevi çağıran her yerin elinde adres yoktur ve olmayan bir
       adres yüzünden denetim çökmemelidir. Ama adres GEÇİLDİĞİNDE üç
       kuralın üçünde birden ölçüt olur: kendisi, ortak yazarlık ve
       karşılıklılık. Ölçüldü (11 Ağustos 2026): adını ve ORCID'ini
       değiştirip kendi çalışmasına gönüllü olan biri, başvuruyu kendi
       adresiyle yaptığı için buradan yakalanır.
       --------------------------------------------------------------- */
    function tg_hakem_cakisma(array $yazilar, int $indeks, string $adayAd, string $adayOrcid = '', string $adayEposta = ''): array {
        $yazi = $yazilar[$indeks] ?? null;
        if (!is_array($yazi)) return [];
        $adayK = tg_ad_anahtar($adayAd);
        /* ORCID on altı hane değilse yok sayılır: yarım yazılmış bir
           kimlik yanlış eşleşme üretir ve denetimi bozar. Anahtar
           üreten işlev aşağıda zaten tanımlı (tg_orcid_anahtar). */
        $adayO = strlen(tg_orcid_anahtar($adayOrcid)) === 16 ? tg_orcid_anahtar($adayOrcid) : '';
        /* Adresin de tek bir normalleştirmesi var: tg_eposta_anahtar().
           '@' içermeyen bir değer adres sayılmaz; yoksa boş ya da
           doldurma bir alan iki ayrı kişiyi aynı kişi yapardı. */
        $adayE = tg_eposta_anahtar($adayEposta);
        if (strpos($adayE, '@') === false) $adayE = '';
        if ($adayK === '' && $adayO === '' && $adayE === '') return [];

        $yazarK = tg_yazar_anahtarlari($yazi);
        $yazarO = tg_yazar_orcidleri($yazi);
        $yazarE = tg_yazar_epostalari($yazi);

        /* Aday bu kişilerden biri mi: ad, ORCID ya da adres tutarsa evet. */
        $ayni = function (array $adlar, array $orcidler, array $epostalar = []) use ($adayK, $adayO, $adayE): bool {
            if ($adayO !== '' && in_array($adayO, $orcidler, true)) return true;
            if ($adayE !== '' && in_array($adayE, $epostalar, true)) return true;
            return $adayK !== '' && in_array($adayK, $adlar, true);
        };

        if ($ayni($yazarK, $yazarO, $yazarE)) {
            return ['kod' => 'kendisi',
                    'mesaj' => 'Bir çalışmanın yazarı kendi çalışmasının hakemi olamaz.'];
        }

        $ay    = (int)tg_ayar('karsilikli_hakem_ay', 12);
        $sinir = time() - ($ay * 30 * 86400);

        foreach ($yazilar as $ix => $w) {
            if ($ix === $indeks || !is_array($w)) continue;
            $wK = tg_yazar_anahtarlari($w);
            $wO = tg_yazar_orcidleri($w);
            $wE = tg_yazar_epostalari($w);
            $adayYazar = $ayni($wK, $wO, $wE);
            /* Ortaklık da adresten kurulabilir: aynı kişi iki çalışmada
               adını başka yazmış olabilir, adresini değiştirmesi ise
               gerçekten adresi değiştirmeyi gerektirir. */
            $ortakVar  = (bool)array_intersect($wK, $yazarK)
                      || (bool)array_intersect($wO, $yazarO)
                      || (bool)array_intersect($wE, $yazarE);

            /* 2. Ortak yazarlık */
            if ($ortakVar && $adayYazar) {
                return ['kod' => 'ortak',
                        'mesaj' => 'Bu kişi çalışmanın yazarlarıyla ortak yayın yapmış görünüyor; çıkar çatışması nedeniyle hakem olamaz.'];
            }

            foreach (tg_dizi($w['hakemler'] ?? null) as $wh) {
                if (!is_array($wh)) continue;
                $whK = tg_ad_anahtar((string)($wh['ad'] ?? ''));
                $whO = tg_orcid_anahtar((string)(($wh['profil']['orcid'] ?? '') ?: ''));
                if (strlen($whO) !== 16) $whO = '';
                /* Hakem kaydının düz adresleri de ölçüte girer: adını
                   başka yazan bir hakem, adresiyle tanınır. */
                $whE = tg_hakem_epostalari($wh);
                $ts  = strtotime((string)($wh['tarih'] ?? '')) ?: (strtotime((string)($wh['davet_tarih'] ?? '')) ?: 0);
                if ($ts !== 0 && $ts <= $sinir) continue;
                /* Geri çekilmiş ya da reddedilmiş davet sırayı kapatmaz. */
                $dd = (string)($wh['davet_durum'] ?? '');
                if ($dd === 'ret' || $dd === 'geri_cekildi') continue;

                /* 3. Bu çalışmanın yazarlarından biri, adayın çalışmasını
                      değerlendirmiş (ya da değerlendiriyor). */
                if ($adayYazar) {
                    $hakemYazar = ($whO !== '' && in_array($whO, $yazarO, true))
                               || (bool)array_intersect($whE, $yazarE)
                               || ($whK !== '' && in_array($whK, $yazarK, true));
                    if ($hakemYazar) {
                        return ['kod' => 'karsilikli',
                                'mesaj' => 'Karşılıklı hakemlik kapalıdır: bu çalışmanın yazarlarından biri, adı geçen kişinin bir çalışmasını son ' . $ay . ' ay içinde değerlendirdi ya da değerlendiriyor.'];
                    }
                }

            }
        }
        return [];
    }

    /* ---------------------------------------------------------------
       YAZAR VE HAKEM ARASINDAKİ DİYALOG
       ---------------------------------------------------------------
       Bugüne kadar hakem raporunu yazıyor, yazar metnini düzeltiyor ve
       ikisi birbiriyle hiç konuşmuyordu. Yazarın söyleyeceği bir şey
       varsa gidecek tek yer kurul itirazıydı; o da rapordan sonra
       açılıyor ve karara değil kurula gidiyordu. Yani "bu bulguyu yanlış
       okumuşsunuz, şu sayfaya bakar mısınız" demenin yolu yoktu.

       Açılan kanalın üç sınırı var ve üçü de bilerek konuldu:

         1. KAYITLIDIR. Diyaloğun tamamı çalışmanın kalıcı kaydına girer
            ve yayımlandığında raporun altında görünür. Silinmez. Gizli
            bir kanal, kapalı hakemliğe yöneltilen eleştirinin aynısını
            bu sisteme taşırdı.
         2. SAYILIDIR. Her iki taraf için tur sayısı sınırlıdır
            (ayar: diyalog_tur_ust). Sınırsız bir tartışma, hakemin
            gönüllü emeğini sömürür ve kararı geciktirir.
         3. SIRAYLADIR. Yazar yazar, hakem yanıtlar. Yazar üst üste iki
            not yazamaz; hakem yanıtlamadan yeni tur açılmaz.

       Kişisel adres, telefon ya da başka bir iletişim yolu bu kanalda
       paylaşılmaz; zaten hiçbir alanı yoktur. Taraflar birbirinin
       adresini görmez.
       --------------------------------------------------------------- */

    /* Bir hakem kaydındaki diyalog dizisi. */
    function tg_diyalog(array $hakem): array {
        $d = is_array($hakem['diyalog'] ?? null) ? $hakem['diyalog'] : [];
        $out = [];
        foreach ($d as $e) {
            if (!is_array($e)) continue;
            $yon = (string)($e['yon'] ?? '');
            if ($yon !== 'yazar' && $yon !== 'hakem') continue;
            if (trim((string)($e['metin'] ?? '')) === '') continue;
            $out[] = ['yon' => $yon, 'metin' => (string)$e['metin'],
                      'tarih' => (string)($e['tarih'] ?? ''),
                      'surum' => (string)($e['surum'] ?? '')];
        }
        return $out;
    }

    /* Kaç tur tamamlandı: bir tur = yazarın notu + hakemin yanıtı. */
    function tg_diyalog_tur(array $hakem): int {
        $y = 0;
        foreach (tg_diyalog($hakem) as $e) if ($e['yon'] === 'yazar') $y++;
        return $y;
    }

    /* Sıra kimde: 'yazar', 'hakem' ya da '' (kanal kapalı). */
    function tg_diyalog_sira(array $hakem): string {
        /* Kanal ancak rapor yazılmışken ve süreç açıkken işler.
           Kabul ve ret kararı süreci bitirir; itiraz yolu ayrıdır. */
        if (trim((string)($hakem['rapor'] ?? '')) === '') return '';
        $karar = (string)($hakem['karar'] ?? '');
        if ($karar !== 'buyuk' && $karar !== 'kucuk') return '';
        if (!empty($hakem['kapali'])) return '';
        $d = tg_diyalog($hakem);
        $ust = (int)tg_ayar('diyalog_tur_ust', 2);
        $tur = tg_diyalog_tur($hakem);
        $son = $d ? $d[count($d) - 1]['yon'] : '';
        if ($son === 'yazar') return 'hakem';
        if ($tur >= $ust) return '';
        return 'yazar';
    }

    /* ---- TEKRAR EDEN EŞLEŞME: YASAK DEĞİL, GÖRÜNÜR ----
       Bu kişi, bu çalışmanın yazarlarının kaç ayrı çalışmasında daha
       hakem olarak görev aldı? Sıfırdan büyükse editör bunu atama
       anında görür ve kararını bilerek verir. Engel değildir; dar bir
       alanda aynı adın ikinci kez çıkması olağandır. Ama sessiz
       kalmamalıdır: açık hakemliğin anlamı, böyle bir örüntünün
       görülebilir olmasıdır. */
    function tg_hakem_tekrar(array $yazilar, int $indeks, string $adayAd, string $adayOrcid = ''): int {
        $yazi = $yazilar[$indeks] ?? null;
        if (!is_array($yazi)) return 0;
        $adayK = tg_ad_anahtar($adayAd);
        $adayO = tg_orcid_anahtar($adayOrcid);
        if (strlen($adayO) !== 16) $adayO = '';
        if ($adayK === '' && $adayO === '') return 0;
        $yazarK = tg_yazar_anahtarlari($yazi);
        $yazarO = tg_yazar_orcidleri($yazi);
        $say = 0;
        foreach ($yazilar as $ix => $w) {
            if ($ix === $indeks || !is_array($w)) continue;
            if (!array_intersect(tg_yazar_anahtarlari($w), $yazarK)
                && !array_intersect(tg_yazar_orcidleri($w), $yazarO)) continue;
            foreach (tg_dizi($w['hakemler'] ?? null) as $wh) {
                if (!is_array($wh)) continue;
                $dd = (string)($wh['davet_durum'] ?? '');
                if ($dd === 'ret' || $dd === 'geri_cekildi') continue;
                $whK = tg_ad_anahtar((string)($wh['ad'] ?? ''));
                $whO = tg_orcid_anahtar((string)(($wh['profil']['orcid'] ?? '') ?: ''));
                if (strlen($whO) !== 16) $whO = '';
                if (($adayO !== '' && $whO !== '' && $adayO === $whO)
                    || ($adayK !== '' && $whK !== '' && $adayK === $whK)) { $say++; break; }
            }
        }
        return $say;
    }

    /* ---------------------------------------------------------------
       Hakem bağımsız mı? Yazarın kendi önerdiği hakem bağımsız değildir.
       --------------------------------------------------------------- */
    function tg_hakem_bagimsiz($h): bool {
        if (!is_array($h)) return false;
        $t = (string)(($h['atayan']['tur'] ?? ''));
        if ($t === '') return true;              /* eski kayıtlar: atama bilgisi yok, bağımsız sayılır */
        return $t !== 'yazar';
    }

    /* ---------------------------------------------------------------
       Raporun nitelik eşiği. Karşılamayan rapor yayımlanır, gösterilir,
       ama hakemlik sayımına ve onay sayımına katılmaz.
       Dönen: ['yeterli'=>bool, 'eksik'=>[tr metinleri]]
       --------------------------------------------------------------- */
    /* ---------------------------------------------------------------
       ZENGİN METİN: eski düz yazılar ile yeni biçimli metinler bir arada
       ---------------------------------------------------------------
       Hakem raporları ve editör notları başlangıçta düz metin olarak
       yazılıyordu ve sayfada nl2br ile basılıyordu. Yazma ekranına bir
       düzenleyici konduğunda yeni metinler HTML olarak geliyor. İki
       biçim arşivde yan yana durur; geçmişi geriye dönük değiştirmek
       bu sistemde yapılmaz.

       Bu yüzden gösterimde ayrım burada yapılır: metinde bir etiket
       yoksa düz metin gibi (kaçırılarak, satır sonları korunarak),
       varsa süzülmüş HTML olarak basılır. Uzunluk ölçümü de her iki
       durumda metnin kendi uzunluğunu sayar; etiketler sayılmaz, yoksa
       biçimlendirme eşiği aşmanın yolu olurdu.
       --------------------------------------------------------------- */
    /* ---------------------------------------------------------------
       Yazar ve hakem içeriği için güvenli HTML.
       Yalnızca izinli etiket ve öznitelikler kalır. Olay öznitelikleri
       (onclick vb.), script/iframe/style ve javascript: adresleri
       tamamen elenir. Düzenleyicinin ürettiğine güvenilmez; süzgeç
       burada, sunucudadır.

       api/ dizininden de, kamusal sayfalardan da çağrılır; bu yüzden
       ortak.php içindedir.
       --------------------------------------------------------------- */
function guvenli_html(string $h): string {
    $h = trim($h);
    if ($h === '') return '';
    $izinli = [
        'p'=>[], 'br'=>[], 'strong'=>[], 'b'=>[], 'em'=>[], 'i'=>[], 'u'=>[], 'sup'=>[], 'sub'=>[], 'small'=>[],
        'h2'=>[], 'h3'=>[], 'h4'=>[], 'h5'=>[], 'ul'=>[], 'ol'=>[], 'li'=>[], 'blockquote'=>[], 'hr'=>[],
        'table'=>[], 'thead'=>[], 'tbody'=>[], 'tfoot'=>[], 'tr'=>[],
        'th'=>['colspan','rowspan'], 'td'=>['colspan','rowspan'],
        'a'=>['href','title'], 'img'=>['src','alt','title'],
        'figure'=>[], 'figcaption'=>[], 'code'=>[], 'pre'=>[], 'span'=>[], 'div'=>[],
    ];
    $tamSil = ['script','style','iframe','object','embed','form','input','button','select','textarea','link','meta','base','svg','math','audio','video','source','noscript','template'];
    $dom = new DOMDocument();
    $eski = libxml_use_internal_errors(true);
    $ok = $dom->loadHTML('<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div>' . $h . '</div>',
        LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors(); libxml_use_internal_errors($eski);
    if (!$ok) return '';
    $divler = $dom->getElementsByTagName('div');
    $kok = $divler->length > 0 ? $divler->item(0) : null;
    if (!$kok) return '';
    $gez = function(DOMNode $d) use (&$gez, $izinli, $tamSil) {
        for ($i = $d->childNodes->length - 1; $i >= 0; $i--) {
            $c = $d->childNodes->item($i);
            if ($c instanceof DOMComment) { $d->removeChild($c); continue; }
            if (!($c instanceof DOMElement)) continue;
            $ad = strtolower($c->nodeName);
            if (in_array($ad, $tamSil, true)) { $d->removeChild($c); continue; }
            if (!isset($izinli[$ad])) {           /* bilinmeyen etiket: kabuğu at, metni koru */
                while ($c->firstChild) $d->insertBefore($c->firstChild, $c);
                $d->removeChild($c);
                continue;
            }
            $silinecek = [];
            foreach ($c->attributes as $a) {
                $an = strtolower($a->nodeName);
                if (!in_array($an, $izinli[$ad], true)) { $silinecek[] = $a->nodeName; continue; }
                $v = trim((string)$a->nodeValue);
                if (($an === 'href' || $an === 'src') && !preg_match('#^(https?://|mailto:|/|\#)#i', $v)) $silinecek[] = $a->nodeName;
            }
            foreach ($silinecek as $sa) $c->removeAttribute($sa);
            if ($ad === 'a') { $c->setAttribute('rel', 'noopener nofollow'); $c->setAttribute('target', '_blank'); }
            $gez($c);
        }
    };
    $gez($kok);
    $ic = '';
    foreach ($kok->childNodes as $c) $ic .= $dom->saveHTML($c);
    return trim($ic);
}

    function tg_metin_html_mi(string $s): bool {
        return (bool)preg_match('/<(p|br|div|ul|ol|li|h[2-6]|table|blockquote|strong|em|b|i|u|sup|sub|a|img|figure|pre|code|hr|span)\b[^>]*>/i', $s);
    }

    /* Görünür metin: eşik ölçümü, özet ve arama için */
    function tg_duz(string $s): string {
        if ($s === '') return '';
        if (tg_metin_html_mi($s)) {
            $s = preg_replace('#<(br|/p|/li|/h[2-6]|/tr)[^>]*>#i', "\n", $s);
            $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        return trim(preg_replace('/[ \t]+/u', ' ', $s));
    }

    /* Sayfada basılacak güvenli gösterim */
    /* AYNI METİN İKİ KEZ BASILMASIN. Yazar tam metni Word'den yapıştırırken
       çoğu zaman metnin içindeki "Abstract/Özet" ve "References/Kaynakça"
       bölümlerini de getirir; oysa özet ve kaynakça ayrı kutulardan da
       alınır ve sayfa onları kendisi basar. Sonuç: özet iki, kaynakça iki
       kez görünüyor, içindekilerde "References" iki satır oluyor.

       Yalnızca gövdedeki bölüm ayrı alanla AYNI İÇERİĞİ taşıyorsa atılır:
       başlığı özet/kaynakça adlarından biri olmalı ve ilk otuz sözcüğü ile
       uzunluğu alanla örtüşmeli. Yazarın farklı bir "Özet" bölümü yazdığı
       bir metne dokunulmaz. $ozetler dizi alır: özet bir dilde, çevirisi
       öbüründe durabilir. */
    function tg_metin_tekrar_ayikla(string $metin, $ozetler, string $kaynakca): string {
        if ($metin === '') return $metin;
        $sozcuk = function (string $h): array {
            $t = mb_strtolower(html_entity_decode(strip_tags($h), ENT_QUOTES, 'UTF-8'), 'UTF-8');
            preg_match_all('/[\p{L}\p{N}]+/u', $t, $m);
            return $m[0];
        };
        $ayni = function (array $a, array $b): bool {
            $na = count($a); $nb = count($b);
            if ($na < 8 || $nb < 8) return false;
            $r = $na / $nb;
            if ($r < 0.8 || $r > 1.25) return false;
            $k = min(30, $na, $nb);
            return array_slice($a, 0, $k) === array_slice($b, 0, $k);
        };
        $ozetS = [];
        foreach ((array)$ozetler as $o) { $w = $sozcuk((string)$o); if ($w) $ozetS[] = $w; }
        $kaynS = $sozcuk($kaynakca);
        if (!$ozetS && !$kaynS) return $metin;
        if (!preg_match_all('#<h([1-3])\b[^>]*>(.*?)</h\1>#isu', $metin, $m, PREG_OFFSET_CAPTURE)) return $metin;
        $n = count($m[0]);
        $ozetAd = ['abstract', 'özet', 'öz', 'summary', 'özet abstract', 'abstract özet'];
        $kaynAd = ['references', 'reference list', 'kaynakça', 'kaynaklar', 'bibliography'];
        $sil = [];
        for ($i = 0; $i < $n; $i++) {
            $bas  = $m[0][$i][1];
            $govS = $bas + strlen($m[0][$i][0]);
            $son  = ($i + 1 < $n) ? $m[0][$i + 1][1] : strlen($metin);
            $ad   = trim(preg_replace('/^[\d.\s]+/u', '', implode(' ', $sozcuk($m[2][$i][0]))));
            $govde = $sozcuk(substr($metin, $govS, $son - $govS));
            $at = false;
            if (in_array($ad, $ozetAd, true)) { foreach ($ozetS as $ow) { if ($ayni($govde, $ow)) { $at = true; break; } } }
            elseif (in_array($ad, $kaynAd, true) && $kaynS && $ayni($govde, $kaynS)) { $at = true; }
            if ($at) $sil[] = [$bas, $son];
        }
        for ($j = count($sil) - 1; $j >= 0; $j--) {
            $metin = substr($metin, 0, $sil[$j][0]) . substr($metin, $sil[$j][1]);
        }
        return $metin;
    }

    function tg_zengin(string $s): string {
        $s = trim($s);
        if ($s === '') return '';
        if (!tg_metin_html_mi($s)) {
            return nl2br(htmlspecialchars($s, ENT_QUOTES, 'UTF-8'));
        }
        if (function_exists('guvenli_html')) return guvenli_html($s);
        /* Süzgeç yüklü değilse (kamusal sayfa) en güvenli yol: etiketleri at */
        return nl2br(htmlspecialchars(tg_duz($s), ENT_QUOTES, 'UTF-8'));
    }

    function tg_rapor_nitelik($h): array {
        $eksik = [];
        if (!is_array($h)) return ['yeterli' => false, 'eksik' => ['Rapor yok']];
        $enAz  = (int)tg_ayar('rapor_asgari_karakter', 400);
        $enAzI = (int)tg_ayar('rapor_asgari_isaret', 2);
        /* Uzunluk, etiketlerin değil metnin kendi uzunluğudur. Aksi
           hâlde biçimlendirmek eşiği aşmanın yolu olurdu. */
        $metin = tg_duz((string)($h['rapor'] ?? ''));
        if (mb_strlen($metin, 'UTF-8') < $enAz) $eksik[] = 'gerekce';
        $notlar = is_array($h['notlar'] ?? null) ? $h['notlar'] : [];
        $dolu = 0;
        foreach ($notlar as $n) { if (is_array($n) && trim((string)($n['not'] ?? '')) !== '') $dolu++; }
        if ($dolu < $enAzI) $eksik[] = 'isaret';
        /* Ölçüt değerlendirmesi yalnızca çalışmayı yayımlanabilir bulan
           kararlarda istenir; hakeme de yalnızca o kararlarda sorulur.
           "Ret" ya da "büyük revizyon" diyen bir hakemden, çalışmanın
           hangi dizinde yayımlanabileceğini işaretlemesi beklenemez. */
        $karar = (string)($h['karar'] ?? '');
        if (in_array($karar, ['kabul', 'kucuk'], true) && empty($h['endeks_anket'])) $eksik[] = 'anket';
        return ['yeterli' => $eksik === [], 'eksik' => $eksik];
    }

    function tg_nitelik_metin(string $k, bool $en = false): string {
        $m = [
            'gerekce' => ['Gerekçe çok kısa', 'Reasoning too brief'],
            'isaret'  => ['Metinde işaretlenmiş yer yok', 'No passages marked in the text'],
            'anket'   => ['Ölçüt değerlendirmesi doldurulmamış', 'Criteria assessment not completed'],
            'oylama'  => ['Kurul oylamasıyla geçersiz sayıldı', 'Set aside by a panel vote'],
        ];
        return isset($m[$k]) ? tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]], $en) : $k;
    }

    /* ---------------------------------------------------------------
       OYLAMA VE İTİRAZ DÜZENİ
       ---------------------------------------------------------------
       Açık hakemlikte en kırılgan an, yazar ile hakemin anlaşamadığı
       andır. Kararı tek bir editöre bırakmak, sistemin bütün ağırlığını
       tek kişiye yükler; yazara bırakmak ise hakemliği anlamsız kılar.
       Bu yüzden anlaşmazlık, tarafların hiçbirine değil, üç bağımsız
       kişiye götürülür.

       İki yol vardır:
         itiraz  : Yazar, hakkındaki bir raporun haksız olduğunu düşünür.
         sikayet : Herhangi bir doğrulanmış kişi, bir raporun kötü
                   niyetli ya da savruk olduğunu düşünür.

       Her ikisi de aynı biçimde işler:
         - Üç bağımsız oy toplanır. Oy verenler birbirinin oyunu
           GÖREMEZ; üçüncü oy düşene kadar sayım da gizlidir. Böylece
           ilk oyun sonrakileri sürüklemesi engellenir.
         - Üçüncü oy düştüğü anda oylama kapanır ve HER ŞEY açılır:
           kimin nasıl oy verdiği, gerekçesiyle birlikte yayımlanır.
           Gizlilik karar anına aittir, karardan sonrasına değil.
         - Sonuç kalıcıdır. Silinmez, değiştirilmez.
         - Taraflar oy veremez: yazar, ortak yazarlar, hakkında oy
           verilen hakem ve oylamayı açan kişi kapsam dışıdır.

       Dört geçerli oy kullanan kişi yazarlık hakkını kazanır. Hakemlik
       gibi bu da bir emek karşılığıdır: başkasının işine vakit ayırmadan
       bu sistemde yazar olunmaz.
       --------------------------------------------------------------- */

    function tg_oy_secenekleri(string $tur, bool $en = false): array {
        if ($tur === 'sikayet') {
            return [
                'yersiz'    => tg_c('Şikâyet yersizdir; rapor yerinde durur', 'The complaint is unfounded; the report stands', $en),
                'saymaz'    => tg_c('Rapor geçersiz sayılsın, onay sayımına katılmasın', 'The report is set aside and does not count', $en),
                'askiya_al' => tg_c('Rapor geçersiz sayılsın ve hakemlik yetkisi askıya alınsın', 'The report is set aside and the reviewer is suspended', $en),
            ];
        }
        return [
            'gecerli'    => tg_c('İtiraz yersizdir; rapor yerinde durur', 'The objection is unfounded; the report stands', $en),
            'saymaz'     => tg_c('Rapor geçersiz sayılsın, onay sayımına katılmasın', 'The report is set aside and does not count', $en),
            'yeni_hakem' => tg_c('Rapor dursun, ancak yeni bir hakem aransın', 'The report stands but a further reviewer is needed', $en),
        ];
    }

    function tg_oy_metin(string $tur, string $karar, bool $en = false): string {
        $s = tg_oy_secenekleri($tur, $en);
        return $s[$karar] ?? $karar;
    }

    function tg_oylama_tur_ad(string $tur, bool $en = false): string {
        $m = [
            'itiraz'  => ['Yazar itirazı', 'Objection by the author'],
            'sikayet' => ['Rapor şikâyeti', 'Complaint about a report'],
        ];
        return isset($m[$tur]) ? tg_t(['tr' => $m[$tur][0], 'en' => $m[$tur][1]], $en) : $tur;
    }

    /* Bir çalışmadaki bütün oylamalar, eskiden yeniye */
    function tg_oylamalar(array $y): array {
        $o = is_array($y['oylamalar'] ?? null) ? $y['oylamalar'] : [];
        $out = [];
        foreach ($o as $e) { if (is_array($e) && trim((string)($e['kod'] ?? '')) !== '') $out[] = $e; }
        usort($out, fn($a, $b) => strcmp((string)($a['tarih'] ?? ''), (string)($b['tarih'] ?? '')));
        return $out;
    }

    function tg_oy_gerek(): int { return max(1, (int)tg_ayar('oy_gerekli', 3)); }

    /* Oylamanın sayımı ve sonucu. Üçüncü oy düşene kadar sayım gizlidir. */
    function tg_oylama_sonuc(array $ov): array {
        $oylar = is_array($ov['oylar'] ?? null) ? $ov['oylar'] : [];
        $gerek = tg_oy_gerek();
        $sayim = [];
        foreach ($oylar as $o) {
            if (!is_array($o)) continue;
            $k = (string)($o['karar'] ?? '');
            if ($k === '') continue;
            $sayim[$k] = ($sayim[$k] ?? 0) + 1;
        }
        $kapali = count($oylar) >= $gerek;
        $karar = '';
        if ($kapali && $sayim) {
            arsort($sayim);
            $karar = (string)array_key_first($sayim);
            /* Beraberlik: ağır sonuç değil hafif sonuç kazanır. Bir
               kaydı geçersiz saymak için açık bir çoğunluk aranır. */
            $enCok = $sayim[$karar];
            $berabere = array_keys(array_filter($sayim, fn($v) => $v === $enCok));
            if (count($berabere) > 1) {
                $tur = (string)($ov['tur'] ?? 'itiraz');
                $hafif = $tur === 'sikayet' ? 'yersiz' : 'gecerli';
                $karar = in_array($hafif, $berabere, true) ? $hafif : $berabere[0];
            }
        }
        return [
            'kapali' => $kapali,
            'oy_sayisi' => count($oylar),
            'gerek' => $gerek,
            'karar' => $karar,
            'sayim' => $kapali ? $sayim : [],   /* açılmadan sayım verilmez */
        ];
    }

    /* Bir hakem raporu oylamayla geçersiz sayıldı mı? */
    function tg_rapor_oylama_saymaz(array $y, $h): bool {
        if (!is_array($h)) return false;
        $ad = tg_ad_anahtar((string)($h['ad'] ?? ''));
        if ($ad === '') return false;
        foreach (tg_oylamalar($y) as $ov) {
            if (tg_ad_anahtar((string)($ov['hedef_ad'] ?? '')) !== $ad) continue;
            $s = tg_oylama_sonuc($ov);
            if (!$s['kapali']) continue;
            if ($s['karar'] === 'saymaz' || $s['karar'] === 'askiya_al') return true;
        }
        return false;
    }

    /* Oylamayla yeni hakem istendi mi? */
    function tg_oylama_yeni_hakem(array $y): bool {
        foreach (tg_oylamalar($y) as $ov) {
            $s = tg_oylama_sonuc($ov);
            if ($s['kapali'] && $s['karar'] === 'yeni_hakem') return true;
        }
        return false;
    }

    /* Bu çalışmada açık (henüz sonuçlanmamış) oylama var mı? */
    function tg_acik_oylama_sayisi(array $y): int {
        $n = 0;
        foreach (tg_oylamalar($y) as $ov) { if (!tg_oylama_sonuc($ov)['kapali']) $n++; }
        return $n;
    }

    /* Bu kişi bu oylamada oy verebilir mi? Taraflar dışarıdadır. */
    function tg_oy_verebilir(array $y, array $ov, array $hesap): array {
        $mail = mb_strtolower(trim((string)($hesap['eposta'] ?? '')), 'UTF-8');
        $ad   = tg_ad_anahtar((string)($hesap['ad'] ?? ''));

        if (tg_oylama_sonuc($ov)['kapali']) return ['olur' => false, 'neden' => 'kapandi'];
        if (!empty($hesap['hakemlik_askida']))  return ['olur' => false, 'neden' => 'askida'];
        if (tg_yazar_mi($y, $hesap))            return ['olur' => false, 'neden' => 'yazar'];
        if ($ad !== '' && tg_ad_anahtar((string)($ov['hedef_ad'] ?? '')) === $ad) {
            return ['olur' => false, 'neden' => 'hedef'];
        }
        if ($mail !== '' && mb_strtolower((string)($ov['acan_eposta'] ?? ''), 'UTF-8') === $mail) {
            return ['olur' => false, 'neden' => 'acan'];
        }
        foreach (tg_dizi($ov['oylar'] ?? null) as $o) {
            if (!is_array($o)) continue;
            if ($mail !== '' && mb_strtolower((string)($o['eposta'] ?? ''), 'UTF-8') === $mail) {
                return ['olur' => false, 'neden' => 'verdi'];
            }
        }
        return ['olur' => true, 'neden' => ''];
    }

    function tg_oy_engel_metin(string $neden, bool $en = false): string {
        $m = [
            'kapandi' => ['Bu oylama sonuçlandı', 'This vote has concluded'],
            'askida'  => ['Hakemlik yetkiniz askıya alınmış', 'Your reviewing privilege is suspended'],
            'yazar'   => ['Bu çalışmanın yazarısınız', 'You are an author of this work'],
            'hedef'   => ['Oylama sizin raporunuz hakkında', 'The vote concerns your own report'],
            'acan'    => ['Bu oylamayı siz açtınız', 'You opened this vote'],
            'verdi'   => ['Oyunuzu kullandınız', 'You have already voted'],
            'belge'   => ['Hesabınız henüz doğrulanmadı', 'Your account is not yet verified'],
            'giris'   => ['Oy vermek için giriş yapın', 'Sign in to vote'],
        ];
        return isset($m[$neden]) ? tg_t(['tr' => $m[$neden][0], 'en' => $m[$neden][1]], $en) : '';
    }

    /* ---------------------------------------------------------------
       Onay durumu. "Hakem onaylı" için iki olumlu rapor yetmez:
       olumlu raporlardan en az biri yazarın önermediği bir hakemden
       gelmelidir ve raporlar nitelik eşiğini karşılamalıdır.
       --------------------------------------------------------------- */
    function tg_onay_durumu(array $y): array {
        $esik = (int)tg_ayar('kabul_gecerli', 2);
        $bagimsizSart = (bool)tg_ayar('bagimsiz_hakem_zorunlu', true);
        $kabul = 0; $bagimsizKabul = 0; $ret = 0; $niteliksiz = 0;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $karar = (string)($h['karar'] ?? '');
            /* Kurul oylamasıyla geçersiz sayılan rapor hiçbir sayıma girmez:
               ne onaya, ne rette. Ama sayfada durmayı sürdürür. */
            if (tg_rapor_oylama_saymaz($y, $h)) { $niteliksiz++; continue; }
            if ($karar === 'ret') { $ret++; continue; }
            if ($karar !== 'kabul') continue;
            if (!tg_rapor_nitelik($h)['yeterli']) { $niteliksiz++; continue; }
            $kabul++;
            if (tg_hakem_bagimsiz($h)) $bagimsizKabul++;
        }
        $onayli = ($kabul >= $esik) && (!$bagimsizSart || $bagimsizKabul >= 1);
        $neden = '';
        if (!$onayli) {
            if ($kabul < $esik) $neden = 'sayi';
            elseif ($bagimsizSart && $bagimsizKabul < 1) $neden = 'bagimsiz';
        }
        return [
            'onayli' => $onayli, 'kabul' => $kabul, 'bagimsiz_kabul' => $bagimsizKabul,
            'ret' => $ret, 'niteliksiz' => $niteliksiz, 'esik' => $esik, 'neden' => $neden,
        ];
    }

    /* ---------------------------------------------------------------
       ÇALIŞMANIN TARİHÇESİ

       Akademik bir kayıtta üç tarih beklenir: gönderim, ilk
       değerlendirmeye alınış, kabul. Bunların hepsi kayıtta yoktur ve
       olmayanı hesaplamaya çalışmak yanlış bir tarih üretir. Bu yüzden:

         gonderim   Kayıtta ayrı bir alandır. Eski kayıtlarda yoktur;
                    yoksa boş döner ve hiçbir yerde yazılmaz.
         ilk        Bir hakemin çalışmaya ilk atandığı an. "İlk
                    değerlendirmeye alındığı tarih" budur ve atama
                    kaydından okunur, uydurulmaz.
         kabul      Onay eşiğini dolduran raporun geldiği an, yani
                    çalışmanın hakem onaylı hâle geldiği an. Sayıma giren
                    olumlu raporlar tarihe göre sıralanır, eşiğinci
                    olanın tarihi alınır.
         yayin      Kayıttaki yayın tarihi.

       Bilinmeyen bir tarihi boş bırakmak, bilir gibi yazmaktan iyidir.
       --------------------------------------------------------------- */
    function tg_yazi_tarihleri(array $y): array {
        $out = ['gonderim' => '', 'ilk' => '', 'kabul' => '', 'yayin' => ''];

        $g = trim(tg_metin($y['gonderim'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $g)) $out['gonderim'] = substr($g, 0, 10);

        $t = trim(tg_metin($y['tarih'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $t)) $out['yayin'] = substr($t, 0, 10);

        $ilk = ''; $olumlu = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h)) continue;
            $at = trim(tg_metin($h['atayan']['tarih'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $at)) {
                $at = substr($at, 0, 10);
                if ($ilk === '' || $at < $ilk) $ilk = $at;
            }
            if (trim((string)($h['rapor'] ?? '')) === '') continue;
            if ((string)($h['karar'] ?? '') !== 'kabul') continue;
            if (tg_rapor_oylama_saymaz($y, $h)) continue;
            if (!tg_rapor_nitelik($h)['yeterli']) continue;
            $rt = trim(tg_metin($h['tarih'] ?? ''));
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $rt)) $olumlu[] = substr($rt, 0, 10);
        }
        $out['ilk'] = $ilk;

        $esik = (int)tg_ayar('kabul_gecerli', 2);
        sort($olumlu);
        if ($esik > 0 && count($olumlu) >= $esik) $out['kabul'] = $olumlu[$esik - 1];

        return $out;
    }

    /* Kabul veren hakemler: sayıma giren olumlu raporların sahipleri.
       Kapakta adları ve rapor adresleri bununla yazılır. */
    function tg_kabul_edenler(array $y): array {
        $out = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h)) continue;
            if (trim((string)($h['rapor'] ?? '')) === '') continue;
            if ((string)($h['karar'] ?? '') !== 'kabul') continue;
            if (tg_rapor_oylama_saymaz($y, $h)) continue;
            if (!tg_rapor_nitelik($h)['yeterli']) continue;
            $out[] = [
                'ad'       => trim(tg_metin($h['ad'] ?? '')),
                'tarih'    => substr(trim(tg_metin($h['tarih'] ?? '')), 0, 10),
                'bagimsiz' => tg_hakem_bagimsiz($h),
            ];
        }
        usort($out, fn($a, $b) => strcmp((string)$a['tarih'], (string)$b['tarih']));
        return $out;
    }

    function tg_onay_neden_metin(string $k, bool $en = false): string {
        $m = [
            'sayi' => [
                'Henüz yeterli sayıda olumlu rapor yok.',
                'Not enough positive reports yet.',
            ],
            'bagimsiz' => [
                'Olumlu raporların tümü yazarın önerdiği hakemlerden geliyor. Bu çalışmanın hakem onaylı sayılabilmesi için, yazarın önermediği bir hakemden de olumlu rapor gerekir.',
                'All positive reports come from reviewers the author proposed. For this work to count as reviewer approved, a positive report from a reviewer not proposed by the author is also required.',
            ],
        ];
        return isset($m[$k]) ? tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]], $en) : '';
    }

    /* ---------------------------------------------------------------
       HAKEMLİK KOŞULLARI
       Hakemlik kapısı herkese açıktır ama koşulsuz değildir. Aranan tek
       nitelik doktora ya da eşdeğeri bir dereceye sahip olmaktır ve bu,
       beyanla değil belgeyle kanıtlanır:
         Türkiye  : e-Devlet'ten alınmış barkodlu belgenin doğrulama kodu.
         Yurt dışı: belgenin sorgulanabileceği KAMUSAL bir alan adı.
                    Kişisel site, blog ya da ticari alan adı kabul edilmez.
       Geçiş dönemi 31 Aralık 2027'ye kadar sürer; bu tarihe kadar belge
       sonradan da tamamlanabilir, sonrasında zorunludur.
       --------------------------------------------------------------- */

    /* Kamusal doğrulama alan adı mı? Yanıltıcı adresler elenir. */
    function tg_kamu_alan(string $url): bool {
        $url = trim($url);
        if ($url === '') return false;
        if (!preg_match('#^https?://#i', $url)) $url = 'https://' . $url;
        $h = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($h === '') return false;
        $h = preg_replace('/^www\./', '', $h);
        $ekler = [
            '.gov', '.edu', '.mil', '.int',
            '.gov.tr', '.edu.tr', '.tsk.tr', '.pol.tr', '.k12.tr', '.av.tr', '.bel.tr',
            '.ac.uk', '.gov.uk', '.sch.uk',
            '.edu.au', '.gov.au', '.edu.cn', '.gov.cn', '.ac.jp', '.go.jp', '.ac.kr', '.go.kr',
            '.edu.in', '.gov.in', '.ac.in', '.edu.pk', '.gov.pk', '.edu.br', '.gov.br',
            '.ac.at', '.ac.be', '.ac.il', '.ac.nz', '.ac.za', '.ac.ir', '.ac.id', '.ac.th',
            '.edu.sa', '.gov.sa', '.edu.eg', '.gov.eg', '.edu.my', '.gov.my',
            '.gouv.fr', '.gob.es', '.gob.mx', '.gov.it', '.gov.pl', '.gov.gr',
        ];
        foreach ($ekler as $e) { if (str_ends_with($h, $e)) return true; }
        /* Almanya, Fransa, Hollanda gibi ülkelerde üniversiteler doğrudan ulusal
           uzantı kullanır; bu durumda alan adının kurumsal olduğu elle görülür. */
        $ulusalKurum = ['uni-', 'univ-', 'universite', 'universitat', 'universiteit', 'univerzita'];
        foreach ($ulusalKurum as $u) { if (strpos($h, $u) === 0) return true; }
        return false;
    }

    /* tg_edevlet_kod() SİLİNDİ — 19 Ağustos 2026.
       e-Devlet barkod kodunun biçimini denetliyordu. O yol 14 Ağustos
       kurul kararıyla bütünüyle kaldırıldı (doktorayı bir editör adıyla
       doğrular) ve işlev o günden beri HİÇBİR YERDEN çağrılmıyordu.

       ÇAĞRILMAYAN BİR İŞLEV ZARARSIZ DEĞİLDİR: kaldırılmış bir kuralın
       kodda durması, bir sonraki okuyucuya o kuralın hâlâ yürürlükte
       olduğunu düşündürür ve birinin onu yeniden çağırmasıyla kural
       sessizce geri gelir. tg_kamu_alan() ise SİLİNMEDİ; o bir kapı
       değil, editöre kararında yardımcı olan bir işarettir ve
       çağrılmayı sürdürür. */

    /* Geçiş dönemi sürüyor mu? */
    function tg_gecis_suruyor(): bool {
        $son = (string)tg_ayar('gecis_sonu', '2027-12-31');
        return date('Y-m-d') <= $son;
    }

    /* =================================================================
       DOKTORA DOĞRULAMASI: TEK STANDART, ADIYLA SORUMLULUK ÜSTLENEN
       BİR EDİTÖR
       -----------------------------------------------------------------
       14 Ağustos 2026 kurul kararı. Önceki düzen ülkeye göre şekilliydi
       ve ilan ettiği şeyi yapmıyordu:

         - Türkiye'de e-Devlet barkod kodu isteniyordu. Yurt dışındaki
           hiçbir araştırmacının böyle bir kodu yok; sistem hakemlerinin
           yalnız bir ülkeden gelmeyeceğini bilerek o ülkeye özel bir
           kapı kurmuştu. "Ortak zeminde bütün milletler kardeştir"
           diyen bir sistemde bu tek başına bir çelişkidir.
         - Yurt dışı için kamusal alan adı (gov, edu, ac.uk...) şartı
           vardı ve makinede uygulanıyordu (tg_kamu_alan). Ama alan adı
           doktorayı göstermez; sayfaya bakıp karar veren yine bir
           EDİTÖRDÜ.
         - Yani her iki yolda da gerçek denetim aynıydı: bir insanın
           bakıp karar vermesi. Sistem ise ekranda "doktora belgesi
           doğrulandı" diyordu — yapılmayan bir işi duyuruyordu.

       Yeni kural tek cümledir: DOKTORAYI BİR EDİTÖR ADIYLA DOĞRULAR.
       Üç yol, tek ölçüt:

         1. DAVET. Editör ya da baş editör birini hakem olarak davet
            ettiyse, o kişinin doktoralı olduğunu daveti gönderen zaten
            bilerek davet etmiştir. Ayrıca belge istemek, editörün kendi
            kararına güvenmemek olurdu.
         2. BAŞVURU. Kişi kendisi hakem olmak istediyse, o çalışmanın
            editörü ya da bir baş editör onaylar.
         3. HİÇBİRİ. Kimse adıyla arkasında durmadıysa kişi aday hakem
            olarak kalır ve rapor yazamaz.

       Belge İSTENMEZ ve SAKLANMAZ. ORCID eğitim kaydı ve kamusal kurum
       sayfası duruyor ama artık KAPI değil, editöre gösterilen destektir:
       birini reddetmezler, karara yardım ederler.

       Kayıtta kimin doğruladığı YAZAR ve ekranda görünür. Bu, sistemin
       başka her yerinde uyguladığı kuralın aynısıdır: hakem raporunun
       arkasında adıyla durur, destekleyen araştırmacı adıyla durur,
       kararı veren adıyla durur. Doğrulama da öyle olur.
       ================================================================= */
    function tg_dogrulama_editor(string $onaylayanAd, string $yol = 'davet'): array {
        return [
            'tur'        => 'editor',
            'onay'       => true,
            'durum'      => 'onayli',
            'yol'        => in_array($yol, ['davet', 'basvuru', 'atama'], true) ? $yol : 'davet',
            'onaylayan'  => mb_substr(trim($onaylayanAd), 0, 120),
            'tarih'      => date('c'),
            'onay_tarih' => date('c'),
        ];
    }

    /* Doğrulamanın arkasında duran kişi. Boş dönerse kayıt eski
       düzenden kalmadır (belge yolu) ya da henüz onaylanmamıştır. */
    function tg_dogrulama_onaylayan($d): string {
        return is_array($d) ? trim((string)($d['onaylayan'] ?? '')) : '';
    }

    /* Bir doğrulama kaydı hakemlik için yeterli mi?
       Dönen: ['yeterli'=>bool, 'durum'=>'onayli|bekliyor|eksik', 'neden'=>...] */
    function tg_dogrulama_durum($d): array {
        if (!is_array($d) || ($d['tur'] ?? '') === '') {
            return ['yeterli' => tg_gecis_suruyor(), 'durum' => 'eksik', 'neden' => 'belge_yok'];
        }
        if (!empty($d['onay'])) return ['yeterli' => true, 'durum' => 'onayli', 'neden' => ''];
        return ['yeterli' => tg_gecis_suruyor(), 'durum' => 'bekliyor', 'neden' => 'sorgulaniyor'];
    }

    /* METİN OLANI SÖYLER. Eskiden "Doktora belgesi doğrulandı" yazıyordu;
       oysa doğrulanan bir belge değil, bir editörün adıyla verdiği
       karardı. Belge hiç istenmiyor ve saklanmıyor — o cümle sistemin
       yapmadığı bir işi duyuruyordu. */
    function tg_dogrulama_metin(string $durum, bool $en = false): string {
        $m = [
            'onayli'   => ['Doktorası bir editör tarafından doğrulandı', 'The doctorate was confirmed by an editor'],
            'bekliyor' => ['Bir editörün doğrulaması bekleniyor', 'Awaiting confirmation by an editor'],
            'eksik'    => ['Doktorasını henüz kimse doğrulamadı', 'No one has confirmed the doctorate yet'],
        ];
        return isset($m[$durum]) ? tg_t(['tr' => $m[$durum][0], 'en' => $m[$durum][1]], $en) : '';
    }

    /* ---------------------------------------------------------------
       METİN KİLİDİ
       Bir çalışma değerlendirilmeye başlandıktan sonra metnin sessizce
       değişebiliyor olması, hem bilimsel kaydı hem de hakemin emeğini
       geçersiz kılar. Ayrıca yazarın erişim şifresi çalınırsa, kötü
       niyetli biri yayımlanmış bir çalışmanın içeriğini değiştirebilir.
       Bu yüzden metin üç durumdan birinde bulunur:
         acik     : henüz rapor gelmemiş, yazar serbestçe düzenler
         revizyon : bir hakem revizyon istemiş, yalnızca o kapı açık
         kilitli  : rapor tamamlanmış ya da çalışma onaylanmış, metin
                    yazar tarafından değiştirilemez; düzeltme ancak
                    editöre başvurularak, kayda geçerek yapılır
       Kilit her durumda yazarın KENDİ profilini düzenlemesini engellemez.
       --------------------------------------------------------------- */
    function tg_kilit_durum(array $y): string {
        $tamamlanan = 0; $revizyon = false;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $k = (string)($h['karar'] ?? '');
            if ($k === 'buyuk' || $k === 'kucuk') $revizyon = true;
            if (!empty($h['kapali']) || $k === 'kabul' || $k === 'ret') $tamamlanan++;
        }
        if (tg_onay_durumu($y)['onayli']) return 'kilitli';
        if ($tamamlanan > 0 && !$revizyon) return 'kilitli';
        if ($revizyon) return 'revizyon';
        return 'acik';
    }

    function tg_kilit_metin(string $durum, bool $en = false): string {
        $m = [
            'acik'     => ['Metin düzenlenebilir', 'The text can be edited'],
            'revizyon' => ['Hakem revizyon istedi: düzeltilmiş metni yükleyebilirsiniz', 'A reviewer asked for revision: you may upload the corrected text'],
            'kilitli'  => ['Metin kilitli: değerlendirme tamamlandığı için içerik değiştirilemez', 'The text is locked: the assessment is complete, so the content cannot be changed'],
        ];
        return isset($m[$durum]) ? tg_t(['tr' => $m[$durum][0], 'en' => $m[$durum][1]], $en) : '';
    }

    /* Metnin parmak izi: sonradan değiştirilip değiştirilmediği kanıtlanabilir */
    function tg_metin_ozeti(array $y): string {
        $p = (string)($y['baslik'] ?? '') . "\n" . (string)($y['ozet'] ?? '') . "\n"
           . (string)($y['metin'] ?? '') . "\n" . (string)($y['kaynakca'] ?? '');
        return substr(hash('sha256', $p), 0, 32);
    }

    /* ---------------------------------------------------------------
       ÇEVİRİNİN PARMAK İZİ

       tg_metin_ozeti() yalnızca baslik/ozet/metin/kaynakca alanlarını
       özetler. Sayfa ise ?lang=en verildiğinde baslik_en/ozet_en/
       metin_en alanlarını basar. İkisi ayrı düştüğü için İngilizce
       sayfada okur, OKUMADIĞI bir metnin özetini doğruluyordu:
       çeviri sessizce değiştirilebiliyor ve değer yine "tutuyor"
       diyordu. Ölçüldü, üç bayrakla kayda geçti, burada kapandı.

       Neden ayrı bir değer, neden tg_metin_ozeti() genişletilmedi:
       o işlevin döndürdüğü sayı arşiv dökümünde YAYIMLANDI ve
       başkalarının elinde olabilir. Genişletmek, metni hiç değişmemiş
       bir çalışmanın değerini de değiştirirdi; elinde eski sayıyı
       tutan okur olmayan bir değişikliği görmüş olurdu. Bir güven
       aracının verebileceği en kötü yanıt budur.

       Kapsam, sayfanın kendi kuralıdır (yazi.php'deki alan()): dil
       karşılığı doluysa o, boşsa taban alan. Böylece değer, okurun
       o sayfada gerçekten gördüğü metnin özetidir. Çevirisi olmayan
       bir çalışmada iki değer eşit çıkar ve ikinci satır basılmaz.
       --------------------------------------------------------------- */
    function tg_dil_alani(array $y, string $ad, bool $en): string {
        if ($en) {
            $v = trim(tg_metin($y[$ad . '_en'] ?? ''));
            if ($v !== '') return $v;
        }
        return tg_metin($y[$ad] ?? '');
    }
    function tg_metin_ozeti_dil(array $y, bool $en): string {
        if (!$en) return tg_metin_ozeti($y);
        $p = tg_dil_alani($y, 'baslik', true) . "\n" . tg_dil_alani($y, 'ozet', true) . "\n"
           . tg_dil_alani($y, 'metin', true) . "\n" . tg_dil_alani($y, 'kaynakca', true);
        return substr(hash('sha256', $p), 0, 32);
    }
    /* Çevirinin ayrı bir özeti var mı: yoksa okura ikinci bir sayı
       göstermenin anlamı yok, gösterilirse gürültü olur. */
    function tg_ceviri_ozeti(array $y): string {
        $c = tg_metin_ozeti_dil($y, true);
        return $c === tg_metin_ozeti($y) ? '' : $c;
    }

    /* ---------------------------------------------------------------
       Bu çalışmaya hakem aranıyor mu?
       Yazar çalışmasını yüklediğinde, isteyen herkes hakemliğe
       gönüllü olabilir. Gönüllülük yazarın seçimi olmadığı için
       bağımsız sayılır; başvuru editör onayından geçer.
       --------------------------------------------------------------- */
    function tg_hakem_araniyor(array $y): bool {
        if ((string)($y['tur'] ?? '') !== 'hakemli') return false;
        if (tg_geri_cekildi($y)) return false;
        $tam = 0; $ret = 0;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $tam++;
            if ((string)($h['karar'] ?? '') === 'ret') $ret++;
        }
        if ($ret >= (int)tg_ayar('ret_donusum', 2)) return false;   /* süreç kapandı */
        /* Kurul "yeni hakem aransın" dediyse hedef sayıya bakılmaz */
        if (tg_oylama_yeni_hakem($y)) return true;
        return $tam < (int)tg_ayar('hakem_hedef', 3);
    }

    /* ---------------------------------------------------------------
       HAKEMLİK AŞAMASI

       'tur' alanı çalışmanın hangi YOLDA olduğunu söyler, o yolun
       neresinde olduğunu değil. Yazar çalışmasını hakemliğe açtığı
       anda tur='hakemli' yazılır; tek bir rapor bile gelmemiştir.
       Etiketi doğrudan bu alandan üreten her yer, o çalışmayı hakem
       değerlendirmesinden geçmiş gibi gösterir. Okura verilmiş yanlış
       bir sözdür ve bu sistemin en temel iddiasını aşındırır: burada
       hakemlik görünür olduğu için güvenilir sayılıyor.

       Aşama tek yerden hesaplanır ki rozet, sayaç, süzgeç ve dışarıya
       verilen üstveri aynı şeyi söylesin.

         cekildi   geri çekilmiş
         yok       hakemsiz yol
         aranan    hakemli yolda, tamamlanmış rapor YOK
         suruyor   en az bir rapor var, onay eşiği dolmadı
         onayli    onay eşiği doldu

       Aşama hesaplanır, saklanmaz. Saklanan bir alan raporla birlikte
       güncellenmeyi unutabilir; hesaplanan bir değer unutamaz.
       --------------------------------------------------------------- */
    /* =================================================================
       KABUL EDİLEN ÇALIŞMA HANGİ YOLA GİRER
       -----------------------------------------------------------------
       ÖLÇÜLEN KUSUR — 15 Ağustos 2026. Kural İKİ YERDE yazılıydı
       (basvuru.php'nin gönderim koşulları ve tg_yazarlik_kosulu_metni)
       ve ikisi de aynı şeyi diyordu:

         "Kabul edilen çalışma ÖNCE HAKEMSİZ olarak yayımlanır; yazar
          dilerse hakem aranmasını ister, dilerse hakemsiz bırakır."

       Kod ise tam tersini yapıyordu: /yonetim/basvuru-karar ucunda
       'tur' => 'hakemli' ELLE yazılıydı ve kabul edilen her çalışma
       hakemli yola giriyor, sayfasında "Hakem aranıyor" rozeti
       beliriyordu. Yani sistem, uygulamadığı bir kuralı duyuruyordu —
       bu depoda on dördüncü kez.

       Yol artık BURADAN okunur. Metin de buradan okunmalı ki ikisi bir
       daha ayrışmasın: bir gün kurul yolu değiştirirse tek satır
       değişir ve hem ekran hem davranış birlikte döner.

       Doktora şartı AÇIKKEN yol hakemlidir: o düzende yayımlanmanın
       koşulu değerlendirmeden geçmektir. Şart KAPALIYKEN (bugünkü
       hâl) çalışma hakemsiz yayımlanır ve hakemlik yazarın kendi
       kararına kalır — açma düğmesi zaten panelinde duruyor
       (/yazar-hakemlige-ac).
       ================================================================= */
    function tg_kabul_yolu(): string {
        return tg_yazarlik_doktora_sarti() ? 'hakemli' : 'yazi';
    }

    function tg_rapor_sayisi(array $y): int {
        $n = 0;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (is_array($h) && trim((string)($h['rapor'] ?? '')) !== '') $n++;
        }
        return $n;
    }

    /* ---------------------------------------------------------------
       HAKEMLİ YOLU GÖRMÜŞ MÜ

       ÖLÇÜLEN KUSUR — 19 Ağustos 2026, deneme akışı kapısı. İki ret
       kararı gelince /hakem-rapor ucu çalışmanın turunu 'yazi' yapıyor
       (ret_donusum) ve kayda bir düşme kaydı yazıyor. Bundan sonra
       'tur' alanına bakan HER yer o çalışmayı hiç hakem görmemiş bir
       yazı sanıyordu:

         - makale sayfası hakem raporlarını hiç basmıyordu,
         - üstelik "Bu, bağımsız bir yazıdır ve hakem değerlendirmesinden
           geçmemiştir" bandını basıyordu — iki hakemin adıyla imzalayıp
           reddettiği bir metnin başında,
         - tg_hakem_asamasi() 'yok' diyordu; 'reddedildi' basamağı ve
           onun "Hakemler reddetti" rozeti ERİŞİLEMEZ koddu, çünkü eşik
           dolduğu anda tur zaten değişmiş oluyordu,
         - tg_ret_turu() boş dönüyordu: hakem reddi hiçbir sayımda
           görünmüyordu,
         - dış üstveride (OAI setleri) çalışma hakemsiz sayılıyordu.

       Oysa sistemin kendi yazdığı kural şudur (hakemlik.php):
       "ret alan çalışma silinmez; hakemsiz yazıya döner ve ALDIĞI
       RAPORLARLA BİRLİKTE AÇIK KALIR." Yazara gösterilen kilit iletisi
       de aynı sözü verir: "Makale, ret gerekçeleriyle yayında kalır."
       Sistem, uygulamadığı bir kuralı duyuruyordu — bu depoda otuz
       ikinci kez. Kötü haberin sayfadan düşmesi, bu depoda en pahalı
       kusur türüdür: silinmiş gibi görünen bir ret, hiç verilmemiş bir
       ret ile aynı şeye benzer.

       İKİ AYRI SORU AYRILDI:
         tg_hakem_yolunda()     bu çalışma ŞU AN hakemli yolda mı
                                (yeni hakem aranır mı, aşama gösterilir
                                mi — süreçle ilgili her şey buna bakar)
         tg_hakem_yolu_gormus() bu çalışma hakemli yolu GÖRDÜ mü
                                (raporlar, gerekçeler, ret bandı, karar
                                geçmişi — kayıtla ilgili her şey buna)

       Düşme kaydı yoksa da eşiği dolduran ret varken yol 'yazi' ise
       aynı sayılır: elle düzeltilmiş ya da düşme alanı eklenmeden önce
       yazılmış kayıtlar da geçmişini korusun. */
    function tg_hakem_yolunda(array $y): bool {
        return (string)($y['tur'] ?? '') === 'hakemli';
    }
    function tg_hakemlikten_dusmus(array $y): bool {
        if (tg_hakem_yolunda($y)) return false;
        if (is_array($y['ret_donusum'] ?? null)) return true;
        return tg_rapor_sayisi($y) > 0 && tg_ret_esigi_doldu($y);
    }
    function tg_hakem_yolu_gormus(array $y): bool {
        return tg_hakem_yolunda($y) || tg_hakemlikten_dusmus($y);
    }

    function tg_hakem_asamasi(array $y): string {
        if (tg_geri_cekildi($y)) return 'cekildi';
        if (!tg_hakem_yolu_gormus($y)) return 'yok';
        if (tg_rapor_sayisi($y) === 0) return 'aranan';
        /* REDDEDİLDİ, 'suruyor'dan ÖNCE sorulur.

           Bu aşama eskiden yoktu ve iki hakemin reddettiği bir çalışma
           'suruyor' sayılıyordu; yani rozeti "Değerlendirmede" diyordu.
           Oysa süreç kapanmıştı: tg_hakem_araniyor() aynı eşiği görüp o
           çalışmaya çoktan hakem aramayı bırakıyordu. Sistem, kapattığı
           bir süreci okura sürüyormuş gibi gösteriyordu.

           Eşik tek yerden okunur (ret_donusum) ki aşama ile hakem
           arama ölçütü birbirinden ayrı düşmesin.

           Ret, onaydan da önce gelir: eşiği dolduran ret varsa çalışma
           reddedilmiştir, kaç kabul aldığına bakılmaz. */
        if (tg_ret_esigi_doldu($y)) return 'reddedildi';
        return tg_onay_durumu($y)['onayli'] ? 'onayli' : 'suruyor';
    }

    /* Eşiği dolduran ret var mı. tg_hakem_araniyor() ile AYNI sayımı
       kullanır; ikisi ayrı yazılırsa biri değişince öteki eski kalır. */
    function tg_ret_esigi_doldu(array $y): bool {
        $ret = 0;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            if ((string)($h['karar'] ?? '') === 'ret') $ret++;
        }
        return $ret >= (int)tg_ayar('ret_donusum', 2);
    }

    /* ---------------------------------------------------------------
       RET TÜRÜ

       "Ret" bu sistemde iki bambaşka olayı anlatıyordu:

         MASA REDDİ   Başvuru hiç yayımlanmadan geri çevrilir. Ortada
                      çalışma yoktur; kayıt basvurular.json içinde
                      durur. Hakemlikle ilgisi yoktur.
         HAKEM REDDİ  Çalışma yayımlanmıştır, okunabilir, raporlar
                      ortadadır ve hakemler reddetmiştir. Metin
                      SİLİNMEZ; gerekçesiyle açıkta durmayı sürdürür.

       İkisini tek sözcükle anmak ikisini de yanlış anlatır. "Ret oranı"
       diye bir sayı verilecekse hangisinin sayıldığı yazılmalıdır.
       --------------------------------------------------------------- */
    function tg_ret_turu(array $y): string {
        /* Düşmüş çalışma da hakem reddidir: eşiği dolduran ret, turu
           değiştirdiği için sayımdan düşemez. */
        if (!tg_hakem_yolu_gormus($y)) return '';
        return tg_ret_esigi_doldu($y) ? 'hakem' : '';
    }

    function tg_ret_metni(string $tur, bool $en = false): string {
        $m = [
            'masa'  => ['Masa reddi: başvuru, hiç yayımlanmadan geri çevrilmiştir. Ortada bir çalışma yoktur ve hakemlik yapılmamıştır.',
                        'Desk rejection: the submission was turned down without ever being published. There is no work and no review took place.'],
            'hakem' => ['Hakem reddi: çalışma yayımlanmıştır ve yayımda kalır; hakemler onu reddetmiştir ve gerekçeleri açıkta durur. Ret, metnin kaldırılması demek değildir.',
                        'Reviewer rejection: the work is published and stays published; reviewers rejected it and their reasons remain in the open. Rejection does not mean the text is taken down.'],
        ];
        return isset($m[$tur]) ? tg_t(['tr' => $m[$tur][0], 'en' => $m[$tur][1]], $en) : '';
    }

    /* KOŞULLARI DAHA ÖNCE OKUDU MU?

       Bildirilen istek: "kayıtlı kullanıcı her çalışma göndereceğinde
       koşulları baştan görmese olmaz mı." Olur — ama iki şartla:

         1. Atlanan şey OKUMA'dır, BEYAN değil. Koşulları karşıladığını
            her başvuruda yeniden bildirir; o kutu son adımda durur ve
            hiçbir zaman kendiliğinden işaretlenmez.
         2. Koşullar DEĞİŞMEMİŞ olmalı. Değiştiyse "zaten okumuştu"
            demek, okumadığı bir metni okumuş saymaktır. Bu yüzden her
            başvuru, o an ekranda duran koşul metninin SÜRÜMÜNÜ de
            kaydeder; sürüm tutmuyorsa adım yine gösterilir.

       Sürüm elle artırılmaz: metnin kendisinden üretilir (bkz.
       basvuru.php, $kosulSurum). Elle artırılan bir sayı unutulur. */
    function tg_kosul_okundu_mu(string $eposta, string $surum): array {
        $eposta = trim(mb_strtolower($eposta));
        if ($eposta === '' || $surum === '') return ['okundu' => false, 'tarih' => ''];
        $yol = tg_veri_dizini() . '/basvurular.json';
        $b = is_file($yol) ? json_decode((string)@file_get_contents($yol), true) : [];
        if (!is_array($b)) return ['okundu' => false, 'tarih' => ''];
        $tarih = '';
        foreach ($b as $e) {
            if (!is_array($e)) continue;
            if (empty($e['kosullar_okundu'])) continue;
            if ((string)($e['kosul_surum'] ?? '') !== $surum) continue;
            $e2 = trim(mb_strtolower((string)($e['basvuran']['eposta'] ?? '')));
            if ($e2 !== $eposta) continue;
            $t = substr((string)($e['tarih'] ?? ''), 0, 10);
            if ($t > $tarih) $tarih = $t;
        }
        return ['okundu' => $tarih !== '', 'tarih' => $tarih];
    }

    /* Masa reddi SAYIYLA yayımlanır, ADLA değil.

       Reddedilmiş bir başvurunun yazarını duyurmak, yayımlanmamış bir
       çalışmayı sahibinin rızası olmadan duyurmak olurdu; üstelik
       başvurmayı caydırırdı. Şeffaflık burada sayının kendisindedir:
       kaç başvuru masadan döndü. Bu işlev yalnız sayar; hiçbir ad,
       başlık ya da kimlik döndürmez. */
    function tg_masa_reddi(): array {
        $yol = tg_veri_dizini() . '/basvurular.json';
        $b = is_file($yol) ? json_decode((string)@file_get_contents($yol), true) : [];
        if (!is_array($b)) $b = [];
        $s = ['ret' => 0, 'kabul' => 0, 'bekleyen' => 0, 'toplam' => 0];
        foreach ($b as $e) {
            if (!is_array($e)) continue;
            $s['toplam']++;
            $d = (string)($e['durum'] ?? '');
            if ($d === 'ret') $s['ret']++;
            elseif ($d === 'kabul') $s['kabul']++;
            else $s['bekleyen']++;
        }
        return $s;
    }

    /* Çalışma gerçekten hakem değerlendirmesinden geçti mi?

       Sayaçlar, süzgeçler ve dış üstveri bunu sorar, 'tur'u değil.
       Geri çekilmiş olması geçmişi silmez: çekilmiş ama hakemlenmiş
       bir çalışma hakemlenmiş sayılır, bu yüzden aşamaya değil rapor
       sayısına bakılır. */
    function tg_hakemden_gecti(array $y): bool {
        /* Ret alması da geçmişi silmez: reddedilen çalışma DEĞERLENDİRMEDEN
           GEÇMİŞTİR, olumsuz geçmiştir. Bu işlev "geçti mi" diye sorar,
           "onaylandı mı" diye değil; onayı tg_onay_durumu() söyler. */
        return tg_hakem_yolu_gormus($y) && tg_rapor_sayisi($y) > 0;
    }

    /* Rozet metni. $kisa dar yerler (liste kartı, paylaşım görseli)
       içindir; uzun biçim makale sayfasında kullanılır. */
    function tg_asama_metni(string $asama, bool $en = false, bool $kisa = false): string {
        $m = [
            'cekildi' => [['Geri çekildi', 'Retracted'], ['Geri çekildi', 'Retracted']],
            'yok'     => [['Hakemsiz yazı', 'Non reviewed'], ['Hakemsiz', 'Non reviewed']],
            'aranan'  => [['Hakem aranıyor', 'Seeking reviewers'], ['Hakem aranıyor', 'Seeking reviewers']],
            'suruyor' => [['Değerlendirmede', 'Under review'], ['Değerlendirmede', 'Under review']],
            'onayli'  => [['Hakem onaylı', 'Reviewer approved'], ['Hakem onaylı', 'Reviewer approved']],
            /* Ret, metnin kaldırılması değildir; rozet bunu kısaltamaz
               ama yanlış da anlatmamalı. "Hakemler reddetti" öznesi
               belli bir cümledir: reddeden sistem değil hakemlerdir. */
            'reddedildi' => [['Hakemler reddetti', 'Rejected by reviewers'], ['Ret', 'Rejected']],
        ];
        if (!isset($m[$asama])) return '';
        return tg_t(['tr' => $m[$asama][$kisa ? 1 : 0][0], 'en' => $m[$asama][$kisa ? 1 : 0][1]], $en);
    }

    /* Rozet rengi. Yalnızca kutadgu.css içinde hâlihazırda bulunan
       sınıflar kullanılır; yeni sınıf eklenmediği için TG_SURUM'u
       artırmak gerekmez. */
    function tg_asama_rz(string $asama): string {
        $r = ['cekildi' => 'rz-kir', 'yok' => 'rz-lac', 'aranan' => 'rz-cizgi',
              'suruyor' => 'rz-kut', 'onayli' => 'rz-yes', 'reddedildi' => 'rz-kir'];
        return $r[$asama] ?? 'rz-cizgi';
    }

    /* Aşamanın bir cümlelik açıklaması. Rozet tek başına yeterli
       değildir: "Hakem aranıyor" diyen bir rozetin yanında okurun
       "yani henüz değerlendirilmedi" diye okuyabileceği bir cümle
       durmalı. */
    function tg_asama_aciklama(string $asama, bool $en = false): string {
        /* Cümle düz olguyla başlar, sonra çağrıyı yapar. Sırası bilerek
           böyle: okur önce ne olmadığını öğrenmeli. Ama cümle orada
           bitmez, çünkü bu çalışma saklanmıyor; açıkta durmasının
           sebeplerinden biri gönüllü hakem bulmak. Dürüstlüğün kendisi
           davetin yerine geçiyor. */
        $m = [
            'aranan'  => ['Bu çalışma hakem değerlendirmesinden geçmemiştir. Hakemliğe açıktır ve gönüllü hakem aranmaktadır.',
                          'This work has not undergone peer review. It is open for review and volunteer reviewers are sought.'],
            'suruyor' => ['Bu çalışma değerlendirilmektedir ve henüz onaylanmamıştır; onay için gereken rapor sayısına ulaşılmamıştır.',
                          'This work is under review and not yet approved; it has not reached the number of reports required for approval.'],
            'yok'     => ['Bu, bağımsız bir yazıdır ve hakem değerlendirmesinden geçmemiştir.',
                          'This is an independent piece and has not undergone peer review.'],
            /* Reddedilmiş çalışmanın cümlesi iki şeyi birden söylemek
               zorunda: süreç kapandı VE metin yerinde duruyor. İkincisi
               olmadan okur, ret ile kaldırmayı aynı şey sanır. */
            'reddedildi' => ['Bu çalışma hakemler tarafından reddedilmiştir ve değerlendirme süreci kapanmıştır. Metin kaldırılmaz: reddedilmiş çalışma da hakem gerekçeleriyle birlikte yayımda kalır.',
                             'This work was rejected by its reviewers and the review process is closed. The text is not taken down: a rejected work stays published together with the reviewers\' reasons.'],
        ];
        return isset($m[$asama]) ? tg_t(['tr' => $m[$asama][0], 'en' => $m[$asama][1]], $en) : '';
    }

    /* ---------------------------------------------------------------
       GECİKME

       Bu sistem hakemliğin görünür olmasıyla güven kazanıyor. Şimdiye
       kadar görünen yalnızca SONUÇTU: kaç rapor geldi, kim yazdı, ne
       dedi. Görünmeyen şey SÜREYDİ. Bir çalışma iki yıldır hakem
       bekliyor olabilir ve sayfasında bunu söyleyen tek bir sayı
       yoktu. Süreyi saklamak kötü haberi saklamaktır; açık hakemlik
       iddiasıyla bağdaşmaz. Sayı okurun gözü önünde durur ki bekleyen
       çalışma da hakem çağrısını kendi sayfasından yapabilsin.

       Gecikme HESAPLANIR, SAKLANMAZ -- aşamanın saklanmama gerekçesi
       burada da geçerlidir: saklanan sayı güncellenmeyi unutabilir.

       KAYNAK DÜRÜSTÇE ETİKETLENİR. Yeni kayıtlarda gönderim anı
       'gonderim' alanındadır. Eski kayıtlarda o alan yoktur ve
       geriye dönük uydurulamaz; dördüncü değişmez ilke yayımlanmış
       kaydın sonradan değiştirilmesini yasaklar. O hâlde 'tarih'
       alanına düşülür ve okura bunun gönderim değil YAYIN günü
       olduğu söylenir. Yanlış bir sayı, eksik bir sayıdan kötüdür.
       --------------------------------------------------------------- */
    function tg_gun_farki(string $a, string $b): ?int {
        $a = trim($a); $b = trim($b);
        if ($a === '' || $b === '') return null;
        try {
            $d1 = (new DateTimeImmutable($a))->setTime(0, 0);
            $d2 = (new DateTimeImmutable($b))->setTime(0, 0);
        } catch (Exception $e) { return null; }
        $f = $d1->diff($d2);
        $g = (int)$f->days;
        return $f->invert ? -$g : $g;
    }

    /* Raporu gelmiş hakemlerin rapor tarihleri, küçükten büyüğe */
    function tg_rapor_tarihleri(array $y): array {
        $t = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h)) continue;
            if (trim((string)($h['rapor'] ?? '')) === '') continue;
            $z = trim((string)($h['tarih'] ?? ''));
            if ($z !== '') $t[] = $z;
        }
        sort($t);
        return $t;
    }

    function tg_gecikme(array $y): array {
        $gon = trim((string)($y['gonderim'] ?? ''));
        $kaynak = $gon !== '' ? 'gonderim' : 'tarih';
        if ($gon === '') $gon = trim((string)($y['tarih'] ?? ''));
        if ($gon === '') $kaynak = '';

        $bos = ['gonderim' => '', 'kaynak' => '', 'bekleme_gun' => null,
                'ilk_rapor' => '', 'ilk_rapor_gun' => null, 'son_olay' => ''];
        if ($gon === '') return $bos;

        $simdi = date('c');
        $raporlar = tg_rapor_tarihleri($y);
        $ilk = $raporlar[0] ?? '';
        $son = $raporlar ? $raporlar[count($raporlar) - 1] : '';

        $asama = tg_hakem_asamasi($y);
        /* Bekleme yalnızca gerçekten bekleyen çalışmalarda sürer.
           "Hakem onaylı" bir çalışmanın yanında "412 gündür bekliyor"
           yazmak yalandır: o çalışma beklemiyor, bitmiş. */
        $bekleme = null;
        if ($asama === 'aranan' || $asama === 'suruyor') {
            $f = tg_gun_farki($gon, $simdi);
            $bekleme = $f === null ? null : max(0, $f);   /* gelecek tarih 0 */
        }
        $ilkGun = null;
        if ($ilk !== '') {
            $f = tg_gun_farki($gon, $ilk);
            $ilkGun = $f === null ? null : max(0, $f);
        }
        return [
            'gonderim'      => $gon,
            'kaynak'        => $kaynak,
            'bekleme_gun'   => $bekleme,
            'ilk_rapor'     => $ilk,
            'ilk_rapor_gun' => $ilkGun,
            'son_olay'      => $son !== '' ? $son : $gon,
        ];
    }

    /* Sayının nereden sayıldığı. Okur bunu görmezse sayı yanıltır. */
    function tg_gecikme_kaynak_metni(string $kaynak, bool $en = false): string {
        if ($kaynak === 'gonderim') {
            return tg_c('gönderildiği günden sayılmıştır', 'counted from the day the work was submitted', $en);
        }
        if ($kaynak === 'tarih') {
            return tg_c('yayına alındığı günden sayılmıştır; bu çalışmanın gönderim günü kayıtlı değildir', 'counted from the day it was published, because the submission date is not recorded for this work', $en);
        }
        return '';
    }

    /* Gün sayısının okunur biçimi. Uzun süreler yalnız gün olarak
       yazılınca büyüklüğü kaybolur: "612 gün" ile "612 gün (yaklaşık
       1 yıl 8 ay)" aynı sayıdır, ikincisi anlaşılır. */
    function tg_gun_metni(int $g, bool $en = false): string {
        /* SÜRE CÜMLESİ PARÇA PARÇA ÇEVRİLİR.
           Eskiden bütün cümle `$en ? '… day …' : '… gün …'` diye iki
           dilde kuruluyordu ve üçüncü dilde tamamı Türkçe geliyordu
           ("940 gün (yaklaşık 2 yıl 7 ay)"). Sayı çevrilmez; çevrilen
           BİRİM adıdır. Her birim ayrı ayrı çeviri katmanından geçer,
           böylece sözlüğe 'day'/'days' karşılığı yazan bir dil süreyi
           de kendi dilinde okur. İngilizcedeki çokluk (day/days) o
           dilin kuralıdır ve İngilizce dizede kalır; başka bir dilin
           çokluk kuralı, o dilin sözlüğündeki karşılıkla belirlenir. */
        if ($g <= 0) return tg_c('bugün', 'today', $en);
        $birim = fn(int $n, string $tr, string $enTek, string $enCok): string
            => $n . ' ' . tg_c($tr, $n === 1 ? $enTek : $enCok, $en);
        $temel = $birim($g, 'gün', 'day', 'days');
        if ($g < 60) return $temel;
        $yil = intdiv($g, 365);
        $ay  = intdiv($g - $yil * 365, 30);
        $p = [];
        if ($yil > 0) $p[] = $birim($yil, 'yıl', 'year', 'years');
        if ($ay  > 0) $p[] = $birim($ay, 'ay', 'month', 'months');
        if (!$p) return $temel;
        return $temel . ' (' . tg_c('yaklaşık', 'about', $en) . ' ' . implode(' ', $p) . ')';
    }

    /* Tarihin okunur biçimi. Sayfalar ay adlarını kendi içlerine
       yazmasın diye tek yerde durur. */
    function tg_tarih_ad(string $t, bool $en = false): string {
        $t = substr(trim($t), 0, 10);
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $t, $m)) return '';
        return tg_tarih_dizimi((int)$m[3], (int)$m[2], (int)$m[1], $en);
    }

    /* Gecikme cümlesi. Aşama metni gibi TEK KAYNAKTAN üretilir: sayı
       makale sayfasında, panelde ve istatistikte aynı cümleyle
       anlatılsın, biri diğerinden ayrı düşmesin. HTML döner, çünkü
       tarih makine okunur <time> içinde durur. */
    function tg_gecikme_cumlesi(array $y, bool $en = false): string {
        $g = tg_gecikme($y);
        if ($g['bekleme_gun'] === null || $g['gonderim'] === '') return '';
        $gun  = (int)$g['bekleme_gun'];
        $iso  = substr($g['gonderim'], 0, 10);
        $ad   = tg_tarih_ad($iso, $en);
        if ($ad === '') $ad = $iso;
        $zaman = '<time datetime="' . htmlspecialchars($iso, ENT_QUOTES, 'UTF-8') . '">'
               . htmlspecialchars($ad, ENT_QUOTES, 'UTF-8') . '</time>';
        $sure = htmlspecialchars(tg_gun_metni($gun, $en), ENT_QUOTES, 'UTF-8');
        if ($g['kaynak'] === 'gonderim') {
            return $en
                ? 'Submitted on ' . $zaman . '; it has been awaiting review for <b>' . $sure . '</b>.'
                : $zaman . ' tarihinde gönderildi; <b>' . $sure . '</b> hakem bekliyor.';
        }
        /* Gönderim günü kayıtlı değil: sayı yayın gününden sayılır ve
           bu OKURA SÖYLENİR. Kaynağı gizlenmiş bir sayı, sayı değil
           iddiadır. */
        return $en
            ? 'Published on ' . $zaman . '; <b>' . $sure . '</b> have passed since. The submission date is not recorded for this work, so the count starts from publication.'
            : $zaman . ' tarihinde yayına alındı; o günden bu yana <b>' . $sure . '</b> geçti. Bu çalışmanın gönderim günü kayıtlı olmadığı için sayı yayın gününden başlatılmıştır.';
    }

    /* Hakem bekleyen çalışmaların sırası.

       Bu sayfanın işi gönüllü hakem bulmaktır; o hâlde en önde duran
       çalışma, gönüllüye EN ÇOK ihtiyacı olan çalışma olmalıdır. Sıra
       eskiden "raporu az olan, sonra EN YENİ" idi; yani en uzun
       bekleyen çalışma listenin en dibine düşüyordu. Sıra tersine
       çevrildi: eşit sayıda raporu olanlar arasında EN UZUN BEKLEYEN
       öne gelir. Sayının görünür olması yetmez, sıraya da geçmelidir;
       yoksa görünürlük süsten ibaret kalır. */
    function tg_bekleyen_sirala(array $liste): array {
        $b = [];
        foreach ($liste as $y) {
            if (!is_array($y)) continue;
            $y['_tam'] = tg_rapor_sayisi($y);
            $g = tg_gecikme($y);
            $y['_bek'] = $g['bekleme_gun'] === null ? -1 : (int)$g['bekleme_gun'];
            $b[] = $y;
        }
        usort($b, fn($x, $z) => ($x['_tam'] <=> $z['_tam']) ?: ($z['_bek'] <=> $x['_bek']));
        return $b;
    }

    /* ---------------------------------------------------------------
       KÜNYE: bu çalışma anılırken yazılacak dize.

       TEK KAYNAK OLMASININ SEBEBİ.
       Künye şimdiye kadar yazi.php içinde elle kuruluyordu ve
       yapılandırılmış veride (schema.org) hiç yoktu. Yani okurun
       gördüğü künye ile makinenin okuduğu künye AYRI kaynaklardan
       geliyordu -- daha doğrusu makineninki hiç yoktu. Biri
       değiştiğinde öteki eski kalır ve sistem okura başka, dizine
       başka bir künye verir. Bu oturumda telif metninde düzelttiğimiz
       hatanın aynısıdır: sayfada bir şey, imzada başka bir şey.

       Artık dize burada üretilir; yazi.php de, seo.php'deki
       creditText alanı da aynı işlevi çağırır. Kapı ikisinin BİREBİR
       aynı olduğunu ölçer.

       Biçim APA 7'dir ve kalıcı kimlik olarak TAMGA yazılır. DOI
       varsa o da eklenir, ama tamga düşmez: DOI dışarıdan verilen bir
       numaradır ve bir gün verilmeyebilir; tamga bu arşivin kendi
       kimliğidir ve her zaman çözümlenir.
       --------------------------------------------------------------- */
    function tg_kunye(array $y, bool $en = false): string {
        $marka = (string)tg_ayar('marka', 'Kutadgu');
        $tamgaAd = (string)tg_ayar('tamga_ad', 'Tamga');

        /* ATIF KAYDA YAPILIR, ÇEVİRİYE DEĞİL.
           Sayfa bunu okura zaten söylüyor: "atıf, çalışmanın yazıldığı
           dildeki kayda yapılır; çeviri kaydın yerine geçmez". Künye de
           öyle davranmalıydı ama davranmıyordu: onaysız bir makine
           çevirisinin başlığı künyeye giriyor, yani kimsenin görmediği
           bir başlıkla atıf yapılması isteniyordu. Yayın sayılan bir
           çeviri (yazarın yazdığı ya da onayladığı) başlığını verebilir;
           okuma yardımı veremez. Ölçüt tek yerdedir. */
        $cKun = $en ? tg_yazi_ceviri($y, 'en') : [];
        $bas = tg_ceviri_yayin_mi($cKun) ? trim(strip_tags((string)($cKun['baslik'] ?? ''))) : '';
        if ($bas === '') $bas = trim(strip_tags((string)($y['baslik'] ?? '')));
        $yil = substr((string)($y['tarih'] ?? ''), 0, 4);

        /* Yazar adı: unvan atılır, soyadı öne alınır. Çok yazarlı
           çalışmada APA'nın kendi kuralı: ikiye kadar "ve", üstünde
           "vd." (İngilizcede "et al."). */
        $adlar = [];
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
            if (is_array($ya) && trim((string)($ya['ad'] ?? '')) !== '') $adlar[] = trim((string)$ya['ad']);
        }
        if (!$adlar) {
            $ham = trim((string)($y['yazar'] ?? ''));
            if ($ham !== '') $adlar = array_map('trim', preg_split('/\s*(?:,| ve | and )\s*/u', $ham));
        }
        $bic = function (string $t): string {
            $t = trim(preg_replace('/\b(Dr\.?|Prof\.?|Doç\.?|Doc\.?|Öğr\.?|Gör\.?)\s*/iu', '', $t));
            $p = preg_split('/\s+/', $t);
            if (count($p) < 2) return $t;
            $soy = array_pop($p);
            $bh = implode(' ', array_map(fn($a) => mb_substr($a, 0, 1, 'UTF-8') . '.', $p));
            return $soy . ', ' . $bh;
        };
        $adlar = array_values(array_filter(array_map($bic, $adlar)));
        if (count($adlar) === 0) $yazarKis = tg_c('Anonim', 'Anonymous', $en);
        elseif (count($adlar) === 1) $yazarKis = $adlar[0];
        elseif (count($adlar) === 2) $yazarKis = $adlar[0] . (tg_c(' ve ', ' & ', $en)) . $adlar[1];
        else $yazarKis = $adlar[0] . (tg_c(' vd.', ' et al.', $en));

        $kod = trim((string)($y['bcid'] ?? ''));
        $adres = tg_kok() . tg_yazi_yolu($y);
        $doi = trim((string)($y['doi'] ?? ''));

        $s = $yazarKis . ' (' . ($yil !== '' ? $yil : (tg_c('s.a.', 'n.d.', $en))) . '). ' . $bas . '. ' . $marka . '.';
        if ($kod !== '') $s .= ' ' . $tamgaAd . ': ' . $kod . '.';
        if ($doi !== '') $s .= ' https://doi.org/' . $doi;
        else $s .= ' ' . $adres;
        return $s;
    }

    /* Yapay zekâ çıktıları için koşulun bir cümlelik hâli. Bu cümle
       SAYFADA GÖRÜNÜR; gizli bir talimat değildir. Gizli metin bu
       sistemde yasaktır ve sinama/atif-kapi.php bunu ölçer. */
    /* =================================================================
       İLETİYE YANIT SÜRESİ — SÖZ VERİLİR VE ÖLÇÜLÜR
       -----------------------------------------------------------------
       İletişim sayfası "yanıt için birkaç gün gerekebilir" diyordu.
       İki kusuru vardı. Birincisi ölçülemez olması: "birkaç" tutulup
       tutulmadığı bilinemeyen bir sözdür. İkincisi ve ağırı, bütün
       türlere aynı şeyi söylemesi: bir kurum destek için yazdığında
       birkaç gün beklemek, o desteğin kaybedilmesi demektir.

       Süre artık TÜRE BAĞLIDIR ve ayardan gelir. Ama asıl mesele söz
       değil ÖLÇÜ: bu sistem hakemlik gecikmesini de yayımlıyor ve
       kendi vaadini denetlemeyen bir vaat, vaat değildir. Gerçekleşen
       süreler istatistik sayfasında yayımlanır.

       Saat cinsinden tutulur, çünkü kurum ve destek başvurusunda gün
       çözünürlüğü anlamsız kalır. */
    function tg_ileti_hedef_saat(string $tur): int {
        $a = (array)tg_ayar('iletisim_hedef', []);
        if (isset($a[$tur])) return max(1, (int)$a[$tur]);
        if (isset($a['*']))  return max(1, (int)$a['*']);
        return 72;
    }

    /* Gerçekleşen yanıt süreleri. Kaynağı iletiler.json'dur ve YALNIZ
       yanıtlanmış iletiler sayılır: yanıtlanmamış bir iletiyi süre
       ortalamasına katmak, bekleyen işi tamamlanmış göstermek olurdu.
       Bekleyenler ayrıca ve açıkça sayılır. */
    function tg_ileti_sureleri(): array {
        $yol = tg_veri_dizini() . '/iletiler.json';
        $d = is_file($yol) ? json_decode((string)@file_get_contents($yol), true) : [];
        if (!is_array($d)) $d = [];
        $sure = []; $bekleyen = 0; $toplam = 0; $hedefTutan = 0;
        foreach ($d as $k) {
            if (!is_array($k)) continue;
            /* İSTENMEYEN İŞARETLİ İLETİ ÖLÇÜME GİRMEZ. Yayımlanan sayı
               "yazana ne kadar sürede döndük" sorusunun cevabıdır; bir
               reklama dönülmediği için geçen süreyi oraya katmak,
               sistemin kendi vaadini yanlış ölçmesi olurdu. Karar
               insanındır (elle işaretlenir), sonucu buraya taşınır. */
            if (!empty($k['istenmeyen'])) continue;
            $toplam++;
            $msj = is_array($k['mesajlar'] ?? null) ? $k['mesajlar'] : [];
            $ilk = null; $yanit = null;
            foreach ($msj as $m) {
                if (!is_array($m)) continue;
                $t = strtotime((string)($m['tarih'] ?? ''));
                if (!$t) continue;
                if ($ilk === null && (string)($m['kim'] ?? '') === 'ziyaretci') $ilk = $t;
                if ($yanit === null && (string)($m['kim'] ?? '') === 'kurul' && $ilk !== null) { $yanit = $t; break; }
            }
            if ($ilk !== null && $yanit !== null) {
                $saat = max(0, (int)round(($yanit - $ilk) / 3600));
                $sure[] = $saat;
                if ($saat <= tg_ileti_hedef_saat((string)($k['tur'] ?? 'genel'))) $hedefTutan++;
            } elseif (empty($k['kapali'])) {
                $bekleyen++;
            }
        }
        sort($sure);
        $n = count($sure);
        return [
            'toplam'   => $toplam,
            'yanitli'  => $n,
            'bekleyen' => $bekleyen,
            'ortalama' => $n ? (int)round(array_sum($sure) / $n) : 0,
            /* Ortanca da verilir: tek bir çok geç yanıt ortalamayı
               bozar ve tablo, olduğundan kötü görünür. */
            'ortanca'  => $n ? (int)$sure[intdiv($n, 2)] : 0,
            'en_uzun'  => $n ? (int)$sure[$n - 1] : 0,
            'hedefte'  => $n ? (int)round($hedefTutan * 100 / $n) : 0,
        ];
    }

    function tg_makine_kosulu(bool $en = false): string {
        return tg_c('Bu çalışma, makineyle okunması, eğitimde ve çıkarımda kullanılması dahil her türlü kullanıma açıktır ve karşılığında ücret istenmez. Tek koşul vardır ve kaynağı bu cümle değil CC BY 4.0 lisansıdır: bu çalışmadan alınan ya da türetilen her çıktıda yukarıdaki künye, kalıcı kimliği ve bağlantısıyla birlikte verilmelidir.', 'This work is open to any use, including machine reading, training and inference, at no charge. One condition applies, and it comes from the CC BY 4.0 licence rather than from this sentence: any output taken or derived from this work must carry the citation above, with its permanent identifier and link.', $en);
    }

    /* Bekleyen gönüllü başvurusu sayısı */
    function tg_gonullu_bekleyen(array $y): int {
        $n = 0;
        foreach (tg_dizi($y['gonulluler'] ?? null) as $g) {
            if (is_array($g) && (string)($g['durum'] ?? '') === 'bekliyor') $n++;
        }
        return $n;
    }

    /* ---------------------------------------------------------------
       Düzeltme ve geri çekme kayıtları.
       Özgün metin hiçbir zaman silinmez; kayıt metnin üstüne eklenir.
       --------------------------------------------------------------- */
    function tg_kayitlar(array $y): array {
        $k = is_array($y['kayitlar'] ?? null) ? $y['kayitlar'] : [];
        $out = [];
        foreach ($k as $e) {
            if (!is_array($e)) continue;
            $tur = (string)($e['tur'] ?? '');
            if (!in_array($tur, ['duzeltme', 'geri_cekme', 'endise'], true)) continue;
            $out[] = $e;
        }
        usort($out, fn($a, $b) => strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? '')));
        return $out;
    }

    function tg_geri_cekildi(array $y): bool {
        foreach (tg_kayitlar($y) as $k) { if (($k['tur'] ?? '') === 'geri_cekme') return true; }
        return false;
    }

    function tg_kayit_ad(string $tur, bool $en = false): string {
        $m = [
            'duzeltme'   => ['Düzeltme', 'Correction'],
            'geri_cekme' => ['Geri çekme', 'Retraction'],
            'endise'     => ['Endişe bildirimi', 'Expression of concern'],
        ];
        return isset($m[$tur]) ? tg_t(['tr' => $m[$tur][0], 'en' => $m[$tur][1]], $en) : $tur;
    }

    /* ---------------------------------------------------------------
       Hakemin sıfatı.
       Uzmanlık, diplomanın alanıyla değil çalışmanın konusuyla ilgilidir.
       Bir fizikçi, bir iktisat çalışmasının yöntemine iktisatçının
       göremeyeceği bir katkı yapabilir. Bu yüzden alan uyuşmazlığı bir
       engel değildir; hakem hangi sıfatla değerlendirdiğini seçer ve
       yetkinliğinin gerekçesini yazar, ikisi de okuyucuya gösterilir.
       Kural: hakemlerden en az biri "konu" sıfatıyla girmelidir.
       --------------------------------------------------------------- */
    function tg_sifatlar(bool $en = false): array {
        return [
            'konu'   => tg_c('Konu hakemi', 'Subject reviewer', $en),
            'yontem' => tg_c('Yöntem hakemi', 'Method reviewer', $en),
            'veri'   => tg_c('Veri ve istatistik hakemi', 'Data and statistics', $en),
            'dil'    => tg_c('Dil ve kurgu hakemi', 'Language and form', $en),
        ];
    }
    function tg_sifat_metin($h, bool $en = false): string {
        if (!is_array($h)) return '';
        $s = is_array($h['sifat'] ?? null) ? $h['sifat'] : [];
        $ad = tg_sifatlar($en);
        $out = [];
        foreach ($s as $k) { if (isset($ad[$k])) $out[] = $ad[$k]; }
        return implode(', ', $out);
    }
    /* Konu hakemi var mı? Yoksa okuyucuya bildirilir, ama yayım engellenmez. */
    function tg_konu_hakemi_var(array $y): bool {
        $bilgiVar = false;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $s = is_array($h['sifat'] ?? null) ? $h['sifat'] : [];
            if (!$s) continue;                       /* eski kayıtlar: sıfat bilgisi yok */
            $bilgiVar = true;
            if (in_array('konu', $s, true)) return true;
        }
        return !$bilgiVar;                           /* bilgi yoksa uyarı gösterilmez */
    }

    /* ---------------------------------------------------------------
       Veri ve kod erişilebilirliği beyanı.
       --------------------------------------------------------------- */
    function tg_veri_beyan_ad(string $k, bool $en = false): string {
        $m = [
            'acik'    => ['Veri ve kod açık olarak paylaşıldı', 'Data and code are openly shared'],
            'istek'   => ['Veri ve kod, makul istek üzerine paylaşılır', 'Data and code are available on reasonable request'],
            'kisitli' => ['Veri paylaşılamıyor', 'Data cannot be shared'],
            'yok'     => ['Bu çalışma veri ya da kod üretmiyor', 'This work produces no data or code'],
        ];
        return isset($m[$k]) ? tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]], $en) : '';
    }

    /* ---------------------------------------------------------------
       ETİK KURUL BEYANININ DURUMU — TEK KAYNAK

       ÖLÇÜLEN KUSUR — 18 Ağustos 2026. Üç kusur bir aradaydı:

       1) ARŞİV KAYDI SUSUYORDU. Etik beyanı 15 Ağustos 2026'da
          istenmeye başlandı. Ondan önce yayımlanmış çalışmalarda
          'etik' alanı hiç yok ve yazi.php'deki satır ancak durum boş
          DEĞİLSE basılıyordu. Okur hiçbir şey görmüyordu: "gerekmiyor"
          mu, "sorulmadı" mı, ayırt edilemiyordu. Susmak da bir yanıttır
          ve bu yanıt yanlıştı.

       2) SUSMAK YERİNE SUÇLAMAK DA ÇÖZÜM DEĞİL. Boş alanı kırmızı
          "Beyan edilmedi" yapmak, kendisinden hiç istenmemiş bir şeyi
          yapmadığı için yazarı suçlamak olurdu. Kural geriye
          yürütülemez; ama kuralın NE ZAMAN başladığı yazılabilir.

       3) KAPI YOKTU. Beyanı sonradan yapmanın hiçbir yolu yoktu:
          /yazar-kaydet 'etik' alanını hiç okumuyordu. Sistem eksiği
          gösterip kapatma yolunu vermiyordu. Bu, defalarca yakalanan
          sınıfın kardeşidir: sistem, çözümünü sunmadığı bir eksiği
          duyuruyor.

       Beş hâl ayrılır ve hepsinin ADI VAR:
         gereksiz  — yazar "izin gerekmiyor" dedi
         beyanli   — gerekli dedi ve kurul/tarih/numarayı yazdı
         askida    — gerekli dedi, beyanı yok (kırmızı)
         sorulmadi — kural yürürlüğe girmeden önce yayımlandı (gri)
         eksik     — kuraldan SONRA kaydedildi ama beyanı yok (kırmızı)

       Başlangıç tarihi ayar.php'de tek satırdır. Tarih okunamazsa
       'sorulmadi' seçilir: bilinmeyen bir tarih yüzünden bir yazarı
       suçlamaktansa, bilinmeyeni bilinmeyen olarak göstermek yeğdir.
       --------------------------------------------------------------- */
    function tg_etik_baslangic(): string {
        $t = trim(tg_metin(tg_ayar('etik_beyan_baslangic', '')));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? $t : '';
    }

    function tg_etik_beyanli(array $e): bool {
        return trim(tg_metin($e['kurul'] ?? '')) !== ''
            && trim(tg_metin($e['tarih'] ?? '')) !== ''
            && trim(tg_metin($e['no'] ?? '')) !== '';
    }

    function tg_etik_hal(array $y): string {
        $e = is_array($y['etik'] ?? null) ? $y['etik'] : [];
        $d = trim(tg_metin($e['durum'] ?? ''));
        if ($d === 'gereksiz') return 'gereksiz';
        if ($d === 'gerekli')  return tg_etik_beyanli($e) ? 'beyanli' : 'askida';
        $bas = tg_etik_baslangic();
        if ($bas === '') return 'sorulmadi';
        $tar = substr(trim(tg_metin($y['tarih'] ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $tar)) return 'sorulmadi';
        return ($tar < $bas) ? 'sorulmadi' : 'eksik';
    }

    /* Hâlin okura görünen adı. Renk de burada: iki yerde iki ayrı renk
       seçilirse aynı hâl bir sayfada kırmızı, ötekinde yeşil olur. */
    function tg_etik_hal_ad(string $hal, bool $en = false): string {
        $m = [
            'gereksiz'  => ['Gerekmiyor', 'Not required'],
            'beyanli'   => ['Yazarca beyan edildi', 'Declared by the author'],
            'askida'    => ['Beyan edilmedi', 'Not declared'],
            'sorulmadi' => ['Sorulmadı (arşiv kaydı)', 'Not asked (archive record)'],
            'eksik'     => ['Bildirilmedi', 'Not reported'],
        ];
        return isset($m[$hal]) ? tg_t(['tr' => $m[$hal][0], 'en' => $m[$hal][1]], $en) : '';
    }

    function tg_etik_hal_renk(string $hal): string {
        if ($hal === 'askida' || $hal === 'eksik') return 'var(--kirmizi)';
        if ($hal === 'sorulmadi') return 'var(--metin-2)';
        return 'var(--yesil)';
    }


    /* ---------------------------------------------------------------
       YAPILANDIRILMIŞ ÖZ — İSTEĞE BAĞLI, TEK KAYNAK

       Karşılaştırma notu (ScholarOne/IJPDLM, 15 Ağustos 2026): oradaki
       öz dört başlıkla isteniyor — Purpose / Design-methodology-approach
       / Findings / Originality-value. Dizinlenebilirliği ve okunurluğu
       artırdığı doğru. Ama OLDUĞU GİBİ ALINMADI, iki nedenle:

       1) O başlıklar TEK BİR ALANIN yerleşik geleneğidir. Kutadgu bütün
          FORD alanlarına açıktır; bir felsefe denemesine "araştırma
          tasarımı" sormak, sorunun kendisini anlamsız kılar. Zorunlu
          kılınsaydı, o alanların yazarları kutuyu doldurmak için
          olmayan bir yöntem uydururdu. Uydurulan bir alan, boş bir
          alandan kötüdür.
       2) Bu yüzden yapı ZORUNLU DEĞİL, SUNULUR. İsteyen kullanır.

       BÜYÜK İŞ OLMAKTAN ÇIKARAN KARAR: 'ozet' HÂLÂ TEK KANONİK DİZEDİR
       ve yapı verildiğinde ondan TÜRETİLİR. Bu yüzden PDF, OAI, döküm,
       site haritası, seo ve atıf künyesi hiç değişmedi — hepsi eskisi
       gibi 'ozet' okur. Yapı yalnızca bilen yerde (çalışma sayfası)
       başlıklarıyla çizilir. Aksi yol —her tüketiciye ayrı yapı bilgisi
       taşımak— aynı olguyu altı yerde yazmak olurdu.

       Türetme SUNUCUDA yapılır: tarayıcıdan gelen 'ozet' dizesine
       güvenilseydi, yapı ile özet birbirini tutmayan bir kayıt doğardı
       ve hangisinin doğru olduğu sorusunun yanıtı olmazdı.
       --------------------------------------------------------------- */
    function tg_ozet_bolumleri(): array {
        return [
            ['k' => 'amac',   'tr' => 'Amaç',                 'en' => 'Purpose'],
            ['k' => 'yontem', 'tr' => 'Yöntem ya da yaklaşım', 'en' => 'Method or approach'],
            ['k' => 'bulgu',  'tr' => 'Bulgular',             'en' => 'Findings'],
            ['k' => 'ozgun',  'tr' => 'Özgünlük',             'en' => 'Originality'],
        ];
    }

    function tg_ozet_bolum_ad(string $k, bool $en = false): string {
        foreach (tg_ozet_bolumleri() as $b) {
            if ($b['k'] === $k) return tg_t(['tr' => $b['tr'], 'en' => $b['en']], $en);
        }
        return '';
    }

    /* Gelen ham yapıyı temizler: yalnız tanınan anahtarlar, sıra
       BÖLÜM LİSTESİNİN sırasıdır (gönderenin sırası değil — yoksa aynı
       öz iki kayıtta iki ayrı sırayla okunurdu), boş bölüm atılır.
       Hiçbir bölüm dolu değilse boş dizi döner: yarım bir yapı, yapı
       değildir. */
    function tg_ozet_yapi_temizle($ham, int $enCok = 1200): array {
        if (!is_array($ham)) return [];
        $out = [];
        foreach (tg_ozet_bolumleri() as $b) {
            $v = '';
            if (isset($ham[$b['k']])) $v = (string)$ham[$b['k']];
            else foreach ($ham as $x) {                 /* [{k,m},...] biçimi */
                if (is_array($x) && (string)($x['k'] ?? '') === $b['k']) { $v = (string)($x['m'] ?? ''); break; }
            }
            $v = trim(preg_replace('/\s+/u', ' ', strip_tags($v)));
            if ($v === '') continue;
            $out[] = ['k' => $b['k'], 'm' => mb_substr($v, 0, $enCok)];
        }
        return $out;
    }

    /* Kanonik özet dizesi. Sayfa da uç da bunu okur; iki yerde iki ayrı
       birleştirme yazılsaydı, biri noktalı öteki noktasız olurdu. */
    function tg_ozet_yapidan(array $yapi, bool $en = false): string {
        $p = [];
        foreach ($yapi as $b) {
            if (!is_array($b)) continue;
            $ad = tg_ozet_bolum_ad((string)($b['k'] ?? ''), $en);
            $m  = trim((string)($b['m'] ?? ''));
            if ($ad === '' || $m === '') continue;
            $p[] = $ad . ': ' . $m;
        }
        return implode(' ', $p);
    }

    /* Kayıttaki yapı (varsa). Yapı ile özet birbirini tutmuyorsa YAPI
       YOK SAYILIR: çelişen iki kayıttan hangisinin doğru olduğunu
       sistem bilemez, ama hangisinin KANONİK olduğunu bilir. */
    function tg_ozet_yapisi(array $y, string $alan = 'ozet'): array {
        $anahtar = $alan === 'ozet' ? 'ozet_yapi' : $alan . '_yapi';
        $yapi = tg_ozet_yapi_temizle($y[$anahtar] ?? null);
        if (!$yapi) return [];
        $ozet = trim(preg_replace('/\s+/u', ' ', strip_tags((string)($y[$alan] ?? ''))));
        return (tg_ozet_yapidan($yapi, $alan !== 'ozet') === $ozet) ? $yapi : [];
    }

    /* Arşiv kaydının yanında duracak tek cümle: suçlamaz, tarihi verir
       ve kapının açık olduğunu söyler. */
    function tg_etik_arsiv_notu(bool $en = false): string {
        $b = tg_etik_baslangic();
        if ($b === '') return '';
        return tg_t([
            'tr' => 'Bu çalışma, etik kurul beyanının istenmeye başlandığı ' . $b
                  . ' tarihinden önce yayımlandı; beyan yazarından hiç istenmedi.'
                  . ' Yazarı, çalışmasının erişim koduyla beyanı bugün de ekleyebilir.',
            'en' => 'This work was published before ' . $b . ', the date on which the ethics'
                  . ' declaration began to be requested; it was never asked of its author.'
                  . ' The author can still add the declaration with the access code for the work.',
        ], $en);
    }

    /* ---------------------------------------------------------------
       YAYIN SONRASI ŞERH
       Klasik derginin en zayıf yanı, bir çalışma yayımlandıktan sonra
       tartışmanın bitmesidir. Oysa bilimde değerlendirme yayımla
       başlar. Bu yüzden her çalışmanın altında, doğrulanmış hesabıyla
       ve adıyla yazan okuyucuların bıraktığı kalıcı bir şerh alanı
       vardır.

       Kurallar bilerek serttir:
         - Şerh adla yazılır; takma ad ya da anonim şerh yoktur.
         - Şerh silinmez. Yazan da yazar da silemez. Kayıt kalıcıdır.
         - Bir şerh ancak kişisel saldırı ya da hukuka aykırı içerik
           taşıyorsa editör tarafından PERDELENİR: metin kapatılır ama
           şerhin varlığı, kimin perdelediği ve gerekçesi görünür kalır.
           Böylece sansür de kayda geçer.
         - Yazar her şerhe bir kez yanıt verebilir; yanıt da kalıcıdır.
       --------------------------------------------------------------- */
    function tg_serhler(array $y): array {
        $s = is_array($y['serhler'] ?? null) ? $y['serhler'] : [];
        $out = [];
        foreach ($s as $e) { if (is_array($e) && trim((string)($e['metin'] ?? '')) !== '') $out[] = $e; }
        usort($out, fn($a, $b) => strcmp((string)($a['tarih'] ?? ''), (string)($b['tarih'] ?? '')));
        return $out;
    }

    function tg_serh_sayi(array $y): int { return count(tg_serhler($y)); }

    /* ---------------------------------------------------------------
       BEĞENİ VE İZLEME
       ---------------------------------------------------------------
       Bir okuyucu bir çalışmayı listesine aldığında iki şey ister:
       onu yeniden bulmak ve başına bir şey geldiğinde haberdar olmak.
       Yayımlanmış bir metin bu sistemde değişmez; ama etrafındaki
       değerlendirme yaşamayı sürdürür: yeni bir hakem raporu gelir,
       çalışma hakem onaylı olur, bir kurul oylaması sonuçlanır, bir
       şerh düşülür, bir düzeltme ya da geri çekme kaydı eklenir.

       Bunları izlemek için her yazma yoluna bir bildirim koymak yerine
       çalışmanın o anki durumundan bir özet çıkarılır. Okuyucunun
       listesinde en son gördüğü özet saklanır; ikisi ayrıldığında
       farkın ne olduğu doğrudan okunabilir. Böylece geçmişe dönük de
       çalışır: özellik açılmadan önce olmuş değişiklikler bir kez
       "yeni" görünür, sonra susar.

       Beğeni sayısı kimin beğendiğini açık etmez. Sayfada yalnızca
       toplam görünür; kimin listesinde olduğu o kişinin kendi işidir.
       --------------------------------------------------------------- */
    function tg_yazi_ozet(array $y): array {
        $rapor = 0; $kabul = 0; $ret = 0;
        foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
            if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
            $rapor++;
            $k = (string)($h['karar'] ?? '');
            if ($k === 'kabul') $kabul++;
            elseif ($k === 'ret') $ret++;
        }
        $acikOy = 0; $kapaliOy = 0;
        foreach (tg_oylamalar($y) as $ov) {
            if (tg_oylama_sonuc($ov)['kapali']) $kapaliOy++; else $acikOy++;
        }
        return [
            'rapor'   => $rapor,
            'kabul'   => $kabul,
            'ret'     => $ret,
            'onayli'  => tg_onay_durumu($y)['onayli'] ? 1 : 0,
            'serh'    => tg_serh_sayi($y),
            'oy_acik' => $acikOy,
            'oy_bitti'=> $kapaliOy,
            'kayit'   => count(tg_kayitlar($y)),
            'tur'     => (string)($y['tur'] ?? ''),
            'cekildi' => tg_geri_cekildi($y) ? 1 : 0,
        ];
    }

    /* İki özet arasındaki farkın okunur karşılığı. Boş dizi: değişiklik yok. */
    function tg_ozet_fark($eski, array $yeni, bool $en = false): array {
        if (!is_array($eski) || $eski === []) return [];
        $f = [];
        $a = fn(string $k) => (int)($yeni[$k] ?? 0) - (int)($eski[$k] ?? 0);

        if ($a('rapor') > 0) {
            $n = $a('rapor');
            $f[] = $en ? ($n === 1 ? 'A new reviewer report was published' : $n . ' new reviewer reports were published')
                       : ($n === 1 ? 'Yeni bir hakem raporu yayımlandı' : $n . ' yeni hakem raporu yayımlandı');
        }
        if ((int)($yeni['onayli'] ?? 0) === 1 && (int)($eski['onayli'] ?? 0) === 0) {
            $f[] = tg_c('Çalışma hakem onaylı oldu', 'The work became peer approved', $en);
        }
        if ((int)($yeni['onayli'] ?? 0) === 0 && (int)($eski['onayli'] ?? 0) === 1) {
            $f[] = tg_c('Çalışmanın hakem onayı düştü', 'The work lost its peer approved standing', $en);
        }
        if ($a('oy_acik') > 0) {
            $f[] = tg_c('Bir kurul oylaması açıldı', 'A panel vote was opened', $en);
        }
        if ($a('oy_bitti') > 0) {
            $f[] = tg_c('Bir kurul oylaması sonuçlandı', 'A panel vote concluded', $en);
        }
        if ($a('serh') > 0) {
            $n = $a('serh');
            $f[] = $en ? ($n === 1 ? 'A note was added after publication' : $n . ' notes were added after publication')
                       : ($n === 1 ? 'Yayın sonrası bir şerh düşüldü' : $n . ' yeni şerh düşüldü');
        }
        if ($a('kayit') > 0) {
            $f[] = tg_c('Bir düzeltme ya da bildirim kaydı eklendi', 'A correction or notice was recorded', $en);
        }
        if ((int)($yeni['cekildi'] ?? 0) === 1 && (int)($eski['cekildi'] ?? 0) === 0) {
            $f[] = tg_c('Çalışma geri çekildi', 'The work was retracted', $en);
        }
        $et = (string)($eski['tur'] ?? ''); $yt = (string)($yeni['tur'] ?? '');
        if ($et !== '' && $yt !== '' && $et !== $yt) {
            $f[] = tg_c('Çalışmanın türü değişti', 'The kind of the work changed', $en);
        }
        return $f;
    }

    /* ---------------------------------------------------------------
       BAŞ EDİTÖRÜN DEĞİŞTİREBİLECEĞİ AYARLAR
       ---------------------------------------------------------------
       Bazı sayısal eşikler zamanla ayarlanmak ister ve her ayar için
       bir gönderim yapmak makul değildir. Bu değerler önce veri
       dizinindeki yonetim-ayar.json dosyasında aranır, orada yoksa
       ayar.php'deki varsayılana düşer.

       Buraya yalnızca "işleyişin hızıyla" ilgili sayılar konur.
       İlkelerle ilgili hiçbir şey (ücret yasağı, açık hakemlik,
       kaydın değişmezliği) buradan değiştirilemez; onlar koddadır
       ve depo dışından dokunulamaz.
       --------------------------------------------------------------- */
    function tg_canli_ayar(string $anahtar, $vars = null) {
        static $r = null;
        if ($r === null) {
            $r = [];
            /* =========================================================
               DOSYANIN ADI 'yonetim-ayar.json'.

               Eskiden 'ruh-ayar.json' idi. Ad değişti ve api/index.php
               içindeki veri_gecis() eski dosyayı yeni ada TAŞIYOR — ama
               BURASI eski adı okumayı sürdürüyordu. Sonuç sessiz bir
               kusurdu: taşıma yapıldığı an bu işlev aradığı dosyayı
               bulamaz oldu ve bütün canlı ayarlar varsayılana düştü.
               Hiçbir hata çıkmadı, çünkü "dosya yoksa varsayılan" zaten
               tasarlanmış davranıştı. Bir yeniden adlandırmanın en
               tehlikeli yanı budur: yazan taraf taşınır, okuyan taraf
               unutulur ve sistem çalışmaya devam ediyormuş gibi görünür.

               İkisi de okunur: önce yeni ad, yoksa eski ad. Eski adı
               geride bırakmak, taşınmamış bir sunucuyu sessizce
               varsayılana düşürmek olurdu.
               ========================================================= */
            foreach (['/yonetim-ayar.json', '/ruh-ayar.json'] as $ad) {
                $y = tg_veri_dizini() . $ad;
                if (!is_file($y)) continue;
                $d = json_decode((string)file_get_contents($y), true);
                if (is_array($d)) { $r = $d; break; }
            }
        }
        if (array_key_exists($anahtar, $r)) return $r[$anahtar];
        return tg_ayar($anahtar, $vars);
    }

    /* Yanıtlanmayan bir hakem davetinin düşme süresi (gün). */
    function tg_davet_gun(): int {
        $g = (int)tg_canli_ayar('hakem_davet_gun', 10);
        /* Akla uygun sınırlar: bir günden kısa ya da bir yıldan uzun
           bir süre, ayarın yanlış girilmiş olduğunu gösterir. */
        if ($g < 1) $g = 1;
        if ($g > 365) $g = 365;
        return $g;
    }

    /* ---------------------------------------------------------------
       KEFİL DÜZENİ
       ---------------------------------------------------------------
       Gerekçesi ayar.php içinde yazılıdır. Burada yalnızca durumun
       okunması var: bir yazar unvansız mı, kefilleri kim, onayları
       geldi mi.

       Kefil kaydının biçimi (çalışmadaki yazar kaydının içinde):
         'kefiller' => [
           ['ad','unvan','kurum','orcid','eposta','ilgi','durum',
            'gerekce','tarih','onay_tarihi']
         ]
       'ilgi'  : 'ortak_yazar' | 'bagimsiz'
       'durum' : 'bekliyor' | 'onayli' | 'ret'
       --------------------------------------------------------------- */
    /* ---------------------------------------------------------------
       UNVANLAR
       ---------------------------------------------------------------
       Unvan elle yazılmaz, listeden seçilir. Sebebi yalnızca düzen
       değildir: elle yazılan bir unvan doğrulanamaz ve "Prof. Dr."
       yazan herkes profesör görünür.

       Her unvan bir ANAHTARLA saklanır, metinle değil. Böylece aynı
       kayıt iki dilde de doğru okunur: İngilizce formdan "Assoc. Prof."
       seçen bir araştırmacının unvanı, Türkçe sayfaya bakan birine
       "Doç. Dr." olarak görünür. Kayıt bir tanedir; görünen ad dile
       göre değişir.

       Liste dünyadaki doktora eşdeğerlerini kapsar. Doktoraya denk
       sayılan her derece buraya eklenebilir; eklemek tek satırdır.

       "Öğr. Gör. Dr." ve "Arş. Gör. Dr." Türkiye'de kadro adıdır, unvan
       değildir; doktorası olan kişinin unvanı "Dr."dir. Buna rağmen
       listededirler, çünkü kişinin kendini nasıl adlandıracağına karar
       vermek sistemin işi değildir.
       --------------------------------------------------------------- */
    function tg_unvan_tablo(): array {
        return [
            /* anahtar        Türkçe karşılık        İngilizce karşılık */
            'dr'        => ['tr' => 'Dr.',            'en' => 'Dr.'],
            'dr_ogr'    => ['tr' => 'Dr. Öğr. Üyesi', 'en' => 'Assist. Prof.'],
            /* Aşağıdaki ikisi Türkiye'de kadro adıdır, akademik unvan
               değildir; doktorası olan kişinin unvanı "Dr."dir. Yine de
               listede yer alıyorlar: kişinin kendini nasıl adlandırdığına
               sistemin karışmaması, karışmasından daha doğrudur. */
            'ogr_gor_dr'=> ['tr' => 'Öğr. Gör. Dr.',  'en' => 'Lecturer, PhD'],
            'ars_gor_dr'=> ['tr' => 'Arş. Gör. Dr.',  'en' => 'Research Assistant, PhD'],
            'doc'       => ['tr' => 'Doç. Dr.',       'en' => 'Assoc. Prof.'],
            'prof'      => ['tr' => 'Prof. Dr.',      'en' => 'Prof.'],
            'uzm_dr'    => ['tr' => 'Uzm. Dr.',       'en' => 'Specialist Dr.'],
            'op_dr'     => ['tr' => 'Op. Dr.',        'en' => 'Dr. (Surgeon)'],
            'md'        => ['tr' => 'Dr. (Tıp)',      'en' => 'MD'],
            'phd'       => ['tr' => 'Dr. (PhD)',      'en' => 'PhD'],
            'dphil'     => ['tr' => 'Dr. (DPhil)',    'en' => 'DPhil'],
            'dsc'       => ['tr' => 'Dr. (Fen)',      'en' => 'DSc'],
            'scd'       => ['tr' => 'Dr. (Fen)',      'en' => 'ScD'],
            'edd'       => ['tr' => 'Dr. (Eğitim)',   'en' => 'EdD'],
            'dba'       => ['tr' => 'Dr. (İşletme)',  'en' => 'DBA'],
            'jsd'       => ['tr' => 'Dr. (Hukuk)',    'en' => 'JSD / SJD'],
            'thd'       => ['tr' => 'Dr. (İlahiyat)', 'en' => 'ThD'],
            'dma'       => ['tr' => 'Dr. (Müzik)',    'en' => 'DMA'],
            'dr_habil'  => ['tr' => 'Dr. habil.',     'en' => 'Dr. habil.'],
            'privdoz'   => ['tr' => 'Doç. (Privatdozent)', 'en' => 'Privatdozent'],
            'docent'    => ['tr' => 'Doçent',         'en' => 'Docent'],
            'kandidat'  => ['tr' => 'Dr. (Kandidat Nauk)', 'en' => 'Candidate of Sciences'],
            'hakase'    => ['tr' => 'Dr. (Hakase)',   'en' => 'Hakase (博士)'],
            'boshi'     => ['tr' => 'Dr. (Boshi)',    'en' => 'Boshi (博士)'],
            'doctorat'  => ['tr' => 'Dr. (Doctorat)', 'en' => 'Docteur'],
            'dottore'   => ['tr' => 'Dr. (Dottore di Ricerca)', 'en' => 'Dottore di Ricerca'],
        ];
    }

    /* Bir unvan anahtarının o dildeki karşılığı */
    function tg_unvan_ad(string $anahtar, ?bool $en = null): string {
        $anahtar = trim($anahtar);
        if ($anahtar === '') return '';
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        $t = tg_unvan_tablo();
        if (isset($t[$anahtar])) return (string)tg_t($t[$anahtar], $en);
        /* Anahtar değil de eski kayıtlardan gelen düz metinse olduğu
           gibi gösterilir; hiçbir kayıt sessizce kaybolmaz. */
        return $anahtar;
    }

    /* Düz metin bir unvanı anahtara çevirir. Eski kayıtlar ve elle
       yazılmış unvanlar için; eşleşme bulunamazsa '' döner. */
    function tg_unvan_anahtar(string $metin): string {
        $m = trim($metin);
        if ($m === '') return '';
        if (isset(tg_unvan_tablo()[$m])) return $m;          /* zaten anahtar */
        $sade = function (string $x): string {
            $x = mb_strtolower(trim($x), 'UTF-8');
            $x = strtr($x, ['ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u','İ'=>'i']);
            return preg_replace('/[^a-z0-9]/', '', $x) ?? '';
        };
        $hedef = $sade($m);
        if ($hedef === '') return '';
        foreach (tg_unvan_tablo() as $k => $v) {
            if ($sade($v['tr']) === $hedef || $sade($v['en']) === $hedef) return $k;
        }
        return '';
    }

    /* Seçim listesi: [anahtar => o dildeki ad] */
    function tg_unvanlar(?bool $en = null): array {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        $out = [];
        foreach (tg_unvan_tablo() as $k => $v) $out[$k] = (string)tg_t($v, $en);
        return $out;
    }

    /* ---------------------------------------------------------------
       UNVAN SEÇENEKLERİ — TEK KAYNAK

       Unvan üç ayrı yerde soruluyordu (başvuru sihirbazı, yazar paneli,
       hakem adaylığı) ve üçünde de DÜZ METİN kutusuydu. Düz metin,
       aynı unvanın altı ayrı yazımını üretir: "Dr.", "Dr", "dr.",
       "Doktor", "Öğr. Gör. Dr." … Bir listeyi sonradan derlemek,
       baştan seçtirmekten kat kat pahalıdır.

       Seçeneklerin DEĞERİ görünen addır, anahtar değil. Bunlar hesap
       kaydı değil, çalışmanın künyesindeki yazar satırlarıdır ve orada
       okunacak olan şey addır. Değer anahtar yapılsaydı, eskiden
       yazılmış bütün kayıtlar bir gecede eşleşmez olurdu.

       Liste kapalı DEĞİLDİR: tg_unvan_tablo() dünyanın dört yanından
       doktora eşdeğeri dereceleri taşır (DPhil, Docteur, 博士,
       Dottore di Ricerca, Privatdozent…). Bir sistem, kurulunu tek bir
       ülkeyle sınırlamadığını söylüyorsa unvan listesini de
       sınırlamamalıdır.
       --------------------------------------------------------------- */
    function tg_unvan_secenek(?bool $en = null, string $secili = '', string $bosMetin = ''): string {
        $liste = tg_unvanlar($en);
        $ç = '<option value="">' . htmlspecialchars($bosMetin !== '' ? $bosMetin
             : (string)tg_c('(seçiniz)', '(choose)', $en), ENT_QUOTES, 'UTF-8') . '</option>';
        $bulundu = false;
        foreach ($liste as $ad) {
            $s = ($secili !== '' && $secili === $ad) ? ' selected' : '';
            if ($s !== '') $bulundu = true;
            $ç .= '<option value="' . htmlspecialchars($ad, ENT_QUOTES, 'UTF-8') . '"' . $s . '>'
                . htmlspecialchars($ad, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        /* ESKİ KAYIT KAYBOLMAZ. Elle yazılmış bir unvan listede yoksa
           kendi seçeneği olarak eklenir; yoksa kutu boş açılır ve kişi
           kaydını kendi eliyle silmiş olur. */
        if ($secili !== '' && !$bulundu) {
            $ç .= '<option value="' . htmlspecialchars($secili, ENT_QUOTES, 'UTF-8') . '" selected>'
                . htmlspecialchars($secili, ENT_QUOTES, 'UTF-8') . '</option>';
        }
        return $ç;
    }

    function tg_unvan_yeterli(string $u): bool {
        $u = trim($u);
        if ($u === '') return false;
        /* Listedeki her unvan doktora ya da eşdeğeri bir derecedir;
           anahtarla gelen bir unvan doğrudan yeterlidir. */
        if (isset(tg_unvan_tablo()[$u])) return true;
        if (tg_unvan_anahtar($u) !== '') return true;
        /* Eski kayıtlar: elle yazılmış unvanlar */
        $l = mb_strtolower($u, 'UTF-8');
        foreach (['dr', 'prof', 'doç', 'doc'] as $k) { if (mb_strpos($l, $k) !== false) return true; }
        return false;
    }

    /* ---- DESTEKLEYEN ARAŞTIRMACI: DÜZENİN ADI ----
       Bu düzenin adı önce "kefil"di. Kefalet hukuktan gelir ve borç
       çağrıştırır; burada yapılan şey bir borcun üstlenilmesi değil,
       bir araştırmacının bir başkasının adına ve çalışmasına açıkça
       sahip çıkmasıdır. Ad bu yüzden değişti.

       DEĞİŞEN YALNIZCA ADDIR. Düzenek aynıdır ve 2027 sonuna kadar
       aynı kalır: unvanı olmayan bir araştırmacı için iki doktoralı,
       biri çalışmanın ortak yazarı biri dışarıdan, gerekçesiyle
       sorumluluk üstlenir.

       KOD İÇİNDEKİ ANAHTARLAR DEĞİŞMEZ. 'kefiller', 'kefil_acik',
       'kefil_sayisi' ve /kefil uçları oldukları gibi durur. Nedeni
       şudur: bu anahtarlar yayımlanmış kayıtların içindedir ve
       gönderilmiş onay bağlantıları o adrese gider. Yayımlanmış bir
       kayıt geriye dönük değiştirilmez; ad değişikliği de bu ilkenin
       dışında değildir. Görünen ad buradan gelir, kayıt olduğu yerde
       kalır.

       Terimi tek bir yerden okumanın nedeni bu: ad bir kez daha
       değişirse dokunulacak yer burasıdır, otuz cümle değil. */
    function tg_destek_ad(bool $en = false, bool $cogul = false): string {
        if ($en) return $cogul ? 'supporting researchers' : 'supporting researcher';
        return $cogul ? 'destekleyen araştırmacılar' : 'destekleyen araştırmacı';
    }

    /* Cümle başında kullanılacak biçim. */
    function tg_destek_ad_bas(bool $en = false, bool $cogul = false): string {
        $a = tg_destek_ad($en, $cogul);
        return mb_strtoupper(mb_substr($a, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($a, 1, null, 'UTF-8');
    }

    /* Düzenin adı: "destekleyicilik" gibi bir soyut ad gerektiğinde. */
    function tg_destek_duzen_ad(bool $en = false): string {
        return tg_c('yazarlık desteği', 'the authorship support arrangement', $en);
    }

    /* Bu yazarın destekleyen araştırmacıya ihtiyacı var mı?
       (unvanı yeterli değilse) */
    function tg_yazar_unvansiz($ya): bool {
        if (!is_array($ya)) return false;
        return !tg_unvan_yeterli((string)($ya['unvan'] ?? ''));
    }

    function tg_kefiller($ya): array {
        if (!is_array($ya)) return [];
        $k = is_array($ya['kefiller'] ?? null) ? $ya['kefiller'] : [];
        $out = [];
        foreach ($k as $e) { if (is_array($e) && trim((string)($e['ad'] ?? '')) !== '') $out[] = $e; }
        return $out;
    }

    /* Bir yazarın kefil durumu: ['gerek','onayli','bekleyen','ret','tam'] */
    function tg_kefil_durum($ya): array {
        $gerek = (int)tg_ayar('kefil_sayisi', 2);
        $onayli = 0; $bekleyen = 0; $ret = 0;
        foreach (tg_kefiller($ya) as $k) {
            $d = (string)($k['durum'] ?? 'bekliyor');
            if ($d === 'onayli') $onayli++;
            elseif ($d === 'ret') $ret++;
            else $bekleyen++;
        }
        return ['gerek' => $gerek, 'onayli' => $onayli, 'bekleyen' => $bekleyen,
                'ret' => $ret, 'tam' => $onayli >= $gerek];
    }

    /* =================================================================
       "DESTEKLE YAZAR" — ROZET NE ZAMAN BASILIR
       -----------------------------------------------------------------
       ÖLÇÜLEN KUSUR — 14 Ağustos 2026, kurul bildirimi: kişi sayfasında
       "Destekle yazar" rozeti duruyordu, oysa yazarlık desteği düzeni
       yürürlükte DEĞİL.

       Ölçüm:
         tg_yazarlik_doktora_sarti() ....... false
         kefil_acik ........................ true
         ikisinin birleşimi ($kefilAcik) ... FALSE
       Yani hiç kimse "destekle yazar" olamaz; buna rağmen rozet
       basılıyordu. Bu, bu depoda on ikinci kez yakalanan kusur
       sınıfıdır: sistem, uygulamadığı bir kuralı duyuruyor.

       İKİNCİ VE DAHA DERİN KUSUR: rozet YANLIŞ OLGUDAN türetiliyordu.
       Ölçüt tg_yazar_unvansiz() idi, yani "bu kayıtta unvan yazmıyor".
       Unvanın yazmaması, o kişinin destekle yazar olduğu anlamına
       gelmez — unvan alanını doldurmamış olabilir, ya da düzen kapalı
       olduğu için hiç sorulmamıştır. Sistem böylece bir insanı, hiç
       uygulanmamış bir kurala göre ve kendi beyanına bakmadan
       herkese açık bir sayfada etiketliyordu.

       Doğru ölçüt iki şeyi birden ister:
         1. Düzen YÜRÜRLÜKTE olacak (ikisi birden: doktora şartı ve
            kefil_acik — yazarlık koşulu kalkmışsa desteğin karşılığı
            da kalmaz),
         2. O kayıt desteği GERÇEKTEN kullanmış olacak, yani gereken
            sayıda ONAYLI destekleyen araştırmacı bulunacak.
       Bekleyen ya da reddedilmiş bir destek yetmez: tamamlanmamış bir
       düzen, tamamlanmış gibi duyurulamaz.
       ================================================================= */
    function tg_destek_duzeni_acik(): bool {
        return tg_yazarlik_doktora_sarti() && (bool)tg_ayar('kefil_acik', true);
    }
    function tg_destekle_yazar($ya): bool {
        if (!is_array($ya) || !tg_destek_duzeni_acik()) return false;
        if (!tg_yazar_unvansiz($ya)) return false;
        return (bool)tg_kefil_durum($ya)['tam'];
    }

    /* Çalışmanın bütün yazar kayıtları tek listede (sıra korunur) */
    function tg_yazar_kayitlari(array $y): array {
        $liste = is_array($y['yazar_liste'] ?? null) ? $y['yazar_liste'] : [];
        if ($liste) return $liste;
        $b = is_array($y['yazar_bilgi'] ?? null) ? [$y['yazar_bilgi']] : [];
        if ($b) return $b;
        $ad = trim((string)($y['yazar'] ?? ''));
        return $ad !== '' ? [['ad' => $ad]] : [];
    }

    /* Çalışmada onayı bekleyen kefil var mı? Varsa yayına alınamaz. */
    function tg_kefil_eksik(array $y): array {
        $eksik = [];
        foreach (tg_yazar_kayitlari($y) as $ya) {
            if (!tg_yazar_unvansiz($ya)) continue;
            $d = tg_kefil_durum($ya);
            if (!$d['tam']) $eksik[] = ['ad' => (string)($ya['ad'] ?? ''), 'durum' => $d];
        }
        return $eksik;
    }

    function tg_kefil_ilgi_ad(string $k, bool $en = false): string {
        $m = [
            'ortak_yazar' => ['Bu çalışmanın doktoralı ortak yazarı', 'A co author of this work holding a doctorate'],
            'bagimsiz'    => ['Çalışmayla bağı olmayan doktoralı', 'A doctorate holder with no connection to the work'],
        ];
        return isset($m[$k]) ? tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]], $en) : $k;
    }

    /* ---------------------------------------------------------------
       KİŞİ SAYFALARI
       ---------------------------------------------------------------
       Bir adın altında ne yapıldığı bu sistemde zaten açıktır: raporlar
       adla yayımlanır, kurul oyları adla ve gerekçesiyle açılır, şerhler
       adla durur. Dağınık hâlde duran bu kayıtları tek bir sayfada
       toplamak yeni bir bilgi açmaz; yalnızca zaten açık olanı okunur
       kılar. "Her hakemin karar dağılımı okuyucuya açıktır" sözü de
       ancak böyle bir sayfayla gerçekten tutulmuş olur.

       Kişi, adının sadeleştirilmiş biçimiyle bulunur. Aynı adı taşıyan
       iki araştırmacıyı ayıran şey ORCID'dir; ORCID varsa sayfada
       yazılıdır ve okuyucu ayrımı oradan yapar. Bu, kapatılmamış bir
       açıktır ve acikliklar.php sayfasında yazılıdır.

       E-posta adresi hiçbir kişi sayfasında görünmez.
       --------------------------------------------------------------- */
    function tg_ad_slug(string $ad): string {
        $a = tg_ad_anahtar($ad);
        return $a === '' ? '' : str_replace(' ', '-', $a);
    }
    function tg_slug_anahtar(string $slug): string {
        return trim(preg_replace('/\s+/', ' ', str_replace('-', ' ', mb_strtolower(trim($slug), 'UTF-8'))));
    }

    /* Bir kişinin sistemdeki bütün kamusal kaydı.
       $yazilar: arşivin tamamı. Dönen dizi kisi.php tarafından basılır. */
    function tg_kisi_kaydi(array $yazilar, string $anahtar): array {
        $k = [
            'anahtar' => $anahtar, 'ad' => '', 'unvan' => '', 'kurum' => '', 'orcid' => '', 'scopus' => '', 'web' => '',
            'yazarlik' => [], 'hakemlik' => [], 'oylar' => [], 'serhler' => [], 'kefillikler' => [],
            'karar' => ['kabul' => 0, 'kucuk' => 0, 'buyuk' => 0, 'ret' => 0],
            'ilk' => '', 'son' => '',
        ];
        if ($anahtar === '') return $k;

        $zaman = function (string $t) use (&$k) {
            $t = trim($t); if ($t === '') return;
            if ($k['ilk'] === '' || strcmp($t, $k['ilk']) < 0) $k['ilk'] = $t;
            if ($k['son'] === '' || strcmp($t, $k['son']) > 0) $k['son'] = $t;
        };
        /* Ad, unvan ve kurum: kayıtlarda geçen en dolu biçim alınır */
        $doldur = function (array $kaynak) use (&$k) {
            foreach (['ad', 'unvan', 'kurum', 'orcid', 'scopus', 'web'] as $alan) {
                $v = trim((string)($kaynak[$alan] ?? ''));
                if ($v !== '' && (string)$k[$alan] === '') $k[$alan] = $v;
            }
        };

        foreach ($yazilar as $y) {
            if (!is_array($y)) continue;
            $yol = tg_yazi_yolu($y);
            $bas = (string)($y['baslik'] ?? '');
            $tar = (string)($y['tarih'] ?? '');

            /* Yazarlık */
            foreach (tg_yazar_kayitlari($y) as $ya) {
                if (tg_ad_anahtar((string)($ya['ad'] ?? '')) !== $anahtar) continue;
                $doldur($ya);
                $k['yazarlik'][] = ['baslik' => $bas, 'yol' => $yol, 'tarih' => $tar,
                                    'tur' => (string)($y['tur'] ?? ''),
                                    'onayli' => tg_onay_durumu($y)['onayli'],
                                    /* ANAHTAR ADI DA DEĞİŞTİ: 'unvansiz' yanlış olguyu
                                       taşıyordu ve adı yüzünden yanlış yerde
                                       kullanıldı. Ad artık ne olduğunu söylüyor. */
                                    'destekli' => tg_destekle_yazar($ya)];
                $zaman($tar);
                break;
            }

            /* Hakemlik */
            foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
                if (!is_array($h) || tg_ad_anahtar((string)($h['ad'] ?? '')) !== $anahtar) continue;
                if (trim((string)($h['rapor'] ?? '')) === '') continue;
                $doldur(is_array($h['profil'] ?? null) ? $h['profil'] : []);
                if ((string)$k['ad'] === '') $k['ad'] = (string)($h['ad'] ?? '');
                $karar = (string)($h['karar'] ?? '');
                if (isset($k['karar'][$karar])) $k['karar'][$karar]++;
                $k['hakemlik'][] = ['baslik' => $bas, 'yol' => $yol, 'karar' => $karar,
                                    'tarih' => (string)($h['tarih'] ?? $tar),
                                    'nitelik' => tg_rapor_nitelik($h)['yeterli'],
                                    'saymaz' => tg_rapor_oylama_saymaz($y, $h),
                                    'bagimsiz' => tg_hakem_bagimsiz($h)];
                $zaman((string)($h['tarih'] ?? ''));
            }

            /* Kurul oyları: yalnızca kapanmış oylamalar açıktır */
            foreach (tg_oylamalar($y) as $ov) {
                $s = tg_oylama_sonuc($ov);
                if (!$s['kapali']) continue;
                foreach (tg_dizi($ov['oylar'] ?? null) as $o) {
                    if (!is_array($o) || tg_ad_anahtar((string)($o['ad'] ?? '')) !== $anahtar) continue;
                    if ((string)$k['ad'] === '') $k['ad'] = (string)($o['ad'] ?? '');
                    $k['oylar'][] = ['baslik' => $bas, 'yol' => $yol, 'tur' => (string)($ov['tur'] ?? ''),
                                     'karar' => (string)($o['karar'] ?? ''), 'gerekce' => (string)($o['gerekce'] ?? ''),
                                     'tarih' => (string)($o['tarih'] ?? '')];
                    $zaman((string)($o['tarih'] ?? ''));
                }
            }

            /* Şerhler */
            foreach (tg_serhler($y) as $sr) {
                if (tg_ad_anahtar((string)($sr['ad'] ?? '')) !== $anahtar) continue;
                if ((string)$k['ad'] === '') $k['ad'] = (string)($sr['ad'] ?? '');
                $k['serhler'][] = ['baslik' => $bas, 'yol' => $yol, 'metin' => (string)($sr['metin'] ?? ''),
                                   'ilgi' => (string)($sr['ilgi'] ?? ''), 'tarih' => (string)($sr['tarih'] ?? '')];
                $zaman((string)($sr['tarih'] ?? ''));
            }

            /* Kefillikler */
            foreach (tg_yazar_kayitlari($y) as $ya) {
                foreach (tg_kefiller($ya) as $kf) {
                    if (tg_ad_anahtar((string)($kf['ad'] ?? '')) !== $anahtar) continue;
                    $doldur($kf);
                    $k['kefillikler'][] = ['baslik' => $bas, 'yol' => $yol, 'kime' => (string)($ya['ad'] ?? ''),
                                           'ilgi' => (string)($kf['ilgi'] ?? ''), 'durum' => (string)($kf['durum'] ?? ''),
                                           'gerekce' => (string)($kf['gerekce'] ?? ''),
                                           'tarih' => (string)($kf['onay_tarihi'] ?? '')];
                }
            }
        }

        usort($k['yazarlik'], fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
        usort($k['hakemlik'], fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
        usort($k['oylar'],    fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
        usort($k['serhler'],  fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
        return $k;
    }

    /* ---------------------------------------------------------------
       DIŞ PROFİL BAĞLANTISI
       ---------------------------------------------------------------
       Araştırmacının kendi kurumundaki ya da bir araştırmacı ağındaki
       sayfası. Adres kişinin kendi seçimidir; sistem hiçbir kuruma
       ayrıcalık tanımaz. Etiket verilmemişse alan adından tanınır,
       tanınmıyorsa "Profil" yazılır. Türkiye'deki kurumsal sistemler
       (YÖKSİS, ABS, AVESİS) ile uluslararası ağlar aynı listede durur;
       hiçbiri zorunlu değildir.
       --------------------------------------------------------------- */
    /* ---------------------------------------------------------------
       HATA KAYDI
       ---------------------------------------------------------------
       Bir sayfa çöktüğünde okuyucunun gördüğü boş bir 500 ekranıdır ve
       geriye hiçbir iz kalmaz; sunucu günlüğüne erişimi olmayan biri
       için hata görünmez olur. Bu yüzden ölümcül hatalar veri dizinine
       yazılır (web kökünün dışına) ve okuyucuya sistemin kendi hata
       sayfası gösterilir.

       Kayıt sınırlıdır: dosya 512 KB'yi geçerse baştan yazılır. Kişisel
       veri yazılmaz; yalnızca dosya, satır ve ileti. */
    function tg_hata_yaz(string $nerede, string $mesaj, string $dosya = '', int $satir = 0): void {
        $dizin = function_exists('tg_veri_dizini') ? tg_veri_dizini() : '';
        if ($dizin === '' || !is_dir($dizin)) return;
        $yol = $dizin . '/hata-gunluk.jsonl';
        if (is_file($yol) && filesize($yol) > 512 * 1024) @unlink($yol);
        $satirMetin = json_encode([
            't'      => date('c'),
            'nerede' => mb_substr($nerede, 0, 80),
            'mesaj'  => mb_substr(preg_replace('/\s+/u', ' ', $mesaj), 0, 600),
            'dosya'  => mb_substr($dosya, 0, 160),
            'satir'  => $satir,
        ], JSON_UNESCAPED_UNICODE);
        @file_put_contents($yol, $satirMetin . "\n", FILE_APPEND | LOCK_EX);
    }

    /* Sayfanın başında çağrılır. Ölümcül bir hata olursa kaydı tutar ve
       okuyucuya boş ekran yerine sistemin hata sayfasını gösterir. */
    function tg_hata_yakala(string $sayfa): void {
        register_shutdown_function(function () use ($sayfa) {
            $h = error_get_last();
            if (!$h || !in_array($h['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) return;
            tg_hata_yaz($sayfa, (string)$h['message'], (string)$h['file'], (int)$h['line']);
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
                echo '<!doctype html><meta charset="utf-8"><title>Hata</title>'
                   . '<div style="font:16px/1.6 system-ui;max-width:38em;margin:12vh auto;padding:0 20px">'
                   . '<h1 style="font-size:var(--y-8)">Bu sayfa şu an açılamıyor</h1>'
                   . '<p>Hata kaydı tutuldu ve baş editörlere görünür durumda. Arşivdeki hiçbir çalışma bundan etkilenmez.</p>'
                   . '<p><a href="/">Ana sayfaya dön</a></p></div>';
            }
        });
    }

    /* ---------------------------------------------------------------
       KİŞİSEL BAĞLANTILAR VE ÜYELİKLER
       ---------------------------------------------------------------
       Bir araştırmacının kaydı yalnızca bu sistemdeki işlerinden ibaret
       değildir. Kendi seçtiği akademik bağlantıları (Google Scholar,
       ResearchGate, YÖKSİS, kurum sayfası, GitHub ...) ve üyeliklerini
       (dernek, kurul, komisyon) profiline ekleyebilir.

       Kurallar:
         - Yalnızca https adresleri kabul edilir. Şifresiz bir adres,
           tıklayan kişinin trafiğini açıkta bırakır.
         - En çok 10 bağlantı, en çok 12 üyelik. Bu bir özgeçmiş değil,
           bir kayıt sayfasıdır; uzadıkça okunmaz olur.
         - Bağlantının adı adresten çözülür (tg_dis_profil_ad); kişi
           isterse kendi etiketini yazar.
         - Hiçbiri zorunlu değildir ve hiçbiri bir yetki getirmez. */
    function tg_baglantilar($ham): array {
        if (!is_array($ham)) return [];
        $out = [];
        $gorulen = [];
        foreach ($ham as $b) {
            if (is_string($b)) $b = ['url' => $b, 'ad' => ''];
            if (!is_array($b)) continue;
            $url = trim((string)($b['url'] ?? ''));
            if ($url === '') continue;
            if (!preg_match('#^https://#i', $url)) {
                /* Şemasız yazılmışsa https varsayılır; http ise reddedilir */
                if (preg_match('#^http://#i', $url)) continue;
                $url = 'https://' . ltrim($url, '/');
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            $url = mb_substr($url, 0, 300);
            /* Aynı adres iki kez yazılmışsa bir kez durur; kişi
               listesini elle ayıklamak zorunda kalmasın. */
            $anah = tg_url_anahtar($url);
            if (isset($gorulen[$anah])) continue;
            $gorulen[$anah] = true;
            $ad  = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)($b['ad'] ?? ''))), 0, 60);
            $out[] = ['url' => $url, 'ad' => $ad];
            if (count($out) >= 10) break;
        }
        return $out;
    }

    /* İki adresin aynı olup olmadığına bakarken şema, baştaki www ve
       sondaki eğik çizgi dikkate alınmaz: bunlar yazana göre değişir
       ama aynı sayfayı gösterir. */
    function tg_url_anahtar(string $url): string {
        $u = mb_strtolower(trim($url), 'UTF-8');
        $u = (string)preg_replace('#^https?://#', '', $u);
        $u = (string)preg_replace('#^www\.#', '', $u);
        return rtrim($u, '/');
    }

    /* Bir hesabın bağlantı listesi, tek kapıdan.

       Bu sistemde önceleri tek bir 'web' alanı vardı. Çoklu listeye
       geçilirken o alan silinmedi: kişi panelini bir daha hiç açmasa
       bile girdiği adres kaybolmaz, listenin başında görünmeye devam
       eder. Alan, kişi profilini ilk kaydedişinde listeye taşınır ve
       kayıttan düşer. Gösteren her yer bu işlevi çağırır; hiçbir sayfa
       'web' alanını kendi başına okumaz. */
    function tg_hesap_baglantilar($h): array {
        if (!is_array($h)) return [];
        $liste = tg_baglantilar($h['baglantilar'] ?? []);
        $eski  = trim((string)($h['web'] ?? ''));
        if ($eski === '') return $liste;
        $tek = tg_baglantilar([['url' => $eski, 'ad' => (string)($h['web_ad'] ?? '')]]);
        if (!$tek) return $liste;
        foreach ($liste as $b) {
            if (tg_url_anahtar($b['url']) === tg_url_anahtar($tek[0]['url'])) return $liste;
        }
        array_unshift($liste, $tek[0]);
        return array_slice($liste, 0, 10);
    }

    /* ---- EDİTÖRYAL İŞİN SAYIMI ----
       Kim, kaç hakem ataması yapmış ve bu atamaların kaçı raporla
       sonuçlanmış? Görevdeki baş editörlüğün ölçütü budur.

       Sayılan şey atamanın kendisi değil, raporla sonuçlanmış olanıdır:
       atama yapmak kolaydır, sürecin sonuna kadar götürmek iş ister.
       Ad anahtarıyla sayılır, çünkü unvan zamanla değişir.

       Dönen: ad anahtarı => sayı. */
    /* $kesim: ad anahtarı => 'YYYY-AA-GG'. O kişi için yalnız bu
       tarihten SONRAKİ atamalar sayılır.

       NEDEN GEREKLİ. Ölçüt ömür boyu birikimliydi. Etkinlik ölçütünü
       karşılamadığı için görevi biten bir kişi, eski atamaları hâlâ
       sayıldığı için ertesi gün kendiliğinden göreve dönerdi: sistem
       bir kuralı hem uygular hem aynı anda geçersiz kılardı. Kurulun
       dediği "baş editörlüğü bittikten sonra kuralları yerine
       getirirse" cümlesi de zaten bunu söylüyor — SONRA. */
    function tg_atama_sayimi(array $yazilar, array $kesim = []): array {
        $out = [];
        foreach ($yazilar as $y) {
            if (!is_array($y)) continue;
            foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
                if (!is_array($h)) continue;
                /* Rapor yazılmamışsa atama tamamlanmamıştır */
                if (trim(tg_metin($h['rapor'] ?? '')) === '') continue;
                $a = $h['atayan'] ?? null;
                if (!is_array($a)) continue;
                $ad = tg_ad_anahtar(trim(tg_metin($a['ad'] ?? '')));
                if ($ad === '') continue;
                if (isset($kesim[$ad])) {
                    $g = substr(trim(tg_metin($a['tarih'] ?? '')), 0, 10);
                    /* Tarihsiz bir atama kesim sonrasına sayılamaz:
                       tarihi olmayan kayıt, olmayan tarihten daha
                       tehlikelidir çünkü hangi döneme ait olduğu
                       bilinemez. */
                    if ($g === '' || $g <= $kesim[$ad]) continue;
                }
                $out[$ad] = ($out[$ad] ?? 0) + 1;
            }
        }
        return $out;
    }

    /* Görevi bitmiş kişilerin bitiş günü: ad anahtarı => 'YYYY-AA-GG'.
       Bir kişi birden çok kez görevde bulunmuşsa en SON bitiş alınır. */
    function tg_gorev_bitis_haritasi(): array {
        static $h = null;
        if ($h !== null) return $h;
        $h = [];
        foreach (tg_onursal_bas_editorler() as $k) {
            $ad = tg_ad_anahtar(trim(tg_metin($k['ad'] ?? '')));
            $t  = trim(tg_metin($k['gorev_sonu_tarih'] ?? ''));
            if ($ad === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $t)) continue;
            if (!isset($h[$ad]) || $t > $h[$ad]) $h[$ad] = $t;
        }
        return $h;
    }

    /* Ölçüt ayarları, eksik yazılmışsa güvenli varsayılanlarla. */
    function tg_bas_olcut(): array {
        $o = (array)tg_ayar('bas_editor_olcut', []);
        return [
            'baslangic' => (string)($o['baslangic'] ?? '2028-01-01'),
            'atama'     => max(1, (int)($o['atama'] ?? 40)),
            'kurul'     => (bool)($o['kurul'] ?? true),
        ];
    }

    /* Ölçüt bugün işliyor mu? Başlangıç tarihinden önce hiç kimse
       bu yoldan baş editör olamaz; o güne kadar görev ayar.php'deki
       listededir. */
    function tg_bas_olcut_isliyor(): bool {
        $o = tg_bas_olcut();
        return date('Y-m-d') >= $o['baslangic'];
    }

    /* ---- ÇALIŞMANIN DİLİ ----
       Arayüzün dili ile çalışmanın dili ayrı şeylerdir. Bir araştırmacı
       kendi dilinde yazabilmelidir; o dilde arayüz olmasa bile. */
    function tg_calisma_dilleri(): array {
        $d = (array)tg_ayar('calisma_dilleri', []);
        if (!$d) $d = ['tr' => 'Türkçe', 'en' => 'English'];
        return $d;
    }

    /* ---- KÜNYE DİLİ ----
       Baş editör kurulunun belirlediği ikinci dil. Gönderim formundaki
       ikinci sekme budur. İstenen künyedir (başlık ve özet), metnin
       kendisi değil: kayıt her zaman yazarın yazdığı dildeki metindir.
       Tek kaynak burasıdır; sayfa da uç da buradan okur, yoksa "hangi
       dil" sorusu iki yerde iki ayrı yanıt alır. */
    function tg_kunye_dili(): string {
        $k = (array)tg_ayar('kunye_dili', []);
        $kod = mb_strtolower(trim(tg_metin($k['kod'] ?? 'en')), 'UTF-8');
        if ($kod === '') return '';
        return isset(tg_calisma_dilleri()[$kod]) ? $kod : '';
    }

    function tg_kunye_zorunlu(): bool {
        $k = (array)tg_ayar('kunye_dili', []);
        return tg_kunye_dili() !== '' && !empty($k['zorunlu']);
    }

    /* Künye dilinin kendi adı ('English', 'Türkçe'...). Dil adları
       listede KENDİ dillerinde yazılıdır ve öyle gösterilir. */
    /* DİL ADI ARAYÜZÜN DİLİNDE YAZILIR.
       ÖLÇÜLEN KUSUR — 14 Ağustos 2026, kurul bildirimi: "english demesin,
       İngilizce demesi lazım". Doğruydu. tg_calisma_dilleri() dil
       adlarını KENDİ dillerinde tutar (English, Deutsch, العربية) ve bu
       doğrudur — dil seçme listesinde herkes kendi dilinin adını kendi
       yazısıyla görmelidir. Ama bir Türkçe CÜMLENİN İÇİNDE o ad
       yabancı kalıyordu: "Çalışmanız English dışında bir dilde
       yazıldığı için..." Cümlenin dili neyse, içindeki dil adı da o
       dilde olmalı.

       Bu yüzden ad artık tg_dil_adi()'nden okunur: o işlev arayüz
       diline göre karşılığı verir. Karşılığı yoksa kendi adına düşer —
       bilinmeyen bir dilin adını uydurmak, yanlış yazmaktan kötüdür. */
    function tg_kunye_dil_adi(): string {
        $k = tg_kunye_dili();
        if ($k === '') return '';
        $ad = tg_dil_adi_yerel($k);
        return $ad !== '' ? $ad : (string)(tg_calisma_dilleri()[$k] ?? $k);
    }

    /* Bir dil adının ARAYÜZ DİLİNDEKİ karşılığı.
       Liste bilerek KISADIR: yalnızca künye dili olarak seçilebilecek
       yaygın diller. Kırk dilin hepsini iki dile elle çevirmek, çoğu
       hiç kullanılmayacak kırk çeviriyi bakıma sokmak olurdu; listede
       olmayan dil kendi adıyla yazılır ve bu yanlış değil, yalnızca
       çevrilmemiştir. Uydurmak yerine kendi adını yazmak doğrudur. */
    function tg_dil_adi_yerel(string $kod): string {
        $kod = tg_dil_kodu($kod);
        if ($kod === '') return '';
        $m = [
            'tr' => ['Türkçe', 'Turkish'],      'en' => ['İngilizce', 'English'],
            'ar' => ['Arapça', 'Arabic'],       'fr' => ['Fransızca', 'French'],
            'de' => ['Almanca', 'German'],      'es' => ['İspanyolca', 'Spanish'],
            'ru' => ['Rusça', 'Russian'],       'zh' => ['Çince', 'Chinese'],
            'fa' => ['Farsça', 'Persian'],      'ku' => ['Kürtçe', 'Kurdish'],
            'az' => ['Azerbaycanca', 'Azerbaijani'], 'uz' => ['Özbekçe', 'Uzbek'],
            'ky' => ['Kırgızca', 'Kyrgyz'],     'kk' => ['Kazakça', 'Kazakh'],
            'it' => ['İtalyanca', 'Italian'],   'pt' => ['Portekizce', 'Portuguese'],
            'ja' => ['Japonca', 'Japanese'],    'ko' => ['Korece', 'Korean'],
            'el' => ['Yunanca', 'Greek'],       'he' => ['İbranice', 'Hebrew'],
            'ur' => ['Urduca', 'Urdu'],         'hi' => ['Hintçe', 'Hindi'],
        ];
        return isset($m[$kod]) ? tg_c($m[$kod][0], $m[$kod][1]) : '';
    }

    /* ---- GENİŞLETİLMİŞ ÖZET ----
       Kurul kararı: çalışmanın dili künye dilinden başkaysa, künye
       dilinde GENİŞLETİLMİŞ bir özet istenir. Gerekçesi ayar.php'de
       yazılıdır; özeti şudur: iki yüz kelimelik bir künye, Arapça
       yazılmış bir çalışmanın ne yaptığını anlatmaz ve anlatmadığı
       sürece o çalışma fiilen görünmez kalır.

       DİL AYNIYSA İSTENMEZ: aynı metni iki kez istemek olurdu. */
    function tg_genis_ozet_ayar(): array {
        $k = (array)tg_ayar('kunye_dili', []);
        $g = (array)($k['genis_ozet'] ?? []);
        $az = (int)($g['en_az_kelime'] ?? 500);
        $hd = (int)($g['hedef_kelime'] ?? 750);
        return [
            'zorunlu'      => (bool)($g['zorunlu'] ?? false),
            'en_az_kelime' => $az > 0 ? $az : 500,
            'hedef_kelime' => $hd > $az ? $hd : ($az + 250),
        ];
    }

    /* Bu çalışma için genişletilmiş özet isteniyor mu?
       $dil: çalışmanın dili. Boşsa istenmez — bilinmeyen bir dile göre
       kural uygulanamaz ve uydurulmuş bir varsayım yazarı boşuna
       durdururdu. */
    function tg_genis_ozet_gerek(string $dil): bool {
        $k = tg_kunye_dili();
        if ($k === '') return false;
        $dil = tg_dil_kodu($dil);
        if ($dil === '') return false;
        if ($dil === $k) return false;
        return tg_genis_ozet_ayar()['zorunlu'];
    }

    /* ---- KELİME SAYIMI ----
       Boşlukla ayrılan diller için sözcük sayılır. Ama Çince, Japonca
       ve Korecede sözcükler boşlukla ayrılmaz: orada boşluk sayarsak
       bin karakterlik bir metin "1 kelime" çıkar ve yazar hiçbir zaman
       eşiği geçemez. O dillerde karakter sayılır ve ikiye bölünür
       (yerleşik yaklaşık karşılık).

       Bu, künye dili bir gün İngilizceden başkası olursa da doğru
       çalışsın diye yazıldı; bugün İngilizce için boşluk sayımı yeter. */
    function tg_kelime_say(string $metin): int {
        $m = trim(preg_replace('/\s+/u', ' ', strip_tags($metin)) ?? '');
        if ($m === '') return 0;
        $cjk = preg_match_all('/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $m);
        $harf = max(1, (int)mb_strlen($m, 'UTF-8'));
        if ($cjk > 0 && ($cjk / $harf) > 0.3) return (int)floor($cjk / 2);
        return count(preg_split('/ /u', $m, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }

    /* Gelen dil kodunu listeye karşı denetler. Tanınmayan bir kod
       reddedilmez, boşa düşer: kaydın dili bilinmiyor olabilir, ama
       kayda uydurma bir kod yazılmaz. */
    function tg_dil_kodu($ham): string {
        $k = mb_strtolower(trim(tg_metin($ham)), 'UTF-8');
        if ($k === '') return '';
        return isset(tg_calisma_dilleri()[$k]) ? $k : '';
    }

    /* Okurun o an gördüğü metnin dili. Arayüzün dili değildir: arayüz
       İngilizce olsa da, çalışmanın İngilizce sürümü yoksa okunan metin
       kendi dilindedir. Üstveride yanlış dil bildirmek, çalışmayı yanlış
       okur kitlesine göstermek demektir. */
    function tg_gorunen_dil(array $y, bool $en): string {
        /* Çeviri iki biçimde saklanabiliyor; okuma tek yerden yapılır
           (bkz. tg_yazi_ceviriler). Burada YAYIN olup olmadığına
           bakılmaz: okurun gördüğü metnin dili neyse odur, o metin bir
           okuma yardımı olsa bile. Yayın ayrımı üstveride yapılır. */
        $enVar = (bool)tg_yazi_ceviri($y, 'en');
        if ($en && $enVar) return 'en';
        $k = tg_yazi_dili($y);
        return $k !== '' ? $k : (k_dil());
    }

    /* İki harfli kodun üç harfli karşılığı (ISO 639-2/B).
       OAI-PMH ile toplayan dizinlerin bir bölümü üç harfli kod bekler;
       karşılığı bilinmeyen bir dil için iki harfli kod olduğu gibi
       verilir, çünkü yanlış bir kod vermektense eksik vermek yeğdir. */
    function tg_dil_iso3(string $kod): string {
        static $h = [
            'tr' => 'tur', 'en' => 'eng', 'ar' => 'ara', 'az' => 'aze', 'bg' => 'bul',
            'bn' => 'ben', 'cs' => 'cze', 'da' => 'dan', 'de' => 'ger', 'el' => 'gre',
            'es' => 'spa', 'fa' => 'per', 'fi' => 'fin', 'fr' => 'fre', 'he' => 'heb',
            'hi' => 'hin', 'hu' => 'hun', 'id' => 'ind', 'it' => 'ita', 'ja' => 'jpn',
            'ka' => 'geo', 'kk' => 'kaz', 'ko' => 'kor', 'ky' => 'kir', 'ms' => 'may',
            'nl' => 'dut', 'no' => 'nor', 'pl' => 'pol', 'pt' => 'por', 'ro' => 'rum',
            'ru' => 'rus', 'sq' => 'alb', 'sr' => 'srp', 'sv' => 'swe', 'sw' => 'swa',
            'th' => 'tha', 'tk' => 'tuk', 'uk' => 'ukr', 'ur' => 'urd', 'uz' => 'uzb',
            'vi' => 'vie', 'zh' => 'chi',
        ];
        $k = tg_dil_kodu($kod);
        if ($k === '') return '';
        return $h[$k] ?? $k;
    }

    /* =================================================================
       GÖREV NE ZAMAN BİTER
       -----------------------------------------------------------------
       İki gereklilik var ve ikisi birden karşılanmalıdır: sistemin
       yönetilmesi gerekir, ve bu görevi bugün taşıyan akademisyenlerin
       görevde kalması gerekir. Bırakma bir gün olacaktır, ama kendi
       elleriyle olacaktır.

       Bundan çıkan kural şudur: HİÇBİR GÖREV TAKVİMLE BİTMEZ. Bir
       tarihin gelmesi bir kişinin yetkisini sessizce almaz. Görev
       yalnızca YAZILI BİR KAYITLA biter ve o kaydı ya kişinin kendisi
       yazar ya da kurucuların çoğunluğu yazar.

       BEŞ YOL VARDIR. Dördü yazılı bir kayıttır; beşincisi bir sayımdır
       ve yalnızca ATANMIŞ baş editörlere işler (kurul kararı F1,
       14 Ağustos 2026). Beşi de görünürdür:

         1. DEVİR       Kişinin kendi kararı. Kayıtta 'devir' bloğu:
                        tarih ve devralanın adı. "Kendi hakkını
                        devretmek" budur.
         2. VEFAT       Kayıtta 'vefat' bloğu: tarih. Bu bir karar
                        değil, bir olgudur; oy istemez, yazılır.
         3. KARAR       Kurucuların çoğunluk kararı. Kayıtta
                        'gorev_sonu' bloğu: tarih ve karara katılan
                        kurucuların adları. En az üç kurucu adı yoksa
                        karar yok sayılır ve kişi görevde kalır; eksik
                        karar, kaydın hatası olarak görünür.
         4. YAZILI SÜRE Atama sırasında bilerek yazılmış bitiş tarihi.
                        ARTIK ZORUNLU DEĞİLDİR. Yazılmışsa o gün görev
                        biter, çünkü kişi göreve o şartla gelmiştir;
                        yazılmamışsa görev sürer.
         5. ETKİNLİK    Atanan baş editörün görevi bir yıllık dönemlere
                        bölünür. Dönem sonunda etkinlik ölçütü
                        karşılanmamışsa görev O GÜN biter. Ölçüt ve
                        gerekçesi ayar.php'deki 'bas_editor_gorev'
                        bloğundadır; sayım tg_donem_olcumu()'ndedir.
                        KURUCULARA İŞLEMEZ: kurucu baş editörlük bir
                        görev değil bir kayıttır ve ölçüme bağlanamaz.
                        Bu yol bir görevden alma değildir; kimse kimseyi
                        almaz, sayım yapılır ve sayım herkese açıktır.
                        Hedef sabit değil tavandır: sistemin o dönemde
                        ürettiği iş azsa hedef de iner, çünkü yapılacak
                        iş yokken "iş yapmadın" demek ölçmek değildir.

       Bu beş yoldan biriyle görevi biten kişi ONURSAL BAŞ EDİTÖR olarak
       anılır. Onursallık yine VERİLMEZ: kimsenin onu birine bahşetme
       yetkisi yoktur, devralacak kurumun da yoktur. Ölçüt geçmiş bir
       görevdir ve geçmiş satın alınamaz.

       'editor_yetki_bitis' ARTIK GÖREVİ BİTİRMEZ. O alan yalnızca bir
       şeyi kapatır: editör listesine ekleme ve çıkarma. Kişi kurul
       sayfasındaki yerinde durur, hakem atamayı ve editöryal not
       düşmeyi sürdürür. Bu ayrım ayar.php'de ve hocalara giden karar
       belgesinde zaten yazılıydı; kod ise iki alanı tek yerden okuyup
       ikisini de görevi bitiren bir tarih sayıyordu. Yani sistem, yazılı
       olarak verdiği sözün tersini yapmaya hazır duruyordu. Burada
       düzeltilen budur.
       ================================================================= */

    /* Görevi sonlandıran çoğunluk kararı kaç ad ister? Atama için
       yazılı olan sayı neyse bu da odur: bir yetkiyi vermek ile almak
       aynı ağırlıkta olmalıdır. Yapılandırma DOĞRUDAN okunur;
       tg_bas_atama() üzerinden okunsaydı görev durumu ile atama düzeni
       birbirini çağırırdı.

       Bu karar yolu TARİHE BAĞLI DEĞİLDİR ve her zaman açıktır. Atama
       yetkisi 2027 sonunda düşer, ama bir kurulun kendi üyesinin
       görevini sonlandırabilmesi bir yetki dağıtımı değil, bir denetim
       aracıdır; kapandığı gün kurul kendini denetleyemez hâle gelir. */
    function tg_karar_yeter_sayisi(): int {
        $a = (array)tg_ayar('bas_editor_atama', []);
        return max(1, (int)($a['oy_gerekli'] ?? 3));
    }

    /* Kurucu adlarının ve e-posta adreslerinin anahtar kümesi.
       Ayar dosyasından DOĞRUDAN okunur, tg_kurucu_bas_editorler()
       üzerinden değil: o işlev her kayıt için görev durumunu hesaplar,
       görev durumu da karar sayarken kurucu listesine bakar. İkisi
       birbirini çağırsaydı sonsuz döngü olurdu. Sayım için gereken
       yalnızca adlardır, görev durumu değildir. */
    function tg_kurucu_anahtarlari(): array {
        static $c = null;
        if ($c !== null) return $c;
        $c = [];
        foreach ((array)tg_ayar('bas_editorler', []) as $b) {
            if (!is_array($b)) continue;
            $ad = trim(tg_metin($b['ad'] ?? ''));
            if ($ad === '') continue;
            $c[tg_ad_anahtar($ad)] = true;
            $ep = tg_eposta_anahtar(tg_metin($b['eposta'] ?? ''));
            if ($ep !== '') $c[$ep] = true;
            /* Kayıtta düz adres yoksa özeti de anahtar olur: karar
               kaydına bir kurucu adres yazıldığında, ayar dosyasında
               yalnızca özet dururken de sayılabilsin. Karar veren
               adının özeti tg_gorev_durumu() içinde alınır. */
            $oz = strtolower(trim(tg_metin($b['eposta_ozet'] ?? '')));
            if (preg_match('/^[a-f0-9]{64}$/', $oz)) $c['ozet:' . $oz] = true;
        }
        return $c;
    }

    /* Bir tarih alanı yazılı ve geçmiş mi? */
    function tg_gecmis_tarih(string $t): bool {
        $t = trim($t);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) === 1 && date('Y-m-d') > $t;
    }

    /* =================================================================
       GÖREV DÖNEMİ VE ETKİNLİK ÖLÇÜTÜ  (kurul kararı F1, 14/08/2026)
       -----------------------------------------------------------------
       Atanan bir baş editörün görevi BİR YILLIK DÖNEMLERE bölünür.
       Dönem sonunda ölçüm yapılır: ölçüt karşılanmışsa görev
       kendiliğinden sürer, karşılanmamışsa o gün biter ve kişi onursal
       listeye geçer. Kuruculara uygulanmaz — kurucu baş editörlük bir
       görev değil, bir kayıttır.

       ÖLÇÜM ADİL OLMAK ZORUNDADIR. Hiç çalışma gelmemiş bir yılda hakem
       atanamaz; hedef bu yüzden bir TAVANDIR ve sistemin o dönemde
       ürettiği işe göre iner. Yapılacak iş yokken "iş yapmadın" demek
       ölçmek değil, cezalandırmaktır ve bu sistemde ölçülemeyen bir
       eşik duyurulmaz (bkz. benzerlik raporu kararı).

       SAYILAN NEDİR. Hakem ataması ve editöryal işlem günü. Yalnız
       "giriş" sayılmaz: girmek katkı değil, katkının önkoşuludur.
       ================================================================= */

    /* Ölçütün okunmuş ve sağlığa çekilmiş hâli. Tek kaynak burasıdır. */
    function tg_gorev_olcut(): array {
        $a = (array)tg_ayar('bas_editor_gorev', []);
        $ay = (int)($a['sure_ay'] ?? 12);
        return [
            'sure_ay'    => $ay > 0 ? $ay : 12,
            'atama'      => max(0, (int)($a['atama'] ?? 12)),
            'islem_gunu' => max(0, (int)($a['islem_gunu'] ?? 24)),
            'kurucu'     => (bool)($a['kurucu'] ?? false),
        ];
    }

    /* Bir kişinin editöryal işlemleri: ['atama' => [Y-m-d, ...],
       'islem' => [Y-m-d => true, ...]]. Kayıtlardan anlık okunur;
       ayrı bir sayaç tutulmaz, çünkü tutulan bir sayaç kayıtla
       ayrışabilir ve ayrıştığı gün hangisinin doğru olduğu bilinemez. */
    function tg_editoryal_islemler(string $ad): array {
        static $bellek = [];
        $an = tg_ad_anahtar($ad);
        if ($an === '') return ['atama' => [], 'islem' => []];
        if (isset($bellek[$an])) return $bellek[$an];
        $atama = []; $islem = [];
        $gun = static function ($t): string {
            $t = trim(tg_metin($t));
            if ($t === '') return '';
            $g = substr($t, 0, 10);
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $g) ? $g : '';
        };
        foreach (tg_yazilar_oku() as $y) {
            if (!is_array($y)) continue;
            foreach ((array)($y['hakemler'] ?? []) as $h) {
                $at = is_array($h['atayan'] ?? null) ? $h['atayan'] : [];
                /* Yazarın kendi önerisi editöryal iş değildir. */
                if ((string)($at['tur'] ?? '') === 'yazar') continue;
                if (tg_ad_anahtar((string)($at['ad'] ?? '')) !== $an) continue;
                $g = $gun($at['tarih'] ?? '');
                if ($g === '') continue;
                $atama[] = $g; $islem[$g] = true;
            }
            foreach ((array)($y['editor_notlari'] ?? []) as $n) {
                if (!is_array($n)) continue;
                if (tg_ad_anahtar((string)($n['kim'] ?? '')) !== $an) continue;
                $g = $gun($n['tarih'] ?? '');
                if ($g !== '') $islem[$g] = true;
            }
            foreach ((array)($y['hakem_onerileri'] ?? []) as $o) {
                if (!is_array($o)) continue;
                if (tg_ad_anahtar((string)($o['karar_veren'] ?? '')) !== $an) continue;
                $g = $gun($o['karar_tarih'] ?? ($o['tarih'] ?? ''));
                if ($g !== '') $islem[$g] = true;
            }
        }
        sort($atama);
        return $bellek[$an] = ['atama' => $atama, 'islem' => $islem];
    }

    /* Sistemin o pencerede ürettiği toplam hakem ataması. Hedefin
       tavanı buradan iner: kimse yapılmamış bir işten sorumlu değildir. */
    function tg_sistem_atama_sayisi(string $bas, string $son): int {
        $n = 0;
        foreach (tg_yazilar_oku() as $y) {
            if (!is_array($y)) continue;
            foreach ((array)($y['hakemler'] ?? []) as $h) {
                $at = is_array($h['atayan'] ?? null) ? $h['atayan'] : [];
                if ((string)($at['tur'] ?? '') === 'yazar') continue;
                $g = substr(trim(tg_metin($at['tarih'] ?? '')), 0, 10);
                if ($g >= $bas && $g < $son) $n++;
            }
        }
        return $n;
    }

    function tg_tarih_ekle_ay(string $tarih, int $ay): string {
        $z = date_create_immutable($tarih . ' 00:00:00');
        if (!$z) return '';
        return $z->add(new DateInterval('P' . max(1, $ay) . 'M'))->format('Y-m-d');
    }

    /* Bir dönemin ölçümü. Dönen:
       ['karsilandi'=>bool,'atama'=>n,'hedef'=>n,'gun'=>n,'gun_hedef'=>n,
        'sebep'=>'atama|islem|is-yok|karsilanmadi'] */
    function tg_donem_olcumu(string $ad, string $bas, string $son): array {
        $o  = tg_gorev_olcut();
        $iz = tg_editoryal_islemler($ad);
        $atama = 0;
        foreach ($iz['atama'] as $g) if ($g >= $bas && $g < $son) $atama++;
        $gun = 0;
        foreach (array_keys($iz['islem']) as $g) if ($g >= $bas && $g < $son) $gun++;

        /* Hedefin tavanı: sistemin o dönemde ürettiği iş, görevdeki
           kişi sayısına bölünür. Beş kişilik bir kurulda on atama
           yapılmışsa kimseden on iki atama beklenemez. */
        $koltuk  = max(1, tg_kurul_koltuk());
        $sistem  = tg_sistem_atama_sayisi($bas, $son);
        $hedef   = min($o['atama'], intdiv($sistem, $koltuk));

        if ($hedef <= 0) {
            /* Yapılacak iş yoktu. Dönem sorgusuz sürer; bu bir aklama
               değil, ölçülemeyen bir şeyi ölçmüş gibi yapmamaktır. */
            return ['karsilandi' => true, 'atama' => $atama, 'hedef' => 0,
                    'gun' => $gun, 'gun_hedef' => $o['islem_gunu'], 'sebep' => 'is-yok'];
        }
        if ($atama >= $hedef) {
            return ['karsilandi' => true, 'atama' => $atama, 'hedef' => $hedef,
                    'gun' => $gun, 'gun_hedef' => $o['islem_gunu'], 'sebep' => 'atama'];
        }
        if ($o['islem_gunu'] > 0 && $gun >= $o['islem_gunu']) {
            return ['karsilandi' => true, 'atama' => $atama, 'hedef' => $hedef,
                    'gun' => $gun, 'gun_hedef' => $o['islem_gunu'], 'sebep' => 'islem'];
        }
        return ['karsilandi' => false, 'atama' => $atama, 'hedef' => $hedef,
                'gun' => $gun, 'gun_hedef' => $o['islem_gunu'], 'sebep' => 'karsilanmadi'];
    }

    /* Kişinin dönem çizelgesi. Kurucularda ve atama tarihi olmayan
       kayıtlarda ölçüm YAPILMAZ; ölçülemeyen bir kural uygulanmaz.
       Dönen: ['olculdu'=>bool,'bitti'=>bool,'tarih'=>'','donemler'=>[...],
               'suren_bitis'=>'','hata'=>''] */
    function tg_gorev_donemleri($kisi): array {
        $bos = ['olculdu' => false, 'bitti' => false, 'tarih' => '',
                'donemler' => [], 'suren_bitis' => '', 'hata' => '', 'uyari' => ''];
        if (!is_array($kisi)) return $bos;
        $o = tg_gorev_olcut();
        if (!empty($kisi['kurucu']) && !$o['kurucu']) return $bos;

        $ad  = trim(tg_metin($kisi['ad'] ?? ''));
        $bas = trim(tg_metin($kisi['atama_tarih'] ?? ''));
        if ($ad === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bas)) {
            /* Atama tarihi yoksa saat başlamamıştır. Bu bir UYARIDIR,
               hata değildir — ve aradaki fark ölçülerek öğrenildi.

               İlk yazımda 'hata' döndürüyordu. Hata, tg_gorev_kaydi_suz()
               içinde kaydı DÜŞÜRÜR: yani atama tarihi yazılmamış her
               baş editör kuruldan siliniyordu. Ölçüm bunu on yedi kusur
               olarak gösterdi ve haklıydı. Eksik bir alan yüzünden bir
               kişiyi kuruldan silmek, o alanı istemekten çok daha ağır
               bir şeydir; kural görevi ölçmek için kondu, görevi
               düşürmek için değil.

               Ama sessiz de kalmaz: alanı boş bırakmak ölçütü kapatmanın
               en kolay yolu olurdu. Uyarı kurul sayfasında görünür,
               kişi görevde kalır, saat yazıldığı gün başlar.

               (Ayrıca: burada "$bos + [...]" yazılıydı; PHP'de dizi
               toplama var olan anahtarı korur ve metin sessizce
               düşüyordu.) */
            $c = $bos;
            $c['uyari'] = ($ad === '' ? '' :
                'Atama tarihi yazılmamış ya da biçimi hatalı; görev süresi ölçülemiyor: ' . $ad);
            return $c;
        }
        $bugun = date('Y-m-d');
        $donem = []; $adim = 0;
        while ($adim++ < 200) {
            $son = tg_tarih_ekle_ay($bas, $o['sure_ay']);
            if ($son === '') break;
            if ($son > $bugun) {
                return ['olculdu' => true, 'bitti' => false, 'tarih' => '',
                        'donemler' => $donem, 'suren_bitis' => $son, 'hata' => ''];
            }
            $ol = tg_donem_olcumu($ad, $bas, $son) + ['bas' => $bas, 'son' => $son];
            $donem[] = $ol;
            if (!$ol['karsilandi']) {
                return ['olculdu' => true, 'bitti' => true, 'tarih' => $son,
                        'donemler' => $donem, 'suren_bitis' => '', 'hata' => ''];
            }
            $bas = $son;
        }
        return ['olculdu' => true, 'bitti' => false, 'tarih' => '',
                'donemler' => $donem, 'suren_bitis' => '', 'hata' => ''];
    }

    /* Görevin durumu. Dönen:
         ['bitti'=>bool, 'tarih'=>'', 'yol'=>'devir|karar|sure|etkinlik', 'hata'=>''] */
    function tg_gorev_durumu($kisi): array {
        $bos = ['bitti' => false, 'tarih' => '', 'yol' => '', 'hata' => ''];
        if (!is_array($kisi)) return $bos;

        /* 1. Kendi devri. */
        $d = is_array($kisi['devir'] ?? null) ? $kisi['devir'] : [];
        $dt = trim(tg_metin($d['tarih'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dt) && date('Y-m-d') >= $dt) {
            return ['bitti' => true, 'tarih' => $dt, 'yol' => 'devir', 'hata' => ''];
        }

        /* 2. Vefat. Bir olgudur, oy istemez. */
        $v = is_array($kisi['vefat'] ?? null) ? $kisi['vefat'] : [];
        $vt = trim(tg_metin($v['tarih'] ?? (is_string($kisi['vefat'] ?? null) ? $kisi['vefat'] : '')));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vt) && date('Y-m-d') >= $vt) {
            return ['bitti' => true, 'tarih' => $vt, 'yol' => 'vefat', 'hata' => ''];
        }

        /* 3. Kurucuların çoğunluk kararı. Adlar sayılır ve
              tekilleştirilir; yalnızca kurucu adları geçerlidir. */
        $g = is_array($kisi['gorev_sonu'] ?? null) ? $kisi['gorev_sonu'] : [];
        $gt = trim(tg_metin($g['tarih'] ?? ''));
        if ($gt !== '') {
            $kurucu = tg_kurucu_anahtarlari();
            $sayilan = [];
            foreach ((array)($g['karar_veren'] ?? []) as $ad) {
                $ad = trim(tg_metin($ad));
                if ($ad === '') continue;
                $an = tg_ad_anahtar($ad);
                $ep = tg_eposta_anahtar($ad);
                /* Üçüncü ölçüt, adres özetidir: ayar dosyasında düz
                   adres durmuyorsa kurucu yalnızca özetiyle tanınır. */
                $oz = 'ozet:' . tg_eposta_ozet($ad);
                $bul = isset($kurucu[$an]) ? $an
                     : (isset($kurucu[$ep]) ? $ep
                     : (isset($kurucu[$oz]) ? $oz : ''));
                if ($bul !== '') $sayilan[$bul] = true;
            }
            $yeter = tg_karar_yeter_sayisi();
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $gt)) {
                return ['bitti' => false, 'tarih' => '', 'yol' => '',
                        'hata' => 'Görev sonu kaydında tarih biçimi hatalı: ' . tg_metin($kisi['ad'] ?? '')];
            }
            if (count($sayilan) < $yeter) {
                /* Eksik karar görevi bitirmez. Sessizce yutulmaz da:
                   yapılandırma hatası kurul sayfasında görünür. */
                return ['bitti' => false, 'tarih' => '', 'yol' => '',
                        'hata' => 'Görev sonu kararında en az ' . $yeter . ' kurucu adı gerekir; sayılan: '
                                . count($sayilan) . ' (' . tg_metin($kisi['ad'] ?? '') . ')'];
            }
            if (date('Y-m-d') >= $gt) {
                return ['bitti' => true, 'tarih' => $gt, 'yol' => 'karar', 'hata' => ''];
            }
        }

        /* 4. Atamada bilerek yazılmış bitiş tarihi. */
        $b = trim(tg_metin($kisi['gorev_bitis'] ?? ''));
        if (tg_gecmis_tarih($b)) {
            return ['bitti' => true, 'tarih' => $b, 'yol' => 'sure', 'hata' => ''];
        }

        /* 5. ETKİNLİK ÖLÇÜTÜ (kurul kararı F1). Bir yıllık dönem
              sonunda ölçüt karşılanmamışsa görev o gün biter. Beşinci
              yol öteki dördünden SONRA bakılır: yazılı bir karar,
              sayımla varılan bir sonuçtan önce gelir. Kuruculara
              uygulanmaz. */
        $dn = tg_gorev_donemleri($kisi);
        if ($dn['olculdu'] && $dn['bitti']) {
            return ['bitti' => true, 'tarih' => $dn['tarih'], 'yol' => 'etkinlik', 'hata' => ''];
        }
        /* Uyarı görevi bitirmez ve kaydı düşürmez; yalnız görünür. */
        if (($dn['uyari'] ?? '') !== '') {
            return ['bitti' => false, 'tarih' => '', 'yol' => '', 'hata' => '', 'uyari' => $dn['uyari']];
        }
        return $bos;
    }

    /* =================================================================
       BİR KİŞİNİN GÖREV GEÇMİŞİ  (kurul kararı F1'in son cümlesi)
       -----------------------------------------------------------------
       "Hangi yıllar baş editörlük yaptığı, hangi çalışmalarda yer aldığı
        — hakem, editör, baş editör olarak — profil kartında gösterilir."

       Bu bir övgü listesi değil, bir hesap verme aracıdır: bu sistemde
       hakemlik zaten adıyla açıktır, editörlük de öyle olmalıdır.
       Kim hangi çalışmaya hangi hakemi atadı sorusunun yanıtı arşivde
       zaten duruyordu; duran ama toplanmayan bir kayıt, okurun eline
       geçmediği sürece açıklık sayılmaz.

       Dönen:
         ['gorev'  => [['bas','son','kurucu','yol','suren']...],
          'atama'  => n,      yaptığı hakem ataması
          'gun'    => n,      editöryal işlem yapılan ayrı gün
          'ilk'    => 'Y-m-d', 'son' => 'Y-m-d',
          'isler'  => [ ['yol','baslik','tarih'] ... ]  editörlük ettiği çalışmalar
         ]
       ================================================================= */
    function tg_gorev_gecmisi(string $ad): array {
        $an = tg_ad_anahtar($ad);
        $out = ['gorev' => [], 'atama' => 0, 'gun' => 0, 'ilk' => '', 'son' => '', 'isler' => []];
        if ($an === '') return $out;

        foreach (array_merge(tg_kurucu_bas_editorler(), tg_atanmis_bas_editorler()['kayit']) as $k) {
            if (tg_ad_anahtar((string)($k['ad'] ?? '')) !== $an) continue;
            $kurucu = !empty($k['kurucu']);
            /* Kuruluş kaydında atama tarihi YOKTUR ve uydurulmaz:
               kurucular atanmadı, sistem onlarla başladı. Boş kalır ve
               sayfa bunu "kuruluştan bu yana" diye okur. */
            $bas = trim(tg_metin($k['atama_tarih'] ?? ''));
            $son = trim(tg_metin($k['gorev_sonu_tarih'] ?? ''));
            $out['gorev'][] = [
                'bas'    => preg_match('/^\d{4}-\d{2}-\d{2}$/', $bas) ? $bas : '',
                'son'    => $son,
                'kurucu' => $kurucu,
                'yol'    => (string)($k['gorev_sonu_yol'] ?? ''),
                'suren'  => $son === '',
            ];
        }

        $iz = tg_editoryal_islemler($ad);
        $out['atama'] = count($iz['atama']);
        $out['gun']   = count($iz['islem']);
        $gunler = array_keys($iz['islem']);
        sort($gunler);
        $out['ilk'] = (string)($gunler[0] ?? '');
        $out['son'] = (string)($gunler[count($gunler) - 1] ?? '');

        foreach (tg_yazilar_oku() as $y) {
            if (!is_array($y)) continue;
            $var = false;
            foreach ((array)($y['hakemler'] ?? []) as $h) {
                $at = is_array($h['atayan'] ?? null) ? $h['atayan'] : [];
                if ((string)($at['tur'] ?? '') === 'yazar') continue;
                if (tg_ad_anahtar((string)($at['ad'] ?? '')) === $an) { $var = true; break; }
            }
            if (!$var) foreach ((array)($y['editor_notlari'] ?? []) as $n) {
                if (is_array($n) && tg_ad_anahtar((string)($n['kim'] ?? '')) === $an) { $var = true; break; }
            }
            if (!$var) continue;
            $out['isler'][] = [
                'yol'     => tg_yazi_yolu($y),
                'baslik'  => trim(tg_metin($y['baslik'] ?? '')),
                'tarih'   => substr(trim(tg_metin($y['tarih'] ?? '')), 0, 10),
            ];
        }
        usort($out['isler'], static fn($a, $b) => strcmp((string)$b['tarih'], (string)$a['tarih']));
        return $out;
    }

    /* Görevin bittiği gün. Sürüyorsa boş. */
    function tg_gorev_bitis($kisi): string {
        return (string)tg_gorev_durumu($kisi)['tarih'];
    }

    function tg_onursal_mu($kisi): bool {
        return (bool)tg_gorev_durumu($kisi)['bitti'];
    }

    /* Editör listesini değiştirme yetkisinin son günü. Yalnızca bu
       yetkiyi kapatır; göreve dokunmaz. */
    function tg_editor_yetki_bitis($kisi): string {
        if (!is_array($kisi)) return '';
        $b = trim(tg_metin($kisi['editor_yetki_bitis'] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) ? $b : '';
    }

    /* =================================================================
       DEVİR: SİSTEMİ KİM DEVRALDI
       -----------------------------------------------------------------
       Kurucuların yetkilerinin 31 Aralık 2027'de kapanması yazılıdır ve
       yerinde durur. Ancak bu kapanmanın bir şartı vardır: DEVRALAN
       BİRİNİN OLMASI. Yoksa kapanma sistemi yönetimsiz bırakırdı ve
       yönetimsiz bir yayın sistemi, hakem atayamayan, karar veremeyen,
       düzeltme yayımlayamayan bir arşiv demektir.

       Devralan sayılan iki durum vardır:
         1. Sistem bir kuruma, derneğe ya da vakfa devredilmiştir
            ('devir' bloğu yazılıdır ve türü 'proje' değildir).
         2. Kurucu olmayan, görevde en az bir baş editör vardır; yani
            görev fiilen taşınmaya başlamıştır.

       PROJE DEVİR DEĞİLDİR. Sistemin bir projeye dönüşmesi, bir fona
       bağlanması ya da bir kurumun çatısı altında yürütülmesi tek
       başına devir sayılmaz; sorumluluğu üstlenen bir devralan yoksa
       kurucuların yetkisi aynı şekilde sürer.

       Bu sayım anlıktır, bir kez yazılıp bırakılmaz. Devralan bir gün
       görevi bırakırsa yetki kurucularda yeniden açılır; sistem hiçbir
       anda yönetimsiz kalmaz.
       ================================================================= */
    function tg_devir_kaydi(): array {
        $d = (array)tg_ayar('devir', []);
        return [
            'tarih'    => trim(tg_metin($d['tarih'] ?? '')),
            'devralan' => trim(tg_metin($d['devralan'] ?? '')),
            'tur'      => mb_strtolower(trim(tg_metin($d['tur'] ?? '')), 'UTF-8'),
        ];
    }

    function tg_devir_oldu(): bool {
        $d = tg_devir_kaydi();
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['tarih'])) return false;
        if (date('Y-m-d') < $d['tarih']) return false;
        if ($d['devralan'] === '') return false;
        if ($d['tur'] === 'proje') return false;   /* proje devir değildir */
        return true;
    }

    /* Görevi devralan var mı: kurum devri ya da kurucu olmayan görevdeki
       bir baş editör. */
    function tg_devralan_var(): bool {
        if (tg_devir_oldu()) return true;
        return tg_gorevdeki_bas_editorler() !== [];
    }

    /* =================================================================
       BAŞ EDİTÖRLÜĞÜN ÜÇ BASAMAĞI
       -----------------------------------------------------------------
       Üç ayrı şey vardır ve üçü birbirinin yerine geçmez:

         KURUCU    Bir kayıttır. ayar.php'deki 'bas_editorler' listesinde
                   ve yalnızca orada durur. Sıra kayıt sırasıdır ve
                   değişmez; bir kaydın sırası zaman sırasıdır, bir
                   derece değil. Sonradan hiç kimseye verilemez ve hiçbir
                   koşulda düşmez: kişi görevi bıraksa da kurucu kaydı
                   yerinde kalır, çünkü sistemi kimin kurduğu olmuş bir
                   şeydir.
         GÖREVDEKİ Bir yetkidir. Kurucular ve ayar.php'deki
                   'gorevdeki_bas_editorler' listesinde olup görevi
                   sürenler. Görev SÜRESİZDİR; yazılı bir kayıtla biter
                   (kendi devri, kurucuların çoğunluk kararı ya da
                   atamada bilerek yazılmış bitiş tarihi).
                   Sonradan atananlar kendi aralarında alfabetiktir.
         ONURSAL   Bir teşekkürdür. Verilmez; görev bittiğinde kalır.
                   Hiçbir yetki taşımaz. Kurucu da olabilir: görevini
                   devreden kurucu hem kurucu kaydında durur hem onursal
                   baş editör olarak anılır.

       Sıra: önce kurucular, sonra öteki baş editörler, sonra onursallar.

       Aşağıdaki işlevler bu üç kümenin TEK kaynağıdır. Bir sayfa kendi
       listesini kurmaz; kurarsa üç küme zamanla birbirine karışır.
       ================================================================= */

    /* Kurucu baş editörler. Sıralama YAPILMAZ: liste kayıt sırasındadır.
       Bir kaydı alfabetik diziye sokmak, olmuş bir şeyin sırasını
       değiştirmek olurdu. Görevini bırakmış olan da bu listededir;
       'gorevde' alanı bugün yetkisi olup olmadığını söyler. */
    function tg_kurucu_bas_editorler(): array {
        $out = [];
        foreach ((array)tg_ayar('bas_editorler', []) as $b) {
            if (!is_array($b)) continue;
            if (trim(tg_metin($b['ad'] ?? '')) === '') continue;
            $b['kurucu'] = true;
            $b['sinif']  = 'kurucu';
            $d = tg_gorev_durumu($b);
            $b['gorevde']     = !$d['bitti'];
            $b['gorev_sonu_tarih'] = (string)$d['tarih'];
            $b['gorev_sonu_yol']   = (string)$d['yol'];
            $out[] = $b;
        }
        return $out;
    }

    /* ---- KAPI: sonradan atanan bir kayıt kurucu olamaz ----
       Bu işlev, 'gorevdeki_bas_editorler' listesindeki bir satırı göreve
       almadan önce süzer. Üç şey yapar ve üçü de bilerek serttir:

         1. 'kurucu' alanı ne yazarsa yazsın düşürülür. Kurucu sıfatı
            bir yetki değil bir kayıttır; sonradan yazılamaz. Bir atama
            ekranı yanlışlıkla kurucu yazabiliyorsa yanlış yazılmıştır,
            ve buradan geçemez.
         2. Yazılı bitiş tarihi ARTIK ZORUNLU DEĞİLDİR. Görev süresizdir
            ve yazılı bir kayıtla biter. Bir tarih yazılmışsa biçimi
            denetlenir ve o gün görev biter; yazılmamışsa görev sürer.
            Bozuk biçimli bir tarih kabul edilmez: yanlış yazılmış bir
            tarih, yazılmamış bir tarihten tehlikelidir, çünkü görevin
            ne zaman bittiği okunamaz.
         3. Uyruk, ülke ve kurum şartı taşıyan alanlar düşürülür.
            Sekiz değiştirilemez ilkenin sonuncusu budur: sistem hiçbir
            ülkeye, kuruma, dile ya da kişiye ayrıcalık tanımaz,
            hiçbirini de dışlamaz.

       Dönen: ['ok'=>bool, 'hata'=>metin, 'kayit'=>süzülmüş kayıt]. */
    function tg_gorev_kaydi_suz($ham): array {
        if (!is_array($ham)) return ['ok' => false, 'hata' => 'Kayıt bir dizi değil.', 'kayit' => []];
        $k = $ham;
        /* 1. Kurucu sıfatı buradan verilemez. */
        unset($k['kurucu']);
        /* 3. Uyruk ve kurum sınırı yazılamaz. */
        foreach (['uyruk', 'uyruk_sarti', 'ulke_sarti', 'kurum_sarti', 'ulke_sinir'] as $yasak) unset($k[$yasak]);
        $ad = trim(tg_metin($k['ad'] ?? ''));
        if ($ad === '') return ['ok' => false, 'hata' => 'Kayıtta ad yok.', 'kayit' => []];
        $k['ad'] = $ad;
        /* 2. Bitiş tarihi yazılıysa biçimi doğru olmalıdır. */
        $bitis = trim(tg_metin($k['gorev_bitis'] ?? ''));
        if ($bitis !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $bitis)) {
            return ['ok' => false, 'hata' => 'Görev bitiş tarihi biçimi hatalı (YYYY-AA-GG bekleniyor): ' . $ad, 'kayit' => []];
        }
        $k['gorev_bitis'] = $bitis;
        $d = tg_gorev_durumu($k);
        if ($d['hata'] !== '') return ['ok' => false, 'hata' => $d['hata'], 'kayit' => []];
        $k['sinif'] = $d['bitti'] ? 'onursal' : 'gorevdeki';
        $k['gorev_sonu_tarih'] = (string)$d['tarih'];
        $k['gorev_sonu_yol']   = (string)$d['yol'];
        /* UYARI KAYDI DÜŞÜRMEZ. Eksik atama tarihi görevi ölçülemez
           kılar ama görevi bitirmez; kişi kurulda kalır, eksiklik
           kurul sayfasında ayrı bir küme olarak görünür. */
        $k['uyari'] = (string)($d['uyari'] ?? '');
        return ['ok' => true, 'hata' => '', 'kayit' => $k, 'uyari' => $k['uyari']];
    }

    /* Süzgeçten geçmiş bütün sonradan atanmış kayıtlar (görevdeki ve
       onursal birlikte). Süzgeci geçemeyen kayıtlar düşer ve neden
       düştükleri 'tg_gorev_kayit_hatalari()' ile okunabilir; sessizce
       yutulan bir yapılandırma hatası, olmayan bir hatadan kötüdür. */
    function tg_atanmis_bas_editorler(): array {
        static $c = null;
        if ($c !== null) return $c;
        $c = ['kayit' => [], 'hata' => [], 'uyari' => []];
        foreach ((array)tg_ayar('gorevdeki_bas_editorler', []) as $b) {
            $s = tg_gorev_kaydi_suz($b);
            if ($s['ok']) {
                $c['kayit'][] = $s['kayit'];
                if (trim((string)($s['uyari'] ?? '')) !== '') $c['uyari'][] = (string)$s['uyari'];
            } elseif ($s['hata'] !== '') $c['hata'][] = $s['hata'];
        }
        return $c;
    }

    function tg_gorev_kayit_hatalari(): array { return tg_atanmis_bas_editorler()['hata']; }

    /* Kaydı düşürmeyen ama görünmesi gereken eksikler. Ayrı okunur:
       hata ile uyarıyı aynı listeye koymak, birini ötekinin ağırlığıyla
       okutur — ya hatalar hafifler ya uyarılar korkutur. */
    function tg_gorev_kayit_uyarilari(): array { return tg_atanmis_bas_editorler()['uyari'] ?? []; }

    /* Adlara göre alfabetik sıralama. Türkçe harf sırası için
       yerelleştirilmiş karşılaştırma varsa o kullanılır. */
    function tg_ad_sirala(array $liste): array {
        usort($liste, function ($a, $b) {
            $x = trim(tg_metin($a['ad'] ?? '')); $y = trim(tg_metin($b['ad'] ?? ''));
            if (class_exists('Collator')) {
                $c = new Collator('tr_TR');
                $s = $c->compare($x, $y);
                if ($s !== false) return (int)$s;
            }
            return strcmp(tg_ad_anahtar($x), tg_ad_anahtar($y));
        });
        return $liste;
    }

    /* Görevdeki baş editörler: görevi süren atanmışlar, alfabetik.
       Kurucular bu kümede DEĞİLDİR; kurucular kendi kümesinde durur ve
       ikisi birlikte tg_bas_editorler_gorevde() ile alınır. */
    function tg_gorevdeki_bas_editorler(): array {
        $out = [];
        foreach (tg_atanmis_bas_editorler()['kayit'] as $k) {
            if ($k['sinif'] === 'gorevdeki') $out[] = $k;
        }
        return tg_ad_sirala($out);
    }

    /* Onursal baş editörler: görevi bitmiş HERKES, kurucu olsun ya da
       olmasın. Görevini devreden ya da vefat eden kurucu da buraya
       yazılır; kurucu kaydı yerinde kalmayı sürdürür, çünkü kurucu
       olmak bir kayıttır ve düşmez.

       Sıra: önce kurucular, kuruluş kaydındaki sırayla; sonra öteki baş
       editörler, alfabetik. Tarihe göre sıralamak, listeye bakan kişiyi
       "kim daha yeni bıraktı" sorusuna zorluyordu; oysa bu bir teşekkür
       listesidir, bir sıralama değil. */
    function tg_onursal_bas_editorler(): array {
        $kurucu = []; $oteki = [];
        foreach (tg_kurucu_bas_editorler() as $k) {
            if (empty($k['gorevde'])) $kurucu[] = $k;      /* kayıt sırası korunur */
        }
        foreach (tg_atanmis_bas_editorler()['kayit'] as $k) {
            if ($k['sinif'] === 'onursal') $oteki[] = $k;
        }
        return array_merge($kurucu, tg_ad_sirala($oteki));
    }

    /* Bugün görevde olan bütün baş editörler: görevi süren kurucular ve
       görevi süren atanmışlar. Onursal olanlar burada yoktur, çünkü
       onursal sıfat bir yetki taşımaz. Sıra: önce kurucular, sonra
       ötekiler. */
    function tg_bas_editorler_gorevde(): array {
        $out = [];
        foreach (tg_kurucu_bas_editorler() as $k) { if (!empty($k['gorevde'])) $out[] = $k; }
        foreach (tg_gorevdeki_bas_editorler() as $k) $out[] = $k;
        return $out;
    }

    /* ---- KURULUN KOLTUK SAYISI ----
       Yönetici baş editör sayısı yazılıdır. Bir koltuk boşaldığında
       (devir, vefat, karar ya da yazılı sürenin dolması) görevdekiler
       yerine birini atar. Sayı ayar.php'de durur ve görünürdür: bir
       kurulun kaç kişi olduğu, o kurulun kendi bileceği bir şey değil,
       okurun da bilmesi gereken bir şeydir. */
    function tg_kurul_koltuk(): int {
        $n = (int)tg_ayar('bas_editor_koltuk', 5);
        return $n > 0 ? $n : 5;
    }

    function tg_bos_koltuk(): int {
        return max(0, tg_kurul_koltuk() - count(tg_bas_editorler_gorevde()));
    }

    /* =================================================================
       KURUL KARARLARI — YAZILAN, OYLANAN VE KALICI
       -----------------------------------------------------------------
       KURUL BİLDİRİMİ — 19 Ağustos 2026: "kurucu danışma kurulu bir
       karara yazdı, sisteme onu nerede yazacak? Orası yok. Diğerleri
       nasıl oylayacak? O da yok."

       ÖLÇÜLEN KUSUR VE AĞIRLIĞI. Bu sistemin kuralları kurulun kendi
       kararına ONLARCA yerde dayanır: baş editörlüğün "kurucuların
       çoğunluk kararıyla" sona ermesi, bir metnin ilkelere uyup
       uymadığının "yayın kurulunca" karara bağlanması, hakemlik
       yetkisinin askıya alınması. Yani sistem, kendisini yöneten
       yordamı ilan ediyor ama o yordamı yürütecek hiçbir yer
       taşımıyordu. Bu, "uygulanmayan kuralı duyurmak" sınıfının en ağır
       hâlidir: duyurulan şey, kuralların kendisini değiştiren kural.

       ÇALIŞMA OYLAMASINDAN AYRIDIR VE AYRI KALMALIDIR. yazi.php'deki
       oylama BİR ÇALIŞMA hakkındadır (bir hakem raporuna itiraz) ve
       çalışmanın sayfasında durur. Buradaki karar çalışma hakkında
       değildir; kurulun kendisi hakkındadır. İkisini tek bir yapıya
       sıkıştırmak, kararın hangi kurulun olduğunu yine belirsizleştirir
       — kaldırılan oylama.php'nin kusuru tam olarak buydu.

       DÖRT KURAL:
         1. KARAR YAZIYLA AÇILIR. Başlıksız ya da gerekçesiz bir karar
            açılamaz: oylanacak şeyin ne olduğu, oy verilmeden önce
            yazılmış olmalıdır.
         2. OY GEREKÇESİYLE VERİLİR. Bu sistemin her yerinde geçerli
            olan kural: hakem adıyla durur, editör adıyla durur, oy da
            adıyla ve gerekçesiyle durur.
         3. KARAR KALICIDIR. Açıldıktan sonra metni değişmez, oy geri
            alınmaz, kayıt silinmez. Kurul sayfası bunu zaten ilan
            ediyor: "kurucusu dâhil hiç kimse bir kurul kararını geri
            alamaz."
         4. SAYIM AÇIKTIR. Kim ne oy verdi ve neden, kurul sayfasında
            görünür. Kapalı sayım, denetlenemeyen bir kurul demektir.

       İKİ TÜR, TEK YAPI: 'kurul' kararını görevdeki baş editörler,
       'kurucu' kararını yalnız kurucu baş editörler oylar. Ayrım
       kurulun kendi kurallarından gelir (kurucu sıfatı sonradan
       verilemez), bu yüzden burada da vardır.
       ================================================================= */
    function tg_kk_turleri(): array {
        return [
            'kurul'  => ['tr' => 'Kurul kararı',   'en' => 'Board decision'],
            'kurucu' => ['tr' => 'Kurucu kararı',  'en' => 'Founders\' decision'],
        ];
    }

    function tg_kk_tur_ad(string $t, bool $en = false): string {
        $m = tg_kk_turleri();
        return isset($m[$t]) ? tg_t(['tr' => $m[$t]['tr'], 'en' => $m[$t]['en']], $en) : '';
    }

    function tg_kk_secenekler(): array {
        return [
            'kabul'    => ['tr' => 'Kabul',     'en' => 'In favour'],
            'ret'      => ['tr' => 'Ret',       'en' => 'Against'],
            'cekimser' => ['tr' => 'Çekimser',  'en' => 'Abstain'],
        ];
    }

    function tg_kk_secenek_ad(string $k, bool $en = false): string {
        $m = tg_kk_secenekler();
        return isset($m[$k]) ? tg_t(['tr' => $m[$k]['tr'], 'en' => $m[$k]['en']], $en) : '';
    }

    /* Kaç kişi oy verebilir. Çekimser oy SAYIYA DAHİLDİR ama hiçbir
       yöne yazılmaz: çekimser kalmak da bir karardır ve çoğunluğu
       zorlaştırır. Yeter sayı, oy verebilecek kişi sayısının yarısından
       fazlasıdır — "oy verenlerin çoğunluğu" denseydi, iki kişinin
       oyuyla beş kişilik bir kurul karar almış olurdu. */
    function tg_kk_oy_verebilir(array $karar): int {
        if ((string)($karar['tur'] ?? '') === 'kurucu') {
            $n = 0;
            foreach (tg_kurucu_bas_editorler() as $k) { if (!empty($k['gorevde'])) $n++; }
            return max(1, $n);
        }
        return max(1, count(tg_bas_editorler_gorevde()));
    }

    function tg_kk_yeter(array $karar): int {
        return (int)floor(tg_kk_oy_verebilir($karar) / 2) + 1;
    }

    function tg_kk_sonuc(array $karar): array {
        $say = ['kabul' => 0, 'ret' => 0, 'cekimser' => 0];
        foreach (tg_dizi($karar['oylar'] ?? null) as $o) {
            if (!is_array($o)) continue;
            $k = (string)($o['karar'] ?? '');
            if (isset($say[$k])) $say[$k]++;
        }
        $yeter = tg_kk_yeter($karar);
        $kisi  = tg_kk_oy_verebilir($karar);
        $hal   = 'suruyor';
        if ($say['kabul'] >= $yeter)      $hal = 'kabul';
        elseif ($say['ret'] >= $yeter)    $hal = 'ret';
        elseif (array_sum($say) >= $kisi) $hal = 'yetersiz';   /* herkes oy verdi, çoğunluk çıkmadı */
        return ['say' => $say, 'yeter' => $yeter, 'kisi' => $kisi,
                'hal' => $hal, 'kapali' => $hal !== 'suruyor',
                'verilen' => array_sum($say)];
    }

    function tg_kk_hal_ad(string $hal, bool $en = false): string {
        $m = [
            'suruyor'  => ['Oylama sürüyor', 'The vote is open'],
            'kabul'    => ['Kabul edildi', 'Adopted'],
            'ret'      => ['Reddedildi', 'Rejected'],
            'yetersiz' => ['Çoğunluk çıkmadı', 'No majority was reached'],
        ];
        return isset($m[$hal]) ? tg_t(['tr' => $m[$hal][0], 'en' => $m[$hal][1]], $en) : '';
    }

    /* Kararları okur. Tek kaynak: sayfa da uç da buradan okur; iki
       yerde iki ayrı sıralama yazılsaydı aynı liste iki ekranda iki
       ayrı sırayla görünürdü. En yeni en üstte. */
    function tg_kk_oku(): array {
        /* ÜÇ OKUYUCU, ÜÇ AYRI BAĞLAM. Uçta oku_json(), sayfada k_json()
           vardır; ikisi de yoksa dosya doğrudan okunur. İlk yazımda
           yalnız oku_json() deneniyordu ve kurul sayfası kararları BOŞ
           gösteriyordu — uçta çalışan bir okuyucu, sayfada olmayabilir. */
        $d = null;
        if (function_exists('oku_json'))      $d = oku_json('kurul-kararlari.json', []);
        if (!is_array($d) && function_exists('k_json')) $d = k_json('kurul-kararlari.json', []);
        if (!is_array($d)) {
            $dz = function_exists('k_veri_dizin') ? k_veri_dizin()
                : (function_exists('tg_veri_dizini') ? tg_veri_dizini() : '');
            $p = $dz !== '' ? rtrim($dz, '/') . '/kurul-kararlari.json' : '';
            $d = $p !== '' ? json_decode((string)@file_get_contents($p), true) : [];
        }
        if (!is_array($d)) $d = [];
        $out = [];
        foreach ($d as $k) { if (is_array($k) && trim((string)($k['kod'] ?? '')) !== '') $out[] = $k; }
        usort($out, fn($a, $b) => strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? '')));
        return $out;
    }

    /* ---- ATAMA DÜZENİ ----
       Bu işlev yalnızca yapılandırmayı okur, hiçbir sayım yapmaz.
       Sayım yapsaydı, görev durumunu hesaplayan işlevlerle karşılıklı
       çağrıya girerdi: görev durumu karar yeter sayısını sorar, yeter
       sayı da atama düzenini sorardı. Okuma ile sayımı ayırmak bu
       döngüyü büsbütün ortadan kaldırır. */
    function tg_bas_atama(): array {
        $a = (array)tg_ayar('bas_editor_atama', []);
        $b = trim((string)($a['yetki_bitis'] ?? '2027-12-31'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $b)) $b = '2027-12-31';
        return [
            'yetki_bitis' => $b,
            'oy_gerekli'  => max(1, (int)($a['oy_gerekli'] ?? 3)),
            'koltuk'      => tg_kurul_koltuk(),
        ];
    }

    /* =================================================================
       ZORUNLU HÂLLER
       -----------------------------------------------------------------
       Baş editör listesine ekleme ve çıkarma yetkisi yazılı tarihte
       DÜŞER. Bu asıl kuraldır ve öyle kalır: süresiz bir atama yetkisi
       zamanla kampanyayı, öbekleşmeyi ve kayırmayı getirir.

       Ancak düşmüş bir yetki, sistemin yapmak ZORUNDA olduğu bir şeyi
       yapamaz hâle gelmesine yol açıyorsa orada durmaz. Aşağıdaki dört
       hâlde yetki kendiliğinden açılır. Dördü de şu üç şartı birden
       taşır ve taşımayan bir hâl bu listeye giremez:

         a. KAYITTAN OKUNUR. Bir yorum, bir gerek görme ya da bir
            takdir değildir; sayılabilir bir olgudur. Kimse "zorunluluk
            var" diyerek yetkiyi açamaz, çünkü açan kişi değil kayıttır.
         b. KENDİ KENDİNİ KAPATIR. Hâl ortadan kalktığı gün yetki
            yeniden düşer. Bir kez açılıp açık kalan bir kapı, kuralın
            kendisini yürürlükten kaldırırdı.
         c. YALNIZCA EKSİĞİ GİDERİR. Açılan yetki, kurulu yazılı koltuk
            sayısının üstüne çıkaramaz; boşluğu doldurur, kurul
            büyütmez.

       DÖRT HÂL:

         1. kurul-bos     Bugün görevde hiçbir baş editör yok. Sistem
                          yönetimsizdir: hakem atanamaz, karar
                          verilemez, düzeltme yayımlanamaz. Bu, hiçbir
                          kuralın savunamayacağı tek durumdur.
         2. devralan-yok  Ne bir kuruma devir yapılmıştır ne de kurucu
                          olmayan görevde bir baş editör vardır. Yani
                          görevi devralmış kimse yoktur. Projeye
                          dönüşmek devir sayılmaz: bir fona bağlanmak ya
                          da bir kurumun çatısı altında yürütülmek,
                          sorumluluğu üstlenen birini yaratmaz.
         3. koltuk-bos    Bir baş editör görevi bıraktı, vefat etti ya
                          da görevi kurul kararıyla sona erdi; yeri
                          boştur. Kalan baş editörler YALNIZCA o boşluğu
                          doldurmak üzere atama yapar. Bu hâl olmasaydı
                          kurul her ayrılıkta küçülür ve bir gün kimse
                          kalmazdı.
         4. sayimla-yok   Yetki kapandıktan sonra baş editörlük sayımla
                          kazanılır. Ölçütü karşılayan kimse yoksa o yol
                          da kapalıdır; iki yolu birden kapatmak kurulu
                          yenilenemez kılar. Bu hâl yalnızca bir koltuk
                          boşken anlamlıdır, tek başına yetki açmaz.

       Bu liste KAPALIDIR. Yeni bir zorunlu hâl eklemek bir kod
       değişikliğidir ve kurul sayfasında görünür; ayar dosyasına
       yazılarak eklenemez.

       ÇIKARMA BU YETKİNİN İÇİNDE DEĞİLDİR. Bir baş editörü görevden
       çıkarmak hiçbir zaman serbest bir yetki olmadı: görev yalnızca
       kişinin kendi devriyle, vefatıyla, yazılı bir çoğunluk kararıyla
       ya da atamaya konmuş bir bitiş tarihiyle biter. Çoğunluk kararı
       yolu tarihe bağlı DEĞİLDİR ve her zaman açıktır; bir kurulun
       kendini denetleyebilmesi buna bağlıdır.
       ================================================================= */
    function tg_zorunlu_haller(): array {
        $gorevde = count(tg_bas_editorler_gorevde());
        $bos     = tg_bos_koltuk();
        $olcut   = function_exists('tg_bas_olcut_saglayanlar') ? tg_bas_olcut_saglayanlar() : [];
        return [
            'kurul-bos' => [
                'var' => $gorevde < 1,
                'ad'  => ['Kurul boş', 'The board is empty'],
                'ic'  => ['Bugün görevde hiçbir baş editör yok. Sistem yönetimsizdir: hakem atanamaz, karar verilemez, düzeltme yayımlanamaz.',
                          'No chief editor is in office today. The system is ungoverned: no reviewer can be assigned, no decision reached, no correction published.'],
            ],
            'devralan-yok' => [
                'var' => !tg_devralan_var(),
                'ad'  => ['Devralan yok', 'There is no successor'],
                'ic'  => ['Ne bir kuruma devir yapılmıştır ne de kurucu olmayan görevde bir baş editör vardır. Bir projeye dönüşmek devir sayılmaz.',
                          'The system has not been handed to an institution, and no chief editor who is not a founder is in office. Turning into a project is not a handover.'],
            ],
            'koltuk-bos' => [
                'var' => $bos > 0,
                'ad'  => ['Koltuk boş', 'A seat is empty'],
                'ic'  => ['Bir baş editörün görevi sona erdi ve yeri boş. Kalan baş editörler yalnızca o boşluğu doldurmak üzere atama yapar; kurul yazılı koltuk sayısının üstüne çıkarılamaz.',
                          'A chief editor\'s office has ended and the seat is empty. The remaining chief editors appoint only to fill that gap; the board cannot be taken above the written number of seats.'],
            ],
            'sayimla-yok' => [
                'var' => $bos > 0 && $olcut === [],
                'ad'  => ['Sayımla kazanan yok', 'No one has earned it by count'],
                'ic'  => ['Ölçütü karşılayan kimse bulunmuyor; kurul öteki yoldan da yenilenemiyor. Bu hâl yalnızca bir koltuk boşken anlamlıdır.',
                          'No one meets the criterion, so the board cannot renew itself by the other route either. This case has meaning only while a seat is empty.'],
            ],
        ];
    }

    /* Bugün geçerli olan zorunlu hâllerin anahtarları. Boş dizi dönmesi
       olağandır ve olması gerekendir. */
    function tg_zorunlu_hal(): array {
        $out = [];
        foreach (tg_zorunlu_haller() as $k => $h) if (!empty($h['var'])) $out[] = $k;
        return $out;
    }

    /* ---- ATAMA YETKİSİ BUGÜN AÇIK MI ----
       Yazılı tarih geçmemişse açıktır. Geçmişse yalnızca bir zorunlu
       hâl varsa açılır. Yetki kapandığında baş editörlük atamayla değil
       sayımla kazanılır (ayar.php'deki 'bas_editor_olcut'). */
    function tg_atama_yetkisi_acik(): bool {
        if (date('Y-m-d') <= tg_bas_atama()['yetki_bitis']) return true;
        return tg_zorunlu_hal() !== [];
    }

    /* Yetki bugün yalnızca bir zorunlu hâl sayesinde mi açık? */
    function tg_atama_zorunlu_halle_mi(): bool {
        return date('Y-m-d') > tg_bas_atama()['yetki_bitis'] && tg_zorunlu_hal() !== [];
    }

    /* Atama oyunu kimler kullanır: bugün görevde olan baş editörler.
       Başlangıçta bu küme beş kurucudur. Biri bıraktığında ya da vefat
       ettiğinde kalanlar oy kullanır; kurucu olmayan bir baş editör
       göreve geldiğinde o da bu kümededir. Oylar eşittir.

       Yeter sayı yazılı sayıdır (üç), ancak kurul üçten küçükse salt
       çoğunluğa iner. Sabit bir üç sayısı, kurul ikiye düştüğünde
       atamayı imkânsız kılar ve sistem kendini yenileyemez hâle
       gelirdi. */
    /* KURUL KARARI F2, 14 Ağustos 2026:
         "Kurucuların olduğu zamanda, 2027 sonuna kadar kurucu baş
          editörler atar; kurucu kurul üyelerinin beşte üçü kabul
          ederse o kişi baş editör olur, ama kurucu baş editör değil."

       ÖLÇÜLEN AYRIM. Kod bugün oy kurulunu "görevdeki bütün baş
       editörler" sayıyordu. Bugün ikisi aynı kümedir — görevdeki beş
       kişinin beşi de kurucudur — ama 2027 bitmeden bir atama
       yapıldığı gün ayrışırdı: yeni gelen kişi, kendisinden sonraki
       atamada oy kullanırdı. Kurulun dediği bu değil. Kuruluş
       döneminde oy kurulu KURUCULARDIR.

       Tarihten sonra eski kural sürer ve sürmesi gerekir: kurucular
       azaldıkça sabit bir "üç kurucu oyu" şartı kurulu kendini
       yenileyemez hâle getirirdi. Yetkinin dört zorunlu hâlde yeniden
       açılması da o günden sonrasına aittir. */
    function tg_atama_oy_kurulu(): array {
        if (tg_kurulus_atama_donemi()) {
            $k = [];
            foreach (tg_kurucu_bas_editorler() as $b) { if (!empty($b['gorevde'])) $k[] = $b; }
            if ($k !== []) return $k;
            /* Görevde kurucu kalmadıysa kuruluş dönemi fiilen bitmiştir;
               kurul yönetimsiz bırakılmaz. */
        }
        return tg_bas_editorler_gorevde();
    }

    /* Kuruluş dönemi atama yetkisi bugün açık mı? Tarih ayar
       dosyasındadır ve tek yerden okunur. */
    function tg_kurulus_atama_donemi(): bool {
        $b = trim(tg_metin(tg_bas_atama()['yetki_bitis'] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $b) === 1 && date('Y-m-d') <= $b;
    }

    function tg_atama_yeter_sayisi(): int {
        $toplam = count(tg_atama_oy_kurulu());
        $yazili = tg_bas_atama()['oy_gerekli'];
        if ($toplam < 1) return 1;
        $cogunluk = (int)floor($toplam / 2) + 1;
        return max(1, min($yazili, max($cogunluk, 1)));
    }

    /* ---- KAPI: bir atama geçerli mi ----
       Bir atamanın yazılabilmesi için geçmesi gereken tek yer burasıdır.
       Bir ekran yazılırsa o ekran da buradan geçer; ikinci bir yol
       açılırsa denetimlerin biri eksik kalır ve bu sistemde bunun bir
       örneği zaten yaşandı.

       $oylar: evet oyu veren GÖREVDEKİ baş editörlerin adları ya da
       e-posta adresleri. Görevde olmayan bir ad sayılmaz; aynı kişi iki
       kez sayılmaz.

       Oy kurulu neden yalnızca kurucular değil: bir kurucu görevi
       bıraktığında ya da vefat ettiğinde oy kurulu küçülür. Sabit
       "üç kurucu oyu" şartı, kurucular ikiye indiğinde atamayı
       imkânsız kılar ve kurul kendini yenileyemez. Görevdeki baş
       editörler oy kullanır; kurucu olan da, sonradan gelen de. */
    function tg_atama_gecerli($kayit, array $oylar): array {
        $hata = [];
        if (!tg_atama_yetkisi_acik()) {
            $hata[] = 'Atama yetkisi ' . tg_bas_atama()['yetki_bitis'] . ' tarihinde kapandı, devralan vardır ve kurulda boş koltuk yoktur; bu durumda baş editörlük atamayla değil sayımla kazanılır.';
        }
        $s = tg_gorev_kaydi_suz($kayit);
        if (!$s['ok']) $hata[] = $s['hata'];
        if ($s['ok'] && $s['kayit']['sinif'] === 'onursal') {
            $hata[] = 'Görevi bitmiş görünen bir atama yazılamaz: bitiş tarihi, devir ya da vefat kaydı geçmişte.';
        }
        /* ---- KOLTUK TAVANI ----
           Atama kurulu yazılı koltuk sayısının üstüne çıkaramaz. Bu
           denetim her zaman işler, yalnızca zorunlu hâlde değil:
           koltuk sayısı kurulun kendi bileceği bir şey olsaydı, "bir
           kişi daha" diye diye kurul büyür ve yazılı sayı bir süs
           olurdu. Sayıyı değiştirmek ayar dosyasında bir satırdır ve
           görünür; atama kararıyla dolaylı olarak değiştirilemez. */
        $koltuk = tg_kurul_koltuk();
        $dolu   = count(tg_bas_editorler_gorevde());
        if ($dolu >= $koltuk) {
            $hata[] = 'Kurulda boş koltuk yok: yazılı koltuk sayısı ' . $koltuk . ', görevde ' . $dolu
                    . ' kişi var. Kurul ancak ayar dosyasındaki koltuk sayısı değiştirilerek büyütülebilir.';
        }
        /* Oy sayımı: görevdeki baş editörlerin oyu sayılır ve oylar
           eşittir. Kurucunun oyu ağır basmaz, yalnızca sayılır. */
        $kurul = [];
        foreach (tg_atama_oy_kurulu() as $k) {
            $kurul[tg_ad_anahtar((string)$k['ad'])] = true;
            $ep = mb_strtolower(trim(tg_metin($k['eposta'] ?? '')), 'UTF-8');
            if ($ep !== '') $kurul[$ep] = true;
        }
        $sayilan = [];
        foreach ($oylar as $o) {
            $o = trim(tg_metin($o));
            if ($o === '') continue;
            $ad = tg_ad_anahtar($o);
            $ep = mb_strtolower($o, 'UTF-8');
            $an = isset($kurul[$ad]) ? $ad : (isset($kurul[$ep]) ? $ep : '');
            if ($an === '') continue;
            $sayilan[$an] = true;
        }
        $yeter = tg_atama_yeter_sayisi();
        if (count($sayilan) < $yeter) {
            $kim = tg_kurulus_atama_donemi() ? 'kurucu baş editör' : 'görevdeki baş editör';
            $hata[] = 'Atama için en az ' . $yeter . ' ' . $kim . ' oyu gerekir; sayılan oy: ' . count($sayilan) . '.';
        }
        if ($hata) return ['ok' => false, 'hata' => $hata, 'kayit' => []];
        $k = $s['kayit'];
        $k['atayan'] = array_keys($sayilan);
        return ['ok' => true, 'hata' => [], 'kayit' => $k];
    }

    /* =================================================================
       DEĞİŞTİRİLEMEZ İLKELER (tüzük taslağı Ek A, Madde 2)
       -----------------------------------------------------------------
       Sekiz hüküm. Genel kurul bunları yalnızca AÇIKLIĞI GENİŞLETECEK
       yönde değiştirebilir; daraltacak yönde değiştiremez, kaldıramaz
       ve askıya alamaz. Bu yönde alınan karar hükümsüzdür.

       Bunun metinde kalması yetmez. Aşağıda iki kapı var:

         tg_ilke_kapisi()    Bir kararın konusu bu sekizden birini
                             daraltıyorsa karar hükümsüzdür; oylanmadan
                             önce burada durur.
         tg_ayar_ilke_suz()  Yapılandırmanın kendisi denetlenir. Ücret
                             alan bir ayar yazılırsa düşürülür, kapalı
                             bir lisans yazılırsa açık lisansa geri
                             çekilir, arşive onay kapısı koyan bir ayar
                             yazılırsa kapatılır. Yani ilke, bir ayar
                             satırıyla sessizce delinemez.
       ================================================================= */
    function tg_degismez_ilkeler(): array {
        return [
            1 => ['ad' => ['Ücret yasağı', 'No charges'],
                  'ic' => ['Yazardan, hakemden ve okurdan; gönderme, değerlendirme, yayımlama, okuma ya da indirme karşılığında hiçbir ad altında ücret alınamaz.',
                           'No charge of any kind may be taken from an author, a reviewer or a reader for submission, assessment, publication, reading or downloading.']],
            2 => ['ad' => ['Açık erişim', 'Open access'],
                  'ic' => ['Bütün çalışmalar, telif hakkı yazarında kalmak üzere, en az Creative Commons Atıf düzeyinde açık bir lisansla yayımlanır.',
                           'All work is published under an open licence at least at the level of Creative Commons Attribution, with copyright remaining with the author.']],
            3 => ['ad' => ['Açık değerlendirme', 'Open assessment'],
                  'ic' => ['Hakem raporları adlarıyla birlikte çalışmayla aynı sayfada yayımlanır. Kurul oylamalarının sonucu, oylar ve gerekçeleri yayımlanır. Reddedilen çalışmalar arşivden çıkarılmaz.',
                           'Reviewer reports are published with their names on the same page as the work. The result of panel votes, the votes and their reasons are published. Rejected work is not removed from the archive.']],
            4 => ['ad' => ['Kaydın değişmezliği', 'The record does not change'],
                  'ic' => ['Yayımlanmış hiçbir kayıt geriye dönük değiştirilemez ya da silinemez. Düzeltme, endişe bildirimi ve geri çekme, özgün kaydın üzerine eklenen ayrı kayıtlardır.',
                           'No published record may be altered or deleted retrospectively. A correction, an expression of concern and a retraction are separate records added on top of the original.']],
            5 => ['ad' => ['Arşivin taşınabilirliği', 'The archive can be taken away'],
                  'ic' => ['Arşivin tamamı, kişisel veri içermeyecek biçimde her an indirilebilir. Bu erişim kimlik, kayıt, üyelik, onay ya da bedel şartına bağlanamaz. Teknik önlemler erişimi geciktirebilir, hiçbir durumda reddedemez.',
                           'The whole archive, carrying no personal data, can be downloaded at any time. That access may not be made conditional on identity, registration, membership, approval or payment. Technical measures may delay access; they may never refuse it.']],
            6 => ['ad' => ['Yazılımın açıklığı', 'The software is open'],
                  'ic' => ['Sistemin yazılımı en az AGPL-3.0 düzeyinde özgür bir lisansla açık tutulur.',
                           'The software of the system is kept open under a free licence at least at the level of AGPL-3.0.']],
            7 => ['ad' => ['Bağımsızlık', 'Independence'],
                  'ic' => ['Destek veren, bağış yapan ya da himaye üstlenen hiç kimse bir çalışmanın kabulüne, reddine, bir hakem raporuna ya da bir kurul kararına etki edemez. Bu kişilerin adları, verdikleri desteğin miktarı ve harcandığı yer açık yayımlanır.',
                           'No one who supports, donates or offers patronage may affect the acceptance or rejection of a work, a reviewer report or a panel decision. Their names, the amount they gave and where it was spent are published openly.']],
            8 => ['ad' => ['Ayrım gözetmeme', 'No discrimination'],
                  'ic' => ['Sistem hiçbir ülkeye, kuruma, dile, bilim dalına ya da kişiye ayrıcalık tanımaz; hiçbirini de dışlamaz.',
                           'The system grants no privilege to any country, institution, language, discipline or person, and excludes none of them.']],
        ];
    }

    /* ---- KAPI: bir karar oylanabilir mi ----
       Konuyu okur ve sekiz ilkeden birini daraltıyorsa reddeder. Oy
       sayısına bakmaz, çünkü bu sınır oy sayısından bağımsızdır: ücret
       alınması, kapalı hakemliğe dönülmesi ya da bir kaydın silinmesi
       oylanamaz; oylanırsa karar hükümsüzdür.

       Dönen: ['gecer'=>bool, 'ilke'=>int, 'sebep'=>[tr, en]]. */
    function tg_ilke_kapisi(string $konu): array {
        $m = mb_strtolower(trim($konu), 'UTF-8');
        if ($m === '') return ['gecer' => true, 'ilke' => 0, 'sebep' => ['', '']];
        /* Desenler konunun kendisini arar. Bir kararın adı "ücret
           alınması" ise oylanamaz; "ücret alınmaması" da aynı desene
           takılır ama o karar zaten ilkenin kendisidir ve alınmasına
           gerek yoktur. Şüpheli bir konuyu geçirmektense durdurmak
           yeğdir: tereddütte hüküm, Madde 2'yi en geniş biçimde
           koruyacak şekilde yorumlanır.

           Bir konu birden çok ilkeye dokunabilir; bu durumda ilk
           eşleşen madde bildirilir. Hangi maddenin bildirildiği bir
           ayrıntıdır, kararın durdurulmuş olması esastır. */
        $desen = [
            1 => 'ücret|ödeme|abonelik|bedel|paywall|fee|charge|subscription',
            2 => 'kapalı erişim|erişimi kapat|lisansı daralt|closed access|restrict access',
            3 => 'kapalı hakem|hakem adı gizle|raporu yayımlama|anonim hakem|closed review|anonymous review',
            4 => 'kaydı sil|kayıt silme|arşivden çıkar|geriye dönük değiştir|delete record|remove from archive',
            5 => 'indirmeyi kapat|üyelik şartı|kayıt şartı|onay kapısı|download gate|registration required',
            6 => 'kapalı kaynak|lisansı kapat|closed source|proprietary',
            7 => 'destekçiye söz|bağış karşılığı|sponsor kararı|donor decides',
            8 => 'uyruk şartı|ülke şartı|kurum şartı|yalnızca türk|nationality requirement|citizens only',
        ];
        foreach ($desen as $no => $d) {
            if (preg_match('/' . $d . '/u', $m)) {
                $i = tg_degismez_ilkeler()[$no];
                return ['gecer' => false, 'ilke' => $no, 'sebep' => [
                    'Bu konu Ek A Madde 2.' . $no . ' (' . $i['ad'][0] . ') ile korunuyor ve hiçbir çoğunlukla daraltılamaz. Oylanırsa karar hükümsüzdür.',
                    'This subject is protected by Appendix A Article 2.' . $no . ' (' . $i['ad'][1] . ') and cannot be narrowed by any majority. A vote on it would be void.',
                ]];
            }
        }
        return ['gecer' => true, 'ilke' => 0, 'sebep' => ['', '']];
    }

    /* ---- KAPI: yapılandırma denetimi ----
       Bir ilke, bir ayar satırıyla sessizce delinebiliyorsa yazılı
       olması bir şey ifade etmez. Bu işlev ayar dizisini alır, ilkeye
       aykırı satırları etkisiz kılar ve neyi neden düşürdüğünü kayda
       yazar. tg_ayar() dosyayı okur okumaz buradan geçirir; yani sistem
       aykırı bir ayarla hiç çalışmaz.

       Bu bir uyarı değil, bir düzeltmedir: uyarı okunmayabilir. */
    function tg_ayar_ilke_suz(array $a, &$kayit = null): array {
        /* İkinci değişken türsüz bırakıldı: çağıran taraf tanımsız bir
           değişken verdiğinde tür denetimi ölümcül hata veriyordu.
           Denetim kapısının kendisi bir hatayla düşerse kapı değildir. */
        $kayit = [];
        /* 2.1 Ücret yasağı: ücret alan bir ayar yazılamaz. */
        foreach (array_keys($a) as $anahtar) {
            if (!preg_match('/^(ucret|ücret|abonelik|bedel|fiyat|islem_ucreti|apc)(_|$)/u', (string)$anahtar)) continue;
            $d = $a[$anahtar];
            $dolu = is_array($d) ? (bool)array_filter($d) : (is_numeric($d) ? (float)$d > 0 : (bool)$d);
            if (!$dolu) continue;
            unset($a[$anahtar]);
            $kayit[] = ['ilke' => 1, 'anahtar' => (string)$anahtar,
                        'yapilan' => 'düşürüldü'];
        }
        /* 2.2 Açık erişim: lisans en az CC BY düzeyinde olmalıdır. */
        $lis = mb_strtoupper(trim((string)($a['lisans'] ?? '')), 'UTF-8');
        $acik = $lis === '' || preg_match('/^(CC0|CC BY)/u', $lis);
        if (!$acik) {
            $kayit[] = ['ilke' => 2, 'anahtar' => 'lisans', 'yapilan' => 'CC BY 4.0 değerine çekildi'];
            $a['lisans']     = 'CC BY 4.0';
            $a['lisans_url'] = 'https://creativecommons.org/licenses/by/4.0/';
        }
        /* 2.5 Arşive onay kapısı konulamaz. */
        foreach (['arsiv_onay', 'arsiv_kayit_gerek', 'arsiv_uyelik_gerek', 'dokum_onay'] as $anahtar) {
            if (empty($a[$anahtar])) continue;
            $a[$anahtar] = false;
            $kayit[] = ['ilke' => 5, 'anahtar' => $anahtar, 'yapilan' => 'kapatıldı'];
        }
        /* 2.8 Uyruk, ülke ve kurum şartı yazılamaz. */
        foreach (['bas_editor_uyruk', 'bas_editor_ulke', 'bas_editor_kurum', 'hakem_uyruk', 'yazar_uyruk'] as $anahtar) {
            if (!isset($a[$anahtar]) || $a[$anahtar] === '' || $a[$anahtar] === [] || $a[$anahtar] === false) continue;
            unset($a[$anahtar]);
            $kayit[] = ['ilke' => 8, 'anahtar' => $anahtar, 'yapilan' => 'düşürüldü'];
        }
        return $a;
    }

    /* Süzgecin bu çalışmada neyi düşürdüğü. Boş dönmesi olağandır ve
       öyle olmalıdır; dolu dönüyorsa ayar dosyasına ilkeye aykırı bir
       satır yazılmış demektir. */
    function tg_ilke_suzgec_kaydi(): array {
        tg_ayar();                       /* süzgecin çalışmasını sağlar */
        return $GLOBALS['TG_ILKE_SUZGEC'] ?? [];
    }

    /* ---- PARA VE KAYNAK KARARLARINDA OY ----
       Görevdeki baş editörler oy kullanır: yıllık bütçe, harcama
       kalemleri, kabul edilecek destekler, fon başvuruları ve ücret
       alınıp alınmaması dışındaki mali kararlar. Oylar eşittir;
       kurucunun oyu ağır basmaz. Ağırlık alanı bilerek vardır ve
       bilerek herkeste birdir: ağırlığın bir gün değiştirilmek
       istenmesi hâlinde değiştirilecek yer görünür olsun.

       Onursal baş editörler bu listede yoktur, çünkü onursal sıfat bir
       teşekkürdür ve hiçbir yetki taşımaz. */
    function tg_mali_oy_kurulu(): array {
        $out = [];
        foreach (tg_bas_editorler_gorevde() as $k) {
            $out[] = [
                'ad'      => (string)$k['ad'],
                'sinif'   => (string)($k['sinif'] ?? 'gorevdeki'),
                'agirlik' => 1,
            ];
        }
        return $out;
    }

    /* Bir mali kararın geçerliliği. Önce ilke kapısından geçer: konu
       sekiz ilkeden birini daraltıyorsa oy sayılmaz bile. Sonra salt
       çoğunluk aranır. */
    function tg_mali_karar(string $konu, array $evet): array {
        $kapi = tg_ilke_kapisi($konu);
        if (!$kapi['gecer']) return ['ok' => false, 'sebep' => $kapi['sebep'], 'ilke' => $kapi['ilke']];
        $kurul = tg_mali_oy_kurulu();
        $gecerli = [];
        foreach ($kurul as $k) $gecerli[tg_ad_anahtar($k['ad'])] = true;
        $sayi = 0; $gorulen = [];
        foreach ($evet as $e) {
            $an = tg_ad_anahtar(trim(tg_metin($e)));
            if ($an === '' || !isset($gecerli[$an]) || isset($gorulen[$an])) continue;
            $gorulen[$an] = true; $sayi++;
        }
        $toplam = count($kurul);
        $yeter  = (int)floor($toplam / 2) + 1;
        return [
            'ok'     => $toplam > 0 && $sayi >= $yeter,
            'oy'     => $sayi,
            'toplam' => $toplam,
            'yeter'  => $yeter,
            'ilke'   => 0,
            'sebep'  => ['', ''],
        ];
    }

    /* Dilin kendi adı. Bir dilin adını başka bir dilde okumak zorunda
       kalmak, bu sistemin karşı olduğu şeyin küçük bir örneğidir. */
    function tg_dil_adi(string $kod): string {
        $kod = tg_dil_kodu($kod);
        return $kod === '' ? '' : (string)(tg_calisma_dilleri()[$kod] ?? '');
    }

    /* Bir çalışmanın dili. Eski kayıtlarda alan yoktur; o kayıtlar
       Türkçe ya da İngilizce yazılmıştı ve hangisi olduğu metnin
       kendisinden değil, İngilizce alanların dolu olup olmamasından
       anlaşılır. Uydurmak yerine boş bırakmak doğrudur: bilinmeyen bir
       şeyi biliyormuş gibi yazmak, kaydı bozar. */
    function tg_yazi_dili(array $y): string {
        return tg_dil_kodu($y['dil'] ?? '');
    }

    /* =================================================================
       ÇEVİRİNİN KAYNAĞI — TEK KAYNAK
       -----------------------------------------------------------------
       İLKELER SAYFASI BUNU ZATEN SÖZ VERİYORDU, KOD TUTMUYORDU:

         "Bir çevirinin buraya girmesi için yazarın onayı ve çevirenin
          adı gerekir; çeviren kendi adıyla kayda geçer."

       Oysa kayıtta çevirenin adını tutan bir alan yoktu. Yazarın kendi
       yazdığı İngilizce metin, bir insanın çevirdiği metin ve bir
       makinenin çevirdiği metin sayfada BİRBİRİNİN AYNI görünüyordu.
       Sistem, uygulamadığı bir kuralı duyuruyordu; bu projede en
       pahalıya mal olan kusur türü budur.

       KAYIT BİÇİMİ. Çeviriler artık dile göre tutulur:

         'ceviriler' => [
            'en' => ['baslik'=>…, 'ozet'=>…, 'metin'=>…, 'metin_ham'=>…,
                     'kaynak'=>'yazar|insan|makine', 'ceviren'=>'…',
                     'onay'=>true|false, 'tarih'=>'YYYY-MM-DD'],
            'ar' => [...],
         ]

       ESKİ BİÇİM DE OKUNUR. Bugüne kadarki kayıtlarda baslik_en /
       ozet_en / metin_en alanları var ve bunlar BAŞVURU FORMUNDAKİ
       "İngilizce başlık" kutusundan geldi; yani yazarın kendi elinden
       çıktı. Onlara geriye dönük olarak "makine çevirisi" demek,
       bilinmeyen bir şeyi biliyormuş gibi kaydetmek olurdu. Eski biçim
       bu yüzden 'kaynak' => 'yazar' sayılır ve hiçbir kayıt dosyası
       değiştirilmez: dönüştürme OKUMA anında olur.

       OKUYAN TEK YER BURASI. oai.php, seo.php, yazi.php, arama ve
       döküm bu işlevlerden geçer; hiçbiri $y['baslik_en'] diye
       doğrudan bakmaz. İki ayrı okuma yolu olsaydı, biri çeviriyi
       yayın öteki okuma yardımı sayardı.
       ================================================================= */
    function tg_ceviri_kaynaklari(): array { return ['yazar', 'insan', 'makine']; }

    function tg_yazi_ceviriler(array $y): array {
        $out = [];
        $ham = $y['ceviriler'] ?? null;
        if (is_array($ham)) {
            foreach ($ham as $dk => $c) {
                if (!is_array($c)) continue;
                $dk = tg_dil_kodu((string)$dk);
                if ($dk === '') continue;
                $kaynak = (string)($c['kaynak'] ?? 'yazar');
                if (!in_array($kaynak, tg_ceviri_kaynaklari(), true)) $kaynak = 'yazar';
                $out[$dk] = [
                    'dil'       => $dk,
                    'baslik'    => trim(tg_metin($c['baslik'] ?? '')),
                    'ozet'      => trim(tg_metin($c['ozet'] ?? '')),
                    'metin'     => (string)($c['metin'] ?? ''),
                    'metin_ham' => (string)($c['metin_ham'] ?? ''),
                    'kaynak'    => $kaynak,
                    'ceviren'   => trim(tg_metin($c['ceviren'] ?? '')),
                    'onay'      => !empty($c['onay']),
                    'tarih'     => substr(trim((string)($c['tarih'] ?? '')), 0, 10),
                ];
            }
        }
        /* Eski biçim: yalnızca yeni biçimde o dil YOKSA okunur. Varsa
           yeni biçim kazanır; yoksa iki kaynak birbirini sessizce
           ezerdi. */
        if (!isset($out['en'])) {
            $b = trim(tg_metin($y['baslik_en'] ?? ''));
            $o = trim(tg_metin($y['ozet_en'] ?? ''));
            $m = (string)($y['metin_en'] ?? '');
            if ($b !== '' || $o !== '' || trim($m) !== '') {
                /* METİN YERİNDE KALIR, YANINA KÜNYESİ YAZILIR.
                   İngilizce metnin kendisi baslik_en/ozet_en/metin_en'de
                   duruyor ve orada kalıyor: onu taşımak arşiv dökümünü,
                   arama dizinini ve yazar panelini aynı anda kırardı ve
                   kazancı sıfır olurdu, çünkü eksik olan metin değil
                   KÜNYEDİR. Künye 'ceviri_en' alanında ayrıca durur;
                   yoksa eski davranış korunur: bu alanlar başvuru
                   formundaki "İngilizce başlık" kutusundan, yani
                   yazarın kendi elinden geldi. */
                $k = is_array($y['ceviri_en'] ?? null) ? $y['ceviri_en'] : [];
                $kaynak = (string)($k['kaynak'] ?? 'yazar');
                if (!in_array($kaynak, tg_ceviri_kaynaklari(), true)) $kaynak = 'yazar';
                $out['en'] = [
                    'dil' => 'en', 'baslik' => $b, 'ozet' => $o, 'metin' => $m,
                    'metin_ham' => (string)($y['metin_ham_en'] ?? ''),
                    'kaynak'  => $kaynak,
                    'ceviren' => trim(tg_metin($k['ceviren'] ?? '')),
                    /* Yazarın kendi yazdığı metin için onay sorusu
                       anlamsızdır: yazan zaten odur. */
                    'onay'    => $kaynak === 'yazar' ? true : !empty($k['onay']),
                    'tarih'   => substr(trim((string)($k['tarih'] ?? ($y['tarih'] ?? ''))), 0, 10),
                ];
            }
        }
        /* KAYDIN KENDİ DİLİ BURADAN DÜŞÜRÜLMEZ. İlk yazımda düşürülüyordu
           ("kendi dili bir çeviri değildir") ve kulağa doğru geliyordu;
           ölçüm başka söyledi. Arşivde 'dil' => 'en' yazan ama İngilizce
           metnini yine baslik_en / metin_en yuvalarında tutan kayıtlar
           var: alan adları kaydın diline göre değişmiyor. O kayıtlarda
           düşürme kuralı İngilizce metni görünmez yapıyor ve sayfa
           sessizce Türkçe alana düşüyordu (dogrula-kapi 9. bölüm bunu
           yakaladı).

           Ayrım DOĞRU YERDE zaten var: bir metnin ÇEVİRİ OLARAK
           SUNULUP sunulmayacağına gösterim yerinde, "istenen dil kaydın
           dilinden başka mı" sorusuyla karar veriliyor. Tek bir yerde
           duran bu koruma, burada ikinci kez ve daha kaba biçimde
           tekrarlanmamalı. */
        return $out;
    }

    function tg_yazi_ceviri(array $y, string $dil): array {
        $c = tg_yazi_ceviriler($y);
        $dil = tg_dil_kodu($dil);
        return ($dil !== '' && isset($c[$dil])) ? $c[$dil] : [];
    }

    /* BİR ÇEVİRİ YAYIN MI, OKUMA YARDIMI MI.

       Ayrım tek yerde verilir, çünkü üç ayrı yerde sonucu var: sayfada
       hangi cümlenin yazılacağı, canonical'in nereyi göstereceği ve
       dizine hangi dilin bildirileceği. Üçü ayrı ayrı karar verseydi
       bir gün sayfa "makine çevirisi" der, üstveri "eng sürüm var"
       diye bildirirdi.

       Kural: yazarın kendi yazdığı ya da insanın çevirip yazarın
       onayladığı metin yayındır. Makine çevirisi ancak yazar
       onayladıysa yayın olur; onaysız makine çevirisi bir okuma
       yardımıdır ve kayıt yerine geçmez. */
    function tg_ceviri_yayin_mi(array $c): bool {
        if (!$c) return false;
        if (($c['kaynak'] ?? '') === 'yazar') return true;
        return !empty($c['onay']);
    }

    /* Sayfada görünecek cümle. Metin de tek kaynaktan gelir: aynı
       cümle yazı sayfasında, PDF kapağında ve kartta yazılır. */
    function tg_ceviri_kaynak_metni(array $c, ?bool $en = null): string {
        if (!$c) return '';
        $ad = trim((string)($c['ceviren'] ?? ''));
        /* İlkeler sayfasının altıncı koşulu: "hangi çeviri motorunun
           hangi TARİHTE ürettiği yazılır". Tarih ada eklenir; ad yoksa
           tek başına da yazılır, çünkü koşul tarihi ada bağlamıyor. */
        $tr = trim((string)($c['tarih'] ?? ''));
        if ($tr !== '' && ($c['kaynak'] ?? '') !== 'yazar') {
            $g = tg_tarih_ad($tr, (bool)($en ?? (function_exists('k_en') ? k_en() : false)));
            if ($g !== '') $ad = $ad !== '' ? ($ad . ', ' . $g) : $g;
        }
        switch ((string)($c['kaynak'] ?? '')) {
            case 'yazar':
                return tg_c('Bu sürümü yazarın kendisi yazdı.',
                            'This version was written by the author.', $en);
            case 'insan':
                return $ad !== ''
                    ? tg_cd('Bu metni %1 çevirdi; yazar onayladı.',
                            'Translated by %1; approved by the author.', $en, $ad)
                    : tg_c('Bu metin çeviridir; yazar onayladı.',
                           'This text is a translation; approved by the author.', $en);
            case 'makine':
                if (!empty($c['onay'])) {
                    return $ad !== ''
                        ? tg_cd('Bu metin %1 ile makine çevirisi olarak üretildi; yazar okuyup onayladı.',
                                'Machine translated with %1; read and approved by the author.', $en, $ad)
                        : tg_c('Bu metin makine çevirisidir; yazar okuyup onayladı.',
                               'This text is a machine translation; read and approved by the author.', $en);
                }
                return $ad !== ''
                    ? tg_cd('Bu metin %1 ile makine çevirisi olarak üretildi. Yazar görmedi; kayıt bu değildir.',
                            'Machine translated with %1. The author has not seen it; this is not the record.', $en, $ad)
                    : tg_c('Bu metin makine çevirisidir. Yazar görmedi; kayıt bu değildir.',
                           'This text is a machine translation. The author has not seen it; this is not the record.', $en);
        }
        return '';
    }

    /* BAŞLIK VE DİLİ, TEK KAYNAK.

       Bildirilen eksik: İngilizce arayüzde Türkçe bir başlık görünüyor
       ama o başlığın hangi dilde olduğu YAZMIYOR. Okur, sistemin
       bozuk olduğunu mu yoksa çalışmanın Türkçe yazıldığını mı
       anlayacağını bilemiyor. Oysa bu sistemin en açık sözlerinden biri
       şudur: "kayıt her zaman yazarın yazdığı dildeki metindir."

       Öyleyse iki şey birden gösterilmelidir:
         1. Gösterilen başlığın DİLİ (kendi adıyla: Türkçe, English…),
         2. Varsa ÖZGÜN başlık — çeviri gösteriliyorsa altında özgünü.

       Karar burada bir kez verilir: kart, çalışma sayfası, arama
       sonucu ve panel aynı işlevi çağırır. İki yerde ayrı ayrı
       yazılsaydı biri güncellenir öteki unutulurdu.

       Döndürülen alanlar:
         bas       gösterilecek başlık
         dil       o başlığın dili (kod)
         dil_ad    o dilin kendi adı
         ozgun     gösterilen başlık çeviriyse özgün başlık, değilse ''
         ozgun_dil özgün başlığın dili
         im        başlığın yanında dil imi gösterilmeli mi (sayfanın
                   dilinden başka bir dilse evet) */
    function tg_baslik_bilgi(array $y, ?bool $en = null): array {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        $sayfaDil = function_exists('k_dil') ? k_dil() : ($en ? 'en' : 'tr');
        $tr  = trim(tg_metin($y['baslik'] ?? ''));
        $cEn = tg_yazi_ceviri($y, 'en');
        $enB = trim((string)($cEn['baslik'] ?? ''));
        /* Kaydın kendi dili: yazılmışsa o, yazılmamışsa Türkçe alanın
           dolu olmasından anlaşılan. Uydurulmaz, varsayılır ve bu
           varsayım kaydın kendisinden gelir. */
        $kayitDil = tg_yazi_dili($y);
        if ($kayitDil === '') $kayitDil = $tr !== '' ? 'tr' : ($enB !== '' ? 'en' : '');

        /* Hangisi gösterilecek: sayfa İngilizceyse ve İngilizce başlık
           varsa o; yoksa kaydın kendi başlığı. (Üçüncü dillerde de
           İngilizce yedektir; k_alan ile aynı kural.) */
        if ($sayfaDil !== 'tr' && $enB !== '') { $bas = $enB; $basDil = 'en'; }
        else                                   { $bas = $tr !== '' ? $tr : $enB;
                                                 $basDil = $tr !== '' ? ($kayitDil !== '' ? $kayitDil : 'tr') : 'en'; }

        /* Özgün başlık: gösterilen başlık kaydın kendi dilinde değilse
           özgünü de görünmelidir. Çeviri özgünün yerine geçmez. */
        $ozgun = ''; $ozgunDil = '';
        if ($basDil === 'en' && $kayitDil !== '' && $kayitDil !== 'en' && $tr !== '' && $tr !== $bas) {
            $ozgun = $tr; $ozgunDil = $kayitDil;
        }
        return [
            'bas'       => $bas,
            'dil'       => $basDil,
            'dil_ad'    => tg_dil_adi($basDil),
            'ozgun'     => $ozgun,
            'ozgun_dil' => $ozgunDil,
            'ozgun_ad'  => $ozgunDil !== '' ? tg_dil_adi($ozgunDil) : '',
            'im'        => $basDil !== '' && $basDil !== $sayfaDil,
        ];
    }

    /* ---- KURULUŞ DÖNEMİ ----
       Gevşetilmiş kurallar tek bir blokta ve bir bitiş tarihiyle durur;
       tarih geçince kendiliğinden kapanırlar. Unutulan bir gevşetmenin
       kalıcı hale gelmesi, bu sistemin en kolay bozulma yoludur. */
    function tg_kurulus_bitis(): string {
        $k = (array)tg_ayar('kurulus_donemi', []);
        $t = trim((string)($k['bitis'] ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) ? $t : '';
    }

    function tg_kurulus_donemi(): bool {
        $b = tg_kurulus_bitis();
        return $b !== '' && date('Y-m-d') <= $b;
    }

    /* Yazarlık için önce hakemlik yapmış olmak aranıyor mu?
       Kuruluş döneminde aranmaz: arşiv boşken değerlendirilecek çalışma
       olmadığı için bu koşul sisteme ilk çalışmanın girmesini engeller.
       Doktora ya da iki kefil şartı bundan etkilenmez, hiç gevşemez. */
    function tg_yazarlik_hakemlik_sarti(): bool {
        $k = (array)tg_ayar('kurulus_donemi', []);
        if (tg_kurulus_donemi() && array_key_exists('yazarlik_hakemlik_sarti', $k)) {
            return (bool)$k['yazarlik_hakemlik_sarti'];
        }
        return true;
    }

    /* Kuruluş dönemi bitiş tarihi, okunacak biçimde.
       Sayfalar tarihi kendi metinlerine yazmaz; yazsalardı ayar dosyası
       değiştiğinde metin eski tarihte kalırdı. */
    function tg_kurulus_bitis_ad(bool $en = false): string {
        $b = tg_kurulus_bitis();
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $b, $m)) return '';
        return tg_tarih_dizimi((int)$m[3], (int)$m[2], (int)$m[1], $en);
    }

    /* ---- YAZARLIK KOŞULUNUN O ANKİ HÂLİ, TEK CÜMLEYLE ----
       Bu cümleyi üreten tek yer burasıdır. Neden: koşul kodda
       aranmıyorken sayfalarda "en az bir hakemlik gerekir" yazıyordu.
       Sistem uygulamadığı bir kuralı duyurunca iki şey birden olur;
       gelmek isteyen boşuna caydırılır ve yazılı olanla yapılan
       ayrışır. Metin artık koşulun kendisinden okunur, elle
       yazılmaz. */
    /* Yazarlık için doktora derecesi aranıyor mu?
       Varsayılan EVET: ayar okunamazsa sistem gevşemez, sıkı davranır.
       13 Ağustos 2026 kurul kararıyla ayar false yapıldı; gerekçesi
       ayar.php'de 'yazarlik_doktora_sarti' başlığı altındadır.

       BU KOŞUL YALNIZCA YAZARLIĞA BAKAR. Hakemlik için doktora ayrı bir
       yerde aranır ve bu işlev oraya karışmaz. */
    function tg_yazarlik_doktora_sarti(): bool {
        $a = tg_ayar('yazarlik_doktora_sarti', null);
        if ($a === null) return true;
        return (bool)$a;
    }

    /* =================================================================
       BENZERLİK (İNTİHAL) RAPORU — TEK KAYNAK
       -----------------------------------------------------------------
       Kural 13 Ağustos 2026 kurul kararıyla kaldırıldı; gerekçe
       ayar.php'de yazılıdır. Buradaki işlevler o kararı TEK YERDEN
       okur: sayfalar, sihirbaz ve uç aynı yanıtı alır.

       İşlevler silinmedi, koşula bağlandı. Şart geri açılırsa cümleler
       ve denetimler kendiliğinden döner ve hiçbir sayfada elle
       düzeltme gerekmez.
       ================================================================= */
    function tg_benzerlik_sarti(): bool {
        $a = tg_ayar('benzerlik_raporu_sarti', null);
        /* Ayar yazılmamışsa ESKİ davranış sürer. Bir kuralı sessizce
           kaldıran varsayılan, kaldırıldığını kimseye söylemez. */
        if ($a === null) return true;
        return (bool)$a;
    }

    /* Yayın ilkelerinde ve başvuru sayfasında geçen cümle. */
    function tg_benzerlik_metni(bool $en = false): string {
        if (!tg_benzerlik_sarti()) {
            return tg_c(
                'Benzerlik (intihal) raporu istenmez. Bu sistem raporu ölçemez; ölçemediği bir eşiği şart koşmak '
                  . 'bir güvence değil bir görüntü olurdu. Ayrıca rapor paralıdır ve kapıyı paraya bağlar. '
                  . 'Aşırma yasağı sürer: kaynak gösterilmeden aktarılmış bir tek paragraf, oranı ne olursa olsun '
                  . 'reddi gerektirir ve yayımdan sonra da kaldırma sebebidir. Denetim, metnin herkese açık '
                  . 'durmasıyla ve hakemin adıyla imzaladığı raporla yapılır. Yazar dilerse kendi raporunu ekler; '
                  . 'eklediği rapor çalışmanın sayfasında görünür.',
                'No similarity report is required. This system cannot measure similarity, and to require a threshold '
                  . 'it cannot measure would be an appearance rather than an assurance. Such reports are also paid for, '
                  . 'which would put a price on the door. The prohibition on plagiarism stands: a single paragraph '
                  . 'carried over without attribution warrants rejection whatever the ratio, and remains grounds for '
                  . 'withdrawal after publication. The scrutiny is done by the text standing open to everyone and by '
                  . 'the report a reviewer signs with their name. An author may attach their own report if they wish; '
                  . 'it then appears on the work\'s page.',
                $en);
        }
        $u  = (int)tg_ayar('benzerlik_ust', 15);
        $ut = (int)tg_ayar('benzerlik_tek_ust', 5);
        return tg_cd(
            'Her başvuruya, tanınmış bir benzerlik denetimi yazılımından alınmış rapor eklenmesi zorunludur. '
              . 'Genel benzerlik en çok %%1, tek bir kaynaktan gelen pay en çok %%2 olabilir. Rapor, kaynakça '
              . 'hariç tutularak hazırlanmalı ve yazarın kendi önceki çalışmalarıyla olan örtüşmeyi de göstermelidir.',
            'Every submission must be accompanied by a report from a recognised similarity detection service. '
              . 'Overall similarity may be at most %1%%, and at most %2%% may come from any single source. The report '
              . 'should be prepared with the bibliography excluded and should also show overlap with the author\'s '
              . 'own earlier work.',
            $en, (string)$u, (string)$ut);
    }

    /* Kısa hâli: liste maddelerinde ve sihirbazda kullanılır. */
    function tg_benzerlik_kisa(bool $en = false): string {
        if (!tg_benzerlik_sarti()) {
            return tg_c('Benzerlik raporu istenmez; aşırma yasağı sürer.',
                        'No similarity report is required; the prohibition on plagiarism stands.', $en);
        }
        $u = (int)tg_ayar('benzerlik_ust', 15);
        return tg_cd('Benzerlik raporu zorunludur; genel oran en çok %%1.',
                     'A similarity report is required; overall ratio at most %1%%.', $en, (string)$u);
    }

    function tg_yazarlik_kosulu_metni(bool $en = false): string {
        /* DOKTORA ŞARTI KALKTIYSA ÖNCE O SÖYLENİR. Sıra önemlidir:
           aşağıdaki iki dal da doktoradan söz eder ve biri okunursa
           sistem, uygulamadığı bir koşulu duyurmuş olur. */
        if (!tg_yazarlik_doktora_sarti()) {
            return tg_c(
                'Doktora derecesi aranmaz. Çalışmayı herkes gönderebilir; gönderilen metni editör okur ve '
                  . 'sisteme girip girmeyeceğine karar verir. Kabul edilen çalışma hakemsiz olarak yayımlanır; '
                  . 'yazar dilerse hakem aranmasını ister, dilerse hakemsiz bırakır. Her yazar için ORCID '
                  . 'zorunludur, çünkü bu sistemde adın kime ait olduğunu belirleyen tek numara odur.',
                'No doctorate is required. Anyone may submit a work; an editor reads what is sent and decides '
                  . 'whether it enters the system. An accepted work is published without review; the author may '
                  . 'then ask for reviewers to be sought, or leave it unreviewed. An ORCID is required for every '
                  . 'author, because it is the only number that ties a name to a person in this system.',
                $en);
        }
        if (tg_yazarlik_hakemlik_sarti()) {
            return tg_c('Tamamlanmış en az bir hakemlik ya da gereken sayıda geçerli kurul oyu.', 'At least one completed review, or the required number of valid panel votes.', $en);
        }
        $t = tg_kurulus_bitis_ad($en);
        /* TARİH METNİN İÇİNE DEĞİL YERİNE KONUR (bkz. tg_cd).
           Eskiden $t doğrudan cümlenin içine giriyordu; hs_tarih o anki
           dilde yazdığı için "İngilizce" metnin içinde Arapça bir ay adı
           duruyor, çeviri anahtarı da dile göre değişiyordu. Ölçüldü:
           aynı cümle altı dilde altı ayrı anahtar üretiyordu ve hiçbiri
           sözlükte bulunamıyordu. Tarihli ve tarihsiz iki ayrı cümle
           kurulur, çünkü ikisinin dizimi ayrıdır; ama her ikisinin de
           anahtarı sabittir. */
        if ($t !== '') {
            return tg_cd(
                'Kuruluş döneminde önceden hakemlik yapmış olmak aranmaz; dönem %1 tarihinde biter. '
                  . 'Doğrulanmış bir doktora derecesi ve ORCID, çalışma göndermeye yeter. '
                  . 'Nedeni açıktır: arşiv boşken değerlendirilecek çalışma yoktur, '
                  . 'koşul da sisteme ilk çalışmanın girmesini engeller.',
                'No prior review is asked for during the founding period, which runs to %1. '
                  . 'A verified doctorate and an ORCID are enough to submit. '
                  . 'The reason is plain: while the archive is empty there is nothing to review, '
                  . 'so the condition would keep the first work out of the system altogether.',
                $en, $t);
        }
        return tg_c(
            'Kuruluş döneminde önceden hakemlik yapmış olmak aranmaz. '
              . 'Doğrulanmış bir doktora derecesi ve ORCID, çalışma göndermeye yeter. '
              . 'Nedeni açıktır: arşiv boşken değerlendirilecek çalışma yoktur, '
              . 'koşul da sisteme ilk çalışmanın girmesini engeller.',
            'No prior review is asked for during the founding period. '
              . 'A verified doctorate and an ORCID are enough to submit. '
              . 'The reason is plain: while the archive is empty there is nothing to review, '
              . 'so the condition would keep the first work out of the system altogether.',
            $en);
    }

    /* =================================================================
       GÜVEN İŞARETLERİ — TEK KAYNAK
       -----------------------------------------------------------------
       ISSN, DOAJ, COPE, DOI tescili, dizinler... Bunlar bir yayın
       sisteminin "güvenilir" sayılmasını sağlayan dış işaretlerdir ve
       hepsinin ortak bir tehlikesi vardır: SAHİP OLUNMAYAN BİR İŞARETİ
       BASMAK, BU SİSTEMİN EN PAHALI YALANI OLUR. Bir kurum bunu bir kez
       yakalarsa, doğru söylenen her şey de şüpheli olur.

       Bu yüzden üç kural:

         1. Bir işaret ancak DURUMU 'alindi' VE TARİHİ varsa alınmış
            sayılır. Tarihsiz bir "alındı" iddiası kabul edilmez;
            tg_guven_isaretleri() onu kendiliğinden 'yok'a düşürür.
            Tarih, iddiayı denetlenebilir yapan şeydir.
         2. Alınmamış işaretler LİSTEDEN ÇIKARILMAZ, "alınmadı" diye
            yazılır. Eksik olanı listeden düşürmek, sayfayı temiz ama
            okuru yanlış bilgilendirilmiş bırakır.
         3. Makine üstverisine (OAI, schema.org) alınmamış bir kimlik
            YAZILMAZ. Sayfada dürüst olup üstveride boş bir ISSN
            basmak, dizinlere yalan söylemektir.

       Bugünkü durum ayar.php'de yazılıdır ve bugün hepsi alınmamıştır.
       Bir işaret alındığında değişecek olan tek yer orasıdır; sayfalar,
       üstveri ve bildiri metni oradan okur.
       ================================================================= */
    function tg_guven_isaretleri(): array {
        /* Tanımlı işaretler ve ne oldukları. Sıra, bir kurumun bakma
           sırasıdır: önce kimlik, sonra lisans, sonra süreç. */
        $tanim = [
            'issn'    => ['tr' => 'ISSN', 'en' => 'ISSN',
                          'ack' => ['Süreli yayının uluslararası kimliği.', 'The international identifier of a serial publication.']],
            'doi'     => ['tr' => 'DOI tescili', 'en' => 'DOI registration',
                          'ack' => ['Her çalışmaya kalıcı bir kimlik veren tescil kuruluşu anlaşması.', 'An agreement with a registration agency giving each work a persistent identifier.']],
            'doaj'    => ['tr' => 'DOAJ', 'en' => 'DOAJ',
                          'ack' => ['Açık erişimli dergiler dizini.', 'The Directory of Open Access Journals.']],
            'cope'    => ['tr' => 'COPE üyeliği', 'en' => 'COPE membership',
                          'ack' => ['Yayın etiği kuruluşu üyeliği.', 'Membership of the Committee on Publication Ethics.']],
            'dizin'   => ['tr' => 'Dizinlerde taranma', 'en' => 'Indexing',
                          'ack' => ['Alan dizinlerinde taranma durumu.', 'Coverage by subject indexes.']],
            'arsiv'   => ['tr' => 'Uzun süreli saklama', 'en' => 'Long term preservation',
                          'ack' => ['Arşivin bağımsız bir kurumda saklanması taahhüdü.', 'A commitment that the archive is preserved by an independent institution.']],
        ];
        $ayar = (array)tg_ayar('guven_isaretleri', []);
        $cikti = [];
        foreach ($tanim as $k => $t) {
            $a = (array)($ayar[$k] ?? []);
            $durum = (string)($a['durum'] ?? 'yok');
            $tarih = trim((string)($a['tarih'] ?? ''));
            $no    = trim((string)($a['no'] ?? ''));
            $url   = trim((string)($a['url'] ?? ''));
            if (!in_array($durum, ['alindi', 'basvuruldu', 'yok'], true)) $durum = 'yok';
            /* TARİHSİZ "ALINDI" KABUL EDİLMEZ. Bir iddia, ne zaman
               doğru olduğu söylenmeden denetlenemez; denetlenemeyen
               iddia da işaret değil, süstür. */
            if ($durum === 'alindi' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tarih)) $durum = 'yok';
            /* Gelecek bir tarih de kabul edilmez: henüz olmamış bir şey
               olmuş gibi yazılamaz. */
            if ($durum === 'alindi' && $tarih > date('Y-m-d')) $durum = 'yok';
            $cikti[$k] = ['k' => $k, 'tr' => $t['tr'], 'en' => $t['en'], 'ack' => $t['ack'],
                          'durum' => $durum, 'tarih' => $durum === 'alindi' ? $tarih : '',
                          'no' => $durum === 'alindi' ? $no : '', 'url' => $durum === 'alindi' ? $url : ''];
        }
        /* ---- DOI: DURUM AYARDAN DEĞİL, GERÇEKTEN YAPILAN İŞTEN ----
           20 Ağustos 2026. Zenodo düzeni açıldığında her yayımlanan
           çalışmaya kalıcı kimlik veriliyor; bu satırın "alınmadı"
           demeyi sürdürmesi, sistemin kendi yaptığı işi inkâr etmesi
           olurdu. Ama "DOI tescili" başlığı da olduğu gibi bırakılamaz:
           tescil, bir kayıt kuruluşuyla YAPILAN ANLAŞMADIR ve Kutadgu'nun
           böyle bir anlaşması yok — kimlikleri Zenodo kendi adına veriyor
           ve adres Zenodo kaydına çözülüyor. İkisi ayrı şeydir; ayrı
           yazılır. Deneme evreninde ise hiçbir şey iddia edilmez:
           oradaki kimlik gerçek değildir. */
        if (function_exists('tg_zenodo_acik') && tg_zenodo_acik() && empty(tg_zenodo_ayar()['sandbox'])) {
            $cikti['doi']['durum'] = 'alindi';
            $cikti['doi']['tarih'] = $cikti['doi']['tarih'] !== '' ? $cikti['doi']['tarih'] : date('Y-m-d');
            $cikti['doi']['tr']    = 'Kalıcı kimlik (DOI)';
            $cikti['doi']['en']    = 'Persistent identifier (DOI)';
            $cikti['doi']['ack']   = [
                'Yayımlanan her çalışmaya Zenodo (CERN) üzerinden kalıcı bir kimlik verilir. Bu bir tescil kuruluşu ÜYELİĞİ değildir: kimliği Zenodo kendi adına verir ve adres Zenodo kaydına çözülür.',
                'Every published work is given a persistent identifier through Zenodo (CERN). This is not a membership of a registration agency: Zenodo issues the identifier in its own name and the address resolves to the Zenodo record.'];
        }
        return $cikti;
    }

    /* Bir işaret gerçekten alınmış mı. Makine üstverisi bunu sorar. */
    function tg_guven_alindi(string $k): bool {
        $i = tg_guven_isaretleri();
        return ($i[$k]['durum'] ?? 'yok') === 'alindi';
    }

    /* Alınmış bir işaretin numarası; alınmamışsa BOŞ DİZE.
       Üstveriye boş dize yazılmaz, alan hiç basılmaz. */
    function tg_guven_no(string $k): string {
        $i = tg_guven_isaretleri();
        return ($i[$k]['durum'] ?? '') === 'alindi' ? (string)($i[$k]['no'] ?? '') : '';
    }

    /* Durumun okura görünen adı. Tek kaynak: sayfa da bildiri de
       buradan okur, ikisi ayrışmaz. */
    function tg_guven_durum_ad(string $durum, bool $en = false): string {
        switch ($durum) {
            case 'alindi':     return tg_c('alındı', 'obtained', $en);
            case 'basvuruldu': return tg_c('başvuruldu', 'applied for', $en);
            default:           return tg_c('alınmadı', 'not obtained', $en);
        }
    }

    /* Sayım: kaçı alındı, kaçı başvuruldu, kaçı yok. */
    function tg_guven_sayim(): array {
        $s = ['alindi' => 0, 'basvuruldu' => 0, 'yok' => 0];
        foreach (tg_guven_isaretleri() as $i) $s[$i['durum']]++;
        return $s;
    }

    /* =================================================================
       ÜÇÜNCÜ DİL — ÇEVİRİ KATMANI
       -----------------------------------------------------------------
       Arayüzde 1883 adet k_c('Türkçe', 'English') çağrısı var. Üçüncü
       bir dil açmanın akla ilk gelen yolu bu 1883 çağrıyı k_t([...])
       biçimine geçirmekti; yapılmadı, ve yapılmaması bilerekti:

         - 1883 çağrının elle ya da makineyle dönüştürülmesi, tek bir
           kaçak tırnak yüzünden sayfa kırabilecek en pahalı değişiklik
           olurdu ve karşılığında hiçbir dil kazanılmazdı.
         - Asıl engel çağrının BİÇİMİ değildi: k_c iki dize alıyordu,
           yani üçüncü dilin konacağı bir yer yoktu.

       Bu yüzden yer, çağrının içinde değil ÜSTÜNDE açıldı. k_c artık
       Türkçe ve İngilizce dışında bir dil istendiğinde bir çeviri
       katmanına bakar. Katmanın anahtarı İngilizce dizedir: kaynakta
       zaten duran, biricik ve okunabilir bir anahtar. Böylece 1883
       çağrının hiçbiri değişmeden hepsi üçüncü dile açık hâle geldi.

       ÇEVİRİ YOKSA NE OLUR: metin İngilizceye düşer (dilin 'yedek'i),
       sayfa eksik görünmez ve ziyaretçiye bu dilin henüz gözden
       geçirilmediği AÇIKÇA söylenir. Sessizce yarım bir arayüz
       göstermek, bu sistemin dürüstlük iddiasına en çok zarar veren
       şey olurdu.

       MAKİNE ÇEVİRİSİ BU DOSYAYA KENDİLİĞİNDEN YAZILMAZ. Sözlük veri
       dizinindedir ve oraya ne konduğu insanın işidir; kod yalnız
       okur.
       ================================================================= */
    function tg_ceviri_dizin(): string { return tg_veri_dizini() . '/ceviri'; }

    /* =================================================================
       SÖZLÜK İKİ YERDEN OKUNUR VE SIRA ÖNEMLİDİR
       -----------------------------------------------------------------
       1. k/ceviri/<dil>.json   — DEPODAKİ sözlük. Asıl yeri burasıdır.
       2. <veri>/ceviri/<dil>.json — SUNUCUDAKİ üstyazım. Varsa kazanır.

       NEDEN DEPO ASIL YER: bir arayüz dizesi ile onun çevirisi aynı
       şeyin iki yüzüdür. Dize değiştiğinde çevirisi de değişmelidir ve
       bunun olabilmesi için ikisinin AYNI sürümde durması gerekir.
       Sözlük yalnız veri dizininde dursaydı, kod ilerledikçe sözlük
       sessizce eskirdi: eski çeviri yeni dizeyle eşleşmez, eşleşmeyen
       dize İngilizce görünür ve kimse bunu bir kusur olarak görmezdi.
       Çeviri bir veri değil, bir metindir; metin depoda durur.

       NEDEN ÜSTYAZIM YİNE DE VAR: bir sözcüğü düzeltmek için sürüm
       çıkarmak zorunda kalmak, yanlış sözcüğün orada kalmasına yol
       açar. Sunucudaki dosya tek tek anahtar bazında üste yazar; orada
       olmayan anahtar depodakinden gelir. Böylece elle yapılmış küçük
       bir düzeltme, yeni sürümün getirdiği 1800 dizeyi silmez.

       MAKİNE ÇEVİRİSİ İKİSİNE DE KENDİLİĞİNDEN YAZILMAZ. Kod yalnız
       okur; ne konduğu insanın işidir.
       ================================================================= */
    function tg_ceviri_sozluk(string $dil): array {
        static $bellek = [];
        if (isset($bellek[$dil])) return $bellek[$dil];
        $ad = preg_replace('/[^a-z-]/', '', strtolower($dil)) . '.json';
        $oku = function (string $p): array {
            if (!is_file($p)) return [];
            $j = json_decode((string)@file_get_contents($p), true);
            return is_array($j) ? $j : [];
        };
        $d = $oku(__DIR__ . '/k/ceviri/' . $ad);
        /* array_merge değil array_replace: anahtarlar dizedir ve
           array_merge sayısal görünen bir anahtarı yeniden numaralar.
           "2024" gibi bir anahtar sözlükte olabilir. */
        $d = array_replace($d, $oku(tg_ceviri_dizin() . '/' . $ad));
        return $bellek[$dil] = $d;
    }

    /* Anahtar: İngilizce dizenin kendisidir. Uzun dizeler için kısa bir
       özet kullanılır ki sözlük dosyası okunabilir kalsın; kısa olanlar
       olduğu gibi durur, çünkü bir çevirmenin gördüğü ilk şey anahtar
       olur ve anlaşılmaz bir özet çevirmeni kör bırakır. */
    function tg_ceviri_anahtar(string $en): string {
        $t = trim(preg_replace('/\s+/u', ' ', $en));
        return mb_strlen($t, 'UTF-8') <= 120 ? $t : ('#' . substr(sha1($t), 0, 16));
    }

    /* Bu istekte kaç dize çeviri bulamadı. Sayfanın altındaki uyarı
       bunu söyleyebilsin diye sayılır; hiçbir yere yazılmaz. */
    function tg_ceviri_eksik(?int $arttir = null): int {
        static $n = 0;
        if ($arttir !== null) $n += $arttir;
        return $n;
    }

    /* Çeviriyi bul; yoksa yedek metni ver ve eksiği say. */
    function tg_ceviri_bul(string $en, string $dil): string {
        $s = tg_ceviri_sozluk($dil);
        $a = tg_ceviri_anahtar($en);
        if (isset($s[$a]) && trim((string)$s[$a]) !== '') return (string)$s[$a];
        tg_ceviri_eksik(1);
        return $en;
    }

    /* Bir dil insan gözünden geçti mi. Varsayılan HAYIR: bir dilin
       gözden geçirildiğini söylemek, söyleyenin sorumluluğudur ve
       varsayılan olarak üstlenilmez. */
    function tg_dil_gozden_gecirildi(string $dil): bool {
        if ($dil === 'tr' || $dil === 'en') return true;
        $d = (array)tg_ayar('diller', []);
        return !empty($d[$dil]['gozden_gecirildi']);
    }

    /* =================================================================
       ZİYARETÇİNİN ÜLKESİ — TEK KAYNAK
       -----------------------------------------------------------------
       Ülke iki yerde işe yarar: sayfanın dili buradan tahmin edilir
       (k_dil zincirinin üçüncü basamağı) ve okuma kaydına ülke olarak
       bu yazılır. İkisi ayrı ayrı çözülürse zamanla ayrışır ve sistem
       okura bir şey, kaydına başka bir şey söyler.

       Adres HİÇBİR ZAMAN okunmaz ve hiçbir yere gönderilmez: ülke
       vekil sunucunun (Cloudflare) koyduğu başlıktan gelir. Ham adres
       yazılmaz; toplanmayan veri sızdırılamaz.
       ================================================================= */
    function tg_ulke(): string {
        $u = strtoupper(trim((string)($_SERVER['HTTP_CF_IPCOUNTRY']
            ?? $_SERVER['HTTP_X_VERCEL_IP_COUNTRY']
            ?? $_SERVER['GEOIP_COUNTRY_CODE']
            ?? '')));
        return preg_match('/^[A-Z]{2}$/', $u) ? $u : '';
    }
    /* Sayfa katmanındaki adı; çağrı yerleri değişmesin diye duruyor. */
    function k_ulke(): string { return tg_ulke(); }

    /* =================================================================
       GÖNDERİM SİHİRBAZININ ADIMLARI — TEK KAYNAK
       -----------------------------------------------------------------
       Başvuru formu üç ayrı yerde aynı listeyi gösterir: form bölümleri,
       sağ raydaki içindekiler ve sihirbazın adım şeridi. Liste üç yere
       elle yazılırsa zamanla ayrışır; bu projede bu hata dört kez
       bulundu (devir belgesi, "tek kaynak kuralı"). Bu yüzden üçü de
       burayı okur.

       SIRA BEHİÇ'İN TARİFİDİR: "önce makale dilini seçecek, sonra
       başlık, sonra İngilizce başlık." Yani çalışma en başta gelir.
       Eskiden form başvuranın adıyla başlıyordu; oysa insan önce
       gönderdiği ŞEYİ tanımlar, sonra kendini. Kimlik alanları da
       çalışmanın ne olduğu belliyken doldurulunca anlamlanır.

       Telif en sonda durur ve orada durması gerekir: imza, imzalanan
       şeyin tamamı görüldükten sonra atılır.
       ================================================================= */
    function tg_basvuru_adimlari(): array {
        $a = [
            /* KOŞULLAR SİHİRBAZIN BİRİNCİ ADIMIDIR, SAYFANIN ÖNSÖZÜ
               DEĞİL. Ölçüldü: koşullar sayfanın başında ayrı bir bölüm
               olarak dururken formun ilk alanı 2186 pikselde başlıyordu;
               yani yazar, form alanını görmeden önce iki ekran boyu
               metin kaydırıyordu. Bildirilen şikâyet buydu: "kurallar
               başta yazan sayfaya geliyor, altındaki formla gönderiyor."
               Koşullar kaldırılmadı, silinmedi, kısaltılmadı; okunacağı
               yere, akışın içine kondu. Adım listesi tek kaynaktır:
               şerit, yan ray, <legend>ler ve JS doğrulama hepsi burayı
               okur. */
            ['k' => 'kosul',     'tr' => 'Gönderim koşulları',   'en' => 'Submission conditions'],
            ['k' => 'calisma',   'tr' => 'Çalışma',              'en' => 'The work'],
            /* ---- TAM METİN ARTIK GÖNDERİM ANINDA ----
               Kurul kararı, 15 Ağustos 2026: "çalışma gönder kısmında
               hâlâ tam metnin, şekillerin ekleneceği yer yok; yazdığı
               metni şekillendirebilmesi lazım, onun için editör
               kurdurmuştur."

               ÖNCEKİ TASARIM YANLIŞTI. Tam metin, editör KABUL
               ETTİKTEN SONRA yazma ekranında isteniyordu. Ama editörün
               vereceği karar tam olarak "bu çalışma sisteme girsin mi"
               sorusudur ve o soru iki yüz kelimelik bir özetle
               yanıtlanamaz: editör görmediği bir metni kabul ediyordu.
               Kabul edilen çalışma da hakemsiz yayımlandığı için,
               görülmemiş metin doğrudan yayına gidiyordu.

               Metin ÇALIŞMA adımının hemen ardındadır: başlık ve özeti
               yazan kişi, sıradaki adımda metnin kendisini koyar.
               Beyanlar (etik, veri, yapay zekâ) metinden sonra gelir,
               çünkü hepsi metnin İÇERİĞİ hakkındadır. */
            ['k' => 'metin',     'tr' => 'Tam metin',            'en' => 'The full text'],
            ['k' => 'basvuran',  'tr' => 'Başvuran',             'en' => 'Applicant'],
            ['k' => 'ortak',     'tr' => 'Ortak yazarlar',       'en' => 'Co authors'],
            /* BENZERLİK ADIMI, ŞART KAPALIYKEN HİÇ YOKTUR.
               Önce adın sonuna "(isteğe bağlı)" eklenmişti; yetmedi.
               Kurul kararı (15 Ağustos 2026): "benzerlik kısmını kaldır,
               gerek yok demiştik." Doğru: isteğe bağlı bir adım da bir
               adımdır — şeritte yer kaplar, sayıyı büyütür ve yazara
               "burada bir iş var" dedirtir. Oysa hiçbir alanı aranmıyor.

               Adım SİLİNMEDİ, koşula bağlandı: kurul şartı geri açarsa
               adım da geri gelir. Liste tek kaynaktır — şerit, yan ray,
               <legend>ler ve doğrulama hepsi burayı okur, dolayısıyla
               tek satır bütün sihirbazı değiştirir. */
            ['k' => 'etik',      'tr' => 'Etik kurul izni',      'en' => 'Ethics approval'],
            ['k' => 'veri',      'tr' => 'Veri ve kod',          'en' => 'Data and code'],
            /* ADIM "YAPAY ZEKÂ BEYANI" DEĞİL, "BEYANLAR".
               ScholarOne'ın beşinci adımı (Details & Comments) bütün
               beyanları tek yerde topluyor: çıkar çatışması, başka
               yerde değerlendirilmeme, fon, yapay zekâ. Kutadgu'da
               yapay zekâ beyanı tek başına bir adımdı ve ötekiler HİÇ
               YOKTU — çıkar çatışması, akademik yayıncılıkta en
               standart beyandır ve sistemin "açıklık" sözüyle doğrudan
               ilgilidir.

               YENİ ADIM AÇILMADI, VAR OLAN GENİŞLETİLDİ: on bir adımlık
               bir forma on ikinci adımı eklemek, kurulun "sadeleştir"
               isteğinin tersiydi. Birbirine benzeyen şeyler bir arada
               durur. */
            ['k' => 'yz',        'tr' => 'Beyanlar',             'en' => 'Declarations'],
            ['k' => 'sekil',     'tr' => 'Şekiller ve çözümleme','en' => 'Figures and analysis'],
            ['k' => 'hakem',     'tr' => 'Hakem önerisi',        'en' => 'Suggested reviewers'],
            /* SON ADIM ARTIK "GÖZDEN GEÇİR VE GÖNDER".
               ScholarOne'ın altıncı adımı incelendi (15 Ağustos 2026):
               bütün adımların özeti tek ekranda, her bölümün yanında
               "Düzenle", en üstte eksik kalan her şeyin listesi.
               Kutadgu ilk eksikte durup yalnız onu söylüyordu; on bir
               adımlık bir formda bu, kişiyi adım adım geri gönderir.

               Adım ANAHTARI 'telif' kaldı, adı değişti: anahtar
               kayıtlarda ve bağlarda geçiyor, adı ise okurun gördüğü
               şey. Lisans beyanı kalkmadı — gözden geçirmenin ardından,
               göndermeden hemen önce duruyor; imzalanacak yer orası. */
            ['k' => 'telif',     'tr' => 'Gözden geçir ve gönder', 'en' => 'Review and submit'],
        ];
        if (tg_benzerlik_sarti()) {
            /* Şart açıksa adım ORTAK YAZARLAR'dan sonra, ETİK'ten önce
               durur. Sıra numarası ELLE sayılmaz: araya TAM METİN adımı
               girdiğinde 4 sabiti sessizce yanlış yere kayardı ve
               benzerlik adımı ortak yazarların arasına düşerdi.
               Anahtarın yeri anahtarla bulunur. */
            $ix = count($a);
            foreach ($a as $ai => $aa) if ($aa['k'] === 'etik') { $ix = $ai; break; }
            array_splice($a, $ix, 0, [[
                'k' => 'benzerlik', 'tr' => 'Benzerlik raporu', 'en' => 'Similarity report',
            ]]);
        }
        return $a;
    }

    /* İstenen adım numarası: 1 ile adım sayısı arasına sıkıştırılır.
       Uydurma bir değer (dizge, negatif, aşırı büyük) sessizce birinci
       adıma düşer; sayfaya hiçbir zaman ham hâliyle basılmaz. */
    function tg_basvuru_adim_no($ham): int {
        $n = (int)preg_replace('/\D/', '', (string)$ham);
        $son = count(tg_basvuru_adimlari());
        if ($n < 1) return 1;
        return $n > $son ? 1 : $n;
    }

    /* Aynı bilginin kenar notu boyu: kart altlarında ve yan sütunda. */

    /* ---- HAKEMLİK YAZARLIĞIN KAPISI MI ----
       ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi (ekran
       görüntüsüyle): "bak burada yazarlık bilgisi yanlış."

       Doğruydu ve sayfa kendi kendisiyle çelişiyordu. bekleyen.php aynı
       ekranda şu ikisini birden yazıyordu:
         "Hakemlik yapmak bu sistemde yazarlığın da kapısıdır:
          değerlendirme yapan bir araştırmacı kendi çalışmasını gönderme
          hakkını kazanır."
         "Doktora derecesi aranmaz: çalışmayı herkes gönderebilir,
          kararı editör verir."
       İkincisi tek kaynaktan (tg_yazarlik_kosulu_kisa) geliyordu ve
       doğruydu; birincisi ELLE yazılmıştı ve 13 Ağustos'ta kaldırılmış
       bir kuralı anlatıyordu. Kural gerçekten kapalı:
       tg_yazarlik_hakemlik_sarti() bugün false döner.

       Aynı cümle ilkeler.php ve nasil-isler.php'de de elle duruyordu.
       Bir kural üç sayfada üç kez yazıldığında, kaldırıldığı gün üç
       yerin de bulunması gerekir; biri unutulur. Cümle artık BURADAN
       gelir ve kuralı kendisi sorar.

       BOŞ DÖNMEZ, DOĞRUSUNU SÖYLER: kural kapalıyken sayfada bir boşluk
       bırakmak, okurun aklındaki soruyu yanıtsız bırakırdı — "peki
       hakemlik ne kazandırır?" Yanıt bugün şudur: hakemlik bir
       yükümlülük değil, emeğin görünür kaydıdır. */
    function tg_hakemlik_yazarlik_cumlesi(bool $en = false): string {
        if (tg_yazarlik_hakemlik_sarti()) {
            return tg_c(
                'Hakemlik bu sistemde yazarlığın da kapısıdır: değerlendirme yapan bir araştırmacı kendi çalışmasını gönderme hakkını kazanır.',
                'Reviewing is also the door to authorship here: a researcher who assesses a work earns the right to submit their own.',
                $en);
        }
        return tg_c(
            'Hakemlik yazarlığın önkoşulu değildir: çalışmayı herkes gönderebilir. Değerlendirme yapmak bir hak kazandırmaz, emeğinizi adınızla kalıcı olarak kayda geçirir; raporunuz çalışmayla birlikte yayımlanır ve size atıf verilebilir.',
            'Reviewing is not a precondition for authorship: anyone may submit a work. Assessing does not earn you a right; it records your labour permanently under your name. Your report is published together with the work and can be cited.',
            $en);
    }

    /* Kenar kutusunun başlığı da kurala bağlıdır: başlık iddiayı taşır,
       gövde onu açar. Başlık sabit kalsaydı kutu "kapısıdır" der, içi
       "değildir" derdi. */
    function tg_hakemlik_yazarlik_basligi(bool $en = false): string {
        return tg_yazarlik_hakemlik_sarti()
            ? tg_c('Hakemlik yazarlığın kapısıdır', 'Reviewing is the door to authorship', $en)
            : tg_c('Hakemlik ne kazandırır', 'What reviewing gives you', $en);
    }

    function tg_yazarlik_kosulu_kisa(bool $en = false): string {
        /* Doktora şartı kalktıysa kenar notu da onu söyler; aşağıdaki
           metinlerin hepsi doktoradan söz eder ve biri çizilirse sistem
           uygulamadığı bir koşulu duyurmuş olur. */
        if (!tg_yazarlik_doktora_sarti()) {
            return tg_c(
                'Doktora derecesi aranmaz: çalışmayı herkes gönderebilir, kararı editör verir.',
                'No doctorate is required: anyone may submit, and an editor decides.',
                $en);
        }
        if (tg_yazarlik_hakemlik_sarti()) return '';
        /* Cümle parça parça değil, TAM olarak çeviri katmanından geçer:
           tarih araya girdiği için iki dilin dizimi de ayrı. Sözlükte
           karşılığı yoksa İngilizcesi görünür (yedek dil). */
        $t = tg_kurulus_bitis_ad($en);
        if ($t !== '') {
            return tg_cd(
                'Bu, kazanılan haktır, aranan koşul değildir: kuruluş döneminde %1 tarihine kadar '
                  . 'doğrulanmış bir doktora derecesi tek başına çalışma göndermeye yeter.',
                'This is what is gained, not what is required: during the founding period, to %1, '
                  . 'a verified doctorate is on its own enough to submit.',
                $en, $t);
        }
        return tg_c(
            'Bu, kazanılan haktır, aranan koşul değildir: kuruluş döneminde '
              . 'doğrulanmış bir doktora derecesi tek başına çalışma göndermeye yeter.',
            'This is what is gained, not what is required: during the founding period, '
              . 'a verified doctorate is on its own enough to submit.',
            $en);
    }

    /* Arşivin tamamı, bir kez okunur. */
    function tg_yazilar_oku(): array {
        static $y = null;
        if ($y !== null) return $y;
        $p = tg_veri_dizini() . '/yazilar.json';
        $y = [];
        if (is_file($p)) {
            $d = json_decode((string)file_get_contents($p), true);
            if (is_array($d)) $y = $d;
        }
        return $y;
    }

    /* Yazarlık sayımı: ad anahtarı => ['yayin' => n, 'onayli' => n].
       Kurul üyeliği ölçütü de bunu kullanır. */
    function tg_yayin_sayimi(array $yazilar): array {
        $out = [];
        foreach ($yazilar as $y) {
            if (!is_array($y)) continue;
            $onayli = tg_onay_durumu($y)['onayli'];
            $adlar = [];
            $ilk = trim(tg_metin($y['yazar'] ?? ''));
            if ($ilk !== '') $adlar[tg_ad_anahtar($ilk)] = true;
            foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
                if (!is_array($ya)) continue;
                $n = trim(tg_metin($ya['ad'] ?? ''));
                if ($n !== '') $adlar[tg_ad_anahtar($n)] = true;
            }
            foreach (array_keys($adlar) as $k) {
                if ($k === '') continue;
                if (!isset($out[$k])) $out[$k] = ['yayin' => 0, 'onayli' => 0];
                $out[$k]['yayin']++;
                if ($onayli) $out[$k]['onayli']++;
            }
        }
        return $out;
    }

    /* ---- Ölçütü karşılayanlar ----
       Dönen: ad anahtarı => ['atama'=>n, 'yayin'=>n, 'onayli'=>n].
       Ölçüt başlamadıysa boş döner. Sayım arşivin o anki hâlinden
       yapılır; hiçbir yere yazılmaz, çünkü yazılan bir yetki geri
       alınamaz hâle gelir ve bu sistemin sözüne aykırı olur. */
    function tg_bas_olcut_saglayanlar(): array {
        static $s = null;
        if ($s !== null) return $s;
        $s = [];
        if (!tg_bas_olcut_isliyor()) return $s;
        $o = tg_bas_olcut();
        $y = tg_yazilar_oku();
        /* Görevi bitmiş olanlarda sayaç, bitiş gününde sıfırlanır.
           Gerekçesi tg_atama_sayimi()'nin başındadır. */
        $atama = tg_atama_sayimi($y, tg_gorev_bitis_haritasi());
        $yayin = $o['kurul'] ? tg_yayin_sayimi($y) : [];
        $eYayin  = (int)tg_ayar('kurul_yayin', 20);
        $eOnayli = (int)tg_ayar('kurul_onayli', 10);
        foreach ($atama as $ad => $n) {
            if ($n < $o['atama']) continue;
            $yv = $yayin[$ad] ?? ['yayin' => 0, 'onayli' => 0];
            if ($o['kurul'] && ($yv['yayin'] < $eYayin || $yv['onayli'] < $eOnayli)) continue;
            $s[$ad] = ['atama' => $n, 'yayin' => $yv['yayin'], 'onayli' => $yv['onayli']];
        }
        return $s;
    }

    /* ---- Bir değeri güvenle metne çevirir ----
       PHP'de bir diziyi metne çevirmek "Array" sözcüğünü verir ve bir
       uyarı basar. Bu, hakem dizinini düşüren hatayla aynı ailedendir:
       bir alanın beklenen biçimde olduğu varsayılır, bir gün başka
       biçimde gelir ve kimse fark etmez.

       Anahtar kelimeler bu sistemde virgülle ayrılmış tek bir metindir.
       Ama bir istemci ya da bir içe aktarma bunu dizi olarak
       gönderebilir; o zaman kayda "Array" sözcüğü yazılır ve asıl değer
       kaybolurdu. Bu işlev diziyi birleştirir, ötekini olduğu gibi
       bırakır. */
    function tg_metin($v, string $ayrac = ', '): string {
        if (is_array($v)) {
            $p = [];
            foreach ($v as $x) {
                $x = trim(tg_metin($x, $ayrac));
                if ($x !== '') $p[] = $x;
            }
            return implode($ayrac, $p);
        }
        if ($v === null) return '';
        if (is_bool($v)) return $v ? '1' : '';
        if (is_object($v)) return method_exists($v, '__toString') ? (string)$v : '';
        return (string)$v;
    }

    /* Kısa tanıtım. Düz metindir: HTML yoktur, biçimlendirme yoktur ve
       araya sıkışan boşluklar tek boşluğa iner. 600 karakter, çünkü
       kişi sayfası bir özgeçmiş değil bir kayıttır; uzadıkça okunmaz
       olur ve kaydın kendisini gölgeler. */
    function tg_tanitim($ham): string {
        if (is_array($ham)) return '';
        $t = (string)preg_replace('#<[^>]*>#', '', (string)$ham);
        $t = (string)preg_replace('/\s+/u', ' ', $t);
        return mb_substr(trim($t), 0, 600);
    }

    function tg_uyelikler($ham): array {
        if (is_string($ham)) $ham = preg_split('/\R+/u', $ham) ?: [];
        if (!is_array($ham)) return [];
        $out = [];
        foreach ($ham as $u) {
            $u = mb_substr(trim(preg_replace('#<[^>]*>#', '', (string)$u)), 0, 160);
            if ($u === '') continue;
            $out[] = $u;
            if (count($out) >= 12) break;
        }
        return $out;
    }

    function tg_dis_profil_ad(string $url, string $etiket = '', bool $en = false): string {
        $etiket = trim($etiket);
        if ($etiket !== '') return $etiket;
        $u = mb_strtolower(trim($url), 'UTF-8');
        if ($u === '') return '';
        $bilinen = [
            'yoksis'         => 'YÖKSİS',
            'abs.'           => 'ABS profili',
            'avesis'         => 'AVESİS',
            'aperta'         => 'Aperta',
            'orcid.org'      => 'ORCID',
            'scholar.google' => 'Google Scholar',
            'researchgate'   => 'ResearchGate',
            'academia.edu'   => 'Academia.edu',
            'publons'        => 'Publons',
            'webofscience'   => 'Web of Science',
            'scopus'         => 'Scopus',
            'linkedin'       => 'LinkedIn',
            'github'         => 'GitHub',
            'zenodo'         => 'Zenodo',
            'osf.io'         => 'OSF',
            'hal.science'    => 'HAL',
            'cris.'          => 'CRIS',
            'pure.'          => 'Pure',
        ];
        foreach ($bilinen as $par => $ad) { if (mb_strpos($u, $par) !== false) return $ad; }
        if (preg_match('#\.edu(\.[a-z]{2})?/|\.ac\.[a-z]{2}/|\.edu\.tr#', $u)) {
            return tg_c('Kurum profili', 'Institutional profile', $en);
        }
        /* Tanınmayan adres için eskiden düz "Profil" yazılıyordu. Kartın
           gövdesi zaten kişinin bu sistemdeki sayfasına gidiyor; altta
           "Profil" yazan ikinci bir bağlantı görünce okur onu kişinin
           profili sanıp dışarı çıkıyordu. Artık alan adı yazılır: nereye
           gideceği bağlantının üstünde okunur. */
        $sunucu = (string)parse_url($u, PHP_URL_HOST);
        $sunucu = (string)preg_replace('#^www\.#', '', $sunucu);
        if ($sunucu !== '') return $sunucu;
        return tg_c('Dış bağlantı', 'External link', $en);
    }

    /* =================================================================
       İLETİŞİM GÖRÜNÜRLÜĞÜ

       Her iletişim alanı için üç düzey vardır:

         acik    Herkese açık. Sayfayı açan herkes görür.
         uyeler  Yalnızca sisteme girmiş kişilere açık: yazar, hakem,
                 editör. Giriş yapmamış okur ve toplayıcı göremez.
         gizli   Hiç gösterilmez.

       VARSAYILANLAR GERİYE DÖNÜK DEĞİŞTİRİLMEZ. E-posta ve telefon
       varsayılan olarak GİZLİDİR. Sebebi şudur: bu sistem kayıt sırasında
       "hiçbir kişi sayfasında e-posta adresi görünmez" sözünü verdi ve
       insanlar hesaplarını o söze bakarak açtı. Varsayılanı görünüre
       çevirmek, kimseye sormadan onların adreslerini yayımlamak olurdu.
       Görünür olmasını isteyen kendi eliyle açar.

       ORCID, Scopus ve dış bağlantılar bugüne kadar zaten herkese
       açıktı; onların varsayılanı açık kalır, çünkü bir varsayılanı
       gizliye çevirmek de verilmiş bir sözü bozmaktır, ters yönde.
       ================================================================= */
    function tg_gorunurluk_alanlari(): array {
        return [
            'eposta'      => 'gizli',
            'telefon'     => 'gizli',
            'orcid'       => 'acik',
            'scopus'      => 'acik',
            'baglantilar' => 'acik',
        ];
    }

    function tg_gorunurluk($hesap, string $alan): string {
        $vars = tg_gorunurluk_alanlari();
        if (!isset($vars[$alan])) return 'gizli';
        if (!is_array($hesap)) return $vars[$alan];
        $g = is_array($hesap['gorunurluk'] ?? null) ? $hesap['gorunurluk'] : [];
        $d = trim(tg_metin($g[$alan] ?? ''));
        return in_array($d, ['acik', 'uyeler', 'gizli'], true) ? $d : $vars[$alan];
    }

    /* Bu alan, şu an bakan kişiye gösterilir mi? */
    function tg_alan_gorunur($hesap, string $alan, bool $girisli): bool {
        $d = tg_gorunurluk($hesap, $alan);
        if ($d === 'acik') return true;
        if ($d === 'uyeler') return $girisli;
        return false;
    }

    function tg_gorunurluk_ad(string $d, bool $en = false): string {
        $m = [
            'acik'   => ['Herkese açık', 'Public'],
            'uyeler' => ['Yalnızca sistem kullanıcılarına', 'Registered users only'],
            'gizli'  => ['Gizli', 'Hidden'],
        ];
        return isset($m[$d]) ? tg_t(['tr' => $m[$d][0], 'en' => $m[$d][1]], $en) : $d;
    }

    /* Adresi sayfaya düz metin olarak basmadan taşımanın yolu.
       Toplayıcıların çoğu betik çalıştırmaz; sayfada duran şey ters
       çevrilmiş ve kodlanmış bir dizedir, adres değildir. Bu bir
       şifreleme değildir ve öyle sunulmaz: kararlı bir toplayıcı yine
       çözer. Amaç, adresi otomatik tarayanların eline kolayca
       geçmemesidir. Adresini hiç görünmesin isteyen "gizli" der; o zaman
       sayfaya bu veri de konmaz. */
    function tg_eposta_ort(string $eposta): string {
        $e = trim($eposta);
        if ($e === '') return '';
        return base64_encode(strrev($e));
    }

    /* Kurul kartında gösterilecek resim. Sıra önemlidir: kişinin kendi
       yüklediği resim, ayar dosyasındaki değerin önüne geçer. Bir kişinin
       kendi yüzü hakkındaki kararı, yapılandırma dosyasına yazılmış bir
       değerden önce gelir. */
    function tg_kurul_resmi($b): string {
        if (!is_array($b)) return '';
        /* Hesap düz adresle ya da adres özetiyle bulunur; ayrımı
           hs_kayit_hesabi() yapar ve kural tek yerdedir. Burada
           yalnızca düz adrese bakılsaydı, kurul kayıtlarında adres
           yerine özet duran bir kurulumda kişinin kendi yüklediği
           resim görünmez, ayar dosyasındaki başlangıç değeri
           basılırdı. */
        if (function_exists('hs_kayit_hesabi')) {
            $h = hs_kayit_hesabi($b);
            if (is_array($h)) {
                $r = tg_resim_yolu($h);
                if ($r !== '') return $r;
            }
        }
        return trim(tg_metin($b['resim'] ?? ''));
    }

    /* Kayıt ekranlarında gösterilecek örnekler */
    function tg_dis_profil_ornek(): array {
        return [
            'YÖKSİS', 'ABS profili', 'AVESİS', 'Kurum profili',
            'ORCID', 'Google Scholar', 'ResearchGate', 'Academia.edu',
            'Web of Science', 'Scopus', 'Zenodo', 'OSF', 'HAL', 'Pure', 'CRIS',
        ];
    }

    /* Kişinin sistemde kamusal bir izi var mı? Yoksa sayfası da yoktur. */
    function tg_kisi_var(array $k): bool {
        return $k['yazarlik'] || $k['hakemlik'] || $k['oylar'] || $k['serhler'] || $k['kefillikler'];
    }

    /* Kişi sayfasının adresi */
    /* ---------------------------------------------------------------
       SİSTEM İÇİ ATIFLAR
       ---------------------------------------------------------------
       Bir çalışmanın kaynakçasında bu sistemdeki başka bir çalışmanın
       tamgası, DOI'si ya da kalıcı adresi geçiyorsa bu bir atıftır ve
       görünür olmalıdır. Dışarıdaki dizinler bu bağı yıllar sonra ve
       eksik kurar; oysa kayıt buradadır ve şimdi kurulabilir.

       Eşleşme üç yoldan aranır ve üçü de kesindir:
         1. Tamga             KTG-2026-00001-7
         2. Kalıcı adres      .../tamga/KTG-2026-00001-7
         3. DOI               10.xxxx/yyy  (çalışmanın doi alanı doluysa)
       Başlık benzerliğine bakılmaz: bir atıf ancak kesin kimlikle
       kurulur, yoksa yanlış çalışmaya bağlanma tehlikesi doğar.

       Dönen: atıf yapılan çalışmaların kimlik dizisi. */
    function tg_atif_kodlari(array $y): array {
        $metin = (string)($y['kaynakca'] ?? '') . ' ' . (string)($y['kaynakca_en'] ?? '');
        /* Metnin gövdesinde de geçebilir: "Çetin (2026) ... KTG-..." */
        $metin .= ' ' . (string)($y['metin'] ?? '') . ' ' . (string)($y['metin_en'] ?? '');
        $metin = strip_tags($metin);
        /* Boş metinde de aynı biçim döner: çağıran yer anahtarların
           varlığını denetlemek zorunda kalmasın. */
        if (trim($metin) === '') return ['tamga' => [], 'doi' => []];

        $tamgalar = [];
        if (preg_match_all('/\bKTG-\d{4}-\d{5}-[0-9X]\b/iu', $metin, $m)) {
            foreach ($m[0] as $t) $tamgalar[strtoupper($t)] = true;
        }
        $doiler = [];
        if (preg_match_all('#\b(10\.\d{4,9}/[^\s<)"\']+)#u', $metin, $m2)) {
            foreach ($m2[1] as $d) $doiler[strtolower(rtrim($d, '.,;'))] = true;
        }
        return ['tamga' => array_keys($tamgalar), 'doi' => array_keys($doiler)];
    }

    /* Bir çalışmanın bu sistemde atıf yaptığı çalışmalar.
       $hepsi: arşivin tamamı. Kendine atıf sayılmaz. */
    function tg_atif_verilen(array $y, array $hepsi): array {
        $ara = tg_atif_kodlari($y);
        if (!$ara['tamga'] && !$ara['doi']) return [];
        $benim = strtolower(trim((string)($y['bcid'] ?? '')));
        $out = [];
        foreach ($hepsi as $e) {
            if (!is_array($e)) continue;
            $kod = strtolower(trim((string)($e['bcid'] ?? '')));
            if ($kod === '' || $kod === $benim) continue;
            $eslesti = false;
            foreach ($ara['tamga'] as $t) { if (tg_kod_esles($e, $t)) { $eslesti = true; break; } }
            if (!$eslesti) {
                $doi = strtolower(trim((string)($e['doi'] ?? '')));
                if ($doi !== '' && in_array($doi, $ara['doi'], true)) $eslesti = true;
            }
            if ($eslesti) $out[(string)($e['id'] ?? $kod)] = $e;
        }
        return array_values($out);
    }

    /* Bu çalışmaya bu sistemde atıf yapan çalışmalar.
       Arşiv büyüdüğünde bu tarama pahalılaşır; o gün geldiğinde
       kaydedilirken tutulan bir atıf dosyası bunun yerine geçer.
       Bugünkü ölçekte tarama yeterlidir ve hiçbir şey eskimez. */
    function tg_atif_alan(array $y, array $hepsi): array {
        $kod = trim((string)($y['bcid'] ?? ''));
        $doi = strtolower(trim((string)($y['doi'] ?? '')));
        if ($kod === '' && $doi === '') return [];
        $out = [];
        foreach ($hepsi as $e) {
            if (!is_array($e)) continue;
            if ((string)($e['id'] ?? '') === (string)($y['id'] ?? '')) continue;
            $ara = tg_atif_kodlari($e);
            $var = false;
            foreach ($ara['tamga'] as $t) { if (tg_kod_esles($y, $t)) { $var = true; break; } }
            if (!$var && $doi !== '' && in_array($doi, $ara['doi'], true)) $var = true;
            if ($var) $out[(string)($e['id'] ?? '')] = $e;
        }
        return array_values($out);
    }

    /* Bir hakem kararının okunur adı. Kararlar dört tanedir ve
       sistemin her yerinde aynı sözcüklerle anılır: aynı kararın
       sayfadan sayfaya başka türlü adlandırılması, okuyanı kararın
       farklı olduğunu sanmaya götürür. */
    function tg_karar_ad(string $k, ?bool $en = null): string {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        $t = [
            'kabul' => ['tr' => 'Kabul',            'en' => 'Accept'],
            'kucuk' => ['tr' => 'Küçük düzeltme',   'en' => 'Minor revision'],
            'buyuk' => ['tr' => 'Büyük düzeltme',   'en' => 'Major revision'],
            'ret'   => ['tr' => 'Ret',              'en' => 'Reject'],
        ];
        $k = trim($k);
        return isset($t[$k]) ? tg_t($t[$k], $en) : $k;
    }

    function tg_kisi_yolu(string $ad): string {
        $s = tg_ad_slug($ad);
        return $s === '' ? '' : '/kisi/' . rawurlencode($s);
    }

    /* ---------------------------------------------------------------
       PROFİL RESMİ
       ---------------------------------------------------------------
       Resim dosyaları veri dizininde durur, depoda değil: böylece her
       gönderimde silinmez. Dosya adı içeriğin özetidir; bu yüzden bir
       yıl önbelleğe alınabilir ve resim değiştiğinde adres de değişir.
       --------------------------------------------------------------- */
    function tg_resim_dizini(): string { return tg_veri_dizini() . '/resim'; }

    function tg_resim_yolu($hesap): string {
        if (!is_array($hesap)) return '';
        $r = trim((string)($hesap['resim'] ?? ''));
        if ($r === '' || !preg_match('/^[a-f0-9]{16,64}\.(webp|jpg|png)$/', $r)) return '';
        return '/resim.php?r=' . rawurlencode($r);
    }

    /* Baş harfler: resim yoksa dairede bunlar görünür */
    function tg_bas_harf(string $ad): string {
        $sade = trim(preg_replace('/^((Prof|Doç|Doc|Dr|Öğr|Ogr|Arş|Ars|Uzm|Op)\.?\s*)+/ui', '', trim($ad)));
        $p = preg_split('/\s+/u', $sade, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (!$p) return 'K';
        $b = mb_strtoupper(mb_substr($p[0], 0, 1, 'UTF-8'), 'UTF-8');
        if (count($p) > 1) $b .= mb_strtoupper(mb_substr($p[count($p) - 1], 0, 1, 'UTF-8'), 'UTF-8');
        return $b !== '' ? $b : 'K';
    }

    /* Hesabın izleme listesi: [id => ['t'=>tarih, 'ozet'=>[...]]] */
    function tg_takip_listesi($hesap): array {
        if (!is_array($hesap)) return [];
        $t = $hesap['begeni'] ?? null;
        return is_array($t) ? $t : [];
    }

    function tg_begendi_mi($hesap, string $id): bool {
        return $id !== '' && array_key_exists($id, tg_takip_listesi($hesap));
    }

    /* ---------------------------------------------------------------
       KURULUŞ DÖNEMİ İSTİSNASI
       Sistem kurulurken, kuralların gerçekten işleyip işlemediğini
       görmenin tek yolu onları çalıştırmaktı. İlk çalışmalar bu yüzden
       deneme sırasında yayımlandı ve bugünkü kuralların tamamını
       karşılamaz. Bunları geriye dönük düzeltmek de sessizce silmek de
       kaydı bozar. Doğru olan, istisnayı tanımak ve okuyucuya
       göstermektir: bu çalışmalar arşivde durur, ancak üzerlerinde
       hangi koşulda yayımlandıkları yazılıdır.
       --------------------------------------------------------------- */
    function tg_kurulus_istisnasi(array $y): bool {
        $liste = (array)tg_ayar('kurulus_istisna', []);
        if (!$liste) return false;
        /* Listede eski kod da yazıyor olabilir (bc.000001 gibi). Kimlik
           düzene sokulduğunda not düşmesin diye kod eşleştirmesi eski
           kodları da kapsayan ortak işleve bırakılır. */
        foreach ($liste as $k) {
            if (tg_kod_esles($y, (string)$k)) return true;
        }
        return false;
    }

    /* ---------------------------------------------------------------
       BU HESAP BU ÇALIŞMANIN YAZARI MI?
       Yalnızca ada bakmak yetmez: aynı adı taşıyan bir başkası, yazara
       ait yanıt hakkını kullanabilir. Bu yüzden sırayla bakılır:
         1. ORCID eşleşmesi. Bu sistemde her yazar için ORCID zorunlu
            olduğu için en güvenilir ölçüt budur.
         2. Gönderim sırasında bildirilen e-posta.
         3. Ad eşleşmesi; ancak yalnızca o yazar için kayıtlı bir ORCID
            yoksa. ORCID varken ad eşleşmesi tek başına yetmez.
       --------------------------------------------------------------- */
    function tg_orcid_anahtar(string $o): string {
        $o = preg_replace('/[^0-9Xx]/', '', $o);
        return strtoupper((string)$o);
    }

    function tg_yazar_mi(array $y, array $hesap): bool {
        $hOrcid = tg_orcid_anahtar((string)($hesap['orcid'] ?? ''));
        $hMail  = mb_strtolower(trim((string)($hesap['eposta'] ?? '')), 'UTF-8');
        $hAd    = tg_ad_anahtar((string)($hesap['ad'] ?? ''));

        /* Çalışmadaki bütün yazar kayıtları tek listede toplanır */
        $kayitlar = [];
        $b = $y['yazar_bilgi'] ?? null;
        if (is_array($b)) $kayitlar[] = $b;
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) { if (is_array($ya)) $kayitlar[] = $ya; }
        /* "yazar" alanı çoğu kayıtta yazar_bilgi ile aynı kişidir. Aynı
           kişiyi ORCID'siz ikinci kez eklemek, ORCID denetimini boşa
           çıkarır; bu yüzden yalnızca listede olmayan bir ad ise eklenir. */
        $ilk = trim((string)($y['yazar'] ?? ''));
        if ($ilk !== '') {
            $ilkA = tg_ad_anahtar($ilk); $varMi = false;
            foreach ($kayitlar as $k) { if (tg_ad_anahtar((string)($k['ad'] ?? '')) === $ilkA) { $varMi = true; break; } }
            if (!$varMi) $kayitlar[] = ['ad' => $ilk];
        }

        foreach ($kayitlar as $k) {
            $kOrcid = tg_orcid_anahtar((string)($k['orcid'] ?? ''));
            $kMail  = mb_strtolower(trim((string)($k['eposta'] ?? '')), 'UTF-8');
            $kAd    = tg_ad_anahtar((string)($k['ad'] ?? ''));
            if ($hOrcid !== '' && $kOrcid !== '' && $hOrcid === $kOrcid) return true;
            if ($hMail  !== '' && $kMail  !== '' && $hMail  === $kMail)  return true;
            /* Yalnızca ada dayanan eşleşme en zayıf olanıdır: ad herkesin
               kendi yazdığı bir alandır. Bu yüzden ad eşleşmesi, ancak
               hesabın kimlik doğrulaması onaylıysa kabul edilir. ORCID
               ya da e-posta eşleşmesinde böyle bir koşul aranmaz; onlar
               zaten kişiye bağlı alanlardır. */
            if ($hAd    !== '' && $kAd    !== '' && $hAd    === $kAd && $kOrcid === ''
                && (string)(($hesap['dogrulama']['durum'] ?? '')) === 'onayli') return true;
        }

        /* Yazarın kendi erişim anahtarıyla eşleşme (eski kayıtlar) */
        $eh = (string)(($y['yazar_erisim']['eposta_hash'] ?? ''));
        if ($eh !== '' && $hMail !== '' && hash_equals($eh, hash('sha256', $hMail))) return true;

        return false;
    }

    /* Şerhi yazanın çalışmayla ilişkisi: okuyucu bunu bilmeli */
    function tg_serh_ilgi_ad(string $k, bool $en = false): string {
        $m = [
            'okur'   => ['Okuyucu', 'Reader'],
            'hakem'  => ['Bu çalışmanın hakemi', 'A reviewer of this work'],
            'yazar'  => ['Bu çalışmanın yazarı', 'An author of this work'],
            'editor' => ['Editör', 'Editor'],
        ];
        return isset($m[$k]) ? tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]], $en) : '';
    }

    /* =================================================================
       ZENODO · KALICI KİMLİK (DOI) · TEK KAYNAK
       -----------------------------------------------------------------
       KURUL SORUSU — 20 Ağustos 2026: "Zenodo'dan her eklenen yazı için
       DOI alınabilir mi?" Alınabilir; gerekçesi ve sınırları ayar.php'de
       yazılı. Buradaki işlevler AĞA ÇIKMAZ: üstveri eşlemesi ve kurallar
       burada durur, ağ işi api/index.php'dedir. Ayrımın sebebi
       sınanabilirliktir — bir kapı, Zenodo'ya hiç bağlanmadan gönderilecek
       üstverinin doğruluğunu ölçebilmelidir.
       ================================================================= */
    function tg_zenodo_ayar(): array {
        static $a = null;
        if ($a !== null) return $a;
        $z = (array)tg_ayar('zenodo', []);
        $a = [
            'acik'     => !empty($z['acik']),
            'sandbox'  => !array_key_exists('sandbox', $z) || !empty($z['sandbox']),
            'topluluk' => trim((string)($z['topluluk'] ?? '')),
            'dergi'    => trim((string)($z['dergi'] ?? tg_marka(false))),
            'otomatik' => !empty($z['otomatik']),
            'jeton'    => '',
        ];
        /* Sunucudaki değer depodakinden yenidir ve gizli olanıdır.
           Jeton hiçbir zaman depoya girmez. */
        $c = tg_canli_ayar('zenodo', []);
        if (is_array($c)) {
            foreach (['acik', 'sandbox', 'otomatik'] as $k) if (array_key_exists($k, $c)) $a[$k] = !empty($c[$k]);
            foreach (['topluluk', 'dergi', 'jeton'] as $k) if (isset($c[$k]) && trim((string)$c[$k]) !== '') $a[$k] = trim((string)$c[$k]);
        }
        return $a;
    }
    /* Jetonsuz Zenodo yoktur: ayarda "açık" yazması yetmez. Sistem,
       uygulayamadığı bir kuralı duyurmaz. */
    function tg_zenodo_acik(): bool {
        $a = tg_zenodo_ayar();
        return $a['acik'] && $a['jeton'] !== '';
    }
    function tg_zenodo_taban(): string {
        return tg_zenodo_ayar()['sandbox'] ? 'https://sandbox.zenodo.org/api' : 'https://zenodo.org/api';
    }
    /* Deneme evreninde çıkan kimlik GERÇEK DEĞİLDİR; önek bunu söyler. */
    function tg_zenodo_onek(): string {
        return tg_zenodo_ayar()['sandbox'] ? '10.5072' : '10.5281';
    }
    /* Okura ve yazara duyurulan cümle. KAPALIYKEN BOŞ DÖNER: bu depoda
       en sık görülen kusur, uygulanmayan bir kuralın duyurulmasıdır. */
    function tg_zenodo_cumlesi(bool $en = false): string {
        if (!tg_zenodo_acik()) return '';
        $a = tg_zenodo_ayar();
        if ($a['sandbox']) {
            return tg_t(['tr' => 'Kalıcı kimlik (DOI) düzeni şu anda DENEME evreninde sınanıyor; verilen kimlikler gerçek değildir.',
                         'en' => 'The persistent identifier (DOI) setup is being tested in the SANDBOX; the identifiers it issues are not real.'], $en);
        }
        return tg_t(['tr' => 'Yayımlanan her çalışmaya Zenodo (CERN) üzerinden kalıcı bir kimlik (DOI) verilir; kimlik çalışmanın sayfasında ve künyesinde görünür.',
                     'en' => 'Every published work is given a persistent identifier (DOI) through Zenodo (CERN); the identifier appears on the work\'s page and in its citation.'], $en);
    }

    /* Bu çalışmanın Zenodo kaydı (varsa). */
    function tg_zenodo_kayit(array $y): array {
        $z = is_array($y['zenodo'] ?? null) ? $y['zenodo'] : [];
        return [
            'kayit'  => trim((string)($z['kayit'] ?? '')),
            'taslak' => trim((string)($z['taslak'] ?? '')),
            'doi'    => trim((string)($z['doi'] ?? '')),
            'kavram' => trim((string)($z['kavram'] ?? '')),
            'tarih'  => trim((string)($z['tarih'] ?? '')),
            'kim'    => trim((string)($z['kim'] ?? '')),
            'sandbox'=> !empty($z['sandbox']),
        ];
    }

    /* =================================================================
       ÜSTVERİ EŞLEMESİ · AĞSIZ, SINANABİLİR
       -----------------------------------------------------------------
       Zenodo'ya giden her alan burada üretilir. Ağ çağrısının içine
       gömülseydi, doğruluğu ancak gerçek bir kayıt açarak sınanabilirdi
       — yani geri alınamaz bir işle.
       ================================================================= */
    function tg_zenodo_ustveri(array $y): array {
        $dil    = tg_yazi_dili($y);
        $baslik = trim(strip_tags(tg_dil_alani($y, 'baslik', false)));
        if ($baslik === '') $baslik = trim(strip_tags((string)($y['baslik'] ?? '')));
        $ozet   = trim(strip_tags(tg_dil_alani($y, 'ozet', false)));
        $url    = tg_kok() . tg_yazi_yolu($y);

        $yazarlar = [];
        foreach (tg_yazar_kayitlari($y) as $ya) {
            if (!is_array($ya)) continue;
            $ad = trim((string)($ya['ad'] ?? ''));
            if ($ad === '') continue;
            /* Zenodo "Soyad, Ad" ister; unvan kimliğin parçası değildir. */
            $sade = trim(preg_replace('/\b(Prof|Doç|Doc|Dr|Öğr|Ogr|Arş|Ars|Uzm)\.?\s*/iu', '', $ad));
            $par  = preg_split('/\s+/u', $sade, -1, PREG_SPLIT_NO_EMPTY) ?: [$sade];
            $soy  = count($par) > 1 ? array_pop($par) : '';
            $kisi = ['name' => $soy !== '' ? ($soy . ', ' . implode(' ', $par)) : $sade];
            /* ORCID biçimlendiricisi k/orcid.php'de; o dosya her sayfada
               yüklenmiyor. Yoksa ham değer temizlenip yazılır — kimliği
               atmak, biçimini bilmemekten kötüdür. */
            $orcHam = trim((string)($ya['orcid'] ?? ''));
            $orc = function_exists('tg_orcid_bicim') ? tg_orcid_bicim($orcHam)
                 : (preg_match('/(\d{4}-\d{4}-\d{4}-\d{3}[\dXx])/', $orcHam, $mo) ? $mo[1] : '');
            if ($orc !== '') $kisi['orcid'] = $orc;
            $krm  = trim((string)($ya['kurum'] ?? ''));
            if ($krm !== '') $kisi['affiliation'] = $krm;
            $yazarlar[] = $kisi;
        }
        if (!$yazarlar) $yazarlar[] = ['name' => trim((string)($y['yazar'] ?? ''))];

        $anahtar = array_values(array_filter(array_map('trim',
            preg_split('/[,;]+/u', tg_dil_alani($y, 'anahtar', false)) ?: [])));

        /* BAĞLI KİMLİKLER. Kutadgu sayfası "aynıdır" bağıyla verilir:
           kayıt iki yerde durur, ikisi de aynı çalışmadır. Tamga da
           ayrı bir kimlik olarak yazılır ki kayıt oradan da bulunsun. */
        $bagli = [['identifier' => $url, 'relation' => 'isIdenticalTo', 'resource_type' => 'publication-article']];
        $bcid = trim((string)($y['bcid'] ?? ''));
        if ($bcid !== '') {
            /* Tamga adresi çoğu kayıtta zaten ASIL adrestir (tg_yazi_yolu
               tamgayı kullanır). Aynı adresi iki kez yazmak, Zenodo'da
               aynı bağın iki kopyası demektir. */
            $tam = tg_kok() . '/' . trim((string)tg_ayar('tamga_yol', 'tamga'), '/') . '/' . rawurlencode($bcid);
            if ($tam !== $url) $bagli[] = ['identifier' => $tam, 'relation' => 'isAlternateIdentifier'];
        }
        /* Yazarın verdiği veri/kod arşivi varsa çalışma onu tamamlar. */
        $veri = is_array($y['veri'] ?? null) ? $y['veri'] : [];
        $vdoi = trim((string)($veri['doi'] ?? ($veri['adres'] ?? '')));
        if ($vdoi !== '') $bagli[] = ['identifier' => $vdoi, 'relation' => 'isSupplementedBy'];

        /* NOT ALANI DÜRÜSTLÜK ALANIDIR: kaydın hangi aşamada olduğu
           Zenodo'da da görünsün. Dizinler yalnız üstveriyi okur. */
        $asama = tg_asama_metni(tg_hakem_asamasi($y), false);
        $not = 'Kutadgu · ' . $url;
        if ($asama !== '') $not .= "\n" . 'Değerlendirme: ' . $asama;
        if ($bcid !== '') $not .= "\n" . 'Tamga: ' . $bcid;
        $not .= "\n" . 'Parmak izi (SHA-256, ilk 32): ' . tg_metin_ozeti($y);

        $u = [
            'upload_type'      => 'publication',
            'publication_type' => 'article',
            'title'            => $baslik,
            'creators'         => $yazarlar,
            'description'      => ($ozet !== '' ? '<p>' . htmlspecialchars($ozet, ENT_QUOTES, 'UTF-8') . '</p>' : '')
                                . '<p>' . htmlspecialchars(tg_t(['tr' => 'Bu çalışmanın yayımlandığı yer:', 'en' => 'Published at:'], false), ENT_QUOTES, 'UTF-8')
                                . ' <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '</a></p>',
            'publication_date' => substr((string)($y['tarih'] ?? date('c')), 0, 10),
            'access_right'     => 'open',
            'license'          => 'cc-by-4.0',
            'language'         => tg_dil_iso3($dil !== '' ? $dil : 'tr'),
            'journal_title'    => tg_zenodo_ayar()['dergi'],
            'related_identifiers' => $bagli,
            'notes'            => $not,
        ];
        if ($anahtar) $u['keywords'] = $anahtar;
        $top = tg_zenodo_ayar()['topluluk'];
        if ($top !== '') $u['communities'] = [['identifier' => $top]];
        return ['metadata' => $u];
    }

    /* Kayda konacak dosyalar. Zenodo en az bir dosya ister (kendi SSS'i);
       Kutadgu dosya barındırmadığı için metnin kendisi tek dosyalık bir
       HTML, üstverisi de makine okunur bir JSON olarak konur. */
    function tg_zenodo_dosyalar(array $y): array {
        $slug = trim((string)($y['slug'] ?? ($y['id'] ?? 'calisma')));
        $bas  = trim(strip_tags(tg_dil_alani($y, 'baslik', false)));
        $ozet = trim(strip_tags(tg_dil_alani($y, 'ozet', false)));
        $metin= tg_dil_alani($y, 'metin', false);
        $kayn = tg_dil_alani($y, 'kaynakca', false);
        $url  = tg_kok() . tg_yazi_yolu($y);
        $yzr  = [];
        foreach (tg_yazar_kayitlari($y) as $ya) if (is_array($ya) && trim((string)($ya['ad'] ?? '')) !== '') $yzr[] = trim((string)$ya['ad']);
        $e = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
        $html = "<!doctype html>\n<html lang=\"" . $e(tg_yazi_dili($y) ?: 'tr') . "\">\n<head>\n"
              . "<meta charset=\"utf-8\">\n<title>" . $e($bas) . "</title>\n"
              . "<style>body{font-family:Georgia,'Times New Roman',serif;line-height:1.6;max-width:38em;margin:3em auto;padding:0 1em}"
              . "h1{line-height:1.2}.kim{color:#555;font-size:.9em}blockquote{margin:0 0 2em;color:#333}</style>\n</head>\n<body>\n"
              . "<h1>" . $e($bas) . "</h1>\n<p class=\"kim\">" . $e(implode(' · ', $yzr)) . "</p>\n"
              . "<p class=\"kim\">" . $e($url) . "</p>\n"
              . ($ozet !== '' ? "<blockquote>" . $e($ozet) . "</blockquote>\n" : '')
              . tg_zengin((string)$metin) . "\n"
              . ($kayn !== '' ? "<h2>Kaynakça</h2>\n" . tg_zengin((string)$kayn) . "\n" : '')
              . "</body>\n</html>\n";
        $json = json_encode([
            'kimlik'     => ['tamga' => (string)($y['bcid'] ?? ''), 'adres' => $url, 'slug' => $slug],
            'baslik'     => $bas,
            'yazarlar'   => $yzr,
            'tarih'      => (string)($y['tarih'] ?? ''),
            'dil'        => tg_yazi_dili($y),
            'ozet'       => $ozet,
            'lisans'     => (string)tg_ayar('lisans', 'CC BY 4.0'),
            'asama'      => tg_hakem_asamasi($y),
            'parmak_izi' => tg_metin_ozeti($y),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return [$slug . '.html' => $html, $slug . '.json' => (string)$json];
    }

    /* Veri dizini: yapılandırma > ortam değişkeni > webroot'un üstü */
    function tg_veri_dizini(): string {
        $v = trim((string)tg_ayar('veri_dizini', ''));
        if ($v !== '') return rtrim($v, '/');
        $env = getenv('KUTADGU_DATA');
        if ($env) return rtrim((string)$env, '/');
        return realpath(__DIR__ . '/..') . '/kutadgu-data';
    }
}
