<?php

namespace App\Support;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\HtmlString;

class TenantCodeQr
{
    public static function dataUri(string $code): string
    {
        $options = new QROptions([
            'outputBase64' => true,
            'scale' => 6,
        ]);

        return (new QRCode($options))->render($code);
    }

    public static function image(string $code): HtmlString
    {
        return new HtmlString(
            '<img src="'.e(self::dataUri($code)).'" alt="QR code for tenant code '.e($code).'" width="168" height="168" style="background:#fff;padding:8px;border-radius:8px" />'
        );
    }
}
