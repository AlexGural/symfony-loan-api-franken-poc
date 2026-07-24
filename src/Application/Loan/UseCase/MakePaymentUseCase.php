<?php

declare(strict_types=1);

namespace App\Application\Loan\UseCase;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Entity\Payment;
use App\Domain\Loan\Exception\LoanNotFoundException;
use App\Domain\Loan\Exception\MinPaymentAmountException;
use App\Domain\Loan\Exception\UnsupportedCurrencyException;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\Loan\Repository\PaymentRepositoryInterface;
use App\Domain\Loan\Service\ExchangeRate\ExchangeRateServiceInterface;
use App\Domain\Loan\Service\LoanManager;
use App\Domain\Loan\Service\LoanStrategy\PaymentStrategyResolver;
use App\Domain\Loan\ValueObject\ConvertedPayment;
use App\Domain\Loan\ValueObject\LoanId;
use App\Domain\Shared\TransactionalRepositoryInterface;
use App\Domain\Shared\ValueObject\SupportedCurrency;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Psr\Log\LoggerInterface;

final readonly class MakePaymentUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private PaymentRepositoryInterface $paymentRepository,
        private TransactionalRepositoryInterface $transactional,
        private LoanManager $loanManager,
        private ExchangeRateServiceInterface $exchangeRateService,
        private LoggerInterface $logger,
        private PaymentStrategyResolver $paymentStrategyResolver,
    ) {
    }

    #[\NoDiscard]
    public function execute(string $loanId, int $amountMinor, string $currency): Payment
    {
        $payment = Money::ofMinor($amountMinor, strtoupper($currency));
        $conversion = $this->convertIfForeignCurrency($loanId, $payment);

        $finalAmount = $conversion?->convertedAmount ?? $payment;

        /** @var Payment $paymentEntity */
        $paymentEntity = $this->transactional->executeTransactional(
            function () use ($loanId, $finalAmount, $conversion): Payment {
                // Apply pessimistic write
                $loan = $this->loanRepository->findByIdForUpdate(LoanId::fromString($loanId))
                    ?? throw LoanNotFoundException::withId($loanId);

                $minLoanPayment = $this->calculateMinLoanPayment($loan);

                if ($finalAmount->isLessThan($minLoanPayment)) {
                    throw MinPaymentAmountException::withAmounts($finalAmount->getMinorAmount()->toInt(), $minLoanPayment->getMinorAmount()->toInt());
                }

                $paymentEntity = $this->loanManager->applyPayment(
                    $loan, $finalAmount, $conversion,
                );

                $this->loanRepository->persist($loan);
                $this->paymentRepository->persist($paymentEntity);

                return $paymentEntity;
            },
        );

        $this->logger->info('payment_recorded', [
            'loanId' => $loanId,
            'paymentId' => $paymentEntity->getId()->value,
            'converted' => null !== $conversion,
        ]);

        return $paymentEntity;
    }

    private function convertIfForeignCurrency(string $loanId, Money $payment): ?ConvertedPayment
    {
        $loan = $this->loanRepository->findById(LoanId::fromString($loanId))
            ?? throw LoanNotFoundException::withId($loanId);

        $loanCurrency = $loan->getCurrency()->getCurrencyCode();
        $paymentCurrency = $payment->getCurrency()->getCurrencyCode();

        if ($paymentCurrency === $loanCurrency) {
            return null;
        }

        $from = SupportedCurrency::tryFrom(strtoupper($paymentCurrency))
            ?? throw UnsupportedCurrencyException::withCode($paymentCurrency);
        $to = SupportedCurrency::tryFrom(strtoupper($loanCurrency))
            ?? throw UnsupportedCurrencyException::withCode($loanCurrency);


        $rate = $this->exchangeRateService->getBestRate($from, $to);

        $convertedMinor = $payment->getMinorAmount()
            ->toBigDecimal()
            ->multipliedBy($rate->rate)
            ->toScale(0, RoundingMode::HALF_UP);

        return new ConvertedPayment(
            convertedAmount: Money::ofMinor($convertedMinor, $loan->getCurrency()),
            originalAmount: $payment,
            exchangeRate: $rate->rate,
        );
    }

    private function calculateMinLoanPayment(Loan $loan): Money
    {
        $paymentStrategy = $this->paymentStrategyResolver->resolve($loan->getTermMonths());

        return $paymentStrategy->calculatePaymentAmount(
            $loan->getPrincipal(),
            $loan->getAnnualInterestRate(),
            $loan->getTermMonths(),
            $loan->getPaidMonths() + 1, // calculate next payment
            $loan->getRemainingBalance()
        );
    }
}
