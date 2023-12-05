<?php

/*
 * A sane, functional wrapper for PHP's mishmash of incompatible primitives,
 * procedures, methods, tokens, etc.
 *
 * Written by Chris Warburton ( ChrisWarbo@GMail.com ) and released into the
 * Public Domain
 */

// Errors are unacceptable
set_error_handler(function() {
  var_dump(array('args'  => func_get_args(),
                 'trace' => debug_backtrace()));
  die();
});

defuns([
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
  'array_'       => nary(function() { return func_get_args(); }),
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
        'over' => flip('map')]);

function compose($a, $b) {
  $funcs = array_reverse(func_get_args());
  $f     = op(array_shift($funcs));
  return function($x) use ($funcs, $f) {
           static $curried = true;
           return array_reduce($funcs,
                               function($x, $f) {
                                 return call_user_func(op($f), $x);
                               },
                               call_user_func_array($f, func_get_args()));
         };
}

defun('implode_',  'implode');
defun('join_',     implode_(''));
defun('concat',    function($n) {
                     return compose('join_', array_($n));
                   });
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
                         return compose(foldr($f, $zero), key_map(array_(2)));
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
                         return compose('swap', first($f), 'swap');
                       },
        'head'      => function($arr) { return $arr[0]; },
        'delay'     => function($f, $args, $_) {
                         return call_user_func_array($f, $args);
                       }]);

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
                     return key_foldr(function($str, $row) {
                                        list($n, $v) = $row;
                                        $v = is_array($v)? implode(' ', $v) : $v;
                                        $s = format($v);
                                        return "{$str}\n{$n} {$s}";
                                      },
                                      "$h1 " . (is_array($h2)? implode(' ', $h2)
                                                             : $h2),
                                      $arr);
                   });

defun('shell', 'shell_exec');
defun('mem',   function($cmd) {
                 return intval(shell(
                   "env time -f '%M' $cmd 2>&1 1> /dev/null"));
               });

defun('runphp', function($f, $arg) {
                  return "./runphp '{$f}({$arg})'";
                });

function papply() {
  $args    = func_get_args();
  $f       = op(array_shift($args));
  return function() use ($args, $f) {
           static $curried = true;
           return call_user_func_array('call_user_func',
                                       array_merge($args, func_get_args()));
         };
};


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