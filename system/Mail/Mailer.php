<?php

namespace System\Mail;

use System\Log\Writer;
use System\View\Factory;
use System\Events\Dispatcher;
use System\Container\Container;

use Swift_Mailer;
use Swift_Message;

use Closure;
use InvalidArgumentException;


class Mailer
{
    /**
     * The view factory instance.
     *
     * @var \System\View\Factory
     */
    protected $views;

    /**
     * The Swift Mailer instance.
     *
     * @var \Swift_Mailer
     */
    protected $swift;

    /**
     * The Swift Spool Mailer instance.
     *
     * @var \Swift_Mailer
     */
    protected $swiftSpool;

    /**
     * The event dispatcher instance.
     *
     * @var \System\Events\Dispatcher
     */
    protected $events;

    /**
     * The global from address and name.
     *
     * @var array
     */
    protected $from;

    /**
     * The log writer instance.
     *
     * @var \System\Log\Writer
     */
    protected $logger;

    /**
     * The IoC container instance.
     *
     * @var \System\Container\Container
     */
    protected $container;

    /**
     * Indicates if the actual sending is disabled.
     *
     * @var bool
     */
    protected $pretending = false;

    /**
     * Array of failed recipients.
     *
     * @var array
     */
    protected $failedRecipients = array();


    /**
     * Create a new Mailer instance.
     *
     * @param  \System\View\Factory  $views
     * @param  \Swift_Mailer  $swift
     * @param  \Swift_Mailer  $swiftSpool
     * @param  \System\Events\Dispatcher  $events
     * @return void
     */
    public function __construct(Factory $views, Swift_Mailer $swift, Swift_Mailer $swiftSpool, Dispatcher $events = null)
    {
        $this->views = $views;
        $this->swift = $swift;

        $this->swiftSpool = $swiftSpool;

        $this->events = $events;
    }

    /**
     * Set the global from address and name.
     *
     * @param  string  $address
     * @param  string  $name
     * @return void
     */
    public function alwaysFrom($address, $name = null)
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Send a new message when only a plain part.
     *
     * @param  string  $view
     * @param  array   $data
     * @param  mixed   $callback
     * @return int
     */
    public function plain($view, array $data, $callback)
    {
        return $this->send(array('text' => $view), $data, $callback);
    }

    /**
     * Send a new message using a view.
     *
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @return void
     */
    public function send($view, array $data, $callback)
    {
        $this->sendMessage($view, $data, $callback, $this->swift);
    }

    /**
     * Queue a new e-mail message for sending.
     *
     * @param  string|array  $view
     * @param  array   $data
     * @param  \Closure|string  $callback
     * @return void
     */
    public function queue($view, array $data, $callback)
    {
        $this->sendMessage($view, $data, $callback, $this->swiftSpool);
    }

    /**
     * Create and send a new message using a view.
     *
     * @param  string|array  $view
     * @param  array  $data
     * @param  \Closure|string  $callback
     * @param  \Swift_Mailer  $mailer
     * @return void
     */
    protected function sendMessage($view, array $data, $callback, $mailer)
    {
        list($view, $plain) = $this->parseView($view);

        // Create a new message.
        $data['message'] = $message = $this->createMessage();

        $this->callMessageBuilder($callback, $message);

        //
        $this->addContent($message, $view, $plain, $data);

        $this->sendSwiftMessage($message->getSwiftMessage(), $mailer);
    }

    /**
     * Send a Swift Message instance.
     *
     * @param  \Swift_Message  $message
     * @param  \Swift_Mailer  $mailer
     * @return void
     */
    protected function sendSwiftMessage($message, $mailer)
    {
        if ($this->events) {
            $this->events->fire('mailer.sending', array($message));
        }

        if (! $this->pretending) {
            $mailer->send($message, $this->failedRecipients);
        } else if (isset($this->logger)) {
            $this->logMessage($message);
        }
    }

    /**
     * Add the content to a given message.
     *
     * @param  \System\Mail\Message  $message
     * @param  string  $view
     * @param  string  $plain
     * @param  array   $data
     * @return void
     */
    protected function addContent($message, $view, $plain, $data)
    {
        if (isset($view)) {
            $message->setBody($this->getView($view, $data), 'text/html');
        }

        if (isset($plain)) {
            $message->addPart($this->getView($plain, $data), 'text/plain');
        }
    }

    /**
     * Parse the given view name or array.
     *
     * @param  string|array  $view
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseView($view)
    {
        if (is_string($view)) {
            return array($view, null);
        } else if (! is_array($view)) {
            throw new InvalidArgumentException("Invalid view.");
        } else if (isset($view[0])) {
            return $view;
        }

        return array(
            array_get($view, 'html'),
            array_get($view, 'text')
        );
    }

    /**
     * Log that a message was sent.
     *
     * @param  \Swift_Message  $message
     * @return void
     */
    protected function logMessage($message)
    {
        $emails = implode(', ', array_keys((array) $message->getTo()));

        $this->logger->info("Pretending to mail message to: {$emails}");
    }

    /**
     * Call the provided message builder.
     *
     * @param  \Closure|string  $callback
     * @param  \System\Mail\Message  $message
     * @return mixed
     *
     * @throws \InvalidArgumentException
     */
    protected function callMessageBuilder($callback, $message)
    {
        if ($callback instanceof Closure) {
            return call_user_func($callback, $message);
        } else if (is_string($callback)) {
            $instance = $this->container->make($callback);

            return $instance->mail($message);
        }

        throw new InvalidArgumentException("Callback is not valid.");
    }

    /**
     * Create a new message instance.
     *
     * @return \Mail\Message
     */
    protected function createMessage()
    {
        $swiftMessage = new Swift_Message;

        $message = new Message($swiftMessage);

        if (isset($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        return $message;
    }

    /**
     * Render the given view.
     *
     * @param  string  $view
     * @param  array   $data
     * @return \System\View\View
     */
    protected function getView($view, $data)
    {
        return $this->views->make($view, $data)->render();
    }

    /**
     * Tell the mailer to not really send messages.
     *
     * @param  bool  $value
     * @return void
     */
    public function pretend($value = true)
    {
        $this->pretending = $value;
    }

    /**
     * Check if the mailer is pretending to send messages.
     *
     * @return bool
     */
    public function isPretending()
    {
        return $this->pretending;
    }

    /**
     * Get the view factory instance.
     *
     * @return \System\View\Factory
     */
    public function getFactory()
    {
        return $this->views;
    }

    /**
     * Get the Swift Mailer instance.
     *
     * @return \Swift_Mailer
     */
    public function getSwiftMailer()
    {
        return $this->swift;
    }

    /**
     * Get the array of failed recipients.
     *
     * @return array
     */
    public function failures()
    {
        return $this->failedRecipients;
    }

    /**
     * Set the Swift Mailer instance.
     *
     * @param  \Swift_Mailer  $swift
     * @return void
     */
    public function setSwiftMailer($swift)
    {
        $this->swift = $swift;
    }

    /**
     * Set the log writer instance.
     *
     * @param  \Log\Writer  $logger
     * @return $this
     */
    public function setLogger(Writer $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \System\Container\Container  $container
     * @return void
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

}
