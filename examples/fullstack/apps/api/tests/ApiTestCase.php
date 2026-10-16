<?php

declare(strict_types=1);

namespace App\Tests;

use App\Kernel;
use Doctrine\ORM\Tools\SchemaTool;
use PayKit\Bundle\PayKit;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/** Boots a fresh kernel per request, on a throwaway SQLite database and fake provider state. */
abstract class ApiTestCase extends TestCase
{
    private ?Kernel $kernel = null;
    private string $directory;
    private array $previousEnvironment = [];

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/paykit-example-'.bin2hex(random_bytes(8));
        mkdir($this->directory, 0700);
        foreach ([
            'DATABASE_URL' => 'sqlite:///'.$this->directory.'/test.db',
            'DEMO_STATE_FILE' => $this->directory.'/fake-provider.bin',
        ] as $key => $value) {
            $this->previousEnvironment[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)];
            $_ENV[$key] = $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
        $this->kernel = new Kernel('test', true);
        $this->kernel->boot();
        $em = $this->kernel->getContainer()->get('doctrine')->getManager();
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        $this->kernel->getContainer()->get(PayKit::class)->push();
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
        foreach ($this->previousEnvironment as $key => [$env, $server, $process]) {
            if (null === $env) { unset($_ENV[$key]); } else { $_ENV[$key] = $env; }
            if (null === $server) { unset($_SERVER[$key]); } else { $_SERVER[$key] = $server; }
            false === $process ? putenv($key) : putenv($key.'='.$process);
        }
    }

    protected function bootedContainer(): \Symfony\Component\DependencyInjection\ContainerInterface
    {
        $this->kernel ??= new Kernel('test', true);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }

    /** @return array{int, array<string, mixed>} */
    protected function call(string $path, ?array $body = null, array $headers = []): array
    {
        $this->kernel ??= new Kernel('test', true);
        $request = Request::create($path, null === $body ? 'GET' : 'POST', server: [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            ...$headers,
        ], content: null === $body ? null : json_encode($body, JSON_THROW_ON_ERROR));
        $response = $this->kernel->handle($request);
        $this->kernel->terminate($request, $response);
        $this->kernel->shutdown();
        $this->kernel = null;
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        return [$response->getStatusCode(), json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)];
    }
}
