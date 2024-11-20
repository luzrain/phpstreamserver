<?php

declare(strict_types=1);

use Symplify\MonorepoBuilder\ComposerJsonManipulator\ValueObject\ComposerJsonSection;
use Symplify\MonorepoBuilder\Config\MBConfig;

return static function (MBConfig $mbConfig): void {
    $mbConfig->packageDirectories([
        __DIR__ . '/src/Plugins',
        __DIR__ . '/src/Server',
    ]);

    $mbConfig->dataToRemove([
        ComposerJsonSection::REQUIRE => [
            'phpstreamserver/phpstreamserver' => '*',
        ],
    ]);
};
