<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Loan\Entity\Payment;
use App\Domain\Loan\Repository\PaymentRepositoryInterface;
use App\Domain\Loan\ValueObject\LoanId;
use App\Infrastructure\Persistence\Doctrine\Trait\PersistenceTrait;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrinePaymentRepository implements PaymentRepositoryInterface
{
    use PersistenceTrait;

    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /** @return Payment[] */
    #[\Override]
    public function findByLoanId(LoanId $loanId): array
    {
        return $this->em->getRepository(Payment::class)->findBy(
            ['loan' => $loanId->value],
            ['paymentNumber' => 'ASC'],
        );
    }
}
