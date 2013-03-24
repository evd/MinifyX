<?php
/**
 * MinifyX
 *
 * Copyright 2011-12 by SCHERP Ontwikkeling <info@scherpontwikkeling.nl>
 *
 * This file is part of MinifyX.
 *
 * MinifyX is free software; you can redistribute it and/or modify it under the
 * terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * MinifyX is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR
 * A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * MinifyX; if not, write to the Free Software Foundation, Inc., 59 Temple Place,
 * Suite 330, Boston, MA 02111-1307 USA
 *
 * @package MinifyX
 */
/**
 * This file is the main class file for MinifyX.
 *
 * @copyright Copyright (C) 2011, SCHERP Ontwikkeling <info@scherpontwikkeling.nl>
 * @author SCHERP Ontwikkeling <info@scherpontwikkeling.nl>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU Public License v2
 * @package MinifyX
 */
class MinifyX {
	/**
	 * A reference to the modX object.
	 * @var modX $modx
	 */
	public $modx = null;

	function __construct(modX &$modx,array $config = array()) {
		$this->modx =& $modx;

		/* allows you to set paths in different environments
		 * this allows for easier SVN management of files
		 */
		$corePath = $this->modx->getOption('core_path').'components/minifyx/';
		$assetsPath = $this->modx->getOption('assets_path').'components/minifyx/';
		$assetsUrl = $this->modx->getOption('assets_url').'components/minifyx/';

		$this->config = array_merge(array(
			'corePath' => $corePath
			,'modelPath' => $corePath.'model/'
			,'snippetsPath' => $corePath.'elements/snippets/'
			,'cacheFolder' => $assetsPath.'cache/'
			,'cacheFolderUrl' => $assetsUrl.'cache/'
			,'basePath' => MODX_BASE_PATH
			,'baseUrl' => MODX_BASE_URL
			,'cssFilename' => 'styles'
			,'jsFilename' => 'scripts'
			,'jsSources' => ''
			,'cssSources' => ''
			,'minifyCss' => 1
			,'minifyJs' => 1
			,'combineCss' => 1
			,'combineJs' => 1
			,'cssTpl' => '<link rel="stylesheet" type="text/css" href="[[+file]]" />'
			,'jsTpl' => '<script type="text/javascript" src="[[+file]]"></script>'
			,'outputSeparator' => "\n"
		),$config);
		if (!empty($config['jsSources'])) {$this->config['jsSources'] = explode(',', str_replace("\n",'', trim($config['jsSources'])));}
		if (!empty($config['cssSources'])) {$this->config['cssSources'] = explode(',', str_replace("\n",'', trim($config['cssSources'])));}
		if (!empty($config['cacheFolder'])) {
			$this->config['cacheFolderUrl'] = str_replace('//','/', $config['cacheFolder']);
			$this->config['cacheFolder'] = str_replace('//','/', MODX_BASE_PATH.$config['cacheFolder']);
		}
		
		$this->config['jsExt'] = $this->config['minifyJs'] ? '.min.js' : '.js';
		$this->config['cssExt'] = $this->config['minifyCss'] ? '.min.css' : '.css';
		
		$files = scandir($this->config['cacheFolder'], 1);
		$jsExt = str_replace('.','\.',$this->config['jsExt']);
		$cssExt = str_replace('.','\.',$this->config['cssExt']);
		$jsExpr = '('.$this->config['jsFilename'].'_(\d){10})'.$jsExt;
		$cssExpr = '('.$this->config['cssFilename'].'_(\d){10})'.$cssExt;

		foreach ($files as $v) {
			if ($v == '.' || $v == '..') {continue;}
			
			if (preg_match("/^$jsExpr$/iu", $v, $matches)) {
				// delete old js file if exists
				if (!empty($this->config['jsFile'])) {
					unlink($this->config['cacheFolder'].$v);
					continue;
				}
				$this->config['jsFile'] = $matches[1];
			}
			else if (preg_match("/^$cssExpr$/iu", $v, $matches)) {
				// delete old css file if exists
				if (!empty($this->config['cssFile'])) {
					unlink($this->config['cacheFolder'].$v);
					continue;
				}
				$this->config['cssFile'] = $matches[1];
			}
			else {
				//do nothing, but here we can delete other files in cache dir
			}
		}
		if (empty($this->config['jsFile'])) {$this->config['jsFile'] = $this->config['jsFilename'];}
		if (empty($this->config['cssFile'])) {$this->config['cssFile'] = $this->config['cssFilename'];}
	}

	/**
	 * Initializes MinifyX based on a specific context.
	 *
	 * @access public
	 * @param string $ctx The context to initialize in.
	 * @return string The processed content.
	 */
	public function initialize($ctx = 'mgr') {
		$output = '';

		return $output;
	}
	
	/**
	 * Does the actual minifying, combining files and setting placeholders
	 *
	 * @access public
	 * @param array $options Script properties
	 * @return void 
	 */
	public function minify() {
		// Javascript
		if ($jsFiles = $this->processJs()) {
			$this->modx->setPlaceholder('MinifyX.javascript', $this->prepareFilesForOutput($jsFiles, 'jsTpl'));
		}
		
		//CSS
		if ($cssFiles = $this->processCss()) {
			$this->modx->setPlaceholder('MinifyX.css', $this->prepareFilesForOutput($cssFiles, 'cssTpl'));
		}
		
		return;
	}

	/**
	 * Templating files
	 *
	 * @access public
	 * @param array $files Filses for output
	 * @param string $tplName Template name in config
	 * @return string 
	 */
	public function prepareFilesForOutput($files, $tplName) {
		$tpl = $this->config[$tplName];
		foreach($files as $k => $file) {
			$files[$k] = str_replace('[[+file]]', $file, $tpl);
		}
		return implode($this->config['outputSeparator'], $files);
	}
	

	/**
	 * Check and create (if need) MinifyX js file 
	 *
	 * @access public
	 * @return boolean
	 */
	function processJs() {
		$result = array();
		if ($this->config['combineJs']) {
			$file = $this->config['cacheFolder'].$this->config['jsFile'].$this->config['jsExt'];
			$output = '';
			$maxtime = 0;
			foreach($this->config['jsSources'] as $source) {
				$source = str_replace('//', '/', $this->config['basePath'].trim($source));
				if (is_file($source)) {
					$output .= file_get_contents($source)."\n";	
					$filetime = filemtime($source);
					if ($filetime > $maxtime) {$maxtime = $filetime;}
				} else {
					$this->modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not find file: '.$source);
				}
			}
			if (is_file($file)) {
				$mintime = filemtime($file);
				if ($mintime > $maxtime) {
					$result[] = $this->config['cacheFolderUrl'].$this->config['jsFile'].$this->config['jsExt'];
					return $result;
				}
			}
			if ($this->config['minifyJs']) {
				require_once 'jsmin.class.php';
				$output = JSMin::minify($output);
			}

			if (!file_put_contents($file, $output)) {
				$this->modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not write JS cache file!');
				return false;
			}
			$newname = $this->config['jsFilename'].'_'.time().$this->config['jsExt'];
			rename($file, $this->config['cacheFolder'].$newname);
			$result[] = $this->config['cacheFolderUrl'] . $newname;
		} else {
			foreach($this->config['jsSources'] as $source) {
				$result[] = str_replace('//', '/', $this->config['baseUrl'].trim($source));
			}
		}
		return $result;
	}

	
	/**
	 * Check and create (if need) MinifyX css file 
	 *
	 * @access public
	 * @return boolean
	 */
	function processCss() {
		$result = array();
		if ($this->config['combineCss']) {
			$file = $this->config['cacheFolder'].$this->config['cssFile'].$this->config['cssExt'];
			$output = '';
			$maxtime = 0;
			foreach($this->config['cssSources'] as $source) {
				$source = str_replace('//', '/', $this->config['basePath'].trim($source));
				if (is_file($source)) {
					$output .= file_get_contents($source)."\n";
					$filetime = filemtime($source);
					if ($filetime > $maxtime) {$maxtime = $filetime;}
				} else {
					$this->modx->log(modX::LOG_LEVEL_ERROR, '[MinifyX] Could not find file: '.$source);
				}
			}
			if (is_file($file)) {
				$mintime = filemtime($file);
				if ($mintime > $maxtime) {
					$result[] = $this->config['cacheFolderUrl'].$this->config['cssFile'].$this->config['cssExt'];
					return $result;
				}
			}
			if ($this->config['minifyCss']) {
				require_once 'cssmin.class.php';
				$output = Minify_CSS_Compressor::process($output);
			}
			
			if (!file_put_contents($file, $output)) {
				$this->modx->log(modX::LOG_LEVEL_ERROR,'[MinifyX] Could not write CSS cache file!');
				return false;
			}
			$newname = $this->config['cssFilename'].'_'.time().$this->config['cssExt'];
			rename($file, $this->config['cacheFolder'].$newname);
			$result[] = $this->config['cacheFolderUrl'] . $newname;
		} else {
			foreach($this->config['cssSources'] as $source) {
				$result[] = str_replace('//', '/', $this->config['baseUrl'].trim($source));
			}
		}
		return $result;
	}
	
	
	/**
	 * Clears the cachefolder for the MinifyX snippet
	 *
	 * @access public
	 * @param array $options Script properties
	 * @return true 
	 */
	public function clearCache() {
		return true;
	}
}