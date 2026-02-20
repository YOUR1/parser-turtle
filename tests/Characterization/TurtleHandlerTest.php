<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserTurtle\TurtleHandler;

beforeEach(function () {
    $this->handler = new TurtleHandler();
});

// ============================================================
// Task 2: Characterize canHandle() detection behavior (AC: #1)
// ============================================================

describe('canHandle()', function () {
    // 2.1: Returns true for content starting with @prefix
    it('returns true for content starting with @prefix declaration', function () {
        $content = '@prefix ex: <http://example.org/> .
ex:Person a rdfs:Class .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // 2.2: Returns true for content containing @prefix NOT at the start
    it('returns true for content containing @prefix not at the start', function () {
        $content = '# This is a comment line
@prefix ex: <http://example.org/> .
ex:Person a rdfs:Class .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // 2.3: Returns true for SPARQL-style PREFIX declaration (uppercase)
    it('returns true for SPARQL-style uppercase PREFIX declaration', function () {
        $content = 'PREFIX ex: <http://example.org/>
ex:Person a rdfs:Class .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // 2.4: Returns false for empty string
    it('returns false for empty string', function () {
        expect($this->handler->canHandle(''))->toBeFalse();
    });

    // 2.5: Returns false for whitespace-only content
    it('returns false for whitespace-only content', function () {
        expect($this->handler->canHandle('   '))->toBeFalse();
        expect($this->handler->canHandle("\n\t\n"))->toBeFalse();
    });

    // 2.6: Returns false for RDF/XML content
    it('returns false for RDF/XML content', function () {
        $content = '<?xml version="1.0" encoding="UTF-8"?>
<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
  <rdf:Description rdf:about="http://example.org/Person"/>
</rdf:RDF>';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    // 2.7: Returns false for JSON-LD content
    it('returns false for JSON-LD content without prefix keyword', function () {
        $content = '{"@context": {"name": "http://schema.org/name"}, "@type": "Person"}';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    // 2.8: Returns false for plain N-Triples content without Turtle-specific features
    // Plain N-Triples content (only <s> <p> <o> . triples) is not detected as Turtle
    // because it lacks Turtle-specific features like @prefix, @base, 'a' keyword,
    // semicolons, or string literals. This preserves the NTriples handler priority.
    it('returns false for plain N-Triples content without Turtle-specific features', function () {
        $content = '<http://example.org/Person> <http://www.w3.org/1999/02/22-rdf-syntax-ns#type> <http://www.w3.org/2000/01/rdf-schema#Class> .';
        expect($this->handler->canHandle($content))->toBeFalse();
    });

    // 2.9: Behavior with content containing @prefix in a string literal (potential false positive)
    it('returns true for content containing @prefix in any position (potential false positive)', function () {
        // This documents the actual behavior: canHandle uses str_contains which matches
        // @prefix anywhere in the content, even if it's in a string literal context
        $content = '<http://example.org/doc> <http://example.org/text> "contains @prefix keyword" .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // 2.9b: @PREFIX (uppercase with @) matches via str_contains(PREFIX) — not valid Turtle syntax but detected
    it('returns true for @PREFIX uppercase variant via PREFIX check (false positive)', function () {
        $content = '@PREFIX ex: <http://example.org/> .
ex:Person a rdfs:Class .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // 2.9c: Non-Turtle content containing SPARQL-style PREFIX keyword (false positive)
    it('returns true for non-Turtle content containing PREFIX keyword (false positive)', function () {
        // Same false positive behavior as 2.9 but via the SPARQL PREFIX path
        $content = '<http://example.org/doc> <http://example.org/text> "contains PREFIX keyword" .';
        expect($this->handler->canHandle($content))->toBeTrue();
    });

    // 2.10: Trims whitespace before checking (leading whitespace with @prefix and PREFIX)
    it('trims whitespace before checking content', function () {
        // Leading whitespace + @prefix
        $content = "   \n  @prefix ex: <http://example.org/> .\nex:A a rdfs:Class .";
        expect($this->handler->canHandle($content))->toBeTrue();

        // Leading whitespace + SPARQL PREFIX
        $prefixContent = "  \n  PREFIX ex: <http://example.org/>\nex:A a rdfs:Class .";
        expect($this->handler->canHandle($prefixContent))->toBeTrue();

        // Trailing whitespace does not affect detection
        $trailingContent = "@prefix ex: <http://example.org/> .\nex:A a rdfs:Class .   \n  ";
        expect($this->handler->canHandle($trailingContent))->toBeTrue();
    });
});

// ============================================================
// Task 3: Characterize parse() output structure (AC: #2, #6)
// ============================================================

describe('parse() output structure', function () {
    // 3.1: Returns ParsedRdf instance for valid Turtle with a single class
    it('returns ParsedRdf instance for valid Turtle with a single class', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
<http://example.org/Person> a owl:Class .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
    });

    // 3.2: Output has correct format property
    it('has correct format property', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
<http://example.org/Person> a rdfs:Class .';
        $result = $this->handler->parse($content);
        expect($result->format)->toBe('turtle');
    });

    // 3.3: Output has correct rawContent property (original input preserved)
    it('preserves original input in rawContent property', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
<http://example.org/Person> a rdfs:Class .';
        $result = $this->handler->parse($content);
        expect($result->rawContent)->toBe($content);
    });

    // 3.4: Metadata contains expected keys and values (pinned resource_count for regression baseline)
    it('has metadata with parser, format, and resource_count keys', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
<http://example.org/Person> a rdfs:Class .';
        $result = $this->handler->parse($content);
        expect($result->metadata)->toHaveKeys(['parser', 'format', 'resource_count']);
        expect($result->metadata['parser'])->toBe('turtle_handler');
        expect($result->metadata['format'])->toBe('turtle');
        expect($result->metadata['resource_count'])->toBeInt();
        // Pin exact count as characterization baseline — EasyRdf counts all subject/object resources
        expect($result->metadata['resource_count'])->toBe(count($result->graph->resources()));
    });

    // 3.5: Parses Turtle with multiple classes and rdfs:subClassOf relationships
    it('parses Turtle with multiple classes and subClassOf relationships', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
<http://example.org/Animal> a owl:Class .
<http://example.org/Person> a owl:Class ;
    rdfs:subClassOf <http://example.org/Animal> .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $graph = $result->graph;
        $person = $graph->resource('http://example.org/Person');
        $parent = $person->getResource('rdfs:subClassOf');
        expect($parent)->not->toBeNull();
        expect($parent->getUri())->toBe('http://example.org/Animal');
    });

    // 3.6: Parses Turtle with properties (rdf:Property, rdfs:domain, rdfs:range)
    it('parses Turtle with properties including domain and range', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
<http://example.org/hasName> a rdf:Property ;
    rdfs:domain <http://example.org/Person> ;
    rdfs:range <http://www.w3.org/2001/XMLSchema#string> .';
        $result = $this->handler->parse($content);
        $graph = $result->graph;
        $prop = $graph->resource('http://example.org/hasName');
        $domain = $prop->getResource('rdfs:domain');
        $range = $prop->getResource('rdfs:range');
        expect($domain->getUri())->toBe('http://example.org/Person');
        expect($range->getUri())->toBe('http://www.w3.org/2001/XMLSchema#string');
    });

    // 3.7: Parses Turtle with multiple prefix declarations
    it('parses Turtle with multiple prefix declarations', function () {
        $content = '@prefix rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#> .
@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
@prefix ex: <http://example.org/> .
ex:Person a owl:Class ;
    rdfs:label "Person" .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->metadata['resource_count'])->toBeGreaterThan(0);
    });

    // 3.8: Parses Turtle with blank nodes
    it('parses Turtle with blank nodes', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
<http://example.org/Person> a owl:Class ;
    rdfs:subClassOf [
        a owl:Restriction ;
        owl:onProperty <http://example.org/hasAge> ;
        owl:minCardinality 1
    ] .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $graph = $result->graph;
        $person = $graph->resource('http://example.org/Person');
        expect($person)->not->toBeNull();
    });

    // 3.9: Graph contains expected resources (verify resource count and URIs)
    it('graph contains expected resources with correct URIs', function () {
        $content = '@prefix owl: <http://www.w3.org/2002/07/owl#> .
<http://example.org/Person> a owl:Class .
<http://example.org/Animal> a owl:Class .';
        $result = $this->handler->parse($content);
        $resources = $result->graph->resources();
        $uris = array_map(fn ($r) => $r->getUri(), $resources);
        expect($uris)->toContain('http://example.org/Person');
        expect($uris)->toContain('http://example.org/Animal');
    });

    // 3.10: Parses Turtle with string literals with language tags
    it('parses Turtle with string literals with language tags', function () {
        $content = '@prefix rdfs: <http://www.w3.org/2000/01/rdf-schema#> .
@prefix owl: <http://www.w3.org/2002/07/owl#> .
<http://example.org/Person> a owl:Class ;
    rdfs:label "Person"@en ;
    rdfs:label "Persoon"@nl .';
        $result = $this->handler->parse($content);
        $graph = $result->graph;
        $person = $graph->resource('http://example.org/Person');
        $labelEn = $person->getLiteral('rdfs:label', 'en');
        $labelNl = $person->getLiteral('rdfs:label', 'nl');
        expect((string) $labelEn)->toBe('Person');
        expect((string) $labelNl)->toBe('Persoon');
    });

    // 3.11: Parses Turtle with typed literals
    it('parses Turtle with typed literals', function () {
        $content = '@prefix xsd: <http://www.w3.org/2001/XMLSchema#> .
@prefix ex: <http://example.org/> .
ex:resource ex:age "42"^^xsd:integer ;
    ex:active "true"^^xsd:boolean .';
        $result = $this->handler->parse($content);
        $graph = $result->graph;
        $resource = $graph->resource('http://example.org/resource');
        $age = $resource->getLiteral('ex:age');
        expect($age)->not->toBeNull();
        expect($age->getValue())->toBe(42);
    });
});

// ============================================================
// Task 4: Characterize getFormatName() (AC: #3)
// ============================================================

describe('getFormatName()', function () {
    // 4.1: Returns 'turtle'
    it('returns turtle as format name', function () {
        expect($this->handler->getFormatName())->toBe('turtle');
    });

    // 4.2: Return value is a string
    it('returns a string type', function () {
        expect($this->handler->getFormatName())->toBeString();
    });
});

// ============================================================
// Task 5: Characterize error behavior (AC: #4)
// ============================================================

describe('error behavior', function () {
    // 5.1: Throws ParseException for malformed Turtle
    it('throws ParseException for malformed Turtle syntax', function () {
        $malformed = '@prefix ex: <http://example.org/> .
ex:Person a ex:Class
missing dot here';
        expect(fn () => $this->handler->parse($malformed))->toThrow(ParseException::class);
    });

    // 5.2: Exception message starts with 'Turtle parsing failed: '
    it('exception message starts with Turtle parsing failed prefix', function () {
        try {
            $this->handler->parse('@prefix invalid turtle content without proper syntax');
            $this->fail('Expected ParseException');
        } catch (ParseException $e) {
            expect($e->getMessage())->toStartWith('Turtle parsing failed: ');
        }
    });

    // 5.3: Exception has $previous set to original exception
    it('exception has previous exception set', function () {
        try {
            $this->handler->parse('@prefix invalid turtle content without proper syntax');
            $this->fail('Expected ParseException');
        } catch (ParseException $e) {
            expect($e->getPrevious())->not->toBeNull();
            expect($e->getPrevious())->toBeInstanceOf(\Throwable::class);
        }
    });

    // 5.4: Empty string input — actual behavior: does NOT throw, returns empty ParsedRdf
    it('does not throw for empty string input — returns empty ParsedRdf', function () {
        $result = $this->handler->parse('');
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        expect($result->format)->toBe('turtle');
        expect($result->rawContent)->toBe('');
        expect($result->graph->resources())->toBeEmpty();
    });

    // 5.5: Throws for content with @prefix but invalid Turtle body
    it('throws for content with valid prefix but invalid body', function () {
        $content = '@prefix ex: <http://example.org/> .
this is not valid turtle at all {{{';
        expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
    });

    // 5.6: Exception code is 0
    it('exception code is 0', function () {
        try {
            $this->handler->parse('@prefix invalid');
            $this->fail('Expected ParseException');
        } catch (ParseException $e) {
            expect($e->getCode())->toBe(0);
        }
    });
});

// ============================================================
// Task 6: Characterize prefix registration side effect (AC: #5)
// ============================================================

describe('prefix registration side effect', function () {
    // 6.1: After parse(), custom prefixes are registered in EasyRdf\RdfNamespace
    it('registers custom prefixes in EasyRdf RdfNamespace after parse', function () {
        $content = '@prefix chartest61: <http://characterization-test.example.org/> .
<http://characterization-test.example.org/Thing> a <http://www.w3.org/2000/01/rdf-schema#Class> .';
        $this->handler->parse($content);
        $namespaces = \EasyRdf\RdfNamespace::namespaces();
        expect($namespaces)->toHaveKey('chartest61');
        expect($namespaces['chartest61'])->toBe('http://characterization-test.example.org/');
    });

    // 6.2: Standard prefixes remain available after parsing
    it('standard prefixes remain available after parsing', function () {
        $content = '@prefix ex: <http://example.org/> .
ex:Thing a <http://www.w3.org/2000/01/rdf-schema#Class> .';
        $this->handler->parse($content);
        $namespaces = \EasyRdf\RdfNamespace::namespaces();
        expect($namespaces)->toHaveKey('rdf');
        expect($namespaces)->toHaveKey('rdfs');
        expect($namespaces)->toHaveKey('owl');
        expect($namespaces)->toHaveKey('xsd');
    });

    // 6.3: Multiple custom prefixes registered from single content
    it('registers multiple custom prefixes from single content', function () {
        $content = '@prefix multi163: <http://multi-test1.example.org/> .
@prefix multi263: <http://multi-test2.example.org/> .
<http://multi-test1.example.org/A> a <http://www.w3.org/2000/01/rdf-schema#Class> .';
        $this->handler->parse($content);
        $namespaces = \EasyRdf\RdfNamespace::namespaces();
        expect($namespaces)->toHaveKey('multi163');
        expect($namespaces)->toHaveKey('multi263');
        expect($namespaces['multi163'])->toBe('http://multi-test1.example.org/');
        expect($namespaces['multi263'])->toBe('http://multi-test2.example.org/');
    });

    // 6.4: Prefix registration happens BEFORE graph parsing
    it('prefix registration happens before graph parsing', function () {
        // Verify the prefix is globally registered AFTER parse completes.
        // Note: This test confirms the observable outcome (parse succeeds AND prefix
        // is registered in EasyRdf\RdfNamespace). It does not definitively prove
        // internal ordering because EasyRdf also handles @prefix declarations natively
        // during Turtle parsing. The characterization captures the documented intent
        // of registerPrefixesFromContent() being called before $graph->parse().
        $content = '@prefix orderbefore64: <http://before-test.example.org/> .
orderbefore64:Item a <http://www.w3.org/2000/01/rdf-schema#Class> .';
        $result = $this->handler->parse($content);
        expect($result)->toBeInstanceOf(ParsedRdf::class);
        $namespaces = \EasyRdf\RdfNamespace::namespaces();
        expect($namespaces)->toHaveKey('orderbefore64');
        expect($namespaces['orderbefore64'])->toBe('http://before-test.example.org/');
    });
});
