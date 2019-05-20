<?php

namespace Mini\Mail\Console;

use Mini\Console\Command;
use Mini\Filesystem\Filesystem;


class SpoolFlushCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'spool:flush';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Flush the Mailer's Messages Spool";


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        // Get the Swift Transport instance.
        $transport = $this->container['swift.transport'];

        // Get the Swift Spool instance.
        $spool = $this->container['swift.transport.spool']->getSpool();

        // Execute a recovery if for any reason a process is sending for too long.
        $timeout = (int) config('mail.spool.timeout', 900);

        if ($timeout > 0) {
            $spool->recover($timeout);
        }

        // Sends the queued messages using the given transport instance.
        $result = $spool->flushQueue($transport);

        $this->info("Sent {$result} email(s) ...");
    }

}
