// SPDX-License-Identifier: GPL-2.0-or-later

import assert from "node:assert/strict";

import {
  WebMcpAdapterError,
  registerGovernedWebMcpTool,
} from "../examples/webmcp/governed-tool-adapter.mjs";
import {
  registerSiteSummaryTool,
  siteSummaryAction,
} from "../examples/webmcp/site-summary-tool.mjs";

function fakeDocument() {
  const registrations = new Map();
  return {
    location: { href: "https://example.test/dashboard?agent=1" },
    registrations,
    modelContext: {
      async registerTool(tool, options = {}) {
        registrations.set(tool.name, tool);
        options.signal?.addEventListener(
          "abort",
          () => registrations.delete(tool.name),
          { once: true },
        );
      },
    },
  };
}

function kernelWith({ proposal, execution }) {
  const calls = { proposed: [], executed: [] };
  return {
    calls,
    async propose(action) {
      calls.proposed.push(structuredClone(action));
      return structuredClone(proposal);
    },
    async execute(proposalId) {
      calls.executed.push(proposalId);
      return structuredClone(execution);
    },
  };
}

async function register(documentObject, kernel, overrides = {}) {
  return registerGovernedWebMcpTool({
    document: documentObject,
    kernel,
    name: "reference_tool",
    description: "Exercises the WebMCP governance boundary.",
    annotations: { readOnlyHint: true },
    validateInput: () => true,
    buildAction: ({ input, context }) => ({ input, context }),
    ...overrides,
  });
}

let passed = 0;

await assert.rejects(
  () =>
    register(
      { location: { href: "https://example.test/" } },
      kernelWith({}),
    ),
  (error) =>
    error instanceof WebMcpAdapterError && error.code === "WEBMCP_UNAVAILABLE",
);
passed += 1;

// Registration failures belong to the real host. The adapter must propagate
// them, never fall back to a parallel registry that bypasses the browser gate.
for (const name of ["InvalidStateError", "NotAllowedError"]) {
  const documentObject = fakeDocument();
  const kernel = kernelWith({});
  const failure = new DOMException("Host rejected registration", name);
  documentObject.modelContext.registerTool = async () => {
    throw failure;
  };
  await assert.rejects(
    () => register(documentObject, kernel),
    (error) => error === failure,
  );
  assert.equal(documentObject.registrations.size, 0);
  assert.deepEqual(kernel.calls.proposed, []);
  assert.deepEqual(kernel.calls.executed, []);
  passed += 1;
}

{
  const documentObject = fakeDocument();
  const controller = new AbortController();
  let startProposal;
  let finishProposal;
  const proposing = new Promise((resolve) => {
    startProposal = resolve;
  });
  const resumeProposal = new Promise((resolve) => {
    finishProposal = resolve;
  });
  let executionCount = 0;
  const kernel = {
    async propose() {
      startProposal();
      await resumeProposal;
      return { status: "ready", proposal_id: "cancelled-before-dispatch" };
    },
    async execute() {
      executionCount += 1;
    },
  };
  await register(documentObject, kernel);
  const invocation = documentObject.registrations
    .get("reference_tool")
    .execute({}, { signal: controller.signal });
  await proposing;
  controller.abort(
    new DOMException("Cancelled during policy evaluation", "AbortError"),
  );
  finishProposal();
  await assert.rejects(invocation, (error) => error.name === "AbortError");
  assert.equal(executionCount, 0);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({
    proposal: {
      status: "ready",
      proposal_id: "proposal-1",
      reason_codes: ["READ_ALLOWED"],
    },
    execution: {
      status: "executed",
      proposal_id: "proposal-1",
      reason_codes: ["EXECUTE_ALLOWED"],
      result: { name: "Example" },
    },
  });
  const registration = await register(documentObject, kernel);
  const tool = documentObject.registrations.get("reference_tool");
  assert.equal(registration.ownerOrigin, "https://example.test");
  assert.equal(tool.annotations.readOnlyHint, true);
  assert.equal(tool.annotations.consequentialHint, false);

  const result = await tool.execute({ origin: "https://attacker.test" });
  assert.equal(result.status, "succeeded");
  assert.deepEqual(result.result, { name: "Example" });
  assert.equal(
    kernel.calls.proposed[0].context.owner_origin,
    "https://example.test",
  );
  registration.unregister();
  assert.equal(documentObject.registrations.has("reference_tool"), false);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const controller = new AbortController();
  let markExecutionStarted;
  let finishExecution;
  const executionStarted = new Promise((resolve) => {
    markExecutionStarted = resolve;
  });
  const executionMayFinish = new Promise((resolve) => {
    finishExecution = resolve;
  });
  const kernel = {
    async propose() {
      return {
        status: "ready",
        proposal_id: "proposal-dispatched",
        reason_codes: ["READ_ALLOWED"],
      };
    },
    async execute() {
      markExecutionStarted();
      await executionMayFinish;
      return {
        status: "executed",
        proposal_id: "proposal-dispatched",
        reason_codes: ["EXECUTE_ALLOWED"],
        result: { completed: true },
      };
    },
  };
  await register(documentObject, kernel);
  const invocation = documentObject.registrations
    .get("reference_tool")
    .execute({}, { signal: controller.signal });
  await executionStarted;
  controller.abort(new DOMException("cancelled", "AbortError"));
  finishExecution();
  const result = await invocation;
  assert.equal(result.status, "succeeded");
  assert.deepEqual(result.result, { completed: true });
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({
    proposal: {
      status: "denied",
      proposal_id: "proposal-2",
      reason_codes: ["POLICY_DENIED"],
    },
  });
  await register(documentObject, kernel);
  const result = await documentObject.registrations
    .get("reference_tool")
    .execute({});
  assert.equal(result.status, "denied");
  assert.deepEqual(result.reason_codes, ["POLICY_DENIED"]);
  assert.deepEqual(kernel.calls.executed, []);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({
    proposal: {
      status: "pending_approval",
      proposal_id: "proposal-3",
      reason_codes: ["WRITE_REQUIRES_REVIEW"],
    },
  });
  await register(documentObject, kernel);
  const result = await documentObject.registrations
    .get("reference_tool")
    .execute({});
  assert.equal(result.status, "approval_required");
  assert.deepEqual(kernel.calls.executed, []);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({});
  await register(documentObject, kernel, {
    validateInput: () => ["INPUT_REJECTED_BY_APPLICATION"],
  });
  const result = await documentObject.registrations
    .get("reference_tool")
    .execute({});
  assert.equal(result.status, "denied");
  assert.deepEqual(result.reason_codes, ["INPUT_REJECTED_BY_APPLICATION"]);
  assert.deepEqual(kernel.calls.proposed, []);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({
    proposal: {
      status: "ready",
      proposal_id: "proposal-4",
      reason_codes: ["READ_ALLOWED"],
    },
    execution: {
      status: "failed",
      proposal_id: "proposal-4",
      reason_codes: ["EXECUTION_FAILED"],
    },
  });
  await register(documentObject, kernel);
  const result = await documentObject.registrations
    .get("reference_tool")
    .execute({});
  assert.equal(result.status, "outcome_uncertain");
  assert.deepEqual(result.reason_codes, ["EXECUTION_FAILED"]);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({
    proposal: {
      status: "ready",
      proposal_id: "proposal-5",
      reason_codes: ["READ_ALLOWED"],
    },
    execution: {
      status: "executed",
      proposal_id: "proposal-5",
      reason_codes: ["EXECUTE_ALLOWED"],
      result: {},
    },
  });
  await register(documentObject, kernel);
  const controller = new AbortController();
  controller.abort(new DOMException("cancelled", "AbortError"));
  await assert.rejects(
    () =>
      documentObject.registrations
        .get("reference_tool")
        .execute({}, { signal: controller.signal }),
    (error) => error?.name === "AbortError",
  );
  assert.deepEqual(kernel.calls.proposed, []);
}
passed += 1;

{
  const documentObject = fakeDocument();
  const kernel = kernelWith({
    proposal: {
      status: "ready",
      proposal_id: "proposal-6",
      reason_codes: ["READ_ALLOWED"],
    },
    execution: {
      status: "executed",
      proposal_id: "proposal-6",
      reason_codes: ["EXECUTE_ALLOWED"],
      result: { name: "Example" },
    },
  });
  await registerSiteSummaryTool({
    document: documentObject,
    kernel,
    applicationPrincipal: { type: "application-user", id: "user-42" },
    applicationInstanceId: "example.test",
  });
  const result = await documentObject.registrations
    .get("agentic_web_governance_site_summary")
    .execute({ fields: ["name", "url"] });
  assert.equal(result.status, "succeeded");
  assert.deepEqual(kernel.calls.proposed[0],
    siteSummaryAction({
      input: { fields: ["name", "url"] },
      context: {
        protocol: "webmcp",
        owner_origin: "https://example.test",
        tool_name: "agentic_web_governance_site_summary",
      },
      applicationPrincipal: { type: "application-user", id: "user-42" },
      applicationInstanceId: "example.test",
    }),
  );
}
passed += 1;

console.log(`WebMCP adapter JavaScript: ${passed} passed`);
