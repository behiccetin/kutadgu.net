<?php
/* =====================================================================
   KUTADGU - Ortak sayfa kabuğu / Shared page shell
   Her genel sayfa bunu çağırır: k_bas([...]) ... içerik ... k_son();
   Dil: ?lang=en|tr, yoksa kdil çerezi, yoksa tarayıcı dili, yoksa Türkçe.
   ===================================================================== */

require_once __DIR__ . '/../ortak.php';
require_once __DIR__ . '/kimlik.php';

if (!function_exists('k_dil')) {

    /* Sürüm etiketi ortak.php'de tek bir yerde tutulur; stil ya da betik
       değiştiğinde orada güncellenir. Burada yalnızca ona bağlanılır. */
    define('K_SURUM', TG_SURUM);

    /* Tanımlı diller. Yeni dil eklemek ayar.php'de bir satırdır. */
    function k_diller(): array {
        static $d = null;
        if ($d !== null) return $d;
        $d = (array)tg_ayar('diller', []);
        if (!$d) $d = ['tr' => ['ad' => 'Türkçe', 'yon' => 'ltr', 'ulke' => ['TR'], 'yedek' => 'en'],
                       'en' => ['ad' => 'English', 'yon' => 'ltr', 'ulke' => ['*'], 'yedek' => 'tr']];
        return $d;
    }

    /* Ziyaretçinin ülkesi ortak.php'deki tg_ulke() ile çözülür ve
       k_ulke() onun adıdır. Başlığı okuyan TEK yer orasıdır: hem
       sayfanın dili hem de okuma kaydındaki ülke aynı yerden gelir.
       Eskiden ikisi ayrı ayrı çözüyordu; aynı bilgiyi iki yerde
       üretmek, zamanla iki ayrı yanıt demektir. */

    /* Dil çözümleme sırası:
       1. Adresteki ?lang=            (bağlantıyla gelen açık seçim)
       2. kdil çerezi                 (ziyaretçinin daha önceki seçimi)
       3. Ülke                        (Türkiye'den Türkçe, dışından İngilizce)
       4. Tarayıcı dili               (ülke bilinmiyorsa)
       5. Varsayılan                  (ayar.php)                              */
    /* Bir sayfa dili kendisi belirlediyse (örn. tamga adresi çalışmanın
       yazıldığı dili açar) kabuk de aynı dili kullanmalıdır. */
    function k_dil_zorla(string $dil): void { $GLOBALS['__k_dil_zorla'] = $dil; }
    function k_dil(): string {
        static $d = null;
        if (!empty($GLOBALS['__k_dil_zorla']) && isset(k_diller()[$GLOBALS['__k_dil_zorla']])) return (string)$GLOBALS['__k_dil_zorla'];
        if ($d !== null) return $d;
        $diller = k_diller();

        $g = isset($_GET['lang']) ? strtolower(substr((string)$_GET['lang'], 0, 2)) : '';
        if ($g !== '' && isset($diller[$g])) { $d = $g; return $d; }

        $c = isset($_COOKIE['kdil']) ? strtolower(substr((string)$_COOKIE['kdil'], 0, 2)) : '';
        if ($c !== '' && isset($diller[$c])) { $d = $c; return $d; }

        $u = k_ulke();
        if ($u !== '') {
            foreach ($diller as $kod => $bilgi) {
                $ulkeler = (array)($bilgi['ulke'] ?? []);
                if (in_array($u, $ulkeler, true)) { $d = $kod; return $d; }
            }
            /* Ülkesi listede yoksa: yurt dışı sayılır, varsayılan dil */
            $d = (string)tg_ayar('dil_varsayilan', 'en');
            if (!isset($diller[$d])) $d = array_key_first($diller);
            return $d;
        }

        /* =============================================================
           ÜLKE BİLGİSİ YOKSA TARAYICI DİLİ — BAŞLIK OKUNUR, ARANMAZ

           Burada bir zamanlar şu vardı: tanımlı diller sırayla dolaşılır,
           her biri Accept-Language metninin İÇİNDE aranırdı. İki dil
           varken çalışıyordu; üçüncü dil açılınca yanlış cevap vermeye
           başladı.

             Accept-Language: de-DE,de;q=0.9,en;q=0.8
             Tanımlı sıra   : tr, en, de, fr

           Döngü 'en'e 'de'den ÖNCE bakar ve ",en" dizisini başlığın
           içinde bulur. Almanca isteyen tarayıcıya İngilizce verilirdi.
           Kusur arama biçiminde değil, SIRADAYDI: cevabı ayarın sırası
           belirliyordu, ziyaretçinin bildirdiği öncelik değil.

           Doğrusu, başlığı olduğu gibi okumaktır. Accept-Language bir
           metin değil bir LİSTEdir: her öge bir dil ve bir ağırlık (q)
           taşır, ağırlık verilmemişse 1'dir. Ziyaretçinin sırası
           bizimkinden önce gelir; eşit ağırlıkta başlıktaki sıra korunur.

           'de-DE' gibi bölgeli kodlarda ana etiket alınır ('de'), çünkü
           arayüz dilleri iki harflidir.
           ============================================================= */
        $a = strtolower((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if ($a !== '') {
            $sira = [];
            foreach (explode(',', $a) as $i => $parca) {
                $parca = trim($parca);
                if ($parca === '') continue;
                $q = 1.0;
                if (preg_match('/;\s*q\s*=\s*([0-9.]+)/', $parca, $mq)) $q = (float)$mq[1];
                $kod = trim(explode(';', $parca)[0]);
                $kod = substr(explode('-', $kod)[0], 0, 8);
                if ($kod === '' || $kod === '*') continue;
                /* Aynı dil iki kez geçerse büyük ağırlık kalır. */
                if (isset($sira[$kod]) && $sira[$kod][0] >= $q) continue;
                $sira[$kod] = [$q, $i];
            }
            /* Ağırlığa göre büyükten küçüğe; eşitse başlıktaki sıra. */
            uasort($sira, fn(array $x, array $y): int => ($y[0] <=> $x[0]) ?: ($x[1] <=> $y[1]));
            foreach ($sira as $kod => $bilgi) {
                if (isset($diller[$kod])) { $d = $kod; return $d; }
            }
        }
        $d = (string)tg_ayar('dil_varsayilan', 'en');
        if (!isset($diller[$d])) $d = array_key_first($diller);
        return $d;
    }

    function k_en(): bool { return k_dil() === 'en'; }
    function k_yon(): string { return (string)((k_diller()[k_dil()]['yon'] ?? 'ltr')); }

    /* İki dilli metin seçici (Türkçe ve İngilizce için kısa yol).
       Arayüzde 1883 çağrısı var ve hiçbiri değişmedi: üçüncü bir dil
       istendiğinde çeviri katmanına bakılır (ortak.php, tg_ceviri_bul).
       Çeviri yoksa metin İngilizceye düşer ve eksik sayılır; sayfa
       eksik görünmez, ama eksikliği ziyaretçiden de gizlenmez. */
    function k_c(string $tr, string $en): string {
        $d = k_dil();
        if ($d === 'tr') return $tr;
        if ($d === 'en') return $en;
        return tg_ceviri_bul($en, $d);
    }

    /* =================================================================
       DEĞİŞKEN TAŞIYAN METİN — k_cd
       -----------------------------------------------------------------
       ÖLÇÜLDÜ, VARSAYILMADI. Arapça açılırken bütün sayfalar gezildi ve
       çeviri bulamayan her dize kaydedildi. Dört cümle her dilde AYRI
       bir anahtar üretiyordu: aynı cümle için altı dilde altı ayrı
       özet. Sebebi şuydu:

           k_c('... ' . hs_tarih_yaz($g) . ' ...', '... ' . hs_tarih_yaz($g) . ' ...')

       hs_tarih_yaz O ANKİ DİLDE yazar. Yani k_c'ye giden "İngilizce"
       metnin içinde "ديسمبر 31, 2027" duruyordu; anahtar bu metinden
       üretildiği için anahtar da dile göre değişiyordu. Sözlükte hangi
       dilin anahtarı yazılıysa yalnız o dil buluyor, ötekiler
       bulamıyordu. Bu cümleler hiçbir dilde çevrilemez durumdaydı ve
       kimse fark etmemişti: eksik sayacı 4 gösteriyordu, ama 4'ün
       "çevrilmemiş" değil "ÇEVRİLEMEZ" demek olduğunu söylemiyordu.

       Çözüm: değişken metnin İÇİNE değil, YERİNE konur. Anahtar
       "... %1 ..." olur ve dile göre değişmez; değer çeviri
       bulunduktan SONRA yerine geçer. Sıra da böylece çevirmenin
       elinde kalır: "%1 tarihinde kapanır" ile "closes on %1"
       aynı anahtarı paylaşır ama sözcük sırası ayrıdır.

       Değerler olduğu gibi konur: kaçırma (escape) çağrı yerinde
       yapılır, çünkü bazı değerler bilerek <b> taşır. */
    function k_cd(string $tr, string $en, ...$deger): string {
        $m = k_c($tr, $en);
        foreach ($deger as $i => $d) {
            $m = str_replace('%' . ($i + 1), (string)$d, $m);
        }
        return $m;
    }

    /* Çok dilli metin seçici: ['tr'=>..., 'en'=>..., 'de'=>...]
       Aranan dil yoksa o dilin yedeğine, o da yoksa ilk değere düşer.
       Yeni bir dil eklendiğinde çağrı yerlerini değiştirmek gerekmez. */
    function k_t(array $metin): string {
        $d = k_dil();
        if (isset($metin[$d]) && $metin[$d] !== '') return (string)$metin[$d];
        /* Dizide o dil yoksa çeviri katmanına bak: k_c ile aynı yol,
           aynı sözlük, aynı anahtar. İki ayrı çeviri yolu olsaydı
           sayfanın yarısı çevrilir yarısı çevrilmezdi. */
        if ($d !== 'tr' && $d !== 'en' && isset($metin['en']) && (string)$metin['en'] !== '') {
            return tg_ceviri_bul((string)$metin['en'], $d);
        }
        $yedek = (string)((k_diller()[$d]['yedek'] ?? ''));
        if ($yedek !== '' && isset($metin[$yedek]) && $metin[$yedek] !== '') return (string)$metin[$yedek];
        foreach ($metin as $v) { if ((string)$v !== '') return (string)$v; }
        return '';
    }

    /* Bulunulan sayfanın istenen dildeki adresi.
       k_bag() gidilecek YOLU dil koruyarak kurar; bu ise ŞU ANKİ yolu
       başka bir dile çevirir. İkisi ayrı işlerdir: biri "bu bağlantıya
       dilimi taşı", öteki "bu sayfayı öteki dilde aç" der.
       Sorgu dizesindeki öteki değerler korunur; yalnız lang değişir. */
    function k_dil_bag(string $hedefDil): string {
        $yol = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if ($yol === '') $yol = '/';
        $s = [];
        parse_str((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_QUERY), $s);
        $s['lang'] = $hedefDil;
        return $yol . '?' . http_build_query($s);
    }

    function k_esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

    /* Dil koruyan bağlantı.
       Sorgu, varsa çapadan (#bolum) önce eklenir: "/a.php#b?lang=en"
       adresi sunucuya sorgu olarak ulaşmaz, çapanın parçası sayılır. */
    function k_bag(string $yol): string {
        $d = k_dil();
        if ($d === 'tr') return $yol;
        $capa = '';
        $d1 = strpos($yol, '#');
        if ($d1 !== false) { $capa = substr($yol, $d1); $yol = substr($yol, 0, $d1); }
        return $yol . (strpos($yol, '?') === false ? '?' : '&') . 'lang=' . $d . $capa;
    }

    /* Gezinme ögeleri.

       KÜMELER BİR SORUYA GÖRE KURULUR, SAYFA TÜRÜNE GÖRE DEĞİL.
       Menüye bakan kişi "bu sayfa hangi türdendir" diye sormaz; "ne
       yapmak istiyorum" diye sorar. Kümeler bu yüzden dört sorunun
       karşılığıdır: ne okuyabilirim (Arşiv), değerlendirme nasıl
       yürüyor (Hakemlik), bu sistem nedir (Sistem), ne yapabilirim
       (Katılın). Önceki düzende "Sistem" kümesi sekiz maddeyle geri
       kalan her şeyin döküldüğü yerdi ve "Kurul oylamaları" arşivin
       içinde duruyordu; ikisi de aranan sayfayı zorlaştırıyordu.

       Ana sayfa ve "Nasıl işler" hiçbir kümeye girmez. İlki bir yere
       değil geriye götürür, ikincisi sisteme yeni gelenin ilk okuması
       gereken sayfadır; ikisi de bir başlığın altında beklemez.

       ADLAR TAM OLMALIDIR. "Hakem aranan" yarım bir sıfat tamlamasıydı,
       neyin arandığı yazmıyordu. Üç ayrı "hakem" maddesi de birbirinden
       ayırt edilebilir olmalıdır: biri süreci anlatır, biri bekleyen
       çalışmaları listeler, biri kişileri.

       "im" değeri k/kimlik.php içindeki ikon dizgesinden gelir. */
    function k_gez(): array {
        return [
            ['yol' => '/',              'im' => 'kure',    'tr' => 'Ana sayfa',   'en' => 'Home',        'k' => ''],
            ['yol' => '/nasil-isler.php','im' => 'kilavuz','tr' => 'Nasıl işler', 'en' => 'How it works','k' => ''],

            ['yol' => '/yazilar.php',   'im' => 'bilgi',   'tr' => 'Çalışmalar',    'en' => 'Works',      'k' => 'oku'],
            ['yol' => '/ara.php',       'im' => 'ara',     'tr' => 'Arama',         'en' => 'Search',     'k' => 'oku'],
            ['yol' => '/istatistik.php','im' => 'grafik',     'tr' => 'İstatistikler', 'en' => 'Statistics', 'k' => 'oku'],

            ['yol' => '/hakemlik.php',  'im' => 'hakemlik',   'tr' => 'Hakemlik süreci',  'en' => 'The review process',   'k' => 'hakem'],
            ['yol' => '/bekleyen.php',  'im' => 'saat',    'tr' => 'Hakem bekleyenler','en' => 'Awaiting reviewers',   'k' => 'hakem'],
            ['yol' => '/hakemler.php',  'im' => 'dizin',  'tr' => 'Hakem dizini',     'en' => 'Reviewer directory',   'k' => 'hakem'],

            ['yol' => '/ilkeler.php',   'im' => 'ilkeler',   'tr' => 'Yayın ilkeleri',   'en' => 'Publication policy',   'k' => 'bil'],
            ['yol' => '/kurul.php',     'im' => 'kurul',   'tr' => 'Yayın kurulu',     'en' => 'Editorial board',      'k' => 'bil'],
            /* Sayfa yapay zekânın bu sistemde neye izinli olduğunu
               yazar. Menüde yalnızca "Yapay zekâ" diye durunca bir
               araç sanılıyordu; bu bir ilke metnidir. */
            ['yol' => '/yz.php',        'im' => 'yz',     'tr' => 'Yapay zekâ ilkesi','en' => 'Policy on AI',         'k' => 'bil'],
            /* Sistemin kendi kusurlarını yazdığı sayfa. Menüde "açıklar"
               diye durunca bir arıza duyurusu gibi okunuyordu; oysa
               sayfanın kendisi bir dürüstlük belgesidir. */
            ['yol' => '/acikliklar.php','im' => 'goz',  'tr' => 'Açık sözlülük',    'en' => 'Plain speaking',       'k' => 'bil'],

            ['yol' => '/basvuru.php',   'im' => 'yayin',   'tr' => 'Çalışma gönderin', 'en' => 'Submit a work',        'k' => 'katil'],
            /* KILAVUZ, GÖNDERİM SAYFASININ HEMEN ALTINDA DURUR.
               Kurul kararı, 15 Ağustos 2026: "yazı ekleme kılavuzu
               oluşturmak lazım, yardım menüsü gibi." Yeri buradadır:
               bir kılavuz, anlattığı işin yanında durmazsa aranmaz.
               "Nasıl işler" sisteme yeni gelenin ilk okumasıdır ve
               sistemin NE OLDUĞUNU anlatır; bu sayfa NE YAPILACAĞINI
               anlatır. İkisi ayrı sorulardır ve birbirinin yerine
               geçmez. */
            ['yol' => '/kilavuz.php',   'im' => 'yazim', 'tr' => 'Yazı ekleme kılavuzu', 'en' => 'Guide to submitting', 'k' => 'katil'],
            /* Açık çağrı: dünyanın herhangi bir yerindeki bir kurum
               destek verebilir ya da himayeyi üstlenebilir. */
            ['yol' => '/destek.php',    'im' => 'destek',  'tr' => 'Destek olun',      'en' => 'Support the system',   'k' => 'katil'],
            ['yol' => '/iletisim.php',  'im' => 'posta','tr' => 'Bize yazın',       'en' => 'Write to us',          'k' => 'katil'],

            /* SİTE HARİTASI KÜMESİZDİR VE EN SONDADIR.
               Bir kümenin içine konsaydı o kümeye ait sanılırdı; oysa
               harita bütün kümelerin üstünde durur ve "nerede ne var"
               sorusunun tek yerdeki cevabıdır. Menüde bulunmasının
               sebebi de budur: menüde bulamayan insanın gideceği yer. */
            ['yol' => '/harita.php',    'im' => 'harita',    'tr' => 'Site haritası',    'en' => 'Site map',             'k' => 'son'],
        ];
    }

    /* KÜME BAŞLIKLARI — NE OLDUKLARINI DEĞİL, NE İŞE YARADIKLARINI SÖYLER.
       Eski başlıklar "Arşiv / Hakemlik / Sistem / Katılın" idi ve üçü de
       menüdeki maddeleri karşılamıyordu:
         - "Arşiv" altında İstatistikler vardı; istatistik bir arşiv
           değil, arşivin sayılarıdır. Arama da bir arşiv değil, araçtır.
         - "Sistem" hem yayın ilkelerini hem kurulu hem açık sözlülük
           sayfasını topluyordu; bunlar "sistem" değil, sistemin
           KURALLARI ile o kuralları KİMİN yürüttüğüdür.
         - "Hakemlik" tek başına doğruydu ama altındaki dört madde
           sürecin kendisi değil, sürecin işleyen halleridir.
       Yeni başlıklar okurun o kümede ne bulacağını söyler. Küme
       başlığı bir etiket değil, bir cevaptır: "burada ne var?"

       Boş anahtarın başlığı yoktur ve basılmaz. */
    function k_gez_kume(): array {
        return [
            'oku'   => ['tr' => 'Arşivi okuyun',     'en' => 'Read the archive'],
            'hakem' => ['tr' => 'Değerlendirme',     'en' => 'Assessment'],
            'bil'   => ['tr' => 'Kurallar ve kurul', 'en' => 'Rules and board'],
            'katil' => ['tr' => 'Katılın',           'en' => 'Take part'],
        ];
    }

    /**
     * Uzun metinli sayfaların yan sütunu.
     * ---------------------------------------------------------------
     * Geniş ekranda metin okuma genişliğinde kalmalıdır, yoksa satırlar
     * uzar ve göz satır başını kaybeder. Ama metni ortalayıp iki yanını
     * boş bırakmak da sayfayı yarım gösterir. Çözüm: metin solda kalır,
     * sağdaki sütun sayfanın kendi içindekiler listesini taşır ve
     * kaydırma boyunca yerinde durur. Böylece uzun sayfada aşağı inip
     * yukarı çıkmak gerekmez.
     *
     * @param array $bolumler [['k'=>'capa','tr'=>'Başlık','en'=>'Title'], ...]
     * @param array $kutular  [['tr'=>'Başlık','en'=>'Title','ic'=>'HTML'], ...]
     */
    function k_belge_yan(array $bolumler, array $kutular = []): string {
        $en = k_en();
        ob_start(); ?>
     <?php /* SAĞ RAY TEK BİR KUTUDUR.
              İçindekiler ile not kutuları ayrı ayrı yapışkan yapılınca
              ikisi de aynı üst noktaya yapışıyor ve kaydırdıkça birbirinin
              üstüne biniyordu; ölçüldü, 1500 piksel kaydırmada 33 piksel
              bindirme vardı ve aşağı indikçe büyüyordu. İkisini tek bir
              sarmalayıcıya alıp yapışkanlığı ona vermek bu sınıf hatayı
              büsbütün ortadan kaldırır: artık yapışan tek bir öge var. */ ?>
     <div class="blg-ray">
     <?php if ($bolumler): ?>
     <?php /* Geniş ekranda hep açık; dar ekranda katlanabilir olsun diye
              details kullanılıyor. "open" yazılı olduğu için betik
              çalışmasa da liste açık gelir. */ ?>
     <details class="blg-nav" open>
       <summary><?= k_c('Bu sayfada', 'On this page') ?></summary>
       <ol>
         <?php foreach ($bolumler as $b): ?>
         <li><a href="#<?= k_esc($b['k']) ?>"><?= k_esc(k_t($b)) ?></a></li>
         <?php endforeach; ?>
       </ol>
     </details>
     <?php endif; ?>
     <?php if ($kutular): ?>
     <aside class="blg-ek" aria-label="<?= k_c('Sayfa notları', 'Page notes') ?>">
       <?php foreach ($kutular as $kt): ?>
       <div class="blg-kutu">
         <b><?= k_esc(tg_t((array)$kt, $en)) ?></b>
         <?= (string)($kt['ic'] ?? '') ?>
       </div>
       <?php endforeach; ?>
     </aside>
     <?php endif; ?>
     </div><!-- /blg-ray -->
<?php   /* DİKKAT: bu işlev yalnızca yan sütunları basar, sarmalayıcı
           ".blg" kutusunu KAPATMAZ. Önceden burada fazladan bir
           </div> vardı; sayfa kendi kutusunu da kapattığı için
           .sahne erkenden kapanıyor ve altbilgi sol sütunun altına
           kayıyordu. Kapatmayı sayfa yapar, açan kim ise o kapatır. */
        return (string)ob_get_clean();
    }

    /**
     * Sekme çubuğu.
     * ---------------------------------------------------------------
     * Aynı türden birkaç listeyi alt alta dizmek yerine yan yana koyar.
     * Çubuk her zaman görünür; hangi seçeneklerin bulunduğu gizlenmez.
     * Bölmeler sunucudan açık gelir, betik yalnızca birini bırakır:
     * betik çalışmazsa sayfa eskisi gibi alt alta okunur.
     *
     * @param string $ad       benzersiz ön ek (kimlikler bundan türer)
     * @param array  $sekmeler [['tr'=>..,'en'=>..,'say'=>?int], ...]
     * @param int    $on       başlangıçta açık olan sekme
     */
    function k_sekme_bar(string $ad, array $sekmeler, int $on = 0): string {
        $en = k_en();
        $c = '<div class="sek-bar" role="tablist">';
        foreach (array_values($sekmeler) as $i => $sk) {
            $sec = $i === $on;
            $c .= '<button type="button" role="tab"'
                . ' id="' . k_esc($ad . '-d' . $i) . '"'
                . ' aria-controls="' . k_esc($ad . '-p' . $i) . '"'
                . ' aria-selected="' . ($sec ? 'true' : 'false') . '"'
                . ' tabindex="' . ($sec ? '0' : '-1') . '">'
                . k_esc(tg_t((array)$sk, $en));
            if (isset($sk['say'])) $c .= '<span class="sek-say">' . (int)$sk['say'] . '</span>';
            $c .= '</button>';
        }
        return $c . '</div>';
    }

    /** Sekme bölmesinin açılış etiketi */
    function k_sekme_ac(string $ad, int $i): string {
        return '<div class="sek-pnl" role="tabpanel" id="' . k_esc($ad . '-p' . $i) . '"'
             . ' aria-labelledby="' . k_esc($ad . '-d' . $i) . '" tabindex="0">';
    }

    /**
     * Sayfa başlığı ve üst çubuk.
     * $o: baslik, aciklama, yol (etkin gezinme), genis (bool), ek_bas (ham HTML)
     */
    function k_bas(array $o = []): void {
        $en   = k_en();
        $dil  = k_dil();
        $mar  = tg_marka($en);
        $alt  = tg_marka_alt($en);
        $bas  = trim((string)($o['baslik'] ?? ''));
        $tam  = $bas !== '' ? ($bas . ' · ' . $mar) : ($mar . ' · ' . $alt);
        $ack  = (string)($o['aciklama'] ?? k_c(
            'Hakemli ve hakemsiz akademik çalışmaların açık erişimle yayımlandığı bağımsız yayın sistemi.',
            'An independent publishing system for open access academic work, with and without peer review.'
        ));
        $yol  = (string)($o['yol'] ?? '');
        $kok  = tg_kok();
        $s    = K_SURUM;
        /* Sayfa türü. "belge" olan sayfalarda içerik ölçüsü daralır ve
           başlık şeridi, metin, kartlar, çizelgeler; hepsi aynı iki
           sütunlu ölçüye oturur. Böylece bir satırın nerede bittiği
           sayfadan sayfaya değişmez. */
        $tur  = (string)($o['tur'] ?? '');
        /* ---- SAYFANIN ÖLÇÜSÜ ----
           Üç ölçü vardır ve sayfa hangisinde olduğunu kendisi bildirir:

             okuma  Düz yazı sayfası. Sütun genişletilmez; genişletilirse
                    76 karakterde biten metnin sağında kalıcı bir boşluk
                    kalır ve sayfa sola yapışmış görünür. Varsayılan.
             genis  Ana içeriği ızgara, çizelge ya da form olan sayfa.
                    Okunacak paragraf azdır, yer varken kullanılır.
             tam    Kenardan kenara sayfa (kaydırak, kimlik levhası).

           Genişlikler k/kutadgu.css içinde tek yerde tanımlıdır; burada
           yalnızca hangisinin geçerli olduğu söylenir. Bilinmeyen bir
           değer yazılırsa okuma ölçüsüne düşer, sayfa bozulmaz. */
        $olcu = (string)($o['olcu'] ?? 'okuma');
        /* 'pano': gösterge panosu ölçüsü (bkz. kutadgu.css,
           --en-metin-pano). Beyaz listeye eklenmeden verilirse sessizce
           'okuma'ya düşer ve sayfa 980 pikselde kalır; panel tam da bu
           yüzden dar görünüyordu. Bilinmeyen bir değerin sessizce
           varsayılana düşmesi doğrudur, ama yeni bir ölçü eklerken
           listeyi güncellemek unutulmamalıdır. */
        if (!in_array($olcu, ['okuma', 'genis', 'pano', 'tam'], true)) $olcu = 'okuma';
        ?><!DOCTYPE html>
<html lang="<?= $dil ?>" dir="<?= k_yon() ?>" data-tema="acik" data-olcu="<?= k_esc($olcu) ?>"<?= $tur !== '' ? ' data-sayfa="' . k_esc($tur) . '"' : '' ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= k_esc($tam) ?></title>
<meta name="description" content="<?= k_esc($ack) ?>">
<?php /* Kişiye özel bağlantıyla açılan sayfalar arama motorlarına kapalıdır. */
$rb = trim((string)($o['robots'] ?? '')); if ($rb !== ''): ?>
<meta name="robots" content="<?= k_esc($rb) ?>">
<?php endif; ?>
<meta name="theme-color" content="#1b2a4a">
<meta name="color-scheme" content="light dark">
<link rel="icon" href="/k/tamga-kucuk.svg?v=<?= $s ?>" type="image/svg+xml">
<link rel="apple-touch-icon" href="/k/tamga-180.png">
<link rel="manifest" href="/manifest.webmanifest">
<?php /* =================================================================
   YAZI TİPİ ÖN YÜKLEME — İKİ YÜZ BİRDEN
   -----------------------------------------------------------------
   BİLDİRİLEN KUSUR: "yazı tipleri sapıtmış, g ve b farklı farklı,
   hepsi farklı gibi."

   ÖLÇÜLDÜ ve doğruydu. Sekiz sayfa denendi; yedisi Caladea'nın DÜZ
   (400) yüzünü istiyor, ama yalnızca KALIN (700) yüz önceden
   yükleniyordu:

     /               indirilen: 700            400:unloaded
     /yazilar.php    indirilen: 700, 400       400:loaded
     /ilkeler.php    indirilen: 700, 400       400:loaded
     /panel.php      indirilen: 700, 400       400:loaded
     ... (yedi sayfa aynı)

   Sonuç şuydu: kalın başlıklar Caladea ile ANINDA çiziliyor, düz
   yüzle yazılan metinler ise 400 dosyası inene kadar yedek serifle
   (Georgia / Palatino / Times) duruyordu. font-display:swap gereği
   metin hiçbir an görünmez kalmıyor — ama bir süre boyunca EKRANDA
   İKİ AYRI YÜZ birden bulunuyordu. 'g' ile 'b', Caladea ile
   Georgia'nın en çok ayrıştığı iki harftir; kusurun ilk görüldüğü
   yer de tam olarak orasıdır.

   İki dosya da 16 KB'dir ve ikisi de zaten indiriliyordu; değişen
   şey NE ZAMAN indirildikleri. Şimdi ikisi de ilk boyamadan önce,
   paralel olarak isteniyor.

   EĞİK YÜZ (400i) ÖN YÜKLENMEZ: ölçümde hiçbir sayfa onu istemedi
   (400i:unloaded, sekiz sayfanın sekizinde). Kullanılmayan bir dosyayı
   önceden yüklemek, ilk boyamayı geciktirmekten başka bir şey yapmaz.
   ================================================================= */ ?>
<link rel="preload" href="/k/yazitipi/kutadgu-serif-700.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/k/yazitipi/kutadgu-serif-400.woff2" as="font" type="font/woff2" crossorigin>
<?php /* =================================================================
   CANONICAL VE HREFLANG BİRBİRİYLE ÇELİŞMEMELİ
   -----------------------------------------------------------------
   ÖLÇÜLDÜ. Sayfa sekiz dil için hreflang bildiriyordu ama canonical
   hepsinde Türkçe adresi gösteriyordu. Bu geçersiz bir bildirimdir:
   hreflang'ın kuralı, kümedeki her adresin KENDİ KENDİNİN canonical'i
   olmasıdır. Bir alternatif kendini başka bir adrese devrediyorsa
   arama motoru kümenin tamamını düşürür. Yani sekiz satır yazılıyor,
   sıfırı işe yarıyordu; İngilizce sayfa da bu yüzden ayrı bir sonuç
   olarak indekslenemiyordu.

   İki tutarlı çözüm vardı ve ikisi eşit değil:

     (a) Bütün diller kendi canonical'ini alsın. O zaman her sayfanın
         altı makine çevirisi ayrı ayrı indekslenir. Denetlenmemiş
         makine çevirisini yayın diye sunmak, hem arama motorlarının
         açıkça uyardığı bir şeydir hem de bu sistemin kendi yazdığı
         cümleyle çelişir: "makine çevirisi bir yayın değil, bir okuma
         yardımıdır".

     (b) Yayın sayılan diller (insan gözünden geçmiş olanlar) kendi
         canonical'ini alır ve hreflang kümesini onlar kurar; okuma
         yardımı olan diller varsayılana devreder ve kümeye girmez.

   (b) seçildi. Sayfanın arama motoruna söylediği şey, okura söylediği
   şeyle aynı olsun diye: hangi dilin yayın hangisinin yardım olduğunu
   zaten sayfanın üstündeki şeritte yazıyoruz.

   Ölçüt tek kaynaktan gelir: tg_dil_gozden_gecirildi(). Bir dil insan
   eliyle denetlendiği gün ayarda işaretlenir ve hem şerit kalkar hem
   sayfa kendi canonical'ini alır; burada ikinci bir liste tutulmaz.
   ================================================================= */
$yolTam = $kok . ($yol !== '' ? $yol : '/');
$ayr    = (strpos($yol, '?') === false ? '?' : '&');
$dilAdres = function (string $dk) use ($yolTam, $ayr): string {
    return $dk === 'tr' ? $yolTam : ($yolTam . $ayr . 'lang=' . $dk);
};
$yayinDilleri = array_values(array_filter(array_keys(k_diller()), 'tg_dil_gozden_gecirildi'));
$kendi = in_array($dil, $yayinDilleri, true);
?>
<link rel="canonical" href="<?= k_esc($kendi ? $dilAdres($dil) : $yolTam) ?>">
<?php foreach ($yayinDilleri as $dk): ?>
<link rel="alternate" hreflang="<?= k_esc($dk) ?>" href="<?= k_esc($dilAdres($dk)) ?>">
<?php endforeach; ?>
<link rel="alternate" hreflang="x-default" href="<?= k_esc($yolTam) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= k_esc($mar) ?>">
<meta property="og:title" content="<?= k_esc($tam) ?>">
<meta property="og:description" content="<?= k_esc($ack) ?>">
<meta property="og:locale" content="<?= k_c('tr_TR', 'en_US') ?>">
<meta name="twitter:card" content="summary">
<meta property="og:image" content="<?= k_esc($kok) ?>/k/tamga-512.png">
<?php /* x-default yukarıda, hreflang kümesinin içinde bir kez yazılıyor.
         Burada ikinci bir kez yazılıyordu; aynı bildirimin iki kopyası
         kümeyi belirsiz kılar. */ ?>
<?php if (empty($o['seo_yok'])) { require_once __DIR__ . '/seo.php'; echo sq_site($en) . "\n"; } ?>
<link rel="stylesheet" href="/k/kutadgu.css?v=<?= $s ?>">
<script>/* yanıp sönmeyi önle: tema stil yüklenmeden uygulanır */
(function(){try{var t=localStorage.getItem('kutadgu-tema');
if(!t)t=(window.matchMedia&&matchMedia('(prefers-color-scheme: dark)').matches)?'koyu':'acik';
document.documentElement.setAttribute('data-tema',t);}catch(e){}})();</script>
<?= (string)($o['ek_bas'] ?? '') ?>
</head>
<body>
<a class="atla" href="#ana"><?= k_c('İçeriğe geç', 'Skip to content') ?></a>

<?php
    /* ---------------------------------------------------------------
       DÜZEN
       Solda kurumsal lacivertte sabit bir gezinme sütunu, üstte sabit
       bir araç çubuğu vardır. Çubuk her sayfada aynı üç şeyi taşır:
       arama, dil ve tema, ve sağ uçta profil dairesi. Böylece okuyucu
       nerede olursa olsun aynı yerde aynı düğmeleri bulur ve panele
       girmek için sayfayı kaydırması gerekmez.
       Dar ekranda sol sütun bir çekmeceye iner, çubuk yerinde kalır.
       --------------------------------------------------------------- */
    k_kabuk_yan($yol, $mar, $alt, $en, $dil, '', $bas);
?>
<div class="sahne">
<main id="ana"><?php
    /* GÖZDEN GEÇİRİLMEMİŞ DİL UYARISI.
       Yeni bir dil, çevirisi tamamlanmadan da açılabilir; açılmalıdır
       da, yoksa hiçbir dil hiç başlamaz. Ama yarım bir arayüzü tam
       gibi göstermek başka bir şeydir. Bu kutu, o dilin henüz bir
       insan tarafından gözden geçirilmediğini SÖYLER ve okura kendi
       dilini seçme yolunu bırakır.

       Uyarı sayfanın en başındadır ve gizlenemez: bir sistemin kendi
       eksiğini dipnotta söylemesi, söylememesinin kibar biçimidir. */
    if (!tg_dil_gozden_gecirildi($dil)): ?>
<div class="kap"><div class="kutu kutu-uya dil-uyari" role="status">
  <p style="margin:0"><b><?= k_esc((string)((k_diller()[$dil]['ad'] ?? strtoupper($dil)))) ?></b>
  · <?= k_esc(k_c('bu dil henüz bir insan tarafından gözden geçirilmedi. Çevirisi olmayan metinler İngilizce görünür.',
                        'this language has not yet been reviewed by a person. Text without a translation is shown in English.')) ?>
  <a href="<?= k_esc(k_dil_bag('en')) ?>" hreflang="en">English</a>
  &middot; <a href="<?= k_esc(k_dil_bag('tr')) ?>" hreflang="tr">Türkçe</a></p>
</div></div>
<?php endif;
    }

    /* Sol sütun + üst çubuk. Ayrı bir işlevdir ki kendi başlığını
       kuran sayfalar (çalışma sayfası gibi) da aynı kabuğu çağırsın
       ve iki ayrı gezinme bakımı yapılmasın. */
    function k_kabuk_yan(string $yol, string $mar = '', string $alt = '', ?bool $en = null, string $dil = '', string $ekArac = '', string $ustBaslik = ''): void {
        if ($en === null) $en = k_en();
        if ($dil === '') $dil = k_dil();
        /* Ana sayfada başlık yazılmaz; uzun bir ad da çubuğu şişirmesin
           diye kırpılır ve tamamı title özniteliğinde kalmaz, çünkü
           çubuktaki ad bir bağlantı değil, bir konum işaretidir. */
        if ($yol === '/' || $yol === '') $ustBaslik = '';
        $ustBaslik = mb_substr(trim($ustBaslik), 0, 70);
        if ($mar === '') $mar = tg_marka($en);
        if ($alt === '') $alt = tg_marka_alt($en);
        $kumeler = k_gez_kume();
        $gecen   = '';
        ?>
<div class="yan-perde" data-gez-kapat hidden></div>

<aside class="yan" id="yan" data-acik="0">
  <a class="marka" href="<?= k_esc(k_bag('/')) ?>">
    <?= kim_isaret(46, 'tam', 'marka-im') ?>
    <span class="marka-yazi">
      <span class="marka-ad"><?= k_esc($mar) ?></span>
      <span class="marka-alt"><?= k_esc($alt) ?></span>
    </span>
  </a>

  <nav class="gez" aria-label="<?= k_c('Ana gezinme', 'Main navigation') ?>">
    <?php foreach (k_gez() as $g):
      $k = (string)($g['k'] ?? '');
      /* Kümesiz maddeler başlıksız durur; boş bir başlık şeridi
         basmak, orada bir küme varmış izlenimi verirdi.
         Başlığı OLMAYAN bir küme (örn. 'son') ise yalnızca ayırıcı
         çizgi ister: madde kendi başına durur, ama üstündeki kümeden
         ayrıldığı görülür. */
      $kumeAd = ($k === '' || !isset($kumeler[$k])) ? '' : (string)tg_t((array)$kumeler[$k], $en);
      if ($k !== $gecen): $gecen = $k; if ($k !== '' && $kumeAd !== ''): ?>
      <span class="yan-bol"><?= k_esc($kumeAd) ?></span>
      <?php elseif ($k !== ''): ?>
      <span class="yan-ayir" aria-hidden="true"></span>
      <?php endif; endif; ?>
      <a href="<?= k_esc(k_bag($g['yol'])) ?>"<?= $yol === $g['yol'] ? ' aria-current="page"' : '' ?>><?= kim_ikon((string)$g['im'], 17) ?><?= k_esc(k_t($g)) ?></a>
    <?php endforeach; ?>
  </nav>

  <div class="yan-dip">
    <?php /* Bildiri, menü listesinin bir maddesi değildir: sistemin
             kuruluş metnidir. Sol sütunun dibinde, monogramıyla,
             kendi başına durur. Göze çarpar ama bağırmaz; alt satırı
             merak uyandırsın diye vardır. */ ?>
    <a class="yan-bildiri" href="<?= k_esc(k_bag('/bildiri.php')) ?>"<?= $yol === '/bildiri.php' ? ' aria-current="page"' : '' ?>>
      <?= kim_monogram(26, 'yan-bildiri-im', true) ?>
      <span>
        <b><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></b>
        <em><?= k_c('Bu sistem neye söz verir', 'What this system promises') ?></em>
      </span>
    </a>
    <p class="yan-slogan"><?= k_esc(kim_slogan($dil)) ?></p>
    <a class="yan-arsiv" href="/dokum.php"><?= kim_ikon('damga', 15) ?><?= k_c('Arşivin tamamını indir', 'Download the whole archive') ?></a>
  </div>
</aside>

<header class="ust">
  <button class="ust-dg" type="button" data-gez-dg aria-expanded="false" aria-controls="yan"
          aria-label="<?= k_c('Menü', 'Menu') ?>">
    <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <path d="M3 6h18M3 12h18M3 18h18"/>
    </svg>
  </button>
  <a class="ust-marka" href="<?= k_esc(k_bag('/')) ?>">
    <?= kim_isaret(34, 'tam', 'marka-im') ?>
    <span class="marka-ad"><?= k_esc($mar) ?></span>
  </a>

  <?php /* ---- Çubuğun ortası: bulunulan sayfanın adı ----
           Burada önce bir arama kutusu duruyordu. Kutu, çubuğun
           yarısını kaplıyor ve her sayfada aynı boş alanı taşıyordu;
           oysa arama sol sütunda kendi maddesine ve kendi sayfasına
           sahip. Yerine sayfanın adı kondu: okuyucu kaydırdıkça
           nerede olduğunu unutmasın, çubuk da boş durmasın.
           Ana sayfada yazılmaz, çünkü orada ad zaten markanın kendisi
           olurdu ve iki kez okunurdu. */ ?>
  <?php if ($ustBaslik !== ''): ?>
  <span class="ust-baslik" aria-hidden="true"><?= k_esc($ustBaslik) ?></span>
  <?php endif; ?>

  <div class="ust-sag">
    <?php /* Arama artık tek bir düğme: dar ekranda yalnızca imi,
             geniş ekranda imi ve adı görünür. */ ?>
    <a class="d d-ara" href="<?= k_esc(k_bag('/ara.php')) ?>"
       aria-label="<?= k_c('Çalışmalarda ara', 'Search the works') ?>"><?= kim_ikon('ara', 16) ?><span><?= k_c('Ara', 'Search') ?></span></a>
    <?php /* Sayfaya özgü araçlar (çalışma sayfasındaki okuma araçları
             gibi) buraya girer; çubuğun geri kalanı her sayfada aynıdır. */ ?>
    <?= $ekArac ?>
    <?php $dl = k_diller(); ?>
    <?php /* DİL SEÇİMİ BİR PENCEREDİR, İKİ DİL ARASINDA BİR ANAHTAR DEĞİL.
             Eskiden iki dil varsa tek bir "EN" bağlantısı, üç ve daha
             çoksa ayrı bir açılır liste basılıyordu: aynı iş için iki
             ayrı arayüz, ve dil sayısı ikiyi geçtiği gün ilki sessizce
             yanlış olurdu. Artık tek bir yol var ve dil sayısından
             bağımsız çalışır.

             <details> KULLANILIYOR, <dialog> DEĞİL. Betik kapalıyken
             <dialog> hiç açılmaz; <details> tarayıcının kendi işidir ve
             betiksiz de açılır. İçindeki her dil gerçek bir
             BAĞLANTIDIR: betik yokken tıklayan o dile gider, betik
             varken seçim ayrıca çereze yazılır ve sonraki sayfalarda
             sürer.

             Her dil KENDİ ADIYLA yazılır. Bir dilin adını başka bir
             dilde okumak zorunda kalmak, bu sistemin karşı olduğu şeyin
             küçük bir örneğidir. Gözden geçirilmemiş diller de listede
             durur ve öyle olduğu YAZAR: eksik bir çeviriyi gizlemek,
             okura eksik olmadığını söylemektir. */ ?>
    <details class="dil-sec" data-dil-sec>
      <summary class="d d-im" title="<?= k_esc(k_c('Dil seçin', 'Choose a language')) ?>"
               aria-label="<?= k_esc(k_c('Dil seçin', 'Choose a language') . ': ' . ($dl[$dil]['ad'] ?? strtoupper($dil))) ?>"><?= k_esc(strtoupper($dil)) ?></summary>
      <div class="dil-kutu">
        <p class="dil-bas"><?= k_esc(k_c('Dil seçin', 'Choose a language')) ?></p>
        <ul class="dil-liste">
          <?php foreach ($dl as $dk => $db):
            $secili = ($dk === $dil);
            $gg = tg_dil_gozden_gecirildi($dk); ?>
          <li>
            <a data-dil="<?= k_esc($dk) ?>" href="<?= k_esc(k_dil_bag($dk)) ?>" hreflang="<?= k_esc($dk) ?>"
               lang="<?= k_esc($dk) ?>"<?= $secili ? ' aria-current="true"' : '' ?>>
              <span class="dil-kod"><?= k_esc(strtoupper($dk)) ?></span>
              <span class="dil-ad">
                <b><?= k_esc((string)($db['ad'] ?? strtoupper($dk))) ?></b>
                <?php if (!$gg): ?><small><?= k_esc(k_c('gözden geçirilmedi', 'not reviewed yet')) ?></small><?php endif; ?>
              </span>
              <?php if ($secili): ?><span class="dil-tik" aria-hidden="true">&#10003;</span><?php endif; ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="dil-not"><?= k_esc(k_c('Çevirisi olmayan metinler İngilizce görünür.',
                                         'Text without a translation is shown in English.')) ?></p>
      </div>
    </details>
    <button class="d d-im" type="button" data-tema-dg aria-pressed="false"
            aria-label="<?= k_c('Temayı değiştir', 'Toggle theme') ?>"><span data-tema-sim>☽</span></button>
    <?= k_profil($en) ?>
  </div>
</header>
<?php
    }

    /**
     * Sağ üstteki profil dairesi.
     * Sunucu tarafında oturum açılmaz: her genel sayfada oturum açmak
     * önbelleği kırar ve sayfayı yavaşlatır. Daire önce "giriş" hâliyle
     * çizilir, betik hesabı öğrenince baş harfe ve bildirim sayısına
     * döner. Böylece sayfa ilk boyamada tamdır, yer kayması olmaz.
     */
    /**
     * Kişi yüzü: profil resmi varsa o, yoksa baş harfleri.
     * ---------------------------------------------------------------
     * Baş harf dairesi bir eksiklik değil, resmin yokluğunun düzgün
     * karşılığıdır: kimse resim yüklemek zorunda kalmasın diye. Resim
     * yüklendiğinde aynı yerde aynı ölçüde onun yerini alır.
     */
    function k_yuz(string $ad, string $resim = '', int $b = 40, string $sinif = '', string $yedek = 'harf'): string {
        $s = 'yuz' . ($sinif !== '' ? ' ' . $sinif : '');
        $o = 'width:' . $b . 'px;height:' . $b . 'px';
        if ($resim !== '') {
            return '<img class="' . k_esc($s) . '" src="' . k_esc($resim) . '" alt="" loading="lazy" '
                 . 'width="' . $b . '" height="' . $b . '" style="' . $o . '">';
        }
        /* Resmini paylaşmak istemeyen kişi için: kurumsal işaretten
           türetilmiş, herkes için aynı olan bir simge. Kimliği açık
           etmez ama boş bir daire de bırakmaz. */
        if ($yedek === 'isaret') {
            return '<span class="' . k_esc($s) . ' yuz-isaret" aria-hidden="true" style="' . $o . '">'
                 . kim_isaret($b, 'daire') . '</span>';
        }
        return '<span class="' . k_esc($s) . ' yuz-harf" aria-hidden="true" style="' . $o
             . ';font-size:' . max(10, (int)round($b * 0.38)) . 'px">' . k_esc(tg_bas_harf($ad)) . '</span>';
    }

    /**
     * Kişi kartı. Bir adın göründüğü her yerde aynı biçimde kullanılır:
     * yüz, ad, kurum, ORCID ve kişinin kendi sayfasına giden bağlantı.
     * Kart bir bağlantıdır; tıklayan kişinin kaydına gider.
     *
     * $k: ['ad','unvan','kurum','orcid','resim','alt']  (hepsi isteğe bağlı)
     */
    function k_kisi_kart(array $k, bool $unvanGoster = true, int $yuzB = 56): string {
        $ad    = trim((string)($k['ad'] ?? ''));
        if ($ad === '') return '';
        $unvan = trim((string)($k['unvan'] ?? ''));
        $gorAd = $unvanGoster && $unvan !== '' ? k_unvan_ekle(tg_unvan_ad($unvan), $ad) : $ad;
        $yol   = tg_kisi_yolu($ad);
        ob_start(); ?>
<?php /* KARTIN GÖVDESİ ZATEN KUTADGU PROFİLİNE BAĞLIDIR, ama bağ
         olduğu GÖRÜNMÜYORDU: kurul sayfasında yalnızca dış profili olan
         kişinin kartında bir düğme duruyor, ötekiler bağlantısız
         sanılıyordu. Bildirilen şikâyet buydu. Alt şeride açık bir
         "Kutadgu profili" bağlantısı eklendi.

         ŞERİT İKİ AYRI ŞEY TAŞIR ve ikisi karıştırılmaz:
           kk-ickapi  bu sistemdeki kişi sayfası
           kk-dis     kişinin KENDİ seçtiği dış profil (kurum sayfası,
                      YÖKSİS, ABS, ResearchGate...). Sistem hiçbirine
                      ayrıcalık tanımaz; adres de etiket de kişinindir.

         YOL YOKSA KART HİÇ BAĞLANMAZ. tg_kisi_yolu() boş dönebilir;
         eskiden o durumda href k_bag('') ile ANA SAYFAYA düşüyordu.
         Bağlantı gibi görünen ama hiçbir yere götürmeyen bir kart,
         bağlantısız bir karttan kötüdür. Kural k_kisi_bag() ile aynı. */ ?>
<div class="kk-sar">
<?php if ($yol !== ''): ?><a class="kk" href="<?= k_esc(k_bag($yol)) ?>"><?php else: ?><div class="kk kk-bagsiz"><?php endif; ?>
  <?= k_yuz($ad, (string)($k['resim'] ?? ''), $yuzB, 'kk-yuz', 'isaret') ?>
  <span class="kk-ic">
    <b><?= k_esc($gorAd) ?></b>
    <?php $alt = trim((string)($k['alt'] ?? '')); if ($alt !== ''): ?><span class="kk-alt"><?= k_esc($alt) ?></span><?php endif; ?>
    <?php $krm = trim((string)($k['kurum'] ?? '')); if ($krm !== ''): ?><span class="kk-kurum"><?= k_esc($krm) ?></span><?php endif; ?>
    <?php $orc = trim((string)($k['orcid'] ?? '')); if ($orc !== ''): ?><span class="kk-orcid">ORCID <?= k_esc($orc) ?></span><?php endif; ?>
  </span>
<?php if ($yol !== ''): ?></a><?php else: ?></div><?php endif; ?>
<?php /* İKİNCİ BİR "KUTADGU PROFİLİ" BAĞLANTISI DENENDİ VE GERİ ALINDI.
         Kartın gövdesi zaten kişi sayfasına bağlıdır; şeride ayrıca bir
         bağlantı koymak aynı yere giden İKİ düğme demekti ve dar kartta
         alt alta düşüp kartın dışına taşmış gibi duruyordu (ekran
         görüntüsüyle bildirildi).

         Asıl kusur bağlantının YOKLUĞU değil GÖRÜNMEZLİĞİYDİ: kart
         tıklanabilir olduğunu belli etmiyordu. Çözüm ikinci bir bağlantı
         eklemek değil, var olanı görünür kılmaktır — ad, üzerine
         gelindiğinde bağlantı gibi davranır (bkz. .kk:hover kuralları).

         Şeritte yalnızca DIŞ profil kalır, çünkü o gerçekten başka bir
         yere gider ve kartın gövdesinden ulaşılamaz. */ ?>
<?php $web = trim((string)($k['web'] ?? '')); if ($web !== ''): ?>
<a class="kk-dis" href="<?= k_esc($web) ?>" target="_blank" rel="noopener">
  <?= kim_ikon('insan', 15) ?><?= k_esc(tg_dis_profil_ad($web, (string)($k['web_ad'] ?? ''), k_en())) ?> &nearr;
</a>
<?php endif; ?>
</div>
<?php   return (string)ob_get_clean();
    }

    /* Bir adı kişi sayfasına bağlar. Sayfa yoksa bağ da kurulmaz;
       kırık bağlantı bırakmaktansa düz metin bırakmak yeğdir. */
    function k_kisi_bag(string $ad, string $ic = '', string $sinif = ''): string {
        $yol = tg_kisi_yolu($ad);
        $metin = $ic !== '' ? $ic : k_esc($ad);
        if ($yol === '') return $metin;
        return '<a class="' . k_esc('kisi-bag' . ($sinif !== '' ? ' ' . $sinif : '')) . '" href="'
             . k_esc(k_bag($yol)) . '">' . $metin . '</a>';
    }

    function k_profil(bool $en): string {
        $giris = k_esc(k_bag('/panel.php'));
        ob_start(); ?>
<div class="hs" data-hs>
  <?php /* =================================================================
           İKİ AYRI DENETİM: BAĞLANTI VE MENÜ

           Girmemiş ziyaretçi için menü açmanın anlamı yok — içinde tek bir
           satır kalıyor ve o satır da "giriş yap" diyor. Bir tıklamanın
           ardından ikinci bir tıklama istemek, hiçbir şey seçtirmeden
           yalnız yol uzatmaktır. Bu yüzden girmemiş ziyaretçi DOĞRUDAN
           giriş sayfasına giden bir BAĞLANTI görür.

           Menü düğmesi ise ancak giriş yapılmışsa görünür; orada seçilecek
           gerçek şeyler vardır.

           BETİKSİZ TARAYICI DA GİREBİLİR. Öntanımlı olarak görünen şey
           bağlantıdır ve o bağlantı gerçek bir adres taşır. Eskiden
           buradaki tek denetim bir <button>'dı ve menüyü yalnız betik
           açabiliyordu: betiği kapalı bir ziyaretçinin siteye girmek için
           hiçbir yolu yoktu. Bir kapının açılması betiğe bağlı olmamalıdır.
           ================================================================= */ ?>
  <a class="hs-dg hs-bag" data-hs-bag href="<?= $giris ?>"><?= kim_ikon('kalkan', 18) ?><span class="hs-etiket"><?= k_c('Giriş', 'Sign in') ?></span></a>
  <button class="hs-dg" type="button" data-hs-dg hidden aria-expanded="false" aria-haspopup="menu"
          aria-label="<?= k_c('Hesabım', 'My account') ?>">
    <span data-hs-bas><?= kim_ikon('insan', 19) ?></span>
    <span class="hs-roz" data-hs-roz hidden>0</span>
  </button>
  <div class="hs-menu" data-hs-menu role="menu" hidden>
    <div class="hs-kim" data-hs-kim hidden>
      <span class="hs-dai" data-hs-kim-bas></span>
      <span><b data-hs-kim-ad></b><span data-hs-kim-rol></span></span>
    </div>
    <?php /* KİŞİSEL SATIRLAR GİRİŞ YAPILMADAN GÖSTERİLMEZ.
             "Panelim", "Çalışmalarım", "Hakemliklerim" bir ziyaretçiye
             sahip olmadığı bir şeyi vaat ediyordu; tıklayınca giriş
             ekranı çıkıyor ve kişi ne olduğunu anlamıyordu. Menü, kişinin
             gerçekten yapabileceği şeyleri göstermelidir.

             Öntanımlı olarak GİZLİ ve betikle açılıyor. Betiksiz tarayıcı
             bunları hiç görmez; bir şey kaybetmez, çünkü aşağıdaki
             "Giriş yap ya da hesap aç" satırı zaten aynı sayfaya
             götürür. */ ?>
    <div data-hs-ozel hidden>
    <a role="menuitem" href="<?= $giris ?>#ozet"><?= kim_ikon('kilavuz', 17) ?><?= k_c('Panelim', 'My panel') ?><span class="hs-say" data-hs-say hidden>0</span></a>
    <a role="menuitem" href="<?= $giris ?>#calismalar"><?= kim_ikon('bilgi', 17) ?><?= k_c('Çalışmalarım', 'My works') ?></a>
    <a role="menuitem" href="<?= $giris ?>#hakemlik"><?= kim_ikon('insan', 17) ?><?= k_c('Hakemliklerim', 'My reviews') ?></a>
    <a role="menuitem" href="<?= $giris ?>#listem"><?= kim_ikon('imi', 17) ?><?= k_c('Listem', 'My list') ?><span class="hs-say" data-hs-haber hidden>0</span></a>
    <a role="menuitem" href="<?= $giris ?>#hesap"><?= kim_ikon('kalkan', 17) ?><?= k_c('Hesap ayarları', 'Account settings') ?></a>
    <?php /* "Çalışma gönder" de kişisel satırların arasına alındı.
             Gönderim hesap ister; girmemiş birine bu satırı göstermek,
             "Panelim" satırını göstermekle aynı şeydi: tıklayan kişi
             beklediği yere değil giriş ekranına düşüyordu. Menü,
             kişinin şu anda GERÇEKTEN yapabileceği şeyleri göstermeli.

             Çalışma göndermeye çağrı kaybolmuyor: sol sütunda KATILIN
             başlığı altında herkese açık duruyor. Orası bir davettir,
             burası bir hesabın kendi menüsü — ikisi ayrı şeydir. */ ?>
    <a role="menuitem" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= kim_ikon('yayin', 17) ?><?= k_c('Çalışma gönder', 'Submit a work') ?></a>
    <div class="hs-ayir"></div>
    </div>
    <a role="menuitem" href="<?= $giris ?>" data-hs-giris><?= kim_ikon('kalkan', 17) ?><?= k_c('Giriş yap ya da hesap aç', 'Sign in or create an account') ?></a>
    <button role="menuitem" type="button" data-hs-cikis hidden><?= kim_ikon('cikis', 17) ?><?= k_c('Çıkış yap', 'Sign out') ?></button>
  </div>
</div>
<?php   return (string)ob_get_clean();
    }

    /* Alt bilgi ve betikler */
    function k_son(string $ek = ''): void {
        $en  = k_en();
        $mar = tg_marka($en);
        $ana = (string)tg_ayar('ana_site', '');
        $anaAd = (string)tg_ayar('ana_site_ad', '');
        $lis = (string)tg_ayar('lisans', 'CC BY 4.0');
        $lisU = (string)tg_ayar('lisans_url', '');
        $tam = (string)tg_ayar('tamga_ad', 'Tamga');
        ?></main>

<?php /* Alt bilgi başlıkları h2'dir, h4 değil. contentinfo kendi
         yer imidir ve bu dördü onun en üst düzey bölümleridir; h4
         yazıldığında gövdedeki h2'den sonra iki basamak atlanıyor ve
         belge yapısı, ekran okuyucunun gördüğü hâliyle bozuluyordu
         (WCAG 1.3.1). Görünüşleri değişmedi: biçim footer.alt h4
         yerine footer.alt h2 seçicisinden geliyor. */ ?>
<footer class="alt">
  <div class="kap alt-ic">
    <div>
      <h2><?= k_c('Kutadgu', 'Kutadgu') ?></h2>
      <p><?= k_c(
        'Adını Kutadgu Bilig\'den alır: kut veren, insanı mutluluğa eriştiren bilgi. Açık erişimli, bağımsız akademik yayın sistemi.',
        'Named after the Kutadgu Bilig: knowledge that brings fortune and wellbeing. An independent, open access academic publishing system.'
      ) ?></p>
    </div>
    <?php /* SÜTUNLAR EŞİT UZUNLUKTA OLMALI, ÇÜNKÜ GÖZ EN UZUNU ÖLÇER.
             Yayın 11, Sistem 8, Açıklık 7 satırdı. Izgara sütunları
             en uzunun boyuna gerdiği için Açıklık sütununun altında
             124 piksellik bir boşluk kalıyor, alt bilgi hem uzun hem
             delik görünüyordu. İki madde SINIFINA GÖRE taşındı, sayı
             tutsun diye değil:
               "Bildiri"      -> Açıklık   (bildiri bir açıklık belgesidir;
                                 zaten "Haklar ve devir" onun bir bölümüne
                                 iniyor, ana belge de yanında dursun)
               "Site haritası"-> Sistem    (yayın değil, gezinme aracıdır)
             Bir madde de KALDIRILDI: "Hesabım ve panelim". Her sayfanın
             sağ üstünde, betik çalışmasa bile görünen bir <a> olarak
             duruyor; alt bilgideki kopyası yeni bir yol açmıyordu.
             Sonuç 9 / 8 / 8. */ ?>
    <div>
      <h2><?= k_c('Yayın', 'Publishing') ?></h2>
      <ul>
        <li><a href="<?= k_esc(k_bag('/nasil-isler.php')) ?>"><?= k_c('Sistem nasıl işler', 'How the system works') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Bütün çalışmalar', 'All works') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/ara.php')) ?>"><?= k_c('Arama ve çözümleme', 'Search and analysis') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Çalışma gönder', 'Submit a work') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/hakemlik.php')) ?>"><?= k_c('Hakemlik süreci', 'Peer review process') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/bekleyen.php')) ?>"><?= k_c('Hakem aranan çalışmalar', 'Works seeking reviewers') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/yz.php')) ?>"><?= k_c('Yapay zekâ kullanımı', 'Use of artificial intelligence') ?></a></li>
      </ul>
    </div>
    <div>
      <h2><?= k_c('Sistem', 'The system') ?></h2>
      <ul>
        <li><a href="<?= k_esc(k_bag('/kurul.php')) ?>"><?= k_c('Yayın kurulu', 'Editorial board') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/hakemler.php')) ?>"><?= k_c('Hakem dizini', 'Reviewer directory') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/istatistik.php')) ?>"><?= k_c('İstatistikler', 'Statistics') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/kimlik.php')) ?>"><?= k_c('Kurumsal kimlik ve işaret', 'Brand and identity') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/uygulama.php')) ?>"><?= k_c('Uygulama olarak kur', 'Install as an app') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/harita.php')) ?>"><?= k_c('Site haritası', 'Site map') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/iletisim.php')) ?>"><?= k_c('İletişim ve destek', 'Contact and support') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/destek.php')) ?>"><?= k_c('Destek olun', 'Support us') ?></a></li>
      </ul>
    </div>

    <div>
      <h2><?= k_c('Açıklık', 'Openness') ?></h2>
      <ul>
        <li><a href="<?= k_esc(k_bag('/acikliklar.php')) ?>"><?= k_c('Sistemin bilinen açıkları', 'Where the system is weak') ?></a></li>
        <li><a href="/dokum.php"><?= k_c('Arşivin tamamını indir', 'Download the whole archive') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/ilkeler.php#tamga')) ?>"><?= k_esc($tam) ?> <?= k_c('nedir', 'explained') ?></a></li>
        <li><a href="<?= k_esc($lisU) ?>" rel="license noopener" target="_blank"><?= k_c('Çalışmalar', 'Works') ?>: <?= k_esc($lis) ?></a></li>
        <?php /* KAYNAK KODUN BAĞI — AGPL §13'ün İSTEDİĞİ ŞEY.
                 Lisansın adı yazılıydı ama KAYNAĞIN KENDİSİNE giden bir
                 bağ hiçbir görünür sayfada yoktu; yalnız llms.php'de
                 vardı, o da tarayıcılar için. §13 "ağ üzerinden erişen
                 kullanıcıya kaynağı sun" der ve okurun göremediği bir
                 bağ sunulmuş sayılmaz.

                 BAĞ AYRI BİR SATIR DEĞİL, LİSANS SATIRININ YANINDA.
                 Ayrı satır olarak eklendiğinde altbilgi kapısı dört
                 ölçüde birden kırmızıya döndü (1440'ta 475px, tavan
                 470). Tavan doğru: altbilgi büyüdükçe her sayfanın
                 altına eklenen bir vergidir. Aynı bilgi, satır
                 eklemeden, ait olduğu yere kondu — lisansın yanına.

                 Adres tek kaynaktan gelir: depo açıksa depo, değilse
                 Zenodo kaydı (kayıt açık, kaynak zipi herkese açık
                 iniyor). */ ?>
        <?php $knk = function_exists('tg_kaynak_adres') ? tg_kaynak_adres() : ''; ?>
        <li><a href="https://www.gnu.org/licenses/agpl-3.0.html" rel="license noopener" target="_blank"><?= k_c('Yazılım', 'Software') ?>: AGPL-3.0</a><?php if ($knk !== ''): ?>
          &middot; <a href="<?= k_esc($knk) ?>" rel="noopener" target="_blank"><?= k_c('kaynak', 'source') ?></a><?php endif; ?></li>
        <?php /* SİSTEMİN KENDİ DOI'Sİ. Bildirilen kusur: "sistemimizin
                 DOI'si sistemde nerede, ben göremedim." Ölçüldü ve
                 haklıydı: numara YALNIZCA kimlik.php'de duruyordu ve o
                 sayfaya giden bağın adı "Kurumsal kimlik ve işaret"ti.
                 Bir DOI'yi marka sayfasında aramak kimsenin aklına
                 gelmez; alınmış ama bulunamayan bir kimlik, alınmamış
                 gibidir.

                 Yeri artık altbilgi: bir sistemi anmak isteyen kişi
                 önce oraya bakar. Ayrı satır değil, kendi satırında
                 kısa: altbilgi yüksekliğinin tavanı var ve tavan
                 haklı (bkz. yukarıdaki not). KAVRAM DOI'si yazılır,
                 sürümünki değil — anılacak olan sistemdir. */ ?>
        <?php $sysDoi = function_exists('tg_doi') ? tg_doi() : ''; if ($sysDoi !== ''): ?>
        <li><a href="<?= k_esc(tg_doi_adres($sysDoi)) ?>" rel="noopener" target="_blank">DOI <?= k_esc($sysDoi) ?></a></li>
        <?php endif; ?>
        <li><a href="<?= k_esc(k_bag('/bildiri.php')) ?>"><?= k_c('Bildiri', 'Declaration') ?></a></li>
        <li><a href="<?= k_esc(k_bag('/bildiri.php#b-devir')) ?>"><?= k_c('Haklar ve devir', 'Rights and succession') ?></a></li>
                <?php /* KİŞİSEL SİTE BAĞI ALTBİLGİDEN KALDIRILDI (kurul
                         isteği, 14 Ağustos 2026). Gerekçesi bildirinin
                         kendi cümlesiyle aynı: bu sistem kurucusunun
                         mülkü değil, ortak bir emanettir. Her sayfanın
                         altında kurucunun kişisel sitesine giden bir
                         bağ, o cümlenin tersini söyler.

                         Bağ SİLİNMEDİ, ait olduğu yerde duruyor:
                         bildirinin imzasında, metni yazanın adının
                         yanında. Orada bir aidiyet değil, bir imzadır.
                         ayar.php'deki 'ana_site' de yerinde kalır;
                         kaldırılan yalnızca altbilgideki yerdir. */ ?>
      </ul>
    </div>
  </div>
  <div class="kap alt-son">
    <span>&copy; <?= date('Y') ?> <?= k_esc($mar) ?></span>
    <span><?= k_c('Bütün çalışmalar', 'All works') ?> <?= k_esc($lis) ?> <?= k_c('ile yayımlanır.', 'licensed.') ?></span>
    <span><?= k_c('Açık erişim, ücretsiz, reklamsız.', 'Open access, free of charge, ad free.') ?></span>
        <?php /* Bu bir üslup cümlesi değil, işleyen bir hükümdür; gerekçesi
             ve sonuçları bildiride yazılıdır. */ ?>
    <span class="alt-hak"><a href="<?= k_esc(k_bag('/bildiri.php#b-devir')) ?>"><?= k_c('Bütün hakları bütün insanlığındır.', 'All rights belong to all humanity.') ?></a></span>
    <?php /* ALT BİLGİ KİŞİYİ DEĞİL SİSTEMİ ANLATIR.
             Her sayfanın altında tek bir kişinin adı durduğunda okur,
             sistemi o kişinin sitesi sanar. Oysa Kutadgu bir kurulun
             yönettiği ortak bir yapıdır ve yazılımı herkesin kurabileceği
             açık bir yazılımdır. Alt bilgi bu yüzden iki şeyi söyler:
             sistemi kimin yönettiğini (kurucu kurul) ve yazılımın nerede
             durduğunu (kaynak kodu). Kimin neyi yaptığı kurul sayfasında,
             bildiride ve Zenodo kaydında adlarıyla yazılıdır; emek orada
             kayıt altındadır, burada değil. */ ?>
    <span class="ara-oto"><a href="<?= k_esc(k_bag('/kurul.php')) ?>"><?= k_c('Kurucu kurul', 'Founding board') ?></a></span>
    <span class="ara-oto"><?= k_c('Yazılım', 'Software') ?>: <a href="<?= k_esc(tg_kaynak_adres()) ?>" rel="noopener">Kutadgu, AGPL-3.0</a></span>
  </div>
</footer>
</div><?php /* .sahne */ ?>

<script src="/k/kutadgu.js?v=<?= K_SURUM ?>" defer></script>
<?= $ek ?>
<?php /* ÇEVİRİ EKSİĞİ, SAYFANIN SONUNDA VE SAYIYLA.

         Sözlük dosyası "1822 dize, %100" diyordu ve doğru sayıyordu:
         yanlış olan neyi saydığıydı. Sayfa açıldığında 371 dize
         İngilizce kalıyor, bunu ölçen hiçbir yer yoktu. Sayı buraya,
         sayfanın en sonuna yazılır — çünkü ancak sayfa kurulup bittiğinde
         kaç dizenin karşılığı bulunamadığı bilinir.

         Yorum satırıdır: okura görünmez, kapıya görünür. Yalnız üçüncü
         dillerde yazılır; Türkçe ve İngilizce sayfada aranacak bir
         karşılık yoktur ve sayı her zaman sıfırdır.

         Ölçen kapı: sinama/ceviri-kapi.php. */
      if (k_dil() !== 'tr' && k_dil() !== 'en'): ?>
<!-- ceviri-eksik: <?= (int)tg_ceviri_eksik() ?> -->
<?php endif; ?>
</body>
</html><?php
    }
}
