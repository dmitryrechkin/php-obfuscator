<?php
require $argv[1];
use OneTeamSoftware\Test\Packer;
$p = new Packer();
$p->apply(['boxes' => ['A', 'B']]);
echo implode(',', $p->result()), "\n";
