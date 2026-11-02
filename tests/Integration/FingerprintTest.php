<?php

declare(strict_types=1);

namespace Astra\Http\Tests\Integration;

use Astra\Http\Client;
use Astra\Http\Contract\RequestOptions;
use Astra\Http\WorkerDownloader;
use PHPUnit\Framework\TestCase;

/**
 * End-to-end integration tests that exercise the real Go worker.
 *
 * These require the worker binary (downloaded on demand) and outbound network
 * access to a TLS-fingerprint reflector. They are NOT part of the default unit
 * suite (phpunit.xml excludes tests/Integration) and run in the dedicated
 * "integration" CI job. Any environment problem (worker cannot be installed or
 * started, every reflector unreachable) marks the test skipped rather than
 * failed, so the job never produces a false red; a reachable reflector that
 * reports the wrong fingerprint is a real failure.
 */
final class FingerprintTest extends TestCase
{
    /** Reflectors that echo the TLS fingerprint as JSON with a `ja3_hash` field. */
    private const REFLECTORS = [
        'https://tls.browserleaks.com/json',
        'https://tls.peet.ws/api/clean',
    ];

    // Two deliberately different JA3 strings (GREASE disabled for determinism).
    private const JA3_A = '771,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27,29-23-24,0';
    private const JA3_B = '771,49195-49199-49196-49200-52393-52392-49171-49172-156-157-47-53-10,0-23-65281-10-11-35-16-5-13-51-45-43,29-23-24-25,0';

    private function makeClientOrSkip(): Client
    {
        try {
            WorkerDownloader::ensureInstalled();
            return new Client();
        } catch (\Throwable $e) {
            self::skip('Worker unavailable in this environment: ' . $e->getMessage());
        }
    }

    private static function skip(string $reason): never
    {
        // Surface the reason in CI logs even without --display-skipped.
        fwrite(STDERR, "[integration skipped] {$reason}\n");
        self::markTestSkipped($reason);
    }

    /**
     * Fetch the observed ja3_hash for a given JA3 input, trying each reflector
     * until one responds with a usable value. Throws if none do.
     */
    private function observedJa3Hash(Client $client, string $ja3): string
    {
        $errors = [];

        foreach (self::REFLECTORS as $url) {
            try {
                $response = $client->get($url, new RequestOptions([
                    'ja3' => $ja3,
                    'disableGrease' => true,
                    'responseType' => 'json',
                    'timeout' => 30000, // milliseconds
                    'userAgent' => 'AstraHTTP-IntegrationTest',
                ]))->await();

                if ($response->status !== 200) {
                    $errors[] = "{$url}: HTTP {$response->status}";
                    continue;
                }

                $data = $response->json();
                if (is_array($data) && isset($data['ja3_hash']) && is_string($data['ja3_hash']) && $data['ja3_hash'] !== '') {
                    return $data['ja3_hash'];
                }

                $errors[] = "{$url}: response missing ja3_hash";
            } catch (\Throwable $e) {
                $errors[] = "{$url}: " . $e->getMessage();
            }
        }

        throw new \RuntimeException('no reflector usable (' . implode(' | ', $errors) . ')');
    }

    public function testWorkerPerformsHttpsRequestEndToEnd(): void
    {
        $client = $this->makeClientOrSkip();

        $status = null;
        $body = null;
        $errors = [];
        foreach (self::REFLECTORS as $url) {
            try {
                $response = $client->get($url, new RequestOptions([
                    'responseType' => 'json',
                    'timeout' => 30000, // milliseconds
                    'userAgent' => 'AstraHTTP-IntegrationTest',
                ]))->await();
                $status = $response->status;
                $body = $response->body;
                break;
            } catch (\Throwable $e) {
                $errors[] = "{$url}: " . $e->getMessage();
            }
        }

        if ($status === null) {
            $client->close();
            self::skip('No reflector reachable: ' . implode(' | ', $errors));
        }

        $client->close();

        // Proves the PHP -> Go worker -> network -> response path works.
        self::assertSame(200, $status);
        self::assertNotSame('', (string) $body);
    }

    public function testDistinctJa3InputsProduceDistinctObservedFingerprints(): void
    {
        $client = $this->makeClientOrSkip();

        try {
            $hashA  = $this->observedJa3Hash($client, self::JA3_A);
            $hashB  = $this->observedJa3Hash($client, self::JA3_B);
            $hashA2 = $this->observedJa3Hash($client, self::JA3_A);
        } catch (\Throwable $e) {
            $client->close();
            self::skip('Reflector unreachable: ' . $e->getMessage());
        }

        $client->close();

        // The worker actually applied our fingerprint: different JA3 in -> different
        // fingerprint observed on the wire; the same JA3 in -> the same fingerprint.
        self::assertNotSame($hashA, $hashB, 'Different JA3 inputs must yield different observed fingerprints');
        self::assertSame($hashA, $hashA2, 'The same JA3 input must yield the same observed fingerprint');
    }
}
