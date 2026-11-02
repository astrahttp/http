<?php

declare(strict_types=1);

namespace Astra\Http\Tests;

use Astra\Http\Contract\RequestOptions;
use PHPUnit\Framework\TestCase;

final class RequestOptionsTest extends TestCase
{
    public function testUnknownOptionsAreIgnored(): void
    {
        $options = new RequestOptions(['nonExistentOption' => 'x', 'timeout' => 5]);
        self::assertSame(5, $options->timeout);
        self::assertFalse(property_exists($options, 'nonExistentOption'));
    }

    public function testTransportArrayDropsNullsAndLocalOnlyFields(): void
    {
        $options = new RequestOptions([
            'timeout' => 5,
            'onComplete' => fn () => null,
            'maxRetries' => 3,
            'retryDelayMs' => 500,
            'retryable' => true,
        ]);

        $transport = $options->toTransportArray();

        // Local-only keys must never cross the transport boundary.
        self::assertArrayNotHasKey('onComplete', $transport);
        self::assertArrayNotHasKey('maxRetries', $transport);
        self::assertArrayNotHasKey('retryDelayMs', $transport);
        self::assertArrayNotHasKey('retryable', $transport);

        // Null fields are filtered out.
        self::assertArrayNotHasKey('ja3', $transport);

        self::assertSame(5, $transport['timeout']);
    }

    public function testAssociativeCookiesAreNormalizedToNameValuePairs(): void
    {
        $options = new RequestOptions(['cookies' => ['session' => 'abc', 'lang' => 'en']]);
        $transport = $options->toTransportArray();

        self::assertSame(
            [
                ['name' => 'session', 'value' => 'abc'],
                ['name' => 'lang', 'value' => 'en'],
            ],
            $transport['cookies']
        );
    }

    public function testObjectCookiesAreNormalized(): void
    {
        $options = new RequestOptions(['cookies' => (object) ['session' => 'abc']]);
        $transport = $options->toTransportArray();

        self::assertSame([['name' => 'session', 'value' => 'abc']], $transport['cookies']);
    }

    public function testArrayBodyIsJsonEncodedAndContentTypeAdded(): void
    {
        $options = new RequestOptions(['body' => ['name' => 'Ali', 'age' => 30]]);
        $transport = $options->toTransportArray();

        self::assertSame('{"name":"Ali","age":30}', $transport['body']);
        self::assertSame('application/json; charset=utf-8', $transport['headers']['Content-Type']);
    }

    public function testExistingContentTypeIsPreservedForArrayBody(): void
    {
        $options = new RequestOptions([
            'headers' => ['Content-Type' => 'application/vnd.api+json'],
            'body' => ['a' => 1],
        ]);
        $transport = $options->toTransportArray();

        self::assertSame('application/vnd.api+json', $transport['headers']['Content-Type']);
    }

    public function testStringBodyIsPassedThroughUnchanged(): void
    {
        $options = new RequestOptions(['body' => 'raw-payload']);
        $transport = $options->toTransportArray();

        self::assertSame('raw-payload', $transport['body']);
        self::assertArrayNotHasKey('headers', $transport);
    }

    public function testStringableBodyIsCastToString(): void
    {
        $body = new class implements \Stringable {
            public function __toString(): string
            {
                return 'from-object';
            }
        };

        $options = new RequestOptions(['body' => $body]);
        $transport = $options->toTransportArray();

        self::assertSame('from-object', $transport['body']);
    }

    public function testMultipartBodyProducesMultipartContentType(): void
    {
        $options = new RequestOptions([
            'body' => [
                '_multipart' => true,
                'name' => 'Ali',
            ],
        ]);
        $transport = $options->toTransportArray();

        self::assertStringStartsWith('multipart/form-data; boundary=', $transport['headers']['Content-Type']);
        self::assertStringContainsString('name="name"', $transport['body']);
    }

    public function testFileDescriptorBodyIsDetectedAsMultipart(): void
    {
        $options = new RequestOptions([
            'body' => [
                'avatar' => ['filename' => 'a.png', 'mime' => 'image/png', 'content' => 'x'],
            ],
        ]);
        $transport = $options->toTransportArray();

        self::assertStringStartsWith('multipart/form-data; boundary=', $transport['headers']['Content-Type']);
    }
}
