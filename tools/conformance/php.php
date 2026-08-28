#!/usr/bin/env php
<?php

declare(strict_types=1);

use Opis\JsonSchema\Validator;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\Lexer;
use Truschery\Kanon\Json;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

const SAFE_INTEGER_MAX = 9007199254740991;

final class ConformanceError extends RuntimeException
{
    public function __construct(public readonly string $stage, string $message)
    {
        parent::__construct($message);
    }
}

function assertInteroperableValues(mixed $value): void
{
    if (is_int($value) && ($value < -SAFE_INTEGER_MAX || $value > SAFE_INTEGER_MAX)) {
        throw new ConformanceError('schema', "unsafe integer value: {$value}");
    }
    if (is_float($value) && (!is_finite($value))) {
        throw new ConformanceError('parse', 'non-finite number');
    }
    if (is_string($value) && preg_match('//u', $value) !== 1) {
        throw new ConformanceError('unicode', 'invalid Unicode string');
    }
    if (is_array($value)) {
        foreach ($value as $item) {
            assertInteroperableValues($item);
        }
    } elseif (is_object($value)) {
        foreach (get_object_vars($value) as $key => $item) {
            assertInteroperableValues($key);
            assertInteroperableValues($item);
        }
    }
}

function assertNoDuplicateKeys(string $source): void
{
    $lexer = (new Lexer())->setInput($source);
    $stack = [];

    while ($lexer->lex() !== Lexer::EOF) {
        $token = $lexer->match;
        if ($token === '{') {
            $stack[] = ['type' => 'object', 'keys' => [], 'expectingKey' => true];
            continue;
        }
        if ($token === '[') {
            $stack[] = ['type' => 'array'];
            continue;
        }
        if ($token === '}' || $token === ']') {
            array_pop($stack);
            continue;
        }
        if ($stack === []) {
            continue;
        }

        $index = array_key_last($stack);
        if ($token === ',' && $stack[$index]['type'] === 'object') {
            $stack[$index]['expectingKey'] = true;
            continue;
        }
        if (
            $stack[$index]['type'] === 'object'
            && $stack[$index]['expectingKey']
            && str_starts_with($token, '"')
        ) {
            $key = json_decode($token, false, 512, JSON_THROW_ON_ERROR);
            if (array_key_exists($key, $stack[$index]['keys'])) {
                throw new ConformanceError('parse', "duplicate object member: {$key}");
            }
            $stack[$index]['keys'][$key] = true;
            $stack[$index]['expectingKey'] = false;
        }
    }
}

function parseJson(string $source, bool $inspectNumbers = false): mixed
{
    $parser = new JsonParser();
    $flags = JsonParser::DETECT_KEY_CONFLICTS | JsonParser::VALIDATE_UTF8_ENCODING;

    try {
        assertNoDuplicateKeys($source);
        $parser->parse($source, $flags);
    } catch (Throwable $error) {
        throw new ConformanceError('parse', $error->getMessage());
    }

    try {
        $value = json_decode($source, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $error) {
        $stage = str_contains(strtolower($error->getMessage()), 'surrogate')
            ? 'unicode'
            : 'parse';
        throw new ConformanceError($stage, $error->getMessage());
    }

    if ($inspectNumbers) {
        assertInteroperableValues($value);
    }

    return $value;
}

function deepClone(mixed $value): mixed
{
    return unserialize(serialize($value));
}

/** @return list<string> */
function pointerSegments(string $pointer): array
{
    if (!str_starts_with($pointer, '/')) {
        throw new RuntimeException("invalid fixture pointer: {$pointer}");
    }

    return array_map(
        static fn(string $part): string => str_replace(['~1', '~0'], ['/', '~'], $part),
        explode('/', substr($pointer, 1)),
    );
}

function applyChanges(object $base, array $changes = []): object
{
    $value = deepClone($base);

    foreach ($changes as $change) {
        $segments = pointerSegments($change->path);
        $key = array_pop($segments);
        $parent =& $value;

        foreach ($segments as $segment) {
            if (is_object($parent) && property_exists($parent, $segment)) {
                $parent =& $parent->{$segment};
            } elseif (is_array($parent) && array_key_exists((int) $segment, $parent)) {
                $parent =& $parent[(int) $segment];
            } else {
                throw new RuntimeException("fixture pointer does not exist: {$change->path}");
            }
        }

        if ($change->op === 'remove') {
            if (is_object($parent)) {
                unset($parent->{$key});
            } else {
                unset($parent[(int) $key]);
                $parent = array_values($parent);
            }
        } elseif ($change->op === 'add' || $change->op === 'replace') {
            if (is_object($parent)) {
                $parent->{$key} = deepClone($change->value);
            } else {
                $parent[(int) $key] = deepClone($change->value);
            }
        } else {
            throw new RuntimeException("unsupported fixture operation: {$change->op}");
        }

        unset($parent);
    }

    return $value;
}

function normalizeStringSet(mixed $value): mixed
{
    if (
        !is_array($value)
        || array_filter($value, static fn (mixed $item): bool => !is_string($item)) !== []
    ) {
        return $value;
    }

    $value = array_values(array_unique($value, SORT_REGULAR));
    sort($value, SORT_STRING);
    return $value;
}

function normalizeAction(object $input): object
{
    $action = deepClone($input);

    if (isset($action->impact) && property_exists($action->impact, 'data_classes')) {
        $action->impact->data_classes = normalizeStringSet($action->impact->data_classes);
    }
    if (isset($action->transmission) && property_exists($action->transmission, 'data_classes')) {
        $action->transmission->data_classes = normalizeStringSet(
            $action->transmission->data_classes,
        );
    }
    if (isset($action->policy->profiles) && is_array($action->policy->profiles)) {
        $ids = [];
        foreach ($action->policy->profiles as $profile) {
            if (!is_object($profile) || !isset($profile->id) || !is_string($profile->id)) {
                throw new ConformanceError('normalize', 'profile id must be a string');
            }
            if (isset($ids[$profile->id])) {
                throw new ConformanceError('normalize', "duplicate profile id: {$profile->id}");
            }
            $ids[$profile->id] = true;
        }
        usort(
            $action->policy->profiles,
            static fn(object $left, object $right): int => strcmp($left->id, $right->id),
        );
    }

    return $action;
}

/** @return array{canonical: string, canonicalHex: string, requestHash: string} */
function createDigest(
    object $action,
    string $domainPrefix,
    object $schema,
    Validator $validator,
): array {
    $normalized = normalizeAction($action);
    assertInteroperableValues($normalized);

    $validation = $validator->validate($normalized, $schema);
    if (!$validation->isValid()) {
        $message = $validation->error()?->message() ?? 'schema validation failed';
        throw new ConformanceError('schema', $message);
    }

    try {
        $canonical = Json::canonicalize($normalized);
    } catch (Throwable $error) {
        throw new ConformanceError('canonicalize', $error->getMessage());
    }

    return [
        'canonical' => $canonical,
        'canonicalHex' => bin2hex($canonical),
        'requestHash' => 'sha256:' . hash('sha256', $domainPrefix . $canonical),
    ];
}

function loadCaseAction(object $case, object $manifest, string $fixtureDir): object
{
    if (isset($case->file)) {
        $source = file_get_contents($fixtureDir . '/' . $case->file);
        if ($source === false) {
            throw new RuntimeException("cannot read fixture: {$case->file}");
        }
        $fixture = parseJson($source, true);
        return isset($fixture->action) ? $fixture->action : $fixture;
    }

    return applyChanges($manifest->base_action, $case->changes ?? []);
}

function runActionConformance(array $argv): int
{
    $root = dirname(__DIR__, 2);
    $fixtureDir = $root . '/fixtures/action-v1';
    $manifestSource = file_get_contents($fixtureDir . '/manifest.json');
    if ($manifestSource === false) {
        throw new RuntimeException('cannot read fixture manifest');
    }
    $manifest = parseJson($manifestSource);

    $schemaSource = file_get_contents($fixtureDir . '/' . $manifest->schema);
    if ($schemaSource === false) {
        throw new RuntimeException('cannot read action schema');
    }
    $schema = parseJson($schemaSource);
    $validator = new Validator();
    $emit = in_array('--emit', $argv, true);
    $results = [];
    $failures = 0;

    foreach ($manifest->cases as $case) {
        try {
            $action = loadCaseAction($case, $manifest, $fixtureDir);
            $result = createDigest($action, $manifest->domain_prefix, $schema, $validator);
            $results[$case->id] = $result;

            if (
                !$emit
                && $case->expected_canonical_hex !== ''
                && $result['canonicalHex'] !== $case->expected_canonical_hex
            ) {
                throw new RuntimeException('canonical bytes do not match fixture');
            }
            if (!$emit && $result['requestHash'] !== $case->expected_hash) {
                throw new RuntimeException('request hash does not match fixture');
            }
        } catch (Throwable $error) {
            ++$failures;
            fwrite(STDERR, "FAIL accepted/{$case->id}: {$error->getMessage()}\n");
        }
    }

    foreach ($manifest->relations as $relation) {
        $left = $results[$relation->left]['requestHash'] ?? null;
        $right = $results[$relation->right]['requestHash'] ?? null;
        $passed = $relation->kind === 'equal' ? $left === $right : $left !== $right;
        if (!$passed || $left === null || $right === null) {
            ++$failures;
            fwrite(
                STDERR,
                "FAIL relation/{$relation->left}/{$relation->kind}/{$relation->right}\n",
            );
        }
    }

    foreach ($manifest->rejected as $rejected) {
        try {
            $action = isset($rejected->raw)
                ? parseJson($rejected->raw, true)
                : applyChanges($manifest->base_action, $rejected->changes ?? []);
            createDigest($action, $manifest->domain_prefix, $schema, $validator);
            ++$failures;
            fwrite(STDERR, "FAIL rejected/{$rejected->id}: unexpectedly accepted\n");
        } catch (Throwable $error) {
            $stage = $error instanceof ConformanceError ? $error->stage : 'runtime';
            if ($stage !== $rejected->stage) {
                ++$failures;
                fwrite(
                    STDERR,
                    "FAIL rejected/{$rejected->id}: expected {$rejected->stage}, got {$stage}\n",
                );
            }
        }
    }

    if ($emit) {
        $output = [];
        foreach ($results as $id => $result) {
            $output[$id] = [
                'expected_canonical_hex' => $result['canonicalHex'],
                'expected_hash' => $result['requestHash'],
            ];
        }
        echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    } else {
        echo sprintf(
            "PHP: %d accepted, %d rejected, %d relations\n",
            count($manifest->cases),
            count($manifest->rejected),
            count($manifest->relations),
        );
    }

    return $failures === 0 ? 0 : 1;
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(runActionConformance($argv));
}
