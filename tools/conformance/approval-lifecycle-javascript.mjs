#!/usr/bin/env node

import { timingSafeEqual } from "node:crypto";
import { readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import Ajv2020 from "ajv/dist/2020.js";

import {
  applyChanges,
  createDigest,
  loadCaseAction,
  parseJson,
} from "./javascript.mjs";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const lifecycleDir = resolve(root, "fixtures/approval-lifecycle-v1");

function isNonEmptyString(value) {
  return typeof value === "string" && value.length > 0;
}

function hashesEqual(left, right) {
  const pattern = /^sha256:[0-9a-f]{64}$/;
  if (!pattern.test(left) || !pattern.test(right)) {
    return false;
  }
  return timingSafeEqual(Buffer.from(left, "ascii"), Buffer.from(right, "ascii"));
}

function outcome(state, status, reasonCode, reconstruction) {
  return {
    status,
    reason_code: reasonCode,
    reconstruction,
    replay_state: state.replayState,
    side_effect_count: state.sideEffectCount,
  };
}

function approvalBindingIsValid(context, runtime) {
  const { proposal, approval, execution } = context;
  if (
    !proposal ||
    !approval ||
    !execution ||
    !isNonEmptyString(proposal.id) ||
    !isNonEmptyString(proposal.action_case) ||
    !isNonEmptyString(proposal.request_hash) ||
    !isNonEmptyString(approval.id) ||
    !isNonEmptyString(approval.proposal_id) ||
    !isNonEmptyString(approval.request_hash) ||
    !isNonEmptyString(approval.approver?.type) ||
    !isNonEmptyString(approval.approver?.id) ||
    !["approved", "denied"].includes(approval.decision) ||
    !Number.isSafeInteger(approval.decided_at) ||
    !Number.isSafeInteger(approval.expires_at) ||
    approval.decided_at >= approval.expires_at ||
    typeof approval.single_use !== "boolean" ||
    !["unused", "used"].includes(approval.replay_state) ||
    !Number.isSafeInteger(execution.now) ||
    approval.proposal_id !== proposal.id ||
    !hashesEqual(approval.request_hash, proposal.request_hash)
  ) {
    return false;
  }

  const proposedAction = runtime.actions.get(proposal.action_case);
  if (!proposedAction) {
    return false;
  }

  try {
    const proposed = createDigest(
      proposedAction,
      runtime.domainPrefix,
      runtime.validate,
    );
    return hashesEqual(proposed.requestHash, proposal.request_hash);
  } catch {
    return false;
  }
}

function evaluateAttempt(context, state, runtime) {
  if (!approvalBindingIsValid(context, runtime)) {
    return outcome(
      state,
      "denied",
      "APPROVAL_BINDING_INVALID",
      "not_attempted",
    );
  }

  const { approval, execution } = context;
  if (approval.decision !== "approved" || execution.now < approval.decided_at) {
    return outcome(
      state,
      "denied",
      "APPROVAL_NOT_APPROVED",
      "not_attempted",
    );
  }
  if (execution.now >= approval.expires_at) {
    return outcome(
      state,
      "denied",
      "APPROVAL_EXPIRED",
      "not_attempted",
    );
  }
  if (approval.single_use && state.replayState === "used") {
    return outcome(
      state,
      "denied",
      "APPROVAL_REPLAYED",
      "not_attempted",
    );
  }

  const reconstruction = execution.reconstruction;
  const currentAction =
    reconstruction?.status === "available"
      ? runtime.actions.get(reconstruction.action_case)
      : undefined;
  if (!currentAction) {
    return outcome(
      state,
      "denied",
      "RECONSTRUCTION_FAILED",
      "failed",
    );
  }

  let current;
  try {
    current = createDigest(currentAction, runtime.domainPrefix, runtime.validate);
  } catch {
    return outcome(
      state,
      "denied",
      "RECONSTRUCTION_FAILED",
      "failed",
    );
  }
  if (!hashesEqual(current.requestHash, approval.request_hash)) {
    return outcome(
      state,
      "denied",
      "ACTION_HASH_MISMATCH",
      "mismatched",
    );
  }

  const checks = [
    ["application_permission", "APPLICATION_PERMISSION_DENIED"],
    ["delegation", "DELEGATION_INVALID"],
    ["policy", "POLICY_INVALID"],
    ["budget", "BUDGET_EXHAUSTED"],
    ["preconditions", "PRECONDITION_FAILED"],
  ];
  for (const [field, reasonCode] of checks) {
    if (execution.checks?.[field] !== true) {
      return outcome(state, "denied", reasonCode, "matched");
    }
  }

  if (approval.single_use) {
    state.replayState = "used";
  }
  state.sideEffectCount += 1;
  return outcome(state, "executed", "EXECUTE_ALLOWED", "matched");
}

async function loadRuntime(manifest) {
  const actionManifestPath = resolve(lifecycleDir, manifest.action_fixture);
  const actionFixtureDir = dirname(actionManifestPath);
  const actionManifest = parseJson(await readFile(actionManifestPath, "utf8"));
  const schema = parseJson(
    await readFile(resolve(actionFixtureDir, actionManifest.schema), "utf8"),
  );
  const validate = new Ajv2020({ allErrors: true, strict: true }).compile(schema);
  const actions = new Map();

  for (const testCase of actionManifest.cases) {
    actions.set(testCase.id, await loadCaseAction(testCase, actionManifest));
  }

  return {
    actions,
    domainPrefix: actionManifest.domain_prefix,
    validate,
  };
}

async function main() {
  const manifest = parseJson(
    await readFile(resolve(lifecycleDir, "manifest.json"), "utf8"),
  );
  const runtime = await loadRuntime(manifest);
  const emit = process.argv.includes("--emit");
  const results = new Map();
  let attempts = 0;
  let failures = 0;

  for (const testCase of manifest.cases) {
    try {
      const caseContext = applyChanges(manifest.base_context, testCase.changes);
      const state = {
        replayState: caseContext.approval.replay_state,
        sideEffectCount: 0,
      };
      const actual = [];
      for (const attempt of testCase.attempts) {
        const attemptContext = applyChanges(caseContext, attempt.changes);
        actual.push(evaluateAttempt(attemptContext, state, runtime));
        attempts += 1;
      }
      results.set(testCase.id, actual);

      if (!emit && JSON.stringify(actual) !== JSON.stringify(testCase.expected)) {
        failures += 1;
        console.error(`FAIL lifecycle/${testCase.id}: outcome mismatch`);
      }
    } catch (error) {
      failures += 1;
      console.error(`FAIL lifecycle/${testCase.id}: ${error.message}`);
    }
  }

  if (emit) {
    console.log(JSON.stringify(Object.fromEntries(results), null, 2));
  } else {
    console.log(
      `Lifecycle JavaScript: ${manifest.cases.length} cases, ${attempts} attempts`,
    );
  }

  if (failures > 0) {
    process.exitCode = 1;
  }
}

await main();
