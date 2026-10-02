<?php
/* =====================================================================
   KUTADGU - Kullanıcı hesapları ve roller / Accounts and roles
   ---------------------------------------------------------------------
   Roller bir merdivendir; kimse basamak atlayamaz:

     okur        Sayfaya ilk gelen herkes. Rol değildir, kayıt gerekmez.
                 Bütün çalışmalar okura zaten açıktır.
     aday hakem  Doktora belgesini sunmuş, doğrulanmayı bekleyen kişi.
     hakem       Belgesi doğrulanmış ve en az bir değerlendirme yapmış kişi.
     yazar       Doktora derecesi olan ya da iki doktoralı destekleyen
                 kişi. Merdivenin aslı, yazarlığın hakemlikten sonra
                 gelmesidir: bir metni değerlendirmiş olan, kendi metninin
                 nasıl değerlendirileceğini de bilir. KURULUŞ DÖNEMİNDE bu
                 sıra aranmaz, çünkü arşiv boşken değerlendirilecek çalışma
                 yoktur ve koşul sisteme ilk çalışmanın girmesini engeller.
                 Bkz. ayar.php 'kurulus_donemi'. Doktora ya da destek şartı
                 bundan etkilenmez; o hiçbir zaman gevşemez.
     editor      Baş editörlerce listeye eklenen kişi. Hakem atayabilir,
                 editöryal not düşebilir.
     bas_editor  Üç basamağı vardır ve karıştırılmaz. KURUCU bir
                 kayıttır, düşmez ve sonradan verilemez. GÖREVDEKİ bir
                 yetkidir, yazılı bir süresi vardır, hakem atar, editör
                 ekler, editöryal not düşer. ONURSAL bir teşekkürdür,
                 sürenin dolmasıyla kalır ve hiçbir yetki taşımaz.
     yonetici    Sistem yöneticisi.

   Bir kullanıcı aynı anda birden çok rol taşıyabilir. Bütün yetki
   denetimi bu dosyadaki işlevlerden geçer; sayfalar kendi kuralını
   yazmaz.
   ===================================================================== */

require_once __DIR__ . '/../ortak.php';

if (!function_exists('hs_roller')) {

    function hs_roller(bool $en = false): array {
        return [
            'aday_hakem' => k_c('Aday hakem', 'Candidate reviewer'),
            'hakem'      => k_c('Hakem', 'Reviewer'),
            'yazar'      => k_c('Yazar', 'Author'),
            'editor'     => k_c('Editör', 'Editor'),
            'bas_editor' => k_c('Baş editör', 'Chief editor'),
            'yonetici'   => k_c('Sistem yöneticisi', 'Administrator'),
        ];
    }

    /* Unvan işlevleri ortak.php içine taşındı: hem hesap katmanı hem
       başvuru ve hakemlik akışları aynı listeyi kullanmalıdır. Buradaki
       adlar eski çağrı yerleri kırılmasın diye duruyor. */
    function hs_unvan_tablo(): array   { return tg_unvan_tablo(); }
    function hs_unvan_ad(string $a, ?bool $en = null): string { return tg_unvan_ad($a, $en); }
    function hs_unvan_anahtar(string $m): string { return tg_unvan_anahtar($m); }
    function hs_unvanlar(?bool $en = null): array { return tg_unvanlar($en); }

    function hs_dosya(): string { return 'hesaplar.json'; }

    /* Hesap dosyası: k/veri.php varsa onun okuyucusu, yoksa doğrudan */
    function hs_oku(): array {
        if (function_exists('k_json')) return (array)k_json(hs_dosya(), []);
        $p = tg_veri_dizini() . '/' . hs_dosya();
        if (!is_file($p)) return [];
        $d = json_decode((string)file_get_contents($p), true);
        return is_array($d) ? $d : [];
    }

    /* =================================================================
       KAMUSAL HESAP LİSTESİ — DENEME KAYITLARI DIŞARIDA
       -----------------------------------------------------------------
       ÖLÇÜLEN KUSUR (14 Ağustos 2026): çalışmalar için deneme süzgeci
       vardı (k_yazilar), HESAPLAR için yoktu. Yani deneme düzeni
       kurulduğu an "Deneme Hakem" adlı kişi:
         - hakem dizinine düşüyordu (hakemler.php hs_oku() okuyor),
         - /kisi/deneme-hakem adresinde kendi sayfasını açıyordu.

       Kurul isteği tam olarak bunu yasaklıyor: "demo kullanıcılar aynı
       altyapıyı kullanmalı ama kayıt hiçbir yere yazılmadan
       silinebilmeli." Aynı altyapı EVET — aynı dosya, aynı giriş, aynı
       akış; ama okurun gördüğü hiçbir yerde görünmeyecek, silindiğinde
       de arkasında iz kalmayacak.

       SÜZGEÇ TEK YERDE. Sayfalara ayrı ayrı yazılsaydı biri unutulduğu
       gün deneme hesabı oradan sızardı — çalışmalarda bu ders zaten
       ödenmişti (bkz. k/veri.php, k_yazilar).

       YÖNETİM VE GİRİŞ hs_oku() kullanmayı SÜRDÜRÜR: deneme hesabı
       gerçekten giriş yapabilmeli, yoksa deneme değersizleşir. Ayrım
       şudur: kimlik doğrulama hepsini görür, OKURA GÖSTERİLEN liste
       görmez. */
    function hs_kamusal(): array {
        static $c = null;
        if ($c !== null) return $c;
        $c = [];
        foreach (hs_oku() as $h) {
            if (is_array($h) && !empty($h['deneme'])) continue;
            $c[] = $h;
        }
        return $c;
    }

    /* Bir adın deneme hesabına ait olup olmadığı. Kişi sayfası bunu
       sorar: ada göre üretilen bir sayfa, hesap listesinden değil
       kayıtlardan da doğabilir. */
    function hs_deneme_adi(string $ad): bool {
        $an = function_exists('tg_ad_anahtar') ? tg_ad_anahtar($ad) : mb_strtolower(trim($ad), 'UTF-8');
        if ($an === '') return false;
        foreach (hs_oku() as $h) {
            if (!is_array($h) || empty($h['deneme'])) continue;
            $ha = function_exists('tg_ad_anahtar') ? tg_ad_anahtar((string)($h['ad'] ?? ''))
                                                  : mb_strtolower(trim((string)($h['ad'] ?? '')), 'UTF-8');
            if ($ha !== '' && $ha === $an) return true;
        }
        return false;
    }

    function hs_yaz(array $h): bool {
        $p = tg_veri_dizini() . '/' . hs_dosya();
        $t = $p . '.tmp';
        if (file_put_contents($t, json_encode($h, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
        return rename($t, $p);
    }

    /* Adresin karşılaştırılabilir biçimi. Kural ortak.php'dedir ve
       buradan yalnızca çağrılır: aynı normalleştirme iki yerde
       yazılsaydı, biri değiştiği gün kimlik karşılaştırması ile adres
       özeti birbirinden ayrışırdı. Ad eski çağrı yerleri için duruyor. */
    function hs_eposta_anahtar(string $e): string {
        return tg_eposta_anahtar($e);
    }

    /* ---- BAŞ EDİTÖRLÜĞÜN İKİ LİSTESİ ----
       Baş editörler ayar dosyasında tanımlıdır; ilk girişte hesapları
       kendiliğinden açılır ve kullanıcı adını kendisi seçer.

       İki ayrı liste okunur ve karıştırılmaz:
         'bas_editorler'            kuruluş kaydı; kapalıdır, sıralaması
                                    kayıt sırasıdır ve düşmez.
         'gorevdeki_bas_editorler'  sonradan atananlar; her kayıtta
                                    yazılı bir süre vardır.

       ÖNEMLİ AYRIM: kurucunun kaydı düşmez, bu yüzden süresi geçmiş bir
       kurucu da baş editör olarak tanınmayı sürdürür ve yalnızca editör
       atama yetkisini yitirir. Sonradan atanan biri içinse süre görevin
       tamamını kapatır: o gün geldiğinde rol de düşer, kişi onursal baş
       editör olur ve hiçbir yetkisi kalmaz. Onursal bir kaydın buradan
       geri dönmemesi, sistemin sözünün tuttuğu yerdir. */
    function hs_bas_editor_mu(string $eposta): bool {
        return hs_bas_editor_bilgi($eposta) !== [];
    }

    function hs_bas_editor_bilgi(string $eposta): array {
        $e = hs_eposta_anahtar($eposta);
        if ($e === '') return [];
        /* Yalnızca GÖREVDE OLANLAR. Görevi bitmiş bir kayıt buraya
           gelmez; gelseydi onursal sıfat bir yetki taşır olurdu.
           Kurucular da bu denetimin dışında değildir: kurucu KAYDI
           düşmez, ama görevini devreden ya da vefat eden kurucu yetki
           taşımaz. Kayıt ile yetkiyi ayıran yer burasıdır. */
        /* Eşleşme tg_kurucu_eslesir() ile yapılır: kayıtta düz adres
           varsa onunla, yoksa 'eposta_ozet' ile. Kural tek yerdedir. */
        foreach (tg_bas_editorler_gorevde() as $b) {
            if (tg_kurucu_eslesir($b, $e)) return $b;
        }
        return [];
    }

    /* Kişi kurucu baş editör mü? Bu soru yalnızca kuruluş kaydına
       sorulur; sonradan atanmış bir kayıt hiçbir koşulda evet
       döndürmez. Kurucu sıfatı sonradan verilemez. */
    function hs_kurucu_mu(string $eposta): bool {
        $e = hs_eposta_anahtar($eposta);
        if ($e === '') return false;
        foreach (tg_kurucu_bas_editorler() as $b) {
            if (tg_kurucu_eslesir($b, $e)) return true;
        }
        return false;
    }

    /* Bir hesabı e-posta ile bul */
    function hs_bul(string $eposta): ?array {
        $e = hs_eposta_anahtar($eposta);
        /* Boş anahtar hiçbir hesapla eşleşmemelidir. Aksi hâlde e-postası
           boş kalmış bir kayıt, boş bir istekle bulunabilir hâle gelir. */
        if ($e === '') return null;
        foreach (hs_oku() as $h) {
            if (!is_array($h)) continue;
            $he = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
            if ($he !== '' && $he === $e) return $h;
        }
        return null;
    }

    /* ---- BİR HESABI ADRES ÖZETİYLE BUL ----
       Kurul kayıtlarında düz adres artık durmuyor; duran şey özettir.
       Hesaplar ise veri dizinindedir ve düz adres taşır, yani
       karşılaştırma burada yapılabilir: her hesabın adresinin özeti
       alınır ve aranan özetle karşılaştırılır.

       Bu yol olmasaydı kurul kartları kişinin kendi unvanı, kurumu ve
       ORCID'i ile zenginleşmeyi bırakır, yapılandırmadaki başlangıç
       değerlerinde donup kalırdı.

       Arama düz adres aramasından pahalıdır (her kayıt için bir sha256)
       ama yalnızca kurul kartları için, sayfa başına birkaç kez
       çalışır. Geçersiz biçimli bir özet hiçbir hesabı bulmaz. */
    function hs_bul_ozet(string $ozet): ?array {
        $oz = strtolower(trim($ozet));
        if (!preg_match('/^[a-f0-9]{64}$/', $oz)) return null;
        foreach (hs_oku() as $h) {
            if (!is_array($h)) continue;
            $he = tg_eposta_ozet((string)($h['eposta'] ?? ''));
            if ($he !== '' && hash_equals($he, $oz)) return $h;
        }
        return null;
    }

    /* Bir kurul/editör kaydının sahibi olan hesap. Kural yine tektir:
       düz adres varsa onunla aranır, yoksa özetle. Kartları
       zenginleştiren bütün yerler bunu çağırır ki biri özet yolunu
       tanımadığı için eksik kart basmasın. */
    function hs_kayit_hesabi(array $kayit): ?array {
        $ep = trim((string)($kayit['eposta'] ?? ''));
        if ($ep !== '') return hs_bul($ep);
        return hs_bul_ozet((string)($kayit['eposta_ozet'] ?? ''));
    }

    function hs_bul_kullanici(string $kad): ?array {
        $k = mb_strtolower(trim($kad), 'UTF-8');
        /* GÜVENLİK: kullanıcı adı henüz seçilmemiş hesapların bu alanı
           boştur. Boş bir arama bunlardan ilkiyle eşleşirse, giriş
           denemesi hedeflenmeyen bir hesaba yönelir. Bu yüzden boş
           anahtar hiçbir zaman eşleşmez. */
        if ($k === '') return null;
        foreach (hs_oku() as $h) {
            if (!is_array($h)) continue;
            $hk = mb_strtolower((string)($h['kullanici'] ?? ''), 'UTF-8');
            if ($hk !== '' && $hk === $k) return $h;
        }
        return null;
    }

    /* Kullanıcı adı kuralı: yalnızca giriş içindir, sayfalarda gösterilmez.
       Harf, rakam, nokta, alt çizgi; 3 ile 24 arası. */
    function hs_kullanici_gecerli(string $k): bool {
        return (bool)preg_match('/^[a-z0-9][a-z0-9._]{2,23}$/', mb_strtolower(trim($k), 'UTF-8'));
    }

    /* Görünen ad: sayfalarda her zaman "Ünvan Ad SOYAD" biçiminde yazılır.
       Kullanıcı adı hiçbir yerde görünmez. */
    /* Görünen ad. Unvan anahtar olarak saklandığı için sayfanın diline
       göre çözülür: aynı kayıt Türkçe sayfada "Doç. Dr.", İngilizce
       sayfada "Assoc. Prof." olarak okunur. */
    function hs_gorunen_ad(array $h, ?bool $en = null): string {
        $ad = trim((string)($h['ad'] ?? ''));
        if ($ad === '') return '';
        return k_unvan_ekle_basit(hs_unvan_ad((string)($h['unvan'] ?? ''), $en), $ad);
    }
    function k_unvan_ekle_basit(string $unvan, string $ad): string {
        $unvan = trim($unvan); $ad = trim($ad);
        if ($ad === '' || $unvan === '') return $ad;
        if (preg_match('/^\s*(prof|doç|doc|dr|öğr|ogr|arş|ars|uzm|op|assoc|assist)\b\.?/iu', $ad)) return $ad;
        if (mb_stripos($ad, $unvan, 0, 'UTF-8') !== false) return $ad;
        return $unvan . ' ' . $ad;
    }

    /* Bir hesabın rolleri: kayıtlı roller + kayıtlardan türeyenler */
    function hs_rolleri(array $h): array {
        $r = is_array($h['roller'] ?? null) ? array_values(array_unique($h['roller'])) : [];
        /* ---- BAŞ EDİTÖRLÜĞÜN TEK KAYNAĞI AYAR DOSYASIDIR ----
           Bu rol her okumada ayar.php'deki listeden çözülür ve hesabın
           içinde yazılı olsa bile oradan gelmiyorsa sayılmaz.

           Önceden rol, hesap açılırken kaydın içine de yazılıyordu.
           O kayıt, kişi listeden çıkarıldıktan sonra da yetkiyi
           taşımayı sürdürürdü: sistem bir kuruma devredilip o kurum
           görevdeki baş editörleri değiştirdiğinde, eskiler yetkilerini
           kaybetmezdi. Bildiri "görevdeki baş editörlük devredilebilir
           ve geri alınabilir" diyor; bunun doğru olması için rolün tek
           bir kaynağı olmalıdır. Aşağıdaki iki satır, eski kayıtları
           göç ettirmeye gerek bırakmadan bunu sağlar. */
        $r = array_values(array_filter($r, fn($x) => $x !== 'bas_editor'));
        if (hs_bas_editor_mu((string)($h['eposta'] ?? '')) || hs_olcutle_bas_mu($h)) $r[] = 'bas_editor';
        $dd = tg_dogrulama_durum($h['dogrulama'] ?? null);
        if ($dd['durum'] === 'bekliyor' && !in_array('hakem', $r, true) && !in_array('aday_hakem', $r, true)) $r[] = 'aday_hakem';
        return array_values(array_unique($r));
    }

    function hs_rol_var(array $h, string $rol): bool { return in_array($rol, hs_rolleri($h), true); }

    /* Editör yetkisi: hakem atayabilir, editöryal not düşebilir */
    function hs_editor_mu(array $h): bool {
        foreach (['editor', 'bas_editor', 'yonetici'] as $r) { if (hs_rol_var($h, $r)) return true; }
        return false;
    }
    /* Yalnızca baş editör ve yönetici */
    function hs_bas_yetki(array $h): bool {
        foreach (['bas_editor', 'yonetici'] as $r) { if (hs_rol_var($h, $r)) return true; }
        return false;
    }

    /* ---- EDİTÖR ATAMA YETKİSİ VE SÜRESİ ----
       Baş editörlük bir kayıttır ve düşmez. Editör listesini değiştirmek
       ise bir yetkidir ve süreli verilebilir: ayar.php'deki kişi
       kaydında 'editor_yetki_bitis' varsa o günün sonunda kapanır.

       Kapanan yalnızca bu yetkidir. Kişi kurul sayfasında kurucu baş
       editör olarak durmayı sürdürür, hakem atar, editöryal not düşer;
       yalnızca editör listesine ekleme ve çıkarma yapamaz. Süre
       yazılmamışsa yetki süresizdir. Yönetici bu sınırın dışındadır,
       çünkü sistemin bakımı hiçbir tarihte durmamalıdır. */
    function hs_editor_yetki_bitis(string $eposta): string {
        $b = hs_bas_editor_bilgi($eposta);
        /* YALNIZCA 'editor_yetki_bitis' okunur. Bir zamanlar burada
           tg_gorev_bitis() çağrılıyordu ve o işlev 'gorev_bitis' alanını
           da okuyordu; yani editör listesi yetkisinin süresi ile görevin
           kendisi tek bir tarihe bağlanmıştı. Sonuç, ayar dosyasının ve
           hocalara giden karar belgesinin yazdığının tersiydi: tarih
           geldiğinde kişi yalnızca editör listesini değil, baş
           editörlüğü büsbütün yitiriyordu. İki yetki iki alandır ve
           burada yalnızca biri okunur. */
        return $b === [] ? '' : tg_editor_yetki_bitis($b);
    }

    function hs_editor_atama_yetkisi(array $h): bool {
        if (!hs_bas_yetki($h)) return false;
        if (hs_rol_var($h, 'yonetici')) return true;
        /* Görevi ölçütle kazanmış kişinin süresi yoktur: ölçüt
           karşılandığı sürece görev sürer, karşılanmadığı gün düşer. */
        if (hs_olcutle_bas_mu($h)) return true;
        $bitis = hs_editor_yetki_bitis((string)($h['eposta'] ?? ''));
        if ($bitis === '') return true;
        if (date('Y-m-d') <= $bitis) return true;
        /* Tarih geçmiş olsa bile bir zorunlu hâl varsa yetki açık
           kalır. Hâllerin listesi tek yerdedir (tg_zorunlu_haller());
           burada ikinci bir liste kurulmaz, yoksa iki liste zamanla
           ayrışır ve biri ötekinin tanımadığı bir durumda açılır. */
        return tg_zorunlu_hal() !== [];
    }

    /* =================================================================
       DAVET EDİLEBİLECEK ROLLER — TEK KAYNAK
       -----------------------------------------------------------------
       "Kurucu baş editörler 2027 sonuna kadar yeni baş editör, editör ve
       hakem ekleyebilir; ama kendileri gibi KURUCU ekleyemez."

       Bu cümlenin dört ayrı yeri vardır ve dördü de burada toplanır:
       kimin davet edebileceği, neyi davet edebileceği, ne zamana kadar,
       ve neyi HİÇBİR ZAMAN edemeyeceği.

       KAPALI SEÇENEKLER GİZLENMEZ, SEBEBİYLE GÖSTERİLİR. Olmayan bir
       seçenek, neden olmadığını söylemez; kişi kendinde kusur arar.
       Ekranda görünen ama basılınca 403 dönen bir düğme de aynı
       kusurun tersidir. Bu yüzden her satır üç şey taşır: açık mı,
       değilse neden, ve seçilirse kişinin hangi rolle başlayacağı.

       OKUR SATIRI KALDIRILDI (kurul isteği, 14 Ağustos 2026):
       "yönetim panelinde okur davet etme olmasın."

       Gerekçesi zaten satırın kendi açıklamasında yazılıydı ve satırı
       geçersiz kılıyordu: OKUMAK İÇİN KAYIT GEREKMEZ. Bu sistemde
       arşivin tamamı kayıtsız açıktır; kimseyi okumaya davet etmenin
       bir karşılığı yok. Karşılığı olmayan bir davet, davet edilen
       kişiye "burada kapalı bir şey var" izlenimi verir — açık erişimli
       bir sistemde söylenebilecek en yanlış şey budur. Hesap kurmak
       isteyen zaten kendi eliyle kurar; davete gerek yok.

       ÜÇ SATIRIN GEREKÇESİ:

       aday_hakem  Hakem DEĞİL, aday hakem. Hakemlik doktora belgesinin
                   doğrulanmasına bağlıdır ve o denetim bu davetle
                   atlanamaz. Davetle "hakem" vermek, sistemin tek
                   nitelik denetimini bir düğmeye indirgemek olurdu.

       editor      Editör listesine ekleme yetkisi SÜRELİDİR
                   (ayar.php'deki 'editor_yetki_bitis'; kurucularda
                   2027 sonu). Süre dolduğunda bu satır kapanır ve
                   sebebini yazar. Zorunlu hâller listesi yetkiyi
                   yeniden açarsa satır da açılır — ikinci bir liste
                   kurulmaz.

       bas_editor  Atama yetkisi 2027 sonunda düşer ('bas_editor_atama'
                   .yetki_bitis). Ama bu satır AÇIKKEN BİLE davet tek
                   başına yetmez: baş editörlük rolü her okumada
                   ayar.php'deki kurul kaydından çözülür ve hesabın
                   içine yazılsa bile oradan gelmiyorsa sayılmaz. Bu
                   bilerek böyledir — yazılan bir yetki geri alınamaz
                   hâle gelirdi. Panel bunu gizlemez: daveti üretir ve
                   kurul kaydının ayrıca yazılması gerektiğini söyler.

       kurucu      HİÇBİR ZAMAN. Kurucu bir yetki değil bir KAYITtır ve
                   sonradan verilemez; 2028'de kapanan şey görev değil
                   iki dar yetkidir, kurucu kaydı düşmez. Satır listede
                   durur ve kapalı olduğunu söyler, çünkü sorulan soru
                   budur.
       ================================================================= */
    function hs_davet_rolleri(array $h): array {
        /* k_en() KABUK dosyasında tanımlıdır ve bu dosya kabuksuz da
           yüklenebiliyor (uçlar, kapılar, komut satırı). Bir saat önce
           aynı kusurun başka bir biçimi basvuru.php'yi yarıda kesmişti:
           bir sayfada var olan yardımcı, her bağlamda var demek değildir. */
        $enBayrak = function_exists('k_en') ? (bool)k_en() : false;
        $bas   = hs_bas_yetki($h);
        $edYet = hs_editor_atama_yetkisi($h);
        $atama = function_exists('tg_bas_atama') ? tg_bas_atama() : ['yetki_bitis' => '2027-12-31'];
        $bugun = date('Y-m-d');
        $basAcik = $bas && ($bugun <= (string)$atama['yetki_bitis'] || (function_exists('tg_zorunlu_hal') && tg_zorunlu_hal() !== []));
        $edBitis = hs_editor_yetki_bitis((string)($h['eposta'] ?? ''));

        return [
            [
                'k'    => 'hakem',
                'ad'   => tg_c('Hakem', 'Reviewer'),
                /* 14 Ağustos 2026: burası "Aday hakem — hakemlik, doktora
                   belgesi doğrulanınca açılır" diyordu ve kişiyi belge
                   sunmaya yolluyordu. Kurul kararıyla kalktı; gerekçesi
                   ortak.php'de tg_dogrulama_editor()'ün başındadır.
                   Kısacası: belgeye bakan zaten bir editördü, yani
                   denetim hep insandı; sistem yalnızca bunu söylemiyordu.
                   Artık söylüyor ve KİMİN doğruladığını da yazıyor. */
                'ack'  => tg_c('Davet ettiğinizde doktorasını doğrulamış olursunuz; adınız kayda geçer ve görünür.',
                               'By inviting, you confirm their doctorate; your name is recorded and shown.'),
                'acik' => $bas,
                'sebep'=> $bas ? '' : tg_c('Davet yalnızca baş editörlere açıktır.', 'Inviting is open to chief editors only.'),
                'rol'  => ['hakem'],
                /* Uç bu bayrağı görünce hesaba doğrulama kaydını yazar.
                   Bayrak burada durur ki panel ile ucun uyguladığı kural
                   aynı yerden gelsin; iki ayrı yere yazılsaydı bir gün
                   ayrışırlardı. */
                'dogrular' => true,
            ],
            [
                'k'    => 'editor',
                'ad'   => tg_c('Editör', 'Editor'),
                'ack'  => tg_c('Hakem atar, editöryal not düşer, başvuruları okur.',
                               'Assigns reviewers, adds editorial notes, reads applications.'),
                'acik' => $edYet,
                'sebep'=> $edYet ? '' : ($edBitis !== ''
                            ? tg_cd('Editör listesine ekleme yetkiniz %1 tarihinde doldu.',
                                    'Your authority to add to the editor list ended on %1.', null, tg_tarih_ad($edBitis, $enBayrak))
                            : tg_c('Editör listesine ekleme yetkiniz yok.', 'You do not have the authority to add to the editor list.')),
                'rol'  => ['editor'],
            ],
            [
                'k'    => 'bas_editor',
                'ad'   => tg_c('Baş editör', 'Chief editor'),
                'ack'  => tg_c('Davet hesabı açar; rol ancak kurul kaydı yazılınca başlar.',
                               'The invitation opens the account; the role begins only once the board record is written.'),
                'acik' => $basAcik,
                'sebep'=> $basAcik ? '' : tg_cd('Yeni baş editör atama yetkisi %1 tarihinde düşer; bundan sonra görev davetle değil kayıtla kazanılır.',
                                                'The authority to appoint new chief editors ends on %1; from then on the role is earned by record, not by invitation.',
                                                null, tg_tarih_ad((string)$atama['yetki_bitis'], $enBayrak)),
                /* Rol verilmez: davet yalnız hesabı açar. Kurul kaydı
                   yazılmadan bu kişi baş editör OLMAZ ve olmadığını
                   panel söyler. */
                'rol'  => [],
                'kurul_kaydi_gerek' => true,
            ],
            [
                'k'    => 'kurucu',
                'ad'   => tg_c('Kurucu baş editör', 'Founding chief editor'),
                'ack'  => tg_c('Kurucu bir yetki değil bir kayıttır; kuruluş kaydı kapalıdır.',
                               'Founder is a record, not an authority; the founding record is closed.'),
                'acik' => false,
                'sebep'=> tg_c('Kurucu sıfatı hiçbir koşulda sonradan verilemez.', 'Founder status can never be granted afterwards.'),
                'rol'  => [],
            ],
        ];
    }

    /* Bir davet anahtarının verdiği roller. Uç bu işlevi kullanır;
       istemciden gelen role ASLA güvenilmez. */
    function hs_davet_rol_coz(array $h, string $k): ?array {
        foreach (hs_davet_rolleri($h) as $r) {
            if ($r['k'] !== $k) continue;
            if (empty($r['acik'])) return null;
            return $r;
        }
        return null;
    }

    /* ---- ÖLÇÜTLE KAZANILAN BAŞ EDİTÖRLÜK ----
       2028'den sonra bu görev davetle değil kayıtla kazanılır:
       ayar.php'deki 'bas_editor_olcut'a bakılır ve sayım arşivin o anki
       hâlinden yapılır. Hiçbir yere yazılmaz; yazılan bir yetki geri
       alınamaz hâle gelirdi ve bildirideki söz boşa çıkardı.

       Bir kuruma devir olduğunda o kurumun ataması bunun üstündedir:
       kurum, ayar.php'deki listeyi kendisi yazar. */
    function hs_olcutle_bas_mu(array $h): bool {
        if (!function_exists('tg_bas_olcut_saglayanlar')) return false;
        $ad = tg_ad_anahtar(trim((string)($h['ad'] ?? '')));
        if ($ad === '') return false;
        return isset(tg_bas_olcut_saglayanlar()[$ad]);
    }

    /* ---- DAVET ANAHTARININ ÇÖZÜLMESİ ----
       Anahtarın kendisi hiçbir yerde saklanmaz; kayıtta yalnızca özeti
       durur. Karşılaştırma sabit zamanlıdır, çünkü özet karşılaştırması
       da bir sızıntı yoludur. Süresi dolmuş ya da kullanılmış bir
       anahtar hiçbir hesabı açmaz. */
    function hs_davet_coz(string $jeton): ?array {
        $jeton = trim($jeton);
        if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) return null;
        $ozet = hash('sha256', $jeton);
        foreach (hs_oku() as $h) {
            if (!is_array($h) || !is_array($h['davet'] ?? null)) continue;
            if (!hash_equals((string)($h['davet']['ozet'] ?? ''), $ozet)) continue;
            $bitis = strtotime((string)($h['davet']['bitis'] ?? ''));
            if ($bitis === false || $bitis < time()) return null;
            /* Parolası kurulmuş bir hesapta davet artık bir anlam
               taşımaz; kayıt kalmış olsa bile geçmez. */
            if (trim((string)($h['parola'] ?? '')) !== '') return null;
            return $h;
        }
        return null;
    }

    /* =================================================================
       PAROLA YENİLEME JETONU
       -----------------------------------------------------------------
       BU YOL YOKTU VE YOKLUĞU KODUN İÇİNDE YAZILIYDI. api/index.php'de
       davet ucunun yanında şu cümle duruyor: "Kişi parolasını unuttuysa
       PAROLA YENİLEME YOLUNDAN gider." Öyle bir yol hiç yazılmamıştı.
       Sonucu ağırdı: parolasını unutan herkes kalıcı olarak kilitli
       kalıyordu, çünkü davet de kapalı (parolası olan hesaba davet
       çıkarmak, o hesabı ele geçirmenin en kısa yolu olurdu).

       DAVETLE AYNI İSKELET, TEK BİR KRİTİK FARKLA. İskelet aynı: ham
       jeton bir kez üretilir, kayda yalnız sha256 özeti yazılır, süre
       kısadır ve tek kullanımlıktır. Fark şudur: davet bağlantısı
       YÖNETİCİNİN eline geçer ve o iletir; yenileme bağlantısı yalnızca
       HESABIN KENDİ ADRESİNE gider. Yöneticinin elinden çıkan bir
       sıfırlama bağlantısı, bir kurtarma aracı değil bir hesap ele
       geçirme aracıdır.

       SÜRE KISA. Davet on dört gün yaşar, çünkü davet edilen kişi henüz
       sistemi bilmez ve okumak ister. Yenileme bir saat yaşar: kişi
       zaten oradadır, düğmeye basmıştır, bekleyen bir şey yoktur. Uzun
       yaşayan bir sıfırlama jetonu, posta kutusuna erişen birinin
       elinde uzun süre çalışan bir anahtardır.
       ================================================================= */
    /* ---- HESABI DİSKE YAZ ---- (varsa günceller, yoksa ekler)
       Bu işlev api/index.php'deydi ve k/hesap.php içindeki
       hs_yenileme_uret() onu çağırıyordu: hesap modülü, tek başına
       yüklendiğinde çalışmayan bir işlev taşıyordu. Ölçüldü — komut
       satırından çağrılan ilk araç "Call to undefined function
       hs_kaydet()" ile düştü. Bir modülün işlevi, o modül yüklendiğinde
       çalışmalıdır; yoksa modül değil, o dosyanın parçasıdır. */
    function hs_kaydet(array $hesap): void {
        /* Baş editörlük kayda yazılmaz; ayar.php'deki listeden çözülür.
           Eski kayıtlarda yazılı kalmış olabilir, ilk kaydedişte düşer.
           hs_rolleri() onu zaten saymıyor; burası yalnızca dosyayı
           gerçeğe uyduruyor ki kayda bakan biri yanılmasın. */
        if (is_array($hesap['roller'] ?? null)) {
            $hesap['roller'] = array_values(array_filter($hesap['roller'], fn($x) => $x !== 'bas_editor'));
        }
        $liste = hs_oku();
        $e = hs_eposta_anahtar((string)$hesap['eposta']);
        $bulundu = false;
        foreach ($liste as $i => $h) {
            if (is_array($h) && hs_eposta_anahtar((string)($h['eposta'] ?? '')) === $e) { $liste[$i] = $hesap; $bulundu = true; break; }
        }
        if (!$bulundu) $liste[] = $hesap;
        hs_yaz($liste);
    }

    function hs_yenileme_uret(array $h, int $saat = 1): string {
        $jeton = bin2hex(random_bytes(32));
        $h['parola_yenile'] = [
            'ozet'   => hash('sha256', $jeton),
            'olusma' => date('c'),
            'bitis'  => date('c', time() + max(1, $saat) * 3600),
        ];
        hs_kaydet($h);
        return $jeton;
    }

    function hs_yenileme_coz(string $jeton): ?array {
        $jeton = trim($jeton);
        if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) return null;
        $ozet = hash('sha256', $jeton);
        foreach (hs_oku() as $h) {
            if (!is_array($h) || !is_array($h['parola_yenile'] ?? null)) continue;
            if (!hash_equals((string)($h['parola_yenile']['ozet'] ?? ''), $ozet)) continue;
            $bitis = strtotime((string)($h['parola_yenile']['bitis'] ?? ''));
            if ($bitis === false || $bitis < time()) return null;
            return $h;
        }
        return null;
    }

    /* Tarihi okunur biçimde yazar: 2027-12-31 -> 31 Aralık 2027 */
    function hs_tarih_yaz(string $gun, bool $en = false): string {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $gun, $m)) return '';
        /* Ay adı ve dizim ortak.php'de tek yerde (tg_tarih_dizimi). */
        return tg_tarih_dizimi((int)$m[3], (int)$m[2], (int)$m[1], $en);
    }

    /* Rol adının okunur karşılığı */
    function hs_rol_ad(string $rol, bool $en = false): string {
        $r = hs_roller($en);
        return $r[$rol] ?? $rol;
    }

    /* =================================================================
       SON GÖRÜLME
       -----------------------------------------------------------------
       İstenen: "online kullanıcıları izleyebilmek." Bu sistemde
       "çevrimiçi" diye bir durum YOKTUR ve olmadığını söylemek, olduğunu
       varsayan bir ekran çizmekten dürüsttür: web bir bağlantı değil bir
       istek dizisidir; kimse "bağlı" değildir, yalnızca en son ne zaman
       bir sayfa istediği bilinir. Bu yüzden ölçülen şey "çevrimiçi" değil
       SON GÖRÜLME zamanıdır ve panelde de öyle yazar.

       NE TUTULUR: hesabın adres özeti, adı ve son görülme saati. Başka
       hiçbir şey. IP tutulmaz, hangi sayfaya bakıldığı tutulmaz, tarayıcı
       parmak izi tutulmaz. Bir yayın sisteminin yöneticisi, sistemin
       yaşadığını görmek için kimin ne okuduğunu bilmek zorunda değildir.

       BEŞ DAKİKADA BİR YAZILIR. Her sayfa isteğinde yazmak, tek kişilik
       bir gezinmede dakikada onlarca dosya yazması demektir; ölçüm
       değeri aynı kalırken sunucuya bedeli boşuna artar.

       OTUZ GÜNDE DÜŞER. Kayıt bir yoklama listesi değildir; eskisi
       silinir çünkü kimsenin bir yıl önce hangi gün girdiğini bilmenin
       bu sisteme bir faydası yoktur.
       ================================================================= */
    function hs_gorundu(array $h): void {
        $e = hs_eposta_anahtar((string)($h['eposta'] ?? ''));
        if ($e === '') return;
        $p = tg_veri_dizini() . '/son-gorulme.json';
        $d = is_file($p) ? json_decode((string)@file_get_contents($p), true) : [];
        if (!is_array($d)) $d = [];
        $simdi = time();
        if ($simdi - (int)($d[$e]['t'] ?? 0) < 300) return;
        $d[$e] = ['t' => $simdi, 'ad' => (string)($h['ad'] ?? ''),
                  'roller' => array_values((array)($h['roller'] ?? []))];
        foreach ($d as $k => $v) if ($simdi - (int)($v['t'] ?? 0) > 30 * 86400) unset($d[$k]);
        $t = $p . '.tmp';
        if (@file_put_contents($t, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false) @rename($t, $p);
    }

    /* Oturumdaki hesap (yoksa null) */
    function hs_oturum(): ?array {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'path' => '/']);
            @session_start();
        }
        $e = (string)($_SESSION['kutadgu_hesap'] ?? '');
        if ($e === '') return null;
        $h = hs_bul($e);
        if (is_array($h)) hs_gorundu($h);
        return $h;
    }

    /* Sayfalarda kullanılan kısayol */
    function hs_girisli(): bool { return hs_oturum() !== null; }
}
