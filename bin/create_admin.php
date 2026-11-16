<?php
/**
 * Create a platform administrator, or reset an existing account's password
 * and make it an active, verified platform administrator. Use it when the
 * only administrator has forgotten the password.
 *
 *   php bin/create_admin.php --email=admin@example.edu --name="Ada Bello"
 *   php bin/create_admin.php --email=ada@example.edu --school=demo --role=owner   also add to a school
 *
 * The password is asked for interactively so it does not end up in the shell
 * history. For scripted installs, --password=... is accepted.
 */
declare(strict_types=1);

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Db;
use App\Core\Validator;
use App\Data\MemberRepository;
use App\Data\SchoolRepository;
use App\Data\UserRepository;

require dirname(__DIR__) . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit("Run this from the command line.\n");
}

$options = getopt('', ['email:', 'name:', 'password:', 'school:', 'role:']);
$email = strtolower(trim((string) ($options['email'] ?? prompt('Email address: '))));
$name = trim((string) ($options['name'] ?? prompt('Full name: ')));
$password = (string) ($options['password'] ?? promptHidden('Password (at least ' . Auth::MIN_PASSWORD_LENGTH . ' characters): '));
$role = (string) ($options['role'] ?? 'owner');

$errors = Validator::validate(
    ['email' => $email, 'name' => $name, 'password' => $password, 'role' => $role],
    [
        'email' => 'required|email|maxlen:120',
        'name' => 'required|maxlen:120',
        'password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200',
        'role' => 'in:owner,admin,officer,viewer',
    ],
    ['name' => 'Full name', 'email' => 'Email address', 'role' => 'Role']
);
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}

try {
    $pdo = Db::pdo();
    $users = new UserRepository($pdo);
    $existing = $users->findByEmail($email);
    if ($existing) {
        $id = (int) $existing['id'];
        $users->setPassword($id, $password);
        $users->updateProfile($id, $name);
        $pdo->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$id]);
        echo "Updated existing account {$email}: now an active platform administrator with the new password.\n";
    } else {
        $id = $users->create($name, $email, $password, true, true);
        echo "Created platform administrator {$email}.\n";
    }
    $users->setPlatformAdmin($id, true);
    $users->markVerified($id);

    if (isset($options['school'])) {
        $school = (new SchoolRepository($pdo))->findBySlug((string) $options['school']);
        if ($school === null) {
            fwrite(STDERR, "There is no school with the link name '{$options['school']}'.\n");
            exit(1);
        }
        (new MemberRepository($pdo))->add((int) $school['id'], $id, $role);
        echo "Added to {$school['name']} as {$role}.\n";
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
