<?php

declare(strict_types=1);

namespace TwigComponents\Twig;

use Twig\Node\Node;
use Twig\Token;
use Twig\TokenParser\AbstractTokenParser;

/**
 * Parses {% props name, other = 'default' %} tags in component templates.
 *
 * Declared props are extracted as template variables and stripped from the
 * attrs bag, leaving only non-prop HTML attributes for passthrough.
 */
final class PropsTokenParser extends AbstractTokenParser
{
    public function parse(Token $token): Node
    {
        $stream = $this->parser->getStream();
        $line = $token->getLine();
        $propDefs = [];
        $defaultNodes = [];

        while (!$stream->test(Token::BLOCK_END_TYPE)) {
            $name = $stream->expect(Token::NAME_TYPE)->getValue();

            if ($stream->nextIf(Token::OPERATOR_TYPE, '=')) {
                $defaultNodes[$name] = $this->parser->parseExpression();
                $propDefs[] = ['name' => $name, 'hasDefault' => true];
            } else {
                $propDefs[] = ['name' => $name, 'hasDefault' => false];
            }

            $stream->nextIf(Token::PUNCTUATION_TYPE, ',');
        }

        $stream->expect(Token::BLOCK_END_TYPE);

        return new PropsNode($propDefs, $defaultNodes, $line);
    }

    public function getTag(): string
    {
        return 'props';
    }
}
