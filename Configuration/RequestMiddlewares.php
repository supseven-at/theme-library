<?php

declare(strict_types=1);

return [
    'frontend' => [
        'supseven/theme-library/image-generator' => [
            'target' => \Supseven\ThemeLibrary\Imaging\GenerateMiddleware::class,
            'after'  => [
                'typo3/cms-frontend/maintenance-mode',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
                'typo3/cms-redirects/redirecthandler',
            ],
        ],
    ],
];
