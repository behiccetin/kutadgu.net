<?php
/* =====================================================================
   KUTADGU - Kişi sayfası / Person page
   ---------------------------------------------------------------------
   Bir adın altında ne yapıldığı bu sistemde zaten açıktır: hakem
   raporları adla yayımlanır, kurul oyları adla ve gerekçesiyle açılır,
   şerhler adla durur, kefillikler adla kaydedilir. Bunlar bugüne kadar
   arşivin içine dağılmış hâlde duruyordu; bir hakemin bütün kararlarını
   görmek için bütün çalışmaları tek tek açmak gerekiyordu.

   Bu sayfa yeni bir bilgi açmaz. Yalnızca zaten açık olanı bir araya
   getirir. "Her hakemin karar dağılımı okuyucuya açıktır" sözü de ancak
   böyle bir sayfayla gerçekten tutulmuş olur: her şeye onay veren bir
   hakem, ancak kararları yan yana konduğunda görünür.

   İletişim bilgileri kişinin seçtiği görünürlük düzeyine göre çıkar;
   e-posta hiçbir durumda düz yazı olarak basılmaz. Açık oylamaların oyları görünmez;
   yalnızca sonuçlanmış oylamalar açıktır, tıpkı çalışma sayfalarında
   olduğu gibi.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';

$hsO = null;
if (is_file(__DIR__ . '/k/hesap.php')) { require_once __DIR__ . '/k/hesap.php'; }

$en   = k_en();
$slug = (string)($_GET['k'] ?? '');
$anah = tg_slug_anahtar($slug);

$kisi = tg_kisi_kaydi(k_yazilar(), $anah);

/* ---- Çalışmaların hakemlik basamağı ----
   Rozet, çalışmanın hangi YOLDA olduğunu değil o yolun neresinde
   olduğunu söylemelidir: 'tur' alanı yazar çalışmasını hakemliğe
   açtığı anda 'hakemli' olur, tek bir rapor bile gelmemiş olabilir.
   tg_kisi_kaydi() her çalışmanın yalnızca 'tur' ve 'onayli' bilgisini
   taşır; rapor sayısı oradan görünmediği için basamak, çalışmanın ham
   kaydından yol üzerinden okunur. Kayıt bulunamazsa en dar basamak
   varsayılır: söylenemeyecek şey söylenmez. */
$asamaHar = [];
foreach (k_yazilar() as $yKayit) {
    if (is_array($yKayit)) $asamaHar[tg_yazi_yolu($yKayit)] = tg_hakem_asamasi($yKayit);
}

/* Hesap varsa profil bilgisi oradan tamamlanır: unvan, kurum, ORCID,
   profil resmi. Hesabı olmayan biri de sayfaya sahiptir; kayıtları
   arşivden okunur. */
/* DENEME HESABININ KİŞİ SAYFASI AÇILMAZ. Sayfa ada göre üretiliyor ve
   ad, hesap listesinden de arşiv kayıtlarından da gelebilir; bu yüzden
   iki yerde birden süzülür: hesap hs_kamusal()'dan okunur ve adın
   kendisi hs_deneme_adi() ile ayrıca sorulur. Tek ölçüt bırakılsaydı,
   hesabı silinmiş ama kaydı duran bir deneme adı sayfa açardı. */
$hesap = null;
if ($anah !== '' && function_exists('hs_deneme_adi') && hs_deneme_adi($anah)) {
    $hesap = null; $anah = '';
}
if ($anah !== '' && function_exists('hs_kamusal')) {
    foreach (hs_kamusal() as $h) {
        if (!is_array($h)) continue;
        if (tg_ad_anahtar((string)($h['ad'] ?? '')) !== $anah) continue;
        $hesap = $h; break;
    }
}
if ($hesap) {
    foreach (['ad', 'unvan', 'kurum', 'orcid', 'scopus'] as $alan) {
        $v = trim((string)($hesap[$alan] ?? ''));
        if ($v !== '') $kisi[$alan] = $v;
    }
}

/* ---- Kişinin kendi yazdıkları ----
   Tanıtım, dış bağlantılar ve üyelikler yalnızca hesabı olan kişide
   bulunur; arşivden üretilen bir kişi sayfasında bunlar boştur.
   Hiçbiri doğrulanmaz ve hiçbiri bir yetki getirmez: sayfada beyan
   olduğu açıkça yazılıdır. */
/* Bakan kişi sisteme girmiş mi? Görünürlüğü "yalnızca sistem
   kullanıcılarına" olan alanlar buna göre gösterilir. Giriş yapmamış
   okur ve toplayıcı için o alanlar sayfada hiç bulunmaz; gizlenmez,
   basılmaz. Gizlenen bir şey kaynakta durur ve okunur. */
$bakanGirisli = function_exists('hs_oturum') && hs_oturum() !== null;
$gor = fn(string $alan): bool => $hesap ? tg_alan_gorunur($hesap, $alan, $bakanGirisli) : false;

$tanitim   = $hesap ? tg_tanitim($hesap['tanitim'] ?? '') : '';
$disBaglar = $hesap ? tg_hesap_baglantilar($hesap) : [];
$uyelikler = $hesap ? tg_uyelikler($hesap['uyelikler'] ?? []) : [];

/* Editör ve baş editörlerin sayfası, henüz bir kayıtları olmasa da
   vardır: kurul sayfasında adları yazılıdır ve oraya bağlanır. */
$kurulda = $hesap !== null && (
    (function_exists('hs_bas_editor_mu') && hs_bas_editor_mu((string)($hesap['eposta'] ?? '')))
    || (function_exists('hs_editor_mu') && hs_editor_mu($hesap))
);

if ($anah === '' || (!tg_kisi_var($kisi) && !$kurulda)) {
    http_response_code(404);
    k_bas([
        'tur' => 'belge',
        'baslik' => k_c('Kişi bulunamadı', 'Person not found'),
        'yol' => '/kisi.php',
        'robots' => 'noindex,follow',
    ]);
    echo '<section class="bolum"><div class="kap blg"><div class="blg-ic">'
       . '<h1>' . k_c('Bu ada ilişkin bir kayıt yok', 'There is no record under this name') . '</h1>'
       . '<p>' . k_c('Kişi sayfaları arşivdeki kayıtlardan üretilir. Bu adla yayımlanmış bir çalışma, yazılmış bir hakem raporu, kullanılmış bir kurul oyu ya da düşülmüş bir şerh bulunamadı.',
                     'Person pages are produced from the records in the archive. No work published, no referee report written, no panel vote cast and no note left under this name was found.') . '</p>'
       . '<p><a href="' . k_esc(k_bag('/yazilar.php')) . '">' . k_c('Bütün çalışmalara git', 'Go to all works') . '</a></p>'
       . '</div></div></section>';
    k_son();
    exit;
}

$ad      = $kisi['ad'] !== '' ? $kisi['ad'] : k_titr($slug);
$adUnvan = k_unvan_ekle((string)$kisi['unvan'], $ad);
$resim   = tg_resim_yolu($hesap);
$harf    = tg_bas_harf($ad);

/* Roller: kayıtlardan çıkar, hesaptan tamamlanır */
$roller = [];
if ($kisi['yazarlik'])    $roller[] = k_c('Yazar', 'Author');
if ($kisi['hakemlik'])    $roller[] = k_c('Hakem', 'Reviewer');
if ($kisi['oylar'])       $roller[] = k_c('Kurul oyu kullandı', 'Has cast panel votes');
if ($kisi['kefillikler']) $roller[] = tg_destek_ad_bas(k_en());
if ($hesap && function_exists('hs_bas_editor_mu') && hs_bas_editor_mu((string)($hesap['eposta'] ?? ''))) {
    array_unshift($roller, k_c('Baş editör', 'Chief editor'));
} elseif ($hesap && function_exists('hs_editor_mu') && hs_editor_mu($hesap)) {
    array_unshift($roller, k_c('Editör', 'Editor'));
}

/* ---- GÖREV GEÇMİŞİ (kurul kararı F1, 14 Ağustos 2026) ----
   "Hangi yıllar baş editörlük yaptığı, hangi çalışmalarda yer aldığı
    — hakem, editör, baş editör olarak — profil kartında gösterilir."

   Hakemlik zaten bu sayfanın altında adıyla duruyordu; eksik olan
   EDİTÖRLÜKTÜ. Kim hangi çalışmaya hangi hakemi atadı bilgisi arşivde
   vardı ama hiçbir yerde toplanmıyordu; toplanmayan bir kayıt okurun
   eline geçmediği sürece açıklık değildir. Sayım tek kaynaktan gelir
   (ortak.php, tg_gorev_gecmisi). */
$gecmis = function_exists('tg_gorev_gecmisi') ? tg_gorev_gecmisi($ad) : ['gorev' => [], 'atama' => 0, 'gun' => 0, 'isler' => [], 'ilk' => '', 'son' => ''];
$kurucuMu = false; $onursalMu = false;
foreach ($gecmis['gorev'] as $g) {
    if (!empty($g['kurucu'])) $kurucuMu = true;
    if (empty($g['suren']))   $onursalMu = true;
}
if ($kurucuMu)  array_unshift($roller, k_c('Kurucu baş editör', 'Founding chief editor'));
/* Onursal sıfat GÖREVDEKİ ile birlikte yazılmaz: kişi yeniden göreve
   gelmişse bugünkü sıfatı görevdekidir. Onursal, yalnızca bugün
   görevde olmayan için doğrudur. */
if ($onursalMu && !in_array(k_c('Baş editör', 'Chief editor'), $roller, true)) {
    $roller[] = k_c('Onursal baş editör', 'Honorary chief editor');
}

/* Görev yılları: "2026–2028", süren görevde "2026–". Yıl yazılır, gün
   değil: kartta okunan şey bir süre, bir tarihtir değil.

   KURUCULARDA BAŞLANGIÇ YILI YOKTUR ve UYDURULMAZ. Kuruluş kaydında
   atama tarihi diye bir alan yoktur, çünkü kurucular atanmadı; sistem
   onlarla başladı. Buraya bir yıl yazmak, kayıtta olmayan bir olguyu
   varmış gibi göstermek olurdu — bu depoda en sık düzeltilen kusur
   sınıfı tam olarak budur. O yüzden kurucuda süre değil, sürenin
   niteliği yazılır. */
$gorevYil = [];
foreach ($gecmis['gorev'] as $g) {
    $b = substr((string)$g['bas'], 0, 4);
    $s = substr((string)$g['son'], 0, 4);
    if ($b === '' && $s === '') {
        if (!empty($g['kurucu'])) {
            $gorevYil[] = !empty($g['suren'])
                ? k_c('kuruluştan bu yana', 'since the founding')
                : k_c('kuruluştan ', 'from the founding to ') . $s;
        }
        continue;
    }
    if (!empty($g['suren'])) $gorevYil[] = ($b !== '' ? $b : '') . '–';
    elseif ($b === '')       $gorevYil[] = '–' . $s;
    elseif ($b === $s)       $gorevYil[] = $b;
    else                     $gorevYil[] = $b . '–' . $s;
}

$kararAd = ['kabul' => [k_c('Kabul', 'Accepted')], 'kucuk' => [k_c('Küçük revizyon', 'Minor revision')],
            'buyuk' => [k_c('Büyük revizyon', 'Major revision')], 'ret' => [k_c('Ret', 'Rejected')]];
$kararToplam = array_sum($kisi['karar']);

$ekBas = <<<CSS
<style>
/* Kart, rozet ve yüz dizgeden gelir. Burada yalnızca kişi sayfasının
   kendi düzeni, karar çubuğu ve beyan bölümleri durur. */
.ks{min-width:0}
/* ---- KİŞİ KARTI ----
   Kurul isteği: profil kartları derli toplu dursun. Örnek derlemedeki
   kart kopyalanmadı — oradaki renkler, gölgeler ve yuvarlaklıklar bu
   dizgenin dışından geliyor. Alınan fikir şu: kimlik bilgisi kendi
   kutusunda dursun, sayfanın geri kalanından bir çizgiyle ayrılsın.
   Uygulaması bizim belirteçlerimizle; palete tek bir renk eklenmedi.

   Üstteki ince altın şerit kimliğin nerede bittiğini söyler; kartın
   kendisi tıklanabilir değildir, o yüzden hareket de yoktur. */
.ks-bas{display:flex;flex-wrap:wrap;gap:var(--b-5);align-items:flex-start;margin:0 0 var(--b-5);
  position:relative;padding:var(--b-5);padding-top:calc(var(--b-5) + 3px);
  background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-4);
  box-shadow:var(--g-1);overflow:hidden}
.ks-bas::before{content:"";position:absolute;inset-inline:0;inset-block-start:0;height:3px;
  background:var(--kut)}
@media(max-width:600px){ .ks-bas{padding:var(--b-4);padding-top:calc(var(--b-4) + 3px)} }
/* Yüzün ölçüsü boşluk basamaklarından kurulur: 64 + 32. */
.ks-yuz{width:calc(var(--b-8) + var(--b-6));height:calc(var(--b-8) + var(--b-6));font-size:var(--y-8)}
.ks-kim{min-width:0;flex:1 1 260px}
.ks-kim h1{margin:0 0 var(--b-1)}
.ks-kurum{color:var(--metin-2);font-size:var(--y-4);margin:0 0 var(--b-2)}
.ks-rol{margin:0 0 var(--b-3)}
.ks-bag{margin:0}
/* Kişinin kendi yazdıkları: kısa tanıtım, dış bağlantılar, üyelikler.
   Sistemdeki kayıttan görsel olarak ayrılırlar, çünkü biri ölçülmüş
   bir kayıt, öteki kişinin beyanıdır ve okuyucu ikisini karıştırmasın. */
/* İletişim satırı. Adres düğmeye basılınca kurulur; düğme kendi
   yerinde bir bağlantıya dönüşür, sayfa zıplamaz. */
.ks-iletisim{margin:var(--b-3) 0 0}
.ks-tel{font-family:var(--mono);font-size:var(--y-3);color:var(--metin-2)}
/* Betiğin kurduğu adres bağlantısı: kendi başına duran bir hedeftir. */
.ks-eposta{display:inline-flex;align-items:center;min-height:var(--hedef);
  font-family:var(--mono);font-size:var(--y-3);word-break:break-all}
.ks-uye-not{font-size:var(--y-2);color:var(--metin-2);flex-basis:100%}
.ks-tan{margin:var(--b-4) 0 0;padding-top:var(--b-4);
  border-top:1px solid var(--cizgi);color:var(--metin);max-width:62ch}
.ks-dis{margin:var(--b-4) 0 0;padding:0;list-style:none}
.ks-dis a small{color:var(--metin-2);font-family:var(--mono);font-size:var(--y-1);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:22ch}
.ks-dis a:hover small{color:var(--kut)}
.ks-uye{margin:var(--b-3) 0 0;padding:0;list-style:none;display:grid;gap:var(--b-1);
  font-size:var(--y-3);color:var(--metin-2)}
.ks-uye li{padding-left:var(--b-4);position:relative}
.ks-uye li::before{content:"";position:absolute;left:0;top:.62em;width:var(--b-1);height:var(--b-1);
  border-radius:50%;background:var(--cizgi-2)}
.ks-beyan{font-size:var(--y-2);color:var(--metin-2);margin:var(--b-3) 0 0}
.ks-say{margin:0 0 var(--b-5)}
.ks-say b{display:block;font-family:var(--baslik);font-size:var(--y-7);line-height:1.1}
.ks-say span{display:block;font-size:var(--y-1);color:var(--metin-2);margin-top:2px;
  letter-spacing:.04em;text-transform:uppercase}
/* Karar çubuğu: dört kararın payı tek bir şeritte. Renkler durum
   renklerinden gelir; çubuk ile alttaki açıklama aynı sınıfı taşır ki
   ikisi ayrı ayrı düzeltilmek zorunda kalmasın. */
.ks-cub{display:flex;height:var(--b-3);border-radius:var(--r-tam);overflow:hidden;
  background:var(--yuzey-2);margin:var(--b-3) 0 var(--b-2)}
.ks-cub i{display:block}
.ks-cub i.kabul,.ks-efs em.kabul{background:var(--yesil)}
.ks-cub i.kucuk,.ks-efs em.kucuk{background:var(--lacivert)}
.ks-cub i.buyuk,.ks-efs em.buyuk{background:var(--kut)}
.ks-cub i.ret,.ks-efs em.ret{background:var(--kirmizi)}
.ks-efs{font-size:var(--y-3);color:var(--metin-2)}
.ks-efs span{display:inline-flex;align-items:center;gap:var(--b-2)}
.ks-efs em{width:var(--b-3);height:var(--b-3);border-radius:var(--r-1);display:inline-block}
.ks-liste{display:grid;gap:var(--b-2);margin:var(--b-4) 0 0}
.ks-k a{color:var(--metin);font-weight:600;text-decoration:none;display:block}
.ks-k a:hover{color:var(--kut)}
.ks-alt{margin-top:var(--b-2);font-size:var(--y-2);color:var(--metin-2)}
.ks-ger{font-size:var(--y-3);margin-top:var(--b-2);padding-top:var(--b-2);
  border-top:1px solid var(--cizgi);color:var(--metin-2)}
h2.ks-b{margin:var(--b-6) 0 var(--b-2)}
.ks-not{font-size:var(--y-3);color:var(--metin-2);margin:0}
</style>
CSS;

k_bas([
    'tur'      => 'belge',
    'baslik'   => $adUnvan,
    'yol'      => '/kisi/' . rawurlencode(tg_ad_slug($ad)),
    'aciklama' => k_c(
        $adUnvan . ' adlı araştırmacının Kutadgu\'daki kamusal kaydı: çalışmaları, hakem raporları, kurul oyları ve şerhleri.',
        'The public record of ' . $adUnvan . ' in Kutadgu: works, referee reports, panel votes and notes.'
    ),
    'ek_bas'   => $ekBas,
]);
?>
<section class="bolum">
  <div class="kap blg">
    <div class="ks blg-ic">

      <div class="kart ks-bas">
        <?php if ($resim !== ''): ?>
          <img class="yuz ks-yuz" src="<?= k_esc($resim) ?>" alt="" width="96" height="96" loading="lazy">
        <?php else: ?>
          <div class="yuz yuz-harf ks-yuz" aria-hidden="true"><?= k_esc($harf) ?></div>
        <?php endif; ?>
        <div class="ks-kim">
          <h1><?= k_esc($adUnvan) ?></h1>
          <?php if ((string)$kisi['kurum'] !== ''): ?><p class="ks-kurum"><?= k_esc($kisi['kurum']) ?></p><?php endif; ?>
          <?php if ($roller): ?>
          <p class="satir ks-rol"><?php foreach ($roller as $ri => $rr): ?><span class="rz <?= $ri === 0 ? 'rz-kut' : 'rz-cizgi' ?>"><?= k_esc($rr) ?></span><?php endforeach; ?></p>
          <?php endif; ?>
          <p class="satir ks-bag">
            <?php if ((string)$kisi['orcid'] !== '' && (!$hesap || $gor('orcid'))): ?><a href="https://orcid.org/<?= k_esc($kisi['orcid']) ?>" target="_blank" rel="noopener" class="rz rz-cizgi">ORCID <?= k_esc($kisi['orcid']) ?> &nearr;</a><?php endif; ?>
            <?php if ((string)$kisi['scopus'] !== '' && (!$hesap || $gor('scopus'))): ?><a href="https://www.scopus.com/authid/detail.uri?authorId=<?= k_esc($kisi['scopus']) ?>" target="_blank" rel="noopener" class="rz rz-cizgi">Scopus &nearr;</a><?php endif; ?>
          </p>

          <?php /* ---- İletişim ----
                   E-posta sayfaya düz metin olarak BASILMAZ. Sayfada
                   duran şey ters çevrilmiş ve kodlanmış bir dizedir;
                   okur düğmeye bastığında betik adresi kurar. Betik
                   çalıştırmayan toplayıcılar bir adres bulamaz. Bu bir
                   şifreleme değildir ve öyle sunulmaz: kararlı bir
                   toplayıcı yine çözer, ama otomatik tarama zorlaşır.
                   Adresinin hiç görünmesini istemeyen kişide bu veri
                   sayfaya hiç konmaz. */
          $ePosta = $hesap && $gor('eposta') ? trim((string)($hesap['eposta'] ?? '')) : '';
          $telefon = $hesap && $gor('telefon') ? trim((string)($hesap['telefon'] ?? '')) : '';
          /* Giriş yapmamış okura, "burada bir şey var ama sana kapalı"
             demek gerekir. Bunu söylememek, alanın hiç olmadığını
             sandırır ve okur girip bakmayı da denemez. */
          $uyeSaklı = !$bakanGirisli && $hesap && (
              (tg_gorunurluk($hesap, 'eposta') === 'uyeler' && trim((string)($hesap['eposta'] ?? '')) !== '')
           || (tg_gorunurluk($hesap, 'telefon') === 'uyeler' && trim((string)($hesap['telefon'] ?? '')) !== ''));
          if ($ePosta !== '' || $telefon !== '' || $uyeSaklı): ?>
          <p class="satir ks-iletisim">
            <?php if ($ePosta !== ''): ?>
            <button type="button" class="d d-ikinci d-kucuk" data-eposta-ac
                    data-e="<?= k_esc(tg_eposta_ort($ePosta)) ?>">
              <?= k_c('E-posta adresini göster', 'Show e mail address') ?>
            </button>
            <?php endif; ?>
            <?php if ($telefon !== ''): ?>
            <span class="ks-tel"><?= k_c('Telefon', 'Telephone') ?>: <?= k_esc($telefon) ?></span>
            <?php endif; ?>
            <?php if ($uyeSaklı): ?>
            <span class="ks-uye-not"><?= k_c(
              'Bu kişinin bazı iletişim bilgileri yalnızca sisteme girmiş kullanıcılara açıktır.',
              'Some of this person\'s contact details are open only to signed in users.'
            ) ?></span>
            <?php endif; ?>
          </p>
          <?php endif; ?>

          <?php /* Kısa tanıtım. Kişinin kendi cümlesidir; sistem onu
                   ne doğrular ne de düzeltir. */ ?>
          <?php if ($tanitim !== ''): ?>
          <p class="ks-tan"><?= k_esc($tanitim) ?></p>
          <?php endif; ?>

          <?php /* Dış bağlantılar. Adın ne olduğu adresten çözülür;
                   kişi kendi etiketini yazdıysa onunki geçerlidir.
                   Hepsi https, hepsi yeni sekmede, hepsi rel="noopener".
                   Arama motorlarına izlenmemesi söylenir: bu sayfa dış
                   bağlantı satmaz ve kimseye sıra kazandırmaz. */ ?>
          <?php if ($disBaglar): ?>
          <ul class="satir ks-dis">
            <?php foreach ($disBaglar as $b): ?>
            <li><a class="rz rz-cizgi" href="<?= k_esc($b['url']) ?>" target="_blank" rel="noopener nofollow">
              <?= k_esc(tg_dis_profil_ad($b['url'], (string)$b['ad'], $en)) ?> &nearr;
            </a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>

          <?php if ($uyelikler): ?>
          <ul class="ks-uye">
            <?php foreach ($uyelikler as $u): ?><li><?= k_esc($u) ?></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>

          <?php if ($tanitim !== '' || $disBaglar || $uyelikler): ?>
          <p class="ks-beyan"><?= k_c(
            'Bu bölümdeki tanıtım, bağlantılar ve üyelikler kişinin kendi beyanıdır; sistem tarafından doğrulanmaz ve hiçbir yetki getirmez. Aşağıdaki kayıtlar ise bu sistemde olmuş işlerdir.',
            'The description, links and memberships in this section are stated by the person themselves; they are not verified by the system and confer no authority. The records below, by contrast, are things that happened in this system.'
          ) ?></p>
          <?php endif; ?>
        </div>
      </div>

      <div class="dizi dizi-4 ks-say">
        <div class="kart"><b><?= k_esc(k_sayi_kisa(count($kisi['yazarlik']))) ?></b><span><?= k_c('çalışma', 'works') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa(count($kisi['hakemlik']))) ?></b><span><?= k_c('hakem raporu', 'reports') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa(count($kisi['oylar']))) ?></b><span><?= k_c('kurul oyu', 'panel votes') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa(count($kisi['serhler']))) ?></b><span><?= k_c('şerh', 'notes') ?></span></div>
      </div>

      <?php /* ---- GÖREV GEÇMİŞİ ----
               Yalnızca bir şey varsa çizilir. Boş bir "editörlük"
               başlığı, hiç editörlük yapmamış bir kişiyi yapmış gibi
               göstermez ama okuru da boşuna oyalar. */ ?>
      <?php if ($gorevYil || $gecmis['atama'] > 0 || $gecmis['isler']): ?>
      <h2 class="ks-b"><?= k_c('Görev ve editörlük', 'Office and editorial work') ?></h2>
      <p class="ks-not"><?= k_c(
        'Bu sistemde hakemlik adıyla açıktır; editörlük de öyledir. Aşağıdaki sayılar arşivdeki kayıtlardan anlık çıkarılır: kimin hangi çalışmaya hakem atadığı ve hangi çalışmaya editöryal not düştüğü zaten yazılıdır. Bir başarı ölçüsü değildir; kararı verenin kim olduğu okurun bilme hakkıdır.',
        'Reviewing is open under a name in this system, and so is editorial work. The figures below are drawn from the archive as it stands: who assigned which reviewer to which work, and who wrote an editorial note on it, is already recorded. This is not a measure of merit; who made a decision is the reader\'s to know.'
      ) ?></p>
      <?php if ($gorevYil): ?>
      <p class="satir ks-rol">
        <span class="rz rz-kut"><?= k_c('Baş editörlük', 'Chief editorship') ?>: <?= k_esc(implode(', ', $gorevYil)) ?></span>
      </p>
      <?php endif; ?>
      <div class="dizi dizi-4 ks-say">
        <div class="kart"><b><?= k_esc(k_sayi_kisa($gecmis['atama'])) ?></b><span><?= k_c('hakem ataması', 'reviewer assignments') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa($gecmis['gun'])) ?></b><span><?= k_c('editöryal işlem günü', 'days of editorial work') ?></span></div>
        <div class="kart"><b><?= k_esc(k_sayi_kisa(count($gecmis['isler']))) ?></b><span><?= k_c('editörlük ettiği çalışma', 'works edited') ?></span></div>
      </div>
      <?php if ($gecmis['isler']): ?>
      <ul class="ks-liste">
        <?php foreach (array_slice($gecmis['isler'], 0, 20) as $w): ?>
        <li><a href="<?= k_esc(k_bag($w['yol'])) ?>"><?= k_esc($w['baslik'] !== '' ? $w['baslik'] : $w['yol']) ?></a><?php if ($w['tarih'] !== ''): ?> <span class="ks-tel"><?= k_esc($w['tarih']) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if (count($gecmis['isler']) > 20): ?>
      <p class="ks-not"><?= k_cd('Toplam %1 çalışmanın ilk yirmisi gösteriliyor.', 'The first twenty of %1 works are shown.', null, k_sayi(count($gecmis['isler']))) ?></p>
      <?php endif; ?>
      <?php endif; ?>
      <?php endif; ?>

      <?php if ($kararToplam > 0): ?>
      <h2 class="ks-b"><?= k_c('Karar dağılımı', 'Distribution of decisions') ?></h2>
      <p class="ks-not"><?= k_c(
        'Bir hakemin verdiği kararlar yan yana konduğunda okunur hâle gelir. Her şeye onay veren bir hakem de, hiçbir şeye onay vermeyen bir hakem de burada görünür. Bu, bir başarı ölçüsü değildir; okuyucunun raporu nasıl okuyacağına kendi karar verebilmesi içindir.',
        'A reviewer\'s decisions become legible when set side by side. A reviewer who approves everything, and one who approves nothing, are both visible here. This is not a measure of merit; it is so that a reader can decide for themselves how to read a report.'
      ) ?></p>
      <div class="ks-cub" role="img" aria-label="<?= k_c('Karar dağılımı', 'Distribution of decisions') ?>">
        <?php foreach ($kisi['karar'] as $kk => $kn): if ($kn <= 0) continue; ?>
        <i class="<?= k_esc($kk) ?>" style="width:<?= round($kn / $kararToplam * 100, 2) ?>%"></i>
        <?php endforeach; ?>
      </div>
      <p class="satir ks-efs">
        <?php foreach ($kisi['karar'] as $kk => $kn): if ($kn <= 0) continue; ?>
        <span><em class="<?= k_esc((string)$kk) ?>"></em><?= k_esc($kararAd[$kk][0]) ?> <b><?= (int)$kn ?></b></span>
        <?php endforeach; ?>
      </p>
      <?php endif; ?>

      <?php if ($kisi['yazarlik']): ?>
      <h2 class="ks-b"><?= k_c('Çalışmaları', 'Works') ?></h2>
      <div class="ks-liste">
        <?php foreach ($kisi['yazarlik'] as $w):
          $wAs = $asamaHar[$w['yol']] ?? ($w['tur'] === 'hakemli' ? 'aranan' : 'yok');
        ?>
        <div class="kart ks-k">
          <a href="<?= k_esc(k_bag($w['yol'])) ?>"><?= k_esc($w['baslik']) ?></a>
          <div class="satir ks-alt">
            <span><?= k_esc(k_tarih((string)$w['tarih'])) ?></span>
            <span class="rz <?= k_esc(tg_asama_rz($wAs)) ?>"><?= k_esc(tg_asama_metni($wAs, (bool)$en, true)) ?></span>
            <?php /* Ayrı "Hakem onaylı" rozeti kaldırıldı: basamak rozeti
                     onaylı çalışmada zaten "Hakem onaylı" yazıyor, ikisi
                     yan yana yinelemeden başka bir şey söylemiyordu.
                     Tek durum ayrık: geri çekilmiş çalışmada basamak
                     "Geri çekildi" olur ve o çalışmanın hakem onayından
                     geçmiş olduğu bilgisi kaybolur. Geri çekilme yapılmış
                     değerlendirmeyi yok saymaz, bu yüzden onay orada
                     ayrıca yazılır. */ ?>
            <?php if ($w['onayli'] && $wAs === 'cekildi'): ?><span class="rz rz-yes"><?= k_c('Hakem onaylıydı', 'Was reviewer approved') ?></span><?php endif; ?>
            <?php /* Rozet YALNIZCA düzen yürürlükteyken ve o kayıt desteği
                     gerçekten kullanmışken basılır; gerekçe ortak.php'de
                     tg_destekle_yazar()'ın başındadır. Eskiden ölçüt
                     "unvan yazmıyor" idi ve düzen kapalıyken bile
                     basıyordu. */ ?>
            <?php if (!empty($w['destekli'])): ?><span class="rz rz-kut"><?= k_c('Destekle yazar', 'Author by support') ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($kisi['hakemlik']): ?>
      <h2 class="ks-b"><?= k_c('Hakem raporları', 'Referee reports') ?></h2>
      <div class="ks-liste">
        <?php foreach ($kisi['hakemlik'] as $h): ?>
        <div class="kart ks-k">
          <a href="<?= k_esc(k_bag($h['yol'])) ?>"><?= k_esc($h['baslik']) ?></a>
          <div class="satir ks-alt">
            <?php if ((string)$h['tarih'] !== ''): ?><span><?= k_esc(tg_zaman((string)$h['tarih'])) ?></span><?php endif; ?>
            <?php if (isset($kararAd[$h['karar']])): ?><span class="rz <?= $h['karar'] === 'ret' ? 'rz-kir' : ($h['karar'] === 'kabul' ? 'rz-yes' : 'rz-cizgi') ?>"><?= k_esc($kararAd[$h['karar']][0]) ?></span><?php endif; ?>
            <?php if ($h['bagimsiz']): ?><span class="rz rz-cizgi"><?= k_c('Bağımsız', 'Independent') ?></span><?php endif; ?>
            <?php if (!$h['nitelik']): ?><span class="rz rz-kut"><?= k_c('Eşiğin altında', 'Below the threshold') ?></span><?php endif; ?>
            <?php if ($h['saymaz']): ?><span class="rz rz-kir"><?= k_c('Kurul geçersiz saydı', 'Set aside by a panel') ?></span><?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($kisi['oylar']): ?>
      <h2 class="ks-b"><?= k_c('Kurul oyları', 'Panel votes') ?></h2>
      <p class="ks-not"><?= k_c(
        'Yalnızca sonuçlanmış oylamalar burada görünür. Açık bir oylamanın oyları, üçüncü oy düşene kadar hiç kimseye görünmez.',
        'Only concluded votes appear here. The votes in an open ballot are visible to no one until the third vote is cast.'
      ) ?></p>
      <div class="ks-liste">
        <?php foreach ($kisi['oylar'] as $o): ?>
        <div class="kart ks-k">
          <a href="<?= k_esc(k_bag($o['yol'] . '#oylama')) ?>"><?= k_esc($o['baslik']) ?></a>
          <div class="satir ks-alt">
            <?php if ((string)$o['tarih'] !== ''): ?><span><?= k_esc(tg_zaman((string)$o['tarih'])) ?></span><?php endif; ?>
            <span class="rz rz-cizgi"><?= k_esc(tg_oy_metin((string)$o['tur'], (string)$o['karar'], (bool)$en)) ?></span>
          </div>
          <?php if (trim((string)$o['gerekce']) !== ''): ?><div class="ks-ger"><?= nl2br(k_esc((string)$o['gerekce'])) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($kisi['kefillikler']): ?>
      <h2 class="ks-b"><?= k_c('Destekledikleri', 'Works supported') ?></h2>
      <div class="ks-liste">
        <?php foreach ($kisi['kefillikler'] as $kf): ?>
        <div class="kart ks-k">
          <a href="<?= k_esc(k_bag($kf['yol'] . '#kefil')) ?>"><?= k_esc($kf['baslik']) ?></a>
          <div class="satir ks-alt">
            <span><?= k_esc($kf['kime']) ?></span>
            <span class="rz rz-cizgi"><?= k_esc(tg_kefil_ilgi_ad((string)$kf['ilgi'], (bool)$en)) ?></span>
            <?php if ((string)$kf['durum'] === 'ret'): ?><span class="rz rz-kir"><?= k_c('Desteklemedi', 'Declined') ?></span>
            <?php elseif ((string)$kf['durum'] !== 'onayli'): ?><span class="rz rz-kut"><?= k_c('Onay bekliyor', 'Awaiting confirmation') ?></span><?php endif; ?>
          </div>
          <?php if (trim((string)$kf['gerekce']) !== ''): ?><div class="ks-ger"><?= nl2br(k_esc((string)$kf['gerekce'])) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if ($kisi['serhler']): ?>
      <h2 class="ks-b"><?= k_c('Şerhleri', 'Notes') ?></h2>
      <div class="ks-liste">
        <?php foreach ($kisi['serhler'] as $sr): ?>
        <div class="kart ks-k">
          <a href="<?= k_esc(k_bag($sr['yol'] . '#serh')) ?>"><?= k_esc($sr['baslik']) ?></a>
          <div class="satir ks-alt">
            <?php if ((string)$sr['tarih'] !== ''): ?><span><?= k_esc(tg_zaman((string)$sr['tarih'])) ?></span><?php endif; ?>
            <?php $ilg = tg_serh_ilgi_ad((string)$sr['ilgi'], (bool)$en); if ($ilg !== ''): ?><span class="rz rz-cizgi"><?= k_esc($ilg) ?></span><?php endif; ?>
          </div>
          <div class="ks-ger"><?= nl2br(k_esc(k_ozet((string)$sr['metin'], 400))) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div>
    <?= k_belge_yan([], [
      ['tr' => 'Bu sayfa nereden geliyor', 'en' => 'Where this page comes from',
       'ic' => k_c(
         'Burada yeni bir bilgi yoktur. Hakem raporları, kurul oyları ve şerhler zaten adla yayımlanır; bu sayfa onları bir araya getirir. Hiçbir kayıt buraya özel değildir ve hiçbiri buradan silinemez.',
         'There is no new information here. Referee reports, panel votes and notes are already published under their authors\' names; this page brings them together. No record is special to this page and none can be deleted from it.'
       )],
      ['tr' => 'Aynı adı taşıyan iki kişi', 'en' => 'Two people with the same name',
       'ic' => k_c(
         'Kişiler adlarının sadeleştirilmiş biçimiyle bulunur. Aynı adı taşıyan iki araştırmacıyı ayıran şey ORCID\'dir ve varsa yukarıda yazılıdır. Bu, kapatılmamış bir açıktır; açık sözlülük sayfasında yazılıdır.',
         'People are found by the simplified form of their name. What distinguishes two researchers sharing a name is their ORCID, shown above where present. This is an unclosed gap, and it is stated on the plain speaking page.'
       )],
      ['tr' => 'İletişim', 'en' => 'Contact',
       'ic' => k_c(
         'Hangi iletişim bilgisinin kime açık olacağına kişinin kendisi karar verir: herkese, yalnızca sisteme girmiş kullanıcılara ya da hiç kimseye. Varsayılan gizlidir; görünmesini isteyen kendi açar. E-posta adresi hiçbir durumda sayfaya düz yazı olarak basılmaz, okur düğmeye bastığında kurulur. Bu bir şifreleme değildir; adres toplayan yazılımların işini zorlaştırır, imkânsız kılmaz. Arşivin indirilebilir kopyasında hiçbir adres yoktur.',
         'Each person decides who sees which of their contact details: everyone, signed in users only, or no one. The default is hidden; whoever wants theirs shown opens it themselves. An e mail address is never printed on the page as plain text; it is assembled when the reader presses a button. This is not encryption; it makes address harvesting harder, not impossible. The downloadable copy of the archive contains no addresses.'
       )],
    ]) ?>
  </div>
</section>
<?php k_son();
