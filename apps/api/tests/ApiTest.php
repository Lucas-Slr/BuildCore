<?php

declare(strict_types=1);

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use App\Catalog\Variant;
use App\Customer\Customer;
use App\Order\Purchase;
use Doctrine\ORM\EntityManagerInterface;

final class ApiTest extends WebTestCase
{
    private KernelBrowser $browser;
    private string $csrf;
    protected function setUp(): void
    {
        $this->browser = static::createClient();
        $this->browser->request('GET', '/api/v1/auth/csrf');
        $this->csrf = $this->data()['token'];
    }
    private function data(): array
    {
        return json_decode($this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
    private function send(string $method, string $url, array $body = []): void
    {
        $this->browser->request($method, $url, [], [], ['CONTENT_TYPE' => 'application/json','HTTP_X_CSRF_TOKEN' => $this->csrf], json_encode($body, JSON_THROW_ON_ERROR));
    }
    private function login(string $name = 'alice'): void
    {
        $this->send('POST', '/api/v1/auth/login', ['email' => $name.'@buildcore.test','password' => 'BuildCore-Demo-2026!']);
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/api/v1/auth/csrf');
        $this->csrf = $this->data()['token'];
    }
    public function testAddressEditionIsScopedToOwner(): void
    {
        $this->login();
        $address = ['name' => 'Alice Test', 'street' => '10 rue du Test', 'city' => 'Lyon', 'postalCode' => '69002', 'country' => 'FR'];
        $this->send('POST', '/api/v1/me/addresses', $address);
        self::assertResponseIsSuccessful();
        $id = array_slice($this->data()['addresses'], -1)[0]['id'];
        $address['city'] = 'Paris';
        $address['postalCode'] = '75001';
        $this->send('PATCH', '/api/v1/me/addresses/'.$id, $address);
        self::assertResponseIsSuccessful();
        self::assertSame('Paris', array_slice($this->data()['addresses'], -1)[0]['city']);
        $this->login('thomas');
        $this->send('PATCH', '/api/v1/me/addresses/'.$id, $address);
        self::assertResponseStatusCodeSame(404);
        $this->login();
        $this->send('DELETE', '/api/v1/me/addresses/'.$id);
        self::assertResponseIsSuccessful();
    }
    public function testSavedConfigurationDeletionIsScopedToOwner(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $alice = $em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
        $alice->builds[] = ['id' => 'delete-test', 'name' => 'Test suppression', 'components' => []];
        $em->flush();
        $this->login('thomas');
        $this->send('DELETE', '/api/v1/me/configurations/delete-test');
        self::assertResponseIsSuccessful();
        $this->login();
        $this->browser->request('GET', '/api/v1/me');
        self::assertContains('delete-test', array_column($this->data()['builds'], 'id'));
        $this->send('DELETE', '/api/v1/me/configurations/delete-test');
        self::assertNotContains('delete-test', array_column($this->data()['builds'], 'id'));
    }
    public function testAdminOrderSearchAndDates(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $alice = $em->getRepository(Customer::class)->findOneBy(['email' => 'alice@buildcore.test']);
        $order = new Purchase($alice, 'search-'.bin2hex(random_bytes(10)));
        $order->tracking = 'SEARCH-'.bin2hex(random_bytes(10));
        $em->persist($order);
        $em->flush();
        $this->login('admin');
        $this->browser->request('GET', '/api/v1/admin/orders?q='.$order->tracking.'&status=PENDING_PAYMENT&from='.date('Y-m-d').'&to='.date('Y-m-d'));
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->data()['total']);
        self::assertSame($order->id, $this->data()['items'][0]['id']);
        $this->browser->request('GET', '/api/v1/admin/orders?from=2026-02-30');
        self::assertResponseStatusCodeSame(422);
    }
    public function testCartGroupReplacementAndRemovalPreserveSeparateUnits(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $ids = [];
        foreach (['BC-CPU-1', 'BC-MOTHERBOARD-4', 'BC-MEMORY-6', 'BC-GPU-9', 'BC-STORAGE-17', 'BC-PSU-12', 'BC-CASE-15', 'BC-COOLER-19'] as $sku) {
            $variant = $em->getRepository(Variant::class)->findOneBy(['sku' => $sku]);
            self::assertNotNull($variant, $sku);
            $variant->physical = 20;
            $variant->reserved = 0;
            $ids[] = $variant->id;
        }
        $em->flush();
        $this->send('PUT', '/api/v1/cart/items/'.$ids[0], ['quantity' => 1]);
        $this->send('POST', '/api/v1/cart/configuration', ['components' => $ids, 'name' => 'Mon PC']);
        self::assertResponseIsSuccessful();
        $group = $this->data()['groups'][0];
        $this->send('PUT', '/api/v1/cart/configurations/'.$group['id'], ['components' => $ids, 'name' => 'PC modifié']);
        self::assertResponseIsSuccessful();
        self::assertSame('PC modifié', $this->data()['groups'][0]['name']);
        self::assertSame(2, $this->data()['items'][0]['quantity']);
        $this->send('DELETE', '/api/v1/cart/configurations/'.$group['id']);
        self::assertSame([], $this->data()['groups']);
        self::assertCount(1, $this->data()['items']);
        self::assertSame(1, $this->data()['items'][0]['quantity']);
    }
    public function testCataloguePaginationAndFilters(): void
    {
        $this->browser->request('GET', '/api/v1/products?category=cpu&brand=Novea&sort=price_desc');
        self::assertResponseIsSuccessful();
        $d = $this->data();
        self::assertSame(2, $d['total']);
        self::assertSame('Novea Core 12', $d['items'][0]['name']);
        $this->browser->request('GET', '/api/v1/products?page=2');
        self::assertCount(12, $this->data()['items']);
    }
    public function testGuestCartQuantityStockAndPrice(): void
    {
        $this->browser->request('GET', '/api/v1/products?category=cpu');
        $p = $this->data()['items'][0];
        $this->send('PUT', '/api/v1/cart/items/'.$p['id'], ['quantity' => 2,'price' => 1]);
        self::assertResponseIsSuccessful();
        self::assertSame($p['price'] * 2, $this->data()['subtotal']);
        $this->send('PUT', '/api/v1/cart/items/'.$p['id'], ['quantity' => 11]);
        self::assertResponseStatusCodeSame(422);
        $this->send('DELETE', '/api/v1/cart/items/'.$p['id']);
        self::assertSame([], $this->data()['items']);
        $this->browser->request('GET', '/api/v1/products?q=Altis%20Arc%20G50');
        $id = $this->data()['items'][0]['id'];
        $this->send('PUT', '/api/v1/cart/items/'.$id, ['quantity' => 1]);
        self::assertResponseStatusCodeSame(409);
    }
    public function testCsrfRequiredForMutationsAndLogin(): void
    {
        $this->browser->request('POST', '/api/v1/auth/login', [], [], ['CONTENT_TYPE' => 'application/json'], '{"email":"alice@buildcore.test","password":"BuildCore-Demo-2026!"}');
        self::assertResponseStatusCodeSame(403);
        self::assertSame('CSRF_INVALID', $this->data()['error']['code']);
    }
    public function testAuthenticationAndAdminAuthorization(): void
    {
        $this->browser->request('GET', '/api/v1/me');
        self::assertResponseStatusCodeSame(401);
        $this->login();
        $this->browser->request('GET', '/api/v1/me');
        self::assertSame('alice@buildcore.test', $this->data()['email']);
        $this->browser->request('GET', '/api/v1/admin/dashboard');
        self::assertResponseStatusCodeSame(403);
    }
    public function testGuestMergeIsDeterministic(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(Customer::class)->findOneBy(['email' => 'thomas@buildcore.test']);
        $v = $em->getRepository(Variant::class)->findOneBy(['sku' => 'BC-CPU-1']);
        $user->cart = [$v->id => 2];
        $em->flush();
        $this->send('PUT', '/api/v1/cart/items/'.$v->id, ['quantity' => 3]);
        $this->login('thomas');
        $this->browser->request('GET', '/api/v1/cart');
        self::assertSame(5, $this->data()['items'][0]['quantity']);
    }
    public function testOwnerVoterPreventsOtherCustomerAccess(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $u = $em->getRepository(Customer::class)->findOneBy(['email' => 'thomas@buildcore.test']);
        $o = new Purchase($u, 'test-owner-'.bin2hex(random_bytes(6)));
        $em->persist($o);
        $em->flush();
        $id = $o->id;
        $this->login();
        $this->browser->request('GET', '/api/v1/orders/'.$id);
        self::assertResponseStatusCodeSame(403);
    }
    public function testInvalidWebhookRejected(): void
    {
        $this->browser->request('POST', '/api/v1/payments/webhook', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        self::assertResponseStatusCodeSame(503);
    }
    public function testCheckoutCannotPretendPaymentWhenStripeMissing(): void
    {
        $this->login();
        $this->send('POST', '/api/v1/checkout', ['total' => 1]);
        self::assertResponseStatusCodeSame(422);
    }
    public function testAdminStockAuditAndReservedInvariant(): void
    {
        $this->login('admin');
        $this->browser->request('GET', '/api/v1/products?category=cpu');
        $id = $this->data()['items'][0]['id'];
        $this->send('POST', '/api/v1/admin/stock/'.$id, ['quantity' => 20,'reason' => 'Réapprovisionnement de test']);
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/api/v1/admin/stock/'.$id.'/history');
        self::assertNotEmpty($this->data()['items']);
    }
    public function testConfigurationIsValidatedBeforeCart(): void
    {
        $this->send('POST', '/api/v1/cart/configuration', ['components' => []]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame('BUILD_INCOMPATIBLE', $this->data()['error']['code']);
    }
    public function testProductLifecycleAndUniqueSku(): void
    {
        $this->login('admin');
        $suffix = bin2hex(random_bytes(5));
        $data = ['name' => 'Test Mouse','slug' => 'test-mouse-'.$suffix,'brand' => 'Fictive','category' => 'mouse','summary' => 'Souris de test','description' => 'Produit fictif de validation','status' => 'DRAFT','sku' => 'TEST-'.strtoupper($suffix),'price' => 1990,'specs' => []];
        $this->send('POST', '/api/v1/admin/products', $data);
        self::assertResponseStatusCodeSame(201);
        $id = $this->data()['id'];
        $this->browser->request('GET', '/api/v1/products/'.$id);
        self::assertResponseStatusCodeSame(404);
        $data['status'] = 'PUBLISHED';
        $this->send('PUT', '/api/v1/admin/products/'.$id, $data);
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/api/v1/products/'.$id);
        self::assertResponseIsSuccessful();
        $data['slug'] .= '-duplicate';
        $this->send('POST', '/api/v1/admin/products', $data);
        self::assertResponseStatusCodeSame(409);
        $data['status'] = 'ARCHIVED';
        $this->send('PUT', '/api/v1/admin/products/'.$id, $data);
        self::assertResponseIsSuccessful();
        $this->browser->request('GET', '/api/v1/products/'.$id);
        self::assertResponseStatusCodeSame(404);
    }
    public function testTechnicalFiltersAreScopedToCategory(): void
    {
        $this->browser->request('GET', '/api/v1/products?category=cpu&specKey=socket&specValue=NC5');
        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->data()['total']);
        $this->browser->request('GET', '/api/v1/products?category=cpu&specKey=password&specValue=test');
        self::assertResponseStatusCodeSame(422);
    }
    public function testOpenApiIsAvailable(): void
    {
        $this->browser->request('GET', '/api/v1/openapi.json');
        self::assertResponseIsSuccessful();
        self::assertSame('3.0.3', $this->data()['openapi']);
        self::assertArrayHasKey('/api/v1/checkout', $this->data()['paths']);
    }
    public function testImageUploadOrderingAndUnsafeFormatRejection(): void
    {
        $this->login('admin');
        $this->browser->request('GET', '/api/v1/products?category=cpu');
        $id = $this->data()['items'][0]['id'];
        $path = dirname(__DIR__).'/var/upload-test.png';
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($path, 'image.png', 'image/png', null, true);
        $this->browser->request('POST', '/api/v1/admin/products/'.$id.'/images', ['alt' => 'Illustration de test'], ['image' => $file], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(201);
        $images = $this->data()['images'];
        $image = end($images);
        $this->send('PATCH', '/api/v1/admin/products/'.$id.'/images/'.$image['id'], ['position' => 0,'alt' => 'Image principale']);
        self::assertSame($image['id'], $this->data()['images'][0]['id']);
        $this->send('DELETE', '/api/v1/admin/products/'.$id.'/images/'.$image['id']);
        self::assertResponseIsSuccessful();
        $bad = dirname(__DIR__).'/var/upload-test.svg';
        file_put_contents($bad, '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $file = new \Symfony\Component\HttpFoundation\File\UploadedFile($bad, 'unsafe.svg', 'image/svg+xml', null, true);
        $this->browser->request('POST', '/api/v1/admin/products/'.$id.'/images', ['alt' => 'Invalide'], ['image' => $file], ['HTTP_X_CSRF_TOKEN' => $this->csrf]);
        self::assertResponseStatusCodeSame(422);
        unlink($bad);
    }
}
