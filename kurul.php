<?php
/* =====================================================================
   KUTADGU - Yayın kurulu / Editorial board
   ---------------------------------------------------------------------
   Baş editörlüğün üç basamağı vardır ve sayfa üçünü adlarıyla ayırır:

     KURUCU    Bir kayıttır. En üstte ve tek sırada durur; sırası kayıt
               sırasıdır, yani zaman sırası. Değişmez.
     GÖREVDEKİ Bir yetkidir. Bir alt satırda, kendi aralarında
               alfabetik. Her kayıtta yazılı bir süre bulunur.
     ONURSAL   Bir teşekkürdür. Üçüncü kümede, süresinin ne zaman
               dolduğu yazılı olarak durur.

   Okuyucu kimin hangi basamakta olduğunu tahmin etmek zorunda kalmaz.
   Editör kurulu ise sistemdeki kayıtlardan hesaplanır: kural açıktır,
   uygulaması denetlenebilir.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/hesap.php';

$en = k_en();

/* ---- Kişinin kendi kaydı, ayar dosyasının önüne geçer ----
   Ayar dosyasındaki unvan, ad, kurum ve resim yalnızca başlangıç
   değeridir. Kişi hesabına girip kendi unvanını yazdığında sayfada
   onunki görünür; kimsenin unvanı bir yapılandırma satırına
   hapsedilmez. Üç küme de bu tek işlevden geçer: ikinci bir
   zenginleştirme yazılsaydı biri ötekinin denetimlerini taşımazdı. */
function kr_kisilestir(array $liste): array {
    foreach ($liste as $i => $b) {
        /* Kaydın hesabı düz adresle ya da adres özetiyle bulunur;
           ayrımı hs_kayit_hesabi() yapar. Burada yalnızca düz adrese
           bakılsaydı, kurul-eposta.json bulunmayan bir kurulumda
           kartlar kişinin kendi unvanıyla değil, yapılandırmadaki
           başlangıç değeriyle basılırdı. */
        $h = hs_kayit_hesabi($b);
        if ($h === null) continue;
        $kendiUnvan = trim((string)($h['unvan'] ?? ''));
        $kendiAd    = trim((string)($h['ad'] ?? ''));
        if ($kendiUnvan !== '') $liste[$i]['unvan'] = $kendiUnvan;
        if ($kendiAd !== '')    $liste[$i]['ad']    = $kendiAd;
        if (trim((string)($h['kurum'] ?? '')) !== '') $liste[$i]['kurum'] = (string)$h['kurum'];
        if (trim((string)($h['orcid'] ?? '')) !== '') $liste[$i]['orcid'] = (string)$h['orcid'];
        /* Kartta tek bir dış bağlantı gösterilir: kişinin listesindeki
           ilki. Listenin tamamı kişi sayfasındadır; kart onun yerine
           geçmez. */
        $bg = tg_hesap_baglantilar($h);
        if ($bg) { $liste[$i]['web'] = $bg[0]['url']; $liste[$i]['web_ad'] = $bg[0]['ad']; }
    }
    return $liste;
}

/* Görevin hangi yolla bittiğini okunur biçimde yazar. Dört yol vardır
   ve hangisi olduğu gizlenmez: bir kişinin görevi neden bitti sorusu,
   kurul sayfasının cevaplaması gereken sorulardandır. */
function kr_bitis_ad(string $yol, bool $en): string {
    $m = [
        'devir' => ['devretti', 'handed over on'],
        'vefat' => ['vefat',    'died on'],
        'karar' => ['kurul kararı', 'by board decision on'],
        'sure'  => ['süre doldu', 'term ended on'],
        /* Beşinci yol: bir yıllık dönem sonunda etkinlik ölçütünün
           karşılanmaması (kurul kararı F1). Adı "görevden alındı"
           değildir ve olmamalıdır: kimse kimseyi almadı, sayım
           yapıldı. */
        'etkinlik' => ['dönem sonunda ölçüt karşılanmadı', 'criterion not met at end of term on'],
    ];
    return k_t(['tr' => $m[$yol][0], 'en' => $m[$yol][1]]) ?? (k_c('görev sona erdi', 'office ended on'));
}

/* ---- ÜÇ KÜME ----
   Üçünün de tek kaynağı ortak.php'dir. Bu sayfa kendi listesini
   kurmaz; kursaydı kümeler zamanla birbirine karışırdı. */
$kurucular  = kr_kisilestir(tg_kurucu_bas_editorler());     /* kayıt sırası, değişmez */
$gorevdeki  = kr_kisilestir(tg_gorevdeki_bas_editorler());  /* alfabetik */
$onursal    = kr_kisilestir(tg_onursal_bas_editorler());    /* görevi bitenler */
$sutun      = max(1, count($kurucular));

/* Bozuk bir kayıt (tarih biçimi hatalı, görev sonu kararında yeterli
   kurucu adı yok) göreve alınmaz. Sessizce yutulan bir yapılandırma
   hatası, olmayan bir hatadan kötüdür; sayfada görünür. */
$kayitHata = tg_gorev_kayit_hatalari();

/* ---- Editör kurulu ölçütü ---- */
$olcutYayin  = (int)tg_ayar('kurul_yayin', 20);
$olcutOnayli = (int)tg_ayar('kurul_onayli', 10);

/* ---- Kayıtlardan hesapla: kim ölçütü karşılıyor ---- */
$sayim = [];   /* ad anahtarı => ['ad'=>, 'yayin'=>, 'onayli'=>] */
foreach (k_yazilar() as $y) {
    $onayli = tg_onay_durumu($y)['onayli'];
    $adlar = [];
    $ilk = trim((string)($y['yazar'] ?? ''));
    if ($ilk !== '') $adlar[tg_ad_anahtar($ilk)] = $ilk;
    foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
        if (!is_array($ya)) continue;
        $n = k_unvan_ekle((string)($ya['unvan'] ?? ''), (string)($ya['ad'] ?? ''));
        if (trim($n) !== '') $adlar[tg_ad_anahtar($n)] = $n;
    }
    foreach ($adlar as $k => $ad) {
        if ($k === '') continue;
        if (!isset($sayim[$k])) $sayim[$k] = ['ad' => $ad, 'yayin' => 0, 'onayli' => 0];
        $sayim[$k]['yayin']++;
        if ($onayli) $sayim[$k]['onayli']++;
    }
}
/* Baş editörler kurul listesinde ayrıca gösterilmez */
$basAnahtar = [];
foreach ([$kurucular, $gorevdeki, $onursal] as $kume) {
    foreach ($kume as $b) $basAnahtar[] = tg_ad_anahtar((string)$b['ad']);
}

$kurul = [];
foreach ($sayim as $k => $s) {
    if (in_array($k, $basAnahtar, true)) continue;
    if ($s['yayin'] >= $olcutYayin && $s['onayli'] >= $olcutOnayli) $kurul[] = $s;
}
usort($kurul, fn($a, $b) => $b['onayli'] <=> $a['onayli']);

/* ---- Baş editörlerce atanmış editörler (veri dosyasından) ---- */
$editorler = [];
foreach (k_json('editorler.json', []) as $e) {
    if (!is_array($e) || trim((string)($e['ad'] ?? '')) === '') continue;
    /* Profil resmi hesaptan gelir; editör kaydında tutulmaz */
    $eh = hs_kayit_hesabi($e);
    $e['resim'] = $eh ? tg_resim_yolu($eh) : '';
    $ebg = $eh ? tg_hesap_baglantilar($eh) : [];
    if ($ebg) { $e['web'] = $ebg[0]['url']; $e['web_ad'] = $ebg[0]['ad']; }
    $editorler[] = $e;
}

/* ---- Ölçüte en yaklaşanlar: kural somut görünsün ---- */
$yaklasan = [];
foreach ($sayim as $k => $s) {
    if (in_array($k, $basAnahtar, true)) continue;
    if ($s['yayin'] >= $olcutYayin && $s['onayli'] >= $olcutOnayli) continue;
    if ($s['yayin'] >= 3) $yaklasan[] = $s;
}
usort($yaklasan, fn($a, $b) => ($b['onayli'] <=> $a['onayli']) ?: ($b['yayin'] <=> $a['yayin']));
$yaklasan = array_slice($yaklasan, 0, 8);

/* ---- Atama düzeni ve mali oy ---- */
$atama       = tg_bas_atama();
$oyKurulSay  = count(tg_atama_oy_kurulu());
$bosKoltuk   = tg_bos_koltuk();
$koltuk      = tg_kurul_koltuk();
$devralanVar = tg_devralan_var();
$zorunlu     = tg_zorunlu_haller();
$zorunluAcik = tg_zorunlu_hal();
/* Kaydında yazılı bir editör yetkisi bitişi olanlar. Bu tarih YALNIZCA
   editör listesine ekleme ve çıkarmayı ilgilendirir; göreve dokunmaz. */
$edSureli = [];
foreach (tg_kurucu_bas_editorler() as $b) {
    $bt = tg_editor_yetki_bitis($b);
    if ($bt !== '') $edSureli[$bt][] = (string)$b['ad'];
}
$oyKurulu = tg_mali_oy_kurulu();
$ilkeler  = tg_degismez_ilkeler();

$ekBas = <<<CSS
<style>
/* Kart, kişi kartı, kutu, rozet ve çizelge dizgeden gelir. Burada
   yalnızca kurul sayfasının kendi listeleri ve ölçüt maddeleri durur. */
.kr{min-width:0}
/* Sağ raydaki içindekiler listesinden atlanan başlıklar yapışkan üst
   çubuğun altında kalmasın. */
.kr h2{scroll-margin-top:calc(var(--ust) + var(--b-5));margin:var(--b-6) 0 var(--b-2)}
/* Süreli yetki uyarısı: bir kutu ve yanında bir saat imi. */
.kr-sira{display:flex;gap:var(--b-3);align-items:flex-start;margin-top:var(--b-4)}
.kr-sira svg{flex:none;color:var(--kut);margin-top:2px}
.kr-sira p{margin:0 0 var(--b-1)}
.kr-sira p:last-child{margin-bottom:0}
.kr-liste{align-items:start;margin:var(--b-4) 0}
/* ÜÇ KÜME DE AYNI RİTİMDE (.kr-bes).
   Beş kurucu üç artı iki diye kırıldığında, ilk satırdaki üç ad öne
   çıkmış gibi duruyordu. Oysa kurucu satırı bir sıralama değil bir
   kayıttır. Beş kart yan yana sığsın diye bu listede kart dikey
   kurulur: resim üstte, ad ve kurum altında ortalanmış. Yer yetmezse
   yine alt alta geçer, çünkü sıkışmış bir ad okunmayan bir addır.

   Görevdeki ve onursal kümeler de aynı sütun genişliğini kullanır.
   Kümeler arasında kart boyu değişseydi, geniş kart önemli görünürdü;
   burada üç kümenin farkı yerleri ve adlarıdır, boyları değil. */
.kr-bes-kap{container-type:inline-size}
.kr-bes{grid-template-columns:repeat(auto-fit,minmax(190px,1fr));align-items:stretch}
/* Beş kart tek satıra sığmıyorsa kaç sütun olacağı BİLEREK seçilir.
   auto-fit'e bırakıldığında 936 piksellik bir sütunda dört kart yan yana
   diziliyor, beşinci tek başına alt satıra düşüyordu; tek başına kalan
   kart, listede bir eksiklik varmış gibi duruyor. Üç sütunda ise satırlar
   üç ve iki olur, kartlar 300 pikselin üstüne çıkar ve hiçbir ad
   kırılmaz. Bu bir sıralama değildir; üç kümede de aynı ritim geçerli
   olduğu için üstteki satır bir öncelik anlamına gelmez. */
@container (min-width:640px) and (max-width:1039px){
  .kr-bes{grid-template-columns:repeat(3,minmax(0,1fr))}
}
/* Sütun sayısını EKRAN değil KAPSAYICI belirler. Ekran genişliğine
   bakmak burada yanlış sonuç veriyordu: 1890 pikselde beş kart tek
   satıra iniyor ama 1400 pikselde dörde düşüyordu, çünkü metin
   sütununun genişliği ekranla doğru orantılı değil; araya sol menü ve
   sağ ray giriyor. Kapsayıcı sorgusu doğrudan sütunun kendi genişliğini
   ölçer, yani soruyu doğru yere sorar. */
/* Eşik 640 pikselden 1040'a çıkarıldı. 640'ta beş kart yan yana
   diziliyordu ama her birine 120 piksel düşüyordu: ad iki satıra
   kırılıyor, kart sıkışıyordu. Bir adı sıkıştırmak, onu okunmaz
   kılmanın en sessiz yoludur. Beş kartın rahat durması için karta en az
   190 piksel gerekir; beş kart ve aralar 1040 pikselde tam oturur.
   Altında auto-fit kuralı geçerlidir ve kartlar alt alta geçer. */
@container (min-width:1040px){
  /* Sütun sayısı kurucu sayısıdır; sayı ayar.php'den gelir ve liste
     büyürse düzen kendiliğinden uyar. Sabit bir 5 yazmak, altıncı bir
     ad eklendiği gün sessizce bozulurdu. Görevdeki ve onursal kümeler
     aynı sayıyı kullanır; kart sayısı azsa hücreler soldan dolar. */
  .kr-bes{grid-template-columns:repeat(var(--kurucu-sayi,5),minmax(0,1fr))}
}
/* Kart daraldıkça kurum adı satır satır kırılıp kartı uzatıyordu; 1320
   pikselde kart boyu 532 piksele çıkıyordu. Dar kartta kurum adı
   gösterilmez. Bu bir kayıp değil: sayfa zaten unvan yazmıyor, aynı
   gerekçeyle. Kurum, karta tıklayınca açılan kişi sayfasında tam
   hâliyle durur. */
/* Eşik 879'dan 700'e indirildi. Kurum adı, kart dar olduğu için
   gizleniyordu; kart sayısı üçe düşünce kart 260 pikselin üstüne çıktı
   ve kurum adı iki satırda rahat duruyor. Gizlemenin sebebi kartın
   uzaması ve adın kırılmasıydı; sebep ortadan kalkınca kural da
   daraltıldı. */
@container (max-width:699px){
  .kr-bes .kk-kurum,.kr-bes .kk-orcid{display:none}
}
/* Kartlar eşit boyda tutulur; farklı boyda kartlar, uzun olanı önemli
   gibi gösterir. */
.kr-bes .kk-sar{height:100%}
.kr-bes .kk{flex-direction:column;align-items:center;text-align:center;
  gap:var(--b-2);padding:var(--b-4) var(--b-3);height:100%}
.kr-bes .kk-ic{flex:0 1 auto;text-align:center}
/* Ölçütü karşılayanların kartındaki baş harf dairesi. */
.kr-im{width:var(--b-7);height:var(--b-7);font-size:var(--y-3)}
/* Küme başlığının hemen altındaki tek satırlık tanım. Kümenin ne
   olduğunu okumak için aşağıdaki bölümlere gitmek gerekmesin. */
.kr-kume{color:var(--metin-2);font-size:var(--y-4);margin:0 0 var(--b-3)}
/* Ölçüt maddeleri tek bir kutu içinde, aralarında ince çizgiyle durur.
   Önce her madde ayrı bir karttı ve aralarındaki boşluk üç ayrı bilgi
   izlenimi veriyordu; oysa bunlar tek bir ölçütün maddeleridir,
   birlikte okunmaları gerekir. */
.kr-olcut{counter-reset:ko;list-style:none;padding:0;margin:var(--b-4) 0;display:grid;gap:0;
  background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-3);overflow:hidden}
.kr-olcut li{counter-increment:ko;position:relative;border-top:1px solid var(--cizgi);
  padding:var(--b-3) var(--b-4) var(--b-3) var(--b-7);font-size:var(--y-4)}
.kr-olcut li:first-child{border-top:0}
.kr-olcut li::before{content:counter(ko);position:absolute;left:var(--b-4);top:var(--b-3);
  width:var(--b-5);height:var(--b-5);border-radius:50%;
  background:var(--kut-zemin);color:var(--kut);font-family:var(--mono);
  font-size:var(--y-2);font-weight:700;display:grid;place-items:center}
.kr-not{font-size:var(--y-3);color:var(--metin-2);border-top:1px solid var(--cizgi);
  padding-top:var(--b-4);margin-top:var(--b-6)}
/* Art arda gelen açıklama paragraflarında çizgi bir kez çekilir. */
.kr-not-duz{border-top:0;padding-top:0;margin-top:var(--b-4)}
/* Zorunlu hâl çizelgesinde hâlin kod adı: makine okunur anahtar, insan
   okunur adın altında küçük ve tek aralıklı durur. */
.kr-kod{font-family:var(--mono);font-size:var(--y-1);color:var(--metin-2)}
.kr-cizelge,.kr-oy{margin-top:var(--b-4)}
.kr-cizelge th:nth-child(2),.kr-cizelge th:nth-child(3){text-align:right}
.kr-cizelge td:nth-child(2),.kr-cizelge td:nth-child(3){text-align:right;font-family:var(--mono);font-size:var(--y-2)}
/* Sekiz değiştirilemez ilke: numarası koyu, adı ayrı satırda. Ölçüt
   listesiyle aynı aileden, çünkü ikisi de numaralı ve bağlayıcı. */
.kr-ilke li b{display:block;margin-bottom:var(--b-1)}
/* ---- KURUL KARARLARI ----
   Kararın metni bir alıntı gibi durur; oylar altında, her biri adı ve
   gerekçesiyle. Renk tek başına anlam taşımaz: rozetin yazısı hâli zaten
   söylüyor, renk yalnız pekiştiriyor. */
/* AÇIKLAMA PARAGRAFI OKUMA ÖLÇÜSÜNDE KALIR. Bu sayfa 'genis' ölçüyle
   açılıyor (kart ızgaraları ve çizelgeler için); düz bir paragraf o
   genişlikte 1060 piksele uzuyor ve bosluk-kapi bunu "dizgenin en geniş
   okuma ölçüsünü aşıyor" diye yakaladı. Çizelge geniş olabilir, cümle
   olamaz.

   SINIR DİZGENİN KENDİ DEĞİŞKENİDİR (--olcu-metin-genis, 92ch): ilk
   denemede --en-metin yazılmıştı ve tutmadı, çünkü o değişken bu
   sayfada geniş ölçüye ayarlıdır. Piksel yazmak da olmazdı: 'ch'
   ögenin kendi yazı tipiyle ölçülür, sabit bir sayı yazı tipi
   değiştiğinde yanılır — kapı da tam olarak bu değişkenle ölçüyor. */
.kk-ack-p{max-width:var(--olcu-metin-genis)}
.kk-kamu{margin-top:var(--b-4)}
.kk-kamu h3{margin:0 0 var(--b-2)}
.kk-ust{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;margin:0 0 var(--b-3)}
.kk-ack{font-size:var(--y-2);color:var(--metin-2)}
.kk-metin{white-space:pre-wrap;border-left:2px solid var(--cizgi);padding-left:var(--b-3);
  margin:0;color:var(--metin-2);line-height:var(--sh-genis)}
.kk-oylar{display:grid;gap:var(--b-2);margin-top:var(--b-4)}
.kk-oy{border-top:1px solid var(--cizgi);padding-top:var(--b-2);font-size:var(--y-3)}
.kk-oy span:last-child{display:block;color:var(--metin-2);margin-top:var(--b-1)}
</style>
CSS;

k_bas([
    /* Bu sayfa düz yazı değil: beş kişilik kart ızgaraları, oy çizelgesi
       ve zorunlu hâl çizelgesi taşıyor. Okuma ölçüsünde sütun 980
       pikselde kalıyor, beş kart 180 piksere sıkışıyor ve paragrafın
       sağında 200 piksel boşluk kalıyordu. Geniş ölçü ikisini birden
       düzeltir: kart rahatlar, paragraf sütunu doldurur. */
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Yayın kurulu', 'Editorial board'),
    'yol'    => '/kurul.php',
    'aciklama' => k_c(
        'Kutadgu baş editörleri, editör kurulu ve kurula girme ölçütleri.',
        'The chief editors of Kutadgu, the editorial board and the criteria for joining it.'
    ),
    'ek_bas' => $ekBas,
]);

/* Baş harfler: Ad SOYAD -> AS */
function kr_bas_harf(string $ad): string {
    $p = preg_split('/\s+/u', trim(preg_replace('/\b(prof|doç|doc|dr)\b\.?/iu', '', $ad)));
    $p = array_values(array_filter($p));
    if (!$p) return '?';
    $ilk = mb_strtoupper(mb_substr($p[0], 0, 1, 'UTF-8'), 'UTF-8');
    $son = count($p) > 1 ? mb_strtoupper(mb_substr(end($p), 0, 1, 'UTF-8'), 'UTF-8') : '';
    return $ilk . $son;
}
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Kurul', 'Board') ?></span>
      <h1><?= k_c('Yayın kurulu', 'Editorial board') ?></h1>
      <p><?= k_c(
      'Bu sayfada kurulun kimlerden oluştuğu ve kurula kimin hangi ölçütle girdiği açıkça yazılıdır. Kurul üyeliği bir unvan değil, bir sorumluluktur: hakem atamak, editöryal not düşmek ve bunların hepsinin kaydının okuyucuya görünmesini kabul etmek anlamına gelir.',
      'This page states plainly who sits on the board and by what criterion each member joined it. Membership is not a title but a duty: it means assigning reviewers, adding editorial notes, and accepting that the record of all of it is visible to the reader.'
    ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="kr blg-ic">

    <?php /* SAYFANIN İŞİ KURULU GÖSTERMEKTİR.
             ÖLÇÜLEN KUSUR — 14 Ağustos 2026 (kurul bildirimi): sayfayı
             açan kişi kurulu göremiyordu. Dört paragraflık açıklama
             ilk ekranı tamamen dolduruyor, adların ilki katlanmanın
             ALTINDA kalıyordu. Üstelik birinci paragraf, sağdaki "Üç
             küme" kutusunun söylediğinin uzun hâliydi: aynı şey iki kez
             yazılmıştı ve uzun olanı yukarıdaydı.

             Açıklama SİLİNMEDİ — bu sayfanın yarısı kuralın kendisidir
             ve kural gizlenmez. Katlandı: iki cümlelik giriş açıkta
             kalır, ayrıntı isteyen kutuyu açar. Katlanan bölüm
             <details> ile yapılır; betik kapalıyken de açılır ve
             içindeki metin sayfa kaynağında her koşulda durur. */ ?>
    <p><?= k_c(
      'Bu kurulda üç basamak vardır ve üçü birbirinin yerine geçmez: <b>kurucu</b> bir kayıttır, <b>görevdeki</b> bir yetkidir, <b>onursal</b> bir teşekkürdür. Aşağıdaki üç küme bu üç basamağa karşılık gelir; karta tıklayan o kişinin sistemdeki bütün kaydına gider.',
      'This board has three steps and none stands for another: <b>founding</b> is a record, <b>in office</b> is an authority, <b>honorary</b> is a thank you. The three groups below correspond to those three steps; clicking a card takes you to that person\'s full record in the system.'
    ) ?></p>

    <details class="kr-ack">
      <summary><?= k_c('Basamaklar, görevin nasıl bittiği ve boşalan koltuk', 'The steps, how an office ends, and the empty seat') ?></summary>
      <p><?= k_c(
      'Baş editörlüğün üç basamağı vardır ve üçü birbirinin yerine geçmez. <b>Kurucu baş editörlük bir kayıttır</b>: sistemin kimin elinden çıktığı olmuş bir şeydir, hiçbir zaman düşmez ve sonradan hiç kimseye verilemez. <b>Görevdeki baş editörlük bir yetkidir</b>: hakem atamak, editör eklemek, editöryal not düşmek. <b>Onursal baş editörlük bir teşekkürdür</b>: verilmez, görev sona erdiğinde kalır ve hiçbir yetki taşımaz.',
      'Chief editorship has three steps and none of them stands for another. <b>Founding chief editorship is a record</b>: whose hands the system came from is something that happened, it never lapses and it can be granted to no one afterwards. <b>Chief editorship in office is an authority</b>: assigning reviewers, adding editors, recording editorial notes. <b>Honorary chief editorship is a thank you</b>: it is not granted, it remains once the office has ended, and it carries no authority.'
      ) ?></p>
      <p><?= k_c(
      '<b>Görev takvimle bitmez, ölçümle biter.</b> Bir tarihin gelmesi kimsenin yetkisini sessizce almaz. Dört yol yazılı bir kayıttır ve dördü de görünürdür: kişinin kendi devri, vefatı, kurucuların çoğunluk kararı, ve atamaya bilerek yazılmış bir bitiş tarihi. Beşinci yol bir sayımdır ve yalnız atanmış baş editörlere işler: görev bir yıllık dönemlere bölünür ve dönem sonunda etkinlik ölçütü karşılanmamışsa o gün sona erer. Bu bir görevden alma değildir; kimse kimseyi almaz, sayım yapılır ve sayım herkese açıktır. Kuruculara işlemez, çünkü kurucu baş editörlük bir görev değil bir kayıttır.',
      '<b>An office does not end by the calendar; it ends by measurement.</b> The arrival of a date takes no one\'s authority away in silence. Four routes are a written record and all four are visible: the person\'s own handover, their death, a majority decision of the founders, and an end date deliberately written into the appointment. A fifth route is a count, and it applies only to appointed chief editors: the office runs in one-year terms, and if the activity criterion is not met at the end of a term the office ends that day. This is not a removal; no one removes anyone, a count is made and the count is open to all. It does not apply to founders, because founding chief editorship is a record and not an office.'
      ) ?></p>
      <p><?= k_c(
      'Boşalan koltuğu görevdeki baş editörler doldurur. Kurulun koltuk sayısı ' . (int)$koltuk . ' olarak yazılıdır; bugün ' . (int)$oyKurulSay . ' kişi görevdedir' . ($bosKoltuk > 0 ? ' ve ' . (int)$bosKoltuk . ' koltuk boştur' : ' ve boş koltuk yoktur') . '.',
      'A seat that falls empty is filled by the chief editors in office. The number of seats on the board is written as ' . (int)$koltuk . '; today ' . (int)$oyKurulSay . ' are in office' . ($bosKoltuk > 0 ? ' and ' . (int)$bosKoltuk . ' seats are empty' : ' and no seat is empty') . '.'
      ) ?></p>
      <p><?= k_c(
      'E-posta adresleri yalnızca giriş ve davet içindir; hiçbir sayfada gösterilmez.',
      'E mail addresses are used only for signing in and for invitations; they are shown on no page.'
      ) ?></p>
    </details>

    <p><a class="d d-ikinci d-git" href="<?= k_esc(k_bag('/bildiri.php#b-devir')) ?>"><?= k_c('Bildiride ayrıntısı', 'Set out in the declaration') ?></a></p>

    <?php if ($kayitHata): ?>
    <div class="kutu kutu-kir">
      <p><b><?= k_c('Yapılandırma uyarısı', 'Configuration warning') ?></b></p>
      <p><?= k_c(
        'Aşağıdaki kayıtlar göreve alınmadı, çünkü her atamada yazılı bir süre bulunması gerekir. Süresiz bir görev, yazılı olmayan bir yetkidir.',
        'The records below were not put into office, because every appointment must carry a written term. An office without a term is an authority that is not written down.'
      ) ?></p>
      <ul><?php foreach ($kayitHata as $kh): ?><li><?= k_esc($kh) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php /* ---- UYARI, HATA DEĞİL ----
             Eksik atama tarihi kaydı DÜŞÜRMEZ: kişi görevde kalır. Ama
             o kayıtta görev süresi ölçülemez, yani etkinlik ölçütü
             fiilen kapalıdır ve bunun görünmemesi ölçütü sessizce
             kaldırmanın en kolay yolu olurdu. İki liste bilerek ayrı:
             hata ile uyarıyı aynı kutuya koymak, ya hataları
             hafifletir ya uyarıları korkutur. */ ?>
    <?php $kayitUyari = function_exists('tg_gorev_kayit_uyarilari') ? tg_gorev_kayit_uyarilari() : []; ?>
    <?php if ($kayitUyari): ?>
    <div class="kutu kutu-kut">
      <p><b><?= k_c('Görev süresi ölçülemeyen kayıtlar', 'Records whose term cannot be measured') ?></b></p>
      <p><?= k_c(
        'Aşağıdaki kişiler görevdedir ve yetkileri tamdır; ancak kayıtlarında atama tarihi bulunmadığı için bir yıllık dönem hiç başlamamıştır ve etkinlik ölçütü onlarda işlemez. Eksik alan ayar dosyasına yazıldığı gün ölçüm başlar.',
        'The people below are in office and their authority is complete; but because their record carries no appointment date, no one-year term has begun and the activity criterion does not run for them. Measurement begins on the day the missing field is written into the configuration file.'
      ) ?></p>
      <ul><?php foreach ($kayitUyari as $ku): ?><li><?= k_esc($ku) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <?php /* ================= KÜME 1: KURUCULAR ================= */ ?>
    <h2 id="kurucu"><?= k_c('Kurucu baş editörler', 'Founding chief editors') ?></h2>
    <p class="kr-kume"><?= k_c(
      'Bir kayıttır. Her zaman en üstte ve tek sırada durur; sırası kayıt sırasıdır ve değişmez.',
      'A record. It always stands at the top and on a single line; the order is the order of the record and does not change.'
    ) ?></p>
    <p><?= k_c(
      'Aşağıdaki adlar sistemin kurucu baş editörleridir ve bu sistemi kuranın hocalarıdır. Sıra bir derece anlamı taşımaz: bir kayıt zaman sırasına göre durur, bu liste de öyle durmaktadır. Alfabetik dizim ikinci kümede, yani sonradan katılanlar arasında kullanılır; orada amaç hiçbir öncelik kurmamaktır. Burada ise değiştirilecek bir şey yoktur, çünkü kaydın kendisi değiştirilmez.',
      'The names below are the founding chief editors of the system, and the teachers of the person who founded it. The order carries no sense of rank: a record stands in the order of time, and so does this list. Alphabetical ordering is used in the second group, among those who joined later, where the point is to establish no precedence. Here there is nothing to reorder, because the record itself is not altered.'
    ) ?></p>
    <?php /* Kuruluş kadrosunun neden ölçütsüz olduğu ve devralan kurumun
             elinin neden bağlı olmadığı burada açıkça yazılır. İkisi de
             sorulmadan söylenmelidir; sorulunca söylenen bir açıklama
             savunma gibi okunur. */ ?>
    <p><?= k_c(
      'Bir noktayı açıkça yazmak gerekir. Kuruluş kadrosu, sistemin kendi ölçütüyle seçilmedi; sistem henüz yokken uygulanacak bir ölçüt de yoktu. Bu, kuralın tek istisnasıdır, süreli tutulmuştur ve tekrarlanmayacaktır: kuruluş listesinden sonra hiç kimse kurucu baş editör yapılamaz. Bu bir yetki kısıtı değil, kaydın bütünlüğüdür ve aynı ilke kuruculara da uygulanır; onların kaydı da geriye dönük düzeltilemez.',
      'One point should be stated plainly. The founding cohort was not selected by the system\'s own criterion; while the system did not yet exist, there was no criterion to apply. This is the single exception to the rule, it has been given a time limit, and it will not be repeated: after the founding list, no one can be made a founding chief editor. This is not a restriction of authority but the integrity of the record, and the same principle applies to the founders themselves; their record is not corrected retrospectively either.'
    ) ?></p>
    <p><?= k_c(
      'Devralacak bir kuruma da şunu belirtmek isteriz: kurucu kaydı hiçbir yetki, hiçbir veto ve hiçbir öncelik taşımaz. Sistemi üstlenen kurum, ilk gün bütünüyle yeni bir kurul atayabilir; kurucu adları o gün de sayfada durmayı sürdürür, çünkü onlar bir görev değil bir tarih kaydıdır.',
      'To an institution considering taking the system over we would add this: the founding record carries no authority, no veto and no priority. An institution that assumes the system may appoint an entirely new board on its first day; the founders\' names will still stand on this page, because they are a record of history and not an office.'
    ) ?></p>

    <?php
    /* Süreli yetkiler açıkça yazılır. Görünmeyen bir yetkinin ne zaman
       başlayıp ne zaman bittiği tartışılamaz; bu sistemde tartışılabilir
       olması gerekir. Süre yalnızca editör atamayı kapatır, kurucu baş
       editörlük kaydına dokunmaz. */
    /* Bir zamanlar burada, kurucular kümesinin başında "şu adların editör
       atama yetkisi 31 Aralık 2027 tarihine kadardır" diyen bir uyarı
       kutusu vardı. Kaldırıldı ve yerine bir şey konmadı; sebebi iki
       tane.

       Birincisi doğruluk. O cümle yetkinin o gün kapandığını söylüyor,
       zorunlu hâllerde açıldığını söylemiyordu. Bugün geçerli olan
       'devralan-yok' hâli sürdüğü sürece yetki 1 Ocak 2028'de de açık
       kalacak; yani kutu, olmayacak bir şeyi olacak gibi yazıyordu.

       İkincisi tekrar. Aynı kural aşağıda "Yeni baş editör atama"
       bölümünde, dört zorunlu hâlin çizelgesiyle ve bugünkü durumuyla
       birlikte zaten yazılı. Bir kuralı iki yerde yazmak, ikisinden
       birinin er geç ötekinin tersini söylemesi demektir; bu sayfa bunun
       bir örneğini yaşadı ve ders alındı.

       Kimin kaydında yazılı bir tarih olduğu bilgisi kaybolmadı: aşağıda
       atama bölümünde, ilgili maddede adlarıyla duruyor. */
    ?>

    <div class="kr-bes-kap">
    <div class="dizi dizi-3 kr-liste kr-bes" style="--kurucu-sayi:<?= (int)$sutun ?>">
      <?php foreach ($kurucular as $b): ?>
      <?php /* Kartta unvan yazılmaz: bu sayfa bir unvan listesi değildir.
               Unvan, kişinin kendi sayfasında ve çalışmalarında görünür.
               Karta tıklayan o sayfaya gider. */
      echo k_kisi_kart([
          'ad'    => (string)$b['ad'],
          'kurum' => (string)($b['kurum'] ?? ''),
          'orcid' => (string)($b['orcid'] ?? ''),
          /* Resim önce kişinin kendi hesabından alınır. Ayar dosyasındaki
             değer yalnızca hesabı olmayan ya da resim yüklememiş kişi
             içindir. */
          'resim' => tg_kurul_resmi($b),
          'web'   => (string)($b['web'] ?? ''),
          'web_ad'=> (string)($b['web_ad'] ?? ''),
          /* Onursal sıfat kurucu kaydının yerine geçmez, yanına yazılır:
             biri olguyu, öteki teşekkürü anlatır. */
          'alt'   => empty($b['gorevde'])
              ? k_c('Kurucu ve onursal baş editör · ' . kr_bitis_ad((string)($b['gorev_sonu_yol'] ?? ''), false)
                      . ': ' . hs_tarih_yaz((string)($b['gorev_sonu_tarih'] ?? ''), false),
                    'Founding and honorary chief editor · ' . kr_bitis_ad((string)($b['gorev_sonu_yol'] ?? ''), true)
                      . ' ' . hs_tarih_yaz((string)($b['gorev_sonu_tarih'] ?? ''), true))
              : k_c('Kurucu baş editör', 'Founding chief editor'),
      ], false); ?>
      <?php endforeach; ?>
    </div>
    </div><!-- /kr-bes-kap -->

    <?php /* ================= KÜME 2: GÖREVDEKİLER ================= */ ?>
    <h2 id="gorevdeki"><?= k_c('Görevdeki baş editörler', 'Chief editors in office') ?></h2>
    <p class="kr-kume"><?= k_c(
      'Bir yetkidir. Kurucuların bir alt satırında, kendi aralarında alfabetik olarak durur; her kayıtta yazılı bir süre bulunur.',
      'An authority. It stands on the line below the founders, in alphabetical order among themselves; every record carries a written term.'
    ) ?></p>
    <p><?= k_c(
      'Bu küme sonradan atananları tutar. Alfabetik sıra, kimseyi kayırmayan ve herkesin bildiği tek ölçüttür; katılma sırası burada bir üstünlük anlamı taşımasın diye kullanılmaz. Kartın altında görevin yazılı bitiş günü yazar. O gün geldiğinde kişi kendiliğinden üçüncü kümeye geçer; bunun için bir karar alınması ya da birinin bir şey yapması gerekmez.',
      'This group holds those appointed later. Alphabetical order is the one measure that favours no one and that everyone knows; the order of joining is not used, so that it should carry no sense of precedence here. The written end date of the office is shown beneath each card. On that day the person passes to the third group of their own accord; no decision needs to be taken and no one needs to do anything.'
    ) ?></p>
    <?php if ($gorevdeki): ?>
    <div class="kr-bes-kap">
    <div class="dizi dizi-3 kr-liste kr-bes" style="--kurucu-sayi:<?= (int)$sutun ?>">
      <?php foreach ($gorevdeki as $b): ?>
      <?php echo k_kisi_kart([
          'ad'    => (string)$b['ad'],
          'kurum' => (string)($b['kurum'] ?? ''),
          'orcid' => (string)($b['orcid'] ?? ''),
          'resim' => tg_kurul_resmi($b),
          'web'   => (string)($b['web'] ?? ''),
          'web_ad'=> (string)($b['web_ad'] ?? ''),
          'alt'   => k_c(
              'Görevdeki baş editör · süre: ' . hs_tarih_yaz((string)$b['gorev_bitis'], false),
              'Chief editor in office · term to ' . hs_tarih_yaz((string)$b['gorev_bitis'], true)
          ),
      ], false); ?>
      <?php endforeach; ?>
    </div>
    </div><!-- /kr-bes-kap -->
    <?php else: ?>
    <p class="kutu"><?= k_c(
      'Bu kümede şu an kimse yok. Sistem yeni kuruldu ve henüz atama yapılmadı; yapılan her atama, süresiyle birlikte burada görünecektir.',
      'There is no one in this group at present. The system is newly founded and no appointment has yet been made; every appointment made will appear here together with its term.'
    ) ?></p>
    <?php endif; ?>

    <?php /* ================= KÜME 3: ONURSALLAR ================= */ ?>
    <h2 id="onursal"><?= k_c('Onursal baş editörler', 'Honorary chief editors') ?></h2>
    <p class="kr-kume"><?= k_c(
      'Bir teşekkürdür. Görevi sona ermiş olanlar burada durur; görevin ne zaman ve hangi yolla sona erdiği yazılıdır. Önce kurucular, kuruluş kaydındaki sırayla; sonra öteki baş editörler, alfabetik.',
      'A thank you. Those whose office has ended stand here; when and by which route the office ended is written. First the founders, in the order of the founding record; then the other chief editors, alphabetically.'
    ) ?></p>
    <?php if ($onursal): ?>
    <div class="kr-bes-kap">
    <div class="dizi dizi-3 kr-liste kr-bes" style="--kurucu-sayi:<?= (int)$sutun ?>">
      <?php foreach ($onursal as $b): ?>
      <?php echo k_kisi_kart([
          'ad'    => (string)$b['ad'],
          'kurum' => (string)($b['kurum'] ?? ''),
          'orcid' => (string)($b['orcid'] ?? ''),
          'resim' => tg_kurul_resmi($b),
          'web'   => (string)($b['web'] ?? ''),
          'web_ad'=> (string)($b['web_ad'] ?? ''),
          'alt'   => k_c(
              (!empty($b['kurucu']) ? 'Kurucu ve onursal baş editör' : 'Onursal baş editör')
                . ' · ' . kr_bitis_ad((string)($b['gorev_sonu_yol'] ?? ''), false)
                . ': ' . hs_tarih_yaz((string)($b['gorev_sonu_tarih'] ?? ''), false),
              (!empty($b['kurucu']) ? 'Founding and honorary chief editor' : 'Honorary chief editor')
                . ' · ' . kr_bitis_ad((string)($b['gorev_sonu_yol'] ?? ''), true)
                . ' ' . hs_tarih_yaz((string)($b['gorev_sonu_tarih'] ?? ''), true)
          ),
      ], false); ?>
      <?php endforeach; ?>
    </div>
    </div><!-- /kr-bes-kap -->
    <?php else: ?>
    <p class="kutu"><?= k_c(
      'Bu kümede şu an kimse yok. Onursal baş editörlük yalnızca bir görev sona erdiğinde doğar; henüz sona ermiş bir görev bulunmuyor.',
      'There is no one in this group at present. Honorary chief editorship arises only when an office ends; no office has ended yet.'
    ) ?></p>
    <?php endif; ?>
    <ol class="kr-olcut">
      <li><?= k_c(
        '<b>Bu sıfat verilmez, kalır.</b> Onursal baş editör olmanın tek ölçütü, görevdeki baş editörlüğün sona ermiş olmasıdır: kişinin kendi devriyle, vefatıyla, kurucuların çoğunluk kararıyla ya da atamaya yazılmış bir bitiş tarihiyle. Kimsenin bu sıfatı birine bahşetme yetkisi yoktur; sistemi devralan kurumun da yoktur. Görevde bulunmamış biri hiçbir yolla onursal baş editör olamaz, çünkü ölçüt geçmiş bir görevdir.',
        '<b>This title is not granted; it remains.</b> The single criterion for becoming an honorary chief editor is that a chief editorship in office has ended: by the person\'s own handover, by their death, by a majority decision of the founders, or by an end date written into the appointment. No one has the authority to bestow this title on anyone, and neither does the institution that takes the system over. Someone who has never held office cannot become an honorary chief editor by any route, because the criterion is a past office.'
      ) ?></li>
      <li><?= k_c(
        '<b>Para karşılığı verilemez.</b> Bağış, barındırma, çeviri desteği ya da başka bir katkı, hiç kimseye bu sıfatı kazandırmaz. Destek verenlerin adı destek sayfasında ve destekledikleri dilin sayfasında kalıcı olarak yazar; bu ayrı bir teşekkürdür ve kurulla ilgisi yoktur.',
        '<b>It cannot be given for money.</b> A donation, hosting, translation support or any other contribution earns this title for no one. The names of supporters are recorded permanently on the support page and on the page of the language they supported; that is a separate acknowledgement and has nothing to do with the board.'
      ) ?></li>
      <li><?= k_c(
        '<b>Hiçbir yetki taşımaz.</b> Onursal baş editör hakem atayamaz, editör ekleyemez, editöryal not düşemez, kimseyi göreve getiremez ve hiçbir kararı geri alamaz. Para ve kaynak kararlarında oy kullanamaz. Yeniden görev almak isterse yolu herkesle aynıdır.',
        '<b>It carries no authority.</b> An honorary chief editor may not assign reviewers, add editors, write editorial notes, place anyone in office or reverse any decision. They may not vote on decisions about money and resources. If they wish to hold office again the route is the same as for everyone else.'
      ) ?></li>
    </ol>
    <p class="kr-not kr-not-duz"><?= k_c(
      'Neden ölçüte bağlı: bu sıfatı dağıtma yetkisi birinde olsaydı, zamanla bir nezaket parasına dönerdi; bağış yapana, iyilik edene, tanıdığa verilen bir incelik. Ölçüt geçmiş bir görevdir ve geçmiş satın alınamaz. Kurul sayfası şişirilmiş bir ad listesine dönüşmesin diye de onursal adlar görevdekilerden ayrı bir kümede durur; iş yapmayan büyük adlarla dolu kurul listeleri, yağmacı dergilerin bilinen işaretidir.',
      'Why it is tied to a criterion: if the authority to hand out this title rested with someone, it would in time become a courtesy currency, a nicety given to the donor, the helpful and the acquaintance. The criterion is a past office, and the past cannot be bought. So that the board page does not become an inflated list of names, honorary names stand in a group of their own apart from those in office; boards filled with distinguished names who do no work are a known mark of predatory journals.'
    ) ?></p>

    <?php /* ================= EDİTÖRLER ================= */ ?>
    <h2 id="ed"><?= k_c('Editörler', 'Editors') ?></h2>
    <p><?= k_c(
      'Editörler, baş editörlerce listeye eklenen kişilerdir. İstedikleri çalışmaya hakem atayabilir ve editöryal not düşebilirler. Yapılan her atama, atayanın adı ve saatiyle birlikte çalışmanın sayfasında görünür.',
      'Editors are those added to the list by the chief editors. They may assign reviewers to any work and add editorial notes. Every assignment appears on the work\'s page together with the name of the person who made it and the time.'
    ) ?></p>
    <?php if ($editorler): ?>
    <div class="dizi dizi-3 kr-liste">
      <?php foreach ($editorler as $e): ?>
      <?php echo k_kisi_kart([
          'ad'    => (string)$e['ad'],
          'kurum' => (string)($e['kurum'] ?? ''),
          'orcid' => (string)($e['orcid'] ?? ''),
          'resim' => (string)($e['resim'] ?? ''),
          'web'   => (string)($e['web'] ?? ''),
          'web_ad'=> (string)($e['web_ad'] ?? ''),
          'alt'   => k_c('Editör', 'Editor'),
      ], false); ?>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="kutu"><?= k_c(
      'Şu an listede editör bulunmuyor. Sistem yeni kurulduğu için atamalar baş editörlerce yapılmaktadır; eklenen her editör bu sayfada görünecektir.',
      'There are no editors on the list at present. As the system is newly founded, assignments are made by the chief editors; every editor added will appear on this page.'
    ) ?></p>
    <?php endif; ?>

    <?php /* ================= ATAMA DÜZENİ ================= */ ?>
    <h2 id="atama"><?= k_c('Yeni baş editör atama', 'Appointing new chief editors') ?></h2>
    <p><?= k_c(
      'Beş kişilik bir kurul bir sistemi kurmaya yeter, yürütmeye yetmez. Kurula yeni baş editörlerin katılması aşağıdaki kurallara bağlıdır. Kuralların hiçbiri yeni bir ilke getirmez; hepsi sistemde zaten yazılı olanlardan türer.',
      'A board of five is enough to found a system but not to run it. New chief editors join the board under the rules below. None of them introduces a new principle; all of them follow from what is already written in the system.'
    ) ?></p>
    <ol class="kr-olcut">
      <li><?= k_c(
        '<b>Atanan kişi görevdeki baş editör olur.</b> Kurucu baş editör olmaz ve olamaz. Kurucu baş editörlük bir yetki değil bir kayıttır; sonradan yazılamaz. Bu kısıt yalnızca sayfada değil kodda da bir kapıdır: sonradan yazılan bir kayda kurucu sıfatı yazılsa bile sistem onu düşürür.',
        '<b>The person appointed becomes a chief editor in office.</b> They do not and cannot become a founding chief editor. Founding chief editorship is a record, not an authority; it cannot be written afterwards. This limit is a gate in the code and not only on the page: even if the founding title were written into a later record, the system drops it.'
      ) ?></li>
      <li><?= k_c(
        '<b>Görev bir yıllık dönemler hâlinde sürer.</b> Dönem sonunda etkinlik ölçütü karşılanmışsa görev kendiliğinden devam eder; karşılanmamışsa o gün sona erer ve kişi onursal baş editör olarak anılır. Ölçüt hakem ataması ve editöryal işlem günü sayar; yalnız sisteme girmek katkı sayılmaz. Hedef sabit değildir: sistemin o dönemde ürettiği iş azsa hedef de iner, çünkü yapılacak iş yokken kimse iş yapmamakla ölçülemez. Görevi bitiren öteki dört yol kişinin kendi devri, vefatı, kurucuların çoğunluk kararı ve atamaya bilerek yazılmış bir bitiş tarihidir. Görevi biten kişi sistemde yazar ve hakem olarak çalışmayı sürdürür; ölçütü yeniden karşıladığı gün kendiliğinden yeniden göreve gelir.',
        '<b>The office runs in one-year terms.</b> If the activity criterion is met at the end of a term the office continues of itself; if it is not, the office ends that day and the person is named an honorary chief editor. The criterion counts reviewer assignments and days on which editorial work was done; signing in alone is not counted as a contribution. The target is not fixed: if the system produced little work in that term the target falls with it, because no one can be measured for not doing work that did not exist. The other four routes that end an office are the person\'s own handover, their death, a majority decision of the founders, and an end date deliberately written into the appointment. A person whose office has ended goes on working in the system as an author and a reviewer, and returns to office of itself on the day they meet the criterion again.'
      ) ?></li>
      <?php /* TARİH VE SAYILAR CÜMLENİN İÇİNE KONMAZ (bkz. k_cd).
               İçine konduğunda çeviri anahtarı hem sayfanın diline hem
               de kurulun kaç kişi olduğuna bağlanıyordu: kurul bir kişi
               büyüdüğü gün cümle bütün sözlüklerden düşecekti. */ ?>
      <li><?= k_cd(
        '<b>Atama yetkisi görevdeki baş editörlere aittir ve %1 tarihinde kapanır.</b> Bugün bu kurul <b>%2 kişidir</b> ve bir atama için en az <b>%3 oy</b> gerekir. Oylar eşittir; kurucunun oyu ağır basmaz. Oy kurulunun yalnızca kuruculardan değil görevdeki baş editörlerden oluşmasının sebebi şudur: bir kurucu görevi bıraktığında ya da vefat ettiğinde kurul küçülür, sabit bir oy sayısı ise kurulu bir gün kendini yenileyemez hâle getirirdi. Yeter sayı bu yüzden kurul küçüldüğünde salt çoğunluğa iner.',
        '<b>The authority to appoint rests with the chief editors in office and closes on %1.</b> Today that board is <b>%2 people</b> and an appointment requires at least <b>%3 votes</b>. The votes are equal; a founder\'s vote does not weigh more. The voting board is made of the chief editors in office rather than the founders alone for this reason: when a founder lays down office or dies the board grows smaller, and a fixed number of votes would one day leave the board unable to renew itself. The required number therefore falls to a simple majority as the board shrinks.',
        k_esc(hs_tarih_yaz($atama['yetki_bitis'], k_en())), (int)$oyKurulSay, (int)tg_atama_yeter_sayisi()
      ) ?></li>
      <li><?= k_c(
        '<b>Yetki düşer, zorunlu hâllerde açılır.</b> Yazılı tarih geldiğinde baş editör listesine ekleme ve çıkarma yetkisi düşer; asıl kural budur. Yalnızca aşağıda sayılan <b>dört zorunlu hâlden</b> biri varsa kendiliğinden açılır ve hâl ortadan kalktığı gün yeniden düşer. Liste kapalıdır: yeni bir zorunlu hâl eklemek bir kod değişikliğidir, ayar dosyasına yazılarak eklenemez. Bugün yetki %1.',
        '<b>The authority lapses, and opens in compulsory cases.</b> When the written date arrives, the authority to add to and remove from the chief editor list lapses; that is the rule. It opens of its own accord only where one of the <b>four compulsory cases</b> listed below holds, and it lapses again on the day that case ends. The list is closed: adding a new compulsory case is a change to the code and cannot be done by writing a line in the configuration file. Today the authority is %1.',
        tg_atama_yetkisi_acik()
          ? '<b>' . k_c('açıktır', 'open') . '</b>'
          : '<b>' . k_c('kapalıdır', 'closed') . '</b>'
      ) ?></li>
      <li><?= k_c(
        '<b>Uyruk, ülke ve kurum şartı yoktur.</b> Baş editör olmak için hiçbir ülke ya da kurum koşulu aranmaz. Bildiride yazılı olan cümle şudur: bilimde bir milletin ötekine üstünlüğü yoktur. Bir sistemin kurulunu tek bir ülkeyle sınırlamak, o cümleyi geçersiz kılar. Böyle bir koşul yapılandırmaya yazılırsa sistem onu düşürür.',
        '<b>There is no requirement of nationality, country or institution.</b> No condition of country or institution is asked of a chief editor. The sentence written in the declaration is this: in scholarship no nation stands above another. To limit a system\'s board to a single country would make that sentence void. If such a condition were written into the configuration, the system drops it.'
      ) ?></li>
      <li><?= k_c(
        '<b>Maddi katkı bir şart değildir.</b> Katkı sorulabilir ve verilirse kaynağı, tutarı ve harcandığı yer istatistik sayfasında yayımlanır; ama atama kararında ölçüt olarak kullanılamaz. "Şu kadar katkı veren baş editör olur" denildiği gün baş editörlük satılmış olur ve bu, onursal baş editörlüğün para karşılığı verilemeyeceği hükmüyle doğrudan çelişir.',
        '<b>A financial contribution is not a condition.</b> A contribution may be asked for, and if given, its source, amount and use are published on the statistics page; but it cannot be used as a criterion in an appointment. The day it is said that whoever contributes a certain sum becomes a chief editor, chief editorship has been sold, and that contradicts outright the provision that honorary chief editorship cannot be given for money.'
      ) ?></li>
    </ol>

    <?php /* ---- DÖRT ZORUNLU HÂL ----
             Düşmüş bir yetkinin hangi durumlarda açıldığı, o durumların
             bugün geçerli olup olmadığıyla birlikte yazılır. Sayfaya
             bakan kişi "yetki açık mı" sorusunu bize sormadan
             cevaplayabilmelidir; cevap kayıttadır. */ ?>
    <h3 id="zorunlu"><?= k_c('Yetkiyi açan dört zorunlu hâl', 'The four compulsory cases that open the authority') ?></h3>
    <p><?= k_c(
      'Her biri kayıttan okunur, kendi kendini kapatır ve yalnızca eksiği giderir. Bir yorum ya da gerek görme değildir: kimse "zorunluluk var" diyerek yetkiyi açamaz, çünkü açan kişi değil kayıttır. Açılan yetki kurulu yazılı koltuk sayısının üstüne çıkaramaz.',
      'Each is read from the record, closes itself, and does no more than make good what is missing. None of them is a judgment or a finding of necessity: no one can open the authority by declaring that necessity exists, because what opens it is the record and not a person. The authority so opened cannot take the board above the written number of seats.'
    ) ?></p>
    <div class="tablo-sar">
      <table class="tb">
        <thead><tr>
          <th><?= k_c('Hâl', 'Case') ?></th>
          <th><?= k_c('Ne demek', 'What it means') ?></th>
          <th><?= k_c('Bugün', 'Today') ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($zorunlu as $anahtar => $h): ?>
          <tr>
            <td><b><?= k_esc(k_t(['tr' => $h['ad'][0], 'en' => $h['ad'][1]])) ?></b><br>
                <span class="kr-kod"><?= k_esc($anahtar) ?></span></td>
            <td><?= k_esc(k_t(['tr' => $h['ic'][0], 'en' => $h['ic'][1]])) ?></td>
            <td><?= !empty($h['var'])
                  ? '<span class="rz rz-kut">' . k_c('geçerli', 'holds') . '</span>'
                  : '<span class="rz rz-cizgi">' . k_c('yok', 'no') . '</span>' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($edSureli): ?>
    <?php foreach ($edSureli as $bt => $adlar): $gecti = date('Y-m-d') > $bt; ?>
    <p class="kr-not kr-not-duz"><?php
      /* Adlar, tarih ve "bugün açık/kapalı" eki üç ayrı değerdir ve
         üçü de cümlenin İÇİNE değil YERİNE konur; yoksa anahtar hem
         dile hem kurulun bugünkü hâline bağlanır (bkz. k_cd). Tarihi
         geçmiş olan ve olmayan iki ayrı cümle vardır, çünkü ikisinin
         sonu ayrıdır. */
      $bugun = !$gecti ? '' : (tg_atama_yetkisi_acik()
        ? k_c(' ve bugün açıktır', ', and today it is open')
        : k_c(' ve bugün kapalıdır', ', and today it is closed'));
      echo k_cd(
      'Kaydında yazılı bir editör yetkisi bitişi olanlar: <b>%1</b>, tarih <b>%2</b>. '
        . 'Bu tarih yalnızca editör listesine ekleme ve çıkarmayı '
        . 'ilgilendirir; göreve, hakem atamaya, editöryal not düşmeye ve kurucu baş editörlük kaydına dokunmaz. '
        . 'Yukarıdaki dört zorunlu hâl bu yetki için de geçerlidir: hâllerden biri varsa tarih geçmiş olsa bile '
        . 'yetki açık kalır%3.',
      'Those with a written end date for the editor authority: <b>%1</b>, on <b>%2</b>. '
        . 'That date concerns only the adding and removing of editors; '
        . 'it does not touch the office, the assigning of reviewers, the writing of editorial notes or the record of '
        . 'founding chief editorship. The four compulsory cases above apply to this authority too: where one of them '
        . 'holds, the authority stays open even after the date has passed%3.',
      k_esc(implode(', ', $adlar)), k_esc(hs_tarih_yaz((string)$bt, k_en())), $bugun
    ); ?></p>
    <?php endforeach; ?>
    <?php endif; ?>
    <p class="kr-not kr-not-duz"><?= k_c(
      'Çıkarma bu yetkinin içinde değildir. Bir baş editörü görevden çıkarmak hiçbir zaman serbest bir yetki olmadı: görev yalnızca kişinin kendi devriyle, vefatıyla, yazılı bir çoğunluk kararıyla ya da atamaya konmuş bir bitiş tarihiyle biter. Çoğunluk kararı yolu tarihe bağlı değildir ve her zaman açıktır; bir kurulun kendini denetleyebilmesi buna bağlıdır.',
      'Removal is not part of this authority. Removing a chief editor was never a free standing power: an office ends only by the person\'s own handover, by their death, by a written majority decision, or by an end date written into the appointment. The route of a majority decision is not tied to any date and is always open; a board\'s ability to hold itself to account depends on it.'
    ) ?></p>
    <p class="kr-not kr-not-duz"><?= k_c(
      'Atama neden süreli bir istisna: bir göreve süresiz atama yetkisi, zamanla kampanyayı, öbekleşmeyi ve kayırmayı getirir. Kapalı hakemliğe yönelttiğimiz eleştirinin aynısı bu kez yönetimin başına gelir. Süreli bir istisna bu riski taşımaz.',
      'Why appointment is a time limited exception: an open ended authority to appoint brings, in time, campaigning, factions and patronage. The very criticism we level at closed peer review would then fall on the management instead. A time limited exception does not carry that risk.'
    ) ?></p>

    <?php /* ================= MALİ OY ================= */ ?>
    <h2 id="oy"><?= k_c('Para ve kaynak kararlarında oy', 'Voting on money and resources') ?></h2>
    <p><?= k_c(
      'Görevdeki baş editörler para ve kaynak kararlarında oy kullanır: yıllık bütçe, harcama kalemleri, kabul edilecek destekler ve fon başvuruları. <b>Oylar eşittir; kurucunun oyu ağır basmaz.</b> Onursal baş editörler bu oyda yer almaz, çünkü onursal sıfat bir teşekkürdür ve hiçbir yetki taşımaz.',
      'Chief editors in office vote on decisions about money and resources: the annual budget, items of expenditure, support to be accepted and applications for funding. <b>The votes are equal; a founder\'s vote does not outweigh another\'s.</b> Honorary chief editors take no part in this vote, because the honorary title is a thank you and carries no authority.'
    ) ?></p>
    <?php if ($oyKurulu): ?>
    <div class="tablo-sar kr-oy">
      <table class="tb">
        <thead><tr>
          <th><?= k_c('Oy kullanan', 'Voting member') ?></th>
          <th><?= k_c('Basamak', 'Step') ?></th>
          <th class="sayi"><?= k_c('Oy ağırlığı', 'Vote weight') ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($oyKurulu as $o): ?>
          <tr>
            <td><?= k_esc($o['ad']) ?></td>
            <td><?= $o['sinif'] === 'kurucu'
                    ? k_c('Kurucu', 'Founding')
                    : k_c('Görevdeki', 'In office') ?></td>
            <td class="sayi"><?= (int)$o['agirlik'] ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <p class="kr-not kr-not-duz"><?= k_c(
      'Ağırlık sütunu bilerek vardır ve bilerek herkeste birdir. Bir gün değiştirilmek istenirse, değiştirilecek yerin görünür olması gerekir.',
      'The weight column is deliberately present and is deliberately one for everyone. If it is ever to be changed, the place where it would be changed should be visible.'
    ) ?></p>
    <?php endif; ?>
    <p><?= k_c(
      'Oy sayısından bağımsız iki sınır vardır. Birincisi aşağıdaki sekiz ilkedir. İkincisi, bir dernek ya da vakıf kurulduğunda o tüzel kişiliğin genel kurul kararlarına ilişkin hukuki sınırdır; bu iki oy düzeni ayrıdır ve birbirinin yerine geçmez. Sistemin kurul oyunda uyruk sınırı yoktur ve bütün baş editörler eşittir; derneğin genel kurul oyu ise Türk hukukuna bağlıdır.',
      'Two limits stand apart from the number of votes. The first is the eight principles below. The second is the legal limit on the general assembly decisions of an association or a foundation, once such a body has been established; these two voting arrangements are separate and neither stands for the other. In the system\'s own board vote there is no requirement of nationality and all chief editors are equal; the general assembly vote of an association is subject to Turkish law.'
    ) ?></p>

    <h3><?= k_c('Hiçbir çoğunlukla daraltılamayacak sekiz ilke', 'Eight principles that no majority may narrow') ?></h3>
    <p><?= k_c(
      'Aşağıdakiler tüzük taslağının Ek A Madde 2\'sindedir. Bir kurul yalnızca açıklığı genişletecek yönde karar alabilir; daraltacak yönde alamaz, kaldıramaz ve askıya alamaz. Ücret alınması, kapalı hakemliğe dönülmesi ya da bir kaydın silinmesi oylanamaz; oylanırsa karar hükümsüzdür. Bu, sayfada duran bir söz değildir: karar konusu bu ilkelerden birine dokunuyorsa oy sayılmadan durdurulur, ve yapılandırmaya ilkeye aykırı bir satır yazılırsa sistem o satırı okumaz.',
      'The following stand in Appendix A Article 2 of the draft statute. A board may decide only in the direction of widening openness; it may not narrow, remove or suspend these. Charging a fee, returning to closed review or deleting a record cannot be put to a vote; if they are, the decision is void. This is not a promise that merely sits on the page: if the subject of a decision touches one of these principles it is stopped before the votes are counted, and if a line contrary to them is written into the configuration the system does not read it.'
    ) ?></p>
    <ol class="kr-olcut kr-ilke">
      <?php foreach ($ilkeler as $ilk): ?>
      <li><b><?= k_esc(k_t(['tr' => $ilk['ad'][0], 'en' => $ilk['ad'][1]])) ?></b><?= k_esc(k_t(['tr' => $ilk['ic'][0], 'en' => $ilk['ic'][1]])) ?></li>
      <?php endforeach; ?>
    </ol>

    <?php /* ================= KURULA GİRME ÖLÇÜTÜ ================= */ ?>
    <h2 id="olcut"><?= k_c('Kurula girme ölçütü', 'The criterion for joining the board') ?></h2>
    <p><?= k_c(
      'Kurul üyeliği davetle değil, kayıtla kazanılır. Ölçüt tektir ve herkes için aynıdır:',
      'Membership is earned by record, not by invitation. There is one criterion and it is the same for everyone:'
    ) ?></p>
    <ol class="kr-olcut">
      <li><?= k_c(
        'Bu sistemde en az <b>' . $olcutYayin . ' yayın</b> bulunması.',
        'Having at least <b>' . $olcutYayin . ' publications</b> in this system.'
      ) ?></li>
      <li><?= k_c(
        'Bu yayınlardan en az <b>' . $olcutOnayli . ' tanesinin</b> iki hakemden de olumlu rapor alarak <b>hakem onaylı</b> yayımlanmış olması.',
        'At least <b>' . $olcutOnayli . '</b> of those publications having been published as <b>reviewer approved</b>, with a positive report from two reviewers.'
      ) ?></li>
      <li><?= k_c(
        'Ölçütü karşılayan kişi kurula girer; ayrıca bir onay ya da oylama gerekmez. Sayım bu sayfada, kayıtların üzerinden anlık olarak yapılır ve isteyen doğrulayabilir.',
        'Whoever meets the criterion joins the board; no further approval or vote is required. The count is made on this page directly from the records, and anyone may verify it.'
      ) ?></li>
    </ol>

    <?php
    /* ---- 2027'den sonra görevdeki baş editörlük ----
       Kurucuların atama yetkisi 2027 sonunda kapanıyor. Bundan
       sonrasının nasıl yürüyeceği burada yazılıdır; görünmeyen bir
       kural, olmayan bir kuraldan iyi değildir. */
    $bo = tg_bas_olcut();
    ?>
    <h2 id="devam"><?= k_c('2027\'den sonra baş editörlük', 'Chief editorship after 2027') ?></h2>
    <p><?= k_cd(
      'Kurucu baş editörlük bir kayıttır ve hiçbir zaman düşmez. Görevdeki baş editörlük ise bir yetkidir ve kurucuların bu yetkisi <b>%1</b> tarihinde kapanır. O günden sonra görev şu sırayla yürür ve başka hiçbir yol yoktur.',
      'Founding chief editorship is a record and never lapses. Chief editorship in office is an authority, and the founders\' authority closes on <b>%1</b>. After that day the office passes in the following order, and by no other route.',
      k_esc(hs_tarih_yaz($atama['yetki_bitis'], k_en()))
    ) ?></p>
    <ol class="kr-olcut">
      <li><?= k_c(
        '<b>Devralan kurum atar.</b> Sistem bir kuruma devredilmişse, görevdeki baş editörleri o kurum belirler ve kararı her şeyin üstündedir. Değiştiremediği iki şey vardır: kurucu kaydı ve görevi sona ermiş olanların onursal baş editörlüğü. İkisi de kayıttır, yetki değildir. Bir projeye dönüşmek devir sayılmaz: sorumluluğu üstlenen bir devralan yoksa görevdekilerin yetkisi aynı şekilde sürer.',
        '<b>The institution that takes the system over appoints them.</b> If the system has been handed to an institution, that institution determines the chief editors in office and its decision overrides everything else. Two things it cannot change: the founding record and the honorary chief editorship of those whose office has ended. Both are records, not authorities. Turning into a project is not a handover: where there is no successor who takes on the responsibility, the authority of those in office continues just as before.'
      ) ?></li>
      <li><?= k_c(
        '<b>Kurum yoksa görev kayıtla kazanılır.</b> Yayın kurulu ölçütünü karşılayan ve raporla sonuçlanmış en az <b>' . (int)$bo['atama'] . ' hakem ataması</b> yapmış kişi görevdeki baş editör olur. Davet yoktur, oylama yoktur; sayım bu sayfada kayıtlardan yapılır. Görev, ölçüt karşılandığı sürece sürer.',
        '<b>If there is no institution, the office is earned by record.</b> Whoever meets the board criterion and has made at least <b>' . (int)$bo['atama'] . ' reviewer assignments</b> that resulted in a report becomes a chief editor in office. There is no invitation and no vote; the count is made on this page from the records. The office lasts as long as the criterion is met.'
      ) ?></li>
      <li><?= k_c(
        '<b>Kimse ölçütü karşılamıyorsa sistem yönetimsiz kalmaz.</b> Yetki sınırı yazılı olmayan kurucu baş editör görevi sürdürür. Bu hüküm, arşivin bir gün yönetilemez hâle gelmemesi içindir.',
        '<b>If no one meets the criterion the system is not left unmanaged.</b> The founding chief editor whose authority carries no written limit continues in office. This provision exists so that the archive never becomes ungovernable.'
      ) ?></li>
    </ol>
    <p class="kr-not kr-not-duz"><?= k_c(
      'Neden oylama değil: bu sistemde oylama bir yargı aracıdır, bir siyaset aracı değil. Kurul oylaması tek bir çalışmaya ilişkin itirazı karara bağlar. Bir göreve seçim yapmak kampanyayı, öbekleşmeyi ve kayırmayı getirir. Neden yalnızca yayın sayısı değil: bu görev yazmak değil yürütmektir, bu yüzden yapılmış editöryal iş de sayılır.',
      'Why not a vote: in this system voting is an instrument of adjudication, not of politics. A panel vote settles an objection about one particular work. Electing someone to an office brings campaigning, factions and patronage. Why not publication count alone: this office is not writing but running, so editorial work done is counted as well.'
    ) ?></p>
    <?php
    $saglayan = tg_bas_olcut_saglayanlar();
    if (!tg_bas_olcut_isliyor()): ?>
    <div class="kutu"><?= k_cd(
      'Bu ölçüt %1 tarihinden sonra işlemeye başlar. O güne kadar görev, yukarıda adı yazılı kurucu baş editörlerdedir.',
      'This criterion begins to apply after %1. Until then the office rests with the founding chief editors named above.',
      k_esc(hs_tarih_yaz($bo['baslangic'], k_en()))
    ) ?></div>
    <?php elseif (!$saglayan): ?>
    <div class="kutu"><?= k_c(
      'Ölçütü karşılayan henüz kimse yok. Görev, yetki sınırı yazılı olmayan kurucu baş editörde durmayı sürdürüyor.',
      'No one meets the criterion yet. The office continues to rest with the founding chief editor whose authority carries no written limit.'
    ) ?></div>
    <?php else: ?>
    <h3><?= k_c('Ölçütle görev kazananlar', 'Those who have earned the office by criterion') ?></h3>
    <ul>
      <?php foreach ($saglayan as $sk => $sv): ?>
      <li><?= k_esc($sk) ?> &middot; <?= (int)$sv['atama'] ?> <?= k_c('atama', 'assignments') ?>,
          <?= (int)$sv['yayin'] ?> <?= k_c('yayın', 'publications') ?>,
          <?= (int)$sv['onayli'] ?> <?= k_c('hakem onaylı', 'reviewer approved') ?></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php if ($kurul): ?>
    <h3><?= k_c('Ölçütü karşılayanlar', 'Those who meet the criterion') ?></h3>
    <div class="dizi dizi-3 kr-liste">
      <?php foreach ($kurul as $s): ?>
      <div class="kk">
        <span class="yuz yuz-harf kk-yuz kr-im" aria-hidden="true"><?= k_esc(kr_bas_harf((string)$s['ad'])) ?></span>
        <span class="kk-ic">
          <b><?= k_esc($s['ad']) ?></b>
          <small class="kk-alt"><?= (int)$s['yayin'] ?> <?= k_c('yayın', 'publications') ?> · <?= (int)$s['onayli'] ?> <?= k_c('hakem onaylı', 'reviewer approved') ?></small>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="kutu"><?= k_c(
      'Ölçütü karşılayan henüz kimse yok. Bu olağandır: sistem yeni açıldı ve ' . $olcutYayin . ' yayına ulaşmak zaman ister. Ölçüt yumuşatılmayacaktır.',
      'No one meets the criterion yet. This is to be expected: the system has just opened and reaching ' . $olcutYayin . ' publications takes time. The criterion will not be relaxed.'
    ) ?></p>
    <?php endif; ?>

    <?php if ($yaklasan): ?>
    <h3><?= k_c('Sayım nasıl işliyor', 'How the count works') ?></h3>
    <p><?= k_c(
      'Aşağıdaki çizelge, sayımın gizli bir yanı olmadığını göstermek içindir. Sistemde üç ve daha fazla yayını bulunan yazarların güncel durumu:',
      'The table below is here to show that there is nothing hidden in the count. The current standing of authors with three or more publications in the system:'
    ) ?></p>
    <div class="tablo-sar kr-cizelge">
      <table class="tb">
        <thead><tr>
          <th><?= k_c('Yazar', 'Author') ?></th>
          <th><?= k_c('Yayın', 'Publications') ?></th>
          <th><?= k_c('Hakem onaylı', 'Reviewer approved') ?></th>
        </tr></thead>
        <tbody>
          <?php foreach ($yaklasan as $s): ?>
          <tr>
            <td><?= k_esc($s['ad']) ?></td>
            <td><?= (int)$s['yayin'] ?> / <?= $olcutYayin ?></td>
            <td><?= (int)$s['onayli'] ?> / <?= $olcutOnayli ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <p class="kr-not"><?= k_c(
      'Kurul üyeliği bir ayrıcalık getirmez: kurul üyesinin çalışması da aynı hakemlikten geçer, aynı ölçütlerle değerlendirilir ve raporları aynı şekilde yayımlanır. Bir editörün kendi çalışmasına hakem ataması ise mümkün değildir.',
      'Membership brings no privilege: a board member\'s own work goes through the same review, is assessed by the same criteria and its reports are published in the same way. An editor may not assign reviewers to their own work.'
    ) ?></p>

   </div>
   <?= k_belge_yan(
     [
       ['k' => 'kurucu',    'tr' => 'Kurucu baş editörler',   'en' => 'Founding chief editors'],
       ['k' => 'gorevdeki', 'tr' => 'Görevdeki baş editörler', 'en' => 'Chief editors in office'],
       ['k' => 'onursal',   'tr' => 'Onursal baş editörler',  'en' => 'Honorary chief editors'],
       ['k' => 'ed',        'tr' => 'Editörler',              'en' => 'Editors'],
       ['k' => 'atama',     'tr' => 'Yeni baş editör atama',  'en' => 'Appointing new chief editors'],
       ['k' => 'zorunlu',   'tr' => 'Dört zorunlu hâl',       'en' => 'The four compulsory cases'],
       ['k' => 'oy',        'tr' => 'Para kararlarında oy',   'en' => 'Voting on money'],
       ['k' => 'olcut',     'tr' => 'Kurula girme ölçütü',    'en' => 'How to join the board'],
       ['k' => 'devam',     'tr' => '2027\'den sonra',        'en' => 'After 2027'],
     ],
     [
       ['tr' => 'Üç küme', 'en' => 'Three groups',
        'ic' => k_c(
          '<b>Kurucu</b> bir kayıttır, en üstte ve kayıt sırasında durur.<br><b>Görevdeki</b> bir yetkidir, alfabetiktir, bir yıllık dönemler hâlinde sürer.<br><b>Onursal</b> bir teşekkürdür, görev sona erdiğinde kalır.',
          '<b>Founding</b> is a record; it stands at the top, in the order of the record.<br><b>In office</b> is an authority; alphabetical, and the office runs in one-year terms.<br><b>Honorary</b> is a thank you; it remains once the office has ended.'
        )],
       ['tr' => 'Ölçüt', 'en' => 'The criterion',
        'ic' => k_c(
          'Bu sistemde en az <b>' . $olcutYayin . ' yayın</b>, bunlardan en az <b>' . $olcutOnayli . ' tanesi hakem onaylı</b>. Davet yok, oylama yok; sayım kayıtlardan yapılır.',
          'At least <b>' . $olcutYayin . ' publications</b> in this system, at least <b>' . $olcutOnayli . ' of them reviewer approved</b>. No invitation, no vote; the count is made from the records.'
        )],
       ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
        'ic' => '<a href="' . k_esc(k_bag('/ilkeler.php')) . '">' . k_c('Yayın ilkeleri', 'Editorial policies') . '</a><br>'
              . '<a href="' . k_esc(k_bag('/bildiri.php')) . '">' . k_c('Bildiri', 'Declaration') . '</a><br>'
              . '<a href="' . k_esc(k_bag('/hakemlik.php')) . '">' . k_c('Hakemlik süreci', 'Peer review process') . '</a>'],
     ]
   ) ?>
  </div>
</section>

<?php /* ---------------------------------------------------------------
     KURUL KARARLARI — KAMUSAL SAYIM
     19 Ağustos 2026. Kurul kararlarının yazıldığı ve oylandığı yer
     panelde açıldı; sonucu ise BURADA durur, çünkü bu sayfa zaten
     "sayım herkese açıktır" diyor ve bir kurulun kendini denetleyebilmesi
     buna bağlı. Kapalı bir sayım, denetlenemeyen bir kurul demektir.

     KARARLAR ÇALIŞMA OYLAMALARINDAN AYRIDIR: bir çalışma hakkındaki
     oylama o çalışmanın kendi sayfasında görünür. Kaldırılan
     oylama.php'nin kusuru ikisini bir listede toplamaktı; okur "bu hangi
     kurulun oylaması" diye soruyor ve yanıtı sayfada bulamıyordu.
     --------------------------------------------------------------- */ ?>
<?php $kkListe = function_exists('tg_kk_oku') ? tg_kk_oku() : []; ?>
<section class="kap" id="kararlar">
  <h2><?= k_c('Kurul kararları', 'Board decisions') ?></h2>
  <p class="ack kk-ack-p"><?= k_c(
    'Kurulun kendisi hakkındaki kararlar ve oylar. Karar açıldıktan sonra metni değişmez, verilen oy geri alınmaz ve kayıt silinmez. Her oy adıyla ve gerekçesiyle görünür; çoğunluk, oy verenlerin değil oy verebileceklerin yarısından fazlasıdır. Bir çalışma hakkındaki oylamalar burada değil, o çalışmanın kendi sayfasında durur.',
    'The decisions about the board itself, and the votes. Once a decision is opened its text does not change, a vote once cast is not withdrawn and the record is not deleted. Every vote appears with its name and its reasoning; a majority means more than half of those entitled to vote, not of those who voted. Votes about a work are not here but on the page of that work.'
  ) ?></p>
  <?php if (!$kkListe): ?>
  <p class="ack"><?= k_c('Henüz bir kurul kararı oylanmadı.', 'No board decision has been voted on yet.') ?></p>
  <?php else: ?>
  <?php foreach ($kkListe as $kk): $ks = tg_kk_sonuc($kk); ?>
  <article class="kutu kk-kamu">
    <h3><?= k_esc((string)($kk['baslik'] ?? '')) ?></h3>
    <p class="kk-ust">
      <span class="rz rz-cizgi"><?= k_esc(tg_kk_tur_ad((string)($kk['tur'] ?? 'kurul'), k_en())) ?></span>
      <span class="rz <?= $ks['kapali'] ? ($ks['hal'] === 'kabul' ? 'rz-yes' : 'rz-kir') : 'rz-cizgi' ?>"><?= k_esc(tg_kk_hal_ad($ks['hal'], k_en())) ?></span>
      <span class="kk-ack"><?= k_esc((string)((($kk['acan'] ?? [])['ad'] ?? ''))) ?> · <?= k_esc(substr((string)($kk['tarih'] ?? ''), 0, 10)) ?>
        · <?= k_c('yeter sayı', 'threshold') ?> <?= (int)$ks['yeter'] ?>/<?= (int)$ks['kisi'] ?></span>
    </p>
    <p class="kk-metin"><?= nl2br(k_esc((string)($kk['metin'] ?? ''))) ?></p>
    <?php $oylar = tg_dizi($kk['oylar'] ?? null); if ($oylar): ?>
    <div class="kk-oylar">
      <?php foreach ($oylar as $oy): if (!is_array($oy)) continue; ?>
      <div class="kk-oy">
        <b><?= k_esc((string)($oy['ad'] ?? '')) ?></b>
        <span class="rz rz-cizgi"><?= k_esc(tg_kk_secenek_ad((string)($oy['karar'] ?? ''), k_en())) ?></span>
        <span><?= k_esc((string)($oy['gerekce'] ?? '')) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </article>
  <?php endforeach; ?>
  <?php endif; ?>
</section>

<?php k_son(); ?>
