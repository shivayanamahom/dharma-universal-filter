# Security Policy

## Supported Versions

Security fixes target the current public release and the `main` branch.

## Reporting a Vulnerability

Please report security issues privately to the maintainer before opening a public issue. Include:

- Affected version or commit.
- Joomla and RadicalMart versions.
- Steps to reproduce.
- Expected impact.

Do not include production credentials, database dumps, or private customer data in reports.

## Security Expectations

- Installer scripts must not store secrets.
- Filter input must be read through Joomla input APIs.
- SQL queries must use Joomla database quoting/binding patterns.
- Layout output must be escaped unless intentionally rendering trusted Joomla HTML.
