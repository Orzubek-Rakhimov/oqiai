<?php

namespace App\Actions\Fortify;

use App\Concerns\NormalizesPhoneNumbers;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use NormalizesPhoneNumbers, PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered Parent.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input['phone'] = $this->normalizePhoneNumber($input['phone'] ?? null);

        $validated = Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->newPasswordRules(),
        ], [
            'phone.unique' => __('This phone number is already registered. If you have an account, log in.'),
        ])->validate();

        return User::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'password' => $validated['password'],
        ]);
    }
}
