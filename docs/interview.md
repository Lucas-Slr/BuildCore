# Quinze notions à expliquer en entretien

1. **Monolithe modulaire** : transactions simples et frontières métier lisibles sans coûts de microservices.
2. **Produit et variante** : identité descriptive séparée du SKU vendable, de son prix et de son stock.
3. **Monnaie entière** : centimes pour éviter les erreurs binaires des flottants.
4. **Caractéristiques structurées** : schémas par catégorie plutôt qu’un EAV libre non contrôlable.
5. **Moteur de compatibilité pur** : entrées explicites, diagnostics stables, tests indépendants du HTTP.
6. **Snapshots de commande** : un changement catalogue ne réécrit pas l’histoire de la vente.
7. **Disponible et réservé** : réserver n’est pas vendre ; expliquer physique − réservé.
8. **Concurrence PostgreSQL** : UPDATE conditionnel, verrous, transactions et ordre déterministe des références.
9. **Idempotence** : clé de checkout, identifiant Stripe et état de réservation protègent trois effets différents.
10. **Webhook comme autorité** : le navigateur peut revenir sans paiement ; signature et montant doivent être vérifiés.
11. **Frontière transaction/réseau** : ne pas conserver des verrous de stock pendant un appel Stripe.
12. **Workflow explicite** : distinguer commande, paiement et réservation ; refuser une transition incohérente.
13. **Sessions et CSRF** : HttpOnly n’empêche pas CSRF ; expliquer SameSite, rotation de session et même origine.
14. **Asynchronisme fiable** : messages persistés avec la transaction, retries, file d’échec et limites de l’exactly-once email.
15. **Autorisation temps réel** : Voter sur l’abonnement, topic privé, cookie signé, relecture API et repli périodique.

Préparer une démonstration de socket incompatible, de dernière unité, d’événement dupliqué et d’accès refusé à la commande d’un autre client. Distinguer systématiquement ce qui a été testé localement de ce qui exige Stripe et PostgreSQL réels.
