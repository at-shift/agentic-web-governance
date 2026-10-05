# WebMCP Mapping

**Mapping version:** Draft 0.1  
**Upstream snapshot:** 2026-10-02 Draft Community Group Report, repository reviewed 2026-10-05  
**Last reviewed:** 2026-10-05

## 1. Purpose

This mapping applies the Agentic Web Governance core to WebMCP, the browser API
through which a web application exposes tools to user agents and agents. It
does not define a WebMCP implementation or replace the evolving upstream API.

WebMCP and the network-oriented Model Context Protocol have related tool
concepts but distinct trust and execution boundaries. This mapping therefore
does not overload [`MCP.md`](MCP.md).

## 2. Upstream boundary

The current upstream Draft Community Group Report includes a browser API for
registering and invoking tools, tool activation and cancellation events,
`consequentialHint` and `debugging` annotations, and the provider document's
origin in observed tool collections. These contracts remain owned by WebMCP.
The format in which a browser agent receives observations is
implementation-defined, so this mapping does not assume every agent-facing tool
description carries origin.

The `executeTool()` caller-facing contract accepts a discovered
`RegisteredTool` and a JavaScript input object, rejects non-object values, and
serializes the object for transfer to the tool's document. The discovered
`RegisteredTool.inputSchema` is exposed as a parsed JavaScript object. Chrome's
documented transition deprecates JSON-stringified input from Chrome 155. An
adapter therefore SHOULD pass a serializable object directly and MUST NOT
depend on pre-stringified arguments or schema values as its normal path. The
current draft treats an omitted or `undefined` input as an empty object; this
default does not satisfy any required application fields or reduce validation.

Each page observation still contains the complete tool map required by the
draft. A non-normative clarification permits agent products to diff, cache,
filter, or absorb repeated observations rather than append each full map to
model input verbatim. Those context-management choices do not change the
registered tools, provider provenance, or application authority.

The upstream repository now explicitly treats headless browsing scenarios as
in scope where client-side WebMCP tools are reused for task completion,
including transitions between human-in-the-loop and headless experiences. The
same clarification distinguishes WebMCP from purely server-side task completion
and from backend-focused protocols such as MCP.

Upstream annotations, descriptions, provider origin, and execution-mode
descriptions are discovery, provenance, and interoperability inputs. They are
not authoritative declarations of application permission, risk, approval, or
human presence. A WebMCP implementation may use them to inform presentation or
routing, but governance must derive the effective decision from application
authority and policy.

## 3. Actors and authority

The adapter must distinguish:

```text
human user or reviewer
logical agent
agent host, browser, or user agent
web page, tool owner, and tool-owning document origin
application server
application principal
```

The agent host may be able to invoke WebMCP tools and automate the same page's
DOM. A page-controlled review button is therefore not necessarily an
independent human-authorization boundary.

A headless execution path does not collapse these actor distinctions. Human
presence in a visible browser UI MUST NOT be assumed merely because a web page
registered the tool, and absence of a visible UI MUST NOT widen the automation
authority granted to the agent or host.

## 4. Core mapping

| WebMCP concept | Core mapping | Rule |
|---|---|---|
| Registered tool | Protocol-facing capability description | Registration does not create application authority |
| Tool invocation | Governed action proposal | Normalize before policy evaluation |
| Tool input schema | Protocol validation input | Application/server validation remains authoritative |
| Tool annotations | Untrusted capability metadata | Hints may restrict treatment but never grant permission or approval |
| `consequentialHint` | Risk-classification signal | `false`, absent, or stale metadata cannot suppress required controls |
| `debugging` | Developer-tooling classification hint | Does not grant developer authority or lower policy, approval, or evidence requirements |
| Observed tool origin | Provider provenance | Bind it to tool identity; origin alone grants no permission or application authority |
| `toolactivated` / `toolcancel` events | Lifecycle-observation signals | Correlate execution state; neither event proves a terminal outcome, rollback, or human decision |
| Browser or agent identity | Client or agent context | Do not substitute it for the application principal |
| Visible or headless execution mode | Execution context | Does not establish human presence, approval, or additional authority |
| Tool result or error | Protocol result mapping | Do not leak policy, credentials, or sensitive application internals |

When tools from multiple documents or origins are visible, an adapter MUST keep
same-named tools distinct by binding trusted provider origin to the normalized
tool identity. It MUST accept that origin only from a trusted browser or user
agent observation or registration context, not from caller-asserted metadata.
If provider provenance is missing, untrusted, or ambiguous, the adapter MUST
fail closed rather than borrowing another origin's policy, delegation,
classification, or approval.

## 5. Execution paths

A low-risk read path may execute after the ordinary application and governance
checks when policy does not require approval:

```text
WebMCP invocation
  -> normalize and validate
  -> application authorization
  -> governance evaluation and budgets
  -> execution-time re-check
  -> application capability
  -> minimized evidence
```

The same checks apply in a headless browsing scenario. A visible UI is not a
prerequisite for low-risk execution, and headless operation is not a reason to
skip policy, authorization, budgets, validation, or evidence.

A consequential path that requires approval must separate proposal from
execution:

```text
WebMCP invocation
  -> immutable proposal and request hash
  -> APPROVAL_PENDING
  -> independent human-authorization boundary
  -> verified, bound decision
  -> authorization, policy, budget, and state re-check
  -> application capability
  -> minimized evidence
```

If a headless invocation reaches a policy decision that requires human
approval, the action MUST remain pending or be denied until valid authorization
evidence arrives through an accepted independent boundary. An implementation
MUST NOT downgrade or bypass required approval merely because no interactive
browser surface is currently present.

The initial WebMCP tool SHOULD be proposal-only when immediate execution would
bypass a required approval boundary. The later commit or execution operation
must consume the bound decision and must not infer approval from the existence
of a pending action.

## 6. Human-authorization boundary

When policy requires human approval, the adapter MUST meet
[RFC 0003](../rfcs/0003-human-authorization-assurance.md). In particular:

- the requesting agent and agent host must not be able to produce the accepted
  decision evidence through their delegated automation authority;
- a DOM click, synthetic or host-mediated event, user-activation signal
  available to the agent host, same-session login, cookie, CSRF token, or
  asserted authorization source is not sufficient by itself;
- approver authorization and human decision provenance must both be verified;
- the decision evidence must bind the request hash, approver, assurance method,
  and expiry;
- missing, false, or failed provenance verification must remain pending or fail
  closed.

A trusted user-agent confirmation surface, out-of-band review, organizational
approval service, or cryptographically bound user-verification ceremony may
supply this boundary. The core does not require one WebMCP- or browser-specific
mechanism.

Visible, hidden, background, and headless browser execution are all untrusted
with respect to proving a human decision unless the accepted authorization
mechanism independently establishes that provenance.

## 7. Validation and input handling

Browser-side schema validation is defense in depth. The application capability
or server MUST validate all security-relevant inputs and resource identifiers
again before execution. Validation success does not imply authorization,
delegation, safe data handling, or approval.

The WebMCP specification now documents `Permissions-Policy: tools=()` as a
browser-enforced mitigation that disables WebMCP for a document and its
descendants before page script runs. Sites that do not intend to expose WebMCP
SHOULD use this control to reduce attack surface. An enabled `tools` feature is
availability only: it MUST NOT be interpreted as application authorization,
delegation, approval, or permission for every script in the document.

WebMCP pull request 289 proposed more specific registration and invocation
validation behavior but was closed without merge. An adapter MUST NOT depend
on that proposal's exact schema subset, exception type, or validation timing.

Merged pull requests 302 and 301 align registration validation order with
Chromium, repair malformed `getTools()` and navigable lookup steps, correct the
inverted `executeTool()` input type check, and run completion steps in the
required parallel context. These changes improve specification correctness but
do not replace application-side validation or create authorization from a valid
tool name, description, schema, or input object. An adapter MUST retain
compatibility tests because browser implementations may lag the evolving draft.

WebMCP issue 298 and follow-up pull request 333 propose three page-enforced
protections for tool-mediated writes: a person-owned write scope, optimistic
concurrency against unread human edits, and page-owned cancellation for
long-running writes. These protections are consistent with AWG's
least-authority, execution-time re-check, state precondition, and
cancellation-evidence requirements. The proposal remains open with a limited,
author-reported evaluation, so this mapping treats it as implementation
evidence rather than adopted WebMCP semantics. Its own stated limit also
preserves the need for Section 6: a tool-body guard does not govern a separate
DOM automation path.

## 8. Evidence and errors

Evidence should correlate WebMCP registration or invocation context with the
canonical proposal and terminal outcome without copying full page state, tool
arguments, results, cookies, or credentials.

`toolactivated` and `toolcancel` events MAY support lifecycle correlation and
operator visibility. Evidence MUST still derive the terminal status from the
governed execution and authoritative application state. Activation does not
prove success, and cancellation does not prove rollback or absence of partial
effects.

When provider provenance affects routing, policy, or reconstruction, evidence
SHOULD record the normalized tool-owning origin obtained from a trusted host
context. It MUST NOT substitute origin for the application principal or retain
broader URL paths and page state merely to establish provenance.

Where execution mode affects a policy or security decision, evidence MAY record
a minimized mode indicator such as `interactive` or `headless`. The indicator
is context only and MUST NOT be treated as proof of human presence or approval.

Policy denial, approval pending, expired approval, invalid input, application
denial, and execution failure SHOULD remain distinguishable internally. Their
WebMCP-facing representation must follow the supported upstream API while
avoiding sensitive policy disclosure.

Tool registration lifetime and in-flight execution lifetime are separate. An
adapter MUST NOT treat removal of a tool from discovery as proof that an
already dispatched invocation was cancelled or produced no side effect.
Execution cancellation must use the supported per-invocation mechanism, and its
terminal outcome must be recorded independently of registration state.

If a user agent or transport reports failure after dispatch, the adapter MUST
NOT automatically retry a non-idempotent action. It SHOULD reconcile the
application result using an idempotency key, stable operation identifier, or
authoritative application-state query. If the outcome cannot be established,
evidence SHOULD preserve that uncertainty in `result_status` or reason codes
rather than claiming failure without side effects.

A generic `UnknownError` after dispatch is not proof that the tool refused,
produced no partial effect, or failed before completing. Until WebMCP adopts a
portable outcome envelope, the adapter SHOULD preserve any trustworthy result
or refusal information its host exposes, distinguish known refusal from known
partial or completed effects internally, and keep an unresolved outcome
explicitly uncertain. It MUST NOT infer retry safety from the generic error.

A non-autosubmit declarative tool may populate form fields and then remain
waiting for a later human or application submission. Field population is a
nonterminal protocol condition: an adapter MUST NOT map it to `SUCCEEDED`,
execution failure, a human decision, or AWG `APPROVAL_PENDING`. The latter is a
governance state requiring verified authorization evidence; protocol workflow
waiting establishes none. Until upstream defines portable lifecycle signaling,
an adapter SHOULD preserve a distinct bounded waiting state when its host
exposes one, record timeout or abandonment without claiming a terminal side
effect outcome, and MUST NOT automatically retry or submit the form.

## 9. Compatibility posture

The upstream specification is evolving. This mapping depends only on the broad
tool registration and invocation boundary, not on an open pull request or a
particular browser's implementation behavior.

The upstream clarification that headless browsing scenarios are in scope is an
interoperability and threat-model input, not a new authority primitive. The same
authorization, policy, approval, validation, and evidence boundaries apply to
interactive and headless WebMCP execution.

`consequentialHint` and `debugging` may improve classification and user
experience, but they are hints rather than authorization assertions. Issue 288
is treated as evidence for a realistic design hypothesis: at least one observed
agent host could both
invoke a proposal tool and automate its page review control. It is not treated
as proof that every WebMCP implementation behaves that way or as a confirmed
vulnerability in this repository. Issue 298 is likewise tracked as a proposed
defense-in-depth pattern, not as a required or universally available WebMCP
control. Issue 300 is now closed after recording one Chrome 152 result-delivery
failure following self-unregistration; Chrome's documentation states that
Chrome 153 preserves in-flight executions. Merged pull request 311 adds a
non-normative clarification that unregistering during a callback does not
cancel the already invoked execution, while independent cancellation and
document unloading still apply. This mapping keeps registration and execution
lifetime separate and requires outcome reconciliation before consequential
retries.

The 2026-10-02 draft's observed tool collection carries the provider document's
origin. This mapping adopts origin as provenance while remaining independent of
the implementation-defined format used to expose observations to an agent.
The same draft adds activation and cancellation event interfaces, a `debugging`
annotation, and explicit Permissions Policy mitigation guidance. AWG adopts
these as observability, classification, and attack-surface controls, not as new
authority or proof of a terminal outcome.
Issue 306 was closed as a duplicate of issue 255. The primary issue's
tool-collection and progressive-disclosure design remains open and may improve
least-exposure discovery, but collections do not confer authority.
Issue 307's proposed lifecycle signals for non-autosubmit declarative tools are
also open; this mapping requires nonterminal handling without depending on the
suggested `awaiting_submission` label. Issue 308 was closed without adopting a
general outcome-preservation rule. Refusal and specific execution errors remain
separate open discussions, including issues 282 and 323, so this mapping
preserves uncertainty without depending on a specific result shape. Pull
request 289 was closed without merge. Pull request 324 merged and now specifies
that omitted or `undefined` `executeTool()` input becomes an empty object; this
does not bypass schema or application validation. Pull request 264 adds
non-normative observation context-management guidance while preserving the
complete observed tool map. Pull request 330 removes the origin-keyed
agent-cluster precondition, so an adapter MUST NOT treat `Origin-Agent-Cluster`
as a WebMCP authority or availability requirement. Pull request 333 and issue
312 remain open proposals for page-enforced write boundaries and page-context
synchronization. No portable
context-notification or context-description primitive is assumed by this
mapping.

## 10. Conformance scenarios

A WebMCP adapter should test at least:

- an application denial remains a denial despite permissive tool metadata;
- absent or false `consequentialHint` does not bypass policy classification;
- `debugging: true` does not grant developer authority, bypass approval, or
  reduce evidence requirements;
- `Permissions-Policy: tools=()` prevents WebMCP use in the protected document
  tree, while enabling the feature grants no application authority;
- same-named tools from different trusted provider origins remain distinct;
- missing, caller-asserted, or ambiguous origin cannot inherit policy,
  delegation, classification, or approval from a known provider;
- provider origin never substitutes for application authorization;
- `executeTool()` receives a serializable JavaScript object rather than a
  pre-stringified JSON argument;
- a discovered `RegisteredTool.inputSchema` remains a parsed JavaScript object
  rather than a pre-stringified schema;
- a non-object or unserializable input fails before tool dispatch;
- omitted or `undefined` input becomes an empty object but still fails when the
  action contract requires fields;
- headless execution does not bypass application authorization, governance
  policy, budgets, validation, approval, or evidence requirements;
- a headless consequential action requiring human approval remains pending or
  fails closed until independent authorization evidence is verified;
- invalid inputs fail before the application side effect;
- server-side validation catches inputs accepted or altered after browser
  validation;
- writes outside the person- or application-owned allowed scope fail before a
  side effect;
- a write that would overwrite an unread intervening change fails a state
  precondition and returns only the conflict information safe for that caller;
- cancellation records whether any partial effect landed and does not imply
  rollback;
- activation and cancellation events correlate lifecycle state without being
  accepted as terminal success, no-effect, rollback, or human approval;
- unregistering a tool after dispatch does not by itself mark its in-flight
  action cancelled or prove that no side effect occurred;
- an ambiguous failure after possible dispatch triggers result reconciliation
  and does not automatically repeat a non-idempotent action;
- a generic `UnknownError` does not erase a known refusal, partial effect, or
  completed effect and never establishes retry safety;
- populating a non-autosubmit declarative form remains nonterminal and does not
  establish execution success or a human approval decision;
- workflow waiting, timeout, and abandonment do not automatically retry or
  submit the form;
- a proposal tool creates no consequential side effect;
- an agent that can invoke a tool and automate the page cannot self-satisfy
  required human approval through page controls alone;
- altered arguments or target state invalidate the approval;
- expired or replayed approval fails closed;
- protocol errors and evidence do not disclose secrets.

## 11. References

- [WebMCP Draft Community Group Report](https://webmachinelearning.github.io/webmcp/)
- [WebMCP repository](https://github.com/webmachinelearning/webmcp)
- [Browser and Agent Implementation Status](https://github.com/webmachinelearning/webmcp/blob/main/implementation-status.md)
- [Chrome WebMCP Imperative API](https://developer.chrome.com/docs/ai/webmcp/imperative-api)
- [Pull request 251: JavaScript object input for `executeTool()`](https://github.com/webmachinelearning/webmcp/pull/251)
- [Pull request 279: clarified `executeTool()` and schema value shapes](https://github.com/webmachinelearning/webmcp/pull/279)
- [Pull request 281: origin in observed tool collections](https://github.com/webmachinelearning/webmcp/pull/281)
- [Pull request 302: registration and lookup algorithm corrections](https://github.com/webmachinelearning/webmcp/pull/302)
- [Pull request 245: tool activation and cancellation events](https://github.com/webmachinelearning/webmcp/pull/245)
- [Pull request 253: `debugging` tool annotation](https://github.com/webmachinelearning/webmcp/pull/253)
- [Pull request 275: Permissions Policy security mitigation](https://github.com/webmachinelearning/webmcp/pull/275)
- [Issue 255: tool collections and progressive disclosure](https://github.com/webmachinelearning/webmcp/issues/255)
- [Issue 306: closed duplicate of issue 255](https://github.com/webmachinelearning/webmcp/issues/306)
- [Issue 288: page-side approval and agent-controlled UI](https://github.com/webmachinelearning/webmcp/issues/288)
- [Issue 298: proposed page-enforced write boundaries](https://github.com/webmachinelearning/webmcp/issues/298)
- [Issue 300: unregistration and in-flight execution](https://github.com/webmachinelearning/webmcp/issues/300)
- [Issue 307: non-autosubmit declarative-tool lifecycle](https://github.com/webmachinelearning/webmcp/issues/307)
- [Issue 282: proposed application-level refusal errors](https://github.com/webmachinelearning/webmcp/issues/282)
- [Issue 308: closed proposal to preserve tool outcomes](https://github.com/webmachinelearning/webmcp/issues/308)
- [Issue 323: proposed specific execution errors](https://github.com/webmachinelearning/webmcp/issues/323)
- [Pull request 289: closed schema-validation proposal](https://github.com/webmachinelearning/webmcp/pull/289)
- [Pull request 301: `executeTool()` and navigable algorithm corrections](https://github.com/webmachinelearning/webmcp/pull/301)
- [Pull request 311: unregistration clarification](https://github.com/webmachinelearning/webmcp/pull/311)
- [Pull request 324: omitted input becomes an empty object](https://github.com/webmachinelearning/webmcp/pull/324)
- [Pull request 264: observation context-management clarification](https://github.com/webmachinelearning/webmcp/pull/264)
- [Pull request 330: removed origin-keyed agent-cluster requirement](https://github.com/webmachinelearning/webmcp/pull/330)
- [Pull request 333: proposed page-enforced write boundaries](https://github.com/webmachinelearning/webmcp/pull/333)
- [Issue 312: proposed page-context synchronization](https://github.com/webmachinelearning/webmcp/issues/312)
- [Pull request 296: headless browsing scenarios explicitly in scope](https://github.com/webmachinelearning/webmcp/pull/296)
- [Pull request 217: `consequentialHint`](https://github.com/webmachinelearning/webmcp/pull/217)
