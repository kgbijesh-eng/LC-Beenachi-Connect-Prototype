<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Models\ClubAccountingCutoverAdminApproval;
use App\Models\ClubAccountingCutoverAdminOperation;
use App\Models\ClubAccountingCutoverAuthorityAppointment;
use App\Models\ClubAccountingCutoverAuthorityConsumption;
use App\Models\ClubAccountingCutoverAuthorityEvent;
use App\Models\ClubAccountingCutoverCaEligibility;
use App\Models\ClubAccountingCutoverPackage;
use App\Models\ClubAccountingCutoverPermissionAssignment;
use App\Models\ClubAccountingCutoverPermissionEvent;
use App\Models\ClubMembership;
use App\Models\Permission;
use App\Models\User;
use App\Services\Access\OrganisationTransactionContext;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Finance\Exceptions\CutoverAuthorizationException;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;

final class ClubCutoverAuthorityAdministrationService
{
    public function __construct(
        private readonly ClubCutoverAuthorityResolver $authority,
        private readonly OrganisationTransactionGuard $organisations,
    ) {}

    public function requestPermission(ClubAccountingCutoverPackage $package, User $subject, string $slug, string $reference, User $requester, ?string $expiresAt = null): ClubAccountingCutoverAdminOperation
    {
        return $this->withPackage($package, function (ClubAccountingCutoverPackage $current, OrganisationTransactionContext $context) use ($package, $subject, $slug, $reference, $requester, $expiresAt) {
            $permission = Permission::query()->where('slug', $slug)->first();
            if (! $permission || ! str_starts_with($slug, 'finance.cutover.package.')) {
                throw new CutoverAuthorizationException('The exact cutover permission is undefined.');
            }
            $this->assertPermissionRoleBasis($current, $subject, $slug);
            $this->assertNoConflict($current, $subject, $slug);

            return $this->request($context, $package, $requester, 'assign_permission', $subject, 'permission', $slug, $reference, null, $expiresAt);
        });
    }

    public function requestAppointment(ClubAccountingCutoverPackage $package, User $subject, string $type, string $reference, User $requester, ?string $expiresAt = null): ClubAccountingCutoverAdminOperation
    {
        return $this->withPackage($package, function (ClubAccountingCutoverPackage $current, OrganisationTransactionContext $context) use ($package, $subject, $type, $reference, $requester, $expiresAt) {
            if (! in_array($type, [ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA, ClubAccountingCutoverAuthorityAppointment::TYPE_TECHNICAL_OPERATOR], true)) {
                throw new CutoverAuthorizationException('The cutover authority type is invalid.');
            }
            $this->assertNoConflict($current, $subject, $type);

            return $this->request($context, $package, $requester, 'appoint', $subject, 'appointment', $type, $reference, null, $expiresAt);
        });
    }

    public function requestAppointmentRevocation(ClubAccountingCutoverAuthorityAppointment $target, string $reference, string $reason, User $requester): ClubAccountingCutoverAdminOperation
    {
        return $this->organisations->run($target->club_id, function (OrganisationTransactionContext $context) use ($target, $reference, $reason, $requester) {
            $package = $this->lockPackage($context, $target->cutover_package_id);
            $current = ClubAccountingCutoverAuthorityAppointment::whereKey($target->id)->where('club_id', $context->organisationId)->where('cutover_package_id', $package->id)->firstOrFail();
            return $this->request($context, $package, $requester, 'revoke_appointment', $current->appointedUser, 'appointment_record', (string) $current->id, $reference, $reason);
        }, attempts: 1);
    }

    public function requestPermissionRevocation(ClubAccountingCutoverPermissionAssignment $target, string $reference, string $reason, User $requester): ClubAccountingCutoverAdminOperation
    {
        return $this->organisations->run($target->club_id, function (OrganisationTransactionContext $context) use ($target, $reference, $reason, $requester) {
            $package = $this->lockPackage($context, $target->cutover_package_id);
            $current = ClubAccountingCutoverPermissionAssignment::whereKey($target->id)->where('club_id', $context->organisationId)->where('cutover_package_id', $package->id)->firstOrFail();
            return $this->request($context, $package, $requester, 'revoke_permission', User::findOrFail($current->user_id), 'permission_record', (string) $current->id, $reference, $reason);
        }, attempts: 1);
    }

    public function requestCaEligibility(ClubAccountingCutoverAuthorityAppointment $appointment, array $externalReferences, string $checkedOn, string $independenceState, string $reference, User $requester, ?string $digest = null, ?string $expiresAt = null): ClubAccountingCutoverAdminOperation
    {
        if (! in_array($independenceState, ['confirmed', 'not_confirmed'], true) || count($externalReferences) < 5 || ! preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $checkedOn) || ($digest !== null && ! preg_match('/\A[a-f0-9]{64}\z/', $digest))) {
            throw new CutoverAuthorizationException('The CA eligibility request is invalid.');
        }
        foreach ($externalReferences as $key => $value) {
            if (! in_array($key, ['professional_qualification', 'identity', 'professional_standing', 'engagement', 'independence'], true) || ! $this->safeOpaque((string) $value)) {
                throw new CutoverAuthorizationException('Sensitive or incomplete CA evidence metadata is prohibited.');
            }
        }
        if (strlen(json_encode($externalReferences, JSON_THROW_ON_ERROR)) > 500) {
            throw new CutoverAuthorizationException('The CA evidence reference set exceeds the safe metadata boundary.');
        }
        return $this->organisations->run($appointment->club_id, function (OrganisationTransactionContext $context) use ($appointment, $externalReferences, $checkedOn, $independenceState, $reference, $requester, $digest, $expiresAt) {
            $package = $this->lockPackage($context, $appointment->cutover_package_id);
            $current = ClubAccountingCutoverAuthorityAppointment::whereKey($appointment->id)->where('club_id', $context->organisationId)->where('cutover_package_id', $package->id)->firstOrFail();
            if ($current->authority_type !== ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA) {
                throw new CutoverAuthorizationException('The CA eligibility request is invalid.');
            }
            return $this->request($context, $package, $requester, 'attest_ca_eligibility', $current->appointedUser, 'ca_eligibility', (string) $current->id, $reference, json_encode(['references' => $externalReferences, 'checked_on' => $checkedOn, 'independence_state' => $independenceState, 'digest' => $digest, 'expires_at' => $expiresAt], JSON_THROW_ON_ERROR));
        }, attempts: 1);
    }

    public function approve(ClubAccountingCutoverAdminOperation $operation, User $actor, string $approvalReference): ClubAccountingCutoverAdminOperation
    {
        return $this->organisations->run($operation->club_id, function (OrganisationTransactionContext $context) use ($operation, $actor, $approvalReference) {
            $operation = ClubAccountingCutoverAdminOperation::query()->whereKey($operation->id)->where('club_id', $context->organisationId)->lockForUpdate()->firstOrFail();
            if ($operation->status === 'applied') {
                return $operation;
            }
            $package = $this->lockPackage($context, $operation->cutover_package_id);
            if ($operation->package_version !== $package->package_version || $operation->package_lock_version !== $package->lock_version) {
                throw new CutoverAuthorizationException('The administration operation is stale for the current package version.');
            }
            $role = $this->authority->governanceOfficerRole($package, $actor);
            if ($operation->action === 'appoint' && $operation->target_key === ClubAccountingCutoverAuthorityAppointment::TYPE_INDEPENDENT_CA && $operation->subject_user_id === $actor->id) {
                throw new CutoverAuthorizationException('An Independent CA appointment subject cannot approve their own appointment.');
            }
            if ($operation->approvals()->where(fn ($query) => $query->where('approval_role', $role)->orWhere('approved_by_user_id', $actor->id))->exists()) {
                throw new CutoverAuthorizationException('Each governance role and actor may approve an operation only once.');
            }
            ClubAccountingCutoverAdminApproval::create(['club_id' => $operation->club_id, 'cutover_package_id' => $operation->cutover_package_id, 'admin_operation_id' => $operation->id, 'approval_role' => $role, 'approved_by_user_id' => $actor->id, 'approval_reference' => $approvalReference, 'operation_digest' => $operation->request_digest, 'approved_at' => now()]);
            if ($operation->approvals()->count() === 2) {
                $this->apply($context, $operation, $package);
            }

            return $operation->fresh('approvals');
        }, attempts: 1);
    }

    public function selfRevokeAppointment(ClubAccountingCutoverAuthorityAppointment $target, User $actor, string $reference, string $reason): ClubAccountingCutoverAuthorityEvent
    {
        return $this->organisations->run($target->club_id, function (OrganisationTransactionContext $context) use ($target, $actor, $reference, $reason): ClubAccountingCutoverAuthorityEvent {
            $package = $this->lockPackage($context, $target->cutover_package_id);
            $current = ClubAccountingCutoverAuthorityAppointment::query()->with('events')->whereKey($target->id)->where('club_id', $context->organisationId)->where('cutover_package_id', $package->id)->lockForUpdate()->first();
            if (! $current || $current->cutover_package_id !== $package->id || $current->club_id !== $package->club_id
                || $current->appointed_user_id !== $actor->id || $current->effective_at->isFuture()
                || ($current->expires_at !== null && ! $current->expires_at->isFuture())
                || $current->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty()
                || ClubAccountingCutoverAuthorityConsumption::where('cutover_package_id', $package->id)->exists()) {
                throw new CutoverAuthorizationException('Only the holder may revoke their own current unconsumed appointment.');
            }

            return ClubAccountingCutoverAuthorityEvent::create(['club_id' => $current->club_id, 'cutover_package_id' => $current->cutover_package_id, 'authority_appointment_id' => $current->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
        }, attempts: 1);
    }

    public function selfRevokePermission(ClubAccountingCutoverPermissionAssignment $target, User $actor, string $reference, string $reason): ClubAccountingCutoverPermissionEvent
    {
        return $this->organisations->run($target->club_id, function (OrganisationTransactionContext $context) use ($target, $actor, $reference, $reason): ClubAccountingCutoverPermissionEvent {
            $package = $this->lockPackage($context, $target->cutover_package_id);
            $current = ClubAccountingCutoverPermissionAssignment::query()->with('events')->whereKey($target->id)->where('club_id', $context->organisationId)->where('cutover_package_id', $package->id)->lockForUpdate()->first();
            if (! $current || $current->cutover_package_id !== $package->id || $current->club_id !== $package->club_id
                || $current->user_id !== $actor->id || $current->effective_at->isFuture()
                || ($current->expires_at !== null && ! $current->expires_at->isFuture())
                || $current->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty()
                || ClubAccountingCutoverAuthorityConsumption::where('cutover_package_id', $package->id)->exists()) {
                throw new CutoverAuthorizationException('Only the holder may revoke their own current unconsumed permission.');
            }

            return ClubAccountingCutoverPermissionEvent::create(['club_id' => $current->club_id, 'cutover_package_id' => $current->cutover_package_id, 'permission_assignment_id' => $current->id, 'event_type' => 'revoked', 'event_reference' => $reference, 'reason' => $reason, 'recorded_by_user_id' => $actor->id, 'recorded_at' => now()]);
        }, attempts: 1);
    }

    /** Legacy single-grantor entry points are deliberately fail closed. */
    public function appoint(ClubAccountingCutoverPackage $p, User $u, string $t, string $r, User $g, ?string $e = null): never
    {
        throw new CutoverAuthorizationException('A dual-approved appointment operation is required.');
    }

    public function assignPermission(ClubAccountingCutoverPackage $p, User $u, string $s, string $r, User $g, ?string $e = null): never
    {
        throw new CutoverAuthorizationException('A dual-approved permission operation is required.');
    }

    public function revokeAppointment(ClubAccountingCutoverAuthorityAppointment $a, User $u, string $r, string $n): never
    {
        throw new CutoverAuthorizationException('Use self-revocation or a dual-approved revocation operation.');
    }

    public function revokePermission(ClubAccountingCutoverPermissionAssignment $a, User $u, string $r, string $n): never
    {
        throw new CutoverAuthorizationException('Use self-revocation or a dual-approved revocation operation.');
    }

    private function request(OrganisationTransactionContext $context, ClubAccountingCutoverPackage $p, User $requester, string $action, User $subject, string $type, string $key, string $reference, ?string $note = null, ?string $expiresAt = null): ClubAccountingCutoverAdminOperation
    {
        $context->assertUsable($p->club_id);
        // Preserve the supplied version/lock binding, including exact stale replay.
        $storedKey = $note === null ? $key : $key.'|'.base64_encode($note);
        $binding = ['club_id' => $p->club_id, 'cutover_package_id' => $p->id, 'package_version' => $p->package_version, 'package_lock_version' => $p->lock_version, 'action' => $action, 'subject_user_id' => $subject->id, 'target_type' => $type, 'target_key' => $storedKey, 'effective_at' => null, 'expires_at' => $expiresAt];
        $digest = $this->digest($binding);
        $existing = ClubAccountingCutoverAdminOperation::where('club_id', $context->organisationId)->where('operation_reference', $reference)->first();
        if ($existing) {
            if (! hash_equals($existing->request_digest, $digest)) {
                throw new CutoverAuthorizationException('The operation reference was reused with changed semantics.');
            }

            return $existing;
        }

        try {
            return ClubAccountingCutoverAdminOperation::create([...$binding, 'operation_reference' => $reference, 'request_digest' => $digest, 'status' => 'pending', 'requested_by_user_id' => $requester->id, 'requested_at' => now()]);
        } catch (UniqueConstraintViolationException $exception) {
            // A globally used reference in another organisation must not disclose its record.
            throw new CutoverAuthorizationException('The operation reference was reused with changed semantics.', previous: $exception);
        }
    }

    private function apply(OrganisationTransactionContext $context, ClubAccountingCutoverAdminOperation $o, ClubAccountingCutoverPackage $p): void
    {
        $context->assertUsable($p->club_id);
        $record = null;
        if ($o->action === 'assign_permission') {
            $permission = Permission::where('slug', $o->target_key)->firstOrFail();
            $this->assertPermissionRoleBasis($p, User::findOrFail($o->subject_user_id), $o->target_key);
            $this->assertNoConflict($p, User::findOrFail($o->subject_user_id), $o->target_key);
            $record = ClubAccountingCutoverPermissionAssignment::create(['club_id' => $p->club_id, 'cutover_package_id' => $p->id, 'user_id' => $o->subject_user_id, 'permission_id' => $permission->id, 'assignment_reference' => $o->operation_reference, 'effective_at' => now(), 'expires_at' => $o->expires_at, 'assigned_by_user_id' => $o->approvals()->where('approval_role', 'secretary')->value('approved_by_user_id')]);
        } elseif ($o->action === 'appoint') {
            $subject = User::findOrFail($o->subject_user_id);
            $this->assertNoConflict($p, $subject, $o->target_key);
            $record = ClubAccountingCutoverAuthorityAppointment::create(['club_id' => $p->club_id, 'cutover_package_id' => $p->id, 'appointed_user_id' => $subject->id, 'authority_type' => $o->target_key, 'appointment_reference' => $o->operation_reference, 'effective_at' => now(), 'expires_at' => $o->expires_at, 'appointed_by_user_id' => $o->approvals()->where('approval_role', 'secretary')->value('approved_by_user_id')]);
        } elseif (in_array($o->action, ['revoke_appointment', 'revoke_permission'], true)) {
            if (ClubAccountingCutoverAuthorityConsumption::where('cutover_package_id', $p->id)->exists()) {
                throw new CutoverAuthorizationException('Consumed cutover authority cannot be revoked or made reusable.');
            } [$id,$encoded] = array_pad(explode('|', $o->target_key, 2), 2, '');
            $reason = base64_decode($encoded, true) ?: 'Governance revocation';
            if ($o->action === 'revoke_appointment') {
                $target = ClubAccountingCutoverAuthorityAppointment::whereKey($id)->where('club_id', $context->organisationId)->where('cutover_package_id', $p->id)->firstOrFail();
                if ($target->events()->whereIn('event_type', ['revoked', 'invalidated'])->exists()) {
                    throw new CutoverAuthorizationException('The appointment is not current.');
                } $record = ClubAccountingCutoverAuthorityEvent::create(['club_id' => $p->club_id, 'cutover_package_id' => $p->id, 'authority_appointment_id' => $target->id, 'event_type' => 'revoked', 'event_reference' => $o->operation_reference, 'reason' => $reason, 'recorded_by_user_id' => $o->approvals()->where('approval_role', 'secretary')->value('approved_by_user_id'), 'recorded_at' => now()]);
            } else {
                $target = ClubAccountingCutoverPermissionAssignment::whereKey($id)->where('club_id', $context->organisationId)->where('cutover_package_id', $p->id)->firstOrFail();
                if ($target->events()->whereIn('event_type', ['revoked', 'invalidated'])->exists()) {
                    throw new CutoverAuthorizationException('The permission assignment is not current.');
                } $record = ClubAccountingCutoverPermissionEvent::create(['club_id' => $p->club_id, 'cutover_package_id' => $p->id, 'permission_assignment_id' => $target->id, 'event_type' => 'revoked', 'event_reference' => $o->operation_reference, 'reason' => $reason, 'recorded_by_user_id' => $o->approvals()->where('approval_role', 'secretary')->value('approved_by_user_id'), 'recorded_at' => now()]);
            }
        } elseif ($o->action === 'attest_ca_eligibility') {
            [$id,$encoded] = explode('|', $o->target_key, 2);
            $data = json_decode(base64_decode($encoded), true, flags: JSON_THROW_ON_ERROR);
            ClubAccountingCutoverAuthorityAppointment::whereKey($id)->where('club_id', $context->organisationId)->where('cutover_package_id', $p->id)->firstOrFail();
            $record = ClubAccountingCutoverCaEligibility::create(['club_id' => $p->club_id, 'cutover_package_id' => $p->id, 'authority_appointment_id' => $id, 'external_reference_set' => json_encode($data['references'], JSON_THROW_ON_ERROR), 'external_reference_digest' => $data['digest'], 'checked_on' => $data['checked_on'], 'independence_state' => $data['independence_state'], 'expires_at' => $data['expires_at'], 'admin_operation_id' => $o->id, 'eligible_at' => now()]);
        }
        $o->update(['status' => 'applied', 'target_record_id' => $record?->id, 'applied_at' => now()]);
    }

    private function withPackage(ClubAccountingCutoverPackage $binding, Closure $operation): mixed
    {
        return $this->organisations->run($binding->club_id, function (OrganisationTransactionContext $context) use ($binding, $operation) {
            return $operation($this->lockPackage($context, $binding->id), $context);
        }, attempts: 1);
    }

    private function lockPackage(OrganisationTransactionContext $context, int $packageId): ClubAccountingCutoverPackage
    {
        $context->assertUsable();
        return ClubAccountingCutoverPackage::whereKey($packageId)->where('club_id', $context->organisationId)->lockForUpdate()->firstOrFail();
    }

    private function assertNoConflict(ClubAccountingCutoverPackage $p, User $u, string $authority): void
    {
        $president = $this->authority->uniqueOfficer($p, 'president-admin');
        $ids = array_filter([$p->prepared_by_user_id, $p->internally_verified_by_user_id]);
        $isSecretary = ClubMembership::query()->where('club_id', $p->club_id)->where('user_id', $u->id)->where('status', 'active')
            ->whereHas('role', fn ($role) => $role->where('slug', 'secretary'))->exists();
        if (in_array($authority, ['independent_ca', 'technical_operator'], true) && (in_array($u->id, $ids, true) || $u->id === $president->id || $isSecretary)) {
            throw new CutoverAuthorizationException('The requested authority conflicts with package separation of duties.');
        }
        $other = $authority === 'independent_ca' ? 'technical_operator' : ($authority === 'technical_operator' ? 'independent_ca' : null);
        if ($other && ClubAccountingCutoverAuthorityAppointment::with('events')->where('cutover_package_id', $p->id)->where('appointed_user_id', $u->id)->where('authority_type', $other)
            ->where('effective_at', '<=', now())->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->get()
            ->contains(fn ($appointment) => $appointment->events->whereIn('event_type', ['revoked', 'invalidated'])->isEmpty())) {
            throw new CutoverAuthorizationException('The requested authority conflicts with another appointment.');
        }
    }

    private function assertPermissionRoleBasis(ClubAccountingCutoverPackage $p, User $u, string $slug): void
    {
        $membership = ClubMembership::with('role')->where('club_id', $p->club_id)->where('user_id', $u->id)->where('status', 'active')->first();
        $role = $membership?->role?->slug;
        $appointmentTypes = ClubAccountingCutoverAuthorityAppointment::with('events')->where('cutover_package_id', $p->id)->where('appointed_user_id', $u->id)->get()->reject(fn ($a) => $a->events->whereIn('event_type', ['revoked', 'invalidated'])->isNotEmpty())->pluck('authority_type');
        $allowed = match ($slug) {
            'finance.cutover.package.prepare' => $role === 'treasurer',
            'finance.cutover.package.validate' => in_array($role, ['treasurer', 'secretary'], true),
            'finance.cutover.package.verify' => $role === 'secretary',
            'finance.cutover.package.accounting-review' => $appointmentTypes->contains('independent_ca'),
            'finance.cutover.package.business-approve' => $role === 'president-admin',
            'finance.cutover.package.dry-run-accept','finance.cutover.package.activate-authorize','finance.cutover.package.rollback-authorize' => $role === 'president-admin' || $appointmentTypes->contains('independent_ca'),
            'finance.cutover.package.execute' => $appointmentTypes->contains('technical_operator'),
            'finance.cutover.package.view-restricted-manifest' => $role === 'secretary' || $appointmentTypes->contains('independent_ca'),
            default => false,
        };
        if (! $allowed) {
            throw new CutoverAuthorizationException('The subject lacks the approved role or appointment basis for this package permission.');
        }
    }

    private function digest(array $v): string
    {
        foreach ($v as $k => $x) {
            if ($x instanceof \DateTimeInterface) {
                $v[$k] = $x->format(DATE_ATOM);
            }
        } ksort($v);

        return hash('sha256', json_encode($v, JSON_THROW_ON_ERROR));
    }

    private function safeOpaque(string $v): bool
    {
        return preg_match('/\A[A-Z0-9][A-Z0-9._:-]{5,119}\z/i', $v) === 1 && ! preg_match('~https?://|file:|password|secret|token|aadhaar|passport|pan[ :_-]~i', $v);
    }
}
