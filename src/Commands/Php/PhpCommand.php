<?php

namespace Chriha\ProjectCLI\Commands\Php;

use Chriha\ProjectCLI\Commands\Command;
use Chriha\ProjectCLI\Services\Docker;

class PhpCommand extends Command
{

    /** @var string */
    protected static $defaultName = 'php';

    /** @var bool */
    protected $requiresProject = true;


    public function configure() : void
    {
        $this->setDescription( 'Run PHP commands' )
            ->addDynamicArguments()->addDynamicOptions();
    }

    public function handle( Docker $docker ) : void
    {
        passthru( "{$docker->compose()} {$docker->runExec()} php "
            . implode( " ", $this->getParameters() ) );
    }

}
