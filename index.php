<?php
/* =====================================================================
   KUTADGU - Ana sayfa / Home
   kutadgu.net kökünden .htaccess ile buraya yönlendirilir.

   Düzen ilkesi: ilk ekranda hem ne olduğumuz hem en yeni çalışma hem
   de sayılar aynı anda görünür. Okuyucunun bir şey öğrenmek için
   aşağı inmesi gerekmez; aşağısı ayrıntı içindir, giriş değil.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/parca.php';

$en      = k_en();
$yazilar = k_yazilar();
$say     = k_sayaclar();
$one     = $yazilar[0] ?? null;
$son     = array_slice($yazilar, 1, 4);

/* Çalışma listeleri: aynı kartlar birkaç ayrı ölçüte göre dizilir. Alt
   alta ayrı bölümler yerine tek bölümde sekmeler; sayfa hem kısalır hem
   okurun aradığı sıra elinin altında kalır. */
$enCok = $yazilar;
usort($enCok, fn($a, $b) => k_okuma_sayisi((string)($b['id'] ?? '')) <=> k_okuma_sayisi((string)($a['id'] ?? '')));
$enCok = array_slice($enCok, 0, 4);
/* "Hakemli" sekmesi yalnızca gerçekten hakemden geçmiş çalışmaları
   gösterir. 'tur' alanı çalışmanın hangi yolda olduğunu söyler, o yolun
   neresinde olduğunu değil: hakemliğe açılmış ama tek raporu bile
   gelmemiş bir çalışma bu listeye girseydi, ana sayfa okura yapılmamış
   bir işi yapılmış gibi gösterirdi. */
$hakemli = array_values(array_filter($yazilar, fn($y) => tg_hakemden_gecti($y)));
$hakemli = array_slice($hakemli, 0, 4);
/* Hakem aranan çalışmalar: hakemli yola girmiş ama tek raporu bile
   gelmemiş olanlar. Yukarıdaki süzgeç bunları "Hakemli" sekmesinden
   çıkardı; çıkarmak saklamak değildir. Bugüne kadar gönüllü çağrısı
   yalnızca çalışmanın kendi sayfasında duruyordu, yani o çalışmayı
   zaten bulmuş kişiye görünüyordu. Kendi sekmesi olması çağrıyı
   aramayana da götürür: hakem böyle gelir.
   $yazilar tarihe göre yeniden eskiye sıralı geldiği için ayrıca
   sıralamak gerekmez. */
$aranan = array_values(array_filter($yazilar, fn($y) => tg_hakem_asamasi($y) === 'aranan'));
$aranan = array_slice($aranan, 0, 4);

/* Sekmeler tek dizide kurulur. Çubuk ile bölmeler aynı diziden
   üretilir; böylece biri eklendiğinde ötekini güncellemeyi unutmak
   olanaksızdır ve iki liste hiçbir zaman kaymaz. */
$sekmeler = [
    ['tr' => 'Son yayımlananlar', 'en' => 'Recently published', 'say' => count($son),     'kume' => $son],
    ['tr' => 'En çok okunanlar',  'en' => 'Most read',          'say' => count($enCok),   'kume' => $enCok],
    ['tr' => 'Hakemli',           'en' => 'Peer reviewed',      'say' => count($hakemli), 'kume' => $hakemli],
];
/* "Hakem aranıyor" sekmesi ancak gösterecek çalışma varken açılır.
   Öteki sekmeler boş kalsa da yerinde durur, çünkü onların boşluğu
   yalnızca arşivin genç olduğunu söyler. Bunun boşluğu ise bir çağrının
   karşılıksız kaldığını söylerdi: yanında "0" yazan bir "Hakem aranıyor"
   sekmesi, okura sistemin işlemediğini düşündürür. Oysa burada boşluk
   iyi haberdir; bekleyen çalışma kalmamıştır. İyi haberi kötü görünen
   bir sekmeyle duyurmanın anlamı yok. */
if ($aranan) {
    /* Sekme başlığı da bir rozettir: kart üstündeki rozetle aynı sözü
       söylemezse okur iki ayrı durum olduğunu sanır. Metin tek
       kaynaktan alınıyor. */
    $sekmeler[] = ['tr' => tg_asama_metni('aranan', false), 'en' => tg_asama_metni('aranan', true),
                   'say' => count($aranan), 'kume' => $aranan, 'cagri' => true];
}

$ekBas = k_kart_stil() . <<<CSS
<style>
/* ---- Giriş bölümü ----
   Kurumsal lacivert zemin. Üstünde altı ayrı geleneğin süsleme
   motifinden kurulu ortak zemin deseni, çok düşük görünürlükte. */
.giris{position:relative;overflow:hidden;color:var(--marka-metin);
  background:linear-gradient(135deg,var(--marka) 0%,var(--marka-koyu) 100%)}
.giris::before{content:"";position:absolute;inset:0;pointer-events:none;
  background:url("/k/desen.svg") repeat;background-size:336px 112px;opacity:.06}
.giris::after{content:"";position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(720px 320px at 92% -20%,rgba(201,162,39,.24),transparent 68%)}
.giris-ic{position:relative;z-index:1;padding-block:clamp(26px,3.2vw,44px)}
.giris-dizi{display:grid;gap:clamp(22px,2.6vw,40px);grid-template-columns:minmax(0,1.12fr) minmax(0,.88fr);
  align-items:center}
@media (max-width:960px){.giris-dizi{grid-template-columns:1fr}}
.giris h1{margin:0 0 .34em;max-width:21ch;color:#fff}
.giris-vur{color:var(--altin-ac)}
.giris-ack{font-size:var(--y-6);color:var(--marka-sonuk);max-width:62ch;line-height:1.68;margin:0}
.giris-dg{display:flex;flex-wrap:wrap;gap:var(--b-3);margin-top:var(--b-5)}
.kyd .d-ikinci{background:transparent;color:#fff;border-color:var(--marka-cizgi-2)}
.kyd .d-ikinci:hover{background:var(--marka-yuzey);border-color:#fff}
/* Kaydırağın içinde beyaz bir kart da duruyor (öne çıkan çalışma).
   Yukarıdaki kural lacivert zemin için yazıldı ve o kartın içine de
   sızıyordu: beyaz kartta beyaz yazı kalıyordu. Kart kendi dilini
   konuşur. */
.one .d-ikinci{background:var(--yuzey);color:var(--metin);border-color:var(--cizgi)}
.one .d-ikinci:hover{background:var(--kut-zemin);color:var(--kut);border-color:var(--kut)}
.giris-not{margin:20px 0 0;display:flex;flex-wrap:wrap;gap:6px 18px;font-size:var(--y-3);color:var(--marka-sonuk)}
.giris-not b{color:#fff;font-weight:600}

/* Sayı şeridi: girişin alt kenarına oturur */
.giris-say{display:flex;flex-wrap:wrap;gap:0;margin-top:26px;border-top:1px solid var(--marka-cizgi);
  padding-top:16px}
.giris-say div{flex:1 1 100px;padding-right:16px}
.giris-say b{display:block;font-family:var(--baslik);font-size:var(--y-8);
  line-height:1.1;color:#fff;font-weight:700}
.giris-say span{font-size:var(--y-1);letter-spacing:.1em;text-transform:uppercase;color:var(--marka-sonuk);font-weight:600}

/* Girişin sağındaki öne çıkan çalışma */
.one{background:var(--yuzey);border-radius:var(--r-4);padding:clamp(18px,2vw,24px);
  box-shadow:var(--g-3);color:var(--metin);display:flex;flex-direction:column;gap:11px}
.one-ust{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center}
.one h2{margin:0;font-size:var(--y-7);line-height:1.3}
.one h2 a{color:var(--metin)}
.one h2 a:hover{color:var(--kut);text-decoration:none}
.one-yzr{margin:0;font-size:var(--y-3);color:var(--kut);font-weight:600}
.one-oz{margin:0;font-size:var(--y-3);color:var(--metin-2);line-height:1.62}
.one-alt{display:flex;flex-wrap:wrap;gap:8px 14px;align-items:center;font-size:var(--y-2);color:var(--metin-2);
  border-top:1px solid var(--cizgi);padding-top:11px;margin-top:auto}
.one-bos{background:var(--marka-yuzey);border:1px solid var(--marka-cizgi-2);border-radius:var(--r-4);
  padding:24px;color:var(--marka-sonuk);font-size:var(--y-3)}


/* ---- Kaydırak ----
   Girişte üç levha döner: ne yaptığımız, kurumsal işaret ve adın
   anlamı. Kaydırma yakalamalı (scroll-snap) bir şerittir; betik
   çalışmasa da parmakla ya da klavyeyle kaydırılabilir, hiçbir levha
   erişilmez kalmaz. Kendiliğinden ilerleme, imleç üstündeyken,
   odaklanıldığında ve hareket azaltma tercihinde durur. */
.kyd{position:relative;overflow:hidden;color:var(--marka-metin);
  background:linear-gradient(135deg,var(--marka) 0%,var(--marka-koyu) 100%)}
.kyd::before{content:"";position:absolute;inset:0;pointer-events:none;
  background:url("/k/desen.svg") repeat;background-size:336px 112px;opacity:.06}
.kyd::after{content:"";position:absolute;inset:0;pointer-events:none;
  background:radial-gradient(720px 320px at 92% -20%,rgba(201,162,39,.22),transparent 68%)}
html[data-tema="koyu"] .kyd::before{opacity:.04}
.kyd-ray{position:relative;z-index:1;display:flex;overflow-x:auto;scroll-snap-type:x mandatory;
  scroll-behavior:smooth;scrollbar-width:none;-ms-overflow-style:none}
.kyd-ray::-webkit-scrollbar{display:none}
@media (prefers-reduced-motion:reduce){.kyd-ray{scroll-behavior:auto}}
.kyd-s{flex:0 0 100%;scroll-snap-align:start;min-width:0}
.kyd-ic{padding-block:clamp(26px,3.2vw,44px)}

/* Levha düzeni: solda söz, sağda görsel */
.kyd-dizi{display:grid;gap:clamp(22px,2.6vw,40px);grid-template-columns:minmax(0,1.12fr) minmax(0,.88fr);
  align-items:center;min-height:clamp(330px,34vw,430px)}
@media (max-width:960px){.kyd-dizi{grid-template-columns:1fr;min-height:0}}

/* Alt şerit: noktalar ve oklar */
.kyd-alt{position:relative;z-index:2;display:flex;align-items:center;gap:10px;
  padding-bottom:16px;padding-top:2px}
/* Noktalar görünürde küçüktür ama dokunulacak alan küçük değildir.
   Ölçüldü: nokta 9x9 pikseldi. Parmakla 9 piksellik bir hedefe basmak
   deneme yanılmadır; standartlar en az 24, telefon kılavuzları 44 der.
   Nokta görsel olarak 9 piksel kalır, çevresindeki saydam dolgu hedefi
   40 piksele çıkarır. Küçük görünen bir düğme, küçük olmak zorunda
   değildir. */
.kyd-nokta{display:flex;gap:0}
.kyd-nokta button{width:40px;height:40px;padding:0;border:0;background:transparent;
  cursor:pointer;display:grid;place-items:center;-webkit-tap-highlight-color:transparent}
.kyd-nokta button::before{content:"";display:block;width:9px;height:9px;border-radius:99px;
  background:rgba(255,255,255,.3);transition:var(--gecis)}
.kyd-nokta button[aria-current="true"]::before{background:var(--altin);width:26px}
.kyd-nokta button:hover::before{background:rgba(255,255,255,.55)}
.kyd-ok{margin-left:auto;display:flex;gap:var(--b-2)}
.kyd-ok button{width:40px;height:40px;display:grid;place-items:center;border-radius:50%;cursor:pointer;
  border:1px solid rgba(255,255,255,.22);background:var(--marka-yuzey);color:#fff}
.kyd-ok button:hover{background:var(--marka-cizgi-2)}

/* Levha 2: kurumsal işaret */
.kmk{grid-column:1 / -1;display:flex;flex-direction:column;align-items:center;text-align:center;gap:14px;
  padding-block:8px}
.kmk-im{width:clamp(104px,12vw,168px);height:auto}
.kmk-ad{font-family:var(--baslik);font-size:clamp(2rem,1.1rem + 3.4vw,3.5rem);font-weight:700;
  letter-spacing:.22em;line-height:1;color:#fff;margin:0;padding-left:.22em}
/* İşaret aynı zamanda kimlik sayfasının kapısıdır: tıklayan kişi
   dosyaların tamamını indirebileceği sayfaya gider. */
.kmk-bag{display:flex;flex-direction:column;align-items:center;gap:12px;color:inherit;text-decoration:none;
  padding:var(--b-3) var(--b-4);border-radius:var(--r-4);border:1px solid transparent;transition:var(--gecis)}
.kmk-bag:hover{text-decoration:none;background:rgba(255,255,255,.05);border-color:var(--marka-cizgi-2)}
.kmk-in{display:inline-flex;align-items:center;gap:var(--b-2);font-size:var(--y-2);font-weight:700;letter-spacing:.1em;
  text-transform:uppercase;color:var(--altin-ac);opacity:.85}
.kmk-bag:hover .kmk-in{opacity:1}
/* Kelime işaretindeki A, markanın kendi harfidir; kim_harf_a() basar.
   Yüksekliği büyük harf boyuna oturur, harf aralığı sözcükle aynıdır. */
/* Harf aralığı (.22em) tarayıcıda gömülü ögeden SONRA uygulanmıyor;
   bu yüzden A'nın sağı dar, solu geniş kalıyordu. İki yan burada elle
   dengeleniyor: ölçüldü, iki boşluk da .17em'e getirildi. */
.kmk-ad .kim-a{height:.72em;width:auto;display:inline-block;vertical-align:baseline;
  margin-left:0;margin-right:.188em}
.kmk-cizgi{display:flex;align-items:center;gap:10px;width:min(560px,86%)}
.kmk-cizgi i{flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--altin),transparent)}
.kmk-cizgi b{width:6px;height:6px;border-radius:50%;background:var(--altin);flex:none}
.kmk-dil{display:flex;flex-wrap:wrap;justify-content:center;gap:6px 18px;max-width:74ch;margin:0}
.kmk-dil span{font-size:var(--y-2);letter-spacing:.05em;color:var(--marka-sonuk);line-height:1.6}
.kmk-dil b{color:var(--altin-ac);font-weight:600;margin-right:5px;font-size:var(--y-1);letter-spacing:.1em;
  text-transform:uppercase}

/* Levha 3: ad ve tamga */
.kad-gorsel{display:flex;flex-direction:column;gap:12px;align-items:center;
  background:rgba(255,255,255,.06);border:1px solid var(--marka-cizgi);
  border-radius:var(--r-4);padding:22px}
.kad-gorsel .tam-ornek{background:rgba(0,0,0,.24);border-color:rgba(255,255,255,.18);color:var(--altin-ac);
  margin:0;width:100%;text-align:center}
.kad-gorsel small{font-size:var(--y-2);color:var(--marka-sonuk);text-align:center;line-height:1.55}
/* Tamganın parçaları. Kodun altında, her parça kendi adıyla. Tek
   sütun: iki sütuna bölmek, okunacak sırayı belirsiz bırakıyordu. */
.tam-parca{list-style:none;margin:0;padding:0;display:grid;gap:6px;width:100%}
.tam-parca li{display:grid;grid-template-columns:auto auto 1fr;align-items:baseline;gap:8px;
  font-size:var(--y-2);line-height:1.45}
.tam-parca code{font-family:var(--mono);color:var(--altin-ac);
  background:rgba(0,0,0,.24);border-radius:var(--r-1);padding:1px 6px}
.tam-parca b{color:#fff;font-weight:600}
.tam-parca span{color:var(--marka-sonuk)}


/* ---- Kavram şeridi ----
   Kurumsal kimliğin altı kavramı. Sayfanın en üstünde, girişin hemen
   altında: sistemin neyi savunduğu bir bakışta okunur. */
.kav{background:var(--yuzey);border-bottom:1px solid var(--cizgi)}
.kav-ic{display:grid;grid-template-columns:repeat(6,1fr);gap:1px;background:var(--cizgi)}
@media (max-width:1180px){.kav-ic{grid-template-columns:repeat(3,1fr)}}
@media (max-width:640px){.kav-ic{grid-template-columns:repeat(2,1fr)}}
@media (max-width:400px){.kav-ic{grid-template-columns:1fr}}
.kav-ic a{background:var(--yuzey);display:flex;flex-direction:column;gap:var(--b-2);
  padding:var(--b-4);color:var(--metin);text-decoration:none;transition:var(--gecis)}
.kav-ic a:hover{background:var(--kut-zemin);text-decoration:none}
/* Eylem şeridi: kavram ızgarasının altında, aynı bandın parçası.
   Izgaraya dokunulmadı; yedinci bir hücre bütün satırı bozardı. */
.kav-eylem-kap{background:var(--yuzey)}
.kav-eylem{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;
  gap:var(--b-3) var(--b-5);padding:var(--b-4) 0;border-top:1px solid var(--cizgi)}
.kav-eylem-yazi{min-width:0;flex:1 1 320px}
.kav-eylem-yazi b{display:block;font-size:var(--y-5);line-height:1.3}
.kav-eylem-yazi span{display:block;font-size:var(--y-3);color:var(--metin-2);margin-top:2px;
  max-width:62ch}
.kav-eylem-dg{display:flex;flex-wrap:wrap;gap:var(--b-2);flex:0 0 auto}
@media(max-width:640px){ .kav-eylem-dg{width:100%} .kav-eylem-dg .d{flex:1 1 100%} }
.kav-im{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;
  background:var(--lacivert-zemin);color:var(--lacivert)}
.kav-ic a:hover .kav-im{background:var(--kut);color:#fff}
.kav-ad{font-size:var(--y-1);font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:var(--kut)}
.kav-ack{font-size:var(--y-3);color:var(--metin-2);line-height:1.5}

/* ---- Son çalışmalar ---- */
.son-ust{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-bottom:18px}
.son-dizi{grid-template-columns:repeat(auto-fit,minmax(272px,1fr))}

/* ---- Hakem çağrısı ----
   Ölçüler yazi.php'deki hakem-cagri kutusundan alındı: aynı çağrı iki
   ayrı sayfada başka görünmesin. Düğme dar ekranda alta iner, çünkü
   yan yana sıkıştığında ya metni kırılıyor ya da cümlenin satırı iki
   sözcüğe düşüyordu. */
.cgr{display:flex;flex-wrap:wrap;gap:var(--b-4);align-items:center;margin-top:var(--b-5)}
.cgr > div{flex:1 1 260px}
.cgr b{display:block;font-size:var(--y-4);margin-bottom:var(--b-1)}
.cgr span{display:block;font-size:var(--y-3);color:var(--metin-2);
  line-height:var(--sh-genis);max-width:60ch}

/* ---- İki yol ---- */
.yol-dizi{display:grid;gap:16px;grid-template-columns:repeat(auto-fit,minmax(300px,1fr))}
.yol{display:flex;flex-direction:column;gap:var(--b-3);border-top:3px solid var(--yesil)}
.yol.yol-b{border-top-color:var(--lacivert)}
.yol h3{margin:0;display:flex;align-items:center;gap:var(--b-3)}
.yol ol{margin:0;padding-left:19px;display:grid;gap:8px;font-size:var(--y-3);color:var(--metin-2)}
.yol ol b{color:var(--metin);font-weight:600}
.yol .sure{font-size:var(--y-2);color:var(--metin-2);margin:auto 0 0;padding-top:var(--b-3);border-top:1px solid var(--cizgi)}
/* Kartlar tıklanabilir DEĞİL, o yüzden hareket de yok: yükselen ama
   basılamayan bir kart, olmayan bir bağ vaat eder. Kazanılan tek şey
   gölge — kart yüzeyden ayrılsın, sayfanın geri kalanıyla aynı dilde
   dursun. Üstteki üç piksellik renk zaten hangi yol olduğunu söylüyor. */
.yol{box-shadow:var(--g-1)}

/* ---- Farkımız ---- */
.fark{display:grid;gap:1px;background:var(--cizgi);border:1px solid var(--cizgi);
  border-radius:var(--r-3);overflow:hidden;grid-template-columns:repeat(3,1fr)}
@media (max-width:900px){.fark{grid-template-columns:repeat(2,1fr)}}
@media (max-width:560px){.fark{grid-template-columns:1fr}}
.fark article{background:var(--yuzey);padding:var(--b-5);display:flex;flex-direction:column;gap:var(--b-2);
  transition:background var(--gecis)}
/* Üzerine gelindiğinde hücre aydınlanır ve simgesi doluya döner.
   Hücreler bağ değil, bilgi; bu yüzden yükselmez, yalnız okunduğu yer
   belli olur. Dokunmatik ekranda hiç görünmez ve orada gerekmez de. */
.fark article:hover{background:var(--yuzey-2)}
.fark article:hover .im{background:var(--kut);color:var(--kut-dolu-metin)}
.fark h3{margin:0;font-size:var(--y-5)}
.fark p{margin:0;font-size:var(--y-3);color:var(--metin-2);line-height:1.6}
.fark .im{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;
  background:var(--kut-zemin);color:var(--kut);margin-bottom:2px;
  transition:background var(--gecis),color var(--gecis)}

/* ---- Dürüstlük ve tamga yan yana ---- */
.ikili{display:grid;gap:16px;grid-template-columns:minmax(0,1.3fr) minmax(0,1fr);align-items:start}
@media (max-width:960px){.ikili{grid-template-columns:1fr}}
.durust{display:grid;gap:14px;grid-template-columns:1fr 1fr}
@media (max-width:640px){.durust{grid-template-columns:1fr}}
.durust ul{margin:8px 0 0;padding-left:17px;display:grid;gap:6px;font-size:var(--y-3);color:var(--metin-2);line-height:1.55}
.durust h3{margin:0;font-size:var(--y-5)}
.tam-ornek{font-family:var(--mono);font-size:var(--y-3);background:var(--yuzey-2);border:1px dashed var(--cizgi);
  border-radius:var(--r-2);padding:var(--b-3);overflow-x:auto;color:var(--kut);margin:var(--b-3) 0}

/* ---- Kapanış ---- */
/* Kapanış: sayfanın son sözü. Üstündeki ince altın şerit onu bir
   bölüm değil bir SON yapar; aynı şerit kişi kartında ve hakem raporu
   kartında da var, üçü aynı dili konuşuyor. */
.kapan{position:relative;overflow:hidden;text-align:center;background:var(--yuzey);
  border:1px solid var(--cizgi);border-radius:var(--r-4);
  padding:clamp(24px,3.6vw,42px);box-shadow:var(--g-1)}
.kapan::before{content:"";position:absolute;inset-inline:0;inset-block-start:0;height:3px;
  background:var(--kut)}
.kapan p{max-width:58ch;margin-inline:auto;color:var(--metin-2)}
</style>
CSS;

k_bas([
    'olcu'   => 'tam',
    'baslik' => '',
    'yol' => '/',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu, hakemli ve hakemsiz akademik çalışmaların açık erişimle yayımlandığı bağımsız bir yayın sistemidir. Hakemler adlarıyla durur, raporlar herkese açıktır.',
        'Kutadgu is an independent publishing system for open access academic work, with and without peer review. Reviewers sign their reports and every report is public.'
    ),
]);
?>

<!-- ================= KAYDIRAK ================= -->
<section class="kyd" aria-roledescription="<?= k_c('kaydırak', 'carousel') ?>"
         aria-label="<?= k_c('Kutadgu tanıtımı', 'About Kutadgu') ?>">
  <div class="kyd-ray" id="kydRay" tabindex="0">

    <article class="kyd-s" aria-roledescription="<?= k_c('levha', 'slide') ?>"
             aria-label="1 / 3 <?= k_c('Ne yapıyoruz', 'What we do') ?>">
      <div class="kap kyd-ic">
        <div class="kyd-dizi">
          <div>
      <span class="bas-ust" style="color:var(--altin)"><?= k_c('Açık erişim · Açık hakemlik', 'Open access · Open peer review') ?></span>
      <h1><?= k_c('Bilgi, ', 'Knowledge that ') ?><span class="giris-vur"><?= k_c('saklandığında değil paylaşıldığında', 'earns its worth when shared') ?></span><?= k_c(' değer kazanır.', ', not when withheld.') ?></h1>
      <?php /* Cümle iki hâl sayıyordu: hakemli ve hakemsiz. Oysa hâl
               üçtür; hakemli yola girmiş ama daha tek raporu gelmemiş
               çalışma ikisine de girmiyordu ve tanıtım metninde yeri
               yoktu. Söylenen şey büyütülmedi, yalnızca eksik olan hâl
               de sayıldı. */ ?>
      <p class="giris-ack"><?= k_c(
        'Akademik çalışmaların ücretsiz, engelsiz ve gecikmesiz yayımlandığı bağımsız bir sistem. Hakemli çalışmalarda raporlar adıyla yayımlanır; ilk raporu henüz gelmemiş çalışmalar ve hakemsiz yazılar açıkça işaretlenir. Hiçbir bilim dalıyla sınırlı değildir.',
        'An independent system where academic work is published free of charge, without barriers and without delay. For peer reviewed work, reports are published with the reviewer\'s name; work still awaiting its first report and non reviewed pieces are clearly marked. It is not limited to any single discipline.'
      ) ?></p>
      <?php /* ÇALIŞMA GÖNDER BURADAN KALDIRILDI ve kavram şeridinin
               sonuna, SABİT bir yere alındı. Sebebi ölçülebilir:
               düğme üç ayrı yerde duruyordu (birinci levha, üçüncü
               levha, aşağıdaki kapanış) ve üçü de KAYAN bir şeridin
               içindeydi — okur ikinci levhaya geçtiği anda eylem
               ekrandan çıkıyordu. Kayan bir yüzeydeki çağrı, orada
               olmadığı zamanlarda hiç yoktur.

               Levhada okuma bağı kaldı: birinci levhanın işi arşivi
               tanıtmaktır, gönderim çağrısı değil. */ ?>
      <div class="giris-dg">
        <a class="d d-vurgu" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Çalışmalara göz at', 'Browse the works') ?></a>
        <a class="d d-ikinci" href="<?= k_esc(k_bag('/nasil-isler.php')) ?>"><?= k_c('Nasıl işler', 'How it works') ?></a>
      </div>
      <p class="giris-not">
        <span><b><?= k_c('Ücret yok.', 'No fees.') ?></b> <?= k_c('Ne yazardan ne okurdan.', 'Neither from authors nor readers.') ?></span>
        <span><b><?= k_c('Reklam yok.', 'No ads.') ?></b></span>
        <span><b><?= k_c('Kalıcı kimlik.', 'A permanent identifier.') ?></b> <?= k_c('Her çalışmaya.', 'For every work.') ?></span>
      </p>
      <?php /* Sayaçlar kısa biçimde yazılır: sayı büyüdüğünde şerit
               bozulmasın ve okuma sayısı olduğundan önemli görünmesin.
               Tam sayı title özniteliğinde durur.

               $say['aranan'] burada BİLEREK yok. Şerit dört sayıya göre
               ölçülmüş: 1440'ta her göz 153, 820'de 193 piksel ve etiketler
               tek satır. Beşinci sayı eklenince gözler 1440'ta 122,
               820'de 154 piksele iniyor ve etiket sığmıyor; "hakem
               aranıyor" 1440'ta, "seeking reviewers" iki genişlikte de
               ikinci satıra kırılıyor, şeridin yüksekliği 79'dan 105'e
               (820'de 74'ten 100'e) çıkıyor. Tek satırda kalan biçim
               yalnızca "aranan" gibi tek sözcük ki o da neyin arandığını
               söylemiyor.
               Ölçünün ötesinde: bu şeritteki dört sayı da var olanı
               sayar. "Hakem aranıyor" ise var olanı değil eksik olanı
               söyler ve bir çağrıdır; çağrının yeri, karşılığında bir
               şey yapılabilecek yerdir. Aşağıdaki sekme ile onun
               altındaki kutu tam olarak orasıdır. */ ?>
      <div class="giris-say">
        <div title="<?= k_esc(k_sayi($say['toplam'])) ?>"><b><?= k_esc(k_sayi_kisa($say['toplam'])) ?></b><span><?= k_c('çalışma', 'works') ?></span></div>
        <div title="<?= k_esc(k_sayi($say['hakemli'])) ?>"><b><?= k_esc(k_sayi_kisa($say['hakemli'])) ?></b><span><?= k_c('hakemli', 'reviewed') ?></span></div>
        <div title="<?= k_esc(k_sayi($say['yazar'])) ?>"><b><?= k_esc(k_sayi_kisa($say['yazar'])) ?></b><span><?= k_c('yazar', 'authors') ?></span></div>
        <div title="<?= k_esc(k_sayi($say['okuma'])) ?>"><b><?= k_esc(k_sayi_kisa($say['okuma'])) ?></b><span><?= k_c('okuma', 'reads') ?></span></div>
      </div>
    </div>

    <?php if ($one):
      $oBas  = k_alan($one, 'baslik');
      $oYzr  = k_yazarlar($one);
      $oOzet = k_ozet(k_alan($one, 'ozet') !== '' ? k_alan($one, 'ozet') : k_alan($one, 'metin'), 210);
      /* Öne çıkarılan çalışmanın rozeti basamağı söyler: "Hakemli"
         yazan bir rozet, henüz tek raporu gelmemiş bir çalışmanın
         üstünde durduğunda okura verilmiş yanlış bir sözdür. */
      $oAs   = tg_hakem_asamasi($one);
      $oTam  = k_tamga($one);
      $oOku  = k_okuma_sayisi((string)($one['id'] ?? ''));
    ?>
    <article class="one">
      <div class="one-ust">
        <span class="rz rz-kut"><?= k_c('En yeni', 'Latest') ?></span>
        <span class="rz <?= k_esc(tg_asama_rz($oAs)) ?>"><span class="nokta"></span><?= k_esc(tg_asama_metni($oAs, (bool)$en)) ?></span>
      </div>
      <h2><a href="<?= k_esc(k_bag(k_yazi_yolu($one))) ?>"><?= k_esc($oBas) ?></a></h2>
      <?php if ($oYzr !== ''): ?><p class="one-yzr"><?= k_esc($oYzr) ?></p><?php endif; ?>
      <?php if ($oOzet !== ''): ?><p class="one-oz"><?= k_esc($oOzet) ?></p><?php endif; ?>
      <div class="one-alt">
        <span><?= k_esc(k_tarih((string)($one['tarih'] ?? ''))) ?></span>
        <?php if ($oTam['kod'] !== ''): ?><span class="rz rz-cizgi"><?= k_esc($oTam['kod']) ?></span><?php endif; ?>
        <?php if ($oOku > 0): ?><span title="<?= k_esc(k_sayi($oOku)) ?>"><?= k_esc(k_sayi_kisa($oOku)) ?> <?= k_c('okuma', 'reads') ?></span><?php endif; ?>
        <a class="d d-ikinci d-kucuk d-git ara-oto" href="<?= k_esc(k_bag(k_yazi_yolu($one))) ?>"><?= k_c('Tam metni oku', 'Read in full') ?></a>
      </div>
    </article>
    <?php else: ?>
    <div class="one-bos"><?= k_c(
      'İlk çalışmalar yolda. Arşiv açıldığında en yeni çalışma burada görünecek.',
      'The first works are on their way. When the archive opens, the latest work will appear here.'
    ) ?></div>
    <?php endif; ?>
        </div><!-- /kyd-dizi -->
      </div><!-- /kyd-ic -->
    </article>

    <article class="kyd-s" aria-roledescription="<?= k_c('levha', 'slide') ?>"
             aria-label="2 / 3 <?= k_c('Kurumsal işaret', 'The mark') ?>">
      <div class="kap kyd-ic">
        <div class="kyd-dizi">
          <div class="kmk">
            <a class="kmk-bag" href="<?= k_esc(k_bag('/kimlik.php')) ?>"
               title="<?= k_c('Kurumsal kimlik sayfası: bütün işaret dosyaları indirilebilir',
                              'Brand page: every version of the mark is available to download') ?>">
              <?= kim_isaret(168, 'ters', 'kmk-im') ?>
              <p class="kmk-ad">KUT<?= kim_harf_a() ?>DGU</p>
              <span class="kmk-in"><?= kim_ikon('damga', 15) ?><?= k_c('İşareti ve simgeleri indir', 'Download the mark and icons') ?></span>
            </a>
            <span class="kmk-cizgi"><i></i><b></b><i></i></span>
            <p class="kmk-dil">
              <?php foreach (kim_sloganlar() as $dk => $sl): ?>
                <span><b><?= k_esc(strtoupper($dk)) ?></b><?= k_esc($sl) ?></span>
              <?php endforeach; ?>
            </p>
            <p style="max-width:66ch;color:var(--marka-sonuk);font-size:var(--y-3);line-height:1.68;margin:4px 0 0">
              <?= k_c(
                'İşaret üç şeyi bir arada söyler: küre yeryüzünü ve sınırsız erişimi, açık kitap bilgiyi, kollarını açmış figür ise onu yazan ve arkasında duran insanı. Figürün gövdesi bir kalem ucudur. Küreyi çevreleyen ayrı renkteki noktalar ayrı ayrı kültürlerdir; hepsi aynı kürenin çevresinde durur.',
                'The mark says three things at once: the globe is the earth and unbounded access, the open book is knowledge, and the figure with open arms is the person who writes it and stands behind it. The figure\'s body is the nib of a pen. The differently coloured points around the globe are distinct cultures; all of them stand around the same globe.'
              ) ?>
            </p>
          </div>
        </div>
      </div>
    </article>

    <article class="kyd-s" aria-roledescription="<?= k_c('levha', 'slide') ?>"
             aria-label="3 / 3 <?= k_c('Adın anlamı', 'What the name means') ?>">
      <div class="kap kyd-ic">
        <div class="kyd-dizi">
          <div>
            <span class="bas-ust" style="color:var(--altin)"><?= k_c('Ad ve tamga', 'Name and tamga') ?></span>
            <h2 style="color:#fff;font-size:var(--y-8);margin-bottom:.4em">
              <?= k_c('Kut veren bilgi', 'Knowledge that brings fortune') ?></h2>
            <p class="giris-ack" style="margin-bottom:14px"><?= k_c(
              'Ad, 1069 yılında Yusuf Has Hacib\'in yazdığı Kutadgu Bilig\'den gelir: kut veren, insanı mutluluğa eriştiren bilgi. Bu sistemin ölçüsü de odur; bilginin kime ulaştığı, kimin elinde tutulduğundan önemlidir.',
              'The name comes from the Kutadgu Bilig, written by Yusuf Has Hacib in 1069: knowledge that brings fortune and wellbeing. That is the measure here too; who the knowledge reaches matters more than who holds it.'
            ) ?></p>
            <p class="giris-ack"><?= k_c(
              'Tamga ise Türk boylarının kendilerine ait olanı işaretlemek için kullandığı damgadır. Burada her çalışmaya devredilemez bir işaret verilir. İşlevi DOI\'ye benzer, ama DOI değildir ve DOI yerine geçmez.',
              'A tamga is the mark Turkic clans used to sign what belonged to them. Here every work receives a mark of its own. Its function resembles a DOI, but it is not a DOI and does not replace one.'
            ) ?></p>
            <?php /* Bu levhanın işi ADI ve TAMGAYI anlatmaktır; gönderim
                     çağrısı kavram şeridinin sonunda, kaymayan bir
                     yerde duruyor. Bir çağrıyı üç yere koymak onu üç
                     kat görünür yapmaz; hangisinin asıl olduğunu
                     belirsizleştirir. */ ?>
            <div class="giris-dg">
              <a class="d d-ikinci" href="<?= k_esc(k_bag('/ilkeler.php#tamga')) ?>"><?= k_c('Tamga nasıl işler', 'How the tamga works') ?></a>
              <a class="d d-ikinci" href="<?= k_esc(k_bag('/bildiri.php')) ?>"><?= k_c('Bildiriyi oku', 'Read the declaration') ?></a>
            </div>
          </div>
          <?php /* ---------- TAMGA NASIL KURULUYOR ----------
                   BİLDİRİLEN İSTEK: "oluşturulan simgelerin nasıl
                   oluşturulduğu da mı gösterilse."

                   Doğru bir istek: sağdaki kutu bir tamga GÖSTERİYOR
                   ama okur onun neden o biçimde olduğunu bilmiyordu.
                   Bir işaretin güveni, nasıl kurulduğunun görülmesine
                   bağlıdır — bu sistemin bütün savı da bu.

                   Dört parça ayrı ayrı adlandırıldı; sayılar uydurma
                   DEĞİL, gerçek üreticiden geliyor: ön ek ve yol
                   ayar.php'den, yıl date()'ten, denetim hanesi ise
                   tg_denetim() ile HESAPLANIYOR. Ekranda gösterilen
                   örneğin, sistemin gerçekten üreteceği koddan başka
                   olması, bu bölümün anlatmak istediği şeyi çürütürdü. */ ?>
          <?php
            $tmOn   = (string)tg_ayar('tamga_on', 'KTG');
            $tmYil  = date('Y');
            $tmSira = '00001';
            $tmDen  = tg_denetim($tmYil . $tmSira);
            $tmKok  = preg_replace('#^https?://#', '', tg_kok());
            $tmYol  = (string)tg_ayar('tamga_yol', 'tamga');
            $tmParca = [
              [$tmOn,   k_c('ön ek', 'prefix'),        k_c('Sistemi gösterir; değişmez.', 'Identifies the system; never changes.')],
              [$tmYil,  k_c('yıl', 'year'),            k_c('Kaydın açıldığı yıl.', 'The year the record was opened.')],
              [$tmSira, k_c('sıra', 'sequence'),       k_c('Bir kez verilir, geri alınmaz.', 'Given once, never reissued.')],
              [$tmDen,  k_c('denetim', 'check digit'), k_c('Yanlış yazılan kodu yakalar.', 'Catches a mistyped code.')],
            ];
          ?>
          <div class="kad-gorsel">
            <?= kim_monogram(74, '', true) ?>
            <div class="tam-ornek"><?= k_esc($tmKok) ?>/<?= k_esc($tmYol) ?>/<?= k_esc($tmOn . '-' . $tmYil . '-' . $tmSira . '-' . $tmDen) ?></div>
            <ul class="tam-parca" aria-label="<?= k_c('Tamga nasıl kurulur', 'How a tamga is built') ?>">
              <?php foreach ($tmParca as $pk): ?>
              <li><code><?= k_esc($pk[0]) ?></code><b><?= k_esc($pk[1]) ?></b><span><?= k_esc($pk[2]) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <small><?= k_c(
              'Her çalışmanın kalıcı adresi bu biçimdedir. Adres değişse de tamga değişmez.',
              'Every work has a permanent address in this form. The address may change; the tamga does not.'
            ) ?></small>
          </div>
        </div>
      </div>
    </article>

  </div>

  <div class="kap kyd-alt">
    <div class="kyd-nokta" id="kydNokta" role="tablist" aria-label="<?= k_c('Levhalar', 'Slides') ?>"></div>
    <div class="kyd-ok">
      <button type="button" id="kydGeri" aria-label="<?= k_c('Önceki levha', 'Previous slide') ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 6-6 6 6 6"/></svg>
      </button>
      <button type="button" id="kydIleri" aria-label="<?= k_c('Sonraki levha', 'Next slide') ?>">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>
      </button>
    </div>
  </div>
</section>

<!-- ================= KAVRAMLAR ================= -->
<section class="kav" aria-label="<?= k_c('Kutadgu neyi savunur', 'What Kutadgu stands for') ?>">
  <div class="kap"><div class="kav-ic">
    <?php foreach (kim_kavramlar() as $k): ?>
      <a href="<?= k_esc(k_bag((string)$k['yol'])) ?>">
        <span class="kav-im"><?= kim_ikon($k['im'], 19) ?></span>
        <span class="kav-ad"><?= k_esc(k_t($k)) ?></span>
        <span class="kav-ack"><?= k_esc(k_t(['tr' => $k['ack_tr'], 'en' => $k['ack_en']])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
  <?php /* ---------- SABİT EYLEM ŞERİDİ ----------
           KURUL İSTEĞİ: "bu düğmeyi ana sayfada sabit bir yere koyalım,
           slayttakileri kaldıralım; Yeryüzü · Bilgi · Yayın yazan yerin
           başına ya da sonuna."

           SONUNA KONDU, İÇİNE DEĞİL. Şerit altı sütunluk bir ızgaradır;
           yedinci bir hücre eklemek bütün satırı bozar ve dar ekranda
           düğme kavramların arasına sıkışırdı. Şeridin ALTINA, aynı
           yüzeyin üstünde, kenardan kenara bir satır olarak kondu: aynı
           bandın parçası gibi okunur, ızgaraya dokunmaz.

           KAYMAYAN BİR YER OLMASI ASIL MESELE. Düğme daha önce kayan
           levhaların içindeydi; okur ikinci levhaya geçtiği anda eylem
           ekrandan çıkıyordu. Kayan bir yüzeydeki çağrı, orada olmadığı
           zamanlarda hiç yoktur.

           İKİNCİ BİR BAĞ DA VAR ve bilerek: "gönder" diyen bir çağrının
           yanında "önce nasıl işlediğini oku" demek, kimseyi bilmediği
           bir sürece itmemektir. */ ?>
  <div class="kap kav-eylem-kap">
    <div class="kav-eylem">
      <div class="kav-eylem-yazi">
        <b><?= k_c('Çalışmanızı gönderin', 'Send us your work') ?></b>
        <span><?= k_c('Ücret yok, sayı beklemek yok. Hazır olan yayımlanır; her çalışmaya kalıcı bir tamga verilir.', 'No fees, no waiting for an issue. What is ready is published, and every work receives a permanent tamga.') ?></span>
      </div>
      <div class="kav-eylem-dg">
        <?php /* Sayfanın var oluş sebebi olan tek eylem. Lacivert yüzey +
                 altın çerçeve; ölçüm ve gerekçe k/kutadgu.css 5.1'dedir. */ ?>
        <a class="d d-vurgu d-buyuk" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Çalışma gönder', 'Submit a work') ?></a>
        <a class="d d-ikinci" href="<?= k_esc(k_bag('/nasil-isler.php')) ?>"><?= k_c('Önce nasıl işlediğine bakın', 'See how it works first') ?></a>
        <?php /* BİLDİRİ BURAYA DA KONDU (kurul isteği, 14 Ağustos 2026):
                 "bildiri çok gözükmüyor gibi". Doğruydu: bildiriye giden
                 tek kapı sol sütunun dibindeydi ve dar ekranda o sütun
                 kapalı duruyor — yani telefondan bakan biri sistemin
                 kuruluş metnini hiç görmüyordu.

                 SOL SÜTUNDAKİ İKİ SATIRLI KART BURAYA OLDUĞU GİBİ
                 TAŞINMADI. Bir düğme sırasında iki satırlı bir kart,
                 sıranın hizasını bozar ve yanındakileri ortadan aşağı
                 iter. Ayırt edici olan şey yazının ikinci satırı değil,
                 MONOGRAMdır; o alındı, satır sayısı bir kaldı. */ ?>
        <a class="d d-ikinci d-bldr" href="<?= k_esc(k_bag('/bildiri.php')) ?>"><?= kim_monogram(20, 'd-bldr-im') ?><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></a>
      </div>
    </div>
  </div>
  </div>
</section>

<!-- ================= SON ÇALIŞMALAR ================= -->
<?php if ($yazilar): ?>
<section class="bolum" id="son">
  <div class="kap">
    <div class="son-ust">
      <div>
        <span class="bas-ust"><?= k_c('Arşiv', 'Archive') ?></span>
        <h2 style="margin:0"><?= k_c('Arşivden', 'From the archive') ?></h2>
      </div>
      <a class="d d-ikinci d-kucuk ara-oto" href="<?= k_esc(k_bag('/yazilar.php')) ?>"><?= k_c('Tümünü gör', 'See all') ?> (<?= k_esc(k_sayi($say['toplam'])) ?>)</a>
    </div>

    <?php /* BİLİM DALLARI SATIRI.
             KURUL İSTEĞİ (M. Z. Tunca, iki kez): "ana sayfada bilim
             dalları bazında çalışmaları görsek."

             ÜÇ KURALLA ÇİZİLİR:

             1. YALNIZ DOLU ALANLAR. Boş bir alan adı, olmayan bir
                arşivi varmış gibi gösterir; okur tıklar ve boş liste
                bulur. Sayım kayıtların üzerinden anlık yapılır.
             2. SAYIYLA. Alan adı tek başına bir vaat, sayıyla birlikte
                bir olgudur. Kaç çalışma olduğunu gizleyip yalnızca
                başlığı göstermek, arşivi olduğundan büyük gösterir.
             3. ARŞİVİN İÇİNDE. Ayrı bir bölüm açılmadı: bu bir gezinme
                aracıdır, sayfanın kendi başına bir bölümü değil. Ayrı
                bölüm açmak, ana sayfayı bir dizin sayfasına çevirirdi.

             Bir çalışma birden çok ana alana bağlıysa her birinin altında
             sayılır; toplam, çalışma sayısından büyük olabilir ve olmalıdır
             da — aradisipliner bir çalışmayı tek alana hapsetmek onu
             arayanlardan gizler. */ ?>
    <?php
      /* ÖLÇÜLEN KUSUR: ilk yazımda tekil 'alan' okunuyordu ve satır her
         zaman boş çıkıyordu. Sebebi kayıtta: tekil 'alan' ESKİ kısa
         koddur (sag, fen, sos...) ve yalnız dizin önerileri için
         tutulur; FORD kodları çoğul 'alanlar' dizisindedir. Yanlış
         alanı okuyan bir sayaç, sıfır gösterip hata vermez — en sinsi
         kusur budur, çünkü ekranda boş bir liste doğru görünür. */
      $alanSay = [];
      foreach ($yazilar as $__y) {
          $__kodlar = is_array($__y['alanlar'] ?? null) ? $__y['alanlar'] : [];
          $__ana = [];
          foreach ($__kodlar as $__kod) {
              foreach (al_anaLar((string)$__kod) as $__k) {
                  if (isset(al_ana()[$__k])) $__ana[$__k] = true;
              }
          }
          /* Bir çalışma aynı ana alanda iki dal taşıyorsa BİR kez sayılır:
             sayılan şey dal değil çalışmadır. */
          foreach (array_keys($__ana) as $__k) $alanSay[$__k] = ($alanSay[$__k] ?? 0) + 1;
      }
      arsort($alanSay);
    ?>
    <?php /* ---------- SATIRIN ADI VE SONUNDAKİ KAPI ----------
             ÖLÇÜLEN İKİ KUSUR:

             1. Satırın adı "Bilim dallarına göre"ydi, ama satırda DAL
                yoktu: en üst basamak, yani TEMEL ALAN vardı. Merdiven
                üç basamaklıdır (temel alan > bilim alanı > bilim dalı)
                ve iki sayfada iki ad kullanmak okura kaç basamak
                olduğunu unutturuyordu. Ad artık al_duzey_ad()'dan gelir.

             2. Satır yalnız DOLU temel alanları gösterdiği için —ki bu
                doğrudur, boş bir alan adı olmayan bir arşivi varmış gibi
                gösterir— sınıflandırmanın tamamına giden HİÇBİR yol
                yoktu. Bildirilen cümle buydu: "alanlar tam gözükmüyor,
                işlevsiz duruyor gibi; birisi gelip herhangi bir bilim
                dalında arama yapabilmeli."

                Çözüm iki kuralı da bozmadan: satır dolu alanları
                göstermeyi sürdürür, SONUNA bütün sınıflandırmayı açan
                bir bağ eklenir. O bağ 238 seçenekli seçiciye götürür.
                Böylece satır hâlâ yalnız olguyu gösterir, kapı ise
                arayanı içeri alır.

             Satır, arşiv boşken bile o kapıyı taşır: aramak isteyen
             kişinin yolu, arşivin bugünkü doluluğuna bağlı olamaz. */ ?>
    <?php /* Ad al_duzey_ad(1)'in söylediği addır ama BURADA ELLE yazılır:
             Türkçede "temel alanlar" + "-a göre" çekim ister, işlev ise
             çekim üretmez. Çekimi işleve yaptırmaya çalışmak, sistemde
             daha önce ölçülmüş bir tuzaktır (bkz. destek-ad). Ad
             değişirse bu satır da değişir; kapı ikisini karşılaştırır. */ ?>
    <nav class="alan-satir" aria-label="<?= k_c('Temel alanlara göre', 'By broad field') ?>">
      <?php foreach ($alanSay as $__k => $__n): ?>
      <a class="alan-et" href="<?= k_esc(k_bag('/yazilar.php') . '?alan=' . rawurlencode((string)$__k)) ?>">
        <span><?= k_esc(al_ad((string)$__k)) ?></span><b><?= k_esc(k_sayi($__n)) ?></b>
      </a>
      <?php endforeach; ?>
      <a class="alan-et alan-et-hepsi" href="<?= k_esc(k_bag('/yazilar.php') . '#alanSec') ?>">
        <span><?= k_c('Bütün bilim alanları ve dalları', 'All fields and branches of science') ?></span>
      </a>
    </nav>

    <div class="sek" data-sek>
      <?= k_sekme_bar('ars', $sekmeler) ?>
      <?php foreach ($sekmeler as $i => $sk): ?>
        <?= k_sekme_ac('ars', $i) ?>
          <?php if ($sk['kume']): ?>
          <div class="dizi son-dizi">
            <?php foreach ($sk['kume'] as $y) echo k_yazi_kart($y); ?>
          </div>
          <?php else: ?>
          <p class="sek-bos"><?= k_c('Bu listede henüz çalışma yok.', 'No works in this list yet.') ?></p>
          <?php endif; ?>
          <?php if (!empty($sk['cagri'])): ?>
          <?php /* Çağrı kartların altında durur: okur önce çalışmaların
                   ne olduğunu görsün, sonra ne yapabileceğini. Cümle
                   önce olguyu söyler, sonra davet eder; sıra bilerek
                   böyle, çünkü davetin dürüstlükten önce gelmesi bu
                   sekmenin ne için açıldığını gizlerdi. Sözcükler
                   yazi.php'deki kutuyla aynı yerden gelir. */ ?>
          <div class="cgr kutu kutu-kut">
            <div>
              <b><?= k_c('Bu çalışmalara hakem aranıyor', 'These works are seeking reviewers') ?></b>
              <span><?= k_c(
                'Bu çalışmalar hakem değerlendirmesinden geçmemiştir. Doktora derecesine sahip herkes gönüllü olabilir.',
                'These works have not undergone peer review. Anyone holding a doctorate may volunteer.'
              ) ?></span>
            </div>
            <a class="d d-vurgu d-kucuk" href="<?= k_esc(k_bag('/bekleyen.php')) ?>"><?= k_c('Hakemliğe gönüllü ol', 'Volunteer to review') ?></a>
          </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ================= İKİ YOL ================= -->
<section class="bolum<?= $yazilar ? ' bolum-bitisik' : '' ?>" id="yollar">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Nasıl işler', 'How it works') ?></span>
    <h2><?= k_c('İki ayrı yol, tek bir arşiv', 'Two separate tracks, one archive') ?></h2>
    <p style="max-width:66ch;color:var(--metin-2);margin-bottom:20px"><?= k_c(
      'Her çalışma hakemlikten geçmek zorunda değildir. Geçenler ve geçmeyenler aynı arşivde bulunur ama hiçbir zaman aynı etiketi taşımaz. Okur, bir metnin hangi süreçten geçtiğini ilk bakışta görür.',
      'Not every work has to go through peer review. Reviewed and non reviewed pieces live in the same archive, but they never carry the same label. A reader can see which process a text went through at a glance.'
    ) ?></p>

    <div class="yol-dizi">
      <div class="kart yol">
        <h3><span class="rz rz-yes"><span class="nokta"></span><?= k_c('Hakemli', 'Peer reviewed') ?></span></h3>
        <ol>
          <li><b><?= k_c('Başvuru', 'Application') ?></b> <?= tg_benzerlik_sarti()
                ? k_c(': yazar bilgileri, ORCID ve Scopus kimliği, benzerlik raporu.', ': author details, ORCID and Scopus IDs, similarity report.')
                : k_c(': yazar bilgileri, ORCID ve Scopus kimliği, alan kodları.', ': author details, ORCID and Scopus IDs, field codes.') ?></li>
          <li><b><?= k_c('Ön denetim', 'Desk check') ?></b> <?= k_c(': kapsam, biçim ve yayın ilkeleri.', ': scope, format and editorial policies.') ?></li>
          <li><b><?= k_c('Açık hakemlik', 'Open review') ?></b> <?= k_c(': hakemler raporlarını adlarıyla yazar, raporlar yayımlanır.', ': reviewers sign their reports and the reports are published.') ?></li>
          <li><b><?= k_c('Yayın', 'Publication') ?></b> <?= k_c(': kalıcı kimlik verilir, çalışma açık erişime girer.', ': a permanent identifier is issued and the work goes open access.') ?></li>
        </ol>
        <p class="sure"><?= k_c(
          'İki olumlu rapor alan çalışma bu sistemde hakem onaylı sayılır. İki ret alan çalışma silinmez; hakemsiz yazı olarak, süreciyle birlikte açıkta kalır.',
          'A work receiving two positive reports counts as reviewer approved within this system. A work with two rejections is not deleted; it remains visible as a non reviewed piece, together with its process.'
        ) ?></p>
      </div>

      <div class="kart yol yol-b">
        <h3><span class="rz rz-lac"><span class="nokta"></span><?= k_c('Hakemsiz yazı', 'Non reviewed') ?></span></h3>
        <ol>
          <li><b><?= k_c('Başvuru', 'Application') ?></b> <?= k_c(': aynı yazar ölçütleri geçerlidir.', ': the same author criteria apply.') ?></li>
          <li><b><?= k_c('Ön denetim', 'Desk check') ?></b> <?= k_c(': yayın ilkelerine uygunluk aranır.', ': compliance with editorial policies is checked.') ?></li>
          <li><b><?= k_c('Yayın', 'Publication') ?></b> <?= k_c(': "hakemsiz" etiketiyle ve kalıcı kimlikle yayımlanır.', ': published with a "non reviewed" label and a permanent identifier.') ?></li>
          <li><b><?= k_c('Sonradan hakemlik', 'Review later') ?></b> <?= k_c(': yazar isterse aynı metin hakem sürecine alınabilir.', ': if the author wishes, the same text can enter the review process.') ?></li>
        </ol>
        <p class="sure"><?= k_c(
          'Deneme, çeviri, alan notu, veri betimlemesi, kitap değerlendirmesi ve tartışma yazıları bu yolda yayımlanabilir.',
          'Essays, translations, field notes, data descriptions, book reviews and discussion pieces can be published on this track.'
        ) ?></p>
      </div>
    </div>
  </div>
</section>

<!-- ================= FARKIMIZ ================= -->
<section class="bolum" id="fark" style="background:var(--yuzey-2);border-block:1px solid var(--cizgi)">
  <div class="kap">
    <span class="bas-ust"><?= k_c('Neden başka', 'What is different') ?></span>
    <h2 style="margin-bottom:20px"><?= k_c('Klasik dergiden ayrıldığımız yerler', 'Where we part ways with the classic journal') ?></h2>

    <div class="fark">
      <article>
        <div class="im"><?= kim_ikon('insan', 19) ?></div>
        <h3><?= k_c('Hakem adıyla durur', 'Reviewers sign') ?></h3>
        <p><?= k_c('Kör hakemlik yoktur. Rapor kimin yazdığıyla birlikte yayımlanır. Bu, hem raporun niteliğini yükseltir hem de hakemin emeğini görünür kılar.', 'There is no blind review. Each report is published together with its author. This raises the quality of the report and makes the reviewer\'s labour visible.') ?></p>
      </article>
      <article>
        <div class="im"><?= kim_ikon('saat', 19) ?></div>
        <h3><?= k_c('Bekleme yok', 'No waiting room') ?></h3>
        <p><?= k_c('Sayı ve cilt beklenmez. Bir çalışma hazır olduğunda yayımlanır; arşiv sürekli akar.', 'There are no issues or volumes to wait for. A work is published when it is ready; the archive flows continuously.') ?></p>
      </article>
      <article>
        <div class="im"><?= kim_ikon('birlik', 19) ?></div>
        <h3><?= k_c('Hiçbir ücret yok', 'No charge at any point') ?></h3>
        <p><?= k_c('Yazardan işlem ücreti alınmaz, okurdan abonelik istenmez. Sistem reklam da barındırmaz.', 'No processing charge for authors, no subscription for readers. The system carries no advertising either.') ?></p>
      </article>
      <article>
        <div class="im"><?= kim_ikon('terazi', 19) ?></div>
        <h3><?= k_c('Tek ve açık telif kaydı', 'One clear copyright record') ?></h3>
        <p><?= k_c('Telif hakkı yazarda kalır; yazar yalnızca yayımlama ve kalıcı arşivleme izni verir. Metin CC BY 4.0 ile açık erişimdedir; herkes kaynak göstererek çoğaltabilir, çevirebilir ve kullanabilir.', 'Copyright remains with the author, who grants only the permission to publish and to archive permanently. The text is open access under CC BY 4.0; anyone may copy, translate and reuse it with attribution.') ?></p>
      </article>
      <article>
        <div class="im"><?= kim_ikon('kure', 19) ?></div>
        <h3><?= k_c('Alan sınırı yok', 'No disciplinary fence') ?></h3>
        <p><?= k_c('Tek bir bilim dalına bağlı değildir. Ölçüt yöntemin sağlamlığı ve sonucun izlenebilirliğidir.', 'It is not tied to a single discipline. The criterion is the soundness of the method and the traceability of the result.') ?></p>
      </article>
      <article>
        <div class="im"><?= kim_ikon('goz', 19) ?></div>
        <h3><?= k_c('Süreç görünür', 'The process is visible') ?></h3>
        <?php /* Benzerlik oranı ARTIK ZORUNLU DEĞİL; yazar verirse
                 gösterilir. "Yer alır" demek, verilmediği durumda
                 tutulamayacak bir söz vermektir. */ ?>
        <p><?= tg_benzerlik_sarti()
              ? k_c('Benzerlik oranı, yapay zekâ kullanım beyanı ve hakem kararları çalışmanın sayfasında yer alır.', 'The similarity ratio, the AI use declaration and the review decisions all appear on the work\'s own page.')
              : k_c('Yapay zekâ kullanım beyanı ve hakem kararları çalışmanın sayfasında yer alır; yazar benzerlik raporu eklediyse oranı da orada görünür.', 'The AI use declaration and the review decisions appear on the work\'s own page; if the author attached a similarity report, its ratio is shown there too.') ?></p>
      </article>
    </div>
  </div>
</section>

<!-- ================= DÜRÜST SINIRLAR VE TAMGA ================= -->
<section class="bolum" id="sinir">
  <div class="kap ikili">
    <div>
      <span class="bas-ust"><?= k_c('Açık sözlülük', 'Plain speaking') ?></span>
      <h2 style="margin-bottom:16px"><?= k_c('Ne verebiliriz, ne veremeyiz', 'What we can and cannot offer') ?></h2>
      <div class="durust">
        <div class="kart">
          <h3 style="color:var(--yesil)"><?= k_c('Verdiklerimiz', 'What we offer') ?></h3>
          <ul>
            <li><?= k_c('Kalıcı adres ve değişmez kimlik.', 'A permanent address and a stable identifier.') ?></li>
            <li><?= k_c('Adıyla imzalanmış, herkese açık hakem raporları.', 'Signed, publicly readable reviewer reports.') ?></li>
            <li><?= k_c('Arama motorlarında ve akademik dizinlerde bulunabilirlik.', 'Discoverability in search engines and academic indexes.') ?></li>
            <li><?= k_c('Türkçe ve İngilizce tam metin yayımlama imkânı.', 'Full text publication in both Turkish and English.') ?></li>
            <li><?= k_c('Okuma sayısı ve erişim verisinin yazarla paylaşılması.', 'Reading counts and access data shared with the author.') ?></li>
          </ul>
        </div>
        <div class="kart">
          <h3 style="color:var(--kirmizi)"><?= k_c('Veremediklerimiz', 'What we cannot offer') ?></h3>
          <ul>
            <li><?= k_c('Yükseltme ölçütü sayılan bir dizin sıralaması. Bu sistem hiçbir dizinde taranmamaktadır.', 'A place in an index that counts towards academic promotion. This system is not indexed anywhere.') ?></li>
            <li><?= k_c('Etki faktörü ya da benzer bir nicel derecelendirme.', 'An impact factor or any similar quantitative ranking.') ?></li>
            <li><?= k_c('ISSN ya da DOI. Verilen kimlik bunların yerine geçmez.', 'An ISSN or a DOI. The identifier issued here does not replace them.') ?></li>
            <li><?= k_c('Geniş bir kurumsal hakem havuzu. Havuz yeni ve küçüktür.', 'A large institutional pool of reviewers. The pool is new and small.') ?></li>
          </ul>
        </div>
      </div>
      <p style="margin-top:14px;font-size:var(--y-3);color:var(--metin-2);max-width:72ch"><?= k_c(
        'Bunları saklamak yerine yazıyoruz. Çalışmanızı buraya gönderirken ne beklediğinizi bilmeniz, sonradan hayal kırıklığına uğramanızdan iyidir.',
        'We write these down rather than hide them. Knowing what to expect before you submit is better than being disappointed afterwards.'
      ) ?></p>
    </div>

    <div class="kart" id="tamga">
      <span class="bas-ust"><?= k_esc((string)tg_ayar('tamga_ad', 'Tamga')) ?></span>
      <h3 style="font-size:var(--y-6);margin-bottom:.4em"><?= k_c('Her çalışmaya kalıcı bir işaret', 'A lasting mark for every work') ?></h3>
      <p style="color:var(--metin-2);font-size:var(--y-3);margin:0"><?= k_c(
        'Tamga, Türk boylarının kendilerine ait olanı işaretlemek için kullandığı damgadır. Burada da her çalışmaya devredilemez bir işaret verilir. İşlevi DOI\'ye benzer, ama DOI değildir ve DOI yerine geçmez.',
        'A tamga is the mark Turkic clans used to sign what belonged to them. Here every work receives a mark of its own. Its function resembles a DOI, but it is not a DOI and does not replace one.'
      ) ?></p>
      <div class="tam-ornek"><?= k_esc(preg_replace('#^https?://#', '', tg_kok())) ?>/<?= k_esc((string)tg_ayar('tamga_yol', 'tamga')) ?>/<?= k_esc((string)tg_ayar('tamga_on', 'KTG') . '-' . date('Y') . '-00001-' . tg_denetim(date('Y') . '00001')) ?></div>
      <a class="d d-ikinci d-git" href="<?= k_esc(k_bag('/ilkeler.php#tamga')) ?>"><?= k_c('Tamga nedir: ayrıntılı açıklama', 'What a tamga is: detailed explanation') ?></a>
    </div>
  </div>
</section>

<!-- ================= KAPANIŞ ================= -->
<section class="bolum bolum-bitisik">
  <div class="kap">
    <div class="kapan">
      <h2 style="margin-bottom:.4em"><?= k_c('Çalışmanız hazırsa', 'If your work is ready') ?></h2>
      <p><?= k_c(
        'Bütün yazarlarda en az doktora derecesi aranır. Başvuru formu Türkçe ve İngilizce doldurulabilir; Word belgesi yüklerseniz biçimlendirme sistem tarafından yapılır.',
        'A doctoral degree is required of every author. The application form can be completed in Turkish or English; if you upload a Word file, the formatting is handled by the system.'
      ) ?></p>
      <div class="giris-dg" style="justify-content:center">
        <a class="d d-vurgu" href="<?= k_esc(k_bag('/basvuru.php')) ?>"><?= k_c('Başvuru formunu aç', 'Open the application form') ?></a>
        <a class="d d-ikinci" href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Önce ilkeleri oku', 'Read the policies first') ?></a>
      </div>
    </div>
  </div>
</section>

<?php k_son(<<<JS
<script>
/* Kaydırak.
   Kaydırma yakalamalı bir şerittir; noktalar ve oklar yalnızca onu
   sürer. Betik çalışmazsa şerit parmakla ve klavyeyle kaydırılabilir
   durumda kalır, hiçbir levha erişilmez olmaz. */
(function(){
  var ray = document.getElementById('kydRay');
  if (!ray) return;
  var levhalar = Array.prototype.slice.call(ray.querySelectorAll('.kyd-s'));
  if (levhalar.length < 2) return;
  var noktaKap = document.getElementById('kydNokta'),
      geri = document.getElementById('kydGeri'),
      ileri = document.getElementById('kydIleri'),
      EN = document.documentElement.lang === 'en',
      simdi = 0, zaman = null,
      azHareket = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  levhalar.forEach(function(l, i){
    var b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('role', 'tab');
    b.setAttribute('aria-label', (EN ? 'Slide ' : 'Levha ') + (i + 1));
    b.setAttribute('aria-current', i === 0 ? 'true' : 'false');
    b.addEventListener('click', function(){ git(i); dur(); });
    noktaKap.appendChild(b);
  });
  var noktalar = Array.prototype.slice.call(noktaKap.children);

  function git(i){
    simdi = (i + levhalar.length) % levhalar.length;
    ray.scrollTo({ left: levhalar[simdi].offsetLeft - ray.offsetLeft, behavior: azHareket ? 'auto' : 'smooth' });
    isaretle();
  }
  function isaretle(){
    noktalar.forEach(function(n, i){ n.setAttribute('aria-current', i === simdi ? 'true' : 'false'); });
  }
  /* Parmakla kaydırıldığında da noktalar doğru levhayı göstersin */
  var z;
  ray.addEventListener('scroll', function(){
    clearTimeout(z);
    z = setTimeout(function(){
      var en = ray.clientWidth || 1;
      var y = Math.round(ray.scrollLeft / en);
      if (y !== simdi && y >= 0 && y < levhalar.length) { simdi = y; isaretle(); }
    }, 90);
  });

  if (geri) geri.addEventListener('click', function(){ git(simdi - 1); dur(); });
  if (ileri) ileri.addEventListener('click', function(){ git(simdi + 1); dur(); });
  ray.addEventListener('keydown', function(e){
    if (e.key === 'ArrowRight') { e.preventDefault(); git(simdi + 1); dur(); }
    if (e.key === 'ArrowLeft')  { e.preventDefault(); git(simdi - 1); dur(); }
  });

  function basla(){ if (!azHareket && !zaman) zaman = setInterval(function(){ git(simdi + 1); }, 8000); }
  function dur(){ if (zaman) { clearInterval(zaman); zaman = null; } }
  ray.addEventListener('mouseenter', dur);
  ray.addEventListener('focusin', dur);
  ray.addEventListener('mouseleave', basla);
  document.addEventListener('visibilitychange', function(){ document.hidden ? dur() : basla(); });
  basla();
})();
</script>
JS); ?>
