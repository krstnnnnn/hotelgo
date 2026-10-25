<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Admin (owner) only. Everyone else gets a plain 404 (the owner area is hidden). */
class AdminMiddleware
{
    public function handle(Closure $next)
    {
        if (!current_user()) { abort(404); }               // outsiders must not learn that a staff area exists
        if (!has_role('admin')) { abort(404); }   // guests get the same 'not found' page
        if ((int) current_user()['must_change_password'] === 1) { go('account/password'); }
        return $next();
    }
}
