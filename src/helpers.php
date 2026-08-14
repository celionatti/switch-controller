<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Http\Response;
use Switch\Http\Stream;
use Switch\View\View;
use Switch\Controller\Validation\Validator;

if (!function_exists('json')) {
    /**
     * Create a JSON HTTP response.
     *
     * @param mixed $data
     * @param int $status
     * @param array<string, string|array<string>> $headers
     */
    function json(mixed $data, int $status = 200, array $headers = []): ResponseInterface
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headers['Content-Type'] = 'application/json';

        return new Response($status, $headers, Stream::create($payload));
    }
}

if (!function_exists('redirect')) {
    /**
     * Create a redirect HTTP response.
     *
     * @param string $url
     * @param int $status
     * @param array<string, string|array<string>> $headers
     */
    function redirect(string $url, int $status = 302, array $headers = []): ResponseInterface
    {
        $headers['Location'] = $url;
        return new Response($status, $headers, Stream::create(''));
    }
}

if (!function_exists('validate')) {
    /**
     * Validate an array of data against rules.
     *
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed>
     */
    function validate(array $data, array $rules, array $messages = []): array
    {
        return Validator::validate($data, $rules, $messages);
    }
}
