<?php

namespace App\Services\Finance;

use App\Models\Member;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Models\MemberLedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Services\Access\ClubPermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentRecordingService
{
    public const PERMISSION_RECORD_PAYMENT = 'payments.record';

    public function __construct(
        private readonly ClubPermissionService $clubPermissionService
    ) {
    }

    /**
     * Record a member payment and create the matching member ledger credit.
     *
     * This service is intentionally internal-only for Milestone 3M.
     */
    public function record(User $receivedByUser, Member $member, array $attributes): Payment
    {
        $validated = $this->validateAttributes($attributes);

        $amount = $this->formatAmount((float) $validated['amount']);
        $paymentDate = CarbonImmutable::parse($validated['payment_date'])->toDateString();

        return app(OrganisationTransactionGuard::class)->run((int) $member->club_id, function (OrganisationTransactionContext $context) use ($receivedByUser, $member, $validated, $amount, $paymentDate): Payment {
            $member = Member::query()->where('club_id', $context->organisationId)->whereKey($member->id)->firstOrFail();
            $member->load('club');
            $this->authorizeRecording($receivedByUser, $member);
            $payment = Payment::query()->create([
                'club_id' => $member->club_id,
                'member_id' => $member->id,
                'amount' => $amount,
                'payment_date' => $paymentDate,
                'payment_mode' => $validated['payment_mode'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_by_user_id' => $receivedByUser->id,
                'ledger_entry_id' => null,
                'status' => 'posted',
            ]);

            $ledgerEntry = MemberLedgerEntry::query()->create([
                'club_id' => $member->club_id,
                'member_id' => $member->id,
                'created_by_user_id' => $receivedByUser->id,
                'entry_type' => MemberLedgerEntry::ENTRY_TYPE_CREDIT,
                'source_type' => MemberLedgerEntry::SOURCE_PAYMENT,
                'amount' => $amount,
                'accounting_month' => null,
                'due_date' => null,
                'transaction_date' => $paymentDate,
                'reference_number' => $validated['reference_number'] ?? null,
                'description' => 'Payment received from '.$member->full_name,
                'metadata' => [
                    'payment_id' => $payment->id,
                    'payment_mode' => $validated['payment_mode'],
                    'recorded_by_user_id' => $receivedByUser->id,
                    'service' => self::class,
                ],
                'status' => MemberLedgerEntry::STATUS_POSTED,
            ]);

            $payment->update([
                'ledger_entry_id' => $ledgerEntry->id,
            ]);

            return $payment->refresh()->load(['member', 'ledgerEntry', 'receivedBy']);
        });
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    private function authorizeRecording(User $receivedByUser, Member $member): void
    {
        if (! $member->club) {
            throw ValidationException::withMessages([
                'member' => 'The selected member must belong to a valid club.',
            ]);
        }

        if (! $member->club->is_active) {
            throw new AuthorizationException('Payments cannot be recorded for an inactive club.');
        }

        if ($member->status !== 'active') {
            throw ValidationException::withMessages([
                'member' => 'Payments can only be recorded for active members.',
            ]);
        }

        if (! $this->clubPermissionService->userCanAccessClub($receivedByUser, (int) $member->club_id)) {
            throw new AuthorizationException('The user cannot access this club.');
        }

        if (! $this->clubPermissionService->userHasPermission($receivedByUser, self::PERMISSION_RECORD_PAYMENT, (int) $member->club_id)) {
            throw new AuthorizationException('The user does not have permission to record payments.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAttributes(array $attributes): array
    {
        return Validator::make($attributes, [
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_date' => ['required', 'date'],
            'payment_mode' => ['required', Rule::in(Payment::MODES)],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();
    }

    private function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2, '.', '');
    }
}
