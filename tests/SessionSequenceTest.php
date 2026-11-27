<?php

declare(strict_types=1);

namespace kim\present\utils\session\tests;

use kim\present\utils\session\sequence\SessionSequence;
use kim\present\utils\session\SessionTerminateReasons;
use kim\present\utils\session\tests\Fixture\LifecycleStep;
use kim\present\utils\session\tests\Fixture\NotASequenceSession;
use kim\present\utils\session\tests\Fixture\StepOne;
use kim\present\utils\session\tests\Fixture\StepThree;
use kim\present\utils\session\tests\Fixture\StepTwo;
use PHPUnit\Framework\Attributes\CoversClass;
use pocketmine\player\Player;

#[CoversClass(SessionSequence::class)]
final class SessionSequenceTest extends SessionTestCase{

    private function sequence() : SessionSequence{
        return new SessionSequence($this->makePlugin()->plugin, StepOne::class, StepTwo::class, StepThree::class);
    }

    public function testProgressionAndOnComplete() : void{
        $seq = $this->sequence();
        $completed = [];
        self::assertSame($seq, $seq->onComplete(function(Player $p) use (&$completed) : void{
            $completed[] = $p;
        }));
        $player = $this->makePlayer();

        self::assertNull($seq->getCurrentStep($player));
        $seq->start($player);
        self::assertSame(0, $seq->getCurrentStep($player));

        foreach([1, 2] as $expected){
            $this->currentSession($seq, $player)->advance();
            self::assertSame($expected, $seq->getCurrentStep($player));
        }
        self::assertSame([], $completed);

        $this->currentSession($seq, $player)->advance();
        self::assertNull($seq->getCurrentStep($player));
        self::assertSame([$player], $completed);
        self::assertSame([
            StepOne::class . ":start",
            StepOne::class . ":onTerminate:completed",
            StepTwo::class . ":start",
            StepTwo::class . ":onTerminate:completed",
            StepThree::class . ":start",
            StepThree::class . ":onTerminate:completed",
        ], StepOne::$log);
    }

    private function currentSession(SessionSequence $seq, Player $player) : \kim\present\utils\session\tests\Fixture\StepSession{
        $index = $seq->getCurrentStep($player);
        self::assertNotNull($index);
        $reflection = new \ReflectionProperty($seq, "managers");
        $session = $reflection->getValue($seq)[$index]->getSession($player);
        self::assertInstanceOf(\kim\present\utils\session\tests\Fixture\StepSession::class, $session);
        return $session;
    }

    public function testOnCompleteMayBeSetAfterStart() : void{
        $seq = $this->sequence();
        $player = $this->makePlayer();
        $seq->start($player);
        $done = false;
        $seq->onComplete(function() use (&$done) : void{
            $done = true;
        });
        for($i = 0; $i < 3; ++$i){
            $this->currentSession($seq, $player)->advance();
        }
        self::assertTrue($done);
    }

    public function testStartFromIndexAndClass() : void{
        $seq = $this->sequence();
        $a = $this->makePlayer();
        $b = $this->makePlayer();

        $seq->startFrom($a, 2);
        $seq->startFrom($b, StepTwo::class);

        self::assertSame(2, $seq->getCurrentStep($a));
        self::assertSame(1, $seq->getCurrentStep($b));
    }

    public function testStartFromInvalidStep() : void{
        $seq = $this->sequence();
        $player = $this->makePlayer();

        foreach([3, -1, "Nope\\Missing"] as $step){
            try{
                $seq->startFrom($player, $step);
                self::fail("Expected exception for " . var_export($step, true));
            }catch(\InvalidArgumentException){
            }
        }
        self::assertNull($seq->getCurrentStep($player));
    }

    public function testStartingAgainRestartsInsteadOfDuplicating() : void{
        $seq = $this->sequence();
        $player = $this->makePlayer();

        $seq->startFrom($player, 1);
        $seq->start($player);

        self::assertSame(0, $seq->getCurrentStep($player));
        self::assertSame([
            StepTwo::class . ":start",
            StepTwo::class . ":onTerminate:" . SessionTerminateReasons::RESTART,
            StepOne::class . ":start",
        ], StepOne::$log);

        $seq->startFrom($player, StepThree::class);
        self::assertSame(2, $seq->getCurrentStep($player));
        $active = 0;
        $managers = (new \ReflectionProperty($seq, "managers"))->getValue($seq);
        foreach($managers as $manager){
            $active += count($manager->getAllSessions());
        }
        self::assertSame(1, $active);

        // Starting the step the player is already in keeps the running session.
        $before = $this->currentSession($seq, $player);
        $seq->startFrom($player, 2);
        self::assertSame($before, $this->currentSession($seq, $player));
    }

    public function testStaleSessionCannotAdvanceTheSequence() : void{
        $seq = $this->sequence();
        $player = $this->makePlayer();
        $seq->start($player);
        $stale = $this->currentSession($seq, $player);

        $seq->startFrom($player, 2);
        $stale->advance(); // inactive: ignored

        self::assertSame(2, $seq->getCurrentStep($player));
    }

    public function testTerminateAllAndClose() : void{
        $seq = $this->sequence();
        $a = $this->makePlayer();
        $b = $this->makePlayer();
        $seq->start($a);
        $seq->startFrom($b, 1);

        $seq->terminateAll();
        self::assertNull($seq->getCurrentStep($a));
        self::assertNull($seq->getCurrentStep($b));

        $seq->start($a);
        $seq->close();
        self::assertNull($seq->getCurrentStep($a));

        $this->expectException(\LogicException::class);
        $seq->start($a);
    }

    public function testGetSessionClassAt() : void{
        $seq = $this->sequence();
        self::assertSame(StepOne::class, $seq->getSessionClassAt(0));
        self::assertSame(StepThree::class, $seq->getSessionClassAt(2));
        self::assertNull($seq->getSessionClassAt(3));
    }

    public function testConstructorValidation() : void{
        $plugin = $this->makePlugin()->plugin;

        $cases = [
            "empty" => [],
            "not a sequence session" => [StepOne::class, NotASequenceSession::class],
            "lifecycle" => [LifecycleStep::class],
            "unknown class" => ["Nope\\Missing"],
        ];
        $thrown = 0;
        foreach($cases as $name => $classes){
            try{
                new SessionSequence($plugin, ...$classes);
                self::fail("Expected exception: $name");
            }catch(\InvalidArgumentException){
                ++$thrown;
            }
        }
        self::assertSame(count($cases), $thrown);
    }
}
