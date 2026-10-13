<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class UserController extends Controller
{
    private $db;
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->call->database();
        $this->api = $this->call->library('api');
    }

    public function index()
    {
        $this->api->require_method('GET');
        $payload = $this->api->require_jwt();

        if (($payload['role'] ?? '') === 'admin') {
            $stmt = $this->db->raw(
                'SELECT id, username, email, role, created_at FROM users ORDER BY id DESC'
            );
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $stmt = $this->db->raw(
                'SELECT id, username, email, role, created_at FROM users WHERE id = ? LIMIT 1',
                [$payload['sub']]
            );
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            $users = $user ? [$user] : [];
        }

        $this->api->respond(['users' => $users]);
    }

    public function update($id)
    {
        $this->api->require_method('PUT');
        $payload = $this->api->require_jwt();

        if (!ctype_digit((string) $id)) {
            $this->api->respond_error('Invalid user ID.', 400);
        }

        $id = (int) $id;
        $isAdmin = ($payload['role'] ?? '') === 'admin';
        if (!$isAdmin && (int) $payload['sub'] !== $id) {
            $this->api->respond_error('You can only update your own account.', 403);
        }

        $body = $this->api->body();
        $username = trim((string) ($body['username'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $role = trim((string) ($body['role'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('A valid email address is required.', 422);
        }

        if ($username !== '' && strlen($username) > 100) {
            $this->api->respond_error('Username must be at most 100 characters.', 422);
        }

        if ($role !== '' && !$isAdmin) {
            $this->api->respond_error('Only admins can change a user role.', 403);
        }

        if ($role !== '' && !in_array($role, ['user', 'admin', 'moderator'], true)) {
            $this->api->respond_error('Invalid role.', 422);
        }

        $existing = $this->db->raw('SELECT id FROM users WHERE id = ? LIMIT 1', [$id]);
        if ($existing->fetch() === false) {
            $this->api->respond_error('User not found.', 404);
        }

        if ($username !== '') {
            $duplicate = $this->db->raw(
                'SELECT id FROM users WHERE username = ? AND id <> ? LIMIT 1',
                [$username, $id]
            );
            if ($duplicate->fetch()) {
                $this->api->respond_error('An account with this username already exists.', 409);
            }
        }

        if ($email !== '') {
            $duplicate = $this->db->raw(
                'SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1',
                [$email, $id]
            );
            if ($duplicate->fetch()) {
                $this->api->respond_error('An account with this email already exists.', 409);
            }
        }

        $changes = [];
        $values = [];
        if ($username !== '') {
            $changes[] = 'username = ?';
            $values[] = $username;
        }
        if ($email !== '') {
            $changes[] = 'email = ?';
            $values[] = $email;
        }
        if ($role !== '') {
            $changes[] = 'role = ?';
            $values[] = $role;
        }

        if (!$changes) {
            $this->api->respond_error('At least one user field must be provided.', 422);
        }

        $changes[] = 'updated_at = ?';
        $values[] = date('Y-m-d H:i:s');
        $values[] = $id;

        $this->db->raw(
            'UPDATE users SET ' . implode(', ', $changes) . ' WHERE id = ?',
            $values
        );

        $this->api->respond(['message' => 'User updated successfully.']);
    }

    public function delete($id)
    {
        $this->api->require_method('DELETE');
        $payload = $this->api->require_jwt();

        if (!ctype_digit((string) $id)) {
            $this->api->respond_error('Invalid user ID.', 400);
        }

        $id = (int) $id;
        $isAdmin = ($payload['role'] ?? '') === 'admin';
        if (!$isAdmin && (int) $payload['sub'] !== $id) {
            $this->api->respond_error('You can only delete your own account.', 403);
        }

        $existing = $this->db->raw('SELECT id FROM users WHERE id = ? LIMIT 1', [$id]);
        if ($existing->fetch() === false) {
            $this->api->respond_error('User not found.', 404);
        }

        $this->db->raw('DELETE FROM refresh_tokens WHERE user_id = ?', [$id]);
        $this->db->raw('DELETE FROM users WHERE id = ?', [$id]);

        $this->api->respond(['message' => 'User deleted successfully.']);
    }
}
