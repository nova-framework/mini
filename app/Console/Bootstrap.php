<?php

/**
 * Console - register the Forge Commands and Schedule
 */


/**
 * Resolve the Forge commands from application.
 */
Forge::resolveCommands(array(
    //'App\Console\Commands\MagicWand',
));


/**
 * Schedule the flushing of Mailer's Messages Spool.
 */
Schedule::command('spool:flush')->everyMinute();
