<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Service;

use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SamlCertificate::class)]
final class SamlCertificateTest extends TestCase
{
    /**
     * The same certificate written the ways it actually reaches us must compare equal:
     * one line from `local.yaml`, a PEM block from a downloaded file, and the
     * space-separated form a folded multi-line YAML scalar produces.
     *
     * @return iterable<string, array{string}>
     */
    public static function equivalentSpellings(): iterable
    {
        $single = CertificateFixtures::CERT_A;

        yield 'single line' => [$single];

        yield 'pem block' => [
            "-----BEGIN CERTIFICATE-----\n".chunk_split($single, 64, "\n").'-----END CERTIFICATE-----',
        ];

        yield 'folded yaml scalar' => [implode(' ', str_split($single, 64))];

        yield 'indented and padded' => ["\n\t  ".chunk_split($single, 64, "\n  ")."\n"];
    }

    #[DataProvider('equivalentSpellings')]
    public function testReadsEverySpellingOfTheSameCertificate(string $spelling): void
    {
        $expected = SamlCertificate::fromString(CertificateFixtures::CERT_A);

        self::assertSame($expected->fingerprint, SamlCertificate::fromString($spelling)->fingerprint);
    }

    public function testDifferentCertificatesGetDifferentFingerprints(): void
    {
        $a = SamlCertificate::fromString(CertificateFixtures::CERT_A);
        $b = SamlCertificate::fromString(CertificateFixtures::CERT_B);

        self::assertNotSame($a->fingerprint, $b->fingerprint);
    }

    public function testReadsExpiryAndSubject(): void
    {
        $certificate = SamlCertificate::fromString(CertificateFixtures::CERT_B);

        self::assertSame('2026-10-11', $certificate->notAfter->format('Y-m-d'));
        self::assertStringContainsString('Fixture IdP Signing B', $certificate->subject);
    }

    public function testFormatsTheFingerprintLikeOpenssl(): void
    {
        $formatted = SamlCertificate::fromString(CertificateFixtures::CERT_A)->formattedFingerprint();

        self::assertMatchesRegularExpression('/^([0-9A-F]{2}:){31}[0-9A-F]{2}$/', $formatted);
    }

    public function testExpiresWithinCountsFromTheGivenMoment(): void
    {
        $certificate = SamlCertificate::fromString(CertificateFixtures::CERT_B);
        $now = new \DateTimeImmutable('2026-09-11 12:00:00');

        self::assertTrue($certificate->expiresWithin($now, 45));
        self::assertFalse($certificate->expiresWithin($now, 10));
    }

    public function testIsExpiredAtComparesAgainstTheGivenMoment(): void
    {
        $certificate = SamlCertificate::fromString(CertificateFixtures::CERT_B);

        self::assertFalse($certificate->isExpiredAt(new \DateTimeImmutable('2026-09-11 12:00:00')));
        self::assertTrue($certificate->isExpiredAt(new \DateTimeImmutable('2026-11-01 12:00:00')));
    }

    public function testRejectsSomethingThatIsNotACertificate(): void
    {
        $this->expectException(AakSamlException::class);
        $this->expectExceptionMessage('Not a readable X.509 certificate');

        SamlCertificate::fromString('ADD YOUR CERTIFICATE HERE');
    }

    public function testRejectsAnEmptyValue(): void
    {
        $this->expectException(AakSamlException::class);

        SamlCertificate::fromString('   ');
    }
}
