<?php
/**
 * Turn two-step sign-in off for a user who lost both their phone and their
 * recovery codes (or after the server key storage/keys/app.key was lost).
 * They then sign in with their password and can set it up again.
 * Also ends every "Keep me signed in" browser of that user.
 *
 *   php bin/reset_2fa.php --email=ada@example.edu
 *
 * Check the person's identity first (in person or by a known phone number):
 * this removes the second step from their account.
 */
declare(strict_types=1);

use App\Core\Audit;
use App\Core\Db;
use App\Data\RememberTokenRepository;
use App\Data\TwoFactorRepository;
use App\Data\UserRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$options = getopt('', ['email:']);
$email = strtolower(trim((string) ($options['email'] ?? '')));
if ($email === '') {
    fwrite(STDERR, "Usage: php bin/reset_2fa.php --email=person@example.edu\n");
    exit(1);
}
$pdo = Db::pdo();
$user = (new UserRepository($pdo))->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "No account has the email {$email}.\n");
    exit(1);
}
if (!TwoFactorRepository::isOn($user)) {
    echo "Two-step sign-in is already off for {$email}. Nothing changed.\n";
    exit(0);
}
(new TwoFactorRepository($pdo))->disable((int) $user['id']);
$browsers = (new RememberTokenRepository($pdo))->forgetUser((int) $user['id']);
(new Audit($pdo))->log(null, 'auth.2fa_reset_cli', 'user', (int) $user['id'], ['email' => $email], 'cli');
echo "Two-step sign-in is now off for {$email}; {$browsers} kept-signed-in browser(s) signed out.\n"
    . "They can sign in with their password and set it up again under Profile.\n";
