<?php

#test log
$msg = 'test log';
fwrite(STDOUT,'hello logs' . PHP_EOL);

if (filter_var(getenv('CUSTOM_HEROKU_LOG'), FILTER_VALIDATE_BOOLEAN)){
	$filename = '/tmp/heroku.apache2_error.'.$_SERVER['PORT'].'.log';

	$fd = fopen($filename, "a");
	$str = "[" . date("d/m/Y H:i:s", time()) . substr((string)microtime(), 1, 8) . "] ". $msg;

	fwrite(STDOUT,$str . PHP_EOL);
	
	fwrite($fd, $str . PHP_EOL);
	fclose($fd); 
}
		
