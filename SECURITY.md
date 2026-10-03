# Güvenlik

Bu belge, Kutadgu'da bir güvenlik açığı bulan kişinin ne yapması
gerektiğini ve karşılığında ne bekleyebileceğini anlatır. Sözü tutulacak
şeyler yazılmıştır; tutulacağı bilinmeyen hiçbir şey yazılmamıştır.

`.htaccess` kuralı `.md` uzantılı dosyaları dışarıya kapatır; bu belge
sunucuda yayımlanmaz, yalnızca depoda durur.

## Bildirim açık değil, özel yapılır

Bulduğunuz şeyi herkese açık bir yerde (depo konusu, sosyal ağ, blog)
yazmadan önce bize yazın. İki yol var:

- **E-posta:** `editor@kutadgu.net`
  Sistemin yapılandırmasındaki iletişim adresidir; aynı adres OAI-PMH
  `Identify` yanıtında yönetici adresi olarak da yayımlanır.
- **İletişim sayfası:** `/iletisim.php`
  Buradan yazdığınızda size yalnızca sizde duran bir bağlantı verilir.
  Konuşma hiçbir listede görünmez, arama motorlarına kapalıdır ve yanıt
  aynı sayfada belirir. E-posta adresinizi bırakmak istemiyorsanız bu
  yol daha uygundur.

Yazarken şunlar işi hızlandırır: hangi adres ya da uç nokta, hangi
adımlarla, ne elde ediliyor. Varsa isteğin ve yanıtın kendisi. Bir rol
gerekiyorsa (hakem erişimi, yazar erişimi, panel) hangisiyle
denediğiniz. Kavram kanıtı için gerçek bir çalışmanın kaydını
bozmayın; deneme kaydı yeterlidir, çünkü yayımlanmış bir kayıt geriye
dönük düzeltilemez.

## Yanıt süresi

Bu sistemi bugün tek kişi yürütüyor. Nöbet tutan bir ekip, bilet
sistemi ya da hizmet düzeyi taahhüdü yok. "Kırk sekiz saat içinde yanıt
verilir" demek kolay olurdu, ama tutulacağı garanti edilemeyeceği için
denmiyor.

Uygulamada olan şudur: iletiler okunur ve okunduğunda yanıtlanır;
bu birkaç gün sürebilir. **İki hafta geçtiği hâlde hiç ses çıkmadıysa**
aynı adrese ikinci kez yazın, ileti kaybolmuş olabilir.

Düzeltmenin yayına girmesi kısa sürer: dosya diske yazılır, depoya
gönderilir, sunucudaki cron iki dakikada bir `git pull --ff-only`
yapıp servisi yeniden başlatır. Ara (staging) ortamı bilerek yoktur;
bu, düzeltmenin dakikalar içinde yayına girmesi demektir, ama aynı
zamanda düzeltmenin yerelde denenmiş olması gerektiği anlamına gelir.

## Kapsam

### Güvenlik açığı sayılanlar

- Kimlik doğrulamanın atlatılması: panel girişi, hakem erişimi, yazar
  erişimi, yönetim uçları.
- Başkasının hakem ya da yazar erişim belirtecini veya şifresini elde
  etmek, tahmin etmek, sıralamak.
- Hakem sürecindeki, henüz yayımlanmamış bir çalışmanın metnine yetkisiz
  erişim. O metin yazarın emanetidir; sistemin çeviri düzeni bile onu
  yazarın açık onayı olmadan dışarı vermez.
- Yayımlanmış bir kaydın izinsiz değiştirilmesi ya da silinmesi.
- Betik enjeksiyonu (XSS), kalıcı içerik enjeksiyonu, siteler arası
  istek sahtekârlığı, oturum ele geçirme.
- Sunucuda dosya okuma ya da yazma; veri dizinine erişim.
- Gizli anahtarların sızması: veri dizinindeki `ruh-ayar.json` içinde
  duran bot anahtarı ve webhook gizli anahtarı.
- İletişim webhook'unun gizli anahtar olmadan işlem yaptırabilmesi.
- Hız sınırlarının anlamlı bir sonuç doğuracak biçimde aşılması
  (kütük şişirme, kayıt kirletme, hizmet dışı bırakma).

### Güvenlik açığı sayılmayanlar

Aşağıdakiler kusur değil, sistemin kurulduğu ilkelerdir. Tüzük
taslağının Ek A, Madde 2'sinde yazılıdır ve kodda da bir kapı ile
korunur: bu ilkeleri daraltan bir ayar satırı yazılırsa yapılandırma
okunur okunmaz düşürülür.

- **Arşivin herkese açık olması.** `/arsiv.php` üzerinden arşivin tamamı
  kimlik, kayıt, üyelik, onay ya da bedel istenmeden indirilir. Bu bir
  açık değil, Madde 2.5'tir. İndirmeyi bir kapıya bağlamak bir düzeltme
  değil, ilkenin ihlali olurdu.
- **Hakem raporlarının ve hakem adlarının görünür olması.** Raporlar
  çalışmayla aynı sayfada, adlarıyla yayımlanır (Madde 2.3).
- **Kurul oylarının ve gerekçelerinin oylama kapandıktan sonra
  açılması.**
- **Reddedilen çalışmaların arşivde kalması** ve yazar ile hakem
  arasındaki yazışmanın kalıcı kayıtta durması.
- Sürüm bilgisi, sunucu yazılımı adı gibi bilgilerin görünmesi.
- Otomatik bir tarayıcının tek başına ürettiği çıktı: eksik başlık
  listesi, TLS derecelendirmesi, "en iyi uygulama" uyarıları. Bunlar
  bir sonuca bağlanmadıkça rapor sayılmaz; sömürülebilir bir etki
  gösterilmelidir.
- Kullanıcının kendi tarayıcısında kendi verisini görebilmesi.

## Bilinen sınırlılıklar

Bunlar bilinen ve bugün bilerek kabul edilmiş sınırlardır. Burada
yazılı olmaları, saklandıklarında bir sızıntının çok daha pahalıya mal
olacağı içindir. Bunları bildirmenize gerek yok; bunları **kötüye
kullanılabilir hâle getiren** bir şey bulursanız bildirin.

**1. Hakem ve yazar erişim şifreleri diskte açık metin durur.**
Sisteme davet edilen hakeme ve çalışmasını izleyen yazara `K7P-3RM`
biçiminde altı karakterlik bir erişim şifresi üretilir. Bu şifre veri
dizinindeki `yazilar.json` dosyasında düz metin olarak saklanır;
doğrulama sabit süreli karşılaştırmayla yapılır ama karşılaştırılan
değerin kendisi açıktır. Sebebi şudur: şifre bir kez gösterilip
unutulmuyor, sonradan yeniden gösterilmesi gerekiyor; yazar kendi
önerdiği hakeme daveti kendisi iletiyor ve editör panelinde de aynı
şifre görünüyor. Özet alınsaydı bu aktarma mümkün olmazdı.

Sınırın nereye kadar tutulduğu: veri dizini kod dizininin dışındadır,
sunucu kuralları `.json` dosyalarını dışarıya kapatır ve panel
parolaları bu düzenin dışındadır, onlar `password_hash` ile saklanır.
Yine de sunucudaki dosyaları okuyabilen ya da bir yedeği ele geçiren
biri bu erişim şifrelerini görür ve ilgili çalışmanın hakem veya yazar
ekranına girebilir.

**2. `/hakem-gonullu` ucu oturum istemez.** Bir çalışmaya hakem olmak
için gönüllü olma başvurusu, oturum açmadan gönderilebilir. Kapıda
duran şeyler: unvan, ORCID, doktora belgesinin türü ve doğrulama
kaynağı, çıkar çatışması denetimi, çalışma başına başvuru üst sınırı ve
IP özetine bağlı bir sayaç (iki saatlik pencerede on ikinci başvurudan
sonra 429). Başvuru doğrudan hakemlik açmaz, editör onayından geçer.

Sınırı şudur: IP adresi vekil sunucu başlıklarından okunur. Sistem bu
başlıkları kendisi yazan bir vekil sunucunun arkasında değilse başlık
uydurulabilir ve sayaç dağıtılabilir. Yani bu sayaç bir kapı değil,
yalnızca bir yavaşlatıcıdır; gerçek kapı editör onayıdır.

**3. Yönetim hesabının ilk parolası kodda yazılıdır.** Veri dizininde
`auth.json` yoksa sistem onu kodda yazılı bir varsayılan parolayla
üretir. Kaynak herkese açık olduğu için bu parola da açıktır. Yeni
kurulan her kopyada ilk iş bu parolayı değiştirmektir; değiştirilene
kadar panel korumasız sayılmalıdır.

**4. Ziyaretçi IP adresi bir dış hizmete sorulur.** Yönetim
istatistiklerindeki şehir ve ülke bilgisi için IP adresi ücretsiz bir
konum hizmetine gönderilir ve sonuç otuz gün önbelleklenir. Bu, ziyaretçi
IP'sinin üçüncü bir tarafa gitmesi demektir.

## Ödül programı yok

Para ödülü, hediye ya da sözleşmeli bir ödül programı **yoktur** ve
kurulması planlanmıyor. Bu sistem hiçbir yönde para almadığı gibi
veremiyor da. Yapabileceğimiz tek şey teşekkür etmek ve isterseniz
adınızı anmaktır. Bunu baştan yazıyoruz ki kimse emek harcadıktan sonra
karşılığını beklemek zorunda kalmasın.

## Sorumlu açıklama

Bulduğunuz şeyi, düzeltmesi yayına girene kadar kendinize saklamanızı
rica ediyoruz. Bu bir rica; imzalanacak bir şey, dayatılmış bir süre ya
da yerine getirilmezse hukuki bir tehdit değil. Bulan kişi bulduğu şeyi
istediği zaman yazmakta serbesttir.

Karşılığında yapabileceğimiz şey: bildirimi ciddiye almak, ne yaptığımızı
size söylemek ve düzeltmeyi geciktirmemek. Düzeltilemeyecek ya da
düzeltilmemesine karar verilmiş bir şey varsa, bunu da gerekçesiyle
söyleriz. Susmak yerine "bunu şimdilik böyle bırakıyoruz, sebebi şu"
demeyi tercih ediyoruz; yukarıdaki "bilinen sınırlılıklar" bölümü tam
olarak bunun için var.

---

# Security

This document explains what to do if you find a security flaw in
Kutadgu and what you may expect in return. Only undertakings that can
be kept are written here; nothing whose keeping is uncertain has been
promised.

A server rule closes `.md` files to the outside; this document is not
published on the site, it lives only in the repository.

## Report privately, not publicly

Before writing about what you found in a public place (a repository
issue, a social network, a blog), write to us. There are two routes:

- **E-mail:** `editor@kutadgu.net`
  This is the contact address held in the system's configuration; the
  same address is published as the administrator address in the OAI-PMH
  `Identify` response.
- **Contact page:** `/iletisim.php`
  Writing from here gives you a link that only you hold. The
  conversation appears in no listing, is closed to search engines, and
  the reply appears on that same page. If you would rather not leave an
  e-mail address, this is the better route.

These things speed matters up: which address or endpoint, by which
steps, and what is obtained. The request and the response themselves,
if you have them. If a role is required (reviewer access, author
access, panel), which one you used. For a proof of concept, please do
not damage the record of a real work; a test record is enough, because
a published record cannot be corrected retrospectively.

## Response time

One person runs this system today. There is no team on rota, no ticket
system and no service level undertaking. It would be easy to write "you
will receive a reply within forty eight hours", but that is not written
here because it cannot be guaranteed.

What happens in practice: messages are read and answered when read;
this may take a few days. **If two weeks pass with no word at all**,
write to the same address a second time; the message may have gone
astray.

A fix reaches the site quickly: the file is written to disk, pushed to
the repository, and a cron job on the server runs `git pull --ff-only`
every two minutes and restarts the service. There is deliberately no
staging environment. That means a fix is live within minutes, and it
also means a fix must have been tried locally first.

## Scope

### What counts as a security flaw

- Bypassing authentication: panel login, reviewer access, author
  access, administrative endpoints.
- Obtaining, guessing or enumerating another person's reviewer or
  author access token or code.
- Unauthorised access to the text of a work still under review and not
  yet published. That text is held in trust for the author; even the
  translation arrangement does not send it out without the author's
  express consent.
- Altering or deleting a published record without authorisation.
- Script injection (XSS), persistent content injection, cross site
  request forgery, session hijacking.
- Reading or writing files on the server; access to the data directory.
- Leakage of secrets: the bot key and the webhook secret held in
  `ruh-ayar.json` in the data directory.
- Making the contact webhook act without the secret key.
- Defeating rate limits in a way that produces a real consequence (log
  flooding, polluting the record, denial of service).

### What does not count

The following are not defects but the principles the system was built
on. They are written in Article 2 of Appendix A of the draft charter
and are also guarded in code: a configuration line that narrows any of
them is dropped as soon as the configuration is read.

- **The archive being open to everyone.** The whole archive can be
  downloaded through `/arsiv.php` without identity, registration,
  membership, approval or payment. This is not a flaw, it is Article
  2.5. Putting that download behind a gate would not be a fix, it would
  be a breach of the principle.
- **Reviewer reports and reviewer names being visible.** Reports are
  published under their authors' names on the same page as the work
  (Article 2.3).
- **Panel votes and their reasons being opened once voting closes.**
- **Rejected work remaining in the archive**, and correspondence
  between author and reviewer remaining part of the permanent record.
- Version information or the name of the server software being visible.
- The bare output of an automated scanner: lists of missing headers,
  TLS grades, "best practice" warnings. These do not count as a report
  unless tied to a demonstrated, exploitable consequence.
- A user being able to see their own data in their own browser.

## Known limitations

These are known limits, accepted deliberately as things stand. They are
written here because hiding them would cost far more in the event of a
leak. You need not report them; do report anything that makes them
**exploitable**.

**1. Reviewer and author access codes are stored in clear text on
disk.** An invited reviewer, and an author following their own work,
are given a six character access code in the form `K7P-3RM`. That code
is kept in clear text in `yazilar.json` in the data directory;
verification uses a constant time comparison, but the value compared is
itself in the clear. The reason: the code is not shown once and then
forgotten, it has to be shown again later. An author passes the
invitation to a reviewer they themselves proposed, and the same code is
visible in the editor's panel. Hashing it would make that passing on
impossible.

How far the limit is held: the data directory sits outside the code
directory, server rules close `.json` files to the outside, and panel
passwords are outside this arrangement altogether, stored with
`password_hash`. Even so, anyone able to read files on the server, or
who obtains a backup, sees these access codes and can enter the
reviewer or author screen of the work concerned.

**2. The `/hakem-gonullu` endpoint requires no session.** An offer to
review a work can be submitted without logging in. What stands at the
door: title, ORCID, the type and verification source of the doctoral
document, a conflict of interest check, a ceiling on applications per
work, and a counter keyed to a hash of the IP address (429 after the
twelfth application within a two hour window). An application does not
open a reviewership by itself; it passes through editor approval.

Its limit: the IP address is read from proxy headers. If the system is
not behind a proxy that writes those headers itself, they can be forged
and the counter spread out. So this counter is not a gate but a brake;
the real gate is editor approval.

**3. The first password of the administrative account is written in the
code.** If `auth.json` is absent from the data directory, the system
creates it with a default password written in the source. Since the
source is public, so is that password. On every newly installed copy
the first task is to change it; until it is changed, the panel should
be regarded as unprotected.

**4. Visitor IP addresses are sent to an outside service.** For the
city and country shown in the administrative statistics, the IP address
is sent to a free geolocation service and the result is cached for
thirty days. This means visitor IP addresses reach a third party.

## There is no bounty programme

There is **no** monetary reward, gift or contractual bounty programme,
and none is planned. This system takes no money in any direction and so
has none to give. All we can do is thank you and, if you wish, name
you. This is written plainly at the outset so that nobody spends effort
expecting something in return.

## Responsible disclosure

We ask that you keep what you found to yourself until the fix is live.
That is a request: not something to be signed, not an imposed deadline,
and not a legal threat if it is not honoured. Whoever finds something
is free to write about it whenever they choose.

In return we can do this much: take the report seriously, tell you what
we are doing about it, and not delay the fix. If something cannot be
fixed, or a decision is taken not to fix it, we will say so and give
the reason. We would rather say "we are leaving this as it is for now,
and here is why" than say nothing; the known limitations section above
exists for exactly that purpose.
