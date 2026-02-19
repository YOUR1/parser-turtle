<?php

declare(strict_types=1);

namespace Youri\vandenBogert\Software\ParserTurtle;

use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use Youri\vandenBogert\Software\ParserCore\Contracts\RdfFormatHandlerInterface;
use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;

/**
 * Handler for Turtle (RDF 1.1) format parsing.
 *
 * Detects and parses Turtle content using EasyRdf, returning a ParsedRdf
 * value object. Registers prefix declarations in EasyRdf's global namespace
 * registry before parsing to ensure proper IRI resolution.
 */
final class TurtleHandler implements RdfFormatHandlerInterface
{
    public function canHandle(string $content): bool
    {
        $trimmed = trim($content);

        return str_starts_with($trimmed, '@prefix') ||
               str_contains($trimmed, '@prefix') ||
               str_contains($trimmed, 'PREFIX');
    }

    public function parse(string $content): ParsedRdf
    {
        try {
            $this->registerPrefixesFromContent($content);

            $graph = new Graph();
            $graph->parse($content, 'turtle');

            $metadata = [
                'parser' => 'turtle_handler',
                'format' => 'turtle',
                'resource_count' => count($graph->resources()),
            ];

            return new ParsedRdf(
                graph: $graph,
                format: 'turtle',
                rawContent: $content,
                metadata: $metadata,
            );
        } catch (\Throwable $e) {
            throw new ParseException('Turtle parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getFormatName(): string
    {
        return 'turtle';
    }

    /**
     * Register prefixes from Turtle content in EasyRdf's global namespace registry.
     */
    private function registerPrefixesFromContent(string $content): void
    {
        if (preg_match_all('/@prefix\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if ($prefix !== '' && $namespace !== '') {
                    RdfNamespace::set($prefix, $namespace);
                }
            }
        }
    }
}
