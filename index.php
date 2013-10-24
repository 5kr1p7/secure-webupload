<?php
$debug = false;							// Debug flag
$rand = false;							// Generate random passwords?
$pass_l = 15;

// Reset vars
$error = false;
$message = "";

// RESTRICT ACCESS ---------------------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'];
$allowed = preg_match("/^192\.168\.1\..*$/", $ip);
// -------------------------------------------------------------------------------------------------

setlocale(LC_CTYPE, 'ru_RU.UTF-8');		// For suppord non-en filenames
$secret = 'Rh47mf3';					// Salt
$md5 = "";								// Clear md5 variable
$uploaddir = '/srv/ftp/www/secure/';
$max_file_size = 15 * 1048576;			// Max file size (MB)

// Generate Password
function GenRandomPass($chars) {
	return rand(pow(10, $chars-1),pow(10, $chars-1)*10-1);
}

// Delete extension
function StripEx($filename) {
// TODO: Replace stupid symbols
//	$ret = preg_replace('/[^a-zA-Zа-яА-Я0-9\(\)\[\]\.\- ]/', '', $filename);
	return preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);
}

// Pack to 7zip with password
function RePack($arc, $pass) {
	// Archiving file
	$ret = exec('LC_CTYPE=ru_RU.UTF-8 7za a -t7z -p'. $pass .' -mhe=on "'. addslashes(StripEx($arc)) .'.7z" "' . addslashes($arc) .'"');

	// If everything is ok - delete original file
	if ( preg_match('/Ok/', $ret) ) {
		unlink($arc);
		return true;
	} else {
		return false;
	}
}


if($_POST['MAX_FILE_SIZE'] && $allowed) {
	if ($_FILES['userfile']['name'] && is_numeric($_POST['exp'])) {

// UPLOAD PROCESS -----------------------------------------------------------------------------------
		$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);

		if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
			if($rand){
				$arc_pass = GenRandomPass($pass_l);
			} else {
				$arc_pass = substr( filesize($uploadfile) . preg_replace('/[^0-9]/i', '', md5($uploadfile)), 0, $pass_l );
			}
			
			if (Repack($uploadfile, $arc_pass) ) {
				$message = '<div class="success-msg">Файл корректен и был успешно загружен.</div>'."\n";
			} else {
				$error = true;
				$message = '<div class="error-msg">Ошибка при упаковке.</div>'."\n";
			}
		} else {
			$message = '<div class="error-msg">Возможная атака с помощью файловой загрузки!</div>'."\n";
		}
// --------------------------------------------------------------------------------------------------

// CREATE SECURE LINK -------------------------------------------------------------------------------
//		$path = '/secure/'.$_FILES['userfile']['name'];
		$path = '/secure/' . StripEx($_FILES['userfile']['name']) . '.7z';
		$expire = time() + (86400 * $_POST['exp']);					// Calculate expire date
		
		$md5 = base64_encode(md5($secret . $path . $expire, true));
		$md5 = strtr($md5, '+/', '-_');
		$md5 = str_replace('=', '', $md5);
// --------------------------------------------------------------------------------------------------
	} else {
		echo '<div class="error-msg">Некорректный ввод!</div>'."\n";
	}
}
?>
<html>
<head>
	<meta http-equiv="Content-type" content="text/html;charset=UTF-8">
	<title>Генератор безопасных ссылок</title>
	<link rel="stylesheet" href="secure.css">
</head>
<body>
	<div id="container">
		<div id="message" style="visibility: <?php echo $message ? 'visible' : 'hidden'; ?>">
			<?php echo $message; ?>
		</div>
		
		<div id="logo">
			<a href="/sec/"><img src="sec_lock.png" /></a>
			<h1>Генератор защищенных ссылок</h1>
		</div>

		<div id="uploader">
<?php
		// CHECK ACCESS ----------------------------------------------------------------------------
		if (!$allowed) {
			echo "\t<center>Доступ запрещен</center>\n\t</div>\n</div>\n</body>\n</html>";
			return 0;
		}
		// -----------------------------------------------------------------------------------------
?>
			<form enctype="multipart/form-data" action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" method="POST">
				<input type="hidden" name="MAX_FILE_SIZE" value="<?php echo $max_file_size; ?>" />
				<input name="userfile" type="file" /><br>
				<label for="exp">Время жизни файла (дней): </label><input maxlength="3" size="1" id="exp" name="exp" class="solid" value="1"><br><br>
				<input id="btn-create" type="submit" value="Создать ссылку" />
			</form>
			<div id="links">
<?php
		// PRINT SECURE LINK AND EXPIRE DATE ---------------------------------------------------------------
		if($debug) {
			echo "Pass:		$arc_pass<br>\n";
			echo "MD5:		$md5<br>\n";
			echo "Expire:	$expire<br>\n";
			echo "Error:	$error<br>\n";
		}

		if ($md5 && $expire && !$error) {
			$sec_link = 'https://f.medkirov.ru'.$path.'?st='.$md5.'&e='.$expire;

			echo '<input onclick="this.select();" class="solid" readonly="readonly" style="width:100%" value="'.$sec_link.'"><br>'."\n";
			echo '<br><center><a target="_blank" href="' . $sec_link . '"\">Открыть защищенную ссылку</a></center><br>'."\n";
			echo '<div id="exp-date">Ссылка будет работать до: <b>'.date('d.m.Y H:i:s',$expire+14400).'</b></div>'."<br>\n";
			echo '<div id="pass">Пароль на архив: <b>' . $arc_pass . "</b></div>\n";
		}
// -------------------------------------------------------------------------------------------------
?>
			</div>
		</div>


	</div>
</body>
</html>
