<?php

declare(strict_types=1);

namespace TwigComponents\Twig;

use Twig\Compiler;
use Twig\Node\Node;

/**
 * Compiles {% props %} into PHP that:
 *   1. Applies defaults for optional props
 *   2. Throws RuntimeError for missing required props
 *   3. Strips declared prop names from the attrs bag
 */
final class PropsNode extends Node
{
    /**
     * @param array<array{name: string, hasDefault: bool}> $propDefs
     * @param array<string, Node> $defaultNodes
     */
    public function __construct(array $propDefs, array $defaultNodes, int $line)
    {
        parent::__construct($defaultNodes, ['props' => $propDefs], $line);
    }

    public function compile(Compiler $compiler): void
    {
        /** @var array<array{name: string, hasDefault: bool}> $props */
        $props = $this->getAttribute('props');

        foreach ($props as $prop) {
            $name = $prop['name'];
            $quoted = var_export($name, true);

            $compiler->addDebugInfo($this);

            if ($prop['hasDefault']) {
                $compiler
                    ->write("if (!array_key_exists($quoted, \$context)) {\n")
                    ->indent()
                    ->write("\$context[$quoted] = ");
                $compiler->subcompile($this->getNode($name));
                $compiler
                    ->raw(";\n")
                    ->outdent()
                    ->write("}\n");
            } else {
                $compiler
                    ->write("if (!array_key_exists($quoted, \$context)) {\n")
                    ->indent()
                    ->write('throw new \Twig\Error\RuntimeError(')
                    ->string("Prop '$name' is required.")
                    ->raw(", -1, \$this->getSourceContext());\n")
                    ->outdent()
                    ->write("}\n");
            }
        }

        $propNames = array_column($props, 'name');

        if ($propNames !== []) {
            $args = implode(', ', array_map(static fn (string $n) => var_export($n, true), $propNames));
            $compiler
                ->addDebugInfo($this)
                ->write("if (isset(\$context['attrs']) && \$context['attrs'] instanceof \\TwigComponents\\ComponentAttributes) {\n")
                ->indent()
                ->write("\$context['attrs'] = \$context['attrs']->without($args);\n")
                ->outdent()
                ->write("}\n");
        }
    }
}
