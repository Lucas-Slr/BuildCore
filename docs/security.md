# Sécurité

## Authentification et sessions

Symfony Security utilise un fournisseur Doctrine et le hachage `auto`. Le cookie `BUILDCORE_SESSION` est HttpOnly, SameSite=Lax et Secure en production. La session est migrée à la connexion ; Angular renouvelle son jeton CSRF. Aucune donnée d’authentification n’est placée dans localStorage.

Le mode natif emploie les sessions fichiers ; Docker emploie l’extension Redis native avec verrouillage de session. Ce verrouillage évite les écrasements entre requêtes concurrentes. Le limiteur Symfony borne les tentatives de connexion à cinq par minute. Les erreurs de connexion et la réponse d’inscription ne divulguent pas l’existence d’un compte.

## CSRF, origine et autorisations

Toutes les mutations `/api` exigent `X-CSRF-Token`, obtenu via `/auth/csrf`. Seul le webhook Stripe est exempté, avec signature obligatoire. Le contrôle CSRF s’exécute avant le firewall. Les routes `/me`, `/orders` et `/checkout` exigent un client ; `/admin` exige un administrateur. `OrderVoter` protège chaque commande et l’émission du cookie Mercure. Les gardes Angular n’ont qu’un rôle d’ergonomie.

Le frontend proxifie l’API et Mercure en développement. Le déploiement cible est de même origine en HTTPS ; aucun CORS API permissif n’est ajouté. Mercure a une liste d’origines locale explicite.

## Entrées, XSS et images

Les requêtes Doctrine/DBAL sont paramétrées ; noms de champs techniques et tris sont sur liste autorisée. Le frontend utilise les interpolations Angular et ne rend pas de HTML de produit. Les erreurs API exposent un code et un message, sans pile interne. Les en-têtes `nosniff`, `DENY` et une politique Referrer sont présents.

Les uploads sont limités aux JPEG, PNG et WebP de 5 Mo / 4096 px. Le type réel est inspecté, le nom est aléatoire et les fichiers restent dans `var/uploads`, hors de la racine publique. La lecture passe par une route qui impose le type MIME, `nosniff` et une CSP sandbox. Les SVG fournis avec la boutique sont des assets originaux de confiance ; les SVG importés sont refusés. Une abstraction `ImageStorage` permet une évolution vers un stockage objet.

## Paiements et secrets

L’application n’accepte que les clés Stripe test et ne reçoit aucune donnée de carte. Le montant est recalculé côté serveur ; le webhook signé est l’unique confirmation. Signature, montant, devise, session, propriétaire, état et idempotence sont vérifiés. Les secrets sont exclus de Git et du contexte Docker.

Les topics Mercure sont privés et ne contiennent que l’identifiant et l’état de la commande. Les adresses, emails et hashes ne sont pas publiés. Les journaux de développement ne doivent pas être utilisés tels quels en production ; configurez la rétention et la protection d’accès.

Le cookie Mercure est HttpOnly, SameSite=Strict, limité au chemin du proxy du hub et à l’hôte courant ; il est Secure en production et expire après une heure. Son JWT donne uniquement le droit de souscrire à la commande autorisée, sans droit de publication. Le frontend renouvelle l’abonnement après erreur.

## Limites avant production

Audit indépendant non effectué ; pas de récupération de mot de passe ni vérification d’email ; limiteurs non distribués hors session Redis ; aucune analyse antivirus ou réencodage des images ; cookies et reverse proxy HTTPS à vérifier sur l’hébergement choisi ; politique CSP de l’application et HSTS à configurer sur le serveur frontal. Le serveur PHP intégré et les identifiants de démonstration ne conviennent pas à la production.
