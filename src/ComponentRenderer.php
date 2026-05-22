<?php

declare(strict_types=1);

namespace TwigComponents;

use RuntimeException;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Markup;

final readonly class ComponentRenderer
{
    public function __construct(private ComponentConfig $config) {}

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function render(Environment $twig, string $name, array $props = []): Markup
    {
        $templateName = $this->resolveTemplateName($name);

        if (!$twig->getLoader()->exists($templateName)) {
            throw new RuntimeException(
                sprintf("Component '%s': template '%s' not found.", $name, $templateName)
            );
        }

        return new Markup($twig->render($templateName, $props), 'UTF-8');
    }

    private function resolveTemplateName(string $name): string
    {
        // kebab-case → PascalCase: my-card → MyCard
        $pascalCaseName = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));

        return '@' . $this->config->loaderNamespace . '/' . $pascalCaseName . $this->config->templateExtension;
    }
}
