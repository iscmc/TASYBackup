<?php
/**
 * Servidor de contingência ISCMC Off frid
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

session_start();
require_once __DIR__ . '/app/bootstrap.php';

$request = $_SERVER['REQUEST_URI'];
$basePath = '/TASYBackup';
/*
// Remove base path e parâmetros de query
$route = str_replace($basePath, '', parse_url($request, PHP_URL_PATH));

// Roteamento simplificado
switch ($route) {
    case '/':
    case '':
        $controller = new HomeController();
        $controller->index();
        break;
    case '/login':
        $controller = new AuthController();
        $controller->login();
        break;
    case '/logout':
        $controller = new AuthController();
        $controller->logout();
        break;
    case '/pacientes':
        $controller = new PacienteController();
        $controller->search();
        break;
    default:
        http_response_code(404);
        include __DIR__ . '/app/views/404.php';
        break;
}
*/
// index.php na raiz
$route = $_GET['route'] ?? 'home';

switch ($route) {
    case 'login':
        require 'app/views/auth/login.php';
        break;
    case 'home':
        require 'app/views/home.php';
        break;
    // adicione outros cases conforme necessário
    default:
        require 'app/views/404.php';
        break;
}