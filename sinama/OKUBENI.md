# Kutadgu sınama betikleri

Bu klasör 3 Ekim 2026'dan beri depodadır; yalnızca geliştirme sırasında
kullanılır ve sunucuda `.htaccess` ile dışarıya kapalıdır. Aşağıdaki
yollar (`/home/claude/kg/...`) geliştiricinin kendi ortamındandır; kendi
yollarınızla değiştirin. Kurucu adreslerini isteyen betikler (`kapi.php`,
`kurul-kapi.php`) onları `KUTADGU_KURUL_EPOSTA` ile gösterilen JSON
dosyasından okur.

**11 Ağustos 2026 notu.** Bu betiklerin sekizi bir oturumun kendi geçici
ortamında kalmış ve diske hiç yazılmadığı için kaybolmuştu. Yeniden
yazıldılar. **Yeni bir sınama betiği yazan herkes onu bitirdiği gün
`H:\claude optimize\sinama\` klasörüne yazsın**; çalışma ortamı geçicidir,
disk kalıcıdır.

## Yerel sunucuyu kurmak

    cd /home/claude/kg
    rm -rf ktest && cp -a kutadgunet ktest && rm -rf ktest/.git
    setsid nohup env KUTADGU_DATA=/home/claude/kg/ktest-data KTEST_DIR=/home/claude/kg/ktest \
      php -S 127.0.0.1:8941 -t /home/claude/kg/ktest sinama/krouter.php \
      > /home/claude/kg/sunucu.log 2>&1 < /dev/null &

`krouter.php` canlı sunucudaki yönlendirme kurallarının (tamga, kisi,
api) yerel karşılığıdır.

Sınama verisi boş bir dizinde kendiliğinden oluşmaz; tohumlanır:

    php sinama/veri-kur.php /home/claude/kg/ktest-data 60 /home/claude/kg/ktest
    KUTADGU_DATA=/home/claude/kg/ktest-data php ktest/dokum-gorev.php gece
    KUTADGU_DATA=/home/claude/kg/ktest-data php ktest/dokum-gorev.php paket

`kurul-kapi.php` ayrıca `/tmp/kv` altında bir kod kopyası ister:

    cp -a /home/claude/kg/kutadgunet /tmp/kv && rm -rf /tmp/kv/.git

`yazi-tipi-kapi.js` panelin ölçüm kopyasını ister; üretme komutu o
betiğin başlığında yazılıdır. Üretilmezse kapı KALIR (sessizce eksik
ölçmez).

## Kapı betikleri ve geçmesi gereken sayılar

11 Ağustos 2026 akşamı ölçülen taban. Ortak çevre değişkenleri:
`KUTADGU_DATA` (veri dizini) ve `KPORT` (varsayılan 8941).

| Betik | Taban | Ne bakar |
|---|---|---|
| `kurul-kapi.php` | 97 / 0 | Baş editörlük süresi, koltuk tavanı, zorunlu hâller |
| `dokum-kapi.php` | 79 / 0 | Arşiv dökümü: üç katman, kilit, günlük sınır, belirte ucu |
| `destek-ad.php` | 114 / **2** | Kefil → destekleyen araştırmacı; kod anahtarları yerinde mi |
| `yazarlik-kapi.php` | 42 / **1** | Kuruluş dönemi yazarlık kapısının açılıp kapanması |
| `yazarlik-metin.php` | 45 / **5** | Koşul cümlesinin tek kaynaktan üretilmesi |
| `muhur-kapi.php` | 48 / 0 | Tamga mührü: kararlılık, çakışma oranı, iddia denetimi |
| `hakem-kapi.php` | 76 / 0 | `tg_hakem_cakisma()`: ad, ORCID **ve e-posta** ile çakışma |
| `hakem-akis.php` | 94 / 0 | Uçtan uca hakem akışı; **4. bölüm şifre sızıntısı kapısıdır**, 7. bölüm kendi çalışmasına gönüllülük |
| `diyalog-kapi.php` | 131 / **3** | Yazar-hakem diyaloğu: kanal, sıra, tur, kalıcılık |
| `sir-kapi.php` | 33 / 0 | Sır taraması: anahtar, parola, kişisel veri, lisans. `KREPO=` ile hedef, `KGIT=` ile geçmiş |
| `sihirbaz-kapi.php` | 72 / 0 | Gönderim sihirbazı: adım sırası (1. adım **gönderim koşulları**), betiksiz gönderim, koşul beyanının sunucuda aranması, telif metni |
| `sihirbaz-tarayici.js` | 40 / 0 | Sihirbaz tarayıcıda: adımlar, **formun ilk ekranda olması**, sekme sırası, taslak, betiksiz görünüm |
| `dil-kapi.php` | 44 / 0 | Dil zinciri: ?lang -> çerez -> ülke -> tarayıcı -> varsayılan |
| `ceviri-kapi.php` | 45 / 0 | Üçüncü dil: çeviri katmanı, **boş sözlüklü dil İngilizce sayfanın aynısı mı**, dil seçim penceresi, ikili dil seçimi sayacı (tavan **19**) |
| `guven-kapi.php` | 35 / 0 | Güven işaretleri: tarihsiz "alındı" reddi, alınmayanın gizlenmemesi |
| `kvkk-kapi.php` | 16 / 0 | Ziyaretçi adresi: ham adres dışarı çıkmıyor, ülke tek kaynaktan |
| `panel-kapi.php` | 54 / 0 | Panel sekme ayrımı, bekleyen iş sayacı (**tek kaynak: bekleyen_isler**), çalışma rozetinin aşamadan gelmesi, kapaklı kart |
| `okuma-olcusu-kapi.js` | 81 / 0 | Çalışma sayfasının okuma düzeni: kaç ray, satır uzunluğu (**gerçek satırlar sayılır**; ekran bandı 65-95, dar ekranda 35-95), **okunan sütunun ekrandaki payı**, telefonda içindekiler, **kâğıt** (metin+ray tek yüzeyde, dengeli kenar), **yazarın bölüm başlığı**, çizelge sütunu doldurma, **rayın kendi kaydırma çubuğu olmaması** |
| `zenodo-kapi.php` | 55 / 0 | Kalıcı kimlik (DOI): üstveri eşlemesi, deneme evreni, jetonun ekrana dönmemesi, **yayımlamanın açık onay istemesi ve tek yerde olması**. Zenodo'ya BAĞLANMAZ |
| `bildirim-kapi.php` | 58 / 0 | Bildirimler: rozet = liste uzunluğu, her satırın bir HEDEFİ olması, `#sekme/kart` adresinin kartı açıp vurgulaması, iş bitince düşmesi; ayrıca **deneme işareti** (kendi çalışması, gerekçe, DOI, üçüncü kişinin emeği, geri alma) |
| `asama-kapi.php` | 172 / 0 | Hakemlik aşaması: raporsuz çalışmaya "hakemli" denmiyor **ve** gizlenmiyor; **iki ret alan çalışmanın geçmişi korunuyor** (raporlar sayfada kalır) |
| `arayuz-kapi.js` | 27 / 0 | [hidden] kuralı, **`.gizle` düzende yer kaplamıyor**, ilkeler gezinmesi, panel genişliği, sol menü, site haritası |
| `daralma-kapi.js` | 6 / 0 | Daralan hücre / dikey uzama taraması: 19 sayfa, iki genişlik |
| `yazi-tipi-kapi.js` | 6 / 0 | Punto merdiveni: her metnin boyu belirteçten mi, başlık serif mi etiket mi, h1 tek boy mu |
| `bosluk-kapi.js` | 3 / 0 | Sağda kalan boşluk: sarmış metnin kabı boşuna geniş mi, satır dizgenin ölçüsünü aşıyor mu |
| `duzen-karsilastir.js` | 0 / 178 bayrak | Düzen gerilemesi, 1440 ve 820 genişlikte. **Taban artık `taban-12agu-baslik`** |

Sunucu kütüğünde uyarı sayısı **0** olmalı:

    grep -ciE "warning|deprecated|notice|fatal" /home/claude/kg/sunucu.log

**Koyu yazılan kalanlar bilinen ve açıklanmış kusurlardır**, ölçüm hatası
değildir. Listesi `H:\claude optimize\KUTADGU-SONRAKI-OTURUM.md` içinde.
Sayı **artarsa** yeni bir gerileme var demektir.

### Sıra ve dikkat

- `asama-kapi.php` de **veriyi değiştirir**: sınama verisinde hiçbir
  çalışma `aranan` aşamasında değildir, o hâl kurulmadan ölçülemez.
  Yedeğini alır, `register_shutdown_function` ile geri yükler ve dökümü
  yeniden üretir. Kütük yolu `KLOG` ile verilir.
- `hakem-akis.php` ve `diyalog-kapi.php` **veriyi değiştirir**; ikisi de
  kendi yedeğini alır ve `register_shutdown_function` ile geri yükler.
  Yine de önce `cp -a ktest-data /tmp/ktest-data-yedek` almak iyi olur.
- Bir betik 429 verirse: `echo '{}' > $KUTADGU_DATA/hiz-sinir.json`.
- Aynı anda birden çok sınama koşacaksanız **her birine ayrı kapı ve ayrı
  veri dizini verin.** 11 Ağustos'ta iki oturum aynı kapıyı paylaştı ve
  biri ötekinin verisine yazdı.

## Çeviri: paket üret, dönüşü denetle

    php sinama/ceviri-cikar.php                     # sözlüğü tazele
    php sinama/ceviri-paket.php de                  # /tmp/ceviri-paket/de
    php sinama/ceviri-paket.php fr
    KUTADGU_DATA=<veri> php sinama/ceviri-al.php de <klasör>          # yalnız rapor
    KUTADGU_DATA=<veri> php sinama/ceviri-al.php de <klasör> --yaz    # sözlüğe yaz

`ceviri-al.php` dört kusuru geri çevirir ve dördü de yanlışlamayla
sınandı: uydurulmuş anahtar, bozuk HTML, kayıp yer tutucu, düşmüş
korunan ad. İki yanlış bayrak yolda bulundu ve düzeltildi: Almanca adı
büyük harfle yazınca ("tamga" → "Tamga") ad düşmüş sanılıyordu; "COPE"
adı "scope" sözcüğünün içinde bulunuyordu. Karşılaştırma artık büyük
harfe duyarsız ve SÖZCÜK SINIRINA bakıyor.

## Düzen gerileme betiği

    node duzen-karsilastir.js al <ad>                # anlık görüntü
    node duzen-karsilastir.js karsilastir <once> <sonra>
    node duzen-karsilastir.js <ad>                   # <ad> ile karşılaştır

Anlık görüntüler `.duzen/<ad>.json` altında. **`.duzen/taban.json`
11 Ağustos akşamının düzenidir; silmeyin.** 12 Ağustos'ta başvuru
sayfası sihirbaza döndü ve açıklıklar sayfasına güven işaretleri
kartı eklendi; ikisi de bilerek yapılan düzen değişiklikleridir.
Bu yüzden **yeni taban `.duzen/taban-12agu.json`dur** ve karşılaştırma
artık onunla yapılır:

    node duzen-karsilastir.js taban-12agu-baslik   # 0/178 gelmeli

12 Ağustos akşamı punto merdiveni tek kaynağa alındı (bütün sayfalarda
`h1` aynı boy, kart başlığı dizgeden, etiket tek ölçü). `taban-12agu`
ile karşılaştırılırsa **12 bayrak** yanar ve hepsi "başlık ölçek
adımları" eksenindedir; bilerek yapılmış değişikliklerdir. Bu yüzden
taban `taban-12agu-yazi`dır.

`taban.json` ile karşılaştırılırsa 14 bayrak yanar; bunların hepsi
açıklanmış değişikliklerdir (basvuru: 8, acikliklar: 6). Düzene dokunmadan önce
`node duzen-karsilastir.js taban` koşturun: 0/178 gelmeli.

12 eksen ölçülür: kart sayısı, satır sayısı, paragraf genişliği, küçük
dokunma hedefi, yatay taşma, taşan öge sayısı, ızgara sütun sayısı,
yığılma sırası, başlık ölçek adımları, odak halkası, yüzey rengi, kısık
devinim. Sekiz sayfa × iki genişlik.

## Görsel ölçüm betikleri (eski)

| Betik | Ne bakar |
|---|---|
| `olcum.js` | Yatay taşma ve konsol hatası, dört ekran genişliğinde |
| `bosluk.js` | Izgarada boş kalan sütun, başlık ile gövde hizası |
| `kontrast.js` | Metin karşıtlığı, açık ve koyu temada (WCAG) |
| `mobil.js` | Dar ekranda taşma ve dokunma hedefi ölçüsü |
| `etki.js` | Arama, süzme, menü gibi etkileşimler |
| `hiz.js` | Sayfa ağırlığı ve yükleme süresi |
| `kurul-olcum.js`, `dokum-olcum.js` | Kurul ve döküm sayfalarının düzeni |

## ÖLÇÜM TUZAKLARI

Bunları okumadan ölçüm yazmayın. Hepsi gerçekten vurdu.

1. **`.focus()` `:focus-visible`i tetiklemez.** Odak halkasını ölçmek
   için klavye `Tab` kullanın.
2. **`prefers-reduced-motion` geçişi `0s` yapmaz.** Canlıda `1e-06s`
   ölçüldü. Eşik `parseFloat(d) > 0.01` olmalı; yoksa tek sayfada 56
   sahte ihlal çıkar.
3. **`11.52px` ve `12.8px` belirtecin KENDİSİdir** (`--y-1`=.72rem,
   `--y-2`=.8rem). Kesirli olmak ihlal değildir. Belirteç listesini elle
   yazmayın, tarayıcıdan okuyun.
4. **`clamp()` değeri ihlal değil, tavandır.** Akan/sabit ayrımı için
   aynı ögeyi **1440 ve 820**'de ölçün. 1180 çok yakındır, ayrım çıkmaz.
5. **Yarı saydam zemini opak sanmayın.** Alfası 0.9'un altındaki zemini
   atlayıp ağaçta yukarı çıkın. Bu hata dokuz sahte WCAG kalanı üretti.
6. **Sayfa dili varsayılan İngilizcedir.** `k_dil()` sırayla `?lang=`,
   `kdil` çerezi, ülke, `Accept-Language`, ayar bakar; yerel `curl`
   sonuncuya düşer ve o `en`. `?lang=tr` yazılmazsa Türkçe metin hiç
   ölçülmemiş olur ve kapı sahte GECTI verir.
7. **Makale adresi `/tamga/<bcid>`.** `yazi.php?y=slug` ve
   `/10.00001/<doi>` 301 verir. Rapor gövdesi ve diyalog **yalnızca
   `?surec=1`** ile basılır.
8. **`yazi.php` içinde `k_tarih()` YOKTUR** (`k/veri.php`de ve `yazi.php`
   onu yüklemiyor). Çağrılırsa sayfa 500 verir ve ekran görüntüsünde
   boş değil **kırpılmış** görünür. Sayfa çektikten sonra HTTP kodunu,
   gövdenin `</html>` ile bittiğini **ve sunucu kütüğünü** ayrıca ölçün.
9. **`tg_ayar()` dosyayı bir kez okur ve bellekte tutar.** Ayar değiştiren
   her senaryo **kendi alt sürecinde** koşmalı. Alt süreç özet satırı
   basmasın, yoksa üst süreç `GECTI`/`KALDI` sözcüklerini iki kez sayar.
10. **Grep ile "elle yazım" saymayın.** Yorumlar da eşleşir ve kapı sahte
    GECTI verir. PHP için `token_get_all()` kullanın, yorumları ayırın.
11. **Kayan noktalı geometriyi dizge eşitliğiyle karşılaştırmayın.** Aynı
    şekil kimi zaman 46.7, kimi zaman 46.8 yazılıyor; çakışma oranı 5,5
    kat iyimser çıkar. Koordinatları yuvarlayıp parçaları sıralayın.
12. **Uçlar IP başına sayar** ama hangi başlıkla saydıklarına dikkat:
    `dk_hiz_sinir()` kimliği `dk_iz()`den alır, o da **yalnızca
    `HTTP_CF_CONNECTING_IP` ya da `REMOTE_ADDR`** okur. `X-Forwarded-For`
    bu yola HİÇ girmez; onunla gönderilen isteklerin hepsi tek kovaya
    düşer. Sayaç dosyası da iki tanedir: bu yolun sayacı **döküm
    dizinindeki `hiz.json`**, `api/index.php`nin ayrı `hiz-sinir.json`u
    değil. İkisi karıştırıldığında "429 sonrası aynı kayıt verilir" sözü
    ölçülmüş gibi görünüp ölçülmez. 11 Ağustos'ta ikisi de yapıldı ve
    `dogrula-kapi.php` iki sahte KALDI verdi.
13. **Aynı oturumda kodu değiştirip hemen ölçmeyin.** `php -S` sunucusunda
    opcache açıktır ve `opcache.revalidate_freq=2`dir: yazdığınız dosya
    iki saniye boyunca eski hâliyle koşar. Yanlışlama yaparken değişiklik
    ile ölçüm arasına **`sleep 3`** koyun; yoksa yanlışlamanın ısırmadığı
    sanılır. 11 Ağustos'ta bir yanlışlama tam bu yüzden bir öncekinin
    sonucunu tekrarladı.

14. **Logo bir DOSYA değil, satır içi SVG'dir** (`class="marka-im"`).
    `tamga-kucuk.svg` sayfanın HTML'inde hiç geçmez. Mühür de
    `viewBox="0 0 64 64"` ile aranamaz: logo ve bildirim simgesi aynı
    kutuyu kullanıyor. Mührün ayırt edici imzası `stroke-width="2.2"`.
    İkisi de `tamga-kapi.php`nin ilk yazımında sahte GECTI verdi.
15. **Bir işaret gövdeye BİÇEMLE de gelebilir.** Tamga deseni bir zemin
    görüntüsüdür; dosya adı `<style>` içindedir, `<main>` içinde hiç
    geçmez. Yalnız gövdeye bakan ölçüm, okurun ekranda gördüğü işareti
    "yok" sayar. Kuralın seçicisini okuyup o sınıfın gövdede bulunup
    bulunmadığına bakın.
16. **Parça parça basılan bir değeri düz dizgeyle aramayın.**
    Çözümleyici şeması kimliği dört ayrı `<span>` içinde basıyor;
    `KTG-2026-00001-7` dizgesi şemanın içinde HİÇ geçmiyor. Sayfa
    genelinde kod arayan ölçüm, şemadaki denetim hanesi elle yazılsa
    bile GECTI veriyordu. Parçaları toplayıp değeri kendiniz kurun.
17. **Sınırı anlatan cümle de yasak sözcükleri taşır.** "Her hatayı
    yakaladığı **söylenemez**" cümlesi, "her hatayı" arayan bir
    ölçümde KALDI verir. Aranan şey iddianın varlığı değil,
    **sınırlamanın** varlığı olmalı.

18. **Odak halkası GEÇİŞLİ çizilir.** `.kav-ic a` ve `.kmk-bag` üzerinde
    `transition` var (`--gecis`, .18s). Tab'a basıp hemen ölçen bir kapı
    `outline-width`i 0px okur ve halkası olan bir ögeyi "halkasız"
    sayar; 400 ms sonra aynı öge 2px veriyor. Tab ile ölçüm arasına
    **`sleep 300ms`** koyun. `wcag-kapi.js` bununla beş sahte ihlal
    verdi.
19. **WCAG'in satır içi istisnası KAP LİSTESİYLE yazılamaz.** İlk
    yazımda `p,li,dd,figcaption` listesi vardı; ray kutularındaki
    (`.blg-kutu`) cümle içi bağlantılar listede olmadıkları için 60'tan
    fazla sahte ihlal ürettiler. Doğru ölçüt kabın ADI değil, hedefin
    **çevresinde hedef olmayan metin bulunup bulunmadığıdır.** Ayrıca
    `<b><a>...</a></b>` gibi salt biçim saranlar **aşılmalıdır**, yoksa
    cümle ortasındaki bağlantı "tek başına hedef" sanılır.
20. **Bir ögeyi METNİYLE aramayın, DAVRANIŞIYLA arayın.** "İçeriğe atla"
    bağlantısı `/atla|skip/` ile arandı; Türkçesi "İçeriğe **geç**"
    olduğu için kapı, sekiz sayfada da var olan ve çalışan bir
    bağlantıyı "yok" saydı. Ölçülecek olan söz değil davranıştır: odak
    sırasındaki ilk bağlantı ana bölgeye gidiyor mu.

21. **`/yazi.php?y=<slug>` 301 verir.** Kanonik adres `/tamga/<bcid>`
    ve yönlendirme ÜRETİM konağına gider; yerel kapı oraya ulaşamaz ve
    403/000 okur. Sayfa `tg_yazi_yolu($y)` ile istenmeli — kapı böylece
    kanonik adresi de ölçmüş olur.
22. **Aranan alan sistemde ZATEN olabilir, başka bir yolda.**
    `'gonderim'` alanı yönetim düzeltme yolunda yıllardır duruyordu;
    "başvurudan çalışma üretilirken yazılıyor mu" sorusunu dosyanın
    tamamında arayan desen o satırı yakalayıp **sahte GECTI** verdi.
    Ölçüm ilgili BLOKLA sınırlanmalı (`$makale = [...]`).
23. **Düz `gün` araması ADLARA takılır.** `bekleyen.php` içindeki
    "Ergün", "Özgün" gibi yazar adları deseni doldurdu ve sayfa hiç
    süre yazmıyorken kapı GECTI verdi. Süre bir sayıdır: `\d+\s*gün`.
24. **`?? ` işleci null'ı yutar.** `(tg_gecikme($y)['bekleme_gun'] ?? 0)
    === null` denemesi ASLA geçemez; null beklenen denemeler değeri
    `??` kullanmadan okumalı. Aynı bölümde kurgular da yanlıştı: bir
    kaydı 'onayli' yapmak için rapor 400 karakterden uzun olmalı, iki
    işaret notu ve dizin anketi bulunmalı; 'cekildi' ise `kayitlar`
    içinde `tur=geri_cekme` kaydıyla olur. Kurgu aşamayı tutturmuyorsa
    kapı yanlış şeyi ölçer — bu yüzden aşamalar ÖLÇÜM satırında
    yazdırılıyor.
25. **Sayfanın dili ziyaretçinin ülkesinden çözülür.** Kapının
    gönderdiği `CF-Connecting-IP` başlığı `istatistik.php`yi
    İngilizce açtırdı, Türkçe desenler tutmadı, üç sahte KALDI çıktı.
    Dil istenirken **açıkça** verilmeli: `?lang=tr`.

26. **Boş ile boşu karşılaştıran deneme hep geçer.** "creditText görünen
    künyeyle birebir aynı" denemesi, İKİSİ DE YOKKEN `'' === ''` diye
    GECTI verdi. Eşitlik denemelerinde **boş olmama şartı eşitlikten
    önce gelir.**
27. **"display:none varsa gizli metin var" fazla kaba bir kuraldır.**
    Açılır form panelleri (`#gvYabanci`) ve sekme panelleri bu kurala
    takılır; onlar gizli metin değil, aşamalı açılımdır. Ayırt edici
    ölçüt: kutunun içinde **form denetimi** var mı, ve kap
    `details/dialog/[hidden]/[role=tabpanel]/form` mi.
28. **SimpleXML: `->children($ns)` sonrası öznitelik `$o['type']` ile
    OKUNMAZ**, boş döner; `$o->attributes()['type']` gerekir. Geçerli
    bir RSL belgesi bu yüzden "ödeme türü yok" ölçüldü — üstelik yan
    denemeyi de boş dizeyle boşuna geçirdi.
29. **APA künyesi ham yazar dizesini içermez.** Unvanı atar, soyadı öne
    alır: "Dr. Zeynep Aydın" → "Aydın, Z.". Künyeyi ham adın ilk
    harfleriyle arayan deneme hiçbir zaman geçemez; aranacak olan
    **soyadıdır.**
30. **Gizlilik ögenin KENDİSİNDEN okunamaz.** `display:none` olan bir
    üstögenin çocuğunda `getComputedStyle(e).display` hâlâ `block`
    döner. Gizlilik ağaçta **yukarı çıkarak** belirlenmeli; sıfır ölçülü
    kutu ayrı bir suç değil, gizliliğin belirtisidir. Bu hata anasayfada
    **397 sahte bulgu** verdi.
31. **Sekme paneli gizli metin değildir** (`role="tabpanel"`). Kapalı
    sekmenin içeriği `display:none`tur ve kullanıcı sekmeye basınca
    görünür. Etkileşimli açılım listesine dahil edilmeli.
32. **opcache tuzağı (madde 13) yanlışlamada TEKRAR ETTİ.** `lisans.php`
    değiştirilip 3 sn beklendiği hâlde sunucu eski dosyayı verdi ve
    yanlışlama "ısırmadı" göründü; ikinci denemede ısırdı. Bir
    yanlışlama beklenmedik biçimde geçerse **önce dosyanın gerçekten
    sunulduğunu** `curl` ile doğrulayın.

33. **`preg_quote('')` boş desen üretir ve BOŞ DESEN HER METNE UYAR.**
    "Reddedilmiş çalışma sayısı sayfada var mı" denemesi, aşama metni
    henüz hiç yokken GECTI verdi. Aranan dize boşsa deneme geçemez.
34. **Veri yokken geçen deneme, geçmiş sayılmaz.** Sınama verisinde
    reddedilmiş başvuru yoktu; mahremiyet sızıntısı denemesi boşlukta
    GECTI verip sayıyı şişiriyordu. Ölçülecek veri yoksa deneme
    **ÖLÇÜM olarak yazılır**, GECTI olarak değil.
35. **Kapılar da eskir.** `asama-kapi.php` "dört basamak" sayısını
    **sabit yazmıştı**; beşinci aşama eklenince gerileme gibi göründü,
    oysa gerileyen kod değil kapının varsayımıydı. Beklenen değerler
    mümkün olduğunca **kaynağın kendisinden türetilmeli** (aşama
    listesinden), elle yazılmamalı.

36. **Deseni koda değil, koda YAZILIŞINA göre yazmayın.** "Şehir alanı
    üretiliyor mu" denemesi `'sehir' => (string)($d['city']` arıyordu;
    kod ise DEĞİŞKENE atıyordu (`$sehir = (string)($d['city'] ?? '')`).
    Desen hiç tutmadığı için, şehir hâlâ üretilirken deneme GECTI verdi.
    Aranan şey biçim değil, **dış yanıtın alanına dokunulup
    dokunulmadığıdır** (`['city']`).
37. **Kaynakta dize ararken YORUMLARI ÇIKARIN.** "ip-api.com çağrısı
    yok" denemesi, kusuru **anlatan yorumun kendisine** takıldı ve
    düzeltme yapıldığı hâlde KALDI verdi. Bu projede her düzeltmenin
    yanına neyin neden kaldırıldığı yazılıyor; o yazı kalacak, kapı ona
    takılmayacak. `token_get_all()` ile `T_COMMENT`/`T_DOC_COMMENT`
    düşürülür. Ölçülen şey **çağrıdır**, anlatı değil.

38. **PHP dizi değişmezinde AYNI ANAHTAR İKİ KEZ yazılırsa SONUNCUSU
    kazanır.** `guven-kapi.php` ayar dosyasına yamasını dizinin BAŞINA
    koyuyordu; asıl satır yamayı eziyordu ve "tarihsiz alındı
    reddediliyor" denemeleri hiçbir şey ölçmeden GECTI veriyordu.
    Bir ayar dosyasına yama koyarken yamanın gerçekten kazandığını
    ayrıca doğrulayın (kapı bunu artık "TARİHLİ alindi kabul ediliyor"
    denemesiyle yapıyor: yama tutmazsa o deneme de düşer).
39. **`[hidden]` bir SINIF kuralına yenilir.** Tarayıcının kendi
    sayfasında `[hidden]{display:none}` yazılıdır, ama özgüllüğü bir
    sınıf seçicisiyle aynıdır; sonra gelen `.sh-adim{display:block}`
    onu ezer. Ölçüldü: kapalı sihirbaz adımları ekranda görünmediği
    hâlde SEKME SIRASINDA duruyor ve klavyeyle gezen biri 29 kez
    kapalı bir adımın içine düşüyordu. Bir şeyi `hidden` ile
    kapatıyorsanız yanına `[hidden]{display:none !important}` yazın.
40. **Uç yalnız POST kabul ediyor olabilir.** `/api/iletisim` GET ile
    sorulunca genel 404 yakalayıcısına düşüyor; "iletişim ucu yok"
    diye sahte bir KALDI çıktı. Bir ucun varlığını ölçerken onun
    kabul ettiği yöntemle sorun.
41. **Deneme dizesi ölçülen SAYFADA gerçekten bulunmalı.**
    `ceviri-kapi.php` çeviri denemesini 'Open access' ile yapıyordu;
    o dize başvuru sayfasında geçiyor, ölçülen `nasil-isler.php`de
    değil. Çeviri katmanı çalıştığı hâlde kapı "çeviri sayfada
    görünmüyor" dedi.
42. **`/lisans.php` bir insan sayfası değil, makine okur RSL
    belgesidir.** İçinde "CC BY 4.0" adı geçmez; lisans ADRESİYLE
    bildirilir. Ad arayan ölçüm, geçerli bir belgeyi eksik sandı.

43. **`[hidden]` DÜZELTMESİ BİR @media BLOĞUNUN İÇİNE DÜŞEBİLİR.**
    Genel kural `.yan-perde[hidden]` satırının yanına konmuştu; o satır
    dar ekran medya sorgusunun içindedir ve kural da oraya hapsoldu.
    Geniş ekranda on altı "Sonraki bölüm" şeridi hâlâ görünüyordu.
    Genel bir kural hiçbir koşulun içinde durmamalı; dosyanın en
    başına yazılır. (39. maddenin devamı.)
44. **YENİ BİR ÖLÇÜ KİPİ BEYAZ LİSTEYE EKLENMEZSE SESSİZCE DÜŞER.**
    `k_bas()` `$olcu` değerini `['okuma','genis','tam']` listesine
    karşı denetler ve tanımadığını 'okuma'ya çevirir. Panel için
    eklenen 'pano' listeye yazılmayınca sayfa 980 pikselde kaldı ve
    "genişlik düzeltmesi işlemedi" gibi göründü. Sessiz varsayılan
    doğrudur; listeyi güncellemeyi unutmak değil.
45. **`.pn{max-width:...}` GİBİ SAYFAYA GÖMÜLÜ BİR SINIR, ÖLÇÜ KİPİNİ
    GEÇERSİZ KILAR.** Panel `--en-metin-genis` (1240px) ile sabitlenmişti;
    sayfanın ölçü kipi ne olursa olsun 1240'ta kalıyordu. Genişlik iki
    yerde tanımlıysa biri ötekini sessizce yener. Sayfanın genişliği
    ölçü kipinin işidir.
46. **KOMUT SATIRINDA ÜRETİLEN SAYFA İNGİLİZCEDİR.** (6/25'in bir başka
    yüzü.) `php panel.php` ile üretilen çıktıda Türkçe kart adlarını
    arayan kapı, sayfa doğru üretildiği hâlde on kartın dokuzunu "YOK"
    ölçtü. Dil, `$_GET['lang']` kuran küçük bir başlatıcıyla açıkça
    istenmeli.
47. **`tg_yazar_mi()` SALT ADLA EŞLEŞMEYİ BİLEREK REDDEDER.** Ad
    eşleşmesi ancak hesabın doğrulaması onaylıysa VE kayıtta ORCID
    yoksa kabul edilir. Kapı bunu kusur sanıp "kendi yazarını
    tanımıyor" diye sahte bir KALDI verdi; oysa ölçtüğü şey kuralın
    kendisiydi.

48. **SATIR SAYISI KUTU YÜKSEKLİĞİNDEN HESAPLANMAZ.** "yükseklik /
    satır yüksekliği" formülü, içinde iki ayrı punto olan bir kutuda
    (başlık + açıklama) satır sayısını olduğundan çok gösterir;
    anasayfadaki kartlar böyle altı sahte bulgu verdi. Bunu düzeltmek
    için "çocuğu olmayan öge" şartı konunca bu kez gerçek çizelge
    hücreleri elendi ve kapı ısırmaz oldu. Doğrusu: bir METİN
    DÜĞÜMÜNÜN `Range.getClientRects()` sayısı. O sayı kardeşlerin
    puntosundan, ızgaradan ve satır yüksekliğinden bağımsızdır.
49. **`max-width` bir `<td>` üzerinde YOK SAYILIR** (`table-layout:auto`
    ile). Hücreyi daraltarak yapılan bir yanlışlama bu yüzden ısırmadı
    ve kapı suçlu sanıldı; oysa yanlışlamanın kendisi hiçbir şeyi
    daraltmamıştı. Çizelge hücresini gerçekten daraltmak için
    `table-layout:fixed` gerekir.
50. **DÜZELTMEYİ SÜTUNUN YERİNE BAĞLAMA.** `td:first-child` ile yazılan
    daralma düzeltmesi "Çalışma başına" çizelgesini düzeltti, "Son
    okuma kayıtları" çizelgesinde ise hiçbir şey değiştirmedi: orada
    ilk sütun ZAMAN, başlık ikincidir. Kusur içeriğe bağlıdır, yere
    değil; hücre `.tb-ad` sınıfını alır ve kaçıncı sütunda olduğu
    önemsizdir.

51. **BELİRTECİN HESAPLANMIŞ DEĞERİ METİNDİR.**
    `getComputedStyle(html).getPropertyValue('--y-6')` piksel değil,
    `"clamp(1.05rem,.98rem + .34vw,1.2rem)"` dizesini döndürür. Punto
    merdivenini okumak için ekrana `font-size:var(--y-6)` verilmiş bir
    deneme ögesi konur ve ORADAN okunur. Ayrıca clamp'li basamaklar
    ekran genişliğine göre değişir; merdiven her genişlikte yeniden
    çözülmelidir.
52. **BİR SAYFADA BİRDEN ÇOK `h1` OLABİLİR VE İLKİ ARADIĞINIZ
    OLMAYABİLİR.** Panelin ölçüm kopyasında giriş kutusunun `h1`i
    panelin kendi `h1`inden önce geliyordu; kapı ilkini ölçtü, panelin
    başlığını hiç görmedi. Yanlışlama sınamasında panelin başlığı
    bilerek bozulduğu hâlde kapı "hepsi aynı" dedi. Aynı düzeydeki
    ögelerin HEPSİ toplanır, ilki değil.
53. **PANELİN İÇİ BETİKLE AÇILIR.** `#pnIc` `.gizli` sınıfıyla kapalı
    doğar ve başlıkları boştur; kapak kaldırılmadan panelin tek bir
    biçim kuralı bile ölçülemez, üstelik ölçülmüş sanılır. Ölçümde
    kapak kaldırılır ve boş başlıklara örnek metin konur; ölçülen şey
    yetki değil, biçimdir.
54. **GEVŞEK YAKINLIK PAYI, ÖLÇMEDİĞİNİ ÖLÇMÜŞ GÖSTERİR.** Punto
    kapısı önce 0.6 piksellik pay kullanıyordu; `.93rem` (14.88) ile
    `.95rem` (15.20) arasındaki fark bu payın altında kaldı ve
    "merdiven dışı bir değer yaz" yanlışlaması ısırmadı. Pay yalnızca
    YUVARLAMA için vardır: 0.25 piksele indirildi. (Bkz. 10 numaralı
    eşik tuzağı; aynı kusurun tipografideki hâli.)
55. **AYNI ŞEYİN İKİ ADI, İKİ BİÇİM DEMEKTİR.** Etiket biçimi için
    `.et` diye yeni bir sınıf yazıldı; oysa dizgede etiket zaten
    `.bas-ust` adıyla vardı. Ölçüm iki ayrı etiket ölçüsü gösterdi
    (11.52px/.13em ve 12.8px/.1em). Yeni bir sınıf yazmadan önce
    dizgede o işi gören bir ad var mı diye bakın.

56. **KAPIYA ADIM ADI YAZMAYIN, SIRAYI SAYFADAN OKUYUN.**
    `sihirbaz-tarayici.js` "beşinci adım `etik` olmalı", "birinci adım
    `calisma` olmalı" diye yazılmıştı. Listenin başına bir adım
    eklenince dört ölçüm birden KALDI verdi — oysa hiçbiri bozulmamıştı;
    ölçülen şey adımın ADI değil, "n numaralı bağ n numaralı adımı
    açıyor mu"dur. Sıra artık sayfadan okunur (`SIRA[4]`). Aynı kural
    tek kaynak kuralının ölçüm tarafıdır: kapı da tek kaynağı okumalı.
57. **TARAYICIDA `required` YAZMAK KAPI DEĞİLDİR.** Koşul onay kutusu
    önce yalnız sayfada zorunluydu; doğrudan bir POST beyanı atlıyor ve
    kayda "koşullar okundu: hayır" yazan bir başvuru düşüyordu. Kutu
    sunucuda da arandı. Bir beyan, atlanabildiği sürece beyan değildir.
58. **BAŞVURU GÖVDESİ ÜRETEN HER KAPI, YENİ ZORUNLU ALANI DA
    VERMELİDİR.** `kosullar_okundu` sunucuda aranınca `hakem-akis.php`
    94/0'dan 26/64'e düştü: akış kapısı başvuru gönderemez olmuştu.
    Kusur kodda değil, kapının gövdesindeydi. Yeni bir zorunlu alan
    eklerken `grep -l "yazar-basvuru" sinama/*.php` ile hepsine bakın.

59. **BİR ADIMIN ANLAMI DEĞİŞİNCE O ADIMI ÖLÇEN HER SATIR DEĞİŞİR.**
    Koşul adımı "beyan adımı"ndan "okuma adımı"na dönünce
    `sihirbaz-tarayici.js`in 3. bölümü sessizce yalancı oldu: ilk
    `İleri` tıklaması doğrulamayı değil OKUMA adımını geçiyordu, ölçüm
    ise "başlık boşken durdurdu" sanıyordu. Test GECTI veriyordu ve
    hiçbir şey ölçmüyordu. Bir adımın anlamı değişince, o adımdan önce
    ve sonra ne ölçüldüğüne tek tek bakın.

60. **OKUNUR SATIR UZUNLUĞU BİR ÜST SINIRDIR, BİR HEDEF DEĞİL.**
    Koşul metnine `max-width:56ch` yazıldı; kap 668, metin 499 piksel
    oldu ve sağda %25 boş kaldı. Metin "okunur" ölçüdeydi ama kutunun
    içinde sıkışık göründü, çünkü kap onunla birlikte daralmamıştı.
    Kabından dar tutulan metin, boşluğu kabın içine hapseder. İki
    doğrusu vardır: kabı metnin ölçüsüne indirmek ya da kalan yeri bir
    işe yaramaya çevirmek. Sınır elle seçilen bir sayı değil, dizgenin
    kendi ölçüsü olmalıdır (`--olcu-metin-genis`).
61. **SARKACIN ÖTEKİ UCU DA ÖLÇÜLMELİ.** Boşluğu kapatmak uğruna metni
    kabın tamamına yaymak, 137 harflik satırlar demektir. `bosluk-kapi`
    bu yüzden iki yandan da bakar: hem sağda kalan yer, hem dizgenin en
    geniş ölçüsünü aşan satır. Tek yanlı bir kapı, düzeltmenin yarattığı
    kusuru görmez — ölçüm eklerken "bunun tersi de kusur mu" diye sorun.

62. **TEK GENİŞLİKTE ÖLÇMEK, ÖLÇMEMEKTİR.** `bosluk-kapi` ilk yazımda
    1440 ve 820'de ölçüyordu ve yeşil verdi; kusur 1835 pikselde
    duruyordu (kap 1054'e çıkıyor, metin 92 karakterlik sınırda 819'da
    kalıyor, sağda 223 piksel boşluk). Genişliğe bağlı bir kusuru tek
    genişlikte aramak, aramamaktır. Ölçüm artık 1920/1680/1440/820'de
    yapılır.
63. **`minmax(400px,1fr)` DAR EKRANDA SAYFAYI TAŞIRIR.** Izgara rayının
    en küçük ölçüsü kabından büyük olamaz; 320 piksellik ekranda 400
    piksellik bir ray istemek, kabı taşırır. Doğrusu
    `minmax(min(400px,100%),1fr)`. Aynı kusur `/bekleyen.php`de
    `minmax(320px,1fr)` olarak zaten duruyordu ve o sayfa WCAG
    listesinde olmadığı için görülmemişti (16 piksel taşma).
64. **BİR SAYFAYI LİSTEYE EKLEMEK, O SAYFAYI ÖLÇMEK DEMEKTİR.**
    `/bekleyen.php` wcag listesine eklenince iki kusur birden çıktı:
    320 pikselde taşma ve h1'den h3'e atlayan başlık sırası. Ölçülmeyen
    sayfa, kusursuz sayfa değildir.

65. **YENİ BİR DİL EKLEMEK, DİL ZİNCİRİNİ DE DEĞİŞTİRİR.** Pilot için
    sınama kopyasına `de` eklendiğinde `dil-kapi.php` 44/0'dan 43/1'e
    düştü: "Almanya'dan gelen ziyaretçi varsayılana (İngilizce) düşer"
    ölçümü artık doğru değildi, çünkü Almanca vardı. Kapı haklıydı.
    Gerçekten bir dil açıldığında o beklenti SESSİZCE değil, bilerek
    güncellenir.

66. **SAYMAK ÖLÇMEK DEĞİLDİR: SAYFAYA BAKIN.** Üçüncü dile hazırlığı
    önce "kaç yerde `$en ?` var" diye saydım (423). Doğru ölçü şuydu:
    **sözlüğü boş bir dilde sayfa, İngilizce sayfanın aynısı olmalı.**
    O ölçü kurulunca gerçek sayı çıktı: 593 Türkçe dize sızıyordu — ve
    düzeltme ilerledikçe sayı 593 → 456 → 350 → 150 → 106 → 27 → 2 → 0
    diye indi. Sayaç yaklaşık, sayfa kesindir.
67. **ÇAĞIRANIN AÇIK İSTEĞİ, SAYFANIN DİLİNDEN ÖNCE GELİR.** `tg_c`
    ilk yazımda doğrudan `k_c`ye gidiyordu; `k_c` sayfanın dilini okur.
    Oysa `tg_asama_metni($a, $en)` gibi işlevlere bayrağı BİLEREK veren
    çağıranlar var (arşiv dökümü iki dili birden üretir, komut satırı
    Türkçe metin ister). Bayrak yok sayılınca Türkçe sayfada "Seeking
    reviewers" yazdı: `asama-kapi` 160/0'dan 148/11'e düştü. Kural:
    sayfa ÜÇÜNCÜ bir dildeyse katman, değilse çağıranın dediği.
    Aynı kusur tarih biçiminde de çıktı ("runs to 31 Aralık 2027").
68. **BİR DİZİ ANAHTARI OLARAK DİL KODU KULLANILMAZ.** `$tablo[k_dil()]`
    üçüncü dilde boş döner, çünkü tabloda yalnız 'tr' ve 'en' vardır.
    Doğrusu tabloyu çeviri katmanına vermektir: `tg_t($tablo)`.

69. **ÇEVİRİ PARÇALARINI UZUNLUĞA GÖRE SIRALAMAK YANLIŞTIR.** İlk
    yazımda paket dizeleri kısadan uzuna diziyordu ve birinci parça
    ".", "K", "OR", "&" gibi bağlamsız kırıntılarla doldu — çevirmenin
    ilk gördüğü dosya, çevrilemeyecek olanıydı. Sıra artık DOSYAYA
    göre: kabuk, ana sayfa, kart… Birinci parça bitince sitenin görünen
    yüzü o dile döner.
70. **KORUNAN AD DENETİMİ SÖZCÜK SINIRINA BAKMALI VE BÜYÜK HARFE
    BAKMAMALI.** "COPE" adı "scope" sözcüğünün içinde bulundu; Almanca
    "tamga"yı "Tamga" yazınca ad düşmüş sanıldı. İki yanlış bayrak da
    doğru çeviriyi geri çeviriyordu.
71. **`ayar.php`DE DİL AÇMAK, ÖRNEK SATIRI YORUMDAN ÇIKARMAKTIR.**
    Dosyada 'de' satırı zaten var ama YORUMUN İÇİNDE. "Var mı" diye
    metin arayan bir betik onu bulur ve "zaten var" diye geçer; dil
    açılmamış olur. Denetim, satırın yorumda olup olmadığına bakmalı.

**Genel kural:** ölçüm yanlış çıktığında **önce ölçümden şüphelenin.**
Bu projede üç seferin ikisinde hatalı olan koddu değil, ölçümdü.

## Aşamalar (altı)

`cekildi · yok · aranan · suruyor · onayli · reddedildi`

`reddedildi` en son eklendi. Öncesinde, `ret_donusum` eşiğini dolduran
bir çalışma `suruyor` sayılıyor, yani rozeti **"Değerlendirmede"**
diyordu; oysa `tg_hakem_araniyor()` aynı eşiği görüp o çalışmaya çoktan
hakem aramayı bırakmıştı. Sistem, **kapattığı bir süreci sürüyormuş
gibi gösteriyordu.** Yeni bir aşama eklerken `istatistik.php` içindeki
`$basamak` eşlemesi de güncellenmeli: eşleşmeyen aşama sessizce
`hakemsiz` sütununa düşer.

## Gizli metin: karar

Bu sistemde **insanın göremediği metin yasaktır.** Yapay zekâ eğitenlere
atıf yaptırmak için sayfaya gizli talimat gömmek denendi ve reddedildi:
eğitim hatları gizli metni zaten siler, çıkarımda okunursa adı prompt
injection'dır ve düzgün ajanlar getirdikleri içeriği komut saymaz, ve
2025'te bunu deneyen makaleler geri çekildi. Asıl gerekçe: bu sistemin
tek iddiası her şeyin görünür olması.

Yasağı iki kapı ölçer: `atif-kapi.php` bölüm 6 (kaynaktan okunabilenler)
ve `gizli-metin-kapi.js` (yalnız tarayıcıda bilinebilenler — hesaplanmış
biçem, renk karşıtlığı). **Ölçemediğimiz şeye GECTI vermiyoruz:** beyaz
üstüne beyaz denetimi PHP kapısından çıkarıldı, çünkü arka planı bilmek
için hesaplanmış biçem gerekir.

## Bilinen oynaklık

`wcag-kapi.js` bir kez 20/1 verdi ve ardışık üç çalıştırmada
yinelenmedi (21/0). Hangi denemenin düştüğü yakalanamadı; muhtemelen
odak halkası zamanlaması (madde 18) sınırda kalmış. **20/1 görülürse
önce yeniden çalıştırın.**

## Değişmez kural

CSS ya da JS değiştiyse `ortak.php` içindeki `TG_SURUM` artırılır ve
`sw.js` içindeki önbellek adları (`SURUM`, `KABUK`) yenilenir. Yoksa
tarayıcı eski dosyayı bir yıl boyunca saklar.

---

## 72. Sözlük "%100 doldu" diyordu, sayfanın beşte biri İngilizceydi

`ceviri-cikar.php` yalnız `k_c('…','…')` çağrılarını topluyordu ve 1822
dize sayıyordu; `ceviri-al.php` de 1822/1822 doluyor diyordu. İki ölçüm
de kendi içinde doğruydu. Ama Almanca sayfa açıldığında **371 dize
İngilizce kalıyordu.**

Sebep: arayüz metinlerinin hepsi çağrı yerinde durmuyor.

```php
$madde = ['tr' => '…', 'en' => '…'];   // ortak.php'de bir tablo
echo tg_t($madde);                      // çeviri BURADA aranıyor
```

Belirteç çözümlemesi bunu izleyemez; izlenecek şey artık bir dize değil
bir program akışıdır. Üç biçim birden kaçıyordu: `k_t`/`tg_t` argümanları,
`'tr'=>/'en'=>` çiftleri taşıyan veri tabloları ve sıraya dayalı
`['Türkçe', 'English']` ikilileri (bir de `$c('…','…')` takma adlı
çağrılar).

**Kural: bir dizenin çevrilmiş sayılabilmesi için önce SAYILMASI
gerekir. Sayılmayan dize hiçbir zaman eksik görünmez.**

Çıkarıcı çağrıyı değil ÇİFTİ arayacak biçimde yeniden yazıldı: 1822 →
2307. Fazla toplamak ucuz (sözlükte durur, hiç aranmaz), eksik toplamak
pahalıdır.

## 73. Eksiği sayan bir yer yoktu; şimdi sayfanın sonunda yazıyor

Yukarıdaki kusurun asıl sebebi ölçüm eksikliğiydi: çalışma anında kaç
dizenin karşılığı bulunamadığını söyleyen hiçbir yer yoktu.
`tg_ceviri_eksik()` sayıyordu ama kimse okumuyordu.

Artık üçüncü dillerde sayfanın en sonuna yazılıyor:

```html
<!-- ceviri-eksik: 4 -->
```

Sayfanın **sonunda**, çünkü kaç dizenin düştüğü ancak sayfa kurulup
bittiğinde bilinir. Yorum satırı: okura görünmez, kapıya görünür.

## 74. Kalan 6 dize kendi kendini yiyor (bilinen, kapatılmadı)

Şu cümle sözlükte hiçbir zaman bulunmaz:

> "…closes on **Dezember** 31, 2027."

Ay adı zaten çevrildiği için birleşen İngilizce cümle artık İngilizce
değildir; anahtar değişir, karşılık bulunamaz. Kalıcı çözümü, cümleyi
`%1` yer tutucusuyla kurup şablonu çevirmektir. 2392 dizenin 6'sı; şimdilik
İngilizceye düşüyorlar ve bu tasarlanmış davranıştır.

## 75. Boş sözlük ölçümü, sözlük depoya girince bozuldu

`ceviri-kapi.php` "boş sözlüklü bir dil İngilizce sayfayla aynı mı"
diye ölçüyordu ve pilot dil olarak Almancayı kullanıyordu. Almanca
açılıp sözlüğü `k/ceviri/de.json` olarak depoya girince ölçümün
önkoşulu yok oldu.

Daha sinsisi: kapı `ayar.php`'ye ikinci bir `'de' =>` satırı ekliyordu.
PHP dizisinde aynı anahtar iki kez yazılırsa **sonuncusu kazanır**; yani
"dil açıldı" denemesi, hiçbir şey açılmamışken bile GECTI derdi.

Pilot dil İspanyolcaya (`es`) çevrildi ve kapıya bir önkoşul denemesi
eklendi: *pilot dil arayüz dilleri arasında zaten tanımlı DEĞİL.* O
arama `'diller'` bloğunun İÇİNDE yapılır — bütün dosyada arandığında
`'calisma_dilleri'` listesindeki `'es' => 'Español'` satırına takılıyordu.
**Ölçüm, baktığı yeri de bilmeli.**

## 76. Accept-Language metin gibi aranıyordu, liste gibi okunmuyordu

`k_dil()` tanımlı dilleri sırayla dolaşıp her birini
`Accept-Language` metninin içinde arıyordu. İki dil varken çalıştı;
üçüncü dil açılınca yanlış cevap verdi:

```
Accept-Language: de-DE,de;q=0.9,en;q=0.8
Tanımlı sıra   : tr, en, de, fr
```

Döngü `en`e `de`den önce bakar, `",en"` dizisini bulur ve Almanca
isteyen tarayıcıya İngilizce verir. Kusur arama biçiminde değil
**sırada**ydı: cevabı ayarın sırası belirliyordu, ziyaretçinin
bildirdiği öncelik değil. Başlık artık ayrıştırılıyor, `q` ağırlığına
göre sıralanıyor, ana etiket (`de-DE` → `de`) alınıyor.

## 77. "Çıkış yap" oturumun yarısını kapatıyordu

Aynı oturumda iki kimlik durabilir:

```
$_SESSION['kutadgu_hesap']   hesap (yazar, hakem, editör)
$_SESSION['behic_giris']     yönetici (tek parolalı kapı)
```

Arayüzdeki bütün çıkış düğmeleri `/api/hesap/cikis`'e gider ve o uç
yalnız **birincisini** siliyordu. Kişinin gördüğü şey şuydu: çıkış
yapılıyor, yeniden girilmeye çalışılıyor, parola yanlış olduğu için
ekranda hata beliriyor — **ama panel yine de açılıyordu.**

Bu bir görüntü kusuru değil. `girisli()` tek başına
`yonetim_yazma_mi()` içinde yeter ve bütün `/yonetim/*` uçlarını açar.
`hs_cikis()` artık oturumun tamamını yıkar ve çerezi düşürür; `/cikis`
de aynı işlevi çağırır (iki yıkım kodu olsaydı biri düzeltilir öteki
unutulurdu).

Ölçen kapı: **`sinama/cikis-kapi.php`** (15/0). Kusurluyken 8/5 veriyordu.

## 78. Kapı gövdeyi harf sayarak arıyordu

`cikis-kapi.php` "hs_cikis içinde session_destroy var mı" denemesini
`[^}]{0,400}` ile yazmıştım. Kusur düzeltilip işleve gerekçe yorumu ve
çerez düşürme eklenince gövde 400 harfi aştı ve kapı, **davranış
düzelmiş olduğu hâlde** KALDI demeye başladı. Gövde artık ilk sütundaki
kapanış ayracına kadar okunuyor. *Ölçüm metnin uzunluğuna değil,
sınırına bakmalıdır.*

## 79. Yeni CSS kuralı sessizce yenildi: özgüllük

"Kapalı bir kapak koca bir satır kaplamasın" diye şu kural yazıldı:

```css
.pn-pnl.acik > .pn-katla:not([open]){grid-column:span 6}
```

Deneme kartı düzeldi (529 piksel), **sunucu durumu kartı hiç
değişmedi** (1075 piksel). İki kart aynı sınıfları taşıyordu; fark
konumlarındaydı: sunucu durumu sekmenin son kartıydı ve ona daha eski
bir kural değiyordu —

```css
.pn-pnl.acik > .pn-kart:last-child:nth-child(odd){grid-column:1/-1}
```

Bu seçici bir sınıf ve bir sözde-sınıf daha taşır, yani daha
**özgül**dür; kuralın kaynakta sonra yazılmış olması onu kurtarmaz.
Seçiciye `.pn-kart` eklenerek eşitlendi.

*Bir CSS kuralı yazıldı diye uygulanmış olmaz. Ölçülecek şey kuralın
varlığı değil, öğenin genişliğidir.*

## 80. Ölçüm döngüsü kendi kendini kilitledi (hız sınırı)

Dokuz farklı ekran genişliğinde panel ölçülürken her adım aynı hesapla
giriş yapıyordu. Bir noktadan sonra **bütün ölçümler sıfır dönmeye
başladı**: şerit yok, kutu yok, hiçbir sekme yok. Değişen şey CSS
değildi; `hiz-sinir.json` içindeki `giris:` sayacı 37'ye çıkmış ve
sistem doğru davranarak girişleri reddetmeye başlamıştı.

Yani panel çalışıyordu, sistem doğru çalışıyordu, **yalnız ölçüm
yanlıştı.** Ölçüm artık her genişlik için ayrı bir `CF-Connecting-IP`
gönderiyor (PHP kapıları bunu zaten yapıyordu, tarayıcı ölçümleri
yapmıyordu).

*Ölçüm birdenbire her yerde sıfır gösteriyorsa, kusur ölçülen şeyde
değil ölçenin kendisindedir.*

## 81. `.pn-sk` iki ayrı işi birden adlandırıyordu

Sekme düğmeleri de, İleti süzgeci düğmeleri de `.pn-sk` sınıfını
taşıyordu. `sekAc()` bağlayıcısı `.pn-sk` üzerinden kuruluyordu, yani
"Bekleyen" süzgecine basan kişi `sekAc(undefined)` çağırıyor ve
**Özet sekmesine atılıyordu.** Aynı seçici sekme değiştirirken süzgeç
düğmelerinin `acik` sınıfını da siliyordu.

Ölçüt artık `[data-sek]`: sınıf neye benzediğini söyler, öznitelik ne
olduğunu.

## 82. İki kapı bir önbelleği paylaşıyordu ve ikisi de dünkü kodu ölçüyordu

`yazarlik-metin.php` ile `yazarlik-kapi.php`, senaryolarını gerçek
kaynağa değil bir kopya dizine yazar. İkisi de aynı dizini kullanıyordu
(`/tmp/ky`) ve ikisi de kopyayı şöyle kuruyordu:

```php
if (!is_dir(KOPYA)) { exec('cp -a ...'); }
```

Yani kopya **bir kez kurulup bir daha hiç tazelenmiyordu.** 12
Ağustos'ta kurulan dizin, 13 Ağustos'ta doktora şartı kaldırıldıktan
sonra da eski `ortak.php`'yi taşımaya devam etti.

Sonucu şu oldu:

* `yazarlik-metin` alt süreçte **eski cümleleri** üretti, o cümleleri
  sayfalarda aradı, bulamadı ve doğru çalışan beş sayfayı kusurlu
  bildirdi (9 KALDI).
* `yazarlik-kapi` ise **44/0 vererek yeşil göründü.** Yeşildi çünkü
  dünkü kodu ölçüyordu. Kopya tazelenir tazelenmez 39/5'e düştü.

İkincisi birincisinden çok daha tehlikelidir: kırmızı bir kapı bakılır,
yeşil bir kapı bakılmaz.

Kopya artık her koşuda siliniyor ve yeniden kuruluyor; dizinler de
ayrıldı (`/tmp/ky-metin`, `/tmp/ky-kapi`), çünkü iki kapı bir dizini
paylaşırsa hangisinin yazdığı belirsizleşir.

*Bir kapının verdiği yeşil, ölçtüğü kodun bugünkü kod olmasına
bağlıdır. Ölçtüğü kodu önbelleğe alan kapı, er ya da geç dünkü kodu
ölçer.*

## 83. Kapı da yürürlükten kalkmış bir kuralı ölçebilir

Doktora şartı kalkınca `yazarlik-metin` ve `yazarlik-kapi`, hâlâ
"kuruluş dönemi içinde ve dışında cümle farklı olmalı" diye ölçüyordu.
Oysa kuruluş dönemi bir **gevşetme**dir: aranan bir koşulu geçici
olarak askıya alır. Askıya alınacak koşul kalkınca dönemin cümleye
etkisi de kalkar — `tg_yazarlik_kosulu_metni()` doktora dalını en başta
döndürür, dönem dalına hiç varmaz.

Kapılar ikiye ayrıldı: şart açıksa eski ölçümler, kapalıysa tersi
(cümle dönemden bağımsız, içinde hiç tarih yok, kenar notu susmuyor).
Eski dal silinmedi ve `ayar.php`'de bayrak açılıp iki kapı da yeniden
koşturularak **iki dünyada da yeşil** olduğu doğrulandı.

*Bir kapının, yürürlükten kalkmış bir kuralı ölçmeyi sürdürmesi,
sistemin uygulamadığı bir kuralı duyurmasıyla aynı kusurdur — yalnız
aynası.*

## 84. Kaldırılan cümle silinmez, koşula bağlanır — kapı bunu bilmeli

`hakemlik.php` ve `ilkeler.php`, "Hakemlik yapmadan yazar olunamaz" ve
"kendi çalışmanızı yayımlayamazsınız" diyordu. Kural kalkmıştı; cümle
duruyordu.

Doğru düzeltme cümleyi **silmek değil**, `tg_yazarlik_hakemlik_sarti()`
bloğuna almaktır: kurul kararı geri dönerse metnin de dönmesi gerekir
ve dönmesi için yazılı durması gerekir. Ama kapı bunu bilmezse doğru
davranışı kusur sayar ve insanı silmeye zorlar.

`kopya_ara()` artık iki şey yapıyor:

1. Yorumları PHP ayrıştırıcısıyla ayıklıyor — ilk yazımda yalnız
   "satır `*` ile mi başlıyor" diye bakıyordu ve bu kod tabanındaki
   girintili düz metin yorumların **ikinci ve sonraki satırlarını**
   kusur saydı. *Yoruma göze bakarak değil ayrıştırıcıyla karar
   verilir.*
2. `if (tg_yazarlik_..._sarti()):` ... `endif` aralıklarını çıkarıp o
   aralıktaki satırları muaf tutuyor.

Ölçülen şey artık "cümle kaynakta var mı" değil, **"cümle koşulsuz
basılıyor mu"**.

## 85. Panel uzunluğu bir yerleşim kusuru değil, bir yaklaşım kusuruydu

"Panel hala uzun uzun" diye bildirildi. Ölçüldü (1440px, on iki
çalışmalık sınama verisi):

```
Editör  sekmesi  5385px   Çalışmalar ve hakem atama tek başına 4303
Hesabım sekmesi  3368px   Profiliniz            tek başına 2348
Özet    sekmesi  1370px   Bulunduğunuz basamak  tek başına  655
```

Yani üç sekmenin toplamının **yüzde yetmişi üç kartta** duruyordu.

Sebep, kartların yanlış yerde durması değildi: her kart bütün ayrıntısını
**aynı anda** açık tutuyordu. Hakem bir çalışmaya atanır; on iki
çalışmanın atama düzeneğinin birden açık olması, on birinin boşuna açık
olmasıdır.

Panelde üç ayrı cins içerik var ve üçü ayrı zamanlarda okunur:

| cins | ne zaman | nerede durmalı |
|---|---|---|
| **iş** | şimdi | hep açık |
| **durum** | bir bakışta | kapağın üstünde |
| **açıklama** | bir kez | kapağın altında |

Sonuç:

```
Editör   5385 -> 1979   (%63)
Hesabım  3368 -> 1504   (%55)
Özet     1370 ->  779   (%43)
çalışma satırı  330 -> 86
```

İki kural, kapağı bir gizleme aracı olmaktan çıkarıp bir sıralama aracı
yapıyor:

1. **Kapağın üstünde kartın tek günlük bilgisi durur** — belgenin
   durumu, kaç rapor ölçüldü, hangi basamaktasınız. Boş bir kapak
   bilgiyi sıraya koymaz, saklar.
2. **Karar bekleyen bir insan kapağın arkasında kalmaz** — gönüllü ya da
   öneri bekleyen çalışma kendiliğinden açık gelir.

Ölçen kapı: **`sinama/panel-boy-kapi.js`** (32/0). Sekme başına tavan
yazılıdır; asıl koruma ise **satır başına** tavandır, çünkü sekme boyu
sınama verisindeki çalışma sayısına bağlıdır ve veri değişince değişir,
satır boyu ise yalnız tasarım değişince.

*Uzunluk, eklenen her kartla sessizce geri gelir. Kimse bir kart
eklerken "sekme kaç piksel oldu" diye bakmaz — tavan yazılı olmalıdır.*

## 86. Kapağa alınan kartın alanları forma dâhil kalmalı

Profil kartı üç gruba ayrılırken tehlike şuydu: kapalı bir gruptaki
`<textarea>`, kaydet düğmesine basıldığında okunmazsa **sessiz veri
kaybı** olurdu — kullanıcı yazdığını sanır, sistem kaydetmez.

`<details>` alanları devre dışı bırakmaz; betik `getElementById` ile
okumayı sürdürür. Ama bu, bilinerek değil şansa bağlı doğru olmasın diye
kapıya bir ölçüm kondu: kapalı gruptaki bir alana değer yazılır, aynı
alan betikten okunur, sonra eski değer geri konur.

*Bir kolaylık, veri kaybına dönüşebiliyorsa artık kolaylık değildir;
ölçülmesi gerekir.*

## 87. Ölçüm hesabı yetkiyi taşımıyordu; kapı en uzun sekmeyi hiç görmedi

`panel-boy-kapi.js` ilk yazımında ölçüm hesabını şöyle kuruyordu:

```php
'eposta' => 'olcum-bas@example.org',
'roller' => ['editor', 'bas_editor'],
```

Kapı **32/0** verdi. Yeşildi ve yanlıştı: `hs_roller()` `bas_editor`
rolünü hesap dosyasından **siler** ve yalnız adres `ayar.php`'deki kurul
listesiyle eşleşiyorsa geri koyar (kurucu sıfatı sonradan verilemez —
bu doğru davranıştır). Yönetim sekmesi sunucuda hiç basılmadı; kapı
"görünen sekmeler" üzerinde döndüğü için onu **atladı ve atladığını
söylemedi.**

Adres kurucununkiyle değiştirilince görülen:

```
Editör sekmesi   79.723px      Yazar başvuruları 77.629px
Yönetim sekmesi   2.257px      Deneme düzeni kartı y=2752'de
```

Yani baş editörün paneli **yetmiş dokuz bin piksel** uzunluğundaydı ve
bunu kimse ölçmemişti, çünkü o kartlar yalnız baş editöre basılıyor.
Sebep: başvuru listesi ve hakemlik süreçleri listesi **sınırsızdı**.
Çalışma listesinde aynı kural yıllar önce konmuştu (12 kayıt, aramada
40); bu iki listede konmamıştı.

Düzeltmeden sonra: **4032px**.

Kapıya artık `ZORUNLU` dizisi kondu: bir sekme yetki yüzünden hiç
basılmazsa kapı sessizce atlamıyor, KALDI diyor.

*Bir kapının ölçüm hesabı, ölçtüğü sayfanın en yetkili hâlini
görebilmelidir. Göremiyorsa kapı yeşil verir ve gördüğü kadarını ölçtüğünü
söylemez.*

## 88. "İstisna" sanılan şey kural çıkınca, istisnaya göre kurulmuş davranış kusur olur

Çalışma listesinde kural şuydu: karar bekleyen bir gönüllü varsa kapak
**kendiliğinden açılır**. Doğrudur — gönüllü başvurusu ayda birkaç kez
olur, bekleyen bir insanı tıklamanın arkasına koymak onu geciktirmektir.

Aynı kuralı başvuru listesine taşıdım: `durum === 'bekliyor'` olan açık
gelsin. Ölçüm: kart **4458 piksel**. Çünkü orada "bekliyor" bir istisna
değil, her yeni başvurunun **ilk hâli**. On iki bekleyen başvurunun
hepsi açıldı.

Kural düzeltildi: **yalnız ilk bekleyen açılır**. Başvurular teker teker
karara bağlanır. Kart 4458 → 1533.

*Bir davranışı başka bir yerden kopyalarken, oradaki gerekçenin burada da
geçerli olup olmadığına bakılır. Gerekçe taşınmıyorsa davranış da
taşınmamalıdır.*

## 89. Bulunamayan düğme, olmayan düğmedir

"deneme yapamıyorum ben" diye bildirildi; ekran görüntüsünde adres
çubuğuna `kutadgu.net/yonetim/deneme-kur` yazılmış ve 404 alınmış.

O bir **uçtur**, sayfa değil: `POST /api/yonetim/deneme-kur`. 404 doğru
cevaptır. Ama bir insanın adres uydurmaya kalkması, aradığı düğmeyi
bulamadığının kanıtıdır.

Düğme vardı ve çalışıyordu — Yönetim sekmesinin en altında, sayfanın
**2752 piksel** aşağısında, kapalı bir kapağın arkasında. Üstünde iki dev
kart duruyordu (Hesap daveti 832px, Okuma raporları 1011px).

Araçların altta durması bilerek konmuş bir kuraldır (*bakılan bir kart
ile ÇAĞRILAN bir araç aynı yerde duramaz*) ve o kural değişmedi. Değişen
şey şu: **soru nerede doğuyorsa cevap da orada durur.** "Hakem atamayı
nasıl denerim" sorusu "Çalışmalar ve hakem atama" kartında doğuyor;
bağlantı oraya kondu ve basıldığında sekmeyi açıp kapağı açıp karta
kaydırıyor, sonra kısa bir vurgu bırakıyor.

*Bir aracı gizleyen şey, o aracın kendisi değil üstündeki kalabalıktır.*

## 90. Ölçülemeyen bir kural, kural değil bir görüntüdür

Yazardan benzerlik (intihal) raporu isteniyordu: yüzde on beşlik eşik,
tek kaynaktan yüzde beş, rapor bağlantısı zorunlu. Denetim uçta gerçekten
vardı ve gerçekten reddediyordu.

Ama ölçüm şuydu: **sistem o oranı ölçmüyordu.** Yazar bir sayı yazıyor,
sistem o sayıyı denetlemeden kayda geçiriyor ve sayfada gösteriyordu.
`acikliklar.php` bunu zaten "bilinen açık" diye yazmıştı — yani sistem
kendi kusurunu biliyor, yazıyor ve kuralı uygulamayı sürdürüyordu.

Kural 13 Ağustos 2026 kurul kararıyla kaldırıldı. Üç gerekçe
`ayar.php`'de yazılı: ölçülemiyordu, paralı olduğu için kapıyı paraya
bağlıyordu, ve asıl denetim zaten açıklıkta (metin herkese açık, hakem
adıyla imzalıyor).

**Bir kural kaldırılırken üç yerin birden değişmesi gerekir: ayar, uç ve
cümle.** Biri unutulursa sistem yalan söyler. Değişenler:

```
ayar.php        benzerlik_raporu_sarti => false + gerekçe
ortak.php       tg_benzerlik_sarti / _metni / _kisa  (tek kaynak)
api/index.php   beş denetim koşula bağlandı
basvuru.php     adım isteğe bağlı, koşul kutusu değişti, betik denetimi
ilkeler.php     §06 tek kaynaktan basıyor, ölçülmeyen eşik kutusu gitti
index.php · nasil-isler.php · acikliklar.php
```

`acikliklar.php` kaydı **silinmedi**, "kapalı"ya çevrildi ve nasıl
kapandığı yazıldı: *ölçüm eklenerek değil, ölçülemeyen kural kaldırılarak.*
İkisi karıştırılırsa sistem, yapmadığı bir işi yapmış gibi görünür.

Ölçen kapı: **`sinama/benzerlik-kapi.php`** (şart kapalıyken 34/0, açıkken
28/0). Kapı yalnız cümleyi değil **ucu** da ölçüyor: raporsuz bir başvuru
gerçekten geçiyor mu, %40 benzerlik bildiren geçiyor mu, tutarsız veri
hâlâ reddediliyor mu.

## 91. Üç kez üst üste: ölçümün kendisi kusurluydu

`benzerlik-kapi.php` ilk koşusunda 4 KALDI verdi. Üçü **kapının kendi
kusuruydu**:

1. Gövdede alan adı `yz_etik` yazılıydı; doğrusu `yz_etik_kabul`. Uç
   doğru davranarak reddetti, kapı "benzerlik kuralı kalkmamış" diye
   okudu.
2. ORCID numaralarını sırayla ürettim (`...-009` + sayı). ORCID'in son
   basamağı bir **denetim basamağıdır**; uydurulmuş numara reddedilir ve
   reddedilmesi doğrudur.
3. Desen `/similarity report is required/i` idi. Doğru cümle — **"No
   similarity report is required"** — bu deseni içerir. Kapı, kuralın
   kaldırıldığını söyleyen cümleyi kuralın kendisi sandı ve doğru sayfayı
   kusurlu bildirdi. Desen artık başındaki olumsuzlamayı dışarıda
   bırakıyor.

Dördüncüsü gerçekti ve düzeltildi.

*Bir kapı ilk koşusunda kırmızı verdiğinde, kusur ölçülende değil ölçende
olma olasılığı en yüksektir — hele ki kapı yeni yazılmışsa.*

## 92. `$en` her sayfada tanımlı değil

`basvuru.php`'ye `tg_benzerlik_metni($en)` yazdım. O dosyada `$en` diye
bir değişken **yok** (`nasil-isler.php`'de var). PHP 8'de `null` bir
`bool` parametreye geçince ölümcül hata verir ve **sayfa yarıda kesilir**.

Sayfa 200 dönüyordu, 40 KB gövde basıyordu, gözle bakınca çalışıyor
görünüyordu — ama `</html>` ile bitmiyordu. Altı kapı birden kırmızıya
döndü:

```
sihirbaz  72/0 -> 48/24     telif  38/0 -> 32/6
dil       45/0 -> 40/5      ceviri 48/0 -> 47/1
tamga     58/0 -> 57/1      dogrula 92/0 -> 91/1
```

Kırılan tek şey vardı; yirmi dört ölçüm onu ayrı ayrı gördü. Düzeltme:
`k_en()`.

*"Gövde `</html>` ile bitiyor" ölçümü süs değildir: yarıda kesilen bir
sayfa, tarayıcıda çoğu zaman düzgün görünür.*

## 93. Davet, neye davet olduğunu söylemiyordu

Kural: kurucu baş editörler 2027 sonuna kadar yeni **baş editör, editör
ve hakem** ekleyebilir; kendileri gibi **kurucu** ekleyemez.

Ölçülen durum: davet ucu hesabı `'roller' => []` ile açıyordu. Davet
hiçbir rol vermiyor, rol sonradan başka bir yerden veriliyordu — "kimi
davet ediyorum" sorusunun cevabı davet anında hiçbir yerde yazmıyordu.

`hs_davet_rolleri()` tek kaynak oldu. Dört kural buradan çıkıyor:

* **Kapalı seçenek gizlenmez, sebebiyle gösterilir.** Olmayan bir
  seçenek neden olmadığını söylemez; kişi kendinde kusur arar. Görünüp
  basılınca 403 dönen bir düğme de aynı kusurun tersidir.
* **Hakemliğe davet `hakem` değil `aday_hakem` verir.** Doktora belgesi
  denetimi sistemin tek nitelik denetimidir; bir düğmeye indirgenemez.
* **Baş editörlükte davet tek başına yetmez.** Rol her okumada
  `ayar.php`'deki kurul kaydından çözülür ve hesaba yazılsa bile
  sayılmaz — *yazılan bir yetki geri alınamaz hâle gelirdi.* Panel bunu
  gizlemiyor, söylüyor.
* **Kurucu hiçbir koşulda açılmaz.** Kurucu bir yetki değil bir
  kayıttır; 2028'de kapanan şey görev değil iki dar yetkidir.

Uç istemciye güvenmiyor: aynı işleve yeniden soruyor.

Ölçen kapı: **`sinama/davet-kapi.php`** (54/0).

## 94. Uçlar sayfanın dilini bilmez

Türkçe panelde kurucu davetinin reddi **İngilizce** döndü: "Founder
status can never be granted afterwards."

`k_dil()` sırayla `?lang=`, çerez, ülke ve `Accept-Language`'a bakar;
hiçbiri yoksa varsayılana — İngilizceye — düşer. Panelin `api()` işlevi
hiçbir isteğe dil eklemiyordu. Uçlardaki elle yazılmış Türkçe hata
iletileri bunu gizlemişti; kusur ancak `tg_c()` ile üretilen bir metin
uçtan dönünce göründü.

Dil artık **`api()` içinde her isteğe** ekleniyor. Çağrı başına
eklenseydi bir gün biri unutulur ve o uç sessizce İngilizce konuşurdu.

## 95. Kapı kendi bıraktığı çöple başka bir kapıyı kırdı

`davet-kapi.php` sınama verisine gerçek hesap yazıyor — yazmadan davet
ucunu ölçemez. Ama **temizlemiyordu.** Sekiz koşudan sonra panelin
"Hesap daveti" kartındaki editör listesi 980 piksele çıktı, Yönetim
sekmesi 2029 piksel oldu ve `panel-boy-kapi` tavanı aştı. Panelde
hiçbir şey değişmemişti; büyüyen şey benim bıraktığım çöptü.

Sonra ikinci çarpışma: temizlik eklendiğinde bu kapı `dvolcum` adında
**ikinci bir hesap** kuruyordu ve e-postası `panel-boy`'unkiyle aynıydı
(kurucu adresi; başkası olamaz). `hs_kaydet()` kayıtları e-postaya göre
yazar; iki kayıt birbirini yuttu ve temizlik ikisini birden götürdü.
Ertesi koşuda `panel-boy` giriş yapamadı ve "Yönetim sekmesi yok" diye
kusur bildirdi — oysa yok olan sekme değil ölçüm hesabıydı.

İki kapı artık **aynı fiziksel hesabı** paylaşıyor: kuran ikisi de aynı
satırı yazar, silen hiçbiri onu silmez.

*Bir kapı, ölçtüğü dünyayı değiştiriyorsa o değişikliği geri almalıdır;
ve başka bir kapının fikstürüne asla dokunmamalıdır.*

## 96. Çipler artık kaydırmıyor, değiştiriyor

Sekme içi çip şeridi bir **yol göstericisiydi**: basınca karta
kaydırıyordu. Kart yerinde kalıyor, sekme boyu değişmiyordu.

Çip artık hangi kartın **görüneceğini** seçiyor:

```
Özet     1370 ->  806      Hesabım  3368 -> 1255
Editör   5385 -> 2034      Yönetim  2257 ->  963
```

(Editör'ün baş editörde ölçülen gerçek boyu 79.723 pikseldi.)

Ödenen bedel: bir kart görünürken ötekiler görünmez. Bu yüzden **çipin
üstünde o kartın bekleyen sayısı durur** ve sayı, kartın kendi
rozetinden okunur — ayrı bir sayaç yazılsaydı bir gün ondan farklı bir
şey gösterirdi. Açılışta seçilen kart da bekleyen işi olan karttır.

Üç ölçüm kusuru üst üste çıktı:

1. `display:none` **uygulanmadı**: ızgara kuralları `.pn-pnl.acik`
   altında karta `display:flex` veriyor ve bunlar üç sınıflık
   seçicilerdir. Editör sekmesi 4032'den **5308'e çıktı** — kural
   yazılmıştı ama yeniliyordu. (OKUBENI 79'un aynısı.)
2. Sekme başlığı `grid-column` almadığı için **tek sütuna sıkıştı** ve
   "Editör" sözcüğü iki satıra bölündü. Izgaranın her doğrudan çocuğu
   bir hücredir.
3. Tek görünen kart yarım sütunda kaldı: `:only-child` kuralı işe
   yaramıyor, çünkü kardeşler kaldırılmıyor yalnız gizleniyor.

Ve kapının kendisi bir kez boşlukta yeşil verdi: çip geldikten sonra
kart seçilmeden ölçülen "kapalı satır boyu" **0 piksel** çıktı ve deneme
sıfırla geçti. *Bir ölçüm sıfır dönüyorsa önce ölçüleni görüp
görmediğine bakılır.*

## 97. Yazı tipinin yarısı önceden yükleniyordu

"Yazı tipleri sapıtmış, g ve b farklı farklı" diye bildirildi. Ölçüldü ve
doğruydu; ama sebep yazı tipi dosyalarında değildi — üçü de tek bir aile
(Caladea) ve tutarlı. Sebep **ne zaman indikleriydi**:

```
/               indirilen: 700            400:unloaded
/yazilar.php    indirilen: 700, 400       400 -> 60-89 ms'de BAŞLIYOR
/ilkeler.php    ...                       700 -> 30-57 ms'de BİTİYOR
```

Sekiz sayfanın yedisi düz (400) yüzü istiyor, ama `kabuk.php` yalnız
kalın (700) yüzü önden yüklüyordu. Kalın başlıklar Caladea ile anında
çiziliyor, düz metinler ise ikinci gidiş geliş bitene kadar yedek serifle
(Georgia/Palatino) duruyordu. `font-display:swap` gereği metin hiçbir an
görünmez kalmıyor — ama bir süre **ekranda iki ayrı yüz** bulunuyordu.
'g' ile 'b', Caladea ile Georgia'nın en çok ayrıştığı iki harftir.

Düzeltmeden sonra ikisi de 20-28 ms'de başlıyor, 39-59 ms'de bitiyor.

Ölçen kapı: **`sinama/yazi-tipi-kapi.js`** (7/0). Ölçüt "önden yükleme
etiketi var mı" değil, **iki yüzün başlangıç zamanı arasındaki fark**:
etiket yazılıp yanlış yola bakıyorsa kapı yine yeşil verirdi.

*Bir yazı tipinin tutarlılığı dosyalarında değil, hepsinin aynı anda
gelmesindedir.*

## 98. İki kapı birbirinin tersini söylüyordu; biri yeşil olduğu için kimse bakmadı

`diyalog-kapi` uzun süredir kırmızıydı:

> KALDI  KUCUK REVIZYONDA KANAL ACIK KALIYOR

`hakem-akis` ise aynı davranışı **yeşil** ölçüyordu:

> GECTI  ve degerlendirme kapandi

İkisi de tek bir alana (`kapali`) bakıyordu. Kaynak kodda da iki ayrı
kural yazılıydı:

```php
// api/index.php
if (in_array($karar, ['kabul','kucuk','ret'], true)) $hakem['kapali'] = true;

// ortak.php — kanalın açık olup olmadığına karar veren TEK KAYNAK
if ($karar !== 'buyuk' && $karar !== 'kucuk') return '';   // kucuk AÇIK
```

Yani sayfa yazara "hakeme not yazabilirsiniz" diyor, uç 409 ile "diyalog
turlarınız doldu" diyordu. Beş kez yakalanmış kusurun aynısı: **sistem
uygulamadığı bir kuralı duyuruyor.**

Karar kuralın kendisinden okundu: küçük revizyon bir SON değil, yazara
yöneltilmiş bir istektir; yazarın yanıt veremediği bir revizyon isteği
istek değil hükümdür. Süreci bitiren iki karar kaldı: kabul ve ret.

Ara yolda bir de yanlış çözüm denendi: `degerlendirme_bitti` diye ikinci
bir alan. Ölçüm onu da eledi — büyük revizyondan sonra hakem geri gelip
kararını kesinleştirebiliyor ve bu doğru; küçük revizyonu ayrı tutmak
"az düzeltme isteyen hakem geri dönemez, çok düzeltme isteyen döner"
demek olurdu.

*Aynı şeyi ölçen iki kapı varsa ve biri kırmızıysa, öteki yeşil olduğu
için değil ölçtüğü için doğrudur — hangisinin doğru olduğuna kural karar
verir, renk değil.*

## 99. Döküm, sayfanın gösterdiğinden az şey taşıyordu

Yazar ile hakem arasındaki yazışma çalışmanın sayfasında ve herkese açık
listede görünüyor, ama **kalıcı arşiv dökümüne hiç girmiyordu**. Yani
okurun bugün gördüğü bir kayıt, yarının arşivinde bulunmayacaktı.

`dk_hakem()` artık `tg_diyalog()`'dan geçirerek yazışmayı da alıyor;
anahtar, şifre ve e-posta buraya da girmiyor.

*Açık hakemliğin anlamı, değerlendirmenin kendisinin de denetlenebilir
bir belge olmasıdır. O belgenin yarısını dışarıda bırakan döküm, kalıcı
kayıt değildir.*

## 100. Üretim yapılandırmasında fikstür arayan kapı

`sinama/kapi.php` 43/16 ile kırmızıydı. Ölçtüğü şeyler doğruydu — kurucu
sıfatının sonradan verilemediği, süresi dolanın onursala geçtiği — ama
bunları ölçerken **üretimdeki `ayar.php`'yi** okuyor ve orada "Çiğdem
Öztürk", "Suresiz Kayit" gibi **sınama satırlarının** durmasını
bekliyordu.

O satırlar kaldırılmıştı ve kaldırılmaları doğruydu: üretim ayar dosyası
uydurma kurul üyesi taşıyamaz. Kaldırıldıkları gün kapı kırmızıya döndü
ve öyle kaldı — kusuru sistemde değil kendi yönteminde olduğu hâlde.

Kapı artık senaryosunu kendisi kuruyor (`/tmp/kkurul`), ve üç ölçümü de
yürürlükteki kurala göre yeniden yazıldı: **64/0**.

Bu arada iki soru çıktı ve **karara bağlanmadı**, çünkü ikisi de kurul
kararıdır: atanan baş editöre yazılı süre zorunlu mu, ve yeni atamada oyu
yalnız kurucular mı verir? İkisi de `KUTADGU-KURUL-GERI-BILDIRIM.md` F
bölümüne yazıldı.

*Bir kapı, ölçtüğü senaryoyu kendisi kurar. Üretim yapılandırmasında
fikstür arayan kapı, o fikstür temizlendiği gün yalancı bir kusur
bildirir; yalancı kusur bildiren kapıya da bir süre sonra kimse bakmaz.*

---

## 101. Süzgeç, hakem eşleştirmesinin sorusunu soruyordu

`ar_suz()` ve `yazilar.php` alan süzgecinde `al_yakinlik(...) >= 2`
arıyordu. `al_yakinlik()` "iki kod birbirine ne kadar yakın" sorusunu
yanıtlar ve **hakem eşleştirmesi için** yazılmıştır. Süzgecin sorusu ise
"bu çalışma seçilen alanın ALTINDA mı"dır. İkisi karışınca süzgeç aynı
anda hem **dar** hem **gevşek** oldu:

```
al_yakinlik('5',       '5.2.001') = 1   -> "Sosyal bilimler" seçen 0 sonuç
al_yakinlik('5.2.001', '5.2.007') = 2   -> "Makro iktisat" seçen "Mikro"yu da alıyor
```

Yani otuz çalışma varken sıfır görünüyordu. Bildirilen cümle —"alanlar
işlevsiz duruyor"— tam olarak buydu. Doğru soru **kapsamadır** ve tek
yönlüdür: `al_kapsar()`. Kardeş kardeşi kapsamaz.

*Bir işlev doğru çalışıyor olabilir ve yine de yanlış yerde durabilir.
Bir süzgeç kırıldığında önce "hangi soruyu soruyor" diye bakın.*

## 102. Açıklama satırı, kodun yaptığını değil yapılmasını istediğimi anlatıyordu

`yazilar.php`'nin seçici açıklaması "yedi temel alan, kırk iki bilim
alanı, yüz seksen dokuz bilim dalı listelenir" **diyordu**. Ölçüldü:
temel alan hiç basılmıyordu; yedi ad yalnızca `<optgroup>` **etiketi**
olarak geçiyordu. Ana sayfadaki satır `/yazilar.php?alan=5` adresine
gidiyor, sayfa otuz çalışmayı doğru süzüyor, ama seçicide seçili
görünen satır olmadığı için okur "süzgeç çalışmadı" diye okuyordu.

Bu, bu depoda **dokuzuncu** kez yakalanan aynı kusur: *sistem,
uygulamadığı bir kuralı duyuruyor.* Bu kez duyuru bir sayfada değil bir
YORUM satırındaydı; yorum da bir duyurudur ve yalan söyleyebilir.

Seçici artık tek yerden basılıyor (`al_secenek_html()`): 7 + 42 + 189.

## 103. Dizi toplama, hata metnini sessizce yuttu

```php
return $bos + ['hata' => 'Atama tarihi yazılmamış...'];
```

`$bos` zaten `'hata' => ''` taşıyordu. PHP'de `+` **var olan anahtarı
korur**; yazdığım metin hiç geçmedi ve kapı "hata bildirilmiyor" dedi.
Kapı haklıydı, kod sessizdi.

*İki dizi toplarken hangisinin kazandığını bilmiyorsanız, açık atama
yazın.*

## 104. Bir alanın eksikliği, kişiyi listeden silmemeli

Etkinlik ölçütü için `atama_tarih` gerekiyordu. İlk yazımda eksik tarih
**hata** döndürüyordu — ve `tg_gorev_kaydi_suz()` içinde hata, kaydı
**düşürür**. Sonuç: atama tarihi yazılmamış her baş editör kuruldan
siliniyordu. `kapi.php` bunu on yedi kusurla bildirdi.

Doğru ayrım şu: **hata** kaydı düşürür, **uyarı** düşürmez ama görünür.
Kişi görevde kalır, ölçüm yapılmaz, eksiklik kurul sayfasında ayrı bir
kümede yazar. Eksik bir alan yüzünden bir kişiyi kuruldan silmek, o alanı
istemekten çok daha ağır bir şeydir.

*Bir doğrulama kuralı yazarken sorun: bu eksiklik kaydı geçersiz mi
kılıyor, yoksa yalnızca eksik mi bırakıyor?*

## 105. Yalnız cezayı ölçen kapı, herkesi cezalandıran kusuru geçirir

`gorev-suresi-kapi.php`'nin ilk taslağı yalnızca "ölçütü karşılamayanın
görevi bitiyor mu" diye soruyordu. O kapı, **herkesin** görevini bitiren
bir kusuru yeşil geçirirdi. Kapı şimdi dört hâli birden ölçüyor: iş vardı
ve yapmadı (biter), iş vardı ve yaptı (sürer), hiç iş yoktu (sürer), az
iş vardı ve payını yaptı (sürer).

Bu arada bir ölçümüm de yanlış çıktı: "az iş vardı, hedef indi, görev
sürer" yazmıştım. Hedef ikiye inmişti ve kişi ikisinden hiçbirini
yapmamıştı. **Az iş olması, hiç iş yapmamayı aklamaz.**

*Bir kuralın kapısı, kuralın işlediği hâli de işlemediği hâli de
ölçmelidir. Tek yönlü kapı, yarısını görmez.*

## 106. Çeviri anahtarı İNGİLİZCE dizedir

`al_secici_etiket()`'i `$en ? 'Field...' : 'Bilim alanı...'` diye
yazdım. İki kapı birden kırmızıya döndü ve ikisi de haklıydı: (1) üçüncü
bir dilde Türkçe metin sızıyordu, çünkü ikili seçim sözlük katmanını hiç
görmüyor; (2) "ikili dil seçimi artmıyor" sayacı 24'ten 27'ye çıkmıştı.
Çözüm `tg_c()`'den geçirmek.

Bunun bir yan bilgisi de var ve daha önce yanlış hatırlıyordum: sözlük
anahtarı **İngilizce** dizedir (`ceviri-cikar.php`). Yani Türkçe metni
değiştirmek çevirileri düşürmez; İngilizceyi değiştirmek düşürür.

## 107. Kapı, kendi yorumunu kusur saydı (ikinci kez)

`ceviri-kapi.php`'nin ikili-seçim sayacı **ham dosyayı** okuyordu ve
`k/alanlar.php`'de "ikili seçim neden kaldırıldı" diye yazdığım YORUM
satırını da saydı: 24 → 25, ve kapı doğru bir düzeltmeyi gerileme diye
bildirdi. Aynı hata daha önce `kopya_ara()`'da çıkmıştı.

*Kaynakta desen arayan her ölçüm `token_get_all()` ile yorumları
atmalıdır. Gerekçe yazmak yasaklanamaz.*

## 108. Kapının atladığı ölçüm, kırmızı ölçümden sinsidir

Bu oturumda üç kez oldu:

* `alan-arama-kapi` `tg_yazilar()` çağırıyordu — öyle bir işlev yok.
  Dönen boş diziydi ve kapı bütün davranış ölçümlerini "arşivde çalışma
  yok" diyerek **atladı**, sonra yeşil verdi. Doğrusu `k_yazilar()`.
* Aynı kapı "en dolu dal"ı seçiyordu; o dalın boş kardeşi olmadığı için
  **kardeş sızması** ölçümü atlanıyordu — oysa bütün düzeltmenin sebebi
  o sızmaydı. Artık önce boş kardeşi olan dolu dal aranıyor.
* Aradisipliner kapsama ölçümü "böyle bir dal yok" diye atlanıyordu.
  O dallar veri dizininden gelir; üretimde bugün yok diye kural
  ölçülmeden kalırsa, ilk öneri onaylandığı gün kimse bakmayacak. Kapı
  artık kendi düzeneğini kuruyor ve bitince siliyor.

*Bir kapının çıktısında "atlandı" görüyorsanız, o satır yeşil değildir.*

## 109. PCRE'nin `{n,m}` sınırı 65535'tir

`preg_match('#<select[^>]*id="alanSec".{0,80000}?...#')` **derlenmedi**;
uyarı bastı ve `false` döndü — yani kapı "Türkçe ad kalmamış" diye
GEÇTİ verdi. Desen derlenmediğinde `preg_match` false döner ve `!false`
doğrudur: derlenmeyen bir desen, sessizce her ölçümü geçiren bir
ölçümdür. Uzun bir gövdede arama yapacaksanız önce kesin, sonra arayın.

---

## 110. Bir öznitelik adı, o adı kimin sahiplendiğine bakmadan seçilemez

Gönderim formundaki dil sekmelerine `data-dil="ana"` / `data-dil="kunye"`
yazdım. Ölçüm: sekmeye basınca **sayfa Kürtçeye dönüyordu.**

Sebep `k/kutadgu.js`'de: `[data-dil]` taşıyan **her öge** bir SİTE DİLİ
değiştiricisidir ve değeri dil kodu sayılır. `"kunye"` dizesinin ilk iki
harfi `ku`'dur.

Bulmak yarım saat aldı, çünkü belirti sebebi gizliyordu: tıklamadan sonra
`<select>` değeri `tr`'den `en`'e dönüyordu ve ben "kim bu değeri
yazıyor" diye setter'a tuzak kurdum — hiçbir şey yazmıyordu. Sayfa
yeniden yükleniyordu, o kadar. `framenavigated` dinleyicisi bir satırda
söyledi: `?lang=ku`.

*Bir davranış "imkânsız" görünüyorsa, ölçtüğünüz sayfa artık o sayfa
olmayabilir. Önce navigasyonu ölçün.*

## 111. Onay kutusu kullanıcıyı durduruyor, kuralı hiç korumuyordu

"Yapay zekâ kullanılmadı" diyen yazar formu ilerletemiyordu: etik onay
kutusu zorunluydu. Kutunun cümlesi ise şuydu: "yapay zekâ
**KULLANIMININ** ilkeler çerçevesinde kaldığını beyan ederim."
Kullanmadığını söyleyen birinden, olmamış bir kullanımın niteliği
isteniyordu.

İkinci katman daha kötüydü: sayfa `yz_etik_kabul` alanını kutuya
**bakmadan** her zaman `true` gönderiyordu ve kayda da sabit `true`
yazılıyordu. Yani kutu kimseyi korumuyor, yalnızca yoruyordu — ve
yayımlanan kayıtta olmamış bir onay duruyordu.

*Bir zorunlu alan eklerken iki soru: (1) bu soru, bu durumdaki kişiye
sorulabilir mi? (2) yanıtı gerçekten okunuyor mu?*

## 112. `k_cd` ile `tg_cd` aynı imzada değil

`tg_cd($tr, $en, $enBayrak, ...$deger)` ama `k_cd($tr, $en, ...$deger)`.
`k_cd(..., null, $ad)` yazınca `null` birinci **değer** yuvasını yedi ve
ekranda `Başlık ()` çıktı. Sessiz bir hata: sayfa 200 döndü, kapı
yeşildi, yalnızca parantez boştu.

*Aynı işi yapan iki işlevin imzası farklıysa, çağırmadan önce bakın.*

## 113. Dil adı sıfat değildir

`'%1 başlık'` deseni ile ikinci dilin adını başa koyunca Türkçede
**"English başlık"** çıkıyordu. Dil adları `calisma_dilleri` listesinde
KENDİ dillerinde yazılıdır ('English', 'Deutsch') ve Türkçe sıfat hâlleri
o listede yoktur — üretmeye çalışmak `destek-ad`'daki çekim tuzağının
aynısıdır.

Çözüm çekim değil, **yapı**: `'Başlık (%1)'`. Her dilde ve her ikinci dil
seçiminde doğru okunur.

*Bir dizeyi çekimli kullanmak zorunda kalıyorsanız, cümleyi değiştirin.*

## 114. Araç vardı, cümle yoktu

"9. adımda şekiller diyoruz ama kişi tabloyu nasıl ekleyecek?" Ölçtüm:
yazma ekranında **doksan araç düğmeli** tam bir düzenleyici var — tablo,
görsel, formül, geri alma. Eksik olan araç değil, o aracın var olduğunu
söyleyen cümleydi. Başvuru formundaki adım yalnızca bir bağlantı istiyor
ve nereye ekleneceğini hiç söylemiyordu.

Aynı sınıf: panelde "Çalışmayı düzenle" yazan bağ, tam metnin **yazıldığı**
yere gidiyordu ama adı bunu söylemiyordu.

*Kullanılamayan bir özellik ile var olmayan bir özellik, kullanıcı için
aynı şeydir. Kusur her zaman kodda değildir.*
