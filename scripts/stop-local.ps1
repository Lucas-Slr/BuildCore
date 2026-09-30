param([switch]$FrontendOnly)
$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path $PSScriptRoot -Parent
$processes = @(Get-CimInstance Win32_Process)
$targets = @{}
$engines = @($processes | Where-Object { $_.ExecutablePath -eq "$projectRoot\apps\storefront\node_modules\@esbuild\win32-x64\esbuild.exe" })
foreach ($engine in $engines) {
    $parent = $processes | Where-Object { $_.ProcessId -eq $engine.ParentProcessId }
    if ($parent -and $parent.Name -eq 'node.exe' -and $parent.CommandLine -match '(ng\.js|angular).*serve') { $targets[$parent.ProcessId] = $parent }
    $targets[$engine.ProcessId] = $engine
}
foreach ($process in $processes) {
    $command = ([string]$process.CommandLine).Replace('\', '/')
    $root = $projectRoot.Replace('\', '/')
    if ($process.Name -eq 'node.exe' -and $command.Contains("$root/apps/storefront/node_modules/@angular/cli/bin/ng.js") -and $command -match '\bserve\b') { $targets[$process.ProcessId] = $process }
    if (!$FrontendOnly -and $process.Name -eq 'php.exe' -and
        ($command.Contains("$root/apps/api/") -or
         ($process.ExecutablePath -eq "$projectRoot\.tools\php84\php.exe" -and $command -match 'apps/api/(public|bin/console)'))) {
        $targets[$process.ProcessId] = $process
    }
}
foreach ($target in ($targets.Values | Sort-Object @{Expression={ $_.Name -eq 'esbuild.exe' }})) {
    $current = Get-CimInstance Win32_Process -Filter "ProcessId=$($target.ProcessId)"
    if ($current -and $current.CreationDate -eq $target.CreationDate) {
        Stop-Process -Id $target.ProcessId -ErrorAction SilentlyContinue
        Wait-Process -Id $target.ProcessId -Timeout 10 -ErrorAction SilentlyContinue
        Write-Output "Arrêt BuildCore : $($target.Name), PID $($target.ProcessId)"
    }
}
Write-Output 'Les services PostgreSQL, Mailpit et Mercure restent disponibles.'
