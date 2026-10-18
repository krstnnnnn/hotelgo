<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Any logged-in user (guest, staff or admin). */
class AuthMiddleware
{
    public function handle(Closure $next)
    {
        $user = current_user();
        if (!$user) {
            flash('warn', 'Please sign in first.');
            go('login');                                   // go() stops the script
        }
        $path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '', '/');
        if ((int) $user['must_change_password'] === 1 && !str_ends_with($path, 'account/password') && !str_ends_with($path, 'logout')) {
            flash('warn', 'Please set a new password before you continue.');
            go('account/password');
        }
        return $next();
    }
}
