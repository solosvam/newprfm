<?php

namespace App\Support;

class LocalizedValidation
{
    /**
     * All frontend validation text comes from lang/{locale}.json.
     */
    public static function messages(): array
    {
        $rules = [
            'required', 'string', 'email', 'unique', 'digits', 'confirmed',
            'integer', 'array', 'exists', 'in', 'boolean', 'numeric',
            'regex', 'image', 'mimes', 'max', 'min',
            'min.string', 'min.numeric', 'min.array',
            'max.string', 'max.numeric', 'max.array',
            'between.numeric', 'between.string', 'gt.numeric',
        ];

        $messages = [];

        foreach ($rules as $rule) {
            $messages[$rule] = __("validation.{$rule}");
        }

        return $messages;
    }

    public static function attributes(): array
    {
        $attributes = __('validation.attributes');

        return is_array($attributes) ? $attributes : [];
    }
}
