<?php

set_error_handler(function() { var_dump(debug_backtrace()); });

// Written by Chris Warburton ( ChrisWarbo@GMail.com )
// and released into the Public Domain

// A sane, functional wrapper for PHP's mishmash of incompatible primitives,
// procedures, methods, tokens, etc.

// Bootstrap in a private scope
call_user_func(function() {
  // Curries functions
  $curry_n = function($args_curry, $n_curry, $f_curry) use (&$curry_n) {
    // Even if $args_curry is sufficient, we still delay with a thunk to make
    // higher-order functions more predictable
    return function() use ($args_curry, $n_curry, $f_curry, &$curry_n) {
      // Do we have enough arguments yet?
      $args_curry = array_merge($args_curry, func_get_args());
      return (count($args_curry) < $n_curry)
        // No. Curry what we have into a new function
        ? $curry_n($args_curry, $n_curry, $f_curry)
        // Yes. Pass $n_curry of them to $f_curry, then the rest one at a time
        : array_reduce(array_slice($args_curry, $n_curry),
                       'call_user_func',
                        call_user_func_array($f_curry,
                                             array_slice($args_curry, 0,
                                                         $n_curry)));
    };
  };

  // Find a function's arity, even if it's curried or defined with defun
  $argnum = function($f) use (&$argnum) {
    // Inspect the static environment of $f. We used awkward names in $curry_n
    // to avoid false-positives here
    $rf   = new ReflectionFunction($f);
    $sv   = $rf->getStaticVariables();

    if (isset($sv['args_curry']) &&
        isset($sv['n_curry'])    &&
        $sv['n_curry']) {
      // We're a curried N-ary function
      return $sv['n_curry'] - count($sv['args_curry']);
    }

    // If we're just a wrapper around some f_curry, return its argnum instead
    if (isset($sv['f_curry'])) return $argnum($sv['f_curry']);

    // If we're a defun wrapper, return the argnum of our implementation
    if (isset($sv['f_defun'])) return $argnum($sv['f_defun']);

    // Otherwise, we're a regular function, so reflect the argnum back
    return $rf->getNumberOfParameters();
  };

  $curry = function($f) use ($curry_n, $argnum) {
    return $curry_n([], $argnum($f), $f);
  };

  // Define immutable, global, curried functions
  $defun = function($name, $body) use ($curry) {
    // Regex from http://www.php.net/manual/en/functions.user-defined.php
    $valid = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/';
    array_map(
      function($x) { if ($x[1]) throw new Exception($x[0]); },
      [["Invalid name for $name", !preg_match($valid, $name)],
       ["Cannot redeclare $name", function_exists($name)    ],
       ["Invalid body for $name", !is_callable($body, TRUE)]]);

    // Declare $name globally, storing the body in an assign-once static variable
    eval("function {$name}() {
            static \$f_defun = NULL;
            if (is_null(\$f_defun)) \$f_defun = current(func_get_args());
            else return call_user_func_array(\$f_defun, func_get_args());
          }");
    $name($curry($body));  // Populate $f with a curried $body
  };

  // Export these definitions
  $defun('defun',   $defun);
   defun('curry',   $curry);
   defun('argnum',  $argnum);
   defun('curry_n', function($n, $f) use ($curry_n) {
                      return $curry_n([], $n, $f);
                    });
});

// All of our functions from now on will be curried //
defun('uncurry', curry_n(2, 'call_user_func_array'));

// A simple test framework //

call_user_func(function() {
  $t = [];
  defun('tests',    function($_)     use (&$t) { return keys($t); });
  defun('deftest',  function($n, $f) use (&$t) {
                      $t[$n] = [uncurry($f), argnum($f)];
                    });
  defun('run_test', function($n)     use (&$t) {
                      $f = function($a) {
                             return $a[0](sample('random', $a[1]));
                           };
                      return $n? $f($t[$n])
                               : keys(filter('bNot', map($f, $t)));
                    });
});

deftest('run_test1', function($n) {
                       return eq(count(func_get_args()), 1);
                     });
deftest('run_test2', function($a, $b, $c) {
                       return (is_int($a) && is_int($b) && is_int($c))?:
                              dump(get_defined_vars());
                     });

defun('sample', function($f, $n) {
                  return $n? map($f, upto($n)) : [];
                });
deftest('sample1', function($n) { return eq(sample('id', 4), upto(4)); });
deftest('sample2', function($n) { return eq(sample('id', $n), upto($n)); });

defun('skip', function($n, $f) {
                return nary(function() use ($n, $f) {
                  return call_user_func_array($f,
                                              array_slice(func_get_args(), $n));
                }, $n + argnum($f));
              });
deftest('skip1', function($x) {
                   return skip(1, 'id', $x, true);
                 });
deftest('skip2', function() {
                   return eq(array_map(skip(1, 'id'),
                                       ['a', 'b', 'c'],
                                       ['x', 'y', 'z']),
                             ['x', 'y', 'z']);
                 });

deftest('curry',   function() {
                     return eq(id('plus', 5, 3), 8);
                   });
deftest('curry_n', function() {
                     return eq(curry_n(3, 'array_merge', [1], [2], [3]),
                               [1, 2, 3]);
                   });

// Inferring the number of arguments to a function doesn't always work, for
// example if it uses func_get_args(). The following macro lets us specfify
// that number explicitly.

defun('nary',      function($f, $n) {
                     $args = abs($n)? '$a' . implode(', $a', range(0, $n-1))
                                    : '';
                     return curry(
                       eval("return function({$args}) use (\$f) {
                                      return \$f({$args});
                                    };"));
                   });
deftest('nary',    function() {
                     $n = mt_rand(2, 50);
                     return eq(uncurry(nary(function() {
                                              return sum(func_get_args());
                                            },
                                            $n),
                                       range(1, $n)),
                               sum(range(1, $n)));
                   });

// Now we can define everything else in dependency order //

defun('key_map', function($f, $a) {
                   return array_combine(array_keys($a),
                          array_map($f, array_keys($a), $a));
                 });
deftest('key_map', function() {
                     return eq(key_map('plus', [1 => 2, 4 => 8,  16 => 32]),
                               [1 => 3, 4 => 12, 16 => 48]);
                   });

defun('defuns',   key_map('defun'));
defun('deftests', key_map('deftest'));

// Replace operators with proper functions //

defuns(['minus'    => function($x)     { return      -$x;  },
        'bNot'     => function($x)     { return      !$x;  },
        'clone_'   => function($x)     { return clone $x;  },
        'plus'     => function($x, $y) { return $x +   $y; },
        'sub'      => function($x, $y) { return $x -   $y; },
        'div'      => function($x, $y) { return $x /   $y; },
        'mult'     => function($x, $y) { return $x *   $y; },
        'mod'      => function($x, $y) { return $x %   $y; },
        'cat'      => function($x, $y) { return $x .   $y; },
        'lAnd'     => function($x, $y) { return $x and $y; },
        'lOr'      => function($x, $y) { return $x or  $y; },
        'lXor'     => function($x, $y) { return $x xor $y; },
        'bLeft'    => function($x, $y) { return $x <<  $y; },
        'bRight'   => function($x, $y) { return $x >>  $y; },
        'bAnd'     => function($x, $y) { return $x &   $y; },
        'bOr'      => function($x, $y) { return $x |   $y; },
        'bXor'     => function($x, $y) { return $x ^   $y; },
        'eq'       => function($x, $y) { return $x === $y; },
        'like'     => function($x, $y) { return $x ==  $y; },
        'nEq'      => function($x, $y) { return $x !== $y; },
        'unlike'   => function($x, $y) { return $x !=  $y; },
        'lt'       => function($x, $y) { return $x <   $y; },
        'gt'       => function($x, $y) { return $x >   $y; },
        'lte'      => function($x, $y) { return $x <=  $y; },
        'gte'      => function($x, $y) { return $x >=  $y; },
        'power'    => nary('pow', 2),
        'instance' => function($x, $y) { return $x instanceof $y; }]);

defuns(['apply'        => nary('call_user_func'),
        'array_'       => nary('func_get_args'),
        'discard_keys' => function($arr) { return array_combine($arr, $arr); },
        'map'          => nary('array_map', 2),
        'map_keys'     => function($f, $a) {
                            return array_combine(map($f, keys($a)), $a);
                          }]);

defun('call', apply(1));

deftests(['argnum1' => function() {
                         return eq(argnum(function($a, $b) {}), 2);
                       },
          'argnum2' => function() {
                          return eq(argnum(nary(function() {},
                                                $n = mt_rand(1, 10))),
                                    $n);
                        },
          'argnum3' => function() { return eq(argnum('plus'), 2); },
          'argnum4' => function() { return eq(argnum(plus(2)), 1); },
          'argnum5' => function() { return eq(argnum(flip('plus')), 2); }]);

defun('flip',      function($f) {
                     return nary(function($x, $y) use ($f) {
                       return $f($y, $x);
                     }, argnum($f));
                   });
deftests(['flip1' => function() {
                       return(eq(flip(array_(2), 1, 2), [2, 1]));
                     },
          'flip2' => function() {
                       return eq(flip('map', [-1, -2], plus(5)),
                                 [4, 3]);
                     }]);

defuns(['with' => flip(apply(2)),
        'over' => flip('map'),
        '∘'    => function($f, $g, $x) { return $f($g($x)); }]);

deftests(['compose1' => function($x, $y, $z) {
                          return eq(∘(plus($x), mult($y), $z),
                                    $x + ($y * $z));
                        },
          'compose2' => function($x, $y, $z) {
                          return eq(call(∘(mult($x), plus($y)), $z),
                                    $x * ($y + $z));
                        },
          'compose3' => function($x, $y, $z) {
                          return eq(∘(flip('map', [$x, $y]),  'plus', $z),
                                    [$x + $z, $y + $z]);
                        },
          'compose4' => function($n) {
                          return eq(∘('id', 'id', $n), $n);
                        },
          'compose5' => function($x, $y, $z) {
                          return eq(∘(plus($x), plus($y), $z),
                                    $x + $y + $z);
                        },
          'compose6' => function($x, $y, $z) {
                          return eq(∘(flip('plus', $x), plus($y), $z),
                                    $x + $y + $z);
                        },
          'compose7' => function($x, $y, $z) {
                          return eq(call(∘(flip('map', [$x, $y]),  'plus'), $z),
                                    [$x + $z, $y + $z]);
                        },
          'compose8' => function($x, $y) {
                          return eq(∘(with($x), 'plus', $y), $x + $y);
                        }]);

defun('new_', function($x, $y) {
                return with($y, [new ReflectionClass($x),
                                 'newInstanceArgs']);
              });

defun('implode_',  'implode');
defun('join_',     implode_(''));
defun('concat',    function($n) {
                     return ∘('join_', array_($n));
                   });
defun('papply',    nary(function($f) {
                     $args = array_slice(func_get_args(), 1);
                     return nary(function() use ($f, $args) {
                       return uncurry($f, merge($args, func_get_args()));
                     });
                   }));
defun('thunk',     function($x, $_) { return $x; });
defun('nil',       thunk([]));
defuns(['filter' => flip(nary('array_filter', 2)),
        'sum'    => nary('array_sum',    1)]);
deftest('sum',     function() {
                     return eq(sum($xs = range(0, mt_rand(1, 100))),
                               array_reduce($xs, 'plus', 0));
                   });

defuns(['keys'      => nary('array_keys',   1),
        'values'    => nary('array_values', 1),
        'merge'     => nary('array_merge',  2),
        'foldr'     => function($f, $zero, $arr) { // Take args in a sane order
                         return array_reduce($arr, $f, $zero);
                       },
        'key_foldr' => function($f, $zero) {
                         return ∘(foldr($f, $zero), key_map(array_(2)));
                       },
        'subscript' => function($x, $y) { return $x[$y]; },
        'take'      => function($n, $a) { return array_slice($a, 0, $n); },
        'cons'      => function($x, $y) { return merge([$x], $y); },
        'snoc'      => function($x, $y) { return merge($y, [$x]); },
        'echo_'     => function($x) { echo $x; return $x; },
        'id'        => function($x) { return $x; },
        'chain'     => function($x, $y, $z) {  return $x($z, $y($z)); },
        'zip'       => function($arr1, $arr2) {
                         return foldr(
                           function($acc, $val) use ($arr1, $arr2) {
                             $lookup = subscript($val);
                             $el1    = $lookup($arr1);
                             $el2    = $lookup($arr2);
                             return merge($acc, [[$el1, $el2]]);
                           },
                           [],
                           keys($arr1));
                       },
        'dup'       => function($x) { return [$x, $x]; },
        'swap'      => function($arr) {
                         return merge([$arr[1], $arr[0]],
                                      array_slice($arr, 2));
                       },
        'first'     => function($f, $arr) {
                         return cons($f(subscript(0, $arr)),
                                     array_slice($arr, 1));
                       },
        'second'    => function($f) {
                         return ∘('swap', ∘(first($f), 'swap'));
                       },
        'head'      => function($arr) { return $arr[0]; },
        'delay'     => function($f, $args, $_) {
                         return call_user_func_array($f, $args);
                       },
        'random'    => function($_) { return mt_rand(0, 200); }]);

deftest('random1', function() { return is_int(random(null)); });

defun('format',    function($x) {
                     return is_float($x)
                       ? number_format($x, 6)
                       : (is_array($x)
                          ? map('format', $x)
                          : $x);
                   });

defun('benchmark', function($f, $x) {
                     $time = microtime(true);
                     $f($x);
                     return microtime(true) - $time;
                   });

defun('tabulate',  function($h1, $h2, $arr) {
                     $h2 = is_array($h2)? implode(' ', $h2) : $h2;
                     echo "{$h1} {$h2}\n";
                     foreach ($arr as $n => $v) {
                       $v = is_array($v)? implode(' ', $v) : $v;
                       $s = format($v);
                       echo "{$n} {$s}\n";
                     }
                   });

defun('shell', 'shell_exec');
defun('mem',   function($cmd) {
    if (!is_string($cmd)) {
var_dump(['cmd' => $cmd]);
die();
    }
                 return intval(shell(
                   "/usr/bin/time -f '%M' $cmd 2>&1 1> /dev/null"));
               });
deftest('mem1', function($n) { return mem('true') > 0; });

defun('runphp', function($f, $arg) {
                  return "./runphp '{$f}({$arg})'";
                });

defun('parens', function($x) { return "($x)"; });

defun('upto', function($n) { return $n? range(0, $n-1) : []; });
deftest('upto1', function($n) { return eq(count(upto($n)), $n); });
defun('between', function($x, $y) {
                   return ($x - $y)? map(plus($x), upto($y - $x + 1))
                                   : [];
                 });
deftest('between1', function() {
                      return eq(between(5, 10), [5, 6, 7, 8, 9, 10]);
                    });

defun('branch', function($then, $else, $condition, $x) {
                  return $condition($x)? $then($x) : $else($x);
                });
deftests(['b_then' => function($n) {
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
                      }]);
defun('fanout', function($funcs, $x) { return map(with($x), $funcs); });

defun('until',  function($f, $x) {
                  return loop(function($y, $n) use ($f) { return $f($y); },
                              $x);
                });

deftest('until1', function($n) {
                    return until(function($args) use ($n) {
                                   list($m, $arr) = $args;
                                   return [$m === $n,
                                           [$m + 1, snoc($m, $arr)]];
                                 }, [0, []]) === [$n + 1, upto($n+1)];
                  });

defun('loop', function($f, $acc) {
                return trampoline(y(function($y, $f, $n, $x, $_) {
                                      list($stop, $x) = $f($x, $n);
                                      return [$stop, $stop? $x
                                                          : $y($f, $n+1, $x)];
                                    }, $f, 0, $acc));
              });

defun('trampoline', function($f) {
                      for ($stop = false; !$stop; list($stop, $f) = $f(null));
                      return $f;
                    });

deftest('trampoline1', function($n) {
                         return eq($n,
                                   trampoline(
                                     y(function($f, $m, $n, $_) {
                                         return ($m < $n)? [false, $f($m+1, $n)]
                                                         : [true,  $m];
                                       }, 0, $n)));
                       });

deftest('loop1', function($n) {
                   $lhs = loop(function($x, $m) use ($n) {
                                 return [$m >= $n, snoc($m, $x)];
                               }, []);
                   $rhs = upto($n + 1);
                   return eq($lhs, $rhs)?:
                          dump(get_defined_vars());
                 });

defun('y', function($f) {
             $cf  = curry($f);
             return curry(function($x) use ($cf) {
                            return $cf(y($cf), $x);
                          });
           });
deftest('y1', function($n) {
                return y(function($f, $m) use ($n) {
                           return ($m === $n)? $m
                                             : $f($m + 1);
                         });
              });

defun('stream_take', function($n, $s) {
                       return trampoline(y(function($f, $x, $n, $s, $_) {
                                             if (!$n) return [true, $x];
                                             list($h, $t) = $s(null);
                                             return [false, $f(snoc($h, $x),
                                                               $n-1, $t)];
                                           }, [], $n, $s));
                     });
deftest('stream_take1', function($n) {
                          $lhs = upto($n);
                          $rhs = stream_take($n, y(function($f, $n, $_) {
                                                     return [$n, $f($n+1)];
                                                   }, 0));
                          return eq($lhs, $rhs)?:
                                 dump(get_defined_vars());
                        });

defun('stream_drop', function($n, $s) {
                       return trampoline(y(function($f, $n, $s, $_) {
                                             if (!$n) return [true, $s];
                                             list($h, $t) = $s(null);
                                             return [false, $f($n-1, $t)];
                                           }));
                     });

defun('dump', nary('var_dump', 1));
