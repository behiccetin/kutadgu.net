#!/bin/sh
# =====================================================================
#  KUTADGU ARSIV YEDEGI
#  ---------------------------------------------------------------------
#  Kalici bir kimlik vermek, kaliciligi taahhut etmektir. Tek bir
#  sunucuda duran arsiv, o sunucu gittiginde gider. Bu betik arsivin
#  tamamini disari alir ve ikinci bir yere kopyalar.
#
#  Sunucuda her gece bir kez calistirilmasi icin:
#     crontab -e
#     17 3 * * *  <KURULUM_DIZINI>/yedek.sh >> <GUNLUK_DIZINI>/kutadgu-yedek.log 2>&1
#
#  Koseli ayrac icindeki iki yer kendi yollarinizla doldurulur. Gecerli
#  bir yola benzeyen ornek yazilmadi: benzeseydi kopyalayip yapistiran
#  onu oldugu gibi birakabilir ve yedek hic alinmadigi halde alindi
#  sanilirdi. Koseli ayrac doldurulmadan calistirilirsa hemen patlar.
#
#  Ne yapar:
#    1. Veri klasorunun (yazilar, oylamalar, serhler) tam kopyasini alir.
#    2. Herkese acik arsiv disa aktarimini indirir.
#    3. Her ikisinin de parmak izini yazar; kopyanin bozulup bozulmadigi
#       sonradan bu degerlerle denetlenebilir.
#    4. Belirtilen gun sayisindan eski yedekleri siler.
#
#  Ne yapmaz: uzak bir yere kendiliginden gondermez. Nereye
#  gonderilecegi bir karardir ve o karar sizindir. Asagidaki UZAK
#  degiskenine bir rsync hedefi yazarsaniz oraya da kopyalar.
# =====================================================================

set -eu

# ---- Ayarlar: yalnizca bu dort satiri degistirmeniz yeterli ----------
VERI="${KUTADGU_VERI:-<KURULUM_DIZINI>/kutadgu-data}"   # veri klasoru
HEDEF="${KUTADGU_YEDEK:-<KURULUM_DIZINI>/kutadgu-yedek}" # yedeklerin durdugu yer
ADRES="${KUTADGU_ADRES:-https://kutadgu.net}"        # sitenin adresi
GUN="${KUTADGU_YEDEK_GUN:-30}"                       # kac gunluk yedek tutulsun
UZAK="${KUTADGU_UZAK:-}"                             # ornek: kullanici@sunucu:/yedek/kutadgu
# ---------------------------------------------------------------------

DAMGA="$(date +%Y%m%d-%H%M)"
KLASOR="$HEDEF/$DAMGA"

if [ ! -d "$VERI" ]; then
  echo "HATA: veri klasoru bulunamadi: $VERI" >&2
  exit 1
fi

mkdir -p "$KLASOR"
echo "[$(date +%F\ %T)] yedek basliyor -> $KLASOR"

# 1. Veri klasorunun tam kopyasi
tar czf "$KLASOR/veri.tar.gz" -C "$(dirname "$VERI")" "$(basename "$VERI")"
echo "  veri.tar.gz  $(wc -c < "$KLASOR/veri.tar.gz") bayt"

# 2. Herkese acik dokum (kisisel veri icermez)
#    Dokum istek aninda uretilmez; asagidaki adresler yayim aninda ve her
#    gece uretilmis DURAGAN dosyalari akitir. Bu yuzden yedek almak
#    sunucuya yuk bindirmez.
if command -v curl >/dev/null 2>&1; then
  curl -fsS "$ADRES/dokum.php?durum=1"                         -o "$KLASOR/belirte.json"       || echo "  UYARI: belirte alinamadi"
  curl -fsS "$ADRES/dokum.php?d=kutadgu-ustveri.json"          -o "$KLASOR/ustveri.json"       || echo "  UYARI: ustveri alinamadi"
  curl -fsS "$ADRES/dokum.php?d=kutadgu-tam-tumu.json"         -o "$KLASOR/arsiv.json"         || echo "  UYARI: arsiv.json alinamadi"
  curl -fsS "$ADRES/dokum.php?d=kutadgu-parmak-izleri.txt"     -o "$KLASOR/parmak-izleri.txt"  || echo "  UYARI: parmak izleri alinamadi"
fi

# 3. Parmak izleri: kopyanin bozulup bozulmadigi bununla denetlenir
( cd "$KLASOR" && sha256sum ./* > OZETLER.txt 2>/dev/null || shasum -a 256 ./* > OZETLER.txt )
echo "  ozetler yazildi"

# 4. Uzak kopya (UZAK bos ise atlanir)
if [ -n "$UZAK" ] && command -v rsync >/dev/null 2>&1; then
  rsync -a --delete "$KLASOR/" "$UZAK/$DAMGA/" && echo "  uzak kopya tamam: $UZAK/$DAMGA/"
fi

# 5. Eski yedekleri temizle
find "$HEDEF" -maxdepth 1 -type d -name '20*' -mtime "+$GUN" -exec rm -rf {} + 2>/dev/null || true

echo "[$(date +%F\ %T)] yedek bitti"
