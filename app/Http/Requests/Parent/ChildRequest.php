<?php

namespace App\Http\Requests\Parent;

use App\Enums\Avatar;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * The only details a Child profile holds: name, Grade and avatar.
 */
class ChildRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, Enum|ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'grade' => ['required', 'integer', 'between:1,4'],
            'avatar' => ['required', 'string', Rule::enum(Avatar::class)],
        ];
    }
}
