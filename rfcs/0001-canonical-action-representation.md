# RFC 0001: Canonical Action Representation

- **Status:** Accepted
- **Authors:** `@at-shift`
- **Created:** 2026-08-25
- **Updated:** 2026-08-26
- **Target version:** Draft 0.2
- **Supersedes:** None
- **Superseded by:** None

## Summary

This RFC defines a versioned canonical action representation for binding an
approval to the exact action reviewed. It uses the JSON Canonicalization Scheme
(JCS) from RFC 8785, additional field-level normalization rules defined here,
and SHA-256 with domain separation.

The representation is a security projection of the normalized execution
context. It is not the transport request, the evidence event, or the complete
authorization state.

## Decision

Accepted by `@at-shift` on 2026-08-26.

The maintainer accepts version 1 as the common contract for canonical action
projection, validation, normalization, and request hashing. The decision is
supported by the Draft 2020-12 schema, independent PHP and JavaScript paths,
shared adversarial fixtures, matching canonical bytes and hashes, and the
fail-closed approval lifecycle fixtures.

`ACCEPTED` records a settled technical direction. It does not claim that a
production governance implementation exists, that the current conformance
tools are product code, or that the overall specification is `STABLE`. The RFC
lifecycle intentionally separates an accepted decision from `IMPLEMENTED`, and
the project maturity process separately defines `CANDIDATE` and `STABLE`. This
allows current and future contributors to implement the contract incrementally
without requiring one prototype to reach production completeness first.

The follow-up questions at the end of this RFC are deferred and non-blocking.
Version 1 has closed defaults: undeclared arrays are ordered, Unicode is not
normalized, and every value in an extension object enters the request hash.
Capability profiles may later add narrower canonical string, set, Unicode, or
precondition semantics without changing these core defaults in place.

## Problem

`SPEC.md` and `EVIDENCE.md` require a deterministic request hash but do not
select a complete canonicalization profile. Sorting object keys alone is not
enough. Implementations can still disagree about numbers, Unicode, arrays,
duplicate object names, absent values, and which context fields affect the
action identity.

That ambiguity can permit approval bait-and-switch, accidental approval
invalidation, or incompatible hashes across adapters and runtimes.

## Goals

- Produce the same digest for the same semantic action across conforming
  implementations.
- Produce a different digest when any approval-relevant value changes.
- Keep transport, tracing, and other non-authoritative metadata out of the
  action identity.
- Reuse a published canonical JSON scheme rather than create a serializer.
- Define fail-closed validation and cross-runtime test requirements.

## Non-goals

- Authenticate the proposal or prove who created it.
- Replace application authorization, delegation, policy, or replay state.
- Canonicalize arbitrary non-JSON application types without a declared mapping.
- Define the human-readable approval preview or diff format.
- Make request hashes safe to expose publicly.

## Proposal

### 1. Terms

**Execution context** is the complete normalized input used by governance.

**Canonical action object** is the approval-relevant JSON projection defined by
this RFC.

**Canonical action bytes** are the UTF-8 bytes produced by applying RFC 8785
JCS to the canonical action object.

**Request hash** is the versioned, domain-separated SHA-256 digest defined
below.

### 2. Canonical action object

A version 1 canonical action object MUST be a JSON object with this shape:

```json
{
  "version": 1,
  "application": {
    "type": "wordpress",
    "instance_id": "site-42",
    "tenant_id": "site-42"
  },
  "application_principal": {
    "type": "wordpress-user",
    "id": "123"
  },
  "capability": "example/publish-post",
  "actors": {
    "client": {
      "issuer": "https://issuer.example",
      "subject": "client-7"
    },
    "agent": {
      "issuer": "https://issuer.example",
      "subject": "agent-9"
    }
  },
  "delegation": {
    "id": "delegation-11",
    "version": "4"
  },
  "arguments": {
    "post_id": "42",
    "status": "publish"
  },
  "resource": {
    "type": "post",
    "id": "42"
  },
  "impact": {
    "risk_class": "write",
    "reversibility": "reversible",
    "data_classes": ["INTERNAL"]
  },
  "transmission": {
    "recipient": "provider-3",
    "purpose": "content-review",
    "data_classes": ["INTERNAL"]
  },
  "policy": {
    "id": "site-default",
    "version": "17",
    "profiles": [
      {"id": "japan", "version": "draft-0.1"}
    ]
  },
  "preconditions": {
    "resource_version": "8"
  }
}
```

The example is illustrative. The exact version 1 object shape is defined by
[`schemas/action-v1.schema.json`](../schemas/action-v1.schema.json), using JSON
Schema Draft 2020-12.

Required fields are:

- `version`;
- `application` with stable `type` and `instance_id`;
- `application_principal` with stable `type` and `id`;
- `capability`;
- `arguments`;
- `impact`;
- `policy` with effective identifier and version.

Conditional fields are:

- `actors` when validated client or agent identity is known and affects policy,
  review, attribution, or impact;
- `delegation` when execution relies on a delegation;
- `resource` when target identity or scope is not fully represented by the
  capability and arguments;
- `transmission` when data may cross the application boundary;
- `preconditions` when target version, state, amount, or another condition is
  necessary to prevent a reviewed action from changing meaning.

An adapter or capability profile MUST include every value that can materially
change authorization, reviewer understanding, recipient, data handling, or
side effect. Failure to construct a complete canonical action object MUST fail
closed before approval is requested.

#### 2.1 Schema vocabulary and extension points

Core objects are closed. Unknown properties fail schema validation. The only
capability- or application-defined value objects in version 1 are:

- `arguments`;
- `resource.attributes`;
- `preconditions`.

Every value placed in those objects is approval-relevant and enters the request
hash. Transport, tracing, credential, and display-only values MUST NOT use them
as a route into the canonical action.

Stable identifiers use ASCII letters, digits, `.`, `_`, `:`, `/`, `@`, and `-`,
start with an alphanumeric character, and are limited to 256 characters. This
keeps identifier comparison independent of Unicode normalization. Human text
and capability-defined string values remain Unicode.

The core `risk_class` values are `read`, `low`, `write`, `sensitive`,
`destructive`, and `financial`. The core `reversibility` values are
`reversible`, `partially_reversible`, `irreversible`, and `unknown`. Core data
classes are `PUBLIC`, `INTERNAL`, `PERSONAL`, `SENSITIVE`, and `CONFIDENTIAL`.

### 3. Excluded fields

The following MUST NOT enter the canonical action object unless a future
profile explicitly gives them action semantics:

- request, correlation, trace, span, or protocol task identifiers;
- receipt time, network address, user agent, routing header, or retry count;
- transport protocol name or protocol version;
- bearer tokens, cookies, nonces, authorization headers, or other credentials;
- mutable permission results, budget counters, or approval status;
- display-only labels or natural-language summaries.

If a protocol difference changes the action's meaning, the adapter MUST map
that difference into an approval-relevant field instead of hashing the protocol
envelope.

### 4. JSON input profile

The canonical action object MUST satisfy RFC 8785 and its I-JSON constraints:

- object member names are unique;
- strings contain valid Unicode and are encoded as UTF-8;
- numbers are finite and representable under the RFC 8785 number model;
- object properties are recursively sorted by JCS;
- array order is preserved by JCS;
- no whitespace is emitted between JSON tokens.

A parser MUST reject duplicate object member names before a generic object
model silently discards them.

JCS does not perform Unicode normalization. Strings MUST be preserved as-is
after application validation. When an application treats canonically
equivalent Unicode sequences as the same identifier, its capability profile
MUST define and apply normalization before constructing the action object.

Values requiring integer range or decimal precision beyond the interoperable
JCS number model MUST use a capability-defined canonical string format. This
includes identifiers that merely look numeric, monetary amounts requiring
fixed decimal semantics, and integers whose exact value cannot be preserved by
all conforming runtimes.

The version 1 schema restricts integer values in generic JSON fields to
`[-9007199254740991, 9007199254740991]`. Finite fractional values remain
available under the JCS number model. A strict input parser remains necessary
because JSON Schema cannot detect duplicate source names, malformed Unicode, or
every lossy source-number conversion after parsing.

### 5. Absent, null, and unknown values

An unknown or inapplicable optional context field MUST be omitted. Optional
context fields MUST NOT use `null` as a placeholder.

Within `arguments`, `resource`, or another capability-defined value, explicit
JSON `null` remains a value and is materially different from an absent object
member. Capability schemas MUST define whether either form is valid.

Unknown client or agent identity MUST remain omitted. A display name,
transport label, or self-asserted value MUST NOT be substituted for a validated
issuer and subject.

### 6. Array semantics

Arrays with application-defined order, including ordinary argument arrays,
MUST preserve that order.

Fields defined as sets by this specification or a capability profile MUST be
deduplicated and sorted before JCS serialization. String sets such as
`data_classes` MUST be sorted using the same UTF-16 code-unit ordering that JCS
uses for object property names. `policy.profiles` MUST be sorted by `id` and
MUST NOT contain duplicate identifiers.

An array without declared set semantics is ordered. Reordering it changes the
request hash.

### 7. Digest algorithm

For version 1:

```text
canonical_bytes = JCS(canonical_action_object)

digest_input = ASCII("AWG-ACTION-V1\n") || canonical_bytes

request_hash = "sha256:" || lowercase_hex(SHA-256(digest_input))
```

The ASCII prefix provides domain and version separation. The `request_hash`
field itself MUST NOT appear in the canonical action object.

An implementation MUST NOT request approval when validation,
canonicalization, encoding, or hashing fails.

### 8. Approval and execution lifecycle

The immutable proposal MUST retain or be able to reconstruct the exact
canonical action object used for hashing. Approval records MUST bind the
request hash, approver, decision, expiry, and replay state.

Immediately before execution, the implementation MUST reconstruct the
canonical action object from current authoritative inputs and compare its hash
with the approved hash. It MUST also re-check application permission,
delegation, policy, budgets, preconditions, and replay status.

An approval is no longer valid at its `expires_at` boundary; execution MUST be
denied when the current time is equal to or later than that boundary. Checking
replay state, consuming a single-use approval, and dispatching the side effect
MUST use an application-appropriate atomic or idempotent boundary.

A policy or profile version change alters the version 1 request hash and
invalidates approval. A future RFC may define narrowly-scoped semantic policy
migrations; version 1 has no such exception.

The canonical action object can contain personal or confidential data. It MUST
receive access control and retention appropriate to the underlying action. An
evidence event SHOULD store the request hash and a redacted projection rather
than unrestricted canonical bytes.

## Data flow and trust boundaries

```text
Untrusted protocol request
        |
        v
Adapter validation and semantic mapping
        |
        v
Normalized execution context
        |
        v
Canonical action projection
        |
        +--> structured approval preview
        |
        v
JCS + domain-separated SHA-256
        |
        v
Immutable proposal and request hash
        |
        v
Approval decision
        |
        v
Execution-time reconstruction and re-check
```

The adapter boundary is responsible for rejecting malformed JSON, validating
claim provenance, and mapping protocol-specific semantics. Governance is
responsible for ensuring that the projection includes every policy and impact
field. Application authorization remains authoritative at execution.

## Security and privacy

- A hash is not a signature, authorization grant, freshness proof, or evidence
  that input claims were truthful.
- Weak projection rules can create semantic collisions even when SHA-256 and
  JCS are implemented correctly.
- Request hashes over predictable, low-entropy values can permit offline
  guessing. They should not be exposed as public identifiers.
- Canonical bytes may contain personal, confidential, or unpublished data and
  require stricter handling than ordinary evidence metadata.
- Reusing an approved hash still requires expiry and replay control.
- Policy and application permission changes are re-checked even when the hash
  remains equal.
- Implementations should use maintained JCS and SHA-256 libraries and must
  verify them against shared fixtures rather than hand-roll serialization.

## Evidence

This RFC does not add an event type. It refines `request_hash` and requires
evidence implementations to record:

- the `sha256:` algorithm prefix and lowercase digest;
- canonical action profile version `1`;
- proposal and approval correlation;
- whether execution-time reconstruction matched;
- a stable failure reason when canonicalization or comparison fails.

Full canonical bytes are not required in the general evidence stream.

## Compatibility and upstream relationship

The profile is protocol-neutral and applies before MCP, A2A, REST, CLI, or
another adapter invokes an application capability. It does not replace JSON
Schema validation or any protocol's request identity.

RFC 8785 is an Informational RFC rather than an IETF Standards Track document,
but it provides a concrete interoperable JSON canonicalization scheme and
cross-language test material. This project adds a field and semantic profile
because JCS deliberately does not decide application meaning.

The reference conformance runners use `canonicalize` 4.x and Ajv 8.x in
JavaScript, and `truschery/kanon` 1.x and Opis JSON Schema 2.x in PHP. Strict
parsing is a separate step in both runtimes. Exact versions and transitive
dependencies are pinned in `package-lock.json` and `composer.lock`.

## Conformance and testing

Acceptance of this RFC requires shared test fixtures demonstrating:

1. object-key and insignificant-whitespace differences produce equal hashes;
2. ordered-array changes produce different hashes;
3. declared set arrays normalize to equal hashes;
4. absent and explicit null argument members remain distinct;
5. duplicate object names are rejected;
6. composed and decomposed Unicode remain distinct unless a capability profile
   normalizes them;
7. non-finite and unsupported-precision numbers are rejected;
8. numeric identifiers represented as strings remain exact;
9. transport, trace, and receipt metadata do not change the hash;
10. principal, capability, arguments, resource, transmission, impact,
    delegation, precondition, or policy changes do change the hash;
11. PHP and JavaScript implementations produce the same canonical bytes and
    digest for every accepted fixture;
12. reconstruction failure, hash mismatch, expiry, and replay all fail closed.

At least two independent runtime implementations MUST pass the fixture set
before a Stable specification can depend on version 1.

The canonical-action fixture suite contains 22 accepted cases, 11 rejected
inputs, and 18 equality or difference relationships. JavaScript and PHP produce
identical canonical bytes and request hashes for all accepted cases. The
[approval-lifecycle fixture suite](../fixtures/approval-lifecycle-v1/README.md)
adds 13 cases and 14 sequential attempts with matching complete outcomes in
both runtimes. Together the suites cover all 12 requirements above and support
the acceptance decision recorded in this RFC.

## Migration

No production migration exists because the reference kernel uses only
in-memory state and has no deployment. Version 1 implementations MUST store the
canonical action profile version beside the request hash. A future profile
version MUST use a different domain prefix and MUST NOT reinterpret existing
approvals in place.

## Alternatives

### Ad hoc sorted JSON

Rejected because it leaves primitive serialization, Unicode, and cross-runtime
behavior underspecified.

### Hash the received transport body

Rejected because whitespace, property order, protocol envelopes, and routing
metadata do not define the semantic action.

### Hash the complete execution context

Rejected because request IDs, time, traces, mutable authorization results, and
other operational values would invalidate otherwise identical actions and may
leak unnecessary data.

### Deterministically encoded CBOR

Deferred. RFC 8949 defines deterministic CBOR options and a richer number
model, but current project interfaces and application schemas are JSON-based.
Selecting CBOR now would add a second data-model mapping without demonstrated
need.

## Deferred follow-up questions

These questions may be addressed by capability profiles or later RFCs. They do
not change the closed version 1 defaults accepted above.

- Which canonical string profiles are required for decimal money, timestamps,
  URI-like identifiers, and application-native large integers?
- Which capability metadata declares ordered versus set-valued arrays and
  application-level Unicode normalization?
- Which resource preconditions are portable enough for the core profile?

## References

- [RFC 8259: The JavaScript Object Notation (JSON) Data Interchange Format](https://www.rfc-editor.org/rfc/rfc8259)
- [RFC 7493: The I-JSON Message Format](https://www.rfc-editor.org/rfc/rfc7493)
- [RFC 8785: JSON Canonicalization Scheme](https://www.rfc-editor.org/rfc/rfc8785)
- [RFC 8949: Concise Binary Object Representation](https://www.rfc-editor.org/rfc/rfc8949)
- [NIST FIPS 180-4: Secure Hash Standard](https://www.nist.gov/publications/secure-hash-standard-0)
- [SPEC.md](../SPEC.md)
- [EVIDENCE.md](../EVIDENCE.md)
- [THREAT-MODEL.md](../THREAT-MODEL.md)
