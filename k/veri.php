<?php
/* =====================================================================
   KUTADGU - Veri okuma yardımcıları / Data helpers
   Yazılar ve okuma kayıtları tek yerden okunur, sayfalar hafif kalır.
   ===================================================================== */

require_once __DIR__ . '/kabuk.php';

if (!function_exists('k_veri_dizin')) {

    function k_veri_dizin(): string {
        return function_exists('tg_veri_dizini')
            ? tg_veri_dizini()
            : (getenv('KUTADGU_DATA') ?: realpath(__DIR__ . '/../..') . '/kutadgu-data');
    }

    function k_json(string $ad, $vars = []) {
        $y = k_veri_dizin() . '/' . $ad;
        if (!is_file($y)) return $vars;
        $j = json_decode((string)file_get_contents($y), true);
        return is_array($j) ? $j : $vars;
    }

    /* Bütün çalışmalar, yeniden eskiye */
    /* =================================================================
       DENEME KAYITLARI KAMUYA GÖRÜNMEZ · TEK SÜZGEÇ
       -----------------------------------------------------------------
       Kurul isteği (M. Z. Tunca): "bir sürecin tam olarak işlediğini
       denemek için demo hakem, yazar, editör ile deneme yapamıyoruz."
       Doğru bir istek: hakem ataması bugüne kadar hiç uçtan uca
       denenemedi.

       DENEMEYE İZİN VERMENİN BEDELİ, DENEME KAYITLARININ ARŞİVE
       SIZMASIDIR ve bu bedel ödenmemelidir: sahte bir çalışma
       istatistiğe girerse sayılar yalan söyler, OAI ile toplanırsa
       dizinlere düşer ve dökümde yer alırsa arşivin parmak izini
       bozar. Bu yüzden süzgeç ARŞİVİN GİRİŞİNE kondu, tek yere:
       k_yazilar() bütün kamusal sayfaların okuduğu kaynaktır
       (anasayfa, liste, arama, istatistik, OAI, site haritası, döküm).
       Her sayfaya ayrı süzgeç yazılsaydı, biri unutulduğu gün deneme
       kaydı oradan görünürdü.

       İŞARET KAYITTA DURUR ('deneme' => true), ayarda değil: ayar
       kapandığında kayıt görünür olmamalı, YOK olmalıdır. Denemeyi açıp
       kapatmak bir görünürlük anahtarı değildir.

       YÖNETİM UÇLARI BU İŞLEVİ KULLANMAZ; onlar yazilar.json'u
       doğrudan okur ve deneme kayıtlarını görür — göremezlerse deneme
       yapılamaz.
       ================================================================= */
    function k_yazilar(): array {
        static $y = null;
        if ($y !== null) return $y;
        $ham = k_json('yazilar.json', []);
        $y = [];
        foreach ($ham as $k) {
            if (is_array($k) && !empty($k['deneme'])) continue;
            $y[] = $k;
        }
        usort($y, fn($a, $b) => strcmp((string)($b['tarih'] ?? ''), (string)($a['tarih'] ?? '')));
        return $y;
    }

    /* Okuma kayıtları: id => ['tekil'=>[...], 'sure'=>...] */
    function k_okumalar(): array {
        static $o = null;
        if ($o === null) $o = k_json('yazi-oku.json', []);
        return $o;
    }

    function k_okuma_sayisi(string $id): int {
        $o = k_okumalar();
        $k = $o[$id] ?? null;
        if (!is_array($k)) return 0;
        return is_array($k['tekil'] ?? null) ? count($k['tekil']) : 0;
    }

    /* Dile göre alan: baslik / baslik_en gibi */
    function k_alan(array $y, string $k): string {
        /* Değer dizi olarak kaydedilmişse birleştirilir; "Array"
           sözcüğünün sayfaya düşmesindense bilgi korunur. */
        if (k_en()) { $v = trim(tg_metin($y[$k . '_en'] ?? '')); if ($v !== '') return $v; }
        return tg_metin($y[$k] ?? '');
    }

    /* Düz metin özet */
    function k_ozet($html, int $n = 210): string {
        $t = trim((string)preg_replace('/\s+/u', ' ', strip_tags((string)$html)));
        return mb_strlen($t, 'UTF-8') > $n ? mb_substr($t, 0, $n, 'UTF-8') . '...' : $t;
    }

    /* Türkçe uyumlu ilk harf büyütme */
    function k_titr(string $s): string {
        $s = trim($s); if ($s === '') return '';
        $up = ['i' => 'İ', 'ı' => 'I', 'ş' => 'Ş', 'ğ' => 'Ğ', 'ü' => 'Ü', 'ö' => 'Ö', 'ç' => 'Ç'];
        $out = [];
        foreach (preg_split('/\s+/u', $s) as $w) {
            if ($w === '') continue;
            $f = mb_substr($w, 0, 1, 'UTF-8'); $r = mb_substr($w, 1, null, 'UTF-8');
            $fl = mb_strtolower($f, 'UTF-8');
            $out[] = ($up[$fl] ?? mb_strtoupper($f, 'UTF-8')) . $r;
        }
        return implode(' ', $out);
    }

    /* Ad zaten unvan taşıyor mu? ("Prof. Dr. Ali" gibi) */
    function k_unvanli(string $ad): bool {
        return (bool)preg_match('/^\s*(prof|doç|doc|dr|öğr|ogr|arş|ars|uzm|av)\b\.?/iu', $ad);
    }

    /* Unvanı adın önüne, gerekmiyorsa eklemeden birleştir */
    function k_unvan_ekle(string $unvan, string $ad): string {
        /* Unvan bir anahtar olabilir; sayfanın diline göre çözülür. */
        $unvan = tg_unvan_ad(trim($unvan)); $ad = trim($ad);
        if ($ad === '') return '';
        if ($unvan === '') return $ad;
        if (k_unvanli($ad)) return $ad;                                  /* ad zaten unvanlı */
        if (mb_stripos($ad, $unvan, 0, 'UTF-8') !== false) return $ad;   /* unvan ad içinde geçiyor */
        return $unvan . ' ' . $ad;
    }

    /* Bütün yazarların adları: "Dr. A, Dr. B ve Prof. Dr. C" */
    function k_yazarlar(array $y): string {
        $ad = [];
        $b = $y['yazar_bilgi'] ?? null;
        $ilk = trim((string)($y['yazar'] ?? ''));
        if (is_array($b) && trim((string)($b['ad'] ?? '')) !== '') {
            $ilk = k_unvan_ekle((string)($b['unvan'] ?? ''), (string)$b['ad']);
        }
        if ($ilk !== '') $ad[] = $ilk;
        foreach (tg_dizi($y['yazar_liste'] ?? null) as $ya) {
            if (!is_array($ya)) continue;
            $n = k_unvan_ekle((string)($ya['unvan'] ?? ''), (string)($ya['ad'] ?? ''));
            if ($n !== '') $ad[] = $n;
        }
        $ad = array_values(array_unique($ad));
        if (count($ad) === 0) return '';
        if (count($ad) === 1) return $ad[0];
        $son = array_pop($ad);
        return implode(', ', $ad) . k_c(' ve ', ' and ') . $son;
    }

    /* Bir çalışmanın adresi: kimliği varsa kalıcı adres kullanılır */
    function k_yazi_yolu(array $y): string {
        return tg_yazi_yolu($y);
    }

    /* Tamga kimliği ve adresi */
    function k_tamga(array $y): array {
        $kod = trim((string)($y['bcid'] ?? ''));
        if ($kod === '') return ['kod' => '', 'url' => ''];
        return function_exists('tg_tamga') ? tg_tamga($kod) : ['kod' => $kod, 'url' => ''];
    }

    /* Hakem kararlarının özeti */
    function k_hakem_ozet(array $y): array {
        $h = [];
        foreach (tg_dizi($y['hakemler'] ?? null) as $hk) {
            if (!is_array($hk) || trim((string)($hk['rapor'] ?? '')) === '') continue;
            $h[] = ['ad' => (string)($hk['ad'] ?? ''), 'karar' => (string)($hk['karar'] ?? '')];
        }
        return $h;
    }

    /* Karar etiketleri */
    function k_karar_ad(string $k): string {
        $m = [
            'kabul' => ['Kabul', 'Accepted'],
            'kucuk' => ['Küçük revizyon', 'Minor revision'],
            'buyuk' => ['Büyük revizyon', 'Major revision'],
            'ret'   => ['Ret', 'Rejected'],
        ];
        if (!isset($m[$k])) return '';
        return tg_t(['tr' => $m[$k][0], 'en' => $m[$k][1]]);
    }
    function k_karar_renk(string $k): string {
        return ['kabul' => 'rz-yes', 'kucuk' => 'rz-lac', 'buyuk' => 'rz-kut', 'ret' => 'rz-kir'][$k] ?? 'rz-cizgi';
    }

    /* Tarih biçimi */
    function k_tarih(string $t): string {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $t, $m)) return $t;
        /* Ay adı çeviri katmanından, dizim sayfanın dilinden gelir;
           ikisi de ortak.php'de tek yerde durur. */
        return tg_tarih_dizimi((int)$m[3], (int)$m[2], (int)$m[1]);
    }

    /* Sayı biçimi */
    function k_sayi(int $n): string {
        return number_format($n, 0, ',', k_en() ? ',' : '.');
    }

    /* ---------------------------------------------------------------
       Kısa sayı. Gösterge şeritlerinde sayı büyüdükçe rakam sayısı da
       büyür; "1.284.930" gibi bir dizi, yanındaki üç sayacın hepsini
       ezer ve dar ekranda satırı taşırır. Bu yüzden binden sonra kısa
       biçim kullanılır: 12,4 B / 1,2 M. Tam sayı kaybolmaz, ögenin
       title özniteliğinde durur; isteyen üstüne gelip görür.

       Bir de şu var: bir okuma sayısı ne kadar büyürse büyüsün bir
       nitelik ölçüsü değildir. Kısa biçim, sayıyı olduğundan daha
       önemli göstermemeye de yarar.
       --------------------------------------------------------------- */
    function k_sayi_kisa(int $n): string {
        $en = k_en();
        $ond = k_c(',', '.');
        if ($n < 1000) return k_sayi($n);
        /* 999.999 "1000 B" diye yazılmasın; yuvarlama bini aşıyorsa
           bir üst basamağa geçilir. */
        if ($n < 999500) {
            $v = $n / 1000;
            $b = $v < 10 ? number_format($v, 1, $ond, '') : (string)(int)round($v);
            if (substr($b, -2) === $ond . '0') $b = substr($b, 0, -2);
            return $b . ' ' . (k_c('B', 'K'));
        }
        $v = $n / 1000000;
        $b = $v < 10 ? number_format($v, 1, $ond, '') : (string)(int)round($v);
        if (substr($b, -2) === $ond . '0') $b = substr($b, 0, -2);
        return $b . ' M';
    }

    /* Sayaçlar

       "N hakemli" sayısı ana sayfada ve arşivde okurun gördüğü ilk
       iddiadır. 'tur' alanı çalışmanın hangi YOLDA olduğunu söyler, o
       yolun neresinde olduğunu değil: yazar çalışmasını hakemliğe açtığı
       anda tur='hakemli' yazılır, tek bir rapor bile gelmemiş olabilir.
       Bu yüzden sayaç tg_hakemden_gecti()'ye bağlıdır; hakem aranan
       çalışmalar ayrı bir sayıda ('aranan') durur, gizlenmez ama
       hakemliden geçmiş gibi de sayılmaz.

       tg_* işlevleri ortak.php'dedir ve bu dosya kabuk.php üzerinden
       onu yükler. Yine de kamusal sayfaların hepsinin ortak.php'yi
       yüklediğine güvenilmez; işlev yoksa sayaç eski davranışa değil,
       daha dar olana düşer: hiçbir çalışma hakemli sayılmaz. Yanlış
       fazla saymaktansa eksik saymak yeğdir. */
    function k_sayaclar(): array {
        $y = k_yazilar();
        $hakemli = 0; $aranan = 0; $yazar = []; $oku = 0;
        $var = function_exists('tg_hakemden_gecti') && function_exists('tg_hakem_asamasi');
        foreach ($y as $e) {
            if ($var && tg_hakemden_gecti($e)) $hakemli++;
            elseif ($var && tg_hakem_asamasi($e) === 'aranan') $aranan++;
            $a = trim((string)($e['yazar'] ?? '')); if ($a !== '') $yazar[$a] = true;
            foreach (tg_dizi($e['yazar_liste'] ?? null) as $ya) {
                if (is_array($ya) && trim((string)($ya['ad'] ?? '')) !== '') $yazar[trim((string)$ya['ad'])] = true;
            }
            $oku += k_okuma_sayisi((string)($e['id'] ?? ''));
        }
        return ['toplam' => count($y), 'hakemli' => $hakemli, 'aranan' => $aranan,
                'yazar' => count($yazar), 'okuma' => $oku];
    }
}
