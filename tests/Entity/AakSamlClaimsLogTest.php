<?php

/*
 * This file is part of the "AakSamlBundle" for Kimai.
 * All rights reserved by ITK Development (https://github.com/itk-kimai).
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\AakSamlBundle\Tests\Entity;

use KimaiPlugin\AakSamlBundle\Entity\AakSamlClaimsLog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AakSamlClaimsLog::class)]
final class AakSamlClaimsLogTest extends TestCase
{
    public function testExceptionMessageIsCapturedVerbatim(): void
    {
        $log = new AakSamlClaimsLog('user@aarhus.dk', false, [], new \Exception('Validation Failed'));

        // Regression guard: the message must be stored, not silently emptied.
        self::assertSame('Validation Failed', $log->getExceptionMessage());
    }

    public function testLongExceptionMessageIsTruncatedToColumnLength(): void
    {
        $message = str_repeat('x', 300);
        $log = new AakSamlClaimsLog('user@aarhus.dk', false, [], new \Exception($message));

        // The exceptionMessage column is VARCHAR(255).
        self::assertSame(255, mb_strlen((string) $log->getExceptionMessage()));
        self::assertSame(str_repeat('x', 255), $log->getExceptionMessage());
    }

    public function testNoExceptionYieldsNullMessage(): void
    {
        $log = new AakSamlClaimsLog('user@aarhus.dk', true, []);

        self::assertNull($log->getExceptionMessage());
    }
}
