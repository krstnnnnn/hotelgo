<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Front-desk staff AND admins. Guests get a 403 error. */
class StaffMiddleware
{
    public function handle(Closure $next)
    {
        if (!current_user()) { flash('warn', 'Please sign in first.'); go('admin/login'); }
        if (!has_role('staff', 'admin')) { abort(403, 'You do not have permission to open this page.'); }
        if ((int) current_user()['must_change_password'] === 1) { go('account/password'); }
        return $next();
    }
}
