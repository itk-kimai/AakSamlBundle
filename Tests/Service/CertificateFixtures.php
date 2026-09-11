<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Service;

/**
 * Certificates used by the SAML certificate tests.
 *
 * Self-signed and generated once, so their expiry dates never move. Tests pass the
 * moment to compare against explicitly rather than reading the clock.
 */
final class CertificateFixtures
{
    /** Self-signed fixture, expires 2036-09-08. */
    public const string CERT_A =
        'MIIDITCCAgmgAwIBAgIUQNXwnbJ2J+Aj4y4ZHNpTm9m01TAwDQYJKoZIhvcNAQELBQAwIDEe'.
        'MBwGA1UEAwwVRml4dHVyZSBJZFAgU2lnbmluZyBBMB4XDTI2MDkxMTExMTgxOFoXDTM2MDkw'.
        'ODExMTgxOFowIDEeMBwGA1UEAwwVRml4dHVyZSBJZFAgU2lnbmluZyBBMIIBIjANBgkqhkiG'.
        '9w0BAQEFAAOCAQ8AMIIBCgKCAQEAoyducOvIJQeKIJIfqpqTYXftlILuDJRUyjatAeS4x07R'.
        '70QucJLBS2ofBtN+cIEqH8wIYKb7R318mKk5fYuwC213ZjxNCedbMrxdBH9JdnlXZgI+psgK'.
        '0fvRmQaudWrsP5Irb58KgvyaXBog1+cTVBtQWKYnqo9KFEK6fps45o+3pwa70K+0h/BrYTOV'.
        'Z4K6snwNk9YGOzOts4E7xYxx57g/nk/HhuOa90rA3BaXYLe4IQg+Xg/gapY1PIOc2zUFreQN'.
        'hnGd2/7cMgpb+sbReLQ5/F5ygB4L5xPVo2dBBxi813/ooGOKfR02SFVMGh1OquL4tRaZLAMq'.
        'tRyWqhguVwIDAQABo1MwUTAdBgNVHQ4EFgQUAFBhIyNTSsBqgRqkcSIr7z3BGjQwHwYDVR0j'.
        'BBgwFoAUAFBhIyNTSsBqgRqkcSIr7z3BGjQwDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0B'.
        'AQsFAAOCAQEAmH0od3Xko1vWF7SWEtmjZBx0+KRiHfDaWYN14gX82bsmSq1TDQvlTQ/3J+Ea'.
        'kvG+v8LW0FWYNp7YAP1kph2EvlmEbXpMXuN+eNXFAiEf0q5CgNNkYM6wgScl+nWBUP56lfE8'.
        'BaJP8m+E/RWUtcjZwn6Zhn6iiHVA5Kmuim51u102MO+GGDzvQ3PFvjJbTCPKicT9G1KzSfrK'.
        'QkFEDTXmRn6lt0yhViVVDYhjeH55HmHrN4WqhMrDU5jy7R4JvrJ8R2bJikvd7GxLq1wMCA9N'.
        'UC9NQ/erKQ2omVk3NR2JozqwRx6hy3ABfIDLciFkm+Hj7UH6YfC9EUGYfp15u5Qnjw==';

    /** Self-signed fixture, expires 2026-10-11. */
    public const string CERT_B =
        'MIIDITCCAgmgAwIBAgIUNlN1RHVx6hHTy0wrCrswlZW/a7kwDQYJKoZIhvcNAQELBQAwIDEe'.
        'MBwGA1UEAwwVRml4dHVyZSBJZFAgU2lnbmluZyBCMB4XDTI2MDkxMTExMTgxOFoXDTI2MTAx'.
        'MTExMTgxOFowIDEeMBwGA1UEAwwVRml4dHVyZSBJZFAgU2lnbmluZyBCMIIBIjANBgkqhkiG'.
        '9w0BAQEFAAOCAQ8AMIIBCgKCAQEAotbWjp83t4/kRaEFYop0vWApX1DGLXM9Z+4k6Np1jBkt'.
        'MOkTnECALJIn0oAdGK7nWfoe7tqBY6dqLgpv6Fd8JdjCICi4kEWhCqjCK66gZuOM/TJuI0x7'.
        '0yd6pTufKa4AMBHOOg2TNO3GEZiaU+NndJiv1Mgx1A7J0GpG9qF0yi8SEgxWLbhVPTpw63NK'.
        '0yQE7eJT6zTMaRhbWJE3eZjyDzq7kKv/0Vb3I+IZ2G1Aj7SdLNfNUBoNmGxMHdzw7EcKql2q'.
        'iotPeYJTp4T4uyy2sD5AQnoKCw1Fg6OvAvaAWFGAy7TmGlO6+YDbbE6MLqIqEFi+uuz2PKZr'.
        'WWw1y8f3gQIDAQABo1MwUTAdBgNVHQ4EFgQUNKhu2NR1vOtoUMGNu1tHvMHeDuowHwYDVR0j'.
        'BBgwFoAUNKhu2NR1vOtoUMGNu1tHvMHeDuowDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0B'.
        'AQsFAAOCAQEAWPaWymEmR1LtLFUnMVI5TLgOlbzLa5vkO0fND4dYcwwUorh5FuyprWbsdE4b'.
        'Ck3837I1CaOV5eQcWb6Es70tJKzCKAuJG4LBPytOOJPj5DsJjlgecSRfNHE+xSBQoIX+Mop8'.
        'FTlgrbuujmkdj7WJqJm5A9Hn2Oz2yGRGNVPXgcCxaeu+Fv9meTxgzWmlp9Ex3Ou0dzEmlDw4'.
        '6s9CvSlCwjvLGLZmugCvueimo/GtwixJ4jkPnFGKqboyrwHNywb9bWdvAj24TO0HXK19Gr61'.
        'zQmf3wNw+UCy/oi+MpJQe13WNhd0Y0KU7d9VXfW9tBAbLCsbousIwlyTWp8jkofnTQ==';

    /** Self-signed fixture, expires 2036-09-08. */
    public const string CERT_C =
        'MIIDITCCAgmgAwIBAgIUT1u1DUnHGc5PuHiOKq3/k8lTs5cwDQYJKoZIhvcNAQELBQAwIDEe'.
        'MBwGA1UEAwwVRml4dHVyZSBJZFAgU2lnbmluZyBDMB4XDTI2MDkxMTExMTgxOFoXDTM2MDkw'.
        'ODExMTgxOFowIDEeMBwGA1UEAwwVRml4dHVyZSBJZFAgU2lnbmluZyBDMIIBIjANBgkqhkiG'.
        '9w0BAQEFAAOCAQ8AMIIBCgKCAQEAwuONtxnY3YCT7CpLG+WF+NJWjZkyXa8+UeDsAYqx81zL'.
        'dFy38KjohCcSvYvD54iz67hMg1nF4ngDOK8i8m7s2SzD4KAQ3dBxOG3Y81hN0DHExq3l4mLk'.
        'aFWyUKBmjXwR8T83hbUbhWzZpjX9hxQ7fgC4cArs50lwHR+qlGdqhMMmVpTZ3g3OInhqQNMG'.
        '9YjYeM54yNL1P5dUtErzwWutSbpplhPbPw4emXp7/9IeOcmESm0CA/86PWsPobfm7HBS5dX9'.
        '2koZBUdGYZ4fPkvr4lkvrG3g1nJSTeYYk/WgOeZgfbM4F36lu7aKsjJcWTACc7eMN6eup6c3'.
        'Qld1PjThzQIDAQABo1MwUTAdBgNVHQ4EFgQU9XYCUTDBq8geY5DvMwzHg0LOv7owHwYDVR0j'.
        'BBgwFoAU9XYCUTDBq8geY5DvMwzHg0LOv7owDwYDVR0TAQH/BAUwAwEB/zANBgkqhkiG9w0B'.
        'AQsFAAOCAQEAsoyUkZrzOjRIWfMJgFkS/FUmZPq2/iuaCnI2loDSSLdXTg3+iZLAbHj9X8RC'.
        '3ZpcsV+pkVgRbX45BOnmUfyo0Vk7fp3vtRWIe/B7hnEjZ5+p0/wcRsxRFJ7CrSQpHLSDhuaJ'.
        'EVGQHO5iP68phco8ML6paGR3ziMF28jJWSn9/yzH459P0iNU4b+NJgXqNYSwEMEyT0hAqCQW'.
        'Twape7VLxsCIEnOhGRg2avnuAXlEExuoAMuExQti8T8+3Z85vP3NljTtRuVVFGNTE4KE9kcV'.
        'yD3wqIRI89eyp5YhKXepZhHzfEIw25hniwrrc3Sr4OSPNcLDDWzcJWz8kc6zMAhz2Q==';
}
