<?php

declare(strict_types=1);

/*
 * Translation keys for the Workflow engine (DOC-071) — en
 */

return [
    'not_found' => 'Workflow ":workflow" is not defined',
    'process_not_found' => 'Process instance ":instance" not found',
    'not_running' => 'Process instance ":instance" is not running (status: :status)',
    'illegal_transition' => 'Transition ":transition" is not allowed from state ":from"',
    'unknown_transition' => 'Transition ":transition" does not exist in the process definition',
    'gateway_stuck' => 'Gateway ":gateway" has no executable outgoing transition',
    'gateway_manual_fire' => 'Outgoing transitions of gateway ":gateway" are engine-internal',
    'join_unbalanced' => 'Join gateway ":gateway" is unbalanced (:arrived of :expected tokens)',
    'unauthorized' => 'Current user is not authorized for transition ":transition"',
    'invalid_duration' => 'Invalid duration ":after" (use ISO-8601 like P3D or human like "3 days")',
    'max_instances' => 'Active instance limit (:limit) reached for workflow ":workflow"',
    'timer_not_found' => 'Timer ":timer" is not pending on instance ":instance"',
    'timer_load_failed' => 'Failed to load instance for timer processing',
    'timer_failed' => 'Timer ":timer" failed to fire',
    'task_not_found' => 'Task ":task" not found',
    'task_not_pending' => 'Task ":task" is :status (only pending/claimed tasks are actionable)',
    'task_not_claimable' => 'User is not the assignee/candidate of task ":task"',
    'task_no_outgoing' => 'State ":state" has no outgoing transition',
    'task_ambiguous_outgoing' => 'State ":state" has multiple outgoing transitions — pass transitionId explicitly',
    'definition' => [
        'missing_start' => 'Process ":process" has no start state',
        'duplicate_state' => 'Duplicate state ":state" in process ":process"',
        'duplicate_transition' => 'Duplicate transition ":transition" in process ":process"',
        'unknown_state' => 'Unknown state ":state" in process ":process" (:where)',
        'invalid_id' => 'Invalid identifier ":id" in process ":process"',
        'orphan_state' => 'State ":state" in process ":process" has no incoming/outgoing transitions',
        'subprocess_missing' => 'Sub-process state ":state" in process ":process" has no process reference',
    ],
];
