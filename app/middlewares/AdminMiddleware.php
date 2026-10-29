<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Admin (owner) only. Everyone else gets a plain 404 (the owner area is hidden). */
class AdminMiddleware
{
    public function handle(Closure $next)
    {
        if (!current_user()) { abort(404); }               // outsiders must not learn that a staff area exists
        if (!has_role('admin')) { abort(404); }   // guests get the same 'not found' page
        if (console_door() !== '') { header('Location: ' . console_door(), true, 302); exit; }   // the new staff console lives in the React app
        \Hotelgo\Support\Scope::set(current_user());     // hotel staff only see their own hotel
        if ((int) current_user()['must_change_password'] === 1) { go('account/password'); }
        return $next();
    }
}
