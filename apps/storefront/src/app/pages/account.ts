import { Component, inject } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink, Router } from '@angular/router';
import { Api } from '../core/api';
import { Address, Cart } from '../core/models';
@Component({
  selector: 'bc-account',
  imports: [ReactiveFormsModule, RouterLink],
  template: `<section class="wrap page">
    <span class="eyebrow">VOTRE ESPACE</span>
    <h1>
      Bonjour, <em>{{ api.user()?.name }}.</em>
    </h1>
    <div class="button-row">
      <a routerLink="/commandes" class="button primary">Mes commandes →</a>
      @if (api.user()?.roles?.includes('ROLE_ADMIN')) {
        <a routerLink="/admin" class="button secondary">Administration</a>
      }
      <button class="text-button" (click)="logout()">Me déconnecter</button>
    </div>
    <div class="two-columns section">
      <div>
        <form class="panel" [formGroup]="profile" (ngSubmit)="saveProfile()">
          <h2>Mon profil</h2>
          <p>{{ api.user()?.email }}</p>
          <label>Nom<input formControlName="name" /></label
          ><button class="button secondary" [disabled]="profile.invalid">Enregistrer</button>
        </form>
        <form class="panel" [formGroup]="password" (ngSubmit)="changePassword()">
          <h2>Changer mon mot de passe</h2>
          <label
            >Mot de passe actuel<input
              type="password"
              autocomplete="current-password"
              formControlName="currentPassword" /></label
          ><label
            >Nouveau mot de passe<input
              type="password"
              autocomplete="new-password"
              formControlName="password" /></label
          ><small>12 caractères minimum. Vous devrez vous reconnecter.</small
          ><button class="button secondary" [disabled]="password.invalid">Modifier</button>
        </form>
        <section class="panel">
          <h2>Mes configurations</h2>
          @for (b of api.user()?.builds; track b.id) {
            <div class="saved-build">
              <strong>{{ b.name }}</strong
              ><button class="text-button" (click)="addBuild(b.components)">
                Vérifier et ajouter au panier →
              </button>
              <button class="text-button" (click)="removeBuild(b.id)">
                Supprimer cette configuration
              </button>
            </div>
          } @empty {
            <p>Aucune configuration enregistrée.</p>
            <a routerLink="/configurateur">Composer mon premier PC →</a>
          }
        </section>
      </div>
      <div>
        <section class="panel">
          <h2>Mes adresses</h2>
          @for (a of api.user()?.addresses; track a.id) {
            <address>
              <strong>{{ a.name }}</strong
              ><br />{{ a.street }}<br />{{ a.postalCode }} {{ a.city }}
            </address>
            <button class="text-button" (click)="editAddress(a)">Modifier cette adresse</button>
            <button class="text-button" (click)="remove(a.id!)">Supprimer cette adresse</button>
          } @empty {
            <p>Aucune adresse enregistrée.</p>
          }
        </section>
        <form class="panel" [formGroup]="address" (ngSubmit)="saveAddress()">
          <h2>{{ editingAddress ? 'Modifier une adresse' : 'Ajouter une adresse' }}</h2>
          <label>Nom complet<input formControlName="name" autocomplete="name" /></label
          ><label>Adresse<input formControlName="street" autocomplete="street-address" /></label
          ><label
            >Code postal<input formControlName="postalCode" autocomplete="postal-code" /></label
          ><label>Ville<input formControlName="city" autocomplete="address-level2" /></label>
          <p>France métropolitaine continentale.</p>
          <button class="button primary" [disabled]="address.invalid">Enregistrer l’adresse</button>
          @if (editingAddress) {
            <button type="button" class="text-button" (click)="cancelAddress()">
              Annuler la modification
            </button>
          }
        </form>
      </div>
    </div>
  </section>`,
})
export class Account {
  api = inject(Api);
  fb = inject(FormBuilder);
  router = inject(Router);
  editingAddress: string | null = null;
  editAddress(address: Address) {
    this.editingAddress = address.id ?? null;
    this.address.patchValue(address);
  }
  cancelAddress() {
    this.editingAddress = null;
    this.address.reset();
  }
  async removeBuild(id: string) {
    try {
      await this.api.mutate('DELETE', '/me/configurations/' + id);
      await this.api.refreshUser();
      this.api.success('Configuration supprimée.');
    } catch (e) {
      this.api.fail(e);
    }
  }
  profile = this.fb.nonNullable.group({ name: [this.api.user()?.name ?? '', Validators.required] });
  password = this.fb.nonNullable.group({
    currentPassword: ['', Validators.required],
    password: ['', [Validators.required, Validators.minLength(12)]],
  });
  address = this.fb.nonNullable.group({
    name: ['', Validators.required],
    street: ['', Validators.required],
    postalCode: ['', [Validators.required, Validators.pattern(/^\d{5}$/)]],
    city: ['', Validators.required],
    country: 'FR',
  });
  async saveProfile() {
    try {
      await this.api.mutate('PATCH', '/me/profile', this.profile.getRawValue());
      await this.api.refreshUser();
      this.api.success('Profil enregistré.');
    } catch (e) {
      this.api.fail(e);
    }
  }
  async saveAddress() {
    try {
      await this.api.mutate(
        this.editingAddress ? 'PATCH' : 'POST',
        '/me/addresses' + (this.editingAddress ? '/' + this.editingAddress : ''),
        this.address.getRawValue(),
      );
      await this.api.refreshUser();
      this.cancelAddress();
      this.api.success('Adresse enregistrée.');
    } catch (e) {
      this.api.fail(e);
    }
  }
  async remove(id: string) {
    try {
      await this.api.mutate('DELETE', '/me/addresses/' + id);
      await this.api.refreshUser();
    } catch (e) {
      this.api.fail(e);
    }
  }
  async changePassword() {
    try {
      await this.api.mutate('POST', '/me/password', this.password.getRawValue());
      this.api.user.set(null);
      await this.router.navigate(['/connexion']);
      this.api.success('Mot de passe modifié. Reconnectez-vous.');
    } catch (e) {
      this.api.fail(e);
    }
  }
  async logout() {
    await this.api.logout();
    await this.router.navigate(['/']);
  }
  async addBuild(components: string[]) {
    try {
      this.api.cart.set(await this.api.mutate<Cart>('POST', '/cart/configuration', { components }));
      await this.router.navigate(['/panier']);
    } catch (e) {
      this.api.fail(e);
    }
  }
}
