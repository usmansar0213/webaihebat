@echo off
setlocal EnableExtensions

rem Deploy Web AIHebat to the Cloud Run service that serves aihebat.com.
rem Domain mappings for aihebat.com and www.aihebat.com point to us-central1.

set "PROJECT_ID=project-manajement"
set "REGION=us-central1"
set "SERVICE=webaihebat"
set "REPOSITORY=webaihebat"
set "DOMAIN=aihebat.com"
set "WWW_DOMAIN=www.aihebat.com"
for /f %%I in ('powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-Date -Format yyyyMMddHHmmss"') do set "IMAGE_TAG=deploy-%%I"

if not "%~1"=="" set "PROJECT_ID=%~1"

where gcloud.cmd >nul 2>nul
if errorlevel 1 (
  echo ERROR: gcloud.cmd tidak ditemukan di PATH.
  exit /b 1
)

echo [1/6] Validasi syntax JavaScript...
where node >nul 2>nul
if errorlevel 1 (
  echo WARNING: Node.js tidak ditemukan, skip node --check.
) else (
  node --check assets\js\main.js
  if errorlevel 1 exit /b 1
)

echo [2/6] Set project: %PROJECT_ID%
call gcloud.cmd config set project %PROJECT_ID%
if errorlevel 1 exit /b 1

echo [3/6] Cek domain mapping Cloud Run di %REGION%...
call gcloud.cmd beta run domain-mappings list --project %PROJECT_ID% --region %REGION% --filter="metadata.name=(%DOMAIN% %WWW_DOMAIN%)" --format="table(metadata.name, spec.routeName, location)"
if errorlevel 1 exit /b 1

echo [4/6] Build dan deploy ke Cloud Run %SERVICE% di %REGION%...
call gcloud.cmd builds submit --project %PROJECT_ID% --config cloudbuild.yaml --substitutions "_REGION=%REGION%,_SERVICE=%SERVICE%,_REPOSITORY=%REPOSITORY%,_IMAGE_TAG=%IMAGE_TAG%"
if errorlevel 1 exit /b 1

echo [5/6] Cek revision aktif...
call gcloud.cmd run services describe %SERVICE% --project %PROJECT_ID% --region %REGION% --format="table(status.latestReadyRevisionName,status.url)"
if errorlevel 1 exit /b 1

echo [6/6] Verifikasi domain live...
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $html=(Invoke-WebRequest -UseBasicParsing 'https://%DOMAIN%/').Content; if ($html -notmatch 'main\.js') { throw 'main.js tidak ditemukan di homepage domain' }; $line=($html -split \"`n\" | Select-String -Pattern 'main\.js' | Select-Object -First 1).Line.Trim(); Write-Host $line; $jsUrl='https://%DOMAIN%/assets/js/main.js?v=deploy-check'; $tmp=Join-Path $env:TEMP 'webaihebat-main-live.js'; (Invoke-WebRequest -UseBasicParsing $jsUrl).Content | Set-Content -LiteralPath $tmp -NoNewline; if (Get-Command node -ErrorAction SilentlyContinue) { node --check $tmp; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } }; Remove-Item -LiteralPath $tmp -ErrorAction SilentlyContinue"
if errorlevel 1 exit /b 1

echo.
echo Deploy selesai: https://%DOMAIN%/
endlocal
