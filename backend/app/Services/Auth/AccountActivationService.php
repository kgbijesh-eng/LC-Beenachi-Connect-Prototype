<?php

namespace App\Services\Auth;

use App\Models\AccountActivation;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\User;
use App\Services\Access\OrganisationTransactionGuard;
use App\Services\Access\TransactionActorAuthorisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountActivationService
{
    public const PERMISSION_ISSUE = 'members.account_activation.issue';

    public function __construct(
        private readonly OrganisationTransactionGuard $transactions,
        private readonly UserCredentialLock $credentials,
        private readonly TransactionActorAuthorisation $actorAuthorisation,
    ) {}

    public function issue(
        Club $club,
        string $memberCode,
        string $intendedEmail,
        string $method,
        ?User $actor
    ): array {
        $email = $this->normalizeEmail($intendedEmail);
        $plainToken = Str::random(64);
        $tokenHash = self::tokenHash($plainToken);

        $activation = $this->transactions->run($club, function ($context) use ($club, $memberCode, $email, $method, $actor, $tokenHash): AccountActivation {
            if ($method === 'authenticated' && $actor !== null) {
                $this->actorAuthorisation->requirePermission($context, $club->id, $actor, self::PERMISSION_ISSUE);
            } elseif ($method !== 'bootstrap' || $actor !== null || ! config('account_activation.bootstrap_enabled')) {
                throw new AccountActivationException('Activation issuance is not authorized.');
            }

            $member = Member::query()->where('club_id', $club->id)
                ->where('member_code', $memberCode)->lockForUpdate()->first();
            if (! $member || ! $member->user_id) {
                throw new AccountActivationException('The target linkage is missing or ambiguous.');
            }
            $this->credentials->acquire(DB::connection(), $member->user_id);
            $target = $this->resolveEligibleTargetByUser($club, $member->user_id, $email, $member);

            $now = now();
            $superseded = AccountActivation::query()
                ->where('user_id', $target['user']->id)
                ->where('club_id', $club->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            foreach ($superseded as $previous) {
                $previous->forceFill(['revoked_at' => $now])->save();
                $this->audit($previous, 'account_activation.superseded', $actor?->id, [
                    'issuance_method' => $method,
                    'target_user_id' => $target['user']->id,
                ]);
            }

            $activation = AccountActivation::query()->create([
                'user_id' => $target['user']->id,
                'club_id' => $club->id,
                'intended_email' => $email,
                'token_hash' => $tokenHash,
                'issuing_actor_user_id' => $actor?->id,
                'issuance_method' => $method,
                'issued_at' => $now,
                'expires_at' => $now->copy()->addMinutes(max(5, (int) config('account_activation.expires_minutes', 30))),
            ]);

            $this->audit($activation, 'account_activation.issued', $actor?->id, [
                'issuance_method' => $method,
                'target_user_id' => $target['user']->id,
            ]);

            return $activation;
        });

        return [
            'activation' => $activation,
            'link' => rtrim((string) config('account_activation.frontend_url'), '/')
                .'/activate#token='.rawurlencode($plainToken),
        ];
    }

    public function complete(string $plainToken, string $email, string $password): void
    {
        $normalizedEmail = $this->normalizeEmail($email);

        $hash = self::tokenHash($plainToken);
        $candidate = AccountActivation::query()->useWritePdo()->where('token_hash', $hash)->first();
        if (! $candidate) {
            throw new AccountActivationException('Activation could not be completed.');
        }

        $this->transactions->run($candidate->club_id, function () use ($candidate, $hash, $normalizedEmail, $password): void {
            $this->credentials->acquire(DB::connection(), $candidate->user_id);
            $grants = AccountActivation::query()
                ->where('club_id', $candidate->club_id)
                ->where('user_id', $candidate->user_id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->orderBy('id')->lockForUpdate()->get();
            $activation = $grants->firstWhere('id', $candidate->id);

            if (! $activation || ! hash_equals($activation->token_hash, $hash)
                || $activation->expires_at->isPast()
                || ! hash_equals($activation->intended_email, $normalizedEmail)) {
                throw new AccountActivationException('Activation could not be completed.');
            }

            $club = Club::query()->findOrFail($candidate->club_id);
            $user = $this->resolveEligibleTargetByUser($club, $activation->user_id, $normalizedEmail)['user'];

            $user->forceFill([
                'email' => $normalizedEmail,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ])->save();

            $now = now();
            $activation->forceFill(['consumed_at' => $now])->save();
            foreach ($grants->where('id', '!=', $activation->id) as $other) {
                $other->forceFill(['revoked_at' => $now])->save();
                $this->audit($other, 'account_activation.revoked', $user->id, [
                    'target_user_id' => $user->id,
                    'reason' => 'activation_completed',
                ]);
            }
            $user->tokens()->delete();
            $this->audit($activation, 'account_activation.consumed', $user->id, [
                'target_user_id' => $user->id,
            ]);
        }, 3);
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function resolveEligibleTargetByUser(Club $club, int $userId, string $email, ?Member $expectedMember = null): array
    {
        $user = User::query()->find($userId);
        $members = Member::query()->where('club_id', $club->id)->where('user_id', $userId)->get();
        $memberships = ClubMembership::query()->with('role')->where('club_id', $club->id)->where('user_id', $userId)->get();
        if (! $user || $members->count() !== 1 || $memberships->count() !== 1 || ! $memberships->first()->role) {
            throw new AccountActivationException('The target linkage is missing or ambiguous.');
        }
        $member = $members->first();
        $membership = $memberships->first();
        if (($expectedMember && $member->id !== $expectedMember->id)
            || strtolower((string) $member->status) !== 'active'
            || strtolower((string) $membership->status) !== 'active') {
            throw new AccountActivationException('The target is not eligible.');
        }
        if (User::query()->whereRaw('LOWER(email) = ?', [$email])->whereKeyNot($user->id)->exists()) {
            throw new AccountActivationException('The operational email is unavailable.');
        }

        return compact('user', 'member', 'membership');
    }

    private function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function audit(AccountActivation $activation, string $action, ?int $actorId, array $metadata): void
    {
        AuditLog::query()->create([
            'club_id' => $activation->club_id,
            'user_id' => $actorId,
            'action' => $action,
            'auditable_type' => AccountActivation::class,
            'auditable_id' => $activation->id,
            'metadata' => $metadata,
        ]);
    }
}
