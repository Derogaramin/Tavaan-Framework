<?php

declare(strict_types=1);

/*
 * Core-user module — messages (en).
 */

return [
    // Auth errors
    'email_taken' => 'This email is already registered.',
    'create_failed' => 'Failed to create user.',
    'credentials_required' => 'Email and password are required.',
    'invalid_credentials' => 'Invalid email or password.',
    'account_banned' => 'This account has been banned.',
    'account_inactive' => 'This account is inactive.',
    'logged_out' => 'You have been logged out.',
    'registered' => 'Registration successful. Welcome!',
    'registration_disabled' => 'Public registration is disabled.',
    'wrong_current_password' => 'Current password is incorrect.',

    // User administration
    'user_not_found' => 'User not found.',
    'user_created' => 'User created successfully.',
    'user_updated' => 'User updated successfully.',
    'user_deleted' => 'User deleted.',
    'status_changed' => 'User status changed.',
    'password_reset' => 'Password has been reset.',
    'password_changed' => 'Password changed.',
    'profile_updated' => 'Profile updated.',
    'invalid_status' => 'Invalid status.',
    'cannot_delete_self' => 'You cannot delete your own account.',
    'cannot_change_self_status' => 'You cannot change your own account status.',
    'last_admin_protected' => 'The last active admin cannot be deleted or disabled.',

    // Statuses
    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'banned' => 'Banned',
    ],

    // UI labels
    'ui' => [
        'app_name' => 'Tavaan',
        'login_title' => 'Sign in to your account',
        'register_title' => 'Create an account',
        'profile_title' => 'My Profile',
        'admin_title' => 'User Management',
        'email' => 'Email',
        'password' => 'Password',
        'current_password' => 'Current password',
        'new_password' => 'New password',
        'confirm_password' => 'Confirm password',
        'name' => 'Full name',
        'mobile' => 'Mobile',
        'bio' => 'Bio',
        'status' => 'Status',
        'roles' => 'Roles',
        'remember_me' => 'Remember me',
        'login' => 'Sign in',
        'logout' => 'Sign out',
        'register' => 'Register',
        'no_account' => "Don't have an account?",
        'have_account' => 'Already have an account?',
        'save' => 'Save',
        'create_user' => 'Create user',
        'edit_user' => 'Edit user',
        'new_user' => 'New user',
        'search_placeholder' => 'Search name/email/mobile…',
        'all_statuses' => 'All statuses',
        'col_name' => 'Name',
        'col_email' => 'Email',
        'col_mobile' => 'Mobile',
        'col_status' => 'Status',
        'col_created' => 'Joined',
        'col_last_login' => 'Last login',
        'col_actions' => 'Actions',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'confirm_delete' => 'Are you sure you want to delete this user?',
        'activate' => 'Activate',
        'deactivate' => 'Deactivate',
        'ban' => 'Ban',
        'reset_password' => 'Reset password',
        'reset_password_hint' => 'New password (min 8 characters):',
        'view_site' => 'View site',
        'dashboard' => 'Dashboard',
        'my_profile' => 'My profile',
        'users_count' => 'Users',
        'no_users' => 'No users found.',
        'you' => '(you)',
        'change_password' => 'Change password',
        'password_hint' => 'Minimum 8 characters.',
        'created_at' => 'Joined',
        'page' => 'Page',
    ],
];
