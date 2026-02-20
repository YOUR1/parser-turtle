<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserTurtle\TurtleHandler;

beforeEach(function () {
    $this->handler = new TurtleHandler();
});

it('detects turtle with prefix syntax', function () {
    expect($this->handler->canHandle('@prefix ex: <http://example.org/> .'))->toBeTrue();
});

it('returns turtle format name', function () {
    expect($this->handler->getFormatName())->toBe('turtle');
});

it('parses valid turtle', function () {
    $ttl = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> . <http://example.org/A> a rdfs:Class .';
    $parsed = $this->handler->parse($ttl);
    expect($parsed)->toBeInstanceOf(ParsedRdf::class);
    expect($parsed->format)->toBe('turtle');
});

it('throws on invalid turtle', function () {
    expect(fn () => $this->handler->parse('@prefix invalid'))->toThrow(ParseException::class);
});

// ============================================================
// Story 9-1: canHandle() detection gap tests
// ============================================================

describe('canHandle() detection gaps (Story 9-1)', function () {
    // AC 1: @base-only Turtle content returns true
    it('detects @base-only Turtle content', function () {
        $content = '@base <http://example.org/> .
<s> <p> <o> .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects @base with full-IRI triples', function () {
        $content = '@base <http://example.org/> .
<http://example.org/s> <http://example.org/p> <http://example.org/o> .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects SPARQL-style BASE directive', function () {
        $content = 'BASE <http://example.org/>
<s> <p> <o> .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // AC 2: full-IRI-only Turtle content with Turtle-specific features returns true
    it('detects full-IRI-only Turtle content with a keyword (rdf:type shorthand)', function () {
        $content = '<http://example.org/Person> a <http://www.w3.org/2002/07/owl#Class> .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects full-IRI-only Turtle content with semicolons (predicate-object lists)', function () {
        $content = '<http://example.org/s> <http://example.org/p1> <http://example.org/o1> ;
    <http://example.org/p2> <http://example.org/o2> .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects Turtle content with commas (object lists)', function () {
        $content = '<http://example.org/s> <http://example.org/p> <http://example.org/o1> , <http://example.org/o2> .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    it('detects Turtle content with a keyword and multiple predicates', function () {
        $content = '<http://example.org/Person> a <http://www.w3.org/2002/07/owl#Class> ;
    <http://www.w3.org/2000/01/rdf-schema#label> "Person" .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // AC 3: non-Turtle content returns false
    it('rejects JSON content', function () {
        $content = '{"name": "test", "value": 42}';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects XML content', function () {
        $content = '<?xml version="1.0"?><root><item>test</item></root>';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects plain text content', function () {
        $content = 'This is just plain text without any RDF content.';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects CSV content', function () {
        $content = 'name,age,city
John,30,Amsterdam
Jane,25,Rotterdam';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    it('rejects empty content', function () {
        expect($this->handler->canHandle(''))->toBeFalse();
    });

    it('rejects whitespace-only content', function () {
        expect($this->handler->canHandle("   \n\t  "))->toBeFalse();
    });
});

// ============================================================
// Story 9-2: @prefix regex matching bug tests
// ============================================================

describe('@prefix regex matching bug (Story 9-2)', function () {
    // AC 1: @prefix inside a comment is not treated as real prefix
    it('does not fail when @prefix appears only inside a comment', function () {
        $content = '# Test @prefix and qnames
@prefix :  <http://example.org/base1#> .
@prefix a: <http://example.org/base2#> .
@prefix b: <http://example.org/base3#> .
:a :b :c .
a:a a:b a:c .
:a a:a b:a .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });

    it('correctly registers only real prefixes when comment contains @prefix', function () {
        $content = '# This comment mentions @prefix foo: <http://fake.example.org/> .
@prefix real: <http://real.example.org/> .
<http://real.example.org/Thing> a <http://www.w3.org/2000/01/rdf-schema#Class> .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $namespaces = \EasyRdf\RdfNamespace::namespaces();
        expect($namespaces)->toHaveKey('real');
        // The fake prefix from the comment should not be registered
        expect($namespaces)->not->toHaveKey('foo');
    });

    // AC 2: @prefix inside a string literal is not treated as real prefix
    it('does not treat @prefix inside string literal as real prefix declaration', function () {
        $content = '@prefix ex: <http://example.org/> .
ex:doc ex:content "@prefix fake: <http://fake.example.org/> ." .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $namespaces = \EasyRdf\RdfNamespace::namespaces();
        expect($namespaces)->not->toHaveKey('fake');
    });

    // AC 4: W3C turtle-subm-02 test passes through TurtleHandler
    it('parses W3C turtle-subm-02 fixture through TurtleHandler without error', function () {
        $path = __DIR__ . '/../Fixtures/W3c/turtle-subm-02.ttl';
        if (!file_exists($path)) {
            test()->skip('W3C fixture not available');
        }
        $content = file_get_contents($path);
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });
});

// ============================================================
// Story 9-4: Negative syntax pre-parse validation tests
// ============================================================

describe('pre-parse validation (Story 9-4)', function () {
    // IRI validation: spaces in IRIs
    it('rejects IRIs containing spaces', function () {
        $content = '<http://example.org/ space> <http://example.org/p> <http://example.org/o> .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // IRI validation: bad escape sequences in IRIs
    it('rejects bad character escapes in IRIs (backslash-n)', function () {
        $content = '<http://example.org/\n> <http://example.org/p> <http://example.org/o> .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    it('rejects bad character escapes in IRIs (backslash-slash)', function () {
        $content = '<http://example.org/\/> <http://example.org/p> <http://example.org/o> .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // IRI validation: bad Unicode escapes
    it('rejects bad Unicode escape in IRI (invalid hex digits)', function () {
        $content = '<http://example.org/\u00ZZ11> <http://example.org/p> <http://example.org/o> .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    it('rejects bad 8-digit Unicode escape in IRI (invalid hex digits)', function () {
        $content = '<http://example.org/\U00ZZ1111> <http://example.org/p> <http://example.org/o> .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // IRI validation: surrogate codepoints in IRIs
    it('rejects surrogate codepoint in IRI', function () {
        $content = '<http://example.org/s> <http://example.org/p> <\ud800> .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // String validation: bad escape sequences
    it('rejects bad escape sequence in string literal', function () {
        $content = '<http://example.org/s> <http://example.org/p> "a\zb" .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // String validation: bad Unicode escapes
    it('rejects bad Unicode escape in string literal', function () {
        $content = '<http://example.org/s> <http://example.org/p> "\uWXYZ" .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    it('rejects bad 8-digit Unicode escape in string literal', function () {
        $content = '<http://example.org/s> <http://example.org/p> "\U0000WXYZ" .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // String validation: surrogate codepoints
    it('rejects surrogate codepoint in string literal (double-quoted)', function () {
        $content = '<http://example.org/s> <http://example.org/p> "\ud800" .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    it('rejects surrogate codepoint in string literal (single-quoted)', function () {
        $content = "<http://example.org/s> <http://example.org/p> '\\ud800' .";
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    it('rejects surrogate codepoint in triple-quoted string literal', function () {
        $content = '<http://example.org/s> <http://example.org/p> """\ud800""" .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // NFR3: security — malformed input handling
    it('throws ParseException with descriptive message for invalid IRI', function () {
        $content = '<http://example.org/ space> <http://example.org/p> <http://example.org/o> .';
        try {
            $this->handler->parse($content);
            test()->fail('Expected ParseException');
        } catch (ParseException $e) {
            expect($e->getMessage())->toStartWith('Turtle parsing failed: ');
            expect($e->getMessage())->toContain('IRI');
        }
    });

    // NFR4: structured error response
    it('throws ParseException (not generic exception) for all validation errors', function () {
        $content = '<http://example.org/s> <http://example.org/p> "a\zb" .';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // Valid content should still parse successfully
    it('does not reject valid Turtle content with proper escapes', function () {
        $content = '@prefix ex: <http://example.org/> .
ex:s ex:p "hello\nworld" .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });

    it('does not reject valid Turtle with Unicode escapes', function () {
        $content = '@prefix ex: <http://example.org/> .
ex:s ex:p "\u0041\u0042\u0043" .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });
});
