<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CastPayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => 'required|integer|min:100',
            'currency' => 'nullable|string|size:3',
            'metadata' => 'nullable|array',
            'metadata.*' => 'nullable|string',
            'description' => 'nullable|string|max:255',
        ];
    }
}


