<?php

namespace App\Http\Requests\Members;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemberProfileDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return array_merge([
            'document_type' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:255'],
            'external_reference' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:5000'],
        ], $this->blockedFieldRules());
    }

    private function blockedFieldRules(): array
    {
        return collect([
            'club_id',
            'member_id',
            'created_by_user_id',
            'updated_by_user_id',
            'file_path',
            'path',
            'disk',
            'storage_disk',
            'file_name',
            'original_file_name',
            'mime_type',
            'file_size',
            'url',
            'public_url',
            'signed_url',
            'download_url',
            'ocr_text',
            'extracted_text',
            'pdf_path',
            'thumbnail_path',
            'uploaded_file',
            'attachment',
            'binary_data',
            'base64_data',
        ])->mapWithKeys(fn (string $field): array => [$field => ['prohibited']])->all();
    }
}
