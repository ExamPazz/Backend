<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;


class StoreExamDetailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'exam_name' => ['required', 'string', Rule::in(['JAMB', 'WAEC', 'NECO'])],
            'registration_number' => 'nullable|string',
            'has_written_before' => 'required|boolean',
            'exam_year' => 'nullable|integer|digits:4',
            'previous_score' => 'nullable|integer|min:0|max:400',
            'target_score' => 'required|integer|min:0|max:400',
            'subject_combinations' => 'required|array|min:4|max:4|exists:subjects,id',
        ];
    }
}
