<?php
function PrintFile($str) {
	$str = date("[Y-m-d H:i:s] ") . "{$str}\n";
	echo $str;
}

if(PHP_SAPI !== 'cli') {
?>
<html lang="en">
	<head>
		<title>403 Forbidden</title>
		<style type="text/css">
			body {
				background: #F1F1F1;
				font-weight: 100 ! important;
				padding: 32px;
			}
			h1 {
				font-weight: 100 ! important;
			}
			logo {
				font-size: 100px;
			}
		</style>
	</head>
	<body>
		<logo>:(</logo>
		<h1>403 Forbidden</h1>
		<p><b>Error:</b> Plugin is disabled in php-fpm mode.</p>
		<p><em>Powered by ZeroDream</em></p>
	</body>
</html>
<?php
	exit;
}

$pointStorage = new Swoole\Table(2048);
$pointStorage->column('id', swoole_table::TYPE_INT, 4);
$pointStorage->column('name', swoole_table::TYPE_STRING, 128);
$pointStorage->column('point', swoole_table::TYPE_FLOAT);
$pointStorage->column('health', swoole_table::TYPE_FLOAT);
$pointStorage->create();

$fidStorage = new Swoole\Table(2048);
$fidStorage->column('id', swoole_table::TYPE_INT, 4);
$fidStorage->column('client', swoole_table::TYPE_INT, 4);
$fidStorage->create();

$server = new swoole_websocket_server("0.0.0.0", 9235);
$server->pointStorage = $pointStorage;
$server->fidStorage = $fidStorage;

$server->on('open', function (swoole_websocket_server $server, $request) {
    PrintFile("Client {$request->fd} connected");
});

$server->on('message', function (swoole_websocket_server $server, $frame) {
	$rawdata = $frame->data;
	$data = json_decode($rawdata, true);
	if($data) {
		if(isset($data['action'])) {
			switch($data['action']) {
				case 'connect':
					if(isset($data['sid']) && preg_match("/^[0-9]{1,5}$/", $data['sid'])) {
						if(!$server->fidStorage->get($frame->fd)) {
							$server->fidStorage->set($frame->fd, Array('client' => Intval($data['sid'])));
							PrintFile("Client {$frame->fd} connect to server ID {$data['sid']}");
						} else {
							PrintFile("Warning: Client {$frame->fd} trying to connect another server ID {$data['sid']}, blocked.");
						}
					}
					break;
				case 'point':
					if(isset($data['sid']) && preg_match("/^[0-9]{1,5}$/", $data['sid'])) {
						$client = $server->fidStorage->get($frame->fd, 'client');
						$point = $server->pointStorage->get(Intval($client), 'point');
						if($client == Intval($data['sid'])) {
							$server->pointStorage->set(Intval($data['sid']), [
								'id' => Intval($data['sid']),
								'name' => substr($data['name'], 0, 128),
								'point' => Intval($data['point']),
								'health' => Floatval($data['health'])
							]);
						} else {
							PrintFile("Warning: Client {$frame->fd} correctly server ID is {$client}, but trying to set the ID {$data['sid']} player's drift point");
						}
					}
					break;
				case "getpoint":
					$list = [];
					foreach($server->pointStorage as $point) {
						$list[] = $point;
					}
					$server->push($frame->fd, json_encode($list));
					break;
			}
		}
	}
});

$server->on('close', function (swoole_websocket_server $server, $fd) {
	PrintFile("Client {$fd} offline");
	$fid = $server->fidStorage->get($fd, 'client');
	if($fid) {
		$server->fidStorage->del($fd);
		$server->pointStorage->del($fid);
	}
});
$server->start();
