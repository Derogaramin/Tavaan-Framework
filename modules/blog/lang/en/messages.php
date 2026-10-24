<?php

declare(strict_types=1);

/*
 * Blog module — messages and labels (en).
 * Keys: blog::messages.*
 */

return [
    // Public
    'blog_title' => 'Blog',
    'feed_title' => 'Blog feed',
    'nav_home' => 'Blog home',
    'nav_feed' => 'RSS feed',
    'powered_by' => 'Powered by Tavaan Framework ⚡',

    // Listing/search/archive
    'latest_posts' => 'Latest posts',
    'no_posts' => 'No posts published yet.',
    'search' => 'Search',
    'search_placeholder' => 'Search posts…',
    'search_submit' => 'Search',
    'search_results' => 'Search results for ":q"',
    'search_count' => ':count result(s) found',
    'tag_heading' => 'Tag: :tag',
    'archive_heading' => 'Archive: :month',
    'categories' => 'Categories',
    'no_categories' => 'No categories yet.',
    'archive' => 'Archive',
    'this_month' => 'This month',
    'by_author' => 'By :name',
    'views' => ':count views',
    'prev' => 'Prev',
    'next' => 'Next',

    // Comments (public)
    'comments' => 'Comments',
    'no_comments' => 'No comments yet.',
    'comment_form_title' => 'Leave a comment',
    'your_name' => 'Your name',
    'your_email' => 'Your email (not published)',
    'comment_body' => 'Comment',
    'comment_submit' => 'Submit',

    // Exceptions (messageIds)
    'comments_disabled' => 'Comments are disabled on this blog.',
    'comments_closed' => 'Comments are closed for this post.',
    'comments_guest_forbidden' => 'Only signed-in users may comment.',
    'comment_invalid_parent' => 'Parent comment is invalid.',
    'comment_not_found' => 'Comment not found.',
    'post_not_found' => 'Post not found.',

    // Notifications
    'notify_author_subject' => 'New comment on ":title"',
    'notify_author_line' => ':name left a new comment on ":title".',
    'notify_author_cta' => 'Review comment',
    'notify_commenter_subject' => 'Your comment was published',
    'notify_commenter_greeting' => 'Hi :name,',
    'notify_commenter_line' => 'Your comment was approved and published.',
    'notify_commenter_cta' => 'View comment',

    // Admin panel
    'admin_title' => 'Blog administration',
    'admin_posts' => 'Posts',
    'admin_comments' => 'Comments',
    'new_post' => 'New post',
    'edit_post' => 'Edit post',
    'view_site' => 'View site',
    'all_statuses' => 'All statuses',
    'title_col' => 'Title',
    'status_col' => 'Status',
    'author_col' => 'Author',
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

    // Post form
    'field_title' => 'Title',
    'field_slug' => 'Slug (optional)',
    'slug_auto_hint' => 'Leave empty to generate from title',
    'field_category' => 'Category',
    'no_category' => '— No category —',
    'field_body' => 'Body',
    'body_hint' => 'Markdown supported; output is sanitized automatically.',
    'field_excerpt' => 'Excerpt',
    'field_tags' => 'Tags',
    'tags_hint' => 'Separate with commas',
    'field_featured_image' => 'Featured image (media ULID)',
    'field_status' => 'Status',
    'field_publish_at' => 'Publish at',
    'schedule_hint' => 'Fill to schedule; empty publishes immediately',
    'seo' => 'SEO',
    'field_seo_title' => 'SEO title',
    'field_seo_description' => 'SEO description',
    'field_seo_keywords' => 'Keywords',

    // Operation messages
    'post_created' => '✓ Post created.',
    'post_updated' => '✓ Post updated.',
    'post_published' => '✓ Post published.',
    'post_scheduled' => '✓ Post scheduled.',
    'post_archived' => '✓ Post archived.',
    'post_deleted' => '✓ Post deleted.',
    'comment_approved' => '✓ Comment approved.',
    'comment_marked_spam' => '✓ Comment marked as spam.',
    'comment_deleted' => '✓ Comment deleted.',

    // Comment admin
    'comment_author_col' => 'Author',
    'comment_body_col' => 'Body',
    'comment_post_col' => 'Post',
    'pending_count' => ':count pending',
    'approve' => 'Approve',
    'mark_spam' => 'Spam',
];
