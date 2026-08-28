# Security Policy

## Reporting a vulnerability

Use GitHub private vulnerability reporting from this repository's **Security**
tab when it is available. If that channel is unavailable, contact `@at-shift`
through an existing private channel and ask for a secure reporting route. Do
not include vulnerability details in a public issue or discussion.

A useful report includes the affected document, version, component or future
implementation path; prerequisites; realistic impact; reproduction or
reasoning; and a suggested mitigation when known. Do not send live credentials,
unnecessary personal data, or data taken from systems you are not authorized to
test.

## System and scope

This repository currently contains a protocol-neutral governance specification,
security and evidence models, protocol and WordPress mappings, conformance
tooling, and an executable in-memory reference kernel. It does not
contain a production implementation or externally reachable governance
endpoint.

This policy covers:

- contradictions or omissions that would make a conforming implementation
  violate a stated security invariant;
- unsafe executable reference code, tests, or deployment material included here;
- repository automation or configuration maintained by this project;
- accidental disclosure of secrets or non-public security material here.

Vulnerabilities in WordPress, MCP, A2A, or another upstream project should be
reported to that project unless this repository introduces or materially
worsens the issue.

## Threat model and trust boundaries

`THREAT-MODEL.md` is the canonical design threat model. Important boundaries
include client to adapter, adapter to governance, governance to application
authorization, proposal to reviewer to executor, application to external
service, and components to evidence storage.

Agent requests, protocol claims, retrieved content, capability arguments,
external data, and self-asserted identity metadata are untrusted unless their
provenance and validation are explicitly established.

## Security invariants

1. Governance and adapters cannot widen application authority.
2. Every supported external action crosses the same governance boundary.
3. Approval is bound to the exact request, expires, and is replay-controlled.
4. Mutable authorization, policy, delegation, budget, and approval state is
   re-checked before side effects.
5. Client, agent, runtime, and application-principal identities remain distinct;
   unknown identity is not invented.
6. Reading application data does not authorize external disclosure.
7. Evidence excludes reusable secrets, minimizes sensitive data, and is access
   controlled.
8. Administrative changes are authorized, request-forgery protected,
   versioned, and evidenced.
9. Payload, rate, iteration, retry, time, and cost limits fail safely.

## Reportable findings and severity context

A security finding needs a realistic path from an attacker-controlled input or
compromised actor to an invariant violation with meaningful confidentiality,
integrity, availability, financial, privacy, or authorization impact.

In specification-only material, an unimplemented attacker story is a design
hypothesis rather than a software vulnerability. It becomes reportable here
when normative text requires unsafe behavior, contradicts an invariant, or
claims protection that the specified controls cannot provide. For future code,
severity depends on reachable behavior and the authority or data an attacker
can gain, not only on the presence of a suspicious pattern.

## Out of scope

- Hypothetical bugs in implementation code that does not exist in this
  repository.
- Upstream vulnerabilities not introduced or materially worsened here.
- General policy preferences without a concrete invariant, safety, or
  interoperability impact.
- Claims that this project provides legal certification or eliminates all
  prompt injection, rollback, provider, or administrator risk.

These exclusions do not suppress accidental secret exposure, supply-chain
issues in project-controlled dependencies, or concrete contradictions in the
normative specification. This policy accepts no security risk by implication.

## Known limitations

The WordPress interception point, storage, evidence integrity level, multisite
boundaries, and role mappings remain open design questions. Canonical action
version 1 is defined by accepted RFC 0001, but no production integration claims
conformance yet. Provider metadata is not independently verified. A fully
compromised application administrator already has broad application authority,
although organizational separation and evidence integrity can still be
security-relevant.

## Response and disclosure

The maintainer will validate reports on a best-effort basis, distinguish design
issues from implementation vulnerabilities, and coordinate remediation and
disclosure with affected upstream projects when necessary. Public disclosure
should wait until a fix or documented mitigation is available and affected
parties have had a reasonable opportunity to respond.
