<?php

$value = $argv[1];

require "WikiPediaAPI.php";

use API\WikiPediaAPI;

$value = "coronavirus";

$wiki = new WikiPediaAPI();


// Test 1
// Test for the first paragraph.
$Data = $wiki->GetWikipediaFirstParagraph($value);
echo"\n\n";
echo "Search: '$value'\n----------\n";
var_export($Data);

echo "\n\n";


// Test 2
// Test for multiple paragraphs. Make param 2 list empty for all paragraphs
$Data = $wiki->GetWikipediaParagraphs($value, [0, 1, 2, 3]);

echo"\n\n";
echo "Search: '$value'\n----------\n";
var_export($Data);

echo "\n\n";