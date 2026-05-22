<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Environment;
use Twig\Markup;

final readonly class ComponentRenderer
{
    public function __construct(private ComponentConfig $config) {}

    public function render(Environment $twig, string $name, array $props = []): Markup
    {
        $templateName = $this->resolveTemplateName($name);

        if (!$twig->getLoader()->exists($templateName)) {
            throw new \RuntimeException(
                sprintf("Component '%s': template '%s' not found.", $name, $templateName)
            );
        }

        return new Markup($twig->render($templateName, $props), 'UTF-8');
    }

    private function resolveTemplateName(string $name): string
    {
        // kebab-case → PascalCase: my-card → MyCard
        $pascal = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

        return '@' . $this->config->loaderNamespace . '/' . $pascal . $this->config->templateExtension;
    }
}
