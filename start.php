<?php
	include_once('agent.php');
	include_once('issue.php');
	include_once('conn.php');
	include_once('CheckIssue.php');
	include_once('CheckWorker.php');
	//$szuid = getmypid();
	
	$file_array = parse_ini_file("dmconfig.ini",true);
	$hostname = $file_array['hostname'];
	$con = new Conn();
	$redis = $con->RedisConByArr($file_array);
	if(!$redis){
		echo "start连接失败";
		exit;
	}
	//$redis->set($hostname.'_agent',$szuid);
	
	//echo "agentID:".$szuid;
	//调度worker
	$issuepid = pcntl_fork();
	if($issuepid == -1){
		echo "创建Issue失败,发生错误";
		exit(1);
	}
	if(!$issuepid){
		$issuegpid = pcntl_fork();
		echo "issueID:".$issuegpid;
		if(!$issuegpid){
			$issue = new Issue($file_array);
			$issue->run();
		}
		exit;
		pcntl_wait($status);
	}
	
	//检测进程CheckIssue
	$pid = pcntl_fork();
	if($issuepid == -1){
		echo "创建CheckIssue失败,发生错误";
		exit(1);
	}
	if(!$pid){
		$gpid = pcntl_fork();
		echo "CheckIssueID:".$gpid;
		if(!$gpid){
			$uid = posix_getpid();
			$checkissue = new CheckIssue($file_array);
			$redis->set($hostname.'_issue_ppi',$uid);
			$checkissue->run();			
		}
		exit;
		pcntl_wait($status);
	}
	
	//检测进程CheckWorker
	$pid = pcntl_fork();
	if($issuepid == -1){
		echo "创建CheckWorker失败,发生错误";
		exit(1);
	}
	if(!$pid){
		$gpid = pcntl_fork();
		echo "CheckWorkerID:".$gpid;
		if(!$gpid){
			$uid = posix_getpid();
			$checkworker = new CheckWorker($file_array);
			$redis->set($hostname.'_worker_ppw',$uid);
			$checkworker->run();			
		}
		exit;
		pcntl_wait($status);
	}
	
	//Conn::RedisPclose($redis);
	//$agent = new agent($file_array);
	
	$agent = new agent($file_array);
	$agent->register();
	$agent->agentsub();
?>


