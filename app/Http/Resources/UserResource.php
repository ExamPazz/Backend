<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'full_name' => $this->first_name,
            'email' => $this->email,
            'phone_number' => $this->userProfile->phone_number ?? null,
            'region' => $this->userProfile->region ?? null,
            'city' => $this->userProfile->city ?? null,
            'nationality' => $this->userProfile->nationality ?? null,
            'age' => $this->userProfile->age ?? null,
        ];
    }
}
