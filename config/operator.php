<?php
// Enter the operator's real identity and bank details in the private /foadmin UI.
// Never commit personal addresses, tax identifiers or bank account numbers.
return [
 'password_hash'=>env('PLATFORM_ADMIN_PASSWORD_HASH'),
 'name'=>'', 'country'=>'Magyarország','zip'=>'','city'=>'','address'=>'',
 'taxNumber'=>'','registrationNumber'=>'','vat'=>'AAM',
 'bank'=>'','account'=>'','iban'=>'','bic'=>'',
];
