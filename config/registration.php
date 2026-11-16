<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Registration safeguards
    |--------------------------------------------------------------------------
    |
    | Sign-up stays open to anyone. These limit abuse: an e-mail to the owner
    | about every new account, a cap on sign-up attempts per IP address, and
    | removal of accounts that never verified their e-mail address.
    |
    */

    // Where to report new accounts; empty disables the e-mail.
    'notification_mail' => env('REGISTRATION_NOTIFICATION_MAIL', env('BACKUP_NOTIFICATION_MAIL')),

    // Sign-up attempts (POST /register, failed ones included) per IP address per hour.
    'attempts_per_hour' => (int) env('REGISTRATION_ATTEMPTS_PER_HOUR', 5),

    // Days after which an account that never verified its e-mail and holds no data is deleted.
    'unverified_days' => (int) env('REGISTRATION_UNVERIFIED_DAYS', 7),

];
