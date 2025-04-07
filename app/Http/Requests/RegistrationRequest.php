<?php

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class RegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string'],
            'phone_number' => ['required', 'string', 'unique:user_profiles,phone_number'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', new StrongPassword()],
            'nationality' => ['nullable', 'string'],
            'region' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'age' => ['nullable', 'numeric'],
            'referral_code' => 'nullable|exists:users,referral_code',
        ];
    }
}
