<?php
/**********************************************************************
      					SECURE LINK SERVICE
-----------------------------------------------------------------------
Author: Alexander Shangin <5kr1p7@gmail.com>    Special for KOGBUZ MIAC
***********************************************************************/

$debug = false;							// Debug flag
$rand = true;							// Generate random passwords?
$pass_l = 15;							// Password length
$hash_l = 6;							// Length of hash code
$sfx = false;							// SFX archive flag
$uploaddir = '/srv/ftp/www/secure/';	// Directory for upload files
$max_file_size = 1024 * 1048576;		// Max file size (MB)
$secret = 'Rh47mf3';					// Salt
$public = false;						// No restricted access
$onlyhash = true;						// Delete original filename
$password = true;						// Password protect

// Reset vars
$error = false;
$message = '';
$debug_info = array();
$file_info = array();
$arc_pass = '';

// RESTRICT ACCESS ---------------------------------------------------------------------------------
if (!$public) {
	$ip = $_SERVER['REMOTE_ADDR'];
//	$allowed = preg_match("/^192\.168\.1\..*$/", $ip);
	$allowed = true;
} else {
	$allowed = true;
}
// -------------------------------------------------------------------------------------------------

setlocale(LC_CTYPE, 'ru_RU.UTF-8');		// For suppord non-en filenames
$md5 = '';								// Clear md5 variable

// Formatting for password output
function NicePassOutput($pass) {
	$out = '';
	$chunks = explode( "\n", rtrim(chunk_split($pass, 3)) );

	foreach ($chunks as $pass_block) {
		$out = $out . '<span class="pass_block">' . rtrim($pass_block)  . '</span>';
	}

	return $out;
}

// Add a numeric appendix to filename
function AddHashFilename($filename) {
	global $hash_l;

	return substr_replace($filename, rand(pow(10, $hash_l) / 10, pow(10, $hash_l) - 1).'-', 0, 0);
}

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
function RePack($arc, $file, $pass = '') {
	global $file_info, $sfx;
	$option = '';

	// Archiving file
	if ($file_info['arc_sfx']) {
		$option = '-sfx ';
	}
	
	if ($pass) {
		$option = $option . '-p' . $pass;
	}

	$cmd = 'LC_CTYPE=ru_RU.UTF-8 7za a -mx0 -mhe=on '. $option . ' "' . addslashes($file_info['arc_upload_h']) . '" "' . addslashes($file_info['filename_upload']) . '"';
	$ret = exec($cmd);

	// If everything is ok - delete original file
	if ( preg_match('/Ok/', $ret) ) {
		unlink($arc);
		touch($file_info['arc_upload_h'], $file_info['expire']);
		return true;
	} else {
		return false;
	}
}


if($_POST['MAX_FILE_SIZE'] && $allowed) {
	if ($_FILES['userfile']['name'] && is_numeric($_POST['exp'])) {

// UPLOAD PROCESS -----------------------------------------------------------------------------------
		// Array with info
		if ($sfx) {
			$file_info['arc_ext'] = '.exe';				// Archive extension
		} else {
			$file_info['arc_ext'] = '.7z';				// Archive extension
		}

		$file_info['arc_sfx'] = $sfx;																					// SFX flag
		$file_info['filename_original']	= basename($_FILES['userfile']['name']);										// Original filename
		$file_info['filename_hashed']	= AddHashFilename(basename($_FILES['userfile']['name']));						// Filename with hash

		// If we need only hash		
		if ($onlyhash) {
			$file_info['filename_hashed'] = str_replace('-'.$file_info['filename_original'], '', $file_info['filename_hashed']);
		}
		
		$file_info['filename_upload']	= $uploaddir . basename($_FILES['userfile']['name']);							// Path of original file
		$file_info['arc_upload_h']	= $uploaddir . StripEx($file_info['filename_hashed']) . $file_info['arc_ext'];		// Path of archive
		$file_info['expire'] = time() + ($_POST['exp'] * 86400);														// Calculate expire
		

		// ------------------------------------------------------------------------------------------

		$uploadfile = $uploaddir . basename($_FILES['userfile']['name']);
		
		if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
			if ($password) {
				if ($rand){
					$arc_pass = GenRandomPass($pass_l);
				} else {
					$arc_pass = substr( filesize($uploadfile) . preg_replace('/[^0-9]/i', '', md5($uploadfile)), 0, $pass_l );
				}
			}
			
			if ($debug) { $debug_info["uploadfile"] = $uploadfile; $debug_info["pass"] = $arc_pass; }
			
			if (Repack($file_info['filename_upload'], $file_info['arc_upload_h'], $arc_pass) ) {
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
		$path = '/secure/' . StripEx($file_info['filename_hashed']) . $file_info['arc_ext'];
		$expire = $file_info['expire'];
		
		$md5 = base64_encode(md5($secret . $path . $expire, true));
		$md5 = strtr($md5, '+/', '-_');
		$md5 = str_replace('=', '', $md5);

		if ($debug) { $debug_info["md5"] = $md5; $debug_info["expire"] = $expire; }
// --------------------------------------------------------------------------------------------------
	} else {
		echo '<div class="error-msg">Некорректный ввод!</div>'."\n";
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta http-equiv="Content-type" content="text/html;charset=UTF-8">
	<title>Генератор безопасных ссылок</title>
	<link rel="stylesheet" href="secure.css">
	<script src="jquery.js"></script>
	<script src="jquery.form.js"></script>
</head>
<body>
	<div id="container">
		<div id="message" style="visibility: <?php echo $message ? 'visible' : 'hidden'; ?>"><?php echo $message; ?></div>
		
		<div id="logo">
			<a href="/sec/"><img src="sec_lock.png" alt="Secure Link" /></a>
			<h1>Генератор защищенных ссылок</h1>
		</div>

		<div class="block">
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
			<br>
    <div class="progress">
        <div class="bar"></div>
        <div class="percent">0%</div>
    </div>

			<div class="block">
<?php
		// PRINT SECURE LINK AND EXPIRE DATE ---------------------------------------------------------------
		if ($debug) {
			echo 'File:		' . $debug_info["uploadfile"] . "<br>\n";
			echo 'Pass:		' . $debug_info["pass"] .		"<br>\n";
			echo 'MD5:		' . $debug_info["md5"] . 		"<br>\n";
			echo 'Expire:	' . date('d.m.Y H:i:s', $debug_info["expire"]+14400) .		"<br>\n";
			echo 'Error:	' . $debug_info["error"] .		"<br>\n";
		}

		if ($md5 && $expire && !$error) {
			$sec_link = 'https://f.medkirov.ru'.$path.'?st='.$md5.'&e='.$expire;

			echo
"				<br>\n" . 
'				<input onclick="this.select();" style="width:392px" class="solid" readonly="readonly" value="'.$sec_link.'"><br><br>' . "\n" . 
'				<center><a target="_blank" href="' . $sec_link . '"\">Открыть защищенную ссылку</a></center><br>' . "\n" . 
'				<div id="exp-date">Ссылка будет работать до: <b>'.date('d.m.Y H:i:s',$expire+14400).'</b></div>' . "<br>\n";

			if ($password) {
				echo '				<div id="pass">Пароль на архив: <b>' . NicePassOutput($arc_pass) . "</b></div>\n";
			}
		}
// -------------------------------------------------------------------------------------------------
?>

			</div>
		</div>
	</div>

<script>
(function() {
    
var progress = $('.progress')
var bar = $('.bar');
var percent = $('.percent');
var container = $('#container');

progress.hide();

$('form').ajaxForm({
    beforeSend: function() {
		progress.show();
        var percentVal = '0%';
        bar.width(percentVal)
        percent.html(percentVal);
    },
    uploadProgress: function(event, position, total, percentComplete) {
        var percentVal = percentComplete + '%';
        bar.width(percentVal)
        percent.html(percentVal);
    },
    success: function() {
        var percentVal = '100%';
        bar.width(percentVal)
        percent.html(percentVal);
    },
	complete: function(xhr) {
		progress.hide();
		container.html(xhr.responseText);
	}
});

})();       
</script>

</body>
</html>
