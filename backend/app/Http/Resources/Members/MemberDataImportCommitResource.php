<?php

namespace App\Http\Resources\Members;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberDataImportCommitResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'dry_run' => false,
            'committed' => true,
            'summary' => $this->resource['summary'] ?? [
                'total_rows' => 0,
                'created_rows' => 0,
                'updated_rows' => 0,
                'skipped_rows' => 0,
                'failed_rows' => 0,
            ],
            'rows' => collect($this->resource['rows'] ?? [])
                ->map(fn (array $row): array => $this->commitRow($row))
                ->values()
                ->all(),
        ];
    }

    private function commitRow(array $row): array
    {
        return [
            'row_number' => $row['row_number'] ?? null,
            'status' => $row['status'] ?? 'failed',
            'member_id' => $row['member_id'] ?? null,
            'changed_fields' => $row['changed_fields'] ?? [],
            'errors' => $row['errors'] ?? [],
            'error_details' => $row['error_details'] ?? [],
            'warnings' => $row['warnings'] ?? [],
            'normalized' => $row['normalized'] ?? [],
        ];
    }
}
