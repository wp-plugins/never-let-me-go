<?php
if(!class_exists('Hametuha_Library')){
/**
 * WordPressプラグインのためのユーティリティクラス
 *
 * @package Hametuha Libarary
 * @author Takahashi Fumiki<takahashi.fumiki@hametuha.co.jp>
 * @since 0.1
 */
class Hametuha_Library {
	
	/**
	 * @var string
	 */
	public $version = "1.0";
	
	/**
	 * @var string
	 */
	public $domain = "hametuha-library";
	
	/**
	 * プラグインのルートディレクトリ
	 * @var string
	 */
	public $dir;
	
	/**
	 * プラグインのルートURL
	 * @var string
	 */
	public $url;
	
	/**
	 * このファイルのパス
	 * @var string
	 */
	protected $source_file;
	
	/**
	 * このプラグイン用のテーブルセット接頭辞
	 * @var string
	 */
	protected $prefix = "";
	
	/**
	 * @var string
	 */
	protected $name = "hametuha_library";

	/**
	 * @var array
	 */
	public $option = array();
	
	/**
	 * @var array
	 */
	protected $default_option = array(
		
	);	
	
	/**
	 * @var array
	 */
	protected $admin_error = array();
	
	/**
	 * @var array
	 */
	protected $admin_message = array();
	
	/**
	 * @var array
	 */
	protected $container = array();
	
	/**
	 * コンストラクタ
	 * @global string $table_prefix
	 * @param string $base_file プラグインのコアファイルのパス。__FILE__
	 * @param type $version (optional) このプラグインのバージョン
	 * @param type $domain  (optional) このプラグインのドメイン
	 */
	public function __construct($base_file, $version = '1.0', $domain = 'hametuha-library') {
		global $table_prefix;
		//初期値を設定
		$this->dir = plugin_dir_path($base_file);
		$this->url = plugin_dir_url($base_file);
		if($version != $this->version){
			$this->version = $version;
		}
		if($domain != $this->domain){
			$this->domain = $domain;
		}
		//ソースファイル名を記録
		$this->source_file = __FILE__;
		//テーブル名すべてに接頭辞をつける
		foreach(get_object_vars($this) as $prop => $value){
			if(0 === strpos($prop, 'table')){
				$this->$prop = $table_prefix.$this->prefix.$value;
			}
		}
		//保存されたオプションを取得
		$saved_option = get_option($this->name."_option");
		foreach($this->default_option as $key => $value){
			if(!isset($saved_option[$key])){
				$this->option[$key] = $value;
			}else{
				$this->option[$key] = $saved_option[$key];
			}
		}
		//アクチベーションフックがあれば登録
		if(method_exists($this, "activate")){
			register_activation_hook($base_file, array($this, "activate"));
		}
		//デアクチベーションフックがあれば登録
		if(method_exists($this, "deactivate")){
			register_deactivation_hook($base_file, array($this, "deactivate"));
		}
		//共通フックを登録
		if(method_exists($this, "init")){
			add_action("init", array($this, "init"));
		}
		//テンプレートリダイレクトフック
		if(method_exists($this, "template_redirect")){
			add_action("template_redirect", array($this, "template_redirect"));
		}
		//初期化フックを登録
		if(is_admin()){
			//初期化
			if(method_exists($this, "admin_init")){
				add_action("admin_init", array($this, "admin_init"));
			}
			//メニュー
			if(method_exists($this, "admin_menu")){
				add_action("admin_menu", array($this, "admin_menu"));
			}
		}else{
			if(method_exists($this, "public_init")){
				add_action("init", array("public_init"));
			}
		}
		//管理画面にメッセージを表示
		add_action("admin_notice", array($this, "admin_notice"));
		//国際化対応していればドメインを登録
		if(is_dir($this->dir.DIRECTORY_SEPARATOR."language")){
			load_plugin_textdomain($this->domain, false, basename($this->dir).DIRECTORY_SEPARATOR."language");
		}
		//Javascriptを登録
		$this->register_script();
	}
	
	/**
	 * htmlspecialcharsのエイリアス
	 * 
	 * @param string $string
	 * @param boolean $echo (optional) falseにすると出力しない
	 * @return void|string
	 */
	public function h($string, $echo = true){
		if($echo){
			echo htmlspecialchars($string, ENT_QUOTES, "utf-8");
		}else{
			return htmlspecialchars($string, ENT_QUOTES, "utf-8");
		}
	}
	
	/**
	 * フォームなどのname属性を返す
	 * 
	 * @param string $name アンダースコア区切り
	 * @return string
	 */
	public function key($name, $echo = false){
		if($echo){
			echo $this->name."_".$name;
		}else{
			return $this->name."_".$name;
		}
	}
	
	/**
	 * $_GETに値が設定されていたら返す
	 * @param string $key
	 * @return mixed
	 */
	public function get($key){
		if(isset($_GET[$key])){
			return $_GET[$key];
		}else{
			return null;
		}
	}
	
	/**
	 * $_POSTに値が設定されていたら返す
	 * @param string $key
	 * @return mixed
	 */
	public function post($key){
		if(isset($_POST[$key])){
			return $_POST[$key];
		}else{
			return null;
		}
	}
	
	/**
	 * $_REQUESTに値が設定されていたら返す
	 * @param string $key
	 * @return mixed
	 */
	public function request($key){
		if(isset($_REQUEST[$key])){
			return $_REQUEST[$key];
		}else{
			return null;
		}
	}
	
	/**
	 * アップロードされたファイルのtmp_nameを取得し、エラーがあった場合はメッセージを登録する
	 * @param string $name fileインプットのname属性
	 * @return string アップロードされていない場合はfalse
	 */
	protected function get_uploaded_file_name($name){
		if(isset($_FILES[$name])){
			switch($_FILES[$name]['error']){
				 case UPLOAD_ERR_INI_SIZE: 
					$this->add_message($this->_("Uploaded file size exceeds the &quot;upload_max_filesize&quot; value defined in php.ini"), true); 
					return false;
					break; 
				case UPLOAD_ERR_FORM_SIZE: 
					$this->add_message($this->_("Uploaded file size exceeds"), true); 
					return false;
					break; 
				case UPLOAD_ERR_PARTIAL: 
					$this->add_message($this->_("File has been uploaded incompletely. Check your internet connection."), true); 
					return false;
					break; 
				case UPLOAD_ERR_NO_FILE: 
					$this->add_message($this->_("No file was uploaded."), true); 
					return false;
					break; 
				case UPLOAD_ERR_NO_TMP_DIR: 
					$this->add_message($this->_("No tmp directory exists. Contact to your server administrator."), true); 
					return false;
					break; 
				case UPLOAD_ERR_CANT_WRITE: 
					$this->add_message($this->_("Failed to save the uploaded file. Contact to your server administrator."), true); 
					return false;
					break; 
				case UPLOAD_ERR_EXTENSION: 
					$this->add_message($this->_("PHP stops uploading."), true); 
					return false;
					break;
				case UPLOAD_ERR_OK:
					return $_FILES[$name]['tmp_name'];
					break;
			}
		}else{
			$this->add_message($this->_("File isn't uploaded."), true);
			return false;
		}
	}
	
	/**
	 * fgetcsvのShift_JIS対応版
	 * @param resource $handle
	 * @param string $length
	 * @param string $d
	 * @param string $e
	 * @return array
	 */
	protected function fget_csv(&$handle, $length = null, $d = ',', $e = '"'){
		$d = preg_quote($d);
        $e = preg_quote($e);
        $_line = "";
        while ($eof != true) {
            $_line .= (empty($length) ? fgets($handle) : fgets($handle, $length));
            $itemcnt = preg_match_all('/'.$e.'/', $_line, $dummy);
            if ($itemcnt % 2 == 0) $eof = true;
        }
        $_csv_line = preg_replace('/(?:\\r\\n|[\\r\\n])?$/', $d, trim($_line));
        $_csv_pattern = '/('.$e.'[^'.$e.']*(?:'.$e.$e.'[^'.$e.']*)*'.$e.'|[^'.$d.']*)'.$d.'/';
        preg_match_all($_csv_pattern, $_csv_line, $_csv_matches);
        $_csv_data = $_csv_matches[1];
        for($_csv_i=0;$_csv_i<count($_csv_data);$_csv_i++){
            $_csv_data[$_csv_i]=preg_replace('/^'.$e.'(.*)'.$e.'$/s','$1',$_csv_data[$_csv_i]);
            $_csv_data[$_csv_i]=str_replace($e.$e, $e, $_csv_data[$_csv_i]);
        }
        return empty($_line) ? false : $_csv_data;
	}


	/**
	 * フックをまたがって保持しておきたい情報を保存
	 * @param string $key
	 * @param mixed $object
	 * @return void
	 */
	public function save($key, $object){
		$this->container[(string) $key] = $object;
	}
	
	/**
	 * オプションを更新する
	 * @param mixed $option
	 * @return boolean
	 */
	public function save_option($option){
		if(update_option($this->name."_option", $option)){
			return true;
		}else{
			return false;
		}
	}
	
	/**
	 * フックをまたがって保存した情報を取得する
	 * @param string $key
	 * @return mixed
	 */
	public function retrieve($key){
		return isset($this->container[$key]) ?  $this->container[$key] : null;
	}
	
	/**
	 * nonce用に接頭辞をつけて返す
	 * @param string $action
	 * @return string
	 */
	public function nonce_action($action){
		return $this->name."_".$action;
	}
	
	/**
	 * wp_nonce_fieldのエイリアス
	 * @param type $action 
	 */
	public function nonce_field($action){
		wp_nonce_field($this->nonce_action($action), "_{$this->name}_nonce");
	}
	
	/**
	 * nonceが合っているか確かめる
	 * @param string $action
	 * @param string $referrer
	 * @return boolean
	 */
	public function verify_nonce($action, $referrer = false){
		if($referrer){
			return ( (wp_verify_nonce($this->request("_{$this->name}_nonce"), $this->nonce_action($action)) && $referrer == $this->request("_wp_http_referer")) );
		}else{
			return wp_verify_nonce($this->request("_{$this->name}_nonce"), $this->nonce_action($action));
		}
	}
		
	/**
	 * 管理画面にメッセージを表示する
	 * @return void
	 */
	public function admin_notice(){
		if(!empty($this->admin_error)){
			?><div class="error"><?php
			foreach($this->admin_error as $err){
				?><p><?php echo $err; ?></p><?php
			}
			?></div><?php
		}
		if(!empty($this->admin_message)){
			?><div class="updated"><?php
			foreach($this->admin_message as $message){
				?><p><?php echo $message; ?></p><?php
			} ?></div><?php
		}
	}
	
	/**
	 * 管理画面に表示するメッセージを追加する
	 * @param string $string
	 * @param boolean $error (optional) trueにするとエラーメッセージ
	 * @return void
	 */
	public function add_message($string, $error = false){
		if($error){
			$this->admin_error[] = (string) $string;
		}else{
			$this->admin_message[] = (string) $string;
		}
	}
	
	/**
	 * WordPressの_eのエイリアス
	 * @param string $text
	 * @return void
	 */
	public function e($text){
		_e($text, $this->domain);
	}
	
	/**
	 * WordPressの__のエイリアス
	 * @param string $text
	 * @return string
	 */
	public function _($text){
		return __($text, $this->domain);
	}
	
	/**
	 * selected属性を出力する
	 * @param boolean $condition 条件がfalseになる場合は出力しない
	 * @return void
	 */
	public function selected($condition = true){
		if($condition){
			echo ' selected="selected"';
		}
	}
	
	/**
	 * checked属性を出力する
	 * @param boolean $condition 
	 * @return void
	 */
	public function checked($condition = false){
		if($condition){
			echo ' checked="checked"';
		}
	}
	
	/**
	 * JSおよびそれに依存するCSSを登録する
	 * @return void
	 */
	public function register_script(){
		wp_register_script("jquery-ui-effects", $this->url."/lib/js/jquery-ui-effects.js", array('jquery'), "1.8.14", !is_admin());
		wp_register_script("jquery-ui-slider", $this->url."/lib/js/jquery-ui-slider.js", array("jquery", "jquery-ui-core", "jquery-ui-widget", "jquery-ui-mouse"), "1.8.9", !is_admin());
		wp_register_script("jquery-ui-datepicker", $this->url."/lib/js/datepicker/jquery-ui-datepicker.js",array("jquery", "jquery-ui-core", "jquery-ui-slider") ,"1.8.9", !is_admin());
		wp_register_script("jquery-ui-timepicker", $this->url."/lib/js/datepicker/jquery-ui-timepicker.js",array("jquery-ui-datepicker") ,"1.8.9", !is_admin());
		wp_register_style("jquery-ui-datepicker", $this->url."/lib/js/datepicker/smoothness/jquery-ui.css", array(), "1.8.9");
		wp_register_script("gmap", "http://maps.google.com/maps/api/js?sensor=true&language=ja", array(), array(), !is_admin());
		wp_register_script('xregexp', $this->url."/lib/js/syntax/xregexp.js", array(), "1.5.0", !is_admin());
		wp_register_script('syntax-core', $this->url."/lib/js/syntax/shCore.js", array('xregexp'), '3.0.83', !is_admin());
		wp_register_script('syntax-php', $this->url."/lib/js/syntax/shBrushPhp.js", array('syntax-core'), '3.0.83', !is_admin());
		wp_register_style('syntax-core', $this->url."/lib/js/syntax/shCore.css", array(), '3.0.83');
		wp_register_style('syntax-theme-default', $this->url."/lib/js/syntax/shThemeDefault.css", array('syntax-core'), '3.0.83');
	}
	
	/**
	 * 管理画面を作成するファイルを読み込む
	 * @param string $title
	 * @param string $file
	 * @param array $args 連想配列。extractで変数として展開される
	 * @param string $icon_id
	 * @return void
	 */
	public function admin_create($title, $file, $args = array(), $icon_id = "icon-tools"){
		if(file_exists($this->dir.DIRECTORY_SEPARATOR.$file)){
			extract($args);
		?>
		<div class="wrap">
			<div class="icon32" id="<?php $this->h($icon_id); ?>"><br /></div>
			<h2><?php $this->h((string)$title); ?></h2>
			<?php do_action("admin_notice"); ?>
			<?php require_once $this->dir.DIRECTORY_SEPARATOR.$file; ?>
			</div>
			<?php
		}else{
			trigger_error(sprintf($this->_("Admin Template file dosen't exist. [FILE NAME: %s]"), $this->dir.DIRECTORY_SEPARATOR.$file));
		}
	}
}
}