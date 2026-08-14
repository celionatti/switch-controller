<?php

declare(strict_types=1);

namespace Switch\Controller\Tests;

use PHPUnit\Framework\TestCase;
use Switch\Controller\Controller;
use Switch\Controller\Validation\ValidationException;
use Switch\Controller\Validation\Validator;
use Switch\Http\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use PDO;

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
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        // Setup in-memory SQLite database for testing database validator rules
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->pdo->exec("CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, username TEXT)");
        $this->pdo->exec("INSERT INTO users (id, email, username) VALUES (1, 'john@example.com', 'john_doe')");
        $this->pdo->exec("INSERT INTO users (id, email, username) VALUES (2, 'jane@example.com', 'jane_doe')");

        Validator::setDatabaseResolver(fn() => $this->pdo);
    }

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
            'role' => 'admin',
            'terms' => 'yes',
            'score' => '85',
            'tag' => 'cool_tag-1',
            'code' => '1234',
            'published_at' => '2026-08-14'
        ];

        $rules = [
            'name' => 'required|alpha|min:2|max:50',
            'email' => 'required|email',
            'age' => 'required|integer|min:18',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|in:admin,user,editor',
            'terms' => 'accepted',
            'score' => 'between:1,100',
            'tag' => 'alpha_dash',
            'code' => 'digits:4',
            'published_at' => 'date'
        ];

        $validated = Validator::validate($data, $rules);
        $this->assertEquals('Alice', $validated['name']);
        $this->assertEquals('alice@example.com', $validated['email']);
    }

    public function testUniqueValidatorRuleFailsOnDuplicate(): void
    {
        $this->expectException(ValidationException::class);

        // 'john@example.com' already exists in the database
        Validator::validate(
            ['email' => 'john@example.com'],
            ['email' => 'required|unique:users,email']
        );
    }

    public function testUniqueValidatorRulePassesOnNewValue(): void
    {
        // 'bob@example.com' does not exist yet
        $validated = Validator::validate(
            ['email' => 'bob@example.com'],
            ['email' => 'required|unique:users,email']
        );

        $this->assertEquals('bob@example.com', $validated['email']);
    }

    public function testUniqueValidatorRuleIgnoresSpecifiedIdOnUpdate(): void
    {
        // 'john@example.com' exists with id=1, but we pass exceptId=1
        $validated = Validator::validate(
            ['email' => 'john@example.com'],
            ['email' => 'required|unique:users,email,1,id']
        );

        $this->assertEquals('john@example.com', $validated['email']);
    }

    public function testExistsValidatorRule(): void
    {
        // id 1 exists
        $val1 = Validator::validate(['user_id' => 1], ['user_id' => 'exists:users,id']);
        $this->assertEquals(1, $val1['user_id']);

        // id 999 does not exist
        $this->expectException(ValidationException::class);
        Validator::validate(['user_id' => 999], ['user_id' => 'exists:users,id']);
    }

    public function testNullableAndCustomClosureRules(): void
    {
        $validated = Validator::validate(
            ['bio' => '', 'coupon' => 'SUPER50'],
            [
                'bio' => 'nullable|min:10',
                'coupon' => [
                    'required',
                    fn($val) => $val === 'SUPER50' ? true : 'Invalid coupon code'
                ]
            ]
        );

        $this->assertNull($validated['bio']);
        $this->assertEquals('SUPER50', $validated['coupon']);
    }

    public function testCustomExtendedRule(): void
    {
        Validator::extend('even_number', function ($field, $value) {
            return is_numeric($value) && (int) $value % 2 === 0;
        });

        $validated = Validator::validate(['num' => 4], ['num' => 'even_number']);
        $this->assertEquals(4, $validated['num']);

        $this->expectException(ValidationException::class);
        Validator::validate(['num' => 5], ['num' => 'even_number']);
    }

    public function testDateComparisonRules(): void
    {
        $validated = Validator::validate(
            ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
            [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date'
            ]
        );

        $this->assertEquals('2026-12-31', $validated['end_date']);
    }

    public function testStringPrefixAndSuffixRules(): void
    {
        $validated = Validator::validate(
            ['site' => 'https://example.com', 'file' => 'document.pdf'],
            [
                'site' => 'starts_with:http://,https://',
                'file' => 'ends_with:.pdf,.docx'
            ]
        );

        $this->assertEquals('https://example.com', $validated['site']);
        $this->assertEquals('document.pdf', $validated['file']);
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
