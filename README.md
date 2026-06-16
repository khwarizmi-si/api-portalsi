# 📘 API Documentation - Portal SI (PSI)

RESTful API untuk aplikasi sosial media **Portal SI** 😵‍💫🧠🌀👁️👄👁️😱🙃😈👹  
**Base URL:** `https://api-new.portalsi.com/api/(endpoint)`

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

## Email Queue

Email verifikasi dan reset password dikirim lewat queue `mail` supaya request API tidak menunggu koneksi SMTP. Setelah deploy, jalankan migration dan worker berikut:

```bash
php artisan migrate
php artisan queue:work database --queue=mail,default --tries=3 --timeout=60
```

Environment yang bisa diatur:

```env
MAIL_QUEUE_CONNECTION=database
MAIL_QUEUE_NAME=mail
```

Jika memakai Supervisor/systemd di server, pastikan command worker di atas berjalan terus menerus dan restart otomatis saat gagal.
