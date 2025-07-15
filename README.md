# 📘 API Documentation - Portal SI (PSI)

RESTful API untuk aplikasi sosial media **Portal SI** 😵‍💫🧠🌀👁️👄👁️😱🙃😈👹  
**Base URL:** `http://localhost:8000/api/(endpoint)`

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

## 🔐 Authentication

| Method | Endpoint     | Description                  |
|--------|--------------|------------------------------|
| POST   | `/register`  | Register user baru           |
| POST   | `/login`     | Login dan generate token     |
| POST   | `/logout`    | Logout user (revoke token)   |
| GET    | `/user`      | Get detail user yang login   |

---

## 👤 Account & Profile

| Method | Endpoint                    | Description                     |
|--------|-----------------------------|---------------------------------|
| GET    | `/profile/{id}`             | Lihat profil publik user        |
| PUT    | `/account/settings`         | Update username, email, dll     |
| PUT    | `/account/password`         | Ganti password                  |
| DELETE | `/account/delete`           | Hapus akun                      |

---

## 📮 Posts

| Method | Endpoint           | Description             |
|--------|--------------------|-------------------------|
| GET    | `/posts`           | Ambil semua postingan   |
| POST   | `/posts`           | Buat postingan baru     |
| GET    | `/posts/{id}`      | Detail 1 postingan      |
| PUT    | `/posts/{id}`      | Update postingan        |
| DELETE | `/posts/{id}`      | Hapus postingan         |

---

## 💬 Comments

| Method | Endpoint                              | Description                   |
|--------|---------------------------------------|-------------------------------|
| GET    | `/posts/{post_id}/comments`           | Ambil komentar dari post      |
| POST   | `/posts/{post_id}/comments`           | Tambah komentar               |
| PUT    | `/comments/{id}`                      | Edit komentar                 |
| DELETE | `/comments/{id}`                      | Hapus komentar                |

---

## ❤️ Likes

| Method | Endpoint                    | Description                    |
|--------|-----------------------------|--------------------------------|
| POST   | `/posts/{post_id}/like`     | Like/Unlike postingan          |
| GET    | `/posts/{post_id}/likes`    | Lihat siapa saja yang like     |

---

## 🤝 Follows

| Method | Endpoint                    | Description                    |
|--------|-----------------------------|--------------------------------|
| POST   | `/follow/{id}`              | Follow user                    |
| DELETE | `/unfollow/{id}`            | Unfollow user                  |
| GET    | `/users/{id}/followers`     | Lihat followers user           |
| GET    | `/users/{id}/following`     | Lihat siapa yang di-follow     |

---

## 📷 Stories

| Method | Endpoint                  | Description                     |
|--------|---------------------------|---------------------------------|
| POST   | `/stories`                | Upload story                    |
| GET    | `/stories/feed`           | Ambil semua story               |
| DELETE | `/stories/{id}`           | Hapus story                     |
| POST   | `/stories/{id}/view`      | Tambahkan view pada story       |

---

## 📨 Direct Messages

| Method | Endpoint                             | Description                       |
|--------|--------------------------------------|-----------------------------------|
| POST   | `/messages/send`                     | Kirim pesan                       |
| GET    | `/messages/conversation/{user_id}`   | Ambil percakapan dengan user      |
| PATCH  | `/messages/{id}/read`                | Tandai pesan sebagai dibaca       |

---

## 🔔 Notifications

| Method | Endpoint                            | Description                            |
|--------|-------------------------------------|----------------------------------------|
| GET    | `/notifications`                    | Lihat semua notifikasi                 |
| PATCH  | `/notifications/{id}/read`          | Tandai satu notifikasi sebagai read    |
| PATCH  | `/notifications/read/all`           | Tandai semua notifikasi sebagai read   |

---

## 🌐 Explore

| Method | Endpoint       | Description                          |
|--------|----------------|--------------------------------------|
| GET    | `/explore`     | Tampilkan postingan populer/acak     |

---

## 📤 Media Upload

| Method | Endpoint      | Description                    |
|--------|---------------|--------------------------------|
| POST   | `/upload`     | Upload gambar/video ke server  |

---

## ⚠️ Fallback

| Method | Endpoint             | Description                  |
|--------|----------------------|------------------------------|
| GET    | `/api/{404}`         | Endpoint tidak ditemukan     |

---

> Dokumen ini dibuat dengan 😈 oleh ciptaan tuhan  
