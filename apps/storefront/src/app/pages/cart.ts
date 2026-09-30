import { Component, inject } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api';
import { Cart } from '../core/models';
@Component({
  selector: 'bc-cart',
  imports: [CurrencyPipe, RouterLink],
  template: `<section class="wrap page">
    <span class="eyebrow">VOTRE PROJET PREND FORME</span>
    <h1>Votre panier.</h1>
    @if (api.cart(); as cart) {
      @if (cart.items.length) {
        @for (group of cart.groups; track group.id) {
          <section class="panel">
            <h2>{{ group.name }}</h2>
            <p>{{ group.components.length }} composants · Une unité de chaque composant</p>
            <div class="button-row">
              <a class="text-link" routerLink="/configurateur" [queryParams]="{ group: group.id }"
                >Modifier cette configuration</a
              >
              <button class="text-button" (click)="removeGroup(group.id)">
                Retirer cette configuration
              </button>
            </div>
          </section>
        }
        @if (cart.groups.length) {
          <p class="muted">
            Modifier séparément la quantité d’un composant dissocie sa configuration. Les autres
            composants restent dans le panier.
          </p>
        }
        <div class="checkout-layout">
          <div>
            @for (p of cart.items; track p.id) {
              <article class="cart-row">
                <img
                  [src]="p.images[0]?.url || '/assets/components/cpu.svg'"
                  alt=""
                  width="120"
                  height="96"
                />
                <div>
                  <h2>
                    <a [routerLink]="['/produits', p.id]">{{ p.name }}</a>
                  </h2>
                  <p>{{ p.price / 100 | currency: 'EUR' }} / unité</p>
                  @if (!p.valid) {
                    <p class="field-error">Produit indisponible ou stock insuffisant.</p>
                  }
                  <button class="text-button" (click)="change(p.id, 0)">Supprimer</button>
                </div>
                <label
                  >Quantité<input
                    type="number"
                    min="1"
                    max="10"
                    [value]="p.quantity"
                    (change)="quantity(p.id, $event)" /></label
                ><strong>{{ p.amount / 100 | currency: 'EUR' }}</strong>
              </article>
            }
          </div>
          <aside class="panel summary">
            <h2>Récapitulatif</h2>
            <div>
              <span>Sous-total</span><strong>{{ cart.subtotal / 100 | currency: 'EUR' }}</strong>
            </div>
            <div>
              <span>Livraison standard estimée</span
              ><strong>{{ cart.shipping / 100 | currency: 'EUR' }}</strong>
            </div>
            <div class="summary-total">
              <span>Total TTC</span><strong>{{ cart.total / 100 | currency: 'EUR' }}</strong>
            </div>
            @if (cart.valid) {
              <a routerLink="/commande" class="button primary full">Passer commande →</a>
            } @else {
              <p class="field-error">Corrigez votre panier avant de continuer.</p>
            }
            <p class="muted">
              Les prix et le stock sont recalculés côté serveur avant le paiement. Aucun stock n’est
              réservé à ce stade.
            </p>
            <a routerLink="/catalogue" class="text-link">Continuer mes recherches</a>
          </aside>
        </div>
      } @else {
        <div class="empty">
          <span class="empty-icon">▧</span>
          <h2>Tout commence par une première pièce.</h2>
          <p>Votre panier est encore vide. Le prochain projet vous attend.</p>
          <a routerLink="/catalogue" class="button primary">Explorer les composants ↗</a>
        </div>
      }
    } @else {
      <p role="status">Chargement du panier…</p>
    }
  </section>`,
})
export class CartPage {
  api = inject(Api);
  constructor() {
    void this.api.refreshCart();
  }
  quantity(id: string, e: Event) {
    void this.change(id, Number((e.target as HTMLInputElement).value));
  }
  async removeGroup(id: string) {
    try {
      this.api.cart.set(await this.api.mutate<Cart>('DELETE', '/cart/configurations/' + id));
    } catch (e) {
      this.api.fail(e);
    }
  }
  async change(id: string, quantity: number) {
    try {
      this.api.cart.set(
        await this.api.mutate<Cart>(quantity ? 'PUT' : 'DELETE', '/cart/items/' + id, { quantity }),
      );
    } catch (e) {
      this.api.fail(e);
    }
  }
}
