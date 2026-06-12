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
            'errors' => $row['errors'] ?? [],
            'duplicate_matches' => [
                'batch' => $row['duplicate_matches']['batch'] ?? [],
                'existing' => $row['duplicate_matches']['existing'] ?? [],
            ],
        ];
    }
}
