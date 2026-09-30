import { Component, inject, signal, computed } from '@angular/core';
import { CurrencyPipe } from '@angular/common';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { Api } from '../core/api';
import { BuildResult, Cart, Product } from '../core/models';
import { CATEGORIES } from '../core/brand';
@Component({
  selector: 'bc-builder',
  imports: [CurrencyPipe, ReactiveFormsModule],
  template: ` <section class="wrap page">
    <span class="eyebrow">UNE MACHINE À VOTRE IMAGE</span>
    <h1>Construisons <em>votre prochain PC.</em></h1>
    <p class="lead">Chaque pièce compte. Composez, comparez et vérifiez votre configuration.</p>
    <form class="builder-intro panel" [formGroup]="form">
      <label>Nom de la configuration<input formControlName="name" maxlength="80" /></label>
      <label
        >Votre usage<select formControlName="usage">
          <option value="gaming">Gaming</option>
          <option value="creation">Création de contenu</option>
          <option value="development">Développement</option>
          <option value="office">Bureautique</option>
          <option value="workstation">Workstation</option>
        </select></label
      ><label
        >Votre budget (€)<input
          type="number"
          min="300"
          max="50000"
          formControlName="budget"
          (change)="check()"
      /></label>
      <p>L’usage guide votre projet. Aucun benchmark ou niveau de performance n’est garanti.</p>
    </form>
    <label class="check"
      ><input type="checkbox" [checked]="showAll()" (change)="showAll.set(!showAll())" />Afficher
      aussi les options incompatibles</label
    >
    <div class="builder-layout">
      <div>
        @for (c of categories; track c; let i = $index) {
          <section class="component-slot">
            <div class="slot-heading">
              <span class="step-number">{{ i + 1 }}</span>
              <div>
                <h2>{{ labels[c] }}</h2>
                <small>{{
                  c === 'gpu'
                    ? 'Facultative avec circuit graphique intégré'
                    : 'Composant obligatoire'
                }}</small>
              </div>
            </div>
            <label [for]="'part-' + c" class="sr-only">{{ labels[c] }}</label
            ><select [id]="'part-' + c" [value]="selected()[c] || ''" (change)="choose(c, $event)">
              <option value="">Choisir un composant</option>
              @for (p of options(c); track p.id) {
                <option [value]="p.id" [disabled]="!p.available">
                  {{ p.name }} — {{ p.price / 100 | currency: 'EUR' }}
                  {{ !p.available ? '(rupture)' : '' }}
                </option>
              }
            </select>
            @if (part(c); as p) {
              <div class="selected-part">
                <img [src]="p.images[0]?.url" alt="" width="90" height="72" />
                <div>
                  <strong>{{ p.name }}</strong>
                  <p>{{ p.summary }}</p>
                </div>
                <strong>{{ p.price / 100 | currency: 'EUR' }}</strong>
              </div>
            }
          </section>
        }
      </div>
      <aside class="build-summary panel">
        <span class="eyebrow">VOTRE CONFIGURATION</span>
        <h2>{{ count() }} / 8 composants</h2>
        <div class="budget-meter"><span [style.width.%]="progress()"></span></div>
        <p class="total-price">
          {{ result()?.total ? (result()!.total / 100 | currency: 'EUR') : '0,00 €' }}
        </p>
        <p class="muted">Budget : {{ form.controls.budget.value | currency: 'EUR' }}</p>
        <div aria-live="polite">
          @if (checking()) {
            <p>Vérification en cours…</p>
          } @else if (result()?.valid) {
            <div class="note success">✓ Configuration compatible selon les règles disponibles.</div>
          }
          @for (issue of result()?.issues; track issue.code) {
            <div class="issue" [class]="'issue ' + issue.severity">
              <strong>{{
                issue.severity === 'ERROR'
                  ? 'À corriger'
                  : issue.severity === 'WARNING'
                    ? 'À vérifier'
                    : 'À savoir'
              }}</strong>
              <p>{{ issue.message }}</p>
              <small>{{ issue.suggestion }}</small>
            </div>
          }
        </div>
        <button
          class="button primary full"
          [disabled]="!result()?.valid || checking()"
          (click)="add()"
        >
          {{
            groupId
              ? 'Enregistrer les modifications du panier'
              : 'Ajouter la configuration au panier'
          }}</button
        ><button
          class="button secondary full"
          [disabled]="!result()?.valid || !api.user()"
          (click)="save()"
        >
          Enregistrer ma configuration
        </button>
        @if (!api.user()) {
          <small>Connectez-vous pour enregistrer votre configuration.</small>
        }
      </aside>
    </div>
  </section>`,
})
export class Builder {
  api = inject(Api);
  router = inject(Router);
  route = inject(ActivatedRoute);
  fb = inject(FormBuilder);
  form = this.fb.nonNullable.group({
    name: 'Ma configuration',
    usage: this.route.snapshot.queryParamMap.get('usage') ?? 'gaming',
    budget: 1500,
  });
  categories = ['cpu', 'motherboard', 'memory', 'gpu', 'storage', 'psu', 'case', 'cooler'];
  labels = CATEGORIES;
  products = signal<(Product & { compatible: boolean })[]>([]);
  showAll = signal(false);
  selected = signal<Record<string, string>>({});
  result = signal<BuildResult | null>(null);
  checking = signal(false);
  sequence = 0;
  groupId = this.route.snapshot.queryParamMap.get('group');
  count = computed(() => Object.values(this.selected()).filter(Boolean).length);
  constructor() {
    void this.load();
  }
  async load() {
    try {
      if (this.groupId) {
        const cart = await this.api.get<Cart>('/cart');
        const group = cart.groups.find((item) => item.id === this.groupId);
        if (!group) throw new Error('Configuration absente');
        this.form.controls.name.setValue(group.name);
        const products = await Promise.all(
          group.components.map((id) => this.api.get<Product>('/products/' + id)),
        );
        this.selected.set(
          Object.fromEntries(products.map((product) => [product.category, product.id])),
        );
      }
      await this.check();
    } catch (e) {
      this.api.fail(e);
    }
  }
  options(c: string) {
    return this.products().filter(
      (p) => p.category === c && (this.showAll() || p.compatible || p.id === this.selected()[c]),
    );
  }
  part(c: string) {
    return this.products().find((p) => p.id === this.selected()[c]);
  }
  progress() {
    return Math.min(
      100,
      ((this.result()?.total ?? 0) / (this.form.controls.budget.value * 100)) * 100,
    );
  }
  choose(c: string, event: Event) {
    this.selected.update((v) => ({ ...v, [c]: (event.target as HTMLSelectElement).value }));
    void this.check();
  }
  ids() {
    return Object.values(this.selected()).filter(Boolean);
  }
  async check() {
    const seq = ++this.sequence;
    this.checking.set(true);
    try {
      const result = await this.api.mutate<BuildResult>('POST', '/configurations/check', {
        components: this.ids(),
        budget: Math.round(this.form.controls.budget.value * 100),
      });
      const options = await this.api.mutate<{ items: (Product & { compatible: boolean })[] }>(
        'POST',
        '/configurations/options',
        { components: this.ids() },
      );
      if (seq === this.sequence) {
        this.result.set(result);
        this.products.set(options.items);
      }
    } catch (e) {
      this.result.set(null);
      this.api.fail(e);
    } finally {
      if (seq === this.sequence) this.checking.set(false);
    }
  }
  async add() {
    try {
      this.api.cart.set(
        await this.api.mutate<Cart>(
          this.groupId ? 'PUT' : 'POST',
          this.groupId ? '/cart/configurations/' + this.groupId : '/cart/configuration',
          { components: this.ids(), name: this.form.controls.name.value },
        ),
      );
      await this.router.navigate(['/panier']);
    } catch (e) {
      this.api.fail(e);
    }
  }
  async save() {
    try {
      await this.api.mutate('POST', '/me/configurations', {
        name: this.form.controls.name.value,
        components: this.ids(),
      });
      this.api.success('Configuration enregistrée.');
    } catch (e) {
      this.api.fail(e);
    }
  }
}
