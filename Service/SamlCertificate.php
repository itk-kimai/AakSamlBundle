<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Service;

use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;

/**
 * An X.509 certificate as it appears in the SAML configuration or in IdP metadata.
 *
 * Identity is the SHA-256 fingerprint, so two certificates compare equal whatever
 * PEM markers, line breaks or whitespace they were written with. That matters
 * here: `local.yaml` may carry the certificate as a single line or as a folded
 * multi-line scalar (whose newlines YAML turns into spaces), while metadata
 * carries it wrapped at 64 characters.
 */
final class SamlCertificate
{
    private function __construct(
        public readonly string $fingerprint,
        public readonly \DateTimeImmutable $notAfter,
        public readonly string $subject,
    ) {
    }

    /**
     * @throws AakSamlException when the string is not a readable X.509 certificate
     */
    public static function fromString(string $certificate): self
    {
        $pem = self::toPem($certificate);

        // Suppressed because OpenSSL warns on unreadable input and the exception
        // below is the report we want.
        $parsed = @openssl_x509_parse($pem);
        if (!\is_array($parsed)) {
            throw new AakSamlException('Not a readable X.509 certificate: '.self::abbreviate($certificate));
        }

        $fingerprint = @openssl_x509_fingerprint($pem, 'sha256');
        if (!\is_string($fingerprint)) {
            throw new AakSamlException('Could not fingerprint certificate: '.self::abbreviate($certificate));
        }

        $notAfter = $parsed['validTo_time_t'] ?? null;
        if (!\is_int($notAfter)) {
            throw new AakSamlException('Certificate has no readable expiry: '.self::abbreviate($certificate));
        }

        $subject = $parsed['name'] ?? null;

        return new self(
            $fingerprint,
            new \DateTimeImmutable('@'.$notAfter),
            \is_string($subject) ? $subject : '',
        );
    }

    public function isExpiredAt(\DateTimeImmutable $moment): bool
    {
        return $this->notAfter <= $moment;
    }

    public function expiresWithin(\DateTimeImmutable $moment, int $days): bool
    {
        return $this->notAfter <= $moment->modify(\sprintf('+%d days', $days));
    }

    /**
     * The fingerprint as OpenSSL and the Azure portal print it: colon separated, upper case.
     */
    public function formattedFingerprint(): string
    {
        $pairs = str_split(strtoupper($this->fingerprint), 2);

        return implode(':', $pairs);
    }

    /**
     * Strips PEM markers and every kind of whitespace, then re-wraps. Mirrors what
     * onelogin/php-saml does to the configured value before it verifies a signature,
     * so a certificate that compares equal here is one onelogin would also accept.
     */
    private static function toPem(string $certificate): string
    {
        $body = str_replace(
            ['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----'],
            '',
            $certificate,
        );

        $body = preg_replace('/\s+/', '', $body);
        if (!\is_string($body)) {
            throw new AakSamlException('Could not normalise certificate: '.self::abbreviate($certificate));
        }

        return "-----BEGIN CERTIFICATE-----\n".chunk_split($body, 64, "\n").'-----END CERTIFICATE-----';
    }

    private static function abbreviate(string $certificate): string
    {
        $trimmed = trim($certificate);

        return mb_strlen($trimmed) > 40 ? mb_substr($trimmed, 0, 40).'…' : $trimmed;
    }
}
