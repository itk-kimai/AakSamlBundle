<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Command;

use App\Configuration\SamlConfigurationInterface;
use KimaiPlugin\AakSamlBundle\Exception\AakSamlException;
use KimaiPlugin\AakSamlBundle\Service\IdpMetadataFetcher;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificate;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificateChecker;
use KimaiPlugin\AakSamlBundle\Service\SamlCertificateStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'kimai:bundle:aak-saml:check-idp-certificate')]
class CheckIdpCertificateCommand extends Command
{
    private const int DEFAULT_WARN_DAYS = 30;

    public function __construct(
        private readonly SamlConfigurationInterface $configuration,
        private readonly SamlCertificateChecker $checker,
        private readonly IdpMetadataFetcher $fetcher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp(<<<'HELP'
            Compares the IdP signing certificates in <info>local.yaml</info> against the ones the
            identity provider publishes in its SAML metadata, and reports certificates
            about to expire.

            Nothing rotates the configured certificate automatically. When the IdP is
            given a new signing key, every login fails with "Signature validation failed.
            SAML Response rejected" until <info>kimai.saml.connection.idp.x509cert</info> is updated.
            Run this from monitoring so that turns into an alert rather than an outage.

            Exit codes, so it can be wired up directly:

              <info>0</info>  every published certificate is configured, none expiring soon
              <info>1</info>  act now: a published certificate is missing, or one expires soon
              <info>2</info>  the check could not run: SAML off, bad options, metadata unreachable

            Example:

              <info>%command.full_name% --metadata-url=https://example.b2clogin.com/example.onmicrosoft.com/B2C_1A_Policy/Samlp/metadata</info>
            HELP);

        $this->addOption(
            'metadata-url',
            null,
            InputOption::VALUE_REQUIRED,
            'The IdP metadata endpoint to compare against',
        );

        $this->addOption(
            'warn-days',
            null,
            InputOption::VALUE_REQUIRED,
            'Report a configured certificate that expires within this many days',
            self::DEFAULT_WARN_DAYS,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->configuration->isActivated()) {
            $io->error('SAML is not activated, so there is no certificate to check.');

            return Command::INVALID;
        }

        $metadataUrl = $input->getOption('metadata-url');
        if (!\is_string($metadataUrl) || '' === trim($metadataUrl)) {
            $io->error('The --metadata-url option is required.');

            return Command::INVALID;
        }

        $warnDays = filter_var($input->getOption('warn-days'), \FILTER_VALIDATE_INT);
        if (!\is_int($warnDays) || $warnDays < 0) {
            $io->error('The --warn-days option must be a positive whole number of days.');

            return Command::INVALID;
        }

        try {
            $published = $this->fetcher->signingCertificates($metadataUrl, $this->configuredEntityId());
            $result = $this->checker->check($published, $warnDays, new \DateTimeImmutable());
        } catch (AakSamlException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $missingFingerprints = array_flip(array_map(
            static fn (SamlCertificate $certificate): string => $certificate->fingerprint,
            $result->missing,
        ));

        $io->table(
            ['Published for signing', 'Expires', 'Configured', 'Subject'],
            array_map(
                static fn (SamlCertificate $certificate): array => [
                    substr($certificate->formattedFingerprint(), 0, 23).'…',
                    $certificate->notAfter->format('Y-m-d H:i T'),
                    isset($missingFingerprints[$certificate->fingerprint]) ? 'NO' : 'yes',
                    $certificate->subject,
                ],
                $result->published,
            ),
        );

        foreach ($result->unused as $certificate) {
            $io->note(\sprintf(
                'Configured but no longer published, so it can be dropped: %s',
                $certificate->formattedFingerprint(),
            ));
        }

        return match ($result->status) {
            SamlCertificateStatus::Mismatch => $this->reportMismatch($io, $result->missing, $metadataUrl),
            SamlCertificateStatus::ExpiringSoon => $this->reportExpiring($io, $result->expiring, $warnDays),
            SamlCertificateStatus::Ok => $this->reportOk($io, \count($result->published)),
        };
    }

    /**
     * @param list<SamlCertificate> $missing
     */
    private function reportMismatch(SymfonyStyle $io, array $missing, string $metadataUrl): int
    {
        $io->error(\sprintf(
            '%d signing certificate(s) published by the IdP are not configured. Logins signed with them fail.',
            \count($missing),
        ));

        foreach ($missing as $certificate) {
            $io->writeln(\sprintf(' <comment>%s</comment>', $certificate->formattedFingerprint()));
            $io->writeln(\sprintf('   expires %s', $certificate->notAfter->format('Y-m-d H:i T')));
            $io->writeln(\sprintf('   subject %s', $certificate->subject));
        }

        $io->newLine();
        $io->writeln('Copy the certificate from the metadata into kimai.saml.connection.idp.x509cert as a');
        $io->writeln('single line, then run <info>kimai:reload</info> and restart php-fpm. To carry both across a');
        $io->writeln('rotation, list them under <info>idp.x509certMulti.signing</info> instead.');
        $io->writeln(\sprintf('Metadata: %s', $metadataUrl));

        return Command::FAILURE;
    }

    /**
     * @param list<SamlCertificate> $expiring
     */
    private function reportExpiring(SymfonyStyle $io, array $expiring, int $warnDays): int
    {
        $io->warning(\sprintf(
            'Every published certificate is configured, but %d expires within %d days.',
            \count($expiring),
            $warnDays,
        ));

        foreach ($expiring as $certificate) {
            $io->writeln(\sprintf(
                ' <comment>%s</comment> expires %s',
                $certificate->formattedFingerprint(),
                $certificate->notAfter->format('Y-m-d H:i T'),
            ));
        }

        return Command::FAILURE;
    }

    private function reportOk(SymfonyStyle $io, int $published): int
    {
        $io->success(\sprintf('All %d published signing certificate(s) are configured.', $published));

        return Command::SUCCESS;
    }

    /**
     * Passed to the parser so it reads the EntityDescriptor we actually federate with,
     * rather than whichever one happens to come first.
     */
    private function configuredEntityId(): ?string
    {
        $idp = $this->configuration->getConnection()['idp'] ?? null;
        if (!\is_array($idp)) {
            return null;
        }

        $entityId = $idp['entityId'] ?? null;

        return \is_string($entityId) && '' !== trim($entityId) ? $entityId : null;
    }
}
