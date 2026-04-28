<?php
$pdo = new PDO('mysql:host=db;dbname=komorebi_db', 'komorebi_user', 'komorebi');
$stmt = $pdo->prepare('SELECT r.name, r.code AS slug FROM roles r INNER JOIN user_roles ur ON r.id = ur.role_id WHERE ur.user_id = :user_id');
$stmt->execute(['user_id' => 1]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($rows) . PHP_EOL;
var_dump($rows);
