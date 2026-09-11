<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Service;

final class SamlCertificateCheckResult
{
    /**
     * @param list<SamlCertificate> $published certificates the IdP currently advertises for signing
     * @param list<SamlCertificate> $missing   published but absent from the configuration
     * @param list<SamlCertificate> $expiring  published, configured, and expiring within the warning window
     * @param list<SamlCertificate> $unused    configured but no longer published, so safe to drop
     */
    public function __construct(
        public readonly SamlCertificateStatus $status,
        public readonly array $published,
        public readonly array $missing,
        public readonly array $expiring,
        public readonly array $unused,
    ) {
    }

    public function isOk(): bool
    {
        return SamlCertificateStatus::Ok === $this->status;
    }
}
