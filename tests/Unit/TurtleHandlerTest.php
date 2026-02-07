<?php

use App\Services\Ontology\Exceptions\OntologyImportException;
use App\Services\Ontology\Parsers\Handlers\TurtleHandler;
use App\Services\Ontology\Parsers\ValueObjects\ParsedRdf;

beforeEach(function () {
    $this->handler = new TurtleHandler;
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
    expect(fn () => $this->handler->parse('@prefix invalid'))->toThrow(OntologyImportException::class);
});
