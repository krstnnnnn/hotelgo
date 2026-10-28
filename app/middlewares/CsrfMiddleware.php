<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/* Runs on EVERY route (see routes.php). Blocks any POST that does not carry our secret token. */
class CsrfMiddleware
{
    public function handle(Closure $next)
    {
        // PayMongo's server calls this URL, so there is no browser token. It is protected by PayMongo's signature (WebhookController).
        if (str_ends_with(trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'), 'webhooks/paymongo')) return $next();
        // the JSON API (/api/...) is protected by a JWT bearer token instead of a cookie, so there is nothing for a forged form to ride on
        if (str_starts_with(trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/'), 'api/')) return $next();
        if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $sent  = $_POST['_csrf'] ?? '';
            $known = $_SESSION['_csrf'] ?? '';
            $ok = is_string($sent) && $known !== '' && hash_equals($known, $sent);

            // extra check: if the browser tells us where the form came from, it must be our own site
            $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
            if ($ok && $origin !== '') {
                $ok = parse_url($origin, PHP_URL_HOST) === explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];
            }
            if (!$ok) {
                abort(419, 'Your page expired or the request was not allowed. Go back, refresh the page and try again.');
            }
        }
        return $next();
    }
}
