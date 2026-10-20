<?php

declare(strict_types=1);

namespace Sibol\Services;

/**
 * QR codes for printed sign-in slips (ISO/IEC 18004), written here so no library is needed.
 * Byte mode at error-correction level M (about 15% of the symbol can be damaged), versions 1 to 40,
 * with the mask chosen by the standard's penalty rules. Rendered as an SVG with a 4-module quiet zone.
 */
final class Qr
{
    /** Level M: error-correction codewords per block and number of blocks, by version (index 0 unused). */
    private const ECC_PER_BLOCK = [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26,
        26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28];
    private const BLOCKS = [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16,
        17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49];
    /** Format bits for level M are 00. */
    private const ECL_BITS = 0;

    /** @var list<list<bool>> */
    private array $modules = [];
    /** @var list<list<bool>> */
    private array $isFunction = [];
    private int $size;
    private int $version;
    private int $mask = 0;

    private function __construct(int $version)
    {
        $this->version = $version;
        $this->size = $version * 4 + 17;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);
    }

    /** The smallest symbol that holds $text (UTF-8 bytes). */
    public static function encode(string $text): self
    {
        $bytes = array_values(unpack('C*', $text) ?: []);
        for ($version = 1; $version <= 40; $version++) {
            $capacityBits = self::dataCodewords($version) * 8;
            $needed = 4 + ($version <= 9 ? 8 : 16) + count($bytes) * 8;
            if ($needed <= $capacityBits) {
                break;
            }
        }
        if ($version > 40) {
            throw new \InvalidArgumentException('Text is too long for a QR code.');
        }

        // Segment: byte mode indicator, length, data; then terminator, byte alignment and pad bytes.
        $bits = [];
        self::appendBits($bits, 0x4, 4);
        self::appendBits($bits, count($bytes), $version <= 9 ? 8 : 16);
        foreach ($bytes as $b) {
            self::appendBits($bits, $b, 8);
        }
        $capacityBits = self::dataCodewords($version) * 8;
        self::appendBits($bits, 0, min(4, $capacityBits - count($bits)));
        self::appendBits($bits, 0, (8 - count($bits) % 8) % 8);
        for ($pad = 0xEC; count($bits) < $capacityBits; $pad ^= 0xEC ^ 0x11) {
            self::appendBits($bits, $pad, 8);
        }
        $data = [];
        foreach (array_chunk($bits, 8) as $byte) {
            $value = 0;
            foreach ($byte as $bit) {
                $value = ($value << 1) | $bit;
            }
            $data[] = $value;
        }

        $qr = new self($version);
        $qr->drawFunctionPatterns();
        $qr->drawCodewords(self::addEccAndInterleave($data, $version));
        $best = 0;
        $lowest = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $qr->applyMask($mask);
            $qr->drawFormatBits($mask);
            $penalty = $qr->penalty();
            if ($penalty < $lowest) {
                $best = $mask;
                $lowest = $penalty;
            }
            $qr->applyMask($mask); // XOR again to undo
        }
        $qr->mask = $best;
        $qr->applyMask($best);
        $qr->drawFormatBits($best);
        return $qr;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function mask(): int
    {
        return $this->mask;
    }

    /** True for a dark module; x is the column and y the row, from the top-left corner. */
    public function module(int $x, int $y): bool
    {
        return $x >= 0 && $y >= 0 && $x < $this->size && $y < $this->size && $this->modules[$y][$x];
    }

    /** Decorative SVG (the slip prints the link and code as text beside it). */
    public function svg(string $class = '', string $label = ''): string
    {
        $border = 4;
        $full = $this->size + $border * 2;
        $path = '';
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if ($this->modules[$y][$x]) {
                    $path .= 'M' . ($x + $border) . ',' . ($y + $border) . 'h1v1h-1z';
                }
            }
        }
        $a11y = $label === '' ? ' aria-hidden="true" focusable="false"' : ' role="img" aria-label="' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '"';
        return '<svg xmlns="http://www.w3.org/2000/svg" class="' . htmlspecialchars($class, ENT_QUOTES, 'UTF-8') . '" viewBox="0 0 ' . $full . ' ' . $full . '"'
            . ' shape-rendering="crispEdges"' . $a11y . '><rect width="100%" height="100%" fill="#FFFFFF"/><path d="' . $path . '" fill="#000000"/></svg>';
    }

    // ---------------------------------------------------------------- function patterns

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->setFunction(6, $i, $i % 2 === 0);
            $this->setFunction($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);
        $positions = $this->alignmentPositions();
        $count = count($positions);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 0; $j < $count; $j++) {
                // Not where a finder pattern already is.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $count - 1) || ($i === $count - 1 && $j === 0)) {
                    continue;
                }
                $this->drawAlignment($positions[$i], $positions[$j]);
            }
        }
        $this->drawFormatBits(0); // reserves the area; drawn again once the mask is known
        $this->drawVersion();
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $dist = max(abs($dx), abs($dy));
                    $this->setFunction($x, $y, $dist !== 2 && $dist !== 4);
                }
            }
        }
    }

    private function drawAlignment(int $cx, int $cy): void
    {
        for ($dy = -2; $dy <= 2; $dy++) {
            for ($dx = -2; $dx <= 2; $dx++) {
                $this->setFunction($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
            }
        }
    }

    /** @return list<int> */
    private function alignmentPositions(): array
    {
        if ($this->version === 1) {
            return [];
        }
        $count = intdiv($this->version, 7) + 2;
        $step = intdiv($this->version * 8 + $count * 3 + 5, $count * 4 - 4) * 2;
        $result = array_fill(0, $count, 0);
        $result[0] = 6;
        for ($i = $count - 1, $pos = $this->size - 7; $i >= 1; $i--, $pos -= $step) {
            $result[$i] = $pos;
        }
        return $result;
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::ECL_BITS << 3) | $mask;
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $bits = (($data << 10) | $rem) ^ 0x5412;

        for ($i = 0; $i <= 5; $i++) {
            $this->setFunction(8, $i, self::bit($bits, $i));
        }
        $this->setFunction(8, 7, self::bit($bits, 6));
        $this->setFunction(8, 8, self::bit($bits, 7));
        $this->setFunction(7, 8, self::bit($bits, 8));
        for ($i = 9; $i < 15; $i++) {
            $this->setFunction(14 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->setFunction($this->size - 1 - $i, 8, self::bit($bits, $i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->setFunction(8, $this->size - 15 + $i, self::bit($bits, $i));
        }
        $this->setFunction(8, $this->size - 8, true); // always dark
    }

    private function drawVersion(): void
    {
        if ($this->version < 7) {
            return;
        }
        $rem = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $rem;
        for ($i = 0; $i < 18; $i++) {
            $bit = self::bit($bits, $i);
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->setFunction($a, $b, $bit);
            $this->setFunction($b, $a, $bit);
        }
    }

    private function setFunction(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    // ---------------------------------------------------------------- data and masks

    /** @param list<int> $codewords */
    private function drawCodewords(array $codewords): void
    {
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5; // skip the vertical timing column
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = self::bit($codewords[$i >> 3], 7 - ($i & 7));
                        $i++;
                    }
                }
            }
        }
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                switch ($mask) {
                    case 0: $invert = ($x + $y) % 2 === 0; break;
                    case 1: $invert = $y % 2 === 0; break;
                    case 2: $invert = $x % 3 === 0; break;
                    case 3: $invert = ($x + $y) % 3 === 0; break;
                    case 4: $invert = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $invert = $x * $y % 2 + $x * $y % 3 === 0; break;
                    case 6: $invert = ($x * $y % 2 + $x * $y % 3) % 2 === 0; break;
                    default: $invert = (($x + $y) % 2 + $x * $y % 3) % 2 === 0;
                }
                if ($invert && !$this->isFunction[$y][$x]) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    /** The standard's penalty score; the mask with the lowest score is used. */
    private function penalty(): int
    {
        $result = 0;
        $size = $this->size;
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $size; $a++) {
                $runColor = false;
                $run = 0;
                $history = array_fill(0, 7, 0);
                for ($b = 0; $b < $size; $b++) {
                    $color = $pass === 0 ? $this->modules[$a][$b] : $this->modules[$b][$a];
                    if ($color === $runColor) {
                        $run++;
                        if ($run === 5) {
                            $result += 3;
                        } elseif ($run > 5) {
                            $result++;
                        }
                    } else {
                        $this->addHistory($run, $history);
                        if (!$runColor) {
                            $result += $this->finderLike($history) * 40;
                        }
                        $runColor = $color;
                        $run = 1;
                    }
                }
                if ($runColor) {
                    $this->addHistory($run, $history);
                    $run = 0;
                }
                $this->addHistory($run + $size, $history);
                $result += $this->finderLike($history) * 40;
            }
        }
        for ($y = 0; $y < $size - 1; $y++) {
            for ($x = 0; $x < $size - 1; $x++) {
                $c = $this->modules[$y][$x];
                if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                    $result += 3;
                }
            }
        }
        $dark = 0;
        foreach ($this->modules as $row) {
            foreach ($row as $m) {
                $dark += $m ? 1 : 0;
            }
        }
        $total = $size * $size;
        $k = intdiv(abs($dark * 20 - $total * 10) + $total - 1, $total) - 1;
        return $result + $k * 10;
    }

    /** @param list<int> $history */
    private function addHistory(int $run, array &$history): void
    {
        if ($history[0] === 0) {
            $run += $this->size; // light border before the first run
        }
        array_pop($history);
        array_unshift($history, $run);
    }

    /** @param list<int> $history */
    private function finderLike(array $history): int
    {
        $n = $history[1];
        $core = $n > 0 && $history[2] === $n && $history[3] === $n * 3 && $history[4] === $n && $history[5] === $n;
        return ($core && $history[0] >= $n * 4 && $history[6] >= $n ? 1 : 0)
            + ($core && $history[6] >= $n * 4 && $history[0] >= $n ? 1 : 0);
    }

    // ---------------------------------------------------------------- error correction

    /** Modules available for data and error correction (everything but function patterns). */
    private static function rawModules(int $version): int
    {
        $result = (16 * $version + 128) * $version + 64;
        if ($version >= 2) {
            $align = intdiv($version, 7) + 2;
            $result -= (25 * $align - 10) * $align - 55;
            if ($version >= 7) {
                $result -= 36;
            }
        }
        return $result;
    }

    public static function dataCodewords(int $version): int
    {
        return intdiv(self::rawModules($version), 8) - self::ECC_PER_BLOCK[$version] * self::BLOCKS[$version];
    }

    /**
     * Splits the data into blocks, adds Reed-Solomon codewords to each and interleaves them.
     * @param list<int> $data
     * @return list<int>
     */
    private static function addEccAndInterleave(array $data, int $version): array
    {
        $blocks = self::BLOCKS[$version];
        $eccLen = self::ECC_PER_BLOCK[$version];
        $raw = intdiv(self::rawModules($version), 8);
        $shortBlocks = $blocks - $raw % $blocks;
        $shortLen = intdiv($raw, $blocks);
        $divisor = self::rsDivisor($eccLen);
        $all = [];
        for ($i = 0, $k = 0; $i < $blocks; $i++) {
            $len = $shortLen - $eccLen + ($i < $shortBlocks ? 0 : 1);
            $dat = array_slice($data, $k, $len);
            $k += $len;
            $block = array_pad($dat, $shortLen + 1 - $eccLen, 0); // short blocks get a placeholder byte
            $all[] = array_merge($block, self::rsRemainder($dat, $divisor));
        }
        $result = [];
        for ($i = 0; $i < $shortLen + 1; $i++) {
            for ($j = 0; $j < $blocks; $j++) {
                if ($i !== $shortLen - $eccLen || $j >= $shortBlocks) {
                    $result[] = $all[$j][$i];
                }
            }
        }
        return $result;
    }

    /** @return list<int> generator polynomial coefficients, highest power first (leading 1 omitted) */
    public static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree, 0);
        $result[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $result[$j] = self::gfMultiply($result[$j], $root);
                if ($j + 1 < $degree) {
                    $result[$j] ^= $result[$j + 1];
                }
            }
            $root = self::gfMultiply($root, 0x02);
        }
        return $result;
    }

    /**
     * @param list<int> $data
     * @param list<int> $divisor
     * @return list<int>
     */
    public static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $b) {
            $factor = $b ^ $result[0];
            array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= self::gfMultiply($coefficient, $factor);
            }
        }
        return $result;
    }

    /** Multiplication in GF(2^8) modulo x^8 + x^4 + x^3 + x^2 + 1. */
    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = ($z << 1) ^ (($z >> 7) * 0x11D);
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    /** @param list<int> $bits */
    private static function appendBits(array &$bits, int $value, int $length): void
    {
        for ($i = $length - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }

    private static function bit(int $value, int $i): bool
    {
        return (($value >> $i) & 1) !== 0;
    }
}
