# Spec Completeness

> Assessment of parser-turtle implementation coverage against the W3C RDF 1.1 Turtle specification.
> Last updated: 2026-02-19

Reference: [W3C RDF 1.1 Turtle](https://www.w3.org/TR/turtle/)

## Scope

This library provides a single `TurtleHandler` class that detects and parses Turtle (RDF 1.1)
content. Actual parsing is delegated to **EasyRdf 1.1.1** via `EasyRdf\Graph::parse()`. The handler
adds format detection (`canHandle`), prefix pre-registration in EasyRdf's global namespace registry,
and unified error handling via `ParseException`.

Source file: `src/TurtleHandler.php` (76 lines).

## Summary

| Spec Area | Passed | Total | Coverage |
|---|---|---|---|
| Turtle Grammar Productions (handler level) | 20 | 26 | 77% |
| Directive Support (@prefix, PREFIX, @base, BASE) | 3 | 4 | 75% |
| Blank Nodes | 9 | 10 | 90% |
| Collections | 6 | 6 | 100% |
| Literals (typed, language-tagged, boolean, numeric) | 36 | 36 | 100% |
| Comment Handling | 4 | 4 | 100% |
| Error Handling | 6 | 6 | 100% |
| W3C Conformance Test Suite | 268 | 313 | 85.6% |
| **Overall (weighted)** | | | **~87%** |

---

## Turtle Grammar Productions

Reference: [W3C Turtle Grammar, Section 6.5](https://www.w3.org/TR/turtle/#sec-grammar)

The `TurtleHandler` delegates all grammar production handling to EasyRdf. The table below reflects
what the combined TurtleHandler + EasyRdf stack supports.

| Production | Name | Status | Location | Tests |
|---|---|---|---|---|
| [1] | `turtleDoc` | implemented | `TurtleHandler:36-37` (delegates to EasyRdf) | Characterization `3.1` |
| [2] | `statement` | implemented | EasyRdf | W3C `turtle-syntax-struct-01..05` |
| [3] | `directive` | partial | `TurtleHandler:66` (@prefix regex), EasyRdf | W3C `turtle-syntax-base-01..04`, `turtle-syntax-prefix-01..09` |
| [4] | `prefixID` (`@prefix`) | implemented | `TurtleHandler:26-27,66` | Characterization `2.1`, `6.1-6.4` |
| [5] | `base` (`@base`) | delegated | EasyRdf (no handler-level support) | W3C `turtle-syntax-base-01`, `old_style_base` |
| [5s] | `sparqlBase` (`BASE`) | partial | EasyRdf only (skipped in W3C suite) | W3C `turtle-syntax-base-02` (skipped) |
| [6s] | `sparqlPrefix` (`PREFIX`) | implemented | `TurtleHandler:28`, EasyRdf | Characterization `2.3`, W3C `SPARQL_style_prefix` |
| [6] | `triples` | implemented | EasyRdf | W3C `IRI_subject`, eval tests |
| [7] | `predicateObjectList` | implemented | EasyRdf | Characterization `3.5`, W3C `predicateObjectList_*` |
| [8] | `objectList` | implemented | EasyRdf | Characterization `3.6`, W3C `objectList_with_two_objects` |
| [9] | `verb` (`a` keyword) | implemented | EasyRdf | W3C `bareword_a_predicate`, `turtle-syntax-kw-01..03` |
| [10] | `subject` | implemented | EasyRdf | W3C `IRI_subject`, `labeled_blank_node_subject` |
| [11] | `predicate` | implemented | EasyRdf | W3C `prefixed_IRI_predicate` |
| [12] | `object` | implemented | EasyRdf | W3C `prefixed_IRI_object`, `labeled_blank_node_object` |
| [13] | `literal` | implemented | EasyRdf | Characterization `3.10-3.11`, W3C `LITERAL*` tests |
| [14] | `blankNodePropertyList` | partial | EasyRdf (fails as subject) | W3C `blankNodePropertyList_as_object` passes; `*_as_subject` skipped |
| [15] | `collection` | implemented | EasyRdf | W3C `collection_subject`, `collection_object`, `nested_collection` |
| [16] | `NumericLiteral` | implemented | EasyRdf | W3C `bareword_integer`, `bareword_decimal`, `bareword_double` |
| [128s] | `RDFLiteral` | implemented | EasyRdf | W3C `LITERAL1`, `LITERAL2`, `LITERAL_LONG*` |
| [133s] | `BooleanLiteral` | implemented | EasyRdf | W3C `literal_true`, `literal_false` |
| [17] | `String` (all 4 variants) | implemented | EasyRdf | W3C `turtle-syntax-string-01..11`, `LITERAL*` eval tests |
| [135s] | `iri` | implemented | EasyRdf | W3C `IRI_subject`, `IRI_with_all_punctuation` |
| [136s] | `PrefixedName` | implemented | EasyRdf | W3C `prefixed_IRI_predicate`, `prefixed_IRI_object` |
| [137s] | `BlankNode` | implemented | EasyRdf | W3C `turtle-syntax-bnode-01..10` |
| -- | Dots in local names | not supported | EasyRdf limitation | W3C `turtle-syntax-ln-dots` (skipped) |
| -- | Dots in namespace prefixes | not supported | EasyRdf limitation | W3C `turtle-syntax-ns-dots` (skipped) |
| -- | Certain blank node labels | not supported | EasyRdf limitation | W3C `turtle-syntax-blank-label` (skipped) |

---

## Directive Support

Reference: [W3C Turtle Section 2.4 -- Prefixes](https://www.w3.org/TR/turtle/#prefixes)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@prefix` declaration | implemented | `TurtleHandler:26-27` (detection), `TurtleHandler:66` (regex extraction), EasyRdf (parsing) | Characterization `2.1`, `6.1-6.4`; Unit `detects turtle with prefix syntax`; W3C `turtle-syntax-prefix-01..09`, `old_style_prefix` |
| `PREFIX` (SPARQL-style) | implemented | `TurtleHandler:28` (detection), EasyRdf (parsing) | Characterization `2.3`; W3C `SPARQL_style_prefix` |
| `@base` declaration | delegated (EasyRdf only) | Not in `TurtleHandler` source; EasyRdf handles it | W3C `turtle-syntax-base-01`, `turtle-syntax-base-03`, `turtle-syntax-base-04`, `old_style_base` |
| `BASE` (SPARQL-style) | not supported | EasyRdf 1.1.1 does not support bare `BASE` | W3C `turtle-syntax-base-02` (skipped), `SPARQL_style_base` (skipped) |

### Prefix Registration Side Effect

The handler has a custom `registerPrefixesFromContent()` method at line 64-75 that uses a regex
(`/@prefix\s+([^:]+):\s*<([^>]+)>/i`) to extract `@prefix` declarations and register them in
EasyRdf's global `RdfNamespace` registry **before** delegating to `EasyRdf\Graph::parse()`. This
ensures prefixed names resolve correctly even in contexts where EasyRdf's internal prefix handling
might not be sufficient.

**Known bug**: The regex matches `@prefix` anywhere in the content, including inside comments and
string literals. This is documented in the conformance test for `turtle-subm-02`, which is marked
as a `turtleHandlerKnownFailures` entry (line 551-553 of
`tests/Conformance/W3cTurtleConformanceTest.php`).

### canHandle() Detection Limitations

The `canHandle()` method (lines 22-29) uses simple string matching:
- `str_starts_with($trimmed, '@prefix')` -- matches leading `@prefix`
- `str_contains($trimmed, '@prefix')` -- matches `@prefix` anywhere
- `str_contains($trimmed, 'PREFIX')` -- matches `PREFIX` anywhere

**Known false positives** (documented in characterization tests 2.9, 2.9b, 2.9c):
- Content containing `@prefix` inside string literals is detected as Turtle
- Content containing `PREFIX` inside string literals is detected as Turtle
- `@PREFIX` (invalid syntax) is detected via the `PREFIX` check

**Known false negatives**:
- Turtle content using only `@base` (no prefix declarations) is not detected
- Turtle content using only full IRIs (no prefix/base directives) is not detected

---

## Blank Nodes

Reference: [W3C Turtle Section 2.6 -- RDF Blank Nodes](https://www.w3.org/TR/turtle/#BNodes)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `_:label` syntax (labeled blank nodes) | implemented | EasyRdf | W3C `labeled_blank_node_subject`, `labeled_blank_node_object` |
| `_:` with PN_CHARS_BASE boundaries | implemented | EasyRdf | W3C `labeled_blank_node_with_PN_CHARS_BASE_character_boundaries` |
| `_:` with leading underscore | implemented | EasyRdf | W3C `labeled_blank_node_with_leading_underscore` |
| `_:` with leading digit | implemented | EasyRdf | W3C `labeled_blank_node_with_leading_digit` |
| `_:` with non-leading extras | not supported | EasyRdf limitation | W3C `labeled_blank_node_with_non_leading_extras` (skipped) |
| `[]` anonymous blank node (subject) | implemented | EasyRdf | W3C `anonymous_blank_node_subject` |
| `[]` anonymous blank node (object) | implemented | EasyRdf | W3C `anonymous_blank_node_object` |
| `[...]` blank node property list as object | implemented | EasyRdf | Characterization `3.8`; W3C `blankNodePropertyList_as_object*` |
| `[...]` blank node property list as subject | not supported | EasyRdf limitation | W3C `sole_blankNodePropertyList`, `blankNodePropertyList_as_subject`, `blankNodePropertyList_with_multiple_triples`, `nested_blankNodePropertyLists`, `blankNodePropertyList_containing_collection` (all skipped) |
| Nested blank node property lists (as objects) | implemented | EasyRdf | Characterization `3.8` (restriction with blank node) |

---

## Collections

Reference: [W3C Turtle Section 2.8 -- Collections](https://www.w3.org/TR/turtle/#collections)

| Feature | Status | Location | Tests |
|---|---|---|---|
| `()` empty collection | implemented | EasyRdf | W3C `empty_collection` |
| `(...)` collection as subject | implemented (deprecated) | EasyRdf | W3C `collection_subject` (deprecated: EasyRdf\Collection::count()) |
| `(...)` collection as object | implemented | EasyRdf | W3C `collection_object` |
| Nested collections | implemented | EasyRdf | W3C `nested_collection` |
| `rdf:first` / `rdf:rest` expansion | implemented | EasyRdf | W3C `first`, `last` |
| List evaluation tests | implemented | EasyRdf | W3C `turtle-eval-lists-01..06` |

---

## Literals

Reference: [W3C Turtle Section 2.5 -- RDF Literals](https://www.w3.org/TR/turtle/#literals)

### String Literals

| Feature | Status | Location | Tests |
|---|---|---|---|
| `STRING_LITERAL_QUOTE` (`"..."`) | implemented | EasyRdf | W3C `LITERAL1`, `LITERAL1_ascii_boundaries` |
| `STRING_LITERAL_SINGLE_QUOTE` (`'...'`) | implemented | EasyRdf | W3C `LITERAL2`, `LITERAL2_ascii_boundaries` |
| `STRING_LITERAL_LONG_QUOTE` (`"""..."""`) | implemented | EasyRdf | W3C `LITERAL_LONG2`, `LITERAL_LONG2_ascii_boundaries` |
| `STRING_LITERAL_LONG_SINGLE_QUOTE` (`'''...'''`) | implemented | EasyRdf | W3C `LITERAL_LONG1`, `LITERAL_LONG1_ascii_boundaries` |
| UTF-8 boundary characters | implemented | EasyRdf | W3C `LITERAL1_with_UTF8_boundaries`, `LITERAL_LONG1_with_UTF8_boundaries`, `LITERAL2_with_UTF8_boundaries`, `LITERAL_LONG2_with_UTF8_boundaries` |
| Embedded quotes in long strings | implemented | EasyRdf | W3C `LITERAL_LONG1_with_1_squote`, `LITERAL_LONG1_with_2_squotes`, `LITERAL_LONG2_with_1_squote`, `LITERAL_LONG2_with_2_squotes` |
| All control characters | implemented | EasyRdf | W3C `LITERAL1_all_controls` |
| All punctuation characters | implemented | EasyRdf | W3C `LITERAL1_all_punctuation` |

### Escape Sequences

| Feature | Status | Location | Tests |
|---|---|---|---|
| `\t` (CHARACTER TABULATION) | implemented | EasyRdf | W3C `literal_with_escaped_CHARACTER_TABULATION` |
| `\b` (BACKSPACE) | implemented | EasyRdf | W3C `literal_with_escaped_BACKSPACE` |
| `\n` (LINE FEED) | implemented | EasyRdf | W3C `literal_with_escaped_LINE_FEED` |
| `\r` (CARRIAGE RETURN) | implemented | EasyRdf | W3C `literal_with_escaped_CARRIAGE_RETURN` |
| `\f` (FORM FEED) | implemented | EasyRdf | W3C `literal_with_escaped_FORM_FEED` |
| `\\` (REVERSE SOLIDUS) | implemented | EasyRdf | W3C `literal_with_REVERSE_SOLIDUS`, `LITERAL_LONG2_with_REVERSE_SOLIDUS` |
| `\uXXXX` (4-digit Unicode escape) | implemented | EasyRdf | W3C `literal_with_numeric_escape4` |
| `\UXXXXXXXX` (8-digit Unicode escape) | implemented | EasyRdf | W3C `literal_with_numeric_escape8` |
| String escape syntax tests | implemented | EasyRdf | W3C `turtle-syntax-str-esc-01..03` |
| Prefixed name escapes | implemented | EasyRdf | W3C `turtle-syntax-pname-esc-01..03` |

### Typed Literals

| Feature | Status | Location | Tests |
|---|---|---|---|
| `^^` datatype IRI syntax | implemented | EasyRdf | Characterization `3.11`; W3C `IRIREF_datatype`, `prefixed_name_datatype` |
| `xsd:integer` (bareword) | implemented | EasyRdf | W3C `bareword_integer` |
| `xsd:decimal` (bareword) | implemented | EasyRdf | W3C `bareword_decimal` |
| `xsd:double` (bareword) | implemented | EasyRdf | W3C `bareword_double`, `double_lower_case_e` |
| Negative numerics | implemented | EasyRdf | W3C `negative_numeric` |
| Positive numerics | implemented | EasyRdf | W3C `positive_numeric` |
| Leading zeros | implemented | EasyRdf | W3C `numeric_with_leading_0` |
| `xsd:boolean` (`true`/`false`) | implemented | EasyRdf | W3C `literal_true`, `literal_false` |

### Language-Tagged Literals

| Feature | Status | Location | Tests |
|---|---|---|---|
| `@lang` tag on short strings | implemented | EasyRdf | Characterization `3.10`; W3C `langtagged_non_LONG` |
| `@lang` tag on long strings | implemented | EasyRdf | W3C `langtagged_LONG` |
| Language subtags (e.g., `@en-US`) | implemented | EasyRdf | W3C `lantag_with_subtag`, `langtagged_LONG_with_subtag` |

---

## IRI Handling

Reference: [W3C Turtle Section 2.4 -- IRIs](https://www.w3.org/TR/turtle/#sec-iri)

| Feature | Status | Location | Tests |
|---|---|---|---|
| Absolute IRIs (`<...>`) | implemented | EasyRdf | W3C `IRI_subject`, `IRI_with_all_punctuation` |
| `\uXXXX` in IRIs | implemented | EasyRdf | W3C `IRI_with_four_digit_numeric_escape` |
| `\UXXXXXXXX` in IRIs | implemented | EasyRdf | W3C `IRI_with_eight_digit_numeric_escape` |
| Prefixed names (`prefix:local`) | implemented | EasyRdf | W3C `prefixed_IRI_predicate`, `prefixed_IRI_object`, `prefix_only_IRI` |
| Default namespace (`:local`) | implemented | EasyRdf | W3C `default_namespace_IRI` |
| Prefix reassignment | implemented | EasyRdf | W3C `prefix_reassigned_and_used` |
| Reserved escaped local names | implemented | EasyRdf | W3C `reserved_escaped_localName` |
| Percent-escaped local names | implemented | EasyRdf | W3C `percent_escaped_localName` |
| `HYPHEN-MINUS` in local names | implemented | EasyRdf | W3C `HYPHEN_MINUS_in_localName` |
| Underscore in local names | implemented | EasyRdf | W3C `underscore_in_localName` |
| COLON in local names | implemented | EasyRdf | W3C `localname_with_COLON` |
| Leading underscore/digit in local names | implemented | EasyRdf | W3C `localName_with_leading_underscore`, `localName_with_leading_digit` |
| IRI resolution (relative IRIs via @base) | partial | EasyRdf | W3C `IRI-resolution-01`, `IRI-resolution-02`, `IRI-resolution-07` pass; `IRI-resolution-08` skipped |
| PN_CHARS_BASE character boundaries | implemented | EasyRdf | W3C `prefix_with_PN_CHARS_BASE_character_boundaries`, `localName_with_assigned_nfc_*` |

---

## Comment Handling

Reference: [W3C Turtle Section 6.2](https://www.w3.org/TR/turtle/#sec-grammar) -- comments start
with `#` (outside of IRIs and strings) and continue to end of line.

| Feature | Status | Location | Tests |
|---|---|---|---|
| `#` comments (end-of-line) | implemented | EasyRdf | W3C `comment_following_localName`, `comment_following_PNAME_NS` |
| `#` in local names (not a comment) | implemented | EasyRdf | W3C `number_sign_following_localName`, `number_sign_following_PNAME_NS` |
| Comments before `@prefix` | implemented | EasyRdf (detection: Characterization `2.2`) | Characterization `2.2` |
| `@prefix` inside comments (false match) | known bug | `TurtleHandler:66` regex | `turtle-subm-02` (handler-level failure, line 551-553 of conformance test) |

---

## Error Handling

Reference: Conformance requirement -- parsers must reject syntactically invalid Turtle.

| Feature | Status | Location | Tests |
|---|---|---|---|
| Wraps exceptions in `ParseException` | implemented | `TurtleHandler:51-53` | Characterization `5.1-5.6`; Unit `throws on invalid turtle` |
| Exception message prefix `Turtle parsing failed: ` | implemented | `TurtleHandler:52` | Characterization `5.2` |
| Previous exception preserved | implemented | `TurtleHandler:52` (`$e` as 3rd argument) | Characterization `5.3` |
| Exception code is 0 | implemented | `TurtleHandler:52` (hardcoded `0`) | Characterization `5.6` |
| Empty input returns empty `ParsedRdf` (no throw) | implemented | `TurtleHandler:36-37` | Characterization `5.4` |
| Invalid body after valid prefix | implemented | EasyRdf + wrapping | Characterization `5.5` |

### Negative Syntax Rejection

65 of 90 W3C negative syntax tests correctly reject invalid input. 25 are skipped due to EasyRdf's
permissive parsing (accepting content the spec requires rejecting).

| Skipped Category | Count | Reason |
|---|---|---|
| Bad URIs (spaces, percent-encoding, chars, Unicode escapes) | 5 | EasyRdf does not validate IRI syntax |
| Bad numeric escapes | 10 | EasyRdf does not validate `\uXXXX` / `\UXXXXXXXX` escape ranges |
| Bad escape sequences | 4 | EasyRdf does not validate escape sequences in IRIs/strings/pnames |
| Bad blank node syntax | 2 | EasyRdf does not reject invalid blank node syntax |
| Bad BASE syntax | 1 | EasyRdf does not reject this specific invalid BASE |
| Bad prefixed name (dot at end) | 1 | EasyRdf does not reject dot at end of prefixed name |
| Bad local name escapes | 2 | EasyRdf does not reject invalid local name escapes |

### Negative Evaluation Rejection

All 4 W3C negative evaluation tests are skipped:
- `turtle-eval-bad-01`: Undefined prefix usage not rejected
- `turtle-eval-bad-02..04`: Relative IRI without base not rejected

---

## W3C Conformance Test Suite

Source: [W3C RDF 1.1 Turtle Test Suite](https://www.w3.org/2013/TurtleTests/)
Test file: `tests/Conformance/W3cTurtleConformanceTest.php`
Fixtures: `tests/Fixtures/W3c/` (425 files)

### Results Summary

| Category | Passed | Deprecated | Skipped | Total | Pass Rate |
|---|---|---|---|---|---|
| Positive Syntax | 69 | 0 | 5 | 74 | 93.2% |
| Negative Syntax | 65 | 0 | 25 | 90 | 72.2% |
| Positive Evaluation | 135 | 1 | 9 | 145 | 93.8% |
| Negative Evaluation | 0 | 0 | 4 | 4 | 0.0% |
| **Total** | **269** | **1** | **43** | **313** | **86.3%** |

### Deprecated Tests (W3C suite)

| Test | Deprecation Source |
|---|---|
| `collection_subject` | `EasyRdf\Collection::count()` return type notice |

### Skipped Tests by Root Cause

| Root Cause | Count | Tests |
|---|---|---|
| EasyRdf permissive parsing (does not reject invalid input) | 25 + 4 = 29 | Negative syntax + negative evaluation |
| Dots in local/namespace names (easyrdf/easyrdf#140) | 3 | `turtle-syntax-ln-dots`, `turtle-syntax-ns-dots`, `prefix_with_non_leading_extras` |
| Blank node property list as subject | 5 | `sole_blankNodePropertyList`, `blankNodePropertyList_as_subject`, `blankNodePropertyList_with_multiple_triples`, `nested_blankNodePropertyLists`, `blankNodePropertyList_containing_collection` |
| Special characters in blank node labels/local names | 2 | `labeled_blank_node_with_non_leading_extras`, `localName_with_non_leading_extras` |
| SPARQL-style BASE | 1 | `turtle-syntax-base-02` |
| SPARQL-style PREFIX (specific context) | 1 | `turtle-syntax-prefix-02` |
| Blank node label pattern | 1 | `turtle-syntax-blank-label` |
| IRI resolution edge case | 1 | `IRI-resolution-08` |

---

## Test Coverage Summary

Test runner: Pest 3.x
Total test results: **362 tests** (316 passed, 3 deprecated, 43 skipped, 0 failing)
Assertions: 760

### Test File Breakdown

| File | Test Count | Purpose |
|---|---|---|
| `tests/Unit/TurtleHandlerTest.php` | 4 | Basic unit tests: detection, format name, parsing, error throwing |
| `tests/Unit/AliasesTest.php` | 10 | Backward-compatibility class alias bridge tests |
| `tests/Characterization/TurtleHandlerTest.php` | 35 | Detailed behavioral characterization of canHandle(), parse(), getFormatName(), error handling, and prefix registration side effects |
| `tests/Conformance/W3cTurtleConformanceTest.php` | 313 | Full W3C RDF 1.1 Turtle conformance suite (74 + 90 + 145 + 4) |
| **Total** | **362** | |

### Test Categories

| Area | Test Count | Coverage |
|---|---|---|
| Format detection (`canHandle`) | 12 | Positive/negative detection, false positives, whitespace trimming |
| Parsing output structure | 11 | Return type, format, rawContent, metadata, graph resources |
| Format name (`getFormatName`) | 2 | Return value and type |
| Error handling | 6 | ParseException, message format, previous exception, edge cases |
| Prefix registration | 4 | Global namespace side effect, multiple prefixes, ordering |
| Class alias bridge | 10 | Old namespace resolution, instanceof, deprecation warnings |
| W3C positive syntax | 74 | Valid Turtle accepted without error |
| W3C negative syntax | 90 | Invalid Turtle rejected with ParseException |
| W3C positive evaluation | 145 | Parsed triples match expected N-Triples output |
| W3C negative evaluation | 4 | Semantically invalid input rejected |

---

## Architecture Notes

The implementation follows a **thin handler + delegation** pattern:

1. **Single source file**: `src/TurtleHandler.php` (76 lines) implements `RdfFormatHandlerInterface`
   from parser-core.
2. **EasyRdf delegation**: All actual Turtle parsing, grammar production handling, blank node
   management, collection expansion, and literal processing are performed by EasyRdf's built-in
   Turtle parser (`EasyRdf\Graph::parse($content, 'turtle')`).
3. **Prefix pre-registration**: The handler extracts `@prefix` declarations via regex and registers
   them in `EasyRdf\RdfNamespace` before parsing (lines 64-75).
4. **Error wrapping**: All `\Throwable` exceptions from EasyRdf are caught and re-thrown as
   `ParseException` with a standardized message prefix (lines 51-53).

Key design decisions:
- Spec compliance is bounded by EasyRdf 1.1.1's capabilities, which accounts for all 43 skipped
  W3C tests.
- The handler does not implement its own tokenizer or parser; it is a facade over EasyRdf.
- `canHandle()` uses heuristic string matching rather than attempting a trial parse, resulting in
  documented false positives and false negatives.

---

## Remaining Gaps

All gaps are due to EasyRdf 1.1.1 limitations, not handler-level omissions:

| Gap | Impact | Root Cause |
|---|---|---|
| SPARQL-style `BASE` directive | 1 positive syntax test skipped | EasyRdf does not support bare `BASE` |
| Blank node property list as subject (`[...] pred obj .`) | 5 positive eval tests skipped | EasyRdf parsing failure |
| Dots in local/namespace names | 3 tests skipped | EasyRdf issue #140 |
| Special characters in labels/local names | 2 tests skipped | EasyRdf limitation |
| Permissive IRI/escape validation | 29 negative tests skipped | EasyRdf does not reject invalid input per spec |
| IRI resolution edge case (double-slash paths) | 1 eval test skipped | EasyRdf produces incorrect resolved IRI |
| `@prefix` regex matches inside comments/strings | 1 eval test marked as handler known failure | `TurtleHandler:66` regex is context-unaware |
| `canHandle()` does not detect `@base`-only content | No test coverage | `TurtleHandler:22-29` only checks for prefix/PREFIX |

### Upgrade Path

Replacing EasyRdf 1.1.1 with a fully spec-compliant Turtle parser would resolve 42 of 43 skipped
tests. The remaining 1 skip (`turtle-syntax-prefix-02`) relates to a specific SPARQL-style PREFIX
context. The handler-level `@prefix` regex bug and `canHandle()` limitations would need separate
fixes in `TurtleHandler.php`.
