<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\ExamDetail;

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
            'subject_combinations' => [
            'sometimes',
            'array',
            'min:4',
            'max:4',
            function ($attribute, $value, $fail) {
                // Fetch the current exam detail
                $examDetail = ExamDetail::find($this->route('exam_detail'));

                if (!$examDetail) {
                    $fail('Exam detail not found.');
                    return;
                }

                // Compare arrays (strict comparison to detect changes)
                $storedCombinations = $examDetail->subject_combinations;
                
                if (!is_array($storedCombinations) || count(array_diff($value, $storedCombinations)) > 0 || count(array_diff($storedCombinations, $value)) > 0) {
                    $fail('The subject combinations cannot be changed.');
                }
            },
        ],
    ];
    }
}
