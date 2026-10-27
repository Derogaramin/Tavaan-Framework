<?php

declare(strict_types=1);

/*
 * Content module — CMS pages messages and labels (en).
 * Keys: content::messages.*
 */

return [
    // Public (pages index/show)
    'pages_title' => 'Pages',
    'nav_home' => 'Pages index',
    'nav_sitemap' => 'Sitemap',
    'powered_by' => 'Powered by Tavaan Framework ⚡',
    'no_pages' => 'No pages published yet.',
    'views' => ':count views',
    'read_more' => 'Read more',

    // Exceptions (messageId)
    'page_not_found' => 'Page not found.',

    // Comments (under page — via comment module)
    'comments' => 'Comments',
    'no_comments' => 'No comments yet.',
    'comment_form_title' => 'Leave a comment',
    'your_name' => 'Your name',
    'your_email' => 'Your email (not published)',
    'comment_body' => 'Comment body',
    'comment_submit' => 'Submit comment',
    'comments_closed' => 'Comments are closed for this page.',

    // Admin panel
    'admin_title' => 'Content management',
    'admin_pages' => 'Pages',
    'new_page' => 'New page',
    'edit_page' => 'Edit page',
    'view_site' => 'View site',
    'all_statuses' => 'All statuses',
    'title_col' => 'Title',
    'status_col' => 'Status',
    'date_col' => 'Date',
    'views_col' => 'Views',
    'actions_col' => 'Actions',
    'edit' => 'Edit',
    'publish' => 'Publish',
    'archive_action' => 'Archive',
    'delete' => 'Delete',
    'confirm_delete' => 'Are you sure?',
    'save' => 'Save',
    'create' => 'Create',
    'back_to_list' => 'Back to list',
    'show_page' => 'View',

    // Page form
    'field_title' => 'Title',
    'field_slug' => 'slug (optional)',
    'slug_auto_hint' => 'Leave empty to generate from title',
    'field_body' => 'Body',
    'body_hint' => 'Markdown is supported; output is sanitized automatically.',
    'field_excerpt' => 'Excerpt',
    'excerpt_hint' => 'Leave empty to generate from body',
    'field_comments_open' => 'Comments open for this page',
    'seo' => 'SEO',
    'field_seo_title' => 'SEO title',
    'field_seo_description' => 'SEO description',
    'field_seo_keywords' => 'Keywords',

    // Operation messages
    'page_created' => '✓ Page created.',
    'page_updated' => '✓ Page updated.',
    'page_published' => '✓ Page published.',
    'page_archived' => '✓ Page archived.',
    'page_trashed' => '✓ Page deleted.',

    // Dashboard widget
    'widget_title' => 'Pages',
    'widget_published' => ':count published pages',
    'widget_unavailable' => 'Pages table is not ready (migrate not run).',
    'widget_link' => 'Manage pages',
];
