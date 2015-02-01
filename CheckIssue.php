<?php
	include_once('issue.php');
	include_once('agent.php');
	include_once('CheckWorker.php');
	include_once('conn.php');
	class CheckIssue{
		private $predis;
		private $timeout;
		private $hostnameworkerP;
		private $hostnamepiid;
		private $hostnameissueing;
		private $hostnameissueP;
		private $file_array;
		public function __construct($file_array){
			$con = new Conn();
			$this->predis = $con->RedisPConByArr($file_array);
			if(!$this->predis){
				echo "CheckIssue连接失败";
				exit;
			}
			$this->timeout = 150;	
			$this->file_array = $file_array;
			$this->hostnameagenting = $file_array['hostname'].'_agenting';
			$this->hostnameworkerP = $file_array['hostname'].'_worker_ppw';
			$this->hostnameissueP = $file_array['hostname'].'_issue_ppi';
			$this->hostnameissueing = $file_array['hostname'].'_issueing';
			echo "CheckIssue连接成功";
		}
		public function changeRedisConnect($redisarr,$is_pcon){
			
			//$this->predis = $predis;
		}
		public function run(){
			$nowtime = 0;
			try{
				while(1){
					$this->predis->set($this->hostnameissueP,time());
					$nowtime = time()-$this->timeout;
					$this->checkppw();
					//$this->checksu();
					//获取当前worker进程ID及其上次注册时间
					$pids = $this->predis->hGetAll($this->hostnameissueing);
					//$count = count($pids);
					foreach($pids as $uid => $uval){
						if($uval < $nowtime){
							//超时情况 默认当前worker已死，杀死worker 转移其JOB（是否创建新的worker，目前否）
							$workarr = explode('|',$uval);
							if($timeout > $workarr[0]){
								exec("kill ".$uid);
								$rediserr = Conn::RedisCon($workarr[1],$workarr[2],$workarr[3],$workarr[4]);
								//取出job从头插入jobList
								$this->predis->rPush($workarr[5],$workarr[6]);
								$this->predis->hDel($this->hostnameissueing,$uid);
							}
						}
					}
					sleep(1);
				}
			}
			catch (Exception $e){
				//return '错误';
				exit(1);
			}
		}
		
		private function checksu(){
			try{
				$agenting = $this->predis->get($this->hostnameagenting);
				if(time()-$agenting > $this->timeout){
					echo "agent die";
					$pid = pcntl_fork();
					if($pid == -1){
						echo "创建agent失败,发生错误";
						exit(1);
					}
					if(!$pid){
						$gpid = pcntl_fork();
						if(!$gpid){
							echo "create new agent";
							$uid = posix_getpid();
							$agent = new agent($this->file_array);
							$agent->register();
							$agent->agentsub();
						}
						exit;
						pcntl_wait($status);
					}
				}
			}
			catch (Exception $e) {
				//return '错误';
				return 0;
			}
		}
		
		private function checkppw(){
			try{
				$workertime = $this->predis->get($this->hostnameworkerP);
				if(time()-$workertime > $this->timeout){
					echo "CheckWorker die";
					$pid = pcntl_fork();
					if($pid == -1){
						echo "创建checkworker失败,发生错误";
						exit(1);
					}
					if(!$pid){
						$gpid = pcntl_fork();
						if(!$gpid){
							$uid = posix_getpid();
							//心跳worker检测
							$checkWorker = new CheckWorker($this->file_array);
						}
						exit;
						pcntl_wait($status);
					}
				}
			}
			catch (Exception $e) {
				//return '错误';
				return 0;
			}
		}
	}
?>