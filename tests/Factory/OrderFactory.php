<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Order;
use Prism\TestPack\Factory\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    protected static function class(): string
    {
        return Order::class;
    }

    protected function defaults(): array
    {
        return ['reference' => static fn (int $n): string => \sprintf('ORD-%05d', $n), 'placedAt' => new \DateTimeImmutable('2026-03-01 10:00'), 'status' => 'open', 'customer' => CustomerFactory::new()];
    }
}
