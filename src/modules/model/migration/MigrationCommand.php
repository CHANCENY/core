<?php

namespace Simp\Core\modules\model\migration;

use Simp\Modal\ModalDefinitions\ModalConfiguration;
use Simp\Modal\ModalDefinitions\ModalMigrationCLI;

class MigrationCommand extends ModalMigrationCLI
{
    public function __construct(ModalConfiguration $modalConfiguration)
    {
        parent::__construct($modalConfiguration);
    }
}