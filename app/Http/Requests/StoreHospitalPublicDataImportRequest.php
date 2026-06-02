<?php

namespace App\Http\Requests;

use App\Models\Hospital;
use App\Models\HospitalPublicDataImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 심평원(HIRA) Excel 보강 업로드 — platform 운영자가 병의원 마스터에 보강 데이터를 적재.
 */
class StoreHospitalPublicDataImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isPlatform() ?? false)
            && ($this->user()?->can('create', Hospital::class) ?? false);
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(HospitalPublicDataImport::KINDS)],
            'file' => ['required', 'file', 'max:153600', 'extensions:xlsx'],
        ];
    }

    public function attributes(): array
    {
        return [
            'kind' => '적재 유형',
            'file' => 'Excel 파일',
        ];
    }
}
