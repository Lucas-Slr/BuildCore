# Workflow des commandes

```mermaid
stateDiagram-v2
  [*] --> PENDING_PAYMENT
  PENDING_PAYMENT --> PAID: pay / webhook
  PENDING_PAYMENT --> PAYMENT_FAILED: fail / expiration ou échec
  PENDING_PAYMENT --> CANCELED: cancel
  PAID --> PREPARING: prepare
  PREPARING --> SHIPPED: ship + suivi
  SHIPPED --> DELIVERED: deliver
  PAID --> REFUND_PENDING: request_refund
  PREPARING --> REFUND_PENDING: request_refund
  SHIPPED --> REFUND_PENDING: request_refund
  DELIVERED --> REFUND_PENDING: request_refund
  REFUND_PENDING --> REFUNDED: refund / webhook
```

Symfony Workflow est la source des transitions autorisées. Les contrôleurs administratifs exposent uniquement préparer, expédier et livrer ; ils ne permettent pas de marquer une commande payée. L’expédition exige un numéro de suivi.

Une transition interdite produit `TRANSITION_INVALID` avec HTTP 409. L’acteur, l’heure et l’état cible sont ajoutés à l’historique. Les états de paiement et de réservation sont indépendants : par exemple, un remboursement ne signifie pas que les marchandises sont revenues physiquement en stock.

`DRAFT` et `CANCELED` sont prévus dans le workflow mais ne disposent pas encore d’un parcours public d’annulation. La version actuelle crée directement `PENDING_PAYMENT` lors du checkout.
