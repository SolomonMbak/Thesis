<?php
declare(strict_types=1);

namespace Tests;

use App\Core\QrCode;
use App\Core\Totp;

/**
 * Two-step sign-in codes (RFC 6238) and the QR code that sets them up.
 * The QR encoder was also checked by decoding versions 1 to 10 with an
 * independent decoder (jsQR); these tests pin its structure.
 */
final class TotpTest extends TestCase
{
    /** RFC 6238 appendix B, SHA-1, secret "12345678901234567890"; the last 6 of the 8 digits. */
    public function testCodesMatchTheRfcTestVectors(): void
    {
        $secret = Totp::base32Encode('12345678901234567890');
        $vectors = [59 => '287082', 1111111109 => '081804', 1111111111 => '050471', 1234567890 => '005924', 2000000000 => '279037'];
        foreach ($vectors as $time => $code) {
            $this->assertSame($code, Totp::codeAt($secret, Totp::stepAt($time)), "Code at {$time}");
        }
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret, 'Base32 of the RFC secret.');
        $this->assertSame('12345678901234567890', Totp::base32Decode(strtolower(Totp::grouped($secret))), 'Grouping and case are ignored.');
    }

    public function testMatchAllowsOneStepOfClockDriftAndNeverTheSameStepTwice(): void
    {
        $secret = Totp::newSecret();
        $this->assertSame(32, strlen($secret), '160-bit key.');
        $now = 1_800_000_000;
        $step = Totp::stepAt($now);
        $this->assertSame($step, Totp::match($secret, Totp::codeAt($secret, $step), $now));
        $this->assertSame($step - 1, Totp::match($secret, Totp::codeAt($secret, $step - 1), $now), 'Previous code still works.');
        $this->assertSame($step + 1, Totp::match($secret, substr_replace(Totp::codeAt($secret, $step + 1), ' ', 3, 0), $now), 'Spaces ignored.');
        $this->assertSame(null, Totp::match($secret, Totp::codeAt($secret, $step - 2), $now), 'Two steps old is too old.');
        $this->assertSame(null, Totp::match($secret, Totp::codeAt($secret, $step), $now, $step), 'A used step is refused.');
        $this->assertSame(null, Totp::match($secret, 'abcdef', $now));
        $uri = Totp::uri($secret, 'ada@example.edu', 'Timetable');
        $this->assertTrue(str_starts_with($uri, 'otpauth://totp/Timetable:ada%40example.edu?secret=' . $secret . '&issuer=Timetable'), $uri);
    }

    public function testQrCodeHasTheFixedPatternsAndAValidFormatField(): void
    {
        $uri = Totp::uri(Totp::newSecret(), 'someone.with.a.long.name@department.example.edu', 'Automated Class Timetabling');
        $m = QrCode::matrix($uri);
        $size = count($m);
        $this->assertSame(0, ($size - 17) % 4, 'Size is 17 + 4 x version.');
        $this->assertTrue($size >= 41 && $size <= 57, "A set-up link needs version 6 to 10, got size {$size}.");
        // Finder pattern corners: dark ring, light ring, dark 3x3 centre.
        foreach ([[0, 0], [$size - 7, 0], [0, $size - 7]] as [$x, $y]) {
            $this->assertTrue($m[$y][$x] && $m[$y + 6][$x + 6] && !$m[$y + 1][$x + 1] && $m[$y + 3][$x + 3], "Finder at {$x},{$y}");
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $this->assertSame($i % 2 === 0, $m[6][$i], "Timing pattern at {$i}");
        }
        $this->assertTrue($m[$size - 8][8], 'The dark module.');
        // Both copies of the 15-bit format field are equal and are a valid BCH code word for level M.
        $a = $b = 0;
        $first = [[8, 0], [8, 1], [8, 2], [8, 3], [8, 4], [8, 5], [8, 7], [8, 8], [7, 8], [5, 8], [4, 8], [3, 8], [2, 8], [1, 8], [0, 8]];
        foreach ($first as $i => [$x, $y]) {
            $a |= ($m[$y][$x] ? 1 : 0) << $i;
        }
        for ($i = 0; $i < 8; $i++) {
            $b |= ($m[8][$size - 1 - $i] ? 1 : 0) << $i;
        }
        for ($i = 8; $i < 15; $i++) {
            $b |= ($m[$size - 15 + $i][8] ? 1 : 0) << $i;
        }
        $this->assertSame($a, $b, 'Format copies differ.');
        $data = ($a ^ 0x5412) >> 10;
        $this->assertSame(0, $data >> 3, 'Error correction level M.');
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
        }
        $this->assertSame(($data << 10) | $rem, $a ^ 0x5412, 'Format BCH check.');
        $svg = QrCode::svg('hello', 'Label "x"');
        $this->assertTrue(str_contains($svg, 'aria-label="Label &quot;x&quot;"') && str_contains($svg, 'viewBox="0 0 29 29"'), 'SVG of version 1 with quiet zone.');
    }
}
