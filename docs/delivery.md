# Compte rendu de livraison BuildCore

Projet local : `C:\DEV\buildcore`. Aucun dépôt distant ni déploiement public n’a été créé.

## Architecture et services

Monorepo comprenant une API Symfony 7.4 / PHP 8.4 (`apps/api`), une application Angular 21 / Node 24 (`apps/storefront`), l’infrastructure et la documentation. Le backend est un monolithe modulaire dont les services métier séparent catalogue, compatibilité, panier, inventaire, commande, paiement et notifications.

Docker Compose définit PostgreSQL 17, Redis 7, PHP, Mailpit et Mercure, ainsi que worker et scheduler dans le profil `workers`. Ces services sont configurés mais leur démarrage Docker reste à valider sur une machine équipée. La démonstration exécutée ici utilise SQLite, des sessions fichiers et les serveurs PHP/Angular locaux.

Les commandes complètes d’installation native et Docker, de migration, de fixtures et de lancement sont dans le [README](../README.md). Pour relancer la démonstration déjà installée sous PowerShell :

```powershell
Set-Location C:\DEV\buildcore
$env:PATH = "$PWD\.tools\php84;" + $env:PATH
./scripts/start-local.ps1
```

## Accès et pages

Boutique : <http://127.0.0.1:4200>. Explorateur API : <http://127.0.0.1:8000/api/docs>. Contrat : <http://127.0.0.1:8000/api/v1/openapi.json>.

Comptes : `admin@buildcore.test`, `alice@buildcore.test`, `thomas@buildcore.test`. Mot de passe de démonstration commun : `BuildCore-Demo-2026!`.

Pages réalisées : accueil, catalogue, fiche produit, configurateur, panier, inscription, connexion, compte, checkout, liste et détail des commandes, retour de paiement, administration, accès refusé et page introuvable. L’administration comprend indicateurs, produits et variantes, images, stock et commandes.

## API et règles métier

Les endpoints principaux sous `/api/v1` concernent `/products`, `/categories`, `/configurations/check`, `/configurations/options`, `/cart`, `/auth`, `/me`, `/checkout`, `/orders`, `/payments/webhook` et `/admin`. Le contrat OpenAPI décrit les méthodes et les paramètres ; l’explorateur permet d’essayer les lectures.

Le configurateur contrôle socket CPU/carte mère, type et capacité mémoire, nombre de modules, format de carte mère, longueur GPU, format et puissance PSU, socket et hauteur du refroidissement, interfaces de stockage, composants manquants et stock. Il distingue erreurs bloquantes, avertissements et informations ; le budget est un avertissement. Les profils d’usage ne constituent pas des benchmarks.

Le stock disponible est le stock physique moins le stock réservé. Ajouter au panier ne réserve rien. Le checkout recalcule les prix, fige les lignes et l’adresse, puis réserve en transaction. Les mises à jour conditionnelles et les verrous visent à éviter la survente ; la validation concurrente PostgreSQL reste nécessaire. Un paiement confirmé consomme la réservation une seule fois ; une expiration fiable la libère une seule fois.

Stripe fonctionne exclusivement en mode test. Le serveur crée Checkout après validation métier avec une clé idempotente. Seul le webhook signé, contrôlé en montant, devise et session, confirme le paiement. Le retour navigateur relit la commande. Le remboursement total est demandé par l’administrateur puis confirmé par webhook, sans remise en stock physique implicite.

Messenger traite les expirations et distribue les changements de commande sur des files email et temps réel distinctes, avec retries et file d’échec. Les messages métier sont persistés avec la transaction de paiement. Mercure publie sur des topics privés ; l’abonnement exige l’autorisation propriétaire ou administrateur. Le frontend relit la commande après événement et dispose d’un repli périodique.

## Validation et limites

Les résultats détaillés et les limites non résolues figurent dans [status.md](status.md) : PHPUnit sur PostgreSQL, 50 tests réussis et 163 assertions ; Vitest, 5 réussis ; Playwright, 9 parcours réussis ; PHPStan niveau 6 sans erreur ; ESLint, formatage, build Angular et cache Symfony de production réussis.

Les [intégrations PostgreSQL, Mailpit et Mercure](integration.md) ont été exécutées avec des services portables : concurrence de stock, email, déduplication, topic privé, affichage du suivi Angular et reconnexion après erreur réseau. Les adresses sont éditables, les configurations sauvegardées supprimables, les groupes du panier modifiables, les changements de prix soumis à confirmation, les commandes filtrables et les alertes de stock envoyées par Messenger.

Le parcours Stripe Checkout et le remboursement test nécessitent encore les clés test et le webhook CLI. Docker/Redis et la CI distante restent non exécutés. Le projet ne doit pas être présenté comme ayant passé ces validations.

Les [quinze notions d’entretien](interview.md) expliquent les choix à défendre : modularité, variantes, centimes, caractéristiques structurées, moteur pur, snapshots, réservation, concurrence, idempotence, webhook, transaction/réseau, workflow, sessions/CSRF, asynchronisme et autorisation temps réel.
