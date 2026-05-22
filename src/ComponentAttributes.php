<?php

declare(strict_types=1);

namespace TwigComponents;

/**
 * Immutable bag of HTML attributes passed to a component.
 * Casting to string renders all attributes as a safe HTML attribute string.
 *
 * Usage in a component template:
 *   <div{{ attrs }}>...</div>
 *   <div{{ attrs.without('class') }}>...</div>
 *   <div{{ attrs.only('class', 'id') }}>...</div>
 */
final class ComponentAttributes implements \Stringable
{
    public function __construct(private readonly array $attributes) {}

    public function __toString(): string
    {
        $html = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $safeKey = htmlspecialchars((string) $key, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

            if ($value === true) {
                $html .= ' ' . $safeKey;
            } else {
                $safeValue = htmlspecialchars((string) $value, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
                $html .= ' ' . $safeKey . '="' . $safeValue . '"';
            }
        }

        return $html;
    }

    public function only(string ...$keys): self
    {
        return new self(array_intersect_key($this->attributes, array_flip($keys)));
    }

    public function without(string ...$keys): self
    {
        return new self(array_diff_key($this->attributes, array_flip($keys)));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function all(): array
    {
        return $this->attributes;
    }
}
