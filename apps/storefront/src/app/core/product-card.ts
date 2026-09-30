import { Component, inject, input } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { RouterLink } from '@angular/router';
import { Product } from './models';
import { Api } from './api';
@Component({
  selector: 'bc-product-card',
  imports: [CurrencyPipe, RouterLink],
  template: ` <article class="product-card">
    <a class="product-art" [routerLink]="['/produits', product().id]"
      ><img
        [src]="product().images[0]?.url || '/assets/components/cpu.svg'"
        [alt]="product().images[0]?.alt || product().name"
        width="400"
        height="320"
        loading="lazy"
    /></a>
    <div class="product-info">
      <span class="eyebrow">{{ product().brand }}</span>
      <h3>
        <a [routerLink]="['/produits', product().id]">{{ product().name }}</a>
      </h3>
      <p class="product-summary">{{ product().summary }}</p>
      <span class="stock" [class.out]="!product().available">{{
        product().available ? '● En stock' : '○ Indisponible'
      }}</span>
      <div class="product-bottom">
        <strong>{{ product().price / 100 | currency: 'EUR' : 'symbol' : '1.2-2' : 'fr' }}</strong
        ><button
          class="icon-button"
          [disabled]="!product().available"
          (click)="api.add(product().id)"
          [attr.aria-label]="'Ajouter ' + product().name + ' au panier'"
        >
          ＋
        </button>
      </div>
    </div>
  </article>`,
})
export class ProductCard {
  product = input.required<Product>();
  api = inject(Api);
}
