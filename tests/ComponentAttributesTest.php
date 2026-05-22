<?php

declare(strict_types=1);

namespace TwigComponents\Tests;

use PHPUnit\Framework\TestCase;
use TwigComponents\ComponentAttributes;

final class ComponentAttributesTest extends TestCase
{
    public function test_stringifies_string_attributes(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn', 'type' => 'submit']);

        self::assertSame(' class="btn" type="submit"', (string) $attrs);
    }

    public function test_boolean_true_renders_as_bare_attribute(): void
    {
        $attrs = new ComponentAttributes(['disabled' => true]);

        self::assertSame(' disabled', (string) $attrs);
    }

    public function test_boolean_false_is_omitted(): void
    {
        $attrs = new ComponentAttributes(['disabled' => false, 'class' => 'btn']);

        self::assertSame(' class="btn"', (string) $attrs);
    }

    public function test_null_is_omitted(): void
    {
        $attrs = new ComponentAttributes(['class' => null, 'id' => 'foo']);

        self::assertSame(' id="foo"', (string) $attrs);
    }

    public function test_empty_attributes_produce_empty_string(): void
    {
        $attrs = new ComponentAttributes([]);

        self::assertSame('', (string) $attrs);
    }

    public function test_values_are_html_escaped(): void
    {
        $attrs = new ComponentAttributes(['data-value' => '<script>alert(1)</script>']);

        self::assertStringContainsString('&lt;script&gt;', (string) $attrs);
        self::assertStringNotContainsString('<script>', (string) $attrs);
    }

    public function test_only(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn', 'id' => 'foo', 'type' => 'submit']);

        self::assertSame(' class="btn"', (string) $attrs->only('class'));
    }

    public function test_without(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn', 'id' => 'foo']);

        self::assertSame(' id="foo"', (string) $attrs->without('class'));
    }

    public function test_has(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn']);

        self::assertTrue($attrs->has('class'));
        self::assertFalse($attrs->has('id'));
    }

    public function test_get(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn']);

        self::assertSame('btn', $attrs->get('class'));
        self::assertNull($attrs->get('id'));
        self::assertSame('default', $attrs->get('id', 'default'));
    }

    public function test_all(): void
    {
        $data = ['class' => 'btn', 'disabled' => true];
        $attrs = new ComponentAttributes($data);

        self::assertSame($data, $attrs->all());
    }

    public function test_immutability(): void
    {
        $original = new ComponentAttributes(['class' => 'btn', 'id' => 'foo']);
        $filtered = $original->without('class');

        self::assertSame(' class="btn" id="foo"', (string) $original);
        self::assertSame(' id="foo"', (string) $filtered);
    }
}
