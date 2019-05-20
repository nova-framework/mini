<?php

namespace App\Middleware;

use Mini\Foundation\Application;
use Mini\Http\Request;
use Mini\Http\Response;
use Mini\Support\Str;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

use Closure;
use Exception;


class HandleProfiling
{
    /**
     * The application implementation.
     *
     * @var \Mini\Foundation\Application
     */
    protected $app;


    /**
     * Create a new middleware instance.
     *
     * @param  \Mini\Foundation\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Handle the given request and get the response.
     *
     * @param  $request
     * @param  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request, $next);

        // Get the debug flags from configuration.
        $debug = $this->app['config']->get('app.debug', false);

        if ($debug && $this->canPatchContent($response)) {
            $content = str_replace('<!-- DO NOT DELETE! - Profiler -->',
                $this->getInfo($request),
                $response->getContent()
            );

            $response->setContent($content);
        }

        return $response;
    }

    protected function getInfo(Request $request)
    {
        $requestTime = $request->server('REQUEST_TIME_FLOAT');

        $elapsedTime = sprintf("%01.4f", (microtime(true) - $requestTime));

        $memoryUsage = static::formatSize(memory_get_usage());

        //
        $queries = $this->getQueries();

        return sprintf('Elapsed Time: <b>%s</b> sec | Memory Usage: <b>%s</b> | SQL: <b>%d</b> %s | UMAX: <b>%0d</b>',
            $elapsedTime, $memoryUsage, $queries, ($queries == 1) ? 'query' : 'queries', intval(25 / $elapsedTime)
        );
    }

    protected function canPatchContent(SymfonyResponse $response)
    {
        if ((! $response instanceof Response) && is_subclass_of($response, 'Symfony\Component\Http\Foundation\Response')) {
            return false;
        }

        $contentType = $response->headers->get('Content-Type');

        return Str::is('text/html*', $contentType);
    }

    protected function getQueries()
    {
        try {
            $connection = $this->app['db']->connection();

            $queries = $connection->getQueryLog();

            return count($queries);
        }
        catch (Exception $e) {
            return 0;
        }
    }

    protected static function formatSize($bytes, $decimals = 2)
    {
        $size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');

        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) .@$size[$factor];
    }
}
