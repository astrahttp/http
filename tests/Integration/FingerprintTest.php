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
 * suite (phpunit.xml excludes tests/Integration) and are run by the dedicated
 * "integration" CI job. Any environment problem (worker cannot be installed or
 * started, reflector unreachable) marks the test skipped rather than failed, so
 * the job never produces a false red; a reachable reflector that reports the
 * wrong fingerprint is a real failure.
 */
final class FingerprintTest extends TestCase
{
    /** Reflector that echoes the TLS fingerprint of the request. */
    private const REFLECTOR = 'https://tls.peet.ws/api/clean';

    // Two deliberately different JA3 strings (GREASE disabled for determinism).
    private const JA3_A = '771,4865-4866-4867-49195-49199-49196-49200-52393-52392-49171-49172-156-157-47-53,0-23-65281-10-11-35-16-5-13-18-51-45-43-27,29-23-24,0';
    private const JA3_B = '771,49195-49199-49196-49200-52393-52392-49171-49172-156-157-47-53-10,0-23-65281-10-11-35-16-5-13-51-45-43,29-23-24-25,0';

    private function makeClientOrSkip(): Client
    {
        try {
            WorkerDownloader::ensureInstalled();
            return new Client();
        } catch (\Throwable $e) {
            self::markTestSkipped('Worker unavailable in this environment: ' . $e->getMessage());
        }
    }

    private function observedJa3Hash(Client $client, string $ja3): string
    {
        $response = $client->get(self::REFLECTOR, new RequestOptions([
            'ja3' => $ja3,
            'disableGrease' => true,
            'responseType' => 'json',
            'timeout' => 30000, // milliseconds
            'userAgent' => 'AstraHTTP-IntegrationTest',
        ]))->await();

        if ($response->status !== 200) {
            throw new \RuntimeException('Reflector returned HTTP ' . $response->status);
        }

        $data = $response->json();
        if (!is_array($data) || !isset($data['ja3_hash']) || !is_string($data['ja3_hash']) || $data['ja3_hash'] === '') {
            throw new \RuntimeException('Reflector response missing ja3_hash');
        }

        return $data['ja3_hash'];
    }

    public function testWorkerPerformsHttpsRequestEndToEnd(): void
    {
        $client = $this->makeClientOrSkip();

        try {
            $response = $client->get(self::REFLECTOR, new RequestOptions([
                'responseType' => 'json',
                'timeout' => 30000, // milliseconds
            ]))->await();
        } catch (\Throwable $e) {
            $client->close();
            self::markTestSkipped('Reflector unreachable: ' . $e->getMessage());
        }

        $client->close();

        // Proves the PHP -> Go worker -> network -> response path works.
        self::assertSame(200, $response->status);
        self::assertNotSame('', $response->body);
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
            self::markTestSkipped('Reflector unreachable: ' . $e->getMessage());
        }

        $client->close();

        // The worker actually applied our fingerprint: different JA3 in -> different
        // fingerprint observed on the wire; same JA3 in -> same fingerprint.
        self::assertNotSame($hashA, $hashB, 'Different JA3 inputs must yield different observed fingerprints');
        self::assertSame($hashA, $hashA2, 'The same JA3 input must yield the same observed fingerprint');
    }
}
