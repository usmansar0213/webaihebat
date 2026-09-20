# Migrasi Web AIHebat ke Google Cloud Run

Dokumen ini khusus untuk website statis + PHP form sederhana pada repo ini.

## 1. Arsitektur yang dipakai

- Hosting web: Cloud Run (container Apache + PHP)
- Image registry: Artifact Registry
- Build & deploy: Cloud Build
- Domain: tetap bisa pakai aihebat.com melalui domain mapping Cloud Run

Keuntungan pendekatan ini:
- Tidak lagi tergantung GitHub Pages static-only
- Bisa menambahkan API/backend kapan saja
- Cocok untuk pengembangan lanjut (form, database, autentikasi, dashboard)

## 2. Prasyarat

- Billing aktif di Google Cloud
- gcloud CLI sudah login
- Project Google Cloud sudah dibuat

## 3. Setup awal project

Ganti PROJECT_ID sesuai milik Anda.

```powershell
gcloud auth login
gcloud config set project PROJECT_ID

gcloud services enable run.googleapis.com cloudbuild.googleapis.com artifactregistry.googleapis.com
```

Alternatif otomatis dari repo ini:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup-gcp-cloudrun.ps1 -ProjectId PROJECT_ID
```

Buat repository image untuk container:

```powershell
gcloud artifacts repositories create webaihebat --repository-format=docker --location=us-central1
```

## 4. Deploy pertama

Dari folder root repo ini:

```powershell
.\deploy-cloud.bat
```

Alternatif otomatis (disarankan):

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\deploy-gcp-cloudrun.ps1 `
	-ProjectId PROJECT_ID `
	-ContactReceivingEmail admin@aihebat.com `
	-NewsletterReceivingEmail admin@aihebat.com `
	-SenderEmail no-reply@aihebat.com
```

Setelah sukses, ambil URL Cloud Run:

```powershell
gcloud run services describe webaihebat --region us-central1 --format="value(status.url)"
```

## 5. Domain custom aihebat.com

Map domain ke Cloud Run:

```powershell
gcloud run domain-mappings create --service webaihebat --domain aihebat.com --region us-central1
gcloud run domain-mappings create --service webaihebat --domain www.aihebat.com --region us-central1
```

Lalu ikuti DNS records yang diberikan command di atas di provider domain Anda.

## 6. Alur update berikutnya

Setiap ada perubahan web:

```powershell
.\deploy-cloud.bat
```

Cloud Run akan membuat revision baru otomatis.

## 7. Catatan penting untuk form contact/newsletter

Form [forms/contact.php](forms/contact.php) dan [forms/newsletter.php](forms/newsletter.php) default memakai:

- Gmail/Google Workspace SMTP untuk kirim email
- Firestore (opsional) untuk menyimpan lead/subscription

### Environment variable yang perlu di-set di Cloud Run

```text
CONTACT_RECEIVING_EMAIL=admin@aihebat.com
NEWSLETTER_RECEIVING_EMAIL=admin@aihebat.com
ASSESSMENT_RECEIVING_EMAIL=admin@aihebat.com
SENDER_EMAIL=no-reply@aihebat.com
SENDER_NAME=AIHebat Web
MAIL_PROVIDER=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME=admin@mamastoria.com
ALLOWED_HOSTS=aihebat.com,www.aihebat.com
ENABLE_FIRESTORE_LOG=true
FIRESTORE_COLLECTION_CONTACT=contact_submissions
FIRESTORE_COLLECTION_NEWSLETTER=newsletter_subscriptions
FIRESTORE_COLLECTION_ASSESSMENT=assessment_submissions
FIRESTORE_COLLECTION_VISITOR=visitor_counters
VISITOR_COUNTER_START=999
GOOGLE_CLOUD_PROJECT=PROJECT_ID
ASSESSMENT_LOG_TOKEN=GANTI_DENGAN_TOKEN_ACAK_PANJANG
```

`SMTP_PASSWORD` sebaiknya dipasang via Secret Manager, bukan plain env.

Contoh set secret SMTP ke Cloud Run:

```powershell
gcloud secrets create smtp-password --replication-policy="automatic"
echo "YOUR_GMAIL_APP_PASSWORD" | gcloud secrets versions add smtp-password --data-file=-

gcloud run services update webaihebat `
	--region us-central1 `
	--set-secrets SMTP_PASSWORD=smtp-password:latest
```

Atau gunakan helper script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\create-sendgrid-secret.ps1 -ProjectId PROJECT_ID -SecretValue "YOUR_GMAIL_APP_PASSWORD" -SecretName "smtp-password"
```

Jika trafik email sudah besar, Anda bisa migrasi ke SendGrid tanpa ubah form frontend:

```text
MAIL_PROVIDER=sendgrid
SENDGRID_API_KEY (via Secret Manager)
```

### IAM role untuk logging Firestore

Service account Cloud Run butuh role:

```text
roles/datastore.user
```

## 8. Struktur Data Assessment (submit)

Endpoint `forms/assessment.php` menerima field tambahan:

```text
assessment_mode=organization|personal
training_goal=<tujuan pelatihan peserta>
```

Keduanya disimpan ke Firestore sebagai `assessmentMode` dan `trainingGoal` untuk memudahkan pemisahan laporan assessment Organisasi vs Pribadi.

Endpoint monitoring ringan tersedia di `forms/assessment-log.php`.

Contoh pakai:

```text
/forms/assessment-log.php?token=ASSESSMENT_LOG_TOKEN&mode=organization&limit=50
/forms/assessment-log.php?token=ASSESSMENT_LOG_TOKEN&mode=personal&limit=50
```

Catatan keamanan: selalu gunakan token acak panjang untuk `ASSESSMENT_LOG_TOKEN` dan jangan dipublikasikan.

## 9. Menonaktifkan deploy GitHub Pages

Repo Anda punya workflow GitHub Pages di:
- [.github/workflows/static.yml](.github/workflows/static.yml)
- [.github/workflows/deploy.yml](.github/workflows/deploy.yml)

Workflow di atas sudah diubah ke mode manual-only agar tidak auto deploy dari push.

## 10. Verifikasi setelah pindah

Checklist:
- Home page terbuka dari URL Cloud Run
- Semua asset CSS/JS/images berhasil load (status 200)
- Halaman artikel dan navigasi berjalan
- Counter visitor berjalan dari `forms/visitor-counter.php` dan count tersimpan di Firestore
- Form contact/newsletter ditest dan sudah ditangani backend email yang valid

## 11. Kelola penuh via CLI dari VS Code

Semua aksi bisa lewat satu script:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\gcp-cli.ps1 -Action describe -ProjectId PROJECT_ID
```

Action yang tersedia:

- `setup`
- `secret`
- `deploy`
- `describe`
- `logs`
- `url`

Contoh:

```powershell
# Setup resource dasar
powershell -ExecutionPolicy Bypass -File .\scripts\gcp-cli.ps1 -Action setup -ProjectId PROJECT_ID

# Simpan/update SMTP app password ke Secret Manager
powershell -ExecutionPolicy Bypass -File .\scripts\gcp-cli.ps1 -Action secret -ProjectId PROJECT_ID -MailProvider smtp -SmtpPasswordSecretName smtp-password -ApiKey "GMAIL_APP_PASSWORD_ANDA"

# Deploy lengkap
powershell -ExecutionPolicy Bypass -File .\scripts\gcp-cli.ps1 -Action deploy -ProjectId PROJECT_ID -MailProvider smtp -SmtpHost smtp.gmail.com -SmtpPort 587 -SmtpEncryption tls -SmtpUsername admin@mamastoria.com -ContactReceivingEmail admin@mamastoria.com -NewsletterReceivingEmail admin@mamastoria.com -SenderEmail admin@mamastoria.com

# Tailing logs Cloud Run
powershell -ExecutionPolicy Bypass -File .\scripts\gcp-cli.ps1 -Action logs -ProjectId PROJECT_ID

# Health check cepat (homepage + endpoint form + status secret)
powershell -ExecutionPolicy Bypass -File .\scripts\gcp-cli.ps1 -Action check -ProjectId PROJECT_ID
```

Task VS Code juga sudah tersedia di [ .vscode/tasks.json ]:
- `GCP: CLI Action Hub`
