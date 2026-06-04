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

    public function test_self_closing_no_props(): void
    {
        $result = $this->preLexer->transform('<twig:alert />');

        self::assertSame("{{ component('alert', {}) }}", $result);
    }

    public function test_self_closing_one_prop(): void
    {
        $result = $this->preLexer->transform('<twig:alert message="Saved!" />');

        self::assertSame("{{ component('alert', { message: 'Saved!' }) }}", $result);
    }

    public function test_self_closing_multiple_props(): void
    {
        $result = $this->preLexer->transform('<twig:button variant="primary" label="Click" />');

        self::assertSame("{{ component('button', { variant: 'primary', label: 'Click' }) }}", $result);
    }

    public function test_kebab_tag_name(): void
    {
        $result = $this->preLexer->transform('<twig:my-card title="Hello" />');

        self::assertSame("{{ component('my-card', { title: 'Hello' }) }}", $result);
    }

    public function test_dashed_attribute_names_become_string_keys(): void
    {
        $result = $this->preLexer->transform('<twig:input data-id="42" aria-label="Name" required />');

        self::assertSame(
            "{{ component('input', { 'data-id': '42', 'aria-label': 'Name', required: true }) }}",
            $result,
        );
    }

    public function test_dashed_dynamic_attribute_name_becomes_string_key(): void
    {
        $result = $this->preLexer->transform('<twig:input :data-count="total" />');

        self::assertSame("{{ component('input', { 'data-count': total }) }}", $result);
    }

    public function test_single_quote_in_prop_value_is_escaped(): void
    {
        $result = $this->preLexer->transform('<twig:alert message="it\'s fine" />');

        self::assertSame("{{ component('alert', { message: 'it\\'s fine' }) }}", $result);
    }

    public function test_non_matching_tags_are_left_untouched(): void
    {
        $source = '<div class="alert">hello</div>';

        self::assertSame($source, $this->preLexer->transform($source));
    }

    public function test_multiple_tags_in_source(): void
    {
        $source = '<twig:alert message="A" />' . "\n" . '<twig:alert message="B" />';
        $result = $this->preLexer->transform($source);

        self::assertSame(
            "{{ component('alert', { message: 'A' }) }}\n{{ component('alert', { message: 'B' }) }}",
            $result,
        );
    }

    public function test_tag_mixed_with_regular_twig(): void
    {
        $source = '{% if show %}<twig:alert message="Hi" />{% endif %}';
        $result = $this->preLexer->transform($source);

        self::assertSame(
            "{% if show %}{{ component('alert', { message: 'Hi' }) }}{% endif %}",
            $result,
        );
    }

    // --- new scanner tests ---

    public function test_twig_interpolation_in_static_value(): void
    {
        $result = $this->preLexer->transform('<twig:alert message="Hello {{ name }}!" />');

        self::assertSame(
            "{{ component('alert', { message: 'Hello ' ~ (name) ~ '!' }) }}",
            $result,
        );
    }

    public function test_twig_interpolation_only_in_value(): void
    {
        // Pure {{ expr }} — equivalent to :message="name" (dynamic prop shortcut)
        $result = $this->preLexer->transform('<twig:alert message="{{ name }}" />');

        self::assertSame(
            "{{ component('alert', { message: name }) }}",
            $result,
        );
    }

    public function test_component_tag_inside_twig_comment_is_passed_through(): void
    {
        $source = '{# <twig:alert message="Saved!" /> #}';

        self::assertSame($source, $this->preLexer->transform($source));
    }

    public function test_component_tag_inside_verbatim_is_passed_through(): void
    {
        $source = '{% verbatim %}<twig:alert message="Saved!" />{% endverbatim %}';

        self::assertSame($source, $this->preLexer->transform($source));
    }

    public function test_unclosed_attribute_value_throws_with_line_number(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches("/Expected '\"' but reached end of input/");

        $this->preLexer->transform('<twig:alert message="unclosed />');
    }

    // --- non-self-closing tags ---

    public function test_non_self_closing_with_content(): void
    {
        $result = $this->preLexer->transform('<twig:card><p>hello</p></twig:card>');

        self::assertSame(
            "{% embed '@components/Card.html.twig' with component_embed_vars({}) only %}{% block content %}<p>hello</p>{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_non_self_closing_empty(): void
    {
        $result = $this->preLexer->transform('<twig:card></twig:card>');

        self::assertSame(
            "{% embed '@components/Card.html.twig' with component_embed_vars({}) only %}{% endembed %}",
            $result,
        );
    }

    public function test_non_self_closing_with_props(): void
    {
        $result = $this->preLexer->transform('<twig:card title="Hello"><p>inner</p></twig:card>');

        self::assertSame(
            "{% embed '@components/Card.html.twig' with component_embed_vars({ title: 'Hello' }) only %}{% block content %}<p>inner</p>{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_kebab_name_non_self_closing(): void
    {
        $result = $this->preLexer->transform('<twig:my-card></twig:my-card>');

        self::assertSame(
            "{% embed '@components/MyCard.html.twig' with component_embed_vars({}) only %}{% endembed %}",
            $result,
        );
    }

    public function test_named_slot(): void
    {
        $result = $this->preLexer->transform('<twig:modal><twig:block name="footer">Cancel</twig:block></twig:modal>');

        self::assertSame(
            "{% embed '@components/Modal.html.twig' with component_embed_vars({}) only %}{% block footer %}Cancel{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_named_slot_with_default_content(): void
    {
        $result = $this->preLexer->transform('<twig:modal><twig:block name="footer">Cancel</twig:block>Sure?</twig:modal>');

        self::assertSame(
            "{% embed '@components/Modal.html.twig' with component_embed_vars({}) only %}{% block footer %}Cancel{% endblock %}{% block content %}Sure?{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_default_content_before_named_slot(): void
    {
        // Default block must be closed before the named slot opens
        $result = $this->preLexer->transform('<twig:modal>Sure?<twig:block name="footer">Cancel</twig:block></twig:modal>');

        self::assertSame(
            "{% embed '@components/Modal.html.twig' with component_embed_vars({}) only %}{% block content %}Sure?{% endblock %}{% block footer %}Cancel{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_nested_self_closing_inside_non_self_closing(): void
    {
        $result = $this->preLexer->transform('<twig:card><twig:alert message="Hi" /></twig:card>');

        self::assertSame(
            "{% embed '@components/Card.html.twig' with component_embed_vars({}) only %}{% block content %}{{ component('alert', { message: 'Hi' }) }}{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_mismatched_closing_tag_throws(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/Expected.*card.*found.*alert/');

        $this->preLexer->transform('<twig:card><p>hello</p></twig:alert>');
    }

    public function test_unclosed_component_throws(): void
    {
        $this->expectException(SyntaxError::class);
        $this->expectExceptionMessageMatches('/closing tag.*card.*end of input/');

        $this->preLexer->transform('<twig:card>hello');
    }

    public function test_block_at_top_level_becomes_block_definition(): void
    {
        // In a component template: <twig:block name="content"> → {% block content %}...{% endblock %}
        $result = $this->preLexer->transform('<div><twig:block name="content"></twig:block></div>');

        self::assertSame('<div>{% block content %}{% endblock %}</div>', $result);
    }

    public function test_block_at_top_level_with_default_content(): void
    {
        $result = $this->preLexer->transform('<twig:block name="title">Default title</twig:block>');

        self::assertSame('{% block title %}Default title{% endblock %}', $result);
    }

    // --- dynamic props ---

    public function test_dynamic_prop(): void
    {
        $result = $this->preLexer->transform('<twig:button :href="url" />');

        self::assertSame("{{ component('button', { href: url }) }}", $result);
    }

    public function test_dynamic_prop_with_expression(): void
    {
        $result = $this->preLexer->transform('<twig:button :href="path(\'route\', {id: item.id})" />');

        self::assertSame("{{ component('button', { href: path('route', {id: item.id}) }) }}", $result);
    }

    public function test_mixed_static_and_dynamic_props(): void
    {
        $result = $this->preLexer->transform('<twig:button variant="primary" :href="url" />');

        self::assertSame("{{ component('button', { variant: 'primary', href: url }) }}", $result);
    }

    public function test_dynamic_prop_shorthand(): void
    {
        // :foo alone passes the variable named foo — equivalent to :foo="foo"
        $result = $this->preLexer->transform('<twig:button :disabled />');

        self::assertSame("{{ component('button', { disabled: disabled }) }}", $result);
    }

    // --- boolean props ---

    public function test_boolean_prop(): void
    {
        $result = $this->preLexer->transform('<twig:input disabled />');

        self::assertSame("{{ component('input', { disabled: true }) }}", $result);
    }

    public function test_multiple_boolean_props(): void
    {
        $result = $this->preLexer->transform('<twig:input disabled readonly />');

        self::assertSame("{{ component('input', { disabled: true, readonly: true }) }}", $result);
    }

    public function test_boolean_mixed_with_other_props(): void
    {
        $result = $this->preLexer->transform('<twig:input type="text" disabled :value="val" />');

        self::assertSame("{{ component('input', { type: 'text', disabled: true, value: val }) }}", $result);
    }

    // --- subdirectory components ---

    public function test_namespaced_self_closing(): void
    {
        $result = $this->preLexer->transform('<twig:ui:alert message="Hi" />');

        self::assertSame("{{ component('ui:alert', { message: 'Hi' }) }}", $result);
    }

    public function test_namespaced_non_self_closing(): void
    {
        $result = $this->preLexer->transform('<twig:ui:card><p>content</p></twig:ui:card>');

        self::assertSame(
            "{% embed '@components/Ui/Card.html.twig' with component_embed_vars({}) only %}{% block content %}<p>content</p>{% endblock %}{% endembed %}",
            $result,
        );
    }

    public function test_deeply_namespaced_self_closing(): void
    {
        $result = $this->preLexer->transform('<twig:ui:form:input />');

        self::assertSame("{{ component('ui:form:input', {}) }}", $result);
    }

    public function test_namespaced_kebab_segments(): void
    {
        $result = $this->preLexer->transform('<twig:my-ui:form-input />');

        self::assertSame("{{ component('my-ui:form-input', {}) }}", $result);
    }
}
