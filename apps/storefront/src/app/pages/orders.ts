import { Component, inject, signal, DestroyRef } from '@angular/core';
import { CurrencyPipe, DatePipe } from '@angular/common';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { interval } from 'rxjs';
import { Api } from '../core/api';
import { Order } from '../core/models';
import { STATUS } from '../core/brand';
@Component({
  selector: 'bc-orders',
  imports: [CurrencyPipe, DatePipe, RouterLink],
  template: `<section class="wrap page">
    <span class="eyebrow">DE LA COMMANDE À VOTRE BUREAU</span>
    <h1>{{ order() ? 'Votre commande.' : 'Mes commandes.' }}</h1>
    @if (loading()) {
      <p role="status">Chargement…</p>
    }
    @if (order(); as o) {
      <div class="order-title">
        <h2>{{ o.number }}</h2>
        <span class="status-badge">{{ status[o.status] }}</span
        ><button class="button secondary" (click)="load()">Actualiser</button>
      </div>
      @if (o.status === 'PENDING_PAYMENT') {
        <div class="note">
          Le paiement attend sa confirmation par Stripe. Le retour sur cette page ne confirme pas le
          paiement. La réservation expire le {{ o.expiresAt | date: 'short' }}.
        </div>
      }
      <p class="muted" role="status">
        {{
          realtime()
            ? 'Suivi en direct connecté.'
            : 'Actualisation automatique toutes les 15 secondes.'
        }}
      </p>
      <div class="checkout-layout">
        <div class="panel">
          <h2>Les étapes de votre commande</h2>
          <ol class="timeline">
            @for (h of o.history; track $index) {
              <li>
                <strong>{{ status[h.status] || h.status }}</strong
                ><span>{{ h.at | date: 'medium' }}</span>
              </li>
            }
          </ol>
          @if (o.tracking) {
            <p>
              Numéro de suivi : <strong>{{ o.tracking }}</strong>
            </p>
          }
          <h2>Vos composants</h2>
          @for (l of o.lines; track l.sku) {
            <div class="order-line">
              <span>{{ l.name }} × {{ l.quantity }}</span
              ><strong>{{ l.amount / 100 | currency: 'EUR' }}</strong>
            </div>
          }
        </div>
        <aside class="panel summary">
          <h2>Livraison {{ o.delivery }}</h2>
          <address>
            {{ o.shippingAddress.name }}<br />{{ o.shippingAddress.street }}<br />{{
              o.shippingAddress.postalCode
            }}
            {{ o.shippingAddress.city }}
          </address>
          <div>
            <span>Frais de livraison</span><strong>{{ o.shipping / 100 | currency: 'EUR' }}</strong>
          </div>
          <div class="summary-total">
            <span>Total</span><strong>{{ o.total / 100 | currency: 'EUR' }}</strong>
          </div>
          <a routerLink="/commandes">← Toutes mes commandes</a>
        </aside>
      </div>
    } @else if (!loading()) {
      @for (o of orders(); track o.id) {
        <a [routerLink]="['/commandes', o.id]" class="order-list-row"
          ><div>
            <strong>{{ o.number }}</strong
            ><small>{{ o.createdAt | date: 'mediumDate' }}</small>
          </div>
          <span class="status-badge">{{ status[o.status] }}</span
          ><strong>{{ o.total / 100 | currency: 'EUR' }} →</strong></a
        >
      } @empty {
        <div class="empty">
          <h2>Aucune commande pour le moment.</h2>
          <p>Votre prochain projet commence dans le catalogue.</p>
          <a routerLink="/catalogue" class="button primary">Explorer les composants</a>
        </div>
      }
    }
  </section>`,
})
export class Orders {
  api = inject(Api);
  route = inject(ActivatedRoute);
  destroy = inject(DestroyRef);
  status = STATUS;
  order = signal<Order | null>(null);
  orders = signal<Order[]>([]);
  loading = signal(true);
  realtime = signal(false);
  source?: EventSource;
  retry?: ReturnType<typeof setTimeout>;
  stopped = false;
  id: string | null = null;
  constructor() {
    this.route.paramMap.pipe(takeUntilDestroyed()).subscribe((p) => {
      this.id = p.get('id') ?? this.route.snapshot.queryParamMap.get('order');
      void this.load();
      void this.subscribe();
    });
    interval(15000)
      .pipe(takeUntilDestroyed())
      .subscribe(() => {
        if (this.id) void this.load(false);
      });
    this.destroy.onDestroy(() => {
      this.stopped = true;
      clearTimeout(this.retry);
      this.source?.close();
    });
  }
  async load(show = true) {
    if (show) this.loading.set(true);
    try {
      if (this.id) this.order.set(await this.api.get<Order>('/orders/' + this.id));
      else this.orders.set((await this.api.get<{ items: Order[] }>('/orders')).items);
    } catch (e) {
      this.api.fail(e);
    } finally {
      this.loading.set(false);
    }
  }
  async subscribe() {
    clearTimeout(this.retry);
    this.source?.close();
    if (!this.id || this.stopped) return;
    const id = this.id;
    try {
      const { topic } = await this.api.mutate<{ topic: string }>(
        'POST',
        '/orders/' + this.id + '/subscription',
      );
      if (this.stopped || id !== this.id) return;
      this.source = new EventSource('/.well-known/mercure?topic=' + encodeURIComponent(topic), {
        withCredentials: true,
      });
      this.source.onopen = () => this.realtime.set(true);
      this.source.onmessage = () => void this.load(false);
      this.source.onerror = () => {
        this.realtime.set(false);
        this.source?.close();
        this.retry = setTimeout(() => void this.subscribe(), 5000);
      };
    } catch {
      this.realtime.set(false);
      if (!this.stopped) this.retry = setTimeout(() => void this.subscribe(), 5000);
    }
  }
}
