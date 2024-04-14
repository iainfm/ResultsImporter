<?php
// Create a new DOM Document to hold our webpage structure
$dom = new DOMDocument();

// Load the HTML from the file into the DOM
@$dom->loadHTMLFile('https://results.nsra.co.uk/nsra/results/296');

// Create a new XPath object
$xpath = new DOMXPath($dom);

// Query the DOM for div elements with the class 'results-row' within a div with the class 'results wrapper has-fixtures'
$nodes = $xpath->query('//div[contains(@class, "results wrapper has-fixtures")]//div[@class="results-row"]');

// Create an array to hold our results
$results = [];

// Loop through all the div tags
foreach($nodes as $node) {
    // Get the clubname
    $clubnameNode = $xpath->query('.//div[@class="item clubname"]', $node)->item(0);
    $clubname = $clubnameNode ? $clubnameNode->textContent : null;

    // Get the team_id from the data-tooltip attribute
    $teamNode = $xpath->query('.//div[contains(@class, "team-members-tooltip")]', $node)->item(0);
    $tooltip = $teamNode ? $teamNode->getAttribute('data-tooltip') : null;
    preg_match('/team_id=(\d+)/', $tooltip, $matches);
    $team_id = $matches[1] ?? null;

    // Only add the result to our array if both team_id and clubname are not null or blank
    if ($team_id && trim($team_id) !== '' && $clubname && trim($clubname) !== '') {
        $results[] = [
            'team_id' => $team_id,
            'clubname' => $clubname,
        ];
    }

}

// Print out our results
print_r($results);
?>