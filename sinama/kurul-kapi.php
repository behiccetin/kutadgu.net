<?php
/* =====================================================================
   Baş editörlük süre düzeni: kapı ölçümü. Depoya girmez.

   Her senaryo kendi ayar dosyasıyla ve KENDİ SÜRECİNDE koşar, çünkü
   tg_ayar() dosyayı bir kez okuyup bellekte tutar; aynı süreçte ikinci
   bir ayar denemek, birincinin sonucunu ölçmek olurdu.

   Kullanım:
     php kurul-kapi.php            bütün senaryolar
     php kurul-kapi.php <senaryo>  tek senaryo (alt süreç bunu çağırır)
   ===================================================================== */
declare(strict_types=1);


/* KURUCU ADRESLERİ DEPOYA GİRMEZ (açık depo, 3 Ekim 2026).
   ayar.php yalnızca adreslerin özetini taşır. Bu sınama gerçek adresleri
   KUTADGU_KURUL_EPOSTA ortam değişkeninin gösterdiği JSON dosyasından
   okur; biçim sunucudaki kurul-eposta.json ile aynıdır:
     { "Murat Kayalar": "...", "Gökhan Kalağan": "...", ... }
   Dosya verilmezse yer tutucu adresler kullanılır ve özet eşleşmesi
   gereken denetimler KALIR; bu bir hata değil, eksik girdinin işaretidir. */
if (!function_exists('sk_eposta')) {
    function sk_eposta(string $ad): string {
        static $l = null;
        if ($l === null) {
            $f = getenv('KUTADGU_KURUL_EPOSTA');
            $l = ($f && is_file($f)) ? (array)json_decode((string)file_get_contents($f), true) : [];
        }
        return (string)($l[$ad] ?? ('kurucu-' . substr(md5($ad), 0, 6) . '@ornek.org'));
    }
}
const KAYNAK = '/home/claude/kg/kutadgunet';
const KOPYA  = '/tmp/kv';

$gecti = 0; $kaldi = 0;
function den(string $ad, bool $sonuc, string $ek = ''): void {
    global $gecti, $kaldi;
    if ($sonuc) { $gecti++; echo "  GECTI  $ad\n"; }
    else { $kaldi++; echo "  KALDI  $ad" . ($ek !== '' ? "  ($ek)" : '') . "\n"; }
}

/* ---- Senaryolar: gerçek ayar dosyasını alır, gereken satırı değiştirir ---- */
function ayar_kur(string $senaryo): array {
    $a = include KAYNAK . '/ayar.php';
    $gecmis = '2020-01-01';

    switch ($senaryo) {

        case 'bugun':
            break;

        case 'yetki-kapandi-devralan-yok':
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            foreach ($a['bas_editorler'] as $i => $k) $a['bas_editorler'][$i]['editor_yetki_bitis'] = $gecmis;
            break;

        case 'yetki-kapandi-devralan-var':
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            foreach ($a['bas_editorler'] as $i => $k) $a['bas_editorler'][$i]['editor_yetki_bitis'] = $gecmis;
            $a['bas_editor_koltuk'] = 6;
            $a['gorevdeki_bas_editorler'] = [
                ['ad' => 'Zeynep Aydın', 'unvan' => 'Doç. Dr.', 'kurum' => 'Kurum',
                 'eposta' => 'zeynep@ornek.org', 'atama_tarih' => '2026-10-01',
                 'atayan' => ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']],
            ];
            break;

        case 'devir':
            $a['bas_editorler'][1]['devir'] = ['tarih' => $gecmis, 'devralan' => 'Zeynep Aydın'];
            break;

        case 'vefat':
            $a['bas_editorler'][2]['vefat'] = ['tarih' => $gecmis];
            break;

        case 'karar-eksik':
            $a['bas_editorler'][3]['gorev_sonu'] = ['tarih' => $gecmis,
                'karar_veren' => ['Behiç Çetin', 'Murat Kayalar']];
            break;

        case 'karar-yeterli':
            $a['bas_editorler'][3]['gorev_sonu'] = ['tarih' => $gecmis,
                'karar_veren' => ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']];
            break;

        case 'karar-yabanci-ad':
            $a['bas_editorler'][3]['gorev_sonu'] = ['tarih' => $gecmis,
                'karar_veren' => ['Behiç Çetin', 'Biri', 'Baska Biri', 'Ucuncu Biri']];
            break;

        case 'atanmis-suresiz':
            $a['gorevdeki_bas_editorler'] = [
                ['ad' => 'Zeynep Aydın', 'eposta' => 'zeynep@ornek.org', 'atama_tarih' => '2026-10-01'],
            ];
            break;

        case 'atanmis-bozuk-tarih':
            $a['gorevdeki_bas_editorler'] = [
                ['ad' => 'Bozuk Tarih', 'eposta' => 'bozuk@ornek.org', 'gorev_bitis' => '31.12.2027'],
            ];
            break;

        case 'onursal-sira':
            /* İki kurucu bıraktı, iki atanmışın süresi doldu. */
            $a['bas_editorler'][3]['devir'] = ['tarih' => $gecmis, 'devralan' => 'X'];
            $a['bas_editorler'][1]['vefat'] = ['tarih' => $gecmis];
            $a['gorevdeki_bas_editorler'] = [
                ['ad' => 'Zeynep Aydın', 'eposta' => 'z@ornek.org', 'gorev_bitis' => $gecmis],
                ['ad' => 'Ahmet Bulut',  'eposta' => 'a@ornek.org', 'gorev_bitis' => $gecmis],
            ];
            break;

        case 'kurul-kucuk':
            /* Üç kurucu ayrıldı: kurul ikiye indi. */
            foreach ([1, 2, 3] as $i) $a['bas_editorler'][$i]['devir'] = ['tarih' => $gecmis, 'devralan' => 'X'];
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            break;

        case 'devir-proje':
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            $a['devir'] = ['tarih' => $gecmis, 'devralan' => 'Bir Proje', 'tur' => 'proje'];
            break;

        case 'devir-dernek':
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            $a['devir'] = ['tarih' => $gecmis, 'devralan' => 'Kutadgu Derneği', 'tur' => 'dernek'];
            break;

        case 'kurul-bos':
            /* Beş kurucunun beşi de görevi bıraktı. */
            foreach ($a['bas_editorler'] as $i => $k) {
                $a['bas_editorler'][$i]['devir'] = ['tarih' => $gecmis, 'devralan' => 'X'];
            }
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            break;

        case 'koltuk-tavani':
            /* Tarih henüz geçmedi, kurul dolu. */
            break;

        case 'cikarma-tarihten-bagimsiz':
            /* Tarih geçti; buna rağmen çoğunluk kararı görevi bitirir. */
            $a['bas_editor_atama']['yetki_bitis'] = $gecmis;
            $a['bas_editorler'][4]['gorev_sonu'] = ['tarih' => $gecmis,
                'karar_veren' => ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']];
            break;
    }
    return $a;
}

/* ---- Senaryonun denetimleri ---- */
function kos(string $senaryo): void {
    switch ($senaryo) {

    case 'bugun':
        den('beş kurucu görevde', count(tg_bas_editorler_gorevde()) === 5, (string)count(tg_bas_editorler_gorevde()));
        den('onursal yok', tg_onursal_bas_editorler() === []);
        den('boş koltuk yok', tg_bos_koltuk() === 0, (string)tg_bos_koltuk());
        den('atama yetkisi açık', tg_atama_yetkisi_acik());
        den('yeter sayı üç', tg_atama_yeter_sayisi() === 3, (string)tg_atama_yeter_sayisi());
        den('kayıt hatası yok', tg_gorev_kayit_hatalari() === [], implode(' | ', tg_gorev_kayit_hatalari()));
        den('kurucu sırası kayıt sırası', tg_kurucu_bas_editorler()[0]['ad'] === 'Behiç Çetin');
        den('zorunlu hâl listesi dörttür ve kapalıdır', count(tg_zorunlu_haller()) === 4,
            (string)count(tg_zorunlu_haller()));
        /* Bugün devralan yoktur ve bu doğrudur: ne kuruma devir yapıldı
           ne de kurucu olmayan bir baş editör göreve geldi. Yani 2027
           sonu geldiğinde yetki bu hâl yüzünden açık kalacak; kurucular
           birini göreve getirene ya da sistemi devredene kadar. */
        den('bugün geçerli tek zorunlu hâl devralan-yok', tg_zorunlu_hal() === ['devralan-yok'],
            implode(', ', tg_zorunlu_hal()));
        den('yetki bugün tarihe dayanıyor, zorunlu hâle değil', !tg_atama_zorunlu_halle_mi());
        den('kurul boş değil', !tg_zorunlu_haller()['kurul-bos']['var']);
        den('koltuk boş değil', !tg_zorunlu_haller()['koltuk-bos']['var']);
        den('her hâlin iki dilde adı ve gerekçesi var',
            !array_filter(tg_zorunlu_haller(), fn($h) => count($h['ad']) !== 2 || count($h['ic']) !== 2));
        break;

    case 'yetki-kapandi-devralan-yok':
        /* Karar belgesinde verilen söz: tarih geldiğinde yeriniz durur. */
        den('süresi geçmiş kurucular HÂLÂ GÖREVDE', count(tg_bas_editorler_gorevde()) === 5,
            (string)count(tg_bas_editorler_gorevde()));
        den('hiçbiri onursal olmadı', tg_onursal_bas_editorler() === []);
        den('baş editör rolü duruyor', hs_bas_editor_mu(sk_eposta('Murat Kayalar')));
        den('editör listesi yetkisinin tarihi okunuyor',
            hs_editor_yetki_bitis(sk_eposta('Murat Kayalar')) === '2020-01-01',
            hs_editor_yetki_bitis(sk_eposta('Murat Kayalar')));
        den('devralan yok', !tg_devralan_var());
        den('devralan yokken atama yetkisi AÇIK KALIR', tg_atama_yetkisi_acik());
        den('devralan yokken editör listesi yetkisi de açık kalır',
            hs_editor_atama_yetkisi(['eposta' => sk_eposta('Murat Kayalar'), 'roller' => []]));
        break;

    case 'yetki-kapandi-devralan-var':
        den('devralan var', tg_devralan_var());
        den('koltuk dolu', tg_bos_koltuk() === 0, (string)tg_bos_koltuk());
        den('atama yetkisi KAPANIR', !tg_atama_yetkisi_acik());
        den('kurucular yine de görevde', count(tg_bas_editorler_gorevde()) === 6,
            (string)count(tg_bas_editorler_gorevde()));
        den('editör listesi yetkisi kapanır',
            !hs_editor_atama_yetkisi(['eposta' => sk_eposta('Murat Kayalar'), 'roller' => []]));
        $s = tg_atama_gecerli(['ad' => 'Yeni Kisi', 'eposta' => 'y@ornek.org'],
                              ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
        den('yetki kapalıyken atama yazılamaz', empty($s['ok']));
        break;

    case 'devir':
        $k = tg_kurucu_bas_editorler();
        den('devreden kurucu KAYITTA DURUYOR', count($k) === 5, (string)count($k));
        den('  ve görevde değil', empty($k[1]['gorevde']));
        den('  ve bitiş yolu devir', ($k[1]['gorev_sonu_yol'] ?? '') === 'devir', (string)($k[1]['gorev_sonu_yol'] ?? ''));
        den('onursal listesine yazıldı', count(tg_onursal_bas_editorler()) === 1);
        den('görevdeki sayısı dörde indi', count(tg_bas_editorler_gorevde()) === 4);
        den('baş editör rolünü YİTİRDİ', !hs_bas_editor_mu(sk_eposta('Murat Kayalar')));
        den('öteki kurucu rolünü taşımayı sürdürüyor', hs_bas_editor_mu('cbehic@gmail.com'));
        den('bir koltuk boşaldı', tg_bos_koltuk() === 1, (string)tg_bos_koltuk());
        den('boş koltuk atama yetkisini açar', tg_atama_yetkisi_acik());
        den('yeter sayı üç (dört kişilik kurulda)', tg_atama_yeter_sayisi() === 3, (string)tg_atama_yeter_sayisi());
        $s = tg_atama_gecerli(['ad' => 'Yeni Kisi', 'eposta' => 'y@ornek.org'],
                              ['Behiç Çetin', 'Gökhan Kalağan', 'Mustafa Zihni Tunca']);
        den('kalanlar yerine birini atayabilir', !empty($s['ok']), implode(' | ', $s['hata'] ?? []));
        $s2 = tg_atama_gecerli(['ad' => 'Yeni Kisi', 'eposta' => 'y@ornek.org'],
                               ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
        den('görevi bırakmış kişinin oyu SAYILMAZ', empty($s2['ok']));
        break;

    case 'vefat':
        $k = tg_kurucu_bas_editorler();
        den('vefat eden kurucu kayıtta duruyor', count($k) === 5);
        den('  ve görevde değil', empty($k[2]['gorevde']));
        den('  ve bitiş yolu vefat', ($k[2]['gorev_sonu_yol'] ?? '') === 'vefat', (string)($k[2]['gorev_sonu_yol'] ?? ''));
        den('doğrudan onursal listesinde', count(tg_onursal_bas_editorler()) === 1);
        den('baş editör rolü yok', !hs_bas_editor_mu(sk_eposta('Gökhan Kalağan')));
        den('bir koltuk boşaldı', tg_bos_koltuk() === 1);
        den('kalanlar yeni baş editör atayabilir', tg_atama_yetkisi_acik());
        break;

    case 'karar-eksik':
        den('iki adlı karar görevi BİTİRMEZ', count(tg_bas_editorler_gorevde()) === 5,
            (string)count(tg_bas_editorler_gorevde()));
        den('kişi görevde', hs_bas_editor_mu(sk_eposta('Mustafa Zihni Tunca')));
        den('eksiklik sessizce yutulmaz', tg_gorev_kayit_hatalari() !== []
            || tg_gorev_durumu(tg_ayar('bas_editorler')[3])['hata'] !== '',
            'hata bildirilmedi');
        break;

    case 'karar-yeterli':
        den('üç adlı karar görevi bitirir', count(tg_bas_editorler_gorevde()) === 4,
            (string)count(tg_bas_editorler_gorevde()));
        den('kişi onursal', count(tg_onursal_bas_editorler()) === 1);
        den('baş editör rolü yok', !hs_bas_editor_mu(sk_eposta('Mustafa Zihni Tunca')));
        den('bitiş yolu karar', (tg_kurucu_bas_editorler()[3]['gorev_sonu_yol'] ?? '') === 'karar');
        break;

    case 'karar-yabanci-ad':
        den('kurucu olmayan adlar sayılmaz', count(tg_bas_editorler_gorevde()) === 5,
            (string)count(tg_bas_editorler_gorevde()));
        break;

    case 'atanmis-suresiz':
        den('süresiz atanmış kayıt GÖREVE ALINIR', count(tg_gorevdeki_bas_editorler()) === 1,
            implode(' | ', tg_gorev_kayit_hatalari()));
        den('kayıt hatası yok', tg_gorev_kayit_hatalari() === []);
        den('rolü var', hs_bas_editor_mu('zeynep@ornek.org'));
        den('devralan sayılır', tg_devralan_var());
        break;

    case 'atanmis-bozuk-tarih':
        den('bozuk tarihli kayıt göreve alınmaz', tg_gorevdeki_bas_editorler() === []);
        den('hata bildirilir', tg_gorev_kayit_hatalari() !== [], 'hata yok');
        break;

    case 'onursal-sira':
        $o = tg_onursal_bas_editorler();
        $ad = array_map(fn($x) => (string)$x['ad'], $o);
        den('dört onursal', count($o) === 4, implode(', ', $ad));
        den('önce kurucular, kayıt sırasıyla',
            array_slice($ad, 0, 2) === ['Murat Kayalar', 'Mustafa Zihni Tunca'], implode(', ', $ad));
        den('sonra ötekiler, alfabetik',
            array_slice($ad, 2) === ['Ahmet Bulut', 'Zeynep Aydın'], implode(', ', $ad));
        den('onursalların hiçbiri görevde değil', count(tg_bas_editorler_gorevde()) === 3,
            (string)count(tg_bas_editorler_gorevde()));
        den('onursalın rolü yok', !hs_bas_editor_mu(sk_eposta('Murat Kayalar')));
        break;

    case 'kurul-kucuk':
        den('kurul ikiye indi', count(tg_bas_editorler_gorevde()) === 2,
            (string)count(tg_bas_editorler_gorevde()));
        den('yeter sayı salt çoğunluğa iner', tg_atama_yeter_sayisi() === 2,
            (string)tg_atama_yeter_sayisi());
        den('üç boş koltuk', tg_bos_koltuk() === 3, (string)tg_bos_koltuk());
        den('atama yetkisi açık kalır', tg_atama_yetkisi_acik());
        $s = tg_atama_gecerli(['ad' => 'Yeni Kisi', 'eposta' => 'y@ornek.org'],
                              ['Behiç Çetin', 'İbrahim Atilla Acar']);
        den('iki oyla atama yapılabilir', !empty($s['ok']), implode(' | ', $s['hata'] ?? []));
        $s1 = tg_atama_gecerli(['ad' => 'Yeni Kisi', 'eposta' => 'y@ornek.org'], ['Behiç Çetin']);
        den('tek oy yetmez', empty($s1['ok']));
        break;

    case 'devir-proje':
        den('proje devir sayılmaz', !tg_devir_oldu());
        den('devralan yok', !tg_devralan_var());
        den('kurucuların yetkisi aynı şekilde sürer', tg_atama_yetkisi_acik());
        den('kurucular görevde', count(tg_bas_editorler_gorevde()) === 5);
        break;

    case 'devir-dernek':
        den('dernek devri devirdir', tg_devir_oldu());
        den('devralan var', tg_devralan_var());
        den('koltuk dolu olduğu için atama yetkisi kapanır', !tg_atama_yetkisi_acik());
        den('  ve hiçbir zorunlu hâl yok', tg_zorunlu_hal() === [], implode(', ', tg_zorunlu_hal()));
        den('kurucular yine de görevde', count(tg_bas_editorler_gorevde()) === 5);
        break;

    case 'kurul-bos':
        den('görevde kimse yok', tg_bas_editorler_gorevde() === []);
        den('zorunlu hâl: kurul-bos', in_array('kurul-bos', tg_zorunlu_hal(), true),
            implode(', ', tg_zorunlu_hal()));
        den('  yanında devralan-yok ve koltuk-bos da var',
            in_array('devralan-yok', tg_zorunlu_hal(), true) && in_array('koltuk-bos', tg_zorunlu_hal(), true));
        den('yetki zorunlu hâlle açılır', tg_atama_yetkisi_acik());
        den('  ve yalnızca zorunlu hâl sayesinde açık', tg_atama_zorunlu_halle_mi());
        den('beş kurucunun beşi de onursal', count(tg_onursal_bas_editorler()) === 5);
        den('yeter sayı bire iner (boş kurulda)', tg_atama_yeter_sayisi() === 1,
            (string)tg_atama_yeter_sayisi());
        break;

    case 'koltuk-tavani':
        den('tarih henüz geçmedi, yetki açık', tg_atama_yetkisi_acik());
        den('  ama zorunlu hâlle değil', !tg_atama_zorunlu_halle_mi());
        den('kurul dolu', tg_bos_koltuk() === 0);
        $s = tg_atama_gecerli(['ad' => 'Altinci Kisi', 'eposta' => 'alti@ornek.org'],
                              ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
        den('dolu kurula atama YAZILAMAZ', empty($s['ok']), implode(' | ', $s['hata'] ?? []));
        den('  ve sebebi koltuk tavanıdır',
            (bool)array_filter($s['hata'] ?? [], fn($h) => strpos($h, 'boş koltuk yok') !== false),
            implode(' | ', $s['hata'] ?? []));
        break;

    case 'cikarma-tarihten-bagimsiz':
        den('tarih geçmiş olsa da çoğunluk kararı görevi bitirir',
            count(tg_bas_editorler_gorevde()) === 4, (string)count(tg_bas_editorler_gorevde()));
        den('kişi onursal', count(tg_onursal_bas_editorler()) === 1);
        den('rolünü yitirdi', !hs_bas_editor_mu(sk_eposta('İbrahim Atilla Acar')));
        den('boşalan koltuk zorunlu hâl doğurur', in_array('koltuk-bos', tg_zorunlu_hal(), true));
        den('  ve yerine atama yapılabilir', tg_atama_yetkisi_acik());
        $s = tg_atama_gecerli(['ad' => 'Yerine Gelen', 'eposta' => 'yg@ornek.org'],
                              ['Behiç Çetin', 'Murat Kayalar', 'Gökhan Kalağan']);
        den('  atama geçerli', !empty($s['ok']), implode(' | ', $s['hata'] ?? []));
        break;
    }
}

/* ---- Koşum ---- */
$senaryolar = ['bugun', 'yetki-kapandi-devralan-yok', 'yetki-kapandi-devralan-var',
               'devir', 'vefat', 'karar-eksik', 'karar-yeterli', 'karar-yabanci-ad',
               'atanmis-suresiz', 'atanmis-bozuk-tarih', 'onursal-sira', 'kurul-kucuk',
               'devir-proje', 'devir-dernek', 'kurul-bos', 'koltuk-tavani',
               'cikarma-tarihten-bagimsiz'];

$arg = $argv[1] ?? '';

if ($arg === '') {
    if (!is_dir(KOPYA)) { echo "KOPYA yok: " . KOPYA . " (once kur)\n"; exit(2); }
    $tg = 0; $tk = 0;
    foreach ($senaryolar as $s) {
        echo "== $s ==\n";
        $cikti = [];
        exec('php ' . escapeshellarg(__FILE__) . ' ' . escapeshellarg($s) . ' 2>&1', $cikti);
        foreach ($cikti as $satir) {
            echo $satir . "\n";
            if (strpos($satir, 'GECTI') !== false) $tg++;
            if (strpos($satir, 'KALDI') !== false) $tk++;
        }
        echo "\n";
    }
    echo "----------------------------------------\n";
    echo "GECTI: $tg   KALDI: $tk\n";
    exit($tk > 0 ? 1 : 0);
}

if (!in_array($arg, $senaryolar, true)) { echo "bilinmeyen senaryo: $arg\n"; exit(2); }
file_put_contents(KOPYA . '/ayar.php', "<?php\nreturn " . var_export(ayar_kur($arg), true) . ";\n");
require_once KOPYA . '/k/hesap.php';
kos($arg);
