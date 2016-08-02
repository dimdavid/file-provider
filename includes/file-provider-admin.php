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
	
	public function file_provider_menu(){
		add_options_page('File Provider', 'File Provider Options', 'manage_options', 'file-provider-options', array($this, 'file_provider_options_page'));
		add_menu_page( 'Aquisições', 'Aquisições', 'edit_posts', 'report', array($this, 'lista_pedidos'), '', '' );
	}

	public function file_provider_options_page(){
		if (getenv("REQUEST_METHOD") == "POST"){
			$this->save_option_inputs();
		}		
		$html = '<div><h2>Opções do File Provider</h2>';
		$html .= '<h4>Conteúdo descritivo.</h4>
		</div>';
		$html .= '<div><form action="options-general.php?page=file_provider_options_page" method="POST">';
				
		$html .= $this->show_option_inputs();
		
		$html .= '<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Salvar alterações"  /></p>';
		$html .= '</form></div>';
		echo $html;
	}

}