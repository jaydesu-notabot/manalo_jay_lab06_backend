<?php

class Seed_admin_user {

    private $_lava;

    public function __construct()
    {
        $this->_lava = lava_instance();
        $this->_lava->call->database();
    }

    public function up()
    {
        $existing = $this->_lava->db->raw(
            "SELECT id FROM users WHERE email = ? LIMIT 1",
            ['admin@example.com']
        )->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $this->_lava->db->raw(
                "INSERT INTO users (username, email, password, role, is_active)
                 VALUES (?, ?, ?, ?, ?)",
                ['admin', 'admin@example.com', password_hash('password', PASSWORD_DEFAULT), 'admin', 1]
            );
        }
    }

    public function down()
    {
        $this->_lava->db->raw("DELETE FROM users WHERE email = ?", ['admin@example.com']);
    }
}
