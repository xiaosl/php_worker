<?php
	include_once('worker.php');
	include_once('conn.php');
	ini_set('default_socket_timeout', -1);
	class Agent{
		private $file_array;
		private $hostname = 'localhost';
		public function __construct($file_array){
			$this->file_array = $file_array;
			$this->hostname = $this->file_array['hostname'];
		}
		public function register(){	
			$urltemp = 'http://'.$this->file_array['urltemp'].'/servicereg.php?key='.serialize($this->file_array);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $urltemp);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			$out = curl_exec($ch);
			curl_close($ch);
			return unserialize($out);
		}
		
		private function createfork($jobkey,$class='',$jobname=''){
			$pid = pcntl_fork();
			if($pid == -1){
				echo "创建worker失败,发生错误";
				exit(1);
			}
			if(!$pid){
				$gpid = pcntl_fork();
				if(!$gpid){

					ini_set('default_socket_timeout', -1);	
					//设置autoloader
					function autoloader($class) {
						$file = str_replace(array('\\', '_'), DIRECTORY_SEPARATOR, $class) . '.php';
						return include_once $file;
					};
					spl_autoload_register('autoloader');

					//创建worker
					$thispid = posix_getpid();
					echo "创建worker进程ID".$thispid."\n";
					//判断存在
					$con = new Conn();
					$predis = $con->RedisPConByArr($this->file_array);
					if(!$predis){
						echo "连接失败";
						exit;
					}
					$predis->lPush($this->hostname.'_jobList_'.$thispid,$jobkey);
					$worker = new Worker($predis,$this->file_array);
					//worker  dojob
					while(1){
						//判断存在
						$runres = $worker->run();
						//检查检测进程				
						if($runres == 'stop'){
							$predis->hDel($this->hostname."_pid",$thispid);
							$predis->close();
							exit;
							pcntl_wait($status);
						}
					}
				}
				exit;
				pcntl_wait($status);
			}
		}
		
		private function killthefork($uid){
			//判断存在
			exec("kill ".$uid);
			echo "进程杀死".$uid."\n";
			pcntl_waitpid($uid,$status,WUNTRACED);
			//$this->redis->lRem($hostname."_pid",$uid,1);
			$this->redis->hDel($this->hostname."_pid",$uid);
			$this->redis->delete($this->hostname."_".$uid);
		}
		
		private function setjoblist($uid,$jobList){
			if($jobList == "")
				return;
			//判断uid存在
			$this->redis->lPush($$this->hostname.'_jobList_'.$uid,$jobList);
			//优化考虑杀死worker
		}
		
		private function killself($uid){
			//判断存在
			$this->redis->lPush($this->hostname."_stop_".$uid,1);
		}
			//回调函数
		public function callbackfct($redis, $chan, $msg){
			switch($chan) {
				case $this->hostname :{
				//处理msg   
					$cmdarr = explode('|',$msg);
					switch($cmdarr[0]){
						//处理命令
						case '1':{
							//判断，如果多余参数则调用其他worker类
							$this->createfork($cmdarr[1]);
							break;
						}
						case '2':{
							//强制杀死worker
							$this->killthefork($cmdarr[1]);
							break;
						}
						case '3':{
							//修改关注队列
							$this->setjoblist($cmdarr[1],$cmdarr[2]);
							break;
						}
						case '4':{
							//worker自杀
							$this->killself($cmdarr[1]);
							break;
						}
						default:
							break;
					}
				}
				case 'all' :{
					
				}
			}
		}
		
		public function agentsub(){
			$reskey = array(
				$this->hostname,
				'all'
			);
			$consub = new Conn();
			$redissub = $consub->RedisConByArr($this->file_array);
			if(!$redissub){
				echo "连接失败";
				exit;
			}
			
			//订阅消息，宿主名称通道/全部宿主通道
			$redissub->subscribe($reskey,array($this,'callbackfct'));
		}
	}
?>