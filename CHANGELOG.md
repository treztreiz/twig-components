# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).
Releases are published via git tags and synced to
[Packagist](https://packagist.org/packages/treztreiz/twig-components).

## [Unreleased]

### Fixed

- Dashed attribute names (`data-*`, `aria-*`, etc.) now compile to quoted Twig
  hash keys instead of bare keys, which previously parsed the dash as
  subtraction and threw a syntax error.

## [1.3.0] - 2026-05-26

### Added

- `:foo` dynamic prop shorthand — passes the variable named `foo` without
  repeating it (`:foo` is equivalent to `:foo="foo"`).
- Pure-interpolation attribute values (`prop="{{ expr }}"`) are now treated as a
  single dynamic expression, the same as `:prop="expr"`.

## [1.2.0] - 2026-05-26

### Added

- `{% props %}` tag to declare component props. Declared props become named
  template variables, optional defaults are supported (`name = 'default'`),
  missing required props throw a `RuntimeError`, and declared props are stripped
  from the `attrs` passthrough bag.
- Subdirectory components via colon-separated names: `<twig:ui:alert />` resolves
  to `components/Ui/Alert.html.twig`.

## [1.0.1] - 2026-05-22

### Documentation

- Documented that `auto_reload` is required for component template changes to be
  picked up when a compiled-template cache is enabled.

## [1.0.0] - 2026-05-22

Initial release.

### Added

- Pre-lexer that rewrites `<twig:name />` and `<twig:name>…</twig:name>` into
  native Twig before tokenization, working in any plain Twig 3.x project.
- Self-closing components compile to a `component()` function call;
  non-self-closing components compile to `{% embed %}` with slot support.
- Named slots via `<twig:block name="…">`, with children before the first named
  slot auto-wrapped in the `content` block.
- Dynamic props (`:prop="expr"`) and boolean props (bare attribute names).
- `ComponentAttributes` bag for attribute passthrough (`{{ attrs }}`), with
  `only()`, `without()`, `has()`, `get()`, and `all()` helpers; rendered HTML-safe.
- `PreLexerLoader` cache key is suffixed with a pre-lexer version to bust stale
  compiled templates when the output format changes.
- MIT license, php-cs-fixer config, and PHPStan at level 8.

[Unreleased]: https://github.com/treztreiz/twig-components/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/treztreiz/twig-components/compare/v1.2.0...v1.3.0
[1.2.0]: https://github.com/treztreiz/twig-components/compare/v1.0.1...v1.2.0
[1.0.1]: https://github.com/treztreiz/twig-components/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/treztreiz/twig-components/releases/tag/v1.0.0
