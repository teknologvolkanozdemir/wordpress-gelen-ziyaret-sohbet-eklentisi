# wordpress-gelen-ziyaret-sohbet-eklentisi
gelen ziyaretçi ile sohbet etme imkanı sunar.

## Kurulum ve Kullanım

1. `ziyaretci-sohbet` dosyalarını `wp-content/plugins/` altına kopyalayıp eklentiyi etkinleştirin.
2. **Ayarlar → Ziyaretçi Sohbet** sayfasında **Bot Token** ve **Chat ID** girin (ikisi de zorunludur; site HTTPS olmalıdır). Kayıtta Telegram webhook otomatik kurulur.
3. Ziyaretçi mesajları Telegram'a düşer. Bir ziyaretçiye yanıt vermek için Telegram'da ilgili mesaja **yanıtla (reply)** yapın; cevap yalnızca o ziyaretçiye iletilir. Böylece tek pencerede birden fazla ziyaretçiyle görüşebilirsiniz.

Erişilebilirlik: etiketli form alanları, `role="log"`/`aria-live` ile ekran okuyucu duyuruları, klavye ile tam kullanım (Esc ile kapatma), görünür odak göstergesi.
