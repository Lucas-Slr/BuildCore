import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Api } from '../core/api';
@Component({
  selector: 'bc-auth',
  imports: [ReactiveFormsModule, RouterLink],
  template: `<section class="auth-page wrap">
    <div class="auth-story">
      <span class="eyebrow">VOTRE ESPACE BUILDCORE</span>
      <h1>Vos idées.<br />Vos composants.<br /><em>Votre espace.</em></h1>
      <p>Retrouvez vos configurations et suivez chaque étape de vos commandes.</p>
      <img src="/assets/components/cpu.svg" alt="" width="300" height="240" />
    </div>
    <form class="panel auth-form" [formGroup]="form" (ngSubmit)="submit()">
      <span class="eyebrow">BIENVENUE</span>
      <h2>{{ register ? 'Créer mon compte' : 'Content de vous retrouver.' }}</h2>
      @if (register) {
        <label>Votre nom<input formControlName="name" autocomplete="name" /></label>
      }
      <label
        >Adresse email<input
          type="email"
          formControlName="email"
          autocomplete="email"
          required /></label
      ><label
        >Mot de passe<input
          type="password"
          formControlName="password"
          [autocomplete]="register ? 'new-password' : 'current-password'"
          required
          [attr.aria-describedby]="register ? 'password-help' : null"
      /></label>
      @if (register) {
        <small id="password-help">12 caractères minimum. Utilisez un mot de passe unique.</small>
      }
      @if (submitted() && form.invalid) {
        <p class="field-error" role="alert">Vérifiez votre email et les champs obligatoires.</p>
      }
      <button class="button primary full" [disabled]="busy()" type="submit">
        {{ busy() ? 'Un instant…' : register ? 'Créer mon compte' : 'Me connecter' }} →
      </button>
      <p>
        {{ register ? 'Déjà un compte ?' : 'Pas encore de compte ?' }}
        <a [routerLink]="register ? '/connexion' : '/inscription'">{{
          register ? 'Se connecter' : 'S’inscrire'
        }}</a>
      </p>
      <div class="note">
        Démonstration : alice&#64;buildcore.test<br />Mot de passe : BuildCore-Demo-2026!
      </div>
    </form>
  </section>`,
})
export class Auth {
  api = inject(Api);
  router = inject(Router);
  fb = inject(FormBuilder);
  register = this.router.url.startsWith('/inscription');
  busy = signal(false);
  submitted = signal(false);
  form = this.fb.nonNullable.group({
    name: ['', this.register ? [Validators.required, Validators.minLength(2)] : []],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(this.register ? 12 : 1)]],
  });
  async submit() {
    this.submitted.set(true);
    if (this.form.invalid) return;
    this.busy.set(true);
    try {
      await this.api.mutate(
        'POST',
        this.register ? '/auth/register' : '/auth/login',
        this.form.getRawValue(),
      );
      if (this.register) {
        this.api.success('Inscription prise en compte. Vous pouvez vous connecter.');
        await this.router.navigate(['/connexion']);
      } else {
        await this.api.init();
        await this.router.navigate(['/compte']);
      }
    } catch (e) {
      this.api.fail(e);
    } finally {
      this.busy.set(false);
    }
  }
}
