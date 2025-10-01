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
 * 
 * Fluxo atual correto
 * index.php (arquivos estáticos) → bootstrap.php (roteamento MVC) → Controller → Model → View
 */

session_start();

// Serve arquivos estáticos
$request = $_SERVER['REQUEST_URI'];
$basePath = '/TASYBackup';
// remove basePath
$route = str_replace($basePath, '', $request);
$route = explode('?', $route)[0];

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

// Todo o roteamento MVC fica no bootstrap
require_once __DIR__ . '/app/bootstrap.php';