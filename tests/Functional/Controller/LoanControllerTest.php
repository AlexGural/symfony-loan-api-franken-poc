<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\Support\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class LoanControllerTest extends ApiTestCase
{
    private function createUser(): array
    {
        $response = $this->jsonRequest('POST', '/api/users', [
            'name' => 'Loan Tester',
            'email' => 'loan.' . bin2hex(random_bytes(4)) . '@test.com',
        ]);

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($response);
        self::assertArrayHasKey('id', $response);
        self::assertTrue(Uuid::isValid($response['id']));

        return $response;
    }

    private function getMinPayment(string $loanId): array
    {
        $minPayment = $this->jsonRequest('GET', '/api/loans/' . $loanId . '/payments/min-payment');
        self::assertSame(200, $this->getStatusCode());
        self::assertIsArray($minPayment);
        self::assertArrayHasKey('minPaymentAmountMinor', $minPayment);
        self::assertGreaterThan(0, $minPayment['minPaymentAmountMinor']);

        return $minPayment;
    }

    private function makePayment(string $loanId, ?int $amountMinor = null, string $currency = 'EUR'): array
    {
        if (null === $amountMinor) {
            $amountMinor = $this->getMinPayment($loanId)['minPaymentAmountMinor'];
        }

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
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));
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
        $nonExistentUserId = Uuid::v7()->toRfc4122();
        $this->jsonRequest('POST', '/api/users/' . $nonExistentUserId . '/loans', [
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

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $response = $this->jsonRequest('GET', '/api/loans/' . $loan['id']);
        self::assertSame(200, $this->getStatusCode());
        self::assertSame($loan['id'], $response['id']);
        self::assertSame(300000, $response['principalAmount']);
    }

    public function testGetNonExistentLoanReturns404(): void
    {
        $nonExistentLoanId = Uuid::v7()->toRfc4122();
        $this->jsonRequest('GET', '/api/loans/' . $nonExistentLoanId);

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

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $minPayment = $this->getMinPayment($loan['id']);
        $payment = $this->makePayment($loan['id'], $minPayment['minPaymentAmountMinor'], 'EUR');

        self::assertSame(201, $this->getStatusCode());
        self::assertSame(1, $payment['paymentNumber']);
        self::assertSame('EUR', $payment['currency']);
        self::assertSame($minPayment['minPaymentAmountMinor'], $payment['amount']);
        self::assertSame(500000 - $minPayment['minPaymentAmountMinor'], $payment['remainingBalanceAfter']);
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

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $minPayment = $this->getMinPayment($loan['id']);
        $paymentAmount = $minPayment['minPaymentAmountMinor'] * 2;
        $payment = $this->makePayment($loan['id'], $paymentAmount, 'USD');

        self::assertSame(201, $this->getStatusCode());
        self::assertSame('EUR', $payment['currency']);
        self::assertSame('USD', $payment['originalCurrency']);
        self::assertNotNull($payment['exchangeRate']);
    }

    public function testMakePaymentValidationRequiresBody(): void
    {
        $user = $this->createUser();
        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

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

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $firstMinPayment = $this->getMinPayment($loan['id']);
        $first = $this->makePayment($loan['id'], $firstMinPayment['minPaymentAmountMinor'], 'EUR');
        $secondMinPayment = $this->getMinPayment($loan['id']);
        $second = $this->makePayment($loan['id'], $secondMinPayment['minPaymentAmountMinor'], 'EUR');

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

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $firstMinPayment = $this->getMinPayment($loan['id']);
        $this->makePayment($loan['id'], $firstMinPayment['minPaymentAmountMinor'], 'EUR');
        $secondMinPayment = $this->getMinPayment($loan['id']);
        $this->makePayment($loan['id'], $secondMinPayment['minPaymentAmountMinor'], 'EUR');

        $payments = $this->jsonRequest('GET', '/api/loans/' . $loan['id'] . '/payments');

        self::assertSame(200, $this->getStatusCode());
        self::assertCount(2, $payments);
        self::assertSame(1, $payments[0]['paymentNumber']);
        self::assertSame(2, $payments[1]['paymentNumber']);
    }

    public function testPaymentOnNonExistentLoanReturns404(): void
    {
        $nonExistentLoanId = Uuid::v7()->toRfc4122();
        $this->jsonRequest('POST', '/api/loans/' . $nonExistentLoanId . '/payments', [
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

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        self::assertSame('active', $loan['status']);

        for ($i = 0; $i < 120; ++$i) {
            $updated = $this->jsonRequest('GET', '/api/loans/' . $loan['id']);
            if ('paid_off' === $updated['status']) {
                break;
            }

            $minPayment = $this->getMinPayment($loan['id']);
            $this->makePayment($loan['id'], $minPayment['minPaymentAmountMinor'], 'EUR');
            self::assertSame(201, $this->getStatusCode());
        }

        $final = $this->jsonRequest('GET', '/api/loans/' . $loan['id']);
        self::assertSame('paid_off', $final['status']);
        self::assertSame(0, $final['remainingBalance']);
    }

    public function testPaymentsRequiredReturnsNonZeroAmount(): void
    {
        $user = $this->createUser();

        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $required = $this->jsonRequest('GET', '/api/loans/' . $loan['id'] . '/payments/min-payment');

        self::assertSame(200, $this->getStatusCode());
        self::assertSame('EUR', $required['currency']);
        self::assertArrayHasKey('minPaymentAmountMinor', $required);
        self::assertGreaterThan(0, $required['minPaymentAmountMinor']);
    }

    public function testMakePaymentBelowMinimumReturns422(): void
    {
        $user = $this->createUser();

        $loan = $this->jsonRequest('POST', '/api/users/' . $user['id'] . '/loans', [
            'amountMinor' => 500000,
            'currency' => 'EUR',
        ]);

        self::assertSame(201, $this->getStatusCode());
        self::assertIsArray($loan);
        self::assertArrayHasKey('id', $loan);
        self::assertIsString($loan['id']);
        self::assertTrue(Uuid::isValid($loan['id']));

        $required = $this->jsonRequest('GET', '/api/loans/' . $loan['id'] . '/payments/min-payment');
        $minAmount = $required['minPaymentAmountMinor'];
        self::assertGreaterThan(0, $minAmount);

        $belowMinAmount = max(1, $minAmount - 1);

        $payment = $this->makePayment($loan['id'], $belowMinAmount, 'EUR');

        self::assertSame(422, $this->getStatusCode());
        self::assertArrayHasKey('error', $payment);
        self::assertArrayHasKey('code', $payment['error']);
        self::assertSame(2004, $payment['error']['code']);
    }
}
