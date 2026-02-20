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

        if ($trimmed === '') {
            return false;
        }

        // Detect @prefix or PREFIX directives (existing behavior)
        if (str_contains($trimmed, '@prefix') || str_contains($trimmed, 'PREFIX')) {
            return true;
        }

        // Detect @base or BASE directives
        if (str_contains($trimmed, '@base') || preg_match('/^BASE\s+</m', $trimmed) === 1) {
            return true;
        }

        // Detect Turtle-specific syntax features in content with full IRIs:
        // - 'a' keyword as predicate shorthand for rdf:type
        // - Semicolons (;) for predicate-object lists
        // - Commas (,) for object lists
        // - Blank node property lists ([...])
        // These features distinguish Turtle from N-Triples (which is line-based only)
        if ($this->hasTurtleSpecificFeatures($trimmed)) {
            return true;
        }

        return false;
    }

    /**
     * Check if content contains Turtle-specific syntax features that distinguish it from N-Triples.
     *
     * N-Triples is a strict subset of Turtle. Features unique to Turtle include:
     * - The 'a' keyword as shorthand for rdf:type
     * - Semicolons (;) for predicate-object lists
     * - Commas (,) for object lists within triple context
     * - Prefixed names (already handled by @prefix/PREFIX detection)
     */
    private function hasTurtleSpecificFeatures(string $content): bool
    {
        // Detect 'a' keyword as predicate (Turtle shorthand for rdf:type)
        // Pattern: <IRI> a <IRI> or <IRI> a prefix:name
        if (preg_match('/^<[^>]+>\s+a\s+/m', $content) === 1) {
            return true;
        }

        // Detect IRI triples with semicolons (predicate-object lists, Turtle-only)
        // Must start with a subject IRI to avoid matching semicolons in other formats
        if (preg_match('/^<[^>]+>\s+<[^>]+>\s+.+\s*;\s*$/m', $content) === 1) {
            return true;
        }

        // Detect IRI triples with commas (object lists, Turtle-only)
        // Must be within a triple context: <subject> <predicate> <obj1> , <obj2>
        if (preg_match('/^<[^>]+>\s+<[^>]+>\s+.+\s*,\s*/m', $content) === 1) {
            return true;
        }

        return false;
    }

    public function parse(string $content): ParsedRdf
    {
        try {
            $this->validateContent($content);
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
        } catch (ParseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new ParseException('Turtle parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Pre-parse validation of Turtle content.
     *
     * Validates IRIs and string literal escape sequences before delegating to EasyRdf,
     * catching errors that EasyRdf's permissive parsing would silently accept.
     *
     * @throws ParseException
     */
    private function validateContent(string $content): void
    {
        $this->validateIRIs($content);
        $this->validateStringEscapes($content);
    }

    /**
     * Validate IRI contents in angle brackets.
     *
     * Per W3C Turtle spec, IRIs must not contain spaces and only allow \uXXXX and \UXXXXXXXX
     * escape sequences. Surrogate codepoints (U+D800..U+DFFF) are not valid.
     *
     * @throws ParseException
     */
    private function validateIRIs(string $content): void
    {
        $inString = false;
        $inLongString = false;
        $stringQuote = '';
        $escaped = false;
        $inIRI = false;
        $iriContent = '';
        $len = \strlen($content);

        for ($i = 0; $i < $len; $i++) {
            $char = $content[$i];

            if ($escaped) {
                $escaped = false;

                continue;
            }

            // Handle long string detection (""" or ''')
            if (!$inIRI && !$inString && $i + 2 < $len) {
                $three = substr($content, $i, 3);
                if ($three === '"""' || $three === "'''") {
                    $inString = true;
                    $inLongString = true;
                    $stringQuote = $three;
                    $i += 2;

                    continue;
                }
            }

            // Handle long string end
            if ($inLongString && $i + 2 < $len && substr($content, $i, 3) === $stringQuote) {
                $inString = false;
                $inLongString = false;
                $i += 2;

                continue;
            }

            // Handle short string detection
            if (!$inIRI && !$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringQuote = $char;

                continue;
            }

            // Handle short string end
            if ($inString && !$inLongString && $char === $stringQuote) {
                $inString = false;

                continue;
            }

            // Handle escape in strings
            if ($inString && $char === '\\') {
                $escaped = true;

                continue;
            }

            // Handle comment (# outside strings and IRIs)
            if (!$inString && !$inIRI && $char === '#') {
                while ($i < $len && $content[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            // Handle IRI start
            if (!$inString && $char === '<') {
                $inIRI = true;
                $iriContent = '';

                continue;
            }

            // Handle IRI end — validate accumulated content
            if ($inIRI && $char === '>') {
                $this->validateSingleIRI($iriContent);
                $inIRI = false;

                continue;
            }

            if ($inIRI) {
                $iriContent .= $char;
            }
        }
    }

    /**
     * Validate a single IRI's content.
     *
     * @throws ParseException
     */
    private function validateSingleIRI(string $iri): void
    {
        // Check for spaces
        if (preg_match('/\s/', $iri) === 1) {
            throw new ParseException('Turtle parsing failed: IRI contains whitespace');
        }

        // Validate escape sequences — only \uXXXX and \UXXXXXXXX allowed in IRIs
        $offset = 0;
        while (($pos = strpos($iri, '\\', $offset)) !== false) {
            $nextChar = $iri[$pos + 1] ?? '';

            if ($nextChar === 'u') {
                $hex = substr($iri, $pos + 2, 4);
                if (\strlen($hex) < 4 || !ctype_xdigit($hex)) {
                    throw new ParseException('Turtle parsing failed: invalid \\u escape in IRI');
                }
                $codepoint = hexdec($hex);
                if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
                    throw new ParseException('Turtle parsing failed: surrogate codepoint in IRI');
                }
                $offset = $pos + 6;
            } elseif ($nextChar === 'U') {
                $hex = substr($iri, $pos + 2, 8);
                if (\strlen($hex) < 8 || !ctype_xdigit($hex)) {
                    throw new ParseException('Turtle parsing failed: invalid \\U escape in IRI');
                }
                $codepoint = hexdec($hex);
                if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
                    throw new ParseException('Turtle parsing failed: surrogate codepoint in IRI');
                }
                $offset = $pos + 10;
            } else {
                throw new ParseException('Turtle parsing failed: only \\u and \\U escapes are allowed in IRIs');
            }
        }
    }

    /**
     * Validate escape sequences in string literals.
     *
     * Checks all string literals for valid escape sequences per the Turtle spec.
     * Valid escapes: \t, \b, \n, \r, \f, \", \\, \', \uXXXX, \UXXXXXXXX.
     * Surrogate codepoints (U+D800..U+DFFF) are not valid.
     *
     * @throws ParseException
     */
    private function validateStringEscapes(string $content): void
    {
        $validSimpleEscapes = ['t', 'b', 'n', 'r', 'f', '"', '\\', "'"];
        $len = \strlen($content);
        $inString = false;
        $inLongString = false;
        $stringQuote = '';
        $inIRI = false;

        for ($i = 0; $i < $len; $i++) {
            $char = $content[$i];

            // Skip IRIs
            if (!$inString && $char === '<') {
                $inIRI = true;

                continue;
            }
            if ($inIRI && $char === '>') {
                $inIRI = false;

                continue;
            }
            if ($inIRI) {
                continue;
            }

            // Handle comment
            if (!$inString && $char === '#') {
                while ($i < $len && $content[$i] !== "\n") {
                    $i++;
                }

                continue;
            }

            // Handle long string detection
            if (!$inString && $i + 2 < $len) {
                $three = substr($content, $i, 3);
                if ($three === '"""' || $three === "'''") {
                    $inString = true;
                    $inLongString = true;
                    $stringQuote = $three;
                    $i += 2;

                    continue;
                }
            }

            // Handle long string end
            if ($inLongString && $i + 2 < $len && substr($content, $i, 3) === $stringQuote) {
                $inString = false;
                $inLongString = false;
                $i += 2;

                continue;
            }

            // Handle prefixed name escapes (backslash outside strings/IRIs)
            // In Turtle, prefixed local names can contain escape sequences like \' \" \~ etc.
            // These must not be confused with string delimiters.
            if (!$inString && $char === '\\' && $i + 1 < $len) {
                $i++; // Skip the escaped character

                continue;
            }

            // Handle short string detection
            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringQuote = $char;

                continue;
            }

            // Handle short string end
            if ($inString && !$inLongString && $char === $stringQuote) {
                $inString = false;

                continue;
            }

            // Validate escape sequences inside strings
            if ($inString && $char === '\\') {
                $nextChar = $content[$i + 1] ?? '';

                if (\in_array($nextChar, $validSimpleEscapes, true)) {
                    $i++;

                    continue;
                }

                if ($nextChar === 'u') {
                    $hex = substr($content, $i + 2, 4);
                    if (\strlen($hex) < 4 || !ctype_xdigit($hex)) {
                        throw new ParseException('Turtle parsing failed: invalid \\u escape in string literal');
                    }
                    $codepoint = hexdec($hex);
                    if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
                        throw new ParseException('Turtle parsing failed: surrogate codepoint in string literal');
                    }
                    $i += 5;

                    continue;
                }

                if ($nextChar === 'U') {
                    $hex = substr($content, $i + 2, 8);
                    if (\strlen($hex) < 8 || !ctype_xdigit($hex)) {
                        throw new ParseException('Turtle parsing failed: invalid \\U escape in string literal');
                    }
                    $codepoint = hexdec($hex);
                    if ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) {
                        throw new ParseException('Turtle parsing failed: surrogate codepoint in string literal');
                    }
                    $i += 9;

                    continue;
                }

                throw new ParseException("Turtle parsing failed: invalid escape sequence '\\{$nextChar}' in string literal");
            }
        }
    }

    public function getFormatName(): string
    {
        return 'turtle';
    }

    /**
     * Register prefixes from Turtle content in EasyRdf's global namespace registry.
     *
     * Strips comments and string literals before matching to avoid false positives
     * when @prefix appears inside comments or quoted strings.
     */
    private function registerPrefixesFromContent(string $content): void
    {
        $cleaned = $this->stripCommentsAndStrings($content);

        if (preg_match_all('/@prefix\s+([^:]+):\s*<([^>]+)>/i', $cleaned, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $prefix = trim($match[1]);
                $namespace = trim($match[2]);
                if ($prefix !== '' && $namespace !== '') {
                    RdfNamespace::set($prefix, $namespace);
                }
            }
        }
    }

    /**
     * Strip Turtle comments and string literals from content.
     *
     * Comments start with # (outside strings and IRIs) and extend to end of line.
     * String literals use "...", '...', """...""", or '''...''' delimiters.
     * IRIs are enclosed in <...> and may contain # characters.
     * Returns content with comments and string literal contents replaced by spaces.
     */
    private function stripCommentsAndStrings(string $content): string
    {
        $result = '';
        $len = \strlen($content);
        $i = 0;

        while ($i < $len) {
            $char = $content[$i];

            // Handle IRIs (<...>) — # inside IRIs is not a comment start
            if ($char === '<') {
                $result .= $char;
                $i++;
                while ($i < $len && $content[$i] !== '>') {
                    $result .= $content[$i];
                    $i++;
                }
                if ($i < $len) {
                    $result .= $content[$i]; // closing >
                    $i++;
                }

                continue;
            }

            // Handle triple-quoted strings (""" or ''')
            if ($i + 2 < $len && $content[$i] === '"' && $content[$i + 1] === '"' && $content[$i + 2] === '"') {
                $result .= '   ';
                $i += 3;
                while ($i < $len) {
                    if ($i + 2 < $len && $content[$i] === '"' && $content[$i + 1] === '"' && $content[$i + 2] === '"') {
                        $result .= '   ';
                        $i += 3;
                        break;
                    }
                    $result .= ' ';
                    $i++;
                }

                continue;
            }

            if ($i + 2 < $len && $content[$i] === "'" && $content[$i + 1] === "'" && $content[$i + 2] === "'") {
                $result .= '   ';
                $i += 3;
                while ($i < $len) {
                    if ($i + 2 < $len && $content[$i] === "'" && $content[$i + 1] === "'" && $content[$i + 2] === "'") {
                        $result .= '   ';
                        $i += 3;
                        break;
                    }
                    $result .= ' ';
                    $i++;
                }

                continue;
            }

            // Handle single/double-quoted strings (" or ')
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $result .= ' ';
                $i++;
                while ($i < $len && $content[$i] !== $quote) {
                    if ($content[$i] === '\\' && $i + 1 < $len) {
                        $result .= '  ';
                        $i += 2;

                        continue;
                    }
                    if ($content[$i] === "\n") {
                        // Newline inside a non-long string: treat as end of string
                        break;
                    }
                    $result .= ' ';
                    $i++;
                }
                if ($i < $len && $content[$i] === $quote) {
                    $result .= ' ';
                    $i++;
                }

                continue;
            }

            // Handle comments (# outside strings and IRIs, to end of line)
            if ($char === '#') {
                while ($i < $len && $content[$i] !== "\n") {
                    $result .= ' ';
                    $i++;
                }

                continue;
            }

            $result .= $char;
            $i++;
        }

        return $result;
    }
}
