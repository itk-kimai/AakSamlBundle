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
use OneLogin\Saml2\IdPMetadataParser;

/**
 * Reads the signing certificates an IdP publishes in its SAML metadata.
 */
class IdpMetadataFetcher
{
    /**
     * Deliberately returns certificates and nothing else.
     *
     * `IdPMetadataParser::parseRemoteXML()` also hands back the entity id, the SSO
     * endpoint and the bindings, and it would be tempting to trust those too. We
     * do not: a metadata document that could redirect sign-in to another host is a
     * far worse outcome than one that is merely out of date, and those values
     * belong under review in `local.yaml`.
     *
     * @param string      $metadataUrl the IdP's published metadata endpoint
     * @param string|null $entityId    when set, only the matching EntityDescriptor is read
     *
     * @return list<string> raw certificates, in the order the IdP lists them
     *
     * @throws AakSamlException when the metadata cannot be fetched or parsed
     */
    public function signingCertificates(string $metadataUrl, ?string $entityId = null): array
    {
        try {
            // validatePeer defaults to false in php-saml, which would skip TLS
            // verification on the one request whose answer we are about to trust.
            $metadata = IdPMetadataParser::parseRemoteXML($metadataUrl, $entityId, validatePeer: true);
        } catch (\Exception $exception) {
            throw new AakSamlException(\sprintf('Could not read IdP metadata from %s: %s', $metadataUrl, $exception->getMessage()), 0, $exception);
        }

        if (!\is_array($metadata)) {
            throw new AakSamlException(\sprintf('IdP metadata at %s was not readable.', $metadataUrl));
        }

        $idp = $metadata['idp'] ?? null;
        if (!\is_array($idp)) {
            throw new AakSamlException(\sprintf('IdP metadata at %s describes no identity provider%s.', $metadataUrl, null === $entityId ? '' : \sprintf(' with entity id "%s"', $entityId)));
        }

        $certificates = [];

        // The parser reports a single signing certificate as `x509cert` and several
        // as `x509certMulti.signing`, so both shapes have to be read.
        $single = $idp['x509cert'] ?? null;
        if (\is_string($single) && '' !== trim($single)) {
            $certificates[] = $single;
        }

        $multi = $idp['x509certMulti'] ?? null;
        $signing = \is_array($multi) ? ($multi['signing'] ?? null) : null;
        if (\is_array($signing)) {
            foreach ($signing as $certificate) {
                if (\is_string($certificate) && '' !== trim($certificate)) {
                    $certificates[] = $certificate;
                }
            }
        }

        return array_values(array_unique($certificates));
    }
}
