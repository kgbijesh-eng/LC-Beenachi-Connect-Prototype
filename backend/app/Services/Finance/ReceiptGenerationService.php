<?php

namespace App\Services\Finance;

use App\Models\Payment;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Access\ClubPermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ReceiptGenerationService
{
    public const PERMISSION_GENERATE_RECEIPT = PaymentRecordingService::PERMISSION_RECORD_PAYMENT;

    public function __construct(
        private readonly ClubPermissionService $clubPermissionService
    ) {
    }

    /**
     * Generate one official receipt for an already recorded posted payment.
     *
     * Milestone 3U scope:
     * - backend service only
     * - no route
     * - no controller/request/resource
     * - no PDF
     * - no frontend
     * - no messaging
     * - no reports
     */
    public function generate(User $issuedByUser, Payment $payment, array $attributes = []): Receipt
    {
        $validated = $this->validateAttributes($attributes);

        return app(OrganisationTransactionGuard::class)->run((int) $payment->club_id, function (OrganisationTransactionContext $context) use ($issuedByUser, $payment, $validated): Receipt {
            $lockedPayment = Payment::query()->where('club_id', $context->organisationId)
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedPayment->loadMissing(['club', 'member', 'ledgerEntry']);

            $this->authorizeGeneration($issuedByUser, $lockedPayment);
            $this->ensurePaymentIsReceiptable($lockedPayment);
            $this->ensurePaymentDoesNotAlreadyHaveReceipt($lockedPayment);

            $receiptDate = CarbonImmutable::parse(
                $validated['receipt_date'] ?? $lockedPayment->payment_date
            )->toDateString();

            $receipt = Receipt::query()->create([
                'club_id' => $lockedPayment->club_id,
                'payment_id' => $lockedPayment->id,
                'receipt_number' => $this->nextReceiptNumber($lockedPayment, $receiptDate),
                'receipt_date' => $receiptDate,
                'issued_by_user_id' => $issuedByUser->id,
                'status' => 'issued',
                'notes' => $validated['notes'] ?? null,
            ]);

            return $receipt->refresh()->load(['club', 'payment.member', 'issuedBy']);
        });
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeGeneration(User $issuedByUser, Payment $payment): void
    {
        if (! $payment->club) {
            throw new AuthorizationException('Receipts cannot be generated for payments without a valid club.');
        }

        if (! $payment->club->is_active) {
            throw new AuthorizationException('Receipts cannot be generated for inactive clubs.');
        }

        if (! $this->clubPermissionService->userCanAccessClub($issuedByUser, (int) $payment->club_id)) {
            throw new AuthorizationException('The user cannot access this club.');
        }

        if (! $this->clubPermissionService->userHasPermission($issuedByUser, self::PERMISSION_GENERATE_RECEIPT, (int) $payment->club_id)) {
            throw new AuthorizationException('The user does not have permission to generate receipts.');
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensurePaymentIsReceiptable(Payment $payment): void
    {
        if ($payment->status !== 'posted') {
            throw ValidationException::withMessages([
                'payment' => 'Receipts can only be generated for posted payments.',
            ]);
        }

        if (! $payment->ledger_entry_id) {
            throw ValidationException::withMessages([
                'payment' => 'Receipts can only be generated for payments linked to a posted ledger entry.',
            ]);
        }

        if (! $payment->member) {
            throw ValidationException::withMessages([
                'payment' => 'Receipts can only be generated for payments linked to a valid member.',
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensurePaymentDoesNotAlreadyHaveReceipt(Payment $payment): void
    {
        $existingReceiptExists = Receipt::query()
            ->where('payment_id', $payment->id)
            ->exists();

        if ($existingReceiptExists) {
            throw ValidationException::withMessages([
                'payment' => 'A receipt has already been generated for this payment.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAttributes(array $attributes): array
    {
        return Validator::make($attributes, [
            'receipt_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();
    }

    private function nextReceiptNumber(Payment $payment, string $receiptDate): string
    {
        $clubCode = $this->normalisedClubCode((string) $payment->club->code, (int) $payment->club_id);
        $financialYear = $this->financialYearFor($receiptDate);

        $prefix = "{$clubCode}/{$financialYear}/RCP/";

        $latestReceiptNumber = Receipt::query()
            ->where('club_id', $payment->club_id)
            ->where('receipt_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('receipt_number');

        $nextSequence = 1;

        if (is_string($latestReceiptNumber) && preg_match('/\/RCP\/(\d+)$/', $latestReceiptNumber, $matches)) {
            $nextSequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $nextSequence, 4, '0', STR_PAD_LEFT);
    }

    private function financialYearFor(string $date): string
    {
        $receiptDate = CarbonImmutable::parse($date);
        $year = (int) $receiptDate->format('Y');
        $month = (int) $receiptDate->format('m');

        $startYear = $month >= 4 ? $year : $year - 1;
        $endYear = $startYear + 1;

        return $startYear.'-'.substr((string) $endYear, -2);
    }

    private function normalisedClubCode(string $clubCode, int $clubId): string
    {
        $normalised = preg_replace('/[^A-Z0-9]/', '', strtoupper($clubCode));

        return $normalised ?: 'CLUB'.$clubId;
    }
}