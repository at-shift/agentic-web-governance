# Request for Comments Process

The RFC process is used for material changes to the Agentic Web Governance core,
profiles, mappings, and conformance requirements.

## When an RFC is required

Use an RFC for:

- new or changed normative terms;
- changes to security invariants;
- a portable policy or evidence format;
- new conformance levels;
- new governance profiles;
- new protocol or platform mappings with normative requirements;
- public API or identity decisions that implementations would depend on;
- accepted changes to project governance.

Editorial corrections, source updates, examples, and clarifications that do not
change conformance may be proposed without an RFC.

## Lifecycle

```text
DRAFT -> DISCUSSION -> ACCEPTED -> IMPLEMENTED
                    \-> REJECTED
                    \-> WITHDRAWN
                    \-> SUPERSEDED
```

During Draft 0.x, `ACCEPTED` means approval by the initial maintainer under
[../CONTRIBUTING.md](../CONTRIBUTING.md), with the rationale recorded in the
pull request or RFC. An unresolved objection identifying a violation of a core
security invariant blocks acceptance.

`ACCEPTED` records a settled technical decision, not an expectation that a
proposal will eventually be ready. Acceptance MUST be based on the review
standard and the proposal's stated acceptance or conformance requirements being
met at decision time. Implementation status and overall project maturity remain
separate from RFC acceptance.

## Numbering

Use four digits in sequence:

```text
0001-short-title.md
0002-next-title.md
```

Do not renumber an RFC after public discussion begins.

## RFC index

| RFC | Status | Topic |
|---|---|---|
| [0001](0001-canonical-action-representation.md) | Accepted | Canonical action projection, JCS, and request hashing |
| [0002](0002-platform-adapter-boundary.md) | Accepted | Platform-adapter boundary and implementation portability proof |

## Required content

Every RFC must state:

- status, authors, and dates;
- the problem and affected actors;
- proposed normative changes;
- security, privacy, evidence, and compatibility effects;
- rejected alternatives;
- migration and conformance impact;
- unresolved questions.

Use [0000-template.md](0000-template.md).

## Review standard

Reviewers should ask:

1. Does the proposal preserve application authorization?
2. Can it be expressed without binding the core to one protocol?
3. Does it create or transmit sensitive data?
4. How is exact approval and replay handled?
5. What evidence is required, and could that evidence leak data?
6. Is an upstream primitive already available?
7. Which claims are implemented, standardized, research-backed, or speculative?
8. Is the proposal testable?

## Initial topics

High-priority RFC candidates are listed in
[../OPEN-QUESTIONS.md](../OPEN-QUESTIONS.md), especially policy interchange,
identity semantics, and evidence integrity levels.
