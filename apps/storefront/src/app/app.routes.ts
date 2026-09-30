import { inject } from '@angular/core';
import { CanActivateFn, Router, Routes } from '@angular/router';
import { Api } from './core/api';
const customer: CanActivateFn = async () => {
  const api = inject(Api),
    router = inject(Router);
  await api.refreshUser();
  return !!api.user() || router.parseUrl('/connexion');
};
const admin: CanActivateFn = async () => {
  const api = inject(Api),
    router = inject(Router);
  await api.refreshUser();
  return !!api.user()?.roles.includes('ROLE_ADMIN') || router.parseUrl('/acces-refuse');
};
export const routes: Routes = [
  { path: '', loadComponent: () => import('./pages/home').then((m) => m.Home) },
  { path: 'catalogue', loadComponent: () => import('./pages/catalog').then((m) => m.Catalog) },
  {
    path: 'produits/:id',
    loadComponent: () => import('./pages/product').then((m) => m.ProductPage),
  },
  { path: 'configurateur', loadComponent: () => import('./pages/builder').then((m) => m.Builder) },
  { path: 'panier', loadComponent: () => import('./pages/cart').then((m) => m.CartPage) },
  { path: 'connexion', loadComponent: () => import('./pages/auth').then((m) => m.Auth) },
  { path: 'inscription', loadComponent: () => import('./pages/auth').then((m) => m.Auth) },
  {
    path: 'commande',
    canActivate: [customer],
    loadComponent: () => import('./pages/checkout').then((m) => m.Checkout),
  },
  {
    path: 'compte',
    canActivate: [customer],
    loadComponent: () => import('./pages/account').then((m) => m.Account),
  },
  {
    path: 'commandes',
    canActivate: [customer],
    loadComponent: () => import('./pages/orders').then((m) => m.Orders),
  },
  {
    path: 'commandes/:id',
    canActivate: [customer],
    loadComponent: () => import('./pages/orders').then((m) => m.Orders),
  },
  {
    path: 'paiement/retour',
    canActivate: [customer],
    loadComponent: () => import('./pages/orders').then((m) => m.Orders),
  },
  {
    path: 'admin',
    canActivate: [admin],
    loadComponent: () => import('./pages/admin').then((m) => m.Admin),
  },
  { path: 'acces-refuse', loadComponent: () => import('./pages/empty').then((m) => m.Empty) },
  { path: '**', loadComponent: () => import('./pages/empty').then((m) => m.Empty) },
];
