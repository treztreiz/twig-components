<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Error\SyntaxError;

/**
 * Transforms <twig:name .../> syntax into {{ component(...) }} calls before
 * Twig tokenizes the template. Uses a character-by-character scanner so that
 * {{ }} expressions inside attribute values are handled correctly.
 *
 * Scope (current): self-closing tags only, static + interpolated props.
 * Non-self-closing tags are reserved for the next slice.
 */
final class PreLexer
{
    private string $input;
    private int $length;
    private int $position;
    private int $line;

    public function transform(string $source): string
    {
        if (!str_contains($source, '<twig:')) {
            return $source;
        }

        $this->input = str_replace(["\r\n", "\r"], "\n", $source);
        $this->length = \strlen($this->input);
        $this->position = 0;
        $this->line = 1;

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

            if ($this->consume('<twig:')) {
                $name = $this->consumeComponentName();
                $attrs = $this->consumeAttributes($name);

                if ($this->consume('/>')) {
                    $output .= $attrs !== ''
                        ? \sprintf("{{ component('%s', { %s }) }}", $name, $attrs)
                        : \sprintf("{{ component('%s', {}) }}", $name);
                    continue;
                }

                // Non-self-closing: next slice
                throw new SyntaxError(
                    \sprintf('Non-self-closing <twig:%s> is not yet supported.', $name),
                    $this->line,
                );
            }

            $char = $this->input[$this->position];
            if ($char === "\n") {
                ++$this->line;
            }
            $output .= $char;
            ++$this->position;
        }

        return $output;
    }

    private function consumeComponentName(): string
    {
        if (preg_match('/\G[a-z][a-z0-9-]*/', $this->input, $matches, 0, $this->position)) {
            $this->position += \strlen($matches[0]);
            return $matches[0];
        }

        throw new SyntaxError('Expected component name after "<twig:".', $this->line);
    }

    private function consumeAttributes(string $componentName): string
    {
        $parts = [];

        while ($this->position < $this->length && !$this->check('>') && !$this->check('/>')) {
            $this->consumeWhitespace();

            if ($this->check('>') || $this->check('/>')) {
                break;
            }

            // :prop="expr" — dynamic prop: value is emitted as a raw Twig expression
            $isDynamic = $this->consume(':');

            if (!preg_match('/\G[a-zA-Z][a-zA-Z0-9-]*/', $this->input, $matches, 0, $this->position)) {
                throw new SyntaxError(
                    \sprintf('Expected attribute name in "<twig:%s>".', $componentName),
                    $this->line,
                );
            }
            $key = $matches[0];
            $this->position += \strlen($key);

            if ($isDynamic) {
                $this->expectAndConsumeChar('=');
                $quote = $this->consumeChar(['"', "'"]);
                $expr = $this->consumeUntil($quote);
                $this->expectAndConsumeChar($quote);
                $parts[] = \sprintf('%s: %s', $key, $expr !== '' ? $expr : 'null');
            } elseif ($this->check('=')) {
                $this->expectAndConsumeChar('=');
                $quote = $this->consumeChar(['"', "'"]);
                $value = $this->consumeAttributeValue($quote);
                $this->expectAndConsumeChar($quote);
                $parts[] = \sprintf('%s: %s', $key, $value !== '' ? $value : "''");
            } else {
                // Boolean prop: bare attribute with no value → true
                $parts[] = \sprintf('%s: true', $key);
            }

            $this->consumeWhitespace();
        }

        return implode(', ', $parts);
    }

    /**
     * Consumes a static attribute value, handling {{ }} Twig interpolation.
     * "Hello {{ name }}!" → 'Hello ' ~ (name) ~ '!'
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
                    $parts[] = \sprintf("'%s'", str_replace("'", "\\'", $current));
                    $current = '';
                }

                $this->consume('{{');
                $this->consumeWhitespace();
                $expr = rtrim($this->consumeUntil('}}'));
                $this->expectAndConsumeChar('}');
                $this->expectAndConsumeChar('}');
                $parts[] = \sprintf('(%s)', $expr);
                continue;
            }

            $current .= $this->input[$this->position];
            ++$this->position;
        }

        if ($current !== '') {
            $parts[] = \sprintf("'%s'", str_replace("'", "\\'", $current));
        }

        return implode(' ~ ', $parts);
    }

    /**
     * If the input at the current position starts with $string, advance past it and return true.
     */
    private function consume(string $string): bool
    {
        if (str_starts_with(substr($this->input, $this->position), $string)) {
            $this->position += \strlen($string);
            return true;
        }

        return false;
    }

    /**
     * Consume exactly one character, optionally asserting it is one of $valid.
     */
    private function consumeChar(array|string|null $valid = null): string
    {
        if ($this->position >= $this->length) {
            throw new SyntaxError('Unexpected end of input.', $this->line);
        }

        $char = $this->input[$this->position];

        if ($valid !== null && !\in_array($char, (array) $valid, true)) {
            throw new SyntaxError(
                \sprintf("Expected one of [%s] but found '%s'.", implode('', (array) $valid), $char),
                $this->line,
            );
        }

        ++$this->position;

        return $char;
    }

    /**
     * Advance until $needle is found. Returns consumed text; position stops just before $needle.
     */
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

    private function expectAndConsumeChar(string $char): void
    {
        if ($this->position >= $this->length) {
            throw new SyntaxError("Expected '{$char}' but reached end of input.", $this->line);
        }

        if ($this->input[$this->position] !== $char) {
            throw new SyntaxError(
                \sprintf("Expected '%s' but found '%s'.", $char, $this->input[$this->position]),
                $this->line,
            );
        }

        ++$this->position;
    }

    /**
     * Peek at the current position without advancing.
     */
    private function check(string $string): bool
    {
        return $this->position + \strlen($string) <= $this->length
            && substr_compare($this->input, $string, $this->position, \strlen($string)) === 0;
    }
}
