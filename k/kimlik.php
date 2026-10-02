<?php
/* =====================================================================
   KUTADGU - Kurumsal kimlik / Corporate identity
   ---------------------------------------------------------------------
   Kimliğin tek kaynağı burasıdır: renkler, işaret, ikon dizgesi ve
   çok dilli slogan. Bir sayfa kendi başına renk ya da işaret uydurmaz,
   buradan çağırır. Kimlik değişirse tek dosya değişir.

   Renk dizgesi
     Lacivert  #1B2A4A  ana kurumsal renk, gezinme ve marka yüzeyleri
     Altın     #C9A227  vurgu; lacivert üstünde kullanılır (5.9:1)
     Koyu altın #856410 açık zeminde metin olarak kullanılan altın
                        (kâğıt, kart ve ikincil kart üstünde 4.7:1 üstü)

   Bu değerler k/kutadgu.css içindeki değişkenlerle birebir aynıdır;
   biri değişirse öteki de değişmelidir.
   ===================================================================== */

if (!function_exists('kim_renk')) {

    /* ---------- Renkler ---------- */
    function kim_renkler(): array {
        return [
            'lacivert'    => '#1B2A4A',
            'lacivert_koyu' => '#101B31',
            'lacivert_ac' => '#27395E',
            'altin'       => '#C9A227',
            'altin_ac'    => '#E0BB52',
            'altin_koyu'  => '#856410',
            'fildisi'     => '#F5F0E4',
        ];
    }
    function kim_renk(string $ad): string { return kim_renkler()[$ad] ?? '#1B2A4A'; }

    /* ---------- Çok dilli slogan ----------
       Kimlik levhasındaki diller. Sayfa dili listede yoksa İngilizce
       gösterilir. Yeni dil eklemek buraya bir satırdır. */
    function kim_sloganlar(): array {
        return [
            'tr' => 'Bilgi paylaşıldığında kut verir',
            'en' => 'Knowledge brings fortune when it is shared',
            'fr' => 'Le savoir porte ses fruits lorsqu\'il est partagé',
            'es' => 'El saber da fruto cuando se comparte',
            'ru' => 'Знание приносит благо, когда им делятся',
            'ar' => 'العلم يثمر حين يُشارَك',
            'zh' => '知识共享方得其福',
        ];
    }
    function kim_slogan(string $dil = 'tr'): string {
        $s = kim_sloganlar();
        return (string)($s[$dil] ?? $s['en']);
    }

    /* ---------- İşaret ----------
       Kimlik levhasındaki işaret üç parçadan kurulur:
         KÜRE    altın, ekvatoru ve meridyenleriyle: yeryüzü, sınırsız erişim
         KİTAP   lacivert, iki yana açılmış yaprakları: bilgi
         İNSAN   kollarını açmış bir figür; gövdesi bir kalem ucudur:
                 bilgiyi yazan ve arkasında duran kişi
       Kürenin çevresindeki renkli yaylar ve noktalar ayrı ayrı
       kültürleri, ortak bir küre çevresinde durmalarını anlatır.

       $b   kenar uzunluğu (piksel)
       $tip 'tam'     lacivert yuvarlak kare zemin üstünde işaret (simge, favicon)
            'renkli'  zeminsiz, tam renkli işaret (açık zeminler için)
            'ters'    zeminsiz, koyu zeminler için (kitap ve figür fildişi)
            'daire'   kimlik levhasındaki lacivert daire içindeki simge
            'sade'    tek renk, currentColor ile çizilir
            'kucuk'   çok küçük ölçüler için sadeleştirilmiş işaret         */
    function kim_isaret(int $b = 40, string $tip = 'tam', string $sinif = ''): string {
        $s  = $sinif !== '' ? ' class="' . htmlspecialchars($sinif, ENT_QUOTES, 'UTF-8') . '"' : '';
        $r  = kim_renkler();
        $bas = '<svg' . $s . ' width="' . $b . '" height="' . $b . '" viewBox="0 0 64 64" '
             . 'role="img" aria-hidden="true" focusable="false">';

        /* --- parçalar --- */
        $kureCizgi = '<circle cx="32" cy="24" r="15"/><ellipse cx="32" cy="24" rx="6" ry="15"/>'
                   . '<path d="M17.6 19h28.8M17 29h30M19.5 14h25M19.5 34h25"/>';
        $kitap = '<path d="M31 46C24 40 15 36 5 35v12c10 1 19 4 26 9z"/>'
               . '<path d="M33 46c7-6 16-10 26-11v12c-10 1-19 4-26 9z"/>';
        $kitapCizgi = '<path d="M9 39c6 1.2 12 3.4 17 6.6M9 44c6 1.2 12 3.4 17 6.6"/>'
                    . '<path d="M55 39c-6 1.2-12 3.4-17 6.6M55 44c-6 1.2-12 3.4-17 6.6"/>';
        /* İnsan: baş, iki kol, gövde yerine kalem ucu */
        $insan = '<circle cx="32" cy="17" r="3.4"/>'
               . '<path d="M28.4 22.5c-3.4-1.6-6-4.4-7.2-8l2.6-1c1.1 3.1 3.4 5.5 6.3 6.7z"/>'
               . '<path d="M35.6 22.5c3.4-1.6 6-4.4 7.2-8l-2.6-1c-1.1 3.1-3.4 5.5-6.3 6.7z"/>'
               . '<path d="M32 22c3.2 0 5.4 2.2 5.4 5.2 0 4.6-2.2 12-5.4 20.8-3.2-8.8-5.4-16.2-5.4-20.8 0-3 2.2-5.2 5.4-5.2z"/>';
        /* Kalem ucunun deliği ve yarığı; zeminin renginde boşaltılır */
        $ucBosluk = fn(string $renk) => '<circle cx="32" cy="30" r="2.1" fill="' . $renk . '"/>'
                  . '<path d="M32 33.5v9" stroke="' . $renk . '" stroke-width="1.1" fill="none"/>';
        /* Çevredeki kültür yayları ve noktaları */
        $yay = '<g fill="none" stroke-width="1.4" stroke-linecap="round">'
             . '<path d="M14 16a22 22 0 0 1 6-8" stroke="#1F7A6B"/>'
             . '<path d="M50 16a22 22 0 0 0-6-8" stroke="#2F6FB8"/>'
             . '<path d="M9 27a24 24 0 0 1 1-9" stroke="#B33A3A"/>'
             . '<path d="M55 27a24 24 0 0 0-1-9" stroke="#6B4CA8"/></g>'
             . '<circle cx="20" cy="8" r="1.9" fill="#1F7A6B"/>'
             . '<circle cx="44" cy="8" r="1.9" fill="#2F6FB8"/>'
             . '<circle cx="10" cy="18" r="1.9" fill="#B33A3A"/>'
             . '<circle cx="54" cy="18" r="1.9" fill="#6B4CA8"/>'
             . '<circle cx="32" cy="4" r="2.2" fill="' . $r['altin'] . '"/>';

        if ($tip === 'renkli' || $tip === 'ters') {
            $kitapRenk = $tip === 'ters' ? $r['fildisi'] : $r['lacivert'];
            $bosluk    = $tip === 'ters' ? $r['lacivert'] : $r['fildisi'];
            return $bas
                 . $yay
                 . '<g fill="none" stroke="' . $r['altin'] . '" stroke-width="1.5">' . $kureCizgi . '</g>'
                 . '<g fill="' . $kitapRenk . '">' . $kitap . '</g>'
                 . '<g fill="none" stroke="' . $bosluk . '" stroke-width="1.1">' . $kitapCizgi . '</g>'
                 . '<g fill="' . $kitapRenk . '">' . $insan . '</g>'
                 . $ucBosluk($bosluk)
                 . '</svg>';
        }
        if ($tip === 'daire') {
            return $bas
                 . '<circle cx="32" cy="32" r="32" fill="' . $r['lacivert'] . '"/>'
                 . '<g transform="translate(32 34) scale(.78) translate(-32 -30)">'
                 . '<g fill="none" stroke="' . $r['altin'] . '" stroke-width="1.9">' . $kureCizgi . '</g>'
                 . '<g fill="' . $r['fildisi'] . '">' . $kitap . '</g>'
                 . '<g fill="none" stroke="' . $r['lacivert'] . '" stroke-width="1.3">' . $kitapCizgi . '</g>'
                 . '<g fill="' . $r['fildisi'] . '">' . $insan . '</g>'
                 . $ucBosluk($r['lacivert'])
                 . '</g></svg>';
        }
        if ($tip === 'sade') {
            return $bas . '<g fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" '
                 . 'stroke-linejoin="round">'
                 . '<circle cx="32" cy="23" r="13.5"/><path d="M18.5 23h27"/>'
                 . '<ellipse cx="32" cy="23" rx="5.4" ry="13.5"/>'
                 . '<path d="M31 46C25 40.5 17 37 8 36v10c9 1 17 3.6 23 7.6z"/>'
                 . '<path d="M33 46c6-5.5 14-9 23-10v10c-9 1-17 3.6-23 7.6z"/>'
                 . '</g></svg>';
        }
        if ($tip === 'kucuk') {
            /* 16-24 piksel için: ince ayrıntılar kaybolduğundan yalnızca
               küre, kitap ve figürün ana kütlesi kalır. */
            return $bas
                 . '<rect width="64" height="64" rx="14" fill="' . $r['lacivert'] . '"/>'
                 . '<g fill="none" stroke="' . $r['altin'] . '" stroke-width="3.2">'
                 . '<circle cx="32" cy="24" r="13.5"/><path d="M18.5 24h27"/>'
                 . '<ellipse cx="32" cy="24" rx="5.4" ry="13.5"/></g>'
                 . '<g fill="' . $r['fildisi'] . '"><path d="M31 47C25 42 17 39 8 38v10c9 1 17 3.6 23 7.6z"/>'
                 . '<path d="M33 47c6-5 14-8 23-9v10c-9 1-17 3.6-23 7.6z"/></g>'
                 . '</svg>';
        }
        /* varsayılan: 'tam' */
        return $bas
             . '<rect width="64" height="64" rx="14" fill="' . $r['lacivert'] . '"/>'
             . '<g transform="translate(32 33) scale(.82) translate(-32 -30)">'
             . '<g fill="none" stroke="' . $r['altin'] . '" stroke-width="1.8">' . $kureCizgi . '</g>'
             . '<g fill="' . $r['fildisi'] . '">' . $kitap . '</g>'
             . '<g fill="none" stroke="' . $r['lacivert'] . '" stroke-width="1.3">' . $kitapCizgi . '</g>'
             . '<g fill="' . $r['fildisi'] . '">' . $insan . '</g>'
             . $ucBosluk($r['lacivert'])
             . '</g></svg>';
    }

    /* ---------- Monogram ----------
       Kimlik levhasındaki "A": KUTADGU sözcüğünün ortasındaki, karnında
       altın bir nokta taşıyan harf. Çok dar yerlerde işaretin yerine
       geçer. */
    function kim_monogram(int $b = 40, string $sinif = '', bool $ters = false): string {
        $s = $sinif !== '' ? ' class="' . htmlspecialchars($sinif, ENT_QUOTES, 'UTF-8') . '"' : '';
        $r = kim_renkler();
        $harf = $ters ? $r['fildisi'] : $r['lacivert'];
        return '<svg' . $s . ' width="' . $b . '" height="' . $b . '" viewBox="0 0 64 64" '
             . 'role="img" aria-hidden="true" focusable="false">'
             . '<path d="M32 8 52 56h-8.6L32 25.5 20.6 56H12z" fill="' . $harf . '"/>'
             . '<circle cx="32" cy="43" r="5" fill="' . $r['altin'] . '"/></svg>';
    }

    /* ---------- Kelime işaretindeki A ----------
       KUTADGU sözcüğünün ortasındaki A, sıradan bir A değildir: işaretin
       kendi harfidir, karnında altın bir nokta taşır. Sözcük yazıyla
       dizildiğinde de o harfin görünmesi gerekir; yoksa marka bir yerde
       kendi harfini kullanıp başka bir yerde kullanmamış olur.

       Harf metnin içine gömülür: yüksekliği satırın büyük harf boyuna
       göre em ile verilir, gövdesinin rengi çevresinden (currentColor)
       gelir. Böylece lacivert zeminde de açık zeminde de doğru renkte
       çıkar ve punto değiştiğinde kendiliğinden büyür.               */
    function kim_harf_a(string $sinif = ''): string {
        $r = kim_renkler();
        $s = 'kim-a' . ($sinif !== '' ? ' ' . htmlspecialchars($sinif, ENT_QUOTES, 'UTF-8') : '');
        return '<svg class="' . $s . '" viewBox="12 8 40 48" role="img" aria-label="A" focusable="false">'
             . '<path d="M32 8 52 56h-8.6L32 25.5 20.6 56H12z" fill="currentColor"/>'
             . '<circle cx="32" cy="43" r="5" fill="' . $r['altin'] . '"/></svg>';
    }

    /* ---------- İkon dizgesi ----------
       Kimlik levhasındaki altı kavram. Hepsi tek çizgi kalınlığında,
       24x24 kutuda, aynı elden çıkmış gibi durur. Sayfalar bu adlarla
       çağırır: kim_ikon('kure'). Listede olmayan ad boş döner ki
       yanlış yazım sessizce yanlış resim göstermesin.                  */
    function kim_ikonlar(): array {
        return [
            /* --- Kimlik levhasındaki altı kavram ---
               Hepsi işaretin kendi parçalarından türetilmiştir: küre,
               kitap, kalem ucu, figür, pusula ve halka. Böylece ikonlar
               logonun dışında ayrı bir dil kurmaz, aynı elden çıkar. */

            /* GLOBAL: ekvatoru ve meridyeni olan küre */
            'kure'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/>'
                      . '<ellipse cx="12" cy="12" rx="3.7" ry="9"/>'
                      . '<path d="M5 7.2h14M5 16.8h14"/>',
            /* KNOWLEDGE: iki yana açılmış kitap */
            'bilgi'  => '<path d="M12 7.6v12.8"/>'
                      . '<path d="M12 7.6C9.4 5 5.9 3.6 2 3.4v12.8c3.9.2 7.4 1.6 10 4.2"/>'
                      . '<path d="M12 7.6C14.6 5 18.1 3.6 22 3.4v12.8c-3.9.2-7.4 1.6-10 4.2"/>',
            /* PUBLICATION: kalem ucu */
            'yayin'  => '<path d="M12 2.4c2.6 0 4.4 1.8 4.4 4.3 0 3.7-1.8 8.8-4.4 15.3C9.4 15.5 7.6 10.4 7.6 6.7 7.6 4.2 9.4 2.4 12 2.4z"/>'
                      . '<circle cx="12" cy="9.2" r="1.7"/><path d="M12 12v7.5"/>',
            /* PEOPLE: kollarını açmış figür */
            'insan'  => '<circle cx="12" cy="4.6" r="2.4"/>'
                      . '<path d="M12 9.2c-2.6 0-4 1.6-4 3.6 0 2.6 1.4 6 4 9.2 2.6-3.2 4-6.6 4-9.2 0-2-1.4-3.6-4-3.6z"/>'
                      . '<path d="M8.6 10.4C6.4 9.2 4.9 7.2 4.2 4.6M15.4 10.4c2.2-1.2 3.7-3.2 4.4-5.8"/>',
            /* GUIDANCE: pusula yıldızı */
            'kilavuz'=> '<path d="M12 1.6 14.3 9.7 22.4 12l-8.1 2.3L12 22.4l-2.3-8.1L1.6 12l8.1-2.3z"/>'
                      . '<path d="M17.6 6.4 13.6 10.4M6.4 17.6l4-4M17.6 17.6l-4-4M6.4 6.4l4 4"/>',
            /* UNITY: ortak halkada duran ayrı ayrı noktalar */
            'birlik' => '<path d="M14.9 4.1a8.4 8.4 0 0 1 5 5"/>'
                      . '<path d="M19.9 14.9a8.4 8.4 0 0 1-5 5"/>'
                      . '<path d="M9.1 19.9a8.4 8.4 0 0 1-5-5"/>'
                      . '<path d="M4.1 9.1a8.4 8.4 0 0 1 5-5"/>'
                      . '<circle cx="12" cy="3.6" r="1.9" fill="currentColor" stroke="none"/>'
                      . '<circle cx="20.4" cy="12" r="1.9" fill="currentColor" stroke="none"/>'
                      . '<circle cx="12" cy="20.4" r="1.9" fill="currentColor" stroke="none"/>'
                      . '<circle cx="3.6" cy="12" r="1.9" fill="currentColor" stroke="none"/>',

            /* --- Arayüz yardımcıları --- */
            'ara'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
            'bildirim' => '<path d="M18 8.5a6 6 0 1 0-12 0c0 6.5-2.5 7.5-2.5 7.5h17S18 15 18 8.5z"/>'
                      . '<path d="M13.7 19.5a2 2 0 0 1-3.4 0"/>',
            'cikis'  => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'kalkan' => '<path d="M12 22s8-3.4 8-10V5.5l-8-3-8 3V12c0 6.6 8 10 8 10z"/>',
            'terazi' => '<path d="M12 3v18"/><path d="M5 7h14"/><path d="M5 7 2 14h6z"/><path d="M19 7l-3 7h6z"/><path d="M8 21h8"/>',
            'saat'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
            'goz'    => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
            'damga'  => '<path d="M12 2.8 20 7v6.2c0 4.6-3.3 7.6-8 8.9-4.7-1.3-8-4.3-8-8.9V7z"/><path d="m9 12 2.2 2.2L15.4 10"/>',
            'ok'     => '<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
            /* YER İMİ: kitabın bir yaprağı arasına konmuş im.
               İşaretin kitap parçasından türer; ayrı bir dil kurmaz. */
            'imi'    => '<path d="M6 3.4h12a1 1 0 0 1 1 1v16.2l-7-4.3-7 4.3V4.4a1 1 0 0 1 1-1z"/>',
            'dil'    => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/>'
                      . '<path d="M12 3c2.6 3 2.6 15 0 18-2.6-3-2.6-15 0-18z"/>',

            /* --- 12 Ağustos 2026: MENÜ SİMGELERİ ---
               Menüde on yedi madde, on bir simge vardı; altı madde
               başkasının simgesini ödünç alıyordu. "Yayın kurulu"
               onaylanmış bir kalkanla, "Bize yazın" bir zille, "Site
               haritası" da ana sayfanın küresiyle duruyordu. Bir simge
               yanlış şeyi gösterdiğinde okuru menüde bir kez yanıltır;
               iki madde aynı simgeyi taşıdığında ise gözün ayırt edecek
               bir şeyi kalmaz.

               Yenileri de aynı elden çıkar: 24 birimlik ızgara, yalnız
               çizgi, currentColor, aynı 1.7 kalınlık. Hiçbiri dolgu
               kullanmaz; dolgulu bir simge yanındakilerden ağır durur. */

            /* KURUL: bir masanın çevresinde beş koltuk. Kurulun kendisi
               bir onay değil, bir topluluktur; kalkan onu yanlış
               anlatıyordu. */
            'kurul'  => '<ellipse cx="12" cy="12.6" rx="6.2" ry="3.4"/>'
                      . '<circle cx="12" cy="4.6" r="1.8"/>'
                      . '<circle cx="4.4" cy="9.4" r="1.8"/>'
                      . '<circle cx="19.6" cy="9.4" r="1.8"/>'
                      . '<circle cx="6.6" cy="19" r="1.8"/>'
                      . '<circle cx="17.4" cy="19" r="1.8"/>',

            /* İSTATİSTİK: üç sütun ve taban çizgisi. Yer imi değil. */
            'grafik' => '<path d="M3.5 20.5h17"/>'
                      . '<path d="M7 20.5v-6.2M12 20.5V6.8M17 20.5v-9.4"/>',

            /* HAKEMLİK SÜRECİ: üstünde onay imi olan bir belge.
               Süreç bir insan değil, bir metnin okunmasıdır. */
            'hakemlik' => '<path d="M6 2.8h8.4L19 7.4v13.8H6z"/>'
                      . '<path d="M14.2 2.8v4.8H19"/>'
                      . '<path d="m8.8 14.4 2.2 2.2 4.2-4.4"/>',

            /* DİZİN: adların alt alta sıralandığı liste. */
            'dizin'  => '<circle cx="6.4" cy="7" r="2"/><path d="M11 7h9"/>'
                      . '<circle cx="6.4" cy="12.6" r="2"/><path d="M11 12.6h9"/>'
                      . '<circle cx="6.4" cy="18.2" r="2"/><path d="M11 18.2h9"/>',

            /* İLKELER: satırları olan, mühürlü bir belge. Pusula
               yıldızı "nasıl işler" sayfasının simgesiydi; kurallar
               belgesi ayrı bir şeydir. */
            'ilkeler'=> '<path d="M6 2.8h12v18.4H6z"/>'
                      . '<path d="M9 7.4h6M9 11.4h6M9 15.4h3.5"/>',

            /* KILAVUZ SAYFASI: yazılmakta olan bir sayfa — çizgili bir
               yaprak ve ucunu ona koymuş bir kalem. 'ilkeler' düz bir
               belgedir (kural metni), 'kilavuz' pusuladır (yön), 'yayin'
               kalem ucudur (gönderme edimi). Bu üçünden de ayrı durması
               gerekiyordu: menüde "Nasıl işler" ile "Yazı ekleme
               kılavuzu" yan yana duruyor ve aynı ikonu taşıdıklarında
               ikisi tek madde gibi okunuyordu. */
            'yazim'  => '<path d="M6 2.8h8.4l3.6 3.6v5.2"/>'
                      . '<path d="M14.4 2.8v3.6H18"/>'
                      . '<path d="M18 21.2H6V2.8"/>'
                      . '<path d="M9 8.6h4M9 12.2h3"/>'
                      . '<path d="M19.4 13.6 13.6 19.4l-2.6.8.8-2.6 5.8-5.8z"/>',

            /* YAPAY ZEKÂ: birbirine bağlı düğümler. Göz, gözetim
               anlatıyordu; bu sayfa bir ilke metnidir. */
            'yz'     => '<circle cx="12" cy="12" r="2.6"/>'
                      . '<circle cx="5.2" cy="6.4" r="1.9"/><circle cx="18.8" cy="6.4" r="1.9"/>'
                      . '<circle cx="5.2" cy="17.6" r="1.9"/><circle cx="18.8" cy="17.6" r="1.9"/>'
                      . '<path d="m6.7 7.7 3 2.6M17.3 7.7l-3 2.6M6.7 16.3l3-2.6M17.3 16.3l-3-2.6"/>',

            /* POSTA: kapalı zarf. Zil bir bildirimdir, bir ileti değil. */
            'posta'  => '<path d="M2.8 6.2h18.4v11.6H2.8z"/>'
                      . '<path d="m2.8 7 9.2 6.4L21.2 7"/>',

            /* DESTEK: bir eli açmış avuç ve üstünde kalp. */
            'destek' => '<path d="M12 20.4c-3.6-2.6-6.4-5-6.4-8.2a3.2 3.2 0 0 1 6.4-1.4 3.2 3.2 0 0 1 6.4 1.4c0 3.2-2.8 5.6-6.4 8.2z"/>'
                      . '<path d="M4.4 5.6 6 3.6M19.6 5.6 18 3.6"/>',

            /* HARİTA: katlanmış üç sayfa. */
            'harita' => '<path d="M2.8 6 9 3.6v14.8L2.8 20.8z"/>'
                      . '<path d="M9 3.6 15 6v14.8L9 18.4z"/>'
                      . '<path d="M15 6l6.2-2.4v14.8L15 20.8z"/>',
        ];
    }

    /**
     * İkon çizer.
     * @param string $ad    kim_ikonlar() anahtarı
     * @param int    $b     kenar (piksel)
     * @param string $sinif ek sınıf
     */
    function kim_ikon(string $ad, int $b = 20, string $sinif = ''): string {
        $i = kim_ikonlar()[$ad] ?? '';
        if ($i === '') return '';
        $s = $sinif !== '' ? ' class="' . htmlspecialchars($sinif, ENT_QUOTES, 'UTF-8') . '"' : '';
        return '<svg' . $s . ' width="' . $b . '" height="' . $b . '" viewBox="0 0 24 24" fill="none" '
             . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" '
             . 'aria-hidden="true" focusable="false">' . $i . '</svg>';
    }

    /* ---------- Kavram dizgesi ----------
       Kimlik levhasındaki altı başlık, iki dilde. Ana sayfa bunları
       kullanır; başka bir sayfa da kullanabilir.                        */
    function kim_kavramlar(): array {
        /* Her kavramın altında bir cümle ve bir kapı vardır. Kavram tek
           başına bir süs olarak durmaz; okuyucuyu onu karşılayan sayfaya
           götürür. Yoksa altı ikon, altı boş söz olarak kalırdı. */
        return [
            ['im' => 'kure',    'tr' => 'Yeryüzü',  'en' => 'Global',
             'ack_tr' => 'Ücretsiz, engelsiz, her ülkeden okunur.',
             'ack_en' => 'Free of charge, unblocked, readable from every country.',
             'yol' => '/yazilar.php'],
            ['im' => 'bilgi',   'tr' => 'Bilgi',    'en' => 'Knowledge',
             'ack_tr' => 'Tam metinler açık; hiçbir alan dışarıda değil.',
             'ack_en' => 'Full texts are open; no discipline is left outside.',
             'yol' => '/yazilar.php'],
            ['im' => 'yayin',   'tr' => 'Yayın',    'en' => 'Publication',
             'ack_tr' => 'Sayı beklenmez; hazır olan yayımlanır.',
             'ack_en' => 'No issues to wait for; what is ready is published.',
             'yol' => '/basvuru.php'],
            ['im' => 'insan',   'tr' => 'İnsan',    'en' => 'People',
             'ack_tr' => 'Hakem raporunun arkasında adıyla durur.',
             'ack_en' => 'The reviewer stands behind the report by name.',
             'yol' => '/hakemlik.php'],
            ['im' => 'kilavuz', 'tr' => 'Kılavuz',  'en' => 'Guidance',
             'ack_tr' => 'Bütün kurallar tek belgede, herkese açık.',
             'ack_en' => 'Every rule in one document, open to all.',
             'yol' => '/ilkeler.php'],
            ['im' => 'birlik',  'tr' => 'Birlik',   'en' => 'Unity',
             'ack_tr' => 'Ortak zeminde bütün milletler kardeştir.',
             'ack_en' => 'On common ground all nations are kin.',
             'yol' => '/bildiri.php'],
        ];
    }
}
