<?php

namespace System\Support\Contracts;


interface MessageProviderInterface
{
    /**
     * Get the messages for the instance.
     *
     * @return \System\Support\MessageBag
     */
    public function getMessageBag();
}
