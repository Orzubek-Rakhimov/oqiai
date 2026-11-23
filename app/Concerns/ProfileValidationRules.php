<?php

namespace App\Concerns;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait ProfileValidationRules
{
    /**
     * Get the validation rules used to validate user profiles.
     *
     * The phone number must already be normalised before these rules run.
     *
     * @return array<string, array<int, Unique|ValidationRule|array<mixed>|string>>
     */
    protected function profileRules(?int $userId = null): array
    {
        return [
            'name' => $this->nameRules(),
            'phone' => $this->phoneRules($userId),
        ];
    }

    /**
     * Get the validation rules used to validate user names.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate normalised phone numbers.
     *
     * @return array<int, Unique|ValidationRule|array<mixed>|string>
     */
    protected function phoneRules(?int $userId = null): array
    {
        return [
            'required',
            'string',
            'regex:/^\+998\d{9}$/',
            $userId === null
                ? Rule::unique(User::class)
                : Rule::unique(User::class)->ignore($userId),
        ];
    }
}
