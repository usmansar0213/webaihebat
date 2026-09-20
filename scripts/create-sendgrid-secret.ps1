param(
  [Parameter(Mandatory = $true)] [string]$ProjectId,
  [string]$ApiKey,
  [string]$SecretValue,
  [string]$SecretName = "sendgrid-api-key"
)

$ErrorActionPreference = "Stop"

if (-not $SecretValue) {
  $SecretValue = $ApiKey
}

if (-not $SecretValue) {
  throw "Provide -SecretValue (or legacy -ApiKey)"
}

gcloud config set project $ProjectId | Out-Null

$secretExists = gcloud secrets list --project $ProjectId --filter="name:$SecretName" --format="value(name)"
if (-not $secretExists) {
  gcloud secrets create $SecretName --project $ProjectId --replication-policy="automatic"
}

$SecretValue | gcloud secrets versions add $SecretName --project $ProjectId --data-file=-
Write-Host "Secret updated: $SecretName" -ForegroundColor Green
