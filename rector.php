<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    // Directories to process
    $rectorConfig->paths([
        __DIR__ . '/src',
    ]);

    // Apply PHP 8.2 upgrade rules
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        SetList::CODING_STYLE,
        SetList::STRICT_BOOLEANS,
        SetList::TYPE_DECLARATION,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
    ]);
};
