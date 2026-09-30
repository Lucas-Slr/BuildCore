import { Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
@Component({
  selector: 'bc-empty',
  imports: [RouterLink],
  template: `<section class="wrap empty page">
    <span class="eyebrow">{{ forbidden ? 'ACCÈS REFUSÉ' : '404 · HORS CIRCUIT' }}</span>
    <h1>{{ forbidden ? 'Cet espace est réservé.' : 'Cette page est introuvable.' }}</h1>
    <p>
      {{
        forbidden
          ? 'Votre compte ne permet pas d’accéder à cette page.'
          : 'Reprenons votre projet là où les composants vous attendent.'
      }}
    </p>
    <a routerLink="/" class="button primary">Retour à l’accueil</a>
  </section>`,
})
export class Empty {
  forbidden = inject(Router).url === '/acces-refuse';
}
