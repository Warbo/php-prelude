<?php

require_once(__DIR__ . '/vendor/autoload.php');

deftests([
  // Test the test framework
  'run_test1' => function($n) {
                   return count(func_get_args()) !== 1;
                 },
  'run_test2' => function($a, $b, $c) {
                   return (is_int($a) && is_int($b) && is_int($c))
                     ? 0
                     : dump(get_defined_vars());
                 },
  'sample1' => function($n) { return sample('id', 4)  !== upto(4);  },
  'sample2' => function($n) {
                 return sample('id', $n % 100) !== upto($n % 100);
               },

  'skip1' => function($x) { return skip(1, 'id', $x, false); },
  'skip2' => function() {
               return array_map(skip(1, 'id'),
                                ['a', 'b', 'c'],
                                ['x', 'y', 'z'])
                      !== ['x', 'y', 'z'];
             },

  // Check that our currying infrastructure works as expected
  'curry'   => function() { return id('plus', 5, 3) !== 8; },
  'curry_n' => function() {
                 return curry_([], 3, 'array_merge', [1], [2], [3])
                        !== [1, 2, 3];
               },
  'nary'    => function() {
                 $n = mt_rand(2, 50);
                 return uncurry(nary(function() {
                                       return sum(func_get_args());
                                     },
                                     $n),
                                range(1, $n))
                        !== sum(range(1, $n));
               },

  'arity1' => function() {
                 return arity(function($a, $b) {}) !== 2;
               },
  'arity2' => function($n) {
                return arity(nary(function() {}, $n % 100)) !== $n % 100;
              },
  'arity3' => function()   { return arity(     'plus')   !== 2;  },
  'arity4' => function()   { return arity(      plus(2)) !== 1;  },
  'arity5' => function()   { return arity(flip('plus'))  !== 2;  },

  'key_map' => function() {
                 return key_map('plus', [1 => 2, 4 => 8,  16 => 32])
                        !== [1 => 3, 4 => 12, 16 => 48];
               },

  // Rearranging function arguments
  'flip1' => function() { return flip(array_(2), 1, 2)          !== [2, 1]; },
  'flip2' => function() { return flip('map', [-1, -2], plus(5)) !== [4, 3]; },

  // Composition should interact properly with currying
  'compose1' => function($x, $y, $z) {
                  return call(compose(plus($x), mult($y)), $z)
                         !== $x + ($y * $z);
                },
  'compose2' => function($x, $y, $z) {
                  return call(compose(mult($x), plus($y)), $z)
                         !== $x * ($y + $z);
                },
  'compose3' => function($x, $y, $z) {
                  $f = flip('map', [$x, $y]);
                  $c = compose($f, 'plus');
                  return $c($z) !== [$x + $z, $y + $z];
                },
  'compose4' => function($n) {
                  return call(compose('id', 'id'), $n)
                         !== $n;
                },
  'compose5' => function($x, $y, $z) {
                  return call(compose(plus($x), plus($y)), $z)
                         !== $x + $y + $z;
                },
  'compose6' => function($x, $y, $z) {
                  return call(compose(flip('plus', $x), plus($y)), $z)
                         !== $x + $y + $z;
                },
  'compose7' => function($x, $y, $z) {
                  $f = flip('map', [$x, $y]);
                  $c = compose($f, 'plus');
                  return $c($z) !== [$x + $z, $y + $z];
                },
  'compose8' => function($x, $y) {
                  $c = compose(with($x), 'plus');
                  return $c($y) !== $x + $y;
                },

  'sum' => function() {
             return sum($xs = range(0, mt_rand(1, 100)))
                    !== array_reduce($xs, 'plus', 0);
           },
  'random1' => function() { return !is_int(random(null)); },
  'mem1' => function() { return mem('true') <= 0; },
  'upto1' => function($n) { return count(upto($n % 100)) !== $n % 100; },
  'between1' => function() {
                  return between(5, 10) !== [5, 6, 7, 8, 9, 10];
                },

  'b_then' => function($n) {
                return branch(thunk($n),
                              null,
                              thunk(true),
                              null)
                  !== $n;
              },
  'b_else' => function($n) {
                return branch(null,
                              thunk($n),
                              thunk(false),
                              null)
                  !== $n;
              },

  // General recursion
  'until1'      => function($n) {
                     $x = $n % 100;
                     return until(function($args) use ($x) {
                                    list($m, $arr) = $args;
                                    return [$m === $x,
                                            [$m + 1, snoc($m, $arr)]];
                                  },
                                  [0, []])
                       !== [$x+1, upto($x+1)];
                   },
  'trampoline1' => function($n) {
                     $x = $n % 100;
                     return trampoline(
                               y(function($f, $m, $n, $_) {
                                   return ($m < $n)? [false, $f($m+1, $n)]
                                                   : [true,  $m];
                                 }, 0, $x))
                       !== $x;
                   },
  'loop1'       => function($x) {
                     $n = $x % 100;
                     $lhs = loop(function($x, $m) use ($n) {
                                   return [$m >= $n, snoc($m, $x)];
                                 }, []);
                     $rhs = upto($n + 1);
                     return ($lhs === $rhs)? 0 : dump(get_defined_vars());
                   },

  // Taking fixed points
  'y1' => function($x) {
            $n = $x % 100;
            return y(function($f, $m) use ($n) {
                          return ($m === $n)? $m
                                            : $f($m + 1);
                     }, 0)
                   !== $n;
          },

  'stream_take1' => function($x) {
                      $n = $x % 100;
                      $lhs = upto($n);
                      $rhs = stream_take($n, y(function($f, $n, $_) {
                                                 return [$n, $f($n+1)];
                                               }, 0));
                      return ($lhs === $rhs)? 0 : dump(get_defined_vars());
                    },
]);

$failures = runtests(null);

$failures? var_dump(array('Test failures' => $failures))
         : (print "All tests passed\n");
