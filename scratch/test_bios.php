<?php
$path = 'public/bios/ps1/scph1001.bin';
echo "Checking: $path\n";
echo "Exists: " . (file_exists($path) ? 'YES' : 'NO') . "\n";

$path2 = 'public/bios/ps1/SCPH1001.BIN';
echo "Checking: $path2\n";
echo "Exists: " . (file_exists($path2) ? 'YES' : 'NO') . "\n";

echo "Real path: " . realpath($path) . "\n";
?>
