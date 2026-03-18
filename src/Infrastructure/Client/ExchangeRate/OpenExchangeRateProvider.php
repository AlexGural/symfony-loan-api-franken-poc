<?php

declare(strict_types=1);

namespace App\Infrastructure\Client\ExchangeRate;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use Amp\TimeoutCancellation;
use App\Domain\Loan\Service\ExchangeRate\ExchangeRateProviderInterface;
use App\Domain\Loan\ValueObject\ExchangeRate;
use App\Domain\Shared\ValueObject\SupportedCurrency;
use Brick\Math\BigDecimal;

final readonly class OpenExchangeRateProvider implements ExchangeRateProviderInterface
{
    private const string BASE_URL = 'https://open.er-api.com/v6/latest';

    public function __construct(
        private HttpClient $httpClient,
        private float $timeoutSeconds,
    ) {
    }

    #[\Override]
    public function getRate(SupportedCurrency $from, SupportedCurrency $to): ExchangeRate
    {
        $url = self::BASE_URL . '/' . $from->value;

        $response = $this->httpClient->request(
            new Request($url),
            new TimeoutCancellation($this->timeoutSeconds),
        );

        if (200 !== $response->getStatus()) {
            throw new \RuntimeException(sprintf('%s returned HTTP %d', static::class, $response->getStatus()));
        }

        $body = $response->getBody()->buffer();
        $data = json_decode($body, true);

        if (!is_array($data) || !isset($data['rates'][$to->value])) {
            throw new \RuntimeException(sprintf('%s returned unexpected response structure', static::class));
        }

        return new ExchangeRate(
            from: $from,
            to: $to,
            rate: BigDecimal::of((string) $data['rates'][$to->value]),
            provider: static::class,
        );
    }
}
