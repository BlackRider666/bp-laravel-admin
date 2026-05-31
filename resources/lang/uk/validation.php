<?php

return [
    'required' => 'Поле :attribute є обов\'язковим.',
    'email'    => 'Поле :attribute має бути дійсною електронною адресою.',
    'string'   => 'Поле :attribute має бути рядком.',
    'numeric'  => 'Поле :attribute має бути числом.',
    'integer'  => 'Поле :attribute має бути цілим числом.',
    'boolean'  => 'Поле :attribute має бути true або false.',
    'min'      => [
        'numeric' => 'Поле :attribute має бути не менше :min.',
        'string'  => 'Поле :attribute має містити щонайменше :min символів.',
    ],
    'max'      => [
        'numeric' => 'Поле :attribute не може перевищувати :max.',
        'string'  => 'Поле :attribute не може бути довше :max символів.',
    ],
    'confirmed' => 'Підтвердження поля :attribute не співпадає.',
    'unique'    => 'Значення поля :attribute вже використовується.',
    'exists'    => 'Обране значення поля :attribute некоректне.',
    'image'     => 'Поле :attribute має бути зображенням.',
    'mimes'     => 'Поле :attribute має бути файлом одного з типів: :values.',
    'owned_relation_requires_embed_payload' => 'Цей зв\'язок належить хосту і приймає лише вкладений payload, а не зовнішній id.',
];
