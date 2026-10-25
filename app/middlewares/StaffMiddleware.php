<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Front-desk staff AND admins. Everyone else gets a plain 404 (the staff area is hidden). */
class StaffMiddleware
{
    public function handle(Closure $next)
    {
        if (!current_user()) { abort(404); }               // outsiders must not learn that a staff area exists
        if (!has_role('staff', 'admin')) { abort(404); }   // guests get the same 'not found' page
        if ((int) current_user()['must_change_password'] === 1) { go('account/password'); }
        return $next();
    }
}
