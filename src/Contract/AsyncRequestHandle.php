<?php
declare(strict_types=1);

namespace Astra\Http\Contract;

use Amp\Future;
use Astra\Http\Client;
use Astra\Http\Response;

final class AsyncRequestHandle
{
    public function __construct(
        private Future $future,
        private string $id,
        private Client $client
    ) {}

    public function await(): Response
    {
        return $this->client->awaitHandle($this);
    }

    public function future(): Future
    {
        return $this->future;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
