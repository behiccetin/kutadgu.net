# İletişim botunun kurulumu

> **Durum: kurulum 7 Ağustos 2026'da tamamlandı.** Bot `@kutadgu_iletisim_bot`
> olarak açıldı, webhook kuruldu ve anahtarlar `ruh-ayar.json` içine yazıldı.
> Bu belge, anahtarın bir gün yenilenmesi gerekirse diye duruyor.

İletişim formu, özel konuşma sayfası ve webhook kodu hazır ve sınandı.
Anahtar depoya girmemeli, bu yüzden bu adımlar elle yapılır. Beş dakikalık
iştir.

## Neden ayrı bir bot

Sistemde zaten bir bildirim botu var; yeni çalışma, gelen rapor, sunulan
belge gibi olaylar oraya düşüyor. İletişim formu için **ayrı** bir bot
açıyoruz. Sebebi şu: iletişim botunun webhook adresi herkese açık bir
adrestir. İki işi tek bota bindirirseniz, bir gün o webhook'ta bir sorun
çıktığında sistemin bütün bildirimlerini de kaybedersiniz. Ayrı tutmak,
birinin arızasının diğerini etkilememesi içindir.

## 1. Botu açın

Telegram'da **@BotFather** ile konuşun:

    /newbot
    Ad:            Kutadgu İletişim
    Kullanıcı adı: kutadgu_iletisim_bot      (sonu "bot" ile bitmeli, boşta olan bir ad seçin)

BotFather size şuna benzer bir anahtar verir:

    8123456789:AAH-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx

Bu anahtarı kimseyle paylaşmayın. Anahtarı bilen herkes bot adına
konuşabilir.

## 2. Sohbet kimliğinizi öğrenin

Yeni açtığınız bota Telegram'dan bir kez **/start** yazın, sonra
tarayıcıda şu adresi açın (ANAHTAR yerine kendi anahtarınızı koyun):

    https://api.telegram.org/botANAHTAR/getUpdates

Dönen metinde `"chat":{"id":123456789` gibi bir sayı göreceksiniz. O sayı
sizin sohbet kimliğinizdir. Eksi ile başlıyorsa (grup ise) eksiyi de alın.

## 3. Gizli anahtarı üretin

Webhook adresi herkese açık olduğu için, gelen her isteğin gerçekten
Telegram'dan geldiğini doğrulayan bir gizli anahtar kullanıyoruz.
Rastgele, uzun ve tahmin edilemez olsun. Sunucuda şu komut işinizi görür:

    openssl rand -hex 24

## 4. Üçünü yapılandırmaya yazın

Sunucuda **webroot'un dışındaki** veri klasöründe `ruh-ayar.json`
dosyasını açın (kutadgu-data klasörü) ve `iletisim` bölümünü ekleyin:

    {
      "iletisim": {
        "token": "8123456789:AAH-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx",
        "chat":  "123456789",
        "gizli": "3. adımda ürettiğiniz uzun anahtar"
      }
    }

Dosyada başka bölümler varsa onları silmeyin; `iletisim` bölümünü
yanlarına ekleyin. Bu dosya web kökünün dışındadır ve depoya girmez;
anahtarlarınız hiçbir zaman GitHub'a çıkmaz.

## 5. Webhook'u kurun

Tek bir komut. ANAHTAR ve GIZLI yerine kendi değerlerinizi koyun:

    curl -s "https://api.telegram.org/botANAHTAR/setWebhook" \
      -d "url=https://kutadgu.net/api/iletisim-webhook" \
      -d "secret_token=GIZLI" \
      -d "allowed_updates=[\"message\"]"

Yanıt `{"ok":true,...}` ise kurulum bitti.

## 6. Sınayın

1. `kutadgu.net/iletisim.php` adresinden kendinize bir ileti gönderin.
   Telegram'a düşmeli.
2. Telegram'da o iletiyi **alıntılayarak yanıtlayın** (reply). Yanıtınız,
   yazan kişinin kendi konuşma sayfasında görünmelidir.

## Güvenlik: kod tarafında zaten yapılmış olanlar

Bunları bilmeniz iyi olur, çünkü bir gün biri "bu form güvenli mi" diye
sorarsa yanıtı hazır olsun:

- Gizli anahtarı taşımayan hiçbir istek işlenmez. Anahtarı bilmeyen bir
  saldırgan webhook adresine ne gönderirse göndersin hiçbir şey olmaz.
- Gelen iletinin sohbet kimliği, yapılandırmadaki kimlikle karşılaştırılır.
  Başka bir sohbetten gelen ileti yok sayılır.
- Yanıt metni HTML olarak değil düz metin olarak saklanır ve sayfada
  kaçırılarak basılır. Betik çalıştırılamaz; bunu `<img src=x onerror=...>`
  ve `<script>` yükleriyle sınadım, ikisi de düz metin olarak göründü.
- Konuşma sayfası tahmin edilemez bir anahtarla açılır, arama motorlarına
  kapalıdır ve hiçbir yerde listelenmez.
- Form bir e-posta aktarıcısına dönüşmesin diye bal kabı alanı, adres
  başına gönderim sınırı ve ileti uzunluğu sınırı vardır.

## Bir gün anahtar sızarsa

BotFather'da `/revoke` ile anahtarı iptal edin, yenisini alın,
`ruh-ayar.json` içindeki `token` değerini değiştirin ve 5. adımı yeni
anahtarla bir kez daha çalıştırın. Başka bir şey yapmanız gerekmez.
