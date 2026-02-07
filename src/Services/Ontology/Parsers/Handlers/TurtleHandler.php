<?php

namespace App\Services\Ontology\Parsers\Handlers;

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Contracts\RdfFormatHandlerInterface;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;
use EasyRdf\Graph;

/**
 * Handler for Turtle format parsing
 */
class TurtleHandler implements RdfFormatHandlerInterface
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
            // Extract and register prefixes from content BEFORE parsing
            // This ensures proper IRI shortening during extraction
            $this->registerPrefixesFromContent($content);

            $graph = new Graph;
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
                metadata: $metadata
            );

        } catch (\Throwable $e) {
            throw new OntologyImportException('Turtle parsing failed: '.$e->getMessage(), 0, $e);
        }
    }

    /**
     * Register prefixes from Turtle content in EasyRDF's global namespace registry
     */
    private function registerPrefixesFromContent(string $content): void
    {
        // Extract @prefix declarations from content
        if (preg_match_all('/@prefix\s+([^:]+):\s*<([^>]+)>/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if (! empty($prefix) && ! empty($namespace)) {
                    \EasyRdf\RdfNamespace::set($prefix, $namespace);
                }
            }
        }
    }

    public function getFormatName(): string
    {
        return 'turtle';
    }
}
