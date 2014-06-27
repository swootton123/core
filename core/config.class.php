<?php
 /*
 * Project:		EQdkp-Plus
 * License:		Creative Commons - Attribution-Noncommercial-Share Alike 3.0 Unported
 * Link:		http://creativecommons.org/licenses/by-nc-sa/3.0/
 * -----------------------------------------------------------------------
 * Began:		2010
 * Date:		$Date$
 * -----------------------------------------------------------------------
 * @author		$Author$
 * @copyright	2006-2011 EQdkp-Plus Developer Team
 * @link		http://eqdkp-plus.com
 * @package		eqdkp-plus
 * @version		$Rev$
 * 
 * $Id$
 */

if ( !defined('EQDKP_INC') ){
	header('HTTP/1.0 404 Not Found');exit;
}
class config extends gen_class {
	public static $dependencies = array('pfh', 'db');

	protected $config_modified	= false;
	protected $config			= array();
	
	private $changed_keys = array();
	private $deleted_keys = array();
	private $added_keys = array();
	
	public function __construct($pfh=false, $db=false)	{
		$this->get_config();
	}

	public function __destruct() {
		if ($this->config_modified) $this->config_save();
		parent::__destruct();
	}
	
	public function get($name, $plugin='') {
		if($plugin) return (isset($this->config[$plugin][$name])) ? $this->config[$plugin][$name] : false;
		return (isset($this->config[$name])) ? $this->config[$name] : false;
	}

	public function set($config_name, $config_value='', $plugin=''){
		if(is_array($config_name)){
			foreach($config_name as $d_name => $d_value){
				$this->set($d_name, $d_value, $plugin);
			}
		}else{
			if($plugin){
				if(!isset($this->config[$plugin][$config_name])){
					$this->config[$plugin][$config_name]	= $config_value;
					$this->added_keys[] = array('k' => $config_name, 'v' => $config_value, 'p' => $plugin);
					$this->config_modified = true;
				}else if($this->config[$plugin][$config_name] !== $config_value){
					$this->config[$plugin][$config_name]	= $config_value;
					$this->changed_keys[] = array('k' => $config_name, 'v' => $config_value, 'p' => $plugin);
					$this->config_modified = true;
				}
			}else{
				if(!isset($this->config[$config_name])){
					$this->config[$config_name]	= $config_value;
					$this->added_keys[] = array('k' => $config_name, 'v' => $config_value, 'p' => 'core');
					$this->config_modified = true;
				}else if($this->config[$config_name] !== $config_value){
					$this->config[$config_name]	= $config_value;
					$this->changed_keys[] = array('k' => $config_name, 'v' => $config_value, 'p' => 'core');
					$this->config_modified = true;
				}
			}
		}
	}

	public function del($config_name, $plugin=''){
		if(is_array($config_name)) {
			foreach($config_name as $d_name) {
				$this->del($d_name, $plugin);
			}
		}else{
			if($plugin && isset($this->config[$plugin][$config_name])) {
				unset($this->config[$plugin][$config_name]);
				$this->deleted_keys[] = array('k' => $config_name, 'p' => $plugin);
				$this->config_modified = true;
			} elseif(!$plugin && isset($this->config[$config_name])) {
				if(is_array($this->config[$config_name])) {
					unset($this->config[$config_name]);
					$this->deleted_keys[] = array('k' => null, 'p' => $config_name);
					$this->config_modified = true;
				} else {
					unset($this->config[$config_name]);
					$this->deleted_keys[] = array('k' => $config_name, 'p' => 'core');
					$this->config_modified = true;
				}
			}
			return true;
		}
		return false;
	}

	public function get_config($plugin=''){
		if(count($this->config) < 1){
			// load cache file
			$file = $this->pfh->FolderPath('config', 'eqdkp')."localconf.php";
			if(is_file($file)){
				include($file);
				$this->config = $localconf;
			}
			if(!isset($this->config['server_path'], $this->config['cookie_name'], $this->config['plus_version'])) {
				// important configs are missing in cache-file, probably an empty/not available localconf.php ... Load from Database..
				$this->get_dbconfig();
			}
		}
		return ($plugin) ? ((empty($this->config[$plugin])) ? array() : $this->config[$plugin]) : $this->config;
	}
	
	private function unserialize($val) {
		//check for '{', only in this case we try an unserialize
		if(strpos($val, '{') === false) return $val;
		$value = unserialize($val);
		// if value is an array now, return value, else return val
		if(is_array($value)) return $value;
		return $val;
	}

	private function get_dbconfig(){
		if(!is_object($this->db)){return true;}
		$this->config_modified = true;
		$this->config = array();
		$objQuery = $this->db->query("SELECT * FROM __backup_cnf;");
		if ($objQuery){
			while($row = $objQuery->fetchAssoc() ){
				if($row['config_plugin'] != 'core'){
					$this->config[$row['config_plugin']][$row['config_name']] = $this->unserialize($row['config_value']);
				}else{
					$this->config[$row['config_name']] = $this->unserialize($row['config_value']);
				}
			}
		}

		return $this->config;
	}

	public function install_set($array){
		$this->config_save($array);
	}

	private function changeset_db_backup(){
		if(!$this->config_modified) return;
		$all_keys = array();
		if(is_array($this->changed_keys)) $all_keys = $this->changed_keys;
		if(is_array($this->added_keys)) $all_keys = array_merge($this->added_keys, $all_keys);
		$done = array();
		foreach($all_keys as $changed){
			if(strlen(trim($changed['k'])) > 0 && !in_array($changed['k'], $done)) {
				$done[] = $changed['k'];
				
				$this->db->prepare("REPLACE INTO __backup_cnf :p")->set(array(
					'config_name'	=> $changed['k'],
					'config_value'	=> (is_array($changed['v'])) ? serialize($changed['v']) : $changed['v'],
					'config_plugin'	=> $changed['p']
				))->execute();
			}
		}
		unset($this->changed_keys, $this->added_keys);
		
		foreach($this->deleted_keys as $dk => $deleted){
			if($deleted['k'] != null)
				$this->db->prepare("DELETE FROM __backup_cnf WHERE config_name = ? AND config_plugin = ?")->execute($deleted['k'], $deleted['p']);
			else
				$this->db->prepare("DELETE FROM __backup_cnf WHERE config_plugin = ?")->execute($deleted['p']);
			unset($this->deleted_keys[$dk]);
		}
		//check if row-counts matches number of configs
		$objQuery = $this->db->query("SELECT COUNT(config_name) as count FROM __backup_cnf;");
		if ($objQuery){
			$arrResult =  $objQuery->fetchAssoc();
			$row_count = (int)$arrResult['count'];	
		} else $row_count = 0;

		$array_count = 0;
		foreach($this->config as $key => $data) {
			if(is_array($data) && !is_numeric(key($data))) {
				$array_count = 0;
				foreach($data as $dat) {
					$array_count++;
				}
			} else {
				$array_count++;
			}
		}
		if($row_count != $array_count) $this->save_backup($this->config);
	}

	private function save_backup($array){
		if(is_array($array)){
			$this->db->query("TRUNCATE TABLE __backup_cnf");
			$data = array();
			foreach($array as $name=>$value){
				//include multiselects and sliders of core (they have numerical keys)
				if(!is_array($value) || is_numeric(key($value))) {
					// Core
					$value = array($name => (is_array($value) ? serialize($value) : $value));
					$name = 'core';
				}
				foreach($value as $pname=>$pvalue){
					if(strlen(trim($pname)) > 0){
						$data[] = array(
							'config_name'	=> $pname,
							'config_value'	=> (is_array($pvalue)) ? serialize($pvalue) : $pvalue,
							'config_plugin'	=> $name
						);
					}
				}
			}
			$this->db->prepare("REPLACE INTO __backup_cnf :p")->set($data)->execute();
		}
	}

	private function config_save($manual=false){

		// add the database backup
		if($manual == false){
			//dont save anything, when important configs are not available
			if(!isset($this->config['server_path'], $this->config['cookie_name'], $this->config['plus_version'])) return false;
			$this->changeset_db_backup();
		}else{
			$this->config = (is_array($manual) ? $manual : $this->config);
			$this->save_backup($this->config);
		}
		// Build the plain file config cache, reload from database first
		$this->get_dbconfig();
		ksort($this->config);
		$file = $this->pfh->FolderPath('config', 'eqdkp')."localconf.php";
		$data = "<?php\n";
		$data .= "if (!defined('EQDKP_INC')){\n\tdie('You cannot access this file directly.');\n}\n";
		$data .= '$localconf = ';
		$data .= var_export($this->config, true);
		$data .= ";\n?";
		$data .= ">";
		$this->pfh->putContent($file, $data);
		$this->config_modified = false;
	}
	
	public function flush(){
		$this->config_save();
	}
}
?>