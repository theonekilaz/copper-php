<?php


use Copper\Component\Validator\ValidatorConfigurator;

return function (ValidatorConfigurator $validator) {
    // email
    $validator->email_minLength = 7;
    $validator->email_maxLength = 50;
    $validator->email_regex = '/(^[a-zA-Z0-9][a-zA-Z0-9\+\.\-\_]*@[a-zA-Z0-9][a-zA-Z0-9\-\.]*\.[a-zA-Z0-9]{2,}$)/';
    $validator->email_regex_format_example = [
        'en' => 'john.wick@gmail.com',
        'ru' => 'ivan.pavlov@mail.ru',
        'lv' => 'ivars.ozols@inbox.lv',
    ];

    $validator->phone_regex = '/(^\+?\(?\d{0,3}\)?\s?\d{8,18}$)/';
    $validator->phone_regex_format_example = '+371 12345678';

    $validator->alpha_non_strict_extra_characters = '.,';

    $validator->strict = false;
};