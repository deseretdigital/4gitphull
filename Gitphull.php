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
     * Array of branches that currently exist on the filesystem
     * @var array
     */
    protected $currentBranches = array();

    /**
     * The location of your master branch
     * @var string
     */
    protected $masterDir;

    protected $invalidBranchCharacters = array('-','_','/');

    /**
     * Checkout or update all branches that aren't ignored.
     */
    public function run() {

        $this->currentBranches = $this->getCheckedOutBranches();

        /* Checkout "master" */
        $this->ignoreBranches[] = $this->masterBranch; // ignore it, it is special
        $this->masterDir = $this->location . $this->prefix . $this->masterBranch;
        $this->updateOrClone($this->masterBranch);

        $remotes = $this->getBranches($this->masterDir);
        $this->msg("Known remotes:");

        $this->msg(print_r($remotes, true));
        $this->deleteOldBranches($this->currentBranches, $remotes, $this->ignoreBranches);

        /* Clone or update other remote branches */
        $this->checkoutBranches($remotes);

        $this->afterRun();

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

    public function setInvalidBranchCharacters($array)
    {
        $this->invalidBranchCharacters = $array;
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
    		$this->msg("Checkout Branch $b");
    		$this->updateOrClone($b);
    	}
    }

    /**
     * Reset and pull a branch directory
     * @param string $branch
     */
    protected function update($branch) {

        $gitPath = $this->location . $this->prefix . $branch;

    	$cmd = "git --git-dir=$gitPath/.git --work-tree=\"$gitPath\" reset --hard";
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
    	$cmd = "git clone --branch=$branch {$this->repo} {$this->location}{$this->prefix}{$branch}";
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
    	unset($lines[0]);
    	foreach($lines as &$l) {
    		$l = trim(str_replace('origin/', '', $l));
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
    		$dir = str_replace($this->masterBranch, $co, $this->masterDir);
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
    	$branchpath = $dir;

    	if($branch != $this->masterBranch) {
    	    // path for branches other than master
    		$branchpath = str_replace($this->invalidBranchCharacters, '', $branch);
    		$dir = str_replace($this->masterBranch, $branchpath, $this->masterDir);
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

    	@mkdir($dir);

    	// Either clone or update a branch
    	if(!file_exists($dir.'/.git')) {
    		$this->klone($branch);
    		$this->afterBranchClone($branch);
    	} else {
    		$this->msg("update $branch");
    		$this->update($branch);
    		$this->afterBranchUpdate($branch);
    	}

    }

}
