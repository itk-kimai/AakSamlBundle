<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Service;

use App\Configuration\SamlConfigurationInterface;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificate;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificateChecker;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificateStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamlCertificateChecker::class)]
final class SamlCertificateCheckerTest extends TestCase
{
    private const string NOW = '2026-09-11 12:00:00';

    /**
     * @param array<string, mixed> $idp
     */
    private function checker(array $idp): SamlCertificateChecker
    {
        $configuration = $this->createStub(SamlConfigurationInterface::class);
        $configuration->method('getConnection')->willReturn(['idp' => $idp]);

        return new SamlCertificateChecker($configuration);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::NOW);
    }

    /**
     * @param list<SamlCertificate> $certificates
     *
     * @return list<string>
     */
    private static function fingerprints(array $certificates): array
    {
        return array_map(static fn (SamlCertificate $c): string => $c->fingerprint, $certificates);
    }

    private static function fingerprintOf(string $certificate): string
    {
        return SamlCertificate::fromString($certificate)->fingerprint;
    }

    public function testOkWhenThePublishedCertificateIsConfigured(): void
    {
        $result = $this->checker(['x509cert' => CertificateFixtures::CERT_A])
            ->check([CertificateFixtures::CERT_A], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Ok, $result->status);
        self::assertTrue($result->isOk());
        self::assertSame([], $result->missing);
        self::assertSame([], $result->unused);
    }

    /**
     * The rotation case: the IdP has moved to a new key and the configuration still
     * holds the old one. Every login signed with the new key fails.
     */
    public function testMismatchWhenThePublishedCertificateIsNotConfigured(): void
    {
        $result = $this->checker(['x509cert' => CertificateFixtures::CERT_A])
            ->check([CertificateFixtures::CERT_C], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Mismatch, $result->status);
        self::assertFalse($result->isOk());
        self::assertSame([self::fingerprintOf(CertificateFixtures::CERT_C)], self::fingerprints($result->missing));
        self::assertSame([self::fingerprintOf(CertificateFixtures::CERT_A)], self::fingerprints($result->unused));
    }

    /**
     * Containment, not equality: holding one of two published certificates is not
     * enough, because the IdP may sign the next response with either.
     */
    public function testMismatchWhenOnlyOneOfTwoPublishedCertificatesIsConfigured(): void
    {
        $result = $this->checker(['x509cert' => CertificateFixtures::CERT_A])
            ->check([CertificateFixtures::CERT_A, CertificateFixtures::CERT_C], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Mismatch, $result->status);
        self::assertSame([self::fingerprintOf(CertificateFixtures::CERT_C)], self::fingerprints($result->missing));
    }

    public function testReadsCertificatesFromX509CertMultiSigning(): void
    {
        $result = $this->checker([
            'x509certMulti' => ['signing' => [CertificateFixtures::CERT_A, CertificateFixtures::CERT_C]],
        ])->check([CertificateFixtures::CERT_A, CertificateFixtures::CERT_C], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Ok, $result->status);
    }

    public function testCombinesX509CertAndX509CertMultiSigning(): void
    {
        $result = $this->checker([
            'x509cert' => CertificateFixtures::CERT_A,
            'x509certMulti' => ['signing' => [CertificateFixtures::CERT_C]],
        ])->check([CertificateFixtures::CERT_A, CertificateFixtures::CERT_C], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Ok, $result->status);
    }

    public function testExpiringSoonWhenAConfiguredCertificateIsInsideTheWindow(): void
    {
        $result = $this->checker(['x509cert' => CertificateFixtures::CERT_B])
            ->check([CertificateFixtures::CERT_B], 45, $this->now());

        self::assertSame(SamlCertificateStatus::ExpiringSoon, $result->status);
        self::assertSame([self::fingerprintOf(CertificateFixtures::CERT_B)], self::fingerprints($result->expiring));
    }

    public function testNotExpiringWhenTheWindowIsShorterThanTheRemainingLife(): void
    {
        $result = $this->checker(['x509cert' => CertificateFixtures::CERT_B])
            ->check([CertificateFixtures::CERT_B], 10, $this->now());

        self::assertSame(SamlCertificateStatus::Ok, $result->status);
        self::assertSame([], $result->expiring);
    }

    /**
     * A missing certificate is the more urgent finding, so it must not be masked by
     * one that merely expires soon.
     */
    public function testMismatchOutranksExpiringSoon(): void
    {
        $result = $this->checker(['x509cert' => CertificateFixtures::CERT_B])
            ->check([CertificateFixtures::CERT_B, CertificateFixtures::CERT_C], 45, $this->now());

        self::assertSame(SamlCertificateStatus::Mismatch, $result->status);
        self::assertNotSame([], $result->expiring);
    }

    public function testEverythingIsMissingWhenNothingIsConfigured(): void
    {
        $result = $this->checker([])->check([CertificateFixtures::CERT_A], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Mismatch, $result->status);
        self::assertSame([self::fingerprintOf(CertificateFixtures::CERT_A)], self::fingerprints($result->missing));
    }

    public function testIgnoresAnEmptyConfiguredCertificate(): void
    {
        $result = $this->checker(['x509cert' => '  '])->check([], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Ok, $result->status);
        self::assertSame([], $result->unused);
    }

    public function testTheSameCertificateConfiguredTwiceCountsOnce(): void
    {
        $result = $this->checker([
            'x509cert' => CertificateFixtures::CERT_A,
            'x509certMulti' => ['signing' => [implode(' ', str_split(CertificateFixtures::CERT_A, 64))]],
        ])->check([CertificateFixtures::CERT_A], 30, $this->now());

        self::assertSame(SamlCertificateStatus::Ok, $result->status);
        self::assertSame([], $result->unused);
    }
}
