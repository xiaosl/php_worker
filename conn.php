<?php
	class Conn{
		public function RedisCon($ip,$port,$pwd,$rdb){
			try{
				$redis = new Redis();
				$redis->connect($ip,$port);
				if($pwd != 0){
					$auth = $redis->auth($pwd);
				}
				if($rdb){
					$redis->select($rdb);
				}
				return $redis;
			}
			catch (Exception $e) {
				return 0;
			}
		}
		
		public function RedisConByArr($redisarr){
			try{
				if(is_array($redisarr)){
					$redis = new Redis();
					$redis->connect($redisarr['rip'],$redisarr['rport']);
					if($redisarr['rpwd'] != 0){
						$auth = $redis->auth($redisarr['rpwd']);
					}
					if($redisarr['rdb']){
						$redis->select($redisarr['rdb']);
					}
					return $redis;

				}
				return 0;
			}
			catch (Exception $e) {
				//return '连接失败';
				return 0;
			}
		}
		
		
		public function RedisPcon($ip,$port,$pwd,$rdb){
			try{
				$redis = new Redis();
				$redis->pconnect($ip,$port);
				if($pwd != 0){
					$auth = $redis->auth($pwd);
				}
				if($rdb){
					$redis->select($rdb);
				}
				return $redis;
			}
			catch (Exception $e){
				//return '连接失败';
				return 0;
			}
		}
		
		public function RedisPconByArr($redisarr){
			try{
				if(is_array($redisarr)){
					$redis = new Redis();
					$redis->pconnect($redisarr['rip'],$redisarr['rport']);
					if($redisarr['rpwd'] != 0){
						$auth = $redis->auth($redisarr['rpwd']);
					}
					if($redisarr['rdb']){
						$redis->select($redisarr['rdb']);
					}
					echo "创建成功";
					return $redis;
				}
				echo "非数组";
				return 0;
			}
			catch (Exception $e) {
				//return '连接失败';
				return 0;
			}
		}
		
		public function RedisPclose(Redis $redis){
			try{
				$redis->close();
				return 1;
			}
			catch (Exception $e) {
				//return '连接失败';
				return 0;
			}
		}
		
		public function RedisSelectDb(Redis $redis,$rdb = 0){
			try{
				$redis->select($rdb);
				return $redis;
			}
			catch (Exception $e) {
				//return '连接失败';
			}
		}
	}
?>