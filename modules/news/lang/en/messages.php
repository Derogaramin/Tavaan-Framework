<?php

declare(strict_types=1);

/*
 * News module — messages and labels (en). Keys: news::messages.*
 */

return [
    // Public
    'news_title' => 'News',
    'feed_title' => 'News feed',
    'nav_home' => 'Home',
    'nav_feed' => 'RSS feed',
    'powered_by' => 'Powered by Tavaan Framework ⚡',
    'breaking' => 'Breaking',
    'desktop_version' => 'Full version',

    // Listing/search/archive
    'latest_news' => 'Latest news',
    'no_news' => 'No news published yet.',
    'search' => 'Search',
    'search_placeholder' => 'Search news…',
    'search_submit' => 'Search',
    'search_results' => 'Search results for ":q"',
    'tag_heading' => 'Tag: :tag',
    'archive_heading' => 'Archive: :month',
    'categories' => 'Categories',
    'sub_category' => 'sub',
    'no_categories' => 'No categories yet.',
    'archive' => 'Archive',
    'this_month' => 'This month',
    'by_author' => 'By :name',
    'edited_by' => 'Editor: :name',
    'views' => ':count views',
    'prev' => 'Prev',
    'next' => 'Next',
    'related_news' => 'Related news',

    // Comments
    'comments' => 'Comments',
    'no_comments' => 'No comments yet.',
    'comment_form_title' => 'Leave a comment',
    'your_name' => 'Your name',
    'your_email' => 'Your email (not published)',
    'comment_body' => 'Comment',
    'comment_submit' => 'Submit',

    // Exceptions (messageIds)
    'comments_disabled' => 'Comments are disabled on this portal.',
    'comments_closed' => 'Comments are closed for this article.',
    'comments_guest_forbidden' => 'Only signed-in users may comment.',
    'comment_invalid_parent' => 'Parent comment is invalid.',
    'comment_not_found' => 'Comment not found.',
    'article_not_found' => 'Article not found.',
    'category_not_found' => 'Category not found.',
    'category_title_required' => 'Category title is required.',

    // Workflow notifications
    'notify_approved_subject' => '"​:title" was approved',
    'notify_approved_line' => '"​:title" was approved by the editor and queued for publication.',
    'notify_rejected_subject' => '"​:title" needs revision',
    'notify_rejected_line' => '"​:title" was rejected. Reason: :reason',
    'notify_published_subject' => '"​:title" was published',
    'notify_published_line' => '"​:title" is now live.',
    'notify_cta' => 'View article',

    // Admin
    'admin_title' => 'News administration',
    'admin_articles' => 'Articles',
    'admin_categories' => 'Categories',
    'admin_comments' => 'Comments',
    'new_article' => 'New article',
    'edit_article' => 'Edit article',
    'view_site' => 'View site',
    'all_statuses' => 'All statuses',
    'title_col' => 'Title',
    'status_col' => 'Status',
    'author_col' => 'Author',
    'date_col' => 'Date',
    'views_col' => 'Views',
    'actions_col' => 'Actions',
    'edit' => 'Edit',
    'view' => 'View',
    'publish' => 'Publish',
    'publish_now' => 'Publish now',
    'delete' => 'Delete',
    'confirm_delete' => 'Are you sure?',
    'save' => 'Save',
    'create' => 'Create',
    'back_to_list' => 'Back to list',

    // Article form
    'field_title' => 'Title',
    'field_slug' => 'Slug (optional)',
    'slug_auto_hint' => 'Leave empty to generate from title',
    'field_category' => 'Category',
    'no_category' => '— No category —',
    'field_lead' => 'Lead',
    'field_body' => 'Body',
    'field_tags' => 'Tags',
    'field_featured_image' => 'Featured image (media ULID)',
    'field_gallery' => 'Gallery (comma-separated ULIDs)',
    'seo' => 'SEO',
    'field_seo_title' => 'SEO title',
    'field_seo_description' => 'SEO description',
    'field_category_title' => 'Category title',
    'field_parent' => 'Parent category',
    'field_description' => 'Description',
    'field_schedule_at' => 'Scheduled publish time (empty = on publish)',

    // Workflow
    'workflow' => 'Workflow',
    'submit_for_review' => 'Submit for review',
    'approve' => 'Approve',
    'reject' => 'Reject',
    'reject_reason' => 'Rejection reason: :reason',
    'reject_reason_placeholder' => 'Rejection reason…',
    'mark_breaking' => 'Mark breaking 🔴',
    'unmark_breaking' => 'Unmark breaking',
    'archive_action' => 'Archive',

    // Operation messages
    'article_created' => '✓ Article created.',
    'article_updated' => '✓ Article updated.',
    'article_submitted' => '✓ Submitted for review.',
    'article_approved' => '✓ Article approved.',
    'article_rejected' => '✓ Article rejected.',
    'article_published' => '✓ Article published.',
    'article_scheduled' => '✓ Publication scheduled.',
    'article_archived' => '✓ Article archived.',
    'article_deleted' => '✓ Article deleted.',
    'marked_breaking' => '✓ Marked as breaking.',
    'unmarked_breaking' => '✓ Breaking mark removed.',
    'category_created' => '✓ Category created.',
    'category_updated' => '✓ Category updated.',
    'category_deleted' => '✓ Category deleted.',
    'comment_approved' => '✓ Comment approved.',
    'comment_marked_spam' => '✓ Comment marked as spam.',
    'comment_deleted' => '✓ Comment deleted.',

    // Comment admin
    'comment_author_col' => 'Author',
    'comment_body_col' => 'Body',
    'comment_article_col' => 'Article',
    'pending_count' => ':count pending',
    'mark_spam' => 'Spam',
];
