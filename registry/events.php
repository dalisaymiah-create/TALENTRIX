<?php
require 'config.php';

$stmt = $pdo->query("SELECT * FROM events ORDER BY event_date ASC");
$events = $stmt->fetchAll();
?>

<h2>Events</h2>

<table border="1" cellpadding="10">

<tr>
<th>Event Name</th>
<th>Type</th>
<th>Date</th>
<th>Time</th>
<th>Location</th>
</tr>

<?php foreach($events as $event): ?>

<tr>
<td><?php echo $event['event_name']; ?></td>
<td><?php echo $event['event_type']; ?></td>
<td><?php echo $event['event_date']; ?></td>
<td><?php echo $event['event_time']; ?></td>
<td><?php echo $event['location']; ?></td>
</tr>

<?php endforeach; ?>

</table>