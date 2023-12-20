<?php

// $symbols = file($filename, FILE_IGNORE_NEW_LINES);

$result['count'] = file('./result_status_count.txt');
$result['symbols'] = file('./result_status_symbols.txt');
// $result['symbols'] = [
//     'www',
//     'eee'
// ];

$result = json_encode($result);
echo $result;