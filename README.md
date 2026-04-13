# AstraHTTP PHP

AstraHTTP PHP is a production-oriented wrapper around a Go-based transport engine. It is designed for high-concurrency HTTP traffic with shared worker management, TLS fingerprint customization, streaming response handling, multipart form encoding, retry support, and WebSocket / SSE protocol hooks.

This document explains the package in detail: installation, architecture, API surface, request options, response methods, streaming, retries, WebSocket / SSE usage, multipart uploads, and practical examples.


## Features

- **High Performance**
  Built-in goroutine pool efficiently handles large numbers of asynchronous requests with minimal overhead.

- **Custom Header Ordering (fhttp)**
  Full control over HTTP header order to accurately mimic real browsers and bypass strict fingerprinting systems.

- **Proxy Support**
  Supports multiple proxy protocols:
  - SOCKS4
  - SOCKS5
  - SOCKS5h (DNS over proxy)

- **JA3 Fingerprint Configuration**
  Customize TLS fingerprints (JA3) to emulate specific clients such as browsers or mobile apps.

- **HTTP/3 & QUIC Support**
  Native support for modern transport protocols for improved performance and lower latency.

- **WebSocket Client**
  Built-in WebSocket client for real-time, bidirectional communication.

- **Server-Sent Events (SSE)**
  Native support for consuming streaming HTTP events.

- **Connection Reuse**
  Persistent connections (keep-alive) to reduce latency and improve throughput.

- **JA4 Fingerprinting**
  Advanced TLS fingerprinting beyond JA3 for more precise client emulation.


---

## 1. Requirements

- PHP 8.2 or newer
- Composer
- One of the supported platforms:
  - Android (arm64 / aarch64)
  - Ubuntu and other Linux distributions (amd64 / x86_64, arm, arm64 / aarch64)
  - FreeBSD (amd64 / x86_64)
  - macOS (amd64 / x86_64, arm64)
  - Windows (x86 / 386, amd64 / x86_64)

> **Note:** `amd64` and `x86_64` refer to the same architecture, and `arm64` is also known as `aarch64`.
---

## 2. Installation

### Using Composer in a project (Recommended)

```bash
composer require astrahttp/http
php vendor/bin/astrahttp install
```

### Installing from source 
- This method installs a **development version (unstable)** and is not recommended for production.

```bash
composer config repositories.astrahttp vcs https://github.com/astrahttp/http.git
composer require astrahttp/http:1.x-dev
php vendor/bin/astrahttp install
```

### Autoloading

The library is PSR-4 namespaced and autoloaded through Composer:

```php
require __DIR__ . '/vendor/autoload.php';
```

The main entry points are:

- `Astra\Http\Client`
- `Astra\Http\Contract\RequestOptions`
- `Astra\Http\initAstraHTTP()`

---


---
## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Astra\Http\Client;

$client = new Client();

$req = $client->get('https://httpbin.org/get');

// do anything 

$response = $req->await(); // Real asynchronous 

echo "Status: ". $response->status . PHP_EOL;
echo "Body: ". $response->body;

// You can do $response->text();
// You can do $response->json();

$client->close(); //Terminate client
```

## With RequestOptions

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Astra\Http\Client;
use Astra\Http\Contract\RequestOptions;

$client = new Client(['port' => 9119]);

try {
    $handle = $client->get('https://httpbin.org/get', new RequestOptions([
        'responseType' => 'json',
    ]));

    $response = $handle->await();
    echo $response->text();
} finally {
    $client->close();
}
```

## RequestOptions

> **Note:** When using `requestAsync($options)`, the `method` option is used.
> When using shortcut methods such as `get()`, `post()`, `put()`, etc., the HTTP method is determined by the method name itself, and any provided `method` value is ignored.

| Option               | Type                      | Description                                                                               | Example                               |
| -------------------- | ------------------------- | ----------------------------------------------------------------------------------------- | ------------------------------------- |
| `headers`            | `?array<string, string>`  | Custom headers to send with the request.                                                  | `['Authorization' => 'Bearer token']` |
| `cookies`            | `array \| object \| null` | Cookies as array or object. They are normalized internally before transport.              | `['session' => 'abc123']`             |
| `body`               | `mixed`                   | Request body. Supports string, array (JSON/multipart), `Stringable`, or `ReadableStream`. | `['key' => 'value']`                  |
| `responseType`       | `?string`                 | Response format: `json`, `text`, `arraybuffer`, `blob`, `stream`.                         | `'json'`                              |
| `ja3`                | `?string`                 | JA3 TLS fingerprint.                                                                      | `'771,4865-4867,...'`                 |
| `ja4r`               | `?string`                 | Raw JA4R fingerprint.                                                                     | `'t13d1516h2_002f,...'`               |
| `http2Fingerprint`   | `?string`                 | Custom HTTP/2 fingerprint.                                                                | `'1:65536;4:131072;...'`              |
| `quicFingerprint`    | `?string`                 | QUIC fingerprint for HTTP/3.                                                              | `'16030106f2...'`                     |
| `disableGrease`      | `?bool`                   | Disables GREASE values in TLS handshake.                                                  | `true`                                |
| `userAgent`          | `?string`                 | User-Agent header value.                                                                  | `'Mozilla/5.0 ...'`                   |
| `serverName`         | `?string`                 | TLS SNI (Server Name Indication).                                                         | `'example.com'`                       |
| `proxy`              | `?string`                 | Proxy URL. Supports `http`, `socks4`, `socks5`, and `socks5h`.                            | `'http://user:pass@host:443'`         |
| `timeout`            | `?int`                    | Timeout in seconds before the request fails.                                              | `5`                                   |
| `disableRedirect`    | `?bool`                   | If `true`, redirects will not be followed.                                                | `true`                                |
| `headerOrder`        | `?array<int, string>`     | Custom header order.                                                                      | `['host', 'connection']`              |
| `orderAsProvided`    | `?bool`                   | Sends headers exactly as provided without reordering.                                     | `true`                                |
| `insecureSkipVerify` | `?bool`                   | Skips TLS certificate verification.                                                       | `false`                               |
| `forceHTTP1`         | `?bool`                   | Forces HTTP/1.1.                                                                          | `false`                               |
| `forceHTTP3`         | `?bool`                   | Forces HTTP/3.                                                                            | `false`                               |
| `protocol`           | `?string`                 | Protocol override: `http1`, `http2`, `http3`, `websocket`, `sse`.                         | `'http2'`                             |
| `maxRetries`         | `?int`                    | Maximum retry attempts. Default: `2`.                                                     | `3`                                   |
| `retryDelayMs`       | `?int`                    | Delay between retries in milliseconds. Default: `250`.                                    | `500`                                 |
| `retryable`          | `?bool`                   | Whether the request is retryable.                                                         | `true`                                |
| `onHeaders`          | `mixed`                   | Callback triggered when headers are received.                                             | `fn($headers) => null`                |
| `onChunk`            | `mixed`                   | Callback triggered on each response chunk.                                                | `fn($chunk) => null`                  |
| `onComplete`         | `mixed`                   | Callback triggered when request completes.                                                | `fn($res) => null`                    |
| `onError`            | `mixed`                   | Callback triggered on error.                                                              | `fn($e) => null`                      |

### Example with all options

```php
$options = [
    'headers' => [
        'Authorization' => 'Bearer token',
        'Accept' => 'application/json',
    ],
    'cookies' => [
        'session' => 'abc123',
    ],
    'body' => [
        'key' => 'value',
    ],
    'responseType' => 'json',
    'ja3' => '771,4865-4867,4866-49195,49199-52393-52392-49196-49200-49162-49161-49171-49172-51-57-47-53-10,0-23-65281-10-11-35-16-5-51-43-13-45-28-21,29-23-24-25-256-257,0',
    'ja4r' => 't13d1516h2_002f,0035,009c,009d,1301,1302,1303,c013,c014,c02b,c02c,c02f,c030,cca8,cca9_0000,0005,000a,000b,000d,0012,0017,001b,0023,002b,002d,0033,44cd,fe0d,ff01_0403,0804,0401,0503,0805,0501,0806,0601',
    'http2Fingerprint' => '1:65536;4:131072;5:16384|12517377|3:0:0:201,5:0:0:101,7:0:0:1,9:0:7:1,11:0:3:1,13:0:0:241|m,p,a,s',
    'quicFingerprint' => '16030106f2010006ee03039a2b98d81139db0e128ea09eff...',
    'disableGrease' => false,
    'userAgent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:87.0) Gecko/20100101 Firefox/87.0',
    'serverName' => 'example.com',
    'proxy' => 'http://username:password@hostname.com:443',
    'timeout' => 5,
    'disableRedirect' => true,
    'headerOrder' => ['cache-control', 'connection', 'host'],
    'orderAsProvided' => true,
    'insecureSkipVerify' => false,
    'forceHTTP1' => false,
    'forceHTTP3' => false,
    'protocol' => 'http2',
    'maxRetries' => 3,
    'retryDelayMs' => 500,
    'retryable' => true,
    'onHeaders' => fn ($headers) => null,
    'onChunk' => fn ($chunk) => print($chunk),
    'onComplete' => fn ($res) => null,
    'onError' => fn ($e) => null,
];
```

---

##  Body Types

`body` can be provided in several forms. The library normalizes it automatically and may set `Content-Type` depending on the value.

| Body type                   | Behavior                                                     | Content-Type handling                                                                     | Example                                                                                       |
| --------------------------- | ------------------------------------------------------------ | ----------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| `string`                    | Sent as-is.                                                  | Not changed automatically. Set it manually when needed.                                   | `'hello world'`                                                                               |
| `string` (URL-encoded form) | Sent as-is for `application/x-www-form-urlencoded` payloads. | Set `Content-Type: application/x-www-form-urlencoded; charset=UTF-8` manually.            | `'url=https://www.github.com/'`                                              |
| `array` (JSON)              | Encoded automatically as JSON.                               | If `Content-Type` is not already set, the library adds `application/json; charset=utf-8`. | `['name' => 'Ali', 'age' => 30]`                                                              |
| `array` (multipart form)    | Encoded automatically as multipart/form-data.                | Multipart headers are generated automatically and merged into the request headers.        | `['_multipart' => true, ['name' => 'file', 'path' => '/tmp/a.txt']]`                          |
| `Stringable`                | Converted to string and sent as-is.                          | Not changed automatically.                                                                | `new class implements Stringable { public function __toString(): string { return 'data'; } }` |
| `ReadableStream`            | Sent as a stream without conversion.                         | Not changed automatically.                                                                | `$stream`                                                                                     |
| `null`                      | No body is sent.                                             | Not applicable.                                                                           | `null`                                                                                        |

### String body

Use this when you already have the exact payload you want to send.

```php
$options = [
    'headers' => [
        'Content-Type' => 'text/plain; charset=utf-8',
    ],
    'body' => 'hello world',
];
```

### URL-encoded form body

Use this when you want to send a raw `application/x-www-form-urlencoded` payload.

```php
$options = [
    'headers' => [
        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
    ],
    'body' => 'url=https://www.github.com.com/',
];
```

In this case, the body is sent as a plain string, and the library does not JSON-encode it.
The `Content-Type` header must be set manually.

### Array as JSON

When `body` is an array, it is encoded to JSON automatically.

```php
$options = [
    'body' => [
        'name' => 'Ali',
        'age' => 30,
    ],
];
```

If `Content-Type` is not already set, the library adds:

```http
Content-Type: application/json; charset=utf-8
```

### Multipart array

If the array looks like multipart data, it is encoded as multipart automatically.

```php
$options = [
    'body' => [
        '_multipart' => true,
        [
            'name' => 'file',
            'path' => '/tmp/avatar.png',
            'mime' => 'image/png',
        ],
        [
            'name' => 'title',
            'content' => 'My upload',
        ],
    ],
];
```

Multipart headers are generated automatically and merged into the request headers.

### Stringable

Any object implementing `Stringable` is converted to string.

```php
$body = new class implements Stringable {
    public function __toString(): string
    {
        return 'payload from object';
    }
};

$options = [
    'body' => $body,
];
```

### ReadableStream

Use a stream for streamed or large content.

```php
$options = [
    'body' => $stream,
];
```

### Null

No body is sent.

```php
$options = [
    'body' => null,
];
```

### Content-Type behavior

* `string`, `Stringable`, and `ReadableStream` are sent as provided.
* `array` is encoded as JSON by default.
* Multipart arrays generate multipart headers automatically.
* If `Content-Type` is already present, it is preserved.
* `Accept` is separate from `Content-Type`:

  * `Content-Type` describes the request body.
  * `Accept` describes the response format you want.

---

## 3. Package overview

The library is structured around four layers:

### 3.1 Client layer

`Astra\Http\Client` is the public API used by application code. It exposes HTTP methods such as:

- `get()`
- `post()`
- `put()`
- `patch()`
- `delete()`
- `head()`
- `options()`
- `trace()`
- `websocket()` / `ws()`
- `sse()` / `eventSource()`

Each method returns an `AsyncRequestHandle`, which can be awaited using `->await()`.

### 3.2 Request options layer

`Astra\Http\Contract\RequestOptions` is the configuration object for a request. It contains:

- headers
- cookies
- body
- response type
- TLS fingerprint fields
- connection settings
- protocol settings
- retry policy
- local lifecycle hooks

### 3.3 Runtime layer

The runtime layer manages the external Go worker process and the WebSocket transport between PHP and the worker.

It includes:

- `Astra\Http\Runtime\WorkerRuntime`
- `Astra\Http\Runtime\WorkerManager`
- `Astra\Http\Runtime\WorkerTransport`

This is what gives the library its shared-process behavior and failover behavior.

### 3.4 Response layer

Responses are represented by `Astra\Http\Response` and may be consumed as:

- raw text
- decoded JSON
- binary string
- stream

---

## 4. Creating a client

### Basic client creation

```php
use Astra\Http\Client;

$client = new Client([
    'port' => 9119,
    'debug' => false,
]);
```

### Configuration options for the client constructor

The client constructor accepts an array with the following keys:

- `port` — the worker port to use
- `debug` — enables worker debugging output
- `workerPath` — explicit path to the Go worker binary

### Example

```php
$client = new Client([
    'port' => 9119,
    'debug' => true,
    'workerPath' => __DIR__ . '/bin/index',
]);
```

### Closing the client

Always close the client when you are finished:

```php
$client->close();
```

This releases the shared worker reference and helps the runtime shut down cleanly.

---

## 5. Main request workflow

The standard flow is:

1. Create a `Client`
2. Build `RequestOptions`
3. Call a method such as `get()` or `post()`
4. Receive an `AsyncRequestHandle`
5. Call `->await()` to get a `Response`

### Example

```php
use Astra\Http\Client;
use Astra\Http\Contract\RequestOptions;

$client = new Client(['port' => 9119]);

try {
    $handle = $client->get('https://httpbin.org/get', new RequestOptions([
        'responseType' => 'json',
    ]));

    $response = $handle->await();
    echo $response->text();
} finally {
    $client->close();
}
```

---

## 6. Request methods

The client exposes the following request methods.

### 6.1 HTTP methods

- `get(string $url, ?RequestOptions $options = null)`
- `post(string $url, ?RequestOptions $options = null)`
- `put(string $url, ?RequestOptions $options = null)`
- `patch(string $url, ?RequestOptions $options = null)`
- `delete(string $url, ?RequestOptions $options = null)`
- `head(string $url, ?RequestOptions $options = null)`
- `options(string $url, ?RequestOptions $options = null)`
- `trace(string $url, ?RequestOptions $options = null)`

Each returns an `AsyncRequestHandle`.

### 6.2 Protocol-specific methods

- `websocket(string $url, ?RequestOptions $options = null)`
- `ws(string $url, ?RequestOptions $options = null)`
- `sse(string $url, ?RequestOptions $options = null)`
- `eventSource(string $url, ?RequestOptions $options = null)`

These are specialized wrappers that set the `protocol` field internally.

### 6.3 Functional-style entry point

You can also use:

```php
use function Astra\Http\initAstraHTTP;

$client = initAstraHTTP(['port' => 9119]);
```

---

## 7. RequestOptions in detail

`Astra\Http\Contract\RequestOptions` is the central request configuration object.

### 7.1 Constructor

```php
new RequestOptions([
    'headers' => [],
    'body' => null,
    'responseType' => 'json',
]);
```

All properties are optional. Anything not provided remains `null` or the class default.

---

## 8. Request options reference

### 8.1 `headers`

Type: `array|null`

Defines the request headers.

Example:

```php
new RequestOptions([
    'headers' => [
        'Accept' => 'application/json',
        'User-Agent' => 'AstraHTTP PHP',
    ],
]);
```

### 8.2 `cookies`

Type: `array|object|null`

Supported forms:

#### Associative array form

```php
new RequestOptions([
    'cookies' => [
        'session' => 'abc123',
        'theme' => 'dark',
    ],
]);
```

#### Array of cookie objects

```php
new RequestOptions([
    'cookies' => [
        ['name' => 'session', 'value' => 'abc123'],
        ['name' => 'theme', 'value' => 'dark'],
    ],
]);
```

#### Object form

```php
new RequestOptions([
    'cookies' => (object) [
        'session' => 'abc123',
    ],
]);
```

### 8.3 `body`

Type: `mixed`

Accepted body forms:

- string
- `Stringable`
- array
- `ReadableStream`
- multipart-like array structure
- binary data string

The body is normalized automatically.

#### String body

```php
new RequestOptions([
    'body' => 'hello world',
]);
```

#### JSON body

Any plain array that does not look like multipart is JSON-encoded automatically.

```php
new RequestOptions([
    'headers' => [
        'Content-Type' => 'application/json',
    ],
    'body' => [
        'name' => 'Ali',
        'age' => 42,
    ],
]);
```

#### Multipart body

An array becomes multipart when it contains `_multipart => true` or field entries that look like file descriptors.

Example with file path:

```php
new RequestOptions([
    'body' => [
        'title' => 'My upload',
        'file' => [
            'path' => __DIR__ . '/document.pdf',
            'filename' => 'document.pdf',
            'mime' => 'application/pdf',
        ],
    ],
]);
```

Example with raw in-memory content:

```php
new RequestOptions([
    'body' => [
        '_multipart' => true,
        'name' => 'Ali',
        'avatar' => [
            'filename' => 'avatar.png',
            'mime' => 'image/png',
            'content' => file_get_contents(__DIR__ . '/avatar.png'),
        ],
    ],
]);
```

### 8.4 `responseType`

Type: `json|text|arraybuffer|blob|stream|null`

Controls how the response body is presented.

- `json` — decode JSON into PHP array
- `text` — return string text
- `arraybuffer` — return binary string suitable for binary handling
- `blob` — return binary string with blob-style intent
- `stream` — retain a stream object for incremental reading

Example:

```php
new RequestOptions([
    'responseType' => 'stream',
]);
```

### 8.5 TLS fingerprint fields

These fields are passed to the worker for transport fingerprint configuration.

- `ja3`
- `ja4r`
- `http2Fingerprint`
- `quicFingerprint`
- `disableGrease`

Example:

```php
new RequestOptions([
    'ja4r' => '771,4865-4866-4867,...',
    'http2Fingerprint' => 'SETTINGS_ENABLE_PUSH=0;WINDOW_SIZE=6291456',
    'disableGrease' => true,
]);
```

### 8.6 Browser / connection fields

- `userAgent`
- `serverName`
- `proxy`
- `timeout`
- `disableRedirect`
- `headerOrder`
- `orderAsProvided`
- `insecureSkipVerify`
- `forceHTTP1`
- `forceHTTP3`
- `protocol`

Example:

```php
new RequestOptions([
    'userAgent' => 'Mozilla/5.0 ...',
    'proxy' => 'http://127.0.0.1:8080',
    'timeout' => 15000,
    'forceHTTP1' => true,
]);
```

### 8.7 Retry policy fields

- `maxRetries`
- `retryDelayMs`
- `retryable`

Example:

```php
new RequestOptions([
    'maxRetries' => 3,
    'retryDelayMs' => 500,
    'retryable' => true,
]);
```

### 8.8 Lifecycle hooks

- `onHeaders`
- `onChunk`
- `onComplete`
- `onError`

These are local PHP callbacks.

#### `onHeaders`
Called when response metadata arrives.

```php
new RequestOptions([
    'onHeaders' => function (array $meta, string $requestId): void {
        echo "Headers received for {$requestId}\n";
    },
]);
```

#### `onChunk`
Called for response body chunks.

```php
new RequestOptions([
    'responseType' => 'stream',
    'onChunk' => function (string $chunk, string $requestId, ?array $meta): void {
        echo $chunk;
    },
]);
```

#### `onComplete`
Called after a request finishes.

```php
new RequestOptions([
    'onComplete' => function (\Astra\Http\Response $response, string $requestId): void {
        echo "Completed: {$requestId}\n";
    },
]);
```

#### `onError`
Called when a request fails.

```php
new RequestOptions([
    'onError' => function (\Throwable|string $error, string $requestId): void {
        echo "Error for {$requestId}: " . (string) $error . PHP_EOL;
    },
]);
```

---

## 9. Response object in detail

Requests resolve to `Astra\Http\Response`.

### 9.1 Public properties

- `status` — HTTP status code
- `headers` — response headers as an array
- `finalUrl` — final URL after redirection or transport handling
- `body` — raw response body string

### 9.2 Response methods

#### `text(): string`
Returns the raw body as text.

#### `json(): array`
Attempts JSON decoding and returns an array.

#### `arrayBuffer(): string`
Returns the raw binary content as a string.

#### `blob(): string`
Returns the raw binary content as a string.

#### `asStream(): ReadableStream`
Returns a readable stream wrapper around the body.

#### `isStreamed(): bool`
Returns `true` if the response was produced with a stream object.

### 9.3 Examples

#### Text response

```php
$response = $client->get('https://example.com', new RequestOptions([
    'responseType' => 'text',
]))->await();

echo $response->text();
```

#### JSON response

```php
$response = $client->get('https://httpbin.org/json', new RequestOptions([
    'responseType' => 'json',
]))->await();

$data = $response->json();
```

#### Binary response

```php
$response = $client->get('https://example.com/file.bin', new RequestOptions([
    'responseType' => 'arraybuffer',
]))->await();

file_put_contents(__DIR__ . '/file.bin', $response->arrayBuffer());
```

#### Stream response

```php
$response = $client->get('https://example.com/large-file', new RequestOptions([
    'responseType' => 'stream',
]))->await();

$stream = $response->asStream();
while (($chunk = $stream->read()) !== null) {
    echo $chunk;
}
```

---

## 10. Request handle

All request methods return an `AsyncRequestHandle`.

### Methods

- `await(): Response`
- `future(): Future`
- `getId(): string`

### Example

```php
$handle = $client->get('https://httpbin.org/get', new RequestOptions());

// Do other work here...

$response = $handle->await();
```

This style lets your application structure work around asynchronous request submission.

---

## 11. Streaming behavior

Streaming is useful for:

- large downloads
- incremental parsing
- log processing
- media delivery
- server-sent chunk processing

### Stream response example

```php
$response = $client->get('https://speed.hetzner.de/100MB.bin', new RequestOptions([
    'responseType' => 'stream',
]))->await();

$stream = $response->asStream();
while (null !== $chunk = $stream->read()) {
    // Process chunk immediately
    echo strlen($chunk) . " bytes\n";
}
```

### Important note

The stream interface is designed for consumption after the response resolves. For very large payloads, use streaming and process chunks as they arrive.

---

## 12. Multipart and file upload scenarios

### 12.1 Single file upload

```php
$response = $client->post('https://example.com/upload', new RequestOptions([
    'body' => [
        'file' => [
            'path' => __DIR__ . '/image.jpg',
            'filename' => 'image.jpg',
            'mime' => 'image/jpeg',
        ],
    ],
]))->await();
```

### 12.2 Mixed form fields and files

```php
$response = $client->post('https://example.com/upload', new RequestOptions([
    'body' => [
        'title' => 'Project file',
        'description' => 'Uploaded from PHP',
        'document' => [
            'path' => __DIR__ . '/report.pdf',
            'filename' => 'report.pdf',
            'mime' => 'application/pdf',
        ],
    ],
]))->await();
```

### 12.3 Multipart using explicit marker

```php
$response = $client->post('https://example.com/upload', new RequestOptions([
    'body' => [
        '_multipart' => true,
        'name' => 'Ali',
        'avatar' => [
            'filename' => 'avatar.png',
            'mime' => 'image/png',
            'content' => file_get_contents(__DIR__ . '/avatar.png'),
        ],
    ],
]))->await();
```

---

## 13. TLS / fingerprint customization

AstraHTTP PHP exposes fields for advanced transport fingerprinting.

### Common fields

- `ja3`
- `ja4r`
- `http2Fingerprint`
- `quicFingerprint`
- `disableGrease`

### Example

```php
$options = new RequestOptions([
    'ja4r' => '771,4865-4866-4867,0-10-11-13-16,29-23-24,0',
    'http2Fingerprint' => 'SETTINGS_ENABLE_PUSH=0',
    'disableGrease' => true,
]);
```

### When to use these

Use fingerprint settings when you need:

- stable transport identity
- controlled TLS behavior
- special upstream compatibility
- advanced traffic shaping

---

## 14. Retry and failover behavior

The wrapper includes local retry logic for safe methods and configurable retry policies.

### Default behavior

If `retryable` is not set:

- idempotent methods such as GET, HEAD, OPTIONS, TRACE may be retried
- unsafe methods are not retried automatically

### Force retry

```php
new RequestOptions([
    'retryable' => true,
    'maxRetries' => 5,
    'retryDelayMs' => 250,
]);
```

### Disable retry

```php
new RequestOptions([
    'retryable' => false,
]);
```

### Practical note

Retry is useful when the worker reconnects or the shared transport is interrupted. The library keeps request-level state separate through request identifiers.

---

## 15. Shared worker model

The library uses a shared-worker concept tied to a port.

### What this means

- Multiple PHP clients can point to the same worker port
- The worker manager keeps a reference count
- The transport can reconnect after a disconnect
- The runtime can attempt to attach to an existing worker first

### Why this matters

This reduces repeated startup costs and makes the wrapper suitable for long-running services and concurrent workloads.

### Example

```php
$clientA = new Client(['port' => 9119]);
$clientB = new Client(['port' => 9119]);
```

Both clients will share the same worker runtime in the same process space.

---

## 16. WebSocket usage

### Basic WebSocket request

```php
$socket = $client->websocket('wss://echo.example.com/socket', new RequestOptions([
    'protocol' => 'websocket',
]))->await();
```

### Notes

The library exposes the protocol field so your worker can treat the request as a WebSocket upgrade or a WS transport flow.

### Alias

`ws()` is an alias for `websocket()`.

---

## 17. SSE usage

### Basic SSE request

```php
$sse = $client->sse('https://example.com/events', new RequestOptions([
    'protocol' => 'sse',
]))->await();
```

### Alias

`eventSource()` is an alias for `sse()`.

### Typical use cases

- server push updates
- live event feeds
- progress streams
- dashboard refresh channels

---

## 18. Cookies

Cookies are normalized into the transport-friendly structure expected by the worker.

### Associative array example

```php
new RequestOptions([
    'cookies' => [
        'session' => 'abc123',
        'lang' => 'en',
    ],
]);
```

### Array of objects example

```php
new RequestOptions([
    'cookies' => [
        ['name' => 'session', 'value' => 'abc123'],
        ['name' => 'lang', 'value' => 'en'],
    ],
]);
```

---

## 19. Common usage patterns

### 19.1 Simple GET

```php
$response = $client->get('https://httpbin.org/get')->await();
echo $response->text();
```

### 19.2 POST JSON

```php
$response = $client->post('https://httpbin.org/post', new RequestOptions([
    'headers' => ['Content-Type' => 'application/json'],
    'body' => ['name' => 'Ali'],
    'responseType' => 'json',
]))->await();
```

### 19.3 Custom headers

```php
$response = $client->get('https://example.com', new RequestOptions([
    'headers' => [
        'Accept' => 'text/html',
        'Cache-Control' => 'no-cache',
    ],
]))->await();
```

### 19.4 Custom user agent

```php
$response = $client->get('https://example.com', new RequestOptions([
    'userAgent' => 'Mozilla/5.0 ...',
]))->await();
```

### 19.5 Proxy

```php
$response = $client->get('https://example.com', new RequestOptions([
    'proxy' => 'http://127.0.0.1:8080',
]))->await();
```

### 19.6 Ignore TLS verification

```php
$response = $client->get('https://self-signed.example.local', new RequestOptions([
    'insecureSkipVerify' => true,
]))->await();
```

### 19.7 Force HTTP/1

```php
$response = $client->get('https://example.com', new RequestOptions([
    'forceHTTP1' => true,
]))->await();
```

### 19.8 Long timeout

```php
$response = $client->get('https://example.com/slow', new RequestOptions([
    'timeout' => 30000,
]))->await();
```

---

## 20. Error handling

Wrap requests in `try / catch` blocks whenever failures are possible.

### Example

```php
try {
    $response = $client->get('https://example.com', new RequestOptions([
        'timeout' => 5000,
    ]))->await();

    echo $response->text();
} catch (\Throwable $e) {
    echo 'Request failed: ' . $e->getMessage();
}
```

### Typical error sources

- worker binary not found
- worker not reachable
- request timeout
- invalid response parsing
- transport disconnect during request
- malformed multipart file path

---

## 21. Practical examples

### Example A: read JSON API

```php
$response = $client->get('https://api.github.com', new RequestOptions([
    'headers' => [
        'Accept' => 'application/vnd.github+json',
        'User-Agent' => 'AstraHTTP PHP',
    ],
    'responseType' => 'json',
]))->await();

$data = $response->json();
```

### Example B: upload a file

```php
$response = $client->post('https://example.com/upload', new RequestOptions([
    'body' => [
        'file' => [
            'path' => __DIR__ . '/report.pdf',
            'filename' => 'report.pdf',
            'mime' => 'application/pdf',
        ],
    ],
]))->await();
```

### Example C: stream a large download

```php
$response = $client->get('https://example.com/big.bin', new RequestOptions([
    'responseType' => 'stream',
]))->await();

$stream = $response->asStream();
while (($chunk = $stream->read()) !== null) {
    file_put_contents(__DIR__ . '/big.bin', $chunk, FILE_APPEND);
}
```

### Example D: set TLS fingerprint

```php
$response = $client->get('https://example.com', new RequestOptions([
    'ja3' => '771,4865-4867-4866-49195-49199,...',
    'disableGrease' => true,
]))->await();
```

### Example E: event stream

```php
$sse = $client->sse('https://example.com/events')->await();
```

---

## 22. Recommended production practices

- always create the client once and reuse it where possible
- close the client explicitly at shutdown
- set a meaningful timeout on every external request
- use streaming for large bodies
- keep multipart file paths validated before use
- prefer explicit `RequestOptions` objects in shared codebases
- keep the worker binary under version control for deployments
- handle `\Throwable` around awaited requests

---

## 23. Minimal full example

```php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Astra\Http\Client;
use Astra\Http\Contract\RequestOptions;

$client = new Client([
    'port' => 9119,
    'debug' => false,
]);

try {
    $response = $client->get('https://httpbin.org/get', new RequestOptions([
        'responseType' => 'json',
        'headers' => [
            'Accept' => 'application/json',
        ],
    ]))->await();

    echo $response->text() . PHP_EOL;
} finally {
    $client->close();
}
```

---

## 24. Summary

AstraHTTP PHP is best used when you need:

- a stable worker shared across requests
- customizable TLS and transport behavior
- high-performance PHP orchestration
- streaming-aware response handling
- flexible request body encoding
- simple object-based request configuration

The package is designed to feel like a modern async client while still fitting naturally into PHP 8.2+ applications.

