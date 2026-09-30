import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { provideHttpClientTesting, HttpTestingController } from '@angular/common/http/testing';
import { Api } from './core/api';

describe('API et session', () => {
  let api: Api;
  let http: HttpTestingController;
  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    api = TestBed.inject(Api);
    http = TestBed.inject(HttpTestingController);
  });
  afterEach(() => http.verify());
  it('charge les prix du panier depuis le serveur', async () => {
    const promise = api.refreshCart();
    http
      .expectOne('/api/v1/cart')
      .flush({ items: [], subtotal: 10900, shipping: 990, total: 11890, valid: true });
    await promise;
    expect(api.cart()?.total).toBe(11890);
  });
  it('associe un jeton CSRF aux mutations', async () => {
    const promise = api.mutate('PUT', '/cart/items/example', { quantity: 2 });
    http.expectOne('/api/v1/auth/csrf').flush({ token: 'csrf-test' });
    await Promise.resolve();
    const request = http.expectOne('/api/v1/cart/items/example');
    expect(request.request.headers.get('X-CSRF-Token')).toBe('csrf-test');
    expect(request.request.body).toEqual({ quantity: 2 });
    request.flush({});
    await promise;
  });
  it('renouvelle le jeton après connexion', async () => {
    const login = api.mutate('POST', '/auth/login', {});
    http.expectOne('/api/v1/auth/csrf').flush({ token: 'before' });
    await Promise.resolve();
    http.expectOne('/api/v1/auth/login').flush({ authenticated: true });
    await login;
    const mutation = api.mutate('POST', '/me/addresses', {});
    http.expectOne('/api/v1/auth/csrf').flush({ token: 'after' });
    await Promise.resolve();
    const req = http.expectOne('/api/v1/me/addresses');
    expect(req.request.headers.get('X-CSRF-Token')).toBe('after');
    req.flush({});
    await mutation;
  });
  it('présente une erreur réseau lisible', async () => {
    const promise = api.refreshCart();
    http.expectOne('/api/v1/cart').error(new ProgressEvent('network'));
    await promise;
    expect(api.error()).toContain('Connexion au service impossible');
  });
  it('ne conserve pas de profil après une session expirée', async () => {
    const promise = api.refreshUser();
    http.expectOne('/api/v1/me').flush({}, { status: 401, statusText: 'Unauthorized' });
    await promise;
    expect(api.user()).toBeNull();
  });
});
