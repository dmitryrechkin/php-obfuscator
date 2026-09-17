<?php
require $argv[1];
use OneTeamSoftware\Test\Registry;
$before = Registry::value();
Registry::configure('set');
$r = new Registry();
$r->bump();
echo $before, ':', Registry::value(), ':', $r->bump(), "\n";
