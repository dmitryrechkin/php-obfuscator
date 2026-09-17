<?php
require $argv[1];
use OneTeamSoftware\Test\Lister;
echo (new Lister())->run(['a', 'b']), "\n";
