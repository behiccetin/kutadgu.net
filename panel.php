<?php
/* =====================================================================
   KUTADGU - Kullanıcı paneli / User panel
   Herkesin kendi hesabı, kendi rolleri, kendi belgesi ve kendi işleri.
   Editörler için hakem atama ve belge onayı bölümleri de buradadır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';
require_once __DIR__ . '/k/hesap.php';
require_once __DIR__ . '/k/endeks.php';
require_once __DIR__ . '/k/orcid.php';
require_once __DIR__ . '/k/alan_secim.php';

$en = k_en();
$UNVAN = hs_unvanlar();   /* [anahtar => o dildeki ad] */
$ALAN = [];
foreach (kt_alanlar() as $ak => $av) { $ALAN[$ak] = k_t(['tr' => $av[0], 'en' => $av[1]]); }

/* ---- BU SAYFAYI KİM AÇTI? ----
   Yönetim bölümü sunucuda basılır, betikle gizlenmez. Bir bölümü
   ekrandan kaldırmak onu kaynaktan kaldırmaz; kaynakta duran işaretleme
   okunabilir. Baş editör değilse ne sekme ne bölüm sayfaya konur:
   olmayan bir sekme kazayla açılamaz.

   Bu, yetki denetiminin kendisi değildir. Uçlar ayrıca kendi başına
   denetlenir (api/index.php: yonetim_yazma_gerek, yonetim_okuma_gerek).
   Gizlemek bir kolaylıktır, denetim sunucudadır.

   The management section is rendered on the server, not hidden by
   script. If the visitor is not a chief editor, neither the tab nor the
   section is placed on the page at all. This is not the authority check
   itself; the endpoints guard themselves. */
$hsBen    = function_exists('hs_oturum') ? hs_oturum() : null;
$edYetki  = $hsBen !== null && hs_editor_mu($hsBen);
$basYetki = $hsBen !== null && hs_bas_yetki($hsBen);
/* KURUCU BAŞ EDİTÖR MÜ. Kurul kararı, 15 Ağustos 2026: hakem daveti
   WhatsApp'tan da gönderilebilsin, "kurucu baş editörler için geçerli
   bu sadece". Baş editörlük yetmez: kurucu, sistemin kendisiyle birlikte
   anılan ve numarası tanınan kişidir; bir bağlantıyı kendi numarasından
   göndermesi tanıdıktır. Sonradan atanan bir baş editörün numarasından
   gelen bağlantı, hakemin tanımadığı bir yerden gelen bağlantıdır.
   Soru tek kaynaktan sorulur (hs_kurucu_mu), burada yeniden yazılmaz. */
$kurucuYetki = $hsBen !== null && function_exists('hs_kurucu_mu')
            && hs_kurucu_mu((string)($hsBen['eposta'] ?? ''));

$ekBas = <<<CSS
<meta name="robots" content="noindex, nofollow">
<style>
/* =====================================================================
   PANEL · sayfaya özgü biçim
   ---------------------------------------------------------------------
   Buradaki kuralların hepsi yalnızca bu sayfada karşılığı olan
   bileşenlere aittir: gösterge sayaçları, iş satırları, hakem havuzu
   listesi, davet kutusu. Düğme, form alanı, onay kutusu, sekme çubuğu,
   kart, rozet ve düzen kuralları burada YOKTUR; hepsi k/kutadgu.css
   içindeki dizgeden gelir. Bir kural yazmadan önce sorulacak soru:
   bu yalnızca panelde mi var? Değilse yeri bu dosya değildir.
   ===================================================================== */

/* Panel bir okuma sayfası değil, form ve liste sayfasıdır: sütun
   dizgedeki "geniş" ölçüye bağlıdır, sayfaya özel bir sayı yazılmaz.
   Paragraflar yine okunur genişlikte kalır. */
/* PANEL GENİŞLİĞİ.
   Burada sabit bir üst sınır (--en-metin-genis, 1240px) yazılıydı ve
   sayfanın ölçü kipini büsbütün geçersiz kılıyordu: 1920 piksellik bir
   ekranda sahne 1670 piksel genişken panel 1240'ta kalıyor, sağında
   430 piksel boşluk duruyordu. Ad, rozetler ve altı sütunlu çizelgeler
   o dar şeride sıkışıyordu.

   Sınır artık sayfanın kendi ölçüsünden gelir (--en-metin); panel
   'pano' kipinde açılıyor ve o kipin sınırı 1600 pikseldir. Böylece
   genişlik iki yerde değil tek yerde tanımlı: bir sayfanın ne kadar
   geniş olacağı, ölçü kipinin işidir. */
.pn{min-width:0;max-width:var(--en-metin);margin-inline:auto}
/* Panel metni de kartını doldurur. Buradaki 74 karakterlik sınır,
   kartın kendisi ondan geniş olduğu için her açıklamanın sağında bir
   boşluk bırakıyordu. Satırı sınırlayan tek şey kartın genişliğidir;
   dizgede olduğu gibi burada da iki sınır aynı anda durmaz. */
.pn-ack{font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-genis);margin:6px 0 0}
/* WhatsApp devir kutusu: numara alanı ile düğme aynı satırda, açıklama
   altta. Yalnız kurucu baş editörün ekranında çizilir. */
.pn-wa{margin-top:10px;display:flex;flex-wrap:wrap;gap:8px;align-items:center}
.pn-wa input{flex:1 1 220px;min-width:0;margin:0}
.pn-wa .pn-ack{flex:1 1 100%;margin:2px 0 0}
.pn-pnl{display:none}
.pn-pnl.acik{display:block}

/* ---- SEKME İÇİ DÜZEN ----
   Bir sekmenin kartları alt alta dizildiğinde sayfa bir yığına dönüyor:
   Editör sekmesi 5531, Hesap sekmesi 3273 piksel uzunluğundaydı ve
   ikisinde de kısa kartlar bir devin altında kayboluyordu. Ölçüldü:
   Editör sekmesindeki beş karttan dördü 200 ile 600 piksel arasında,
   biri 4258 piksel.

   Çözüm kartları küçültmek değil, kısa olanları yan yana koymaktır.
   Geniş ekranda sekme iki sütunlu bir ızgaradır; uzun kart '.pn-tam'
   ile iki sütunu birden kaplar. 'dense' yerleştirme, uzun kartın
   açtığı boşluğu bir sonraki kısa kartla doldurur; yoksa uzun kartın
   yanında yarım satırlık bir boşluk kalırdı. */
/* ON İKİ SÜTUN VE EŞİT BOY.
   İki eşit sütun ve 'align-items:start' ile kısa kart kendi boyunda
   kalıyor, yanındaki uzun kartın hizasına kadar SAYFA ZEMİNİ
   görünüyordu: bildirilen "kutu gibi kalmış yer" buydu. Boşluğun
   kendisi kusur değil, boşluğun KARTIN DIŞINDA kalması kusurdu; kart
   içindeki boşluk nefes, kart dışındaki delik gibi okunur.

   On iki sütun, ikiden fazla oranı mümkün kılar (8+4, 7+5, 6+6) ve
   kartlar satırın boyuna uzar. Çizelge ve liste kartın içinde üstte
   toplanır, artan yer tek parça kalır. */
@media(min-width:1000px){
  .pn-pnl.acik{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));
    gap:var(--b-4);align-items:stretch;grid-auto-flow:row dense}
  .pn-pnl.acik > .pn-kart{margin-bottom:0;grid-column:span 6;
    display:flex;flex-direction:column}
  /* Kapaklı kart ızgarada da <details> kalır: display:flex verilirse
     tarayıcı kapağı çizemez ve kapalı kart açık görünür. */
  .pn-pnl.acik > .pn-katla{display:block}
  .pn-pnl.acik > .pn-kart > .tablo-sar,
  .pn-pnl.acik > .pn-kart > .pn-liste{flex:1 1 auto;align-content:start}
  /* Sekmenin başlığı, sayaç şeridi, iki sütunlu özet düzeni ve uzun
     kartlar satırın tamamını alır. */
  /* .pn-bas2 BURAYA YAZILMAZSA SEKME BAŞLIĞI TEK SÜTUNA SIKIŞIR ve
     "Editör" sözcüğü iki satıra bölünerek okunmaz hâle gelir. Ölçüldü:
     başlık 96 piksel genişlikte çizildi. Izgaranın her doğrudan çocuğu
     bir hücredir; sütun genişliği yazılmayan çocuk bir sütun alır. */
  .pn-pnl.acik > .pn-bas2,
  .pn-pnl.acik > .pn-tam,
  .pn-pnl.acik > .pn-blk,
  .pn-pnl.acik > .pn-sayac,
  .pn-pnl.acik > .pn-yol,
  .pn-pnl.acik > .pn-ikili-duzen{grid-column:1/-1}
  /* Dört ölçü kutusu ve başlıklar satırın tamamını alır. */
  /* KAPALI BİR KAPAK KOCA BİR SATIR İSTEMEZ.
     Bildirilen kusur: "gizleme kısmı için koca satır ayrılmış gibi".
     Ölçüldü ve doğruydu — ama sebebi kapağın YÜKSEKLİĞİ değil
     GENİŞLİĞİydi: kapaklı kart .pn-tam taşıdığı için, iki satır bile
     olmayan kapalı bir başlık 1440 pikselde satırın tamamını (1240px)
     alıyordu ve yanına hiçbir şey gelemiyordu. İki kapaklı kart alt
     alta 61+61 piksel ve iki tam satır tutuyordu.

     Kapalıyken kapak yarım sütunda durur, iki kapak yan yana gelir;
     AÇILDIĞINDA satırın tamamını alır, çünkü açılan şey (sunucu
     durumu çizelgesi, deneme düzeni) gerçekten geniştir. Ölçü
     kartın adına değil DURUMUNA bağlıdır, yani bir kapak eklendiğinde
     buraya bir şey yazmak gerekmez. */
  /* Sekmede tek kart varsa yarım sütunda durmasının anlamı yoktur:
     yanında eşleşecek bir şey yok, sağı boş kalır. */
  .pn-pnl.acik > .pn-kart:only-child{grid-column:1/-1}
  /* Tek başına kalan son kart da satırı doldurur: yanında eşleşecek
     bir kart yoksa yarım sütunda durmasının anlamı yok. */
  .pn-pnl.acik > .pn-kart:last-child:nth-child(odd){grid-column:1/-1}
  /* En sonda duruyorlar çünkü üstteki iki kural (.pn-tam ve
     :last-child:nth-child(odd)) da tam satır veriyor ve kapaklı kart
     ikisine de uyuyor. Ölçüt DURUMdur: kapalıysa yarım, açıksa tam.

     '.pn-kart' seçiciye AYRICA yazıldı ve bu bir süs değil bir ölçüm
     sonucudur: ilk yazımda kural '.pn-pnl.acik > .pn-katla:not([open])'
     idi ve sunucu durumu kartı 1075 piksel ölçüldü, yani hiç
     değişmedi. Sebep, :last-child:nth-child(odd) kuralının bir sınıf
     bir sözde-sınıf daha taşıması, yani daha ÖZGÜL olmasıydı. */
  .pn-pnl.acik > .pn-kart.pn-katla:not([open]){grid-column:span 6}
  .pn-pnl.acik > .pn-kart.pn-katla[open]{grid-column:1/-1}
}

/* ---- SAYI ŞERİDİ ----
   Sekme çubuğunun üstünde duran, adıyla birlikte sayı gösteren kutular.
   Kutu bir düğmedir, çünkü yaptığı şey sayfa değiştirmek değil sekme
   açmaktır; <a href="#..."> yazılsaydı tarayıcı geçmişi her bakışta
   bir adım şişerdi.

   Izgara auto-fit'tir: bir tek kutu varsa satırı doldurmaz, 220 piksel
   kalır ve yanı boş durur — dört kutu için kurulmuş bir düzen tek
   kutuda dev bir levhaya dönüşmesin diye. */
/* IZGARA DEĞİL ESNEK SIRA, ve bu bir ölçüm sonucudur: ızgara
   'repeat(auto-fit,minmax(200px,220px))' iken 1200 piksellik ekranda
   dört kutunun üçü bir satıra giriyor, dördüncüsü tek başına alt
   satırda kalıyordu. Sebep, sütunun 220 pikselle sınırlanmasıydı.

   Esnek sırada kutular satırı paylaşır (flex:1) ve tek kutu kalırsa
   320 pikselde durur; yani ne dördüncü kutu yalnız kalır ne de tek
   kutu satır boyunca uzayan bir levhaya döner. */
.pn-serit{display:flex;flex-wrap:wrap;gap:var(--b-3);margin-bottom:var(--b-4)}
.pn-serit > .pn-kutu{flex:1 1 180px;max-width:320px}
/* Sayının boyu MERDİVENDEN gelir (--y-7), elle yazılmış bir rem
   değerinden değil: bu dizgede boy kararı bir kez verilir, sayfa
   yalnız hangi basamağı kullandığını söyler. İlk yazımda 1.6rem
   yazmıştım; yazi-tipi-kapi böyle bir değeri "yalnız bu sayfada
   geçerli bir karar" sayar ve haklıdır. */
/* Sol kenardaki vurgu çizgisi bir süs değil bir AYIRIMDIR: hemen
   altındaki "sistemin nabzı" kutuları da sayı gösteriyor ve ikisi
   karışabilir. Nabız sistemin durumudur ve okunur; şerit SİZİN
   işinizdir ve basılır. Çizgi, basılabilir olanı işaretler. */
.pn-kutu{background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-3);
  border-inline-start:3px solid var(--kut);
  padding:var(--b-3) var(--b-4);display:flex;flex-direction:column;gap:2px;
  text-align:start;font:inherit;cursor:pointer;min-height:var(--hedef);min-width:0}
/* DAR EKRANDA ŞERİT YAN KAYAR, ALT ALTA DİZİLMEZ.
   Ölçüldü: 320 pikselde dört kutu alt alta 347 piksel tutuyordu, yani
   sekme çubuğuna varmadan önce bir ekran boyu kaydırmak gerekiyordu.
   Bir özet, özetlediği şeyden uzun olamaz. Şerit artık sekme çubuğuyla
   aynı deyimi kullanır: yana kayan bir sıra. Kaydırma çubuğu gizlenir,
   çünkü kutular zaten kenardan kırpılarak devamının olduğunu söyler. */
@media(max-width:699px){
  .pn-serit{flex-wrap:nowrap;overflow-x:auto;scrollbar-width:none;
    scroll-snap-type:x proximity;padding-bottom:2px}
  .pn-serit > .pn-kutu{flex:0 0 min(170px,60%);max-width:none;scroll-snap-align:start}
  .pn-serit::-webkit-scrollbar{display:none}
}
.pn-kutu:hover,.pn-kutu:focus-visible{border-color:var(--kut);background:var(--kut-zemin)}
.pn-kutu-n{font-family:var(--serif);font-size:var(--y-7);line-height:1.1;
  font-variant-numeric:tabular-nums;color:var(--kut)}
.pn-kutu-a{font-size:var(--y-2);color:var(--metin-2);line-height:1.35;overflow-wrap:anywhere}

/* Sekme içi yol göstericisi: o sekmede hangi kartlar var, hangi sırada.
   Beş kartlı bir sekmede "neyin nerede olduğu" sorusunun cevabı
   sayfanın en üstünde durmalıdır, kaydırarak aranmamalıdır. Listeyi
   betik kartların kendi başlıklarından kurar; yeni bir kart
   eklendiğinde buraya bir şey yazmak gerekmez. */
/* SEKME BAŞLIĞI. Sekme çubuğu yapışkan ve dar ekranda yana kayıyor;
   nerede olunduğunu söyleyen bir başlık sayfada her zaman durmalı. */
.pn-bas2{margin:0 0 var(--b-4)}
.pn-bas2 h2{font-family:var(--serif);font-size:var(--y-7);margin:0 0 4px;line-height:1.15}
.pn-bas2 p{margin:0;color:var(--metin-2);font-size:var(--y-3);line-height:var(--sh-orta)}

/* Çip şeridi. Artık bağlantı değil DÜĞME: yaptığı şey bir yere gitmek
   değil, hangi kartın görüneceğini seçmek. <a href="#..."> yazılsaydı
   her seçim tarayıcı geçmişine bir adım eklerdi. */
.pn-yol{display:flex;flex-wrap:wrap;gap:var(--b-2);margin-bottom:var(--b-4)}
.pn-yol button{font:inherit;font-size:var(--y-2);font-weight:600;color:var(--metin-2);
  border:1px solid var(--cizgi);border-radius:var(--r-tam);padding:7px 14px;
  background:var(--yuzey);line-height:1.2;cursor:pointer;min-height:0;
  display:inline-flex;align-items:center;gap:7px}
.pn-yol button:hover{border-color:var(--kut);color:var(--kut);background:var(--kut-zemin)}
.pn-yol button.acik{border-color:var(--kut);background:var(--kut-dolu);
  color:var(--kut-dolu-metin)}
.pn-yol button.acik .sek-say{background:var(--kut-dolu-metin);color:var(--kut-dolu)}
/* Seçili olmayan kart sayfadan KALDIRILMAZ, gizlenir: kaldırılsaydı
   içindeki form alanları da giderdi ve yazılmış bir değer kaybolurdu.

   ÜÇ KEZ YAZILDI ve sebebi ölçüldü: ızgara kuralları .pn-pnl.acik'in
   altında karta 'display:flex' / 'display:block' veriyor ve bunlar üç
   sınıflık seçicilerdir. Tek sınıflık bir 'display:none' onlara yenilir;
   ilk yazımda Editör sekmesi 4032'den 5308 piksele ÇIKTI, çünkü hiçbir
   kart gizlenmedi — kural yazılmıştı ama uygulanmıyordu. (OKUBENI 79'un
   aynısı.) */
.pn-kart-kapali{display:none}
.pn-pnl.acik > .pn-kart.pn-kart-kapali{display:none}
.pn-pnl.acik > .pn-kart.pn-kart-secili{grid-column:1/-1}
.pn-pnl.acik > .pn-kart.pn-katla.pn-kart-kapali{display:none}
/* ÇİZELGEDE ÇALIŞMA BAŞLIĞI TAŞIYAN HÜCRE.
   Okuma çizelgeleri altı sütunlu; kart yarım sütundayken başlık
   hücresine 90 piksel kalıyor ve uzun bir makale adı SATIRA BİR SÖZCÜK
   düşerek on beş satır boyunca aşağı uzuyordu. Ekran ne kadar geniş
   olursa olsun değişmiyordu, çünkü daralan ekran değil hücreydi.

   İLK DÜZELTME EKSİKTİ VE NEDEN EKSİK OLDUĞU BURAYA YAZILIYOR:
   ölçüt "td:first-child" idi, yani SÜTUNUN YERİNE bağlıydı. "Çalışma
   başına" çizelgesinde başlık ilk sütundur ve düzeldi; "Son okuma
   kayıtları" çizelgesinde ise ilk sütun ZAMAN, başlık ikincidir ve
   orada hiçbir şey değişmedi. Bir kusuru yerine göre yamamak, aynı
   kusurun ikinci örneğini görmezden gelmektir.

   Ölçüt artık yere değil İÇERİĞE bağlı: başlık taşıyan hücre
   .tb-ad sınıfını alır, kaçıncı sütunda olduğu önemsizdir. Sığmayan
   başlık üç satırda kırpılır; tamamı title özniteliğinde durur, yani
   bilgi kaybolmaz. */
.pn-kart table.tb td.tb-ad{min-width:20ch;max-width:44ch}
.pn-kart table.tb td.tb-ad > span.tb-bas{display:-webkit-box;-webkit-line-clamp:3;
  -webkit-box-orient:vertical;overflow:hidden}
.pn-kart .tablo-sar table.tb{min-width:640px}
.pn-kart{scroll-margin-top:calc(var(--ust) + 16px)}
/* Hakemliğe açma düğmesi, "çalışmayı düzenle" bağlantısıyla aynı yerde
   ve aynı ölçüde durur: ikisi de o kartın eylemidir. */
button.pn-duzenle{background:none;border:0;font:inherit;cursor:pointer;padding:0}
button.pn-duzenle:disabled{opacity:.55;cursor:default}

/* Geniş ekranda kartlar yan yana: aşağı doğru uzamayı kesen asıl şey budur */
.pn-ikili-duzen{display:grid;gap:var(--b-4);align-items:start}
@media(min-width:1000px){.pn-ikili-duzen{grid-template-columns:minmax(0,1.2fr) minmax(0,.8fr)}}
.pn-sut{display:grid;gap:var(--b-4);align-content:start;min-width:0}
.pn-sut .pn-kart{margin-bottom:0}

/* Panel kartı. Sistem kartından farkı: gölgesi yoktur ve alt alta
   dizilir; panelde on beş kart yan yana durur, hepsi gölgeliyse sayfa
   kabarcıklı görünür. */
.pn-kart{background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-3);
  padding:clamp(18px,2.4vw,26px);margin-bottom:var(--b-4)}
.pn-kart h2{font-size:var(--y-6);margin:0 0 6px;display:flex;align-items:baseline;gap:10px;flex-wrap:wrap}
.pn-kart > .pn-ack:first-of-type{margin-bottom:var(--b-3)}
.pn-bag-dg{margin-left:auto;background:transparent;border:0;color:var(--kut);font:inherit;
  font-family:var(--ui);font-size:var(--y-2);font-weight:600;cursor:pointer;padding:0;min-height:0}
.pn-bag-dg:hover{text-decoration:underline}
.pn-kisa{display:grid;gap:var(--b-2)}
.pn-kisa .pn-is{margin-top:0}

/* ---- Kapaklı kart ----
   Basılmadan hiçbir şey göstermeyen kart (sunucu durumu) kapalı
   durur. Kapak kapalıyken kart iki satırdır: adı ve ne işe yaradığı.
   Izgarada da tam satır ister: yarım sütunda duran iki satırlık bir
   kart, yanındaki uzun kartın altında kutu gibi bir boşluk bırakır. */
.pn-katla{padding:0}
.pn-katla > summary{list-style:none;cursor:pointer;display:flex;flex-wrap:wrap;
  align-items:baseline;gap:var(--b-2) var(--b-3);padding:clamp(14px,1.8vw,18px) clamp(18px,2.4vw,26px);
  min-height:var(--hedef)}
.pn-katla > summary::-webkit-details-marker{display:none}
.pn-katla > summary::after{content:"";margin-left:auto;width:9px;height:9px;flex:none;
  border-right:2px solid var(--metin-3);border-bottom:2px solid var(--metin-3);
  transform:rotate(45deg) translateY(-2px);transition:transform .15s ease}
.pn-katla[open] > summary::after{transform:rotate(-135deg) translateY(-2px)}
.pn-katla > summary:hover{background:var(--yuzey-2)}
.pn-katla > summary h2{margin:0}
.pn-katla > summary span{color:var(--metin-2);font-size:var(--y-3)}
.pn-katla-ic{padding:0 clamp(18px,2.4vw,26px) clamp(18px,2.4vw,26px)}
/* Bir kartın içindeki ikinci iş: aynı kart, ayrı konu. Yeni bir kart
   açılsaydı Yönetim sekmesi bir kart daha uzardı; ayraç, konunun
   değiştiğini kartı bölmeden söyler. */
/* BİLDİRİMDEN GELEN KART BELLİ OLUR. Vurgu iki katmanlıdır: renkli bir
   çerçeve (bilgi) ve sönen bir parıltı (dikkat). Hareket istemeyene
   yalnız çerçeve kalır; bilgi kaybolmaz, hareket kaybolur. */
.pn-vurgu-ac{outline:2px solid var(--kut);outline-offset:3px;border-radius:var(--r-3);
  animation:pnVurgu 2.6s ease-out 1}
@keyframes pnVurgu{
  0%{box-shadow:0 0 0 0 rgba(191,149,63,.55)}
  30%{box-shadow:0 0 0 10px rgba(191,149,63,0)}
  100%{box-shadow:0 0 0 0 rgba(191,149,63,0)}
}
@media (prefers-reduced-motion:reduce){ .pn-vurgu-ac{animation:none} }
.pn-alt-blok{margin-top:var(--b-4);padding-top:var(--b-3);border-top:1px solid var(--cizgi)}
.pn-alt-blok h3{margin:0 0 var(--b-2)}
.pn-is-sat{border:1px solid var(--cizgi);border-radius:var(--r-2);padding:var(--b-2);margin-top:var(--b-2)}
.pn-is-sat b{display:block}
.pn-is-sat span{display:block;font-size:var(--y-2);color:var(--metin-2)}
/* Zenodo önizlemesi: gönderilecek üstverinin kendisi. Okunur kalsın
   diye kendi kutusunda ve kendi kaydırmasıyla durur; sayfayı
   genişletmez (yatay taşma bu depoda ölçülen bir kusurdur). */
.zn-onz{max-height:280px;overflow:auto;background:var(--yuzey-2);border:1px solid var(--cizgi);
  border-radius:var(--r-2);padding:var(--b-2);font-size:var(--y-2);white-space:pre-wrap;word-break:break-word;margin:var(--b-2) 0 0}

/* Giriş ve kayıt kutusu */
.pn-kapi{max-width:460px;margin-inline:auto;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:var(--r-4);padding:clamp(22px,3vw,32px);box-shadow:var(--g-2)}


/* ---- Panel başlığı ----
   Kurumsal lacivert şerit (dizgedeki .marka-serit). Buradaki kurallar
   yalnızca şeridin içindeki üç parçayı dizer: baş harf dairesi, ad
   bloğu ve düğmeler. */
.pn-bas{display:flex;flex-wrap:wrap;gap:var(--b-4);align-items:flex-start;
  margin-bottom:var(--b-5)}
.pn-bas-yazi{min-width:0}
.pn-bas-dg{margin-left:auto;display:flex;gap:var(--b-2);flex-wrap:wrap}
.pn-bas h1{font-family:var(--baslik);font-size:var(--y-8);line-height:1.15;margin:0 0 8px}
.pn-bas-alt{display:flex;gap:var(--b-3);align-items:center;flex-wrap:wrap}
.pn-bas .eposta{color:var(--metin-2);font-size:var(--y-3)}

/* ---- Sekme çubuğu: kart kenarı ----
   Düz alt çizgili sekme şeridi sayfanın geri kalanına bağlanmıyordu.
   Etkin sekme artık kartla aynı yüzeye oturur ve üstünde vurgu çizgisi
   taşır; şerit bir çubuk değil, altındaki alanın kenarı olur. Yalnız
   panelde kullanılır: .sek-bar başka sayfalarda da var ve onların
   ölçülmüş düzeni değişmemeli. */
.sek-bar-kart{gap:4px}
.sek-bar-kart button{min-height:44px;border:1px solid transparent;border-bottom:0;
  border-radius:var(--r-2) var(--r-2) 0 0;position:relative;top:1px}
.sek-bar-kart button:hover{background:var(--yuzey-2)}
.sek-bar-kart button[aria-selected="true"],.sek-bar-kart button.acik{
  background:var(--yuzey);border-color:var(--cizgi);color:var(--metin);
  box-shadow:0 -2px 0 var(--kut) inset}

/* Bölüm başlığı: panelin hangi bölümüne bakıldığı yazılı olsun.
   Sayılar başlıksız durunca ne oldukları anlaşılmıyordu. */
.pn-blk{font-size:var(--y-4);margin:0 0 10px;display:flex;flex-wrap:wrap;gap:4px 10px;align-items:baseline}
.pn-blk span{font-family:var(--ui);font-size:var(--y-2);font-weight:400;color:var(--metin-2);
  line-height:var(--sh-orta)}

/* ---- Nabız kutuları ----
   Gösterge tablosunun üst satırı. İm solda, sayı büyük, etiket altında:
   bir bakışta okunur, göz sayıdan sayıya yatay gider. */
/* ---- DENEME AKIŞI MERDİVENİ ----
   Sıra bir listedir: <ol>. Numarayı CSS üretmez, tarayıcının kendi
   sayacı üretir; betiksiz tarayıcıda da sıra görünür.
   ÜÇ HÂL ÜÇ AYRI ŞEY SÖYLER ve renk tek başına taşımaz: geçilmiş adımın
   önünde imi, şimdiki adımın kalın çerçevesi vardır. Renk körü bir
   okurun "şu an neredeyim" sorusuna yanıtı renkten gelmez. */
.dn-merdiven{margin:var(--b-3) 0 0;padding-left:var(--b-5);display:grid;gap:var(--b-2)}
.dn-bs{padding:var(--b-2) var(--b-3);border:1px solid var(--cizgi);border-radius:var(--r-2)}
.dn-bs b{display:block;font-size:var(--y-3)}
.dn-bs span{display:block;font-size:var(--y-2);color:var(--metin-2);margin-top:2px;line-height:var(--sh-orta)}
.dn-oldu{opacity:.62}
.dn-oldu b::after{content:' \2713';color:var(--yesil)}
.dn-simdi{border-width:2px;border-color:var(--kut);background:var(--yuzey-2)}
.dn-sonra{border-style:dashed}
/* Deneme şifreleri gerçek şifre değildir ama yine de seçilebilir
   durmalı: kişi onları kopyalayıp başka bir tarayıcıda deneyecek. */
.dn-sifre{font-family:var(--mono);word-break:break-all}
/* ---- KURUL KARARI KAYDI ----
   Karar metni bir alıntı gibi durur: kenarında çizgi, normal ağırlıkta.
   Oylar kararın altında, her biri adı ve gerekçesiyle — gerekçesiz oyu
   ekranda göstermek de mümkün değil, çünkü uç onu hiç kabul etmiyor. */
/* Gidecek iletinin metni olduğu gibi görünür: satır sonları korunur,
   tek aralıklı yazılır. Bir iletiyi "yaklaşık" göstermek, onu hiç
   göstermemekten farksızdır. */
.bs-ileti{white-space:pre-wrap;font-family:var(--mono);font-size:var(--y-2);
  background:var(--yuzey-2);border:1px solid var(--cizgi);border-radius:var(--r-2);
  padding:var(--b-3);margin:var(--b-2) 0;overflow-x:auto}
.kk-kayit{border:1px solid var(--cizgi);border-radius:var(--r-3);
  padding:var(--b-4);margin-bottom:var(--b-4)}
.kk-bas{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;margin-bottom:var(--b-2)}
.kk-bas b{font-size:var(--y-4)}
.kk-metin{white-space:pre-wrap;border-left:2px solid var(--cizgi);padding-left:var(--b-3);
  margin:0 0 var(--b-2);color:var(--metin-2);font-size:var(--y-3);line-height:var(--sh-orta)}
.kk-oylar{display:grid;gap:var(--b-2);margin-top:var(--b-3)}
.kk-oy{border-top:1px solid var(--cizgi);padding-top:var(--b-2);font-size:var(--y-2)}
.kk-oy span{display:block;color:var(--metin-2);margin-top:2px}
.kk-ver{margin-top:var(--b-3);border-top:1px solid var(--cizgi);padding-top:var(--b-3)}
.pn-sayac{display:grid;grid-template-columns:repeat(4,1fr);gap:var(--b-3);margin-bottom:var(--b-5)}
@media(max-width:820px){.pn-sayac{grid-template-columns:repeat(2,1fr)}}
/* Kutunun solunda ince bir renk şeridi: hangi sayının iyi, hangisinin
   dikkat istediği bir bakışta okunur. Şerit bir süs değil, kutunun
   durumudur; vurgulu kutuda altın, ötekinde çizgi rengi. */
.pn-sayi{background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-3);
  padding:15px 17px;display:flex;align-items:center;gap:var(--b-3);min-width:0;
  box-shadow:var(--g-1);position:relative;overflow:hidden}
.pn-sayi::before{content:"";position:absolute;inset:0 auto 0 0;width:3px;background:var(--cizgi-2)}
.pn-sayi.vurgu::before{background:var(--kut-dolu)}
.pn-sayi > div{min-width:0}
.pn-sayi b{display:block;font-family:var(--baslik);font-size:var(--y-7);line-height:1.05;font-weight:700}
.pn-sayi span{display:block;font-size:var(--y-1);font-weight:700;letter-spacing:.07em;
  text-transform:uppercase;color:var(--metin-2);margin-top:3px;line-height:1.3}
.pn-sayi.vurgu{border-color:var(--kut-cizgi);background:var(--kut-zemin)}
.pn-sayi.vurgu b{color:var(--kut)}
.pn-sim{display:grid;place-items:center;width:36px;height:36px;flex:none;border-radius:var(--r-2);
  background:var(--lacivert-zemin);color:var(--lacivert)}
.pn-sim svg{width:18px;height:18px}
.pn-sayi.vurgu .pn-sim{background:var(--kut-dolu);color:var(--kut-dolu-metin)}

/* ---- Durum ve merdiven ----
   Hesabın hangi basamakta olduğunu anlatır. Uyarı değil bildirimdir;
   bu yüzden kırmızı kullanılmaz. */
.pn-durum{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;border-radius:var(--r-2);
  padding:13px 15px;font-size:var(--y-3);line-height:var(--sh-genis);margin:var(--b-3) 0}
.pn-durum.iyi{background:var(--yesil-zemin);border:1px solid var(--yesil-cizgi)}
.pn-durum.bek{background:var(--kut-zemin);border:1px solid var(--kut-cizgi)}
.pn-durum.eks{background:var(--yuzey-2);border:1px solid var(--cizgi)}
.pn-merdiven{list-style:none;padding:0;margin:var(--b-3) 0 0;display:grid;gap:9px}
.pn-merdiven li{display:flex;gap:var(--b-3);align-items:flex-start;padding:var(--b-3) var(--b-3);
  border:1px solid var(--cizgi);border-radius:var(--r-2);font-size:var(--y-3);line-height:var(--sh-genis)}
.pn-merdiven li[data-var="1"]{border-color:var(--yesil-cizgi);background:var(--yesil-zemin)}
.pn-merdiven .im{width:22px;height:22px;flex:none;border-radius:50%;display:grid;place-items:center;
  font-size:var(--y-1);font-weight:700;background:var(--yuzey-2);color:var(--metin-2);font-family:var(--mono)}
.pn-merdiven li[data-var="1"] .im{background:var(--yesil);color:var(--dolu-metin)}
.pn-merdiven b{display:block}
.pn-merdiven span{color:var(--metin-2)}

/* Eksikler kutusu: uyarı değil hatırlatma. Bu yüzden kırmızı değil
   altın zeminde durur. */
.pn-eksik{background:var(--kut-zemin);border:1px solid var(--kut-cizgi);border-radius:var(--r-3);
  padding:15px 18px;margin:0 0 var(--b-5)}
.pn-eksik b{display:block;color:var(--kut);font-size:var(--y-4);margin-bottom:7px}
.pn-eksik ul{margin:0;padding-left:19px;display:grid;gap:6px}
.pn-eksik li{font-size:var(--y-3);line-height:var(--sh-genis);color:var(--metin)}
.pn-eksik a{color:var(--kut);font-weight:600}

/* ---- İş satırları ---- */
.pn-liste{display:grid;gap:var(--b-2);margin-top:var(--b-3)}
.pn-sat{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;border:1px solid var(--cizgi);
  border-radius:var(--r-2);padding:var(--b-3) var(--b-3);font-size:var(--y-3)}
.pn-sat b{font-size:var(--y-4)}
.pn-sat small{color:var(--metin-2);font-size:var(--y-2)}
.pn-sat .d{margin-left:auto}
/* Öneri satırı: gerekçe ve iki düğme. Gerekçe uzun olabilir, satırın
   altına tam genişlikte geçer. */
.pn-sat > span:first-child{flex:1 1 300px;min-width:0}
.pn-ger{display:block;margin-top:5px;line-height:var(--sh-genis);white-space:pre-line}
.pn-dg{display:flex;gap:var(--b-2);flex-wrap:wrap;margin-left:auto}
.pn-dg .d{margin-left:0}

/* ---- Gösterge iş kutusu ---- */
.pn-bekleyen-var{border-color:var(--kut-cizgi)}
.pn-is{display:block;border:1px solid var(--cizgi);border-radius:var(--r-2);padding:var(--b-3) var(--b-3);
  margin-top:9px;color:var(--metin);text-decoration:none;transition:var(--gecis)}
a.pn-is:hover{border-color:var(--kut);text-decoration:none;background:var(--yuzey-2)}
.pn-is b{display:block;font-size:var(--y-4);line-height:1.45;margin-bottom:3px}
.pn-is span{display:block;font-size:var(--y-2);color:var(--metin-2);line-height:var(--sh-orta)}
.pn-bekleyen-var .pn-is{border-left:3px solid var(--kut)}

/* Davet rol seçicisi: seçenekler DAR durur. Ölçüldü — beş seçenek
   uzun açıklamalarıyla "Hesap daveti" kartını 750'den 1984 piksele
   çıkardı ve Yönetim sekmesi tavanı aştı. Açıklama bir cümleye indi;
   asıl uzun gerekçe kapalı satırın 'sebep' alanındadır ve orada
   kalması gerekir. */
.pn-secim .pn-grup-ic{display:grid;gap:8px}
.pn-secim .onay-kart{padding:9px 12px;min-height:0}
.pn-secim .onay-kart small{display:block;line-height:1.4}

/* Kapalı davet seçeneği. GİZLENMEZ, kapalı gösterilir: olmayan bir
   seçenek neden olmadığını söylemez. Soluklaştırma tek başına yetmez —
   sebebi de yanında yazılı durur ve o satır soluk DEĞİLdir, çünkü
   okunması gereken asıl şey odur. */
.onay-kart.pn-kapali{opacity:.62;cursor:not-allowed}
.onay-kart.pn-kapali b{color:var(--metin-2)}
.pn-sebep{display:block;margin-top:5px;color:var(--kirmizi);opacity:1;font-weight:600}

/* Bir karta gönderildikten sonraki kısa vurgu. Kaydırma bittiğinde
   "nereye geldim" sorusunu ortadan kaldırır. Devinimi kapatmış olana
   renk de değişmez: azaltılmış devinim bir tercih değil bir gereksinim
   olabilir. */
.pn-vurgula{outline:2px solid var(--kut);outline-offset:3px;
  transition:outline-color .4s ease}
@media(prefers-reduced-motion:reduce){.pn-vurgula{transition:none}}

/* ---- KART İÇİ GRUP KAPAĞI ----
   Bir kartın içinde birden çok iş varsa (profil kartında üç tane) her
   iş kendi kapağını alır. Kapağın üstünde ADI ve NE OLDUĞU yazılıdır;
   kapalı bir kapak bilgiyi gizlemez, sıraya koyar. */
.pn-grup{border:1px solid var(--cizgi);border-radius:var(--r-3);margin-bottom:var(--b-3)}
.pn-grup > summary{list-style:none;cursor:pointer;display:flex;flex-wrap:wrap;
  align-items:baseline;gap:4px 12px;padding:12px 15px;min-height:var(--hedef);
  position:relative;padding-inline-end:38px;border-radius:var(--r-3)}
.pn-grup > summary::-webkit-details-marker{display:none}
.pn-grup > summary::after{content:"";position:absolute;inset-inline-end:16px;top:19px;
  width:9px;height:9px;border-right:2px solid var(--metin-3);border-bottom:2px solid var(--metin-3);
  transform:rotate(45deg);transition:transform .15s ease}
.pn-grup[open] > summary::after{transform:rotate(-135deg) translateY(-3px)}
.pn-grup > summary:hover{background:var(--yuzey-2)}
.pn-grup > summary b{font-size:var(--y-4)}
.pn-grup > summary span{color:var(--metin-2);font-size:var(--y-2)}
.pn-grup[open] > summary{border-bottom:1px solid var(--cizgi);border-radius:var(--r-3) var(--r-3) 0 0}
.pn-grup-ic{padding:14px 15px 16px}
.pn-grup-ic > label:first-child{margin-top:0}

/* ---- Çalışma kartı ----
   ÖLÇÜM (13 Ağustos 2026, 1440px): "Çalışmalar ve hakem atama" kartı
   4303 piksel, içindeki liste 4026 piksel, on iki çalışma için çalışma
   başına 330 piksel. Editör sekmesinin tamamı 5385 pikseldi ve bunun
   yüzde sekseni bu tek listeydi.

   Sebep yerleşim değil YAKLAŞIMdı: her çalışmanın bütün ayrıntısı
   (hakemler, davet durumları, gönüllüler, öneriler, iki düğme) aynı
   anda açıktı. Oysa hakem BİR çalışmaya atanır; on iki çalışmanın
   açık durması, on birinin boşuna açık durmasıdır.

   Çalışma artık bir kapaktır: kapalıyken adı, yazarı ve rozetleri
   görünür — yani "hangi çalışmaya bakmalıyım" sorusunun cevabı
   görünür; ayrıntı basılınca açılır.

   BİR İSTİSNA VAR VE ÖNEMLİDİR: karar bekleyen bir gönüllü ya da
   yazar önerisi varsa çalışma KENDİLİĞİNDEN AÇIK gelir. Bekleyen bir
   insanı bir tıklamanın arkasına koymak, onu geciktirmenin en sessiz
   yoludur. */
.pn-cal{display:flex;flex-direction:column;margin-top:9px;
  border:1px solid var(--cizgi);border-radius:var(--r-3);padding:0}
/* Kapak üç satırdı: ad, yazar, rozetler — 110 piksel. Yazar ile
   rozetler aynı satıra alındı; ikisi de aynı soruya (bu çalışma ne
   durumda) cevap veriyor ve yan yana daha hızlı okunuyor. */
details.pn-cal > summary{list-style:none;cursor:pointer;
  display:flex;flex-wrap:wrap;align-items:center;gap:5px 14px;
  padding:12px 17px;border-radius:var(--r-3);position:relative;padding-inline-end:40px}
details.pn-cal > summary > h3{flex:1 1 100%}
details.pn-cal > summary > .kim{margin-bottom:0}
details.pn-cal > summary::-webkit-details-marker{display:none}
details.pn-cal > summary::after{content:"";position:absolute;inset-inline-end:17px;top:20px;
  width:9px;height:9px;border-right:2px solid var(--metin-3);border-bottom:2px solid var(--metin-3);
  transform:rotate(45deg);transition:transform .15s ease}
details.pn-cal[open] > summary::after{transform:rotate(-135deg) translateY(-3px)}
details.pn-cal > summary:hover{background:var(--yuzey-2)}
details.pn-cal[open] > summary{border-radius:var(--r-3) var(--r-3) 0 0;
  border-bottom:1px solid var(--cizgi)}
.pn-cal-ic{padding:13px 17px 15px}
.pn-cal h3{font-size:var(--y-4);margin:0 0 4px;line-height:1.45}
.pn-cal .kim{color:var(--metin-2);font-size:var(--y-2);margin-bottom:var(--b-2)}
.pn-cal .rzler{display:flex;flex-wrap:wrap;gap:6px}
.pn-cal > summary > .rzler{margin-bottom:0}
.pn-cal-ic > .rzler{margin-bottom:var(--b-3)}
.pn-cal .pn-is{margin-top:0;border-radius:var(--r-2) var(--r-2) 0 0;border-bottom:0}
.pn-cal-ac{display:flex;flex-wrap:wrap;gap:var(--b-2);margin-top:var(--b-3)}
.pn-cal-form{margin-top:var(--b-3);padding-top:var(--b-3);border-top:1px solid var(--cizgi)}
.pn-duzenle{display:flex;align-items:center;justify-content:center;min-height:var(--hedef);
  background:var(--kut-zemin);border:1px solid var(--kut-cizgi);border-top:0;
  border-radius:0 0 var(--r-2) var(--r-2);padding:var(--b-2) var(--b-3);
  font-size:var(--y-2);font-weight:600;color:var(--kut);text-decoration:none}
.pn-duzenle:hover{background:var(--kut-dolu);color:var(--kut-dolu-metin);text-decoration:none}
.pn-hk{display:grid;gap:6px;margin:var(--b-3) 0}
.pn-hk div{font-size:var(--y-2);color:var(--metin-2);line-height:var(--sh-orta);padding:7px 10px;
  background:var(--yuzey-2);border-radius:var(--r-2)}
.pn-hk b{color:var(--metin)}
.pn-gon{border:1px solid var(--kut-cizgi);background:var(--kut-zemin);border-radius:var(--r-2);
  padding:var(--b-3) var(--b-3);margin:var(--b-2) 0;font-size:var(--y-3);line-height:var(--sh-genis)}
.pn-gon b{display:block;font-size:var(--y-3);margin-bottom:3px}
.pn-gon .dgler{display:flex;gap:var(--b-2);margin-top:9px;flex-wrap:wrap}

/* ---- Hakem havuzu ---- */
.pn-havuz{margin-bottom:var(--b-3)}
.pn-hv-ara{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center;margin:10px 0 var(--b-3)}
.pn-hv-ara .pn-hv-q{flex:1 1 220px;min-width:0}
.pn-havuz-liste{display:grid;gap:var(--b-2);max-height:320px;overflow-y:auto;
  border:1px solid var(--cizgi);border-radius:var(--r-3);padding:10px;background:var(--yuzey-2)}
.pn-hv{display:flex;gap:var(--b-3);align-items:flex-start;background:var(--yuzey);
  border:1px solid var(--cizgi);border-radius:var(--r-2);padding:10px var(--b-3)}
.pn-hv > span{flex:1 1 auto;min-width:0;line-height:var(--sh-orta)}
.pn-hv small{color:var(--metin-2);font-size:var(--y-2)}
.pn-hv-yak{color:var(--kut)}
.pn-hv .d{flex:none;margin-left:auto}

/* ---- Görünürlük seçimleri ---- */
.pn-gor{display:grid;gap:10px;margin-top:var(--b-3)}
.pn-gor-sat{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1.1fr);gap:var(--b-3);align-items:center}
.pn-gor-sat label{margin:0}
@media (max-width:560px){ .pn-gor-sat{grid-template-columns:minmax(0,1fr)} }

/* ---- Profil resmi ---- */
.pn-resim{display:flex;gap:var(--b-4);align-items:flex-start;flex-wrap:wrap;margin:0 0 var(--b-5);
  border:1px solid var(--cizgi);border-radius:var(--r-3);padding:15px var(--b-4);background:var(--yuzey-2)}
.pn-resim-yuz{width:72px;height:72px;flex:none;border-radius:50%;overflow:hidden;display:grid;
  place-items:center;background:var(--kut-zemin);color:var(--kut);border:1px solid var(--cizgi);
  font-family:var(--serif);font-size:var(--y-7);font-weight:600}
.pn-resim-yuz img{width:100%;height:100%;object-fit:cover;display:block}
.pn-resim-ic{flex:1 1 260px;min-width:0}
.pn-resim-ic b{display:block;font-size:var(--y-3);margin-bottom:3px}
.pn-resim-ic p{margin:0 0 10px;font-size:var(--y-2);color:var(--metin-2);line-height:var(--sh-genis)}
.pn-resim-dg{display:flex;flex-wrap:wrap;gap:9px;align-items:center}

/* ---- Listem ---- */
.pn-lst{margin-top:var(--b-3)}
.pn-lst-yeni > .pn-is{border-left:3px solid var(--kut)}
.pn-lst-fark{margin:7px 0 0;padding-left:1.15em;font-size:var(--y-2);line-height:var(--sh-genis);color:var(--kut)}
.pn-lst-fark li{margin:2px 0}
.pn-lst-ust{display:flex;flex-wrap:wrap;gap:10px;align-items:center;justify-content:space-between;
  border:1px solid var(--kut-cizgi);background:var(--kut-zemin);border-radius:var(--r-2);
  padding:10px 13px;font-size:var(--y-3);line-height:var(--sh-orta)}

/* ---- Bağlantı satırları ----
   Her satır bir adres ve isteğe bağlı bir etikettir; etiket boş
   bırakılırsa ad adresten çözülür ve kişi bir şey yazmak zorunda kalmaz. */
.pn-bag{display:grid;gap:var(--b-2);margin-top:var(--b-2)}
.pn-bag-s{display:grid;gap:var(--b-2);grid-template-columns:1fr;align-items:start}
@media (min-width:620px){.pn-bag-s{grid-template-columns:minmax(0,1.9fr) minmax(0,1fr) auto}}
.pn-say{font-size:var(--y-2);color:var(--metin-2);font-family:var(--mono);margin:6px 0 0;text-align:right}
.pn-say.dolu{color:var(--kirmizi)}
/* Davet bağlantısı bir kez gösterilir; kutu bunu görünür kılacak kadar
   ayrı durmalı, ama bir hata iletisi gibi de okunmamalı. */
.pn-bag-kutu{margin-top:var(--b-3);background:var(--kut-zemin);border-radius:var(--r-3);
  padding:var(--b-3) var(--b-4);border:1px solid var(--kut-cizgi);display:grid;gap:10px}
.pn-bag-kutu input{font-family:var(--mono);font-size:var(--y-2)}
.pn-bag-kutu .d{justify-self:start}
.pn-bag-uyari{margin:0;font-size:var(--y-3);line-height:var(--sh-genis);color:var(--metin)}
#dvSureBitti{background:var(--yuzey-2);border:1px solid var(--cizgi);border-radius:var(--r-2);
  padding:13px 15px;margin:0 0 4px;color:var(--metin-2)}

/* ---- Mahremiyet notu ve örnek ---- */
.pn-mahrem{border-left:3px solid var(--kut);background:var(--kut-zemin);
  border-radius:0 var(--r-2) var(--r-2) 0;padding:13px 15px;margin:var(--b-3) 0;
  font-size:var(--y-3);line-height:1.68}
.pn-mahrem b{display:block;margin-bottom:3px}
.pn-ilerle{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:baseline;border:1px solid var(--cizgi);
  border-radius:var(--r-2);padding:var(--b-3) var(--b-3);margin-bottom:10px}
.pn-ilerle b{font-family:var(--serif);font-size:var(--y-6)}
.pn-ilerle span{font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-orta)}
.pn-ornek{margin-top:10px;border:1px solid var(--cizgi);border-radius:var(--r-2);overflow:hidden}
.pn-ornek summary{cursor:pointer;padding:11px var(--b-3);font-size:var(--y-3);font-weight:600;list-style:none;
  min-height:var(--hedef);display:flex;align-items:center}
.pn-ornek summary::-webkit-details-marker{display:none}
.pn-ornek summary::before{content:"+ ";color:var(--kut);font-family:var(--mono)}
.pn-ornek[open] summary::before{content:"- "}
.pn-ornek img{width:100%;height:auto;border-top:1px solid var(--cizgi)}
.pn-ornek .pn-ack{padding:0 var(--b-3) var(--b-3)}
.pn-rz{display:flex;flex-wrap:wrap;gap:6px;margin-top:7px}
.pn-rz-sat{display:flex;flex-wrap:wrap;gap:5px;margin:6px 0}
.pn-rz-k{font-size:var(--y-1);font-weight:600;border:1px solid var(--cizgi);border-radius:var(--r-tam);
  padding:2px 9px;color:var(--metin-2)}
.pn-rz-k.iyi{color:var(--yesil);border-color:var(--yesil-cizgi)}
.pn-rz-k.uyar{color:var(--kut);border-color:var(--kut-cizgi)}

/* ---- Yazar satırı ----
   Bir çalışmanın ikinci, üçüncü, dördüncü yazarı. Her satır kendi
   başına bir kayıttır ve tek başına kaldırılabilir. Yalnızca yönetim
   bölümündeki çalışma kaydında vardır. */
.pn-yzr{border:1px solid var(--cizgi);border-radius:var(--r-2);padding:var(--b-3) var(--b-4);margin-top:var(--b-3)}
.pn-yzr-sira{font-size:var(--y-1);font-weight:700;letter-spacing:.08em;text-transform:uppercase;
  color:var(--kut);margin-bottom:var(--b-2)}

/* ---- Üretilen anahtar ----
   Bir kez gösterilen bağlantı ve şifre. Mono yüzle yazılır: birbirine
   benzeyen karakterler ayırt edilebilsin. */
.pn-anahtar{background:var(--kut-zemin);border:1px solid var(--kut-cizgi);border-radius:var(--r-2);
  padding:var(--b-3) var(--b-4);margin-top:var(--b-3);font-family:var(--mono);
  font-size:var(--y-2);line-height:var(--sh-genis);word-break:break-all}
.pn-anahtar b{font-family:var(--ui);color:var(--kut)}
.pn-anahtar .d{margin-top:var(--b-3);font-family:var(--ui)}

/* ---- Rapor satırı ----
   Bir hakemin kararı ve raporunun başı. Rapor uzundur; burada yalnızca
   ilk bölümü durur, tamamı çalışmanın kendi sayfasındadır. */
.pn-rpr{border-top:1px solid var(--cizgi);padding:var(--b-3) 0}
.pn-rpr:first-child{border-top:0}
.pn-rpr-ust{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center}
.pn-rpr-metin{margin:var(--b-2) 0 0;font-size:var(--y-3);color:var(--metin-2);
  line-height:var(--sh-genis);white-space:pre-wrap}
</style>
CSS;

k_bas([
    /* PANO ÖLÇÜSÜ. Panel bir metin sayfası değil: sağ rayı yok, içi
       kart ızgarası ve çizelge. 'genis' (1240px) ölçüsünde 1600+
       ekranlarda ad, rozetler ve altı sütunlu okuma çizelgesi dar bir
       şeride hapsoluyor, iki yanı boş kalıyordu. */
    'olcu'   => 'pano',
    'baslik' => k_c('Panelim', 'My panel'),
    'yol'    => '/panel.php',
    'ek_bas' => $ekBas,
]);
?>
<section class="bolum" style="padding-top:clamp(16px,2vw,26px)">
  <div class="kap pn">

    <div id="pnYuk" class="yukleniyor"><?= k_c('Yükleniyor...', 'Loading...') ?></div>

    <!-- ---------- GİRİŞ / KAYIT ---------- -->
    <div id="pnKapi" class="gizli">
      <p class="bas-ust" style="display:flex;justify-content:center"><?= k_c('Hesap', 'Account') ?></p>
      <h1 style="text-align:center;margin-bottom:8px"><?= k_c('Kutadgu hesabınız', 'Your Kutadgu account') ?></h1>
      <p class="pn-ack" style="text-align:center;max-width:52ch;margin:0 auto 24px"><?= k_c(
        'Çalışmaları okumak için hesap gerekmez; her şey herkese açıktır. Hesap, hakemlik yapmak ve kendi çalışmanızı göndermek içindir.',
        'No account is needed to read; everything is open to everyone. An account is for reviewing and for submitting your own work.'
      ) ?></p>

      <div class="pn-kapi">
        <div class="sec-serit" role="tablist">
          <button type="button" id="sekGiris" role="tab" aria-selected="true"><?= k_c('Giriş', 'Sign in') ?></button>
          <button type="button" id="sekKayit" role="tab" aria-selected="false"><?= k_c('Yeni hesap', 'New account') ?></button>
        </div>

        <div id="formGiris">
          <label for="gKim"><?= k_c('E-posta ya da kullanıcı adı', 'E mail or username') ?></label>
          <input type="text" id="gKim" autocomplete="username">
          <label for="gParola"><?= k_c('Parola', 'Password') ?></label>
          <input type="password" id="gParola" autocomplete="current-password">
          <button class="d d-vurgu d-genis" type="button" id="dgGiris" style="margin-top:18px"><?= k_c('Giriş yap', 'Sign in') ?></button>
          <?php /* PAROLAYI UNUTAN İÇİN BİR YOL OLMALIYDI VE YOKTU.
                   Kod, davet ucunun yanında "parolasını unutan parola
                   yenileme yolundan gider" diye yazıyordu; öyle bir yol
                   hiç yazılmamıştı. Sonucu şuydu: parolasını unutan
                   herkes kalıcı olarak kilitli kalıyordu, çünkü davet de
                   kapalıdır (parolası olan hesaba davet çıkarmak, o
                   hesabı ele geçirmenin en kısa yoludur).

                   ORCID BUNUN YERİNE GEÇMEZ. ORCID doğrulanmamış olabilir
                   ve ORCID bir üçüncü taraftır: kapanırsa ya da istemci
                   anahtarı yenilenirse o kapı da kapanır. Tek kapısı
                   üçüncü tarafta olan bir sistem, o tarafın çalışma
                   saatlerine bağlıdır. */ ?>
          <p class="ip" style="margin-top:10px"><a href="#" id="dgUnuttum"><?= k_c('Parolamı unuttum', 'I forgot my password') ?></a></p>
          <div id="unutKutu" class="gizli">
            <label for="uMail"><?= k_c('Hesabınızın e-posta adresi', 'The e mail address of your account') ?></label>
            <input type="email" id="uMail" autocomplete="email">
            <button class="d d-ikinci d-genis" type="button" id="dgUnutGonder" style="margin-top:10px"><?= k_c('Yenileme bağlantısı gönder', 'Send a reset link') ?></button>
            <div id="unutMsj" class="form-msj"></div>
          </div>
          <?php /* ORCID YOLU BİR BAĞLANTIDIR, DÜĞME DEĞİL.
                   Betiksiz tarayıcıda da çalışsın diye: tıklanan şey
                   doğrudan bir adres. Ayar kapalıyken bu satır hiç
                   basılmaz — çalışmayan bir düğme göstermektense hiç
                   göstermemek doğrudur. */
                 if (function_exists('tg_orcid_acik') && tg_orcid_acik()): ?>
          <p class="pn-ayir-yazi"><?= k_c('ya da', 'or') ?></p>
          <a class="d d-genis d-ikinci" href="<?= k_esc(k_bag('/api/orcid/git')) ?>" rel="nofollow"><?= k_c('ORCID ile giriş yap', 'Sign in with ORCID') ?></a>
          <p class="ip"><?= k_c('ORCID kimliğinizi doğrular; unvanınızı doğrulamaz. Hakemlik için belge yine istenir.',
                                'ORCID verifies who you are; it does not verify your credential. A document is still required for reviewing.') ?></p>
          <?php endif; ?>
        </div>

        <div id="formKayit" class="gizli">
          <div class="alan-ikili">
            <div>
              <label for="kUnvan"><?= k_c('Unvan', 'Title') ?></label>
              <select id="kUnvan">
                <option value=""><?= k_c('(seçiniz)', '(choose)') ?></option>
                <?php foreach ($UNVAN as $uk => $ua): ?><option value="<?= k_esc($uk) ?>"><?= k_esc($ua) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="kAd"><?= k_c('Ad ve soyad', 'Name and surname') ?></label>
              <input type="text" id="kAd" autocomplete="name">
            </div>
          </div>
          <label for="kEposta"><?= k_c('E-posta', 'E mail') ?></label>
          <input type="email" id="kEposta" autocomplete="email">
          <label for="kKurum"><?= k_c('Kurum', 'Institution') ?></label>
          <input type="text" id="kKurum">
          <label for="kOrcid">ORCID</label>
          <input type="text" id="kOrcid" placeholder="0000-0000-0000-0000">
          <label for="kParola"><?= k_c('Parola (en az 10 karakter)', 'Password (at least 10 characters)') ?></label>
          <input type="password" id="kParola" autocomplete="new-password">

          <?php /* Bilim alanları kayıt sırasında sorulur. Zorunlu değildir;
                   sonradan panelden de eklenebilir. Burada sorulmasının
                   sebebi, hakem havuzunun ancak herkes alanını verdiğinde
                   işe yaramasıdır. */ ?>
          <label><?= k_c('Çalışma alanlarınız', 'Your fields') ?>
            <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı, sonradan da eklenebilir)', '(optional, can be added later)') ?></small></label>
          <?= as_kutu('kAlan', [], ['oneri' => false, 'ensok' => 6]) ?>
          <button class="d d-vurgu d-genis" type="button" id="dgKayit" style="margin-top:18px"><?= k_c('Hesap aç', 'Create account') ?></button>
          <?php /* ---------- ON YEDİNCİ KUSUR ----------
                   Burada şu yazıyordu: "Hesap açmak sizi hakem ya da
                   yazar yapmaz. Hakemlik için doktoranızın bir editör
                   tarafından doğrulanması, yazarlık için de en az bir
                   değerlendirme yapmanız gerekir."

                   İkinci yarısı 13 Ağustos 2026 kurul kararıyla
                   KALDIRILMIŞTI: yazarlık artık bir basamak değil, ayrı
                   bir yoldur; çalışmayı herkes gönderir, kararı editör
                   verir. Cümle kaldırılmayı unuttu ve kayıt sayfası —
                   yani sistemi ilk gören ekran — kaldırılmış bir kuralı
                   duyurmayı sürdürdü. Bu, bu oturumda on yedinci kez
                   görülen kusur sınıfıdır.

                   CÜMLE ARTIK ELLE YAZILMIYOR. Yazarlık koşulu tek
                   kaynaktan (tg_yazarlik_kosulu_kisa) gelir; kural
                   yeniden değişirse bu satır da kendiliğinden değişir.
                   Hakemlik koşulu ayrı bir olgudur ve o hâlâ doğrudur:
                   hakemlik için doktora bir editörce doğrulanır. */ ?>
          <p class="pn-ack"><?= k_c(
            'Hesap açmak sizi hakem yapmaz: hakemlik için doktoranızın bir editör tarafından doğrulanması gerekir.',
            'Creating an account does not make you a reviewer: reviewing requires an editor to confirm your doctorate.'
          ) ?> <?= k_esc(tg_yazarlik_kosulu_kisa(k_en())) ?> <?= k_c(
            'Kabul edilen çalışma önce hakemsiz yayımlanır; hakem aranmasını isteyip istemediğinize sonradan siz karar verirsiniz.',
            'An accepted work is first published without review; whether reviewers are sought afterwards is your decision.'
          ) ?></p>
        </div>
        <div id="kapiMsj" class="form-msj"></div>
      </div>
    </div>

    <!-- ---------- PANEL ---------- -->
    <div id="pnIc" class="gizli">
      <?php /* Eksikler. Kimseyi zorlamaz, yalnızca hatırlatır: profil
               fotoğrafı ve alan seçimi olmadan da hesap çalışır, ama
               ikisi de sistemin geri kalanını işler kılar. */ ?>
      <div class="pn-eksik gizli" id="pnEksik">
        <b><?= k_c('İki küçük eksik', 'Two small things missing') ?></b>
        <ul id="pnEksikListe"></ul>
      </div>
      <?php /* PANEL BAŞLIĞI — BAŞ HARF DAİRESİ YOK.
               Başlıkta hem baş harf dairesi hem kişinin tam adı
               vardı; daire adın kısaltmasıdır ve adın yanında durunca
               aynı şeyi iki kez söyler. Daire üst çubukta kalıyor:
               orada ad yazılı değil, işlevi orada. Orada da baş harf
               değil, kişinin kendi resmi gösteriliyor (tg_resim_yolu).

               Şerit de kalktı. Lacivert bant sayfanın en üstünde bir
               kez kuruluyor (üst çubuk); panelin başlığında ikinci kez
               kurulunca sayfa iki ayrı marka alanıyla başlıyordu. Ad
               artık kâğıdın üstünde, sayfa başlığı olarak duruyor. */ ?>
      <header class="pn-bas">
        <div class="pn-bas-yazi">
          <h1 id="pnAd"></h1>
          <div class="pn-bas-alt">
            <div class="pn-rz" id="pnRoller"></div>
            <span class="eposta" id="pnEposta"></span>
          </div>
        </div>
        <div class="pn-bas-dg">
          <a class="d d-vurgu d-kucuk" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Çalışma gönder', 'Submit a work') ?></a>
          <button class="d d-kucuk d-ikinci" type="button" id="dgCikis"><?= k_c('Çıkış', 'Sign out') ?></button>
        </div>
      </header>

      <?php /* ---------- SAYI ŞERİDİ ----------
               BİLDİRİLEN KUSUR: sekmede "Editör 2" yazıyordu ve iki'nin
               ne olduğu hiçbir yerde yazmıyordu. Bekleyen bir ileti mi,
               atanacak bir hakem mi, karar bekleyen bir başvuru mu?

               Sayı tek başına bir MERAKtır; adıyla birlikte bir İŞtir.
               Şerit her sayıyı adıyla yazar ve basıldığında o işin
               durduğu sekmeye götürür. Sıfır olan hiç çizilmez: "0 iş
               bekliyor" yazan bir kutu, yer kaplamaktan başka bir şey
               yapmaz.

               Şeridin verisi AYRI BİR UÇTAN GELMEZ. Sekme rozetlerini
               yazan roz() işlevi aynı çağrıda şeridi de besler; yani
               rozet ile şerit ayrışamaz, çünkü ikisi tek çağrının iki
               yüzüdür. İkinci bir sayaç yazılsaydı, bir gün biri
               ötekinden farklı bir sayı gösterirdi.

               Betiksiz tarayıcıda şerit boş kalır ve gizli durur;
               panelin bütün sayıları zaten uçlardan gelir. */ ?>
      <?php /* SAYI ŞERİDİ KALDIRILDI — 14 Ağustos 2026 kurul bildirimi:
               "üstteki '1 iş sizi bekliyor', '1 editörlük işi bekliyor'
               normalde orada olmasın, başka bir formül bulmak lazım."

               Doğruydu ve sebebi ölçülebilir: aynı olgu ekranda ÜÇ KEZ
               yazıyordu — şeritteki kutu, sekmenin üstündeki rozet ve
               Özet'teki "Sizi bekleyenler" kartı. Üç yerde yazan bir
               sayı, üç yerde ayrı düşme riski taşır ve hiçbirine
               güvenilmez.

               Yeni formül: SAYI AİT OLDUĞU SEKMENİN ÜSTÜNDE, BİR KEZ.
               Rozet zaten oradaydı; şerit onun uzun tekrarıydı.
               İşin KENDİSİ ise Özet'teki kartta listelenir — sayı
               nerede olduğunu söyler, kart ne olduğunu. */ ?>

      <!-- ---------- SEKMELER ----------
           Panel aşağı doğru uzayan bir liste değildir. Her iş kendi
           sekmesindedir; sekmeler tek satırda durur ve dar ekranda yana
           kayar. Amaç, bir işi yapmak için sayfayı kaydırmak zorunda
           kalmamaktır. -->
      <nav class="sek-bar sek-bar-kart sek-bar-yapiskan" role="tablist" aria-label="<?= k_c('Panel bölümleri', 'Panel sections') ?>">
        <button type="button" class="pn-sk acik" role="tab" aria-selected="true" data-sek="ozet"><?= k_c('Özet', 'Overview') ?><span class="sek-say sek-say-uyar gizli" id="rozOzet"></span></button>
        <button type="button" class="pn-sk" role="tab" aria-selected="false" data-sek="calismalar"><?= k_c('Çalışmalarım', 'My works') ?><span class="sek-say sek-say-uyar gizli" id="rozCalisma"></span></button>
        <button type="button" class="pn-sk" role="tab" aria-selected="false" data-sek="hakemlik"><?= k_c('Hakemliğim', 'My reviewing') ?></button>
        <button type="button" class="pn-sk" role="tab" aria-selected="false" data-sek="listem"><?= k_c('Listem', 'My list') ?><span class="sek-say sek-say-uyar gizli" id="rozListem"></span></button>
        <button type="button" class="pn-sk" role="tab" aria-selected="false" data-sek="hesap"><?= k_c('Hesabım', 'My account') ?><span class="sek-say sek-say-uyar gizli" id="rozHesap"></span></button>
        <?php /* İletiler kendi sekmesindedir: bir gelen kutusu, kime açık
                 olduğuna göre değil ne olduğuna göre yerleştirilir. Düğme
                 yalnız editöre görünür ve bölüm de yalnız ona basılır. */ ?>
        <button type="button" class="pn-sk gizli" role="tab" aria-selected="false" data-sek="iletiler" id="sekIletiler"><?= k_c('İletiler', 'Messages') ?><span class="sek-say sek-say-uyar gizli" id="rozIletiler"></span></button>
        <button type="button" class="pn-sk gizli" role="tab" aria-selected="false" data-sek="editor" id="sekEditor"><?= k_c('Editör', 'Editor') ?><span class="sek-say sek-say-uyar gizli" id="rozEditor"></span></button>
        <?php /* Yönetim sekmesi yalnızca baş editöre basılır. Editörün ve
                 hakemin sayfasında bu düğme hiç yoktur. */ ?>
        <?php if ($basYetki): ?>
        <button type="button" class="pn-sk" role="tab" aria-selected="false" data-sek="yonetim"><?= k_c('Yönetim', 'Management') ?><span class="sek-say sek-say-uyar gizli" id="rozYonetim"></span></button>
        <?php endif; ?>
      </nav>

      <!-- ================= ÖZET ================= -->
      <section class="pn-pnl acik" data-pnl="ozet">
      <?php /* Özet sekmesinde çip yoktur (bir pano, bir liste değil) ama
               başlık vardır: her sekmenin nerede olunduğunu söyleyen bir
               satırı olmalı. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Özet', 'Overview') ?></h2>
        <p><?= k_c('Sizi bekleyen işler, son çalışmalarınız ve sistemin nabzı.',
                   'The work waiting for you, your latest works, and the pulse of the system.') ?></p>
      </header>

      <!-- ---------- GÖSTERGE ----------
           Nabız tam genişlikte tek satır; altında iki sütun. Amaç, bir
           bakışta durumu görmek ve iş yapmak için kaydırmak zorunda
           kalmamak. -->
      <h2 class="pn-blk"><?= k_c('Sistemin nabzı', 'The pulse of the system') ?>
        <span><?= k_c('Bu sayılar sizin değil, sistemin tamamının durumunu gösterir.',
                      'These numbers show the state of the whole system, not of your own account.') ?></span></h2>
      <div class="pn-sayac" id="pnSayac" aria-label="<?= k_c('Sistemin nabzı', 'The pulse of the system') ?>"></div>

      <!-- Kullanıcı adı seçimi (ilk giriş) -->
      <div class="pn-kart gizli" id="kartKullanici">
        <h2><?= k_c('Bir kullanıcı adı seçin', 'Choose a username') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Kullanıcı adı yalnızca girişte kullanılır. Çalışmalarda, raporlarda ve kurul listelerinde her zaman <b>Ünvan Ad SOYAD</b> yazılır; kullanıcı adınız hiçbir sayfada görünmez. Bir kez seçilir, sonra değişmez.',
          'The username is used only for signing in. On works, reports and board listings your name always appears as <b>Title Name SURNAME</b>; your username is shown on no page. It is chosen once and does not change afterwards.'
        ) ?></p>
        <label for="kuAd"><?= k_c('Kullanıcı adı', 'Username') ?></label>
        <input type="text" id="kuAd" placeholder="ornek.kullanici" autocomplete="off">
        <button class="d d-vurgu" type="button" id="dgKullanici" style="margin-top:14px"><?= k_c('Kullanıcı adını belirle', 'Set username') ?></button>
        <div class="form-msj" id="kuMsj"></div>
      </div>


      <div class="pn-ikili-duzen">
        <div class="pn-sut">
          <div class="pn-kart pn-bekleyen" id="kartBekleyen">
            <h2><?= k_c('Sizi bekleyenler', 'Waiting for you') ?></h2>
            <p class="pn-ack"><?= k_c(
              'Yalnızca sizden bir şey bekleyen işler burada görünür: yazmanız gereken bir rapor, oyunuzu bekleyen bir oylama ya da tamamlanmamış bir doğrulama. Sağ üstteki daire üzerindeki sayı da bunları sayar.',
              'Only work that is waiting on you appears here: a report you owe, a vote awaiting your voice, or an unfinished verification. The number on the circle at the top right counts the same things.'
            ) ?></p>
            <div id="bekleyenListe"></div>
          </div>
          <div class="pn-kart">
            <h2><?= k_c('Son çalışmalarım', 'My latest works') ?>
              <button type="button" class="pn-bag-dg" data-sek-git="calismalar"><?= k_c('tümü', 'all') ?></button>
            </h2>
            <div id="ozetCalisma"></div>
          </div>
        </div>
        <div class="pn-sut">
          <?php /* MERDİVEN KAPAĞA ALINDI.
                   ÖLÇÜM: bu kart 655 piksel ve Özet sekmesinin yarısı.
                   İçeriği bir AÇIKLAMAdır: rollerin nasıl kazanıldığını
                   anlatır ve bir kez okunur. Her girişte 655 piksel yer
                   kaplaması, her gün okunacakmış gibi davranmaktır.

                   Kapağın üstünde kaybolan bilgi yok: kişinin ŞU ANDA
                   hangi basamakta olduğu kapakta yazılıdır ve betik onu
                   merdivenle aynı veriden yazar. Bir bakışta görülecek
                   şey odur; gerisi merak edildiğinde açılır.

                   METİN DE DÜZELTİLDİ: "yazarlıkla açılır" cümlesi,
                   yazarlığın hakemliğin üstünde bir basamak olduğunu
                   söylüyordu. 13 Ağustos 2026 kurul kararından sonra bu
                   doğru değil. Bu, aynı kusurun (uygulanmayan kuralı
                   duyurmak) kapının deseni tutmadığı için gözden kaçmış
                   bir örneğiydi. */ ?>
          <details class="pn-kart pn-katla" id="kartMerdiven">
            <summary><h2><?= k_c('Bulunduğunuz basamak', 'Where you stand') ?></h2>
              <span id="pnBasamak"></span></summary>
            <div class="pn-katla-ic">
              <p class="pn-ack"><?= k_c(
                'Hakemlikte roller verilmez, kazanılır ve sıra atlanmaz: okumakla başlar, belgeyle sürer, raporla tamamlanır. Yazarlık bu sıranın üstünde bir basamak değildir; ayrı bir yoldur.',
                'In reviewing, roles are not granted but earned, and no step is skipped: it begins with reading, continues with a credential, and is completed with a report. Authorship is not a rung above that ladder; it is a separate road.'
              ) ?></p>
              <ul class="pn-merdiven" id="pnMerdiven"></ul>
            </div>
          </details>
          <div class="pn-kart">
            <h2><?= k_c('Kısa yollar', 'Quick links') ?></h2>
            <div class="pn-kisa">
              <a class="pn-is" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><b><?= k_c('Çalışma gönder', 'Submit a work') ?></b><span><?= k_c('Yeni bir çalışmayı sisteme yükleyin.', 'Upload a new work to the system.') ?></span></a>
              <a class="pn-is" href="<?= k_esc(k_bag('/bekleyen.php')) ?>"><b><?= k_c('Hakem aranan çalışmalar', 'Works seeking reviewers') ?></b><span><?= k_c('Gönüllü olun; sistemi ayakta tutan emek budur.', 'Volunteer; this is the labour that holds the system up.') ?></span></a>
              <?php /* KISA YOL ARTIK PANELİN KENDİ LİSTESİNE GÖTÜRÜR.
                       Eskiden /oylama.php'ye gidiyordu: bütün çalışmaların
                       oylamalarını bir arada listeleyen ayrı bir sayfaydı.
                       Kaldırıldı (19 Ağustos 2026); gerekçesi o dosyanın
                       başındadır. Oy bekleyenler zaten "Sizi bekleyen
                       işler" kartında duruyor ve oy, çalışmanın kendi
                       sayfasında kullanılıyor. */ ?>
              <button type="button" class="pn-is" data-sek-git="ozet"><b><?= k_c('Sizi bekleyen işler', 'Work waiting on you') ?></b><span><?= k_c('Yazmanız gereken rapor, beklenen oyunuz ve yarım kalmış doğrulamalar.', 'A report you owe, a vote awaiting your voice, an unfinished verification.') ?></span></button>
              <a class="pn-is" href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><b><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></b><span><?= k_c('Bütün kurallar tek belgede.', 'Every rule in a single document.') ?></span></a>
            </div>
          </div>
        </div>
      </div>

      </section><!-- /ozet -->

      <!-- ================= ÇALIŞMALARIM ================= -->
      <section class="pn-pnl" data-pnl="calismalar">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Çalışmalarım', 'My works') ?></h2>
        <p><?= k_c('Yazarı olduğunuz çalışmalar: gönderdikleriniz, değerlendirmede olanlar ve yayımlananlar.', 'The works you are an author of: what you have submitted, what is under assessment and what has been published.') ?></p>
      </header>
        <div class="pn-kart">
          <?php /* BAŞLIK DÜZELTİLDİ — 18 Ağustos 2026. "Gönderdiğim
                   çalışmalar" yazıyordu; oysa bu liste tg_yazar_mi() ile
                   kurulur ve GÖNDERMEDİĞİ bir çalışmanın ortak yazarını
                   da kapsar. Arşivdeki bir ortak yazar hesabını açtığında,
                   hiç göndermediği bir çalışmayı "gönderdiğim" başlığı
                   altında buluyordu. Başlık, listenin ölçütünü söylemek
                   zorundadır: ölçüt gönderim değil YAZARLIKTIR. */ ?>
          <h2><?= k_c('Yazarı olduğum çalışmalar', 'Works I am an author of') ?></h2>
          <p class="pn-ack"><?= k_c(
            'Her çalışmanın hangi aşamada olduğu, kaç olumlu rapor aldığı, metninin açık mı kilitli mi olduğu ve altına düşen şerhler burada bir arada durur. Kendiniz göndermediğiniz ama yazarı olarak göründüğünüz çalışmalar da buradadır.',
            'Where each work stands, how many positive reports it has received, whether its text is open or locked, and the notes left beneath it, all in one place. Works you did not submit yourself but are listed as an author of also appear here.'
          ) ?></p>
          <div id="calismaListe"></div>
        </div>
      </section>

      <!-- ================= HAKEMLİĞİM ================= -->
      <section class="pn-pnl" data-pnl="hakemlik">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Hakemliğim', 'My reviewing') ?></h2>
        <p><?= k_c('Size gelen davetler, yazdığınız raporlar ve kullandığınız oylar.', 'The invitations you received, the reports you wrote and the votes you cast.') ?></p>
      </header>
        <div class="pn-kart">
          <h2><?= k_c('Hakemlik ve oy geçmişim', 'My reviewing and voting record') ?></h2>
          <p class="pn-ack"><?= k_c(
            'Değerlendirdiğiniz çalışmalar, verdiğiniz kararlar ve kullandığınız kurul oyları. Dört geçerli oy yazarlık hakkını kazandırır.',
            'The works you have assessed, the decisions you gave, and the panel votes you cast. Four valid votes earn the right to submit as an author.'
          ) ?></p>
          <div id="gecmisAlan"></div>
        </div>
      </section>

      <!-- ================= LİSTEM =================
           Beğenilen çalışmalar. Beğeni bir onay oyu değil bir yer imidir:
           çalışmayı yeniden bulmak ve başına bir şey geldiğinde haberdar
           olmak içindir. Kimin neyi beğendiği hiçbir yerde açık edilmez;
           çalışmanın sayfasında yalnızca toplam görünür. -->
      <section class="pn-pnl" data-pnl="listem">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Listem', 'My list') ?></h2>
        <p><?= k_c('Kaydettiğiniz çalışmalar ve son bakışınızdan bu yana değişenler.', 'The work you saved and what has changed since you last looked.') ?></p>
      </header>
        <div class="pn-kart">
          <h2><?= k_c('Listemdeki çalışmalar', 'Works in my list') ?></h2>
          <p class="pn-ack"><?= k_c(
            'Bir çalışmayı listenize aldığınızda, o çalışmaya yeni bir hakem raporu geldiğinde, hakem onayı değiştiğinde, bir kurul oylaması açıldığında ya da sonuçlandığında, altına şerh düşüldüğünde veya bir düzeltme kaydı eklendiğinde burada görürsünüz. Yayımlanmış metnin kendisi değişmez; değişen, etrafındaki değerlendirmedir.',
            'When you add a work to your list, you see it here when a new reviewer report arrives, when its approval status changes, when a panel vote opens or concludes, when a note is left beneath it, or when a correction is recorded. The published text itself does not change; what changes is the assessment around it.'
          ) ?></p>
          <div id="listemAlan"></div>
        </div>
      </section>

      <!-- ================= HESABIM ================= -->
      <section class="pn-pnl" data-pnl="hesap">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Hesabım', 'My account') ?></h2>
        <p><?= k_c('Profiliniz, doktora doğrulamanız ve parolanız.', 'Your profile, your doctorate confirmation and your password.') ?></p>
      </header>

      <!-- Doktora belgesi -->
      <?php /* BELGE KARTI KAPAĞA ALINDI.
               ÖLÇÜM: 945 piksel ve Hesabım sekmesinin dörtte biri. İçi
               üç ayrı doğrulama yolu, bir gizlilik açıklaması ve bir
               örnek belge görseli — hepsi doğru, hepsi gerekli, ama
               hepsi TEK BİR KEZ gerekli. Doğrulama bir kez yapılır ve
               bir daha açılmaz.

               Kapağın üstünde duran şey, kartın tek günlük bilgisidir:
               belgenizin durumu. "Doktora belgeniz doğrulandı" yazısını
               görmek için 945 pikseli açmak gerekmiyor artık.

               KENDİLİĞİNDEN AÇILMA: kişi aday hakemse ve belgesi henüz
               onaylanmamışsa kart açık gelir. Yarım kalmış bir işi bir
               tıklamanın arkasına koymak, onu unutturmanın yoludur.
               Ölçüt betikte, belgeGoster() içindedir. */ ?>
      <details class="pn-kart pn-katla" id="kartBelge">
        <summary><h2><?= k_c('Doktora doğrulaması', 'Doctorate confirmation') ?></h2>
          <span id="belgeOzet"></span></summary>
        <div class="pn-katla-ic">
        <p class="pn-ack"><?= k_c(
          'Hakemlik için doktora ya da eşdeğeri bir derece aranır; bilim dalı ayrımı gözetilmez. Belge istemiyoruz: doktoranızı bir editör adıyla doğrular ve o ad kayda geçer. Bir editör sizi hakem olarak davet ettiyse doğrulama zaten yapılmış olur.',
          'Reviewing requires a doctorate or an equivalent degree; no distinction is made between disciplines. We do not ask for a document: an editor confirms your doctorate by name and that name is recorded. If an editor invited you as a reviewer, the confirmation has already been made.'
        ) ?></p>

        <div class="pn-mahrem">
          <b><?= k_c('Belgenizin kendisini istemiyoruz.', 'We do not ask for the document itself.') ?></b>
          <?= k_c(
            'Bir mezuniyet belgesinin üzerinde kimlik numaranız, anne ve baba adınız, doğum tarihiniz ve diploma numaranız yazılıdır. Sistemin bunların hiçbirine ihtiyacı yoktur; ihtiyaç duyduğu tek şey doktora derecenizin olup olmadığıdır. Bu yüzden belge yüklenmez, yalnızca doğrulama anahtarı saklanır. Toplanmayan veri sızdırılamaz.',
            'A graduation document carries your identity number, your parents\' names, your date of birth and your diploma number. The system needs none of these; the only thing it needs to know is whether you hold a doctorate. The document is therefore never uploaded; only the verification key is stored. Data that is not collected cannot be leaked.'
          ) ?>
        </div>

        <div class="pn-durum" id="belgeDurum"></div>
        <div id="belgeForm">
          <div class="onay-dizi-2">
            <?php /* e-DEVLET SATIRI KALDIRILDI — 14 Ağustos 2026.
                     Tek bir ülkenin kimlik altyapısına bağlıydı ve
                     listede ilk sıradaydı: yurt dışındaki bir
                     araştırmacı sayfayı açtığında karşısına önce
                     giremeyeceği bir kapı çıkıyordu. Gerekçenin tamamı
                     ortak.php'de tg_dogrulama_editor()'ün başındadır.
                     Uç da o türü 410 ile kapatıyor; ekranda duran ama
                     sunucuda kapalı bir seçenek bırakılmadı. */ ?>
            <label class="onay-kart"><input type="radio" name="bTur" value="orcid" checked> <span><?= k_c('ORCID üzerinden kendiliğinden denetim', 'Automatic check through ORCID') ?></span></label>
            <label class="onay-kart"><input type="radio" name="bTur" value="yabanci"> <span><?= k_c('Kurumun kamusal sayfası', 'A public page of the institution') ?></span></label>
          </div>

          <div id="bOrcid">
            <label for="bOrcidNo">ORCID</label>
            <input type="text" id="bOrcidNo" placeholder="0000-0000-0000-0000" autocomplete="off" spellcheck="false">
            <p class="pn-ack"><?= k_c(
              'ORCID profilinizdeki eğitim kayıtlarını herkese açık bıraktıysanız, doktora düzeyinde bir kaydınızın olup olmadığı sistem tarafından kendiliğinden sorgulanır. Bu tek başına onay değildir; editörün işini tek bakışa indirir. Bu yol her ülkeden araştırmacı için işler ve hiçbir belge yüklemeyi gerektirmez.',
              'If you have left the education records on your ORCID profile public, the system checks automatically whether you hold a record at doctoral level. This alone is not approval; it reduces the editor\'s work to a single glance. This route works for researchers from any country and requires no document upload.'
            ) ?></p>
            <button class="d d-ikinci d-kucuk" type="button" id="dgOrcidDenetle"><?= k_c('ORCID kayıtlarımı şimdi sorgula', 'Check my ORCID records now') ?></button>
            <div class="form-msj" id="orcidMsj"></div>
          </div>

          <div id="bYabanci" class="gizli">
            <label for="bUrl"><?= k_c('Belgenin sorgulanabileceği adres', 'Address at which it can be verified') ?></label>
            <input type="text" id="bUrl" placeholder="https://..." autocomplete="off" spellcheck="false">
            <label for="bKurum"><?= k_c('Belgeyi veren kurum', 'Issuing institution') ?></label>
            <input type="text" id="bKurum">
            <p class="pn-ack"><?= k_c(
              'Adres kamusal bir alan adında olmalıdır (gov, edu, ac.uk, edu.tr ve benzeri). Kişisel site ya da ticari alan adı kabul edilmez. Kurumun mezun sorgulama sayfası, bölüm personel sayfanız ya da tez veri tabanındaki kaydınız uygundur.',
              'The address must be on a public domain (gov, edu, ac.uk, edu.tr and the like). A personal site or a commercial domain is not accepted. An institutional graduate verification page, your departmental staff page, or your record in a thesis database is suitable.'
            ) ?></p>
          </div>

          <button class="d d-vurgu" type="button" id="dgBelge" style="margin-top:14px"><?= k_c('Doğrulama anahtarımı sun', 'Submit my verification key') ?></button>
          <div class="form-msj" id="belgeMsj"></div>
        </div>
        </div>
      </details>

      <!-- Profil -->
      <div class="pn-kart pn-tam">
        <h2><?= k_c('Profiliniz', 'Your profile') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Bu bilgiler çalışmalarınızda ve raporlarınızda görünür. Unvanınızı istediğiniz zaman güncelleyebilirsiniz; unvan zamanla yükselir, sistem buna kapalı değildir.',
          'This information appears on your works and your reports. You may update your title at any time; a title rises over the years and the system is not closed to that.'
        ) ?></p>
        <?php /* ---------- PROFİL KARTI ÜÇ GRUBA AYRILDI ----------
                 ÖLÇÜM (1440px): kart 2348 piksel, Hesabım sekmesi 3368
                 piksel. Tek bir kartta üç ayrı iş vardı: kimlik, iletişim
                 ayarları ve alan seçimi. Üçü de aynı anda açıktı, oysa
                 üçü de AYRI zamanlarda yapılır — kimlik ilk girişte,
                 görünürlük bir kez, alan seçimi hakemliğe başlarken.

                 Gruplar kapak oldu. Birincisi açık doğar (yeni gelen
                 önce adını yazar), ötekiler kapalı. Kapaklar bilgiyi
                 gizlemez: kapağın üstünde ne olduğu yazılıdır ve alanlar
                 kapalıyken de forma dâhildir — kaydet düğmesi hepsini
                 birden gönderir, çünkü tek bir profil vardır. */ ?>
        <details class="pn-grup" open>
        <summary><b><?= k_c('Kimliğiniz', 'Who you are') ?></b>
          <span><?= k_c('Resim, unvan, kurum, ORCID, kısa tanıtım',
                        'Photograph, title, institution, ORCID, a short introduction') ?></span></summary>
        <div class="pn-grup-ic">
        <?php /* Profil resmi. Zorunlu değildir: resim yoksa baş harfler
                 aynı yerde aynı ölçüde durur. */ ?>
        <div class="pn-resim">
          <span class="pn-resim-yuz" id="pnYuz"></span>
          <div class="pn-resim-ic">
            <b><?= k_c('Profil resmi', 'Profile picture') ?></b>
            <p><?= k_c(
              'İsteğe bağlıdır. Yüklerseniz, adınızın göründüğü yerlerde baş harflerinizin yerine bu resim görünür. Resim kareye kırpılır, küçültülür ve yeniden yazılır; içindeki konum ve cihaz bilgisi düşer.',
              'Optional. If you upload one, it appears in place of your initials wherever your name is shown. The image is cropped square, reduced and rewritten; any location and device data inside it is dropped.'
            ) ?></p>
            <div class="pn-resim-dg">
              <label class="dosya-sec d d-ikinci d-kucuk"><input type="file" id="pnResimDosya" accept="image/*"><span><?= k_c('Resim seç', 'Choose a picture') ?></span></label>
              <button type="button" class="d d-ikinci d-kucuk gizli" id="pnResimSil"><?= k_c('Kaldır', 'Remove') ?></button>
            </div>
            <p class="form-msj" id="pnResimMsj"></p>
          </div>
        </div>
        <div class="alan-ikili">
          <div>
            <label for="pUnvan"><?= k_c('Unvan', 'Title') ?></label>
            <select id="pUnvan">
              <option value=""><?= k_c('(yok)', '(none)') ?></option>
              <?php /* DEĞER ANAHTAR, GÖRÜNEN AD METİN. Bir zamanlar ikisi de
                       görünen addı ve kayıtta anahtar duruyordu ('dr' gibi).
                       İkisi eşleşmediği için kutu her açılışta BOŞ geliyordu:
                       selectedIndex -1 oluyor, kişi unvanını seçip kaydetse
                       bile sayfayı yenilediğinde yine boş görüyor, ve bir
                       sonraki kaydetmede boş kutu unvanı SİLİYORDU. Yani
                       kaydetmek unvanı siliyordu. */ ?>
              <?php foreach ($UNVAN as $uk => $ua): ?><option value="<?= k_esc($uk) ?>"><?= k_esc($ua) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="pAd"><?= k_c('Ad ve soyad', 'Name and surname') ?></label>
            <input type="text" id="pAd">
          </div>
        </div>
        <label for="pKurum"><?= k_c('Kurum', 'Institution') ?></label>
        <input type="text" id="pKurum">
        <div class="alan-ikili">
          <div><label for="pOrcid">ORCID</label><input type="text" id="pOrcid" placeholder="0000-0000-0000-0000">
            <?php /* Doğrulanmış ORCID, elle yazılmış olandan AYRI görünür.
                     İkisi aynı görünseydi doğrulamanın bir anlamı kalmazdı. */ ?>
            <p class="orcid-durum" id="pOrcidDurum" hidden></p>
            <?php if (function_exists('tg_orcid_acik') && tg_orcid_acik()): ?>
            <p><a class="orcid-bag" id="pOrcidBag" href="<?= k_esc(k_bag('/api/orcid/git?niyet=bagla')) ?>" rel="nofollow"><?= k_c('ORCID hesabımla doğrula', 'Verify with my ORCID account') ?></a></p>
            <?php endif; ?>
          </div>
          <div><label for="pScopus">Scopus Author ID <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı)', '(optional)') ?></small></label><input type="text" id="pScopus"></div>
        </div>
        <?php /* Kısa tanıtım. Düz metindir ve 600 karakterle sınırlıdır:
                 kişi sayfası bir özgeçmiş değil bir kayıt sayfasıdır. */ ?>
        <label for="pTanitim"><?= k_c('Kısa tanıtım', 'Short description') ?>
          <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı)', '(optional)') ?></small></label>
        <textarea id="pTanitim" rows="4" maxlength="600" placeholder="<?= k_c(
          'Çalıştığınız konular ve ilgi alanlarınız hakkında birkaç cümle.',
          'A few sentences about the subjects you work on and your interests.'
        ) ?>"></textarea>
        <p class="pn-say" id="pTanitimSay">0 / 600</p>

        <?php /* ---- İLETİŞİM VE GÖRÜNÜRLÜK ----
                 Her alanın kime açık olacağına kişi karar verir. Üç düzey
                 vardır ve üçü de sayfada yazılıdır; "gizli" seçildiğinde
                 veri sayfaya hiç konmaz, gizlenip kaynakta bırakılmaz.

                 E-posta ve telefonun varsayılanı GİZLİDİR. Sistem kayıt
                 sırasında adresin görünmeyeceği sözünü verdi; o sözü
                 kimseye sormadan bozmak olmaz. Görünmesini isteyen
                 buradan kendi açar. */ ?>
        </div></details>

        <?php /* İKİNCİ GRUP. Kapalı doğar: burası bir kez ayarlanır ve
                 sonra yıllarca açılmaz. Kapağın üstündeki satır ne
                 olduğunu söyler, yani kapalı olması bilgiyi
                 gizlemez — sıraya koyar. */ ?>
        <details class="pn-grup">
        <summary><b><?= k_c('İletişim ve görünürlük', 'Contact and visibility') ?></b>
          <span><?= k_c('Telefon, e-posta görünürlüğü, akademik bağlantılar, üyelikler',
                        'Telephone, e-mail visibility, academic links, memberships') ?></span></summary>
        <div class="pn-grup-ic">
        <p class="pn-ack"><?= k_c(
          'Hangi bilginizin kime açık olacağına siz karar verirsiniz. "Herkese açık" dediğinizde sayfanızı açan herkes görür. "Yalnızca sistem kullanıcılarına" dediğinizde yalnızca sisteme girmiş yazar, hakem ve editörler görür; giriş yapmamış okur ve otomatik toplayıcılar göremez. "Gizli" dediğinizde o bilgi sayfaya hiç konmaz. E-posta adresi hiçbir durumda sayfaya düz yazı olarak basılmaz; okur bir düğmeye bastığında kurulur, böylece adres toplayan yazılımların eline kolayca geçmez.',
          'You decide who sees which of your details. "Public" means anyone who opens your page sees it. "Registered users only" means only signed in authors, reviewers and editors see it; a signed out reader and automated harvesters cannot. "Hidden" means the detail is never placed on the page at all. An e mail address is never printed on the page as plain text; it is assembled when the reader presses a button, so that address harvesting software cannot pick it up easily.'
        ) ?></p>
        <div class="alan-ikili">
          <div><label for="pTelefon"><?= k_c('Telefon', 'Telephone') ?>
            <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı)', '(optional)') ?></small></label>
            <input type="text" id="pTelefon" inputmode="tel" maxlength="40" placeholder="+90 ..."></div>
        </div>
        <div class="pn-gor" id="pGorunurluk">
          <?php foreach ([
            'eposta'      => [k_c('E-posta adresi', 'E mail address')],
            'telefon'     => [k_c('Telefon', 'Telephone')],
            'orcid'       => ['ORCID'],
            'scopus'      => ['Scopus Author ID'],
            'baglantilar' => [k_c('Dış bağlantılar', 'External links')],
          ] as $alan => $ad): ?>
          <div class="pn-gor-sat">
            <label for="pGor-<?= k_esc($alan) ?>"><?= k_esc($ad[0]) ?></label>
            <select id="pGor-<?= k_esc($alan) ?>" data-gor="<?= k_esc($alan) ?>">
              <option value="acik"><?= k_c('Herkese açık', 'Public') ?></option>
              <option value="uyeler"><?= k_c('Yalnızca sistem kullanıcılarına', 'Registered users only') ?></option>
              <option value="gizli"><?= k_c('Gizli', 'Hidden') ?></option>
            </select>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="pn-ack"><?= k_c(
          'Kişi sayfanızda, kayıtlarınızın üstünde görünür. Bu bir beyandır; sistem doğrulamaz ve sayfada beyan olduğu yazılıdır. Biçimlendirme kullanılmaz.',
          'It appears on your person page, above your records. This is a statement; the system does not verify it, and the page says so. No formatting is used.'
        ) ?></p>

        <?php /* Akademik bağlantılar. Yalnızca https kabul edilir:
                 şifresiz bir adres, tıklayan kişinin trafiğini açıkta
                 bırakır. En çok on tane. */ ?>
        <label><?= k_c('Akademik bağlantılarınız', 'Your academic links') ?>
          <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(en çok 10)', '(up to 10)') ?></small></label>
        <p class="pn-ack" style="margin-bottom:4px"><?= k_c(
          'YÖKSİS, ABS, AVESİS, kurum sayfanız, ORCID, Google Scholar, ResearchGate, GitHub ya da başka bir yer. Etiketi boş bırakırsanız ad adresten çözülür. Yalnızca https adresleri kabul edilir.',
          'YOKSIS, your institutional page, ORCID, Google Scholar, ResearchGate, GitHub or anywhere else. If you leave the label empty, the name is resolved from the address. Only https addresses are accepted.'
        ) ?></p>
        <div class="pn-bag" id="pBaglar"></div>
        <button type="button" class="d d-ikinci d-kucuk" id="pBagEkle" style="margin-top:8px"><?= k_c('Bağlantı ekle', 'Add a link') ?></button>

        <?php /* Üyelikler. Dernek, kurul, komisyon; her satır bir üyelik. */ ?>
        <label for="pUyelik"><?= k_c('Üyelikleriniz', 'Your memberships') ?>
          <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(her satıra bir tane, en çok 12)', '(one per line, up to 12)') ?></small></label>
        <textarea id="pUyelik" rows="4" placeholder="<?= k_c(
          'Türk Tarih Kurumu&#10;İktisatçılar Derneği yönetim kurulu',
          'Royal Historical Society&#10;Board of the Economic Association'
        ) ?>"></textarea>

        </div></details>

        <?php /* ÜÇÜNCÜ GRUP. Alan seçici tek başına 441 piksel; bir kez
                 seçilir ve nadiren değişir. */ ?>
        <details class="pn-grup">
        <summary><b><?= k_c('Çalışma alanlarınız', 'Your fields') ?></b>
          <span><?= k_c('Editörlerin hakem ararken sizi bulması için',
                        'So that editors can find you when they look for a reviewer') ?></span></summary>
        <div class="pn-grup-ic">
        <p class="pn-ack" style="margin-bottom:10px"><?= k_c(
          'Hakem aranan çalışmalarda size uygun olanların gösterilmesi ve editörlerin hakem ararken sizi bulabilmesi içindir. Alanınız dışındaki bir çalışmayı da değerlendirebilirsiniz; sıfatınızı kendiniz seçersiniz. Kodların ilk iki basamağı uluslararası ölçüte (OECD Frascati) bağlıdır; üçüncü basamak bu sisteme aittir ve listede olmayan bir dalı önerebilirsiniz.',
          'This lets works seeking reviewers be shown to you and lets editors find you when they look for a reviewer. You may also assess a work outside your fields; you choose the capacity yourself. The first two levels of the codes follow an international standard (OECD Frascati); the third level belongs to this system, and you may propose a branch that is missing.'
        ) ?></p>
        <?= as_kutu('pAlan', [], ['ensok' => 10]) ?>

        <?php /* Dizinden çıkma. Hakem dizini herkese açıktır; kimse orada
                 durmak zorunda değildir ve çıkmak hakemliği etkilemez.
                 Adres dizinde zaten hiçbir zaman görünmez. */ ?>
        <label class="onay">
          <input type="checkbox" id="pDizin">
          <span><?= k_c(
            'Hakem dizininde görünmek istemiyorum',
            'I would rather not appear in the reviewer directory'
          ) ?><small><?= k_c(
            'Dizinde adınız, kurumunuz ve alanlarınız görünür; adresiniz hiçbir zaman görünmez. Çıkmanız hakemliğinizi ve kayıtlarınızı etkilemez.',
            'The directory shows your name, institution and fields; your address is never shown. Leaving affects neither your reviewing nor your records.'
          ) ?></small></span>
        </label>
        </div></details>

        <?php /* Kaydet düğmesi kapakların DIŞINDA durur: üç grup tek bir
                 profildir ve tek bir düğmeyle kaydedilir. Her grubun
                 kendi düğmesi olsaydı, hangisinin neyi kaydettiği
                 sorusu doğardı. */ ?>
        <button class="d d-vurgu" type="button" id="dgProfil" style="margin-top:var(--b-5)"><?= k_c('Profili kaydet', 'Save profile') ?></button>
        <div class="form-msj" id="profilMsj"></div>
      </div>

      <!-- Parola -->
      <div class="pn-kart">
        <h2><?= k_c('Parola', 'Password') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Yayımlanmış bir çalışmanın metni, parolanız ele geçse bile değiştirilemez. Yine de parolanızı zaman zaman yenilemek iyi bir alışkanlıktır.',
          'The text of a published work cannot be altered even if your password is taken. Even so, renewing your password from time to time is a good habit.'
        ) ?></p>
        <div class="alan-ikili">
          <div><label for="sEski"><?= k_c('Mevcut parola', 'Current password') ?></label><input type="password" id="sEski" autocomplete="current-password"></div>
          <div><label for="sYeni"><?= k_c('Yeni parola', 'New password') ?></label><input type="password" id="sYeni" autocomplete="new-password"></div>
        </div>
        <button class="d d-ikinci" type="button" id="dgParola" style="margin-top:14px"><?= k_c('Parolayı değiştir', 'Change password') ?></button>
        <div class="form-msj" id="parolaMsj"></div>
      </div>

      </section><!-- /hesap -->

      <!-- ================= İLETİLER ================= -->
      <?php /* YALNIZCA BAŞ EDİTÖRE BASILIR.
               KURUL BİLDİRİMİ — 19 Ağustos 2026: "editörler siteden
               gelen mesajları görmesin, baş editörler görecek."

               Eskiden $edYetki ile basılıyordu ve okuma uçları da
               sıradan editöre açıktı: bir editör yanıt veremiyor ama
               herkesin yazdığı her şeyi okuyabiliyordu. Yetkilendirmenin
               en sık yapılan yanlışı budur — EYLEM korunur, VERİ
               korunmaz. Oysa iletişim kutusuna yazan kişi bir yayın
               kuruluna değil, sistemin sorumlusuna yazdığını düşünür;
               içinde bir şikâyet ya da bir hakem hakkında söz olabilir.

               BÖLÜM SUNUCUDA BASILMAZ, BETİKLE GİZLENMEZ. Olmayan bir
               sekme kazayla açılamaz; gizlenmiş bir sekme açılır. */ ?>
      <?php if ($basYetki): ?>
      <section class="pn-pnl" data-pnl="iletiler">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('İletiler', 'Messages') ?></h2>
        <p><?= k_c('İletişim sayfasından gelen iletiler ve yanıtları.', 'The messages sent from the contact page, and their replies.') ?></p>
      </header>
      <?php /* =================================================================
               İLETİLER
               -----------------------------------------------------------------
               BU BÖLÜM YOKTU VE YOKLUĞU BİR SÖZÜ TUTULAMAZ KILIYORDU.
               İletişim sayfası ziyaretçiye "yanıt geldiğinde burada belirir"
               diyor; oysa yanıt verecek hiçbir yol yoktu. Panelde bölüm yok,
               uçlar yok, Telegram'dan yanıtlamayı sağlayacak webhook ise
               yalnız bir yorum olarak duruyordu.

               NEDEN KENDİ SEKMESİNDE (13 Ağustos 2026'da taşındı):
               bölüm önce Editör sekmesinin içindeydi ve gerekçesi
               "iletiyi okumak editörün işidir" diye yazılmıştı. Bu
               gerekçe SORUMLUYU söylüyor, İŞİN NE OLDUĞUNU değil. Bir
               gelen kutusu, kime açık olduğuna göre değil ne olduğuna
               göre yerleştirilir; posta kutusunu mutfağa koymak, onu
               yemek yapan kişi boşaltıyor diye doğru olmaz.

               Ölçülen sonuç da bunu söylüyordu: Editör sekmesindeki
               rozet İKİ ayrı işi birden sayıyordu (bekleyen iletiler +
               editörlük işleri), yani "Editör 2" yazısının neyi
               saydığı belirsizdi. Sayılar ancak ayrıldıkları zaman bir
               şey söyler.

               YETKİ 19 AĞUSTOS 2026'DA DARALDI. Bu satırda bir zaman
               "okuma editöre, yanıt yazma baş editöre açıktır" yazıyordu
               ve o gün doğruydu; artık değil. Kurul bildirimi üzerine
               OKUMA da baş editöre alındı: yazma uçları korunurken
               okuma uçlarının açık kalması, korunması gereken şeyin
               eylem değil insanların YAZDIKLARI olduğunu atlamaktı.
               Denetim uçlarda durur (yonetim_yazma_gerek); bu bölüm de
               $basYetki ile basılır.

               BEKLEYEN SAYISI SEKMEDE DE GÖRÜNÜR: bir iletiyi aramak
               zorunda kalmak, onu geciktirmenin en sessiz yoludur. */ ?>
      <div class="pn-kart pn-tam" id="kartIleti">
        <h2><?= k_c('İletiler', 'Messages') ?>
          <span class="sek-say sek-say-uyar gizli" id="iltBekSay"></span></h2>
        <p class="pn-ack"><?= k_c(
          'İletişim sayfasından gelen iletiler. Yanıt iki yere birden düşer: kişinin kendi konuşma bağlantısına ve e-postasına. Kurum ve destek başvuruları ayrı tür olarak gelir; bunlar bekletildiğinde kaybedilen şey bir soru değil, bir destektir. Kapatmak silmek değildir: konuşma kişinin bağlantısında durmayı sürdürür ve yeniden açılabilir.',
          'The messages sent from the contact page. A reply reaches two places at once: the person\'s own conversation link and their e-mail. Institutional and support enquiries arrive as their own kind; what is lost when one of those waits is not a question but a source of support. Closing is not deleting: the conversation stays at the person\'s link and can be reopened.'
        ) ?></p>
        <div class="sek-bar" role="tablist" aria-label="<?= k_c('İleti süzgeci', 'Message filter') ?>">
          <button type="button" class="pn-sk acik" data-ilt-suz=""><?= k_c('Hepsi', 'All') ?></button>
          <button type="button" class="pn-sk" data-ilt-suz="bekleyen"><?= k_c('Bekleyen', 'Awaiting reply') ?></button>
          <button type="button" class="pn-sk" data-ilt-suz="kapali"><?= k_c('Kapalı', 'Closed') ?></button>
          <button type="button" class="pn-sk" data-ilt-suz="istenmeyen"><?= k_c('İstenmeyen', 'Unwanted') ?></button>
        </div>
        <div id="iltListe"></div>
        <div id="iltKonusma" class="ilt-konusma gizli" aria-live="polite"></div>
      </div>
      </section><!-- /iletiler -->
      <?php endif; ?>

      <!-- ================= EDİTÖR ================= -->
      <?php /* =============== SEKME AYRIMI: EDİTÖRLÜK / SİTE YÖNETİMİ ===============
               İki sekme vardı ama içerikleri birbirine karışmıştı:
               "Editör" sekmesinde sunucu durumu ve okuma raporları,
               "Yönetim" sekmesinde ise yazar başvuruları ve hakemlik
               süreçleri duruyordu. Yani editörlüğün asıl işi olan iki
               kart yönetimde, yönetimin işi olan üç kart editördeydi.
               Bir kullanıcı aradığı işi bulamadığında kusuru kendinde
               arar; oysa kusur yerleştirmedeydi.

               Ayrım artık tek bir soruyla yapılıyor:
                 ÇALIŞMALAR ve İNSANLAR üzerine bir karar mı? -> EDİTÖR
                 SİTENİN kendisi üzerine bir iş mi?           -> YÖNETİM

               Editör: başvuruyu kabul etmek, hakem atamak, süreci
               izlemek, belgeyi onaylamak, alan kodu kararı, sürelerin
               ölçümü. Hepsi bir çalışma ya da bir kişi hakkında karar.

               Yönetim: hesap davet etmek ve rol vermek (baş editörlük
               dahil), sunucunun durumu, sitenin okunma kayıtları.
               Hiçbiri bir çalışma hakkında karar değildir.

               Bu ayrım yetkiyi DEĞİŞTİRMEZ: hangi kartın kime açıldığı
               eskisi gibi $edYetki ve $basYetki ile belirlenir. Değişen
               yalnızca kartın hangi sekmede durduğudur. */ ?>
      <section class="pn-pnl" data-pnl="editor">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Editör', 'Editor') ?></h2>
        <p><?= k_c('Çalışmalar ve insanlar üzerine verilen kararlar.', 'The decisions taken about works and about people.') ?></p>
      </header>

      <?php /* Yeni başvurular: kabul kararı baş editörlerindir, ama
               kararın verildiği yer editörlük sekmesidir. */ ?>
      <?php if ($basYetki): ?>
      <div class="pn-kart">
        <h2><?= k_c('Yazar başvuruları', 'Author applications') ?></h2>
        <?php /* "Tüm yazarların en az doktora unvanı taşıması sistemce
                 denetlenir" cümlesi buradaydı ve 13 Ağustos 2026 kurul
                 kararından sonra YANLIŞtı. Koşul cümlesi tek kaynaktan
                 okunur; şart geri açılırsa cümle de kendiliğinden döner. */ ?>
        <p class="pn-ack"><?= k_c(
          'Kabul edilen başvuruda tek bir çalışma için yazar erişimi açılır; bağlantı ve şifre burada bir kez görünür, yazara siz iletirsiniz. Ret kararı çalışmayı silmez, başvuru kaydına gerekçe düşer.',
          'When an application is accepted, author access is opened for that one work; the link and the password appear here once and you pass them on. A rejection deletes nothing; the reason is recorded on the application.'
        ) ?></p>
        <?php $ybKosul = tg_yazarlik_kosulu_kisa(k_en()); if ($ybKosul !== ''): ?>
        <p class="pn-ack"><b><?= k_c('Yürürlükteki koşul:', 'The condition in force:') ?></b> <?= k_esc($ybKosul) ?></p>
        <?php endif; ?>
        <p class="pn-ack"><a href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkelerini aç', 'Open the editorial policies') ?></a>
          <?= k_c('Ön inceleme yaparken bakılacak ölçütler orada durur.', 'The criteria to look at during the first assessment stand there.') ?></p>
        <?php /* BAŞVURU LİSTESİ SINIRSIZDI VE ÖLÇÜLDÜ: 262 başvuruluk
                 sınama verisinde bu kart 77.629 PİKSEL, Editör sekmesi
                 79.723 piksel oldu. Yani baş editörün paneli yetmiş dokuz
                 bin piksel uzunluğundaydı ve bunu kimse ölçmemişti, çünkü
                 kart yalnız baş editöre basılıyor.

                 Liste artık çalışma listesiyle aynı kuralı izliyor:
                 kapaklı satırlar, sınırlı sayı, arama. Kapağın üstünde
                 kimin başvurduğu, hangi çalışma için ve hangi durumda
                 olduğu yazılı. */ ?>
        <label for="ybAra"><?= k_c('Başvuru ara', 'Search applications') ?></label>
        <input type="text" id="ybAra" placeholder="<?= k_c('Ad, kurum ya da çalışma başlığı', 'Name, institution or work title') ?>">
        <div id="yonBasListe" style="margin-top:14px"></div>
        <div class="form-msj" id="yonBasMsj"></div>
      </div>

      <!-- Editör: çalışmalar -->
      <?php endif; ?>

      <div class="pn-kart pn-tam gizli" id="kartCalisma">
        <h2><?= k_c('Çalışmalar ve hakem atama', 'Works and reviewer assignment') ?></h2>
        <p class="pn-ack"><?= k_c(
          'İstediğiniz çalışmaya hakem atayabilir, editöryal not düşebilir ve gönüllü hakem başvurularını karara bağlayabilirsiniz. Yaptığınız her işlem adınız ve saatiyle birlikte çalışmanın sayfasında görünür. Kendi çalışmanıza hakem atayamazsınız.',
          'You may assign a reviewer to any work, add an editorial note, and decide on volunteer applications. Every action you take appears on the work\'s page with your name and the time. You may not assign reviewers to your own work.'
        ) ?></p>
        <?php /* DENEME DÜZENİNE BURADAN GİDİLİR.
                 Bildirilen kusur: "deneme yapamıyorum ben" — ve adres
                 çubuğuna /yonetim/deneme-kur yazılmış. O bir uçtur,
                 sayfa değil; 404 doğru cevaptır. Ama bir insanın adres
                 uydurmaya kalkması, aradığı düğmeyi bulamadığının
                 kanıtıdır.

                 Düğme vardı ve çalışıyordu: Yönetim sekmesinin en
                 altında, sayfanın 2752 piksel aşağısında, kapalı bir
                 kapağın arkasında. Araçların altta durması bilerek
                 seçilmiş bir kuraldır (bakılan bir kart ile ÇAĞRILAN bir
                 araç aynı yerde duramaz) ve o kural değişmedi. Değişen
                 şey şu: SORU NEREDE DOĞUYORSA CEVAP DA ORADA DURUR.
                 "Hakem atamayı nasıl denerim" sorusu burada doğuyor.

                 Bağlantı yalnızca baş editöre basılır; deneme kartı da
                 zaten yalnız ona açılıyor. Görünüp basılınca 403 dönen
                 bir bağlantı, sistemin kırık olduğunu düşündürür. */ ?>
        <?php if ($basYetki): ?>
        <p class="pn-ack" style="margin-bottom:var(--b-3)"><?= k_c(
          'Bu akışı gerçek bir çalışma üzerinde denemek istemiyorsanız, deneme düzeni üç sahte hesap ve hakem bekleyen bir deneme çalışması açar; deneme kayıtları arşivde hiçbir yerde görünmez.',
          'If you would rather not try this flow on a real work, the demo set-up creates three fictitious accounts and a demo work awaiting a reviewer; demo records appear nowhere in the archive.'
        ) ?> <button type="button" class="pn-bag-dg" id="dgDenemeGit" style="margin-left:0"><?= k_c('Deneme düzenine git', 'Go to the demo set-up') ?></button></p>
        <?php endif; ?>
        <label for="calAra"><?= k_c('Çalışma ara', 'Search works') ?></label>
        <input type="text" id="calAra" placeholder="<?= k_c('Başlık ya da yazar', 'Title or author') ?>">
        <div class="pn-liste" id="calListe" style="margin-top:14px"></div>
        <div class="form-msj" id="calMsj"></div>
      </div>


      <?php /* Hakemlik süreçleri: yalnızca okunur.
               Hakem ATAMA burada değildir, Editör sekmesindedir. Aynı işin
               iki yolu olmaz; olduğunda biri ötekinin denetimlerini
               taşımaz. Editördeki yol davet tarihini ve durumunu yazar,
               askıya alınmış hakemi engeller, editörün kendi çalışmasına
               atamasını engeller, yazarı hakem yapmaz, daveti postalar ve
               bağlantı ile şifreyi yine size geri verir.

               Burada duran şey oradan alınamayan tek şeydir: davet
               edilmiş bir hakemin bağlantısı ve şifresi. Posta gitmediyse
               ya da hakem bağlantıyı yitirdiyse buradan yeniden iletilir.

               Assignment is not here but under the Editor tab. What
               remains here is the one thing not available there: an
               already invited reviewer's link and password, for when the
               message did not arrive. */ ?>
      <?php if ($basYetki): ?>
      <div class="pn-kart">
        <h2><?= k_c('Hakemlik süreçleri', 'Reviewing in progress') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Bütün çalışmalardaki hakemler, kararları ve raporlarının başı. Bir hakemin bağlantısı ya da şifresi ona ulaşmadıysa buradan yeniden iletebilirsiniz. Yeni hakem atamak için Editör sekmesindeki "Çalışmalar ve hakem atama" bölümünü kullanın; orada alanına göre sıralanmış bir hakem havuzu da vardır.',
          'The reviewers on every work, their decisions and the beginning of their reports. If a reviewer\'s link or password did not reach them, you can pass it on again from here. To invite a new reviewer, use "Works and reviewer assignment" under the Editor tab, where there is also a pool of reviewers ordered by field.'
        ) ?></p>
        <?php /* Düğme ailesinden, kart başlığındaki "tümü" bağlantısından
                 değil: burası bir yan bakış değil, hakem atamanın asıl
                 yoludur ve dokunma hedefi ölçüsünde olmalıdır. */ ?>
        <button type="button" class="d d-ikinci d-kucuk" data-sek-git="editor"><?= k_c('Çalışmalar ve hakem atamaya git', 'Go to works and reviewer assignment') ?></button>
        <?php /* LİSTE SINIRSIZDI VE ÖLÇÜLDÜ: 45 hakemli çalışmanın bütün
                 hakemleri, raporlarının ilk 600 harfi ve bağlantı-şifre
                 kutularıyla birlikte açık çiziliyordu — kart 49.002
                 piksel. Yazar başvuruları kartıyla aynı satırda durduğu
                 için o da 49.002 ölçülüyor ve suçlu o sanılıyordu.

                 Aynı kural: kapaklı satır, sınırlı sayı, arama. Kapağın
                 üstünde çalışmanın adı, kaç hakemi olduğu ve kaçının
                 raporunun beklendiği yazılı. Rapor bekleyen çalışma
                 kendiliğinden açık gelir. */ ?>
        <label for="hkAra"><?= k_c('Çalışma ya da hakem ara', 'Search works or reviewers') ?></label>
        <input type="text" id="hkAra" placeholder="<?= k_c('Başlık, hakem adı ya da kimlik', 'Title, reviewer name or identifier') ?>">
        <div id="hkListe" style="margin-top:14px"></div>
      </div>

      <!-- Editör: belge onayı -->
      <?php endif; ?>

      <div class="pn-kart gizli" id="kartEditor">
        <h2><?= k_c('Doktora belgesi bekleyenler', 'Awaiting credential verification') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Onayladığınız her belge, onaylayanın adıyla birlikte kayda geçer. Onaylanan kişi hakem olur; hakemlik, kurul üyeliğini ve ilerideki baş editörlüğü kazandıran kaydı da kurar.',
          'Every credential you approve is recorded with the name of whoever approved it. The approved person becomes a reviewer; reviewing also builds the record that earns board membership and, in time, chief editorship.'
        ) ?></p>
        <div class="pn-liste" id="edListe"></div>
        <div class="form-msj" id="edMsj"></div>
      </div>

      <!-- Editör: alan önerileri -->
      <?php /* Sınıflandırmanın üçüncü basamağı herkese açıktır: önerilen
               dallar burada karara bağlanır. Reddedilen öneri de kayıtta
               kalır ve numarası bir daha kullanılmaz. */ ?>
      <div class="pn-kart gizli" id="kartAlan">
        <h2><?= k_c('Önerilen bilim dalları', 'Proposed fields') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Sınıflandırmanın ilk iki basamağı uluslararası ölçüte bağlıdır ve değişmez. Üçüncü basamak bu sisteme aittir: listede olmayan bir dalı herkes önerebilir. Onayladığınız dal listeye girer ve seçilebilir olur; onaylamadığınız öneri de kayıtta kalır, silinmez. Numara bir kez verilir, geri alınmaz.',
          'The first two levels of the classification follow an international standard and do not change. The third level belongs to this system: anyone may propose a branch that is missing. A branch you approve enters the list and becomes selectable; a proposal you do not approve also stays on the record and is not deleted. A number is issued once and never withdrawn.'
        ) ?></p>
        <div class="pn-liste" id="alListe"></div>
        <div class="form-msj" id="alMsj"></div>
      </div>

      <?php /* Değerlendirme süreleri editörlüğün kendi ölçümüdür:
               hakem ne kadar bekletti, karar nasıl dağıldı. */ ?>
      <?php if ($edYetki): ?>
      <?php /* ---------------------------------------------------------
           ARŞİV BOŞLUKLARI · SALT OKUNUR

           ÖLÇÜLEN KUSUR — 18 Ağustos 2026. İki eksik biliniyordu ama
           hiçbir yerde SAYILMIYORDU: etik beyanı hiç istenmemiş arşiv
           çalışmaları ve ortak yazarı adressiz kaydedilmiş çalışmalar.
           Ölçülmeyen eksik ya abartılır ya unutulur; ikisi de karar
           vermeyi engeller.

           BU KARTTA GÖNDER DÜĞMESİ YOKTUR VE BİLEREK YOKTUR. Eksiği
           kapatmanın yolu gerçek kişilere posta yazmaktan geçer; posta
           geri alınamaz. Geri alınamaz bir işi bir raporun yanına
           iliştirmek, onu kazayla yapılabilir kılar. Kart yalnız sayar.

           KAPALI DOĞAR: bu bir iş değil bir rapordur, editör buna ara
           sıra bakar. Kapağın üstünde iki sayı durur; sıfırsa açacak
           bir şey olmadığı da oradan görülür. */ ?>
      <?php /* ---------------------------------------------------------
           KURUL KARARLARI
           KURUL BİLDİRİMİ — 19 Ağustos 2026: "kurucu danışma kurulu bir
           karara yazdı, sisteme onu nerede yazacak? Orası yok. Diğerleri
           nasıl oylayacak? O da yok."

           Doğruydu ve ağır bir kusurdu: bu sistemin kuralları onlarca
           yerde kurulun kararına dayanıyor (baş editörlüğün çoğunluk
           kararıyla sona ermesi, bir metnin ilkelere uygunluğunun kurulca
           karara bağlanması, hakemlik yetkisinin askıya alınması). Yani
           sistem, kendisini yöneten yordamı ilan ediyor ama o yordamı
           yürütecek hiçbir yer taşımıyordu.

           ÇALIŞMA OYLAMASIYLA KARIŞTIRILMAZ: yazi.php'deki oylama bir
           ÇALIŞMA hakkındadır ve o çalışmanın sayfasında durur. Buradaki
           karar kurulun kendisi hakkındadır. İkisini birleştirmek,
           kaldırılan oylama.php'nin kusurunu geri getirirdi.

           SAYIM AÇIKTIR: kim ne oy verdi ve neden, kurul sayfasında
           herkese görünür. Kapalı sayım, denetlenemeyen bir kurul
           demektir. --------------------------------------------- */ ?>
      <details class="pn-kart pn-tam pn-katla gizli" id="kartKarar">
        <summary>
          <h2><?= k_c('Kurul kararları', 'Board decisions') ?></h2>
          <span id="kkOzet"></span>
        </summary>
        <div class="pn-katla-ic">
          <p class="pn-ack"><?= k_c(
            'Kurulun kendisi hakkındaki kararlar burada yazılır ve oylanır. Karar açıldıktan sonra metni değişmez, verilen oy geri alınmaz ve kayıt silinmez; kurul sayfası bunu zaten ilan ediyor. Oy gerekçesiyle verilir ve gerekçe adınızla birlikte kurul sayfasında görünür. Çoğunluk, oy verenlerin değil OY VEREBİLECEKLERİN yarısından fazlasıdır.',
            'Decisions about the board itself are written and voted on here. Once a decision is opened its text does not change, a vote once cast is not withdrawn and the record is not deleted; the board page already declares this. A vote is cast with its reasoning, and that reasoning appears with your name on the board page. A majority means more than half of those entitled to vote, not of those who voted.'
          ) ?></p>

          <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Yeni karar aç', 'Open a new decision') ?></h3>
          <label for="kkTur"><?= k_c('Tür', 'Kind') ?></label>
          <select id="kkTur" class="gir">
            <?php foreach (tg_kk_turleri() as $tk => $ta): ?>
            <option value="<?= k_esc($tk) ?>"><?= k_esc(k_en() ? $ta['en'] : $ta['tr']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="pn-ack" id="kkTurAck"><?= k_c(
            'Kurul kararını görevdeki baş editörler, kurucu kararını yalnız kurucu baş editörler oylar. Ayrım kurulun kendi kurallarından gelir: kurucu sıfatı sonradan verilemez.',
            'A board decision is voted on by the chief editors in office; a founders\' decision only by the founding chief editors. The distinction comes from the board\'s own rules: the founder status cannot be conferred later.'
          ) ?></p>
          <label for="kkBaslik"><?= k_c('Başlık', 'Title') ?></label>
          <input type="text" id="kkBaslik" maxlength="200">
          <label for="kkMetin"><?= k_c('Kararın metni ve gerekçesi', 'The text of the decision and its reasoning') ?></label>
          <textarea id="kkMetin" rows="6" maxlength="12000" placeholder="<?= k_c('Neyin oylandığı, neden oylandığı ve kabul edilirse ne değişeceği. En az 200 karakter.', 'What is being voted on, why, and what changes if it is adopted. At least 200 characters.') ?>"></textarea>
          <div class="d-kume">
            <button type="button" class="d d-vurgu d-kucuk" id="kkAc"><?= k_c('Kararı aç ve oylamayı başlat', 'Open the decision and start the vote') ?></button>
          </div>
          <div class="form-msj" id="kkMsj"></div>

          <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Açık ve sonuçlanmış kararlar', 'Open and concluded decisions') ?></h3>
          <div id="kkListe"></div>
        </div>
      </details>

      <details class="pn-kart pn-tam pn-katla" id="kartBosluk">
        <summary><h2><?= k_c('Arşiv boşlukları', 'Gaps in the archive') ?></h2>
          <span id="bsOzet"></span></summary>
        <div class="pn-katla-ic">
        <p class="pn-ack" id="bsAck"></p>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Etik beyanı olmayan çalışmalar', 'Works without an ethics declaration') ?></h3>
        <div class="tablo-sar"><table class="tb" id="bsEtik"></table></div>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Adresi bilinmeyen ortak yazarlar', 'Co authors whose address is not known') ?></h3>
        <p class="pn-ack"><?= k_c(
          'Bu kişilere yazarlıkları hiç bildirilmedi; adresleri kayıtta yok. Adreslerin kendisi bu rapora girmez, yalnızca eksik olduğu bilgisi girer.',
          'These people were never told of their authorship; their addresses are not in the record. The addresses themselves do not enter this report, only the fact that they are missing.'
        ) ?></p>
        <div class="tablo-sar"><table class="tb" id="bsOrtak"></table></div>

        <?php /* ---------------------------------------------------
             GİDECEK İLETİNİN ÖNİZLEMESİ — GÖNDERME DÜĞMESİ YOKTUR

             Bu iki eksiği kapatmak gerçek kişilere posta yazmayı
             gerektiriyor ve posta geri alınamaz. İşi bekleten şey de
             teknik bir eksik değil, bir KARARdı; karar verilebilmesi
             için görülmesi gereken üç şey vardı: ileti ne diyor, kaç
             kişiye gidiyor, gidemeyen var mı. Üçü de burada.

             Gönderme eylemi yazılmadı. Bir kararın önünü açmakla o
             kararı vermek aynı şey değildir: hazırlık geri alınabilir,
             gönderim değil. --------------------------------------- */ ?>
        <details id="bsOnizleme" style="margin-top:var(--b-5)">
          <summary><b><?= k_c('Gidecek iletiyi göster', 'Show the message that would be sent') ?></b></summary>
          <p class="pn-ack"><?= k_c(
            'Burada hiçbir ileti gönderilmez ve gönderecek bir düğme yoktur. Gösterilen şey, gönderilmesine karar verilirse gidecek olan iletinin tam metnidir. Adresler maskelenir: kaç kişiye gideceğini ve kime gidemeyeceğini bilmek yeterlidir.',
            'Nothing is sent here and there is no button that sends. What is shown is the full text of the message that would go out, should it be decided to send it. Addresses are masked: knowing how many it would reach, and whom it could not reach, is enough.'
          ) ?></p>
          <div id="bsOnizIc"></div>
        </details>
        <div class="form-msj" id="bsMsj"></div>
        </div>
      </details>
      <?php /* SÜRE RAPORU KAPAĞA ALINDI (704 piksel).
               Bu bir RAPORdur, bir iş değil: iki çizelge ve bir sayaç
               şeridi. Editör buna ara sıra bakar, her girişte değil.
               Kapağın üstünde kaç raporun ölçüldüğü yazar; sayı sıfırsa
               açacak bir şey olmadığı da oradan görülür. */ ?>
      <details class="pn-kart pn-tam pn-katla" id="kartSure">
        <summary><h2><?= k_c('Değerlendirme süreleri ve karar dağılımı', 'Assessment durations and the spread of decisions') ?></h2>
          <span id="srOzet"></span></summary>
        <div class="pn-katla-ic">
        <p class="pn-ack"><?= k_c(
          'Süre, hakemin davet edildiği gün ile raporunu yazdığı gün arasındaki gün sayısıdır. Yalnızca iki tarihi de bilinen raporlar sayılır. Bu sayılar bir başarı ölçüsü değildir; bir çalışmanın ne kadar beklediğini gösterir.',
          'The duration is the number of days between the day a reviewer was invited and the day the report was written. Only reports whose two dates are both known are counted. These numbers are not a measure of success; they show how long a work has waited.'
        ) ?></p>
        <div class="pn-sayac" id="srSayac"></div>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Kararlar', 'Decisions') ?></h3>
        <div class="tablo-sar"><table class="tb" id="srKarar"></table></div>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Raporu beklenen davetler', 'Invitations whose report is awaited') ?></h3>
        <div class="tablo-sar"><table class="tb" id="srBekleyen"></table></div>
        </div>
      </details>
      <?php endif; ?>

      </section><!-- /editor -->

      <?php /* ================= YÖNETİM =================
               Bu bölüm 8 Ağustos 2026'ya kadar /yonetim/index.html
               adresinde ayrı bir sayfaydı ve ayrı bir parolayla
               açılırdı. Yönetim işini yapanlar baş editörlerdir ve
               panelleri zaten burasıdır; ayrı sayfa gereksizdi.

               Bölümün tamamı sunucuda koşullu basılır: baş editör
               değilseniz aşağıdaki işaretlemenin hiçbiri sayfaya
               girmez. Yetki denetimi ayrıca uçlarda durur.

               Yayın ilkeleri metni buraya kopyalanmadı. Aynı metnin iki
               kopyası zamanla ikiye ayrılır ve hangisinin doğru olduğu
               belirsizleşir; ilkeler tek yerdedir ve oraya bağlanılır.

               Until 8 August 2026 this was a separate page with its own
               password. The people who do this work are chief editors
               and this is already their panel. The whole section is
               rendered conditionally on the server. */ ?>
      <?php if ($basYetki): ?>
      <section class="pn-pnl" data-pnl="yonetim">
      <?php /* SEKME BAŞLIĞI. Sekme çubuğundaki ad, çubuk kaydığında
               ya da dar ekranda görünmez olabiliyor; sayfanın neresinde
               olduğunu söyleyen bir başlık her zaman durur. Sunucuda
               basılır, betiksiz de görünür. */ ?>
      <header class="pn-bas2">
        <h2><?= k_c('Yönetim', 'Management') ?></h2>
        <p><?= k_c('Sitenin kendisi üzerine işler: hesaplar, sunucu, okunma.', 'Work about the site itself: accounts, the server, readership.') ?></p>
      </header>

      <?php /* Site yönetimi: hesaplar ve roller, sunucu, okunma. */ ?>
      <!-- Baş editör: hesap daveti ve editör listesi -->
      <?php /* Bu kart yalnızca baş editörlere ve yöneticiye açılır.

               PAROLA ÜRETİLMEZ. Davet edilen kişiye parola gönderilmez;
               tek kullanımlık bir bağlantı gönderilir ve kişi kendi
               parolasını kurar. Bir parolayı iletmek, onu yolda geçtiği
               her yerde bırakmaktır.

               Editör atama ayrı bir yetkidir ve süreli olabilir; süresi
               dolmuşsa aşağıdaki bölüm kapanır ama baş editörlük kaydı
               ve öteki yetkiler yerinde durur. */ ?>
      <?php /* Tam satır: yönetim sekmesindeki öteki üç kart da tam
               satır. Yarım sütunda bırakıldığında sağı boş kalıyordu
               (ölçüldü: satırın sağında 620 piksel boşluk), çünkü
               yanına eşleşecek yarım kart artık yok — sunucu durumu
               en alta, kapağın altına indi. */ ?>
      <div class="pn-kart pn-tam gizli" id="kartBas">
        <h2><?= k_c('Hesap daveti', 'Account invitation') ?></h2>
        <p class="pn-ack"><?= k_c(
          'Davet ettiğiniz kişiye parola gönderilmez. Sistem tek kullanımlık bir bağlantı üretir; siz bağlantıyı iletirsiniz, kişi kendi parolasını kurar. Bağlantı size bir kez gösterilir, on dört günde düşer ve bir kez kullanılır. Parolayı hiç kimse görmez, hiçbir yerde durmaz.',
          'No password is sent to the person you invite. The system produces a single use link; you pass the link on and the person sets their own password. The link is shown to you once, expires in fourteen days and works once. No one ever sees the password and it is stored nowhere.'
        ) ?></p>
        <div class="alan-ikili">
          <div><label for="dvAd"><?= k_c('Ad ve soyad', 'Name and surname') ?></label><input type="text" id="dvAd"></div>
          <div><label for="dvEposta"><?= k_c('E-posta', 'E mail') ?></label><input type="email" id="dvEposta" autocomplete="off"></div>
        </div>
        <div class="alan-ikili">
          <div>
            <label for="dvUnvan"><?= k_c('Unvan', 'Title') ?> <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı)', '(optional)') ?></small></label>
            <select id="dvUnvan">
              <option value=""><?= k_c('(yok)', '(none)') ?></option>
              <?php /* Değer anahtar, görünen ad metin; profil kutusuyla aynı. */ ?>
              <?php foreach ($UNVAN as $uk => $ua): ?><option value="<?= k_esc($uk) ?>"><?= k_esc($ua) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label for="dvKurum"><?= k_c('Kurum', 'Institution') ?> <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı)', '(optional)') ?></small></label><input type="text" id="dvKurum"></div>
        </div>
        <?php /* ---------- NEYE DAVET ----------
                 "Kurucu baş editörler 2027 sonuna kadar yeni baş editör,
                 editör ve hakem ekleyebilir; ama kendileri gibi KURUCU
                 ekleyemez."

                 Eskiden davet hiçbir rol vermiyordu: davet edilen kişi
                 rolsüz bir hesap alıyor, rol sonradan ayrı bir yerden
                 veriliyordu. Yani "kimi davet ediyorum" sorusunun cevabı
                 davet anında hiçbir yerde yazmıyordu.

                 Seçenekler sunucudan gelir ve KAPALI OLANLAR DA GELİR,
                 sebebiyle birlikte. Olmayan bir seçenek neden olmadığını
                 söylemez; kişi kendinde kusur arar. Görünüp basılınca
                 403 dönen bir seçenek de aynı kusurun tersidir — bu
                 yüzden kapalı olan görünür ama seçilemez.

                 Kapak: seçim yapılmadan da ne seçildiği başlıkta yazar,
                 açılınca gerekçeler görünür. */ ?>
        <?php /* KAPAK KAPALI DOĞAR ve bu bir ölçüm sonucudur: beş seçenek
                 açıkken "Hesap daveti" kartı 750'den 2060 piksele çıktı
                 ve Yönetim sekmesi tavanı aştı. İstenen zaten "açılır bir
                 pencere"ydi.

                 Kapalı olması bilgiyi gizlemiyor: kapağın üstünde HANGİ
                 rolün seçili olduğu yazılı durur ve düğmeye basmadan
                 önce görülür. Seçenekleri karşılaştırmak isteyen açar. */ ?>
        <details class="pn-grup pn-secim" id="dvRolKutu">
          <summary><b><?= k_c('Neye davet ediyorsunuz?', 'What are you inviting them to?') ?></b>
            <span id="dvRolOzet"></span></summary>
          <div class="pn-grup-ic" id="dvRolListe"></div>
        </details>
        <button class="d d-vurgu" type="button" id="dgDavet" style="margin-top:16px"><?= k_c('Davet bağlantısı üret', 'Produce an invitation link') ?></button>
        <div class="form-msj" id="dvMsj"></div>

        <div class="pn-bag-kutu gizli" id="dvSonuc">
          <p class="pn-bag-uyari" id="dvUyari"></p>
          <p class="kutu kutu-kut gizli" id="dvKurulNot"></p>
          <input type="text" id="dvBag" readonly onfocus="this.select()">
          <button class="d d-ikinci d-kucuk" type="button" id="dgDavetKopya"><?= k_c('Kopyala', 'Copy') ?></button>

          <?php /* Hazır davet metni. Bağlantıyı çıplak göndermek, karşı
                   tarafa neye davet edildiğini anlatmaz; kimi zaman da
                   kimlik avı gibi görünür. Metin burada hazır durur,
                   kopyalanır ve olduğu gibi ya da değiştirilerek
                   gönderilir. Sistem kendisi e-posta atmaz: daveti kimin
                   gönderdiği belli olsun diye bunu insan yapar. */ ?>
          <label for="dvMetin" style="margin-top:16px"><?= k_c('Gönderilecek ileti', 'The message to send') ?></label>
          <textarea id="dvMetin" rows="14" readonly onfocus="this.select()"></textarea>
          <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end">
            <button class="d d-ikinci d-kucuk" type="button" id="dgMetinKopya"><?= k_c('İletiyi kopyala', 'Copy the message') ?></button>
            <button class="d d-ikinci d-kucuk" type="button" id="dgWhats"><?= k_c('WhatsApp ile gönder', 'Send over WhatsApp') ?></button>
          </div>

          <?php /* WHATSAPP: DAVET İÇİN, PAROLA İÇİN DEĞİL.
                   İstenen buydu: "davetler whatsapptan da gidebilsin."
                   Yapılan, bir WhatsApp tümleşimi DEĞİL, bir devir
                   noktasıdır. Sistem hiçbir yere ileti göndermez;
                   düğme, hazır metni WhatsApp'ın kendi penceresine
                   taşır ve gönder düğmesine daveti eden kişi basar.
                   Gerekçesi üç maddedir:
                     1. WhatsApp'ın iş API'si bir işletme hesabı, bir
                        onaylı kalıp ve mesaj başına ücret ister.
                        Davet ayda birkaç kez gönderilir; bu altyapıyı
                        kurmak, kullanılmayacak bir bağımlılıktır.
                     2. Davetin kimden geldiği görünür kalır. Kurumsal
                        bir numaradan gelen bağlantı, tanımadığı bir
                        yerden bağlantı almış gibi durur; kurucunun
                        kendi numarasından gelen ise tanıdıktır.
                     3. Sunucuda hiçbir sır tutulmaz: ne bir API
                        anahtarı ne bir telefon oturumu.

                   PAROLA YENİLEME BURADAN GEÇMEZ ve geçmeyecektir.
                   Davet bağlantısı zaten yöneticinin elinden çıkar;
                   yenileme bağlantısı ise yalnızca hesabın KENDİ
                   adresine gider. Bir sıfırlama bağlantısını yöneticinin
                   panosuna düşürmek, kurtarma aracını hesap ele geçirme
                   aracına çevirir. */ ?>
          <label for="dvTel" style="margin-top:14px"><?= k_c('WhatsApp numarası', 'WhatsApp number') ?> <small style="font-weight:500;color:var(--metin-2)"><?= k_c('(isteğe bağlı, ülke koduyla: 905xxxxxxxxx)', '(optional, with country code: 905xxxxxxxxx)') ?></small></label>
          <input type="tel" id="dvTel" autocomplete="off" inputmode="numeric">
          <p class="pn-ack"><?= k_c(
            'WhatsApp düğmesi iletiyi göndermez; hazır metni WhatsApp penceresine taşır ve gönderme kararını size bırakır. Numara yazmazsanız alıcıyı WhatsApp içinde siz seçersiniz. Davet bağlantısı böyle gönderilebilir; parola yenileme bağlantısı gönderilemez, o yalnızca hesabın kendi e-posta adresine gider.',
            'The WhatsApp button does not send anything; it carries the prepared message into the WhatsApp window and leaves the decision to send with you. If you write no number you pick the recipient inside WhatsApp. An invitation link may be sent this way; a password reset link may not, as that goes only to the account\'s own e mail address.'
          ) ?></p>
        </div>

        <h2 style="margin-top:26px"><?= k_c('Editör listesi', 'The list of editors') ?></h2>
        <p class="pn-ack" id="dvEdAck"><?= k_c(
          'Editör, istediği çalışmaya hakem atayabilir ve editöryal not düşebilir. Verdiğiniz ve aldığınız her rol, adınız ve saatiyle birlikte kayda geçer; kurul sayfasındaki liste kendiliğinden güncellenir.',
          'An editor may assign a reviewer to any work and add an editorial note. Every role you give or take is recorded with your name and the time, and the list on the board page updates itself.'
        ) ?></p>
        <div class="pn-bag-uyari gizli" id="dvSureBitti"></div>
        <div id="dvEdKutu">
          <label for="dvAra"><?= k_c('Hesap ara', 'Search accounts') ?></label>
          <input type="text" id="dvAra" placeholder="<?= k_c('Ad, kurum ya da e-posta', 'Name, institution or e mail') ?>">
          <div class="pn-liste" id="dvListe" style="margin-top:14px"></div>
        </div>
        <div class="form-msj" id="dvRolMsj"></div>
      </div>

      <?php if ($edYetki): ?>
      <?php /* OKUMA RAPORLARI KAPAĞA ALINDI (1011 piksel).
               Yönetim sekmesinin en uzun kartıydı ve dört çizelge
               taşıyor. Bir RAPORdur: ara sıra bakılır, her girişte
               değil. Kapağın üstünde dönemin okuma sayısı yazar; sayı
               sıfırsa açacak bir şey olmadığı oradan görülür.

               NEDEN ÖNEMLİ: bu kartın uzunluğu yüzünden "Deneme
               düzeni" kartı sekmenin 2752 piksel aşağısında kalıyordu
               ve bulunamıyordu. Bir aracı gizleyen şey, o aracın
               kendisi değil üstündeki kalabalıktır. */ ?>
      <details class="pn-kart pn-tam pn-katla" id="kartRapor">
        <summary><h2><?= k_c('Okuma raporları', 'Reading reports') ?></h2>
          <span id="rpOzet"></span></summary>
        <div class="pn-katla-ic">
        <p class="pn-ack"><?= k_c(
          'Hangi çalışma nereden ve ne kadar süreyle okundu. Kayıt, okuyucu sayfadan ayrılırken toplanır; beş saniyeden kısa ziyaretler ve arama motoru robotları sayılmaz. Okuyucunun adresi kayda geri çevrilemeyecek biçimde özetlenerek girer, olduğu gibi saklanmaz; aynı okuyucunun tekrar açması tek okuma sayılır.',
          'Which work was read, from where and for how long. The record is gathered as the reader leaves the page; visits shorter than five seconds and search engine robots are not counted. The reader\'s address enters the record as an irreversible digest and is not stored as it is; the same reader opening a work again counts as one reading.'
        ) ?></p>
        <label for="rpAy"><?= k_c('Dönem', 'Period') ?></label>
        <div class="gir-grup">
          <select id="rpAy" class="gir"></select>
          <button type="button" class="d d-ikinci" id="rpYenile"><?= k_c('Yenile', 'Refresh') ?></button>
        </div>
        <div class="pn-sayac" id="rpSayac" style="margin-top:var(--b-4)"></div>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Çalışma başına', 'Per work') ?></h3>
        <div class="tablo-sar"><table class="tb" id="rpCalisma"></table></div>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Şehirler', 'Cities') ?></h3>
        <div class="tablo-sar"><table class="tb" id="rpSehir"></table></div>
        <h3 style="margin:var(--b-5) 0 var(--b-3)"><?= k_c('Ülkeler ve cihaz', 'Countries and device') ?></h3>
        <div class="tablo-sar"><table class="tb" id="rpUlke"></table></div>
        <div class="form-msj" id="rpMsj"></div>
        </div>
      </details>
      <?php endif; ?>


      <?php /* Tek tek okuma kayıtları. Toplu sayılar bütün editörlere
               açıktır; bu liste değildir ve sunucu da onu editöre giden
               yanıta koymaz. */ ?>
      <div class="pn-kart pn-tam">
        <h2><?= k_c('Son okuma kayıtları', 'The latest reading records') ?></h2>
        <p class="pn-ack"><?= k_c(
          'En yeni altmış kayıt. Toplu sayılar Editör sekmesindeki okuma raporlarındadır ve bütün editörlere açıktır; tek tek kayıtlar yalnızca burada durur. Okuyucunun adresi kaydedilmez.',
          'The sixty most recent records. The aggregate counts are in the reading reports under the Editor tab and are open to every editor; the individual records stand only here. The reader\'s address is not recorded.'
        ) ?></p>
        <div class="tablo-sar"><table class="tb" id="rpSon"></table></div>
      </div>

      <?php /* SUNUCU DURUMU EN ALTTA VE KAPALI DURUR.
               Ölçüt tahmin değil: bu kart açıldığında HİÇBİR ŞEY
               göstermez. İçindeki "Durumu getir" düğmesine basılana
               kadar #durumIc boştur. Yönetim sekmesindeki öteki üç
               kart ise açılır açılmaz kendi verisini gösterir (davet
               formu, okuma raporu, son kayıtlar). Bakılan bir kart ile
               ÇAĞRILAN bir araç aynı yerde duramaz: araç, sekmenin
               başında yer kaplayıp altındaki işi aşağı itiyordu.
               Kart silinmedi; kapağının altına alındı. */ ?>
      <?php /* DENEME DÜZENİ.
               Kurul isteği (M. Z. Tunca) ve yönetimin bildirimi:
               "hakem atamasını test bile edemedim." Bir yayın
               sisteminin en tehlikeli yeri hiç denenmemiş bir akıştır;
               hakem ataması ilk kez gerçek bir çalışmayla, gerçek bir
               hakemin gözü önünde denenirse çıkan her kusur bir
               kişinin emeğinin üstüne düşer.

               Kart KATLI durur ve açılır açılmaz hiçbir şey yapmaz:
               içindeki düğmeye basılana kadar kayıt üretilmez. Sahte
               kayıt üreten bir aracın kazayla çalışması olmamalıdır. */ ?>
      <details class="pn-kart pn-tam pn-katla gizli" id="kartDeneme">
        <summary>
          <h2><?= k_c('Deneme düzeni', 'Test setup') ?></h2>
          <span><?= k_c('Hakem atama akışını uçtan uca deneyin', 'Exercise the reviewer assignment flow end to end') ?></span>
        </summary>
        <div class="pn-katla-ic">
          <p class="pn-ack"><?= k_c(
            'Üç deneme hesabı (yazar, hakem, editör) ve hakem bekleyen bir deneme çalışması açar. Deneme kayıtları arşivde, listede, aramada, istatistikte, OAI çıktısında, site haritasında ve arşiv dökümünde GÖRÜNMEZ; süzgeç arşivin girişinde tek yerde durur. Deneme adresleri hiçbir posta sunucusunun teslim edemeyeceği bir alan adındadır, yani kimseye ileti gitmez. Parolalar bir kez gösterilir ve kayda yalnızca özetleri girer.',
            'Creates three test accounts (author, reviewer, editor) and one test work awaiting a reviewer. Test records do NOT appear in the archive, the list, search, the statistics, the OAI output, the site map or the archive dump; the filter sits at the entrance of the archive, in one place. The test addresses are on a domain no mail server can deliver to, so no one is sent anything. Passwords are shown once and only their digests are stored.'
          ) ?></p>
          <div style="display:flex;flex-wrap:wrap;gap:10px">
            <button type="button" class="d d-vurgu d-kucuk" id="dnKur"><?= k_c('Deneme düzenini kur', 'Create the test setup') ?></button>
            <button type="button" class="d d-ikinci d-kucuk" id="dnSil"><?= k_c('Deneme kayıtlarını sil', 'Delete the test records') ?></button>
          </div>
          <div id="dnIc" style="margin-top:14px"></div>
          <div class="form-msj" id="dnMsj"></div>

          <?php /* ---------------------------------------------------
               ESKİ BİR DENEME ÇALIŞMASINI ARŞİVDEN ÇIKARMAK

               KURUL BİLDİRİMİ (20 Ağustos 2026): "ben deneme gibi bir
               makale gönderdim ret ettim ama orada kalmasa; denemeyi
               düzgün yapamamıştık ya o zaman."

               Yerinde bir istek: deneme düzeni 19 Ağustos'ta açıldı,
               ondan önce sistemi denemenin tek yolu GERÇEK bir çalışma
               göndermekti. Silme değil İŞARET konur: kayıt yerinde
               kalır, kamusal her sayfadan tek süzgeçle düşer ve
               yanlışlık olursa geri alınır. Kalıcı silme, yukarıdaki
               "Deneme kayıtlarını sil" düğmesidir — ayrı ve bilinçli
               ikinci bir adım. Kapılar sunucuda; buradaki form onların
               yalnızca yüzüdür. --------------------------------------- */ ?>
          <div class="pn-alt-blok" id="dnIsaretBlok">
            <h3><?= k_c('Eski bir deneme çalışmasını arşivden çıkar', 'Take an old test work out of the archive') ?></h3>
            <p class="pn-ack"><?= k_c(
              'Deneme düzeninden önce gönderilmiş bir deneme çalışmanız varsa burada deneme kaydı olarak işaretlenir: arşivde, listede, aramada, istatistikte, OAI çıktısında, site haritasında ve dökümde görünmez olur. Kayıt SİLİNMEZ, geri alınabilir. Yalnızca kendi çalışmanız, DOI verilmemişse ve ona başka kimse (hakem, şerh yazan, ortak yazar) emek vermemişse işaretlenebilir.',
              'If you submitted a test work before the test setup existed, it can be marked as a test record here: it then disappears from the archive, the list, search, the statistics, the OAI output, the site map and the dump. The record is NOT deleted and the marking can be undone. Only your own work qualifies, only if it has no DOI and no one else (reviewer, note writer, co author) has put work into it.'
            ) ?></p>
            <label class="alan"><span><?= k_c('Çalışma', 'Work') ?></span>
              <select id="dnIsSec"><option value=""><?= k_c('— seçin —', '— choose —') ?></option></select></label>
            <label class="alan"><span><?= k_c('Gerekçe (kayda geçer, en az 20 karakter)', 'Reason (recorded, at least 20 characters)') ?></span>
              <textarea id="dnIsNeden" rows="2" maxlength="600"></textarea></label>
            <button type="button" class="d d-ikinci d-kucuk" id="dnIsYap"><?= k_c('Deneme kaydı olarak işaretle', 'Mark as a test record') ?></button>
            <div class="form-msj" id="dnIsMsj"></div>
            <div id="dnIsListe"></div>
          </div>

          <?php /* ---------------------------------------------------
               DENEME AKIŞI — ADIM ADIM, GERÇEK UÇLARDAN

               KURUL BİLDİRİMİ (19 Ağustos 2026): "hakeme gönderince
               hakem nasıl bir arayüz görecek, makale incelerken onlara
               bakmak istiyorum; inceledi revizyon istedi, yazar yaptı,
               diğer hakemin isteklerini de yaptı, yayımlanabilir
               dedikleri zaman nasıl görünüyor, aradaki iletişim nasıl
               gözüküyor, revize edilmiş yeni metin nasıl gözüküyor,
               bunları deneyemiyorum."

               ADIMLARI PANEL İŞLETİR, AMA KENDİ YOLUNDAN DEĞİL. Her
               adım sistemin GERÇEK ucunu çağırır: /editor/hakem-ata,
               /hakem-davet-yanit, /hakem-gonder, /yazar-hakem-yanit,
               /yazar-kaydet. Deneme için ikinci bir yol yazılsaydı,
               denenen şey sistemin kendisi olmazdı — bir denemenin tek
               değeri, denediği şeyin gerçek olmasıdır.

               AŞAMA SUNUCUDA HESAPLANIR, BURADA SAKLANMAZ. Sayfa
               yenilense de kapatılsa da deneme kaldığı yerden sürer;
               "her seferinde tekrar açmak" bu yüzden gerekmiyor artık.

               HER AŞAMADA "KİMİN GÖZÜNDEN" BAĞLARI DURUR: yazar, hakem
               ve okur ekranları ayrı ayrı açılır. Bildirimin asıl
               istediği buydu: akışı işletmek değil, GÖRMEK.
               --------------------------------------------------- */ ?>
          <div id="dnAkis" hidden style="margin-top:var(--b-5);border-top:1px solid var(--cizgi);padding-top:var(--b-4)">
            <h3 style="margin:0 0 var(--b-2)"><?= k_c('Deneme akışı', 'The test run') ?></h3>
            <p class="pn-ack"><?= k_c(
              'Her adım sistemin gerçek ucundan geçer; deneme için ayrı bir yol yoktur. Adımı işlettikten sonra aşağıdaki bağlarla aynı anı yazarın, hakemin ve okurun gözünden açabilirsiniz.',
              'Every step goes through the system\'s real endpoint; there is no separate path for the test. After running a step you can open the same moment through the links below, from the author\'s, the reviewer\'s and the reader\'s eyes.'
            ) ?></p>
            <ol class="dn-merdiven" id="dnMerdiven"></ol>
            <div class="d-kume" style="margin-top:var(--b-3)">
              <button type="button" class="d d-vurgu d-kucuk" id="dnIleri"></button>
              <button type="button" class="d d-sessiz d-kucuk" id="dnYenile"><?= k_c('Durumu tazele', 'Refresh state') ?></button>
            </div>
            <div class="dn-bak" id="dnBak"></div>
            <div class="form-msj" id="dnAkisMsj"></div>

            <?php /* ---- RET YOLU ----
                 Ret, kabulün aynası değildir; kendi kuralları vardır ve
                 en pahalısı geri alınamaz: iki ret alan çalışma
                 KİLİTLENİR, yazar metnini bir daha değiştiremez ve
                 çalışma ret gerekçeleriyle birlikte YAYINDA KALIR. Bir
                 yayın sisteminin en ağır kuralı budur ve hiç
                 denenmemişti.

                 AYRI BİR KAYITTA yürür: bir çalışma ya kabul yolunu
                 yürür ya ret yolunu; ikisini tek kayıtta denemek
                 ikisini de yarım denemek olurdu. */ ?>
            <div id="dnRet" hidden style="margin-top:var(--b-5);border-top:1px solid var(--cizgi);padding-top:var(--b-4)">
              <h3 style="margin:0 0 var(--b-2)"><?= k_c('Ret yolu', 'The rejection path') ?></h3>
              <p class="pn-ack"><?= k_c(
                'Ayrı bir deneme çalışmasında yürür. İki ret alan çalışma kilitlenir: yazar metnini bir daha değiştiremez ve çalışma ret gerekçeleriyle birlikte yayında kalır. Sistemin en ağır kuralı budur; silmek değil, gerekçesiyle bırakmak.',
                'It runs on a separate test work. A work that receives two rejections is locked: the author can no longer change the text, and the work stays published together with the grounds for rejection. This is the system\'s heaviest rule: not deletion, but leaving it with its reasoning.'
              ) ?></p>
              <ol class="dn-merdiven" id="dnRetMerdiven"></ol>
              <div class="d-kume" style="margin-top:var(--b-3)">
                <button type="button" class="d d-ikinci d-kucuk" id="dnRetIleri"></button>
              </div>
              <div class="dn-bak" id="dnRetBak"></div>
              <div class="form-msj" id="dnRetMsj"></div>
            </div>
          </div>
        </div>
      </details>

      <?php /* ---------------------------------------------------
           ZENODO · KALICI KİMLİK (DOI)
           KURUL SORUSU (20 Ağustos 2026): "Zenodo'dan her eklenen yazı
           için DOI alınabilir mi?" Alınabilir; kart bunun içindir.

           KARTIN DÜZENİ, İŞİN AĞIRLIĞINI ANLATIR: önizleme ağa hiç
           çıkmaz, taslak geri alınabilir, yayımlama geri alınamaz ve
           ayrı bir onay ister. Üç iş üç ayrı düğmedir; tek düğmede
           toplansaydı geri alınamaz olan, alınabilir olanın arkasına
           gizlenmiş olurdu. --------------------------------------- */ ?>
      <details class="pn-kart pn-tam pn-katla gizli" id="kartZenodo">
        <summary>
          <h2><?= k_c('Kalıcı kimlik · Zenodo (DOI)', 'Persistent identifier · Zenodo (DOI)') ?></h2>
          <span id="znOzet"></span>
        </summary>
        <div class="pn-katla-ic">
          <p class="pn-ack"><?= k_c(
            'Zenodo (CERN) yayımlanan her kayda kalıcı bir kimlik (DOI) verir; kurum, üyelik ve ücret istemez. Üç şey bilinerek kullanılır: (1) DOI Zenodo kaydına çözülür, kutadgu.net\'e değil; (2) <b>yayımlanmış kayıt silinemez</b> ve DOI kalıcıdır; (3) kayıt en az bir dosya ister — çalışmanın metni tek dosyalık HTML ve makine okunur JSON olarak konur. Bu yüzden taslak kendiliğinden hazırlanabilir, ama <b>yayımlama her zaman elle</b> yapılır.',
            'Zenodo (CERN) gives every published record a persistent identifier (DOI); no institution, membership or fee is required. Three things must be known: (1) the DOI resolves to the Zenodo record, not to kutadgu.net; (2) <b>a published record cannot be deleted</b> and the DOI is permanent; (3) a record needs at least one file — the work\'s text goes in as a single self contained HTML plus machine readable JSON. A draft may therefore be prepared automatically, but <b>publishing is always done by hand</b>.'
          ) ?></p>
          <label class="alan"><span><?= k_c('Zenodo jetonu (parolanız gibidir; buraya siz yapıştırırsınız)', 'Zenodo token (it is like your password; you paste it here)') ?></span>
            <input type="password" id="znJeton" autocomplete="off" placeholder="****"></label>
          <label class="alan"><span><?= k_c('Zenodo topluluğu (isteğe bağlı)', 'Zenodo community (optional)') ?></span>
            <input type="text" id="znTopluluk" autocomplete="off"></label>
          <label class="onay"><input type="checkbox" id="znSandbox"> <span><?= k_c('Deneme evreni (sandbox) — verilen kimlikler gerçek değildir', 'Sandbox — the identifiers it issues are not real') ?></span></label>
          <label class="onay"><input type="checkbox" id="znAcik"> <span><?= k_c('Zenodo düzeni açık', 'Zenodo setup enabled') ?></span></label>
          <div class="d-kume" style="margin-top:var(--b-3)">
            <button type="button" class="d d-ikinci d-kucuk" id="znKaydet"><?= k_c('Kaydet', 'Save') ?></button>
            <button type="button" class="d d-ikinci d-kucuk" id="znSina"><?= k_c('Jetonu sına', 'Test the token') ?></button>
          </div>
          <div class="form-msj" id="znMsj"></div>
          <div id="znListe"></div>
        </div>
      </details>

      <details class="pn-kart pn-tam pn-katla gizli" id="kartDurum">
        <summary>
          <h2><?= k_c('Sunucu durumu', 'Server status') ?></h2>
          <span><?= k_c('Posta yolu, yazma izinleri, eklentiler', 'Mail path, write permissions, extensions') ?></span>
        </summary>
        <div class="pn-katla-ic">
          <p class="pn-ack"><?= k_c(
            'Bir yayın sisteminin kendi arızasını görebilmesi, o arızayı gizlememesi kadar önemlidir. Burada sunucunun posta yolu, dosya yazma izinleri ve eklenti desteği görünür. Bu bölüm yalnızca okur; hiçbir şeyi değiştirmez. Posta sınaması yaparsanız ileti yalnızca kendi kayıtlı adresinize gider.',
            'A publishing system being able to see its own failures matters as much as not hiding them. This shows the server\'s mail path, file write permissions and extension support. This section only reads; it changes nothing. If you run the mail test, the message goes only to your own registered address.'
          ) ?></p>
          <div style="display:flex;flex-wrap:wrap;gap:10px">
            <button type="button" class="d d-ikinci d-kucuk" id="durumYenile"><?= k_c('Durumu getir', 'Fetch status') ?></button>
            <button type="button" class="d d-ikinci d-kucuk" id="durumPosta"><?= k_c('Posta yolunu sına', 'Test the mail path') ?></button>
          </div>

          <?php /* POSTA AKTARICISI.
                   Ölçülen kusur: bu sunucudan çıkan ileti alıcıya
                   varmıyor. Sebep sunucunun kendisi değil, kimliğinin
                   olmaması: kutadgu.net'in MX, SPF, DKIM ve DMARC kaydı
                   yok, sunucunun PTR kaydı da yok. Böyle bir sunucudan
                   gelen iletiyi Gmail ve Outlook ya gereksiz posta
                   klasörüne atar ya da sessizce düşürür.

                   Bu alanlar doldurulduğunda ileti dışarıdaki bir
                   aktarıcıya teslim edilir ve kimlik doğrulaması onun
                   işi olur. Bizde bakımı olan hiçbir şey kalmaz;
                   "sistemi kasmasın" sınırı budur.

                   PAROLA GERİ GÖSTERİLMEZ. Kayıtlıysa yıldızla görünür;
                   kutuyu boş bırakıp kaydetmek onu silmez. */ ?>
          <h3 style="margin:24px 0 8px"><?= k_c('Posta aktarıcısı', 'Mail relay') ?></h3>
          <p class="pn-ack"><?= k_c(
            'Sistem kendi sunucusundan posta göndermez. İleti burada yazılı aktarıcıya teslim edilir; gönderenin doğrulanması (SPF, DKIM) aktarıcının işidir. Boş bırakılırsa sunucunun kendi posta yolu denenir, ama bu sunucuda o yolun çalışmadığı ölçüldü: davet ve parola yenileme iletileri alıcıya varmaz. Buraya yazılan parola bu sunucudaki veri dizininde durur, kaynak deposuna girmez ve ekrana geri getirilmez.',
            'The system does not send mail from its own server. Messages are handed to the relay written here; authenticating the sender (SPF, DKIM) is the relay\'s job. If left empty the server\'s own mail path is tried, but that path was measured not to work on this server: invitations and password resets do not reach the recipient. The password written here stays in the data directory of this server, never enters the source repository and is never shown back on screen.'
          ) ?></p>
          <?php /* HAZIR AYAR DÜĞMESİ.
                   Ölçülen kusur değil, ölçülen RİSK: bu formda yanlış
                   yazılabilecek beş alan var (sunucu adı, kapı, güvenlik
                   kipi, kullanıcı adı, gönderen adres) ve beşinin de
                   yanlış olması hâlinde ekranda çıkan tek şey "teslim
                   reddedildi" oluyor. Kurulumu yapan kişi hangi alanın
                   yanlış olduğunu göremiyor.

                   Bu yüzden aktarıcının SABİT olan alanları düğmeye
                   bağlandı. Kutadgu'nun kullandığı aktarıcı Lettermint;
                   sunucu adı, kapı ve güvenlik kipi o hizmet için
                   değişmez değerlerdir, kullanıcı adı da her projede
                   düz "lettermint"tir — değişken olan tek şey paroladır
                   (proje API belirteci).

                   PAROLA BU DÜĞMEYE YAZILMADI ve yazılmayacak. Bir
                   parolayı kaynak koduna gömmek, onu depoyu okuyan
                   herkese vermek demektir; bu depo herkese açıktır.
                   Düğme parola kutusunu boş bırakır ve imleci oraya
                   götürür: yapıştırma işini insan yapar. */ ?>
          <div style="margin:0 0 12px">
            <button type="button" class="d d-ikinci d-kucuk" id="smOnAyar"><?= k_c('Lettermint ayarlarını doldur', 'Fill in the Lettermint settings') ?></button>
            <span class="pn-ack" style="display:block;margin-top:6px"><?= k_c(
              'Sunucu, kapı, güvenlik kipi ve kullanıcı adını doldurur. Parolayı doldurmaz: parola, Lettermint panelindeki proje API belirtecidir ve onu buraya yalnızca siz yapıştırabilirsiniz.',
              'Fills in the host, port, security mode and user name. It does not fill the password: the password is the project API token from the Lettermint panel and only you can paste it here.'
            ) ?></span>
          </div>
          <div class="alan-ikili">
            <div><label for="smSunucu"><?= k_c('Aktarıcı sunucu', 'Relay host') ?></label><input type="text" id="smSunucu" autocomplete="off" placeholder="smtp.lettermint.co"></div>
            <div><label for="smPort"><?= k_c('Kapı', 'Port') ?></label><input type="number" id="smPort" value="587" min="1" max="65535"></div>
          </div>
          <div class="alan-ikili">
            <div><label for="smKullanici"><?= k_c('Kullanıcı adı', 'User name') ?></label><input type="text" id="smKullanici" autocomplete="off"></div>
            <div><label for="smParola"><?= k_c('Parola', 'Password') ?></label><input type="password" id="smParola" autocomplete="new-password"></div>
          </div>
          <div class="alan-ikili">
            <div>
              <label for="smGuvenlik"><?= k_c('Güvenlik', 'Security') ?></label>
              <select id="smGuvenlik">
                <option value="tls">STARTTLS (587)</option>
                <option value="ssl">SSL/TLS (465)</option>
                <option value="yok"><?= k_c('yok (önerilmez)', 'none (not advised)') ?></option>
              </select>
            </div>
            <div><label for="smGonderen"><?= k_c('Gönderen adres', 'From address') ?></label><input type="email" id="smGonderen" autocomplete="off" placeholder="<?= k_c('bildirim@alanadınız', 'notice@yourdomain') ?>"></div>
          </div>
          <div class="alan-ikili">
            <div><label for="smGonderenAd"><?= k_c('Gönderen adı', 'From name') ?></label><input type="text" id="smGonderenAd" autocomplete="off" placeholder="Kutadgu"></div>
            <div><label for="smYanit"><?= k_c('Yanıt adresi', 'Reply to address') ?></label><input type="email" id="smYanit" autocomplete="off"></div>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:14px">
            <button type="button" class="d d-vurgu d-kucuk" id="smKaydet"><?= k_c('Aktarıcıyı kaydet', 'Save the relay') ?></button>
          </div>
          <div class="form-msj" id="smMsj"></div>
          <div id="durumIc" style="margin-top:14px"></div>
          <div class="form-msj" id="durumMsj"></div>

          <?php /* İZLEME. Ayrı bir yönetici paneli açılmadı; gerekçesi
                   api/index.php'de /yonetim/izleme ucunun başındadır.
                   Kısacası: bu sistemin ikinci bir yönetim kapısı vardı
                   ve kaldırıldı, çünkü ikinci kapı ikinci parola ve
                   ikinci saldırı yüzeyi demektir. Bu ayın asıl arızası
                   da kurulun sisteme girememesiydi; bir giriş sorunu
                   ikinci bir giriş eklenerek çözülmez.

                   "ÇEVRİMİÇİ" YAZMIYOR ve yazmayacak. Web'de kimse
                   bağlı değildir; yalnızca en son ne zaman bir sayfa
                   istediği bilinir. Ekranda ölçülen şey ne ise o yazar:
                   son görülme. */ ?>
          <h3 style="margin:24px 0 8px"><?= k_c('İzleme', 'Monitoring') ?></h3>
          <p class="pn-ack"><?= k_c(
            'Son görülen hesaplar, kayda geçen hatalar ve bildirim kütüğü. "Çevrimiçi" diye bir ölçü yoktur: bir kişinin bağlı olup olmadığı bilinemez, yalnızca en son ne zaman bir sayfa istediği bilinir ve burada yazan odur. Kimin hangi sayfaya baktığı tutulmaz, adres tutulmaz; yalnızca ad ve saat tutulur, otuz günde düşer.',
            'Recently seen accounts, recorded faults and the notification log. There is no such measure as "online": whether a person is connected cannot be known, only when they last asked for a page, and that is what is shown. What anyone looked at is not recorded, nor their address; only a name and a time, dropped after thirty days.'
          ) ?></p>
          <button type="button" class="d d-ikinci d-kucuk" id="izYenile"><?= k_c('İzlemeyi getir', 'Fetch monitoring') ?></button>
          <div id="izIc" style="margin-top:14px"></div>
          <div class="form-msj" id="izMsj"></div>
        </div>
      </details>


      </section><!-- /yonetim -->
      <?php endif; ?>

    </div>
  </div>
</section>

<?php
$S = json_encode([
  'baglanti' => k_c('Bağlantı kurulamadı. Tekrar deneyin.', 'Could not connect. Please try again.'),
  /* "Bağlantı kurulamadı" YALNIZCA gerçekten bağlantı kurulamadığında
     yazmalıdır. Eskiden JSON olmayan HER yanıt bu cümleye düşüyordu:
     sunucu 500 verse de, araya bir güvenlik duvarı HTML sayfası girse
     de ekranda "bağlantı kurulamadı" yazıyordu. Kullanıcı ağını,
     modemini, sunucusunu arıyordu; oysa bağlantı kurulmuştu ve karşı
     taraf konuşmuştu — söylediği şey anlaşılmamıştı.
     Yanlış tanı koyan bir hata iletisi, hata iletisi değildir. */
  'yanit'    => k_c('Sunucu beklenmedik bir yanıt verdi (%1). Sayfayı yenileyip tekrar deneyin.',
                    'The server sent an unexpected response (%1). Reload the page and try again.'),
  'bekle'    => k_c('Bekleyin...', 'Please wait...'),
  'girisTamam' => k_c('Giriş yapıldı, paneliniz açılıyor...', 'Signed in, opening your panel...'),
  'orcidDogru' => k_c('ORCID hesabınızla doğrulandı', 'Verified with your ORCID account'),
  'orcidElle'  => k_c('Elle yazıldı, doğrulanmadı', 'Typed by hand, not verified'),
  'orcidYok'   => k_c('Henüz bir ORCID yok', 'No ORCID yet'),
  'orcidDogrula' => k_c('ORCID hesabımla doğrula', 'Verify with my ORCID account'),
  'orcidYeniden' => k_c('Başka bir ORCID ile değiştir', 'Replace with a different ORCID'),
  /* İletiler bölümünün dizgeleri. Sayfada yazılı değil burada: metnin
     iki kopyası zamanla ikiye ayrılır. */
  'iltYok'      => k_c('Şu an bu süzgeçte ileti yok.', 'There are no messages under this filter.'),
  'iltBekleyen' => k_c('yanıt bekliyor', 'awaiting reply'),
  'iltKapali'   => k_c('kapalı', 'closed'),
  'iltAc'       => k_c('Aç', 'Open'),
  /* ÜSTLENME. Gerekçe api/index.php'de ilt_ustlenme()'nin başındadır:
     bir işi herkes görüyorsa o işi kimse üstlenmemiş olur. Metinler
     ENGELLEME dili kullanmaz — kimse engellenmiyor, yalnız görüyor. */
  'iltUstBen'   => k_c('Bu iletiyi siz üstlendiniz.', 'You have taken this message on.'),
  'iltUstBaska' => k_c('Bu iletiyle şu an %1 ilgileniyor (%2). Yine de yanıtlayabilirsiniz; ama iki ayrı yanıt giderse yazan kişi hangisine uyacağını bilemez.',
                       '%1 is dealing with this message right now (%2). You may still reply; but if two separate replies go out, the person who wrote will not know which to follow.'),
  'iltUstSure'  => k_c('Üstlenme %1 dakika sonra kendiliğinden düşer.', 'The claim lapses on its own after %1 minutes.'),
  'iltBakanlar' => k_c('Bu iletiyi görenler', 'Who has seen this message'),
  'iltYanit'    => k_c('Yanıt yazın', 'Write a reply'),
  'iltGonder'   => k_c('Yanıtı gönder', 'Send the reply'),
  'iltKapat'    => k_c('Yanıtla ve kapat', 'Reply and close'),
  'iltYeniden'  => k_c('Yeniden aç', 'Reopen'),
  'iltKapatSade'=> k_c('Kapat', 'Close'),
  'iltIstenmeyen' => k_c('İstenmeyen olarak işaretle', 'Mark as unwanted'),
  'iltIstGeri'    => k_c('İstenmeyen işaretini kaldır', 'Remove the unwanted mark'),
  'iltSil'        => k_c('Sil', 'Delete'),
  'iltSilSor'     => k_c('Bu konuşma tamamen silinsin mi? Geri alınamaz ve kişinin elindeki bağlantı bundan sonra çalışmaz.',
                         'Delete this conversation entirely? This cannot be undone and the person\'s link will stop working.'),
  'iltSilindi'    => k_c('Silindi.', 'Deleted.'),
  'iltIstSuz'     => k_c('İstenmeyen', 'Unwanted'),
  'unutBos'  => k_c('E-posta adresinizi yazın.', 'Write the e mail address of your account.'),
  'unutGitti'=> k_c('Bu adreste bir hesap varsa, parola yenileme bağlantısı gönderildi. Bağlantı bir saat geçerlidir.',
                    'If there is an account at this address, a password reset link has been sent. The link is valid for one hour.'),
  'iltGitti'    => k_c('Yanıt gönderildi.', 'The reply has been sent.'),
  'iltBos'      => k_c('Yanıt boş olamaz.', 'The reply cannot be empty.'),
  'iltSiz'      => k_c('Kurul', 'The panel'),
  'iltKisi'     => k_c('Yazan', 'Sender'),
  'iltBag'      => k_c('Kişinin konuşma bağlantısı', 'The person\'s conversation link'),
  'iltTur'      => [
    'genel'     => k_c('Genel', 'General'),
    /* İletişim formundaki etiketin AYNISI yazılır: form "Maddi katkı"
       derken panel "Destek" deseydi, aynı tür iki adla anılırdı ve
       hangi kutunun işaretlendiği panelde okunamazdı. */
    'destek'    => k_c('Maddi katkı', 'Material contribution'),
    'hata'      => k_c('Hata bildirimi', 'Fault report'),
    'oneri'     => k_c('Öneri', 'Suggestion'),
    'isbirligi' => k_c('Kurumsal iş birliği', 'Institutional cooperation'),
    'kurum'     => k_c('Kurum başvurusu', 'Institutional enquiry'),
  ],
  /* ORCID dönüşünün sonucu. Sekiz durumun sekizi de SÖYLENİR: sessizce
     giriş ekranına düşen bir kişi, ne olduğunu bilmediği için aynı şeyi
     tekrar dener ve aynı yere düşer. */
  'orcidD' => [
    'iptal'      => k_c('ORCID ekranında izin verilmedi. Bir şey değişmedi.', 'Permission was not granted on the ORCID screen. Nothing has changed.'),
    'durum'      => k_c('ORCID dönüşü doğrulanamadı. Bu çoğunlukla tarayıcı çerezinin taşınmamasından olur: sayfayı yenileyip yeniden deneyin.', 'The ORCID return could not be verified. This usually means the browser cookie did not travel: reload and try again.'),
    'kod'        => k_c('ORCID bir yetki kodu göndermedi. Yeniden deneyin.', 'ORCID did not send an authorisation code. Please try again.'),
    'jeton'      => k_c('ORCID ile bağlantı kurulamadı. Kısa bir süre sonra yeniden deneyin.', 'Could not reach ORCID. Please try again shortly.'),
    'baskasinda' => k_c('Bu ORCID başka bir hesapta doğrulanmış. Bir ORCID iki hesapta duramaz.', 'This ORCID is already verified on another account. One ORCID cannot belong to two accounts.'),
    'baglandi'   => k_c('ORCID hesabınıza bağlandı ve doğrulandı.', 'Your ORCID has been linked and verified.'),
    'giris'      => k_c('ORCID ile giriş yapıldı.', 'Signed in with ORCID.'),
    'kayit'      => k_c('ORCID doğrulandı. Hesabınız yok; aşağıdan hesap açın, ORCID kaydınıza doğrulanmış olarak işlenecek.', 'Your ORCID is verified. You do not have an account yet; create one below and the ORCID will be recorded as verified.'),
  ],
  'kaydedildi' => k_c('Kaydedildi.', 'Saved.'),
  'eAd'      => k_c('Adınızı yazın.', 'Please write your name.'),
  'ePosta'   => k_c('Geçerli bir e-posta adresi girin.', 'Enter a valid e mail address.'),
  'eParola'  => k_c('Parola en az 10 karakter olmalıdır.', 'The password must be at least 10 characters.'),
  'eKim'     => k_c('E-posta ya da kullanıcı adınızı girin.', 'Enter your e mail or username.'),
  'eKod'     => k_c('Doğrulama kodunu girin.', 'Enter the verification code.'),
  'eUrl'     => k_c('Doğrulama adresini girin.', 'Enter the verification address.'),
  'onayla'   => k_c('Onayla', 'Approve'),
  'reddet'   => k_c('Doğrulanmadı', 'Not verified'),
  'bosListe' => k_c('Doğrulama bekleyen hesap yok.', 'No account is awaiting verification.'),
  'okur'     => k_c('Okur', 'Reader'),
  'okurAck'  => k_c('Her şey size açık; okumak için hesap gerekmez.', 'Everything is open to you; no account is needed to read.'),
  'adayAck'  => k_c('Belgeniz sorgulanıyor.', 'Your credential is being checked.'),
  'hakemAck' => k_c('Çalışmaları değerlendirebilirsiniz. Hakemlik gönüllüdür, hiçbir zaman zorunlu değildir.', 'You may assess works. Reviewing is voluntary and never obligatory.'),
  'yazarAck' => k_c('Kendi çalışmanızı gönderebilirsiniz.', 'You may submit your own work.'),
  'edAck'    => k_c('Hakem atayabilir, editöryal not düşebilirsiniz.', 'You may assign reviewers and add editorial notes.'),
  'basAck'   => k_c('Editör listesine kişi ekleyebilirsiniz.', 'You may add editors to the list.'),
  /* 14 Ağustos 2026: "doktora belgenizi sunun" diyordu — belge artık
     hiç istenmiyor; kaldırılmış bir kuralı duyuran üçüncü metin buydu. */
  'yolHakem' => k_c('Doktoranızın bir editör tarafından doğrulanması gerekir.', 'An editor needs to confirm your doctorate.'),
  /* Doktora şartı 13 Ağustos 2026 kurul kararıyla kaldırıldı; bu satır
     kullanıcıya "sana ne açık" der ve artık doğru olanı söylemelidir. */
  'yolYazar' => tg_yazarlik_doktora_sarti()
      ? k_c('Şu an size açık: doktora ya da iki doktoralı ' . tg_destek_ad() . ', çalışma göndermeye yeter.', 'Open to you now: a doctorate, or two doctoral ' . tg_destek_ad(true, true) . ', is enough to submit.')
      : k_c('Şu an size açık: çalışma göndermek için doktora aranmaz, ORCID yeter. Kararı editör verir.', 'Open to you now: no doctorate is needed to submit, an ORCID is enough. An editor decides.'),
  'hakemAta' => k_c('Hakem ata', 'Assign a reviewer'),
  'davetEt'  => k_c('Davet et', 'Invite'),
  'daveti_cek' => k_c('Daveti geri çek', 'Withdraw the invitation'),
  'notEkle'  => k_c('Editöryal not ekle', 'Add an editorial note'),
  'hAd'      => k_c('Hakem adı', 'Reviewer name'),
  'hEposta'  => k_c('Hakem e-postası (davet buraya gider)', 'Reviewer e mail (the invitation is sent here)'),
  'gonder'   => k_c('Gönder', 'Send'),
  'vazgec'   => k_c('Vazgeç', 'Cancel'),
  'notMetin' => k_c('Notunuz (sayfada adınızla görünür)', 'Your note (shown on the page with your name)'),
  'bosCal'   => k_c('Çalışma bulunamadı.', 'No work found.'),
  'hakemYok' => k_c('Henüz hakem yok', 'No reviewer yet'),
  /* Aşama adları elle yazılmaz, tek kaynaktan okunur: panelde başka,
     çalışmanın sayfasında başka bir ad görmek aynı çalışmayı iki ayrı
     durumda sanmaya yeter. */
  'onayliRz' => tg_asama_metni('onayli', $en),
  'araniyor' => tg_asama_metni('aranan', $en),
  'suruyorRz'=> tg_asama_metni('suruyor', $en),
  'oneriBas'   => k_c('Yazarın önerisi', 'Suggested by the author'),
  'onerenEt'   => k_c('öneren:', 'suggested by'),
  'adresYok'   => k_c('adres verilmemiş', 'no address given'),
  'oneriKabul' => k_c('Kabul et ve davet gönder', 'Accept and invite'),
  'oneriNeden' => k_c('Ret gerekçesi (isteğe bağlı):', 'Reason for declining (optional):'),
  'tekrarUyari'=> k_c('Bu kişi aynı yazarların %d başka çalışmasını daha değerlendirdi. Engel değildir, ama bilerek karar verin.', 'This person has already reviewed %d other work(s) by the same author(s). Not a bar, but worth knowing.'),
  'gonulluBas' => k_c('Gönüllü başvurusu', 'Volunteer application'),
  'kabulEt'  => k_c('Kabul et', 'Accept'),
  'reddet2'  => k_c('Reddet', 'Decline'),
  'belgeOnayla' => k_c('Belgeyi de doğruladım', 'The credential is verified as well'),
  'davetGitti' => k_c('Davet gönderildi.', 'The invitation has been sent.'),
  /* WHATSAPP DEVRİ (yalnız kurucu baş editörler). Metin tek yerden
     gelir ve %1 ile %2 yerine geçer: cümlenin içine değer yapıştırmak,
     çeviriye kelime sırası dayatır. */
  'waDg'   => k_c('WhatsApp ile ilet', 'Pass it on over WhatsApp'),
  'waTel'  => k_c('WhatsApp numarası (ülke koduyla, ör. 905xxxxxxxxx)', 'WhatsApp number (with country code, e.g. 905xxxxxxxxx)'),
  'waAck'  => k_c('Düğme iletiyi göndermez: hazır metni WhatsApp penceresine taşır, gönderme kararı sizindir. Numara yazmazsanız alıcıyı WhatsApp içinde seçersiniz. Taşınan şey yalnızca davet bağlantısıdır; hakemin raporu açacağı şifre WhatsApp\'a hiç konmaz, o yalnızca e-postayla gider.',
                  'The button does not send anything: it carries the prepared message into the WhatsApp window and the decision to send is yours. If you write no number, you pick the recipient inside WhatsApp. Only the invitation link travels this way; the code the reviewer uses to open the report is never placed in WhatsApp, it goes by e mail alone.'),
  'waMetin'=> k_c(
      "Merhaba,\n\nKutadgu açık erişimli yayın sisteminde bir çalışmayı değerlendirmeniz için sizi hakem olarak önerdim:\n\n\"%1\"\n\nDaveti kabul edip etmeyeceğinizi şu sayfadan bildirebilirsiniz:\n%2\n\nBu sistemde kör hakemlik yoktur: adınız, kararınız ve raporunuz çalışmayla birlikte açıkça yayımlanır. Hakemlik gönüllüdür, reddetmek en doğal hakkınızdır.",
      "Hello,\n\nI have suggested you as a reviewer for a work in the Kutadgu open access publishing system:\n\n\"%1\"\n\nYou can say whether you accept on this page:\n%2\n\nThere is no blind review here: your name, your decision and your report are published openly with the work. Reviewing is voluntary, and declining is entirely your right."),
  /* GİTMEYEN DAVET. Hakem kaydı kuruldu ama ileti çıkmadı: ikisi ayrı
     şeydir ve ekran ikisini ayrı söylemek zorundadır. Eskiden her iki
     hâlde de "Davet gönderildi." yazıyordu — sistemin uygulamadığı bir
     kuralı duyurması. Bağlantı yanıtta zaten dönüyor; ekranda
     gösterilir ki editör onu elden iletebilsin. */
  'davetPostaYok' => k_c('Hakem eklendi, ANCAK davet iletisi gönderilemedi. Aşağıdaki bağlantıyı kendiniz iletin:',
                         'The reviewer was added, BUT the invitation message could not be sent. Pass on the link below yourself:'),
  'davetAdresYok' => k_c('Hakem eklendi. Adres bilinmediği için ileti gönderilmedi; davet bağlantısını kendiniz iletin:',
                         'The reviewer was added. No address is known, so no message was sent; pass on the invitation link yourself:'),
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

$enJs = $en ? 'true' : 'false';
/* Heredoc içinde <?= ?> ÇALIŞMAZ; oraya yazılan bir PHP etiketi olduğu
   gibi betiğe düşer ve bütün paneli susturur. Değer burada hesaplanıp
   değişkenle taşınır. */
$orcidAcik = (function_exists('tg_orcid_acik') && tg_orcid_acik()) ? 'true' : 'false';
/* Yanıt yazma yetkisi baş editörlerdedir. Betikteki bu bayrak yalnızca
   KOLAYLIK içindir: yetkisi olmayana yanıt kutusu gösterilmez. Asıl
   denetim uçtadır (yonetim_yazma_gerek); buradaki bayrağı değiştiren
   biri hiçbir şey kazanmaz. */
$basYetkiJs = $basYetki ? 'true' : 'false';
$kurucuYetkiJs = $kurucuYetki ? 'true' : 'false';
$betik = <<<JS
<script>
(function(){
  var S = {$S};
  var EN = {$enJs};
  function \$(id){ return document.getElementById(id); }
  /* İKİSİ DE YOK OLAN ÖGEYE DAYANIKLI.
     Bu sayfanın bölümleri SUNUCUDA koşullu basılır: yönetim ve editör
     kartları yalnız yetkisi olan için yazılır. Giriş yapılmadan açılan
     sayfada o kartlar hiç yoktur. Betik onları arayınca null döner ve
     null.classList bir istisna fırlatır; istisna da giriş akışının
     .catch()'ine düşüp "bağlantı kurulamadı" diye görünürdü. Bir
     yardımcı işlev, olmayan bir ögeyi sessizce geçmelidir. */
  function goster(el,k){ if(el&&el.classList) el.classList.toggle('gizli', !k); }
  function msj(el,t,tur){ if(!el) return; el.textContent=t; el.className='form-msj'+(tur?(' '+tur):''); }
  /* DAVET SONUCU. Üç hâl ayrı yazılır ve ikisi başarı değildir:
       posta===true  ileti gitti
       posta===false denendi, aktarıcı kabul etmedi
       posta===null  adres bilinmiyor (dizinden gizli adresle atama)
     Son ikisinde davet bağlantısı da basılır: o hâllerde daveti
     taşıyacak olan editörün kendisidir, bağlantıyı görmeden taşıyamaz.
     'undefined' hâli eski bir yanıt demektir ve eski davranışa düşer;
     yeni bir alanın yokluğu ekranı bozmamalıdır. */
  function davetSonuc(m, r, baslik){
    if(!m) return;
    if(!r || r.posta === true || typeof r.posta === 'undefined'){ msj(m, S.davetGitti, 'ok'); }
    else {
      msj(m, (r.posta === null ? S.davetAdresYok : S.davetPostaYok), 'err');
      if(r.davet_link){
        var a=document.createElement('a');
        a.href=r.davet_link; a.textContent=r.davet_link;
        a.style.display='block'; a.style.marginTop='6px'; a.style.wordBreak='break-all';
        m.appendChild(a);
      }
    }
    /* ---- WHATSAPP DEVRİ ----
       Kurul kararı, 15 Ağustos 2026: "hakem daveti WhatsApp'la da
       gönderilebilecekti, kurucu baş editörler için geçerli bu sadece."

       NE YAPILDI: bir WhatsApp TÜMLEŞİMİ değil, bir DEVİR NOKTASI.
       Sunucu hiçbir yere ileti göndermez; düğme hazır metni WhatsApp'ın
       kendi penceresine taşır ve gönder düğmesine kurucunun kendisi
       basar. Sunucuda ne API anahtarı ne telefon oturumu durur.

       NİÇİN YALNIZ KURUCU: bağlantı, alıcının TANIDIĞI bir numaradan
       gelmelidir. Kurucunun numarası sistemle birlikte anılır; sonradan
       atanmış bir baş editörün numarasından gelen bağlantı, hakem için
       tanımadığı bir yerden gelen bağlantıdır — yani kimlik avının
       kendisine benzer. Denetim SUNUCUDA basılan KURUCU değişkeninden
       gelir; düğme yetkisi olmayanın sayfasında hiç çizilmez.

       ŞİFRE BURADAN GEÇMEZ. Hakemin raporu açacağı şifre yanıtta
       dönüyor ama metne KONMAZ: e-postanın taşıdığı davet bağlantısı ile
       aynı şeyi taşımak yeterlidir, ikinci bir kanala sır koymak
       gereksiz bir yayılmadır. */
    if(!KURUCU || !r || !r.davet_link) return;
    var kap=document.createElement('div'); kap.className='pn-wa';
    var tel=document.createElement('input');
    tel.type='tel'; tel.inputMode='numeric'; tel.autocomplete='off';
    tel.placeholder=S.waTel; tel.setAttribute('aria-label', S.waTel);
    var dg=document.createElement('button');
    dg.type='button'; dg.className='d d-ikinci d-kucuk'; dg.textContent=S.waDg;
    dg.addEventListener('click', function(){
      var metin=S.waMetin.replace('%1', baslik||'').replace('%2', r.davet_link);
      var n=tel.value.replace(/[^0-9]/g,'');
      if(n.charAt(0)==='0') n=n.slice(1);
      window.open('https://wa.me/'+n+'?text='+encodeURIComponent(metin), '_blank', 'noopener');
    });
    var ack=document.createElement('p'); ack.className='pn-ack'; ack.textContent=S.waAck;
    kap.appendChild(tel); kap.appendChild(dg); kap.appendChild(ack);
    m.appendChild(kap);
  }
  function api(yol, govde){
    var s = { headers:{'Accept':'application/json'}, credentials:'same-origin' };
    if (govde !== undefined) { s.method='POST'; s.headers['Content-Type']='application/json'; s.body=JSON.stringify(govde); }
    /* Yanıt önce METİN olarak alınır, sonra çözülmeye çalışılır.
       r.json() doğrudan çağrılırsa, JSON olmayan bir yanıtta fırlattığı
       hata çağıranın .catch()'ine düşer ve orada ağ hatasından ayırt
       edilemez. Oysa ikisi bambaşka şeydir: biri "sunucuya ulaşamadım",
       öteki "sunucuya ulaştım ama ne dediğini anlamadım". Durum kodunu
       da taşırız; 500 ile 403 aynı cümleyle anlatılmaz. */
    /* DİL HER İSTEĞE EKLENİR, tek yerden.
       Uçlarda sayfanın dili yoktur: k_dil() sırayla ?lang=, çerez,
       ülke ve Accept-Language'a bakar, hiçbiri yoksa varsayılana —
       yani İngilizceye — düşer (OKUBENI 6/25). Ölçüldü: Türkçe panelde
       kurucu davetinin reddi "Founder status can never be granted
       afterwards." diye döndü.

       Çağrı başına eklemek yerine buraya kondu: tek tek eklenseydi bir
       gün biri unutulur ve o uç sessizce İngilizce konuşurdu. */
    var ayrac = yol.indexOf('?') >= 0 ? '&' : '?';
    return fetch('/api' + yol + ayrac + 'lang=' + (EN ? 'en' : 'tr'), s).then(function(r){
      return r.text().then(function(t){
        try { return JSON.parse(t); }
        catch(e){ return { ok:false, hata: String(S.yanit).replace('%1', r.status) }; }
      });
    }).then(function(c){
      /* İŞ BİTİNCE BİLDİRİM DÜŞER · TEK YERDE
         KURUL BİLDİRİMİ (20 Ağustos 2026): "kişi onun üzerine tıklarsa
         ve işlem yaparsa bildirim gider."

         Tazeleme tek tek çağrı yerlerine yazılabilirdi ve yazılmadı:
         panelde durum değiştiren yirmiden çok çağrı var, yarısı zaten
         unutulmuştu (belge onayı listeyi tazeliyor, gönüllü kararı ve
         kurul oyu tazelemiyordu). Bir sonraki uç eklendiğinde de
         unutulurdu. Ölçüt burada tek ve nesneldir: GÖVDESİ OLAN istek
         durumu değiştirir; değiştiyse bekleyen işler yeniden sorulur.

         Bekletme, arka arkaya gelen adımların (deneme akışı) her biri
         için ayrı bir istek açmasın diyedir; son istekten kısa süre
         sonra bir kez sorulur. */
      if (govde !== undefined && c && c.ok) bekTazele();
      return c;
    });
  }
  var BEK_ZAMAN = null;
  function bekTazele(){
    if (BEK_ZAMAN) clearTimeout(BEK_ZAMAN);
    BEK_ZAMAN = setTimeout(function(){
      BEK_ZAMAN = null;
      if (typeof gostergeYukle === 'function') gostergeYukle();
    }, 400);
  }
  /* Metni HTML'e gömmeden önce kaçır: kullanıcı adı, başlık ve kurum
     alanları buradan geçer. */
  function esc(s){
    return String(s==null?'':s).replace(/[&<>"']/g, function(c){
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
    });
  }

  function harf(ad){
    var p=String(ad||'').replace(/\\b(prof|do[çc]|dr|öğr|ogr)\\b\\.?/gi,'').trim().split(/\\s+/).filter(Boolean);
    if(!p.length) return '?';
    return (p[0][0]||'').toUpperCase() + (p.length>1 ? (p[p.length-1][0]||'').toUpperCase() : '');
  }
  function orcidTemiz(o){
    var s=String(o||'').replace(/[^0-9Xx]/g,'').toUpperCase();
    return s.length===16 ? s.slice(0,4)+'-'+s.slice(4,8)+'-'+s.slice(8,12)+'-'+s.slice(12,16) : '';
  }

  /* ORCID kapalıysa ne eksik sayılır ne de çağrı yapılır: olmayan bir
     kapıya çağırmak, çağrılanı boşuna yorar. */
  var ORCID_ACIK = $orcidAcik;
  var BAS_YETKI  = $basYetkiJs;
  var KURUCU     = $kurucuYetkiJs;

  var HESAP = null;

  /* Gün adı: makine biçimindeki tarihi okunur hâle getirir. Ayrıştırma
     başarısızsa gelen dize olduğu gibi döner — bir tarihi kaybetmektense
     çirkin göstermek yeğdir. */
  function gunAd(t){
    if(!t) return '';
    var d = new Date(t); if(isNaN(d)) return String(t);
    return d.toLocaleDateString(EN?'en-GB':'tr-TR', {year:'numeric', month:'long', day:'numeric'});
  }

  /* ---- Rol merdiveni ---- */
  function merdiven(h){
    var r = h.roller||[];
    var var_ = function(x){ return r.indexOf(x)>=0; };
    var ad = {
      aday_hakem: EN?'Candidate reviewer':'Aday hakem', hakem: EN?'Reviewer':'Hakem',
      yazar: EN?'Author':'Yazar', editor: EN?'Editor':'Editör',
      bas_editor: EN?'Chief editor':'Baş editör', yonetici: EN?'Administrator':'Sistem yöneticisi'
    };
    var basamak = [
      { k:'okur', ad:S.okur, ack:S.okurAck, v:true },
      { k:'aday_hakem', ad:ad.aday_hakem, ack:var_('aday_hakem')?S.adayAck:S.yolHakem, v:var_('aday_hakem')||var_('hakem') },
      { k:'hakem', ad:ad.hakem, ack:S.hakemAck, v:var_('hakem') },
      { k:'yazar', ad:ad.yazar, ack:var_('yazar')?S.yazarAck:S.yolYazar, v:var_('yazar') }
    ];
    if (var_('editor')) basamak.push({ k:'editor', ad:ad.editor, ack:S.edAck, v:true });
    if (var_('bas_editor')) basamak.push({ k:'bas_editor', ad:ad.bas_editor, ack:S.basAck, v:true });
    /* KAPAK SATIRI: kişinin ulaştığı EN ÜST basamak. Merdivenle aynı
       diziden okunur; ayrı bir hesap yapılsaydı ikisi bir gün farklı
       şey söylerdi. */
    var ust = null;
    basamak.forEach(function(b){ if(b.v) ust = b; });
    var kb = \$('pnBasamak');
    if(kb) kb.textContent = ust
      ? (EN ? ('You are at: ' + ust.ad) : ('Şu an: ' + ust.ad))
      : (EN ? 'Reader' : 'Okur');
    var ul=\$('pnMerdiven'); ul.innerHTML='';
    basamak.forEach(function(b,i){
      var li=document.createElement('li');
      li.setAttribute('data-var', b.v?'1':'0');
      li.innerHTML='<span class="im">'+(b.v?'\\u2713':(i+1))+'</span><span><b></b><span></span></span>';
      li.querySelector('b').textContent=b.ad;
      li.querySelectorAll('span')[2].textContent=b.ack;
      ul.appendChild(li);
    });
  }

  function belgeGoster(h){
    var d=h.dogrulama||{}, el=\$('belgeDurum');
    var m={onayli:[EN?'Your doctorate has been confirmed by an editor.':'Doktoranız bir editör tarafından doğrulandı.','iyi'],
           bekliyor:[EN?'Your credential has been received and is being checked.':'Belgeniz alındı, sorgulanıyor.','bek'],
           eksik:[EN?'You have not yet supplied a credential.':'Henüz belge sunmadınız.','eks']};
    var x=m[d.durum]||m.eksik;
    el.textContent=x[0]; el.className='pn-durum '+x[1];
    goster(\$('belgeForm'), d.durum!=='onayli');
    /* Kapak satırı kartın TEK günlük bilgisini taşır; durum yazısıyla
       aynı kaynaktan gelir, ayrı bir metin yazılmadı. */
    var oz=\$('belgeOzet'); if(oz) oz.textContent=x[0];
    /* Yarım kalmış iş kapanmaz: aday hakemin belgesi onaylanmadıysa
       kart açık gelir. Onaylıysa kapalı kalır — bakılacak bir şey yok. */
    var kart=\$('kartBelge');
    if(kart) kart.open = (d.durum!=='onayli')
      && !!(h.roller && h.roller.indexOf('aday_hakem')>=0);
  }

  function panelDoldur(h){
    HESAP=h;
    /* Baş harf dairesi başlıktan kalktı; üst çubuktaki resim
       kabuk betiği tarafından kuruluyor. */
    \$('pnAd').textContent=h.gorunen||h.ad;
    \$('pnEposta').textContent=h.eposta;
    var rz=\$('pnRoller'); rz.innerHTML='';
    var adlar={aday_hakem:EN?'Candidate reviewer':'Aday hakem',hakem:EN?'Reviewer':'Hakem',yazar:EN?'Author':'Yazar',
               editor:EN?'Editor':'Editör',bas_editor:EN?'Chief editor':'Baş editör',yonetici:EN?'Administrator':'Sistem yöneticisi'};
    (h.roller||[]).forEach(function(r){
      var s=document.createElement('span');
      s.className='rz '+(r==='bas_editor'||r==='yonetici'?'rz-kut':(r==='hakem'||r==='yazar'?'rz-yes':'rz-cizgi'));
      s.textContent=adlar[r]||r; rz.appendChild(s);
    });
    eksikleriGoster(h);
    merdiven(h); belgeGoster(h);
    goster(\$('kartKullanici'), !h.kullanici_secildi);
    \$('pUnvan').value=h.unvan||''; \$('pAd').value=h.ad||''; \$('pKurum').value=h.kurum||'';
    \$('pOrcid').value=h.orcid||''; \$('pScopus').value=h.scopus||'';
    /* ORCID alanının altındaki satır üç şeyi birden söyler: doğrulandı
       mı, değilse ne eksik, ve ne yapılacak. Eskiden doğrulanmış olsa
       bile "ORCID hesabımla doğrula" bağlantısı duruyordu — yapılmış
       bir işi yeniden yapmaya çağırmak, kişiye yaptığının işe
       yaramadığını düşündürür. */
    var od=\$('pOrcidDurum'), obag=\$('pOrcidBag');
    if(od){
      od.removeAttribute('hidden');
      od.className = 'orcid-durum ' + (h.orcid_dogru ? 'orcid-var' : 'orcid-yok');
      od.textContent = h.orcid_dogru ? S.orcidDogru : (h.orcid ? S.orcidElle : S.orcidYok);
    }
    if(obag){ obag.textContent = h.orcid_dogru ? S.orcidYeniden : S.orcidDogrula;
              obag.className = h.orcid_dogru ? 'orcid-bag orcid-bag-sessiz' : 'orcid-bag'; }
    \$('pTanitim').value=h.tanitim||''; tanitimSay();
    \$('pTelefon').value=h.telefon||'';
    gorunurlukYaz(h.gorunurluk||{});
    \$('pUyelik').value=(h.uyelikler||[]).join('\\n');
    baglarYaz(h.baglantilar||[]);
    if(window.alSecYaz) window.alSecYaz('pAlan', h.alanlar||h.alan||[]);
    if(\$('pDizin')) \$('pDizin').checked = !!h.dizin_gizli;
    goster(\$('kartEditor'), !!h.editor);
    goster(\$('kartAlan'), !!h.editor);
    goster(\$('kartCalisma'), !!h.editor);
    goster(\$('kartDurum'), !!h.editor);
    /* Deneme düzeni sahte kayıt üretir; sunucu ucu zaten yönetim yazma
       yetkisi arıyor, ama kartı editöre göstermek de doğru olmazdı:
       görünen ama basılınca 403 dönen bir düğme, kullanıcıya sistemin
       kırık olduğunu düşündürür. Kart ile uç aynı ölçüte bakar. */
    goster(\$('kartDeneme'), !!(h.roller && (h.roller.indexOf('bas_editor')>=0 || h.roller.indexOf('yonetici')>=0)));
    /* Deneme akışının durumu açılışta okunur: yarım kalmış bir deneme
       varsa kart onu kaldığı yerden gösterir. "Her seferinde tekrar
       açmak" tam olarak bunun yokluğuydu. */
    if (h.roller && (h.roller.indexOf('bas_editor')>=0 || h.roller.indexOf('yonetici')>=0)) {
      dnDurum(true);
      /* İşaretleme bölümü de açılışta dolar: seçenekleri boş bir açılır
         liste, "burada yapacak bir şey yok" der. */
      dnIsSecDoldur(); dnIsListeCiz();
      goster(\$('kartZenodo'), true);
      znDurum();
    }
    /* İletiler sekmesi baş editöre aittir (gerekçe bölümün başında).
       Sunucu zaten yalnız ona basıyor; bu satır sekme düğmesini de
       aynı ölçüte bağlar ki ikisi ayrılmasın. */
    goster(\$('sekIletiler'), !!h.bas_yetki);
    goster(\$('sekEditor'), !!h.editor);
    goster(\$('kartBas'), !!h.bas_yetki);
    /* Kurul kararları baş editöre aittir; kurucu kararını yalnız kurucu
       oylayabilir ama listeyi bütün baş editörler görür — bir kurulun
       kendi kararlarını görememesi, denetlenemeyen bir kurul demektir. */
    goster(\$('kartKarar'), !!h.bas_yetki);
    if (h.bas_yetki) kkCiz();
    if (h.bas_yetki) basKur(h);
    if (h.editor) { edYukle(); calYukle(); alYukle(); raporKur(); }
    /* Yönetim bölümü sayfada ancak baş editör için basılmıştır; kurulum
       da yalnızca o zaman koşar. */
    if (h.bas_yetki) yonetimKur();
    yuzCiz(h);
    goster(\$('pnKapi'), false); goster(\$('pnIc'), true);
    gostergeYukle();
    listemYukle();
  }

  /* ---- Hakem havuzu: alana göre süzülmüş öneriler ----
     Editör bir ad yazmak zorunda değil; sistemdeki hakemler çalışmanın
     alan kodlarına yakınlığa göre sıralanır. Adres görünmez: atama
     dizin kimliğiyle yapılır, adresi sunucu çözer. */
  /* Havuz, alan yakınlığına göre sıralı gelir. Editörün aklında belirli
     bir kişi varsa altmış kaydı gözle taramasın diye arama da vardır:
     ad, ORCID ya da e-posta. Hangisi olduğu yazılandan anlaşılır ve
     sunucuda çözülür. Arama sonucu yine adres döndürmez. */
  function havuzCiz(kutu, c, form, ara){
    if(!kutu) return;
    var q = (ara===undefined) ? (kutu.getAttribute('data-ara')||'') : ara;
    kutu.setAttribute('data-ara', q);
    kutu.innerHTML='<p class="pn-ack">'+S.bekle+'</p>';
    fetch('/api/editor/hakem-oner?id='+encodeURIComponent(c.id)+(q?('&ara='+encodeURIComponent(q)):''),
          {credentials:'same-origin'})
      .then(function(r){return r.json();}).then(function(d){
        if(!d||!d.ok){ kutu.innerHTML=''; return; }
        var h='';
        var alanAd=(d.calisma&&d.calisma.alan_ad||[]);
        h+='<p class="pn-ack">'+(EN?'Field of the work: ':'Çalışmanın alanı: ')
          +(alanAd.length?esc(alanAd.join(' | ')):(EN?'not given':'belirtilmemiş'))+'</p>';
        h+='<div class="pn-hv-ara">'
         + '<input type="search" class="gir pn-hv-q" value="'+esc(q)+'" placeholder="'
         + (EN?'Search by name, ORCID or e mail':'Ad, ORCID ya da e-posta ile ara')+'">'
         + '<button type="button" class="d d-ikinci d-kucuk pn-hv-dg">'+(EN?'Search':'Ara')+'</button>'
         + (q?('<button type="button" class="d d-sessiz d-kucuk pn-hv-sil">'+(EN?'Clear':'Temizle')+'</button>'):'')
         + '</div>';
        if(!(d.hakemler||[]).length){
          h+='<p class="pn-ack">'+(q
            ? (EN ? 'No one in the directory matches that. Check the spelling, or invite someone by name and address below.'
                  : 'Dizinde buna uyan kimse yok. Yazımı denetleyin ya da aşağıdan ad ve adres yazarak davet edin.')
            : (EN ? 'No suitable reviewer was found in the directory. You may still invite someone by name and address below.'
                  : 'Dizinde uygun bir hakem bulunamadı. Aşağıdan ad ve adres yazarak yine de davet edebilirsiniz.'))+'</p>';
          kutu.innerHTML=h; havuzAramaBagla(kutu, c, form); return;
        }
        if(q){
          h+='<p class="pn-ack">'+(EN?'Matches: ':'Eşleşen: ')+(d.toplam||0)+'</p>';
        }
        h+='<div class="pn-havuz-liste">';
        d.hakemler.slice(0,20).forEach(function(x){
          var yak=x.yakinlik===3?(EN?'same branch':'aynı dal')
                 :x.yakinlik===2?(EN?'same subfield':'aynı alt alan')
                 :x.yakinlik===1?(EN?'same main field':'aynı ana alan'):(EN?'no field match':'alan eşleşmesi yok');
          h+='<div class="pn-hv"><span><b>'+esc(x.gorunen||x.ad)+'</b>'
           + (x.kurum?'<br><small>'+esc(x.kurum)+'</small>':'')
           + '<br><small>'+esc((x.alan_ad||[]).slice(0,3).join(' · '))+'</small>'
           + '<br><small class="pn-hv-yak">'+esc(yak)+' · '+x.rapor+' '+(EN?'reports':'rapor')+'</small></span>'
           + '<button type="button" class="d d-vurgu d-kucuk" data-dk="'+esc(x.dk)+'" data-ad="'+esc(x.ad)+'">'+S.davetEt+'</button></div>';
        });
        h+='</div>';
        kutu.innerHTML=h;
        havuzAramaBagla(kutu, c, form);
        Array.prototype.forEach.call(kutu.querySelectorAll('[data-dk]'),function(b){
          b.addEventListener('click',function(){
            var m=form.querySelector('.f-msj'); m.textContent=S.bekle; m.className='form-msj';
            b.disabled=true;
            api('/editor/hakem-ata',{id:c.id, dk:b.getAttribute('data-dk')}).then(function(r){
              if(r&&r.ok){ davetSonuc(m, r, c.baslik); calYukle(); }
              else { m.textContent=(r&&r.hata)||S.baglanti; m.className='form-msj err'; b.disabled=false; }
            }).catch(function(){ m.textContent=S.baglanti; m.className='form-msj err'; b.disabled=false; });
          });
        });
      }).catch(function(){ kutu.innerHTML=''; });
  }

  function havuzAramaBagla(kutu, c, form){
    var q=kutu.querySelector('.pn-hv-q'), dg=kutu.querySelector('.pn-hv-dg'), sil=kutu.querySelector('.pn-hv-sil');
    if(dg&&q) dg.addEventListener('click', function(){ havuzCiz(kutu, c, form, q.value.trim()); });
    if(q) q.addEventListener('keydown', function(ev){
      if(ev.key==='Enter'){ ev.preventDefault(); havuzCiz(kutu, c, form, q.value.trim()); }
    });
    if(sil) sil.addEventListener('click', function(){ havuzCiz(kutu, c, form, ''); });
  }

  /* Editör sekmesindeki rozet iki işi birden sayar: belge bekleyenler
     ve karara bağlanmamış alan önerileri. */
  var edBekleyen=0, alBekleyen=0;
  function rozEditorCiz(){
    var n = edBekleyen + alBekleyen;
    roz('rozEditor', n,
        EN?(n===1?'editorial task is waiting':'editorial tasks are waiting'):'editörlük işi bekliyor', 'editor');
  }

  /* ---- Alan önerileri (editör) ---- */
  function alYukle(){
    api('/yonetim/alan-oneriler').then(function(d){
      if(!d||!d.ok) return;
      var kutu=\$('alListe'); kutu.innerHTML='';
      var bekleyen=(d.oneriler||[]).filter(function(x){return x.durum==='oneri';});
      var karara=(d.oneriler||[]).filter(function(x){return x.durum!=='oneri';}).slice(0,12);
      if(!bekleyen.length && !karara.length){
        kutu.innerHTML='<p class="pn-ack">'+(EN?'No field has been proposed yet.':'Henüz önerilmiş bir dal yok.')+'</p>';
        alBekleyen=0; rozEditorCiz(); return;
      }
      bekleyen.forEach(function(x){
        var s=document.createElement('div'); s.className='pn-sat';
        var bilgi=document.createElement('span');
        bilgi.innerHTML='<b></b><br><small></small><br><small class="pn-ger"></small>';
        bilgi.querySelector('b').textContent=x.kod+'  '+x.tr+(x.en&&x.en!==x.tr?'  ('+x.en+')':'');
        bilgi.querySelector('small').textContent=(EN?'Proposed by ':'Öneren: ')+x.ekleyen+' · '+(x.tarih||'').slice(0,10)+' · '+x.yol;
        bilgi.querySelector('.pn-ger').textContent=x.gerekce||'';
        s.appendChild(bilgi);
        var b1=document.createElement('button'); b1.className='d d-vurgu d-kucuk'; b1.textContent=EN?'Approve':'Onayla';
        var b2=document.createElement('button'); b2.className='d d-ikinci d-kucuk'; b2.textContent=EN?'Decline':'Onaylama';
        function karar(k){
          var not=window.prompt(EN?'Note (optional, sent to the proposer):':'Not (isteğe bağlı, önerene iletilir):','')||'';
          b1.disabled=true; b2.disabled=true;
          api('/yonetim/alan-karar',{kod:x.kod,karar:k,not:not}).then(function(r){
            if(r&&r.ok) alYukle();
            else { msj(\$('alMsj'),(r&&r.hata)||S.baglanti,'err'); b1.disabled=false; b2.disabled=false; }
          }).catch(function(){ msj(\$('alMsj'),S.baglanti,'err'); b1.disabled=false; b2.disabled=false; });
        }
        b1.addEventListener('click',function(){karar('onayli');});
        b2.addEventListener('click',function(){karar('red');});
        var dg=document.createElement('span'); dg.className='pn-dg'; dg.appendChild(b1); dg.appendChild(b2);
        s.appendChild(dg); kutu.appendChild(s);
      });
      if(karara.length){
        var bas=document.createElement('p'); bas.className='pn-ack';
        bas.textContent=EN?'Concluded proposals':'Karara bağlanmış öneriler';
        kutu.appendChild(bas);
        karara.forEach(function(x){
          var s=document.createElement('div'); s.className='pn-sat';
          var t=document.createElement('span');
          t.innerHTML='<b></b><br><small></small>';
          t.querySelector('b').textContent=x.kod+'  '+x.tr;
          t.querySelector('small').textContent=(x.durum==='onayli'?(EN?'Approved':'Onaylandı'):(EN?'Not approved':'Onaylanmadı'))
            +' · '+((x.karar&&x.karar.veren)||'')+' · '+(((x.karar&&x.karar.tarih)||'').slice(0,10));
          s.appendChild(t); kutu.appendChild(s);
        });
      }
      alBekleyen=bekleyen.length; rozEditorCiz();
    }).catch(function(){});
  }

  function edYukle(){
    api('/hesap/liste').then(function(d){
      if(!d||!d.ok) return;
      var kutu=\$('edListe'); kutu.innerHTML='';
      var bekleyen=(d.hesaplar||[]).filter(function(x){ return x.dogrulama && x.dogrulama.durum==='bekliyor'; });
      if(!bekleyen.length){ kutu.innerHTML='<p class="pn-ack">'+S.bosListe+'</p>'; return; }
      bekleyen.forEach(function(x){
        var s=document.createElement('div'); s.className='pn-sat';
        s.innerHTML='<span><b></b><br><small></small></span>';
        s.querySelector('b').textContent=x.gorunen||x.ad;
        /* Tür adı TEK YERDEN gelir. Eskiden burada iki dallı bir
           koşul vardı ('edevlet' ya da 'Yurt dışı') ve yeni bir tür
           eklendiğinde sessizce yanlış ad basıyordu: ORCID ile gelen
           bir kayıt "Yurt dışı" görünüyordu. Bilinmeyen tür artık
           adını kendi söyler, uydurulmaz. */
        var turAd={edevlet:'e-Devlet', orcid:'ORCID',
                   yabanci:(EN?'Institution page':'Kurum sayfası'),
                   editor:(EN?'Editor confirmation':'Editör doğrulaması')}[x.dogrulama.tur]||x.dogrulama.tur;
        s.querySelector('small').textContent=(x.kurum||'')+' · '+x.eposta+' · '+turAd;
        var b1=document.createElement('button'); b1.className='d d-vurgu d-kucuk'; b1.textContent=S.onayla;
        var b2=document.createElement('button'); b2.className='d d-sessiz d-kucuk'; b2.textContent=S.reddet;
        b1.addEventListener('click',function(){ belgeKarar(x.eposta,true); });
        b2.addEventListener('click',function(){ belgeKarar(x.eposta,false); });
        var sar=document.createElement('span'); sar.className='ara-oto'; sar.style.display='flex'; sar.style.gap='8px';
        sar.appendChild(b1); sar.appendChild(b2); s.appendChild(sar);
        kutu.appendChild(s);
      });
    });
  }
  function belgeKarar(eposta, onay){
    msj(\$('edMsj'), S.bekle);
    api('/hesap/belge-onay',{eposta:eposta,onay:onay}).then(function(d){
      if(d&&d.ok){ msj(\$('edMsj'), S.kaydedildi, 'ok'); edYukle(); }
      else msj(\$('edMsj'), (d&&d.hata)||S.baglanti, 'err');
    }).catch(function(){ msj(\$('edMsj'), S.baglanti, 'err'); });
  }

  /* ---- Baş editör: hesap daveti ve editör listesi ---- */
  var BAS_HESAPLAR = [];

  function basKur(h){
    /* Rol seçenekleri sunucudan çekilir. basKur yalnız baş editörde
       çağrılıyor; uç de aynı yetkiyi arıyor, yani liste yetkisi
       olmayana hiç gitmez. */
    dvRolYukle();
    /* Editör atama süreli olabilir. Süre dolduysa bölüm kapanır ve
       yerine nedeni yazılır; baş editörlük kaydı yerinde durur. */
    var acik = h.editor_atama !== false;
    goster(\$('dvEdKutu'), acik);
    goster(\$('dvEdAck'), acik);
    goster(\$('dvSureBitti'), !acik);
    if (!acik) {
      \$('dvSureBitti').textContent = (EN
        ? 'Your authority to appoint editors ended on ' + tarihYaz(h.yetki_bitis) + '. Your record as chief editor and your other powers, assigning reviewers and adding editorial notes, are unchanged.'
        : 'Editör atama yetkiniz ' + tarihYaz(h.yetki_bitis) + ' tarihinde sona erdi. Baş editörlük kaydınız ve öteki yetkileriniz, hakem atama ile editöryal not düşme, olduğu gibi durur.');
    } else if (h.yetki_bitis) {
      \$('dvEdAck').textContent = (EN
        ? 'An editor may assign a reviewer to any work and add an editorial note. Every role you give or take is recorded with your name and the time. Your authority to appoint editors runs until ' + tarihYaz(h.yetki_bitis) + '.'
        : 'Editör, istediği çalışmaya hakem atayabilir ve editöryal not düşebilir. Verdiğiniz ve aldığınız her rol adınız ve saatiyle kayda geçer. Editör atama yetkiniz ' + tarihYaz(h.yetki_bitis) + ' tarihine kadardır.');
      basListeYukle();
    } else {
      basListeYukle();
    }
  }

  function tarihYaz(g){
    if(!g) return '';
    var p=String(g).split('-'); if(p.length!==3) return String(g);
    var tr=['','Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    var en=['','January','February','March','April','May','June','July','August','September','October','November','December'];
    var a=parseInt(p[1],10), gg=parseInt(p[2],10);
    if(!(a>=1&&a<=12)) return String(g);
    return EN ? (en[a]+' '+gg+', '+p[0]) : (gg+' '+tr[a]+' '+p[0]);
  }

  function basListeYukle(){
    api('/hesap/liste').then(function(d){
      if(!d||!d.ok) return;
      BAS_HESAPLAR = d.hesaplar||[];
      basListeCiz();
    }).catch(function(){});
  }

  function basListeCiz(){
    var kutu=\$('dvListe'); if(!kutu) return;
    var q=(\$('dvAra').value||'').trim().toLocaleLowerCase(EN?'en':'tr');
    var liste=BAS_HESAPLAR.filter(function(x){
      if(!q) return (x.roller||[]).indexOf('editor')>=0;   /* arama yoksa yalnızca editörler */
      return ((x.ad||'')+' '+(x.kurum||'')+' '+(x.eposta||'')).toLocaleLowerCase(EN?'en':'tr').indexOf(q)>=0;
    }).slice(0,40);
    kutu.innerHTML='';
    if(!liste.length){
      kutu.innerHTML='<p class="pn-ack">'+(q
        ? (EN?'No account matches this search.':'Bu aramaya uyan hesap yok.')
        : (EN?'No editor has been appointed yet. Search for an account above to appoint one.':'Henüz editör atanmamış. Atamak için yukarıdan bir hesap arayın.'))+'</p>';
      return;
    }
    liste.forEach(function(x){
      var editorMu=(x.roller||[]).indexOf('editor')>=0;
      var basMu=(x.roller||[]).indexOf('bas_editor')>=0;
      var s=document.createElement('div'); s.className='pn-sat';
      s.innerHTML='<span><b></b><br><small></small></span>';
      s.querySelector('b').textContent=x.gorunen||x.ad;
      s.querySelector('small').textContent=(x.kurum||'')+(x.kurum?' · ':'')+(x.eposta||'')
        +(basMu?(' · '+(EN?'chief editor':'baş editör')):(editorMu?(' · '+(EN?'editor':'editör')):''));
      if(!basMu){
        var b=document.createElement('button');
        b.className='d d-kucuk '+(editorMu?'d-sessiz':'d-vurgu');
        b.textContent=editorMu?(EN?'Remove as editor':'Editörlükten çıkar'):(EN?'Make editor':'Editör yap');
        b.addEventListener('click',function(){ rolVer(x.eposta, !editorMu); });
        var sar=document.createElement('span'); sar.className='ara-oto';
        sar.appendChild(b); s.appendChild(sar);
      }
      kutu.appendChild(s);
    });
  }

  function rolVer(eposta, ver){
    msj(\$('dvRolMsj'), S.bekle);
    api('/hesap/rol',{eposta:eposta, rol:'editor', ver:ver}).then(function(d){
      if(d&&d.ok){ msj(\$('dvRolMsj'), S.kaydedildi, 'ok'); basListeYukle(); }
      else msj(\$('dvRolMsj'), (d&&d.hata)||S.baglanti, 'err');
    }).catch(function(){ msj(\$('dvRolMsj'), S.baglanti, 'err'); });
  }

  if(\$('dvAra')) \$('dvAra').addEventListener('input', basListeCiz);

  /* ---- DAVET ROLLERİ ----
     Liste sunucudan gelir. Panelin gördüğü ile ucun uyguladığı aynı
     işlevden (hs_davet_rolleri) üretilir; iki yerde iki ayrı liste
     yazılsaydı bir gün biri ötekinin kapattığı kapıyı açardı. */
  var DV_ROL = 'okur', DV_ROLLER = [];
  function dvRolCiz(){
    var kutu=\$('dvRolListe'); if(!kutu) return;
    kutu.innerHTML='';
    DV_ROLLER.forEach(function(r){
      var l=document.createElement('label');
      l.className='onay-kart' + (r.acik ? '' : ' pn-kapali');
      var i=document.createElement('input');
      i.type='radio'; i.name='dvRol'; i.value=r.k;
      i.disabled=!r.acik;
      if(r.acik && r.k===DV_ROL) i.checked=true;
      i.addEventListener('change', function(){ if(i.checked){ DV_ROL=r.k; dvRolOzetYaz(); } });
      var sp=document.createElement('span');
      var b=document.createElement('b'); b.textContent=r.ad; sp.appendChild(b);
      var s2=document.createElement('small'); s2.textContent=r.ack; sp.appendChild(s2);
      /* Kapalı olanın sebebi ayrı bir satırda ve vurgulu durur: bir
         seçeneğin neden kapalı olduğu, seçeneğin kendisi kadar bilgidir. */
      if(!r.acik && r.sebep){
        var n=document.createElement('small'); n.className='pn-sebep'; n.textContent=r.sebep;
        sp.appendChild(n);
      }
      l.appendChild(i); l.appendChild(sp);
      kutu.appendChild(l);
    });
    dvRolOzetYaz();
  }
  function dvRolOzetYaz(){
    var e=\$('dvRolOzet'); if(!e) return;
    var r=DV_ROLLER.filter(function(x){ return x.k===DV_ROL; })[0];
    e.textContent = r ? r.ad : (EN?'Reader':'Okur');
  }
  function dvRolYukle(){
    if(!\$('dvRolListe')) return;
    /* Dil api() içinde her isteğe ekleniyor; burada ayrıca istenmez. */
    api('/yonetim/davet-rolleri').then(function(d){
      if(!d||!d.ok) return;
      DV_ROLLER = d.roller||[];
      /* İlk AÇIK seçenek seçili gelir. Varsayılanın kapalı bir seçenek
         olması, düğmeye basınca 403 almak demekti. */
      var ilk = DV_ROLLER.filter(function(x){ return x.acik; })[0];
      /* Açık seçenek yoksa DV_ROL BOŞ kalır. Eskiden 'okur' yazılıydı;
         o satır kaldırıldı (okumak için kayıt gerekmiyor) ve boşta
         kalan varsayılan, olmayan bir role davet gönderirdi. Boş
         gönderilirse uç sorar; sessizce bir rol seçmez. */
      DV_ROL = ilk ? ilk.k : '';
      dvRolCiz();
    }).catch(function(){});
  }

  if(\$('dgDavet')) \$('dgDavet').addEventListener('click', function(){
    var ad=\$('dvAd').value.trim(), ep=\$('dvEposta').value.trim();
    if(!ad){ msj(\$('dvMsj'), S.eAd, 'err'); return; }
    if(ep.indexOf('@')<1){ msj(\$('dvMsj'), S.ePosta, 'err'); return; }
    \$('dgDavet').disabled=true;
    msj(\$('dvMsj'), S.bekle);
    goster(\$('dvSonuc'), false);
    api('/yonetim/hesap-davet',{ad:ad, eposta:ep, unvan:\$('dvUnvan').value,
                                kurum:\$('dvKurum').value.trim(), rol:DV_ROL})
     .then(function(d){
       \$('dgDavet').disabled=false;
       if(d&&d.ok){
         msj(\$('dvMsj'), '');
         \$('dvBag').value=d.bag||'';
         \$('dvUyari').textContent = (EN
           ? 'The link for ' + (d.ad||'') + (d.rol_ad ? ' (' + d.rol_ad + ')' : '') + ' is below. It is shown once; copy it now and send it. It expires in fourteen days and can be used once.'
           : (d.ad||'') + (d.rol_ad ? ' (' + d.rol_ad + ')' : '') + ' için bağlantı aşağıda. Bir kez gösterilir; şimdi kopyalayıp gönderin. On dört günde düşer ve bir kez kullanılır.');
         /* BAŞ EDİTÖRLÜKTE DAVET TEK BAŞINA YETMEZ ve bunu söylememek,
            yarım yapılmış bir atamayı tamam gibi göstermek olurdu. */
         var ku=\$('dvKurulNot');
         if(ku){
           ku.textContent = d.kurul_kaydi_gerek
             ? (EN ? 'The account is opened, but chief editorship does not begin with this invitation: the role is resolved from the board record in the configuration file on every read. Until that record is written, this person is not a chief editor.'
                   : 'Hesap açıldı, ama baş editörlük bu davetle başlamaz: rol her okumada ayar dosyasındaki kurul kaydından çözülür. O kayıt yazılmadıkça bu kişi baş editör olmaz.')
             : '';
           goster(ku, !!d.kurul_kaydi_gerek);
         }
         if(\$('dvMetin')) \$('dvMetin').value = davetMetni(d.ad||'', d.bag||'');
         goster(\$('dvSonuc'), true);
         \$('dvAd').value=''; \$('dvEposta').value=''; \$('dvKurum').value=''; \$('dvUnvan').value='';
         basListeYukle();
       } else msj(\$('dvMsj'), (d&&d.hata)||S.baglanti, 'err');
     })
     .catch(function(){ \$('dgDavet').disabled=false; msj(\$('dvMsj'), S.baglanti, 'err'); });
  });

  /* Hazır davet metni. Sistem kendini önce tanıtır, sonra ne istediğini
     söyler, sonra teşekkür eder. Övünmez ve acele ettirmez: karşıdaki
     kişi bir işe değil, gönüllü bir emeğe çağrılıyor. */
  function davetMetni(ad, bag){
    var m = \$('dvAd') && \$('dvAd').value.trim() ? \$('dvAd').value.trim() : ad;
    if (EN) {
      return 'Dear ' + m + ',\\n\\n'
        + 'I would like to invite you to Kutadgu, an independent open access publishing system.\\n\\n'
        + 'Kutadgu publishes scholarly work free of charge. No processing fee is taken from authors, no '
        + 'subscription from readers and no access fee from institutions, and none may ever be. Review is '
        + 'not closed but open: reports, decisions and their reasoning stand on the page of the work itself, '
        + 'under the names of those who wrote them. The measure of a work is not its author\'s title or '
        + 'institution but the soundness of its method and the verifiability of its sources.\\n\\n'
        + 'An account has been opened in your name. You may submit work, review, or simply keep the account '
        + 'and use it whenever you wish. No password has been sent to you; you set your own from the link '
        + 'below and no one else ever sees it. The link works once and expires in fourteen days.\\n\\n'
        + bag + '\\n\\n'
        + 'Thank you in advance for the time you will give to scholarship. Whether this system works at all '
        + 'depends on people who take the time to read a work and write down their reasoning; it rests on '
        + 'nothing else.\\n\\n'
        + 'The declaration: https://kutadgu.net/bildiri.php\\n'
        + 'Editorial policies: https://kutadgu.net/ilkeler.php\\n';
    }
    return 'Sayın ' + m + ',\\n\\n'
      + 'Sizi bağımsız ve açık erişimli bir yayın sistemi olan Kutadgu\'ya davet etmek istiyorum.\\n\\n'
      + 'Kutadgu bilimsel çalışmaları ücretsiz yayımlar. Yazardan işlem ücreti, okurdan abonelik, kurumdan '
      + 'erişim bedeli alınmaz ve alınamaz. Hakemlik kapalı değil açıktır: raporlar, kararlar ve gerekçeleri '
      + 'çalışmanın kendi sayfasında, yazanların adlarıyla birlikte durur. Bir çalışmanın ölçüsü yazarının '
      + 'unvanı ya da kurumu değil, yönteminin sağlamlığı ve kaynaklarının denetlenebilirliğidir.\\n\\n'
      + 'Adınıza bir hesap açıldı. Çalışma gönderebilir, hakemlik yapabilir ya da hesabı yalnızca üzerinizde '
      + 'tutup dilediğiniz zaman kullanabilirsiniz. Size bir parola gönderilmedi; parolanızı aşağıdaki '
      + 'bağlantıdan kendiniz kurarsınız ve onu sizden başka kimse görmez. Bağlantı bir kez çalışır ve on '
      + 'dört günde düşer.\\n\\n'
      + bag + '\\n\\n'
      + 'Bilime ayıracağınız zaman için şimdiden teşekkür ederim. Bu sistemin işleyebilmesi, çalışmayı okuyup '
      + 'gerekçesini yazmaya vakit ayıran insanlara bağlıdır; başka bir dayanağı yoktur.\\n\\n'
      + 'Bildiri: https://kutadgu.net/bildiri.php\\n'
      + 'Yayın ilkeleri: https://kutadgu.net/ilkeler.php\\n';
  }

  if(\$('dgMetinKopya')) \$('dgMetinKopya').addEventListener('click', function(){
    var i=\$('dvMetin'); i.select(); i.setSelectionRange(0, 99999);
    var bitti=function(){ \$('dgMetinKopya').textContent=EN?'Copied':'Kopyalandı';
      setTimeout(function(){ \$('dgMetinKopya').textContent=EN?'Copy the message':'İletiyi kopyala'; }, 2000); };
    if(navigator.clipboard&&navigator.clipboard.writeText){
      navigator.clipboard.writeText(i.value).then(bitti).catch(function(){});
    } else { try{ document.execCommand('copy'); bitti(); }catch(x){} }
  });

  /* WhatsApp devri. Yeni sekmede açılır; panel yerinde kalır, çünkü
     davet bağlantısı ekranda bir kez gösterilir ve sayfadan çıkmak onu
     kaybetmek olurdu. */
  if(\$('dgWhats')) \$('dgWhats').addEventListener('click', function(){
    var metin = (\$('dvMetin') && \$('dvMetin').value) || '';
    if(!metin){ return; }
    /* Numara: yalnız rakam. Baştaki 0 ve + işareti, ülke kodu yazılmış
       numaralarda wa.me'yi kırar; ayıklanır. */
    var tel = ((\$('dvTel') && \$('dvTel').value) || '').replace(/[^0-9]/g, '');
    if(tel.charAt(0) === '0') tel = tel.slice(1);
    var adres = 'https://wa.me/' + tel + '?text=' + encodeURIComponent(metin);
    window.open(adres, '_blank', 'noopener');
  });

  if(\$('dgDavetKopya')) \$('dgDavetKopya').addEventListener('click', function(){
    var i=\$('dvBag'); i.select(); i.setSelectionRange(0, 99999);
    var bitti=function(){ \$('dgDavetKopya').textContent=EN?'Copied':'Kopyalandı';
      setTimeout(function(){ \$('dgDavetKopya').textContent=EN?'Copy':'Kopyala'; }, 2000); };
    if(navigator.clipboard&&navigator.clipboard.writeText){
      navigator.clipboard.writeText(i.value).then(bitti).catch(function(){});
    } else { try{ document.execCommand('copy'); bitti(); }catch(x){} }
  });

  /* ---- Açılış ---- */
  api('/hesap/ben').then(function(d){
    goster(\$('pnYuk'), false);
    if (d && d.ok && d.girisli) panelDoldur(d.hesap);
    else goster(\$('pnKapi'), true);
  }).catch(function(){ goster(\$('pnYuk'), false); goster(\$('pnKapi'), true); });

  /* ---- Sekmeler ---- */
  \$('sekGiris').addEventListener('click',function(){
    \$('sekGiris').setAttribute('aria-selected','true'); \$('sekKayit').setAttribute('aria-selected','false');
    goster(\$('formGiris'),true); goster(\$('formKayit'),false); msj(\$('kapiMsj'),'');
  });
  /* ORCID dönüşünün sonucu adreste taşınır; okunur, gösterilir ve
     adresten SİLİNİR. Silinmezse kişi sayfayı yenilediğinde aynı
     iletiyi bir kez daha görür ve yeni bir şey olduğunu sanır. */
  (function(){
    var u = new URL(location.href), d = u.searchParams.get('orcid');
    if(!d) return;
    var m = (S.orcidD||{})[d];
    if(m){
      var hedef = \$('kapiMsj') || \$('profilMsj');
      if(hedef) msj(hedef, m, (d==='baglandi'||d==='giris') ? '' : 'err');
    }
    u.searchParams.delete('orcid');
    history.replaceState(null,'',u.toString());
  })();

  \$('sekKayit').addEventListener('click',function(){
    \$('sekKayit').setAttribute('aria-selected','true'); \$('sekGiris').setAttribute('aria-selected','false');
    goster(\$('formKayit'),true); goster(\$('formGiris'),false); msj(\$('kapiMsj'),'');
  });

  /* ---- Giriş ---- */
  /* ---------------------------------------------------------------
     GİRİŞTEN SONRA NEREYE
     ---------------------------------------------------------------
     Kurul kararı, 15 Ağustos 2026: "çalışma gönder kısmı kişiyi kayıt
     sayfasına yöneltmeli ki yazar alanları boş olmasın." Kişi başvuru
     formundan buraya gönderiliyorsa, girişten sonra FORMA dönmelidir;
     panelde bırakmak onu ikinci kez yola çıkarmaktır.

     ADRES DIŞARIDAN GELİR AMA GÜVENİLMEZ. ?donus= değeri açık bir
     yönlendirme (open redirect) kapısıdır: "//kotu.site" ya da
     "https://kotu.site" yazan bir bağlantı, kutadgu.net adresinden
     çıkıp başka bir yere götüren bir tuzak olurdu. Bu yüzden yalnız
     TEK EĞİK ÇİZGİYLE başlayan, ikinci bir eğik çizgi ve ters eğik
     çizgi içermeyen yollar kabul edilir; başkası varsa sayfa
     yenilenir. Aynı denetim sunucu tarafında da yapılır. */
  function donusVeyaYenile(){
    var d = '';
    try { d = new URLSearchParams(location.search).get('donus') || ''; } catch(e) { d = ''; }
    /* TERS EĞİK ÇİZGİ HARF KODUYLA ARANIR, KAÇIŞLA DEĞİL.
       Bu betik PHP heredoc'un içinde duruyor: '\\' yazmak heredoc'ta
       TEK ters eğik çizgiye iner ve tarayıcıya kapanmamış bir dizge
       gider — panelin bütün betiği "Invalid or unexpected token" ile
       düşer, giriş kutusu hiç görünmez. Ölçüldü ve öyle oldu.
       String.fromCharCode(92) hiçbir kaçış gerektirmez.
       Yalnız ilk iki karakter denetlenir; tarayıcılar '/' ile
       başlayan adresi '//' gibi okur ve açık yönlendirme oradan
       çıkar. Sonrasındaki bir ters eğik çizgi yol adının parçasıdır. */
    var ilk = d.charAt(0), iki = d.charAt(1);
    if (d && ilk === '/' && iki !== '/' && iki !== String.fromCharCode(92)) {
      location.assign(d); return;
    }
    location.reload();
  }
  \$('dgGiris').addEventListener('click',function(){
    var kim=\$('gKim').value.trim(), p=\$('gParola').value;
    if(!kim) return msj(\$('kapiMsj'),S.eKim,'err');
    if(!p) return msj(\$('kapiMsj'),S.eParola,'err');
    msj(\$('kapiMsj'),S.bekle);
    /* =================================================================
       GİRİŞ BAŞARILIYSA SAYFA YENİDEN YÜKLENİR — panelDoldur ÇAĞRILMAZ

       Eskiden burada panelDoldur(d.hesap) vardı ve şu oluyordu: giriş
       sunucuda BAŞARIYLA tamamlanıyor, oturum açılıyor, sonra istemci
       tarafı paneli kurmaya çalışırken bir istisna fırlatıyordu. O
       istisna aşağıdaki .catch()'e düşüyor ve ekranda "Bağlantı
       kurulamadı" yazıyordu. Kullanıcının gördüğü şey buydu: hata
       iletisi çıkıyor, ama sayfa yenilenince içeride oluyordu.

       Sebep sayfanın kendisindeydi. Yönetim ve editör kartları SUNUCUDA,
       $basYetki/$edYetki koşuluyla basılır. Giriş yapılmadan açılan
       sayfada o kartların hiçbiri yoktur. panelDoldur ise girişten sonra
       gelen kayda bakıp basKur() ve yonetimKur() çağırıyor, onlar da
       var olmayan ögelere uzanıyordu.

       İstemci, sunucunun basmadığı bir bölümü var edemez. Doğru olan
       şey sayfayı yeniden istemektir: sunucu bu kez kimliği bilerek
       basar ve panel eksiksiz gelir. Bir yeniden yükleme, yarım kurulmuş
       bir panelden ucuzdur.
       ================================================================= */
    api('/hesap/giris',{kim:kim,parola:p}).then(function(d){
      if(d&&d.ok){ msj(\$('kapiMsj'),S.girisTamam); donusVeyaYenile(); return; }
      msj(\$('kapiMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('kapiMsj'),S.baglanti,'err'); });
  });
  \$('gParola').addEventListener('keydown',function(e){ if(e.key==='Enter') \$('dgGiris').click(); });

  /* ---- Kayıt ---- */
  \$('dgKayit').addEventListener('click',function(){
    var ad=\$('kAd').value.trim(), ep=\$('kEposta').value.trim(), p=\$('kParola').value;
    if(!ad) return msj(\$('kapiMsj'),S.eAd,'err');
    if(!/.+@.+\\..+/.test(ep)) return msj(\$('kapiMsj'),S.ePosta,'err');
    if(p.length<10) return msj(\$('kapiMsj'),S.eParola,'err');
    msj(\$('kapiMsj'),S.bekle);
    api('/hesap/kayit',{ad:ad,eposta:ep,parola:p,unvan:\$('kUnvan').value,
      kurum:\$('kKurum').value.trim(),orcid:orcidTemiz(\$('kOrcid').value),
      alanlar:(window.alSecOku?window.alSecOku('kAlan'):[])}).then(function(d){
      /* Kayıt da girişle aynı yolu izler: sunucu oturumu açtı, sayfa
         yeniden istenir. Yeni hesabın yetkisi yoktur ama kural aynı
         kalsın; iki ayrı davranış, biri düzeltilip öteki unutulur. */
      if(d&&d.ok){ msj(\$('kapiMsj'),S.girisTamam); donusVeyaYenile(); return; }
      msj(\$('kapiMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('kapiMsj'),S.baglanti,'err'); });
  });

  /* ---- Çıkış ---- */
  \$('dgCikis').addEventListener('click',function(){
    api('/hesap/cikis',{}).then(function(){ location.reload(); });
  });

  /* ---- Kullanıcı adı ---- */
  \$('dgKullanici').addEventListener('click',function(){
    var k=\$('kuAd').value.trim().toLowerCase();
    msj(\$('kuMsj'),S.bekle);
    api('/hesap/kullanici-sec',{kullanici:k}).then(function(d){
      if(d&&d.ok){ msj(\$('kuMsj'),S.kaydedildi,'ok'); panelDoldur(d.hesap); }
      else msj(\$('kuMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('kuMsj'),S.baglanti,'err'); });
  });

  /* ---- Editör: çalışmalar ---- */
  var CALISMALAR = [];
  function calYukle(){
    api('/editor/calismalar').then(function(d){
      if(!d||!d.ok) return;
      CALISMALAR = d.calismalar||[];
      calCiz();
    });
  }
  function calCiz(){
    var q = (\$('calAra').value||'').toLowerCase().trim();
    var kutu = \$('calListe'); kutu.innerHTML='';
    var liste = CALISMALAR.filter(function(c){
      if(!q) return true;
      return (c.baslik||'').toLowerCase().indexOf(q)>=0 || (c.yazar||'').toLowerCase().indexOf(q)>=0;
    }).slice(0, q ? 40 : 12);
    if(!liste.length){ kutu.innerHTML='<p class="pn-ack">'+S.bosCal+'</p>'; return; }
    liste.forEach(function(c){
      /* KAPAK: adı, yazarı ve rozetleri taşır; ayrıntı içeride durur.
         Karar bekleyen bir insan varsa kapak açık doğar. */
      var bekleyen = (c.oneriler||[]).length + (c.gonulluler||[]).length;
      var d=document.createElement('details'); d.className='pn-cal';
      if(bekleyen>0) d.open=true;
      var ozet=document.createElement('summary'); d.appendChild(ozet);
      var h3=document.createElement('h3'); h3.textContent=c.baslik||''; ozet.appendChild(h3);
      /* TARİH HAM HÂLİYLE BASILIYORDU: "2026-09-15T12:00:00+03:00".
         Kapak öne çıkınca göze de battı; makine biçimini insana
         göstermek, veriyi göstermek değil ham hâlini göstermektir. */
      var kim=document.createElement('div'); kim.className='kim';
      kim.textContent=(c.yazar||'')+(c.tarih?(' · '+gunAd(c.tarih)):''); ozet.appendChild(kim);
      var rz=document.createElement('div'); rz.className='rzler';
      function rozet(t,s2){ var x=document.createElement('span'); x.className='rz '+s2; x.textContent=t; rz.appendChild(x); }
      if(c.onayli) rozet(S.onayliRz,'rz-yes');
      if(c.araniyor) rozet(S.araniyor,'rz-kut');
      rozet((c.hakemler||[]).length+' '+(EN?'reviewers':'hakem'),'rz-cizgi');
      /* Rozet neyi saydığını söyler: çıplak bir sayı bir merak,
         adıyla birlikte bir iştir. */
      if(bekleyen>0) rozet(bekleyen+' '+(EN?(bekleyen===1?'person awaits a decision':'people await a decision')
                                           :'kişi karar bekliyor'),'rz-kir');
      ozet.appendChild(rz);

      /* Kapağın altı. Buradan aşağısı yalnız açıldığında çizilir
         DEĞİL — çizilir ama gösterilmez; tarayıcı <details> kapalıyken
         düzeni hesaplamaz, sayfanın boyu da o kadar uzamaz. */
      var ic=document.createElement('div'); ic.className='pn-cal-ic'; d.appendChild(ic);

      if((c.hakemler||[]).length){
        var hk=document.createElement('div'); hk.className='pn-hk';
        c.hakemler.forEach(function(h){
          var x=document.createElement('div');
          var b=document.createElement('b'); b.textContent=h.ad||''; x.appendChild(b);
          var durum = h.rapor_var ? (EN?'report received':'rapor geldi')
            : h.davet==='bekliyor' ? (EN?'invitation pending':'davet bekliyor')
            : h.davet==='suresi_doldu' ? (EN?'invitation expired':'davetin süresi doldu')
            : h.davet==='geri_cekildi' ? (EN?'invitation withdrawn':'davet geri çekildi')
            : h.davet==='ret' ? (EN?'declined':'davet reddedildi')
            : (EN?'no report yet':'rapor yok');
          x.appendChild(document.createTextNode(' · '+durum+(h.atayan?(' · '+h.atayan):'')));
          /* Süresi dolmuş ya da reddedilmiş davet kapatılabilir; yerine
             başka hakem çağrılabilsin diye. Rapor yazılmışsa kapatılamaz. */
          if(!h.rapor_var && (h.davet==='suresi_doldu'||h.davet==='ret')){
            var bi=document.createElement('button');
            bi.className='d d-sessiz d-kucuk'; bi.style.marginLeft='8px';
            bi.textContent=S.daveti_cek;
            bi.addEventListener('click',function(){
              bi.disabled=true;
              api('/editor/davet-iptal',{id:c.id,ad:h.ad}).then(function(r){
                if(r&&r.ok) calYukle(); else { bi.disabled=false; }
              }).catch(function(){ bi.disabled=false; });
            });
            x.appendChild(bi);
          }
          hk.appendChild(x);
        });
        ic.appendChild(hk);
      }

      /* ---- Yazarın önerdiği hakemler ----
         Gönüllülerin hemen üstünde durur. İkisi de "bir insan var,
         karar bekliyor" demektir, ama kaynakları farklıdır ve bu
         ayrımın görünmesi gerekir: gönüllü kendisi başvurdu, öneriyi
         yazar yaptı. Editör hangisine baktığını bilmelidir. */
      (c.oneriler||[]).forEach(function(o){
        var x=document.createElement('div'); x.className='pn-gon';
        var b=document.createElement('b'); b.textContent=S.oneriBas+': '+o.ad; x.appendChild(b);
        var s4=document.createElement('span');
        s4.textContent=(o.kurum?o.kurum+' · ':'')+(o.orcid?('ORCID '+o.orcid+' · '):'')
          +S.onerenEt+' '+(o.oneren||'')+(o.adres_var?'':(' · '+S.adresYok));
        x.appendChild(s4);
        /* Tekrar eden eşleşme uyarısı. Engel değil: bu kişi bu yazarın
           başka çalışmalarını da değerlendirmiş olabilir ve dar bir
           alanda bu olağandır. Ama editör bilerek karar vermeli. */
        if(o.tekrar>0){
          var t2=document.createElement('div');
          t2.className='kutu kutu-kut'; t2.style.marginTop='8px';
          t2.textContent=S.tekrarUyari.replace('%d', o.tekrar);
          x.appendChild(t2);
        }
        if(o.gerekce){
          var g3=document.createElement('div'); g3.style.marginTop='6px'; g3.style.color='var(--metin-2)';
          g3.textContent=o.gerekce; x.appendChild(g3);
        }
        var dl2=document.createElement('div'); dl2.className='dgler';
        var c1=document.createElement('button'); c1.className='d d-vurgu d-kucuk'; c1.textContent=S.oneriKabul;
        var c2=document.createElement('button'); c2.className='d d-sessiz d-kucuk'; c2.textContent=S.reddet2;
        c1.addEventListener('click',function(){ oneriKarar(c.id,o.kod,'kabul'); });
        c2.addEventListener('click',function(){
          var n=prompt(S.oneriNeden)||'';
          oneriKarar(c.id,o.kod,'ret',n);
        });
        dl2.appendChild(c1); dl2.appendChild(c2); x.appendChild(dl2);
        ic.appendChild(x);
      });

      (c.gonulluler||[]).forEach(function(g2){
        var x=document.createElement('div'); x.className='pn-gon';
        var b=document.createElement('b'); b.textContent=S.gonulluBas+': '+((g2.unvan?g2.unvan+' ':'')+g2.ad); x.appendChild(b);
        var s3=document.createElement('span');
        s3.textContent=(g2.kurum?g2.kurum+' · ':'')+(g2.sifat||'')+(g2.orcid?(' · ORCID '+g2.orcid):'')
          +(g2.belge==='edevlet'?(' · e-Devlet '+(g2.belge_kod||'')):(g2.belge_url?(' · '+g2.belge_url):''));
        x.appendChild(s3);
        var y=document.createElement('div'); y.style.marginTop='6px'; y.style.color='var(--metin-2)'; y.textContent=g2.yetkinlik||'';
        x.appendChild(y);
        var dl=document.createElement('div'); dl.className='dgler';
        var b1=document.createElement('button'); b1.className='d d-vurgu d-kucuk'; b1.textContent=S.kabulEt;
        var b2=document.createElement('button'); b2.className='d d-sessiz d-kucuk'; b2.textContent=S.reddet2;
        b1.addEventListener('click',function(){ gonulluKarar(c.id,g2.kod,'kabul'); });
        b2.addEventListener('click',function(){ gonulluKarar(c.id,g2.kod,'ret'); });
        dl.appendChild(b1); dl.appendChild(b2); x.appendChild(dl);
        ic.appendChild(x);
      });

      var ac=document.createElement('div'); ac.className='pn-cal-ac';
      var bAta=document.createElement('button'); bAta.className='d d-vurgu d-kucuk'; bAta.textContent=S.hakemAta;
      var bNot=document.createElement('button'); bNot.className='d d-ikinci d-kucuk'; bNot.textContent=S.notEkle;
      ac.appendChild(bAta); ac.appendChild(bNot); ic.appendChild(ac);

      var form=document.createElement('div'); form.className='pn-cal-form gizli'; ic.appendChild(form);
      bAta.addEventListener('click',function(){
        form.classList.remove('gizli');
        form.innerHTML='<div class="pn-havuz" data-havuz></div>'
          +'<label>'+S.hAd+'</label><input type="text" class="f-ad">'
          +'<label>'+S.hEposta+'</label><input type="email" class="f-ep">'
          +'<div class="pn-cal-ac"><button class="d d-vurgu d-kucuk f-gonder"></button>'
          +'<button class="d d-sessiz d-kucuk f-vaz"></button></div><div class="form-msj f-msj"></div>';
        havuzCiz(form.querySelector('[data-havuz]'), c, form);
        form.querySelector('.f-gonder').textContent=S.gonder;
        form.querySelector('.f-vaz').textContent=S.vazgec;
        form.querySelector('.f-vaz').addEventListener('click',function(){ form.classList.add('gizli'); });
        form.querySelector('.f-gonder').addEventListener('click',function(){
          var m=form.querySelector('.f-msj');
          m.textContent=S.bekle; m.className='form-msj';
          api('/editor/hakem-ata',{id:c.id, ad:form.querySelector('.f-ad').value.trim(),
            eposta:form.querySelector('.f-ep').value.trim()}).then(function(r){
            if(r&&r.ok){ davetSonuc(m, r, c.baslik); calYukle(); }
            else { m.textContent=(r&&r.hata)||S.baglanti; m.className='form-msj err'; }
          }).catch(function(){ m.textContent=S.baglanti; m.className='form-msj err'; });
        });
      });
      bNot.addEventListener('click',function(){
        form.classList.remove('gizli');
        form.innerHTML='<label>'+S.notMetin+'</label><textarea class="f-not"></textarea>'
          +'<div class="pn-cal-ac"><button class="d d-vurgu d-kucuk f-gonder"></button>'
          +'<button class="d d-sessiz d-kucuk f-vaz"></button></div><div class="form-msj f-msj"></div>';
        form.querySelector('.f-gonder').textContent=S.gonder;
        form.querySelector('.f-vaz').textContent=S.vazgec;
        form.querySelector('.f-vaz').addEventListener('click',function(){ form.classList.add('gizli'); });
        form.querySelector('.f-gonder').addEventListener('click',function(){
          var m=form.querySelector('.f-msj');
          m.textContent=S.bekle; m.className='form-msj';
          api('/editor/not',{id:c.id, metin:form.querySelector('.f-not').value.trim()}).then(function(r){
            if(r&&r.ok){ m.textContent=S.kaydedildi; m.className='form-msj ok'; calYukle(); }
            else { m.textContent=(r&&r.hata)||S.baglanti; m.className='form-msj err'; }
          }).catch(function(){ m.textContent=S.baglanti; m.className='form-msj err'; });
        });
      });
      kuto(kutu, d);
    });
    /* Süreler ve karar dağılımı aynı listeden türer; ayrı bir uç yok. */
    sureKararCiz();
    boslukCiz();
    /* İletiler ayrı bir uçtan gelir ve editör bölümüyle birlikte
       yüklenir: bekleyen sayısı sekme şeridinde de görünsün diye
       sekmenin açılması beklenmez. YALNIZ BAŞ EDİTÖR İÇİN: uç sıradan
       editöre 403 döner ve çağrı, panelin her açılışında ekrana bir
       yetki hatası düşürürdü. */
    if (BAS_YETKI) iltListeCiz();
  }
  function kuto(kutu, d){ kutu.appendChild(d); }
  /* Yazar önerisini karara bağlar. Kabulde hakem kaydı sunucuda oluşur
     ve davet sistemden gider; yazarın eline erişim bilgisi geçmez. */
  function oneriKarar(id,kod,karar,neden){
    msj(\$('calMsj'), S.bekle);
    api('/editor/oneri-karar',{id:id, kod:kod, karar:karar, neden:neden||''}).then(function(d){
      if(d&&d.ok){ msj(\$('calMsj'), S.kaydedildi, 'ok'); calYukle(); }
      else msj(\$('calMsj'), (d&&d.hata)||S.baglanti, 'err');
    }).catch(function(){ msj(\$('calMsj'), S.baglanti, 'err'); });
  }

  function gonulluKarar(id, kod, karar){
    msj(\$('calMsj'), S.bekle);
    api('/yonetim/gonullu-karar',{id:id, kod:kod, karar:karar, atayan_ad:(HESAP&&HESAP.gorunen)||'',
        atayan_tur:(HESAP&&HESAP.roller&&HESAP.roller.indexOf('bas_editor')>=0)?'bas_editor':'editor',
        belge_onay:true}).then(function(d){
      if(d&&d.ok){ msj(\$('calMsj'), S.kaydedildi, 'ok'); calYukle(); }
      else msj(\$('calMsj'), (d&&d.hata)||S.baglanti, 'err');
    }).catch(function(){ msj(\$('calMsj'), S.baglanti, 'err'); });
  }
  \$('calAra').addEventListener('input', calCiz);

  /* ---- Belge ---- */
  Array.prototype.forEach.call(document.querySelectorAll('input[name=bTur]'),function(r){
    r.addEventListener('change',function(){
      goster(\$('bOrcid'),   r.value==='orcid');
      goster(\$('bYabanci'), r.value==='yabanci');
      if(r.value==='orcid' && HESAP && HESAP.orcid && !\$('bOrcidNo').value) \$('bOrcidNo').value=HESAP.orcid;
    });
  });
  \$('dgOrcidDenetle').addEventListener('click',function(){
    msj(\$('orcidMsj'),S.bekle);
    api('/hesap/orcid-denetle',{}).then(function(d){
      if(d&&d.ok){
        var h=(d.mesaj||'');
        if(d.kayitlar&&d.kayitlar.length){
          h+='<ul style="margin:8px 0 0;padding-left:18px">';
          d.kayitlar.forEach(function(k){
            h+='<li>'+esc(k.unvan||'')+(k.kurum?' &middot; '+esc(k.kurum):'')+(k.yil?' &middot; '+esc(k.yil):'')+(k.doktora?' <b>('+(EN?'doctoral':'doktora')+')</b>':'')+'</li>';
          });
          h+='</ul>';
        }
        \$('orcidMsj').innerHTML=h; \$('orcidMsj').className='form-msj '+(d.doktora?'ok':'');
      } else msj(\$('orcidMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('orcidMsj'),S.baglanti,'err'); });
  });
  \$('dgBelge').addEventListener('click',function(){
    var t=(document.querySelector('input[name=bTur]:checked')||{}).value||'orcid';
    var g={belge_tur:t};
    if(t==='orcid'){
      g.belge_orcid=\$('bOrcidNo').value.trim()||((HESAP&&HESAP.orcid)||'');
      if(!/^\\d{4}-\\d{4}-\\d{4}-\\d{3}[0-9Xx]$/.test(g.belge_orcid))
        return msj(\$('belgeMsj'), EN?'Enter a valid ORCID (0000-0000-0000-0000).':'Geçerli bir ORCID girin (0000-0000-0000-0000).','err');
    } else {
      g.belge_url=\$('bUrl').value.trim(); g.belge_kurum=\$('bKurum').value.trim();
      if(!g.belge_url) return msj(\$('belgeMsj'),S.eUrl,'err');
    }
    msj(\$('belgeMsj'),S.bekle);
    api('/hesap/belge',g).then(function(d){
      if(d&&d.ok){ msj(\$('belgeMsj'), d.mesaj||S.kaydedildi, 'ok'); panelDoldur(d.hesap); gostergeYukle(); }
      else msj(\$('belgeMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('belgeMsj'),S.baglanti,'err'); });
  });

  /* ---- Sekme geçişi ----
     Sekmeye basınca sayfa kaymaz, yalnızca görünen bölüm değişir.
     Seçim adres çubuğunda saklanır ki yenilendiğinde aynı yerde kalınsın. */
  function sekAc(ad){
    /* GİZLİ BİR SEKME ADRESTEN AÇILAMAZ.
       Editör ve İletiler düğmeleri sayfada her zaman basılıdır, yetkisi
       olmayanda yalnız 'gizli' ile saklanır. Ölçüt "düğme var mı" olsaydı
       #iletiler yazan bir adres, yetkisi olmayan birinde bütün bölümleri
       kapatıp BOŞ BİR SAYFA bırakırdı: bölüm sunucuda hiç basılmıyor,
       düğme ise duruyor. Ölçüt artık düğmenin GÖRÜNÜR olmasıdır. */
    var bulundu=false;
    document.querySelectorAll('.pn-sk[data-sek]').forEach(function(b){
      var a = b.dataset.sek===ad && !b.classList.contains('gizli');
      if(a) bulundu=true;
      b.classList.toggle('acik',a); b.setAttribute('aria-selected',a?'true':'false');
    });
    if(!bulundu){
      var oz=document.querySelector('.pn-sk[data-sek="ozet"]');
      if(oz){ oz.classList.add('acik'); oz.setAttribute('aria-selected','true'); }
      return ad==='ozet' ? undefined : sekAc('ozet');
    }
    document.querySelectorAll('.pn-pnl').forEach(function(s){
      s.classList.toggle('acik', s.dataset.pnl===ad);
    });
    yolKur(ad);
    try{ history.replaceState(null,'','#'+ad); }catch(e){}
  }

  /* ---- ADRESTEN KARTA GİTMEK  (#sekme/kart) ----
     KURUL BİLDİRİMİ (20 Ağustos 2026): "bildirimi görüyorum ama
     bildirimle ilgili sayfanın açılıp ilgili bölümün belli edilmesi
     lazım."

     ÖLÇÜLEN KUSUR: adres yalnızca SEKME adı taşıyabiliyordu. Bir
     bildirim kişiyi Editör sekmesine bırakıyor, aradığı kart o
     sekmenin 5531 pikselinde bir yerde, üstelik kapağı kapalı
     duruyordu. Sekmeye götürmek, götürmüş sayılmaz.

     Adres artık iki parçalıdır: #editor/kartKarar. Kart bulunamazsa
     sekme yine açılır — bozuk bir adres kimseyi boş sayfada bırakmaz.
     Vurgu, kartın nerede olduğunu göz taramadan söyler ve kendi
     kendine söner; kalıcı bir çerçeve, bir sonraki ziyarette yalan
     söylerdi. */
  function adrestenGit(h){
    h = (h||'').replace(/^#/,'');
    if(!h) return false;
    var p = h.split('/');
    var sek = p[0], kartId = p[1] || '';
    if(!kartId){ sekAc(sek); return true; }
    sekAc(sek);
    var k = document.getElementById(kartId);
    if(!k) return true;                       /* sekme açıldı, kart yok */
    if(k.tagName==='DETAILS') k.open = true;
    kartSec(sek, kartId);
    k.scrollIntoView({behavior:'smooth', block:'start'});
    vurgula(k);
    return true;
  }
  /* Vurgu bir animasyondur ve animasyon istemeyene GÖSTERİLMEZ; onun
     yerine aynı süre boyunca duran bir çerçeve konur. Vurgunun kendisi
     bilgi taşır, hareketi taşımaz. */
  function vurgula(k){
    if(!k) return;
    k.classList.remove('pn-vurgu-ac');
    void k.offsetWidth;
    k.classList.add('pn-vurgu-ac');
    setTimeout(function(){ k.classList.remove('pn-vurgu-ac'); }, 2600);
    /* Odak da oraya taşınır: klavye ve ekran okuyucu kullanan kişi için
       "belli etmek" görsel bir çerçeve değil, odağın kendisidir. */
    try{
      if(!k.hasAttribute('tabindex')) k.setAttribute('tabindex','-1');
      k.focus({preventScroll:true});
    }catch(e){}
  }

  /* ---- SEKME İÇİ YOL GÖSTERİCİSİ ----
     Bir sekmede üç ya da daha çok kart varsa, en üste o kartların
     adlarından bir liste konur. Sebebi ölçüldü: Editör sekmesi 5531,
     Hesap sekmesi 3273 piksel uzunluğundaydı ve bir kartı bulmanın tek
     yolu kaydırmaktı. Liste kartların KENDİ başlıklarından kurulur;
     yeni bir kart eklendiğinde buraya bir şey yazmak gerekmez, ve
     yazılmadığı için de unutulmaz.

     Gizli kartlar listeye girmez: görünmeyen bir şeye yol göstermek,
     olmayan bir kapıyı işaret etmektir. Liste her sekme açılışında
     yeniden kurulur, çünkü kartların bir bölümü yetkiye göre sonradan
     görünür oluyor. */
  /* ---- SEKME İÇİ ÇİPLER: ARTIK KAYDIRMIYOR, DEĞİŞTİRİYOR ----
     Çipler önce birer YOL GÖSTERİCİsiydi: basınca karta kaydırıyordu.
     Kartın kendisi yerinde kalıyor, sekme boyu değişmiyordu. Editör
     sekmesi 4032 piksel, yani dört buçuk ekran; bir kartı bulmanın yolu
     hâlâ kaydırmaktı.

     Çip artık hangi kartın GÖRÜNECEĞİNİ seçiyor. Sekme, seçili kartın
     boyuna iner. Ödenen bedel şudur: bir kart görünürken ötekiler
     görünmez — bu yüzden çipin üstünde o kartın BEKLEYEN SAYISI durur.
     Sayı olmadan çip, arkasında iş biriken bir kapak olurdu.

     BETİKSİZ TARAYICIDA HİÇBİR ŞEY GİZLENMEZ: çip şeridi betikle
     kuruluyor ve kartları gizleyen sınıf da betikle konuyor. Betik
     yoksa bütün kartlar alt alta, eskisi gibi görünür.

     ÖZET SEKMESİ DIŞARIDADIR: orası bir pano, bir liste değil. İki
     sütunlu düzeni bir bakışta durumu göstermek için kuruldu; kartları
     tek tek göstermek onu panoluktan çıkarırdı. */
  var SEK_KART = {};      /* sekme -> seçili kart kimliği */
  var CIPSIZ = ['ozet'];  /* pano düzeni bozulmasın */

  function kartSay(k){
    /* Kartın bekleyen iş sayısı: başlığındaki uyarı rozetinden okunur.
       Ayrı bir sayaç yazılsaydı bir gün rozetten farklı bir sayı
       gösterirdi. */
    var r=k.querySelector('h2 .sek-say');
    if(r && !r.classList.contains('gizli')){
      var n=parseInt(r.textContent,10);
      if(n>0) return n;
    }
    return 0;
  }
  function kartAd(k){
    var h=k.querySelector('h2'); if(!h) return '';
    return (h.childNodes[0]&&h.childNodes[0].nodeValue||h.textContent||'').trim();
  }
  function kartSec(ad, id){
    var pnl=document.querySelector('[data-pnl="'+ad+'"]'); if(!pnl) return;
    SEK_KART[ad]=id;
    [].slice.call(pnl.querySelectorAll(':scope > .pn-kart')).forEach(function(k){
      var secili = k.id===id;
      k.classList.toggle('pn-kart-kapali', !secili);
      /* Seçili kart satırın tamamını alır. Izgarada ':only-child'
         kuralı vardı ama işe yaramıyor: kardeşler sayfadan
         kaldırılmıyor, yalnız gizleniyor — yani kart hâlâ tek çocuk
         değil. Ölçüldü: tek görünen kart 528 pikselde kalıyor ve sağı
         boş duruyordu. */
      k.classList.toggle('pn-kart-secili', secili);
    });
    [].slice.call(pnl.querySelectorAll(':scope > .pn-yol > button')).forEach(function(b){
      var a = b.dataset.kart===id;
      b.classList.toggle('acik', a);
      b.setAttribute('aria-selected', a?'true':'false');
    });
  }
  function yolKur(ad){
    var pnl=document.querySelector('[data-pnl="'+ad+'"]'); if(!pnl) return;
    var eski=pnl.querySelector(':scope > .pn-yol'); if(eski) eski.remove();
    if(CIPSIZ.indexOf(ad)>=0) return;
    var kart=[].slice.call(pnl.querySelectorAll(':scope > .pn-kart'))
      .filter(function(k){ return !k.classList.contains('gizli'); });
    if(kart.length<2){
      /* Tek kart varsa çip şeridi bir süs olur; kart da gizlenmemeli. */
      kart.forEach(function(k){ k.classList.remove('pn-kart-kapali'); });
      return;
    }
    var n=document.createElement('div');
    n.className='pn-yol';
    n.setAttribute('role','tablist');
    n.setAttribute('aria-label', EN?'In this tab':'Bu sekmede');
    kart.forEach(function(k,i){
      if(!k.id) k.id='pnk-'+ad+'-'+i;
      var b=document.createElement('button');
      b.type='button'; b.setAttribute('role','tab');
      b.dataset.kart=k.id;
      b.appendChild(document.createTextNode(kartAd(k)));
      var say=kartSay(k);
      if(say>0){
        var sp=document.createElement('span'); sp.className='sek-say sek-say-uyar';
        sp.textContent=say; b.appendChild(sp);
      }
      b.addEventListener('click',function(){
        /* Kapaklı kart seçildiğinde kapağı da açılır: seçilen ama
           kapalı duran bir kart, gösterilmemiş kart demektir. */
        if(k.tagName==='DETAILS') k.open=true;
        kartSec(ad, k.id);
      });
      n.appendChild(b);
    });
    pnl.insertBefore(n, pnl.firstElementChild.classList.contains('pn-bas2')
      ? pnl.firstElementChild.nextSibling : pnl.firstElementChild);
    /* Seçim korunur: sekmeler arasında gidip gelirken kişi bıraktığı
       yere döner. Yoksa BEKLEYEN İŞİ olan ilk kart seçilir — bir
       panelin açılışta göstereceği şey, en çok iş bekleyen yerdir. */
    var onceki = SEK_KART[ad];
    var varMi = onceki && kart.filter(function(k){ return k.id===onceki; }).length;
    var bekleyen = kart.filter(function(k){ return kartSay(k)>0; })[0];
    /* Bekleyen iş yoksa KAPAKLI OLMAYAN ilk kart seçilir. Ölçüldü:
       Hesabım sekmesinin ilk kartı "Doktora belgeniz" ve o bir kapak;
       kapalı geldiği için sekme 248 piksellik boş bir sayfa gibi
       açılıyordu. Kapaklı bir kart, tanımı gereği "şu an bakılacak bir
       şey yok" demektir; açılışta seçilecek kart o olamaz. */
    var acikKart = kart.filter(function(k){ return k.tagName!=='DETAILS'; })[0];
    kartSec(ad, varMi ? onceki
                      : (bekleyen ? bekleyen.id : (acikKart || kart[0]).id));
  }
  /* Bir karta doğrudan gitmek isteyen (sayı şeridi, deneme bağlantısı)
     bunu kullanır: sekmeyi açar, kartı seçer, oraya kaydırır. */
  function kartaGit(sek, id){
    sekAc(sek);
    var k=document.getElementById(id); if(!k) return;
    if(k.tagName==='DETAILS') k.open=true;
    kartSec(sek, id);
    k.scrollIntoView({behavior:'smooth', block:'start'});
  }
  /* Ölçüt [data-sek]'tir, salt '.pn-sk' değil: aynı sınıfı İleti
     süzgeci düğmeleri de taşıyor ve onların data-sek'i yok. Eskisi
     "Bekleyen" süzgecine basıldığında sekAc(undefined) çağırıyor,
     yani süzgeci deneyen kişiyi Özet sekmesine atıyordu. */
  document.querySelectorAll('.pn-sk[data-sek]').forEach(function(b){
    b.addEventListener('click',function(){ sekAc(b.dataset.sek); });
  });
  /* PANELDEYKEN GELEN BİLDİRİM DE ÇALIŞIR. Aynı sayfaya giden bir
     bağlantıya basıldığında tarayıcı sayfayı yeniden yüklemez, yalnız
     adresi değiştirir; dinleyici olmasaydı bildirim, zaten panelde olan
     kişi için hiçbir şey yapmazdı. */
  window.addEventListener('hashchange', function(){ adrestenGit(location.hash); });
  /* Dinleyici tek tek düğmelere değil BELGEYE bağlanır: sayı şeridinin
     kutuları her sayaç yazımında yeniden çiziliyor ve o anda bağlanmış
     olan dinleyici düğmeyle birlikte yok oluyor. */
  document.addEventListener('click', function(ev){
    var b = ev.target.closest ? ev.target.closest('[data-sek-git]') : null;
    if(!b) return;
    sekAc(b.dataset.sekGit);
    var ust = document.querySelector('.pn-bas');
    if(ust) ust.scrollIntoView({behavior:'smooth', block:'start'});
  });

  /* Deneme düzenine git: sekmeyi açar, kapağı açar ve karta kaydırır.
     Üçü birden yapılmazsa bağlantı, kişiyi doğru sekmede yanlış yere
     bırakır — kart hâlâ kapalı ve hâlâ 2752 piksel aşağıda olurdu. */
  if(\$('dgDenemeGit')) \$('dgDenemeGit').addEventListener('click', function(){
    var k=\$('kartDeneme'); if(!k) return;
    kartaGit('yonetim', k.id);
    /* Kısa bir vurgu: uzun bir sekmenin dibine inen göz, indiği yeri
       aramak zorunda kalmasın. */
    k.classList.add('pn-vurgula');
    setTimeout(function(){ k.classList.remove('pn-vurgula'); }, 2200);
  });

  /* ---- Gösterge: bekleyenler, çalışmalar, nabız, geçmiş ---- */
  function gostergeYukle(){
    fetch('/api/panel/ozet',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
      if(!d||!d.ok) return;
      /* 1. Bekleyenler */
      var b=\$('bekleyenListe');
      if(!d.bekleyen.length){
        b.innerHTML='<p class="pn-ack">'+(EN?'Nothing is waiting for you right now.':'Şu anda sizi bekleyen bir iş yok.')+'</p>';
        \$('kartBekleyen').classList.remove('pn-bekleyen-var');
      } else {
        \$('kartBekleyen').classList.add('pn-bekleyen-var');
        b.innerHTML=d.bekleyen.map(function(x){
          var ic='<b>'+esc(x.baslik)+'</b><span>'+esc(x.aciklama)+'</span>';
          return x.yol ? '<a class="pn-is" href="'+esc(x.yol)+'">'+ic+'</a>' : '<div class="pn-is">'+ic+'</div>';
        }).join('');
      }
      /* 2. Nabız */
      var SIM={
        calisma:'<path d="M4 4h11l5 5v11a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M14 4v6h6"/>',
        hakemli:'<path d="M20 6 9 17l-5-5"/>',
        onayli:'<circle cx="12" cy="12" r="9"/><path d="M8.5 12.2l2.4 2.4 4.6-4.8"/>',
        hakem:'<circle cx="9" cy="8" r="3.4"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><path d="M17 5.5a3.4 3.4 0 0 1 0 6"/><path d="M18.5 14.2A6.5 6.5 0 0 1 21.5 20"/>',
        okuma:'<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
        serh:'<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4L3 21l1.1-4.6A8.4 8.4 0 1 1 21 11.5z"/>',
        oylama:'<path d="M12 3v6"/><path d="M5 9h14l2 11H3z"/><path d="M9 14h6"/>',
        aranan:'<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4.2-4.2"/>'
      };
      function sim(k){
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
             + 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+(SIM[k]||'')+'</svg>';
      }
      var s=d.sayac, N=[
        [s.calisma, EN?'works':'çalışma', false, 'calisma'],
        [s.hakemli, EN?'peer reviewed':'hakemli', false, 'hakemli'],
        [s.onayli, EN?'reviewer approved':'hakem onaylı', false, 'onayli'],
        [s.hakem, EN?'reviewers':'hakem', false, 'hakem'],
        [s.okuma, EN?'reads':'okuma', false, 'okuma'],
        [s.serh, EN?'notes':'şerh', false, 'serh'],
        [s.acik_oylama, EN?'open votes':'açık oylama', true, 'oylama'],
        [s.hakem_aranan, EN?'seeking reviewers':'hakem aranan', true, 'aranan']
      ];
      \$('pnSayac').innerHTML=N.map(function(n){
        var v=(n[2]&&n[0]>0)?' vurgu':'';
        return '<div class="pn-sayi'+v+'"><i class="pn-sim">'+sim(n[3])+'</i>'
             + '<div><b>'+n[0]+'</b><span>'+n[1]+'</span></div></div>';
      }).join('');
      /* 3. Çalışmalarım */
      var c=\$('calismaListe'), oc=\$('ozetCalisma');
      if(!d.calismalar.length){
        /* Boş hâlin cümlesi de ölçütü söyler: liste yazarlığa göre
           kurulur, gönderime göre değil. */
        var bos='<p class="pn-ack">'+(EN?'You are not listed as an author of any work yet.':'Henüz hiçbir çalışmada yazar olarak görünmüyorsunuz.')
          +' <a href="/basvuru.php">'+(EN?'Submit one':'Bir tane gönderin')+'</a></p>';
        c.innerHTML=bos; if(oc) oc.innerHTML=bos;
      } else {
        var kart=function(w){
          var rz=[];
          /* ROZET AŞAMADAN GELİR, 'tur'dan DEĞİL. Eskiden burada
             w.tur==='hakemli' ? 'Hakemli' : 'Hakemsiz' yazıyordu; tek
             raporu olmayan çalışmaya "Hakemli", iki hakemin reddettiği
             çalışmaya "Hakemsiz" diyordu. Metin sunucudan, makale
             sayfasıyla aynı kaynaktan gelir. */
          rz.push('<span class="pn-rz-k">'+esc(EN?(w.asama_ad_en||''):(w.asama_ad||''))+'</span>');
          if(w.onayli) rz.push('<span class="pn-rz-k iyi">'+S.onayliRz+'</span>');
          else if(w.tur==='hakemli') rz.push('<span class="pn-rz-k">'+w.kabul+'/'+w.esik+' '+(EN?'positive':'olumlu')+'</span>');
          /* Aşama rozeti hakem arandığını zaten söylüyorsa ikinci kez
             yazılmaz: aynı cümlenin iki rozeti bilgi eklemez. Aşama
             adının kendisi burada YAZILMAZ; kapı da bunu ölçüyor. */
          if(w.araniyor && w.asama!=='aranan') rz.push('<span class="pn-rz-k uyar">'+S.araniyor+'</span>');
          if(w.acik_oylama) rz.push('<span class="pn-rz-k uyar">'+w.acik_oylama+' '+(EN?'open vote':'açık oylama')+'</span>');
          if(w.kilit==='revizyon') rz.push('<span class="pn-rz-k uyar">'+(EN?'Revision requested':'Revizyon istendi')+'</span>');
          if(w.kilit==='kilitli') rz.push('<span class="pn-rz-k">'+(EN?'Text locked':'Metin kilitli')+'</span>');
          if(w.kurulus) rz.push('<span class="pn-rz-k">'+(EN?'Founding period':'Kuruluş dönemi')+'</span>');
          var h='<div class="pn-cal">'
               + '<a class="pn-is pn-isim" href="'+esc(w.yol)+'"><b>'+esc(w.baslik)+'</b>'
               + '<span class="pn-rz-sat">'+rz.join('')+'</span>'
               + '<span>'+esc(w.tarih)+' &middot; '+w.rapor+' '+(EN?'report':'rapor')+' &middot; '+w.okuma+' '+(EN?'reads':'okuma')+' &middot; '+w.serh+' '+(EN?'notes':'şerh')+'</span></a>';
          /* Düzenleme, e-postayla gelen bağlantı beklemeden buradan
             açılır: hesabıyla giren yazarın yetkisi zaten vardır. */
          /* BAĞIN ADI NE YAPTIĞINI SÖYLER. "Çalışmayı düzenle" yazıyordu
             ve bildirilen kusur şuydu: "buradan kişiler çalışmalarını
             nasıl göndereceği belli değil." Bağ oradaydı ama adı, tam
             metnin YAZILDIĞI yer olduğunu söylemiyordu; yazar da Word
             belgesini nereye koyacağını arıyordu. */
          if(w.duzenle && w.kilit!=='kilitli')
            h+='<a class="pn-duzenle" href="'+esc(w.duzenle)+'">'
              +(EN?'Write / edit the full text':'Tam metni yaz ya da düzenle')+'</a>';
          /* HAKEMLİĞE AÇMA. Hakem havuzu gönüllülükle büyür: bir çalışma
             hakemli olarak işaretlenmedikçe "hakem aranan çalışmalar"
             listesine düşmez, düşmedikçe kimse gönüllü olamaz. Yazarın
             kendi çalışmasını o listeye koyabilmesi bu yüzden gerekli. */
          if(w.acilabilir)
            h+='<button type="button" class="pn-duzenle pn-hkm-ac" data-hkm="'+esc(w.anahtar)+'">'
              +(EN?'Open this work to reviewers':'Bu çalışmayı hakemliğe aç')+'</button>';
          else if(w.kapatilabilir)
            h+='<button type="button" class="pn-duzenle pn-hkm-kapat" data-hkm="'+esc(w.anahtar)+'">'
              +(EN?'Withdraw from reviewing':'Hakemlikten geri al')+'</button>';
          return h+'</div>';
        };
        c.innerHTML=d.calismalar.map(kart).join('');
        if(oc) oc.innerHTML=d.calismalar.slice(0,3).map(kart).join('');
      }
      /* 4. Geçmiş */
      var gm=d.gecmis, gh='';
      gh+='<div class="pn-ilerle"><b>'+gm.oy+' / '+gm.oy_esik+'</b> <span>'
        + (EN?'valid votes cast. Four votes earn the right to submit as an author.'
             :'geçerli oy kullandınız. Dört oy yazarlık hakkını kazandırır.')+'</span>';
      if(gm.yazar) gh+='<span class="pn-rz-k iyi">'+(EN?'Authorship earned':'Yazarlık kazanıldı')+'</span>';
      else if(gm.oy_kalan>0) gh+='<span class="pn-rz-k">'+gm.oy_kalan+' '+(EN?'votes to go':'oy kaldı')+'</span>';
      gh+='</div>';
      if(!gm.hakemlik.length){
        gh+='<p class="pn-ack">'+(EN?'You have not assessed a work yet. Works seeking reviewers are listed on their own page.'
                                    :'Henüz bir çalışma değerlendirmediniz. Hakem aranan çalışmalar kendi sayfasında listelenir.')
          +' <a href="/bekleyen.php">'+(EN?'See them':'Onlara bak')+'</a></p>';
      } else {
        gh+=gm.hakemlik.map(function(x){
          var kr=x.rapor_var?(x.karar||'-'):(EN?'report awaited':'rapor bekleniyor');
          return '<a class="pn-is" href="'+esc(x.yol)+'"><b>'+esc(x.calisma)+'</b><span>'+esc(kr)
               + (x.rapor_var&&!x.nitelik?' &middot; '+(EN?'below the quality threshold':'nitelik eşiğinin altında'):'')+'</span></a>';
        }).join('');
      }
      \$('gecmisAlan').innerHTML=gh;

      /* Rozetler: hangi sekmede kaç iş bekliyor. Üçüncü değişken
         sayının ADIdır ve üstteki şeride de o yazılır. */
      var nBek=d.bekleyen.length;
      roz('rozOzet', nBek,
          EN?(nBek===1?'task is waiting for you':'tasks are waiting for you'):'iş sizi bekliyor', 'ozet');
      var nCal=d.calismalar.filter(function(w){ return w.kilit==='revizyon'||w.acik_oylama>0; }).length;
      roz('rozCalisma', nCal,
          EN?(nCal===1?'work of yours needs an answer':'works of yours need an answer'):'çalışmanız yanıt bekliyor', 'calismalar');
      roz('rozHesap', (d.dogrulama && d.dogrulama.durum!=='onayli') ? 1 : 0,
          EN?'step of your account is unfinished':'hesap adımınız tamamlanmadı', 'hesap');
      if(d.editor){
        edBekleyen=d.bekleyen.filter(function(x){ return x.tur==='belge'||x.tur==='gonullu'||x.tur==='atama'; }).length;
        rozEditorCiz();
      }

      /* Sağ üstteki daire de tazelenir: panel listesi ile rozetin aynı
         anda güncellenmesi, "işi yaptım ama bildirim duruyor" hâlini
         ortadan kaldırır. Sayı sunucuda aynı işlevden geliyor
         (bekleyen_isler), yani ikisi ayrı şey söyleyemez. */
      if (window.kutadguHesapTazele) window.kutadguHesapTazele();

      /* Adreste sekme (ve varsa kart) yazıyorsa oraya git */
      adrestenGit(location.hash);
    }).catch(function(){});
  }

  /* ---- Çalışmayı hakemliğe açma ve geri alma ----
     Dinleyici tek tek düğmelere değil kaba bağlanır: çalışma listesi her
     yüklenişte yeniden çiziliyor ve düğmeler o anda yok oluyor. */
  document.addEventListener('click', function(ev){
    var d = ev.target.closest ? ev.target.closest('.pn-hkm-ac, .pn-hkm-kapat') : null;
    if(!d) return;
    var kapat = d.classList.contains('pn-hkm-kapat');
    var anah = d.dataset.hkm || '';
    if(!anah) return;
    if(kapat && !confirm(EN
      ? 'Withdraw this work from reviewing? You can open it again at any time.'
      : 'Bu çalışma hakemlikten geri alınsın mı? Dilediğiniz zaman yeniden açabilirsiniz.')) return;
    d.disabled = true;
    var eskiMetin = d.textContent;
    d.textContent = EN ? 'Working...' : 'Bekleyin...';
    api('/yazar-hakemlige-ac', kapat ? {y:anah, kapat:1} : {y:anah}).then(function(r){
      if(r && r.ok){ gostergeYukle(); }
      else { d.disabled=false; d.textContent=eskiMetin; alert((r&&r.hata)||S.baglanti); }
    }).catch(function(){ d.disabled=false; d.textContent=eskiMetin; alert(S.baglanti); });
  });

  /* ---- Listem: beğenilen çalışmalar ve son bakıştan bu yana değişenler ---- */
  function listemYukle(){
    var alan=\$('listemAlan'); if(!alan) return;
    fetch('/api/begenilerim'+(EN?'?lang=en':''),{credentials:'same-origin'})
      .then(function(r){return r.json();}).then(function(d){
      if(!d||!d.ok) return;
      if(!d.liste.length){
        alan.innerHTML='<p class="pn-ack">'+(EN
          ? 'Your list is empty. On any work’s page there is an “Add to my list” button; the works you add appear here.'
          : 'Listeniz boş. Her çalışmanın sayfasında “Listeme ekle” düğmesi vardır; ekledikleriniz burada görünür.')+'</p>';
        roz('rozListem',0,EN?'work in your list has changed':'çalışmanızda değişiklik var','listem'); return;
      }
      var h='';
      d.liste.forEach(function(x){
        var et=[];
        if(x.onayli) et.push(S.onayliRz);
        /* Etiket aşamadan gelir; gerekçe yukarıda yazılı. */
        if(x.asama_ad) et.push(x.asama_ad);
        h+='<div class="pn-lst'+(x.degisiklik.length?' pn-lst-yeni':'')+'">'
         + '<a class="pn-is" href="'+esc(x.yol)+'"><b>'+esc(x.baslik)+'</b>'
         + '<span>'+esc(x.yazar)+(et.length?' &middot; '+esc(et.join(' · ')):'')+'</span></a>';
        if(x.degisiklik.length){
          h+='<ul class="pn-lst-fark">';
          x.degisiklik.forEach(function(f){ h+='<li>'+esc(f)+'</li>'; });
          h+='</ul>';
        }
        h+='<button type="button" class="d d-ikinci d-kucuk pn-lst-cik" data-cikar="'+esc(x.id)+'">'
         + (EN?'Remove from list':'Listemden çıkar')+'</button></div>';
      });
      if(d.yeni>0){
        h='<div class="pn-lst-ust"><span>'+(EN
            ? d.yeni+' work(s) in your list have changed since you last looked.'
            : 'Listenizdeki '+d.yeni+' çalışmada, son bakışınızdan bu yana değişiklik oldu.')
          +'</span><button type="button" id="listemOkundu">'
          +(EN?'Mark all as read':'Hepsini okundu say')+'</button></div>'+h;
      }
      alan.innerHTML=h;
      roz('rozListem', d.yeni,
          EN?(d.yeni===1?'work in your list has changed':'works in your list have changed'):'listenizdeki çalışma değişti', 'listem');

      var ok=\$('listemOkundu');
      if(ok) ok.addEventListener('click',function(){
        ok.disabled=true;
        api('/begeni-gorundu',{}).then(function(){
          try{ sessionStorage.removeItem('kutadgu-hesap-durum'); }catch(e){}
          listemYukle();
        }).catch(function(){ ok.disabled=false; });
      });
      alan.querySelectorAll('[data-cikar]').forEach(function(b){
        b.addEventListener('click',function(){
          b.disabled=true;
          api('/begeni',{id:b.getAttribute('data-cikar'),durum:false}).then(function(){
            try{ sessionStorage.removeItem('kutadgu-hesap-durum'); }catch(e){}
            listemYukle();
          }).catch(function(){ b.disabled=false; });
        });
      });
    }).catch(function(){});
  }

  /* Eksikler: fotoğraf ve alan. İkisi de zorunlu değildir; ikisi de
     sistemin başka bir yerini çalıştırır. Fotoğraf, adın yanında
     duracak yüzdür; alan, hakem havuzunda bulunmayı sağlar. */
  function eksikleriGoster(h){
    var kutu=\$('pnEksik'), liste=\$('pnEksikListe');
    if(!kutu||!liste) return;
    var e=[];
    if(!h.resim) e.push(EN
      ? 'You have not added a profile picture. Wherever your name appears, your initials are shown instead. You can add one under Account; if you would rather not show your face, a mark drawn from our own identity is used.'
      : 'Profil fotoğrafınız yok. Adınızın geçtiği her yerde baş harfleriniz görünüyor. Hesap sekmesinden ekleyebilirsiniz; yüzünüzün görünmesini istemiyorsanız kurumsal kimliğimizden türetilmiş bir işaret kullanılır.');
    if(!((h.alanlar||h.alan||[]).length)) e.push(EN
      ? 'You have not chosen your fields. Editors look at these codes when they search for a reviewer, so without them you are not found.'
      : 'Bilim alanlarınızı seçmemişsiniz. Editörler hakem ararken bu kodlara bakar; seçmezseniz aramalarda bulunmazsınız.');
    /* ORCID DOĞRULAMASI DA BİR EKSİKTİR — ayrı bir afiş değil.
       Sisteme "şunu da yapın" diye yeni bir kutu eklemek kolaydı; ama
       kişiye eksiklerini söyleyen bir yer zaten var ve iki ayrı yerden
       seslenmek, ikisinin de duyulmamasıyla biter. Eksik neredeyse,
       yeni eksik de oraya yazılır. */
    if(ORCID_ACIK && !h.orcid_dogru) e.push(EN
      ? (h.orcid
         ? 'Your ORCID is typed in by hand and not verified. A number anyone can look up is not an identity: verify it with your ORCID account so your name on published work stands on something.'
         : 'You have not added an ORCID. It is the identifier that keeps your name attached to your work across every system; you can verify it with your ORCID account in one step.')
      : (h.orcid
         ? 'ORCID numaranız elle yazılmış, doğrulanmamış. Herkesin bakıp öğrenebileceği bir numara kimlik değildir: ORCID hesabınızla doğrulayın ki yayımlanan çalışmanızdaki adınız bir şeye dayansın.'
         : 'ORCID numaranız yok. Adınızı çalışmanıza her sistemde bağlı tutan kimlik odur; ORCID hesabınızla tek adımda doğrulayabilirsiniz.'));
    if(!e.length){ kutu.classList.add('gizli'); return; }
    liste.innerHTML='';
    e.forEach(function(t){ var li=document.createElement('li'); li.textContent=t; liste.appendChild(li); });
    var git=document.createElement('li');
    var a=document.createElement('a'); a.href='#hesap'; a.textContent=EN?'Go to the account tab':'Hesap sekmesine git';
    a.addEventListener('click',function(ev){ ev.preventDefault(); sekAc('hesap'); });
    git.appendChild(a); liste.appendChild(git);
    kutu.classList.remove('gizli');
  }

  /* ---- ROZET VE SAYI ŞERİDİ: TEK ÇAĞRI, İKİ YÜZ ----
     Bildirilen kusur: sekmede "Editör 2" yazıyor ama iki'nin ne olduğu
     hiçbir yerde yazmıyor. Çıplak sayı, sayıldığını söyler; ne
     sayıldığını söylemez.

     roz() artık üç şey birden yapar:
       1. rozete sayıyı yazar (yer dar; orada yalnız sayı sığar),
       2. sekme DÜĞMESİNİN erişilebilir adına ve ipucuna sayının adını
          yazar — böylece ekran okuyucu "Editör, 2 editörlük işi
          bekliyor" der, fare de üstüne gelince aynısını gösterir,
       3. sayıyı üstteki şeride, adıyla birlikte kaydeder.

     ÜÇÜ AYRI ÇAĞRI OLSAYDI bir gün biri ötekinden farklı bir sayı
     gösterirdi; sayaç yazmanın en sessiz kusuru budur. Tek çağrı
     olduğu için ayrışamazlar.

     Şerit her yazımda yeniden çizilir ve sırası SABİTtir: yükleyiciler
     birbirinden bağımsız zamanlarda dönüyor, sıra çağrı sırasına
     bırakılsaydı kutular her yenilemede yer değiştirirdi. */
  /* SERIT defteri ve seritCiz() kaldırıldı: çizdikleri şerit de
     kaldırıldı (gerekçe yukarıda, pnSerit'in durduğu yerde). roz()
     artık yalnız ait olduğu sekmenin rozetini yazar. */
  /* Rozet yazıcısı bütün yükleyiciler tarafından kullanılır.
     ad : sayının NE olduğu ("ileti yanıt bekliyor")
     sek: basıldığında gidilecek sekme */
  function roz(id,n,ad,sek){
    n = n||0;
    var e=\$(id);
    if(e){
      if(n>0){ e.textContent=n; e.classList.remove('gizli'); }
      else { e.textContent=''; e.classList.add('gizli'); }
      /* Erişilebilir ad rozete değil DÜĞMEYE yazılır: bir düğmenin
         içindeki span'ın aria-label'ı okunmaz, düğmenin kendi adı
         okunur. Rozete yazmak, yazdığını sanmak olurdu. */
      var dg=e.closest?e.closest('.pn-sk'):null;
      if(dg){
        var temel=(dg.dataset.ad||'');
        if(!temel){ temel=(dg.childNodes[0]&&dg.childNodes[0].nodeValue||dg.textContent||'').trim(); dg.dataset.ad=temel; }
        if(n>0 && ad){ dg.setAttribute('aria-label', temel+', '+n+' '+ad); dg.title=n+' '+ad; }
        else { dg.removeAttribute('aria-label'); dg.removeAttribute('title'); }
      }
    }
  }

  /* ---- Profil resmi ---- */
  function yuzCiz(h){
    var e=\$('pnYuz'); if(!e) return;
    if(h && h.resim){ e.innerHTML=''; var im=new Image(); im.src=h.resim; im.alt=''; e.appendChild(im);
      \$('pnResimSil').classList.remove('gizli'); }
    else { e.textContent=harf((h&&h.ad)||''); \$('pnResimSil').classList.add('gizli'); }
  }
  var pnDosya=\$('pnResimDosya');
  if(pnDosya) pnDosya.addEventListener('change',function(){
    var f=pnDosya.files&&pnDosya.files[0]; if(!f) return;
    var m=\$('pnResimMsj'); msj(m,S.bekle);
    var fd=new FormData(); fd.append('dosya',f);
    fetch('/api/hesap/resim',{method:'POST',credentials:'same-origin',body:fd})
      .then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok){ msj(m,d.mesaj||S.kaydedildi,'ok');
          var e=\$('pnYuz'); e.innerHTML=''; var im=new Image(); im.src=d.resim; im.alt=''; e.appendChild(im);
          \$('pnResimSil').classList.remove('gizli');
          try{ sessionStorage.removeItem('kutadgu-hesap-durum'); }catch(x){}
        } else msj(m,(d&&d.hata)||S.baglanti,'err');
        pnDosya.value='';
      }).catch(function(){ msj(m,S.baglanti,'err'); pnDosya.value=''; });
  });
  var pnSil=\$('pnResimSil');
  if(pnSil) pnSil.addEventListener('click',function(){
    var m=\$('pnResimMsj'); msj(m,S.bekle);
    api('/hesap/resim-sil',{}).then(function(d){
      if(d&&d.ok){ msj(m,d.mesaj||S.kaydedildi,'ok');
        \$('pnYuz').textContent=harf(\$('pAd').value||'');
        pnSil.classList.add('gizli');
        try{ sessionStorage.removeItem('kutadgu-hesap-durum'); }catch(x){}
      } else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });

  /* ---- Bağlantı satırları, üyelikler ve tanıtım ----
     Satırlar burada kurulur ve burada okunur. Sunucu her değeri yeniden
     denetler; buradaki sınırlar yalnızca kişiye anında geri bildirim
     vermek içindir, güvenlik değildir. */
  var BAG_ENSOK = 10;
  function bagSatir(url, ad){
    var d=document.createElement('div'); d.className='pn-bag-s';
    var u=document.createElement('input'); u.type='text'; u.className='pn-bag-url';
    u.placeholder='https://...'; u.value=url||'';
    var e=document.createElement('input'); e.type='text'; e.className='pn-bag-ad';
    e.placeholder=EN?'Label (optional)':'Etiket (isteğe bağlı)'; e.maxLength=60; e.value=ad||'';
    var s=document.createElement('button'); s.type='button'; s.className='pn-bag-sil';
    s.textContent=EN?'Remove':'Kaldır';
    s.setAttribute('aria-label',(EN?'Remove this link':'Bu bağlantıyı kaldır'));
    s.addEventListener('click',function(){ d.remove(); bagDurum(); });
    d.appendChild(u); d.appendChild(e); d.appendChild(s);
    return d;
  }
  function bagDurum(){
    var n=\$('pBaglar').children.length;
    var b=\$('pBagEkle'); if(b) b.disabled = n >= BAG_ENSOK;
  }
  function baglarYaz(liste){
    var k=\$('pBaglar'); k.innerHTML='';
    (liste||[]).slice(0,BAG_ENSOK).forEach(function(b){
      k.appendChild(bagSatir(b&&b.url||'', b&&b.ad||''));
    });
    bagDurum();
  }
  function baglarOku(){
    var out=[];
    Array.prototype.forEach.call(\$('pBaglar').children,function(d){
      var u=d.querySelector('.pn-bag-url'), a=d.querySelector('.pn-bag-ad');
      var url=(u&&u.value||'').trim();
      if(url) out.push({url:url, ad:(a&&a.value||'').trim()});
    });
    return out.slice(0,BAG_ENSOK);
  }
  function tanitimSay(){
    var t=\$('pTanitim'), s=\$('pTanitimSay'); if(!t||!s) return;
    var n=t.value.length; s.textContent=n+' / 600';
    s.classList.toggle('dolu', n >= 600);
  }
  if(\$('pTanitim')) \$('pTanitim').addEventListener('input',tanitimSay);
  if(\$('pBagEkle')) \$('pBagEkle').addEventListener('click',function(){
    if(\$('pBaglar').children.length >= BAG_ENSOK) return;
    var s=bagSatir('',''); \$('pBaglar').appendChild(s);
    var u=s.querySelector('.pn-bag-url'); if(u) u.focus();
    bagDurum();
  });

  /* ---- Profil ---- */
  \$('dgProfil').addEventListener('click',function(){
    var alan=(window.alSecOku?window.alSecOku('pAlan'):[]);
    var o=\$('pOrcid').value.trim();
    var uy=\$('pUyelik').value.split('\\n').map(function(x){return x.trim();})
             .filter(function(x){return x!=='';}).slice(0,12);
    msj(\$('profilMsj'),S.bekle);
    api('/hesap/guncelle',{ad:\$('pAd').value.trim(),unvan:\$('pUnvan').value,kurum:\$('pKurum').value.trim(),
      orcid:o?orcidTemiz(o):'',scopus:\$('pScopus').value.trim(),alanlar:alan,
      tanitim:\$('pTanitim').value,baglantilar:baglarOku(),uyelikler:uy,
      telefon:\$('pTelefon')?\$('pTelefon').value.trim():'',
      gorunurluk:gorunurlukOku()}).then(function(d){
      if(d&&d.ok){
        api('/hesap/dizin',{gizli:\$('pDizin')?\$('pDizin').checked:false}).catch(function(){});
        msj(\$('profilMsj'),S.kaydedildi,'ok'); panelDoldur(d.hesap);
      }
      else msj(\$('profilMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('profilMsj'),S.baglanti,'err'); });
  });

  /* ---- İletişim görünürlüğü ----
     Seçim kişinindir; panel yalnızca taşır. Sunucu da bu üç değerden
     başkasını kabul etmez. */
  function gorunurlukYaz(g){
    document.querySelectorAll('#pGorunurluk select[data-gor]').forEach(function(sel){
      var d = g[sel.getAttribute('data-gor')];
      sel.value = (d==='acik'||d==='uyeler'||d==='gizli') ? d : sel.value;
    });
  }
  function gorunurlukOku(){
    var o = {};
    document.querySelectorAll('#pGorunurluk select[data-gor]').forEach(function(sel){
      o[sel.getAttribute('data-gor')] = sel.value;
    });
    return o;
  }

  /* ---- Parola ---- */
  \$('dgParola').addEventListener('click',function(){
    msj(\$('parolaMsj'),S.bekle);
    api('/hesap/parola',{eski:\$('sEski').value,yeni:\$('sYeni').value}).then(function(d){
      if(d&&d.ok){ msj(\$('parolaMsj'),S.kaydedildi,'ok'); \$('sEski').value=''; \$('sYeni').value=''; }
      else msj(\$('parolaMsj'),(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(\$('parolaMsj'),S.baglanti,'err'); });
  });

  /* ---- Sunucu durumu ----
     Yalnızca okur. Sunucunun posta yolu ve yazma izinleri burada
     görünür; bir arıza kullanıcıya yansımadan önce fark edilsin diye. */
  function durumIsaret(k){ return k ? '<span class="rz rz-yes">' + (EN?'yes':'var') + '</span>'
                                    : '<span class="rz rz-kut">' + (EN?'no':'yok') + '</span>'; }
  function durumDizin(d){
    if(!d) return durumIsaret(false);
    if(!d.yazilabilir) return '<span class="rz rz-kut">' + (EN?'not writable':'yazılamıyor') + '</span>';
    return '<span class="rz rz-yes">' + (EN?'writable':'yazılabilir') + '</span>' +
           (d.var ? '' : ' <span style="opacity:.7;font-size:var(--y-2)">' +
             (EN?'(opens on first use)':'(ilk kullanımda açılır)') + '</span>');
  }
  function durumCiz(d){
    var kutu = \$('durumIc'); if(!kutu) return;
    if(!d){ kutu.innerHTML=''; return; }
    var p = d.php||{}, z = d.dizinler||{}, dk = d.disk||{}, e = d.eposta||{};
    var h = '';
    h += '<h3 style="margin:0 0 8px">' + (EN?'Mail path':'Posta yolu') + '</h3>';
    /* Hangi yolun açık olduğu, kaç iletinin gittiğinden ÖNCE gelir:
       aktarıcı yoksa aşağıdaki sayılar zaten yanıltıcıdır, çünkü mail()
       "aldım" der ve ileti yolda düşürülür. */
    h += '<div class="pn-rz">' + (EN?'relay (SMTP)':'aktarıcı (SMTP)') + ' ' + durumIsaret(e.aktarici) +
         (e.aktarici && e.sunucu ? ' <span style="opacity:.75">' + esc(e.sunucu) + '</span>' : '') + '</div>';
    h += '<div class="pn-rz">' + (EN?'mail() function':'mail() işlevi') + ' ' + durumIsaret(p.mail_var) + '</div>';
    if(!e.aktarici){
      h += '<p class="pn-ack" style="margin-top:6px">' + (EN
        ? 'No relay is set, so the server\'s own mail path is used. This server has no mail identity (SPF, DKIM, PTR), so invitations and password resets are very likely to be dropped or filed as junk. Set a relay below.'
        : 'Aktarıcı yazılı değil; sunucunun kendi posta yolu kullanılıyor. Bu sunucunun posta kimliği (SPF, DKIM, PTR) yok, bu yüzden davet ve parola yenileme iletileri büyük olasılıkla düşürülür ya da gereksiz posta klasörüne düşer. Aşağıdan bir aktarıcı yazın.') + '</p>';
    }
    h += '<p class="pn-ack" style="margin-top:6px">' +
         (EN ? 'Sent so far: ' : 'Bugüne kadar gönderilen: ') + esc(e.gonderildi||0) + ' | ' +
         (EN ? 'failed: ' : 'başarısız: ') + esc(e.basarisiz||0) + '</p>';
    var son = e.son||[];
    if(son.length){
      h += '<div class="pn-liste" style="margin-top:8px">';
      son.forEach(function(s){
        h += '<div class="pn-sat"><span class="rz ' + (s.durum==='GONDERILDI'?'rz-yes':'rz-kut') + '" style="flex:0 0 auto">' +
             esc(s.durum==='GONDERILDI'?(EN?'sent':'gitti'):(EN?'failed':'gitmedi')) + '</span>' +
             '<span style="opacity:.75;flex:0 0 auto">' + esc((s.zaman||'').replace('T',' ').slice(0,16)) + '</span>' +
             '<span style="flex:0 0 auto">' + esc(s.alici) + '</span>' +
             '<span style="opacity:.75;flex:1 1 auto;min-width:0">' + esc(s.konu) +
             /* Gerekçe satırda durur. "Gitmedi" tek başına bir tanı
                değildir; sunucunun kendi cümlesi ise doğrudan çözümdür. */
             (s.neden ? '<br><small style="opacity:.85">' + esc((s.yol?s.yol+': ':'') + s.neden) + '</small>' : '') +
             '</span></div>';
      });
      h += '</div>';
    } else {
      h += '<p class="pn-ack">' + (EN?'No message has been sent yet.':'Henüz hiç ileti gönderilmemiş.') + '</p>';
    }
    h += '<h3 style="margin:22px 0 8px">' + (EN?'Write permissions':'Yazma izinleri') + '</h3>';
    h += '<div class="pn-rz">' + (EN?'data directory':'veri dizini') + ' ' + durumDizin(z.veri) + '</div>';
    h += '<div class="pn-rz">' + (EN?'profile pictures':'profil resimleri') + ' ' + durumDizin(z.resim) + '</div>';
    h += '<div class="pn-rz">' + (EN?'site pictures':'site resimleri') + ' ' + durumDizin(z.site_resim) + '</div>';
    if(dk.serbest_mb!=null){
      h += '<p class="pn-ack" style="margin-top:6px">' + (EN?'Free disk: ':'Boş alan: ') +
           esc(dk.serbest_mb) + ' MB' + (dk.toplam_mb!=null ? ' / ' + esc(dk.toplam_mb) + ' MB' : '') + '</p>';
    }
    h += '<h3 style="margin:22px 0 8px">' + (EN?'Server support':'Sunucu desteği') + '</h3>';
    h += '<div class="pn-rz">PHP ' + esc(p.surum||'') + '</div>';
    h += '<div class="pn-rz">' + (EN?'image processing (GD)':'resim işleme (GD)') + ' ' + durumIsaret(p.gd_var) + '</div>';
    h += '<div class="pn-rz">WebP ' + durumIsaret(p.webp_var) + '</div>';
    h += '<div class="pn-rz">cURL ' + durumIsaret(p.curl_var) + '</div>';
    h += '<div class="pn-rz">' + (EN?'date formatting (intl)':'tarih biçimleme (intl)') + ' ' + durumIsaret(p.intl_var) + '</div>';
    h += '<p class="pn-ack" style="margin-top:6px">' + (EN?'Upload limit: ':'Yükleme sınırı: ') +
         esc(p.yukleme_boyu||'') + ' | ' + (EN?'memory: ':'bellek: ') + esc(p.bellek||'') + '</p>';
    kutu.innerHTML = h;
  }
  function durumYukle(){
    var m = \$('durumMsj'); if(m) msj(m, S.bekle);
    return api('/yonetim/sunucu-durum').then(function(d){
      if(d&&d.ok){ durumCiz(d.durum); if(m) msj(m,'',''); }
      else if(m) msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ if(m) msj(m,S.baglanti,'err'); });
  }
  /* ---- İzleme ----
     Yalnızca okur. Üç liste: son görülenler, hatalar, bildirim kütüğü. */
  function izSure(dk){
    dk = parseInt(dk||0, 10);
    if(dk < 1) return EN ? 'just now' : 'az önce';
    if(dk < 60) return dk + ' ' + (EN?'min ago':'dk önce');
    var sa = Math.floor(dk/60);
    if(sa < 24) return sa + ' ' + (EN?'h ago':'sa önce');
    return Math.floor(sa/24) + ' ' + (EN?'d ago':'gün önce');
  }
  function izCiz(d){
    var kutu = \$('izIc'); if(!kutu) return;
    if(!d){ kutu.innerHTML=''; return; }
    var h = '';
    var g = d.gorulen||[];
    h += '<h4 style="margin:0 0 6px">' + (EN?'Last seen':'Son görülenler') + '</h4>';
    h += '<p class="pn-ack" style="margin:0 0 8px">' +
         (EN ? 'Accounts seen in the last twenty four hours: ' : 'Son yirmi dört saatte görülen hesap: ') +
         esc(d.gun_sayisi||0) + '</p>';
    if(g.length){
      h += '<div class="pn-liste">';
      g.forEach(function(x){
        h += '<div class="pn-sat"><span style="flex:1 1 auto;min-width:0"><b>' + esc(x.ad||'') + '</b>' +
             (x.roller&&x.roller.length ? ' <small style="opacity:.75">' + esc(x.roller.join(', ')) + '</small>' : '') +
             '</span><span style="opacity:.75;flex:0 0 auto">' + esc(izSure(x.dakika)) + '</span></div>';
      });
      h += '</div>';
    } else h += '<p class="pn-ack">' + (EN?'No account has been seen yet.':'Henüz görülen bir hesap yok.') + '</p>';

    var e2 = d.hatalar||[];
    h += '<h4 style="margin:20px 0 6px">' + (EN?'Recorded faults':'Kayda geçen hatalar') + '</h4>';
    if(e2.length){
      h += '<div class="pn-liste">';
      e2.forEach(function(x){
        h += '<div class="pn-sat"><span style="flex:0 0 auto;opacity:.75">' +
             esc(String(x.zaman||x.t||'').replace('T',' ').slice(0,16)) + '</span>' +
             '<span style="flex:1 1 auto;min-width:0">' + esc(String(x.mesaj||x.hata||x.metin||JSON.stringify(x)).slice(0,220)) + '</span></div>';
      });
      h += '</div>';
    } else h += '<p class="pn-ack">' + (EN?'No fault has been recorded.':'Kayda geçmiş hata yok.') + '</p>';

    var t = d.telegram||[];
    h += '<h4 style="margin:20px 0 6px">' + (EN?'Notification log':'Bildirim kütüğü') + '</h4>';
    if(t.length){
      h += '<div class="pn-liste">';
      t.forEach(function(x){
        h += '<div class="pn-sat"><span class="rz ' + (x.durum==='GONDERILDI'?'rz-yes':'rz-kut') + '" style="flex:0 0 auto">' +
             esc(x.durum==='GONDERILDI'?(EN?'sent':'gitti'):(EN?'failed':'gitmedi')) + '</span>' +
             '<span style="opacity:.75;flex:0 0 auto">' + esc(String(x.zaman||'').replace('T',' ').slice(0,16)) + '</span>' +
             '<span style="flex:1 1 auto;min-width:0">' + esc(x.etiket||'') +
             (x.not ? ' <small style="opacity:.85">' + esc(x.not) + '</small>' : '') + '</span></div>';
      });
      h += '</div>';
    } else h += '<p class="pn-ack">' + (EN?'The notification log is empty.':'Bildirim kütüğü boş.') + '</p>';
    kutu.innerHTML = h;
  }
  if(\$('izYenile')) \$('izYenile').addEventListener('click', function(){
    var m = \$('izMsj'); msj(m, S.bekle);
    api('/yonetim/izleme').then(function(d){
      if(d&&d.ok){ izCiz(d.izleme); msj(m,'',''); }
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });

  /* ---- Posta aktarıcısı ayarları ----
     Kart ilk açıldığında kayıtlı değerler getirilir; parola yıldızla
     gelir ve öyle geri gönderilirse sunucu ona dokunmaz. */
  var smYuklendi = false;
  function smYukle(){
    if(smYuklendi || !\$('smSunucu')) return;
    smYuklendi = true;
    api('/yonetim/ayar').then(function(d){
      if(!d||!d.ok||!d.ayar||!d.ayar.smtp) return;
      var s = d.ayar.smtp;
      \$('smSunucu').value = s.sunucu||'';
      \$('smPort').value = s.port||587;
      \$('smKullanici').value = s.kullanici||'';
      \$('smParola').value = s.parola||'';
      \$('smGuvenlik').value = s.guvenlik||'tls';
      \$('smGonderen').value = s.gonderen||'';
      \$('smGonderenAd').value = s.gonderen_ad||'';
      \$('smYanit').value = s.yanit||'';
    }).catch(function(){});
  }
  if(\$('kartDurum')) \$('kartDurum').addEventListener('toggle', function(){ if(\$('kartDurum').open) smYukle(); });
  /* Hazır ayar: yalnızca SABİT alanlar. Parola kutusuna dokunulmaz,
     yalnızca imleç oraya götürülür. Zaten dolu bir alan EZİLMEZ:
     birinin elle yazdığı gönderen adresini bir düğmenin silmesi,
     düğmenin kendisinden daha büyük bir kusurdur. */
  if(\$('smOnAyar')) \$('smOnAyar').addEventListener('click', function(){
    \$('smSunucu').value   = 'smtp.lettermint.co';
    \$('smPort').value     = '465';
    \$('smGuvenlik').value = 'ssl';
    \$('smKullanici').value= 'lettermint';
    /* Gönderen adresi KODA YAZILMAZ. İki gerekçe: (1) bu depo herkese
       açıktır ve koda yazılan her adres orada kalır — sinama/sir-kapi.php
       ilk yazımda bunu YAKALADI; (2) koda yazılan adres kurulumun
       alan adı değiştiği gün sessizce yanlış olur. Sayfanın kendi
       alan adından türetiliyor: tek kaynak sitenin kendisidir. */
    if(!\$('smGonderen').value.trim())   \$('smGonderen').value   = 'bildirim@' + location.hostname.replace(/^www\./,'');
    if(!\$('smGonderenAd').value.trim()) \$('smGonderenAd').value = 'Kutadgu';
    msj(\$('smMsj'), (EN?'Filled in. Now paste the API token into the password box and press "Save the relay".'
                       :'Dolduruldu. Şimdi parola kutusuna API belirtecini yapıştırıp "Aktarıcıyı kaydet"e basın.'), 'ok');
    \$('smParola').focus();
  });
  if(\$('smKaydet')) \$('smKaydet').addEventListener('click', function(){
    var m = \$('smMsj'); msj(m, S.bekle);
    api('/yonetim/ayar-kaydet', {smtp:{
      sunucu: \$('smSunucu').value.trim(), port: parseInt(\$('smPort').value,10)||587,
      kullanici: \$('smKullanici').value.trim(), parola: \$('smParola').value,
      guvenlik: \$('smGuvenlik').value, gonderen: \$('smGonderen').value.trim(),
      gonderen_ad: \$('smGonderenAd').value.trim(), yanit: \$('smYanit').value.trim()
    }}).then(function(d){
      if(d&&d.ok){
        msj(m, (EN?'Saved. Now press "Test the mail path" above; the message goes only to your own address.'
                 :'Kaydedildi. Şimdi yukarıdaki "Posta yolunu sına" düğmesine basın; ileti yalnızca kendi adresinize gider.'), 'ok');
        smYuklendi = false; smYukle(); durumYukle();
      } else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });

  /* ---- Deneme düzeni ----
     Kurma ve silme yalnızca düğmeye basınca olur; kart açılınca değil. */
  function dnCiz(d){
    var kutu = \$('dnIc'); if(!kutu) return;
    if(!d || !d.hesaplar){ kutu.innerHTML=''; return; }
    /* ---- SATIRA BASINCA O HESABA GİRİLİR ----
       İstenen buydu: "deneme hakemi açıldığında üzerine tıklayınca
       direkt o kullanıcı gibi giriş yapsa." Parolayı elle kopyalayıp
       çıkış yapıp yeniden girmek, denemenin kendisinden uzun sürüyordu.

       DÜĞME, SATIRIN TAMAMI DEĞİL. Satırın her yerini tıklanabilir
       yapmak, parolayı seçip kopyalamak isteyen kişiyi yanlışlıkla
       başka bir hesaba düşürürdü. Giriş ayrı bir düğmedir ve ne
       yapacağını yazar.

       UYARI ÖNCEDEN: giriş yapınca bu oturum kapanır. Bunu tıkladıktan
       SONRA öğrenmek, kaydedilmemiş bir işi kaybetmek demek olabilir. */
    var h = '<div class="pn-liste">';
    d.hesaplar.forEach(function(x){
      h += '<div class="pn-sat"><span style="flex:1 1 auto;min-width:0"><b>' + esc(x.ad) + '</b>'
         + '<br><small style="opacity:.8">' + esc(x.eposta) + ' · ' + esc(x.rol) + '</small></span>'
         + '<span style="flex:0 0 auto;display:flex;gap:8px;align-items:center">' + (x.vardi
             ? '<span class="rz rz-sus">' + (EN?'already existed':'zaten vardı') + '</span>'
             : '<code style="font-size:var(--y-2)">' + esc(x.parola) + '</code>')
         + '<button type="button" class="d d-ikinci d-kucuk dn-gir" data-eposta="' + esc(x.eposta) + '">'
         + (EN?'Sign in as this account':'Bu hesapla gir') + '</button>'
         + '</span></div>';
    });
    h += '</div>';
    h += '<p class="pn-ack" style="margin-top:8px">' + (EN
      ? 'Signing in as a test account closes your own session. Only accounts marked as test can be entered this way; the entry is recorded in that account\'s login history with your name.'
      : 'Bir deneme hesabıyla girmek kendi oturumunuzu kapatır. Bu yoldan yalnızca deneme işaretli hesaplara girilebilir; giriş, o hesabın kaydına adınızla yazılır.') + '</p>';
    /* Parola bir kez gösterilir; kaybolursa düzen silinip yeniden
       kurulur. Ekranda kalıcı bir parola listesi tutmak, deneme
       düzenini gerçek bir açığa çevirir. */
    h += '<p class="pn-ack" style="margin-top:8px">' + (EN
      ? 'Passwords are shown once. If they are lost, delete the test setup and create it again.'
      : 'Parolalar bir kez gösterilir. Kaybolursa deneme düzenini silip yeniden kurun.') + '</p>';
    kutu.innerHTML = h;
  }
  /* Giriş düğmeleri sonradan çiziliyor: dinleyici kutuya bağlanır. */
  if(\$('dnIc')) \$('dnIc').addEventListener('click', function(e){
    var dg = e.target && e.target.closest ? e.target.closest('.dn-gir') : null;
    if(!dg) return;
    var ep = dg.getAttribute('data-eposta') || '';
    if(!confirm(EN ? ('Sign in as ' + ep + '? Your own session will be closed.')
                   : (ep + ' hesabıyla girilsin mi? Kendi oturumunuz kapanır.'))) return;
    var m = \$('dnMsj'); msj(m, S.bekle); dg.disabled = true;
    api('/yonetim/deneme-giris', {eposta: ep}).then(function(d){
      if(d && d.ok){
        msj(m, (EN?'Signed in. Reloading…':'Girildi. Sayfa yenileniyor…'), 'ok');
        location.href = '/panel.php';
      } else { dg.disabled = false; msj(m,(d&&d.hata)||S.baglanti,'err'); }
    }).catch(function(){ dg.disabled = false; msj(m,S.baglanti,'err'); });
  });

  /* ===================================================================
     ZENODO · KALICI KİMLİK (DOI)
     -------------------------------------------------------------------
     Üç iş, üç ayrı düğme ve üç ayrı ağırlık:
       önizle   ağa çıkmaz, ne gönderileceğini gösterir
       taslak   geri alınabilir (silinebilir)
       yayımla  GERİ ALINAMAZ — ayrı onay ister
     =================================================================== */
  var ZN = null;
  function znDurum(){
    return api('/yonetim/zenodo-durum').then(function(d){
      if(!d||!d.ok) return;
      ZN = d;
      var oz=\$('znOzet');
      if(oz) oz.textContent = (d.acik?(d.sandbox?(EN?'sandbox':'deneme evreni'):(EN?'live':'gerçek')):(EN?'off':'kapalı'))
        + ' · ' + d.sayi.doili + '/' + d.sayi.toplam + (EN?' with DOI':' çalışmada DOI');
      if(\$('znJeton')) \$('znJeton').placeholder = d.kurulu ? d.jeton : (EN?'not set':'yazılmamış');
      if(\$('znSandbox')) \$('znSandbox').checked = !!d.sandbox;
      if(\$('znAcik')) \$('znAcik').checked = !!d.acik;
      if(\$('znTopluluk') && !\$('znTopluluk').value) \$('znTopluluk').value = d.topluluk||'';
      znListeCiz();
    }).catch(function(){});
  }
  function znListeCiz(){
    var kutu=\$('znListe'); if(!kutu||!ZN) return;
    var h='<h4>'+(EN?'Works':'Çalışmalar')+'</h4>';
    ZN.liste.slice(0,60).forEach(function(w){
      var hal = w.doi ? ('<span class="pn-rz-k iyi">'+esc(w.doi)+'</span>')
              : (w.taslak ? '<span class="pn-rz-k uyar">'+(EN?'draft':'taslak')+' #'+esc(w.taslak)+'</span>'
                          : '<span class="pn-rz-k">'+(EN?'no identifier':'kimlik yok')+'</span>');
      h+='<div class="pn-is-sat"><b>'+esc(w.baslik)+'</b><span>'+esc(w.tarih)+' · '+hal+'</span>'
       + '<div class="d-kume" style="margin-top:8px">'
       + '<button type="button" class="d d-ikinci d-kucuk zn-onizle" data-y="'+esc(w.anahtar)+'">'+(EN?'Preview':'Önizle')+'</button>'
       + (w.doi ? '' :
           (w.taslak
             ? '<button type="button" class="d d-vurgu d-kucuk zn-yayimla" data-y="'+esc(w.anahtar)+'">'+(EN?'Publish (permanent)':'Yayımla (kalıcı)')+'</button>'
               +'<button type="button" class="d d-ikinci d-kucuk zn-sil" data-y="'+esc(w.anahtar)+'">'+(EN?'Delete draft':'Taslağı sil')+'</button>'
             : '<button type="button" class="d d-ikinci d-kucuk zn-taslak" data-y="'+esc(w.anahtar)+'">'+(EN?'Prepare draft':'Taslak hazırla')+'</button>'))
       + '</div><div class="zn-bak"></div></div>';
    });
    kutu.innerHTML=h;
  }
  if(\$('znKaydet')) \$('znKaydet').addEventListener('click', function(){
    var m=\$('znMsj'); msj(m,S.bekle);
    api('/yonetim/zenodo-ayar', {jeton:(\$('znJeton')||{}).value||'', topluluk:(\$('znTopluluk')||{}).value||'',
        sandbox: !!(\$('znSandbox')||{}).checked, acik: !!(\$('znAcik')||{}).checked}).then(function(d){
      if(d&&d.ok){ if(\$('znJeton')) \$('znJeton').value=''; msj(m,S.kaydedildi,'ok'); znDurum(); }
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });
  if(\$('znSina')) \$('znSina').addEventListener('click', function(){
    var m=\$('znMsj'); msj(m,S.bekle);
    api('/yonetim/zenodo-sina', {}).then(function(d){
      if(d&&d.ok) msj(m,(EN?'Token works · universe: ':'Jeton çalışıyor · evren: ')+esc(d.evren),'ok');
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });
  document.addEventListener('click', function(ev){
    var b=ev.target.closest?ev.target.closest('.zn-onizle,.zn-taslak,.zn-yayimla,.zn-sil'):null;
    if(!b) return;
    var y=b.dataset.y, m=\$('znMsj'), bak=b.closest('.pn-is-sat').querySelector('.zn-bak');
    if(b.classList.contains('zn-onizle')){
      msj(m,S.bekle);
      api('/yonetim/zenodo-onizleme?y='+encodeURIComponent(y)).then(function(d){
        if(!d||!d.ok){ msj(m,(d&&d.hata)||S.baglanti,'err'); return; }
        msj(m,'','');
        var dos=(d.dosyalar||[]).map(function(f){return esc(f.ad)+' ('+f.boyut+' B)';}).join(' · ');
        bak.innerHTML='<pre class="zn-onz">'+esc(JSON.stringify(d.ustveri,null,1))+'</pre>'
                    + '<p class="pn-ack">'+(EN?'Files: ':'Dosyalar: ')+dos+'</p>';
      }).catch(function(){ msj(m,S.baglanti,'err'); });
      return;
    }
    if(b.classList.contains('zn-taslak')){
      msj(m,S.bekle);
      api('/yonetim/zenodo-taslak', {y:y}).then(function(d){
        if(d&&d.ok){ msj(m,(EN?'Draft ready · reserved DOI: ':'Taslak hazır · rezerve DOI: ')+esc(d.rezerv||'-'),'ok'); znDurum(); }
        else msj(m,(d&&d.hata)||S.baglanti,'err');
      }).catch(function(){ msj(m,S.baglanti,'err'); });
      return;
    }
    if(b.classList.contains('zn-sil')){
      if(!confirm(EN?'Delete the draft on Zenodo?':'Zenodo\'daki taslak silinsin mi?')) return;
      msj(m,S.bekle);
      api('/yonetim/zenodo-taslak-sil', {y:y}).then(function(d){
        if(d&&d.ok){ msj(m,(EN?'Draft deleted.':'Taslak silindi.'),'ok'); znDurum(); }
        else msj(m,(d&&d.hata)||S.baglanti,'err');
      }).catch(function(){ msj(m,S.baglanti,'err'); });
      return;
    }
    /* YAYIMLAMA GERİ ALINAMAZ. Onay iki yerdedir: burada insana sorulur,
       uçta ayrıca 'onay' bayrağı aranır. Tek bir yerde sorulsaydı,
       arayüzdeki bir yanlışlık kalıcı bir kaydı doğurabilirdi. */
    if(!confirm(EN
      ? 'Publish this record on Zenodo? A published Zenodo record CANNOT be deleted and the DOI is permanent.'
      : 'Bu kayıt Zenodo\'da yayımlansın mı? Yayımlanmış bir Zenodo kaydı SİLİNEMEZ ve DOI kalıcıdır.')) return;
    msj(m,S.bekle);
    api('/yonetim/zenodo-yayimla', {y:y, onay:'1'}).then(function(d){
      if(d&&d.ok) msj(m,(d.gercek?(EN?'DOI issued: ':'DOI verildi: '):(EN?'Sandbox identifier (not real): ':'Deneme kimliği (gerçek değil): '))+esc(d.doi||'-'),'ok');
      else msj(m,(d&&d.hata)||S.baglanti,'err');
      znDurum();
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });

  if(\$('dnKur')) \$('dnKur').addEventListener('click', function(){
    var m = \$('dnMsj'); msj(m, S.bekle);
    api('/yonetim/deneme-kur', {}).then(function(d){
      if(d&&d.ok){ dnCiz(d); dnDurum(true); msj(m, (EN?'Test setup ready.':'Deneme düzeni hazır.'), 'ok'); }
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });
  /* ---- Eski bir deneme çalışmasını işaretlemek ----
     Seçenekler kişinin KENDİ çalışmalarından kurulur; sunucu da aynı
     kapıyı uyguluyor. İkisi ayrı yazıldığı için değil, biri ötekini
     unutmasın diye: sunucu kapısı tektir ve buradaki liste yalnızca
     onu görünür kılar. */
  function dnIsSecDoldur(){
    var sel=\$('dnIsSec'); if(!sel) return;
    api('/panel/ozet').then(function(d){
      if(!d||!d.ok||!d.calismalar) return;
      var h='<option value="">'+(EN?'— choose —':'— seçin —')+'</option>';
      d.calismalar.forEach(function(w){
        h+='<option value="'+esc(w.anahtar)+'">'+esc(w.baslik)+' · '+esc(w.tarih||'')+'</option>';
      });
      sel.innerHTML=h;
    }).catch(function(){});
  }
  function dnIsListeCiz(){
    var kutu=\$('dnIsListe'); if(!kutu) return;
    api('/yonetim/deneme-isaretliler').then(function(d){
      if(!d||!d.ok){ kutu.innerHTML=''; return; }
      if(!d.liste.length){ kutu.innerHTML=''; return; }
      /* GÖRÜNMEZ OLAN ŞEY BİR YERDE GÖRÜNMELİ: arşivden düşen kayıt
         panelden de düşerse kimse onun durduğunu bilmez. */
      var h='<h4>'+(EN?'Marked as test records':'Deneme kaydı olarak işaretlenenler')+'</h4>';
      d.liste.forEach(function(x){
        h+='<div class="pn-is-sat"><b>'+esc(x.baslik)+'</b>'
         + '<span>'+esc(x.kim)+' · '+esc((x.ne_zaman||'').substring(0,10))+'</span>'
         + '<span>'+esc(x.gerekce)+'</span>'
         + '<button type="button" class="d d-ikinci d-kucuk dn-is-geri" data-y="'+esc(x.anahtar)+'">'
         + (EN?'Undo':'Geri al')+'</button></div>';
      });
      kutu.innerHTML=h;
    }).catch(function(){ kutu.innerHTML=''; });
  }
  if(\$('dnIsYap')) \$('dnIsYap').addEventListener('click', function(){
    var m=\$('dnIsMsj'), y=(\$('dnIsSec')||{}).value||'', g=(\$('dnIsNeden')||{}).value||'';
    if(!y){ msj(m, EN?'Choose a work.':'Bir çalışma seçin.','err'); return; }
    if(!confirm(EN?'Mark this work as a test record? It disappears from every public page. The record is not deleted and this can be undone.'
                  :'Bu çalışma deneme kaydı olarak işaretlensin mi? Kamusal her sayfadan düşer. Kayıt silinmez, geri alınabilir.')) return;
    msj(m, S.bekle);
    api('/yonetim/deneme-isaretle', {y:y, gerekce:g}).then(function(d){
      if(d&&d.ok){ msj(m, EN?'Marked. It is out of the archive now.':'İşaretlendi; arşivden düştü.','ok');
                   if(\$('dnIsNeden')) \$('dnIsNeden').value='';
                   dnIsListeCiz(); dnIsSecDoldur(); }
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });
  document.addEventListener('click', function(ev){
    var b=ev.target.closest?ev.target.closest('.dn-is-geri'):null; if(!b) return;
    var m=\$('dnIsMsj'); msj(m,S.bekle);
    api('/yonetim/deneme-isaretle', {y:b.dataset.y, kaldir:1}).then(function(d){
      if(d&&d.ok){ msj(m, EN?'Undone; the work is in the archive again.':'Geri alındı; çalışma yeniden arşivde.','ok');
                   dnIsListeCiz(); dnIsSecDoldur(); }
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });

  if(\$('dnSil')) \$('dnSil').addEventListener('click', function(){
    if(!confirm(EN?'Delete every test record? Only records marked as test are removed.'
                  :'Bütün deneme kayıtları silinsin mi? Yalnızca deneme işaretli kayıtlar silinir.')) return;
    var m = \$('dnMsj'); msj(m, S.bekle);
    api('/yonetim/deneme-sil', {}).then(function(d){
      if(d&&d.ok){
        \$('dnIc').innerHTML=''; DN=null;
        if(\$('dnAkis')) \$('dnAkis').hidden=true;
        if(\$('dnRet')) \$('dnRet').hidden=true;
        msj(m, (EN?'Deleted: ':'Silindi: ') + (d.hesap||0) + (EN?' account, ':' hesap, ') + (d.calisma||0) + (EN?' work.':' çalışma.'), 'ok');
      } else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });


  /* ===================================================================
     DENEME AKIŞI · adım adım, GERÇEK uçlardan
     -------------------------------------------------------------------
     Aşama sunucuda kayıttan HESAPLANIR (/yonetim/deneme-durum); burada
     saklanmaz. Saklansaydı sayfa yenilendiğinde ya da kayıt elle
     değiştiğinde ekran, kaydın söylemediği bir şeyi söylerdi.

     Her adım sistemin kendi ucunu çağırır. Deneme için ikinci bir yol
     yazılsaydı, denenen şey sistemin kendisi olmazdı.
     =================================================================== */
  var DN = null;
  var DN_ADIM = [
    {k:'kuruldu',      tr:'Düzen kuruldu',            en:'Setup created',
     ic_tr:'Yazar, hakem ve editör hesapları ile hakem bekleyen bir çalışma var.',
     ic_en:'Author, reviewer and editor accounts exist, with a work awaiting a reviewer.',
     dg_tr:'Editör hakem atasın', dg_en:'Let the editor assign a reviewer'},
    {k:'hakem-atandi', tr:'Hakem atandı',             en:'Reviewer assigned',
     ic_tr:'Editör birinci hakemi atadı; davet gönderildi (deneme adresine teslim edilmez).',
     ic_en:'The editor assigned the first reviewer; the invitation was sent (undeliverable on the test domain).',
     dg_tr:'Hakem daveti kabul etsin', dg_en:'Let the reviewer accept'},
    {k:'davet-kabul',  tr:'Davet kabul edildi',       en:'Invitation accepted',
     ic_tr:'Hakem çalışmayı açabiliyor. “Hakemin gözünden bak” bağıyla gördüğü ekranın aynısını görürsünüz.',
     ic_en:'The reviewer can open the work. The link below shows exactly the screen they see.',
     dg_tr:'Hakem küçük revizyon istesin', dg_en:'Let the reviewer ask for a minor revision'},
    {k:'rapor-1',      tr:'Birinci rapor: küçük revizyon', en:'First report: minor revision',
     ic_tr:'Rapor yazıldı, metin kilidi revizyona açıldı ve yazar–hakem kanalı açıldı.',
     ic_en:'The report is written, the text is unlocked for revision and the author–reviewer channel is open.',
     dg_tr:'Yazar yanıt yazsın ve metni düzeltsin', dg_en:'Let the author reply and revise the text'},
    {k:'revizyon',     tr:'Yazar düzeltti',           en:'The author revised',
     ic_tr:'Yeni sürüm kayda geçti; çalışmanın sayfasında iki sürümün parmak izi yan yana durur.',
     ic_en:'The new version is recorded; the fingerprints of both versions stand side by side on the work\'s page.',
     dg_tr:'İkinci hakem atansın', dg_en:'Assign the second reviewer'},
    {k:'hakem-2',      tr:'İkinci hakem atandı',      en:'Second reviewer assigned',
     ic_tr:'İkinci hakem davet edildi ve daveti kabul etti.',
     ic_en:'The second reviewer was invited and accepted.',
     dg_tr:'İkinci hakem “kabul” desin', dg_en:'Let the second reviewer accept the work'},
    {k:'rapor-2',      tr:'İkinci rapor: kabul',      en:'Second report: accept',
     ic_tr:'İki rapor var; birinci hakem düzeltmeyi görüp kararını yenileyebilir.',
     ic_en:'Two reports exist; the first reviewer can see the revision and renew their decision.',
     dg_tr:'Birinci hakem kararını “kabul”e çevirsin', dg_en:'Let the first reviewer change their decision to accept'},
    /* ÜÇÜNCÜ HAKEM BİLEREK VAR. Ölçüldü: iki kabulle çalışma onaylı
       sayılıyor (onay eşiği iki) ama sayfa hâlâ hakem arandığını
       söylüyordu, çünkü hakem HEDEFİ üç. (Aşama metninin kendisi burada
       yazılmaz: asama-kapi, ortak.php dışında elle yazılmış her aşama
       metnini kusur sayar ve haklıdır — bu satır ilk yazımında tam
       olarak ona takıldı.) İki kabulde duran bir deneme,
       "yayımlanabilir dedikleri zaman nasıl görünüyor" sorusuna yarım
       yanıt veriyordu. */
    {k:'hakem-3',      tr:'Üçüncü hakem',             en:'Third reviewer',
     ic_tr:'İki olumlu rapor onay eşiğini doldurdu; sistem hakem hedefine kadar aramayı sürdürüyor.',
     ic_en:'Two positive reports meet the approval threshold; the system keeps seeking until the reviewer target is met.',
     dg_tr:'Üçüncü hakem atansın ve raporunu yazsın', dg_en:'Assign the third reviewer and let them report'},
    {k:'bitti',        tr:'Yayımlanabilir, hakem araması kapandı', en:'Publishable, the search is closed',
     ic_tr:'Hakem hedefi doldu. Çalışmanın sayfasında raporlar, kararlar, yazar–hakem yazışması ve sürüm kaydı bir arada görünür; hakem arama satırı artık yok.',
     ic_en:'The reviewer target is met. The work\'s page shows the reports, the decisions, the author–reviewer exchange and the version record together; the “seeking reviewers” line is gone.',
     dg_tr:'', dg_en:''}
  ];
  function dnAdimNo(k){ for(var i=0;i<DN_ADIM.length;i++) if(DN_ADIM[i].k===k) return i; return -1; }

  function dnAkisCiz(){
    var kap=\$('dnAkis'); if(!kap) return;
    if(!DN || !DN.kurulu){ kap.hidden=true; return; }
    kap.hidden=false;
    var simdi=dnAdimNo(DN.adim);
    \$('dnMerdiven').innerHTML = DN_ADIM.map(function(a,i){
      var hal = i<simdi ? 'oldu' : (i===simdi ? 'simdi' : 'sonra');
      return '<li class="dn-bs dn-'+hal+'"><b>'+esc(EN?a.en:a.tr)+'</b>'
           + '<span>'+esc(EN?a.ic_en:a.ic_tr)+'</span></li>';
    }).join('');
    var s=DN_ADIM[simdi]||{};
    var dg=\$('dnIleri');
    var yazi=EN?s.dg_en:s.dg_tr;
    dg.hidden = !yazi;
    dg.textContent = yazi || '';
    /* KİMİN GÖZÜNDEN. Bildirimin asıl istediği buydu: akışı işletmek
       değil, her rolün o anda ne gördüğünü GÖRMEK. */
    var b=[];
    b.push(['<a class="d d-ikinci d-kucuk" target="_blank" rel="noopener" href="'
            + esc(DN.calisma.yol) + '">' + (EN?'Reader\'s view':'Okurun gözünden') + '</a>']);
    if(DN.calisma.yazar_bak) b.push(['<a class="d d-ikinci d-kucuk" target="_blank" rel="noopener" href="'
            + esc(DN.calisma.yazar_bak) + '">' + (EN?'Author\'s view':'Yazarın gözünden') + '</a>']);
    (DN.hakemler||[]).forEach(function(h,i){
      if(!h.bak) return;
      b.push(['<a class="d d-ikinci d-kucuk" target="_blank" rel="noopener" href="'
            + esc(h.bak) + '">' + (EN?('Reviewer '+(i+1)+'\'s view'):((i+1)+'. hakemin gözünden')) + '</a>']);
    });
    var sifreler = [];
    if(DN.yazar && DN.yazar.sifre) sifreler.push((EN?'author code: ':'yazar erişim şifresi: ')+DN.yazar.sifre
      + ' · ' + (EN?'e mail: ':'e-posta: ') + DN.yazar.eposta);
    (DN.hakemler||[]).forEach(function(h,i){ if(h.sifre) sifreler.push((i+1)+(EN?'. reviewer code: ':'. hakem şifresi: ')+h.sifre); });
    \$('dnBak').innerHTML = '<div class="d-kume" style="margin-top:var(--b-3)">'+b.join('')+'</div>'
      + (sifreler.length ? '<p class="pn-ack dn-sifre">'+esc(sifreler.join(' · '))+'</p>' : '')
      + '<p class="pn-ack">'+esc(EN?('Stage: '+DN.calisma.asama+' · lock: '+DN.calisma.kilit+' · versions: '+DN.calisma.surum
             +' · approved: '+(DN.onay&&DN.onay.onayli?'yes':'no')+' · still seeking: '+(DN.araniyor?'yes':'no')+'/'+DN.hedef)
                                  :('Aşama: '+DN.calisma.asama+' · kilit: '+DN.calisma.kilit+' · sürüm: '+DN.calisma.surum
             +' · onaylı: '+(DN.onay&&DN.onay.onayli?'evet':'hayır')+' · hakem aranıyor: '+(DN.araniyor?'evet':'hayır')+' (hedef '+DN.hedef+')'))+'</p>';
  }


  /* ---- RET YOLU ----
     Aşama sunucuda hesaplanır, adımlar gerçek uçlardan geçer; kabul
     yoluyla aynı düzenek, ayrı kayıt. */
  var DN_RET_ADIM = [
    {k:'kuruldu',      tr:'Ret yolu kaydı hazır',      en:'The rejection test work is ready',
     ic_tr:'Yöntemi bilerek eksik bırakılmış bir deneme çalışması; iki hakemin ret gerekçesi bu.',
     ic_en:'A test work whose method is deliberately incomplete; this is what the two reviewers will reject it for.',
     dg_tr:'Birinci hakem atansın', dg_en:'Assign the first reviewer'},
    {k:'hakem-atandi', tr:'Hakem atandı',              en:'Reviewer assigned',
     ic_tr:'Davet gönderildi.', ic_en:'The invitation was sent.',
     dg_tr:'Hakem daveti kabul etsin', dg_en:'Let the reviewer accept'},
    {k:'davet-kabul',  tr:'Davet kabul edildi',        en:'Invitation accepted',
     ic_tr:'Hakem çalışmayı açabiliyor.', ic_en:'The reviewer can open the work.',
     dg_tr:'Birinci hakem “ret” desin', dg_en:'Let the first reviewer reject'},
    {k:'ret-1',        tr:'Birinci ret',               en:'First rejection',
     ic_tr:'Bir ret geldi; çalışma hâlâ açık ve yazar düzeltebilir. Tek ret süreci bitirmez.',
     ic_en:'One rejection is in; the work is still open and the author can still revise. One rejection does not end the process.',
     dg_tr:'İkinci hakem atansın', dg_en:'Assign the second reviewer'},
    {k:'hakem-2',      tr:'İkinci hakem atandı',       en:'Second reviewer assigned',
     ic_tr:'İkinci hakem daveti kabul etti.', ic_en:'The second reviewer accepted.',
     dg_tr:'İkinci hakem de “ret” desin', dg_en:'Let the second reviewer reject too'},
    {k:'ret-2',        tr:'İkinci ret',                en:'Second rejection',
     ic_tr:'Ret eşiği dolmak üzere.', ic_en:'The rejection threshold is about to be met.',
     dg_tr:'', dg_en:''},
    {k:'kilitli',      tr:'Kilitlendi, yayında kalıyor', en:'Locked, and stays published',
     ic_tr:'İki ret tamam. Yazar metni bir daha değiştiremez; çalışma silinmez, ret gerekçeleriyle birlikte yayında kalır. Yazarın gözünden bakıp kilidi görebilirsiniz.',
     ic_en:'Two rejections are in. The author can no longer change the text; the work is not deleted but stays published with the grounds for rejection. Open the author\'s view to see the lock.',
     dg_tr:'', dg_en:''}
  ];
  function dnRetNo(k){ for(var i=0;i<DN_RET_ADIM.length;i++) if(DN_RET_ADIM[i].k===k) return i; return -1; }
  var DN_RET_RAPOR = 'Çalışmanın sorusu ilgi çekici, ancak yöntem bölümü bulguları taşıyacak durumda değil: '
    + 'örneklemin nasıl seçildiği, hangi ölçüm aracının kullanıldığı ve verinin hangi dönemde toplandığı '
    + 'yazılmamış. Bu üç bilgi olmadan bulguların yinelenebilirliği değerlendirilemez ve yazılan sonuçların '
    + 'veriden mi yoksa yorumdan mı geldiği ayırt edilemez. Tartışma bölümü de alanyazınla hiç konuşmuyor. '
    + 'Bu hâliyle yayımlanmasını öneremiyorum; gerekçem yöntemin eksikliğidir, konunun değeri değil.';

  function dnRetCiz(){
    var kap=\$('dnRet'); if(!kap) return;
    if(!DN || !DN.ret){ kap.hidden=true; return; }
    kap.hidden=false;
    var simdi=dnRetNo(DN.ret.adim);
    \$('dnRetMerdiven').innerHTML = DN_RET_ADIM.map(function(a,i){
      var hal = i<simdi ? 'oldu' : (i===simdi ? 'simdi' : 'sonra');
      return '<li class="dn-bs dn-'+hal+'"><b>'+esc(EN?a.en:a.tr)+'</b>'
           + '<span>'+esc(EN?a.ic_en:a.ic_tr)+'</span></li>';
    }).join('');
    var s=DN_RET_ADIM[simdi]||{};
    var dg=\$('dnRetIleri');
    var yazi=EN?s.dg_en:s.dg_tr;
    dg.hidden = !yazi;
    dg.textContent = yazi || '';
    var b=['<a class="d d-ikinci d-kucuk" target="_blank" rel="noopener" href="'
           + esc(DN.ret.yol) + '">' + (EN?'Reader\'s view':'Okurun gözünden') + '</a>'];
    if(DN.ret.yazar_bak) b.push('<a class="d d-ikinci d-kucuk" target="_blank" rel="noopener" href="'
           + esc(DN.ret.yazar_bak) + '">' + (EN?'Author\'s view':'Yazarın gözünden') + '</a>');
    (DN.ret.hakemler||[]).forEach(function(h,i){
      if(!h.bak) return;
      b.push('<a class="d d-ikinci d-kucuk" target="_blank" rel="noopener" href="'
           + esc(h.bak) + '">' + (EN?('Reviewer '+(i+1)+'\'s view'):((i+1)+'. hakemin gözünden')) + '</a>');
    });
    \$('dnRetBak').innerHTML = '<div class="d-kume" style="margin-top:var(--b-3)">'+b.join('')+'</div>'
      + '<p class="pn-ack">'+esc(EN?('Locked: '+(DN.ret.kilitli?'yes':'no')+' · threshold '+DN.ret.esik)
                                   :('Kilitli: '+(DN.ret.kilitli?'evet':'hayır')+' · ret eşiği '+DN.ret.esik))+'</p>';
  }
  function dnRetSonraki(){
    var a = DN && DN.ret ? DN.ret.adim : '';
    var id = DN.ret.id;
    if(a==='kuruldu')      return api('/editor/hakem-ata', {id:id, ad:'Ret Hakemi', eposta:dnEposta('Ret Hakemi')});
    if(a==='hakem-atandi') return api('/hakem-davet-yanit', {d: DN.ret.hakemler[0].davet, yanit:'kabul'});
    if(a==='davet-kabul')  return dnRetYaz(DN.ret.hakemler[0]);
    if(a==='ret-1')        return api('/editor/hakem-ata', {id:id, ad:'Ret Hakemi 2', eposta:dnEposta('Ret Hakemi 2')})
                             .then(function(){ return dnDurum(true); })
                             .then(function(){ return api('/hakem-davet-yanit',
                               {d: DN.ret.hakemler[1].davet, yanit:'kabul'}); });
    if(a==='hakem-2')      return dnRetYaz(DN.ret.hakemler[1]);
    return Promise.resolve({ok:true});
  }
  function dnRetYaz(h){
    /* RET KARARINDA ÖLÇÜT DEĞERLENDİRMESİ İSTENMEZ: "ret" diyen bir
       hakemden, çalışmanın hangi dizinde yayımlanabileceğini
       işaretlemesi beklenemez (tg_rapor_nitelik). İşaretli alıntı ise
       istenir; gerekçesini metinde göstermeyen bir ret, gerekçesiz
       rettir. */
    return api('/hakem-gonder', {t:h.token, mail:dnEposta(h.ad), sifre:h.sifre,
      karar:'ret', rapor:DN_RET_RAPOR, notlar: JSON.stringify(DN_NOT)});
  }
  if(\$('dnRetIleri')) \$('dnRetIleri').addEventListener('click', function(){
    if(!DN || !DN.ret) return;
    var m=\$('dnRetMsj'); msj(m, S.bekle);
    \$('dnRetIleri').disabled=true;
    dnRetSonraki().then(function(r){
      \$('dnRetIleri').disabled=false;
      if(r && r.ok===false){ msj(m,(r.hata)||S.baglanti,'err'); return dnDurum(true); }
      msj(m, EN?'Step done.':'Adım işletildi.', 'ok');
      return dnDurum(true);
    }).catch(function(){ \$('dnRetIleri').disabled=false; msj(m,S.baglanti,'err'); });
  });

  function dnDurum(sessiz){
    /* GET: api() ikinci argümanı gövde sayar; null geçmek "gövdesi
       null olan bir POST" demekti. Argümansız çağrı GET yapar. */
    return api('/yonetim/deneme-durum').then(function(d){
      if(d&&d.ok){ DN=d; dnAkisCiz(); dnRetCiz(); }
      else if(!sessiz) msj(\$('dnAkisMsj'),(d&&d.hata)||S.baglanti,'err');
      return d;
    }).catch(function(){ if(!sessiz) msj(\$('dnAkisMsj'),S.baglanti,'err'); });
  }

  /* Adımlar. Her biri gerçek ucu çağırır; hiçbiri kısayol kullanmaz. */
  /* RAPOR EŞİĞİ 400 KARAKTERDİR (rapor_asgari_karakter) ve ölçüldü:
     ilk fikstürler 230-330 karakterdi, üçü de "gerekçe çok kısa" diye
     onay sayımının dışında kaldı. Deneme, sistemin kuralına çarpınca
     kuralı gösterdi; metinler eşiğin üstüne çıkarıldı. */
  var DN_METIN = {
    rapor1: 'Çalışmanın kuramsal çerçevesi yerinde kurulmuş ve örneklem araştırma sorusunu '
          + 'karşılayacak genişlikte. Yöntem bölümünde ölçüm aracının geçerlik ve güvenirlik '
          + 'katsayıları verilmemiş; bunlar eklenmeden bulguların ne kadar güvenilir olduğu '
          + 'değerlendirilemez, çünkü aracın ölçtüğü şeyi ölçtüğünü gösteren tek kanıt odur. '
          + 'Tartışma bölümü alanyazınla yeterince konuşmuyor: bulgular sunuluyor ama benzer '
          + 'çalışmalarla karşılaştırılmıyor, dolayısıyla katkının nerede durduğu görünmüyor. '
          + 'Kaynakça biçimi tutarlı ve atıflar metinle örtüşüyor. Bu iki düzeltmeyle çalışma '
          + 'yayımlanabilir; kuramsal katkısı bugünkü hâliyle de açıktır.',
    rapor2: 'Metni baştan sona okudum. Araştırma sorusu açık biçimde kurulmuş, yöntem soruya '
          + 'uygun ve bulgular yöntemin izin verdiği ölçüde yorumlanmış; yazar hiçbir yerde '
          + 'verisinin taşıyamayacağı bir sonuca gitmiyor, bu da ayrıca kayda değer. Birinci '
          + 'hakemin istediği geçerlik katsayıları yöntem bölümüne eklenmiş ve örneklem bilgisi '
          + 'olduğu gibi durmakta. Sınırlılıklar bölümü, çalışmanın neyi gösteremediğini de '
          + 'açıkça yazıyor. Ek bir düzeltmeye gerek görmüyorum; çalışma bu hâliyle '
          + 'yayımlanabilir.',
    rapor3: 'Çalışmayı üçüncü hakem olarak, önceki iki raporu görmeden okudum. Soru, yöntem ve '
          + 'bulgular birbirini tutuyor; kurulan çerçeve bulguların yorumlanma biçimini gerçekten '
          + 'belirliyor, sonradan iliştirilmiş bir çerçeve değil. Önceki hakemlerin istediği '
          + 'düzeltmeler yapılmış: geçerlik katsayıları verilmiş ve tartışma bölümü alanyazınla '
          + 'karşılaştırma yapacak kadar genişletilmiş. Yöntem bölümü artık başka bir '
          + 'araştırmacının aynı işlemi yineleyebileceği ayrıntıda. Ek bir düzeltme gerekmiyor.',
    yanit:  'Uyarınız için teşekkür ederim. Geçerlik katsayılarını yöntem bölümüne ekledim ve '
          + 'tartışma bölümünü üç kaynakla genişlettim. Düzeltilmiş metni kaydettim.',
    metin:  '<h2>Giriş</h2><p>Bu paragraf revizyondan SONRAKİ sürümdür; birinci hakemin '
          + 'isteği üzerine yeniden yazıldı.</p><h2>Yöntem</h2><p>Ölçüm aracının geçerlik '
          + 'katsayıları eklendi: Cronbach alfa 0,87; test-tekrar test 0,91. Örneklem ve '
          + 'dönem bilgisi olduğu gibi durmaktadır.</p><h2>Bulgular</h2><p>Deneme verisiyle '
          + 'üretilmiş üç bulgu.</p><h2>Tartışma</h2><p>Hakemin istediği üzere alanyazınla '
          + 'karşılaştırma bu bölümde genişletildi.</p><h2>Sonuç</h2><p>Bu kayıt bir denemedir.</p>'
  };
  function dnHakemAta(ad){
    return api('/editor/hakem-ata', {id: DN.calisma.id, ad: ad, eposta: dnEposta(ad)});
  }
  function dnEposta(ad){
    return ad.toLowerCase().replace(/ç/g,'c').replace(/ğ/g,'g').replace(/ı/g,'i')
             .replace(/ö/g,'o').replace(/ş/g,'s').replace(/ü/g,'u')
             .replace(/\\s+/g,'.') + '@deneme.gecersiz';
  }
  /* ---- DENEME RAPORU TAM BİR RAPORDUR ----
     ÖLÇÜLEN KUSUR — 19 Ağustos 2026. Akış üç kabulle bittiği hâlde
     çalışma "onaylı" olmuyordu. Sebep kuralın kendisiydi ve doğruydu:
     tg_rapor_nitelik(), gerekçesi kısa olan, METİNDE HİÇBİR YERİ
     İŞARETLEMEYEN ya da ölçüt değerlendirmesi doldurulmamış bir raporu
     onay sayımına katmaz. Deneme raporları yalnız düz metin
     gönderiyordu; yani deneme, sistemin en önemli kurallarından birini
     hiç göstermiyordu.

     Rapor artık gerçek bir raporun taşıdığı her şeyi taşır: işaretli
     alıntılar ve ölçüt değerlendirmesi. Deneme düzeninin gerçek
     düzenden eksik davranması, denemeyi değersizleştirir. */
  var DN_NOT = [
    {alinti:'Ölçüm aracının geçerlik katsayıları', not:'Bu değerler yöntem bölümünde verilmeli; verilmeden bulguların güvenilirliği değerlendirilemez.'},
    {alinti:'tartışma bölümü', not:'Alanyazınla karşılaştırma zayıf; en az üç kaynakla genişletilmeli.'}
  ];
  var DN_ANKET = [{endeks:'trdizin', secim:'evet', yanit:[4,5,4,4,5]}];
  function dnYaz(h, karar, rapor){
    return api('/hakem-gonder', {t:h.token, mail:dnEposta(h.ad), sifre:h.sifre,
      karar:karar, rapor:rapor,
      notlar: JSON.stringify(DN_NOT),
      endeks: JSON.stringify(['trdizin']),
      endeks_anket: JSON.stringify(DN_ANKET)});
  }
  function dnSonraki(){
    var adim = DN ? DN.adim : '';
    var yz = DN && DN.yazar ? {t:DN.yazar.token, sifre:DN.yazar.sifre, y:DN.calisma.slug} : null;
    if(adim==='kuruldu')      return dnHakemAta('Deneme Hakem');
    if(adim==='hakem-atandi') return api('/hakem-davet-yanit', {d: DN.hakemler[0].davet, yanit:'kabul'});
    if(adim==='davet-kabul')  return dnYaz(DN.hakemler[0], 'kucuk', DN_METIN.rapor1);
    if(adim==='rapor-1')      return api('/yazar-hakem-yanit',
                                  {t:yz.t, sifre:yz.sifre, y:yz.y, hakem:DN.hakemler[0].ad, metin:DN_METIN.yanit})
                                .then(function(){
                                  /* İÇERİK OLDUĞU GİBİ GERİ GÖNDERİLİR, yalnız metin
                                     değişir. İlk yazımda yalnız 'metin' gönderiliyordu ve
                                     uç haklı olarak reddetti: dolu bir künyeyi boş
                                     göndermek onu silmek demektir. Gerçek yazar paneli de
                                     alanların hepsini gönderir. */
                                  var ic = DN.calisma.icerik || {};
                                  return api('/yazar-kaydet', {
                                    t:yz.t, sifre:yz.sifre, y:yz.y,
                                    baslik: ic.baslik, baslik_en: ic.baslik_en,
                                    ozet: ic.ozet, ozet_en: ic.ozet_en,
                                    anahtar: ic.anahtar, anahtar_en: ic.anahtar_en,
                                    kaynakca: ic.kaynakca,
                                    metin: DN_METIN.metin }); });
    if(adim==='revizyon')     return dnHakemAta('Deneme Hakem 2')
                                .then(function(){ return dnDurum(true); })
                                .then(function(){ return api('/hakem-davet-yanit',
                                  {d: DN.hakemler[1].davet, yanit:'kabul'}); });
    if(adim==='hakem-2')      return dnYaz(DN.hakemler[1], 'kabul', DN_METIN.rapor2);
    if(adim==='rapor-2')      return dnYaz(DN.hakemler[0], 'kabul', DN_METIN.rapor1
                                  + ' (Revizyon sonrası: istenen katsayılar eklenmiş, karar kabule çevrildi.)');
    if(adim==='hakem-3')      return dnHakemAta('Deneme Hakem 3')
                                .then(function(){ return dnDurum(true); })
                                .then(function(){ return api('/hakem-davet-yanit',
                                  {d: DN.hakemler[2].davet, yanit:'kabul'}); })
                                .then(function(){ return dnDurum(true); })
                                .then(function(){ return dnYaz(DN.hakemler[2], 'kabul', DN_METIN.rapor3); });
    return Promise.resolve({ok:true});
  }
  if(\$('dnIleri')) \$('dnIleri').addEventListener('click', function(){
    if(!DN || !DN.kurulu) return;
    var m=\$('dnAkisMsj'); msj(m, S.bekle);
    \$('dnIleri').disabled=true;
    dnSonraki().then(function(r){
      \$('dnIleri').disabled=false;
      if(r && r.ok===false){ msj(m,(r.hata)||S.baglanti,'err'); return dnDurum(true); }
      msj(m, EN?'Step done.':'Adım işletildi.', 'ok');
      return dnDurum(true);
    }).catch(function(){ \$('dnIleri').disabled=false; msj(m,S.baglanti,'err'); });
  });
  if(\$('dnYenile')) \$('dnYenile').addEventListener('click', function(){ dnDurum(false); });

  if(\$('durumYenile')) \$('durumYenile').addEventListener('click', durumYukle);
  if(\$('durumPosta')) \$('durumPosta').addEventListener('click', function(){
    var m = \$('durumMsj'); msj(m, S.bekle);
    api('/yonetim/eposta-sina', {}).then(function(d){
      if(d&&d.ok){ msj(m, d.mesaj + ' (' + d.alici + ')', d.gonderildi?'ok':'err'); durumYukle(); }
      else msj(m,(d&&d.hata)||S.baglanti,'err');
    }).catch(function(){ msj(m,S.baglanti,'err'); });
  });

  /* ===================================================================
     YÖNETİM VE OKUMA RAPORLARI / MANAGEMENT AND READING REPORTS
     -------------------------------------------------------------------
     Bu bölüm 8 Ağustos 2026'ya kadar /yonetim/index.html adresinde ayrı
     bir sayfaydı ve ayrı bir parolayla açılırdı. Yönetim işini yapanlar
     baş editörlerdir; panelleri zaten burasıdır.

     İkiye ayrılmıştır ve ayrım kasıtlıdır:
       - Yazma işleri (başvuru kararı, çalışma kaydı, hakem daveti)
         Yönetim sekmesindedir ve o sekme yalnızca baş editöre basılır.
       - Okuma nitelikli sayılar (okuma raporu, süreler, karar dağılımı)
         Editör sekmesindedir ve bütün editörlere açıktır. Bir sayıyı
         görmek onu değiştirmek değildir.

     Aşağıdaki her işlev, karşılığı olan öge sayfada yoksa hiçbir şey
     yapmadan döner. Böylece aynı betik üç rolde de sorunsuz koşar.
     =================================================================== */

  /* ---- Küçük yardımcılar ---- */
  function yzSure(sn){
    sn = parseInt(sn||0, 10); if(!sn) return '0 ' + (EN?'s':'sn');
    if(sn < 60) return sn + ' ' + (EN?'s':'sn');
    var d = Math.floor(sn/60), k = sn%60;
    if(d < 60) return d + ' ' + (EN?'min':'dk') + (k ? ' ' + k + ' ' + (EN?'s':'sn') : '');
    return Math.floor(d/60) + ' ' + (EN?'h':'sa') + ' ' + (d%60) + ' ' + (EN?'min':'dk');
  }
  function yzGunAd(g){ return g + ' ' + (g===1 ? (EN?'day':'gün') : (EN?'days':'gün')); }
  function yzZaman(t){
    if(!t) return '';
    var d = new Date(t); if(isNaN(d.getTime())) return String(t);
    return d.toLocaleDateString(EN?'en-GB':'tr-TR') + ' '
         + d.toLocaleTimeString(EN?'en-GB':'tr-TR', {hour:'2-digit', minute:'2-digit'});
  }
  /* İki tarih arasındaki gün sayısı. Tarihlerden biri yoksa ya da
     sonuç akla yatkın değilse null döner: uydurulmuş bir sayı,
     olmayan bir sayıdan kötüdür. */
  function yzGun(a, b){
    if(!a || !b) return null;
    var x = new Date(a), y = new Date(b);
    if(isNaN(x.getTime()) || isNaN(y.getTime())) return null;
    var g = Math.round((y.getTime() - x.getTime()) / 86400000);
    return (g < 0 || g > 3650) ? null : g;
  }
  function bosSatir(sutun, metin){
    return '<tr><td colspan="' + sutun + '" class="pn-ack">' + esc(metin) + '</td></tr>';
  }
  /* Oran çubuğu: sayının bütün içindeki payı. Sayı zaten yanında yazılı. */
  function olcek(pay, butun){
    var o = butun > 0 ? Math.round(pay * 100 / butun) : 0;
    if(o < 0) o = 0; if(o > 100) o = 100;
    return '<div class="olcek"><i style="width:' + o + '%"></i></div>';
  }
  function sayacCiz(kutu, satirlar){
    if(!kutu) return;
    kutu.innerHTML = satirlar.map(function(n){
      return '<div class="pn-sayi"><div><b>' + esc(String(n[0])) + '</b><span>' + esc(n[1]) + '</span></div></div>';
    }).join('');
  }
  function kopyaDugme(kutu, metin, etiket){
    var b = document.createElement('button');
    b.type = 'button'; b.className = 'd d-ikinci d-kucuk';
    b.textContent = etiket || (EN?'Copy':'Kopyala');
    b.addEventListener('click', function(){
      var bitti = function(){
        b.textContent = EN?'Copied':'Kopyalandı';
        setTimeout(function(){ b.textContent = etiket || (EN?'Copy':'Kopyala'); }, 2000);
      };
      if(navigator.clipboard && navigator.clipboard.writeText){
        navigator.clipboard.writeText(metin).then(bitti).catch(function(){});
      } else { try{ document.execCommand('copy'); bitti(); }catch(x){} }
    });
    kutu.appendChild(b);
  }

  /* =========== OKUMA RAPORLARI (editör ve baş editör) =========== */
  var rpDonem = '';
  function raporKur(){
    if(!\$('rpAy') || raporKur.kuruldu) return;
    raporKur.kuruldu = true;
    \$('rpYenile').addEventListener('click', function(){ raporYukle(); });
    \$('rpAy').addEventListener('change', function(){ rpDonem = this.value; raporYukle(); });
    raporYukle();
  }
  function raporYukle(){
    if(!\$('rpAy')) return;
    msj(\$('rpMsj'), S.bekle);
    api('/yonetim/dergi-rapor' + (rpDonem ? ('?ay=' + encodeURIComponent(rpDonem)) : '')).then(function(d){
      if(!d || !d.ok){ msj(\$('rpMsj'), (d && d.hata) || S.baglanti, 'err'); return; }
      msj(\$('rpMsj'), '');
      var s = \$('rpAy'), aylar = (d.aylar && d.aylar.length) ? d.aylar : [d.ay];
      if(s.options.length !== aylar.length){
        s.innerHTML = aylar.map(function(a){ return '<option value="' + esc(a) + '">' + esc(a) + '</option>'; }).join('');
      }
      s.value = d.ay; rpDonem = d.ay;

      var o = d.ozet || {};
      sayacCiz(\$('rpSayac'), [
        [o.okuma || 0,            EN?'readings':'okuma'],
        [o.tekil || 0,            EN?'distinct readers':'tekil okuyucu'],
        [yzSure(o.ort_sure),      EN?'average time':'ortalama süre'],
        [yzSure(o.toplam_sure),   EN?'total time':'toplam süre']
      ]);
      /* Kapak satırı içerideki sayaçla AYNI iki değerden kurulur. */
      var ro = \$('rpOzet');
      if(ro) ro.textContent = (o.okuma || 0)
        ? ((d.ay||'') + ' · ' + (o.okuma||0) + ' ' + (EN?'readings':'okuma')
           + ' · ' + (o.tekil||0) + ' ' + (EN?'distinct readers':'tekil okuyucu'))
        : ((d.ay||'') + ' · ' + (EN?'no reading recorded':'okuma kaydı yok'));

      /* Çalışma başına */
      var mk = d.makale || [], enCok = 1;
      mk.forEach(function(m){ if((m.okuma||0) > enCok) enCok = m.okuma; });
      var h = '<tr><th>' + (EN?'Work':'Çalışma') + '</th>'
            + '<th class="sayi">' + (EN?'Readings':'Okuma') + '</th>'
            + '<th class="sayi">' + (EN?'Distinct':'Tekil') + '</th>'
            + '<th class="sayi">' + (EN?'Average':'Ortalama') + '</th>'
            + '<th class="sayi">' + (EN?'Depth':'Derinlik') + '</th>'
            + '<th>' + (EN?'Share':'Pay') + '</th></tr>';
      if(!mk.length) h += bosSatir(6, EN?'No reading was recorded in this period.':'Bu dönemde okuma kaydı yok.');
      mk.forEach(function(m){
        var mb = String(m.baslik || m.id || '');
        h += '<tr><td class="tb-ad"><span class="tb-bas" title="' + esc(mb) + '">' + esc(mb) + '</span></td>'
           + '<td class="sayi">' + (m.okuma||0) + '</td>'
           + '<td class="sayi">' + (m.tekil||0) + '</td>'
           + '<td class="sayi">' + esc(yzSure(m.ort_sure)) + '</td>'
           + '<td class="sayi">' + (m.ort_oran||0) + '%</td>'
           + '<td>' + olcek(m.okuma||0, enCok) + '</td></tr>';
      });
      \$('rpCalisma').innerHTML = h;

      /* Şehirler */
      var sh = d.sehir || {}, shTop = 0, c;
      for(c in sh) if(Object.prototype.hasOwnProperty.call(sh, c)) shTop += sh[c];
      var h2 = '<tr><th>' + (EN?'City':'Şehir') + '</th>'
             + '<th class="sayi">' + (EN?'Readings':'Okuma') + '</th>'
             + '<th>' + (EN?'Share':'Pay') + '</th></tr>';
      if(!shTop) h2 += bosSatir(3, EN?'No location was recorded in this period.':'Bu dönemde konum kaydı yok.');
      for(c in sh) if(Object.prototype.hasOwnProperty.call(sh, c))
        h2 += '<tr><td>' + esc(c) + '</td><td class="sayi">' + sh[c] + '</td><td>' + olcek(sh[c], shTop) + '</td></tr>';
      \$('rpSehir').innerHTML = h2;

      /* Ülkeler ve cihaz */
      var ul = d.ulke || {}, cz = d.cihaz || {}, ulTop = 0, u;
      for(u in ul) if(Object.prototype.hasOwnProperty.call(ul, u)) ulTop += ul[u];
      var h3 = '<tr><th>' + (EN?'Country':'Ülke') + '</th>'
             + '<th class="sayi">' + (EN?'Readings':'Okuma') + '</th>'
             + '<th>' + (EN?'Share':'Pay') + '</th></tr>';
      if(!ulTop) h3 += bosSatir(3, EN?'No country was recorded in this period.':'Bu dönemde ülke kaydı yok.');
      for(u in ul) if(Object.prototype.hasOwnProperty.call(ul, u))
        h3 += '<tr><td>' + esc(u) + '</td><td class="sayi">' + ul[u] + '</td><td>' + olcek(ul[u], ulTop) + '</td></tr>';
      var czTop = (cz.masaustu||0) + (cz.mobil||0);
      h3 += '<tr><th>' + (EN?'Device':'Cihaz') + '</th><th class="sayi"></th><th></th></tr>'
          + '<tr><td>' + (EN?'Desktop':'Masaüstü') + '</td><td class="sayi">' + (cz.masaustu||0) + '</td><td>' + olcek(cz.masaustu||0, czTop) + '</td></tr>'
          + '<tr><td>' + (EN?'Mobile':'Mobil') + '</td><td class="sayi">' + (cz.mobil||0) + '</td><td>' + olcek(cz.mobil||0, czTop) + '</td></tr>';
      \$('rpUlke').innerHTML = h3;

      /* Tek tek kayıtlar: çizelge yalnızca baş editörün sayfasında var,
         yanıtta da yalnızca ona geliyor. */
      var sonT = \$('rpSon');
      if(sonT){
        var sn = d.son || [];
        var h4 = '<tr><th>' + (EN?'Time':'Zaman') + '</th>'
               + '<th>' + (EN?'Work':'Çalışma') + '</th>'
               + '<th>' + (EN?'Place':'Konum') + '</th>'
               + '<th class="sayi">' + (EN?'Time spent':'Süre') + '</th>'
               + '<th class="sayi">' + (EN?'Depth':'Derinlik') + '</th>'
               + '<th>' + (EN?'Device':'Cihaz') + '</th></tr>';
        if(!sn.length) h4 += bosSatir(6, EN?'No reading was recorded in this period.':'Bu dönemde okuma kaydı yok.');
        sn.forEach(function(r){
          var kon = [r.sehir, r.ulke].filter(Boolean).join(', ');
          h4 += '<tr><td>' + esc(yzZaman(r.t)) + '</td>'
              + '<td class="tb-ad"><span class="tb-bas" title="' + esc(String(r.baslik || '')) + '">'
                + esc(String(r.baslik || '')) + '</span></td>'
              + '<td>' + esc(kon || '-') + '</td>'
              + '<td class="sayi">' + esc(yzSure(r.sure)) + '</td>'
              + '<td class="sayi">' + (r.oran||0) + '%</td>'
              + '<td>' + (r.cihaz === 'mobil' ? (EN?'mobile':'mobil') : (EN?'desktop':'masaüstü')) + '</td></tr>';
        });
        sonT.innerHTML = h4;
      }
    }).catch(function(){ msj(\$('rpMsj'), S.baglanti, 'err'); });
  }

  /* =====================================================================
     İLETİLER
     ---------------------------------------------------------------------
     Üç uçtan beslenir: liste, oku, yanıt. Liste tek başına yetmez, çünkü
     bir iletiye yanıt yazmak için konuşmanın TAMAMINI görmek gerekir;
     özetine bakıp yanıt yazmak, sorulmayan bir soruya cevap vermektir.

     Bekleyen sayısı hem kartın başlığında hem de sekme şeridinde durur:
     bekleyen bir kurum başvurusunun görülmesi, panelin o sekmesinin
     açılmasına bağlı olmamalı.
     ===================================================================== */
  /* Parolamı unuttum. Kutu açılır açılmaz odak adres alanına gider:
     düğmeye basan kişi zaten yazmak istiyor. */
  if(\$('dgUnuttum')) \$('dgUnuttum').addEventListener('click', function(e){
    e.preventDefault();
    var k = \$('unutKutu'); if(!k) return;
    k.classList.toggle('gizli');
    if(!k.classList.contains('gizli')){
      var m = \$('uMail'), g = \$('gKim');
      if(m && g && !m.value) m.value = (g.value || '').indexOf('@') > 0 ? g.value : '';
      if(m) m.focus();
    }
  });
  if(\$('dgUnutGonder')) \$('dgUnutGonder').addEventListener('click', function(){
    var m = (\$('uMail') || {}).value || '';
    if(m.indexOf('@') < 1){ msj(\$('unutMsj'), S.unutBos, 'err'); return; }
    \$('dgUnutGonder').disabled = true; msj(\$('unutMsj'), S.bekle);
    /* YANIT HER DURUMDA AYNI. Uç de öyle davranıyor; burada farklı bir
       şey yazmak, uçtaki özeni ekranda geri vermek olurdu. */
    api('/hesap/parola-unuttum', {eposta: m}).then(function(){
      \$('dgUnutGonder').disabled = false;
      msj(\$('unutMsj'), S.unutGitti, 'ok');
    }).catch(function(){ \$('dgUnutGonder').disabled = false; msj(\$('unutMsj'), S.baglanti, 'err'); });
  });

  /* HEREDOC TUZAĞI. Bu betik PHP'nin <<<JS bloğunun içindedir ve
     heredoc kaçış dizilerini KENDİSİ çözer: kaynağa yazılan bir
     ters bölü + n, betiğe GERÇEK BİR SATIR SONU olarak düşer. İlk
     yazımda satır sonlarını değiştiren düzenli ifade bu yüzden
     ortasından bölündü ve panelin tamamı sustu ("Invalid regular
     expression", ölçüldü). Kaçış hiç kullanılmaz: satır sonu
     karakteri koduyla üretilir ve bir daha kimse buna takılmaz. */
  var SATIR = String.fromCharCode(10);
  var iltSuz = '', iltAcik = '';
  function iltTurAd(t){ return (S.iltTur && S.iltTur[t]) || t; }
  function iltGun(t){
    if(!t) return '';
    var d = new Date(t); if(isNaN(d)) return '';
    return d.toLocaleDateString(EN?'en-GB':'tr-TR') + ' ' +
           d.toLocaleTimeString(EN?'en-GB':'tr-TR', {hour:'2-digit', minute:'2-digit'});
  }
  function iltListeCiz(){
    if(!\$('iltListe')) return;
    api('/yonetim/ileti-liste' + (iltSuz ? ('?durum=' + encodeURIComponent(iltSuz)) : '')).then(function(d){
      if(!d || !d.ok){ \$('iltListe').innerHTML = '<p class="pn-ack">' + ((d&&d.hata)||S.baglanti) + '</p>'; return; }
      /* Bekleyen sayısı süzgeçten BAĞIMSIZ olmalı: "kapalı" süzgecine
         bakarken bekleyen sayısının sıfır görünmesi, bekleyen ileti
         yokmuş gibi okunurdu. */
      if(!iltSuz) iltBekYaz(d.bekleyen || 0);
      var l = d.ileti || [];
      if(!l.length){ \$('iltListe').innerHTML = '<p class="pn-ack">' + S.iltYok + '</p>'; return; }
      var h = '<div class="tablo-sar"><table class="tb"><thead><tr>' +
              '<th>' + (EN?'Kind':'Tür') + '</th><th>' + S.iltKisi + '</th><th>' + (EN?'Subject':'Konu') + '</th>' +
              '<th>' + (EN?'Last':'Son') + '</th><th></th></tr></thead><tbody>';
      l.forEach(function(x){
        h += '<tr><td>' + esc(iltTurAd(x.tur)) + '</td>' +
             '<td>' + esc(x.ad || '') + '<br><small>' + esc(x.eposta || '') + '</small></td>' +
             '<td>' + esc(x.konu || '') +
               (x.bekliyor ? ' <span class="rz rz-kir">' + S.iltBekleyen + '</span>' : '') +
               (x.kapali   ? ' <span class="rz rz-cizgi">' + S.iltKapali + '</span>' : '') +
               (x.ustlenen ? ' <span class="rz rz-kut">' + esc(x.ustlenen) + '</span>' : '') +
               '<br><small>' + esc(x.ozet || '') + '</small></td>' +
             '<td><small>' + esc(iltGun(x.sonTarih)) + '</small></td>' +
             '<td><button type="button" class="d d-ikinci d-kucuk" data-ilt-ac="' + esc(x.kod) + '">' +
                 S.iltAc + '</button></td></tr>';
      });
      \$('iltListe').innerHTML = h + '</tbody></table></div>';
    }).catch(function(){ \$('iltListe').innerHTML = '<p class="pn-ack">' + S.baglanti + '</p>'; });
  }
  /* BEKLEYEN İLETİ SAYISI ARTIK EDİTÖR ROZETİNE YAZILMIYOR.
     Yazıldığı sürece "Editör 2" iki ayrı işi birden sayıyordu ve
     hangisini saydığı okunamıyordu. Sayı kendi sekmesine yazılır;
     kart başlığındaki kopyası ise sekme açıkken de görünsün diye
     durur ve aynı çağrıdan beslenir. */
  function iltBekYaz(n){
    n = n || 0;
    var e = \$('iltBekSay');
    if(e){
      if(n > 0){ e.textContent = n; e.classList.remove('gizli'); }
      else { e.textContent = ''; e.classList.add('gizli'); }
    }
    roz('rozIletiler', n,
        EN?(n===1?'message awaits a reply':'messages await a reply'):'ileti yanıt bekliyor', 'iletiler');
  }
  /* SONUÇ BİLDİRİMİ ÇİZİMDEN SONRA YAZILIR.
     Yanıt gönderilince konuşma yeniden çizilir ve çizim innerHTML'i
     baştan kurduğu için içindeki bildirim kutusu da yok olur: ölçüldü,
     "gönderildi" yazısı bir an görünüp siliniyordu. Bildirim artık
     çizime parametre olarak geçer ve çizimden SONRA yazılır. */
  function iltKonusmaCiz(kod, bildir){
    var kut = \$('iltKonusma'); if(!kut) return;
    iltAcik = kod;
    kut.classList.remove('gizli');
    kut.innerHTML = '<p class="pn-ack">' + S.bekle + '</p>';
    api('/yonetim/ileti-oku?kod=' + encodeURIComponent(kod)).then(function(d){
      if(!d || !d.ok){ kut.innerHTML = '<p class="pn-ack">' + ((d&&d.hata)||S.baglanti) + '</p>'; return; }
      var x = d.ileti || {};
      var h = '<h3>' + esc(iltTurAd(x.tur)) + ': ' + esc(x.konu || x.ad || '') + '</h3>' +
              '<p class="pn-ack">' + esc(x.ad || '') + ' &lt;' + esc(x.eposta || '') + '&gt; · ' +
              esc(iltGun(x.tarih)) + '</p>';
      /* ÜSTLENME KUTUSU. Başkası ilgileniyorsa uyarı rengiyle, siz
         ilgileniyorsanız sessizce yazar. Uyarı YANIT KUTUSUNDAN ÖNCE
         durur: yazdıktan sonra öğrenmek işe yaramaz. */
      if(x.ustlenen){
        if(x.ustlenen_ben){
          h += '<p class="kutu kutu-kut">' + esc(S.iltUstBen) + ' ' +
               esc(S.iltUstSure.replace('%1', x.ustlenme_dk || 45)) + '</p>';
        } else {
          h += '<p class="kutu kutu-kir"><b>' +
               esc(S.iltUstBaska.replace('%1', x.ustlenen).replace('%2', iltGun(x.ustlenen_tarih))) +
               '</b></p>';
        }
      }
      /* KİMLER GÖRDÜ: üstlenmeden ayrıdır ve düşmez. Bir iletiye
         kimsenin bakmadığını görmek de bir bilgidir. */
      if(x.bakanlar && x.bakanlar.length){
        h += '<p class="pn-ack">' + esc(S.iltBakanlar) + ': ' +
             x.bakanlar.map(function(bb){ return esc(bb.ad) + ' (' + esc(iltGun(bb.tarih)) + ')'; }).join(', ') +
             '</p>';
      }
      (x.mesajlar || []).forEach(function(m){
        var ben = (m.kim === 'kurul');
        h += '<div class="ilt-m' + (ben ? ' ilt-m-biz' : '') + '">' +
             '<b>' + (ben ? esc(S.iltSiz + (m.yazan ? (' · ' + m.yazan) : '')) : esc(x.ad || '')) + '</b>' +
             '<span>' + esc(iltGun(m.tarih)) + '</span>' +
             '<p>' + esc(m.metin || '').split(SATIR).join('<br>') + '</p></div>';
      });
      /* Yanıt kutusu yalnız yetkisi olana. Yetkisi olmayan editör
         iletiyi okur; okumak da bir iştir ve engellenmemeli. */
      if(BAS_YETKI){
        h += '<label for="iltMetin">' + S.iltYanit + '</label>' +
             '<textarea id="iltMetin" rows="5"></textarea>' +
             '<div class="pn-dugmeler">' +
             '<button type="button" class="d d-vurgu d-kucuk" data-ilt-yanit="0">' + S.iltGonder + '</button>' +
             '<button type="button" class="d d-ikinci d-kucuk" data-ilt-yanit="1">' + S.iltKapat + '</button>' +
             '<button type="button" class="d d-sessiz d-kucuk" data-ilt-durum="' + (x.kapali ? '1' : '0') + '">' +
                 (x.kapali ? S.iltYeniden : S.iltKapatSade) + '</button>' +
             '<button type="button" class="d d-sessiz d-kucuk" data-ilt-ist="' + (x.istenmeyen ? '1' : '0') + '">' +
                 (x.istenmeyen ? S.iltIstGeri : S.iltIstenmeyen) + '</button>' +
             '<button type="button" class="d d-tehlike d-kucuk" data-ilt-sil="1">' + S.iltSil + '</button>' +
             '</div><div id="iltMsj" class="form-msj"></div>';
      }
      h += '<p class="pn-ack">' + S.iltBag + ': <code>' + esc(x.baglanti || '') + '</code></p>';
      kut.innerHTML = h;
      if(bildir) msj(\$('iltMsj'), bildir, 'ok');
    }).catch(function(){ kut.innerHTML = '<p class="pn-ack">' + S.baglanti + '</p>'; });
  }
  document.addEventListener('click', function(ev){
    var s2 = ev.target.closest ? ev.target.closest('[data-ilt-suz]') : null;
    if(s2){
      iltSuz = s2.getAttribute('data-ilt-suz') || '';
      Array.prototype.forEach.call(document.querySelectorAll('[data-ilt-suz]'), function(b){
        b.classList.toggle('acik', b === s2);
      });
      iltListeCiz(); return;
    }
    var a2 = ev.target.closest ? ev.target.closest('[data-ilt-ac]') : null;
    if(a2){ iltKonusmaCiz(a2.getAttribute('data-ilt-ac')); return; }
    var y2 = ev.target.closest ? ev.target.closest('[data-ilt-yanit]') : null;
    if(y2){
      var mt = (\$('iltMetin') || {}).value || '';
      if(mt.trim().length < 2){ msj(\$('iltMsj'), S.iltBos, 'err'); return; }
      y2.disabled = true; msj(\$('iltMsj'), S.bekle, '');
      api('/yonetim/ileti-yanit', {kod: iltAcik, metin: mt.trim(),
                                   kapat: y2.getAttribute('data-ilt-yanit') === '1'}).then(function(d){
        y2.disabled = false;
        if(d && d.ok){ iltKonusmaCiz(iltAcik, S.iltGitti); iltListeCiz(); }
        else msj(\$('iltMsj'), (d && d.hata) || S.baglanti, 'err');
      }).catch(function(){ y2.disabled = false; msj(\$('iltMsj'), S.baglanti, 'err'); });
      return;
    }
    var k2 = ev.target.closest ? ev.target.closest('[data-ilt-durum]') : null;
    if(k2){
      api('/yonetim/ileti-kapat', {kod: iltAcik, ac: k2.getAttribute('data-ilt-durum') === '1'})
        .then(function(){ iltKonusmaCiz(iltAcik); iltListeCiz(); }).catch(function(){});
      return;
    }
    var i2 = ev.target.closest ? ev.target.closest('[data-ilt-ist]') : null;
    if(i2){
      api('/yonetim/ileti-istenmeyen', {kod: iltAcik, geri: i2.getAttribute('data-ilt-ist') === '1'})
        .then(function(){ iltKonusmaCiz(iltAcik); iltListeCiz(); }).catch(function(){});
      return;
    }
    /* SİLME SORULUR. Geri alınamayan tek işlem budur ve kişinin
       elindeki bağlantıyı da çalışmaz hâle getirir; tek tıkla
       olmamalı. Onay tarayıcının kendi penceresiyle alınır: betik
       çalışmıyorsa düğme zaten yoktur. */
    var s3 = ev.target.closest ? ev.target.closest('[data-ilt-sil]') : null;
    if(s3){
      if(!window.confirm(S.iltSilSor)) return;
      api('/yonetim/ileti-sil', {kod: iltAcik}).then(function(d){
        if(d && d.ok){
          var kut = \$('iltKonusma');
          if(kut){ kut.innerHTML = '<p class="pn-ack">' + S.iltSilindi + '</p>'; }
          iltAcik = ''; iltListeCiz();
        }
      }).catch(function(){});
    }
  });


  /* ===================================================================
     KURUL KARARLARI
     -------------------------------------------------------------------
     Sayım burada YAPILMAZ, sunucudan gelir (tg_kk_sonuc). İki yerde iki
     ayrı sayım, bir gün iki ayrı sonuç demektir.
     =================================================================== */
  function kkCiz(){
    var kutu = \$('kkListe'); if(!kutu) return;
    api('/kurul-kararlari').then(function(d){
      if(!d || !d.ok){ kutu.innerHTML=''; return; }
      var kl = d.kararlar || [];
      var acik = kl.filter(function(k){ return !k.sonuc.kapali; }).length;
      var oz = \$('kkOzet');
      if(oz) oz.textContent = kl.length
        ? (EN ? (acik + ' open · ' + kl.length + ' in total')
              : (acik + ' açık · toplam ' + kl.length))
        : (EN ? 'No decision has been opened yet.' : 'Henüz karar açılmadı.');
      if(!kl.length){
        kutu.innerHTML = '<p class="pn-ack">'
          + (EN ? 'No decision has been opened yet.' : 'Henüz karar açılmadı.') + '</p>';
        return;
      }
      kutu.innerHTML = kl.map(function(k){
        var s = k.sonuc;
        var h = '<div class="kk-kayit"><div class="kk-bas"><b>' + esc(k.baslik) + '</b>'
              + '<span class="rz rz-cizgi">' + esc(k.tur_ad) + '</span>'
              + /* RENK TEK BAŞINA ANLAM TAŞIMAZ: rozetin yazısı hâli
                 zaten söylüyor (tg_kk_hal_ad). Renk yalnız pekiştirir. */
              + '<span class="rz ' + (s.kapali ? (s.hal==='kabul'?'rz-yes':'rz-kir') : 'rz-cizgi') + '">'
              + esc(k.hal_ad) + '</span></div>'
              + '<p class="kk-metin">' + esc(k.metin) + '</p>'
              + '<p class="pn-ack">' + esc(k.acan) + ' · ' + esc((k.tarih||'').slice(0,10))
              + ' · ' + (EN ? 'threshold ' : 'yeter sayı ') + s.yeter + '/' + s.kisi
              + ' · ' + (EN?'in favour ':'kabul ') + s.say.kabul
              + ' · ' + (EN?'against ':'ret ') + s.say.ret
              + ' · ' + (EN?'abstain ':'çekimser ') + s.say.cekimser + '</p>';
        if(k.oylar && k.oylar.length){
          h += '<div class="kk-oylar">' + k.oylar.map(function(o){
            return '<div class="kk-oy"><b>' + esc(o.ad) + '</b> <span class="rz rz-cizgi">'
                 + esc(o.karar_ad) + '</span><span>' + esc(o.gerekce) + '</span></div>';
          }).join('') + '</div>';
        }
        if(!s.kapali){
          h += '<div class="kk-ver" data-kod="' + esc(k.kod) + '">'
             + '<label>' + (EN?'Your reasoning':'Gerekçeniz') + '</label>'
             + '<textarea class="kk-ger" rows="2" maxlength="4000"></textarea>'
             + '<div class="d-kume">'
             + '<button type="button" class="d d-vurgu d-kucuk kk-oy-dg" data-oy="kabul">' + (EN?'In favour':'Kabul') + '</button>'
             + '<button type="button" class="d d-ikinci d-kucuk kk-oy-dg" data-oy="ret">' + (EN?'Against':'Ret') + '</button>'
             + '<button type="button" class="d d-sessiz d-kucuk kk-oy-dg" data-oy="cekimser">' + (EN?'Abstain':'Çekimser') + '</button>'
             + '</div><div class="form-msj kk-msj"></div></div>';
        }
        return h + '</div>';
      }).join('');
    }).catch(function(){});
  }
  if(\$('kkAc')) \$('kkAc').addEventListener('click', function(){
    var m = \$('kkMsj'); msj(m, S.bekle);
    api('/yonetim/kurul-karar-ac', {tur: \$('kkTur').value,
        baslik: \$('kkBaslik').value.trim(), metin: \$('kkMetin').value.trim()}).then(function(d){
      if(d && d.ok){
        msj(m, EN?'The decision is open and the vote has started.':'Karar açıldı, oylama başladı.', 'ok');
        \$('kkBaslik').value=''; \$('kkMetin').value=''; kkCiz();
      } else msj(m, (d&&d.hata)||S.baglanti, 'err');
    }).catch(function(){ msj(m, S.baglanti, 'err'); });
  });
  if(\$('kkListe')) \$('kkListe').addEventListener('click', function(e){
    var dg = e.target.closest ? e.target.closest('.kk-oy-dg') : null;
    if(!dg) return;
    var kap = dg.closest('.kk-ver');
    var m = kap.querySelector('.kk-msj');
    msj(m, S.bekle);
    api('/yonetim/kurul-karar-oy', {kod: kap.getAttribute('data-kod'),
        karar: dg.getAttribute('data-oy'),
        gerekce: kap.querySelector('.kk-ger').value.trim()}).then(function(d){
      if(d && d.ok){ kkCiz(); }
      else msj(m, (d&&d.hata)||S.baglanti, 'err');
    }).catch(function(){ msj(m, S.baglanti, 'err'); });
  });

  /* ===== ARŞİV BOŞLUKLARI (salt okunur) =====
     Kart yalnız sayar; hiçbir düğmesi yoktur. Eksiği kapatmak gerçek
     kişilere posta yazmaktır ve posta geri alınamaz — geri alınamaz bir
     işi bir raporun yanına iliştirmek onu kazayla yapılabilir kılar. */
  function boslukCiz(){
    if(!\$('bsEtik')) return;
    api('/editor/arsiv-bosluk').then(function(d){
      if(!d || !d.ok){ \$('bsMsj').textContent = (d && d.hata) || S.baglanti;
                       \$('bsMsj').className = 'form-msj err'; return; }
      var eS = (d.etik && d.etik.sayi) || 0, oS = (d.ortak && d.ortak.sayi) || 0,
          oK = (d.ortak && d.ortak.kisi) || 0;
      var ozet = \$('bsOzet');
      if(ozet) ozet.textContent = (eS || oS)
        ? (EN ? (eS + ' works without a declaration · ' + oK + ' co authors unreached')
              : (eS + ' çalışmada beyan yok · ' + oK + ' ortak yazara ulaşılamıyor'))
        : (EN ? 'No gap was found.' : 'Boşluk bulunmadı.');
      var ack = \$('bsAck');
      if(ack) ack.textContent = EN
        ? ('Of ' + (d.toplam || 0) + ' works. The ethics declaration began to be requested on '
           + (d.baslangic || '-') + '; it was never asked of works published before that date. '
           + 'This report only counts. Writing to these people is a separate, deliberate act.')
        : (d.toplam || 0) + ' çalışma içinde. Etik beyanı ' + (d.baslangic || '-')
          + ' tarihinde istenmeye başlandı; ondan önce yayımlanmış çalışmaların yazarlarından hiç istenmedi. '
          + 'Bu rapor yalnızca sayar; bu kişilere yazmak ayrı ve bilerek yapılan bir iştir.';

      var h = '<tr><th>' + (EN?'Work':'Çalışma') + '</th><th>' + (EN?'Published':'Yayın')
            + '</th><th>' + (EN?'State':'Durum') + '</th></tr>';
      var el = (d.etik && d.etik.liste) || [];
      if(!el.length) h += bosSatir(3, EN?'Every work carries a declaration.':'Her çalışmada beyan var.');
      else el.forEach(function(w){
        h += '<tr><td><a href="' + esc(w.yol) + '">' + esc(w.baslik) + '</a></td>'
           + '<td>' + esc(w.tarih || '-') + '</td>'
           + '<td>' + esc(EN ? (w.hal_ad_en || '') : (w.hal_ad || '')) + '</td></tr>';
      });
      \$('bsEtik').innerHTML = h;

      var h2 = '<tr><th>' + (EN?'Work':'Çalışma') + '</th><th>' + (EN?'Published':'Yayın')
             + '</th><th>' + (EN?'Co authors':'Ortak yazarlar') + '</th></tr>';
      var ol = (d.ortak && d.ortak.liste) || [];
      if(!ol.length) h2 += bosSatir(3, EN?'Every co author has an address.':'Her ortak yazarın adresi var.');
      else ol.forEach(function(w){
        h2 += '<tr><td><a href="' + esc(w.yol) + '">' + esc(w.baslik) + '</a></td>'
            + '<td>' + esc(w.tarih || '-') + '</td>'
            + '<td>' + esc((w.kisiler || []).join(', ')) + '</td></tr>';
      });
      \$('bsOrtak').innerHTML = h2;
    }).catch(function(){ \$('bsMsj').textContent = S.baglanti; \$('bsMsj').className = 'form-msj err'; });
  }

  /* Arşiv bildiriminin önizlemesi. Kapak AÇILDIĞINDA getirilir: her
     panel açılışında iki yüz satırlık bir liste çekmek, kimsenin
     bakmadığı bir veriyi her seferinde taşımak olurdu. */
  if(\$('bsOnizleme')) \$('bsOnizleme').addEventListener('toggle', function(){
    if(!this.open || this.dataset.geldi) return;
    this.dataset.geldi='1';
    var kutu=\$('bsOnizIc');
    kutu.innerHTML='<p class="pn-ack">'+S.bekle+'</p>';
    api('/yonetim/arsiv-bildirim-onizleme').then(function(d){
      if(!d || !d.ok){ kutu.innerHTML='<p class="form-msj err">'+esc((d&&d.hata)||S.baglanti)+'</p>'; return; }
      var bl = function(ad, x){
        var h='<h4 style="margin:var(--b-5) 0 var(--b-2)">'+esc(ad)+'</h4>'
             + '<p class="pn-ack">'+(EN?'Would reach ':'Ulaşılacak kişi: ')+x.sayi
             + (x.adressiz ? (' · '+(EN?'no address: ':'adresi yok: ')+x.adressiz) : '')+'</p>'
             + '<p class="pn-ack"><b>'+(EN?'Subject: ':'Konu: ')+'</b>'+esc(x.konu)+'</p>'
             + '<pre class="bs-ileti">'+esc(x.govde)+'</pre>';
        if(x.liste && x.liste.length){
          h+='<div class="tablo-sar"><table class="tb"><tr><th>'+(EN?'Recipient':'Alıcı')+'</th><th>'
            +(EN?'Address':'Adres')+'</th><th>'+(EN?'Work':'Çalışma')+'</th></tr>'
            + x.liste.slice(0,40).map(function(r){
                return '<tr><td>'+esc(r.ad)+'</td><td>'+esc(r.adres)+'</td><td>'+esc(r.baslik)+'</td></tr>';
              }).join('')
            + '</table></div>';
          if(x.liste.length>40) h+='<p class="pn-ack">'+(EN?'First 40 of ':'İlk 40 satır, toplam ')+x.liste.length+'</p>';
        }
        return h;
      };
      kutu.innerHTML = bl(EN?'Ethics declaration was never asked':'Etik beyanı hiç istenmemiş çalışmalar', d.etik)
                     + bl(EN?'Co authors never told of their authorship':'Yazarlığı hiç bildirilmemiş ortak yazarlar', d.ortak)
                     + '<p class="pn-ack">'+esc(d.not||'')+'</p>';
    }).catch(function(){ kutu.innerHTML='<p class="form-msj err">'+esc(S.baglanti)+'</p>'; });
  });

  /* ===== DEĞERLENDİRME SÜRELERİ VE KARAR DAĞILIMI (editör ve baş editör)
     Ayrı bir uç açılmadı: sayıların hepsi editörün zaten çağırdığı
     /editor/calismalar yanıtından türer. ===== */
  function sureKararCiz(){
    if(!\$('srKarar')) return;
    var ad = {kabul: EN?'Accept':'Kabul', kucuk: EN?'Minor revision':'Küçük revizyon',
              buyuk: EN?'Major revision':'Büyük revizyon', ret: EN?'Reject':'Ret',
              baska: EN?'Not stated':'Belirtilmemiş'};
    var sira = ['kabul','kucuk','buyuk','ret','baska'];
    var say = {kabul:0, kucuk:0, buyuk:0, ret:0, baska:0};
    var gunler = [], bekleyen = [], simdi = new Date().toISOString();

    CALISMALAR.forEach(function(c){
      (c.hakemler||[]).forEach(function(hk){
        if(hk.rapor_var){
          var k = String(hk.karar||'');
          say[(say[k] === undefined) ? 'baska' : k]++;
          var g = yzGun(hk.davet_tarih, hk.rapor_tarih);
          if(g !== null) gunler.push(g);
        } else if(hk.davet !== 'geri_cekildi' && hk.davet !== 'ret'){
          bekleyen.push({calisma: c.baslik||'', ad: hk.ad||'',
                         gun: yzGun(hk.davet_tarih, simdi), durum: String(hk.davet||'')});
        }
      });
    });

    var ort = '-', enKisa = '-', enUzun = '-';
    if(gunler.length){
      var t = 0;
      gunler.forEach(function(g){ t += g; });
      gunler.sort(function(a, b){ return a - b; });
      ort = yzGunAd(Math.round(t / gunler.length));
      enKisa = yzGunAd(gunler[0]);
      enUzun = yzGunAd(gunler[gunler.length - 1]);
    }
    sayacCiz(\$('srSayac'), [
      [ort,            EN?'average duration':'ortalama süre'],
      [enKisa,         EN?'shortest':'en kısa'],
      [enUzun,         EN?'longest':'en uzun'],
      [gunler.length,  EN?'reports measured':'ölçülen rapor']
    ]);
    /* Kapak satırı: açmadan önce açacak bir şey olup olmadığını söyler
       ve içerideki sayaçla AYNI iki değerden kurulur. */
    var so = \$('srOzet');
    if(so) so.textContent = gunler.length
      ? (EN ? (gunler.length + ' reports measured · ' + ort + ' on average')
            : (gunler.length + ' rapor ölçüldü · ortalama ' + ort))
      + (bekleyen.length ? (EN ? (' · ' + bekleyen.length + ' report awaited')
                               : (' · ' + bekleyen.length + ' rapor bekleniyor')) : '')
      : (EN ? 'No report has been written yet.' : 'Henüz yazılmış rapor yok.');

    var toplam = 0;
    sira.forEach(function(k){ toplam += say[k]; });
    var h = '<tr><th>' + (EN?'Decision':'Karar') + '</th>'
          + '<th class="sayi">' + (EN?'Reports':'Rapor') + '</th>'
          + '<th>' + (EN?'Share':'Pay') + '</th></tr>';
    if(!toplam) h += bosSatir(3, EN?'No report has been written yet.':'Henüz yazılmış rapor yok.');
    else sira.forEach(function(k){
      if(k === 'baska' && !say[k]) return;
      h += '<tr><td>' + esc(ad[k]) + '</td><td class="sayi">' + say[k] + '</td>'
         + '<td>' + olcek(say[k], toplam) + '</td></tr>';
    });
    \$('srKarar').innerHTML = h;

    bekleyen.sort(function(a, b){ return (b.gun===null?-1:b.gun) - (a.gun===null?-1:a.gun); });
    var durumAd = {bekliyor: EN?'invitation pending':'davet bekliyor',
                   suresi_doldu: EN?'invitation expired':'davetin süresi doldu'};
    var h2 = '<tr><th>' + (EN?'Work':'Çalışma') + '</th>'
           + '<th>' + (EN?'Reviewer':'Hakem') + '</th>'
           + '<th class="sayi">' + (EN?'Waiting':'Bekleyen') + '</th>'
           + '<th>' + (EN?'State':'Durum') + '</th></tr>';
    if(!bekleyen.length) h2 += bosSatir(4, EN?'No report is being awaited.':'Raporu beklenen davet yok.');
    bekleyen.slice(0, 40).forEach(function(b){
      h2 += '<tr><td>' + esc(b.calisma) + '</td><td>' + esc(b.ad) + '</td>'
          + '<td class="sayi">' + (b.gun===null ? '-' : esc(yzGunAd(b.gun))) + '</td>'
          + '<td>' + esc(durumAd[b.durum] || (EN?'no report yet':'rapor yok')) + '</td></tr>';
    });
    \$('srBekleyen').innerHTML = h2;
  }

  /* ================= YÖNETİM (yalnızca baş editör) ================= */
  var YON_BAS = [], YON_YAZ = [], YON_ARSIV_GOSTER = false;

  function yonetimKur(){
    if(!\$('yonBasListe') || yonetimKur.kuruldu) return;
    yonetimKur.kuruldu = true;
    yonBasvuruYukle();
    yonYaziYukle();
  }

  /* ---- Başvurular ---- */
  function yonBasvuruYukle(){
    var kutu = \$('yonBasListe'); if(!kutu) return;
    kutu.innerHTML = '<p class="pn-ack">' + esc(S.bekle) + '</p>';
    api('/yonetim/basvurular').then(function(d){
      if(!d || !d.ok){ kutu.innerHTML = '<p class="pn-ack">' + esc((d && d.hata) || S.baglanti) + '</p>'; return; }
      YON_BAS = d.basvurular || [];
      var nYon = YON_BAS.filter(function(b){ return (b.durum||'') === 'bekliyor'; }).length;
      roz('rozYonetim', nYon,
          EN?(nYon===1?'application awaits a decision':'applications await a decision'):'başvuru karar bekliyor', 'yonetim');
      yonBasvuruCiz();
    }).catch(function(){ kutu.innerHTML = '<p class="pn-ack">' + esc(S.baglanti) + '</p>'; });
  }

  /* Arama kutusu listeyi yeniden çizer. Dinleyici bir kez bağlanır;
     liste her yazımda innerHTML ile kurulduğu için kutunun kendisi
     listenin DIŞINDA durur ve silinmez. */
  if(\$('ybAra')) \$('ybAra').addEventListener('input', function(){ yonBasvuruCiz(); });

  function yonBasvuruCiz(){
    var kutu = \$('yonBasListe'); if(!kutu) return;
    if(!YON_BAS.length){
      kutu.innerHTML = '<p class="pn-ack">' + (EN?'No application has arrived yet.':'Henüz başvuru yok.') + '</p>';
      return;
    }
    var rzAd = {kabul: [EN?'Accepted':'Kabul', 'rz-yes'], ret: [EN?'Rejected':'Ret', 'rz-kir']};

    /* SINIR VE SIRA.
       Ölçüldü: 262 başvuruluk veride bu kart 77.629 piksel oluyordu.
       Sebep, listenin sınırsız olmasıydı — çalışma listesinde aynı kural
       yıllar önce konmuştu (12 kayıt, aramada 40), burada konmamıştı.

       Sıra da değişti: karar bekleyenler önce gelir. Karara bağlanmış bir
       başvuru bir KAYITtır; bekleyen bir başvuru bir İŞtir ve iş kaydın
       altında duramaz. */
    var ybQ = ((\$('ybAra')||{}).value || '').toLowerCase().trim();
    var arsivSayi = YON_BAS.filter(function(b){ return !!b.arsiv; }).length;
    var suzulmus = YON_BAS.filter(function(b){
      if(b.arsiv && !YON_ARSIV_GOSTER) return false;
      if(!ybQ) return true;
      var bv = b.basvuran || {};
      return ((bv.ad||'') + ' ' + (bv.kurum||'') + ' ' + (bv.eposta||'') + ' ' + (b.makale_baslik||''))
             .toLowerCase().indexOf(ybQ) >= 0;
    });
    var bekleyenler = suzulmus.filter(function(b){ return String(b.durum||'bekliyor') === 'bekliyor'; });
    var kararlilar  = suzulmus.filter(function(b){ return String(b.durum||'bekliyor') !== 'bekliyor'; });
    var SINIR = ybQ ? 40 : 12;
    var sirali = bekleyenler.concat(kararlilar);
    var gosterilen = sirali.slice(0, SINIR);
    var gizlenen = sirali.length - gosterilen.length;

    /* SESSİZ KIRPMA YOK: kaç kayıt gösterilmediği yazılır. Kırpıldığını
       söylemeyen bir liste, "hepsi bu kadar" diye okunur. */
    var ust = '<p class="pn-ack">'
      + (EN ? (bekleyenler.length + ' awaiting a decision · ' + kararlilar.length + ' decided')
            : (bekleyenler.length + ' başvuru karar bekliyor · ' + kararlilar.length + ' karara bağlandı'))
      + (gizlenen > 0
          ? (EN ? (' · ' + gizlenen + ' more not shown; use the search box above')
                : (' · ' + gizlenen + ' tanesi gösterilmiyor, yukarıdan arayın'))
          : '')
      + (arsivSayi > 0
          ? ' · <button type="button" class="d d-ikinci d-kucuk" id="ybArsivDgm">'
            + (YON_ARSIV_GOSTER
                ? (EN ? 'Hide archived (' + arsivSayi + ')' : 'Arşivdekileri gizle (' + arsivSayi + ')')
                : (EN ? 'Show archived (' + arsivSayi + ')' : 'Arşivdekileri göster (' + arsivSayi + ')'))
            + '</button>'
          : '')
      + '</p>';

    var ybIlkAcildi = false;
    var h = ust + '<div class="pn-liste">';
    gosterilen.forEach(function(b){
      var bv = b.basvuran || {}, dr = String(b.durum || 'bekliyor');
      var rz = rzAd[dr] || [EN?'Pending':'Bekliyor', 'rz-kut'];
      var kim = (bv.unvan ? bv.unvan + ' ' : '') + (bv.ad || '');
      /* YALNIZCA İLK BEKLEYEN AÇIK GELİR — ve bu, çalışma listesindeki
         kuraldan bilerek ayrılır.

         Orada kapak, karar bekleyen bir GÖNÜLLÜ varsa açılır; o bir
         istisnadır, ayda birkaç kez olur. Burada ise "bekliyor" bir
         istisna değil, her yeni başvurunun İLK HÂLİdir. Ölçüldü: on iki
         bekleyen başvurunun hepsi açık gelince kart 4458 piksel oldu.
         İstisna sanılan şey kural çıkınca, istisnaya göre kurulmuş
         davranış da kusura dönüşür.

         Başvurular teker teker karara bağlanır. İlki açık gelir, sırası
         gelen açılır. */
      var ilkBekleyen = (dr === 'bekliyor' && !ybIlkAcildi);
      if(ilkBekleyen) ybIlkAcildi = true;
      h += '<details class="pn-cal"' + (ilkBekleyen ? ' open' : '') + '>'
         + '<summary><h3>' + esc(kim) + '</h3>'
         + '<div class="kim">' + esc(String(b.makale_baslik || '')) + '</div>'
         + '<div class="rzler"><span class="rz ' + rz[1] + '">' + esc(rz[0]) + '</span></div>'
         + '</summary><div class="pn-cal-ic">'
         + '<p class="pn-ack">' + esc([yzZaman(b.tarih), bv.eposta || '', bv.kurum || ''].filter(Boolean).join(' · ')) + '</p>'
         + '<p><b>' + (EN?'Work':'Çalışma') + ':</b> ' + esc(String(b.makale_baslik || '')) + '</p>';
      var yz = (b.yazarlar || []).map(function(y){ return (y.unvan ? y.unvan + ' ' : '') + (y.ad || ''); }).join(', ');
      if(yz) h += '<p class="pn-ack"><b>' + (EN?'Authors':'Yazarlar') + ':</b> ' + esc(yz) + '</p>';
      if(b.makale_ozet)
        h += '<details class="pn-ornek"><summary>' + (EN?'Abstract':'Özet') + '</summary>'
           + '<p class="pn-ack">' + esc(String(b.makale_ozet)) + '</p></details>';
      h += '<p class="pn-ack">' + (b.telif_kabul
             ? (EN?'The licence to publish and archive was granted.':'Yayımlama ve arşivleme iznini verdi.')
             : (EN?'No licence record.':'İzin kaydı yok.')) + '</p>';
      if(dr === 'kabul' && b.yazar_token){
        h += '<div class="pn-anahtar" data-anahtar="' + esc(b.id) + '">'
           + '<b>' + (EN?'Author link':'Yazar bağlantısı') + ':</b> ' + esc(location.origin + '/yazar.php?t=' + b.yazar_token) + '<br>'
           + '<b>' + (EN?'Password':'Şifre') + ':</b> ' + esc(String(b.yazar_sifre || '')) + '<br>'
           + '<b>' + (EN?'E mail':'E-posta') + ':</b> ' + esc(String(bv.eposta || '')) + '</div>';
      }
      if(dr === 'bekliyor'){
        h += '<div class="d-kume" style="margin-top:var(--b-3)">'
           + '<button type="button" class="d d-vurgu d-kucuk" data-kabul="' + esc(b.id) + '">'
           + (EN?'Accept and open the work':'Kabul et, çalışmayı aç') + '</button>'
           + '<button type="button" class="d d-tehlike d-kucuk" data-ret="' + esc(b.id) + '">'
           + (EN?'Reject':'Reddet') + '</button></div>';
      }
      if(dr === 'ret' && b.not)
        h += '<p class="pn-ack"><b>' + (EN?'Reason':'Ret gerekçesi') + ':</b> ' + esc(String(b.not)) + '</p>';
      if(dr !== 'bekliyor')
        h += '<div class="d-kume" style="margin-top:var(--b-3)">'
           + '<button type="button" class="d d-ikinci d-kucuk" data-arsiv="' + esc(b.id) + '" data-ars="' + (b.arsiv ? '0' : '1') + '">'
           + (b.arsiv ? (EN?'Restore from archive':'Arşivden çıkar') : (EN?'Archive (hide from list)':'Arşivle (listeden gizle)')) + '</button></div>';
      h += '</div></details>';
    });
    kutu.innerHTML = h + '</div>';

    Array.prototype.forEach.call(kutu.querySelectorAll('[data-anahtar]'), function(el){
      var id = el.getAttribute('data-anahtar');
      var b = YON_BAS.filter(function(x){ return String(x.id) === id; })[0] || {};
      var bv = b.basvuran || {};
      var metin = (EN ? 'Your author panel link: ' : 'Yazar paneli bağlantınız: ')
                + location.origin + '/yazar.php?t=' + (b.yazar_token||'') + '\\n'
                + (EN ? 'Access password: ' : 'Erişim şifresi: ') + (b.yazar_sifre||'') + '\\n'
                + (EN ? 'E mail: ' : 'E-posta: ') + (bv.eposta||'');
      kopyaDugme(el, metin);
    });
    var ybAd = \$('ybArsivDgm');
    if(ybAd) ybAd.addEventListener('click', function(){ YON_ARSIV_GOSTER = !YON_ARSIV_GOSTER; yonBasvuruCiz(); });
    Array.prototype.forEach.call(kutu.querySelectorAll('[data-arsiv]'), function(dg){
      dg.addEventListener('click', function(){
        var ars = dg.getAttribute('data-ars') === '1';
        dg.disabled = true;
        api('/yonetim/basvuru-arsivle', {id: dg.getAttribute('data-arsiv'), arsiv: ars}).then(function(r){
          if(r && r.ok){ yonBasvuruYukle(); }
          else { dg.disabled = false; window.alert((r && r.hata) || S.baglanti); }
        }).catch(function(){ dg.disabled = false; });
      });
    });
    Array.prototype.forEach.call(kutu.querySelectorAll('[data-kabul]'), function(dg){
      dg.addEventListener('click', function(){
        if(!window.confirm(EN ? 'Accept the application and open author access?'
                              : 'Başvuru kabul edilip yazar erişimi açılsın mı?')) return;
        dg.disabled = true;
        msj(\$('yonBasMsj'), S.bekle);
        api('/yonetim/basvuru-karar', {id: dg.getAttribute('data-kabul'), karar: 'kabul'}).then(function(r){
          if(r && r.ok){ msj(\$('yonBasMsj'), S.kaydedildi, 'ok'); yonBasvuruYukle(); yonYaziYukle(); }
          else { msj(\$('yonBasMsj'), (r && r.hata) || S.baglanti, 'err'); dg.disabled = false; }
        }).catch(function(){ msj(\$('yonBasMsj'), S.baglanti, 'err'); dg.disabled = false; });
      });
    });
    Array.prototype.forEach.call(kutu.querySelectorAll('[data-ret]'), function(dg){
      dg.addEventListener('click', function(){
        var not = window.prompt(EN ? 'Reason (optional; it is written only on the application record):'
                                   : 'Ret gerekçesi (isteğe bağlı; yalnızca başvuru kaydına düşülür):', '');
        if(not === null) return;
        dg.disabled = true;
        msj(\$('yonBasMsj'), S.bekle);
        api('/yonetim/basvuru-karar', {id: dg.getAttribute('data-ret'), karar: 'ret', not: not}).then(function(r){
          if(r && r.ok){ msj(\$('yonBasMsj'), S.kaydedildi, 'ok'); yonBasvuruYukle(); }
          else { msj(\$('yonBasMsj'), (r && r.hata) || S.baglanti, 'err'); dg.disabled = false; }
        }).catch(function(){ msj(\$('yonBasMsj'), S.baglanti, 'err'); dg.disabled = false; });
      });
    });
  }

  /* ---- Çalışmalar ----
     Çalışmanın kendisi burada düzenlenmez: bir çalışmayı yazan da
     düzelten de yazarıdır ve bunu Çalışmalarım sekmesinden yapar. Bu
     yükleme yalnızca hakem daveti içindir; hakemin bağlantısı ve şifresi
     başka hiçbir uçtan gelmiyor.
     A work is not edited here. Whoever wrote a work also corrects it,
     from the My works tab. This load exists only for the reviewer
     invitation. */
  function yonYaziYukle(){
    if(!\$('hkListe')) return;
    api('/yonetim/yazilar-tam').then(function(d){
      if(!d || !d.ok){
        \$('hkListe').innerHTML = '<p class="pn-ack">' + esc((d && d.hata) || S.baglanti) + '</p>';
        return;
      }
      YON_YAZ = d.yazilar || [];
      yonHakemCiz();
    }).catch(function(){
      \$('hkListe').innerHTML = '<p class="pn-ack">' + esc(S.baglanti) + '</p>';
    });
  }

  /* ---- Hakemlik süreçleri: yalnızca okunur ---- */
  function yonHakemCiz(){
    var kutu = \$('hkListe'); if(!kutu) return;
    var kararAd = {kabul: EN?'Accept':'Kabul', kucuk: EN?'Minor revision':'Küçük revizyon',
                   buyuk: EN?'Major revision':'Büyük revizyon', ret: EN?'Reject':'Ret'};
    var hkQ = ((\$('hkAra')||{}).value || '').toLowerCase().trim();
    var hepsi = YON_YAZ.filter(function(y){ return (y.hakemler||[]).length; });
    var suzulmus = hepsi.filter(function(y){
      if(!hkQ) return true;
      var ad = (y.hakemler||[]).map(function(x){ return x.ad||''; }).join(' ');
      return ((y.baslik||'') + ' ' + (y.bcid||'') + ' ' + ad).toLowerCase().indexOf(hkQ) >= 0;
    });
    /* Rapor bekleyeni olan çalışmalar önce: bekleyen bir rapor bir İŞ,
       tamamlanmış bir süreç bir KAYITtır. */
    var beklet = function(y){ return (y.hakemler||[]).some(function(x){ return String(x.rapor||'').trim() === ''; }); };
    var sirali = suzulmus.filter(beklet).concat(suzulmus.filter(function(y){ return !beklet(y); }));
    var SINIR = hkQ ? 30 : 10;
    var gosterilen = sirali.slice(0, SINIR);
    var gizlenen = sirali.length - gosterilen.length;
    var bekleyenCal = suzulmus.filter(beklet).length;

    /* Sessiz kırpma yok. */
    var h = '<p class="pn-ack">'
      + (EN ? (hepsi.length + ' works with reviewers · ' + bekleyenCal + ' awaiting a report')
            : (hepsi.length + ' çalışmada hakem var · ' + bekleyenCal + ' tanesinde rapor bekleniyor'))
      + (gizlenen > 0
          ? (EN ? (' · ' + gizlenen + ' more not shown; use the search box above')
                : (' · ' + gizlenen + ' tanesi gösterilmiyor, yukarıdan arayın'))
          : '')
      + '</p>';
    var bekleyen = 0, hkIlkAcildi = false;
    gosterilen.forEach(function(y){
      var hk = y.hakemler || [];
      if(!hk.length) return;
      var bekliyorMu = beklet(y);
      var acik = bekliyorMu && !hkIlkAcildi;
      if(acik) hkIlkAcildi = true;
      var bekSay = hk.filter(function(x){ return String(x.rapor||'').trim() === ''; }).length;
      h += '<details class="pn-cal"' + (acik ? ' open' : '') + '>'
         + '<summary><h3>' + esc(String(y.baslik||'')) + '</h3>'
         + '<div class="kim">' + esc(String(y.bcid||'')) + '</div>'
         + '<div class="rzler"><span class="rz rz-cizgi">' + hk.length + ' '
         + (EN?'reviewers':'hakem') + '</span>'
         + (bekSay ? ('<span class="rz rz-kut">' + bekSay + ' '
              + (EN?(bekSay===1?'report awaited':'reports awaited'):'rapor bekleniyor') + '</span>') : '')
         + '</div></summary><div class="pn-cal-ic">';
      hk.forEach(function(x){
        var varRapor = String(x.rapor||'').trim() !== '';
        if(!varRapor) bekleyen++;
        h += '<div class="pn-rpr"><div class="pn-rpr-ust"><b>' + esc(String(x.ad||'')) + '</b>'
           + (x.karar ? '<span class="rz rz-cizgi">' + esc(kararAd[x.karar] || x.karar) + '</span>'
                      : '<span class="rz rz-kut">' + (EN?'awaited':'bekliyor') + '</span>')
           + (x.tarih ? '<span class="pn-ack">' + esc(yzZaman(x.tarih)) + '</span>' : '')
           + ((x.raporlar && x.raporlar.length > 1)
                ? '<span class="rz rz-cizgi">' + x.raporlar.length + ' ' + (EN?'versions':'sürüm') + '</span>' : '')
           + '</div>';
        if(varRapor) h += '<p class="pn-rpr-metin">' + esc(String(x.rapor).slice(0, 600)) + '</p>';
        if(x.endeks && x.endeks.length)
          h += '<p class="pn-ack">' + (EN?'Proposed: ':'Önerilen: ')
             + x.endeks.map(function(e){ return '<span class="rz rz-yes">' + esc(e) + '</span>'; }).join(' ') + '</p>';
        if(x.token)
          h += '<div class="pn-anahtar"><b>' + (EN?'Link':'Bağlantı') + ':</b> '
             + esc(location.origin + '/hakem.php?t=' + x.token) + '<br><b>' + (EN?'Password':'Şifre') + ':</b> '
             + esc(String(x.sifre||'')) + '</div>';
        h += '</div>';
      });
      h += '</div></details>';
    });
    kutu.innerHTML = hepsi.length
      ? h
      : '<p class="pn-ack">' + (EN?'No reviewer has been invited yet.':'Henüz hakem daveti yok.') + '</p>';
  }
  if(\$('hkAra')) \$('hkAra').addEventListener('input', function(){ yonHakemCiz(); });

})();
</script>
JS;
k_son($betik);
