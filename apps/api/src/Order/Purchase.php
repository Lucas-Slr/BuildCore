<?php

declare(strict_types=1);

namespace App\Order;

use App\Customer\Customer;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'purchase')]
#[ORM\Index(name:'idx_purchase_expiration', columns:['reservation_status','expires_at'])]
class Purchase
{
    #[ORM\Id, ORM\Column(length: 36)] public string $id;
    #[ORM\Column(length: 32, unique: true)] public string $number;
    #[ORM\ManyToOne, ORM\JoinColumn(nullable: false)] public Customer $customer;
    #[ORM\Column(length: 180)] public string $email;
    /** @var list<array<string, mixed>> */
    #[ORM\Column(type: 'json')] public array $lines = [];
    /** @var array<string, string> */
    #[ORM\Column(type: 'json')] public array $shippingAddress = [];
    /** @var array<string, string> */
    #[ORM\Column(type: 'json')] public array $billingAddress = [];
    #[ORM\Column(length: 20)] public string $delivery = 'standard';
    #[ORM\Column] public int $shipping = 0;
    #[ORM\Column] public int $total = 0;
    #[ORM\Column(length: 3)] public string $currency = 'EUR';
    #[ORM\Column(length: 30)] public string $status = 'PENDING_PAYMENT';
    #[ORM\Column(length: 30)] public string $paymentStatus = 'PENDING';
    #[ORM\Column(length: 30)] public string $reservationStatus = 'ACTIVE';
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $expiresAt;
    #[ORM\Column(type: 'datetime_immutable')] public \DateTimeImmutable $createdAt;
    #[ORM\Column(length: 255, nullable: true, unique: true)] public ?string $stripeSession = null;
    #[ORM\Column(length: 255, nullable: true)] public ?string $paymentIntent = null;
    #[ORM\Column(length: 255, nullable: true)] public ?string $stripeRefund = null;
    #[ORM\Column(type: 'text', nullable: true)] public ?string $checkoutUrl = null;
    #[ORM\Column(length: 180)] public string $tracking = '';
    #[ORM\Column(length: 180, unique: true)] public string $idempotencyKey;
    /** @var list<array<string, string>> */
    #[ORM\Column(type: 'json')] public array $history = [];
    public function __construct(Customer $customer, string $key)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->number = 'BC-'.strtoupper(bin2hex(random_bytes(6)));
        $this->customer = $customer;
        $this->email = $customer->email;
        $this->idempotencyKey = $key;
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = $this->createdAt->modify('+35 minutes');
        $this->record('PENDING_PAYMENT', 'customer');
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }
    public function record(string $status, string $actor): void
    {
        $this->history[] = ['status' => $status, 'actor' => $actor, 'at' => (new \DateTimeImmutable())->format(DATE_ATOM)];
    }
}
