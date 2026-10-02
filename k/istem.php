<?php
/* =====================================================================
   İSTEM PENCERELERİ — ORTAK KAYNAK
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 19 Ağustos 2026, kurul bildirimi: "anahtar kelime
   olsun tam metin olsun bunları yz ile de yapabilir dedik ya, hatta
   istemde önerdik."

   Doğruydu ve eksik olan yer belliydi. Gönderim formu, künye dilindeki
   metni yapay zekâyla üretmeyi AÇIKÇA öneriyor ve dört tane hazır istem
   veriyordu. Ama o metinlerin çoğu gönderim anında değil, KABULDEN
   SONRA yazar panelinde giriliyor: tam metin, anahtar kelimeler,
   kaynakça. Panelde ise ne öneri vardı ne istem. Sistem, bir yerde
   verdiği yardımı, o yardımın asıl gerektiği yerde vermiyordu.

   NEDEN AYRI BİR DOSYA. İstemler iki yüz satır metindir. Panele
   kopyalansaydı aynı metin iki yerde dururdu ve biri bir gün ötekinden
   habersiz değişirdi — bu sistemde tek kaynak kuralının varlık nedeni
   tam olarak budur. Pencere, biçimi ve betiği artık burada; iki sayfa
   da buradan çağırır.

   ÜÇ PARÇA:
     k_istem_stil()          — kaplama ve istem kutusu biçimi
     k_istem_ceviri()        — çeviri istemleri penceresinin kendisi
     k_istem_betik($esles)   — açma, kapama, Esc ve kopyalama düzeneği

   BETİK KENDİ DİZELERİNİ TAŞIR. Çağıran sayfanın $S sözlüğüne
   bağlansaydı, sözlüğü olmayan bir sayfaya konduğu gün kopyala düğmesi
   sessizce "undefined" yazardı.
   ===================================================================== */
declare(strict_types=1);

if (!function_exists('k_istem_stil')) {

    function k_istem_stil(): string {
        return <<<'CSS'
/* İstem penceresi. Dizgede bir kaplama bileşeni yok; buradaki tek
   kullanımı için sayfada duruyor. */
.mod-ov{position:fixed;inset:0;display:none;align-items:flex-start;justify-content:center;
  padding:var(--b-5) var(--b-3);z-index:200;overflow-y:auto;
  background:color-mix(in srgb,var(--marka-koyu) 66%,transparent)}
.mod-ov.acik{display:flex}
.mod{background:var(--yuzey);border:1px solid var(--cizgi);border-radius:var(--r-4);
  max-width:820px;width:100%;padding:var(--b-5);position:relative;box-shadow:var(--g-3)}
.mod .kapa{position:absolute;top:var(--b-3);right:var(--b-3)}
.mod-alt{font-size:var(--y-3);color:var(--metin-2)}
.mod-alt-son{margin:var(--b-4) 0 0}
/* Tek tek kopyalanan istemler. Metin kutusu kendi içinde kaydırılır ki
   pencere on ekran boyu uzamasın. */
.pr{border:1px solid var(--cizgi);border-radius:var(--r-3);margin-top:var(--b-4);overflow:hidden}
.pr-bas{display:flex;gap:var(--b-3);align-items:flex-start;justify-content:space-between;
  padding:var(--b-3) var(--b-4);background:var(--yuzey-2)}
.pr-bas b{font-size:var(--y-4)}
.pr-bas small{display:block;color:var(--metin-2);margin-top:2px}
.pr-metin{padding:var(--b-3) var(--b-4);font-family:var(--mono);font-size:var(--y-2);
  white-space:pre-wrap;max-height:230px;overflow-y:auto;color:var(--metin-2);
  border-top:1px solid var(--cizgi)}
CSS;
    }

    function k_istem_ceviri(): string {
        ob_start(); ?>
<div class="mod-ov" id="cvOv">
  <div class="mod">
    <button class="d d-sessiz d-im kapa" type="button" data-kapat="1">&#10005;</button>
    <h3 style="margin-top:0"><?= k_c('Çeviri istemleri', 'Translation prompts') ?></h3>
    <p class="mod-alt"><?= k_c(
      'Bu istemler, çevirinin <b>sizin üslubunuzu</b> taşıması için yazıldı: cümle kuruluşunuz, vurgunuz ve akıl yürütme sıranız korunur; metin "akademik İngilizce"ye düzleştirilmez. Üç yasak her istemin içinde yazılıdır ve pazarlığa açık değildir: <b>kaynakçaya dokunulmaz</b>, <b>yeni bilgi eklenmez</b>, <b>var olan bilgi çıkarılmaz</b>. Model bir şeyi anlamadıysa uydurmak yerine işaretlemekle yükümlüdür. Son karar sizindir: çıkan metni okumadan göndermeyin, çünkü sorumluluk çevirene değil <b>yazara</b> aittir.',
      'These prompts are written so that the translation carries <b>your own voice</b>: your sentence construction, your emphasis and the order of your reasoning are preserved; the text is not flattened into generic "academic English". Three prohibitions are written into every prompt and are not negotiable: <b>the bibliography is not touched</b>, <b>no information is added</b>, <b>no existing information is removed</b>. Where the model does not understand something it must flag it rather than invent. The final decision is yours: do not submit text you have not read, because responsibility rests with the <b>author</b>, not the translator.'
    ) ?></p>

    <div class="pr">
      <div class="pr-bas"><div><b>1. <?= k_c('Üslubu koruyan çeviri', 'Translation that preserves voice') ?></b><small><?= k_c('Kısa özet ve başlık için', 'For the title and the short abstract') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Sen akademik metin çeviren bir çevirmensin. Aşağıdaki metni [HEDEF DİL] diline çevir.

ÜSLUP KORUNUR. Bu bir yeniden yazım değil, bir çeviridir:
- Cümle uzunluklarımı ve bölünme noktalarımı elimden geldiğince koru. Uzun bir cümlemi üçe bölme, kısa cümlelerimi birleştirme.
- Vurgumu koru: neyi öne aldıysam önde kalsın.
- Akıl yürütme sıramı değiştirme. Sonucu başa alma.
- Metnimi "akademik İngilizceye" düzleştirme. Kendi sesim silinirse çeviri başarısız sayılır.
- Alanın yerleşik terimlerini kullan; ama benim özel olarak tanımladığım bir terimi kendi karşılığınla değiştirme, benim tanımımı taşı.

ÜÇ YASAK:
1. KAYNAKÇAYA VE ATIFLARA DOKUNMA. Yazar adları, yıllar, dergi adları, cilt/sayı/sayfa numaraları, DOI ve bağlantılar OLDUĞU GİBİ kalır. Bir künyeyi düzeltme, tamamlama, biçimini değiştirme ya da sıralamasını bozma. Metin içi atıflar (Yılmaz, 2019) aynen kalır.
2. BİLGİ EKLEME. Metinde olmayan hiçbir bulgu, sayı, örnek, gerekçe ya da bağlam cümlesi ekleme. "Akıcı olsun" diye açıklayıcı cümle yazma.
3. BİLGİ ÇIKARMA. Hiçbir cümleyi kısaltma, atlama ya da özetleme. Fazla bulduğun bir yer varsa bile aynen çevir.

ANLAMADIĞINDA UYDURMA. Bir yeri çözemezsen çeviriyi orada [?ÇEVİRMEN NOTU: ...] biçiminde işaretle ve neyi anlamadığını yaz. İşaretlenmiş bir yer, yanlış çevrilmiş bir yerden iyidir.

ÇIKTI: yalnızca çeviri metni. Açıklama, giriş cümlesi ya da "işte çeviriniz" yazma.

METİN:
"""
[METNİ BURAYA YAPIŞTIRIN]
"""',
'You are a translator of academic texts. Translate the text below into [TARGET LANGUAGE].

VOICE IS PRESERVED. This is a translation, not a rewrite:
- Keep my sentence lengths and my breaking points as far as the language allows. Do not split one long sentence of mine into three, and do not merge my short ones.
- Keep my emphasis: whatever I put first stays first.
- Do not change the order of my reasoning. Do not move the conclusion to the front.
- Do not flatten my text into generic "academic English". If my own voice disappears, the translation has failed.
- Use the established terms of the field; but where I have defined a term myself, carry my definition rather than substituting your own.

THREE PROHIBITIONS:
1. DO NOT TOUCH THE BIBLIOGRAPHY OR THE CITATIONS. Author names, years, journal titles, volume/issue/page numbers, DOIs and links stay EXACTLY as they are. Do not correct, complete, reformat or reorder an entry. In text citations (Yilmaz, 2019) stay as they are.
2. DO NOT ADD INFORMATION. Do not add any finding, number, example, justification or context sentence that is not in the text. Do not write explanatory sentences "for fluency".
3. DO NOT REMOVE INFORMATION. Do not shorten, skip or summarise any sentence, even where you find it redundant.

DO NOT INVENT WHERE YOU DO NOT UNDERSTAND. If you cannot resolve a passage, mark it in place as [?TRANSLATOR NOTE: ...] and say what you did not understand. A marked passage is better than a wrongly translated one.

OUTPUT: the translated text only. No commentary, no opening sentence, no "here is your translation".

TEXT:
"""
[PASTE THE TEXT HERE]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>2. <?= k_c('Genişletilmiş özet üretimi', 'Producing the extended abstract') ?></b><small><?= k_c('Tam metinden, çalışmanın kendi diliyle', 'From the full text, in the work\'s own terms') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıda bir akademik çalışmanın TAM METNİ var. Bundan [HEDEF DİL] dilinde bir GENİŞLETİLMİŞ ÖZET üret. Hedef uzunluk 750 kelime, en az 500.

GENİŞLETİLMİŞ ÖZET NEDİR: çalışmanın sorusunu, yöntemini, bulgularını ve sonucunu, o çalışmayı okumamış birinin ne yapıldığını anlayabileceği ayrıntıda anlatan metin. Kısa özetin uzatılmışı değildir; tam metnin kısaltılmışıdır.

ŞU SIRAYLA VE BAŞLIKLARLA YAZ:
- Amaç ve soru: çalışma neyi soruyor, neden önemli.
- Yöntem: veri, örneklem, teknik, dönem. Sayılar varsa aynen aktar.
- Bulgular: en önemli üç ila beş bulgu. Her biri metindeki sayıyla.
- Sonuç ve katkı: alana ne ekliyor, sınırları ne.

ÜÇ YASAK:
1. KAYNAKÇAYA VE ATIFLARA DOKUNMA. Özet içinde atıf vereceksen metindeki biçimiyle ver; künye üretme, düzeltme, tamamlama.
2. BİLGİ EKLEME. Metinde olmayan hiçbir bulgu, sayı, yorum ya da "literatürde bilindiği gibi" cümlesi yazma. Bir sayıyı yuvarlama.
3. YORUM KATMA. Çalışmanın savını güçlendirme, zayıflatma ya da değerlendirme. Sen özetliyorsun, hakemlik yapmıyorsun.

ÜSLUP: yazarın kendi anlatımını taşı. Yazar bir şeyi ihtiyatla söylüyorsa ("olabilir", "eğilim göstermektedir") sen de ihtiyatla söyle; kesinleştirme.

BİR ŞEY BULAMAZSAN UYDURMA. Metinde yöntem açıkça yazılmamışsa "yöntem metinde açıkça belirtilmemiştir" yaz.

ÇIKTI: yalnızca genişletilmiş özet.

TAM METİN:
"""
[TAM METNİ BURAYA YAPIŞTIRIN]
"""',
'Below is the FULL TEXT of an academic work. Produce an EXTENDED ABSTRACT from it in [TARGET LANGUAGE]. Target length 750 words, minimum 500.

WHAT AN EXTENDED ABSTRACT IS: a text that conveys the question, the method, the findings and the conclusion in enough detail that someone who has not read the work can understand what was done. It is not a lengthened short abstract; it is a shortened full text.

WRITE IN THIS ORDER, WITH THESE HEADINGS:
- Aim and question: what the work asks, and why it matters.
- Method: data, sample, technique, period. Carry any numbers exactly.
- Findings: the three to five most important findings, each with its number from the text.
- Conclusion and contribution: what it adds to the field, and its limits.

THREE PROHIBITIONS:
1. DO NOT TOUCH THE BIBLIOGRAPHY OR THE CITATIONS. If you cite within the abstract, cite in the form used in the text; do not generate, correct or complete an entry.
2. DO NOT ADD INFORMATION. Do not write any finding, number, interpretation or "as is well known in the literature" sentence that is not in the text. Do not round a number.
3. DO NOT ADD JUDGEMENT. Do not strengthen, weaken or evaluate the argument. You are summarising, not reviewing.

VOICE: carry the author\'s own manner. Where the author hedges ("may", "tends to"), hedge as well; do not make it definite.

DO NOT INVENT WHAT YOU CANNOT FIND. If the method is not stated explicitly, write "the method is not stated explicitly in the text".

OUTPUT: the extended abstract only.

FULL TEXT:
"""
[PASTE THE FULL TEXT HERE]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>3. <?= k_c('Terim tutarlılığı', 'Terminological consistency') ?></b><small><?= k_c('Aynı kavram her yerde aynı sözcükle mi', 'Is the same concept rendered by the same word throughout') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıda bir metnin özgün hâli ve [HEDEF DİL] çevirisi var. YALNIZCA TERİMLERE bak.

ŞUNLARI ÇIKAR:
1. Özgün metinde geçen alan terimlerinin listesi ve her birinin çeviride hangi karşılıkla verildiği.
2. TUTARSIZLIKLAR: aynı terimin çeviride birden çok karşılıkla verildiği yerler. Her birini satırıyla göster.
3. ALANDA YERLEŞİK OLMAYAN karşılıklar: alanın standart terimi varken başka bir sözcük kullanılmış yerler. Standart terimi öner ama DEĞİŞTİRME.
4. Yazarın kendi tanımladığı terimler: bunların çeviride yazarın tanımını taşıyıp taşımadığı.

METNİ DÜZELTME. Yalnızca listeyi çıkar; karar yazarındır.

ÖZGÜN:
"""
[ÖZGÜN METİN]
"""

ÇEVİRİ:
"""
[ÇEVİRİ]
"""',
'Below are the original text and its translation into [TARGET LANGUAGE]. Look ONLY at terminology.

PRODUCE:
1. A list of the field terms in the original and the rendering each was given in the translation.
2. INCONSISTENCIES: places where the same term is rendered by more than one word in the translation. Show each with its line.
3. RENDERINGS NOT ESTABLISHED IN THE FIELD: places where a different word is used although the field has a standard term. Propose the standard term but DO NOT change it.
4. Terms the author defines themselves: whether the translation carries the author\'s definition.

DO NOT CORRECT THE TEXT. Produce the list only; the decision is the author\'s.

ORIGINAL:
"""
[ORIGINAL TEXT]
"""

TRANSLATION:
"""
[TRANSLATION]
"""') ?></div>
    </div>

    <div class="pr">
      <div class="pr-bas"><div><b>4. <?= k_c('Sadakat denetimi: BAŞKA bir modelde', 'Fidelity check: on a DIFFERENT model') ?></b><small><?= k_c('Eklenen, çıkarılan ya da değiştirilen var mı', 'Anything added, removed or altered') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Bu istemi, çeviriyi YAPAN modelde değil BAŞKA bir modelde çalıştırın. Kendi işini kendine onaylatan bir denetim, denetim değildir.

Aşağıda bir metnin özgün hâli ve çevirisi var. Çeviriyi denetle ve YALNIZCA şunları bildir:

1. EKLENEN: çeviride bulunan ama özgün metinde karşılığı OLMAYAN her cümle, sayı, örnek ya da nitelemeler. Her birini alıntıla.
2. ÇIKARILAN: özgün metinde bulunan ama çeviride karşılığı olmayan her cümle ya da bilgi.
3. DEĞİŞEN SAYI: özgün ile çeviri arasında farklı çıkan her sayı, oran, tarih, örneklem büyüklüğü.
4. KAYNAKÇA VE ATIF: değiştirilmiş, düzeltilmiş, tamamlanmış, eklenmiş ya da düşmüş her künye ve her metin içi atıf. Bir yazar adının yazımı bile değiştiyse bildir.
5. KESİNLİK KAYMASI: özgün metinde ihtiyatlı söylenmiş bir şeyin çeviride kesin söylendiği (ya da tersi) yerler.

Hiçbir şey bulamazsan "fark bulunamadı" yaz. Övme, yorumlama, üslup değerlendirmesi yapma. Metni DÜZELTME.

ÖZGÜN:
"""
[ÖZGÜN METİN]
"""

ÇEVİRİ:
"""
[ÇEVİRİ]
"""',
'Run this prompt on a DIFFERENT model from the one that produced the translation. A check that has work approved by its own author is not a check.

Below are the original text and its translation. Audit the translation and report ONLY the following:

1. ADDED: every sentence, number, example or qualifier present in the translation with NO counterpart in the original. Quote each one.
2. REMOVED: every sentence or piece of information present in the original with no counterpart in the translation.
3. CHANGED NUMBERS: every number, ratio, date or sample size that differs between original and translation.
4. BIBLIOGRAPHY AND CITATIONS: every entry and in text citation that was altered, corrected, completed, added or dropped. Report even a change in the spelling of an author name.
5. SHIFTS IN CERTAINTY: places where something hedged in the original is stated definitely in the translation, or the reverse.

If you find nothing, write "no differences found". Do not praise, interpret or assess style. DO NOT correct the text.

ORIGINAL:
"""
[ORIGINAL TEXT]
"""

TRANSLATION:
"""
[TRANSLATION]
"""') ?></div>
    </div>

    <?php /* BEŞİNCİ İSTEM — ANAHTAR KELİME.
             Kurul bildirimi (19 Ağustos 2026) anahtar kelimeleri adıyla
             saydı ve haklıydı: dörtlü istem takımı başlığı, özü ve tam
             metni kapsıyordu, anahtar kelimeyi kapsamıyordu. Oysa
             anahtar kelime dizinlerde en çok işe yarayan ve yazarın en
             çok üşendiği alandır.

             İSTEM UYDURMAYI YASAKLAR. Anahtar kelime bir süsleme değil
             bir ERİŞİM aracıdır: metinde karşılığı olmayan bir terim,
             çalışmayı bulunmadığı bir rafa koyar. */ ?>
    <div class="pr">
      <div class="pr-bas"><div><b>5. <?= k_c('Anahtar kelime önerisi', 'Suggesting keywords') ?></b><small><?= k_c('Tam metinden, dizinlerin okuduğu biçimde', 'From the full text, in the form indexes read') ?></small></div><button class="d d-vurgu d-kucuk kopya" type="button"><?= k_c('Kopyala', 'Copy') ?></button></div>
      <div class="pr-metin"><?= k_c(
'Aşağıda bir akademik çalışmanın tam metni var. Bu çalışma için [HEDEF DİL] dilinde ANAHTAR KELİMELER öner.

NE İSTİYORUM: en çok altı, en az üç terim. Sıra önemlidir: en genelden başlama, çalışmayı en iyi ayırt eden terimden başla.

KURALLAR:
1. HER TERİMİN METİNDE KARŞILIĞI OLMALI. Metinde geçmeyen ya da metinden çıkmayan bir kavramı yazma. Anahtar kelime bir süsleme değil, bir erişim aracıdır: karşılığı olmayan terim, çalışmayı bulunmadığı bir rafa koyar.
2. BAŞLIKTAKİ SÖZCÜKLERİ TEKRARLAMA. Başlık zaten aranıyor; anahtar kelime başlığın yakalayamadığını yakalamalıdır.
3. ALANIN YERLEŞİK TERİMİNİ KULLAN. Bir kavramın alanda kabul görmüş karşılığı varsa onu yaz; kendi türettiğin bir terimi ancak yazar metinde öyle tanımlıyorsa kullan.
4. ÇOK GENEL TERİM YAZMA. Tek başına hiçbir şey ayırt etmeyen sözcükler işe yaramaz; gerekiyorsa daralt.
5. KISALTMA KULLANMA. Kısaltmanın açık halini yaz; kısaltma dizinlerde farklı alanlarda farklı şeylere karşılık gelir.
6. UYDURMA. Metinden altı terim çıkmıyorsa üç yaz ve neden az olduğunu bir cümleyle söyle.

ÇIKTI: virgülle ayrılmış tek satır. Altına, her terimin metnin hangi bölümünden geldiğini tek tek yaz ki denetleyebileyim.

METİN:
"""
[TAM METNİ BURAYA YAPIŞTIRIN]
"""',
'Below is the full text of an academic work. Suggest KEYWORDS for it in [TARGET LANGUAGE].

WHAT I WANT: at most six terms, at least three. Order matters: begin with the term that distinguishes this work best, not with the most general one.

RULES:
1. EVERY TERM MUST HAVE A BASIS IN THE TEXT. Do not write a concept that does not appear in, or follow from, the text. A keyword is not decoration but a means of access: a term with no basis files the work on a shelf it is not on.
2. DO NOT REPEAT WORDS FROM THE TITLE. The title is already searched; keywords should catch what the title cannot.
3. USE THE ESTABLISHED TERM OF THE FIELD. Where a concept has an accepted term in the field, use it; use a coinage of your own only if the author defines it that way in the text.
4. DO NOT WRITE VERY GENERAL TERMS. Words that distinguish nothing on their own are of no use; narrow them if needed.
5. DO NOT USE ABBREVIATIONS. Write the expanded form; an abbreviation stands for different things in different fields in an index.
6. DO NOT INVENT. If six terms do not come out of the text, write three and say in one sentence why there are fewer.

OUTPUT: a single line, comma separated. Beneath it, state for each term which part of the text it comes from, so that I can check it.

TEXT:
"""
[PASTE THE FULL TEXT HERE]
"""') ?></div>
    </div>

    <p class="mod-alt mod-alt-son">
      <b><?= k_c('Sorumluluk çevirende değil yazardadır.', 'Responsibility rests with the author, not the translator.') ?></b> <?= k_c(
        'Yapay zekâ ile çevirmek yasak değildir; <b>beyan edilmemesi</b> kabul edilemez. Kullandıysanız sekizinci adımda "dil ve düzenleme" ya da uygun seçeneği işaretleyin ve kapsamını yazın. Çevirinin kaynağı çalışmanın sayfasında da görünür: <b>yazar</b>, <b>insan çevirmen</b> ya da <b>makine</b>. Bir istem, okumadan gönderme hakkı vermez.',
        'Translating with an AI is not forbidden; <b>failing to declare it</b> is not acceptable. If you used one, mark the appropriate option at step eight and describe its extent. The source of a translation is shown on the work\'s page as well: <b>author</b>, <b>human translator</b> or <b>machine</b>. A prompt does not grant the right to submit text you have not read.'
      ) ?>
    </p>
  </div>
</div>
<?php   return (string)ob_get_clean();
    }

    /* $esles: ['acanDugmeId' => 'pencereId', ...]
       Birden çok düğme aynı pencereyi açabilir (gönderim formunda
       denetim istemleri iki ayrı adımdan açılıyor); bu yüzden anahtar
       düğme, değer penceredir. */
    function k_istem_betik(array $esles): string {
        $j = json_encode($esles, JSON_UNESCAPED_UNICODE);
        $kopyala    = json_encode(k_c('Kopyala', 'Copy'), JSON_UNESCAPED_UNICODE);
        $kopyalandi = json_encode(k_c('Kopyalandı', 'Copied'), JSON_UNESCAPED_UNICODE);
        /* BETİK KENDİ <script> KABUĞUNU TAŞIR.
           İlk yazımda çıplak dönüyordu ve k_son() onu olduğu gibi
           sayfaya yazıyordu: çağıran sayfanın kendi betiği zaten
           </script> ile bitiyor, bu yüzden düzenek METİN OLARAK sayfaya
           düşüyor ve hiçbir dinleyici bağlanmıyordu. Ölçüldü: düğme
           görünüyor, tıklanıyor, pencere açılmıyordu. Bir bileşenin
           döndürdüğü şey, tek başına geçerli olmalıdır. */
        return <<<JS
<script>
(function(){
  var ESLES = $j, KOPYALA = $kopyala, KOPYALANDI = $kopyalandi;
  function q(id){ return document.getElementById(id); }
  /* TEK DÜZENEK, KAÇ PENCERE OLURSA OLSUN.
     Açma, kapama, Esc ve kopyalama pencere başına ayrı yazılsaydı biri
     bir gün ötekinden ayrılırdı — nitekim ilk yazımda çeviri penceresi
     açılıyor ama Esc ile KAPANMIYOR ve kopyala düğmeleri çalışmıyordu,
     çünkü dinleyiciler yalnız bir pencereye bağlanmıştı. */
  var adlar = [], gorulen = {};
  Object.keys(ESLES).forEach(function(k){ if(!gorulen[ESLES[k]]){ gorulen[ESLES[k]]=1; adlar.push(ESLES[k]); } });
  var ovler = adlar.map(q).filter(Boolean);
  if(!ovler.length) return;
  function kapa(){
    ovler.forEach(function(o){ o.classList.remove('acik'); });
    document.body.style.overflow='';
  }
  Object.keys(ESLES).forEach(function(dId){
    var d=q(dId); if(!d) return;
    d.addEventListener('click',function(e){
      if(e)e.preventDefault();
      var o=q(ESLES[dId]); if(!o)return;
      o.classList.add('acik'); document.body.style.overflow='hidden';
    });
  });
  document.addEventListener('keydown',function(e){ if(e.key==='Escape')kapa(); });
  ovler.forEach(function(ov){
    ov.addEventListener('click',function(e){ if(e.target===ov||e.target.getAttribute('data-kapat'))kapa(); });
    Array.prototype.forEach.call(ov.querySelectorAll('.kopya'),function(b){
      b.addEventListener('click',function(){
        var m=b.closest('.pr').querySelector('.pr-metin').textContent;
        if(navigator.clipboard)navigator.clipboard.writeText(m);
        b.textContent=KOPYALANDI; setTimeout(function(){b.textContent=KOPYALA;},1600);
      });
    });
  });
})();
</script>
JS;
    }
}
