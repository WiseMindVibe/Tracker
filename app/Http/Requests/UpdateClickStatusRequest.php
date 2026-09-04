<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClickStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // adjust if you have auth/policies
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['tracker-blog', 'blog-buffer', 'buffer-blog', 'blog-affiliate'])],
            // adjust the allowed values to whatever your actual statuses are
        ];
    }
}
