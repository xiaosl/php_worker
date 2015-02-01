<?php
	include_once('CheckIssue.php');
	include_once('conn.php');
	class CheckWorker{
		private $predis;
		private $timeout;
		private $hostnameworking;
		private $hostnameissueP;
		private	$hostnameworkerP;
		private $file_array;
		public function __construct($file_array){
			$con = new Conn();
			$this->predis = $con->RedisPConByArr($file_array);
			if(!$this->predis){
				echo "CheckWorker连接失败";
				exit;
			}
			$this->file_array = $file_array;
			$this->hostnameworkerP = $file_array['hostname'].'_worker_ppw';
			$this->hostnameissueP = $file_array['hostname'].'_issue_ppi';
			$this->hostnameworking = $file_array['hostname'].'_working';
			$this->timeout = 150;
		}
		
		public function run(){
			$nowtime = 0;
			try{
				while(1){
					$this->predis->set($this->hostnameworkerP,time());
					$this->checkppi();
					$nowtime = time()-$this->timeout;
					//获取当前worker进程ID及其上次注册时间
					$pids = $this->predis->hGetAll($this->hostnameworking);
					//$count = count($pids);
					foreach($pids as $uid => $uval){
						if($uval < $nowtime){
							//超时情况 默认当前worker已死，杀死worker 转移其JOB（是否创建新的worker，目前否）
							$workarr = explode('|',$uval);
							if($timeout > $workarr[0]){
								exec("kill ".$uid);
								//$rediserr = Conn::RedisCon($workarr[1],$workarr[2],$workarr[3],$workarr[4]);
								//取出job从头插入jobList
								$this->predis->rPush($workarr[5],$workarr[6]);
								$this->predis->hDel($this->hostnameworking,$uid);
							}
						}
					}
					sleep(1);
				}
			}
			catch (Exception $e) {
				//return '错误';
				exit(1);
			}
		}
		
		private function checkppi(){
			try{
				$issuetime = $this->predis->get($this->hostnameissueP);
				if(time()-$issuetime > $this->timeout){
					echo "CheckIssue die";
					$pid = pcntl_fork();
					if($pid == -1){
						echo "创建checkIssue失败,发生错误";
						exit(1);
					}
					if(!$pid){
						$gpid = pcntl_fork();
						if(!$gpid){
							$uid = posix_getpid();
							//心跳worker检测
							$checkIssue = new CheckIssue($this->file_array);
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