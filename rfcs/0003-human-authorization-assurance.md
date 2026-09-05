# RFC 0003: Human Authorization Assurance

- **Status:** Accepted
- **Authors:** `@at-shift`
- **Created:** 2026-09-04
- **Updated:** 2026-09-05
- **Target version:** Draft 0.2
- **Supersedes:** None
- **Superseded by:** None

## Summary

This RFC distinguishes an authorized approver from a verifiably human approval
decision. When policy requires human approval, a conforming implementation must
establish that the accepted decision came through an authorization boundary the
requesting agent or agent host cannot operate under its delegated automation
authority.

A page button, DOM event, authenticated session, CSRF token, WordPress nonce,
or asserted authorization source is not sufficient by itself. The requirement
is technology-neutral and may be satisfied by a trusted user-agent surface,
out-of-band review, organizational approval service, or a cryptographically
bound user-verification ceremony.

## Decision

Accepted by `@at-shift` on 2026-09-04.

The maintainer accepts the independent human-authorization invariant, its
fail-closed behavior, and the protocol-neutral adapter boundary defined here.
Acceptance settles the specification direction; it did not by itself mean that
the JavaScript reference kernel, WordPress example, WebMCP adapter, or any
production mechanism implemented this RFC. The reference kernel now implements
the protocol-neutral verifier port and its fail-closed test contract. It does
not supply the independent production mechanism needed to claim the full
human-authorization path.

This decision is based on the requirements being explicit, testable, and
independent of an unresolved upstream feature. Implementations may continue to
build proposal, policy, and application-authorization paths before selecting a
production human-authorization mechanism, but they may not claim the required
human-approval path is implemented until the conformance conditions below pass.

## Problem

An agent host may be able to invoke a tool and automate the same application's
DOM. If a consequential tool creates a proposal and the page displays an
Approve button, the same automated actor may be able to activate that button.
The application can still observe a logged-in user, a valid CSRF token, and an
authorized account, yet none of those facts establishes that a human made the
decision.

Exact-request hashing prevents bait-and-switch and replay, but it does not
identify the origin of the approval decision. Approver authorization establishes
what an identity may approve, not whether a human actually exercised that
authority for this proposal. Strong authentication can raise assurance only
when its ceremony is independently controlled and bound to the decision.

Without a separate invariant, implementations can consistently verify the
wrong property and describe agent-generated page activity as human approval.

## Affected actors

- people whose application authority is delegated to agents;
- authorized approvers and organizational reviewers;
- agent and browser hosts that expose both tool and DOM automation;
- protocol adapters, including WebMCP adapters;
- application adapters and capability owners;
- auditors relying on approval evidence.

## Goals

- Separate approver authorization, decision provenance, authentication, and
  request-forgery protection.
- Prevent a requesting agent from self-satisfying a required human approval.
- Preserve exact-request binding, expiry, replay control, and execution-time
  re-checks.
- Keep the core independent of a browser, passkey, WordPress, or protocol API.
- Define a requirement that adapters can test and evidence honestly.

## Non-goals

- Mandate WebAuthn, passkeys, a particular user agent, or one approval product.
- Claim that all browser agents can automate page review controls.
- Replace native application authorization or request-forgery protection.
- Prove that a device user understood the proposal.
- Define universal legal consent, electronic-signature, or non-repudiation
  semantics.
- Require the current reference kernel to implement approval provenance before
  other implementation stages continue.

## Proposal

### 1. Distinct checks

A required approval path must evaluate four distinct properties:

1. **Application authorization:** the effective principal may perform the
   underlying action.
2. **Approver authorization:** the identified reviewer may approve this action
   class and scope.
3. **Decision provenance:** the accepted decision originated through a boundary
   outside the requesting agent's delegated automation authority.
4. **Authentication assurance:** when policy requires strong or stepped-up
   authentication, the required ceremony succeeded and is bound to the same
   decision.

Success at one gate must not imply success at another.

### 2. Proposal and commit separation

An action requiring human approval must first become an immutable proposal. The
proposal must not perform the consequential side effect.

```text
agent request
  -> canonical proposal and request hash
  -> policy requires human approval
  -> independent authorization boundary
  -> verified decision bound to proposal
  -> mutable-state re-check
  -> side effect
```

The commit or execution path must consume the bound decision, enforce expiry
and replay rules, and re-check application permission, policy, delegation,
budget, and relevant target state.

### 3. Authorization-boundary requirements

When policy requires human approval, accepted decision evidence must not be
producible by the requesting agent or agent host within its delegated
automation authority.

The following signals are insufficient by themselves:

- activating a page button or DOM control;
- an event flag or user-activation state visible to the page;
- an authenticated browser session or application cookie;
- a CSRF token or WordPress nonce;
- possession of a pending-action identifier;
- an asserted `authorization_source` value;
- a tool annotation such as `consequentialHint`.

These signals can remain useful for presentation, authentication, request
integrity, correlation, or risk classification. They simply do not establish
human decision provenance alone.

### 4. Technology-neutral adapters

A conforming adapter may use:

- a trusted browser or user-agent confirmation surface not exposed to the
  requesting agent's automation authority;
- an out-of-band reviewer on a separately controlled channel;
- an organizational approval service with independent reviewer interaction;
- a WebAuthn/passkey or comparable user-verification ceremony that binds the
  exact proposal and presents sufficient action context.

Other mechanisms are allowed when their trust boundary and attacker model are
documented. The core must consume a verified result through an explicit port;
it must not infer provenance from UI or protocol metadata.

### 5. Bound evidence

The verified decision must bind at least:

```text
request_hash or proposal_id plus request_hash
approver identity
approval decision
authorization assurance method
verification result
decision time
expiry
replay or single-use state
```

The evidence store records the result and assurance metadata, not reusable
credentials, raw passkey assertions, cookies, or bearer artifacts.

## Security and privacy

The RFC closes an approval-confusion path in which an agent uses an authorized
human session to manufacture the appearance of human review. It does not make
prompt injection impossible, prove comprehension, or protect a fully
compromised authorization service.

Independent review can introduce sensitive proposal data into a second system.
Adapters must minimize the displayed and transmitted payload, authorize access,
apply retention rules, and avoid copying secrets into decision evidence.

The verifier must fail closed on false, unavailable, malformed, expired, or
exceptional results. Assurance labels are security claims and must not be
accepted from the requesting agent without verification.

## Compatibility and upstream relationship

This RFC adds a core invariant and adapter responsibility without changing
canonical action version 1. Existing proposal and execution records can remain
compatible if implementations add decision-provenance assurance before claiming
the required human-approval path.

WebMCP issue 288 documents one observed agent-host behavior and motivates the
attacker capability. It is evidence for a design hypothesis, not a universal
claim about WebMCP. The 2026-09-04 upstream draft includes
`consequentialHint`, but annotations remain hints rather than authorization.
WebMCP pull request 289 is open; this RFC does not depend on its proposed
validation rules or error behavior.

WordPress sessions, capabilities, permission callbacks, and nonces retain their
normal authorization and request-integrity roles. They do not become proof of
human decision provenance.

## Conformance and testing

An implementation may claim this RFC's human-authorization path only if it can
demonstrate all of the following:

1. The requesting agent can invoke the proposal path but cannot produce an
   accepted decision through its delegated automation interfaces.
2. An authorized human can approve and reject the exact proposal through the
   documented independent boundary.
3. An unauthorized approver is rejected even when decision provenance is
   otherwise valid.
4. Altered arguments, target, recipient, policy, or request hash invalidate the
   decision.
5. Expired, replayed, missing, false, and verifier-error results fail closed.
6. Strong-auth requirements are verified independently and bound to the same
   proposal.
7. Evidence records the assurance result without reusable credentials or
   unnecessarily sensitive proposal data.

Protocol adapters must additionally test any shared tool and UI surface under a
host capable of both tool invocation and DOM automation.

## Migration

No stored-data migration is required for the current repository because it has
no production approval store. Future implementations that already record
approval should add a distinct assurance result and treat existing records as
unverified unless their original ceremony satisfies this RFC and can be
validated.

The JavaScript reference kernel may describe RFC 0003's protocol-neutral
verification boundary as implemented once its mandatory verifier port and
fail-closed tests exist. It and the WordPress example must not claim a
production human-authorization mechanism or full RFC conformance until an
actual independent adapter passes the conformance conditions above.

## Alternatives

### Treat a page Approve button as human approval

Rejected because a host that can operate the page may produce the same signal.

### Rely on authenticated session and CSRF protection

Rejected because they establish account/session and request-integrity
properties, not who originated the decision.

### Require `Event.isTrusted` or user activation

Rejected as a universal boundary because host-level automation behavior and
available signals vary, and the requesting host may control activation.

### Require passkeys for every approval

Rejected because passkeys are one useful adapter option but are not universally
available or appropriate. A ceremony also must bind and present the exact
proposal to satisfy this RFC.

### Wait for WebMCP to define approval

Rejected because the invariant applies across protocols and applications. An
upstream feature can later provide a conforming adapter without defining the
core requirement.

## Open questions

- Which portable assurance vocabulary should evidence use without overstating
  guarantees?
- How should trusted user-agent surfaces expose a verified result to
  applications without creating a replayable bearer token?
- Which multi-party or separation-of-duties profiles should organizational
  approval support?

## References

- [WebMCP Draft Community Group Report](https://webmachinelearning.github.io/webmcp/)
- [WebMCP issue 288](https://github.com/webmachinelearning/webmcp/issues/288)
- [WebMCP pull request 289](https://github.com/webmachinelearning/webmcp/pull/289)
- [WebMCP pull request 217](https://github.com/webmachinelearning/webmcp/pull/217)
- [Web Authentication: An API for accessing Public Key Credentials](https://www.w3.org/TR/webauthn-3/)
