<html>
	<head>
		<title>Redirecting...</title>
        <meta charset="UTF-8">
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
        CONST DEFAULT_REDIRECT = "https://sergiocabral.com/";

		ini_set('display_errors', 1);
		ini_set('display_startup_errors', 1);
		error_reporting(E_ALL);

		function error_handler()
		{
		    $error = error_get_last();
			if ($error) {
				header("location:" . DEFAULT_REDIRECT);
			}
		}

		set_error_handler("error_handler");
		register_shutdown_function("error_handler");

		$environment = json_decode(file_get_contents("environment.json"));

		$mysqlServer = "{$environment->database->server}:{$environment->database->port}";
		$mysqlUsername = "{$environment->database->username}";
		$mysqlPassword = "{$environment->database->password}";
		$mysqlDatabase = "{$environment->database->database}";

		$mysqli = new mysqli($mysqlServer, $mysqlUsername, $mysqlPassword, $mysqlDatabase);

        $stmt = $mysqli->prepare( "DESCRIBE `shortener`");
        $isNewDatabase = !$stmt->execute();
        $stmt->close();

        if ($isNewDatabase) {
            $stmt = $mysqli->prepare( "CREATE TABLE `shortener` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `domains` text NOT NULL,
 `shortcut` text NOT NULL,
 `destination` text NOT NULL,
 `counter` int(11) DEFAULT NULL,
 `lastAccess` datetime DEFAULT NULL,
 PRIMARY KEY (`id`)
)");
            $stmt->execute();
            $stmt->close();

            $stmt = $mysqli->prepare( "
CREATE TABLE `access` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `shortenerId` int(11) NOT NULL,
 `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
 `httpReferer` text DEFAULT NULL,
 `remoteHost` text DEFAULT NULL,
 `remoteAddr` text DEFAULT NULL,
 `httpUserAgent` text DEFAULT NULL,
 PRIMARY KEY (`id`)
)");
            $stmt->execute();
            $stmt->close();
        }

        $domain = "{$_SERVER['SERVER_NAME']}";
        $shortcut = "{$_SERVER['QUERY_STRING']}";

        $domainLike = "%[$domain]%";

        $stmt = $mysqli->prepare("
  SELECT `id`, 
         `destination`
    FROM `shortener`
   WHERE `shortcut` = ?
     AND (`domains` = '*' OR `domains` = ? OR `domains` LIKE ?)
ORDER BY `domains` DESC");
        $stmt->bind_param('sss', $shortcut, $domain, $domainLike);
		$stmt->execute();
		$stmt->bind_result($shortenerId, $destination);
		$fetch = $stmt->fetch();
		$stmt->close();

        if (!$fetch || !trim($destination)) {
            $stmt = $mysqli->prepare("
  SELECT `id`, 
         `destination`
    FROM `shortener`
   WHERE `shortcut` = ''
     AND (`domains` = '*' OR `domains` = ? OR `domains` LIKE ?)
ORDER BY `domains` DESC");
            $stmt->bind_param('ss', $domain, $domainLike);
            $stmt->execute();
            $stmt->bind_result($shortenerId, $destination);
            $fetch = $stmt->fetch();
            $stmt->close();
        }

		if ($fetch && trim($destination)) {
			$stmt = $mysqli->prepare("
INSERT
  INTO `access` (
       `shortenerId`,
       `httpReferer`,
       `remoteHost`,
       `remoteAddr`,
       `httpUserAgent`
   ) VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param('dssss', $shortenerId, $httpReferer, $remoteHost, $remoteAddr, $httpUserAgent);
			$httpReferer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
			$remoteHost = $_SERVER['REMOTE_HOST'];
			$remoteAddr = $_SERVER['REMOTE_ADDR'];
			$httpUserAgent = $_SERVER['HTTP_USER_AGENT'];
			$stmt->execute();
			$stmt->close();

			$stmt = $mysqli->prepare("
UPDATE `shortener`
   SET `counter` = COALESCE(`counter`, 0) + 1,
       `lastAccess` = NOW()
 WHERE `shortcut` = ?");
			$stmt->bind_param('s', $shortcut);
			$stmt->execute();
			$stmt->close();
		} ELSE {
            $destination = DEFAULT_REDIRECT;
        }
		
		$mysqli->close();

        header("location:" . $destination);
		?>
	</body>
</html>
