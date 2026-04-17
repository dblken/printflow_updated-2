<?php
$c = new mysqli('localhost', 'root', '122704');
$r = $c->query('SHOW DATABASES');
while($row = $r->fetch_assoc()) {
    echo $row['Database'] . "\n";
}
?>
