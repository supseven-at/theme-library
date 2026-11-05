<?php

declare(strict_types=1);

namespace Supseven\ThemeLibrary\Service;

/**
 * @author Georg Großberger <g.grossberger@supseven.at>
 */
class DomFactory
{
    public function createFromBodyHtml(string $html): \Dom\HTMLDocument
    {
        if (!str_contains($html, '<html') || !str_contains($html, '<body')) {
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    ' . $html . '
</body>
</html>';
        }

        return \Dom\HTMLDocument::createFromString($html);
    }
}
