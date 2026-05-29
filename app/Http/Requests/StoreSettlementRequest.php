<?php

namespace App\Http\Requests;

use App\Models\Settlement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSettlementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Settlement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')->whereNull('deleted_at')],
            'period_month' => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'company_id' => '거래처',
            'period_month' => '정산 월',
        ];
    }
}
