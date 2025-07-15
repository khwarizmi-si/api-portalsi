# 📘 API Documentation - Portal SI (PSI)

RESTful API untuk aplikasi sosial media **Portal SI** 😵‍💫🧠🌀👁️👄👁️😱🙃😈👹  
**Base URL:** `http://localhost:8000/api/(endpoint)`

---

**WARNING!** `Sebelum lanjut yuk kita nyanyi Mars PSI terlebih dahulu!`

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
_(Diulang dari awal)_

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

## 📮 P
