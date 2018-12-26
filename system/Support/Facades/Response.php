<?php

namespace System\Support\Facades;

use System\Http\JsonResponse;
use System\Http\Response as HttpResponse;
use System\Support\Traits\MacroableTrait;
use System\Support\Str;

use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;


class Response
{
    use MacroableTrait;


    public static function make($content, $status = 200, array $headers = array())
    {
        return new HttpResponse($content, $status, $headers);
    }

    public static function json($data, $status = 200, $headers = array(), $jsonOptions = 0)
    {
        if ($data instanceof ArrayableInterface) {
            $data = $data->toArray();
        }

        return new JsonResponse($data, $status, $headers, $options);
    }

    public static function jsonp($callback, $data, $status = 200, array $headers = array())
    {
        return static::json($data, $status, $headers, $options)->setCallback($callback);
    }

    public static function stream($callback, $status = 200, array $headers = array())
    {
        return new StreamedResponse($callback, $status, $headers);
    }

    public static function download($file, $name = null, array $headers = array(), $disposition = 'attachment')
    {
        $response = new BinaryFileResponse($file, 200, $headers, true, $disposition);

        if (! is_null($name)) {
            return $response->setContentDisposition($disposition, $name, str_replace('%', '', Str::ascii($name)));
        }

        return $response;
    }
}
