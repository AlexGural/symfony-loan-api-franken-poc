<?php

declare(strict_types=1);

namespace App\Infrastructure\Client\ExchangeRate;

use App\Domain\Loan\Service\ExchangeRate\BestRateSelector;
use App\Domain\Loan\Service\ExchangeRate\ExchangeRateProviderInterface;
use App\Domain\Loan\Service\ExchangeRate\ExchangeRateServiceInterface;
use App\Domain\Loan\ValueObject\ExchangeRate;
use App\Domain\Shared\ValueObject\SupportedCurrency;
use Psr\Log\LoggerInterface;

use function Amp\async;
use function Amp\Future\await;

final readonly class ExchangeRateService implements ExchangeRateServiceInterface
{
    /**
     * @param iterable<ExchangeRateProviderInterface> $providers
     */
    public function __construct(
        private iterable $providers,
        private BestRateSelector $selector,
        private LoggerInterface $logger,
    ) {
    }

    // In prod i've add cache decorator with ttl based on business requirements
    #[\Override]
    public function getBestRate(SupportedCurrency $from, SupportedCurrency $to): ExchangeRate
    {
        $futures = [];

        foreach ($this->providers as $provider) {
            $futures[] = async(function () use ($provider, $from, $to): ?ExchangeRate {
                try {
                    return $provider->getRate($from, $to);
                } catch (\Throwable $e) {
                    $this->logger->notice('exchange_rate_provider_failed', [
                        'provider' => $provider::class,
                        'from' => $from->value,
                        'to' => $to->value,
                        'error' => $e::class,
                    ]);

                    return null;
                }
            });
        }

        $results = await($futures);
        $rates = array_filter($results);

        return $this->selector->select($rates);
    }
}
