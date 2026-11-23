<?php

namespace App\Http\Requests\Child;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Child read the word once more at the end of Syllable help. That read
 * is not scored, so no clip is sent: only which word of which sentence.
 */
class SyllableHelpRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'sentence' => ['required', 'integer', 'min:0'],
            'word' => ['required', 'integer', 'min:0'],
        ];
    }
}
