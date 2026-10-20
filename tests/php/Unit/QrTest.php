<?php

declare(strict_types=1);

namespace Sibol\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sibol\Services\Qr;

/**
 * The QR encoder is checked against published values from the standard and its worked examples,
 * and every symbol is read back by an independent decoder written from the standard's tables
 * (format bits, function patterns, zigzag order, block structure and Reed-Solomon syndromes).
 */
final class QrTest extends TestCase
{
    /** ISO/IEC 18004 format information for error-correction level M, masks 0 to 7. */
    private const FORMAT_M = [
        '101010000010010', '101000100100101', '101111001111100', '101101101001011',
        '100010111111001', '100000011001110', '100111110010111', '100101010100000',
    ];
    /** Alignment pattern centres by version (Annex E). */
    private const ALIGN = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    /** Level M blocks by version (Table 9): [error-correction codewords per block, [data codewords per block...]]. */
    private const BLOCKS_M = [1 => [10, [16]], 2 => [16, [28]], 3 => [26, [44]], 4 => [18, [32, 32]], 5 => [24, [43, 43]],
        6 => [16, [27, 27, 27, 27]], 7 => [18, [31, 31, 31, 31]], 8 => [22, [38, 38, 39, 39]], 9 => [22, [36, 36, 36, 37, 37]],
        10 => [26, [43, 43, 43, 43, 44]]];

    public function testReedSolomonMatchesPublishedExamples(): void
    {
        // "HELLO WORLD" at 1-Q, a widely used worked example.
        self::assertSame(
            [168, 72, 22, 82, 217, 54, 156, 0, 46, 15, 180, 122, 16],
            Qr::rsRemainder([32, 91, 11, 120, 209, 114, 220, 77, 67, 64, 236, 17, 236], Qr::rsDivisor(13))
        );
        // "01234567" at 1-M, the example in Annex I of the standard.
        self::assertSame(
            [0xA5, 0x24, 0xD4, 0xC1, 0xED, 0x36, 0xC7, 0x87, 0x2C, 0x55],
            Qr::rsRemainder([0x10, 0x20, 0x0C, 0x56, 0x61, 0x80, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11, 0xEC, 0x11], Qr::rsDivisor(10))
        );
    }

    public function testDataCapacityAtLevelM(): void
    {
        $expected = [1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86, 6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216, 40 => 2334];
        foreach ($expected as $version => $codewords) {
            self::assertSame($codewords, Qr::dataCodewords($version), "version $version");
        }
    }

    public function testSlipLinksDecodeBackToTheSameText(): void
    {
        $texts = [
            'https://sibol.example.edu.ph/invite/ABCDEFGHJKMN',
            'http://localhost/BEST/public/reset/Z7Q2-HK9M-PX4R',
            'http://localhost/BEST/public/invite/' . str_repeat('aB3-dE5_fG7hJ9kL2mN4pQ6rS8tU0vW1xY2zA3bC4dE5', 1),
            'Sibol',
            str_repeat('x', 150),
        ];
        foreach ($texts as $text) {
            $qr = Qr::encode($text);
            self::assertLessThanOrEqual(10, $qr->version(), 'slip links fit a small symbol');
            self::assertSame($text, $this->decode($qr), 'decoded ' . $text);
        }
    }

    public function testEveryMaskProducesAReadableSymbol(): void
    {
        // Different texts land on different masks; collect several and decode them all.
        $masks = [];
        for ($i = 0; $i < 60 && count($masks) < 8; $i++) {
            $text = 'http://localhost/BEST/public/invite/' . strtoupper(substr(sha1((string) $i), 0, 12));
            $qr = Qr::encode($text);
            self::assertSame($text, $this->decode($qr));
            $masks[$qr->mask()] = true;
        }
        self::assertGreaterThanOrEqual(4, count($masks), 'several masks were exercised');
    }

    public function testVersionInformationForLargerSymbols(): void
    {
        $qr = Qr::encode(str_repeat('Sibol ', 25)); // 150 bytes: version 7 or more at level M
        self::assertGreaterThanOrEqual(7, $qr->version());
        $bits = 0;
        for ($i = 17; $i >= 0; $i--) {
            $bits = ($bits << 1) | ($qr->module($qr->size() - 11 + $i % 3, intdiv($i, 3)) ? 1 : 0);
        }
        $expected = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];
        self::assertSame($expected[$qr->version()], $bits, 'version information (Annex D)');
        self::assertSame(str_repeat('Sibol ', 25), $this->decode($qr));
    }

    public function testSvgHasAQuietZoneAndOnlyBlackModules(): void
    {
        $svg = Qr::encode('Sibol')->svg('slip__qr');
        self::assertStringContainsString('viewBox="0 0 29 29"', $svg, 'version 1 is 21 modules plus 4 on each side');
        self::assertStringContainsString('aria-hidden="true"', $svg);
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg"', $svg);
    }

    // ---------------------------------------------------------------- independent decoder

    private function decode(Qr $qr): string
    {
        $size = $qr->size();
        $version = intdiv($size - 17, 4);
        self::assertArrayHasKey($version, self::BLOCKS_M, 'decoder covers versions 1 to 10');

        // Format information around the top-left finder, most significant bit first.
        $cells = [[0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8], [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0]];
        $format = '';
        foreach ($cells as [$x, $y]) {
            $format .= $qr->module($x, $y) ? '1' : '0';
        }
        $mask = array_search($format, self::FORMAT_M, true);
        self::assertNotFalse($mask, 'format bits are a valid level M code: ' . $format);
        // The second copy (bottom-left column and top-right row) carries the same bits.
        $second = '';
        for ($i = 0; $i < 7; $i++) {
            $second .= $qr->module(8, $size - 1 - $i) ? '1' : '0';
        }
        for ($i = 0; $i < 8; $i++) {
            $second .= $qr->module($size - 8 + $i, 8) ? '1' : '0';
        }
        self::assertSame($format, $second, 'both copies of the format information agree');
        self::assertTrue($qr->module(8, $size - 8), 'the dark module');
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$fx, $fy]) {
            self::assertTrue($qr->module($fx, $fy) && !$qr->module($fx + 2, $fy) && $qr->module($fx + 3, $fy), 'finder pattern');
        }

        $reserved = $this->reserved($size, $version);
        $bits = [];
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            $upward = ((intdiv($size - 1 - $right, 2)) % 2) === 0; // the first column pair goes up
            for ($i = 0; $i < $size; $i++) {
                $y = $upward ? $size - 1 - $i : $i;
                foreach ([$right, $right - 1] as $x) {
                    if (!isset($reserved[$y][$x])) {
                        $bits[] = ($qr->module($x, $y) xor $this->maskBit((int) $mask, $x, $y)) ? 1 : 0;
                    }
                }
            }
        }
        $codewords = [];
        foreach (array_chunk($bits, 8) as $chunk) {
            if (count($chunk) === 8) {
                $codewords[] = (int) bindec(implode('', $chunk));
            }
        }

        [$eccLen, $dataLens] = self::BLOCKS_M[$version];
        $blocks = count($dataLens);
        $total = array_sum($dataLens) + $eccLen * $blocks;
        self::assertGreaterThanOrEqual($total, count($codewords));
        $data = array_fill(0, $blocks, []);
        $ecc = array_fill(0, $blocks, []);
        $k = 0;
        for ($i = 0; $i < max($dataLens); $i++) {
            for ($b = 0; $b < $blocks; $b++) {
                if ($i < $dataLens[$b]) {
                    $data[$b][] = $codewords[$k++];
                }
            }
        }
        for ($i = 0; $i < $eccLen; $i++) {
            for ($b = 0; $b < $blocks; $b++) {
                $ecc[$b][] = $codewords[$k++];
            }
        }
        $stream = [];
        for ($b = 0; $b < $blocks; $b++) {
            self::assertTrue($this->syndromesAreZero(array_merge($data[$b], $ecc[$b]), $eccLen), "block $b passes the Reed-Solomon check");
            $stream = array_merge($stream, $data[$b]);
        }

        $bitString = implode('', array_map(static fn ($c) => str_pad(decbin($c), 8, '0', STR_PAD_LEFT), $stream));
        self::assertSame('0100', substr($bitString, 0, 4), 'byte mode');
        $countBits = $version <= 9 ? 8 : 16;
        $length = (int) bindec(substr($bitString, 4, $countBits));
        $text = '';
        for ($i = 0; $i < $length; $i++) {
            $text .= chr((int) bindec(substr($bitString, 4 + $countBits + $i * 8, 8)));
        }
        return $text;
    }

    /** @return array<int,array<int,bool>> modules that hold patterns, format or version information */
    private function reserved(int $size, int $version): array
    {
        $r = [];
        $mark = static function (int $x0, int $y0, int $w, int $h) use (&$r, $size): void {
            for ($y = $y0; $y < $y0 + $h; $y++) {
                for ($x = $x0; $x < $x0 + $w; $x++) {
                    if ($x >= 0 && $y >= 0 && $x < $size && $y < $size) {
                        $r[$y][$x] = true;
                    }
                }
            }
        };
        $mark(0, 0, 9, 9);              // top-left finder, separator and format bits
        $mark($size - 8, 0, 8, 9);      // top-right
        $mark(0, $size - 8, 9, 8);      // bottom-left (with the dark module)
        $mark(6, 0, 1, $size);          // timing column
        $mark(0, 6, $size, 1);          // timing row
        $centres = self::ALIGN[$version];
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                $nearFinder = ($cx < 9 && $cy < 9) || ($cx > $size - 9 && $cy < 9) || ($cx < 9 && $cy > $size - 9);
                if (!$nearFinder) {
                    $mark($cx - 2, $cy - 2, 5, 5);
                }
            }
        }
        if ($version >= 7) {
            $mark($size - 11, 0, 3, 6);
            $mark(0, $size - 11, 6, 3);
        }
        return $r;
    }

    private function maskBit(int $mask, int $x, int $y): bool
    {
        // Annex: i = row, j = column.
        $i = $y;
        $j = $x;
        switch ($mask) {
            case 0: return ($i + $j) % 2 === 0;
            case 1: return $i % 2 === 0;
            case 2: return $j % 3 === 0;
            case 3: return ($i + $j) % 3 === 0;
            case 4: return (intdiv($i, 2) + intdiv($j, 3)) % 2 === 0;
            case 5: return ($i * $j) % 2 + ($i * $j) % 3 === 0;
            case 6: return ((($i * $j) % 2) + (($i * $j) % 3)) % 2 === 0;
            default: return ((($i + $j) % 2) + (($i * $j) % 3)) % 2 === 0;
        }
    }

    /** @param list<int> $codeword data then error-correction codewords, highest degree first */
    private function syndromesAreZero(array $codeword, int $eccLen): bool
    {
        $exp = [];
        $log = [];
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;
            }
        }
        $n = count($codeword);
        for ($s = 0; $s < $eccLen; $s++) {
            $sum = 0;
            foreach ($codeword as $j => $c) {
                if ($c !== 0) {
                    $sum ^= $exp[($log[$c] + $s * ($n - 1 - $j)) % 255];
                }
            }
            if ($sum !== 0) {
                return false;
            }
        }
        return true;
    }
}
