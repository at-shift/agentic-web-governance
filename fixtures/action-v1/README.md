# Canonical Action v1 Fixtures

These fixtures exercise the canonical action projection, normalization, RFC
8785 serialization, and domain-separated SHA-256 digest defined by
[RFC 0001](../../rfcs/0001-canonical-action-representation.md).

`manifest.json` contains:

- a complete base action;
- accepted cases with pinned request hashes;
- equality and difference relationships between cases;
- rejected inputs with the expected failure stage.

Files under `valid/` cover source formatting and the boundary between excluded
execution context and the canonical action projection. Fixture patch operations
are test notation, not part of the specification.

## Coverage

The suite currently includes:

- source key order and whitespace;
- set sorting and deduplication;
- ordered arrays;
- absent versus explicit `null`;
- NFC and NFD Unicode preservation;
- RFC 8785 UTF-16 property ordering;
- fractional number serialization and string-form large identifiers;
- changes to every core approval-relevant area;
- duplicate keys, including a duplicate whose first value is `null`;
- malformed Unicode, non-finite numbers, unsafe integers, unknown fields, and
  invalid set members.

## Run

Install the locked development dependencies, then run:

```sh
npm ci
composer install
npm test
```

The JavaScript and PHP runners independently validate every case. The
cross-runtime runner then compares their canonical UTF-8 bytes and digests for
every accepted fixture.

The runners are conformance tooling, not a production authorization or approval
implementation.

The companion [approval lifecycle fixtures](../approval-lifecycle-v1/README.md)
reuse these actions to test execution-time reconstruction and fail-closed
approval handling.
