<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Service;

use App\Configuration\SamlConfigurationInterface;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;

/**
 * Compares the IdP signing certificates Kimai is configured with against the ones
 * the IdP currently publishes.
 *
 * The rule is containment, not equality: every certificate the IdP publishes must
 * be configured. onelogin/php-saml verifies a response against the configured
 * certificates only — it ignores the one the response itself carries — and we
 * cannot know which of the published certificates the IdP will sign the next
 * response with. So one published certificate we do not hold is enough to break
 * an arbitrary share of logins. The reverse, a configured certificate the IdP no
 * longer publishes, is harmless and only reported as removable.
 */
class SamlCertificateChecker
{
    public function __construct(
        private readonly SamlConfigurationInterface $configuration,
    ) {
    }

    /**
     * @param list<string> $publishedCertificates raw certificates read from IdP metadata
     * @param int          $warnDays              how far ahead to look for expiry
     *
     * @throws AakSamlException when a certificate on either side cannot be read
     */
    public function check(array $publishedCertificates, int $warnDays, \DateTimeImmutable $now): SamlCertificateCheckResult
    {
        $configured = $this->configuredCertificates();

        $published = [];
        foreach ($publishedCertificates as $certificate) {
            $parsed = SamlCertificate::fromString($certificate);
            $published[$parsed->fingerprint] = $parsed;
        }

        $missing = array_values(array_diff_key($published, $configured));
        $unused = array_values(array_diff_key($configured, $published));

        $expiring = [];
        foreach (array_intersect_key($published, $configured) as $certificate) {
            if ($certificate->expiresWithin($now, $warnDays)) {
                $expiring[] = $certificate;
            }
        }

        return new SamlCertificateCheckResult(
            $this->status($missing, $expiring),
            array_values($published),
            $missing,
            $expiring,
            $unused,
        );
    }

    /**
     * @param list<SamlCertificate> $missing
     * @param list<SamlCertificate> $expiring
     */
    private function status(array $missing, array $expiring): SamlCertificateStatus
    {
        if ([] !== $missing) {
            return SamlCertificateStatus::Mismatch;
        }

        if ([] !== $expiring) {
            return SamlCertificateStatus::ExpiringSoon;
        }

        return SamlCertificateStatus::Ok;
    }

    /**
     * Both shapes Kimai accepts under `kimai.saml.connection.idp`: the single
     * `x509cert`, and the `x509certMulti.signing` list used across a rotation.
     *
     * @return array<string, SamlCertificate> keyed by fingerprint
     *
     * @throws AakSamlException
     */
    private function configuredCertificates(): array
    {
        $idp = $this->configuration->getConnection()['idp'] ?? null;
        if (!\is_array($idp)) {
            return [];
        }

        $raw = [];

        $single = $idp['x509cert'] ?? null;
        if (\is_string($single) && '' !== trim($single)) {
            $raw[] = $single;
        }

        $multi = $idp['x509certMulti'] ?? null;
        $signing = \is_array($multi) ? ($multi['signing'] ?? null) : null;
        if (\is_array($signing)) {
            foreach ($signing as $certificate) {
                if (\is_string($certificate) && '' !== trim($certificate)) {
                    $raw[] = $certificate;
                }
            }
        }

        $certificates = [];
        foreach ($raw as $certificate) {
            $parsed = SamlCertificate::fromString($certificate);
            $certificates[$parsed->fingerprint] = $parsed;
        }

        return $certificates;
    }
}
