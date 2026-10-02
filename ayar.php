<?php
/* =====================================================================
   TAMGA · Açık Erişimli Akademik Yayın Sistemi
   MERKEZİ YAPILANDIRMA
   ---------------------------------------------------------------------
   Sistemi başka bir alan adına taşımak için YALNIZCA 'kok' değerini
   değiştirmek yeterlidir. Kod içinde hiçbir yerde alan adı sabit
   yazılmaz; bütün bağlantılar buradan üretilir.

   Örnek taşıma:
     'kok' => 'https://kutadgu.net'   (şimdiki)
     'kok' => 'https://bitig.tr'      (ileride, tek satır)

   'kok' boş bırakılırsa sistem, isteğin geldiği alan adını kendisi
   kullanır; yani yanlış yapılandırmada bile çalışmayı sürdürür.
   ===================================================================== */

return [

    /* ---- Kimlik ----
       Kutadgu: Kutadgu Bilig'den; "kut veren, mutluluğa eriştiren bilgi".
       Tamga: Türk boylarının damgası; her esere verilen kalıcı işaret.   */
    'marka'      => 'Kutadgu',
    'marka_alt'  => 'Açık Erişimli Akademik Yayın Sistemi',
    'marka_en'   => 'Kutadgu',
    'marka_alt_en' => 'Open Access Academic Publishing System',

    /* ---- SİSTEMİN KENDİ DOI'Sİ ----
       Sistem 13 Ağustos 2026'da Zenodo'ya yatırıldı ve kalıcı bir kimlik
       aldı. Bu, çalışmalara verilen Tamga'nın yerine geçmez: Tamga bu
       sistemde YAYIMLANAN her çalışmanın işaretidir, aşağıdaki DOI ise
       SİSTEMİN KENDİSİNİN kimliğidir.

       İKİ DOI VARDIR VE KARIŞTIRILMAZ:

         'kavram'  Bütün sürümleri kapsar ve DEĞİŞMEZ. Yeni bir sürüm
                   yatırıldığında bu numara aynı kalır ve her zaman en
                   son sürüme götürür. ANILACAK OLAN BUDUR; bir makale
                   "Kutadgu'yu kullandık" derken sürüme değil sisteme
                   atıf yapar.
         'surum'   Yalnızca 1.0.0'ı gösterir ve o anlık görüntüye
                   sabittir. Bir çalışma "şu sürümle üretildi" demek
                   zorundaysa bu anılır; yeniden üretilebilirliğin
                   gerektirdiği tek yer burasıdır.

       Sürüm numarası ayrı yazılıdır çünkü DOI'den okunamaz: 21919024
       numarasına bakıp "bu 1.0.0'dır" demek mümkün değildir.

       YENİ SÜRÜM YATIRILDIĞINDA 'surum' ve 'surum_ad' DEĞİŞİR, 'kavram'
       DEĞİŞMEZ. Değiştirilirse bugüne kadar yapılmış bütün atıflar
       kırılır; kavram DOI'sinin varlık sebebi tam olarak budur.       */
    /* ---- KAYNAK KODUN ADRESİ ----
       AGPL §13 şunu ister: sisteme ağ üzerinden erişen HERKESE, o anda
       çalışan sürümün kaynağı sunulmalıdır. "Sunulmalı" demek, adresin
       çalışıyor olması demektir; 404 dönen bir adres sunulmuş sayılmaz.

       ÖLÇÜLEN KUSUR (14 Ağustos 2026): sistem kaynak kodunun adresi
       olarak GitHub deposunu duyuruyordu, o depo ise 404 dönüyor —
       henüz açılmadı. Yani sistem, tutamadığı bir sözü veriyordu. Bu
       depoda onbirinci kez aynı kusur sınıfı.

       ÇÖZÜM İKİ ADRESİ AYIRMAK:
         'zenodo'  Kaynağın BUGÜN indirilebildiği yer. Kayıt açık,
                   dosya (4,2 MB zip) herkese açık iniyor. AGPL §13'ü
                   bugün karşılayan adres budur ve her zaman çalışır:
                   Zenodo bir arşivdir, kapanmaz.
         'depo'    Geliştirmenin sürdüğü yer. Açılana kadar
                   'depo_acik' => false durur ve HİÇBİR SAYFADA canlı
                   bağ olarak basılmaz.

       Depo açıldığı gün yapılacak tek şey 'depo_acik' => true. Sayfalar
       da, llms.php de, CITATION.cff de aynı yerden okur.

       NEDEN DEPO SİLİNMEDİ: adres yanlış değil, henüz yok. Silmek,
       açıldığında yeniden yazmayı gerektirirdi; yanlış olan onu ÇALIŞIR
       gibi göstermekti. */
    'kaynak' => [
        'zenodo'     => 'https://doi.org/10.5281/zenodo.21919023',
        /* AÇIK DEPO (3 Ekim 2026). Geliştirme özel depoda sürer ve
           sunucu oradan çeker; o deponun geçmişinde kişisel veriler
           bulunduğu için o depo açılmadı. Herkese açık kaynak, ayrı
           bir depoda GEÇMİŞSİZ anlık görüntü olarak durur ve GONDER.bat
           her gönderimde ÖNCE onu günceller, ancak o başarılı olursa
           siteyi canlıya alır. Yani bu satırın true olduğu bir sürüm,
           açık depo ulaşılabilir olmadan canlıya çıkamaz. */
        'depo'       => 'https://github.com/behiccetin/kutadgu.net',
        'depo_acik'  => true,
    ],

    'doi' => [
        'kavram'   => '10.5281/zenodo.21919023',
        'surum'    => '10.5281/zenodo.21919024',
        'surum_ad' => '1.0.0',
        'tarih'    => '2026-08-13',
    ],

    /* ---- Adres (TAŞIMA İÇİN DEĞİŞTİRİLECEK TEK SATIR) ----
       BOŞ bırakılırsa sistem, isteğin geldiği alan adını kendisi kullanır.
       Böylece sistem hangi alan adında kurulursa kurulsun doğru bağlantılar üretir;
       alan adı geçişi sırasında hiçbir bağlantı kırılmaz.
       kutadgu.net tam olarak yayına girdiğinde buraya
       'https://kutadgu.net' yazılarak tüm bağlantılar oraya sabitlenebilir. */
    'kok'        => 'https://kutadgu.net',
    'kok_hedef'  => 'https://kutadgu.net',   /* geçiş tamamlanınca 'kok' buraya çekilecek */

    /* ---- Bağlı olduğu ana site (isteğe bağlı) ----
       Sistemi bir kurumun ya da derginin kendi sitesi altında kuran biri
       buraya o sitenin adresini yazabilir; alt bilgide bir bağ olarak
       görünür. Boş bırakılırsa hiçbir yerde basılmaz. Kutadgu bağımsız
       bir sistemdir ve hiçbir kişisel siteye bağlı değildir: bu yüzden
       kutadgu.net kurulumunda boştur. */
    'ana_site'    => '',
    'ana_site_ad' => '',

    /* ---- Yönetim iletişim adresi ----
       OAI-PMH Identify yanıtındaki <adminEmail> alanına yazılır ve
       protokol gereği YAYIMLANIR. Yani burada duran adres gizli değildir;
       dizinlerin ve DOAJ'ın sisteme ulaşabilmesi için açık olmak
       zorundadır. Kurucunun adresi bu yüzden depoda durabiliyor;
       kurul üyelerininki duramıyor, çünkü onlar yayımlanmıyor
       (bkz. 'bas_editorler' listesinin üstündeki not).

       Bu sistemi kendi kurumunda kuran biri BU SATIRI KENDİ ADRESİYLE
       DEĞİŞTİRMELİDİR. Boş bırakılırsa Identify yanıtında adres alanı
       hiç basılmaz; teknik olarak eksik bir yanıt olur ve bazı dizinler
       kaydı almaz. */
    'iletisim_eposta' => 'cbehic@gmail.com',

    /* ---- Kalıcı kimlik (Tamga) ----
       Tamga: Türk boylarının damgası; bir eserin kalıcı ve
       devredilemez işareti. DOI değildir, DOI yerine geçmez.
       Biçim:  {kok}/{yol}/{on}.000001      ->  kutadgu.net/tamga/bc.000001
       Eski biçim de çalışmayı sürdürür:     ->  /10.00001/bc.000001            */
    'tamga_ad'   => 'Tamga',
    /* Kimlik biçimi:  KTG-2026-00001-4
         KTG    sistem öneki (alan adı değişse de sabit kalır)
         2026   yayın yılı; dört hane, yüzyıllarca yeter
         00001  o yıl içindeki sıra; her yıl sıfırdan başlar, taşma olmaz
         4      denetim hanesi; yanlış yazılan kimlik anında anlaşılır
       Söylenmesi, yazılması ve arşivlenmesi kolaydır; hiçbir dış kuruma
       bağlı değildir ve sistem el değiştirse bile anlamını korur.        */
    'tamga_on'   => 'KTG',
    'tamga_yol'  => 'tamga',     /* çözümleyici yol parçası */
    'tamga_eski' => '10.00001',  /* en eski çözümleyici (geriye dönük uyumluluk) */
    'tamga_on_eski' => 'bc',     /* ilk dönem kimlikleri: bc.000001 (korunur)   */

    /* Editör kuruluna girme ölçütü: kayıtlardan hesaplanır, davetle değil. */
    'kurul_yayin'  => 20,   /* sistemde en az kaç yayın */
    'kurul_onayli' => 10,   /* bunların kaçı iki hakemden olumlu rapor almış olmalı */

    /* ---- ÇALIŞMA DİLLERİ ----
       Bir çalışmanın yazıldığı dil ile arayüzün dili ayrı şeylerdir.
       Arayüz bugün iki dilde ('diller'); ama bir araştırmacı kendi
       dilinde yazabilmelidir, o dilde arayüz olmasa bile. Bir insan
       kendi ana dilinde daha ince düşünür ve daha doğru anlatır; bunun
       gerekçesi bildirinin "Dil üzerine" bölümünde yazılıdır.

       Liste ISO 639-1 kodlarıyla tutulur ve her dilin KENDİ ADIYLA
       yazılır: bir dilin adını başka bir dilde okumak zorunda kalmak,
       bu sistemin karşı olduğu şeyin küçük bir örneğidir. Sıra Türk
       okuruna tanıdık gelenden başlar, sonra alfabetiktir.

       Yeni bir dil eklemek buraya bir satırdır. Listede olmayan bir
       dille yazan biri iletişim sayfasından bildirir ve dil eklenir;
       kimse dili listede yok diye çalışmasını başka dile çevirmek
       zorunda kalmaz. */

    /* ---- KÜNYE DİLİ (İKİNCİ DİL) ----
       Baş editör kurulunun belirlediği ikinci dildir ve BUGÜN
       İNGİLİZCEDİR. Gönderim formunda iki sekme vardır: birincisi
       çalışmanın kendi dili, ikincisi burada yazan dil.

       NE İSTENİR, NE İSTENMEZ. İstenen künyedir: başlık ve özet.
       İstenmeyen metnin kendisidir. Aradaki fark bu sistemin
       omurgasıdır — "kayıt her zaman yazarın yazdığı dildeki metindir
       ve çeviri onun yerine geçmez". Tam metni ikinci dilde istemek o
       cümleyi sessizce geri almak olurdu.

       ZORUNLU DEĞİLDİR ve zorunlu yapılmadan önce kurul oyu gerekir.
       Bugün istenmesinin tek gerekçesi şudur ve kullanıcıya da böyle
       söylenir: çalışmayı başka bir dilde arayan biri onu ancak bu
       künyeyle bulur. Bir gün kurul zorunlu kılarsa değişecek olan
       aşağıdaki 'zorunlu' satırıdır; sayfa ve uç onu tek yerden okur
       (tg_kunye_dili / tg_kunye_zorunlu).

       Boş bırakılırsa ikinci sekme hiç çizilmez; çalışmanın dili zaten
       künye diliyse de çizilmez, çünkü o durumda çevrilecek bir şey
       yoktur. */
    /* ---- GENİŞLETİLMİŞ ÖZET ----
       KURUL KARARI, 14 Ağustos 2026: "Kişi Arapça makalesini eklerse
       İngilizce genişletilmiş özetini versin. Şu an için en azından ana
       dilin yanında İngilizcenin de olması lazım."

       NEDEN KISA ÖZET YETMİYOR. Bu sistemin sözü şudur: kayıt yazarın
       kendi dilindeki metindir ve çeviri onun yerine geçmez. O söz
       tutuluyor — tam metin ikinci dilde İSTENMİYOR. Ama iki yüz
       kelimelik bir künye özeti, Arapça yazılmış bir çalışmanın ne
       yaptığını başka dilden bir okura anlatmaz; anlatmadığı için de o
       çalışma fiilen görünmez kalır. Açık erişim, görünmeyen bir
       çalışmada bir anlam taşımaz.

       ARADAKİ YOL GENİŞLETİLMİŞ ÖZETTİR: çalışmanın sorusunu,
       yöntemini, bulgusunu ve sonucunu taşıyan, tam metin olmayan bir
       metin. Türkiye'deki dergilerde yerleşik karşılığı budur ve
       uzunluğu da oradan alındı (750–1000 kelime; alt sınır 500).

       DİL ZATEN KÜNYE DİLİYSE İSTENMEZ. Çalışması İngilizce olan
       birinden İngilizce genişletilmiş özet istemek, aynı metni iki kez
       istemektir.

       ÇEVİRİYİ KİMİN YAPTIĞI AYRI BİR SORUDUR ve zaten sorulur
       (tg_ceviri_kaynaklari: yazar | insan | makine). Yapay zekâ ile
       çevirmek yasak değildir; beyan edilmemesi kabul edilemez. Sistem
       istem önerileri de verir — ama istem, sorumluluğu devretmez. */
    /* KISA KÜNYE ARTIK ZORUNLU. Kurul kararı, 15 Ağustos 2026:
       "İngilizce başlık ana dilin yanında zorunlu olmalı, özet de; ama
       genişletilmiş özet de olsun."

       NEDEN ZORUNLU OLDU. Künye isteğe bağlıyken çalışmaların bir kısmı
       yalnız yazıldığı dilde künyeye sahip oluyordu; o çalışmalar
       dizinlerde, arama motorlarında ve başka dilden okurun atıf
       listesinde YOK sayılıyordu. Açık erişim, bulunamayan bir
       çalışmada bir anlam taşımaz. İstenen şey hâlâ TAM METİN DEĞİL,
       yalnız başlık ve özettir; sistemin "kayıt yazarın kendi dilindeki
       metindir" sözü olduğu gibi duruyor.

       GENİŞLETİLMİŞ ÖZET AYRI VE ÜSTÜNE. Kısa künye "bu çalışma ne
       hakkında" der; genişletilmiş özet "ne yaptı, nasıl yaptı, ne
       buldu" der. Biri ötekinin yerine geçmez, bu yüzden ikisi de açık.
       Çalışmanın dili zaten künye diliyse ikisi de istenmez — aynı
       metni iki kez istemek olurdu. */
    /* TAM METNİN EN AZ KELİME SAYISI.
       Gönderim artık tam metni de alıyor (kurul kararı, 15 Ağustos
       2026). Eşik bir KAPI değil, bir YANLIŞLIK ÖLÇÜSÜdür: sekiz yüz
       kelimenin altındaki bir gönderim neredeyse her zaman yanlışlıkla
       yalnız özetin yapıştırılmasıdır. Sayı tek yerde durur; sayfa da
       uç da buradan okur, yoksa tarayıcı geçirir sunucu reddeder ve
       kişi nedenini hiçbir yerde göremez. */
    'metin_en_az_kelime' => 800,

    /* ETİK BEYANININ YÜRÜRLÜK TARİHİ.
       Kurul kararı 15 Ağustos 2026'da etik kurul iznini BEYAN olarak
       istemeye başladı. Bu tarihten önce yayımlanmış çalışmalarda
       'etik' alanı yoktur ve olmaması bir eksiklik değildir: o beyan
       yazarlarından hiç istenmedi.

       KURAL GERİYE YÜRÜTÜLEMEZ, AMA BAŞLANGICI YAZILABİLİR. Tarih
       burada tek satırdır; tg_etik_hal() bunu okuyup "sorulmadı" ile
       "bildirilmedi"yi ayırır. Tarih olmadan ikisi ayrılamaz ve sistem
       ya susmak (okur hiçbir şey bilmez) ya da suçlamak (yazar,
       kendisinden istenmemiş bir şeyle suçlanır) zorunda kalır.
       İkisi de yanlıştır. */
    'etik_beyan_baslangic' => '2026-08-15',

    'kunye_dili' => [
        'kod'     => 'en',
        'zorunlu' => true,    /* kısa künye (başlık + özet) */
        'genis_ozet' => [
            'zorunlu'      => true,
            'en_az_kelime' => 500,
            'hedef_kelime' => 750,
        ],
    ],
    'calisma_dilleri' => [
        'tr' => 'Türkçe',            'en' => 'English',
        'ar' => 'العربية',            'az' => 'Azərbaycanca',
        'bg' => 'Български',          'bn' => 'বাংলা',
        'cs' => 'Čeština',           'da' => 'Dansk',
        'de' => 'Deutsch',           'el' => 'Ελληνικά',
        'es' => 'Español',           'fa' => 'فارسی',
        'fi' => 'Suomi',             'fr' => 'Français',
        'he' => 'עברית',              'hi' => 'हिन्दी',
        'hu' => 'Magyar',            'id' => 'Bahasa Indonesia',
        'it' => 'Italiano',          'ja' => '日本語',
        'ka' => 'ქართული',            'kk' => 'Қазақша',
        'ko' => '한국어',              'ky' => 'Кыргызча',
        'ms' => 'Bahasa Melayu',     'nl' => 'Nederlands',
        'no' => 'Norsk',             'pl' => 'Polski',
        'pt' => 'Português',         'ro' => 'Română',
        'ru' => 'Русский',            'sq' => 'Shqip',
        'sr' => 'Српски',             'sv' => 'Svenska',
        'sw' => 'Kiswahili',         'th' => 'ไทย',
        'tk' => 'Türkmençe',         'uk' => 'Українська',
        'ur' => 'اردو',               'uz' => 'Oʻzbekcha',
        'vi' => 'Tiếng Việt',        'zh' => '中文',
    ],

    /* ---- KURULUŞ DÖNEMİ ----
       Sistem yeni açıldı ve arşivi boş. Bu dönemde bazı kurallar
       gevşetilir; ama gevşetmenin unutulup kalıcı olmaması için hepsi
       burada, tek blokta ve bir bitiş tarihiyle durur. Tarih geçtiğinde
       kendiliğinden kapanırlar, kimsenin bir şey yapması gerekmez.

       yazarlik_hakemlik_sarti:
         Sistemin merdiveninde yazarlık, hakemlikten sonra gelir: bir
         metni değerlendirmiş olan kişi, kendi metninin nasıl
         değerlendirileceğini de bilir. Doğru bir düşüncedir ve
         kalacaktır. Ama arşiv boşken uygulanamaz: değerlendirilecek
         çalışma yoksa kimse hakemlik yapamaz, dolayısıyla kimse yazar
         olamaz ve sisteme ilk çalışma hiç girmez. Kuruluş döneminde bu
         koşul aranmaz; yazarlık doktora ya da iki destekleyen şartına bağlı
         kalır, o şart hiçbir zaman gevşemez.

         Not: bu koşul bugüne kadar kodda zaten aranmıyordu, yalnızca
         sayfalarda yazılıydı. Yani sistem uygulamadığı bir kuralı
         duyuruyor ve insanları boşuna caydırıyordu. */
    'kurulus_donemi' => [
        'bitis' => '2027-12-31',
        'yazarlik_hakemlik_sarti' => false,
    ],

    /* ---- GÖREVDEKİ BAŞ EDİTÖRLÜĞÜN DEVAMI ----
       ÖNCE ŞUNU AYIRMAK GEREKİR. Kurucu baş editörlerin GÖREVİ
       süresizdir; 2027 sonunda kapanan şey görev değil, iki dar
       yetkidir: editör listesine ekleme ve çıkarma
       (aşağıdaki 'editor_yetki_bitis') ve yeni baş editör atama
       ('bas_editor_atama'). Kişi kurul sayfasındaki yerinde durur,
       hakem atamayı ve editöryal not düşmeyi sürdürür. Görev ancak
       kişinin kendi devriyle, vefatıyla, kurucuların çoğunluk kararıyla
       ya da atamada bilerek yazılmış bir bitiş tarihiyle son bulur.

       O iki yetki kapandıktan sonra kimin atayacağı burada yazılıdır
       ve sırası şudur:

         1. Sistem bir kuruma devredilmişse, o kurum baş editörleri
            atar ve kararı her şeyin üstündedir. Bildiride verilmiş söz
            budur ve değişmez.
         2. Kurum yoksa görev DAVETLE DEĞİL KAYITLA kazanılır. Ölçüt
            aşağıdadır ve herkes için aynıdır; sayım kurul sayfasında
            anlık yapılır, isteyen doğrulayabilir.
         3. Ölçütü kimse karşılamıyorsa sistem yönetimsiz kalmaz:
            görevdeki kurucu baş editörlerin yetkisi aynı şekilde
            sürer. Kod bunu metne bırakmaz, kendisi uygular: devralan
            yokken ya da kurulda boş koltuk varken atama yetkisi
            kapanmaz.

       NEDEN OYLAMA DEĞİL. Bu sistemde oylama bir yargı aracıdır, bir
       siyaset aracı değil: kurul oylaması tek bir çalışmaya ilişkin
       itirazı karara bağlar. Bir göreve seçim yapmak kampanyayı,
       öbekleşmeyi ve kayırmayı getirir; kapalı hakemliğe yöneltilen
       eleştirinin aynısı bu kez yönetime düşer. Kurul üyeliği de
       oylamayla değil sayımla kazanılıyor; baş editörlük de öyle.

       NEDEN YALNIZCA MAKALE SAYISI DEĞİL. Bu görev yazmak değil,
       yürütmektir. Ölçüt bu yüzden yapılmış editöryal işi sayar. Yayın
       sayısını kurul üyeliği zaten ölçer; ikisi birlikte istenir. */
    'bas_editor_olcut' => [
        'baslangic' => '2028-01-01',  /* ölçüt bu tarihten sonra işler */
        'atama'     => 40,            /* raporu tamamlanmış hakem ataması sayısı */
        'kurul'     => true,          /* ayrıca yayın kurulu ölçütü de karşılanmalı */
    ],

    /* ---- GÖREV SÜRESİ VE ETKİNLİK ÖLÇÜTÜ ----
       KURUL KARARI, 14 Ağustos 2026 (F1). Kurulun sorduğu soru şuydu:
       "atanan baş editöre yazılı süre zorunlu mu?" Verilen yanıt bir
       süre değil, bir ÖLÇÜT getirdi:

         "Baş editörler hakem atama, sisteme giriş gibi işlemleri sık
          tekrarlayıp sistemin çalışmasına katkı vermiyorsa en fazla
          1 yıl baş editör olur; akabinde onursal listede yer alır.
          Baş editörler sistemde yazar ve hakem olarak çalışabilirler.
          Tekrar baş editör olmak için, baş editörlüğü bittikten sonra
          baş editörlük kurallarını yerine getirirse tekrar otomatik
          baş editör olur."

       NEDEN SÜRE DEĞİL ÖLÇÜT. Yazılı bir süre, çalışanı da çalışmayanı
       da aynı gün görevden alır; işini yapan birini takvim yüzünden
       kaybetmek kurula bir şey kazandırmaz. Ölçüt ise yalnızca duran
       koltuğu boşaltır. Bu yüzden görev BİR YILLIK DÖNEMLERE bölündü:
       dönem sonunda ölçüt karşılanmışsa görev kendiliğinden sürer,
       karşılanmamışsa o gün biter ve kişi onursal listeye geçer.
       Kimse kimseyi görevden almaz; sayım yapar.

       NEDEN SAYIM ADİL OLMAK ZORUNDA. Bir baş editör, sisteme hiç
       çalışma gelmediği bir yılda hakem atayamaz. Bu yüzden hedef
       SABİT DEĞİL, TAVANDIR: dönem boyunca atama bekleyen çalışma
       sayısı hedeften azsa hedef o sayıya iner. Yapılacak iş yokken
       "iş yapmadın" demek, ölçmek değil cezalandırmaktır.

       NEDEN GİRİŞ TEK BAŞINA YETMEZ. Kurul "sisteme giriş" dedi ve
       haklı: görünmeyen bir baş editör yoktur. Ama girmek bir katkı
       değil, katkının önkoşuludur; yalnız girişi saymak, hiçbir şey
       yapmadan ayda bir açılan bir oturumu görev sayardı. Sayılan şey
       EDİTÖRYAL İŞLEM GÜNÜDÜR: hakem atanan, editöryal not düşülen,
       karar verilen ayrı günler. İki ölçütten BİRİ yeterlidir; ikisini
       birden istemek, az ama düzenli çalışanı da yoğun ama aralıklı
       çalışanı da eler.

       KURUCULAR MUAFTIR. Kurucu baş editörlük bir görev değil bir
       kayıttır ve bu depoda beş yerde yazılıdır: sonradan verilemez,
       geri alınamaz, ölçüme bağlanamaz. Kurucunun görevi yalnızca
       kendi devri, vefatı, kurucuların çoğunluk kararı ya da yazılı
       bir bitiş tarihiyle biter.

       GERİ DÖNÜŞ AÇIKTIR. Görevi biten kişi sistemde yazar ve hakem
       olarak çalışmayı sürdürür; 'bas_editor_olcut' ölçütünü karşıladığı
       gün yeniden ve kendiliğinden göreve gelir. Bu bir bağışlama
       değil, aynı ölçütün aynı biçimde işlemesidir. */
    'bas_editor_gorev' => [
        'sure_ay'    => 12,   /* bir dönem; dönem sonunda ölçüm yapılır */
        'atama'      => 12,   /* dönemde yapılan hakem ataması (ayda bir) */
        'islem_gunu' => 24,   /* ya da: editöryal işlem yapılan ayrı gün */
        'kurucu'     => false,/* kurucular ölçüme bağlanmaz */
    ],

    /* ---- Baş editörler ----
       Burada iki ayrı şey vardır ve karıştırılmamalıdır:

         KURUCU BAŞ EDİTÖRLÜK bir görev değil, bir kayıttır. Sistemi kimin
         kurduğu, olmuş bir şeydir; sonradan düzeltilemez. Bu sistemin
         tamamı "yayımlanmış bir kayıt geriye dönük değiştirilmez" ilkesi
         üzerine kuruludur; kuruluş kaydı da o ilkenin dışında değildir.
         'kurucu' => true olan satırlar bu kaydı taşır ve silinmez.

         GÖREVDEKİ BAŞ EDİTÖRLÜK ise bir yetkidir: hakem atamak, editör
         eklemek, editöryal not düşmek. Bu yetki devredilebilir ve
         geri alınabilir. Sistem bir kuruma devredilirse, o kurum
         görevdeki baş editörleri değiştirebilir.

       Yani bir kurum yarın bu sistemin sorumluluğunu üstlenirse
       görevdekileri değiştirmekte serbesttir; değiştiremeyeceği tek şey,
       sistemin kimin elinden çıktığı bilgisidir. Bu bir ayrıcalık talebi
       değil, kaydın bütünlüğüdür.

       Buradaki unvanlar yalnızca BAŞLANGIÇ değerleridir. Her baş editör
       hesabına girdiğinde unvanını kendi panelinden değiştirebilir;
       değiştirdiği andan sonra sayfalarda kendi yazdığı unvan görünür. */
    'bas_editorler' => [
        /* Kurum bilgisi burada başlangıç değeridir; kişi hesabına girip
           kendi kurumunu yazdığında sayfada onunki görünür. Kurumu
           yazılı olmayan bir kart, kartın alt yarısını boş bırakıyordu
           ve kim olduğu okunamıyordu. E-posta yalnızca giriş ve davet
           içindir; hiçbir sayfada gösterilmez. */
        /* 'editor_yetki_bitis' (GG değil, YYYY-AA-GG):
           Editör listesine ekleme ve çıkarma yetkisinin son günüdür ve
           YALNIZCA o yetkiyi kapatır. Kurucu baş editörlük bir kayıttır,
           düşmez: süre dolduğunda kişi kurul sayfasında yerinde durur,
           hakem atamayı ve editöryal not düşmeyi sürdürür, yalnızca
           editör listesini değiştiremez. Bildirideki "yetki
           devredilebilir, kayıt geriye dönük düzeltilmez" ayrımı budur.
           Alan yazılmazsa yetki süresizdir.

           Bu alan GÖREVİ BİTİRMEZ. Bir zamanlar kod bu alanı görevin
           bitiş tarihi gibi okuyordu; sonuç, burada yazılanın ve
           hocalara giden karar belgesinde verilen sözün tersiydi. Kişi
           1 Ocak 2028'de baş editörlüğü büsbütün yitirecekti. Kod artık
           iki alanı ayrı okuyor. Görevi bitiren alanlar 'devir',
           'vefat' ve 'gorev_sonu'dur; üçü de aşağıdaki görevdekiler
           listesinde anlatılıyor ve kurucu kayıtlarında da kullanılır. */

        /* ---- KİMLİK ADRESLE DEĞİL ADRESİN ÖZETİYLE TANINIR ----
           Kayıtlarda düz e-posta adresi YOKTUR; duran şey adresin
           sha256 özetidir ('eposta_ozet'). 'eposta' alanı boş durur ve
           bilerek kalmıştır; aşağıda anlatılıyor.

           NEDEN ÖZET: bu dosya depoya girer, veri dizini girmez. Adres
           zaten hiçbir sayfada gösterilmiyordu (yukarıdaki nota bakın);
           yalnızca giriş ve davet içindi. Yani sitenin bilerek sakladığı
           bir veri, deponun açılmasıyla herkese açılacaktı. Üstelik bu
           adreslerin çoğu bu dosyayı yazanın değil, başkalarınındır;
           başkasının kişisel verisini kendi kararıyla yayımlamak
           yapılacak bir şey değildir.

           NEDEN YALNIZCA VERİ DİZİNİNDEKİ DOSYA YETMEDİ: adresler bir
           süre yalnızca veri dizinindeki 'kurul-eposta.json' dosyasında
           tutuldu ve buraya yükleme anında bindirildi. Bu, dosyanın
           sunucuda bulunmadığı her kurulumda kurucuların baş editör
           yetkisini yitirmesi demekti; sistemin sahibi o dosyayı
           sunucuya kendi koyamıyor. Bir yetkinin, elle taşınması
           gereken bir yardımcı dosyaya bağlı olması bir tuzaktır.
           Özet, hiçbir ek dosya olmadan çalışır.

           ÖZET NEYİ KORUR, NEYİ KORUMAZ. Dürüst olmak gerekir:
             KORUR   Adresin depodan OKUNMASINI ve bir liste hâlinde
                     TOPLANMASINI. Depoyu klonlayan kimse buradan beş
                     adres çıkaramaz; özetten adrese dönüş yoktur.
             KORUMAZ Zaten bilinen ya da tahmin edilmiş bir adresin
                     DOĞRULANMASINI. Adresi tahmin eden biri özetini
                     alıp buradakiyle karşılaştırabilir ve "evet, bu
                     kişi kuruldadır" sonucuna varabilir. E-posta
                     adreslerinin uzayı küçüktür; sha256 bunu
                     değiştirmez.
           Yani özet, düz adresin depoda durmasından çok daha iyidir
           ama sihir değildir. Buradaki asıl kazanç, açılan bir depodan
           kişisel veri toplanamamasıdır.

           Özet nasıl alınır: adres önce ortak.php'deki
           tg_eposta_anahtar() ile normalleştirilir (kırpılır ve küçük
           harfe çevrilir), sonra sha256 alınır. Tek kaynak
           tg_eposta_ozet()'tir; kimlik karşılaştırmasını yapan yer ise
           tg_kurucu_eslesir()'dir ve kural şudur: düz adres varsa
           onunla, yoksa özetle.

           'eposta' ALANI NEDEN DURUYOR: veri dizinindeki
           'kurul-eposta.json' bindirmesi kaldırılmadı, yalnızca
           ZORUNLU olmaktan çıktı. Dosya varsa düz adresler bu alanlara
           bindirilir ve eşleşme yine önce onlarla yapılır. Gerek şudur:
           özetten adrese dönülemez, yani ileride sisteme kurucuya
           POSTA GÖNDEREN bir özellik eklenirse (davet, bildirim) düz
           adres bir yerden gelmek zorundadır ve o yer bu dosya değil,
           veri dizinidir. Bugün hiçbir şey ona bağlı değildir.

           Bir kayıt buraya adresiyle yazılırsa bindirme onu EZMEZ:
           yazılı olan kalır. Böylece yerel bir kurulumda dosya
           kullanmadan, adresi elle yazarak çalışmak da sürebilir.

           Dosyanın biçimi (anahtar ad, değer adres):
             { "Behiç Çetin": "...", "Murat Kayalar": "..." }
           Ad karşılaştırması tg_ad_anahtar() ile yapılır: unvan ve
           Türkçe harf ayrımı gözetilmez.                              */
        ['ad' => 'Behiç Çetin',          'unvan' => 'Dr.',       'kurum' => 'Burdur Mehmet Akif Ersoy Üniversitesi', 'eposta' => '', 'eposta_ozet' => 'ca016af4b730538fb996466cfb59b6d3237bccd05fe7f32808fa1aca22b22b42', 'kurucu' => true],
        ['ad' => 'Murat Kayalar',        'unvan' => 'Prof. Dr.', 'kurum' => 'Burdur Mehmet Akif Ersoy Üniversitesi', 'eposta' => '', 'eposta_ozet' => '7d9e1eb1e390d2ba785faa2d67df28a20781218c81049b9f2b3313797e879d52', 'kurucu' => true, 'editor_yetki_bitis' => '2027-12-31'],
        ['ad' => 'Gökhan Kalağan',       'unvan' => 'Doç. Dr.',  'kurum' => 'Balıkesir Üniversitesi', 'eposta' => '', 'eposta_ozet' => '9b42c5dd6901864a8c543ed530d4873078fa08fd7b96d78b28ced22782eff54c', 'kurucu' => true, 'editor_yetki_bitis' => '2027-12-31'],
        ['ad' => 'Mustafa Zihni Tunca',  'unvan' => 'Prof. Dr.', 'kurum' => 'Süleyman Demirel Üniversitesi', 'eposta' => '', 'eposta_ozet' => '5bce90c2000c9f93b0942cffc91fffa4e60b592ce42544e772993217ff113842', 'kurucu' => true, 'editor_yetki_bitis' => '2027-12-31'],
        ['ad' => 'İbrahim Atilla Acar',  'unvan' => 'Prof. Dr.', 'kurum' => 'İzmir Kâtip Çelebi Üniversitesi', 'eposta' => '', 'eposta_ozet' => 'f6f2f9d2f8ef241c6776b612be10071efa282e6b5a09f06b579174fb1be8fa54', 'kurucu' => true, 'editor_yetki_bitis' => '2027-12-31'],
    ],

    /* ---- GÖREVDEKİ BAŞ EDİTÖRLER ----
       Yukarıdaki 'bas_editorler' listesi KURULUŞ KAYDIDIR ve kapalıdır.
       Bu liste ise sonradan atananları tutar. İkisi ayrı listedir ve
       birbirine karışmaz: kod kurucu sıfatını yalnızca yukarıdaki
       listeden okur, buradaki bir kayda 'kurucu' yazılsa bile düşürür.
       Gerekçesi tek cümlede: kurucu baş editörlük bir yetki değil bir
       kayıttır; sistemin kimin elinden çıktığı olmuş bir şeydir ve
       sonradan yazılamaz. Aynı ilke kuruculara da uygulanır, onların
       kaydı da geriye dönük düzeltilemez.

       GÖREV TAKVİMLE BİTMEZ, ÖLÇÜMLE BİTER. Bir tarihin gelmesi
       kimsenin yetkisini sessizce almaz. Dört yol yazılı bir kayıttır:
       kendi devri, vefat, kurucuların çoğunluk kararı, ve atamada
       bilerek yazılmış bir bitiş tarihi. Beşinci yol bir sayımdır ve
       yalnız ATANMIŞ baş editörlere işler: görev bir yıllık dönemlere
       bölünür, dönem sonunda 'bas_editor_gorev' ölçütü karşılanmamışsa
       o gün biter (kurul kararı F1, 14 Ağustos 2026). Kuruculara
       işlemez.

       ATAMA_TARİH BU YÜZDEN ZORUNLUDUR. Yazılmamışsa saat hiç
       başlamaz; alan boş bırakılarak ölçüt sessizce kapatılabilirdi.
       Boş ya da bozuk yazılmış bir atama tarihi artık kurul sayfasında
       hata olarak görünür.

       Görevi biten kişi onursal baş editör olarak anılır: kurul
       sayfasının üçüncü kümesinde durmayı sürdürür, ama hiçbir yetkisi
       kalmaz. Onursal sıfatı kimse veremez; yalnızca görevin bitmesiyle
       kalır. Boşalan koltuğu görevdeki baş editörler doldurur.

       Kayıt alanları:
         ad         Zorunlu.
         unvan      Başlangıç değeri; kişi hesabından değiştirebilir.
         kurum      Başlangıç değeri; kişi hesabından değiştirebilir.
         eposta     Yalnızca giriş ve davet içindir, hiçbir sayfada
                    gösterilmez. Bu depo herkese açıktır; gerçek adresi
                    buraya yazmak yerine boş bırakıp aşağıdaki
                    'eposta_ozet' alanını doldurmak yeğlenir. Düz adres
                    gerekiyorsa yeri veri dizinindeki
                    'kurul-eposta.json' dosyasıdır; bindirme bu listeye
                    de uygulanır ve yazılı bir adresi ezmez, aşağıdaki
                    örnek bu yüzden hâlâ adresli yazılabiliyor.
         eposta_ozet  Adresin sha256 özeti. Kimlik denetimi düz adres
                      yoksa bununla yapılır; neyi koruyup neyi
                      korumadığı 'bas_editorler' listesinin üstünde
                      yazılıdır. Değeri üretmek için adres önce
                      kırpılır ve küçük harfe çevrilir
                      (tg_eposta_ozet() bunu tek yerde yapar).
         gorev_bitis  İSTEĞE BAĞLI. YYYY-AA-GG. Atamaya bilerek bir süre
                      konmuşsa görevin son günü. Yazılmazsa görev sürer.
                      Yazılıp da biçimi bozuksa kayıt işlemez: yanlış
                      yazılmış bir tarih, yazılmamış bir tarihten
                      tehlikelidir, çünkü görevin ne zaman bittiği
                      okunamaz.
         devir        ['tarih' => 'YYYY-AA-GG', 'devralan' => 'Ad']
                      Kişinin kendi kararıyla görevi bırakması.
         vefat        ['tarih' => 'YYYY-AA-GG']  Bir olgudur, oy istemez.
         gorev_sonu   ['tarih' => 'YYYY-AA-GG',
                       'karar_veren' => ['Ad', 'Ad', 'Ad']]
                      Kurucuların çoğunluk kararı. En az üç kurucu adı
                      yoksa karar yok sayılır, kişi görevde kalır ve
                      eksiklik kurul sayfasında hata olarak görünür.
         atama_tarih  Atamanın yapıldığı gün (kayıt için).
         atayan       Atamaya evet oyu veren GÖREVDEKİ baş editörlerin
                      adları. Sayım tg_atama_gecerli() işlevindedir.

       Örnek (atama yapıldığında bu biçimde yazılır):
         ['ad' => 'Ad SOYAD', 'unvan' => 'Prof. Dr.',
          'kurum' => 'Kurum', 'eposta' => 'ornek@ornek.org',
          'atama_tarih' => '2026-10-01',
          'atayan' => ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']],

       Örnek (kişi görevi bıraktığında aynı kayda eklenir):
          'devir' => ['tarih' => '2029-06-30', 'devralan' => 'Ad SOYAD'],

       Uyruk, ülke ya da kurum şartı YOKTUR ve buraya böyle bir alan
       yazılamaz; yazılırsa kod düşürür. Bilimde bir milletin ötekine
       üstünlüğü yoktur, bir sistemin kurulunu tek bir ülkeyle
       sınırlamak o cümleyi geçersiz kılar. */
    'gorevdeki_bas_editorler' => [
    ],

    /* ---- YENİ BAŞ EDİTÖR ATAMA DÜZENİ ----
       Atama yetkisi GÖREVDEKİ baş editörlere aittir. Başlangıçta bu
       küme beş kurucudur; biri görevi bıraktığında ya da vefat
       ettiğinde kalanlar oy kullanır, sonradan göreve gelen bir baş
       editör de bu kümededir. Oylar eşittir, hiçbirinin oyu ağır
       basmaz. Bir atama için 'oy_gerekli' kadar oy aranır; kurul bu
       sayıdan küçükse salt çoğunluğa iner, yoksa kurul kendini
       yenileyemez hâle gelirdi.

       Yetki 'yetki_bitis' gününün sonunda kapanır. Bu bir kuruluş
       dönemi istisnasıdır, kalıcı bir düzen değil: süresiz bir atama
       yetkisi zamanla kampanyayı, öbekleşmeyi ve kayırmayı getirir.
       Yetki kapandıktan sonra baş editörlük atamayla değil sayımla
       kazanılır; ölçüt yukarıdaki 'bas_editor_olcut' bloğundadır.

       DÖRT ZORUNLU HÂL. Tarih geçtikten sonra yetki yalnızca aşağıdaki
       dört hâlden biri varsa kendiliğinden açılır ve hâl ortadan
       kalktığı gün yeniden düşer:
         1. kurul-bos      Görevde hiçbir baş editör yok.
         2. devralan-yok   Ne kuruma devir var ne kurucu olmayan
                           görevdeki bir baş editör. Proje devir sayılmaz.
         3. koltuk-bos     Görevdeki sayısı 'bas_editor_koltuk'un altında.
         4. sayimla-yok    Ölçütü karşılayan kimse yok (yalnızca bir
                           koltuk boşken anlamlıdır).
       Liste KAPALIDIR ve koddadır (ortak.php, tg_zorunlu_haller()).
       Buraya bir satır yazarak yeni bir zorunlu hâl eklenemez; eklemek
       bir kod değişikliğidir ve kurul sayfasında görünür.

       Açılan yetki kurulu koltuk sayısının üstüne ÇIKARAMAZ; boşluğu
       doldurur, kurul büyütmez. Kurulu büyütmenin tek yolu aşağıdaki
       'bas_editor_koltuk' satırını değiştirmektir.

       ÇIKARMA BU YETKİNİN İÇİNDE DEĞİLDİR. Bir baş editörün görevini
       sonlandırmak yalnızca kişinin kendi devriyle, vefatıyla, yazılı
       çoğunluk kararıyla ya da atamaya konmuş bitiş tarihiyle olur.
       Çoğunluk kararı yolu tarihe bağlı değildir ve her zaman açıktır;
       bir kurulun kendini denetleyebilmesi buna bağlıdır. */
    'bas_editor_atama' => [
        'yetki_bitis' => '2027-12-31',
        'oy_gerekli'  => 3,
    ],

    /* ---- KURULUN KOLTUK SAYISI ----
       Yönetici baş editör sayısı. Bugün beştir ve beş kurucudan
       oluşur. Bir koltuk boşaldığında (devir, vefat, kurucuların
       kararı ya da yazılı sürenin dolması) görevdeki baş editörler
       yerine birini atar. Sayı burada yazılıdır çünkü bir kurulun kaç
       kişi olduğu, o kurulun kendi bileceği bir şey değildir. */
    'bas_editor_koltuk' => 5,

    /* ---- DEVİR ----
       Sistem bir derneğe, vakfa ya da kuruma devredildiğinde burası
       yazılır. Boş olduğu sürece devir olmamıştır ve kurucuların
       yetkisi sürer.

         tarih     YYYY-AA-GG. Devrin yürürlüğe girdiği gün.
         devralan  Devralan tüzel kişinin adı.
         tur       'dernek' | 'vakif' | 'kurum' | 'proje'

       PROJE DEVİR SAYILMAZ. Sistemin bir projeye dönüşmesi, bir fona
       bağlanması ya da bir kurumun çatısı altında yürütülmesi tek
       başına devir değildir: sorumluluğu üstlenen bir devralan yoksa
       kurucuların yetkisi aynı şekilde sürer. Tür 'proje' yazıldığında
       kod bunu devir saymaz. */
    'devir' => [
        'tarih'    => '',
        'devralan' => '',
        'tur'      => '',
    ],

    /* ---- Diller ----
       Sistem şu an Türkçe ve İngilizce yayımlanıyor. Yapı, yeni bir dil
       eklemeyi tek yerden mümkün kılacak biçimde kuruldu: buraya bir
       satır eklemek ve metinleri o dile çevirmek yeterlidir.

         kod    : iki harfli dil kodu (HTML lang niteliği)
         ad     : o dilin kendi adı, dil düğmesinde görünür
         yon    : yazı yönü (ltr / rtl)
         ulke   : bu dilin varsayılan sayıldığı ülke kodları
         yedek  : çevirisi bulunmayan metinlerde kullanılacak dil

       VARSAYILAN DAVRANIŞ: Türkiye'den gelen ziyaretçi Türkçe sayfayı,
       Türkiye dışından gelen ziyaretçi İngilizce sayfayı görür. Ülke
       bilgisi Cloudflare'in CF-IPCountry başlığından okunur; başlık yoksa
       tarayıcı diline bakılır. Ziyaretçi dili elle değiştirdiğinde
       seçimi bir yıl boyunca hatırlanır ve ülke kuralı devreye girmez.  */
    'diller' => [
        'tr' => ['ad' => 'Türkçe',  'yon' => 'ltr', 'ulke' => ['TR', 'CY'], 'yedek' => 'en',
                 'tarih' => '%g %a %y'],
        'en' => ['ad' => 'English', 'yon' => 'ltr', 'ulke' => ['*'],        'yedek' => 'tr',
                 'tarih' => '%a %g, %y'],

        /* ---- ÇEVİRİSİ HAZIR, GÖZDEN GEÇİRİLMEMİŞ DİLLER ----
           Sözlükleri k/ceviri/ altındadır ve arayüzün 1822 dizesinin
           tamamını karşılar. 'gozden_gecirildi' YAZILMAMIŞTIR: bir dilin
           gözden geçirildiğini söylemek, söyleyenin sorumluluğudur. O
           satır konana kadar sayfanın başında bunu söyleyen bir uyarı
           durur (bkz. tg_dil_gozden_gecirildi).

           'ulke' BİLEREK BOŞTUR. Ülke listesi doldurulsaydı Almanya'dan
           gelen her ziyaretçi, hiçbir insanın okumadığı bir çeviriyi
           İSTEMEDEN görürdü; bu, uyarı yazmakla telafi edilmeyecek bir
           iddiadır. Dil, ziyaretçi dil seçicisinden ya da tarayıcı
           dilinden gelir: ikisi de ziyaretçinin kendi bildirimidir.
           Bir insan çeviriyi gözden geçirip 'gozden_gecirildi' => true
           yazdığında ülke listesi de aynı satırda doldurulur.           */
        'de' => ['ad' => 'Deutsch',  'yon' => 'ltr', 'ulke' => [], 'yedek' => 'en',
                 'tarih' => '%g. %a %y'],
        'fr' => ['ad' => 'Français', 'yon' => 'ltr', 'ulke' => [], 'yedek' => 'en',
                 'tarih' => '%g %a %y'],
        /* İspanyolcada ay adı tarihin içinde KÜÇÜK harfle yazılır;
           tek başına bir etiket olarak ise büyük harfle. Sözlükteki
           anahtar etiket hâlini taşır, tarihteki hâl burada yazılıdır. */
        'es' => ['ad' => 'Español',  'yon' => 'ltr', 'ulke' => [], 'yedek' => 'en',
                 'tarih' => '%g de %a de %y',
                 'ay_tarih' => ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
                                'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']],
        /* Rusçada ay adı tarihin içinde tamlanan hâle girer: "Январь"
           tek başına, "января" tarihte. Sözlükteki anahtar yalın hâli
           taşır; tarihteki hâl burada yazılıdır. */
        'ru' => ['ad' => 'Русский',  'yon' => 'ltr', 'ulke' => [], 'yedek' => 'en',
                 'tarih' => '%g %a %y г.',
                 'ay_tarih' => ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                                'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря']],
        /* Çincede tarih yıldan başlar ve ay SAYIYLA yazılır: 2026年9月15日.
           Ay adının çevirisi ("九月") yalnız ay tek başına anıldığında
           kullanılır; tarihte %A (ay sayısı) geçer. */
        'zh' => ['ad' => '中文',      'yon' => 'ltr', 'ulke' => [], 'yedek' => 'en',
                 'tarih' => '%y年%A月%g日'],

        /* ARAPÇA, SAĞDAN SOLA YAZILAN İLK DİLDİR.
           Buradaki 'rtl' yalnız bir etiket değildir: k_yon() onu <html
           dir> içine yazar ve bütün düzen aynalanır. Bu yüzden Arapça
           açılmadan önce biçim dosyasındaki YÖN TUTAN kurallar (sol
           kenarlık, sola dayama, sola konumlama) mantıksal karşılıklarına
           çevrildi; yoksa yazı sağa geçer ama çizgiler, oklar ve yan
           çubuk solda kalırdı. Denetimi sinama/rtl-kapi.js yapar. */
        'ar' => ['ad' => 'العربية', 'yon' => 'rtl', 'ulke' => [], 'yedek' => 'en',
                 'tarih' => '%g %a %y'],
    ],
    'dil_varsayilan' => 'en',   /* ülkesi bilinmeyen ziyaretçi için */

    /* ---------------------------------------------------------------
       ÇEVİRİ
       Makine çevirisi bir yayın değil, bir okuma yardımıdır. Yayın
       ilkelerinin 15. bölümünde yazılı altı koşula bağlıdır ve
       KAPALI gelir: 'anahtar' boşken sistem çeviri sunmaz, başka
       hiçbir şeyi değişmez. Ücretli bir servise bağımlı kalmak
       "her zaman ücretsiz" sözüyle çelişeceği için bu bilinçli bir
       varsayılandır.

       Kesin kural: yalnızca YAYIMLANMIŞ metinler çevrilir. Hakem
       sürecindeki bir çalışma hiçbir koşulda dışarıya gönderilmez;
       o metin yazarın emanetidir.
       --------------------------------------------------------------- */
    'ceviri' => [
        'acik'      => false,   /* true yapmak tek başına yetmez; anahtar da gerekir */
        'motor'     => '',      /* hizmetin adı; künyede bu ad yazılır */
        'ucnokta'   => '',      /* hizmetin adresi */
        'anahtar'   => '',      /* BOŞ BIRAKIN. Anahtar depoya değil,
                                   veri dizinindeki yonetim-ayar.json'a yazılır. */
        'diller'    => [],      /* ['de','fr','es'] gibi; boşsa yalnızca arayüz dilleri */
        'yalniz_yayimlanmis' => true,  /* değiştirmeyin */
    ],
    'dil_ulke'       => 'tr',   /* 'ulke' listesi eşleşirse kullanılacak dil belirlenir */

    /* ---------------------------------------------------------------
       ORCID İLE KİMLİK

       ORCID bir KİMLİKTİR, bir UNVAN değildir: kimin kim olduğunu
       söyler, doktorasını doğrulamaz, hakem yapmaz. Hakemlik için
       belge yine istenir.

       KAPALI GELİR ve üçü birden dolmadan sistem ORCID'den hiç söz
       etmez: düğme basılmaz, uç 404 verir. Yarım açık bir kapı,
       ziyaretçiye çalışmayan bir düğme göstermekten başka işe yaramaz.

       'gizli' BOŞ BIRAKILIR. Değer veri dizinindeki yonetim-ayar.json
       dosyasına 'orcid_gizli' anahtarıyla yazılır: depo herkese
       açıktır ve bir kez giren anahtar geçmişte kalır.

       Kurulum: orcid.org hesabınızda Developer Tools'tan bir istemci
       kaydı alın (Public API ücretsizdir, üyelik gerekmez). Dönüş
       adresi HTTPS olmalıdır ve tam olarak şudur:
           https://<alan-adiniz>/api/orcid/donus

       TEK GİRİŞ YOLU DEĞİLDİR ve olmamalıdır: parola yolu yerinde
       durur. Dış bir servisi tek anahtar yapmak, o servisin çalışma
       saatlerini kendi çalışma saatiniz yapmaktır.
       --------------------------------------------------------------- */
    'orcid' => [
        'acik'    => true,
        /* İSTEMCİ KİMLİĞİ GİZLİ DEĞİLDİR ve burada durabilir: ORCID'e
           giden yetki adresinin içinde, her ziyaretçinin tarayıcısında
           açıkça görünür. Gizli olan yalnız 'gizli' alanıdır. */
        'istemci' => 'APP-MHRZVOPP3NPZZRLD',
        'gizli'   => '',        /* BOŞ BIRAKIN: yonetim-ayar.json -> orcid_gizli */
        'sandbox' => false,     /* denemede true; canlıda false */
    ],

    /* ---- Sistem yöneticisi ----
       Bir hakemi editör atamadıysa, atamayı yapan olarak okuyucuya
       gösterilecek addır. Atama kayıtları sonradan değiştirilemez.        */
    'yonetici_ad' => 'Kutadgu Yayın Yönetimi',
    /* Yazar alanı boş gelmiş eski kayıtlar için gösterilecek ad.
       Bu sistemin ilk iki kaydı yazar alanı doldurulmadan girilmişti ve
       o günkü varsayılan bu addı; kayıtların anlamı değişmesin diye
       burada tutulur. Yeni bir kurulumda boş bırakılmalıdır. */
    'varsayilan_yazar' => 'Dr. Behiç Çetin',

    /* ---- Veri dizini ----
       Boş ise: KUTADGU_DATA ortam değişkeni, yoksa webroot'un bir üstündeki
       kutadgu-data klasörü kullanılır. Taşımada elle verilebilir.        */
    'veri_dizini' => '',   /* boşsa: webroot'un üstündeki kutadgu-data */

    /* ---- Yayın ilkeleri (sayısal eşikler tek yerden yönetilir) ---- */

    /* ---- BENZERLİK (İNTİHAL) RAPORU ŞARTI ----
       KURUL KARARI, 13 Ağustos 2026: yazardan benzerlik raporu
       İSTENMEZ. Gerekçe üç başlıkta yazılıdır ve sonradan
       tartışılabilsin diye burada durur.

       1. SİSTEM ÖLÇEMEDİĞİ BİR ŞEYİ ŞART KOŞUYORDU. Yüzde on beşlik
          eşik yazılıydı, ama bu sistemde onu ölçen hiçbir şey yok:
          yazar bir sayı yazıyor, sistem o sayıyı denetlemeden kayda
          geçiriyordu. Denetlenmeyen bir eşik bir güvence değil, bir
          görüntüdür — ve bu sistemin en pahalı yalanı, sahip olmadığı
          bir işareti basmaktır. Açıklıklar sayfasında bu zaten
          "bilinen açık" olarak yazılıydı; kural kalkınca açık da
          kalkar.

       2. RAPOR PARALIDIR VE KAPIYI PARAYA BAĞLAR. iThenticate ve
          Turnitin kurumsal aboneliktir. Kurumu olmayan, emekli, bağımsız
          ya da yoksul bir araştırmacı raporu alamaz; alamayan gönderemez.
          Açık erişimli bir sistemde kapının bedeli parasal olamaz.

       3. ASIL DENETİM ZATEN AÇIKLIKTA. Bu sistemde metin herkese
          açıktır, hakem adıyla imzalar, rapor yayımlanır ve çalışma
          kalıcı olarak durur. Kaynak göstermeden aktarılmış bir
          paragrafı bulmanın en güçlü yolu, oranı değil METNİ açmaktır.
          Aşırma yasağı KALKMADI ve kalkmaz; kalkan şey, yazardan
          satın alınmış bir belge istemektir.

       Sayısal eşikler silinmedi: şart geri açılırsa aynen işler.
       Yazar dilerse raporunu yine ekleyebilir; eklediği rapor
       çalışmanın sayfasında görünür.                                    */
    'benzerlik_raporu_sarti' => false,

    'benzerlik_ust'      => 15,   /* genel benzerlik en çok % (şart açıkken) */
    'benzerlik_tek_ust'  => 5,    /* tek kaynaktan en çok %  (şart açıkken) */
    'kabul_gecerli'      => 2,    /* kaç olumlu rapor "hakem onaylı" sayılır */
    'ret_donusum'        => 2,    /* kaç ret sonrası yazıya döner */
    'asgari_unvan'       => 'Dr.',

    /* ---- Kefil düzeni ----
       Doktora şartı, niteliği korumak için kondu; ama olduğu gibi
       bırakıldığında henüz derecesini almamış bir araştırmacının kendi
       emeğiyle ürettiği bir çalışmada adının bulunmasını da engelliyordu.
       Bu, korunmak istenen şeyi değil yalnızca kapıyı koruyordu.

       Bu yüzden bir yol açıldı: unvanı olmayan bir araştırmacı, iki
       doktoralı kişinin adıyla sorumluluk üstlenmesiyle yazar olabilir.
       Destekleyen araştırmacılardan biri aynı çalışmanın doktoralı ortak
       yazarı olmak
       zorundadır (metni bilen biri), diğeri çalışmayla hiçbir bağı
       olmayan doğrulanmış bir doktoralı olmak zorundadır (dışarıdan bir
       göz). Böylece danışman kendi öğrencisini tek başına içeri alamaz.

       Desteklemek bir imza işidir: destekleyen araştırmacıların her birine
       kendi bağlantısı gider ve onaylamadığı sürece çalışma yayına alınmaz.
       Kimsenin adı, haberi olmadan bir sorumluluğun altına yazılmaz.

       BU DÜZENİN ADI DEĞİŞTİ. Önceki adı "kefil"di; kefalet hukuktan
       gelir ve borç çağrıştırır, oysa burada yapılan şey bir araştırmacının
       bir başkasının adına ve emeğine açıkça sahip çıkmasıdır. Görünen ad
       artık "destekleyen araştırmacı", düzenek aynıdır.

       AŞAĞIDAKİ ANAHTARLAR BİLEREK DEĞİŞMEDİ. Bu adlar yayımlanmış
       kayıtların ve gönderilmiş onay bağlantılarının içindedir;
       değiştirilirse eskiden gönderilmiş bağlantılar kırılır ve ayar
       sessizce varsayılana düşer. Görünen ad ortak.php'deki
       tg_destek_ad() işlevinden gelir.

       Unvansız yazarın yapamayacakları: hakem raporu yazmak, kurul
       oylamasında oy kullanmak ve tek yazar olarak çalışma göndermek.
       Doktora belgesini sunup doğrulattığı gün bu sınırların hepsi
       kendiliğinden kalkar; destek kaydı ise çalışmanın sayfasında
       kalmayı sürdürür, çünkü o gün gerçekten olmuş bir şeydir.        */
    /* ---- DOKTORA ŞARTI KALDIRILDI (kurul kararı, 13 Ağustos 2026) ----
       Mustafa Zihni Tunca'nın önerisi, kurulun tamamının kabulü:
       "yazarlık için doktora şartı çok doğru değil, doktorası olmayan
       çok değerli araştırmacılar da var."

       Karar YALNIZCA YAZARLIĞI kapsar. Hakemlik için doğrulanmış doktora
       aranmaya devam eder ve gevşemez: bir metni değerlendirmek, onu
       yazmaktan başka bir sorumluluktur ve raporun altına ad atılır.

       KAPI KAPANMADI, YER DEĞİŞTİRDİ. Eskiden kapı unvandaydı: metne
       bakılmadan yazarın derecesine bakılıyordu. Şimdi kapı EDİTÖRDEDİR:
       çalışma gönderilir, editör sisteme girip girmeyeceğine karar
       verir. Bu, niteliği koruyan bir denetimi kaldırmak değil, onu
       doğru yere koymaktır — bir çalışmanın ölçüsü yazarının unvanı
       değil, yönteminin sağlamlığıdır ve bunu ancak metne bakan biri
       söyleyebilir.

       İÇERİK KURALLARI YERİNDE DURUR ve kurul kararında ayrıca
       yazılıdır: istenmeyen posta ve reklam engellenir, siyasi reklam
       kabul edilmez, kişiyi küçük düşüren metin yayımlanmaz. Bunlar
       ilkeler.php §03'te zaten yazılıdır; doktora şartının kalkması
       onları gevşetmez.

       ORCID ZORUNLU KALIR. Doktora bir yeterlik iddiasıdır, ORCID ise
       bir kimlik: bu sistemde adın kime ait olduğunu belirleyen tek
       numaradır ve açık hakemlikte adın doğru kişiye bağlanması
       vazgeçilemez.

       BU SATIR true YAPILIRSA eski düzen geri gelir ve destekleyen
       araştırmacı yolu yeniden işler; ikisi birlikte çalışacak biçimde
       bırakıldı, çünkü bir kararı geri almanın yolu kodu silmek
       olmamalıdır.                                                     */
    'yazarlik_doktora_sarti' => false,

    /* KAPALI — kurul kararı, 14 Ağustos 2026: "kefil de kapalı,
       kaldırdık o sistemi tamamen."

       Zaten fiilen kapalıydı: yazarlık için doktora şartı 13 Ağustos'ta
       kalkmış, bu düzenin de karşılığı kalmamıştı. Ama ANAHTAR true
       duruyordu ve bu bir tuzaktı — sistemin kapalı saydığı bir düzen,
       ayar dosyasında açık yazıyordu. Nitekim kişi sayfasındaki
       "Destekle yazar" rozeti bu yüzden basılmayı sürdürdü (bkz.
       ortak.php, tg_destekle_yazar).

       ANAHTAR SİLİNMEDİ, FALSE YAPILDI. Silinseydi tg_ayar() varsayılana
       düşerdi ve varsayılan true'dur; yani düzen sessizce geri açılırdı.
       Kapatmanın doğru yolu, kapalı olduğunu YAZMAKtır.

       KAYITLAR SİLİNMEZ. Daha önce destekle yazar olmuş biri varsa o
       kayıt çalışmanın sayfasında kalır: o gün gerçekten olmuş bir
       şeydir ve bu sistem olmuş bir şeyi geri almaz. Kapanan şey yeni
       kayıt üretilmesidir. */
    'kefil_acik'         => false,
    'kefil_sayisi'       => 2,
    'kefil_gerekce_asgari' => 120,  /* destekleyenin yazacağı gerekçenin en az karakteri */

    /* ---- Yayın sonrası şerh ----
       Bir çalışma yayımlandıktan sonra da tartışılabilmelidir. Şerh
       adla yazılır, silinmez; ancak gerekçesi görünür kalmak şartıyla
       editörce perdelenebilir.                                         */
    'serh_asgari_karakter' => 120,

    /* ---- Oylama ve itiraz düzeni ----
       Yazar ile hakemin anlaşamadığı yerde karar tek kişiye bırakılmaz.
       Üç bağımsız oy toplanır; oy verenler birbirini görmez, üçüncü oy
       düştüğünde sonuç ve bütün oylar gerekçeleriyle açılır.
       Dört geçerli oy kullanan kişi yazarlık hakkını kazanır.          */
    /* ---- Kuruluş dönemi istisnası ----
       Sistem kurulurken, kuralların işleyip işlemediğini görebilmek için
       ilk çalışmalar denenerek yayımlandı. Bu çalışmalar bugünkü
       kuralların tamamını karşılamaz. Onları geriye dönük olarak
       düzeltmek ya da sessizce silmek doğru olmazdı; ikisi de kaydı
       bozar. Bu yüzden istisna açıkça tanınıyor ve çalışmanın kendi
       sayfasında, gerekçesiyle birlikte okuyucuya gösteriliyor.

       Listeye çalışmanın kalıcı kimliği (bcid) yazılır. Liste
       BÜYÜTÜLMEMELİDİR: kuruluş dönemi bir kez yaşanır.               */
    'kurulus_istisna' => ['bc.000001', 'bc.000002'],

    'oy_gerekli'            => 3,
    'oy_yazarlik_esigi'     => 4,
    'oy_asgari_karakter'    => 80,    /* oy gerekçesi en az */
    'itiraz_asgari_karakter'=> 200,   /* itiraz ve şikâyet gerekçesi en az */

    /* ---- Hakem atama kuralı ----
       Yazarın kendi hakemini seçmesi, bu tür sistemlerdeki en bilinen açıktır:
       birbirini onaylayan kapalı bir halka doğurur. Bu yüzden bir çalışmanın
       "hakem onaylı" sayılabilmesi için olumlu raporlardan en az birinin,
       yazarın önermediği bir hakemden gelmesi aranır.                       */
    'bagimsiz_hakem_zorunlu' => true,
    'hakem_hedef'            => 3,    /* bir çalışmaya kaç hakem tamamlanana kadar gönüllü aranır */

    /* ---- Hakemlik koşulları ----
       DOKTORA BELGEYLE KANITLANMAZ. Bu satırlarda bir zaman "Türkiye'de
       e-Devlet barkod kodu, yurt dışında kamusal alan adı istenir"
       yazıyordu; o düzen 14 Ağustos 2026 kurul kararıyla bütünüyle
       kaldırıldı ve bu açıklama 19 Ağustos'a kadar burada kaldı — bir
       ayar dosyasında duran eskimiş bir cümle, kuralı kodda arayan
       kişiyi yanlış yere gönderir.

       BUGÜNKÜ KURAL TEK CÜMLEDİR: doktorayı bir editör ADIYLA doğrular.
       Editör ya da baş editör birini hakem olarak atadıysa, o kişinin en
       az doktoralı olduğu atamayla kabul edilir ve sorumluluk atayana
       aittir; kişi kendisi gönüllü olduysa çalışmanın editörü ya da bir
       baş editör onaylar. Gerekçesinin tamamı ortak.php'de
       tg_dogrulama_editor()'ün başındadır.                                */
    'gecis_sonu' => '2027-12-31',
    'karsilikli_hakem_ay'    => 12,   /* A, B'yi değerlendirdiyse B kaç ay A'yı değerlendiremez */
    /* Yazar hakem ÖNERİR, davet etmez (11 Ağustos 2026). Aynı anda kaç
       önerisi editör kararını bekleyebilir. Sınır, editörün önüne yığın
       gitmesini engeller; karar verildikçe yenisi eklenebilir. */
    'yazar_oneri_ust'        => 6,
    /* Yazar ile hakem arasındaki diyalogda her tarafın kaç turu var.
       Bir tur = yazarın notu + hakemin yanıtı. Sınırsız bir tartışma
       hakemin gönüllü emeğini sömürür ve kararı geciktirir; sıfır ise
       kanal kapalıdır. */
    'diyalog_tur_ust'        => 2,
    /* Diyalogda bir notun en az karakteri. Kısa bir "katılmıyorum",
       hakemin okuyup yanıtlamasını beklediğimiz bir şey değildir. */
    'diyalog_asgari'         => 80,

    /* ---- Rapor nitelik eşiği ----
       Hakemlik yazarlık hakkı kazandırdığı için, hızlı ve içeriksiz rapor
       yazma eğilimi doğar. Bu eşikleri karşılamayan rapor yayımlanır ama
       "hakemlik yapıldı" olarak sayılmaz.                                   */
    'rapor_asgari_karakter' => 400,
    'rapor_asgari_isaret'   => 2,     /* metinde işaretlenmiş en az kaç yer */

    /* ---- Hakem havuzu ve davet ----
       HAVUZ AÇIKTIR. Kim hangi bilim dalında hakemlik yapabildiği
       herkese görünür bir dizindir. Gerekçesi şudur: bu sistemde
       hakem raporu da, kurul oyu da, atamayı kimin yaptığı da zaten
       açıktır; "kim değerlendirebilir" bilgisini kapalı tutmak, açık
       olan her şeyin yanında tutarsız kalırdı. Ayrıca yazarın hakem
       önerebilmesi ancak açık bir dizinle anlamlı olur.

       Bedeli açıkça yazılıdır: hakemler doğrudan aranıp baskı altına
       alınabilir. Buna karşı iki koruma vardır: dizinde e-posta adresi
       görünmez ve bir hakem dilediği zaman kendini dizinden çıkarabilir.

       DAVET SÜRESİ. Yanıtlanmayan bir davet, aşağıdaki gün sayısından
       sonra kendiliğinden düşer ve çalışma yeniden hakem aranan listesine
       girer; davetin düştüğü çalışmanın sayfasında yazılı kalır. Süreyi
       baş editörler değiştirebilir: bu satır depoda varsayılanı tutar,
       veri dizinindeki yonetim-ayar.json içindeki 'hakem_davet_gun' değeri
       varsa onun yerine geçer ve değişiklik için gönderim gerekmez.  */
    'hakem_havuzu_acik'  => true,
    'hakem_davet_gun'    => 10,

    /* ---- Lisans ---- */
    /* ---- GÜVEN İŞARETLERİ ----
       Bir kurumun bir yayın sistemine bakarken sorduğu dış işaretler.
       KURAL: sahip olunmayan bir işaret basılmaz, ama gizlenmez de;
       "alınmadı" diye yazılır. 'alindi' demek için TARİH zorunludur
       (YYYY-AA-GG); tarihsiz bir "alındı" kodda kendiliğinden "alınmadı"
       sayılır (ortak.php, tg_guven_isaretleri). Bu, iyi niyetli bir
       elin ileride buraya tarihsiz bir iddia yazmasını engeller.

       Bugün hiçbiri alınmamıştır. Bir işaret alındığında değişecek tek
       yer burasıdır: sayfalar, üstveri ve bildiri metni buradan okur.
       Durum: 'alindi' | 'basvuruldu' | 'yok'                          */
    'guven_isaretleri' => [
        'issn'  => ['durum' => 'yok', 'tarih' => '', 'no' => '', 'url' => ''],
        'doi'   => ['durum' => 'yok', 'tarih' => '', 'no' => '', 'url' => ''],
        'doaj'  => ['durum' => 'yok', 'tarih' => '', 'no' => '', 'url' => ''],
        'cope'  => ['durum' => 'yok', 'tarih' => '', 'no' => '', 'url' => ''],
        'dizin' => ['durum' => 'yok', 'tarih' => '', 'no' => '', 'url' => ''],
        'arsiv' => ['durum' => 'yok', 'tarih' => '', 'no' => '', 'url' => ''],
    ],

    /* =================================================================
       ZENODO · HER ÇALIŞMAYA KALICI KİMLİK (DOI)
       -----------------------------------------------------------------
       KURUL SORUSU — 20 Ağustos 2026: "Zenodo'dan her eklenen yazı için
       DOI alınabilir mi?"

       ALINABİLİR. Zenodo (CERN) her YAYIMLANMIŞ kayda bir DOI verir ve
       bunun için kurum, üyelik ya da ücret istemez. Bilinmesi gereken
       üç şey var ve üçü de bu sistemin kendi ilkelerini ilgilendirir:

       1. DOI, ZENODO KAYDINA ÇÖZÜLÜR. Adres zenodo.org'u gösterir,
          kutadgu.net'i değil. Kutadgu'nun kendi adına çözülen DOI'ler
          istenirse yolu Crossref/DataCite üyeliğidir (yıllık ücret ve
          ISSN ister). İkisi birlikte de yürür: bugün Zenodo, yarın
          tescil; Zenodo kaydı o gün "aynıdır" bağıyla durmayı sürdürür.
       2. YAYIMLANAN KAYIT SİLİNEMEZ. Zenodo'nun kendi kuralı budur ve
          DOI kalıcıdır. Bu yüzden bu sistemde YAYIMLAMA hiçbir zaman
          kendiliğinden yapılmaz: taslak kendiliğinden hazırlanır (geri
          alınabilir), yayımlama adı belli bir insanın bir tıklamasıdır.
       3. KAYIT EN AZ BİR DOSYA İSTER (Zenodo SSS). Kutadgu dosya
          barındırmaz; kayda çalışmanın kendi metni tek dosyalık bir
          HTML ve makine okunur bir JSON olarak konur.

       'sandbox' AÇIKKEN sandbox.zenodo.org kullanılır: gerçek DOI
       verilmez (10.5072 önekiyle deneme kimliği çıkar) ve hiçbir kayıt
       kalıcı olmaz. Önce orada denenir.

       JETON BURADA DURMAZ. Sunucudaki yonetim-ayar.json içinde durur ve
       panelden yazılır; depoya giren hiçbir dosyada sır bulunmaz.
       ================================================================= */
    'zenodo' => [
        'acik'     => false,   /* jeton yazılıp sınandıktan sonra açılır */
        'sandbox'  => true,    /* önce deneme evreninde */
        'topluluk' => '',      /* Zenodo topluluğu (isteğe bağlı) */
        'dergi'    => 'Kutadgu',
        'otomatik' => false,   /* yalnız TASLAK otomatik hazırlanır; yayımlama elle */
    ],

    'lisans'     => 'CC BY 4.0',
    'lisans_url' => 'https://creativecommons.org/licenses/by/4.0/',

    /* ---------------------------------------------------------------
       INDEXNOW ANAHTARI
       ---------------------------------------------------------------
       Yeni bir çalışma yayımlandığında Bing, Yandex, Seznam ve Naver'a
       aynı anda haber verilir; dizinin haftalarca beklemesi gerekmez.
       Kod yazılıydı ama anahtar hiçbir yerde tanımlı değildi: ayar boş
       olduğu için bildirim SESSİZCE atlanıyordu. Sessiz atlamak doğru
       davranıştır (anahtarsız bildirim yapılmaz), ama yuvanın hiç
       yazılı olmaması yanlıştı: olmayan bir ayarın açılabileceğini
       kimse bilemez.

       BURASI BOŞ BIRAKILIR. Anahtar bir sırdır ve bu dosya depodadır;
       değer veri dizinindeki yonetim-ayar.json'a yazılır:

           { "indexnow_anahtar": "..." }

       Anahtar sizin seçtiğiniz rastgele bir dizedir: 8 ile 128 arası,
       yalnız onaltılık harfler (0-9, a-f). Bir kez üretip yazmanız
       yeter; sunucuya ayrıca dosya YÜKLEMENİZ GEREKMEZ. IndexNow'un
       istediği /<anahtar>.txt dosyası indexnow.php tarafından bu
       ayardan üretilir, yani bildirimle doğrulama tek kaynaktan gelir
       ve ayrışamaz. */
    'indexnow_anahtar' => '',

    /* ---------------------------------------------------------------
       İLETİYE YANIT HEDEFİ (saat)
       ---------------------------------------------------------------
       İletişim sayfası bütün türlere aynı şeyi söylüyordu: "yanıt için
       birkaç gün gerekebilir". Bir kurum destek için yazdığında birkaç
       gün beklemek, o desteğin kaybedilmesidir; sistemin kendisi de
       destekle ayakta duracaksa bu süre bir ayrıntı değildir.

       BU BİR SÖZ DEĞİL, BİR ÖLÇÜTTÜR. Gerçekleşen süreler istatistik
       sayfasında yayımlanır (ortalama, ortanca, en uzun ve hedefin
       içinde kalma oranı). Kendi vaadini denetlemeyen bir vaat, vaat
       değildir; bu sistem hakemlik gecikmesini de aynı sebeple
       yayımlıyor.

       Süreyi kısaltmak bir kod işi değildir: iletiyi kimin alacağı
       yonetim-ayar.json'daki 'iletisim.alicilar' listesiyle belirlenir
       ve o listeye ikinci bir kişi eklemek, buradaki sayıyı
       küçültmenin tek gerçek yoludur. */
    /* ---------------------------------------------------------------
       İLETİ SAKLAMA SÜRESİ (gün)
       ---------------------------------------------------------------
       ARŞİV İLE YAZIŞMA AYNI ŞEY DEĞİLDİR ve bu ayrım burada yazılıdır.

       Yayımlanmış bir çalışma, hakem raporu ya da kurul oyu HİÇBİR
       ZAMAN silinmez; geri çekilen çalışma bile metniyle durur, çünkü
       kayıt eksilirse arşiv arşiv olmaktan çıkar. İletişim formundan
       gelen bir ileti ise kayıt değil YAZIŞMADIR: bir insanın adı,
       adresi ve serbestçe yazdığı metindir. Onu süresiz saklamak bir
       erdem değil, bir yüktür. Sistemin okuma kaydı için yazdığı cümle
       burada da geçerlidir: toplanmayan veri sızdırılamaz; saklanmayan
       veri de öyle.

       KAPANMA ANINDA SİLİNMEZ. Bir ileti yanıtlandıktan hemen sonra
       kapanır, ama kişinin elindeki bağlantı ona "konuşmanız burada
       durur" diye söz vermiştir ve kişi ertesi gün bir şey daha
       yazabilir. Kapanır kapanmaz silmek, verilen sözü bozmaktır.

       YANITLANMAMIŞ İLETİ HİÇ SİLİNMEZ. O bekleyen bir iştir; süreyle
       silinmesi, biriken işi süpürmek olurdu.

       Sıfır yazılırsa o durumdaki iletiler hiç silinmez. */
    'iletisim_saklama' => [
        'kapali'     => 180,  /* yanıtlanıp kapatılmış konuşma */
        'istenmeyen' => 30,   /* elle istenmeyen diye işaretlenmiş */
        'acik'       => 0,    /* bekleyen iş: süreyle silinmez */
    ],

    'iletisim_hedef' => [
        'kurum'     => 24,   /* kurumsal destek ve himaye başvurusu */
        'destek'    => 24,
        'isbirligi' => 48,
        'hata'      => 48,   /* bildirilen bir arıza bekletilmez */
        'oneri'     => 72,
        'genel'     => 72,
        '*'         => 72,
    ],
];
