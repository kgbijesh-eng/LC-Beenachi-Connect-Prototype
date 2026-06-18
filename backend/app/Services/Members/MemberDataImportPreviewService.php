<?php

namespace App\Services\Members;

use App\Models\Member;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class MemberDataImportPreviewService
{
    private const FIELD_ALIASES = [
        'member_code' => ['member_code', 'member code', 'member id', 'lions member id', 'member no', 'member number', 'code'],
        'full_name' => ['full_name', 'full name', 'member name', 'name'],
        'salutation' => ['salutation', 'title'],
        'first_name' => ['first_name', 'first name', 'given name'],
        'middle_name' => ['middle_name', 'middle name'],
        'last_name' => ['last_name', 'last name', 'surname', 'family name'],
        'birth_date' => ['birth_date', 'birth date', 'date of birth', 'dob'],
        'occupation' => ['occupation', 'profession'],
        'spouse_name' => ['spouse_name', 'spouse name'],
        'wedding_anniversary' => ['wedding_anniversary', 'wedding anniversary', 'anniversary date'],
        'gender' => ['gender', 'sex'],
        'membership_type' => ['membership_type', 'membership type'],
        'membership_category' => ['membership_category', 'membership category'],
        'membership_program' => ['membership_program', 'membership program'],
        'membership_sponsor_name' => ['membership_sponsor_name', 'membership sponsor name', 'sponsor name', 'sponsor'],
        'phone' => ['phone', 'mobile', 'mobile number', 'phone number', 'contact number'],
        'home_phone' => ['home_phone', 'home phone', 'residence phone'],
        'alternate_phone' => ['alternate_phone', 'alternate phone', 'alternative phone', 'secondary phone'],
        'email' => ['email', 'email address', 'personal email', 'mail'],
        'address_line_1' => ['address_line_1', 'address line 1', 'address 1'],
        'address_line_2' => ['address_line_2', 'address line 2', 'address 2'],
        'city_locality' => ['city_locality', 'city locality', 'locality', 'city', 'place'],
        'district' => ['district'],
        'state' => ['state', 'province'],
        'postal_code' => ['postal_code', 'postal code', 'postcode', 'pin code', 'pincode', 'zip code'],
        'country' => ['country'],
        'status' => ['status', 'member status'],
        'joined_at' => ['joined_at', 'joined at', 'joined date', 'joining date', 'date joined', 'lion join date'],
        'blood_group' => ['blood_group', 'blood group'],
        'blood_donor_consent' => ['blood_donor_consent', 'blood donor consent', 'donor consent'],
    ];

    private const OPTIONAL_PLACEHOLDER_FIELDS = [
        'occupation',
        'spouse_name',
        'membership_type',
        'membership_category',
        'membership_program',
        'membership_sponsor_name',
        'home_phone',
        'alternate_phone',
        'address_line_1',
        'address_line_2',
        'city_locality',
        'district',
        'state',
        'postal_code',
        'country',
    ];

    /** @var array<int, string> */
    private const IMPORTABLE_FIELDS = [
        'member_code', 'full_name', 'salutation', 'first_name', 'middle_name', 'last_name',
        'birth_date', 'occupation', 'spouse_name', 'wedding_anniversary', 'gender',
        'membership_type', 'membership_category', 'membership_program', 'membership_sponsor_name',
        'phone', 'home_phone', 'alternate_phone', 'email', 'address_line_1', 'address_line_2',
        'city_locality', 'district', 'state', 'postal_code', 'country', 'status', 'joined_at',
        'blood_group', 'blood_donor_consent',
    ];

    /**
     * Build a read-only preview for spreadsheet-like member rows.
     * It does not upload files, parse xlsx files, create members, update members,
     * queue jobs, store import state, or touch finance records.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    public function preview(int $clubId, array $rows): array
    {
        $existingMembers = $this->existingMemberLookup($clubId);
        $batchLookup = $this->batchDuplicateLookup($rows);
        $previewRows = [];

        foreach (array_values($rows) as $index => $row) {
            $rowNumber = $index + 1;
            $normalized = $this->normalizeRow($row);
            $errorDetails = $this->validateRowDetails($normalized, $rowNumber);
            $batchDuplicates = $this->batchDuplicatesForRow($rowNumber, $normalized, $batchLookup);
            $existingMatches = $this->existingMatchesForRow($normalized, $existingMembers);

            foreach ($batchDuplicates as $duplicate) {
                $errorDetails[] = [
                    'row_number' => $rowNumber,
                    'member_code' => $normalized['member_code'],
                    'field' => $duplicate['field'],
                    'reason' => 'Duplicate '.$duplicate['field'].' inside import batch.',
                ];
            }

            $identityMatch = $this->identityMatch($normalized, $existingMembers);
            $changes = $identityMatch ? $this->changesForMember($identityMatch, $normalized) : [];
            $valid = $errorDetails === [];

            $intent = match (true) {
                ! $valid => 'reject',
                $identityMatch !== null && $changes !== [] => 'update_preview',
                $identityMatch !== null => 'unchanged_preview',
                $existingMatches !== [] => 'matched_preview',
                default => 'create_preview',
            };

            $previewRows[] = [
                'row_number' => $rowNumber,
                'valid' => $valid,
                'intent' => $intent,
                'normalized' => $normalized,
                'changed_fields' => array_keys($changes),
                'errors' => array_map(
                    fn (array $error): string => $error['reason'],
                    $errorDetails
                ),
                'error_details' => $errorDetails,
                'duplicate_matches' => [
                    'batch' => $batchDuplicates,
                    'existing' => $existingMatches,
                ],
            ];
        }

        return [
            'dry_run' => true,
            'committed' => false,
            'summary' => $this->summary($previewRows),
            'rows' => $previewRows,
        ];
    }

    /** @param array<string, mixed> $row */
    public function normalizeRow(array $row): array
    {
        $normalized = array_fill_keys(self::IMPORTABLE_FIELDS, null);

        foreach ($row as $heading => $value) {
            $field = $this->fieldForHeading((string) $heading);

            if ($field === null) {
                continue;
            }

            $normalized[$field] = $this->normalizeValue($field, $value);
        }

        if ($normalized['full_name'] === null) {
            $components = array_filter([
                $normalized['first_name'],
                $normalized['middle_name'],
                $normalized['last_name'],
            ], fn (mixed $value): bool => $value !== null);

            $normalized['full_name'] = $components === [] ? null : implode(' ', $components);
        }

        return $normalized;
    }

    /** @return array<int, array{row_number:int,member_code:mixed,field:string,reason:string}> */
    public function validateRowDetails(array $normalized, int $rowNumber): array
    {
        $validator = Validator::make($normalized, $this->validationRules());
        $details = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            foreach ($messages as $message) {
                $details[] = [
                    'row_number' => $rowNumber,
                    'member_code' => $normalized['member_code'] ?? null,
                    'field' => $field,
                    'reason' => $message,
                ];
            }
        }

        return $details;
    }

    /** @return array<string, mixed> */
    public function valuesForCreate(array $normalized): array
    {
        return array_filter(
            $normalized,
            fn (mixed $value): bool => $value !== null
        );
    }

    /** @return array<string, mixed> */
    public function changesForMember(Member $member, array $normalized): array
    {
        $changes = [];

        foreach (self::IMPORTABLE_FIELDS as $field) {
            if ($field === 'member_code' || $normalized[$field] === null) {
                continue;
            }

            $existing = $member->{$field};
            if ($existing instanceof \DateTimeInterface) {
                $existing = $existing->format('Y-m-d');
            }

            if ((string) $existing !== (string) $normalized[$field]) {
                $changes[$field] = $normalized[$field];
            }
        }

        return $changes;
    }

    private function validationRules(): array
    {
        $nullableStrings = [
            'salutation', 'first_name', 'middle_name', 'last_name', 'occupation', 'spouse_name',
            'membership_type', 'membership_category', 'membership_program', 'membership_sponsor_name',
            'home_phone', 'alternate_phone', 'address_line_1', 'address_line_2', 'city_locality',
            'district', 'state', 'postal_code', 'country',
        ];
        $rules = [];

        foreach ($nullableStrings as $field) {
            $rules[$field] = ['nullable', 'string', 'max:255'];
        }

        return [
            ...$rules,
            'member_code' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date'],
            'wedding_anniversary' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'phone' => ['nullable', 'string', 'min:7', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'joined_at' => ['nullable', 'date'],
            'blood_group' => ['nullable', 'string', 'max:10'],
            'blood_donor_consent' => ['nullable', 'boolean'],
        ];
    }

    private function fieldForHeading(string $heading): ?string
    {
        $normalizedHeading = $this->normalizeHeading($heading);

        foreach (self::FIELD_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                if ($normalizedHeading === $this->normalizeHeading($alias)) {
                    return $field;
                }
            }
        }

        return null;
    }

    private function normalizeHeading(string $heading): string
    {
        $heading = strtolower(trim($heading));
        $heading = preg_replace('/[^a-z0-9]+/', ' ', $heading) ?? $heading;

        return trim(preg_replace('/\s+/', ' ', $heading) ?? $heading);
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $stringValue = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
        $stringValue = str_replace('_x000D_', ' ', $stringValue);
        $stringValue = trim(preg_replace('/\s+/', ' ', $stringValue) ?? $stringValue);

        if ($stringValue === '') {
            return null;
        }

        if ($stringValue === '52' && in_array($field, self::OPTIONAL_PLACEHOLDER_FIELDS, true)) {
            return null;
        }

        return match ($field) {
            'member_code' => strtoupper($stringValue),
            'phone', 'home_phone', 'alternate_phone' => $this->normalizePhone($stringValue),
            'email' => strtolower(preg_replace('/\s+/', '', $stringValue) ?? $stringValue),
            'status' => strtolower($stringValue),
            'birth_date', 'wedding_anniversary', 'joined_at' => $this->normalizeDate($stringValue),
            'gender' => $this->normalizeGender($stringValue),
            'blood_group' => strtoupper(str_replace(' ', '', $stringValue)),
            'blood_donor_consent' => $this->normalizeBoolean($stringValue),
            default => $stringValue,
        };
    }

    private function normalizePhone(string $value): string
    {
        $hasPrefix = str_starts_with(trim($value), '+');
        $digits = preg_replace('/\D+/', '', $value) ?? $value;

        return $hasPrefix ? '+'.$digits : $digits;
    }

    private function normalizeDate(string $value): string
    {
        try {
            return CarbonImmutable::parse($value)->toDateString();
        } catch (\Throwable) {
            return $value;
        }
    }

    private function normalizeGender(string $value): string
    {
        return match (strtolower($value)) {
            'm', 'male' => 'male',
            'f', 'female' => 'female',
            'o', 'other', 'non-binary', 'nonbinary' => 'other',
            default => strtolower($value),
        };
    }

    private function normalizeBoolean(string $value): bool|string
    {
        return match (strtolower(trim($value))) {
            '1', 'yes', 'y', 'true', 'donor', 'consented' => true,
            '0', 'no', 'n', 'false', 'not donor', 'not consented' => false,
            default => $value,
        };
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function batchDuplicateLookup(array $rows): array
    {
        $lookup = ['member_code' => [], 'phone' => [], 'email' => []];

        foreach (array_values($rows) as $index => $row) {
            $normalized = $this->normalizeRow($row);

            foreach (array_keys($lookup) as $field) {
                $key = $this->matchKey($field, $normalized[$field]);
                if ($key !== null) {
                    $lookup[$field][$key][] = $index + 1;
                }
            }
        }

        return $lookup;
    }

    private function batchDuplicatesForRow(int $rowNumber, array $normalized, array $batchLookup): array
    {
        $duplicates = [];

        foreach (array_keys($batchLookup) as $field) {
            $key = $this->matchKey($field, $normalized[$field]);
            if ($key === null) {
                continue;
            }

            $matchedRows = array_values(array_filter(
                $batchLookup[$field][$key] ?? [],
                fn (int $matchedRow): bool => $matchedRow !== $rowNumber
            ));

            if ($matchedRows !== []) {
                $duplicates[] = ['field' => $field, 'matched_rows' => $matchedRows];
            }
        }

        return $duplicates;
    }

    private function existingMemberLookup(int $clubId): array
    {
        $lookup = ['member_code' => [], 'phone' => [], 'email' => []];

        Member::query()->where('club_id', $clubId)->get()->each(function (Member $member) use (&$lookup): void {
            foreach (array_keys($lookup) as $field) {
                $key = $this->matchKey($field, $member->{$field});
                if ($key !== null) {
                    $lookup[$field][$key][] = $member;
                }
            }
        });

        return $lookup;
    }

    private function identityMatch(array $normalized, array $existingMembers): ?Member
    {
        $key = $this->matchKey('member_code', $normalized['member_code']);

        return $key === null ? null : ($existingMembers['member_code'][$key][0] ?? null);
    }

    private function existingMatchesForRow(array $normalized, array $existingMembers): array
    {
        $matches = [];
        $seen = [];

        foreach (array_keys($existingMembers) as $field) {
            $key = $this->matchKey($field, $normalized[$field]);
            if ($key === null) {
                continue;
            }

            foreach ($existingMembers[$field][$key] ?? [] as $member) {
                $seenKey = $member->id.'-'.$field;
                if (isset($seen[$seenKey])) {
                    continue;
                }

                $seen[$seenKey] = true;
                $matches[] = [
                    'member_id' => $member->id,
                    'member_code' => $member->member_code,
                    'member_name' => $member->full_name,
                    'field' => $field,
                ];
            }
        }

        return $matches;
    }

    private function matchKey(string $field, mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return match ($field) {
            'member_code' => strtoupper(trim((string) $value)),
            'phone' => $this->normalizePhone((string) $value),
            'email' => strtolower(trim((string) $value)),
            default => trim((string) $value),
        };
    }

    private function summary(array $previewRows): array
    {
        $countIntent = fn (string $intent): int => count(array_filter(
            $previewRows,
            fn (array $row): bool => $row['intent'] === $intent
        ));

        return [
            'total_rows' => count($previewRows),
            'accepted_rows' => count(array_filter($previewRows, fn (array $row): bool => $row['valid'])),
            'rejected_rows' => $countIntent('reject'),
            'duplicate_rows' => count(array_filter(
                $previewRows,
                fn (array $row): bool => $row['duplicate_matches']['batch'] !== []
            )),
            'possible_existing_match_rows' => count(array_filter(
                $previewRows,
                fn (array $row): bool => $row['duplicate_matches']['existing'] !== []
            )),
            'created_rows' => $countIntent('create_preview'),
            'updated_rows' => $countIntent('update_preview'),
            'skipped_rows' => $countIntent('unchanged_preview') + $countIntent('matched_preview'),
            'failed_rows' => $countIntent('reject'),
        ];
    }
}
