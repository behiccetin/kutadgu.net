<?php
/* =====================================================================
   GÜVEN İŞARETLERİ: kapı ölçümü. Depoya girmez.

   Devir belgesi 7.4: "Güven işaretleri. Lisans, ISSN, iletişim, kurul,
   DOAJ ölçütleri. UYARI: sahip olunmayan bir işareti basmak, bu
   sistemin en pahalı yalanı olur. Yalnızca gerçekten alınmış olanlar,
   alınma tarihiyle."

   Bu kapı iki yönlü ölçer, ve iki yön de gereklidir:

     A) YALAN YOK: alınmamış bir işaret hiçbir yerde alınmış gibi
        görünmemeli; ne sayfada, ne makine üstverisinde. Bu, kapının
        asıl işidir.
     B) SUSMA DA YOK: alınmamış işaret sayfadan SİLİNMEMELİ. Eksik
        olanı listeden düşürmek sayfayı temiz, okuru yanlış
        bilgilendirilmiş bırakır ve bu sistemin tek iddiası her şeyin
        görünür olmasıdır.

   Ayrıca ölçülür: TARİHSİZ BİR "ALINDI" KABUL EDİLMEZ. Bir iddia, ne
   zaman doğru olduğu söylenmeden denetlenemez. Kapı bunu iyi niyetle
   değil, gerçekten deneyerek ölçer: kendi kod kopyasında tarihsiz bir
   "alındı" yazar ve sistemin onu reddettiğini görür.

   Kullanım:
     KUTADGU_DATA=<veri dizini> KPORT=<kapı> php guven-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
$LOG  = getenv('KLOG') ?: '/home/claude/kg/sunucu.log';

if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . $PORT;
require_once $KOD . '/ortak.php';
require_once $KOD . '/k/kabuk.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

function ist(string $yol, string $govde = null): array {
    global $PORT;
    $h = ['method' => $govde === null ? 'GET' : 'POST', 'ignore_errors' => true, 'timeout' => 30,
        'header' => 'CF-Connecting-IP: 10.' . random_int(1, 250) . '.' . random_int(1, 250) . '.' . random_int(1, 250)
            . ($govde === null ? '' : "\r\nContent-Type: application/json")];
    if ($govde !== null) $h['content'] = $govde;
    $g = @file_get_contents('http://127.0.0.1:' . $PORT . $yol, false, stream_context_create(['http' => $h]));
    $kod = 0;
    foreach (($http_response_header ?? []) as $s) if (preg_match('#^HTTP/[\d.]+ (\d+)#', $s, $m)) $kod = (int)$m[1];
    return ['kod' => $kod, 'govde' => (string)$g];
}
function gorunur(string $html): string {
    $h = preg_replace('#<(script|style|template)\b[^>]*>.*?</\1>#si', ' ', $html);
    return trim((string)preg_replace('/\s+/u', ' ',
        html_entity_decode(strip_tags((string)$h), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}
/* Ayar değiştiren senaryolar KENDİ ALT SÜRECİNDE (OKUBENI 9). */
function altSurec(string $ayarYama, string $govde): string {
    global $KOD, $VERI;
    $dizin = '/tmp/kguven';
    shell_exec('rm -rf ' . escapeshellarg($dizin) . ' && cp -a ' . escapeshellarg($KOD) . ' ' . escapeshellarg($dizin)
        . ' && rm -rf ' . escapeshellarg($dizin . '/.git'));
    if ($ayarYama !== '') {
        /* YAMA DİZİNİN SONUNA KONUR, BAŞINA DEĞİL.
           İlk yazımda yama 'guven_isaretleri' => [ satırının hemen
           ardına ekleniyordu. PHP dizi değişmezinde AYNI ANAHTAR İKİ
           KEZ yazılırsa SONUNCUSU kazanır; yani ayar dosyasındaki asıl
           satır yamayı eziyordu ve "tarihsiz alindi reddediliyor"
           denemeleri hiçbir şey ölçmeden GECTI veriyordu. Ölçüm yanlış
           çıktığında önce ölçümden şüphelen; burada kusurlu olan kod
           değil, yamanın konduğu yerdi. */
        $a = (string)file_get_contents($dizin . '/ayar.php');
        $hedef = "    'guven_isaretleri' => [";
        $bas = strpos($a, $hedef);
        $son = strpos($a, "\n    ],", $bas);
        if ($bas === false || $son === false) return 'YAMA-TUTMADI';
        $a = substr($a, 0, $son + 1) . $ayarYama . "\n" . substr($a, $son + 1);
        file_put_contents($dizin . '/ayar.php', $a);
    }
    $p = tempnam(sys_get_temp_dir(), 'kg') . '.php';
    file_put_contents($p, "<?php\nputenv('KUTADGU_DATA=" . $VERI . "');\n"
        . "\$_SERVER['HTTP_HOST']='127.0.0.1';\nrequire '" . $dizin . "/ortak.php';\n" . $govde);
    $c = (string)shell_exec('php ' . escapeshellarg($p) . ' 2>&1');
    @unlink($p);
    return trim($c);
}

$logOnce = (int)@filesize($LOG);

/* =====================================================================
   1. TEK KAYNAK VAR VE BUGÜNKÜ DURUM DÜRÜST
   ===================================================================== */
echo "== 1. Tek kaynak ==\n";
den('tg_guven_isaretleri() var', function_exists('tg_guven_isaretleri'));
$gi = tg_guven_isaretleri();
$gs = tg_guven_sayim();
olc('işaretler: ' . implode(', ', array_map(fn($g) => $g['k'] . '=' . $g['durum'], $gi)));
den('en az beş işaret tanımlı', count($gi) >= 5, (string)count($gi));
den('ISSN, DOAJ ve DOI listede',
    isset($gi['issn'], $gi['doaj'], $gi['doi']));
den('sayım işaret sayısına eşit',
    array_sum($gs) === count($gi), array_sum($gs) . '/' . count($gi));
den('her işaretin Türkçe ve İngilizce adı ve açıklaması var',
    $gi !== [] && count(array_filter($gi, fn($g) => trim($g['tr']) !== '' && trim($g['en']) !== ''
        && trim((string)($g['ack'][0] ?? '')) !== '' && trim((string)($g['ack'][1] ?? '')) !== '')) === count($gi));

/* =====================================================================
   2. TARİHSİZ "ALINDI" KABUL EDİLMİYOR
   ---------------------------------------------------------------------
   İyi niyetle değil, deneyerek ölçülür.
   ===================================================================== */
echo "\n== 2. Tarihsiz iddia ==\n";
den('tarihsiz "alindi" kendiliğinden "yok"a düşüyor',
    altSurec("        'issn'  => ['durum' => 'alindi', 'tarih' => '', 'no' => '1234-5678'],",
             "echo tg_guven_isaretleri()['issn']['durum'];") === 'yok');
den('  ve numarası da basılmıyor',
    altSurec("        'issn'  => ['durum' => 'alindi', 'tarih' => '', 'no' => '1234-5678'],",
             "echo '[' . tg_guven_no('issn') . ']';") === '[]');
den('bozuk tarihli "alindi" de düşüyor',
    altSurec("        'issn'  => ['durum' => 'alindi', 'tarih' => 'yakında', 'no' => '1234-5678'],",
             "echo tg_guven_isaretleri()['issn']['durum'];") === 'yok');
den('GELECEK tarihli "alindi" de düşüyor (henüz olmamış şey olmuş gibi yazılamaz)',
    altSurec("        'issn'  => ['durum' => 'alindi', 'tarih' => '2099-01-01', 'no' => '1234-5678'],",
             "echo tg_guven_isaretleri()['issn']['durum'];") === 'yok');
den('tanınmayan durum "yok" sayılıyor',
    altSurec("        'issn'  => ['durum' => 'neredeyse', 'tarih' => '2026-01-01'],",
             "echo tg_guven_isaretleri()['issn']['durum'];") === 'yok');
den('TARİHLİ "alindi" kabul ediliyor (kapı yalnız reddetmiyor, tanıyor da)',
    altSurec("        'issn'  => ['durum' => 'alindi', 'tarih' => '2026-01-15', 'no' => '1234-5678'],",
             "echo tg_guven_isaretleri()['issn']['durum'] . '|' . tg_guven_no('issn');") === 'alindi|1234-5678');
den('  "basvuruldu" alınmış SAYILMIYOR',
    altSurec("        'doaj'  => ['durum' => 'basvuruldu', 'tarih' => '2026-01-15'],",
             "echo tg_guven_alindi('doaj') ? 'evet' : 'hayir';") === 'hayir');

/* =====================================================================
   3. SAYFA: ALINMAYAN GİZLENMİYOR, ALINMAYAN ALINMIŞ GİBİ DURMUYOR
   ===================================================================== */
echo "\n== 3. Sayfada ==\n";
$s = ist('/acikliklar.php?lang=tr');
den('açıklıklar sayfası 200', $s['kod'] === 200, (string)$s['kod']);
den('  gövde </html> ile bitiyor', str_ends_with(rtrim($s['govde']), '</html>'));
$g = gorunur($s['govde']);
den('güven işaretleri bölümü sayfada', stripos($g, 'Güven işaretleri') !== false);
$alinmadi = substr_count($g, 'alınmadı');
olc('sayfada "alınmadı" geçen yer: ' . $alinmadi);
den('ALINMAYANLAR SAYFADA YAZILI (gizlenmemiş)',
    $alinmadi >= $gs['yok'], $alinmadi . ' >= ' . $gs['yok']);
den('  her işaretin adı sayfada geçiyor',
    count(array_filter($gi, fn($x) => stripos($g, $x['tr']) !== false)) === count($gi));
den('  neden gizlenmediği yazılı (sahip olunmayan işaret basılmaz)',
    stripos($g, 'sahip olmadığımız') !== false || stripos($g, 'alınmamış olanlar listeden çıkarılmaz') !== false
    || stripos($g, 'listeden çıkarılmaz') !== false);
/* Bugün hiçbiri alınmadığına göre sayfada bir "alındı" rozeti
   OLMAMALI. Sayaç rozeti "0/6 alındı" der; o bir iddia değil, sayıdır. */
den('bugün hiçbir işaret "alındı" rozetiyle durmuyor',
    $gs['alindi'] > 0 || substr_count($s['govde'], 'rz-yes">alındı') === 0,
    (string)substr_count($s['govde'], 'rz-yes">alındı'));

$sEn = ist('/acikliklar.php?lang=en');
$gEn = gorunur($sEn['govde']);
den('İngilizce sayfada da aynı liste var', stripos($gEn, 'Marks of standing') !== false);
den('  İngilizcede de alınmayan yazılı', stripos($gEn, 'not obtained') !== false);

/* =====================================================================
   4. MAKİNE ÜSTVERİSİNE ALINMAMIŞ KİMLİK YAZILMIYOR
   ---------------------------------------------------------------------
   Sayfada dürüst olup üstveride boş bir ISSN basmak, dizinlere yalan
   söylemektir. Üstelik daha tehlikelidir: onu bir insan okumaz.
   ===================================================================== */
echo "\n== 4. Makine üstverisi ==\n";
$oai = ist('/oai.php?verb=Identify');
olc('OAI Identify kodu: ' . $oai['kod']);
den('OAI yanıtında ISSN yok (alınmamışken)',
    $gs['alindi'] > 0 || stripos($oai['govde'], 'issn') === false, 'issn geçiyor');
$sit = ist('/');
den('anasayfa üstverisinde uydurma ISSN yok',
    !preg_match('/"issn"\s*:\s*"\s*"/i', $sit['govde']) && !preg_match('/"issn"\s*:\s*"[^"]+"/i', $sit['govde'])
    || $gs['alindi'] > 0);
den('  boş bir ISSN alanı hiç basılmıyor (boş alan da bir iddiadır)',
    !preg_match('/issn[^:]{0,20}:\s*(""|\'\')/i', $sit['govde']));
/* Alınmış bir kimlik varsa numarası verilir; yoksa alan hiç
   basılmamalı. tg_guven_no() bunu tek başına da söylemeli. */
den('tg_guven_no() alınmamış işaret için BOŞ dize veriyor', tg_guven_no('issn') === '');

/* =====================================================================
   5. ZATEN AÇIK OLANLAR: LİSANS, KURUL, İLETİŞİM
   ---------------------------------------------------------------------
   Devir belgesi bunları da sayıyor. Bunlar dışarıdan verilen işaretler
   değil, sistemin kendi açıklıklarıdır ve zaten yerindeler; kapı
   yerinde olduklarını ölçer ki bir gün sessizce düşerlerse görülsün.
   ===================================================================== */
echo "\n== 5. Kendi açıklıkları ==\n";
/* /lisans.php bir İNSAN SAYFASI DEĞİL, makine okur RSL belgesidir.
   İlk yazımda içinde "CC BY 4.0" adı aranmıştı ve belge geçerliyken
   kapı KALDI verdi: belge lisansı ADIYLA değil ADRESİYLE bildirir,
   çünkü makineye söylenen budur. Aranan şey ad değil, lisansın
   kendisi olmalı. */
$lis = ist('/lisans.php');
den('lisans belgesi 200', $lis['kod'] === 200, (string)$lis['kod']);
den('  lisans makine tarafından okunur biçimde bildiriliyor',
    stripos($lis['govde'], (string)tg_ayar('lisans_url', 'creativecommons.org/licenses/by/4.0')) !== false);
den('  ve ücret istenmediği yazılı (payment type=attribution)',
    stripos($lis['govde'], 'attribution') !== false);
/* İletişim bir FORMDUR, düz adres değil: adres sayfada yazılı olsaydı
   toplanır ve istenmeyen postaya döner. Ölçülecek olan adresin
   varlığı değil, ULAŞILABİLİRLİĞİN varlığıdır (OKUBENI 20: ögeyi
   metniyle değil davranışıyla ara). */
$ilet = ist('/iletisim.php?lang=tr');
den('iletişim sayfası 200', $ilet['kod'] === 200, (string)$ilet['kod']);
/* İlk yazımda burada '/api/iletisim' dizesi aranıyordu; sayfa ise
   ucu api('/iletisim') diye çağırıyor ve /api önekini betik ekliyor.
   Desen kodun YAZILIŞINA göre yazılmıştı (OKUBENI 36) ve çalışan bir
   sayfayı "iletişim yolu yok" saydı. Ölçülecek olan dizenin biçimi
   değil, UCUN GERÇEKTEN VAR OLMASIDIR: uç yoksa 404, varsa başka bir
   şey döner. */
$ucDurum = ist('/api/iletisim', '{}')['kod'];
olc('iletişim ucunun GET yanıtı: ' . $ucDurum);
den('  ulaşmanın bir yolu var (form ucu ya da adres)',
    ($ucDurum !== 0 && $ucDurum !== 404)
    || (bool)preg_match('/mailto:/', $ilet['govde'])
    || (bool)preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $ilet['govde']));
den('  sayfa o uca bağlanıyor', (bool)preg_match("#api\('/iletisim'#", $ilet['govde']));
$kur = ist('/kurul.php?lang=tr');
den('kurul sayfası 200 ve en az bir ad taşıyor',
    $kur['kod'] === 200 && strlen(gorunur($kur['govde'])) > 500, (string)$kur['kod']);
den('güven işaretleri bölümü lisansı da anıyor',
    stripos($g, (string)tg_ayar('lisans', 'CC BY 4.0')) !== false);

/* =====================================================================
   6. BİLDİRİ METNİ İLE SAYFA AYRIŞMIYOR
   ---------------------------------------------------------------------
   bildiri.php'de "hiçbir dizinde taranmamaktadır; ISSN başvurusu ve
   DOAJ dosyası henüz tamamlanmamıştır" diye elle yazılmış bir cümle
   var. O cümle ile ayar dosyasındaki durum ayrışırsa, sistem aynı
   şeyi iki yerde farklı söyler. Bugün ikisi de "alınmadı" diyor;
   ayrıştıkları gün burası KALDI verir.
   ===================================================================== */
echo "\n== 6. Bildiri ile tutarlılık ==\n";
$bil = ist('/bildiri.php?lang=tr');
$bg = gorunur($bil['govde']);
$bildiriEksikDiyor = stripos($bg, 'hiçbir dizinde taranmamaktadır') !== false
                  || stripos($bg, 'ISSN başvurusu') !== false;
olc('bildiri "işaretler eksik" diyor mu: ' . ($bildiriEksikDiyor ? 'evet' : 'hayır')
    . ' · ayar "alındı" sayısı: ' . $gs['alindi']);
den('bildiri metni ile ayar dosyası aynı şeyi söylüyor',
    ($gs['alindi'] === 0 && $bildiriEksikDiyor) || ($gs['alindi'] > 0 && !$bildiriEksikDiyor),
    'alindi=' . $gs['alindi'] . ' bildiri=' . ($bildiriEksikDiyor ? 'eksik' : 'tam'));

/* =====================================================================
   7. SUNUCU KÜTÜĞÜ
   ===================================================================== */
echo "\n== 7. Sunucu kütüğü ==\n";
$yeni = (string)@file_get_contents($LOG, false, null, $logOnce);
$uyari = preg_match_all('/warning|deprecated|notice|fatal/i', $yeni);
den('bu ölçüm sırasında sunucu uyarısı yok', $uyari === 0, (string)$uyari);

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
