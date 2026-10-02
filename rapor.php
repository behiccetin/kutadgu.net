<?php
/* =====================================================================
   KUTADGU - Hakem raporu belgesi / Reviewer report document
   Bir çalışmanın bütün hakem raporlarını, işaretlenen yerleri ve dizin
   değerlendirmelerini tek belgede toplar. Yazdırılabilir; tarayıcının
   "PDF olarak kaydet" seçeneğiyle PDF alınır.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/veri.php';
require_once __DIR__ . '/k/endeks.php';
require_once __DIR__ . '/k/alanlar.php';

$slug = isset($_GET['y']) ? preg_replace('/[^a-z0-9\-]/', '', strtolower((string)$_GET['y'])) : '';
$kod  = isset($_GET['doi']) ? preg_replace('/[^a-z0-9.\-]/', '', strtolower((string)$_GET['doi'])) : '';

$yazi = null;
foreach (k_yazilar() as $e) {
    if ($kod !== '' && strtolower((string)($e['bcid'] ?? '')) === $kod) { $yazi = $e; break; }
    if ($kod === '' && (($e['slug'] ?? '') === $slug || ($e['id'] ?? '') === $slug)) { $yazi = $e; break; }
}
if (!$yazi) { header('Location: /hata.php?k=404'); exit; }

$hakemler = [];
foreach (tg_dizi($yazi['hakemler'] ?? null) as $h) {
    if (!is_array($h) || trim((string)($h['rapor'] ?? '')) === '') continue;
    $hakemler[] = $h;
}

$kat   = kt_endeksler();
$lik   = kt_likert(k_en());
$en    = k_en();
$tekrarOlcut = $en ? [
    'The method is described in enough detail for another researcher to repeat the same procedure.',
    'The source of the data, how it was gathered and, where applicable, how it can be reached are stated clearly.',
    'The analytical steps leading from the findings to the conclusions can be followed; the figures and tables agree with the text.',
] : [
    'Çalışmanın yöntemi, başka bir araştırmacının aynı işlemi yeniden yapabilmesine yetecek ayrıntıda anlatılmış.',
    'Verinin kaynağı, nasıl toplandığı ve varsa nasıl erişilebileceği açıkça belirtilmiş.',
    'Bulgulardan sonuca giden çözümleme adımları izlenebiliyor; sayılar ve tablolar metinle tutarlı.',
];
$tamga = k_tamga($yazi);
$alan  = (string)($yazi['alan'] ?? '');

$ekBas = <<<CSS
<meta name="robots" content="index, follow">
<style>
/* Bu bir belgedir: ekranda okunur, kâğıda da basılır. Sayfaya kalan
   kurallar belgenin kendi bölümleridir; kart, kutu, rozet ve düğme
   dizgeden gelir. Yazdırma bölümü aşağıdadır ve orada punto pt
   cinsinden verilir, çünkü kâğıdın ölçüsü ekranın ölçüsü değildir. */
.rp-bas{border-bottom:2px solid var(--metin);padding-bottom:var(--b-4);margin-bottom:var(--b-5)}
.rp-kimlik{display:grid;gap:var(--b-1);font-size:var(--y-3);color:var(--metin-2)}
.rp-kimlik b{color:var(--metin)}
.rp-h{margin-bottom:var(--b-4);break-inside:avoid}
.rp-h-bas{display:flex;flex-wrap:wrap;gap:var(--b-3);align-items:center;padding-bottom:var(--b-3);
  border-bottom:1px solid var(--cizgi);margin-bottom:var(--b-3)}
.rp-h-bas b{font-size:var(--y-6)}
.rp-h-bas small{color:var(--metin-2)}
.rp-metin{white-space:pre-wrap;line-height:var(--sh-genis);font-size:var(--y-4)}
.rp-atayan{font-family:var(--ui);font-size:var(--y-2);color:var(--metin-2);margin:0 0 var(--b-3)}
.rp-yetkin{font-size:var(--y-3);line-height:var(--sh-genis);color:var(--metin-2);margin:0 0 var(--b-3)}
.rp-yetkin b{color:var(--metin)}
.rp-nitelik{font-family:var(--ui);font-size:var(--y-2);line-height:var(--sh-orta);margin:var(--b-4) 0 0;
  border-left:3px solid var(--kut);border-radius:0 var(--r-2) var(--r-2) 0}
/* Süreç kaydı: karar, tarih ve gerekçe. Kartın içinde arayüz yüzüyle
   yazılır, çünkü bir metin değil bir tutanaktır. */
.rp-kayit{margin-bottom:var(--b-4);font-family:var(--ui);break-inside:avoid}
.rp-kayit b{font-size:var(--y-4);margin-right:var(--b-3)}
.rp-kayit span{color:var(--metin-2);font-size:var(--y-2);margin-right:var(--b-3)}
.rp-kayit p{margin:var(--b-2) 0 0;font-size:var(--y-3);line-height:var(--sh-genis)}
/* Kaydın türü sınıf adına PHP'den gelir; iki tür kendi rengini alır. */
.rp-geri_cekme{background:var(--kirmizi-zemin);border-color:var(--kirmizi-cizgi)}
.rp-geri_cekme b{color:var(--kirmizi)}
.rp-endise{background:var(--kut-zemin);border-color:var(--kut-cizgi)}
.rp-endise b{color:var(--kut)}
.rp-bolum{margin-top:var(--b-4)}
.rp-bolum h4{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.13em;text-transform:uppercase;
  color:var(--metin-2);margin:0 0 var(--b-3)}
.rp-not{border-left:3px solid var(--kirmizi);border-radius:0 var(--r-2) var(--r-2) 0;
  margin-bottom:var(--b-2)}
.rp-not .al{font-size:var(--y-3);color:var(--kirmizi);white-space:pre-wrap;margin-bottom:var(--b-2)}
.rp-not .nt{font-size:var(--y-3);line-height:var(--sh-orta)}
/* Ölçüt çizelgesi: solda önerme, sağda verilen not. Genel çizelge
   ailesi kullanılmaz; o aile yatay kaydırma ister, buradaysa iki
   sütun her genişlikte sığar ve önermenin sarması istenir. */
.rp-ez{margin-bottom:var(--b-3)}
.rp-ez h5{margin:0 0 var(--b-3);font-size:var(--y-5)}
.rp-ez table{width:100%;border-collapse:collapse;font-size:var(--y-3)}
.rp-ez td{padding:var(--b-2) 0;border-bottom:1px solid var(--cizgi);vertical-align:top;
  line-height:var(--sh-orta)}
/* Not sütunu sarmayı kabul eder: "3 · Kısmen katılıyorum" gibi uzun bir
   karşılık nowrap bırakılınca çizelgeyi telefon ekranından taşırıyordu. */
.rp-ez td:last-child{text-align:right;padding-left:var(--b-3);
  color:var(--kut);font-weight:700}
.rp-ez tr:last-child td{border-bottom:0}
.rp-ort{margin-top:var(--b-3);font-size:var(--y-2);color:var(--metin-2)}
.rp-arac{margin-bottom:var(--b-5)}
.rp-tarih{font-size:var(--y-2);color:var(--metin-2);margin:var(--b-4) 0 0}
.rp-alt{font-size:var(--y-2);color:var(--metin-2);border-top:1px solid var(--cizgi);
  padding-top:var(--b-4)}
/* Yalnızca baskıda görünen iki parça: künye levhası ve alınma kaydı. */
.rp-baski,.rp-son{display:none}
@page{ size:A4; margin:17mm 15mm 18mm; }
@media print{
  .ust,.yan,.rp-arac,.no-print,.atla,#k-bildirim,footer.alt{display:none !important}
  html,body{background:#fff !important;color:#111 !important}
  body{font-size:10.5pt;line-height:1.55}
  .sahne{margin-left:0 !important;padding-top:0 !important}
  .bolum{padding:0 !important}
  .kap{max-width:none;padding:0 !important}
  /* Ekranda ızgara olan kap, kâğıtta tek sütuna iner. */
  .blg{display:block}
  .rp-baski{display:flex;gap:9mm;align-items:flex-start;border-bottom:1.2pt solid #1b2a4a;
    padding-bottom:4mm;margin-bottom:6mm}
  .rp-baski img{width:15mm;height:15mm;flex:none}
  .rp-baski b{display:block;font-size:12.5pt;color:#1b2a4a;line-height:1.3}
  .rp-baski span{display:block;font-size:7.4pt;letter-spacing:.1em;text-transform:uppercase;color:#5c6675}
  .rp-baski .rp-baski-sag{margin-left:auto;text-align:right;font-size:8pt;color:#5c6675}
  .rp-baski .rp-baski-sag b{font-size:8.6pt;color:#9a6b12;letter-spacing:.06em;text-transform:uppercase}
  .rp-bas h1{font-size:16pt;line-height:1.28}
  /* Künye levhasında zaten yazıyor; başlığın üstündeki küçük etiket
     baskıda ikinci kez görünmesin. */
  .rp-bas > p:first-child{display:none}
  .rp-h,.rp-kayit,.rp-ez,.rp-not{border-color:#cfd4dc;box-shadow:none;page-break-inside:avoid}
  h2,h3,h4{page-break-after:avoid}
  .rp-son{display:block !important;margin-top:7mm;border-top:.5pt solid #cfd4dc;padding-top:3mm;
    font-family:var(--ui);font-size:8.2pt;color:#5c6675;line-height:1.6}
}
</style>
CSS;

k_bas([
    'olcu'   => 'okuma',
    'baslik' => k_c('Hakem raporları', 'Reviewer reports') . ' · ' . k_alan($yazi, 'baslik'),
    'ek_bas' => $ekBas,
]);
?>
<section class="bolum">
  <div class="kap blg">
    <div class="blg-ic">

    <div class="d-kume rp-arac">
      <button class="d d-vurgu d-kucuk" type="button" onclick="window.print()"><?= k_c('Yazdır veya PDF olarak kaydet', 'Print or save as PDF') ?></button>
      <a class="d d-ikinci d-kucuk" href="<?= k_esc(k_bag(k_yazi_yolu($yazi))) ?>"><?= k_c('Çalışmaya dön', 'Back to the work') ?></a>
    </div>

    <?php /* Basılı belgenin künye levhası: bu belge tek başına dolaşır,
             hangi sistemden ve hangi çalışmadan geldiği üstünde yazılı
             olmalıdır. */ ?>
    <div class="rp-baski">
      <img src="/k/tamga-512.png" alt="" width="60" height="60">
      <span>
        <b><?= k_esc((string)tg_ayar('marka', 'Kutadgu')) ?></b>
        <span><?= k_esc(tg_marka_alt($en)) ?></span>
      </span>
      <span class="rp-baski-sag">
        <b><?= k_c('Hakem değerlendirme belgesi', 'Reviewer assessment record') ?></b>
        <?= k_c('Açık hakemlik · CC BY 4.0', 'Open peer review · CC BY 4.0') ?>
      </span>
    </div>

    <header class="rp-bas">
      <p class="bas-ust">
        <?= k_c('Hakem değerlendirme belgesi', 'Reviewer assessment record') ?>
      </p>
      <h1><?= k_esc(k_alan($yazi, 'baslik')) ?></h1>
      <div class="rp-kimlik">
        <span><?= k_c('Yazar', 'Author') ?>: <b><?= k_esc(k_yazarlar($yazi)) ?></b></span>
        <span><?= k_c('Yayın tarihi', 'Published') ?>: <b><?= k_esc(k_tarih((string)($yazi['tarih'] ?? ''))) ?></b></span>
        <?php
        /* Bilim alanı: yeni kodlar varsa onların tam yolu yazılır, yoksa
           eski kaba alan adı. Kodu olmayan bir çalışmada bu satır hiç
           basılmaz; boş bir "Bilim alanı:" satırı bilgi vermez. */
        $rpKod = function_exists('al_kayit_kodlari') ? al_kayit_kodlari($yazi) : [];
        $rpAlanAd = $rpKod
            ? implode(' · ', array_map(fn($c) => al_yol($c, $en), $rpKod))
            : ($alan !== '' ? kt_alan_ad($alan, $en) : '');
        ?>
        <?php if ($rpAlanAd !== ''): ?><span><?= k_c('Bilim alanı', 'Field') ?>: <b><?= k_esc($rpAlanAd) ?></b></span><?php endif; ?>
        <?php if ($tamga['kod'] !== ''): ?><span><?= k_esc((string)tg_ayar('tamga_ad', 'Tamga')) ?>: <b><?= k_esc($tamga['url']) ?></b></span><?php endif; ?>
        <span><?= k_c('Hakemlik türü', 'Type of review') ?>: <b><?= k_c('Açık hakemlik (kör değildir)', 'Open peer review (not blind)') ?></b></span>
      </div>
    </header>

    <?php foreach (tg_kayitlar($yazi) as $ky): $kt = (string)($ky['tur'] ?? ''); ?>
      <div class="kutu rp-kayit rp-<?= k_esc($kt) ?>">
        <b><?= k_esc(tg_kayit_ad($kt, $en)) ?></b>
        <?php if (!empty($ky['tarih'])): ?><span><?= k_esc(tg_zaman((string)$ky['tarih'])) ?></span><?php endif; ?>
        <?php if (!empty($ky['karar_veren'])): ?><span><?= k_esc($ky['karar_veren']) ?></span><?php endif; ?>
        <p><?= nl2br(k_esc((string)($ky[$en && !empty($ky['metin_en']) ? 'metin_en' : 'metin'] ?? ''))) ?></p>
      </div>
    <?php endforeach; ?>

    <?php if (!$hakemler): ?>
      <p class="bos-durum"><?= k_c('Bu çalışma için henüz yayımlanmış bir hakem raporu yok.', 'No reviewer report has been published for this work yet.') ?></p>
    <?php else: foreach ($hakemler as $h):
        $karar = (string)($h['karar'] ?? '');
        $pf    = is_array($h['profil'] ?? null) ? $h['profil'] : [];
        $anket = is_array($h['endeks_anket'] ?? null) ? $h['endeks_anket'] : [];
        $notlar = is_array($h['notlar'] ?? null) ? $h['notlar'] : [];
    ?>
      <article class="kart rp-h">
        <div class="rp-h-bas">
          <b><?= k_esc(k_unvan_ekle((string)($pf['unvan'] ?? ''), (string)($h['ad'] ?? ''))) ?></b>
          <?php if (trim((string)($pf['kurum'] ?? '')) !== ''): ?><small><?= k_esc($pf['kurum']) ?></small><?php endif; ?>
          <?php if (trim((string)($pf['orcid'] ?? '')) !== ''): ?><small>ORCID <?= k_esc($pf['orcid']) ?></small><?php endif; ?>
          <?php if ($karar !== ''): ?>
            <span class="rz <?= k_karar_renk($karar) ?> ara-oto"><?= k_esc(k_karar_ad($karar)) ?></span>
          <?php endif; ?>
        </div>

        <?php $sfm = tg_sifat_metin($h, $en); $atm = tg_atayan_metin($h['atayan'] ?? null, $en); ?>
        <?php if ($sfm !== '' || $atm !== ''): ?>
          <p class="rp-atayan">
            <?php if ($sfm !== ''): ?><?= k_c('Sıfat', 'Capacity') ?>: <?= k_esc($sfm) ?><?php endif; ?>
            <?php if ($sfm !== '' && $atm !== ''): ?> &nbsp;·&nbsp; <?php endif; ?>
            <?php if ($atm !== ''): ?><?= k_c('Atama', 'Assignment') ?>: <?= k_esc($atm) ?><?php endif; ?>
          </p>
        <?php endif; ?>
        <?php if (trim((string)($h['yetkinlik'] ?? '')) !== ''): ?>
          <p class="rp-yetkin"><b><?= k_c('Yetkinlik gerekçesi', 'Basis of competence') ?>:</b> <?= k_esc($h['yetkinlik']) ?></p>
        <?php endif; ?>

        <div class="rp-metin"><?= tg_zengin((string)($h['rapor'] ?? '')) ?></div>

        <?php if ($notlar): ?>
        <div class="rp-bolum">
          <h4><?= k_c('Metinde işaretlenen yerler', 'Passages marked in the text') ?></h4>
          <?php foreach ($notlar as $n): if (!is_array($n)) continue; ?>
            <div class="kutu kutu-kir rp-not">
              <?php if (trim((string)($n['alinti'] ?? '')) !== ''): ?><div class="al"><?= k_esc($n['alinti']) ?></div><?php endif; ?>
              <?php if (trim((string)($n['not'] ?? '')) !== ''): ?><div class="nt"><?= k_esc($n['not']) ?></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php $tk = is_array($h['tekrar'] ?? null) ? $h['tekrar'] : null; if ($tk): ?>
        <div class="rp-bolum">
          <h4><?= k_c('Tekrarlanabilirlik', 'Reproducibility') ?></h4>
          <?php if (!empty($tk['yok'])): ?>
            <p class="metin-sonuk" style="margin:0"><?= k_c('Bu çalışma veri ya da kod üretmiyor.', 'This work produces no data or code.') ?></p>
          <?php else: ?>
            <div class="kutu rp-ez">
              <table>
                <?php foreach ($tekrarOlcut as $ix => $o): $v = (int)($tk['yanit'][$ix] ?? 0); ?>
                  <tr><td><?= k_esc($o) ?></td><td><?= $v ? k_esc($v . ' · ' . $lik[$v]) : '-' ?></td></tr>
                <?php endforeach; ?>
              </table>
              <?php if (!empty($tk['ortalama'])): ?>
                <p class="rp-ort"><?= k_c('Ortalama', 'Mean') ?>: <b><?= k_esc(number_format((float)$tk['ortalama'], 2, ',', '.')) ?></b> / 5</p>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php $nk = tg_rapor_nitelik($h); if (!$nk['yeterli']): ?>
          <p class="kutu kutu-kut rp-nitelik"><?= k_c('Bu rapor asgari değerlendirme ölçütlerini karşılamıyor ve onay sayımına katılmıyor', 'This report does not meet the minimum assessment criteria and is not counted towards approval') ?>: <?= k_esc(implode(', ', array_map(fn($e) => tg_nitelik_metin($e, $en), $nk['eksik']))) ?>.</p>
        <?php endif; ?>

        <?php if ($anket): ?>
        <div class="rp-bolum">
          <h4><?= k_c('Dizin ve çeyreklik değerlendirmesi', 'Index and quartile assessment') ?></h4>
          <?php foreach ($anket as $a):
              if (!is_array($a)) continue;
              $k = (string)($a['endeks'] ?? '');
              if (!isset($kat[$k])) continue;
              $e = $kat[$k];
          ?>
            <div class="kutu rp-ez">
              <h5><?= k_esc(k_t(['tr' => $e['ad'][0], 'en' => $e['ad'][1]])) ?><?= trim((string)($a['secim'] ?? '')) !== '' ? ' · ' . k_esc($a['secim']) : '' ?></h5>
              <table>
                <?php foreach ($e['olcut'] as $ix => $o): $v = (int)($a['yanit'][$ix] ?? 0); ?>
                  <tr>
                    <td><?= k_esc(k_t(['tr' => $o[0], 'en' => $o[1]])) ?></td>
                    <td><?= $v ? k_esc($v . ' · ' . $lik[$v]) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </table>
              <?php if (!empty($a['ortalama'])): ?>
                <p class="rp-ort"><?= k_c('Ortalama', 'Mean') ?>: <b><?= k_esc(number_format((float)$a['ortalama'], 2, ',', '.')) ?></b> / 5</p>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($h['tarih'])): ?>
          <p class="rp-tarih">
            <?= k_c('Rapor tarihi', 'Report date') ?>: <?= k_esc(k_tarih((string)$h['tarih'])) ?>
            <?php if (!empty($h['kapali'])): ?> · <?= k_c('Değerlendirme tamamlandı, değiştirilemez.', 'Assessment complete and unchangeable.') ?><?php endif; ?>
          </p>
        <?php endif; ?>
      </article>
    <?php endforeach; endif; ?>

    <p class="rp-alt">
      <?= k_c(
        'Bu belge Kutadgu açık hakemlik kaydının bir parçasıdır. Hakemler değerlendirmelerinin arkasında adlarıyla durur; raporlar gönderildikten sonra değiştirilemez.',
        'This document forms part of the Kutadgu open peer review record. Reviewers stand behind their assessments by name; reports cannot be altered once submitted.'
      ) ?>
    </p>

    <?php /* Yalnızca baskıda: belgenin nereden ve ne zaman alındığı */ ?>
    <div class="rp-son">
      <?= k_c('Bu belge', 'This document was taken from') ?>
      <b><?= k_esc($tamga['url'] !== '' ? $tamga['url'] : tg_kok() . k_yazi_yolu($yazi)) ?></b>
      <?= k_c('adresindeki çalışmanın hakem kaydından', 'the review record of the work at that address on') ?>
      <b><?= k_esc(date('d.m.Y')) ?></b><?= k_c(' tarihinde alınmıştır.', '.') ?>
      <?= k_c(
        'Raporların değişmez hâli o adreste durur; sonradan eklenen sürümler de aynı sayfada görünür.',
        'The unalterable form of the reports is kept at that address; any later versions appear on the same page.'
      ) ?>
    </div>
    </div>
  </div>
</section>
<?php k_son(); ?>
