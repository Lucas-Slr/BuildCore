import { Component, inject, signal } from '@angular/core';
import { CurrencyPipe, DatePipe } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api';
import { Page, Product, Order } from '../core/models';
import { CATEGORIES, STATUS } from '../core/brand';
interface Dashboard {
  revenue: number;
  pending: number;
  preparing: number;
  lowStock: { sku: string; physical: number; reserved: number }[];
}
@Component({
  selector: 'bc-admin',
  imports: [CurrencyPipe, ReactiveFormsModule, RouterLink, DatePipe],
  template: `<section class="wrap page">
    <span class="eyebrow">ADMINISTRATION</span>
    <h1>Le poste de pilotage.</h1>
    <nav class="tabs" aria-label="Sections administrateur">
      @for (t of tabs; track t.id) {
        <button [class.active]="tab() === t.id" (click)="tab.set(t.id)">{{ t.label }}</button>
      }
    </nav>
    @if (tab() === 'dashboard') {
      @if (dashboard(); as d) {
        <div class="metric-grid">
          <article class="panel">
            <span>Montants payés non remboursés</span
            ><strong>{{ d.revenue / 100 | currency: 'EUR' }}</strong>
          </article>
          <article class="panel">
            <span>Paiements en attente</span><strong>{{ d.pending }}</strong>
          </article>
          <article class="panel">
            <span>Commandes à préparer</span><strong>{{ d.preparing }}</strong>
          </article>
        </div>
        <section class="panel">
          <h2>Stock faible</h2>
          @for (s of d.lowStock; track s.sku) {
            <p>{{ s.sku }} : {{ s.physical - s.reserved }} disponibles</p>
          } @empty {
            <p>Aucune alerte de stock.</p>
          }
        </section>
      }
    }
    @if (tab() === 'catalog') {
      <div class="button-row">
        <button class="button primary" (click)="newProduct()">Nouveau produit</button
        ><button class="button secondary" (click)="load()">Actualiser</button>
      </div>
      <div class="two-columns">
        <div class="table-scroll">
          <table>
            <caption>
              Catalogue ·
              {{
                products().length
              }}
              références chargées
            </caption>
            <thead>
              <tr>
                <th>Produit</th>
                <th>Prix</th>
                <th>Statut</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @for (p of products(); track p.id) {
                <tr>
                  <td>
                    {{ p.name }}<small>{{ p.sku }}</small>
                  </td>
                  <td>{{ p.price / 100 | currency: 'EUR' }}</td>
                  <td>{{ p.status }}</td>
                  <td><button class="text-button" (click)="edit(p)">Modifier</button></td>
                </tr>
              }
            </tbody>
          </table>
        </div>
        @if (editing()) {
          <form class="panel" [formGroup]="productForm" (ngSubmit)="saveProduct()">
            <h2>{{ editingId ? 'Modifier le produit' : 'Nouveau produit' }}</h2>
            @if (editingId) {
              <details class="note">
                <summary>Ajouter une variante commerciale</summary>
                <label>Nom de variante<input #variantName /></label
                ><label>SKU unique<input #variantSku /></label
                ><label>Prix en centimes<input #variantPrice type="number" min="1" /></label
                ><button
                  type="button"
                  class="button secondary"
                  (click)="addVariant(variantName.value, variantSku.value, variantPrice.value)"
                >
                  Créer la variante
                </button>
                <p>Le stock initial est nul ; ajustez-le dans l’onglet Stock.</p>
              </details>
              <label
                >Ajouter une image (JPEG, PNG, WebP)<input
                  type="file"
                  accept="image/png,image/jpeg,image/webp"
                  (change)="chooseImage($event)" /></label
              ><label>Texte alternatif<input #imageAlt /></label
              ><button type="button" class="button secondary" (click)="uploadImage(imageAlt.value)">
                Importer l’image
              </button>
              <div class="image-admin">
                @for (image of editingImages(); track image.url) {
                  <img [src]="image.url" [alt]="image.alt" width="90" height="72" />
                  @if (image.id) {
                    <button
                      type="button"
                      class="text-button"
                      (click)="primaryImage(image.id, image.alt)"
                    >
                      Image principale</button
                    ><button type="button" class="text-button" (click)="removeImage(image.id)">
                      Retirer
                    </button>
                  }
                }
              </div>
            }
            <label>Nom<input formControlName="name" /></label
            ><label>Slug<input formControlName="slug" /></label
            ><label>Marque<input formControlName="brand" /></label
            ><label
              >Catégorie<select formControlName="category">
                @for (c of categories; track c[0]) {
                  <option [value]="c[0]">{{ c[1] }}</option>
                }
              </select></label
            ><label>Résumé<input formControlName="summary" /></label
            ><label>Description<textarea formControlName="description"></textarea></label
            ><label>SKU<input formControlName="sku" /></label
            ><label>Prix (centimes)<input type="number" min="1" formControlName="price" /></label
            ><label
              >Statut<select formControlName="status">
                <option>DRAFT</option>
                <option>PUBLISHED</option>
                <option>ARCHIVED</option>
              </select></label
            ><label
              >Caractéristiques structurées (JSON)<textarea
                rows="8"
                formControlName="specs"
              ></textarea>
            </label>
            <p class="muted">Les champs sont validés selon la catégorie côté serveur.</p>
            <button class="button primary" [disabled]="productForm.invalid">Enregistrer</button>
          </form>
        }
      </div>
    }
    @if (tab() === 'stock') {
      <div class="table-scroll">
        <table>
          <caption>
            Stock disponible et ajustements
          </caption>
          <thead>
            <tr>
              <th>Référence</th>
              <th>Disponible</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @for (p of products(); track p.id) {
              <tr>
                <td>
                  {{ p.name }}<small>{{ p.sku }}</small>
                </td>
                <td>{{ p.available }}</td>
                <td>
                  <button class="text-button" (click)="stockId.set(p.id); stockHistory(p.id)">
                    Ajuster / historique
                  </button>
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
      @if (stockId()) {
        <form class="panel" [formGroup]="stockForm" (ngSubmit)="adjust()">
          <h2>Ajustement transactionnel</h2>
          <label
            >Nouveau stock physique<input type="number" min="0" formControlName="quantity" /></label
          ><label>Raison<input formControlName="reason" /></label
          ><button class="button primary" [disabled]="stockForm.invalid">
            Enregistrer l’ajustement
          </button>
        </form>
        <div class="panel table-scroll">
          <table>
            <caption>
              Historique des ajustements
            </caption>
            <thead>
              <tr>
                <th>Date</th>
                <th>Avant</th>
                <th>Après</th>
                <th>Raison</th>
              </tr>
            </thead>
            <tbody>
              @for (h of history(); track h.id) {
                <tr>
                  <td>{{ h.createdAt | date: 'short' }}</td>
                  <td>{{ h.beforeQuantity }}</td>
                  <td>{{ h.afterQuantity }}</td>
                  <td>{{ h.reason }}</td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      }
    }
    @if (tab() === 'orders') {
      <form class="panel" [formGroup]="orderFilters" (ngSubmit)="searchOrders()">
        <h2>Rechercher des commandes</h2>
        <label>Numéro, email ou suivi<input formControlName="q" /></label>
        <label
          >État<select formControlName="status">
            <option value="">Tous les états</option>
            @for (entry of orderStatuses; track entry[0]) {
              <option [value]="entry[0]">{{ entry[1] }}</option>
            }
          </select></label
        >
        <div class="two-columns">
          <label>Du<input type="date" formControlName="from" /></label
          ><label>Au<input type="date" formControlName="to" /></label>
        </div>
        <button class="button secondary">Rechercher</button>
        <p>{{ orderTotal() }} commande(s)</p>
      </form>
      <div class="button-row">
        <button
          class="text-button"
          [disabled]="orderPage() <= 1"
          (click)="searchOrders(orderPage() - 1)"
        >
          Page précédente
        </button>
        <span>Page {{ orderPage() }}</span>
        <button
          class="text-button"
          [disabled]="orderPage() * 20 >= orderTotal()"
          (click)="searchOrders(orderPage() + 1)"
        >
          Page suivante
        </button>
      </div>
      @for (o of orders(); track o.id) {
        <article class="panel">
          <div class="order-title">
            <h2>
              <a [routerLink]="['/commandes', o.id]">{{ o.number }}</a>
            </h2>
            <span>{{ status[o.status] }}</span
            ><strong>{{ o.total / 100 | currency: 'EUR' }}</strong>
          </div>
          <div class="button-row">
            @if (o.status === 'PAID') {
              <button class="button secondary" (click)="transition(o.id, 'prepare')">
                Préparer
              </button>
            }
            @if (o.status === 'PREPARING') {
              <label>Numéro de suivi<input #tracking /></label
              ><button class="button secondary" (click)="transition(o.id, 'ship', tracking.value)">
                Expédier
              </button>
            }
            @if (o.status === 'SHIPPED') {
              <button class="button secondary" (click)="transition(o.id, 'deliver')">
                Marquer livrée
              </button>
            }
            @if (o.paymentStatus === 'PAID' && o.status !== 'REFUND_PENDING') {
              <button class="text-button" (click)="refundId.set(o.id)">
                Rembourser intégralement
              </button>
            }
            @if (refundId() === o.id) {
              <div class="note">
                <p>
                  Confirmer le remboursement total de {{ o.total / 100 | currency: 'EUR' }} en mode
                  test ?
                </p>
                <button class="button secondary" (click)="refund(o.id)">
                  Confirmer le remboursement</button
                ><button class="text-button" (click)="refundId.set('')">Annuler</button>
              </div>
            }
          </div>
        </article>
      } @empty {
        <div class="empty">Aucune commande à gérer.</div>
      }
    }
  </section>`,
})
export class Admin {
  api = inject(Api);
  fb = inject(FormBuilder);
  tab = signal('dashboard');
  tabs = [
    { id: 'dashboard', label: 'Vue d’ensemble' },
    { id: 'catalog', label: 'Catalogue' },
    { id: 'stock', label: 'Stock' },
    { id: 'orders', label: 'Commandes' },
  ];
  categories = Object.entries(CATEGORIES);
  status = STATUS;
  dashboard = signal<Dashboard | null>(null);
  products = signal<Product[]>([]);
  orders = signal<Order[]>([]);
  orderStatuses = Object.entries(STATUS);
  orderTotal = signal(0);
  orderPage = signal(1);
  orderRequest = 0;
  orderFilters = this.fb.nonNullable.group({ q: '', status: '', from: '', to: '' });
  async searchOrders(page = 1) {
    const sequence = ++this.orderRequest;
    try {
      const params = new URLSearchParams({
        ...this.orderFilters.getRawValue(),
        page: String(page),
      });
      const result = await this.api.get<Page<Order>>('/admin/orders?' + params);
      if (sequence !== this.orderRequest) return;
      this.orders.set(result.items);
      this.orderTotal.set(result.total);
      this.orderPage.set(result.page);
    } catch (e) {
      this.api.fail(e);
    }
  }
  editing = signal(false);
  editingId = '';
  stockId = signal('');
  history = signal<
    {
      id: string;
      createdAt: string;
      beforeQuantity: number;
      afterQuantity: number;
      reason: string;
    }[]
  >([]);
  refundId = signal('');
  productForm = this.fb.nonNullable.group({
    name: ['', Validators.required],
    slug: ['', Validators.required],
    brand: ['', Validators.required],
    category: 'cpu',
    summary: ['', Validators.required],
    description: ['', Validators.required],
    sku: ['', Validators.required],
    price: [100, Validators.min(1)],
    status: 'DRAFT',
    specs: '{}',
  });
  stockForm = this.fb.nonNullable.group({
    quantity: [0, Validators.min(0)],
    reason: ['', [Validators.required, Validators.minLength(3)]],
  });
  imageFile: File | null = null;
  editingImages = signal<{ id?: string; url: string; alt: string }[]>([]);
  chooseImage(event: Event) {
    this.imageFile = (event.target as HTMLInputElement).files?.[0] ?? null;
  }
  async uploadImage(alt: string) {
    if (!this.imageFile) {
      this.api.error.set('Sélectionnez une image.');
      return;
    }
    const data = new FormData();
    data.set('image', this.imageFile);
    data.set('alt', alt);
    try {
      const p = await this.api.mutate<Product>(
        'POST',
        '/admin/products/' + this.editingId + '/images',
        data,
      );
      this.editingImages.set(p.images);
      this.api.success('Image importée.');
      await this.load();
    } catch (e) {
      this.api.fail(e);
    }
  }
  async primaryImage(id: string, alt: string) {
    try {
      const p = await this.api.mutate<Product>(
        'PATCH',
        '/admin/products/' + this.editingId + '/images/' + id,
        { position: 0, alt },
      );
      this.editingImages.set(p.images);
    } catch (e) {
      this.api.fail(e);
    }
  }
  async removeImage(id: string) {
    try {
      const p = await this.api.mutate<Product>(
        'DELETE',
        '/admin/products/' + this.editingId + '/images/' + id,
      );
      this.editingImages.set(p.images);
    } catch (e) {
      this.api.fail(e);
    }
  }
  constructor() {
    void this.load();
  }
  async load() {
    const sequence = ++this.orderRequest;
    try {
      const [d, p, o] = await Promise.all([
        this.api.get<Dashboard>('/admin/dashboard'),
        this.api.get<Page<Product>>('/admin/products'),
        this.api.get<Page<Order>>(
          '/admin/orders?' +
            new URLSearchParams({
              ...this.orderFilters.getRawValue(),
              page: String(this.orderPage()),
            }),
        ),
      ]);
      this.dashboard.set(d);
      const more = await Promise.all(
        Array.from({ length: Math.ceil(p.total / 12) - 1 }, (_, i) =>
          this.api.get<Page<Product>>('/admin/products?page=' + (i + 2)),
        ),
      );
      this.products.set([...p.items, ...more.flatMap((x) => x.items)]);
      if (sequence === this.orderRequest) {
        this.orders.set(o.items);
        this.orderTotal.set(o.total);
      }
    } catch (e) {
      this.api.fail(e);
    }
  }
  newProduct() {
    this.editingId = '';
    this.productForm.reset();
    this.editing.set(true);
  }
  edit(p: Product) {
    this.editingImages.set(p.images);
    this.editingId = p.id;
    this.productForm.patchValue({ ...p, specs: JSON.stringify(p.specs, null, 2) });
    this.editing.set(true);
  }
  async addVariant(name: string, sku: string, price: string) {
    try {
      await this.api.mutate('POST', '/admin/products/' + this.editingId + '/variants', {
        name,
        sku,
        price: Number(price),
      });
      await this.load();
      this.api.success('Variante créée.');
    } catch (e) {
      this.api.fail(e);
    }
  }
  async saveProduct() {
    const v = this.productForm.getRawValue();
    let specs: unknown;
    try {
      specs = JSON.parse(v.specs);
    } catch {
      this.api.error.set('Les caractéristiques doivent être un document JSON valide.');
      return;
    }
    try {
      await this.api.mutate(
        this.editingId ? 'PUT' : 'POST',
        '/admin/products' + (this.editingId ? '/' + this.editingId : ''),
        { ...v, specs },
      );
      this.api.success('Produit enregistré.');
      await this.load();
    } catch (e) {
      this.api.fail(e);
    }
  }
  async adjust() {
    try {
      await this.api.mutate('POST', '/admin/stock/' + this.stockId(), this.stockForm.getRawValue());
      await this.load();
      await this.stockHistory(this.stockId());
      this.api.success('Stock ajusté.');
    } catch (e) {
      this.api.fail(e);
    }
  }
  async stockHistory(id: string) {
    try {
      this.history.set(
        (
          await this.api.get<{
            items: {
              id: string;
              createdAt: string;
              beforeQuantity: number;
              afterQuantity: number;
              reason: string;
            }[];
          }>('/admin/stock/' + id + '/history')
        ).items,
      );
    } catch (e) {
      this.api.fail(e);
    }
  }
  async transition(id: string, transition: string, tracking = '') {
    try {
      await this.api.mutate('POST', '/admin/orders/' + id + '/transition', {
        transition,
        tracking,
      });
      await this.load();
    } catch (e) {
      this.api.fail(e);
    }
  }
  async refund(id: string) {
    try {
      await this.api.mutate('POST', '/admin/orders/' + id + '/refund');
      this.refundId.set('');
      await this.load();
      this.api.success('Demande envoyée. Confirmation Stripe attendue.');
    } catch (e) {
      this.api.fail(e);
    }
  }
}
