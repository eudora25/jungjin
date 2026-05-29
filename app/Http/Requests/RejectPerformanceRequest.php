<?php

namespace App\Http\Requests;

use App\Models\Performance;
use Illuminate\Foundation\Http\FormRequest;

class RejectPerformanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $perf = $this->route('performance');

        return $perf instanceof Performance
            && ($this->user()?->can('reject', $perf) ?? false);
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:2', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => '반려 사유',
        ];
    }
}
