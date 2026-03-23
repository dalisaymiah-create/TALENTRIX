<?php
require 'config.php';

if($_SERVER['REQUEST_METHOD'] == 'POST'){

$event_name = $_POST['event_name'];
$type = $_POST['event_type'];
$date = $_POST['event_date'];
$time = $_POST['event_time'];
$location = $_POST['location'];

$stmt = $pdo->prepare("INSERT INTO events (event_name,event_type,event_date,event_time,location) VALUES (?,?,?,?,?)");

$stmt->execute([$event_name,$type,$date,$time,$location]);

echo "Event Added Successfully";
}
?>

<h2>Add Event</h2>

<form method="POST">

<input type="text" name="event_name" placeholder="Event Name" required><br><br>

<input type="text" name="event_type" placeholder="Sports / Dance"><br><br>

<input type="date" name="event_date"><br><br>

<input type="time" name="event_time"><br><br>

<input type="text" name="location" placeholder="Location"><br><br>

<button type="submit">Add Event</button>

</form>