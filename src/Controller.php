<?php

declare(strict_types=1);

namespace Switch\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Switch\Http\Response;
use Switch\Http\Stream;
use Switch\Controller\Validation\Validator;
use RuntimeException;

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
     * Render a Switch View template (requires switch/view package).
     *
     * @param string $view View template name (e.g. 'home' or 'users.index')
     * @param array<string, mixed> $data Data passed to template
     * @throws RuntimeException if switch/view is not installed
     */
    protected function view(string $view, array $data = []): string
    {
        if (!class_exists(\Switch\View\View::class)) {
            throw new RuntimeException("The 'switch/view' package is required to render views. Install it via 'composer require switch/view'.");
        }

        return \Switch\View\View::render($view, $data);
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
     * Seamless client-side SPA redirect using Switch Live (falls back to standard redirect if switch/live not installed).
     */
    protected function liveRedirect(string $url): ResponseInterface
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            \Switch\Live\LiveResponse::redirect($url);
            return new Response(200, ['X-Switch-Live' => '1', 'X-Switch-Redirect' => $url], Stream::create(''));
        }

        return $this->redirect($url);
    }

    /**
     * Send a client-side Toast Notification via LiveResponse (optional dependency on switch/live).
     *
     * @param string $message Toast content
     * @param string $type Toast type: 'success', 'error', 'warning', 'info'
     * @return $this
     */
    protected function toast(string $message, string $type = 'info'): static
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            \Switch\Live\LiveResponse::toast($message, $type);
        }

        return $this;
    }

    /**
     * Dispatch a custom client-side JavaScript event via LiveResponse (optional dependency on switch/live).
     *
     * @param string $event Event name
     * @param array<string, mixed> $detail Event payload
     * @return $this
     */
    protected function emit(string $event, array $detail = []): static
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            \Switch\Live\LiveResponse::emit($event, $detail);
        }

        return $this;
    }

    /**
     * Dynamically change the document title on the client.
     *
     * @return $this
     */
    protected function title(string $title): static
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            \Switch\Live\LiveResponse::title($title);
        }

        return $this;
    }

    /**
     * Dynamically change the target container selector for the response.
     *
     * @return $this
     */
    protected function target(string $selector): static
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            \Switch\Live\LiveResponse::target($selector);
        }

        return $this;
    }

    /**
     * Tell the client to preserve scroll position.
     *
     * @return $this
     */
    protected function preserveScroll(bool $preserve = true): static
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            \Switch\Live\LiveResponse::preserveScroll($preserve);
        }

        return $this;
    }

    /**
     * Check if the current incoming request is an SPA Live request.
     */
    protected function isLive(): bool
    {
        if (class_exists(\Switch\Live\LiveResponse::class)) {
            return \Switch\Live\LiveResponse::isLiveRequest();
        }

        return isset($_SERVER['HTTP_X_SWITCH_LIVE']) && $_SERVER['HTTP_X_SWITCH_LIVE'] === '1';
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

    /**
     * Get or set session data, or retrieve the active SessionStore instance.
     */
    protected function session(string|array|null $key = null, mixed $default = null): mixed
    {
        if (function_exists('session')) {
            return session($key, $default);
        }

        if (class_exists(\Switch\Session\Session::class)) {
            if ($key === null) {
                return \Switch\Session\Session::getStore();
            }
            if (is_array($key)) {
                \Switch\Session\Session::put($key);
                return null;
            }
            return \Switch\Session\Session::get($key, $default);
        }

        throw new \RuntimeException('Session package (switch/session) is not installed.');
    }

    /**
     * Flash a message to the session, or get the FlashBag instance.
     *
     * @param string|null $type ('success', 'error', 'warning', 'info')
     * @param string|null $message
     * @param string|null $title
     * @param array<string, mixed> $options
     * @return mixed
     */
    protected function flash(?string $type = null, ?string $message = null, ?string $title = null, array $options = []): mixed
    {
        if (function_exists('flash')) {
            if ($type === null) {
                return flash();
            }
            if ($message === null) {
                // If single argument given like $this->flash('status', 'Hello') or $this->flash('Hello')
                return flash('info', $type, $title, $options);
            }
            flash($type, $message, $title, $options);
            return $this;
        }

        if (class_exists(\Switch\Session\Session::class)) {
            \Switch\Session\Session::flash($type ?? 'info', $message ?? true);
        }

        return $this;
    }
}
