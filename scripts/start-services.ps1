$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
New-Item -ItemType Directory -Force "$projectRoot/.cache" | Out-Null
foreach ($binary in @('.tools/mailpit/mailpit.exe', '.tools/mercure/mercure.exe', '.tools/postgresql/pgsql/bin/pg_ctl.exe')) {
    if (!(Test-Path -LiteralPath "$projectRoot/$binary")) { throw "Binaire portable absent : $binary. Voir docs/integration.md ou utiliser Docker Compose." }
}
if (!(Get-NetTCPConnection -LocalPort 8025 -State Listen -ErrorAction SilentlyContinue)) {
    $mail = Start-Process -FilePath "$projectRoot/.tools/mailpit/mailpit.exe" -ArgumentList '--listen','127.0.0.1:8025','--smtp','127.0.0.1:1025','--database',"$projectRoot/.cache/mailpit.db",'--disable-version-check' -WindowStyle Hidden -PassThru -RedirectStandardOutput "$projectRoot/.cache/mailpit.stdout.log" -RedirectStandardError "$projectRoot/.cache/mailpit.stderr.log"
    Write-Output "Mailpit PID $($mail.Id)"
}
if (!(Get-NetTCPConnection -LocalPort 3000 -State Listen -ErrorAction SilentlyContinue)) {
    $hub = Start-Process -FilePath "$projectRoot/.tools/mercure/mercure.exe" -ArgumentList 'run','--config',"$projectRoot/infrastructure/mercure.local.Caddyfile",'--adapter','caddyfile' -WorkingDirectory "$projectRoot/.cache" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$projectRoot/.cache/mercure.stdout.log" -RedirectStandardError "$projectRoot/.cache/mercure.stderr.log"
    Write-Output "Mercure PID $($hub.Id)"
}
if (!(Get-NetTCPConnection -LocalPort 55432 -State Listen -ErrorAction SilentlyContinue)) {
    if (!(Test-Path -LiteralPath "$projectRoot/.cache/pgdata/PG_VERSION")) { throw 'Cluster PostgreSQL portable absent. Voir docs/integration.md.' }
    & "$projectRoot/.tools/postgresql/pgsql/bin/pg_ctl.exe" -D "$projectRoot/.cache/pgdata" -l "$projectRoot/.cache/postgres.log" -o '-h 127.0.0.1 -p 55432' start
    if ($LASTEXITCODE -ne 0) { throw 'Démarrage PostgreSQL impossible.' }
}
Write-Output 'Services locaux : PostgreSQL 55432, Mailpit 8025/1025, Mercure 3000. Aucune installation de service Windows.'
