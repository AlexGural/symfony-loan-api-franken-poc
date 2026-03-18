<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Support\ApiTestCase;

final class LoanControllerTest extends ApiTestCase
{
    private function createUser(): array
    {
        return $this->jsonRequest('POST', '/api/users', [
            'name' => 'Loan Tester',
            'email' => 'loan.' . bin2hex(random_bytes(4)) . '@test.com',
        ]);
    }

    private function makePayment(string $loanId, int $amountMinor = 10000, string $currency = 'EUR'): array
    {
        return $this->jsonRequest('POST', '/api/loans/' . $loanId . '/payments', [
            'amountMinor' => $amountMinor,
            'currency' => $currency,
        ]);
    }

    public function testApplyLoan(): void
    {
        $user = $this->createUser();

        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        self::assertSame(201, $this->getStatusCode());
        self::assertSame(500000, $loan['principalAmount']);
        self::assertSame('EUR', $loan['currency']);
        self::assertSame('active', $loan['status']);
        self::assertSame(0, $loan['paidMonths']);
        self::assertSame(500000, $loan['remainingBalance']);
        self::assertArrayHasKey('annualInterestRate', $loan);
        self::assertArrayHasKey('termMonths', $loan);
        self::assertArrayHasKey('initialMonthlyPayment', $loan);
        self::assertGreaterThan(0, $loan['initialMonthlyPayment']);
    }

    public function testApplyLoanValidation(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => -100,
            'currency' => 'EUR',
        ]);

        self::assertSame(422, $this->getStatusCode());
    }

    public function testApplyLoanUnsupportedCurrency(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'GBP',
        ]);

        self::assertSame(422, $this->getStatusCode());
    }

    public function testApplyLoanForNonExistentUser(): void
    {
        $this->jsonRequest('POST', '/api/users/00000000-0000-0000-0000-000000000000/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        self::assertSame(404, $this->getStatusCode());
    }

    public function testGetLoan(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 300000,
            'currency' => 'EUR',
        ]);

        $response = $this->jsonRequest('GET', '/api/loans/' . $loan['id']);

        self::assertSame(200, $this->getStatusCode());
        self::assertSame($loan['id'], $response['id']);
        self::assertSame(300000, $response['principalAmount']);
    }

    public function testGetNonExistentLoanReturns404(): void
    {
        $this->jsonRequest('GET', '/api/loans/00000000-0000-0000-0000-000000000000');

        self::assertSame(404, $this->getStatusCode());
    }

    public function testListUserLoans(): void
    {
        $user = $this->createUser();

        $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 100000,
            'currency' => 'EUR',
        ]);
        $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 200000,
            'currency' => 'EUR',
        ]);

        $response = $this->jsonRequest('GET', '/api/users/' . $user['id'] . '/loans');

        self::assertSame(200, $this->getStatusCode());
        self::assertCount(2, $response);
    }

    public function testMakePayment(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        $payment = $this->makePayment($loan['id'], 15000, 'EUR');

        self::assertSame(201, $this->getStatusCode());
        self::assertSame(1, $payment['paymentNumber']);
        self::assertSame('EUR', $payment['currency']);
        self::assertSame(15000, $payment['amount']);
        self::assertSame(485000, $payment['remainingBalanceAfter']);
        self::assertNull($payment['originalAmount']);
        self::assertNull($payment['originalCurrency']);
        self::assertNull($payment['exchangeRate']);
    }

    public function testMakePaymentWithForeignCurrency(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        $payment = $this->makePayment($loan['id'], 10000, 'USD');

        self::assertSame(201, $this->getStatusCode());
        self::assertSame('EUR', $payment['currency']);
        self::assertSame(10000, $payment['originalAmount']);
        self::assertSame('USD', $payment['originalCurrency']);
        self::assertNotNull($payment['exchangeRate']);
        // 10000 USD * 0.90 = 9000 EUR (stub)
        self::assertSame(9000, $payment['amount']);
    }

    public function testMakePaymentValidationRequiresBody(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        $this->jsonRequest('POST', '/api/loans/' . $loan['id'] . '/payments');

        self::assertSame(400, $this->getStatusCode());
    }

    public function testMakeMultiplePaymentsReduceBalance(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        $first = $this->makePayment($loan['id'], 10000, 'EUR');
        $second = $this->makePayment($loan['id'], 10000, 'EUR');

        self::assertSame(1, $first['paymentNumber']);
        self::assertSame(2, $second['paymentNumber']);
        self::assertLessThan($first['remainingBalanceAfter'], $second['remainingBalanceAfter']);
    }

    public function testListPaymentsForLoan(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        $this->makePayment($loan['id'], 10000, 'EUR');
        $this->makePayment($loan['id'], 10000, 'EUR');

        $payments = $this->jsonRequest('GET', '/api/loans/' . $loan['id'] . '/payments');

        self::assertSame(200, $this->getStatusCode());
        self::assertCount(2, $payments);
        self::assertSame(1, $payments[0]['paymentNumber']);
        self::assertSame(2, $payments[1]['paymentNumber']);
    }

    public function testPaymentOnNonExistentLoanReturns404(): void
    {
        $this->jsonRequest('POST', '/api/loans/00000000-0000-0000-0000-000000000000/payments', [
            'amountMinor' => 10000,
            'currency' => 'EUR',
        ]);

        self::assertSame(404, $this->getStatusCode());
    }

    public function testFullLoanLifecycle(): void
    {
        $user = $this->createUser();

        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 50000,
            'currency' => 'EUR',
        ]);
        self::assertSame('active', $loan['status']);

        for ($i = 0; $i < 120; ++$i) {
            $updated = $this->jsonRequest('GET', '/api/loans/' . $loan['id']);
            if ('paid_off' === $updated['status']) {
                break;
            }

            $remaining = $updated['remainingBalance'];
            $payAmount = min(5000, $remaining);

            $this->makePayment($loan['id'], $payAmount, 'EUR');
            self::assertSame(201, $this->getStatusCode());
        }

        $final = $this->jsonRequest('GET', '/api/loans/' . $loan['id']);
        self::assertSame('paid_off', $final['status']);
        self::assertSame(0, $final['remainingBalance']);
    }
}
