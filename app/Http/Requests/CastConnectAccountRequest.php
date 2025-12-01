<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CastConnectAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization should be handled by route middleware / policies
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'nullable|email',
            'country' => 'nullable|string|size:2',
            'business_type' => 'nullable|string|in:individual,company',
            'metadata' => 'nullable|array',
            'metadata.*' => 'nullable|string',
            'product_description' => 'nullable|string|max:255',
            'support_email' => 'nullable|email',
            'support_phone' => 'nullable|string|max:30',
            'force_sync' => 'sometimes|boolean',
        ];
    }
}


