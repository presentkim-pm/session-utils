<?php

/**
 * Test-only stand-in for the pmmp/ext-pmmpthread base class that AsyncTask extends.
 *
 * Mocking pocketmine\Server makes PHPUnit load the classes in its method signatures (AsyncTask among them),
 * but nothing ever runs a task here. It is NEVER loaded when the real extension is available.
 */

declare(strict_types=1);

namespace pmmp\thread;

abstract class Runnable{
}
