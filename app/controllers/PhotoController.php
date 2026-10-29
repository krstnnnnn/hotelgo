<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| The public address of a picture: GET /photo/{id}. Not part of the JSON API on purpose: the API sends
| "no-store" and "same-origin" headers, but a picture must be cacheable and loadable by the React website.
| A saved picture never changes (a new upload gets a new id), so browsers may keep it for a year.
*/
class PhotoController extends Controller
{
    public function show($id)
    {
        $this->call->model('PhotoModel');
        $f = $this->PhotoModel->file((int) $id);
        if (!$f) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            exit('Not found');
        }
        header_remove('Content-Security-Policy');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox");
        header('Content-Type: ' . $f[0]);
        header('Content-Length: ' . strlen($f[1]));
        header('Cache-Control: public, max-age=31536000, immutable');
        header('Cross-Origin-Resource-Policy: cross-origin');
        header('X-Content-Type-Options: nosniff');
        echo $f[1];
        exit;
    }
}
