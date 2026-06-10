# Architecture

Dharma Universal Filter is shipped as a Joomla package with a shared library and three extensions.

## Shared Library

`lib_dharma_universal_filter` provides `Dharma\UniversalFilter\Indexer`, the single implementation of the write path that builds the index tables. Both the system plugin (live, per-product reindex) and the task plugin (scheduled full rebuild) delegate to it, so indexing logic can never drift between them.

The indexer:

- owns the database schema (`sql/install.mysql.utf8.sql`) and creates the tables when missing;
- loads products in batches (one query per batch) instead of one query per product;
- wraps delete + insert in a database transaction so an interrupted write cannot leave a product partially indexed;
- invalidates the module read cache after every write so newly indexed data is visible immediately.

## Module

`mod_dharma_universal_filter` renders the frontend filter UI. It reads module parameters, resolves the current RadicalMart category context, prepares selected filters, loads available filter data, and renders the selected Joomla module layout.

The module contains reusable field layouts under:

```text
src/modules/mod_dharma_universal_filter/layouts/dharma_universal_filter/field/
```

These layouts make template overrides practical for individual field types:

- `select.php`
- `checkboxes.php`
- `checkboxes_dropdown.php`
- `radio.php`
- `price_inputs.php`
- `price_slider.php`

## Index Tables

The library owns the schema and creates two tables (the package and plugin installers create them from the same SQL file):

- `#__dharma_universal_filter_index`
- `#__dharma_universal_filter_price_index`

The field index stores normalized category, product, field, value hash, language, and stock information. The price index stores product price ranges by category and currency.

The module uses these tables to avoid recalculating every available option directly from product field data on each request.

## Cascading Availability

When the cascade is enabled, the module recomputes available values per field from the active selection. Values with no matching products are hidden or disabled depending on the empty-options mode. When *every* value of a field is incompatible with the current selection, each option of that field is disabled individually, because the custom checkbox/list layouts honour per-option state rather than a field-level `disabled` attribute.

## System Plugin

`plg_system_dharma_universal_filter` is responsible for keeping index data up to date around product save workflows and reindex tooling. It delegates the actual indexing to the shared library `Indexer`.

## Task Plugin

`plg_task_dharma_universal_filter` provides scheduled reindexing through the shared library `Indexer`. It is intended for full catalog rebuilds, maintenance windows, and sites where product data changes outside normal administrator save flows.

## Frontend Behavior

The frontend can work in non-AJAX mode, AJAX mode with instant updates, or AJAX mode with an explicit apply action. Horizontal filters can render checkbox groups as dropdown-like controls with selected-count badges and clear buttons. Vertical filters can render compact controls using the same field layout system.

## Joomla Compatibility

The code is written for Joomla 5/6 style extension structure and namespaces. Deprecated Joomla `J*` classes should not be added.
