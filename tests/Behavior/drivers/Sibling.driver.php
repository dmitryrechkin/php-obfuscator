<?php
require $argv[1];
use OneTeamSoftware\Test\Definition;
$d = (new Definition('A'))->withSuffix('B');
echo $d->run(), "\n"; // expect run:AB
