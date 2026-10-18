<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Only people who are NOT logged in (login / register pages). */
class GuestMiddleware
{
    public function handle(Closure $next)
    {
        if (current_user()) {
            go(has_role('staff', 'admin') ? 'admin' : 'my-bookings');
        }
        return $next();
    }
}
