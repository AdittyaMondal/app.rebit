<?php

namespace Tests\Feature;

use App\Http\Middleware\LogRequestMiddleware;
use Illuminate\Http\Request;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * LOG_REQUESTS=true is the switch someone flips to debug the native client.
 * Before redaction, that wrote bearer tokens, the session cookie and posted
 * passwords to storage/logs in plaintext.
 */
class LogRequestRedactionTest extends TestCase
{
    private array $logged = [];

    private function runMiddleware(Request $request): void
    {
        config(['logging.log_requests' => true]);

        $capture = new class($this->logged) implements LoggerInterface {
            use \Psr\Log\LoggerTrait;
            public function __construct(public array &$sink) {}
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->sink = $context;
            }
        };

        $manager = \Mockery::mock(LogManager::class);
        $manager->shouldReceive('channel')->with('requests')->andReturn($capture);
        Log::swap($manager);

        (new LogRequestMiddleware())->handle($request, fn () => response('ok'));

        $this->logged = $capture->sink;
    }

    public function test_authorization_header_and_cookies_are_not_written(): void
    {
        $request = Request::create('/api/anything', 'POST', [], ['rebit_session' => 'session-value'], [], [
            'HTTP_AUTHORIZATION' => 'Bearer super-secret-token',
        ]);

        $this->runMiddleware($request);

        $flat = json_encode($this->logged);

        $this->assertStringNotContainsString('super-secret-token', $flat);
        $this->assertStringNotContainsString('session-value', $flat);
        $this->assertStringContainsString('[redacted]', $flat);
    }

    public function test_passwords_are_redacted_including_nested_ones(): void
    {
        $request = Request::create('/api/anything', 'POST', [
            'email' => 'someone@example.test',
            'password' => 'top-secret',
            'account' => ['current_password' => 'also-secret'],
        ]);

        $this->runMiddleware($request);

        $flat = json_encode($this->logged['input']);

        $this->assertStringNotContainsString('top-secret', $flat);
        $this->assertStringNotContainsString('also-secret', $flat);
        // Non-secret fields must survive, or the log stops being useful.
        $this->assertStringContainsString('someone@example.test', $flat);
    }

    public function test_session_is_never_included(): void
    {
        $this->runMiddleware(Request::create('/api/anything', 'GET'));

        $this->assertArrayNotHasKey('session', $this->logged);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
