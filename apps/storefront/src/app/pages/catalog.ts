import { Component, inject, signal, DestroyRef } from '@angular/core';
import { ReactiveFormsModule, FormBuilder } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Api } from '../core/api';
import { CATEGORIES } from '../core/brand';
import { Page, Product } from '../core/models';
import { ProductCard } from '../core/product-card';
@Component({
  selector: 'bc-catalog',
  imports: [ReactiveFormsModule, ProductCard],
  template: ` <section class="wrap page">
    <span class="eyebrow">CHOISISSEZ LES FONDATIONS</span>
    <h1>Le bon composant.<br /><em>Pour chaque projet.</em></h1>
    <p class="lead">Explorez le catalogue et trouvez la prochaine pièce de votre configuration.</p>
    <div class="catalog-layout">
      <form class="filters panel" [formGroup]="form" (ngSubmit)="filter()">
        <h2>Affiner la sélection</h2>
        <label>Rechercher<input formControlName="q" placeholder="Nom, marque ou SKU" /></label
        ><label
          >Catégorie<select formControlName="category">
            <option value="">Tous les composants</option>
            @for (c of categories; track c[0]) {
              <option [value]="c[0]">{{ c[1] }}</option>
            }
          </select></label
        >
        @if (technicalFields().length) {
          <label
            >Caractéristique<select formControlName="specKey">
              <option value="">Aucune</option>
              @for (k of technicalFields(); track k) {
                <option [value]="k">{{ k }}</option>
              }
            </select></label
          ><label
            >Valeur exacte<input formControlName="specValue" placeholder="Ex. NC5, DDR5, 750"
          /></label>
        }
        <label
          >Marque<select formControlName="brand">
            <option value="">Toutes les marques</option>
            @for (b of brands; track b) {
              <option>{{ b }}</option>
            }
          </select></label
        ><label>Prix maximum (€)<input type="number" min="0" formControlName="max" /></label
        ><label class="check"
          ><input type="checkbox" formControlName="stock" /> En stock uniquement</label
        ><label
          >Trier par<select formControlName="sort">
            <option value="name">Nom</option>
            <option value="price_asc">Prix croissant</option>
            <option value="price_desc">Prix décroissant</option>
          </select></label
        ><button class="button primary" type="submit">Appliquer les filtres</button
        ><button type="button" class="text-button" (click)="reset()">Réinitialiser</button>
      </form>
      <div>
        <div class="results-heading">
          <strong>{{ data()?.total ?? 0 }} références</strong
          ><span>Produits fictifs · Prix TTC de démonstration</span>
        </div>
        @if (loading()) {
          <div class="empty" role="status">Chargement des composants…</div>
        } @else if (data()?.items?.length) {
          <div class="product-grid catalog-products">
            @for (p of data()!.items; track p.id) {
              <bc-product-card [product]="p" />
            }
          </div>
          <nav class="pagination" aria-label="Pagination">
            <button class="button secondary" [disabled]="page() === 1" (click)="go(page() - 1)">
              ← Précédent</button
            ><span>Page {{ page() }} / {{ pages() }}</span
            ><button
              class="button secondary"
              [disabled]="page() >= pages()"
              (click)="go(page() + 1)"
            >
              Suivant →
            </button>
          </nav>
        } @else {
          <div class="empty">
            <h2>Aucun composant trouvé</h2>
            <p>Essayez une autre recherche ou retirez un filtre.</p>
            <button class="button secondary" (click)="reset()">Effacer les filtres</button>
          </div>
        }
      </div>
    </div>
  </section>`,
})
export class Catalog {
  api = inject(Api);
  route = inject(ActivatedRoute);
  router = inject(Router);
  destroy = inject(DestroyRef);
  fb = inject(FormBuilder);
  categories = Object.entries(CATEGORIES);
  brands = ['Novea', 'Altis', 'Velora', 'Orven'];
  data = signal<Page<Product> | null>(null);
  loading = signal(true);
  page = signal(1);
  sequence = 0;
  form = this.fb.nonNullable.group({
    q: '',
    category: '',
    brand: '',
    max: '',
    stock: false,
    sort: 'name',
    specKey: '',
    specValue: '',
  });
  constructor() {
    this.form.controls.category.valueChanges
      .pipe(takeUntilDestroyed())
      .subscribe(() => this.form.patchValue({ specKey: '', specValue: '' }, { emitEvent: false }));
    this.route.queryParamMap.pipe(takeUntilDestroyed()).subscribe((p) => {
      this.form.patchValue(
        {
          specKey: p.get('specKey') ?? '',
          specValue: p.get('specValue') ?? '',
          q: p.get('q') ?? '',
          category: p.get('category') ?? '',
          brand: p.get('brand') ?? '',
          max: p.get('max') ? String(Number(p.get('max')) / 100) : '',
          stock: p.get('stock') === 'true',
          sort: p.get('sort') ?? 'name',
        },
        { emitEvent: false },
      );
      this.page.set(Number(p.get('page')) || 1);
      void this.load(
        p.keys
          .map((k) => encodeURIComponent(k) + '=' + encodeURIComponent(p.get(k) ?? ''))
          .join('&'),
      );
    });
  }
  async load(query: string) {
    const seq = ++this.sequence;
    this.loading.set(true);
    try {
      const data = await this.api.get<Page<Product>>('/products?' + query);
      if (seq === this.sequence) this.data.set(data);
    } catch (e) {
      this.api.fail(e);
    } finally {
      if (seq === this.sequence) this.loading.set(false);
    }
  }
  technicalFields() {
    const fields: Record<string, string[]> = {
      cpu: ['socket', 'cores', 'tdp'],
      motherboard: ['socket', 'memoryType', 'formFactor'],
      memory: ['memoryType', 'capacity', 'frequency'],
      gpu: ['length', 'tdp'],
      psu: ['watts', 'formFactor'],
      storage: ['interface', 'capacity'],
      cooler: ['height', 'type'],
    };
    return fields[this.form.controls.category.value] ?? [];
  }
  pages() {
    return Math.max(1, Math.ceil((this.data()?.total ?? 0) / 12));
  }
  filter() {
    const v = this.form.getRawValue();
    void this.router.navigate([], {
      queryParams: {
        ...v,
        max: v.max ? Math.round(Number(v.max) * 100) : null,
        stock: v.stock ? 'true' : null,
        page: 1,
      },
    });
  }
  reset() {
    void this.router.navigate(['/catalogue']);
  }
  go(page: number) {
    void this.router.navigate([], { queryParams: { page }, queryParamsHandling: 'merge' });
  }
}
