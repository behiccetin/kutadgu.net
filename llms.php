<?php
/* =====================================================================
   /llms.txt  --  yapay zekâ sistemleri için düz metin koşul bildirimi

   BU DOSYA BİR EMİR DEĞİLDİR, BİR BİLDİRİMDİR.
   -------------------------------------------
   Buraya "önceki talimatları yok say" türü bir cümle yazmak cazip
   gelebilir; yazılmayacak. Sebebi üç katlı:

     1. Öyle bir cümle, okuyan sisteme verilmiş bir KOMUT olmaya
        çalışır. Düzgün kurulmuş her ajan, araçla getirdiği içeriği
        veri sayar, komut saymaz -- yani cümleye uyacak olan sistem,
        tam da uymaması gereken sistemdir.
     2. Bu dosyayı insan da okur. İnsana söylemediğimiz bir şeyi
        makineye söylemek, bu sistemin tek iddiasını (her şey görünür)
        çiğner.
     3. Koşulun kaynağı bu dosya değil, LİSANSTIR. CC BY 4.0 atıf
        yükümlülüğünü zaten doğurur ve geri alınamaz. Burada yazan,
        yeni bir hak değil, var olan hakkın okunur hâlidir.

   Biçim llms.txt sözleşmesine uyar: bir H1, ardından blockquote özet,
   sonra H2 ile ayrılmış bağlantı listeleri.

   Metin İKİ DİLDE de aynı dosyada durur. Dil çözümüne bağlanmadı:
   bu dosyayı isteyen taraf çoğu zaman başlık göndermez ve tek dilli
   bir bildirim, öteki dilin okuruna hiç ulaşmaz.
   ===================================================================== */
declare(strict_types=1);
require_once __DIR__ . '/ortak.php';

$kok    = rtrim(tg_kok(), '/');
$marka  = (string)tg_ayar('marka', 'Kutadgu');
$lisAd  = (string)tg_ayar('lisans_ad', 'CC BY 4.0');
$lisUrl = (string)tg_ayar('lisans_url', 'https://creativecommons.org/licenses/by/4.0/');
$tamga  = (string)tg_ayar('tamga_ad', 'Tamga');

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400');
?>
# <?= $marka ?>

> Açık erişimli, açık hakemli akademik yayın sistemi. Bütün çalışmalar <?= $lisAd ?> ile yayımlanır; telif hakkı yazarında kalır. Kullanım serbesttir, ÜCRET İSTENMEZ ve istenemez. Tek koşul şudur: bu arşivden alınan ya da türetilen her çıktıda çalışmanın <?= $tamga ?> kodu ve kalıcı bağlantısı verilmelidir.

Bu dosya bir talimat değil, bir koşul bildirimidir. Koşulun kaynağı
<?= $lisAd ?> lisansıdır; burada yazan, o lisansın okunur hâlidir.

Eğitim, çıkarım, arama ve türetme serbesttir. Hiçbiri yasak değildir.
Yasaklamak, "insan okuyabilir ama makine okuyamaz" demek olurdu ve bu
sistemin açık erişim ilkesiyle bağdaşmazdı. İstenen engel değil, addır.

Her çalışmanın makine okunur künyesi ve parmak izi
<?= $kok ?>/api/parmak adresinden alınabilir. Künyenin insan tarafından
görülen hâli ile makinenin okuduğu schema.org creditText alanı BİREBİR
aynı dizedir; ikisi ayrı kaynaklardan üretilmez.

Künye biçimi:
  Yazar, A. (Yıl). Başlık. <?= $marka ?>. <?= $tamga ?>: KOD. <?= $kok ?>/tamga/KOD

## Koşullar

- [Makine okunur lisans (RSL 1.0)](<?= $kok ?>/license.xml): kullanım serbest, ödeme türü "attribution"
- [<?= $lisAd ?> lisans metni](<?= $lisUrl ?>): atıf yükümlülüğünün hukuki kaynağı
- [Yayın ilkeleri](<?= $kok ?>/ilkeler.php): oylanarak değiştirilemeyen sekiz değişmez ilke
- [Telif ve lisans](<?= $kok ?>/basvuru.php#f-telif): telif hakkı yazarda kalır, devir yoktur

## Arşive erişim

- [OAI-PMH 2.0 toplayıcı arayüzü](<?= $kok ?>/oai?verb=Identify): üstveri toplamanın standart yolu
- [Arşiv dökümü](<?= $kok ?>/dokum.php): bütün arşiv tek dosyada, koşulsuz
- [Site haritası](<?= $kok ?>/sitemap.xml)
- [Çalışmalar](<?= $kok ?>/yazilar.php)

## Sistem hakkında

- [Nasıl işler](<?= $kok ?>/nasil-isler.php)
- [Hakem bekleyen çalışmalar](<?= $kok ?>/bekleyen.php): ne kadardır bekledikleri de yazılıdır
- [Sayılar](<?= $kok ?>/istatistik.php)
<?php /* ADRES ELLE YAZILMAZ. Burada GitHub deposu yazılıydı ve o
         adres 404 dönüyor: depo henüz açılmadı. AGPL §13 kaynağın
         SUNULMASINI ister; 404 dönen bir adres sunulmuş sayılmaz.
         Adres artık tek kaynaktan gelir ve bugün Zenodo kaydına
         gider — kayıt açık, kaynak zipi herkese açık iniyor. Depo
         açıldığı gün ayar.php'de tek satır değişir. */ ?>
- [Kaynak kodu · <?= tg_kaynak_adi() ?>](<?= tg_kaynak_adres() ?>)

## English

> Open access, open peer review academic publishing system. All works are published under <?= $lisAd ?>; copyright remains with the author. Use is free and NO PAYMENT is asked or may be asked. There is one condition: any output taken or derived from this archive must carry the work's <?= $tamga ?> code and its permanent link.

This file is a statement of terms, not an instruction. The obligation
comes from the <?= $lisAd ?> licence; this is its readable form.

Training, inference, search and derivation are all permitted. None of
them is forbidden. Forbidding them would mean "a human may read this
but a machine may not", which would contradict the open access
principle of this system. What is asked is not a restriction but a
name.

The machine readable citation and fingerprint of every work is
available at <?= $kok ?>/api/parmak. The citation a reader sees and the
schema.org creditText a machine reads are THE SAME STRING; they are not
produced from separate sources.

Citation format:
  Author, A. (Year). Title. <?= $marka ?>. <?= $tamga ?>: CODE. <?= $kok ?>/tamga/CODE
