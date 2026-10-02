# Yazı tipleri

## kutadgu-serif-*.woff2

Başlıklarda ve marka yazısında kullanılan yüz **Caladea**'dır.
Telif: 2012 Huerta Tipografía / The Caladea Project Authors.
Lisans: **SIL Open Font License 1.1** (tam metin: `CALADEA-LISANS.txt`).
Kaynak: https://github.com/huertatipografica/Caladea

Buradaki dosyalar özgün yüzün alt kümesidir: yalnızca Latin, Latin
Genişletilmiş A ve gerekli noktalama işaretleri bırakılmış, geri kalan
harfler çıkarılmıştır. Türkçenin bütün harfleri (ğ, ı, İ, ş, ç, ö, ü,
â, î, û) içeridedir. Böylece her dosya 16-17 KB'de kalır ve sayfa
açılışına yük bindirmez.

Alt küme şu komutla üretilmiştir (fontTools):

    pyftsubset Caladea-Regular.ttf \
      --unicodes=U+0020-007E,U+00A0-00FF,U+0100-017F,U+018F,U+0192,\
U+01FA-01FF,U+0218-021B,U+02C6-02DD,U+2000-206F,U+2070,U+2074-2079,\
U+2080-2089,U+20AC,U+2122,U+2190-2193,U+2212,U+2215,U+25CF,U+2713,U+FB01-FB02 \
      --layout-features=kern,liga,onum,tnum,frac \
      --flavor=woff2 --desubroutinize --no-hinting \
      --output-file=kutadgu-serif-400.woff2

OFL, alt küme almaya ve yeniden dağıtmaya izin verir; koşul lisans
metninin birlikte taşınmasıdır. `CALADEA-LISANS.txt` bu yüzden burada
durur ve silinmemelidir.

Arayüz ve gövde metni bilerek dışarıdan yazı tipi çekmez: işletim
sisteminin kendi arayüz yüzü kullanılır. Bu, her platformda tanıdık
görünür ve tek bir ek istek bile yapmaz.

## DejaVu*.ttf

PDF üretiminde kullanılır. Web sayfalarında kullanılmaz.

Lisans: **Bitstream Vera Fonts Copyright** (DejaVu'nun kendi eklediği
değişiklikler kamu malıdır). `DejaVuSans*.ttf` ayrıca Arev'den gelen
glifleri taşır; onlar da **Arev Fonts Copyright** altındadır. Tam
metinler: `DEJAVU-LISANS.txt`.
Lisans adresi: http://dejavu.sourceforge.net/wiki/index.php/License

Buradaki dört dosya alt küme değildir; yüzler olduğu gibi dağıtılır.

Lisans izin vericidir ama Caladea'daki OFL gibi bir koşulu vardır:
telif ve izin bildirimi yazı tipinin her kopyasıyla birlikte
taşınmalıdır. `DEJAVU-LISANS.txt` bu yüzden burada durur ve TTF
dosyaları depoda olduğu sürece silinmemelidir. O metin elle yazılmadı;
yazı tiplerinin kendi `name` tablosundan (nameID 0, 13, 14) çıkarıldı,
böylece dağıttığımız dosyanın taşıdığı bildirimle birebir aynı.

Lisans, yüzler "Bitstream"/"Vera" (ve Sans için "Tavmjong Bah"/"Arev")
adlarını taşımayacak biçimde yeniden adlandırılmadıkça değiştirilerek
dağıtılmalarına izin vermez. Dosyalara dokunmuyoruz; değiştirmeniz
gerekirse önce lisansın o maddesini okuyun.
