<?php

declare(strict_types=1);

namespace Switch\Controller\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Controller\Controller;
use Switch\Controller\Validation\ValidationException;
use Switch\Controller\Validation\Validator;
use Switch\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;

require_once __DIR__ . '/../src/helpers.php';

class TestableController extends Controller
{
    public function showView(): string
    {
        return $this->view('home', ['version' => '1.0.0']);
    }

    public function showJson(): ResponseInterface
    {
        return $this->json(['status' => 'success', 'code' => 200]);
    }

    public function showRedirect(): ResponseInterface
    {
        return $this->redirect('/login');
    }

    public function showLiveRedirect(): ResponseInterface
    {
        return $this->liveRedirect('/dashboard');
    }

    public function triggerLiveHelpers(): static
    {
        $this->toast('User created', 'success')
             ->emit('user.created', ['id' => 1])
             ->title('New Title')
             ->target('#app')
             ->preserveScroll(true);

        return $this;
    }

    public function validateData(array $data, array $rules): array
    {
        return $this->validate($data, $rules);
    }
}

class ControllerTest extends TestCase
{
    public function testJsonReturnsJsonResponse(): void
    {
        $controller = new TestableController();
        $response = $controller->showJson();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('{"status":"success","code":200}', (string) $response->getBody());
    }

    public function testRedirectReturnsRedirectResponse(): void
    {
        $controller = new TestableController();
        $response = $controller->showRedirect();

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/login', $response->getHeaderLine('Location'));
    }

    public function testLiveRedirectSetsHeaders(): void
    {
        $controller = new TestableController();
        $response = @$controller->showLiveRedirect();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('1', $response->getHeaderLine('X-Switch-Live'));
        $this->assertEquals('/dashboard', $response->getHeaderLine('X-Switch-Redirect'));
    }

    public function testMiddlewareRegistration(): void
    {
        $controller = new TestableController();
        $controller->middleware('AuthMiddleware', ['only' => ['showView']]);

        $registered = $controller->getMiddleware();
        $this->assertCount(1, $registered);
        $this->assertEquals('AuthMiddleware', $registered[0]['middleware']);
        $this->assertEquals(['showView'], $registered[0]['only']);
    }

    public function testValidatorSuccess(): void
    {
        $data = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => '25',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin'
        ];

        $rules = [
            'name' => 'required|min:2|max:50',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:admin,user,editor'
        ];

        $validated = Validator::validate($data, $rules);
        $this->assertEquals('Alice', $validated['name']);
        $this->assertEquals('alice@example.com', $validated['email']);
    }

    public function testValidatorFailureThrowsValidationException(): void
    {
        $this->expectException(ValidationException::class);

        $data = [
            'name' => 'A',
            'email' => 'not-an-email',
            'password' => '123'
        ];

        $rules = [
            'name' => 'required|min:3',
            'email' => 'required|email',
            'password' => 'required|confirmed'
        ];

        try {
            Validator::validate($data, $rules);
        } catch (ValidationException $e) {
            $errors = $e->getErrors();
            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('password', $errors);
            $this->assertNotNull($e->getFirstError());
            throw $e;
        }
    }

    public function testValidateWithServerRequest(): void
    {
        $request = (new ServerRequest('POST', '/register'))
            ->withParsedBody(['username' => 'john_doe', 'email' => 'john@test.com']);

        $controller = new TestableController();
        $validated = $controller->validateData($request->getParsedBody(), [
            'username' => 'required|min:3',
            'email' => 'required|email'
        ]);

        $this->assertEquals('john_doe', $validated['username']);
        $this->assertEquals('john@test.com', $validated['email']);
    }

    public function testGlobalHelpers(): void
    {
        $jsonRes = json(['msg' => 'hello']);
        $this->assertEquals(200, $jsonRes->getStatusCode());

        $redirRes = redirect('/home');
        $this->assertEquals(302, $redirRes->getStatusCode());
        $this->assertEquals('/home', $redirRes->getHeaderLine('Location'));

        $val = validate(['name' => 'Bob'], ['name' => 'required']);
        $this->assertEquals('Bob', $val['name']);
    }
}
