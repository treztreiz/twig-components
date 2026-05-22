<?php

declare(strict_types=1);

namespace TwigComponents\Tests;

use PHPUnit\Framework\TestCase;
use TwigComponents\ComponentAttributes;

final class ComponentAttributesTest extends TestCase
{
    public function testStringifiesStringAttributes(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn', 'type' => 'submit']);

        $this->assertSame(' class="btn" type="submit"', (string) $attrs);
    }

    public function testBooleanTrueRendersAsBareAttribute(): void
    {
        $attrs = new ComponentAttributes(['disabled' => true]);

        $this->assertSame(' disabled', (string) $attrs);
    }

    public function testBooleanFalseIsOmitted(): void
    {
        $attrs = new ComponentAttributes(['disabled' => false, 'class' => 'btn']);

        $this->assertSame(' class="btn"', (string) $attrs);
    }

    public function testNullIsOmitted(): void
    {
        $attrs = new ComponentAttributes(['class' => null, 'id' => 'foo']);

        $this->assertSame(' id="foo"', (string) $attrs);
    }

    public function testEmptyAttributesProduceEmptyString(): void
    {
        $attrs = new ComponentAttributes([]);

        $this->assertSame('', (string) $attrs);
    }

    public function testValuesAreHtmlEscaped(): void
    {
        $attrs = new ComponentAttributes(['data-value' => '<script>alert(1)</script>']);

        $this->assertStringContainsString('&lt;script&gt;', (string) $attrs);
        $this->assertStringNotContainsString('<script>', (string) $attrs);
    }

    public function testOnly(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn', 'id' => 'foo', 'type' => 'submit']);

        $this->assertSame(' class="btn"', (string) $attrs->only('class'));
    }

    public function testWithout(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn', 'id' => 'foo']);

        $this->assertSame(' id="foo"', (string) $attrs->without('class'));
    }

    public function testHas(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn']);

        $this->assertTrue($attrs->has('class'));
        $this->assertFalse($attrs->has('id'));
    }

    public function testGet(): void
    {
        $attrs = new ComponentAttributes(['class' => 'btn']);

        $this->assertSame('btn', $attrs->get('class'));
        $this->assertNull($attrs->get('id'));
        $this->assertSame('default', $attrs->get('id', 'default'));
    }

    public function testAll(): void
    {
        $data = ['class' => 'btn', 'disabled' => true];
        $attrs = new ComponentAttributes($data);

        $this->assertSame($data, $attrs->all());
    }

    public function testImmutability(): void
    {
        $original = new ComponentAttributes(['class' => 'btn', 'id' => 'foo']);
        $filtered = $original->without('class');

        $this->assertSame(' class="btn" id="foo"', (string) $original);
        $this->assertSame(' id="foo"', (string) $filtered);
    }
}
