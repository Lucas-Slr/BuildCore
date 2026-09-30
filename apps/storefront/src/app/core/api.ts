import { inject, Injectable, signal } from '@angular/core';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { Cart, User } from './models';
@Injectable({ providedIn: 'root' })
export class Api {
  private http = inject(HttpClient);
  private csrf?: string;
  user = signal<User | null>(null);
  cart = signal<Cart | null>(null);
  notice = signal('');
  error = signal('');
  get<T>(path: string) {
    return firstValueFrom(this.http.get<T>('/api/v1' + path));
  }
  async mutate<T>(
    method: string,
    path: string,
    body: unknown = {},
    extra: Record<string, string> = {},
  ): Promise<T> {
    if (!this.csrf) this.csrf = (await this.get<{ token: string }>('/auth/csrf')).token;
    try {
      const result = await firstValueFrom(
        this.http.request<T>(method, '/api/v1' + path, {
          body,
          headers: { 'X-CSRF-Token': this.csrf, ...extra },
        }),
      );
      if (path === '/auth/login') this.csrf = undefined;
      return result;
    } catch (e) {
      if (e instanceof HttpErrorResponse && e.error?.error?.code === 'CSRF_INVALID')
        this.csrf = undefined;
      throw e;
    }
  }
  async init() {
    await Promise.all([this.refreshUser(), this.refreshCart()]);
  }
  async refreshUser() {
    try {
      this.user.set(await this.get<User>('/me'));
    } catch {
      this.user.set(null);
    }
  }
  async refreshCart() {
    try {
      this.cart.set(await this.get<Cart>('/cart'));
    } catch (e) {
      this.fail(e);
    }
  }
  async add(id: string, quantity = 1) {
    const current = this.cart()?.items.find((x) => x.id === id)?.quantity ?? 0;
    try {
      this.cart.set(
        await this.mutate<Cart>('PUT', '/cart/items/' + id, { quantity: current + quantity }),
      );
      this.success('Produit ajouté au panier.');
    } catch (e) {
      this.fail(e);
    }
  }
  async logout() {
    try {
      await this.mutate('POST', '/auth/logout');
      this.csrf = undefined;
      this.user.set(null);
      await this.refreshCart();
    } catch (e) {
      this.fail(e);
    }
  }
  success(message: string) {
    this.error.set('');
    this.notice.set(message);
  }
  fail(e: unknown) {
    this.notice.set('');
    this.error.set(
      e instanceof HttpErrorResponse
        ? (e.error?.error?.message ?? 'Connexion au service impossible. Réessayez dans un instant.')
        : 'Une erreur est survenue. Réessayez.',
    );
  }
}
