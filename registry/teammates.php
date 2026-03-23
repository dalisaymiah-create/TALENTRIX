<?php
include "config.php";

$query = "
SELECT 
students.first_name,
students.last_name,
sports.sport_name,
team_members.jersey_number
FROM team_members
JOIN students ON students.student_id = team_members.student_id
JOIN sports ON sports.sport_id = students.sport_id
";

$result = mysqli_query($conn,$query);

$players = [];

if($result){
$players = mysqli_fetch_all($result, MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>

<title>My Teammates</title>

<style>

body{
font-family:Arial;
background:#f4f6f9;
margin:0;
}

.container{
padding:40px;
}

.cards{
display:flex;
gap:20px;
flex-wrap:wrap;
}

.card{
width:180px;
background:linear-gradient(135deg,#5f6bd8,#8a63d2);
color:white;
padding:20px;
border-radius:15px;
text-align:center;
}

.circle{
width:60px;
height:60px;
background:white;
color:#5f6bd8;
border-radius:50%;
display:flex;
align-items:center;
justify-content:center;
margin:auto;
font-weight:bold;
font-size:20px;
}

.number{
background:rgba(255,255,255,0.2);
padding:5px 10px;
border-radius:20px;
display:inline-block;
margin-top:10px;
}

</style>

</head>

<body>

<div class="container">

<h2>👥 My Teammates</h2>

<div class="cards">

<?php
if(!empty($players)){

foreach($players as $player){

$initials = strtoupper($player['first_name'][0] . $player['last_name'][0]);
?>

<div class="card">

<div class="circle">
<?php echo $initials; ?>
</div>

<h3>
<?php echo $player['first_name']." ".$player['last_name']; ?>
</h3>

<p>
<?php echo $player['sport_name']; ?>
</p>

<div class="number">
#<?php echo $player['jersey_number']; ?>
</div>

</div>

<?php
}

}else{

echo "<p>No teammates found.</p>";

}
?>

</div>

</div>

</body>
</html>