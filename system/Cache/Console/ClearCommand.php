<?php

namespace System\Cache\Console;

use System\Console\Command;
use System\Cache\CacheManager;
use System\Filesystem\Filesystem;


class ClearCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'cache:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush the Application cache";

    /**
     * The Cache Manager instance.
     *
     * @var \System\Cache\CacheManager
     */
    protected $cache;

    /**
     * The File System instance.
     *
     * @var \System\Filesystem\Filesystem
     */
    protected $files;

    /**
     * Create a new Cache Clear Command instance.
     *
     * @param  \System\Cache\CacheManager  $cache
     * @param  \System\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(CacheManager $cache, Filesystem $files)
    {
        parent::__construct();

        $this->cache = $cache;
        $this->files = $files;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->cache->flush();

        $this->files->delete($this->container['config']['app.manifest'] .DS .'services.php');

        $this->info('Application cache cleared!');
    }

}
