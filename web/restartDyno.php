<?php

Class HerokuDynoApi
{
	public $api;
	public $app;

	public function __construct(){
		$this->api = '7457b4c6-f1ea-4c02-ac59-90d07bf47187';
		$this->app = 'blooming-hollows-1843';

	}

	public function getDynos(){
		$this->logger("Getting list of dynos for {$this->app}");
		$url = "https://api.heroku.com/apps/{$this->app}/dynos";
		$dyno_list =  json_decode($this->heroku_curl( $url ));
		return $dyno_list;
	}

	public function restartDynos($option = array()){
		if(isset($option['process'])){
			if($option['process']=='onebyone'){
				//get the dynos
				$dyno_list = $this->getDynos();
				if(!empty($dyno_list)){
					foreach ($dyno_list as $dyno) {
						//restart this dyno
						$this->logger("Restarting {$dyno->name}");
						$url = "https://api.heroku.com/apps/{$this->app}/dynos/{$dyno->id}";
						$dyno_restart = $this->heroku_curl( $url, 'DELETE');
					}
				}
			}
		}
	}

	public function heroku_curl($url, $action = 'GET', $post_data = array(), $next_range = NULL){
		$ch = curl_init( );
		
		curl_setopt( $ch, CURLOPT_URL, $url);
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $action);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt( $ch, CURLOPT_USERPWD, ":{$this->api}");
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		
		// use the latest version of the API
		$content_type = array('Accept: application/vnd.heroku+json; version=3');
		
		if ($action == 'POST' || $action == 'DELETE')
		{
			$content_type[] = 'Content-type: application/json';
		}
		else if ($action == 'GET')
		{
			if (is_null($next_range))
				$content_type[] = "Range: hostname ..; max={$this->max};";
			else
			{
				$content_type[] = "Range: hostname ]{$next_range}..; max={$this->max};";
			}
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $content_type);
		
		if (!empty($post_data))
		{
	    	curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($post_data));
		}
		
		$http_result = curl_exec($ch);
		$error       = curl_error($ch);
	    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	    curl_close($ch);

	    return $http_result;
	    
	}

	public function logger($msg = null){
		if($msg!==null)
			fwrite(STDOUT,$msg. PHP_EOL);
	}
}

$herokuDynoObj = new HerokuDynoApi;
//$process_arr = array('onebyone','timedinterval', 'all');
$herokuDynoObj->restartDynos(array('process'=>'onebyone'));


		
