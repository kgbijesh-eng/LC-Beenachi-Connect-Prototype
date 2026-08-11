<?php

namespace App\Http\Requests\Announcements;

use App\Models\Club;
use App\Models\ClubAnnouncement;
use App\Services\Access\ClubPermissionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListClubAnnouncementsRequest extends FormRequest
{
    private const ALLOWED_KEYS = [
        'scope', 'priority', 'q', 'published_from', 'published_to', 'page',
        'per_page', 'sort', 'direction', 'status', 'audience',
        'effective_state', 'scheduled',
    ];

    private const MANAGEMENT_KEYS = ['status', 'audience', 'effective_state', 'scheduled'];

    public function authorize(): bool
    {
        $club = $this->route('club');
        if (! $this->user() || ! $club instanceof Club) {
            return false;
        }

        $hasManagementKey = collect(self::MANAGEMENT_KEYS)->contains(fn (string $key): bool => $this->query->has($key));
        $requestsAll = $this->query->has('scope')
            && is_string($this->query('scope'))
            && strtolower(trim($this->query('scope'))) === 'all';
        if (! $hasManagementKey && ! $requestsAll) {
            return true;
        }

        return app(ClubPermissionService::class)->userHasPermission(
            $this->user(),
            ClubAnnouncement::PERMISSION_MANAGEMENT_VIEW,
            (int) $club->id,
        );
    }

    protected function prepareForValidation(): void
    {
        $values = [];
        if ($this->query->has('q') && is_string($this->query('q'))) {
            $values['q'] = trim($this->query('q'));
        }
        foreach (['scope', 'priority', 'sort', 'direction', 'status', 'audience', 'effective_state', 'scheduled'] as $key) {
            if ($this->query->has($key) && is_string($this->query($key))) {
                $values[$key] = strtolower(trim($this->query($key)));
            }
        }
        $this->merge($values);
    }

    public function rules(): array
    {
        return [
            'scope' => ['sometimes', Rule::in(['current', 'history', 'all'])],
            'priority' => ['sometimes', Rule::in(ClubAnnouncement::priorities())],
            'q' => ['sometimes', 'string', 'max:100'],
            'published_from' => ['sometimes', 'date_format:Y-m-d'],
            'published_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:published_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'sort' => ['sometimes', Rule::in(['published_at'])],
            'direction' => ['sometimes', Rule::in(['asc', 'desc'])],
            'status' => ['sometimes', Rule::in(ClubAnnouncement::statuses())],
            'audience' => ['sometimes', Rule::in(ClubAnnouncement::audiences())],
            'effective_state' => ['sometimes', Rule::in(['current', 'expired'])],
            'scheduled' => ['sometimes', Rule::in(['true', 'false', '1', '0'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $unknown = array_values(array_diff($this->query->keys(), self::ALLOWED_KEYS));
            foreach ($unknown as $key) {
                $validator->errors()->add($key, 'This filter is not supported.');
            }
        });
    }

    public function normalizedFilters(): array
    {
        $filters = $this->validated();
        $filters['scope'] ??= 'current';
        $filters['per_page'] = (int) ($filters['per_page'] ?? 20);
        $filters['page'] = (int) ($filters['page'] ?? 1);
        $filters['sort'] ??= 'published_at';
        $filters['direction'] ??= 'desc';
        if (array_key_exists('scheduled', $filters)) {
            $filters['scheduled'] = in_array($filters['scheduled'], ['true', '1'], true);
        }

        return $filters;
    }
}
