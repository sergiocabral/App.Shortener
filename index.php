<html>
	<head>
		<title>
			Redirecionando...
		</title>
		<style>
			html {
				color: white;
				background-color: #231F20;
				background-image: url(loading.gif);
				background-repeat: no-repeat;
				background-position: center;
			}
		</style>
	</head>
<body>
	<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

function error_handler()
{
	    if (error_get_last()) {
		            header("location:https://www.jw.org/");
			        }
}
set_error_handler("error_handler");
register_shutdown_function("error_handler");

$de = $_SERVER['QUERY_STRING'];

$mysqli = new mysqli('server', 'username', 'password', 'database');

$stmt = $mysqli->prepare("SELECT id, para, contador, ultimo_acesso FROM de_para WHERE de = ?");
$stmt->bind_param('s', $de);
$stmt->execute();
$stmt->bind_result($de_para_id, $para, $contador, $ultimo_acesso);
$fetch = $stmt->fetch();
$stmt->close();

if ($fetch && trim($para)) {
	    $stmt = $mysqli->prepare("INSERT INTO acessos (de_para_id, HTTP_REFERER, REMOTE_HOST, REMOTE_ADDR, HTTP_USER_AGENT) VALUES (?, ?, ?, ?, ?)");
	        $stmt->bind_param('dssss', $de_para_id, $http_referer, $remote_host, $remote_addr, $http_user_agent);
	        $http_referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
		    $remote_host = $_SERVER['REMOTE_HOST'];
		    $remote_addr = $_SERVER['REMOTE_ADDR'];
		        $http_user_agent = $_SERVER['HTTP_USER_AGENT'];
		        $stmt->execute();
			    $stmt->close();

			    $stmt = $mysqli->prepare("UPDATE de_para SET contador = COALESCE(contador, 0) + 1, ultimo_acesso = NOW() WHERE de = ?");
			        $stmt->bind_param('s', $de);
			        $stmt->execute();
				    $stmt->close();

				    header("location:" . $para);
} else {
	    throw new Exception(-1);
}

$mysqli->close();
?>
</body>
</html>
