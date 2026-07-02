<?php

$fileHeaderComment = <<<COMMENT
This file is part of the "AakSamlBundle" for Kimai.
All rights reserved by ITK Development (https://github.com/itk-kimai).

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
COMMENT;

$finder = (new PhpCsFixer\Finder())
    ->in(__DIR__)
    ->exclude(['Resources', 'vendor', '.github'])
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@Symfony' => true,
        'header_comment' => ['header' => $fileHeaderComment, 'separate' => 'both'],
    ])
    ->setFinder($finder)
;
