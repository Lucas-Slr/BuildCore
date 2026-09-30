import { request, chromium, expect } from '../apps/storefront/node_modules/@playwright/test/index.mjs';
import { execFileSync } from 'node:child_process';
import assert from 'node:assert/strict';
const php = process.env.BUILDCORE_PHP || '.tools/php84/php.exe';
const fixture = JSON.parse(execFileSync(php, ['scripts/service-fixture.php'], { encoding: 'utf8' }));
const baseURL = 'http://127.0.0.1:4200';
async function client(user) {
  const api = await request.newContext({ baseURL });
  let token = (await (await api.get('/api/v1/auth/csrf')).json()).token;
  const login = await api.post('/api/v1/auth/login', { headers: { 'X-CSRF-Token': token }, data: { email: `${user}@buildcore.test`, password: 'BuildCore-Demo-2026!' } });
  assert.equal(login.status(), 200);
  token = (await (await api.get('/api/v1/auth/csrf')).json()).token;
  return { api, headers: { 'X-CSRF-Token': token } };
}
const alice = await client('alice');
const admin = await client('admin');
const thomas = await client('thomas');
const browser = await chromium.launch({ executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE });
const context = await browser.newContext({ storageState: await alice.api.storageState() });
const page = await context.newPage();
const path = `/api/v1/orders/${fixture.id}/subscription`;
try {
  assert.equal((await thomas.api.post(path, { headers: thomas.headers, data: {} })).status(), 403);
  const subscription = await alice.api.post(path, { headers: alice.headers, data: {} });
  assert.equal(subscription.status(), 200, await subscription.text());
  const { topic } = await subscription.json();
  const cookies = (await alice.api.storageState()).cookies;
  const cookie = cookies.find((item) => item.name === 'mercureAuthorization');
  assert.ok(cookie, 'Le cookie privé doit être enregistré');
  const claims = JSON.parse(Buffer.from(cookie.value.split('.')[1], 'base64url').toString());
  assert.deepEqual(claims.mercure.subscribe, [topic]);
  assert.equal(claims.mercure.publish, undefined);
  let interrupted = false;
  await page.route('**/.well-known/mercure?*', async (route) => {
    if (!interrupted) { interrupted = true; await route.abort(); }
    else await route.continue();
  });
  await page.goto(baseURL + '/commandes/' + fixture.id);
  await expect(page.getByText('Suivi en direct connecté.')).toBeVisible({ timeout: 15000 });
  assert.ok(interrupted, 'La reconnexion doit survivre à un premier abonnement interrompu');
  const hub = 'http://127.0.0.1:3000/.well-known/mercure?topic=' + encodeURIComponent(topic);
  assert.equal((await fetch(hub)).status, 401, 'Abonnement anonyme refusé');
  const abort = new AbortController();
  const timer = setTimeout(() => abort.abort(), 20000);
  try {
    const stream = await fetch(hub, { headers: { Cookie: `${cookie.name}=${cookie.value}` }, signal: abort.signal });
    assert.equal(stream.status, 200);
    const transition = await admin.api.post(`/api/v1/admin/orders/${fixture.id}/transition`, { headers: admin.headers, data: { transition: 'prepare' } });
    assert.equal(transition.status(), 200, await transition.text());
    const env = { ...process.env, MAILER_DSN: 'smtp://127.0.0.1:1025' };
    execFileSync(php, ['apps/api/bin/console', 'messenger:consume', 'emails', 'realtime', '--time-limit=3', '--no-interaction'], { env, stdio: 'pipe' });
    const reader = stream.body.getReader();
    let received = '';
    while (!received.includes('PREPARING')) {
      const { done, value } = await reader.read();
      assert.ok(!done, 'Le flux doit contenir la transition');
      received += new TextDecoder().decode(value);
    }
    await reader.cancel();
    assert.ok(received.includes(fixture.id));
    await expect(page.locator('.status-badge')).toHaveText('En préparation');
    execFileSync(php, ['scripts/service-fixture.php', fixture.id], { env, stdio: 'pipe' });
    execFileSync(php, ['apps/api/bin/console', 'messenger:consume', 'emails', 'realtime', '--time-limit=3', '--no-interaction'], { env, stdio: 'pipe' });
    const mail = await (await fetch('http://127.0.0.1:8025/api/v1/search?query=' + encodeURIComponent('subject:' + fixture.number))).json();
    assert.equal(mail.messages.length, 1, 'Un seul email malgré un événement dupliqué');
    console.log(JSON.stringify({ order: fixture.number, privateSubscription: 'passed', unauthorizedSubscription: 'passed', browserReconnection: 'passed', realtimeTransition: 'passed', smtpAndDeduplication: 'passed' }, null, 2));
  } finally { clearTimeout(timer); abort.abort(); }
} finally { await browser.close(); await Promise.all([alice.api.dispose(), admin.api.dispose(), thomas.api.dispose()]); }
