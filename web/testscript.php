<?php

#test log
$msg = 'test log';
if (filter_var(getenv('CUSTOM_HEROKU_LOG'), FILTER_VALIDATE_BOOLEAN)){
	$filename = '/tmp/heroku.apache2_error.'.$_SERVER['PORT'].'.log';

	$fd = fopen($filename, "a");
	$str = "[" . date("d/m/Y H:i:s", time()) . substr((string)microtime(), 1, 8) . "] ". $msg;

	if($this->debug_mode) fwrite(STDOUT,$str . PHP_EOL);// for testing 
	
	fwrite($fd, $str . PHP_EOL);
	fclose($fd); 
}
		
