# Spec Completeness

> Assessment of parser-turtle implementation coverage against the W3C RDF 1.1 Turtle specification.
> Last updated: 2026-02-20

Reference: [W3C RDF 1.1 Turtle](https://www.w3.org/TR/turtle/)

## Scope

This library provides a single `TurtleHandler` class that detects and parses Turtle (RDF 1.1)
content. Actual parsing is delegated to **EasyRdf 1.1.1** via `EasyRdf\Graph::parse()`. The handler
adds format detection (`canHandle`), prefix pre-registration in EasyRdf's global namespace registry,
pre-parse validation of IRI and string escape sequences, and unified error handling via `ParseException`.

Source file: `src/TurtleHandler.php`.

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
| W3C Conformance Test Suite | 287 | 313 | 91.7% |
| **Overall (weighted)** | | | **~92%** |

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

**Fixed in Story 9-2**: The regex previously matched `@prefix` anywhere in the content, including
inside comments and string literals. This was fixed by adding a `stripCommentsAndStrings()` method
that uses a character-by-character state machine to strip comments (`#...\n`), string literals
(`"..."`, `'...'`, `"""..."""`, `'''...'''`), while preserving IRI content (`<...>` including `#`
fragments). The W3C conformance test `turtle-subm-02` now passes through TurtleHandler.

### canHandle() Detection

The `canHandle()` method detects Turtle content through multiple strategies:
1. `str_contains($trimmed, '@prefix')` -- matches `@prefix` anywhere
2. `str_contains($trimmed, 'PREFIX')` -- matches SPARQL-style `PREFIX` anywhere
3. `str_contains($trimmed, '@base')` -- matches `@base` anywhere (added in Story 9-1)
4. `preg_match('/^BASE\s+</m', ...)` -- matches SPARQL-style `BASE` at line start (added in Story 9-1)
5. Turtle-specific feature detection via `hasTurtleSpecificFeatures()` (added in Story 9-1):
   - `a` keyword as predicate (rdf:type shorthand)
   - Semicolons in IRI triple context (predicate-object lists)
   - Commas in IRI triple context (object lists)

**Known false positives** (documented in characterization tests 2.9, 2.9b, 2.9c):
- Content containing `@prefix` inside string literals is detected as Turtle
- Content containing `PREFIX` inside string literals is detected as Turtle
- `@PREFIX` (invalid syntax) is detected via the `PREFIX` check

**Design decision**: Plain N-Triples content (only `<s> <p> <o> .` triples without Turtle-specific
features) is NOT detected as Turtle, preserving the NTriplesHandler priority in RdfParser.

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
| `@prefix` inside comments (false match) | fixed (Story 9-2) | `TurtleHandler::stripCommentsAndStrings()` | `turtle-subm-02` now passes; Unit Story 9-2 tests |

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

### Pre-Parse Validation (Story 9-4)

The handler implements pre-parse validation via `validateContent()` that catches errors EasyRdf's
permissive parsing would silently accept. This runs before EasyRdf delegation and throws
`ParseException` for:

| Validation | Method | Tests Now Passing |
|---|---|---|
| IRI whitespace (spaces in `<...>`) | `validateIRIs()` / `validateSingleIRI()` | `turtle-syntax-bad-uri-01` |
| IRI escape sequences (only `\u` and `\U` allowed) | `validateSingleIRI()` | `turtle-syntax-bad-uri-04`, `turtle-syntax-bad-uri-05`, `turtle-syntax-bad-esc-01` |
| IRI Unicode escape hex validation | `validateSingleIRI()` | `turtle-syntax-bad-uri-02`, `turtle-syntax-bad-uri-03` |
| IRI surrogate codepoints (U+D800..U+DFFF) | `validateSingleIRI()` | `turtle-syntax-bad-numeric-escape-09`, `turtle-syntax-bad-numeric-escape-10` |
| String escape sequences (valid escapes only) | `validateStringEscapes()` | `turtle-syntax-bad-esc-02`, `turtle-syntax-bad-esc-03`, `turtle-syntax-bad-esc-04` |
| String Unicode escape hex validation | `validateStringEscapes()` | (covered by unit tests) |
| String surrogate codepoints (U+D800..U+DFFF) | `validateStringEscapes()` | `turtle-syntax-bad-numeric-escape-01..08` |

The validation uses character-by-character state machines to track context (inside IRI, inside
string, inside comment) and validate only the relevant escape sequences in each context. Prefixed
name escapes (`\~`, `\.`, etc.) outside strings and IRIs are correctly skipped.

### Negative Syntax Rejection

84 of 90 W3C negative syntax tests correctly reject invalid input. 6 are skipped due to EasyRdf's
permissive parsing (accepting content the spec requires rejecting).

**Story 9-4 improvement**: 19 previously-skipped tests now pass thanks to TurtleHandler pre-parse
validation (IRI whitespace/escape validation, string escape validation, surrogate codepoint detection).

| Skipped Category | Count | Reason |
|---|---|---|
| Bad blank node syntax | 2 | EasyRdf does not reject invalid blank node syntax |
| Bad BASE syntax | 1 | EasyRdf does not reject `@BASE` (wrong case) |
| Bad prefixed name (dot at end) | 1 | EasyRdf does not reject dot at end of prefixed name |
| Bad local name escapes | 2 | EasyRdf does not reject invalid percent-encoding in local names |

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
| Negative Syntax | 84 | 0 | 6 | 90 | 93.3% |
| Positive Evaluation | 135 | 1 | 9 | 145 | 93.8% |
| Negative Evaluation | 0 | 0 | 4 | 4 | 0.0% |
| **Total** | **288** | **1** | **24** | **313** | **92.3%** |

### Deprecated Tests (W3C suite)

| Test | Deprecation Source |
|---|---|
| `collection_subject` | `EasyRdf\Collection::count()` return type notice |

### Skipped Tests by Root Cause (24 total — all EasyRdf-blocked)

All 24 remaining skipped tests are due to EasyRdf 1.1.1 limitations that cannot be addressed at the
TurtleHandler level without replacing the underlying parser.

19 previously-skipped tests now pass thanks to Story 9-4 pre-parse validation (see below).

| Root Cause Category | Count | Fixable at Handler Level? | Tests |
|---|---|---|---|
| **Permissive blank node validation** | 2 | No (EasyRdf-only) | `turtle-syntax-bad-bnode-01..02` |
| **Permissive BASE validation** | 1 | No (EasyRdf-only) | `turtle-syntax-bad-base-02` |
| **Permissive name validation** | 1 | No (EasyRdf-only) | `turtle-syntax-bad-pname-02` |
| **Permissive local name escape validation** | 2 | No (EasyRdf-only) | `turtle-syntax-bad-ln-escape`, `turtle-syntax-bad-ln-escape-start` |
| **Permissive semantic validation** | 4 | No (EasyRdf-only) | `turtle-eval-bad-01..04` |
| **Blank node property lists as subject** | 5 | No (EasyRdf parse failure) | `sole_blankNodePropertyList`, `blankNodePropertyList_as_subject`, `blankNodePropertyList_with_multiple_triples`, `nested_blankNodePropertyLists`, `blankNodePropertyList_containing_collection` |
| **Dots in names** (easyrdf/easyrdf#140) | 3 | No (EasyRdf limitation) | `turtle-syntax-ln-dots`, `turtle-syntax-ns-dots`, `prefix_with_non_leading_extras` |
| **Special chars in labels/local names** | 2 | No (EasyRdf limitation) | `labeled_blank_node_with_non_leading_extras`, `localName_with_non_leading_extras` |
| **SPARQL-style directives** | 2 | No (EasyRdf limitation) | `turtle-syntax-base-02`, `turtle-syntax-prefix-02` |
| **Blank node label pattern** | 1 | No (EasyRdf limitation) | `turtle-syntax-blank-label` |
| **IRI resolution edge case** | 1 | No (EasyRdf produces incorrect IRI) | `IRI-resolution-08` |
| **Total** | **24** | | |

#### Previously Skipped, Now Passing (Story 9-4)

19 tests that were previously skipped due to EasyRdf's permissive parsing now pass thanks to
TurtleHandler pre-parse validation added in Story 9-4:

| Category | Count | Tests | Validation |
|---|---|---|---|
| IRI whitespace/escapes | 5 | `turtle-syntax-bad-uri-01..05` | `validateIRIs()` / `validateSingleIRI()` |
| Surrogate codepoints in strings | 8 | `turtle-syntax-bad-numeric-escape-01..08` | `validateStringEscapes()` |
| Surrogate codepoints in IRIs | 2 | `turtle-syntax-bad-numeric-escape-09..10` | `validateSingleIRI()` |
| String escape sequences | 3 | `turtle-syntax-bad-esc-02..04` | `validateStringEscapes()` |
| IRI escape sequences | 1 | `turtle-syntax-bad-esc-01` | `validateSingleIRI()` |

#### Category Details

**Permissive parsing (6 remaining tests)**: EasyRdf 1.1.1 silently accepts content that the W3C
spec requires parsers to reject. The remaining cases involve invalid blank node syntax, invalid
BASE case, invalid percent-encoding in local names, and dot at end of prefixed name. These cannot
be caught by pre-parse validation without re-implementing significant parsing logic for blank
nodes and prefixed name syntax.

**Blank node property lists as subject (5 tests)**: EasyRdf 1.1.1 cannot parse `[ :p :o ] .` or
`[ :p :o ] :q :r .` syntax where a blank node property list serves as the subject of a triple.
This is a fundamental EasyRdf parsing limitation.

**Dots in names (3 tests)**: EasyRdf issue #140 — the EasyRdf parser does not support dots in
local names or namespace prefix names per the Turtle grammar. This affects prefixed names like
`p:a.b` and namespace prefixes like `@prefix a.b:`.

**Special characters (2 tests)**: EasyRdf does not support certain Unicode characters in blank
node labels and local names that the Turtle grammar allows.

**SPARQL-style directives (2 tests)**: EasyRdf does not support bare `BASE` and `PREFIX`
(without the `@` prefix) in certain syntactic contexts.

**Blank node label pattern (1 test)**: EasyRdf does not support a specific blank node label
pattern allowed by the Turtle grammar.

**IRI resolution (1 test)**: EasyRdf incorrectly resolves IRIs with double-slash paths,
producing `//de//xyz` instead of `//de/xyz`.

---

## Test Coverage Summary

Test runner: Pest 3.x
Total test results: **395 tests** (368 passed, 3 deprecated, 24 skipped, 0 failing)
Assertions: 817

### Test File Breakdown

| File | Test Count | Purpose |
|---|---|---|
| `tests/Unit/TurtleHandlerTest.php` | 36 | Unit tests: detection, format name, parsing, error throwing, canHandle() gaps (Story 9-1), prefix regex bug (Story 9-2), pre-parse validation (Story 9-4) |
| `tests/Unit/AliasesTest.php` | 10 | Backward-compatibility class alias bridge tests |
| `tests/Characterization/TurtleHandlerTest.php` | 35 | Detailed behavioral characterization of canHandle(), parse(), getFormatName(), error handling, and prefix registration side effects |
| `tests/Conformance/W3cTurtleConformanceTest.php` | 313 | Full W3C RDF 1.1 Turtle conformance suite (74 + 90 + 145 + 4) |
| **Total** | **395** | |

### Test Categories

| Area | Test Count | Coverage |
|---|---|---|
| Format detection (`canHandle`) | 12 + 12 | Positive/negative detection, false positives, whitespace trimming, @base/BASE, Turtle-specific features (Story 9-1) |
| Parsing output structure | 11 | Return type, format, rawContent, metadata, graph resources |
| Format name (`getFormatName`) | 2 | Return value and type |
| Error handling | 6 | ParseException, message format, previous exception, edge cases |
| Prefix registration | 4 + 4 | Global namespace side effect, multiple prefixes, ordering, comment/string exclusion (Story 9-2) |
| Pre-parse validation | 16 | IRI whitespace, IRI escapes, string escapes, surrogate codepoints, valid content passthrough (Story 9-4) |
| Class alias bridge | 10 | Old namespace resolution, instanceof, deprecation warnings |
| W3C positive syntax | 74 | Valid Turtle accepted without error |
| W3C negative syntax | 90 | Invalid Turtle rejected with ParseException |
| W3C positive evaluation | 145 | Parsed triples match expected N-Triples output |
| W3C negative evaluation | 4 | Semantically invalid input rejected |

---

## Architecture Notes

The implementation follows a **thin handler + delegation** pattern:

1. **Single source file**: `src/TurtleHandler.php` implements `RdfFormatHandlerInterface` from
   parser-core.
2. **EasyRdf delegation**: All actual Turtle parsing, grammar production handling, blank node
   management, collection expansion, and literal processing are performed by EasyRdf's built-in
   Turtle parser (`EasyRdf\Graph::parse($content, 'turtle')`).
3. **Prefix pre-registration**: The handler extracts `@prefix` declarations via regex (after
   stripping comments and string literals) and registers them in `EasyRdf\RdfNamespace` before
   parsing. Comment/string stripping uses a character-by-character state machine that preserves
   IRI content (including `#` fragments).
4. **Pre-parse validation**: Before delegating to EasyRdf, the handler validates IRI contents
   (no whitespace, only `\u`/`\U` escapes, no surrogate codepoints) and string literal escape
   sequences (valid escapes only, no surrogates). This catches 19 classes of errors that EasyRdf's
   permissive parsing would silently accept.
5. **Error wrapping**: All `\Throwable` exceptions from EasyRdf are caught and re-thrown as
   `ParseException` with a standardized message prefix. Pre-parse validation errors are thrown
   directly as `ParseException` and re-thrown without wrapping.
5. **Format detection**: `canHandle()` uses a multi-strategy approach: directive detection
   (`@prefix`, `PREFIX`, `@base`, `BASE`), and Turtle-specific syntax feature detection (`a`
   keyword, semicolons, commas in IRI triple context).

Key design decisions:
- Spec compliance is bounded by EasyRdf 1.1.1's capabilities, which accounts for all 24 remaining
  skipped W3C tests. Pre-parse validation extends spec compliance beyond EasyRdf's built-in
  validation for IRI and string escape sequences.
- The handler does not implement its own tokenizer or parser; it is a facade over EasyRdf with
  targeted pre-parse validation for specific error classes.
- `canHandle()` uses heuristic pattern matching rather than attempting a trial parse. Plain
  N-Triples content is not detected as Turtle, preserving handler priority order.
- The `stripCommentsAndStrings()` method provides context-aware prefix extraction without
  implementing a full Turtle tokenizer.

---

## Remaining Gaps

All remaining gaps are due to EasyRdf 1.1.1 limitations, not handler-level omissions.
Handler-level issues fixed in Epic 9: `canHandle()` detection gaps (Story 9-1),
`@prefix` regex matching inside comments/strings (Story 9-2), and pre-parse validation
for IRI/string escape sequences (Story 9-4).

| Gap | Impact | Root Cause | Fixable? |
|---|---|---|---|
| SPARQL-style `BASE` directive | 2 tests skipped | EasyRdf does not support bare `BASE`/`PREFIX` | EasyRdf-blocked |
| Blank node property list as subject | 5 tests skipped | EasyRdf parsing failure | EasyRdf-blocked |
| Dots in names | 3 tests skipped | EasyRdf issue #140 | EasyRdf-blocked |
| Special characters in labels/names | 2 tests skipped | EasyRdf limitation | EasyRdf-blocked |
| Permissive blank node/BASE/name validation | 6 tests skipped | EasyRdf does not reject invalid input | EasyRdf-blocked |
| Permissive semantic validation | 4 tests skipped | EasyRdf does not reject undefined prefix/relative IRI | EasyRdf-blocked |
| IRI resolution edge case | 1 test skipped | EasyRdf produces incorrect IRI | EasyRdf-blocked |
| Blank node label pattern | 1 test skipped | EasyRdf limitation | EasyRdf-blocked |

### Fixed in Epic 9

| Issue | Resolution | Story |
|---|---|---|
| `canHandle()` did not detect `@base`-only content | Added `@base`, `BASE`, and Turtle-specific feature detection | 9-1 |
| `canHandle()` did not detect full-IRI Turtle content | Added `a` keyword, semicolon, and comma pattern detection | 9-1 |
| `@prefix` regex matched inside comments/strings | Added `stripCommentsAndStrings()` state machine | 9-2 |
| `turtle-subm-02` W3C eval test failed through TurtleHandler | Fixed by Story 9-2 regex fix | 9-2 |
| EasyRdf accepted invalid IRIs (spaces, bad escapes) | Added `validateIRIs()` / `validateSingleIRI()` pre-parse validation | 9-4 |
| EasyRdf accepted invalid string escapes | Added `validateStringEscapes()` pre-parse validation | 9-4 |
| EasyRdf accepted surrogate codepoints in strings/IRIs | Surrogate range check in both IRI and string validators | 9-4 |
| 19 W3C negative syntax tests were skipped | Now passing via pre-parse validation (43 -> 24 skipped) | 9-4 |

### Upgrade Path

Replacing EasyRdf 1.1.1 with a fully spec-compliant Turtle parser would resolve 23 of 24 remaining
skipped tests. The remaining 1 skip (`turtle-syntax-prefix-02`) relates to a specific SPARQL-style
PREFIX context that even alternative parsers may not support.
