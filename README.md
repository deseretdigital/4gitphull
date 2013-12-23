gitphull
========

A quick PHP class to pull all branches of project. It works great for setting up a stage server with each branch on its own site.

Getting Started
--------

    $phull = new Gitphull();
    $phull->setRepo('https://github.com/deseretdigital/gitphull.git')
      ->setMasterBranch('master')
      ->setLocation('/var/www/')
      ->setPrefix('gitphull_');
    $phull->run();

Assuming you have a master, bugs and foo branch, the following directories will be created:

    /var/www/gitphull_master
    /var/www/gitphull_bugs
    /var/www/gitphull_foo

You can setup apache so the following subdomains serve up the corrosponding branch of code:

    http://master.example.com
    http://bugs.example.com
    http://foo.example.com


Apache Vhost Setup
--------

Since this vhost matches anything, it should be the last to load so it doesn't grab traffic for www.example.com

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

    ->setIgnoreBranches(array('old','deleteme'))

Set user/group/permissions

    ->setPermissions('www-data', 'www-data', '774')

What change? Show commits that are not merged into master. Path is relative to the location of the master

    ->setBranchDiffsFileLocation('/diff.html')

Pivotal Tracker - If an API token is provided, this will attempt to parse out [#123] as story id 123 and add info from the tracker to the diff page.

    ->setPivotalTracker('your_tracker_api_token')

Set Domain - Diffs File will create links to each branch. The domain will be used to create  links to branchName.domain

    ->setDomain('example.com')

Methods that run after events
--------

These methods will be called, allowing you to create symlinks or do other chores that must be done after a branch is updated, deleted, etc.

    afterRun()
    afterBranchClone()
    afterBranchUpdate()
    afterBranchDelete()


