<?php
/* =====================================================================
   KUTADGU - Bilimsel açıklar ve kapatma durumu
   Bir sistemin şeffaflık iddiası varsa, ilk şeffaf olacağı şey kendi
   kusurlarıdır. Bu sayfa sistemin zayıf noktalarını ve her biri için
   ne yapıldığını açıkça yazar; kapatılmamış olanlar da burada durur.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en = k_en();

/* durum: kapali | kismi | acik */
$maddeler = [
  [
    'durum' => 'kapali',
    'bas' => ['Hakemi yazarın seçmesi', 'The author choosing the reviewer'],
    'sorun' => [
      'Yazarın kendi hakemini önermesi, birbirini onaylayan kapalı bir halka doğurabilir. Bu kurgusal bir tehlike değildir: büyük yayınevleri, yazarın önerdiği dost ya da sahte hakem adresleri yüzünden binlerce makaleyi geri çekmiştir.',
      'An author proposing their own reviewer can produce a closed circle of mutual approval. This is not a hypothetical danger: major publishers have retracted thousands of papers because of friendly or fabricated reviewer addresses supplied by authors.',
    ],
    'yapilan' => [
      'Bir çalışmanın hakem onaylı sayılabilmesi için olumlu raporlardan en az birinin yazarın önermediği bir hakemden gelmesi arandı. Karşılıklı hakemlik engellendi: bir kişinin çalışmasını yakın zamanda değerlendiren yazar, o kişiyi kendi çalışmasına öneremiyor. Ortak yayın yapmış kişilerin birbirine hakem olması kapatıldı. Her hakemin kim tarafından atandığı, adı ve saatiyle okuyucuya gösteriliyor.',
      'For a work to count as reviewer approved, at least one positive report must come from a reviewer the author did not propose. Reciprocal reviewing is blocked: an author who recently assessed someone\'s work cannot propose that person for their own. Reviewing between people who have published together is closed off. Who assigned each reviewer, with their name and the time, is shown to the reader.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Hakemliğin yazarlık bileti olması', 'Reviewing as a ticket to authorship'],
    'sorun' => [
      'Yazar olmak için hakemlik şartı katılımı artırır ama ters bir teşvik üretir: hızlı, yüzeysel ve olumlu rapor yazma eğilimi.',
      'Requiring a review before authorship increases participation but creates a perverse incentive: quick, shallow and favourable reports.',
    ],
    'yapilan' => [
      'Rapor nitelik eşiği kondu. Yeterli gerekçe taşımayan, metinde hiçbir yeri işaretlemeyen ya da ölçüt değerlendirmesi doldurulmamış bir rapor yayımlanır ve görünür kalır, ancak onay sayımına ve hakemlik kaydına katılmaz. Eşik, hakem raporu göndermeden önce ekranda gösterilir. Ayrıca her hakemin sistemdeki karar dağılımı okuyucuya açıktır; her şeye onay veren hakem görünür olur.',
      'A quality threshold was introduced. A report with insufficient reasoning, with no passage marked in the text, or with the criteria assessment left unfilled is published and stays visible, but does not count towards approval or towards the reviewing record. The threshold is shown on screen before the report is sent. Each reviewer\'s distribution of decisions is also open to the reader, so a reviewer who approves everything becomes visible.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['Açık hakemliğin nezaket yanlılığı', 'The courtesy bias of open review'],
    'sorun' => [
      'Kör olmayan hakemlik dürüstlüğü artırır ama sertliği azaltır. Genç bir hakem, profesörün çalışmasını reddetmekte zorlanır.',
      'Review that is not blind increases honesty but reduces severity. A junior reviewer finds it hard to reject a professor\'s work.',
    ],
    'yapilan' => [
      'Şimdilik açıkça yazılmakla yetinildi: bu bir bedeldir ve sistem bunu biliyor. Düşünülen çözüm, hakeme davet anında bir seçenek sunmaktır: rapor her hâlükârda yayımlanır, ancak hakem isterse adı karar kesinleşene kadar gizli tutulur ve yayımdan sonra açılır. Bu seçenek henüz eklenmedi.',
      'For now it is simply stated openly: this is a cost, and the system knows it. The intended remedy is to offer the reviewer a choice at the moment of invitation: the report is published in any case, but the reviewer\'s name may be withheld until the decision is final and revealed afterwards. That option has not yet been added.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Reddedilen çalışmanın yayında kalması', 'A rejected work remaining published'],
    'sorun' => [
      'Şeffaflık açısından doğru, bilimsel kayıt açısından risklidir: verisi uydurma bir metin kalıcı bir kimlikle arşivde durur ve zamanla atıf alabilir. Arama motorları sayfadaki uyarı bandını her zaman okumaz.',
      'Right for transparency, risky for the record: a text with fabricated data sits in the archive under a permanent identifier and may in time attract citations. Search engines do not always read a notice on the page.',
    ],
    'yapilan' => [
      'Geri çekme bilgisi yalnızca sayfada değil, makine tarafından okunabilen üst verilerde de yer alıyor: çalışmanın başlığı arama motorlarına ve dizinlere geri çekildiği bilgisiyle gidiyor. Düzeltme, endişe bildirimi ve geri çekme kayıtları kalıcı kimliğe bağlanıyor; tarihi, gerekçesi ve kararı verenin adı görünüyor.',
      'The fact of retraction now appears not only on the page but in machine readable metadata: the title reaches search engines and indexes carrying it. Corrections, expressions of concern and retractions are bound to the permanent identifier, with the date, the grounds and the name of whoever decided.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Geri çekme ve düzeltme yolunun bulunmaması', 'The absence of a path for correction and retraction'],
    'sorun' => [
      'Yayımdan sonra hata bulunduğunda yapılacak bir şey tanımlı değildi. Oysa bu, bilimsel yayıncılığın temel gereklerinden biridir.',
      'Nothing was defined for what to do when an error is found after publication, although this is one of the basic requirements of scholarly publishing.',
    ],
    'yapilan' => [
      'Düzeltme, endişe bildirimi ve geri çekme, kimliğe bağlı birer kayıt türü olarak eklendi. Özgün metin silinmiyor; kayıt metnin üstüne ekleniyor ve rapor belgesinde de görünüyor.',
      'Correction, expression of concern and retraction were added as record types bound to the identifier. The original text is not deleted; the record is added above it and appears in the report document as well.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Tekrarlanabilirlik', 'Reproducibility'],
    'sorun' => [
      'Bugünün bilimindeki en büyük sorun hakemlik değil, tekrarlanamayan sonuçlardır. Veri ve çözümleme kodu paylaşılmadan bir sonucun doğruluğu denetlenemez.',
      'The greatest problem in today\'s science is not review but results that cannot be reproduced. Without shared data and analysis code, the correctness of a result cannot be checked.',
    ],
    'yapilan' => [
      'Her gönderimde veri ve kod erişilebilirliği beyanı zorunlu kılındı; paylaşılamıyorsa gerekçesi yazılıyor ve okuyucuya görünüyor. Hakem raporuna tekrarlanabilirlik değerlendirmesi eklendi.',
      'A statement on the availability of data and code is now required with every submission; where it cannot be shared, the reason is stated and shown to the reader. A reproducibility assessment was added to the reviewer\'s report.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Alan daraltmasının maliyeti', 'The cost of narrowing by field'],
    'sorun' => [
      'Hakemin alanını çalışmanın alanına bağlamak, bilimin en verimli yerini keser: bir fizikçinin iktisat çalışmasına yaptığı katkı çoğu zaman iktisatçının yapamayacağı katkıdır.',
      'Tying the reviewer\'s field to the work\'s field cuts off the most fertile ground in scholarship: a physicist\'s contribution to an economics paper is often one an economist could not make.',
    ],
    'yapilan' => [
      'Alan uyuşmazlığı engel olmaktan çıkarıldı. Hakem, hangi sıfatla değerlendirdiğini kendisi seçiyor: konu, yöntem, veri ve istatistik ya da dil. Yanına kendisini o çalışmada neden yetkin gördüğünü yazıyor ve bu cümleler raporla birlikte yayımlanıyor. Kapı kapanmıyor, şeffaflaşıyor.',
      'A mismatch of field is no longer an obstacle. The reviewer chooses the capacity in which they assess: subject, method, data and statistics, or language. Alongside it they write why they consider themselves competent for that work, and those sentences are published with the report. The door is not closed; it is made transparent.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Yazarın erişimi ele geçerse', 'If an author\'s access is taken'],
    'sorun' => [
      'Yayımlanmış bir çalışmanın metni yazar panelinden değiştirilebiliyordu. Parolası çalınan bir yazarın çalışması, kimse fark etmeden başkalaştırılabilirdi.',
      'The text of a published work could be altered from the author panel. The work of an author whose password was stolen could be changed without anyone noticing.',
    ],
    'yapilan' => [
      'Değerlendirmesi tamamlanmış bir çalışmanın metni kilitleniyor; yazar bile değiştiremiyor. Düzeltme ancak gerekçesiyle editöre başvurularak, kayda geçerek yapılıyor. Her değişiklikte metnin parmak izi saklanıyor ve yazara e-posta gidiyor, böylece izinsiz bir değişiklik hemen fark ediliyor.',
      'The text of a work whose assessment is complete is locked; not even the author can change it. A correction is made only by applying to an editor with reasons, and it enters the record. At every change the text\'s fingerprint is stored and the author is notified by e mail, so an unauthorised change is noticed at once.',
    ],
  ],
  /* BU AÇIK 13 AĞUSTOS 2026'DA KAPANDI — ama kapanma biçimi yazılmadan
     kapatılmış sayılmaz. Eşik kaldırılarak kapandı, ölçüm eklenerek
     değil. İkisi aynı şey değildir ve karıştırılırsa sistem, yapmadığı
     bir işi yapmış gibi görünür.

     Kayıt silinmedi: bir açığın nasıl kapandığı, açığın kendisi kadar
     bilgidir. */
  [
    'durum' => 'kapali',
    'bas' => ['Ölçülemeyen benzerlik eşiği', 'A similarity threshold that could not be measured'],
    'sorun' => [
      'Yüzde on beşlik benzerlik eşiği yazılıydı ve her başvuruda rapor isteniyordu; ama bu sistemde o oranı ölçen hiçbir şey yoktu. Yazar bir sayı yazıyor, sistem o sayıyı denetlemeden kayda geçiriyordu.',
      'A fifteen per cent similarity threshold was written down and a report was required with every submission; but nothing in this system measured that ratio. The author typed a number and the system recorded it without checking it.',
    ],
    'yapilan' => [
      'Eşik ve rapor şartı 13 Ağustos 2026 kurul kararıyla kaldırıldı. Ölçüm eklenerek değil, ölçülemeyen kural kaldırılarak kapatıldı; ikisi aynı şey değildir. Ayrıca rapor paralıdır ve kapıyı paraya bağlıyordu. Aşırma yasağı sürüyor ve denetim, metnin herkese açık durmasıyla ve hakemin adıyla imzaladığı raporla yapılıyor. Yazar dilerse kendi raporunu ekler; eklediği oran çalışmanın sayfasında görünür. Kendi arşivimize karşı otomatik karşılaştırma hâlâ yok.',
      'The threshold and the report requirement were removed by a board decision on 13 August 2026. It was closed by removing a rule that could not be measured, not by adding the measurement; the two are not the same. Such reports are also paid for, which put a price on the door. The prohibition on plagiarism stands, and the scrutiny is done by the text standing open to everyone and by the report a reviewer signs with their name. An author may attach their own report; the ratio then appears on the work\'s page. Automatic comparison against our own archive is still absent.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['Unvan şartının bilimsel maliyeti', 'The scholarly cost of the title requirement'],
    'sorun' => [
      'Doktora sahibi olmak bir çalışmanın doğru olduğunu göstermez; olmamak da yanlış olduğunu göstermez. Unvan şartı kapıyı güvenli kılar ama bilimi daraltır.',
      'Holding a doctorate does not show that a work is right, nor does lacking one show that it is wrong. The title requirement makes the door safe but narrows scholarship.',
    ],
    'yapilan' => [
      'Kısmen kapatıldı. Doktora derecesi olmayan bir araştırmacı, iki doktoralı araştırmacının adıyla sorumluluk üstlenmesiyle yazar olarak yer alabiliyor. ' . tg_destek_ad_bas(false, true) . 'dan biri aynı çalışmanın doktoralı ortak yazarı, diğeri çalışmayla hiçbir bağı olmayan bir doktoralı olmak zorunda; böylece bir danışman kendi öğrencisini tek başına içeri alamıyor. ' . tg_destek_ad_bas(false, true) . 'ın her biri kendi bağlantısından, gerekçesini yazarak onaylıyor; onay gelmeden çalışma yayına alınmıyor ve gerekçe çalışmanın sayfasında kalıcı olarak duruyor. Bu yolla yazar olan araştırmacı hakem raporu yazamıyor, kurul oyu kullanamıyor ve tek yazar olarak çalışma gönderemiyor; doktora belgesini doğrulattığı gün bu sınırların hepsi kalkıyor. Kapatılmayan kısım şudur: yazarlık desteği hâlâ tanıdık olmayı gerektirir. Hiç kimseyi tanımayan, hiçbir kurumla bağı olmayan bir araştırmacının önü yine kapalıdır; bu kişi için düşünülen yol, gönüllü hakemlerin çalışmayı okuyup katkının gerçekliğine tanıklık etmesidir, ama henüz eklenmemiştir.',
      'Partly closed. A researcher without a doctorate may appear as an author where two researchers holding doctorates take responsibility under their own names. One supporting researcher must be a co author of the same work holding a doctorate, the other must hold a doctorate and have no connection with the work, so that a supervisor cannot admit their own student alone. Each supporting researcher confirms through their own link, writing their reasoning; the work is not published until the confirmation arrives, and the reasoning stays permanently on the work\'s page. A researcher admitted by this path may not write referee reports, cast panel votes or submit as sole author; on the day they verify a doctoral document, all of these limits fall away. What remains unclosed: authorship support still requires being known to someone. A researcher who knows no one and has no institutional tie is still shut out; the path considered for them is for volunteer reviewers to read the work and attest to the reality of the contribution, but it has not yet been added.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['Arşivin kalıcılığı', 'The permanence of the archive'],
    'sorun' => [
      'Sunucu giderse kayıt da gider. Kalıcı kimlik vermek, kalıcılığı taahhüt etmektir; oysa taahhüdün kendisi tek bir makineye ve tek bir kişinin ömrüne bağlıysa boştur.',
      'If the server goes, the record goes with it. To issue a permanent identifier is to promise permanence; yet the promise is empty if it rests on a single machine and a single person\'s lifetime.',
    ],
    'yapilan' => [
      'Kısmen kapatıldı. Üç şey yapıldı. Birincisi, her çalışmanın metin parmak izi sayfasında yayımlanıyor; metin sonradan sessizce değiştirilirse bu değer tutmaz ve değişiklik herkesçe görülebilir. İkincisi, arşivin tamamı tek bir adresten, kimseden izin almadan indirilebiliyor: çalışmalar, hakem raporları, kurul oylamaları, şerhler ve düzeltme kayıtları dahil. Dosya kişisel veri içermez; e-posta adresleri, erişim anahtarları ve hesap kayıtları bilerek dışarıda bırakılmıştır. Böylece kopyayı alan herkes arşivi sürdürebilir ve kalıcılık tek bir sunucuya bağlı kalmaz. Üçüncüsü, veri klasörünü ve dışa aktarımı her gece ikinci bir yere kopyalayan bir yedek betiği depoya kondu. Kapatılmayan kısım şudur: arşiv henüz kurumsal bir dijital koruma hizmetine (LOCKSS, CLOCKSS, Internet Archive gibi) kayıtlı değildir. Bu bir kod işi değil, bir kurum kararıdır ve verildiğinde burada yazılacaktır.',
      'Partly closed. Three things were done. First, the fingerprint of each work\'s text is published on its page; if the text is later altered in silence the value no longer matches and the change becomes visible to anyone. Second, the entire archive can be downloaded from a single address without anyone\'s permission: works, referee reports, panel votes, notes and correction records included. The file contains no personal data; e mail addresses, access keys and account records are deliberately excluded. Anyone who takes a copy can therefore continue the archive, and permanence no longer rests on one server. Third, a backup script that copies the data directory and the export to a second location every night was placed in the repository. What remains unclosed: the archive is not yet lodged with an institutional digital preservation service (such as LOCKSS, CLOCKSS or the Internet Archive). That is not a matter of code but an institutional decision, and when it is taken it will be written here.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Yayın sonrası eleştiri', 'Post publication criticism'],
    'sorun' => [
      'Bir çalışma yayımlandıktan sonra okurun itiraz edeceği, tartışmayı sürdüreceği bir yer yok. Oysa açık bir sistemin klasik dergiye üstünlüğü tam da buradadır.',
      'After a work is published there is no place for a reader to object or to continue the discussion, although this is precisely where an open system has the advantage over a conventional journal.',
    ],
    'yapilan' => [
      'Her çalışmanın altına yayın sonrası şerh alanı eklendi. Doğrulanmış hesabı olan her okuyucu kendi adıyla kalıcı bir not bırakabilir: itiraz, düzeltme, yineleme denemesi ya da kanıt. Şerh silinmez; ne yazan siler, ne çalışmanın yazarı, ne editör. Yazar her şerhe bir kez yanıt verebilir. Kişisel saldırı ya da hukuka aykırı içerik taşıyan bir şerh editörce perdelenebilir; ancak perdelemenin yapıldığı, kimin yaptığı ve gerekçesi görünür kalır, böylece sansür de kayda geçer.',
      'A post publication commentary area was added beneath every work. Any reader with a verified account may leave a permanent note under their own name: an objection, a correction, a replication attempt or a piece of evidence. Notes are never deleted, not by the writer, not by the author, not by an editor. The author may reply to each note once. A note carrying a personal attack or unlawful content may be screened by an editor, but the fact of the screening, the editor\'s name and the reason remain visible, so that censorship is recorded too.',
    ],
  ],
  [
    'durum' => 'kapali',
    'bas' => ['Anlaşmazlıkta kararı tek kişinin vermesi', 'One person deciding a disagreement'],
    'sorun' => [
      'Yazar ile hakem anlaşamadığında kararı bir editör verirse, sistemin bütün ağırlığı tek kişiye biner ve o kişi zamanla sistemin fiilî sahibi hâline gelir. Kararı yazara bırakmak ise hakemliği baştan anlamsız kılar.',
      'If an editor decides when an author and a reviewer disagree, the whole weight of the system rests on one person, who in time becomes its de facto owner. Leaving the decision to the author makes review meaningless from the start.',
    ],
    'yapilan' => [
      'Anlaşmazlık tarafların hiçbirine değil üç bağımsız kişiye götürülüyor. Yazar kendi çalışmasındaki bir rapora itiraz edebiliyor; doğrulanmış hesabı olan herkes savruk ya da kötü niyetli bulduğu bir raporu şikâyet edebiliyor. Oy verenler birbirinin oyunu göremiyor, hangi seçeneğe kaç oy gittiği bile üçüncü oya kadar kapalı; böylece ilk oy sonrakileri sürüklemiyor. Üçüncü oy düştüğünde her şey açılıyor ve kalıcı olarak yayımlanıyor. Geçersiz sayılan rapor silinmiyor, sayfada kalıyor. Dört geçerli oy kullanan kişi yazarlık hakkını kazanıyor.',
      'A disagreement goes to neither party but to three independent people. An author may object to a report on their own work; anyone with a verified account may complain about a report they find careless or made in bad faith. Voters cannot see one another\'s votes, and even the count by option stays sealed until the third vote, so the first does not drag the rest. When the third vote falls everything opens and is published permanently. A report set aside is not deleted; it stays on the page. Whoever casts four valid votes earns the right to submit as an author.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['İlk çalışmaların deneme sırasında yayımlanmış olması', 'The first works having been published during testing'],
    'sorun' => [
      'Sistem kurulurken kuralların işleyip işlemediğini görmenin tek yolu onları çalıştırmaktı. Bu yüzden ilk çalışmalar deneme sırasında yayımlandı ve bugünkü kuralların tamamını karşılamıyor. Bunları örnek alan bir okuyucu, sürecin nasıl işlediği konusunda yanılabilir.',
      'While the system was being built, the only way to see whether its rules worked was to run them. The first works were therefore published during testing and do not satisfy every rule now in force. A reader taking them as exemplary could be misled about how the process actually works.',
    ],
    'yapilan' => [
      'Kısmen kapatıldı. İki seçenek vardı: kayıtları geriye dönük düzeltmek ya da çalışmaları sessizce kaldırmak. İkisi de bilimsel kaydı bozar, üstelik bu sistemin başkalarında kusur saydığı davranışın ta kendisidir. Bunun yerine istisna açıkça tanındı: bu çalışmaların sayfasında, hangi koşulda yayımlandıklarını ve bugünkü sürecin örneği sayılamayacaklarını anlatan kalıcı bir kuruluş dönemi kaydı duruyor. Aynı açıklama yayın ilkelerinde de yazılı. İstisnanın kapsamı ayar dosyasında adıyla sınırlıdır ve genişletilmeyecektir. Kapatılmayan kısım şudur: bu çalışmalar arşivde, bugünkü kurallara göre yayımlanmış çalışmalarla yan yana duruyor ve aradaki farkı yalnızca sayfayı açan okuyucu görüyor; listede bir ayrım işareti henüz yok.',
      'Partly closed. There were two options: to correct the records retrospectively, or to remove the works quietly. Both damage the scholarly record, and both are precisely the conduct this system treats as a fault in others. The exception was acknowledged openly instead: on those works\' pages stands a permanent founding period record setting out the conditions of their publication and stating that they cannot be taken as examples of the present process. The same explanation appears in the editorial policies. The scope of the exception is limited by name in the configuration file and will not be extended. What remains unclosed: these works sit in the archive alongside works published under the present rules, and only a reader who opens the page sees the difference; there is as yet no mark distinguishing them in the listing.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['Kurulun kendisinin ele geçirilmesi', 'Capture of the panel itself'],
    'sorun' => [
      'Bir raporu geçersiz kılma yetkisi üç kişiye verildiğinde, anlaşmalı üç kişi bir hakemi susturabilir. Kurul, çözmek için kurulduğu sorunun kendisi hâline gelebilir.',
      'Once the power to set a report aside rests with three people, three people acting in concert can silence a reviewer. The panel can become the very problem it was created to solve.',
    ],
    'yapilan' => [
      'Kısmen kapatıldı. Oy vermek için doktora belgesinin gerçekten doğrulanmış olması aranıyor; şerhte ve hakemlikte tanınan geçiş dönemi kolaylığı burada uygulanmıyor, böylece sahte hesaplarla kurul kurulamıyor. Taraflar oy veremiyor. Her oy, adı ve gerekçesiyle kalıcı olarak yayımlanıyor; bir kişinin oy geçmişi okunabiliyor. Oylar eşit dağıldığında ağır değil hafif sonuç geçerli sayılıyor. Kapatılmayan kısım şudur: hakem havuzu küçükken birbirini tanıyan üç kişinin aynı yönde oy vermesi hâlâ mümkündür. Düşünülen çözüm, oy verenlerin çalışmanın yazarı ve hedef hakemle ortak yayın geçmişinin de denetlenmesi ve havuz büyüdükçe oy verenlerin sistemce rastgele davet edilmesidir; henüz eklenmedi.',
      'Partly closed. Voting requires a doctoral credential that has actually been verified; the transitional allowance granted for commentary and reviewing does not apply here, so a panel cannot be assembled from false accounts. The parties cannot vote. Every vote is published permanently with its author\'s name and reasoning, and a person\'s voting record can be read. Where votes divide evenly the lighter outcome prevails, not the heavier one. What remains unclosed: while the reviewer pool is small, three people who know one another can still vote the same way. The intended remedy is to check voters for co publication history with both the author and the reviewer at issue, and, as the pool grows, to have the system invite voters at random; this has not yet been added.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['Kişilerin adla ayırt edilmesi', 'Telling people apart by name'],
    'sorun' => [
      'Hakem raporları, kurul oyları ve şerhler adla yayımlanır; kişi sayfaları da bu kayıtları adın sadeleştirilmiş biçimiyle bir araya getirir. Aynı adı taşıyan iki araştırmacının kayıtları böylece tek bir sayfada karışabilir. Bu, yalnızca bir gösterim sorunu değildir: bir kişinin karar dağılımına başka birinin kararları girerse, okuyucu yanlış bir kanaate varır.',
      'Referee reports, panel votes and notes are published under their authors\' names, and person pages gather those records by the simplified form of the name. The records of two researchers sharing a name can therefore be mixed on a single page. This is not merely a display problem: if another person\'s decisions enter someone\'s distribution of decisions, a reader is led to a false judgement.',
    ],
    'yapilan' => [
      'Kısmen kapatıldı. Her yazar için ORCID zorunludur ve kişi sayfasında yazılıdır; okuyucu ayrımı oradan yapabilir. Hesabı olan kişilerde unvan, kurum ve profil resmi de hesaptan gelir ve ayırt etmeyi kolaylaştırır. Kapatılmayan kısım şudur: kayıtlar hâlâ ada göre toplanır, ORCID\'e göre değil. Doğru çözüm, hakem raporunu ve kurul oyunu da yazan kişinin ORCID\'ine bağlamak ve kişi sayfasını ORCID üzerinden kurmaktır; bu, hakemlik akışında ORCID\'i zorunlu kılmayı gerektirir ve henüz yapılmamıştır.',
      'Partly closed. An ORCID is required for every author and is shown on the person page, so a reader can make the distinction there. For people with accounts, the title, institution and profile picture also come from the account and make identification easier. What remains unclosed: records are still gathered by name rather than by ORCID. The right remedy is to bind referee reports and panel votes to the ORCID of whoever wrote them and to build the person page on ORCID; that requires making ORCID mandatory in the reviewing flow, and it has not yet been done.',
    ],
  ],
  [
    'durum' => 'kismi',
    'bas' => ['Beğeni sayısının bir değer ölçüsüne dönüşmesi', 'The like count turning into a measure of worth'],
    'sorun' => [
      'Bir çalışmanın sayfasında duran sayı, ne kadar dikkatli konulursa konulsun, zamanla bir kalite göstergesi gibi okunmaya başlar. Oysa beğeni yalnızca kaç kişinin o çalışmayı listesine aldığını söyler; hakem raporunun, kurul kararının ya da yöntemin yerini tutmaz. Ayrıca sayılabilir her şey gibi şişirilebilir: hesap açmanın maliyeti düşük olduğu sürece bir çalışmanın sayısı yapay olarak yükseltilebilir.',
      'A number sitting on a work\'s page, however carefully it is placed, comes in time to be read as a mark of quality. Yet a like says only how many people added the work to their list; it stands in for neither a reviewer report, nor a panel decision, nor method. And like anything countable it can be inflated: while opening an account is cheap, a work\'s number can be raised artificially.',
    ],
    'yapilan' => [
      'Kısmen kapatıldı. Beğeni hiçbir sıralamaya, onay sayımına ya da hakemlik kaydına girmez; arşiv sıralamalarında "en çok beğenilen" diye bir ölçüt bilerek yoktur. Yalnızca hesabı olan kişi beğenebilir ve bir hesap bir çalışmayı bir kez sayar. Kimin beğendiği hiçbir yerde açık edilmez; bu, beğeninin bir destek gösterisine dönüşmesini önler. Kapatılmayan kısım şudur: sayı yine de sayfada durmaktadır ve okuyucunun onu bir değer ölçüsü sanmasını engelleyecek bir yol yoktur. Sayının tümüyle kaldırılması düşünüldü; okuyucunun "başkaları da bunu izliyor" bilgisine erişmesi ile sayının yanlış okunması arasında, şimdilik ilki yeğlendi ve bu not yazıldı.',
      'Partly closed. A like enters no ranking, no approval count and no reviewing record; there is deliberately no "most liked" criterion among the archive\'s sort options. Only a person with an account can add a work, and one account counts once. Who liked what is nowhere disclosed, which keeps a like from becoming a show of support. What remains unclosed: the number still sits on the page, and there is no way to stop a reader from taking it for a measure of worth. Removing the number altogether was considered; between letting a reader know that others are following the work and the risk of the number being misread, the former was preferred for now, and this note was written.',
    ],
  ],
  [
    'durum' => 'acik',
    'bas' => ['Arşivin yalnızca burada yayımlananları içermesi',
              'The archive holding only what is published here'],
    'sorun' => [
      'Bir araştırmacının çalışmalarının bir bölümü burada, bir bölümü başka dergilerde yayımlanmıştır. Bu sistem yalnızca kendi arşivini gösterdiği için, kişi sayfası o kişinin bilimsel emeğinin tamamını değil bir kesitini gösterir. Aynı sınır kurumlar için de geçerlidir: bir üniversitenin ya da derginin geçmiş sayıları, açık erişimle paylaşılmaya elverişli olsa bile bu arşivde yer alamaz. Bunun sonucu şudur: bir çalışmaya bu sistem üzerinden yapılan atıf, o çalışma burada bulunmadığı için hiçbir yerde sayılmaz ve kayıt eksik kalır.',
      'Part of a researcher\'s work is published here and part of it in other journals. Because this system shows only its own archive, a person page shows a section of that person\'s scholarly labour rather than the whole of it. The same limit applies to institutions: the past issues of a university or a journal cannot appear in this archive even where they are suitable for open access. The consequence is this: a citation made through this system to a work that is not held here is counted nowhere, and the record stays incomplete.',
    ],
    'yapilan' => [
      'Henüz kapatılmadı ve şimdilik kapatılmayacaktır. Doğru çözüm bellidir: yazarın başka yerlerde yayımlanmış çalışmalarını kendi sayfasına, kaynağı ve adresi açıkça yazılmış biçimde ekleyebilmesi; bu kayıtlara sistem içinden yapılan atıfların da sayılması; ve dergilerin, üniversitelerin, enstitülerin kendi sayılarını uygun bir yordamla arşive taşıyabilmesi. Bunun yapılmaması bir tercih değil, bir imkân sorunudur. Böyle bir işlev, üstveri doğrulaması, mükerrer kayıt ayıklaması, telif denetimi ve kalıcı barındırma ister; bunların hiçbiri bugünkü kaynaklarla sürdürülebilir değildir ve yarım yapılmış bir arşiv, hiç yapılmamış bir arşivden daha zararlıdır. Bu maddenin buraya yazılma sebebi, sınırın gizlenmemesidir. Kaynaklar, maddi olduğu kadar insan emeği bakımından da, bu işi hakkıyla yapmaya yeter hâle geldiğinde madde yeniden ele alınacak ve burada bunu okuyacaksınız.',
      'Not yet closed, and it will not be closed for the time being. The right remedy is clear: allowing an author to add to their own page the work they have published elsewhere, with its source and address stated openly; counting citations made from within this system to those records; and enabling journals, universities and institutes to bring their own issues into the archive by a proper procedure. That this has not been done is not a preference but a question of means. Such a facility requires metadata verification, deduplication, copyright checking and permanent hosting; none of these is sustainable with present resources, and a half built archive is more harmful than none. The reason this item is written here is that the limit should not be hidden. When resources, in human labour as much as in money, suffice to do this properly, the item will be taken up again and you will read about it here.',
    ],
  ],
];

/* Ölçüm sonucu eklenen madde: arama, arşiv büyüdükçe yavaşlar.
   Bu bir tahmin değil, ölçülmüş bir sınırdır; bu yüzden sayısıyla
   birlikte yazılıyor. */
$maddeler[] = [
  'durum' => 'acik',
  'bas' => ['Arama, arşiv büyüdükçe yavaşlar', 'Search slows as the archive grows'],
  'sorun' => [
    'Arama her sorguda bütün kayıtları baştan sona tarar; ayrı bir dizin dosyası tutulmaz. Bu, sistemi bir veritabanına bağımlı olmaktan kurtarır ve taşınabilirliği sağlar, ama sonsuza kadar sürmez. Ölçtük: iki bin çalışmada arama yarım saniyenin altında sonuçlanıyor, on bin çalışmada tam metin araması dört saniyeye çıkıyor. Yani sınır uzakta ama vardır ve nerede olduğu bilinmektedir.',
    'Search scans every record from end to end on each query; no separate index file is kept. This frees the system from depending on a database and is what makes it portable, but it does not hold for ever. We measured it: with two thousand works a search completes in under half a second; with ten thousand, a full text search takes four seconds. The limit is far off, but it exists and its location is known.',
  ],
  'yapilan' => [
    'Şimdilik hiçbir şey; arşiv bu sayının çok altında. Sınıra yaklaşıldığında yapılacak olan bellidir: her çalışma kaydedilirken küçük bir arama dizini dosyası güncellenir ve arama önce ona bakar. Bu, veritabanı gerektirmez ve taşınabilirliği bozmaz. Bu maddenin burada durmasının sebebi, sınırın sessizce aşılıp aramanın bir gün ağırlaşmasını önlemektir.',
    'Nothing for now; the archive is far below that figure. What will be done as the limit approaches is already clear: a small search index file will be updated as each work is saved, and search will consult it first. This needs no database and does not break portability. This entry stands here so that the limit is not crossed quietly and search does not simply become sluggish one day.',
  ],
];

$durumAd = [
  'kapali' => [['Kapatıldı', 'Closed'], 'rz-yes'],
  'kismi'  => [['Kısmen kapatıldı', 'Partly closed'], 'rz-kut'],
  'acik'   => [['Henüz açık', 'Still open'], 'rz-kir'],
];
$say = ['kapali' => 0, 'kismi' => 0, 'acik' => 0];
foreach ($maddeler as $m) { $say[$m['durum']]++; }

$ekBas = <<<CSS
<style>
/* Her açık bir kart. Kartın kendisi dizgeden gelir; burada yalnızca
   maddeler arasındaki boşluk, başlık şeridi ve durumun kenarlığa
   yansıması var. Kenarlık rengi, rozetin söylediğini uzaktan bakınca
   da söyler. */
/* Tamga deseni: ana sayfadaki ile aynı dosya, aynı ölçek, aynı
   soluk. Opaklık .06'da tutuldu; daha koyusu başlık metninin
   karşıtlığını düşürür ve WCAG kazanımını geri verir. */
.ac-bas{position:relative;isolation:isolate}
.ac-bas::before{content:"";position:absolute;inset:0;z-index:-1;pointer-events:none;
  background:url("/k/desen.svg") repeat;background-size:336px 112px;opacity:.06}
.ac-sayac{margin-bottom:var(--b-6)}
.ac-m{margin-bottom:var(--b-4);scroll-margin-top:calc(var(--ust) + var(--b-5))}
.ac-m.kapali{border-color:var(--yesil-cizgi)}
.ac-m.acik{border-color:var(--kirmizi-cizgi)}
.ac-m-bas{margin-bottom:var(--b-3)}
.ac-m h2{font-size:var(--y-6);margin:0;flex:1 1 auto}
/* Sorun ve ne yapıldı ayraçları: bir bölüm başlığı değil, kartın
   içindeki etikettir. Bu yüzden küçük ve arayüz yüzüyle yazılır. */
.ac-m h3{font-family:var(--ui);font-size:var(--y-1);font-weight:700;letter-spacing:.13em;text-transform:uppercase;
  color:var(--metin-2);margin:var(--b-4) 0 var(--b-1)}
.ac-m p{margin:0;font-size:var(--y-4)}
.ac-son{margin-top:var(--b-7);border-top:1px solid var(--cizgi);padding-top:var(--b-5);
  font-size:var(--y-3);color:var(--metin-2)}

/* Güven işaretleri listesi. Her satır bir işaret: adı, durumu, varsa
   tarihi ve bir cümlelik açıklaması. Durum rozeti adın YANINDA durur,
   sonunda değil: okurun aradığı şey "var mı yok mu" sorusudur ve o
   soru satırın başında yanıtlanmalıdır. */
.guven-liste{list-style:none;margin:var(--b-4) 0 var(--b-4);padding:0;display:grid;gap:var(--b-3)}
.guven-sat{display:grid;grid-template-columns:auto auto 1fr;gap:var(--b-2) var(--b-3);
  align-items:center;padding-bottom:var(--b-3);border-bottom:1px solid var(--cizgi)}
.guven-sat:last-child{border-bottom:0;padding-bottom:0}
.guven-ad{font-weight:700;font-size:var(--y-4)}
.guven-tarih{font-family:var(--mono);font-size:var(--y-2);color:var(--metin-2)}
.guven-sat small{grid-column:1 / -1;font-size:var(--y-3);display:block}
@media (max-width:520px){
  .guven-sat{grid-template-columns:1fr auto}
  .guven-tarih{grid-column:1 / -1}
}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Bilimsel açıklar', 'Weaknesses of this system'),
    'yol'    => '/acikliklar.php',
    'aciklama' => k_c(
        'Kutadgu\'nun bilimsel zayıf noktaları, her biri için ne yapıldığı ve hangilerinin hâlâ açık olduğu.',
        'The scholarly weaknesses of Kutadgu, what was done about each, and which remain open.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<?php /* Sayfa başlığındaki tamga deseni. Ana sayfada aynı desen aynı
         soluklukla kullanılıyor; kimliğin iç sayfalarda da sürmesi
         için buraya kondu. Desen bir ŞEY SÖYLEMEZ: arkada durur,
         okunmaz, ekran okuyucuya hiç ulaşmaz (zemin görüntüsüdür,
         belge ağacında yoktur). Bu sayfanın konusu sistemin kendi
         kusurları olduğu için, önüne bir iddia taşıyan hiçbir işaret
         konmadı; konsaydı sayfanın tonunu bozardı. */ ?>
<section class="sayfa-bas ac-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('İç değerlendirme', 'Self assessment') ?></span>
      <h1><?= k_c('Bu sistemin bilimsel açıkları', 'The scholarly weaknesses of this system') ?></h1>
      <p><?= k_c(
      'Bir sistemin şeffaflık iddiası varsa, ilk şeffaf olacağı şey kendi kusurlarıdır. Aşağıda Kutadgu\'nun zayıf noktaları, her biri için ne yapıldığı ve hangilerinin hâlâ kapatılmadığı yazılıdır. Kapatılmamış olanlar bu listeden çıkarılmaz; kapatıldıklarında burada bunu okuyacaksınız.',
      'If a system claims transparency, the first thing it must be transparent about is its own faults. Below are the weaknesses of Kutadgu, what was done about each, and which remain unclosed. Those that remain are not removed from this list; when they are closed, you will read that here.'
    ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic">

    <div class="satir ac-sayac">
      <span class="rz rz-yes"><?= (int)$say['kapali'] ?> <?= k_c('kapatıldı', 'closed') ?></span>
      <span class="rz rz-kut"><?= (int)$say['kismi'] ?> <?= k_c('kısmen kapatıldı', 'partly closed') ?></span>
      <span class="rz rz-kir"><?= (int)$say['acik'] ?> <?= k_c('henüz açık', 'still open') ?></span>
    </div>

    <?php foreach ($maddeler as $i => $m): $d = $durumAd[$m['durum']]; ?>
    <article class="kart ac-m <?= k_esc($m['durum']) ?>" id="m<?= (int)$i + 1 ?>">
      <div class="satir ac-m-bas">
        <h2><?= str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) ?>. <?= k_esc(k_t(['tr' => $m['bas'][0], 'en' => $m['bas'][1]])) ?></h2>
        <span class="rz <?= k_esc($d[1]) ?>"><?= k_esc(k_t(['tr' => $d[0][0], 'en' => $d[0][1]])) ?></span>
      </div>
      <h3><?= k_c('Sorun', 'The problem') ?></h3>
      <p class="metin-sonuk"><?= k_esc(k_t(['tr' => $m['sorun'][0], 'en' => $m['sorun'][1]])) ?></p>
      <h3><?= k_c('Ne yapıldı', 'What was done') ?></h3>
      <p><?= k_esc(k_t(['tr' => $m['yapilan'][0], 'en' => $m['yapilan'][1]])) ?></p>
    </article>
    <?php endforeach; ?>

    <?php /* GÜVEN İŞARETLERİ.
             Bu sayfa sistemin kendi kusurlarını yazıyor; bir kurumun
             ilk soracağı dış işaretlerin de burada olması gerekir.
             Başka bir sayfada olsaydı, "iyi haberler orada, kötü
             haberler burada" gibi durur ve okurun ikisini yan yana
             görmesi engellenirdi.

             Liste ayar.php'den gelir ve ALINMAYANLAR DA BASILIR.
             Eksik olanı listeden düşürmek sayfayı temiz, okuru yanlış
             bilgilendirilmiş bırakır. Alınmış bir işaret ise tarihsiz
             basılamaz: tarih, iddiayı denetlenebilir yapan şeydir. */
          $gi = tg_guven_isaretleri(); $gs = tg_guven_sayim(); ?>
    <article class="kart ac-m" id="guven">
      <div class="satir ac-m-bas">
        <h2><?= k_c('Güven işaretleri', 'Marks of standing') ?></h2>
        <span class="rz <?= $gs['alindi'] > 0 ? 'rz-yes' : 'rz-kir' ?>"><?= (int)$gs['alindi'] ?>/<?= count($gi) ?> <?= k_c('alındı', 'obtained') ?></span>
      </div>
      <p class="metin-sonuk"><?= k_c(
        'Bir kurum bir yayın sistemine bakarken önce bu dış işaretlere bakar. Aşağıda hangilerinin alındığı, hangilerinin alınmadığı yazılıdır. Alınmamış olanlar listeden çıkarılmaz; alındıklarında burada tarihiyle görünürler. Sahip olmadığımız bir işareti basmak, bu sistemin verebileceği en pahalı yanlış bilgi olurdu.',
        'An institution looking at a publishing system looks first at these external marks. Below is which of them have been obtained and which have not. Those not obtained are not removed from the list; when they are obtained they appear here with the date. Displaying a mark we do not hold would be the most costly piece of misinformation this system could give.'
      ) ?></p>
      <ul class="guven-liste">
        <?php foreach ($gi as $g): ?>
        <li class="guven-sat guven-<?= k_esc($g['durum']) ?>">
          <span class="guven-ad"><?php if ($g['url'] !== ''): ?><a href="<?= k_esc($g['url']) ?>" rel="noopener" target="_blank"><?= k_esc(k_t($g)) ?></a><?php else: ?><?= k_esc(k_t($g)) ?><?php endif; ?></span>
          <span class="rz <?= $g['durum'] === 'alindi' ? 'rz-yes' : ($g['durum'] === 'basvuruldu' ? 'rz-kut' : 'rz-kir') ?>"><?= k_esc(tg_guven_durum_ad($g['durum'], $en)) ?></span>
          <?php if ($g['durum'] === 'alindi'): ?>
          <span class="guven-tarih"><?= k_esc($g['tarih']) ?><?= $g['no'] !== '' ? ' · ' . k_esc($g['no']) : '' ?></span>
          <?php endif; ?>
          <small class="metin-sonuk"><?= k_esc(k_t(['tr' => $g['ack'][0], 'en' => $g['ack'][1]])) ?></small>
        </li>
        <?php endforeach; ?>
      </ul>
      <p><?= k_c(
        'Lisans (' . k_esc((string)tg_ayar('lisans', 'CC BY 4.0')) . '), yayın kurulu, iletişim adresi ve yayın ilkeleri bu sistemde baştan beri açıktır ve dizinlerin aradığı biçimde makine tarafından okunabilir. Yukarıdaki işaretler ise dışarıdan verilir; verilmeden yazılmazlar.',
        'The licence (' . k_esc((string)tg_ayar('lisans', 'CC BY 4.0')) . '), the editorial board, the contact address and the editorial policies have been open in this system from the start and are machine readable in the form indexes look for. The marks above, however, are conferred from outside; they are not written down before they are conferred.'
      ) ?></p>
    </article>

    <p class="ac-son"><?= k_c(
      'Bu listenin kısalmasını değil doğru kalmasını istiyorum. Gördüğünüz ve burada yazmayan bir açık varsa lütfen bildirin: bir kusuru göstermek, onu gizlemekten her zaman daha değerlidir.',
      'I do not want this list to grow shorter but to stay truthful. If you see a weakness that is not written here, please tell me: pointing out a fault is always worth more than concealing it.'
    ) ?> <a href="<?= k_esc(k_bag('/iletisim.php')) ?>"><?= k_c('İletişim', 'Contact') ?></a></p>

   </div>
   <?php
   $ynBolum = [];
   foreach ($maddeler as $bi => $bm) {
       $ynBolum[] = ['k' => 'm' . ($bi + 1),
                     'tr' => str_pad((string)($bi + 1), 2, '0', STR_PAD_LEFT) . '. ' . $bm['bas'][0],
                     'en' => str_pad((string)($bi + 1), 2, '0', STR_PAD_LEFT) . '. ' . $bm['bas'][1]];
   }
   $ynBolum[] = ['k' => 'guven', 'tr' => 'Güven işaretleri', 'en' => 'Marks of standing'];
   echo k_belge_yan($ynBolum, [
     ['tr' => 'Durum', 'en' => 'Standing',
      'ic' => (int)$say['kapali'] . ' ' . k_c('kapatıldı', 'closed') . ', '
            . (int)$say['kismi'] . ' ' . k_c('kısmen kapatıldı', 'partly closed') . ', '
            . (int)$say['acik'] . ' ' . k_c('henüz açık', 'still open') . '.'],
     ['tr' => 'Güven işaretleri', 'en' => 'Marks of standing',
      'ic' => (int)tg_guven_sayim()['alindi'] . '/' . count(tg_guven_isaretleri()) . ' '
            . k_c('alındı. Alınmayanlar listeden çıkarılmaz.', 'obtained. Those not obtained are not removed from the list.')
            . '<br><a href="#guven">' . k_c('Listeyi gör', 'See the list') . '</a>'],
     ['tr' => 'Bir açık gördünüz mü', 'en' => 'Seen a weakness',
      'ic' => k_c('Burada yazmayan bir kusur görürseniz yazın; liste kısalsın diye değil, doğru kalsın diye tutuluyor.',
                  'If you see a fault that is not written here, tell us; the list is kept to stay truthful, not to grow shorter.')
            . '<br><a href="' . k_esc(k_bag('/iletisim.php')) . '">' . k_c('İletişim', 'Contact') . '</a>'],
   ]);
   ?>
  </div>
</section>
<?php k_son(); ?>
