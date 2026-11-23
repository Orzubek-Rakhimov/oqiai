<?php

namespace App\Http\Requests\Child;

use App\Concerns\AudioClipValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * One recorded sentence: which sentence it is, the clip and its length.
 * The clip is only read, never stored (ADR-0002).
 */
class SentenceRequest extends FormRequest
{
    use AudioClipValidationRules;

    /**
     * The longest recording, in milliseconds, that counts toward reading time.
     */
    public const int MAX_DURATION_MS = 60_000;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'sentence' => ['required', 'integer', 'min:0'],
            'audio' => $this->audioClipRules(),
            'duration_ms' => ['required', 'integer', 'min:0', 'max:'.self::MAX_DURATION_MS],
        ];
    }
}
