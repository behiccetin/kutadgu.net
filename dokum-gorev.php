<?php
/* =====================================================================
   KUTADGU - DÖKÜM GÖREVİ / DUMP TASK
   ---------------------------------------------------------------------
   Bu betik yalnızca komut satırından çalışır. Web üzerinden açılamaz;
   .htaccess bunu ayrıca kapatır, ancak kilit iki yerde durur, çünkü
   kilidin ucuz, unutmanın pahalı olduğu yerlerden biri burasıdır.

   Kurulum (sunucuda). Aşağıdaki <KURULUM_DIZINI> bir yer tutucudur:
   Kutadgu'nun kurulu olduğu klasörün yolunu oraya siz yazarsınız.
   Örnek satırlara gerçek bir sunucu yolu konmaz; o yol crontab'ı
   kurana bir şey kazandırmaz, yalnızca depoyu okuyan birine sunucudaki
   kullanıcı adını ve dizin düzenini bildirir. <GUNLUK_DIZINI> için de
   aynısı geçerlidir; yazma izniniz olan bir yer seçin.

     crontab -e
     23 3 * * *  php <KURULUM_DIZINI>/dokum-gorev.php gece   >> <GUNLUK_DIZINI>/kutadgu-dokum.log 2>&1
     7  * * * *  php <KURULUM_DIZINI>/dokum-gorev.php kuyruk >> <GUNLUK_DIZINI>/kutadgu-dokum.log 2>&1

   Komutlar:
     gece    Katman a ve b'yi üretir (değişiklik varsa), sonra kuyruğa
             bakar. Gecelik iş budur.
     hafif   Yalnızca katman a ve b.
     kuyruk  Bekleyen istek varsa ve günlük ara dolduysa katman c'yi
             üretir. Saatte bir çalıştırılabilir: günlük sınırı kendisi
             gözetir, sık çağrılması fazladan iş çıkarmaz.
     paket   Katman c'yi günlük sınıra bakmadan üretir (elle bakım).
     durum   Ne var ne yok, hiçbir şey üretmeden yazar.

   Kök adres: ayar.php içindeki 'kok' değeri kullanılır. Orası boşsa
   dökümdeki bağlantılar için KUTADGU_KOK ortam değişkeni okunur.
   ===================================================================== */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Bu betik yalnizca komut satirindan calisir.\nThis script runs from the command line only.\n");
}

date_default_timezone_set('Europe/Istanbul');

/* Komut satırında HTTP_HOST yoktur; bağlantılar kırılmasın diye
   ayardaki kök adres yoksa ortam değişkeni kullanılır. */
$kokEnv = getenv('KUTADGU_KOK');
if ($kokEnv) {
    $_SERVER['HTTP_HOST'] = preg_replace('#^https?://#', '', rtrim((string)$kokEnv, '/'));
    $_SERVER['HTTPS'] = 'on';
}

require_once __DIR__ . '/k/dokum.php';

$komut = strtolower(trim((string)($argv[1] ?? 'gece')));
$bas = microtime(true);

function gy(string $s): void { echo '[' . date('Y-m-d H:i:s') . '] ' . $s . "\n"; }

function gd(array $b): void {
    $t = 0;
    foreach (($b['dosyalar'] ?? []) as $d) {
        $t += (int)($d['boyut'] ?? 0);
        gy(sprintf('  %-32s %-2s %10s  %s  %s',
            (string)($d['ad'] ?? ''), (string)($d['katman'] ?? ''),
            dk_boy((int)($d['boyut'] ?? 0)),
            substr((string)($d['ozet'] ?? ''), 0, 16),
            (string)($d['uretim'] ?? '')));
    }
    gy('  toplam: ' . dk_boy($t) . ' / ' . count($b['dosyalar'] ?? []) . ' dosya');
}

switch ($komut) {

    case 'durum':
        $b = dk_belirte();
        gy('uretim: ' . (string)($b['uretim'] ?? '-') . '  sure: ' . (int)($b['sure_ms'] ?? 0) . ' ms');
        gy('calisma: ' . (int)($b['calisma_sayisi'] ?? 0));
        gd($b);
        gy('kuyrukta bekleyen: ' . dk_kuyruk_bekleyen() . '  en erken paket: ' . dk_sonraki_uretim());
        $iz = dk_kirli();
        gy($iz ? ('kirli iz var: ' . (string)($iz['tarih'] ?? '') . ' (' . (string)($iz['neden'] ?? '') . ')')
               : 'kirli iz yok: dokum guncel');
        break;

    case 'hafif':
    case 'gece':
        $s = dk_uret_hafif($komut === 'gece');
        if (!empty($s['atlandi'])) gy('katman a+b atlandi: ' . (string)($s['sebep'] ?? ''));
        elseif (empty($s['ok']))   gy('katman a+b URETILEMEDI: ' . (string)($s['sebep'] ?? ''));
        else gy('katman a+b uretildi: ' . (int)$s['sure_ms'] . ' ms, '
              . (int)$s['dosya'] . ' dosya, ' . (int)$s['calisma'] . ' calisma');
        if ($komut === 'gece') {
            $p = dk_kuyruk_isle();
            if (!empty($p['atlandi'])) gy('katman c atlandi: ' . (string)($p['sebep'] ?? ''));
            elseif (empty($p['ok']))   gy('katman c URETILEMEDI: ' . (string)($p['sebep'] ?? ''));
            else gy('katman c uretildi: ' . (int)($p['sure_ms'] ?? 0) . ' ms, '
                  . dk_boy((int)($p['boyut'] ?? 0)) . ', ' . (int)($p['gorsel_sayisi'] ?? 0) . ' gorsel, '
                  . (int)($p['belge_sayisi'] ?? 0) . ' belge');
            gd(dk_belirte());
        }
        break;

    case 'kuyruk':
        $bekleyen = dk_kuyruk_bekleyen();
        $p = dk_kuyruk_isle();
        if (!empty($p['atlandi'])) gy('katman c atlandi: ' . (string)($p['sebep'] ?? '') . ' (bekleyen: ' . $bekleyen . ')');
        elseif (empty($p['ok']))   gy('katman c URETILEMEDI: ' . (string)($p['sebep'] ?? ''));
        else gy('katman c uretildi: ' . (int)($p['sure_ms'] ?? 0) . ' ms, ' . dk_boy((int)($p['boyut'] ?? 0))
              . '; kuyruktaki ' . $bekleyen . ' istek karsilandi');
        break;

    case 'paket':
        $p = dk_uret_paket(true);
        if (empty($p['ok'])) gy('katman c URETILEMEDI: ' . (string)($p['sebep'] ?? ''));
        else gy('katman c uretildi: ' . (int)($p['sure_ms'] ?? 0) . ' ms, ' . dk_boy((int)($p['boyut'] ?? 0))
              . ', ' . (int)($p['gorsel_sayisi'] ?? 0) . ' gorsel, ' . (int)($p['belge_sayisi'] ?? 0) . ' belge');
        break;

    default:
        gy('bilinmeyen komut: ' . $komut);
        gy('kullanim: php dokum-gorev.php [gece|hafif|kuyruk|paket|durum]');
        exit(2);
}

gy('bitti: ' . (int)round((microtime(true) - $bas) * 1000) . ' ms');
