<?php
/* =====================================================================
   SIR KAPISI: depoya sızmış anahtar, parola ve kişisel veri taraması.
   Depoya girmez; sınama betiğidir.

   Neden kapı, neden bir kerelik tarama değil:
   Bir depo bir kez temizlenir, sonra yeniden kirlenir. İlk taramada
   bulunan her şey elle silinebilir; ama ertesi hafta eklenen bir
   "geçici" belirteç kimsenin gözüne çarpmaz. Bu yüzden tarama bir
   ölçüme, ölçüm de her gönderimden önce koşturulabilen bir kapıya
   çevrildi. Kapı yanmıyorsa gönderim güvenlidir demiyoruz; kapı
   yanıyorsa gönderim durur diyoruz.

   Kullanım:
     php sir-kapi.php [dizin]
     KREPO=<dizin> php sir-kapi.php
     KGIT=<git deposu> php sir-kapi.php     (geçmişi de tarar, yavaştır)

   Varsayılan hedef /home/claude/kg/kutadgunet. Geçmiş taraması
   varsayılan DEĞİLDİR: 69 commit'in bütün eklenen satırlarını gezmek
   çalışma ağacı taramasından kat kat uzun sürer, her gönderim öncesi
   koşulacak bir kapının bunu her seferinde ödemesi gerekmez. Geçmiş
   yalnız depo halka açılmadan önce ve anahtar döndürüldükten sonra
   taranır.

   Betik hiçbir dosyaya yazmaz, yalnız okur ve sayar.
   ===================================================================== */
declare(strict_types=1);

/* =====================================================================
   BEYAZ LİSTE
   ---------------------------------------------------------------------
   Buradaki her madde gerçek taramada YANLIŞ ALARM çıktı. Elenen
   eşleşmeler yok sayılmaz, SAYILIR ve sayısı ekrana yazılır: beyaz
   liste büyüyorsa kapı körelmiş demektir, o zaman bu diziye bakılır.

   Liste koda gömülmedi, tek bir yerde toplandı ki bir sonraki oturum
   dosyanın başına bakıp "bu neden elenmiş?" sorusunu okuyabilsin ve
   gerekirse bir maddeyi silip kapıyı yeniden sıkabilsin.

   Alanlar:
     ad     kısa ad
     neden  NİÇİN yanlış alarm olduğu (tek satır, karar gerekçesi)
     bolum  hangi tarama bölümlerinde geçerli ('*' = hepsi)
     deger  eşleşen değere uygulanan kalıp (isteğe bağlı)
     dosya  dosya yoluna uygulanan kalıp (isteğe bağlı)
     satir  eşleşmenin geçtiği satırın tamamına uygulanan kalıp (ops.)
   Verilen bütün alanlar aynı anda tutarsa eşleşme elenir.
   ===================================================================== */
const BEYAZ = [

    /* ---- Arayüz çevirisi sözlükleri ---- */
    [
        'ad'    => 'Çeviri sözlüğündeki "password" etiketi',
        'neden' => 'k/ceviri/<dil>.json dosyaları arayüz metinlerinin karşılıklarıdır: anahtar İngilizce dize, değer o dildeki karşılığı. "Change password" gibi bir DÜĞME YAZISI, kalıba "password": "..." biçiminde görünür ve atama sanılır. Ortada bir değer ataması yoktur; iki dilde yazılmış bir etikettir. Eleme dar tutuldu: yalnız bu dizindeki .json dosyalarında, yalnız "atama" bölümünde ve YALNIZ değer boşluk içeriyorsa (yani bir cümle ya da söz öbeğiyse) geçerlidir. Boşluksuz, rastgele görünen bir değer buraya konsa kapı yine yanar.',
        'bolum' => ['atama'],
        'dosya' => '~^k/ceviri/[a-z-]+\.json$~',
        /* Eleme, DEĞERİN BİÇİMİNE bakar: yalnız harf, boşluk ve
           noktalama taşıyan bir değer bir insan metnidir ("Passwort",
           "Mot de passe actuel"). İçinde rakam, alt çizgi ya da simge
           geçen bir değer elenmez ve kapı yanar; bir sır böyle
           görünür. Sınır 80 harf: bir düğme yazısı bundan uzun olmaz. */
        'deger' => '~^[\p{L}\p{M}\s\x27’\-.,:;()!?«»“”/]{1,80}$~u',
    ],

    /* ---- Deneme düzeninin teslim edilemez adresleri ---- */
    [
        'ad'    => 'Deneme hesabının adresi (@deneme.gecersiz)',
        'neden' => 'Deneme düzeni, hesaplarını RFC 6761 ile KALICI OLARAK GEÇERSİZ kılınmış "invalid" ailesinden bir alan adında kurar: deneme.gecersiz. Bu adrese hiçbir posta sunucusu teslim yapamaz ve bu adres hiçbir zaman bir kişinin adresi olamaz. Kişisel veri taraması bir İNSANIN adresini arar; burada aranan şey yok. Eleme dar: yalnız bu alan adı, yalnız e-posta bölümünde. Başka bir alan adı yazılırsa kapı yanar.',
        'bolum' => ['eposta'],
        'deger' => '~@deneme\.gecersiz$~i',
    ],
    /* ---- "anahtar" bir kayıt alanı adıdır ---- */
    [
        'ad'    => 'Kaydın "anahtar" alanı (anahtar kelimeler)',
        'neden' => 'Bu sistemde bir çalışmanın anahtar KELİMELERİ "anahtar" alanında durur. Kalıp, adında "anahtar" geçen bir değişkene dize atanmasını gizli anahtar sanıyor. Eleme dar tutuldu: yalnız atama bölümünde, yalnız değer VİRGÜLLE AYRILMIŞ bir sözcük listesiyse ve içinde rastgele görünen bir dizge yoksa. Bir anahtar kelime listesi boşluk ve virgül taşır; bir gizli anahtar taşımaz. Boşluksuz ya da rakam-simge yığını bir değer yazılırsa kapı yine yanar.',
        'bolum' => ['atama'],
        'satir' => '~\x27anahtar(_en)?\x27\s*=>~',
        'deger' => '~^[\p{L}\p{M}\s,.\-]{3,120}$~u',
    ],

    /* ---- Bilerek yayımlanan yönetim adresi ---- */
    [
        'ad'    => 'Yönetim iletişim adresi, ayar satırı',
        'neden' => 'OAI-PMH Identify yanıtındaki <adminEmail> protokol gereği açıktır; dizinler ve DOAJ sisteme bu adresten ulaşır. Gizlenemez, gizlenmesi de istenmez. Kurucunun kendi adresidir, üçüncü kişinin verisi değildir. Eleme YALNIZCA iletisim_eposta satırında geçerlidir: aynı adres kurul listesine geri konursa satır eşleşmez ve kapı yanar, çünkü orada yayımlanmayan bir alandır.',
        'bolum' => ['eposta'],
        'dosya' => '~^ayar\.php$~',
        'satir' => '~iletisim_eposta~',
        'deger' => '~^cbehic@gmail\.com$~i',
    ],
    [
        'ad'    => 'Yönetim iletişim adresi, belgelerde',
        'neden' => 'SECURITY.md güvenlik bildirimi için, CONTRIBUTING.md katkı için aynı yayımlanan adresi gösterir. Bir güvenlik belgesinin bildirim adresi olmadan yazılması anlamsız olurdu.',
        'bolum' => ['eposta'],
        'dosya' => '~^(SECURITY|CONTRIBUTING)\.md$~',
        'deger' => '~^cbehic@gmail\.com$~i',
    ],
    [
        'ad'    => 'Kurulum belgesindeki alan tarifi',
        'neden' => 'TELEGRAM-KURULUM.md içinde "gizli" alanının ne yazılacağını Türkçe anlatan cümle; değerin kendisi değil, tarifi. Gerçek bir anahtar taşımaz.',
        'bolum' => ['atama'],
        'dosya' => '~KURULUM~',
        'deger' => '~ürettiğiniz|olusturdugunuz|oluşturduğunuz|buraya|yazınız|yaziniz~iu',
    ],

    /* ---- Form ve belge yer tutucuları ---- */
    [
        'ad'    => 'Form yer tutucusu e-posta',
        'neden' => 'Kayıt ve başvuru formlarında kullanıcıya örnek gösterilen adres; kimseye ait değil, teslim edilmiş bir kutu yok.',
        'bolum' => ['eposta'],
        'deger' => '~^(ornek|örnek|hakem|yazar|editor|ad|eposta|email|isim|test|user|kullanici)@~i',
    ],
    [
        'ad'    => 'ornek.org / site.com gibi örnek alan adları',
        'neden' => 'RFC ve yaygın örnek alan adları ile Türkçe karşılıkları; gerçek posta sunucusu yok.',
        'bolum' => ['eposta'],
        'deger' => '~@(ornek\.org|örnek\.org|site\.com|eposta\.com|example\.(com|org|net)|universite\.edu(\.tr)?|domain\.com)$~i',
    ],
    [
        'ad'    => 'ORCID yer tutucusu 0000-0000-0000-0000',
        'neden' => 'Boş ORCID alanının gösterim biçimi; bir kişiyi göstermez, ORCID kaydı olarak da geçersizdir.',
        'bolum' => ['*'],
        'satir' => '~0000-0000-0000-0000~',
        'deger' => '~^0+$|^0000~',
    ],
    [
        'ad'    => 'BotFather örnek belirteci',
        'neden' => 'TELEGRAM-KURULUM.md içinde Telegram belgelerinden alınan örnek; 8123456789 diye bir bot yok, xxx dolgusu zaten geçersiz.',
        'bolum' => ['anahtar'],
        'deger' => '~^8123456789:|x{4,}~i',
    ],
    [
        'ad'    => 'Kurulum belgesindeki örnek anahtarlar',
        'neden' => 'Kurulum kılavuzları anahtarın biçimini göstermek zorunda; dosya adı KURULUM içeriyorsa ve değer xxx/YOUR/BURAYA dolgusu taşıyorsa gerçek değildir.',
        'bolum' => ['anahtar', 'atama', 'entropi', 'hex32'],
        'dosya' => '~KURULUM|OKUBENI|README~i',
        'deger' => '~x{4,}|YOUR_|BURAYA|DEGISTIR|<[a-z_]+>~i',
    ],

    /* ---- Üçüncü taraf düzenleyici (Jodit) ---- */
    [
        'ad'    => 'Jodit SVG yol verisi ve CSS değişken adları',
        'neden' => 'jodit.min.js/css içindeki uzun dizeler simge çizim koordinatları ve --jd-* değişken adlarıdır; küçültülmüş dosyada rastgele görünürler, sır değildirler.',
        'bolum' => ['entropi', 'hex32'],
        'dosya' => '~jodit\.min\.(js|css)$~',
    ],
    [
        'ad'    => 'Gömülü base64 görsel',
        'neden' => 'data: URI ile gömülmüş simge/görsel; uzun ve düzensiz görünür ama içeriği piksel, anahtar değil.',
        'bolum' => ['entropi', 'hex32'],
        'satir' => '~data:(image|font)/[a-z0-9.+-]+;base64~i',
    ],
    [
        'ad'    => 'Alfabe sabitleri (base32 / base62 harf kümesi)',
        'neden' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567 gibi diziler kodlama alfabesidir; her harf bir kez geçtiği için düzensizliği en yüksek değerdedir ama içinde bilgi yoktur. Sıralı harf/rakam dizisi rastgele üretilmiş bir anahtarda görülmez.',
        'bolum' => ['entropi'],
        'deger' => '~ABCDEFG|abcdefg|0123456|1234567~',
    ],
    [
        'ad'    => 'Yol ve ad alanı parçaları',
        'neden' => '/k/yazitipi/kutadgu-serif-700, org/2001/XMLSchema-instance gibi diziler eğik çizgiyle ayrılmış sözcüklerdir; hiçbir parçası 24 karakteri bulmaz, yani rastgele bir blok değil bir adrestir.',
        'bolum' => ['entropi'],
        'deger' => '~^[^/]{0,23}(/[^/]{0,23})+$~',
    ],
    [
        'ad'    => 'SVG yol verisi (d="..." ve viewBox)',
        'neden' => 'Vektör çizim koordinatları harf ve rakam karışımıdır, entropi ölçütünü doldurur; taşıdığı bilgi şekildir.',
        'bolum' => ['entropi'],
        'satir' => '~<(path|polygon|polyline)\b|\sd="[Mm]~',
    ],
    [
        'ad'    => 'Üçüncü taraf lisans metinlerindeki yazar adresleri',
        'neden' => 'chupurnov@gmail.com (Jodit yazarı) ve fabian@debian.org (Caladea paketleyicisi) lisansın parçası olarak taşınır; silmek lisansı bozar, sızıntı değildir.',
        'bolum' => ['eposta'],
        'deger' => '~^(chupurnov@gmail\.com|fabian@debian\.org)$~i',
    ],
    [
        'ad'    => 'Lisans ve üçüncü taraf belge dosyalarındaki adresler',
        'neden' => 'LICENSE/LISANS dosyalarındaki her adres telif sahibinin künyesidir; depoya bizim koyduğumuz sır değildir.',
        'bolum' => ['eposta'],
        'dosya' => '~(LICENSE|LISANS|COPYING|OFL)~i',
    ],

    /* ---- Bilerek yayımlanan ---- */
    [
        'ad'    => 'behic.net',
        'neden' => 'Kurucunun kendi yayımladığı kişisel sitesi; künyede ve iletişim sayfasında bilerek duruyor.',
        'bolum' => ['eposta', 'entropi'],
        'deger' => '~behic\.net~i',
    ],
    [
        'ad'    => 'YÖK profil bağlantısındaki authorId',
        'neden' => 'akademik.yok.gov.tr adresindeki B8D30384229272FA onaltılık kimliğin içindeki 11 haneli rakam dizisi TC desenine benzer; TC değildir, herkese açık profil kimliğidir.',
        'bolum' => ['tc', 'telefon', 'hex32', 'entropi'],
        'satir' => '~authorId=~i',
    ],
    [
        'ad'    => 'Geri döngü ve dinleme adresleri',
        'neden' => '127.0.0.1 ve 0.0.0.0 makinenin kendisidir; kimin ağında olduğunu ele vermez, geliştirme kodunda olağandır.',
        'bolum' => ['ip'],
        'deger' => '~^(127\.0\.0\.1|0\.0\.0\.0)$~',
    ],
    [
        'ad'    => 'Kurumsal ev dizinleri',
        'neden' => '/home/kutadgu sunucudaki hizmet hesabı, /home/claude çalışma ortamıdır; ikisi de bir kişiyi adlandırmaz. Kişi adı taşıyan yollar (bkz. 13. bölüm, 13.4 kapısı) elenmez.',
        'bolum' => ['yol'],
        'deger' => '~^/home/(kutadgu|claude|www-data|ubuntu|root)\b~',
    ],
    [
        'ad'    => 'TAMGA/DOI ön eki 10.00001',
        'neden' => 'Yayın kimliği ön eki; özel ağ adresine benzer bir başlangıcı var ama dört sekizli değil, IP olamaz.',
        'bolum' => ['ip'],
        'deger' => '~^10\.0{4}~',
    ],
    [
        'ad'    => 'Değişkene bağlanmış atama',
        'neden' => "parola => \$x, secret=' . \$secret gibi satırlarda tırnak içindeki metin sabit değil, birleştirmenin parçasıdır; gizli değer kodda değildir.",
        'bolum' => ['atama'],
        'deger' => '~\$[A-Za-z_]|\{\$|<\?|\?>~',
    ],
    [
        'ad'    => 'Boş ve dolgu parola değerleri',
        'neden' => "'', 'bos', 'xxx', '...' gibi değerler bir sır taşımaz; alanın var olduğunu gösterir.",
        'bolum' => ['atama'],
        'deger' => '~^(bos|bo\x{15F}|yok|none|null|empty|xxx+|\.{3,}|-+|\*+|degistir|de\x{11F}i\x{15F}tir)$~iu',
    ],
    [
        'ad'    => "Kayıt alanı adı olarak 'anahtar'",
        'neden' => "Türkçede anahtar hem sır hem kayıt ALANI demektir; ['anahtar' => 'lisans'] bir denetim kaydının hangi alanı değiştirdiğini söyler. Yalnız değer noktalamasız, rakamsız, küçük harfli tek sözcükse elenir: 'anahtar' => 'Ab3xK9...' elenmez.",
        'bolum' => ['atama'],
        'satir' => '~[\'"]anahtar[\'"]\s*=>~',
        'deger' => '~^[a-z\x{E7}\x{11F}\x{131}\x{F6}\x{15F}\x{FC}]{2,16}$~u',
    ],
    [
        'ad'    => 'Arayüz etiketi olarak parola sözcüğü',
        'neden' => "'parola' => 'Parola', 'type' => 'password' gibi satırlar ekranda görünen etiket ya da HTML alan türüdür.",
        'bolum' => ['atama'],
        'deger' => '~^(parola|\x{15F}ifre|sifre|password|passwd|token|secret|anahtar|api_key|apikey)$~iu',
    ],

    /* ---- İkili ve yayımlanmak üzere üretilmiş dosyalar ---- */
    [
        'ad'    => 'İkili dosyalar',
        'neden' => 'png/jpg/gif/ttf/woff/zip/pdf/ico/webp/mp4/ogg: metin taraması bunlarda yalnız gürültü üretir, eşleşmeleri de okunamaz. Tümü ATLANAN sayısına yazılır.',
        'bolum' => ['ikili'],
        'dosya' => '~\.(png|jpe?g|gif|ttf|otf|eot|woff2?|zip|gz|tgz|pdf|ico|webp|mp4|ogg|mp3|wav|avif|bmp|tif|tiff)$~i',
    ],
    [
        'ad'    => 'Yazı tipi ve marka dizinleri',
        'neden' => 'k/yazitipi/ ve k/marka/ altındaki her şey üretilmiş varlıktır (yüz dosyası, logo); içlerinde sır olmaz, olsa da metin olarak okunamaz.',
        'bolum' => ['ikili'],
        'dosya' => '~(^|/)k/(yazitipi|marka)/~',
    ],
];

/* İkili sayılan uzantılar ve dizinler yukarıdaki BEYAZ listesinden
   ('ikili' bölümü) okunur; ikinci bir liste tutmak ikisinin ayrışması
   demekti. Tarama dışı bırakılan altyapı dizinleri ise burada: */
const ATLANAN_DIZIN = ['.git', 'node_modules', '.venv', 'venv', '__pycache__'];

/* Tek dosya için üst sınır. Bunun üstü ya derlenmiş varlıktır ya da
   veri dökümü; ikisi de satır satır taranacak şey değil. */
const AZAMI_BOYUT = 6 * 1024 * 1024;

/* =====================================================================
   Ölçüm altyapısı
   ===================================================================== */
$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

$SAYAC = [
    'dosya'    => 0,   // taranan metin dosyası
    'satir'    => 0,   // taranan satır
    'ikili'    => 0,   // ikili diye atlanan
    'buyuk'    => 0,   // boyut sınırını aşan
    'beyaz'    => 0,   // beyaz listeye takılan eşleşme
    'gecmis'   => 0,   // git geçmişinde taranan eklenen satır
];

/* Bulguyu bağlamıyla basar. Bağlamsız eşleşme basılmaz: "3 e-posta
   bulundu" cümlesi kimseyi bir yere götürmez, dosya ve satır götürür. */
function kirp(string $s, int $n = 90): string {
    $s = preg_replace('~[\x00-\x1F\x7F]+~u', ' ', $s) ?? $s;
    $s = trim($s);
    if (function_exists('mb_strlen') && mb_check_encoding($s, 'UTF-8')) {
        if (mb_strlen($s) > $n) return mb_substr($s, 0, $n) . '...';
        return $s;
    }
    return strlen($s) > $n ? substr($s, 0, $n) . '...' : $s;
}

function bulgu_bas(array $bulgular, int $azami = 30): void {
    $n = count($bulgular);
    foreach (array_slice($bulgular, 0, $azami) as $b) {
        echo sprintf("         %s:%d  %s\n", $b['dosya'], $b['satir'], kirp($b['deger']));
    }
    if ($n > $azami) echo sprintf("         ... ve %d bulgu daha\n", $n - $azami);
}

/* Beyaz liste kararı. Elenen her eşleşme sayılır. */
function beyazda_mi(string $bolum, string $yol, string $satir, string $deger, ?string &$ad = null): bool {
    global $SAYAC;
    foreach (BEYAZ as $b) {
        if (!in_array('*', $b['bolum'], true) && !in_array($bolum, $b['bolum'], true)) continue;
        if (isset($b['dosya']) && !preg_match($b['dosya'], $yol))  continue;
        if (isset($b['satir']) && !preg_match($b['satir'], $satir)) continue;
        if (isset($b['deger']) && !preg_match($b['deger'], $deger)) continue;
        $ad = $b['ad'];
        if ($bolum !== 'ikili') $SAYAC['beyaz']++;
        return true;
    }
    return false;
}

/* =====================================================================
   Desenler
   ---------------------------------------------------------------------
   'bolum' beyaz listenin hangi maddelerinin uygulanacağını belirler.
   Her desen kendi den() satırını üretir: bir bayrak yanınca hangisinin
   yandığı tek bakışta görünsün diye.
   ===================================================================== */
function desenler(): array {
    return [
        // ---- 2. bölüm: doğrudan anahtar biçimleri ----
        ['anahtar', 'Telegram bot belirteci',        '~(?<![\w:])\d{8,10}:AA[A-Za-z0-9_-]{30,40}~'],
        ['anahtar', 'Özel anahtar (PEM) başlığı',    '~-----BEGIN [A-Z ]*PRIVATE KEY-----~'],
        ['anahtar', 'AWS erişim anahtarı',           '~\b(AKIA|ASIA)[0-9A-Z]{16}\b~'],
        ['anahtar', 'GitHub belirteci',              '~\b(ghp_|gho_|ghu_|ghs_|ghr_|github_pat_)[A-Za-z0-9_]{20,}~'],
        ['anahtar', 'OpenAI / Anthropic anahtarı',   '~\bsk-(ant-)?[A-Za-z0-9_-]{24,}~'],
        ['anahtar', 'Google API anahtarı',           '~\bAIza[0-9A-Za-z_-]{35}~'],
        ['anahtar', 'Slack belirteci',               '~\bxox[abposr]-[A-Za-z0-9-]{10,}~'],
        ['anahtar', 'JWT (eyJ... üç parçalı)',       '~\beyJ[A-Za-z0-9_-]{8,}\.eyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}~'],

        // ---- 3. bölüm: gizli değer ataması ----
        /* Anahtar sözcükten sonra kapanış tırnağı serbest bırakıldı:
           PHP dizisinde alan adı tırnak içindedir ('parola' => '...'),
           bunu kaçıran bir kalıp tam da aradığımız biçimi kaçırırdı. */
        ['atama',   'Kodda gizli değer ataması',
         '~\b(sifre|\x{15F}ifre|parola|password|passwd|pwd|secret|gizli|token|jeton|api_?key|apikey|access_key|private_key|anahtar|auth_?key)\b[\'"`\]]*\s*(=>|=|:)\s*[\'"]([^\'"\r\n]{4,})[\'"]~iu', 3],

        // ---- 4. bölüm: veritabanı bağlantısı ----
        ['vt',      'Veritabanı bağlantı dizesi (şema://kullanici:parola@)',
         '~\b(mysql|mysqli|pgsql|postgres(?:ql)?|mongodb(?:\+srv)?|redis|sqlsrv|mssql)://[^\s\'"<>]{4,}~i'],
        ['vt',      'PDO/DSN bağlantı dizesi',
         '~\b(mysql|pgsql|sqlsrv):host=[^\s\'"]{2,}~i'],
        ['vt',      'Veritabanı kimlik değişkenleri',
         '~\b(DB_(HOST|USER(NAME)?|PASS(WORD)?|NAME|PORT)|DATABASE_URL|MYSQL_(USER|PASSWORD|ROOT_PASSWORD))\b\s*[=:]\s*\S+~'],

        // ---- 5. bölüm: kişisel veri ----
        ['eposta',  'E-posta adresi',                '~[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,24}~'],
        ['telefon', 'Türkiye telefon numarası',      '~(?<![\d/.])(?:\+90|00\s?90|\b0)[ .\-]?\(?(?:5\d{2}|2\d{2}|3\d{2}|4\d{2})\)?[ .\-]?\d{3}[ .\-]?\d{2}[ .\-]?\d{2}(?![\d/.])~'],

        // ---- 6. bölüm: yol ve ağ izleri ----
        ['yol',     'Windows kullanıcı dizini',      '~[Cc]:\\\\Users\\\\[A-Za-z0-9._\- ]+~'],
        ['yol',     'Unix ev dizini (/home/<ad>)',   '~/home/[a-z][a-z0-9._\-]{1,31}(?=[/\s\'"]|$)~'],
        ['yol',     'macOS ev dizini (/Users/<ad>)', '~/Users/[A-Za-z0-9._\-]{1,31}(?=[/\s\'"]|$)~'],
        ['ip',      'Özel ağ adresi',
         '~(?<![\d.])(?:10\.\d{1,3}\.\d{1,3}\.\d{1,3}|192\.168\.\d{1,3}\.\d{1,3}|172\.(?:1[6-9]|2\d|3[01])\.\d{1,3}\.\d{1,3}|127\.0\.0\.1|0\.0\.0\.0)(?![\d.])~'],
    ];
}

/* TC kimlik numarası: 11 hane, ilki sıfır olamaz, iki denetim hanesi
   tutmak zorundadır. Denetimi uygulamak yanlış alarmı büyük ölçüde
   keser; tutmayanlar yine sayılır ve sayısı yazılır ki eşik körelirse
   görülsün. */
function tc_gecerli(string $t): bool {
    if (strlen($t) !== 11 || $t[0] === '0') return false;
    $d = array_map('intval', str_split($t));
    $tek  = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
    $cift = $d[1] + $d[3] + $d[5] + $d[7];
    if ((($tek * 7) - $cift) % 10 !== $d[9]) return false;
    return (array_sum(array_slice($d, 0, 10)) % 10) === $d[10];
}

/* Shannon düzensizliği: rastgele üretilmiş bir dizenin harf dağılımı
   düzdür, insan yazımı bir tanımlayıcınınki değildir. */
function duzensizlik(string $s): float {
    $n = strlen($s);
    if ($n === 0) return 0.0;
    $say = count_chars($s, 1);
    $h = 0.0;
    foreach ($say as $k) { $p = $k / $n; $h -= $p * log($p, 2); }
    return $h;
}

/* =====================================================================
   Dosya toplama
   ===================================================================== */
function dosyalari_topla(string $kok): array {
    global $SAYAC;
    /* Liste bir kez kurulur ve saklanır: ikinci bir gezinti hem süreyi
       ikiye katlar hem de "atlanan ikili" sayacını ikiye katlayarak
       ölçümü yalan söyletirdi. */
    static $onbellek = [];
    if (isset($onbellek[$kok])) return $onbellek[$kok];
    $liste = [];
    $yig = [$kok];
    while ($yig) {
        $d = array_pop($yig);
        $el = @scandir($d);
        if ($el === false) continue;
        foreach ($el as $ad) {
            if ($ad === '.' || $ad === '..') continue;
            $tam = $d . '/' . $ad;
            if (is_link($tam)) continue;
            if (is_dir($tam)) {
                if (in_array($ad, ATLANAN_DIZIN, true)) continue;
                $yig[] = $tam;
                continue;
            }
            if (!is_file($tam)) continue;
            $bag = ltrim(substr($tam, strlen($kok)), '/');
            if (beyazda_mi('ikili', $bag, '', '')) { $SAYAC['ikili']++; continue; }
            $boy = @filesize($tam);
            if ($boy === false) continue;
            if ($boy > AZAMI_BOYUT) { $SAYAC['buyuk']++; continue; }
            /* Uzantısı metin görünen ama içi ikili olan dosyalar: ilk
               8 KB'de NUL varsa metin değildir. */
            $bas = @file_get_contents($tam, false, null, 0, 8192);
            if ($bas === false) continue;
            if (strpos($bas, "\0") !== false) { $SAYAC['ikili']++; continue; }
            $liste[] = [$tam, $bag];
        }
    }
    sort($liste);
    $onbellek[$kok] = $liste;
    return $liste;
}

/* =====================================================================
   Çalışma ağacı taraması
   ===================================================================== */
function agaci_tara(string $kok): array {
    global $SAYAC;
    $desen  = desenler();
    $bulgu  = [];            // ad => [bulgu, ...]
    foreach ($desen as $d) $bulgu[$d[1]] = [];
    $bulgu['TC kimlik numarası']            = [];
    $bulgu['32 haneli onaltılık dize']      = [];
    $bulgu['Yüksek düzensizlik']            = [];
    $bulgu['.env değişkeni']                = [];
    $tc_denetimsiz = 0;

    foreach (dosyalari_topla($kok) as [$tam, $bag]) {
        $SAYAC['dosya']++;
        $env_mi = (bool)preg_match('~(^|/)\.env(\.|$)|\.env$~', $bag);
        $fh = @fopen($tam, 'r');
        if (!$fh) continue;
        $no = 0;
        while (($satir = fgets($fh)) !== false) {
            $no++;
            $SAYAC['satir']++;
            $satir = rtrim($satir, "\r\n");
            if ($satir === '') continue;

            foreach ($desen as $d) {
                [$bolum, $ad, $re] = $d;
                $grup = $d[3] ?? 0;
                if (!preg_match_all($re, $satir, $m, PREG_SET_ORDER)) continue;
                foreach ($m as $set) {
                    $deger = $set[$grup] ?? $set[0];
                    if ($deger === '') continue;
                    if (beyazda_mi($bolum, $bag, $satir, $deger)) continue;
                    $bulgu[$ad][] = ['dosya' => $bag, 'satir' => $no, 'deger' => $set[0]];
                }
            }

            /* ---- TC kimlik ----
               Harf komşuluğu bilerek serbest bırakıldı: YÖK authorId
               gibi onaltılık kimliklerin içindeki 11 haneli rakam dizisi
               de yakalansın, beyaz listeye TAKILSIN ve sayılsın. */
            if (preg_match_all('~(?<!\d)[1-9]\d{10}(?!\d)~', $satir, $m)) {
                foreach ($m[0] as $t) {
                    if (beyazda_mi('tc', $bag, $satir, $t)) continue;
                    if (!tc_gecerli($t)) { $tc_denetimsiz++; continue; }
                    $bulgu['TC kimlik numarası'][] = ['dosya' => $bag, 'satir' => $no, 'deger' => $t];
                }
            }

            /* ---- 32 haneli saf onaltılık ----
               Birinci geçişte "md5/sha özeti, zararsız" diye elenmişti;
               tam o kutudan canlı bir IndexNow anahtarı çıktı. Bir daha
               elenmiyor, ayrı başlık altında insan gözüne veriliyor. */
            if (preg_match_all('~(?<![0-9A-Za-z])[0-9a-fA-F]{32}(?![0-9A-Za-z])~', $satir, $m)) {
                foreach ($m[0] as $h) {
                    if (beyazda_mi('hex32', $bag, $satir, $h)) continue;
                    $bulgu['32 haneli onaltılık dize'][] = ['dosya' => $bag, 'satir' => $no, 'deger' => $h];
                }
            }

            /* ---- Yüksek düzensizlik ----
               20 karakterden uzun base64/onaltılık görünümlü diziler. */
            if (preg_match_all('~[A-Za-z0-9+/=_-]{20,}~', $satir, $m)) {
                foreach ($m[0] as $t) {
                    if (strlen($t) < 24) continue;
                    /* Kod tanımlayıcıları elenir: yalnız harf ve alt
                       çizgi taşıyan uzun ad bir sır değil, bir işlev
                       adıdır. Sır olabilmesi için hem rakam hem harf
                       gerekir. */
                    if (!preg_match('~\d~', $t) || !preg_match('~[A-Za-z]~', $t)) continue;
                    if (preg_match('~^[a-z]+(_[a-z0-9]+)+$~', $t)) continue;
                    if (duzensizlik($t) < 4.0) continue;
                    if (beyazda_mi('entropi', $bag, $satir, $t)) continue;
                    $bulgu['Yüksek düzensizlik'][] = ['dosya' => $bag, 'satir' => $no, 'deger' => $t];
                }
            }

            /* ---- .env değişkenleri ----
               .env dosyasının deponun içinde olması başlı başına bir
               bulgudur; içindeki her atama yazılır. */
            if ($env_mi && preg_match('~^\s*(?:export\s+)?([A-Z][A-Z0-9_]{2,})\s*=\s*(\S.*)$~', $satir, $m)) {
                if (!beyazda_mi('env', $bag, $satir, $m[1])) {
                    $bulgu['.env değişkeni'][] = ['dosya' => $bag, 'satir' => $no, 'deger' => $m[1] . '=' . kirp($m[2], 40)];
                }
            }
        }
        fclose($fh);
    }
    $bulgu['__tc_denetimsiz'] = $tc_denetimsiz;
    return $bulgu;
}

/* =====================================================================
   Git geçmişi taraması (yalnız KGIT verilirse)
   ---------------------------------------------------------------------
   Yalnız EKLENEN satırlara bakılır: silinen bir satır zaten geçmişte
   duruyor demektir, onu eklendiği commit'te yakalarız. Bütün desenler
   değil, geçmişte gerçekten anlamı olanlar taranır (anahtar, atama,
   e-posta, yol, 32 hane): geçmiş taraması zaten yavaş, entropi taraması
   onu kullanılamaz hâle getirirdi.
   ===================================================================== */
function gecmisi_tara(string $git): array {
    global $SAYAC;
    $desen = array_values(array_filter(desenler(), static fn($d) => in_array($d[0], ['anahtar', 'atama', 'eposta', 'yol', 'vt'], true)));
    $bulgu = [];
    $komut = 'git -C ' . escapeshellarg($git) . ' log --all --no-color --no-renames -p -U0 --format=%x01%H 2>/dev/null';
    $fh = popen($komut, 'r');
    if (!$fh) return ['__hata' => 'git çalıştırılamadı'];
    $sha = '?'; $dosya = '?';
    while (($satir = fgets($fh)) !== false) {
        $satir = rtrim($satir, "\r\n");
        if ($satir === '') continue;
        if ($satir[0] === "\x01") { $sha = substr($satir, 1, 8); continue; }
        if (str_starts_with($satir, '+++ b/')) { $dosya = substr($satir, 6); continue; }
        if ($satir[0] !== '+' || str_starts_with($satir, '+++')) continue;
        $govde = substr($satir, 1);
        if ($govde === '') continue;
        $SAYAC['gecmis']++;
        /* Geçmişte de ikili dosya farkı taranmaz. */
        if (beyazda_mi('ikili', $dosya, '', '')) continue;
        foreach ($desen as $d) {
            [$bolum, $ad, $re] = $d;
            $grup = $d[3] ?? 0;
            if (!preg_match_all($re, $govde, $m, PREG_SET_ORDER)) continue;
            foreach ($m as $set) {
                $deger = $set[$grup] ?? $set[0];
                if ($deger === '' || beyazda_mi($bolum, $dosya, $govde, $deger)) continue;
                $anah = $ad . "\x00" . $set[0] . "\x00" . $dosya;
                if (isset($bulgu[$anah])) continue;   // aynı değer her commit'te yeniden basılmasın
                $bulgu[$anah] = ['ad' => $ad, 'dosya' => $dosya, 'sha' => $sha, 'deger' => $set[0]];
            }
        }
        if (preg_match_all('~(?<![0-9A-Za-z])[0-9a-fA-F]{32}(?![0-9A-Za-z])~', $govde, $m)) {
            foreach ($m[0] as $h) {
                if (beyazda_mi('hex32', $dosya, $govde, $h)) continue;
                $anah = "32 hane\x00" . $h . "\x00" . $dosya;
                if (isset($bulgu[$anah])) continue;
                $bulgu[$anah] = ['ad' => '32 haneli onaltılık dize', 'dosya' => $dosya, 'sha' => $sha, 'deger' => $h];
            }
        }
    }
    pclose($fh);
    return $bulgu;
}

/* =====================================================================
   Yardımcı: dosya oku
   ===================================================================== */
function oku(string $y): string {
    return is_file($y) ? (string)@file_get_contents($y) : '';
}

/* =====================================================================
   ANA AKIŞ
   ===================================================================== */
$KOK = $argv[1] ?? (getenv('KREPO') ?: '/home/claude/kg/kutadgunet');
$KOK = rtrim($KOK, '/');
$GIT = getenv('KGIT') ?: '';

if (!is_dir($KOK)) {
    fwrite(STDERR, "Hedef dizin yok: $KOK\n");
    exit(2);
}

echo "\n";
echo "=====================================================================\n";
echo "  SIR KAPISI\n";
echo "  Hedef : $KOK\n";
echo "  Geçmiş: " . ($GIT !== '' ? $GIT : '(taranmıyor; KGIT verilmedi)') . "\n";
echo "  Zaman : " . date('Y-m-d H:i:s') . "\n";
echo "=====================================================================\n";

$t0 = microtime(true);
$B  = agaci_tara($KOK);
$t_agac = microtime(true) - $t0;

echo "\n== 1. Tarama kapsamı ==\n";
printf("  taranan metin dosyası : %d\n", $SAYAC['dosya']);
printf("  taranan satır         : %d\n", $SAYAC['satir']);
printf("  atlanan ikili dosya   : %d   (uzantı listesi + k/yazitipi + k/marka + NUL baytı olan)\n", $SAYAC['ikili']);
printf("  atlanan büyük dosya   : %d   (> %d MB)\n", $SAYAC['buyuk'], (int)(AZAMI_BOYUT / 1048576));
printf("  süre                  : %.2f sn\n", $t_agac);
den('En az bir dosya tarandı', $SAYAC['dosya'] > 0, 'hedef boş ya da okunamıyor');

/* ---- 2..9. bölümler: desen bulguları ---- */
$desen = desenler();
$bolum_yaz = static function (string $bolum, string $baslik) use ($desen, $B): void {
    echo "\n== $baslik ==\n";
    foreach ($desen as $d) {
        if ($d[0] !== $bolum) continue;
        $ad = $d[1];
        $n  = count($B[$ad]);
        den("$ad yok", $n === 0, "$n bulgu");
        if ($n) bulgu_bas($B[$ad]);
    }
};

$bolum_yaz('anahtar', '2. Doğrudan anahtar biçimleri');
$bolum_yaz('atama',   '3. Kodda gizli değer ataması');
$bolum_yaz('vt',      '4. Veritabanı bağlantısı');
$bolum_yaz('eposta',  '5. Kişisel veri: e-posta');
$bolum_yaz('telefon', '6. Kişisel veri: Türkiye telefon numarası');

echo "\n== 7. Kişisel veri: TC kimlik ==\n";
printf("  denetim hanesi tutmayan 11 haneli dizi (sayıldı, TC değil): %d\n", $B['__tc_denetimsiz']);
$n = count($B['TC kimlik numarası']);
den('Geçerli TC kimlik numarası yok', $n === 0, "$n bulgu");
if ($n) bulgu_bas($B['TC kimlik numarası']);

$bolum_yaz('yol', '8. Yerel yol izleri');
$bolum_yaz('ip',  '9. Özel ağ adresi');

echo "\n== 10. .env değişkenleri ==\n";
$n = count($B['.env değişkeni']);
den('Depoda .env değişkeni yok', $n === 0, "$n bulgu");
if ($n) bulgu_bas($B['.env değişkeni']);

echo "\n== 11. 32 haneli onaltılık dizeler (insan gözü baksın) ==\n";
echo "  Bunlar md5/sha özeti OLABİLİR, ama canlı IndexNow anahtarı da tam\n";
echo "  bu biçimdeydi ve birinci geçişte 'zararsız özet' diye elenmişti.\n";
echo "  Her biri elle bakılmadan kapı geçmez.\n";
$n = count($B['32 haneli onaltılık dize']);
den('Elle bakılmamış 32 haneli onaltılık dize yok', $n === 0, "$n bulgu");
if ($n) bulgu_bas($B['32 haneli onaltılık dize'], 40);

echo "\n== 12. Yüksek düzensizlik (entropi >= 4.0, uzunluk >= 24) ==\n";
$n = count($B['Yüksek düzensizlik']);
den('Yüksek düzensizlikli dize yok', $n === 0, "$n bulgu");
if ($n) bulgu_bas($B['Yüksek düzensizlik'], 25);

/* =====================================================================
   13. ÖZEL KAPILAR
   Bunlar desen taraması değil, bu depoya özgü sözlerdir. Her biri
   gerçek bir olaydan doğdu; sözün tutulduğu tek tek ölçülür.
   ===================================================================== */
echo "\n== 13. Özel kapılar ==\n";

/* -- 13.1  ayar.php içinde gerçek e-posta olmamalı --
   Kurucuların adresleri veri dizinine taşınıyor. Yer tutucu ve boş
   dize serbest; taşınma geri alınırsa burası yanar. */
$ayar = $KOK . '/ayar.php';
$ayar_bulgu = [];
if (is_file($ayar)) {
    foreach (file($ayar, FILE_IGNORE_NEW_LINES) ?: [] as $i => $s) {
        if (!preg_match_all('~[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,24}~', $s, $m)) continue;
        foreach ($m[0] as $e) {
            if (beyazda_mi('eposta', 'ayar.php', $s, $e)) continue;
            $ayar_bulgu[] = ['dosya' => 'ayar.php', 'satir' => $i + 1, 'deger' => $e];
        }
    }
    den('13.1  ayar.php içinde gerçek e-posta yok', count($ayar_bulgu) === 0, count($ayar_bulgu) . ' adres');
    if ($ayar_bulgu) bulgu_bas($ayar_bulgu);
} else {
    den('13.1  ayar.php içinde gerçek e-posta yok', false, 'ayar.php bulunamadı');
}

/* -- 13.2  kodda düz yazılı yönetici parolası olmamalı --
   password_hash('sabit') ve parola => 'sabit'. Karma üretmek parolayı
   gizlemez: karmanın girdisi kodda duruyorsa parola kodda duruyordur. */
$parola_bulgu = [];
foreach (dosyalari_topla($KOK) as [$tam, $bag]) {
    $fh = @fopen($tam, 'r'); if (!$fh) continue;
    $no = 0;
    while (($s = fgets($fh)) !== false) {
        $no++;
        if (preg_match_all('~password_hash\s*\(\s*[\'"]([^\'"\r\n]{1,})[\'"]~i', $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $set) {
                if (beyazda_mi('atama', $bag, $s, $set[1])) continue;
                $parola_bulgu[] = ['dosya' => $bag, 'satir' => $no, 'deger' => trim($set[0])];
            }
        }
        if (preg_match_all('~\b(parola|\x{15F}ifre|sifre|password)\b[\'"`\]]*\s*(=>|=)\s*[\'"]([^\'"\r\n]{4,})[\'"]~iu', $s, $m, PREG_SET_ORDER)) {
            foreach ($m as $set) {
                if (beyazda_mi('atama', $bag, $s, $set[3])) continue;
                $parola_bulgu[] = ['dosya' => $bag, 'satir' => $no, 'deger' => trim($set[0])];
            }
        }
    }
    fclose($fh);
}
den('13.2  Kodda düz yazılı parola yok', count($parola_bulgu) === 0, count($parola_bulgu) . ' bulgu');
if ($parola_bulgu) bulgu_bas($parola_bulgu);

/* -- 13.3  api/kb-tohum.json depoda olmamalı -- */
den('13.3  api/kb-tohum.json depoda yok', !is_file($KOK . '/api/kb-tohum.json'), 'dosya duruyor');

/* -- 13.4  /home/<kişi-adı>/ biçiminde yol olmamalı --
   KARAR: /home/kutadgu (sunucudaki hizmet hesabı), /home/claude
   (çalışma ortamı), /home/www-data, /home/ubuntu, /home/root kurumsaldır
   ve serbesttir; bunlar bir kişiyi adlandırmaz, sunucunun kendi
   düzenidir ve halka açık depoda kimsenin mahremiyetini açmaz. Kişi
   adı taşıyan her yol (/home/behic gibi) yasaktır: hem geliştiricinin
   makinesindeki düzeni ele verir, hem de o yol sunucuda yoktur, yani
   kod da yanlıştır. Karar beyaz listede 'Kurumsal ev dizinleri'
   maddesinde de yazılıdır. */
$yol_bulgu = array_values(array_filter(
    $B['Unix ev dizini (/home/<ad>)'],
    static fn($b) => !preg_match('~^/home/(kutadgu|claude|www-data|ubuntu|root)\b~', $b['deger'])
));
den('13.4  Kişi adı taşıyan /home/<ad>/ yolu yok', count($yol_bulgu) === 0, count($yol_bulgu) . ' bulgu');
if ($yol_bulgu) bulgu_bas($yol_bulgu);

/* -- 13.5  .gitignore kapsamı --
   Bir kalıp düşerse kapı yanar: .gitignore'dan bir satırın silinmesi
   sessiz bir olaydır, sonucu ilk sızıntıda görülür. */
$gi = oku($KOK . '/.gitignore');
$gi_satir = array_map('trim', explode("\n", $gi));
$eksik = [];
foreach (['data/', '.env', '*.key', '*.pem', '*.log'] as $k) {
    if (!in_array($k, $gi_satir, true)) $eksik[] = $k;
}
den('13.5  .gitignore gerekli kalıpları kapsıyor', $gi !== '' && !$eksik,
    $gi === '' ? '.gitignore yok' : 'eksik: ' . implode(' ', $eksik));

/* -- 13.6  .htaccess kapsamı --
   Aranan şey kalıbın kendisi değil, kapatılmış olması: bir dosya
   FilesMatch listesinde geçiyorsa yeter. */
$ht = oku($KOK . '/.htaccess');
$ht_bekle = [
    'ayar.php'         => '~<Files(Match)?\s+"[^"]*ayar\\\\?\.php~i',
    'ortak.php'        => '~<Files(Match)?\s+"[^"]*ortak\\\\?\.php~i',
    '.md'              => '~FilesMatch\s+"[^"]*\bmd\b~i',
    '.sh'              => '~FilesMatch\s+"[^"]*\bsh\b~i',
    '.bat'             => '~FilesMatch\s+"[^"]*\bbat\b~i',
    '.json'            => '~FilesMatch\s+"[^"]*\bjson\b~i',
    'nokta ile başlayan' => '~FilesMatch\s+"\^\\\\\.~',
    '/.git'            => '~RedirectMatch\s+\d+\s+/\\\\\.git|<DirectoryMatch[^>]*\\\\\.git~i',
];
$ht_eksik = [];
foreach ($ht_bekle as $ad => $re) if (!preg_match($re, $ht)) $ht_eksik[] = $ad;
den('13.6  .htaccess gerekli yolları kapatıyor', $ht !== '' && !$ht_eksik,
    $ht === '' ? '.htaccess yok' : 'kapatılmamış: ' . implode(', ', $ht_eksik));

/* -- 13.7  Yazı tipi ve üçüncü taraf lisansları --
   Bir yazı tipi ailesi lisans metni olmadan dağıtılamaz; OFL'nin tek
   koşulu budur. Aile adı ya doğrudan bir lisans dosyasında geçmeli, ya
   da OKUBENI'de o aileyi anlatan bölüm var olan bir lisans dosyasına
   gönderme yapmalı (alt küme alınmış Caladea'nın adı dosya adında
   geçmiyor, bu yüzden gönderme de kabul ediliyor). */
$yt = $KOK . '/k/yazitipi';
$aileler = []; $lisanslar = []; $belge = '';
if (is_dir($yt)) {
    foreach (scandir($yt) ?: [] as $ad) {
        if ($ad === '.' || $ad === '..') continue;
        if (preg_match('~\.(ttf|otf|woff2?|eot)$~i', $ad)) {
            $g = preg_replace('~\.(ttf|otf|woff2?|eot)$~i', '', $ad);
            $g = preg_replace('~[-_](Bold|Italic|Oblique|Regular|Light|Medium|Black|Thin|Semi[Bb]old|Extra[Bb]old|\d{3}i?)$~i', '', (string)$g);
            $aileler[(string)$g] = true;
        } elseif (preg_match('~(lisans|licen[cs]e|ofl|copying|telif)~i', $ad)) {
            $lisanslar[$ad] = oku($yt . '/' . $ad);
        } elseif (preg_match('~(okubeni|readme)~i', $ad)) {
            $belge .= "\n" . oku($yt . '/' . $ad);
        }
    }
}
$yt_eksik = [];
foreach (array_keys($aileler) as $aile) {
    $on = substr($aile, 0, 6);
    $var = false;
    foreach ($lisanslar as $lad => $lic) {
        if (stripos($lad, $aile) !== false || stripos($lad, $on) !== false
         || stripos($lic, $aile) !== false || stripos($lic, $on) !== false) { $var = true; break; }
    }
    if (!$var && $belge !== '') {
        /* OKUBENI bölümleri: aileyi anan bölüm var olan bir lisans
           dosyasının adını da anıyorsa lisans o aile için taşınıyordur. */
        foreach (preg_split('~^##\s~m', $belge) ?: [] as $bl) {
            if (stripos($bl, $aile) === false && stripos($bl, $on) === false) continue;
            foreach (array_keys($lisanslar) as $lad) {
                if (stripos($bl, $lad) !== false) { $var = true; break 2; }
            }
        }
    }
    if (!$var) $yt_eksik[] = $aile;
}
den('13.7a Her yazı tipi ailesinin lisansı var',
    is_dir($yt) && $aileler && !$yt_eksik,
    !is_dir($yt) ? 'k/yazitipi yok' : (!$aileler ? 'yazı tipi bulunamadı' : 'lisansı bulunamayan aile: ' . implode(', ', $yt_eksik)));
den('13.7b k/duzen/LICENSE-jodit.txt var', is_file($KOK . '/k/duzen/LICENSE-jodit.txt'), 'dosya yok');

/* -- 13.8  Açık depo belgeleri -- */
den('13.8a SECURITY.md var',     is_file($KOK . '/SECURITY.md'), 'dosya yok');
den('13.8b CONTRIBUTING.md var', is_file($KOK . '/CONTRIBUTING.md'), 'dosya yok');

/* =====================================================================
   14. Git geçmişi (yalnız KGIT verilirse)
   ===================================================================== */
if ($GIT !== '') {
    echo "\n== 14. Git geçmişi ==\n";
    if (!is_dir($GIT . '/.git') && !is_dir($GIT . '/objects')) {
        den('14.  Git geçmişi taranabildi', false, "git deposu değil: $GIT");
    } else {
        $t1 = microtime(true);
        $g  = gecmisi_tara($GIT);
        $t_git = microtime(true) - $t1;
        $sayi = @exec('git -C ' . escapeshellarg($GIT) . ' rev-list --all --count 2>/dev/null');
        printf("  commit sayısı        : %s\n", $sayi !== false && $sayi !== '' ? $sayi : '?');
        printf("  taranan eklenen satır: %d\n", $SAYAC['gecmis']);
        printf("  süre                 : %.2f sn\n", $t_git);
        if (isset($g['__hata'])) {
            den('14.  Git geçmişi taranabildi', false, $g['__hata']);
        } else {
            $grup = [];
            foreach ($g as $b) $grup[$b['ad']][] = $b;
            ksort($grup);
            den('14.  Git geçmişinde sır yok', count($g) === 0, count($g) . ' benzersiz bulgu');
            foreach ($grup as $ad => $liste) {
                printf("       -- %s (%d) --\n", $ad, count($liste));
                foreach (array_slice($liste, 0, 12) as $b) {
                    printf("         %s  %s:  %s\n", $b['sha'], $b['dosya'], kirp($b['deger']));
                }
                if (count($liste) > 12) printf("         ... ve %d bulgu daha\n", count($liste) - 12);
            }
        }
    }
}

/* =====================================================================
   Sonuç
   ===================================================================== */
echo "\n== Sayaçlar ==\n";
printf("  taranan dosya            : %d\n", $SAYAC['dosya']);
printf("  atlanan ikili            : %d\n", $SAYAC['ikili']);
printf("  atlanan büyük dosya      : %d\n", $SAYAC['buyuk']);
printf("  beyaz listeye takılan    : %d   (BEYAZ dizisi: %d madde)\n", $SAYAC['beyaz'], count(BEYAZ));
if ($GIT !== '') printf("  geçmişte taranan satır   : %d\n", $SAYAC['gecmis']);

echo "\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
