<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;
use App\Tests\Factory\CustomerFactory;
use App\Tests\Factory\OrderFactory;

final class CustomerPagesTest extends AppWebTestCase
{
    public function testListIsPaginatedByName(): void
    {
        $client = static::createClient();
        CustomerFactory::new()->many(25);
        self::forgetFixtures();

        $client->request('GET', '/customers');
        self::assertSelectorCount(20, 'tbody tr');
        self::assertSelectorTextContains('.pager', 'Page 1 of 2 · 25 in all');

        $client->request('GET', '/customers?page=2');
        self::assertSelectorCount(5, 'tbody tr');
    }

    public function testCustomerAndTheirOrders(): void
    {
        $client = static::createClient();
        $customer = CustomerFactory::new(['name' => 'Ada Lovelace'])->create();
        OrderFactory::new(['customer' => $customer])->many(3);
        self::forgetFixtures();

        $client->request('GET', '/customers/' . $customer->id);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Ada Lovelace');
        self::assertSelectorCount(3, 'tbody tr');
    }
}
