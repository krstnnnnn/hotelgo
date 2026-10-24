<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

/*
| QR code as an inline SVG picture (pure PHP, no internet needed).
| Load it with:  $this->call->library('qr');   then:  $this->qr->svg('text')
| Settings: app/config/qrcode.php (read with config_item).
| The generator itself is the third-party library app/libraries/qrcode.php
| (Kazuhiko Arase, MIT licence), loaded through LavaLust's own loader.
*/
class Qr
{
    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->config->load('qrcode');
        $this->_lava->call->library('qrcode');
    }

    public function svg(string $text, ?string $color = null): string
    {
        $levels = ['L' => QR_ERROR_CORRECT_LEVEL_L, 'M' => QR_ERROR_CORRECT_LEVEL_M, 'Q' => QR_ERROR_CORRECT_LEVEL_Q, 'H' => QR_ERROR_CORRECT_LEVEL_H];
        $level  = $levels[strtoupper((string) config_item('qr_error_level'))] ?? QR_ERROR_CORRECT_LEVEL_M;
        $color  = $color ?? (string) config_item('qr_color');
        $quiet  = max(0, (int) config_item('qr_quiet_zone'));

        $qr   = QRCode::getMinimumQRCode($text, $level);
        $n    = $qr->getModuleCount();
        $path = '';
        for ($r = 0; $r < $n; $r++) {
            $c = 0;
            while ($c < $n) {
                if ($qr->isDark($r, $c)) {
                    $start = $c;
                    while ($c < $n && $qr->isDark($r, $c)) {
                        $c++;
                    }
                    $path .= 'M' . $start . ' ' . $r . 'h' . ($c - $start) . 'v1h-' . ($c - $start) . 'z';
                } else {
                    $c++;
                }
            }
        }
        $size = $n + 2 * $quiet;
        return '<svg viewBox="-' . $quiet . ' -' . $quiet . ' ' . $size . ' ' . $size . '" role="img" aria-label="QR code for this booking" shape-rendering="crispEdges">'
            . '<path d="' . $path . '" fill="' . htmlspecialchars($color, ENT_QUOTES) . '"/></svg>';
    }
}
