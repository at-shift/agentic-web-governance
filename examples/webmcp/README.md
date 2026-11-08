# WebMCP Reference Adapter

**Status:** Experimental, read-only reference implementation  
**Upstream:** WebMCP Draft Community Group Report, 2 November 2026  
**API:** Imperative `document.modelContext.registerTool()`

This directory demonstrates a narrow WebMCP protocol adapter around the AWG
governance kernel. It is not a browser polyfill and does not claim that every
browser implements WebMCP.

The November 8 review retains the current `RegisteredTool` contract. Upstream
PR 348's UUID-backed `Tool` interface is still a proposal. The host owns
detached-document, duplicate-registration, and Permissions Policy checks;
the adapter propagates registration errors. Contract tests cover that behavior
and cancellation during proposal evaluation, without claiming native-browser
interoperability or reimplementing host algorithms.

Merged PR 254 adds optional `outputSchema` to registration and discovery.
Both registration helpers accept a plain, serializable schema object, clone it,
and pass it to the host only when supplied. Omitting it preserves the existing
registration path; the site-summary example does not invent a default schema.
The schema must describe the complete AWG application outcome, including
`denied`, `approval_required`, `outcome_uncertain`, and `succeeded`, not just
the nested business `result`. Declaring a success-only schema cannot bypass
governance; it may instead cause a consumer to reject a legitimate refusal.

This option is a declaration, not a new output-validation engine, permission,
approval, or terminal-outcome guarantee. The host owns schema serialization and
registration rejection. Contract tests cover cloned declarations, unchanged
governance outcomes, omission, and host rejection. Native-browser discovery,
result validation, and serialization remain implementation/compatibility
validation candidates; issue 361 is still unresolved.

The adapter deliberately:

- feature-detects `document.modelContext.registerTool()`;
- derives origin from the owning `Document`, never from agent input;
- keeps the example same-origin by omitting `exposedTo`;
- passes a JavaScript object directly to the current imperative API;
- returns expected policy denials and approval requirements as structured
  application outcomes instead of rejected promises;
- treats cancellation after execution dispatch as outcome-ambiguous until the
  governance kernel records the authoritative result; and
- uses a registration `AbortSignal` to remove the tool without claiming that an
  in-flight execution was cancelled.

## Integration

Create a configured governance kernel whose application ports re-check the
current authenticated principal, policy, budget, preconditions, and target
state. The kernel's `executeAction` port must invoke the existing application
operation rather than duplicate it.

```js
import { registerSiteSummaryTool } from "./site-summary-tool.mjs";

const registration = await registerSiteSummaryTool({
  document,
  kernel,
  applicationPrincipal: { type: "application-user", id: "user-42" },
  applicationInstanceId: "example.test",
});

// Remove discovery for future calls. This does not cancel an execution that
// has already entered the tool callback.
registration.unregister();
```

`applicationPrincipal` must be supplied from the web application's trusted
session. Agent-provided identity metadata is not an authenticated application
principal.

The example exposes one read-only `site/summary:read` action. Adding write tools
requires durable proposal storage, an independent human-authorization adapter,
idempotency or replay protection, execution-time reconstruction, and minimized
evidence. A WebMCP annotation such as `consequentialHint` is classification
metadata and does not replace those controls.

Run the adapter contract tests with:

```sh
npm run test:webmcp
```
