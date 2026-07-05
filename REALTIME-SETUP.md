# Realtime Portal SI (Reverb) tanpa nge-hang

Tujuan: notifikasi & chat realtime kembali jalan, TANPA membuat request HTTP (mis. upload
post, kirim pesan) menggantung. Caranya: broadcast **di-antrekan** (queue) lalu dikirim oleh
**worker**, dan **Reverb** berjalan sebagai proses tetap. Web request tidak lagi menunggu
koneksi ke Reverb.

Arsitektur:
- Backend (broadcaster) menembak Reverb **lokal**: `127.0.0.1:8080` (http) — cepat, tanpa Cloudflare.
- Klien (browser, Laravel Echo) konek via **`wss://ws.portalsi.com`** (443) — subdomain yang
  di-proxy ke `127.0.0.1:8080`.
- `queue:work` mengirim event ke Reverb secara asinkron, sehingga response HTTP instan.

---

## 1. Perubahan kode (SUDAH diterapkan di repo)

Lima event diubah dari `ShouldBroadcastNow` → `ShouldBroadcast` (di-antrekan):
`ChatListUpdated`, `GroupMessageUpdated`, `NewDirectMessage`, `NewGroupMessage`,
`NotificationCreated`. Deploy folder `api-portalsi` ke server API dulu (git pull / upload).

---

## 2. Konfigurasi `.env` BACKEND (`/home/api.portalsi.com/public_html`)

```env
BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=database

# Kredensial app (WAJIB terisi; KEY harus sama dengan frontend)
REVERB_APP_ID=812001
REVERB_APP_KEY=portalsi_realtime_key_ganti_ini
REVERB_APP_SECRET=ganti_dengan_rahasia_acak_panjang

# Broadcaster backend -> Reverb LOKAL (jangan lewat ws.portalsi.com)
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Bind server Reverb
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

Buat nilai acak untuk KEY/SECRET bila perlu:

```bash
php -r "echo 'KEY='.bin2hex(random_bytes(16)).PHP_EOL.'SECRET='.bin2hex(random_bytes(24)).PHP_EOL;"
```

## 3. Tabel antrean + bersihkan cache config

```bash
cd /home/api.portalsi.com/public_html
php artisan queue:table
php artisan migrate --force
php artisan config:clear
```

## 4. Jalankan Reverb + Queue Worker sebagai PM2 (auto-restart)

```bash
cd /home/api.portalsi.com/public_html
pm2 start "php artisan reverb:start --host=0.0.0.0 --port=8080" --name portalsi-reverb
pm2 start "php artisan queue:work --tries=3 --timeout=90 --sleep=1" --name portalsi-queue
pm2 save
```

Cek: `pm2 status` → `portalsi-reverb` & `portalsi-queue` online.
Uji Reverb lokal (sebelum ini connection refused):

```bash
curl -sS -o /dev/null -w "reverb lokal: %{http_code}\n" http://127.0.0.1:8080
```

## 5. Subdomain `ws.portalsi.com` → proxy WebSocket ke Reverb

Di CyberPanel:
1. **Websites → Create Website**: `ws.portalsi.com` (bila belum ada). Terbitkan **SSL** (Let's Encrypt).
2. **Manage → vHost Conf**, tambahkan proxy WebSocket ke Reverb lokal:

```
websocket / {
  address                 127.0.0.1:8080
}

context / {
  type                    proxy
  handler                 reverbws
  addDefaultCharset       off
}

extprocessor reverbws {
  type                    proxy
  address                 127.0.0.1:8080
  maxConns                200
  initTimeout             60
  retryTimeout            0
  respBuffer              0
}
```

3. Simpan, lalu: `sudo systemctl restart lsws`
4. Uji dari luar: `curl -I https://ws.portalsi.com` (200/101/426 = terjangkau; bukan 502/503).

> Catatan: broadcaster backend TIDAK lewat subdomain ini (ia pakai 127.0.0.1 langsung).
> Subdomain ini hanya untuk koneksi WebSocket dari browser.

## 6. Konfigurasi FRONTEND (`frontend-svelte/.env.production`)

```env
PUBLIC_REVERB_HOST=ws.portalsi.com
PUBLIC_REVERB_PORT=443
PUBLIC_REVERB_SCHEME=wss
PUBLIC_REVERB_APP_KEY=portalsi_realtime_key_ganti_ini   # SAMA persis dengan REVERB_APP_KEY backend
```

Jika mengubah nilai ini, redeploy frontend: `git pull --ff-only` (hook build+restart).

---

## 7. Verifikasi akhir

1. Upload post / kirim pesan → **response instan** (tidak lagi 524), karena broadcast
   ditangani worker, bukan request.
2. Buka app di dua browser/akun → notifikasi & chat muncul realtime.
3. Pantau: `pm2 logs portalsi-queue` (job broadcast diproses) dan
   `pm2 logs portalsi-reverb` (koneksi klien).

## Kenapa ini tidak nge-hang lagi

- `ShouldBroadcast` (bukan `...Now`) → event masuk antrean, request HTTP langsung selesai.
- `queue:work` mengirim ke Reverb di latar belakang; kalau Reverb sesaat lambat/mati, yang
  menunggu/mengulang adalah worker, BUKAN pengguna.
- Broadcaster menembak `127.0.0.1:8080` (bukan lewat Cloudflare), jadi cepat dan tak kena
  timeout 100 detik CF.

## Jika sewaktu-waktu ingin cepat mematikan realtime

`BROADCAST_CONNECTION=log` + `php artisan config:clear` → broadcast jadi no-op (aman, tak nge-hang).
