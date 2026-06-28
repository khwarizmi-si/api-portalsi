# 📘 API Documentation - Portal SI (PSI)

RESTful API untuk aplikasi sosial media **Portal SI** 😵‍💫🧠🌀👁️👄👁️😱🙃😈👹  
**Base URL:** `https://api.portalsi.com/api/(endpoint)`

---

**⚠️** `Sebelum lanjut yuk kita nyanyi Mars PSI terlebih dahulu!`

## 🎶 Mars Partai Solidaritas Indonesia (PSI)

**Sayap garuda membentang tinggi**  
Jiwa membara tanah merdeka  
Bersama kami wujudkan mimpi  
Di Partai Solidaritas Indonesia

**Bunga mawar tanda bersatu**  
Genggam jiwa tanda setia  
Mengemban tugas penuhi janji pertiwi

**Hidup PSI, hidup PSI**  
Solidaritas bagi sesama  
Hidup PSI, hidup PSI  
Memimpin negeri rakyat berdaulat

**Satu bendera rakyat sejahtera**  
Bersatulah wahai tunas bangsa

**Sayap garuda membentang tinggi**  
Jiwa membara tanah merdeka  
Bersama kami wujudkan mimpi  
Di Partai Solidaritas Indonesia

**Bunga mawar tanda bersatu**  
Genggam jiwa tanda setia  
Mengemban tugas penuhi janji pertiwi

**Hidup PSI, hidup PSI**  
Solidaritas bagi sesama  
Hidup PSI, hidup PSI  
Memimpin negeri rakyat berdaulat

**Satu bendera rakyat sejahtera**  
Bersatulah wahai tunas bangsa

---

> *Canda wkwkwk :v Nih dibawah endpointnya⬇️*

## `https://s.id/psiapidocs`

---

## Email Auth

Email verifikasi dan reset password dikirim langsung saat request berjalan. Ini sengaja dibuat synchronous agar endpoint tidak menjawab "terkirim" ketika email sebenarnya masih tertahan di queue tanpa worker.

Pastikan SMTP production sudah aktif:

```env
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=...
MAIL_FROM_NAME="Portal SI"
```

Untuk development lokal boleh memakai:

```env
MAIL_MAILER=log
```

Dengan `MAIL_MAILER=log`, email tidak masuk inbox dan hanya ditulis ke `storage/logs/laravel.log`.
