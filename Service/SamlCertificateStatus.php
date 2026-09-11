<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Service;

enum SamlCertificateStatus
{
    /** Every certificate the IdP publishes is configured, none of them expiring soon. */
    case Ok;

    /** Everything is configured, but a certificate in use expires within the warning window. */
    case ExpiringSoon;

    /**
     * The IdP publishes a signing certificate that is not configured. Logins signed
     * with it fail, so this is the state a rotation leaves behind.
     */
    case Mismatch;
}
