<?php
	include_once('conn.php');
	class Worker{
		private $predis;
		private $workid;
		private $jobkey;
		private $file_array;
		private $checkuid;
		private $timeout;
		private $envData=array();
		private $job_flownum = array();
		private $userInfo;
		private $roleInfo;
		private $jobinfo;
		private $joblistkey;
		private $killselfkey;
		public function __construct($predis,$file_array){
			ini_set('default_socket_timeout', -1);
			$this->timeout = 30;
			$this->workid = posix_getpid();
			$this->file_array = $file_array;
			$this->joblistkey = $file_array['hostname'].'_jobList_'.$this->workid;
			$this->killselfkey = $file_array['hostname'].'_stop_'.$this->workid;
			$this->hostnameworking = $file_array['hostname'].'_working';
			$this->predis = $predis;
			$this->predis->hSet($file_array['hostname']."_pid",$this->workid,time());
		}
		
		public function getworkid(){
			return $this->workid;
		}
		
		public function setworkid($id){
			$this->workid = $id;
		}
		
		public function getname(){
			return $this->hostname;
		}
		
		public function setname($hostname){
			$this->hostname = $hostname;
		}
		public function run(){
			$this->predis->hSet($this->file_array['hostname']."_pid",$this->workid,time());
			//ini_set('default_socket_timeout', -1);				
			while(1){
				$this->job_flownum = $this->predis->blPop($this->joblistkey,$this->killselfkey,$this->jobkey,$this->timeout);
				if($this->job_flownum[0] == $this->joblistkey){
					$this->jobkey = $this->job_flownum[1];
				}else{
					break;
				}
			}
			if($this->job_flownum[0] == $this->killselfkey){
				if($this->job_flownum[1] == 1){
					return 'stop';
				}
			}
			if($this->job_flownum[0] == $this->jobkey){
				//写入redis暂时有任务
				$msg = $this->jobkey.'|'.$this->job_flownum[1];
				$this->predis->hset($this->file_array['hostname'].'_history',$this->workid,$msg);
				$this->predis->hset($this->file_array['hostname'].'_working',$this->workid,$msg);
				//if()  如果没有传其他类，走本类dojob		
				$this->dojob($this->job_flownum);
				//否则
				/*
				include_once();
				$class = new ;
				$class->do($array,$this->job_flownum);
				*/
				//清理job
				$this->predis->hDel($this->file_array['hostname'].'_working',$this->workid);
				
				$result = $this->predis->lPop($this->killselfkey);				
				if($result[1] === 1){
					return 'stop';
				}
			}
			$this->predis->hSet($this->hostnameworkerP,$this->workid,time());
		}
	

		private function dojob($job_flownum){
			//unset($job_flownum);
            //TODO
		}
	}

?>