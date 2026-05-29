<?php

namespace App\Http\Requests;

use App\Models\Performance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ImportPerformancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Performance::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required_without:token', 'nullable', 'file', 'max:5120', 'mimes:csv,txt', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel'],
            'token' => ['required_without:file', 'nullable', 'string', 'size:36'],
            'mode' => ['required', Rule::in(['analyze', 'commit'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'file' => 'CSV 파일',
            'mode' => '동작 모드',
            'token' => '분석 토큰',
        ];
    }
}
