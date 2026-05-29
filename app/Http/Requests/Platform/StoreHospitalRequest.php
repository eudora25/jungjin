<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\StoreHospitalRequest as BaseStoreHospitalRequest;

class StoreHospitalRequest extends BaseStoreHospitalRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isPlatform() ?? false;
    }
}
