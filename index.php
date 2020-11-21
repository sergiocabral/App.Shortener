<html>
	<head>
		<title>
			Redirecting...
		</title>
		<style>
			html {
				color: white;
				background-color: #231F20;
				background-image: url(loading.gif);
				background-repeat: no-repeat;
				background-position: center;
				background-size: cover;
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
		    $error = error_get_last();
			if ($error) {
				header("location:https://sergiocabral.com/");
			}
		}

		set_error_handler("error_handler");
		register_shutdown_function("error_handler");

		$works = false;

		$shortcut = $_SERVER['QUERY_STRING'];

		$mysqlServer = "server";
		$mysqlUsername = "username";
		$mysqlPassword = "password";
		$mysqlDatabase = "database";

		$mysqli = new mysqli($mysqlServer, $mysqlUsername, $mysqlPassword, $mysqlDatabase);

		$stmt = $mysqli->prepare("SELECT `id`, `destination`, `counter`, `lastAccess` FROM `shortener` WHERE `shortcut` = ?");
		$stmt->bind_param('s', $shortcut);
		$stmt->execute();
		$stmt->bind_result($shortenerId, $destination, $counter, $lastAccess);
		$fetch = $stmt->fetch();
		$stmt->close();

		if ($fetch && trim($destination)) {
			$stmt = $mysqli->prepare("INSERT INTO `access` (`shortenerId`, `httpReferer`, `remoteHost`, `remoteAddr`, `httpUserAgent`) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('dssss', $shortenerId, $httpReferer, $remoteHost, $remoteAddr, $httpUserAgent);
			$httpReferer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
			$remoteHost = $_SERVER['REMOTE_HOST'];
			$remoteAddr = $_SERVER['REMOTE_ADDR'];
			$httpUserAgent = $_SERVER['HTTP_USER_AGENT'];
			$stmt->execute();
			$stmt->close();

			$stmt = $mysqli->prepare("UPDATE `shortener` SET `counter` = COALESCE(`counter`, 0) + 1, `lastAccess` = NOW() WHERE `shortcut` = ?");
			$stmt->bind_param('s', $shortcut);
			$stmt->execute();
			$stmt->close();

			header("location:" . $destination);
			$works = true;
		}
		
		$mysqli->close();

		if (!$works) throw new Exception(-1);

		?>
	</body>
</html>
