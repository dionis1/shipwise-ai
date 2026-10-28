<?php

namespace App\Shared\Presentation;

final class ProblemCode
{
    public static function of(string $message, int $status): string
    {
        $exact = [
            'Invalid CSRF token.' => 'csrf_invalid', 'Invalid credentials or unavailable account.' => 'credentials_invalid', 'Authentication required.' => 'authentication_required',
            'This link is invalid or expired.' => 'token_invalid', 'Current password is incorrect.' => 'current_password_invalid',
            'The last active Super Administrator must remain active.' => 'last_admin', 'An account with this email already exists.' => 'account_exists',
            'Unknown role.' => 'role_unknown', 'Unknown permission.' => 'permission_unknown', 'System role permissions are immutable.' => 'system_role',
            'Assign one or more distinct staff roles.' => 'roles_required', 'Unsupported locale.' => 'locale_invalid',
            'Unexpected request fields.' => 'fields_invalid', 'Malformed JSON.' => 'request_invalid', 'A JSON body is required.' => 'request_invalid',
            'Name must contain between 2 and 100 characters.' => 'name_invalid', 'Password must contain between 12 and 128 bytes.' => 'password_invalid',
            'A valid email address is required.' => 'email_invalid', 'Invalid email address.' => 'email_invalid',
            'Only a Super Administrator can manage staff invitations and roles.' => 'forbidden',
            'Only active and suspended staff accounts can change status.' => 'status_invalid', 'Only active accounts can be suspended.' => 'status_invalid',
            'Account is already active.' => 'status_invalid', 'Account cannot be verified.' => 'token_invalid', 'Invitation cannot be accepted.' => 'token_invalid', 'Account unavailable.' => 'account_unavailable',
            'A staff account is required.' => 'staff_required', 'Staff account not found.' => 'not_found',
            'Use a valid ISO 8601 date or UTC timestamp filter.' => 'date_invalid', 'No profile fields supplied.' => 'fields_invalid',
        ];
        if (isset($exact[$message])) {
            return $exact[$message];
        }
        foreach (['email', 'name', 'password', 'currentPassword', 'roles', 'permissions', 'token', 'preferredLocale', 'status'] as $field) {
            if (str_starts_with($message, 'Invalid '.$field)) {
                return ['preferredLocale' => 'locale_invalid', 'currentPassword' => 'current_password_invalid', 'roles' => 'roles_required', 'permissions' => 'permission_unknown'][$field] ?? $field.'_invalid';
            }
        }

        return match ($status) {
            400 => 'request_invalid',401 => 'authentication_required',403 => 'forbidden',404 => 'not_found',405 => 'method_invalid',422 => 'request_invalid',429 => 'rate_limited',default => 'server_error'
        };
    }
}
