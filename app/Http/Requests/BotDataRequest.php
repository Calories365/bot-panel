<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BotDataRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'type_id' => 'nullable|exists:bot_types,id',
            'wordpress_endpoint' => 'nullable|string',
            'token' => 'required|string|max:255',
            'message' => 'nullable|string',
            'active' => 'boolean',
            'message_image' => 'nullable|image',
            'web_hook' => 'required'
        ];
    }
}
