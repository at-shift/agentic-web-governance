# Schemas

This directory contains machine-readable schemas that accompany normative
specification text.

## `action-v1.schema.json`

The schema defines the version 1 canonical action object from
[RFC 0001](../rfcs/0001-canonical-action-representation.md). It uses JSON
Schema Draft 2020-12.

Core objects are closed with `additionalProperties: false`. Capability- or
application-specific values can appear only in approval-relevant extension
points:

- `arguments`;
- `resource.attributes`;
- `preconditions`.

Those values are part of the canonical bytes and request hash. Operational,
transport, tracing, credential, and display-only metadata are not extension
values and must remain outside the canonical action object.

The schema limits integer values in generic JSON fields to the interoperable
range `[-9007199254740991, 9007199254740991]`. Larger integers, fixed-precision
decimals, and numeric identifiers use capability-defined strings.

JSON Schema cannot express every RFC 8785 input condition. Implementations must
also reject duplicate object names, malformed Unicode, non-finite values, and
lossy source parsing before schema validation.
