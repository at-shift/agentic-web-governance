# Agentic Web Governance Specification

**Version:** Draft 0.1  
**Status:** Experimental, Request for Comments  
**Last reviewed:** 2026-09-04

## 1. Scope

This specification defines a protocol-neutral governance model for actions that
AI agents or automated clients perform on web systems under delegated human or
organizational authority.

It defines:

- a normalized execution context;
- restrictive policy decisions;
- delegated authority and scope;
- approval binding and execution rules;
- data classification and external-transmission controls;
- operational budgets;
- evidence requirements;
- adapter responsibilities;
- conformance invariants.

It does not define an agent communication protocol, an identity proof format,
an authorization server, an agent runtime, or application business logic.

The key words MUST, MUST NOT, REQUIRED, SHOULD, SHOULD NOT, and MAY are to be
interpreted as requirements for conforming implementations.

## 2. Core terms

**Human authority**  
The person whose existing application authority is being exercised or
delegated.

**Organization authority**  
Site-level or organization-level policy that may further restrict actions.

**Application principal**  
The identity recognized by the target application, such as a WordPress user.

**Agent**  
A logical automated actor. An agent identity may be unavailable or may differ
from both the application principal and protocol client.

**Client**  
The application or connector that communicates with a protocol endpoint.

**Protocol adapter**  
The component that translates MCP, A2A, REST, CLI, or another interface into
the protocol-neutral governance model.

**Application adapter**

The component that maps an application's native principals, capabilities,
validation, authorization, execution, and persistence boundaries to the
protocol-neutral governance model.

**Capability**  
A machine-invocable application operation with defined inputs, outputs, and an
application authorization check. In WordPress, a capability is represented by
an Ability.

**Governed action**  
A proposed or executing capability invocation evaluated by this model.

**Delegation**  
A bounded subset of existing application authority that a human or
organization permits an agent or client to exercise.

**Approval**  
An explicit decision about an exact proposed action. Approval is not a general
grant of application authority.

**Human authorization**  
Verified evidence that a required approval decision originated through a
human-controlled authorization boundary rather than through the requesting
agent's delegated automation authority. Human authorization is distinct from
application permission, approver authorization, authentication, and
request-forgery protection.

**Evidence**  
A privacy-aware record that supports reconstruction of a policy decision and
its execution outcome.

## 3. Architectural boundary

```text
Human / organization
        |
        v
Authorization and delegation
        |
        v
Agent / client
        |
        v
Protocol adapter
        |
        v
Agentic governance
  - normalize context
  - classify risk and data
  - evaluate policy
  - enforce budgets
  - require approval
  - record evidence
        |
        v
Application adapter
        |
        v
Application authorization
        |
        v
Application capability
```

Business logic MUST remain in the application capability layer. A protocol
adapter MUST NOT become a parallel implementation of that business logic.

Application authorization MUST be evaluated for the effective application
principal. Governance MAY deny an action that the application permits.
Governance MUST NOT allow an action that the application denies.

### 3.1 Platform portability

The governance core MUST NOT require a WordPress, MCP, or other
platform-specific identity, capability API, permission model, transport, or
storage primitive. Platform-specific values MAY appear as typed adapter data,
but their interpretation and enforcement MUST remain in the relevant mapping
or adapter.

An application adapter MUST:

1. resolve the effective application principal using application-native rules;
2. map a native operation to a stable capability and validated arguments;
3. preserve native validation and authorization as mandatory and authoritative;
4. expose only authority the principal already holds in the application;
5. execute through the application's capability or business-logic boundary;
6. map application outcomes into minimized core evidence without leaking raw
   credentials, inputs, or outputs;
7. fail closed when a required native authorization or execution boundary
   cannot be established.

Protocol-adapter and application-adapter responsibilities MAY be implemented in
one package, but their authority boundaries MUST remain distinguishable. Adding
a second protocol to one application, such as REST beside MCP for WordPress,
does not demonstrate application-platform portability.

WordPress is the first reference application adapter. Its users, Abilities,
hooks, options, transients, and MCP Adapter integration MUST NOT become required
core primitives.

The project MUST NOT claim implementation-level portability until at least two
materially distinct application adapters, including one non-WordPress adapter,
preserve the same core security invariants and pass the same protocol-neutral
conformance fixtures. A second adapter MAY be a minimal reference application;
it does not need to be a production product or another CMS.

This proof threshold does not block continued development of the WordPress
reference adapter. It limits claims about what the executable implementation
has demonstrated. See [RFC 0002](rfcs/0002-platform-adapter-boundary.md).

## 4. Normalized execution context

Every governed request MUST be normalized before policy evaluation.

```text
ExecutionContext
  request_id
  request_time
  application
  application_principal
  capability
  canonical_arguments
  protocol
  client_identity?
  agent_identity?
  delegation_id?
  resource_context?
  authorization_context
  transmission_context?
  trace_context?
  profile_context?
```

Requirements:

1. `request_id` MUST be unique within the implementation's evidence domain.
2. `capability` and `canonical_arguments` MUST identify the operation being
   evaluated.
3. An unavailable `agent_identity` or `client_identity` MUST remain unknown.
4. The implementation MUST NOT infer that client, agent, and application
   principal are the same actor.
5. Transport metadata that does not affect authorization MUST NOT change the
   canonical action identity.
6. Security-relevant adapter claims MUST retain their issuer and validation
   status.

## 5. Capability governance metadata

Implementations SHOULD reuse application-native metadata before introducing
project-specific fields. Governance metadata MAY add concepts the application
does not express.

Minimum metadata:

```text
governance_state   disabled | enabled | conditional
risk_class         read | low | write | sensitive | destructive | financial
approval_policy    never | policy | required | strong
data_classes[]
external_transmission
reversibility
budget_policy?
```

Native labels such as `readonly`, `destructive`, and `idempotent` are useful
inputs but MUST NOT alone determine exposure or approval.

Read-only does not imply public, non-sensitive, or inexpensive.

## 6. Policy decisions

A policy evaluation MUST return one of:

```text
ALLOW
DENY
REQUIRE_APPROVAL
REQUIRE_STRONG_AUTH
```

A decision SHOULD include:

```text
decision
reason_codes[]
policy_id
policy_version
matched_rules[]
required_controls[]
expires_at?
```

Policy evaluation MUST consider all applicable layers:

```text
application permission
site or organization policy
governance profile
delegation constraints
capability metadata
resource constraints
data and transmission policy
rate or iteration budget
approval state
```

Conflicts MUST resolve toward the more restrictive result. A profile or local
policy MUST NOT weaken a mandatory core invariant.

See [GOVERNANCE.md](GOVERNANCE.md) for policy precedence and lifecycle rules.

## 7. Delegation

Delegation answers:

> Which subset of this principal's current application authority may this
> agent or client exercise?

A delegation MAY constrain:

- capability identifiers;
- object identifiers or resource selectors;
- content or resource types;
- current-user-only access;
- time window and expiry;
- invocation count;
- cumulative cost or monetary amount;
- external recipients or providers;
- data classifications;
- approval thresholds.

A delegation MUST NOT grant authority the application principal lacks at
execution time. Revocation, expiry, principal changes, and application
permission changes MUST take effect before execution.

Delegation identifiers are references to authorization state, not proof of
identity by themselves.

## 8. Approval

Approval is a decision on an immutable action proposal.

An approval record MUST bind at least:

```text
capability
application_principal
known agent and client context
canonical arguments
resource context
data and transmission context
policy version
expiry
request hash
authorization source and assurance
```

Canonical action version 1 and its request hash algorithm are defined by
accepted [RFC 0001](rfcs/0001-canonical-action-representation.md).

The implementation MUST invalidate or re-request approval when any bound field
changes. It MUST re-check application permission, delegation, policy, budget,
and approval status immediately before execution.

When policy requires human approval, the implementation MUST verify that the
decision originated through an authorization mechanism whose accepted evidence
cannot be produced by the requesting agent or agent host within its delegated
automation authority. Authorization of the approver and provenance of the
decision are separate checks; both MUST succeed.

A DOM button activation, an event or user-activation signal available to the
agent host, an authenticated browser session, a cookie, a CSRF token or
WordPress nonce, or a claimed `authorization_source` MUST NOT by itself be
treated as proof of a human decision. If required decision provenance cannot be
established, the action MUST remain pending or be denied.

An implementation MAY establish the boundary through a trusted user-agent
confirmation surface, an out-of-band reviewer, an organizational approval
service, or a cryptographically bound user-verification ceremony. The core does
not mandate one technology. Accepted evidence MUST be bound to the proposal or
request hash, authorized approver, assurance method, and expiry.

Approval UIs SHOULD show:

- acting principal and known agent/client;
- target and proposed change;
- external transmission and recipient;
- risk and reversibility;
- policy reason;
- approval expiry;
- a reviewable diff where practical.

An LLM-generated summary MUST NOT be the only security-relevant representation
of the proposed action.

Generic pending-action contracts SHOULD be reused when an upstream platform
provides them. Product-specific storage, routing, UI, and evidence remain the
implementation's responsibility.

## 9. Data handling and external transmission

Implementations MUST treat transfer from the governed application to another
service as a distinct policy boundary.

Suggested data classes:

```text
PUBLIC
INTERNAL
PERSONAL
SENSITIVE
CONFIDENTIAL
```

An external-transmission decision SHOULD consider:

- data class;
- recipient provider and model or service;
- purpose;
- minimum necessary payload;
- redaction or transformation;
- retention and training-use metadata;
- region or processing-location metadata where relevant;
- organization approval state.

The implementation MUST NOT label data as legally compliant merely because a
technical policy allowed transmission.

## 10. Operational budgets

Implementations SHOULD support bounded use by:

- site or organization;
- application principal;
- agent or client when reliably identified;
- capability;
- time window;
- iteration or tool-call count;
- cumulative cost or monetary amount where applicable.

Exhausted budgets MUST fail closed for the affected action. Budget keys MUST
not depend on identities that the implementation cannot reliably establish.

## 11. Evidence

Every governed external action MUST produce an evidence event for the policy
decision. Executed actions MUST additionally record their terminal outcome.

Evidence MUST NOT contain reusable credentials, authorization headers,
passwords, session cookies, raw payment credentials, or other secrets.

Evidence SHOULD support correlation across proposal, approval, execution, and
failure events without requiring full sensitive payloads.

See [EVIDENCE.md](EVIDENCE.md) for the event model and privacy rules.

## 12. Protocol adapter requirements

Every protocol adapter MUST:

1. authenticate and validate protocol-level claims using the protocol's rules;
2. normalize the request into `ExecutionContext`;
3. preserve the distinction between client, agent, and application principal;
4. invoke the same governance pipeline used by other adapters;
5. preserve application validation and authorization;
6. map policy and application errors without leaking sensitive internals;
7. propagate or create correlation identifiers;
8. avoid implementing application business logic;
9. fail closed when required governance context cannot be established.
10. preserve the distinction between agent automation authority and any
    required human-authorization channel;
11. never synthesize or infer a human decision from protocol or UI activity
    alone.

Protocol-native discovery SHOULD remain protocol-native. This specification
does not define a universal `agent.json` or equivalent discovery format.

Specific mappings are documented in [`mappings/`](mappings/).

## 13. Action lifecycle

```text
RECEIVED
  -> NORMALIZED
  -> APPLICATION_PERMISSION_CHECKED
  -> POLICY_EVALUATED
  -> DENIED
     or APPROVAL_PENDING
          -> AUTHORIZATION_VERIFIED (when human approval is required)
          -> READY
     or READY
  -> PERMISSION_AND_POLICY_RECHECKED
  -> EXECUTING
  -> SUCCEEDED | FAILED | CANCELLED | EXPIRED
```

Implementations MAY use different internal state names, but MUST preserve these
semantic boundaries. A proposal MUST NOT be represented as already executed.

Retries MUST be explicit. Non-idempotent actions require an idempotency or
replay-control strategy appropriate to the application.

## 14. Abstract records

This specification does not mandate database tables. An implementation MAY
materialize:

- policy versions;
- delegations;
- pending actions and approvals;
- rate and budget counters;
- evidence events;
- provider or recipient governance metadata.

Storage SHOULD be introduced only for implemented features. Implementations
SHOULD NOT create speculative permanent records for universal agent identity,
reputation, marketplaces, or protocol-specific task state.

## 15. Security invariants

A conforming implementation MUST preserve:

1. **No permission widening:** governance never grants application authority.
2. **No adapter bypass:** every supported external action uses the same core
   governance checks.
3. **Exact approval:** approval is bound to the exact action and expires.
4. **Decision provenance:** required human approval cannot be satisfied solely
   by evidence the requesting agent or agent host can produce through its
   delegated automation authority.
5. **Execution-time re-check:** permission and policy are re-evaluated before
   the side effect.
6. **Identity honesty:** unknown identities are not invented or conflated.
7. **Untrusted input:** agent requests, tool metadata, and retrieved content are
   treated as untrusted.
8. **Data-boundary control:** external transmission is separately evaluated.
9. **Evidence restraint:** evidence is useful, integrity-protected, and
   secret-free.
10. **Bounded operation:** payload, rate, iteration, and cost limits fail safely.
11. **Administrative integrity:** policy and approval administration require
    application authorization and request-forgery protection.

The reusable threat hypotheses and mitigations are in
[THREAT-MODEL.md](THREAT-MODEL.md).

## 16. Conformance

An implementation MAY claim **Draft 0.1 Core Conformance** only if it:

- implements Sections 4 through 13 for every external action it identifies as
  governed;
- documents unsupported adapters and capability classes;
- demonstrates the invariants in Section 15 with automated or reproducible
  tests;
- identifies the policy and evidence versions used by each decision;
- documents its retention, redaction, and deletion behavior;
- does not claim legal or regulatory certification from this specification.

An adapter mapping or governance profile MAY claim compatibility only when it
documents which core requirements it adds, satisfies, or leaves unsupported.

An implementation claiming platform portability MUST additionally satisfy the
proof threshold in Section 3.1. Multiple protocol adapters for one application
count as one application adapter for this purpose.

No implementation currently claims conformance.

## 17. Implementation sequence

The evidence-calibrated implementation sequence is:

1. Govern and audit existing read-only application capabilities.
2. Add scoped delegation without widening application authority.
3. Add durable pending actions, human approval, and replay protection.
4. Add external-transmission policy and provider governance metadata.
5. Add asynchronous and protocol-specific adapters only for concrete use cases.

WordPress-specific sequencing is described in
[mappings/WORDPRESS.md](mappings/WORDPRESS.md).

## 18. Non-goals

The core specification does not define:

- a replacement for MCP, A2A, REST, or application capability APIs;
- a general-purpose OAuth authorization server;
- a universal agent identity or reputation system;
- a workflow or autonomous-agent runtime;
- a universal agent discovery format;
- arbitrary shell, filesystem, database, or code-execution tools;
- vector search, RAG, or a knowledge graph;
- fully autonomous financial execution by default;
- legal compliance conclusions.

## 19. Change process

Normative changes require an RFC. Editorial corrections, source updates, and
clarifications that do not change conformance may be merged directly.

Open design decisions are tracked in [OPEN-QUESTIONS.md](OPEN-QUESTIONS.md).
