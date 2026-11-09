<?php

namespace App\Services\ProfileUpdates;

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\Member;
use App\Models\ProfileUpdateRequest;
use App\Models\ProfileUpdateRequestChange;
use App\Models\User;
use App\Services\Access\OrganisationTransactionGuard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ProfileUpdateWriteService
{
    public function __construct(private readonly OrganisationTransactionGuard $transactions) {}

    private const RESTRICTED = ['birth_date', 'spouse_name', 'wedding_anniversary', 'phone', 'home_phone', 'alternate_phone', 'address_line_1', 'address_line_2', 'city_locality', 'district', 'state', 'postal_code', 'country', 'blood_group', 'blood_donor_consent'];

    public function submit(Club $club, User $user, array $input): ProfileUpdateRequest
    {
        return $this->transactions->run($club, function () use ($club, $user, $input) {
            $member = $this->ownMember($club, $user, true);
            if (ProfileUpdateRequest::where('club_id', $club->id)->where('member_id', $member->id)->where('status', 'pending')->lockForUpdate()->exists()) {
                throw new ConflictHttpException('A pending profile update request already exists.');
            }

            $changes = [];
            foreach ($input['changes'] as $field => $value) {
                $proposed = $this->normalize($field, $value);
                $this->validate($field, $proposed);
                $original = $this->value($member, $field);
                if ($original !== $proposed) {
                    $changes[$field] = [$original, $proposed];
                }
            }

            if (! $changes) {
                abort(422, 'At least one proposed value must differ from the current profile.');
            }

            ksort($changes);
            $fingerprint = hash('sha256', json_encode(['updated_at' => $member->updated_at?->toISOString(), 'originals' => array_map(fn ($x) => $x[0], $changes)], JSON_THROW_ON_ERROR));
            $r = ProfileUpdateRequest::create(['club_id' => $club->id, 'member_id' => $member->id, 'requester_user_id' => $user->id, 'status' => 'pending', 'pending_guard' => 1, 'source_member_updated_at' => $member->updated_at, 'source_fingerprint' => $fingerprint, 'submitted_at' => now(), 'lock_version' => 1]);
            foreach ($changes as $field => [$old, $new]) {
                ProfileUpdateRequestChange::create(['profile_update_request_id' => $r->id, 'field_key' => $field, 'classification' => in_array($field, self::RESTRICTED, true) ? 'restricted' : 'standard', 'original_value' => $old, 'proposed_value' => $new]);
            }

            $this->audit($club, $user, $r, 'profile_updates.submitted', ['field_keys' => array_keys($changes)]);

            return $r->load(['member', 'changes', 'requester']);
        });
    }

    public function withdraw(Club $club, User $user, ProfileUpdateRequest $request, int $version): ProfileUpdateRequest
    {
        return $this->transactions->run($club, function () use ($club, $user, $request, $version) {
            $r = $this->locked($club, $request, $version);
            abort_unless($r->requester_user_id === $user->id, 403);
            $r->update(['status' => 'withdrawn', 'pending_guard' => null, 'decided_at' => now(), 'lock_version' => $r->lock_version + 1]);
            $this->audit($club, $user, $r, 'profile_updates.withdrawn', ['before' => 'pending', 'after' => 'withdrawn']);

            return $r->refresh();
        });
    }

    public function reject(Club $club, User $user, ProfileUpdateRequest $request, int $version, string $reason): ProfileUpdateRequest
    {
        return $this->transactions->run($club, function () use ($club, $user, $request, $version, $reason) {
            $r = $this->locked($club, $request, $version);
            $r->update(['status' => 'rejected', 'pending_guard' => null, 'reviewer_user_id' => $user->id, 'decided_at' => now(), 'rejection_reason' => trim($reason), 'lock_version' => $r->lock_version + 1]);
            $this->audit($club, $user, $r, 'profile_updates.rejected', ['before' => 'pending', 'after' => 'rejected', 'reason' => trim($reason)]);

            return $r->refresh();
        });
    }

    public function approve(Club $club, User $user, ProfileUpdateRequest $request, int $version): ProfileUpdateRequest
    {
        try {
            return $this->transactions->run($club, function () use ($club, $user, $request, $version) {
                $r = $this->locked($club, $request, $version);
                $member = Member::whereKey($r->member_id)->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
                $r->load('changes');
                $stale = ($r->source_member_updated_at?->getTimestamp() !== $member->updated_at?->getTimestamp());
                foreach ($r->changes as $c) {
                    $stale = $stale || $this->value($member, $c->field_key) !== $c->original_value;
                }

                if ($stale) {
                    throw new StaleProfileUpdateException($r, $r->changes->pluck('field_key')->all());
                }

                $updates = [];
                foreach ($r->changes as $c) {
                    $updates[$c->field_key] = $c->proposed_value;
                }

                $member->update($updates);
                $r->update(['status' => 'approved', 'pending_guard' => null, 'reviewer_user_id' => $user->id, 'decided_at' => now(), 'lock_version' => $r->lock_version + 1]);
                $this->audit($club, $user, $r, 'profile_updates.approved', ['before' => 'pending', 'after' => 'approved', 'changes' => $updates]);

                return $r->refresh();
            });
        } catch (StaleProfileUpdateException $stale) {
            // B1 has already rolled back. The admitted attempt only writes its conflict audit.
            $this->audit($club, $user, $stale->request, 'profile_updates.stale_conflict', ['field_keys' => $stale->fieldKeys]);
            throw new ConflictHttpException('Member profile changed after submission. Withdraw and resubmit.');
        }
    }

    public function ownMember(Club $club, User $user, bool $lock = false): Member
    {
        $q = Member::where('club_id', $club->id)->where('user_id', $user->id);
        if ($lock) {
            $q->lockForUpdate();
        }

        $m = $q->first();
        abort_unless($m, 403, 'A linked member profile is required.');

        return $m;
    }

    private function locked(Club $club, ProfileUpdateRequest $request, int $version): ProfileUpdateRequest
    {
        $r = ProfileUpdateRequest::whereKey($request->id)->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
        if ($r->status !== 'pending') {
            throw new ConflictHttpException('Profile update request is no longer pending.');
        }

        if ($r->lock_version !== $version) {
            throw new ConflictHttpException('Profile update request changed. Reload and retry.');
        }

        return $r;
    }

    private function normalize(string $field, mixed $value): mixed
    {
        if (is_string($value)) {
            $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
            if ($value === '') {
                $value = null;
            }
        }

        if ($field === 'gender' && is_string($value)) {
            $value = strtolower($value);
        }

        if ($field === 'blood_donor_consent') {
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return $value;
    }

    private function validate(string $field, mixed $value): void
    {
        $rules = match ($field) {
            'full_name' => ['required', 'string', 'max:255'],'gender' => ['nullable', 'in:male,female,other'],'birth_date','wedding_anniversary' => ['nullable', 'date_format:Y-m-d'],'blood_donor_consent' => ['boolean'],'phone','home_phone','alternate_phone','salutation','first_name','middle_name','last_name','occupation','spouse_name','address_line_1','address_line_2','city_locality','district','state','postal_code','country','blood_group' => ['nullable', 'string', 'max:255'],default => ['prohibited']
        };
        Validator::make([$field => $value], [$field => $rules])->validate();
    }

    private function value(Member $m, string $field): mixed
    {
        $v = $m->getAttribute($field);
        if ($v instanceof Carbon || $v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        return $v;
    }

    private function audit(Club $c, User $u, ProfileUpdateRequest $r, string $action, array $metadata): void
    {
        AuditLog::create(['club_id' => $c->id, 'user_id' => $u->id, 'action' => $action, 'auditable_type' => ProfileUpdateRequest::class, 'auditable_id' => $r->id, 'metadata' => $metadata]);
    }
}

class StaleProfileUpdateException extends \RuntimeException
{
    public function __construct(
        public readonly ProfileUpdateRequest $request,
        public readonly array $fieldKeys,
    ) {
        parent::__construct();
    }
}
