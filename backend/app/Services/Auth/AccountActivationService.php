<?php

namespace App\Services\Auth;

use App\Models\AccountActivation;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMembership;
use App\Models\Member;
use App\Models\User;
use App\Services\Access\InstanceOrganisation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AccountActivationService
{
    public const PERMISSION_ISSUE = 'members.account_activation.issue';

    public function issue(
        Club $club,
        string $memberCode,
        string $intendedEmail,
        string $method,
        ?User $actor
    ): array {
        $email = $this->normalizeEmail($intendedEmail);
        $target = $this->resolveEligibleTarget($club, $memberCode, $email);
        $plainToken = Str::random(64);
        $tokenHash = self::tokenHash($plainToken);

        $activation = DB::transaction(function () use ($club, $target, $email, $method, $actor, $tokenHash): AccountActivation {
            $now = now();
            $superseded = AccountActivation::query()
                ->where('user_id', $target['user']->id)
                ->where('club_id', $club->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
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

        DB::transaction(function () use ($plainToken, $normalizedEmail, $password): void {
            $activation = AccountActivation::query()
                ->where('token_hash', self::tokenHash($plainToken))
                ->lockForUpdate()
                ->first();

            if (! $activation
                || $activation->consumed_at
                || $activation->revoked_at
                || $activation->expires_at->isPast()
                || ! hash_equals($activation->intended_email, $normalizedEmail)) {
                throw new AccountActivationException('Activation could not be completed.');
            }

            $club = Club::query()->find($activation->club_id);
            if (! $club) {
                throw new AccountActivationException('Activation could not be completed.');
            }
            $target = $this->resolveEligibleTargetByUser($club, $activation->user_id, $normalizedEmail);
            $user = User::query()->lockForUpdate()->find($target['user']->id);
            if (! $user) {
                throw new AccountActivationException('Activation could not be completed.');
            }

            $user->forceFill([
                'email' => $normalizedEmail,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ])->save();

            $now = now();
            $activation->forceFill(['consumed_at' => $now])->save();
            $remaining = AccountActivation::query()
                ->where('user_id', $user->id)
                ->where('club_id', $club->id)
                ->whereKeyNot($activation->id)
                ->whereNull('consumed_at')
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get();
            foreach ($remaining as $other) {
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

    private function resolveEligibleTarget(Club $club, string $memberCode, string $email): array
    {
        $members = Member::query()
            ->where('club_id', $club->id)
            ->where('member_code', $memberCode)
            ->get();
        if ($members->count() !== 1 || ! $members->first()->user_id) {
            throw new AccountActivationException('The target linkage is missing or ambiguous.');
        }

        return $this->resolveEligibleTargetByUser($club, (int) $members->first()->user_id, $email, $members->first());
    }

    private function resolveEligibleTargetByUser(Club $club, int $userId, string $email, ?Member $expectedMember = null): array
    {
        app(InstanceOrganisation::class)->assertClub($club);
        if (! $club->is_active) {
            throw new AccountActivationException('The target is not eligible.');
        }
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
