<?php

declare(strict_types=1);

namespace Sibol\Core;

/**
 * Removes embedded metadata (EXIF, GPS location, comments, text chunks) from JPEG and PNG
 * files without needing the GD extension. Used for profile photos, which may show minors.
 */
final class ImageSanitizer
{
    public static function clean(string $path, string $ext): bool
    {
        $data = (string) file_get_contents($path);
        $clean = $ext === 'png' ? self::png($data) : self::jpeg($data);
        if ($clean === null) {
            return false;
        }
        return file_put_contents($path, $clean) !== false;
    }

    /** Keeps image data; drops APP1–APP15 (EXIF, XMP, ICC kept as APP2 is dropped too) and COM segments. */
    private static function jpeg(string $data): ?string
    {
        if (substr($data, 0, 2) !== "\xFF\xD8") {
            return null;
        }
        $out = "\xFF\xD8";
        $pos = 2;
        $len = strlen($data);
        while ($pos + 4 <= $len) {
            if ($data[$pos] !== "\xFF") {
                return null;
            }
            $marker = ord($data[$pos + 1]);
            if ($marker === 0xDA) { // start of scan: copy the rest verbatim
                return $out . substr($data, $pos);
            }
            $segLen = (ord($data[$pos + 2]) << 8) | ord($data[$pos + 3]);
            if ($segLen < 2 || $pos + 2 + $segLen > $len) {
                return null;
            }
            $isMetadata = ($marker >= 0xE1 && $marker <= 0xEF) || $marker === 0xFE;
            if (!$isMetadata) {
                $out .= substr($data, $pos, 2 + $segLen);
            }
            $pos += 2 + $segLen;
        }
        return null;
    }

    /** Keeps critical and rendering chunks; drops tEXt, zTXt, iTXt, eXIf, tIME. */
    private static function png(string $data): ?string
    {
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }
        $out = substr($data, 0, 8);
        $pos = 8;
        $len = strlen($data);
        while ($pos + 12 <= $len) {
            $chunkLen = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $total = 12 + $chunkLen;
            if ($pos + $total > $len) {
                return null;
            }
            if (!in_array($type, ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'tIME'], true)) {
                $out .= substr($data, $pos, $total);
            }
            $pos += $total;
            if ($type === 'IEND') {
                return $out;
            }
        }
        return null;
    }
}
