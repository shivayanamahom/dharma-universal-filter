# Changelog

## 0.2.0 - 2026-06-10

Maintainability, correctness, and performance release.

### Added
- New `lib_dharma_universal_filter` shared library with a single `Indexer` class. The system and task plugins now delegate to it, removing ~250 lines of duplicated indexing logic that previously had to be kept in sync by hand.
- Canonical database schema as a shipped SQL file (`src/libraries/dharma_universal_filter/sql/install.mysql.utf8.sql`); the library, package and plugin installers all create the tables from this single source.

### Fixed
- Cascading filter now correctly disables fields whose values are all incompatible with the active selection. Previously a fully-unavailable custom field (e.g. a connection type with no products for the chosen body material) set a field-level `disabled` flag that the custom checkbox/list layouts ignore, so it still rendered as active. The helper now disables each option, which the layouts honour.
- Read cache is invalidated after every reindex, so newly indexed data is visible immediately instead of being served stale for up to the cache lifetime.
- Reindexing now runs inside a database transaction (delete + insert), so an interrupted write can no longer leave a product partially indexed or missing from the filter.
- AJAX response handler no longer throws when the response lacks a `#pageTitle` element.

### Changed
- Indexing loads products in batches (single query per batch) instead of one query per product, noticeably reducing full-rebuild time on large catalogs.
- Default module caching changed to "No caching". Filter output depends on the request query string, so static module output caching froze the cascade. Existing module instances should set Caching to "No caching" manually.
- Module, plugin and package versions bumped to 0.2.0.

## 0.1.0 - 2026-06-01

Initial open-source release.

- Added Joomla package with module, system plugin, and task plugin.
- Added indexed filter tables for RadicalMart product fields and prices.
- Added vertical and horizontal filter layouts.
- Added reusable field layouts for selects, checkbox lists, checkbox dropdowns, radio button groups, price inputs, and price slider.
- Added AJAX filtering with instant or apply-button behavior.
- Added cascading availability logic and optional option counts.
- Added mobile/offcanvas mode and sticky horizontal filter options.
- Added Russian and English language files.
- Added PowerShell build script for installable Joomla ZIP packages.
