<?php

$db = new SQLite3('database/database.sqlite');

echo 'users      = ' . $db->querySingle('select count(*) from users') . PHP_EOL;
echo 'categories = ' . $db->querySingle('select count(*) from categories') . PHP_EOL;

$result = $db->query('select type, count(*) as total from categories group by type');
while (($row = $result->fetchArray()) !== null) {
    echo $row[0] . ' = ' . $row[1] . PHP_EOL;
}