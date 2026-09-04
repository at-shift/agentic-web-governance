# WordPress Mapping

**Mapping version:** Draft 0.1  
**Target baseline:** WordPress 7.1+

**Last reviewed:** 2026-09-04

## 1. Purpose

This mapping applies the Agentic Web Governance core to WordPress. WordPress is
the first reference platform, not a limit on the core specification.
WordPress-specific users, Abilities, hooks, storage, and MCP integration remain
inside this mapping and its adapter. They are not required core primitives.
The portability boundary and proof rule are defined in
[`RFC 0002`](../rfcs/0002-platform-adapter-boundary.md).

## 2. Upstream boundary

The reference implementation must treat the following as upstream:

```text
WordPress Core
  - Users, roles, and capabilities
  - Abilities API
  - REST API

WordPress MCP Adapter
  - Ability-to-MCP exposure
  - MCP tools, resources, and prompts
  - HTTP and STDIO transports
  - server and permission integration
  - protocol observability

Automattic Agents API, when available
  - execution-principal and agent contracts
  - access-grant and authorization contracts
  - action/tool policy contracts
  - pending-action contracts
  - workflow and iteration-budget substrate

Future WordPress agent identity work
  - possible Agent User or actor model
```

The project owns product-specific policy, durable materialization where
upstream does not, administration, approval routing, data handling, and
evidence.

## 3. Core mapping

| Core concept | WordPress mapping | Rule |
|---|---|---|
| Application principal | Authenticated `WP_User` or explicitly public context | Never substitute agent identity for the user |
| Capability | WordPress Ability | Do not create a second business-capability registry |
| Application authorization | Ability `permission_callback` and WordPress capability checks | Mandatory and authoritative |
| Input/output contract | Ability JSON Schema | Preserve WordPress validation |
| Native semantics | Ability annotations and metadata | Reuse before adding project metadata |
| Governance metadata | Additional namespaced Ability/site policy metadata | Add only missing governance concepts |
| Agent/client context | Validated adapter claims or Agents API execution principal | Preserve unknown status; omit unknown identity from the canonical action |
| Delegation | Subset of current WordPress user authority | Cannot add WordPress capabilities |
| Approval | Agents API pending action when compatible, plus product storage/UI and an independent human-authorization adapter | Bind to exact action, verify decision provenance when required, and re-check permission |
| Evidence | Dedicated operational event store or adapter | Do not store secrets or use posts as a high-volume log |
| MCP exposure | Official WordPress MCP Adapter | Do not ship a competing generic MCP server |
| A2A exposure | Future adapter | Not required for Draft 0.1 |

## 4. Execution path

```text
External agent/client
        |
        v
Supported adapter (official MCP Adapter, REST integration, future A2A)
        |
        v
Normalize execution context
        |
        v
WordPress Ability permission check
        |
        v
Governance policy, delegation, budget, approval
        |
        v
Execution-time permission and policy re-check
        |
        v
WP_Ability::execute()
        |
        v
Evidence event
```

No external path may bypass either WordPress permission or governance.

### 4.1 Supported interception points

The reference implementation requires WordPress 7.1+ and uses
`wp_ability_permission_result`. The filter runs after the registered
`permission_callback` and applies to `check_permissions()` and `execute()`.

Both paths preserve every non-literal-`true` WordPress result, including
`false` and `WP_Error`. Governance can therefore narrow an allow but cannot
convert an application denial into an allow. The reference gate uses maximum
filter priority so its restriction follows ordinary extension filters. As with
WordPress capability checks generally, deliberately hostile PHP running as an
installed plugin is trusted application code and is outside this adapter's
enforcement boundary.

`wp_before_execute_ability` and `wp_after_execute_ability` are observation
actions, not authorization points. `wp_pre_execute_ability` is also not the
primary governance allow path because it runs before normalization, validation,
and permission and can bypass the remainder of execution.

WordPress 6.9 and 7.0 are intentionally unsupported. They have the Abilities
registry but lack the post-permission filter, so supporting them would add a
registration-time callback wrapper and a second security-sensitive execution
path. Compatibility can be reconsidered as a separate adapter if demonstrated
deployment demand outweighs that cost.

The official MCP Adapter 0.6.1 delegates Ability-backed tools to
`WP_Ability::check_permissions()` and `WP_Ability::execute()`. The same
governance boundary therefore covers its permission probe and execution without
an MCP-specific fork. Because that adapter performs both calls, an allow may be
memoized within one PHP request and cleared at `wp_before_execute_ability` so a
single external invocation does not reserve its rate budget twice.

## 5. Ability metadata

Use WordPress-native fields for:

- namespaced identity;
- label and description;
- category;
- input and output schema;
- execute callback;
- permission callback;
- REST/client exposure;
- native annotations provided by the platform.

Additional governance metadata may include:

```json
{
  "agentic_governance": {
    "state": "conditional",
    "risk": "write",
    "approval": "required",
    "data_classes": ["INTERNAL"],
    "external_transmission": "deny"
  }
}
```

This shape is illustrative. A final public API requires an RFC and collision
review with upstream metadata conventions.

## 6. Permission invariant

For every invocation:

```text
WordPress permission = false -> DENY
WordPress permission = true  -> continue governance evaluation
```

Governance never reverses the first result. Permission must be checked again
after approval and before the side effect because user roles, object ownership,
site state, and policy may have changed.

## 7. Identity mapping

The implementation must distinguish:

```text
WordPress user
OAuth or application client
logical agent
runtime or execution principal
delegation grant
```

Supported sources may include:

- borrowed human identity;
- dedicated WordPress user;
- Agents API execution principal;
- a future WordPress Agent User;
- validated external client and agent claims.

The implementation should expose an adapter interface rather than freeze one
permanent agent table in Draft 0.1.

## 8. Approval mapping

When Agents API is available and compatible, use its generic pending-action
contract. The WordPress implementation remains responsible for:

- persistence selected for the product;
- REST/admin surfaces;
- approver authorization;
- verification of human decision provenance when policy requires it;
- request preview or diff;
- policy and delegation re-check;
- WordPress permission re-check;
- applying or rejecting the action;
- terminal evidence.

No action should be materialized merely because a pending-action object exists.

A logged-in WordPress session, `current_user_can()` result, REST
`permission_callback`, and WordPress nonce remain required where applicable for
identity, authorization, and request integrity. None of them proves by itself
that a human made the approval decision. A DOM click is also insufficient when
the requesting agent or its host can automate that page.

Stage 3 MUST therefore provide a human-authorization adapter outside the
requesting agent's delegated automation authority. Candidate adapters include a
trusted host confirmation surface, an out-of-band reviewer, an organizational
approval service, or a WebAuthn/passkey ceremony that displays and
cryptographically binds the exact proposal. WebAuthn is one possible adapter,
not a core requirement. Verified evidence must bind the request hash,
authorized WordPress approver, assurance method, and expiry.

## 9. Storage guidance

Use WordPress Options API for small site configuration. Consider dedicated
tables only for implemented, high-volume operational records such as:

- policy versions;
- delegations;
- pending actions and approvals;
- evidence events.

Use cache-backed counters where appropriate for rate limiting. Avoid large
autoloaded options and avoid storing high-volume evidence as posts.

Multisite scope remains unresolved.

## 10. Reference implementation stages

### Stage 1: Read-only governance

- require WordPress 7.1+;
- inventory Abilities;
- inspect native metadata;
- explicitly enable a low-risk read Ability;
- deny through governance without widening permission;
- apply rate limits;
- record evidence;
- integrate through supported official MCP Adapter mechanisms.

Exit condition:

> A read-only Ability invoked through the official MCP Adapter is governed and
> evidenced while WordPress remains the final permission authority.

The experimental plugin in
[`examples/wordpress/plugin/`](../examples/wordpress/plugin/) now implements the
Stage 1 control path and isolated boundary tests. On 2026-08-26, the deployed
HTTPS acceptance verifier completed with `ACCEPTED` against WordPress 7.1 and
the official MCP Adapter 0.6.1. It proved authenticated principal binding,
MCP-only discovery, governed read-only execution, evidence-producing policy
decisions, and fail-closed rate-limit denial. The Stage 1 exit condition is
therefore satisfied for this reference path; this does not claim production
readiness or full specification conformance.

### Stage 2: Scoped write delegation

- normalize agent/client identity;
- restrict delegation by Ability and resource;
- add expiry and invocation budgets;
- prove that delegation cannot exceed current WordPress authority.

### Stage 3: Approval and data transmission

- use compatible Agents API pending-action contracts;
- provide durable review and exact-request approval;
- verify required human decisions through an independent authorization adapter;
- re-check permission before execution;
- apply external-transmission policy;
- export evidence.

## 11. WordPress-specific threats

The reference implementation must test:

- bypass through an ungoverned REST, MCP, CLI, or direct Ability path;
- permission widening after governance allow;
- object-level authorization mistakes;
- CSRF on administrative and approval actions;
- agent self-approval through a browser or DOM surface available to the same
  agent host;
- capability metadata disclosure;
- approval replay and argument changes;
- unsafe outbound HTTP and SSRF;
- secret or personal-data leakage in logs;
- unbounded requests and evidence growth;
- plugin or upstream-version incompatibility.

See [../THREAT-MODEL.md](../THREAT-MODEL.md).

## 12. Upstream decision rule

Before implementing a primitive:

1. Check WordPress Core and the Abilities API.
2. Check WordPress AI work.
3. Check the official MCP Adapter.
4. Check Automattic Agents API.
5. Check the relevant protocol specification.
6. Implement only the missing product-specific governance layer.
