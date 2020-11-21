<html>
	<head>
		<title>Redirecting...</title>
        <meta charset="UTF-8" />
        <meta name="version" content="<?php echo trim(file_get_contents('VERSION')) ?>" />
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
        require "environment.php";

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

		$mysqlServer = DATABASE_SERVER . ":" . DATABASE_PORT;
		$mysqlUsername = DATABASE_USERNAME;
		$mysqlPassword = DATABASE_PASSWORD;
		$mysqlDatabase = DATABASE_NAME;

		$mysqli = new mysqli($mysqlServer, $mysqlUsername, $mysqlPassword, $mysqlDatabase);

        $stmt = $mysqli->prepare( "DESCRIBE `shortener`");
        $isNewDatabase = !$stmt->execute();
        $stmt->close();

        if ($isNewDatabase) {
            $stmt = $mysqli->prepare( "CREATE TABLE `shortener` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `enabled` int(1) NOT NULL DEFAULT 1,
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
 `shortenerId` int(11) DEFAULT NULL,
 `timestamp` datetime NOT NULL DEFAULT current_timestamp(),
 `url` text NOT NULL,
 `destination` text NOT NULL,
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
   WHERE `enabled` <> 0
     AND `shortcut` = ?
     AND (`domains` = '' OR `domains` = ? OR `domains` LIKE ?)
ORDER BY `domains` DESC");
        $stmt->bind_param('sss', $shortcut, $domain, $domainLike);
		$stmt->execute();
		$stmt->bind_result($shortenerId, $destination);
		$fetch = $stmt->fetch();
		$stmt->close();

        if (!$shortenerId) {
            $stmt = $mysqli->prepare("
  SELECT `id`, 
         `destination`
    FROM `shortener`
   WHERE `enabled` <> 0
     AND `shortcut` = ''
     AND (`domains` = '' OR `domains` = ? OR `domains` LIKE ?)
ORDER BY `domains` DESC");
            $stmt->bind_param('ss', $domain, $domainLike);
            $stmt->execute();
            $stmt->bind_result($shortenerId, $destination);
            $fetch = $stmt->fetch();
            $stmt->close();
        }

		if ($shortenerId) {
            $stmt = $mysqli->prepare("
UPDATE `shortener`
   SET `counter` = COALESCE(`counter`, 0) + 1,
       `lastAccess` = NOW()
 WHERE `id` = ?");
            $stmt->bind_param('d', $shortenerId);
            $stmt->execute();
            $stmt->close();
		}

		if (!$destination) {
            $destination = DEFAULT_REDIRECT;
        }

        $stmt = $mysqli->prepare("
INSERT
  INTO `access` (
       `shortenerId`,
       `url`,
       `destination`,
       `httpReferer`,
       `remoteHost`,
       `remoteAddr`,
       `httpUserAgent`
   ) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('dssssss', $shortenerId, $url, $destination, $httpReferer, $remoteHost, $remoteAddr, $httpUserAgent);
        $httpReferer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on';
        $serverPort = (!$isHttps && $_SERVER['SERVER_PORT'] == 80) || ($isHttps && $_SERVER['SERVER_PORT'] == 443) ? "" : ":{$_SERVER['SERVER_PORT']}";
        $url = ($isHttps ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}{$serverPort}{$_SERVER['REQUEST_URI']}";
        $remoteHost = $_SERVER['REMOTE_HOST'];
        $remoteAddr = $_SERVER['REMOTE_ADDR'];
        $httpUserAgent = $_SERVER['HTTP_USER_AGENT'];
        $stmt->execute();
        $stmt->close();

        $mysqli->close();

        header("location:" . $destination);
		?>
	</body>
</html>
