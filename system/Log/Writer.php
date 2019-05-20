<?php

namespace Mini\Log;

use Psr\Log\AbstractLogger;


class Writer extends AbstractLogger
{

    public function log($level, $message, array $context = array())
    {
        $date = date('M d, Y G:iA');

        $content = $date .' - ' .strtoupper($level) .":\n\n" .$message ."\n\n---------\n\n";

        //
        $path = STORAGE_PATH .'logs' .DS .'errors.log';

        file_put_contents($path, $content, FILE_APPEND);
    }
}
