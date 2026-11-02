<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| Plain HTTP client used by the payment, SMS and email providers.
| Load it with:  $this->call->library('http_client');   then:  $this->http_client->request(...)
*/
class Http_client
{
    /** Returns [http code, body text, error text]. */
    public function request(string $method, string $url, array $headers = [], ?string $body = null, int $timeout = 15, bool $follow = false): array
    {
        if (!function_exists('curl_init')) {
            return [0, '', 'The PHP cURL extension is not installed on this server.'];
        }
        $ch  = curl_init($url);
        $opt = [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => $follow,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_USERAGENT      => 'HOTELGO/1.0',
        ];
        if ($follow) {
            $opt[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;          // only follow redirects to https
        }
        if ($method === 'POST') {
            $opt[CURLOPT_POST] = true;                                // lets a redirect turn into a GET (Google Apps Script needs this)
        } else {
            $opt[CURLOPT_CUSTOMREQUEST] = $method;
        }
        if ($body !== null) {
            $opt[CURLOPT_POSTFIELDS] = $body;
        }
        $ca = (string) config_item('curl_ca_bundle');                 // only needed on some Windows setups
        if ($ca !== '' && is_file($ca)) {
            $opt[CURLOPT_CAINFO] = $ca;
        }
        curl_setopt_array($ch, $opt);
        $res  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        return [$code, (string) $res, $err];
    }
}
