<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Product;
use Prism\TestPack\Factory\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected static function class(): string
    {
        return Product::class;
    }

    protected function defaults(): array
    {
        return ['sku' => static fn (int $n): string => \sprintf('SKU-%04d', $n), 'name' => static fn (int $n): string => 'Product ' . $n, 'price' => '10.00'];
    }
}
