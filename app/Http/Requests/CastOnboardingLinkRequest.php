<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CastOnboardingLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_url' => 'nullable|url',
            'refresh_url' => 'nullable|url',
            'type' => 'nullable|string|in:account_onboarding,account_update',
        ];
    }
}


