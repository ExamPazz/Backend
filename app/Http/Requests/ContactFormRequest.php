<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
            'message' => ['required', 'string', 'max:1000']
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Please provide your name',
            'email.required' => 'Please provide your email address',
            'email.email' => 'Please provide a valid email address',
            'phone_number.required' => 'Please provide your phone number',
            'message.required' => 'Please provide your message',
            'message.max' => 'Message is too long (maximum is 1000 characters)'
        ];
    }
}
