<?php
/* =====================================================================
   KUTADGU - Bilim alanı sınıflandırması / Field classification
   ---------------------------------------------------------------------
   NEDEN JEL DEĞİL

   JEL kodları yalnızca iktisadı kapsar; Amerikan İktisat Derneği'nin
   kendi literatür dizini için tuttuğu bir listedir. Fizik, tıp, tarih
   ya da mühendislik orada yoktur. Bu sistem bütün bilim dallarına açık
   olduğuna göre, iktisada özgü bir liste omurga olamaz.

   NEDEN KENDİMİZ UYDURMUYORUZ

   Sıfırdan bir sınıflandırma uydurmak, sistemi dünyanın geri kalanından
   koparır: hiçbir istatistik kurumu, hiçbir dizin ve hiçbir fon kuruluşu
   onu tanımaz. Bu yüzden ilk iki basamak uluslararası bir ölçüte
   bağlanır.

   OMURGA: FORD (OECD Frascati / UNESCO)
     6 ana alan, 42 alt alan. Frascati Kılavuzu'nda tanımlıdır, UNESCO
     İstatistik Enstitüsü kullanır, ulusal ajansların çoğu buna eşler.
     Kod biçimi burada da aynıdır: 5.2, 1.6, 3.2 ...

   ÜÇÜNCÜ BASAMAK: BİZİM
     FORD ancak on yılda bir gözden geçirilir; yeni doğan bir alan orada
     yıllarca görünmez. Bu yüzden üçüncü basamağı biz tutarız ve HERKESE
     AÇIKTIR: bir araştırmacı kayıt olurken ya da çalışma gönderirken
     listede olmayan bir dal önerebilir. Önerilen dal, kimin ne zaman
     önerdiği yazılı olarak eklenir; bu da bu sistemin geri kalanı gibi
     kayda geçer ve silinmez.

     Kod biçimi:  5.2.014   (FORD alt alanı + kendi sıra numaramız)

   YENİ BİR BİLİM DALI DOĞARSA
     Üç ayrı durum vardır ve üçünün de karşılığı vardır:

     1) Yeni dal var olan bir alt alana sığıyorsa (çoğu durum): üçüncü
        basamağa yazılır. 5.2.013 gibi.
     2) Hiçbir alt alana sığmıyorsa, yani gerçekten yeni bir ANA alansa
        (sistem mimarlığı, analog teknolojiler gibi): 9 numara buna
        ayrılmıştır. 9.1, 9.2 ... kendi alt alanları olur; altlarına da
        dal yazılır. Sonradan FORD o alanı tanırsa kayda 'ford' eşlemesi
        eklenir, uluslararası kodla da bildirilir, ama bizim kodumuz
        değişmez.
     3) Yeni dal birden çok alanın kesişiminde duruyorsa (bilişsel
        küratörlük gibi): kayda 'ust' listesi eklenir ve dal bağlı
        olduğu her alt alanın altında görünür. Kodu tektir; kimliği
        kodudur, yeri değil. Bkz. al_ustler().

   NE İŞE YARAR
     - Yazar çalışmasının alanını seçer.
     - Hakem kendi alanlarını seçer; editör hakem havuzunu alana göre
       süzer. "Kim bu işi değerlendirebilir" sorusu ada değil koda
       bağlanır.
     - Dizin ve fon başvurularında alan bilgisi uluslararası bir kodla
       verilebilir.

   ESKİ ALANLAR
     Sistemde önce yedi kaba alan vardı (sag, fen, sos, ikt, egt, hkk,
     san). Kayıtlar silinmez: eskiler burada FORD karşılıklarına
     eşlenir ve okunmayı sürdürür.
   ===================================================================== */

if (!function_exists('al_ana')) {

    /* ---- 1. basamak: altı ana alan (FORD) ---- */
    function al_ana(): array {
        return [
            '1' => ['tr' => 'Doğa bilimleri',                  'en' => 'Natural sciences'],
            '2' => ['tr' => 'Mühendislik ve teknoloji',        'en' => 'Engineering and technology'],
            '3' => ['tr' => 'Tıp ve sağlık bilimleri',         'en' => 'Medical and health sciences'],
            '4' => ['tr' => 'Ziraat ve veteriner bilimleri',   'en' => 'Agricultural and veterinary sciences'],
            '5' => ['tr' => 'Sosyal bilimler',                 'en' => 'Social sciences'],
            '6' => ['tr' => 'Beşerî bilimler',                 'en' => 'Humanities'],
            /* ---- 9: uluslararası sınıflandırmada henüz yeri olmayan alanlar ----
               FORD altı alanla kapalıdır ve on yılda bir gözden geçirilir.
               Oysa yeni bir bilim dalı, sınıflandırmaya girmesinden yıllar
               önce doğar ve o yıllarda da çalışma üretir. "Diğer" kutusuna
               atmak, o çalışmaları görünmez kılar.

               Bu yüzden dokuzuncu numara bize ayrıldı: FORD'un altı alanının
               hiçbirine sığmayan yeni alanlar burada kendi yerlerini alır.
               Sonradan FORD bu alanı tanırsa, kayda 'ford' eşlemesi eklenir
               ve uluslararası kodla da bildirilir; ama BURADAKİ KOD DEĞİŞMEZ.
               Tamgada olduğu gibi: verilmiş bir kimlik geri alınmaz. */
            '9' => ['tr' => 'Yeni ve sınıflandırılmamış alanlar', 'en' => 'New and unclassified fields'],
        ];
    }

    /* ---- 2. basamak: kırk iki alt alan (FORD) ----
       Kodlar ve sıra uluslararası ölçütle birebir aynıdır; burada
       yalnızca Türkçe karşılıkları eklenmiştir. Bu listeye satır
       EKLENMEZ: FORD ne diyorsa odur. Yeni dallar üçüncü basamağa
       yazılır. */
    function al_alt(): array {
        return [
            '1.1' => ['tr' => 'Matematik',                              'en' => 'Mathematics'],
            '1.2' => ['tr' => 'Bilgisayar ve bilişim bilimleri',        'en' => 'Computer and information sciences'],
            '1.3' => ['tr' => 'Fizik bilimleri',                        'en' => 'Physical sciences'],
            '1.4' => ['tr' => 'Kimya bilimleri',                        'en' => 'Chemical sciences'],
            '1.5' => ['tr' => 'Yer bilimleri ve çevre bilimleri',       'en' => 'Earth and related environmental sciences'],
            '1.6' => ['tr' => 'Biyoloji bilimleri',                     'en' => 'Biological sciences'],
            '1.7' => ['tr' => 'Diğer doğa bilimleri',                   'en' => 'Other natural sciences'],

            '2.1'  => ['tr' => 'İnşaat mühendisliği',                   'en' => 'Civil engineering'],
            '2.2'  => ['tr' => 'Elektrik, elektronik ve bilişim mühendisliği', 'en' => 'Electrical, electronic and information engineering'],
            '2.3'  => ['tr' => 'Makine mühendisliği',                   'en' => 'Mechanical engineering'],
            '2.4'  => ['tr' => 'Kimya mühendisliği',                    'en' => 'Chemical engineering'],
            '2.5'  => ['tr' => 'Malzeme mühendisliği',                  'en' => 'Materials engineering'],
            '2.6'  => ['tr' => 'Tıp mühendisliği',                      'en' => 'Medical engineering'],
            '2.7'  => ['tr' => 'Çevre mühendisliği',                    'en' => 'Environmental engineering'],
            '2.8'  => ['tr' => 'Çevre biyoteknolojisi',                 'en' => 'Environmental biotechnology'],
            '2.9'  => ['tr' => 'Endüstriyel biyoteknoloji',             'en' => 'Industrial biotechnology'],
            '2.10' => ['tr' => 'Nanoteknoloji',                         'en' => 'Nanotechnology'],
            '2.11' => ['tr' => 'Diğer mühendislik ve teknolojiler',     'en' => 'Other engineering and technologies'],

            '3.1' => ['tr' => 'Temel tıp bilimleri',                    'en' => 'Basic medicine'],
            '3.2' => ['tr' => 'Klinik tıp',                             'en' => 'Clinical medicine'],
            '3.3' => ['tr' => 'Sağlık bilimleri',                       'en' => 'Health sciences'],
            '3.4' => ['tr' => 'Tıbbi biyoteknoloji',                    'en' => 'Medical biotechnology'],
            '3.5' => ['tr' => 'Diğer tıp bilimleri',                    'en' => 'Other medical sciences'],

            '4.1' => ['tr' => 'Tarım, ormancılık ve balıkçılık',        'en' => 'Agriculture, forestry and fisheries'],
            '4.2' => ['tr' => 'Hayvancılık ve süt bilimi',              'en' => 'Animal and dairy science'],
            '4.3' => ['tr' => 'Veteriner bilimi',                       'en' => 'Veterinary science'],
            '4.4' => ['tr' => 'Tarımsal biyoteknoloji',                 'en' => 'Agricultural biotechnology'],
            '4.5' => ['tr' => 'Diğer ziraat bilimleri',                 'en' => 'Other agricultural sciences'],

            '5.1' => ['tr' => 'Psikoloji ve bilişsel bilimler',         'en' => 'Psychology and cognitive sciences'],
            '5.2' => ['tr' => 'İktisat ve işletme',                     'en' => 'Economics and business'],
            '5.3' => ['tr' => 'Eğitim bilimleri',                       'en' => 'Educational sciences'],
            '5.4' => ['tr' => 'Sosyoloji',                              'en' => 'Sociology'],
            '5.5' => ['tr' => 'Hukuk',                                  'en' => 'Law'],
            '5.6' => ['tr' => 'Siyaset bilimi',                         'en' => 'Political science'],
            '5.7' => ['tr' => 'Sosyal ve ekonomik coğrafya',            'en' => 'Social and economic geography'],
            '5.8' => ['tr' => 'Medya ve iletişim',                      'en' => 'Media and communications'],
            '5.9' => ['tr' => 'Diğer sosyal bilimler',                  'en' => 'Other social sciences'],

            '6.1' => ['tr' => 'Tarih ve arkeoloji',                     'en' => 'History and archaeology'],
            '6.2' => ['tr' => 'Dil ve edebiyat',                        'en' => 'Languages and literature'],
            '6.3' => ['tr' => 'Felsefe, etik ve din',                   'en' => 'Philosophy, ethics and religion'],
            '6.4' => ['tr' => 'Sanat (sanat tarihi, sahne sanatları, müzik)', 'en' => 'Arts (arts, history of arts, performing arts, music)'],
            '6.5' => ['tr' => 'Diğer beşerî bilimler',                  'en' => 'Other humanities'],
        ] + al_alt_yeni();
    }

    /* 9 altındaki alt alanlar. FORD listesine satır eklenmez; yeni bir
       ANA alan doğduğunda yeri burasıdır. Veri dizinindeki alanlar.json
       içine yazılır ve buraya karışır; depodaki liste boş başlar. */
    function al_alt_yeni(bool $yalnizOnayli = true): array {
        $out = [];
        foreach (al_dal_ek() as $k => $v) {
            if (!is_array($v) || !preg_match('/^9\.\d{1,2}$/', (string)$k)) continue;
            /* Karara bağlanmamış ya da onaylanmamış bir alan listeye
               girmez; yalnızca adı çözümlenir (bkz. al_ad). Öneri
               aşamasındaki bir alanın seçilebilmesi, editör onayını
               anlamsız kılardı. */
            if ($yalnizOnayli && (string)($v['durum'] ?? 'onayli') !== 'onayli') continue;
            $out[(string)$k] = ['tr' => (string)($v['tr'] ?? $k), 'en' => (string)($v['en'] ?? ($v['tr'] ?? $k))];
        }
        return $out;
    }

    /* ---- 3. basamak: dallar ----
       Bu liste sistemin kendisine aittir ve BÜYÜR. Başlangıç kümesi
       aşağıdadır; kullanıcıların önerdiği dallar veri dizinindeki
       'alanlar.json' dosyasına eklenir ve buraya karışır. Depodaki
       liste her kurulumda aynıdır; eklenenler o kurulumun kaydıdır.

       Bir dalı öneren kişinin adı ve tarihi kayıtta durur. */
    function al_dal_cekirdek(): array {
        return [
            /* kod        alt alan  Türkçe                          İngilizce */
            '1.2.001' => ['tr' => 'Yapay zekâ ve makine öğrenmesi',  'en' => 'Artificial intelligence and machine learning'],
            '1.2.002' => ['tr' => 'Veri bilimi',                     'en' => 'Data science'],
            '1.2.003' => ['tr' => 'Ağ bilimi ve karmaşık sistemler', 'en' => 'Network science and complex systems'],
            '1.2.004' => ['tr' => 'Bilgi güvenliği',                 'en' => 'Information security'],
            '1.5.001' => ['tr' => 'İklim bilimi',                    'en' => 'Climate science'],
            '1.6.001' => ['tr' => 'Genetik ve genomik',              'en' => 'Genetics and genomics'],
            '2.2.001' => ['tr' => 'Yazılım mühendisliği',            'en' => 'Software engineering'],
            '2.11.001'=> ['tr' => 'Yenilenebilir enerji teknolojileri','en' => 'Renewable energy technologies'],
            '3.3.001' => ['tr' => 'Halk sağlığı',                    'en' => 'Public health'],
            '3.3.002' => ['tr' => 'Epidemiyoloji',                   'en' => 'Epidemiology'],
            '5.2.001' => ['tr' => 'Makro iktisat',                   'en' => 'Macroeconomics'],
            '5.2.002' => ['tr' => 'Mikro iktisat',                   'en' => 'Microeconomics'],
            '5.2.003' => ['tr' => 'Ekonometri',                      'en' => 'Econometrics'],
            '5.2.004' => ['tr' => 'Uluslararası iktisat ve ticaret', 'en' => 'International economics and trade'],
            '5.2.005' => ['tr' => 'Maliye ve kamu ekonomisi',        'en' => 'Public finance and public economics'],
            '5.2.006' => ['tr' => 'Finans ve bankacılık',            'en' => 'Finance and banking'],
            '5.2.007' => ['tr' => 'Yönetim ve organizasyon',         'en' => 'Management and organisation'],
            '5.2.008' => ['tr' => 'Pazarlama',                       'en' => 'Marketing'],
            '5.2.009' => ['tr' => 'Muhasebe',                        'en' => 'Accounting'],
            '5.2.010' => ['tr' => 'Davranışsal iktisat',             'en' => 'Behavioural economics'],
            '5.2.011' => ['tr' => 'Bölgesel ve kentsel iktisat',     'en' => 'Regional and urban economics'],
            '5.2.012' => ['tr' => 'Çalışma ekonomisi',               'en' => 'Labour economics'],
            /* KURUL GERİ BİLDİRİMİ (M. Z. Tunca, 13 Ağustos 2026):
               "işletme alt alanlarında ciddi eksiklikler var, sadece
               pazarlama buldum; üretim/operasyon yönetimi gibi bir ana
               alan ya da lojistik, YBS, TZY, TKY gibi alt alanlar da
               olsa iyi olurdu."

               Doğruydu: liste iktisat tarafını ayrıntılı, işletme
               tarafını üç başlıkla (yönetim, pazarlama, muhasebe)
               geçiyordu. İşletmenin ana işlevlerinden üretim yoktu.
               Aşağıdakiler eklendi; numaralar sıradan devam eder,
               ARADAKİ NUMARALAR YENİDEN KULLANILMAZ: yayımlanmış bir
               çalışmanın alan kodu değişirse o çalışmanın sınıflandırma
               kaydı sessizce başka bir alana kayar. */
            '5.2.013' => ['tr' => 'Üretim ve operasyon yönetimi',    'en' => 'Production and operations management'],
            '5.2.014' => ['tr' => 'Tedarik zinciri yönetimi',        'en' => 'Supply chain management'],
            '5.2.015' => ['tr' => 'Lojistik',                        'en' => 'Logistics'],
            '5.2.016' => ['tr' => 'Yönetim bilişim sistemleri',      'en' => 'Management information systems'],
            '5.2.017' => ['tr' => 'Toplam kalite yönetimi',          'en' => 'Total quality management'],
            '5.2.018' => ['tr' => 'İnsan kaynakları yönetimi',       'en' => 'Human resource management'],
            '5.2.019' => ['tr' => 'Stratejik yönetim',               'en' => 'Strategic management'],
            '5.2.020' => ['tr' => 'Girişimcilik ve yenilik yönetimi','en' => 'Entrepreneurship and innovation management'],
            '5.2.021' => ['tr' => 'Sayısal yöntemler ve yöneylem araştırması', 'en' => 'Quantitative methods and operations research'],
            '5.2.022' => ['tr' => 'Uluslararası işletmecilik',       'en' => 'International business'],
            '5.3.001' => ['tr' => 'Yükseköğretim çalışmaları',       'en' => 'Higher education studies'],
            '5.3.002' => ['tr' => 'Eğitim teknolojileri',            'en' => 'Educational technology'],
            '5.5.001' => ['tr' => 'Kamu hukuku',                     'en' => 'Public law'],
            '5.5.002' => ['tr' => 'Özel hukuk',                      'en' => 'Private law'],
            '5.8.001' => ['tr' => 'Bilim iletişimi ve açık bilim',   'en' => 'Science communication and open science'],
            '6.2.001' => ['tr' => 'Türk dili ve edebiyatı',          'en' => 'Turkish language and literature'],
            '6.3.001' => ['tr' => 'Bilim etiği',                     'en' => 'Research ethics'],
            /* =========================================================
               KURUL GERİ BİLDİRİMİ: "o alanlar kısıtlı oldu gibi."
               ÖLÇÜLDÜ VE DOĞRUYDU: 42 alt alanın 30'unda HİÇ dal yoktu
               ve 39 dalın 27'si sosyal bilimlerdeydi. Yani liste bütün
               bilimi kapsadığını söylüyor ama kurucuların çalıştığı
               köşeyi ayrıntılandırıyordu. Bir fizikçi ya da veteriner
               kendi alt alanını açtığında boş bir liste buluyordu.

               Aşağıdaki dallar o boşluğu kapatmak için eklendi. Liste
               yine de TAMAM DEĞİLDİR ve olamaz: bilim, sınıflandırmadan
               hızlı büyür. Eksik bir dal gören yazar alan önerisi
               açabilir; öneri editör kararıyla listeye girer.
               Kapsayıcılığın yolu listeyi bitirmek değil, büyüyebilir
               tutmaktır.

               NUMARALAR SIRADAN GİDER VE GERİ KULLANILMAZ: yayımlanmış
               bir çalışmanın alan kodu değişirse o çalışma sessizce
               başka bir alana kayar.
               ========================================================= */
            '1.1.001' => ['tr' => 'Cebir', 'en' => 'Algebra'],
            '1.1.002' => ['tr' => 'Analiz', 'en' => 'Analysis'],
            '1.1.003' => ['tr' => 'Geometri ve topoloji', 'en' => 'Geometry and topology'],
            '1.1.004' => ['tr' => 'Olasılık ve istatistik', 'en' => 'Probability and statistics'],
            '1.1.005' => ['tr' => 'Uygulamalı matematik', 'en' => 'Applied mathematics'],
            '1.1.006' => ['tr' => 'Sayısal analiz', 'en' => 'Numerical analysis'],
            '1.1.007' => ['tr' => 'Ayrık matematik', 'en' => 'Discrete mathematics'],
            '1.3.001' => ['tr' => 'Kuramsal fizik', 'en' => 'Theoretical physics'],
            '1.3.002' => ['tr' => 'Katıhâl fiziği', 'en' => 'Condensed matter physics'],
            '1.3.003' => ['tr' => 'Optik ve fotonik', 'en' => 'Optics and photonics'],
            '1.3.004' => ['tr' => 'Nükleer ve parçacık fiziği', 'en' => 'Nuclear and particle physics'],
            '1.3.005' => ['tr' => 'Atom ve molekül fiziği', 'en' => 'Atomic and molecular physics'],
            '1.3.006' => ['tr' => 'Astrofizik ve kozmoloji', 'en' => 'Astrophysics and cosmology'],
            '1.3.007' => ['tr' => 'Plazma fiziği', 'en' => 'Plasma physics'],
            '1.4.001' => ['tr' => 'Organik kimya', 'en' => 'Organic chemistry'],
            '1.4.002' => ['tr' => 'Anorganik kimya', 'en' => 'Inorganic chemistry'],
            '1.4.003' => ['tr' => 'Analitik kimya', 'en' => 'Analytical chemistry'],
            '1.4.004' => ['tr' => 'Fizikokimya', 'en' => 'Physical chemistry'],
            '1.4.005' => ['tr' => 'Polimer kimyası', 'en' => 'Polymer chemistry'],
            '1.4.006' => ['tr' => 'Elektrokimya', 'en' => 'Electrochemistry'],
            '1.7.001' => ['tr' => 'Bilim tarihi ve felsefesi', 'en' => 'History and philosophy of science'],
            '1.7.002' => ['tr' => 'Disiplinlerarası doğa bilimleri', 'en' => 'Interdisciplinary natural sciences'],
            '2.1.001' => ['tr' => 'Yapı mühendisliği', 'en' => 'Structural engineering'],
            '2.1.002' => ['tr' => 'Geoteknik mühendisliği', 'en' => 'Geotechnical engineering'],
            '2.1.003' => ['tr' => 'Ulaştırma mühendisliği', 'en' => 'Transportation engineering'],
            '2.1.004' => ['tr' => 'Hidrolik ve su kaynakları', 'en' => 'Hydraulics and water resources'],
            '2.1.005' => ['tr' => 'Yapı malzemesi', 'en' => 'Construction materials'],
            '2.1.006' => ['tr' => 'Deprem mühendisliği', 'en' => 'Earthquake engineering'],
            '2.3.001' => ['tr' => 'Termodinamik ve ısı transferi', 'en' => 'Thermodynamics and heat transfer'],
            '2.3.002' => ['tr' => 'Akışkanlar mekaniği', 'en' => 'Fluid mechanics'],
            '2.3.003' => ['tr' => 'Makine tasarımı ve imalat', 'en' => 'Machine design and manufacturing'],
            '2.3.004' => ['tr' => 'Mekatronik', 'en' => 'Mechatronics'],
            '2.3.005' => ['tr' => 'Otomotiv mühendisliği', 'en' => 'Automotive engineering'],
            '2.3.006' => ['tr' => 'Havacılık ve uzay mühendisliği', 'en' => 'Aerospace engineering'],
            '2.3.007' => ['tr' => 'Robotik', 'en' => 'Robotics'],
            '2.4.001' => ['tr' => 'Kimyasal süreç mühendisliği', 'en' => 'Chemical process engineering'],
            '2.4.002' => ['tr' => 'Ayırma işlemleri', 'en' => 'Separation processes'],
            '2.4.003' => ['tr' => 'Kataliz ve reaksiyon mühendisliği', 'en' => 'Catalysis and reaction engineering'],
            '2.4.004' => ['tr' => 'Süreç güvenliği', 'en' => 'Process safety'],
            '2.5.001' => ['tr' => 'Metalurji', 'en' => 'Metallurgy'],
            '2.5.002' => ['tr' => 'Seramik malzemeler', 'en' => 'Ceramic materials'],
            '2.5.003' => ['tr' => 'Polimer malzemeler', 'en' => 'Polymer materials'],
            '2.5.004' => ['tr' => 'Kompozit malzemeler', 'en' => 'Composite materials'],
            '2.5.005' => ['tr' => 'Yüzey mühendisliği ve kaplama', 'en' => 'Surface engineering and coatings'],
            '2.6.001' => ['tr' => 'Biyomedikal görüntüleme', 'en' => 'Biomedical imaging'],
            '2.6.002' => ['tr' => 'Biyomekanik', 'en' => 'Biomechanics'],
            '2.6.003' => ['tr' => 'Biyomalzemeler', 'en' => 'Biomaterials'],
            '2.6.004' => ['tr' => 'Tıbbi cihaz tasarımı', 'en' => 'Medical device design'],
            '2.6.005' => ['tr' => 'Rehabilitasyon mühendisliği', 'en' => 'Rehabilitation engineering'],
            '2.7.001' => ['tr' => 'Su ve atıksu arıtımı', 'en' => 'Water and wastewater treatment'],
            '2.7.002' => ['tr' => 'Hava kirliliği ve denetimi', 'en' => 'Air pollution and control'],
            '2.7.003' => ['tr' => 'Katı atık yönetimi', 'en' => 'Solid waste management'],
            '2.7.004' => ['tr' => 'İklim değişikliği ve uyum', 'en' => 'Climate change and adaptation'],
            '2.7.005' => ['tr' => 'Yenilenebilir enerji sistemleri', 'en' => 'Renewable energy systems'],
            '2.8.001' => ['tr' => 'Biyoremediasyon', 'en' => 'Bioremediation'],
            '2.8.002' => ['tr' => 'Atıktan enerji', 'en' => 'Waste to energy'],
            '2.9.001' => ['tr' => 'Biyoproses mühendisliği', 'en' => 'Bioprocess engineering'],
            '2.9.002' => ['tr' => 'Endüstriyel enzimler', 'en' => 'Industrial enzymes'],
            '2.9.003' => ['tr' => 'Biyoyakıtlar', 'en' => 'Biofuels'],
            '2.10.001' => ['tr' => 'Nanomalzemeler', 'en' => 'Nanomaterials'],
            '2.10.002' => ['tr' => 'Nanoelektronik', 'en' => 'Nanoelectronics'],
            '2.10.003' => ['tr' => 'Nanotıp', 'en' => 'Nanomedicine'],
            '3.1.001' => ['tr' => 'Anatomi', 'en' => 'Anatomy'],
            '3.1.002' => ['tr' => 'Fizyoloji', 'en' => 'Physiology'],
            '3.1.003' => ['tr' => 'Biyokimya', 'en' => 'Biochemistry'],
            '3.1.004' => ['tr' => 'Mikrobiyoloji', 'en' => 'Microbiology'],
            '3.1.005' => ['tr' => 'Farmakoloji', 'en' => 'Pharmacology'],
            '3.1.006' => ['tr' => 'Tıbbi genetik', 'en' => 'Medical genetics'],
            '3.1.007' => ['tr' => 'İmmünoloji', 'en' => 'Immunology'],
            '3.1.008' => ['tr' => 'Patoloji', 'en' => 'Pathology'],
            '3.2.001' => ['tr' => 'İç hastalıkları', 'en' => 'Internal medicine'],
            '3.2.002' => ['tr' => 'Cerrahi', 'en' => 'Surgery'],
            '3.2.003' => ['tr' => 'Kardiyoloji', 'en' => 'Cardiology'],
            '3.2.004' => ['tr' => 'Nöroloji', 'en' => 'Neurology'],
            '3.2.005' => ['tr' => 'Onkoloji', 'en' => 'Oncology'],
            '3.2.006' => ['tr' => 'Pediatri', 'en' => 'Paediatrics'],
            '3.2.007' => ['tr' => 'Psikiyatri', 'en' => 'Psychiatry'],
            '3.2.008' => ['tr' => 'Radyoloji', 'en' => 'Radiology'],
            '3.2.009' => ['tr' => 'Kadın hastalıkları ve doğum', 'en' => 'Obstetrics and gynaecology'],
            '3.2.010' => ['tr' => 'Anesteziyoloji', 'en' => 'Anaesthesiology'],
            '3.2.011' => ['tr' => 'Ortopedi', 'en' => 'Orthopaedics'],
            '3.2.012' => ['tr' => 'Göz hastalıkları', 'en' => 'Ophthalmology'],
            '3.2.013' => ['tr' => 'Diş hekimliği', 'en' => 'Dentistry'],
            '3.2.014' => ['tr' => 'Acil tıp', 'en' => 'Emergency medicine'],
            '3.4.001' => ['tr' => 'Gen tedavisi', 'en' => 'Gene therapy'],
            '3.4.002' => ['tr' => 'Biyobelirteçler', 'en' => 'Biomarkers'],
            '3.4.003' => ['tr' => 'Doku mühendisliği', 'en' => 'Tissue engineering'],
            '3.5.001' => ['tr' => 'Hemşirelik', 'en' => 'Nursing'],
            '3.5.002' => ['tr' => 'Eczacılık', 'en' => 'Pharmacy'],
            '3.5.003' => ['tr' => 'Beslenme ve diyetetik', 'en' => 'Nutrition and dietetics'],
            '3.5.004' => ['tr' => 'Fizyoterapi ve rehabilitasyon', 'en' => 'Physiotherapy and rehabilitation'],
            '3.5.005' => ['tr' => 'Sağlık yönetimi', 'en' => 'Health management'],
            '3.5.006' => ['tr' => 'Spor bilimleri', 'en' => 'Sport sciences'],
            '4.1.001' => ['tr' => 'Tarla bitkileri', 'en' => 'Field crops'],
            '4.1.002' => ['tr' => 'Bahçe bitkileri', 'en' => 'Horticulture'],
            '4.1.003' => ['tr' => 'Toprak bilimi', 'en' => 'Soil science'],
            '4.1.004' => ['tr' => 'Bitki koruma', 'en' => 'Plant protection'],
            '4.1.005' => ['tr' => 'Ormancılık', 'en' => 'Forestry'],
            '4.1.006' => ['tr' => 'Su ürünleri ve balıkçılık', 'en' => 'Fisheries and aquaculture'],
            '4.1.007' => ['tr' => 'Tarım ekonomisi', 'en' => 'Agricultural economics'],
            '4.1.008' => ['tr' => 'Tarım makineleri', 'en' => 'Agricultural machinery'],
            '4.2.001' => ['tr' => 'Zootekni', 'en' => 'Animal science'],
            '4.2.002' => ['tr' => 'Hayvan besleme', 'en' => 'Animal nutrition'],
            '4.2.003' => ['tr' => 'Süt teknolojisi', 'en' => 'Dairy technology'],
            '4.2.004' => ['tr' => 'Kanatlı yetiştiriciliği', 'en' => 'Poultry science'],
            '4.3.001' => ['tr' => 'Veteriner klinik bilimleri', 'en' => 'Veterinary clinical sciences'],
            '4.3.002' => ['tr' => 'Veteriner patoloji', 'en' => 'Veterinary pathology'],
            '4.3.003' => ['tr' => 'Hayvan refahı', 'en' => 'Animal welfare'],
            '4.4.001' => ['tr' => 'Bitki biyoteknolojisi', 'en' => 'Plant biotechnology'],
            '4.4.002' => ['tr' => 'Gıda biyoteknolojisi', 'en' => 'Food biotechnology'],
            '4.5.001' => ['tr' => 'Gıda bilimi ve teknolojisi', 'en' => 'Food science and technology'],
            '4.5.002' => ['tr' => 'Peyzaj mimarlığı', 'en' => 'Landscape architecture'],
            '5.1.001' => ['tr' => 'Klinik psikoloji', 'en' => 'Clinical psychology'],
            '5.1.002' => ['tr' => 'Gelişim psikolojisi', 'en' => 'Developmental psychology'],
            '5.1.003' => ['tr' => 'Sosyal psikoloji', 'en' => 'Social psychology'],
            '5.1.004' => ['tr' => 'Bilişsel bilim', 'en' => 'Cognitive science'],
            '5.1.005' => ['tr' => 'Endüstri ve örgüt psikolojisi', 'en' => 'Industrial and organisational psychology'],
            '5.1.006' => ['tr' => 'Eğitim psikolojisi', 'en' => 'Educational psychology'],
            '5.4.001' => ['tr' => 'Kent sosyolojisi', 'en' => 'Urban sociology'],
            '5.4.002' => ['tr' => 'Aile ve toplumsal cinsiyet', 'en' => 'Family and gender studies'],
            '5.4.003' => ['tr' => 'Göç çalışmaları', 'en' => 'Migration studies'],
            '5.4.004' => ['tr' => 'Sosyal politika', 'en' => 'Social policy'],
            '5.4.005' => ['tr' => 'Kültür sosyolojisi', 'en' => 'Sociology of culture'],
            '5.4.006' => ['tr' => 'Sosyal hizmet', 'en' => 'Social work'],
            '5.6.001' => ['tr' => 'Siyaset kuramı', 'en' => 'Political theory'],
            '5.6.002' => ['tr' => 'Karşılaştırmalı siyaset', 'en' => 'Comparative politics'],
            '5.6.003' => ['tr' => 'Uluslararası ilişkiler', 'en' => 'International relations'],
            '5.6.004' => ['tr' => 'Kamu yönetimi', 'en' => 'Public administration'],
            '5.6.005' => ['tr' => 'Kamu politikası', 'en' => 'Public policy'],
            '5.7.001' => ['tr' => 'Beşerî coğrafya', 'en' => 'Human geography'],
            '5.7.002' => ['tr' => 'Kentsel ve bölgesel planlama', 'en' => 'Urban and regional planning'],
            '5.7.003' => ['tr' => 'Turizm', 'en' => 'Tourism'],
            '5.9.001' => ['tr' => 'İletişim ve medya çalışmaları', 'en' => 'Communication and media studies'],
            '5.9.002' => ['tr' => 'Bilgi ve belge yönetimi', 'en' => 'Information and records management'],
            '5.9.003' => ['tr' => 'Kriminoloji', 'en' => 'Criminology'],
            '6.1.001' => ['tr' => 'Tarih', 'en' => 'History'],
            '6.1.002' => ['tr' => 'Arkeoloji', 'en' => 'Archaeology'],
            '6.1.003' => ['tr' => 'Sanat tarihi', 'en' => 'Art history'],
            '6.1.004' => ['tr' => 'Antropoloji', 'en' => 'Anthropology'],
            '6.4.001' => ['tr' => 'Müzik', 'en' => 'Music'],
            '6.4.002' => ['tr' => 'Sahne sanatları', 'en' => 'Performing arts'],
            '6.4.003' => ['tr' => 'Görsel sanatlar', 'en' => 'Visual arts'],
            '6.4.004' => ['tr' => 'Sinema ve televizyon', 'en' => 'Film and television'],
            '6.4.005' => ['tr' => 'Tasarım', 'en' => 'Design'],
            '6.4.006' => ['tr' => 'Mimarlık', 'en' => 'Architecture'],
            '6.5.001' => ['tr' => 'Felsefe', 'en' => 'Philosophy'],
            '6.5.002' => ['tr' => 'Din bilimleri', 'en' => 'Religious studies'],
            '6.5.003' => ['tr' => 'Dilbilim', 'en' => 'Linguistics'],
            '6.5.004' => ['tr' => 'Karşılaştırmalı edebiyat', 'en' => 'Comparative literature'],
            '6.5.005' => ['tr' => 'Çeviribilim', 'en' => 'Translation studies'],
        ];
    }

    /* Veri dizinindeki eklenmiş dallar. Biçim:
       { "5.2.013": {"tr":"...","en":"...","ekleyen":"Ad","tarih":"...","durum":"onayli|oneri"} }

       Bir dal birden çok alt alana bağlanabilir; o zaman kayda 'ust'
       eklenir:
       { "5.1.007": {"tr":"Bilişsel küratörlük","en":"Cognitive curation",
                     "ust":["6.4","5.8"], ...} }
       Kodun kendi üstü (5.1) her zaman geçerlidir; 'ust' ona eklenir,
       onun yerine geçmez. Bkz. al_ustler(). */
    function al_dal_ek(): array {
        $y = (function_exists('tg_veri_dizini') ? tg_veri_dizini() : '') . '/alanlar.json';
        if ($y === '/alanlar.json' || !is_file($y)) return [];
        $d = json_decode((string)file_get_contents($y), true);
        return is_array($d) ? $d : [];
    }

    /* Bütün dallar: çekirdek + eklenenler. Yalnızca onaylı olanlar
       seçim listelerinde görünür; öneri hâlindekiler editör onayını
       bekler ama kaydı baştan tutulur. */
    function al_dallar(bool $yalnizOnayli = true): array {
        static $c = null;
        if ($c === null) {
            $c = al_dal_cekirdek();
            foreach (al_dal_ek() as $k => $v) {
                if (!is_array($v) || !preg_match('/^\d\.\d{1,2}\.\d{3}$/', (string)$k)) continue;   /* 9.x.### de geçerlidir */
                $c[$k] = $v;
            }
            ksort($c, SORT_NATURAL);
        }
        if (!$yalnizOnayli) return $c;
        $o = [];
        foreach ($c as $k => $v) {
            if (isset($v['durum']) && $v['durum'] !== 'onayli') continue;
            $o[$k] = $v;
        }
        return $o;
    }

    /* ---- Uluslararası karşılık ----
       9 altındaki bir alan sonradan FORD'a girerse, kaydına
       "ford":"2.11" yazılır. O günden sonra dizinlere ve fon
       başvurularına uluslararası kodla da bildirilir; ama bizdeki
       kod olduğu gibi kalır, hiçbir çalışmanın alanı değişmez.
       Verilmiş bir kimlik geri alınmaz. */
    function al_ford(string $kod): string {
        $kod = trim($kod);
        if ($kod === '') return '';
        $ek = al_dal_ek();
        $f  = (string)($ek[$kod]['ford'] ?? '');
        if ($f === '' && ($u = al_ust($kod)) !== '') $f = (string)($ek[$u]['ford'] ?? '');
        return $f;
    }

    /* ---- Ad çözümleme ---- */
    function al_ad(string $kod, ?bool $en = null): string {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        /* DİZİ ANAHTARI OLARAK DİL KODU KULLANILMAZ. Alan adları
           tablosunda yalnız 'tr' ve 'en' anahtarları var; üçüncü bir
           dilde k_dil() 'de' döndürür ve anahtar bulunamaz, ad boş
           gelirdi. Çeviri katmanı zaten bu iş için var: tablodan iki
           dil okunur, hangisinin görüneceğine tg_t karar verir. */
        $d = $en ? 'en' : 'tr';
        $kod = trim($kod);
        if ($kod === '') return '';
        $dal = al_dallar(false);
        if (isset($dal[$kod])) return tg_t((array)$dal[$kod], $en);
        $alt = al_alt();
        if (isset($alt[$kod])) return tg_t((array)$alt[$kod], $en);
        $ana = al_ana();
        if (isset($ana[$kod])) return tg_t((array)$ana[$kod], $en);
        /* Henüz onaylanmamış öneriler listelerde görünmez, ama adları
           çözümlenir: editör ekranında ve kayıtta okunabilsinler. */
        $ek = al_dal_ek();
        if (isset($ek[$kod]) && is_array($ek[$kod])) {
            return isset($ek[$kod]) ? tg_t((array)$ek[$kod], $en) : $kod;
        }
        return $kod;
    }

    /* Bir kodun tam yolu: "Sosyal bilimler > İktisat ve işletme > Ekonometri" */
    function al_yol(string $kod, ?bool $en = null): string {
        $p = explode('.', trim($kod));
        $par = [];
        if (isset($p[0])) $par[] = al_ad($p[0], $en);
        if (isset($p[1])) $par[] = al_ad($p[0] . '.' . $p[1], $en);
        if (isset($p[2])) $par[] = al_ad($kod, $en);
        return implode(' › ', array_filter($par));
    }

    /* Aradisipliner bir dalın bütün yolları. Tek üstü varsa tek
       satır döner; birden çok üstü varsa hepsi döner ve sayfada
       "şu üç alanın kesişiminde" diye gösterilebilir. */
    function al_yollar(string $kod, ?bool $en = null): array {
        $ustl = al_ustler($kod);
        if (count($ustl) < 2) return [al_yol($kod, $en)];
        $ad  = al_ad($kod, $en);
        $out = [];
        foreach ($ustl as $u) {
            $p = explode('.', $u);
            $out[] = implode(' › ', array_filter([al_ad($p[0], $en), al_ad($u, $en), $ad]));
        }
        return $out;
    }

    /* ---- Kod listesi kurarken kullanılan anahtar çözümü ----
       Bir kod dizisini tekilleştirmenin en ucuz yolu kodu dizi
       anahtarı yapmaktır. Ama PHP, tamsayıya benzeyen bir anahtarı
       sessizce tamsayıya çevirir: "5.2" metin kalır, "5" ise 5 olur.
       array_keys() o listeyi geri verdiğinde ana alan kodları artık
       metin değil sayıdır ve strict_types açık olan bir sayfada
       al_ust() gibi metin bekleyen bir işleve girince sayfa çöker.
       Hakem dizini canlıda tam olarak bundan 500 veriyordu.

       Kod her yerde metindir; bu işlev o sözü tek yerde tutar. */
    function al_anahtarlar(array $harita): array {
        return array_map('strval', array_keys($harita));
    }

    /* Bir kodun üst alt alanı: 5.2.014 -> 5.2 ; 5.2 -> 5 ; 5 -> '' */
    function al_ust(string $kod): string {
        $p = explode('.', trim($kod));
        if (count($p) <= 1) return '';
        array_pop($p);
        return implode('.', $p);
    }

    /* ---- Bir dalın bütün üst alt alanları ----
       ARADİSİPLİNER DALLAR

       Bazı dallar tek bir alt alana sığmaz. "Bilişsel küratörlük"
       hem psikolojiye, hem sanata, hem iletişime bakar; onu üçünden
       birine hapsetmek, diğer ikisindeki hakemi ve okuyucuyu ondan
       koparır. Bu yüzden bir dal kayda 'ust' listesi ekleyerek
       birden çok alt alanın altında birden görünebilir.

       Kod tektir ve değişmez: dalın kimliği kodudur, yeri değil.
       Kodun kendi üstü (5.1.007 -> 5.1) her zaman listenin başındadır;
       'ust' ona eklenir, onun yerine geçmez.

       Dönen: ['5.1', '6.4', '5.8'] gibi, ilki asıl üst. */
    function al_ustler(string $kod): array {
        $kod = trim($kod);
        $asil = al_ust($kod);
        $out = $asil !== '' ? [$asil => true] : [];
        $dal = al_dallar(false);
        $ek  = $dal[$kod]['ust'] ?? null;
        if (is_array($ek)) {
            $alt = al_alt();
            foreach ($ek as $u) {
                $u = trim((string)$u);
                if ($u === '' || $u === $asil) continue;
                if (isset($alt[$u])) $out[$u] = true;
            }
        }
        return al_anahtarlar($out);
    }

    /* Bir kodun bakabileceği bütün ana alanlar. 5.1.007 için
       ['5'] ; 'ust' ile sanata da bağlıysa ['5','6']. */
    function al_anaLar(string $kod): array {
        $kod = trim($kod);
        if ($kod === '') return [];
        $out = [];
        $ilk = explode('.', $kod)[0];
        if ($ilk !== '') $out[$ilk] = true;
        foreach (al_ustler($kod) as $u) {
            $p = explode('.', $u)[0];
            if ($p !== '') $out[$p] = true;
        }
        return al_anahtarlar($out);
    }

    /* İki kod aynı aileden mi? Hakem eşleştirmesinde kullanılır:
       tam kod eşleşmesi en güçlü, alt alan eşleşmesi yeterli,
       ana alan eşleşmesi zayıf sayılır. Dönen: 3 | 2 | 1 | 0

       Aradisipliner dallarda karşılaştırma tek üst üzerinden değil,
       üst KÜMELERİ üzerinden yapılır: iki dalın paylaştığı bir alt
       alan varsa yakınlık 2'dir, sırf kodlarının ilk hanesi farklı
       diye 0 sayılmaz. */
    /* =================================================================
       KAPSAMA — SÜZGECİN SORDUĞU SORU
       -----------------------------------------------------------------
       "Bu çalışma seçilen alanın ALTINDA mı?" Süzgecin sorusu budur ve
       al_yakinlik() bu soruyu yanıtlamaz; o "iki kod birbirine ne kadar
       yakın" sorusunu yanıtlar ve hakem eşleştirmesi için yazılmıştır.
       İkisi karıştırıldığında süzgeç aynı anda hem GEVŞEK hem DAR olur;
       ölçüldü ve tam olarak öyleydi:

         al_yakinlik('5',       '5.2.001') = 1   -> temel alan seçen
                                                    HİÇBİR ŞEY bulamıyordu
         al_yakinlik('5.2.001', '5.2.007') = 2   -> "Makro iktisat" seçen
                                                    "Mikro iktisat"ı da alıyordu

       Yani okur "Sosyal bilimler"i seçtiğinde otuz çalışma varken sıfır
       sonuç görüyor, bir dalı seçtiğinde ise kardeş dalların hepsi
       geliyordu. Bildirilen "alanlar işlevsiz duruyor" cümlesinin
       arkasındaki ölçüm budur.

       Kapsama tek yönlüdür ve kesindir: seçilen kod, kaydın kodunun
       kendisi ya da üstlerinden biri olacak. Kardeş kardeşi kapsamaz.
       ================================================================= */
    function al_kapsar(string $ust, string $kod): bool {
        $ust = trim($ust); $kod = trim($kod);
        if ($ust === '' || $kod === '') return false;
        if ($ust === $kod) return true;
        /* al_ustler() yalnız bir basamak üstü verebilir; zincirin
           tamamı al_ust() ile yukarı yürünerek çıkarılır. */
        $g = $kod;
        $adim = 0;
        while ($adim++ < 8) {
            $u = al_ust($g);
            if ($u === '' || $u === $g) break;
            if ($u === $ust) return true;
            $g = $u;
        }
        /* Aradisipliner dallar birden çok alt alana bağlı olabilir;
           onların üstleri al_ustler() ile okunur ve her biri ayrıca
           yukarı yürünür. */
        foreach (al_ustler($kod) as $u2) {
            if ($u2 === $ust) return true;
            $g2 = $u2; $adim2 = 0;
            while ($adim2++ < 8) {
                $u3 = al_ust($g2);
                if ($u3 === '' || $u3 === $g2) break;
                if ($u3 === $ust) return true;
                $g2 = $u3;
            }
        }
        return false;
    }

    /* Bir kaydın kodlarından herhangi biri seçilen alanın altında mı? */
    function al_kayit_kapsam(array $yazi, string $ust): bool {
        foreach (al_kayit_kodlari($yazi) as $k) { if (al_kapsar($ust, (string)$k)) return true; }
        return false;
    }

    function al_yakinlik(string $a, string $b): int {
        $a = trim($a); $b = trim($b);
        if ($a === '' || $b === '') return 0;
        if ($a === $b) return 3;

        /* alt alan düzeyinde kesişim */
        $au = al_ustler($a); $bu = al_ustler($b);
        if ($au === []) $au = [$a];          /* a zaten alt alan ya da ana alansa kendisi */
        if ($bu === []) $bu = [$b];
        if (array_intersect($au, $bu) !== []) return 2;
        /* biri alt alan, diğeri o alt alanın dalı olabilir */
        if (in_array($b, $au, true) || in_array($a, $bu, true)) return 2;

        /* ana alan düzeyinde kesişim */
        if (array_intersect(al_anaLar($a), al_anaLar($b)) !== []) return 1;
        return 0;
    }

    /* Seçim listesi: alt alanlara göre kümelenmiş dallar.
       [ '5.2' => ['ad' => '...', 'dallar' => ['5.2.001' => 'Makro iktisat', ...]], ... ] */
    function al_secim(?bool $en = null): array {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        $d = $en ? 'en' : 'tr';   /* bkz. al_ad(): anahtar dil kodu değildir */
        $out = [];
        foreach (al_alt() as $ak => $av) {
            $out[$ak] = ['ana' => al_ad(explode('.', $ak)[0], $en), 'ad' => tg_t((array)$av, $en), 'dallar' => [], 'ortak' => []];
        }
        foreach (al_dallar() as $k => $v) {
            $ad   = is_array($v) ? tg_t((array)$v, $en) : (string)$v;
            $ustl = al_ustler($k);
            /* aradisipliner dal, bağlı olduğu her alt alanın altında görünür */
            foreach ($ustl as $ust) {
                if (!isset($out[$ust])) continue;
                $out[$ust]['dallar'][$k] = $ad;
                if (count($ustl) > 1) $out[$ust]['ortak'][$k] = true;
            }
        }
        return $out;
    }

    /* Geçerli bir kod mu? Okuma içindir: karara bağlanmamış ya da
       reddedilmiş bir dalın kodu da geçerli sayılır, çünkü o kod bir
       kayıtta duruyor olabilir ve hiçbir kayıt geriye dönük olarak
       alansız bırakılmaz. */
    function al_gecerli(string $kod): bool {
        $kod = trim($kod);
        if ($kod === '') return false;
        return isset(al_dallar(false)[$kod]) || isset(al_alt()[$kod])
            || isset(al_alt_yeni(false)[$kod]) || isset(al_ana()[$kod]) || isset(al_dal_ek()[$kod]);
    }

    /* Şimdi seçilebilir mi? Yeni gelen bir seçim için budur: yalnızca
       onaylı dallar ve uluslararası listedeki alanlar seçilebilir. */
    function al_secilebilir(string $kod): bool {
        $kod = trim($kod);
        if ($kod === '') return false;
        return isset(al_dallar(true)[$kod]) || isset(al_alt()[$kod]) || isset(al_ana()[$kod]);
    }

    /* ---- Eski yedi kaba alanın karşılığı ----
       Kayıtlar silinmez; eski alan değerleri buradan çözülür. */
    function al_eski_esles(string $eski): array {
        $m = [
            'sag' => ['3'],
            'fen' => ['1', '2'],
            'sos' => ['5', '6'],
            'ikt' => ['5.2'],
            'egt' => ['5.3'],
            'hkk' => ['5.5'],
            'san' => ['6.4'],
        ];
        return $m[trim($eski)] ?? [];
    }

    /* Bir kaydın (hesap ya da çalışma) bütün alan kodları.
       Yeni kodlar 'alanlar' alanında, eski kaba alan 'alan' alanında
       durur; ikisi birlikte okunur ve eski olan yenisine çevrilir.
       Böylece hiçbir eski kayıt alansız kalmaz, hiçbir alan da elle
       taşınmak zorunda kalmaz. */
    function al_kayit_kodlari($kayit): array {
        if (!is_array($kayit)) return [];
        $ham = [];
        foreach ((array)($kayit['alanlar'] ?? []) as $k) $ham[] = (string)$k;
        $es = $kayit['alan'] ?? '';
        foreach (is_array($es) ? $es : [$es] as $k) $ham[] = (string)$k;
        return al_kodlar($ham);
    }

    /* Önerilen bir dala verilecek ilk boş kod.
       $ust bir alt alansa (5.2) -> 5.2.013
       $ust '9' ise, yani hiçbir alt alana sığmıyorsa -> 9.3
       Numara bir kez verilir ve geri alınmaz; reddedilen öneri de
       numarasını götürür, o numara bir daha kullanılmaz. */
    function al_yeni_kod(string $ust): string {
        $ust = trim($ust);
        $hepsi = al_dallar(false);
        if ($ust === '9') {
            $en = 0;
            foreach (array_keys(al_alt()) as $k) {
                if (preg_match('/^9\.(\d{1,2})$/', $k, $m)) $en = max($en, (int)$m[1]);
            }
            foreach (array_keys(al_dal_ek()) as $k) {
                if (preg_match('/^9\.(\d{1,2})$/', (string)$k, $m)) $en = max($en, (int)$m[1]);
            }
            return '9.' . ($en + 1);
        }
        if (!isset(al_alt()[$ust])) return '';
        $en = 0;
        foreach (array_keys($hepsi) as $k) {
            if (strpos((string)$k, $ust . '.') === 0) {
                $son = substr((string)$k, strlen($ust) + 1);
                if (ctype_digit($son)) $en = max($en, (int)$son);
            }
        }
        return $ust . '.' . str_pad((string)($en + 1), 3, '0', STR_PAD_LEFT);
    }

    /* =================================================================
       BASAMAK ADLARI — TEK KAYNAK
       -----------------------------------------------------------------
       ÖLÇÜLEN KUSUR: "bilim alanı" ve "bilim dalı" sistemde birbirinin
       yerine kullanılıyordu. Ana sayfadaki satırın adı "Bilim dallarına
       göre"ydi ama satırda DAL değil, en üst basamak vardı; yazilar.php
       ise aynı listeye "Bilim alanı ya da dalı" diyordu. Okur iki
       sayfada aynı şeyin iki adını görüyor ve sınıflandırmanın kaç
       basamaklı olduğunu çıkaramıyordu.

       DOĞRUSU YÖK/FORD merdivenidir ve üç basamaklıdır:

         1. basamak  temel alan    7 tane    (kod: 5)
         2. basamak  bilim alanı  42 tane    (kod: 5.2)
         3. basamak  bilim dalı  189 tane    (kod: 5.2.001)

       "Bilim alanı" üçünün ortak adı DEĞİLDİR; ikinci basamağın adıdır.
       Üçünü birden anmak gerektiğinde al_secici_etiket() kullanılır.

       Bundan sonra bu adlar elle yazılmaz; yazılırsa iki sayfa yeniden
       ayrışır. Ölçen kapı: sinama/alan-arama-kapi.php. */
    function al_duzey(string $kod): int {
        $kod = trim($kod);
        if ($kod === '') return 0;
        $n = substr_count($kod, '.') + 1;
        return $n > 3 ? 3 : $n;
    }

    /* Adlar tg_c()'den geçer, ELLE seçilmez. İlk yazımda burada
       "$en ? ... : ..." vardı; iki kapı birden kırmızıya döndü ve ikisi
       de haklıydı: (1) üçüncü bir dilde —altı makine çevirisi dili
       vardır— Türkçe metin sızıyordu, çünkü ikili seçim sözlük
       katmanını hiç görmüyor; (2) "ikili dil seçimi artmıyor" sayacı
       24'ten 27'ye çıkıyordu. tg_c() sayfanın dili tr/en değilse
       sözlüğe gider; anahtar da Türkçe kaynak metindir. */
    function al_duzey_ad(int $duzey, ?bool $en = null, bool $cogul = false): string {
        $ad = [
            1 => ['tr' => 'temel alan',  'trc' => 'temel alanlar',  'en' => 'broad field',       'enc' => 'broad fields'],
            2 => ['tr' => 'bilim alanı', 'trc' => 'bilim alanları', 'en' => 'field of science',  'enc' => 'fields of science'],
            3 => ['tr' => 'bilim dalı',  'trc' => 'bilim dalları',  'en' => 'branch of science', 'enc' => 'branches of science'],
        ];
        if (!isset($ad[$duzey])) return '';
        $s = $ad[$duzey];
        return $cogul ? tg_c($s['trc'], $s['enc'], $en) : tg_c($s['tr'], $s['en'], $en);
    }

    /* Bir kodun basamak adı: 5 -> "temel alan", 5.2.001 -> "bilim dalı" */
    function al_kod_duzey_ad(string $kod, ?bool $en = null): string {
        return al_duzey_ad(al_duzey($kod), $en);
    }

    /* Üç basamağı birden anan etiket. Seçicinin adı budur. */
    function al_secici_etiket(?bool $en = null): string {
        return tg_c('Bilim alanı ya da dalı', 'Field or branch of science', $en);
    }

    function al_hepsi_etiket(?bool $en = null): string {
        return tg_c('Bütün bilim alanları', 'All fields of science', $en);
    }

    /* Seçicideki ilk kümenin adı. Ayrı bir işlev, çünkü metin de
       çeviriye girer ve üç sayfada aynı olmalıdır. */
    function al_temel_kume_adi(?bool $en = null): string {
        return tg_c('Temel alanlar', 'Broad fields', $en);
    }

    /* =================================================================
       SEÇİCİNİN TEK KAYNAĞI
       -----------------------------------------------------------------
       ÖLÇÜLEN KUSUR: seçici üç yerde (ara.php temel, ara.php gelişmiş,
       yazilar.php) üç kez elle yazılmıştı ve ÜÇÜ DE TEMEL ALANI
       ATLIYORDU. Yedi temel alan yalnızca optgroup ETİKETİ olarak
       geçiyor, seçenek olarak hiç basılmıyordu. Sonucu şuydu: ana
       sayfadaki alan satırı /yazilar.php?alan=5 adresine gidiyor, sayfa
       otuz çalışmayı doğru süzüyor, ama seçicide seçili görünen hiçbir
       satır olmadığı için okur "süzgeç çalışmadı" diye okuyordu.
       yazilar.php'nin kendi açıklama satırı da "yedi temel alan"
       listelendiğini SÖYLÜYORDU; söylüyordu ama basmıyordu — bu
       sistemde en sık çıkan kusur sınıfı yine buydu.

       Artık üç basamak da tek yerden basılır: 7 + 42 + 189 = 238
       seçenek, 43 küme.

       $sayim verilirse (bir kod alıp altındaki çalışma sayısını dönen
       işlev) dolu satırların yanına sayı yazılır. Sayı bir söz değil
       ölçümdür: KAPSAYAN sayımdır, alt dalları toplamak gerekmez. */
    /* GİRİNTİ İŞARETİ UZUN ÇİZGİ DEĞİL.
       Kurul kararı, 15 Ağustos 2026: "sistemde hiç uzun çizgi olmasın."
       <option> etiketinde baştaki boşluklar tarayıcıda daraltılır, bu
       yüzden alt dalın girintisi görünür bir işaret ister. İşaret,
       sitenin başka her yerinde ayraç olarak kullanılan orta noktadır
       (·) ve girintiyi kırılmaz boşluk taşır. Tek yerde durur: üç seçici
       de bu işlevden basılır, işaret üç yerde ayrı ayrı yazılmaz. */
    function al_secenek_html(string $secili = '', ?callable $sayim = null, ?bool $en = null, string $girinti = "\u{00A0}\u{00A0}· "): string {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        $es = function ($s) { return function_exists('k_esc') ? k_esc((string)$s) : htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
        $sy = function ($n) { return function_exists('k_sayi') ? k_sayi($n) : (string)$n; };
        $et = function (string $ad, string $kod) use ($sayim, $sy): string {
            if ($sayim === null) return $ad;
            $n = (int)$sayim($kod);
            return $n > 0 ? ($ad . '  (' . $sy($n) . ')') : $ad;
        };
        $sec = function (string $kod) use ($secili): string { return $secili === $kod ? ' selected' : ''; };

        $h = '<optgroup label="' . $es(al_temel_kume_adi($en)) . '">';
        foreach (al_ana() as $k => $v) {
            $k = (string)$k;
            $h .= '<option value="' . $es($k) . '"' . $sec($k) . '>' . $es($et(al_ad($k, $en), $k)) . '</option>';
        }
        $h .= '</optgroup>';

        foreach (al_secim($en) as $ak => $av) {
            $ak = (string)$ak;
            $h .= '<optgroup label="' . $es($av['ana'] . ' › ' . $av['ad']) . '">';
            $h .= '<option value="' . $es($ak) . '"' . $sec($ak) . '>' . $es($et($av['ad'], $ak)) . '</option>';
            foreach ($av['dallar'] as $dk => $dad) {
                $dk = (string)$dk;
                $h .= '<option value="' . $es($dk) . '"' . $sec($dk) . '>' . $es($girinti . $et((string)$dad, $dk)) . '</option>';
            }
            $h .= '</optgroup>';
        }
        return $h;
    }

    /* Kapsayan sayım: bir kod, ALTINDAKİ her çalışmayı sayar.
       al_secenek_html()'e verilecek sayaç bundan üretilir. */
    function al_sayac(array $kayitlar): callable {
        return static function (string $kod) use ($kayitlar): int {
            $n = 0;
            foreach ($kayitlar as $y) { if (is_array($y) && al_kayit_kapsam($y, $kod)) $n++; }
            return $n;
        };
    }

    /* Bir kaydın alan kodları: yeni kodlar varsa onlar, yoksa eskiden
       çevrilenler. Hiçbir kayıt alansız kalmaz. */
    function al_kodlar($deger, bool $yalnizSecilebilir = false): array {
        $v = is_array($deger) ? $deger : (trim((string)$deger) !== '' ? [(string)$deger] : []);
        $out = [];
        foreach ($v as $k) {
            $k = trim((string)$k);
            if ($k === '') continue;
            $ok = $yalnizSecilebilir ? al_secilebilir($k) : al_gecerli($k);
            if ($ok) { $out[$k] = true; continue; }
            foreach (al_eski_esles($k) as $y) $out[$y] = true;
        }
        return al_anahtarlar($out);
    }
}
