<?php

declare(strict_types=1);

namespace TwigComponents\Tests;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Loader\FilesystemLoader;
use TwigComponents\ComponentConfig;
use TwigComponents\ComponentExtension;
use TwigComponents\ComponentRenderer;
use TwigComponents\PreLexer;
use TwigComponents\PreLexerLoader;

final class PropsTest extends TestCase
{
    private Environment $twig;

    protected function setUp(): void
    {
        $config = new ComponentConfig();
        $inner = new FilesystemLoader([__DIR__ . '/fixtures/templates']);
        $inner->addPath(__DIR__ . '/fixtures/components', 'components');
        $loader = new PreLexerLoader($inner, new PreLexer($config));
        $this->twig = new Environment($loader, ['cache' => false]);
        ComponentExtension::register($this->twig, new ComponentRenderer($config));
    }

    public function test_props_become_variables(): void
    {
        $result = $this->twig->render('button-page.html.twig');

        self::assertStringContainsString('btn--danger', $result);
        self::assertStringContainsString('Submit', $result);
    }

    public function test_props_are_stripped_from_attrs(): void
    {
        $result = $this->twig->render('button-page.html.twig');

        self::assertStringContainsString('id="btn-submit"', $result);
        self::assertStringNotContainsString('label=', $result);
        self::assertStringNotContainsString('variant=', $result);
    }

    public function test_default_prop_is_applied_when_not_passed(): void
    {
        $result = $this->twig->render('button-default-page.html.twig');

        self::assertStringContainsString('btn--primary', $result);
    }

    public function test_required_prop_missing_throws(): void
    {
        $this->expectException(RuntimeError::class);
        $this->expectExceptionMessageMatches('/label.*required/i');

        $this->twig->render('button-missing-label-page.html.twig');
    }
}
