# scramble-extras

[![tests](https://github.com/pjadanowski/scramble-extras/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/pjadanowski/scramble-extras/actions/workflows/tests.yml)

A free, open Laravel package that adds full **Spatie ecosystem** support to [dedoc/scramble](https://github.com/dedoc/scramble) — covering everything `scramble-pro` does for the integrations most projects actually need.

This is a fork of [pjadanowski/scramble-extras](https://github.com/pjadanowski/scramble-extras). See [Fork changes](#fork-changes) for what it adds and fixes on top of upstream.

## Features

- **Spatie Laravel Data** — output and input (request body) schemas auto-generated from your `Data` classes. Honors `MapName`/`MapInputName`, `Optional`, `Lazy`, `#[Computed]`, `#[Hidden]`, `#[DataCollectionOf]`, and reads `@example`/`@default` from property phpdoc.
- **All ~80 Spatie validation attributes → OpenAPI fields** — `#[Email]` → `format: email`, `#[Min(3)]` → `minLength`, `#[GreaterThan(0)]` → `exclusiveMinimum`, `#[In(...)]` / `#[Enum(SomeEnum::class)]` → `enum`, `#[Regex]` → `pattern`, `#[Image]` → `contentMediaType: image/*`, plus description notes for `#[Same]`, `#[Confirmed]`, `#[Exists]`, `#[RequiredIf]`, etc.
- **`PaginatedDataCollection<T>`, `CursorPaginatedDataCollection<T>`, `DataCollection<T>`** — proper response envelopes (Laravel paginator format / cursor format / plain array).
- **Spatie Laravel Query Builder** — `allowedFilters` / `allowedSorts` / `allowedIncludes` / `allowedFields` / `defaultSort` / `jsonPaginate()` parsed via AST. `AllowedFilter::exact($column)` on an enum-cast Eloquent column emits `enum: [...]` automatically.
- **Persistent schema cache** — file-based, mtime-invalidated. `php artisan scramble-extras:cache:clear` to wipe.
- **422 for `Data`-typed actions** — a controller action type-hinted with a `Data` class is validated the same way a `FormRequest` would be, so it now documents a `422` response the same way core Scramble already does for `FormRequest`.
- **`PATCH` documented alongside `PUT`** — Scramble's default route-methods resolver only documents the first HTTP method a route responds to, so `Route::apiResource()`'s update action (registered for both `PUT` and `PATCH`) only ever got a `put` operation. Opt out with `SCRAMBLE_EXTRAS_EXPAND_ROUTE_METHODS=false`. Requires a `dedoc/scramble` version with `GeneratorConfig::resolveOperationMethodsUsing()` (added after this package's 0.13.0 floor); on older installs this feature is a no-op rather than an error.

## Fork changes

Everything below is on top of upstream `pjadanowski/scramble-extras`.

### Added

- **laravel-actions support** — routes pointing at an action class are resolved to its `asController` method, so the action is documented like a controller. `#[ExcludeRouteFromDocs]` and `#[Api]` on `asController` are honoured. An action's public `rules()` is documented as the request body, whether the route takes an `ActionRequest` or no request at all. `ActionRequest` itself is ignored by the core `FormRequest` extractor so it no longer produces an empty body.
- **`Data::rules()` → request schema** — rules returned from a `Data` class's `rules()` method are mapped onto the request schema (types, formats, `enum`, min/max, `nullable`, required), the same way validation attributes are. Enums from `in:` / `Rule::in()` on array rules land on the array items, not the array.
- **File uploads** — `UploadedFile` properties are emitted as `type: string, format: binary`, and the request body switches to `multipart/form-data` with nested fields flattened to bracket notation (`lines[0][name]`).
- **`PaginatedListResponse`** — a helper for paginated lists that need extra `meta` (counts, totals, filters). It returns the normal paginated envelope with your `Data` object merged into `meta`, and Scramble documents the merged shape:

  ```php
  return PaginatedListResponse::make(
      UserData::collect($users, PaginatedDataCollection::class),
      $request,
      new UserListMetaData(...),
  );
  ```

- **Pagination query params** — `GET` routes returning a `PaginatedDataCollection` or `PaginatedListResponse` get a `page` parameter; cursor collections get a `cursor` parameter. Skipped when the route already declares one (e.g. `page[size]` from `jsonPaginate()`).
- **Query parameters from `Data`** — on bodyless methods (`GET`, `DELETE`) a `Data` parameter is documented as query parameters instead of a request body.
- **Return-type inference** — `Data::toResponse()` and custom static constructors on `Data` classes resolve to the right response schema.
- **Query builder** — usage is detected inside custom `QueryBuilder` subclasses (including resolving the model from them), and array and boolean filters are typed. Detection covers the route method and the methods it calls (including an action's `handle()` called from `asController`), so unrelated query builders elsewhere in the class are ignored.

### Fixed

- **Union types** are emitted as `anyOf` instead of keeping only the first member.
- **Duplicate query parameters** — when a `Data` query parameter and a query-builder filter share a name, only one is emitted, and rule-derived types (integer, boolean, date) win over the query builder's generic `string`.
- **Operation ids on multi-method routes** — the `PATCH` operation of a `PUT|PATCH` route gets a `.patch` suffix instead of a numeric de-duplication suffix; the primary method keeps its plain id.
- **Date formats** — date-only rules, attributes and `Y-m-d` date transformers emit `format: date` instead of `date-time`.
- **Rule mapping** — `$ref`s are left intact when constraints are applied, `in:` values are cast to the property's type, and fields that are `required` but not `nullable` are no longer marked nullable.
- **Request body components** — the request body is a single `$ref` to a `{ShortName}Input` component (e.g. `UserDataInput`), kept separate from the `UserData` output component.
- **Schema cache** — cached schemas keep their types and later mutations from other extensions; references are stored by fully-qualified class name so same-named `Data` classes in different namespaces don't collide; the cache invalidates when this package's source changes.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- `dedoc/scramble` ^0.12.20

## Install

```bash
composer require pjadanowski/scramble-extras
```

To use this fork instead of upstream, add it as a VCS repository in your app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/HishamM1/scramble-extras" }
],
"require": {
    "pjadanowski/scramble-extras": "dev-main"
}
```

The service provider auto-registers all extensions via Laravel's package discovery — no config touching needed. Install [`dedoc/scramble`](https://github.com/dedoc/scramble) itself if you haven't already, and you're done: your `Data` classes start showing up in the generated docs.

To document `allowedFilters/Sorts/Includes/Fields` you also need `spatie/laravel-query-builder` (the query-builder extension self-registers only when that package is present):

```bash
composer require spatie/laravel-query-builder
```

## Configuration

There's nothing to configure to get started. If you want to tune the cache, publish the config:

```bash
php artisan vendor:publish --tag=scramble-extras-config
```

```php
// config/scramble-extras.php
return [
    'cache' => [
        'enabled' => env('SCRAMBLE_EXTRAS_CACHE', true),
        'path' => env('SCRAMBLE_EXTRAS_CACHE_PATH'), // null = storage/framework/cache/scramble-extras/schemas.php
    ],
];
```

Or just set the env vars — e.g. disable the cache while iterating on `Data` classes locally:

```dotenv
SCRAMBLE_EXTRAS_CACHE=false
```

## How it plugs in

The package boots in two ways:

1. Calls `Scramble::registerExtensions(...)` in its service provider, contributing TypeToSchema, OperationExtension, and InferExtension classes that Scramble picks up automatically.
2. Calls `Scramble::configure()->withParametersExtractors(...)` to prepend a Data-aware parameter extractor for request bodies typed with `Data` subclasses.

You don't have to add anything to `config/scramble.php`.

## Cache

The package transparently caches built schemas in `storage/framework/cache/scramble-extras/schemas.php`. Cache invalidates per-class on `filemtime()` change, so editing a `Data` class auto-rebuilds just that class on the next export. The cache file also carries a signature derived from the installed Scramble version and a hash of this package's source files, so it self-invalidates after a `dedoc/scramble` or `scramble-extras` update — no manual clear required.

Writes are deferred: a full export builds every schema in memory and persists the cache **once** on shutdown, rather than rewriting the file per class.

To wipe manually:

```bash
php artisan scramble-extras:cache:clear
```

## Request bodies vs responses

A `Data` class is often used as both a response and a request body, and the two projections differ — `#[Computed]` is output-only, `#[Hidden]` is input-only, and `Optional` / `#[MapInputName]` change required-ness and field names. To keep both faithful, the request body gets its own `{ShortName}Input` component (e.g. `UserDataInput`) instead of `$ref`-ing the shared output component. Response schemas remain reusable `$ref` components.

## What's NOT covered (yet)

Long-tail features still on the roadmap:

- Sparse fieldsets rendered as proper enum array params (currently emitted as `string` with allowed values in the description)
- Polymorphic `Data` with discriminator schemas

## Testing

```bash
composer install
composer test
```

The suite covers the attribute → OpenAPI translation, the query-builder AST extraction, the schema cache, the return-type inference, and a full end-to-end Scramble generation pass (request bodies, response `$ref`s, paginated envelopes, and query parameters) booted with `orchestra/testbench`.

## License

MIT
