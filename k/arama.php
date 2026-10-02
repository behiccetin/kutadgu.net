<?php
/* =====================================================================
   KUTADGU - Arama motoru / Search engine
   ---------------------------------------------------------------------
   Web of Science'ın temel ve gelişmiş aramasının yaptığı işi, bu
   arşivin ölçeğine uygun biçimde yapar. Veri JSON dosyalarında durduğu
   ve arşiv birkaç bin kayıtla ölçüldüğü sürece, her sorguda tüm kaydı
   taramak hem yeterince hızlıdır hem de bir veritabanı bağımlılığı
   getirmez. Sistem taşınabilirliğini bundan alıyor.

   ARAMA ALANLARI (WoS'taki karşılıkları parantezte)
     hepsi    - başlık, özet, anahtar kelime, yazar, kurum   (ALL)
     baslik   - başlık                                        (TI)
     yazar    - yazar adı                                     (AU)
     ozet     - özet                                          (AB)
     anahtar  - anahtar kelimeler                             (AK)
     kurum    - yazarların kurumu                             (AD/OG)
     tamga    - kalıcı kimlik                                 (DOI benzeri)
     hakem    - hakem adı                                     (bu sisteme özgü)
     metin    - tam metin                                     (FT)

   İŞLEÇLER
     VE / VEYA / HARİÇ  (AND / OR / NOT)
   Satırlar sırayla uygulanır; ilk satırın işleci yok sayılır.
   "hariç" satırı, o ana kadar bulunan kümeden çıkarma yapar.

   İfade içinde tırnak tam öbek arar: "panel veri" gibi.
   Yıldız sonek joker olarak çalışır: iktisad* -> iktisadi, iktisadın.
   ===================================================================== */

require_once __DIR__ . '/veri.php';
require_once __DIR__ . '/alanlar.php';
/* Tür süzgecinin ölçütü k_suz_tur() ile aynıdır ve orada durur.
   Buraya ikinci bir kopya yazmak, iki sayfanın zamanla ayrı liste
   vermesi demektir; kopya değil bağımlılık kuruluyor. */
require_once __DIR__ . '/parca.php';

if (!function_exists('ar_alanlar')) {

    function ar_alanlar(?bool $en = null): array {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        return [
            'hepsi'   => k_c('Her yerde', 'All fields'),
            'baslik'  => k_c('Başlık', 'Title'),
            'yazar'   => k_c('Yazar', 'Author'),
            'ozet'    => k_c('Özet', 'Abstract'),
            'anahtar' => k_c('Anahtar kelime', 'Keywords'),
            'kurum'   => k_c('Kurum', 'Institution'),
            'metin'   => k_c('Tam metin', 'Full text'),
            'hakem'   => k_c('Hakem', 'Reviewer'),
            'tamga'   => k_c('Tamga', 'Identifier'),
        ];
    }

    function ar_islecler(?bool $en = null): array {
        if ($en === null) $en = function_exists('k_en') ? k_en() : false;
        return ['ve' => k_c('VE', 'AND'), 'veya' => k_c('VEYA', 'OR'), 'haric' => k_c('HARİÇ', 'NOT')];
    }

    /* Bir çalışmanın aranabilir metni, alan alan. Her iki dildeki metin
       birlikte taranır: İngilizce başlıkla arayan biri Türkçe kaydı da
       bulur, çünkü kayıt tek eserdir. */
    function ar_metin(array $y, string $alan): string {
        switch ($alan) {
            case 'baslik':
                return (string)($y['baslik'] ?? '') . ' ' . (string)($y['baslik_en'] ?? '');
            case 'ozet':
                return strip_tags((string)($y['ozet'] ?? '') . ' ' . (string)($y['ozet_en'] ?? ''));
            case 'anahtar':
                return tg_metin($y['anahtar'] ?? '') . ' ' . tg_metin($y['anahtar_en'] ?? '');
            case 'yazar':
                $ad = [trim((string)($y['yazar'] ?? ''))];
                $b = $y['yazar_bilgi'] ?? null;
                if (is_array($b)) $ad[] = (string)($b['ad'] ?? '');
                foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) { if (is_array($ya)) $ad[] = (string)($ya['ad'] ?? ''); }
                return implode(' ', array_filter($ad));
            case 'kurum':
                $k = [];
                $b = $y['yazar_bilgi'] ?? null;
                if (is_array($b)) $k[] = (string)($b['kurum'] ?? '');
                foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) { if (is_array($ya)) $k[] = (string)($ya['kurum'] ?? ''); }
                return implode(' ', array_filter($k));
            case 'hakem':
                $h = [];
                foreach (tg_dizi($y['hakemler'] ?? null) as $x) { if (is_array($x)) $h[] = (string)($x['ad'] ?? ''); }
                return implode(' ', array_filter($h));
            case 'tamga':
                return (string)($y['bcid'] ?? '') . ' ' . (string)($y['doi'] ?? '');
            case 'metin':
                return strip_tags((string)($y['metin'] ?? '') . ' ' . (string)($y['metin_en'] ?? '')
                     . ' ' . (string)($y['metin_ham'] ?? '') . ' ' . (string)($y['metin_ham_en'] ?? ''));
            case 'hepsi':
            default:
                return ar_metin($y, 'baslik') . ' ' . ar_metin($y, 'ozet') . ' ' . ar_metin($y, 'anahtar')
                     . ' ' . ar_metin($y, 'yazar') . ' ' . ar_metin($y, 'kurum') . ' ' . ar_metin($y, 'tamga');
        }
    }

    /* Karşılaştırma anahtarı: küçük harf, Türkçe harfler sadeleştirilmiş.
       "Çetin" ile "cetin", "İktisat" ile "iktisat" eşleşsin diye. */
    function ar_anahtar(string $s): string {
        $s = function_exists('tg_ad_anahtar') ? tg_ad_anahtar($s) : mb_strtolower($s, 'UTF-8');
        return preg_replace('/\s+/u', ' ', $s);
    }

    /* Tek bir ifadenin bir metinde bulunup bulunmadığı.
       "tırnak içi" tam öbek, yıldız* sonek jokeri, boşluklu ifade
       ise bütün kelimelerin geçmesi (VE) anlamına gelir. */
    function ar_eslesme(string $metin, string $ifade): bool {
        $ifade = trim($ifade);
        if ($ifade === '') return true;
        $m = ar_anahtar($metin);

        /* Tam öbek */
        if (preg_match('/^"(.+)"$/us', $ifade, $mm)) {
            return strpos($m, ar_anahtar($mm[1])) !== false;
        }
        /* Kelimeler: hepsi geçmeli */
        foreach (preg_split('/\s+/u', $ifade) as $kel) {
            if ($kel === '') continue;
            $joker = substr($kel, -1) === '*';
            $k = ar_anahtar(rtrim($kel, '*'));
            if ($k === '') continue;
            if ($joker) {
                /* Sonek jokeri: kelime başı eşleşmesi aranır */
                if (!preg_match('/(^|\W)' . preg_quote($k, '/') . '/u', $m)) return false;
            } else {
                if (strpos($m, $k) === false) return false;
            }
        }
        return true;
    }

    /* Bir satırın ölçütü: ['alan'=>..., 'ifade'=>..., 'islec'=>...] */
    function ar_satir_uyar(array $y, array $satir): bool {
        return ar_eslesme(ar_metin($y, (string)($satir['alan'] ?? 'hepsi')), (string)($satir['ifade'] ?? ''));
    }

    /* Satırları sırayla uygula. İlk satırın işleci yok sayılır. */
    function ar_satirlar(array $yazilar, array $satirlar): array {
        $satirlar = array_values(array_filter($satirlar, fn($s) => trim((string)($s['ifade'] ?? '')) !== ''));
        if (!$satirlar) return $yazilar;
        $sonuc = null;
        foreach ($satirlar as $i => $s) {
            $bu = array_values(array_filter($yazilar, fn($y) => ar_satir_uyar($y, $s)));
            if ($i === 0 || $sonuc === null) { $sonuc = $bu; continue; }
            $islec = (string)($s['islec'] ?? 've');
            $kimlik = fn($y) => (string)($y['id'] ?? '');
            if ($islec === 'veya') {
                $var = array_map($kimlik, $sonuc);
                foreach ($bu as $y) { if (!in_array($kimlik($y), $var, true)) $sonuc[] = $y; }
            } elseif ($islec === 'haric') {
                $cikar = array_map($kimlik, $bu);
                $sonuc = array_values(array_filter($sonuc, fn($y) => !in_array($kimlik($y), $cikar, true)));
            } else {
                $kal = array_map($kimlik, $bu);
                $sonuc = array_values(array_filter($sonuc, fn($y) => in_array($kimlik($y), $kal, true)));
            }
        }
        return $sonuc ?? [];
    }

    /* Tür süzgecinin üç değerinin okura görünen adı.

       İkisi doğrudan bir aşamadır ve adını tek kaynaktan alır; üçüncüsü
       ("hakemli") tek bir aşama değil, raporu gelmiş bütün çalışmaları
       toplayan bir kümedir (değerlendirmede olan da, onaylanan da), bu
       yüzden kendi adını taşır. */
    function ar_tur_ad(string $tur): string {
        $en = k_en();
        if ($tur === 'aranan') return tg_asama_metni('aranan', $en);
        if ($tur === 'yazi')   return tg_asama_metni('yok', $en, true);
        return k_c('Hakemli', 'Peer reviewed');
    }

    /* ---- Süzgeçler (WoS'taki "Refine results") ---- */
    function ar_suz(array $yazilar, array $s): array {
        $out = $yazilar;

        /* SÜZGEÇ KAPSAMA SORAR, YAKINLIK DEĞİL.
           Eskiden burada al_yakinlik() >= 2 aranıyordu. O işlev "iki kod
           birbirine ne kadar yakın" sorusunu yanıtlar ve hakem
           eşleştirmesi için yazılmıştır; süzgecin sorusu ise "bu çalışma
           seçilen alanın ALTINDA mı"dır. İkisi karıştırılınca süzgeç aynı
           anda hem gevşek hem dar oldu: temel alan seçen hiçbir şey
           bulamıyor, bir dal seçen kardeş dalların hepsini alıyordu. */
        if (!empty($s['alan'])) {
            $kodlar = al_kodlar((array)$s['alan']);
            if ($kodlar) {
                $out = array_values(array_filter($out, function ($y) use ($kodlar) {
                    foreach ($kodlar as $a) { if (al_kayit_kapsam($y, (string)$a)) return true; }
                    return false;
                }));
            }
        }
        if (!empty($s['yil_bas'])) {
            $out = array_values(array_filter($out, fn($y) => substr((string)($y['tarih'] ?? ''), 0, 4) >= (string)$s['yil_bas']));
        }
        if (!empty($s['yil_son'])) {
            $out = array_values(array_filter($out, fn($y) => substr((string)($y['tarih'] ?? ''), 0, 4) <= (string)$s['yil_son']));
        }
        if (!empty($s['tur'])) {
            /* Ham 'tur' alanına süzülmez. O alan çalışmanın hangi YOLDA
               olduğunu söyler, o yolun neresinde olduğunu değil: yazar
               hakemliğe açtığı anda tur='hakemli' yazılır ve tek rapor
               bile gelmemiştir. Seçeneğin adı "Hakemli çalışma" olduğu
               için okur açıkça hakemden geçmiş bir liste istiyor;
               raporsuz çalışmayı oraya koymak listeyi yalancı yapar.
               Ölçüt arşiv sayfasınınkiyle birebir aynı olsun diye
               k_suz_tur() kullanılıyor. */
            $out = array_values(array_filter($out, fn($y) => k_suz_tur($y) === (string)$s['tur']));
        }
        if (!empty($s['onayli'])) {
            $out = array_values(array_filter($out, fn($y) => (bool)(tg_onay_durumu($y)['onayli'] ?? false)));
        }
        if (!empty($s['yazar'])) {
            $a = ar_anahtar((string)$s['yazar']);
            $out = array_values(array_filter($out, fn($y) => in_array($a, array_map('ar_anahtar', tg_yazar_anahtarlari($y)), true)
                || strpos(ar_anahtar(ar_metin($y, 'yazar')), $a) !== false));
        }
        if (!empty($s['kurum'])) {
            $a = ar_anahtar((string)$s['kurum']);
            $out = array_values(array_filter($out, fn($y) => strpos(ar_anahtar(ar_metin($y, 'kurum')), $a) !== false));
        }
        if (!empty($s['hakem'])) {
            $a = ar_anahtar((string)$s['hakem']);
            $out = array_values(array_filter($out, fn($y) => strpos(ar_anahtar(ar_metin($y, 'hakem')), $a) !== false));
        }
        if (!empty($s['anahtar'])) {
            /* Süzme iki dile de bakar: çözümlemeden Türkçe bir kelimeyle
               inilse bile, aynı çalışmanın İngilizce anahtarıyla gelen
               bir bağlantı da aynı kümeyi açmalıdır. */
            $a = ar_anahtar((string)$s['anahtar']);
            $out = array_values(array_filter($out, function ($y) use ($a) {
                foreach (preg_split('/[,;]+/u', ar_metin($y, 'anahtar')) as $k) {
                    if (ar_anahtar($k) === $a) return true;
                }
                return false;
            }));
        }
        return $out;
    }

    /* ---- Çözümleme (WoS'taki "Analyze results") ----
       Sonuç kümesinin hangi eksende nasıl dağıldığını sayar. Her
       eksende [değer => ['ad'=>, 'say'=>]] döner, çoktan aza sıralı. */
    function ar_coz(array $yazilar): array {
        $eksen = [
            'alan'    => [], 'yil' => [], 'yazar' => [], 'kurum' => [],
            'hakem'   => [], 'tur' => [], 'karar' => [], 'anahtar' => [],
        ];

        foreach ($yazilar as $y) {
            foreach (al_kayit_kodlari($y) as $k) {
                $eksen['alan'][$k] = ['ad' => al_ad($k), 'say' => ($eksen['alan'][$k]['say'] ?? 0) + 1];
            }
            $yil = substr((string)($y['tarih'] ?? ''), 0, 4);
            if ($yil !== '') $eksen['yil'][$yil] = ['ad' => $yil, 'say' => ($eksen['yil'][$yil]['say'] ?? 0) + 1];

            $adlar = [];
            $b = $y['yazar_bilgi'] ?? null;
            $ilk = (is_array($b) && trim((string)($b['ad'] ?? '')) !== '') ? trim((string)$b['ad']) : trim((string)($y['yazar'] ?? ''));
            if ($ilk !== '') $adlar[] = $ilk;
            foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
                if (is_array($ya) && trim((string)($ya['ad'] ?? '')) !== '') $adlar[] = trim((string)$ya['ad']);
            }
            foreach (array_unique($adlar) as $ad) {
                $eksen['yazar'][$ad] = ['ad' => $ad, 'say' => ($eksen['yazar'][$ad]['say'] ?? 0) + 1];
            }

            $kurumlar = [];
            if (is_array($b) && trim((string)($b['kurum'] ?? '')) !== '') $kurumlar[] = trim((string)$b['kurum']);
            foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
                if (is_array($ya) && trim((string)($ya['kurum'] ?? '')) !== '') $kurumlar[] = trim((string)$ya['kurum']);
            }
            foreach (array_unique($kurumlar) as $kr) {
                $eksen['kurum'][$kr] = ['ad' => $kr, 'say' => ($eksen['kurum'][$kr]['say'] ?? 0) + 1];
            }

            foreach (tg_dizi($y['hakemler'] ?? null) as $h) {
                if (!is_array($h)) continue;
                $ad = trim((string)($h['ad'] ?? ''));
                if ($ad === '' || trim((string)($h['rapor'] ?? '')) === '') continue;
                $eksen['hakem'][$ad] = ['ad' => $ad, 'say' => ($eksen['hakem'][$ad]['say'] ?? 0) + 1];
                $kr = (string)($h['karar'] ?? '');
                if ($kr !== '') $eksen['karar'][$kr] = ['ad' => tg_karar_ad($kr), 'say' => ($eksen['karar'][$kr]['say'] ?? 0) + 1];
            }

            /* Çözümleme kutusundaki sayım, süzgecin verdiği listeyle
               aynı ölçütten gelmeli: kutuda "3" yazıp süzgeç 5 sonuç
               getirirse okur hangisine inanacağını bilemez. */
            $tur   = k_suz_tur($y);
            $turAd = ar_tur_ad($tur);
            $eksen['tur'][$tur] = ['ad' => $turAd, 'say' => ($eksen['tur'][$tur]['say'] ?? 0) + 1];

            /* Anahtar kelimeler yalnızca sayfanın dilinde sayılır.
               Arama iki dili birden tarar, çünkü kayıt tek eserdir ve
               İngilizce kelimeyle arayan Türkçe kaydı da bulmalıdır.
               Ama çözümleme kutusu bir sayım tablosudur: aynı kavramın
               iki dildeki karşılığı ayrı satırlar hâlinde durunca hem
               liste ikiye katlanıyor hem de sayılar bölünüyordu.
               Türkçe sayfada Türkçe, İngilizce sayfada İngilizce
               anahtarlar sayılır; kaydın o dilde anahtarı yoksa
               öteki dildekine düşülür (k_alan bunu yapar). */
            foreach (preg_split('/[,;]+/u', k_alan($y, 'anahtar')) as $k) {
                $k = trim($k);
                if ($k === '') continue;
                $eksen['anahtar'][$k] = ['ad' => $k, 'say' => ($eksen['anahtar'][$k]['say'] ?? 0) + 1];
            }
        }

        foreach ($eksen as $ad => $liste) {
            uasort($liste, fn($a, $b) => ($b['say'] <=> $a['say']) ?: strcmp((string)$a['ad'], (string)$b['ad']));
            $eksen[$ad] = $liste;
        }
        /* Yıl ekseni sayıya değil zamana göre okunur */
        krsort($eksen['yil']);
        return $eksen;
    }

    /* Karar adları ortak.php'de tanımlıdır (tg_karar_ad). */

    /* Sıralama */
    function ar_sirala(array $liste, string $nasil): array {
        usort($liste, function ($a, $b) use ($nasil) {
            switch ($nasil) {
                case 'eski':   return strcmp((string)($a['tarih'] ?? ''), (string)($b['tarih'] ?? ''));
                case 'ad':     return strcmp(ar_anahtar((string)($a['baslik'] ?? '')), ar_anahtar((string)($b['baslik'] ?? '')));
                case 'okunan': return k_okuma_sayisi((string)($b['id'] ?? '')) <=> k_okuma_sayisi((string)($a['id'] ?? ''));
                case 'rapor':
                    $s = function ($y) { $n = 0; foreach (tg_dizi($y['hakemler'] ?? null) as $h) { if (is_array($h) && trim((string)($h['rapor'] ?? '')) !== '') $n++; } return $n; };
                    return $s($b) <=> $s($a);
                default:       return strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? ''));
            }
        });
        return $liste;
    }
}
