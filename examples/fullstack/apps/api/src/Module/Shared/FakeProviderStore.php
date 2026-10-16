<?php

declare(strict_types=1);

namespace App\Module\Shared;

use PayKit\Bundle\Provider\Dto\CheckoutRequest;
use PayKit\Bundle\Provider\Dto\ProviderInvoice;
use PayKit\Bundle\Provider\Dto\ProviderSubscription;
use PayKit\Bundle\Testing\FakeProvider;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/** Local demo adapter only. The state file must stay outside the public directory. */
final class FakeProviderStore implements EventSubscriberInterface
{
    private ?FakeProvider $provider = null;
    /** @var resource|null */
    private $file = null;

    public function __construct(private readonly string $statePath)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => ['onResponse', -2048], ConsoleEvents::TERMINATE => 'save'];
    }

    public function get(): FakeProvider
    {
        if (null !== $this->provider) {
            return $this->provider;
        }
        $directory = dirname($this->statePath);
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Cannot create the demo state directory.');
        }
        $this->file = fopen($this->statePath, 'c+b');
        if (false === $this->file || !flock($this->file, LOCK_EX)) {
            throw new \RuntimeException('Cannot lock the fake provider state.');
        }
        chmod($this->statePath, 0600);
        $contents = stream_get_contents($this->file);
        if (false === $contents) {
            throw new \RuntimeException('Cannot read the fake provider state.');
        }
        if ('' !== $contents) {
            $provider = unserialize($contents, ['allowed_classes' => [
                FakeProvider::class, \DateTimeImmutable::class, CheckoutRequest::class,
                ProviderSubscription::class, ProviderInvoice::class,
            ]]);
            if (!$provider instanceof FakeProvider) {
                throw new \RuntimeException('Invalid demo state. Restore both the database and provider state together.');
            }
            return $this->provider = $provider;
        }

        return $this->provider = new FakeProvider(new \DateTimeImmutable('first day of this month midnight UTC'));
    }

    public function onResponse(ResponseEvent $event): void
    {
        if ($event->isMainRequest()) {
            $this->save();
        }
    }

    public function save(): void
    {
        if (null === $this->provider || !is_resource($this->file)) {
            return;
        }
        // Calls are request-local diagnostics; keep only the provider's billing state.
        $this->provider->calls = [];
        $contents = serialize($this->provider);
        rewind($this->file);
        if (!ftruncate($this->file, 0) || strlen($contents) !== fwrite($this->file, $contents) || !fflush($this->file)) {
            throw new \RuntimeException('Cannot persist the fake provider state.');
        }
        flock($this->file, LOCK_UN);
        fclose($this->file);
        $this->file = null;
    }
}
