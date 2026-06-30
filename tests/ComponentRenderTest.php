<?php

declare(strict_types=1);

namespace TwigComponents\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use TwigComponents\ComponentConfig;
use TwigComponents\ComponentExtension;
use TwigComponents\ComponentRenderer;
use TwigComponents\PreLexer;
use TwigComponents\PreLexerLoader;

final class ComponentRenderTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $fixturesDir = __DIR__ . '/fixtures';

        $loader = new FilesystemLoader($fixturesDir . '/templates');
        $loader->addPath($fixturesDir . '/components', 'components');

        $preLexerLoader = new PreLexerLoader($loader, new PreLexer());

        $this->twig = new Environment($preLexerLoader, ['debug' => true, 'cache' => false]);
        ComponentExtension::register($this->twig, new ComponentRenderer(new ComponentConfig()));
    }

    public function test_self_closing_component_renders_template(): void
    {
        $result = $this->twig->render('page.html.twig');

        self::assertStringContainsString('<div class="alert">Saved!</div>', $result);
    }

    public function test_missing_component_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/Component 'ghost'/");

        $renderer = new ComponentRenderer(new ComponentConfig());
        $renderer->render($this->twig, 'ghost', []);
    }

    public function test_prop_is_passed_as_template_variable(): void
    {
        $renderer = new ComponentRenderer(new ComponentConfig());
        $result = $renderer->render($this->twig, 'alert', ['message' => 'Direct call']);

        self::assertStringContainsString('<div class="alert">Direct call</div>', (string) $result);
    }

    public function test_non_self_closing_component_renders_content(): void
    {
        $result = $this->twig->render('card-page.html.twig');

        self::assertStringContainsString('<div class="card"><p>inner content</p></div>', $result);
    }

    public function test_named_slot_and_default_content_render(): void
    {
        $result = $this->twig->render('modal-page.html.twig');

        self::assertStringContainsString('<div class="body">Sure?</div>', $result);
        self::assertStringContainsString('<div class="footer">Cancel</div>', $result);
    }

    public function test_kebab_name_resolves_to_pascal_file(): void
    {
        $renderer = new ComponentRenderer(new ComponentConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/@components\/MyAlert\.html\.twig/");

        $renderer->render($this->twig, 'my-alert', []);
    }

    public function test_attrs_rendered_on_self_closing(): void
    {
        $result = $this->twig->render('input-page.html.twig');

        self::assertStringContainsString('type="text"', $result);
        self::assertStringContainsString('class="form-control"', $result);
    }

    public function test_attrs_rendered_on_non_self_closing(): void
    {
        $result = $this->twig->render('card-attrs-page.html.twig');

        self::assertStringContainsString('id="main"', $result);
        self::assertStringContainsString('class="highlight"', $result);
    }

    public function test_namespaced_component_resolves_to_subdirectory(): void
    {
        $result = $this->twig->render('ui-alert-page.html.twig');

        self::assertSame('<div class="ui-alert">Hello!</div>', trim($result));
    }

    // --- context opt-in ---

    public function test_context_flag_exposes_parent_scope_self_closing(): void
    {
        $result = $this->twig->render('context-probe-page.html.twig', ['foo' => 'page-foo']);

        self::assertStringContainsString('foo=page-foo', $result);
    }

    public function test_context_is_stripped_from_attrs_bag(): void
    {
        $result = $this->twig->render('context-probe-page.html.twig', ['foo' => 'page-foo']);

        // The real attr survives, the reserved `context` key never leaks into {{ attrs }}.
        self::assertStringContainsString('attrs=[ class="x"]', $result);
        self::assertStringNotContainsString('context=', $result);
    }

    public function test_context_flag_exposes_parent_scope_slotted(): void
    {
        $result = $this->twig->render('context-card-page.html.twig', ['foo' => 'embed-foo']);

        self::assertStringContainsString('<p>hi</p>', $result);
        self::assertStringContainsString('foo=embed-foo', $result);
    }

    public function test_context_bag_is_flat_and_nearer_scope_wins_at_depth(): void
    {
        $result = $this->twig->render('context-depth-page.html.twig', [
            'g' => 'from-G',
            'shared' => 'G-shared',
        ]);

        // grandparent value merged up, parent value present, nearer wins on collision,
        // and the bag never nests (no context.context).
        self::assertSame('g=from-G;p=from-P;shared=P-shared;nested=no', trim($result));
    }
}
