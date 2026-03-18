<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Service;

use App\Domain\Loan\Service\LoanConditionsGenerator;
use PHPUnit\Framework\TestCase;

final class LoanConditionsGeneratorTest extends TestCase
{
    public function testGeneratedTermIsFromAvailableList(): void
    {
        $terms = [12, 24, 36, 48, 60];
        $generator = new LoanConditionsGenerator($terms, 3.0, 10.0);

        for ($i = 0; $i < 50; ++$i) {
            $conditions = $generator->generate();
            self::assertContains($conditions->termMonths, $terms);
        }
    }

    public function testGeneratedRateWithinBounds(): void
    {
        $generator = new LoanConditionsGenerator([12], 3.0, 10.0);

        for ($i = 0; $i < 50; ++$i) {
            $conditions = $generator->generate();
            $rate = $conditions->annualRate->toFloat();

            self::assertGreaterThanOrEqual(3.0, $rate);
            self::assertLessThanOrEqual(10.0, $rate);
        }
    }

    public function testRateHasTwoDecimalPlaces(): void
    {
        $generator = new LoanConditionsGenerator([12], 3.0, 10.0);
        $conditions = $generator->generate();

        self::assertSame(2, $conditions->annualRate->getScale());
    }

    public function testSingleTermAlwaysReturned(): void
    {
        $generator = new LoanConditionsGenerator([24], 5.0, 5.0);
        $conditions = $generator->generate();

        self::assertSame(24, $conditions->termMonths);
        self::assertTrue($conditions->annualRate->isEqualTo('5.00'));
    }
}
