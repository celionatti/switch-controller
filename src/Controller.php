<?php

declare(strict_types=1);

namespace Switch\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Http\Response;
use Switch\Http\Stream;
use Switch\View\View;
use Switch\Live\LiveResponse;
use Switch\Controller\Validation\Validator;

abstract class Controller
{
    /**
     * @var array<int, array{middleware: string|callable|object, only?: array<string>, except?: array<string>}>
     */
    protected array $middleware = [];

    /**
     * Register a middleware on this controller.
     *
     * @param string|callable|object $middleware
     * @param array{only?: array<string>, except?: array<string>} $options
     * @return $this
     */
    public function middleware(string|callable|object $middleware, array $options = []): static
    {
        $this->middleware[] = [
            'middleware' => $middleware,
            'only' => $options['only'] ?? null,
            'except' => $options['except'] ?? null,
        ];

        return $this;
    }

    /**
     * Get registered middleware definitions.
     *
     * @return array<int, array{middleware: string|callable|object, only?: array<string>, except?: array<string>}>
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Render a Switch View template.
     *
     * @param string $view View template name (e.g. 'home' or 'users.index')
     * @param array<string, mixed> $data Data passed to template
     */
    protected function view(string $view, array $data = []): string
    {
        return View::render($view, $data);
    }

    /**
     * Return a PSR-7 JSON HTTP Response.
     *
     * @param mixed $data Data to serialize as JSON
     * @param int $status HTTP status code (default 200)
     * @param array<string, string|array<string>> $headers Extra HTTP headers
     */
    protected function json(mixed $data, int $status = 200, array $headers = []): ResponseInterface
    {
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $headers['Content-Type'] = 'application/json';

        return new Response($status, $headers, Stream::create($payload));
    }

    /**
     * Return a PSR-7 HTTP Redirect Response.
     *
     * @param string $url Destination URL
     * @param int $status HTTP status code (default 302)
     * @param array<string, string|array<string>> $headers Extra HTTP headers
     */
    protected function redirect(string $url, int $status = 302, array $headers = []): ResponseInterface
    {
        $headers['Location'] = $url;
        return new Response($status, $headers, Stream::create(''));
    }

    /**
     * Seamless client-side SPA redirect using Switch Live.
     */
    protected function liveRedirect(string $url): ResponseInterface
    {
        LiveResponse::redirect($url);
        return new Response(200, ['X-Switch-Live' => '1', 'X-Switch-Redirect' => $url], Stream::create(''));
    }

    /**
     * Send a client-side Toast Notification via LiveResponse.
     *
     * @param string $message Toast content
     * @param string $type Toast type: 'success', 'error', 'warning', 'info'
     * @return $this
     */
    protected function toast(string $message, string $type = 'info'): static
    {
        LiveResponse::toast($message, $type);
        return $this;
    }

    /**
     * Dispatch a custom client-side JavaScript event via LiveResponse.
     *
     * @param string $event Event name
     * @param array<string, mixed> $detail Event payload
     * @return $this
     */
    protected function emit(string $event, array $detail = []): static
    {
        LiveResponse::emit($event, $detail);
        return $this;
    }

    /**
     * Dynamically change the document title on the client.
     *
     * @return $this
     */
    protected function title(string $title): static
    {
        LiveResponse::title($title);
        return $this;
    }

    /**
     * Dynamically change the target container selector for the response.
     *
     * @return $this
     */
    protected function target(string $selector): static
    {
        LiveResponse::target($selector);
        return $this;
    }

    /**
     * Tell the client to preserve scroll position.
     *
     * @return $this
     */
    protected function preserveScroll(bool $preserve = true): static
    {
        LiveResponse::preserveScroll($preserve);
        return $this;
    }

    /**
     * Check if the current incoming request is an SPA Live request.
     */
    protected function isLive(): bool
    {
        return LiveResponse::isLiveRequest();
    }

    /**
     * Validate incoming request parameters or array data.
     *
     * @param ServerRequestInterface|array<string, mixed> $requestOrData
     * @param array<string, string|array<int, string>> $rules
     * @param array<string, string> $messages
     * @return array<string, mixed> Validated data subset
     * @throws Validation\ValidationException
     */
    protected function validate(ServerRequestInterface|array $requestOrData, array $rules, array $messages = []): array
    {
        if ($requestOrData instanceof ServerRequestInterface) {
            $data = array_merge(
                $requestOrData->getQueryParams(),
                is_array($requestOrData->getParsedBody()) ? $requestOrData->getParsedBody() : []
            );
        } else {
            $data = $requestOrData;
        }

        return Validator::validate($data, $rules, $messages);
    }
}
