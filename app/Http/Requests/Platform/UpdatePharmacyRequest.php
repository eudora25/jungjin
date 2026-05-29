<?php

namespace App\Http\Requests\Platform;

use App\Http\Requests\UpdatePharmacyRequest as BaseUpdatePharmacyRequest;

class UpdatePharmacyRequest extends BaseUpdatePharmacyRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }
}
