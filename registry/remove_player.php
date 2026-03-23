<?php
require_once "db.php";

if(!isset($_GET['id'])){
header("Location: coach_dashboard.php");
exit();
}

$id = $_GET['id'];

$stmt = $pdo->prepare("
DELETE FROM team_members
WHERE student_id = ?
");

$stmt->execute([$id]);

header("Location: coach_dashboard.php");
exit();