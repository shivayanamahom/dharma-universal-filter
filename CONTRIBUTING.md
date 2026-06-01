# Contributing

Contributions are welcome, especially around Joomla compatibility, RadicalMart field support, performance, accessibility, translations, and reusable layouts.

## Development Setup

1. Install Joomla 5.x or 6.x locally.
2. Install RadicalMart.
3. Clone this repository outside the Joomla web root.
4. Copy or symlink the extension folders into a Joomla test site while developing.
5. Build installable archives with:

```powershell
powershell -ExecutionPolicy Bypass -File .\build\build-package.ps1
```

## Coding Rules

- Keep Joomla 5/6 compatibility.
- Do not introduce deprecated Joomla `J*` classes.
- Do not use `Joomla\CMS\Input\Input`; use `Joomla\Input\Input` when a direct input import is required.
- Do not use `Joomla\CMS\Filesystem\*`; use `Joomla\Filesystem\*`.
- Escape user-facing output in layouts.
- Use Joomla database APIs and quoted identifiers/values.
- Keep site-specific template overrides and private data out of the repository.

## Pull Requests

Please include:

- A short description of the behavior change.
- Joomla/RadicalMart versions used for testing.
- Screenshots for UI changes.
- Notes about database or migration changes.
