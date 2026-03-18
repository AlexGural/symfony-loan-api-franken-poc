<?php

declare(strict_types=1);

namespace App\Application\Loan\UseCase;

use App\Domain\Loan\Entity\Loan;
use App\Domain\Loan\Repository\LoanRepositoryInterface;
use App\Domain\Loan\Service\LoanManager;
use App\Domain\User\Exception\UserNotFoundException;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use Brick\Money\Money;
use Psr\Log\LoggerInterface;

final readonly class ApplyLoanUseCase
{
    public function __construct(
        private LoanRepositoryInterface $loanRepository,
        private UserRepositoryInterface $userRepository,
        private LoanManager $loanManager,
        private LoggerInterface $logger,
    ) {
    }

    #[\NoDiscard]
    public function execute(string $userId, int $amountMinor, string $currency): Loan
    {
        $user = $this->userRepository->findById(new UserId($userId))
            ?? throw UserNotFoundException::withId($userId);

        $principal = Money::ofMinor($amountMinor, $currency);
        $loan = $this->loanManager->createLoan($user, $principal);

        $this->loanRepository->save($loan);

        $this->logger->info('loan_applied', [
            'userId' => $userId,
            'loanId' => $loan->getId()->value,
        ]);

        return $loan;
    }
}
