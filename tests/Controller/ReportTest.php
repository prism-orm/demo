<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppWebTestCase;
use App\Tests\Factory\CustomerFactory;
use App\Tests\Factory\InvoiceFactory;
use App\Tests\Factory\OrderFactory;

final class ReportTest extends AppWebTestCase
{
    // une seule requête groupée, projetée dans un DTO (RG-05.6)
    public function testRevenueByCustomerInOneQuery(): void
    {
        $client = static::createClient();
        $ada = CustomerFactory::new(['name' => 'Ada'])->create();
        $alan = CustomerFactory::new(['name' => 'Alan'])->create();
        foreach (['100.00', '50.50'] as $total) {
            InvoiceFactory::new(['total' => $total, 'order' => OrderFactory::new(['customer' => $ada])])->create();
        }
        InvoiceFactory::new(['total' => '20.00', 'order' => OrderFactory::new(['customer' => $alan])])->create();
        self::forgetFixtures();

        // la société du sous-domaine, puis le rapport en une requête
        $this->assertQueryCount(2, static fn () => $client->request('GET', '/reports/revenue'));

        $rows = $client->getCrawler()->filter('tbody tr')->each(static fn ($row): string => implode(' ', $row->filter('td')->each(static fn ($cell): string => trim($cell->text()))));
        self::assertSame(['Ada 2 150.50 2026-03-02', 'Alan 1 20.00 2026-03-02'], $rows);
    }
}
