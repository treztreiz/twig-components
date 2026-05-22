<?php

declare(strict_types=1);

namespace TwigComponents;

use Twig\Loader\LoaderInterface;
use Twig\Source;

/**
 * Loader decorator that runs the PreLexer on every template source before
 * Twig tokenises it. All other loader responsibilities are delegated.
 */
final class PreLexerLoader implements LoaderInterface
{
    public function __construct(
        private readonly LoaderInterface $inner,
        private readonly PreLexer $preLexer,
    ) {}

    public function getSourceContext(string $name): Source
    {
        $source = $this->inner->getSourceContext($name);
        $transformed = $this->preLexer->transform($source->getCode());

        return new Source($transformed, $source->getName(), $source->getPath());
    }

    public function getCacheKey(string $name): string
    {
        return $this->inner->getCacheKey($name);
    }

    public function isFresh(string $name, int $time): bool
    {
        return $this->inner->isFresh($name, $time);
    }

    public function exists(string $name): bool
    {
        return $this->inner->exists($name);
    }
}
