<?php
require_once 'vendor/autoload.php';
$dsn = "pgsql:host=postgres;port=5432;dbname=northwind";
$user = "postgres";
$password = "postgres";
$tableName = "categories";
$driver = explode(':', $dsn)[0];
$quote = ($driver === 'pgsql') ? '"' : '`';
$pdo = new \PDO($dsn, $user, $password);
$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->query("SELECT * FROM {$quote}{$tableName}{$quote}");
print_r($stmt->fetchAll(\PDO::FETCH_ASSOC));
