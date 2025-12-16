# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

[1.3.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.2.1...v1.3.0
[1.2.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.1.1...v1.2.0
[1.1.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/sofascore/purgatory-bundle/compare/v1.0.1...v1.1.0
[1.0.1]: https://github.com/sofascore/purgatory-bundle/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/sofascore/purgatory-bundle/releases/tag/v1.0.0
