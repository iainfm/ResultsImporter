<?php
// Create a new DOM Document to hold our webpage structure
$dom = new DOMDocument();

// Load the URL's HTML into the DOM
@$dom->loadHTMLFile('https://results.nsra.co.uk');

// Create a new XPath object
$xpath = new DOMXPath($dom);

// Query the DOM
$nodes = $xpath->query('//a');

// Create an array to hold our results
$results = [];

// Loop through all the a tags
foreach($nodes as $node) {
    // Get the href attribute
    # print_r($node);

    $url = $node->getAttribute('href');

    // Get the text content
    $text = $node->textContent;

    // Split the text content into league name and unique number
    list($uniqueNumber, $leagueName) = explode(' ', $text, 2);

    // Add the result to our array if the URL begins /nsra/results
    if (strpos($url, '/nsra/results') !== 0) {
        continue;
    }
    $results[] = [
        'leagueName' => $leagueName,
        'uniqueNumber' => $uniqueNumber,
        'url' => $url,
    ];
}

// Print out our results
print_r($results);
?>