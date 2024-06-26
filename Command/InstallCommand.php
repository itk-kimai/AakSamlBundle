<?php

namespace KimaiPlugin\AakSamlBundle\Command;

use App\Command\AbstractBundleInstallerCommand;

class InstallCommand extends AbstractBundleInstallerCommand
{
    protected function getBundleCommandNamePart(): string
    {
        return 'aak-saml';
    }

    protected function getMigrationConfigFilename(): ?string
    {
        return __DIR__.'/../Migrations/aak-saml.yaml';
    }
}
