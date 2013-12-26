<?php
 /**
  * @name Gitphull
  * @version 1.0
  * @author  Mark Sticht
  * @link    https://github.com/deseretdigital/gitphull
  *
  * Copyright (c) 2013 Deseret Digital Media
  *
  * LICENSE:
  * Permission is hereby granted, free of charge, to any person obtaining a copy of
  * this software and associated documentation files (the "Software"), to deal in
  * the Software without restriction, including without limitation the rights to
  * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
  * of the Software, and to permit persons to whom the Software is furnished to do
  * so, subject to the following conditions:
  *
  * The above copyright notice and this permission notice shall be included in all
  * copies or substantial portions of the Software.
  *
  * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
  * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
  * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
  * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
  * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
  * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
  * THE SOFTWARE.
  *
  * Except as contained in this notice, the name(s) of the above copyright
  * holders shall not be used in advertising or otherwise to promote the sale,
  * use or other dealings in this Software without prior written authorization.
  *
  **/

class Gitphull {

    /**
     * Path to a git repo
     * @var string
     */
    protected $repo;

    /**
     * Path to the base directory where branches will be checked out to
     * @var string
     */
    protected $location;

    /**
     * The name of your "master" branch
     * @var string
     */
    protected $masterBranch;

    /**
     * A prefix for each branch directory
     * A prefix of "gitphull_" will put the master branch in the "gitphull_master" directory of $location
     * @var string
     */
    protected $prefix = '';

    /**
     * An array of branch names to ignore, they won't be checked out
     * @var array
     */
    protected $ignoreBranches = array();

    /**
     * An array of branch names to pull, all others will be ignored
     * @var array
     */
    protected $onlyBranches = array();

    /**
     * Array of branches that currently exist on the filesystem
     * @var array
     */
    protected $currentBranches = array();

    /**
     * The location of your master branch
     * @var string
     */
    protected $masterDir;

    /**
     * user, group and permissions for everything this checks out
     * @var array
     */
    protected $ownerInfo = array();

    /**
     * Chars that are not valid for paths
     * @var array
     */
    protected $invalidBranchCharacters = array('-','_','/');

    /**
     * Name of the static file to write out, relative to the masterDir
     * @var string
     */
    protected $branchDiffsFileLocation = null;

    /**
     * Name of the static file to write out, relative to the masterDir
     * @var string
     */
    protected $liveDiffFileLocation = null;

    /**
     * Array of connection info for pivotal tracker
     * @var array
     */
    protected $piv = array();

    /**
     * Domain for this project (example.com)
     * @var string
     */
    protected $domain;

    /**
     * Array of info about the branch that is currently being acted on
     * @var array
     */
    protected $current = array();

    /**
     * Url to a page that returns the hash of the release that is in production
     * @var string
     */
    protected $urlCurrentHash = null;

    /**
     * Array of commits comparing local master to production master (requires urlCurrentHash to kno where production master is at)
     * @var unknown
     */
    protected $liveLog = null;

    /**
     * Checkout or update all branches that aren't ignored.
     */
    public function run() {

    	try {

    		$this->currentBranches = $this->getCheckedOutBranches();

    		if($this->currentBranches[0] == '') {
    			unset($this->currentBranches[0]);
    		}

    		/* Checkout "master" */
    		$this->ignoreBranches[] = $this->masterBranch; // ignore it, it is special
    		$this->masterDir = $this->location . $this->prefix . $this->masterBranch;

    		if(!file_exists($this->masterDir)) {
    			@$result = mkdir($this->masterDir);
    			if($result == '1') {
    				$this->msg('created ' . $this->masterDir);
    			} else {
    				//$this->msg('could not create ' . $this->masterDir);
    				throw new Exception('could not create dir: ' . $this->masterDir);
    			}
    		}

    		// also sets branch, branchPath, gitPath in $this->current
    		$this->updateOrClone($this->masterBranch);

    		$remotes = $this->getBranches($this->masterDir);
    		$this->msg("Known remotes:");

    		$this->msg(print_r($remotes, true));
    		// also sets branch, branchPath, gitPath in $this->current
    		$this->deleteOldBranches($this->currentBranches, $remotes, $this->ignoreBranches);

    		/* Clone or update other remote branches */
    		$this->checkoutBranches($remotes);

    		// diff $master with all other known branches, generate an html file of commits that aren't merged
    		$this->generateBranchDiffs();

    		// show commits that are not live
    		if($this->liveDiffFileLocation) {
    			$this->writeLiveDiff();
    		}

    		// we aren't currently operating on a branch, so nuke this data
    		$this->current = array();

    		$this->afterRun();

    	} catch (Exception $e) {
    		$this->msg("Exception caught in run():\n" . $e->getMessage() . "\n\n");
    		exit;
    	}
    }

    /**
     * Set the repo
     * @param string $repo
     * @return gitphull
     */
    public function setRepo($repo) {
        $this->repo = $repo;
        return $this;
    }

    /**
     * Set chars that are not valid (or you don't want to use) in filesystem paths
     * @param array $array
     * @return Gitphull
     */
    public function setInvalidBranchCharacters($array)
    {
        $this->invalidBranchCharacters = $array;
        return $this;
    }

    /**
     * The location to write out a branch diffs file
     * @param string $location
     * @return Gitphull
     */
    public function setBranchDiffsFileLocation($location) {
    	$this->branchDiffsFileLocation = $location;
    	return $this;
    }

    /**
     * The location to write out a branch diffs file
     * @param string $location
     * @return Gitphull
     */
    public function setLiveDiffFileLocation($location) {
    	$this->liveDiffFileLocation = $location;
    	return $this;
    }

    /**
     * Url to a page which returns the hash of the release that is currently in production
     * @param string $location
     * @return Gitphull
     */
    public function setUrlCurrentHash($location) {
    	$this->urlCurrentHash = $location;
    	return $this;
    }

    /**
     * Set token and URL for pivotal tracker
     * @param string $token
     * @param string $apiUrl
     * @return Gitphull
     */
    public function setPivotalTracker($token, $apiUrl = 'https://www.pivotaltracker.com/services/v5/stories/') {
    	$this->piv['token'] = $token;
    	$this->piv['url'] = $apiUrl;
    	return $this;
    }

    /**
     * Branches will appear as sub domains of this domain.
     * @param string $domain
     * @return Gitphull
     */
    public function setDomain($domain) {
    	$this->domain = $domain;
    	return $this;
    }

    /**
     * Set user/group/perms for checked out branches
     * @param string $user
     * @param string $group
     * @param string $mask
     * @return Gitphull
     */
    public function setPermissions($user, $group, $mask) {
    	$this->ownerInfo['user'] = $user;
    	$this->ownerInfo['group'] = $group;
    	$this->ownerInfo['mask'] = $mask;
    	return $this;
    }

    /**
     * Set the root directory that will contain all branches
     * @param string $dir
     * @return gitphull
     */
    public function setLocation($dir) {
        if (substr($dir, -1) !== '/') {
            $dir .= '/';
        }
    	$this->location = $dir;
    	return $this;
    }

    /**
     * Which branch is the "master"?
     * @param string $branch
     * @return gitphull
     */
    public function setMasterBranch($branch) {
    	$this->masterBranch = $branch;
    	return $this;
    }

    /**
     * Branch names that should be ignored
     * @param array $branches
     * @return gitphull
     */
    public function setIgnoreBranches($branches) {
        if(!is_array($branches)) {
            $branches = array($branches);
        }
    	$this->ignoreBranches = $branches;
    	return $this;
    }

    /**
     * Branch names that should be pulled, ignore all others
     * @param array $branches
     * @return gitphull
     */
    public function setOnlyBranches($branches) {
    	if(!is_array($branches)) {
    		$branches = array($branches);
    	}
    	$this->onlyBranches = $branches;
    	// can't ignore the master
    	unset($this->onlyBranches[$this->masterBranch]);
    	return $this;
    }

    /**
     * Set the branch dir prefix (location.prefix.branchName)
     * @param string $prefix
     * @return gitphull
     */
    public function setPrefix($prefix) {
        $this->prefix = $prefix;
        return $this;
    }

    /**
     * Do stuff - after run is finished (all branches are updated)
     * @param string $branch
     */
    protected function afterRun() {
    	// restart your web server, submit an online order for pizza?
    }

    /**
     * Do stuff - after a branch is cloned
     * @param string $branch
     */
    protected function afterBranchClone($branch) {
    	// maybe make some symlinks?
    }

    /**
     * Do stuff - before a branch is updated
     * @param string $branch
     */
    protected function beforeBranchUpdate($branch) {
    	// any additional tasks after $branch is updated
    }

    /**
     * Do stuff - after a branch is updated
     * @param string $branch
     */
    protected function afterBranchUpdate($branch) {
    	// any additional tasks after $branch is updated
    }

    /**
     * Do stuff - after a branch has been deleted
     * @param string $branch
     */
    protected function afterBranchDelete($branch) {
    	// any additional cleanup after $branch is deleted
    }

    /**
     * Run a command
     * @param string $command
     */
    protected function runCommand($command) {
        //echo "\n$command\n";
        exec($command);
    }

    /**
     * Output a message
     * @param string $string
     */
    protected function msg($string) {
        echo "$string\n";
    }

    /**
     * Find all branches that exist on the local filesystem
     * @return array
     */
    protected function getCheckedOutBranches() {
    	$currentBranches = array(); // branches that exist and are managed by this script
    	$tmp = scandir($this->location);
    	foreach($tmp as $t) {
    		if(!is_dir($this->location . $t)) {
    			continue; // only looking for dirs
    		}
    		if(strpos($t, $this->prefix) !== 0) {
    			continue; // does not start with prefix
    		}
    		$branch = str_replace($this->prefix, '', $t);
    		if(in_array($branch, $this->ignoreBranches)) {
    			continue; // skip branches not managed by this script
    		}
    		if(!empty($this->onlyBranches) && !in_array($t, $this->onlyBranches) ) {
    			continue; // if we want only some branches, ignroe all others
    		}
    		$currentBranches[] = trim($branch);
    	}
    	return $currentBranches;
    }

    /**
     * Checkout all remote branches that are not ignored
     * @param array $branches
     */
    protected function checkoutBranches($branches) {
    	foreach($branches as $b) {
    		if(in_array($b, $this->ignoreBranches)) {
    			continue;
    		}
    		if(!empty($this->onlyBranches) && !in_array($b, $this->onlyBranches) ) {
    			continue; // if we want only some branches, ignroe all others
    		}
    		$this->msg("Checkout Branch $b");
    		$this->updateOrClone($b);
    	}
    }

    /**
     * Reset and pull a branch directory
     * @param string $branch
     */
    protected function update($branch) {

        $gitPath = $this->current['gitPath'];
        $this->beforeBranchUpdate($branch);

    	$cmd = "git --git-dir=$gitPath/.git --work-tree=\"$gitPath\" reset --hard";
    	$this->msg("Hard reset on $gitPath");
    	$this->runCommand($cmd);

    	$cmd = "git --git-dir=$gitPath/.git --work-tree=\"$gitPath\" checkout $branch";
    	$this->runCommand($cmd);
    	$cmd = "cd $gitPath ; git pull";
    	$this->msg($cmd);
    	system($cmd, $result);
    	$cmd = "touch $gitPath/managedbranch.txt";
    	$this->msg($cmd);
    	$this->runCommand($cmd);

    }

    /**
     * Git clone a branch from a repo into a location. Add a file to track that it was cloned by this code
     * @param string $branch
     */
    protected function klone($branch) {
    	$dir = $this->current['gitPath'];
    	$cmd = "git clone --branch=$branch {$this->repo} {$dir}";
    	$this->msg($cmd);
    	$this->runCommand($cmd);
    	$cmd = "touch {$this->location}{$this->prefix}{$branch}/managedbranch.txt";
    	$this->msg($cmd);
    	$this->runCommand($cmd);
    }

    /**
     * Get a list of branches that exist
     * @return array
     */
    protected function getBranches() {
        $location = $this->location . $this->prefix . $this->masterBranch;
    	$cmd = "git --git-dir=$location/.git --work-tree=\"$location\" branch -r";
    	$this->msg($cmd);
    	$rs = shell_exec($cmd);
    	$lines = preg_split("/\n/", trim($rs));
    	foreach($lines as $k => &$l) {
    		$l = trim(str_replace('origin/', '', $l));
            // Some repos have an entry called "HEAD -> master", we want to ignore this
            // remote. Check for any remotes that have a space in them.
            if(strpos($l, ' ') !== false)
            {
                unset($lines[$k]);
            }
    	}

    	return $lines;
    }

    /**
     * Cleanup old branches - see which remotes no longer exist and nuke them
     *
     * @param string $checkedOut
     * @param array $remotes
     * @param array $notManaged
     */
    protected function deleteOldBranches($checkedOut, $remotes, $notManaged) {

    	if(!count($checkedOut) || !count($remotes)) {
    		return;
    	}
    	foreach($notManaged as &$m) {
    		$m = str_replace($this->invalidBranchCharacters, '', $m);
    	}
    	foreach($remotes as &$r) {
    		$r = str_replace($this->invalidBranchCharacters, '', $r);
    	}

    	foreach($checkedOut as $co) {
    		if(in_array($co, $notManaged)) {
    			continue;
    		}
    		if(in_array($co, $remotes)) {
    			continue;
    		}

    		$branchPath = str_replace($this->invalidBranchCharacters, '', $co);

    		$this->current = array();
    		$this->current['branch'] = $co;
    		$this->current['branchPath'] = $branchPath;
    		$dir = str_replace($this->masterBranch, $branchPath, $this->masterDir);
    		$this->current['gitPath'] = $dir;

    		$this->msg("check $dir/managedbranch.txt");
    		if(file_exists($dir . "/managedbranch.txt")) {
    			$this->msg("Delete $co");
    			$cmd = "rm -Rf $dir";
    			$this->runCommand($cmd);
    			$this->afterBranchDelete($co);
    		} else {
    			$this->msg("Keep $co");
    		}
    	}
    }

    /**
     * Update or cloen a branch
     * @param string $branch
     */
    protected function updateOrClone($branch) {
    	$repo = $this->repo;
    	$dir = $this->masterDir;
    	$this->current['branch'] = $branch;
    	$this->current['branchPath'] = str_replace($this->invalidBranchCharacters, '', $branch);
    	$this->current['gitPath'] = $dir;

    	if($branch != $this->masterBranch) {
    	    // path for branches other than master
    		$dir = str_replace($this->masterBranch, $this->current['branchPath'], $this->masterDir);
    		$this->current['gitPath'] = $dir;

    	} else {
    	    // "master" branch (fetch names of other branches)
    		$cmd = "git --git-dir=$this->masterDir/.git --work-tree=\"". $this->masterDir ."\" remote prune origin";
    		$this->msg($cmd);
    		$this->runCommand($cmd);
    		$cmd = "git --git-dir=$this->masterDir/.git --work-tree=\"". $this->masterDir ."\" fetch";
    		$this->msg($cmd);
    		$this->runCommand($cmd);
    		$this->msg("purned and fetched " . $this->masterBranch);
    	}

		if(!file_exists($dir)) {
	    	@$result = mkdir($dir);
	    	if($result == '1') {
	    		$this->msg('created ' . $dir);
	    	} else {
	    		$this->msg('could not create ' . $dir);
	    		throw new Exception('could not create branch dir ' . $dir);
	    	}
		}

    	// Either clone or update a branch
    	if(!file_exists($dir.'/.git')) {
    		$this->msg("CLONE $branch");
    		$this->klone($branch);
    		$this->applyPermissions();
    		$this->afterBranchClone($branch);
    	} else {
    		$this->msg("update $branch");
    		$this->beforeBranchUpdate($branch);
    		$this->update($branch);
    		$this->applyPermissions();
    		$this->afterBranchUpdate($branch);
    	}

    }

    /**
     * If user/group/perms info was provided, make it so
     * @return boolean
     */
    protected function applyPermissions() {

    	$dir = $this->current['gitPath'];

    	if(empty($this->ownerInfo)) {
    		return false;
    	}

    	if(!empty($this->ownerInfo['user'])) {
    		$cmd = 'chown -R ' . $this->ownerInfo['user'] . " $dir";
    		shell_exec($cmd);
    	}

    	if(!empty($this->ownerInfo['group'])) {
    		$cmd = 'chgrp -R ' . $this->ownerInfo['group'] . " $dir";
    		shell_exec($cmd);
    	}

    	if(!empty($this->ownerInfo['mask'])) {
    		$cmd = 'chmod -R ' . $this->ownerInfo['mask'] . " $dir";
    		shell_exec($cmd);
    	}

    	return true;

    }

    /**
     * If the repo is on github, get the path to it
     * @return Ambigous <NULL, string>
     */
    protected function getRepoPath() {
    	$repoPath = null;
    	$repoFound = preg_match('/https:\/\/github.com(.*)\.git/', $this->repo,$match);
    	if($repoFound) {
    		$repoPath = $match[1];
    	}
    	if(!$repoPath) {
    		$repoFound = preg_match('/git@github.com:(.*)\.git/', $this->repo,$match);
    		if($repoFound) {
    			$repoPath = '/' . $match[1];
    		}
    	}
    	return $repoPath;
    }

    /**
     * Generate a diff of branches (compared to master)
     */
    protected function generateBranchDiffs() {
		if(empty($this->branchDiffsFileLocation)) {
			return;
		}

		// so we can link to github
		$repoPath = $this->getRepoPath();

		$html = '<html><head><title>Branches</title></head><body>';

		// write out an index of branches
		$html .= '<table width=200 cellspacing=0 cellpadding=2><tr><td>Branch</td><td>Changes</td></tr>';
		foreach($this->currentBranches as $b) {
			$result = null;
			$branchPath = str_replace($this->invalidBranchCharacters,'',$b);
			$historyDir = str_replace($this->masterBranch, $branchPath, $this->masterDir);

			if($b == $this->masterBranch || $branchPath == '') {
				continue;
			}
			$html .= '<tr><td><a href="http://'. $branchPath .'.' . $this->domain . '/">' . $b . "</a></td><td><a href=\"#$branchPath\">Changes</a><BR></td></tr>\n";
		}
		$html .= '</table><hr>';


		/* try to grab some log info */
		foreach($this->currentBranches as $b) {

			$result = null;
			$branchPath = str_replace($this->invalidBranchCharacters,'',$b);
			$historyDir = str_replace($this->masterBranch, $branchPath, $this->masterDir);

			if($b == $this->masterBranch) {
				continue;
			}

			if($repoPath) {
				$html .= '<h3><a target="ghb" id="'. $branchPath .'" href="https://github.com' . $repoPath . '/tree/'. $b .'">' . $b . "</a></h3>\n";
			} else {
				$html .= '<h3><a target="ghb" id="'. $branchPath .'" href="https://github.com' . $repoPath . '/tree/'. $b .'">' . $b . "</a></h3>\n";
			}

			// diff this branch with master and output the resutls to html
			$cmd = "git --git-dir=$historyDir/.git --work-tree=\"$historyDir\" log $b ^{$this->masterBranch} --no-merges";
			// git --git-dir=/var/www/connect_api/.git --work-tree="/var/www/connect_api" log api ^master --no-merges
			//$html .= $cmd . '<BR>';
			exec($cmd, $result);
			if(count($result)> 0) {
				foreach($result as $line) {

					$piv = 0;
					$status = '';
					if(!empty($this->piv['token'])) {

						// find a piv number - ok any number and hope it is right
						//preg_match('/([0-9]{6,10})\]/', $line, $matches);
						preg_match('/#([0-9]{6,10})/', $line, $matches);
						if(!empty($matches[1])) {
							$piv = (int) $matches[1];
						}

						$status = '';
						if($piv > 0 ) {
							$link = '<a href="https://www.pivotaltracker.com/story/show/'. $piv .'" target="piv">'. $piv . "</a>";
							$line = str_replace($piv, $link, $line);
							$pivInfo = $this->getPivInfo($piv);
							$status = trim(strtolower($pivInfo['current_state']));
						}
					}

					// look for commit hash
					preg_match('/commit [0-9a-f]{40}/', $line, $commits);
					if(!empty($commits[0])) {
						$commit = str_replace('commit ', '', $commits[0]);
						$link = '<a href="https://github.com'. $repoPath .'/commit/'. $commit .'" target="gh">'. $commit . "</a>";
						$line = str_replace($commit, $link, $line);
					}

					if($piv > 0 && !empty($status)) {
						$line = $status . '<BR>' . $line;
					}

					$html .= $line . "<br>\n";
				}
			}

			$html .= "<hr>";
		}

		$html .= '</body></html>';
		$indexFile = $this->masterDir . $this->branchDiffsFileLocation;
		$this->msg('Write diff file to ' . $indexFile);
		file_put_contents($indexFile, $html);



    }

    /**
     * Hit pivotal tracker to get info on the story
     * @param string $pivStoryId
     * @return mixed
     */
    protected function getPivInfo($pivStoryId) {

		$token = $this->piv['token'];
		$url = $this->piv['url'] . $pivStoryId;
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    			"X-TrackerToken: $token"
		));
		$result = curl_exec($ch);
		curl_close($ch);
		$json = json_decode($result, true);

		return $json;
    }

    /**
     * Compare log of local master, relative to where production master is at.
     * Lists commits in local master that aren't in production yet.
     * Sets data in $this->liveData
     * @return Gitphull
     */
    protected function liveDiff() {

	    // find current branch
	    $branch = $this->masterBranch;

	    $this->msg("Current branch: $branch");
	    $rs = null;

	    // https://github.com/deseretdigital/deseret-connect/commit/18eeb230676c4faa81e009ff263376420362aea6
		// https://www.pivotaltracker.com/story/show/30848279

		// get live version (hash)
		@$liveHash = trim(file_get_contents($this->urlCurrentHash));
		$this->msg('live hash = ' . $liveHash);
		if(!$liveHash) {
			$this->liveLog = null;
			return $this;
		}

		if(!$this->masterDir) {
			$this->masterDir = $this->location . $this->prefix . $this->masterBranch;
		}

		// commits are stored in here
		$log = array();
		$limit = 30;

		$cmd = "git --git-dir={$this->masterDir}/.git --work-tree={$this->masterDir}/ log -$limit";
		exec($cmd, $rs);

		$foundLive = false;
		$style = '';
		$status = '';
		if(count($rs)) {
			$item = array();
			foreach($rs as $line) {
				// is this the start of a new commit?
				if(strpos($line, 'commit') !== false) {

					//ignore merges
	    			if(!empty($item['merge']) && $item['github'] != $liveHash) {
		   				$item = array();
	    			}

	    			// output current entry and reset
	    			if(count($item)){
	    				$log[] = $item;
	    				$item = array();
	    			}

					$cmt = str_replace('commit ', '', $line);
	    			$insert = '';
	    			if($cmt == $liveHash) {
	    				$insert = '<div style="background-color: #900; color:#FFF; padding:2px;">Live</div>';
	    				$item['live'] = true;
	    				$foundLive = true;
	    				$style = '';
	    				$state = '';
	    			}
	    			$line = substr($line, 0, 15); // we don't need the whole hash

	    			//$item['insert'] = $insert;
	    			$item['github'] = $cmt;

	    		} else {
					// this is just a new line on an existing entry(commit)
					// find a piv number - ok any number, we aren't picky - and hope it is right
                    $piv = 0;
                    if(!empty($this->piv)) {
						//preg_match('/([0-9]{6,10})\]/', $line, $matches);
		    			preg_match('/#([0-9]{6,10})/', $line, $matches);
		    			if(!empty($matches[1])) {
		    				$piv = (int) $matches[1];
		    			}
                    }

	    			if($piv > 0 ) {
	    				$bg = '#FFF';
	    				$item['pivId'] = $piv;
	    				if(!$foundLive || 1) {
	    					$pivInfo = $this->getPivInfo($piv);
	    					$item['piv'] = $pivInfo;
	    					$status = trim(strtolower($pivInfo['current_state']));
	    					if($status != '') {
	    						$item['status'] = $status;
	    					}
	    				}
	    				//$link = '<a href="https://www.pivotaltracker.com/story/show/'. $piv .'" target="piv">'. $piv . "</a>";
	    				$link = '';
	    				//$line = str_replace('[#' . $piv . ']', '<span style="float:right">[#'. $link . ']</span>', $line);
	    				$style = "background-color: $bg ;";
	    			}
					if(strpos($line, 'Author') !== false) {
    					$item['author'] = $line;
    				} else if(strpos($line, 'Merge') !== false) {
    					$item['merge'] = $line;
    				} else if(strpos($line, 'Date') === 0) {
    					@$item['date'] .= $line;
    				} else {
    					if(empty($item['body'])) {
    						$item['body'] = $line;
    					} else {
    						$item['body'] .= $line;
    					}
    				}
    			}

    		} // foreach

    		// output last entry
    		$style = '';
    		if(count($item)){
    			$log[] = $item;
    			$item = array();
    		}

		$this->liveLog = $log;

		return $this;

		}


	} // end live()


	public function writeLiveDiff() {

		if(empty($this->liveLog)) {
			$this->liveDiff();
		}
		if(empty($this->liveLog)) {
			return 'Unable to render report';
		}

		$this->repoPath = $this->getRepoPath();

		ob_start();
		include_once 'liveLog.php';
		$contents = ob_get_contents();
		ob_end_clean();

		$location = $this->masterDir . $this->liveDiffFileLocation;
		$this->msg('Write live log to ' . $location . '. Content length = ' . strlen($contents));
		file_put_contents($location, $contents);

		exit;
	}

}
