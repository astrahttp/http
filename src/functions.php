<?php
declare(strict_types=1);

namespace Astra\Http;

function initAstraHTTP(array $config = []): Client
{
    return new Client($config);
}
