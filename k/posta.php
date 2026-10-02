<?php
/* =====================================================================
   KUTADGU · POSTA YOLU (SMTP)
   ---------------------------------------------------------------------
   BİLDİRİLEN KUSUR: "sunucudan mail çalışmıyor galiba." Ölçüldü ve
   doğrulandı. kutadgu.net'in MX, SPF, DKIM ve DMARC kaydı yok, sunucunun
   PTR kaydı da yok. Bu durumda PHP'nin mail() işlevi "true" döndürür ve
   HİÇBİR ŞEY gitmez: true yalnızca "iletiyi yerel posta yazılımına
   verdim" demektir, "alıcı aldı" demek değildir. Gmail ve Outlook,
   kimliği doğrulanmayan bir sunucudan gelen iletiyi ya gereksiz posta
   klasörüne atar ya da sessizce düşürür.

   KARAR: sunucudan posta göndermeyi BIRAKIYORUZ. İleti, dışarıdaki bir
   posta aktarıcısına (relay) SMTP ile teslim edilir; SPF, DKIM ve itibar
   yönetimi o aktarıcının işidir. Gerekçesi, yönetimin koyduğu sınırın
   kendisidir: "sistemi kasmasın, sonuçta bu bir eposta sistemi değil."
   Kendi sunucumuzda posta kimliği kurmak, kurmakla bitmeyen bir iştir:
   IP itibarı, geri dönüş kutusu, kara liste takibi, DKIM anahtar
   döndürme. Aktarıcıda bunların hiçbiri bize ait değildir; tek
   yaptığımız bir kullanıcı adı ve parola yazmaktır.

   BAĞIMLILIK YOK. PHPMailer ya da Symfony Mailer kurulmadı. Bu sistemde
   composer yok ve olmaması bir eksiklik değil bir tercihtir: sunucuya
   yalnızca okunabilir PHP dosyaları gider. İhtiyaç duyulan SMTP altkümesi
   (EHLO, STARTTLS, AUTH LOGIN/PLAIN, MAIL FROM, RCPT TO, DATA, QUIT) iki
   yüz satırdır ve aşağıdadır.

   NEDEN HER ZAMAN GERÇEK SEBEP YAZILIR: mail() çağının en sinsi yanı
   sessizliğiydi. Buradaki her başarısızlık, sunucunun KENDİ cümlesiyle
   kayda geçer ("535 5.7.8 Username and Password not accepted" gibi).
   "Gönderilemedi" demek, hiçbir şey dememektir.
   ===================================================================== */

if (!function_exists('pst_ayar')) {

/* Aktarıcı ayarları. Depodaki ayar.php'de DEĞİL, veri dizinindeki
   yonetim-ayar.json'da durur: bir parola, herkesin okuyabildiği bir git
   deposuna giremez. Değerler panelden yazılır. */
function pst_ayar(): array {
    $v = ['sunucu' => '', 'port' => 587, 'kullanici' => '', 'parola' => '',
          'guvenlik' => 'tls', 'gonderen' => '', 'gonderen_ad' => '', 'yanit' => ''];
    $a = function_exists('tg_canli_ayar') ? tg_canli_ayar('smtp', []) : [];
    if (!is_array($a)) $a = [];
    foreach ($v as $k => $d) if (isset($a[$k]) && $a[$k] !== null) $v[$k] = is_int($d) ? (int)$a[$k] : (string)$a[$k];
    $v['port'] = (int)$v['port'] > 0 ? (int)$v['port'] : 587;
    if (!in_array($v['guvenlik'], ['tls', 'ssl', 'yok'], true)) $v['guvenlik'] = 'tls';
    return $v;
}

/* Aktarıcı kurulu mu? Kullanıcı adı ve parola İSTEĞE BAĞLIDIR: kurum içi
   bir aktarıcı kimlik istemeyebilir. Zorunlu olan tek şey sunucu adıdır. */
function pst_kurulu(): bool {
    $a = pst_ayar();
    return $a['sunucu'] !== '';
}

/* ---- POSTA YOLU BUGÜN ÇALIŞIYOR MU ----
   Bir adres bilinmeden, bir hesap aranmadan yanıtlanabilen soru:
   sistemin gönderme yolu var mı? İki yol vardır ve ikisi de yoksa
   hiçbir ileti çıkmaz.

   BU AYRIM NEDEN ÖNEMLİ. Parola yenileme ucu, hesabın VAR OLUP
   OLMADIĞINI sızdırmamak için her durumda aynı cümleyi döndürür ve bu
   doğrudur. Ama "aynı cümle" ile "yalan cümle" aynı şey değildir:
   posta yolu kapalıyken "gönderildi" demek, hesabı olan da olmayan da
   aynı yalanı duysun demektir.

   Posta yolunun kapalı olması SİSTEMİN durumudur, hesabın değil.
   O yüzden hesap aranmadan önce sorulur ve iki durumda da aynı yanıt
   verilir — sızıntı yok, yalan da yok. */
function pst_yol_var(): bool {
    return pst_kurulu() || function_exists('mail');
}

/* Son hata. eposta_gonder() bool döndürür ve on beş yerden çağrılır;
   imzasını değiştirmek o on beş yeri de değiştirmek olurdu. Sebep bu
   yüzden ayrı bir kapıdan okunur. */
function pst_son_hata(?string $yeni = null): string {
    static $h = '';
    if ($yeni !== null) $h = $yeni;
    return $h;
}

/* Sunucunun yanıtını okur. SMTP çok satırlı yanıt verebilir
   ("250-PIPELINING" ... "250 HELP"); son satırın dördüncü karakteri
   tiredir değil boşluktur. Bunu atlamak, STARTTLS'ten sonra elde kalan
   satırların komut sanılmasına ve bağlantının kilitlenmesine yol açar. */
function pst_yanit($soket): array {
    $tum = '';
    while (($satir = fgets($soket, 2048)) !== false) {
        $tum .= $satir;
        if (strlen($satir) >= 4 && $satir[3] === ' ') break;
        if (strlen($satir) < 4) break;
    }
    $kod = (int)substr(ltrim($tum), 0, 3);
    return [$kod, trim($tum)];
}

function pst_komut($soket, string $komut, array $bekle): array {
    fwrite($soket, $komut . "\r\n");
    [$kod, $metin] = pst_yanit($soket);
    return [in_array($kod, $bekle, true), $kod, $metin];
}

/* =====================================================================
   Tek iletiyi aktarıcıya teslim eder.
   Döner: ['ok' => bool, 'hata' => string, 'yol' => 'smtp']
   ===================================================================== */
/* =====================================================================
   METNİN HTML KARŞILIĞI
   ---------------------------------------------------------------------
   ÖLÇÜLEN KUSUR — 14 Ağustos 2026. Parola yenileme iletisi Gmail'de
   SPAM klasörüne düştü. Önce kimlik doğrulamasından şüphelenildi;
   ölçüldü ve üçü de geçiyordu (iletinin aslından okundu):

     SPF   PASS  (zarf gönderen aktarıcının bounce alt alanıdır;
                   aktarıcı MAIL FROM'u kendi kaydına yazar, bu yüzden
                   ana alan adında ayrı bir SPF kaydı gerekmiyor)
     DKIM  PASS  (d=kutadgu.net, s=lm1)
     DMARC PASS  (p=none, header.from=kutadgu.net)

   Yani sebep DNS değil, İLETİNİN BİÇİMİ. Gmail'in kendi cümlesi de
   bunu söylüyordu: "geçmişte spam olarak tanımlanan iletilere
   benziyor." Yalnız düz metinden oluşan, gövdesinde tek başına duran
   uzun ve anlamsız bir bağlantı taşıyan, "parolanızı yenileyin" diyen
   bir ileti, oltalama iletisinin ta kendisine benzer.

   ÇÖZÜM: ileti multipart/alternative olarak gider. HTML bölüm METİNDEN
   ÜRETİLİR, ayrıca yazılmaz — iki kopya yazılsaydı bir gün ikisi ayrı
   şey söylerdi ve kimse hangisinin doğru olduğunu bilemezdi. Metin
   bölümü hiç değişmez: betiksiz, resimsiz, düz metin okuyan bir posta
   yazılımı bugün ne görüyorsa yarın da onu görür.

   HTML'de resim, izleme pikseli, dış kaynak YOKTUR. Bir doğrulama
   iletisinin okunup okunmadığını ölçmek bizim işimiz değil; ölçmeyen
   bir ileti sızdırmaz da.
   ===================================================================== */
function pst_html_govde(string $govde, string $marka, string $kok): string {
    $e = fn(string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
    $paragraflar = preg_split('/\n{2,}/', trim($govde)) ?: [];
    $govdeHtml = '';
    foreach ($paragraflar as $p) {
        $p = trim($p);
        if ($p === '') continue;
        /* Tek başına duran bir bağlantı, düğme gibi gösterilir: okuyan
           kişi nereye gittiğini adresin kendisinden değil, cümleden
           anlar. Adres yine de altında yazılı kalır — gizlenen bir
           adres, oltalamanın kendi yöntemidir. */
        if (preg_match('#^(https?://\S+)$#', $p, $m)) {
            /* DÜĞMENİN YAZISI MARKA DEĞİL, EYLEMDİR. İlk yazımda düğmenin
               üstünde "Kutadgu" yazıyordu; bir düğmenin üstüne kurumun
               adını yazmak, ona basınca ne olacağını söylememektir.
               Yazı bilerek GENELDİR: her ileti için doğrudur ve hiçbir
               iletinin metniyle ayrışamaz. Ne yaptığını bir üstteki
               cümle zaten söylüyor. */
            $etiket = function_exists('tg_c') ? tg_c('Bağlantıyı aç', 'Open the link') : 'Bağlantıyı aç';
            $govdeHtml .= '<p style="margin:0 0 18px"><a href="' . $e($m[1]) . '"'
                . ' style="display:inline-block;background:#1b2a4a;color:#ffffff;'
                . 'border:2px solid #c9a227;border-radius:8px;padding:12px 22px;'
                . 'font-weight:600;text-decoration:none">' . $e($etiket) . '</a></p>'
                . '<p style="margin:0 0 18px;font-size:13px;color:#525c6e;word-break:break-all">'
                . $e($m[1]) . '</p>';
            continue;
        }
        $govdeHtml .= '<p style="margin:0 0 14px">' . nl2br($e($p)) . '</p>';
    }
    return '<!doctype html><html lang="tr"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>' . $e($marka) . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f6f4ef;'
        . 'font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;'
        . 'font-size:15px;line-height:1.65;color:#12161f">'
        . '<div style="max-width:560px;margin:0 auto;background:#ffffff;'
        . 'border:1px solid #dde2ea;border-radius:12px;padding:28px 26px">'
        . '<p style="margin:0 0 20px;font-size:17px;font-weight:700;color:#1b2a4a;'
        . 'letter-spacing:.01em">' . $e($marka) . '</p>'
        . $govdeHtml
        /* ALTBİLGİDE İMZA TEKRARLANMAZ. Metin gövdesi zaten marka adı ve
           adresle bitiyor; HTML'e ikinci bir imza koymak, aynı şeyi iki
           kez söylemek olurdu. Kalan yalnız ince bir çizgidir: kartın
           bittiğini gösterir, yeni bir şey iddia etmez. */
        . '</div></body></html>';
}

function pst_smtp_gonder(string $alici, string $konu, string $govde, array $ek = []): array {
    $a = pst_ayar();
    if ($a['sunucu'] === '') return ['ok' => false, 'hata' => 'aktarici ayarlanmamis', 'yol' => 'smtp'];

    $sema = $a['guvenlik'] === 'ssl' ? 'ssl://' : '';
    $baglam = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $hatano = 0; $hatametin = '';
    $soket = @stream_socket_client($sema . $a['sunucu'] . ':' . $a['port'], $hatano, $hatametin, 15,
                                   STREAM_CLIENT_CONNECT, $baglam);
    if (!$soket) return ['ok' => false, 'hata' => 'baglanti kurulamadi: ' . trim($hatametin . ' (' . $hatano . ')'), 'yol' => 'smtp'];
    stream_set_timeout($soket, 15);

    $kapat = function ($soket, string $hata) {
        @fwrite($soket, "QUIT\r\n");
        @fclose($soket);
        return ['ok' => false, 'hata' => $hata, 'yol' => 'smtp'];
    };

    [$kod, $metin] = pst_yanit($soket);
    if ($kod !== 220) return $kapat($soket, 'karsilama beklenmedik: ' . $metin);

    /* EHLO'da yazılan ad, gönderen alan adıyla aynı olmalıdır; bazı
       aktarıcılar uyuşmayan adı reddeder. */
    $alan = $a['gonderen'] !== '' && strpos($a['gonderen'], '@') !== false
          ? substr(strrchr($a['gonderen'], '@'), 1) : 'kutadgu.net';

    [$ok, $kod, $metin] = pst_komut($soket, 'EHLO ' . $alan, [250]);
    if (!$ok) return $kapat($soket, 'EHLO reddedildi: ' . $metin);
    $yetenek = $metin;

    if ($a['guvenlik'] === 'tls') {
        if (stripos($yetenek, 'STARTTLS') === false)
            return $kapat($soket, 'sunucu STARTTLS bilmiyor; guvenlik ayarini SSL ya da yok yapin');
        [$ok, $kod, $metin] = pst_komut($soket, 'STARTTLS', [220]);
        if (!$ok) return $kapat($soket, 'STARTTLS reddedildi: ' . $metin);
        $tur = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) $tur |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT')) $tur |= STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT;
        if (@stream_socket_enable_crypto($soket, true, $tur) !== true)
            return $kapat($soket, 'TLS el sikismasi basarisiz (sertifika ya da surum uyusmazligi)');
        /* TLS'ten sonra EHLO yeniden gönderilir: sunucunun yetenek listesi
           şifreli oturumda değişir ve AUTH ancak burada görünür. */
        [$ok, $kod, $metin] = pst_komut($soket, 'EHLO ' . $alan, [250]);
        if (!$ok) return $kapat($soket, 'TLS sonrasi EHLO reddedildi: ' . $metin);
        $yetenek = $metin;
    }

    if ($a['kullanici'] !== '' && $a['parola'] !== '') {
        if (stripos($yetenek, 'AUTH') === false)
            return $kapat($soket, 'sunucu kimlik dogrulama sunmuyor (AUTH yok)');
        if (stripos($yetenek, 'PLAIN') !== false) {
            $veri = base64_encode("\0" . $a['kullanici'] . "\0" . $a['parola']);
            [$ok, $kod, $metin] = pst_komut($soket, 'AUTH PLAIN ' . $veri, [235]);
        } else {
            [$ok, $kod, $metin] = pst_komut($soket, 'AUTH LOGIN', [334]);
            if ($ok) [$ok, $kod, $metin] = pst_komut($soket, base64_encode($a['kullanici']), [334]);
            if ($ok) [$ok, $kod, $metin] = pst_komut($soket, base64_encode($a['parola']), [235]);
        }
        /* Parola kayda GİRMEZ; sunucunun cümlesi girer. */
        if (!$ok) return $kapat($soket, 'kimlik dogrulanmadi: ' . $metin);
    }

    $gonderen = $a['gonderen'] !== '' ? $a['gonderen'] : ($a['kullanici'] !== '' ? $a['kullanici'] : ('bildirim@' . $alan));

    [$ok, $kod, $metin] = pst_komut($soket, 'MAIL FROM:<' . $gonderen . '>', [250]);
    if (!$ok) return $kapat($soket, 'gonderen reddedildi: ' . $metin);
    [$ok, $kod, $metin] = pst_komut($soket, 'RCPT TO:<' . $alici . '>', [250, 251]);
    if (!$ok) return $kapat($soket, 'alici reddedildi: ' . $metin);
    [$ok, $kod, $metin] = pst_komut($soket, 'DATA', [354]);
    if (!$ok) return $kapat($soket, 'DATA reddedildi: ' . $metin);

    $ad = $a['gonderen_ad'] !== '' ? $a['gonderen_ad'] : (function_exists('tg_ayar') ? (string)tg_ayar('marka', 'Kutadgu') : 'Kutadgu');
    $yanit = $a['yanit'] !== '' ? $a['yanit'] : $gonderen;

    $bas = [];
    $bas[] = 'Date: ' . date('r');
    $bas[] = 'From: ' . mb_encode_mimeheader($ad, 'UTF-8') . ' <' . $gonderen . '>';
    $bas[] = 'To: ' . $alici;
    $bas[] = 'Reply-To: ' . $yanit;
    $bas[] = 'Subject: ' . mb_encode_mimeheader($konu, 'UTF-8');
    $bas[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $alan . '>';
    $bas[] = 'MIME-Version: 1.0';
    /* Otomatik yanıt döngüsünü keser: tatil yanıtı bu iletiye karşılık
       vermez ve karşılık verse bile bize dönmez. */
    $bas[] = 'Auto-Submitted: auto-generated';

    /* ---- İKİ BÖLÜMLÜ GÖVDE ----
       Gerekçesi pst_html_govde()'nin başındadır: yalnız düz metinden
       oluşan, gövdesinde çıplak bir bağlantı taşıyan bir parola
       iletisi oltalamaya benzer ve öyle sınıflandırılır (ölçüldü,
       14 Ağustos 2026).

       SIRA ÖNEMLİ: multipart/alternative'de SON bölüm tercih edilir.
       Metin ÖNCE, HTML SONRA yazılır. Ters yazılsaydı HTML okuyan
       yazılımlar düz metni gösterirdi ve HTML bölümü boşuna gitmiş
       olurdu.

       Metin bölümü hiç değişmedi: hangi posta yazılımı olursa olsun,
       okunacak bir şey her zaman var. */
    $kokAdr = function_exists('tg_kok') ? rtrim((string)tg_kok(), '/') : ('https://' . $alan);
    $html   = pst_html_govde($govde, $ad, $kokAdr);
    $sinir  = 'kg' . bin2hex(random_bytes(12));
    $bas[]  = 'Content-Type: multipart/alternative; boundary="' . $sinir . '"';
    foreach ($ek as $k => $v) $bas[] = $k . ': ' . preg_replace('/[\r\n]+/', ' ', (string)$v);

    /* base64: satır uzunluğu ve nokta kaçışı sorunlarını birlikte bitirir.
       SMTP'de satır başındaki tek nokta gövdenin sonu demektir; base64
       çıktısında nokta bulunmadığı için o tuzak da ortadan kalkar. */
    $bolum = function (string $tur, string $icerik) use ($sinir): string {
        return '--' . $sinir . "\r\n"
             . 'Content-Type: ' . $tur . '; charset=UTF-8' . "\r\n"
             . 'Content-Transfer-Encoding: base64' . "\r\n\r\n"
             . chunk_split(base64_encode($icerik), 76, "\r\n");
    };
    $govdeB = $bolum('text/plain', $govde) . $bolum('text/html', $html) . '--' . $sinir . "--\r\n";
    fwrite($soket, implode("\r\n", $bas) . "\r\n\r\n" . $govdeB . "\r\n.\r\n");
    [$kod, $metin] = pst_yanit($soket);
    @fwrite($soket, "QUIT\r\n");
    @fclose($soket);

    if ($kod !== 250) return ['ok' => false, 'hata' => 'teslim reddedildi: ' . $metin, 'yol' => 'smtp'];
    return ['ok' => true, 'hata' => '', 'yol' => 'smtp'];
}

}
