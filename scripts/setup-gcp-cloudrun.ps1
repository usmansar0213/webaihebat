param(
  [Parameter(Mandatory = $true)] [string]$ProjectId,
  [string]$Region = "us-central1",
  [string]$Repository = "webaihebat",
  [string]$ServiceName = "webaihebat"
)

$ErrorActionPreference = "Stop"

function Invoke-Gcloud {
  param([Parameter(Mandatory = $true)][string]$Command)
  Invoke-Expression $Command
  if ($LASTEXITCODE -ne 0) {
    throw "Command failed: $Command"
  }
}

Write-Host "[1/4] Set active project..." -ForegroundColor Cyan
Invoke-Gcloud "gcloud config set project $ProjectId"

Write-Host "[2/4] Enable required APIs..." -ForegroundColor Cyan
Invoke-Gcloud "gcloud services enable run.googleapis.com cloudbuild.googleapis.com artifactregistry.googleapis.com secretmanager.googleapis.com firestore.googleapis.com --project $ProjectId"

Write-Host "[3/4] Ensure Artifact Registry exists..." -ForegroundColor Cyan
$repoCheck = gcloud artifacts repositories list --project $ProjectId --location $Region --filter="name~$Repository" --format="value(name)"
if (-not $repoCheck) {
  Invoke-Gcloud "gcloud artifacts repositories create $Repository --repository-format=docker --location=$Region --project=$ProjectId"
  Write-Host "Repository created: $Repository" -ForegroundColor Green
} else {
  Write-Host "Repository already exists: $Repository" -ForegroundColor Yellow
}

Write-Host "[4/4] Ensure Firestore database exists (native mode)..." -ForegroundColor Cyan
try {
  $null = gcloud firestore databases describe --project $ProjectId --database="(default)"
  if ($LASTEXITCODE -eq 0) {
    Write-Host "Firestore database already exists." -ForegroundColor Yellow
  } else {
    Invoke-Gcloud "gcloud firestore databases create --project $ProjectId --database='(default)' --location=$Region --type=firestore-native"
    Write-Host "Firestore database created." -ForegroundColor Green
  }
} catch {
  Invoke-Gcloud "gcloud firestore databases create --project $ProjectId --database='(default)' --location=$Region --type=firestore-native"
  Write-Host "Firestore database created." -ForegroundColor Green
}

Write-Host "Setup completed." -ForegroundColor Green
Write-Host "Next: run scripts/deploy-gcp-cloudrun.ps1" -ForegroundColor Green
