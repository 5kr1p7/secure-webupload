<?php
// RESTRICT ACCESS ---------------------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'];
$allowed = preg_match("/^192\.168\.1\..*$/", $ip);
// -------------------------------------------------------------------------------------------------

setlocale(LC_CTYPE, 'ru_RU.UTF-8');		// For suppord non-en filenames
$secret = 'Rh47mf3';					// Salt
$md5 = "";								// Clear md5 variable
$uploaddir = '/srv/ftp/www/secure/';
$max_file_size = 15 * 1048576;			// Max file size (MB)
$pass_l = 15;

// Delete extension
function StripEx($filename) {
// TODO: Replace stupid symbols
//	$ret = preg_replace('/[^a-zA-Zа-яА-Я0-9\(\)\[\]\.\- ]/', '', $filename);
	return preg_replace('/\.[a-zA-Z0-9]*$/', '', $filename);
}


function RePack($arc, $pass) {
	// Archiving file
	$ret = exec('LC_CTYPE=ru_RU.UTF-8 7za a -t7z -p'. $pass .' -mhe=on "'. StripEx($arc) .'.7z" "' . $arc .'"');

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

		echo '<pre>';
		if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
			$arc_pass = substr( filesize($uploadfile) . preg_replace('/[^0-9]/i', '', md5($uploadfile)), 0, $pass_l );
			if (Repack($uploadfile, $arc_pass) ) {
				echo '<div class="success-msg">Файл корректен и был успешно загружен.</div>'."\n";
			} else {
				echo '<div class="error-msg">Ошибка при упаковке.</div>'."\n";
			}
		} else {
			echo "Возможная атака с помощью файловой загрузки!\n";
		}
		print "</pre>";
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
		<h1>Генератор защищенных ссылок</h1>
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
				<label for="exp">Время жизни файла (дней): </label><input maxlength="3" size="5" id="exp" name="exp" class="solid" value="1"><br><br>
				<input id="btn-create" type="submit" value="Создать ссылку" />
			</form>
<?php
		// PRINT SECURE LINK AND EXPIRE DATE ---------------------------------------------------------------
		if ($md5 && $expire) {
			$sec_link = 'https://f.medkirov.ru'.$path.'?st='.$md5.'&e='.$expire;

			echo '<input onclick="this.select();" class="solid" readonly="readonly" style="width:100%" value="'.$sec_link.'"><br>'."\n";
			echo '<br><center><a target="_blank" href="' . $sec_link . '"\">Открыть защищенную ссылку</a></center><br>'."\n";
			echo '<div id="exp-date">Ссылка будет работать до: <b>'.date('d.m.Y H:i:s',$expire+14400).'</b></div>'."<br>\n";
			echo '<div id="pass">Пароль на архив: <b>' . $arc_pass . "</b><br>\n";
}
// -------------------------------------------------------------------------------------------------
?>
		</div>
	</div>
</body>
</html>
