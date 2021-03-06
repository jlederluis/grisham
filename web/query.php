<?php

// Query processor


// Process the keyword query
if(isset($_GET['q']) && isset($_GET['type']) && $_GET['type'] == "keyword_realtime") {
    header('Content-type: application/json');

    $dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

    // Decode query
    $keyword = pg_escape_string(stripslashes(rawurldecode($_GET['q']))); // Get the keyword


    $query = "SELECT person, papertitle, pubyear, venue, abstract, ".
        "CASE WHEN (person ILIKE '%$keyword%') THEN 'author' ".
        "		 WHEN (papertitle ILIKE '%$keyword%' OR abstract ILIKE '%$keyword%') THEN 'paper' ".
        "ELSE 'none' END as type ".
        "FROM paper left join author ON id=pid ".
        "WHERE person ILIKE '%$keyword%' OR ".
        "papertitle ILIKE '%$keyword%' OR abstract ILIKE '%$keyword%' ".
        "ORDER BY type, pubyear DESC ";

    // Add LIMIT and OFFSET to the query if present
		if(isset($_GET['limit']) && is_numeric($_GET['limit']))
        $thelimit = rawurldecode($_GET['limit']); 
    else
        $thelimit = 50;

    $query = $query . " LIMIT $thelimit ";

    if(isset($_GET['offset']) && is_numeric($_GET['offset']))
        $theoffset = rawurldecode($_GET['offset']);
    else
        $theoffset = 0;

    $query = $query . " OFFSET $theoffset";


    // END THE QUERY
    $query = $query . ";";

    // Make a query to the DB
    list($tic_usec, $tic_sec) = explode(" ", microtime());
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
    list($toc_usec, $toc_sec) = explode(" ", microtime());

    $querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

    // Iterate over results
    $rows = array();
    while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $rows[] = $line;
    }

    $rows["q"] = urldecode($query);
    $rows["querytime"] = $querytime;
    $rows["rowcount"] = pg_num_rows($result);

    if ($rows["rowcount"] > 0) {
        $rows["headers"] = array_keys($rows[0]);
    }
    else {
        // Some default header for no result
        $rows["headers"] = array(0 => 100, "color" => "red"); 
    }

    // Show the json result
    print json_encode($rows); 

    // Free the result set
    pg_free_result($result);

    // Close the connection
    pg_close($dbconn);
}
else if(isset($_GET['q']) && isset($_GET['type']) && $_GET['type'] == "keyword") {
    header('Content-type: application/json');

    $dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

    // Decode query
    $keyword = pg_escape_string(stripslashes(rawurldecode($_GET['q']))); // Get the keyword


    $splitwords = explode(" ", $keyword);
    $size = count($splitwords);

    $query = "SELECT person, papertitle, pubyear, venue, abstract, ".
        " CASE WHEN (person ILIKE '%$keyword%') THEN 'author' ".
        " WHEN (papertitle ILIKE '%$keyword%' OR abstract ILIKE '%$keyword%') THEN 'paper' ".
        " ELSE 'none' END as type ".
        "FROM paper LEFT JOIN author ON paper.id=author.pid, paperindex ". 
        "WHERE paper.id = paperindex.pid AND author.pid = paperindex.pid";

    for($i = 0; $i<$size; $i++)
    {
        $wordsi = $splitwords[$i];
        $query = $query . " OR paperindex.word iLIKE '$wordsi%' ";
    }

    $query = $query . " ORDER BY type, pubyear DESC ";
 
    // Add LIMIT and OFFSET to the query if present
		if(isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $thelimit = rawurldecode($_GET['limit']); 
    }
    else {
        $thelimit = 50;
    }

    $query = $query . " LIMIT $thelimit ";

    if(isset($_GET['offset']) && is_numeric($_GET['offset']))
        $theoffset = rawurldecode($_GET['offset']);
    else
        $theoffset = 0;

    $query = $query . " OFFSET $theoffset";


    // END THE QUERY
    $query = $query . ";";

    // Make a query to the DB
    list($tic_usec, $tic_sec) = explode(" ", microtime());
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
    list($toc_usec, $toc_sec) = explode(" ", microtime());

    $querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

    // Iterate over results
    $rows = array();
    while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $rows[] = $line;
    }

    $rows["q"] = urldecode($query);
    $rows["querytime"] = $querytime;
    $rows["rowcount"] = pg_num_rows($result);

    if ($rows["rowcount"] > 0) {
        $rows["headers"] = array_keys($rows[0]);
    }
    else {
        // Some default header for no result
        $rows["headers"] = array(0 => 100, "color" => "red"); 
    }

    // Show the json result
    print json_encode($rows); 

    // Free the result set
    pg_free_result($result);

    // Close the connection
    pg_close($dbconn);
}
///////////////////////////
//RANKING FUNCTION STARTS//
///////////////////////////
else if(isset($_GET['q']) && isset($_GET['type']) && $_GET['type'] == "rank_realtime") {
    header('Content-type: application/json');

    $dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

    // Decode query
    $id = pg_escape_string(stripslashes(rawurldecode($_GET['q']))); // Get the keyword
    $smallestDouble = "0.000000000001";



    $query = "select comparetable.weight, comparetable.pid".
        " from (select sum(ln(1 - value.t-$smallestDouble)) + ln(value.pi+$smallestDouble) - ln(1-value.pi-$smallestDouble) as weight, value.pid as pid ".
        " from (select unnest(tab.topic_distribution) as t, tab.pid as pid, tab.topic_distribution[$id] as Pi".
        " from  (select pid, topic_distribution from theta) as tab)".
        "as value GROUP BY value.pid, value.pi) as comparetable ORDER BY comparetable.weight DESC";

    // Add LIMIT and OFFSET to the query if present
		if(isset($_GET['limit']) && is_numeric($_GET['limit']))
        $thelimit = rawurldecode($_GET['limit']); 
    else
        $thelimit = 50;

    $query = $query . " LIMIT $thelimit ";

    if(isset($_GET['offset']) && is_numeric($_GET['offset']))
        $theoffset = rawurldecode($_GET['offset']);
    else
        $theoffset = 0;

    $query = $query . " OFFSET $theoffset";

    // END THE QUERY
    $query = $query . ";";

    // Make a query to the DB
    list($tic_usec, $tic_sec) = explode(" ", microtime());
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
    list($toc_usec, $toc_sec) = explode(" ", microtime());

    $querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

    // Iterate over results
    $rows = array();
    while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $rows[] = $line;
    }

    $rows["q"] = urldecode($query);
    $rows["querytime"] = $querytime;
    $rows["rowcount"] = pg_num_rows($result);

    if ($rows["rowcount"] > 0) {
        $rows["headers"] = array_keys($rows[0]);
    }
    else {
        // Some default header for no result
        $rows["headers"] = array(0 => 100, "color" => "red"); 
    }

    // Show the json result
    print json_encode($rows); 

    // Free the result set
    pg_free_result($result);

    // Close the connection
    pg_close($dbconn);
}
////////////////// RANK precomputed ///////////////////
else if(isset($_GET['q']) && isset($_GET['type']) && $_GET['type'] == "rank") {
    header('Content-type: application/json');

    $dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

    // Decode query
    $id = pg_escape_string(stripslashes(rawurldecode($_GET['q']))); // Get the keyword



    $query = "select pid, papertitle, pubyear, venue, abstract from precomputed_rank, paper where topic_id=$id and id=pid ";


    // Add LIMIT and OFFSET to the query if present
		if(isset($_GET['limit']) && is_numeric($_GET['limit']))
        $thelimit = rawurldecode($_GET['limit']); 
    else
        $thelimit = 50;

    if(isset($_GET['offset']) && is_numeric($_GET['offset']))
        $theoffset = rawurldecode($_GET['offset']);
    else
        $theoffset = 0;

    $query = $query . " OFFSET $theoffset";

    // END THE QUERY
    $query = $query . ";";	

    // Make a query to the DB
    list($tic_usec, $tic_sec) = explode(" ", microtime());
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
    list($toc_usec, $toc_sec) = explode(" ", microtime());

    $querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

    // Iterate over results
    $rows = array();
    while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $rows[] = $line;
    }

    $rows["q"] = urldecode($query);
    $rows["querytime"] = $querytime;
    $rows["rowcount"] = pg_num_rows($result);

    if ($rows["rowcount"] > 0) {
        $rows["headers"] = array_keys($rows[0]);
    }
    else {
        // Some default header for no result
        $rows["headers"] = array(0 => 100, "color" => "red"); 
    }

    // Show the json result
    print json_encode($rows); 

    // Free the result set
    pg_free_result($result);

    // Close the connection
    pg_close($dbconn);
}
///////////////////////////
//RANKING FUNCTION ENDS ///
///////////////////////////
// Process the keyword query
else if(isset($_GET['q']) && isset($_GET['type']) && isset($_GET['pid']) && isset($_GET['model']) && $_GET['type'] == "neighborhood") {
    header('Content-type: application/json');

    $dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

    // Decode query
    $keyword = pg_escape_string(stripslashes(rawurldecode($_GET['q']))); // Get the keyword
    $model = pg_escape_string(stripslashes(rawurldecode($_GET['model'])));
    $pid = pg_escape_string(stripslashes(rawurldecode($_GET['pid'])));


    $query = "select r.citation as pid, viru_kl(t.topic_distribution, ARRAY$model::double precision[]) as score ".
             "from reference as r , theta as t ".
             "where r.pid = $pid  AND t.pid = r.citation;";


    // Add LIMIT and OFFSET to the query if present
		if(isset($_GET['limit']) && is_numeric($_GET['limit']))
        $thelimit = rawurldecode($_GET['limit']); 
    else
        $thelimit = 50;

    $query = $query . " LIMIT $thelimit ";

    if(isset($_GET['offset']) && is_numeric($_GET['offset']))
        $theoffset = rawurldecode($_GET['offset']);
    else
        $theoffset = 0;

    $query = $query . " OFFSET $theoffset";


    // END THE QUERY
    $query = $query . ";";

    // Make a query to the DB
    list($tic_usec, $tic_sec) = explode(" ", microtime());
    $result = pg_query($query) or die('Query failed: ' . pg_last_error());
    list($toc_usec, $toc_sec) = explode(" ", microtime());

    $querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

    // Iterate over results
    $rows = array();
    while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
        $rows[] = $line;
    }

    $rows["q"] = urldecode($query);
    $rows["querytime"] = $querytime;
    $rows["rowcount"] = pg_num_rows($result);

    if ($rows["rowcount"] > 0) {
        $rows["headers"] = array_keys($rows[0]);
    }
    else {
        // Some default header for no result
        $rows["headers"] = array(0 => 100, "color" => "red"); 
    }

    // Show the json result
    print json_encode($rows); 

    // Free the result set
    pg_free_result($result);

    // Close the connection
    pg_close($dbconn);
}

else if(isset($_GET['q']) && isset($_GET['type']) && $_GET['type'] == "citations" && is_numeric($_GET['q'])) {
	// Return the citations according to the q=paperid	
	header('Content-type: application/json');

	$pid = pg_escape_string(stripslashes(strtolower(rawurldecode($_GET['q']))));

	$dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

	// Decode query

	$query = "SELECT p.id AS pid, p.papertitle AS title, p.pubyear AS year, p.venue AS venue, p.abstract AS abstract ".
				", (SELECT topic_distribution FROM theta AS t WHERE t.pid = p.id LIMIT 1) AS topic ".
				"FROM reference AS r INNER JOIN paper AS p ON (r.citation = p.id) ".
				"WHERE r.pid = $pid ";

	// Add LIMIT and OFFSET to the query if present
	if(isset($_GET['limit']) && is_numeric($_GET['limit']))
		$thelimit = rawurldecode($_GET['limit']); 
	else
		$thelimit = 50;

	$query = $query . " LIMIT $thelimit ";

	if(isset($_GET['offset']) && is_numeric($_GET['offset']))
		$theoffset = rawurldecode($_GET['offset']);
	else
		$theoffset = 0;

	$query = $query . " OFFSET $theoffset";

	// END THE QUERY
	$query = $query . ";";

	// Make a query to the DB
	list($tic_usec, $tic_sec) = explode(" ", microtime());
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	list($toc_usec, $toc_sec) = explode(" ", microtime());

	$querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

	// Iterate over results
	$rows = array();
	while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$rows[] = $line;
	}

	$rows["q"] = urldecode($query);
	$rows["querytime"] = $querytime;
	$rows["rowcount"] = pg_num_rows($result);

	if ($rows["rowcount"] > 0) {
		$rows["headers"] = array_keys($rows[0]);
	}
	else {
		// Some default header for no result
		$rows["headers"] = array(0 => 100, "color" => "red"); 
	}

	// Show the json result
	print json_encode($rows); 

	// Free the result set
	pg_free_result($result);

	// Close the connection
	pg_close($dbconn);

}
else if(isset($_GET['q']) && isset($_GET['type']) && $_GET['type'] == "d3_citations" && is_numeric($_GET['q'])) {
	// Return the d3_citations according to the q=paperid	
	// This changes the json format so that it can be read as a graph by d3
	// This means a format of {"name": "", "size": "", "children" : [] }
	header('Content-type: application/json');

	$dbconn = pg_connect("host=128.227.176.46 dbname=dblp user=john password=madden options='--client_encoding=UTF8'") or die('Could not connect: ' . pg_last_error());

	// Decode query
	$pid = pg_escape_string(stripslashes(rawurldecode($_GET['q'])));

	$query = "SELECT p.id AS pid, p.papertitle AS title, p.pubyear AS year, p.venue AS venue, p.abstract AS abstract ".
				", (SELECT topic_distribution FROM theta AS t WHERE t.pid = p.id LIMIT 1) AS topic ".
				", (SELECT count(*) FROM reference r1 where r1.pid = p.id) as childcount ".
				"FROM reference AS r INNER JOIN paper AS p ON (r.citation = p.id) ".
				"WHERE r.pid = $pid ";

	$query = $query . " ORDER BY childcount DESC ";

	// Add LIMIT and OFFSET to the query if present
	// NO LIMIT!!!
	if(isset($_GET['limit']) && is_numeric($_GET['limit']))
		$thelimit = rawurldecode($_GET['limit']); 
	else
		$thelimit = 50;

	//$query = $query . " LIMIT $thelimit ";

	if(isset($_GET['offset']) && is_numeric($_GET['offset']))
		$theoffset = rawurldecode($_GET['offset']);
	else
		$theoffset = 0;

	$query = $query . " OFFSET $theoffset";

	// END THE QUERY
	$query = $query . ";";

	// Make a query to the DB
	list($tic_usec, $tic_sec) = explode(" ", microtime());
	$result = pg_query($query) or die('Query failed: ' . pg_last_error());
	list($toc_usec, $toc_sec) = explode(" ", microtime());

	$querytime = $toc_sec + $toc_usec - ($tic_sec + $tic_usec); // Query time

	// Iterate over results
	$rows = array();
	while ($line = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$line["name"] = $line["pid"]; // This adds a duplicate field but it is ok
		$line["size"] = 10000;
		$rows[] = $line;
	}

	$graph = array();
	$graph["q"] = urldecode($query);
	$graph["querytime"] = $querytime;
	$graph["rowcount"] = pg_num_rows($result);
	$graph["childcount"] = pg_num_rows($result);

	if ($graph["rowcount"] > 0) {
		$graph["headers"] = array_keys($rows[0]);
	}
	else {
		// Some default header for no result
		$graph["headers"] = array(0 => 100, "color" => "red"); 
	}

	$graph["name"] = $pid;
	$graph["pid"] = $pid;
	$graph["size"] = 5000;
	$graph["children"] = $rows;

	// Show the json result
	print json_encode($graph); 
	//print json_encode($rows); 

	// Free the result set
	pg_free_result($result);

	// Close the connection
	pg_close($dbconn);

}
else {
    //header('Content-type: text/plain');
    //header('Content-type: text/html');
    header('Content-type: application/json');

    $rows["q"] = "null";
    $rows["querytime"] = -1;
    $rows["rowcount"] = 0;
    $rows["GET"] = $_GET;

    print json_encode($rows);
}
// phpinfo();
?>
