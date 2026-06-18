<?php

namespace App\Http\Resources\Members;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDataImportPreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dry_run' => (bool) ($this->resource['dry_run'] ?? true),
            'committed' => (bool) ($this->resource['committed'] ?? false),
            'summary' => $this->resource['summary'] ?? [
                'total_rows' => 0,
                'accepted_rows' => 0,
                'rejected_rows' => 0,
                'duplicate_rows' => 0,
                'possible_existing_match_rows' => 0,
                'created_rows' => 0,
                'updated_rows' => 0,
                'skipped_rows' => 0,
                'failed_rows' => 0,
            ],
            'rows' => collect($this->resource['rows'] ?? [])
                ->map(fn (array $row): array => $this->previewRow($row))
                ->values()
                ->all(),
        ];
    }

    private function previewRow(array $row): array
    {
        return [
            'row_number' => $row['row_number'] ?? null,
            'valid' => (bool) ($row['valid'] ?? false),
            'intent' => $row['intent'] ?? 'reject',
            'normalized' => $row['normalized'] ?? [],
            'changed_fields' => $row['changed_fields'] ?? [],
            'errors' => $row['errors'] ?? [],
            'error_details' => $row['error_details'] ?? [],
            'duplicate_matches' => [
                'batch' => $row['duplicate_matches']['batch'] ?? [],
                'existing' => $row['duplicate_matches']['existing'] ?? [],
            ],
        ];
    }
}
