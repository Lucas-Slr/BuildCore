# Audit des commandes de lancement — 30 septembre 2026

## Cause de l’erreur npm

Le serveur Angular de BuildCore resté en arrière-plan (PID 32036) utilisait le processus `esbuild.exe` PID 11352 dans le `node_modules` du projet. Windows empêchait `npm ci` de supprimer cet exécutable. Le script `stop-local.ps1 -FrontendOnly` a fermé le serveur concerné ; son enfant esbuild s’est arrêté. La même commande `npm ci --prefix apps/storefront` a ensuite installé 582 paquets et signalé zéro vulnérabilité.

## Corrections apportées

- Installation, lancement quotidien, arrêt et réinstallation séparés dans le README.
- Scripts d’arrêt limités aux exécutables/processus identifiables de BuildCore ; pas d’arrêt global de Node.
- PHP portable 8.4 sélectionné par le lanceur, versions et dépendances vérifiées avant démarrage.
- Lanceur attendant les réponses HTTP avant d’annoncer un démarrage réussi ; nettoyage des nouveaux processus en cas d’échec.
- Copies des `.env` conditionnées à leur absence, modèle SQLite fourni et configurations existantes préservées.
- URLs de lancement unifiées sur `127.0.0.1`, explication des cookies et du retour Stripe.
- Distinction entre API native et API Docker, entre commande de worker bloquante et commandes ponctuelles.
- Arrêt des services portables documenté avant utilisation des mêmes ports par Docker.
- Extension PHP mbstring ajoutée explicitement au Dockerfile ; worker Docker configuré pour redémarrer après sa sortie normale à une heure.
- Scripts PowerShell encodés en UTF-8 avec BOM pour Windows PowerShell 5.1 ; cycle arrêt/lancement exécuté dans cette version.

## Vérifications exécutées

| Étape | Résultat |
|---|---|
| PHP / Node / npm | 8.4.26 / 24.15.0 / 11.12.1 |
| Composer install | Réussi, scripts Symfony inclus |
| npm ci | Réussi après libération du verrou |
| Migrations, fixtures additives, setup des transports | Réussis sur la base native existante |
| Validation du schéma / conteneur Symfony | Réussie |
| Arrêt et lancement API/Angular | Réussis, aussi sous Windows PowerShell 5.1 |
| Arrêt et relance PostgreSQL/Mailpit/Mercure portables | Réussis, données conservées |
| PHPUnit SQLite | 49 réussis, 1 test réservé à PostgreSQL ignoré, 158 assertions |
| PHPStan, PHP CS Fixer, ESLint, Prettier | Réussis |
| Vitest / build Angular | 5 tests réussis / build réussi |
| Playwright | 9 parcours réussis après réinstallation |

Les fichiers `.env.local` existants n’ont pas été remplacés. Les copies initiales vers un fichier absent ont été relues, sans simuler une première installation en écrasant la configuration actuelle.

## Limites de cet audit

Docker et Stripe CLI ne sont pas installés ici. Leurs commandes et fichiers ont été relus, mais aucun `docker compose up`, build d’image ou paiement Stripe CLI n’est déclaré testé. Les services portables validés ne prouvent pas le fonctionnement de Docker/Redis. Ne pas exécuter les commandes de récupération des messages en échec comme une étape d’installation systématique.
