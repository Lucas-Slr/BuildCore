$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
Set-Location $projectRoot
New-Item -ItemType Directory -Force -Path "$projectRoot/.cache" | Out-Null
$portablePhp = "$projectRoot/.tools/php84/php.exe"
$phpCommand = if (Test-Path -LiteralPath $portablePhp) { $portablePhp } else { (Get-Command php).Source }
$nodeCommand = (Get-Command node).Source
$phpVersion = & $phpCommand -r 'echo PHP_VERSION_ID;'
if ($LASTEXITCODE -ne 0 -or [int]$phpVersion -lt 80400) { throw 'PHP 8.4 minimum requis. Vérifiez php --version et le PATH.' }
$nodeVersion = & $nodeCommand --version
if ($LASTEXITCODE -ne 0 -or [int]$nodeVersion.TrimStart('v').Split('.')[0] -lt 24) { throw 'Node.js 24 minimum requis.' }
foreach ($required in @('apps/api/vendor/autoload.php', 'apps/storefront/node_modules/@angular/cli/bin/ng.js')) {
    if (!(Test-Path -LiteralPath "$projectRoot/$required")) { throw "Dépendances absentes : $required. Exécutez l’installation du README avant le lancement." }
}
if (Get-NetTCPConnection -LocalPort 8000,4200 -State Listen -ErrorAction SilentlyContinue) {
    throw 'Le port 8000 ou 4200 est occupé. Réutilisez les serveurs existants ou arrêtez-les avant de relancer.'
}
$apiProcess = Start-Process -FilePath $phpCommand -ArgumentList '-S','127.0.0.1:8000','-t',('"' + "$projectRoot/apps/api/public" + '"'),('"' + "$projectRoot/apps/api/public/index.php" + '"') -WorkingDirectory $projectRoot -WindowStyle Hidden -PassThru -RedirectStandardOutput "$projectRoot/.cache/api.stdout.log" -RedirectStandardError "$projectRoot/.cache/api.stderr.log"
try {
    $webProcess = Start-Process -FilePath $nodeCommand -ArgumentList ('"' + "$projectRoot/apps/storefront/node_modules/@angular/cli/bin/ng.js" + '"'),'serve','--proxy-config','proxy.conf.json','--host','127.0.0.1' -WorkingDirectory "$projectRoot/apps/storefront" -WindowStyle Hidden -PassThru -RedirectStandardOutput "$projectRoot/.cache/angular.stdout.log" -RedirectStandardError "$projectRoot/.cache/angular.stderr.log"
    foreach ($url in @('http://127.0.0.1:8000/api/v1/health', 'http://127.0.0.1:4200')) {
        $deadline = (Get-Date).AddSeconds(45)
        $ready = $false
        while ((Get-Date) -lt $deadline) {
            if ($apiProcess.HasExited -or $webProcess.HasExited) { throw 'Un serveur a quitté. Consultez les journaux .cache/*.stderr.log.' }
            try { $response = Invoke-WebRequest $url -UseBasicParsing -TimeoutSec 2; $ready = $response.StatusCode -eq 200 } catch { $ready = $false }
            if ($ready) { break }
            Start-Sleep -Milliseconds 500
        }
        if (!$ready) { throw "Le serveur ne répond pas : $url. Consultez .cache/*.stderr.log." }
    }
} catch {
    if ($webProcess -and !$webProcess.HasExited) { Stop-Process -Id $webProcess.Id -ErrorAction SilentlyContinue }
    if (!$apiProcess.HasExited) { Stop-Process -Id $apiProcess.Id -ErrorAction SilentlyContinue }
    throw
}
Write-Output "API PID $($apiProcess.Id) — http://127.0.0.1:8000/api/docs"
Write-Output "Angular PID $($webProcess.Id) — http://127.0.0.1:4200"
Write-Output 'Journaux dans .cache. Arrêt : ./scripts/stop-local.ps1 ; frontend seul : ./scripts/stop-local.ps1 -FrontendOnly.'
