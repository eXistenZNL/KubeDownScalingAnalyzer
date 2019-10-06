<?php

require_once 'vendor/autoload.php';

$output = new Symfony\Component\Console\Output\ConsoleOutput();
$cluster = new Cluster($output);

$cluster->analyze();
