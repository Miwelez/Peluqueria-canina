<?php
$host = 'localhost';
$db = 'peluqueria-canina';
$usuario = 'root';
$password = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$opciones = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $usuario, $password, $opciones);
} catch (PDOException $e) {
    die('Error de conexión: ' . $e->getMessage());
}
?>