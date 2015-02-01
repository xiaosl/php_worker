<?php
	include_once('conn.php');
	ini_set('default_socket_timeout', -1);
	
	class Issue{
		private $predis;
		private $jobFlows;
		private $jobLists;
		private $timeout;
		private $jobListSub;
		private $issueid;
		private $hostnameissueing;
		public function __construct($file_array){
			$con = new Conn();
			$this->predis = $con->RedisPConByArr($file_array);
			if(!$this->predis){
				echo "连接失败";
				exit;
			}
			$this->timeout = 30;
			$this->hostnameissueing = $file_array['hostname'].'_issueing';
			$this->jobListSub = $file_array['joblist'];
			$this->issueid = posix_getpid();
			while(1){
				$this->jobFlows = $this->predis->hGetAll('EVENT_QUEUE_CONFIG_JOB_LIST');
				$this->jobLists = $this->predis->hGetAll('WORK_JOB_LIST');
				if(count($this->jobFlows)>0){
					break;
				}
				else{
					echo "JOB列表为空，请导入JOB列表";
					sleep(1);
				}
			}
		}
		public function run(){
		//读队列
			$job_IDmsg;
			$myvc;
			$joblist;
			while(1){
				$this->predis->hSet($this->hostnameissueing,$this->issueid,time());
				$job_IDmsg = $this->predis->blPop($this->jobListSub,$this->timeout);
				$job_IDmsg = unserialize($job_IDmsg[1]);
				if(isset($this->jobFlows[$job_IDmsg['jobtype']])){
					$joblist = trim($this->jobLists[$job_IDmsg['joblist']]);
					$job_flownums = explode('|',$this->jobFlows[$job_IDmsg['jobtype']]);
					if(count($job_flownums)>1){
						$myvc = $this->predis->hGetAll($job_IDmsg['job_flownum']);
						foreach($job_flownums as $value){
							$flowID = time().md5(uniqid(mt_rand(), true)).rand();
							$this->predis->hMSet($flowID,$myvc);
							$this->predis->hSet($flowID,'flowid',$value);
							$this->predis->lPush($joblist,$flowID);
							echo $joblist."队列".$flowID;
						}
						$this->predis->delete($job_IDmsg['job_flownum']);
					}
					else{
						$this->predis->hSet($job_IDmsg['job_flownum'],'flowid',$this->jobFlows[$job_IDmsg['jobtype']]);
						$this->predis->lPush($joblist,$job_IDmsg['job_flownum']);
					}
				}
				//输入时间
			}
		}
	}
?>