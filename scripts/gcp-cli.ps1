param(
  [Parameter(Mandatory = $true)]
  [ValidateSet("setup", "secret", "deploy", "describe", "logs", "url", "check")]
  [string]$Action,

  [Parameter(Mandatory = $true)]
  [string]$ProjectId,

  [string]$Region = "us-central1",
  [string]$ServiceName = "webaihebat",
  [string]$Repository = "webaihebat",

  [string]$SecretName = "sendgrid-api-key",
  [string]$ApiKey,

  [string]$ContactReceivingEmail,
  [string]$NewsletterReceivingEmail,
  [string]$AssessmentReceivingEmail = "",
  [string]$SenderEmail,
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
  [string]$SmtpPasswordSecretName = "smtp-password"
)

$ErrorActionPreference = "Stop"

switch ($Action) {
  "setup" {
    powershell -ExecutionPolicy Bypass -File "$PSScriptRoot/setup-gcp-cloudrun.ps1" -ProjectId $ProjectId -Region $Region -Repository $Repository -ServiceName $ServiceName
    break
  }

  "secret" {
    if (-not $ApiKey) {
      throw "Parameter -ApiKey wajib diisi untuk Action=secret"
    }
    $effectiveSecretName = $SecretName
    if ($MailProvider.ToLowerInvariant() -eq "smtp") {
      $effectiveSecretName = $SmtpPasswordSecretName
    }
    powershell -ExecutionPolicy Bypass -File "$PSScriptRoot/create-sendgrid-secret.ps1" -ProjectId $ProjectId -SecretValue $ApiKey -SecretName $effectiveSecretName
    break
  }

  "deploy" {
    if (-not $ContactReceivingEmail -or -not $NewsletterReceivingEmail -or -not $SenderEmail) {
      throw "Untuk Action=deploy, isi -ContactReceivingEmail, -NewsletterReceivingEmail, dan -SenderEmail"
    }
    powershell -ExecutionPolicy Bypass -File "$PSScriptRoot/deploy-gcp-cloudrun.ps1" `
      -ProjectId $ProjectId `
      -Region $Region `
      -ServiceName $ServiceName `
      -Repository $Repository `
      -ContactReceivingEmail $ContactReceivingEmail `
      -NewsletterReceivingEmail $NewsletterReceivingEmail `
      -AssessmentReceivingEmail $AssessmentReceivingEmail `
      -SenderEmail $SenderEmail `
      -SenderName $SenderName `
      -MailProvider $MailProvider `
      -SmtpHost $SmtpHost `
      -SmtpPort $SmtpPort `
      -SmtpEncryption $SmtpEncryption `
      -SmtpUsername $SmtpUsername `
      -AllowedHosts $AllowedHosts `
      -EnableFirestoreLog $EnableFirestoreLog `
      -FirestoreCollectionContact $FirestoreCollectionContact `
      -FirestoreCollectionNewsletter $FirestoreCollectionNewsletter `
      -FirestoreCollectionAssessment $FirestoreCollectionAssessment `
      -FirestoreCollectionVisitor $FirestoreCollectionVisitor `
      -VisitorCounterStart $VisitorCounterStart `
      -AssessmentLogToken $AssessmentLogToken `
      -SendgridSecretName $SecretName `
      -SmtpPasswordSecretName $SmtpPasswordSecretName
    break
  }

  "describe" {
    gcloud config set project $ProjectId | Out-Null
    gcloud run services describe $ServiceName --region $Region --project $ProjectId
    break
  }

  "logs" {
    gcloud config set project $ProjectId | Out-Null
    gcloud beta run services logs tail $ServiceName --region $Region --project $ProjectId
    break
  }

  "url" {
    gcloud config set project $ProjectId | Out-Null
    $url = gcloud run services describe $ServiceName --region $Region --project $ProjectId --format="value(status.url)"
    Write-Host $url
    break
  }

  "check" {
    gcloud config set project $ProjectId | Out-Null
    $url = gcloud run services describe $ServiceName --region $Region --project $ProjectId --format="value(status.url)"
    if (-not $url) {
      throw "Service URL tidak ditemukan. Pastikan service sudah terdeploy."
    }

    Write-Host "Service URL: $url" -ForegroundColor Cyan

    $homeStatus = "ERR"
    try {
      $homeResp = Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 30
      $homeStatus = [string]$homeResp.StatusCode
    } catch {
      $homeStatus = "ERR"
    }
    Write-Host "Homepage status: $homeStatus" -ForegroundColor Yellow

    $contactStatus = "ERR"
    try {
      $contactResp = Invoke-WebRequest -Uri "$url/forms/contact.php" -Method Post -Body @{} -UseBasicParsing -TimeoutSec 30
      $contactStatus = [string]$contactResp.StatusCode
    } catch {
      if ($_.Exception.Response) {
        $contactStatus = [string][int]$_.Exception.Response.StatusCode
      }
    }
    if ($contactStatus -eq "200") {
      Write-Host "Contact endpoint status: $contactStatus (OK)" -ForegroundColor Green
    } elseif ($contactStatus -eq "400") {
      Write-Host "Contact endpoint status: $contactStatus (expected for empty payload validation)" -ForegroundColor Yellow
    } else {
      Write-Host "Contact endpoint status: $contactStatus" -ForegroundColor Red
    }

    $newsletterStatus = "ERR"
    try {
      $newsletterResp = Invoke-WebRequest -Uri "$url/forms/newsletter.php" -Method Post -Body @{} -UseBasicParsing -TimeoutSec 30
      $newsletterStatus = [string]$newsletterResp.StatusCode
    } catch {
      if ($_.Exception.Response) {
        $newsletterStatus = [string][int]$_.Exception.Response.StatusCode
      }
    }
    if ($newsletterStatus -eq "200") {
      Write-Host "Newsletter endpoint status: $newsletterStatus (OK)" -ForegroundColor Green
    } elseif ($newsletterStatus -eq "400") {
      Write-Host "Newsletter endpoint status: $newsletterStatus (expected for empty payload validation)" -ForegroundColor Yellow
    } else {
      Write-Host "Newsletter endpoint status: $newsletterStatus" -ForegroundColor Red
    }

    $provider = $MailProvider.ToLowerInvariant()
    $secretToCheck = if ($provider -eq "smtp") { $SmtpPasswordSecretName } else { $SecretName }

    $secretOk = $false
    try {
      gcloud secrets describe $secretToCheck --project $ProjectId | Out-Null
      if ($LASTEXITCODE -eq 0) {
        $secretOk = $true
      }
    } catch {
      $secretOk = $false
    }

    if ($secretOk) {
      Write-Host "Mail secret: OK ($secretToCheck)" -ForegroundColor Green
    } else {
      Write-Host "Mail secret: MISSING ($secretToCheck)" -ForegroundColor Red
    }

    if ($AssessmentLogToken -and $AssessmentLogToken -ne "disabled") {
      $assessmentLogStatus = "ERR"
      try {
        $assessmentLogResp = Invoke-WebRequest -Uri "$url/forms/assessment-log.php?token=$AssessmentLogToken&mode=organization&limit=1" -UseBasicParsing -TimeoutSec 30
        $assessmentLogStatus = [string]$assessmentLogResp.StatusCode
      } catch {
        if ($_.Exception.Response) {
          $assessmentLogStatus = [string][int]$_.Exception.Response.StatusCode
        }
      }

      if ($assessmentLogStatus -eq "200") {
        Write-Host "Assessment log endpoint: $assessmentLogStatus (OK)" -ForegroundColor Green
      } else {
        Write-Host "Assessment log endpoint: $assessmentLogStatus" -ForegroundColor Red
      }
    } else {
      Write-Host "Assessment log endpoint: SKIPPED (set -AssessmentLogToken to validate)" -ForegroundColor Yellow
    }

    Write-Host "Done check." -ForegroundColor Green
    break
  }
}
