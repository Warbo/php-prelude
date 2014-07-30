<?php

/*
 * A sane, functional wrapper for PHP's mishmash of incompatible primitives,
 * procedures, methods, tokens, etc.
 *
 * Written by Chris Warburton ( ChrisWarbo@GMail.com ) and released into the
 * Public Domain
 */

// Errors are unacceptable
set_error_handler(function() { var_dump(debug_backtrace()); die(); });

// Bootstrap in a private scope
call_user_func(
  function() {
    // All functions should be curried, so we implement that first.
    $c_sentinel = mt_rand();
    $d_sentinel = mt_rand();

    // $curry_n accumulates N arguments for a function
    $curry_n = function($args, $n, $f) use (&$curry_n, $c_sentinel) {
                 // Always return a function, to make function application nicer
                 return function() use ($args, $n, $f, &$curry_n, $c_sentinel) {
                          // Gather our arguments and split off the first $n
                          $args = array_merge($args, func_get_args());
                          $init = array_slice($args, 0, $n);
                          // Do we have enough to call $f?
                          return (count($init) === $n)
                            // Yes. Send $init to $f, apply the return value to
                            // any extras (one-at-a-time, to allow for "manual"
                            // currying)
                            ? array_reduce(array_slice($args, $n),
                                          'call_user_func',
                                           call_user_func_array($f, $init))
                            // No. Curry what we have and wait for more
                            : $curry_n($args, $n, $f);
                        };
               };

  // Find a function's arity, even if it's curried or defined with defun
  $arity = function($f) use (&$arity, $c_sentinel, $d_sentinel) {
             // Inspect the static environment of $f
             $rf = new ReflectionFunction($f);
             $sv = $rf->getStaticVariables();

             if (isset($sv['c_sentinel']) &&
                !isset($sv['d_sentinel']) &&
                 $sv['c_sentinel'] === $c_sentinel) {
               // We're curried. Find the arity of the function we're wrapping

               // If we accept arguments, our arity is how many we still need
               if ($sv['n'] > 0) return $sv['n'] - count($sv['args']);

               // We're a wrapper around some f, return its arity instead
               if (isset($sv['f'])) return $arity($sv['f']);
             }

             // If we're a defun wrapper, return the arity of our
             // implementation
             if (isset($sv['d_sent']) &&
                 $sv['d_sent'] === $d_sentinel) return $arity($sv['f']);

             // Otherwise, we're a regular function, so reflect the arity back
             return $rf->getNumberOfParameters();
           };

  $curry = function($f) use ($curry_n, $arity) {
             return $curry_n([], $arity($f), $f);
           };

  // Define immutable, global, curried functions
  $defun = function($name, $body) use ($curry, $d_sentinel, $c_sentinel) {
             // Source: http://www.php.net/manual/en/functions.user-defined.php
             $valid = '/[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*/';
             array_map(
               function($x) { if ($x[1]) throw new Exception($x[0]); },
               [["Invalid name for $name", !preg_match($valid, $name)],
                ["Cannot redeclare $name", function_exists($name)    ],
                ["Invalid body for $name", !is_callable($body, TRUE)]]);

             // Declare $name globally, with a static variable for $body
             eval("function {$name}() {
                     static \$d_sent = NULL;
                     static \$f = NULL;
                     if (is_null(\$f)) {
                       list(\$f, \$d_sent) = func_get_args();
                       return;
                     }
                     return call_user_func_array(\$f, func_get_args());
                   }");
             $name($curry($body), $d_sentinel);  // Populate the statics
           };

  // Export these definitions
  $defun('defun',   $defun);
   defun('curry',   $curry);
   defun('arity',  $arity);
   defun('curry_n', function($n, $f) use ($curry_n) {
                      return $curry_n([], $n, $f);
                    });
});

defun('key_map', function($f, $a) {
                   return array_combine(array_keys($a),
                                        array_map($f, array_keys($a), $a));
                 });

defun('defuns', key_map('defun'));

defuns([
  'uncurry' => curry_n(2, 'call_user_func_array'),
  'sample'  => function($f, $n) { return $n? map($f, upto($n)) : []; },
  'skip'    => function($n, $f) {
                 return nary(function() use ($n, $f) {
                               return uncurry($f,
                                              array_slice(func_get_args(), $n));
                             },
                             $n + arity($f));
               },

  // Inferring the number of arguments to a function doesn't always work, for
  // example if it uses func_get_args(). The following macro lets us specfify
  // that number explicitly.
  'nary'    => function($f, $n) {
                 $args = abs($n)? '$a' . implode(', $a', range(0, $n-1))
                                : '';
                 return curry(
                   eval("return function({$args}) use (\$f) {
                                  return \$f({$args});
                                };"));
               }]);

  // Replace operators with proper functions //
defuns([
  'minus'    => function($x)     { return      -$x;  },
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
  'instance' => function($x, $y) { return $x instanceof $y; },
  'power'    => nary('pow', 2),
  'apply'        => nary('call_user_func'),
  'array_'       => nary('func_get_args'),
  'discard_keys' => function($arr) { return array_combine($arr, $arr); },
  'map'          => nary('array_map', 2),
  'map_keys'     => function($f, $a) {
                      return array_combine(map($f, keys($a)), $a);
                    },
  'flip' => function($f) {
              return nary(function($x, $y) use ($f) {
                            return $f($y, $x);
                          }, arity($f));
            }]);

defuns(['with' => flip(apply(2)),
        'over' => flip('map'),
        '∘'    => function($f, $g, $x) { return $f($g($x)); }]);


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
                 return intval(shell(
                   "/usr/bin/time -f '%M' $cmd 2>&1 1> /dev/null"));
               });

defun('runphp', function($f, $arg) {
                  return "./runphp '{$f}({$arg})'";
                });

defun('parens', function($x) { return "($x)"; });

defun('upto', function($n) { return $n? range(0, $n-1) : []; });

defun('between', function($x, $y) {
                   return ($x - $y)? map(plus($x), upto($y - $x + 1))
                                   : [];
                 });

defun('branch', function($then, $else, $condition, $x) {
                  return $condition($x)? $then($x) : $else($x);
                });

defun('fanout', function($funcs, $x) { return map(with($x), $funcs); });

defun('until',  function($f, $x) {
                  return loop(function($y, $n) use ($f) { return $f($y); },
                              $x);
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

defun('y', function($f) {
             $cf  = curry($f);
             return curry(function($x) use ($cf) {
                            return $cf(y($cf), $x);
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

defun('stream_drop', function($n, $s) {
                       return trampoline(y(function($f, $n, $s, $_) {
                                             if (!$n) return [true, $s];
                                             list($h, $t) = $s(null);
                                             return [false, $f($n-1, $t)];
                                           }));
                     });

defun('dump', nary('var_dump', 1));
defun('call', apply(1));