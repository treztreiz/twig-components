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
}
