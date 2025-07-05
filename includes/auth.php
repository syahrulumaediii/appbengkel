<?php
// Includes/auth.php
session_start(); // Penting: jangan lupa start session
require_once __DIR__ . '/../config/database.php';

// Fungsi procedural
function checkAuth()
{
    if (!isset($_SESSION['user'])) {
        header('Location: ../login.php');
        exit();
    }
}

if (!class_exists('Auth')) {
    class Auth
    {
        /**
         * Cek apakah user sudah login
         */
        public function isLoggedIn()
        {
            return isset($_SESSION['user']);
        }


        // /**
        //  * Ambil username dari user yang sedang login
        //  */
        // public function getLoggedInUsername()
        // {
        //     require_once __DIR__ . '/../config/Database.php';

        //     // Pakai instance global $db
        //     global $db;

        //     if (isset($_SESSION['user']['id_user'])) {
        //         $id_user = $_SESSION['user']['id_user'];
        //         $sql = "SELECT username FROM user WHERE id_user = ?";
        //         $result = $db->fetch($sql, [$id_user]);

        //         if ($result) {
        //             return $result['username'];
        //         }
        //     }

        //     return 'Tamu';
        // }


        /**
         * Simpan data user ke session setelah login
         */
        public function login($user)
        {
            $_SESSION['user'] = [
                'id_user' => $user['id_user'],
                'username' => $user['username'],
                'nama' => $user['nama'],         // ⬅️ ini WAJIB ditambahkan
                'role' => $user['role']
            ];
        }


        /**
         * Ambil username yang sedang login
         */
        public function getUserName()
        {
            return $_SESSION['user']['nama'] ?? 'Guest';
        }

        /**
         * Ambil role user saat ini
         */
        public function getUserRole()
        {
            return $_SESSION['user']['role'] ?? 'unknown';
        }

        /**
         * Cek apakah user adalah admin
         */
        public function isAdmin()
        {
            return $this->isLoggedIn() && $_SESSION['user']['role'] === 'admin';
        }

        /**
         * Wajib login untuk mengakses halaman
         */
        public function requireLogin()
        {
            if (!$this->isLoggedIn()) {
                header('Location: ../login.php');
                exit();
            }
        }

        /**
         * Wajib admin untuk mengakses halaman
         */
        public function requireAdmin()
        {
            if (!$this->isAdmin()) {
                header('Location: ../user.php');
                exit();
            }
        }

        /**
         * Ambil data user lengkap dari session
         */
        public function getUser()
        {
            return $_SESSION['user'] ?? null;
        }

        /**
         * Logout user
         */
        public function logout()
        {
            session_destroy();
            header('Location: ../login.php');
            exit();
        }
    }
}
