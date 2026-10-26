<?php

declare(strict_types=1);

/*
 * BPMS module — English messages (DOC-098).
 */

return [
    // ── Titles ────────────────────────────────────────────────────────────
    'bpms_title' => 'Business Process Management',
    'monitoring' => 'Process Monitoring',
    'definitions' => 'Process Definitions',
    'instances' => 'Process Instances',
    'my_tasks' => 'My Tasks',
    'processes' => 'Processes',
    'audit' => 'Audit Trail',
    'tasks' => 'Tasks',
    'search' => 'Search',
    'actions' => 'Actions',
    'status' => 'Status',
    'version' => 'Version',
    'back' => 'Back',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'prev_page' => 'Previous',
    'next_page' => 'Next',
    'no_results' => 'Nothing found.',

    // ── Definitions ───────────────────────────────────────────────────────
    'definition_not_found' => 'Process definition not found.',
    'definition_name_invalid' => 'Process name is empty or too long.',
    'definition_key_invalid' => 'Invalid process key (lowercase letters, digits, - and _ only).',
    'definition_json_invalid' => 'Invalid process definition JSON.',
    'form_json_invalid' => 'Invalid forms JSON.',
    'form_invalid' => 'Invalid form schema.',
    'draft' => 'Draft',
    'published' => 'Published',
    'retired' => 'Retired',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'publish' => 'Publish',
    'activate' => 'Activate',
    'retire' => 'Retire',
    'new_definition' => 'New Process',
    'edit_definition' => 'Edit Process',
    'diagram' => 'Process Diagram',
    'mermaid_source' => 'Mermaid Source',

    // ── Instances ─────────────────────────────────────────────────────────
    'instance_not_found' => 'Process instance not found.',
    'running' => 'Running',
    'completed' => 'Completed',
    'suspended' => 'Suspended',
    'terminated' => 'Terminated',
    'start_process' => 'Start Process',
    'business_key' => 'Business Key',
    'variables' => 'Variables (JSON)',
    'initiator' => 'Initiator',
    'current_state' => 'Current State',
    'timeline' => 'Timeline',
    'terminate' => 'Terminate',
    'suspend' => 'Suspend',
    'resume' => 'Resume',

    // ── Tasks ─────────────────────────────────────────────────────────────
    'task_not_found' => 'Task not found.',
    'task_pending' => 'Pending',
    'task_claimed' => 'Claimed',
    'task_completed' => 'Completed',
    'task_canceled' => 'Canceled',
    'claim' => 'Claim',
    'complete' => 'Complete',
    'delegate' => 'Delegate',
    'due_at' => 'Due',
    'escalated' => 'Escalated',
    'overdue' => 'Overdue',
    'comment' => 'Comment',
    'claimed_tasks' => 'My Claimed Tasks',
    'available_tasks' => 'Available Tasks',
    'assignee' => 'Assignee',

    // ── Monitoring ────────────────────────────────────────────────────────
    'active_instances' => 'Active Instances',
    'completed_instances' => 'Completed',
    'avg_cycle' => 'Average Cycle',
    'bottlenecks' => 'Bottlenecks',
    'sla_compliance' => 'SLA Compliance',
    'workload' => 'Workload',
    'recent_activity' => 'Recent Activity',
    'all_definitions' => 'All Processes',
    'download_csv' => 'Download CSV',

    // ── Security ──────────────────────────────────────────────────────────
    'forbidden' => 'You are not authorized for this action.',

    // ── Notifications ─────────────────────────────────────────────────────
    'notify_task_subject' => 'New task: :title',
    'notify_task_line' => 'Task ":title" is waiting for your action.',
    'notify_task_cta' => 'View task',
    'notify_completed_subject' => 'Process completed: :key',
    'notify_completed_line' => 'Process ":key" has finished.',
    'notify_completed_cta' => 'View instance',
    'notify_escalated_subject' => 'Overdue task: :title',
    'notify_escalated_line' => 'Task ":title" passed its due date and was escalated.',
];
