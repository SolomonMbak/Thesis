<?php
/**
 * Create an administrator account, or reset an existing account's password
 * and make it an active admin.
 *
 *   php bin/create_admin.php --email=admin@example.edu --name="Ada Bello"
 *
 * The password is asked for interactively so it does not end up in the shell
 * history. For scripted installs, --password=... is accepted.
 */
declare(strict_types=1);

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Db;
use App\Core\Validator;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$options = getopt('', ['email:', 'name:', 'password:']);
$email = strtolower(trim((string) ($options['email'] ?? prompt('Email address: '))));
$name = trim((string) ($options['name'] ?? prompt('Full name: ')));
$password = (string) ($options['password'] ?? promptHidden('Password (at least ' . Auth::MIN_PASSWORD_LENGTH . ' characters): '));

$errors = Validator::validate(
    ['email' => $email, 'name' => $name, 'password' => $password],
    [
        'email' => 'required|email|maxlen:120',
        'name' => 'required|maxlen:120',
        'password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200',
    ],
    ['name' => 'Full name', 'email' => 'Email address']
);
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

try {
    $pdo = Db::pdo();
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $existingId = $stmt->fetchColumn();
    $hash = Auth::hashPassword($password);

    if ($existingId) {
        $pdo->prepare("UPDATE users SET full_name = ?, password_hash = ?, role = 'admin', is_active = 1 WHERE id = ?")
            ->execute([$name, $hash, $existingId]);
        $id = (int) $existingId;
        echo "Updated existing account {$email}: now an active administrator with the new password.\n";
    } else {
        $pdo->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
            ->execute([$name, $email, $hash]);
        $id = (int) $pdo->lastInsertId();
        echo "Created administrator {$email}.\n";
    }
    (new Audit($pdo))->log(null, 'user.create_admin_cli', 'user', $id, ['email' => $email], 'cli');
} catch (Throwable $ex) {
    fwrite(STDERR, 'Could not save the account: ' . $ex->getMessage() . "\n");
    exit(1);
}

function prompt(string $label): string
{
    echo $label;
    return trim((string) fgets(STDIN));
}

/** Read a password without echoing it where the terminal allows. */
function promptHidden(string $label): string
{
    echo $label;
    $isWindows = DIRECTORY_SEPARATOR === '\\';
    if (!$isWindows && function_exists('shell_exec')) {
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        echo "\n";
        return $value;
    }
    // Windows consoles cannot hide input from PHP; the typed password will show.
    return trim((string) fgets(STDIN));
}
