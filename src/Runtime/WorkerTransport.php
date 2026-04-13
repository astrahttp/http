<?php
declare(strict_types=1);

namespace Astra\Http\Runtime;

use function Amp\async;
use function Amp\Websocket\Client\connect;
use Astra\Http\Exception\WorkerException;
use Revolt\EventLoop;
use Throwable;

final class WorkerTransport
{
    private mixed $ws = null;
    private bool $closed = false;
    private bool $connecting = false;
    private bool $isConnected = false;
    private bool $reconnectScheduled = false;
    private int $reconnectDelayMs = 250;
    private mixed $onMessage = null;
    private mixed $onConnect = null;
    private mixed $onDisconnect = null;

    public function __construct(private WorkerRuntime $runtime)
    {
        $this->connect();
    }

    public function onMessage(callable $handler): void { $this->onMessage = $handler; }
    public function onConnect(callable $handler): void { $this->onConnect = $handler; }
    public function onDisconnect(callable $handler): void { $this->onDisconnect = $handler; }

    public function isConnected(): bool
    {
        return $this->isConnected && $this->ws !== null;
    }

    public function send(string $payload): void
    {
        if (!$this->isConnected() || !$this->ws) {
            throw new WorkerException('Transport is not connected yet.');
        }

        $this->ws->sendText($payload);
    }

    private function emitConnect(): void
    {
        if (is_callable($this->onConnect)) {
            ($this->onConnect)();
        }
    }

    private function emitDisconnect(string $reason): void
    {
        if (is_callable($this->onDisconnect)) {
            ($this->onDisconnect)($reason);
        }
    }

    private function connect(): void
    {
        if ($this->closed || $this->connecting) {
            return;
        }

        $this->connecting = true;
        $wsUrl = "ws://127.0.0.1:{$this->runtime->port}";

        async(function () use ($wsUrl): void {
            try {
                $conn = connect($wsUrl);

                if ($this->closed) {
                    try {
                        $conn->close();
                    } catch (Throwable) {}
                    return;
                }

                $this->ws = $conn;
                $this->isConnected = true;
                $this->connecting = false;
                $this->reconnectScheduled = false;
                $this->reconnectDelayMs = 250;
                $this->emitConnect();

                try {
                    while ($message = $conn->receive()) {
                        $payload = $message->buffer();
                        if (is_callable($this->onMessage)) {
                            ($this->onMessage)($payload);
                        }
                    }
                    $this->handleDown('WebSocket closed: normal closure');
                } catch (Throwable $e) {
                    $this->handleDown('WebSocket error: ' . $e->getMessage());
                }
            } catch (Throwable $e) {
                $this->connecting = false;
                $this->handleDown('Could not connect to worker: ' . $e->getMessage());
            }
        });
    }

    private function handleDown(string $reason): void
    {
        if ($this->closed) {
            return;
        }

        $wasConnected = $this->isConnected || $this->ws !== null || $this->connecting;
        $this->isConnected = false;
        $this->ws = null;
        $this->connecting = false;

        if ($wasConnected) {
            $this->emitDisconnect($reason);
        }

        try {
            $this->runtime->spawnOrAttach();
        } catch (Throwable) {}

        $this->scheduleReconnect();
    }

    private function scheduleReconnect(): void
    {
        if ($this->closed || $this->reconnectScheduled) {
            return;
        }

        $this->reconnectScheduled = true;
        $delay = max(0.05, min(5.0, $this->reconnectDelayMs / 1000.0));

        EventLoop::delay($delay, function (): void {
            $this->reconnectScheduled = false;
            $this->reconnectDelayMs = min(5000, (int)($this->reconnectDelayMs * 1.7) + 50);
            $this->connect();
        });
    }

    public function close(): void
    {
        $this->closed = true;
        if ($this->ws) {
            try {
                $this->ws->close();
            } catch (Throwable) {}
        }
        $this->ws = null;
        $this->isConnected = false;
        $this->connecting = false;
    }
}
