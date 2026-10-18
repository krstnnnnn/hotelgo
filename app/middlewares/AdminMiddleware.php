<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Admin (owner) only. Staff and guests get a 403 error. */
class AdminMiddleware
{
    public function handle(Closure $next)
    {
        if (!current_user()) { flash('warn', 'Please sign in first.'); go('admin/login'); }
        if (!has_role('admin')) { abort(403, 'Only the administrator can open this page.'); }
        if ((int) current_user()['must_change_password'] === 1) { go('account/password'); }
        return $next();
    }
}
