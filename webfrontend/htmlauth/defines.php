<?php

define ("CONFIGFILE", "$lbpconfigdir/config.json");
define ("BASEURL", "https://www.services.renault-ze.com/api");
define ("TMPPREFIX", "/run/shm/${lbpplugindir}_");
define ("LOGINFILE", TMPPREFIX . "sessiondata.json");

// The Navigation Bar
$navbar[1]['Name'] = "Settings";
$navbar[1]['URL'] = 'index.php';
 
$navbar[2]['Name'] = "Query links and data";
$navbar[2]['URL'] = 'showdata.php';
 
 
