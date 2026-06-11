# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - xxxx-xx-xx

### Added

- `ExpressionValues` to enable resolution of route parameter values via expressions by @HypeMC
  in https://github.com/sofascore/purgatory-bundle/pull/112
- Ability to pass a static method callable as a `DynamicValues` provider by @HypeMC
  in https://github.com/sofascore/purgatory-bundle/pull/137
- Ability to use a closure as the `PurgeOn` `if` condition on PHP 8.5+ by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/116

### Changed

- Method `AbstractValues::toArray()` is now `final` by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/130
- Method `AbstractValues::getValues()` is now `protected` by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/130
- Rename first constructor argument in `DynamicValues` to `$provider` by @HypeMC
  in https://github.com/sofascore/purgatory-bundle/pull/137
- Rename second constructor argument in `DynamicValues` to `$propertyPath` by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/130

### Removed

- Symfony v5 support by @HypeMC in https://github.com/sofascore/purgatory-bundle/pull/128
- `InverseValuesAwareInterface`, use dedicated builder services instead by @HypeMC
  in https://github.com/sofascore/purgatory-bundle/pull/123
- `ValuesInterface::getValues()`, use public properties instead by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/130

## [1.3.2] - 2026-06-10

### Fixed

- Fix purge route generation when Doctrine change sets contain a `PersistentCollection` by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/144

## [1.3.1] - 2026-06-08

### Fixed

- Fix nullsafe operator usage with collection property accessors by @Brajk19
  in https://github.com/sofascore/purgatory-bundle/pull/140

## [1.3.0] - 2025-12-15

### Added

- Allow Symfony v8 in `composer.json` by @HypeMC in https://github.com/sofascore/purgatory-bundle/pull/119

## [1.2.1] - 2025-10-13

### Added

- Allow Doctrine bundle v3 in `composer.json` by @HypeMC in https://github.com/sofascore/purgatory-bundle/pull/114

## [1.2.0] - 2025-04-28

### Added

- Validate `if` expression during cache warmup by @Brajk19 in https://github.com/sofascore/purgatory-bundle/pull/101
- Throw when `if` expression returns non-boolean by @Brajk19 in https://github.com/sofascore/purgatory-bundle/pull/105

## [1.1.1] - 2025-04-14

### Fixed

- Fix `if` with nullable inverse subscriptions by @Brajk19 in https://github.com/sofascore/purgatory-bundle/pull/98
  and https://github.com/sofascore/purgatory-bundle/pull/99

## [1.1.0] - 2025-01-27

### Added

- Validate route params during compilation by @Brajk19 in https://github.com/sofascore/purgatory-bundle/pull/90

## [1.0.1] - 2024-10-28

### Fixed

- Fix error when the Serializer component is not installed by @HypeMC
  in https://github.com/sofascore/purgatory-bundle/pull/72

## [1.0.0] - 2024-10-25

- Initial release

[2.0.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.3.0...v2.0.0
[1.3.2]: https://github.com/sofascore/purgatory-bundle/compare/v1.3.1...v1.3.2
[1.3.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.3.0...v1.3.1
[1.3.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/sofascore/purgatory-bundle/releases/tag/v1.0.0
