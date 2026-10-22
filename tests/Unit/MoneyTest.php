<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testCentsWithoutFloats(): void
    {
        self::assertSame(1250, Money::toCents('12.50'));
        self::assertSame(1205, Money::toCents('12.05'));
        self::assertSame(1200, Money::toCents('12'));
        self::assertSame(-1, Money::toCents('-0.01'));
        self::assertSame('12.50', Money::fromCents(1250));
        self::assertSame('-0.01', Money::fromCents(-1));
        // 0.1 + 0.2 en flottants ne vaut pas 0.3 ; en centimes, si
        self::assertSame('0.30', Money::fromCents(Money::toCents('0.10') + Money::toCents('0.20')));
    }

    public function testInvalidAmountIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::toCents('12.345');
    }
}
