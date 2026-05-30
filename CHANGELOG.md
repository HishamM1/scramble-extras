# Changelog

All notable changes to `scramble-extras` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.2.0] - 2026-05-30

### Added
- Publishable `config/scramble-extras.php` with `cache.enabled` and `cache.path`
  options (also settable via `SCRAMBLE_EXTRAS_CACHE` / `SCRAMBLE_EXTRAS_CACHE_PATH`).
- Full test suite: unit tests for the attribute → OpenAPI applier, query-builder
  AST extraction, schema cache, support types, and return-type inference, plus an
  end-to-end Scramble generation test booted with `orchestra/testbench`.
- GitHub Actions CI matrix across PHP 8.2 / 8.3 / 8.4 and lowest/stable dependencies.

### Changed
- **Request bodies are now inlined** instead of `$ref`-ing a shared component.
  When a `Data` class is used as both a response and a request body, the input
  and output projections differ (`#[Computed]`, `#[Hidden]`, `Optional`,
  `#[MapInputName]`); sharing a component name made the request body describe the
  output shape. Response schemas remain reusable `$ref` components.
- `SchemaCache` writes are deferred and flushed once on shutdown rather than
  rewritten on every schema build, and the cache file now carries a signature
  derived from the installed Scramble version so it self-invalidates after an
  upgrade.

### Fixed
- Use `Response::description()` instead of `Response::setDescription()` on
  paginated/cursor responses; `setDescription()` does not exist on Scramble
  0.12.20 (the declared minimum), so generation crashed on older-but-supported
  Scramble versions. The `^0.12.20` floor is now actually honored.
- Extracted `QueryBuilderUsageVisitor` into its own file for PSR-4 compliance.
- Removed unused imports.

## [0.1.0] - 2026-05-04

### Added
- Initial release: Spatie Laravel Data (input/output schemas), all Spatie
  validation attributes, paginated/cursor/plain Data collections, Spatie Laravel
  Query Builder parameter extraction, and a persistent schema cache.

[Unreleased]: https://github.com/pjadanowski/scramble-extras/compare/v0.2.0...HEAD
[0.2.0]: https://github.com/pjadanowski/scramble-extras/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/pjadanowski/scramble-extras/releases/tag/v0.1.0
