# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module identity

- Composer package: `tweakwise/magento2-tweakwise-export`
- Magento module name: `Tweakwise_Magento2TweakwiseExport`
- PHP namespace root: `Tweakwise\Magento2TweakwiseExport\`
- All source lives under `src/`; Magento registration is in `src/registration.php`

## Commands

```bash
# Install dependencies
composer install

# Run all code-quality checks (phpstan, phpcs, phpmd, phplint, xmllint)
vendor/bin/grumphp run

# Individual checks
vendor/bin/phpstan analyse --configuration phpstan.neon
vendor/bin/phpcs
vendor/bin/phpmd src/ text ruleset.xml

# Run all tests
vendor/bin/codecept run

# Run only unit tests
vendor/bin/codecept run Unit

# Run a single test file
vendor/bin/codecept run Unit tests/Unit/SomeTest.php
```

Tests live in `tests/` with two Codeception suites: `Unit` and `Functional`.

## Commit message format

GrumPHP enforces conventional commits. The type must be one of:
`build`, `ci`, `chore`, `docs`, `feat`, `fix`, `perf`, `refactor`, `revert`, `style`, `test`

A Jira ticket number is also required, either as `TYPE(PROJ-123): message` or `TYPE: PROJ-123 message`.

## Architecture

### Feed generation flow

`Model/Export.php` is the top-level orchestrator. It handles file locking, temp-file swap, archiving, validation, and triggering the Tweakwise import API. It delegates the actual XML writing to `Model/Write/Writer.php`.

`Writer` holds a set of `WriterInterface` implementations injected via DI: `Categories`, `Products`, `Stock`, and `Price`. When generating a specific feed type (`stock` or `price`), `Writer::determineWriters()` discards the others.

### Iterator / EAV query pattern

Data retrieval deliberately avoids Magento's native collection layer for performance. `Model/Write/EavIterator.php` is the base class that queries EAV tables directly using raw `Zend_Db_Select`. `Products/Iterator`, `Stock/Iterator`, and `Price/Iterator` extend it. Each works in configurable batches (set in `Model/Config.php`).

### CollectionDecorator pattern

After the iterator builds a batch of `ExportEntity` objects into a `Collection`, a pipeline of `CollectionDecorator\DecoratorInterface` implementations enriches it. The full-product pipeline (configured in `di.xml`) runs in order:

1. `CategoryReference` – attaches category tree data
2. `Children` – loads child products (for configurable/grouped/bundle)
3. `StockData` – resolves stock via MSI (`SourceItemMapProvider`) or legacy (`StockItemMapProvider`)
4. `ChildrenAttributes` – aggregates child attributes onto the parent
5. `Price` – calculates prices including child variants and exchange rates
6. `WebsiteLink` – adds the product URL
7. `Review` – adds review summary

To add a new decorator, implement `DecoratorInterface` and register it in `di.xml` under the relevant `Iterator`'s `collectionDecorators` argument.

### Entity ID prefixing

All category and product IDs in the feed are prefixed: `1000{store_id}{entity_id}`. This allows a single Tweakwise instance to serve multiple Magento stores. See `Model/Helper.php` for the helper methods.

### Product type handling

`ExportEntityFactory` maps Magento product types to export entity classes:

- `configurable` → `ExportEntityConfigurable`
- `grouped` → `ExportEntityGrouped`
- `bundle` → `ExportEntityBundle`

Register custom product types by extending the `typeMap` in `di.xml`. Composite types extend `CompositeExportEntity` and implement the stock-through-children logic via `Traits/Stock/HasStockThroughChildren`.

### Attribute selection

`Model/ProductAttributes.php` determines which EAV attributes are exported. An attribute must be used for listing, filtering, search, or sorting — and must not be in the `attributeBlacklist` (configurable via DI). `category_ids` is blacklisted by default.

### Review provider

`Model/Review/ReviewProviderInterface` is a DI preference point. The default implementation (`MagentoReviewProvider`) uses Magento's native review module. Swap it in `di.xml` to pull reviews from another source.

### Feed types

Three feed variants are supported via the `type` parameter:
- `null` (default) — full feed: categories + products
- `stock` — stock-only feed (lighter, faster)
- `price` — price-only feed

The Writer's `startDocumentType` method writes a different XML root for stock/price feeds (`startExternalDocument`) vs. the full feed (`startDocument`), which includes shop name and timestamp.
