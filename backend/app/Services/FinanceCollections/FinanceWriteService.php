<?php

namespace App\Services\FinanceCollections;

use App\Models\AccountingChargeCategory;
use App\Models\AuditLog;
use App\Models\Club;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Access\TransactionActorAuthorisation;
use App\Models\ClubCharge;
use App\Models\Member;
use App\Models\MemberCharge;
use App\Models\MemberPayment;
use App\Models\PaymentAllocation;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Services\Finance\AccountingIntegrationConfigurationService;
use App\Services\Finance\FundCode;
use App\Services\Finance\SourceAccountingIntegrationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinanceWriteService
{
    public function __construct(private SourceAccountingIntegrationService $accounting) {}

    public function createCharge(User $u, Club $c, array $v): ClubCharge
    {
        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $v) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.manage');
            $this->enabledCategory($v['category']);
            $ids = $this->members($c, $v);
            $r = ClubCharge::create(['club_id' => $c->id, 'title' => $v['title'], 'description' => $v['description'] ?? null, 'category' => $v['category'], 'amount_paise' => $v['amount_paise'], 'due_date' => $v['due_date'], 'financial_year' => $v['financial_year'], 'status' => 'draft', 'assignment_member_ids' => $ids, 'created_by_user_id' => $u->id]);
            $this->audit($u, $c, 'finance.charge.created', $r, ['amount_paise' => $r->amount_paise, 'member_ids' => $ids]);

            return $r->refresh();
        });
    }

    public function updateCharge(User $u, Club $c, ClubCharge $r, array $v): ClubCharge
    {
        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $r, $v) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.manage');
            $this->enabledCategory($v['category']);
            $r = $this->lockCharge($c, $r, $v['lock_version']);
            if ($r->status !== 'draft') {
                throw new ConflictHttpException('Only Draft charges may be updated.');
            }$before = $r->only(['title', 'category', 'amount_paise', 'due_date', 'financial_year', 'assignment_member_ids']);
            $ids = $this->members($c, $v);
            $r->update(['title' => $v['title'], 'description' => $v['description'] ?? null, 'category' => $v['category'], 'amount_paise' => $v['amount_paise'], 'due_date' => $v['due_date'], 'financial_year' => $v['financial_year'], 'assignment_member_ids' => $ids, 'updated_by_user_id' => $u->id, 'lock_version' => $r->lock_version + 1]);
            $this->audit($u, $c, 'finance.charge.updated', $r, ['before' => $before, 'after' => $r->fresh()->only(array_keys($before))]);

            return $r->fresh();
        });
    }

    public function issue(User $u, Club $c, ClubCharge $r, int $version): ClubCharge
    {
        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $r, $version) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.manage');
            Club::query()->whereKey($c->id)->lockForUpdate()->firstOrFail();
            $r = ClubCharge::where('club_id', $c->id)->whereKey($r->id)->lockForUpdate()->firstOrFail();
            if ($r->club_id !== $c->id) {
                abort(404);
            }
            if ($r->status === 'issued') {
                $integration = $this->accounting->validateChargeReplayWithin($context, $r);
                if (($integration === null || $integration->source_version === $r->lock_version) && $version === $r->lock_version - 1) {
                    return $r;
                }
                throw new ConflictHttpException('Charge issuance replay conflicts with the committed source.');
            }
            if ($r->lock_version !== $version) {
                throw new ConflictHttpException('Stale lock version.');
            }
            if ($r->status !== 'draft') {
                throw new ConflictHttpException('Charge cannot be issued from its current state.');
            }$category = $this->enabledCategory($r->category);
            $members = Member::where('club_id', $c->id)->where('status', 'active')->whereIn('id', $r->assignment_member_ids)->get();
            if ($members->count() !== count($r->assignment_member_ids)) {
                throw ValidationException::withMessages(['member_ids' => 'All assigned members must remain active and same-club.']);
            }foreach ($members as $m) {
                MemberCharge::create(['club_id' => $c->id, 'club_charge_id' => $r->id, 'member_id' => $m->id, 'original_amount_paise' => $r->amount_paise]);
            }$r->update(['status' => 'issued', 'fund_code' => $category->fund_code, 'issued_at' => now(), 'issued_by_user_id' => $u->id, 'lock_version' => $r->lock_version + 1]);
            $requiresAccounting = $this->accounting->recordChargeCoverageDecisionWithin($context, $r->fresh(), $u);
            if ($requiresAccounting) {
                $this->accounting->postChargeWithin($context, $r->fresh(), $u);
            }
            $this->audit($u, $c, 'finance.charge.issued', $r, ['member_charge_count' => $members->count(), 'amount_paise' => $r->amount_paise]);

            return $r->fresh();
        });
    }

    public function close(User $u, Club $c, ClubCharge $r, int $version): ClubCharge
    {
        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $r, $version) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.manage');
            $r = $this->lockCharge($c, $r, $version);
            if ($r->status !== 'issued') {
                throw new ConflictHttpException('Only Issued charges may close.');
            }if ($r->memberCharges()->get()->contains(fn ($m) => $m->outstandingPaise() > 0)) {
                throw new ConflictHttpException('Charge still has outstanding obligations.');
            }$r->update(['status' => 'closed', 'closed_at' => now(), 'closed_by_user_id' => $u->id, 'lock_version' => $r->lock_version + 1]);
            $this->audit($u, $c, 'finance.charge.closed', $r, []);

            return $r->fresh();
        });
    }

    public function cancel(User $u, Club $c, ClubCharge $r, int $version, string $reason): ClubCharge
    {
        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $r, $version, $reason) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.manage');
            Club::query()->whereKey($c->id)->lockForUpdate()->firstOrFail();
            $r = $this->lockCharge($c, $r, $version);
            if (! in_array($r->status, ['draft', 'issued'], true)) {
                throw new ConflictHttpException('Charge is terminal.');
            }if ($r->status === 'issued' && PaymentAllocation::whereIn('member_charge_id', $r->memberCharges()->pluck('id'))->whereHas('payment', fn ($q) => $q->where('status', 'recorded'))->exists()) {
                throw new ConflictHttpException('Issued charge with payment allocations cannot be cancelled.');
            }
            if ($r->status === 'issued') {
                $sourceDate = $r->issued_at->toDateString();
                $sourceCommittedAt = $r->issued_at->toISOString();
                $r->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $u->id, 'cancellation_reason' => $reason, 'lock_version' => $r->lock_version + 1]);
                $this->accounting->reverseWithin($context, $c->id, AccountingIntegrationConfigurationService::SOURCE_CHARGE, $r->public_id, $sourceDate, $sourceCommittedAt, now()->toDateString(), $u, $reason);
            } else {
                $r->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_user_id' => $u->id, 'cancellation_reason' => $reason, 'lock_version' => $r->lock_version + 1]);
            }
            $this->audit($u, $c, 'finance.charge.cancelled', $r, ['reason' => $reason]);

            return $r->fresh();
        });
    }

    public function recordPayment(User $u, Club $c, Member $m, array $v): array
    {
        $hash = hash('sha256', json_encode($this->canonical($v), JSON_UNESCAPED_SLASHES));

        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $m, $v, $hash) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.manage');
            $m = Member::query()->where('club_id', $context->organisationId)->whereKey($m->id)->where('status', 'active')->firstOrFail();
            Club::query()->whereKey($c->id)->lockForUpdate()->firstOrFail();
            $existing = MemberPayment::where('club_id', $c->id)->where('idempotency_key', $v['idempotency_key'])->lockForUpdate()->first();
            if ($existing) {
                if (! hash_equals($existing->request_hash, $hash)) {
                    throw new ConflictHttpException('Idempotency key payload conflict.');
                }

                $this->accounting->validatePaymentReplayWithin($context, $existing);

                return ['payment' => $existing->load(['allocations', 'receipt']), 'replayed' => true];
            }
            $ids = array_column($v['allocations'], 'member_charge_id');
            if (count($ids) !== count(array_unique($ids))) {
                throw ValidationException::withMessages(['allocations' => 'MemberCharge IDs must be unique.']);
            }
            $charges = MemberCharge::where('club_id', $c->id)->where('member_id', $m->id)->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');
            if ($charges->count() !== count($ids)) {
                throw ValidationException::withMessages(['allocations' => 'Every obligation must belong to the route member and club.']);
            }
            $sum = 0;
            foreach ($v['allocations'] as $a) {
                $sum += $a['amount_paise'];
                if ($a['amount_paise'] < 1 || $a['amount_paise'] > $charges[$a['member_charge_id']]->outstandingPaise()) {
                    throw new ConflictHttpException('Allocation exceeds current outstanding amount.');
                }
            }
            if ($sum !== $v['amount_paise']) {
                throw ValidationException::withMessages(['allocations' => 'Allocation total must equal payment amount.']);
            }
            $p = MemberPayment::create(['club_id' => $c->id, 'member_id' => $m->id, 'amount_paise' => $v['amount_paise'], 'payment_date' => $v['payment_date'], 'payment_mode' => $v['payment_mode'], 'external_reference' => $v['external_reference'] ?? null, 'internal_note' => $v['internal_note'] ?? null, 'idempotency_key' => $v['idempotency_key'], 'request_hash' => $hash, 'status' => 'recorded', 'recorded_by_user_id' => $u->id]);
            foreach ($v['allocations'] as $a) {
                $fund = $charges[$a['member_charge_id']]->charge()->value('fund_code');
                if (! FundCode::tryFrom((string) $fund)) {
                    throw ValidationException::withMessages(['allocations' => 'Every obligation must have a frozen canonical fund.']);
                }
                PaymentAllocation::create(['club_id' => $c->id, 'member_payment_id' => $p->id, 'member_charge_id' => $a['member_charge_id'], 'amount_paise' => $a['amount_paise'], 'fund_code' => $fund]);
            }
            $fy = $this->fy($v['payment_date']);
            $receipt = PaymentReceipt::create(['club_id' => $c->id, 'member_payment_id' => $p->id, 'receipt_number' => $this->receiptNumber($c, $fy), 'receipt_date' => $v['payment_date'], 'financial_year' => $fy, 'status' => 'issued', 'issued_by_user_id' => $u->id]);
            $requiresAccounting = $this->accounting->recordPaymentCoverageDecisionWithin($context, $p->refresh(), $u);
            if ($requiresAccounting) {
                $this->accounting->postPaymentWithin($context, $p->refresh()->load('allocations'), $u);
            }
            $this->audit($u, $c, 'finance.payment.recorded', $p, ['member_id' => $m->id, 'amount_paise' => $p->amount_paise, 'allocation_ids' => $p->allocations()->pluck('id'), 'receipt_id' => $receipt->id]);

            return ['payment' => $p->refresh()->load(['allocations', 'receipt']), 'replayed' => false];
        });
    }

    private function enabledCategory(string $code): AccountingChargeCategory
    {
        $category = AccountingChargeCategory::query()->whereKey($code)->first();
        if (! $category || ! $category->is_accounting_enabled || ! FundCode::tryFrom((string) $category->fund_code)) {
            throw ValidationException::withMessages(['category' => 'CHARGE_CATEGORY_NOT_ACCOUNTING_ENABLED']);
        }

        return $category;
    }

    public function void(User $u, Club $c, MemberPayment $p, int $version, string $reason, ?int $replacement = null): MemberPayment
    {
        return app(OrganisationTransactionGuard::class)->run($c, function (OrganisationTransactionContext $context) use ($u, $c, $p, $version, $reason, $replacement) {
            app(TransactionActorAuthorisation::class)->requirePermission($context, $c->id, $u, 'finance.void');
            Club::query()->whereKey($c->id)->lockForUpdate()->firstOrFail();
            $p = MemberPayment::where('club_id', $c->id)->whereKey($p->id)->lockForUpdate()->firstOrFail();
            if ($p->club_id !== $c->id) {
                abort(404);
            }if ($p->lock_version !== $version) {
                throw new ConflictHttpException('Stale lock version.');
            }if ($p->status !== 'recorded') {
                throw new ConflictHttpException('Payment is already voided.');
            }foreach ($p->allocations as $a) {
                MemberCharge::where('club_id', $c->id)->whereKey($a->member_charge_id)->lockForUpdate()->first();
            }if ($replacement) {
                $r = MemberPayment::where('club_id', $c->id)->where('member_id', $p->member_id)->whereKey($replacement)->firstOrFail();
                if ($r->status !== 'recorded') {
                    throw new ConflictHttpException('Replacement must be recorded.');
                }
            }
            $p->update(['status' => 'voided', 'voided_at' => now(), 'voided_by_user_id' => $u->id, 'void_reason' => $reason, 'replacement_payment_id' => $replacement, 'lock_version' => $p->lock_version + 1]);
            $p->receipt()->update(['status' => 'voided', 'voided_at' => now()]);
            $this->accounting->reverseWithin($context, $c->id, AccountingIntegrationConfigurationService::SOURCE_PAYMENT, $p->public_id, $p->payment_date->format('Y-m-d'), $p->created_at->toISOString(), now()->toDateString(), $u, $reason);
            $this->audit($u, $c, 'finance.payment.voided', $p, ['reason' => $reason, 'replacement_payment_id' => $replacement]);
            if ($replacement) {
                $this->audit($u, $c, 'finance.payment.replaced', $p, ['replacement_payment_id' => $replacement]);
            }

            return $p->fresh()->load(['allocations', 'receipt']);
        });
    }

    private function lockCharge(Club $c, ClubCharge $r, int $v): ClubCharge
    {
        $r = ClubCharge::where('club_id', $c->id)->whereKey($r->id)->lockForUpdate()->firstOrFail();
        if ($r->club_id !== $c->id) {
            abort(404);
        }if ($r->lock_version !== $v) {
            throw new ConflictHttpException('Stale lock version.');
        }

        return $r;
    }

    private function members(Club $c, array $v): array
    {
        $ids = $v['assign_all_active_members'] ?? false ? Member::where('club_id', $c->id)->where('status', 'active')->pluck('id')->all() : ($v['member_ids'] ?? []);
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (! $ids || Member::where('club_id', $c->id)->where('status', 'active')->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['member_ids' => 'Assignments require unique active same-club members.']);
        }

        return $ids;
    }

    private function canonical(array $v): array
    {
        $v['allocations'] = collect($v['allocations'])->sortBy('member_charge_id')->values()->all();
        ksort($v);

        return $v;
    }

    private function fy(string $d): string
    {
        $x = new \DateTimeImmutable($d);
        $y = (int) $x->format('Y');
        if ((int) $x->format('n') < 4) {
            $y--;
        }

        return $y.'-'.substr((string) ($y + 1), -2);
    }

    private function receiptNumber(Club $c, string $fy): string
    {
        $code = preg_replace('/[^A-Z0-9]/', '', strtoupper($c->code)) ?: 'CLUB'.$c->id;
        $prefix = "$code/$fy/RCP/";
        $last = PaymentReceipt::where('club_id', $c->id)->where('receipt_number', 'like', $prefix.'%')->lockForUpdate()->orderByDesc('id')->value('receipt_number');
        $n = $last && preg_match('/(\\d+)$/', $last, $m) ? (int) $m[1] + 1 : 1;

        return $prefix.str_pad((string) $n, 4, '0', STR_PAD_LEFT);
    }

    private function audit(User $u, Club $c, string $action, $target, array $meta): void
    {
        AuditLog::create(['club_id' => $c->id, 'user_id' => $u->id, 'action' => $action, 'auditable_type' => $target::class, 'auditable_id' => $target->id, 'metadata' => $meta]);
    }
}
