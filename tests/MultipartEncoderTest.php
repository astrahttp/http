<?php

declare(strict_types=1);

namespace Astra\Http\Tests;

use Astra\Http\Exception\AstraException;
use Astra\Http\MultipartEncoder;
use PHPUnit\Framework\TestCase;

final class MultipartEncoderTest extends TestCase
{
    public function testEncodeReturnsBodyAndBoundaryHeader(): void
    {
        [$body, $headers] = MultipartEncoder::encode(['name' => 'Ali']);

        self::assertArrayHasKey('Content-Type', $headers);
        self::assertStringStartsWith('multipart/form-data; boundary=----AstraHTTPBoundary', $headers['Content-Type']);

        // The declared boundary must actually appear in the body.
        $boundary = substr($headers['Content-Type'], strlen('multipart/form-data; boundary='));
        self::assertStringContainsString('--' . $boundary . "\r\n", $body);
        self::assertStringEndsWith('--' . $boundary . "--\r\n", $body);
    }

    public function testScalarFieldIsEncodedAsFormData(): void
    {
        [$body] = MultipartEncoder::encode(['name' => 'Ali']);

        self::assertStringContainsString('Content-Disposition: form-data; name="name"', $body);
        self::assertStringContainsString("\r\n\r\nAli\r\n", $body);
    }

    public function testMultipartMarkerIsNotEmittedAsField(): void
    {
        [$body] = MultipartEncoder::encode(['_multipart' => true, 'name' => 'Ali']);

        self::assertStringNotContainsString('name="_multipart"', $body);
        self::assertStringContainsString('name="name"', $body);
    }

    public function testInMemoryFileContentIsEncodedWithFilenameAndMime(): void
    {
        [$body] = MultipartEncoder::encode([
            'avatar' => [
                'filename' => 'avatar.png',
                'mime' => 'image/png',
                'content' => 'BINARYDATA',
            ],
        ]);

        self::assertStringContainsString('name="avatar"; filename="avatar.png"', $body);
        self::assertStringContainsString('Content-Type: image/png', $body);
        self::assertStringContainsString("\r\n\r\nBINARYDATA\r\n", $body);
    }

    public function testFileFromPathIsRead(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'astra_mp_');
        self::assertIsString($tmp);
        file_put_contents($tmp, 'from-disk');

        try {
            [$body] = MultipartEncoder::encode([
                'doc' => ['path' => $tmp, 'mime' => 'text/plain'],
            ]);

            self::assertStringContainsString('filename="' . basename($tmp) . '"', $body);
            self::assertStringContainsString("\r\n\r\nfrom-disk\r\n", $body);
        } finally {
            @unlink($tmp);
        }
    }

    public function testMissingFileThrows(): void
    {
        $this->expectException(AstraException::class);

        MultipartEncoder::encode([
            'doc' => ['path' => '/nonexistent/astra/' . bin2hex(random_bytes(6))],
        ]);
    }

    public function testQuotesAndNewlinesInFieldNameAreEscaped(): void
    {
        [$body] = MultipartEncoder::encode(['a"b' . "\r\n" . 'c' => 'v']);

        // Quote escaped, CR/LF stripped — prevents header injection.
        self::assertStringContainsString('name="a\\"bc"', $body);
        self::assertStringNotContainsString("name=\"a\"b\r\nc\"", $body);
    }

    public function testArrayValueIsJsonEncoded(): void
    {
        [$body] = MultipartEncoder::encode(['meta' => ['k' => 'v']]);

        self::assertStringContainsString('name="meta"', $body);
        self::assertStringContainsString('{"k":"v"}', $body);
    }
}
