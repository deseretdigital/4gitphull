<?php

class GitphullTest extends PHPUnit_Framework_TestCase
{
    protected $phull;
    protected static $webDir = '/tmp/gitphullTest_www';
    protected static $repoDir = '/tmp/gitphullTest_repo';
    protected static $repoUri = 'file:///tmp/gitphullTest_repo';
    protected static $branches = array(
        'branch1',
        'branch2',
        'ignore_me',
    );
    protected static $ignoredBranches = array(
        'ignore_me',
    );

    public static function setUpBeforeClass()
    {
        mkdir(self::$repoDir);
        mkdir(self::$webDir);

        $currentDir = getcwd();
        chdir(self::$repoDir);

        `git init`;
        `touch master`;
        `git add -A && git commit -m 'added master'`;

        foreach (self::$branches as $branch) {
            exec('git checkout -b ' . escapeshellarg($branch) . ' master');
            exec('touch ' . escapeshellarg($branch));
            exec('git add -A && git commit -m \'added ' . escapeshellarg($branch) . '\'');
        }

        chdir($currentDir);
    }

    public static function tearDownAfterClass()
    {
        exec('rm -rf ' . escapeshellarg(self::$repoDir));
        exec('rm -rf ' . escapeshellarg(self::$webDir));
    }

    protected function setUp()
    {
        $this->phull = new Gitphull();
    }

    public function testSetRepo()
    {
        $result = $this->phull->setRepo(self::$repoUri);
        $this->assertSame($this->phull, $result);
        return $this->phull;
    }

    /**
     * @depends testSetRepo
     */
    public function testSetLocation($phull)
    {
        $result = $phull->setLocation(self::$webDir);
        $this->assertSame($phull, $result);
        return $phull;
    }

    /**
     * @depends testSetLocation
     */
    public function testSetMasterBranch($phull)
    {
        $result = $phull->setMasterBranch('master');
        $this->assertSame($phull, $result);
        return $phull;
    }

    /**
     * @depends testSetMasterBranch
     */
    public function testSetIgnoreBranches($phull)
    {
        $result = $phull->setIgnoreBranches(self::$ignoredBranches);
        $this->assertSame($phull, $result);
        return $phull;
    }

    /**
     * @depends testSetIgnoreBranches
     */
    public function testSetPrefix($phull)
    {
        $result = $phull->setPrefix('gitphull_');
        $this->assertSame($phull, $result);
        return $phull;
    }

    /**
     * @depends testSetPrefix
     */
    public function testRun($phull)
    {
        $phull->run();

        // Check to see if all branches were created in the www dir with
        // the right branch checked out
        $currentDir = getcwd();
        $testBranches = self::$branches;
        array_unshift($testBranches, 'master');
        foreach ($testBranches as $branch) {
            $dir = self::$webDir . '/gitphull_' . $branch;

            // Check to ensure all ignored branches were actually ignored
            if (in_array($branch, self::$ignoredBranches)) {
                $this->assertFileNotExists($dir);
                continue;
            }

            $this->assertFileExists($dir);

            chdir($dir);
            $result = array();
            exec('git rev-parse --abbrev-ref HEAD', $result);
            $this->assertSame($branch, $result[0]);
        }
        chdir($currentDir);
    }

    /**
     * @expectedException UnexpectedValueException
     */
    public function testRunNoSetup()
    {
        $this->phull->run();
    }
}
