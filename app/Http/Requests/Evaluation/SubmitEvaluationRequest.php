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
            'answers.praktik_baik' => 'required|string',
            'answers.kendala' => 'required|string',
            'answers.solusi' => 'required|string',
            'answers.analisis' => 'required|string',
        ];
    }
}
