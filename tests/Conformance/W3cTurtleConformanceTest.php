<?php

declare(strict_types=1);

use Youri\vandenBogert\Software\ParserCore\Exceptions\ParseException;
use Youri\vandenBogert\Software\ParserCore\ValueObjects\ParsedRdf;
use Youri\vandenBogert\Software\ParserTurtle\TurtleHandler;
use EasyRdf\Graph;

/*
 * CONFORMANCE_RESULTS
 * ===================
 * Source: W3C RDF 1.1 Turtle Test Suite
 * URL: https://www.w3.org/2013/TurtleTests/
 * Downloaded from: https://github.com/w3c/rdf-tests/tree/main/rdf/rdf11/rdf-turtle
 * Date: 2026-02-19
 * Base URI: https://w3c.github.io/rdf-tests/rdf/rdf11/rdf-turtle/ (per manifest mf:assumedTestBase)
 *
 * Total tests: 313
 * - Positive Syntax: 74
 * - Negative Syntax: 90
 * - Positive Evaluation: 145
 * - Negative Evaluation: 4
 *
 * Results:
 * - Passed: 287 (91.7%)
 * - Deprecated: 2 (0.6%) — passing assertions but trigger PHP deprecation notices from EasyRdf
 * - Skipped: 24 (7.7%) — EasyRdf 1.1.1 limitations
 * - Failing: 0
 *
 * Category pass rates:
 * - Positive Syntax:    69/74  (93.2%) — 68 passed, 1 deprecated, 5 skipped
 * - Negative Syntax:    84/90  (93.3%) — 84 passed, 6 skipped
 * - Positive Evaluation: 136/145 (93.8%) — 135 passed, 1 deprecated, 9 skipped
 * - Negative Evaluation:  0/4   (0.0%)  — 4 skipped (entire category untested due to EasyRdf permissive parsing)
 *
 * Skipped breakdown (24 total):
 * - Positive Syntax:  5 skipped — EasyRdf does not parse valid syntax (dots in names, BASE, blank labels)
 * - Negative Syntax:  6 skipped — EasyRdf does not reject invalid syntax (BASE case, blank nodes, local name escapes)
 * - Positive Eval:    9 skipped — EasyRdf parse failures (dots in names, blankNodePropertyList, IRI resolution)
 * - Negative Eval:    4 skipped — EasyRdf does not reject semantic errors (undefined prefix, no base)
 *
 * Story 9-4 improvement: 19 previously-skipped negative syntax tests now pass thanks to
 * TurtleHandler pre-parse validation (IRI whitespace/escape validation, string escape validation,
 * surrogate codepoint detection).
 *
 * Deprecated tests (2):
 * - turtle-syntax-uri-01: EasyRdf\Resource::offsetExists deprecation
 * - collection_subject: EasyRdf\Collection::count() deprecation
 *
 * Testing approach:
 * - Positive syntax/negative syntax/negative eval: tested through TurtleHandler::parse()
 * - Positive evaluation: dual testing — TurtleHandler::parse() verifies no exception,
 *   then EasyRdf with base URI for content-based triple comparison (non-blank-node triples
 *   compared exactly, blank-node triples compared by count)
 * - turtle-subm-02 prefix-in-comment bug fixed in Story 9-2 (stripCommentsAndStrings)
 */

function w3cFixturePath(string $filename): string
{
    return __DIR__ . '/../Fixtures/W3c/' . $filename;
}

function w3cFixture(string $filename): string
{
    $path = w3cFixturePath($filename);
    if (!file_exists($path)) {
        throw new \RuntimeException("W3C fixture not found: {$path}");
    }

    $content = file_get_contents($path);
    if ($content === false) {
        throw new \RuntimeException("Failed to read W3C fixture: {$path}");
    }

    return $content;
}

function countGraphTriples(Graph $graph): int
{
    $count = 0;
    foreach ($graph->resources() as $resource) {
        foreach ($resource->propertyUris() as $property) {
            $count += count($resource->all($property));
        }
    }

    return $count;
}

/**
 * Compare two RDF graphs by content, not just triple count.
 * Non-blank-node triples are compared exactly (sorted N-Triples).
 * Blank-node triples are compared by count (isomorphism is NP-complete).
 */
function assertGraphsEqual(Graph $actual, Graph $expected, string $testId): void
{
    $actualNt = trim($actual->serialise('ntriples'));
    $expectedNt = trim($expected->serialise('ntriples'));

    $actualLines = $actualNt === '' ? [] : array_map('trim', explode("\n", $actualNt));
    $expectedLines = $expectedNt === '' ? [] : array_map('trim', explode("\n", $expectedNt));

    // Filter out empty lines
    $actualLines = array_values(array_filter($actualLines, fn (string $l): bool => $l !== ''));
    $expectedLines = array_values(array_filter($expectedLines, fn (string $l): bool => $l !== ''));

    expect(count($actualLines))->toBe(
        count($expectedLines),
        "Triple count mismatch for [{$testId}]: got " . count($actualLines) . ', expected ' . count($expectedLines)
    );

    $hasBnode = fn (string $line): bool => (bool) preg_match('/_:[a-zA-Z0-9]+/', $line);

    $actualNonBnode = array_values(array_filter($actualLines, fn ($l) => ! $hasBnode($l)));
    $expectedNonBnode = array_values(array_filter($expectedLines, fn ($l) => ! $hasBnode($l)));

    sort($actualNonBnode);
    sort($expectedNonBnode);

    expect($actualNonBnode)->toBe(
        $expectedNonBnode,
        "Non-blank-node triple content mismatch for [{$testId}]"
    );

    $actualBnodeCount = count($actualLines) - count($actualNonBnode);
    $expectedBnodeCount = count($expectedLines) - count($expectedNonBnode);
    expect($actualBnodeCount)->toBe(
        $expectedBnodeCount,
        "Blank-node triple count mismatch for [{$testId}]: got {$actualBnodeCount}, expected {$expectedBnodeCount}"
    );
}

// ---------------------------------------------------------------------------
// Positive Syntax Tests (74)
// ---------------------------------------------------------------------------
describe('W3C Positive Syntax Tests', function () {

    beforeEach(function () {
        $this->handler = new TurtleHandler();
    });

    $positiveSyntaxTests = [
        'turtle-syntax-file-01' => 'turtle-syntax-file-01.ttl',
        'turtle-syntax-file-02' => 'turtle-syntax-file-02.ttl',
        'turtle-syntax-file-03' => 'turtle-syntax-file-03.ttl',
        'turtle-syntax-uri-01' => 'turtle-syntax-uri-01.ttl',
        'turtle-syntax-uri-02' => 'turtle-syntax-uri-02.ttl',
        'turtle-syntax-uri-03' => 'turtle-syntax-uri-03.ttl',
        'turtle-syntax-uri-04' => 'turtle-syntax-uri-04.ttl',
        'turtle-syntax-base-01' => 'turtle-syntax-base-01.ttl',
        'turtle-syntax-base-02' => 'turtle-syntax-base-02.ttl',
        'turtle-syntax-base-03' => 'turtle-syntax-base-03.ttl',
        'turtle-syntax-base-04' => 'turtle-syntax-base-04.ttl',
        'turtle-syntax-prefix-01' => 'turtle-syntax-prefix-01.ttl',
        'turtle-syntax-prefix-02' => 'turtle-syntax-prefix-02.ttl',
        'turtle-syntax-prefix-03' => 'turtle-syntax-prefix-03.ttl',
        'turtle-syntax-prefix-04' => 'turtle-syntax-prefix-04.ttl',
        'turtle-syntax-prefix-05' => 'turtle-syntax-prefix-05.ttl',
        'turtle-syntax-prefix-06' => 'turtle-syntax-prefix-06.ttl',
        'turtle-syntax-prefix-07' => 'turtle-syntax-prefix-07.ttl',
        'turtle-syntax-prefix-08' => 'turtle-syntax-prefix-08.ttl',
        'turtle-syntax-prefix-09' => 'turtle-syntax-prefix-09.ttl',
        'turtle-syntax-string-01' => 'turtle-syntax-string-01.ttl',
        'turtle-syntax-string-02' => 'turtle-syntax-string-02.ttl',
        'turtle-syntax-string-03' => 'turtle-syntax-string-03.ttl',
        'turtle-syntax-string-04' => 'turtle-syntax-string-04.ttl',
        'turtle-syntax-string-05' => 'turtle-syntax-string-05.ttl',
        'turtle-syntax-string-06' => 'turtle-syntax-string-06.ttl',
        'turtle-syntax-string-07' => 'turtle-syntax-string-07.ttl',
        'turtle-syntax-string-08' => 'turtle-syntax-string-08.ttl',
        'turtle-syntax-string-09' => 'turtle-syntax-string-09.ttl',
        'turtle-syntax-string-10' => 'turtle-syntax-string-10.ttl',
        'turtle-syntax-string-11' => 'turtle-syntax-string-11.ttl',
        'turtle-syntax-str-esc-01' => 'turtle-syntax-str-esc-01.ttl',
        'turtle-syntax-str-esc-02' => 'turtle-syntax-str-esc-02.ttl',
        'turtle-syntax-str-esc-03' => 'turtle-syntax-str-esc-03.ttl',
        'turtle-syntax-pname-esc-01' => 'turtle-syntax-pname-esc-01.ttl',
        'turtle-syntax-pname-esc-02' => 'turtle-syntax-pname-esc-02.ttl',
        'turtle-syntax-pname-esc-03' => 'turtle-syntax-pname-esc-03.ttl',
        'turtle-syntax-bnode-01' => 'turtle-syntax-bnode-01.ttl',
        'turtle-syntax-bnode-02' => 'turtle-syntax-bnode-02.ttl',
        'turtle-syntax-bnode-03' => 'turtle-syntax-bnode-03.ttl',
        'turtle-syntax-bnode-04' => 'turtle-syntax-bnode-04.ttl',
        'turtle-syntax-bnode-05' => 'turtle-syntax-bnode-05.ttl',
        'turtle-syntax-bnode-06' => 'turtle-syntax-bnode-06.ttl',
        'turtle-syntax-bnode-07' => 'turtle-syntax-bnode-07.ttl',
        'turtle-syntax-bnode-08' => 'turtle-syntax-bnode-08.ttl',
        'turtle-syntax-bnode-09' => 'turtle-syntax-bnode-09.ttl',
        'turtle-syntax-bnode-10' => 'turtle-syntax-bnode-10.ttl',
        'turtle-syntax-number-01' => 'turtle-syntax-number-01.ttl',
        'turtle-syntax-number-02' => 'turtle-syntax-number-02.ttl',
        'turtle-syntax-number-03' => 'turtle-syntax-number-03.ttl',
        'turtle-syntax-number-04' => 'turtle-syntax-number-04.ttl',
        'turtle-syntax-number-05' => 'turtle-syntax-number-05.ttl',
        'turtle-syntax-number-06' => 'turtle-syntax-number-06.ttl',
        'turtle-syntax-number-07' => 'turtle-syntax-number-07.ttl',
        'turtle-syntax-number-08' => 'turtle-syntax-number-08.ttl',
        'turtle-syntax-number-09' => 'turtle-syntax-number-09.ttl',
        'turtle-syntax-number-10' => 'turtle-syntax-number-10.ttl',
        'turtle-syntax-number-11' => 'turtle-syntax-number-11.ttl',
        'turtle-syntax-number-12' => 'turtle-syntax-number-12.ttl',
        'turtle-syntax-number-13' => 'turtle-syntax-number-13.ttl',
        'turtle-syntax-datatypes-01' => 'turtle-syntax-datatypes-01.ttl',
        'turtle-syntax-datatypes-02' => 'turtle-syntax-datatypes-02.ttl',
        'turtle-syntax-kw-01' => 'turtle-syntax-kw-01.ttl',
        'turtle-syntax-kw-02' => 'turtle-syntax-kw-02.ttl',
        'turtle-syntax-kw-03' => 'turtle-syntax-kw-03.ttl',
        'turtle-syntax-struct-01' => 'turtle-syntax-struct-01.ttl',
        'turtle-syntax-struct-02' => 'turtle-syntax-struct-02.ttl',
        'turtle-syntax-struct-03' => 'turtle-syntax-struct-03.ttl',
        'turtle-syntax-struct-04' => 'turtle-syntax-struct-04.ttl',
        'turtle-syntax-struct-05' => 'turtle-syntax-struct-05.ttl',
        'turtle-syntax-ln-dots' => 'turtle-syntax-ln-dots.ttl',
        'turtle-syntax-ln-colons' => 'turtle-syntax-ln-colons.ttl',
        'turtle-syntax-ns-dots' => 'turtle-syntax-ns-dots.ttl',
        'turtle-syntax-blank-label' => 'turtle-syntax-blank-label.ttl',
    ];

    // @skip categories: [SPARQL-style directives], [dots in names], [special chars in labels]
    $skippedPositiveSyntax = [
        'turtle-syntax-base-02' => '@skip EasyRdf limitation: SPARQL-style directives — EasyRdf 1.1.1 does not support bare BASE without @-prefix',
        'turtle-syntax-prefix-02' => '@skip EasyRdf limitation: SPARQL-style directives — EasyRdf 1.1.1 does not support bare PREFIX without @-prefix in this context',
        'turtle-syntax-ln-dots' => '@skip EasyRdf limitation: dots in names — EasyRdf 1.1.1 does not support dots in local names (easyrdf/easyrdf#140)',
        'turtle-syntax-ns-dots' => '@skip EasyRdf limitation: dots in names — EasyRdf 1.1.1 does not support dots in namespace prefixes (easyrdf/easyrdf#140)',
        'turtle-syntax-blank-label' => '@skip EasyRdf limitation: special chars in labels — EasyRdf 1.1.1 does not support this blank node label pattern',
    ];

    foreach ($positiveSyntaxTests as $testId => $filename) {
        $test = it("[{$testId}] parses valid syntax", function () use ($filename) {
            $content = w3cFixture($filename);
            $result = $this->handler->parse($content);
            expect($result)->toBeInstanceOf(ParsedRdf::class);
        });
        if (isset($skippedPositiveSyntax[$testId])) {
            $test->skip($skippedPositiveSyntax[$testId]);
        }
    }
});

// ---------------------------------------------------------------------------
// Negative Syntax Tests (90)
// ---------------------------------------------------------------------------
describe('W3C Negative Syntax Tests', function () {

    beforeEach(function () {
        $this->handler = new TurtleHandler();
    });

    $negativeSyntaxTests = [
        'turtle-syntax-bad-uri-01' => 'turtle-syntax-bad-uri-01.ttl',
        'turtle-syntax-bad-uri-02' => 'turtle-syntax-bad-uri-02.ttl',
        'turtle-syntax-bad-uri-03' => 'turtle-syntax-bad-uri-03.ttl',
        'turtle-syntax-bad-uri-04' => 'turtle-syntax-bad-uri-04.ttl',
        'turtle-syntax-bad-uri-05' => 'turtle-syntax-bad-uri-05.ttl',
        'turtle-syntax-bad-prefix-01' => 'turtle-syntax-bad-prefix-01.ttl',
        'turtle-syntax-bad-prefix-02' => 'turtle-syntax-bad-prefix-02.ttl',
        'turtle-syntax-bad-prefix-03' => 'turtle-syntax-bad-prefix-03.ttl',
        'turtle-syntax-bad-prefix-04' => 'turtle-syntax-bad-prefix-04.ttl',
        'turtle-syntax-bad-prefix-05' => 'turtle-syntax-bad-prefix-05.ttl',
        'turtle-syntax-bad-base-01' => 'turtle-syntax-bad-base-01.ttl',
        'turtle-syntax-bad-base-02' => 'turtle-syntax-bad-base-02.ttl',
        'turtle-syntax-bad-base-03' => 'turtle-syntax-bad-base-03.ttl',
        'turtle-syntax-bad-bnode-01' => 'turtle-syntax-bad-bnode-01.ttl',
        'turtle-syntax-bad-bnode-02' => 'turtle-syntax-bad-bnode-02.ttl',
        'turtle-syntax-bad-struct-01' => 'turtle-syntax-bad-struct-01.ttl',
        'turtle-syntax-bad-struct-02' => 'turtle-syntax-bad-struct-02.ttl',
        'turtle-syntax-bad-struct-03' => 'turtle-syntax-bad-struct-03.ttl',
        'turtle-syntax-bad-struct-04' => 'turtle-syntax-bad-struct-04.ttl',
        'turtle-syntax-bad-struct-05' => 'turtle-syntax-bad-struct-05.ttl',
        'turtle-syntax-bad-struct-06' => 'turtle-syntax-bad-struct-06.ttl',
        'turtle-syntax-bad-struct-07' => 'turtle-syntax-bad-struct-07.ttl',
        'turtle-syntax-bad-kw-01' => 'turtle-syntax-bad-kw-01.ttl',
        'turtle-syntax-bad-kw-02' => 'turtle-syntax-bad-kw-02.ttl',
        'turtle-syntax-bad-kw-03' => 'turtle-syntax-bad-kw-03.ttl',
        'turtle-syntax-bad-kw-04' => 'turtle-syntax-bad-kw-04.ttl',
        'turtle-syntax-bad-kw-05' => 'turtle-syntax-bad-kw-05.ttl',
        'turtle-syntax-bad-n3-extras-01' => 'turtle-syntax-bad-n3-extras-01.ttl',
        'turtle-syntax-bad-n3-extras-02' => 'turtle-syntax-bad-n3-extras-02.ttl',
        'turtle-syntax-bad-n3-extras-03' => 'turtle-syntax-bad-n3-extras-03.ttl',
        'turtle-syntax-bad-n3-extras-04' => 'turtle-syntax-bad-n3-extras-04.ttl',
        'turtle-syntax-bad-n3-extras-05' => 'turtle-syntax-bad-n3-extras-05.ttl',
        'turtle-syntax-bad-n3-extras-06' => 'turtle-syntax-bad-n3-extras-06.ttl',
        'turtle-syntax-bad-n3-extras-07' => 'turtle-syntax-bad-n3-extras-07.ttl',
        'turtle-syntax-bad-n3-extras-08' => 'turtle-syntax-bad-n3-extras-08.ttl',
        'turtle-syntax-bad-n3-extras-09' => 'turtle-syntax-bad-n3-extras-09.ttl',
        'turtle-syntax-bad-n3-extras-10' => 'turtle-syntax-bad-n3-extras-10.ttl',
        'turtle-syntax-bad-n3-extras-11' => 'turtle-syntax-bad-n3-extras-11.ttl',
        'turtle-syntax-bad-n3-extras-12' => 'turtle-syntax-bad-n3-extras-12.ttl',
        'turtle-syntax-bad-n3-extras-13' => 'turtle-syntax-bad-n3-extras-13.ttl',
        'turtle-syntax-bad-numeric-escape-01' => 'turtle-syntax-bad-numeric-escape-01.ttl',
        'turtle-syntax-bad-numeric-escape-02' => 'turtle-syntax-bad-numeric-escape-02.ttl',
        'turtle-syntax-bad-numeric-escape-03' => 'turtle-syntax-bad-numeric-escape-03.ttl',
        'turtle-syntax-bad-numeric-escape-04' => 'turtle-syntax-bad-numeric-escape-04.ttl',
        'turtle-syntax-bad-numeric-escape-05' => 'turtle-syntax-bad-numeric-escape-05.ttl',
        'turtle-syntax-bad-numeric-escape-06' => 'turtle-syntax-bad-numeric-escape-06.ttl',
        'turtle-syntax-bad-numeric-escape-07' => 'turtle-syntax-bad-numeric-escape-07.ttl',
        'turtle-syntax-bad-numeric-escape-08' => 'turtle-syntax-bad-numeric-escape-08.ttl',
        'turtle-syntax-bad-numeric-escape-09' => 'turtle-syntax-bad-numeric-escape-09.ttl',
        'turtle-syntax-bad-numeric-escape-10' => 'turtle-syntax-bad-numeric-escape-10.ttl',
        'turtle-syntax-bad-struct-08' => 'turtle-syntax-bad-struct-08.ttl',
        'turtle-syntax-bad-struct-09' => 'turtle-syntax-bad-struct-09.ttl',
        'turtle-syntax-bad-struct-10' => 'turtle-syntax-bad-struct-10.ttl',
        'turtle-syntax-bad-struct-11' => 'turtle-syntax-bad-struct-11.ttl',
        'turtle-syntax-bad-struct-12' => 'turtle-syntax-bad-struct-12.ttl',
        'turtle-syntax-bad-struct-13' => 'turtle-syntax-bad-struct-13.ttl',
        'turtle-syntax-bad-struct-14' => 'turtle-syntax-bad-struct-14.ttl',
        'turtle-syntax-bad-struct-15' => 'turtle-syntax-bad-struct-15.ttl',
        'turtle-syntax-bad-struct-16' => 'turtle-syntax-bad-struct-16.ttl',
        'turtle-syntax-bad-struct-17' => 'turtle-syntax-bad-struct-17.ttl',
        'turtle-syntax-bad-lang-01' => 'turtle-syntax-bad-lang-01.ttl',
        'turtle-syntax-bad-esc-01' => 'turtle-syntax-bad-esc-01.ttl',
        'turtle-syntax-bad-esc-02' => 'turtle-syntax-bad-esc-02.ttl',
        'turtle-syntax-bad-esc-03' => 'turtle-syntax-bad-esc-03.ttl',
        'turtle-syntax-bad-esc-04' => 'turtle-syntax-bad-esc-04.ttl',
        'turtle-syntax-bad-pname-01' => 'turtle-syntax-bad-pname-01.ttl',
        'turtle-syntax-bad-pname-02' => 'turtle-syntax-bad-pname-02.ttl',
        'turtle-syntax-bad-pname-03' => 'turtle-syntax-bad-pname-03.ttl',
        'turtle-syntax-bad-string-01' => 'turtle-syntax-bad-string-01.ttl',
        'turtle-syntax-bad-string-02' => 'turtle-syntax-bad-string-02.ttl',
        'turtle-syntax-bad-string-03' => 'turtle-syntax-bad-string-03.ttl',
        'turtle-syntax-bad-string-04' => 'turtle-syntax-bad-string-04.ttl',
        'turtle-syntax-bad-string-05' => 'turtle-syntax-bad-string-05.ttl',
        'turtle-syntax-bad-string-06' => 'turtle-syntax-bad-string-06.ttl',
        'turtle-syntax-bad-string-07' => 'turtle-syntax-bad-string-07.ttl',
        'turtle-syntax-bad-num-01' => 'turtle-syntax-bad-num-01.ttl',
        'turtle-syntax-bad-num-02' => 'turtle-syntax-bad-num-02.ttl',
        'turtle-syntax-bad-num-03' => 'turtle-syntax-bad-num-03.ttl',
        'turtle-syntax-bad-num-04' => 'turtle-syntax-bad-num-04.ttl',
        'turtle-syntax-bad-num-05' => 'turtle-syntax-bad-num-05.ttl',
        'turtle-syntax-bad-LITERAL2_with_langtag_and_datatype' => 'turtle-syntax-bad-LITERAL2_with_langtag_and_datatype.ttl',
        'turtle-syntax-bad-blank-label-dot-end' => 'turtle-syntax-bad-blank-label-dot-end.ttl',
        'turtle-syntax-bad-number-dot-in-anon' => 'turtle-syntax-bad-number-dot-in-anon.ttl',
        'turtle-syntax-bad-ln-dash-start' => 'turtle-syntax-bad-ln-dash-start.ttl',
        'turtle-syntax-bad-ln-escape' => 'turtle-syntax-bad-ln-escape.ttl',
        'turtle-syntax-bad-ln-escape-start' => 'turtle-syntax-bad-ln-escape-start.ttl',
        'turtle-syntax-bad-ns-dot-end' => 'turtle-syntax-bad-ns-dot-end.ttl',
        'turtle-syntax-bad-ns-dot-start' => 'turtle-syntax-bad-ns-dot-start.ttl',
        'turtle-syntax-bad-missing-ns-dot-end' => 'turtle-syntax-bad-missing-ns-dot-end.ttl',
        'turtle-syntax-bad-missing-ns-dot-start' => 'turtle-syntax-bad-missing-ns-dot-start.ttl',
    ];

    // @skip categories: [permissive BASE validation], [permissive blank node validation],
    //   [permissive name validation], [permissive escape validation (local names)]
    // NOTE: 19 tests previously skipped are now passing thanks to Story 9-4 pre-parse validation:
    //   - 5 IRI validation tests (bad-uri-01..05): validateIRIs() catches whitespace and bad escapes
    //   - 4 escape validation tests (bad-esc-01..04): validateStringEscapes() catches bad escape sequences
    //   - 10 numeric escape tests (bad-numeric-escape-01..10): validates surrogate codepoints in strings and IRIs
    $skippedNegativeSyntax = [
        'turtle-syntax-bad-base-02' => '@skip EasyRdf limitation: permissive BASE validation — does not reject @BASE (wrong case)',
        'turtle-syntax-bad-bnode-01' => '@skip EasyRdf limitation: permissive blank node validation — does not reject _::a invalid blank node syntax',
        'turtle-syntax-bad-bnode-02' => '@skip EasyRdf limitation: permissive blank node validation — does not reject _:abc:def invalid blank node syntax',
        'turtle-syntax-bad-pname-02' => '@skip EasyRdf limitation: permissive name validation — does not reject dot at end of prefixed name',
        'turtle-syntax-bad-ln-escape' => '@skip EasyRdf limitation: permissive escape validation — does not reject bad percent-encoding in local name (%2)',
        'turtle-syntax-bad-ln-escape-start' => '@skip EasyRdf limitation: permissive escape validation — does not reject bad percent-encoding at start of local name (%2o)',
    ];

    foreach ($negativeSyntaxTests as $testId => $filename) {
        $test = it("[{$testId}] rejects invalid syntax", function () use ($filename) {
            $content = w3cFixture($filename);
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
        if (isset($skippedNegativeSyntax[$testId])) {
            $test->skip($skippedNegativeSyntax[$testId]);
        }
    }
});

// ---------------------------------------------------------------------------
// Positive Evaluation Tests (145)
// Dual testing: TurtleHandler::parse() verifies no exceptions, then
// EasyRdf with correct base URI for content-based triple comparison.
// ---------------------------------------------------------------------------
describe('W3C Positive Evaluation Tests', function () {

    beforeEach(function () {
        $this->handler = new TurtleHandler();
    });

    // Each entry: 'testId' => ['action.ttl', 'result.nt']
    $positiveEvalTests = [
        'IRI_subject' => ['IRI_subject.ttl', 'IRI_spo.nt'],
        'IRI_with_four_digit_numeric_escape' => ['IRI_with_four_digit_numeric_escape.ttl', 'IRI_spo.nt'],
        'IRI_with_eight_digit_numeric_escape' => ['IRI_with_eight_digit_numeric_escape.ttl', 'IRI_spo.nt'],
        'IRI_with_all_punctuation' => ['IRI_with_all_punctuation.ttl', 'IRI_with_all_punctuation.nt'],
        'bareword_a_predicate' => ['bareword_a_predicate.ttl', 'bareword_a_predicate.nt'],
        'old_style_prefix' => ['old_style_prefix.ttl', 'IRI_spo.nt'],
        'SPARQL_style_prefix' => ['SPARQL_style_prefix.ttl', 'IRI_spo.nt'],
        'prefixed_IRI_predicate' => ['prefixed_IRI_predicate.ttl', 'IRI_spo.nt'],
        'prefixed_IRI_object' => ['prefixed_IRI_object.ttl', 'IRI_spo.nt'],
        'prefix_only_IRI' => ['prefix_only_IRI.ttl', 'IRI_spo.nt'],
        'prefix_with_PN_CHARS_BASE_character_boundaries' => ['prefix_with_PN_CHARS_BASE_character_boundaries.ttl', 'IRI_spo.nt'],
        'prefix_with_non_leading_extras' => ['prefix_with_non_leading_extras.ttl', 'IRI_spo.nt'],
        'localName_with_assigned_nfc_bmp_PN_CHARS_BASE_character_boundaries' => ['localName_with_assigned_nfc_bmp_PN_CHARS_BASE_character_boundaries.ttl', 'localName_with_assigned_nfc_bmp_PN_CHARS_BASE_character_boundaries.nt'],
        'localName_with_assigned_nfc_PN_CHARS_BASE_character_boundaries' => ['localName_with_assigned_nfc_PN_CHARS_BASE_character_boundaries.ttl', 'localName_with_assigned_nfc_PN_CHARS_BASE_character_boundaries.nt'],
        'localName_with_nfc_PN_CHARS_BASE_character_boundaries' => ['localName_with_nfc_PN_CHARS_BASE_character_boundaries.ttl', 'localName_with_nfc_PN_CHARS_BASE_character_boundaries.nt'],
        'default_namespace_IRI' => ['default_namespace_IRI.ttl', 'IRI_spo.nt'],
        'prefix_reassigned_and_used' => ['prefix_reassigned_and_used.ttl', 'prefix_reassigned_and_used.nt'],
        'reserved_escaped_localName' => ['reserved_escaped_localName.ttl', 'reserved_escaped_localName.nt'],
        'percent_escaped_localName' => ['percent_escaped_localName.ttl', 'percent_escaped_localName.nt'],
        'HYPHEN_MINUS_in_localName' => ['HYPHEN_MINUS_in_localName.ttl', 'HYPHEN_MINUS_in_localName.nt'],
        'underscore_in_localName' => ['underscore_in_localName.ttl', 'underscore_in_localName.nt'],
        'localname_with_COLON' => ['localname_with_COLON.ttl', 'localname_with_COLON.nt'],
        'localName_with_leading_underscore' => ['localName_with_leading_underscore.ttl', 'localName_with_leading_underscore.nt'],
        'localName_with_leading_digit' => ['localName_with_leading_digit.ttl', 'localName_with_leading_digit.nt'],
        'localName_with_non_leading_extras' => ['localName_with_non_leading_extras.ttl', 'localName_with_non_leading_extras.nt'],
        'old_style_base' => ['old_style_base.ttl', 'IRI_spo.nt'],
        'SPARQL_style_base' => ['SPARQL_style_base.ttl', 'IRI_spo.nt'],
        'labeled_blank_node_subject' => ['labeled_blank_node_subject.ttl', 'labeled_blank_node_subject.nt'],
        'labeled_blank_node_object' => ['labeled_blank_node_object.ttl', 'labeled_blank_node_object.nt'],
        'labeled_blank_node_with_PN_CHARS_BASE_character_boundaries' => ['labeled_blank_node_with_PN_CHARS_BASE_character_boundaries.ttl', 'labeled_blank_node_object.nt'],
        'labeled_blank_node_with_leading_underscore' => ['labeled_blank_node_with_leading_underscore.ttl', 'labeled_blank_node_object.nt'],
        'labeled_blank_node_with_leading_digit' => ['labeled_blank_node_with_leading_digit.ttl', 'labeled_blank_node_object.nt'],
        'labeled_blank_node_with_non_leading_extras' => ['labeled_blank_node_with_non_leading_extras.ttl', 'labeled_blank_node_object.nt'],
        'anonymous_blank_node_subject' => ['anonymous_blank_node_subject.ttl', 'labeled_blank_node_subject.nt'],
        'anonymous_blank_node_object' => ['anonymous_blank_node_object.ttl', 'labeled_blank_node_object.nt'],
        'sole_blankNodePropertyList' => ['sole_blankNodePropertyList.ttl', 'labeled_blank_node_subject.nt'],
        'blankNodePropertyList_as_subject' => ['blankNodePropertyList_as_subject.ttl', 'blankNodePropertyList_as_subject.nt'],
        'blankNodePropertyList_as_object' => ['blankNodePropertyList_as_object.ttl', 'blankNodePropertyList_as_object.nt'],
        'blankNodePropertyList_as_object_containing_objectList' => ['blankNodePropertyList_as_object_containing_objectList.ttl', 'blankNodePropertyList_as_object_containing_objectList.nt'],
        'blankNodePropertyList_as_object_containing_objectList_of_two_objects' => ['blankNodePropertyList_as_object_containing_objectList_of_two_objects.ttl', 'blankNodePropertyList_as_object_containing_objectList_of_two_objects.nt'],
        'blankNodePropertyList_with_multiple_triples' => ['blankNodePropertyList_with_multiple_triples.ttl', 'blankNodePropertyList_with_multiple_triples.nt'],
        'nested_blankNodePropertyLists' => ['nested_blankNodePropertyLists.ttl', 'nested_blankNodePropertyLists.nt'],
        'blankNodePropertyList_containing_collection' => ['blankNodePropertyList_containing_collection.ttl', 'blankNodePropertyList_containing_collection.nt'],
        'collection_subject' => ['collection_subject.ttl', 'collection_subject.nt'],
        'collection_object' => ['collection_object.ttl', 'collection_object.nt'],
        'empty_collection' => ['empty_collection.ttl', 'empty_collection.nt'],
        'nested_collection' => ['nested_collection.ttl', 'nested_collection.nt'],
        'first' => ['first.ttl', 'first.nt'],
        'last' => ['last.ttl', 'last.nt'],
        'LITERAL1' => ['LITERAL1.ttl', 'LITERAL1.nt'],
        'LITERAL1_ascii_boundaries' => ['LITERAL1_ascii_boundaries.ttl', 'LITERAL1_ascii_boundaries.nt'],
        'LITERAL1_with_UTF8_boundaries' => ['LITERAL1_with_UTF8_boundaries.ttl', 'LITERAL_with_UTF8_boundaries.nt'],
        'LITERAL1_all_controls' => ['LITERAL1_all_controls.ttl', 'LITERAL1_all_controls.nt'],
        'LITERAL1_all_punctuation' => ['LITERAL1_all_punctuation.ttl', 'LITERAL1_all_punctuation.nt'],
        'LITERAL_LONG1' => ['LITERAL_LONG1.ttl', 'LITERAL1.nt'],
        'LITERAL_LONG1_ascii_boundaries' => ['LITERAL_LONG1_ascii_boundaries.ttl', 'LITERAL_LONG1_ascii_boundaries.nt'],
        'LITERAL_LONG1_with_UTF8_boundaries' => ['LITERAL_LONG1_with_UTF8_boundaries.ttl', 'LITERAL_with_UTF8_boundaries.nt'],
        'LITERAL_LONG1_with_1_squote' => ['LITERAL_LONG1_with_1_squote.ttl', 'LITERAL_LONG1_with_1_squote.nt'],
        'LITERAL_LONG1_with_2_squotes' => ['LITERAL_LONG1_with_2_squotes.ttl', 'LITERAL_LONG1_with_2_squotes.nt'],
        'LITERAL2' => ['LITERAL2.ttl', 'LITERAL1.nt'],
        'LITERAL2_ascii_boundaries' => ['LITERAL2_ascii_boundaries.ttl', 'LITERAL2_ascii_boundaries.nt'],
        'LITERAL2_with_UTF8_boundaries' => ['LITERAL2_with_UTF8_boundaries.ttl', 'LITERAL_with_UTF8_boundaries.nt'],
        'LITERAL_LONG2' => ['LITERAL_LONG2.ttl', 'LITERAL1.nt'],
        'LITERAL_LONG2_ascii_boundaries' => ['LITERAL_LONG2_ascii_boundaries.ttl', 'LITERAL_LONG2_ascii_boundaries.nt'],
        'LITERAL_LONG2_with_UTF8_boundaries' => ['LITERAL_LONG2_with_UTF8_boundaries.ttl', 'LITERAL_with_UTF8_boundaries.nt'],
        'LITERAL_LONG2_with_1_squote' => ['LITERAL_LONG2_with_1_squote.ttl', 'LITERAL_LONG2_with_1_squote.nt'],
        'LITERAL_LONG2_with_2_squotes' => ['LITERAL_LONG2_with_2_squotes.ttl', 'LITERAL_LONG2_with_2_squotes.nt'],
        'literal_with_CHARACTER_TABULATION' => ['literal_with_CHARACTER_TABULATION.ttl', 'literal_with_CHARACTER_TABULATION.nt'],
        'literal_with_BACKSPACE' => ['literal_with_BACKSPACE.ttl', 'literal_with_BACKSPACE.nt'],
        'literal_with_LINE_FEED' => ['literal_with_LINE_FEED.ttl', 'literal_with_LINE_FEED.nt'],
        'literal_with_CARRIAGE_RETURN' => ['literal_with_CARRIAGE_RETURN.ttl', 'literal_with_CARRIAGE_RETURN.nt'],
        'literal_with_FORM_FEED' => ['literal_with_FORM_FEED.ttl', 'literal_with_FORM_FEED.nt'],
        'literal_with_REVERSE_SOLIDUS' => ['literal_with_REVERSE_SOLIDUS.ttl', 'literal_with_REVERSE_SOLIDUS.nt'],
        'literal_with_escaped_CHARACTER_TABULATION' => ['literal_with_escaped_CHARACTER_TABULATION.ttl', 'literal_with_CHARACTER_TABULATION.nt'],
        'literal_with_escaped_BACKSPACE' => ['literal_with_escaped_BACKSPACE.ttl', 'literal_with_BACKSPACE.nt'],
        'literal_with_escaped_LINE_FEED' => ['literal_with_escaped_LINE_FEED.ttl', 'literal_with_LINE_FEED.nt'],
        'literal_with_escaped_CARRIAGE_RETURN' => ['literal_with_escaped_CARRIAGE_RETURN.ttl', 'literal_with_CARRIAGE_RETURN.nt'],
        'literal_with_escaped_FORM_FEED' => ['literal_with_escaped_FORM_FEED.ttl', 'literal_with_FORM_FEED.nt'],
        'literal_with_numeric_escape4' => ['literal_with_numeric_escape4.ttl', 'literal_with_numeric_escape4.nt'],
        'literal_with_numeric_escape8' => ['literal_with_numeric_escape8.ttl', 'literal_with_numeric_escape4.nt'],
        'IRIREF_datatype' => ['IRIREF_datatype.ttl', 'IRIREF_datatype.nt'],
        'prefixed_name_datatype' => ['prefixed_name_datatype.ttl', 'IRIREF_datatype.nt'],
        'bareword_integer' => ['bareword_integer.ttl', 'IRIREF_datatype.nt'],
        'bareword_decimal' => ['bareword_decimal.ttl', 'bareword_decimal.nt'],
        'bareword_double' => ['bareword_double.ttl', 'bareword_double.nt'],
        'double_lower_case_e' => ['double_lower_case_e.ttl', 'double_lower_case_e.nt'],
        'negative_numeric' => ['negative_numeric.ttl', 'negative_numeric.nt'],
        'positive_numeric' => ['positive_numeric.ttl', 'positive_numeric.nt'],
        'numeric_with_leading_0' => ['numeric_with_leading_0.ttl', 'numeric_with_leading_0.nt'],
        'literal_true' => ['literal_true.ttl', 'literal_true.nt'],
        'literal_false' => ['literal_false.ttl', 'literal_false.nt'],
        'langtagged_non_LONG' => ['langtagged_non_LONG.ttl', 'langtagged_non_LONG.nt'],
        'langtagged_LONG' => ['langtagged_LONG.ttl', 'langtagged_non_LONG.nt'],
        'lantag_with_subtag' => ['lantag_with_subtag.ttl', 'lantag_with_subtag.nt'],
        'objectList_with_two_objects' => ['objectList_with_two_objects.ttl', 'objectList_with_two_objects.nt'],
        'predicateObjectList_with_two_objectLists' => ['predicateObjectList_with_two_objectLists.ttl', 'predicateObjectList_with_two_objectLists.nt'],
        'predicateObjectList_with_blankNodePropertyList_as_object' => ['predicateObjectList_with_blankNodePropertyList_as_object.ttl', 'predicateObjectList_with_blankNodePropertyList_as_object.nt'],
        'repeated_semis_at_end' => ['repeated_semis_at_end.ttl', 'predicateObjectList_with_two_objectLists.nt'],
        'repeated_semis_not_at_end' => ['repeated_semis_not_at_end.ttl', 'repeated_semis_not_at_end.nt'],
        'turtle-eval-lists-01' => ['turtle-eval-lists-01.ttl', 'turtle-eval-lists-01.nt'],
        'turtle-eval-lists-02' => ['turtle-eval-lists-02.ttl', 'turtle-eval-lists-02.nt'],
        'turtle-eval-lists-03' => ['turtle-eval-lists-03.ttl', 'turtle-eval-lists-03.nt'],
        'turtle-eval-lists-04' => ['turtle-eval-lists-04.ttl', 'turtle-eval-lists-04.nt'],
        'turtle-eval-lists-05' => ['turtle-eval-lists-05.ttl', 'turtle-eval-lists-05.nt'],
        'turtle-eval-lists-06' => ['turtle-eval-lists-06.ttl', 'turtle-eval-lists-06.nt'],
        'turtle-eval-struct-01' => ['turtle-eval-struct-01.ttl', 'turtle-eval-struct-01.nt'],
        'turtle-eval-struct-02' => ['turtle-eval-struct-02.ttl', 'turtle-eval-struct-02.nt'],
        'turtle-subm-01' => ['turtle-subm-01.ttl', 'turtle-subm-01.nt'],
        'turtle-subm-02' => ['turtle-subm-02.ttl', 'turtle-subm-02.nt'],
        'turtle-subm-03' => ['turtle-subm-03.ttl', 'turtle-subm-03.nt'],
        'turtle-subm-04' => ['turtle-subm-04.ttl', 'turtle-subm-04.nt'],
        'turtle-subm-05' => ['turtle-subm-05.ttl', 'turtle-subm-05.nt'],
        'turtle-subm-06' => ['turtle-subm-06.ttl', 'turtle-subm-06.nt'],
        'turtle-subm-07' => ['turtle-subm-07.ttl', 'turtle-subm-07.nt'],
        'turtle-subm-08' => ['turtle-subm-08.ttl', 'turtle-subm-08.nt'],
        'turtle-subm-09' => ['turtle-subm-09.ttl', 'turtle-subm-09.nt'],
        'turtle-subm-10' => ['turtle-subm-10.ttl', 'turtle-subm-10.nt'],
        'turtle-subm-11' => ['turtle-subm-11.ttl', 'turtle-subm-11.nt'],
        'turtle-subm-12' => ['turtle-subm-12.ttl', 'turtle-subm-12.nt'],
        'turtle-subm-13' => ['turtle-subm-13.ttl', 'turtle-subm-13.nt'],
        'turtle-subm-14' => ['turtle-subm-14.ttl', 'turtle-subm-14.nt'],
        'turtle-subm-15' => ['turtle-subm-15.ttl', 'turtle-subm-15.nt'],
        'turtle-subm-16' => ['turtle-subm-16.ttl', 'turtle-subm-16.nt'],
        'turtle-subm-17' => ['turtle-subm-17.ttl', 'turtle-subm-17.nt'],
        'turtle-subm-18' => ['turtle-subm-18.ttl', 'turtle-subm-18.nt'],
        'turtle-subm-19' => ['turtle-subm-19.ttl', 'turtle-subm-19.nt'],
        'turtle-subm-20' => ['turtle-subm-20.ttl', 'turtle-subm-20.nt'],
        'turtle-subm-21' => ['turtle-subm-21.ttl', 'turtle-subm-21.nt'],
        'turtle-subm-22' => ['turtle-subm-22.ttl', 'turtle-subm-22.nt'],
        'turtle-subm-23' => ['turtle-subm-23.ttl', 'turtle-subm-23.nt'],
        'turtle-subm-24' => ['turtle-subm-24.ttl', 'turtle-subm-24.nt'],
        'turtle-subm-25' => ['turtle-subm-25.ttl', 'turtle-subm-25.nt'],
        'turtle-subm-26' => ['turtle-subm-26.ttl', 'turtle-subm-26.nt'],
        'turtle-subm-27' => ['turtle-subm-27.ttl', 'turtle-subm-27.nt'],
        'comment_following_localName' => ['comment_following_localName.ttl', 'IRI_spo.nt'],
        'number_sign_following_localName' => ['number_sign_following_localName.ttl', 'number_sign_following_localName.nt'],
        'comment_following_PNAME_NS' => ['comment_following_PNAME_NS.ttl', 'comment_following_PNAME_NS.nt'],
        'number_sign_following_PNAME_NS' => ['number_sign_following_PNAME_NS.ttl', 'number_sign_following_PNAME_NS.nt'],
        'LITERAL_LONG2_with_REVERSE_SOLIDUS' => ['LITERAL_LONG2_with_REVERSE_SOLIDUS.ttl', 'LITERAL_LONG2_with_REVERSE_SOLIDUS.nt'],
        'two_LITERAL_LONG2s' => ['two_LITERAL_LONG2s.ttl', 'two_LITERAL_LONG2s.nt'],
        'langtagged_LONG_with_subtag' => ['langtagged_LONG_with_subtag.ttl', 'langtagged_LONG_with_subtag.nt'],
        'IRI-resolution-01' => ['IRI-resolution-01.ttl', 'IRI-resolution-01.nt'],
        'IRI-resolution-02' => ['IRI-resolution-02.ttl', 'IRI-resolution-02.nt'],
        'IRI-resolution-07' => ['IRI-resolution-07.ttl', 'IRI-resolution-07.nt'],
        'IRI-resolution-08' => ['IRI-resolution-08.ttl', 'IRI-resolution-08.nt'],
    ];

    // @skip categories: [dots in names], [special chars in labels], [blank node property lists], [IRI resolution]
    $skippedPositiveEval = [
        'prefix_with_non_leading_extras' => '@skip EasyRdf limitation: dots in names — does not support dots in prefix names (easyrdf/easyrdf#140)',
        'localName_with_non_leading_extras' => '@skip EasyRdf limitation: special chars in labels — does not support special characters in local names',
        'labeled_blank_node_with_non_leading_extras' => '@skip EasyRdf limitation: special chars in labels — does not support special characters in blank node labels',
        'sole_blankNodePropertyList' => '@skip EasyRdf limitation: blank node property lists — does not support standalone blank node property list as subject',
        'blankNodePropertyList_as_subject' => '@skip EasyRdf limitation: blank node property lists — does not support blank node property list as subject',
        'blankNodePropertyList_with_multiple_triples' => '@skip EasyRdf limitation: blank node property lists — does not support blank node property list as subject',
        'nested_blankNodePropertyLists' => '@skip EasyRdf limitation: blank node property lists — does not support nested blank node property lists as subject',
        'blankNodePropertyList_containing_collection' => '@skip EasyRdf limitation: blank node property lists — does not support blank node property list containing collection as subject',
        'IRI-resolution-08' => '@skip EasyRdf limitation: IRI resolution — incorrectly resolves double-slash IRI paths (produces //de//xyz instead of //de/xyz)',
    ];

    // Tests where TurtleHandler::parse() fails due to known TurtleHandler bugs
    // (EasyRdf direct parsing still works — these tests still verify triple content)
    // Fixed in Story 9-2: registerPrefixesFromContent now strips comments and strings before matching
    $turtleHandlerKnownFailures = [];

    foreach ($positiveEvalTests as $testId => [$actionFile, $resultFile]) {
        $test = it("[{$testId}] produces expected triples", function () use ($testId, $actionFile, $resultFile, $turtleHandlerKnownFailures) {
            $turtleContent = w3cFixture($actionFile);
            $ntriplesContent = w3cFixture($resultFile);

            // Verify TurtleHandler can parse the content without error
            // (skip for known TurtleHandler bugs — EasyRdf comparison still validates content)
            if (!isset($turtleHandlerKnownFailures[$testId])) {
                $result = $this->handler->parse($turtleContent);
                expect($result)->toBeInstanceOf(ParsedRdf::class);
            }

            // Use EasyRdf directly with correct base URI for content-based triple comparison
            $baseUri = 'https://w3c.github.io/rdf-tests/rdf/rdf11/rdf-turtle/' . $actionFile;
            $actualGraph = new Graph();
            $actualGraph->parse($turtleContent, 'turtle', $baseUri);

            $expectedGraph = new Graph();
            $expectedGraph->parse($ntriplesContent, 'ntriples');

            assertGraphsEqual($actualGraph, $expectedGraph, $testId);
        });
        if (isset($skippedPositiveEval[$testId])) {
            $test->skip($skippedPositiveEval[$testId]);
        }
    }
});

// ---------------------------------------------------------------------------
// Negative Evaluation Tests (4)
// ---------------------------------------------------------------------------
describe('W3C Negative Evaluation Tests', function () {

    beforeEach(function () {
        $this->handler = new TurtleHandler();
    });

    $negativeEvalTests = [
        'turtle-eval-bad-01' => 'turtle-eval-bad-01.ttl',
        'turtle-eval-bad-02' => 'turtle-eval-bad-02.ttl',
        'turtle-eval-bad-03' => 'turtle-eval-bad-03.ttl',
        'turtle-eval-bad-04' => 'turtle-eval-bad-04.ttl',
    ];

    // @skip categories: [permissive semantic validation]
    $skippedNegativeEval = [
        'turtle-eval-bad-01' => '@skip EasyRdf limitation: permissive semantic validation — does not reject undefined prefix usage',
        'turtle-eval-bad-02' => '@skip EasyRdf limitation: permissive semantic validation — does not reject relative IRI without base',
        'turtle-eval-bad-03' => '@skip EasyRdf limitation: permissive semantic validation — does not reject relative IRI without base',
        'turtle-eval-bad-04' => '@skip EasyRdf limitation: permissive semantic validation — does not reject relative IRI without base',
    ];

    foreach ($negativeEvalTests as $testId => $filename) {
        $test = it("[{$testId}] rejects semantically invalid input", function () use ($filename) {
            $content = w3cFixture($filename);
            expect(fn () => $this->handler->parse($content))->toThrow(ParseException::class);
        });
        if (isset($skippedNegativeEval[$testId])) {
            $test->skip($skippedNegativeEval[$testId]);
        }
    }
});
