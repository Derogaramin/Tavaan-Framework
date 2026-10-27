<?php

declare(strict_types=1);

/*
 * Comment module — messages (en).
 */

return [
    'status' => [
        'pending' => 'Pending approval',
        'approved' => 'Approved',
        'spam' => 'Spam',
    ],
    'submitted' => 'Your comment was submitted and will appear after review.',
    'submitted_approved' => 'Your comment was submitted and published.',
    'closed' => 'Comments are closed for this content.',

    // Submit-service messages
    'type_not_registered' => 'Commentable type is not registered.',
    'comments_closed' => 'Comments are closed for this subject.',
    'login_required' => 'You must sign in to comment.',
    'invalid_subject' => 'Invalid subject identifier.',
    'invalid_parent' => 'Parent comment is not valid.',
    'parent_not_visible' => 'Cannot reply to an unapproved comment.',
    'max_depth_reached' => 'Maximum reply depth reached.',
    'not_found' => 'Comment not found.',
    'posted_visible' => 'Your comment was submitted and published.',
    'posted_pending' => 'Your comment was submitted and will appear after review.',

    // Moderation messages
    'approved' => 'Comment approved and published.',
    'marked_spam' => 'Comment marked as spam.',
    'moved_to_pending' => 'Comment moved back to the review queue.',
    'deleted' => 'Comment deleted.',

    // UI labels
    'ui' => [
        'comments' => 'Comments',
        'no_comments' => 'No comments yet — be the first!',
        'form_title' => 'Post a comment',
        'name' => 'Name',
        'email' => 'Email',
        'body' => 'Comment',
        'submit' => 'Submit',
        'admin_title' => 'Comment Moderation',
        'dashboard' => 'Dashboard',
        'queue' => 'Review queue',
        'view_site' => 'View site',
        'stat_pending' => 'Pending',
        'stat_approved' => 'Approved',
        'stat_spam' => 'Spam',
        'stat_total' => 'Total',
        'all_statuses' => 'All statuses',
        'all_types' => 'All subjects',
        'col_author' => 'Author',
        'col_body' => 'Body',
        'col_subject' => 'Subject',
        'col_status' => 'Status',
        'col_date' => 'Date',
        'col_actions' => 'Actions',
        'approve' => 'Approve',
        'unapprove' => 'Unapprove',
        'spam' => 'Spam',
        'delete' => 'Delete',
        'confirm_delete' => 'Are you sure you want to delete this comment?',
        'no_comments_admin' => 'No comments found.',
    ],
];
