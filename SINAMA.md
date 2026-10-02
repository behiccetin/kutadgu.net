# Kutadgu: sistemi baştan sona sınama

Bu belge, sistemin gerçekten işleyip işlemediğini yayına açmadan önce
kendi elinizle görmeniz içindir. Sıra önemlidir: her adım bir öncekinin
ürettiği durumu kullanır.

Bir not: `SINAMA.md` sunucuda **yayımlanmaz**. `.htaccess` içindeki kural
`.md` uzantılı dosyaları dışarıya kapatır; bu belge yalnızca depoda durur.

---

## 0. Hazırlık

- [ ] `GONDER.bat` çalıştırıldı, sunucudaki cron çekimi tamamlandı
      (en çok iki dakika).
- [ ] Tarayıcıda **sert yenileme** yapıldı (Ctrl+F5). Stil sürümü
      değiştiği için eski dosya önbellekte kalmasın.
- [ ] Sağ alttan tema koyuya alınıp geri açığa alındı; bir sayfa iki
      temada da okunuyor.

## 1. Okur gözüyle (hesapsız)

Hesap açmadan, çıkış yapmış hâlde:

- [ ] Ana sayfa açılıyor; kaydırak üç levhayı geçiyor (oklarla ve
      noktalarla), ikinci levhadaki işarete tıklayınca kimlik sayfası
      açılıyor.
- [ ] Arşivden bir çalışma açılıyor, tam metin okunuyor, PDF iniyor.
- [ ] Arşiv sayfasında arama çalışıyor: Türkçe harf kullanmadan
      ("cetin") arandığında da doğru sonuç geliyor.
- [ ] Süzgeçler ve sıralama çalışıyor: tür, yıl, anahtar sözcük;
      en yeni / en eski / en çok okunan / başlığa göre.
- [ ] Üst çubuktaki arama kutusu başka bir sayfadan arşive götürüyor.
- [ ] Dil düğmesiyle EN'e geçiliyor; aynı sayfada kalınıyor,
      ana sayfaya atmıyor.
- [ ] `/istatistik.php` sayıları arşivle tutuyor.
- [ ] `/arsiv.php` iniyor ve içinde **hiçbir e-posta adresi yok**
      (dosyada `@` aratın).
- [ ] Telefonda: sol menü düğmeyle açılıyor, kapanıyor; arama düğmesi
      kutuyu açıyor.

## 2. Hesap ve roller

- [ ] `/panel.php` üzerinden **yeni bir hesap** açılıyor
      (kendi asıl hesabınızla değil, sınama için ikinci bir adresle).
- [ ] İlk girişte kullanıcı adı isteniyor, bir kez seçiliyor.
- [ ] Sağ üstteki daire baş harfleri gösteriyor; bekleyen iş varsa
      üstünde kırmızı sayı çıkıyor.
- [ ] Panelde beş sekme de açılıyor: Özet, Çalışmalarım, Hakemliğim,
      Hesabım, (editörseniz) Editör.
- [ ] Hesabım sekmesinde doğrulama üç yoldan biriyle sunuluyor:
      e-Devlet kodu, ORCID, kurumun kamusal sayfası.
- [ ] Çıkış yapılıyor, yeniden giriliyor.

## 3. Yazar akışı

- [ ] `/basvuru.php` formu **eksik** doldurulup gönderiliyor:
      hangi alanın eksik olduğu söyleniyor.
- [ ] Form tam doldurulup gönderiliyor; onay ekranı geliyor.
- [ ] Editör hesabıyla başvuru görülüyor, çalışma yayına alınıyor.
- [ ] Çalışmanın sayfasında tamga, parmak izi, benzerlik oranı,
      yapay zekâ beyanı ve veri beyanı görünüyor.
- [ ] Eski biçimdeki adres (`/10.00001/...`) yeni tamga adresine
      301 ile yönleniyor.

## 4. Hakemlik akışı

- [ ] `/bekleyen.php` üzerinden bir çalışmaya gönüllü olunuyor.
- [ ] Rapor yazılıyor; **nitelik eşiğinin altında** bir rapor
      gönderilmeye çalışıldığında uyarı çıkıyor.
- [ ] Yeterli rapor gönderiliyor; çalışmanın sayfasında hakemin adı,
      kararı ve raporu görünüyor.
- [ ] İki olumlu rapor sonrası çalışma "hakem onaylı" oluyor.
- [ ] Yazar kendi çalışmasına hakem olamıyor; yakın zamanda
      birbirini değerlendirmiş iki kişi karşılıklı hakem olamıyor.

## 5. İtiraz ve kurul oylaması

- [ ] Yazar bir rapora itiraz ediyor, oylama açılıyor.
- [ ] `/oylama.php` sayfasında oylama "oy bekleyenler" sekmesinde
      görünüyor.
- [ ] Oy veren biri, oylama açıkken **başkasının oyunu göremiyor**.
- [ ] Üçüncü oyla oylama kapanıyor ve bütün oylar gerekçeleriyle
      açılıyor.
- [ ] Kapanan oylama "sonuçlananlar" sekmesine geçiyor.

## 6. Yayın sonrası şerh

- [ ] Yayımlanmış bir çalışmaya şerh düşülüyor.
- [ ] Şerh, çalışmanın sayfasında adıyla görünüyor.
- [ ] Asgari karakter sayısının altındaki şerh kabul edilmiyor.

## 7. İletişim ve bildirim

- [ ] `/iletisim.php` üzerinden bir ileti gönderiliyor.
- [ ] İletiye özel bağlantıyla erişiliyor; başkası o bağlantıyı
      bilmeden konuşmayı göremiyor.
- [ ] Telegram bildirimi düşüyor (kurulum yapıldıysa).

## 8. Son denetim

- [ ] `/sitemap.xml` açılıyor ve yeni sayfaları içeriyor.
- [ ] `/robots.txt` açılıyor.
- [ ] `/oai` açılıyor.
- [ ] Bir çalışma sayfasının kaynağında `citation_title`,
      `citation_author` ve schema.org verisi var.
- [ ] `yedek.sh` gece çalışıyor; yedek dosyası oluşmuş.

---

## Sınamayı bozmadan yapmanın yolu

Sınama sırasında üretilen çalışma, rapor ve oylamalar arşivde iz bırakır.
İki seçenek var:

1. **Ayrı bir veri dizini.** Sunucuda `KUTADGU_DATA` değişkenini geçici
   olarak boş bir dizine gösterip sınamayı orada yapmak. Arşiv hiç
   kirlenmez. Yayına dönerken değişken eski değerine alınır.
2. **Kuruluş dönemi istisnası.** Sınama çalışmaları arşivde kalır ama
   sayfalarında "kuruluş dönemi kaydı" notuyla durur; ne olduğu açıkça
   yazılıdır. `ayar.php` içindeki `kurulus_istisna` listesi bunun içindir.

Birincisi temiz, ikincisi dürüst. İkisi de kabul edilebilir; ama
sınama kayıtlarını sessizce silmek kabul edilemez, çünkü bu sistemin
tamamı "yayımlanan bir şey geriye dönük değiştirilmez" ilkesi üzerine
kuruludur.
