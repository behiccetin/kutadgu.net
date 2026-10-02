<?php
/* =====================================================================
   KUTADGU - Bildiri / Declaration
   Sistemin kuruluş metni ve anayasası. İki dilli.
   ?parca=1 ile yalnızca gövde döner: ilk ziyarette tam sayfa katman olarak
   gösterilir; bir kez okunur, bir daha rahatsız etmez.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$parca = isset($_GET['parca']) && $_GET['parca'] === '1';
$ana   = (string)tg_ayar('ana_site', '');
$anaAd = (string)tg_ayar('ana_site_ad', '');

/* ---------- Bildiri gövdesi ---------- */
/* $ilk    : ilk ziyaret katmanında mı çiziliyor
   $baslik : başlık bloğu burada çizilsin mi. Sayfanın kendisi lacivert
             bir başlık şeridi kullandığı için orada kapatılır; katmanda
             ise şerit olmadığından açık kalır.                          */
function bildiri_govde(bool $ilk = false, bool $baslik = true): void {
    $ana   = (string)tg_ayar('ana_site', '');
    $anaAd = (string)tg_ayar('ana_site_ad', '');
    ?>
    <article class="bld">
      <?php if ($baslik): ?>
      <p class="bld-ust"><?= k_c('Kuruluş metni', 'Founding text') ?></p>
      <h1><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></h1>
      <p class="bld-alt"><?= k_c(
        'Bu sistem neden var, kime aittir, neye söz verir.',
        'Why this system exists, whom it belongs to, what it promises.'
      ) ?></p>
      <?php endif; ?>

      <p class="bld-giris"><?= k_c(
        'Bilgi, saklandığında değil paylaşıldığında çoğalır. Bir bulgunun değeri, kaç kişinin ona ulaşabildiğiyle ölçülür; kaç kişinin erişimden yoksun kaldığıyla değil. Kutadgu\'yu bu yalın kanaatin üzerine kurduk. Aşağıdaki metin bir buyruk değil, bir taahhüttür: sistemin neye söz verdiğini ve bu sözü kim yönetirse yönetsin nasıl koruyacağını açıklar.',
 'Knowledge multiplies when it is shared, not when it is withheld. The worth of a finding is measured by how many people can reach it, not by how many are kept from it. We founded Kutadgu on that plain conviction. What follows is not a command but an undertaking: it sets out what the system promises, and how that promise is to be kept whoever comes to administer it.'
      ) ?></p>

      <h2 id="b-amac"><?= k_c('Amaç', 'Purpose') ?></h2>
      <p><?= k_c(
        'Bu sistemi salt bilim üretilebilsin diye kurduk; başka hiçbir amaç gözetmedik. Kutadgu bir kuruma, bir çevreye ya da bir görüşe hizmet etmek üzere tasarlanmamıştır. Burada bir çalışmanın ölçüsü yazarının unvanı ya da bağlı olduğu kurum değildir; yönteminin sağlamlığı, savının izlenebilirliği ve kaynaklarının denetlenebilirliğidir. Bu ölçüt, kurucuları da dâhil olmak üzere herkes için aynıdır.',
        'We founded this system so that scholarship could be produced, and we pursued no other aim. Kutadgu was not designed to serve an institution, a circle or a doctrine. Here a work is measured not by its author\'s title or affiliation, but by the soundness of its method, the traceability of its argument and the verifiability of its sources. That measure applies to everyone alike, its founders included.'
      ) ?></p>

      <h2 id="b-kim"><?= k_c('Bu sistem kime aittir', 'Whom this system belongs to') ?></h2>
      <p><?= k_c(
        'Kutadgu <b>satılık değildir</b> ve bu niteliğini sürdürmesi bu metnin en temel şartıdır. Sistem, kurucularının mülkü olarak değil, ortak bir emanet olarak düşünülmüştür: onu insanlığa açık bir bilgi kütüphanesi olarak bırakmak üzere kurduk.',
        'Kutadgu <b>is not for sale</b>, and its remaining so is the most basic condition of this text. The system was conceived not as the property of its founders but as a common trust: we founded it in order to leave it to humanity as an open library of knowledge.'
      ) ?></p>
      <p><?= k_c(
        'Bugün bu sistem, kurucularından birinin gönüllü olarak işlettiği tek bir sunucuda barındırılıyor. Zamanla bakım, barındırma ve geliştirme giderleri doğacaktır. Bu giderleri karşılamak ya da sistemi geliştirmek amacıyla Kutadgu, kamu ya da özel bir kuruma devredilebilir. Ancak devir, ancak ve yalnızca aşağıdaki koşulların tamamının kabulüyle mümkündür. Bu koşullar, sistem işlediği sürece onun <b>anayasası</b> olarak yürürlükte kalır.',
        'Today this system runs on a single server operated, on a voluntary basis, by one of its founders. In time, costs of maintenance, hosting and development will arise. To meet those costs, or to develop the system further, Kutadgu may be transferred to an institution, public or private. Such a transfer is possible only on acceptance of every one of the conditions below. These conditions remain in force as the <b>constitution</b> of the system for as long as it operates.'
      ) ?></p>

      <p><?= k_c(
        'Bu koşulların yazılı olmasının nedeni kimseye duyulan bir güvensizlik değildir. Açık erişimle kurulan pek çok yayın girişiminin zamanla ücretli hâle geldiği, el değiştirdikçe amacından uzaklaştığı bilinen bir olgudur. Aşağıdaki maddeler, sistemin sözünün kurucularından sonra da ayakta kalabilmesi için konmuştur.',
        'These conditions are set down not out of mistrust of anyone. It is a familiar fact that many publishing ventures founded on open access have in time become paid, and have drifted from their purpose as they changed hands. The articles below stand so that the system\'s promise may outlive its founders.'
      ) ?></p>

      <ol class="bld-madde">
        <li><b><?= k_c('Devralan kurum sistemi satamaz.', 'The receiving institution may not sell the system.') ?></b>
          <?= k_c('Ne bütün olarak ne parça olarak, ne doğrudan ne dolaylı yoldan.', 'Neither in whole nor in part, neither directly nor indirectly.') ?></li>
        <li><b><?= k_c('Sistem ücretli hâle getirilemez.', 'The system may not be made paid.') ?></b>
          <?= k_c('Ne yazardan işlem ücreti, ne okurdan abonelik, ne kurumdan erişim bedeli istenebilir.', 'No processing charge from authors, no subscription from readers, no access fee from institutions.') ?></li>
        <li><b><?= k_c('Hakemli çalışmalar, proje sürdüğü sürece herkesin erişimine ücretsiz açık kalır.', 'Peer reviewed work stays open to everyone free of charge for as long as the project continues.') ?></b>
          <?= k_c('Bu hüküm geriye dönük olarak da bağlayıcıdır.', 'This provision binds retrospectively as well.') ?></li>
        <li><b><?= k_c('Sistemin yapısı hiçbir çıkar doğrultusunda değiştirilemez.', 'The structure of the system may not be altered to serve any interest.') ?></b>
          <?= k_c('Açık hakemlik, açık erişim ve alan bağımsızlığı ilkelerinden geri adım atılamaz.', 'There can be no retreat from open review, open access and disciplinary independence.') ?></li>
        <li><b><?= k_c('Reklam barındırılamaz.', 'No advertising may be carried.') ?></b>
          <?= k_c('Okurun dikkati bir gelir kalemi olarak görülemez.', 'The reader\'s attention may not be treated as a source of revenue.') ?></li>
        <li><b><?= k_c('Bu bildiri ve kurucu kurulun adları kaldırılamaz.', 'This declaration and the names of its founding board may not be removed.') ?></b>
          <?= k_c('Sistem el değiştirse dahi bu metin ve altındaki adlar yerinde kalır; armağanın kimlerden geldiği kaydın parçasıdır.', 'Even if the system changes hands, this text and the names beneath it remain in place; the record includes whom the gift came from.') ?></li>
        <li><b><?= k_c('Sistemin adı ve amaçları değiştirilemez.', 'The name and the purposes of the system may not be changed.') ?></b>
          <?= k_c('Kutadgu adı ve bu bildiride sayılan amaçlar sistemin kimliğidir; devirle, birleşmeyle ya da yeniden yapılanmayla dahi değiştirilemez.', 'The name Kutadgu and the purposes set out in this declaration are the identity of the system; they cannot be altered by transfer, by merger or by restructuring.') ?></li>
      </ol>


      <h2 id="b-sorumluluk"><?= k_c('Sorumluluk', 'Responsibility') ?></h2>
      <p><?= k_c(
        'Bu sistemde yayımlanan her çalışmanın bilimsel sorumluluğu yazarına aittir. Sistem, bir çalışmanın doğru olduğunu değil, hangi süreçten geçtiğini güvence altına alır: kimin değerlendirdiği, ne dediği, hangi kararı verdiği ve yazarın buna ne yanıt verdiği açıkta durur. Okuyucu, bir çalışmayı yalnızca sonucuna bakarak değil, ona nasıl varıldığına bakarak da okuyabilir.',
        'Scientific responsibility for every work published here rests with its author. The system does not warrant that a work is correct; it warrants which process the work went through: who assessed it, what they said, what decision they reached and how the author replied. A reader may judge a work not only by its conclusion but by the path taken to it.'
      ) ?></p>
      <p><?= k_c(
        'Sistemin sorumluluğu ise kaydı eksiksiz tutmaktır. Yayımlanmış bir metin sessizce değiştirilmez; düzeltilirse düzeltmenin kendisi de kayda geçer, geri çekilirse metin kaldırılmaz, geri çekildiği yazılır. Bir kaydın silinmesi, o kaydın hiç var olmamış gibi görünmesine yol açar; bu sistem bunu yapmaz.',
        'The system\'s own responsibility is to keep the record complete. A published text is never altered in silence; if it is corrected, the correction itself is recorded, and if it is retracted, the text is not taken down, the retraction is written on it. Deleting a record makes it appear never to have existed; this system does not do that.'
      ) ?></p>

      <h2 id="b-dil"><?= k_c('Dil üzerine', 'On language') ?></h2>
      <p><?= k_c(
        'Bu sistem Türkçe ve İngilizce yayımlar. Türkçe burada bir yerellik değil, bir bilim dilidir: bir kavramı kendi dilinde kuramayan bir topluluk, o kavramı yalnızca ödünç alır. İngilizce ise bir üstünlük değil, bir ulaşım yoludur; dünyanın geri kalanıyla konuşmanın bugünkü ortak zeminidir. İkisi birlikte durur, biri ötekinin yerine geçmez.',
        'This system publishes in Turkish and in English. Turkish here is not a provincialism but a language of science: a community that cannot form a concept in its own tongue only ever borrows it. English is not a mark of rank but a route; it is the common ground on which one speaks with the rest of the world today. The two stand together, neither replacing the other.'
      ) ?></p>
      <p><?= k_c(
        'Makine çevirisi bir yayın değil, bir okuma yardımıdır. Bir çalışmanın çevirisi, yazarı görüp onaylamadıkça o çalışmanın kendisi sayılmaz. Yeni bir dil eklemek yapıda tek satırlık bir iştir; asıl iş, o dilde bir metnin arkasında durabilmektir.',
        'Machine translation is a reading aid, not a publication. A translation of a work does not count as the work itself unless the author has seen and approved it. Adding a language is a single line in the structure; the real work is being able to stand behind a text in that language.'
      ) ?></p>
      <p><?= k_c(
        'Hakem sürecindeki bir metnin çeviri için dışarıya gönderilmesi konusunda önceki söz mutlaktı: gönderilmez deniyordu. O söz, hakemin çalışmayı kendi dilinde okuyabilmesini de imkânsız kıldığı için tutulamayacak bir sözdü ve düzeltiliyor. Yeni kural şudur: <b>hakem sürecindeki bir metin yalnızca yazarın açık onayıyla</b> ve yalnızca o çalışma için seçilmiş çeviri motoruna gönderilir. Yazar onay vermezse çalışma yalnızca kendi dilinde ve köprü dilinde değerlendirilir; bu bir kusur sayılmaz. Gönderilen her metin, hangi motora ve hangi tarihte gönderildiğiyle birlikte kayda geçer. Motoru sağlayan taraf, metni kendi modelini eğitmek için kullanamayacağını yazılı olarak kabul etmedikçe bu yola hiç başvurulmaz.',
        'The earlier undertaking on sending a text under review out for translation was absolute: it said such a text is never sent. That undertaking also made it impossible for a reviewer to read a work in their own language, so it was a promise that could not be kept, and it is being corrected. The new rule is this: <b>a text under review is sent only with the author\'s express consent</b>, and only to the translation engine chosen for that work. If the author does not consent, the work is assessed only in its own language and in the bridge language; that is not counted against it. Every text sent is recorded together with the engine it went to and the date it went. This route is not taken at all unless the provider of the engine has accepted in writing that the text may not be used to train its model.'
      ) ?></p>
      <p><?= k_c(
        'Bir dilin sisteme girmesi iki şeye bağlıdır: o dile çevirecek bir motor ve onun masrafı. İkisini de bir dil destekçisi üstlenebilir; bir kurum ya da bir kişi. Destekçinin adı, o dilin sayfalarında kalıcı olarak ve o dilde yazılı bir teşekkürle durur. Buradaki sınır ötekilerle aynıdır ve aynı sertliktedir: <b>dil desteği hiçbir editöryal etki taşımaz.</b> Bir dilin destekçisi, o dildeki bir çalışmanın kabulüne, hakem seçimine ya da yayın sırasına karışamaz. Neyin çevrildiği, kimin karşıladığı ve ne harcandığı herkese açık olarak yazılır.',
        'A language entering the system depends on two things: an engine that translates into it, and the cost of that engine. A language sponsor, an institution or a person, may take on both. The sponsor\'s name stands permanently on the pages of that language, with a note of thanks written in that language. The boundary here is the same as the others and just as firm: <b>sponsoring a language carries no editorial influence.</b> A language\'s sponsor cannot affect the acceptance of a work in that language, the choice of reviewers or the order of publication. What was translated, who paid for it and how much was spent are all written openly.'
      ) ?></p>
      <p><?= k_c(
        'Bugünkü iki dil bir ilke değil, bir kısıttır. Doğrusu, bir çalışmanın yazarının düşündüğü dilde gelmesidir. İnsan kendi ana dilinde daha ince düşünür ve daha doğru anlatır; yabancı bir dilde yazmak zorunda kalan araştırmacı çoğu zaman söylemek istediğini değil, söyleyebildiğini yazar. Herkesten tek bir dilde yazmasını istemek, bilginin kendisini o dilin sınırına indirir.',
        'The two languages of today are a constraint, not a principle. What is right is for a work to arrive in the language its author thinks in. A person thinks more finely and states things more exactly in their mother tongue; a researcher obliged to write in a foreign language often writes not what they meant to say but what they were able to say. To require everyone to write in a single language is to reduce knowledge itself to the limits of that language.'
      ) ?></p>
      <p><?= k_c(
        'Bunun somut bir karşılığı vardır. Bir dilin sözvarlığı, o dili konuşanların ayırt etmek zorunda kaldığı şeylerden oluşur. Fincede karın ve buzun hâlleri için kırk dolayında ayrı sözcük sayılır: <i>hanki</i> kayak yapmaya yetecek düzgün kar örtüsüdür, <i>kinos</i> rüzgârın yığdığı küme, <i>tykky</i> ağaçlara donmuş kar, <i>sohjo</i> kar ile suyun karışımı, <i>loska</i> ona çamurun da karıştığı hâl. Kar hidrolojisi ya da ormanın taşıdığı kar yükü üzerine çalışan bir araştırmacı bu ayrımları hazır bulur. Aynı araştırmacı İngilizce yazarken ya her seferinde bir tanım cümlesi kurar, ya yeni bir terim uydurur, ya da ayrımı düşürür. Türkçenin de başka dillerde karşılığı olmayan ayrımları vardır; en belirgini, konuşanın gördüğü ile duyduğunu bir ekle ayıran geçmiş zamandır. <i>Yağdı</i> ile <i>yağmış</i> arasındaki fark, bilimin gözlem ile aktarım arasında tutmak zorunda olduğu farkın ta kendisidir.',
        'There is a concrete side to this. A language\'s vocabulary is made of the things its speakers have had to tell apart. Finnish counts some forty separate words for states of snow and ice: <i>hanki</i> is an even layer deep enough to ski on, <i>kinos</i> a drift piled by the wind, <i>tykky</i> snow frozen onto trees, <i>sohjo</i> the mixture of snow and water, <i>loska</i> that same mixture with mud in it. A researcher working on snow hydrology, or on the snow load a forest carries, finds these distinctions ready to hand. Writing in English, the same researcher either builds a defining clause each time, or coins a term, or lets the distinction go. Turkish has distinctions of its own that other languages lack; the clearest is a past tense marking, in a single suffix, whether the speaker witnessed the event or was told of it. The difference between <i>yağdı</i> and <i>yağmış</i>, snow seen falling and snow reported to have fallen, is precisely the difference science must hold between observation and testimony.'
      ) ?></p>
      <p><?= k_c(
        'Bundan çıkan sonuç, bir düşüncenin başka bir dilde kurulamayacağı değildir; kurulabilir, ama daha pahalıya kurulur, ve pahalıya kurulan az kurulur. Bir bilim topluluğu bütün üyelerini tek bir dile geçmeye zorladığında yalnızca çeviri masrafını ödemez; o dilde ucuz olmayan ayrımların bir kısmından da sessizce vazgeçer. Kaybedilen bir üslup inceliği değil, bir ayırt etme gücüdür. Her dile açılmanın burada bir süs değil bir gereklilik sayılmasının sebebi budur.',
        'It does not follow that a thought cannot be formed in another language. It can, but it costs more to form, and what costs more gets said less. When a scholarly community requires all its members to move to a single language, it does not only pay the price of translation; it also gives up, quietly, some of the distinctions that are not cheap in that language. What is lost is not a nicety of style but a power to tell things apart. This is why opening to every language is treated here as a requirement and not an ornament.'
      ) ?></p>
      <p><?= k_c(
        'Bu yüzden sistem, olanak bulduğunda hangi dilde yazılmış olursa olsun çalışma kabul edecek ve öteki dillere makine çevirisiyle aktaracaktır. Kayıt yine yazarın yazdığı dildeki metindir; çeviri onun yerine geçmez, yalnızca başka bir dildeki okura kapıyı açar. Bunun bugün yapılamamasının sebebi bir tercih değil, iki somut sınırdır. Birincisi maddidir: çeviri katmanı bir maliyet getirir ve bu sistem hiçbir masrafını okura ya da yazara yıkmayacağına göre, karşılayamadığı şeyi vaat etmemeyi yeğler. İkincisi hakemliktir: burada değerlendirme açıktır ve bir çalışmanın arkasında durabilmek, o dili okuyan bir hakemin bulunmasını gerektirir. İki sınır da zamanla aşılabilir. Aşıldıkça yayımlanan dil sayısı artar; ilke aynı kalır.',
        'The system will therefore accept work in whatever language it is written in, as soon as it is able to, and carry it into other languages by machine translation. The record remains the text in the language its author wrote it in; a translation does not take its place, it only opens the door to a reader in another language. That this cannot be done today is not a preference but two concrete limits. The first is material: a translation layer costs money, and since this system will not push any of its costs onto readers or authors, it prefers not to promise what it cannot pay for. The second is review: assessment here is open, and standing behind a work requires a reviewer who reads the language it is written in. Both limits can be overcome in time. As they are, the number of languages published grows; the principle stays the same.'
      ) ?></p>

      <h2 id="b-ad"><?= k_c('Adın ve tamganın anlamı', 'The meaning of the name and the tamga') ?></h2>
      <p><?= k_c(
        'Kutadgu adını <i>Kutadgu Bilig</i>den alır: <b>kut veren, insanı mutluluğa eriştiren bilgi</b>. Yusuf Has Hacib\'in bin yıl önce yazdığı o kitapta bilgi, biriktirilen bir mülk değil, sahibini ve çevresini iyi kılan bir yetidir. Bu sistemin adı bir gönderme değil, bir ölçüdür: burada yayımlanan bilginin ölçüsü, kaç kişinin ona ulaşabildiğidir.',
        'Kutadgu takes its name from the <i>Kutadgu Bilig</i>: <b>the knowledge that brings fortune, that leads a person to wellbeing</b>. In that book, written by Yusuf Has Hacib a thousand years ago, knowledge is not property to be hoarded but a capacity that makes its holder and those around them better. The name is not an allusion but a measure: the measure of knowledge published here is how many people can reach it.'
      ) ?></p>
      <p><?= k_c(
        'Adın Türkçe olmasının bir iddiası yoktur. Her şeyin bir dilde adlandırılması gerekir ve tarafsız bir dil yoktur; adını kendi dilinden koymak, herkesin kendi dilinde yaptığı olağan şeydir. Bu sistem başka bir dilde adlandırılsaydı da aynı sistem olurdu.',
        'There is no claim in the name being Turkish. Everything must be named in some language and there is no neutral one; naming a thing in your own tongue is the ordinary thing anyone does in theirs. Had this system been named in another language it would be the same system.'
      ) ?></p>
      <p><?= k_c(
        'Bu sistem Türkçe konuşan birkaç kişinin elinden çıktı ve karşılıksız bırakıldı. Buna armağan demek yanlış değildir, yeter ki armağanın kimden kime gittiği doğru anlaşılsın: veren bir millet ve alan başka bir millet yoktur. Ortak bir hazine vardır ve her topluluk oraya kendi getirebildiğini koyar. Bir armağan, vereni alanın üstüne koyduğu anda armağan olmaktan çıkar.',
        'This system came from the hands of a few people who speak Turkish, and it was released without conditions. To call it a gift is not wrong, provided the direction of the gift is understood: there is no nation giving and no other nation receiving. There is a common store, and each community puts into it what it is able to bring. A gift stops being a gift the moment it sets the giver above the receiver.'
      ) ?></p>
      <p><?= k_c(
        'Bir topluluğun oraya koyabileceği şey, kendi dilinin ve geleneğinin taşıdığı ayrımlardır; bunun ne demek olduğunu yukarıda, dil üzerine yazdığımız yerde anlattık. Bizim koyabildiğimiz, bin yıllık bir kitaptan alınmış tek bir sözcüktür: <b>kut</b>. Bilimin olağan sözvarlığında, yalnızca doğru olan bilgi ile insanın hayatını iyi kılan bilgiyi ayıran bir sözcük yoktur. Türkçede vardır. Bu sistemin adı odur, ölçüsü de odur.',
        'What a community has to put there is the distinctions its language and its tradition carry; what that means is set out above, in the section on language. What we were able to bring is a single word taken from a thousand year old book: <b>kut</b>. The ordinary vocabulary of science has no word that separates knowledge which is merely true from knowledge that makes a person\'s life better. Turkish has one. That is the name of this system, and it is also its measure.'
      ) ?></p>
      <p><?= k_c(
        'Bilimde bir milletin ötekine üstünlüğü yoktur. Bir çalışmanın değeri, kimin elinden çıktığına değil, kime yaradığına bakılarak ölçülür. Adın Türkçe olması bu ölçüyü değiştirmez; yalnızca ona bir ad verir.',
        'In science no nation stands above another. The worth of a work is measured by whom it serves, not by whose hands it came from. That the name is Turkish does not change that measure; it only gives it a name.'
      ) ?></p>
      <p><?= k_c(
        'Tamga, bu sistemde her çalışmaya verilen kalıcı kimliktir: <code>KTG-YYYY-NNNNN-C</code>. Sondaki hane bir denetim hanesidir; kodun yanlış yazıldığı, tek bir harfi bile değişse anlaşılır. Tamga sözcüğü Türk yazı geleneğinde mühür, damga, sahiplik ve tanıklık işaretidir; burada da aynı işi görür. Bir kez verilen tamga geri alınmaz: çalışma düzeltilse, başlığı değişse, sistem başka bir alan adına taşınsa bile o adres bozulmaz. Bir kimliği geri almak, kaydı geriye dönük olarak değiştirmek demektir.',
        'The tamga is the permanent identifier given to every work here: <code>KTG-YYYY-NNNNN-C</code>. The final character is a check digit; if the code is mistyped, even by one character, that fact is detectable. In the Turkic written tradition a tamga is a seal, a mark of ownership and of witness; it does the same work here. A tamga once issued is never withdrawn: even if the work is corrected, its title changed, or the system moved to another domain, that address does not break. To withdraw an identity is to alter the record retrospectively.'
      ) ?></p>

      <h2 id="b-dizin"><?= k_c('Dizinler üzerine', 'On indexes') ?></h2>
      <p><?= k_c(
        'Burada yayımlanan çalışmalar bugün itibarıyla TR Dizin, ESCI, Scopus ya da benzeri hiçbir dizinde taranmamaktadır. Bunu ilk sayfada yazıyoruz, çünkü çalışma göndermeden önce bilinmesi gereken bir gerçektir: burada yayımlanan bir çalışma, akademik yükseltme ölçütlerinde bugün bir dizin puanı getirmez. İleride dizinlerin ölçütleri karşılanabilir ve sistem taranmaya başlayabilir; bu bir taahhüt değil, yalnızca bir ihtimaldir.',
        'As of today, the works published here are not indexed in TR Dizin, ESCI, Scopus or any comparable index. We write this on the first page because it is a fact one should know before submitting: a work published here earns no index credit in academic promotion criteria today. In time the criteria of the indexes may be met and the system may come to be scanned; that is a possibility, not an undertaking.'
      ) ?></p>
      <p><?= k_c(
        'Bir dizinde taranmak, bir çalışmayı doğru yapmaz; taranmamak da yanlış yapmaz. Dizin, bir çalışmanın bulunmasını kolaylaştıran bir araçtır ve bu sistem o araca girmeyi reddetmez. Reddettiği tek şey, dizine girmek uğruna açıklıktan ödün vermektir: erişimi kısıtlamak, ücret koymak ya da hakem raporlarını gizlemek pahasına kazanılacak bir görünürlük, bu sistemin varlık sebebini ortadan kaldırır.',
        'Being indexed does not make a work correct, and not being indexed does not make it wrong. An index is a tool that makes a work easier to find, and this system does not refuse that tool. What it refuses is to trade openness for entry: a visibility bought by restricting access, charging fees or hiding referee reports would dissolve the very reason this system exists.'
      ) ?></p>

      <h2 id="b-devir"><?= k_c('Destek çağrısı ve devir', 'A call for support, and succession') ?></h2>
      <p><?= k_c(
        'Kalıcı bir kimlik vermek, kalıcılığı taahhüt etmektir. Bu taahhüt, tek bir sunucuya, tek bir faturaya ve tek bir kişinin ömrüne dayandığı sürece eksik kalır. Bir arşivin sürekliliği, ancak onu kuranlardan bağımsız bir kurumsal dayanağa kavuştuğu gün güvence altına alınmış olur.',
        'To issue a permanent identifier is to promise permanence. That promise remains incomplete for as long as it rests on a single server, a single invoice and the lifetime of a single person. The continuity of an archive is secured only on the day it acquires an institutional footing independent of the people who founded it.'
      ) ?></p>
      <p><?= k_c(
        'Bu nedenle araştırma altyapılarını destekleyen kamu kurumlarına, araştırma fonlarına, ulusal ve uluslararası kütüphanelere ve yükseköğretim kurumlarına açık bir çağrıda bulunuyoruz. Kutadgu, tamamlanmış bir yazılım ürünü olarak değil, sürdürülebilirliği paylaşılması gereken bir <b>açık bilim altyapısı</b> olarak değerlendirilmeye açıktır. Destek, bir bağış olarak değil, ortak bir altyapıya yapılan bir katkı olarak düşünülmüştür.',
        'We therefore address an open invitation to the public bodies that support research infrastructure, to research funders, to national and international libraries and to higher education institutions. Kutadgu is offered for consideration not as a finished software product but as an <b>open science infrastructure</b> whose sustainability ought to be shared. Support is conceived not as a donation but as a contribution to a common infrastructure.'
      ) ?></p>
      <p><?= k_c(
        'Bir kurumun böyle bir kararı verirken hangi ölçütlere baktığını biliyoruz ve sistemin bugünkü durumunu bu ölçütler karşısında açıkça yazıyoruz. Yazılım <b>AGPL-3.0</b> ile açıktır ve denetlenebilir. Yayımlanan çalışmalar <b>CC BY 4.0</b> ile lisanslanır. Arşivin tamamı, makine tarafından okunabilir tek bir dosya olarak indirilebilir ve <b>OAI-PMH</b> ile toplanabilir. Her çalışma, kurum dışı hiçbir hizmete bağlı olmayan kalıcı bir kimlik alır. Değerlendirme süreci, hakem raporları ve editöryal kararlar dâhil olmak üzere baştan sona açıktır; bu, bugünkü açık bilim politikalarının pek çoğunun ulaşmayı hedeflediği saydamlık düzeyinin üzerindedir. Veri, web kökünün dışında ve veritabanı bağımlılığı olmadan tutulur; bu da arşivin başka bir kurumda birebir çoğaltılmasını olağan bir işleme indirger.',
        'We are aware of the criteria an institution weighs in reaching such a decision, and we set out the system\'s present position against them plainly. The software is open under <b>AGPL-3.0</b> and can be audited. Published work is licensed under <b>CC BY 4.0</b>. The whole archive can be downloaded as a single machine readable file and harvested over <b>OAI-PMH</b>. Every work receives a permanent identifier that depends on no external service. The assessment process is open from end to end, referee reports and editorial decisions included, which exceeds the level of transparency most current open science policies set out to achieve. Data is held outside the web root and without any database dependency, which reduces mirroring the archive at another institution to a routine operation.'
      ) ?></p>
      <p><?= k_c(
        'Karşılanmayan ölçütleri de aynı açıklıkla yazıyoruz, çünkü bir kurumun bunları bizden değil başkasından öğrenmesi hem zaman kaybı hem güven kaybıdır. Sistem bugün hiçbir dizinde taranmamaktadır; ISSN başvurusu ve DOAJ dosyası henüz tamamlanmamıştır; DOI tescili için bir tescil kuruluşuyla anlaşma yoktur; arşivin ikinci bir coğrafyada aynası bulunmamaktadır ve uzun süreli saklama için bir kurumsal taahhüt henüz alınmamıştır. Bunların her biri kurumsal bir dayanakla birlikte çözülebilecek işlerdir; bu çağrının konusu da tam olarak budur.',
        'We set out the criteria that are not yet met with the same openness, because an institution learning them from someone other than us costs both time and trust. The system is at present indexed nowhere; the ISSN application and the DOAJ submission are not yet complete; there is no agreement with a registration agency for DOI assignment; the archive has no mirror in a second geography; and no institutional commitment to long term preservation has yet been secured. Each of these can be resolved once an institutional footing exists, and that is precisely what this invitation concerns.'
      ) ?></p>
      <p><?= k_c(
        'İstenen şey bellidir: aynanın tutulacağı sunucular, arşivin başka coğrafyalarda çoğaltılması, çeviri emeği, yazılım bakımı ve gerekirse himaye, yani sistemin bir kuruma devri. Devralan kurum, bu bildirinin yukarıdaki koşullarının tamamını kabul etmiş sayılır ve yayın yönetimine ilişkin bütün yetkileri kullanır: görevdeki baş editörleri değiştirebilir, yenilerini atayabilir.',
        'What is asked is plain: servers on which mirrors can be held, copies of the archive in other geographies, translation labour, software maintenance and, if it comes to it, custody, that is the transfer of the system to an institution. A receiving institution is taken to have accepted every one of the conditions above, and it exercises every authority pertaining to the running of the publication: it may change the serving chief editors and appoint others.'
      ) ?></p>
      <p><?= k_c(
        'Burada iki ayrı şey vardır ve bunları ayırmak gerekir. <b>Görevdeki baş editörlük bir yetkidir</b>: hakem atamak, editör eklemek, editöryal not düşmek. Bu yetki devredilebilir ve geri alınabilir. <b>Kurucu baş editörlük ise bir kayıttır</b>: sistemin kimin elinden çıktığı, olmuş bir şeydir ve bu sistemin kendi ilkesi gereği geriye dönük düzeltilmez. Kurucu sıfatı hiçbir yetki, hiçbir öncelik ve hiçbir imtiyaz taşımaz; yalnızca kaydı korur. Bunu bir ayrıcalık olarak değil, geleceğe gönderilmiş bir selam olarak yazıyoruz: bu işin bir gün kimlerin eliyle başladığını okumak isteyen biri, o kaydı bulabilsin.',
        'Two separate things stand here, and they must be told apart. <b>Serving as chief editor is an authority</b>: assigning reviewers, adding editors, entering editorial notes. That authority may be transferred and withdrawn. <b>Being a founding chief editor is a record</b>: whose hands the system came from is something that happened, and by this system\'s own principle it is not corrected retrospectively. The founding title carries no authority, no priority and no privilege; it preserves a record and nothing more. We write this not as a claim to precedence but as a greeting sent to the future: whoever one day wishes to read whose hands this began in should be able to find that record.'
      ) ?></p>
      <p><?= k_c(
        'Bu ikisine üçüncü bir şey eklenir. Görevi sona eren kişi <b>onursal baş editör</b> olarak anılmayı sürdürür. Bu bir teşekkürdür ve hiçbir yetki taşımaz. Önemli olan, kimsenin bu sıfatı birine verme yetkisinin bulunmamasıdır: sıfat verilmez, görev sona erdiği için kalır. Görevin nasıl sona erdiği de yazılıdır: kişinin kendi devri, vefatı, kurucuların çoğunluk kararı, atamaya bilerek konmuş bir bitiş tarihi ya da (yalnız atanmış baş editörlerde) bir yıllık dönem sonunda etkinlik ölçütünün karşılanmaması. Bir tarihin gelmesi tek başına kimsenin görevini bitirmez; görev ya kişinin kendi eliyle, ya yazılı bir kurul kararıyla, ya da herkese açık bir sayımla biter. Sistemi devralan kurumun da bu sıfatı dağıtma yetkisi yoktur. <b>Para karşılığı ise hiçbir koşulda verilemez</b>; bağış, barındırma ya da çeviri desteği hiç kimseye bu sıfatı kazandırmaz. Bu kısıt bilerek konuldu: dağıtılabilen bir onur sıfatı, zamanla bir nezaket parasına dönüşür ve dönüştüğü gün kurulun bütün adlarını değersizleştirir.',
        'A third thing is added to these two. A person whose office has ended continues to be recorded as an <b>honorary chief editor</b>. This is a thank you and carries no authority. What matters is that no one holds the power to give this title to anyone: it is not granted, it remains, because the office came to an end. How an office ends is written too: the person\'s own handover, their death, a majority decision of the founders, an end date deliberately written into the appointment, or (for appointed chief editors alone) the activity criterion going unmet at the close of a one-year term. The arrival of a date does not by itself end anyone\'s office; an office ends by the person\'s own hand, by a written decision of the board, or by a count open to all. Nor may the institution that takes the system over hand this title out. <b>It may never be given for money</b>; a donation, hosting or translation support earns this title for no one. This restriction is deliberate: an honour that can be handed out becomes, in time, a courtesy currency, and on the day it does it devalues every name on the board.'
      ) ?></p>
      <p><?= k_c(
        'Bakım ve geliştirme için ödeme yapılması yasak değildir; gizlenmesi yasaktır. Bir kurum barındırma, çeviri ya da yazılım bakımı için ödeme yaparsa bu ödeme kaleminin varlığı ve büyüklüğü sistemin istatistik sayfasında yazılır. Sınır tektir ve nettir: <b>hiçbir gelir, erişimi kısıtlamaktan doğamaz.</b> Yazardan işlem ücreti, okurdan abonelik, kurumdan erişim bedeli hiçbir ad altında alınamaz. Bu sınır aşıldığı anda sistem, adı ne olursa olsun, kurulduğu şey olmaktan çıkar.',
        'Paying for maintenance and development is not forbidden; concealing it is. If an institution pays for hosting, translation or software maintenance, the existence and the size of that item is written on the system\'s statistics page. There is one boundary and it is sharp: <b>no income may arise from restricting access.</b> No processing charge from authors, no subscription from readers, no access fee from institutions, under any name. The moment that boundary is crossed, whatever the system is then called, it has ceased to be the thing that was founded.'
      ) ?></p>
      <p><?= k_c(
        'Bu kural kurucuları da kapsar ve önce onları bağlar. Kuruculardan birine barındırma, yazılım bakımı ya da çeviri karşılığında yapılan bir ödeme varsa, aynı yerde, aynı ayrıntıyla ve aynı sıklıkla yazılır. Emeğin karşılığını almak bir çıkar çatışması değildir; çatışma, o karşılığın neyin yayımlanacağı üzerinde bir söz hakkına dönüşebildiği yerde başlar. Bu sistemde dönüşemez: hiçbir katkı, kimden gelirse gelsin, bir çalışmanın kabulünde, hakem seçiminde ya da yayın sırasında hiçbir etki taşımaz. Kural önce kuruculara uygulanır ki sonradan gelene uygulanabilsin.',
        'This rule covers the founders and binds them first. If a payment is made to any founder for hosting, software maintenance or translation, it is recorded in the same place, in the same detail and at the same intervals. Being paid for labour is not a conflict of interest; the conflict begins where that payment can be turned into a say over what gets published. In this system it cannot be: no contribution, from whomever it comes, carries any weight in the acceptance of a work, the choice of reviewers or the order of publication. The rule applies to the founders first so that it can be applied to whoever comes after.'
      ) ?></p>
      <p><?= k_c(
        'Devir, ancak arşivin sürekliliği hangi düzenle daha güvende ise o düzen lehine yapılır; başka hiçbir ölçüt geçerli değildir. Kurucu baş editörlerden altı ay boyunca hiçbir işaret alınamazsa, sistemin sürdürülmesi görevi bu bildiriyi kabul eden kuruma geçer. Bu hüküm, bir kişinin yokluğunda arşivin de yok olmasını önlemek içindir.',
        'A transfer is made only in favour of whichever arrangement leaves the continuity of the archive safer; no other criterion applies. If no sign is received from the founding chief editors for six months, the duty of sustaining the system passes to the institution that has accepted this declaration. This provision exists so that the archive does not disappear along with the absence of one person.'
      ) ?></p>
      <p class="metin-orta">
        <a class="d d-vurgu" href="<?= k_esc(k_bag('/destek.php')) ?>"><?= k_c('Destek ve himaye başvurusu', 'Support and custody: how to apply') ?></a>
      </p>

      <h2 id="b-nereden"><?= k_c('Bu düşünce nereden geldi', 'Where this idea came from') ?></h2>
      <p><?= k_c(
        'Bu düşünce bir dergi kurma isteğinden doğmadı. Bir çalışmanın, gönderildiği günden yayımlandığı güne kadar geçen sürede kimin eline geçtiğini, hangi gerekçeyle beklediğini ve kimin ne dediğini yazarın çoğu zaman bilememesinden doğdu. Kapalı hakemlik, kötü niyetli olduğu için değil, denetlenemediği için sorunludur: görünmeyen bir işlemin adil olup olmadığı tartışılamaz.',
        'This idea did not arise from a wish to found a journal. It arose from the fact that, between the day a work is submitted and the day it appears, an author usually cannot learn whose hands it passed through, on what grounds it waited, or who said what. Closed review is not a problem because it is ill intentioned, but because it cannot be audited: whether an unseen procedure was fair cannot even be argued.'
      ) ?></p>
      <p><?= k_c(
        'Buradaki karşılık basittir: değerlendirmenin kendisini yayımlamak. Hakemin adı, kararı, gerekçesi ve yazarın itirazı çalışmayla birlikte durur. Bu, hakemliği zorlaştırır; kolaylaştırmak da amaç değildi. Amaç, bir kararın arkasında durabilmenin olağan hâle gelmesiydi.',
        'The answer here is simple: publish the assessment itself. The reviewer\'s name, decision and reasoning, and the author\'s objection, stand with the work. This makes reviewing harder; making it easier was never the aim. The aim was that standing behind a judgement should become ordinary.'
      ) ?></p>

      <h2 id="b-nasil"><?= k_c('Bu sistem nasıl yapıldı', 'How this system was made') ?></h2>
      <p><?= k_c(
        'Kutadgu\'nun yazılımı yapay zekâ ile birlikte geliştirildi. Kodun büyük bölümü bir dil modeline yazdırıldı; yazılımı geliştiren kurucunun payına düşen, sistemin ne olması gerektiğini düşünmek ve bunu eksiksiz biçimde dile getirmekti. İlk sürümü ortaya çıkaran üç günlük uğraşın neredeyse tamamı, yazılım yazmak değil, ne istendiğini makineye anlatabilecek açıklıkta cümleler kurmakla geçti. Sonraki her değişiklik kurulun istekleri ve sınamalarıyla birlikte, depoda kayıtlı olarak yapıldı.',
        'Kutadgu\'s software was developed together with artificial intelligence. Most of the code was written by a language model; the part of the founder who develops the software was to think through what the system ought to be and to put that into words without gaps. Almost the whole of the three days that produced the first version went not into writing software but into forming sentences clear enough to explain to the machine what was wanted. Every change since has been made in response to the board\'s requests and tests, and is recorded in the repository.'
      ) ?></p>
      <p><?= k_c(
        'Bunu saklamak yerine yazıyoruz, çünkü bu sistemin savunduğu şeffaflık önce kendisi için geçerli olmalıdır. Bir aracın kullanılmış olması, kurulan yapının sorumluluğunu ortadan kaldırmaz; burada yazılan her satırın sorumluluğu, onu yazdıran insanlara aittir. Kaynak kodun tamamı açıktır ve herkes tarafından denetlenebilir.',
        'We write this rather than conceal it, because the transparency this system asks of others must first apply to itself. That a tool was used does not remove responsibility for what was built; responsibility for every line rests with the people who had it written. The whole source code is open and can be audited by anyone.'
      ) ?></p>

      <h2 id="b-destek"><?= k_c('Destek üzerine', 'On support') ?></h2>
      <p><?= k_c(
        'Kutadgu bugün gönüllü emekle ve karşılıksız yürütülüyor; öyle de kalacak. Bununla birlikte barındırma, yedekleme ve çeviri giderlerinin zamanla artacağı öngörülmektedir. Bu yükü paylaşmak isteyen araştırmacılar, kurumlar ya da okuyucular, katkı biçimini birlikte değerlendirmek üzere kurulla iletişime geçebilirler.',
        'Kutadgu is run today by volunteer labour and without charge, and so it will remain. It is nevertheless anticipated that the costs of hosting, archiving and translation will grow over time. Researchers, institutions or readers who wish to share that burden are welcome to write to the board so that the form of any contribution may be considered together.'
      ) ?></p>
      <p><?= k_c(
        'Katkı, maddi olabileceği gibi barındırma, çeviri, yazılım ya da hakemlik emeği biçiminde de olabilir. Hiçbir katkı, katkıyı verene sistem üzerinde bir söz hakkı, bir öncelik ya da bir görünürlük sağlamaz; bu bildirinin koşulları her durumda geçerlidir. Destek, sistemin bağımsızlığını satın alma yolu değildir; onu sürdürme yoludur.',
        'A contribution may be material, or it may take the form of hosting, translation, software or reviewing labour. No contribution grants the contributor any say over the system, any priority or any visibility; the conditions of this declaration apply in every case. Support is not a way of purchasing the system\'s independence; it is a way of sustaining it.'
      ) ?></p>
      <p class="metin-orta">
        <a class="d d-ikinci" href="<?= k_esc(k_bag('/iletisim.php')) ?>"><?= k_c('İletişim formu', 'Contact form') ?></a>
      </p>

      <?php /* ---------- İMZA ----------
               SORULAN: "altına sadece beni yazmak mantıklı olmayabilir;
               kurucu baş editör kurulunu da yazsak mı?"

               İKİSİ AYRI ŞEY VE İKİSİ DE YAZILIR. Metni yazan tektir ve
               bir metnin yazarı sonradan çoğaltılamaz — bu, bu sistemin
               yazarlık konusunda söylediği her şeye aykırı olurdu.
               Ama bildiri bir yazı değil bir TAAHHÜTTÜR: "bu sistem
               şuna söz verir" diyen bir metnin arkasında kimin durduğu,
               kimin yazdığı kadar önemlidir.

               Bu yüzden imza iki basamaklı: yazan, ve arkasında duran
               kurucu kurul.

               ADLAR ELLE YAZILMAZ. tg_kurucu_bas_editorler()'den gelir
               ve kayıt sırasını korur. Elle yazılsaydı, kurul kaydı
               değiştiği gün bildiri eski kurulu göstermeyi sürdürür ve
               en çok güvenilmesi gereken sayfa en eski bilgiyi taşırdı.
               Kurucu kaydı zaten düşmez: görevi biten kurucu da bu
               listede kalır, çünkü kuruluş olmuş bir şeydir. */ ?>
      <?php $kurucular = function_exists('tg_kurucu_bas_editorler') ? tg_kurucu_bas_editorler() : []; ?>
      <?php if (count($kurucular) > 1): ?>
      <div class="bld-kurul">
        <span class="bld-kurul-bas"><?= k_c('Kurucu baş editörler kurulu', 'The founding chief editors') ?></span>
        <ul>
          <?php foreach ($kurucular as $kb): ?>
          <?php /* k_unvan_ekle() kabuk.php'de tanımlıdır ve bu dosya
                   ?parca=1 ile KABUKSUZ da çağrılabiliyor; o yolda işlev
                   yoktur ve sayfa ilk adın ortasında kesiliyordu.
                   Ölçüldü: gövde 200 dönüyor ama </html> hiç gelmiyordu.
                   Aynı sınıf hata bu depoda daha önce üç kez çıktı —
                   bir sayfada var olan yardımcı, her bağlamda var
                   demek değildir. */ ?>
          <?php $kbAd = function_exists('k_unvan_ekle')
                  ? k_unvan_ekle((string)($kb['unvan'] ?? ''), (string)$kb['ad'])
                  : trim((string)($kb['unvan'] ?? '') . ' ' . (string)$kb['ad']); ?>
          <li><a href="<?= k_esc(k_bag(tg_kisi_yolu((string)$kb['ad']))) ?>"><?= k_esc($kbAd) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <p><?= k_c(
          'Bu bildirinin arkasında duran kuruldur. Adlar kuruluş kaydından okunur ve kayıt sırasını korur; sonradan eklenemez, çıkarılamaz. Görevi sona eren bir kurucunun adı da burada kalır, çünkü bir sistemin kimin elinden çıktığı olmuş bir şeydir.',
          'This is the board that stands behind the declaration. The names are read from the founding record and keep its order; none may be added or removed afterwards. The name of a founder whose office has ended also remains here, because whose hands a system came from is a thing that happened.'
        ) ?></p>
      </div>
      <?php endif; ?>
      <?php /* METNİ KALEME ALAN. Kurulun altında ve ondan bir basamak
               sönük durur: bildirinin arkasında duran kuruldur, metni
               yazan ise bir kayıttır. Kişisel bir siteye bağ verilmez. */ ?>
      <div class="bld-imza">
        <b>Dr. Behiç ÇETİN</b>
        <span><?= k_c('Metni kaleme alan', 'Drafted by') ?></span>
      </div>
    </article>
    <?php
}

/* ---------- Yalnızca gövde isteniyorsa ---------- */
if ($parca) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    bildiri_govde(true);
    exit;
}

/* ---------- Tam sayfa ---------- */
$ekBas = <<<CSS
<style>
/* Kuruluş metninin tam sayfa okunan hâli. Aynı gövde ilk ziyarette tam
   ekran bir katman olarak da gösterilir; o hâlin biçimi dizgededir
   (.bld-kat) ve daha yüksek belirtiçlikte olduğu için buradaki kurallar
   katmana karışmaz. */
.bld{min-width:0}
.bld-ust{font-size:var(--y-1);font-weight:700;letter-spacing:.14em;text-transform:uppercase;
  color:var(--kut);margin:0 0 var(--b-2)}
.bld h1{margin:0 0 .25em}
.bld-alt{font-size:var(--y-6);color:var(--metin-2);margin:0 0 2em}
/* Giriş paragrafı. Kenarındaki çizgi onu metnin geri kalanından
   ayırır: bu bir bölüm değil, belgenin ağzıdır. */
.bld-giris{font-size:var(--y-6);border-left:3px solid var(--kut);padding-left:var(--b-4)}
.bld h2{margin:2em 0 .5em;scroll-margin-top:calc(var(--ust) + var(--b-5))}

/* Bildirinin maddeleri. Numara metnin bir parçası değildir, bu yüzden
   işaretlemede değil sayaçla üretilir. */
.bld-madde{counter-reset:m;list-style:none;padding:0;margin:1.4em 0;display:grid;gap:var(--b-3)}
.bld-madde li{counter-increment:m;position:relative;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:var(--r-3);padding:var(--b-4) var(--b-4) var(--b-4) var(--b-7);font-size:var(--y-4)}
.bld-madde li::before{content:counter(m);position:absolute;left:var(--b-4);top:var(--b-4);
  width:var(--b-5);height:var(--b-5);border-radius:50%;background:var(--kut-zemin);color:var(--kut);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700;display:grid;place-items:center}
.bld-not{font-size:var(--y-3);color:var(--metin-2);border-top:1px solid var(--cizgi);padding-top:var(--b-3)}

/* İmza bloğu. Buradaki bağlantı bir cümlenin ortasında değil, tek
   başına durur: görsel ölçüsü küçük kalsın diye puntosu düşer,
   dokunma yüksekliği düşmez. */
.bld-imza{margin-top:3em;padding-top:var(--b-5);border-top:1px solid var(--cizgi);
  text-align:center;display:grid;justify-items:center;gap:var(--b-1)}
.bld-imza a{display:inline-flex;align-items:center;min-height:var(--hedef);
  font-size:var(--y-3);color:var(--metin-2)}
.bld-imza b{font-family:var(--serif);font-size:var(--y-6);font-weight:600}
.bld-imza span{font-size:var(--y-1);letter-spacing:.1em;text-transform:uppercase;color:var(--metin-2)}
/* Kurucu kurul: imzanın altında, ondan bir basamak sönük. Yazan ile
   arkasında duranlar aynı ağırlıkta görünmemeli. */
.bld-kurul{margin-top:var(--b-5);text-align:center}
.bld-kurul-bas{display:block;font-size:var(--y-1);letter-spacing:.1em;text-transform:uppercase;
  color:var(--metin-2);margin-bottom:var(--b-3)}
.bld-kurul ul{list-style:none;margin:0;padding:0;display:flex;flex-wrap:wrap;
  justify-content:center;gap:var(--b-2) var(--b-4)}
.bld-kurul li a{display:inline-flex;align-items:center;min-height:var(--hedef);
  font-family:var(--serif);font-size:var(--y-4);color:var(--metin)}
.bld-kurul p{margin:var(--b-4) auto 0;max-width:60ch;font-size:var(--y-2);
  color:var(--metin-2);line-height:var(--sh-genis)}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Bildiri', 'Declaration'),
    'yol' => '/bildiri.php',
    'ek_bas' => $ekBas,
    'aciklama' => k_c(
        'Kutadgu\'nun kuruluş metni: sistem kime aittir, neye söz verir ve hangi koşullarla devredilebilir.',
        'The founding text of Kutadgu: whom the system belongs to, what it promises and on what conditions it may be transferred.'
    ),
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Kuruluş metni', 'Founding text') ?></span>
      <h1><?= k_c('Kutadgu Bildirisi', 'The Kutadgu Declaration') ?></h1>
      <p><?= k_c(
        'Bu sistem neden var, kime aittir, neye söz verir.',
        'Why this system exists, whom it belongs to, what it promises.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="blg-ic"><?php bildiri_govde(false, false); ?></div>
   <?= k_belge_yan([
     ['k' => 'b-amac',        'tr' => 'Amaç',                    'en' => 'Purpose'],
     ['k' => 'b-kim',         'tr' => 'Bu sistem kime aittir',   'en' => 'Whom it belongs to'],
     ['k' => 'b-sorumluluk',  'tr' => 'Sorumluluk',              'en' => 'Responsibility'],
     ['k' => 'b-dil',         'tr' => 'Dil üzerine',             'en' => 'On language'],
     ['k' => 'b-ad',          'tr' => 'Adın ve tamganın anlamı', 'en' => 'The name and the tamga'],
     ['k' => 'b-dizin',       'tr' => 'Dizinler üzerine',        'en' => 'On indexes'],
     ['k' => 'b-devir',       'tr' => 'Destek çağrısı ve devir', 'en' => 'Support and succession'],
     ['k' => 'b-nereden',     'tr' => 'Bu düşünce nereden geldi','en' => 'Where the idea came from'],
     ['k' => 'b-nasil',       'tr' => 'Bu sistem nasıl yapıldı', 'en' => 'How it was made'],
     ['k' => 'b-destek',      'tr' => 'Destek üzerine',          'en' => 'On support'],
   ], [
     ['tr' => 'Bu metin bir taahhüttür', 'en' => 'This text is an undertaking',
      'ic' => k_c('Bir buyruk değil, bir söz. Sistemin neye söz verdiğini ve bu sözü kim yönetirse yönetsin nasıl koruyacağını yazar.',
                  'Not a command but a promise. It sets out what the system undertakes, and how that undertaking is to be kept whoever comes to administer it.')],
   ]) ?>
  </div>
</section>
<?php k_son(); ?>
