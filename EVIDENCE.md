# Evidence Model

**Version:** Draft 0.1  
**Last reviewed:** 2026-09-04

## 1. Purpose

Evidence supports reconstruction of what an agent proposed, why governance made
a decision, who approved it, what the application executed, and what happened.

Evidence is not the same as unrestricted logging. A conforming implementation
must balance accountability with data minimization, confidentiality, retention,
and deletion requirements.

## 2. Event model

Implementations SHOULD record append-oriented events rather than repeatedly
overwriting one opaque record.

Recommended event types:

```text
action.proposed
policy.allowed
policy.denied
approval.requested
approval.approved
approval.rejected
approval.expired
execution.started
execution.succeeded
execution.failed
execution.cancelled
delegation.created
delegation.revoked
policy.activated
policy.retired
evidence.exported
evidence.deleted
```

## 3. Core event fields

Each event SHOULD include:

```text
event_id
event_type
occurred_at
request_id
trace_id?
parent_event_id?
application
application_principal?
agent_identity?
client_identity?
protocol
capability
risk_class?
data_classes[]
decision?
reason_codes[]
policy_id?
policy_version?
delegation_id?
approval_id?
request_hash?
result_status?
error_code?
duration_ms?
external_transmission?
recipient?
retention_class
schema_version
```

Unknown identities MUST remain null or absent. Implementations MUST NOT insert a
placeholder that could later be mistaken for an authenticated identity.

## 4. Request binding

Proposal and approval records using canonical action version 1 MUST use the
request hash defined by accepted
[RFC 0001](rfcs/0001-canonical-action-representation.md):

```text
canonical_bytes = JCS(canonical_action_object)
request_hash = "sha256:" || lowercase_hex(
  SHA-256(ASCII("AWG-ACTION-V1\n") || canonical_bytes)
)
```

The canonical representation SHOULD include every value that can materially
change authorization, review, or impact:

```text
application
application principal
capability
known agent/client claims
canonical arguments
resource context
data classes
external recipient and purpose
policy version
```

Canonicalization MUST define:

- UTF-8 encoding;
- lexicographic object-key ordering;
- scalar normalization;
- stable array semantics;
- treatment of absent and null values;
- exclusion of non-authoritative transport metadata.

The digest proves equality under the defined canonicalization. It does not prove
that the original request was truthful, safe, or authorized.

## 5. Payload handling

Evidence SHOULD store structured summaries, references, and hashes instead of
full payloads by default.

Evidence MUST NOT store:

- passwords;
- bearer or refresh tokens;
- authorization headers;
- session cookies;
- nonces used as credentials;
- API or OAuth client secrets;
- raw payment credentials;
- private keys;
- hidden model or system prompts unless an explicit, separately protected
  incident process requires them.

Implementations SHOULD support field-level redaction and capability-specific
evidence projections.

For sensitive requests, useful alternatives include:

- stable resource identifiers;
- field names without values;
- before/after content hashes;
- application-generated diffs with sensitive fields removed;
- recipient and purpose metadata;
- a separately controlled incident artifact reference.

## 6. Decision evidence

A policy decision record SHOULD include:

- the decision and stable reason codes;
- policy and profile versions;
- matched rule identifiers;
- relevant risk and data classifications;
- delegation status and constraints applied;
- budget status;
- approval or strong-auth requirements;
- application permission result.

The record SHOULD distinguish an application denial from a governance denial.
It SHOULD NOT reveal internal rule content that would materially aid bypass when
a stable reason code is sufficient.

## 7. Approval evidence

Approval events MUST identify:

- the exact request hash;
- approver identity and authorization source;
- authorization assurance method and independent verification result when
  human approval is required;
- decision time;
- expiry;
- approval policy;
- single-use or replay status;
- any structured preview or diff reference.

`authorization_source` is descriptive metadata, not proof that a human made
the decision. An event MUST NOT claim `human-approved` or an equivalent state
unless the required decision provenance was independently verified and bound to
the request. Evidence MUST NOT store reusable credentials, raw passkey or
WebAuthn assertion material, or another bearer artifact merely to substantiate
that verification.

An approval record is not evidence of execution. Execution requires a separate
terminal event.

## 8. Execution evidence

Execution events SHOULD identify:

- the application capability invoked;
- execution-time permission and policy re-check outcome;
- idempotency or replay key where relevant;
- start and completion times;
- stable result status and error code;
- side-effect resource identifiers;
- rollback or compensating-action reference when available;
- external recipient when data left the application boundary.

Results SHOULD be summarized. Full model outputs or application records SHOULD
not be copied into evidence without a documented need and retention rule.

## 9. Integrity and access

Evidence storage SHOULD be append-oriented and protected from ordinary agent
write access. Implementations SHOULD consider:

- restricted application capabilities;
- database permissions;
- append-only or write-once storage where justified;
- event chaining or signed export manifests;
- backups and export verification;
- clock and identifier consistency;
- alerts for evidence pipeline failure.

Hash chaining or signatures can reveal modification but do not prevent deletion
by an actor who controls the storage and keys. Implementations must document the
actual trust level of their evidence store.

## 10. Retention and deletion

Retention MUST be configurable and documented. A retention policy SHOULD state:

```text
retention_class
duration
legal or organization basis
deletion method
export behavior
backup behavior
responsible role
last review date
```

Deletion SHOULD be batched and observable. Evidence deletion itself SHOULD
produce a non-sensitive event unless policy requires complete erasure of that
fact.

Implementations SHOULD distinguish:

- routine operational evidence;
- security incident evidence;
- approval records still required for active actions;
- exports held outside the application.

## 11. Export

An evidence export SHOULD contain:

- export identifier and creation time;
- requested scope and filters;
- schema and policy versions;
- included event IDs or range;
- redaction profile;
- integrity digest or signed manifest;
- exporter identity;
- intended audience or purpose.

Export is a data transmission and MUST be authorized, minimized, and recorded.

## 12. Observability relationship

Metrics and traces are not automatically evidence. They may use different
retention, sampling, and access rules.

Trace identifiers SHOULD correlate systems without causing evidence records to
inherit all telemetry payloads. A trace supplied by an untrusted client must not
be treated as proof of identity or uniqueness.

## 13. Minimum conformance

An implementation claiming Draft 0.1 Core Conformance MUST:

- record proposal/decision and terminal execution events;
- correlate approval with the exact request hash;
- record the authorization assurance and verification outcome when policy
  requires human approval;
- identify policy and schema versions;
- preserve unknown identities as unknown;
- redact reusable secrets;
- document retention and deletion behavior;
- restrict evidence access;
- record external recipients when governed data is transmitted;
- document integrity guarantees and their limitations.
