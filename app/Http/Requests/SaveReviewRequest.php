<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Stars from 1 to 5 and optional text. The global middleware trims the text
 * and turns blank text into null before these rules run.
 */
class SaveReviewRequest extends FormRequest
{
    public const int MAX_BODY_LENGTH = 1000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'between:1,5'],
            'body' => ['nullable', 'string', 'max:'.self::MAX_BODY_LENGTH],
        ];
    }
}
