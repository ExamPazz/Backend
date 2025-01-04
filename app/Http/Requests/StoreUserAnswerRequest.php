<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
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
            'answers' => ['required', 'array'],

            'answers.*.question_id' => [
                'required',
                'integer',
                Rule::exists('mock_exam_questions', 'question_id')
                    ->where('mock_exam_id', $this->mock_exam_id),
            ],
            'answers.*.selected_option' => [
                'required',
                'integer',
                'exists:question_options,id'
            ],

//            'answers.*.question_id' => ['required', 'integer', 'exists:questions,id'],
//            'answers.*.selected_option' => ['required', 'integer', 'exists:question_options,id'],
        ];
    }
}
