<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

final class ComponentExtension extends AbstractExtension
{
    public function __construct(private readonly ComponentRenderer $renderer) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'component',
                fn(Environment $env, string $name, array $props = []): Markup => $this->renderer->render($env, $name, $props),
                ['needs_environment' => true, 'is_safe' => ['html']],
            ),
        ];
    }
}
