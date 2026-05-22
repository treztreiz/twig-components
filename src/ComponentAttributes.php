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
final readonly class ComponentAttributes implements \Stringable
{
    /** @param array<string, bool|string|null> $attributes */
    public function __construct(private array $attributes)
    {
    }

    public function __toString(): string
    {
        $html = '';

        foreach ($this->attributes as $key => $value) {
            if ($value === false || $value === null) {
                continue;
            }

            $safeKey = htmlspecialchars($key, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');

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

    /** @return array<string, bool|string|null> */
    public function all(): array
    {
        return $this->attributes;
    }
}
