<?php
/* =====================================================================
   ORCID İLE KİMLİK — YARDIMCILAR

   NE YAPAR, NE YAPMAZ
   -------------------
   ORCID bir KİMLİKTİR, bir UNVAN değildir. Bu dosyadaki hiçbir şey bir
   kişiyi hakem yapmaz, doktorasını doğrulamaz, yetki vermez. Yaptığı tek
   şey şudur: "bu ORCID kaydının sahibi, şu anda tarayıcının başında olan
   kişidir" cümlesini elle yazılmış bir metin olmaktan çıkarıp
   doğrulanmış bir olguya çevirmek.

   Eskiden ORCID alanı hesap ayarlarında düz bir metin kutusuydu: kişi
   ne yazarsa o görünürdü. Bir kimliği kişinin kendi beyanına bırakmak,
   o kimliği hiç sormamakla aynı şeydir.

   KAPALI GELİR
   ------------
   'acik' false, istemci ve gizli boştur. Üçü birden dolmadan sistem
   ORCID'den hiç söz etmez: düğme basılmaz, uç 404 verir. Bir dış
   servise bağımlılık, açıldığı gün açık olmalıdır; yarım açık bir kapı
   ziyaretçiye çalışmayan bir düğme göstermekten başka bir işe yaramaz.

   GİZLİ ANAHTAR DEPOYA YAZILMAZ
   -----------------------------
   ayar.php'deki 'gizli' alanı BOŞ kalır; değer veri dizinindeki
   yonetim-ayar.json dosyasına 'orcid_gizli' anahtarıyla yazılır. Depo
   herkese açıktır ve bir kez giren anahtar geçmişte kalır.

   TEK GİRİŞ YOLU DEĞİLDİR
   -----------------------
   Parola yolu yerinde durur. ORCID bir gün erişilemez olursa kimse
   kapıda kalmamalıdır; dış bir servisi tek anahtar yapmak, o servisin
   çalışma saatlerini kendi çalışma saatiniz yapmaktır.
   ===================================================================== */

if (!function_exists('tg_orcid_ayar')) {

    /* Ayar + veri dizinindeki gizli değer. Gizli değer ayrı okunur ki
       depoya hiçbir koşulda girmesin. */
    function tg_orcid_ayar(): array {
        static $a = null;
        if ($a !== null) return $a;
        $o = (array)tg_ayar('orcid', []);
        $a = [
            'acik'    => !empty($o['acik']),
            'istemci' => trim((string)($o['istemci'] ?? '')),
            'gizli'   => trim((string)($o['gizli'] ?? '')),
            'sandbox' => !empty($o['sandbox']),
        ];
        /* yonetim-ayar.json her zaman üste yazar: sunucudaki değer,
           depodakinden yenidir ve gizli olanıdır. */
        $g = tg_canli_ayar('orcid_gizli', '');
        if (is_string($g) && trim($g) !== '') $a['gizli'] = trim($g);
        $i = tg_canli_ayar('orcid_istemci', '');
        if (is_string($i) && trim($i) !== '') $a['istemci'] = trim($i);
        return $a;
    }

    /* Üçü birden yoksa ORCID yoktur. */
    function tg_orcid_acik(): bool {
        $a = tg_orcid_ayar();
        return $a['acik'] && $a['istemci'] !== '' && $a['gizli'] !== '';
    }

    function tg_orcid_kok(): string {
        return tg_orcid_ayar()['sandbox'] ? 'https://sandbox.orcid.org' : 'https://orcid.org';
    }
    function tg_orcid_api_kok(): string {
        return tg_orcid_ayar()['sandbox'] ? 'https://pub.sandbox.orcid.org' : 'https://pub.orcid.org';
    }
    function tg_orcid_donus_adresi(): string {
        return rtrim(tg_kok(), '/') . '/api/orcid/donus';
    }

    /* ---- DURUM (state) ----
       CSRF'ye karşı tek kullanımlık bir değer. Oturumda durur, dönüşte
       karşılaştırılır ve HEMEN silinir. Süre on dakikadır: bir
       yönlendirme bundan uzun sürmez, uzun sürüyorsa o dönüş artık o
       isteğin dönüşü değildir. */
    function tg_orcid_durum_uret(string $niyet): string {
        $d = bin2hex(random_bytes(16));
        $_SESSION['orcid_durum'] = ['d' => $d, 'niyet' => $niyet, 'zaman' => time()];
        return $d;
    }
    function tg_orcid_durum_al(string $gelen): ?string {
        $s = $_SESSION['orcid_durum'] ?? null;
        unset($_SESSION['orcid_durum']);          /* tek kullanımlık */
        if (!is_array($s)) return null;
        if (!hash_equals((string)($s['d'] ?? ''), $gelen)) return null;
        if (time() - (int)($s['zaman'] ?? 0) > 600) return null;
        return (string)($s['niyet'] ?? 'giris');
    }

    function tg_orcid_yetki_adresi(string $durum): string {
        $a = tg_orcid_ayar();
        return tg_orcid_kok() . '/oauth/authorize?' . http_build_query([
            'client_id'     => $a['istemci'],
            /* /authenticate: ORCID iD'yi al ve AÇIK kaydı oku. Daha
               fazlası istenmiyor; istenmeyen yetki, verilmemiş yetkiden
               daha tehlikelidir. */
            'response_type' => 'code',
            'scope'         => '/authenticate',
            'redirect_uri'  => tg_orcid_donus_adresi(),
            'state'         => $durum,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /* Kodu jetona çevirir. Dönen kayıtta orcid ve ad bulunur. */
    function tg_orcid_jeton(string $kod): ?array {
        $a = tg_orcid_ayar();
        $g = http_build_query([
            'client_id'     => $a['istemci'],
            'client_secret' => $a['gizli'],
            'grant_type'    => 'authorization_code',
            'code'          => $kod,
            'redirect_uri'  => tg_orcid_donus_adresi(),
        ]);
        $ch = curl_init(tg_orcid_kok() . '/oauth/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $g,
            CURLOPT_HTTPHEADER     => ['Accept: application/json',
                                       'Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $y = (string)curl_exec($ch);
        $k = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($k !== 200) {
            /* Kütüğe ne olduğunu yaz, kullanıcıya değil: jeton
               alışverişinin ayrıntısı saldırgana bilgi verir. */
            error_log('kutadgu/orcid: jeton alinamadi, kod ' . $k);
            return null;
        }
        $j = json_decode($y, true);
        if (!is_array($j) || empty($j['orcid'])) return null;
        $id = tg_orcid_bicim((string)$j['orcid']);
        if ($id === '') return null;
        return ['orcid' => $id, 'ad' => trim((string)($j['name'] ?? ''))];
    }

    /* ORCID iD biçimi kesindir: 16 rakam, sonuncusu X olabilir.
       Biçimi tutmayan bir değer ORCID değildir ve kabul edilmez. */
    function tg_orcid_bicim(string $ham): string {
        $s = strtoupper(preg_replace('/[^0-9Xx]/', '', $ham));
        if (strlen($s) !== 16) return '';
        if (strpos($s, 'X') !== false && strrpos($s, 'X') !== 15) return '';
        return substr($s, 0, 4) . '-' . substr($s, 4, 4) . '-' . substr($s, 8, 4) . '-' . substr($s, 12, 4);
    }
}
