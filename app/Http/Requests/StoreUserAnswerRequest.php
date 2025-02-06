<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreUserAnswerRequest extends FormRequest
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
            'mock_exam_id' => [
                'required',
                'integer',
                Rule::exists('mock_exams', 'id')->where(function ($query) {
                    $query->where('user_id', auth()->id());
                })
            ],
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => [
                'nullable',
                'integer',
                Rule::exists('mock_exam_questions', 'question_id')
                    ->where('mock_exam_id', $this->mock_exam_id),
            ],
            'answers.*.selected_option' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) {
                    if (!is_null($value)) {
                        $exists = DB::table('question_options')->where('id', $value)->exists();
                        if (!$exists) {
                            $fail('The selected option does not exist.');
                        }
                    }
                }
            ],
            'answers.*.time_spent' => ['nullable', 'integer', 'min:0']
        ];
    }
}
