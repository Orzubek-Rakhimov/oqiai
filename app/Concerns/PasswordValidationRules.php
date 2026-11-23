<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate a changed password, typed twice.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [...$this->newPasswordRules(), 'confirmed'];
    }

    /**
     * Get the validation rules used to validate a password chosen at sign-up, typed once.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function newPasswordRules(): array
    {
        return ['required', 'string', Password::default()];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, Password|ValidationRule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
