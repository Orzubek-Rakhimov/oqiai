<?php

/*
|--------------------------------------------------------------------------
| Validation messages (Uzbek, Latin script)
|--------------------------------------------------------------------------
|
| Rules missing here fall back to the framework's English messages, so add a
| line whenever a new rule is used in a form the Parent or Child can see.
|
*/

return [

    'accepted' => ':Attribute qabul qilinishi kerak.',
    'array' => ':Attribute ro\'yxat bo\'lishi kerak.',
    'between' => [
        'array' => ':Attribute :min tadan :max tagacha bo\'lishi kerak.',
        'file' => ':Attribute hajmi :min dan :max kilobaytgacha bo\'lishi kerak.',
        'numeric' => ':Attribute :min dan :max gacha bo\'lishi kerak.',
        'string' => ':Attribute :min dan :max belgigacha bo\'lishi kerak.',
    ],
    'boolean' => ':Attribute ha yoki yo\'q bo\'lishi kerak.',
    'confirmed' => ':Attribute tasdig\'i mos kelmadi.',
    'current_password' => 'Parol noto\'g\'ri.',
    'enum' => 'Tanlangan :attribute noto\'g\'ri.',
    'exists' => 'Tanlangan :attribute topilmadi.',
    'file' => ':Attribute fayl bo\'lishi kerak.',
    'in' => 'Tanlangan :attribute noto\'g\'ri.',
    'integer' => ':Attribute butun son bo\'lishi kerak.',
    'max' => [
        'array' => ':Attribute :max tadan ko\'p bo\'lmasligi kerak.',
        'file' => ':Attribute hajmi :max kilobaytdan oshmasligi kerak.',
        'numeric' => ':Attribute :max dan katta bo\'lmasligi kerak.',
        'string' => ':Attribute :max belgidan oshmasligi kerak.',
    ],
    'mimes' => ':Attribute turi quyidagilardan biri bo\'lishi kerak: :values.',
    'mimetypes' => ':Attribute turi quyidagilardan biri bo\'lishi kerak: :values.',
    'min' => [
        'array' => ':Attribute kamida :min ta bo\'lishi kerak.',
        'file' => ':Attribute hajmi kamida :min kilobayt bo\'lishi kerak.',
        'numeric' => ':Attribute kamida :min bo\'lishi kerak.',
        'string' => ':Attribute kamida :min belgidan iborat bo\'lishi kerak.',
    ],
    'numeric' => ':Attribute son bo\'lishi kerak.',
    'password' => [
        'letters' => ':Attribute kamida bitta harfdan iborat bo\'lishi kerak.',
        'mixed' => ':Attribute kamida bitta katta va bitta kichik harfga ega bo\'lishi kerak.',
        'numbers' => ':Attribute kamida bitta raqamga ega bo\'lishi kerak.',
        'symbols' => ':Attribute kamida bitta belgiga ega bo\'lishi kerak.',
        'uncompromised' => 'Bu :attribute ma\'lumotlar sizib chiqishida uchragan. Boshqa :attribute tanlang.',
    ],
    'regex' => ':Attribute noto\'g\'ri ko\'rinishda.',
    'required' => ':Attribute maydonini to\'ldiring.',
    'string' => ':Attribute matn bo\'lishi kerak.',
    'unique' => 'Bu :attribute allaqachon band.',

    /*
    |--------------------------------------------------------------------------
    | Custom messages for specific fields
    |--------------------------------------------------------------------------
    */

    'custom' => [
        'phone' => [
            'regex' => 'Telefon raqamini to\'g\'ri kiriting, masalan: +998 90 123 45 67.',
            'unique' => 'Bu telefon raqami allaqachon ro\'yxatdan o\'tgan.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Field names
    |--------------------------------------------------------------------------
    */

    'attributes' => [
        'avatar' => 'rasm',
        'current_password' => 'joriy parol',
        'grade' => 'sinf',
        'name' => 'ism',
        'password' => 'parol',
        'password_confirmation' => 'parol tasdig\'i',
        'phone' => 'telefon raqami',
    ],

];
