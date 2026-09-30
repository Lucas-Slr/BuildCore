export const BRAND = {
  name: 'BuildCore',
  tagline: 'La performance commence ici.',
  description: 'Les bons composants. Votre configuration.',
  accent: '#087f6a',
} as const;
export const CATEGORIES: Record<string, string> = {
  cpu: 'Processeurs',
  motherboard: 'Cartes mères',
  memory: 'Mémoire',
  gpu: 'Cartes graphiques',
  psu: 'Alimentations',
  case: 'Boîtiers',
  storage: 'Stockage',
  cooler: 'Refroidissement',
  monitor: 'Écrans',
  keyboard: 'Claviers',
  mouse: 'Souris',
  headset: 'Casques',
  prebuilt: 'PC préassemblés',
};
export const STATUS: Record<string, string> = {
  PENDING_PAYMENT: 'En attente de paiement',
  PAID: 'Paiement confirmé',
  PREPARING: 'En préparation',
  SHIPPED: 'Expédiée',
  DELIVERED: 'Livrée',
  PAYMENT_FAILED: 'Paiement échoué ou expiré',
  CANCELED: 'Annulée',
  REFUND_PENDING: 'Remboursement demandé',
  REFUNDED: 'Remboursée',
};
