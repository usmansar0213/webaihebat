# website-aihebat

Website company profile dan edukasi AI untuk AIHebat.

## Stack saat ini

- Frontend: HTML, CSS, JavaScript
- Form endpoint: PHP sederhana di folder [forms](forms)
- Asset: folder [assets](assets)

## Deploy target yang disarankan

Project ini sudah disiapkan untuk deploy ke Google Cloud Run menggunakan:

- [Dockerfile](Dockerfile)
- [cloudbuild.yaml](cloudbuild.yaml)
- [DEPLOYMENT_GCP_WEBAIHEBAT.md](DEPLOYMENT_GCP_WEBAIHEBAT.md)

## Quick start deploy ke Google Cloud

1. Aktifkan API dan set project:

```powershell
gcloud auth login
gcloud config set project PROJECT_ID
gcloud services enable run.googleapis.com cloudbuild.googleapis.com artifactregistry.googleapis.com
```

2. Buat Artifact Registry (sekali saja):

```powershell
gcloud artifacts repositories create webaihebat --repository-format=docker --location=us-central1
```

3. Deploy:

```powershell
.\deploy-cloud.bat
```

Panduan lengkap termasuk domain custom ada di [DEPLOYMENT_GCP_WEBAIHEBAT.md](DEPLOYMENT_GCP_WEBAIHEBAT.md).

## Catatan penting

Form contact/newsletter sekarang default berbasis SMTP (Gmail/Google Workspace) + Firestore logging (opsional), jadi tidak lagi bergantung library PHP Email Form yang hilang.

Environment minimum di Cloud Run:

```text
CONTACT_RECEIVING_EMAIL
NEWSLETTER_RECEIVING_EMAIL
ASSESSMENT_RECEIVING_EMAIL
SENDER_EMAIL
SENDER_NAME
MAIL_PROVIDER=smtp
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
SMTP_USERNAME
SMTP_PASSWORD (via Secret Manager)
ENABLE_FIRESTORE_LOG=true
GOOGLE_CLOUD_PROJECT
FIRESTORE_COLLECTION_ASSESSMENT=assessment_submissions
FIRESTORE_COLLECTION_VISITOR=visitor_counters
VISITOR_COUNTER_START=999
```

Untuk trafik kecil-menengah, Gmail/Workspace SMTP sudah cukup. Jika trafik tumbuh besar, Anda bisa ganti `MAIL_PROVIDER=sendgrid`.

Visitor counter memakai endpoint internal `forms/visitor-counter.php` dan menyimpan angka berkelanjutan di Firestore. Nilai awal default adalah `999`.

Assessment submission menyimpan pembeda tipe lewat field `assessmentMode` (nilai: `organization` atau `personal`) serta field `trainingGoal` untuk tujuan pelatihan peserta.

Untuk melihat log assessment per mode secara cepat, tersedia endpoint `forms/assessment-log.php` dengan query:

```text
?token=ASSESSMENT_LOG_TOKEN&mode=organization|personal&limit=50
```

Set environment variable `ASSESSMENT_LOG_TOKEN` terlebih dulu di Cloud Run.

Detail setup ada di [DEPLOYMENT_GCP_WEBAIHEBAT.md](DEPLOYMENT_GCP_WEBAIHEBAT.md).
