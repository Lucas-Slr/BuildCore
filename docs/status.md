# État des vérifications

Dernière validation locale : **30 septembre 2026**, Windows, PHP 8.4.26, Node 24, PostgreSQL 17.11, SQLite, Mailpit 1.27.0, Mercure 0.18.0 et Google Chrome. PostgreSQL a été testé avec un exécutable portable, indépendamment de Docker.

## Résultats effectivement obtenus

| Vérification | Résultat |
|---|---|
| PHPUnit PostgreSQL | 50 tests réussis, 163 assertions, aucun ignoré ; concurrence entre deux processus incluse |
| PHPUnit SQLite | 49 réussis, 1 test PostgreSQL ignoré, 158 assertions |
| PHPStan niveau 6 | Aucune erreur |
| PHP CS Fixer, contrôle sans modification | 0 fichier à corriger sur 56 |
| ESLint Angular | Réussi |
| Prettier | Tous les fichiers contrôlés sont conformes |
| Vitest Angular | 5 tests réussis |
| Playwright avec Chrome installé | 9 parcours réussis, incluant édition d’adresse, groupe de panier et confirmation des prix |
| Audit axe automatisé | Aucune violation des règles sélectionnées WCAG A/AA sur accueil et configurateur |
| Build Angular de production | Réussi ; chargement initial 312,55 kB, transfert estimé 85,90 kB |
| Cache Symfony de production | Préchauffage réussi |
| Schéma Doctrine | Mapping et migrations synchronisés sur SQLite et PostgreSQL |
| Migrations et fixtures locales | Appliquées ; catalogue fictif et comptes disponibles |
| Workers email et temps réel | Transition publiée sur Mercure privé, affichée dans Angular et email capturé par Mailpit ; événement dupliqué sans second email |
| Reconnexion Mercure | Abonnement navigateur rétabli après interruption réseau simulée |
| Alertes de stock | Trois emails capturés dans Mailpit ; test de déduplication et recontrôle du stock réussi |

Les tests backend couvrent notamment compatibilité, panier, permissions, CSRF, checkout avec passerelle contrôlée, réservation, expiration, signatures et événements de paiement dupliqués. Les parcours navigateur couvrent catalogue et filtres, configuration compatible puis incompatible, ajout groupé, connexion et message Stripe absent, accès admin interdit et autorisé, mobile, clavier, erreurs réseau et accessibilité principale. L’audit automatisé ne constitue pas une certification d’accessibilité.

Les captures de `docs/screenshots` proviennent de l’interface locale réellement exécutée. Les rapports Playwright et leurs traces sont générés dans `apps/storefront/playwright-report` et `apps/storefront/test-results` (ignorés par Git).

## Validations externes encore nécessaires

- **Docker Compose et Redis** : Docker n’est pas installé sur cette machine. PostgreSQL a été validé nativement, mais les conteneurs, leur réseau et les sessions Redis n’ont pas été exécutés.
- **Stripe test** : absence de clés de test configurées et de Stripe CLI connecté. Les événements contrôlés et signatures sont testés localement ; la redirection Checkout, le paiement de test et le remboursement via Stripe restent à valider avec de vrais échanges réseau.
- **Déploiement temps réel** : le flux privé fonctionne localement ; les cookies et le proxy HTTPS restent à vérifier sur l’hébergement cible.
- **CI** : workflow fourni, sans dépôt distant créé ni exécution GitHub Actions.

Pour reproduire les intégrations déjà vérifiées, suivre [integration.md](integration.md). Pour terminer les autres vérifications, exécuter Compose, connecter Stripe CLI, puis payer une commande test. Vérifier sa vente de stock, son email Mailpit et son suivi Mercure ; rejouer le webhook, tester une expiration et un remboursement total. Conserver les identifiants d’événements et les résultats avant de modifier ce bilan.

## Limites fonctionnelles et techniques

- Les catégories et marques sont des valeurs structurées du catalogue ; elles n’ont pas de gestion dédiée. Les filtres techniques sont exacts et le configurateur examine au plus 200 variantes candidates.
- Les adresses se créent, se modifient et se suppriment ; les configurations sauvegardées peuvent être supprimées. Les groupes du panier se modifient via le configurateur. Une modification indépendante de quantité dissocie les groupes concernés en conservant leurs autres lignes.
- Le checkout compare chaque prix et quantité au récapitulatif affiché, puis exige une nouvelle confirmation si le panier a changé. La recherche administrative combine numéro/email/suivi, état et dates, avec pagination de 20 commandes.
- La réservation dure environ 35 minutes. Une panne Stripe diffère sa libération jusqu’à un rapprochement fiable. Une création de session trop tardive peut être refusée par le minimum d’expiration Stripe ; une nouvelle tentative devra attendre la résolution de la réservation précédente.
- Les notifications sont séparées par canal et dédupliquées. Un arrêt après acceptation SMTP mais avant écriture du reçu peut toutefois produire un email en double. Les alertes de stock sont limitées à une par référence et jour UTC.
- Les commandes `DEMO-*` illustrent le suivi et les indicateurs ; elles ne correspondent pas à des paiements Stripe remboursables.
- Remboursements intégraux uniquement, sans remise en stock automatique. France continentale uniquement, sans Corse ni outre-mer.
- La récupération de mot de passe, la vérification d’email et le durcissement d’un hébergement de production restent hors de cette livraison. Voir `security.md` pour les limites précises.

Cette livraison constitue une démonstration locale fonctionnelle. Les vérifications externes ci-dessus restent ouvertes ; elle ne doit pas être présentée comme une boutique prête à encaisser des paiements réels.
