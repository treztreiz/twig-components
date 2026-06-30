<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Markup;

final readonly class ComponentRenderer
{
    public function __construct(private ComponentConfig $config)
    {
    }

    /**
     * @param array<string, mixed> $props
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function render(Environment $twig, string $name, array $props = []): Markup
    {
        $templateName = $this->resolveTemplateName($name);

        if (!$twig->getLoader()->exists($templateName)) {
            throw new \RuntimeException(
                sprintf("Component '%s': template '%s' not found.", $name, $templateName),
            );
        }

        return new Markup($twig->render($templateName, self::prepareProps($props)), 'UTF-8');
    }

    /**
     * Builds the final component context from raw props: flattens an opted-in
     * `context` bag (one level deep, nearer scope wins) and attaches the `attrs`
     * bag. Shared by the component() render path and component_embed_vars() so
     * both forms behave identically.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    public static function prepareProps(array $props): array
    {
        if (array_key_exists('context', $props) && \is_array($props['context'])) {
            $parent = $props['context'];
            $inherited = isset($parent['context']) && \is_array($parent['context']) ? $parent['context'] : [];
            unset($parent['context']);
            $props['context'] = array_merge($inherited, $parent);
        }

        // `context` is not an HTML attribute and must never leak into {{ attrs }}.
        $attrsSource = $props;
        unset($attrsSource['context']);
        $props['attrs'] = new ComponentAttributes($attrsSource);

        return $props;
    }

    private function resolveTemplateName(string $name): string
    {
        $path = implode('/', array_map(
            static fn (string $s) => str_replace(' ', '', ucwords(str_replace('-', ' ', $s))),
            explode(':', $name),
        ));

        return '@' . $this->config->loaderNamespace . '/' . $path . $this->config->templateExtension;
    }
}
