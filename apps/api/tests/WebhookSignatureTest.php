<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Stripe\Webhook;
use Stripe\Exception\SignatureVerificationException;

final class WebhookSignatureTest extends TestCase
{
    public function testValidSignedEventAndTampering(): void
    {
        $body = '{"id":"evt_test_signature","type":"checkout.session.completed","livemode":false,"data":{"object":{}}}';
        $time = time();
        $secret = 'whsec_unit_fixture';
        $header = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, $secret);
        self::assertSame('evt_test_signature', Webhook::constructEvent($body, $header, $secret)->id);
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($body.' ', $header, $secret);
    }
    public function testOldSignatureRejected(): void
    {
        $time = time() - 600;
        $body = '{}';
        $secret = 'whsec_unit_fixture';
        $header = 't='.$time.',v1='.hash_hmac('sha256', $time.'.'.$body, $secret);
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($body, $header, $secret);
    }
}
