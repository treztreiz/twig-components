# Twig Components

A lightweight, framework-free Twig component syntax layer.

Write components as HTML-like tags in your Twig templates — no Symfony, no
bundle, no framework dependency. The library rewrites `<twig:name />` and
`<twig:name>…</twig:name>` into native Twig before the engine tokenizes the
template, so it slots into any Twig 3.x project.

```html
<twig:alert message="Saved!" />

<twig:card title="Hello">
    <p>Default slot content</p>
    <twig:block name="footer">
        <button>OK</button>
    </twig:block>
</twig:card>
```

---

## Requirements

- PHP 8.3+
- Twig 3.x

No framework required.

---

## Installation

```bash
composer require treztreiz/twig-components
```

---

## Wiring

```php
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigComponents\ComponentConfig;
use TwigComponents\ComponentRenderer;
use TwigComponents\ComponentExtension;
use TwigComponents\PreLexer;
use TwigComponents\PreLexerLoader;

$config = new ComponentConfig(
    templateExtension: '.html.twig',
    loaderNamespace: 'components',        // must match the path alias below
);

$inner = new FilesystemLoader([__DIR__ . '/templates']);
$inner->addPath(__DIR__ . '/components', 'components');

$loader = new PreLexerLoader($inner, new PreLexer($config));

$twig = new Environment($loader, [
    'cache'       => __DIR__ . '/var/cache/twig',  // optional; safe with PreLexerLoader
    'auto_reload' => true,                         // recommended in dev; omit in production
]);

$renderer = new ComponentRenderer($config);
ComponentExtension::register($twig, $renderer);
```

**Filesystem layout:**

```
src/
  components/
    Alert.html.twig
    Card.html.twig
  templates/
    page.html.twig
```

Component names are resolved as `PascalCase`: `<twig:my-card />` → `MyCard.html.twig`.
Use colons as directory separators: `<twig:ui:alert />` → `components/Ui/Alert.html.twig`.

> **Cache note:** Twig only rechecks template freshness when `auto_reload` is enabled. Without it, editing a component template won't take effect until the compiled cache is cleared. Enable `auto_reload` in development; in production, clear `cache/` on deploy.

---

## Syntax

### Self-closing (no children)

```html
<twig:alert message="Saved!" />
<twig:input type="text" class="form-control" />
```

Props become variables inside the component template. All props are also
available as an `attrs` bag (see [Attribute passthrough](#attribute-passthrough)).

### Non-self-closing (with children / slots)

```html
<twig:card title="Hello">
    <p>Default slot content</p>
</twig:card>
```

The children become the `content` block. The component template uses
`{% embed %}` under the hood, so standard Twig block semantics apply.

### Named slots

```html
<twig:modal>
    Sure you want to delete this?
    <twig:block name="footer">
        <button>Cancel</button>
        <button>Confirm</button>
    </twig:block>
</twig:modal>
```

Children before the first named slot become the `content` block automatically.

### Dynamic props

Prefix a prop with `:` to pass a Twig expression instead of a string literal:

```html
<twig:button :href="path('app_home')" :disabled="not isLoggedIn" />
```

### Boolean props

Bare attribute names map to `true`:

```html
<twig:input disabled readonly />
```

### Subdirectory components

Use colons to reference components nested in subdirectories:

```html
<twig:ui:alert message="Saved!" />
<twig:ui:form:input type="email" />
```

`<twig:ui:alert />` → `components/Ui/Alert.html.twig`. Kebab segments work too:
`<twig:my-ui:form-input />` → `components/MyUi/FormInput.html.twig`.

### Twig interpolation in static values

```html
<twig:alert message="Hello {{ user.name }}!" />
```

### Parent context access

Components are isolated by default — they only see the props you pass. Add the
bare `context` flag to opt a component into reading its **parent's** scope
through a single `context` bag:

```html
<twig:sidebar context />
<twig:card context>…</twig:card>
```

```twig
{{-- components/Sidebar.html.twig --}}
<aside>Welcome back, {{ context.currentUser.name }}</aside>
```

- The bag is **flat and one level deep** — even through nested opted-in
  components you always reach `context.foo`, never `context.context.foo`.
  Values from a closer ancestor win over a more distant one with the same name.
- `context` is a **reserved bare flag**. It takes no value: `context="x"`,
  `:context` and `:context="x"` all throw a `SyntaxError`. A component therefore
  cannot receive a prop literally named `context`.
- The flag only exposes the parent scope; it never leaks into `{{ attrs }}`.

---

## Props

Use `{% props %}` inside a component template to declare which variables are
component-specific props. Declared props are extracted as named variables and
**automatically stripped from `attrs`**, leaving only the remaining HTML
attributes for passthrough.

```twig
{{-- components/Button.html.twig --}}
{% props label, variant = 'primary' %}

<button class="btn btn--{{ variant }}"{{ attrs }}>{{ label }}</button>
```

```html
<twig:button label="Submit" variant="danger" class="mt-4" id="my-btn" />
{{-- renders: <button class="btn btn--danger" class="mt-4" id="my-btn">Submit</button> --}}
```

- Props with a default value (`variant = 'primary'`) are optional.
- Props without a default are required — a missing required prop throws a
  `RuntimeError` at render time.
- `attrs` will only contain the non-prop attributes (`class`, `id`, `data-*`, etc.).

---

## Attribute passthrough

Every component receives an `attrs` variable — a `ComponentAttributes` instance
built from all the props passed at the call site.

```twig
{{-- components/Input.html.twig --}}
<input{{ attrs }}>
```

```html
<twig:input type="email" required />
{{-- renders: <input type="email" required> --}}
```

`attrs` is HTML-safe; use it without `|raw`.

**Filtering:**

```twig
<div{{ attrs.only('class', 'id') }}>
    <input{{ attrs.without('class', 'id') }}>
</div>
```

| Method                          | Returns                           |
|---------------------------------|-----------------------------------|
| `attrs.only('a', 'b')`          | new instance with only those keys |
| `attrs.without('a', 'b')`       | new instance without those keys   |
| `attrs.has('class')`            | bool                              |
| `attrs.get('class', 'default')` | scalar or null                    |
| `attrs.all()`                   | raw array                         |

---

## Defining block structure in component templates

Use `<twig:block>` inside component templates as a shorthand for `{% block %}`:

```twig
{{-- components/Card.html.twig --}}
<div class="card">
    <twig:block name="content"></twig:block>
</div>
```

Standard `{% block %}` syntax still works and is unchanged.

---

## Known limitations

- **Same-quote in dynamic prop values**: a dynamic prop value (`:prop="..."`)
  cannot contain a double quote internally. Use a Twig variable instead.
- **No PHP class per component**: logic must live in the template or be passed
  as props. If you need computed properties or service injection, Symfony
  TwigComponent is a better fit (see below).
- **No LiveComponent / reactivity**: this library is render-only, server-side,
  no JavaScript binding.

---

## Comparison with Symfony TwigComponent

| Feature                                     | treztreiz/twig-components | Symfony Twig Components                  |
|---------------------------------------------|---------------------------|------------------------------------------|
| Framework requirement                       | None                      | Symfony + UX bundle                      |
| PHP class per component                     | No                        | Yes (optional with anonymous components) |
| Computed properties / service injection     | No                        | Yes, via `mount()` and DI                |
| Template-only (anonymous) components        | Yes (always)              | Yes (opt-in)                             |
| `<twig:name />` syntax                      | Yes                       | Yes                                      |
| Named slots / blocks                        | Yes                       | Yes                                      |
| Attribute passthrough (`attrs`)             | Yes                       | Yes                                      |
| Dynamic props (`:prop="expr"`)              | Yes                       | Yes                                      |
| Boolean props                               | Yes                       | Yes                                      |
| `{% props %}` tag                           | Yes                       | Yes                                      |
| Subdirectory components (`ui:card`)         | Yes                       | Yes                                      |
| LiveComponent (reactive, JS binding)        | No                        | Yes (separate package)                   |
| Stimulus / Turbo integration                | No                        | Yes                                      |
| Works without Composer autoloading magic    | Yes                       | No                                       |
| Cache-safe (compiled template invalidation) | Yes                       | Yes                                      |

**Choose `treztreiz/twig-components` if:**
- You are outside of Symfony (plain PHP, other frameworks, static generators).
- Your components are purely presentational — no logic, no services.
- You want the HTML-like authoring experience without pulling in the full UX stack.

**Choose Symfony TwigComponent if:**
- You are in a Symfony application.
- Components need PHP-side logic, computed data, or injected services.
- You need LiveComponent for reactive UI without writing JavaScript.

---

## License

MIT
