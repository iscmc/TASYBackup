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

// Remove base path
$route = str_replace($basePath, '', $request);
$route = explode('?', $route)[0];

// Roteamento simplificado - SEM AUTENTICAÇÃO
switch ($route) {
    case '/':
    case '':
    case '/login':  // Redireciona login para a página principal
        $controller = new HomeController();
        $controller->index();
        break;
    case '/patients':
        $controller = new PacienteController();
        $controller->search();
        break;
    case '/force-sync':
        $controller = new HomeController();
        $controller->forceSync();
        break;
    case '/logs':
        $controller = new HomeController();
        $controller->viewLogs();
        break;
    default:
        // Tenta servir arquivos estáticos (CSS, JS, imagens)
        if (preg_match('/\.(css|js|png|jpg|jpeg|gif|ico)$/i', $route)) {
            $filePath = __DIR__ . $route;
            if (file_exists($filePath)) {
                $mimeTypes = [
                    'css' => 'text/css',
                    'js' => 'application/javascript',
                    'png' => 'image/png',
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'gif' => 'image/gif',
                    'ico' => 'image/x-icon'
                ];
                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                if (isset($mimeTypes[$ext])) {
                    header('Content-Type: ' . $mimeTypes[$ext]);
                    readfile($filePath);
                    exit;
                }
            }
        }
        http_response_code(404);
        include __DIR__ . '/app/views/404.php';
        break;
}