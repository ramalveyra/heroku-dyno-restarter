<?php
class Requests
{
	public $handle;
 
	public function __construct()
	{
		$this->handle = curl_multi_init();
	}
 
	public function process($url,$action = 'GET' ,$api,$callback,$params=array())
	{	
		$ch = curl_init($url);
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $action);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt( $ch, CURLOPT_USERPWD, ":{$api}");
		
		curl_multi_add_handle($this->handle, $ch);
		$content_type = array('Accept: application/vnd.heroku+json; version=3');

		if($action == 'PATCH'){
			$content_type[] = 'Content-type: application/json';
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $content_type);

		if(isset($params['post_data'])){
			if(!empty($params['post_data'])){
				curl_setopt($ch, CURLOPT_POSTFIELDS,json_encode($params['post_data']));
			}
		}

		curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => TRUE));
 
		do {
			$mrc = curl_multi_exec($this->handle, $active);
 
			if ($state = curl_multi_info_read($this->handle))
			{
				//print_r($state);
				$info = curl_getinfo($state['handle']);
				//print_r($info);
				$callback(curl_multi_getcontent($state['handle']), $info, $params);
				curl_multi_remove_handle($this->handle, $state['handle']);
			}
 
			usleep(10000); // stop wasting CPU cycles and rest for a couple ms
 
		} while ($mrc == CURLM_CALL_MULTI_PERFORM || $active);
 
	}
 
	public function __destruct()
	{
		curl_multi_close($this->handle);
	}
}

class HerokuDynoApi
{
	public $request;
	public $api;
	public $target_app;
	public $restarter_app;
	public $time_interval;
	public $debug = false;
	public $scheduler_last_restart;
	public $last_restarted_app;
	public $time_executed;

	public function __construct(){
		$this->request = new Requests;
		$this->debug = getenv('SHOW_DEBUG_LOGS') == 'TRUE' ? TRUE : FALSE;
		$this->time_executed = new DateTime;
	}

	public function setVars($creds = array()){
		$this->api = $creds['api'];
		$this->target_app = $creds['target_app'];
		$this->restarter_app = $creds['restarter_app'];
		$this->time_interval = $creds['time_interval'];
	}

	public function getDynos($callback){
		$this->logger("Getting list of dynos for {$this->target_app}");

		$url = "https://api.heroku.com/apps/{$this->target_app}/dynos";

		$dyno_list = $this->request->process($url,'GET',$this->api,$callback);
	}

	public function restartDynos($option = array()){
		# Check time of last restart first
		$this->logger("preparing dynos to restart");

		$callback = function($data,$info,$params = array()){
			
			$config_vars = json_decode($data);

			if(isset($config_vars->SCHEDULER_LAST_DYNO_RESTART)){

				// evaluate config vars
				// check the target apps
				$target_apps = explode(",",$this->target_app);
				if(!is_array($target_apps) && empty($target_apps)){
					$this->logger("Restart dyno failed. Invalid target app");
					return;
				}

				// check last restart info
				// config var must be json
				$this->scheduler_last_restart = json_decode($config_vars->SCHEDULER_LAST_DYNO_RESTART);

				if($this->scheduler_last_restart == NULL){
					$this->logger("Restart dyno failed. Invalid config var 'SCHEDULER_LAST_DYNO_RESTART'");
					return;
				}

				$this->last_restarted_app = $this->scheduler_last_restart->{"last-restarted-app"};

				$last_app_key = array_search($this->last_restarted_app, $target_apps);

				if($last_app_key === FALSE){
					$this->logger("Restart dyno failed. Invalid target app");
					return;
				}

				$this->target_app = isset($target_apps[$last_app_key+1]) ? $target_apps[$last_app_key+1] : $target_apps[0];

				$this->logger("Checking info for {$this->target_app}");

				// get the last restart timestamp
				$time_last_restart = new DateTime($this->scheduler_last_restart->{$this->target_app});

				// get the time interval
				$time_interval = json_decode($this->time_interval);

				if($time_interval == NULL){
					$this->logger("Restart dyno failed. Invalid time interval");
					return;
				}

				$this->time_interval = $time_interval->{$this->target_app};
				
				$current_time = new DateTime('now');
				
				$elapsed = $current_time->getTimestamp() - $time_last_restart->getTimestamp();


				if(getenv('RESTARTER_ENV')=='LOCAL'){
					# for testing test in minutes
					$elapsed = floor($elapsed / (60));
					$this->logger("Minutes since last update: {$elapsed}");
				}else{
					$elapsed = floor($elapsed / (60*60));
					$this->logger("Hours since last update: {$elapsed}");	
				}
				

				if($elapsed >= $this->time_interval){
					$fetched_dynos_callback = function($data, $info)
					{
						$dyno_list = json_decode($data);
						
						$dynos_to_restart = count($dyno_list);

						$this->logger("Number of dynos to restart: {$dynos_to_restart}");

						if(!empty($dyno_list)){
							$this->restart_dyno_recursive(0, $dyno_list);
						}
					};
					$this->getDynos($fetched_dynos_callback);
				}else{
					$this->logger("Nothing to restart.");
				}
			}
		};
		$this->getConfigVars($this->restarter_app,$callback);
	}

	public function restart_dyno_recursive($dyno_index, $dyno_list){

		$callback = function($data,$info,$params)
		{
			//now check the dyno status if it has been restarted
			$this->logger("Checking dyno status...");

			if(isset($params['dyno'])){
				$url = "https://api.heroku.com/apps/{$this->target_app}/dynos/{$params['dyno']->name}";
				$this->request->process(
					$url,
					'GET',
					$this->api,
					array($this,'check_dyno'),
					array('dyno'=>$params['dyno'],'dyno_index'=>$params['dyno_index'],'dyno_list'=>$params['dyno_list'])
				);
			}
		};
		
		if(count($dyno_list) !== 0){
			if($dyno_index < count($dyno_list)){
				//restart the dyno
				$dyno = $dyno_list[$dyno_index];
				$this->logger("Restarting dyno: {$dyno->name}", TRUE);
				$url = "https://api.heroku.com/apps/{$this->target_app}/dynos/{$dyno->id}";
				$this->request->process(
					$url,
					'DELETE',
					$this->api,
					$callback,
					array('dyno'=>$dyno,'dyno_index'=>$dyno_index,'dyno_list'=>$dyno_list)
				);
			}else{
				//restart completed, now update the config var of this app
				$this->scheduler_last_restart->{$this->target_app} = $this->time_executed->format('d-m-Y H:i:s');
				$this->scheduler_last_restart->{"last-restarted-app"} = $this->target_app;
				$this->updateConfigVars(
					$this->restarter_app,
					array(
						'post_data'=>array(
							'SCHEDULER_LAST_DYNO_RESTART'=>json_encode($this->scheduler_last_restart)
						)
					)
				);
			}
		}
	}

	public function check_dyno($data,$info,$params){
		$dyno_info = json_decode($data);
		# TODO: add timeout
		if(isset($dyno_info->state)){
			//$this->logger("Dyno status: {$dyno_info->state}");
			if($dyno_info->state !== 'up'){
				//do another check
				if(isset($params['dyno'])){
					$url = "https://api.heroku.com/apps/{$this->target_app}/dynos/{$params['dyno']->name}";
					$this->request->process(
						$url,
						'GET',
						$this->api,
						array($this,'check_dyno'),
						array('dyno'=>$params['dyno'],'dyno_index'=>$params['dyno_index'],'dyno_list'=>$params['dyno_list'])
					);
				}
			}else{
				$this->logger("Dyno {$params['dyno']->name} restarted.", TRUE);
				# proceed to next dyno
				$dyno_index = $params['dyno_index']+1;
				
				$this->restart_dyno_recursive($dyno_index, $params['dyno_list']);
			}
		}
	}

	public function logger($msg = null, $force_show = false){
		if($this->debug == TRUE || $force_show == TRUE){
			if($msg!==null)
				fwrite(STDOUT,$msg. PHP_EOL);
		}
	}

	public function getConfigVars($app, $callback, $params = array()){
		
		$this->logger("Getting config variables");

		$url = "https://api.heroku.com/apps/{$app}/config-vars";
		
		$this->request->process(
			$url,
			'GET',
			$this->api,
			$callback
		);
	}

	public function updateConfigVars($app, $params = array()){
		$url = "https://api.heroku.com/apps/{$app}/config-vars";
		$callback = function($data, $info){
			$this->logger('Updated last restart timestamp');
		};
		$this->request->process(
			$url,
			'PATCH',
			$this->api,
			$callback,
			$params
		);
	}
}
if(getenv('RESTARTER_ENV')=='LOCAL'){
	date_default_timezone_set('UTC');
}
$api = getenv('RESTARTER_API');
$restarter_app = getenv('RESTARTER_APP');
$target_app = getenv('TARGET_APP');
$time_interval = getenv('TIME_INTERVAL'); //in hours

$herokuDynoObj = new HerokuDynoApi;
$herokuDynoObj->setVars(
	array(
		'api'=>$api, 
		'target_app'=>$target_app, 
		'restarter_app'=>$restarter_app,
		'time_interval' => $time_interval
	));
$herokuDynoObj->restartDynos();