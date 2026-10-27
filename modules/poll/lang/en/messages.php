<?php

declare(strict_types=1);

/*
 * Poll module — messages and labels (en).
 * Keys: poll::messages.*
 */

return [
    // Public
    'polls_title' => 'Polls',
    'nav_home' => 'Polls index',
    'powered_by' => 'Powered by Tavaan Framework ⚡',
    'no_polls' => 'No open polls right now.',
    'votes_count' => ':count votes',
    'ends_at_label' => 'Voting ends: :date',
    'starts_at_label' => 'Voting starts: :date',
    'vote_submit' => 'Submit vote',
    'multiple_hint' => 'You may select multiple options.',
    'results_title' => 'Results',
    'total_votes' => 'Total votes: :count',
    'you_voted' => 'Your vote is recorded ✓',
    'closed_notice' => 'Voting for this poll is closed.',
    'not_started_notice' => 'Voting for this poll has not started yet.',
    'option_col' => 'Option',
    'count_col' => 'Count',
    'percent_col' => 'Percent',

    // Exceptions (messageId)
    'poll_not_found' => 'Poll not found.',
    'not_votable' => 'This poll is not votable right now.',
    'login_required' => 'Voting is only allowed for signed-in users.',
    'option_required' => 'Select at least one valid option.',
    'single_choice_only' => 'This poll is single-choice.',
    'already_voted' => 'You have already voted in this poll.',
    'options_required' => 'Enter at least two options.',
    'options_count' => 'Invalid number of options.',
    'options_locked' => 'Options cannot change after the first vote.',
    'window_invalid' => 'Voting end must be after its start.',
    'date_invalid' => 'Invalid date format.',
    'vote_ok' => '✓ Your vote was recorded.',

    // Admin panel
    'admin_title' => 'Poll management',
    'admin_polls' => 'Polls',
    'new_poll' => 'New poll',
    'edit_poll' => 'Edit poll',
    'view_site' => 'View site',
    'all_statuses' => 'All statuses',
    'question_col' => 'Question',
    'status_col' => 'Status',
    'votes_col' => 'Votes',
    'date_col' => 'Date',
    'actions_col' => 'Actions',
    'edit' => 'Edit',
    'open_action' => 'Open',
    'close_action' => 'Close',
    'delete' => 'Delete',
    'confirm_delete' => 'Are you sure?',
    'save' => 'Save',
    'create' => 'Create',
    'back_to_list' => 'Back to list',
    'show_poll' => 'View',

    // Form
    'field_question' => 'Question',
    'field_slug' => 'slug (optional)',
    'slug_auto_hint' => 'Leave empty to generate from question',
    'field_options' => 'Options',
    'option_n' => 'Option :n',
    'field_multiple' => 'Multiple choice (several options at once)',
    'field_starts_at' => 'Voting starts',
    'field_ends_at' => 'Voting ends',
    'window_hint' => 'Empty → no time limit',
    'field_status' => 'Status',
    'options_locked_hint' => 'This poll has votes — options are locked.',

    // Operation messages
    'poll_created' => '✓ Poll created.',
    'poll_updated' => '✓ Poll updated.',
    'poll_opened' => '✓ Voting opened.',
    'poll_closed' => '✓ Voting closed.',
    'poll_trashed' => '✓ Poll deleted.',

    // Dashboard widget
    'widget_title' => 'Polls',
    'widget_unavailable' => 'Poll tables are not ready (migrate not run).',
    'widget_link' => 'Manage polls',
];
