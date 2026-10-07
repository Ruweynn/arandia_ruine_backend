<?php

defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class AuthController extends Controller
{
    private $db;
    private $api;

    public function __construct()
    {
        parent::__construct();
        $this->db = $this->call->database();
        $this->api = $this->call->library('api');
    }

    public function register()
    {
        $this->api->require_method('POST');

        $body = $this->api->body();
        $name = trim((string) ($body['name'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 8) {
            $this->api->respond_error('Name, valid email, and a password of at least 8 characters are required.', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->api->respond_error('A valid email address is required.', 422);
        }

        $stmt = $this->db->raw('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($stmt->fetch()) {
            $this->api->respond_error('An account with this email already exists.', 409);
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $this->db->raw(
            'INSERT INTO users (username, email, password, role, is_active, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [$name, $email, $hash, 'user', 1]
        );

        $this->api->respond([
            'message' => 'Registration successful. You can now log in.',
        ], 201);
    }

    public function login()
    {
        $this->api->require_method('POST');

        $body = $this->api->body();
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');

        if ($email === '' || $password === '') {
            $this->api->respond_error('Email and password are required.', 422);
        }

        $stmt = $this->db->raw(
            'SELECT id, username, email, password, role, is_active FROM users WHERE email = ? LIMIT 1',
            [$email]
        );
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, (string) $user['password']) || !(int) $user['is_active']) {
            $this->api->respond_error('Invalid email or password.', 401);
        }

        $tokens = $this->api->issue_tokens([
            'id' => (int) $user['id'],
            'role' => $user['role'],
            'scopes' => ['read', 'write', 'delete'],
        ]);

        $this->api->respond([
            'message' => 'Login successful.',
            'user' => [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
            'tokens' => $tokens,
        ]);
    }

    public function me()
    {
        $payload = $this->api->require_jwt();
        $stmt = $this->db->raw('SELECT id, username, email, role FROM users WHERE id = ? LIMIT 1', [$payload['sub']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->api->respond_error('User not found.', 404);
        }

        $this->api->respond(['user' => $user]);
    }

    public function logout()
    {
        $this->api->require_jwt();
        $this->api->require_method('POST');
        $this->api->respond(['message' => 'Logged out successfully.']);
    }
}
