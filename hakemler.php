<?php
/* =====================================================================
   KUTADGU - Hakem dizini / Reviewer directory
   ---------------------------------------------------------------------
   Bu sistemde kör hakemlik yoktur: hakemin adı, kararı ve raporu
   çalışmayla birlikte açıkça yayımlanır. Bunu yapan bir sistemin
   hakemlerini gizli tutması tutarsız olurdu; dizin herkese açıktır.

   Dizinde yalnızca kişinin kendi verdiği bilgi görünür. E-posta adresi
   hiçbir koşulda gösterilmez; editör bir hakemi davet ederken bile
   adresi görmez, sunucu çözer. İsteyen kendini dizinden çıkarabilir:
   panelde tek bir onay kutusu yeter ve çıkmak hakemliği etkilemez.

   Sıralama alana yakınlığa göredir: aynı dal, aynı alt alan, aynı ana
   alan. Alan sorulmadığında yazılmış rapor sayısı esas alınır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/alanlar.php';
require_once __DIR__ . '/k/hesap.php';

/* Ölümcül bir hata olursa boş bir 500 ekranı yerine kayıt tutulur ve
   sistemin kendi hata sayfası gösterilir. */
if (function_exists('tg_hata_yakala')) tg_hata_yakala('hakemler.php');

$en = k_en();

/* ---- Dizin: hesaplardan kurulur ---- */
$rapor = [];
foreach (k_yazilar() as $y) {
    foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
        if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
        $k = tg_ad_anahtar((string)($h['ad'] ?? ''));
        if ($k !== '') $rapor[$k] = ($rapor[$k] ?? 0) + 1;
    }
}

$hakemler = [];
/* DENEME HESAPLARI DİZİNE GİRMEZ. hs_kamusal() okunur, hs_oku() değil:
   deneme düzeni kurulduğu an "Deneme Hakem" bu dizine düşüyordu.
   Süzgeç tek yerdedir (k/hesap.php); burada ayrı bir ölçüt yazılsaydı,
   bir gün ikisi ayrışırdı. */
foreach (hs_kamusal() as $h) {
    if (!is_array($h) || !empty($h['dizin_gizli'])) continue;
    $ad = trim((string)($h['ad'] ?? ''));
    if ($ad === '') continue;
    /* Yardımcı işlevlerin varlığı denetlenir: bu sayfa hesap modülüne
       dayanır ve o modül eksikse sayfanın çökmesi değil, boş görünmesi
       doğru davranıştır. */
    $roller = function_exists('hs_rolleri') ? hs_rolleri($h) : (array)($h['roller'] ?? []);
    $dd = function_exists('tg_dogrulama_durum')
        ? tg_dogrulama_durum($h['dogrulama'] ?? null)
        : ['durum' => (string)(($h['dogrulama']['durum'] ?? ''))];
    $yeterli = $dd['durum'] === 'onayli'
        || in_array('hakem', $roller, true)
        || in_array('editor', $roller, true)
        || in_array('bas_editor', $roller, true);
    if (!$yeterli) continue;
    $kodlar = al_kayit_kodlari($h);
    $hakemler[] = [
        'ad'      => $ad,
        'gorunen' => function_exists('hs_gorunen_ad') ? hs_gorunen_ad($h) : $ad,
        'kurum'   => (string)($h['kurum'] ?? ''),
        'orcid'   => (string)($h['orcid'] ?? ''),
        'resim'   => tg_resim_yolu($h),
        'yol'     => tg_kisi_yolu($ad),
        'kodlar'  => $kodlar,
        'rapor'   => (int)($rapor[tg_ad_anahtar($ad)] ?? 0),
        'kurulda' => in_array('bas_editor', $roller, true) || in_array('editor', $roller, true),
    ];
}

/* ---- Süzgeç ---- */
$sAlan = trim((string)($_GET['alan'] ?? ''));
$sAra  = trim((string)($_GET['q'] ?? ''));
if ($sAlan !== '' && !al_gecerli($sAlan)) $sAlan = '';

if ($sAra !== '') {
    $q = mb_strtolower($sAra, 'UTF-8');
    $hakemler = array_values(array_filter($hakemler, function ($k) use ($q) {
        $m = mb_strtolower($k['ad'] . ' ' . $k['kurum'] . ' ' . implode(' ', array_map('al_ad', $k['kodlar'])), 'UTF-8');
        return mb_strpos($m, $q) !== false;
    }));
}
if ($sAlan !== '') {
    foreach ($hakemler as $i => $k) {
        $en2 = 0;
        foreach ($k['kodlar'] as $b) { $y = al_yakinlik($sAlan, $b); if ($y > $en2) $en2 = $y; }
        $hakemler[$i]['yakin'] = $en2;
    }
    $hakemler = array_values(array_filter($hakemler, fn($k) => (int)($k['yakin'] ?? 0) > 0));
    usort($hakemler, fn($a, $b) => (($b['yakin'] ?? 0) <=> ($a['yakin'] ?? 0)) ?: ($b['rapor'] <=> $a['rapor']));
} else {
    usort($hakemler, fn($a, $b) => ($b['rapor'] <=> $a['rapor'])
        ?: strcmp(tg_ad_anahtar($a['ad']), tg_ad_anahtar($b['ad'])));
}

/* Alan süzgeci listesi: yalnızca dizinde karşılığı olan alanlar
   gösterilir; boş bir süzgeç seçeneği kimseye yardımcı olmaz. */
$dolu = [];
foreach ($hakemler as $k) {
    foreach ($k['kodlar'] as $c) {
        /* Kod her zaman metin olarak ele alınır. Bir dizi anahtarına
           yazılıp geri okunmuş bir kod tamsayıya dönmüş olabilir ve
           bu dosyada strict_types açıktır. */
        $c = (string)$c;
        $dolu[$c] = true;
        /* Zincirin TAMAMI yukarı yürünür. Eskiden yalnız bir basamak
           yukarı çıkılıyordu; bir dalın temel alanı hiç işaretlenmiyor
           ve süzgeçte temel alan diye bir seçenek olmuyordu. Oysa
           dizine "sosyal bilimlerde kim var" diye bakmak, en sık
           sorulan sorudur. */
        $u = al_ust($c); $adim = 0;
        while ($u !== '' && $adim++ < 8) { $dolu[$u] = true; $u = al_ust($u); }
    }
}

$ekBas = <<<CSS
<style>
/* Süzgeç şeridi. Alanların, etiketlerin ve düğmelerin görünüşü dizgeden
   gelir; burada yalnızca hepsinin tek satırda ve alt kenarlarından
   hizalı durması söyleniyor. */
.hd-suz{display:flex;flex-wrap:wrap;gap:var(--b-3);align-items:flex-end;margin-bottom:var(--b-5)}
.hd-suz > div{flex:1 1 200px;min-width:0}
.hd-suz .d{flex:none}

/* Yakınlık kümelerinin başlığı. Bir bölüm başlığı değil, listenin
   içindeki ayraçtır: bu yüzden serif yüzle değil arayüz yüzüyle ve
   küçük yazılır. */
.hd-bas{font-family:var(--ui);font-size:var(--y-2);font-weight:700;letter-spacing:.06em;
  text-transform:uppercase;color:var(--metin-2);margin:var(--b-6) 0 var(--b-3);
  padding-bottom:var(--b-2);border-bottom:1px solid var(--cizgi)}
.hd-bas small{font-family:var(--mono);letter-spacing:0;text-transform:none}
.hd-say{font-size:var(--y-3);color:var(--metin-2);margin:0 0 var(--b-4)}

/* Kartın içindeki alan rozetleri: kart dar olduğu için dizinin
   olağan boşluğundan daha sıkı dizilir. */
.hd-kod{display:flex;flex-wrap:wrap;gap:var(--b-1);margin-top:var(--b-2)}
.hd-liste{margin-bottom:var(--b-6)}
</style>
CSS;

k_bas([
    'olcu'   => 'genis',
    'tur'    => 'belge',
    'baslik' => k_c('Hakem dizini', 'Reviewer directory'),
    'yol'    => '/hakemler.php',
    'aciklama' => k_c(
        'Bu sistemde hakemlik yapan araştırmacılar ve çalışma alanları. Dizin herkese açıktır; e-posta adresi hiçbir yerde gösterilmez.',
        'The researchers who review in this system and the fields they work in. The directory is open to all; no e mail address is shown anywhere.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Açık dizin', 'Open directory') ?></span>
      <h1><?= k_c('Hakem dizini', 'Reviewer directory') ?></h1>
      <p><?= k_c(
        'Bu sistemde kör hakemlik yoktur: hakemin adı, kararı ve raporu çalışmayla birlikte yayımlanır. Bunu yapan bir sistemin hakemlerini gizli tutması tutarsız olurdu, bu yüzden dizin de açıktır. Görünen her şey kişinin kendi yazdığıdır; <b>e-posta adresi hiçbir yerde gösterilmez</b>. Editör bir hakemi davet ederken bile adresi görmez.',
        'There is no blind review in this system: a reviewer\'s name, decision and report are published with the work. It would be inconsistent for such a system to keep its reviewers hidden, so the directory is open as well. Everything shown is what the person wrote themselves; <b>no e mail address is shown anywhere</b>. Even an editor sending an invitation does not see the address.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <form class="kart hd-suz" method="get" action="<?= k_esc(k_bag('/hakemler.php')) ?>">
      <?php if (k_en()): ?><input type="hidden" name="lang" value="en"><?php endif; ?>
      <div>
        <label for="hdAlan"><?= k_esc(al_secici_etiket($en)) ?></label>
        <select id="hdAlan" name="alan">
          <option value=""><?= k_esc(al_hepsi_etiket($en)) ?></option>
          <?php /* DİZİNDE KURAL BAŞKADIR: burada arşiv değil İNSAN
                   aranır, bu yüzden yalnız karşılığı olan alanlar
                   listelenir — boş bir alan seçeneği kimseye yardımcı
                   olmaz. Ama basamağın tamamı listelenir: eksik olan
                   temel alandı ve "sosyal bilimlerde kim var" en sık
                   sorulan sorudur. Süzgecin kendisi al_yakinlik()
                   kullanmayı sürdürür ve bu doğrudur: dizinde sorulan
                   "bu kayıt altında mı" değil, "bu kişi bu işi
                   değerlendirebilir mi"dir; komşu daldaki hakem de
                   listelenir ve yakınlığına göre kümelenir. */ ?>
          <?php $anaDolu = array_filter(array_keys(al_ana()), fn($k) => isset($dolu[(string)$k])); ?>
          <?php if ($anaDolu): ?>
          <optgroup label="<?= k_esc(al_temel_kume_adi($en)) ?>">
            <?php foreach ($anaDolu as $ak2): ?>
            <option value="<?= k_esc((string)$ak2) ?>"<?= $sAlan === (string)$ak2 ? ' selected' : '' ?>><?= k_esc(al_ad((string)$ak2, $en)) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endif; ?>
          <?php foreach (al_secim($en) as $ak => $av): ?>
            <?php
              $altVar = isset($dolu[$ak]);
              $dallar = array_filter($av['dallar'], fn($v, $k2) => isset($dolu[$k2]), ARRAY_FILTER_USE_BOTH);
              if (!$altVar && !$dallar) continue;
            ?>
            <optgroup label="<?= k_esc($av['ana'] . ' > ' . $av['ad']) ?>">
              <option value="<?= k_esc($ak) ?>"<?= $sAlan === $ak ? ' selected' : '' ?>><?= k_esc($av['ad']) ?></option>
              <?php foreach ($dallar as $dk => $dad): ?>
              <option value="<?= k_esc($dk) ?>"<?= $sAlan === $dk ? ' selected' : '' ?>><?= k_esc($dad) ?></option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="hdAra"><?= k_c('Ad ya da kurum', 'Name or institution') ?></label>
        <input type="search" id="hdAra" name="q" value="<?= k_esc($sAra) ?>" placeholder="<?= k_c('ara...', 'search...') ?>">
      </div>
      <button class="d d-vurgu" type="submit"><?= k_c('Süz', 'Filter') ?></button>
      <?php if ($sAlan !== '' || $sAra !== ''): ?>
      <a class="d d-sessiz" href="<?= k_esc(k_bag('/hakemler.php')) ?>"><?= k_c('Temizle', 'Clear') ?></a>
      <?php endif; ?>
    </form>

    <?php if (!$hakemler): ?>
      <div class="bos-durum"><?= k_c(
        'Bu süzgece uyan hakem yok. Alan seçimini genişletebilir ya da süzgeci temizleyebilirsiniz. Dizinde görünmek için hesabınızdan bilim alanlarınızı seçmeniz yeterlidir; doktora belgesi onaylanmış herkes burada yer alır.',
        'No reviewer matches this filter. You may widen the field or clear the filter. To appear in the directory it is enough to choose your fields in your account; everyone whose doctoral credential has been approved is listed here.'
      ) ?></div>
    <?php else: ?>
      <p class="hd-say"><?= count($hakemler) ?> <?= k_c('hakem', 'reviewers') ?><?php
        if ($sAlan !== '') echo ' · ' . k_esc(al_yol($sAlan, $en));
      ?></p>
      <?php
      /* Alan sorulduğunda liste yakınlığa göre kümelenir. Tek bir yığın
         hâlinde gösterilince, yalnızca aynı ana alandan olduğu için
         listeye giren biri, tam eşleşen biriyle aynı görünüyordu.
         Başlıklar bunu ayırır: bakan kişi kime baktığını bilir. */
      $kume = ['3' => [], '2' => [], '1' => []];
      if ($sAlan !== '') { foreach ($hakemler as $k) { $kume[(string)($k['yakin'] ?? 1)][] = $k; } }
      $basliklar = [
        '3' => k_c('Aynı dalda çalışanlar', 'Working in the same branch'),
        '2' => k_c('Aynı alt alanda çalışanlar', 'Working in the same subfield'),
        '1' => k_c('Aynı ana alanda çalışanlar', 'Working in the same main field'),
      ];
      ?>
      <?php if ($sAlan !== ''): foreach ($kume as $yk => $liste): if (!$liste) continue; ?>
      <h2 class="hd-bas"><?= k_esc($basliklar[$yk]) ?> <small>(<?= count($liste) ?>)</small></h2>
      <div class="dizi dizi-2 hd-liste">
        <?php foreach ($liste as $k): ?>
        <a class="kk" href="<?= k_esc(k_bag($k['yol'])) ?>">
          <?= k_yuz($k['ad'], $k['resim'], 44, 'kk-yuz') ?>
          <span class="kk-ic">
            <b><?= k_esc($k['gorunen']) ?></b>
            <?php if ($k['kurum'] !== ''): ?><small class="kk-kurum"><?= k_esc($k['kurum']) ?></small><?php endif; ?>
            <?php if ($k['kodlar']): ?>
            <span class="hd-kod">
              <?php foreach (array_slice($k['kodlar'], 0, 4) as $c): ?>
              <span class="rz <?= al_yakinlik($sAlan, $c) === 3 ? 'rz-kut' : 'rz-cizgi' ?>"><?= k_esc(al_ad($c, $en)) ?></span>
              <?php endforeach; ?>
            </span>
            <?php endif; ?>
            <small class="kk-alt"><?= (int)$k['rapor'] ?> <?= k_c('rapor', 'reports') ?><?php
              if ($k['kurulda']) echo ' · ' . k_c('kurul', 'board');
            ?></small>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endforeach; else: ?>
      <div class="dizi dizi-2 hd-liste">
        <?php foreach ($hakemler as $k): ?>
        <a class="kk" href="<?= k_esc(k_bag($k['yol'])) ?>">
          <?= k_yuz($k['ad'], $k['resim'], 44, 'kk-yuz') ?>
          <span class="kk-ic">
            <b><?= k_esc($k['gorunen']) ?></b>
            <?php if ($k['kurum'] !== ''): ?><small class="kk-kurum"><?= k_esc($k['kurum']) ?></small><?php endif; ?>
            <?php if ($k['kodlar']): ?>
            <span class="hd-kod">
              <?php foreach (array_slice($k['kodlar'], 0, 4) as $c): ?>
              <span class="rz <?= ($sAlan !== '' && al_yakinlik($sAlan, $c) === 3) ? 'rz-kut' : 'rz-cizgi' ?>"><?= k_esc(al_ad($c, $en)) ?></span>
              <?php endforeach; ?>
            </span>
            <?php endif; ?>
            <small class="kk-alt"><?= (int)$k['rapor'] ?> <?= k_c('rapor', 'reports') ?><?php
              if ($k['kurulda']) echo ' · ' . k_c('kurul', 'board');
            ?></small>
          </span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    <?php endif; ?>
   </div>

   <?= k_belge_yan([], [
      ['tr' => 'Dizine nasıl girilir', 'en' => 'How to enter the directory',
       'ic' => k_c(
         'Hesabınızı açın, doktora belgenizi sunun ve panelden bilim alanlarınızı seçin. Onaydan sonra dizinde görünürsünüz. Görünmek istemiyorsanız paneldeki tek bir onay kutusu yeterlidir; çıkmanız hakemliğinizi etkilemez.',
         'Open an account, supply your doctoral credential and choose your fields in the panel. After approval you appear in the directory. If you would rather not appear, a single checkbox in the panel is enough; leaving does not affect your reviewing.'
       )],
      ['tr' => 'Adres neden yok', 'en' => 'Why there is no address',
       'ic' => k_c(
         'Bir dizin, insanların istenmeyen ileti almasına yol açmamalıdır. Bu yüzden adres ne burada görünür ne de editöre açılır: davet iletisini sunucu gönderir.',
         'A directory must not become a route for unwanted mail. The address is therefore shown neither here nor to the editor: the server sends the invitation.'
       )],
      ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
       'ic' => '<a href="' . k_esc(k_bag('/hakemlik.php')) . '">' . k_c('Hakemlik süreci', 'Peer review process') . '</a><br>'
             . '<a href="' . k_esc(k_bag('/bekleyen.php')) . '">' . k_c('Hakem aranan çalışmalar', 'Works seeking reviewers') . '</a><br>'
             . '<a href="' . k_esc(k_bag('/kurul.php')) . '">' . k_c('Yayın kurulu', 'Editorial board') . '</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
