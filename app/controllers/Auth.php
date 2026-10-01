<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class Auth extends Controller {

    private function api()
    {
        $this->call->database();
        $this->call->library('api');
        return $this->api;
    }

    public function login()
    {
        $api = $this->api();
        $api->require_method('POST');
        $body = $api->body();
        $identifier = trim((string)($body['identifier'] ?? $body['email'] ?? $body['username'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($identifier === '' || $password === '') {
            $api->respond_error('Username/email and password are required', 422);
        }

        $user = $this->db->raw(
            "SELECT id, username, email, password, role, is_active
             FROM users WHERE (email = ? OR username = ?) LIMIT 1",
            [$identifier, $identifier]
        )->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int)$user['is_active'] || !password_verify($password, $user['password'])) {
            $api->respond_error('Invalid credentials', 401);
        }

        $tokens = $api->issue_tokens([
            'id' => (int)$user['id'],
            'role' => $user['role'],
            'scopes' => $user['role'] === 'admin' ? ['read', 'write'] : ['read'],
        ]);

        unset($user['password'], $user['is_active']);
        $api->respond(['message' => 'Login successful', 'user' => $user, 'tokens' => $tokens]);
    }

    public function register()
    {
        $api = $this->api();
        $api->require_method('POST');
        $body = $api->body();
        $username = trim((string)($body['username'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if (!preg_match('/^[A-Za-z0-9_.-]{3,100}$/', $username)) {
            $api->respond_error('Username must be 3-100 characters and use only letters, numbers, dots, underscores, or hyphens', 422);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $api->respond_error('A valid email address is required', 422);
        }
        if (strlen($password) < 8) {
            $api->respond_error('Password must be at least 8 characters', 422);
        }

        $duplicate = $this->db->raw(
            "SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1",
            [$email, $username]
        )->fetch(PDO::FETCH_ASSOC);

        if ($duplicate) {
            $api->respond_error('That username or email is already registered', 409);
        }

        $id = $this->db->table('users')->insert([
            'username' => $username,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'user',
            'is_active' => 1,
        ]);

        $api->respond([
            'message' => 'Account created successfully',
            'user' => ['id' => (int)$id, 'username' => $username, 'email' => $email, 'role' => 'user'],
        ], 201);
    }

    public function refresh()
    {
        $api = $this->api();
        $api->require_method('POST');
        $body = $api->body();
        $refresh_token = (string)($body['refresh_token'] ?? '');

        if ($refresh_token === '') {
            $api->respond_error('Refresh token is required', 422);
        }

        $api->refresh_access_token($refresh_token);
    }

    public function logout()
    {
        $api = $this->api();
        $api->require_method('POST');
        $api->require_jwt();
        $body = $api->body();
        if (!empty($body['refresh_token'])) {
            $api->revoke_refresh_token((string)$body['refresh_token']);
        }
        $api->respond(['message' => 'Logged out successfully']);
    }
}
