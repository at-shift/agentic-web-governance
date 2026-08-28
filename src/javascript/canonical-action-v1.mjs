// SPDX-License-Identifier: GPL-2.0-or-later

import { createHash } from "node:crypto";

import canonicalize from "canonicalize";

export const ACTION_V1_DOMAIN_PREFIX = "AWG-ACTION-V1\n";

export class CanonicalActionError extends Error {
  constructor(stage, message) {
    super(message);
    this.name = "CanonicalActionError";
    this.stage = stage;
  }
}

export function assertWellFormedStrings(value) {
  if (typeof value === "string" && !value.isWellFormed()) {
    throw new CanonicalActionError("unicode", "unpaired Unicode surrogate");
  }
  if (Array.isArray(value)) {
    value.forEach(assertWellFormedStrings);
  } else if (value && typeof value === "object") {
    for (const [key, child] of Object.entries(value)) {
      assertWellFormedStrings(key);
      assertWellFormedStrings(child);
    }
  }
}

function normalizeStringSet(value) {
  if (!Array.isArray(value) || value.some((item) => typeof item !== "string")) {
    return value;
  }
  return [...new Set(value)].sort();
}

export function normalizeAction(input) {
  const action = structuredClone(input);

  if (action.impact && "data_classes" in action.impact) {
    action.impact.data_classes = normalizeStringSet(action.impact.data_classes);
  }
  if (action.transmission && "data_classes" in action.transmission) {
    action.transmission.data_classes = normalizeStringSet(
      action.transmission.data_classes,
    );
  }
  if (Array.isArray(action.policy?.profiles)) {
    const ids = new Set();
    for (const profile of action.policy.profiles) {
      if (typeof profile?.id !== "string") {
        throw new CanonicalActionError(
          "normalize",
          "profile id must be a string",
        );
      }
      if (ids.has(profile.id)) {
        throw new CanonicalActionError(
          "normalize",
          `duplicate profile id: ${profile.id}`,
        );
      }
      ids.add(profile.id);
    }
    action.policy.profiles.sort((left, right) =>
      left.id < right.id ? -1 : left.id > right.id ? 1 : 0,
    );
  }

  return action;
}

export function createDigest(
  action,
  domainPrefix = ACTION_V1_DOMAIN_PREFIX,
  validate,
) {
  if (typeof validate !== "function") {
    throw new TypeError("createDigest requires a schema validator");
  }

  const normalized = normalizeAction(action);
  assertWellFormedStrings(normalized);
  if (!validate(normalized)) {
    const detail = (validate.errors ?? [])
      .map((error) => `${error.instancePath || "/"} ${error.message}`)
      .join("; ");
    throw new CanonicalActionError(
      "schema",
      detail || "schema validation failed",
    );
  }

  const canonical = canonicalize(normalized);
  if (typeof canonical !== "string") {
    throw new CanonicalActionError(
      "canonicalize",
      "JCS serialization failed",
    );
  }
  const digest = createHash("sha256")
    .update(domainPrefix, "ascii")
    .update(canonical, "utf8")
    .digest("hex");

  return {
    canonical,
    canonicalHex: Buffer.from(canonical, "utf8").toString("hex"),
    requestHash: `sha256:${digest}`,
  };
}

export function createActionHasher({
  validate,
  domainPrefix = ACTION_V1_DOMAIN_PREFIX,
}) {
  if (typeof validate !== "function") {
    throw new TypeError("createActionHasher requires a schema validator");
  }
  return (action) => createDigest(action, domainPrefix, validate);
}
