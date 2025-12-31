<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CastBankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization should be handled by route middleware / policies
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => 'required|string|max:255',
            'branch_name' => 'required|string|max:255',
            'account_type' => 'required|string|in:普通,当座',
            'account_number' => 'required|string|max:255',
            'account_holder_name' => 'required|string|max:255',
        ];
    }
}
