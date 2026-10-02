<?php
/* =====================================================================
   KUTADGU - Yeniden kullanılan arayüz parçaları / UI components
   ===================================================================== */

require_once __DIR__ . '/veri.php';
require_once __DIR__ . '/alanlar.php';
require_once __DIR__ . '/muhur.php';   /* tamga mührü: liste kartında */

if (!function_exists('k_yazi_kart')) {

    /* ---------------------------------------------------------------
       Liste süzgecinin tür değeri.

       Kartın data-tur özniteliği ile arşiv sayfasının sunucu tarafı
       süzmesi bu tek işlevden beslenir: ikisi ayrı yerde hesaplanırsa
       betiği kapalı olan okuyucu başka bir liste görür.

         hakemli   hakemli yolda ve en az bir rapor gelmiş
         aranan    hakemli yolda, henüz rapor yok
         yazi      hakemsiz yol

       Geri çekilme burada bilerek yok sayılır. Süzgeç "bu çalışma
       hangi yolda ve o yolun neresinde" diye sorar, "yayında mı" diye
       değil; çekilmiş ama hakemlenmiş bir çalışma hakemlenmiş olarak
       kalır, çünkü raporlar hâlâ ortadadır ve okunabilir. Aşamayı
       kullanamamamızın nedeni de budur: tg_hakem_asamasi() çekilmiş
       çalışmaya 'cekildi' der ve yolu gizler. Rozet için aşama,
       süzgeç için bu değer kullanılır.

       Üç değer kesişimsiz ve boşluksuzdur: her çalışma tam birine
       düşer, böylece çipler bütünü böler. */
    function k_suz_tur(array $y): string {
        if (tg_hakemden_gecti($y)) return 'hakemli';
        return ((string)($y['tur'] ?? '') === 'hakemli') ? 'aranan' : 'yazi';
    }

    /* Bir çalışmanın kart görünümü */
    function k_yazi_kart(array $y, bool $one = false): string {
        /* Başlık ve dili tek kaynaktan (ortak.php, tg_baslik_bilgi):
           hangi başlık gösterilecek, dili ne, özgünü var mı. */
        $bb    = tg_baslik_bilgi($y);
        $bas   = $bb['bas'] !== '' ? $bb['bas'] : k_alan($y, 'baslik');
        $ozet  = k_ozet(k_alan($y, 'ozet') !== '' ? k_alan($y, 'ozet') : k_alan($y, 'metin'), $one ? 300 : 180);
        $yzr   = k_yazarlar($y);
        $yol   = k_yazi_yolu($y);
        $tar   = k_tarih((string)($y['tarih'] ?? ''));
        /* Rozet 'tur' alanından değil aşamadan üretilir: 'tur' çalışmanın
           hangi yolda olduğunu söyler, o yolun neresinde olduğunu değil.
           Hakemliğe yeni açılmış, tek raporu bile olmayan bir çalışmaya
           "Hakemli" demek okura verilmiş yanlış bir sözdür. */
        $asama = tg_hakem_asamasi($y);
        $suzTur = k_suz_tur($y);
        $ozetH = k_hakem_ozet($y);
        $tam   = k_tamga($y);
        $oku   = k_okuma_sayisi((string)($y['id'] ?? ''));
        $anah  = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', k_alan($y, 'anahtar')))));

        /* Süzme ve sıralama için ölçüler. Karşılaştırma Türkçe harflere
           duyarsız olsun diye arama alanına hem özgün hâli hem de
           harf karşılığı (Behiç -> behic) yazılır. */
        $tarihHam = (string)($y['tarih'] ?? '');
        $yil      = preg_match('/^(\d{4})/', $tarihHam, $mYil) ? $mYil[1] : '';
        $araMetin = mb_strtolower($bas . ' ' . $yzr . ' ' . implode(' ', $anah) . ' ' . k_ozet(k_alan($y, 'ozet'), 400), 'UTF-8');
        $araSade  = tg_ad_anahtar($araMetin);
        $alanlar  = array_values(array_filter(array_map('trim', preg_split('/[,;]+/u', k_alan($y, 'alan')))));
        /* FORD tabanlı alan kodları. Kartta gösterilmez ama süzmede
           kullanılır: arşiv sayfasındaki bilim alanı süzgeci bunlara
           bakar. Kodun kendisi ve üst basamakları birlikte yazılır ki
           "İktisat ve işletme" seçildiğinde altındaki dallar da gelsin. */
        $alanKod = function_exists('al_kayit_kodlari') ? al_kayit_kodlari($y) : [];
        $alanSuz = [];
        foreach ($alanKod as $ak) {
            $alanSuz[$ak] = true;
            $ust = function_exists('al_ust') ? al_ust($ak) : '';
            while ($ust !== '') { $alanSuz[$ust] = true; $ust = al_ust($ust); }
        }

        ob_start(); ?>
        <article class="kart kart-t yk<?= $one ? ' yk-one' : '' ?>"
                 data-ara="<?= k_esc($araMetin . ' ' . $araSade) ?>"
                 data-tur="<?= k_esc($suzTur) ?>"
                 data-yil="<?= k_esc($yil) ?>"
                 data-tarih="<?= k_esc($tarihHam) ?>"
                 data-oku="<?= (int)$oku ?>"
                 data-bas="<?= k_esc(tg_ad_anahtar($bas)) ?>"
                 data-yazar="<?= k_esc(implode('|', tg_yazar_anahtarlari($y))) ?>"
                 data-anah="<?= k_esc(mb_strtolower(implode('|', array_merge($anah, $alanlar)), 'UTF-8')) ?>"
                 data-alan="<?= k_esc(implode('|', array_keys($alanSuz))) ?>">
          <div class="satir yk-ust">
            <span class="rz <?= k_esc(tg_asama_rz($asama)) ?>"><span class="nokta"></span><?= k_esc(tg_asama_metni($asama, k_en(), true)) ?></span>
            <?php if ($tam['kod'] !== ''): ?>
            <?php /* Mühür kodun SOLUNDA ve rozetin İÇİNDE durur. Ayrı
                     bir öğe olsaydı dar ekranda rozetten koparak başka
                     bir satıra düşer ve neyin mührü olduğu okunmazdı.
                     Mühür süstür: bilgi taşımaz, ekran okuyucudan
                     gizlidir (mh_muhur() aria-hidden basar) ve yanında
                     her zaman kodun kendisi yazılıdır. Mührü kodsuz
                     basmak, okunamayan bir kimlik basmak olurdu. */ ?>
            <span class="rz rz-cizgi yk-tam"><?= mh_muhur($tam['kod'], 18, 'tmg-im') ?><?= k_esc((string)tg_ayar('tamga_ad', 'Tamga')) ?> <?= k_esc($tam['kod']) ?></span><?php endif; ?>
          </div>
          <h3 class="yk-bas" lang="<?= k_esc($bb['dil'] !== '' ? $bb['dil'] : 'tr') ?>">
            <a href="<?= k_esc(k_bag($yol)) ?>"><?= k_esc($bas) ?></a>
            <?php /* DİL İMİ: başlık sayfanın dilinde değilse yazılır.
                     Türkçe sayfada Türkçe başlığın yanına "Türkçe"
                     yazmak gürültüdür; İngilizce sayfada Türkçe bir
                     başlığın dilini SÖYLEMEMEK ise okuru şaşırtır. */ ?>
            <?php if ($bb['im'] && $bb['dil_ad'] !== ''): ?><span class="rz rz-cizgi yk-dil" lang="<?= k_esc($bb['dil']) ?>"><?= k_esc($bb['dil_ad']) ?></span><?php endif; ?>
          </h3>
          <?php /* Çeviri gösteriliyorsa ÖZGÜN başlık da durur: çeviri
                   özgünün yerine geçmez, yanında durur. */ ?>
          <?php if ($bb['ozgun'] !== ''): ?>
          <p class="yk-ozgun" lang="<?= k_esc($bb['ozgun_dil']) ?>"><span><?= k_esc(k_c('Özgün başlık', 'Original title')) ?><?= $bb['ozgun_ad'] !== '' ? ' (' . k_esc($bb['ozgun_ad']) . ')' : '' ?>:</span> <?= k_esc($bb['ozgun']) ?></p>
          <?php endif; ?>
          <?php if ($yzr !== ''): ?><p class="yk-yzr"><?= k_esc($yzr) ?></p><?php endif; ?>
          <?php if ($ozet !== ''): ?><p class="yk-oz"><?= k_esc($ozet) ?></p><?php endif; ?>
          <?php if ($anah): ?>
          <div class="satir yk-anah"><?php foreach (array_slice($anah, 0, $one ? 6 : 4) as $a): ?><span class="rz rz-cizgi"><?= k_esc($a) ?></span><?php endforeach; ?></div>
          <?php endif; ?>
          <div class="satir yk-alt">
            <span><?= k_esc($tar) ?></span>
            <?php if ($ozetH): ?>
              <span class="yk-nok"></span>
              <span><?= count($ozetH) ?> <?= k_c('hakem', count($ozetH) === 1 ? 'reviewer' : 'reviewers') ?></span>
              <?php foreach ($ozetH as $h): if ($h['karar'] === '') continue; ?>
                <span class="rz <?= k_karar_renk($h['karar']) ?>"><?= k_esc(k_karar_ad($h['karar'])) ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($oku > 0): ?><span class="yk-nok"></span><span><?= k_esc(k_sayi($oku)) ?> <?= k_c('okuma', 'reads') ?></span><?php endif; ?>
          </div>
        </article>
        <?php
        return (string)ob_get_clean();
    }

    /* Kart üslubu: bir kez yazdırılır */
    function k_kart_stil(): string {
        return <<<CSS
<style>
/* Tamga rozeti: mühür ile kod aynı rozetin içinde, mühür solda.
   Mühür rozetin yazı rengini alır (currentColor); ayrı bir renk
   verilseydi listede altmış tane küçük vurgu noktası oluşur ve
   asıl bilgi olan başlıkla yarışırdı. */
.yk-tam{display:inline-flex;align-items:center;gap:.4em}
.yk-tam > svg{flex:none;opacity:.85}
/* Kartın kendisi dizgeden gelir (.kart); burada yalnızca çalışma
   kartına özgü iç düzen ve tipografi durur. */
.yk{display:flex;flex-direction:column;gap:var(--b-3)}
.yk-bas{margin:0;display:flex;flex-wrap:wrap;align-items:baseline;gap:var(--b-2)}
/* Dil imi başlığın parçası değil, yanındaki bir bilgidir: başlıkla
   aynı boyda yazılırsa başlığın bir bölümü sanılır. */
.yk-dil{flex:none;font-size:var(--y-1)}
.yk-ozgun{margin:calc(-1 * var(--b-2)) 0 0;font-size:var(--y-3);color:var(--metin-2);line-height:var(--sh-orta)}
.yk-ozgun span{color:var(--metin-3)}
.yk-bas a{color:var(--metin)}
.yk-bas a:hover{color:var(--kut);text-decoration:none}
.yk-yzr{margin:0;font-size:var(--y-3);color:var(--kut);font-weight:600}
.yk-oz{margin:0;font-size:var(--y-3);color:var(--metin-2)}
/* Alt şerit kartın dibine yapışır: bilgisi az olan kartta da tarih ve
   okuma sayısı aynı hizada durur. */
.yk-alt{margin-top:auto;padding-top:var(--b-3);border-top:1px solid var(--cizgi);
  font-size:var(--y-2);color:var(--metin-2)}
/* Alt şeritteki ayırıcı nokta. Bir durum imi değil, iki bilgi arasındaki
   duraktır; bu yüzden .nokta değil, ondan küçüktür. */
.yk-nok{width:var(--b-1);height:var(--b-1);border-radius:50%;background:var(--cizgi);flex:none}
/* Öne çıkarılmış kart: aynı kart, bir basamak yukarıda. */
.yk-one{border-color:var(--kut-cizgi);box-shadow:var(--g-2)}
.yk-one .yk-bas{font-size:var(--y-7)}
.yk-one .yk-oz{font-size:var(--y-5)}
</style>
CSS;
    }
}
