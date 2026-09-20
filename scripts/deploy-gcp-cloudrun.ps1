param(
  [Parameter(Mandatory = $true)] [string]$ProjectId,
  [string]$Region = "us-central1",
  [string]$ServiceName = "webaihebat",
  [string]$Repository = "webaihebat",
  [Parameter(Mandatory = $true)] [string]$ContactReceivingEmail,
  [Parameter(Mandatory = $true)] [string]$NewsletterReceivingEmail,
  [string]$AssessmentReceivingEmail = "",
  [Parameter(Mandatory = $true)] [string]$SenderEmail,
  [string]$SenderName = "AIHebat Web",
  [string]$MailProvider = "smtp",
  [string]$SmtpHost = "smtp.gmail.com",
  [string]$SmtpPort = "587",
  [string]$SmtpEncryption = "tls",
  [string]$SmtpUsername = "",
  [string]$AllowedHosts = "aihebat.com,www.aihebat.com",
  [string]$EnableFirestoreLog = "true",
  [string]$FirestoreCollectionContact = "contact_submissions",
  [string]$FirestoreCollectionNewsletter = "newsletter_subscriptions",
  [string]$FirestoreCollectionAssessment = "assessment_submissions",
  [string]$FirestoreCollectionVisitor = "visitor_counters",
  [string]$VisitorCounterStart = "999",
  [string]$AssessmentLogToken = "",
  [string]$SendgridSecretName = "sendgrid-api-key",
  [string]$SmtpPasswordSecretName = "smtp-password"
)

$ErrorActionPreference = "Stop"

function Invoke-Gcloud {
  param([Parameter(Mandatory = $true)][string]$Command)
  Invoke-Expression $Command
  if ($LASTEXITCODE -ne 0) {
    throw "Command failed: $Command"
  }
}

Write-Host "[1/6] Set active project..." -ForegroundColor Cyan
Invoke-Gcloud "gcloud config set project $ProjectId"

Write-Host "[2/6] Submit Cloud Build and deploy image via cloudbuild.yaml..." -ForegroundColor Cyan
$imageTag = Get-Date -Format "yyyyMMddHHmmss"
Invoke-Gcloud "gcloud builds submit --project $ProjectId --config cloudbuild.yaml --substitutions '_REGION=$Region,_SERVICE=$ServiceName,_REPOSITORY=$Repository,_IMAGE_TAG=$imageTag'"

Write-Host "[3/6] Bind service account role for Firestore access..." -ForegroundColor Cyan
$serviceAccount = gcloud run services describe $ServiceName --region $Region --project $ProjectId --format="value(spec.template.spec.serviceAccountName)"
if ($LASTEXITCODE -ne 0) {
  throw "Cloud Run service '$ServiceName' not found after deploy."
}
if (-not $serviceAccount) {
  $projectNumber = gcloud projects describe $ProjectId --format="value(projectNumber)"
  if ($LASTEXITCODE -ne 0) {
    throw "Unable to resolve project number for '$ProjectId'."
  }
  $serviceAccount = "$projectNumber-compute@developer.gserviceaccount.com"
}
Invoke-Gcloud "gcloud projects add-iam-policy-binding $ProjectId --member='serviceAccount:$serviceAccount' --role='roles/datastore.user'"

Write-Host "[4/6] Apply environment variables..." -ForegroundColor Cyan
$smtpUser = $SmtpUsername
if ($smtpUser -eq "") {
  $smtpUser = $SenderEmail
}
if ($AssessmentReceivingEmail -eq "") {
  $AssessmentReceivingEmail = $ContactReceivingEmail
}
if ($AssessmentLogToken -eq "") {
  # Keep endpoint disabled until token is explicitly configured.
  $AssessmentLogToken = "disabled"
}

$envFile = Join-Path $env:TEMP "webaihebat-cloudrun-env.yaml"
$envYaml = @(
  "GOOGLE_CLOUD_PROJECT: '$ProjectId'",
  "CONTACT_RECEIVING_EMAIL: '$ContactReceivingEmail'",
  "NEWSLETTER_RECEIVING_EMAIL: '$NewsletterReceivingEmail'",
  "ASSESSMENT_RECEIVING_EMAIL: '$AssessmentReceivingEmail'",
  "SENDER_EMAIL: '$SenderEmail'",
  "SENDER_NAME: '$SenderName'",
  "MAIL_PROVIDER: '$MailProvider'",
  "SMTP_HOST: '$SmtpHost'",
  "SMTP_PORT: '$SmtpPort'",
  "SMTP_ENCRYPTION: '$SmtpEncryption'",
  "SMTP_USERNAME: '$smtpUser'",
  "ALLOWED_HOSTS: '$AllowedHosts'",
  "ENABLE_FIRESTORE_LOG: '$EnableFirestoreLog'",
  "FIRESTORE_COLLECTION_CONTACT: '$FirestoreCollectionContact'",
  "FIRESTORE_COLLECTION_NEWSLETTER: '$FirestoreCollectionNewsletter'",
  "FIRESTORE_COLLECTION_ASSESSMENT: '$FirestoreCollectionAssessment'",
  "FIRESTORE_COLLECTION_VISITOR: '$FirestoreCollectionVisitor'",
  "VISITOR_COUNTER_START: '$VisitorCounterStart'",
  "ASSESSMENT_LOG_TOKEN: '$AssessmentLogToken'"
)
Set-Content -Path $envFile -Value $envYaml -Encoding UTF8
try {
  Invoke-Gcloud "gcloud run services update $ServiceName --region $Region --project $ProjectId --env-vars-file '$envFile'"
} finally {
  Remove-Item -Path $envFile -ErrorAction SilentlyContinue
}

Write-Host "[5/6] Attach email provider secret(s) if available..." -ForegroundColor Cyan
$mailProviderLower = $MailProvider.ToLowerInvariant()

$secretAvailable = $false
if ($mailProviderLower -eq "sendgrid") {
  try {
    $null = gcloud secrets describe $SendgridSecretName --project $ProjectId
    if ($LASTEXITCODE -eq 0) {
      $secretAvailable = $true
    }
  } catch {
    $secretAvailable = $false
  }
}

if ($mailProviderLower -eq "sendgrid" -and $secretAvailable) {
  Invoke-Gcloud "gcloud run services update $ServiceName --region $Region --project $ProjectId --set-secrets 'SENDGRID_API_KEY=${SendgridSecretName}:latest'"
  Write-Host "SendGrid secret attached." -ForegroundColor Green
} elseif ($mailProviderLower -eq "sendgrid") {
  Write-Host "Secret '$SendgridSecretName' not found. Create it first, then re-run this script." -ForegroundColor Yellow
}

$smtpSecretAvailable = $false
try {
  $null = gcloud secrets describe $SmtpPasswordSecretName --project $ProjectId
  if ($LASTEXITCODE -eq 0) {
    $smtpSecretAvailable = $true
  }
} catch {
  $smtpSecretAvailable = $false
}

if ($mailProviderLower -eq "smtp" -and $smtpSecretAvailable) {
  Invoke-Gcloud "gcloud run services update $ServiceName --region $Region --project $ProjectId --set-secrets 'SMTP_PASSWORD=${SmtpPasswordSecretName}:latest'"
  Write-Host "SMTP password secret attached." -ForegroundColor Green
} elseif ($mailProviderLower -eq "smtp") {
  Write-Host "Secret '$SmtpPasswordSecretName' not found. Create it first, then re-run this script." -ForegroundColor Yellow
}

Write-Host "[6/6] Show service URL..." -ForegroundColor Cyan
$serviceUrl = gcloud run services describe $ServiceName --region $Region --project $ProjectId --format="value(status.url)"
if ($LASTEXITCODE -ne 0) {
  throw "Failed to read service URL for '$ServiceName'."
}
Write-Host "Deployment completed: $serviceUrl" -ForegroundColor Green
