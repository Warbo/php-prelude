<?php

require_once(__DIR__ . '/vendor/autoload.php');


deftests([
  // Test the test framework
  'run_test1' => function($n) {
                   return eq(count(func_get_args()), 1);
                 },
  'run_test2' => function($a, $b, $c) {
                   return (is_int($a) && is_int($b) && is_int($c))?:
                          dump(get_defined_vars());
                 },

  'sample1' => function($n) { return eq(sample('id', 4),  upto(4));  },
  'sample2' => function($n) { return eq(sample('id', $n), upto($n)); },
/*
  //'skip1' => function($x) { return skip(1, 'id', $x, true); },
  'skip2' => function() {
               return eq(array_map(skip(1, 'id'),
                                   ['a', 'b', 'c'],
                                   ['x', 'y', 'z']),
                         ['x', 'y', 'z']);
             },

  // Check that our currying infrastructure works as expected
  'curry'   => function() { return eq(id('plus', 5, 3), 8); },
  'curry_n' => function() {
                 return eq(curry_n(3, 'array_merge', [1], [2], [3]),
                           [1, 2, 3]);
               },
  'nary'    => function() {
                 $n = mt_rand(2, 50);
                 return eq(uncurry(nary(function() {
                                          return sum(func_get_args());
                                        },
                                        $n),
                                   range(1, $n)),
                           sum(range(1, $n)));
               },
  'arity1' => function() {
                 return eq(arity(function($a, $b) {}), 2);
               },
  'arity2' => function($n) { return eq(arity(nary(function() {}, $n)), $n); },
  'arity3' => function()   { return eq(arity(     'plus'),              2); },
  'arity4' => function()   { return eq(arity(      plus(2)),            1); },
  'arity5' => function()   { return eq(arity(flip('plus')),             2); },

  'key_map' => function() {
                 return eq(key_map('plus', [1 => 2, 4 => 8,  16 => 32]),
                           [1 => 3, 4 => 12, 16 => 48]);
               },

  // Rearranging function arguments
  'flip1' => function() { return(eq(flip(array_(2), 1, 2),          [2, 1])); },
  'flip2' => function() { return eq(flip('map', [-1, -2], plus(5)), [4, 3]);  },

  // Composition should interact properly with currying
  'compose1' => function($x, $y, $z) {
                  return eq(call(compose(plus($x), mult($y)), $z)),
                            $x + ($y * $z));
                },
  'compose2' => function($x, $y, $z) {
                  return eq(call(compose(mult($x), plus($y)), $z),
                            $x * ($y + $z));
                },
  'compose3' => function($x, $y, $z) {
                  return eq(call(compose(flip('map', [$x, $y]), 'plus'), $z),
                            [$x + $z, $y + $z]);
                },
  'compose4' => function($n) {
                  return eq(call(compose('id', 'id'), $n), $n);
                },
  'compose5' => function($x, $y, $z) {
                  return eq(call(compose(plus($x), plus($y)), $z),
                            $x + $y + $z);
                },
  'compose6' => function($x, $y, $z) {
                  return eq(call(compose(flip('plus', $x), plus($y)), $z),
                            $x + $y + $z);
                },
  'compose7' => function($x, $y, $z) {
                  return eq(call(compose(flip('map', [$x, $y]), 'plus'), $z),
                            [$x + $z, $y + $z]);
                },
  'compose8' => function($x, $y) {
                  return eq(call(compose(with($x), 'plus'), $y), $x + $y);
                },

  'sum' => function() {
      return eq(sum($xs = range(0, mt_rand(1, 100))),
        array_reduce($xs, 'plus', 0));
  },
  'random1' => function() { return is_int(random(null)); },
  'mem1' => function($n) { return mem('true') > 0; },
  'upto1' => function($n) { return eq(count(upto($n)), $n); },
  'between1' => function() {
      return eq(between(5, 10), [5, 6, 7, 8, 9, 10]);
  },
  'b_then' => function($n) {
      return eq(branch(thunk($n),
      null,
      thunk(true),
        null),
        $n);
    },
    'b_else' => function($n) {
        return eq(branch(null,
        thunk($n),
        thunk(false),
        null),
        $n);
    },

  // General recursion
  'until1'      => function($n) {
                     return eq(until(function($args) use ($n) {
                                       list($m, $arr) = $args;
                                       return [$m === $n,
                                               [$m + 1, snoc($m, $arr)]];
                                     },
                                     [0, []]),
                               [$n + 1, upto($n+1)]);
                   },
  'trampoline1' => function($n) {
                     return eq($n,
                               trampoline(
                                 y(function($f, $m, $n, $_) {
                                     return ($m < $n)? [false, $f($m+1, $n)]
                                                     : [true,  $m];
                                   }, 0, $n)));
                   },
  'loop1'       => function($n) {
                     $lhs = loop(function($x, $m) use ($n) {
                                   return [$m >= $n, snoc($m, $x)];
                                 }, []);
                     $rhs = upto($n + 1);
                     return eq($lhs, $rhs)?:
                            dump(get_defined_vars());
                   },

  // Taking fixed points
  'y1' => function($n) {
            return eq(y(function($f, $m) use ($n) {
                          return eq($m, $n)? $m
                                           : $f($m + 1);
                        }),
                      $n);
          },

  'stream_take1' => function($n) {
                      $lhs = upto($n);
                      $rhs = stream_take($n, y(function($f, $n, $_) {
                                                 return [$n, $f($n+1)];
                                               }, 0));
                      return eq($lhs, $rhs)?:
                             dump(get_defined_vars());
                    },
*/
]);

$failures? var_dump(array('Test failures' => $failures))
         : (print "All tests passed\n");
call_user_func(function() {
                 if ($results = run_test(null)) var_dump($results);
               });
