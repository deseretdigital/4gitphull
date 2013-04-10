gitphull
========

A quick way to pull all branches of project. Great for setting up a stage server with a subdomain for each branch.

Getting Started
--------

    $phull = new Gitphull();
    $phull->setRepo('git@github.com:deseretdigital/gitphull.git')
      ->setDomain('deseretdigital.com')
      ->setMasterBranch('master')
      ->setLocation('/var/www/')
      ->setPrefix('gitphull_');
    $phull->run();`

Apache Vhost Setup
--------

    <VirtualHost *:80>
        ServerName branches.example.com
        ServerAlias *.example.com	

	    # A Virtual Document root and * alias are the magic in automagic sites for each branch.
	    # Access the site by using the branch name as a subdomain.
        VirtualDocumentRoot /var/www/gitphull_%1/public

	    # do whatever else you'd do with a vhost, the * alias

    </VirtualHost>


DNS Setup
--------
Point a wildcard to your stage server. Hopefully you can do this for internal DNS only.

Optional Settings
--------

Ignore some branches

`->setIgnoreBranches( array('old','deleteme') )`

Methods that run after events
--------

These methods will be called, allowing you to create symlinks or do other chores that must be done after a branch is updated, deleted, etc.

    afterRun()
    afterBranchClone()
    afterBranchUpdate()
    afterBranchDelete()

