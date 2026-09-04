# Governance Model

**Version:** Draft 0.1  
**Last reviewed:** 2026-09-04

## 1. Purpose

This document defines how a conforming implementation turns application
authority, delegation, policy, approval, and operational state into a decision
about an agent-proposed action.

The governance layer is restrictive. It may reduce existing authority, add
conditions, or deny execution. It may not create application authority.

## 2. Decision inputs

A policy decision is evaluated over:

```text
Application permission
Execution context
Capability metadata
Resource context
Delegation
Site or organization policy
Governance profiles
Data and transmission policy
Rate and iteration budgets
Approval state
```

Each input SHOULD carry a source and version where applicable. Unknown values
must remain unknown. Missing security-relevant context is not an implicit
allow.

## 3. Policy layers and precedence

Policy layers apply in this order:

1. Core security invariants
2. Application authorization
3. Organization or site deny rules
4. Active governance profiles
5. Delegation constraints
6. Capability-specific policy
7. Resource-specific policy
8. Approval and strong-auth state
9. Operational budgets

Rules combine monotonically toward restriction:

```text
DENY > REQUIRE_STRONG_AUTH > REQUIRE_APPROVAL > ALLOW
```

This ordering describes restrictiveness, not a workflow shortcut. Strong
authentication does not replace required approval, and approval does not
replace application authorization.

An implementation MAY represent multiple simultaneous requirements rather than
one scalar decision. It MUST still fail closed if any mandatory requirement is
unsatisfied.

## 4. Risk classes

The base vocabulary is:

| Risk | Typical effect | Default posture |
|---|---|---|
| `read` | Retrieve non-public or public data | Explicit exposure and data review |
| `low` | Reversible, limited local change | Policy-dependent |
| `write` | Create or modify application state | Approval by default |
| `sensitive` | Affect identity, access, private data, or security settings | Strong auth and approval |
| `destructive` | Delete, revoke, publish broadly, or cause hard-to-reverse impact | Exact preview and approval |
| `financial` | Spend, charge, transfer, or create financial obligation | Strong authorization and domain controls |

Risk is one policy input. Implementations MUST evaluate the target, data,
recipient, scale, reversibility, and application authorization independently.

## 5. Data classes

The base vocabulary is:

| Class | Intended meaning |
|---|---|
| `PUBLIC` | Approved for public disclosure |
| `INTERNAL` | Intended for internal organizational use |
| `PERSONAL` | Relates to an identifiable person |
| `SENSITIVE` | Requires heightened handling due to impact or policy |
| `CONFIDENTIAL` | Contractually or organizationally restricted |

Profiles MAY refine these classes but SHOULD map refinements back to the base
vocabulary. Classification MAY be attached to a capability, a resource, a
field, or a computed output.

When multiple classes apply, the most restrictive applicable handling rule
controls.

## 6. Delegation model

A delegation MUST contain:

```text
delegation_id
granting_principal
subject agent/client claims
allowed capabilities
resource constraints
data constraints
transmission constraints
created_at
expires_at
status
```

It MAY add call counts, schedules, monetary limits, network constraints, or
approval thresholds.

Delegation validation MUST verify:

- the delegation is active and unexpired;
- the effective actor matches the bound subject claims;
- the capability and resource are in scope;
- the action stays within data and transmission constraints;
- use and cost budgets remain available;
- the granting principal still has the underlying application authority.

Revocation MUST prevent new execution. Implementations SHOULD define how
revocation affects already-running work and record the result in evidence.

## 7. Approval policy

Approval policies are:

```text
never       policy cannot request approval for this action class
policy      policy decides based on context
required    human approval is mandatory
strong      approval plus strong or stepped-up authentication is mandatory
```

`never` is appropriate only where approval would be meaningless or prohibited;
it does not mean automatic allow.

Every approval MUST be bound to the exact canonical proposal. Approval SHOULD
expire quickly enough for the action's risk and SHOULD be single-use for
non-idempotent or consequential operations.

Canonical action version 1 and its request hash are defined by accepted
[RFC 0001](rfcs/0001-canonical-action-representation.md).

Approvers MUST be authorized for the target action and approval role. The
implementation MUST record who decided, when, under which policy, and which
request hash was decided.

Approver authorization and human decision provenance are independent gates.
When policy requires human approval, the implementation MUST establish that
the accepted decision came through a human-controlled authorization boundary
outside the requesting agent's delegated automation authority. Authentication
establishes an identity or ceremony, and request-forgery protection establishes
request integrity; neither alone proves that a human made this decision.

A page button, DOM event, browser-session cookie, CSRF token or WordPress nonce,
or asserted `authorization_source` MUST NOT by itself satisfy required human
approval. If decision provenance cannot be verified, the action MUST remain
pending or be denied. The verified evidence MUST be bound to the exact request
hash, approver, assurance method, and expiry.

Approval review SHOULD provide structured facts and, where possible, an
application-generated diff. Natural-language summaries may help reviewers but
must not replace the bound structured proposal.

## 8. External-transmission policy

External transmission is evaluated separately from permission to read data.

A transmission policy SHOULD identify:

```text
recipient provider or service
model or endpoint when relevant
purpose
data classes
fields or resource selectors
redaction or minimization rule
retention and training-use notes
region or processing-location note
contract or organization review state
```

The following are distinct decisions:

```text
May read from the application?
May disclose outside the application?
May disclose to this recipient for this purpose?
```

An allow at one boundary MUST NOT imply an allow at the next.

## 9. Operational limits

Governance SHOULD support:

- payload size limits;
- per-capability rate limits;
- per-principal limits;
- per-agent or per-client limits when reliably identified;
- maximum tool calls or iterations;
- cumulative cost limits;
- monetary limits for financial actions;
- maximum retry counts;
- timeouts and cancellation.

Limit failures SHOULD return stable reason codes and SHOULD include retry
information only when disclosure is safe.

## 10. Action lifecycle

### Proposal

The adapter normalizes the request and records a proposal event. No side effect
has occurred.

### Evaluation

Application authorization and governance rules are evaluated. Denial ends the
action. Conditional decisions create an approval or strong-auth requirement.

### Review

The proposed action is stored or represented through an upstream pending-action
contract. The reviewer sees the bound target, arguments, data flow, risk, and
policy reason. When human approval is required, the implementation verifies the
decision through an authorization boundary the requesting agent cannot operate
under its delegated authority.

### Execution

Immediately before execution, the implementation revalidates:

- application permission;
- current policy version or an explicitly pinned valid policy;
- delegation and budget;
- approval and request hash;
- human decision provenance and assurance when required;
- target state assumptions needed for safe application.

### Completion

The implementation records success, failure, cancellation, or expiry. Where an
action is reversible, the evidence SHOULD include the rollback or compensating
action reference without claiming rollback is guaranteed.

## 11. Administrative controls

Implementations MUST protect:

- policy creation and activation;
- risk and data classification changes;
- delegation creation and revocation;
- approval routing and decisions;
- evidence export and deletion;
- provider registry changes;
- emergency disable controls.

Administrative actions require application authorization and request-forgery
protection. Consequential policy changes SHOULD themselves produce evidence.

An implementation SHOULD provide:

- a global emergency disable switch;
- capability-specific disable controls;
- visibility into pending and running actions;
- policy version history;
- safe diagnostics that do not expose secrets.

## 12. Policy versioning

Every decision MUST identify the effective policy version. Policies SHOULD be
immutable once activated; updates SHOULD create a new version.

A version record SHOULD include:

```text
policy_id
version
status
effective_from
effective_until?
author or source
change summary
content digest
```

Implementations MUST define whether pending approvals are invalidated by a
policy change. The safe default is invalidation when the new policy is more
restrictive or materially changes the action's requirements.

## 13. Governance profiles

A profile maps external governance concerns to technical controls and evidence.
It MAY:

- add policy requirements;
- add review fields;
- add evidence fields;
- define control identifiers;
- define source-version metadata.

A profile MUST NOT:

- weaken core security invariants;
- claim legal compliance or certification;
- silently change application authorization;
- treat guidance as immutable law;
- rely on an LLM explanation as a security control.

The first profile is [profiles/JAPAN.md](profiles/JAPAN.md).

## 14. Illustrative policy

The following is non-normative:

```json
{
  "policy_id": "site-content-v1",
  "capability": "content/publish",
  "risk": "write",
  "decision": "REQUIRE_APPROVAL",
  "constraints": {
    "post_types": ["post"],
    "external_transmission": false,
    "max_calls_per_hour": 5
  },
  "approval": {
    "expires_in_seconds": 900,
    "single_use": true,
    "require_diff": true
  }
}
```

Implementations are not required to use this JSON shape.

## 15. Testable invariants

A governance implementation SHOULD test at least:

- application denial cannot be overridden;
- expired or revoked delegation fails;
- out-of-scope resources fail;
- unknown agent identity does not gain agent-specific privileges;
- modified arguments invalidate approval;
- approval cannot be replayed for a second non-idempotent action;
- an agent that can invoke a tool and automate its page cannot satisfy required
  human approval solely by activating that page's review controls;
- an unavailable, false, or failing decision-provenance verifier fails closed;
- external transmission requires a separate allow;
- policy updates have defined effects on pending actions;
- exhausted budgets fail closed;
- administrative state changes are authorized and evidenced;
- secrets are redacted from decisions and records.
