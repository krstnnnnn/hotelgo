<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| "Continue with Google" for guests. Talks to Google only: it never touches our database.
| Load it with:  $this->call->library('google_signin');   then:  $this->google_signin->verify($idToken)
|
| How it works (Google Identity Services):
|   1. The React page shows Google's button. When the guest picks an account, Google gives the page an ID token (a signed text).
|   2. The page sends that token to POST /api/guest/google. We never see the guest's Google password.
|   3. verify() asks Google (tokeninfo) whether the token is real and checks it was made for OUR app (GOOGLE_CLIENT_ID),
|      is not expired, and that Google confirmed the email address.
| Set up once: Google Cloud Console > APIs & Services > Credentials > Create credentials > OAuth client ID > Web application.
|   Authorised JavaScript origins: the address of the React site (e.g. https://hotelgo-web.onrender.com and http://localhost:5173).
|   Copy the Client ID into GOOGLE_CLIENT_ID (no secret is needed). Settings: app/config/integrations.php.
*/
class Google_signin
{
    public $error = '';
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->library('http_client');
    }

    public function client_id(): string { return trim((string) config_item('google_client_id')); }
    public function configured(): bool  { return $this->client_id() !== ''; }

    /* The guest's Google details [email, name, sub], or null (see $this->error) */
    public function verify(string $idToken): ?array
    {
        $this->error = '';
        if (!$this->configured()) { $this->error = 'Google sign-in is not set up.'; return null; }
        if ($idToken === '' || strlen($idToken) > 4096) { $this->error = 'The Google sign-in was not valid.'; return null; }
        [$http, $body, $err] = $this->_lava->http_client->request('GET', config_item('google_tokeninfo_url') . '?id_token=' . rawurlencode($idToken));
        if ($err !== '') { $this->error = 'Could not reach Google. Please try again.'; return null; }
        $j = json_decode($body, true);
        if ($http !== 200 || !is_array($j)) { $this->error = 'The Google sign-in was not valid. Please try again.'; return null; }
        $okIssuer = in_array($j['iss'] ?? '', ['accounts.google.com', 'https://accounts.google.com'], true);
        $verified = ($j['email_verified'] ?? false) === true || ($j['email_verified'] ?? '') === 'true';
        if (($j['aud'] ?? '') !== $this->client_id() || !$okIssuer || (int) ($j['exp'] ?? 0) < time() || empty($j['email']) || empty($j['sub'])) {
            $this->error = 'The Google sign-in was not valid. Please try again.'; return null;
        }
        if (!$verified) { $this->error = 'Google has not confirmed that email address.'; return null; }
        return ['email' => strtolower((string) $j['email']), 'name' => trim((string) ($j['name'] ?? '')), 'sub' => (string) $j['sub']];
    }
}
