<?php

//--------------------------------------------------------------------------
// Send the E-mails Queued In Spool
//--------------------------------------------------------------------------

// Get the Swift Transport instance.
$transport = app('swift.transport');

// Get the Swift Spool instance.
$spool = app('swift.transport.spool')->getSpool();

// Execute a recovery if for any reason a process is sending for too long.
$timeout = (int) config('mail.spool.timeout', 900);

if ($timeout > 0) {
    $spool->recover($timeout);
}

// Sends the queued messages using the given transport instance.
$result = $spool->flushQueue($transport);

echo "Sent {$result} email(s) ...\n";
