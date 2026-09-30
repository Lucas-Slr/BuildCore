import { Component, inject, signal } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api';
import { HttpErrorResponse } from '@angular/common/http';
@Component({
  selector: 'bc-checkout',
  imports: [CurrencyPipe, ReactiveFormsModule, RouterLink],
  template: `<section class="wrap page">
    <span class="eyebrow">PLUS QUE QUELQUES ÉTAPES</span>
    <h1>Finaliser votre commande.</h1>
    <ol class="checkout-steps">
      <li>01 · Adresse</li>
      <li>02 · Livraison</li>
      <li>03 · Paiement sécurisé</li>
    </ol>
    <form class="checkout-layout" [formGroup]="form" (ngSubmit)="pay()">
      <div>
        <section class="panel">
          <h2>Votre adresse de livraison</h2>
          <p>
            France métropolitaine continentale uniquement. L’adresse de facturation est identique.
          </p>
          <label>Nom complet<input formControlName="name" autocomplete="name" /></label
          ><label>Adresse<input formControlName="street" autocomplete="street-address" /></label>
          <div class="two-columns">
            <label
              >Code postal<input
                formControlName="postalCode"
                autocomplete="postal-code"
                inputmode="numeric" /></label
            ><label>Ville<input formControlName="city" autocomplete="address-level2" /></label>
          </div>
        </section>
        <section class="panel">
          <h2>Mode de livraison</h2>
          <label class="delivery-choice"
            ><input type="radio" formControlName="delivery" value="standard" /><span
              ><strong>Standard</strong
              ><small>3 à 5 jours ouvrés · Estimation de démonstration</small></span
            ><strong>{{ standard() / 100 | currency: 'EUR' }}</strong></label
          ><label class="delivery-choice"
            ><input type="radio" formControlName="delivery" value="express" /><span
              ><strong>Express</strong
              ><small>1 à 2 jours ouvrés · Estimation de démonstration</small></span
            ><strong>19,90 €</strong></label
          >
        </section>
      </div>
      <aside class="panel summary">
        <h2>Votre commande</h2>
        @for (p of api.cart()?.items; track p.id) {
          <div>
            <span>{{ p.name }} × {{ p.quantity }}</span
            ><strong>{{ p.amount / 100 | currency: 'EUR' }}</strong>
          </div>
        }
        <div>
          <span>Livraison</span><strong>{{ shipping() / 100 | currency: 'EUR' }}</strong>
        </div>
        <div class="summary-total">
          <span>Total estimé TTC</span
          ><strong>{{ ((api.cart()?.subtotal ?? 0) + shipping()) / 100 | currency: 'EUR' }}</strong>
        </div>
        <label class="check"
          ><input type="checkbox" formControlName="accepted" /> Je comprends qu’il s’agit d’un
          paiement de test sans achat réel.</label
        >
        @if (submitted() && form.invalid) {
          <p class="field-error" role="alert">
            Complétez l’adresse et confirmez le mode démonstration.
          </p>
        }
        <button class="button primary full" [disabled]="busy() || !api.cart()?.valid" type="submit">
          {{ busy() ? 'Préparation…' : 'Continuer vers Stripe' }} ↗
        </button>
        <p class="muted">
          Le serveur recalcule le montant final et réserve le stock pendant environ 35 minutes. Vos
          données bancaires sont saisies uniquement chez Stripe.
        </p>
        <a routerLink="/panier">← Revoir mon panier</a>
      </aside>
    </form>
  </section>`,
})
export class Checkout {
  api = inject(Api);
  fb = inject(FormBuilder);
  busy = signal(false);
  submitted = signal(false);
  key = crypto.randomUUID();
  form = this.fb.nonNullable.group({
    name: ['', Validators.required],
    street: ['', Validators.required],
    postalCode: ['', [Validators.required, Validators.pattern(/^\d{5}$/)]],
    city: ['', Validators.required],
    delivery: 'standard',
    accepted: [false, Validators.requiredTrue],
  });
  constructor() {
    const a = this.api.user()?.addresses[0];
    if (a) this.form.patchValue(a);
    void this.api.refreshCart();
  }
  standard() {
    return (this.api.cart()?.subtotal ?? 0) >= 150000 ? 0 : 990;
  }
  shipping() {
    return this.form.controls.delivery.value === 'express' ? 1990 : this.standard();
  }
  async pay() {
    this.submitted.set(true);
    if (this.form.invalid) return;
    this.busy.set(true);
    const v = this.form.getRawValue();
    try {
      const result = await this.api.mutate<{ order: string; url: string }>(
        'POST',
        '/checkout',
        {
          address: {
            name: v.name,
            street: v.street,
            postalCode: v.postalCode,
            city: v.city,
            country: 'FR',
          },
          delivery: v.delivery,
          expectedLines: this.api
            .cart()
            ?.items.map(({ id, price, quantity }) => ({ id, price, quantity })),
        },
        { 'Idempotency-Key': this.key },
      );
      const url = new URL(result.url);
      if (url.protocol === 'https:' && url.hostname === 'checkout.stripe.com')
        window.location.assign(result.url);
      else throw new Error('Unexpected checkout URL');
    } catch (e) {
      if (e instanceof HttpErrorResponse && e.error?.error?.code === 'QUOTE_CHANGED') {
        await this.api.refreshCart();
        this.form.controls.accepted.setValue(false);
        this.submitted.set(false);
      }
      this.api.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
}
