<?php
/* =====================================================================
   KUTADGU - Çalışmalar arşivi / Works archive
   ---------------------------------------------------------------------
   Arşiv sunucuda üretilir; süzme, arama ve sıralama tarayıcıda çalışır.
   Böylece sayfa tek istekte açılır ve her tuş vuruşunda sunucuya
   gidilmez. Adresteki ölçütler (q, yazar, tur, yil, alan, sirala)
   sunucuda da uygulanır: paylaşılan bir bağlantı doğru sonucu açar ve
   arama motoru da aynı sonucu görür.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/parca.php';

$en      = k_en();
$yazilar = k_yazilar();
$say     = k_sayaclar();

/* ---------- Adresten gelen ölçütler ---------- */
$sorgu    = trim((string)($_GET['q'] ?? ''));
$yazarAra = trim((string)($_GET['yazar'] ?? ''));
$turAra   = (string)($_GET['tur'] ?? '');
$yilAra   = preg_match('/^\d{4}$/', (string)($_GET['yil'] ?? '')) ? (string)$_GET['yil'] : '';
$anahAra  = trim((string)($_GET['anahtar'] ?? ''));
/* Bilim alanı süzgeci: FORD tabanlı kodlarla. Bir üst alan seçildiğinde
   altındaki bütün dallar da gelir; okuyucu "İktisat ve işletme" derken
   "Ekonometri"yi dışarıda bırakmak istemez. */
$alanAra  = trim((string)($_GET['alan'] ?? ''));
if ($alanAra !== '' && !al_gecerli($alanAra)) $alanAra = '';
$sirala   = (string)($_GET['sirala'] ?? 'yeni');
/* Tür süzgeci üç değer alır. 'hakemli' artık "hakemli yola girmiş"
   değil "en az bir hakem raporu gelmiş" demektir; rapor beklemekte
   olanlar 'aranan' altında toplanır. Böylece hiçbir çalışma hakem
   değerlendirmesinden geçmiş gibi listelenmez. */
if (!in_array($turAra, ['hakemli', 'aranan', 'yazi'], true)) $turAra = '';
if (!in_array($sirala, ['yeni', 'eski', 'okunan', 'ad'], true)) $sirala = 'yeni';

/* ---------- Süzme ---------- */
$goster = $yazilar;

if ($yazarAra !== '') {
    /* Karşılaştırma Türkçe harflere ve noktalama farkına duyarsızdır:
       "Behiç Çetin" ile "Behic Cetin" aynı kişiyi gösterir. */
    $ara = tg_ad_anahtar($yazarAra);
    $goster = array_values(array_filter($goster, function ($y) use ($ara) {
        foreach (tg_yazar_anahtarlari($y) as $ak) { if ($ak === $ara) return true; }
        return false;
    }));
}
if ($sorgu !== '') {
    $s1 = mb_strtolower($sorgu, 'UTF-8');
    $s2 = tg_ad_anahtar($sorgu);
    $goster = array_values(array_filter($goster, function ($y) use ($s1, $s2) {
        $h = mb_strtolower(k_alan($y, 'baslik') . ' ' . k_yazarlar($y) . ' ' . k_alan($y, 'anahtar')
            . ' ' . k_ozet(k_alan($y, 'ozet'), 400), 'UTF-8');
        if (mb_strpos($h, $s1) !== false) return true;
        return $s2 !== '' && strpos(tg_ad_anahtar($h), $s2) !== false;
    }));
}
if ($turAra !== '') {
    /* Ölçüt, kartın data-tur özniteliğini üreten işlevin ta kendisidir
       (k_suz_tur). Sunucu ile tarayıcı aynı işlevden beslendiği için
       betiksiz okuyucu ile betikli okuyucu aynı listeyi görür. */
    $goster = array_values(array_filter($goster, fn($y) => k_suz_tur($y) === $turAra));
}
if ($yilAra !== '') {
    $goster = array_values(array_filter($goster, fn($y) => strncmp((string)($y['tarih'] ?? ''), $yilAra, 4) === 0));
}
if ($alanAra !== '') {
    $goster = array_values(array_filter($goster, function ($y) use ($alanAra) {
        /* Kapsama, yakınlık değil: bkz. k/alanlar.php al_kapsar(). */
        if (al_kayit_kapsam($y, $alanAra)) return true;
        return false;
    }));
}
if ($anahAra !== '') {
    $ak = mb_strtolower($anahAra, 'UTF-8');
    $goster = array_values(array_filter($goster, function ($y) use ($ak) {
        $l = array_map(fn($x) => mb_strtolower(trim($x), 'UTF-8'), preg_split('/[,;]+/u', k_alan($y, 'anahtar')) ?: []);
        return in_array($ak, $l, true);
    }));
}

/* ---------- Sıralama ---------- */
usort($goster, function ($a, $b) use ($sirala) {
    switch ($sirala) {
        case 'eski':   return strcmp((string)($a['tarih'] ?? ''), (string)($b['tarih'] ?? ''));
        case 'okunan': return k_okuma_sayisi((string)($b['id'] ?? '')) <=> k_okuma_sayisi((string)($a['id'] ?? ''));
        case 'ad':     return strcmp(tg_ad_anahtar(k_alan($a, 'baslik')), tg_ad_anahtar(k_alan($b, 'baslik')));
        default:       return strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? ''));
    }
});

/* ---------- Süzme seçenekleri ---------- */
$yazarSay = []; $hakemSay = []; $yilSay = []; $anahSay = [];
foreach ($yazilar as $y) {
    $b = $y['yazar_bilgi'] ?? null;
    $ilk = (is_array($b) && trim((string)($b['ad'] ?? '')) !== '') ? trim((string)$b['ad']) : trim((string)($y['yazar'] ?? ''));
    if ($ilk !== '') $yazarSay[$ilk] = ($yazarSay[$ilk] ?? 0) + 1;
    foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
        if (!is_array($ya)) continue;
        $n = trim((string)($ya['ad'] ?? '')); if ($n !== '') $yazarSay[$n] = ($yazarSay[$n] ?? 0) + 1;
    }
    foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
        if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
        $n = trim((string)($h['ad'] ?? '')); if ($n !== '') $hakemSay[$n] = ($hakemSay[$n] ?? 0) + 1;
    }
    if (preg_match('/^(\d{4})/', (string)($y['tarih'] ?? ''), $m)) $yilSay[$m[1]] = ($yilSay[$m[1]] ?? 0) + 1;
    foreach (preg_split('/[,;]+/u', k_alan($y, 'anahtar')) ?: [] as $a) {
        $a = trim($a); if ($a !== '') $anahSay[$a] = ($anahSay[$a] ?? 0) + 1;
    }
}
arsort($yazarSay); arsort($hakemSay); krsort($yilSay); arsort($anahSay);

$suzVar = ($sorgu !== '' || $yazarAra !== '' || $turAra !== '' || $yilAra !== '' || $anahAra !== '' || $alanAra !== '');

$ekBas = k_kart_stil() . <<<CSS
<style>
/* ---- Araç çubuğu ----
   Sayfanın üstünde durur ve kaydırırken üst çubuğun hemen altına
   yapışır: arama ve sıralama her an elin altındadır. Zemin donuktur;
   saydam bir çubuğun altından geçen kart başlıkları okunurluğu
   bozuyordu. */
.arac{position:sticky;top:var(--ust);z-index:40;background:var(--zemin);
  border-bottom:1px solid var(--cizgi);padding-block:var(--b-3)}
.arac-ic{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:center}

/* Arama alanı. Alanın kendisi dizgeden gelir; burada yalnızca büyüteç
   imi ile temizleme düğmesinin alanın içine yerleşmesi ve iki yandaki
   dolgunun onlara yer açması var. */
.ara-kutu{position:relative;flex:1 1 250px;min-width:0;margin:0}
.ara-kutu > svg{position:absolute;left:var(--b-3);top:50%;transform:translateY(-50%);
  color:var(--metin-2);pointer-events:none}
.ara-kutu input{border-radius:var(--r-tam);padding-left:var(--b-7);padding-right:var(--b-7)}
.ara-sil{position:absolute;right:0;top:50%;transform:translateY(-50%)}
.ara-sil[hidden]{display:none}

/* Süzgeç seçicileri çubuğun sağ ucuna toplanır: solda arama, sağda
   daraltma. Seçim kutuları burada satırı doldurmaz, kendi genişliğinde
   durur. */
.suzgec{display:flex;gap:var(--b-2);flex-wrap:wrap;align-items:center;margin-left:auto;min-width:0;max-width:100%}
/* ÖLÇÜLEN KUSUR: 320 pikselde sayfa 115 piksel yana kayıyordu ve suçlu
   bu satırdaki <select>lerdi. Sebebi alan listesinin uzamasıydı: kurul
   isteğiyle on işletme alt alanı eklendi ve "Sayısal yöntemler ve
   yöneylem araştırması" gibi uzun bir seçenek, seçim kutusunu kendi
   genişliğine çekti. Bir seçim kutusu, EN UZUN seçeneğine göre
   genişlemeye devam ederse liste her büyüdüğünde sayfa yeniden taşar;
   yani kusur listede değil, kutunun daralamamasındadır. */
.suzgec>*{min-width:0;max-width:100%}
.suzgec select{max-width:100%;text-overflow:ellipsis}
.suzgec select{width:auto;max-width:100%}
.suzgec label{margin:0;font-size:var(--y-2);color:var(--metin-2)}

/* Düğme ailesi display verdiği için tarayıcının kendi [hidden] kuralını
   geçiyor; gizlenmesi gereken düğmeler için burada yeniden yazılır. */
.d[hidden]{display:none}
.sonuc-satir{padding-block:var(--b-3);font-size:var(--y-2);color:var(--metin-2)}
.daha{padding-top:var(--b-5)}

/* ---- Dizinler ---- */
.dizin-bolum{background:var(--yuzey-2);border-top:1px solid var(--cizgi)}
.dizin-liste{display:flex;flex-wrap:wrap;gap:var(--b-1)}
/* Sayı adın bir parçasıdır, ayrı bir renk almaz. */
.dizin-liste em{font-style:normal;font-weight:400}
.dizin-not{font-size:var(--y-2);color:var(--metin-2);margin-top:var(--b-3)}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'baslik' => k_c('Çalışmalar', 'Works'),
    'yol' => '/yazilar.php',
    'ek_bas' => $ekBas,
    /* Açıklama üç hâli de sayar: hakem raporu gelmiş çalışmalar, hakem
       bekleyenler ve hakemsiz yazılar. İkiye bölen eski cümle, rapor
       beklemekte olanı hakemli sayıyordu. */
    'aciklama' => k_c(
        'Kutadgu\'da yayımlanan bütün çalışmalar: hakem raporu gelmiş olanlar, hakem aranan çalışmalar ve hakemsiz yazılar. Açık erişim, ücretsiz tam metin.',
        'All works published on Kutadgu: works with reviewer reports, works seeking reviewers, and non reviewed pieces. Open access, free full text.'
    ),
]);
?>

<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Arşiv', 'Archive') ?></span>
      <h1><?= $yazarAra !== '' ? k_esc($yazarAra) : k_c('Bütün çalışmalar', 'All works') ?></h1>
      <?php /* Eski cümle arşivi ikiye bölüyordu: hakemli ve hakemsiz.
               Üçüncü bir hâl var ve en kalabalık olan o olabilir:
               hakemliğe açılmış ama henüz tek raporu bile gelmemiş
               çalışma. Cümle o hâli de saysın ve söz vermesin. */ ?>
      <p><?= k_c(
        'Bütün çalışmalar tek arşivdedir. Bir çalışmanın hakemliğe açılması onu hakemlenmiş yapmaz: ilk rapor gelene kadar hakem aranıyor olarak durur. Tam metinlerin hepsi ücretsizdir.',
        'Every work sits in one archive. Opening a work to review does not make it reviewed: it stands as seeking reviewers until the first report arrives. Every full text is free of charge.'
      ) ?></p>
      <?php if ($suzVar): ?>
        <p><a href="<?= k_esc(k_bag('/yazilar.php')) ?>">&larr; <?= k_c('Bütün süzgeçleri kaldır', 'Clear all filters') ?></a></p>
      <?php endif; ?>
    </div>
    <div class="sayfa-olcu">
      <div><b><?= k_esc(k_sayi($say['toplam'])) ?></b><span><?= k_c('çalışma', 'works') ?></span></div>
      <div><b><?= k_esc(k_sayi($say['hakemli'])) ?></b><span><?= k_c('hakemli', 'reviewed') ?></span></div>
      <?php /* Hakem bekleyen çalışmalar ayrı sayılır: hakemli sayısının
               içinde erirse şerit, gelmemiş raporları gelmiş gösterir.
               Anahtar k/veri.php'de eklenmektedir; o dosya ayrı ellerde
               olduğu için burada korumalı okunur. */ ?>
      <div><b><?= k_esc(k_sayi($say['aranan'] ?? 0)) ?></b><span><?= k_c('hakem aranıyor', 'seeking reviewers') ?></span></div>
      <div><b><?= k_esc(k_sayi($say['yazar'])) ?></b><span><?= k_c('yazar', 'authors') ?></span></div>
      <div><b><?= k_esc(k_sayi($say['okuma'])) ?></b><span><?= k_c('okuma', 'reads') ?></span></div>
    </div>
  </div>
</section>

<form class="arac" id="aracForm" method="get" action="/yazilar.php">
  <?php if ($en): ?><input type="hidden" name="lang" value="en"><?php endif; ?>
  <?php if ($yazarAra !== ''): ?><input type="hidden" name="yazar" value="<?= k_esc($yazarAra) ?>"><?php endif; ?>
  <?php /* Çipler düğmedir, formu göndermez; betiksiz tarayıcıda tür
           süzgeci yalnızca adresten gelir. Gizli alan olmazsa yıl ya da
           sıralama değiştiren betiksiz okuyucu tür süzgecini elinden
           kaçırır ve betikli okuyucudan başka bir liste görür. */ ?>
  <?php if ($turAra !== ''): ?><input type="hidden" name="tur" value="<?= k_esc($turAra) ?>"><?php endif; ?>
  <div class="kap arac-ic">
    <label class="ara-kutu">
      <?= kim_ikon('ara', 16) ?>
      <span class="gizle"><?= k_c('Ara', 'Search') ?></span>
      <input id="ara" name="q" type="search" autocomplete="off" value="<?= k_esc($sorgu) ?>"
             placeholder="<?= k_c('Başlık, yazar, anahtar sözcük ya da özette ara...', 'Search title, author, keyword or abstract...') ?>">
      <button class="d d-sessiz d-im ara-sil" type="button" id="araSil" aria-label="<?= k_c('Aramayı temizle', 'Clear search') ?>" <?= $sorgu === '' ? 'hidden' : '' ?>>&times;</button>
    </label>

    <div class="d-kume" id="cipler">
      <button class="d d-ikinci d-kucuk" type="button" data-suz="hepsi" aria-pressed="<?= $turAra === '' ? 'true' : 'false' ?>"><?= k_c('Hepsi', 'All') ?></button>
      <?php /* Dört çip bütünü böler: "Hakemli" artık raporu gelmiş
               olanları, "Hakem aranıyor" hakemli yolda olup henüz
               raporu olmayanları gösterir. Kesişmezler ve aralarında
               boşluk kalmaz; ölçüt k_suz_tur() ile aynıdır. */ ?>
      <button class="d d-ikinci d-kucuk" type="button" data-suz="hakemli" aria-pressed="<?= $turAra === 'hakemli' ? 'true' : 'false' ?>"><?= k_c('Hakemli', 'Peer reviewed') ?></button>
      <?php /* İki çipin adı bir aşamanın adıdır ve tek kaynaktan okunur;
               düğmede başka, kartın rozetinde başka yazması aynı listeyi
               iki ayrı şey gibi gösterirdi. "Hakemli" çipi ise tek bir
               aşama değil, raporu gelmiş bütün aşamaları toplar. */ ?>
      <button class="d d-ikinci d-kucuk" type="button" data-suz="aranan" aria-pressed="<?= $turAra === 'aranan' ? 'true' : 'false' ?>"><?= k_esc(tg_asama_metni('aranan', $en)) ?></button>
      <button class="d d-ikinci d-kucuk" type="button" data-suz="yazi" aria-pressed="<?= $turAra === 'yazi' ? 'true' : 'false' ?>"><?= k_esc(tg_asama_metni('yok', $en, true)) ?></button>
    </div>

    <div class="suzgec">
      <label class="gizle" for="yilSec"><?= k_c('Yıl', 'Year') ?></label>
      <select id="yilSec" name="yil">
        <option value=""><?= k_c('Bütün yıllar', 'All years') ?></option>
        <?php foreach ($yilSay as $yl => $n): ?>
          <option value="<?= k_esc((string)$yl) ?>"<?= $yilAra === (string)$yl ? ' selected' : '' ?>><?= k_esc((string)$yl) ?> (<?= (int)$n ?>)</option>
        <?php endforeach; ?>
      </select>

      <?php
      /* Yalnızca arşivde karşılığı olan alanlar listelenir; boş bir
         seçenek kimseye yardımcı olmaz. */
      $alanDolu = [];
      foreach ($yazilar as $yy) {
          foreach (al_kayit_kodlari($yy) as $kk) {
              $alanDolu[$kk] = true;
              $uu = al_ust($kk);
              while ($uu !== '') { $alanDolu[$uu] = true; $uu = al_ust($uu); }
          }
      }
      ?>
      <?php /* ---------- BİLİM ALANI SÜZGECİ ----------
               ESKİDEN YALNIZ DOLU ALANLAR LİSTELENİYORDU. Gerekçesi
               makuldü: sonuç vermeyecek bir kapıyı açmamak. Ama bedeli
               ölçüldü — arşivde iki çalışma varken listede iki satır
               kalıyor ve süzgeç bozuk görünüyordu. Bildirilen cümle de
               buydu: "alanlar tam gözükmüyor, işlevsiz duruyor."

               Artık sınıflandırmanın TAMAMI listeleniyor: yedi temel
               alan, kırk iki bilim alanı, yüz seksen dokuz bilim dalı.
               Dolu olanın yanında çalışma sayısı yazıyor; boş olan da
               seçilebiliyor ve seçildiğinde "bu dalda henüz çalışma
               yok" diye dürüst bir cevap veriyor.

               Sayı bir SÖZ değil bir ölçümdür: her satırda o alanın
               ALTINDAKİ bütün çalışmalar sayılır, tek tek dalları
               toplamak gerekmez. */ ?>
      <label class="gizle" for="alanSec"><?= k_esc(al_secici_etiket($en)) ?></label>
      <select id="alanSec" name="alan">
        <option value=""><?= k_esc(al_hepsi_etiket($en)) ?></option>
        <?= al_secenek_html($alanAra, al_sayac($yazilar), $en) ?>
      </select>

      <label class="gizle" for="anahSec"><?= k_c('Anahtar sözcük', 'Keyword') ?></label>
      <select id="anahSec" name="anahtar">
        <option value=""><?= k_c('Bütün anahtar sözcükler', 'All keywords') ?></option>
        <?php foreach (array_slice($anahSay, 0, 40, true) as $a => $n): ?>
          <option value="<?= k_esc((string)$a) ?>"<?= mb_strtolower($anahAra, 'UTF-8') === mb_strtolower((string)$a, 'UTF-8') ? ' selected' : '' ?>><?= k_esc((string)$a) ?> (<?= (int)$n ?>)</option>
        <?php endforeach; ?>
      </select>

      <label for="siraSec"><?= k_c('Sırala', 'Sort') ?></label>
      <select id="siraSec" name="sirala">
        <option value="yeni"<?= $sirala === 'yeni' ? ' selected' : '' ?>><?= k_c('En yeni önce', 'Newest first') ?></option>
        <option value="eski"<?= $sirala === 'eski' ? ' selected' : '' ?>><?= k_c('En eski önce', 'Oldest first') ?></option>
        <option value="okunan"<?= $sirala === 'okunan' ? ' selected' : '' ?>><?= k_c('En çok okunan', 'Most read') ?></option>
        <option value="ad"<?= $sirala === 'ad' ? ' selected' : '' ?>><?= k_c('Başlığa göre', 'By title') ?></option>
      </select>
      <noscript><button class="d d-vurgu d-kucuk" type="submit"><?= k_c('Uygula', 'Apply') ?></button></noscript>
    </div>
  </div>
</form>

<section class="bolum bolum-bitisik">
  <div class="kap">
    <div class="satir sonuc-satir">
      <span id="sonuc" aria-live="polite"></span>
      <button class="d d-sessiz d-kucuk" type="button" id="silHepsi" hidden><?= k_c('Süzgeçleri kaldır', 'Clear filters') ?></button>
    </div>

    <?php if (!$goster): ?>
      <div class="bos-durum"><?= $suzVar
        ? k_c('Bu ölçütlere uyan çalışma bulunamadı.', 'No work matches these criteria.')
        : k_c('Henüz çalışma yayımlanmadı.', 'No works published yet.') ?></div>
    <?php else: ?>
      <?php /* Liste bölümünün başlığı. Görünmez ama VARDIR: kart
               başlıkları h3'tür ve sayfada h1'den sonra doğrudan h3
               geliyordu; ekran okuyucunun gördüğü belge yapısında bir
               basamak eksikti (WCAG 1.3.1). Görünür bir başlık
               konmadı çünkü sayfanın h1'i zaten "Bütün çalışmalar"
               diyor; ikinci kez yazmak ekranda gereksiz tekrar olurdu.
               display:none KULLANILMAZ: o, başlığı erişilebilirlik
               ağacından da siler ve sorunu çözmüş gibi görünüp
               çözmez. */ ?>
      <h2 class="gorsel-gizli"><?= k_c('Çalışma listesi', 'List of works') ?></h2>
      <div class="dizi dizi-2" id="dizi">
        <?php foreach ($goster as $y) echo k_yazi_kart($y); ?>
      </div>
      <div class="metin-orta daha" id="dahaSar" hidden>
        <button class="d d-ikinci" type="button" id="daha"><?= k_c('Daha fazla göster', 'Show more') ?></button>
      </div>
      <div id="bosMesaj" class="bos-durum" style="display:none"><?= k_c('Eşleşen çalışma bulunamadı.', 'No matching work found.') ?></div>
    <?php endif; ?>
  </div>
</section>

<!-- ================= DİZİNLER ================= -->
<section class="bolum dizin-bolum">
  <div class="kap dizi dizi-2">
    <div>
      <h3 class="bas-ust"><?= k_c('Yazarlar', 'Authors') ?></h3>
      <div class="dizin-liste">
        <?php foreach ($yazarSay as $ad => $n): ?>
          <?php $secili = tg_ad_anahtar($yazarAra) === tg_ad_anahtar((string)$ad); ?>
          <a class="rz <?= $secili ? 'rz-kut' : 'rz-cizgi' ?>" href="<?= k_esc(k_bag('/yazilar.php?yazar=' . rawurlencode((string)$ad))) ?>"<?= $secili ? ' aria-current="true"' : '' ?>><?= k_esc($ad) ?><em><?= (int)$n ?></em></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php if ($anahSay): ?>
    <div>
      <h3 class="bas-ust"><?= k_c('Anahtar sözcükler', 'Keywords') ?></h3>
      <div class="dizin-liste">
        <?php foreach (array_slice($anahSay, 0, 30, true) as $a => $n): ?>
          <?php $secili = mb_strtolower($anahAra, 'UTF-8') === mb_strtolower((string)$a, 'UTF-8') && $anahAra !== ''; ?>
          <a class="rz <?= $secili ? 'rz-kut' : 'rz-cizgi' ?>" href="<?= k_esc(k_bag('/yazilar.php?anahtar=' . rawurlencode((string)$a))) ?>"<?= $secili ? ' aria-current="true"' : '' ?>><?= k_esc($a) ?><em><?= (int)$n ?></em></a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($hakemSay): ?>
    <div>
      <h3 class="bas-ust"><?= k_c('Hakemler', 'Reviewers') ?></h3>
      <div class="dizin-liste">
        <?php foreach ($hakemSay as $ad => $n): ?>
          <a class="rz rz-cizgi" href="#" onclick="return false" style="cursor:default"><?= k_esc($ad) ?><em><?= (int)$n ?></em></a>
        <?php endforeach; ?>
      </div>
      <p class="dizin-not"><?= k_c(
        'Hakemler değerlendirmelerinin arkasında adlarıyla durur. Raporların tamamı ilgili çalışmanın sayfasında okunabilir.',
        'Reviewers stand behind their assessments by name. Every report can be read in full on the relevant work\'s page.'
      ) ?></p>
    </div>
    <?php endif; ?>
  </div>
</section>

<?php
k_son(<<<JS
<script>
/* Arşiv süzgeci.
   Kartlar sunucudan gelmiştir; burada yalnızca gösterilir, gizlenir ve
   yeniden sıralanır. Betik çalışmasa da liste, süzgeçler ve sıralama
   form gönderimiyle sunucu tarafında çalışmayı sürdürür. */
(function(){
  var SAYFA = 24;                       /* bir seferde gösterilen kart */
  var D = document;
  var form   = D.getElementById('aracForm'),
      ara    = D.getElementById('ara'),
      araSil = D.getElementById('araSil'),
      sonuc  = D.getElementById('sonuc'),
      bos    = D.getElementById('bosMesaj'),
      cipler = D.getElementById('cipler'),
      dizi   = D.getElementById('dizi'),
      dahaSar= D.getElementById('dahaSar'),
      daha   = D.getElementById('daha'),
      silHep = D.getElementById('silHepsi'),
      yilSec = D.getElementById('yilSec'),
      anahSec= D.getElementById('anahSec'),
      alanSec= D.getElementById('alanSec'),
      siraSec= D.getElementById('siraSec');
  if (!dizi) return;

  var EN = D.documentElement.lang === 'en';
  var kartlar = Array.prototype.slice.call(dizi.querySelectorAll('[data-ara]'));
  var toplam = kartlar.length;
  var suz = 'hepsi', metin = '', gorunen = SAYFA;

  var ilk = cipler && cipler.querySelector('[aria-pressed="true"]');
  if (ilk) suz = ilk.getAttribute('data-suz');
  if (ara) metin = ara.value.toLocaleLowerCase(EN ? 'en' : 'tr');

  /* Türkçe harfleri karşılığına indirger: "çetin" ile "cetin" eşleşsin */
  function sade(s){
    return String(s).toLocaleLowerCase('tr')
      .replace(/ı/g,'i').replace(/ş/g,'s').replace(/ğ/g,'g')
      .replace(/ü/g,'u').replace(/ö/g,'o').replace(/ç/g,'c')
      .replace(/â/g,'a').replace(/î/g,'i').replace(/û/g,'u');
  }
  function bicim(n){
    return EN ? (n + (n === 1 ? ' work' : ' works') + ' shown' + (n < toplam ? ' of ' + toplam : ''))
              : (toplam + ' çalışmadan ' + n + ' tanesi gösteriliyor');
  }

  function sirala(){
    var k = siraSec ? siraSec.value : 'yeni';
    var s = kartlar.slice();
    s.sort(function(a,b){
      var at = a.getAttribute('data-tarih') || '', bt = b.getAttribute('data-tarih') || '';
      if (k === 'eski')   return at < bt ? -1 : at > bt ? 1 : 0;
      if (k === 'okunan') return (+b.getAttribute('data-oku')||0) - (+a.getAttribute('data-oku')||0);
      if (k === 'ad')     return (a.getAttribute('data-bas')||'').localeCompare(b.getAttribute('data-bas')||'', 'tr');
      return at > bt ? -1 : at < bt ? 1 : 0;
    });
    var p = D.createDocumentFragment();
    s.forEach(function(c){ p.appendChild(c); });
    dizi.appendChild(p);
    kartlar = s;
  }

  function uygula(sifirla){
    if (sifirla !== false) gorunen = SAYFA;
    var yil  = yilSec ? yilSec.value : '',
        anah = anahSec ? sade(anahSec.value) : '',
        alan = alanSec ? alanSec.value : '',
        g = 0, c = 0;
    kartlar.forEach(function(k){
      var t = suz === 'hepsi' || k.getAttribute('data-tur') === suz;
      var a = metin === '' || (k.getAttribute('data-ara')||'').indexOf(metin) !== -1
              || sade(k.getAttribute('data-ara')||'').indexOf(sade(metin)) !== -1;
      var y = yil === '' || k.getAttribute('data-yil') === yil;
      var n = anah === '' || sade(k.getAttribute('data-anah')||'').split('|').indexOf(anah) !== -1;
      /* Alan kodu: kartta kodun kendisi ve üst basamakları yazılıdır,
         bu yüzden düz eşleşme yeter. */
      var f = alan === '' || (k.getAttribute('data-alan')||'').split('|').indexOf(alan) !== -1;
      var uyar = t && a && y && n && f;
      if (uyar) { g++; c++; k.style.display = c > gorunen ? 'none' : ''; }
      else k.style.display = 'none';
    });
    if (sonuc) sonuc.textContent = bicim(Math.min(g, gorunen));
    if (bos) bos.style.display = g ? 'none' : '';
    if (dahaSar) { if (g > gorunen) dahaSar.removeAttribute('hidden'); else dahaSar.setAttribute('hidden',''); }
    if (silHep) {
      var acik = suz !== 'hepsi' || metin !== '' || yil !== '' || anah !== '' || alan !== '';
      if (acik) silHep.removeAttribute('hidden'); else silHep.setAttribute('hidden','');
    }
    if (araSil) { if (metin !== '') araSil.removeAttribute('hidden'); else araSil.setAttribute('hidden',''); }
  }

  if (ara) {
    var z;
    ara.addEventListener('input', function(){
      clearTimeout(z);
      z = setTimeout(function(){ metin = ara.value.toLocaleLowerCase(EN ? 'en' : 'tr'); uygula(); }, 110);
    });
    /* Enter sunucuya göndermesin: sonuç zaten ekranda */
    if (form) form.addEventListener('submit', function(e){ e.preventDefault(); ara.blur(); });
  }
  if (araSil) araSil.addEventListener('click', function(){ ara.value=''; metin=''; uygula(); ara.focus(); });
  if (cipler) cipler.addEventListener('click', function(e){
    var b = e.target.closest('[data-suz]'); if (!b) return;
    suz = b.getAttribute('data-suz');
    Array.prototype.forEach.call(cipler.querySelectorAll('[data-suz]'), function(x){
      x.setAttribute('aria-pressed', x === b ? 'true' : 'false');
    });
    uygula();
  });
  if (yilSec)  yilSec.addEventListener('change', function(){ uygula(); });
  if (anahSec) anahSec.addEventListener('change', function(){ uygula(); });
  if (alanSec) alanSec.addEventListener('change', function(){ uygula(); });
  if (siraSec) siraSec.addEventListener('change', function(){ sirala(); uygula(); });
  if (daha) daha.addEventListener('click', function(){ gorunen += SAYFA; uygula(false); });
  if (silHep) silHep.addEventListener('click', function(){
    suz = 'hepsi'; metin = '';
    if (ara) ara.value = '';
    if (yilSec) yilSec.value = '';
    if (anahSec) anahSec.value = '';
    if (alanSec) alanSec.value = '';
    if (cipler) Array.prototype.forEach.call(cipler.querySelectorAll('[data-suz]'), function(x){
      x.setAttribute('aria-pressed', x.getAttribute('data-suz') === 'hepsi' ? 'true' : 'false');
    });
    uygula();
  });

  uygula();
})();
</script>
JS);
