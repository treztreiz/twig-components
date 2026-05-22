<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Error\SyntaxError;
use function count;
use function in_array;
use function sprintf;
use function strlen;

/**
 * Transforms <twig:name .../> and <twig:name>...</twig:name> syntax into
 * {{ component() }} / {% embed %} calls before Twig tokenizes the template.
 *
 * Self-closing     → {{ component('name', { props }) }}
 * Non-self-closing → {% embed '@ns/Name.html.twig' with { props } only %}
 *                      {% block content %}...{% endblock %}
 *                    {% endembed %}
 * Named slot       → <twig:block name="x"> → {% block x %}
 *
 * 'block' is a reserved tag name — it cannot be used as a component name.
 */
final class PreLexer
{
    private string $input;
    private int $length;
    private int $position;
    private int $line;

    /** @var array<array{name: string, hasDefaultBlock: bool, isBlock: bool}> */
    private array $componentStack;

    public function __construct(private readonly ComponentConfig $config = new ComponentConfig()) {}

    /**
     * @throws SyntaxError
     */
    public function transform(string $source): string
    {
        if (!str_contains($source, '<twig:')) {
            return $source;
        }

        $this->input = str_replace(["\r\n", "\r"], "\n", $source);
        $this->length = strlen($this->input);
        $this->position = 0;
        $this->line = 1;
        $this->componentStack = [];

        $output = '';

        while ($this->position < $this->length) {
            // Pass {% verbatim %} blocks through unchanged
            if ($this->consume('{% verbatim %}')) {
                $output .= '{% verbatim %}';
                $output .= $this->consumeUntil('{% endverbatim %}');
                $this->consume('{% endverbatim %}');
                $output .= '{% endverbatim %}';
                continue;
            }

            // Pass {# comments #} through unchanged
            if ($this->consume('{#')) {
                $output .= '{#';
                $output .= $this->consumeUntil('#}');
                $this->consume('#}');
                $output .= '#}';
                continue;
            }

            // Closing tag — check before <twig: to avoid matching </twig: as <twig:
            if ($this->consume('</twig:')) {
                $output .= $this->handleClosingTag();
                continue;
            }

            if ($this->consume('<twig:')) {
                $output .= $this->handleOpeningTag();
                continue;
            }

            $char = $this->input[$this->position];
            if (!ctype_space($char)) {
                $output .= $this->maybeOpenDefaultBlock();
            }
            if ($char === "\n") {
                ++$this->line;
            }
            $output .= $char;
            ++$this->position;
        }

        if (!empty($this->componentStack)) {
            $last = end($this->componentStack);
            throw new SyntaxError(
                sprintf(
                    'Expected closing tag </twig:%s> but reached end of input.',
                    $last['isBlock'] ? 'block' : $last['name'],
                ),
                $this->line,
            );
        }

        return $output;
    }

    /**
     * @throws SyntaxError
     */
    private function handleClosingTag(): string
    {
        $closingName = $this->consumeComponentName();
        $this->expectAndConsumeChar('>');

        if (empty($this->componentStack)) {
            throw new SyntaxError(
                sprintf('Unexpected closing tag </twig:%s>.', $closingName),
                $this->line,
            );
        }

        $last = array_pop($this->componentStack);

        if ($closingName === 'block') {
            if (!$last['isBlock']) {
                throw new SyntaxError(
                    sprintf('Unexpected </twig:block>: expected </twig:%s>.', $last['name']),
                    $this->line,
                );
            }
            return '{% endblock %}';
        }

        if ($last['isBlock'] || $last['name'] !== $closingName) {
            throw new SyntaxError(
                sprintf(
                    'Expected closing tag </twig:%s> but found </twig:%s>.',
                    $last['isBlock'] ? 'block' : $last['name'],
                    $closingName,
                ),
                $this->line,
            );
        }

        return ($last['hasDefaultBlock'] ? '{% endblock %}' : '') . '{% endembed %}';
    }

    /**
     * @throws SyntaxError
     */
    private function handleOpeningTag(): string
    {
        $name = $this->consumeComponentName();

        if ($name === 'block') {
            return $this->handleNamedSlot();
        }

        $attrs = $this->consumeAttributes($name);

        if ($this->consume('/>')) {
            $output = $this->maybeOpenDefaultBlock();
            $output .= $attrs !== ''
                ? sprintf("{{ component('%s', { %s }) }}", $name, $attrs)
                : sprintf("{{ component('%s', {}) }}", $name);
            return $output;
        }

        // Non-self-closing opening tag
        $this->expectAndConsumeChar('>');

        $output = $this->maybeOpenDefaultBlock();
        $template = $this->resolveEmbedTemplate($name);
        $output .= $attrs !== ''
            ? sprintf("{%% embed '%s' with { %s } only %%}", $template, $attrs)
            : sprintf("{%% embed '%s' only %%}", $template);

        $this->componentStack[] = ['name' => $name, 'hasDefaultBlock' => false, 'isBlock' => false];

        return $output;
    }

    /**
     * @throws SyntaxError
     */
    private function handleNamedSlot(): string
    {
        // Only "name" is supported on <twig:block>
        $this->consumeWhitespace();
        if (!preg_match('/\Gname\b/', $this->input, $m, 0, $this->position)) {
            throw new SyntaxError('<twig:block> requires a "name" attribute.', $this->line);
        }
        $this->position += strlen($m[0]);
        $this->expectAndConsumeChar('=');
        $quote = $this->consumeChar(['"', "'"]);
        $blockName = $this->consumeUntil($quote);
        $this->expectAndConsumeChar($quote);
        $this->consumeWhitespace();
        $this->expectAndConsumeChar('>');

        // When inside a component, close its open default block if there is one
        $output = '';
        if (!empty($this->componentStack)) {
            $top = &$this->componentStack[count($this->componentStack) - 1];
            if (!$top['isBlock'] && $top['hasDefaultBlock']) {
                $top['hasDefaultBlock'] = false;
                $output .= '{% endblock %}';
            }
        }

        $output .= sprintf('{%% block %s %%}', $blockName);
        $this->componentStack[] = ['name' => $blockName, 'hasDefaultBlock' => false, 'isBlock' => true];

        return $output;
    }

    /**
     * If directly inside a component (not a named slot) without an open default block, open one.
     */
    private function maybeOpenDefaultBlock(): string
    {
        if (empty($this->componentStack)) {
            return '';
        }
        $top = &$this->componentStack[count($this->componentStack) - 1];
        if (!$top['isBlock'] && !$top['hasDefaultBlock']) {
            $top['hasDefaultBlock'] = true;
            return '{% block content %}';
        }
        return '';
    }

    private function resolveEmbedTemplate(string $name): string
    {
        $pascal = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

        return '@' . $this->config->loaderNamespace . '/' . $pascal . $this->config->templateExtension;
    }

    /**
     * @throws SyntaxError
     */
    private function consumeComponentName(): string
    {
        if (preg_match('/\G[a-z][a-z0-9-]*/', $this->input, $matches, 0, $this->position)) {
            $this->position += strlen($matches[0]);
            return $matches[0];
        }

        throw new SyntaxError('Expected component name after "<twig:".', $this->line);
    }

    /**
     * @throws SyntaxError
     */
    private function consumeAttributes(string $componentName): string
    {
        $parts = [];

        while ($this->position < $this->length && !$this->check('>') && !$this->check('/>')) {
            $this->consumeWhitespace();

            if ($this->check('>') || $this->check('/>')) {
                break;
            }

            $isDynamic = $this->consume(':');

            if (!preg_match('/\G[a-zA-Z][a-zA-Z0-9-]*/', $this->input, $matches, 0, $this->position)) {
                throw new SyntaxError(
                    sprintf('Expected attribute name in "<twig:%s>".', $componentName),
                    $this->line,
                );
            }
            $key = $matches[0];
            $this->position += strlen($key);

            if ($isDynamic) {
                $this->expectAndConsumeChar('=');
                $quote = $this->consumeChar(['"', "'"]);
                $expr = $this->consumeUntil($quote);
                $this->expectAndConsumeChar($quote);
                $parts[] = sprintf('%s: %s', $key, $expr !== '' ? $expr : 'null');
            } elseif ($this->check('=')) {
                $this->expectAndConsumeChar('=');
                $quote = $this->consumeChar(['"', "'"]);
                $value = $this->consumeAttributeValue($quote);
                $this->expectAndConsumeChar($quote);
                $parts[] = sprintf('%s: %s', $key, $value !== '' ? $value : "''");
            } else {
                $parts[] = sprintf('%s: true', $key);
            }

            $this->consumeWhitespace();
        }

        return implode(', ', $parts);
    }

    /**
     * Consumes a static attribute value, handling {{ }} Twig interpolation.
     * "Hello {{ name }}!" → 'Hello ' ~ (name) ~ '!'
     * @throws SyntaxError
     */
    private function consumeAttributeValue(string $quote): string
    {
        $parts = [];
        $current = '';

        while ($this->position < $this->length && !$this->check($quote)) {
            if ($this->input[$this->position] === "\n") {
                ++$this->line;
            }

            if ($this->check('{{')) {
                if ($current !== '') {
                    $parts[] = sprintf("'%s'", str_replace("'", "\\'", $current));
                    $current = '';
                }
                $this->consume('{{');
                $this->consumeWhitespace();
                $expr = rtrim($this->consumeUntil('}}'));
                $this->expectAndConsumeChar('}');
                $this->expectAndConsumeChar('}');
                $parts[] = sprintf('(%s)', $expr);
                continue;
            }

            $current .= $this->input[$this->position];
            ++$this->position;
        }

        if ($current !== '') {
            $parts[] = sprintf("'%s'", str_replace("'", "\\'", $current));
        }

        return implode(' ~ ', $parts);
    }

    private function consume(string $string): bool
    {
        if (str_starts_with(substr($this->input, $this->position), $string)) {
            $this->position += strlen($string);
            return true;
        }
        return false;
    }

    /**
     * @throws SyntaxError
     */
    private function consumeChar(array|string|null $valid = null): string
    {
        if ($this->position >= $this->length) {
            throw new SyntaxError('Unexpected end of input.', $this->line);
        }

        $char = $this->input[$this->position];

        if ($valid !== null && !in_array($char, (array) $valid, true)) {
            throw new SyntaxError(
                sprintf("Expected one of [%s] but found '%s'.", implode('', (array) $valid), $char),
                $this->line,
            );
        }

        ++$this->position;
        return $char;
    }

    private function consumeUntil(string $needle): string
    {
        $pos = strpos($this->input, $needle, $this->position);

        if ($pos === false) {
            $content = substr($this->input, $this->position);
            $this->line += substr_count($content, "\n");
            $this->position = $this->length;
            return $content;
        }

        $content = substr($this->input, $this->position, $pos - $this->position);
        $this->line += substr_count($content, "\n");
        $this->position = $pos;

        return $content;
    }

    private function consumeWhitespace(): void
    {
        $len = strspn($this->input, " \t\n\r\0\x0B", $this->position);
        $ws = substr($this->input, $this->position, $len);
        $this->line += substr_count($ws, "\n");
        $this->position += $len;
    }

    /**
     * @throws SyntaxError
     */
    private function expectAndConsumeChar(string $char): void
    {
        if ($this->position >= $this->length) {
            throw new SyntaxError(
                sprintf("Expected '%s' but reached end of input.", $char),
                $this->line
            );
        }

        if ($this->input[$this->position] !== $char) {
            throw new SyntaxError(
                sprintf("Expected '%s' but found '%s'.", $char, $this->input[$this->position]),
                $this->line,
            );
        }

        ++$this->position;
    }

    private function check(string $string): bool
    {
        return $this->position + strlen($string) <= $this->length
            && substr_compare($this->input, $string, $this->position, strlen($string)) === 0;
    }
}
