# AakSamlBundle
Bundle to handle mapping between Aarhus Kommune SAML claims and Kimai team structure

## Claims structure:

```php
array (
  'http://schemas.microsoft.com/ws/2008/06/identity/claims/windowsaccountname' => 
  array (
    0 => 'azxy123',
  ),
  'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name' => 
  array (
    0 => 'John Doe',
  ),
  'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress' => 
  array (
    0 => 'jd@example.com',
  ),
  'managerdisplayname' => 
  array (
    0 => 'Jane Doe',
  ),
  'companyname' => 
  array (
    0 => 'Aarhus Kommune',
  ),
  'division' => 
  array (
    0 => 'Kultur og Borgerservice',
  ),
  'department' => 
  array (
    0 => 'Borgerservice og Biblioteker',
  ),
  'extensionAttribute12' => 
  array (
    0 => 'ITK',
  ),
  'Office' => 
  array (
    0 => 'ITK Development',
  ),
  'extensionAttribute7' => 
  array (
    0 => '1001;1004;1012;1103;6530',
  ),
  'sessionIndex' => '104ec668-7e72-46e3-9bbb-e871337f45eb',
)
```
