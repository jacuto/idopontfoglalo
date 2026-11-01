<?php
return [
 'host'=>env('PLATFORM_MAIL_HOST'), 'port'=>env('PLATFORM_MAIL_PORT',587), 'username'=>env('PLATFORM_MAIL_USERNAME'),
 'password'=>env('PLATFORM_MAIL_PASSWORD'), 'from'=>env('PLATFORM_MAIL_FROM_ADDRESS'), 'name'=>env('PLATFORM_MAIL_FROM_NAME','Ráérő'),
];
