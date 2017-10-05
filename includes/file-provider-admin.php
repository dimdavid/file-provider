<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DimdavidFileProviderAdmin {

	public function __construct(){
		$this->init_actions();
	}

	public function init_actions(){
		add_action('admin_menu', array($this, 'file_provider_menu'));
	}
	
	public function init_settings(){
	}
	
	public function show_option_inputs(){
	}
	
	public function create_folder($path){
		$path = str_replace('//', '/', $path);
		$path = str_replace('\\\\', '\\', $path);
		if(!is_dir($path)){
				if(!mkdir($path)){
					echo '<div class="error">Não foi possível criar a pasta. Tente novamente. Se o erro persistir, entre em contato com o administrador.</div>';
					return false;
				}
		}
		return true;
	}
	
	private function remove_folder_register($id){
		global $wpdb;
		$wpdb->delete( $wpdb->prefix . 'dfp_group', array( 'id' => $id ), array( '%d' ) );
	}
	
	private function insert_folder_register($name, $slug, $path, $restrict, $auto_refresh){
		global $wpdb;
		$wpdb->insert( 
			$wpdb->prefix . 'dfp_group', 
			array( 
				'name' => $slug, 
				'showname' => $name,
				'dirpath' => $path,
				'restricted' => $restrict,
				'auto_refresh' => $auto_refresh
			), 
			array( 
				'%s', 
				'%s', 
				'%s', 
				'%d',
				'%d'
			) 
		);
	}
	
	public function post_option_inputs(){
		$option = sanitize_text_field($_POST['option']);
		switch($option){
		
			case 'insert':
				$folderPath = sanitize_text_field($_POST['folder_path']);
				if ($this->create_folder($folderPath)){
					$this->insert_folder_register(sanitize_text_field($_POST['folder_name']), sanitize_text_field($_POST['folder_slug']), str_replace('//', '/', str_replace('\\\\', '\\', sanitize_text_field($_POST['folder_path']))), sanitize_text_field($_POST['folder_restrict']), sanitize_text_field($_POST['folder_refresh']));
				}	
			break;
			
			case 'remove':
				$this->remove_folder_register(sanitize_text_field($_POST['folder_id']));
			break;
			
			case 'refresh':
				$folderPath = str_replace('//', '/', str_replace('\\\\', '\\', sanitize_text_field($_POST['folder_path'])));
				$groupId = sanitize_text_field($_POST['folder_id']);
				$dfp = DimdavidFileProvider::get_instance();
				$dfp->admin_import_files($folderPath, $groupId);
			break;
			
			case 'restricted':
				$this->change_restrict(sanitize_text_field($_POST['folder_id']));
			break;
			
			case 'auto_refresh';
				$this->change_auto_refresh(sanitize_text_field($_POST['folder_id']));
			break;
			
		}
	}
	
	public function change_restrict($folder_id){
		$folder = $this->get_folder_options($folder_id);
		$v = ($folder->restricted == 1) ? 0 : 1;
		global $wpdb;
		$wpdb->query('update `' . $wpdb->prefix . 'dfp_group` set `restricted`=' . $v . ' where `id`=' . $folder_id);
	}
	
	public function change_auto_refresh($folder_id){
		$folder = $this->get_folder_options($folder_id);
		$v = ($folder->auto_refresh == 1) ? 0 : 1;
		global $wpdb;
		$wpdb->query('update `' . $wpdb->prefix . 'dfp_group` set `auto_refresh`=' . $v . ' where `id`=' . $folder_id);
	}
	
	public function get_folder_options($folder_id){
		global $wpdb;
		return $wpdb->get_row('select `restricted`, `auto_refresh` from `' . $wpdb->prefix . 'dfp_group` where id=' . $folder_id);
	}
	
	public function file_provider_menu(){
		add_options_page('File Provider', 'File Provider Options', 'manage_options', 'file-provider-options', array($this, 'file_provider_options_page'));
	}

	public function file_provider_options_page(){
		if (getenv("REQUEST_METHOD") == "POST"){
			$this->post_option_inputs();
		}		
		$html = '<div><h2>Opções do File Provider</h2>'. $this->get_notes() . '</div>';				
		$html .= '<div style="width: 95%"><table class="wp-list-table widefat fixed striped posts">';
		$html .= '<thead><tr><th scope="col" class="manage-column">Shortcode</th><th scope="col" class="manage-column">Nome de exibição</th><th scope="col" class="manage-column">Slug</th><th scope="col" class="manage-column">Diretório real</th><th scope="col" class="manage-column">Restrito</th><th>Atualizar</th><th></th></tr></thead>';
		$html .= '<tbody id="the-list">';
		
		$folders = $this->get_all_folders();
		
		foreach ($folders as $folder){
			$html .= '<tr><td><strong>[file_provider group=' . $folder->id . ']</strong></td><td>' . $folder->showname . '</td><td>' . $folder->name . '</td><td>' . $folder->dirpath . '</td>';
			$html .= '<td><form name="change_restrict" method="POST"><input type="hidden" name="option" value="restricted" /><input type="hidden" name="folder_id" value="' . $folder->id . '" /><input type="submit" value="';
			$html .= ($folder->restricted == 1) ? 'Sim' : 'Não';
			$html .= '" class="btn btn-change" /></form></td>';
			$html .= '<td><form name="change_refresh" method="POST"><input type="hidden" name="option" value="auto_refresh" /><input type="hidden" name="folder_id" value="' . $folder->id . '" /><input type="submit" value="';
			$html .= ($folder->auto_refresh == 1) ? 'Sim' : 'Não';
			$html .= '" class="btn btn-change" /></form></td>';
			$html .= '<td><form name="remove" method="POST"><input type="hidden" name="folder_id" value="' . $folder->id . '" /><input type="hidden" name="option" value="remove" /><input type="submit" value="Remover" class="btn btn-danger" /></form></td>';
			$html .= '<td><form name="refresh" method="POST"><input type="hidden" name="folder_id" value="' . $folder->id . '" /><input type="hidden" name="folder_path" value="' . $folder->dirpath . '" /><input type="hidden" name="option" value="refresh" /><input type="submit" value="Atualizar" class="btn btn-refresh" /></form></td>';
			$html .= '</tr>';
		}
		
		$html .= '</tbody></table></div>';
		
		$html .= '<div style="max-width: 400px; margin-top: 60px;"><form method="POST"><input type="hidden" name="option" value="insert" />';
				
		$html .= '<div id="submitdiv" class="postbox ">
			<h3 class="hndle ui-sortable-handle" style="padding: 0px 0px 15px 15px"><span>Inserir nova pasta</span></h3>
			<div class="inside">
				<div class="submitbox" id="submitpost">
					<div id="minor-publishing">
						<p>
							<label for="folder_name">Nome de exibição:</label><br />
							<input type="text" name="folder_name" id="folder_name" value="" style="width: 100%" required />
						</p>
						<p>
							<label for="folder_name">Slug:</label><br />
							<input type="text" name="folder_slug" id="folder_name" value="" style="width: 100%" required />
						</p>
						<p>
							<label for="folder_name">Diretório real:</label><br />
							<input type="text" name="folder_path" id="folder_name" value="" style="width: 100%" required />
						</p>
						<p>
							<label for="folder_name">Acesso restrito?</label><br />
							<input type="radio" name="folder_restrict" value="1" required />Sim
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
							<input type="radio" name="folder_restrict" value="0" required />Não
						</p>
						<p>
							<label for="folder_name">Atualizar automaticamente?</label><br />
							<input type="radio" name="folder_refresh" value="1" required />Sim
							&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
							<input type="radio" name="folder_refresh" value="0" required />Não
						</p>
					</div>
					<div id="publishing-action">
						<span class="spinner"></span>
						<input name="original_publish" type="hidden" id="original_publish" value="Publicar">
						<input type="submit" name="publish" id="publish" class="button button-primary button-large" value="Inserir">
					</div>
					<div class="clear"></div>
				</div>
			</div>
		</div>';		
		
		$html .= '</form></div>';
		echo $html;
	}
	
	public function get_all_folders(){
		global $wpdb;
		$sql = 'select `id`, `showname`, `name`, `dirpath`, `restricted`, `auto_refresh` from `' . $wpdb->prefix . 'dfp_group` order by `id`';
		return $wpdb->get_results($sql);
	}
	
	public function get_notes(){
		$notes = '
		<h4>Short code</h4>
		<p>Para mostrar os arquivos em uma página ou post, utilize o shortcode <b>[file_provider group=id]</b>.</p>
		<h4>Remover</h4>
		<p>Ao excluir uma pasta, apenas o registro será removido. A pasta continuará a existir com seus arquivos.</p>
		<h4>Restrito</h4>
		<p>Apenas usuários autenticados poderão ver o conteúdo de pastas marcadas como restritas.</p>
		<h4>Atualizar</h4>
		<p>Atualiza automaticamente quando o usuário acessa a página.</p>
		';
		return $notes;
	}

}