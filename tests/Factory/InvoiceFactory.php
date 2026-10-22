<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Invoice;
use Prism\TestPack\Factory\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    protected static function class(): string
    {
        return Invoice::class;
    }

    protected function defaults(): array
    {
        return ['number' => static fn (int $n): string => \sprintf('INV-%05d', $n), 'issuedAt' => new \DateTimeImmutable('2026-03-02'), 'total' => '20.00', 'paid' => false, 'order' => OrderFactory::new()];
    }
}
