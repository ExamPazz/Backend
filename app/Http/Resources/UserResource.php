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
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone_number' => $this->userProfile->phone_number ?? null,
            'region' => $this->userProfile->region ?? null,
            'city' => $this->userProfile->city ?? null,
            'has_exam_detail' => $this->latestExamDetail,
            'has_mock_exam' => $this->mockExams()->exists(), 
            'subscription' => $this->subscription,
            'nationality' => $this->userProfile->nationality ?? null,
            'gender' => $this->userProfile->gender ?? null,
            'date_of_birth' => $this->userProfile->date_of_birth ?? null,
        ];
    }
}
