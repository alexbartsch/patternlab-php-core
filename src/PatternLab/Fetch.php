<?php

/*!
 * StarterKit Class
 *
 * Copyright (c) 2014 Dave Olsen, http://dmolsen.com
 * Licensed under the MIT license
 *
 * Copy a starter kit from GitHub and put it into source/
 *
 */

namespace PatternLab;

use \Alchemy\Zippy\Zippy;
use \PatternLab\Config;
use \PatternLab\Zippy\UnpackFileStrategy;

class Fetch {
	
	public $rules = array();
	
	/**
	* Set-up a default var
	*/
	public function __construct() {
		if (!is_dir(Config::$options["sourceDir"])) {
			mkdir(Config::$options["sourceDir"]);
		}
		if (!is_dir(Config::$options["pluginDir"])) {
			mkdir(Config::$options["pluginDir"]);
		}
	}
	
	/**
	 * Fetch the starter kit from GitHub and put it into source/
	 * @param  {String}    path of the GitHub repo
	 *
	 * @return {String}    the modified file contents
	 */
	public function fetch($commandOption,$package) {
		
		$this->loadRules($options);
		
		// iterate over the rules and see if the current file matches one, if so run the rule
		foreach ($this->$rules as $rule) {
			if ($rule->test($commandOption)) {
				$name          = $rule->name;
				$unpack        = $rule->unpack;
				$writeDir      = $rule->writeDir;
			}
		}
		
		// see if the user passed anythign useful
		if (empty($packageInfo)) {
			print "please provide a path for the ".$name." before trying to fetch it...\n";
			exit;
		}
		
		// figure out the options for the GH path
		list($org,$repo,$tag) = $this->getPackageInfo($package);
		
		//get the path to the GH repo and validate it
		$tarballUrl = "https://github.com/".$org."/".$repo."/archive/".$tag.".tar.gz";
		
		print "downloading the ".$name."...\n";
		
		// try to download the given starter kit
		if (!$starterKit = @file_get_contents($tarballUrl)) {
			$error = error_get_last();
			print $name." wasn't downloaded because:\n\n  ".$error["message"]."\n";
			exit;
		}
		
		// write the starter kit to the temp directory
		$tempFile = tempnam(sys_get_temp_dir(), "pl-sk-archive.tar.gz");
		file_put_contents($tempFile, $starterKit);
		
		print "installing the ".$name."...\n";
		
		// see if the source directory is empty
		$emptyDir = true;
		$checkDir = (!$unpack) ? $writeDir."/".$tag : $writeDir;
		$objects  = new \DirectoryIterator($checkDir);
		foreach ($objects as $object) {
			if (!$object->isDot() && ($object->getFilename() != "README") && ($object->getFilename() != ".DS_Store")) {
				$emptyDir = false;
			}
		}
		
		// if source directory isn't empty ask if it's ok to nuke what's there
		if (!$emptyDir) {
			$stdin = fopen("php://stdin", "r");
			print("delete everything in ".$checkDir." before installing the starter kit? Y/n\n");
			$answer = strtolower(trim(fgets($stdin)));
			fclose($stdin);
			if ($answer == "y") {
				
				print "nuking everything in ".$checkDir."...\n";
				
				$objects = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($checkDir), \RecursiveIteratorIterator::CHILD_FIRST);
				
				// make sure dots are skipped
				$objects->setFlags(\FilesystemIterator::SKIP_DOTS);
				
				foreach($objects as $name => $object) {
					
					if ($object->isDir()) {
						rmdir($name);
					} else if ($object->isFile()) {
						unlink($name);
					}
					
				}
				
			} else {
				print "aborting install of the ".$name."...\n";
				unlink($tempFile);
				exit;
			}
			
		}
		
		// extract, if the zip is supposed to be unpacked do that (e.g. stripdir)
		$zippy      = Zippy::load();
		if ($unpack) {
			$zippy->addStrategy(new UnpackFileStrategy());
		}
		$zipAdapter = $zippy->getAdapterFor('tar.gz');
		$archiveZip = $zipAdapter->open($tempFile);
		$archiveZip = $archiveZip->extract($writeDir);
		
		// remove the temp file
		unlink($tempFile);
		
		print $name." installation complete...\n";
		
	}
	
	/**
	 * Break up the package path
	 * @param  {String}    path of the GitHub repo
	 *
	 * @return {Array}     the parts of the package path
	 */
	protected function getPackageInfo($package) {
		
		$org  = "";
		$repo = "";
		$tag  = "master";
		
		if (strpos($package, "#") !== false) {
			list($package,$tag) = explode("#",$package);
		}
		
		if (strpos($package, "/") !== false) {
			list($org,$repo) = explode("/",$package);
		} else {
			print "please provide a real path to a starter kit...\n";
			exit;
		}
		
		return array($org,$repo,$tag);
		
	}
	
	/**
	* Load all of the rules related to Fetch
	*/
	public function loadRules() {
		foreach (glob(__DIR__."/Fetch/Rules/*.php") as $filename) {
			$rule = str_replace(".php","",str_replace(__DIR__."/Fetch/Rules/","",$filename));
			if ($rule[0] != "_") {
				$ruleClass     = "\PatternLab\Fetch\Rules\\".$rule;
				self::$rules[] = new $ruleClass($options);
			}
		}
	}
	
}