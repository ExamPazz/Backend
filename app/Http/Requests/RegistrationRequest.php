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
            'first_name' => ['required', 'string', 'alpha'],
            'last_name' => ['required', 'string', 'alpha'],
            'phone_number' => ['required', 'string', 'unique:user_profiles,phone_number'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'confirmed', new StrongPassword()],
            'nationality' => ['nullable', 'string'],
            'region' => ['nullable', 'string'],
            'city' => ['nullable', 'string'],
            'age' => ['nullable', 'numeric'],
            'agreed_to_terms_of_use' => ['required', 'boolean', 'in:true']
        ];
    }
}
