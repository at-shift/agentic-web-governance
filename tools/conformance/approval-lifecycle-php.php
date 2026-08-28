#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/php.php';

function lifecycleIsNonEmptyString(mixed $value): bool
{
    return is_string($value) && $value !== '';
}

function lifecycleHashesEqual(mixed $left, mixed $right): bool
{
    if (
        !is_string($left)
        || !is_string($right)
        || preg_match('/^sha256:[0-9a-f]{64}$/', $left) !== 1
        || preg_match('/^sha256:[0-9a-f]{64}$/', $right) !== 1
    ) {
        return false;
    }

    return hash_equals($left, $right);
}

/** @return array{status: string, reason_code: string, reconstruction: string, replay_state: string, side_effect_count: int} */
function lifecycleOutcome(
    array $state,
    string $status,
    string $reasonCode,
    string $reconstruction,
): array {
    return [
        'status' => $status,
        'reason_code' => $reasonCode,
        'reconstruction' => $reconstruction,
        'replay_state' => $state['replayState'],
        'side_effect_count' => $state['sideEffectCount'],
    ];
}

function lifecycleApprovalBindingIsValid(
    object $context,
    array $runtime,
): bool {
    $proposal = $context->proposal ?? null;
    $approval = $context->approval ?? null;
    $execution = $context->execution ?? null;
    if (
        !is_object($proposal)
        || !is_object($approval)
        || !is_object($execution)
        || !lifecycleIsNonEmptyString($proposal->id ?? null)
        || !lifecycleIsNonEmptyString($proposal->action_case ?? null)
        || !lifecycleIsNonEmptyString($proposal->request_hash ?? null)
        || !lifecycleIsNonEmptyString($approval->id ?? null)
        || !lifecycleIsNonEmptyString($approval->proposal_id ?? null)
        || !lifecycleIsNonEmptyString($approval->request_hash ?? null)
        || !is_object($approval->approver ?? null)
        || !lifecycleIsNonEmptyString($approval->approver->type ?? null)
        || !lifecycleIsNonEmptyString($approval->approver->id ?? null)
        || !in_array($approval->decision ?? null, ['approved', 'denied'], true)
        || !is_int($approval->decided_at ?? null)
        || !is_int($approval->expires_at ?? null)
        || $approval->decided_at >= $approval->expires_at
        || !is_bool($approval->single_use ?? null)
        || !in_array($approval->replay_state ?? null, ['unused', 'used'], true)
        || !is_int($execution->now ?? null)
        || $approval->proposal_id !== $proposal->id
        || !lifecycleHashesEqual($approval->request_hash, $proposal->request_hash)
    ) {
        return false;
    }

    $proposedAction = $runtime['actions'][$proposal->action_case] ?? null;
    if (!is_object($proposedAction)) {
        return false;
    }

    try {
        $proposed = createDigest(
            $proposedAction,
            $runtime['domainPrefix'],
            $runtime['schema'],
            $runtime['validator'],
        );
        return lifecycleHashesEqual($proposed['requestHash'], $proposal->request_hash);
    } catch (Throwable) {
        return false;
    }
}

function lifecycleEvaluateAttempt(object $context, array &$state, array $runtime): array
{
    if (!lifecycleApprovalBindingIsValid($context, $runtime)) {
        return lifecycleOutcome(
            $state,
            'denied',
            'APPROVAL_BINDING_INVALID',
            'not_attempted',
        );
    }

    $approval = $context->approval;
    $execution = $context->execution;
    if ($approval->decision !== 'approved' || $execution->now < $approval->decided_at) {
        return lifecycleOutcome(
            $state,
            'denied',
            'APPROVAL_NOT_APPROVED',
            'not_attempted',
        );
    }
    if ($execution->now >= $approval->expires_at) {
        return lifecycleOutcome(
            $state,
            'denied',
            'APPROVAL_EXPIRED',
            'not_attempted',
        );
    }
    if ($approval->single_use && $state['replayState'] === 'used') {
        return lifecycleOutcome(
            $state,
            'denied',
            'APPROVAL_REPLAYED',
            'not_attempted',
        );
    }

    $reconstruction = $execution->reconstruction ?? null;
    $actionCase = is_object($reconstruction) && ($reconstruction->status ?? null) === 'available'
        ? ($reconstruction->action_case ?? null)
        : null;
    $currentAction = is_string($actionCase) ? ($runtime['actions'][$actionCase] ?? null) : null;
    if (!is_object($currentAction)) {
        return lifecycleOutcome($state, 'denied', 'RECONSTRUCTION_FAILED', 'failed');
    }

    try {
        $current = createDigest(
            $currentAction,
            $runtime['domainPrefix'],
            $runtime['schema'],
            $runtime['validator'],
        );
    } catch (Throwable) {
        return lifecycleOutcome($state, 'denied', 'RECONSTRUCTION_FAILED', 'failed');
    }
    if (!lifecycleHashesEqual($current['requestHash'], $approval->request_hash)) {
        return lifecycleOutcome($state, 'denied', 'ACTION_HASH_MISMATCH', 'mismatched');
    }

    $checks = [
        'application_permission' => 'APPLICATION_PERMISSION_DENIED',
        'delegation' => 'DELEGATION_INVALID',
        'policy' => 'POLICY_INVALID',
        'budget' => 'BUDGET_EXHAUSTED',
        'preconditions' => 'PRECONDITION_FAILED',
    ];
    foreach ($checks as $field => $reasonCode) {
        if (($execution->checks->{$field} ?? null) !== true) {
            return lifecycleOutcome($state, 'denied', $reasonCode, 'matched');
        }
    }

    if ($approval->single_use) {
        $state['replayState'] = 'used';
    }
    ++$state['sideEffectCount'];
    return lifecycleOutcome($state, 'executed', 'EXECUTE_ALLOWED', 'matched');
}

function runApprovalLifecycleConformance(array $argv): int
{
    $root = dirname(__DIR__, 2);
    $lifecycleDir = $root . '/fixtures/approval-lifecycle-v1';
    $manifestSource = file_get_contents($lifecycleDir . '/manifest.json');
    if ($manifestSource === false) {
        throw new RuntimeException('cannot read lifecycle fixture manifest');
    }
    $manifest = parseJson($manifestSource);

    $actionManifestPath = realpath($lifecycleDir . '/' . $manifest->action_fixture);
    if ($actionManifestPath === false) {
        throw new RuntimeException('cannot resolve action fixture manifest');
    }
    $actionFixtureDir = dirname($actionManifestPath);
    $actionManifestSource = file_get_contents($actionManifestPath);
    if ($actionManifestSource === false) {
        throw new RuntimeException('cannot read action fixture manifest');
    }
    $actionManifest = parseJson($actionManifestSource);

    $schemaSource = file_get_contents($actionFixtureDir . '/' . $actionManifest->schema);
    if ($schemaSource === false) {
        throw new RuntimeException('cannot read action schema');
    }
    $schema = parseJson($schemaSource);
    $validator = new \Opis\JsonSchema\Validator();
    $actions = [];
    foreach ($actionManifest->cases as $case) {
        $actions[$case->id] = loadCaseAction($case, $actionManifest, $actionFixtureDir);
    }
    $runtime = [
        'actions' => $actions,
        'domainPrefix' => $actionManifest->domain_prefix,
        'schema' => $schema,
        'validator' => $validator,
    ];

    $emit = in_array('--emit', $argv, true);
    $results = [];
    $attempts = 0;
    $failures = 0;
    foreach ($manifest->cases as $case) {
        try {
            $caseContext = applyChanges($manifest->base_context, $case->changes ?? []);
            $state = [
                'replayState' => $caseContext->approval->replay_state,
                'sideEffectCount' => 0,
            ];
            $actual = [];
            foreach ($case->attempts as $attempt) {
                $attemptContext = applyChanges($caseContext, $attempt->changes ?? []);
                $actual[] = lifecycleEvaluateAttempt($attemptContext, $state, $runtime);
                ++$attempts;
            }
            $results[$case->id] = $actual;

            if (
                !$emit
                && json_encode($actual, JSON_UNESCAPED_SLASHES)
                    !== json_encode($case->expected, JSON_UNESCAPED_SLASHES)
            ) {
                ++$failures;
                fwrite(STDERR, "FAIL lifecycle/{$case->id}: outcome mismatch\n");
            }
        } catch (Throwable $error) {
            ++$failures;
            fwrite(STDERR, "FAIL lifecycle/{$case->id}: {$error->getMessage()}\n");
        }
    }

    if ($emit) {
        echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
    } else {
        echo sprintf(
            "Lifecycle PHP: %d cases, %d attempts\n",
            count($manifest->cases),
            $attempts,
        );
    }

    return $failures === 0 ? 0 : 1;
}

exit(runApprovalLifecycleConformance($argv));
