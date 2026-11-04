<?php
header('Content-Type: text/css');
$config = json_decode(file_get_contents('config.json'), true) ?? [
    'color_fondo' => '#f58610db',
    'color_letra' => '#000',
    'tamano_letra' => '16px',
    'fuente' => 'Arial, sans-serif'
];
?>
:root {
--fondo: <?php echo $config['color_fondo']; ?>;
--letra: <?php echo $config['color_letra']; ?>;
--tamano: <?php echo $config['tamano_letra']; ?>;
--fuente: <?php echo $config['fuente']; ?>;
}

body {
font-family: var(--fuente);
background-color: var(--fondo);
color: var(--letra);
font-size: var(--tamano);
margin: 0;
padding: 20px;
}

.login-container {
max-width: 400px;
margin: 100px auto;
padding: 20px;
background: white;
border-radius: 8px;
box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.login-container h2 {
text-align: center;
}

.login-container form {
display: flex;
flex-direction: column;
}

.login-container label {
margin-top: 10px;
}

.login-container input {
padding: 8px;
margin-top: 5px;
}

.login-container button {
margin-top: 20px;
padding: 10px;
background: #007bff;
color: white;
border: none;
cursor: pointer;
}

header {
background: #333;
color: white;
padding: 10px;
display: flex;
justify-content: space-between;
align-items: center;
}

nav a {
color: white;
margin: 0 10px;
text-decoration: none;
}

.pos-container {
display: flex;
gap: 20px;
}

.mesas-grid {
display: grid;
grid-template-columns: repeat(5, 1fr);
gap: 10px;
}

.mesa-btn {
padding: 10px;
background: #f0f0f0;
border: 1px solid #ccc;
cursor: pointer;
}

.menu-grid {
display: grid;
grid-template-columns: repeat(4, 1fr);
gap: 10px;
}

.menu-item {
padding: 10px;
background: #f0f0f0;
border: 1px solid #ccc;
cursor: pointer;
}

.orden {
background: white;
padding: 20px;
border-radius: 8px;
}

.tip-options {
display: flex;
gap: 10px;
margin: 10px 0;
}

.tip-btn {
padding: 8px 12px;
background: #007bff;
color: white;
border: none;
border-radius: 4px;
cursor: pointer;
}

.tip-btn:hover {
background: #0056b3;
}
/* Modal styles */
.modal {
position: fixed;
top: 0;
left: 0;
width: 100%;
height: 100%;
background: rgba(0, 0, 0, 0.5);
display: flex;
justify-content: center;
align-items: center;
z-index: 1000;
}

.modal-content {
background: white;
padding: 20px;
border-radius: 8px;
max-width: 500px;
width: 90%;
max-height: 80vh;
overflow-y: auto;
}
/* Estilos móviles */
@media (max-width: 768px) {
body {
padding: 10px;
}
header {
flex-direction: column;
text-align: center;
}
nav {
display: flex;
flex-wrap: wrap;
justify-content: center;
}
nav a {
margin: 5px;
padding: 5px 10px;
background: #f0f0f0;
border-radius: 4px;
}
.pos-container {
flex-direction: column;
}
.mesas-grid {
grid-template-columns: repeat(3, 1fr);
}
.menu-grid {
grid-template-columns: repeat(2, 1fr);
}
.login-container {
margin: 50px 10px;
}
}