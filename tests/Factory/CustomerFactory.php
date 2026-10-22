<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Customer;
use Prism\TestPack\Factory\Factory;

/**
 * @extends Factory<Customer>
 */
final class CustomerFactory extends Factory
{
    protected static function class(): string
    {
        return Customer::class;
    }

    protected function defaults(): array
    {
        return ['name' => static fn (int $n): string => 'Customer ' . $n, 'email' => static fn (int $n): string => \sprintf('customer%d@example.test', $n), 'city' => 'Paris'];
    }
}
