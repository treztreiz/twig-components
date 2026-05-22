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

    // --- non-self-closing tags ---

    public function testNonSelfClosingWithContent(): void
    {
        $result = $this->preLexer->transform('<twig:card><p>hello</p></twig:card>');

        $this->assertSame(
            "{% embed '@components/Card.html.twig' only %}{% block content %}<p>hello</p>{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function testNonSelfClosingEmpty(): void
    {
        $result = $this->preLexer->transform('<twig:card></twig:card>');

        $this->assertSame(
            "{% embed '@components/Card.html.twig' only %}{% endembed %}",
            $result,
        );
    }

    public function testNonSelfClosingWithProps(): void
    {
        $result = $this->preLexer->transform('<twig:card title="Hello"><p>inner</p></twig:card>');

        $this->assertSame(
            "{% embed '@components/Card.html.twig' with { title: 'Hello' } only %}{% block content %}<p>inner</p>{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function testKebabNameNonSelfClosing(): void
    {
        $result = $this->preLexer->transform('<twig:my-card></twig:my-card>');

        $this->assertSame(
            "{% embed '@components/MyCard.html.twig' only %}{% endembed %}",
            $result,
        );
    }

    public function testNamedSlot(): void
    {
        $result = $this->preLexer->transform('<twig:modal><twig:block name="footer">Cancel</twig:block></twig:modal>');

        $this->assertSame(
            "{% embed '@components/Modal.html.twig' only %}{% block footer %}Cancel{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function testNamedSlotWithDefaultContent(): void
    {
        $result = $this->preLexer->transform('<twig:modal><twig:block name="footer">Cancel</twig:block>Sure?</twig:modal>');

        $this->assertSame(
            "{% embed '@components/Modal.html.twig' only %}{% block footer %}Cancel{% endblock %}{% block content %}Sure?{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function testDefaultContentBeforeNamedSlot(): void
    {
        // Default block must be closed before the named slot opens
        $result = $this->preLexer->transform('<twig:modal>Sure?<twig:block name="footer">Cancel</twig:block></twig:modal>');

        $this->assertSame(
            "{% embed '@components/Modal.html.twig' only %}{% block content %}Sure?{% endblock %}{% block footer %}Cancel{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function testNestedSelfClosingInsideNonSelfClosing(): void
    {
        $result = $this->preLexer->transform('<twig:card><twig:alert message="Hi" /></twig:card>');

        $this->assertSame(
            "{% embed '@components/Card.html.twig' only %}{% block content %}{{ component('alert', { message: 'Hi' }) }}{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function testMismatchedClosingTagThrows(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/Expected.*card.*found.*alert/');

        $this->preLexer->transform('<twig:card><p>hello</p></twig:alert>');
    }

    public function testUnclosedComponentThrows(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/closing tag.*card.*end of input/');

        $this->preLexer->transform('<twig:card>hello');
    }

    public function testBlockAtTopLevelBecomesBlockDefinition(): void
    {
        // In a component template: <twig:block name="content"> → {% block content %}...{% endblock %}
        $result = $this->preLexer->transform('<div><twig:block name="content"></twig:block></div>');

        $this->assertSame('<div>{% block content %}{% endblock %}</div>', $result);
    }

    public function testBlockAtTopLevelWithDefaultContent(): void
    {
        $result = $this->preLexer->transform('<twig:block name="title">Default title</twig:block>');

        $this->assertSame('{% block title %}Default title{% endblock %}', $result);
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
