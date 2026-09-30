import { Component, inject, computed } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Api } from './core/api';
import { BRAND } from './core/brand';
@Component({
  selector: 'app-root',
  imports: [RouterLink, RouterLinkActive, RouterOutlet],
  templateUrl: './app.html',
})
export class App {
  api = inject(Api);
  brand = BRAND;
  count = computed(() => this.api.cart()?.items.reduce((sum, x) => sum + x.quantity, 0) ?? 0);
  constructor() {
    void this.api.init();
  }
}
