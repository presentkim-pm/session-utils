<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests\Fixture;

use kim\present\utils\session\Session;

final class FailingStartSession extends Session{

    /** @var list<string> */
    public static array $log = [];

    protected function onStart() : void{
        self::$log[] = "start";
        throw new \RuntimeException("start boom");
    }

    protected function onTerminate(string $reason) : void{
        self::$log[] = "onTerminate:$reason";
    }
}
