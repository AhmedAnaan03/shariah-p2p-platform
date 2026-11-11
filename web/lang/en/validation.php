<?php

/*
| Overrides for Laravel's built-in validation messages. Laravel loads its own
| lang/en/validation.php first and then merges this file over it, so only the keys listed
| here change; every other message stays the framework default.
|
| These are the messages of the Password rule (Password::defaults() in AppServiceProvider),
| which register, reset password and change password all use.
*/

return [
    'password' => [
        'mixed' => 'Your password must include both upper-case and lower-case letters.',
        'numbers' => 'Your password must include at least one number.',
        'symbols' => 'Your password must include at least one symbol, such as ! or #.',
    ],
];
