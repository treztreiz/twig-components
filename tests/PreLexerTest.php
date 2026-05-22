<?php

declare(strict_types=1);

namespace TwigComponents\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Error\SyntaxError;
use TwigComponents\PreLexer;

final class PreLexerTest extends TestCase
{
    private PreLexer $preLexer;

    protected function setUp(): void
    {
        $this->preLexer = new PreLexer();
    }

    // --- existing spike tests (must all still pass) ---

    public function testSelfClosingNoProps(): void
    {
        $result = $this->preLexer->transform('<twig:alert />');

        $this->assertSame("{{ component('alert', {}) }}", $result);
    }

    public function testSelfClosingOneProp(): void
    {
        $result = $this->preLexer->transform('<twig:alert message="Saved!" />');

        $this->assertSame("{{ component('alert', { message: 'Saved!' }) }}", $result);
    }

    public function testSelfClosingMultipleProps(): void
    {
        $result = $this->preLexer->transform('<twig:button variant="primary" label="Click" />');

        $this->assertSame("{{ component('button', { variant: 'primary', label: 'Click' }) }}", $result);
    }

    public function testKebabTagName(): void
    {
        $result = $this->preLexer->transform('<twig:my-card title="Hello" />');

        $this->assertSame("{{ component('my-card', { title: 'Hello' }) }}", $result);
    }

    public function testSingleQuoteInPropValueIsEscaped(): void
    {
        $result = $this->preLexer->transform('<twig:alert message="it\'s fine" />');

        $this->assertSame("{{ component('alert', { message: 'it\\'s fine' }) }}", $result);
    }

    public function testNonMatchingTagsAreLeftUntouched(): void
    {
        $source = '<div class="alert">hello</div>';

        $this->assertSame($source, $this->preLexer->transform($source));
    }

    public function testMultipleTagsInSource(): void
    {
        $source = '<twig:alert message="A" />' . "\n" . '<twig:alert message="B" />';
        $result = $this->preLexer->transform($source);

        $this->assertSame(
            "{{ component('alert', { message: 'A' }) }}\n{{ component('alert', { message: 'B' }) }}",
            $result,
        );
    }

    public function testTagMixedWithRegularTwig(): void
    {
        $source = '{% if show %}<twig:alert message="Hi" />{% endif %}';
        $result = $this->preLexer->transform($source);

        $this->assertSame(
            "{% if show %}{{ component('alert', { message: 'Hi' }) }}{% endif %}",
            $result,
        );
    }

    // --- new scanner tests ---

    public function testTwigInterpolationInStaticValue(): void
    {
        $result = $this->preLexer->transform('<twig:alert message="Hello {{ name }}!" />');

        $this->assertSame(
            "{{ component('alert', { message: 'Hello ' ~ (name) ~ '!' }) }}",
            $result,
        );
    }

    public function testTwigInterpolationOnlyInValue(): void
    {
        // Value is entirely a Twig expression — no static prefix/suffix
        $result = $this->preLexer->transform('<twig:alert message="{{ name }}" />');

        $this->assertSame(
            "{{ component('alert', { message: (name) }) }}",
            $result,
        );
    }

    public function testComponentTagInsideTwigCommentIsPassedThrough(): void
    {
        $source = '{# <twig:alert message="Saved!" /> #}';

        $this->assertSame($source, $this->preLexer->transform($source));
    }

    public function testComponentTagInsideVerbatimIsPassedThrough(): void
    {
        $source = '{% verbatim %}<twig:alert message="Saved!" />{% endverbatim %}';

        $this->assertSame($source, $this->preLexer->transform($source));
    }

    public function testUnclosedAttributeValueThrowsWithLineNumber(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches("/Expected '\"' but reached end of input/");

        $this->preLexer->transform('<twig:alert message="unclosed />');
    }

    public function testNonSelfClosingTagThrows(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/Non-self-closing.*not yet supported/');

        $this->preLexer->transform('<twig:card>content</twig:card>');
    }

    // --- dynamic props ---

    public function testDynamicProp(): void
    {
        $result = $this->preLexer->transform('<twig:button :href="url" />');

        $this->assertSame("{{ component('button', { href: url }) }}", $result);
    }

    public function testDynamicPropWithExpression(): void
    {
        $result = $this->preLexer->transform('<twig:button :href="path(\'route\', {id: item.id})" />');

        $this->assertSame("{{ component('button', { href: path('route', {id: item.id}) }) }}", $result);
    }

    public function testMixedStaticAndDynamicProps(): void
    {
        $result = $this->preLexer->transform('<twig:button variant="primary" :href="url" />');

        $this->assertSame("{{ component('button', { variant: 'primary', href: url }) }}", $result);
    }

    public function testDynamicPropWithoutEqualsThrows(): void
    {
        $this->expectException(SyntaxError::class);

        // :prop without = is invalid
        $this->preLexer->transform('<twig:button :disabled />');
    }

    // --- boolean props ---

    public function testBooleanProp(): void
    {
        $result = $this->preLexer->transform('<twig:input disabled />');

        $this->assertSame("{{ component('input', { disabled: true }) }}", $result);
    }

    public function testMultipleBooleanProps(): void
    {
        $result = $this->preLexer->transform('<twig:input disabled readonly />');

        $this->assertSame("{{ component('input', { disabled: true, readonly: true }) }}", $result);
    }

    public function testBooleanMixedWithOtherProps(): void
    {
        $result = $this->preLexer->transform('<twig:input type="text" disabled :value="val" />');

        $this->assertSame("{{ component('input', { type: 'text', disabled: true, value: val }) }}", $result);
    }
}
