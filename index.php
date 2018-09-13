<?
require_once './cpikabuparser.php';

set_time_limit(0);


$arSettings = array(
	'host'   => 'localhost',
	'dbname' => 'finsoft',
	'user'   => 'root',
	'pass'   => '',
	
	'log_filename'   => '.PDOErrors.txt',
	'debug'          => true,
);

$parser = new CPikabuParser($arSettings);

//$parser->updateFrom('https://pikabu.ru/hot/actual');
//$parser->updateFrom('https://pikabu.ru/best');
//$parser->updateFrom('https://pikabu.ru/');

$start = microtime(true); // time start

do {
	$parser->updateFrom('https://pikabu.ru/hot/time');
	
	
	sleep(15);
} while (microtime(true) - $start < 60); // check 1 min?
?>