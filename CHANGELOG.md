# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project aims
to follow [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

> Note: entries for releases before this file was introduced are reconstructed
> from Git tags. Where a tag's exact scope was not recorded in commit history,
> the entry is intentionally conservative rather than inventing detail.

## [Unreleased]

### Added
- `LICENSE` file (MIT) matching the license declared in `composer.json`.
- PHPUnit test suite covering `MultipartEncoder`, `PacketBuffer` and
  `RequestOptions` transport normalization.
- `CONTRIBUTING.md`, `SECURITY.md`, `CODE_OF_CONDUCT.md`, issue/PR templates.
- GitHub Actions CI (PHP lint + unit tests on PHP 8.2–8.4, Go build/vet).

### Changed
- `MultipartEncoder::encode()` now validates file readability before reading,
  throwing `AstraException` cleanly instead of emitting a raw PHP warning on a
  missing or unreadable upload path.

## [2.0.0]
- Tagged release `v2.0.0`. See Git history for the included commits.

## [1.3.1]
- Documentation updates.

## [1.3.0]
- Executable path handling fixes and documentation updates.

## [1.0.0]
- Initial public release: Go-transport-backed async HTTP client for PHP,
  shared worker management, streaming responses, multipart encoding,
  TLS fingerprint options, and WebSocket / SSE entry points.

[Unreleased]: https://github.com/astrahttp/http/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/astrahttp/http/releases/tag/v2.0.0
[1.3.1]: https://github.com/astrahttp/http/releases/tag/v1.3.1
[1.3.0]: https://github.com/astrahttp/http/releases/tag/v1.3.0
[1.0.0]: https://github.com/astrahttp/http/releases/tag/v1.0.0
