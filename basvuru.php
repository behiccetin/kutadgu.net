<?php
/* =====================================================================
   KUTADGU - Çalışma gönderim formu / Submission form
   Alan adları ve API sözleşmesi eski basvuru.html ile birebir aynıdır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/alan_secim.php';
/* Tam metin adımı yazma ekranındakiyle AYNI düzenleyiciyi kullanır:
   iki yerde iki ayrı araç çubuğu olsaydı, gönderimde kullanılan bir
   düğme düzeltme ekranında bulunmazdı. */
require_once __DIR__ . '/k/duzenleyici.php';
require_once __DIR__ . '/k/istem.php';

$benz    = (int)tg_ayar('benzerlik_ust', 15);
$benzTek = (int)tg_ayar('benzerlik_tek_ust', 5);
/* Kural betiğe de aynı kaynaktan geçer: iki yerde iki ayrı karar
   verilirse tarayıcı geçirir, sunucu reddeder. */
$benzSartJs = tg_benzerlik_sarti() ? 'true' : 'false';
/* Tarayıcıya gidecek biçim: JSON, çünkü 'true'/'false' dizesi
   JavaScript'te ikisi de doğrudur ve false sessizce true olur. */
$doktoraSartiJs = json_encode(tg_yazarlik_doktora_sarti());
$kabulS  = (int)tg_ayar('kabul_gecerli', 2);
$retS    = (int)tg_ayar('ret_donusum', 2);
$marka   = (string)tg_ayar('marka', 'Kutadgu');

/* =====================================================================
   SİHİRBAZ: adımlar tek kaynaktan gelir (ortak.php).
   Aynı liste üç yerde kullanılıyor: adım şeridi, form bölümlerinin
   başlıkları ve sağ raydaki içindekiler. Üçünü elle yazmak, üçünün
   zamanla ayrışması demektir.
   ===================================================================== */
$shAdimlar = tg_basvuru_adimlari();
$shEn      = k_en();

/* =====================================================================
   KOŞUL METNİNİN SÜRÜMÜ VE "DAHA ÖNCE OKUDUM"

   Sürüm elle artırılmaz; METNİN KENDİSİNDEN üretilir. Elle artırılan
   bir sayı, metni değiştiren kişi tarafından unutulur ve sistem
   değişmiş bir kuralı "zaten okumuştu" diye atlar. Burada koşul
   adımının kaynağı ile o metne giren sayılar birlikte özetlenir:
   koşullardan biri bir harf değişse sürüm değişir ve herkes metni
   yeniden görür.

   Dil özetin dışında değildir ama iki dilin metni de aynı kaynakta
   durduğu için sürüm dile göre değişmez: Türkçe okuyan da İngilizce
   okuyan da aynı sürümü okumuş sayılır. */
$kosulKaynak = (string)@file_get_contents(__FILE__);
$kk0 = strpos($kosulKaynak, 'id="f-kosul"');
$kk1 = strpos($kosulKaynak, 'id="f-calisma"');
$kosulSurum = ($kk0 !== false && $kk1 !== false && $kk1 > $kk0)
    ? substr(sha1(substr($kosulKaynak, $kk0, $kk1 - $kk0) . '|' . $benz . '|' . $benzTek . '|' . $marka), 0, 10)
    : '';

/* Oturumdaki hesap: yalnızca "bu koşulları daha önce okudum" sorusunu
   sormak için okunur. Girişli olmayan için yanıt her zaman hayırdır ve
   hiçbir dosya açılmaz. */
$hsBen = null;
if (is_file(__DIR__ . '/k/hesap.php')) { require_once __DIR__ . '/k/hesap.php'; $hsBen = hs_oturum(); }
$kosulGecmis = ['okundu' => false, 'tarih' => ''];
if ($hsBen !== null && function_exists('tg_kosul_okundu_mu')) {
    $kosulGecmis = tg_kosul_okundu_mu((string)($hsBen['eposta'] ?? ''), $kosulSurum);
}

/* =====================================================================
   BAŞVURAN ALANLARI HESAPTAN GELİR

   Kurul kararı, 15 Ağustos 2026: "kullanıcı olarak girdiğime göre
   unvanım adım soyadım orcidim ve diğer bilgiler otomatik gelmeli ama
   hiç kaydı olmayan kişi de sisteme kayıt olup yazısını gönderebilsin;
   yoksa ikilik çıkıyor."

   TEK AKIŞ, İKİ DURUM. Ayrı bir "üyeler için form" açmak ikiliğin ta
   kendisi olurdu: iki form, iki doğrulama, iki hata listesi ve zamanla
   iki ayrı kural. Form bir tanedir; girişli olan alanları DOLU bulur,
   olmayan boş bulur. Doldurulmuş alan kilitli değildir — kişi kendi
   kurumunu düzeltebilmelidir; hesaptaki bilgi bir başlangıçtır, bir
   dayatma değil.

   SUNUCUDA DOLDURULUR, BETİKTE DEĞİL. Betikle doldurmak, betiği kapalı
   olanın formu boş bulması demekti; oysa aynı kişi aynı hesapla
   girmiştir. value="" özniteliği her durumda çalışır.

   Taslak geri yüklenirse taslaktaki değer üstündür: taslak kişinin
   kendi yazdığıdır, hesap kaydı ise varsayılan.
   ===================================================================== */
$benAlan = ['unvan' => '', 'ad' => '', 'eposta' => '', 'kurum' => '', 'orcid' => ''];
if ($hsBen !== null) {
    foreach (array_keys($benAlan) as $bA) $benAlan[$bA] = trim((string)($hsBen[$bA] ?? ''));
}
$benGirisli = ($hsBen !== null);
/* Hesapta olmayan alan "gelmedi" sayılır; kişiye hangi alanların
   kendiliğinden geldiğini söylemek için sayılır. */
$benGelen = array_values(array_filter(array_keys($benAlan), static fn($bA) => $benAlan[$bA] !== ''));
/* Adres açıkça bir adım istiyorsa o kazanır: atlama bir kolaylıktır,
   bir kilit değil. */
$shAdim = isset($_GET['adim'])
    ? tg_basvuru_adim_no($_GET['adim'])
    : ($kosulGecmis['okundu'] ? 2 : 1);
/* Bir adımın görünen adı; ekran okuyucunun duyduğu <legend> bundan. */
$adimAd = function (string $k) use ($shAdimlar, $shEn): string {
    foreach ($shAdimlar as $a) if ($a['k'] === $k) return k_esc(k_t($a));
    return '';
};
/* HEREDOC İÇİNDE İŞLEV ÇAĞRILMAZ.
   ÖLÇÜLEN KUSUR — 19 Ağustos 2026: biçim buraya "<?= k_istem_stil() ?>"
   diye yazılmıştı ve <<<CSS bir HEREDOC olduğu için o satır olduğu gibi,
   METİN olarak sayfaya düştü; pencerenin biçimi büsbütün kayboldu.
   Kutu 820 piksel yerine 1175 piksele açıldı ve bosluk-kapi bunu
   "satır en geniş okuma ölçüsünü aşıyor" diye yakaladı — kapı, biçimin
   yokluğunu görmedi, yokluğunun SONUCUNU gördü. Değişken önce kurulur,
   heredoc içinde yalnız yerine konur. */
$istemStil = k_istem_stil();
$ekBas = <<<CSS
<style>
/* Düğme, form, kart, kutu ve rozet dizgeden gelir. Burada yalnızca
   gönderim formuna özgü olanlar durur: adım şeridi, koşul kartlarının
   sol çizgisi, kefil alanı ve istem penceresi. */

/* Adım şeridi: dört adımın numarası. Numara dolu vurgu yüzeyidir,
   üstündeki metin bu yüzden --kut-dolu-metin ile yazılır; iki temada da
   okunur. */
.adim-no{display:inline-grid;place-items:center;width:var(--b-5);height:var(--b-5);border-radius:50%;
  background:var(--kut-dolu);color:var(--kut-dolu-metin);font-size:var(--y-2);font-weight:700;
  margin-bottom:var(--b-2)}
.adim h2{margin:0 0 var(--b-1);font-size:var(--y-5)}
.adim p{margin:0;font-size:var(--y-3);color:var(--metin-2)}

/* Koşul kartı. Soldaki kalın çizgi koşulun türünü söyler: kırmızı bir
   sınır, lacivert bir bilgi. */
.ks{border-left:3px solid var(--cizgi)}
.ks.uyari{border-left-color:var(--kirmizi)}
.ks.bilgi{border-left-color:var(--lacivert)}
.ks h3{margin:0 0 var(--b-2);font-size:var(--y-5)}
/* Koşul kartındaki dış bağlantı, listenin öteki maddeleri gibi bir
   cümlenin parçası değil; tek başına duran bir hedeftir ve WCAG 2.5.8
   AA en az 24 piksel ister. 129x16 ölçülmüştü. Satır kutusu değil
   dolgu büyütüldü: satır yüksekliği artsaydı listenin ritmi bozulurdu.
   Negatif dış boşluk, büyüyen dolgunun listeyi aşağı itmesini önler. */
.ks li > a:only-child{display:inline-block;padding:4px 0;margin:-4px 0}
.ks p{margin:0 0 var(--b-2);font-size:var(--y-3);color:var(--metin-2)}
.ks ul{margin:0;padding-left:var(--b-4);display:grid;gap:var(--b-1);font-size:var(--y-3);color:var(--metin-2)}
.ks .rk{color:var(--kirmizi);font-family:var(--baslik);font-size:var(--y-7)}

/* Form kartı. Sağ raydaki içindekiler listesinden atlanınca yapışkan
   üst çubuğun altında kalmasın. */
/* Form bölümü sayfanın gövdesinden bir ton koyu bir zeminde durur:
   okunacak bölüm bitti, doldurulacak bölüm başladı. */
/* ÇİZGİ KALDIRILDI — 14 Ağustos 2026, kurul isteği: "uzun çizgileri
   kaldır". Bölüm zaten zemin değişimiyle ayrılıyordu; üstüne bir de
   çizgi çekmek aynı şeyi iki kez söylemekti ve düğme sırasının hemen
   altına düştüğü için sırayı ikiye bölüyormuş gibi duruyordu. */
.bv-form-bol{background:var(--yuzey-2)}
.adimlar,.kosullar{margin-top:var(--b-5)}
/* KOŞUL KARTLARI KENDİ BOYUNDA DURUR.
   Izgara varsayılan olarak satırdaki bütün kartları en uzununa
   uzatıyordu: "Yazar niteliği" kartı 928 piksel olduğu için yanındaki
   üç kart da 928 piksel oluyor ve içleri boş kalıyordu. Ekranda dört
   uzun sütun, üçü yarı boş. align-items:start ile her kart kendi
   içeriği kadar yer kaplar; ızgara tırtıklı olur ama boş kutu kalmaz.
   Tırtıklı bir ızgara, içi boş bir kutudan daha okunaklıdır. */
.kosullar{align-items:start}
/* KART İÇİNDE KART OLMAZ. Koşullar sihirbazın birinci adımına
   taşınınca altı koşul kartı, adımın kendi kartının içine girdi ve
   sayfa iç içe iki kenarlıkla kabarcıklı göründü. Adımın içindeyken
   koşul bir KART değil, sol çizgisiyle ayrılmış katlanır bir satırdır. */
.kosul-ic .ks.uyari{border-left-color:var(--kirmizi)}
.kosul-ic .ks.bilgi{border-left-color:var(--lacivert)}
.kosul-onay{margin-top:var(--b-4)}
.kosul-not{margin:0 0 var(--b-4)}
.onay small{display:block;margin-top:2px;font-size:var(--y-2)}
/* KATLANIR KOŞUL SATIRI. Altı koşulun tam metni 1300 piksel tutuyordu
   ve sihirbazın birinci adımı yine bir metin duvarıyla açılıyordu.
   Metin kısaltılmadı, KATLANDI: satır adını ve tek cümlelik özünü
   gösterir, tamamı bir tıkla açılır. Katlanan metin gizli metin
   değildir: sayfada durur, aranabilir, yazdırmada açılır. */
.kosul-ic{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(400px,100%),1fr));
  gap:var(--b-2) var(--b-5);align-items:start;margin:0 0 var(--b-5)}
.kosul-ic .ks{background:transparent;border:0;border-left:3px solid var(--cizgi);
  border-radius:0;box-shadow:none;padding:0}
.kosul-ic .ks > summary{list-style:none;cursor:pointer;display:flex;flex-wrap:wrap;
  align-items:baseline;gap:2px var(--b-3);padding:var(--b-2) var(--b-3);min-height:var(--hedef)}
.kosul-ic .ks > summary::-webkit-details-marker{display:none}
.kosul-ic .ks > summary:hover{background:var(--yuzey-2)}
.kosul-ic .ks > summary::after{content:"";margin-left:auto;width:8px;height:8px;flex:none;
  align-self:center;border-right:2px solid var(--metin-3);border-bottom:2px solid var(--metin-3);
  transform:rotate(45deg) translateY(-2px);transition:transform .15s ease}
.kosul-ic .ks[open] > summary::after{transform:rotate(-135deg) translateY(-2px)}
.kosul-ic .ks h3{margin:0;font-size:var(--y-5)}
.ks-oz{font-size:var(--y-3);color:var(--metin-2);flex:1 1 22ch;min-width:0}
/* Açılan koşul metni tek sütuna yayılınca satır 95 karaktere çıktı
   (ölçüldü); okunur satır 60-75 karakterdir. Kutu değil SATIR
   sınırlanır: kutunun genişliği listenin ritmini tutar. */
.ks-ic{padding:0 var(--b-3) var(--b-3) var(--b-3)}
/* KOŞUL LİSTESİ GENİŞ EKRANDA İKİ SÜTUNDUR.
   Bir üst çözüm satırı dizgenin en geniş ölçüsünde (92ch) tutmaktı;
   1440 pikselde işe yaradı ama 1835 pikselde kap 1054'e çıkınca
   metin 819'da kaldı ve sağda yine 223 piksel boşluk açıldı. Kusur
   ölçüde değil, TEK ÖLÇÜM GENİŞLİĞİNDEYDİ: 1440'ta bakıp geçmiştim.
   Doğrusu, kalan yeri bir işe yaramaya çevirmektir: kap iki okunur
   sütuna yetiyorsa iki sütun olur (400 piksel eşiği), yetmiyorsa tek
   sütun kalır ve metin kabı doldurur. Böylece hiçbir genişlikte ne
   boşluk kalır ne de satır 92 karakteri aşar. */
.ks-ic > p,.ks-ic > ul{max-width:var(--olcu-metin-genis)}
.ks-ic > :last-child{margin-bottom:0}
@media print{ .kosul-ic .ks > .ks-ic{display:block !important} }
/* .ks-genis kuralı kaldırıldı. Koşullar bir kart ızgarası değil,
   katlanır bir liste oldu; tek sütunda okunur. Kural yerinde
   bırakılsaydı tek sütunluk ızgarada "span 2" olmayan bir ikinci
   sütun uydururdu — bu, ölçümde iki sütun olarak göründü ve
   kimsenin karar vermediği bir düzendi. */
/* Forma iniş bağı: başlık bloğunun altında, açıklamasıyla birlikte. */
.bas-eylem{display:flex;flex-wrap:wrap;align-items:center;gap:var(--b-2) var(--b-3);
  margin-top:var(--b-4)}
.bas-eylem-ack{font-size:var(--y-2);color:var(--marka-sonuk)}
.bv-eylem{margin-top:var(--b-5)}
.bv-kart{margin-bottom:var(--b-4);scroll-margin-top:calc(var(--ust) + var(--b-5))}
/* Gönderme düğmesi son alandan bir basamak ayrılır. */
.bv-gonder{margin-top:var(--b-5)}
.bv-kart h3{margin:0 0 var(--b-1);font-size:var(--y-6)}
/* Kart başlığının altındaki açıklama. */
.bv-ack{margin:0 0 var(--b-4);font-size:var(--y-3);color:var(--metin-2)}

/* ---- GÖZDEN GEÇİR VE GÖNDER ----
   Son adımdaki özet. Her bölüm bir başlık, bir "Düzenle" düğmesi ve
   ad-değer satırlarından oluşur. Satır ızgarası iki sütundur ve dar
   ekranda tek sütuna iner; değer uzun olabilir (başlık, kurum adı),
   bu yüzden kırılabilir. */
.bv-eksik{margin:0 0 var(--b-4);padding:var(--b-3) var(--b-4);
  border:1px solid var(--kirmizi);border-radius:var(--r-2);
  background:color-mix(in srgb,var(--kirmizi) 6%,var(--yuzey))}
.bv-eksik[hidden]{display:none}
.bv-eksik h4{margin:0 0 var(--b-2);font-size:var(--y-3);color:var(--kirmizi)}
.bv-eksik ul{margin:0;padding-inline-start:var(--b-5);display:grid;gap:var(--b-1);
  font-size:var(--y-3)}
.bv-eksik button{background:none;border:0;padding:0;color:var(--kut);
  font:inherit;text-decoration:underline;cursor:pointer;text-align:start}
.bv-ozet{margin:0 0 var(--b-5);display:grid;gap:var(--b-3)}
.bv-oz-blok{border:1px solid var(--cizgi);border-radius:var(--r-2);
  background:var(--yuzey);padding:var(--b-3) var(--b-4)}
.bv-oz-bas{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;
  justify-content:space-between;margin-bottom:var(--b-2)}
.bv-oz-bas b{font-size:var(--y-3)}
.bv-oz-sat{display:grid;gap:2px var(--b-4);font-size:var(--y-2);
  padding:var(--b-1) 0;border-top:1px solid var(--cizgi)}
@media(min-width:560px){ .bv-oz-sat{grid-template-columns:minmax(0,13rem) minmax(0,1fr)} }
.bv-oz-sat:first-of-type{border-top:0}
.bv-oz-ad{color:var(--metin-2)}
.bv-oz-deger{overflow-wrap:anywhere}
.bv-oz-bos{color:var(--metin-2);font-style:italic}
.bv-alt-bas{margin:var(--b-6) 0 var(--b-2);font-size:var(--y-5)}
/* Seçenek kümesi ile onu izleyen alan arasında bir basamak boşluk. */
.bv-secim{margin-bottom:var(--b-3)}
/* Zorunlu ve isteğe bağlı imleri: etiketin yanında küçük versal. */
.zor,.ist{font-size:var(--y-1);font-weight:700;letter-spacing:.04em;text-transform:uppercase}
.ist{color:var(--metin-2);font-weight:600}

/* =====================================================================
   ÇALIŞMANIN DİLİ / KÜNYE DİLİ SEKMELERİ
   ---------------------------------------------------------------------
   Şerit dizgedeki .sek-bar ile aynı görünür; ayrı bir sınıf, çünkü
   burada her sekmenin altında bir açıklama satırı var ("kaydın kendisi"
   / "künye · isteğe bağlı") ve o satır olmadan iki sekmenin ne olduğu
   anlaşılmıyordu. Renk, yükseklik ve alt çizgi belirteçlerden gelir;
   palet değişmez.

   BETİKSİZ DAVRANIŞ: .bv-dil-pnl varsayılan olarak AÇIKTIR. Gizleme
   yalnızca betik .bv-dil-js sınıfını koyduğunda başlar. Bu sıra
   bilerek böyle: betiği çalışmayan bir tarayıcıda ikinci dilin alanları
   hiç görünmeseydi, o kişi künyeyi hiç yazamazdı.
   ===================================================================== */
.bv-dil{margin:0 0 var(--b-4)}
.bv-dil-bar{display:flex;gap:2px;overflow-x:auto;scrollbar-width:none;
  border-bottom:1px solid var(--cizgi);margin-bottom:var(--b-4)}
.bv-dil-bar::-webkit-scrollbar{display:none}
.bv-dil-sk{flex:0 0 auto;display:grid;gap:1px;justify-items:start;white-space:nowrap;
  min-height:46px;padding:var(--b-2) var(--b-4);border:0;border-bottom:2px solid transparent;
  background:transparent;color:var(--metin-2);font:inherit;cursor:pointer;text-align:left;
  transition:color var(--gecis),border-color var(--gecis)}
.bv-dil-sk > span{font-size:var(--y-3);font-weight:600}
.bv-dil-sk > small{font-size:var(--y-1);letter-spacing:.03em;text-transform:uppercase;
  color:var(--metin-2)}
.bv-dil-sk:hover{color:var(--metin)}
.bv-dil-sk.secili{color:var(--kut);border-bottom-color:var(--kut)}
.bv-dil-sk[hidden]{display:none !important}
/* Kapalı sekmede yazı varsa okur bunu bilmelidir: kapalı bir bölmeye
   yazdığını unutan biri, boş sandığı bir künyeyle gönderir. */
.bv-dil-sk.dolu > span::after{content:"•";margin-left:6px;color:var(--kut)}
/* Zorunlu ama eksikse im kırmızıdır ve önceliklidir: kapalı bir
   sekmenin içinde zorunlu bir alan olduğunu, ileriye basıp geri
   fırlatıldığında öğrenmek kötü bir öğrenme yoludur. */
.bv-dil-sk.eksik > span::after{content:"•";margin-left:6px;color:var(--kirmizi)}
.bv-dil-js .bv-dil-pnl:not(.acik){display:none}
.bv-dil-pnl[hidden]{display:none !important}

/* Kefil alanı: unvan alanı boş bırakıldığında açılır. */
.kefil-alan{margin-top:var(--b-4)}
.kefil-bas{font-size:var(--y-4);font-weight:700;margin:0 0 var(--b-1)}
.kefil-ack{font-size:var(--y-3);color:var(--metin-2);margin:0 0 var(--b-3)}
.kefil-k{margin-top:var(--b-3)}
.kefil-k > h5{margin:0 0 2px}
.kefil-k > p{margin:0 0 var(--b-2);font-size:var(--y-2);color:var(--metin-2)}

/* Yazar ve hakem satırları: henüz kaydedilmemiş, eklenip çıkarılabilen
   satırlar. Kesik çizgi bunu söyler. */
.yazar-sat{position:relative;margin-top:var(--b-3);padding:var(--b-4);
  border:1px dashed var(--cizgi-2);border-radius:var(--r-3)}
.ysira{font-size:var(--y-1);font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--kut);margin-bottom:var(--b-3)}
.yazar-sat .sil{position:absolute;top:var(--b-3);right:var(--b-3)}

/* İstem penceresinin biçimi k/istem.php'de: aynı pencere yazar
   panelinde de açılıyor ve iki yerde iki ayrı biçim, bir gün iki ayrı
   pencere demekti. */
{$istemStil}

/* =====================================================================
   GÖNDERİM SİHİRBAZI
   ---------------------------------------------------------------------
   Bölümler artık <fieldset>. Tarayıcının kendi fieldset biçimi (çerçeve,
   iç dolgu ve min-width:auto) kart görünümünü bozar; min-width:0 olmadan
   fieldset ızgara hücresinde daralmayı reddeder ve sayfa yatay taşar.
   ===================================================================== */
.sh-adim{min-width:0;border:1px solid var(--cizgi);margin:0}
.sh-adim > legend{padding:0}
/* [hidden] TARAYICI KURALIDIR VE SINIFA YENİLİR.
   Tarayıcının kendi sayfasında [hidden]{display:none} yazılıdır; ama
   özgüllüğü bir sınıf seçicisiyle aynıdır ve sonra gelen sınıf kuralı
   onu ezer. Bu ölçüldü: .sh-adim'e display:block eklendiğinde kapalı
   adımlar ekranda görünmese bile SEKME SIRASINDA duruyor ve klavyeyle
   gezen biri 29 kez kapalı bir adımın içine düşüyordu.
   Bu satır o sınıf hatasını büsbütün kapatır: bundan sonra .kart ya da
   .sh-adim'e ne yazılırsa yazılsın, kapalı adım kapalı kalır. */
.sh-adim[hidden]{display:none !important}

/* Adım şeridi. Sıra bir listedir: <ol>. Numaralar CSS'ten değil
   gövdeden gelir, çünkü ekran okuyucu "3. adım" diyebilmeli. */
.sh-serit{list-style:none;display:flex;flex-wrap:wrap;gap:var(--b-2);
  margin:0 0 var(--b-5);padding:0}
.sh-serit a{display:flex;align-items:center;gap:var(--b-2);padding:6px 10px;
  border:1px solid var(--cizgi);border-radius:var(--r-2);text-decoration:none;
  color:var(--metin-2);font-size:var(--y-2);background:var(--yuzey)}
.sh-serit a:hover{border-color:var(--kut-dolu)}
.sh-no{display:inline-grid;place-items:center;width:20px;height:20px;flex:0 0 20px;
  border-radius:50%;background:var(--yuzey-2);color:var(--metin-2);
  font-size:var(--y-1);font-weight:700}
.sh-simdi a{border-color:var(--kut-dolu);color:var(--metin)}
.sh-simdi .sh-no{background:var(--kut-dolu);color:var(--kut-dolu-metin)}
/* Geçilmiş adım: doldurulmuş sayılır, ama "doğru" denmez. Sistem
   kullanıcıya bilmediği bir şeyi söylemez. */
.sh-gecti .sh-no{background:var(--yesil,var(--kut-dolu));color:var(--kut-dolu-metin)}
.sh-durum{margin:0 0 var(--b-4);font-size:var(--y-3);color:var(--metin-2)}
.sh-durum:empty{display:none}
.sh-gez{display:flex;align-items:center;justify-content:space-between;gap:var(--b-3);
  margin-top:var(--b-5);flex-wrap:wrap}
.sh-say{font-size:var(--y-3);color:var(--metin-2)}
.sh-taslak{margin:var(--b-3) 0 0;font-size:var(--y-2);color:var(--metin-2)}
.sh-taslak button{margin-left:var(--b-2)}
@media (max-width:640px){
  .sh-ad-metin{display:none}
  .sh-serit a{padding:6px}
}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Çalışma gönder', 'Submit a work'),
    'yol' => '/basvuru.php',
    'ek_bas' => $ekBas . kd_bas() . kd_yapi_stil(),
    /* SAYFA AÇIKLAMASI DA KURALDAN GELİR.
       İngilizcesi "A doctoral degree and an ORCID are required of every
       author" diye ELLE yazılmıştı ve doktora şartı kalktıktan sonra
       yanlıştı: arama sonuçlarında ve paylaşım kartlarında görünen
       cümle, sistemin uygulamadığı bir kuralı duyuruyordu. İki dil de
       aynı işlevden okuyor; kural değişirse ikisi birden değişir. */
    'aciklama' => k_c('Kutadgu\'ya çalışma gönderme koşulları ve başvuru formu. ', 'Conditions and application form for submitting work to Kutadgu. ')
                  . tg_yazarlik_kosulu_kisa(k_en()),
]);
?>

<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
   <div>
    <span class="bas-ust"><?= k_c('Açık erişim · Açık hakemlik', 'Open access · Open peer review') ?></span>
    <h1 style="max-width:20ch"><?= k_c('Çalışmanızı gönderin', 'Submit your work') ?></h1>
    <p><?= k_c(
      'Burası kör hakemliğin uygulanmadığı bağımsız bir yayın ortamıdır. Hakemler değerlendirmelerinin arkasında adlarıyla durur; karar ve gerekçe, çalışmayla birlikte kalıcı olarak yayımlanır. Başvuru on bir adımdır ve birincisi gönderim koşullarıdır; her adımda ne istendiği yazılıdır.',
      'This is an independent publishing venue where blind review is not practised. Reviewers stand behind their assessments by name; the decision and its grounds are published permanently alongside the work. The application has eleven steps and the first is the submission conditions; each step states what is asked of you.'
    ) ?></p>
    <?php /* BURADA ARTIK "FORMA GİT" BAĞI YOK, ÇÜNKÜ FORM BURADA.
             Eskiden sayfa koşullarla açılıyor, form iki bin piksel
             aşağıda başlıyordu ve başlığa "başvuru formuna git" diye
             bir bağ konmuştu. Bağ, kusurun kendisini değil belirtisini
             düzeltiyordu: yazar hâlâ formu aramak zorundaydı. Form
             sayfanın ilk ekranında; koşullar da onun BİRİNCİ ADIMI. */ ?>
   </div>
  </div>
</section>

<!-- ================= FORM ================= -->
<section class="bolum bv-form-bol" id="form">
  <div class="kap blg">
   <div class="blg-ic">
    <span class="bas-ust"><?= k_c('Form', 'Form') ?></span>
    <h2><?= k_c('Başvuru formu', 'Application form') ?></h2>

    <?php /* BETİKSİZ GÖNDERİMİN YANITI.
             Betik varken sonuç sayfada gösterilir ve sayfa hiç
             değişmez. Betik yokken tarayıcı formu POST eder, uç 303 ile
             buraya döner ve sonucu tek kullanımlık bir çerezle taşır.
             Çerez okunur okunmaz silinir: aynı ileti ikinci bir
             ziyarette yeniden görünmemeli, çünkü o ziyarette doğru
             değildir. */
          $bvSonuc = (string)($_COOKIE['kbv_sonuc'] ?? '');
          if ($bvSonuc !== '') {
              setcookie('kbv_sonuc', '', ['expires' => time() - 3600, 'path' => '/']);
              [$bvIyi, $bvIleti] = array_pad(explode('|', $bvSonuc, 2), 2, '');
              $bvIleti = trim(mb_substr($bvIleti, 0, 600));
          } ?>
    <?php if ($bvSonuc !== '' && $bvIleti !== ''): ?>
    <div class="kutu <?= $bvIyi === '1' ? 'kutu-yes' : 'kutu-uya' ?>" role="status">
      <p style="margin:0"><?= k_esc($bvIleti) ?></p>
    </div>
    <?php endif; ?>

    <?php /* =============================================================
             ÖNCE HESAP, SONRA GÖNDERİM
             -------------------------------------------------------------
             Kurul kararı, 15 Ağustos 2026: "çalışma gönder kısmı kişiyi
             kayıt sayfasına yöneltmeli ki yazar kısımları boş olmamış
             olur."

             NİÇİN DOĞRU. Form artık TAM METNİ de alıyor; tam metin bir
             künye değil, sahibi olan bir kayıttır. Sahibi olmayan bir
             metin şu üç şeyi yapamaz: yazarı geri dönüp düzeltemez,
             editör kararı kime bildirileceğini bilemez, ortak yazar
             daveti kimin adına çıktığını söyleyemez. Zaten gönderimden
             sonra sistem kişiye hesap AÇIYORDU; yapılan değişiklik o
             hesabı gönderimden ÖNCEYE almaktır. Yeni bir engel değil,
             aynı adımın yer değiştirmesidir.

             İKİLİK YOK. Kayıtsız kişi kapıdan geri çevrilmiyor: aynı
             sayfadan hesabını açıyor ve buraya dönüyor. Ayrı bir "üye
             formu" ile "misafir formu" olsaydı iki doğrulama, iki hata
             listesi ve zamanla iki ayrı kural olurdu.

             TASLAK KAYBOLMAZ: yazdıkları tarayıcıda duruyor ve dönünce
             geri geliyor (aşağıdaki taslak düzeni).
             ============================================================= */ ?>
    <?php /* KUTU KISA TUTULUR. Ölçüldü: uzun bir açıklama ve iki
             düğmeyle bu kutu formun ilk alanını ekranın altına
             itiyordu (950 piksel; eşik 900). Aynı kusur bir kez
             düzeltilmişti — koşullar sayfanın başındayken formun ilk
             alanı 2186 piksele düşüyordu. Gerekçenin uzunu kılavuzda
             ve hesap sayfasında; burada bir cümle yeter. */ ?>
    <?php if (!$benGirisli): ?>
    <p class="kutu kutu-kut bv-kapi" id="bvKapi"><?= k_c(
        '<b>Göndermek için hesap gerekiyor.</b> Yazdıklarınız bu tarayıcıda saklanır; hesabınızı açıp döndüğünüzde olduğu gibi geri gelir ve ad, kurum, ORCID alanları dolu olur.',
        '<b>An account is needed in order to send.</b> What you write is kept in this browser; it comes back unchanged when you return with an account, and the name, institution and ORCID fields arrive filled.'
      ) ?> <a href="<?= k_esc(k_bag('/panel.php') . (strpos(k_bag('/panel.php'), '?') === false ? '?' : '&') . 'donus=' . rawurlencode('/basvuru.php')) ?>#hesap"><?= k_c('Giriş yapın ya da hesap açın', 'Sign in or open an account') ?></a></p>
    <?php endif; ?>

    <?php /* SİHİRBAZ BİR KABUKTUR, TEK YOL DEĞİLDİR.
             Aşağıdaki her şey gerçek bir <form> içindedir ve her alanın
             name'i vardır: betik hiç çalışmazsa sayfa eskisi gibi tek
             uzun form olarak durur, gönder düğmesi formu POST eder ve
             başvuru düşer. Betik çalışırsa aynı bölümleri adım adım
             açar. Sihirbaz sayfanın ÜSTÜNE eklenmiştir, YERİNE değil.

             Eylem doğrudan başvuru ucuna gider; uç, "bicim=form"
             gördüğünde JSON değil sayfaya yönlendirme döndürür (bkz.
             api/index.php, cikti()). Böylece betiksiz kullanıcı ekranda
             ham JSON görmez. */ ?>
    <form id="bvForm" class="sh" method="post"
          action="<?= k_esc(k_bag('/api/yazar-basvuru')) ?>"
          data-adim-baslangic="<?= $shAdim ?>"
          data-kosul-surum="<?= k_esc($kosulSurum) ?>">
      <input type="hidden" name="bicim" value="form">
      <input type="hidden" name="geri" value="<?= k_esc(k_bag('/basvuru.php')) ?>">

      <?php /* ADIM ŞERİDİ. Sıra bir listedir, bu yüzden <ol>. Betik
               yokken de basılır: kullanıcı formun kaç bölümden
               oluştuğunu ve nerede olduğunu görür. */ ?>
      <ol class="sh-serit" id="shSerit">
        <?php foreach ($shAdimlar as $i => $a): ?>
        <li class="sh-ad<?= ($i + 1) === $shAdim ? ' sh-simdi' : '' ?>" data-serit="<?= $i + 1 ?>">
          <a href="<?= k_esc(k_bag('/basvuru.php?adim=' . ($i + 1))) ?>#f-<?= k_esc($a['k']) ?>">
            <span class="sh-no"><?= $i + 1 ?></span><span class="sh-ad-metin"><?= k_esc(k_t($a)) ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ol>
      <p class="sh-durum" id="shDurum" aria-live="polite"></p>

      <noscript>
        <div class="kutu kutu-uya">
          <p><?= k_c(
            'Betik çalışmıyor. Form bu hâliyle de gönderilebilir: bütün bölümler açıktır, doldurup en alttaki <b>Başvuruyu gönder</b> düğmesine basmanız yeterlidir. Adım adım ilerleme, yarıda bırakıp geri dönme ve alan alan uyarı yalnızca betik çalıştığında vardır.',
            'Scripting is not running. The form can still be sent as it stands: every section is open, so fill it in and press <b>Send the application</b> at the bottom. Stepping through, resuming a half filled form and field by field warnings are available only when scripting runs.'
          ) ?></p>
          <p><?= k_c(
            'Ortak yazar ve önerilen hakem satırları betikle eklenir; betiksiz gönderimde yalnız başvuran yazar olarak kaydedilir. Bu durumda unvanınızın en az "Dr." olması gerekir.',
            'Co author and suggested reviewer rows are added by script; in a script free submission only the applicant is recorded as author. In that case your title must be at least "Dr.".'
          ) ?></p>
        </div>
      </noscript>

    <div id="formKap">
      <?php /* BİRİNCİ ADIM: GÖNDERİM KOŞULLARI.
               Bu kartlar sayfanın başında ayrı bir bölümdü; metni
               değişmedi, yeri değişti. Koşulu okumak formu doldurmanın
               önündeki engel değil, ilk adımıdır — DergiPark'ın kontrol
               listesi adımı da böyle çalışır.

               Onay kutusu bir tören değil, bir KAYITtır: başvuruyla
               birlikte "koşullar okundu" bilgisi de saklanır. Kutu
               required'dır; betik çalışmasa da tarayıcı gönderimi
               durdurur. */ ?>
      <fieldset class="kart bv-kart sh-adim" id="f-kosul" data-adim="kosul">
        <legend class="gorsel-gizli"><?= $adimAd('kosul') ?></legend>
        <h3><?= k_c('Gönderim koşulları', 'Submission conditions') ?></h3>
        <?php /* Yerel (girişsiz) atlamada aynı not betikle açılır; iki
                 ayrı metin yazılmaz, aynı kutu kullanılır. */ ?>
        <?php if (!$kosulGecmis['okundu']): ?>
        <p class="kutu kutu-lac kosul-not" hidden><?= k_c(
          'Bu koşulları daha önce bu tarayıcıda okuduğunuzu bildirmiştiniz ve o günden bu yana metin değişmedi. Bu yüzden başvuru doğrudan sonraki adımdan açıldı; koşullar burada, olduğu gibi duruyor.',
          'You reported having read these conditions in this browser and the text has not changed since. That is why the application opened at the next step; the conditions stand here, unchanged.'
        ) ?></p>
        <?php endif; ?>
        <?php if ($kosulGecmis['okundu']): ?>
        <?php /* Daha önce okumuş olan için adım atlanır ama SİLİNMEZ:
                 şeritten her an açılır ve ne zaman okunduğu yazılıdır.
                 "Okumuş sayıldınız" demek yetmez; hangi gün, hangi
                 metin için okuduğu görünmelidir. */ ?>
        <p class="kutu kutu-lac kosul-not"><?= k_c(
          'Bu koşulları <b>' . k_tarih($kosulGecmis['tarih']) . '</b> tarihinde okuduğunuzu bildirmiştiniz ve o günden bu yana metin değişmedi. Bu yüzden başvuru doğrudan sonraki adımdan açıldı; koşullar burada, olduğu gibi duruyor.',
          'You reported having read these conditions on <b>' . k_tarih($kosulGecmis['tarih']) . '</b> and the text has not changed since. That is why the application opened at the next step; the conditions stand here, unchanged.'
        ) ?></p>
        <?php endif; ?>
        <?php /* YAZARLIK KOŞULU HER DURUMDA YAZILI OLMALI.
                 Cümle daha önce yalnız "Yazar niteliği" kartının
                 içindeydi; o kart doktora şartı kapalıyken hiç
                 çizilmiyor ve gönderim koşullarını okuyan kişi
                 yazarlığın koşulunu hiçbir yerde görmüyordu. Koşulun
                 kalkmış olması, söylenmemesi demek değildir: "doktora
                 aranmaz, kararı editör verir" de bir koşuldur.

                 AYRI BİR KUTU AÇILMADI, VAR OLAN GİRİŞ CÜMLESİNE
                 EKLENDİ. Ölçüldü: ayrı bir kutu birinci adımın ilk
                 alanını 969 piksele itiyordu (eşik 900) ve bu, bir kez
                 düzeltilmiş bir kusurun geri gelmesi olurdu — koşullar
                 sayfanın başındayken formun ilk alanı 2186 piksele
                 düşüyordu. Cümle ortak.php'den gelir, elle yazılmaz. */ ?>
        <p class="bv-ack"><b><?= k_esc(tg_yazarlik_kosulu_kisa(k_en())) ?></b> <?= k_c(
          'Aşağıdakiler bu sistemin çalışmadan aradığı koşullardır. Okuyun; sonraki adımlarda bunların her biri için belge ya da beyan istenecek.',
          'These are the conditions this system asks of a work. Read them; the following steps will ask for a document or a declaration for each of them.'
        ) ?></p>
  <div class="kosullar kosul-ic">
        <?php /* Bu kart ötekilerin üç katı uzunlukta (882 piksele karşı
                 220-356) çünkü yazarlık koşulu sistemin en ayrıntılı
                 kuralı. Tek sütunda kaldığında satırın öteki kartları
                 kısacık kalıyor ve ızgara "bir uzun sütun, dört kısa"
                 görünüyordu. İki sütuna yayılınca metin genişliyor,
                 yükseklik yarıya iniyor ve satır dengeleniyor. Kartın
                 içeriği kısaltılmadı: kural kısaltılacak bir şey değil,
                 yalnız daha geniş bir yere yazılıyor. */ ?>
        <?php /* YAZAR NİTELİĞİ KARTI, ŞART KAPALIYKEN YOK — kurul
                 kararı, 15 Ağustos 2026: "yazar niteliği bilgisinin
                 yazmasına gerek yok." Doğru: doktora aranmıyorsa kart
                 "aranmaz" demekten başka bir şey söylemiyor ve gönderim
                 koşullarının ilk sırasında duruyordu. Şart geri açılırsa
                 kart da geri gelir; ORCID zorunluluğu ise zaten
                 Başvuran adımında alanın yanında yazılı. */ ?>
        <?php if (tg_yazarlik_doktora_sarti()): ?>
        <details class="ks">
          <summary>
            <h3><?= k_c('Yazar niteliği', 'Author standing') ?></h3>
            <span class="ks-oz"><?= tg_yazarlik_doktora_sarti()
                 ? k_c('En az doktora; olmayan için iki doktoralı ' . tg_destek_ad() . '. Her yazara ORCID.', 'At least a doctorate; otherwise two ' . tg_destek_ad(true, true) . ' with doctorates. ORCID for every author.')
                 : k_c('Doktora aranmaz. Her yazara ORCID. Kararı editör verir.', 'No doctorate required. An ORCID for every author. An editor decides.') ?></span>
          </summary>
          <div class="ks-ic">
            <p><?= tg_yazarlik_doktora_sarti()
                 ? k_c('Yazarların en az doktora (Dr.) unvanına sahip olması aranır. Doktora derecesi henüz olmayan bir araştırmacı da yazar olarak yer alabilir; bunun için <b>iki doktoralı ' . tg_destek_ad() . '</b> gerekir: biri bu çalışmanın ortak yazarı, biri çalışmayla bağı olmayan bağımsız bir araştırmacı. Her ikisi de kendisine gönderilen bağlantıdan gerekçesiyle onaylar; onay gelmeden çalışma yayına alınmaz.', 'Authors are asked to hold at least a doctoral degree. A researcher who does not yet hold one may also appear as an author; this requires <b>two ' . tg_destek_ad(true, true) . ' with doctorates</b>: one a co author of this work, one an independent researcher with no connection to it. Each confirms with their reasoning through a link sent to them; the work is not published until both confirmations arrive.')
                 : k_c('<b>Doktora derecesi aranmaz</b>; çalışmayı herkes gönderebilir. Her yazar için <b>ORCID zorunludur</b>, çünkü bu sistemde adın kime ait olduğunu belirleyen tek numara odur. Gönderdiğiniz metni bir editör okur ve sisteme girip girmeyeceğine karar verir. Kabul edilen çalışma önce hakemsiz olarak yayımlanır; dilerseniz hakem aranmasını istersiniz, dilerseniz hakemsiz bırakırsınız.', '<b>No doctorate is required</b>; anyone may submit a work. An <b>ORCID is required</b> for every author, because it is the only number that ties a name to a person in this system. An editor reads what you send and decides whether it enters the system. An accepted work is first published without review; you may then ask for reviewers to be sought, or leave it unreviewed.') ?></p>
          <ul>
            <li><?= k_c('Her yazar için <b>ORCID</b> zorunlu (sağlama basamağı denetlenir)', '<b>ORCID</b> required for every author (check digit is validated)') ?></li>
            <li><?= k_c('Yazarlık desteğiyla yazar olan araştırmacı <b>hakem raporu yazamaz</b>, kurul oyu kullanamaz ve tek yazar olarak çalışma gönderemez', 'A researcher admitted through supporting researchers <b>may not write referee reports</b>, may not cast panel votes, and may not submit as sole author') ?></li>
          </ul>
          <?php /* Gönderim koşullarının okunduğu yer burasıdır; hakemlik
                   koşulunun o an aranıp aranmadığı da burada yazmalıdır.
                   Cümle ortak.php'den gelir, elle yazılmaz. */ ?>
          <?php if (tg_kurulus_donemi() && !tg_yazarlik_hakemlik_sarti()): ?>
          <p class="metin-sonuk"><?= k_esc(tg_yazarlik_kosulu_metni(k_en())) ?></p>
          <?php endif; ?>
          </div>
        </details>
        <?php endif; ?>
        <?php /* KOŞUL KUTUSU DA KURALA BAĞLI.
                 Gönderim koşullarını sayan bu kutunun yanlış olması,
                 sayfanın herhangi bir yerinin yanlış olmasından daha
                 ağırdır: kişi buradaki maddeleri okuduğunu BEYAN
                 ediyor. Okunmamış bir koşulu beyan ettirmek, beyanın
                 kendisini değersizleştirir. */ ?>
        <?php if (tg_benzerlik_sarti()): ?>
        <details class="ks uyari">
          <summary>
            <h3><?= k_c('Benzerlik üst sınırı', 'Similarity ceiling') ?> <span class="rk">%<?= $benz ?></span></h3>
            <span class="ks-oz"><?= k_c('Benzerlik en çok %' . $benz . ', tek kaynaktan en çok %' . $benzTek . '; rapor bağlantısı istenir.', 'Similarity at most ' . $benz . '%, at most ' . $benzTek . '% from a single source; a link to the report is required.') ?></span>
          </summary>
          <div class="ks-ic">
            <p><?= k_c('<b>Benzerlik üst sınırı.</b> iThenticate, Turnitin ya da intihal.net raporuyla belgelenmelidir.', '<b>Similarity ceiling.</b> Must be documented with an iThenticate, Turnitin or intihal.net report.') ?></p>
          <ul>
            <li><?= k_c('Genel benzerlik oranı en çok <b>%' . $benz . '</b>', 'Overall similarity at most <b>' . $benz . '%</b>') ?></li>
            <li><?= k_c('Benzerliğin tamamı tek kaynaktan olamaz: <b>tek kaynak payı en çok %' . $benzTek . '</b>', 'Similarity cannot all come from one source: <b>at most ' . $benzTek . '% from a single source</b>') ?></li>
            <li><?= k_c('Raporun erişilebilir bağlantısı istenir', 'An accessible link to the report is required') ?></li>
          </ul>
          </div>
        </details>
        <?php endif; ?>
        <?php /* AŞIRMA KARTI KALDIRILDI — kurul kararı, 15 Ağustos 2026:
                 "aşırma kısmına da gerek yok, bunu komple kaldıralım."
                 Şart kapalıyken kart yalnızca "rapor istenmez" diyordu;
                 istenmeyen bir şeyi anlatan kart, gönderim koşullarını
                 uzatmaktan başka iş görmüyordu.

                 Aşırma YASAĞI kalkmadı — yayın ilkelerinde durur ve
                 editör okuduğu metinde görür. Kalkan şey, yazardan
                 hiçbir şey istemeyen bir kuralın formda yer kaplaması.
                 Şart geri açılırsa üstteki kart geri gelir. */ ?>
        <details class="ks bilgi">
          <summary>
            <h3><?= k_c('Yapay zekâ kullanımı', 'Use of artificial intelligence') ?></h3>
            <span class="ks-oz"><?= k_c('Yasak değil; kapsamı beyan edilir, yazar olarak gösterilemez.', 'Not prohibited; the extent is declared, and AI cannot be listed as an author.') ?></span>
          </summary>
          <div class="ks-ic">
            <p><?= k_c('Yapay zekâ araçlarından yararlanmak <b>yasak değildir</b>; ancak kapsamı beyan edilmeli ve kullanım, YÖK\'ün ilgili etik rehberi çerçevesinde kalmalıdır.', 'Using AI tools is <b>not prohibited</b>; the extent must be declared and the use must remain within the relevant ethical guidance.') ?></p>
          <ul>
            <li><?= k_c('Yapay zekâ <b>yazar olarak gösterilemez</b>', 'AI <b>cannot be listed as an author</b>') ?></li>
            <li><?= k_c('Kapsam açıkça beyan edilir ve yayımlanır', 'The extent is declared openly and published') ?></li>
            <li><a href="https://proje.yok.gov.tr/tr/page/635" target="_blank" rel="noopener"><?= k_c('YÖK etik rehberi', 'Council of Higher Education ethics guide') ?> &#8599;</a></li>
          </ul>
          </div>
        </details>
        <details class="ks">
          <summary>
            <h3><?= k_c('Şekiller ve tablolar', 'Figures and tables') ?></h3>
            <span class="ks-oz"><?= k_c('Metne gömülü değil, ayrı ve yüksek çözünürlüklü; paylaşımlı klasör yeter.', 'Supplied separately at high resolution, not embedded; a shared folder is enough.') ?></span>
          </summary>
          <div class="ks-ic">
            <p><?= k_c('Şekil ve tablolar metne gömülü değil, <b>ayrı ve yüksek çözünürlüklü</b> olarak sunulmalıdır; yayımlanan çalışmada büyütülebilir bağlantı olarak gösterilir.', 'Figures and tables must be supplied <b>separately and at high resolution</b> rather than embedded in the text; they appear as enlargeable links in the published work.') ?></p>
          <ul><li><?= k_c('Paylaşımlı klasör bağlantısı yeterlidir', 'A shared folder link is sufficient') ?></li></ul>
          </div>
        </details>
        <details class="ks">
          <summary>
            <h3><?= k_c('Veri ve çözümleme', 'Data and analysis') ?></h3>
            <span class="ks-oz"><?= k_c('Görgül çalışmada veri seti ile yazılım ve kodlar paylaşılır.', 'For empirical work the data set and the software and code are shared.') ?></span>
          </summary>
          <div class="ks-ic">
            <p><?= k_c('Görgül çalışmalarda <b>veri seti</b> ile çözümlemede kullanılan <b>yazılım ve kodlar</b> paylaşılmalıdır.', 'For empirical work the <b>data set</b> and the <b>software and code</b> used in the analysis must be shared.') ?></p>
          <ul>
            <li>R, EViews, MATLAB, Orange, Stata, SPSS, Python</li>
            <li><?= k_c('Yeniden üretilebilirlik esastır', 'Reproducibility is essential') ?></li>
          </ul>
          </div>
        </details>
        <details class="ks">
          <summary>
            <h3><?= k_c('Telif ve lisans', 'Copyright and licence') ?></h3>
            <span class="ks-oz"><?= k_c('Telif hakkı sizde kalır; CC BY 4.0 ile yayımlama izni verirsiniz.', 'Copyright remains with you; you grant permission to publish under CC BY 4.0.') ?></span>
          </summary>
          <div class="ks-ic">
            <p><?= k_c('<b>Telif hakkı sizde kalır.</b> Yazar, ' . $marka . ' yayın sistemine yalnızca yayımlama, kalıcı arşivleme ve CC BY 4.0 ile dağıtma izni verir; bu izin münhasır değildir, yani çalışmanızı başka yerlerde de yayımlamayı sürdürebilirsiniz.', '<b>Copyright remains with you.</b> The author grants the ' . $marka . ' publishing system only the permission to publish, to archive permanently and to distribute under CC BY 4.0. The permission is non exclusive: you may continue to publish your work elsewhere as well.') ?></p>
          </div>
        </details>
      </div>

        <div class="d-kume bv-eylem">
        <button class="d d-ikinci d-kucuk" type="button" id="yzAc"><?= k_c('Yapay zekâ denetim istemlerini aç', 'Open the AI review prompts') ?></button>
        <a class="d d-sessiz d-kucuk" href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkelerinin tamamı', 'The full editorial policies') ?></a>
        </div>
      </fieldset>

      <fieldset class="kart bv-kart sh-adim" id="f-calisma" data-adim="calisma">
        <legend class="gorsel-gizli"><?= $adimAd('calisma') ?></legend>
        <h3><?= k_c('Çalışma', 'The work') ?></h3>
        <?php /* ÇALIŞMANIN DİLİ. Arayüzün dili değil, metnin dili.
                 Kendi dilinde yazmak bir istisna değil, olağan yoldur;
                 gerekçesi bildirinin "Dil üzerine" bölümündedir. */ ?>
        <label for="mDil"><?= k_c('Çalışmanın dili', 'Language of the work') ?>
          <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
        <p class="bv-ack"><?= k_c(
          'Çalışmanızı hangi dilde yazdıysanız onu seçin. Kendi ana dilinizde yazmak burada bir istisna değil, olağan yoldur: kayıt her zaman sizin yazdığınız dildeki metindir ve çeviri onun yerine geçmez. Listede diliniz yoksa bize yazın, eklenir; kimse dili listede yok diye başka bir dile geçmek zorunda kalmaz.',
          'Choose the language you wrote your work in. Writing in your own first language is the ordinary path here, not an exception: the record is always the text in the language you wrote it in, and a translation does not take its place. If your language is not listed, write to us and it will be added; no one has to switch language because theirs is missing.'
        ) ?></p>
        <select id="mDil" name="makale_dil">
          <?php foreach ((array)tg_ayar('calisma_dilleri', []) as $dk => $dad): ?>
          <option value="<?= k_esc($dk) ?>"<?= $dk === k_dil() ? ' selected' : '' ?>><?= k_esc($dad) ?></option>
          <?php endforeach; ?>
        </select>

        <?php /* ---------- İKİ DİL, İKİ SEKME ----------
                 KURUL İSTEĞİ: künye ikinci bir dilde de istensin; ikinci
                 dili baş editör kurulu belirler, bugün İngilizcedir.

                 ÖNCEKİ DÜZEN NEDEN YETMİYORDU. Alanlar alt alta
                 diziliydi: başlık, İngilizce başlık, özet. Yani
                 "çalışmanın kendi dili" ile "künye dili" aynı sütunda
                 karışıyor, özetin ikinci dildeki karşılığı ise HİÇ
                 SORULMUYORDU — sayfa iki dilde künye vaat ediyor, kayıt
                 tek dilde tutuyordu.

                 SEKME AYRIMI YAPIYOR. Birinci sekme kaydın kendisidir;
                 ikinci sekme yalnızca künyedir. İkisi ayrı durunca
                 hangisinin asıl olduğu da görünür hâle geliyor.

                 ÜÇ KURAL:
                   1. İkinci sekme İSTEĞE BAĞLIDIR. Zorunlu kılmak,
                      "kayıt yazarın yazdığı dildeki metindir" sözünü
                      sessizce geri almak olurdu. Kurul zorunlu kılarsa
                      ayar.php'de tek satır değişir.
                   2. Çalışmanın dili künye diliyle AYNIYSA ikinci sekme
                      hiç çizilmez: çevrilecek bir şey yok.
                   3. İstenen künyedir, metin değil. Tam metni ikinci
                      dilde istemek başka bir sistem kurmak olurdu.

                 Betiksiz tarayıcıda iki bölme de açık durur ve form
                 aynen çalışır; sekme yalnızca bir düzen aracıdır. */ ?>
        <?php $kunyeKod = tg_kunye_dili(); $kunyeAd = tg_kunye_dil_adi(); ?>
        <?php /* ÖZNİTELİK ADI 'data-bvdil', 'data-dil' DEĞİL.
                 İlk yazımda sekmelere data-dil yazmıştım. Ölçüldü:
                 sekmeye basınca sayfa Kürtçeye dönüyordu. Sebebi
                 k/kutadgu.js'de: [data-dil] taşıyan HER ÖGE bir SİTE
                 DİLİ değiştiricisidir ve değeri dil kodu sayılır —
                 "kunye" dizesinin ilk iki harfi 'ku'dur. Bir öznitelik
                 adı, o adı kimin sahiplendiğine bakmadan seçilemez. */ ?>
        <div class="bv-dil" id="mDilSekme" data-kunye="<?= k_esc($kunyeKod) ?>">
          <div class="bv-dil-bar" role="tablist" aria-label="<?= k_c('Künye dili', 'Language of the citation entry') ?>">
            <button type="button" class="bv-dil-sk secili" data-bvdil="ana" role="tab" aria-selected="true" aria-controls="mDilAna" id="mDilSkAna">
              <span id="mDilAnaAd"><?= k_c('Çalışmanın dili', 'Language of the work') ?></span>
              <small><?= k_c('kaydın kendisi', 'the record itself') ?></small>
            </button>
            <?php if ($kunyeKod !== ''): ?>
            <button type="button" class="bv-dil-sk" data-bvdil="kunye" role="tab" aria-selected="false" aria-controls="mDilKunye" id="mDilSkKunye">
              <span><?= k_esc($kunyeAd) ?></span>
              <small><?= tg_kunye_zorunlu() ? k_c('künye · zorunlu', 'citation entry · required') : k_c('künye · isteğe bağlı', 'citation entry · optional') ?></small>
            </button>
            <?php endif; ?>
          </div>

          <div class="bv-dil-pnl acik" id="mDilAna" role="tabpanel" aria-labelledby="mDilSkAna">
            <label for="mBaslik"><?= k_c('Çalışmanın başlığı', 'Title of the work') ?></label>
            <input type="text" id="mBaslik" placeholder="<?= k_c('Çalışmanızın başlığı', 'The title of your work') ?>" name="makale_baslik">
            <label for="mOzet"><?= k_c('Özet', 'Abstract') ?></label>
            <textarea id="mOzet" placeholder="<?= k_c('Çalışmanın kısa özeti. Kabul sonrası tam metni Word belgesi yükleyerek ya da elle sisteme siz girersiniz.', 'A short abstract. After acceptance you enter the full text yourself, either by uploading a Word file or by hand.') ?>" name="makale_ozet"></textarea>

            <?php /* ---------- YAPILANDIRILMIŞ ÖZ (İSTEĞE BAĞLI) ----------
                     ScholarOne'da öz dört başlıkla isteniyor. Okunurluğu
                     ve dizinlenebilirliği artırdığı doğru; ama o başlıklar
                     tek bir alanın geleneğidir. Kutadgu bütün FORD
                     alanlarına açıktır — bir felsefe denemesine "araştırma
                     tasarımı" sormak, sorunun kendisini anlamsız kılar ve
                     zorunlu olsaydı yazar olmayan bir yöntem uydururdu.
                     Uydurulan bir alan, boş bir alandan kötüdür.

                     Bu yüzden yapı SUNULUR, DAYATILMAZ. Kapalıyken özet
                     eskisi gibi tek bir serbest metindir.

                     BÖLÜMLER TEK KAYNAKTAN (tg_ozet_bolumleri) BASILIR ve
                     ekran betiksiz tarayıcıda da çalışır: kutular açık
                     doğar, gizleyen betiktir. Betiksiz kişi dört kutuyu da
                     görür ve isterse boş bırakır — gizli doğan bir alan,
                     betiği olmayana doldurulamaz bir alan demekti (bu
                     kusur bu formda daha önce üç kez ölçüldü).

                     ÖZETİN KENDİSİ SUNUCUDA TÜRETİLİR: tarayıcının
                     birleştirdiği dizeye güvenilseydi, yapı ile özet
                     birbirini tutmayan bir kayıt doğardı. */ ?>
            <label class="onay" id="mYapiAc">
              <input type="checkbox" id="mOzetYapili" name="ozet_yapili" value="1">
              <span><?= k_c('Özeti bölümlere ayırayım', 'Let me divide the abstract into sections') ?>
                <small><?= k_c('İsteğe bağlı. Amaç, yöntem, bulgular ve özgünlük ayrı ayrı yazılır; özet bunlardan kurulur. Her alana uymayabilir; uymuyorsa kapalı bırakın.', 'Optional. Purpose, method, findings and originality are written separately and the abstract is built from them. It does not suit every field; leave it off if it does not suit yours.') ?></small></span>
            </label>
            <div id="mOzetYapi">
              <?php foreach (tg_ozet_bolumleri() as $bo): ?>
              <label for="oz_<?= k_esc($bo['k']) ?>"><?= k_esc(k_en() ? $bo['en'] : $bo['tr']) ?></label>
              <textarea id="oz_<?= k_esc($bo['k']) ?>" name="ozet_yapi_<?= k_esc($bo['k']) ?>" rows="2" data-ozbolum="<?= k_esc($bo['k']) ?>"></textarea>
              <?php endforeach; ?>
              <p class="bv-ack" id="mOzetYapiNot"><?= k_c(
                'Doldurduğunuz bölümler yukarıdaki özeti oluşturur; boş bıraktıklarınız hiç görünmez.',
                'The sections you fill in make up the abstract above; the ones you leave empty do not appear at all.'
              ) ?></p>
            </div>
          </div>

          <?php if ($kunyeKod !== ''): ?>
          <div class="bv-dil-pnl" id="mDilKunye" role="tabpanel" aria-labelledby="mDilSkKunye">
            <?php /* AÇIKLAMA DA KURALA BAĞLI. Kısa künye 15 Ağustos
                     2026'da zorunlu oldu; cümle "zorunlu değildir" demeyi
                     sürdürseydi sistem uygulamadığı bir kuralı ters
                     yönden duyurmuş olurdu — bu oturumun en sık çıkan
                     kusur sınıfı. Zorunluluk tek kaynaktan sorulur. */ ?>
            <p class="bv-ack" id="mBaslikEnAck"><?= tg_kunye_zorunlu() ? k_cd(
              'Bu sekme kaydın kendisi değil, KÜNYESİDİR. Buradaki başlık ve özet (%1) <b>zorunludur</b>: dizinler, arama motorları ve başka dillerde yazanların atıf künyesi bunları okur; çalışmanızı başka bir dilde arayan biri onu ancak bununla bulur, bulunamayan bir çalışmada açık erişimin bir anlamı kalmaz. Metnin tamamı istenmez ve istenmeyecektir: kayıt her zaman sizin yazdığınız dildeki metindir. Çalışmanızın dili zaten %1 ise bu sekme kendiliğinden kapanır.',
              'This tab is not the record but its CITATION ENTRY. The title and abstract here (%1) are <b>required</b>: indexes, search engines and the citation entry seen by readers writing in other languages read them; someone searching for your work in another language can find it only through these, and open access means little for a work that cannot be found. The full text is not asked for and will not be: the record is always the text in the language you wrote it in. If your work is already in %1 this tab closes by itself.',
              $kunyeAd
            ) : k_cd(
              'Bu sekme kaydın kendisi değil, KÜNYESİDİR. Buradaki başlık ve özet (%1) zorunlu değildir; yalnızca dizinler, arama motorları ve başka dillerde yazanların atıf künyesi için istenir; çalışmanızı başka bir dilde arayan biri onu ancak bununla bulur. Metnin tamamı istenmez ve istenmeyecektir: kayıt her zaman sizin yazdığınız dildeki metindir. Çalışmanızın dili zaten %1 ise bu sekme kendiliğinden kapanır.',
              'This tab is not the record but its CITATION ENTRY. The title and abstract here (%1) are not required; they are asked for only so that indexes, search engines and the citation entry seen by readers writing in other languages can find your work. The full text is not asked for and will not be: the record is always the text in the language you wrote it in. If your work is already in %1 this tab closes by itself.',
              $kunyeAd
            ) ?></p>
            <?php /* ETİKET "%1 başlık" DEĞİL, "Başlık (%1)".
                     Dil adları listede KENDİ dillerinde yazılıdır
                     ('English', 'Deutsch'); Türkçede sıfat olarak
                     kullanılınca "English başlık" gibi bozuk bir tamlama
                     çıkıyordu. Adı parantez içine almak her dilde ve her
                     ikinci dil seçiminde doğru okunur — çekim üretmeye
                     çalışmadan. (Aynı tuzak destek-ad'da da ölçülmüştü.) */ ?>
            <label for="mBaslikEn"><?= k_cd('Başlık (%1)', 'Title (%1)', $kunyeAd) ?>
              <small class="<?= tg_kunye_zorunlu() ? 'zor' : 'ist' ?>"><?= tg_kunye_zorunlu() ? k_c('zorunlu', 'required') : k_c('isteğe bağlı', 'optional') ?></small></label>
            <input type="text" id="mBaslikEn" name="makale_baslik_en"
                   placeholder="<?= k_esc(tg_cd('Çalışmanızın başlığı (%1)', 'The title of your work (%1)', null, $kunyeAd)) ?>">
            <label for="mOzetEn"><?= k_cd('Özet (%1)', 'Abstract (%1)', $kunyeAd) ?>
              <small class="<?= tg_kunye_zorunlu() ? 'zor' : 'ist' ?>"><?= tg_kunye_zorunlu() ? k_c('zorunlu', 'required') : k_c('isteğe bağlı', 'optional') ?></small></label>
            <textarea id="mOzetEn" name="makale_ozet_en" placeholder="<?= k_esc(tg_cd('Özetin karşılığı (%1).', 'The abstract (%1).', null, $kunyeAd)) ?>"></textarea>

            <?php /* ---------- GENİŞLETİLMİŞ ÖZET ----------
                     KURUL KARARI: "Kişi Arapça makalesini eklerse
                     İngilizce genişletilmiş özetini versin."

                     NEDEN KISA ÖZET YETMİYOR. Sistemin sözü duruyor:
                     kayıt yazarın kendi dilindeki metindir ve tam metin
                     ikinci dilde İSTENMİYOR. Ama iki yüz kelimelik bir
                     künye, Arapça yazılmış bir çalışmanın ne yaptığını
                     başka dilden bir okura anlatmaz; anlatmadığı sürece
                     o çalışma fiilen görünmez kalır. Açık erişim,
                     görünmeyen bir çalışmada bir anlam taşımaz.

                     Bölme yalnızca GEREKTİĞİNDE çizilir: çalışmanın
                     dili künye diliyse istenmez, çünkü aynı metni iki
                     kez istemek olurdu. Betik dili değiştirdiğinde
                     bölmeyi açıp kapatır; sunucu da ilk çizimde aynı
                     kuralı uygular (tg_genis_ozet_gerek). */ ?>
            <?php $gOz = tg_genis_ozet_ayar(); ?>
            <div id="mGenisKutu"<?= tg_genis_ozet_gerek(k_dil()) ? '' : ' hidden' ?>>
              <label for="mGenisOzet"><?= k_cd('Genişletilmiş özet (%1)', 'Extended abstract (%1)', $kunyeAd) ?>
                <small class="ist" id="mGenisZor"><?= $gOz['zorunlu'] ? k_c('zorunlu', 'required') : k_c('isteğe bağlı', 'optional') ?></small></label>
              <p class="ipucu"><?= k_cd(
                'Çalışmanız %1 dışında bir dilde yazıldığı için, %1 dilinde genişletilmiş bir özet istenir: sorunuz, yönteminiz, bulgunuz ve sonucunuz. Hedef %2 kelime, en az %3. Tam metin istenmez ve istenmeyecektir: kayıt her zaman sizin yazdığınız dildeki metindir. Kendiniz çevirebilir ya da yapay zekâdan yardım alabilirsiniz; aşağıdaki istemler üslubunuzu koruyacak biçimde yazıldı.',
                'Because your work is written in a language other than %1, an extended abstract in %1 is asked for: your question, your method, your findings and your conclusion. The target is %2 words, the minimum %3. The full text is not asked for and will not be: the record is always the text in the language you wrote it in. You may translate it yourself or take help from an AI; the prompts below are written to preserve your own voice.',
                $kunyeAd, k_sayi($gOz['hedef_kelime']), k_sayi($gOz['en_az_kelime'])
              ) ?></p>
              <textarea id="mGenisOzet" name="makale_genis_ozet_en" rows="12"
                        placeholder="<?= k_esc(tg_cd('Soru · yöntem · bulgu · sonuç (%1).', 'Question · method · findings · conclusion (%1).', null, $kunyeAd)) ?>"></textarea>
              <p class="ipucu" id="mGenisSay" aria-live="polite"></p>
              <?php /* DÜĞME, DÜZ METİN DEĞİL — 14 Ağustos 2026 kurul
                       bildirimi: "bu hiç gözükmüyor, anlayamaz kimse."
                       Doğruydu: satır .ipucu sınıfındaydı, yani soluk
                       gri küçük yazı; içindeki <a> ile çevresindeki
                       cümle aynı boyda ve neredeyse aynı renkteydi.
                       Sayfanın en çok işe yarayan yardımı, en az
                       görünen ögesiydi.

                       Artık düğme ailesinin üyesi: zemini, çerçevesi ve
                       kırk piksellik dokunma alanı var. Yanındaki not
                       ayrı satıra indi; bir düğmenin yanına iliştirilen
                       cümle, düğmenin bir parçası sanılıyordu. */ ?>
              <p style="margin:var(--b-3) 0 0">
                <button type="button" class="d d-ikinci d-kucuk" id="cvAc"><?= k_c('Çeviri istemlerini açın', 'Open the translation prompts') ?></button>
              </p>
              <p class="ipucu"><?= k_c('Yapay zekâ kullanırsanız sekiz numaralı adımda beyan etmeniz gerekir.', 'If you use an AI you must declare it at step eight.') ?></p>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php /* Alan kodları. Editör hakem havuzunu bu kodlara göre süzer;
                 kod verilmeyen bir çalışmaya hakem bulmak ada ve başlığa
                 bakmakla kalır. En çok üç kod istenir: birincisi çalışmanın
                 asıl alanı, ötekiler değdiği alanlardır. */ ?>
        <label><?= k_c('Çalışmanın bilim alanı', 'The field of the work') ?>
          <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
        <p class="bv-ack"><?= k_c(
          'En az bir, en çok üç alan seçin. Hakem ararken editörler bu kodlara bakar. Kodların ilk iki basamağı uluslararası ölçüte (OECD Frascati) bağlıdır; üçüncü basamak bu sisteme aittir ve listede olmayan bir dalı önerebilirsiniz.',
          'Choose at least one and at most three. Editors look at these codes when seeking reviewers. The first two levels follow an international standard (OECD Frascati); the third level belongs to this system, and you may propose a branch that is missing.'
        ) ?></p>
        <?= as_kutu('alanlar[]', [], ['ensok' => 3]) ?>
      </fieldset>

      <?php /* =========================================================
               TAM METİN — GÖNDERİMİN İÇİNDE
               ---------------------------------------------------------
               Kurul kararı, 15 Ağustos 2026: "çalışma gönder kısmında
               hâlâ tam metnin, şekillerin ekleneceği yer yok; yazdığı
               metni şekillendirebilmesi lazım, onun için editör
               kurdurmuştur."

               Gerekçenin tamamı ortak.php'de, adım listesinin içinde
               yazılı; özeti: editör görmediği bir metni kabul ediyordu
               ve kabul edilen çalışma hakemsiz yayımlandığı için
               görülmemiş metin doğrudan yayına gidiyordu.

               ARAÇ ÇUBUĞU 'tam' TAKIMIDIR: yazma ekranındakiyle aynı.
               İki yerde iki ayrı takım olsaydı, gönderimde kullanılan
               bir düğme düzeltme ekranında bulunmazdı. Word yapıştırma
               ayrıştırıcısı ve yapı paneli de yalnız bu takımda çalışır.

               BETİKSİZ TARAYICIDA ALAN BİR <textarea>'DIR ve name'i
               vardır: düzenleyici bir kolaylıktır, tek yol değil. */ ?>
      <fieldset class="kart bv-kart sh-adim" id="f-metin" data-adim="metin">
        <legend class="gorsel-gizli"><?= $adimAd('metin') ?></legend>
        <h3><?= k_c('Tam metin', 'The full text') ?></h3>
        <p class="bv-ack"><?= k_c(
          'Çalışmanızın kendisi buraya girer. Word belgenizi açıp <b>tamamını kopyalayın</b> ve aşağıya yapıştırın: biçim çöpü atılır, yapı korunur (başlık düzeyleri, listeler, çizelgeler, dipnotlar, kalın ve eğik). İçindekiler yapıştırdığınız anda kendiliğinden oluşur ve metnin altında görünür.',
          'The work itself goes here. Open your Word document, <b>copy all of it</b> and paste it below: the formatting rubbish is discarded and the structure is kept (heading levels, lists, tables, footnotes, bold and italic). A table of contents grows by itself as you paste and appears beneath the text.'
        ) ?></p>
        <p class="kutu kutu-kut"><?= k_c(
          '<b>Şekil, çizelge ve grafik metnin içine konur.</b> Araç çubuğundaki görsel düğmesiyle ekleyebilir ya da doğrudan yapıştırabilirsiniz; yüklenen görsel sunucuda saklanır ve çalışmanızın sayfasında görünür. Word belgenizdeki görseller bilgisayarınızda duruyorsa yapıştırmayla gelmez; kaç tanesinin düştüğü size sayıyla bildirilir ve onları tek tek eklersiniz. Yüksek çözünürlüklü asılları ile veri ve kod, dokuzuncu adımda bir arşiv bağlantısıyla (Zenodo) verilir.',
          '<b>Figures, tables and charts go inside the text.</b> Add them with the image button on the toolbar or paste them directly; an uploaded image is stored on the server and appears on your work\'s page. Images in your Word document that live on your own computer do not come across when pasting; you are told how many were dropped and you add them one by one. The high resolution originals, together with data and code, are given as an archive link (Zenodo) at step nine.'
        ) ?></p>
        <label for="bvMetin"><?= k_c('Çalışmanın tam metni', 'The full text of the work') ?>
          <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
        <textarea id="bvMetin" name="makale_metin" class="buyuk"></textarea>
        <?= kd_yapi('bvMetin') ?>
        <label for="bvKaynakca"><?= k_c('Kaynakça', 'References') ?>
          <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
        <p class="ipucu"><?= k_c('Kaynakçayı metnin içinde bıraktıysanız buraya da yapıştırın: çalışmanın sayfasında ayrı bir bölüm olarak basılır ve içindekilerde kendi maddesiyle görünür.', 'If you left the reference list inside the text, paste it here as well: it is printed as its own section on the work\'s page and appears as its own entry in the contents.') ?></p>
        <?php /* Word şablonu düğmesi YAPI PANELİNDE zaten var (kd_yapi);
                 ikinci bir kopya, aynı işi iki düğmeye böler ve hangisinin
                 doğru olduğu sorusunu doğurur. */ ?>
        <textarea id="bvKaynakca" name="makale_kaynakca" rows="8"></textarea>
      </fieldset>

      <fieldset class="kart bv-kart sh-adim" id="f-basvuran" data-adim="basvuran">
        <legend class="gorsel-gizli"><?= $adimAd('basvuran') ?></legend>
        <h3><?= k_c('Başvuran (iletişim yazarı)', 'Applicant (corresponding author)') ?></h3>
        <p class="bv-ack"><?= k_c('Erişim bilgileri bu kişiye iletilir.', 'Access details are sent to this person.') ?></p>
        <?php /* GİRİŞLİ / GİRİŞSİZ AYRIMI BURADA BİR CÜMLEDİR, İKİ FORM
                 DEĞİL. Girişli olana alanların nereden geldiği söylenir;
                 olmayana hesabının olmasının ne kazandıracağı ve hesabı
                 olmadan da gönderebileceği söylenir. Söylenmezse kişi
                 dolu bir alanı "sistem beni tanıyor mu" diye düşünür ya
                 da boş bir formu "önce üye olmam mı gerek" diye kapatır.
                 İkisi de kaybedilmiş bir gönderimdir. */ ?>
        <?php if ($benGirisli && $benGelen): ?>
        <p class="ipucu"><?= k_c(
             'Bu alanlar hesabınızdan geldi. Yanlış ya da eksikse burada düzeltebilirsiniz; düzeltmeniz bu çalışma için geçerlidir. Hesabınızdaki bilgiyi kalıcı olarak değiştirmek isterseniz ',
             'These fields came from your account. If anything is wrong or missing you may correct it here; the correction applies to this work. To change the information in your account permanently, ') ?><a href="<?= k_esc(k_bag('/panel.php')) ?>#hesap"><?= k_c('hesap sayfanızı', 'open your account page') ?></a><?= k_c(' açın.', '.') ?></p>
        <?php elseif (!$benGirisli): ?>
        <p class="ipucu"><?= k_c('Hesabınız varsa ', 'If you have an account, ') ?><a href="<?= k_esc(k_bag('/panel.php')) ?>"><?= k_c('giriş yapın', 'sign in') ?></a><?= k_c(
             '; bu alanlar kendiliğinden dolar. Hesabınız yoksa da gönderebilirsiniz: çalışmanız alındığında adınıza bir hesap açılır ve erişim bağlantısı aşağıdaki adrese gider. Ayrıca bir kayıt adımı yoktur.',
             '; these fields then fill themselves. You may also submit without one: an account is opened in your name when the work arrives, and the access link is sent to the address below. There is no separate registration step.') ?></p>
        <?php endif; ?>
        <div class="alan-ikili">
          <div class="alan"><label for="unvan"><?= k_c('Unvan', 'Title') ?></label><?php /* Düz metin kutusu değil, seçim: aynı unvanın altı ayrı yazımını üretmemek için. Liste tek kaynaktan (tg_unvan_secenek) gelir. */ ?><select id="unvan" name="unvan"><?= tg_unvan_secenek(null, $benAlan['unvan']) ?></select></div>
          <div class="alan"><label for="ad"><?= k_c('Ad Soyad', 'Full name') ?></label><input type="text" id="ad" placeholder="<?= k_c('Adınız Soyadınız', 'Your full name') ?>" name="ad" value="<?= k_esc($benAlan['ad']) ?>"></div>
        </div>
        <label for="eposta"><?= k_c('E-posta', 'E mail') ?></label>
        <input type="email" id="eposta" placeholder="ornek@universite.edu.tr" autocomplete="email" name="eposta" value="<?= k_esc($benAlan['eposta']) ?>">
        <p class="ipucu"><?= k_c('Erişim bağlantısı bu adrese iletileceği için kurumsal ve erişebildiğiniz bir adres girin.', 'The access link is sent to this address, so use an institutional address you can reach.') ?></p>
        <label for="kurum"><?= k_c('Kurum', 'Institution') ?></label>
        <input type="text" id="kurum" placeholder="<?= k_c('Üniversite / Fakülte / Bölüm', 'University / Faculty / Department') ?>" name="kurum" value="<?= k_esc($benAlan['kurum']) ?>">
        <div class="alan-ikili">
          <div class="alan"><label for="orcid">ORCID <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label><input type="text" id="orcid" placeholder="0000-0000-0000-0000" name="orcid" value="<?= k_esc($benAlan['orcid']) ?>"></div>
          <?php /* SCOPUS VE KİŞİSEL WEB ALANI KALDIRILDI — kurul kararı,
                     15 Ağustos 2026: "Scopus Author ID isteğe bağlı,
                     web/kişisel akademik profil isteğe bağlı; bunlara
                     gerek yok, otomatik gelmeli."

                     İkisi de İSTEĞE BAĞLIYDI ve hiçbir kural onlara
                     dayanmıyordu; formda yer kaplayıp yazarı "bunu da
                     doldurmalı mıyım" diye duraklatıyorlardı. Kişinin
                     Scopus kimliği ve web adresi hesabında durur ve
                     profilinden düzenlenir — gönderim anında sorulacak
                     şeyler değil. Eski kayıtlardaki değerler yerinde
                     kalır; kalkan şey her gönderimde yeniden sorulması.
                     */ ?>
        </div>
        <?php /* SCOPUS İPUCU DA KALDIRILDI. Alan kalkarken açıklaması
                 kalmıştı: sistem, formda olmayan bir alanın nasıl
                 doldurulacağını anlatıyordu. Bu, bu oturumda on dört
                 kez görülen kusurun aynısıdır — "uygulamadığı bir kuralı
                 duyurmak". Scopus kimliği hesap sayfasında durur. */ ?>
        
        <div class="kutu kutu-kut kefil-alan gizli" data-kefil-alan="b"></div>
      </fieldset>
      <fieldset class="kart bv-kart sh-adim" id="f-ortak" data-adim="ortak">
        <legend class="gorsel-gizli"><?= $adimAd('ortak') ?></legend>
        <h3><?= k_c('Ortak yazarlar', 'Co authors') ?></h3>
        <p class="bv-ack"><?= tg_yazarlik_doktora_sarti()
             ? k_c('ORCID her yazar için zorunludur. Doktora derecesi henüz olmayan bir araştırmacı da yazar olarak yer alabilir: unvan alanı boş bırakıldığında iki ' . tg_destek_ad() . ' istenir.', 'An ORCID is required for each author. A researcher who does not yet hold a doctorate may also appear as an author: leave the title field empty and two ' . tg_destek_ad(true, true) . ' are asked for.')
             : k_c('ORCID her yazar için zorunludur. Unvan alanı isteğe bağlıdır ve boş bırakılabilir: yazarlık için doktora derecesi aranmaz.', 'An ORCID is required for each author. The title field is optional and may be left empty: no doctorate is required for authorship.') ?></p>
        <?php /* EKLENEN YAZARA NE OLACAĞI BURADA YAZILI OLMALI.
                 Kurul kararı, 15 Ağustos 2026: eklenen her yazara
                 yazarlığı bildirilir; sistemde kayıtlı değilse davet
                 bağlantısı gider. Bunu söylemeyen bir form, kişinin
                 haberi olmadan adres toplayan bir form olurdu — ve
                 gönderen de üçüncü bir kişinin adresini neden
                 istediğimizi bilmezdi. */ ?>
        <p class="ipucu"><?= k_c(
          'Eklediğiniz her yazara, bu çalışmada yazar olarak gösterildiğini söyleyen bir bildirim gider; e-postayı bunun için istiyoruz. Kişi sistemde kayıtlı değilse bildirime hesap kurma daveti de eklenir; hesap açmak zorunda değildir, açmasa da yazarlığı durur. ORCID&#8217;ini biliyorsanız <b>ORCID ile ara</b> düğmesiyle adını ve kurumunu getirebilirsiniz.',
          'Every author you add receives a notice saying they have been listed as an author of this work; that is why we ask for the e mail address. If the person is not registered here, an invitation to open an account is added to the notice; they are not obliged to open one, and their authorship stands either way. If you know their ORCID, the <b>Search by ORCID</b> button brings in their name and institution.') ?></p>
        <div id="yazarlar"></div>
        <button type="button" class="d d-ikinci d-kucuk" id="yazarEkle">+ <?= k_c('Ortak yazar ekle', 'Add a co author') ?></button>

        <?php /* =====================================================
                 "BÜTÜN YAZARLARI EKLEDİM" ONAYI
                 -----------------------------------------------------
                 ScholarOne'ın dördüncü adımında zorunlu bir soru olarak
                 duruyor (15 Ağustos 2026'da görüldü) ve Kutadgu'da
                 yoktu.

                 NİÇİN BİR KUTU DEĞİL, İKİ SEÇENEK: bir onay kutusu
                 işaretlenmeden geçilebilir ve işaretlenmemesi bir şey
                 söylemez. İki seçenek, kişiyi BİR CÜMLE KURMAYA
                 zorlar: ya "hepsi eklendi" ya "tek yazarım". İkisi de
                 kayda geçer.

                 NİÇİN ÖNEMLİ: bu sistem yazarlığı ciddiye alıyor —
                 eklenen her yazara bildirim gidiyor, adının
                 kullanıldığını bilmeyen yazarlık yazarlık sayılmıyor.
                 Eksik bırakılan bir yazar, o bildirimi hiç almaz.
                 Hayalet yazarlık tam olarak burada doğar.

                 TUTARLILIK AYRICA DENETLENİR: "tek yazarım" deyip
                 ortak yazar eklemiş olmak bir çelişkidir ve sayfa da uç
                 da onu söyler. */ ?>
        <div class="onay-dizi-2 bv-secim" style="margin-top:var(--b-5)">
          <label class="onay"><input type="radio" name="yazar_tam" value="hepsi"><span><?= k_c('Bütün yazarları ekledim', 'All authors have been added') ?><small><?= k_c('Yukarıdaki liste eksiksizdir; her birine yazarlığı bildirilecek.', 'The list above is complete; each of them will be told of their authorship.') ?></small></span></label>
          <label class="onay"><input type="radio" name="yazar_tam" value="tek"><span><?= k_c('Tek yazar benim', 'I am the only author') ?><small><?= k_c('Bu çalışmanın başka yazarı yok.', 'This work has no other authors.') ?></small></span></label>
        </div>
      </fieldset>
      <?php /* BENZERLİK ADIMI KOŞULA BAĞLANDI.
               13 Ağustos 2026 kurul kararı: rapor istenmez. Gerekçe
               ayar.php'de; özeti — sistem bu eşiği ölçemiyordu, yazarın
               yazdığı sayıyı denetlemeden kayda geçiriyordu ve rapor
               paralı olduğu için kapıyı paraya bağlıyordu.

               ADIM SİLİNMEDİ. Silinseydi raporu OLAN bir yazar onu
               ekleyemezdi; oysa eklediği rapor çalışmanın sayfasında
               görünür ve okura bir şey söyler. İstenmeyen bir şey ile
               kabul edilmeyen bir şey aynı değildir.

               Zorunluluk etiketleri de koşullu: "zorunlu" yazan bir
               etiketin altında boş bırakılabilen bir alan, sistemin
               kendi sözünü tutmaması olurdu. */ ?>
      <?php $bzSart = tg_benzerlik_sarti();
            $bzEt = $bzSart
              ? '<b class="zor">' . k_esc(k_c('zorunlu', 'required')) . '</b>'
              : '<b class="ist">' . k_esc(k_c('isteğe bağlı', 'optional')) . '</b>'; ?>
      <?php /* BENZERLİK ADIMI, ŞART KAPALIYKEN HİÇ BASILMAZ.
               Adım listesinden çıkarmak yetmez: bölme sayfada kalırsa
               şeritte adı olmayan bir adım durur, "Adım 5 / 10" sayısı
               tutmaz ve İleri düğmesi ona uğrar. Bölme koşula bağlandı;
               şart geri açılırsa hem adım hem bölme geri gelir. */ ?>
      <?php if (tg_benzerlik_sarti()): ?>
      <fieldset class="kart bv-kart sh-adim" id="f-benzerlik" data-adim="benzerlik">
        <legend class="gorsel-gizli"><?= $adimAd('benzerlik') ?></legend>
        <h3><?= k_c('Benzerlik (intihal) raporu', 'Similarity report') ?></h3>
        <p class="bv-ack"><?= k_esc(tg_benzerlik_metni(k_en())) ?></p>
        <div class="dizi dizi-3">
          <div class="alan"><label for="intArac"><?= k_c('Kullanılan araç', 'Tool used') ?> <?= $bzEt ?></label>
            <select id="intArac" name="intihal_arac">
              <option value=""><?= k_c('Seçiniz', 'Select') ?></option>
              <option>iThenticate</option><option>Turnitin</option><option>intihal.net</option>
              <option>Copyscape</option><option><?= k_c('Diğer', 'Other') ?></option>
            </select></div>
          <div class="alan"><label for="intOran"><?= k_c('Genel benzerlik (%)', 'Overall similarity (%)') ?> <?= $bzEt ?></label><input type="number" id="intOran" min="0" max="100" step="0.1" placeholder="11.4" name="intihal_oran"></div>
          <div class="alan"><label for="intTek"><?= k_c('Tek kaynaktan en yüksek (%)', 'Highest from one source (%)') ?> <?= $bzEt ?></label><input type="number" id="intTek" min="0" max="100" step="0.1" placeholder="3.2" name="intihal_tek_kaynak"></div>
        </div>
        <label for="intLink"><?= k_c('Rapor bağlantısı', 'Link to the report') ?> <?= $bzEt ?></label>
        <input type="text" id="intLink" placeholder="https://..." name="intihal_link">
        <p class="ipucu"><?= $bzSart
          ? k_c('Raporun PDF çıktısını erişime açık bir klasöre yükleyip bağlantısını buraya girin.', 'Upload the PDF of the report to an accessible folder and enter the link here.')
          : k_c('Raporunuz yoksa bu adımı boş geçin; hiçbir alan zorunlu değildir. Doldurursanız bildirdiğiniz oran çalışmanın sayfasında görünür.', 'If you have no report, leave this step empty; no field here is required. If you do fill it in, the ratio you declare appears on the work\'s page.') ?></p>
      </fieldset>
      <?php endif; ?>
      <fieldset class="kart bv-kart sh-adim" id="f-etik" data-adim="etik">
        <legend class="gorsel-gizli"><?= $adimAd('etik') ?></legend>
        <h3><?= k_c('Etik kurul izni', 'Ethics committee approval') ?></h3>
        <p class="bv-ack"><?= k_c(
          'İnsan ya da hayvan denekle yürütülen çalışmalar ile bilim otoritelerince izin alınması zorunlu tutulan deneysel tasarımlar için etik kurul onayı zorunludur. Gerekli olduğu hâlde sunulmayan çalışmalar yayımlanır ancak sayfalarında <b>askıya alınmıştır</b> uyarısı taşır.',
          'Ethics committee approval is required for studies involving human or animal subjects and for experimental designs for which scientific authorities require approval. Work for which it is required but not supplied is published, but carries a <b>suspended</b> notice on its page.'
        ) ?></p>
        <div class="onay-dizi-2 bv-secim">
          <label class="onay"><input type="radio" name="etik_durum" value="gerekli"><span><?= k_c('Gereklidir', 'Required') ?><small><?= k_c('Çalışma insan ya da hayvan denek içeriyor veya izne tabidir.', 'The study involves human or animal subjects, or is otherwise subject to approval.') ?></small></span></label>
          <label class="onay"><input type="radio" name="etik_durum" value="gereksiz"><span><?= k_c('Gerekmemektedir', 'Not required') ?><small><?= k_c('Kuramsal, kavramsal ya da ikincil veriye dayalı çalışma.', 'Theoretical, conceptual or based on secondary data.') ?></small></span></label>
        </div>
        <?php /* ---------- BİLDİRİLEN KUSUR VE VERİLEN KARAR ----------
                 "Etik kurul izni için 'belgeyi sonradan da
                 yükleyebilirsiniz' diyor ama nereye yükleyecek? Buna bir
                 yer vermek lazım. Ya da sadece etik kurulu veren kurum
                 adı, tarihi, numarası gibi metin olarak mı isteyelim —
                 bu sonuçta yazarı bağlar."

                 ÖLÇÜLDÜ: sistemde etik belgesi yükleyecek hiçbir uç
                 yoktu. Cümle, olmayan bir yeteneği duyuruyordu; bu
                 oturumda on beşinci kez aynı kusur.

                 KARAR: METİN BEYANI, DOSYA DEĞİL. Gerekçeler:

                 1. Yükleme yapılsa bile sistem belgeyi DOĞRULAYAMAZ.
                    Taranmış bir PDF'in gerçekliğini ancak veren kurul
                    bilir. "Doğrulandı" yazan bir rozet, doğrulanmamış
                    bir şeyi doğrulanmış göstermek olurdu.
                 2. Kurul adı + karar tarihi + karar numarası ÜÇLÜSÜ
                    DOĞRUDAN DENETLENEBİLİR: okur da editör de veren
                    kurula sorabilir. Dosya ekinden daha denetlenebilir
                    bir şeydir, çünkü sorulacak adres bellidir.
                 3. Etik kurul kararları KİŞİSEL VERİ taşır (katılımcı,
                    araştırmacı adları, bazen kurum içi yazışma). Onları
                    yayımlanan bir sistemde saklamak, korunması gereken
                    veriyi toplamaktır; toplanmayan veri sızdırılamaz.
                 4. Beyan yazarı bağlar ve çalışmayla birlikte
                    YAYIMLANIR. Yanlış beyan, geri çekme sebebidir.

                 Belgenin herkese açık bir adresi VARSA yine de girilir:
                 istenmeyen bir şey ile kabul edilmeyen bir şey aynı
                 değildir. Ama zorunlu olan şey üç metin alanıdır. */ ?>
        <div id="etikAlan">
          <label for="etikKurul"><?= k_c('İzni veren etik kurulun tam adı', 'Full name of the ethics committee that gave the approval') ?> <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
          <input type="text" id="etikKurul" placeholder="<?= k_c('Örn. Ankara Üniversitesi Sosyal Bilimler Etik Kurulu', 'e.g. Social Sciences Research Ethics Committee, University of X') ?>" name="etik_kurul">
          <div class="alan-ikili">
            <div class="alan"><label for="etikTarih"><?= k_c('Karar tarihi', 'Date of the decision') ?> <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label><input type="date" id="etikTarih" name="etik_tarih"></div>
            <div class="alan"><label for="etikNo"><?= k_c('Karar numarası', 'Decision number') ?> <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label><input type="text" id="etikNo" placeholder="<?= k_c('Örn. 2026-14', 'e.g. 2026-14') ?>" name="etik_no"></div>
          </div>
          <label for="etikBelge"><?= k_c('Kararın herkese açık bağlantısı', 'Public link to the decision') ?> <b class="ist"><?= k_c('isteğe bağlı', 'optional') ?></b></label>
          <input type="text" id="etikBelge" placeholder="https://..." name="etik_belge">
          <p class="ipucu"><?= k_c(
            'Belge <b>yüklenmez</b>: sistem taranmış bir belgeyi doğrulayamaz ve etik kurul kararları kişisel veri taşır. Yukarıdaki üç bilgi çalışmanızla birlikte yayımlanır; kurul adı, tarih ve numara verildiği için okur da editör de doğrudan veren kurula sorabilir. Beyan sizi bağlar; yanlış beyan geri çekme sebebidir.',
            'The document is <b>not uploaded</b>: the system cannot verify a scanned document, and ethics decisions carry personal data. The three details above are published with your work; because the committee, the date and the number are given, a reader or an editor can ask the issuing committee directly. The declaration binds you; a false declaration is grounds for retraction.') ?></p>
        </div>
      </fieldset>
      <fieldset class="kart bv-kart sh-adim" id="f-veri" data-adim="veri">
        <legend class="gorsel-gizli"><?= $adimAd('veri') ?></legend>
        <h3><?= k_c('Veri ve kod erişilebilirliği', 'Data and code availability') ?></h3>
        <p class="bv-ack"><?= k_c(
          'Bir sonucun doğruluğu, ancak başkası tarafından denetlenebildiği ölçüde bilimseldir. Veri ya da kod paylaşılamıyorsa bu bir kusur değildir; gizlenmesi kusurdur. Beyanınız çalışmayla birlikte yayımlanır.',
          'A finding is scientific only to the extent that someone else can check it. Being unable to share data or code is not a fault; concealing that is. Your statement is published with the work.'
        ) ?></p>
        <div class="onay-dizi-2 bv-secim">
          <label class="onay"><input type="radio" name="veri_beyan" value="acik"><span><?= k_c('Açık olarak paylaşıldı', 'Openly shared') ?><small><?= k_c('Veri ve varsa kod, erişime açık bir adreste duruyor.', 'The data and any code are held at a publicly accessible address.') ?></small></span></label>
          <label class="onay"><input type="radio" name="veri_beyan" value="istek"><span><?= k_c('İstek üzerine paylaşılır', 'Available on request') ?><small><?= k_c('Makul bir istek üzerine sorumlu yazar tarafından iletilir.', 'Provided by the corresponding author on reasonable request.') ?></small></span></label>
          <label class="onay"><input type="radio" name="veri_beyan" value="kisitli"><span><?= k_c('Paylaşılamıyor', 'Cannot be shared') ?><small><?= k_c('Yasal, ticari ya da kişisel veri kısıtı var; gerekçesi yazılır.', 'A legal, commercial or personal data restriction applies; the reason is stated.') ?></small></span></label>
          <label class="onay"><input type="radio" name="veri_beyan" value="yok"><span><?= k_c('Veri ya da kod üretmiyor', 'Produces no data or code') ?><small><?= k_c('Kuramsal ya da kavramsal çalışma.', 'A theoretical or conceptual work.') ?></small></span></label>
        </div>
        <div id="veriAlan">
          <label for="veriUrl"><?= k_c('Veri ya da kodun bağlantısı', 'Link to the data or code') ?></label>
          <?php /* YER TUTUCUYA DOI YAZILMAZ. doi-kapi haklı olarak yandı:
                 sayfalara elle yazılmış bir DOI numarası, bir gün
                 gerçek bir kaydı işaret eder ve o kayıt bizim değildir.
                 Biçim, numara verilmeden de anlatılır. */ ?>
          <input type="text" id="veriUrl" placeholder="https://doi.org/..." name="veri_url">
          <?php /* NEREYE sorusu burada da sorulur; yanıtı dokuzuncu
                   adımda vermek, kişiyi cevabı olmayan bir alanın
                   önünde bırakmaktı. Aynı cümlenin uzunu orada. */ ?>
          <p class="ipucu"><?= k_c(
            'Kutadgu dosya barındırmaz. Ücretsiz ve kalıcı bir akademik arşive yükleyip DOI bağlantısını buraya yapıştırın; en kolayı <a href="https://zenodo.org" target="_blank" rel="noopener">Zenodo</a>&#8217;dur (kurum gerekmez, DOI verir). Kod için GitHub da olur.',
            'Kutadgu does not host files. Upload to a free, permanent academic archive and paste the DOI link here; the easiest is <a href="https://zenodo.org" target="_blank" rel="noopener">Zenodo</a> (no institution needed, gives a DOI). GitHub is fine for code.') ?></p>
        </div>
        <div id="veriGerekceAlan">
          <label for="veriGerekce"><?= k_c('Paylaşılamama gerekçesi', 'Reason it cannot be shared') ?></label>
          <input type="text" id="veriGerekce" placeholder="<?= k_c('Örn. Katılımcı gizliliği nedeniyle ham veri paylaşılamıyor.', 'e.g. Raw data cannot be shared owing to participant confidentiality.') ?>" name="veri_gerekce">
        </div>
      </fieldset>
      <fieldset class="kart bv-kart sh-adim" id="f-yz" data-adim="yz">
        <legend class="gorsel-gizli"><?= $adimAd('yz') ?></legend>
        <h3><?= k_c('Beyanlar', 'Declarations') ?></h3>
        <p class="bv-ack"><?= k_c(
          'Bu adımdaki beyanların hepsi çalışmayla birlikte <b>yayımlanır</b>. Hiçbiri bir engel değildir; söylenmemesi kabul edilemez olan şey, söylenmiş olanın kendisi değildir.',
          'Every declaration on this step is <b>published</b> with the work. None of them is a barrier; what is unacceptable is not what you declare, but leaving it undeclared.'
        ) ?></p>

        <?php /* =====================================================
                 ÇIKAR ÇATIŞMASI
                 -----------------------------------------------------
                 Kutadgu'da HİÇ YOKTU. ScholarOne'ın beşinci adımında
                 zorunlu bir alan olarak duruyor (15 Ağustos 2026'da
                 kurucunun kendi gönderiminde görüldü) ve akademik
                 yayıncılıkta en standart beyandır.

                 NİÇİN BU SİSTEMDE AYRICA ÖNEMLİ: burada hakemlik
                 açıktır — hakemin adı, kararı ve raporu yayımlanır.
                 Okuyan kişi hakemin bağını görebiliyor ama yazarın
                 bağını göremiyordu. Açıklığın tek yönlü olması,
                 açıklık değildir.

                 "YOK" DA BİR BEYANDIR ve o da yayımlanır: boş bırakmak
                 ile "yoktur" demek aynı şey değildir. */ ?>
        <h4 class="bv-alt-bas"><?= k_c('Çıkar çatışması', 'Conflict of interest') ?></h4>
        <p class="bv-ack"><?= k_c(
          'Çalışmanın sonucundan çıkarı olan bir bağınız var mı? Fon veren kuruluşla ilişki, danışmanlık, ortaklık, patent, istihdam ya da çalışmanın konusu olan kurumla bağ. Bağ olması bir kusur değildir; bildirilmemesi kusurdur.',
          'Do you have any interest in the outcome of this work? A relationship with the funder, consultancy, partnership, a patent, employment, or a tie to the organisation the work is about. Having a tie is not a fault; not declaring it is.'
        ) ?></p>
        <div class="onay-dizi-2 bv-secim">
          <label class="onay"><input type="radio" name="cikar_catismasi" value="yok"><span><?= k_c('Yok', 'None') ?><small><?= k_c('Bildirilecek bir çıkar çatışması yok.', 'There is no conflict of interest to declare.') ?></small></span></label>
          <label class="onay"><input type="radio" name="cikar_catismasi" value="var"><span><?= k_c('Var', 'Yes') ?><small><?= k_c('Aşağıda açıklanır ve çalışmayla birlikte yayımlanır.', 'Described below and published with the work.') ?></small></span></label>
        </div>
        <div id="ccAlan">
          <label for="ccAciklama"><?= k_c('Çıkar çatışmasının açıklaması', 'Description of the conflict of interest') ?>
            <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
          <textarea id="ccAciklama" name="cikar_aciklama" rows="3" placeholder="<?= k_c('Örn. Çalışmanın verisini sağlayan kuruluşta danışmanım.', 'e.g. I am a consultant to the organisation that supplied the data.') ?>"></textarea>
        </div>

        <?php /* =====================================================
                 BAŞKA YERDE DEĞERLENDİRİLMİYOR
                 -----------------------------------------------------
                 Kutadgu'da yoktu. Aynı metnin iki yerde aynı anda
                 değerlendirilmesi, iki ayrı hakem takımının aynı işi
                 karşılıksız yapması demektir; bu sistemde hakemlik
                 gönüllü ve ücretsiz olduğu için maliyeti doğrudan
                 gönüllülerin üstüne biner. */ ?>
        <h4 class="bv-alt-bas"><?= k_c('Başka yerde değerlendirme', 'Assessment elsewhere') ?></h4>
        <label class="onay">
          <input type="checkbox" id="tekGonderim" name="tek_gonderim" value="1" required>
          <span><?= k_c(
            'Bu çalışma <b>başka bir yerde yayımlanmadı</b> ve şu anda başka bir yerde değerlendirilmiyor. Başvurum sürerken başka bir yere gönderirsem bunu bildireceğim.',
            'This work <b>has not been published elsewhere</b> and is not under assessment elsewhere. If I submit it elsewhere while this application is open, I will say so.'
          ) ?><small><?= k_c('Ön baskı (preprint) sunucusundaki bir sürüm buna aykırı değildir; ön baskı bir değerlendirme değildir.', 'A version on a preprint server does not contradict this; a preprint is not an assessment.') ?></small></span>
        </label>

        <?php /* =====================================================
                 FON
                 -----------------------------------------------------
                 Fon veren kuruluş ile proje numarası, çalışmanın
                 künyesinin parçasıdır: kim ödedi sorusu, sonucun nasıl
                 okunacağını değiştirir. İsteğe bağlı değil, "yok" da
                 dâhil beyan edilir. */ ?>
        <h4 class="bv-alt-bas"><?= k_c('Fon ve destek', 'Funding') ?></h4>
        <div class="onay-dizi-2 bv-secim">
          <label class="onay"><input type="radio" name="fon_durum" value="yok"><span><?= k_c('Fon almadı', 'No funding') ?><small><?= k_c('Bu çalışma için dışarıdan bir destek alınmadı.', 'No external support was received for this work.') ?></small></span></label>
          <label class="onay"><input type="radio" name="fon_durum" value="var"><span><?= k_c('Fon aldı', 'Funded') ?><small><?= k_c('Fon veren kuruluş ve proje numarası aşağıda yazılır.', 'The funder and the project number are written below.') ?></small></span></label>
        </div>
        <div id="fonAlan">
          <label for="fonKaynak"><?= k_c('Fon veren kuruluş ve proje numarası', 'Funder and project number') ?>
            <b class="zor"><?= k_c('zorunlu', 'required') ?></b></label>
          <input type="text" id="fonKaynak" name="fon_kaynak" placeholder="<?= k_c('Örn. TÜBİTAK 1001 · 123E456', 'e.g. TÜBİTAK 1001 · 123E456') ?>">
        </div>

        <?php /* =====================================================
                 EDİTÖRE NOT
                 -----------------------------------------------------
                 ScholarOne'da "cover letter". İsteğe bağlıdır ve
                 YAYIMLANMAZ: editöre söylenecek bir şey (çalışmanın
                 arka planı, bir hakem çekincesi, bir gecikme sebebi)
                 her zaman vardır ve gidecek başka bir yeri yoktu. */ ?>
        <h4 class="bv-alt-bas"><?= k_c('Editöre not', 'Note to the editor') ?>
          <small class="ist"><?= k_c('isteğe bağlı', 'optional') ?></small></h4>
        <p class="bv-ack"><?= k_c(
          'Editörün bilmesini istediğiniz bir şey varsa buraya yazın. Bu not <b>yayımlanmaz</b> ve hakemlere gösterilmez; yalnızca kararı verecek editör okur.',
          'If there is something you want the editor to know, write it here. This note is <b>not published</b> and is not shown to reviewers; only the deciding editor reads it.'
        ) ?></p>
        <textarea id="editorNotu" name="editor_notu" rows="4" placeholder="<?= k_c('İsteğe bağlı.', 'Optional.') ?>"></textarea>

        <h4 class="bv-alt-bas"><?= k_c('Yapay zekâ beyanı', 'Artificial intelligence declaration') ?></h4>
        <p class="bv-ack"><?= k_c('Kullanım yasak değildir; beyan edilmemesi kabul edilemez. Bu beyan çalışmayla birlikte yayımlanır.', 'Use is not prohibited; failing to declare it is not acceptable. This declaration is published with the work.') ?></p>
        <div class="onay-dizi-2 bv-secim">
          <label class="onay"><input type="radio" name="yz_kullanim" value="yok"><span><?= k_c('Kullanılmadı', 'Not used') ?><small><?= k_c('Hiçbir aşamada üretken yapay zekâ kullanılmadı.', 'No generative AI was used at any stage.') ?></small></span></label>
          <label class="onay"><input type="radio" name="yz_kullanim" value="duzenleme"><span><?= k_c('Dil ve düzenleme', 'Language and editing') ?><small><?= k_c('Yalnızca dil düzeltme, akıcılık, biçim düzenleme.', 'Language correction, fluency and formatting only.') ?></small></span></label>
          <label class="onay"><input type="radio" name="yz_kullanim" value="icerik"><span><?= k_c('Metin üretimi', 'Text generation') ?><small><?= k_c('Metnin bir bölümü yapay zekâ desteğiyle oluşturuldu.', 'Part of the text was produced with AI assistance.') ?></small></span></label>
          <label class="onay"><input type="radio" name="yz_kullanim" value="analiz"><span><?= k_c('Çözümleme desteği', 'Analysis support') ?><small><?= k_c('Veri işleme, kodlama ya da çözümlemede kullanıldı.', 'Used in data processing, coding or analysis.') ?></small></span></label>
        </div>
        <?php /* ---------- BİLDİRİLEN KUSUR ----------
                 "Yapay zekâ kullanılmadı dedim, geçmedi; burayı tıklamam
                 gerekti."

                 Ölçüldü, doğruydu ve iki katmanda birden yanlıştı:

                 1. SORULMAMASI GEREKEN ŞEY SORULUYORDU. Onay kutusunun
                    cümlesi "yapay zekâ KULLANIMININ ... ilkeler
                    çerçevesinde kaldığını beyan ederim" diyor.
                    Kullanmadığını söyleyen birinden, olmamış bir
                    kullanımın niteliğini beyan etmesi isteniyordu.
                 2. UÇ, UYGULAMADIĞI BİR KURALI DUYURUYORDU. Sunucu
                    'yz_etik_kabul' istiyordu ama sayfa bu alanı kutuya
                    bakmadan HER ZAMAN true gönderiyordu. Yani kutu
                    kullanıcıyı durduruyor, kuralı ise hiç korumuyordu.

                 Çözüm ikisini birden düzeltir: kullanım beyan
                 edilmediğinde kutu istenmez ve yerine ne beyan edilmiş
                 olduğunu söyleyen bir cümle geçer; beyan edildiğinde
                 kutu görünür ve GERÇEKTEN zorunludur — hem sayfada hem
                 uçta. Kutunun cümlesi de artık koşula göre değişmiyor,
                 çünkü değişen şey cümle değil, sorulup sorulmadığı. */ ?>
        <div id="yzKapsam">
          <label for="yzAciklama"><?= k_c('Kullanım kapsamının açıklaması', 'Description of the extent of use') ?></label>
          <textarea id="yzAciklama" placeholder="<?= k_c('Hangi araç, hangi aşamada, hangi kapsamda kullanıldı?', 'Which tool, at which stage, to what extent?') ?>" name="yz_aciklama"></textarea>
          <label class="onay">
            <input type="checkbox" id="yzEtik" name="yz_etik_kabul" value="1">
            <span><?= k_c('Yapay zekâ kullanımının <b><a href="https://proje.yok.gov.tr/tr/page/635" target="_blank" rel="noopener">YÖK Yapay Zekâ Kullanımına Dair Etik Rehber</a></b> ilkeleri çerçevesinde kaldığını; yapay zekânın yazar olarak gösterilmediğini ve bilimsel sorumluluğun tümüyle insan yazarlara ait olduğunu beyan ederim.', 'I declare that any use of artificial intelligence remained within the principles of the <b><a href="https://proje.yok.gov.tr/tr/page/635" target="_blank" rel="noopener">ethical guidance on the use of artificial intelligence</a></b>, that no AI is listed as an author, and that scientific responsibility rests entirely with the human authors.') ?></span>
          </label>
        </div>
        <?php /* Betiksiz tarayıcıda ikisi de görünür ve kutu boş
                 bırakılabilir; uç zaten kullanım beyan edilmediğinde
                 kutuyu aramıyor. Betik, gereksiz olanı gizler. */ ?>
        <p class="kutu kutu-kut" id="yzYokNot" hidden><?= k_c(
          'Yapay zekâ kullanılmadığını beyan ettiniz. Bu adımda başka bir şey gerekmiyor: olmamış bir kullanımın kapsamı yazılamaz, niteliği de beyan edilemez. Beyanınız çalışmayla birlikte yayımlanır ve bilimsel sorumluluk yazarlara aittir.',
          'You have declared that no artificial intelligence was used. Nothing further is needed at this step: the extent of a use that did not occur cannot be described, nor its nature declared. Your declaration is published with the work, and scientific responsibility rests with the authors.'
        ) ?></p>
        <?php /* BİLDİRİLEN KUSUR: "denetim istemlerini açın hiçbir
                 şekilde gözükmüyor, orada olduğu anlaşılmıyor."
                 Ölçüldü, doğruydu: bir cümlenin ortasındaki, sönük
                 renkli (.ipucu) bir bağlantıydı ve tıklanabilir bir şey
                 olduğu ancak üstüne gelinince anlaşılıyordu. Aynı iş
                 sayfanın başında DÜĞMEYLE yapılıyordu (#yzAc); iki ayrı
                 görünüm, aynı işlev. Artık ikisi de düğme. */ ?>
        <p class="ipucu"><?= k_c('Göndermeden önce çalışmanızı birden çok dil modelinde denetlemek isterseniz:', 'If you wish to check your work against several language models before submitting:') ?></p>
        <button type="button" class="d d-ikinci d-kucuk" id="yzAc2"><?= k_c('Yapay zekâ denetim istemlerini aç', 'Open the AI review prompts') ?></button>
      </fieldset>
      <fieldset class="kart bv-kart sh-adim" id="f-sekil" data-adim="sekil">
        <legend class="gorsel-gizli"><?= $adimAd('sekil') ?></legend>
        <h3><?= k_c('Şekiller, veri ve çözümleme', 'Figures, data and analysis') ?></h3>
        <p class="bv-ack"><?= k_c('Şekil ve tablolar ayrı ve yüksek çözünürlüklü sunulur; görgül çalışmalarda veri ve kod paylaşılır.', 'Figures and tables are supplied separately at high resolution; for empirical work the data and code are shared.') ?></p>
        <?php /* ---------- BİLDİRİLEN KUSUR ----------
                 "9. adımda şekiller diyoruz ama kişi bunu kendisi
                 ekleyemez; metin editöründe Word'den kopyaladı, buraya
                 tabloyu ya da şekli nasıl ekleyecek? Burası zayıf,
                 insanlar yapamayabilir."

                 ÖLÇÜLDÜ. Şekil ve tablo eklemek MÜMKÜN: yazma ekranında
                 doksan araç düğmeli tam bir düzenleyici var; tablo,
                 görsel, formül, hizalama hepsi orada. Eksik olan araç
                 değil, CÜMLEYDİ: bu adım yalnızca bir bağlantı istiyor
                 ve nereye ekleneceğini hiç söylemiyordu. Yazar da haklı
                 olarak "şekli buraya mı koyacağım" diye kalıyordu.

                 Bu adım kaynakları kaydeder; şekil ve tablo METNİN
                 İÇİNE, yazma ekranında konur. Sıra da bu yüzden
                 böyle: metin kabulden sonra yazılır, bu adım ise
                 başvuru anındadır. */ ?>
        <p class="kutu kutu-kut"><?= k_c(
          '<b>Şekli ve tabloyu bu adımda yüklemezsiniz.</b> Onların yeri metnin içidir ve metni yazma ekranında yazarsınız: orada Word\'deki gibi bir araç çubuğu vardır: tablo ekleme, görsel ekleme, başlık, madde, üst/alt simge, formül işaretleri ve geri alma. Word belgenizi olduğu gibi yükleyebilir ya da metni yapıştırabilirsiniz; biçim korunur. Bu adım yalnızca <b>kaynakları</b> kaydeder: yüksek çözünürlüklü dosyaların, veri setinin ve çözümleme kodunun nerede durduğunu. Hepsi isteğe bağlıdır.',
          '<b>You do not upload figures or tables at this step.</b> They belong inside the text, and you write the text on the writing screen: there you have a toolbar like the one in Word: insert table, insert image, headings, lists, superscript and subscript, symbols and undo. You may upload your Word file as it is, or paste the text; the formatting is kept. This step records only the <b>sources</b>: where the high resolution files, the data set and the analysis code live. All of it is optional.'
        ) ?></p>
        <?php /* ---------- SORULAN SORU VE VERİLEN KARAR ----------
                 "Veri ve kod ile şekiller nerede paylaşılacak? Hani şu
                 akademik verileri arşivleyen ücretsiz erişimli yerler
                 mi, yoksa biz mi? Onlara karar ver."

                 KARAR: KUTADGU DOSYA BARINDIRMAZ; ADRES GÖSTERİR.
                 Gerekçeler:

                 1. Barındırmak, taahhüt etmektir. Bir veri setini
                    yayımlayan sistem onu ON YIL saklamayı taahhüt eder;
                    saklayamayacağı bir şeyi taahhüt eden sistem, bir
                    gün ölü bağlantılar bırakır. Kutadgu'nun kalıcılık
                    taahhüdü METİN içindir.
                 2. Arşiv siteleri veriye DOI verir; DOI atıf alır,
                    sürümlenir ve alıntılanabilir. Bizim sunucumuzdaki
                    bir klasör bunların hiçbirini yapmaz.
                 3. Ücretsiz, kurumsuz ve kalıcı bir seçenek var:
                    ZENODO (CERN). Kayıt başına 50 GB, DOI, sürüm,
                    kapatılmış erişim seçeneği. Bir kişi kurumsuz da
                    kullanabilir; bu, kurumu olmayan araştırmacıyı
                    dışarıda bırakmamak demektir.

                 Önerilen tek yer değil, ÖNERİLEN İLK YER yazılır:
                 tekleştirmek, o kuruluşa bağımlılık üretir. */ ?>
        <p class="ipucu"><?= k_c(
          '<b>Nereye yükleyeceksiniz?</b> Kutadgu dosya barındırmaz; sakladığı şey metindir. Veri, kod ve yüksek çözünürlüklü şekiller için ücretsiz ve kalıcı bir akademik arşiv kullanın; en kolayı <a href="https://zenodo.org" target="_blank" rel="noopener">Zenodo</a>&#8217;dur (CERN işletir, kurum gerektirmez, kayıt başına 50 GB, her kayda kalıcı bir DOI verir). <a href="https://osf.io" target="_blank" rel="noopener">OSF</a>, <a href="https://datadryad.org" target="_blank" rel="noopener">Dryad</a>, <a href="https://figshare.com" target="_blank" rel="noopener">figshare</a> ya da kurumunuzun açık arşivi de olur. Kod için GitHub yeterlidir; kalıcı olsun isterseniz deponuzu Zenodo&#8217;ya bağlayıp sürüm DOI&#8217;si alabilirsiniz. Buraya <b>DOI bağlantısını</b> yapıştırmanız yeter.',
          '<b>Where do you upload?</b> Kutadgu does not host files; what it keeps is the text. Use a free, permanent academic archive for data, code and high resolution figures. The easiest is <a href="https://zenodo.org" target="_blank" rel="noopener">Zenodo</a> (run by CERN, no institution needed, 50 GB per record, a permanent DOI for every record). <a href="https://osf.io" target="_blank" rel="noopener">OSF</a>, <a href="https://datadryad.org" target="_blank" rel="noopener">Dryad</a>, <a href="https://figshare.com" target="_blank" rel="noopener">figshare</a> or your institution\'s open archive will do just as well. GitHub is enough for code; if you want it permanent, connect the repository to Zenodo and take a version DOI. Pasting the <b>DOI link</b> here is all that is needed.') ?></p>
        <label for="sekilLink"><?= k_c('Şekil ve tabloların bağlantısı', 'Link to figures and tables') ?></label>
        <input type="text" id="sekilLink" placeholder="https://..." name="sekil_link">
        <div class="alan-ikili">
          <div class="alan"><label for="analizArac"><?= k_c('Kullanılan çözümleme yazılımı', 'Analysis software used') ?></label><input type="text" id="analizArac" placeholder="R 4.4, EViews 13, MATLAB, Stata" name="analiz_arac"></div>
          <div class="alan"><label for="veriLink"><?= k_c('Veri seti bağlantısı', 'Link to the data set') ?></label><input type="text" id="veriLink" placeholder="https://..." name="veri_link"></div>
        </div>
        <label for="analizKod"><?= k_c('Çözümleme kodlarının bağlantısı', 'Link to the analysis code') ?></label>
        <input type="text" id="analizKod" placeholder="https://... (GitHub, OSF)" name="analiz_kod">
        <p class="ipucu"><?= k_c('Kuramsal ya da kavramsal çalışmalarda bu alanlar boş bırakılabilir.', 'These fields may be left empty for theoretical or conceptual work.') ?></p>
      </fieldset>
      <fieldset class="kart bv-kart sh-adim" id="f-hakem" data-adim="hakem">
        <legend class="gorsel-gizli"><?= $adimAd('hakem') ?></legend>
        <h3><?= k_c('Hakem önerisi (isteğe bağlı)', 'Suggested reviewers (optional)') ?></h3>
        <p class="bv-ack"><?= k_c('Tanıdığınız meslektaşlarınızı önerebilirsiniz. Her hakemin adı, kararı ve raporu yayımlandığı için tanışıklık kayırmacılığa dönüşemez. Nihai atama editörlüğe aittir.', 'You may suggest colleagues you know. Because every reviewer\'s name, decision and report is published, acquaintance cannot turn into favouritism. The final assignment rests with the editors.') ?></p>
        <div id="oneriler"></div>
        <button type="button" class="d d-ikinci d-kucuk" id="oneriEkle">+ <?= k_c('Hakem öner', 'Suggest a reviewer') ?></button>
      </fieldset>
      <fieldset class="kart bv-kart sh-adim" id="f-telif" data-adim="telif">
        <legend class="gorsel-gizli"><?= $adimAd('telif') ?></legend>
        <?php /* =====================================================
                 GÖZDEN GEÇİR VE GÖNDER
                 -----------------------------------------------------
                 ScholarOne'ın altıncı adımından alınan fikir (kurucunun
                 kendi gönderimi incelendi, 15 Ağustos 2026): göndermeden
                 önce bütün adımların özeti tek ekranda, her bölümün
                 yanında "Düzenle", en üstte eksik kalan her şeyin
                 listesi.

                 NİÇİN GEREKLİ: form on bir adım. Kutadgu ilk eksikte
                 durup yalnız onu söylüyordu; kişi eksiği düzeltip
                 ilerliyor, bir sonraki eksikte yine duruyordu. Uzun bir
                 formda bu, insanı adım adım geri gönderir. Şimdi hepsi
                 bir kerede görünüyor.

                 ÖZET FORMUN KENDİSİNDEN OKUNUR, AYRI BİR LİSTEDEN
                 DEĞİL. Alanların adını ikinci bir yere yazmak, formla
                 özetin zamanla ayrışması demekti: bir alan eklenir,
                 özete konmayı unutulur ve gönderen kişi onu gözden
                 geçiremez. Özet, adımların içindeki etiketleri ve
                 değerleri gezerek kurulur; yeni bir alan kendiliğinden
                 görünür.

                 BETİKSİZ TARAYICIDA HİÇ ÇİZİLMEZ ve çizilmemesi
                 doğrudur: orada bütün adımlar zaten açık ve tek uzun
                 form hâlinde ekrandadır, yani gözden geçirme sayfanın
                 kendisidir. */ ?>
        <h3><?= k_c('Gözden geçir ve gönder', 'Review and submit') ?></h3>
        <p class="bv-ack"><?= k_c(
          'Aşağıda gönderdiğiniz her şey duruyor. Değiştirmek istediğiniz bölümün yanındaki <b>Düzenle</b> düğmesi sizi o adıma götürür; döndüğünüzde yazdıklarınız yerinde olur.',
          'Everything you are about to send is shown below. The <b>Edit</b> button beside a section takes you to that step; what you have written is still there when you come back.'
        ) ?></p>
        <div id="shEksik" class="bv-eksik" hidden></div>
        <div id="shOzet" class="bv-ozet"></div>
        <?php /* Alt başlık BURAYA YAZILMAZ: lisans kartının kendi
                 <h3>'ü zaten "Telif ve lisans" diyor. İkisi birlikte
                 basılınca başlık iki kez göründü (ölçüldü, ekranda). */ ?>
        <?php /* BU SAYFANIN EN SONUÇLU CÜMLESİ.
                 Eskiden yazara "bütün telif haklarımı devrettiğimi beyan
                 ederim" imzalatılıyordu. Bu, sistemin İKİNCİ DEĞİŞMEZ
                 İLKESİNİ doğrudan çiğniyordu: "Bütün çalışmalar, telif
                 hakkı YAZARINDA KALMAK ÜZERE ... yayımlanır." Üstelik
                 her makale sayfasının altında "Eserin telif hakları
                 yazara aittir" yazıyordu; yani sistem yazara bir şey
                 imzalatıp okura bunun tersini söylüyordu.

                 Devir zaten gereksizdi. Devrin gerekçesi olarak "çalışma
                 kesintisiz açık erişimde kalsın" deniyordu, ama bunu
                 sağlayan şey mülkiyet değil LİSANSTIR: CC BY 4.0 geri
                 alınamaz. Bir çalışma bir kez o lisansla yayımlandığında
                 arşivde kalma güvencesi lisansın kendisinden gelir;
                 hakkın el değiştirmesine gerek yoktur.

                 Alan adı (telif_kabul) DEĞİŞMEDİ: eski kayıtlarda o
                 anahtar var ve veriyi geriye dönük değiştirmek dördüncü
                 ilkeye aykırı olurdu. Değişen, o kutunun ne anlama
                 geldiğidir ve anlamı burada yazılıdır. */ ?>
        <h3><?= k_c('Telif ve lisans', 'Copyright and licence') ?></h3>
        <label class="onay">
          <input type="checkbox" id="telif" name="telif_kabul" value="1">
          <span><?= k_c('Bu çalışmanın <b>telif hakkı bende (ve varsa ortak yazarlarımda) kalır</b>. ' . $marka . ' yayın sistemine, çalışmayı yayımlaması, kalıcı olarak arşivlemesi ve CC BY 4.0 lisansıyla dağıtması için <b>münhasır olmayan, geri alınamaz ve süresiz</b> bir izin veriyorum; bütün yazarların bu koşulu kabul ettiğini beyan ederim. Çalışmamı başka yerlerde de yayımlamayı sürdürebilirim.', 'The <b>copyright in this work remains with me</b> (and with my co authors, if any). I grant the ' . $marka . ' publishing system a <b>non exclusive, irrevocable and perpetual</b> permission to publish the work, to archive it permanently and to distribute it under the CC BY 4.0 licence, and I declare that all authors accept this condition. I may continue to publish my work elsewhere as well.') ?></span>
        </label>
        <?php /* MAKİNEYLE OKUNMA, İZNİN BİR PARÇASIDIR VE BURADA YAZILIR.
                 Yukarıdaki izin CC BY 4.0 ile dağıtımı kapsıyor ve o
                 lisans makineyle okumayı, eğitimde ve çıkarımda
                 kullanmayı zaten serbest bırakıyor. Yani bu bir ek
                 koşul değil, verilen iznin zaten içinde olan bir
                 sonuçtur. Ama SONUCUN KENDİSİ yazılı değildi: yazar
                 imzayı atarken çalışmasının bir dil modelinin eğitim
                 verisine girebileceğini kök dizindeki bir metin
                 dosyasından öğrenmek zorundaydı. Bir izin, sonucu
                 söylenmeden alınmaz.
                 Cümle burada yazılmıyor, tg_makine_kosulu()'ndan
                 geliyor; aynı cümle çalışma sayfasında, yapay zekâ
                 sayfasında ve llms.txt'te de basılıyor. */ ?>
        <p class="bv-makine"><?= k_esc(tg_makine_kosulu(k_en())) ?>
          <a href="<?= k_esc(k_bag('/yz.php#okuyan')) ?>" target="_blank" rel="noopener"><?= k_c('Bu ne demek', 'What this means') ?></a></p>
        <?php /* BEYAN BURADA, OKUMA BİRİNCİ ADIMDA.
                 Kutu önce birinci adımdaydı; okumayı atlayabilen bir
                 yazar beyanı da atlıyordu. İkisi ayrıldı: METİN her
                 zaman birinci adımda durur ve daha önce okunmuşsa
                 atlanır; BEYAN her başvuruda, gönderme düğmesinin
                 hemen üstünde yeniden verilir. Beyan hiçbir koşulda
                 kendiliğinden işaretlenmez. */ ?>
        <label class="onay">
          <input type="checkbox" id="kosulOk" name="kosullar_okundu" value="1" required>
          <span><?= k_c('<b>Gönderim koşullarını okudum</b>; bu çalışma o koşulları karşılıyor.',
                        '<b>I have read the submission conditions</b> and this work meets them.') ?>
            <small><a href="#f-kosul" data-adim-git="kosul"><?= k_c('Koşulları yeniden aç', 'Open the conditions again') ?></a></small></span>
        </label>
        <input type="hidden" name="kosul_surum" value="<?= k_esc($kosulSurum) ?>">
        <button class="d d-vurgu bv-gonder" id="gonder" type="submit"><?= k_c('Başvuruyu gönder', 'Send the application') ?></button>
        <div id="mesaj" class="form-msj"></div>
      </fieldset>
    </div>

      <?php /* İLERİ / GERİ. Betik yokken görünmezler (biri de formu
               göndermez): type="button" olduğu için betiksiz bir
               tarayıcıda hiçbir şey yapmazlar, bu yüzden gizli
               başlarlar ve betik onları açar. */ ?>
      <div class="sh-gez" id="shGez" hidden>
        <button type="button" class="d d-ikinci" id="shGeri"><?= k_c('&larr; Geri', '&larr; Back') ?></button>
        <span class="sh-say" id="shSay"></span>
        <button type="button" class="d d-ana" id="shIleri"><?= k_c('İleri', 'Next') ?></button>
      </div>
      <p class="sh-taslak" id="shTaslak" hidden></p>
    </form>


    <div id="bitti" class="kutu kutu-yes gizli">
      <h3 style="margin-top:0"><?= k_c('Başvurunuz alındı', 'Your application has been received') ?></h3>
      <p id="bittiMsj" class="metin-sonuk"></p>
      <p><a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Çalışmalara dön', 'Back to the works') ?></a></p>
    </div>
   </div>
   <?php /* YAN RAY DA AYNI LİSTEDEN ÜRETİLİR.
            Eskiden burada elle yazılmış ikinci bir liste vardı ve
            sırası form bölümlerinin sırasından farklıydı: ray
            "Başvuran, Ortak yazarlar, Çalışma" diyordu, form ise
            başka. Aynı bilgi iki yerde üretilince ayrışır (devir
            belgesi, tek kaynak kuralı). Artık ikisi de
            tg_basvuru_adimlari() okur; sıra bir yerde değişince
            ötekinde de değişir. */ ?>
   <?php /* SAĞ RAYDA ARTIK ADIM LİSTESİ YOK.
            Liste iki yerde birden duruyordu: formun üstündeki adım
            şeridi ve sağ ray. İkisi aynı sırayı aynı adlarla
            gösteriyordu; biri tıklanınca adım açılıyor, öteki yalnız
            kaydırıyordu. Aynı soruya iki farklı cevap veren arayüz,
            ikisinde de güveni kaybeder. Şerit kaldı (etkin olan o),
            ray yalnız notları taşıyor. */ ?>
   <?= k_belge_yan([], [
     ['tr' => 'Yarıda bırakabilirsiniz', 'en' => 'You may stop midway',
      /* Bu kutu bir SÖZ veriyor: "gönderilmeden sisteme yazılmaz."
         Taslak bu yüzden sunucuda değil, tarayıcınızda durur. Sunucuda
         bir taslak tutmak, henüz göndermediğiniz adınızı, e-postanızı
         ve ORCID'inizi toplamak olurdu; toplanmayan veri sızdırılamaz.
         Söz değişmedi, taslak sözü çiğnemeyen yere kondu. */
      'ic' => k_c('Form tek seferde doldurulmak zorunda değildir; hiçbir alan gönderilmeden sisteme yazılmaz. Yazdıklarınız yalnızca <b>kendi tarayıcınızda</b> saklanır ve geri döndüğünüzde kaldığınız yerden sürer; taslağı istediğiniz an silebilirsiniz. Eksik bıraktığınız bir alan varsa gönderirken hangisi olduğu söylenir.',
                  'The form does not have to be completed in one sitting; no field is written to the system before you send it. What you type is kept <b>in your own browser</b> only, and you resume where you left off; you can delete the draft whenever you wish. If a field is missing, you are told which one when you send.')],
     ['tr' => 'Önce okuyun', 'en' => 'Read first',
      'ic' => '<a href="' . k_esc(k_bag('/ilkeler.php')) . '">' . k_c('Yayın ilkeleri', 'Editorial policies') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/yz.php')) . '">' . k_c('Yapay zekâ kullanımı', 'Use of artificial intelligence') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/hakemlik.php')) . '">' . k_c('Hakemlik süreci', 'Peer review process') . '</a>'],
   ]) ?>
  </div>
</section>

<?php /* SÜREÇ ÖZETİ FORMUN ALTINDA.
         "Başvuru, Erişim, Hakemlik, Karar" — bu dört kart formu
         doldurmak için gereken bir bilgi değil, gönderdikten sonra ne
         olacağının özetidir. Formun üstünde dururken yazarı bekletiyordu;
         altında, gönderme düğmesinin hemen ardından, tam yerinde. */ ?>
<section class="bolum bolum-bitisik">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Bundan sonra', 'What follows') ?></span>
    <div class="dizi dizi-3 adimlar">
      <div class="kart adim"><span class="adim-no">1</span><h2><?= k_c('Başvuru', 'Application') ?></h2><p><?= k_c('Koşulları karşılayan çalışmanızı bu formla iletirsiniz. Ön inceleme yapılır.', 'You submit work meeting the conditions through this form. A desk check follows.') ?></p></div>
      <div class="kart adim"><span class="adim-no">2</span><h2><?= k_c('Erişim', 'Access') ?></h2><p><?= k_c('Kabul edilirse yalnızca bir çalışma için düzenleme erişimi açılır.', 'If accepted, editing access is opened for that one work only.') ?></p></div>
      <div class="kart adim"><span class="adim-no">3</span><h2><?= k_c('Hakemlik', 'Review') ?></h2><p><?= k_c('Hakem önerebilirsiniz. Her ad, karar ve rapor açıkça yayımlanır.', 'You may suggest reviewers. Every name, decision and report is published openly.') ?></p></div>
      <div class="kart adim"><span class="adim-no">4</span><h2><?= k_c('Karar', 'Decision') ?></h2><p><?= k_c($kabulS . ' olumlu rapor çalışmayı hakem onaylı yapar; ' . $retS . ' ret çalışmayı yazıya döndürür.', $kabulS . ' positive reports make the work reviewer approved; ' . $retS . ' rejections move it to the non reviewed track.') ?></p></div>
    </div>
  </div>
</section>


<!-- ================= ÇEVİRİ İSTEMLERİ ================= -->
<?php /* KURUL İSTEĞİ: "kendi ana dilindeki anlatımı, üslubu, kurguyu
         tamamen yansıtacak ve İngilizce ya da istenilen dilde çevirisini
         yapacak prompt önerileri verelim. Aynı zamanda yapay zekâ ile
         çeviri yaparken kaynakçaya dokunmamalı, yeni bilgi eklememeli
         veya çıkarmamalıdır."

         DÖRT İSTEM, DÖRT AYRI İŞ. Tek bir "çevir" istemi vermek kolaydı
         ama yanlış olurdu: çeviri, genişletme ve denetim ayrı işlerdir
         ve tek istemde birleştirilirse model üçünü de yarım yapar.

         HER İSTEMDE ÜÇ YASAK YAZILI ve üçü de kurulun koyduğu yasaktır:
           1. Kaynakçaya dokunma. Künye bir metin değil bir kayıttır;
              "düzeltilmiş" bir künye, artık o kaynağı göstermez.
           2. Bilgi ekleme. Modelin en sık ürettiği hata, olmayan bir
              bulguyu akıcı bir cümleyle yazmaktır.
           3. Bilgi çıkarma. Kısaltmak da bir çeviri kararıdır ve yazara
              aittir.

         DÖRDÜNCÜ İSTEM DENETLEYİCİDİR ve bilerek en sona kondu: çeviriyi
         yapan modele "doğru mu" diye sormak, kendi işini kendine
         onaylatmaktır. O istem BAŞKA bir modelde çalıştırılmak üzere
         yazıldı ve metninde de öyle yazıyor. */ ?>
<?php /* ÇEVİRİ İSTEMLERİ PENCERESİ ORTAK KAYNAKTAN GELİR (k/istem.php).
         Burada iki yüz satır metin duruyordu; aynı metnin yazar
         panelinde de gerekmesi onu ikinci kez yazmayı değil, ortak bir
         yere almayı gerektirdi. */ ?>
<?= k_istem_ceviri() ?>

<!-- ================= YAPAY ZEKÂ DENETİM İSTEMLERİ ================= -->
<div class="mod-ov" id="yzOv">
  <div class="mod">
    <button class="d d-sessiz d-im kapa" type="button" data-kapat="1">&#10005;</button>
    <h3 style="margin-top:0"><?= k_c('Yapay zekâ denetim istemleri', 'AI review prompts') ?></h3>
    <p class="mod-alt"><?= k_c(
      'Aşağıdaki istemleri <b>birden çok dil modelinde ayrı ayrı</b> çalıştırın. Tek bir modelin yargısı yeterli değildir; modeller aynı metin için farklı sonuçlar üretebilir. Amaç bir "yakalanma" denetimi değil, metnin <b>gerçekten sizin düşünsel katkınızı taşıyıp taşımadığını</b> görmektir. Çıktıları çalışmanızı güçlendirmek için kullanın.',
      'Run the prompts below <b>separately on several language models</b>. The judgement of a single model is not sufficient; models can produce different results for the same text. The aim is not a test of "being caught" but of seeing whether the text <b>really carries your own intellectual contribution</b>. Use the output to strengthen your work.'
    ) ?></p>

    <div class="pr">
      <div class="pr-bas"><div><b>1. <?= k_c('Kapsamlı köken çözümlemesi', 'Comprehensive provenance analysis') ?></b><small><?= k_c('Metnin bütününü yapay zekâ üretimi göstergeleri bakımından inceler', 'Examines the whole text for indicators of AI generation') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Sen, akademik metinlerde üretken yapay zekâ kullanımını değerlendiren kıdemli bir editörsün. Aşağıdaki metni tarafsız biçimde çözümle. Kesin hüküm verme; kanıta dayalı bir olasılık değerlendirmesi yap.

Şu boyutların her birini ayrı ayrı ele al ve her biri için metinden DOĞRUDAN ALINTI ver:

1. SÖZCÜK VE SÖZDİZİMİ: Aşırı düzenli cümle uzunluğu, tekdüze paragraf yapısı, "önemlidir", "dikkat çekicidir", "kritik bir rol oynamaktadır" gibi içeriği zayıf kalıpların sıklığı. Cümle uzunluklarının değişkenliğini değerlendir.
2. SAV YAPISI: Savlar gerçekten geliştiriliyor mu, yoksa yalnızca dengeli görünen genellemeler mi sıralanıyor?
3. KAYNAK KULLANIMI: Atıflar savın belirli bir adımını mı destekliyor, yoksa süs işlevi mi görüyor?
4. ALAN DERİNLİĞİ: Yalnızca o alanda çalışan birinin bilebileceği ayrıntılar, tartışmalı noktalar, yöntemsel kısıtlar var mı?
5. ÖZGÜN KATKI: Bu metnin literatüre eklediği, başka bir kaynaktan devşirilemeyecek düşünce nedir? Tek cümleyle söyle. Söyleyemiyorsan bunu açıkça belirt.
6. TUTARSIZLIK: Üslup, terim tercihi ya da biçim bakımından metnin bölümleri arasında kopukluk var mı?

Sonunda şunları ver:
- Her boyut için 0-10 arası bir puan ve gerekçesi
- Genel değerlendirme ve güven düzeyin
- Metnin insan katkısını güçlendirmek için somut 5 öneri

METİN:
"""
[çalışmanızın tam metnini buraya yapıştırın]
"""',
'You are a senior editor assessing the use of generative AI in academic texts. Analyse the text below impartially. Do not deliver a verdict; give an evidence based probability assessment.

Address each of the following dimensions separately and give a DIRECT QUOTATION from the text for each:

1. VOCABULARY AND SYNTAX: Overly regular sentence length, uniform paragraph structure, frequency of low content formulas such as "it is important", "it is noteworthy", "plays a critical role". Assess the variance in sentence length.
2. ARGUMENT STRUCTURE: Are arguments actually developed, or are balanced looking generalisations merely listed?
3. USE OF SOURCES: Do citations support a specific step of the argument, or do they serve as decoration?
4. DISCIPLINARY DEPTH: Are there details, contested points or methodological limits that only someone working in the field would know?
5. ORIGINAL CONTRIBUTION: What idea does this text add to the literature that could not be assembled from another source? Say it in one sentence. If you cannot, say so plainly.
6. INCONSISTENCY: Is there discontinuity between parts of the text in style, terminology or format?

Finish with:
- A score from 0 to 10 for each dimension, with grounds
- An overall assessment and your confidence level
- Five concrete suggestions for strengthening the human contribution

TEXT:
"""
[paste the full text of your work here]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>2. <?= k_c('Karşıt sınama', 'Adversarial test') ?></b><small><?= k_c('İlk çözümlemenin yanlış olabileceğini varsayarak sınar', 'Tests on the assumption that the first analysis may be wrong') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıdaki akademik metnin yapay zekâ tarafından üretildiği iddia edildi. Sen bu iddiaya KARŞI çıkan tarafsın: metnin insan yazımı olduğunu gösteren kanıtları ara.

Şunları özellikle incele:
- Kişisel yargı, tereddüt ya da sınırlılık itirafı içeren yerler
- Alana özgü, ezberden söylenemeyecek somut ayrıntılar
- Beklenmedik örnekler, alışılmadık kavram eşleştirmeleri
- Dilsel düzensizlikler, uzun ve kısa cümlelerin doğal dağılımı
- Yazarın kendi verisine ya da özgül gözlemine dayanan bölümler

Ardından dürüst ol: Bu kanıtlar iddiayı çürütmeye yetiyor mu? Yetmiyorsa hangi bölümler hâlâ şüpheli? Yalnızca metne dayan, iyimserlik yapma.

METİN:
"""
[metni yapıştırın]
"""',
'It has been claimed that the academic text below was produced by artificial intelligence. You are the party arguing AGAINST that claim: look for evidence that the text was written by a human.

Examine in particular:
- Passages containing personal judgement, hesitation or admission of limits
- Concrete field specific details that could not be recited from memory
- Unexpected examples and unusual pairings of concepts
- Linguistic irregularity, a natural distribution of long and short sentences
- Passages resting on the author\'s own data or particular observation

Then be honest: is this evidence enough to refute the claim? If not, which passages remain suspect? Rely only on the text; do not be charitable.

TEXT:
"""
[paste the text]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>3. <?= k_c('Bölüm bölüm haritalama', 'Paragraph by paragraph mapping') ?></b><small><?= k_c('Hangi paragrafın şüpheli olduğunu tek tek gösterir', 'Shows which paragraphs are suspect, one by one') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıdaki metni paragraf paragraf numaralandırarak incele. Her paragraf için tek satırda şunu ver:

[paragraf no] | [insan / karma / yapay zekâ olasılığı yüksek] | [0-100 güven] | [bu kararı veren en belirleyici tek gösterge]

Bittiğinde:
- En şüpheli 3 paragrafı sırala ve her biri için neyin eksik olduğunu söyle
- Şüpheli paragrafların oranını yüzde olarak ver
- Bu paragrafların nasıl güçlendirilebileceğini somut olarak yaz

METİN:
"""
[metni yapıştırın]
"""',
'Examine the text below paragraph by paragraph, numbering each. For every paragraph give one line:

[paragraph no] | [human / mixed / likely AI] | [confidence 0-100] | [the single most decisive indicator behind this call]

When finished:
- List the three most suspect paragraphs and say what is missing in each
- Give the proportion of suspect paragraphs as a percentage
- Set out concretely how those paragraphs could be strengthened

TEXT:
"""
[paste the text]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>4. <?= k_c('Bilgi doğrulama ve uydurma kaynak taraması', 'Fact checking and fabricated source scan') ?></b><small><?= k_c('Var olmayan atıf ve yanlış bilgi riskini denetler', 'Checks the risk of non existent citations and false information') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıdaki akademik metindeki her olgusal iddiayı ve her atfı denetle. Üretken yapay zekâ araçları var olmayan kaynak ve yanlış künye üretebildiği için bu denetim zorunludur.

Her kaynak için şunu belirt:
- Künye biçimsel olarak tutarlı mı? (yazar, yıl, dergi, cilt, sayfa uyumu)
- Bu çalışmanın gerçekten var olduğuna dair bilgin var mı? Emin değilsen "doğrulanamadı" de, uydurma.
- Metinde bu kaynağa atfedilen sav, kaynağın bilinen içeriğiyle bağdaşıyor mu?

Ayrıca:
- Sayısal iddiaları tek tek listele ve şüpheli olanları işaretle
- Kaynak gösterilmeden yapılmış, kaynak gerektiren iddiaları listele
- Aşırı genelleyici ve kanıtlanamaz ifadeleri işaretle

Sonunda doğrulanamayan kaynakların listesini ayrı bir başlıkta ver.

METİN VE KAYNAKÇA:
"""
[metni ve kaynakçayı yapıştırın]
"""',
'Check every factual claim and every citation in the academic text below. This check is essential because generative AI tools can produce non existent sources and incorrect bibliographic details.

For each source state:
- Is the reference internally consistent? (author, year, journal, volume, pages)
- Do you have knowledge that this work actually exists? If unsure, say "could not be verified"; do not invent.
- Does the claim attributed to this source match the source\'s known content?

Also:
- List numerical claims one by one and flag the suspect ones
- List claims made without a citation that require one
- Flag over general and unfalsifiable statements

Finish with a separate list of sources that could not be verified.

TEXT AND BIBLIOGRAPHY:
"""
[paste the text and bibliography]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>5. <?= k_c('Yazarlık sınaması', 'Authorship test') ?></b><small><?= k_c('Metnin düşünsel sahipliğini ölçer', 'Measures intellectual ownership of the text') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıdaki metni okuduktan sonra, yazarına sorulmak üzere 12 soru üret. Sorular öyle olsun ki, metni gerçekten kendisi düşünüp yazan biri rahatlıkla yanıtlasın; metni bir araca ürettirmiş biri ise zorlansın.

Sorular şunları yoklasın:
- Neden bu kuramsal çerçeve seçildi, hangi alternatif elendi ve niçin?
- Yöntemin bilinen kısıtları nelerdir, yazar bunlarla nasıl başa çıktı?
- Şu bulgu beklenen yönde değilse, yazarın buna dair açıklaması nedir?
- Kaynakçadaki şu çalışma ile metindeki şu sav arasındaki bağ tam olarak nedir?
- Bu çalışma yanlışlanacak olsa, hangi kanıt bunu sağlardı?

Soruları ürettikten sonra, metnin kendisinde bu soruların yanıtının bulunup bulunmadığını da değerlendir. Yanıtı metinde bulunmayan sorular, yazarın çalışmayı güçlendirmesi gereken noktalardır.

METİN:
"""
[metni yapıştırın]
"""',
'After reading the text below, produce twelve questions to put to its author. The questions should be such that someone who genuinely thought through and wrote the text can answer easily, while someone who had a tool produce it would struggle.

The questions should probe:
- Why was this theoretical framework chosen, which alternative was set aside and why?
- What are the known limits of the method, and how did the author deal with them?
- If a given finding runs against expectation, what is the author\'s explanation?
- What exactly is the link between a given work in the bibliography and a given claim in the text?
- If this study were to be falsified, what evidence would do it?

Having produced the questions, assess whether the text itself answers them. Questions with no answer in the text mark the points where the author should strengthen the work.

TEXT:
"""
[paste the text]
"""') ?></div>
    </div>

    <p class="mod-alt mod-alt-son">
      <b><?= k_c('Nasıl yorumlamalı:', 'How to read the results:') ?></b> <?= k_c(
        'Bu araçların hiçbiri kesin kanıt üretmez; dil modelleri insan yazımı metinleri yapay zekâ üretimi sanabildiği gibi tersini de yapabilir. Bu nedenle tek başına bir "yapay zekâ tespit skoru" ret gerekçesi sayılmaz. Ölçüt, çalışmanın <b>özgün düşünsel katkı taşıması</b> ve kullanımın <b>beyan edilmiş</b> olmasıdır.',
        'None of these tools produces conclusive proof; language models can mistake human writing for AI output and the reverse. An "AI detection score" alone is therefore never grounds for rejection here. The criterion is that the work <b>carries an original intellectual contribution</b> and that any use is <b>declared</b>.'
      ) ?>
    </p>
  </div>
</div>

<?php
$en = k_en();
$S = json_encode([
  'kopyalandi' => k_c('Kopyalandı', 'Copied'),
  'kopyala'    => k_c('Kopyala', 'Copy'),
  'yazarNo'    => k_c('. yazar', '. author'),
  'hakemNo'    => k_c('. önerilen hakem', '. suggested reviewer'),
  /* ETİKET KURALA BAĞLIDIR. "En az Dr." yazan bir etiket, doktora
     şartı kapalıyken uygulanmayan bir kuralı duyurur; bu oturumda on
     beş kez görülen kusurun aynısı. Şart tek kaynaktan sorulur. */
  'unvanEt'    => tg_yazarlik_doktora_sarti()
                    ? k_c('Unvan (en az Dr.)', 'Title (at least Dr.)')
                    : k_c('Unvan (isteğe bağlı)', 'Title (optional)'),
  /* Seçenek listesi betiğe HAZIR MARKUP olarak geçer. Liste iki yerde
     ayrı ayrı kurulsaydı (biri PHP'de biri JS'te), biri güncellenip
     öteki unutulurdu; sihirbazın birinci satırıyla ikinci yazar
     satırı farklı unvanlar gösterirdi. */
  'unvanSec'   => tg_unvan_secenek(),
  'adEt'       => k_c('Ad Soyad', 'Full name'),
  'kurumEt'    => k_c('Kurum', 'Institution'),
  'zorunlu'    => $en ? 'required' : 'zorunlu',
  'istege'     => k_c('isteğe bağlı', 'optional'),
  'kaldir'     => k_c('Kaldır', 'Remove'),
  'adYer'      => k_c('Ortak yazar', 'Co author'),
  /* Ortak yazar satırının yeni parçaları (kurul kararı, 15 Ağustos
     2026): ORCID ile arama ve e-posta. Metinler tek yerden gelir. */
  'yEposta'    => k_c('E-posta', 'E mail'),
  /* Örnek adres ÇEVRİLMEZ: başvuranın kendi e-posta alanındaki
     yer tutucu da tek bir dizedir ("ornek@universite.edu.tr").
     İkinci bir dil için ikinci bir örnek adres uydurmak, gerçek gibi
     görünen yeni bir adres üretmekten başka bir şey değildi ve
     sır-kapı bunu haklı olarak kişisel veri saydı. */
  'yEpostaYer' => 'ornek@universite.edu.tr',
  'yAra'       => k_c('ORCID ile ara', 'Search by ORCID'),
  'yAranıyor'  => k_c('Aranıyor…', 'Searching…'),
  'yBulundu'   => k_c('Sistemde kayıtlı: bilgileri dolduruldu.', 'Registered in the system: the details were filled in.'),
  'yBulunmadi' => k_c('Bu ORCID sistemde kayıtlı değil. Bilgileri elle yazın; gönderdikten sonra bu kişiye davet gider.', 'This ORCID is not registered here. Enter the details by hand; an invitation is sent to this person after you submit.'),
  'yAraOrcid'  => k_c('Aramak için önce geçerli bir ORCID yazın.', 'Enter a valid ORCID first in order to search.'),
  'yAraHata'   => k_c('Arama yapılamadı; bilgileri elle yazabilirsiniz.', 'The search could not be made; you may enter the details by hand.'),
  'eYEposta'   => k_c(' için geçerli bir e-posta gerekir: yazarlığı ona bildirebilmemiz için.', ' needs a valid e mail address, so that we can tell them of their authorship.'),
  'hAd'        => k_c('Ad Soyad (unvanıyla)', 'Full name with title'),
  'hEposta'    => k_c('E-posta', 'E mail'),
  'hAlan'      => k_c('Uzmanlık alanı', 'Field of expertise'),
  'eAd'        => k_c('Adınızı girin.', 'Enter your name.'),
  'eUnvan'     => k_c('Unvanınız en az "Dr." olmalıdır.', 'Your title must be at least "Dr."'),
  'ePosta'     => k_c('Geçerli bir e-posta girin.', 'Enter a valid e mail address.'),
  'eOrcid'     => k_c('ORCID zorunludur ve 0000-0000-0000-0000 biçiminde geçerli olmalıdır.', 'ORCID is required and must be valid in the form 0000-0000-0000-0000.'),
  'eBaslik'    => k_c('Çalışmanın başlığını girin.', 'Enter the title of the work.'),
  /* TAM METİN ADIMI. Eşik bir sayıdır ve tek yerde yazılır: aşağıdaki
     METIN_AZ ile bu cümledeki sayı aynı kaynaktan gelir. */
  'eMetin'     => k_c('Çalışmanın tam metnini girin: en az %1 kelime. Word belgenizi açıp tamamını buraya yapıştırabilirsiniz.', 'Enter the full text of the work: at least %1 words. You can open your Word document and paste all of it here.'),
  'eKaynakca'  => k_c('Kaynakçayı girin. Kaynağı olmayan bir çalışma yayımlanmaz; kaynakça metnin içindeyse oradan kopyalayıp buraya da yapıştırın.', 'Enter the reference list. A work without sources is not published; if the list is inside the text, copy it from there and paste it here as well.'),
  'eGorselBek' => k_c('Görseller hâlâ yükleniyor. Yükleme bitince gönderebilirsiniz.', 'The images are still uploading. You can send once the upload has finished.'),
  /* Gözden geçir ve gönder adımı. */
  'ozDuzenle'  => k_c('Düzenle', 'Edit'),
  'ozBos'      => k_c('boş bırakıldı', 'left empty'),
  'ozKelime'   => k_c('%1 kelime', '%1 words'),
  'ozEksikBas' => k_c('Göndermeden önce tamamlanması gerekenler', 'To be completed before sending'),
  'ozTamam'    => k_c('Her adım tamam. Aşağıdaki iki beyanı verip gönderebilirsiniz.', 'Every step is complete. Give the two declarations below and you can send.'),
  'ozGorsel'   => k_c('%1 şekil', '%1 figure(s)'),
  'ozAlanAd'   => k_c('Bilim alanı', 'Field of the work'),
  'ozBeyan'    => k_c('Beyanınız', 'Your declaration'),
  'eCikar'     => k_c('Çıkar çatışması olup olmadığını belirtin. "Yok" da bir beyandır ve o da yayımlanır.', 'State whether there is a conflict of interest. "None" is also a declaration, and it too is published.'),
  'eCikarAck'  => k_c('Çıkar çatışmasını açıklayın: neyin, kiminle ve nasıl bir bağı var.', 'Describe the conflict of interest: what the tie is, with whom, and of what kind.'),
  'eTekGon'    => k_c('Çalışmanın başka bir yerde değerlendirilmediğini beyan etmelisiniz.', 'You must declare that the work is not under assessment elsewhere.'),
  'eFon'       => k_c('Fon alınıp alınmadığını belirtin.', 'State whether funding was received.'),
  'eFonKaynak' => k_c('Fon veren kuruluşu ve proje numarasını yazın.', 'Write the funder and the project number.'),
  'eYazarTam'  => k_c('Bütün yazarları ekleyip eklemediğinizi belirtin. Eksik bırakılan bir yazar, yazarlığından hiç haberdar olmaz.', 'State whether all authors have been added. An author left out never learns of their authorship.'),
  'eYazarTek'  => k_c('"Tek yazar benim" dediniz ama ortak yazar eklediniz. İkisinden biri doğru olabilir.', 'You said you are the only author but you have added co authors. Only one of the two can be true.'),
  /* Künye dili zorunlu kılındığında kullanılır; bugün kılınmamıştır
     ama dize şimdiden yazılıdır, çünkü kural bir gün açıldığında
     eksik bir uyarı metni sessizce boş bir ileti gösterirdi. */
  'eKunyeBaslik' => tg_cd('Başlığın karşılığını girin (%1).', 'Enter the title (%1).', null, tg_kunye_dil_adi()),
  'eKunyeOzet'   => tg_cd('Özetin karşılığını girin (%1).', 'Enter the abstract (%1).', null, tg_kunye_dil_adi()),
  /* Sayılar cümlenin İÇİNE konmaz; %1 ile yerine geçer. Eşik ayardan
     gelir ve bir gün değişirse cümle bütün sözlüklerden düşmesin. */
  'eGenis'   => tg_cd('Genişletilmiş özet en az %1 kelime olmalı.', 'The extended abstract must be at least %1 words.', null, '%1'),
  'gozBos'   => tg_cd('Henüz yazılmadı. Hedef %1 kelime.', 'Nothing written yet. Target %1 words.', null, tg_genis_ozet_ayar()['hedef_kelime']),
  'gozAz'    => k_c('%1 kelime · en az %2 gerekiyor.', '%1 words · at least %2 needed.'),
  'gozTamam' => k_c('%1 kelime · yeterli (hedef %2).', '%1 words · enough (target %2).'),
  'eAlan'      => k_c('Çalışmanın bilim alanını seçin (en az bir tane).', 'Choose at least one field for the work.'),
  'eYUnvan'    => k_c(' için unvan en az "Dr." olmalıdır.', ' must hold at least a "Dr." title.'),
  'kfBas'      => k_c('İki ' . tg_destek_ad() . ' gerekir', 'Two ' . tg_destek_ad(true, true) . ' are required'),
  'kfAck'      => k_c('Bu yazarın doktora unvanı yok. İki doktoralı araştırmacı adıyla sorumluluk üstlenirse yazar olarak yer alabilir. Her ikisine bağlantı gönderilir ve gerekçesiyle onaylar; onaylar gelmeden çalışma yayına alınmaz.', 'This author does not hold a doctorate. They may appear as an author when two researchers holding doctorates take responsibility under their own names. Each is sent a link and confirms with their reasoning; the work is not published until both confirmations arrive.'),
  'kfOrtakB'   => k_c(tg_destek_ad_bas() . ' 1 · bu çalışmanın ortak yazarı', tg_destek_ad_bas(true) . ' 1 · a co author of this work'),
  'kfOrtakA'   => k_c('Yukarıdaki yazarlardan doktoralı biri olmalıdır. Metni bilen kişidir.', 'Must be one of the authors above who holds a doctorate. Someone who knows the text.'),
  'kfBagB'     => k_c(tg_destek_ad_bas() . ' 2 · bağımsız', tg_destek_ad_bas(true) . ' 2 · independent'),
  'kfBagA'     => k_c('Doktoralı olmalı ve bu çalışmanın yazarlarından biri olmamalıdır. Dışarıdan bir gözdür.', 'Must hold a doctorate and must not be an author of this work. An outside eye.'),
  'kfEposta'   => k_c('E-posta (onay bağlantısı buraya gider)', 'E mail (the confirmation link goes here)'),
  'eKfUnvan'   => k_c(tg_destek_ad_bas(false, true) . 'ın her biri en az "Dr." unvanına sahip olmalıdır.', 'Every ' . tg_destek_ad(true) . ' must hold at least a "Dr." title.'),
  'eKfAd'      => k_c(tg_destek_ad_bas(false, true) . 'ın adını ve soyadını yazın.', 'Write the full name of every ' . tg_destek_ad(true) . '.'),
  'eKfOrcid'   => k_c(tg_destek_ad_bas(false, true) . 'ın her biri için geçerli bir ORCID gerekir.', 'A valid ORCID is required for every ' . tg_destek_ad(true) . '.'),
  'eKfEposta'  => k_c(tg_destek_ad_bas(false, true) . 'ın her biri için geçerli bir e-posta adresi gerekir.', 'A valid e mail address is required for every ' . tg_destek_ad(true) . '.'),
  'eKfTek'     => k_c('Unvanı olmayan bir araştırmacı tek başına çalışma gönderemez: çalışmada en az bir doktoralı ortak yazar bulunmalıdır.', 'A researcher without a doctorate cannot submit alone: the work needs at least one co author holding a doctorate.'),
  'eYOrcid'    => k_c(' için geçerli bir ORCID zorunludur.', ' needs a valid ORCID.'),
  'eArac'      => k_c('Benzerlik raporunun hangi araçla alındığını seçin.', 'Select which tool produced the similarity report.'),
  'eOran'      => k_c('Genel benzerlik oranını girin.', 'Enter the overall similarity ratio.'),
  'eOranUst'   => $en ? 'The overall similarity ratio can be at most ' . $benz . '%. You entered: ' : 'Genel benzerlik oranı en çok %' . $benz . ' olabilir. Girdiğiniz oran: %',
  'eTek'       => k_c('Tek kaynaktan gelen en yüksek benzerlik oranını girin.', 'Enter the highest similarity coming from a single source.'),
  'eTekUst'    => $en ? 'Similarity cannot all come from one source; at most ' . $benzTek . '% from a single source. You entered: ' : 'Benzerliğin tamamı tek kaynaktan olamaz; tek kaynak payı en çok %' . $benzTek . ' olabilir. Girdiğiniz: %',
  'eTekBuyuk'  => k_c('Tek kaynak payı, genel benzerlik oranından büyük olamaz.', 'The single source share cannot exceed the overall similarity.'),
  'eLink'      => k_c('Benzerlik raporunun erişilebilir bağlantısını girin (https:// ile).', 'Enter an accessible link to the similarity report (starting with https://).'),
  'eYz'        => k_c('Yapay zekâ kullanımına ilişkin beyanı seçin.', 'Select a declaration about the use of artificial intelligence.'),
  'eYzAcik'    => k_c('Yapay zekâ kullandıysanız kapsamını en az bir cümleyle açıklayın.', 'If you used AI, describe the extent in at least one sentence.'),
  'eYzEtik'    => k_c('Etik rehbere uygunluk beyanını onaylamalısınız.', 'You must confirm the declaration of ethical compliance.'),
  'eEtik'      => k_c('Etik kurul izninin gerekli olup olmadığını belirtin.', 'State whether ethics committee approval is required.'),
  'eEtikKurul' => k_c('İzni veren etik kurulun tam adını yazın.', 'Enter the full name of the ethics committee that gave the approval.'),
  'eEtikTarih' => k_c('Etik kurul kararının tarihini girin.', 'Enter the date of the ethics committee decision.'),
  'eEtikNo'    => k_c('Etik kurul kararının numarasını girin.', 'Enter the number of the ethics committee decision.'),
  'eTelif'     => k_c('Yayımlama ve arşivleme iznini vermelisiniz.', 'You must grant the licence to publish and archive.'),
  'eKosul'     => k_c('Gönderim koşullarını okuyup kutuyu işaretleyin.', 'Please read the submission conditions and tick the box.'),
  'eVeri'      => k_c('Veri ve kodun erişilebilirliğini belirtin.', 'State whether the data and code are available.'),
  'eVeriUrl'   => k_c('Veri ya da kodun bulunduğu adresi girin.', 'Enter the address where the data or code is held.'),
  'eVeriGerekce' => k_c('Verinin neden paylaşılamadığını yazın.', 'Write the reason the data cannot be shared.'),
  /* Sihirbaz dizgeleri. %1 şimdiki adım, %2 toplam, %3 adımın adı. */
  'shSay'      => k_c('Adım %1 / %2', 'Step %1 of %2'),
  'shDurum'    => k_c('%2 adımdan %1. adımdasınız: %3', 'Step %1 of %2: %3'),
  'shTaslakVar' => k_c('Bu tarayıcıda yarım kalmış bir başvurunuz duruyor.', 'A half filled application is kept in this browser.'),
  'shTaslakSur' => k_c('Kaldığım yerden sür', 'Resume it'),
  'shTaslakSil' => k_c('Taslağı sil', 'Delete the draft'),
  'shTaslakSurdu' => k_c('Taslak geri yüklendi.', 'The draft has been restored.'),
  'shTaslakSildi' => k_c('Taslak bu tarayıcıdan silindi.', 'The draft has been deleted from this browser.'),
  'gonderiliyor' => k_c('Gönderiliyor...', 'Sending...'),
  'alindi'     => k_c('Başvurunuz alındı.', 'Your application has been received.'),
  'gonderilemedi' => k_c('Gönderilemedi.', 'Could not be sent.'),
  'baglanti'   => k_c('Bağlantı hatası, tekrar deneyin.', 'Connection error, please try again.'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

/* HEREDOC İÇİNDE <?= ?> ÇALIŞMAZ; değer önce değişkene yazılır.
   Bu tuzağa bu depoda daha önce de düşüldü (bkz. panel.php). */
$kunyeZorJs = tg_kunye_zorunlu() ? 'true' : 'false';
$gOzAyar    = tg_genis_ozet_ayar();
$genisZorJs = $gOzAyar['zorunlu'] ? 'true' : 'false';
/* Ortak yazar aramasının ucu. Sayfada elle yazılmaz: k_bag() dil ekini
   ve alt dizini bilir. */
$araUcJs = json_encode(k_bag('/api/yazar-ara'));
/* Tam metnin en az kelime sayısı. Ayardan gelir: eşiği değiştirmek
   isteyen tek bir yeri değiştirir ve sayfa, uç ve kılavuz birlikte
   değişir. */
$metinAz   = (int)tg_ayar('metin_en_az_kelime', 800);
$metinAzJs = (int)$metinAz;
$genisAzJs  = (int)$gOzAyar['en_az_kelime'];
$genisHedefJs = (int)$gOzAyar['hedef_kelime'];

$betik = <<<JS
<script>
(function(){
  var S = {$S};
  var KUNYE_ZOR = {$kunyeZorJs};
  var GENIS_ZOR = {$genisZorJs}, GENIS_AZ = {$genisAzJs}, GENIS_HEDEF = {$genisHedefJs};
  var BENZ = {$benz}, BENZ_TEK = {$benzTek}, BENZ_SART = {$benzSartJs};
  /* DOKTORA ŞARTI. Sunucu ile aynı kaynaktan gelir (ayar.php ->
     tg_yazarlik_doktora_sarti). Elle true/false yazılmaz: tarayıcı bir
     kuralı, sunucu başka bir kuralı uygularsa kullanıcı hiçbir yerde
     yazmayan bir hatayla karşılaşır. */
  var DOKTORA_SARTI = {$doktoraSartiJs};
  /* Uç adresi PHP'den gelir (k_bag): dil eki ve alt dizin buradadır.
     Elle '/api/...' yazmak, alt dizine kurulmuş bir kopyada 404 verirdi. */
  var ARA_UC = {$araUcJs};
  /* TAM METİN EN AZ KELİME. Uçtaki eşikle aynı kaynaktan gelir
     (tg_ayar 'metin_en_az_kelime'); sayfa kendi eşiğini koymaz, yoksa
     tarayıcı geçirir sunucu reddeder ve kişi nedenini anlamaz. */
  var METIN_AZ = {$metinAzJs};
  function \$(id){return document.getElementById(id);}
  /* OLMAYAN ALANIN DEĞERİ: BOŞ DİZGİ, ÇÖKME DEĞİL.
     15 Ağustos 2026 kurul kararlarıyla Scopus, kişisel web ve benzerlik
     alanları formdan kaldırıldı. Gövde derleyicisi bu alanları hâlâ
     \$('x').value ile okuyordu; ilki null döndüğü an gönder düğmesi
     sessizce çöküyor, kişi hiçbir uyarı görmeden gönderemiyordu
     (tarayıcı ölçümü bunu "gönderim tamamlanmadı" diye yakaladı).
     dg() alanı yoksa boş dizgi verir: kaldırılmış alan, boş alandır. */
  function dg(id){var e=\$(id);return e?e.value:'';}

  /* ---- YAPILANDIRILMIŞ ÖZ ----
     Kutular AÇIK doğar (sunucuda basılır); kapatan betiktir. Betiksiz
     tarayıcıda dördü de görünür ve boş bırakılabilir — gizli doğan bir
     alan, betiği olmayana doldurulamaz bir alan demekti.

     Yazılan bölümler özeti CANLI kurar ve özet kutusu bu sırada salt
     okunur olur: iki yerde birbirinden habersiz yazılabilen tek bir
     olgu, er geç ikiye ayrılır. Kutu kapatılınca yazılan özet OLDUĞU
     GİBİ KALIR — kapatmak, yazdığını silmek değildir. */
  function ozYapiAlan(){ return [].slice.call(document.querySelectorAll('[data-ozbolum]')); }
  function ozYapiAcikMi(){ var c=\$('mOzetYapili'); return !!(c&&c.checked); }
  function ozYapiTopla(){
    if(!ozYapiAcikMi()) return null;
    var o={};
    ozYapiAlan().forEach(function(e){
      var v=e.value.replace(/\s+/g,' ').trim();
      if(v) o[e.getAttribute('data-ozbolum')]=v;
    });
    return o;
  }
  function ozYapiOzet(){
    var p=[];
    ozYapiAlan().forEach(function(e){
      var v=e.value.replace(/\s+/g,' ').trim();
      if(v) p.push((e.previousElementSibling?e.previousElementSibling.textContent.trim():'')+': '+v);
    });
    return p.join(' ');
  }
  function ozYapiCiz(){
    var acik=ozYapiAcikMi(), kutu=\$('mOzetYapi'), oz=\$('mOzet');
    if(kutu) kutu.hidden=!acik;
    if(!oz) return;
    oz.readOnly=acik;
    if(acik) oz.value=ozYapiOzet();
    if(oz.kdSay) oz.kdSay();
  }
  if(\$('mOzetYapili')) \$('mOzetYapili').addEventListener('change',ozYapiCiz);
  ozYapiAlan().forEach(function(e){ e.addEventListener('input',ozYapiCiz); });
  ozYapiCiz();

  /* İstem pencerelerinin düzeneği k/istem.php'de; iki düğme de aynı
     denetim penceresini açar. */

  /* ---- Doğrulayıcılar ---- */
  function orcidTemiz(s){
    s=String(s||'').toUpperCase().trim().replace(/^HTTPS?:\\/\\/(WWW\\.)?ORCID\\.ORG\\//,'');
    var d=s.replace(/[^0-9X]/g,'');
    if(d.length!==16) return '';
    if(d.slice(0,15).indexOf('X')!==-1) return '';
    var t=0; for(var i=0;i<15;i++) t=(t+parseInt(d.charAt(i),10))*2;
    var son=(12-(t%11))%11, bek=(son===10)?'X':String(son);
    if(d.charAt(15)!==bek) return '';
    return d.slice(0,4)+'-'+d.slice(4,8)+'-'+d.slice(8,12)+'-'+d.slice(12,16);
  }
  function scopusTemiz(s){
    s=String(s||'').trim();
    var mm=s.match(/authorId=(\\d{6,})/i); if(mm) s=mm[1];
    var d=s.replace(/\\D/g,'');
    if(!d||d.length<8||d.length>16) return '';
    return d;
  }
  function unvanYeterli(u){ return /(^|\\s)(dr|prof|do[çc])\\b|dr\\./i.test(String(u||'').trim()); }
  function linkGecerli(s){ return /^https?:\\/\\/.+\\..+/i.test(String(s||'').trim()); }

  /* ---- Ortak yazarlar ---- */
  /* ---- Kefil alanı ----
     Unvan alanı boş ya da yetersiz bırakıldığında açılır. İki kefil
     istenir: biri bu çalışmanın doktoralı ortak yazarı, biri
     çalışmayla bağı olmayan bağımsız bir doktoralı. */
  function kefilIc(){
    function blok(tur,bas,ack){
      return '<div class="kart kefil-k" data-kt="'+tur+'">'+
        '<h4>'+bas+'</h4><p>'+ack+'</p>'+
        /* ETİKET ALANI SARAR. Eskiden <label>...</label><input> yan
           yana yazılıyordu: etiket görünüyordu ama alana BAĞLI
           değildi (ne for/id ne de sarma). Ekran okuyucu alanı adsız
           okuyor, etikete tıklamak alanı odaklamıyordu. Bu bloklar
           betikle çoğaltıldığı için for/id yolu seçilmedi: aynı id
           iki kez basılır ve bağ ikisinde de kırılırdı. Sarmak
           çoğaltmadan etkilenmez. Görünüş değişmedi; biçim öge adıyla
           yazılı (kutadgu.css), sınıfla değil. */
        '<div class="alan-ikili"><div class="alan"><label>'+S.unvanEt+'<select class="k-unvan">'+S.unvanSec+'</select></label></div>'+
        '<div class="alan"><label>'+S.adEt+'<input type="text" class="k-ad" placeholder="'+S.adYer+'"></label></div></div>'+
        '<label>'+S.kurumEt+'<input type="text" class="k-kurum" placeholder="'+S.kurumEt+'"></label>'+
        '<div class="alan-ikili"><div class="alan"><label>ORCID <b class="zor">'+S.zorunlu+'</b><input type="text" class="k-orcid" placeholder="0000-0000-0000-0000"></label></div>'+
        '<div class="alan"><label>'+S.kfEposta+' <b class="zor">'+S.zorunlu+'</b><input type="email" class="k-eposta" placeholder="ornek@universite.edu.tr"></label></div></div>'+
        '</div>';
    }
    return '<div class="kefil-bas">'+S.kfBas+'</div><p class="kefil-ack">'+S.kfAck+'</p>'+
      blok('ortak_yazar',S.kfOrtakB,S.kfOrtakA)+blok('bagimsiz',S.kfBagB,S.kfBagA);
  }
  function kefilBagla(unvanGirdi, alan){
    function guncelle(){
      /* Şart kalktıysa destek alanı HİÇ açılmaz:
         olmayan bir engelin yan kapısını göstermek, kullanıcıya
         var olmayan bir zorunluluk anlatmaktır. */
      var gerek=DOKTORA_SARTI && !unvanYeterli(unvanGirdi.value.trim());
      if(gerek && !alan.dataset.kuruldu){ alan.innerHTML=kefilIc(); alan.dataset.kuruldu='1'; }
      alan.classList.toggle('gizli', !gerek);
    }
    unvanGirdi.addEventListener('input',guncelle);
    unvanGirdi.addEventListener('blur',guncelle);
    guncelle();
  }
  /* Bir kefil alanından kayıtları toplar; eksik varsa [metin, alan] döner */
  function kefilTopla(alan, kimAd){
    if(alan.classList.contains('gizli')) return {liste:[],hata:null};
    var liste=[],hata=null;
    Array.prototype.forEach.call(alan.querySelectorAll('.kefil-k'),function(k){
      var u=k.querySelector('.k-unvan').value.trim(),
          a=k.querySelector('.k-ad').value.trim(),
          o=orcidTemiz(k.querySelector('.k-orcid').value),
          e=k.querySelector('.k-eposta').value.trim().toLowerCase();
      if(!hata&&!a)hata=[S.eKfAd,k.querySelector('.k-ad')];
      else if(!hata&&!unvanYeterli(u))hata=[S.eKfUnvan,k.querySelector('.k-unvan')];
      else if(!hata&&!o)hata=[S.eKfOrcid,k.querySelector('.k-orcid')];
      else if(!hata&&!/.+@.+\..+/.test(e))hata=[S.eKfEposta,k.querySelector('.k-eposta')];
      if(o)k.querySelector('.k-orcid').value=o;
      liste.push({unvan:u,ad:a,kurum:k.querySelector('.k-kurum').value.trim(),
                  orcid:o,eposta:e,ilgi:k.dataset.kt});
    });
    return {liste:liste,hata:hata};
  }
  kefilBagla(\$('unvan'), document.querySelector('[data-kefil-alan="b"]'));

  var yazarKutu=\$('yazarlar');
  function yazarSatir(){
    var no=yazarKutu.querySelectorAll('.yazar-sat').length+2;
    var d=document.createElement('div'); d.className='yazar-sat';
    d.innerHTML='<div class="ysira">'+no+S.yazarNo+'</div>'+
      '<div class="alan-ikili"><div class="alan"><label>'+S.unvanEt+'</label><select class="y-unvan">'+S.unvanSec+'</select></div>'+
      '<div class="alan"><label>'+S.adEt+'</label><input type="text" class="y-ad" placeholder="'+S.adYer+'"></div></div>'+
      '<label>'+S.kurumEt+'</label><input type="text" class="y-kurum" placeholder="'+S.kurumEt+'">'+
      '<div class="alan-ikili"><div class="alan"><label>ORCID <b class="zor">'+S.zorunlu+'</b></label><input type="text" class="y-orcid" placeholder="0000-0000-0000-0000"></div>'+
      '<div class="alan"><label>'+S.yEposta+' <b class="zor">'+S.zorunlu+'</b></label><input type="email" class="y-eposta" placeholder="'+S.yEpostaYer+'"></div>'+
      '</div>'+
      '<button type="button" class="d d-ikinci d-kucuk y-ara">'+S.yAra+'</button>'+
      '<p class="ipucu y-ara-not" hidden></p>'+
      '<div class="kutu kutu-kut kefil-alan gizli" data-kefil-alan="y"></div>'+
      '<button type="button" class="d d-ikinci d-kucuk sil">'+S.kaldir+'</button>';
    d.querySelector('.sil').addEventListener('click',function(){d.remove();yenidenNumarala();});
    /* ---- ORCID İLE ARAMA ----
       Kurul kararı, 15 Ağustos 2026: "ekleyeceği kişiler sistemde
       kayıtlıysa oradan seçebilir, ORCID'ini girerek arayabilir."

       ARAMA YALNIZCA ORCID İLE YAPILIR, E-POSTA İLE DEĞİL. E-postayla
       arayan bir uç, "bu adres burada kayıtlı mı" sorusuna yanıt veren
       bir makine olurdu; bu, üyeliği herkese açık hâle getirir. ORCID
       zaten herkese açık bir numaradır: birinin ORCID'ini bilen kişi
       onun kim olduğunu da bilir. E-posta yine de İSTENİR — aramak
       için değil, yazarlığı kişiye BİLDİRMEK için.

       Arama bulamazsa bu bir hata değildir: kişi sistemde yoktur ve
       gönderimden sonra ona davet gider. Not da bunu söyler. */
    d.querySelector('.y-ara').addEventListener('click',function(){
      var dg=d.querySelector('.y-ara'), no=d.querySelector('.y-ara-not');
      var o=orcidTemiz(d.querySelector('.y-orcid').value);
      function yaz(t){ no.textContent=t; no.hidden=false; }
      if(!o){ yaz(S.yAraOrcid); return; }
      d.querySelector('.y-orcid').value=o;
      dg.disabled=true; yaz(S.yAranıyor);
      fetch(ARA_UC,{method:'POST',headers:{'Content-Type':'application/json'},
        body:JSON.stringify({orcid:o})})
        .then(function(r){return r.json();})
        .then(function(k){
          dg.disabled=false;
          if(k&&k.ok&&k.bulundu){
            if(k.ad)d.querySelector('.y-ad').value=k.ad;
            if(k.kurum)d.querySelector('.y-kurum').value=k.kurum;
            if(k.unvan){ var u=d.querySelector('.y-unvan');
              for(var i=0;i<u.options.length;i++) if(u.options[i].value===k.unvan) u.selectedIndex=i; }
            yaz(S.yBulundu);
          } else { yaz(S.yBulunmadi); }
        })
        .catch(function(){ dg.disabled=false; yaz(S.yAraHata); });
    });
    yazarKutu.appendChild(d);
    kefilBagla(d.querySelector('.y-unvan'), d.querySelector('[data-kefil-alan="y"]'));
  }
  function yenidenNumarala(){
    Array.prototype.forEach.call(yazarKutu.querySelectorAll('.yazar-sat'),function(el,i){
      el.querySelector('.ysira').textContent=(i+2)+S.yazarNo;});
  }
  \$('yazarEkle').addEventListener('click',yazarSatir);

  /* ---- Hakem önerileri ---- */
  var oneriKutu=\$('oneriler');
  \$('oneriEkle').addEventListener('click',function(){
    if(oneriKutu.querySelectorAll('.yazar-sat').length>=6)return;
    var no=oneriKutu.querySelectorAll('.yazar-sat').length+1;
    var d=document.createElement('div'); d.className='yazar-sat';
    d.innerHTML='<div class="ysira">'+no+S.hakemNo+'</div>'+
      '<div class="alan-ikili"><div class="alan"><label>'+S.hAd+'</label><input type="text" class="o-ad" placeholder="Prof. Dr. ..."></div>'+
      '<div class="alan"><label>'+S.kurumEt+'</label><input type="text" class="o-kurum" placeholder="'+S.kurumEt+'"></div></div>'+
      '<div class="alan-ikili"><div class="alan"><label>'+S.hEposta+'</label><input type="email" class="o-eposta" placeholder="hakem@universite.edu.tr"></div>'+
      '<div class="alan"><label>'+S.hAlan+'</label><input type="text" class="o-alan" placeholder=""></div></div>'+
      '<button type="button" class="d d-ikinci d-kucuk sil">'+S.kaldir+'</button>';
    d.querySelector('.sil').addEventListener('click',function(){
      d.remove();
      Array.prototype.forEach.call(oneriKutu.querySelectorAll('.yazar-sat'),function(el,i){
        el.querySelector('.ysira').textContent=(i+1)+S.hakemNo;});
    });
    oneriKutu.appendChild(d);
  });

  /* ---- Etik kurul ve veri alanları ----
     BETİKSİZ TARAYICIDA GÖRÜNÜR OLMALILAR.
     Bu üç bölme sayfaya class="gizli" ile basılıyordu: betiği kapalı
     olan kişi "Gereklidir" işaretleyip ilerlediğinde kurul adını,
     tarihi ve numarayı GİRECEK YER BULAMIYORDU ve uç haklı olarak 400
     dönüyordu — kişi de neyi eksik bıraktığını hiçbir yerde göremiyordu.
     Aynı desen yapay zekâ adımında zaten uygulanmıştı: "betiksiz
     tarayıcıda ikisi de görünür, betik gereksiz olanı gizler."

     Artık bölmeler AÇIK basılıyor; gizlemeyi betik yapıyor ve ilk
     çalıştığında bir kez uyguluyor. Betik yoksa üç bölme de açıktır ve
     kişi hangisi kendisine uyuyorsa onu doldurur. */
  function etikCiz(){
    var r=document.querySelector('input[name=etik_durum]:checked');
    \$('etikAlan').classList.toggle('gizli', !r || r.value !== 'gerekli');
  }
  function veriCiz(){
    var r=document.querySelector('input[name=veri_beyan]:checked');
    var v=r?r.value:'';
    \$('veriAlan').classList.toggle('gizli', !(v==='acik'||v==='istek'));
    \$('veriGerekceAlan').classList.toggle('gizli', v!=='kisitli');
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name=etik_durum]'),function(r){
    r.addEventListener('change', etikCiz);
  });
  Array.prototype.forEach.call(document.querySelectorAll('input[name=veri_beyan]'),function(r){
    r.addEventListener('change', veriCiz);
  });
  /* ---- BEYAN ADIMININ KOŞULLU BÖLMELERİ ----
     Aynı desen: bölme AÇIK basılır, gizlemeyi betik yapar. Betiksiz
     tarayıcıda "Var" diyen kişi açıklama alanını bulamazsa uç haklı
     olarak reddeder ve kişi nedenini göremez. */
  function ccCiz(){
    var r=document.querySelector('input[name=cikar_catismasi]:checked');
    \$('ccAlan').classList.toggle('gizli', !r || r.value !== 'var');
  }
  function fonCiz(){
    var r=document.querySelector('input[name=fon_durum]:checked');
    \$('fonAlan').classList.toggle('gizli', !r || r.value !== 'var');
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name=cikar_catismasi]'),function(r){
    r.addEventListener('change', ccCiz); });
  Array.prototype.forEach.call(document.querySelectorAll('input[name=fon_durum]'),function(r){
    r.addEventListener('change', fonCiz); });
  etikCiz(); veriCiz(); ccCiz(); fonCiz();

  /* ---- Yapay zekâ beyanı ----
     Kullanım beyan edilmediğinde kapsam ve etik onayı ekrandan
     kaldırılır; yerine ne beyan edildiğini söyleyen cümle gelir.
     'hidden' kullanılıyor, 'gizli' değil: bu iki öge birbirinin YERİNE
     geçiyor, biri gizlenip öteki açılıyor. */
  function yzCiz(){
    var y=document.querySelector('input[name=yz_kullanim]:checked');
    var yok=!!y&&y.value==='yok';
    if(\$('yzKapsam'))\$('yzKapsam').hidden=yok;
    if(\$('yzYokNot'))\$('yzYokNot').hidden=!yok;
  }
  Array.prototype.forEach.call(document.querySelectorAll('input[name=yz_kullanim]'),function(r){
    r.addEventListener('change',yzCiz);
  });
  yzCiz();

  /* =================================================================
     ADIM ADIM DOĞRULAMA
     -----------------------------------------------------------------
     Kurallar eskiden tek uzun bir işlevin içinde, gönder düğmesine
     basılınca sırayla koşuyordu. Bu, sona saklanan hata demekti: on
     bölümü doldurmuş biri ilk bölümdeki bir eksik yüzünden en başa
     fırlatılıyordu.

     Kurallar artık ADIMA GÖRE bölündü ve iki yerden çağrılıyor:
       - "İleri"ye basınca yalnız o adımın kuralları,
       - Göndermeden önce hepsi, baştan sona.
     İkisi de AYNI işlevleri çağırır; kural tek yerde durur. İki ayrı
     kural kümesi yazmak, birini güncelleyip ötekini unutmak demektir.
     ================================================================= */
  var m=\$('mesaj');
  /* =================================================================
     HATA, ALANIN YANINDA YAZAR

     Bildirilen kusur (15 Ağustos 2026): "formu doldururken nerede hata
     olduğunu anlayamıyorum." Ölçüldü, doğruydu: hata metni formun
     altındaki tek bir satıra yazılıyordu. On altı alanlık bir formda o
     satır "bir yerde bir şey yanlış" demekten başka bir şey söylemez.
     Odaklanma ve kaydırma vardı ama metin gözün gittiği yerde değildi;
     kişi doğru alana bakıyor, yanlışın ne olduğunu başka yerde
     okuyordu.

     Artık üç şey birden yapılıyor:
       - alan aria-invalid="true" alır (kırmızı kenar, ekran okuyucuda
         "geçersiz" diye duyurulur; biçimi kutadgu.css'te zaten vardı),
       - metin alanın HEMEN ALTINA basılır ve alana aria-describedby
         ile bağlanır,
       - alt satırdaki genel ileti de kalır: alanı olmayan hatalar
         (ağ hatası, uçtan dönen ret) oraya düşer.

     Metin tek yerden gelir: aynı cümle iki yerde ayrı ayrı yazılmaz.
     Alan yeniden yazılmaya başlandığında kendi hatası silinir; bütün
     hataları temizlemek için temizMesaj() yeter.
     ================================================================= */
  var HATA_ID = 'alan-hata-';
  function alanKap(el){
    /* İletiyi alanın KENDİ satırının sonuna koyarız: ikili ızgarada
       (.alan-ikili) doğrudan alanın altına konursa öteki sütunu
       kaydırır. .alan sarmalı varsa o, yoksa alanın kendisi. */
    var k = el.closest ? el.closest('.alan') : null;
    return k || el;
  }
  function alanHataSil(el){
    if(!el)return;
    el.removeAttribute('aria-invalid');
    var d = el.getAttribute('aria-describedby')||'';
    if(d.indexOf(HATA_ID)===0) el.removeAttribute('aria-describedby');
    var k = alanKap(el), e = k.nextSibling;
    if(e && e.nodeType===1 && e.className==='alan-hata' && e.parentNode) e.parentNode.removeChild(e);
  }
  function tumHatalariSil(){
    Array.prototype.forEach.call(form.querySelectorAll('[aria-invalid="true"]'),function(el){
      el.removeAttribute('aria-invalid'); el.removeAttribute('aria-describedby');
    });
    Array.prototype.forEach.call(form.querySelectorAll('.alan-hata'),function(e){
      if(e.parentNode)e.parentNode.removeChild(e);
    });
  }
  var hataSayac=0;
  function hata(t,el){ m.textContent=t; m.className='form-msj err';
    tumHatalariSil();
    if(el){
      var kap=alanKap(el), p=document.createElement('p');
      p.className='alan-hata'; p.setAttribute('role','alert');
      p.id = HATA_ID + (++hataSayac);
      p.textContent = t;
      if(kap.parentNode) kap.parentNode.insertBefore(p, kap.nextSibling);
      el.setAttribute('aria-invalid','true');
      el.setAttribute('aria-describedby', p.id);
      /* Yazmaya başlayınca kendi hatası kalkar: düzeltilmiş bir alanın
         yanında duran kırmızı metin, düzeltmeyi görmemek demektir. */
      var kalk=function(){ alanHataSil(el);
        el.removeEventListener('input',kalk); el.removeEventListener('change',kalk); };
      el.addEventListener('input',kalk); el.addEventListener('change',kalk);
      try{el.focus();}catch(e){}
      el.scrollIntoView({block:'center',behavior:'smooth'});
    }
    return false; }
  function temizMesaj(){ m.textContent=''; m.className='form-msj'; tumHatalariSil(); }

  /* Toplayıcılar: hem doğrulama hem gönderim aynı veriyi okur. */
  var toplanan={yazarlar:[],bKefil:null,oneri:[]};
  function yazarlariTopla(){
    var ad=\$('ad').value.trim(), unvan=\$('unvan').value.trim();
    var yazarlar=[],ykHata=null,doktoraliSay=unvanYeterli(unvan)?1:0;
    var bKefil=kefilTopla(document.querySelector('[data-kefil-alan="b"]'),ad);
    if(bKefil.hata)return {hata:bKefil.hata};
    Array.prototype.forEach.call(yazarKutu.querySelectorAll('.yazar-sat'),function(sa){
      var a=sa.querySelector('.y-ad').value.trim(); if(!a)return;
      var u=sa.querySelector('.y-unvan').value.trim();
      var o=orcidTemiz(sa.querySelector('.y-orcid').value), sc='';
      if(!ykHata&&!o)ykHata=[a+S.eYOrcid,sa.querySelector('.y-orcid')];

      if(o)sa.querySelector('.y-orcid').value=o;
      /* E-POSTA ZORUNLU. Kurul kararı, 15 Ağustos 2026: eklenen yazara
         "bu çalışmada yer alıyorsunuz" diyen bir bildirim gider; kayıtlı
         değilse davet bağlantısıyla. Adresi olmayan bir yazara bunu
         söyleyemeyiz ve kişi adının nerede kullanıldığını bilmez.
         Adının kullanıldığını bilmeyen yazarlık, yazarlık değildir. */
      var ye=(sa.querySelector('.y-eposta')?sa.querySelector('.y-eposta').value:'').trim().toLowerCase();
      if(!ykHata&&!/.+@.+\..+/.test(ye))ykHata=[a+S.eYEposta,sa.querySelector('.y-eposta')];

      if(unvanYeterli(u)) doktoraliSay++;
      var kf=kefilTopla(sa.querySelector('[data-kefil-alan="y"]'),a);
      if(!ykHata&&kf.hata)ykHata=kf.hata;
      yazarlar.push({unvan:u,ad:a,kurum:sa.querySelector('.y-kurum').value.trim(),orcid:o,eposta:ye,scopus:sc,kefiller:kf.liste});
    });
    if(ykHata)return {hata:ykHata};
    /* Unvanı olmayan biri tek başına gönderemez: yanında doktoralı biri
       olmalı. YALNIZCA şart açıkken; kalktığında bu kural yoktur. */
    if(DOKTORA_SARTI && doktoraliSay===0)return {hata:[S.eKfTek,\$('unvan')]};
    toplanan.yazarlar=yazarlar; toplanan.bKefil=bKefil;
    return {liste:yazarlar,bKefil:bKefil};
  }
  function onerileriTopla(){
    var oneri=[];
    Array.prototype.forEach.call(oneriKutu.querySelectorAll('.yazar-sat'),function(sa){
      var a=sa.querySelector('.o-ad').value.trim(); if(!a)return;
      oneri.push({ad:a,kurum:sa.querySelector('.o-kurum').value.trim(),
                  eposta:sa.querySelector('.o-eposta').value.trim(),alan:sa.querySelector('.o-alan').value.trim()});
    });
    toplanan.oneri=oneri; return oneri;
  }

  /* Adım kuralları. Anahtar, gövdedeki data-adim ile aynıdır; ikisi de
     ortak.php'deki tg_basvuru_adimlari() listesinden gelir. */
  var ADIM_DEN={
    calisma:function(){
      if(!\$('mBaslik').value.trim())return [S.eBaslik,\$('mBaslik')];
      /* Künye dili zorunlu kılınmışsa (kurul kararı) ve çalışmanın dili
         ondan başkaysa, künye alanları da istenir. Zorunluluk ayar
         dosyasından gelir; sayfa kendi kuralını koymaz. */
      if(KUNYE_ZOR && \$('mBaslikEn') && !\$('mDilSkKunye').hidden){
        if(!\$('mBaslikEn').value.trim()){ dilSekAc('kunye'); return [S.eKunyeBaslik,\$('mBaslikEn')]; }
        if(\$('mOzetEn') && !\$('mOzetEn').value.trim()){ dilSekAc('kunye'); return [S.eKunyeOzet,\$('mOzetEn')]; }
      }
      /* GENİŞLETİLMİŞ ÖZET. Kural sunucudan gelir; bölme gizliyse
         istenmez — gizli bir alanı zorunlu tutmak, kapalı bir kapıyı
         kilitli bulmaktır. */
      var gk=\$('mGenisKutu');
      if(GENIS_ZOR && gk && !gk.hidden && \$('mGenisOzet')){
        var gm=\$('mGenisOzet').value.replace(/\s+/g,' ').trim();
        var gn=gm?gm.split(' ').length:0;
        if(gn<GENIS_AZ){ dilSekAc('kunye'); return [S.eGenis.replace('%1',GENIS_AZ),\$('mGenisOzet')]; }
      }
      if(window.alSecOku && !window.alSecOku('alanlar').length)
        return [S.eAlan, document.querySelector('[data-alsec="alanlar"] [data-alsec-ara]')];
      return null;
    },
    /* TAM METİN. Düzenleyici değeri gizli textarea'ya geri yazar, ama
       son yazımı beklememek için kdOku() ile doğrudan sorulur: yazarın
       son cümlesi doğrulamada eksik sayılmamalı. */
    metin:function(){
      var m = (window.kdOku ? window.kdOku('bvMetin') : dg('bvMetin')) || '';
      \$('bvMetin').value = m;
      /* Etiketler atılıp kelime sayılır: <p> sayısı bir ölçü değildir. */
      var duz = m.replace(/<[^>]*>/g, ' ').replace(/&nbsp;/g, ' ').replace(/\s+/g, ' ').trim();
      var kel = duz ? duz.split(' ').length : 0;
      if (kel < METIN_AZ) return [S.eMetin.replace('%1', METIN_AZ), \$('bvMetin')];
      /* Yüklemesi süren görsel varsa gönderim beklenir: yarısı data:
         adresli bir metin kaydedilirse o görseller sessizce düşer. */
      if (document.querySelector('#bvMetin-yapi img, .jodit-wysiwyg img[src^="data:"]'))
        return [S.eGorselBek, \$('bvMetin')];
      var k = (window.kdOku ? window.kdOku('bvKaynakca') : dg('bvKaynakca')) || '';
      \$('bvKaynakca').value = k;
      if (!k.replace(/<[^>]*>/g, '').trim()) return [S.eKaynakca, \$('bvKaynakca')];
      return null;
    },
    basvuran:function(){
      if(!\$('ad').value.trim())return [S.eAd,\$('ad')];
      if(!/.+@.+\\..+/.test(\$('eposta').value.trim()))return [S.ePosta,\$('eposta')];
      var o=orcidTemiz(\$('orcid').value);
      if(!o)return [S.eOrcid,\$('orcid')];
      \$('orcid').value=o;
      /* Scopus alanı formdan kaldırıldı (kurul, 15 Ağustos 2026).
         Olmayan bir alanı okumak, doğrulamanın ilk satırında null
         dönerdi ve bütün adım doğrulaması sessizce çökerdi. */
      return null;
    },
    ortak:function(){
      var r=yazarlariTopla(); if(r.hata)return r.hata;
      /* Yazar listesinin TAM olduğu beyanı. Gerekçesi ekranın
         yanındadır; özeti: eksik bırakılan yazar, kendisine gidecek
         bildirimi hiç almaz. */
      var t=document.querySelector('input[name=yazar_tam]:checked');
      if(!t)return [S.eYazarTam,document.querySelector('input[name=yazar_tam]')];
      if(t.value==='tek' && (r.liste||[]).length)
        return [S.eYazarTek,document.querySelector('input[name=yazar_tam][value=hepsi]')];
      return null;
    },
    benzerlik:function(){
      /* dg(): benzerlik adımı kapalıyken alanlar hiç basılmaz. Bu
         doğrulayıcı o durumda çağrılmaz ama okuma yine de güvenli
         olmalı — doğrulamanın kendisi çökerse kimse uyarı görmez. */
      var iArac=dg('intArac'), iOran=parseFloat(dg('intOran')), iTek=parseFloat(dg('intTek'));
      /* ŞART KAPALIYKEN HİÇBİR ALAN ARANMAZ ama girilen değer yine de
         tutarlı olmalı: sayfada gösterilecek bir sayı, kimsenin
         bakmadığı bir sayı değildir. Uç de aynı ikili davranışı
         uyguluyor; ikisi ayrışırsa tarayıcı geçirir, sunucu reddeder
         ve kişi neden reddedildiğini anlamaz. */
      if(!BENZ_SART){
        if(!isNaN(iOran) && !isNaN(iTek) && iTek>iOran)return [S.eTekBuyuk,\$('intTek')];
        return null;
      }
      if(!iArac)return [S.eArac,\$('intArac')];
      if(isNaN(iOran))return [S.eOran,\$('intOran')];
      if(iOran>BENZ)return [S.eOranUst+iOran,\$('intOran')];
      if(isNaN(iTek))return [S.eTek,\$('intTek')];
      if(iTek>BENZ_TEK)return [S.eTekUst+iTek,\$('intTek')];
      if(iTek>iOran)return [S.eTekBuyuk,\$('intTek')];
      if(!linkGecerli(dg('intLink')))return [S.eLink,\$('intLink')];
      return null;
    },
    etik:function(){
      var e=document.querySelector('input[name=etik_durum]:checked');
      if(!e)return [S.eEtik,document.querySelector('input[name=etik_durum]')];
      /* İZİN GEREKLİ DENDİYSE ÜÇÜ DE İSTENİR. Bu üç metin, belgenin
         yerine geçen şeydir; biri eksikse beyan denetlenemez ve
         denetlenemeyen bir beyan yazarı bağlamaz. Aynı denetim uçta da
         var: burada geçip orada takılan bir alan, kişiye nedenini
         söylemeyen bir ret üretir. */
      if(e.value==='gerekli'){
        if(!\$('etikKurul').value.trim())return [S.eEtikKurul,\$('etikKurul')];
        if(!\$('etikTarih').value.trim())return [S.eEtikTarih,\$('etikTarih')];
        if(!\$('etikNo').value.trim())return [S.eEtikNo,\$('etikNo')];
      }
      return null;
    },
    veri:function(){
      var v=document.querySelector('input[name=veri_beyan]:checked');
      if(!v)return [S.eVeri,document.querySelector('input[name=veri_beyan]')];
      if(v.value==='acik'&&!linkGecerli(\$('veriUrl').value.trim()))return [S.eVeriUrl,\$('veriUrl')];
      if(v.value==='kisitli'&&\$('veriGerekce').value.trim().length<15)return [S.eVeriGerekce,\$('veriGerekce')];
      return null;
    },
    yz:function(){
      /* ---- BEYANLAR ----
         Adım artık yalnız yapay zekâ beyanı değil; çıkar çatışması,
         başka yerde değerlendirilmeme ve fon da burada. Sıra ekrandaki
         sırayla aynıdır: kişi eksiği ararken yukarıdan aşağı bakar. */
      var cc=document.querySelector('input[name=cikar_catismasi]:checked');
      if(!cc)return [S.eCikar,document.querySelector('input[name=cikar_catismasi]')];
      if(cc.value==='var' && !dg('ccAciklama').trim())return [S.eCikarAck,\$('ccAciklama')];
      if(!\$('tekGonderim').checked)return [S.eTekGon,\$('tekGonderim')];
      var fn=document.querySelector('input[name=fon_durum]:checked');
      if(!fn)return [S.eFon,document.querySelector('input[name=fon_durum]')];
      if(fn.value==='var' && !dg('fonKaynak').trim())return [S.eFonKaynak,\$('fonKaynak')];
      var y=document.querySelector('input[name=yz_kullanim]:checked');
      if(!y)return [S.eYz,document.querySelector('input[name=yz_kullanim]')];
      /* Kullanım beyan edilmediyse ne kapsam ne de etik onayı istenir.
         Olmamış bir kullanımın kapsamı yazılamaz. */
      if(y.value==='yok')return null;
      if(\$('yzAciklama').value.trim().length<20)return [S.eYzAcik,\$('yzAciklama')];
      if(!\$('yzEtik').checked)return [S.eYzEtik,\$('yzEtik')];
      return null;
    },
    sekil:function(){ return null; },    /* hepsi isteğe bağlı */
    hakem:function(){ onerileriTopla(); return null; },
    telif:function(){
      /* İki beyan da burada: koşulların okunduğu ve lisans izni.
         Koşul METNİ birinci adımdadır ve daha önce okunmuşsa atlanır;
         BEYAN atlanmaz. */
      if(!\$('kosulOk').checked)return [S.eKosul,\$('kosulOk')];
      if(!\$('telif').checked)return [S.eTelif,\$('telif')];
      return null;
    }
  };

  /* =================================================================
     SİHİRBAZ
     -----------------------------------------------------------------
     Sunucu bütün bölümleri AÇIK gönderir; kapatmayı bu betik yapar.
     Böylece betik çalışmazsa sayfa tek uzun form olarak durur ve
     gönderilebilir. Sihirbaz bir kabuktur, tek yol değildir.

     Kapatma "hidden" ÖZNİTELİĞİYLE yapılır, sınıfla değil: hidden
     ögeyi ekrandan, erişilebilirlik ağacından VE sekme sırasından
     birden çıkarır. Sınıfla gizlenen bir adımın içindeki alana Tab ile
     düşmek sihirbazların en bilinen kusurudur.
     ================================================================= */
  var form=\$('bvForm');
  var adimlar=[].slice.call(form.querySelectorAll('[data-adim]'));
  var seritOge=[].slice.call(document.querySelectorAll('#shSerit li'));
  var simdi=Math.min(Math.max(parseInt(form.getAttribute('data-adim-baslangic'),10)||1,1),adimlar.length);
  var enBuyuk=simdi;

  function adimAnahtar(i){ return adimlar[i-1].getAttribute('data-adim'); }
  /* =================================================================
     GÖZDEN GEÇİR VE GÖNDER

     Özet FORMUN KENDİSİNDEN okunur. Alanların adını ikinci bir yere
     yazmak, formla özetin zamanla ayrışması demekti: bir alan eklenir,
     özete konmayı unutulur ve gönderen kişi onu gözden geçiremez.
     Burada yapılan iş, her adımın içindeki etiket-değer çiftlerini
     gezmektir; yeni bir alan kendiliğinden görünür.

     ÜÇ ÖZEL DURUM var ve üçü de alanın TÜRÜNDEN doğuyor, adından
     değil:
       - düzenleyici alanları: metnin kendisi değil KELİME SAYISI
         yazılır (bir makaleyi özete dökmek özet olmaz),
       - seçim düğmeleri: işaretlenenin görünen yazısı,
       - alan seçici: kendi arayüzünden okunur (alSecOku).
     ================================================================= */
  function ozKisalt(t,n){ t=(t||'').replace(/\s+/g,' ').trim(); return t.length>n?t.slice(0,n)+'…':t; }
  function ozEtiket(el){
    /* Etiket ya for= ile bağlıdır ya ögeyi sarar; ikisi de yoksa
       en yakın önceki etiket alınır. Bulunamazsa satır çizilmez:
       adı olmayan bir değer, gözden geçirilemez. */
    var l = el.id ? document.querySelector('label[for="'+el.id+'"]') : null;
    if(!l && el.closest) l = el.closest('label');
    if(!l){ var p=el.previousElementSibling; while(p){ if(p.tagName==='LABEL'){l=p;break;} p=p.previousElementSibling; } }
    if(!l) return '';
    var k = l.cloneNode(true);
    Array.prototype.forEach.call(k.querySelectorAll('input,textarea,select,small,b.zor,b.ist'),function(x){ x.remove(); });
    return ozKisalt(k.textContent,60).replace(/\s*:\s*$/,'');
  }
  function ozGorunur(el){
    /* SIRA ÖNEMLİ: önce "bu, adımın kendisi mi" diye sorulur.
       Özet çizilirken son adımdayız ve öteki bütün adımlar hidden'dır;
       önce hidden'a bakan bir döngü, ölçülecek her alanı gizli sayardı
       ve özet neredeyse boş çıkardı (ölçüldü: on bir bölümden beşi).
       Adım kabına ulaşıldığında görünürlük sorusu biter — o kabın
       kapalılığı adımın sırası yüzündendir, alanın gizliliği değil. */
    for(var a=el;a&&a!==document.body;a=a.parentElement){
      if(a.hasAttribute&&a.hasAttribute('data-adim'))return true;
      if(a.hasAttribute&&a.hasAttribute('hidden'))return false;
      if(a.classList&&a.classList.contains('gizli'))return false;
    }
    return true;
  }
  /* UZUN DEĞER ÖZETE OLDUĞU GİBİ GİRMEZ. Ölçüldü: genişletilmiş özet
     yedi yüz elli kelimedir ve özetin içine olduğu gibi basılınca
     gözden geçirme ekranının neredeyse tamamını kaplıyor, öteki
     bölümler görünmez oluyordu. Özet, metnin kendisi değil metnin
     GÖZDEN GEÇİRİLEBİLİR hâlidir: uzun metinlerde ilk cümleler ve
     kelime sayısı yeter, düzeltmek isteyen "Düzenle" ile asıl alana
     gider. */
  function ozUzun(t){
    t=(t||'').replace(/\s+/g,' ').trim();
    if(t.length<=200) return t;
    var kel=t.split(' ').length;
    return t.slice(0,200)+'… ('+S.ozKelime.replace('%1',kel)+')';
  }
  function ozSatir(ad,deger,bos){
    return '<div class="bv-oz-sat"><span class="bv-oz-ad">'+ozKac(ad)+'</span>'
         + '<span class="bv-oz-deger'+(bos?' bv-oz-bos':'')+'">'+ozKac(deger)+'</span></div>';
  }
  function ozKac(t){ return String(t==null?'':t).replace(/[<>&]/g,function(k){
    return {'<':'&lt;','>':'&gt;','&':'&amp;'}[k]; }); }
  function ozAdimIcerik(kap,basAd){
    var h='';
    /* Düzenleyici alanları: kelime sayısı ve şekil sayısı. */
    Array.prototype.forEach.call(kap.querySelectorAll('textarea[id]'),function(t){
      if(!t.joditKur) return;
      var v=(window.kdOku?window.kdOku(t.id):t.value)||'';
      var duz=v.replace(/<[^>]*>/g,' ').replace(/&nbsp;/g,' ').replace(/\s+/g,' ').trim();
      var kel=duz?duz.split(' ').length:0;
      var gor=(v.match(/<img\b/gi)||[]).length;
      var d=kel?S.ozKelime.replace('%1',kel):'';
      if(gor) d+=(d?' · ':'')+S.ozGorsel.replace('%1',gor);
      h+=ozSatir(ozEtiket(t)||t.id, d||S.ozBos, !kel);
    });
    /* Düz alanlar. */
    Array.prototype.forEach.call(kap.querySelectorAll('input[type=text],input[type=email],input[type=date],input[type=tel],input[type=number],select,textarea'),function(el){
      if(el.joditKur) return;                       /* yukarıda işlendi */
      if(el.type==='hidden'||!ozGorunur(el)) return;
      /* ALAN SEÇİCİNİN KENDİ ARAYÜZÜ ÖZETE GİRMEZ. İçindeki arama
         kutusu ve "listede olmayan dalı öner" formu, gönderilen
         çalışmanın bir parçası değil, seçiciyi kullanmanın aracıdır.
         Seçilen alanlar zaten aşağıda tek satırda yazılıyor. */
      if(el.closest('[data-alsec]')||el.closest('[data-alsec-oner]')||el.closest('.alsec')) return;
      /* YAPILANDIRILMIŞ ÖZÜN BÖLÜMLERİ ÖZETE AYRI AYRI GİRMEZ.
         Girselerdi aynı içerik iki kez yazılırdı: bir kez birleştirilmiş
         özet satırında, bir kez de dört bölüm satırında. Yayımlanacak
         olan birleştirilmiş dizedir; özet, yayımlanacak olanı gösterir. */
      if(el.closest('#mOzetYapi')) return;
      var ad=ozEtiket(el); if(!ad) return;
      var d=ozUzun(el.value||'');
      if(el.tagName==='SELECT'&&el.selectedIndex>=0){ var o=el.options[el.selectedIndex]; d=o&&o.value?o.text:''; }
      h+=ozSatir(ad,d||S.ozBos,!d);
    });
    /* Seçim düğmeleri: her küme bir satır. */
    var kume={};
    Array.prototype.forEach.call(kap.querySelectorAll('input[type=radio]'),function(r){ kume[r.name]=1; });
    Object.keys(kume).forEach(function(n){
      var s=kap.querySelector('input[name="'+n+'"]:checked');
      var ilk=kap.querySelector('input[name="'+n+'"]');
      /* KÜMENİN ADI BAŞLIKTAN GELİR, PARAGRAFTAN DEĞİL.
         Önce hemen önceki öge alınıyordu; o çoğu adımda uzun bir
         açıklama paragrafıdır ve özete yarım cümle olarak düşüyordu
         ("İnsan ya da hayvan denekle yürütülen çalışmalar ile bilim
         ot…"). Yarım bir cümle, ad değildir. Geriye doğru en yakın
         BAŞLIK aranır; o da bölümün adıyla aynıysa —çoğu adımda
         öyledir— genel bir sözcük yazılır, çünkü bölüm adı zaten
         kutunun üstünde duruyor. */
      var kok=ilk&&ilk.closest('.bv-secim')?ilk.closest('.bv-secim'):ilk;
      var bas=kok?kok.previousElementSibling:null;
      while(bas&&!/^(H3|H4|H5)$/.test(bas.tagName)) bas=bas.previousElementSibling;
      var ad=bas?ozKisalt(bas.textContent,60):'';
      if(!ad||ad===basAd) ad=S.ozBeyan;
      var d='';
      if(s){ var sp=s.parentElement.querySelector('span'); d=sp?ozKisalt(sp.childNodes[0]?sp.childNodes[0].textContent:sp.textContent,60):s.value; }
      h+=ozSatir(ad,d||S.ozBos,!d);
    });
    return h;
  }
  function ozetCiz(){
    var kutu=\$('shOzet'), eks=\$('shEksik');
    if(!kutu) return;
    var h='';
    adimlar.forEach(function(a,i){
      if(i===adimlar.length-1) return;              /* son adımın kendisi */
      var anahtar=a.getAttribute('data-adim');
      var bas=(seritOge[i]?seritOge[i].textContent:'').replace(/^\s*\d+\s*/,'').trim();
      var ic=ozAdimIcerik(a,bas);
      /* Alan seçici kendi arayüzünden okunur. */
      if(anahtar==='calisma'&&window.alSecOku){
        var al=window.alSecOku('alanlar')||[];
        ic+='<div class="bv-oz-sat"><span class="bv-oz-ad">'+ozKac(S.ozAlanAd||'')+'</span>'
          + '<span class="bv-oz-deger'+(al.length?'':' bv-oz-bos')+'">'
          + ozKac(al.length?al.join(', '):S.ozBos)+'</span></div>';
      }
      /* Ortak yazarlar: satır sayısı ve adları. */
      if(anahtar==='ortak'){
        var yz=[];
        Array.prototype.forEach.call(yazarKutu.querySelectorAll('.yazar-sat'),function(sa){
          var n=sa.querySelector('.y-ad'); if(n&&n.value.trim())yz.push(n.value.trim()); });
        ic+='<div class="bv-oz-sat"><span class="bv-oz-ad">'+ozKac(bas)+'</span>'
          + '<span class="bv-oz-deger'+(yz.length?'':' bv-oz-bos')+'">'
          + ozKac(yz.length?yz.join(', '):S.ozBos)+'</span></div>';
      }
      if(!ic) return;
      h+='<div class="bv-oz-blok"><div class="bv-oz-bas"><b>'+ozKac(bas)+'</b>'
       + '<button type="button" class="d d-ikinci d-kucuk" data-oz-git="'+ozKac(anahtar)+'">'
       + ozKac(S.ozDuzenle)+'</button></div>'+ic+'</div>';
    });
    kutu.innerHTML=h;
    Array.prototype.forEach.call(kutu.querySelectorAll('[data-oz-git]'),function(b){
      b.addEventListener('click',function(){
        var k=b.getAttribute('data-oz-git');
        for(var i=0;i<adimlar.length;i++)
          if(adimlar[i].getAttribute('data-adim')===k){ goster(i+1,true); return; }
      });
    });
    /* ---- EKSİKLERİN TAMAMI, İLK EKSİK DEĞİL ----
       Her adımın kendi doğrulayıcısı çağrılır ve dönenler TOPLANIR.
       Doğrulayıcı hata döndüğünde ekrana yazmaz; burada yalnız
       sorulur. hata() çağrılsaydı her adım için bir kırmızı kutu
       açılır ve sonuncusu ötekilerin üstüne binerdi. */
    var eksik=[];
    adimlar.forEach(function(a,i){
      if(i===adimlar.length-1) return;
      var k=a.getAttribute('data-adim'), f=ADIM_DEN[k];
      if(!f) return;
      var r=null;
      try{ r=f(); }catch(e){ r=null; }
      if(r) eksik.push({k:k, m:r[0], b:(seritOge[i]?seritOge[i].textContent:'').replace(/^\s*\d+\s*/,'').trim()});
    });
    if(eksik.length){
      var e='<h4>'+ozKac(S.ozEksikBas)+'</h4><ul>';
      eksik.forEach(function(x){
        e+='<li><button type="button" data-oz-git="'+ozKac(x.k)+'">'+ozKac(x.b)+'</button>: '+ozKac(x.m)+'</li>';
      });
      eks.innerHTML=e+'</ul>';
      eks.hidden=false;
      Array.prototype.forEach.call(eks.querySelectorAll('[data-oz-git]'),function(b){
        b.addEventListener('click',function(){
          var k=b.getAttribute('data-oz-git');
          for(var i=0;i<adimlar.length;i++)
            if(adimlar[i].getAttribute('data-adim')===k){ goster(i+1,true); return; }
        });
      });
    } else { eks.hidden=true; eks.innerHTML=''; }
    /* Doğrulayıcılar çağrıldı ve bazıları alan değeri düzeltir
       (ORCID biçimi gibi); ekranda kalan uyarı temizlenir. */
    temizMesaj();
  }

  function goster(i,odakla){
    simdi=Math.min(Math.max(i,1),adimlar.length);
    if(simdi>enBuyuk)enBuyuk=simdi;
    adimlar.forEach(function(a,j){ a.hidden = (j+1)!==simdi; });
    seritOge.forEach(function(l,j){
      l.classList.toggle('sh-simdi',(j+1)===simdi);
      l.classList.toggle('sh-gecti',(j+1)<simdi);
      var b=l.querySelector('a'); if(b)b.setAttribute('aria-current',(j+1)===simdi?'step':'false');
    });
    \$('shSay').textContent=S.shSay.replace('%1',simdi).replace('%2',adimlar.length);
    \$('shDurum').textContent=S.shDurum.replace('%1',simdi).replace('%2',adimlar.length)
      .replace('%3',(seritOge[simdi-1]?seritOge[simdi-1].textContent:'').replace(/^\s*\d+\s*/,'').trim());
    \$('shGeri').disabled=(simdi===1);
    \$('shIleri').hidden=(simdi===adimlar.length);
    \$('gonder').hidden=(simdi!==adimlar.length);
    /* Son adıma her gelişte özet YENİDEN kurulur: bir kez kurulup
       saklansaydı, geri dönüp bir alanı değiştiren kişi eski özeti
       görürdü — yani gözden geçirdiği şey gönderdiği şey olmazdı. */
    if(simdi===adimlar.length) ozetCiz();
    /* Odak yeni adımın başına gider; yoksa ekran okuyucu kullanıcısı
       sayfanın neresinde olduğunu bilemez. */
    if(odakla){
      var h=adimlar[simdi-1].querySelector('h3');
      if(h){ h.setAttribute('tabindex','-1'); h.focus(); }
      adimlar[simdi-1].scrollIntoView({block:'start',behavior:'smooth'});
    }
    /* ADRES YAZILIRKEN ÖTEKİ PARAMETRELER KORUNUR.
       Burada '?adim='+simdi yazılıydı ve adresteki HER ŞEYİ siliyordu —
       en önemlisi ?lang=. Sonucu şuydu: İngilizce (ya da başka bir
       dilde) forma giren kişi bir adım ilerlediği anda adresi dilsiz
       kalıyor; sayfayı yenilerse, yer imine eklerse ya da bağı
       paylaşırsa dil kayboluyor ve form başka bir dilde açılıyordu.
       Adım numarası bir parametredir, adresin tamamı değil. */
    try{
      var pr = new URLSearchParams(location.search);
      pr.set('adim', String(simdi));
      history.replaceState(null, '', location.pathname + '?' + pr.toString());
    }catch(e){}
  }
  function adimDogrula(i){
    var f=ADIM_DEN[adimAnahtar(i)];
    if(!f)return true;
    var h=f();
    if(h)return hata(h[0],h[1]);
    temizMesaj(); return true;
  }

  /* Adıma doğrudan götüren bağlar (ör. son adımdaki "koşulları yeniden
     aç"). Sıra tek kaynaktan geldiği için adım NUMARASI değil ANAHTARI
     yazılır; araya bir adım girse de bağ doğru yeri açar. */
  document.querySelectorAll('[data-adim-git]').forEach(function(b){
    b.addEventListener('click',function(ev){
      var k=b.getAttribute('data-adim-git');
      for(var i=0;i<adimlar.length;i++){
        if(adimlar[i].getAttribute('data-adim')===k){ ev.preventDefault(); goster(i+1,true); return; }
      }
    });
  });
  \$('shIleri').addEventListener('click',function(){ if(adimDogrula(simdi))goster(simdi+1,true); });
  \$('shGeri').addEventListener('click',function(){ temizMesaj(); goster(simdi-1,true); });
  /* Şerit: doldurulmuş ya da görülmüş bir adıma dönmek serbest, ileri
     atlamak için o ana kadarki adımların geçmesi gerekir. */
  seritOge.forEach(function(l,j){
    var b=l.querySelector('a'); if(!b)return;
    b.addEventListener('click',function(ev){
      ev.preventDefault();
      var hedef=j+1;
      if(hedef>simdi){ for(var i=simdi;i<hedef;i++){ if(!adimDogrula(i)){goster(i,true);return;} } }
      temizMesaj(); goster(hedef,true);
    });
  });
  /* Enter tuşu formu erkenden göndermesin: son adım dışında ileri
     götürsün. Metin kutusu bunun dışındadır, orada Enter satır atlar. */
  form.addEventListener('keydown',function(e){
    if(e.key!=='Enter')return;
    var t=e.target.tagName.toLowerCase();
    if(t==='textarea'||t==='button')return;
    if(simdi<adimlar.length){ e.preventDefault(); if(adimDogrula(simdi))goster(simdi+1,true); }
  });
  \$('shGez').hidden=false;

  /* =================================================================
     TASLAK: TARAYICIDA DURUR, SUNUCUDA DEĞİL
     -----------------------------------------------------------------
     Sayfanın kendi sözü şudur: "hiçbir alan gönderilmeden sisteme
     yazılmaz." Sunucuda taslak tutmak, kullanıcının HENÜZ GÖNDERMEDİĞİ
     adını, e-postasını ve ORCID'ini toplamak olurdu; hem o sözü yalan
     yapardı hem de toplanmamış olsa sızdırılamayacak bir veriyi
     toplardı. Bu yüzden taslak localStorage'da durur.

     Saklanan yalnızca formun kendi alanlarıdır. Bir parola, oturum
     anahtarı ya da kimlik belirteci hiçbir zaman yazılmaz.
     Taslak 30 günde eskir ve gönderim başarılı olduğunda silinir.
     ================================================================= */
  var TASLAK='kutadgu-basvuru-taslak', TASLAK_GUN=30;
  /* KOŞULU OKUMUŞ OLMANIN YEREL KAYDI.
     Girişli kullanıcı için bu soruyu sunucu yanıtlar (önceki
     başvurusunda hangi sürümü okuduğu kayıtlıdır). Girişsiz biri için
     sunucuda hiçbir şey tutulmaz — bu sayfanın verdiği söz budur — ama
     kendi tarayıcısında tutulabilir. Saklanan tek şey metnin sürümüdür;
     kim olduğu değil. Metin değişince sürüm değişir ve koşullar yeniden
     gösterilir. */
  var KOSUL='kutadgu-kosul-surum';
  var kosulSurum=form.getAttribute('data-kosul-surum')||'';
  function kosulOkunduYaz(){ try{ if(kosulSurum) localStorage.setItem(KOSUL,kosulSurum); }catch(e){} }
  function kosulOkunduMu(){ try{ return !!kosulSurum && localStorage.getItem(KOSUL)===kosulSurum; }catch(e){ return false; } }
  function taslakAlanlar(){
    return [].slice.call(form.querySelectorAll('input[name],select[name],textarea[name]'))
      .filter(function(e){ return e.type!=='hidden'&&e.type!=='submit'; });
  }
  /* GÖNDERİLDİKTEN SONRA TASLAK YAZILMAZ.
     Ölçüldü: kullanıcı telif kutusunu işaretleyip HEMEN gönder'e
     basınca, kutunun tetiklediği 600 ms'lik gecikmeli taslak yazımı
     gönderim bittikten SONRA çalışıyor ve az önce silinen taslağı
     geri yazıyordu. Sonuç: başvurusu alınmış birinin tarayıcısında
     "yarım kalmış başvurunuz var" diye duran bir hayalet.
     İki kapı birden: bekleyen zamanlayıcı iptal edilir ve bir daha
     yazılmayacağı bir bayrakla söylenir. */
  var taslakKapali = false;
  var taslakZaman = null;
  function taslakYaz(){
    if(taslakKapali)return;
    try{
      var d={t:Date.now(),adim:simdi,alan:{},kutu:[],alanlar:(window.alSecOku?window.alSecOku('alanlar'):[])};
      taslakAlanlar().forEach(function(e){
        if(e.type==='checkbox'||e.type==='radio'){ if(e.checked)d.kutu.push(e.name+'='+e.value); }
        /* DÜZENLEYİCİ ALANI DOĞRUDAN OKUNUR. e.value gizli
           textarea'dır ve düzenleyicinin son yazımını beklemiş
           olmayabilir; taslağın yarım bir metni saklaması, yarım bir
           metni geri vermesi demektir. */
        else if(e.joditKur){ var v=(window.kdOku?window.kdOku(e.id):e.value)||''; if(v)d.alan[e.name]=v; }
        else if(e.value)d.alan[e.name]=e.value;
      });
      localStorage.setItem(TASLAK,JSON.stringify(d));
    }catch(e){}
  }
  function taslakOku(){
    try{
      var d=JSON.parse(localStorage.getItem(TASLAK)||'null');
      if(!d||!d.t)return null;
      if(Date.now()-d.t > TASLAK_GUN*864e5){ localStorage.removeItem(TASLAK); return null; }
      return d;
    }catch(e){ return null; }
  }
  function taslakSil(){ clearTimeout(taslakZaman); try{ localStorage.removeItem(TASLAK); }catch(e){} }
  function taslakYukle(d){
    taslakAlanlar().forEach(function(e){
      if(e.type==='checkbox'||e.type==='radio'){
        if(d.kutu.indexOf(e.name+'='+e.value)>=0){ e.checked=true;
          e.dispatchEvent(new Event('change',{bubbles:true})); }
      } else if(d.alan[e.name]!==undefined){
        /* ---- ÖLÇÜLEN KUSUR: TAM METİN TASLAKTAN GERİ GELMİYORDU ----
           Buraya e.value=... yazmak, düzenleyici tarafından yönetilen
           bir alanda GİZLİ textarea'yı doldurur; ekrandaki düzenleyici
           boş kalır ve ilk değişiklikte boş değer textarea'nın üstüne
           yazılır. Yani beş bin kelimelik bir metni yazıp sayfayı
           yenileyen kişi metnini kaybederdi. Tek kapı kdAyarla()'dır:
           hem düzenleyiciyi hem textarea'yı birlikte günceller. */
        if(e.joditKur && window.kdAyarla) window.kdAyarla(e.id, d.alan[e.name]);
        else e.value=d.alan[e.name];
      }
    });
    if(window.alSecYaz && d.alanlar && d.alanlar.length) window.alSecYaz('alanlar',d.alanlar);
    /* Taslak yüklendikten sonra koşullu bölmeler yeniden çizilir.
       'select' ve 'textarea' değerleri doğrudan atanıyor ve atama
       change olayı üretmiyor: taslağı süren biri, dil sekmesini ve
       yapay zekâ bölmesini yanlış durumda buluyordu. */
    if(typeof dilAck==='function')dilAck();
    if(typeof yzCiz==='function')yzCiz();
    /* Yapılandırılmış öz de koşullu bir bölmedir: kutuları taslaktan
       doldurmak 'input' olayı üretmez ve taslağı süren kişi kutuları
       dolu ama özeti boş bulurdu. */
    if(typeof ozYapiCiz==='function')ozYapiCiz();
  }
  var eskiTaslak=taslakOku();
  if(eskiTaslak){
    var kutu=\$('shTaslak'); kutu.hidden=false;
    kutu.textContent=S.shTaslakVar+' ';
    var dSur=document.createElement('button');
    dSur.type='button'; dSur.className='d d-ikinci d-kucuk'; dSur.textContent=S.shTaslakSur;
    var dSil=document.createElement('button');
    dSil.type='button'; dSil.className='d d-sessiz d-kucuk'; dSil.textContent=S.shTaslakSil;
    kutu.appendChild(dSur); kutu.appendChild(dSil);
    dSur.addEventListener('click',function(){
      taslakYukle(eskiTaslak); kutu.textContent=S.shTaslakSurdu;
      goster(Math.min(eskiTaslak.adim||1,adimlar.length),true);
    });
    dSil.addEventListener('click',function(){ taslakSil(); kutu.textContent=S.shTaslakSildi; });
  }
  /* DÜZENLEYİCİDE YAZMAK DA TASLAĞI TETİKLER. Jodit gizli
     textarea'ya değer yazarken 'input' olayı doğurmuyor; formun
     input dinleyicisi bu yüzden makale metnini hiç duymuyordu ve
     taslak metinsiz kaydediliyordu. Düzenleyici kendi olayını zaten
     yayıyor (k/duzenleyici.php, 'kutadgu-duzenleyici'); burada ona
     kulak veriliyor — ikinci bir düzenek kurulmuyor. */
  document.addEventListener('kutadgu-duzenleyici',function(){
    if(taslakKapali)return;
    clearTimeout(taslakZaman); taslakZaman=setTimeout(taslakYaz,900);
  });
  form.addEventListener('input',function(){
    clearTimeout(taslakZaman); taslakZaman=setTimeout(taslakYaz,600);
  });
  form.addEventListener('change',function(){ taslakYaz(); });

  /* Çalışmanın dili İngilizce ise İngilizce başlık alanı gereksizdir.
     Gizlenmez, çünkü gizlenen alan kaybolmuş sayılır: yalnız
     açıklaması değişir ve alan boş bırakılabilir olduğunu söyler. */
  /* ---- ÇALIŞMANIN DİLİ / KÜNYE DİLİ SEKMELERİ ----
     Sekme yalnızca bir DÜZEN aracıdır: betiksiz tarayıcıda iki bölme de
     açık durur ve form aynen çalışır. Betik varken üç iş yapar:
       1. Birinci sekmenin adını seçilen dilin adına çevirir. "Çalışmanın
          dili" yazan bir sekme, hangi dilde yazdığını söylemiş birine
          hiçbir şey söylemez.
       2. Çalışmanın dili künye diliyle aynıysa ikinci sekmeyi
          büsbütün kaldırır: çevrilecek bir şey yok.
       3. Künye alanları doldurulmuşsa sekmede bir im gösterir; okur
          kapalı sekmede ne bıraktığını görmeden gönderemesin. */
  var dilKutu=\$('mDilSekme');
  function dilSekAc(hangi){
    if(!dilKutu)return;
    Array.prototype.forEach.call(dilKutu.querySelectorAll('.bv-dil-sk'),function(b){
      var s=b.getAttribute('data-bvdil')===hangi;
      b.classList.toggle('secili',s);
      b.setAttribute('aria-selected',s?'true':'false');
    });
    Array.prototype.forEach.call(dilKutu.querySelectorAll('.bv-dil-pnl'),function(p){
      p.classList.toggle('acik', p.id===(hangi==='ana'?'mDilAna':'mDilKunye'));
    });
  }
  function dilAck(){
    var d=\$('mDil');
    if(!d||!dilKutu)return;
    var kunye=dilKutu.getAttribute('data-kunye')||'';
    var ad=d.options[d.selectedIndex]?d.options[d.selectedIndex].text:'';
    var anaAd=\$('mDilAnaAd');
    if(anaAd&&ad)anaAd.textContent=ad;
    var skK=\$('mDilSkKunye');
    var ayni=(kunye!==''&&d.value===kunye);
    if(skK){
      skK.hidden=ayni;
      if(\$('mDilKunye'))\$('mDilKunye').hidden=ayni;
      if(ayni)dilSekAc('ana');
    }
    /* Tek sekme kaldıysa şerit de gerekmez: tek seçenekli bir seçim
       seçim değildir, gürültüdür. */
    var bar=dilKutu.querySelector('.bv-dil-bar');
    if(bar)bar.hidden=ayni;
    /* GENİŞLETİLMİŞ ÖZET yalnız diller FARKLIYKEN istenir. Çalışması
       künye dilinde olan birinden aynı metni iki kez istemek olurdu. */
    var gk=\$('mGenisKutu');
    if(gk){ gk.hidden = ayni || kunye===''; genisSay(); kunyeIm(); }
  }
  if(dilKutu){
    /* Gizleme ancak betik varken başlar: betiksiz tarayıcıda iki bölme
       de açık kalmalı, yoksa künye hiç yazılamaz. */
    dilKutu.classList.add('bv-dil-js');
    Array.prototype.forEach.call(dilKutu.querySelectorAll('.bv-dil-sk'),function(b){
      b.addEventListener('click',function(){ dilSekAc(b.getAttribute('data-bvdil')); });
    });
    function kunyeIm(){
      var b=\$('mBaslikEn'), o=\$('mOzetEn'), g=\$('mGenisOzet'), gk=\$('mGenisKutu'), sk=\$('mDilSkKunye');
      if(!sk)return;
      var dolu=(b&&b.value.trim()!=='')||(o&&o.value.trim()!=='')||(g&&g.value.trim()!=='');
      sk.classList.toggle('dolu',dolu);
      /* GEREKLİ AMA BOŞ ise sekme ayrıca uyarır. Kapalı bir sekmenin
         içinde zorunlu bir alan olduğunu, ileriye basıp geri
         fırlatıldığında öğrenmek kötü bir öğrenme yoludur. */
      var eksik = GENIS_ZOR && gk && !gk.hidden && g && g.value.replace(/\s+/g,' ').trim().split(' ').filter(Boolean).length < GENIS_AZ;
      sk.classList.toggle('eksik',!!eksik);
    }
    if(\$('mBaslikEn'))\$('mBaslikEn').addEventListener('input',kunyeIm);
    if(\$('mOzetEn'))\$('mOzetEn').addEventListener('input',kunyeIm);
    if(\$('mGenisOzet'))\$('mGenisOzet').addEventListener('input',kunyeIm);
    kunyeIm();
  }
  if(\$('mDil'))\$('mDil').addEventListener('change',dilAck);

  /* ---- GENİŞLETİLMİŞ ÖZET: SAYAÇ ----
     Eşik sunucudan geliyor; sayfaya elle yazılsaydı ayar değiştiği gün
     ikisi ayrı sayı söylerdi. Sayaç yazarken güncellenir: beş yüz
     kelimeyi gönderirken öğrenmek, yazarken öğrenmekten kötüdür. */
  function genisSay(){
    var t=\$('mGenisOzet'), c=\$('mGenisSay');
    if(!t||!c)return;
    var m=t.value.replace(/\s+/g,' ').trim();
    var n=m?m.split(' ').length:0;
    if(!n){ c.textContent=S.gozBos; c.className='ipucu'; return; }
    if(n<GENIS_AZ){ c.textContent=S.gozAz.replace('%1',n).replace('%2',GENIS_AZ); c.className='ipucu hata'; }
    else { c.textContent=S.gozTamam.replace('%1',n).replace('%2',GENIS_HEDEF); c.className='ipucu'; }
  }
  if(\$('mGenisOzet'))\$('mGenisOzet').addEventListener('input',genisSay);
  genisSay();

  dilAck();

  /* GİRİŞSİZ AMA DAHA ÖNCE OKUMUŞ OKUYUCU. Sunucu bu kişiyi tanımaz
     (tanımaması da doğrudur); tarayıcısı tanır. Adres açıkça bir adım
     istemediyse ve koşul metni o günden bu yana değişmediyse, koşul
     adımı atlanır. Adım silinmez: şeritte durur, açılır, notunu taşır. */
  if(!/[?&]adim=/.test(location.search) && simdi===1 && kosulOkunduMu() && adimlar.length>1){
    var not=document.querySelector('#f-kosul .kosul-not');
    if(not) not.hidden=false;
    simdi=2; enBuyuk=2;
  }
  goster(simdi,false);

  /* =================================================================
     GÖNDER
     -----------------------------------------------------------------
     Betik varsa gönderim yine fetch ile yapılır (yanıt sayfayı
     değiştirmeden gösterilir). Betik yoksa bu dinleyici hiç
     bağlanmamıştır ve tarayıcı formu kendisi POST eder; uç "bicim=form"
     görüp sayfaya yönlendirir.
     ================================================================= */
  form.addEventListener('submit',function(ev){
    ev.preventDefault();
    /* Baştan sona bütün adımlar. Bir adım kalırsa oraya götürülür:
       hata mesajı, hatanın bulunduğu ekranda görünmelidir. */
    for(var i=1;i<=adimlar.length;i++){
      var f=ADIM_DEN[adimAnahtar(i)];
      if(!f)continue;
      var h=f();
      if(h){ goster(i,false); return hata(h[0],h[1]); }
    }
    var ad=\$('ad').value.trim(), eposta=\$('eposta').value.trim(), unvan=\$('unvan').value.trim();
    var bOrcid=orcidTemiz(\$('orcid').value), bScopus='';
    var y=yazarlariTopla();
    if(y.hata)return hata(y.hata[0],y.hata[1]);
    var yzEl=document.querySelector('input[name=yz_kullanim]:checked');
    var etEl=document.querySelector('input[name=etik_durum]:checked');
    var vrEl=document.querySelector('input[name=veri_beyan]:checked');

    \$('gonder').disabled=true; m.textContent=S.gonderiliyor; m.className='form-msj';
    fetch(form.getAttribute('action'),{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      unvan:unvan,ad:ad,eposta:eposta,kurum:\$('kurum').value.trim(),orcid:bOrcid,scopus:bScopus,web:dg('web').trim(),
      kefiller:y.bKefil.liste,
      yazar_tam:(document.querySelector('input[name=yazar_tam]:checked')||{}).value||'',
      makale_dil:\$('mDil')?\$('mDil').value:'',
      makale_baslik:\$('mBaslik').value.trim(),
      makale_baslik_en:\$('mBaslikEn')?\$('mBaslikEn').value.trim():'',
      makale_ozet:\$('mOzet').value.trim(),
      /* Yapı da GÖNDERİLİR, ama özeti yapan o değildir: sunucu yapıdan
         kendi özetini kurar. Tarayıcının birleştirdiği dizeye
         güvenilseydi, yapı ile özet birbirini tutmayan bir kayıt
         doğardı ve hangisinin doğru olduğunun yanıtı olmazdı. */
      ozet_yapi:ozYapiTopla(),
      /* Tam metin ve kaynakça: değer düzenleyiciden okunur, gizli
         textarea'nın son yazımını beklemeden. */
      makale_metin:(window.kdOku ? window.kdOku('bvMetin') : dg('bvMetin')),
      makale_kaynakca:(window.kdOku ? window.kdOku('bvKaynakca') : dg('bvKaynakca')),
      makale_ozet_en:\$('mOzetEn')?\$('mOzetEn').value.trim():'',
      makale_genis_ozet_en:(\$('mGenisOzet')&&\$('mGenisKutu')&&!\$('mGenisKutu').hidden)?\$('mGenisOzet').value.trim():'',
      alanlar:(window.alSecOku?window.alSecOku('alanlar'):[]),
      telif_kabul:true,kosullar_okundu:true,yazarlar:y.liste,
      intihal_arac:dg('intArac'),intihal_oran:parseFloat(dg('intOran')),
      intihal_tek_kaynak:parseFloat(dg('intTek')),intihal_link:dg('intLink').trim(),
      /* GERÇEK DEĞER GÖNDERİLİR. Eskiden burada sabit 'true' vardı:
         uçtaki denetim hiçbir zaman işlemiyordu, yani sistem
         uygulamadığı bir kuralı duyuruyordu. Kullanım yoksa uç kutuyu
         zaten aramaz; varsa kutu gerçekten işaretlenmiş olmalıdır. */
      /* Beyanlar: hepsi çalışmayla birlikte yayımlanır (editör notu
         hariç; o yalnız editöre gider). */
      cikar_catismasi:(document.querySelector('input[name=cikar_catismasi]:checked')||{}).value||'',
      cikar_aciklama:dg('ccAciklama').trim(),
      tek_gonderim:\$('tekGonderim').checked,
      fon_durum:(document.querySelector('input[name=fon_durum]:checked')||{}).value||'',
      fon_kaynak:dg('fonKaynak').trim(),
      editor_notu:dg('editorNotu').trim(),
      yz_kullanim:yzEl.value,yz_aciklama:\$('yzAciklama').value.trim(),
      yz_etik_kabul:(yzEl.value==='yok')?false:\$('yzEtik').checked,
      etik_durum:etEl.value,etik_kurul:dg('etikKurul').trim(),etik_tarih:dg('etikTarih').trim(),
      etik_no:dg('etikNo').trim(),etik_belge:dg('etikBelge').trim(),
      veri_beyan:vrEl.value,veri_url:\$('veriUrl').value.trim(),veri_gerekce:\$('veriGerekce').value.trim(),
      sekil_link:\$('sekilLink').value.trim(),veri_link:\$('veriLink').value.trim(),
      analiz_arac:\$('analizArac').value.trim(),analiz_kod:\$('analizKod').value.trim(),
      hakem_oneri:onerileriTopla()
    })}).then(function(r){return r.json();}).then(function(d){
      if(d&&d.ok){
        taslakKapali=true; taslakSil();     /* gönderilen taslak artık taslak değil */
        kosulOkunduYaz();                   /* koşulları okuduğu bu tarayıcıda da bilinsin */
        \$('formKap').classList.add('gizli');
        \$('shSerit').hidden=true; \$('shGez').hidden=true; \$('shTaslak').hidden=true;
        \$('bittiMsj').textContent=(d.mesaj||S.alindi);
        \$('bitti').classList.remove('gizli');
        window.scrollTo({top:0,behavior:'smooth'});
      } else { hata((d&&d.hata)||S.gonderilemedi); \$('gonder').disabled=false; }
    }).catch(function(){ hata(S.baglanti); \$('gonder').disabled=false; });
  });
})();
</script>
JS;

/* Düzenleyici betiği FORM BETİĞİNDEN SONRA yüklenir: kd_betik kendi
   <script> etiketlerini üretir ve textarea'yı Jodit'e çevirir. 'tam'
   takımı yazma ekranındakiyle aynıdır (kd_araclar). En az yükseklik
   420 piksel — yazma ekranındaki değerle aynı; iki yerde iki ayrı
   yükseklik, aynı işin iki ayrı boyda görünmesi olurdu. */
k_son($betik . k_istem_betik(['yzAc' => 'yzOv', 'yzAc2' => 'yzOv', 'cvAc' => 'cvOv'])
    . kd_betik('bvMetin', 'tam', 420) . kd_betik('bvKaynakca', 'rapor', 180));
