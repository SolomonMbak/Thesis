<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\SchoolContext;
use App\Core\Validator;
use App\Data\InvitationRepository;
use App\Data\MemberRepository;
use App\Data\UserRepository;
use App\Services\AccountMail;

/**
 * Members and invitations (owner and admins). Admins can invite officers,
 * viewers and other admins, change their roles and remove them; only the
 * owner role is untouchable here (see School settings for transfers).
 *
 * People can join two ways: an emailed invitation, or (when email is not
 * set up, or the person is in the office) added directly with a temporary
 * password that they must replace at their first sign-in (BUILD_SPEC 13.3).
 */
final class MemberController extends Controller
{
    public function index(array $errors = [], array $old = []): void
    {
        $school = $this->school();
        $this->render('members/index', [
            'title' => 'Members and invitations',
            'members' => (new MemberRepository($this->app->db()))->list($school),
            'invites' => (new InvitationRepository($this->app->db()))->pending($school),
            'roles' => array_intersect_key(SchoolContext::ROLE_LABELS, array_flip(InvitationRepository::ROLES)),
            'errors' => $errors,
            'old' => $old + ['email' => '', 'role' => 'officer', 'add_name' => '', 'add_email' => '', 'add_role' => 'officer',
                'add_password' => self::temporaryPassword()],
            'me' => $this->app->auth()->id(),
        ]);
    }

    public function invite(): void
    {
        $school = $this->school();
        $in = ['email' => strtolower(trim((string) $this->app->request->input('email', ''))),
            'role' => (string) $this->app->request->input('role', '')];
        $errors = Validator::validate($in, ['email' => 'required|email|maxlen:120', 'role' => 'required|in:' . implode(',', InvitationRepository::ROLES)],
            ['email' => 'Email address', 'role' => 'Role']);
        if (!$errors) {
            foreach ((new MemberRepository($this->app->db()))->list($school) as $member) {
                if (strtolower($member['email']) === $in['email']) {
                    $errors['email'] = "{$in['email']} is already a member. Change their role in the list below instead.";
                }
            }
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors, $in);
            return;
        }
        $token = (new InvitationRepository($this->app->db()))->create($school, $in['email'], $in['role'], (int) $this->app->auth()->id());
        $sent = (new AccountMail($this->app->db()))->sendInvitation($in['email'], $token, $school->name(),
            SchoolContext::ROLE_LABELS[$in['role']], (string) $this->app->auth()->user()['full_name']);
        $this->app->log('member.invite', null, null, ['email' => $in['email'], 'role' => $in['role'], 'sent' => $sent]);
        if ($sent) {
            $this->app->flash->success("Invitation sent to {$in['email']}. The link works for 7 days.");
        } else {
            $this->app->flash->error("The invitation was saved but the email could not be sent. Ask the platform administrator to check the email settings, then send it again.");
        }
        $this->redirect('members');
    }

    /**
     * Create an account for someone and add them with a role. Only for new
     * people: someone who already has an account is invited instead, so
     * nobody joins a school without agreeing to it.
     */
    public function add(): void
    {
        $school = $this->school();
        $request = $this->app->request;
        $in = ['add_name' => trim((string) $request->input('add_name', '')),
            'add_email' => strtolower(trim((string) $request->input('add_email', ''))),
            'add_role' => (string) $request->input('add_role', ''),
            'add_password' => (string) $request->input('add_password', '')];
        $errors = Validator::validate($in, [
            'add_name' => 'required|maxlen:120',
            'add_email' => 'required|email|maxlen:120',
            'add_role' => 'required|in:' . implode(',', InvitationRepository::ROLES),
            'add_password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200',
        ], ['add_name' => 'Full name', 'add_email' => 'Email address', 'add_role' => 'Role', 'add_password' => 'Temporary password']);
        $users = new UserRepository($this->app->db());
        if (!isset($errors['add_email']) && $users->findByEmail($in['add_email'])) {
            $errors['add_email'] = 'This person already has an account. Use "Invite someone" above instead, so they can accept joining your school.';
        }
        if ($errors) {
            http_response_code(422);
            $this->index($errors, $in);
            return;
        }
        $pdo = $this->app->db();
        $pdo->beginTransaction();
        try {
            // Marked as confirmed: the administrator vouches for the address, and email may not be set up at all.
            $userId = $users->create($in['add_name'], $in['add_email'], $in['add_password'], true);
            $users->setPassword($userId, $in['add_password'], true);
            (new MemberRepository($pdo))->add($school->id, $userId, $in['add_role']);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        $this->app->log('member.add', 'user', $userId, ['email' => $in['add_email'], 'role' => $in['add_role']]);
        $this->app->flash->success("{$in['add_name']} can now sign in as " . SchoolContext::ROLE_LABELS[$in['add_role']]
            . " with the email {$in['add_email']} and the temporary password you chose. Give them the password in person or by phone; "
            . 'they will be asked to choose their own at the first sign-in.');
        $this->redirect('members');
    }

    /** A readable random password such as "kq7m-3vtr-p9xa" (no 0/o or 1/l), for the add form. */
    private static function temporaryPassword(): string
    {
        $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
        $out = '';
        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)] . ($i % 4 === 3 && $i < 11 ? '-' : '');
        }
        return $out;
    }

    public function changeRole(): void
    {
        $school = $this->school();
        $members = new MemberRepository($this->app->db());
        $member = $members->find($school, (int) $this->app->request->input('user_id', 0));
        $role = (string) $this->app->request->input('role', '');
        if ($member === null || $member['role'] === 'owner' || !in_array($role, InvitationRepository::ROLES, true)) {
            $this->app->flash->error('That change is not possible. The owner role can only be handed over under School settings.');
            $this->redirect('members');
        }
        $members->setRole($school, (int) $member['id'], $role);
        $this->app->log('member.role', 'user', (int) $member['id'], ['from' => $member['role'], 'to' => $role]);
        $this->app->flash->success("{$member['full_name']} is now " . SchoolContext::ROLE_LABELS[$role] . '.');
        $this->redirect('members');
    }

    public function remove(): void
    {
        $school = $this->school();
        $members = new MemberRepository($this->app->db());
        $member = $members->find($school, (int) $this->app->request->input('user_id', 0));
        if ($member === null || $member['role'] === 'owner') {
            $this->app->flash->error('The owner cannot be removed. Hand the school to someone else first (School settings).');
            $this->redirect('members');
        }
        $members->remove($school, (int) $member['id']);
        $this->app->log('member.remove', 'user', (int) $member['id']);
        $this->app->flash->success("{$member['full_name']} no longer has access to {$school->name()}. Their account stays for any other schools.");
        $this->redirect((int) $member['id'] === $this->app->auth()->id() ? 'dashboard' : 'members');
    }

    public function revoke(): void
    {
        $revoked = (new InvitationRepository($this->app->db()))->revoke($this->school(), (int) $this->app->request->input('id', 0));
        $this->app->flash->success($revoked ? 'Invitation withdrawn. Its link no longer works.' : 'That invitation was already used or withdrawn.');
        $this->redirect('members');
    }
}
