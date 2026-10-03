<?php
/* =====================================================================
   DENEME KAPISI
   ---------------------------------------------------------------------
   Deneme düzeni, sistemin en tehlikeli iznidir: sahte kayıt üretir.
   Bu kapı o iznin sınırlarını ölçer.

   Sınanan kusurlar:
     §1 Deneme çalışmasının arşivde görünmesi. Bir sahte kayıt
        istatistiğe girerse sayılar yalan söyler; OAI ile toplanırsa
        dizinlere düşer; dökümde yer alırsa arşivin parmak izi bozulur.
     §2 Süzgecin birden çok yere yazılması. Her sayfa kendi süzgecini
        taşırsa, biri unutulduğu gün deneme kaydı oradan görünür.
     §3 Silmenin fazlasını silmesi. Temizlik aracı, işaret taşımayan bir
        kaydı silebiliyorsa artık temizlik aracı değildir.
     §4 Deneme kurmanın gerçek birine posta göndermesi.
     §5 Uçların kimlik istememesi.
   ===================================================================== */

$kok = dirname(__DIR__) . '/kutadgunet';
$hata = 0; $sira = 0;
function ol(string $ad, bool $ok, string $not = ''): void {
    global $hata, $sira;
    $sira++;
    if (!$ok) $hata++;
    printf("%s  %s%s\n", $ok ? ' OK ' : 'KUSUR', $ad, $not !== '' ? ('  -> ' . $not) : '');
}

$api  = (string)@file_get_contents($kok . '/api/index.php');
$veri = (string)@file_get_contents($kok . '/k/veri.php');

/* §1 Arşivin girişinde süzgeç var. */
ol('§1 k_yazilar deneme kayıtlarını süzüyor',
   preg_match('/function k_yazilar.*?!empty\(\$k\[\x27deneme\x27\]\).*?continue/s', $veri) === 1);

/* §2 Süzgeç TEK yerde. Sayfalar kendi süzgecini yazmamalı; yazsalardı
   biri unutulduğu gün kayıt oradan sızardı. Yönetim uçları hariçtir:
   onlar yazilar.json'u doğrudan okur ve deneme kayıtlarını GÖRMEK
   zorundadır, yoksa deneme yapılamaz. */
$suzenler = [];
$yineleyici = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
foreach ($yineleyici as $d) {
    $y = $d->getPathname();
    if (substr($y, -4) !== '.php') continue;
    $ad = str_replace($kok . '/', '', $y);
    /* İKİ TEK KAYNAK VARDIR, İKİSİ DE MUAFTIR:
         k/veri.php   çalışmaların süzgeci (k_yazilar)
         k/hesap.php  hesapların süzgeci (hs_kamusal, hs_deneme_adi)
       İkincisi 14 Ağustos 2026'da eklendi: hesaplar için süzgeç HİÇ
       yoktu ve deneme hakemi hakem dizinine düşüyordu. Muafiyet
       "burada yazılabilir" demek değil, "kural burada TANIMLANIR"
       demektir; sayfalar yine kendi ölçütünü yazamaz. */
    if ($ad === 'k/veri.php' || $ad === 'k/hesap.php' || $ad === 'api/index.php') continue;
    $i = (string)@file_get_contents($y);
    foreach (explode("\n", $i) as $n => $satir) {
        $kirp = ltrim($satir);
        if ($kirp === '' || $kirp[0] === '*' || strpos($kirp, '//') === 0 || strpos($kirp, '/*') === 0) continue;
        if (strpos($satir, "'deneme'") !== false) $suzenler[] = $ad . ':' . ($n + 1);
    }
}
ol('§2 süzgeç tek yerde (sayfalar kendi süzgecini yazmıyor)', $suzenler === [], implode(' | ', $suzenler));

/* §3 Silme yalnızca işaretlileri siler. */
ol('§3a silme hesapta işaret arıyor',
   strpos($api, "if (is_array(\$h) && !empty(\$h['deneme'])) { \$hSil++; continue; }") !== false);
ol('§3b silme çalışmada işaret arıyor',
   strpos($api, "if (is_array(\$e) && !empty(\$e['deneme'])) { \$ySil++; continue; }") !== false);

/* §4 Adresler teslim edilemez bir alan adında. 'invalid' RFC 6761 ile
   ayrılmıştır ve hiçbir posta sunucusu ona teslim yapamaz. */
ol('§4 deneme adresleri teslim edilemez alan adında',
   strpos($api, '@deneme.gecersiz') !== false);

/* §5 Bütün uçlar yönetim yetkisi istiyor. 'deneme-giris' bu listeye
   sonradan katıldı ve en tehlikeli olanıdır: bir kimliğe PAROLASIZ
   girmeyi sağlar. Yetki denetimi unutulursa, ucun adını bilen herkes
   deneme hakemi olarak içeri girer. */
foreach (['deneme-kur', 'deneme-sil', 'deneme-giris'] as $uc) {
    $i = strpos($api, "/yonetim/{$uc}' && \$metod === 'POST'");
    $govde = $i !== false ? substr($api, $i, 400) : '';
    ol("§5 /{$uc} yönetim yetkisi istiyor", strpos($govde, 'yonetim_yazma_gerek();') !== false);
}

/* §6 Parola kayda düz girmiyor: deneme hesabı da gerçek gibi
   davranmalıdır, yoksa deneme değersizleşir. */
ol('§6 deneme parolası özetlenerek saklanıyor',
   strpos($api, "'parola' => password_hash(\$sifre, PASSWORD_DEFAULT)") !== false);


/* =====================================================================
   §7 DENEME HESABIYLA GİRİŞ — DAR KAPI
   ---------------------------------------------------------------------
   Bu uç bir kimliğe parolasız girer. Yanlış yazılırsa sistemin en
   tehlikeli ucudur; kapıları tek tek ölçülür. Ölçüm KODU okur, çünkü
   bu denetimlerin çalıştığını görmek için gerçek bir oturum açmak
   gerekirdi ve o oturum ölçümün kendisini yetkilendirirdi.
   ===================================================================== */
$i = strpos($api, "/yonetim/deneme-giris' && \$metod === 'POST'");
$govde = $i !== false ? substr($api, $i, 2600) : '';
ol('§7a uç var', $govde !== '');
ol('§7b hedefte deneme işareti aranıyor',
   strpos($govde, "empty(\$h['deneme'])") !== false);
ol('§7c adres deneme alan adında olmalı',
   strpos($govde, '@deneme\\.gecersiz$') !== false);
ol('§7d iki ölçüt de RED üretiyor (403)',
   substr_count($govde, "], 403);") >= 2);
ol('§7e girişten önce oturum tazeleniyor',
   strpos($govde, 'session_regenerate_id(true);') !== false);
ol('§7f kimin girdiği hedefin kaydına yazılıyor',
   strpos($govde, "'vekaleten'") !== false);
ol('§7g giriş kütüğe düşüyor',
   strpos($govde, "error_log('kutadgu/deneme-giris") !== false);

/* Panelde uyarı yazılı mı: tıklamadan SONRA öğrenilen bir oturum
   kapanması, kaydedilmemiş bir işi kaybettirir. */
$pn = (string)@file_get_contents($kok . '/panel.php');
ol('§7h panel oturumun kapanacağını önceden söylüyor',
   strpos($pn, 'kendi oturumunuzu kapatır') !== false);
ol('§7i panel yalnız deneme hesaplarına girilebildiğini söylüyor',
   strpos($pn, 'yalnızca deneme işaretli hesaplara girilebilir') !== false);
ol('§7j giriş ayrı bir düğme (satırın tamamı değil)',
   strpos($pn, "class=\"d d-ikinci d-kucuk dn-gir\"") !== false);


/* =====================================================================
   §8 DENEME KAYDI HİÇBİR YERE YAZILMAZ
   ---------------------------------------------------------------------
   Kurul isteği: "demo kullanıcılar aynı altyapıyı kullanmalı ama kayıt
   hiçbir yere yazılmadan silinebilmeli."

   AYNI ALTYAPI: aynı hesap dosyası, aynı giriş, aynı akış. Deneme
   hesabı gerçekten giriş yapabilmeli, yoksa deneme değersizleşir.

   HİÇBİR YERE YAZILMADAN: okurun gördüğü hiçbir listede bulunmayacak ve
   silindiğinde geri alınamaz bir iz bırakmayacak. Ölçülen üç iz:
     a) hakem dizini  — hesaplar için süzgeç YOKTU, ölçüldü ve kondu
     b) kişi sayfası  — /kisi/deneme-hakem sayfa açıyordu
     c) kalıcı numara — deneme çalışması bir tamga sırası TÜKETMEMELİ;
        tüketirse silindiğinde numarada kalıcı bir boşluk kalır ve
        "numara bir kez verilir, geri alınmaz" kuralı denemeyi kalıcı
        bir ize çevirir.
   ===================================================================== */
$hs = (string)@file_get_contents($kok . '/k/hesap.php');
$hk = (string)@file_get_contents($kok . '/hakemler.php');
$ks = (string)@file_get_contents($kok . '/kisi.php');

ol('§8a hesaplar için kamusal süzgeç var (hs_kamusal)',
   strpos($hs, 'function hs_kamusal(') !== false);
ol('§8b süzgeç deneme işaretine bakıyor',
   preg_match('/function hs_kamusal\(.*?!empty\(\$h\[\x27deneme\x27\]\).*?continue/s', $hs) === 1);
ol('§8c hakem dizini kamusal listeyi okuyor',
   strpos($hk, 'foreach (hs_kamusal() as $h)') !== false);
ol('§8d hakem dizini artık ham listeyi okumuyor',
   strpos($hk, 'foreach (hs_oku() as $h)') === false);
ol('§8e kişi sayfası deneme adını reddediyor',
   strpos($ks, 'hs_deneme_adi($anah)') !== false);
ol('§8f kişi sayfası kamusal listeyi okuyor',
   strpos($ks, 'foreach (hs_kamusal() as $h)') !== false);

/* Deneme çalışması kalıcı bir numara tüketmemeli. */
$i = strpos($api, "'slug' => 'deneme-calismasi-'");
$blok = $i !== false ? substr($api, max(0, $i - 900), 2600) : '';
ol('§8g deneme çalışması tamga sırası tüketmiyor',
   $blok !== '' && strpos($blok, 'tg_tamga_uret') === false && strpos($blok, 'tamga_sira') === false);
ol('§8h kimliği rastgele, sıradan değil',
   strpos($blok, "\$yid = 'deneme' . substr(hash('sha256', microtime())") !== false);

/* Silme iki dosyayı da temizliyor ve SAYIYI döndürüyor: kaç kaydın
   silindiğini söylemeyen bir temizlik, temizlendiğini kanıtlamaz. */
$j = strpos($api, "/yonetim/deneme-sil' && \$metod === 'POST'");
$sil = $j !== false ? substr($api, $j, 1800) : '';
ol('§8i silme hesap sayısını bildiriyor', strpos($sil, "'hesap'") !== false);
ol('§8j silme çalışma sayısını bildiriyor', strpos($sil, "'calisma'") !== false);

echo "\n" . ($hata === 0 ? "GEÇTİ" : "KALDI") . ": {$sira} ölçüm, {$hata} kusur\n";
exit($hata === 0 ? 0 : 1);
