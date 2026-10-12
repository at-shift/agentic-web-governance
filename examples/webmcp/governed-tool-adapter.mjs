// SPDX-License-Identifier: GPL-2.0-or-later

const TOOL_NAME_PATTERN = /^[A-Za-z0-9_.-]{1,128}$/;

export class WebMcpAdapterError extends Error {
  constructor(code, message) {
    super(message);
    this.name = "WebMcpAdapterError";
    this.code = code;
  }
}

function requireFunction(name, value) {
  if (typeof value !== "function") {
    throw new TypeError(`${name} must be a function`);
  }
  return value;
}

function documentOrigin(documentObject) {
  const candidate = documentObject?.location?.href ?? documentObject?.baseURI;
  let origin;
  try {
    origin = new URL(candidate).origin;
  } catch {
    origin = undefined;
  }
  if (!origin || origin === "null") {
    throw new WebMcpAdapterError(
      "WEBMCP_ORIGIN_UNAVAILABLE",
      "WebMCP tools require a non-opaque document origin",
    );
  }
  return origin;
}

function reasonCodes(outcome, fallback) {
  const values = Array.isArray(outcome?.reason_codes)
    ? outcome.reason_codes
    : [outcome?.reason_code];
  const normalized = values.filter(
    (value) => typeof value === "string" && value.length > 0,
  );
  return normalized.length > 0 ? [...new Set(normalized)] : [fallback];
}

function applicationOutcome(status, outcome, extra = {}) {
  return {
    awg_version: 1,
    status,
    proposal_id:
      typeof outcome?.proposal_id === "string"
        ? outcome.proposal_id
        : undefined,
    reason_codes: reasonCodes(outcome, "OUTCOME_UNSPECIFIED"),
    ...extra,
  };
}

function abortReason(signal) {
  if (signal?.reason !== undefined) {
    return signal.reason;
  }
  if (typeof DOMException === "function") {
    return new DOMException("The WebMCP invocation was aborted", "AbortError");
  }
  const error = new Error("The WebMCP invocation was aborted");
  error.name = "AbortError";
  return error;
}

function throwIfAborted(signal) {
  if (signal?.aborted) {
    throw abortReason(signal);
  }
}

function cloneInput(inputObject) {
  if (
    inputObject === null ||
    typeof inputObject !== "object" ||
    Array.isArray(inputObject)
  ) {
    return undefined;
  }
  try {
    return structuredClone(inputObject);
  } catch {
    return undefined;
  }
}

function normalizeAnnotations(annotations = {}) {
  return {
    readOnlyHint: annotations.readOnlyHint === true,
    untrustedContentHint: annotations.untrustedContentHint === true,
    consequentialHint: annotations.consequentialHint === true,
    debugging: annotations.debugging === true,
  };
}

export async function registerGovernedWebMcpTool({
  document: documentObject = globalThis.document,
  kernel,
  name,
  title,
  description,
  inputSchema = { type: "object", additionalProperties: false },
  annotations,
  validateInput = () => true,
  buildAction,
  registrationSignal,
}) {
  const modelContext = documentObject?.modelContext;
  if (!modelContext || typeof modelContext.registerTool !== "function") {
    throw new WebMcpAdapterError(
      "WEBMCP_UNAVAILABLE",
      "document.modelContext.registerTool is unavailable",
    );
  }
  if (
    !kernel ||
    typeof kernel.propose !== "function" ||
    typeof kernel.execute !== "function"
  ) {
    throw new TypeError("kernel must expose propose() and execute()");
  }
  if (!TOOL_NAME_PATTERN.test(name ?? "")) {
    throw new TypeError("name must satisfy the current WebMCP tool-name grammar");
  }
  if (typeof description !== "string" || description.length === 0) {
    throw new TypeError("description must be a non-empty string");
  }
  requireFunction("validateInput", validateInput);
  requireFunction("buildAction", buildAction);

  const ownerOrigin = documentOrigin(documentObject);
  const registrationController = new AbortController();
  if (registrationSignal?.aborted) {
    registrationController.abort(abortReason(registrationSignal));
  } else if (registrationSignal) {
    registrationSignal.addEventListener(
      "abort",
      () => registrationController.abort(abortReason(registrationSignal)),
      { once: true },
    );
  }

  const tool = {
    name,
    ...(typeof title === "string" && title.length > 0 ? { title } : {}),
    description,
    inputSchema: structuredClone(inputSchema),
    annotations: normalizeAnnotations(annotations),
    execute: async (inputObject = {}, options = {}) => {
      const invocationSignal = options.signal;
      throwIfAborted(invocationSignal);

      const input = cloneInput(inputObject);
      if (!input) {
        return applicationOutcome("denied", {
          reason_codes: ["INPUT_INVALID"],
        });
      }

      const context = Object.freeze({
        protocol: "webmcp",
        owner_origin: ownerOrigin,
        tool_name: name,
      });
      const validation = await validateInput(structuredClone(input), context);
      if (validation !== true) {
        return applicationOutcome("denied", {
          reason_codes: Array.isArray(validation)
            ? validation
            : ["INPUT_INVALID"],
        });
      }

      // Origin is derived from the owning Document and is never accepted from
      // tool input. This prevents an agent from choosing its own provenance.
      const action = await buildAction({
        input: structuredClone(input),
        context,
        signal: invocationSignal,
      });
      throwIfAborted(invocationSignal);

      const proposal = await kernel.propose(action);
      if (proposal?.status === "denied") {
        return applicationOutcome("denied", proposal);
      }
      if (proposal?.status === "pending_approval") {
        // Protocol completion is not human approval. Return a durable
        // application outcome instead of rejecting and being flattened into
        // WebMCP's platform-level OperationError.
        return applicationOutcome("approval_required", proposal);
      }
      if (proposal?.status !== "ready") {
        return applicationOutcome("outcome_uncertain", proposal);
      }

      throwIfAborted(invocationSignal);
      // After execute() is dispatched, cancellation does not prove that no
      // effect occurred. Let the kernel finish recording its authoritative
      // result; the user agent may independently discard this callback result.
      const execution = await kernel.execute(proposal.proposal_id);
      if (execution?.status === "executed") {
        return applicationOutcome("succeeded", execution, {
          result: execution.result,
        });
      }
      if (execution?.status === "denied") {
        return applicationOutcome("denied", execution);
      }
      return applicationOutcome("outcome_uncertain", execution);
    },
  };

  // Omitting exposedTo deliberately keeps the reference adapter same-origin.
  // Cross-origin exposure needs a separate, explicit application policy.
  await modelContext.registerTool(tool, {
    signal: registrationController.signal,
  });

  return Object.freeze({
    name,
    ownerOrigin,
    unregister(reason = "WebMCP tool unregistered") {
      registrationController.abort(reason);
    },
  });
}
