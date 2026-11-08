<?php
include 'data.php';
if (isset($_SESSION['user']['id'])) {
    registrarAuditoria(
        (int) $_SESSION['user']['id'],
        'CERRAR_SESION',
        'USUARIO',
        (int) $_SESSION['user']['id'],
        'Sesión cerrada'
    );
}
session_destroy();
header('Location: index.php');