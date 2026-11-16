<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\SchoolContext;
use App\Core\Validator;
use App\Data\InvitationRepository;
use App\Data\MemberRepository;
use App\Data\UserRepository;

/**
 * Accepting an invitation to join a school (BUILD_SPEC section 2).
 *
 * The link carries a one-time token valid for 7 days. Someone with no
 * account chooses a name and password here; someone with an account signs
 * in first (as the invited email) and then joins with one click.
 */
final class InviteController extends Controller
{
    public function show(array $errors = [], string $fullName = ''): void
    {
        $raw = (string) ($this->app->request->query('token') ?? $this->app->request->input('token', ''));
        $invite = (new InvitationRepository($this->app->db()))->findValid($raw);
        $data = ['title' => 'Join a school', 'invite' => $invite, 'token' => $raw, 'errors' => $errors, 'fullName' => $fullName,
            'roleLabel' => $invite ? SchoolContext::ROLE_LABELS[$invite['role']] : '', 'user' => $this->app->auth()->user(),
            'hasAccount' => false];
        if ($invite) {
            $data['hasAccount'] = (new UserRepository($this->app->db()))->findByEmail($invite['email']) !== null;
        }
        $this->renderPlain('invite/accept', $data);
    }

    public function accept(): void
    {
        $raw = (string) $this->app->request->input('token', '');
        $invites = new InvitationRepository($this->app->db());
        $invite = $invites->findValid($raw);
        if ($invite === null || $invite['school_status'] !== 'active') {
            $this->show();
            return;
        }
        $users = new UserRepository($this->app->db());
        $current = $this->app->auth()->user();
        $existing = $users->findByEmail($invite['email']);

        if ($current !== null) {
            if (strtolower($current['email']) !== strtolower($invite['email'])) {
                $this->show(['form' => "This invitation is for {$invite['email']}, but you are signed in as {$current['email']}. Sign out and open the link again."]);
                return;
            }
            $this->join($invite, (int) $current['id']);
            return;
        }
        if ($existing !== null) {
            $this->show(['form' => 'You already have an account. Sign in first, then accept the invitation.']);
            return;
        }

        $fullName = trim((string) $this->app->request->input('full_name', ''));
        $password = (string) $this->app->request->input('password', '');
        $errors = Validator::validate(['full_name' => $fullName, 'password' => $password], [
            'full_name' => 'required|maxlen:120',
            'password' => 'required|minlen:' . Auth::MIN_PASSWORD_LENGTH . '|maxlen:200',
        ], ['full_name' => 'Your name']);
        if (!isset($errors['password']) && $password !== (string) $this->app->request->input('password_confirm', '')) {
            $errors['password_confirm'] = 'The two passwords do not match.';
        }
        if ($errors) {
            http_response_code(422);
            $this->show($errors, $fullName);
            return;
        }
        // The emailed link proves the address, so the new account starts verified.
        $userId = $users->create($fullName, $invite['email'], $password, true);
        $this->app->auth()->loginAs($userId);
        $this->join($invite, $userId);
    }

    private function join(array $invite, int $userId): never
    {
        $invites = new InvitationRepository($this->app->db());
        if ($invites->accept((int) $invite['id'])) {
            (new MemberRepository($this->app->db()))->add((int) $invite['school_id'], $userId, $invite['role']);
            (new UserRepository($this->app->db()))->markVerified($userId);
        }
        $this->app->switchSchool((int) $invite['school_id']);
        $this->app->log('member.join', 'user', $userId, ['role' => $invite['role'], 'invited_by' => (int) $invite['invited_by']]);
        $this->app->flash->success("Welcome to {$invite['school_name']}. You joined as " . SchoolContext::ROLE_LABELS[$invite['role']] . '.');
        $this->redirect('dashboard');
    }
}
