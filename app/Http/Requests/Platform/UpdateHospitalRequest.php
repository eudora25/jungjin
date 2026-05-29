<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\UpdateHospitalRequest as BaseUpdateHospitalRequest;

class UpdateHospitalRequest extends BaseUpdateHospitalRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }
}
