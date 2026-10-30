<?php

namespace Giuliomax\Passport;

defined('ABSPATH') || exit;
final class Qr
{
    public static function matrix(string $url): array
    {
        if (strlen($url) > 500) {
            throw new \InvalidArgumentException(__('The address is too long for a readable label. Use a shorter resolver domain.', 'giuliomax-product-passport')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Returned as structured data; HTML callers escape the message.
        }
        require_once GMPP_DIR . 'vendor/qrcode.php';
        $qr = new Vendor\QRCode();
        $qr->setErrorCorrectLevel(GMPP_QR_ERROR_CORRECT_LEVEL_M);
        $qr->addData($url, GMPP_QR_MODE_8BIT_BYTE);
        for ($version = 1; $version <= 40; $version++) {
            $capacity = 0;
            foreach (Vendor\QRRSBlock::getRSBlocks($version, GMPP_QR_ERROR_CORRECT_LEVEL_M) as $block) {
                $capacity += $block->dataCount;
            }
            if (4 + ($version < 10 ? 8 : 16) + strlen($url) * 8 <= $capacity * 8) {
                $qr->setTypeNumber($version);
                break;
            }
        }
        $qr->make();
        $rows = [];
        $size = $qr->getModuleCount();
        for ($y = 0; $y < $size; $y++) {
            $row = []; for ($x = 0; $x < $size; $x++) {
                $row[] = $qr->isDark($y, $x);
            } $rows[] = $row;
        }
        return $rows;
    }
    public static function svg(string $url): string
    {
        $matrix = self::matrix($url);
        $size = count($matrix) + 8;
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + 4) . ',' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="QR code" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="' . $path . '" fill="#000"/></svg>';
    }
    public static function png(string $url): string
    {
        $matrix = self::matrix($url);
        $n = count($matrix);
        $scale = 8;
        $size = ($n + 8) * $scale;
        $raw = '';
        for ($y = 0; $y < $size; $y++) {
            $line = "\x00";
            for ($x = 0; $x < $size; $x++) {
                $mx = intdiv($x, $scale) - 4;
                $my = intdiv($y, $scale) - 4;
                $line .= ($mx >= 0 && $my >= 0 && $mx < $n && $my < $n && $matrix[$my][$mx]) ? "\x00" : "\xFF";
            }
            $raw .= $line;
        }
        $chunk = static fn($type, $data)=>pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
        return "\x89PNG\r\n\x1a\n" . $chunk('IHDR', pack('NNCCCCC', $size, $size, 8, 0, 0, 0, 0)) . $chunk('IDAT', gzcompress($raw, 9)) . $chunk('IEND', '');
    }
}
