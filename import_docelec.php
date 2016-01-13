<?php

$dir = new RecursiveDirectoryIterator(realpath('../docelec'));


foreach (new RecursiveIteratorIterator($dir) as $filename=>$cur) {
    if (preg_match('/licence/i', $filename)) {
        $command = 'php import_licenses.php "' . $filename . '"';
        echo "\n\n$command\n";
        $output = exec($command); 
        print_r($output);
    }
}

?>
