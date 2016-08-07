<?php
/*
Plugin Name: File Provider
Plugin URI: https://github.com/dimdavid/file-provider
Description: It offers files on your page neatly. Enables direct editing descriptions, conduct upload directly by the front-end, differentiate public or private visibility and protects the actual link of the file to download.
Version: 1.0.0
Author: dimdavid
Author URI: http://dimdavid.wordpress.com/
License: GPLv2 or later
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'DimdavidFileProvider' ) ) :

class DimdavidFileProvider {

	const VERSION = '1.0.0';
	protected static $instance = null;

	protected $pluginFilesPath = '';
	protected $themeFilePath = '';
	protected $themeUri = '';
	protected $pluginFileUri = '';
	
	protected function __construct() {
		if (is_admin()){
			$this->admin_includes();
			$this->admin_init_actions();
			$this->admin_init();
		}
		$this->includes();
		$this->init_actions();
		$this->init_values();
		$this->check_database();
	}
	
	private function admin_includes(){
		include_once dirname( __FILE__ ) . '/includes/file-provider-admin.php';
	}
	
	private function admin_init(){
		$fpAdmin = new DimdavidFileProviderAdmin();
	}
	
	private function admin_init_actions(){
		add_action('wp_ajax_dfp_save',array($this,'save_descr'));
		add_action('wp_ajax_dfp_rename_folder', array($this, 'rename_folder'));
		add_action('wp_ajax_dfp_download_file', array($this, 'download_file'));
	}
	
	private function includes(){
	}
	
	private function init_actions(){
		add_shortcode('file_provider', array($this, 'show'));
		add_action('wp_ajax_nopriv_dfp_download_file', array($this, 'download_file'));
	}
	
	private function init_values(){
		$this->pluginFilesPath = plugin_dir_path( __FILE__ );
		$this->themeFilePath = get_template_directory() . '/file-provider/';
		$this->themeUri = get_template_directory_uri() . '/file-provider/';
		$this->pluginUri = plugins_url( 'file-provider/', dirname(__FILE__) );
	}
	
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function download_file(){
		$fileId = $_GET['fileId'];
		$filePath = $this->get_file_path($fileId);
		$fileName = end(explode('/', $filePath));
		$groupId = $this->get_file_group($fileId);
		if($this->isRestricted($groupId)){
			$this->saveDownloadLog($fileName, $groupId);
		}
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=".$fileName);
		die(readfile($filePath));
	}
	
	public function saveDownloadLog($fileName, $groupId){
		$groupName = $this->get_group_name($groupId);
		$user = wp_get_current_user();
		$upload_dir = wp_upload_dir();		
		$filename = $upload_dir['basedir'] . '/downloadLog_' . $groupName . '.csv';
		$time = date("d/m/Y - H:i:s");
		$linha = $time . '; Usuário: ' . $user->display_name . '; Login: ' . $user->user_login . '; E-mail: ' . $user->user_email . '; Arquivo acessado: ' . $fileName . '
';
		$hand = fopen($filename, 'a');
		fwrite($hand, $linha);
		fclose($hand);
	
	}
	
	public function get_download_log_url($groupId){
		$groupName = $this->get_group_name($groupId);
		$upload_dir = wp_upload_dir();		
		$url = $upload_dir['baseurl'] . '/downloadLog_' . $groupName . '.csv';
		return $url;
	}
	
	public function get_file_group($fileId){
		global $wpdb;
		return $wpdb->get_var('select `dfp_group_id` from ' . $wpdb->prefix . 'dfp_files where `id`=' . $fileId);
	}
	
	public function get_group_name($groupId){
		global $wpdb;
		return $wpdb->get_var('select `name` from ' . $wpdb->prefix . 'dfp_group where `id`=' . $groupId);
	}
	
	protected function get_plugin_file($fileName){
		$filePath = $this->themeFilePath . $fileName;
		if (!file_exists($filePath)){
			$fileUri = $this->pluginUri . $fileName;
		} else {
			$fileUri = $this->themeUri . $fileName;
		}
		return $fileUri;
	}

	protected function get_plugin_file_icon($ext){
		$fileName = 'images/' . $ext . '.png';
		$filePath = $this->themeFilePath . $fileName;
		if (!file_exists($filePath)){
			$filePath = $this->pluginFilesPath . $fileName;
			if (file_exists($filePath)){
				$fileUri = $this->pluginUri . $fileName;
			} else {
				$fileUri = $this->pluginUri . 'images/unknow.png';
			}
		} else {
			$fileUri = $this->themeUri . $fileName;
		}
		return $fileUri;
	}

	protected function read_plugin_file($fileName){
		$filePath = $this->themeFilePath . $fileName;
		if (!file_exists($filePath)){
			$filePath = $this->pluginFilesPath . $fileName;
		}
		$handle = fopen($filePath, "r");
		$conteudo = fread($handle, filesize ($filePath));
		fclose($handle);
		return $conteudo;
	}

	protected function script(){
		$jsContent = $this->read_plugin_file('functions.js');
		$folderClose = 'url(' . $this->get_plugin_file('images/folder.png') . ')';
		$folderOpen = 'url(' . $this->get_plugin_file('images/folder2.png') . ')';
		$html = '
<script>
function sfmOpenClose(idElement){
	iconName = "icon_" + idElement;
	fo = \'' . $folderOpen . '\';
	fc = \'' . $folderClose . '\';
	el = document.getElementById(iconName);
	if (el.style.backgroundImage == fo){
		el.style.backgroundImage = fc;
	} else {
		el.style.backgroundImage = fo;
	}
}

function sfmEdit(fileId){
	descr = document.getElementById("d" + fileId);
	edit = document.getElementById("e" + fileId);
	field = document.getElementById("f" + fileId);
	save = document.getElementById("s" + fileId);
	cancel = document.getElementById("c" + fileId);
	descr.style.display = "none";
	edit.style.display = "none";
	field.style.display = "block";
	save.style.display = "block";
	cancel.style.display = "block";
	field.focus();
}

function sfmFolderEdit(folderId){
	divName = document.getElementById("fn" + folderId);
	inputName = document.getElementById("ifn" + folderId);
	editNameBut = document.getElementById("efn" + folderId);
	saveNameBut = document.getElementById("sfn" + folderId);
	cancelNameBut = document.getElementById("cfn" + folderId);
	divName.style.display = "none";
	editNameBut.style.display = "none";
	inputName.style.display = "block";
	saveNameBut.style.display = "block";
	cancelNameBut.style.display = "block";
	inputName.focus();
}

function sfmFolderCancel(folderId){
	divName = document.getElementById("fn" + folderId);
	inputName = document.getElementById("ifn" + folderId);
	editNameBut = document.getElementById("efn" + folderId);
	saveNameBut = document.getElementById("sfn" + folderId);
	cancelNameBut = document.getElementById("cfn" + folderId);
	divName.style.display = "block";
	editNameBut.style.display = "block";
	inputName.style.display = "none";
	saveNameBut.style.display = "none";
	cancelNameBut.style.display = "none";
}

function sfmFolderSave(folderId, groupId){
	divName = document.getElementById("fn" + folderId);
	inputName = document.getElementById("ifn" + folderId);
	editNameBut = document.getElementById("efn" + folderId);
	saveNameBut = document.getElementById("sfn" + folderId);
	cancelNameBut = document.getElementById("cfn" + folderId);
	
	var url = "' . get_bloginfo('url') . '/wp-admin/admin-ajax.php";
	var data = "action=dfp_rename_folder&groupId=" + groupId + "&folderId=" + folderId + "&newFolderName=" + inputName.value;
	var xhttp=new XMLHttpRequest();
	xhttp.open("POST", url, false);
	xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest");
	xhttp.send(data);
	
	divName.innerHTML = inputName.value;
	divName.style.display = "block";
	editNameBut.style.display = "block";
	inputName.style.display = "none";
	saveNameBut.style.display = "none";
	cancelNameBut.style.display = "none";
}

function sfmCancel(fileId){
	descr = document.getElementById("d" + fileId);
	edit = document.getElementById("e" + fileId);
	field = document.getElementById("f" + fileId);
	save = document.getElementById("s" + fileId);
	cancel = document.getElementById("c" + fileId);
	descr.style.display = "block";
	edit.style.display = "block";
	field.style.display = "none";
	save.style.display = "none";
	cancel.style.display = "none";
}

function sfmSave(fileId){
	descr = document.getElementById("d" + fileId);
	edit = document.getElementById("e" + fileId);
	field = document.getElementById("f" + fileId);
	save = document.getElementById("s" + fileId);
	cancel = document.getElementById("c" + fileId);
	var url = "' . get_bloginfo('url') . '/wp-admin/admin-ajax.php";
	var data = "action=dfp_save&fileDescr=" + field.value + "&fileId=" + fileId;
	var xhttp=new XMLHttpRequest();
	xhttp.open("POST", url, false);
	xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
    xhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest");
	xhttp.send(data);
	descr.innerText = field.value;
	descr.style.display = "block";
	edit.style.display = "block";
	field.style.display = "none";
	save.style.display = "none";
	cancel.style.display = "none";
}

function findFiles(){
	var html = document.getElementById("search_results");
	html.innerHTML = "";
	var strFind = document.getElementById("search_finder").value;
	var results = new Array();
	var words = strFind.split(" ");
	var files = document.getElementsByClassName("file-item");
	var i;
	var fileTitle;
	var fileDescr;
	var found = "";
	var arrays = new Array();
	for (i = 0; i < files.length; i++) {
		arrays = files[i].getElementsByClassName("file-name");
		fileTitle = arrays[0].textContent;
		arrays = files[i].getElementsByClassName("file-descr");
		fileDescr = arrays[0].textContent;
		if((fileTitle.search(new RegExp(strFind, "i")) > -1) || (fileDescr.search(new RegExp(strFind, "i")) > -1)){
			results.push(files[i]);
		}
	}
	for (i = 0; i < results.length; i++){
		found = found + "<div class=\"file-item\">" + results[i].innerHTML + "</div>";
	}
	found = "<div class=\"num-results\"><b>" + i + " resultados encontrados</b></div>" + found;
	html.innerHTML = found;
	html.style.display = "block";
}

function fileOpen(fileId){
	var url = "' . get_bloginfo('url') . '/wp-admin/admin-ajax.php?action=dfp_download_file&fileId=" + fileId;
	window.open(url, "_blank");
}

' . $jsContent . '
</script>
		';
		return $html;
	}

	protected function style(){
		$cssContent = $this->read_plugin_file('style.css');
		$html = '
<style>
' . $cssContent . '
</style>
	';
		return $html;
	}
	
	protected function get_file_id($path, $group){
		$path = str_replace('\\', '/', $path);
		global $wpdb;
		return $wpdb->get_var('select `id` from ' . $wpdb->prefix . 'dfp_files where `filepath`=\'' . $path . '\' and dfp_group_id=' . $group);
	}
	
	protected function get_file_descr($fileId){
		global $wpdb;
		return $wpdb->get_var('select `descr` from ' . $wpdb->prefix . 'dfp_files where `id`=' . $fileId);
	}
	
	protected function read_path($path, $group){
		$folder = $path;
		$folderPath = str_replace('\\', '/', $folder);
		$folder = explode('/', $folderPath);
		$folder = end($folder);
		$folderId = $this->get_file_id($folderPath, $group);
		$groupPath = $this->get_group_path($group);
		$groupPath = str_replace('\\', '/', $groupPath);
		$html = '<div class="dir-item">'; 
		$html .= '<div class="dir-name">';
		$html .= '<div class="folder-icon" id="icon_' . $folder . '" style="background-image: url(' . $this->get_plugin_file('images/folder.png') . '); background-position: center center;" onclick="sfmShowHide(\'' . $folder . '\');"></div><div id="fn' . $folderId . '" class="folder-name">';
		($groupPath == $folderPath) ? $show_name = $this->get_group_showname($group) : $show_name = $folder;
		$html .= $show_name;
		$html .= '</div><input type="text" id="ifn' . $folderId . '" value="' . $folder . '" class="folder-name-edit" style="display:none" />';
		if (current_user_can('edit_pages')){
			$html = $html . '
			<div class="folder-options">';
			if($groupPath != $folderPath){
				$html = $html . '
				<button id="efn'. $folderId . '" class="folder-but" onclick="sfmFolderEdit(\'' . $folderId . '\');">Editar</button>
				<button id="sfn'. $folderId . '" class="folder-but save-but" onclick="sfmFolderSave(\'' . $folderId . '\', \'' . $group . '\');" style="display: none;">Salvar</button>
				<button id="cfn'. $folderId . '" class="folder-but cancel-but" onclick="sfmFolderCancel(\'' . $folderId . '\');" style="display: none;">Cancelar</button>
				';
			} else {
				$html = $html . '<a href="' . $this->get_download_log_url($group) . '" target="_blank"><button id="dow'. $folderId . '" class="folder-but" >Ver Log</button></a>'
				;
			}
			
			$html = $html . '
				<form method="POST" enctype="multipart/form-data">
					<input name="folderId" type="hidden" value="' . $folderId . '" />
					<input name="folderAction" type="hidden" value="uploadFile" />
					<input name="userFile" type="file" class="upload-field" /><input type="submit" value="Enviar arquivo" class="folder-but" />
				</form>
				<form method="POST">
					<input name="newFolderMame" placeholder="Nome da nova pasta" class="input-folder-name" />
					<input name="folderAction" type="hidden" value="newFolder" />
					<input name="folderId" type="hidden" value="' . $folderId . '" />
					<input type="submit" value="Nova pasta" class="folder-but" />
				</form>';
			

			if($groupPath != $folderPath){			
				$html = $html . '
					<form method="POST">
						<input name="folderAction" type="hidden" value="removeFolder" />
						<input name="folderId" type="hidden" value="' . $folderId . '" />
						<input type="submit" value="Eliminar pasta" class="folder-but cancel-but" />
					</form>
				';
			}
			$html = $html . '</div>';
		}

		$html = $html . '</div>';
		$html = $html . '<div id="into_' . $folder . '" class="inside-folder">';
		if (is_dir($path)){
			$scn = scandir($path);
			$ct = 0;
			foreach($scn as $sc){
				if($ct > 1){
					if(is_dir($path . '/' . $sc)){
						$html = $html . $this->read_path($path . '/' . $sc, $group);
					} else {
						$fileId = $this->get_file_id($path . '/' . $sc, $group);
						if ($fileId != ''){
							$lastDot = strrpos($sc, ".");
							$fileText = $this->get_file_descr($fileId);
							$ext = substr($sc, ($lastDot + 1), 10);
							$fileName = substr($sc, 0, $lastDot);
							$html = $html . '<div class="file-item">';
							$html = $html . '	<div class="file-icon" style="background-image: url(\'' . $this->get_plugin_file_icon($ext) . '\');"></div>';
							$html = $html . '	<div class="file-content">';
							$html = $html . '		<div class="file-name">' . $fileName . '</div>';
							$html = $html . '		<div id="d'. $fileId . '" class="file-descr">' . $fileText . '</div>';
							if (current_user_can('edit_pages')){
								$html = $html . '		<textarea id="f'. $fileId . '" class="file-descr-edit" style="display:none">' . $fileText . '</textarea>';
							}
							$html = $html . '		<div class="file-options"><button class="file-but" onclick="fileOpen(\'' . $fileId . '\');">Download</button>';
							if (current_user_can('edit_pages')){
								$html = $html . '
									<form method="POST">
										<input name="folderAction" type="hidden" value="removeFile" />
										<input name="fileId" type="hidden" value="' . $fileId . '" />
										<input type="submit" value="Remover" class="file-but" />
									</form>
								<button id="e'. $fileId . '" class="file-but" onclick="sfmEdit(\'' . $fileId . '\');">Editar</button><button id="c'. $fileId . '" class="file-but cancel-but" style="display:none" onclick="sfmCancel(\'' . $fileId . '\');">Cancelar</button><button id="s'. $fileId . '"class="file-but save-but" style="display:none" onclick="sfmSave(\'' . $fileId . '\');">Salvar</button>';
							}
							$html = $html . '		</div>';
							$html = $html . '	</div>';
							$html = $html . '</div>';
						}
						
					}
					
				}
				$ct++;
			}
					$html = $html . '</div>';
		}

		$html = $html . '</div>';
		return $html;
	}

	protected function rename_folder(){
		global $wpdb;
		$folderId = $_POST['folderId'];
		$newFolderName = $_POST['newFolderName'];
		$groupId = $_POST['groupId'];
		if ((current_user_can('edit_pages')) && ($folderID != '') && ($newFolderName != '') && ($groupId != '')){
			$atualPath = $this->get_file_path($folderId);
			$atualName = end(explode('/', $atualPath));
			$newPath = str_replace($atualName, $newFolderName, $atualPath);
			if (rename($atualPath, $newPath)){
				$files = $wpdb->get_results('select `id`, `filepath` from `'. $wpdb->prefix .'dfp_files` where `dfp_group_id`=' . $groupId . ' and `filepath` like `' . $atualPath . '%`');
				foreach ($files as $file){
					$newFilePath = str_replace($atualPath, $newPath, $file->filepath);
					$wpdb->update($wpdb->prefix . 'dfp_files', array('filepath' => $newFilePath), array('id' => $file->id), array('%s'), array('%d'));
				}
				die('1');
			} else {
				die('0');
			}
		}
	}
	
	protected function forbidden(){
		return '<div class="error">Acesso permitido apenas a usuários cadastrados. Cadastre-se ou realize o login antes de continuar.</div>';
	}
	
	public function isRestricted($groupId){
		global $wpdb;
		$restricted = $wpdb->get_var('select `restricted` from ' . $wpdb->prefix . 'dfp_group where `id`=' . $groupId);
		if ($restricted == 1){
			return true;
		} else {
			return false;
		}
	}
	
	public function show($atts){
		echo $this->style();
		echo $this->script();
		$groupId = $atts['group'];
		if ($this->isRestricted($groupId) && (!is_user_logged_in())){
			echo $this->forbidden();
			return;
		}
		$groupPath = $this->get_group_path($groupId);
		if (current_user_can('edit_pages')){
			if($_POST){
				$this->post_option_folder();
			}
			$this->import_files($groupPath, $groupId);
		}
		echo $this->show_search();
		echo $this->read_path($groupPath, $groupId);
	}

	protected function show_search(){
		$html = '<div class="search-file"><input type="text" id="search_finder"><button id="search_but" class="file-but" onclick="findFiles();">Buscar</button></div>
		<div id="search_results"></div>';
		return $html;
	}
	
	protected function get_group_path($groupId){
		global $wpdb;
		$query = 'select `dirpath` from `' . $wpdb->prefix . 'dfp_group` where `id`=' . $groupId;
		return $wpdb->get_var($query);
	}
	
	protected function get_group_showname($groupId){
		global $wpdb;
		$query = 'select `showname` from `' . $wpdb->prefix . 'dfp_group` where `id`=' . $groupId;
		return $wpdb->get_var($query);
	}
	
	protected function get_file_path($id){
		global $wpdb;
		$query = 'select `filepath` from `' . $wpdb->prefix . 'dfp_files`where `id`=' . $id;
		return $wpdb->get_var($query);
	}
	
	protected function check_database(){
		global $wpdb;
		$sql_groups = 'create table if not exists `' . $wpdb->prefix . 'dfp_group` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`name` varchar(30),
					`showname` varchar(50),
					`dirpath` varchar(4000),
					`restricted` tinyint not null default 0,
					primary key (`id`)
				)';
		$sql_files = 'create table if not exists `' . $wpdb->prefix . 'dfp_files` (
					`id` int(11) unsigned NOT NULL auto_increment,
					`dfp_group_id` int(11),
					`filepath` varchar(4000),
					`descr` varchar(4000),
					primary key (`id`)
				)';
		$wpdb->query($sql_groups);
		$wpdb->query($sql_files);
	}

	protected function import_files($path, $group){
		global $wpdb;
		if (is_dir($path)){	
			$folderPath = str_replace('\\', '/', $path);
			$query = 'select `id` from `' . $wpdb->prefix . 'dfp_files` where `filepath`=\'' . $folderPath .'\' and `dfp_group_id`=' . $group;
			$wpdb->get_results($query);
			if ($wpdb->num_rows == 0){
				$wpdb->insert($wpdb->prefix . 'dfp_files', array('filepath' => $folderPath, 'dfp_group_id' => $group), array( '%s', '%d'));
			}
			$scn = scandir($path);
			$ct = 0;
			foreach($scn as $sc){
				if($ct > 1){
					if(is_dir($path . '/' . $sc)){
						$html = $html . $this->import_files($path . '/' . $sc, $group);
					} else {
						$filePath = $path . '/' . $sc;
						$filePath = str_replace('\\', '/', $filePath);
						$query = 'select `id` from `' . $wpdb->prefix . 'dfp_files` where `filepath`=\'' . $filePath .'\' and `dfp_group_id`=' . $group;
						$wpdb->get_results($query);
						if ($wpdb->num_rows == 0){
							$wpdb->insert($wpdb->prefix . 'dfp_files', array('filepath' => $filePath, 'dfp_group_id' => $group), array( '%s', '%d'));
						}
					}
				}
				$ct++;
			}
		}
	}
	
	public function save_descr(){
		global $wpdb;
		if (current_user_can('edit_pages')){
			$descr = $_POST['fileDescr'];
			$id = $_POST['fileId'];
			$wpdb->update($wpdb->prefix . 'dfp_files', array('descr' => $descr), array('id' => $id), array('%s'), array('%d'));
			wp_die('1');
		} else {
			wp_die('0');
		}
	}
	
	public function post_option_folder(){
		if ($_POST['folderAction'] == 'uploadFile'){
			$this->upload_file();
		} else if ($_POST['folderAction'] == 'newFolder'){
			$this->create_folder();
		} else if ($_POST['folderAction'] == 'removeFolder'){
			$this->remove_folder();
		} else if ($_POST['folderAction'] == 'removeFile'){
			$this->remove_file();
		}
	}
	
	public function upload_file(){
		$folderId = $_POST['folderId'];
		$folderPath = $this->get_file_path($folderId);
		$nome_final = $_FILES['userFile']['name'];
		if (!move_uploaded_file($_FILES['userFile']['tmp_name'], $folderPath . '/' . $nome_final)) {
			echo '<div class="error">Não foi possível enviar o arquivo. Tente novamente. Se o erro persistir, entre em contato com o administrador.</div>';
		}
	}
	
	public function create_folder(){
		$folderName = $_POST['newFolderMame'];
		if ($folderName != ''){
			$except = array('\\', '/', ':', '*', '?', '"', '<', '>', '|', '.'); 
			$folderName = str_replace($except, '', $folderName);
			$folderId = $_POST['folderId'];
			$folderPath = $this->get_file_path($folderId);
			$path = $folderPath . '/' . $folderName;
			$path = str_replace('//', '/', $path);
			$path = str_replace('\\\\', '\\', $path);
			if(!is_dir($path)){
				echo $path;
				if(!mkdir($path)){
					echo '<div class="error">Não foi possível criar a pasta. Tente novamente. Se o erro persistir, entre em contato com o administrador.</div>';
				}
			}
		}
	}
	
	public function remove_folder(){
		$folderId = $_POST['folderId'];
		$folderPath = $this->get_file_path($folderId);
		if(is_dir($folderPath)){
			if(rmdir($folderPath)){
				$this->remove_file_register($folderId); 
			} else {
				echo '<div class="error">Não foi possível remover a pasta. Verifique se ela está vazia.</div>';
			}
		} else {
			echo '<div class="error">O caminho indicado não foi encontrado ou não é uma pasta.</div>';
		}
	}
	
	public function remove_file(){
		$fileId = $_POST['fileId'];
		$filePath = $this->get_file_path($fileId);
		if(is_file($filePath)){
			if(unlink($filePath)){
				$this->remove_file_register($fileId); 
			} else {
				echo '<div class="error">Não foi possível remover o arquivo.</div>';
			}
		} else {
			echo '<div class="error">O caminho indicado não foi encontrado ou não é um arquivo.</div>';
		}
	}
	
	protected function remove_file_register($id){
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'dfp_files', array( 'id' => $id ), array( '%d' ) );
	}
	
}

add_action( 'plugins_loaded', array( 'DimdavidFileProvider', 'get_instance' ) );

endif;