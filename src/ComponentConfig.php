<?php

declare(strict_types=1);

namespace TwigComponents;

/**
 * Holds configuration shared across the component system.
 * The host project is responsible for adding $loaderNamespace to its FilesystemLoader.
 */
final readonly class ComponentConfig
{
    public function __construct(
        public string $templateExtension = '.html.twig',
        public string $loaderNamespace = 'components',
    ) {}
}
