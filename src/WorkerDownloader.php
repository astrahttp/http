<?php

namespace Astra\Http;

class WorkerDownloader {
    const REPO = "astrahttp/http"; 
    const VERSION = "v1.0.0";

    public static function install() {
        $os = strtolower(PHP_OS);
        $arch = php_uname('m');
        $binDir = __DIR__ . '/../bin';

        if (!is_dir($binDir)) mkdir($binDir, 0755, true);
        $osName = 'linux';
        $ext = '';

        if (strpos($os, 'win') !== false) {
            $osName = 'win';
            $ext = '.exe';
        } elseif (strpos($os, 'darwin') !== false) {
            $osName = 'mac';
        } elseif (strpos($os, 'freebsd') !== false) {
            $osName = 'freebsd';
        } elseif (file_exists('/system/bin/app_process')) {
            $osName = 'android';
        }

        $archMap = [
            'x86_64'  => 'amd64',
            'amd64'   => 'amd64',
            'aarch64' => 'arm64',
            'arm64'   => 'arm64',
            'armv7l'  => 'arm',
            'i386'    => '386'
        ];
        $currentArch = $archMap[$arch] ?? 'amd64';

        $fileName = "astra-worker-{$osName}-{$currentArch}{$ext}";
        $url = "https://github.com/" . self::REPO . "/releases/download/" . self::VERSION . "/{$fileName}";
        $savePath = "{$binDir}/{$fileName}";

        echo "Checking for AstraHTTP Worker: {$fileName}...\n";

        if (file_exists($savePath)) {
            echo "Worker already exists. Skipping download.\n";
            return;
        }

        echo "Downloading binary from GitHub...\n";
        $context = stream_context_create(["http" => ["header" => "User-Agent: AstraHTTP-Installer\r\n"]]);
        $content = @file_get_contents($url, false, $context);

        if ($content === false) {
            echo "Error: Could not download binary for {$osName}/{$currentArch}.\n";
            return;
        }

        if (file_put_contents($savePath, $content)) {
            if ($osName !== 'win') chmod($savePath, 0755);
            echo "Successfully installed AstraHTTP Worker.\n";
        }
    }
}
