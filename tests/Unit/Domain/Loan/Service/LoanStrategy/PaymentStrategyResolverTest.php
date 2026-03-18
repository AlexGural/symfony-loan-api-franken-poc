<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Loan\Service\LoanStrategy;

use App\Domain\Loan\Service\LoanStrategy\AnnuityPaymentStrategy;
use App\Domain\Loan\Service\LoanStrategy\DecliningBalancePaymentStrategy;
use App\Domain\Loan\Service\LoanStrategy\PaymentStrategyResolver;
use PHPUnit\Framework\TestCase;

final class PaymentStrategyResolverTest extends TestCase
{
    private PaymentStrategyResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PaymentStrategyResolver([
            new DecliningBalancePaymentStrategy(),
            new AnnuityPaymentStrategy(),
        ]);
    }

    public function testShortTermReturnsDecliningBalance(): void
    {
        self::assertInstanceOf(DecliningBalancePaymentStrategy::class, $this->resolver->resolve(12));
        self::assertInstanceOf(DecliningBalancePaymentStrategy::class, $this->resolver->resolve(24));
    }

    public function testLongTermReturnsAnnuity(): void
    {
        self::assertInstanceOf(AnnuityPaymentStrategy::class, $this->resolver->resolve(25));
        self::assertInstanceOf(AnnuityPaymentStrategy::class, $this->resolver->resolve(36));
        self::assertInstanceOf(AnnuityPaymentStrategy::class, $this->resolver->resolve(420));
    }

    public function testBoundaryAt24Months(): void
    {
        $at24 = $this->resolver->resolve(24);
        $at25 = $this->resolver->resolve(25);

        self::assertInstanceOf(DecliningBalancePaymentStrategy::class, $at24);
        self::assertInstanceOf(AnnuityPaymentStrategy::class, $at25);
    }
}
