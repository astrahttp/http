<?php
declare(strict_types=1);

namespace Astra\Http;

use Amp\DeferredFuture;
use Amp\Cancellation;

final class ResponseBodyStream
{
    /** @var list<string> */
    private array $queue = [];
    private bool $closed = false;
    private ?\Throwable $error = null;
    private ?DeferredFuture $waiter = null;

    public function push(string $chunk): void
    {
        if ($this->closed) {
            return;
        }

        $this->queue[] = $chunk;
        $this->signal();
    }

    public function end(): void
    {
        $this->closed = true;
        $this->signal();
    }

    public function fail(\Throwable $e): void
    {
        $this->error = $e;
        $this->closed = true;
        $this->signal();
    }

    public function read(?Cancellation $cancellation = null): ?string
    {
        while (true) {
            if ($this->error) {
                throw $this->error;
            }

            if ($this->queue !== []) {
                return array_shift($this->queue);
            }

            if ($this->closed) {
                return null;
            }

            $this->waiter = new DeferredFuture();
            $future = $this->waiter->getFuture();

            if ($cancellation !== null) {
                $cancellation->throwIfRequested();
            }

            $future->await();
        }
    }

    public function close(): void
    {
        $this->closed = true;
        $this->signal();
    }

    private function signal(): void
    {
        if ($this->waiter) {
            $waiter = $this->waiter;
            $this->waiter = null;
            $waiter->complete(null);
        }
    }
}
