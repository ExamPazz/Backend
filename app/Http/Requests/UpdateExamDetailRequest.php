<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExamDetailRequest extends FormRequest
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
            'exam_name' => 'sometimes|string',
            'registration_number' => 'nullable|string',
            'has_written_before' => 'sometimes|boolean',
            'exam_year' => 'nullable|integer|digits:4',
            'previous_score' => 'nullable|integer|min:0|max:400',
            'target_score' => 'sometimes|integer|min:0|max:400',
            'subject_combinations' => 'sometimes|array|min:4|max:4',
            'weak_areas' => 'nullable|array',
        ];
    }
}
