<?php

namespace App\Http\Requests\Reports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RunClubReportRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'date_from' => ['sometimes','date_format:Y-m-d'],
            'date_to' => ['sometimes','date_format:Y-m-d','after_or_equal:date_from'],
            'status' => ['sometimes','string','max:30'],
            'type' => ['sometimes','string','max:30'],
            'record_type' => ['sometimes','string','max:30'],
            'service_category' => ['sometimes','string','max:100'],
            'priority' => ['sometimes','string','max:30'],
            'is_all_day' => ['sometimes','boolean'],
            'q' => ['sometimes','string','max:100'],
            'category' => ['sometimes','string','in:membership_dues,installation_contribution,event_contribution,service_project_contribution,other_contribution'],
            'charge_status' => ['sometimes','string','in:draft,issued,closed,cancelled'],
            'payment_status' => ['sometimes','string','in:recorded,voided'],
            'payment_mode' => ['sometimes','string','in:cash,upi,bank_transfer,cheque,other'],
            'member_search' => ['sometimes','string','max:100'],
            'charge_search' => ['sometimes','string','max:100'],
            'page' => ['sometimes','integer','min:1'],
            'per_page' => ['sometimes','integer','min:1','max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $unknown = array_diff(array_keys($this->query()), array_keys($this->rules()));
            if ($unknown) {
                $validator->errors()->add('filters', 'Unknown filters: '.implode(', ', $unknown));
            }

            if ($this->date('date_from') && $this->date('date_to') && $this->date('date_from')->diffInDays($this->date('date_to')) > 366) {
                $validator->errors()->add('date_to', 'The report date range may not exceed 366 days.');
            }
        }];
    }
}
