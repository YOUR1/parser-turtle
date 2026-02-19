# W3C RDF 1.1 Turtle Test Suite Fixtures

## Source

- **Official URL:** https://www.w3.org/2013/TurtleTests/
- **Downloaded from:** https://github.com/w3c/rdf-tests/tree/main/rdf/rdf11/rdf-turtle
- **Download date:** 2026-02-19
- **Specification:** [RDF 1.1 Turtle — W3C Recommendation](https://www.w3.org/TR/turtle/)

## License

These test files are provided under the [W3C Software and Document License](https://www.w3.org/copyright/software-license-2023/).

## Contents

- `manifest.ttl` — Test manifest defining all test cases and their types
- `*.ttl` — Turtle test input files (positive and negative syntax, evaluation actions)
- `*.nt` — N-Triples expected result files (for evaluation tests)

## Test Categories

| Type | Count | Description |
|------|-------|-------------|
| `TestTurtlePositiveSyntax` | 74 | Parser must accept without error |
| `TestTurtleNegativeSyntax` | 90 | Parser must reject with error |
| `TestTurtleEval` | 145 | Parser must produce expected triples |
| `TestTurtleNegativeEval` | 4 | Parser must reject (semantic errors) |
| **Total** | **313** | |

## Important

Do NOT modify these fixture files. They must remain byte-for-byte identical to the official W3C distribution.
