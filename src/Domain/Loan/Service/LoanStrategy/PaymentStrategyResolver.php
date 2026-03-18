<?php

declare(strict_types=1);

namespace App\Domain\Loan\Service\LoanStrategy;

final readonly class PaymentStrategyResolver
{
    /** @param iterable<PaymentCalculationStrategyInterface> $strategies */
    public function __construct(
        private iterable $strategies,
    ) {
    }

    #[\NoDiscard]
    public function resolve(int $termMonths): PaymentCalculationStrategyInterface
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($termMonths)) {
                return $strategy;
            }
        }

        throw new \InvalidArgumentException(sprintf('No payment strategy found for term %d months.', $termMonths));
    }
}
