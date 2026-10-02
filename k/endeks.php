<?php
/* =====================================================================
   KUTADGU - Dizin ve çeyreklik kataloğu / Index and quartile catalogue
   Hakem, kabul yönünde karar verdiğinde çalışmanın hangi dizinde
   yayımlanabilir nitelikte olduğunu işaretler; her dizin için o dizinin
   ölçütlerini yoklayan beşli Likert anketi doldurur.
   Anket bir kez gönderilir, sonrasında değiştirilemez.
   ===================================================================== */

if (!function_exists('kt_alanlar')) {

    /* ---- Bilim alanları ---- */
    function kt_alanlar(): array {
        return [
            'sag' => ['Sağlık ve yaşam bilimleri', 'Health and life sciences'],
            'fen' => ['Fen bilimleri ve mühendislik', 'Natural sciences and engineering'],
            'sos' => ['Sosyal ve beşerî bilimler',   'Social sciences and humanities'],
            'ikt' => ['İktisat, işletme ve maliye',  'Economics, business and finance'],
            'egt' => ['Eğitim bilimleri',            'Educational sciences'],
            'hkk' => ['Hukuk',                       'Law'],
            'san' => ['Sanat ve tasarım',            'Arts and design'],
        ];
    }

    function kt_alan_ad(string $k, bool $en = false): string {
        $a = kt_alanlar();
        return isset($a[$k]) ? tg_t(['tr' => $a[$k][0], 'en' => $a[$k][1]], $en) : '';
    }

    /* ---- Likert seçenekleri ---- */
    function kt_likert(bool $en = false): array {
        return $en
            ? [1 => 'Strongly disagree', 2 => 'Disagree', 3 => 'Undecided', 4 => 'Agree', 5 => 'Strongly agree']
            : [1 => 'Kesinlikle katılmıyorum', 2 => 'Katılmıyorum', 3 => 'Kararsızım', 4 => 'Katılıyorum', 5 => 'Kesinlikle katılıyorum'];
    }

    /**
     * Dizin kataloğu.
     * alan  : hangi bilim alanlarında sunulacağı ('*' hepsi)
     * tur   : 'dizin' | 'ceyreklik'
     * olcut : o dizinin ölçütlerini yoklayan beşli Likert önermeleri
     */
    function kt_endeksler(): array {
        return [

            'trdizin' => [
                'ad' => ['TR Dizin', 'TR Dizin'],
                'alan' => '*', 'tur' => 'dizin',
                'aciklama' => ['TÜBİTAK ULAKBİM ulusal dizini', 'The national index of TUBITAK ULAKBIM'],
                'olcut' => [
                    ['Çalışma özgün bir araştırma sorusu ortaya koyar ve alanyazına ölçülebilir bir katkı sunar.',
                     'The work sets out an original research question and makes a measurable contribution to the literature.'],
                    ['Yöntem bölümü, çalışmanın bağımsız biçimde yeniden üretilmesine yetecek ayrıntıdadır.',
                     'The methods section is detailed enough for the study to be reproduced independently.'],
                    ['Etik kurul izni gerekiyorsa bilgisi metinde açıkça yer alır; gerekmiyorsa bu durum gerekçelendirilmiştir.',
                     'Where ethics committee approval is required its details appear in the text; where it is not, this is justified.'],
                    ['Kaynakça güncel, konuyla doğrudan ilgili ve biçimsel olarak tutarlıdır.',
                     'The bibliography is current, directly relevant and formally consistent.'],
                    ['Türkçe ve İngilizce özetler çalışmanın bulgularını doğru biçimde yansıtır.',
                     'The Turkish and English abstracts accurately reflect the findings of the study.'],
                ],
            ],

            'sobiad' => [
                'ad' => ['SOBİAD', 'SOBIAD'],
                'alan' => ['sos', 'ikt', 'egt', 'hkk', 'san'], 'tur' => 'dizin',
                'aciklama' => ['Sosyal bilimler atıf dizini', 'Social sciences citation index'],
                'olcut' => [
                    ['Çalışma sosyal bilimler alanyazınında tanımlı bir boşluğa karşılık gelir.',
                     'The work answers to a defined gap in the social sciences literature.'],
                    ['Kavramsal çerçeve kuramsal bir temele oturtulmuştur.',
                     'The conceptual framework rests on a theoretical foundation.'],
                    ['Bulgular veriyle desteklenmiş, yorum ile bulgu birbirinden ayrılmıştır.',
                     'Findings are supported by data, and interpretation is kept distinct from finding.'],
                    ['Atıf düzeni tutarlı ve izlenebilirdir.',
                     'The citation practice is consistent and traceable.'],
                    ['Çalışma Türkçe alanyazına da katkı sunar niteliktedir.',
                     'The work also contributes to the Turkish language literature.'],
                ],
            ],

            'esci' => [
                'ad' => ['Web of Science · ESCI', 'Web of Science ESCI'],
                'alan' => '*', 'tur' => 'dizin',
                'aciklama' => ['Emerging Sources Citation Index', 'Emerging Sources Citation Index'],
                'olcut' => [
                    ['Çalışma uluslararası okur için anlaşılır ve ilgi çekici bir soruna eğilir.',
                     'The work addresses a problem intelligible and of interest to an international readership.'],
                    ['İngilizce anlatım, hakemli uluslararası yayın düzeyindedir.',
                     'The English expression is at the level of international peer reviewed publication.'],
                    ['Kaynakçada uluslararası alanyazın yeterince temsil edilmiştir.',
                     'International literature is adequately represented in the bibliography.'],
                    ['Yöntem, alanın kabul görmüş standartlarına uygundur.',
                     'The method conforms to the accepted standards of the field.'],
                    ['Bulgular yerel bir örneklemin ötesinde genellenebilir bir tartışma açar.',
                     'The findings open a discussion generalisable beyond a local sample.'],
                ],
            ],

            'ssci' => [
                'ad' => ['Web of Science · SSCI', 'Web of Science SSCI'],
                'alan' => ['sos', 'ikt', 'egt', 'hkk'], 'tur' => 'dizin',
                'aciklama' => ['Social Sciences Citation Index', 'Social Sciences Citation Index'],
                'olcut' => [
                    ['Çalışma sosyal bilimlerde kuramsal ya da yöntemsel bir ilerleme sağlar.',
                     'The work advances theory or method in the social sciences.'],
                    ['Örneklem ve veri toplama süreci, sonuçları taşıyacak güçtedir.',
                     'The sample and the data collection process are strong enough to carry the conclusions.'],
                    ['Çözümleme yöntemi araştırma sorusuna uygundur ve varsayımları sınanmıştır.',
                     'The analytical method suits the research question and its assumptions have been tested.'],
                    ['Sınırlılıklar açıkça belirtilmiş, sonuçlar bu sınırlar içinde tartışılmıştır.',
                     'Limitations are stated openly and results are discussed within those limits.'],
                    ['Çalışma uluslararası karşılaştırmaya elverişli bir çerçeve sunar.',
                     'The work offers a framework amenable to international comparison.'],
                ],
            ],

            'scie' => [
                'ad' => ['Web of Science · SCI-E', 'Web of Science SCI-E'],
                'alan' => ['fen', 'sag'], 'tur' => 'dizin',
                'aciklama' => ['Science Citation Index Expanded', 'Science Citation Index Expanded'],
                'olcut' => [
                    ['Deneysel ya da gözlemsel tasarım, ileri sürülen nedenselliği destekler.',
                     'The experimental or observational design supports the causality claimed.'],
                    ['Ölçüm araçları, kalibrasyon ve belirsizlik bilgileri verilmiştir.',
                     'Instruments, calibration and uncertainty information are provided.'],
                    ['İstatistiksel çözümleme uygun ve yeterli güçtedir.',
                     'The statistical analysis is appropriate and adequately powered.'],
                    ['Veri ve kodlar erişilebilir kılınmış, yeniden üretilebilirlik sağlanmıştır.',
                     'Data and code are made accessible and reproducibility is ensured.'],
                    ['Bulgular alandaki güncel literatürle karşılaştırmalı olarak tartışılmıştır.',
                     'Findings are discussed comparatively against current literature in the field.'],
                ],
            ],

            'ahci' => [
                'ad' => ['Web of Science · AHCI', 'Web of Science AHCI'],
                'alan' => ['sos', 'san'], 'tur' => 'dizin',
                'aciklama' => ['Arts and Humanities Citation Index', 'Arts and Humanities Citation Index'],
                'olcut' => [
                    ['Çalışma birincil kaynaklara dayanır ve bunları eleştirel biçimde kullanır.',
                     'The work rests on primary sources and uses them critically.'],
                    ['Yorum, metin içi kanıtla adım adım gerekçelendirilmiştir.',
                     'Interpretation is justified step by step with textual evidence.'],
                    ['Alanın kuramsal tartışmalarıyla ilişki kurulmuştur.',
                     'A relation is established with the theoretical debates of the field.'],
                    ['Anlatım, uzman olmayan bir okurun da izleyebileceği açıklıktadır.',
                     'The exposition is clear enough for a non specialist reader to follow.'],
                    ['Çalışma alanyazında özgün bir okuma önerir.',
                     'The work proposes an original reading within the literature.'],
                ],
            ],

            'scopus' => [
                'ad' => ['Scopus', 'Scopus'],
                'alan' => '*', 'tur' => 'dizin',
                'aciklama' => ['Elsevier Scopus', 'Elsevier Scopus'],
                'olcut' => [
                    ['Çalışmanın amacı, yöntemi ve bulguları özet düzeyinde bile açıkça ayırt edilebilir.',
                     'The aim, method and findings of the work are clearly distinguishable even at abstract level.'],
                    ['Etik beyanlar ve çıkar çatışması bildirimi eksiksizdir.',
                     'Ethical statements and the conflict of interest declaration are complete.'],
                    ['Kaynakların en az bir bölümü son beş yıla aittir.',
                     'At least a portion of the sources date from the last five years.'],
                    ['Anahtar kelimeler çalışmanın bulunabilirliğini sağlayacak nitelikte seçilmiştir.',
                     'Keywords are chosen so as to make the work discoverable.'],
                    ['Çalışma, uluslararası bir okur kitlesine hitap eden bir sonuç üretir.',
                     'The work produces a conclusion addressed to an international readership.'],
                ],
            ],

            'pubmed' => [
                'ad' => ['PubMed / MEDLINE', 'PubMed / MEDLINE'],
                'alan' => ['sag'], 'tur' => 'dizin',
                'aciklama' => ['Biyomedikal alanyazın dizini', 'Index of biomedical literature'],
                'olcut' => [
                    ['Araştırma sorusu biyomedikal ya da klinik bir soruna doğrudan karşılık gelir.',
                     'The research question corresponds directly to a biomedical or clinical problem.'],
                    ['İnsan ya da hayvan denek kullanıldıysa etik kurul onayı ve onam süreci belgelenmiştir.',
                     'Where human or animal subjects were used, ethics approval and the consent process are documented.'],
                    ['Çalışma tasarımı ilgili raporlama kılavuzuna uygundur (CONSORT, PRISMA, STROBE vb.).',
                     'The study design conforms to the relevant reporting guideline (CONSORT, PRISMA, STROBE and the like).'],
                    ['Örneklem büyüklüğü ve istatistiksel güç gerekçelendirilmiştir.',
                     'Sample size and statistical power are justified.'],
                    ['Bulguların klinik ya da halk sağlığı açısından anlamı tartışılmıştır.',
                     'The clinical or public health significance of the findings is discussed.'],
                ],
            ],

            'econlit' => [
                'ad' => ['EconLit', 'EconLit'],
                'alan' => ['ikt'], 'tur' => 'dizin',
                'aciklama' => ['Amerikan İktisat Derneği dizini', 'Index of the American Economic Association'],
                'olcut' => [
                    ['Çalışma iktisat yazınında tanımlı bir soruna kuramsal ya da görgül katkı sunar.',
                     'The work makes a theoretical or empirical contribution to a defined problem in the economics literature.'],
                    ['Model kurgusu ve varsayımlar açıkça yazılmıştır.',
                     'The model set up and its assumptions are stated openly.'],
                    ['Veri kaynağı, dönem ve değişken tanımları tam olarak verilmiştir.',
                     'Data source, period and variable definitions are given in full.'],
                    ['Tahmin yöntemi seçimi gerekçelendirilmiş, sağlamlık sınamaları yapılmıştır.',
                     'The choice of estimation method is justified and robustness checks are carried out.'],
                    ['JEL sınıflaması çalışmanın içeriğiyle uyumludur.',
                     'The JEL classification matches the content of the work.'],
                ],
            ],

            'eric' => [
                'ad' => ['ERIC', 'ERIC'],
                'alan' => ['egt'], 'tur' => 'dizin',
                'aciklama' => ['Eğitim bilimleri dizini', 'Index of educational sciences'],
                'olcut' => [
                    ['Çalışma eğitim uygulamasına ya da politikasına doğrudan bir katkı sunar.',
                     'The work makes a direct contribution to educational practice or policy.'],
                    ['Katılımcı grubu ve bağlam ayrıntılı biçimde tanımlanmıştır.',
                     'The participant group and the context are described in detail.'],
                    ['Ölçme araçlarının geçerlik ve güvenirlik bilgileri verilmiştir.',
                     'Validity and reliability information for the instruments is provided.'],
                    ['Bulgular öğrenme çıktılarıyla ilişkilendirilmiştir.',
                     'Findings are related to learning outcomes.'],
                    ['Uygulayıcılar için çıkarımlar açıkça yazılmıştır.',
                     'Implications for practitioners are stated explicitly.'],
                ],
            ],

            'doaj' => [
                'ad' => ['DOAJ', 'DOAJ'],
                'alan' => '*', 'tur' => 'dizin',
                'aciklama' => ['Açık erişimli dergiler dizini', 'Directory of Open Access Journals'],
                'olcut' => [
                    ['Çalışma açık erişim ilkelerine uygundur ve erişim engeli içermez.',
                     'The work conforms to open access principles and carries no access barrier.'],
                    ['Lisans koşulları ve yeniden kullanım hakları açıkça belirtilmiştir.',
                     'Licence terms and reuse rights are stated openly.'],
                    ['Hakemlik süreci şeffaf biçimde belgelenmiştir.',
                     'The peer review process is transparently documented.'],
                    ['Üstveri (yazar, kurum, kimlik numaraları) eksiksizdir.',
                     'The metadata (author, affiliation, identifiers) is complete.'],
                    ['Çalışma kalıcı bir kimlikle erişilebilir kılınmıştır.',
                     'The work is made accessible through a permanent identifier.'],
                ],
            ],

            'q' => [
                'ad' => ['Çeyreklik değerlendirmesi', 'Quartile assessment'],
                'alan' => '*', 'tur' => 'ceyreklik',
                'aciklama' => ['Çalışmanın hangi çeyreklikteki bir dergide yayımlanabilir nitelikte olduğu',
                               'The quartile of journal in which the work could be published'],
                'olcut' => [
                    ['Çalışmanın özgünlüğü, üst çeyreklikteki bir dergi için yeterlidir.',
                     'The originality of the work suffices for a journal in an upper quartile.'],
                    ['Yöntemsel titizlik uluslararası üst düzey dergilerin beklentisini karşılar.',
                     'The methodological rigour meets the expectation of leading international journals.'],
                    ['Bulguların etkisi dar bir uzmanlık çevresinin ötesine geçer.',
                     'The impact of the findings extends beyond a narrow specialist circle.'],
                    ['Anlatım ve sunum düzeyi uluslararası yayın standardındadır.',
                     'The level of exposition and presentation is at international publication standard.'],
                    ['Çalışma yayımlandığında atıf alma potansiyeli taşır.',
                     'The work carries the potential to attract citations once published.'],
                ],
                'secim' => ['Q1', 'Q2', 'Q3', 'Q4'],
            ],
        ];
    }

    /* Bir alana uygun dizinler */
    function kt_alana_gore(string $alan): array {
        $out = [];
        foreach (kt_endeksler() as $k => $e) {
            if ($e['alan'] === '*' || ($alan !== '' && in_array($alan, (array)$e['alan'], true))) $out[$k] = $e;
            elseif ($alan === '') $out[$k] = $e;   /* alan bilinmiyorsa hepsi gösterilir */
        }
        return $out;
    }

    /* Tarayıcıya gönderilecek sade katalog */
    function kt_katalog_json(string $alan, bool $en): string {
        $liste = [];
        foreach (kt_alana_gore($alan) as $k => $e) {
            $liste[$k] = [
                'ad'    => tg_t(['tr' => $e['ad'][0], 'en' => $e['ad'][1]], $en),
                'alan'  => $e['alan'],
                'ack'   => tg_t(['tr' => $e['aciklama'][0], 'en' => $e['aciklama'][1]], $en),
                'tur'   => $e['tur'],
                'secim' => $e['secim'] ?? null,
                'olcut' => array_map(fn($o) => tg_t(['tr' => $o[0], 'en' => $o[1]], $en), $e['olcut']),
            ];
        }
        return (string)json_encode([
            'endeks' => $liste,
            'likert' => kt_likert($en),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
