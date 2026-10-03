# Katkı

## Bu depo nedir

Bu depo, **herkesin kendi sunucusunda kurabileceği açık kaynaklı bir
yayın yazılımının** kaynağıdır. Örnek kurulum kutadgu.net üzerinde
çalışır ve `main` dalı oradaki kodla aynıdır; ama yazılım tek bir siteye
ait değildir: bir üniversite, kütüphane, dernek ya da dergi aynı kodu
kendi adıyla ve kendi kuruluyla çalıştırabilir.

Bugünkü sınırlar da açıkça yazılmalı:

- Sürümler `CHANGELOG.md` içinde kayıtlıdır; 1.0.0 Zenodo'da DOI ile
  arşivlidir. Kurulum paketi ve sürümler arası geçiş aracı henüz
  yoktur, yol haritasının ilk maddesidir (`ROADMAP.md`).
- `api/` altındaki uçlar henüz geriye dönük uyumluluk sözü verilmiş
  kamusal bir API değildir. Dışarıdan sağlam biçimde kullanılabilecek
  uçlar dizin toplayıcılarına açılanlardır: `/oai.php`, `/dokum.php`,
  `/arsiv.php`, `/sitemap.php`.
- Yazılımı bugün tek kişi sürdürüyor. İkinci bir sürdürücü, bu
  belgedeki kurallara uyarak katkı veren biri arasından çıkacaktır.

Lisans `LISANS.md` dosyasında ayrıntılı yazılıdır: yazılım
**AGPL-3.0-only**, arşivdeki çalışmalar **CC BY 4.0**. Sistemi kendi
kurumunuzda kurmak için izin istemeniz gerekmez; tek koşul, değiştirilmiş
bir sürümü ağ üzerinden hizmet olarak sunuyorsanız kaynağı da o hizmeti
kullananlara açmanızdır. "Kutadgu" adı ve işareti ise türetilmiş bir
sistemin adı olamaz; sebebi mülkiyet değil, okuyucunun bir çalışmanın
hangi kuralla yayımlandığını adına bakarak bilebilmesidir. Kendi
kurulumunuzu kendi adınızla çalıştırın.

## Yerel kurulum

**Gerekenler.** PHP 8.0 ve üstü (kodda `str_starts_with` ve
`str_ends_with` kullanılıyor); geliştirme PHP 8.4 ile yapılıyor.
Veritabanı yoktur, kurulacak bir bağımlılık yöneticisi yoktur, derleme
adımı yoktur. Zorunlu eklentiler: `mbstring`, `json`, `dom`. İsteğe
bağlı olanlar yoklukları denetlenerek kullanılır: `gd` (paylaşım
kartı), `zip` (arşiv paketi), `curl` (Telegram bildirimi ve konum),
`intl` (tarih biçimlendirme).

**Veri dizini.** Sistem verisini JSON dosyalarında tutar ve bu dosyalar
**kod dizininin dışında** durur. Sıra şudur: `ayar.php` içindeki
`veri_dizini` doluysa o kullanılır; boşsa `KUTADGU_DATA` ortam
değişkeni; o da yoksa kod dizininin bir üstündeki `kutadgu-data`
klasörü. Dizinin boş olması sorun değil, sistem gerekli dosyaları
kendisi üretir.

```
mkdir -p ~/kutadgu-data
cd /depo/dizini
KUTADGU_DATA=~/kutadgu-data php -S 127.0.0.1:8080 -t .
```

Sayfa dili varsayılan olarak **İngilizce** açılır; Türkçesi için adrese
`?lang=tr` eklenir.

**Yerleşik sunucunun bilinmesi gereken sınırı.** `php -S` `.htaccess`
kurallarını uygulamaz. Bu, yeniden yazma kurallarına bağlı adreslerin
yerelde sessizce ana sayfaya düşmesi demektir: 200 döner ama beklenen
sayfa gelmez. Yerelde karşılıkları şunlardır:

| Canlıdaki adres | Yerelde |
|---|---|
| `/tamga/KTG-2026-00001-4` | `/yazi.php?doi=KTG-2026-00001-4` |
| `/sitemap.xml` | `/sitemap.php` |
| `/robots.txt` | `/robots.php` |
| `/oai` | `/oai.php` |
| `/kisi/ad-soyad` | `/kisi.php?k=ad-soyad` |

`/api/...` yerleşik sunucuda çalışır. Buna karşılık `.htaccess`
içindeki **koruma** kuralları da yerelde uygulanmaz: `ayar.php`,
`ortak.php` ve `k/` altındaki PHP parçaları canlıda dışarıya kapalıdır,
yerelde açıktır. Yerel sunucuyu 127.0.0.1 dışına açmayın.

**Gizli anahtarlar.** Bot anahtarı, webhook gizli anahtarı ve benzeri
şeyler koda ya da `ayar.php`'ye yazılmaz; veri dizinindeki
`ruh-ayar.json` dosyasında durur ve **depoya asla girmez**. Yerelde
çalışırken bunlara ihtiyacınız yok; ilgili özellikler anahtar yoksa
kapalı gelir.

## Kod üslubu

Üslup depodaki mevcut koddan okunur; aşağıdakiler orada zaten geçerli
olan kuralların yazıya dökülmüş hâlidir.

- **Adlandırma Türkçedir.** İşlev ve değişken adları Türkçedir:
  `tg_veri_dizini()`, `tg_tamga_coz()`, `$yazilar`, `$hakem`, `$gerekce`.
  Ortak yardımcılar `tg_` önekiyle `ortak.php` içinde, kabuk işlevleri
  `k_` önekiyle `k/kabuk.php` içinde toplanır. Yeni bir yardımcı
  yazıyorsanız aynı önekleri kullanın.
- **Yorumlar Türkçedir ve NEDEN'i anlatır.** Kodun ne yaptığı zaten
  kodda yazılıdır; yoruma düşen, o kararın neden verildiğidir. Depoda
  bunun örnekleri var: bir kilidin neden iki yerde durduğu, bir ayar
  anahtarının neden bilerek değiştirilmediği, bir denetimin neden
  ölümcül hata vermemesi gerektiği. Bir satırın neden öyle olduğunu üç
  ay sonra siz de hatırlamazsınız.
- **Girinti dört boşluktur**, sekme kullanılmaz.
- **Satırlar kısadır.** Kod satırlarının çoğu 80 sütunun altındadır;
  blok yorumlar 70 sütun civarında sarılır. Uzun HTML ve dizi
  satırlarında bu esnetilir, ama okunmayacak kadar uzatılmaz.
- **Uzun tire kullanılmaz** (U+2014). Ne kodda, ne yorumda, ne de sayfa
  metinlerinde. Ayırma gerekiyorsa iki nokta, virgül ya da yeni bir
  cümle kullanılır.
- Sayfa dosyaları `declare(strict_types=1);` ile başlar.
- **Metin iki dildedir.** Sayfaya yazılan her metin `k_c('Türkçe',
  'English')` biçiminde iki dilde verilir. Tek dilli metin bırakmayın.

## `ayar.php` kuralı

Metin ve kural değişikliği **koda değil ayar dosyasına** yazılır.
`ayar.php`, sistemin tek yapılandırma dosyasıdır: alan adı, marka
adları, eşikler (`benzerlik_ust`, `kabul_gecerli`,
`serh_asgari_karakter`, `kurul_yayin`), çalışma dilleri, kurul üyeleri
ve sayfa metinlerinin çoğu oradadır. Bir sayıyı ya da bir cümleyi
değiştirmek için PHP dosyalarının içine girmeniz gerekiyorsa, büyük
olasılıkla o değerin `ayar.php`'ye taşınması gerekiyordur; doğru katkı
budur.

Bir uyarı: `ayar.php` okunur okunmaz sekiz **değiştirilemez ilkeye**
karşı süzülür (`tg_ayar_ilke_suz`). Ücret alan bir ayar düşürülür,
kapalı bir lisans açık lisansa geri çekilir, arşive onay kapısı koyan
bir ayar kapatılır, uyruk şartı silinir. Yani ilkeye aykırı bir ayar
satırı yazmak işe yaramaz; sistem onu sessizce değil, kayda geçirerek
etkisiz kılar.

## `TG_SURUM` ve `sw.js` kuralı

**CSS ya da JS değiştiyse iki yer güncellenir:**

1. `ortak.php` içindeki `TG_SURUM` sabiti artırılır. Biçim yıl, ay, gün
   ve o günkü sıradır (`'20260811e'` gibi).
2. `sw.js` içindeki önbellek adları (`SURUM` ve `KABUK`) yenilenir.

Sebebi: stil ve betik dosyaları bir yıl boyunca `immutable` olarak
önbelleğe alınıyor. Numara değişmezse ziyaretçiye yeni sayfa eski
stille gider ve düzen dağılır. Bu, unutulduğunda fark edilmesi en geç
olan hatalardandır, çünkü sizin tarayıcınızda sorun görünmez.

## Göndermeden önce

1. **Sözdizimi.** Dokunduğunuz her dosya için `php -l`. Tümü için:
   ```
   for f in *.php api/index.php k/*.php; do php -l "$f" >/dev/null || echo "HATA: $f"; done
   ```
2. **Sayfaları gerçekten açın.** En azından `/`, `/arsiv.php`,
   `/yazilar.php`, `/iletisim.php` ve dokunduğunuz sayfa; hem varsayılan
   dilde hem `?lang=tr` ile.
3. **Sunucu kütüğünde uyarı olmasın.** Yerleşik sunucu `Warning`,
   `Notice` ve `Deprecated` iletilerini kütüğe yazar. Bir sayfanın
   açılıyor olması yetmez; kütük temiz olmalıdır.
4. **CSS ya da JS değiştiyse** sert yenileme (Ctrl+F5) ile bakın ve bir
   üstteki sürüm kuralını uygulayın.
5. **Veri dizinine bakın.** Yazma yoluna dokunduysanız üretilen JSON
   dosyalarının bozulmadığını görün.

Sınamalar `sinama/` klasöründedir (82 betik); nasıl çalıştırılacakları
`sinama/OKUBENI.md` içinde yazılıdır. Klasör sunucuda dışarıya kapalıdır.
Düzeni şudur: her iş için bir betik, sonunda sayı raporlanır
(`GECTI: n KALDI: m`). Kendi sınamanızı yazarsanız aynı düzeni izleyin
ve `sinama/` altına ekleyin. Kurucuların gerçek e-posta adresleri
depoya girmez; gereken betikler onları `KUTADGU_KURUL_EPOSTA` ortam
değişkeninin gösterdiği dosyadan okur.

## Katkı nasıl gelir

- Açık depo `https://github.com/behiccetin/kutadgu.net`, ana dal: `main`.
  Konu ve değişiklik istekleri buraya açılır. Açık depo, geliştirmenin
  sürdüğü özel deponun geçmişsiz anlık görüntüsüdür: kabul edilen bir
  değişiklik elle özel depoya alınır ve sonraki gönderimle açık depoya
  döner.
- **Eski commit iletilerini örnek almayın.** 1.0.0 öncesindeki iletiler
  "kutadgu guncelleme <tarih> <saat>" biçiminde otomatik üretilmişti ve
  tarihten başka bir şey söylemiyor. Bundan sonraki her değişiklik
  neyin neden değiştiğini söyleyen bir iletiyle girer.
- Gönderdiğiniz değişikliğin iletisi **tek satır, Türkçe** olsun ve
  neyin neden değiştiğini söylesin. Örnek:
  `hakem raporu boşken çalışma sayfası düşüyordu: rapor alanı denetlenmeden biçimlendiriliyordu`
- **Bir konu bir istek.** Biçim düzeltmesi ile davranış değişikliğini
  aynı isteğe koymayın; ikisi bir aradayken hangisinin neyi bozduğu
  görünmez.
- **Büyük bir değişikliği yazmadan önce sorun.** Bir haftalık emeğin
  ilkeye takıldığı için geri çevrilmesi kimseye iyi gelmiyor.
  `/iletisim.php` ya da `SECURITY.md` içindeki adres bunun için var.
- **Ara ortam yoktur, bilerek.** `main`'e giren şey iki dakika içinde
  canlıdır: sunucudaki cron `git pull --ff-only` yapıp servisi yeniden
  başlatır. Bu yüzden gelen istekler elle okunur ve elle birleştirilir;
  otomatik birleştirme yok. Aceleye getirilmiş bir katkı doğrudan
  okuyucuya gider.
- **Depoya girmeyecek şeyler:** veri dosyaları, kütükler, anahtarlar,
  `.env`, üretilen dökümler, sınama verisi. `.gitignore` bunların
  çoğunu zaten kapatıyor; `ruh-ayar.json` hiçbir koşulda girmez.

## Nelere dokunulmaz

Aşağıdakiler tartışmaya açık değildir. Tüzük taslağının Ek A, Madde
2'sinde yazılıdırlar ve kodda iki kapı ile korunurlar: bir kararın
konusu bunlardan birini daraltıyorsa karar oylanmadan durdurulur, bir
ayar satırı daraltıyorsa yapılandırma okunurken etkisiz kılınır. Yani
bu maddeleri gevşeten bir katkı, kabul edilse bile çalışmaz.

**1. Arşive onay kapısı konulamaz.** Arşivin tamamı, kişisel veri
içermeyecek biçimde her an indirilebilir; bu erişim kimlik, kayıt,
üyelik, onay ya da bedel şartına bağlanamaz. Teknik önlemler erişimi
geciktirebilir, hiçbir durumda reddedemez (Madde 2.5). Gerekçesi iki
katlıdır: indirilemeyen bir arşiv kalıcı değildir, çünkü kalıcılık
kopyaların çokluğuyla sağlanır; ayrıca DOAJ gibi dizinler kayıt isteyen
bir yayını kabul etmez. Bir kapı koymak, arşivi hem kırılgan hem
dizinlenemez yapardı.

**2. Hakem raporları silinemez.** Raporlar hakemin adıyla, çalışmayla
aynı sayfada yayımlanır ve kaldırılmaz. Reddedilen çalışmalar da
arşivden çıkarılmaz (Madde 2.3). Gerekçesi: raporun silinebildiği bir
yerde kararın gerekçesi de silinebilir; o zaman "açık değerlendirme"
yalnızca bir görünüş olur. Bir raporun kişisel saldırı içermesi gibi
durumlarda editör perdeleme yapabilir, ama perdelemenin kendisi,
editörün adı ve gerekçesi görünür kalır; sansür de kayda geçer.

**3. Yayımlanmış kayıt geriye dönük değiştirilemez.** Düzeltme, endişe
bildirimi ve geri çekme, özgün kaydın **üzerine eklenen ayrı
kayıtlardır**; özgün metnin yerini almazlar (Madde 2.4). Bu kural
sistemin kendi geçmişine de uygulandı: kuruluş döneminde bugünkü
kuralları karşılamayan çalışmalar silinmedi, sayfalarında "kuruluş
dönemi kaydı" notuyla duruyor. Bir kaydı silmek, hiç var olmamış gibi
göstermektir; bu sistem bunu yapmaz.

**4. Yazar ile hakem arasındaki yazışma kalıcı kaydın parçasıdır.**
Sonradan düzenlenemez, temizlenemez, "sadeleştirilemez". Gerekçesi:
okuyucunun görmesi gereken şey yalnızca karar değil, kararın nasıl
oluştuğudur. İtirazın nerede kabul edildiği, hangi eleştirinin metni
değiştirdiği ancak yazışma dururken görülebilir.

## Davranış

Kısa tutuyoruz. Yazışmalarda kibar olun. Eleştiri koda yöneliktir,
kişiye değil; "bu satır şunu kaçırıyor" ile "bunu yazan düşünmemiş"
arasındaki fark, bu projede önemsenen bir farktır. Katkınız
reddedilebilir; reddedilirse gerekçesi yazılır, çünkü gerekçesiz
reddetmek bu sistemin hakem raporlarında da yasakladığı bir şeydir.
Ayrı bir davranış kuralları belgesi tutmuyoruz; bu paragraf yeterlidir.

---

# Contributing

## What this repository is

This repository is the source of **open source publishing software that
anyone can install on their own server**. The reference instance runs on
kutadgu.net and the `main` branch is the code running there; but the
software does not belong to one site: a university, library, society or
journal can run the same code under its own name and its own board.

The present limits should be stated plainly:

- Releases are recorded in `CHANGELOG.md`; 1.0.0 is archived on Zenodo
  with a DOI. There is not yet an installation package or a migration
  tool between versions; that is the first item of the roadmap
  (`ROADMAP.md`).
- The endpoints under `api/` are not yet a public API with an
  undertaking of backward compatibility. What can be relied on from
  outside are the endpoints opened to index harvesters: `/oai.php`,
  `/dokum.php`, `/arsiv.php`, `/sitemap.php`.
- One person maintains the software today. A second maintainer will
  come from among those who contribute under the rules in this
  document.

The licence is set out in detail in `LISANS.md`: the software is
**AGPL-3.0-only**, the works in the archive are **CC BY 4.0**. You need
no permission to install the system at your own institution; the single
condition is that if you offer a modified version as a service over a
network, you must also make the source available to those who use that
service. The name "Kutadgu" and its mark, however, may not be used as
the name of a derived system; the reason is not ownership but that a
reader should be able to tell from the name under which rules a work
was published. Run your own instance under your own name.

## Local installation

**Requirements.** PHP 8.0 or above (the code uses `str_starts_with` and
`str_ends_with`); development is done on PHP 8.4. There is no database,
no dependency manager to install and no build step. Required
extensions: `mbstring`, `json`, `dom`. The optional ones are used only
after checking that they exist: `gd` (share cards), `zip` (archive
package), `curl` (Telegram notifications and geolocation), `intl` (date
formatting).

**Data directory.** The system keeps its data in JSON files, and those
files sit **outside the code directory**. The order is: if
`veri_dizini` in `ayar.php` is set, that is used; if empty, the
`KUTADGU_DATA` environment variable; failing that, a `kutadgu-data`
folder one level above the code directory. An empty directory is fine,
the system creates the files it needs.

```
mkdir -p ~/kutadgu-data
cd /path/to/repository
KUTADGU_DATA=~/kutadgu-data php -S 127.0.0.1:8080 -t .
```

Pages open in **English** by default; add `?lang=tr` to the address for
Turkish.

**A limit of the built in server worth knowing.** `php -S` does not
apply `.htaccess` rules. This means addresses that depend on rewrite
rules quietly fall back to the home page locally: you get a 200, but
not the page you expected. Local equivalents:

| Live address | Locally |
|---|---|
| `/tamga/KTG-2026-00001-4` | `/yazi.php?doi=KTG-2026-00001-4` |
| `/sitemap.xml` | `/sitemap.php` |
| `/robots.txt` | `/robots.php` |
| `/oai` | `/oai.php` |
| `/kisi/name-surname` | `/kisi.php?k=name-surname` |

`/api/...` does work under the built in server. On the other hand the
**protective** rules in `.htaccess` are equally absent locally:
`ayar.php`, `ortak.php` and the PHP parts under `k/` are closed to the
outside on the live server and open locally. Do not expose the local
server beyond 127.0.0.1.

**Secrets.** Bot keys, webhook secrets and the like are never written
into the code or into `ayar.php`; they live in `ruh-ayar.json` in the
data directory and **never enter the repository**. You do not need them
to work locally; the features concerned simply stay off when no key is
present.

## Code style

The style is read off the existing code; what follows is simply the
rules already in force there, written down.

- **Naming is in Turkish.** Function and variable names are Turkish:
  `tg_veri_dizini()`, `tg_tamga_coz()`, `$yazilar`, `$hakem`,
  `$gerekce`. Shared helpers carry the `tg_` prefix and live in
  `ortak.php`; shell functions carry `k_` and live in `k/kabuk.php`. If
  you write a new helper, use the same prefixes.
- **Comments are in Turkish and explain WHY.** What the code does is
  already written in the code; what belongs in a comment is why that
  decision was taken. The repository has examples: why a lock is kept
  in two places, why a configuration key was deliberately left
  unchanged, why a check must not fail fatally. Three months on, you
  will not remember why a line is the way it is either.
- **Indentation is four spaces**, never tabs.
- **Lines are short.** Most code lines fall under 80 columns; block
  comments wrap at around 70. Long HTML and array lines stretch this,
  but not past the point of being readable.
- **No em dashes.** Not in code, not in comments, not in page text. Use
  a colon, a comma or a new sentence.
- Page files begin with `declare(strict_types=1);`.
- **Text is bilingual.** Every string written to a page is given in
  both languages as `k_c('Türkçe', 'English')`. Do not leave
  monolingual text behind.

## The `ayar.php` rule

Changes to text and to rules go **into the configuration file, not into
the code**. `ayar.php` is the system's single configuration file: the
domain, the brand names, the thresholds (`benzerlik_ust`,
`kabul_gecerli`, `serh_asgari_karakter`, `kurul_yayin`), the working
languages, the panel members and most page text are all there. If
changing a number or a sentence requires you to go inside the PHP
files, that value most likely needs moving into `ayar.php`; that is the
right contribution.

One warning: `ayar.php` is filtered against eight **unalterable
principles** as soon as it is read (`tg_ayar_ilke_suz`). A setting that
charges a fee is dropped, a closed licence is pulled back to an open
one, a setting that puts an approval gate on the archive is switched
off, a nationality requirement is deleted. Writing a configuration line
against the principles therefore achieves nothing; the system does not
silently ignore it, it neutralises it and records the fact.

## The `TG_SURUM` and `sw.js` rule

**If CSS or JS has changed, two places are updated:**

1. The `TG_SURUM` constant in `ortak.php` is bumped. The format is
   year, month, day and the sequence for that day (for example
   `'20260811e'`).
2. The cache names in `sw.js` (`SURUM` and `KABUK`) are renewed.

The reason: style and script files are cached as `immutable` for a
year. If the number does not change, a visitor receives the new page
with the old stylesheet and the layout falls apart. This is among the
faults that take longest to notice when forgotten, because nothing
looks wrong in your own browser.

## Before you send

1. **Syntax.** Run `php -l` on every file you touched. For all of them:
   ```
   for f in *.php api/index.php k/*.php; do php -l "$f" >/dev/null || echo "HATA: $f"; done
   ```
2. **Actually open the pages.** At least `/`, `/arsiv.php`,
   `/yazilar.php`, `/iletisim.php` and the page you touched, in the
   default language and with `?lang=tr`.
3. **No warnings in the server log.** The built in server writes
   `Warning`, `Notice` and `Deprecated` messages to the log. A page
   loading is not enough; the log must be clean.
4. **If CSS or JS changed**, check with a hard refresh (Ctrl+F5) and
   apply the version rule above.
5. **Look at the data directory.** If you touched a write path, check
   that the JSON files produced are not corrupted.

Tests live in `sinama/` (82 scripts); how to run them is described in
`sinama/OKUBENI.md`. The folder is closed to the web on the server. The
arrangement is: one script per job, reporting counts at the end
(`GECTI: n KALDI: m`). If you write your own test, follow the same
arrangement and add it under `sinama/`. The founders' real e-mail
addresses never enter the repository; scripts that need them read them
from the file named by the `KUTADGU_KURUL_EPOSTA` environment variable.

## How contributions arrive

- The public repository is `https://github.com/behiccetin/kutadgu.net`,
  main branch: `main`. Open issues and pull requests there. The public
  repository is a history-free snapshot of the private repository in
  which development happens: an accepted change is applied there by
  hand and comes back to the public repository with the next release.
- **Do not take the older commit messages as a model.** Before 1.0.0
  they were generated automatically in the form "kutadgu guncelleme
  <date> <time>" and say nothing beyond the date. From now on every
  change enters with a message that says what changed and why.
- The message for a change you send should be **one line, in Turkish**,
  and should say what changed and why. For example:
  `hakem raporu boşken çalışma sayfası düşüyordu: rapor alanı denetlenmeden biçimlendiriliyordu`
- **One subject per request.** Do not put a formatting fix and a
  behavioural change in the same one; together, it becomes invisible
  which of them broke what.
- **Ask before writing something large.** Nobody enjoys a week's work
  being turned away because it runs into a principle. `/iletisim.php`
  or the address in `SECURITY.md` exists for this.
- **There is deliberately no staging environment.** What lands on
  `main` is live within two minutes: a cron job on the server runs
  `git pull --ff-only` and restarts the service. Incoming requests are
  therefore read and merged by hand; nothing is merged automatically. A
  contribution rushed through goes straight to readers.
- **What must not enter the repository:** data files, logs, keys,
  `.env`, generated dumps, test data. `.gitignore` already closes off
  most of these; `ruh-ayar.json` never enters under any circumstances.

## What is not open to change

The following are not open to discussion. They are written in Article 2
of Appendix A of the draft charter and guarded by two gates in code: if
the subject of a decision narrows one of them, the decision is stopped
before it can be voted on, and if a configuration line narrows one, it
is neutralised as the configuration is read. A contribution that
loosens these articles will not work even if it is accepted.

**1. No approval gate may be placed on the archive.** The whole
archive, carrying no personal data, can be downloaded at any time; that
access may not be made conditional on identity, registration,
membership, approval or payment. Technical measures may delay access;
they may never refuse it (Article 2.5). The reason is twofold: an
archive that cannot be downloaded is not permanent, because permanence
comes from the number of copies; and indexes such as DOAJ do not accept
a publication that requires registration. A gate would make the archive
both fragile and unindexable.

**2. Reviewer reports cannot be deleted.** Reports are published under
the reviewer's name on the same page as the work and are not taken
down. Rejected work is likewise not removed from the archive (Article
2.3). The reason: where a report can be deleted, the reason for the
decision can be deleted too, and then "open assessment" is only an
appearance. Where a report carries a personal attack, an editor may
screen it, but the screening itself, the editor's name and the reason
remain visible; censorship is recorded too.

**3. A published record cannot be altered retrospectively.** A
correction, an expression of concern and a retraction are **separate
records added on top of** the original; they do not replace the
original text (Article 2.4). This rule was applied to the system's own
past as well: work from the founding period that does not meet today's
rules was not deleted, it stands with a "founding period record" note
on its page. To delete a record is to make it appear never to have
existed; this system does not do that.

**4. Correspondence between author and reviewer is part of the
permanent record.** It cannot be edited, cleaned up or "tidied" later.
The reason: what a reader needs to see is not only the decision but how
the decision came about. Where an objection was accepted, and which
criticism changed the text, can only be seen while the correspondence
stands.

## Conduct

We keep this short. Be courteous in correspondence. Criticism is aimed
at the code, not the person; the difference between "this line misses
that case" and "whoever wrote this did not think" is a difference this
project cares about. Your contribution may be turned down; if it is,
the reason will be written out, because refusing without a reason is
something this system forbids in its reviewer reports as well. We keep
no separate code of conduct document; this paragraph is enough.
