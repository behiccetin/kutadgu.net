<?php
/* =====================================================================
   KUTADGU - Arama / Search
   ---------------------------------------------------------------------
   Temel arama, gelişmiş arama ve sonuç çözümlemesi tek sayfada durur.
   Üçü de aynı adres üzerinden çalışır: bir aramanın bağlantısı
   paylaşıldığında aynı sonucu açar, arama motoru da aynı sonucu görür.
   Betiksiz tarayıcıda da çalışır; JavaScript yalnızca satır eklemeyi
   ve çözümleme kutusunu açıp kapamayı kolaylaştırır.

   Adres değişkenleri:
     a1,f1     birinci satırın alanı ve ifadesi
     o2,a2,f2  ikinci satırın işleci, alanı, ifadesi (beşe kadar)
     alan      bilim alanı kodu
     yb,ys     yıl aralığı
     tur       hakemli | yazi
     onayli    1
     yazar,kurum,hakem,anahtar   çözümlemeden gelen daraltmalar
     sirala    yeni | eski | ad | okunan | rapor
     coz       1 ise çözümleme açık
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/parca.php';
require_once __DIR__ . '/k/arama.php';

$en = k_en();
$ALANLAR = ar_alanlar($en);
$ISLEC   = ar_islecler($en);

/* ---------- Adresten sorgu ---------- */
$satirlar = [];
for ($i = 1; $i <= 5; $i++) {
    $ifade = trim((string)($_GET['f' . $i] ?? ''));
    if ($ifade === '') continue;
    $alan = (string)($_GET['a' . $i] ?? 'hepsi');
    if (!isset($ALANLAR[$alan])) $alan = 'hepsi';
    $islec = (string)($_GET['o' . $i] ?? 've');
    if (!isset($ISLEC[$islec])) $islec = 've';
    $satirlar[] = ['alan' => $alan, 'ifade' => mb_substr($ifade, 0, 200), 'islec' => $islec];
}

$suz = [
    'alan'    => trim((string)($_GET['alan'] ?? '')),
    'yil_bas' => preg_match('/^\d{4}$/', (string)($_GET['yb'] ?? '')) ? (string)$_GET['yb'] : '',
    'yil_son' => preg_match('/^\d{4}$/', (string)($_GET['ys'] ?? '')) ? (string)$_GET['ys'] : '',
    /* Üç değer, arşiv sayfasındakiyle aynı: 'hakemli' raporu gelmiş,
       'aranan' hakemli yolda ama raporsuz, 'yazi' hakemsiz. Üçüncü
       değer eksik kaldığında raporsuz çalışmaya ulaşmanın adresi
       olmuyordu ve okur onu 'hakemli' içinde arıyordu. */
    'tur'     => in_array((string)($_GET['tur'] ?? ''), ['hakemli', 'aranan', 'yazi'], true) ? (string)$_GET['tur'] : '',
    'onayli'  => (string)($_GET['onayli'] ?? '') === '1',
    'yazar'   => trim((string)($_GET['yazar'] ?? '')),
    'kurum'   => trim((string)($_GET['kurum'] ?? '')),
    'hakem'   => trim((string)($_GET['hakem'] ?? '')),
    'anahtar' => trim((string)($_GET['anahtar'] ?? '')),
];
if ($suz['alan'] !== '' && !al_gecerli($suz['alan'])) $suz['alan'] = '';

$sirala = (string)($_GET['sirala'] ?? 'yeni');
if (!in_array($sirala, ['yeni', 'eski', 'ad', 'okunan', 'rapor'], true)) $sirala = 'yeni';
$cozAcik = (string)($_GET['coz'] ?? '') === '1';
$gelismis = count($satirlar) > 1 || (string)($_GET['g'] ?? '') === '1';

/* ---------- Arama ---------- */
$hepsi  = k_yazilar();
$sonuc  = ar_satirlar($hepsi, $satirlar);
$sonuc  = ar_suz($sonuc, $suz + ['alan' => $suz['alan'] !== '' ? [$suz['alan']] : []]);
$sonuc  = ar_sirala($sonuc, $sirala);
$coz    = ar_coz($sonuc);
$arandi = $satirlar || array_filter($suz, fn($v) => $v !== '' && $v !== false && $v !== []);

/* Adres kurucu: mevcut ölçütleri koruyarak tek bir değeri değiştirir */
function ara_bag(array $degis = [], array $sil = []): string {
    $q = $_GET;
    unset($q['lang']);
    foreach ($degis as $k => $v) { $q[$k] = $v; }
    foreach ($sil as $k) { unset($q[$k]); }
    $q = array_filter($q, fn($v) => $v !== '' && $v !== null);
    $s = http_build_query($q);
    return k_bag('/ara.php' . ($s !== '' ? '?' . $s : ''));
}

$ekBas = <<<CSS
<style>
.ar-sek{margin-bottom:var(--b-4)}
.ar-kutu{margin-bottom:var(--b-5)}

/* Arama satırı: işleç, alan ve aranan ifade yan yana. Aranan ifade
   ötekilerin iki katı yer alır ve dar ekranda hepsi kendiliğinden
   alt alta iner; ayrı bir eşik yazmaya gerek kalmaz. */
.ar-sat{display:flex;flex-wrap:wrap;gap:var(--b-2);align-items:flex-end;margin-bottom:var(--b-3)}
.ar-sat > div{flex:1 1 180px;min-width:0}
.ar-sat > div:last-child{flex:2 1 240px}
.ar-dg{margin-top:var(--b-4)}

/* Daraltma alanları. Onay kutusu, yanındaki seçim kutularının alt
   kenarına hizalanır; yoksa satırın ortasında asılı kalıyordu.
   Kutunun kendisi burada tek başına duran bir süzgeçtir, bir form
   listesinin maddesi değil: dizgedeki 19 piksellik ölçü telefonda
   ıskalandığı için yanındaki seçim kutularıyla aynı yüksekliğe
   getirilir. */
.ar-suz{display:grid;gap:var(--b-3);grid-template-columns:repeat(auto-fit,minmax(165px,1fr));
  margin-top:var(--b-4);padding-top:var(--b-4);border-top:1px solid var(--cizgi)}
.ar-suz .onay{align-self:end;align-items:center}
.ar-suz .onay > input{width:var(--hedef);height:var(--hedef);margin-top:0}

.ar-ust{justify-content:space-between;margin:var(--b-5) 0 var(--b-3);
  padding-bottom:var(--b-3);border-bottom:1px solid var(--cizgi)}
.ar-say{margin:0}
.ar-say b{font-family:var(--mono);color:var(--kut)}
.ar-sirala label{margin:0;font-size:var(--y-2);color:var(--metin-2)}
.ar-sirala select{width:auto;max-width:100%}

/* Etkin daraltma etiketi. Kaldırma imi tek başına duran bir dokunma
   hedefidir: görsel ölçüsü küçük kalsın diye punto iner, yükseklik
   inmez. */
.ar-etiketler{margin-bottom:var(--b-3)}
.ar-et{padding-block:0;padding-right:var(--b-1)}
.ar-et a{display:inline-flex;align-items:center;justify-content:center;
  min-height:var(--hedef);min-width:var(--b-5);color:inherit}
.ar-et a:hover{color:var(--kirmizi);text-decoration:none}

.ar-liste{margin-bottom:var(--b-6)}
.ar-k{display:grid;gap:var(--b-2)}
.ar-k h3{margin:0}
.ar-k h3 a{color:var(--metin)}
.ar-k h3 a:hover{color:var(--kut)}
.ar-kim,.ar-oz{margin:0;font-size:var(--y-3);color:var(--metin-2)}
.ar-rz{display:flex;flex-wrap:wrap;gap:var(--b-1)}
.ar-tamga{font-family:var(--mono)}

/* ---- Çözümleme ----
   Her satır bir daraltmadır, yani tıklanır: bu yüzden satırın
   yüksekliği dokunma hedefinden küçük olamaz. Kutular da gerilmez;
   üç maddesi olan kutunun altında on maddelik kadar boşluk kalırdı. */
.ar-coz{margin-bottom:var(--b-6);align-items:start}
.ar-cz ol{list-style:none;margin:0;padding:0;display:grid;gap:var(--b-2)}
.ar-cz li{display:grid;gap:var(--b-1)}
.ar-cz a{display:flex;align-items:center;justify-content:space-between;gap:var(--b-3);
  min-height:var(--hedef);font-size:var(--y-3);color:var(--metin);line-height:var(--sh-orta)}
.ar-cz a:hover{color:var(--kut);text-decoration:none}
.ar-cz b{flex:none;font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2)}
/* Sayının büyüklüğünü gösteren çubuk. Süs değil ölçek: kümedeki en
   büyük değere göre oranlanır. */
.ar-cub{height:var(--b-1);border-radius:var(--r-tam);background:var(--kut-zemin);overflow:hidden}
.ar-cub span{display:block;height:100%;background:var(--kut)}

/* Örnek sorgular gövde metninden ayrılsın diye çerçeveli yazılır. */
.ipucu code,.bos-durum code{background:var(--yuzey-2);border:1px solid var(--cizgi);
  border-radius:var(--r-1);padding:0 var(--b-1)}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Arama', 'Search'),
    'yol'    => '/ara.php',
    'aciklama' => k_c(
        'Arşivde başlık, yazar, özet, anahtar kelime, kurum, hakem ve tam metin üzerinden arama; sonuçları alana, yıla, yazara ve kuruma göre çözümleme.',
        'Search the archive by title, author, abstract, keywords, institution, reviewer and full text; analyse the results by field, year, author and institution.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Arşiv araması', 'Archive search') ?></span>
      <h1><?= k_c('Arama', 'Search') ?></h1>
      <p><?= k_c(
        'Arşivin tamamı aranabilir: başlık, yazar, özet, anahtar kelime, kurum, hakem adı, tamga ve tam metin. Gelişmiş aramada satırlar <b>VE</b>, <b>VEYA</b> ve <b>HARİÇ</b> ile birleştirilir. Sonuçları çözümleyerek hangi alanda, hangi yılda, kimin ne kadar çalışması olduğunu görebilir ve tek tıkla daraltabilirsiniz.',
        'The whole archive is searchable: title, author, abstract, keywords, institution, reviewer name, identifier and full text. In advanced search the rows are combined with <b>AND</b>, <b>OR</b> and <b>NOT</b>. Analysing the results shows how they fall by field, year and person, and each figure narrows the search with one click.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <div class="d-kume ar-sek">
      <a href="<?= k_esc(ara_bag([], ['g', 'coz'])) ?>" class="d d-ikinci<?= !$gelismis && !$cozAcik ? ' acik' : '' ?>"><?= k_c('Temel arama', 'Basic search') ?></a>
      <a href="<?= k_esc(ara_bag(['g' => '1'], ['coz'])) ?>" class="d d-ikinci<?= $gelismis && !$cozAcik ? ' acik' : '' ?>"><?= k_c('Gelişmiş arama', 'Advanced search') ?></a>
      <a href="<?= k_esc(ara_bag(['coz' => '1'])) ?>" class="d d-ikinci<?= $cozAcik ? ' acik' : '' ?>"><?= k_c('Sonuçları çözümle', 'Analyse results') ?></a>
    </div>

    <form class="kart ar-kutu" method="get" action="<?= k_esc(k_bag('/ara.php')) ?>">
      <?php if ($en): ?><input type="hidden" name="lang" value="en"><?php endif; ?>
      <?php if ($gelismis): ?><input type="hidden" name="g" value="1"><?php endif; ?>
      <?php if ($cozAcik): ?><input type="hidden" name="coz" value="1"><?php endif; ?>

      <?php
      /* Satırlar. Temel aramada tek satır; gelişmişte var olan satırların
         hepsi ve bir tane boş satır gösterilir, "satır ekle" ile çoğalır. */
      $goster = $satirlar;
      if (!$goster) $goster[] = ['alan' => 'hepsi', 'ifade' => '', 'islec' => 've'];
      if ($gelismis) $goster[] = ['alan' => 'hepsi', 'ifade' => '', 'islec' => 've'];
      $adet = $gelismis ? min(count($goster), 5) : 1;
      ?>
      <?php for ($i = 0; $i < $adet; $i++): $s = $goster[$i]; $n = $i + 1; ?>
      <div class="ar-sat">
        <?php if ($i > 0): ?>
        <div>
          <label for="o<?= $n ?>"><?= k_c('İşleç', 'Operator') ?></label>
          <select id="o<?= $n ?>" name="o<?= $n ?>">
            <?php foreach ($ISLEC as $ik => $ia): ?>
            <option value="<?= k_esc($ik) ?>"<?= ($s['islec'] ?? 've') === $ik ? ' selected' : '' ?>><?= k_esc($ia) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <div>
          <label for="a<?= $n ?>"><?= k_c('Alan', 'Field') ?></label>
          <select id="a<?= $n ?>" name="a<?= $n ?>">
            <?php foreach ($ALANLAR as $ak => $aa): ?>
            <option value="<?= k_esc($ak) ?>"<?= ($s['alan'] ?? 'hepsi') === $ak ? ' selected' : '' ?>><?= k_esc($aa) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="f<?= $n ?>"><?= k_c('Aranan', 'Query') ?></label>
          <input type="search" id="f<?= $n ?>" name="f<?= $n ?>" value="<?= k_esc($s['ifade'] ?? '') ?>"
                 placeholder="<?= k_c('kelime, &quot;tam öbek&quot; ya da kök*', 'word, &quot;exact phrase&quot; or stem*') ?>">
        </div>
      </div>
      <?php endfor; ?>

      <?php /* ---------- TEMEL ARAMADA DA BİLİM ALANI ----------
               Bu seçici yalnız GELİŞMİŞ aramada çiziliyordu. Sorgu
               katmanı alan süzgecini baştan beri destekliyordu, ama
               arayüzde yolu yoktu: "birisi gelip herhangi bir bilim
               dalında arama yapabilmeli" isteği tam olarak buraya
               düşüyor ve bir düğme arkasında duruyordu.

               Temel aramada tek bir ek alan yeter: ne aradığın ve
               nerede aradığın. Yıl, tür ve ötekiler gelişmişte kalır —
               temel aramayı gelişmişe çevirmenin anlamı yok. */ ?>
      <?php if (!$gelismis): ?>
      <div class="ar-sat ar-sat-alan">
        <div>
          <label for="tAlan"><?= k_esc(al_secici_etiket($en)) ?></label>
          <select id="tAlan" name="alan">
            <option value=""><?= k_esc(al_hepsi_etiket($en)) ?></option>
            <?= al_secenek_html((string)$suz['alan'], null, $en) ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($gelismis): ?>
      <div class="ar-suz">
        <div>
          <label for="sAlan"><?= k_esc(al_secici_etiket($en)) ?></label>
          <select id="sAlan" name="alan">
            <option value=""><?= k_esc(al_hepsi_etiket($en)) ?></option>
            <?= al_secenek_html((string)$suz['alan'], null, $en) ?>
          </select>
        </div>
        <div>
          <label for="sYb"><?= k_c('Yıl (başlangıç)', 'Year from') ?></label>
          <input type="number" id="sYb" name="yb" min="1900" max="2200" value="<?= k_esc($suz['yil_bas']) ?>">
        </div>
        <div>
          <label for="sYs"><?= k_c('Yıl (bitiş)', 'Year to') ?></label>
          <input type="number" id="sYs" name="ys" min="1900" max="2200" value="<?= k_esc($suz['yil_son']) ?>">
        </div>
        <div>
          <label for="sTur"><?= k_c('Tür', 'Type') ?></label>
          <select id="sTur" name="tur">
            <option value=""><?= k_c('Hepsi', 'All') ?></option>
            <?php /* Seçenek adları da ar_tur_ad()'dan gelir: aşama adı olan
                     ikisi tek kaynaktan okunur, listedeki rozetle aynı
                     sözü söylesinler. */ ?>
            <option value="hakemli"<?= $suz['tur'] === 'hakemli' ? ' selected' : '' ?>><?= k_esc(ar_tur_ad('hakemli')) ?></option>
            <option value="aranan"<?= $suz['tur'] === 'aranan' ? ' selected' : '' ?>><?= k_esc(ar_tur_ad('aranan')) ?></option>
            <option value="yazi"<?= $suz['tur'] === 'yazi' ? ' selected' : '' ?>><?= k_esc(ar_tur_ad('yazi')) ?></option>
          </select>
        </div>
        <label class="onay">
          <input type="checkbox" name="onayli" value="1"<?= $suz['onayli'] ? ' checked' : '' ?>>
          <span><?= k_c('Yalnızca hakem onaylı', 'Reviewer approved only') ?></span>
        </label>
      </div>
      <?php endif; ?>

      <div class="d-kume ar-dg">
        <button class="d d-vurgu" type="submit"><?= k_c('Ara', 'Search') ?></button>
        <?php if (!$gelismis): ?>
        <a class="d d-sessiz" href="<?= k_esc(ara_bag(['g' => '1'])) ?>"><?= k_c('Gelişmiş aramaya geç', 'Switch to advanced search') ?></a>
        <?php endif; ?>
        <?php if ($arandi): ?>
        <a class="d d-sessiz" href="<?= k_esc(k_bag('/ara.php')) ?>"><?= k_c('Temizle', 'Clear') ?></a>
        <?php endif; ?>
      </div>

      <p class="ipucu"><?= k_c(
        'Tam öbek için tırnak kullanın: <code>"panel veri"</code>. Kelime kökü için yıldız: <code>iktisad*</code>. Türkçe harfler ve büyük küçük harf farkı aranmaz: <code>cetin</code> ile <code>Çetin</code> aynı sonucu verir.',
        'Use quotation marks for an exact phrase: <code>"panel data"</code>. Use an asterisk for a stem: <code>econom*</code>. Case and Turkish diacritics are ignored: <code>cetin</code> and <code>Çetin</code> return the same.'
      ) ?></p>
    </form>

    <?php
    /* Etkin daraltmalar: çözümlemeden gelenler burada görünür ve
       tek tıkla kaldırılır. Kullanıcının hangi süzgeçle baktığını
       bilmemesi, yanlış sayıyı doğru sanmasına yol açar. */
    $etiket = [];
    if ($suz['alan'] !== '')   $etiket[] = [k_c('Alan', 'Field') . ': ' . al_ad($suz['alan'], $en), 'alan'];
    if ($suz['yazar'] !== '')  $etiket[] = [k_c('Yazar', 'Author') . ': ' . $suz['yazar'], 'yazar'];
    if ($suz['kurum'] !== '')  $etiket[] = [k_c('Kurum', 'Institution') . ': ' . $suz['kurum'], 'kurum'];
    if ($suz['hakem'] !== '')  $etiket[] = [k_c('Hakem', 'Reviewer') . ': ' . $suz['hakem'], 'hakem'];
    if ($suz['anahtar'] !== '')$etiket[] = [k_c('Anahtar kelime', 'Keyword') . ': ' . $suz['anahtar'], 'anahtar'];
    if ($suz['tur'] !== '')     $etiket[] = [k_c('Tür', 'Type') . ': ' . ar_tur_ad($suz['tur']), 'tur'];
    if ($suz['onayli'])         $etiket[] = [k_c('Hakem onaylı', 'Reviewer approved'), 'onayli'];
    if ($suz['yil_bas'] !== '') $etiket[] = [k_c('Yıl ≥ ', 'Year ≥ ') . $suz['yil_bas'], 'yb'];
    if ($suz['yil_son'] !== '') $etiket[] = [k_c('Yıl ≤ ', 'Year ≤ ') . $suz['yil_son'], 'ys'];
    ?>
    <?php if ($etiket): ?>
    <div class="d-kume ar-etiketler">
      <?php foreach ($etiket as [$ad, $anahtar]): ?>
      <span class="rz rz-kut ar-et"><?= k_esc($ad) ?><a href="<?= k_esc(ara_bag([], [$anahtar])) ?>" title="<?= k_c('kaldır', 'remove') ?>">×</a></span>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="satir ar-ust">
      <p class="ar-say"><b><?= count($sonuc) ?></b> <?= k_c('sonuç', 'results') ?><?php
        if (!$arandi) echo ' · ' . k_esc(k_c('arşivin tamamı', 'the whole archive'));
      ?></p>
      <form class="satir ar-sirala" method="get" action="<?= k_esc(k_bag('/ara.php')) ?>">
        <?php foreach ($_GET as $gk => $gv): if ($gk === 'sirala' || $gk === 'lang' || !is_string($gv)) continue; ?>
        <input type="hidden" name="<?= k_esc($gk) ?>" value="<?= k_esc($gv) ?>">
        <?php endforeach; ?>
        <?php if ($en): ?><input type="hidden" name="lang" value="en"><?php endif; ?>
        <label for="sirala"><?= k_c('Sırala', 'Sort') ?></label>
        <select id="sirala" name="sirala" onchange="this.form.submit()">
          <option value="yeni"<?= $sirala === 'yeni' ? ' selected' : '' ?>><?= k_c('En yeni', 'Newest') ?></option>
          <option value="eski"<?= $sirala === 'eski' ? ' selected' : '' ?>><?= k_c('En eski', 'Oldest') ?></option>
          <option value="ad"<?= $sirala === 'ad' ? ' selected' : '' ?>><?= k_c('Başlığa göre', 'By title') ?></option>
          <option value="okunan"<?= $sirala === 'okunan' ? ' selected' : '' ?>><?= k_c('En çok okunan', 'Most read') ?></option>
          <option value="rapor"<?= $sirala === 'rapor' ? ' selected' : '' ?>><?= k_c('En çok rapor alan', 'Most reports') ?></option>
        </select>
        <noscript><button class="d d-kucuk d-ikinci" type="submit"><?= k_c('Uygula', 'Apply') ?></button></noscript>
      </form>
    </div>

    <?php if ($cozAcik): ?>
      <?php
      /* Çözümleme eksenleri. Her satır bir daraltmadır: sayıya tıklayan
         kişi o kümeye iner. WoS'un "Analyze Results" ekranının yaptığı
         iş budur; buradaki farkı, sayıların hangi kayıttan geldiğinin
         de açık olması. */
      $eksenler = [
        ['alan',    k_c('Bilim alanı', 'Field of science'),   'alan'],
        ['yil',     k_c('Yıl', 'Year'),                        null],
        ['yazar',   k_c('Yazar', 'Author'),                    'yazar'],
        ['kurum',   k_c('Kurum', 'Institution'),               'kurum'],
        ['hakem',   k_c('Hakem', 'Reviewer'),                  'hakem'],
        ['anahtar', k_c('Anahtar kelime', 'Keyword'),          'anahtar'],
        ['karar',   k_c('Hakem kararı', 'Reviewer decision'),  null],
        ['tur',     k_c('Tür', 'Type'),                        null],
      ];
      ?>
      <div class="dizi dizi-2 ar-coz">
        <?php foreach ($eksenler as [$ek, $baslik, $param]): ?>
          <?php $liste = $coz[$ek] ?? []; if (!$liste) continue; $enUst = max(array_column($liste, 'say')); ?>
          <div class="kart ar-cz">
            <h3 class="bas-ust"><?= k_esc($baslik) ?> <span>(<?= count($liste) ?>)</span></h3>
            <ol>
              <?php foreach (array_slice($liste, 0, 10, true) as $deger => $bilgi): ?>
              <li>
                <?php if ($param !== null): ?>
                <a href="<?= k_esc(ara_bag([$param => (string)$deger])) ?>"><span><?= k_esc($bilgi['ad']) ?></span><b><?= (int)$bilgi['say'] ?></b></a>
                <?php elseif ($ek === 'yil'): ?>
                <a href="<?= k_esc(ara_bag(['yb' => (string)$deger, 'ys' => (string)$deger])) ?>"><span><?= k_esc($bilgi['ad']) ?></span><b><?= (int)$bilgi['say'] ?></b></a>
                <?php elseif ($ek === 'tur'): ?>
                <a href="<?= k_esc(ara_bag(['tur' => (string)$deger])) ?>"><span><?= k_esc($bilgi['ad']) ?></span><b><?= (int)$bilgi['say'] ?></b></a>
                <?php else: ?>
                <a href="#" onclick="return false" style="cursor:default"><span><?= k_esc($bilgi['ad']) ?></span><b><?= (int)$bilgi['say'] ?></b></a>
                <?php endif; ?>
                <span class="ar-cub"><span style="width:<?= max(3, (int)round(100 * $bilgi['say'] / max(1, $enUst))) ?>%"></span></span>
              </li>
              <?php endforeach; ?>
            </ol>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!$sonuc): ?>
      <div class="bos-durum"><?= k_c(
        'Bu ölçütlere uyan çalışma yok. Aramayı genişletmeyi deneyebilirsiniz: kelime kökünü yıldızla yazmak (<code>iktisad*</code>), alanı "her yerde" bırakmak ya da süzgeçleri kaldırmak çoğu zaman yeter.',
        'No work matches these criteria. Widening the search usually helps: write the stem with an asterisk (<code>econom*</code>), leave the field as "all fields", or remove the filters.'
      ) ?></div>
    <?php else: ?>
      <div class="dizi ar-liste">
        <?php foreach (array_slice($sonuc, 0, 60) as $y): ?>
          <?php
            $onay = tg_onay_durumu($y);
            /* Rozet kaydın 'tur' alanından değil hesaplanan aşamadan
               gelir: 'tur' yolu söyler, aşama o yolun neresinde
               olunduğunu. Raporsuz bir çalışmaya "Hakemli" demek onu
               değerlendirmeden geçmiş gösterirdi. */
            $asama = tg_hakem_asamasi($y);
            $rapor = 0;
            foreach (tg_dizi($y['hakemler'] ?? null) as $h) { if (is_array($h) && trim((string)($h['rapor'] ?? '')) !== '') $rapor++; }
            $kodlar = al_kayit_kodlari($y);
          ?>
          <article class="kart ar-k">
            <h3><a href="<?= k_esc(k_bag(k_yazi_yolu($y))) ?>"><?= k_esc(k_alan($y, 'baslik')) ?></a></h3>
            <p class="ar-kim"><?= k_esc(k_yazarlar($y)) ?> · <?= k_esc(substr((string)($y['tarih'] ?? ''), 0, 10)) ?></p>
            <?php $oz = k_ozet(k_alan($y, 'ozet'), 240); if ($oz !== ''): ?>
            <p class="ar-oz"><?= k_esc($oz) ?></p>
            <?php endif; ?>
            <div class="ar-rz">
              <?php /* Aşama rozeti 'onayli' durumunu kendisi söyler; ikisi
                       birden basılırsa aynı cümle iki kez yazılmış olur.
                       Onay rozeti yalnızca aşamanın onu gizlediği yerde
                       (geri çekilmiş ama onaylanmış çalışmada) kalır. */ ?>
              <?php if (!empty($onay['onayli']) && $asama !== 'onayli'): ?><span class="rz rz-kut"><?= k_c('Hakem onaylı', 'Reviewer approved') ?></span><?php endif; ?>
              <span class="rz <?= k_esc(tg_asama_rz($asama)) ?>"><?= k_esc(tg_asama_metni($asama, $en)) ?></span>
              <?php if ($rapor): ?><span class="rz rz-cizgi"><?= (int)$rapor ?> <?= k_c('rapor', 'reports') ?></span><?php endif; ?>
              <?php foreach (array_slice($kodlar, 0, 3) as $kd): ?>
              <span class="rz rz-cizgi"><?= k_esc(al_ad($kd, $en)) ?></span>
              <?php endforeach; ?>
              <?php if (trim((string)($y['bcid'] ?? '')) !== ''): ?><span class="rz rz-cizgi ar-tamga"><?= k_esc((string)$y['bcid']) ?></span><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <?php if (count($sonuc) > 60): ?>
      <p class="ipucu"><?= k_c(
        'İlk 60 sonuç gösteriliyor. Aramayı daraltmak için çözümleme kutusundaki sayıları kullanabilirsiniz.',
        'The first 60 results are shown. Use the figures in the analysis panel to narrow the search.'
      ) ?></p>
      <?php endif; ?>
    <?php endif; ?>
   </div>

   <?= k_belge_yan([], [
     ['tr' => 'Nasıl aranır', 'en' => 'How to search',
      'ic' => k_c(
        'Tırnak tam öbek arar, yıldız kelime kökü. Gelişmiş aramada satırlar VE, VEYA ve HARİÇ ile birleşir ve sırayla uygulanır.',
        'Quotation marks search an exact phrase, an asterisk a stem. In advanced search the rows combine with AND, OR and NOT, applied in order.'
      )],
     ['tr' => 'Sayılar nereden geliyor', 'en' => 'Where the figures come from',
      'ic' => k_c(
        'Çözümleme kutusundaki her sayı, o anda ekranda duran sonuç kümesinden sayılır; ayrı bir istatistik değildir. Bir sayıya tıkladığınızda arama o kümeye iner.',
        'Every figure in the analysis panel is counted from the result set currently on screen; it is not a separate statistic. Clicking a figure narrows the search to that set.'
      )],
     ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
      'ic' => '<a href="' . k_esc(k_bag('/yazilar.php')) . '">' . k_c('Bütün çalışmalar', 'All works') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/hakemler.php')) . '">' . k_c('Hakem dizini', 'Reviewer directory') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/istatistik.php')) . '">' . k_c('İstatistikler', 'Statistics') . '</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
