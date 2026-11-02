# Contributing to AstraHTTP

Thanks for your interest in improving AstraHTTP. This document explains how to
set up the project, run the checks, and submit changes.

## Project layout

- `src/` — the PHP library (`Astra\Http` namespace, PSR-4).
- `src-go/` — the Go transport worker that PHP talks to over a local socket.
- `bin/astrahttp` — the installer/entry CLI shipped with the package.
- `tests/` — PHPUnit tests. Unit tests under `tests/` do **not** require a
  running Go worker; they exercise pure-PHP units (encoding, buffer parsing,
  request-option normalization).

## Requirements

- PHP 8.2+ with Composer.
- Go 1.22+ (only if you change the transport in `src-go/`).

## Getting started

```bash
git clone https://github.com/astrahttp/http.git
cd http
composer install
```

## Running the checks

```bash
composer lint    # php -l across src/
composer test    # PHPUnit unit suite
```

For the Go transport:

```bash
cd src-go
go build ./...
go vet ./...
```

## Pull requests

1. Create a topic branch from the default branch.
2. Keep changes focused; avoid unrelated refactors in the same PR.
3. Add or update tests for behavior you change. New pure-PHP logic should come
   with unit tests.
4. Run `composer lint` and `composer test` locally before pushing.
5. Describe the motivation and the user-visible impact in the PR description.
6. Preserve the public API (`Astra\Http\Client`, `RequestOptions`, `Response`)
   unless a breaking change is clearly justified and documented in
   `CHANGELOG.md`.

## Reporting bugs and requesting features

Use the GitHub issue templates. Include PHP version, OS/architecture, a minimal
reproduction, and the actual vs. expected behavior.

## Security

Do not open public issues for security problems. See [SECURITY.md](SECURITY.md).
