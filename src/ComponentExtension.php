<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Environment;
use Twig\Error\RuntimeError;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\Runtime\EscaperRuntime;
use Twig\TwigFunction;

final class ComponentExtension extends AbstractExtension
{
    public function __construct(private readonly ComponentRenderer $renderer)
    {
    }

    /**
     * Preferred entry point. Registers the extension and marks ComponentAttributes
     * as HTML-safe so {{ attrs }} works without |raw.
     * @throws RuntimeError
     */
    public static function register(Environment $twig, ComponentRenderer $renderer): void
    {
        $twig->addExtension(new self($renderer));
        $twig->getRuntime(EscaperRuntime::class)->addSafeClass(ComponentAttributes::class, ['html']);
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'component',
                fn (Environment $env, string $name, array $props = []): Markup => $this->renderer->render($env, $name, $props),
                ['needs_environment' => true, 'is_safe' => ['html']],
            ),
            new TwigFunction(
                'component_embed_vars',
                static fn (array $props): array => array_merge($props, ['attrs' => new ComponentAttributes($props)]),
            ),
        ];
    }
}
