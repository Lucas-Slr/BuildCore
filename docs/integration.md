# Intégrations locales vérifiées

Le 30 septembre 2026, PostgreSQL 17.11, Mailpit 1.27.0 et Mercure 0.18.0 ont été démarrés depuis des archives officielles dans `.tools` (ignoré par Git). Ils écoutent uniquement sur `127.0.0.1`. Aucun service Windows n’a été installé. La boutique utilise toujours sa base SQLite de démonstration ; PostgreSQL dispose d’une base de tests indépendante.

Sources : [binaires PostgreSQL EDB](https://www.enterprisedb.com/download-postgresql-binaries), [Mailpit 1.27.0](https://github.com/axllent/mailpit/releases/tag/v1.27.0), [Mercure 0.18.0](https://github.com/dunglas/mercure/releases/tag/v0.18.0). Les sommes publiées pour les assets GitHub ont été utilisées lorsqu’elles étaient disponibles. Empreinte SHA256 de l’archive PostgreSQL téléchargée via HTTPS : `b9424ee7bc60b52450ff910a3630225df32e633f3cb29c1d126d9299d59aea28` ; cette empreinte locale n’est pas une signature de l’éditeur.

## Relancer sur cette machine

```powershell
$env:PATH = "$PWD\.tools\php84;" + $env:PATH
./scripts/start-services.ps1
# Si l’API et Angular ne tournent pas déjà :
./scripts/start-local.ps1
```

Les données de test sont dans `.cache/pgdata`, `.cache/mailpit.db` et le dossier de travail `.cache` du hub. Ne pas les versionner. Mailpit : <http://127.0.0.1:8025> ; santé Mercure : <http://127.0.0.1:3000/healthz>.

Sur une nouvelle machine, privilégier les commandes Docker du README. Le script portable réutilise les exécutables et le cluster déjà préparés ; il ne les télécharge pas automatiquement.

## PostgreSQL et concurrence

Dans un terminal réservé aux tests :

```powershell
$env:DATABASE_URL = 'postgresql://buildcore:buildcore_local_test@127.0.0.1:55432/buildcore_test?serverVersion=17&charset=utf8'
php apps/api/bin/console doctrine:migrations:migrate --env=test --no-interaction
php apps/api/bin/console app:fixtures --env=test
php apps/api/bin/console doctrine:schema:validate --env=test
php apps/api/vendor/bin/phpunit -c apps/api/phpunit.xml.dist
Remove-Item Env:DATABASE_URL
```

Le mot de passe ci-dessus est réservé à ce cluster local de démonstration. Résultat obtenu : 50 tests réussis, 163 assertions, aucun ignoré. Le test concurrent lance deux processus PHP distincts sur la dernière unité : une seule réservation réussit. Les migrations PostgreSQL et le schéma Doctrine sont synchronisés, y compris la génération d’identifiants Messenger.

## SMTP, workers et Mercure privé

```powershell
$env:PLAYWRIGHT_CHROMIUM_EXECUTABLE = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
node scripts/check-services.mjs
```

Ce script crée une commande fictive `SERVICE-*` en environnement `dev`, connecte Alice, Thomas et l’administrateur, vérifie le refus de l’abonnement de Thomas et de l’abonnement anonyme, ouvre le suivi dans Chrome, fait préparer la commande et consomme les files réelles. Il vérifie l’événement privé, le changement visible dans Angular et la réception d’un seul email dans Mailpit après répétition du message. Il n’appelle pas Stripe. Les commandes de contrôle restent visibles et identifiées dans les données de démonstration.

Le test force le SMTP vers Mailpit pour ses workers. Pour lancer un worker local continu :

```powershell
$env:MAILER_DSN = 'smtp://127.0.0.1:1025'
php apps/api/bin/console messenger:consume async emails realtime --time-limit=3600
```

Dans un autre terminal, `php apps/api/bin/console app:stock:alert` planifie les alertes au seuil de chaque variante. La déduplication limite à un email par variante et jour UTC ; le worker recontrôle le stock avant envoi. Trois alertes ont été reçues dans Mailpit lors de la vérification. Le scheduler Docker planifie ce contrôle et l’expiration des réservations chaque minute.

Le navigateur renouvelle son abonnement cinq secondes après une erreur de connexion ; le rafraîchissement API toutes les quinze secondes reste disponible. Le script a vérifié la reconnexion après interruption du premier abonnement dans Chrome. La reconnexion après une panne prolongée et les cookies derrière un reverse proxy HTTPS doivent encore être testés dans l’environnement de déploiement.

## Vérifications non réalisées

Docker/Redis et la CI distante n’ont pas été exécutés. Le parcours Stripe Checkout et le remboursement test nécessitent toujours les clés test et un webhook Stripe CLI configurés. Ne pas confondre les paiements contrôlés des tests avec un paiement de test effectué chez Stripe.
