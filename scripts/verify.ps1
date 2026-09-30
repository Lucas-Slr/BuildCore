$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)
function Invoke-Checked([scriptblock] $Command) {
    & $Command
    if ($LASTEXITCODE -ne 0) { throw "Échec d’une vérification (code $LASTEXITCODE)." }
}
Invoke-Checked { php apps/api/bin/console lint:container }
Invoke-Checked { php apps/api/bin/console doctrine:schema:validate }
Invoke-Checked { php apps/api/vendor/bin/phpstan analyse -c apps/api/phpstan.neon --memory-limit=512M --no-progress }
Invoke-Checked { php apps/api/vendor/bin/php-cs-fixer fix --config=apps/api/.php-cs-fixer.dist.php --dry-run --diff }
Invoke-Checked { php apps/api/bin/console doctrine:migrations:migrate --env=test --no-interaction }
Invoke-Checked { php apps/api/bin/console app:fixtures --env=test }
Invoke-Checked { php apps/api/vendor/bin/phpunit -c apps/api/phpunit.xml.dist }
Invoke-Checked { npm --prefix apps/storefront run lint }
Invoke-Checked { npm --prefix apps/storefront run format:check }
Invoke-Checked { npm --prefix apps/storefront test }
Invoke-Checked { npm --prefix apps/storefront run build }
Write-Output 'Vérifications terminées. Pour Playwright, démarrer les serveurs puis npm --prefix apps/storefront run e2e.'
