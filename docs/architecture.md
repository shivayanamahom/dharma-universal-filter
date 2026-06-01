# Architecture

Dharma Universal Filter is shipped as a Joomla package with three extensions.

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

The package installer creates two tables:

- `#__dharma_universal_filter_index`
- `#__dharma_universal_filter_price_index`

The field index stores normalized category, product, field, value hash, language, and stock information. The price index stores product price ranges by category and currency.

The module uses these tables to avoid recalculating every available option directly from product field data on each request.

## System Plugin

`plg_system_dharma_universal_filter` is responsible for keeping index data up to date around product save workflows and reindex tooling.

## Task Plugin

`plg_task_dharma_universal_filter` provides scheduled reindexing. It is intended for full catalog rebuilds, maintenance windows, and sites where product data changes outside normal administrator save flows.

## Frontend Behavior

The frontend can work in non-AJAX mode, AJAX mode with instant updates, or AJAX mode with an explicit apply action. Horizontal filters can render checkbox groups as dropdown-like controls with selected-count badges and clear buttons. Vertical filters can render compact controls using the same field layout system.

## Joomla Compatibility

The code is written for Joomla 5/6 style extension structure and namespaces. Deprecated Joomla `J*` classes should not be added.
