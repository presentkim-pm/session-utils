<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests\Fixture;

use kim\present\utils\session\sequence\SequenceSession;

/** Base for the sequence step fixtures; exposes next(). */
abstract class StepSession extends SequenceSession{

    /** @var list<string> */
    public static array $log = [];

    protected function onStart() : void{
        self::$log[] = static::class . ":start";
    }

    protected function onTerminate(string $reason) : void{
        self::$log[] = static::class . ":onTerminate:$reason";
    }

    public function advance() : void{
        $this->next();
    }
}
