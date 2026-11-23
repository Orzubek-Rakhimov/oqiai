<?php

namespace App\Http\Requests\Child;

use App\Concerns\AudioClipValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One recorded retry of a misread word: which word of which sentence, the
 * clip and its length. The clip is only read, never stored (ADR-0002).
 */
class RetryRequest extends FormRequest
{
    use AudioClipValidationRules;

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
            'audio' => $this->audioClipRules(),
            'duration_ms' => ['required', 'integer', 'min:0', 'max:'.SentenceRequest::MAX_DURATION_MS],
        ];
    }
}
