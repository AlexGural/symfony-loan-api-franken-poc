<?php

declare(strict_types=1);

namespace App\Presentation\Controller;

use App\Application\Loan\UseCase\ApplyLoanUseCase;
use App\Application\Loan\UseCase\GetLoanUseCase;
use App\Application\Loan\UseCase\GetMinLoanPayment;
use App\Application\Loan\UseCase\ListLoanPaymentsUseCase;
use App\Application\Loan\UseCase\ListUserLoansUseCase;
use App\Application\Loan\UseCase\MakePaymentUseCase;
use App\Presentation\Attribute\HttpStatus;
use App\Presentation\Request\ApplyLoanRequestDto;
use App\Presentation\Request\MakePaymentRequestDto;
use App\Presentation\Response\ErrorResponse;
use App\Presentation\Response\Loan\AmountResponseDto;
use App\Presentation\Response\Loan\LoanResponseDto;
use App\Presentation\Response\Loan\PaymentResponseDto;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

#[OA\Tag(name: 'Loans')]
final readonly class LoanController
{
    public function __construct(
        private ApplyLoanUseCase $applyLoan,
        private MakePaymentUseCase $makePayment,
        private GetLoanUseCase $getLoan,
        private ListUserLoansUseCase $listUserLoans,
        private ListLoanPaymentsUseCase $listLoanPayments,
        private GetMinLoanPayment $getMinLoanPayment,
    ) {
    }

    #[HttpStatus(201)]
    #[Route('/api/users/{userId}/loans', methods: ['POST'])]
    #[OA\Post(summary: 'Apply for a loan')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: ApplyLoanRequestDto::class)))]
    #[OA\Response(response: 201, description: 'Loan created', content: new OA\JsonContent(ref: new Model(type: LoanResponseDto::class)))]
    #[OA\Response(response: 404, description: 'User not found', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    public function apply(
        Uuid $userId,
        #[MapRequestPayload] ApplyLoanRequestDto $dto,
    ): LoanResponseDto {
        return $this->applyLoan->execute($userId->toRfc4122(), $dto->amountMinor, $dto->currency) |> LoanResponseDto::toResponse(...);
    }

    #[Route('/api/users/{userId}/loans', methods: ['GET'])]
    #[OA\Get(summary: 'List loans for a user')]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: 200,
        description: 'List of loans',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: LoanResponseDto::class))),
    )]
    /** @return LoanResponseDto[] */
    public function listByUser(Uuid $userId): array
    {
        return array_map(
            LoanResponseDto::toResponse(...),
            $this->listUserLoans->execute($userId->toRfc4122()),
        );
    }

    #[Route('/api/loans/{id}', methods: ['GET'])]
    #[OA\Get(summary: 'Get loan details')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Loan details', content: new OA\JsonContent(ref: new Model(type: LoanResponseDto::class)))]
    #[OA\Response(response: 404, description: 'Loan not found', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    public function show(Uuid $id): LoanResponseDto
    {
        return $this->getLoan->execute($id->toRfc4122()) |> LoanResponseDto::toResponse(...);
    }

    #[HttpStatus(201)]
    #[Route('/api/loans/{id}/payments', methods: ['POST'])]
    #[OA\Post(summary: 'Make a payment on a loan')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: MakePaymentRequestDto::class)))]
    #[OA\Response(response: 201, description: 'Payment processed', content: new OA\JsonContent(ref: new Model(type: PaymentResponseDto::class)))]
    #[OA\Response(response: 404, description: 'Loan not found', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    #[OA\Response(response: 409, description: 'Loan already paid off', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    #[OA\Response(response: 503, description: 'Exchange rate unavailable', content: new OA\JsonContent(ref: new Model(type: ErrorResponse::class)))]
    public function pay(
        Uuid $id,
        #[MapRequestPayload] MakePaymentRequestDto $dto,
    ): PaymentResponseDto {
        return $this->makePayment->execute(
            $id->toRfc4122(),
            $dto->amountMinor,
            $dto->currency,
        ) |> PaymentResponseDto::toResponse(...);
    }

    #[Route('/api/loans/{id}/payments', methods: ['GET'])]
    #[OA\Get(summary: 'List payments for a loan')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: 200,
        description: 'List of payments',
        content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: new Model(type: PaymentResponseDto::class))),
    )]
    /** @return PaymentResponseDto[] */
    public function payments(Uuid $id): array
    {
        return array_map(
            PaymentResponseDto::toResponse(...),
            $this->listLoanPayments->execute($id->toRfc4122()),
        );
    }

    #[Route('/api/loans/{id}/payments/min-payment', methods: ['GET'])]
    #[OA\Get(summary: 'Get minimum required payment amount for a loan')]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(
        response: 200,
        description: 'Min payment amount',
        content: new OA\JsonContent(ref: new Model(type: AmountResponseDto::class))
    )]
    public function minPayment(Uuid $id): AmountResponseDto
    {
        return $this->getMinLoanPayment->execute($id->toRfc4122()) |> AmountResponseDto::toResponse(...);
    }
}
