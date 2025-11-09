<?php

namespace Bright\VCard;

use Closure;
use RuntimeException;

class Vutils
{

    /**
     * Throw the given exception if the given condition is true.
     * 
     * @see https://github.com/laravel/framework/blob/12.x/src/Illuminate/Support/helpers.php#L412
     *
     * @template TValue
     * @template TParams of mixed
     * @template TException of \Throwable
     * @template TExceptionValue of TException|class-string<TException>|string
     *
     * @param  TValue  $condition
     * @param  Closure(TParams): TExceptionValue|TExceptionValue  $exception
     * @param  TParams  ...$parameters
     * @return ($condition is true ? never : ($condition is non-empty-mixed ? never : TValue))
     *
     * @throws TException
     */
    public static function throw_if($condition, $exception = 'RuntimeException', ...$parameters)
    {
        if ($condition) {

            if ($exception instanceof Closure) {
                $exception = $exception(...$parameters);
            }

            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }


    /**
     * Throw the given exception unless the given condition is true.
     * 
     * @see https://github.com/laravel/framework/blob/12.x/src/Illuminate/Support/helpers.php#L430
     *
     * @template TValue
     * @template TParams of mixed
     * @template TException of \Throwable
     * @template TExceptionValue of TException|class-string<TException>|string
     *
     * @param  TValue  $condition
     * @param  Closure(TParams): TExceptionValue|TExceptionValue  $exception
     * @param  TParams  ...$parameters
     * @return ($condition is false ? never : ($condition is non-empty-mixed ? TValue : never))
     *
     * @throws TException
     */
    public static function throw_unless($condition, $exception = 'RuntimeException', ...$parameters)
    {
        self::throw_if(! $condition, $exception, ...$parameters);

        return $condition;
    }
}
