<?php

namespace App\Http\Requests;

use App\Models\MasterChangeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 제약사 admin 의 공유 마스터 변경요청 제출. (GAP-10 MT-8)
 */
class StoreMasterChangeRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MasterChangeRequest::class) ?? false;
    }

    public function rules(): array
    {
        $type = $this->input('target_type');
        $table = $type === MasterChangeRequest::TARGET_HOSPITAL ? 'hospitals' : 'pharmacies';
        $nameField = $type === MasterChangeRequest::TARGET_HOSPITAL ? 'hospital_name' : 'pharmacy_name';

        return [
            'target_type' => ['required', Rule::in([MasterChangeRequest::TARGET_PHARMACY, MasterChangeRequest::TARGET_HOSPITAL])],
            'request_type' => ['required', Rule::in([MasterChangeRequest::TYPE_CREATE, MasterChangeRequest::TYPE_UPDATE])],
            'target_id' => [
                Rule::requiredIf(fn () => $this->input('request_type') === MasterChangeRequest::TYPE_UPDATE),
                'nullable',
                Rule::exists($table, 'id'),
            ],
            'payload' => ['required', 'array'],
            'payload.'.$nameField => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'payload.pharmacy_name' => '약국명',
            'payload.hospital_name' => '병의원명',
        ];
    }
}
