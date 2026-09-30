import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

test('catalogue, filtres URL et fiche produit', async ({ page }) => {
  await page.goto('/catalogue');
  await page.getByRole('combobox', { name: 'Catégorie', exact: true }).selectOption('cpu');
  await page.getByRole('button', { name: 'Appliquer les filtres' }).click();
  await expect(page).toHaveURL(/category=cpu/);
  await expect(page.locator('bc-product-card')).toHaveCount(3);
  await page.getByRole('link', { name: 'Novea Core 8', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Novea Core 8', exact: true })).toBeVisible();
  await expect(page.getByText('BC-CPU-1')).toBeVisible();
});
test('configuration compatible puis incompatible et ajout groupé', async ({ page }) => {
  await page.goto('/configurateur');
  const parts: Record<string, string> = {
    cpu: 'Novea Core 8',
    motherboard: 'Novea Base B650',
    memory: 'Velora Flow 32 Go',
    gpu: 'Altis Arc G70',
    storage: 'Velora Sprint 1 To',
    psu: 'Orven Pulse 750',
    case: 'Orven Frame 01',
    cooler: 'Orven Air 150',
  };
  for (const [category, name] of Object.entries(parts)) {
    const select = page.locator('#part-' + category);
    const value = await select.locator('option').filter({ hasText: name }).getAttribute('value');
    await select.selectOption(value!);
  }
  await expect(page.getByText('Configuration compatible selon')).toBeVisible();
  const cpu = page.locator('#part-cpu');
  await page.getByLabel('Afficher aussi les options incompatibles').check();
  await cpu.selectOption(
    (await cpu.locator('option').filter({ hasText: 'Altis Compute 6' }).getAttribute('value'))!,
  );
  await expect(
    page.getByText('Le processeur et la carte mère utilisent des sockets différents.'),
  ).toBeVisible();
  await expect(
    page.getByRole('button', { name: 'Ajouter la configuration au panier' }),
  ).toBeDisabled();
  await cpu.selectOption(
    (await cpu.locator('option').filter({ hasText: 'Novea Core 8' }).getAttribute('value'))!,
  );
  await page.getByRole('button', { name: 'Ajouter la configuration au panier' }).click();
  await expect(page).toHaveURL(/panier/);
  await expect(page.locator('.cart-row')).toHaveCount(8);
  await page.getByRole('link', { name: 'Modifier cette configuration' }).click();
  await expect(page.getByText('Configuration compatible selon')).toBeVisible();
  await page.getByLabel('Nom de la configuration').fill('PC révisé');
  await page.getByRole('button', { name: 'Enregistrer les modifications du panier' }).click();
  await expect(page.getByRole('heading', { name: 'PC révisé' })).toBeVisible();
  await expect(page.locator('.cart-row')).toHaveCount(8);
  await page.getByRole('button', { name: 'Retirer cette configuration' }).click();
  await expect(page.getByText('Tout commence par une première pièce.')).toBeVisible();
});
test('connexion, adresse et paiement non configuré explicite', async ({ page }) => {
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill('alice@buildcore.test');
  await page.getByLabel('Mot de passe', { exact: true }).fill('BuildCore-Demo-2026!');
  await page.getByRole('button', { name: 'Me connecter' }).click();
  await expect(page).toHaveURL(/compte/);
  const { token } = await (await page.request.get('/api/v1/auth/csrf')).json();
  const cart = await (await page.request.get('/api/v1/cart')).json();
  for (const item of cart.items) {
    await page.request.delete('/api/v1/cart/items/' + item.id, {
      headers: { 'X-CSRF-Token': token },
    });
  }
  await page.goto('/catalogue?category=cpu');
  await page.getByRole('button', { name: 'Ajouter Novea Core 8 au panier' }).click();
  await expect(
    page.getByRole('status').filter({ hasText: 'Produit ajouté au panier.' }),
  ).toBeVisible();
  await page.goto('/commande');
  await page.getByLabel('Je comprends').check();
  await page.getByRole('button', { name: 'Continuer vers Stripe' }).click();
  await expect(page.getByRole('alert')).toContainText('Stripe test n’est pas encore configuré.');
  await page.route('**/api/v1/checkout', (route) =>
    route.fulfill({
      status: 409,
      json: {
        error: {
          code: 'QUOTE_CHANGED',
          message: 'Le panier a changé. Vérifiez les nouveaux prix et confirmez à nouveau.',
        },
      },
    }),
  );
  await page.getByRole('button', { name: 'Continuer vers Stripe' }).click();
  await expect(page.getByLabel('Je comprends')).not.toBeChecked();
  await expect(page.getByRole('alert')).toContainText('Le panier a changé');
});
test('administration protégée', async ({ page }) => {
  await page.goto('/admin');
  await expect(page.getByRole('heading', { name: 'Cet espace est réservé.' })).toBeVisible();
});
test('mobile sans débordement et navigation clavier', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('/');
  await expect(page.getByRole('heading', { name: /De bonnes pièces/ })).toBeVisible();
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(
    true,
  );
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Aller au contenu' })).toBeFocused();
});
test('état erreur réseau du catalogue', async ({ page }) => {
  await page.route('**/api/v1/products*', (route) => route.abort());
  await page.goto('/catalogue');
  await expect(page.getByRole('alert')).toContainText('Connexion au service impossible');
});

test('accessibilité principale accueil et configurateur', async ({ page }) => {
  for (const path of ['/', '/configurateur']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21aa'])
      .analyze();
    expect(results.violations).toEqual([]);
  }
});

test('administrateur connecté et suivi client de démonstration', async ({ page }) => {
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill('admin@buildcore.test');
  await page.getByLabel('Mot de passe', { exact: true }).fill('BuildCore-Demo-2026!');
  await page.getByRole('button', { name: 'Me connecter' }).click();
  await expect(page).toHaveURL(/compte/);
  await page.getByRole('link', { name: 'Administration', exact: true }).click();
  await expect(page.getByRole('heading', { name: 'Le poste de pilotage.' })).toBeVisible();
  await page.getByRole('button', { name: 'Stock', exact: true }).click();
  await expect(page.getByRole('caption')).toContainText('Stock disponible et ajustements');
  await page.getByRole('button', { name: 'Commandes', exact: true }).click();
  await page.getByLabel('Numéro, email ou suivi').fill('DEMO-PAID');
  await page.getByRole('button', { name: 'Rechercher', exact: true }).click();
  await expect(page.getByText('1 commande(s)', { exact: true })).toBeVisible();
  await expect(page.getByRole('link', { name: 'DEMO-PAID', exact: true })).toBeVisible();
});

test('adresse créée, modifiée et supprimée depuis le compte', async ({ page }) => {
  await page.goto('/connexion');
  await page.getByLabel('Adresse email').fill('alice@buildcore.test');
  await page.getByLabel('Mot de passe', { exact: true }).fill('BuildCore-Demo-2026!');
  await page.getByRole('button', { name: 'Me connecter' }).click();
  await expect(page).toHaveURL(/compte/);
  await page.getByLabel('Nom complet').fill('Adresse Playwright');
  await page.getByLabel('Adresse', { exact: true }).fill('42 rue des Tests');
  await page.getByLabel('Code postal').fill('69002');
  await page.getByLabel('Ville', { exact: true }).fill('Lyon');
  await page.getByRole('button', { name: 'Enregistrer l’adresse' }).click();
  const address = page.locator('address').filter({ hasText: 'Adresse Playwright' });
  await expect(address).toContainText('Lyon');
  await page.getByRole('button', { name: 'Modifier cette adresse' }).last().click();
  await page.getByLabel('Code postal').fill('75001');
  await page.getByLabel('Ville', { exact: true }).fill('Paris');
  await page.getByRole('button', { name: 'Enregistrer l’adresse' }).click();
  await expect(address).toContainText('Paris');
  await page.getByRole('button', { name: 'Supprimer cette adresse' }).last().click();
  await expect(address).toHaveCount(0);
});
