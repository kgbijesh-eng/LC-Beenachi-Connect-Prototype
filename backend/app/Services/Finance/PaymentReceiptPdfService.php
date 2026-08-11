<?php

namespace App\Services\Finance;

use App\Models\Club;
use App\Models\Member;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Access\ClubPermissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpFoundation\Response;

class PaymentReceiptPdfService
{
    public function __construct(
        private readonly ClubPermissionService $clubPermissionService
    ) {
    }

    public function download(User $user, Club $club, Member $member, Payment $payment): ?Response
    {
        $this->authorizeDownload($user, $club, $member);

        $receipt = Receipt::query()
            ->where('club_id', $club->id)
            ->where('payment_id', $payment->id)
            ->where('status', 'issued')
            ->with(['club', 'payment.member', 'payment.receivedBy', 'issuedBy'])
            ->first();

        if (! $receipt) {
            return null;
        }

        $pdf = Pdf::loadView('pdf.receipt', [
            'club' => $receipt->club,
            'receipt' => $receipt,
            'payment' => $receipt->payment,
            'member' => $receipt->payment?->member,
            'issuedBy' => $receipt->issuedBy,
            'financialYear' => $this->financialYearFromReceiptNumber((string) $receipt->receipt_number),
        ])->setPaper('a4', 'portrait');

        return $pdf->download($this->filenameFor($receipt));
    }

    /**
     * @throws AuthorizationException
     */
    private function authorizeDownload(User $user, Club $club, Member $member): void
    {
        if (! $club->is_active) {
            throw new AuthorizationException('Receipt PDFs cannot be downloaded for inactive clubs.');
        }

        if (! $this->clubPermissionService->userCanAccessClub($user, (int) $club->id)) {
            throw new AuthorizationException('The user cannot access this club.');
        }

        $canViewAnyReceipt = $this->clubPermissionService->userHasPermission(
            $user,
            PaymentRecordingService::PERMISSION_RECORD_PAYMENT,
            (int) $club->id
        );

        $isOwnMemberReceipt = $member->user_id !== null
            && (int) $member->user_id === (int) $user->id;

        if (! $canViewAnyReceipt && ! $isOwnMemberReceipt) {
            throw new AuthorizationException('The user does not have permission to download this receipt PDF.');
        }
    }

    private function filenameFor(Receipt $receipt): string
    {
        $safeReceiptNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $receipt->receipt_number);

        return 'receipt-'.$safeReceiptNumber.'.pdf';
    }

    private function financialYearFromReceiptNumber(string $receiptNumber): ?string
    {
        if (preg_match('/\/(\d{4}-\d{2})\/RCP\//', $receiptNumber, $matches)) {
            return $matches[1];
        }

        return null;
    }
}