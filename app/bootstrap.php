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

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Configurações básicas
define('APP_ROOT', dirname(__DIR__));
define('LOG_PATH', APP_ROOT . '/logs/');
define('TEMPLATE_PATH', APP_ROOT . '/app/views/');

// Configuração de erro
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', LOG_PATH . 'error.log');
error_reporting(E_ALL);

spl_autoload_register(function ($className) { // Autoload um pouco mais simples incluindo o namespace
    $file = APP_ROOT . '/app/' . str_replace(['App\\', '\\'], ['', '/'], $className) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// Define os caminhos das pastas do assets para imagens e scripts dinamicamente
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
$basePath = dirname($_SERVER['SCRIPT_NAME']);
$baseUrl = $protocol . $_SERVER['HTTP_HOST'] . ($basePath === '\\' ? '' : $basePath) . '/';
$baseUrl = str_replace('\\', '/', $baseUrl); // Normaliza as barras

define('BASE_URL', $baseUrl); // Define como constante para acesso global

// Carregar configurações
require_once APP_ROOT . '/app/config/database.php';

// Rotas básicas
$action = $_GET['action'] ?? 'index';
$controller = $_GET['controller'] ?? 'Home';

$controllerClass = ucfirst($controller) . 'Controller';
$controllerFile = APP_ROOT . '/app/controllers/' . $controllerClass . '.php';

if (file_exists($controllerFile)) {
    require $controllerFile;
    $controllerInstance = new $controllerClass();
    
    if (method_exists($controllerInstance, $action)) {
        $controllerInstance->$action();
    } else {
        header("HTTP/1.0 404 Not Found");
        include TEMPLATE_PATH . '404.php';
    }
} else {
    header("HTTP/1.0 404 Not Found");
    include TEMPLATE_PATH . '404.php';
}
