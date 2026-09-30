import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { Api } from '../core/api';
import { Page, Product } from '../core/models';
import { ProductCard } from '../core/product-card';
@Component({
  selector: 'bc-home',
  imports: [RouterLink, ProductCard],
  template: ` <section class="hero wrap">
      <div class="hero-copy">
        <span class="eyebrow"><span class="live-dot"></span> VOTRE PROCHAIN PC COMMENCE ICI</span>
        <h1>De bonnes pièces.<br />De grandes<br /><em>possibilités.</em></h1>
        <p>
          Gaming, création, travail. Trouvez les composants qui vous correspondent et construisez un
          PC qui vous ressemble.
        </p>
        <div class="button-row">
          <a class="button primary" routerLink="/configurateur"
            >Créer ma configuration <span>↗</span></a
          ><a class="text-link" routerLink="/catalogue">Explorer le catalogue →</a>
        </div>
        <div class="hero-proof">
          <span>✓ Compatibilité vérifiée</span><span>✓ Budget maîtrisé</span>
        </div>
      </div>
      <div class="hero-visual">
        <div class="visual-grid"></div>
        <span class="vertical-note">ENGINEERED FOR YOUR NEXT IDEA</span>
        <div class="hero-orbit"></div>
        <img
          class="hero-pc"
          src="/assets/components/case.svg"
          alt="Illustration originale du boîtier Orven Frame 01"
          width="400"
          height="320"
        />
        <div class="floating-label">
          <span class="live-dot"></span>
          <div>
            <strong>Votre vision. Votre machine.</strong><small>Chaque composant compte.</small>
          </div>
        </div>
        <div class="visual-index">01 / BUILD YOUR OWN</div>
      </div>
    </section>
    <section class="benefits wrap" aria-label="Les avantages">
      <div>
        <span>⌘</span>
        <p>
          <strong>Des composants qui s’accordent</strong
          ><small>Un moteur de compatibilité à chaque étape</small>
        </p>
      </div>
      <div>
        <span>↗</span>
        <p>
          <strong>À chaque projet, son PC</strong><small>Du premier montage à la workstation</small>
        </p>
      </div>
      <div>
        <span>◇</span>
        <p>
          <strong>La clarté, du début à la fin</strong
          ><small>Prix transparents et suivi de commande</small>
        </p>
      </div>
    </section>
    <section class="wrap section">
      <div class="section-heading">
        <div>
          <span class="eyebrow">LES FONDATIONS DE VOTRE SETUP</span>
          <h2>Chaque pièce a son rôle.</h2>
        </div>
        <a routerLink="/catalogue" class="text-link">Tout le catalogue ↗</a>
      </div>
      <div class="category-grid">
        @for (c of categories; track c.id) {
          <a routerLink="/catalogue" [queryParams]="{ category: c.id }" class="category-tile"
            ><img
              [src]="'/assets/components/' + c.id + '.svg'"
              alt=""
              width="140"
              height="112"
              loading="lazy"
            /><strong>{{ c.label }}</strong
            ><span>Explorer ↗</span></a
          >
        }
      </div>
    </section>
    <section class="wrap section">
      <div class="section-heading">
        <div>
          <span class="eyebrow">BIEN CHOISIR, BIEN COMMENCER</span>
          <h2>Les essentiels de votre prochain PC.</h2>
        </div>
        <a routerLink="/catalogue" class="text-link">Voir les composants ↗</a>
      </div>
      @if (loading()) {
        <p role="status">Chargement de la sélection…</p>
      }
      <div class="product-grid">
        @for (p of products(); track p.id) {
          <bc-product-card [product]="p" />
        }
      </div>
    </section>
    <section class="wrap builder-promo">
      <div>
        <span class="eyebrow">LE CONFIGURATEUR BUILDCORE</span>
        <h2>Votre PC, pièce par pièce.<br />La confiance en plus.</h2>
        <p>
          Choisissez vos composants. Nous vérifions qu’ils vont ensemble.<br />Vous gardez le
          contrôle, de la première pièce au budget final.
        </p>
        <a routerLink="/configurateur" class="button light">Commencer ma configuration ↗</a>
      </div>
      <ol>
        <li><span>01</span>Votre usage, votre budget</li>
        <li><span>02</span>Les composants qui vous conviennent</li>
        <li><span>03</span>Une configuration vérifiée</li>
      </ol>
    </section>
    <section class="wrap section">
      <div class="section-heading">
        <div>
          <span class="eyebrow">À CHAQUE AMBITION SON ÉQUIPEMENT</span>
          <h2>Qu’allez-vous créer ?</h2>
        </div>
      </div>
      <div class="usage-grid">
        @for (u of usages; track u.id) {
          <a routerLink="/configurateur" [queryParams]="{ usage: u.id }" class="usage-card"
            ><span>{{ u.icon }}</span>
            <h3>{{ u.title }}</h3>
            <p>{{ u.text }}</p>
            <strong>Composer mon PC ↗</strong></a
          >
        }
      </div>
    </section>`,
})
export class Home {
  api = inject(Api);
  products = signal<Product[]>([]);
  loading = signal(true);
  categories = [
    { id: 'cpu', label: 'Processeurs' },
    { id: 'gpu', label: 'Cartes graphiques' },
    { id: 'motherboard', label: 'Cartes mères' },
    { id: 'memory', label: 'Mémoire' },
    { id: 'storage', label: 'Stockage' },
    { id: 'case', label: 'Boîtiers' },
  ];
  usages = [
    {
      id: 'gaming',
      icon: '⌘',
      title: 'Jouer sans compromis',
      text: 'Une configuration équilibrée pour vos univers préférés.',
    },
    {
      id: 'creation',
      icon: '◈',
      title: 'Donner vie à vos idées',
      text: 'Photo, vidéo, 3D. Faites de la place à votre créativité.',
    },
    {
      id: 'workstation',
      icon: '▤',
      title: 'Aller au bout de vos projets',
      text: 'Une base fiable pour vos outils et votre quotidien professionnel.',
    },
  ];
  constructor() {
    void this.api
      .get<Page<Product>>('/products?sort=price_desc')
      .then((p) => this.products.set(p.items.filter((x) => x.category !== 'prebuilt').slice(0, 4)))
      .catch((e) => this.api.fail(e))
      .finally(() => this.loading.set(false));
  }
}
