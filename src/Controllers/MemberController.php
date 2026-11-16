<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\SchoolContext;
use App\Core\Validator;
use App\Data\InvitationRepository;
use App\Data\MemberRepository;
use App\Services\AccountMail;

/**
 * Members and invitations (owner and admins). Admins can invite officers,
 * viewers and other admins, change their roles and remove them; only the
 * owner role is untouchable here (see School settings for transfers).
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
            'old' => $old + ['email' => '', 'role' => 'officer'],
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
