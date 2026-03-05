<?php

namespace App\Http\Requests;

class ServiceUpdateRequest extends ServiceStoreRequest
{
    protected function excludeServiceId(): ?int
    {
        return $this->route('service')->id;
    }
}
