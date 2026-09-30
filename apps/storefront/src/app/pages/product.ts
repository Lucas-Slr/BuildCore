import { Component, inject, signal } from '@angular/core';
import { CurrencyPipe, KeyValuePipe } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { FormControl, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Api } from '../core/api';
import { Product, Page } from '../core/models';
import { CATEGORIES } from '../core/brand';
import { ProductCard } from '../core/product-card';
@Component({
  selector: 'bc-product',
  imports: [CurrencyPipe, KeyValuePipe, RouterLink, ReactiveFormsModule, ProductCard],
  template: ` <section class="wrap page">
    <a routerLink="/catalogue" class="text-link">← Retour au catalogue</a>
    @if (loading()) {
      <p role="status">Chargement du produit…</p>
    } @else if (product(); as p) {
      <div class="product-detail">
        <div class="detail-art">
          <img [src]="p.images[0]?.url" [alt]="p.images[0]?.alt" width="640" height="512" />
        </div>
        <div>
          <span class="eyebrow">{{ p.brand }} / {{ categories[p.category] }}</span>
          <h1>{{ p.name }}</h1>
          <p class="lead">{{ p.summary }}</p>
          <strong class="detail-price">{{ p.price / 100 | currency: 'EUR' }}</strong>
          <p class="stock">
            {{
              p.available ? '● En stock · ' + p.available + ' disponibles' : '○ Rupture de stock'
            }}
          </p>
          <label class="quantity-label"
            >Quantité<input type="number" min="1" [max]="p.available" [formControl]="quantity"
          /></label>
          @if (quantity.invalid) {
            <p class="field-error" role="alert">
              Choisissez une quantité entre 1 et 10, dans la limite du stock.
            </p>
          }
          <div class="button-row">
            <button
              class="button primary"
              [disabled]="!p.available || quantity.invalid"
              (click)="api.add(p.id, quantity.value)"
            >
              Ajouter au panier ＋</button
            ><a routerLink="/configurateur" class="button secondary">Créer un PC</a>
          </div>
          <p class="muted">SKU : {{ p.sku }} · Prix TTC de démonstration</p>
          <div class="note">Illustration originale. Produit fictif, aucun achat réel.</div>
        </div>
      </div>
      <div class="two-columns section">
        <div>
          <h2>Conçu pour votre prochain projet.</h2>
          <p>{{ p.description }}</p>
        </div>
        <div class="panel">
          <h2>Caractéristiques techniques</h2>
          <dl class="spec-table">
            @for (s of p.specs | keyvalue; track s.key) {
              <div>
                <dt>{{ s.key }}</dt>
                <dd>{{ s.value }}</dd>
              </div>
            } @empty {
              <p>Consultez la description de cet équipement.</p>
            }
          </dl>
        </div>
      </div>
      <h2>Dans la même catégorie</h2>
      <div class="product-grid">
        @for (p of similar(); track p.id) {
          <bc-product-card [product]="p" />
        }
      </div>
    } @else {
      <div class="empty">
        <h1>Produit indisponible</h1>
        <p>Ce produit est absent ou archivé.</p>
        <a class="button primary" routerLink="/catalogue">Explorer le catalogue</a>
      </div>
    }
  </section>`,
})
export class ProductPage {
  api = inject(Api);
  route = inject(ActivatedRoute);
  product = signal<Product | null>(null);
  similar = signal<Product[]>([]);
  loading = signal(true);
  categories = CATEGORIES;
  quantity = new FormControl(1, {
    nonNullable: true,
    validators: [Validators.min(1), Validators.max(10), Validators.pattern(/^\d+$/)],
  });
  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe((p) => void this.load(p.get('id')!));
  }
  async load(id: string) {
    this.loading.set(true);
    try {
      const p = await this.api.get<Product>('/products/' + id);
      this.product.set(p);
      this.quantity.setValidators([
        Validators.min(1),
        Validators.max(Math.min(10, p.available)),
        Validators.pattern(/^\d+$/),
      ]);
      this.quantity.updateValueAndValidity();
      this.similar.set(
        (await this.api.get<Page<Product>>('/products?category=' + p.category)).items
          .filter((x) => x.id !== id)
          .slice(0, 4),
      );
    } catch (e) {
      this.product.set(null);
      this.api.fail(e);
    } finally {
      this.loading.set(false);
    }
  }
}
