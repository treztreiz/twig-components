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

    public function testSelfClosingComponentRendersTemplate(): void
    {
        $result = $this->twig->render('page.html.twig');

        $this->assertStringContainsString('<div class="alert">Saved!</div>', $result);
    }

    public function testMissingComponentThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/Component 'ghost'/");

        $renderer = new ComponentRenderer(new ComponentConfig());
        $renderer->render($this->twig, 'ghost', []);
    }

    public function testPropIsPassedAsTemplateVariable(): void
    {
        $renderer = new ComponentRenderer(new ComponentConfig());
        $result = $renderer->render($this->twig, 'alert', ['message' => 'Direct call']);

        $this->assertStringContainsString('<div class="alert">Direct call</div>', (string) $result);
    }

    public function testNonSelfClosingComponentRendersContent(): void
    {
        $result = $this->twig->render('card-page.html.twig');

        $this->assertStringContainsString('<div class="card"><p>inner content</p></div>', $result);
    }

    public function testNamedSlotAndDefaultContentRender(): void
    {
        $result = $this->twig->render('modal-page.html.twig');

        $this->assertStringContainsString('<div class="body">Sure?</div>', $result);
        $this->assertStringContainsString('<div class="footer">Cancel</div>', $result);
    }

    public function testKebabNameResolvesToPascalFile(): void
    {
        $renderer = new ComponentRenderer(new ComponentConfig());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches("/@components\/MyAlert\.html\.twig/");

        $renderer->render($this->twig, 'my-alert', []);
    }

    public function testAttrsRenderedOnSelfClosing(): void
    {
        $result = $this->twig->render('input-page.html.twig');

        $this->assertStringContainsString('type="text"', $result);
        $this->assertStringContainsString('class="form-control"', $result);
    }

    public function testAttrsRenderedOnNonSelfClosing(): void
    {
        $result = $this->twig->render('card-attrs-page.html.twig');

        $this->assertStringContainsString('id="main"', $result);
        $this->assertStringContainsString('class="highlight"', $result);
    }
}
