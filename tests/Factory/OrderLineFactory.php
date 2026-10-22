<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\OrderLine;
use Prism\TestPack\Factory\Factory;

/**
 * @extends Factory<OrderLine>
 */
final class OrderLineFactory extends Factory
{
    protected static function class(): string
    {
        return OrderLine::class;
    }

    protected function defaults(): array
    {
        return ['order' => OrderFactory::new(), 'product' => ProductFactory::new(), 'quantity' => 2, 'unitPrice' => '10.00'];
    }
}
