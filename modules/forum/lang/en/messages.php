<?php

declare(strict_types=1);

/*
 * Forum module — messages and labels (en). Key: forum::messages.*
 */

return [
    // General
    'forum_title' => 'Forum',
    'nav_forums' => 'Forums',
    'nav_search' => 'Search',
    'nav_profile' => 'My Profile',
    'nav_login' => 'Login',
    'powered_by' => 'Powered by Tavaan Framework ⚡',
    'guest' => 'Guest',

    // Forum list
    'forums' => 'Forums',
    'no_forums' => 'No forums yet.',
    'sub_forums' => 'Sub-forums',
    'threads' => 'Threads',
    'posts' => 'Posts',
    'no_threads' => 'No threads.',
    'latest_threads' => 'Latest Threads',
    'stats' => 'Forum Stats',
    'stat_forums' => 'Forums: :n',
    'stat_threads' => 'Threads: :n',
    'stat_posts' => 'Posts: :n',
    'stat_members' => 'Members: :n',
    'top_members' => 'Top Members',
    'no_members' => 'No members yet.',
    'replies' => 'Replies',
    'views' => 'Views',
    'pinned' => 'Pinned',
    'locked' => 'Locked',
    'open' => 'Open',
    'solved' => 'Solved',

    // Search
    'search' => 'Search',
    'search_placeholder' => 'Search threads and posts…',
    'search_submit' => 'Search',
    'search_results' => 'Search Results',
    'no_results' => 'No results found.',

    // Thread
    'new_thread' => 'New Thread',
    'forum_label' => 'Forum',
    'thread_title' => 'Thread title',
    'thread_body' => 'Thread body',
    'thread_submit' => 'Publish thread',
    'thread_starter' => 'Thread starter',
    'forum_locked_notice' => 'This forum is locked and does not accept new threads.',
    'thread_locked_notice' => 'This thread is locked and does not accept new replies.',
    'cannot_reply' => 'You are not allowed to reply.',
    'login_to_reply' => 'Log in to reply:',

    // Post
    'reply' => 'Reply',
    'reply_body' => 'Reply body',
    'reply_submit' => 'Post reply',
    'no_posts' => 'No posts.',
    'quote' => 'Quote',
    'edit' => 'Edit',
    'edit_post' => 'Edit Post',
    'save' => 'Save',
    'cancel' => 'Cancel',
    'delete' => 'Delete',
    'delete_confirm' => 'Are you sure? This cannot be undone.',
    'hide' => 'Hide',
    'unhide' => 'Unhide',
    'edited' => 'edited',
    'post_hidden_notice' => 'This post was hidden by a moderator.',
    'markdown_hint' => 'Markdown supported: **bold**, `code`, > quote, [link](https://…) and @username',

    // Vote
    'upvote' => 'Upvote',
    'downvote' => 'Downvote',
    'vote' => 'Vote',

    // Solution
    'solution' => 'Solution',
    'mark_solution' => '✅ Mark as solution',
    'unmark_solution' => 'Unmark solution',

    // Flag / moderation
    'flag' => 'Flag',
    'flag_reason_prompt' => 'Reason (optional):',
    'admin_moderation' => 'Moderation Queue',
    'flag_pending' => 'Pending',
    'flag_reporter' => 'Reporter',
    'flag_author' => 'Post author',
    'resolve_hide' => '✓ Resolve (hide)',
    'dismiss' => 'Dismiss',
    'queue_empty' => 'Moderation queue is empty. 🎉',
    'view_in_thread' => 'View in thread',
    'post_removed' => 'Flagged post is unavailable.',
    'status_visible' => 'Visible',
    'status_hidden' => 'Hidden',
    'status_deleted' => 'Deleted',

    // Subscription
    'subscribe' => '🔔 Follow',
    'unsubscribe' => 'Unfollow',

    // Realtime
    'live_on' => 'Live',
    'live_off' => 'Offline',
    'live_new_post' => '🔔 New reply arrived — refresh to view',

    // Profile / member
    'karma' => 'Karma',
    'username' => 'Username',
    'username_hint' => '3-30 chars — letters/digits/_/. (used for @mentions)',
    'bio' => 'Bio',
    'signature' => 'Signature',
    'edit_profile' => 'Edit Profile',
    'member_since' => 'Member since',
    'recent_threads' => 'Recent threads',
    'stat_threads_label' => 'Threads',
    'stat_posts_label' => 'Posts',
    'stat_solutions_label' => 'Solutions',

    // Ban
    'ban_author' => '🚫 Ban author',
    'ban_days' => 'days (0 = permanent)',
    'ban_confirm' => 'Ban this user?',

    // Admin
    'admin_title' => 'Forum Administration',
    'admin_forums' => 'Forums',
    'admin_threads' => 'Threads',
    'view_site' => 'View forum',
    'new_forum' => 'New forum',
    'edit_forum' => 'Edit forum',
    'title' => 'Title',
    'slug' => 'slug',
    'slug_optional' => 'optional — generated from title',
    'description' => 'Description',
    'parent_forum' => 'Parent forum',
    'no_parent' => '— none (root) —',
    'position' => 'Position',
    'status' => 'Status',
    'actions' => 'Actions',
    'all_forums' => 'All forums',
    'lock' => 'Lock',
    'unlock' => 'Unlock',
    'pin' => 'Pin',
    'unpin' => 'Unpin',
    'move' => 'Move',
    'invalid_title' => 'Invalid title.',

    // Flash messages
    'thread_created' => 'Your thread was published. ✅',
    'post_created' => 'Your reply was posted. ✅',
    'post_updated' => 'Post updated.',
    'subscribed' => 'Following this thread. 🔔',
    'unsubscribed' => 'Unfollowed.',
    'solution_marked' => 'Solution marked. ✅',
    'solution_unmarked' => 'Solution unmarked.',
    'flagged' => 'Your report was queued for moderation. 🚩',
    'profile_updated' => 'Profile updated.',
    'forum_created' => 'Forum created.',
    'forum_updated' => 'Forum updated.',
    'forum_locked' => 'Forum locked.',
    'forum_unlocked' => 'Forum unlocked.',
    'forum_deleted' => 'Forum deleted.',
    'thread_locked' => 'Thread locked.',
    'thread_unlocked' => 'Thread unlocked.',
    'thread_pin_toggled' => 'Pin state changed.',
    'thread_moved' => 'Thread moved.',
    'thread_renamed' => 'Thread renamed.',
    'thread_deleted' => 'Thread and all its posts deleted.',
    'flag_resolved' => 'Flag resolved; post hidden.',
    'flag_dismissed' => 'Flag dismissed.',
    'post_hidden' => 'Post hidden.',
    'post_unhidden' => 'Post unhidden.',
    'post_deleted' => 'Post deleted.',
    'user_banned' => 'User banned from the forum.',
    'user_unbanned' => 'User unbanned.',

    // Notifications
    'notify_cta' => 'View in forum',
    'notify_reply_subject' => 'New reply: :thread',
    'notify_reply_line' => ':user replied: ":excerpt"',
    'notify_mention_subject' => 'You were mentioned: :thread',
    'notify_mention_line' => ':user mentioned you in the forum.',
    'notify_solution_subject' => 'Your answer was selected ✅',
    'notify_solution_line' => 'Your post in ":thread" was marked as the solution.',
    'notify_flag_subject' => 'New report in moderation queue 🚩',
    'notify_flag_line' => 'A post was flagged. Reason: :reason',
    'notify_thread_subject' => 'New forum thread',
    'notify_thread_line' => 'New thread: ":title"',
];
