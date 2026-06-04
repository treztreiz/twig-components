# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`treztreiz/twig-components` — a framework-free Twig component layer. It rewrites
`<twig:name />` and `<twig:name>…</twig:name>` HTML-like tags into native Twig
*before* Twig tokenizes the template, so it works in any plain Twig 3.x project
(no Symfony, no UX bundle). PHP 8.3+. Render-only, server-side, no JS.

See `README.md` for the user-facing syntax and wiring guide.

## Commands

```bash
composer test                 # phpunit
composer lint                 # phpstan (level 8, src + tests)
composer cs:check             # php-cs-fixer dry-run
composer cs:fix               # php-cs-fixer apply
composer check                # cs:check + lint + test (run before committing)

vendor/bin/phpunit --filter test_method_name      # single test
vendor/bin/phpunit tests/PreLexerTest.php          # single file
```

## Architecture

The transform happens in two stages: **compile-time source rewriting** (the
pre-lexer) and **render-time runtime functions**.

### Pre-lexer pipeline (compile time)

- `PreLexerLoader` (`src/PreLexerLoader.php`) decorates any Twig `LoaderInterface`.
  It intercepts `getSourceContext()`, runs `PreLexer::transform()` on the raw
  source, and passes native Twig to the inner loader. All other loader duties
  are delegated.
- `PreLexer` (`src/PreLexer.php`) is a hand-written character scanner (not regex-
  based overall — uses `\G` anchored matches at the cursor). It produces:
  - Self-closing `<twig:x />` → `{{ component('x', { props }) }}` (a Twig function call).
  - Slotted `<twig:x>…</twig:x>` → `{% embed '@ns/X.html.twig' with component_embed_vars({…}) only %}` … `{% endembed %}`.
  - `<twig:block name="y">` → `{% block y %}` (named slots).
  - Children before the first named slot are auto-wrapped in `{% block content %}`
    via `maybeOpenDefaultBlock()` — this is the trickiest piece of state in the scanner.
  - `{# comments #}` and `{% verbatim %}` blocks are passed through untouched.
- A `componentStack` tracks open components/blocks to validate matching close
  tags and to manage the implicit default block.

**Why two different output forms?** Self-closing components have no children, so
a plain `component()` function call (which does an isolated `$twig->render()`)
suffices. Slotted components need Twig's block machinery for slots, so they
compile to `{% embed %}`.

### Runtime functions (render time)

Registered by `ComponentExtension` (`src/ComponentExtension.php`):
- `component(name, props)` → delegates to `ComponentRenderer::render()`, which
  resolves the template, injects `attrs`, and returns `Markup`.
- `component_embed_vars(props)` → merges an `attrs` bag into the embed context
  (the embed path can't go through the renderer, so it builds `attrs` here).

`ComponentExtension::register()` is the preferred entry point — it also marks
`ComponentAttributes` as HTML-safe so `{{ attrs }}` works without `|raw`.

### Supporting pieces

- `ComponentAttributes` (`src/ComponentAttributes.php`) — immutable attribute bag,
  `Stringable`. `only()`/`without()`/`has()`/`get()`/`all()`. HTML-escapes on render.
- `{% props %}` tag — `PropsTokenParser` + `PropsNode` (`src/Twig/`). Compiles to
  PHP that applies defaults, throws `RuntimeError` for missing required props, and
  strips declared prop names from the `attrs` bag (`attrs->without(...)`).
- `ComponentConfig` — `templateExtension` + `loaderNamespace`. The host project
  must register `loaderNamespace` as a path on its `FilesystemLoader`.

## Invariants to respect

- **Name resolution is duplicated.** `PreLexer::resolveEmbedTemplate()` and
  `ComponentRenderer::resolveTemplateName()` implement the *same* `<twig:ui:card>`
  → `@ns/Ui/Card.html.twig` mapping. Keep them in sync if you change resolution.
- **Bump `PreLexerLoader::CACHE_KEY_VERSION`** whenever `PreLexer`'s output format
  changes — otherwise stale compiled templates won't be invalidated.
- **`block` is reserved** and cannot be used as a component name (it's the named-slot keyword).
- `attrs` is built in two places (renderer for `component()`, `component_embed_vars()`
  for embeds) — changes to attr handling must cover both paths.

## Testing

Tests use real `Twig\Environment` instances against fixtures in
`tests/fixtures/components/` (component templates) and
`tests/fixtures/templates/` (call-site pages). `PreLexerTest` asserts on the
transformed string output; `ComponentRenderTest`/`PropsTest`/
`ComponentAttributesTest` assert on rendered HTML. Test method names are
`snake_case` (enforced by php-cs-fixer).

## Conventions

- `declare(strict_types=1)` everywhere; classes are `final` (and `readonly` where
  applicable). PSR-12 + risky rules, single quotes, alpha-ordered imports — all
  enforced by `.php-cs-fixer.php`.
