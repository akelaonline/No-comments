# Security Policy

Security issues should be handled privately until a fix is available.

## Supported versions

The latest released version of NO Comments receives security fixes. Older releases may be updated when a fix can be backported safely, but only the latest release should be considered actively supported.

| Version | Supported |
|---|---|
| 1.11.x | Yes |
| < 1.11 | Best effort |

## Reporting a vulnerability

Please do **not** open a normal public GitHub issue with exploit details, proof-of-concept payloads, credentials, private site information or a working attack path.

Preferred process:

1. Open the repository's **Security** tab.
2. Use **Report a vulnerability** / GitHub Private Vulnerability Reporting when available.
3. Include the affected version, WordPress/PHP versions, impact, reproduction steps and the smallest safe proof of concept needed to validate the report.
4. If private vulnerability reporting is not available, open a public issue containing only a request for a private security contact. Do not include technical exploit details in that issue.

## Scope

Reports are especially useful for:

- authorization/capability bypasses;
- CSRF or nonce problems;
- unsafe REST API behavior;
- stored/reflected XSS;
- destructive cleanup behavior outside the requested scope;
- Multisite privilege boundary problems;
- unintended disclosure of comment data;
- dependency or CI supply-chain issues affecting distributed releases.

## Disclosure

Please allow reasonable time for validation, a patch and a release before public disclosure. Confirmed reports will be credited when the reporter wants attribution.
