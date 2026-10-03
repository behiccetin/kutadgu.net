<?php
/* =====================================================================
   DİL SESİ KAPISI · birinci tekil şahıs
   ---------------------------------------------------------------------
   KURUL GERİ BİLDİRİMİ (M. Z. Tunca, 13 Ağustos 2026):
   "site dili revize edilmeli; tek bir kişi tarafından 'ben yaptım oldu'
   ya da 'böyle istiyorum' anlamına gelebilecek ifadeler yerine sade ve
   akademik ifadeler uygun olur."

   ÖLÇÜLDÜ VE SORUNUN BÜYÜKLÜĞÜ 1 ÇIKTI. Bütün sayfa metinleri tarandı;
   birinci tekil şahısla konuşan tek yer yz.php'deki yapay zekâ beyanıydı
   ("Onu gizlemiyorum... o ad benimdir") ve o paragraf kurumsal dile
   çevrildi. Yani kurulun gördüğü şey gerçekti ama yaygın değildi.

   BU KAPI O YÜZDEN VAR: bir kez düzeltilen üslup, yazan kişi değiştiği
   ya da acele edildiği gün geri gelir. Ölçülmeyen bir üslup kuralı,
   birkaç ayda kendiliğinden bozulur.

   NE TARANIR: yalnızca OKURA GÖRÜNEN metin, yani k_c()/tg_c() çağrıları.
   Kod yorumları taranmaz ve taranmamalıdır: bir gerekçeyi "şunu ölçtüm,
   şu yüzden böyle yaptım" diye yazmak bu sistemde beklenen şeydir.

   NE TARANMAZ (ve neden):
     - Kullanıcının KENDİ ağzından konuşan düğme metinleri. "Bu çalışmayı
       değerlendirmek istiyorum" düğmesine basan kişi kendi adına konuşur;
       orada birinci tekil şahıs doğrudur, kusur değildir.
     - CSS sınıf adları (bk-benim gibi): metin değil, seçicidir.
   ===================================================================== */

$kok = dirname(__DIR__) . '/kutadgunet';
$hata = 0; $sira = 0;
function ol(string $ad, bool $ok, string $not = ''): void {
    global $hata, $sira;
    $sira++;
    if (!$ok) $hata++;
    printf("%s  %s%s\n", $ok ? ' OK ' : 'KUSUR', $ad, $not !== '' ? ('  -> ' . $not) : '');
}

/* Sistemin kendi ağzından konuşurken kullanmayacağı kalıplar. */
$kaliplar = '(yaptım|yazdım|koydum|kurdum|seçtim|ekledim|kaldırdım|istedim|dedim|'
          . 'gizlemiyorum|söylüyorum|inanıyorum|sanıyorum|düşünüyorum|'
          . 'amacım|niyetim|tercihim|kanaatimce|şahsen|kendi adıma|bence|benimdir)';

/* Yazılı ve gerekçeli istisnalar. Bir istisna eklemek serbesttir; ama
   gerekçesiz eklenemez, çünkü listenin kendisi de bir belgedir. */
$muaf = [
    /* Kullanıcının kendi ağzından bastığı düğme. */
    'bekleyen.php' => ['değerlendirmek istiyorum', 'assess this work'],
];

$bulgu = [];
$yineleyici = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
foreach ($yineleyici as $dosya) {
    $yol = $dosya->getPathname();
    if (substr($yol, -4) !== '.php') continue;
    if (strpos($yol, '/k/ceviri') !== false) continue;
    $ad = str_replace($kok . '/', '', $yol);

    $satirlar = explode("\n", (string)@file_get_contents($yol));
    foreach ($satirlar as $n => $satir) {
        $kirp = ltrim($satir);
        /* Yorum satırı: gerekçe yazmak yasaklanamaz. */
        if ($kirp === '' || $kirp[0] === '*' || strpos($kirp, '//') === 0 || strpos($kirp, '/*') === 0) continue;
        /* Yalnızca okura görünen metin üreten satırlar. */
        if (strpos($satir, 'k_c(') === false && strpos($satir, 'tg_c(') === false) continue;
        if (!preg_match('/(?<![a-zçğıöşü])' . $kaliplar . '(?![a-zçğıöşü])/ui', $satir, $m)) continue;

        $atla = false;
        foreach (($muaf[$ad] ?? []) as $izin) {
            if (mb_strpos($satir, $izin) !== false) { $atla = true; break; }
        }
        /* ---- KULLANICININ İMZALADIĞI BEYAN MUAFTIR ----
           Kuralın konusu SİSTEMİN sesidir: bir yayın sistemi kurum
           gibi konuşur, "ben" demez. Ama kullanıcının işaretleyerek
           imzaladığı bir beyan zorunlu olarak birinci tekildir —
           "beyan ederim", "izin veriyorum", "bütün yazarları ekledim".
           Onları üçüncü tekile çevirmek beyanı beyan olmaktan çıkarır:
           imzalanan cümle, imzalayanın ağzından kurulur.

           Muafiyet DAR: yalnızca onay ögesini taşıyan satırlar. Aynı
           dosyadaki bir açıklama cümlesi hâlâ yakalanır. Kural bir kez
           daha yazılmıyor, tanınıyor: <label class="onay"> ile
           input type=checkbox/radio bu sistemde beyanın işaretidir.

           15 Ağustos 2026'da eklendi: "Bütün yazarları ekledim" onayı
           bu kapıya takıldı ve takılması ölçümün kusuruydu, metnin
           değil. Kapının kendisi de zaten körıydı — telif beyanındaki
           "beyan ederim" ve "veriyorum" kalıp listesinde olmadığı için
           yıllardır geçiyordu; muafiyet artık yazılı. */
        if (!$atla && preg_match('/class="onay"|name="(yazar_tam|cikar_catismasi|fon_durum|tek_gonderim|yz_kullanim|etik_durum|veri_beyan)"/', $satir)) {
            $atla = true;
        }
        if ($atla) continue;
        $bulgu[] = $ad . ':' . ($n + 1) . ' [' . $m[1] . ']';
    }
}

ol('okura görünen metinde birinci tekil şahıs yok', $bulgu === [], implode(' | ', array_slice($bulgu, 0, 6)));

/* Düzeltilen paragrafın geri gelmediği ayrıca ölçülür: kapı genel kuralı
   korur, bu madde ise BİLİNEN bir kusurun nöbetini tutar. */
$yz = (string)@file_get_contents($kok . '/yz.php');
ol('yapay zekâ beyanı kurumsal dilde', mb_strpos($yz, 'Onu gizlemiyorum') === false);
ol('  ve beyanın kendisi silinmemiş',
   mb_strpos($yz, 'yapay zekâ araçlarının yardımıyla geliştirildi') !== false,
   'beyan kaldırılmış olabilir; gizlemediğini söyleyen bir sistem onu kaldıramaz');
/* KURUL GERİ BİLDİRİMİ (Behiç, 13 Ağustos 2026): ilk düzeltme birinci
   tekil şahsı kaldırmıştı ama "baş yapımcı ... geliştiricisi ...dir"
   dizilişi hâlâ tek kişiyi öne çıkarıyordu. Metin ortak emeği anlatacak
   biçimde yeniden yazıldı. Ölçülen şey artık ÇOĞULLUK: beyanın kurulun
   payını söylemesi ve tek bir ada indirgenmemesi. */
ol('  ve ortak emek yazılı',
   mb_strpos($yz, 'tek bir kişinin işi değildir') !== false
   && mb_strpos($yz, 'kurul kararlarıyla belirlendi') !== false);
ol('  ve beyan tek ada indirgenmemiş',
   mb_strpos($yz, 'baş yapımcısı ve yazılımın geliştiricisi') === false);

/* Sorunun büyüklüğü kayda geçer: bu sayı büyürse üslup dağılıyor demektir. */
printf("\nÖLÇÜM  okura görünen metinde taranan kalıp: %d\n", substr_count($kaliplar, '|') + 1);

echo "\n" . ($hata === 0 ? "GEÇTİ" : "KALDI") . ": {$sira} ölçüm, {$hata} kusur\n";
exit($hata === 0 ? 0 : 1);
