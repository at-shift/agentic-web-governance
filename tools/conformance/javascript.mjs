#!/usr/bin/env node

import { readFile } from "node:fs/promises";
import { dirname, resolve } from "node:path";
import { fileURLToPath } from "node:url";

import Ajv2020 from "ajv/dist/2020.js";
import { getNodeValue, parseTree, printParseErrorCode } from "jsonc-parser";

import {
  CanonicalActionError as ConformanceError,
  assertWellFormedStrings,
  createDigest,
  normalizeAction,
} from "../../src/javascript/canonical-action-v1.mjs";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "../..");
const fixtureDir = resolve(root, "fixtures/action-v1");

function inspectTree(node, source, inspectNumbers) {
  if (node.type === "object") {
    const keys = new Set();
    for (const property of node.children ?? []) {
      const key = property.children?.[0]?.value;
      if (keys.has(key)) {
        throw new ConformanceError("parse", `duplicate object member: ${key}`);
      }
      keys.add(key);
    }
  }

  if (inspectNumbers && node.type === "number") {
    const token = source.slice(node.offset, node.offset + node.length);
    if (/^-?(?:0|[1-9][0-9]*)$/.test(token)) {
      const integer = BigInt(token);
      if (integer < -9007199254740991n || integer > 9007199254740991n) {
        throw new ConformanceError("schema", `unsafe integer literal: ${token}`);
      }
    }
    if (!Number.isFinite(node.value)) {
      throw new ConformanceError("parse", `non-finite number: ${token}`);
    }
    if (Object.is(node.value, -0)) {
      throw new ConformanceError("parse", "negative zero is not admitted");
    }
  }

  for (const child of node.children ?? []) {
    inspectTree(child, source, inspectNumbers);
  }
}

function parseJson(source, { inspectNumbers = false } = {}) {
  const errors = [];
  const tree = parseTree(source, errors, {
    allowEmptyContent: false,
    allowTrailingComma: false,
    disallowComments: true,
  });

  if (!tree || errors.length > 0) {
    const detail = errors.map(({ error }) => printParseErrorCode(error)).join(", ");
    throw new ConformanceError("parse", detail || "invalid JSON");
  }

  inspectTree(tree, source, inspectNumbers);
  const value = getNodeValue(tree);
  assertWellFormedStrings(value);
  return value;
}

function clone(value) {
  return structuredClone(value);
}

function pointerSegments(pointer) {
  if (!pointer.startsWith("/")) {
    throw new Error(`invalid fixture pointer: ${pointer}`);
  }
  return pointer
    .slice(1)
    .split("/")
    .map((part) => part.replaceAll("~1", "/").replaceAll("~0", "~"));
}

function applyChanges(base, changes = []) {
  const value = clone(base);
  for (const change of changes) {
    const segments = pointerSegments(change.path);
    const key = segments.pop();
    let parent = value;
    for (const segment of segments) {
      if (!parent || typeof parent !== "object" || !(segment in parent)) {
        throw new Error(`fixture pointer does not exist: ${change.path}`);
      }
      parent = parent[segment];
    }

    if (change.op === "remove") {
      delete parent[key];
    } else if (change.op === "add" || change.op === "replace") {
      parent[key] = clone(change.value);
    } else {
      throw new Error(`unsupported fixture operation: ${change.op}`);
    }
  }
  return value;
}

async function loadCaseAction(testCase, manifest) {
  if (testCase.file) {
    const source = await readFile(resolve(fixtureDir, testCase.file), "utf8");
    const fixture = parseJson(source, { inspectNumbers: true });
    return fixture.action ?? fixture;
  }
  return applyChanges(manifest.base_action, testCase.changes);
}

async function main() {
  const manifestSource = await readFile(resolve(fixtureDir, "manifest.json"), "utf8");
  const manifest = parseJson(manifestSource);
  const schemaSource = await readFile(resolve(fixtureDir, manifest.schema), "utf8");
  const schema = parseJson(schemaSource);
  const validate = new Ajv2020({ allErrors: true, strict: true }).compile(schema);
  const emit = process.argv.includes("--emit");
  const results = new Map();
  let failures = 0;

  for (const testCase of manifest.cases) {
    try {
      const action = await loadCaseAction(testCase, manifest);
      const result = createDigest(action, manifest.domain_prefix, validate);
      results.set(testCase.id, result);

      if (!emit) {
        if (
          testCase.expected_canonical_hex &&
          result.canonicalHex !== testCase.expected_canonical_hex
        ) {
          throw new Error("canonical bytes do not match fixture");
        }
        if (result.requestHash !== testCase.expected_hash) {
          throw new Error("request hash does not match fixture");
        }
      }
    } catch (error) {
      failures += 1;
      console.error(`FAIL accepted/${testCase.id}: ${error.message}`);
    }
  }

  for (const relation of manifest.relations) {
    const left = results.get(relation.left)?.requestHash;
    const right = results.get(relation.right)?.requestHash;
    const passed = relation.kind === "equal" ? left === right : left !== right;
    if (!passed || !left || !right) {
      failures += 1;
      console.error(
        `FAIL relation/${relation.left}/${relation.kind}/${relation.right}`,
      );
    }
  }

  for (const rejected of manifest.rejected) {
    try {
      const action = rejected.raw
        ? parseJson(rejected.raw, { inspectNumbers: true })
        : applyChanges(manifest.base_action, rejected.changes);
      createDigest(action, manifest.domain_prefix, validate);
      failures += 1;
      console.error(`FAIL rejected/${rejected.id}: unexpectedly accepted`);
    } catch (error) {
      if (error.stage !== rejected.stage) {
        failures += 1;
        console.error(
          `FAIL rejected/${rejected.id}: expected ${rejected.stage}, got ${error.stage ?? "runtime"}`,
        );
      }
    }
  }

  if (emit) {
    console.log(
      JSON.stringify(
        Object.fromEntries(
          [...results].map(([id, result]) => [
            id,
            {
              expected_canonical_hex: result.canonicalHex,
              expected_hash: result.requestHash,
            },
          ]),
        ),
        null,
        2,
      ),
    );
  } else {
    console.log(
      `JavaScript: ${manifest.cases.length} accepted, ${manifest.rejected.length} rejected, ${manifest.relations.length} relations`,
    );
  }

  if (failures > 0) {
    process.exitCode = 1;
  }
}

export {
  ConformanceError,
  applyChanges,
  createDigest,
  loadCaseAction,
  normalizeAction,
  parseJson,
};

if (resolve(process.argv[1] ?? "") === fileURLToPath(import.meta.url)) {
  await main();
}
