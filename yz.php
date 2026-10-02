<?php
/* =====================================================================
   KUTADGU - Yapay zekâ kullanımı / Use of artificial intelligence
   Bu sayfa bir yasak listesi değil, bir ölçü metnidir. Yapay zekânın
   bilim üretiminde nerede yardımcı, nerede yıkıcı olduğunu örneklerle
   anlatır. İki dilli.
   ===================================================================== */
declare(strict_types=1);

require_once __DIR__ . '/k/kabuk.php';

$en = k_en();

/* Sayfanın bölümleri. Hem sağdaki içindekiler listesi hem de başlık
   kimlikleri buradan üretilir: iki yerde ayrı ayrı yazılmaz. */
$bolumler = [
    ['k' => 'neden',  'tr' => 'Neden yasaklamıyoruz',   'en' => 'Why we do not prohibit it'],
    ['k' => 'olcu',   'tr' => 'Ölçü nerede',            'en' => 'Where the measure lies'],
    ['k' => 'ornek',  'tr' => 'Örneklerle',             'en' => 'With examples'],
    ['k' => 'kural',  'tr' => 'Bu sistemde geçerli kurallar', 'en' => 'The rules that apply here'],
    ['k' => 'sorgu',  'tr' => 'Hakemin sorgulama araçları',  'en' => 'The reviewer\'s tools of scrutiny'],
    ['k' => 'okuyan', 'tr' => 'Okumaya gelen yapay zekâ', 'en' => 'The artificial intelligence that comes to read'],
    ['k' => 'birde',  'tr' => 'Bir de şu var',          'en' => 'One more thing'],
];

$ekBas = <<<CSS
<style>
/* Giriş paragrafı. Kenarındaki çizgi onu metnin geri kalanından
   ayırır: bu bir bölüm değil, sayfanın ağzıdır. */
.yz-giris{font-size:var(--y-6);border-left:3px solid var(--kut);padding-left:var(--b-5);margin:0 0 2em}
.yz h2{margin:2.1em 0 .6em;scroll-margin-top:calc(var(--ust) + var(--b-5))}
/* Makinelere söylenen koşul. Alıntı biçiminde durur çünkü bu sayfanın
   cümlesi değildir: çalışma sayfasında ve llms.txt'te de aynen geçen,
   tek kaynaktan gelen bir metindir. */
.yz-makine{margin:1.1em 0;padding:var(--b-4) var(--b-5);background:var(--yuzey-2);
  border-inline-start:3px solid var(--kut);border-radius:var(--r-2);
  font-size:var(--y-4);line-height:var(--sh-genis);color:var(--metin)}
.yz h3{margin:1.8em 0 .5em}

/* İyi ve kötü kullanım, yan yana iki kart. Kartın kendisi dizgeden
   gelir; buradaki renk yalnızca hangisinin hangisi olduğunu söyler. */
.yz-ikili{margin-block:1.6em}
.yz-sut.iyi{border-color:var(--yesil-cizgi);background:var(--yesil-zemin)}
.yz-sut.kotu{border-color:var(--kirmizi-cizgi);background:var(--kirmizi-zemin)}
/* Bu iki başlık bir cümledir ("Bilimi ileri götüren kullanım"), bir
   etiket değil: versal sans yazıldığında ne başlık gibi okunuyordu ne
   etiket gibi. Kart başlığı basamağına alındı. */
.yz-sut h3{margin:0 0 var(--b-2);font-size:var(--y-5)}
.yz-sut.iyi h3{color:var(--yesil)}
.yz-sut.kotu h3{color:var(--kirmizi)}
.yz-sut ul{list-style:none;margin:var(--b-3) 0 0;padding:0;display:grid;gap:var(--b-3)}
.yz-sut li{position:relative;padding-left:var(--b-5);font-size:var(--y-3)}
.yz-sut li::before{position:absolute;left:0;top:0;font-weight:700}
.yz-sut.iyi li::before{content:"+";color:var(--yesil)}
.yz-sut.kotu li::before{content:"\\00d7";color:var(--kirmizi)}
.yz-sut b{display:block;font-weight:600;margin-bottom:var(--b-1)}

/* Kurallar. Numara metnin bir parçası değildir, sayaçla üretilir. */
.yz-kural{counter-reset:yk;list-style:none;padding:0;margin:1.4em 0;display:grid;gap:var(--b-3)}
.yz-kural li{counter-increment:yk;position:relative;background:var(--yuzey);border:1px solid var(--cizgi);
  border-radius:var(--r-3);padding:var(--b-4) var(--b-4) var(--b-4) var(--b-7);font-size:var(--y-4)}
.yz-kural li::before{content:counter(yk);position:absolute;left:var(--b-4);top:var(--b-4);
  width:var(--b-5);height:var(--b-5);border-radius:50%;background:var(--kut-zemin);color:var(--kut);
  font-family:var(--mono);font-size:var(--y-2);font-weight:700;display:grid;place-items:center}

.yz-son{margin-top:2.6em;border-top:1px solid var(--cizgi);padding-top:var(--b-5);
  font-size:var(--y-3);color:var(--metin-2)}
.yz-son a{font-weight:600}

/* Katlanan sorgulama metinleri. Alanın kendisi dizgenin form ögesidir;
   burada yalnızca katlanan kutunun kendisi tanımlanır. */
.sorgu{border:1px solid var(--cizgi);border-radius:var(--r-3);margin-block:var(--b-5);background:var(--yuzey)}
.sorgu summary{cursor:pointer;padding:var(--b-3) var(--b-4);min-height:var(--hedef);
  font-weight:600;list-style:none}
.sorgu summary::-webkit-details-marker{display:none}
.sorgu summary::before{content:"+ ";color:var(--kut);font-family:var(--mono)}
.sorgu[open] summary::before{content:"- "}
.sorgu > *:not(summary){margin-inline:var(--b-4)}
.sorgu > *:last-child{margin-bottom:var(--b-4)}
.sorgu-ack{font-size:var(--y-3);color:var(--metin-2)}
.sorgu-k{border-top:1px solid var(--cizgi);padding-top:var(--b-3);margin-top:var(--b-3)}
.sorgu-k h3{font-size:var(--y-5);margin:0 0 var(--b-2)}
.sorgu-k button{margin-top:var(--b-2)}
</style>
CSS;

k_bas([
    'tur'    => 'belge',
    'baslik' => k_c('Yapay zekâ kullanımı', 'Use of artificial intelligence'),
    'yol'    => '/yz.php',
    'aciklama' => k_c(
        'Bilimsel çalışmada yapay zekânın nerede yardımcı, nerede yıkıcı olduğu; olumlu ve olumsuz kullanım örnekleri ve Kutadgu\'nun kuralları.',
        'Where artificial intelligence helps and where it destroys in scholarly work; examples of good and bad use, and the rules that apply in Kutadgu.'
    ),
    'ek_bas' => $ekBas,
]);
?>
<section class="sayfa-bas">
  <div class="kap sayfa-bas-ic">
    <div>
      <span class="bas-ust"><?= k_c('Duyuru ve ölçü', 'A statement of measure') ?></span>
      <h1><?= k_c('Yapay zekâ ve bilimsel çalışma', 'Artificial intelligence and scholarly work') ?></h1>
      <p><?= k_c(
        'Bu sayfa bir yasak listesi değil, bir ölçü metnidir: yapay zekânın bilim üretiminde nerede yardımcı, nerede yıkıcı olduğunu örnekleriyle anlatır.',
        'This page is not a list of prohibitions but a statement of measure: it shows, with examples, where artificial intelligence helps in the making of knowledge and where it destroys.'
      ) ?></p>
    </div>
  </div>
</section>

<section class="bolum">
  <div class="kap blg">
   <div class="yz blg-ic">

    <?php /* KURUL GERİ BİLDİRİMİ (M. Z. Tunca, 13 Ağustos 2026):
             "burada soru yok, cevabı verilen soru daha sonra geliyor."
             Doğruydu: paragraf "Kısa cevap: evet" diye başlıyor ama
             sorulan şey sayfanın hiçbir yerinde yazmıyordu. Cevabı
             okunan ama sorusu görülmeyen bir metin, okura kendi
             kaçırdığı bir şey varmış duygusu verir. Soru yazıldı. */ ?>
    <p class="yz-soru"><?= k_c(
      'Bilimsel bir çalışmada yapay zekâ kullanmak mantıklı mıdır?',
      'Does it make sense to use artificial intelligence in scholarly work?'
    ) ?></p>
    <p class="yz-giris"><?= k_c(
      'Kısa cevap: evet, mantıklıdır. Ama yapay zekâ bir düşünme yerine geçmez, düşünmenin hızını artırır. Bu ayrımı kaybeden bir çalışma, hızlı üretilmiş ama hiçbir şey bilmeyen bir metne dönüşür. Bu sayfa, o ayrımın nerede olduğunu anlatmak için yazıldı.',
      'The short answer is yes, it makes sense. But artificial intelligence does not stand in place of thinking; it increases the speed of thinking. A work that loses this distinction becomes a text produced quickly that knows nothing. This page is written to show where that distinction lies.'
    ) ?></p>

    <h2 id="neden"><?= k_c('Neden yasaklamıyoruz', 'Why we do not prohibit it') ?></h2>
    <p><?= k_c(
      'Bilginin değeri, üretildiği hızda değil doğruluğunda ve paylaşılabilirliğindedir. Matbaa da, hesap makinesi de, istatistik yazılımı da çıktıklarında bilimi bozacakları söylenerek karşılandı. Bozmadılar; araştırmacının zamanını, gerçekten düşünmesi gereken yere kaydırdılar. Yapay zekâ da aynı yerde durabilir: kaynak taramasının, dil düzeltmesinin, veri düzenlemesinin aldığı saatleri geri verir ve araştırmacıyı kendi sorusuyla baş başa bırakır.',
      'The value of knowledge lies not in the speed at which it is produced but in its correctness and in its being shareable. The printing press, the calculator and statistical software were all met with the claim that they would corrupt science. They did not; they moved the researcher\'s time to where thinking was actually needed. Artificial intelligence can stand in the same place: it gives back the hours taken by literature scanning, language correction and data tidying, and leaves the researcher alone with the question itself.'
    ) ?></p>
    <p><?= k_c(
      'Bunun insanlık açısından anlamı şudur: dünyanın büyük bölümünde araştırmacılar, ana dili İngilizce olmadığı için, kurumsal aboneliği olmadığı için ya da yanında bir istatistikçi bulunmadığı için görünmez kalır. Yapay zekâ bu üç engeli de aynı anda alçaltır. Bir yasak, engeli olmayanları değil, yalnızca engeli olanları vurur. Bu yüzden yasaklamıyoruz.',
      'What this means for humanity is this: in much of the world researchers remain invisible because English is not their first language, because they have no institutional subscription, or because there is no statistician beside them. Artificial intelligence lowers all three of these barriers at once. A prohibition would strike not those who face no barrier but only those who do. That is why we do not prohibit it.'
    ) ?></p>

    <h2 id="olcu"><?= k_c('Ölçü nerede', 'Where the measure lies') ?></h2>
    <p><?= k_c(
      'Ölçü tek bir cümleyle söylenebilir: <b>yapay zekâ, sorumluluğu üstlenemeyeceği hiçbir işi yapmamalıdır.</b> Bir cümlenin doğru olduğunu bilen, o cümlenin arkasında duran ve yanlış çıkarsa hesabını veren bir insan olmak zorundadır. Bir modelin ürettiği kaynakça, o kaynakları okumuş bir insanın kaynakçası değildir. Bir modelin ürettiği bulgu, veriye bakmış bir insanın bulgusu değildir.',
      'The measure can be stated in a single sentence: <b>artificial intelligence should do no work for which it cannot bear the responsibility.</b> The one who knows a sentence to be true, who stands behind it and who answers for it if it proves false, must be a human being. A reference list produced by a model is not the reference list of a person who has read those sources. A finding produced by a model is not the finding of a person who has looked at the data.'
    ) ?></p>
    <p><?= k_c(
      'Buradan pratik bir kural çıkar: yapay zekâ <b>metnin biçimine</b> dokunabilir, <b>metnin iddiasına</b> dokunamaz. Cümleyi düzeltebilir, cümleyi kuramaz. Kaynağı bulmanıza yardım edebilir, kaynağı uyduramaz. Kodu yazabilir, sonucu yorumlayamaz.',
      'From this follows a practical rule: artificial intelligence may touch <b>the form of the text</b>, but not <b>the claim of the text</b>. It may correct a sentence; it may not make the claim. It may help you find a source; it may not invent one. It may write the code; it may not interpret the result.'
    ) ?></p>

    <h2 id="ornek"><?= k_c('Örneklerle', 'With examples') ?></h2>
    <div class="dizi dizi-2 yz-ikili">
      <div class="kart yz-sut iyi">
        <h3><?= k_c('Bilimi ileri götüren kullanım', 'Use that advances knowledge') ?></h3>
        <ul>
          <li><b><?= k_c('Alanyazın taraması', 'Scanning the literature') ?></b><?= k_c(
            'Bir konudaki tartışmanın haritasını çıkarmasını istemek, sonra bulduğu her kaynağı kendiniz açıp okumak. Model yolu kısaltır, okumayı yapan sizsiniz.',
            'Asking it to map the debate on a topic, then opening and reading every source it names yourself. The model shortens the path; the reading is yours.'
          ) ?></li>
          <li><b><?= k_c('Dil ve anlatım', 'Language and expression') ?></b><?= k_c(
            'Ana diliniz olmayan bir dilde yazdığınız metnin akıcılığını düzelttirmek. İçerik sizin, dil engeli ortadan kalkar.',
            'Having the fluency of a text you wrote in a language that is not your own corrected. The content is yours; the language barrier disappears.'
          ) ?></li>
          <li><b><?= k_c('Çözümleme kodu', 'Analysis code') ?></b><?= k_c(
            'Kendi verinizi işlemek için gereken kodu yazdırmak, sonra kodu satır satır okuyup çıktısını elle bir örnekle sınamak.',
            'Having the code you need for your own data written, then reading it line by line and testing its output by hand on one example.'
          ) ?></li>
          <li><b><?= k_c('Kendi savınızı yıkmayı denemek', 'Trying to break your own argument') ?></b><?= k_c(
            'Modele "bu tezin en güçlü karşı savı nedir" diye sormak. Hakemden önce kendi zayıf yerinizi bulmanın en ucuz yolu budur.',
            'Asking the model for the strongest counter-argument to your thesis. This is the cheapest way to find your weak point before a reviewer does.'
          ) ?></li>
          <li><b><?= k_c('Erişilebilirlik', 'Accessibility') ?></b><?= k_c(
            'Çalışmanın özetini başka dillere çevirmek, görme engelli okur için görsel betimlemesi üretmek, karmaşık bir tabloyu okunur hale getirmek.',
            'Translating the abstract into other languages, producing image descriptions for blind readers, making a dense table readable.'
          ) ?></li>
        </ul>
      </div>
      <div class="kart yz-sut kotu">
        <h3><?= k_c('Bilimi yıkan kullanım', 'Use that destroys it') ?></h3>
        <ul>
          <li><b><?= k_c('Uydurulmuş kaynak', 'Fabricated references') ?></b><?= k_c(
            'Modelin ürettiği, gerçekte var olmayan makale adlarını kaynakçaya koymak. Bu, kusur değil sahteciliktir ve tespit edilirse çalışma geri çekilir.',
            'Putting article titles the model produced, which do not exist, into the reference list. This is not a lapse but a falsification, and if detected the work is retracted.'
          ) ?></li>
          <li><b><?= k_c('Uydurulmuş veri', 'Fabricated data') ?></b><?= k_c(
            'Toplanmamış bir veri kümesini modele "üretmesini" söylemek ya da eksik gözlemleri modele tamamlatıp bunu belirtmemek.',
            'Telling the model to "produce" a dataset that was never collected, or having it fill in missing observations without saying so.'
          ) ?></li>
          <li><b><?= k_c('Metni tümüyle devretmek', 'Handing over the whole text') ?></b><?= k_c(
            'Başlıktan sonuca kadar metni modele yazdırıp adınızı üstüne koymak. Ortada bir yazar yoktur; savunulacak bir iddia da yoktur.',
            'Having the model write the text from title to conclusion and putting your name on it. There is no author here, and therefore no claim that can be defended.'
          ) ?></li>
          <li><b><?= k_c('Hakem raporunu devretmek', 'Handing over the review') ?></b><?= k_c(
            'Makaleyi okumadan modele değerlendirtmek. Hakemlik bu sistemde adla ve imzayla yapılır; okunmamış bir metnin raporu bir belge değil, bir gürültüdür.',
            'Having the model assess a paper you have not read. Review here is done by name and by signature; the report of an unread text is not a record but noise.'
          ) ?></li>
          <li><b><?= k_c('Beyanı gizlemek', 'Concealing the use') ?></b><?= k_c(
            'Kullanmak kusur değildir; kullanıp söylememektir kusur. Beyan edilmemiş kullanım, ortaya çıktığında çalışmanın bütününe olan güveni götürür.',
            'Using it is not the fault; using it and not saying so is. Undeclared use, once it surfaces, takes with it the trust in the entire work.'
          ) ?></li>
        </ul>
      </div>
    </div>

    <h2 id="kural"><?= k_c('Bu sistemde geçerli kurallar', 'The rules that apply here') ?></h2>
    <ol class="yz-kural">
      <li><?= k_c(
        'Yapay zekâ kullanımı serbesttir, beyanı zorunludur. Her gönderimde kullanımın kapsamı seçilir ve bu beyan çalışmayla birlikte yayımlanır.',
        'The use of artificial intelligence is permitted; declaring it is compulsory. The extent of use is selected on every submission and that declaration is published with the work.'
      ) ?></li>
      <li><?= k_c(
        'Yapay zekâ yazar olamaz. Yazarlık, sorumluluk üstlenmeyi gerektirir; bir model sorumluluk üstlenemez. Yazar listesinde yalnızca insanlar bulunur.',
        'Artificial intelligence cannot be an author. Authorship requires bearing responsibility, and a model cannot bear it. Only human beings appear in the author list.'
      ) ?></li>
      <li><?= k_c(
        'Kaynakça yazarın sorumluluğundadır. Kaynakçadaki her kaydın gerçekten var olduğunu ve iddia edileni söylediğini yazar güvence eder.',
        'The reference list is the author\'s responsibility. The author warrants that every entry in it genuinely exists and says what it is claimed to say.'
      ) ?></li>
      <li><?= k_c(
        'Hakem, raporunu kendisi yazar. Dil düzeltmesi için yardım alması olağandır; değerlendirmenin kendisini devretmesi hakemliğin sonudur.',
        'The reviewer writes the report. Taking help with language is ordinary; handing over the assessment itself ends the review.'
      ) ?></li>
      <li><?= k_c(
        'Uydurulmuş kaynak ya da veri bulunursa çalışma geri çekilir. Metin silinmez; kaydın eksilmemesi için erişime açık kalır ama geri çekildiği açıkça görünür.',
        'If a fabricated source or fabricated data is found, the work is retracted. The text is not deleted; it stays accessible so that the record is not diminished, but the retraction is shown plainly.'
      ) ?></li>
      <li><?= k_c(
        'Beyan sonradan düzeltilebilir. Bir kullanımı bildirmeyi unuttuysanız yazar panelinizden ekleyebilirsiniz; geç beyan, gizlenmiş beyandan her zaman iyidir.',
        'A declaration can be corrected later. If you forgot to report a use, you may add it from your author panel; a late declaration is always better than a hidden one.'
      ) ?></li>
    </ol>

    <h2 id="sorgu"><?= k_c('Hakemin sorgulama araçları', 'The reviewer\'s tools of scrutiny') ?></h2>
    <p><?= k_c(
      'Önce bir yanlış anlamayı ortadan kaldıralım: <b>bir metnin yapay zekâ tarafından yazılıp yazılmadığını güvenilir biçimde saptayan bir araç yoktur.</b> Bu iddiayı taşıyan yazılımlar, ana dili İngilizce olmayan yazarları ve sade yazan herkesi düzenli olarak yanlış işaretler. Böyle bir aracın çıktısına dayanarak bir çalışmayı reddetmek, haksızlık üretmenin en kestirme yoludur. Bu sistemde yapay zekâ tespiti diye bir ölçüt yoktur ve olmayacaktır.',
      'Let us first clear away a misunderstanding: <b>there is no tool that reliably determines whether a text was written by artificial intelligence.</b> Software making that claim regularly mislabels authors whose first language is not English, and anyone who writes plainly. To reject a work on the strength of such a tool\'s output is the shortest path to producing injustice. There is no criterion of AI detection in this system, and there will not be.'
    ) ?></p>
    <p><?= k_c(
      'Buna karşılık, bir metnin <b>denetlenebilir</b> olup olmadığı sorulabilir ve sorulmalıdır. Aşağıdaki sorular bunun içindir. Hiçbiri "bunu makine mi yazdı" diye sormaz; hepsi "bu metnin dayanakları gerçek mi, yöntemi izlenebilir mi" diye sorar. Uydurulmuş bir kaynak, kim yazmış olursa olsun uydurmadır; izlenemeyen bir yöntem, kim yazmış olursa olsun izlenemez. Ölçü metnin kendisidir, yazarın hangi araçları kullandığı değil.',
      'What can and should be asked, however, is whether a text is <b>checkable</b>. The questions below are for that. None of them asks "did a machine write this"; every one asks "are the grounds of this text real, is its method traceable". A fabricated source is a fabrication whoever wrote it; a method that cannot be followed cannot be followed whoever wrote it. The measure is the text itself, not which tools its author used.'
    ) ?></p>

    <details class="sorgu">
      <summary><?= k_c('Kopyalanabilir sorgulama metinleri', 'Copyable lines of scrutiny') ?></summary>
      <p class="sorgu-ack"><?= k_c(
        'Bunları bir dil modeline yaptırabileceğiniz gibi kendiniz de yürütebilirsiniz. Sonuç bir hüküm değil, bakılacak yerlerin listesidir; hükmü hakem verir.',
        'You may run these through a language model or carry them out yourself. The result is not a verdict but a list of places to look; the verdict is the reviewer\'s.'
      ) ?></p>
      <?php
      $sorular = [
        [
          ['Kaynakların gerçekten var olup olmadığı', 'Whether the sources actually exist'],
          ['Aşağıdaki kaynakçadaki her kayıt için şunu yap: başlığı ve yazar adını olduğu gibi ara. Bulduğun kaydın yılı, dergisi, cilt ve sayfa bilgisi verilenle örtüşüyor mu yaz. Bulamadıklarını ayrı bir listede topla ve "bulunamadı" de; tahmin yürütme, benzerini önerme. Bir DOI verilmişse doi.org üzerinden çözülüp çözülmediğini belirt.',
           'For each entry in the reference list below, do this: search the title and author name exactly as given. State whether the year, journal, volume and page numbers of the record you find match what is given. Collect the ones you cannot find in a separate list and say "not found"; do not guess and do not suggest a close match. Where a DOI is given, state whether it resolves through doi.org.'],
        ],
        [
          ['Kaynağın söylediği ile metnin dediği', 'What the source says and what the text claims'],
          ['Metinde bir kaynağa dayandırılan her iddiayı çıkar ve karşısına o kaynağın gerçekten ne söylediğini yaz. Kaynağın kapsamı iddiadan darsa, iddia kaynaktan çıkmıyorsa ya da kaynak tam tersini söylüyorsa bunu ayrıca işaretle. Emin olamadığın yerde "doğrulanamadı" yaz.',
           'Extract every claim in the text that rests on a source and set against it what that source actually says. Mark separately where the source is narrower than the claim, where the claim does not follow from the source, or where the source says the opposite. Where you cannot be sure, write "could not verify".'],
        ],
        [
          ['Yöntemin başkasınca yürütülebilirliği', 'Whether the method can be carried out by someone else'],
          ['Bu yöntem bölümünü okuyup aynı işlemi baştan yapmam istense, hangi bilgiler eksik kalırdı? Veri kaynağı, örneklem sınırları, dışlama ölçütleri, kullanılan yazılım ve sürümü, rastgelelik tohumu, düzeltme ve ağırlıklandırma adımları için tek tek "verilmiş" ya da "eksik" yaz. Eksik olanların yerine varsayım üretme.',
           'If I were asked to read this methods section and carry out the same procedure from scratch, what information would be missing? For the data source, the sample boundaries, the exclusion criteria, the software and its version, the random seed, and the correction and weighting steps, write "given" or "missing" for each. Do not invent assumptions in place of what is missing.'],
        ],
        [
          ['Sayıların kendi içinde tutarlılığı', 'Whether the numbers agree with one another'],
          ['Metindeki, tablolardaki ve özetteki sayıları karşılaştır. Örneklem büyüklüğü her yerde aynı mı? Yüzdeler toplamı beklendiği gibi mi? Tabloda verilen değerler metinde aynı biçimde mi anılıyor? Serbestlik dereceleri örneklemle uyumlu mu? Tutmayan her yeri, hangi iki yerin çeliştiğini göstererek yaz.',
           'Compare the numbers in the text, in the tables and in the abstract. Is the sample size the same everywhere? Do the percentages sum as expected? Are the values given in the tables cited in the same form in the text? Are the degrees of freedom consistent with the sample? For each place that does not agree, state which two places contradict each other.'],
        ],
        [
          ['Sonucun bulgudan ne kadar uzağa gittiği', 'How far the conclusion travels beyond the finding'],
          ['Bulgular bölümünde gerçekten gösterilen ile sonuç bölümünde iddia edilen arasındaki mesafeyi yaz. Bir ilişki nedensellik gibi mi anlatılmış? Örneklemin kapsamadığı bir topluluğa genelleme yapılmış mı? Sınırlılıklar bölümünde anılmayan ama bulguyu doğrudan zayıflatan bir etken var mı?',
           'Set out the distance between what the findings section actually shows and what the conclusion claims. Is an association narrated as causation? Is a generalisation made to a population the sample does not cover? Is there a factor that directly weakens the finding but is not named in the limitations?'],
        ],
      ];
      foreach ($sorular as $i => $s): ?>
        <div class="sorgu-k">
          <h3><?= k_esc(k_c($s[0][0], $s[0][1])) ?></h3>
          <textarea id="sorgu<?= $i ?>" rows="5" readonly><?= k_esc(k_c($s[1][0], $s[1][1])) ?></textarea>
          <button class="d d-ikinci d-kucuk" type="button" data-kopya="sorgu<?= $i ?>"><?= k_c('Kopyala', 'Copy') ?></button>
        </div>
      <?php endforeach; ?>
      <p class="sorgu-ack"><?= k_c(
        'Bu sorulardan çıkan bir bulgu tek başına ret gerekçesi değildir; hakem raporunda gerekçesiyle birlikte yazılır ve yazara yanıt hakkı doğar. Bir dil modeli kullanıyorsanız, onu hakemliğinizde kullandığınızı raporunuzda belirtmeniz gerekir: bu sistemde beyan yükümlülüğü hakem için de geçerlidir.',
        'A finding arising from these questions is not on its own a ground for rejection; it is written into the referee report with its reasoning, and the author gains a right of reply. If you use a language model, you must state in your report that you used it in your review: the duty of declaration in this system applies to the reviewer as well.'
      ) ?></p>
      <p class="sorgu-ack"><?= k_c(
        'Ölçüt metni için Yükseköğretim Kurulu\'nun bilimsel araştırma ve yayın etiği yönergesine ve COPE\'un yayın etiği kılavuzlarına bakılabilir.',
        'For the underlying criteria, the research and publication ethics directive of the Council of Higher Education and the publication ethics guidelines of COPE may be consulted.'
      ) ?></p>
    </details>

    <?php /* =================================================================
       BU SAYFANIN EKSİK YARISIYDI.
       -----------------------------------------------------------------
       Sayfa bugüne kadar yalnız yapay zekânın YAZAN tarafını anlatıyordu:
       yazar aracı nasıl kullanır, hakem nasıl sorgular, ne beyan edilir.
       Oysa yapay zekânın bu sistemle ikinci bir ilişkisi var ve o ilişki
       sistemin kurulduğu günden beri işliyor: MAKİNELER BURAYA OKUMAYA
       GELİYOR ve kendilerine bir şey söyleniyor.

       Söylenen şey üç makine dosyasında yazılı (robots.txt, llms.txt,
       license.xml) ve bir insanın oralara bakması beklenemez. Yazarın
       gönderim anında bilmesi gereken bir şey, kök dizindeki bir metin
       dosyasında duramaz.

       Cümlenin kendisi burada YAZILMIYOR, tg_makine_kosulu()'ndan
       geliyor: aynı cümle çalışma sayfasında ve llms.txt'te de basılıyor
       ve üç yerde ayrı yazılsaydı bir gün üçü ayrı şey söylerdi.
       ================================================================= */ ?>
    <h2 id="okuyan"><?= k_c('Okumaya gelen yapay zekâ', 'The artificial intelligence that comes to read') ?></h2>
    <p><?= k_c(
      'Bu sayfanın buraya kadarki bölümü yapay zekânın YAZAN tarafını anlatıyor. Bir de okuyan tarafı var: dil modellerini eğiten ve besleyen tarayıcılar bu arşive giriyor ve onlara bir şey söyleniyor. Ne söylendiği burada yazılıdır, çünkü bir yazarın çalışmasını göndermeden önce bunu bilmeye hakkı vardır.',
      'Up to this point the page has described what artificial intelligence does when it writes. There is also the side that reads: the crawlers that train and feed language models come into this archive, and something is said to them. What is said is written here, because an author has the right to know it before sending their work.'
    ) ?></p>
    <p><?= k_c(
      '<b>Kapı kapatılmıyor.</b> Akademik yayıncılığın büyük bölümü bugün ters yöne gidiyor: tarayıcılar engelleniyor, içerik eğitim için ücretle satılıyor. Burada öyle olmuyor ve bunun bir gerekçesi var. Bu arşivdeki her metin CC BY 4.0 ile açıktır; o lisans, makineyle okumayı ve eğitimde kullanmayı zaten serbest bırakır. Lisansın verdiği bir hakkı teknik bir engelle geri almak, lisansı yazarken verilen sözü geri almak olurdu.',
      '<b>The door is not closed.</b> Much of academic publishing is moving the other way today: crawlers are blocked and content is sold for training. That is not what happens here, and there is a reason. Every text in this archive is open under CC BY 4.0, and that licence already permits machine reading and use in training. To take back by a technical block what the licence grants would be to take back the promise made when the licence was chosen.'
    ) ?></p>
    <p><?= k_c(
      '<b>Ücret istenmiyor ve istenmeyecek.</b> Ücret yasağı bu sistemin birinci değişmez ilkesidir ve okurdan da yazardan da alınmadığı gibi bir modelden de alınmaz. İstenen tek şey, lisansın zaten istediği şeydir:',
      '<b>No payment is asked, and none will be.</b> The prohibition on charging is the first unchanging principle of this system: it is not levied on the reader, not on the author, and not on a model either. The one thing asked is the thing the licence already asks:'
    ) ?></p>
    <?php /* Koşulun kendisi tek kaynaktan; bkz. ortak.php. */ ?>
    <blockquote class="yz-makine"><?= k_esc(tg_makine_kosulu(k_en())) ?></blockquote>
    <p><?= k_c(
      '<b>Nerede yazılı olduğu.</b> Aynı koşul, insanın okuduğu bu sayfada olduğu gibi makinenin okuduğu yerlerde de duruyor. Üçü de bu sitenin kökünde ve herkese açık:',
      '<b>Where it is written.</b> The same condition stands where machines read it, just as it stands on this page where people read it. All three are at the root of this site and open to everyone:'
    ) ?></p>
    <ul>
      <li><a href="/llms.txt"><code>/llms.txt</code></a> <?= k_c('koşulun düz yazıyla anlatımı', 'the condition in plain words') ?></li>
      <li><a href="/license.xml"><code>/license.xml</code></a> <?= k_c('makine okunur lisans bildirimi (RSL 1.0)', 'the machine readable licence declaration (RSL 1.0)') ?></li>
      <li><a href="/robots.txt"><code>/robots.txt</code></a> <?= k_c('tarayıcılara açık davet ve lisans satırı', 'the open invitation to crawlers and the licence line') ?></li>
    </ul>
    <p><?= k_c(
      '<b>Yazar için ne demek.</b> Buraya gönderilen bir çalışma, bir dil modelinin eğitim verisine girebilir. Bu bir yan etki değil, bilerek açık bırakılmış bir kapıdır ve bu yüzden gönderim koşullarında da yazılıdır. Karşılığında sistemin istediği şey paraya değil ada bağlıdır: o metinden türetilen her çıktının çalışmanın künyesini, kalıcı kimliğini ve bağlantısını taşıması gerekir. Bunun uygulanacağının güvencesi yoktur; ama istenmediği takdirde hiç uygulanmayacağı kesindir.',
      '<b>What this means for an author.</b> A work sent here may enter the training data of a language model. That is not a side effect but a door deliberately left open, and for that reason it is also written into the submission conditions. What the system asks in return is tied to a name, not to money: any output derived from that text must carry the work\'s citation, its permanent identifier and its link. There is no guarantee this will be honoured; but it is certain that it will never be honoured if it is never asked.'
    ) ?></p>

    <h2 id="birde"><?= k_c('Bir de şu var', 'One more thing') ?></h2>
    <p><?= k_c(
      'Bu sistemin yazılımı yapay zekâ araçlarının yardımıyla geliştirildi. Bu bilgi gizlenmez, burada yazılıdır. Sistem tek bir kişinin işi değildir: kurallar, ölçüler ve bu sayfadaki metinler kurucu kurulun tartışmalarıyla biçimlendi; yayın ilkeleri, hakemlik ölçütleri ve içerik kuralları kurul kararlarıyla belirlendi. Yazılım bu kararların koda çevrilmesiyle ortaya çıktı ve kod bir aracın yardımıyla yazıldı. Söylenmek istenen şudur: araç, sorumluluğu ortadan kaldırmaz. Bu sistemde ne varsa arkasında adıyla duran insanlar vardır ve adları kurul sayfasında yazılıdır.',
      'The software of this system was developed with the help of artificial intelligence tools. That is not hidden; it is written here. The system is not one person\'s work: the rules, the measures and the texts on this page took shape through the discussions of the founding board, and the publishing principles, review criteria and content rules were settled by board decisions. The software came about as those decisions were turned into code, and the code was written with the help of a tool. What this says is that the tool does not remove the responsibility. Whatever is in this system has people standing behind it under their own names, and those names are written on the board page.'
    ) ?></p>

    <p class="yz-son"><?= k_c(
      'Bu sayfa bir yasak listesi değil, bir ölçü metnidir. Zamanla, uygulamada görülenlere göre güncellenir. Görüşünüz varsa lütfen iletin; bu metin ortak bir metindir. Sistemin kuruluş metni için ',
      'This page is not a list of prohibitions but a statement of measure. It will be updated over time in the light of what practice shows. If you have a view, please send it; this text belongs to all of us. For the founding text of the system see '
    ) ?><a href="<?= k_esc(k_bag('/bildiri.php')) ?>"><?= k_c('Bildiri', 'the Declaration') ?></a><?= k_c(' sayfasına, gönderim kurallarının tamamı için ', ', and for the full submission rules see ') ?><a href="<?= k_esc(k_bag('/ilkeler.php')) ?>"><?= k_c('Yayın ilkeleri', 'Editorial policies') ?></a><?= k_c(' sayfasına bakabilirsiniz.', '.') ?></p>

   </div>

   <?= k_belge_yan($bolumler, [
     ['tr' => 'Kısaca', 'en' => 'In short',
      'ic' => k_c(
        'Yapay zekâ metnin biçimine dokunabilir, metnin iddiasına dokunamaz. Her çalışmada kullanım beyanı sorulur ve çalışmanın sayfasında yayımlanır.',
        'Artificial intelligence may touch the form of a text, never its claim. Every submission is asked to declare its use, and the declaration is published on the work\'s own page.'
      )],
     ['tr' => 'İlgili sayfalar', 'en' => 'Related pages',
      'ic' => '<a href="' . k_esc(k_bag('/ilkeler.php')) . '">' . k_c('Yayın ilkeleri', 'Editorial policies') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/bildiri.php')) . '">' . k_c('Bildiri', 'Declaration') . '</a><br>'
            . '<a href="' . k_esc(k_bag('/hakemlik.php')) . '">' . k_c('Hakemlik süreci', 'Peer review process') . '</a><br>'
            . '<a href="/llms.txt">' . k_c('Makinelere söylenen', 'What is said to machines') . '</a>'],
   ]) ?>
  </div>
</section>
<?php k_son(<<<JS
<script>
(function(){
  var EN = document.documentElement.lang === 'en';
  document.querySelectorAll('[data-kopya]').forEach(function(b){
    b.addEventListener('click', function(){
      var a = document.getElementById(b.dataset.kopya);
      if (!a) return;
      var eski = b.textContent;
      var bitir = function(iyi){
        b.textContent = iyi ? (EN ? 'Copied' : 'Kopyalandı') : (EN ? 'Could not copy' : 'Kopyalanamadı');
        setTimeout(function(){ b.textContent = eski; }, 1600);
      };
      if (window.K && K.kopyala) K.kopyala(a.value).then(function(){bitir(true);}).catch(function(){bitir(false);});
      else { a.select(); try { document.execCommand('copy'); bitir(true); } catch(e){ bitir(false); } }
    });
  });
})();
</script>
JS
); ?>
