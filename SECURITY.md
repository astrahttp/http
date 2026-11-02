# Security Policy

## Supported versions

Security fixes are provided for the latest released major version. Older
versions may receive fixes at the maintainer's discretion.

| Version | Supported |
| ------- | --------- |
| 2.x     | ✅        |
| < 2.0   | ❌        |

## Reporting a vulnerability

Please report security issues **privately**. Do not open a public GitHub issue.

- Preferred: open a private advisory via GitHub Security Advisories
  ("Report a vulnerability" on the repository Security tab).
- Alternatively, email the maintainer at `ajmalalkhaledi@gmail.com` with:
  - a description of the issue and its impact,
  - a minimal reproduction or proof of concept,
  - affected version(s) and environment.

You can expect an initial acknowledgement within a reasonable time frame. Please
allow time for a fix before any public disclosure.

## Scope notes

AstraHTTP exposes transport-level options such as `insecureSkipVerify` and
custom TLS fingerprints. These are powerful and can weaken connection security
if misused; that is expected behavior, not a vulnerability. Reports about the
library enabling such options by explicit user request are out of scope.
