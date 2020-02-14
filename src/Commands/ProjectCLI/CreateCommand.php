<?php

namespace Chriha\ProjectCLI\Commands\ProjectCLI;

use Chriha\ProjectCLI\Commands\Command;
use Chriha\ProjectCLI\Helpers;
use Chriha\ProjectCLI\Services\Docker;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Process\Process;

class CreateCommand extends Command
{

    /** @var string */
    protected static $defaultName = 'create';

    /** @var string */
    protected $description = 'Create a new project';

    /** @var array */
    protected $types = [
        'django'  => 'https://github.com/chriha/project-cli-env-django.git',
        'laravel' => 'https://github.com/chriha/project-cli-env-laravel.git',
        'node'    => 'https://github.com/chriha/project-cli-env-node.git',
        'php'     => 'https://github.com/chriha/project-cli-env-php.git',
    ];


    public function configure() : void
    {
        $this->addOption(
            'type',
            't',
            InputOption::VALUE_OPTIONAL,
            'Type of the project. Options: ' . implode(', ', array_keys($this->types)),
            'php'
        )
            ->addOption(
                'repository',
                null,
                InputOption::VALUE_REQUIRED,
                'Specify the repository to use as base structure'
            )
            ->addOption(
                'setup',
                null,
                InputOption::VALUE_NONE,
                'Setup the project by its type'
            );
        $this->addArgument('directory', InputArgument::REQUIRED, 'Project directory');
    }

    /**
     * Execute the console command.
     *
     * @param Docker $docker
     * @return mixed
     */
    public function handle(Docker $docker) : void
    {
        if (PROJECT_IS_INSIDE) {
            $this->abort('You are currently in a project');
        }

        $repository = $this->repository();
        $directory  = $this->argument('directory');

        if (is_dir($directory)) {
            $this->abort(sprintf("Directory '%s' already exists", $directory));
        }

        $clone = new Process(['git', 'clone', '-q', $repository, $directory]);

        $this->spinner('Creating project', $clone);

        $path = getcwd() . DS . $directory;

        Helpers::recursiveRemoveDir($path . DS . '.git');

        $this->spinner('Initializing git', new Process(['git', 'init'], $path));

        if ( ! $this->option('setup')) {
            return;
        }

        chdir($path);
        copy('.env.example', '.env');
        touch('src' . DS . '.env');

        if ($this->option('type') == 'laravel') {
            if ( ! empty($blocked = $docker->hasOccupiedPorts())) {
                $this->abort('Ports are already occupied: ' . implode(', ', $blocked));
            }

            $this->setupLaravel($path);
        }

        $this->info(sprintf("Project '%s' successfully set up", $directory));
    }

    private function setupLaravel(string $path) : void
    {
        $destination = 'temp';
        chdir($path);

        $this->spinner( 'Setting up Laravel', new Process( [
            'project', 'composer', 'create-project', 'laravel/laravel', $destination
        ], getcwd() ) );

        $this->spinner( 'Shutting down containers', new Process( [ 'project', 'down' ], getcwd() ) );

        // move setup into temp
        rename(getcwd() . DS . 'src' . DS . $destination, getcwd() . DS . 'temp' . DS . 'src');
        // rm src directory
        Helpers::rmdir(getcwd() . DS . 'src');
        // mv temp/src into .
        rename(getcwd() . DS . 'temp' . DS . 'src', getcwd() . DS . 'src');
    }

    protected function repository() : string
    {
        if ($this->option('repository')) {
            return $this->option('repository');
        } elseif (filter_var($this->option('type'), FILTER_VALIDATE_URL)) {
            return $this->option('type');
        } elseif (in_array($this->option('type'), array_keys($this->types))) {
            return $this->types[$this->option('type')];
        }

        $this->abort(sprintf('Unknown type: %s', $this->option('type')));
    }

    public static function isActive() : bool
    {
        return ! PROJECT_IS_INSIDE;
    }

}