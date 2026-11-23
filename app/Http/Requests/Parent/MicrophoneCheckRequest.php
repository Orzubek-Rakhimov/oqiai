<?php

namespace App\Http\Requests\Parent;

use App\Concerns\AudioClipValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Child's test sentence recording. It is only read, never stored (ADR-0002).
 */
class MicrophoneCheckRequest extends FormRequest
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
            'audio' => $this->audioClipRules(),
        ];
    }
}
