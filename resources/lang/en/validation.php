<?php

return [
    'required' => 'The :attribute field is required.',
    'email'    => 'The :attribute must be a valid email address.',
    'string'   => 'The :attribute must be a string.',
    'numeric'  => 'The :attribute must be a number.',
    'integer'  => 'The :attribute must be an integer.',
    'boolean'  => 'The :attribute field must be true or false.',
    'min'      => [
        'numeric' => 'The :attribute must be at least :min.',
        'string'  => 'The :attribute must be at least :min characters.',
    ],
    'max'      => [
        'numeric' => 'The :attribute may not be greater than :max.',
        'string'  => 'The :attribute may not be greater than :max characters.',
    ],
    'confirmed' => 'The :attribute confirmation does not match.',
    'unique'   => 'The :attribute has already been taken.',
    'exists'   => 'The selected :attribute is invalid.',
    'image'    => 'The :attribute must be an image.',
    'mimes'    => 'The :attribute must be a file of type: :values.',
    'owned_relation_requires_embed_payload' => 'This relation is owned by the host and only accepts a nested embed payload, not a foreign id.',
];
