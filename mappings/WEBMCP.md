# WebMCP Mapping

**Mapping version:** Draft 0.1  
**Upstream snapshot:** 2026-09-04 Draft Community Group Report, repository reviewed 2026-09-05  
**Last reviewed:** 2026-09-05

## 1. Purpose

This mapping applies the Agentic Web Governance core to WebMCP, the browser API
through which a web application exposes tools to user agents and agents. It
does not define a WebMCP implementation or replace the evolving upstream API.

WebMCP and the network-oriented Model Context Protocol have related tool
concepts but distinct trust and execution boundaries. This mapping therefore
does not overload [`MCP.md`](MCP.md).

## 2. Upstream boundary

The current upstream Draft Community Group Report includes a browser API for
registering and invoking tools and a `consequentialHint` annotation. These
contracts remain owned by WebMCP.

The upstream repository now explicitly treats headless browsing scenarios as
in scope where client-side WebMCP tools are reused for task completion,
including transitions between human-in-the-loop and headless experiences. The
same clarification distinguishes WebMCP from purely server-side task completion
and from backend-focused protocols such as MCP.

Upstream annotations, descriptions, and execution-mode descriptions are
discovery and interoperability inputs. They are not authoritative declarations
of application permission, risk, approval, or human presence. A WebMCP
implementation may use them to inform presentation or routing, but governance
must derive the effective decision from application authority and policy.

## 3. Actors and authority

The adapter must distinguish:

```text
human user or reviewer
logical agent
agent host, browser, or user agent
web page and tool owner
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
| Browser or agent identity | Client or agent context | Do not substitute it for the application principal |
| Visible or headless execution mode | Execution context | Does not establish human presence, approval, or additional authority |
| Tool result or error | Protocol result mapping | Do not leak policy, credentials, or sensitive application internals |

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

As of this mapping's upstream snapshot, WebMCP pull request 289 proposes more
specific registration and invocation validation behavior but remains open. An
adapter MUST NOT depend on that proposal's exact schema subset, exception type,
or validation timing until those semantics are adopted upstream and reviewed.

## 8. Evidence and errors

Evidence should correlate WebMCP registration or invocation context with the
canonical proposal and terminal outcome without copying full page state, tool
arguments, results, cookies, or credentials.

Where execution mode affects a policy or security decision, evidence MAY record
a minimized mode indicator such as `interactive` or `headless`. The indicator
is context only and MUST NOT be treated as proof of human presence or approval.

Policy denial, approval pending, expired approval, invalid input, application
denial, and execution failure SHOULD remain distinguishable internally. Their
WebMCP-facing representation must follow the supported upstream API while
avoiding sensitive policy disclosure.

## 9. Compatibility posture

The upstream specification is evolving. This mapping depends only on the broad
tool registration and invocation boundary, not on an open pull request or a
particular browser's implementation behavior.

The upstream clarification that headless browsing scenarios are in scope is an
interoperability and threat-model input, not a new authority primitive. The same
authorization, policy, approval, validation, and evidence boundaries apply to
interactive and headless WebMCP execution.

`consequentialHint` may improve classification and user experience, but it is a
hint rather than an authorization assertion. Issue 288 is treated as evidence
for a realistic design hypothesis: at least one observed agent host could both
invoke a proposal tool and automate its page review control. It is not treated
as proof that every WebMCP implementation behaves that way or as a confirmed
vulnerability in this repository.

## 10. Conformance scenarios

A WebMCP adapter should test at least:

- an application denial remains a denial despite permissive tool metadata;
- absent or false `consequentialHint` does not bypass policy classification;
- headless execution does not bypass application authorization, governance
  policy, budgets, validation, approval, or evidence requirements;
- a headless consequential action requiring human approval remains pending or
  fails closed until independent authorization evidence is verified;
- invalid inputs fail before the application side effect;
- server-side validation catches inputs accepted or altered after browser
  validation;
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
- [Issue 288: page-side approval and agent-controlled UI](https://github.com/webmachinelearning/webmcp/issues/288)
- [Pull request 289: proposed schema validation](https://github.com/webmachinelearning/webmcp/pull/289)
- [Pull request 296: headless browsing scenarios explicitly in scope](https://github.com/webmachinelearning/webmcp/pull/296)
- [Pull request 217: `consequentialHint`](https://github.com/webmachinelearning/webmcp/pull/217)
