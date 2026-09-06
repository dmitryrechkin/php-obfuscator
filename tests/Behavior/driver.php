<?php
require $argv[1];
use OneTeamSoftware\Test\Complex;

$c = new Complex('X1');
$out = [];
$out[] = $c->greet('world');           // public -> private chain, trait, static const
$out[] = $c->greet('again');
$hooks = [];
$c->register($hooks);
$out[] = call_user_func($hooks['init']);        // [$this,'onInit'] private-as-callback
$out[] = $c->call($hooks['dynamic']);           // method_exists + $this->{$name}()
$out[] = $c->call('missing');
$adder = $c->makeAdder(10);
$out[] = (string) $adder(5);                     // closure capturing $this + local
$out[] = implode(',', $c->history());
$out[] = Complex::PREFIX;                         // public class const
echo implode("\n", $out), "\n";
