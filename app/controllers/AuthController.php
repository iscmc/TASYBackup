<?php
/**
 * Servidor de contingência ISCMC Off Grid
 *
 * Este arquivo faz parte do framework MVC Projeto Contingenciamento.
 *
 * @category Framework
 * @package  Servidor de contingência ISCMC
 * @author   Sergio Figueroa <sergio.figueroa@iscmc.com.br>
 * @license  MIT, Apache
 * @link     http://10.132.16.43/TASYBackup
 * @version  1.0.0
 * @since    2025-07-01
 * @maindev  Sergio Figueroa
 */

class AuthController {
    public function login() {
        // Verifica se é uma submissão de formulário
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Processar login
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            // Aqui você adicionaria a lógica de autenticação
            // Exemplo simplificado:
            if ($username === 'admin' && $password === 'senha') {
                $_SESSION['usuario_logado'] = true;
                header('Location: ' . BASE_URL);
                exit;
            } else {
                $erro = "Usuário ou senha inválidos";
            }
        }
        
        // Mostrar formulário de login
        include __DIR__ . '/../views/auth/login.php';
    }
    
    public function logout() {
        session_destroy();
        header('Location: ' . BASE_URL . 'login');
        exit;
    }
}