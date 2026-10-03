<?php
/* =====================================================================
   DENEME AKIŞI · kapı ölçümü. Depoya girmez.
   ---------------------------------------------------------------------
   KURUL BİLDİRİMİ — 19 Ağustos 2026: "deneme editör hakem yazar falan
   her seferinde tekrar açmak gerekiyor ve kullanışsız... hakeme
   gönderince hakem nasıl bir arayüz görecek, inceledi revizyon istedi,
   yazar yaptı, yayımlanabilir dedikleri zaman nasıl görünüyor, aradaki
   iletişim nasıl gözüküyor, revize edilmiş yeni metin nasıl gözüküyor,
   bunları deneyemiyorum."

   İKİ KUSUR:
   1. DÜZEN SÜRDÜRÜLEBİLİR DEĞİLDİ. Hakem anahtarları yalnız kurulum
      yanıtında bir kez dönüyordu; sayfa yenilenince deneme baştan
      kuruluyordu. Aşama artık kayıttan HESAPLANIR ve her açılışta
      geri verilir.
   2. AKIŞIN KENDİSİ YOKTU. Kurulum hakem bekleyen bir kayıt bırakıp
      duruyordu; rapor, revizyon, ikinci hakem ve kabul hiç denenemiyordu.

   BU KAPININ ASIL ÖLÇÜMÜ: akış GERÇEK uçlardan geçiyor mu. Deneme için
   ikinci bir yol yazılsaydı, denenen şey sistemin kendisi olmazdı ve
   deneme hiçbir şey kanıtlamazdı.

   Kullanım: KPORT=8941 KUTADGU_DATA=<veri> php deneme-akis-kapi.php
   ===================================================================== */
declare(strict_types=1);

$KOD  = getenv('KTEST_DIR') ?: '/home/claude/kg/ktest';
$VERI = getenv('KUTADGU_DATA') ?: '';
$PORT = getenv('KPORT') ?: '8941';
if ($VERI === '' || !is_dir($VERI)) { fwrite(STDERR, "KUTADGU_DATA verilmedi.\n"); exit(2); }
putenv('KUTADGU_DATA=' . $VERI);
$_SERVER['HTTP_HOST'] = '127.0.0.1';
require_once $KOD . '/ortak.php';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $s, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($s) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}
function olc(string $s): void { echo "  ÖLÇÜM  $s\n"; }

$KEREZ = '';
$IP = '203.0.113.' . random_int(1, 254);
function dist(string $yol, ?array $g = null, string $metod = ''): array {
    global $KEREZ, $IP;
    $port = getenv('KPORT') ?: '8941';
    $bas = "Content-Type: application/json\r\nX-Forwarded-For: $IP\r\n";
    if ($KEREZ !== '') $bas .= 'Cookie: ' . $KEREZ . "\r\n";
    $m = $metod !== '' ? $metod : ($g === null ? 'GET' : 'POST');
    $se = ['method' => $m, 'header' => $bas, 'timeout' => 20, 'ignore_errors' => true];
    if ($g !== null) $se['content'] = json_encode($g, JSON_UNESCAPED_UNICODE);
    $c = @file_get_contents('http://127.0.0.1:' . $port . $yol, false, stream_context_create(['http' => $se]));
    foreach (($http_response_header ?? []) as $x) {
        if (stripos($x, 'Set-Cookie: PHPSESSID') === 0) $KEREZ = explode(';', trim(substr($x, 11)))[0];
    }
    if ($c === false) return ['ok' => false, 'hata' => 'sunucuya ulaşılamadı'];
    $d = json_decode($c, true);
    return is_array($d) ? $d : ['ok' => false, 'hata' => 'yanıt okunamadı: ' . substr($c, 0, 200)];
}
$pnl = (string)@file_get_contents($KOD . '/panel.php');
$api = (string)@file_get_contents($KOD . '/api/index.php');
den('kaynaklar okunabildi', $pnl !== '' && $api !== '');

/* ------------------------------------------------------------------ */
echo "\n== 1. Adımlar GERÇEK uçlardan geçiyor ==\n";
/* Bu kapının varlık nedeni. Panelde deneme için yazılmış bir kısayol
   belirirse, deneme artık sistemi denemiyor demektir. */
foreach (['/editor/hakem-ata', '/hakem-davet-yanit', '/hakem-gonder',
          '/yazar-hakem-yanit', '/yazar-kaydet'] as $uc) {
    den('  panel ' . $uc . ' ucunu çağırıyor', str_contains($pnl, "api('" . $uc . "'"));
}
den('  denemeye özel bir akış ucu YOK',
    !preg_match("#'/yonetim/deneme-(rapor|revizyon|ata|adim)'#", $api));
den('  durum ucu yalnız OKUR (GET)', str_contains($api, "\$yol === '/yonetim/deneme-durum' && \$metod === 'GET'"));

/* ------------------------------------------------------------------ */
echo "\n== 2. Kimlik ve kapı ==\n";
$r = dist('/api/yonetim/deneme-durum');
den('kimliksiz istek reddediliyor', empty($r['ok']), json_encode(array_slice($r, 0, 2), JSON_UNESCAPED_UNICODE));
dist('/api/hesap/giris', ['kim' => 'olcumbas', 'parola' => 'olcum1234']);
$r = dist('/api/yonetim/deneme-durum');
den('yönetim yetkisiyle açılıyor', !empty($r['ok']), (string)($r['hata'] ?? ''));

/* Önce temiz bir zemin: eski deneme kaydı varsa kaldırılır. */
dist('/api/yonetim/deneme-sil', []);
$r = dist('/api/yonetim/deneme-durum');
den('düzen yokken adım "yok"', ($r['adim'] ?? '') === 'yok', (string)($r['adim'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 3. Kurulan çalışma EKSİKSİZ doğuyor ==\n";
$r = dist('/api/yonetim/deneme-kur', []);
den('deneme düzeni kuruldu', !empty($r['ok']), (string)($r['hata'] ?? ''));
$d = dist('/api/yonetim/deneme-durum');
den('  durum okunabiliyor', !empty($d['ok']));
den('  adım "kuruldu"', ($d['adim'] ?? '') === 'kuruldu', (string)($d['adim'] ?? ''));
/* Kayıt yarım doğarsa akış yürütülemez: yazar erişimi olmadan revizyon,
   tam metin olmadan önce/sonra farkı, künye olmadan kabul edilmiş bir
   sayfa görünümü olmaz. */
den('  yazar erişim anahtarı var', trim((string)($d['yazar']['token'] ?? '')) !== '');
den('  yazar erişim şifresi var', trim((string)($d['yazar']['sifre'] ?? '')) !== '');
den('  tam metin var', mb_strlen((string)($d['calisma']['icerik']['metin'] ?? '')) > 200);
den('  künye (ikinci dil) dolu',
    trim((string)($d['calisma']['icerik']['baslik_en'] ?? '')) !== ''
    && trim((string)($d['calisma']['icerik']['ozet_en'] ?? '')) !== '');
den('  kaynakça var', trim((string)($d['calisma']['icerik']['kaynakca'] ?? '')) !== '');
den('  üç deneme hesabı kuruldu', count($d['hesaplar'] ?? []) >= 3, (string)count($d['hesaplar'] ?? []));
/* Deneme adresleri teslim edilemez bir alan adındadır. */
$disAdres = [];
foreach (($d['hesaplar'] ?? []) as $h) {
    if (!preg_match('/@deneme\.gecersiz$/i', (string)($h['eposta'] ?? ''))) $disAdres[] = (string)$h['eposta'];
}
den('  hiçbir deneme adresi gerçek alan adında değil', !$disAdres, implode(',', $disAdres));

/* ------------------------------------------------------------------ */
echo "\n== 4. Akış uçtan uca yürüyor ==\n";
$eposta = function (string $ad): string {
    $t = mb_strtolower($ad, 'UTF-8');
    $t = str_replace(['ç','ğ','ı','ö','ş','ü',' '], ['c','g','i','o','s','u','.'], $t);
    return $t . '@deneme.gecersiz';
};
/* RAPOR TAM BİR RAPORDUR. tg_rapor_nitelik() gerekçesi 400 karakterden
   kısa olan, metinde hiçbir yeri işaretlemeyen ya da ölçüt
   değerlendirmesi doldurulmamış bir raporu ONAY SAYIMINA KATMAZ.
   Ölçüldü: eksik raporlarla üç kabul toplandı ve çalışma yine "onaylı"
   olmadı — deneme, sistemin en önemli kurallarından birini hiç
   göstermiyordu. */
$rapor1 = str_repeat('Yöntem bölümünde ölçüm aracının geçerlik ve güvenirlik katsayıları verilmemiş; bunlar eklenmeden bulguların güvenilirliği değerlendirilemez. ', 4);
$rapor2 = str_repeat('Birinci hakemin istediği katsayılar eklenmiş ve tartışma alanyazınla genişletilmiş; çalışma bu hâliyle yayımlanabilir. ', 4);
$ekRapor = [
    'notlar' => json_encode([
        ['alinti' => 'geçerlik katsayıları', 'not' => 'Yöntem bölümünde verilmeli.'],
        ['alinti' => 'tartışma bölümü', 'not' => 'Alanyazınla karşılaştırma genişletilmeli.'],
    ], JSON_UNESCAPED_UNICODE),
    'endeks' => json_encode(['trdizin']),
    'endeks_anket' => json_encode([['endeks' => 'trdizin', 'secim' => 'evet', 'yanit' => [4, 5, 4, 4, 5]]]),
];
/* HEDEF ÜÇ HAKEMDİR. Ölçüldü: iki kabulle akış "bitti" diyordu ama
   sayfa hâlâ "Hakem aranıyor" yazıyordu — onay eşiği iki
   (kabul_gecerli), hakem HEDEFİ üç (hakem_hedef). İki kabulde duran bir
   deneme, "yayımlanabilir dedikleri zaman nasıl görünüyor" sorusuna
   yarım yanıt veriyordu. */
$beklenen = ['kuruldu', 'hakem-atandi', 'davet-kabul', 'rapor-1', 'revizyon', 'hakem-2', 'rapor-2', 'hakem-3', 'bitti'];
$sira = 0;
$adimIsle = function (array $d) use (&$adimIsle, $eposta, $rapor1, $rapor2, $ekRapor): array {
    $a = (string)($d['adim'] ?? '');
    $yz = ['t' => (string)$d['yazar']['token'], 'sifre' => (string)$d['yazar']['sifre'], 'y' => (string)$d['calisma']['slug']];
    if ($a === 'kuruldu')      return dist('/api/editor/hakem-ata', ['id' => $d['calisma']['id'], 'ad' => 'Deneme Hakem', 'eposta' => $eposta('Deneme Hakem')]);
    if ($a === 'hakem-atandi') return dist('/api/hakem-davet-yanit', ['d' => $d['hakemler'][0]['davet'], 'yanit' => 'kabul']);
    if ($a === 'davet-kabul')  return dist('/api/hakem-gonder', ['t' => $d['hakemler'][0]['token'], 'mail' => $eposta('Deneme Hakem'), 'sifre' => $d['hakemler'][0]['sifre'], 'karar' => 'kucuk', 'rapor' => $rapor1] + $ekRapor);
    if ($a === 'rapor-1') {
        $dy = dist('/api/yazar-hakem-yanit', $yz + ['hakem' => 'Deneme Hakem', 'metin' => 'Uyarınız için teşekkür ederim. Geçerlik katsayılarını yöntem bölümüne ekledim ve tartışma bölümünü üç kaynakla genişlettim. Düzeltilmiş metni kaydettim.']);
        /* KISA YANIT REDDEDİLİYOR (en az 80 karakter) ve bu kural bu
           kapının ilk koşumunda kendini gösterdi: fikstürdeki yanıt kırk
           karakterdi, uç haklı olarak reddetti ve "yazışma kayda geçti"
           ölçümü kaldı. Akış gerçek uçtan geçtiği için gerçek kurala
           çarptı; olması gereken de buydu. */
        if (empty($dy['ok'])) echo "  ÖLÇÜM  yanıt ucu reddetti: " . (string)($dy['hata'] ?? '') . "\n";
        $ic = $d['calisma']['icerik'];
        return dist('/api/yazar-kaydet', $yz + [
            'baslik' => $ic['baslik'], 'baslik_en' => $ic['baslik_en'],
            'ozet' => $ic['ozet'], 'ozet_en' => $ic['ozet_en'],
            'anahtar' => $ic['anahtar'], 'anahtar_en' => $ic['anahtar_en'],
            'kaynakca' => $ic['kaynakca'],
            'metin' => '<h2>Yöntem</h2><p>Cronbach alfa 0,87 eklendi; bu sürüm revizyondan sonrasıdır.</p>'
                     . '<h2>Bulgular</h2><p>Deneme verisiyle üretilmiş üç bulgu.</p>']);
    }
    if ($a === 'revizyon') {
        $x = dist('/api/editor/hakem-ata', ['id' => $d['calisma']['id'], 'ad' => 'Deneme Hakem 2', 'eposta' => $eposta('Deneme Hakem 2')]);
        if (empty($x['ok'])) return $x;
        $d2 = dist('/api/yonetim/deneme-durum');
        return dist('/api/hakem-davet-yanit', ['d' => $d2['hakemler'][1]['davet'], 'yanit' => 'kabul']);
    }
    if ($a === 'hakem-2')      return dist('/api/hakem-gonder', ['t' => $d['hakemler'][1]['token'], 'mail' => $eposta('Deneme Hakem 2'), 'sifre' => $d['hakemler'][1]['sifre'], 'karar' => 'kabul', 'rapor' => $rapor2] + $ekRapor);
    if ($a === 'rapor-2')      return dist('/api/hakem-gonder', ['t' => $d['hakemler'][0]['token'], 'mail' => $eposta('Deneme Hakem'), 'sifre' => $d['hakemler'][0]['sifre'], 'karar' => 'kabul', 'rapor' => $rapor1 . ' Revizyon sonrası kabul.'] + $ekRapor);
    if ($a === 'hakem-3') {
        $x = dist('/api/editor/hakem-ata', ['id' => $d['calisma']['id'], 'ad' => 'Deneme Hakem 3', 'eposta' => $eposta('Deneme Hakem 3')]);
        if (empty($x['ok'])) return $x;
        $d3 = dist('/api/yonetim/deneme-durum');
        dist('/api/hakem-davet-yanit', ['d' => $d3['hakemler'][2]['davet'], 'yanit' => 'kabul']);
        $d3 = dist('/api/yonetim/deneme-durum');
        return dist('/api/hakem-gonder', ['t' => $d3['hakemler'][2]['token'], 'mail' => $eposta('Deneme Hakem 3'), 'sifre' => $d3['hakemler'][2]['sifre'], 'karar' => 'kabul', 'rapor' => $rapor2] + $ekRapor);
    }
    return ['ok' => true];
};
$d = dist('/api/yonetim/deneme-durum');
for ($i = 0; $i < 10; $i++) {
    $a = (string)($d['adim'] ?? '');
    den('adım ' . ($i + 1) . ': ' . $a . ' (beklenen ' . ($beklenen[$i] ?? '-') . ')', $a === ($beklenen[$i] ?? '-'), $a);
    if ($a === 'bitti') break;
    $r = $adimIsle($d);
    den('  işletildi', !empty($r['ok']), (string)($r['hata'] ?? ''));
    if (empty($r['ok'])) break;
    $d = dist('/api/yonetim/deneme-durum');
}
den('akış "yayımlanabilir" ile bitti', ($d['adim'] ?? '') === 'bitti', (string)($d['adim'] ?? ''));

/* ------------------------------------------------------------------ */
echo "\n== 5. Görülmek istenen şeyler GERÇEKTEN görünüyor ==\n";
$slug = (string)($d['calisma']['slug'] ?? '');
$s = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/yazi.php?y=' . rawurlencode($slug) . '&lang=tr');
den('çalışma sayfası açıldı', $s !== '', $slug);
den('  revize edilmiş YENİ metin görünüyor', str_contains($s, 'Cronbach alfa'));
den('  iki hakemin adı da görünüyor',
    str_contains($s, 'Deneme Hakem') && str_contains($s, 'Deneme Hakem 2'));
den('  hakem raporu görünüyor', str_contains($s, 'geçerlik katsayıları'));
den('  sürüm kaydı düştü', (int)($d['calisma']['surum'] ?? 0) >= 1, (string)($d['calisma']['surum'] ?? 0));
den('  yazar–hakem yazışması kayda geçti', (int)($d['hakemler'][0]['diyalog'] ?? 0) >= 1,
    (string)($d['hakemler'][0]['diyalog'] ?? 0));
/* AKIŞIN SONU GERÇEKTEN SON: çalışma onaylı olmalı ve hakem araması
   kapanmalı. Aksi hâlde "yayımlanabilir" diyen bir adım, sayfası
   "hakem aranıyor" diyen bir çalışma bırakırdı. */
den('  çalışma ONAYLI', !empty($d['onay']['onayli']), json_encode($d['onay'] ?? [], JSON_UNESCAPED_UNICODE));
den('  hakem araması KAPANDI', empty($d['araniyor']));
den('  üç rapor da nitelik eşiğini geçti',
    (int)($d['onay']['kabul'] ?? 0) >= (int)($d['onay']['esik'] ?? 2), json_encode($d['onay'] ?? []));
den('  sayfada "Hakem aranıyor" rozeti yok',
    !preg_match('/rz[^>]*>\s*Hakem aranıyor/u', $s));
/* Rol ekranları: bildirimin asıl istediği "onlara bakmak istiyorum". */
$hs = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/hakem.php?t=' . rawurlencode((string)$d['hakemler'][0]['token']) . '&lang=tr');
den('hakemin gözünden ekran açılıyor', $hs !== '' && !str_contains($hs, 'Bulunamadı'), (string)strlen($hs));
$ys = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/yazar.php?t=' . rawurlencode((string)$d['yazar']['token']) . '&lang=tr');
den('yazarın gözünden ekran açılıyor', $ys !== '', (string)strlen($ys));
den('panelde üç rolün de bağı var',
    str_contains($pnl, "Okurun gözünden") && str_contains($pnl, "Yazarın gözünden")
    && str_contains($pnl, "hakemin gözünden"));

/* ------------------------------------------------------------------ */
echo "\n== 5.b RET YOLU: iki ret KİLİTLER, çalışma yayında kalır ==\n";
/* Ret, kabulün aynası değildir; kendi kuralları vardır ve en pahalısı
   geri alınamaz: iki ret alan çalışma KİLİTLENİR, yazar metnini bir
   daha değiştiremez ve çalışma ret gerekçeleriyle birlikte YAYINDA
   KALIR. Bir yayın sisteminin en ağır kuralı budur ve hiç denenmemişti.

   AYRI BİR KAYITTA yürür: bir çalışma ya kabul yolunu yürür ya ret
   yolunu; ikisini tek kayıtta denemek ikisini de yarım denemek olurdu. */
$d = dist('/api/yonetim/deneme-durum');
den('ret yolu kaydı kuruldu', is_array($d['ret'] ?? null));
if (is_array($d['ret'] ?? null)) {
    den('  kabul yolundan AYRI bir çalışma',
        (string)$d['ret']['slug'] !== (string)$d['calisma']['slug']);
    $retBek = ['kuruldu', 'hakem-atandi', 'davet-kabul', 'ret-1', 'hakem-2', 'kilitli'];
    $retRapor = str_repeat('Yöntem bölümü bulguları taşıyacak durumda değil: örneklem, ölçüm aracı ve dönem yazılmamış. ', 4);
    $retNot = ['notlar' => json_encode([
        ['alinti' => 'Yöntem', 'not' => 'Örneklem ve ölçüm aracı yazılmamış.'],
        ['alinti' => 'Bulgular', 'not' => 'Veriye dayanmayan çıkarım var.'],
    ], JSON_UNESCAPED_UNICODE)];
    for ($i = 0; $i < 8; $i++) {
        $a = (string)($d['ret']['adim'] ?? '');
        den('  ret adımı ' . ($i + 1) . ': ' . $a . ' (beklenen ' . ($retBek[$i] ?? '-') . ')',
            $a === ($retBek[$i] ?? '-'), $a);
        if ($a === 'kilitli') break;
        $id = (string)$d['ret']['id'];
        if ($a === 'kuruldu')          $x = dist('/api/editor/hakem-ata', ['id' => $id, 'ad' => 'Ret Hakemi', 'eposta' => $eposta('Ret Hakemi')]);
        elseif ($a === 'hakem-atandi') $x = dist('/api/hakem-davet-yanit', ['d' => $d['ret']['hakemler'][0]['davet'], 'yanit' => 'kabul']);
        elseif ($a === 'davet-kabul')  $x = dist('/api/hakem-gonder', ['t' => $d['ret']['hakemler'][0]['token'], 'mail' => $eposta('Ret Hakemi'), 'sifre' => $d['ret']['hakemler'][0]['sifre'], 'karar' => 'ret', 'rapor' => $retRapor] + $retNot);
        elseif ($a === 'ret-1') {
            $x = dist('/api/editor/hakem-ata', ['id' => $id, 'ad' => 'Ret Hakemi 2', 'eposta' => $eposta('Ret Hakemi 2')]);
            if (!empty($x['ok'])) { $d = dist('/api/yonetim/deneme-durum');
                $x = dist('/api/hakem-davet-yanit', ['d' => $d['ret']['hakemler'][1]['davet'], 'yanit' => 'kabul']); }
        }
        elseif ($a === 'hakem-2')      $x = dist('/api/hakem-gonder', ['t' => $d['ret']['hakemler'][1]['token'], 'mail' => $eposta('Ret Hakemi 2'), 'sifre' => $d['ret']['hakemler'][1]['sifre'], 'karar' => 'ret', 'rapor' => $retRapor] + $retNot);
        else $x = ['ok' => true];
        den('    işletildi', !empty($x['ok']), (string)($x['hata'] ?? ''));
        if (empty($x['ok'])) break;
        $d = dist('/api/yonetim/deneme-durum');
    }
    den('  iki retten sonra KİLİTLİ', !empty($d['ret']['kilitli']));
    /* Kilit gerçekten uygulanıyor mu: yazar metni değiştiremesin. */
    $yz = ['t' => (string)$d['ret']['yazar']['token'], 'sifre' => (string)$d['ret']['yazar']['sifre'],
           'y' => (string)$d['ret']['slug']];
    $x = dist('/api/yazar-kaydet', $yz + ['baslik' => 'Değişti mi', 'metin' => 'yeni metin']);
    den('  yazar metni ARTIK DEĞİŞTİREMİYOR', empty($x['ok']), (string)($x['hata'] ?? ''));
    den('    gerekçe iki ret kararını söylüyor',
        str_contains((string)($x['hata'] ?? ''), 'iki ret'), (string)($x['hata'] ?? ''));
    /* SİLİNMEZ, YAYINDA KALIR: ret gerekçeleriyle birlikte. */
    $sr = (string)@file_get_contents('http://127.0.0.1:' . $PORT . '/yazi.php?y=' . rawurlencode((string)$d['ret']['slug']) . '&lang=tr');
    den('  çalışma yayında KALIYOR', $sr !== '' && str_contains($sr, 'Deneme çalışması'));
    den('    ret gerekçesi sayfada görünüyor', str_contains($sr, 'örneklem, ölçüm aracı ve dönem'));
    /* ÖLÇÜLEN KUSUR — 19 Ağustos 2026. İki ret gelince ret_donusum turu
       'yazi' yapıyordu ve 'tur' alanına bakan sayfa raporları hiç
       basmıyor, üstüne "hakem değerlendirmesinden geçmemiştir" diyordu.
       Sistemin kendi yazdığı kural bunun tersiydi (hakemlik.php: "ret
       alan çalışma silinmez; hakemsiz yazıya döner ve aldığı raporlarla
       birlikte açık kalır"). Dördü birden ölçülür ki kural bir daha
       'tur' alanına bağlanmasın. */
    den('    "hakem değerlendirmesinden geçmemiştir" YAZMIYOR',
        !str_contains($sr, 'bağımsız bir yazıdır'));
    den('    ret bandı sayfada', str_contains($sr, 'MAKALE RET EDİLMİŞTİR'));
    den('    hakem bölümü sayfada duruyor', str_contains($sr, 'Hakem Değerlendirmesi'));
    den('    künye ret geçmişini söylüyor', str_contains($sr, 'hakem reddi'));
    $kRet = null;
    foreach (json_decode((string)@file_get_contents($VERI . '/yazilar.json'), true) ?: [] as $w) {
        if (is_array($w) && (string)($w['slug'] ?? '') === (string)$d['ret']['slug']) $kRet = $w;
    }
    den('    aşama "reddedildi"', is_array($kRet) && tg_hakem_asamasi($kRet) === 'reddedildi',
        is_array($kRet) ? tg_hakem_asamasi($kRet) : 'kayıt yok');
    den('    ret türü "hakem" sayılıyor', is_array($kRet) && tg_ret_turu($kRet) === 'hakem');
    den('    geçmiş "hakemden geçti" sayılıyor', is_array($kRet) && tg_hakemden_gecti($kRet));
    den('    yeni hakem ARANMIYOR', is_array($kRet) && !tg_hakem_araniyor($kRet));
}

/* ------------------------------------------------------------------ */
echo "\n== 6. Deneme kaydı arşive SIZMIYOR ==\n";
foreach ([['/yazilar.php', 'çalışma listesi'], ['/index.php', 'ana sayfa'],
          ['/oai.php?verb=ListRecords&metadataPrefix=oai_dc', 'OAI'],
          ['/sitemap.php', 'site haritası'], ['/istatistik.php', 'istatistik']] as [$yol, $ad]) {
    $x = (string)@file_get_contents('http://127.0.0.1:' . $PORT . $yol);
    den('  ' . $ad . ' deneme kaydını göstermiyor', $x !== '' && !str_contains($x, $slug), $yol);
    if (is_array($d['ret'] ?? null)) {
        den('    ret kaydını da göstermiyor', $x !== '' && !str_contains($x, (string)$d['ret']['slug']), $yol);
    }
}

/* ------------------------------------------------------------------ */
echo "\n== 7. Düzen geri alınabiliyor ==\n";
$r = dist('/api/yonetim/deneme-sil', []);
den('deneme kayıtları silindi', !empty($r['ok']), (string)($r['hata'] ?? ''));
olc('silinen: ' . (int)($r['hesap'] ?? 0) . ' hesap, ' . (int)($r['calisma'] ?? 0) . ' çalışma');
$d = dist('/api/yonetim/deneme-durum');
den('  durum yeniden "yok"', ($d['adim'] ?? '') === 'yok', (string)($d['adim'] ?? ''));

echo "\n----------------------------------------\n";
echo "GECTI: $gecti   KALDI: $kaldi\n";
exit($kaldi > 0 ? 1 : 0);
