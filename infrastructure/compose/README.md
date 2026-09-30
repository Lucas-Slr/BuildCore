# Infrastructure locale

Le fichier Compose principal est à la racine. Le profil `workers` démarre le consommateur Messenger et le planificateur d’expiration après les migrations.

Redis est destiné au stockage partagé des sessions et des limiteurs pour une installation distribuée. Le mode local sans Docker utilise les sessions fichiers Symfony. PostgreSQL porte les commandes et le transport Messenger, ce qui permet de publier les messages dans la transaction de paiement.

Le serveur PHP intégré du Dockerfile est exclusivement destiné au développement. Pour une mise en production, employer PHP-FPM derrière un serveur HTTPS, servir Angular et `/api` sous la même origine et protéger les secrets. Aucun déploiement n’est effectué par ce dépôt.
