<?php

declare(strict_types=1);

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class ClubAccountStatementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'lions_year' => ['required', 'string', 'regex:/\A\d{4}-\d{2}\z/'],
            'period_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->query()), array_keys($this->rules()));
            if ($unknown !== []) {
                $validator->errors()->add('filters', 'Unknown filters: '.implode(', ', $unknown));
            }
            $period = $this->query('period_id');
            $from = $this->query('from');
            $to = $this->query('to');
            if ($period !== null && ($from !== null || $to !== null)) {
                $validator->errors()->add('period_id', 'An accounting period cannot be combined with custom dates.');
            }
            if (($from === null) !== ($to === null)) {
                $validator->errors()->add('from', 'From and to dates must be supplied together.');
            }
            if (is_string($from) && is_string($to) && $from > $to) {
                $validator->errors()->add('to', 'The to date must be on or after the from date.');
            }
        }];
    }
}
