<?php namespace Mercator\Uploader\Classes;

class QrCode
{
    public static function pngData($url)
    {
        // Simple external QR generator (Google Charts)
        return @file_get_contents("https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=" . urlencode($url));
    }
}