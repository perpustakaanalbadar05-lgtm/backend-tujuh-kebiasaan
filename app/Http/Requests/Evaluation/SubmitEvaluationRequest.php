<?php

namespace App\Http\Requests\Evaluation;

use Illuminate\Foundation\Http\FormRequest;

class SubmitEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'answers' => 'required|array',
            'answers.praktik_baik' => 'nullable|string',
            'answers.kendala' => 'nullable|string',
            'answers.solusi' => 'nullable|string',
            'answers.analisis' => 'nullable|string',
        ];
    }
}
