# Panduan Deployment Cloud GRI Evaluator

Dokumen ini menjelaskan cara men-deploy aplikasi GRI Evaluator ke Google Cloud Run. Panduan ini fokus pada deployment produksi aplikasi web Flask yang ada di `webapp/app.py`, memakai Vertex AI untuk evaluasi Gemini, Google Cloud Storage untuk persistensi file, dan Cloud Run sebagai runtime container.

Catatan operasional production yang lebih ringkas ada di [CLOUD_RUN_OPERATIONS.md](CLOUD_RUN_OPERATIONS.md). Gunakan dokumen ini untuk langkah deployment end-to-end, dan gunakan dokumen operasional tersebut untuk troubleshooting harian atau riwayat insiden.

## 1. Ringkasan Arsitektur

Komponen utama deployment cloud:

| Komponen | Fungsi |
|---|---|
| Cloud Run | Menjalankan aplikasi web Flask via Gunicorn. |
| Cloud Build | Membuat container dari source repo saat `gcloud run deploy --source .`. |
| Artifact Registry | Menyimpan image hasil build Cloud Run source deploy. |
| Vertex AI | Menjalankan model Gemini dan model embedding multilingual. |
| Cloud Storage | Menyimpan PDF upload, laporan hasil, cache ekstraksi, dan database akun. |
| SQLite | Database akun aplikasi, disimpan di `webapp/users.db` lalu disinkronkan ke bucket. |
| Chroma vector cache | Cache pencarian dokumen di `/tmp/vector_cache`; hanya metadata/pages JSON yang disinkronkan ke bucket. |

Runtime container memakai konfigurasi dari [Dockerfile](Dockerfile):

```text
python:3.11-slim
gunicorn --bind :${PORT} --workers 1 --threads 8 --timeout 600 webapp.app:app
```

Cloud Run wajib dijalankan dengan 1 instance maksimum karena sebagian state proses aktif masih berada di memori proses dan SQLite disinkronkan sebagai file ke bucket.

## 2. Target Production Saat Ini

Konfigurasi production yang dipakai repo ini:

| Item | Nilai |
|---|---|
| Google Cloud project | `sustainability-report-500310` |
| Cloud Run service | `gri-evaluator` |
| Region | `us-central1` |
| Runtime service account | `1045428555101-compute@developer.gserviceaccount.com` |
| Bucket | `gs://sustainability-report-500310-gri-evaluator` |
| Storage prefix | `gri-evaluator` |
| URL production | `https://gri-evaluator-1045428555101.us-central1.run.app` |

Jangan mengganti project, service, region, atau bucket tanpa sengaja. Jika ingin membuat environment baru seperti staging, gunakan nama service dan prefix bucket berbeda agar data production tidak tercampur.

## 3. Prasyarat Lokal

Siapkan komputer deployment dengan:

1. Python 3.11 atau versi kompatibel dengan dependensi repo.
2. Google Cloud CLI (`gcloud`).
3. Akses IAM ke project target untuk deploy Cloud Run, Cloud Build, Artifact Registry, Cloud Storage, dan Vertex AI.
4. Node.js opsional. Jika tersedia, script deploy akan menjalankan syntax check untuk `webapp/static/app.js`.

Login ke Google Cloud:

```powershell
gcloud auth login
gcloud config set project sustainability-report-500310
```

Jika perlu menjalankan aplikasi lokal yang memanggil Vertex AI, siapkan Application Default Credentials:

```powershell
gcloud auth application-default login
```

## 4. API Google Cloud Yang Perlu Aktif

Aktifkan service berikut di project target:

```powershell
gcloud services enable run.googleapis.com `
  cloudbuild.googleapis.com `
  artifactregistry.googleapis.com `
  aiplatform.googleapis.com `
  storage.googleapis.com `
  --project sustainability-report-500310
```

## 5. Bucket Dan Struktur Persistensi

Aplikasi memakai bucket berikut:

```text
gs://sustainability-report-500310-gri-evaluator
```

Dengan prefix aplikasi:

```text
gri-evaluator/
```

Struktur data yang disimpan:

| Prefix object | Isi |
|---|---|
| `gri-evaluator/uploads/` | PDF yang diupload user atau dimasukkan manual lewat GCS. |
| `gri-evaluator/reports/` | Laporan Word/Excel hasil evaluasi. |
| `gri-evaluator/auth/users.db` | Database akun SQLite. |
| `gri-evaluator/vector_cache/*.pages.json` | Cache teks halaman hasil ekstraksi PDF. |
| `gri-evaluator/vector_cache/*.meta.json` | Metadata cache dokumen. |

File internal Chroma seperti `chroma.sqlite3` dan folder UUID collection tidak disinkronkan ke bucket. Aplikasi hanya menyimpan cache JSON yang stabil, lalu membangun ulang collection Chroma lokal di `/tmp/vector_cache` jika diperlukan.

Jika bucket belum ada:

```powershell
gcloud storage buckets create gs://sustainability-report-500310-gri-evaluator `
  --project sustainability-report-500310 `
  --location us-central1 `
  --uniform-bucket-level-access
```

## 6. IAM Service Account

Cloud Run berjalan dengan service account:

```text
1045428555101-compute@developer.gserviceaccount.com
```

Minimal service account runtime perlu akses:

| Role | Tujuan |
|---|---|
| `roles/aiplatform.user` | Memanggil Vertex AI Gemini dan embedding. |
| `roles/storage.objectAdmin` pada bucket aplikasi | Membaca/menulis PDF, laporan, cache, dan `users.db`. |

Contoh pemberian role project-level untuk Vertex AI:

```powershell
gcloud projects add-iam-policy-binding sustainability-report-500310 `
  --member="serviceAccount:1045428555101-compute@developer.gserviceaccount.com" `
  --role="roles/aiplatform.user"
```

Contoh pemberian role bucket-level untuk Storage:

```powershell
gcloud storage buckets add-iam-policy-binding gs://sustainability-report-500310-gri-evaluator `
  --member="serviceAccount:1045428555101-compute@developer.gserviceaccount.com" `
  --role="roles/storage.objectAdmin"
```

User yang menjalankan deploy juga perlu izin untuk Cloud Run deploy, Cloud Build, Artifact Registry, dan iam service account user bila memakai service account runtime eksplisit.

## 7. Environment Variable Wajib

Revision Cloud Run harus memakai env berikut:

```text
GOOGLE_CLOUD_PROJECT=sustainability-report-500310
GOOGLE_CLOUD_LOCATION=us-central1
VECTOR_PERSIST_DIR=/tmp/vector_cache
STORAGE_BUCKET=sustainability-report-500310-gri-evaluator
STORAGE_PREFIX=gri-evaluator
EMBEDDING_BACKEND=vertex
VERTEX_EMBEDDING_MODEL=text-multilingual-embedding-002
VERTEX_EMBEDDING_BATCH_SIZE=8
```

Disarankan juga menambahkan secret key Flask yang stabil lewat environment variable atau Secret Manager:

```text
FLASK_SECRET_KEY=<nilai-random-panjang>
```

Tanpa `FLASK_SECRET_KEY`, aplikasi akan membuat file `.flask_secret_key` di filesystem container. Di Cloud Run filesystem bersifat ephemeral, sehingga session login bisa berubah setelah instance baru dibuat.

## 8. File Yang Tidak Ikut Deploy

Source deploy memakai [.gcloudignore](.gcloudignore). Beberapa data lokal sengaja tidak dikirim ke build Cloud Run:

| Path | Alasan |
|---|---|
| `.vector_cache/` | Cache lokal besar dan bisa dibangun ulang. |
| `webapp/uploads/` | Data upload runtime disimpan di bucket. |
| `docs/` | PDF contoh/laporan lokal besar, bukan bagian image aplikasi. |
| `.env`, `*.log`, `*.bak` | File lokal/dev. |
| `README.md`, `GEMINI_VERTEX_SETUP.md` | Dokumentasi tidak diperlukan runtime container. |

Konsekuensi penting: PDF yang ada di `docs/` lokal tidak otomatis tersedia di production. Masukkan PDF production ke bucket `gri-evaluator/uploads/` atau upload lewat UI jika ukurannya masih diterima Cloud Run.

## 9. Validasi Sebelum Deploy

Dari root repo, jalankan validasi Python:

```powershell
python -m py_compile webapp\app.py webapp\auth.py vector_store.py storage_backend.py
```

Jika Node.js tersedia, cek JavaScript:

```powershell
node --check webapp\static\app.js
```

Script [deploy_cloud.bat](deploy_cloud.bat) menjalankan dua validasi ini otomatis sebelum deploy.

## 10. Deploy Cepat Dari Windows

Cara utama dari repo ini:

```bat
deploy_cloud.bat
```

Script tersebut akan:

1. Masuk ke root repo.
2. Compile-check file Python penting.
3. Syntax-check `webapp/static/app.js` jika Node.js tersedia.
4. Menampilkan akun `gcloud` aktif.
5. Menjalankan `gcloud run deploy` ke service production.
6. Memasang environment variable production.
7. Mengaktifkan `--no-cpu-throttling`.
8. Membatasi Cloud Run ke `--max-instances=1`.
9. Mengecek revision terbaru.
10. Mengecek HTTP status URL production.

Jika gagal karena autentikasi, jalankan:

```powershell
gcloud auth login
gcloud config set project sustainability-report-500310
```

Lalu ulangi `deploy_cloud.bat`.

## 11. Deploy Manual Dengan gcloud

Jika tidak memakai script, jalankan dari root repo:

```powershell
python -m py_compile webapp\app.py webapp\auth.py vector_store.py storage_backend.py

gcloud run deploy gri-evaluator `
  --project sustainability-report-500310 `
  --source . `
  --platform managed `
  --region us-central1 `
  --service-account=1045428555101-compute@developer.gserviceaccount.com `
  --update-env-vars="GOOGLE_CLOUD_PROJECT=sustainability-report-500310,GOOGLE_CLOUD_LOCATION=us-central1,VECTOR_PERSIST_DIR=/tmp/vector_cache,STORAGE_BUCKET=sustainability-report-500310-gri-evaluator,STORAGE_PREFIX=gri-evaluator,EMBEDDING_BACKEND=vertex,VERTEX_EMBEDDING_MODEL=text-multilingual-embedding-002,VERTEX_EMBEDDING_BATCH_SIZE=8" `
  --no-cpu-throttling `
  --max-instances=1 `
  --quiet
```

Kenapa opsi ini penting:

| Opsi | Alasan |
|---|---|
| `--source .` | Cloud Build membuat image dari source repo dan Dockerfile. |
| `--service-account` | Runtime memakai identitas yang punya akses Vertex AI dan bucket. |
| `--update-env-vars` | Mengaktifkan Vertex embedding dan persistensi bucket. |
| `--no-cpu-throttling` | Background thread preprocessing tetap mendapat CPU setelah response awal selesai. |
| `--max-instances=1` | State progress dan SQLite file sync tetap konsisten. |

## 12. Verifikasi Setelah Deploy

Cek revision, traffic, CPU throttling, dan max scale:

```powershell
gcloud run services describe gri-evaluator `
  --project sustainability-report-500310 `
  --region us-central1 `
  --format="value(status.latestReadyRevisionName,status.traffic[0].percent,status.traffic[0].revisionName,spec.template.metadata.annotations['run.googleapis.com/cpu-throttling'],spec.template.metadata.annotations['autoscaling.knative.dev/maxScale'])"
```

Output yang diharapkan:

```text
<revision-terbaru> 100 <revision-terbaru> false 1
```

Cek halaman utama:

```powershell
Invoke-WebRequest -Uri "https://gri-evaluator-1045428555101.us-central1.run.app" `
  -UseBasicParsing `
  -TimeoutSec 45 | Select-Object -ExpandProperty StatusCode
```

Status yang diharapkan:

```text
200
```

## 13. Upload PDF Production

Untuk PDF kecil/sedang, user bisa upload lewat UI aplikasi.

Untuk PDF besar, Cloud Run dapat menolak request dengan `413 Request Entity Too Large`. Gunakan upload langsung ke Cloud Storage:

```powershell
gcloud storage cp "docs\Nama Laporan.pdf" `
  "gs://sustainability-report-500310-gri-evaluator/gri-evaluator/uploads/Nama Laporan.pdf"
```

Setelah file masuk bucket, buka aplikasi atau cek API dokumen:

```powershell
Invoke-RestMethod -Uri "https://gri-evaluator-1045428555101.us-central1.run.app/api/documents" `
  -TimeoutSec 60 | ConvertTo-Json -Depth 5
```

Endpoint ini membutuhkan session login browser. Jika dipanggil tanpa login, response dapat berupa 401 atau redirect login.

## 14. Validasi Preprocessing Dokumen

Setelah login di browser, pilih dokumen dari UI dan tunggu status preprocessing selesai.

Jika ingin menguji via API dengan session/cookie yang valid, alurnya:

```powershell
Invoke-RestMethod -Method Post `
  -Uri "https://gri-evaluator-1045428555101.us-central1.run.app/api/select_document" `
  -ContentType "application/json" `
  -Body '{"filename":"Nama Laporan.pdf"}' `
  -TimeoutSec 60 | ConvertTo-Json -Depth 5

Invoke-RestMethod -Uri "https://gri-evaluator-1045428555101.us-central1.run.app/api/preprocess_status" `
  -TimeoutSec 30 | ConvertTo-Json -Depth 5
```

Status sukses biasanya berisi:

```json
{
  "doc_ready": true,
  "done": true,
  "error": null,
  "stage": "done",
  "info": {
    "source_name": "Nama Laporan.pdf",
    "collection_name": "doc_<hash>_vtx",
    "num_pages": 281,
    "num_chunks": 910,
    "looks_scanned": false
  }
}
```

## 15. Melihat Log Dan Error

Ambil nama revision terbaru:

```powershell
$revision = gcloud run services describe gri-evaluator `
  --project sustainability-report-500310 `
  --region us-central1 `
  --format="value(status.latestReadyRevisionName)"
```

Cek error revision tersebut:

```powershell
gcloud logging read "resource.type=\"cloud_run_revision\" AND resource.labels.service_name=\"gri-evaluator\" AND resource.labels.revision_name=\"$revision\" AND severity>=ERROR" `
  --project sustainability-report-500310 `
  --limit 50 `
  --format="value(timestamp,severity,textPayload)"
```

Cek log umum service:

```powershell
gcloud logging read "resource.type=\"cloud_run_revision\" AND resource.labels.service_name=\"gri-evaluator\"" `
  --project sustainability-report-500310 `
  --limit 100 `
  --format="value(timestamp,severity,textPayload)"
```

## 16. Rollback Revision

Lihat daftar revision:

```powershell
gcloud run revisions list `
  --service gri-evaluator `
  --project sustainability-report-500310 `
  --region us-central1
```

Alihkan 100% traffic ke revision lama:

```powershell
gcloud run services update-traffic gri-evaluator `
  --project sustainability-report-500310 `
  --region us-central1 `
  --to-revisions <REVISION_LAMA>=100
```

Setelah rollback, verifikasi lagi URL production dan log error.

## 17. Checklist Deployment

Sebelum deploy:

- Pastikan branch/kode yang akan dideploy sudah benar.
- Pastikan `gcloud config get-value project` mengarah ke `sustainability-report-500310`.
- Pastikan akun deploy punya izin Cloud Run, Cloud Build, Artifact Registry, Storage, dan service account user.
- Jalankan `python -m py_compile webapp\app.py webapp\auth.py vector_store.py storage_backend.py`.
- Jalankan `node --check webapp\static\app.js` jika Node.js tersedia.

Saat deploy:

- Jalankan `deploy_cloud.bat` dari root repo.
- Pastikan deploy memakai service `gri-evaluator`, region `us-central1`, project `sustainability-report-500310`.
- Pastikan env Vertex dan Storage terpasang.
- Pastikan `--no-cpu-throttling` aktif.
- Pastikan `--max-instances=1` aktif.

Setelah deploy:

- Pastikan latest ready revision menerima 100% traffic.
- Pastikan CPU throttling bernilai `false`.
- Pastikan max scale bernilai `1`.
- Pastikan URL production mengembalikan HTTP 200.
- Login ke aplikasi dan cek daftar dokumen.
- Pilih satu dokumen kecil untuk smoke test preprocessing.

## 18. Troubleshooting Umum

| Masalah | Penyebab umum | Tindakan |
|---|---|---|
| `413 Request Entity Too Large` saat upload PDF | File terlalu besar untuk request Cloud Run. | Upload langsung ke `gs://.../gri-evaluator/uploads/`. |
| Hugging Face download atau rate limit muncul di log | Revision tidak memakai Vertex embedding. | Pastikan `EMBEDDING_BACKEND=vertex`. |
| Error Vertex token limit | Batch embedding terlalu besar. | Pastikan `VERTEX_EMBEDDING_BATCH_SIZE=8`. |
| Progress preprocessing berhenti | CPU throttling aktif atau instance berganti. | Jalankan update service dengan `--no-cpu-throttling --max-instances=1`. |
| Dokumen di `docs/` lokal tidak muncul di production | Folder `docs/` dikecualikan oleh `.gcloudignore`. | Upload PDF ke bucket `gri-evaluator/uploads/`. |
| Akun login hilang setelah deploy/restart | `users.db` tidak tersinkron ke bucket atau service account tidak punya akses Storage. | Cek `STORAGE_BUCKET`, `STORAGE_PREFIX`, dan IAM bucket. |
| Error Chroma collection hilang/duplikat | Cache Chroma internal lama/korup atau preprocessing paralel. | Jangan sync file internal Chroma; gunakan cache JSON dan satu instance. |
| Aplikasi tidak bisa memanggil Gemini | Vertex AI API belum aktif atau IAM kurang. | Aktifkan `aiplatform.googleapis.com` dan beri `roles/aiplatform.user`. |

## 19. Perintah Maintenance Berguna

Refresh konfigurasi single-instance dan CPU always allocated:

```powershell
gcloud run services update gri-evaluator `
  --project sustainability-report-500310 `
  --region us-central1 `
  --no-cpu-throttling `
  --max-instances=1 `
  --quiet
```

Lihat environment variable revision terbaru:

```powershell
gcloud run services describe gri-evaluator `
  --project sustainability-report-500310 `
  --region us-central1 `
  --format="yaml(spec.template.spec.containers[0].env)"
```

Lihat isi bucket aplikasi:

```powershell
gcloud storage ls --recursive gs://sustainability-report-500310-gri-evaluator/gri-evaluator/
```

Download database akun untuk backup manual:

```powershell
gcloud storage cp `
  gs://sustainability-report-500310-gri-evaluator/gri-evaluator/auth/users.db `
  .\users.db.backup
```

## 20. Catatan Keamanan

- Jangan menyimpan secret di repo atau di chat.
- Gunakan `FLASK_SECRET_KEY` yang stabil untuk production.
- Batasi akses bucket hanya ke operator dan service account runtime.
- Rotasi password akun admin setelah deployment pertama.
- Jika aplikasi dibuka ke publik, pertimbangkan proteksi tambahan seperti Cloud Armor, Identity-Aware Proxy, atau pembatasan domain login sesuai kebutuhan organisasi.
- Jangan menaikkan `--max-instances` sebelum state aplikasi, SQLite auth DB, dan proses background dipindahkan ke storage/queue/database yang aman untuk multi-instance.
