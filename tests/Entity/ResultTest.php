<?php

/**
 * PHP version 7.4
 *
 * @category TestEntities
 * @package  App\Tests\Entity
 * @license  https://opensource.org/licenses/MIT MIT License
 * @link     http://www.etsisi.upm.es/ E.T.S. de Ingeniería de Sistemas Informáticos
 */

namespace App\Tests\Entity;

use App\Entity\Result;
use App\Entity\User;
use Exception;
use Faker\Factory as FakerFactoryAlias;
use Faker\Generator as FakerGeneratorAlias;
use PHPUnit\Framework\TestCase;

/**
 * Class ResultTest
 *
 * @package App\Tests\Entity
 *
 * @group   entities
 * @coversDefaultClass \App\Entity\Result
 */
class ResultTest extends TestCase
{

    protected static Result $resultado;

    private static FakerGeneratorAlias $faker;

    /**
     * Sets up the fixture.
     * This method is called before a test is executed.
     */
    public static function setUpBeforeClass(): void
    {
        self::$resultado = new Result();
        self::$faker = FakerFactoryAlias::create('es_ES');
    }

    /**
     * Implement testConstructor().
     *
     * @return void
     */
    public function testConstructor(): void
    {
        $resultado = new Result();
        self::assertEmpty($resultado->getResult());
        self::assertEmpty($resultado->getUser());
        self::assertEquals(0, $resultado->getId());
    }

    /**
     * Implement testGetId().
     *
     * @return void
     */
    public function testGetId(): void
    {
        self::assertEquals(0, self::$resultado->getId());
    }

    /**
     * Implements testGetSetResult().
     *
     * @throws Exception
     * @return void
     */
    public function testGetSetResult(): void
    {
        $resultId = self::$faker->numberBetween();
        self::$resultado->setResult($resultId);
        static::assertEquals(
            $resultId,
            self::$resultado->getResult()
        );
    }

    /**
     * Implements testGetSetUser().
     *
     * @return void
     * @throws Exception
     */
    public function testGetSetUser(): void
    {
        $user = new User();
        $user->setEmail(self::$faker->email);
        $user->setPassword(self::$faker->password);

        self::$resultado->setUser($user);
        self::assertSame(
            $user,
            self::$resultado->getUser()
        );
    }

    /**
     * Implement testGetSetTime().
     *
     * @return void
     */
    public function testGetSetTime(): void
    {
        $time = self::$faker->dateTime;
        self::$resultado->setTime($time);
        self::assertSame(
            $time,
            self::$resultado->getTime()
        );
    }
}
