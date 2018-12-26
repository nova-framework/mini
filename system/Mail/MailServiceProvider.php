<?php

namespace System\Mail;

use System\Mail\LogTransport;
use System\Support\ServiceProvider;

use Swift_Mailer;
use Swift_SmtpTransport as SmtpTransport;
use Swift_MailTransport as MailTransport;
use Swift_SendmailTransport as SendmailTransport;

use Swift_FileSpool as FileSpool;
use Swift_SpoolTransport as SpoolTransport;


class MailServiceProvider extends ServiceProvider
{
    /**
     * Indicates if loading of the provider is deferred.
     *
     * @var bool
     */
    protected $defer = true;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerSwiftMailers();

        $this->app->singleton('mailer', function ($app)
        {
            $mailer = new Mailer(
                $app['view'], $app['swift.mailer'], $app['swift.mailer.spool'], $app['events']
            );

            $mailer->setContainer($app);

            if ($app->bound('log')) {
                $mailer->setLogger($app['log']);
            }

            $from = $app['config']['mail.from'];

            if (is_array($from) && isset($from['address'])) {
                $mailer->alwaysFrom($from['address'], $from['name']);
            }

            $pretend = $app['config']->get('mail.pretend', false);

            $mailer->pretend($pretend);

            return $mailer;
        });
    }

    /**
     * Register the Swift Mailer instance.
     *
     * @return void
     */
    public function registerSwiftMailers()
    {
        $config = $this->app['config']['mail'];

        $this->registerSwiftTransport($config);
        $this->registerSpoolTransport($config);

        $this->app->singleton('swift.mailer', function ($app)
        {
            return new Swift_Mailer($app['swift.transport']);
        });

        $this->app->singleton('swift.mailer.spool', function ($app)
        {
            return new Swift_Mailer($app['swift.transport.spool']);
        });
    }

    /**
     * Register the Swift Transport instance.
     *
     * @param  array  $config
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function registerSwiftTransport($config)
    {
        switch ($config['driver'])
        {
            case 'smtp':
                return $this->registerSmtpTransport($config);

            case 'sendmail':
                return $this->registerSendmailTransport($config);

            case 'mail':
                return $this->registerMailTransport($config);

            case 'log':
                return $this->registerLogTransport($config);

            default:
                throw new \InvalidArgumentException('Invalid mail driver.');
        }
    }

    /**
     * Register the SMTP Swift Transport instance.
     *
     * @param  array  $config
     * @return void
     */
    protected function registerSmtpTransport($config)
    {
        $this->app->singleton('swift.transport', function ($app) use ($config)
        {
            extract($config);

            $transport = SmtpTransport::newInstance($host, $port);

            if (isset($encryption)) {
                $transport->setEncryption($encryption);
            }

            if (isset($username)) {
                $transport->setUsername($username);

                $transport->setPassword($password);
            }

            return $transport;
        });
    }

    /**
     * Register the Sendmail Swift Transport instance.
     *
     * @param  array  $config
     * @return void
     */
    protected function registerSendmailTransport($config)
    {
        $this->app->singleton('swift.transport', function ($app) use ($config)
        {
            return SendmailTransport::newInstance($config['sendmail']);
        });
    }

    /**
     * Register the Mail Swift Transport instance.
     *
     * @param  array  $config
     * @return void
     */
    protected function registerMailTransport($config)
    {
        $this->app->singleton('swift.transport', function ($app)
        {
            return MailTransport::newInstance();
        });
    }

    /**
     * Register the Spool Swift Transport instance.
     *
     * @param  array  $config
     * @return void
     */
    protected function registerSpoolTransport($config)
    {
        $config = array_get($config, 'spool', array(
            'files'        => storage_path('spool'),
            'messageLimit' => 10,
            'timeLimit'    => 100,
            'retryLimit'   => 10,
        ));

        $this->app->singleton('swift.transport.spool', function ($app) use ($config)
        {
            extract($config);

            // Create a new File Spool instance.
            $spool = new FileSpool($files);

            $spool->setMessageLimit($messageLimit);
            $spool->setTimeLimit($timeLimit);
            $spool->setRetryLimit($retryLimit);

            return SpoolTransport::newInstance($spool);
        });
    }

    /**
     * Register the "Log" Swift Transport instance.
     *
     * @param  array  $config
     * @return void
     */
    protected function registerLogTransport($config)
    {
        $this->app->singleton('swift.transport', function ($app)
        {
            $logger = $app->make('Psr\Log\LoggerInterface');

            return new LogTransport($logger);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return array(
            'mailer', 'swift.mailer', 'swift.mailer.spool', 'swift.transport', 'swift.transport.spool'
        );
    }

}
