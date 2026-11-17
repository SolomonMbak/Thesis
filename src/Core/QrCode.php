<?php
declare(strict_types=1);

namespace App\Core;

/**
 * A small QR code encoder (ISO/IEC 18004) for the two-step sign-in set-up
 * link, drawn as inline SVG. The app may not load outside scripts or
 * libraries, so it is written here.
 *
 * Supports what that link needs: byte mode, error correction level M,
 * versions 1 to 10 (up to 213 bytes). The steps follow the standard:
 * encode the data, add Reed-Solomon error correction, place the modules,
 * try the eight masks and keep the one with the lowest penalty.
 */
final class QrCode
{
    /** Per version (level M): [error correction codewords per block, [data codewords per block, ...]]. */
    private const BLOCKS = [
        1 => [10, [16]], 2 => [16, [28]], 3 => [26, [44]], 4 => [18, [32, 32]], 5 => [24, [43, 43]],
        6 => [16, [27, 27, 27, 27]], 7 => [18, [31, 31, 31, 31]], 8 => [22, [38, 38, 39, 39]],
        9 => [22, [36, 36, 36, 37, 37]], 10 => [26, [43, 43, 43, 43, 44]],
    ];
    private const ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];
    private const LEVEL_M = 0;

    private int $version;
    private int $size;
    /** @var array<int, array<int, bool>> [y][x], true = dark */
    private array $modules = [];
    /** @var array<int, array<int, bool>> [y][x], true = part of a fixed pattern (not data) */
    private array $isFunction = [];

    private function __construct(string $data)
    {
        $this->version = self::chooseVersion(strlen($data));
        $this->size = 17 + 4 * $this->version;
        $row = array_fill(0, $this->size, false);
        $this->modules = array_fill(0, $this->size, $row);
        $this->isFunction = array_fill(0, $this->size, $row);

        $this->drawFunctionPatterns();
        $this->drawCodewords($this->codewords($data));
        $this->applyBestMask();
    }

    /** The module grid: rows of booleans, true = dark. */
    public static function matrix(string $data): array
    {
        return (new self($data))->modules;
    }

    /** An SVG image of the code with a quiet zone of 4 modules, scaled by CSS. */
    public static function svg(string $data, string $label): string
    {
        $qr = new self($data);
        $path = '';
        foreach ($qr->modules as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }
        $box = $qr->size + 8;
        // Always black on white, also in dark mode: phone cameras read that best.
        return '<svg class="qr" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $box . ' ' . $box . '" role="img" aria-label="'
            . htmlspecialchars($label, ENT_QUOTES) . '" shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/>'
            . '<path fill="#000" d="' . $path . '"/></svg>';
    }

    private static function chooseVersion(int $length): int
    {
        foreach (self::BLOCKS as $version => [, $blocks]) {
            $countBits = $version < 10 ? 8 : 16;
            if (4 + $countBits + 8 * $length <= 8 * array_sum($blocks)) {
                return $version;
            }
        }
        throw new \InvalidArgumentException('The text is too long for a QR code of version 10.');
    }

    // ---- Data and error correction ----

    /** @return int[] the final codewords: data blocks interleaved, then error correction blocks interleaved */
    private function codewords(string $data): array
    {
        [$eccLength, $blockSizes] = self::BLOCKS[$this->version];
        $capacity = array_sum($blockSizes);
        $bits = '0100' . str_pad(decbin(strlen($data)), $this->version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $bytes = array_map('bindec', str_split($bits, 8));
        for ($pad = 0xEC; count($bytes) < $capacity; $pad ^= 0xEC ^ 0x11) {
            $bytes[] = $pad;
        }

        $divisor = self::rsDivisor($eccLength);
        $dataBlocks = $eccBlocks = [];
        $offset = 0;
        foreach ($blockSizes as $length) {
            $block = array_slice($bytes, $offset, $length);
            $offset += $length;
            $dataBlocks[] = $block;
            $eccBlocks[] = self::rsRemainder($block, $divisor);
        }
        $out = [];
        for ($i = 0; $i < max($blockSizes); $i++) {
            foreach ($dataBlocks as $block) {
                if ($i < count($block)) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $eccLength; $i++) {
            foreach ($eccBlocks as $block) {
                $out[] = $block[$i];
            }
        }
        return $out;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            $z ^= (($y >> $i) & 1) * $x;
        }
        return $z;
    }

    private static function rsDivisor(int $degree): array
    {
        $result = array_fill(0, $degree - 1, 0);
        $result[] = 1;
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

    private static function rsRemainder(array $data, array $divisor): array
    {
        $result = array_fill(0, count($divisor), 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($divisor as $i => $coefficient) {
                $result[$i] ^= self::gfMultiply($coefficient, $factor);
            }
        }
        return $result;
    }

    // ---- Module placement ----

    private function set(int $x, int $y, bool $dark): void
    {
        $this->modules[$y][$x] = $dark;
        $this->isFunction[$y][$x] = true;
    }

    private function drawFunctionPatterns(): void
    {
        for ($i = 0; $i < $this->size; $i++) {
            $this->set(6, $i, $i % 2 === 0);
            $this->set($i, 6, $i % 2 === 0);
        }
        $this->drawFinder(3, 3);
        $this->drawFinder($this->size - 4, 3);
        $this->drawFinder(3, $this->size - 4);

        $positions = self::ALIGNMENT[$this->version];
        $last = count($positions) - 1;
        foreach ($positions as $i => $px) {
            foreach ($positions as $j => $py) {
                // Not on top of the three finder patterns.
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $this->set($px + $dx, $py + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        $this->drawFormatBits(0);   // reserves the format areas; redrawn with the chosen mask
        $this->drawVersionBits();
    }

    private function drawFinder(int $cx, int $cy): void
    {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $this->size && $y >= 0 && $y < $this->size) {
                    $distance = max(abs($dx), abs($dy));
                    $this->set($x, $y, $distance !== 2 && $distance !== 4);
                }
            }
        }
    }

    private function drawFormatBits(int $mask): void
    {
        $data = (self::LEVEL_M << 3) | $mask;
        $remainder = $data;
        for ($i = 0; $i < 10; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 9) * 0x537);
        }
        $bits = (($data << 10) | $remainder) ^ 0x5412;
        $bit = fn (int $i): bool => (($bits >> $i) & 1) === 1;

        for ($i = 0; $i <= 5; $i++) {
            $this->set(8, $i, $bit($i));
        }
        $this->set(8, 7, $bit(6));
        $this->set(8, 8, $bit(7));
        $this->set(7, 8, $bit(8));
        for ($i = 9; $i < 15; $i++) {
            $this->set(14 - $i, 8, $bit($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->set($this->size - 1 - $i, 8, $bit($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->set(8, $this->size - 15 + $i, $bit($i));
        }
        $this->set(8, $this->size - 8, true);   // the dark module
    }

    private function drawVersionBits(): void
    {
        if ($this->version < 7) {
            return;
        }
        $remainder = $this->version;
        for ($i = 0; $i < 12; $i++) {
            $remainder = ($remainder << 1) ^ (($remainder >> 11) * 0x1F25);
        }
        $bits = ($this->version << 12) | $remainder;
        for ($i = 0; $i < 18; $i++) {
            $dark = (($bits >> $i) & 1) === 1;
            $a = $this->size - 11 + $i % 3;
            $b = intdiv($i, 3);
            $this->set($a, $b, $dark);
            $this->set($b, $a, $dark);
        }
    }

    /** Fill the data modules in the zigzag order of the standard: pairs of columns, right to left, alternating up and down. */
    private function drawCodewords(array $codewords): void
    {
        $total = count($codewords) * 8;
        $i = 0;
        for ($right = $this->size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;   // skip the vertical timing pattern
            }
            for ($vert = 0; $vert < $this->size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $upward = (($right + 1) & 2) === 0;
                    $y = $upward ? $this->size - 1 - $vert : $vert;
                    if (!$this->isFunction[$y][$x] && $i < $total) {
                        $this->modules[$y][$x] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    // ---- Masks ----

    private static function maskHit(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
            5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
            6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
            default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
        };
    }

    private function applyMask(int $mask): void
    {
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                if (!$this->isFunction[$y][$x] && self::maskHit($mask, $x, $y)) {
                    $this->modules[$y][$x] = !$this->modules[$y][$x];
                }
            }
        }
    }

    private function applyBestMask(): void
    {
        $best = 0;
        $bestPenalty = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $this->applyMask($mask);
            $this->drawFormatBits($mask);
            $penalty = $this->penalty();
            if ($penalty < $bestPenalty) {
                $best = $mask;
                $bestPenalty = $penalty;
            }
            $this->applyMask($mask);   // applying a mask twice undoes it
        }
        $this->applyMask($best);
        $this->drawFormatBits($best);
    }

    /** The standard's four penalty rules: long runs, 2x2 blocks, finder-like patterns, dark/light balance. */
    private function penalty(): int
    {
        $penalty = 0;
        $lines = [];
        for ($i = 0; $i < $this->size; $i++) {
            $lines[] = $this->modules[$i];
            $lines[] = array_column($this->modules, $i);
        }
        foreach ($lines as $line) {
            $run = 1;
            for ($k = 1; $k <= $this->size; $k++) {
                if ($k < $this->size && $line[$k] === $line[$k - 1]) {
                    $run++;
                    continue;
                }
                $penalty += $run >= 5 ? $run - 2 : 0;
                $run = 1;
            }
            $text = implode('', array_map(fn (bool $d) => $d ? '1' : '0', $line));
            $penalty += 40 * (substr_count('0000' . $text, '00001011101') + substr_count($text . '0000', '10111010000'));
        }
        $dark = 0;
        for ($y = 0; $y < $this->size; $y++) {
            for ($x = 0; $x < $this->size; $x++) {
                $dark += $this->modules[$y][$x] ? 1 : 0;
                if ($x < $this->size - 1 && $y < $this->size - 1) {
                    $c = $this->modules[$y][$x];
                    if ($c === $this->modules[$y][$x + 1] && $c === $this->modules[$y + 1][$x] && $c === $this->modules[$y + 1][$x + 1]) {
                        $penalty += 3;
                    }
                }
            }
        }
        $total = $this->size * $this->size;
        // 10 points for every 5% the dark share is away from 50%.
        $steps = (int) ceil(abs($dark * 20 - $total * 10) / $total) - 1;
        return $penalty + 10 * max(0, $steps);
    }
}
