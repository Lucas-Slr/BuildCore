$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
# Stop only the portable executables belonging to this workspace.
$allowedPaths = @("$projectRoot\.tools\mailpit\mailpit.exe", "$projectRoot\.tools\mercure\mercure.exe")
foreach ($process in @(Get-CimInstance Win32_Process | Where-Object { $_.ExecutablePath -in $allowedPaths })) {
    Stop-Process -Id $process.ProcessId
    Wait-Process -Id $process.ProcessId -Timeout 10 -ErrorAction SilentlyContinue
    Write-Output "Arrêt : $($process.Name), PID $($process.ProcessId)"
}
$pgCtl = "$projectRoot/.tools/postgresql/pgsql/bin/pg_ctl.exe"
$pgData = "$projectRoot/.cache/pgdata"
if ((Test-Path -LiteralPath $pgCtl) -and (Test-Path -LiteralPath "$pgData/PG_VERSION")) {
    & $pgCtl -D $pgData status | Out-Null
    if ($LASTEXITCODE -eq 0) {
        & $pgCtl -D $pgData -m fast stop
        if ($LASTEXITCODE -ne 0) { throw "Impossible d'arrêter le cluster PostgreSQL portable." }
    }
}
Write-Output 'Services portables arrêtés ; données conservées.'
