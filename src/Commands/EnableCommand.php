<?php

namespace akazorg\VoyagerTemplates\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use TCG\Voyager\VoyagerServiceProvider;

class EnableCommand extends Command
{
    use Seedable;

    protected $seedersPath = __DIR__.'/../../publishable/database/seeds/';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'voyager-templates:enable';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enable the hook Voyager Templates';

    protected function getOptions()
    {
        return [
            ['enable', null, InputOption::VALUE_NONE, 'Enable the hook Voyager Templates', null],
        ];
    }

    /**
     * Get the composer command for the environment.
     *
     * @return string
     */
    protected function findComposer()
    {
        if (file_exists(getcwd().'/composer.phar')) {
            return '"'.PHP_BINARY.'" '.getcwd().'/composer.phar';
        }

        return 'composer';
    }

    /**
     * Execute the console command.
     *
     * @param \Illuminate\Filesystem\Filesystem $filesystem
     *
     * @return void
     */
    public function fire(Filesystem $filesystem)
    {
        $this->info('Publishing the Voyager assets, database, and config files');
        $this->call('vendor:publish', ['--provider' => VoyagerServiceProvider::class]);

        $this->info('Migrating the database tables into your application');
        $this->call('migrate');

        $this->info('Dumping the autoloaded files and reloading all new files');

        $composer = $this->findComposer();

        $process = new Process($composer.' dump-autoload');
        $process->setWorkingDirectory(base_path())->run();

        $this->info('Adding Voyager routes to routes/web.php');
        $filesystem->append(
            base_path('routes/web.php'),
            "\n\nRoute::group(['prefix' => 'admin'], function () {\n    Voyager::routes();\n});\n"
        );

        \Route::group(['prefix' => 'admin'], function () {
            \Voyager::routes();
        });

        $this->info('Seeding data into the database');
        $this->seed('VoyagerDatabaseSeeder');

        if ($this->option('with-dummy')) {
            $this->seed('VoyagerDummyDatabaseSeeder');
        }

        $this->info('Successfully enabled Voyager! Enjoy 🎉');
    }
}
