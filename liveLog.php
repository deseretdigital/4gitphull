<html>
<head>
</head>
<body>
<style>
.pull-right {
	float-right;
}
.commit {
	border-top: 1px solid black;
	margin:5px;
}

.accepted {
	background-color: #C6D9B7;
}
.finished {
	background-color: #F0F0C6;
}
.started {
	background-color: #E6E6E6;
}
.delivered {
	background-color: #FFB96B;
}
.rejected {
	background-color: #A85400;
}
.unscheduled {
	background-color: #F8FFD3;
}

.live {
	background-color: #900;
	color:#FFF;
	padding: 2px 2px 2px 5px;
	font-weight:bold;
}

.points {
	font-weight:bold;
	font-size:14px;
	padding:5px;
}

.commit {
	font-size:14px;
	padding:5px;
}

.name {
	font-size:14px;
}

a {
	color: #999;
}

a:hover {
	color: #000;
}

</style>

<?php
$data = $this->liveLog;
$outputLive = false;

foreach($data as $d) {

    if(!empty($d['live']) && $outputLive === false) {
	$outputLive = true;
    	echo "<div class='live'>Live &#9660;</div>";
    }

    if(!empty($d['merge'])) {
        continue;
    }

    if(!empty($d['github'])) {
        $d['github_link'] = "<a target=\"github\" href=\"https://github.com". $this->repoPath ."/commit/". $d['github'] ."\">". substr($d['github'],0,10) ."</a>\n";
    } else {
        $d['github_link'] = '';
    }

    $d['pivLink'] = '';
    $d['name'] = 'Commit ' . $d['github'];
    $d['estimate'] = '';
    $d['labels'] = array();
    if(!empty($d['pivId'])) {
        $d['pivLink'] = '<a href="https://www.pivotaltracker.com/story/show/'. $d['pivId'] .'" target="piv">'. substr($d['pivId'], 0, 10) . "</a>";
        $d['body'] = str_replace($d['pivId'], $d['pivLink'], $d['body']);
        if(!empty($d['piv'])) {
            $piv = $d['piv'];
            @$d['estimate'] = $piv['estimate'];
            $d['name'] = $piv['name'];
            if(count($d['piv']['labels'])) {
                foreach($d['piv']['labels'] as $label) {
                    if($label['kind'] == 'label') {
                        $d['labels'][] = '<span class="badge badge-info">' . $label['name'] . '</span>';
                    }
                }
            }
        }
    }

    if(empty($d['status'])) {
        $d['status'] = '';
    }
    $class = 'commit ';
    switch ($d['status']) {
        case 'accepted':
            $class .= 'accepted ';
        break;
        case 'rejected':
        	$class .= 'rejected ';
        	break;
        case 'delivered':
        	$class .= 'delivered ';
        	break;
        case 'finished':
            $class .= 'finished ';
        break;
        case 'started':
            $class .= 'started ';
        break;
        case 'unscheduled':
            $class .= 'unscheduled ';
        break;
        default:

            ;
        break;
    }
    if($outputLive === false) {
        //echo "<div class='live'>Live</div>";
    }
    echo "<div class=\"$class\">";
    echo "<span class=\"pull-right points\"> " . $d['estimate'] . "</span>";
    echo "<span class=\"pull-right \">" . $d['github_link'] . " </span>";
    echo '<b>' . ucfirst($d['status']) . '</b> ';
    echo "<span class=\"name\">" . $d['name'] . '</span><br>';

    echo $d['body'];
    echo '<div>' . $d['author'] . '</div>';
    echo '<div>' . $d['date'] . '</div>';
    if(count($d['labels'])) {
        echo join(' ', $d['labels']);
    }

    echo "</div>";

}
?>
</body>
</html>
